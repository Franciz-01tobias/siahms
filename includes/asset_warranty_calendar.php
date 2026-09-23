<?php
/**
 * Asset expiry dates → Calendar sync.
 *
 * Keeps two sets of auto-generated, all-day calendar events in step with the
 * dates on an asset:
 *
 *   warranty_expiry → "Warranty expiry: HOST", source 'asset_warranty'
 *   lease_expiry    → "Lease ends: HOST",      source 'asset_lease'
 *
 * Each is gated by its own setting (asset_warranty_surface / asset_lease_surface)
 * and cleared and rebuilt on its own `source`, so a full resync never disturbs
 * user-created events, and switching one kind off never removes the other.
 *
 * The file is still named after warranty because warranty is what it did first
 * and the name is referenced from several call sites; what it covers is the
 * list above, not the filename.
 *
 * Called opportunistically when an asset's warranty or lease date changes
 * (update_asset_field.php) and on demand when either setting is saved
 * (sync_warranty_calendar.php). Cheap full resync — these edits are rare and the
 * event set is small.
 */

if (!function_exists('syncAssetWarrantyCalendar')) {
    /**
     * @param PDO $conn
     * @return array{success:bool, synced?:int, error?:string}
     */
    function syncAssetWarrantyCalendar(PDO $conn): array
    {
        try {
            // The feature depends on schema added alongside it; bail quietly if
            // a DB verification hasn't run yet.
            if (!awcColumnExists($conn, 'calendar_events', 'source')
                || !awcColumnExists($conn, 'assets', 'warranty_expiry')) {
                return ['success' => false, 'error' => 'Schema not ready'];
            }

            // 🔴 lease_expiry is checked SEPARATELY and never added to the
            // guard above. It arrived later than the rest of this file, so on an
            // install that has pulled the update and not yet run Database
            // Verification it is missing - and folding it into that condition
            // would stop warranty entries syncing too, breaking a feature that
            // was working fine to make room for one that is not there yet.
            $leaseReady = awcColumnExists($conn, 'assets', 'lease_expiry');

            // Two independent kinds of entry, each with its own setting, its own
            // category and its own `source` value. They are cleared and rebuilt
            // separately, so switching leases off never disturbs the warranty
            // entries sitting beside them in the same calendar.
            $n = 0;

            $n += awcSyncKind(
                $conn,
                'asset_warranty',
                awcGetSetting($conn, 'asset_warranty_surface', 'dashboard'),
                'warranty_expiry',
                'Warranty',
                '#d13438',
                'Warranty expiry: ',
                'Auto-generated from the asset record. Edit the warranty date on the asset to change this.',
                true
            );

            $n += awcSyncKind(
                $conn,
                'asset_lease',
                awcGetSetting($conn, 'asset_lease_surface', 'dashboard'),
                'lease_expiry',
                'Lease',
                '#8b5cf6',
                'Lease ends: ',
                'Auto-generated from the asset record. Edit the lease end date on the asset to change this.',
                $leaseReady
            );

            return ['success' => true, 'synced' => $n];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Clear and rebuild one kind of auto-generated asset date entry.
     *
     * $ready is the caller's answer to "does the column exist yet" - passed in
     * rather than checked here so the two kinds cannot accidentally gate each
     * other, which is how a missing lease column would otherwise have taken the
     * warranty entries down with it.
     *
     * Clearing happens even when the kind is switched OFF, and even when the
     * column is missing: that is what makes turning it off actually remove the
     * entries instead of leaving the last set behind for ever.
     */
    function awcSyncKind(
        PDO $conn,
        string $source,
        string $surface,
        string $column,
        string $categoryName,
        string $categoryColour,
        string $titlePrefix,
        string $description,
        bool $ready
    ): int {
        $del = $conn->prepare("DELETE FROM calendar_events WHERE source = ?");
        $del->execute([$source]);

        if (!$ready || !in_array($surface, ['calendar', 'both'], true)) {
            return 0;
        }

        $categoryId = awcEnsureCategory($conn, $categoryName, $categoryColour);

        // The column name is interpolated, never bound - a placeholder cannot
        // stand for an identifier. It comes from this file's own two call sites
        // and never from a request, so there is nothing here a caller can steer.
        $rows = $conn->query(
            "SELECT id, hostname, `{$column}` AS expiry_date
             FROM assets
             WHERE `{$column}` IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return 0;
        }

        $ins = $conn->prepare(
            "INSERT INTO calendar_events
                (title, description, category_id, start_datetime, end_datetime, all_day, created_by, source)
             VALUES (?, ?, ?, ?, ?, 1, 0, ?)"
        );

        $n = 0;
        foreach ($rows as $r) {
            $host = $r['hostname'] !== null && $r['hostname'] !== '' ? $r['hostname'] : ('Asset #' . $r['id']);
            $dt = substr($r['expiry_date'], 0, 10) . ' 00:00:00';
            $ins->execute([$titlePrefix . $host, $description, $categoryId, $dt, $dt, $source]);
            $n++;
        }
        return $n;
    }

    /** Find or create a calendar category by name; returns its id (or null). */
    function awcEnsureCategory(PDO $conn, string $name, string $colour): ?int
    {
        $sel = $conn->prepare("SELECT id FROM calendar_categories WHERE name = ? LIMIT 1");
        $sel->execute([$name]);
        $id = $sel->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        try {
            $ins = $conn->prepare("INSERT INTO calendar_categories (name, color, is_active) VALUES (?, ?, 1)");
            $ins->execute([$name, $colour]);
            return (int)$conn->lastInsertId();
        } catch (Exception $e) {
            return null; // category column shape differs / table missing — events just go uncategorised
        }
    }

    function awcGetSetting(PDO $conn, string $key, string $default): string
    {
        try {
            $s = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $s->execute([$key]);
            $v = $s->fetchColumn();
            return ($v === false || $v === null || $v === '') ? $default : (string)$v;
        } catch (Exception $e) {
            return $default;
        }
    }

    function awcColumnExists(PDO $conn, string $table, string $col): bool
    {
        try {
            $s = $conn->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
            );
            $s->execute([$table, $col]);
            return (int)$s->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
