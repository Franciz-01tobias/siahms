<?php
/**
 * API: how the self-service portal looks and what it lets people do.
 *
 * GET   the current settings
 * POST  save them
 *
 * 🔑 WHY THIS IS NOT PART OF api/system/branding.php.
 *
 * Branding owns the ONE logo that the app, the login screen and the emails all
 * share. These settings are the portal's own: a separate logo for customers, a
 * header colour, and two switches that change what the portal lets somebody do.
 * Folding them into branding would put "may a customer close their own ticket?"
 * on a screen about logos.
 *
 * 🔴 Gated on System administrators like every other System endpoint. Two of
 * these decide what CUSTOMERS may do, and one of them closes tickets.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/admin_api_guard.php';
require_once '../../includes/functions.php';
require_once '../../includes/self_service_settings.php';

header('Content-Type: application/json');

try {
    $conn = connectToDatabase();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['success' => true, 'settings' => selfServicePortalSettings($conn)]);
        exit;
    }

    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) {
        echo json_encode(['success' => false, 'error' => 'Invalid request body']);
        exit;
    }

    $clean = [];

    // ── appearance ──────────────────────────────────────────────────────
    if (array_key_exists('logo_path', $in)) {
        // Empty means "use the main logo", which is the default and is how an
        // admin turns a portal-specific logo back off.
        $clean['self_service_logo_path'] = trim((string)$in['logo_path']);
    }
    if (array_key_exists('header_colour', $in)) {
        $v = trim((string)$in['header_colour']);
        // Empty = follow the theme. Otherwise a hex colour and nothing else:
        // this value is written into a style attribute.
        if ($v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            echo json_encode(['success' => false, 'error' => 'Header colour must be a 6-digit hex value like #336699']);
            exit;
        }
        $clean['self_service_header_colour'] = $v;
    }
    if (array_key_exists('table_header_colour', $in)) {
        $v = trim((string)$in['table_header_colour']);
        if ($v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            echo json_encode(['success' => false, 'error' => 'Table header colour must be a 6-digit hex value like #336699']);
            exit;
        }
        $clean['self_service_table_header_colour'] = $v;
    }
    if (array_key_exists('background_pattern', $in)) {
        $v = trim((string)$in['background_pattern']);
        // A fixed list, never a free string: this picks a CSS class.
        if ($v !== '' && !in_array($v, SELF_SERVICE_PATTERNS, true)) {
            echo json_encode(['success' => false, 'error' => 'Unknown background pattern']);
            exit;
        }
        $clean['self_service_background_pattern'] = $v;
    }

    // ── what the portal lets people do ──────────────────────────────────
    foreach (['allow_self_close' => 'self_service_allow_self_close',
              'show_my_assets'   => 'self_service_show_my_assets'] as $field => $key) {
        if (array_key_exists($field, $in)) {
            $clean[$key] = !empty($in[$field]) ? '1' : '0';
        }
    }

    if (!$clean) {
        echo json_encode(['success' => false, 'error' => 'Nothing to save']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, updated_datetime)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                 updated_datetime = VALUES(updated_datetime)"
    );
    foreach ($clean as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Read back from the database rather than echoing what was sent: the page
    // then shows what is stored, not what it hoped was stored.
    echo json_encode(['success' => true, 'settings' => selfServicePortalSettings($conn)]);

} catch (Throwable $e) {
    error_log('self_service_portal.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not save the settings']);
}
