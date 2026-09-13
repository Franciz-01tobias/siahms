<?php
/**
 * API: Delete an SSO / OIDC identity provider.
 * POST JSON { id }
 *
 * Linked rows in analyst_sso_identities are removed by the ON DELETE CASCADE
 * foreign key; any analyst whose auth_provider_id pointed here is reset to
 * local (ON DELETE SET NULL).
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/admin_api_guard.php'; // System admins only (issue #34)
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing provider id']);
    exit;
}

try {
    $conn = connectToDatabase();

    // ⚠️ Delete the write log EXPLICITLY rather than relying on the foreign key.
    // `carddav_write_log` declares ON DELETE CASCADE in database/freeitsm.sql, but
    // Database Verify creates tables from a column list and adds no constraints —
    // so on any install where that table was created by a verify rather than by
    // the schema file, there is no cascade and the rows are simply orphaned.
    // Verified on this dev box: `directory_sync_entries` has the same 0 foreign
    // keys for the same reason, so this is a general property of verified tables
    // and not something peculiar to CardDAV.
    //
    // 🔑 Doing it here is correct EITHER WAY: with the constraint present the rows
    // are already gone and this deletes nothing, and without it they go now.
    try {
        $conn->prepare("DELETE FROM carddav_write_log WHERE provider_id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // An install predating the table. Not a reason to refuse the delete.
        error_log('[sso] write log cleanup skipped: ' . $e->getMessage());
    }

    $stmt = $conn->prepare("DELETE FROM auth_providers WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
