<?php
/**
 * Self-service portal settings — read from one place, by both sides.
 *
 * 🔑 The settings screen and the portal itself both need these, and a second
 * copy of the defaults is how the two drift: the screen shows a switch as off
 * while the portal behaves as though it were on. One function, both callers.
 *
 * Every value degrades to the behaviour the portal had before these settings
 * existed, so an install that has never opened the screen is unchanged.
 */

/**
 * The background patterns the portal offers.
 *
 * A fixed list because the stored value picks a CSS class. Never a free string:
 * this reaches a class attribute, and "none" is the absence of a value rather
 * than an entry, so an install that has never chosen one gets a plain page.
 */
// 'waves' was removed after review: it drew a chain of overlapping ovals
// rather than texture, and no amount of lightening made it calm. An install
// that had already chosen it degrades to no pattern, because the getter below
// blanks any value not on this list.
const SELF_SERVICE_PATTERNS = ['dots', 'grid', 'diagonal', 'flow', 'mesh'];

/**
 * Every self-service portal setting, with its default.
 *
 * @return array{
 *   logo_path:string, header_colour:string, table_header_colour:string,
 *   background_pattern:string, allow_self_close:bool, show_my_assets:bool
 * }
 */
function selfServicePortalSettings(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $defaults = [
        'self_service_logo_path'           => '',   // empty = use the main logo
        'self_service_header_colour'       => '',   // empty = follow the theme
        'self_service_table_header_colour' => '',
        'self_service_background_pattern'  => '',
        'self_service_allow_self_close'    => '0',  // off: closing a ticket is a real action
        'self_service_show_my_assets'      => '0',
    ];

    $rows = [];
    try {
        $in = implode(',', array_fill(0, count($defaults), '?'));
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
        $stmt->execute(array_keys($defaults));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[$r['setting_key']] = (string)$r['setting_value'];
        }
    } catch (Throwable $e) {
        // No system_settings table yet, or the database is unreachable. The
        // portal still has to render, so every setting falls back to its
        // pre-existing behaviour rather than the page failing.
        error_log('self_service_settings: ' . $e->getMessage());
    }

    $get = fn(string $k): string => $rows[$k] ?? $defaults[$k];

    $pattern = $get('self_service_background_pattern');
    if ($pattern !== '' && !in_array($pattern, SELF_SERVICE_PATTERNS, true)) {
        $pattern = '';   // a value from a newer version, or hand-edited
    }

    $colour = function (string $k) use ($get): string {
        $v = $get($k);
        // Re-validated on the way OUT as well as in. The API checks it, but a
        // value can also arrive by hand-editing the table, and this one is
        // written into a style attribute.
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '';
    };

    return $cache = [
        'logo_path'           => $get('self_service_logo_path'),
        'header_colour'       => $colour('self_service_header_colour'),
        'table_header_colour' => $colour('self_service_table_header_colour'),
        'background_pattern'  => $pattern,
        'allow_self_close'    => $get('self_service_allow_self_close') === '1',
        'show_my_assets'      => $get('self_service_show_my_assets') === '1',
    ];
}
