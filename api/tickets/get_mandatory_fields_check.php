<?php
/**
 * API Endpoint: which mandatory fields would be empty if this ticket closed now.
 *
 * GET ?ticket_id=N
 *   -> { success, mode: "warn"|"notify"|"block", record: bool,
 *        missing: [ { key, label } ] }
 *
 * Asked by the inbox the moment an analyst picks a closed status, so it can say
 * "close anyway?" or "fill these in first" BEFORE sending the close. The server
 * enforces the rule again on the close itself (TicketsService::updateTicket), so
 * this is only the conversation - never the control. If it fails, the inbox says
 * nothing and lets the server decide, rather than turning a failed question into
 * a refusal nobody can clear.
 *
 * The inbox saves each field the moment it changes, so the stored ticket is what
 * the close will be judged on.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/i18n.php';
require_once '../../includes/services/tickets.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('tickets');
I18n::initFromSession();

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ticket_id is required']);
    exit;
}

try {
    $conn = connectToDatabase();
    // The same scope rule as every other ticket read: out of scope is "not found".
    $ticket   = TicketsService::loadTicket($conn, ActorContext::fromSession($conn), $ticketId);
    $tenantId = $ticket['tenant_id'] !== null ? (int) $ticket['tenant_id'] : null;
    $cfg      = MandatoryFieldsService::settings($conn, $tenantId);

    $missing = [];
    foreach (MandatoryFieldsService::missing($conn, $tenantId, $ticket) as $key => $english) {
        $label = t('tickets.settings.mandatory.fields.' . $key);
        $missing[] = [
            'key'   => $key,
            // t() hands the key back when a string is absent; never show that.
            'label' => ($label === '' || $label === 'tickets.settings.mandatory.fields.' . $key) ? $english : $label,
        ];
    }

    echo json_encode([
        'success' => true,
        'mode'    => $cfg['mode'],
        'record'  => $cfg['record'],
        'missing' => $missing,
    ]);
} catch (ServiceError $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
