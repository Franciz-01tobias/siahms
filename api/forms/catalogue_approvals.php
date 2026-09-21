<?php
/**
 * API: the catalogue-request approval inbox (#928).
 *
 * GET ?filter=mine|all|decided
 *   -> { success, items: [...], counts: { mine, all, decided } }
 *
 * Module access only, like the Change approvals inbox: signing off a catalogue request
 * is everyday service-desk work. The engine (includes/catalogue_approvals.php) still
 * enforces that only the ASSIGNED approver (or an admin) can actually decide one.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/catalogue_approvals.php';

header('Content-Type: application/json');
if (!isset($_SESSION['analyst_id'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit; }
requireModuleAccessJson('forms');

try {
    $filter = $_GET['filter'] ?? 'mine';
    if (!in_array($filter, ['mine', 'all', 'decided'], true)) $filter = 'mine';

    $conn = connectToDatabase();
    $res  = catalogueApprovalsList($conn, (int)$_SESSION['analyst_id'], $filter);
    echo json_encode(array_merge(['success' => true], $res));
} catch (Throwable $e) {
    /* 🔴 Throwable, not Exception. A PHP Error — a TypeError, a call to a
       function that is not there — is NOT an Exception, so it walked straight
       past this handler, and a fatal still answers HTTP 200 with an HTML error
       page. The page could not parse that as JSON and fell back to printing the
       word "Error" with nothing after it, which is what a user reported and what
       left nobody any way to tell what had happened. Catching Throwable means
       the screen can at least name the fault. */
    error_log('catalogue_approvals: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
