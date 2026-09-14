<?php
/**
 * ChecklistsService — the single home for the SOP-checklist rules that other
 * modules have to obey.
 *
 * Contributed as PR #141 by Santhosh Srinivasan; this layer was added during
 * integration so the one rule that reaches outside the module — "a ticket with
 * outstanding mandatory steps does not close" — is written once instead of
 * being re-stated by every caller that can close a ticket.
 *
 * Follows the three service rules (wiki: Service-Layer-Architecture):
 *   1. method(PDO, ActorContext, …) — no superglobals
 *   2. no transport — returns data or throws ServiceError, never echoes
 *   3. typed errors — ServiceError(kind, code, message)
 *
 * ──────────────────────────────────────────────────────────────────────────
 * 🔴 WHY THE GATE IS HERE AND NOT IN THE BROWSER
 *
 * As contributed, the gate was an alert() in assets/js/inbox.js and nothing
 * else. The UI refused to close the ticket; the v1 REST API, bulk actions, the
 * workflow engine and one line of curl all closed it with every mandatory step
 * outstanding. For a feature whose entire purpose is ISO/SOP compliance, an
 * unenforced gate is worse than no gate — it reports control that isn't there.
 *
 * ⚠️ AND WHY IT CAN ALWAYS BE OVERRIDDEN.
 *
 * assets/js/inbox.js already carries the house position, written for #83 and
 * sitting three lines below where the checklist gate was inserted:
 *
 *     "closing a ticket that still has unfinished tasks WARNS, it never blocks
 *      … a warning that cannot be shown must not become a block that cannot be
 *      cleared."
 *
 * That is right, and a hard block breaks it: the analyst who owned step 4 has
 * left, the step is obsolete, and the ticket is stuck forever with no way out
 * but SQL. So the rule is not "you cannot close this". It is:
 *
 *     close it with the steps outstanding and the override is RECORDED —
 *     who closed it, when, and which steps were skipped.
 *
 * Which is what a compliance feature should produce anyway. An auditor does not
 * want a system where the bad outcome was impossible; they want one where it is
 * attributable. Blocking hides the exception, recording it surfaces it.
 * ──────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../service_context.php';

class ChecklistsService
{
    /**
     * Mandatory steps still outstanding on a ticket.
     *
     * @return array<int, array{checklist: string, step: string, item_id: int}>
     */
    public static function outstandingMandatorySteps(PDO $conn, int $ticketId): array
    {
        if ($ticketId <= 0) return [];

        // Tolerate the tables not existing yet: a site that has never opened the
        // Checklists module must not have its ticket closures start failing.
        try {
            $stmt = $conn->prepare(
                "SELECT i.id AS item_id, i.title AS step, c.title AS checklist
                   FROM ticket_checklist_items i
                   JOIN ticket_checklists c ON c.id = i.ticket_checklist_id
                  WHERE c.ticket_id = ?
                    AND i.is_mandatory = 1
                    AND i.is_completed = 0
                  ORDER BY c.id ASC, i.sort_order ASC, i.id ASC"
            );
            $stmt->execute([$ticketId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Called by TicketsService when a ticket moves to a closed status.
     *
     * Records the closure against any outstanding mandatory steps rather than
     * refusing it — see the header. Returns the steps that were outstanding so
     * the caller can tell the analyst what was skipped; empty array = clean close.
     *
     * @return array<int, array{checklist: string, step: string, item_id: int}>
     */
    public static function recordClosureOverride(PDO $conn, ActorContext $ctx, int $ticketId): array
    {
        $outstanding = self::outstandingMandatorySteps($conn, $ticketId);
        if (!$outstanding) return [];

        $who  = $ctx->actorName !== '' ? $ctx->actorName : ('analyst #' . $ctx->actorId);
        $via  = $ctx->source === 'api' ? 'the API' : 'the web interface';
        $list = implode("\n", array_map(
            fn($r) => '  • ' . $r['checklist'] . ' — ' . $r['step'],
            $outstanding
        ));
        $note = "⚠️ Ticket closed with " . count($outstanding) . " mandatory SOP step(s) outstanding.\n"
              . "Closed by {$who} via {$via}.\n\n{$list}";

        // An internal note, because that is where this module already writes its
        // audit trail and where an auditor will look. UTC at rest (GH #126).
        try {
            $conn->prepare(
                "INSERT INTO ticket_notes (ticket_id, analyst_id, note_text, is_internal, created_datetime)
                 VALUES (?, ?, ?, 1, UTC_TIMESTAMP())"
            )->execute([$ticketId, $ctx->actorId, $note]);
        } catch (Throwable $e) {
            // A failed audit note must not block the close it is describing, but
            // it must not pass silently either.
            error_log('[checklists] could not record closure override for ticket '
                . $ticketId . ': ' . $e->getMessage());
        }

        return $outstanding;
    }
}
