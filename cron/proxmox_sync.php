<?php
/**
 * Proxmox VE sync — scheduled entry point (CLI only).
 *
 *   php cron/proxmox_sync.php
 *
 * Syncs every active Proxmox server whose interval has passed since its last
 * sync. Run it every few minutes (for example from the host's cron, or a
 * container's loop); each server then runs on its own interval. A server that
 * fails does not stop the others.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run from the command line.\n");
}

set_time_limit(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/proxmox_sync.php';
require_once __DIR__ . '/../includes/job_runs.php';   // run history (#182)

$conn = connectToDatabase();
// GH #217: nothing to do while the module is switched off for the install.
if (cronModuleSwitchedOff($conn, 'assets', 'proxmox_sync', 'cron')) exit(0);

// Never two at once (#182): a slow sync overlapping the next would read the same
// servers twice and race to write the same assets.
if ((int)$conn->query("SELECT GET_LOCK('freeitsm_proxmox_sync', 0)")->fetchColumn() !== 1) {
    JobRun::skipped($conn, 'proxmox_sync', 'cron', 'Another sync is still running.');
    echo date('c') . " another sync is still running\n";
    exit(0);
}
$runId = JobRun::start($conn, 'proxmox_sync', 'cron');
$failed = 0;
$due = $conn->query(
    "SELECT id, name FROM proxmox_connections
     WHERE is_active = 1
       AND (last_sync_datetime IS NULL
            OR last_sync_datetime <= UTC_TIMESTAMP() - INTERVAL sync_interval_minutes MINUTE)
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($due as $server) {
    try {
        $s = proxmoxSyncConnection($conn, (int)$server['id']);
        // 'warning' is a sync that worked with something worth reading; 'error' failed.
        $ok = ($s['status'] ?? '') !== 'error';
        if (!$ok) $failed++;
        JobRun::source($conn, $runId, 'proxmox_sync', 'server:' . (int)$server['id'], (string)$server['name'], $ok, $s['message'] ?? null);
        echo date('c') . ' ' . $server['name'] . ': ' . $s['status'] . ' - ' . $s['message'] . "\n";
    } catch (Exception $e) {
        // proxmoxSyncConnection records its own failures; this only catches setup errors.
        echo date('c') . ' ' . $server['name'] . ': error - ' . $e->getMessage() . "\n";
        $failed++;
        JobRun::source($conn, $runId, 'proxmox_sync', 'server:' . (int)$server['id'], (string)$server['name'], false, $e->getMessage());
    }
}
echo date('c') . ' done, ' . count($due) . " server(s) due\n";
JobRun::finish($conn, $runId, null, ['servers' => count($due), 'failed' => $failed], count($due) . " server(s) due, {$failed} failed.");
exit($failed > 0 ? 1 : 0);
