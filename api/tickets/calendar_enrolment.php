<?php
/**
 * API: an analyst's OWN calendar choice (GH #75, CalDAV #133).
 *
 *   GET            -> my mode, where my work would go, and what is on offer
 *   POST mode=off|push|feed
 *
 * CalDAV only - the analyst signs in as themselves, so they also choose where:
 *   POST action=caldav_discover  username, password      -> the calendars that sign-in can write to
 *   POST action=caldav_save      username, password, calendar_url[, turn_on=1]
 *   POST action=caldav_forget                            -> take our events back, forget the sign-in
 *
 * 🔴 WITH MICROSOFT THIS ENDPOINT CANNOT SET AN ADDRESS. Only mode. The
 * application permission behind that push can write to any mailbox in the
 * tenant, so letting an analyst name their own target would let them fill a
 * colleague's calendar with their tickets. Where it goes is an administrator's
 * decision (System → Calendar sync); whether it happens at all is the analyst's.
 *
 * 🔑 WITH CALDAV IT CAN, AND MUST. Each calendar belongs to its owner's account
 * and FreeITSM signs in with the analyst's own password, so the only calendars
 * it can reach are ones they could already write to - and nobody else has the
 * password to choose for them. The calendar must still be on the server the
 * administrator configured (CalDavCalendarProvider::calendarUrl).
 *
 * 🔑 ONE MODE, NOT TWO SWITCHES. With a push and a subscribed feed both live you
 * see every scheduled ticket twice — once as a real event, once from the
 * subscription. A single value makes that unrepresentable.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/calendar_sync/calendar_sync.php';
require_once '../../includes/timezone.php';   // naive_now(), for the work-window backfill (GH #126)

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tickets');

$analystId = (int)$_SESSION['analyst_id'];

/**
 * Put what is already scheduled into a calendar that has just been switched on
 * (or just changed), rather than only tickets touched from now on — an empty
 * calendar after opting in reads as "it didn't work".
 */
function enrolBackfillTickets(PDO $conn, int $analystId): void
{
    require_once __DIR__ . '/../../includes/calendar_sync/push.php';
    $st = $conn->prepare(
        "SELECT t.id FROM tickets t
           LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
          WHERE t.owner_id = :analyst AND t.work_start_datetime IS NOT NULL
            AND t.deleted_datetime IS NULL AND COALESCE(ts.is_closed, 0) = 0
            AND t.work_start_datetime >= (:cutoff - INTERVAL 1 WEEK)
          ORDER BY t.work_start_datetime"
    );
    // A wall clock — work_start_datetime is naive (GH #126).
    //
    // 🔴 The same reversed-parameter bug as the subscribe feed (GH #133):
    // #1446 passed the cutoff where the analyst goes, so this backfill
    // selected NOTHING and switching push on left the calendar empty —
    // which is precisely the failure the comment above says it exists to
    // prevent. Named so it cannot happen a third time.
    $st->execute([':analyst' => $analystId, ':cutoff' => naive_now()]);
    // Bounded to the last week onwards on purpose: back-filling months of
    // finished work would fill a calendar with history nobody asked for, and
    // make opting in a very long request.
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tid) {
        calendarSyncReconcileTicket($conn, (int)$tid);
    }
}

/**
 * Tasks, whenever the choice moved at all (#75).
 *
 * 🔑 RECONCILE, DON'T BRANCH. The same sweep handles switching tasks on,
 * switching them off, and narrowing 'both' to one kind — because
 * calendarSyncReconcileTask() works out what SHOULD be there and makes that
 * true.
 */
function enrolReconcileTasks(PDO $conn, int $analystId): void
{
    require_once __DIR__ . '/../../includes/calendar_sync/push.php';
    $st = $conn->prepare(
        "SELECT tk.id
           FROM tasks tk
      LEFT JOIN task_statuses ts ON ts.id = tk.status_id
          WHERE tk.assigned_analyst_id = ?
            AND (tk.work_start_datetime IS NOT NULL OR tk.due_date IS NOT NULL)
            AND COALESCE(ts.is_closed, 0) = 0
         UNION
         SELECT task_id FROM calendar_sync_events WHERE analyst_id = ? AND task_id IS NOT NULL"
    );
    // ⚠️ The UNION is not tidiness. Turning tasks OFF means reconciling
    // tasks that no longer qualify on the first half — a completed one, or
    // one reassigned away — and those are exactly the rows whose events
    // must be taken back. Without it, switching off would leave them.
    $st->execute([$analystId, $analystId]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tid) {
        if ($tid) calendarSyncReconcileTask($conn, (int)$tid);
    }
}

try {
    $conn = connectToDatabase();

    if (!calendarSyncSchemaReady($conn)) {
        // Not an error the analyst can do anything about — report it as "nothing
        // on offer" rather than a failure, and the screen simply shows less.
        echo json_encode([
            'success' => true, 'mode' => CALENDAR_MODE_OFF,
            'push_available' => false, 'feed_available' => false,
            'reason' => 'needs_db',
        ]);
        exit;
    }

    $enrolment  = calendarSyncEnrolment($conn, $analystId);
    $connection = calendarSyncActiveConnection($conn);
    $isCalDav   = $connection && $connection['provider'] === 'caldav';
    $account    = calendarSyncAccount($enrolment);
    $saved      = calendarSyncDecodeCredentials($enrolment['credentials'] ?? null);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success'        => true,
            'mode'           => $enrolment['mode'],
            'task_mode'      => $enrolment['task_mode'] ?? TASK_CAL_OFF,
            'address'        => $enrolment['calendar_address'],
            'push_available' => (bool)$connection,
            'feed_available' => scheduleFeedAllowed($conn),
            'last_error'     => $enrolment['last_error'] ?? null,
            'provider'       => $connection ? $connection['provider'] : null,
            // CalDAV: who they sign in as and which calendar - never the password.
            'caldav'         => $isCalDav ? [
                'username'      => $account['username'],
                'has_password'  => $account['password'] !== '',
                'calendar_url'  => $enrolment['calendar_address'],
                'calendar_name' => (string)($saved['calendar_name'] ?? ''),
                'server_url'    => (string)($connection['credentials']['server_url'] ?? ''),
            ] : null,
        ]);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    // ── CalDAV: the analyst's own sign-in and calendar ──────────────────────
    if ($action !== '') {
        if (!$isCalDav) {
            echo json_encode(['success' => false, 'error' => 'This system is not connected to a CalDAV server.']);
            exit;
        }
        require_once '../../includes/calendar_sync/push.php';

        // A blank password means "the one I saved", so somebody changing
        // calendars does not have to type it again. Only for the same username:
        // a new username with the old password is not what anybody means.
        $typedUser = trim((string)($_POST['username'] ?? ''));
        $typedPass = (string)($_POST['password'] ?? '');
        if ($typedPass === '' && $typedUser === $account['username']) {
            $typedPass = $account['password'];
        }

        if ($action === 'caldav_discover') {
            if ($typedUser === '') {
                echo json_encode(['success' => false, 'error' => 'Enter your calendar username.']);
                exit;
            }
            $found = calendarSyncProviderFor($connection)
                ->withAccount(['username' => $typedUser, 'password' => $typedPass])
                ->discoverCalendars();
            if (!$found['ok']) {
                echo json_encode(['success' => false, 'error' => $found['error'], 'auth' => $found['auth']]);
                exit;
            }
            echo json_encode(['success' => true, 'calendars' => $found['calendars'], 'auth' => $found['auth']]);
            exit;
        }

        if ($action === 'caldav_save') {
            $url = trim((string)($_POST['calendar_url'] ?? ''));
            if ($typedUser === '' || $url === '') {
                echo json_encode(['success' => false, 'error' => 'Sign in and choose a calendar first.']);
                exit;
            }
            $provider = calendarSyncProviderFor($connection)
                ->withAccount(['username' => $typedUser, 'password' => $typedPass]);
            try {
                $check = $provider->checkCalendar($url);
                $url   = $provider->calendarUrl($url);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            if (!$check['ok']) {
                echo json_encode(['success' => false, 'error' => $check['error']]);
                exit;
            }
            // calendar_address and calendar_sync_events.remote_calendar are both
            // VARCHAR(255). Refused rather than cut short: a truncated address
            // is a calendar that looks set and can never be reached.
            if (strlen($url) > 255) {
                echo json_encode(['success' => false, 'error' => 'That calendar’s address is too long for FreeITSM to store (over 255 characters).']);
                exit;
            }

            // Moving to a different calendar or account: take our events out of
            // the old one first, signed in as the old account, while we still can.
            $wasUrl  = (string)($enrolment['calendar_address'] ?? '');
            $moving  = $wasUrl !== '' && ($wasUrl !== $url || $account['username'] !== $typedUser);
            if ($moving) calendarSyncRemoveAllForAnalyst($conn, $analystId);

            $wasPushing = ($enrolment['mode'] ?? '') === CALENDAR_MODE_PUSH;
            $turnOn     = !empty($_POST['turn_on']);
            $mode       = ($wasPushing || $turnOn) ? CALENDAR_MODE_PUSH : (string)$enrolment['mode'];

            $conn->prepare(
                "INSERT INTO calendar_enrolments (analyst_id, mode, connection_id, calendar_address, credentials)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE mode = VALUES(mode), connection_id = VALUES(connection_id),
                                         calendar_address = VALUES(calendar_address),
                                         credentials = VALUES(credentials),
                                         delta_token = IF(? = 1, NULL, delta_token),
                                         last_error = NULL, updated_datetime = UTC_TIMESTAMP()"
            )->execute([
                $analystId, $mode, (int)$connection['id'], $url,
                calendarSyncEncodeCredentials([
                    'username' => $typedUser, 'password' => $typedPass, 'calendar_name' => $check['name'],
                ]),
                ($moving || $wasUrl === '') ? 1 : 0,
            ]);

            // A calendar that is now receiving work, for the first time or
            // afresh, gets what is already scheduled.
            if ($mode === CALENDAR_MODE_PUSH && (!$wasPushing || $moving)) {
                enrolBackfillTickets($conn, $analystId);
                enrolReconcileTasks($conn, $analystId);
            }
            $after = calendarSyncEnrolment($conn, $analystId);
            echo json_encode(['success' => true, 'mode' => $mode, 'calendar_name' => $check['name'],
                              'last_error' => $after['last_error'] ?? null]);
            exit;
        }

        if ($action === 'caldav_forget') {
            // Take back what we put there while the sign-in still works, then
            // forget it. Leaving events FreeITSM can no longer update is the
            // worst of both worlds.
            calendarSyncRemoveAllForAnalyst($conn, $analystId);
            $conn->prepare(
                "UPDATE calendar_enrolments
                    SET mode = IF(mode = 'push', 'off', mode), calendar_address = NULL, credentials = NULL,
                        delta_token = NULL, delta_synced_datetime = NULL, last_error = NULL,
                        updated_datetime = UTC_TIMESTAMP()
                  WHERE analyst_id = ?"
            )->execute([$analystId]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
    }

    // ── The mode ────────────────────────────────────────────────────────────
    $mode = (string)($_POST['mode'] ?? '');
    if (!calendarModeIsValid($mode)) {
        echo json_encode(['success' => false, 'error' => 'Unknown option.']);
        exit;
    }
    if ($mode === CALENDAR_MODE_FEED && !scheduleFeedAllowed($conn)) {
        echo json_encode(['success' => false, 'error' => 'Subscription links are switched off on this system.']);
        exit;
    }

    if ($mode === CALENDAR_MODE_PUSH) {
        if (!$connection) {
            echo json_encode(['success' => false, 'error' => 'No calendar connection has been set up on this system yet.']);
            exit;
        }
        $address = $enrolment['calendar_address'] ?? '';

        // CalDAV: nothing to switch on until they have signed in and chosen a
        // calendar. Said as its own answer so the screen can show the form.
        if ($isCalDav && ($account['username'] === '' || $address === '' || $address === null)) {
            echo json_encode(['success' => false, 'needs_account' => true,
                'error' => 'Sign in to your calendar and choose one first.']);
            exit;
        }
        if ($address === '' || $address === null) {
            echo json_encode(['success' => false, 'no_address' => true,
                'error' => 'We do not know which mailbox is yours.']);
            exit;
        }

        // 🔑 VERIFIED AT THE MOMENT OF OPTING IN, not on every page load. One
        // call when the analyst actually chooses this is cheap and is exactly
        // when the answer matters; checking on every visit to Preferences would
        // be a call per page view for a question that rarely changes.
        //
        // ⚠️ And it must be checked SOMEWHERE. Ed's own FreeITSM address is
        // admin@localhost, which is not a mailbox at all — switching this on and
        // discovering nothing ever appeared, with no explanation, is the failure
        // this prevents.
        try {
            $provider = calendarSyncProviderFor($connection, $enrolment);
            $provider->conn = $conn;
            if ($isCalDav) {
                $check = $provider->checkCalendar($address);
                if (!$check['ok']) {
                    echo json_encode(['success' => false, 'needs_account' => true, 'error' => $check['error']]);
                    exit;
                }
            } elseif (!$provider->verifyTarget($address)) {
                echo json_encode(['success' => false, 'bad_address' => true, 'address' => $address,
                    'error' => 'No calendar could be found for ' . $address . '.']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    $wasPushing = ($enrolment['mode'] ?? '') === CALENDAR_MODE_PUSH;

    // What of a TASK they want (#75). Sent alongside the mode because they are
    // chosen on the same screen; absent means "leave it as it was", so an older
    // client that does not know about tasks cannot silently switch them off.
    $wasTaskMode = (string)($enrolment['task_mode'] ?? TASK_CAL_OFF);
    $taskMode    = array_key_exists('task_mode', $_POST) ? (string)$_POST['task_mode'] : $wasTaskMode;
    if (!taskCalendarModeIsValid($taskMode)) {
        echo json_encode(['success' => false, 'error' => 'Unknown task calendar choice']);
        exit;
    }

    $conn->prepare(
        "INSERT INTO calendar_enrolments (analyst_id, mode, task_mode, connection_id)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE mode = VALUES(mode), task_mode = VALUES(task_mode),
                                 connection_id = VALUES(connection_id),
                                 last_error = NULL, updated_datetime = UTC_TIMESTAMP()"
    )->execute([$analystId, $mode, $taskMode,
                ($mode === CALENDAR_MODE_PUSH && $connection) ? (int)$connection['id'] : null]);

    // 🔑 TURNING IT OFF TAKES BACK WHAT WE PUT THERE. Leaving events behind in
    // somebody's calendar that FreeITSM has stopped tracking is the worst of both
    // worlds: they cannot be updated, they cannot be removed by us later, and the
    // person has to delete each one by hand wondering where they came from.
    //
    // A CalDAV sign-in is KEPT when the mode goes off, so switching back on is
    // one click. "Forget my sign-in" is its own action.
    if ($wasPushing && $mode !== CALENDAR_MODE_PUSH) {
        require_once '../../includes/calendar_sync/push.php';
        calendarSyncRemoveAllForAnalyst($conn, $analystId);
    }

    if (!$wasPushing && $mode === CALENDAR_MODE_PUSH) {
        enrolBackfillTickets($conn, $analystId);
    }

    // ⚠️ Also runs when the ticket mode changed, because task events only exist
    // while mode is 'push' — turning tickets off has to take tasks with them.
    if ($taskMode !== $wasTaskMode || $wasPushing !== ($mode === CALENDAR_MODE_PUSH)) {
        enrolReconcileTasks($conn, $analystId);
    }

    echo json_encode(['success' => true, 'mode' => $mode, 'task_mode' => $taskMode,
                      'address' => $enrolment['calendar_address']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
