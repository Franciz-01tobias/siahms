<?php
/**
 * vCloud Director sync — scheduled entry point (CLI only).
 *
 *   php cron/vcloud_sync.php
 *
 * Syncs every active vCloud Director server whose interval has passed since its last sync.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run from the command line.\n");
}

set_time_limit(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/vcloud_sync.php';
require_once __DIR__ . '/../includes/job_runs.php';   // run history (#182)

$conn = connectToDatabase();

// Never two at once (#182): a slow sync overlapping the next would read the same
// servers twice and race to write the same assets.
if ((int)$conn->query("SELECT GET_LOCK('freeitsm_vcloud_sync', 0)")->fetchColumn() !== 1) {
    JobRun::skipped($conn, 'vcloud_sync', 'cron', 'Another sync is still running.');
    echo date('c') . " another sync is still running\n";
    exit(0);
}
$runId = JobRun::start($conn, 'vcloud_sync', 'cron');
$failed = 0;
$due = $conn->query(
    "SELECT id, name FROM vcloud_connections
     WHERE is_active = 1
       AND (last_sync_datetime IS NULL
            OR last_sync_datetime <= UTC_TIMESTAMP() - INTERVAL sync_interval_minutes MINUTE)
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($due as $server) {
    try {
        $s = vcdSyncConnection($conn, (int)$server['id']);
        // 'warning' is a sync that worked with something worth reading; 'error' failed.
        $ok = ($s['status'] ?? '') !== 'error';
        if (!$ok) $failed++;
        JobRun::source($conn, $runId, 'vcloud_sync', 'server:' . (int)$server['id'], (string)$server['name'], $ok, $s['message'] ?? null);
        echo date('c') . ' ' . $server['name'] . ': ' . $s['status'] . ' - ' . $s['message'] . "\n";
    } catch (Exception $e) {
        echo date('c') . ' ' . $server['name'] . ': error - ' . $e->getMessage() . "\n";
        $failed++;
        JobRun::source($conn, $runId, 'vcloud_sync', 'server:' . (int)$server['id'], (string)$server['name'], false, $e->getMessage());
    }
}
echo date('c') . ' done, ' . count($due) . " server(s) due\n";
JobRun::finish($conn, $runId, null, ['servers' => count($due), 'failed' => $failed], count($due) . " server(s) due, {$failed} failed.");
exit($failed > 0 ? 1 : 0);
