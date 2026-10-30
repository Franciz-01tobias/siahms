<?php
/**
 * Source-owned inventory observations through the REST API (GH #194, Part B).
 *
 * An external tool (an RMM, Lansweeper, a CMDB export) reports what it saw on a device:
 * software, volumes, network adapters. Its rows sit in the SAME tables the agent and
 * Intune write - the inventory - tagged source = 'rest:<source>', so every screen,
 * report and API that reads inventory sees them, and no other source's rows are touched.
 *
 * The rules (wiki: API-Inventory-and-Evidence-Design, Part B):
 *   - A source name belongs to ONE analyst per company - the analyst behind the first key
 *     that wrote it (an integration's service account). A rotated key acts as the same
 *     analyst, so it keeps writing; another integration cannot clear this one's rows by
 *     sending its name. Built-in source names are refused.
 *   - (source, source_device_ref) is linked to one asset per company. Naming the same
 *     device on another asset is a 409 naming the asset - never a silent move. Unlinking
 *     (DELETE) is the deliberate re-link.
 *   - Each section is judged on its own: older than the newest applied is STALE (left
 *     alone), the same time and content is REPLAYED (nothing written), the same time and
 *     different content FAILS (observation_conflict). Complete = brought exactly into line,
 *     empty included; partial = added and updated, nothing removed; omitted = untouched.
 *   - Partial success: a section that fails validation or conflicts is rolled back to its
 *     own savepoint and named in the response; the others apply. A failed section records
 *     no watermark, so sending the same report again retries just that section - the
 *     sections that applied answer "replayed".
 *   - Never touched: assets.last_seen (it means "the agent reported"), the asset's own
 *     fields, other sources' rows.
 *
 * Contributed by @Dr4inder (PR #197); ownership, partial success and reconciliation by
 * row reworked in review - see wiki REST-Inventory-and-Evidence-PR-Review.
 *
 * @wiki REST-API-Assets
 */
require_once __DIR__ . '/assets.php';

final class AssetInventoryService
{
    private const TABLES = ['software' => 'software_inventory_detail', 'disks' => 'asset_disks', 'network_adapters' => 'asset_network_adapters'];

    /** Names FreeITSM writes itself, or that read as if it did. */
    private const RESERVED = ['agent', 'intune', 'proxmox', 'vcloud', 'vcenter', 'manual', 'api', 'system', 'freeitsm'];

    /** The stored source tag for a REST source name. */
    public static function storedSource(string $source): string
    {
        return 'rest:' . $source;
    }

    /**
     * Apply one report. Whole-request problems (the envelope, the asset, ownership, a
     * device linked elsewhere) throw ServiceError; a section's own problem is reported in
     * its result and the rest still apply.
     *
     * @return array{asset_id:int, source:string, source_device_ref:string, observed_at:string, sections:array<string,array>}
     */
    public static function submit(PDO $conn, ActorContext $ctx, int $assetId, array $in): array
    {
        [$source, $deviceRef, $observed, $rawSections] = self::envelope($in);
        if ($conn->inTransaction()) { throw new LogicException('Inventory submission must own its transaction.'); }
        $stored = self::storedSource($source);
        $discovered = [];
        $conn->beginTransaction();
        try {
            $asset = self::asset($conn, $ctx, $assetId, true);
            $tenant = $asset['tenant_id'] === null ? getDefaultTenantId($conn) : (int)$asset['tenant_id'];
            self::claimSource($conn, $ctx, $tenant, $source);
            $bindingId = self::bind($conn, $assetId, $tenant, $source, $deviceRef);

            $result = ['asset_id' => $assetId, 'source' => $source, 'source_device_ref' => $deviceRef,
                       'observed_at' => str_replace(' ', 'T', $observed) . 'Z', 'sections' => []];
            $applied = [];
            foreach ($rawSections as $name => $raw) {
                $conn->exec('SAVEPOINT inv_section');
                $discoveredBefore = count($discovered);
                try {
                    $section = self::section($name, $raw);
                    $fingerprint = hash('sha256', json_encode($section, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    $get = $conn->prepare('SELECT observed_at, fingerprint FROM asset_inventory_sections WHERE inventory_source_id = ? AND section_name = ?');
                    $get->execute([$bindingId, $name]);
                    $previous = $get->fetch(PDO::FETCH_ASSOC);
                    if ($previous && $observed < $previous['observed_at']) {
                        $result['sections'][$name] = ['result' => 'stale', 'newer_observed_at' => str_replace(' ', 'T', $previous['observed_at']) . 'Z'];
                        $conn->exec('RELEASE SAVEPOINT inv_section');
                        continue;
                    }
                    if ($previous && $observed === $previous['observed_at']) {
                        if ($previous['fingerprint'] !== $fingerprint) {
                            throw new ServiceError('conflict', 'observation_conflict', "A different $name list was already applied for this observation time.");
                        }
                        $result['sections'][$name] = ['result' => 'replayed'];
                        $conn->exec('RELEASE SAVEPOINT inv_section');
                        continue;
                    }
                    $counts = self::applySection($conn, $assetId, $stored, $name, $section, $observed, $discovered);
                    $conn->prepare('INSERT INTO asset_inventory_sections (inventory_source_id, section_name, observed_at, fingerprint, imported_at, imported_by_id)
                        VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)
                        ON DUPLICATE KEY UPDATE observed_at = VALUES(observed_at), fingerprint = VALUES(fingerprint),
                            imported_at = VALUES(imported_at), imported_by_id = VALUES(imported_by_id)')
                        ->execute([$bindingId, $name, $observed, $fingerprint, $ctx->actorId]);
                    $conn->exec('RELEASE SAVEPOINT inv_section');
                    $result['sections'][$name] = ['result' => 'applied'] + $counts;
                    $applied[] = $name;
                } catch (ServiceError $e) {
                    // This section only: back to its savepoint, named in the response.
                    $conn->exec('ROLLBACK TO SAVEPOINT inv_section');
                    array_splice($discovered, $discoveredBefore);   // their catalogue rows were rolled back too
                    $result['sections'][$name] = ['result' => 'failed', 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
                }
            }
            if ($applied) {
                $conn->prepare('INSERT INTO asset_history (asset_id, analyst_id, field_name, old_value, new_value, created_datetime)
                    VALUES (?, ?, ?, NULL, ?, UTC_TIMESTAMP())')->execute([$assetId, $ctx->actorId, 'inventory_import',
                    $source . ' (' . implode(', ', $applied) . '), observed ' . $observed . ' UTC']);
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            throw $e;
        }
        // After the commit, as the agent and Intune do: a new application in the catalogue
        // is a workflow trigger, and a workflow must never see a rolled-back app.
        if ($discovered) {
            require_once dirname(__DIR__, 2) . '/workflow/includes/engine.php';
            foreach ($discovered as $app) {
                try { WorkflowEngine::dispatch('software.application_discovered', ['application' => $app]); }
                catch (Throwable $wfEx) { error_log('Workflow dispatch error in AssetInventoryService: ' . $wfEx->getMessage()); }
            }
        }
        return $result;
    }

    /**
     * Unlink a source from an asset: its link, its section times and every row it
     * reported here are removed. The deliberate way to move a device reference to
     * another asset (a reimaged machine) - the next report then links it afresh.
     */
    public static function unlink(PDO $conn, ActorContext $ctx, int $assetId, string $source): array
    {
        if ($conn->inTransaction()) { throw new LogicException('Inventory unlink must own its transaction.'); }
        $conn->beginTransaction();
        try {
            $asset = self::asset($conn, $ctx, $assetId, true);
            $tenant = $asset['tenant_id'] === null ? getDefaultTenantId($conn) : (int)$asset['tenant_id'];
            $find = $conn->prepare('SELECT id, source_device_ref FROM asset_inventory_sources WHERE asset_id = ? AND source = ?');
            $find->execute([$assetId, $source]);
            $binding = $find->fetch(PDO::FETCH_ASSOC);
            if (!$binding) { throw new ServiceError('not_found', 'not_found', 'This source is not linked to this asset.'); }
            self::claimSource($conn, $ctx, $tenant, $source, false);
            $removed = [];
            foreach (self::TABLES as $name => $table) {
                $host = $name === 'software' ? 'host_id' : 'asset_id';
                $del = $conn->prepare("DELETE FROM $table WHERE $host = ? AND source = ?");
                $del->execute([$assetId, self::storedSource($source)]);
                $removed[$name] = $del->rowCount();
            }
            $conn->prepare('DELETE FROM asset_inventory_sections WHERE inventory_source_id = ?')->execute([$binding['id']]);
            $conn->prepare('DELETE FROM asset_inventory_sources WHERE id = ?')->execute([$binding['id']]);
            $conn->prepare('INSERT INTO asset_history (asset_id, analyst_id, field_name, old_value, new_value, created_datetime)
                VALUES (?, ?, ?, ?, NULL, UTC_TIMESTAMP())')->execute([$assetId, $ctx->actorId, 'inventory_import',
                $source . ' (' . $binding['source_device_ref'] . ') unlinked']);
            $conn->commit();
            return ['asset_id' => $assetId, 'source' => $source, 'removed' => $removed];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            throw $e;
        }
    }

    /** Which sources report on this asset, and when each section was last observed and imported. */
    public static function provenance(PDO $conn, ActorContext $ctx, int $assetId): array
    {
        self::asset($conn, $ctx, $assetId, false);
        $stmt = $conn->prepare('SELECT s.id, s.source, s.source_device_ref, o.owner_analyst_id
            FROM asset_inventory_sources s
            LEFT JOIN asset_inventory_source_owners o ON o.tenant_id = s.tenant_id AND o.source = s.source
            WHERE s.asset_id = ? ORDER BY s.source');
        $stmt->execute([$assetId]);
        $sections = $conn->prepare('SELECT section_name, observed_at, imported_at, imported_by_id FROM asset_inventory_sections WHERE inventory_source_id = ? ORDER BY section_name');
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sections->execute([$row['id']]);
            $state = [];
            foreach ($sections->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $state[$s['section_name']] = ['observed_at' => str_replace(' ', 'T', $s['observed_at']) . 'Z',
                    'imported_at' => str_replace(' ', 'T', $s['imported_at']) . 'Z', 'imported_by_id' => (int)$s['imported_by_id']];
            }
            $rows[] = ['source' => $row['source'], 'source_device_ref' => $row['source_device_ref'],
                'owner_analyst_id' => $row['owner_analyst_id'] === null ? null : (int)$row['owner_analyst_id'], 'sections' => (object)$state];
        }
        return $rows;
    }

    // ======================================================================
    //  Internals
    // ======================================================================

    /** The report's envelope: anything wrong here refuses the whole report. */
    private static function envelope(array $in): array
    {
        self::keys($in, ['schema_version', 'source', 'source_device_ref', 'observed_at', 'sections']);
        if (($in['schema_version'] ?? null) !== 1) { self::invalid('Unsupported schema_version; expected integer 1.'); }
        $source = self::text($in, 'source', 48, true);
        if (!preg_match('/\A[a-z0-9][a-z0-9._-]*\z/D', $source)) { self::invalid('source must use lowercase ASCII letters, digits, dots, underscores or hyphens.'); }
        if (in_array($source, self::RESERVED, true)) { self::invalid("'$source' is a source name FreeITSM uses itself; choose your tool's own name."); }
        $deviceRef = self::text($in, 'source_device_ref', 128, true);
        $stamp = self::text($in, 'observed_at', 35, true);
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/D', $stamp)) {
            self::invalid('observed_at must be an RFC3339 timestamp with seconds and an explicit timezone.');
        }
        try { $date = new DateTimeImmutable($stamp); }
        catch (Exception $e) { self::invalid('Invalid observed_at.'); }
        $errors = DateTimeImmutable::getLastErrors();
        if (($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->getTimestamp() > time() + 300
            || (int)$date->format('Y') < 1000) { self::invalid('observed_at must be a valid observation, at most five minutes in the future.'); }
        $observed = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $sections = $in['sections'] ?? null;
        if (!is_array($sections) || !$sections || array_is_list($sections)) { self::invalid('Supply at least one supported section.'); }
        self::keys($sections, array_keys(self::TABLES));
        return [$source, $deviceRef, $observed, $sections];
    }

    /** One section, validated and put in a stable order (so its fingerprint ignores ordering). */
    private static function section(string $name, $section): array
    {
        if (!is_array($section)) { self::invalid("$name must be an object."); }
        self::keys($section, ['complete', 'items']);
        if (!isset($section['complete']) || !is_bool($section['complete']) || !isset($section['items'])
            || !is_array($section['items']) || !array_is_list($section['items']) || count($section['items']) > 10000) {
            self::invalid("$name requires boolean complete and an items list (at most 10000).");
        }
        $items = []; $seen = [];
        foreach ($section['items'] as $item) {
            if (!is_array($item)) { self::invalid("$name items must be objects."); }
            $row = self::item($name, $item);
            $key = $name === 'software' ? mb_strtolower($row['display_name']) . "\0" . mb_strtolower($row['publisher'] ?? '') : $row['external_id'];
            if (isset($seen[$key])) { self::invalid("Duplicate item identity in $name."); }
            $seen[$key] = true; $items[] = $row;
        }
        usort($items, fn($a, $b) => strcmp(json_encode($a), json_encode($b)));
        return ['complete' => $section['complete'], 'items' => $items];
    }

    /**
     * The analyst who owns this source name in this company - claimed by the first write.
     * A rotated key acts as the same analyst, so it keeps the source.
     *
     * TRAP: ownership is per (company, source), not per asset. Per asset, a second
     * integration could clear the first one's rows on every asset it had not reached yet
     * by sending the same name.
     */
    private static function claimSource(PDO $conn, ActorContext $ctx, int $tenant, string $source, bool $claim = true): void
    {
        $find = $conn->prepare('SELECT owner_analyst_id FROM asset_inventory_source_owners WHERE tenant_id = ? AND source = ? FOR UPDATE');
        $find->execute([$tenant, $source]);
        $owner = $find->fetchColumn();
        if ($owner === false && $claim) {
            try {
                $conn->prepare('INSERT INTO asset_inventory_source_owners (tenant_id, source, owner_analyst_id, created_datetime) VALUES (?, ?, ?, UTC_TIMESTAMP())')
                    ->execute([$tenant, $source, $ctx->actorId]);
                return;
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) { throw $e; }
                $find->execute([$tenant, $source]);   // claimed a moment ago by another request
                $owner = $find->fetchColumn();
            }
        }
        if ($owner !== false && (int)$owner !== $ctx->actorId) {
            throw new ServiceError('forbidden', 'source_owner', "The source '$source' belongs to another integration in this company. Use your tool's own source name.");
        }
    }

    /** Link (source, device ref) to this asset, or confirm the existing link. */
    private static function bind(PDO $conn, int $assetId, int $tenant, string $source, string $deviceRef): int
    {
        $find = $conn->prepare('SELECT id, source_device_ref FROM asset_inventory_sources WHERE asset_id = ? AND source = ?');
        $find->execute([$assetId, $source]);
        if ($binding = $find->fetch(PDO::FETCH_ASSOC)) {
            if ($binding['source_device_ref'] !== $deviceRef) {
                throw new ServiceError('conflict', 'device_ref_conflict', "This asset is linked to the device '{$binding['source_device_ref']}' of this source. Unlink it first to link another.");
            }
            return (int)$binding['id'];
        }
        $elsewhere = $conn->prepare('SELECT asset_id FROM asset_inventory_sources WHERE tenant_id = ? AND source = ? AND source_device_ref = ?');
        $elsewhere->execute([$tenant, $source, $deviceRef]);
        if ($other = $elsewhere->fetchColumn()) {
            throw new ServiceError('conflict', 'device_ref_conflict', "This device is already linked to asset $other. Unlink it there first (DELETE /assets/$other/inventory/$source).");
        }
        try {
            $conn->prepare('INSERT INTO asset_inventory_sources (asset_id, tenant_id, source, source_device_ref) VALUES (?, ?, ?, ?)')
                ->execute([$assetId, $tenant, $source, $deviceRef]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) { throw new ServiceError('conflict', 'device_ref_conflict', 'This device was linked to another asset a moment ago.'); }
            throw $e;
        }
        return (int)$conn->lastInsertId();
    }

    /**
     * Reconcile this source's rows for one section, row by row: matching rows updated in
     * place (their ids survive), new ones added and - complete sections only - the ones no
     * longer reported removed.
     *
     * @param array $discovered collects applications this call added to the catalogue
     * @return array{added:int, updated:int, removed:int}
     */
    private static function applySection(PDO $conn, int $assetId, string $stored, string $name, array $section, string $observed, array &$discovered): array
    {
        $table = self::TABLES[$name];
        $host = $name === 'software' ? 'host_id' : 'asset_id';
        $identity = $name === 'software' ? 'app_id' : 'external_id';
        $existing = $conn->prepare("SELECT id, $identity FROM $table WHERE $host = ? AND source = ?");
        $existing->execute([$assetId, $stored]);
        $have = [];
        foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $r) { $have[(string)$r[$identity]] = (int)$r['id']; }

        $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];
        $seen = [];
        $now = gmdate('Y-m-d H:i:s');
        foreach ($section['items'] as $item) {
            if ($name === 'software') {
                $appId = self::appId($conn, $item['display_name'], $item['publisher'], $discovered);
                if (isset($seen[(string)$appId])) { self::invalid('Two software items resolve to the same application in the catalogue.'); }
                unset($item['display_name'], $item['publisher']);
                $item = ['app_id' => $appId] + $item + ['last_seen' => $observed];
            }
            $key = (string)$item[$identity];
            $seen[$key] = true;
            $item += ['observed_at' => $observed, 'imported_at' => $now];
            if (isset($have[$key])) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($item)));
                $conn->prepare("UPDATE $table SET $set WHERE id = ?")->execute([...array_values($item), $have[$key]]);
                $counts['updated']++;
            } else {
                $row = [$host => $assetId, 'source' => $stored] + $item;
                $conn->prepare("INSERT INTO $table (" . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
                    ->execute(array_values($row));
                $counts['added']++;
            }
        }
        if ($section['complete']) {
            $gone = array_diff_key($have, $seen);
            if ($gone) {
                $conn->prepare("DELETE FROM $table WHERE id IN (" . implode(',', array_fill(0, count($gone), '?')) . ')')->execute(array_values($gone));
                $counts['removed'] = count($gone);
            }
        }
        return $counts;
    }

    /** The catalogue application for a name and publisher - the agent's lookup, so a curated manual row is adopted, never rewritten. */
    private static function appId(PDO $conn, string $displayName, ?string $publisher, array &$discovered): int
    {
        $find = $conn->prepare('SELECT id FROM software_inventory_apps WHERE display_name = ? AND (publisher IS NULL OR publisher = ?)
            ORDER BY (publisher <=> ?) DESC, id LIMIT 1');
        $find->execute([$displayName, $publisher, $publisher]);
        if ($app = $find->fetchColumn()) { return (int)$app; }
        try {
            $conn->prepare("INSERT INTO software_inventory_apps (display_name, publisher, source) VALUES (?, ?, 'api')")->execute([$displayName, $publisher]);
            $id = (int)$conn->lastInsertId();
            $discovered[] = ['id' => $id, 'name' => $displayName, 'publisher' => $publisher];
            return $id;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) { throw $e; }
            // Another ingest path added it since our read; a locking read sees it.
            $latest = $conn->prepare('SELECT id FROM software_inventory_apps WHERE display_name = ? AND publisher <=> ? FOR UPDATE');
            $latest->execute([$displayName, $publisher]);
            if ($app = $latest->fetchColumn()) { return (int)$app; }
            throw new ServiceError('conflict', 'application_conflict', 'Application identity conflicts with the catalogue.');
        }
    }

    private static function asset(PDO $conn, ActorContext $ctx, int $id, bool $lock): array
    {
        $stmt = $conn->prepare('SELECT id, tenant_id FROM assets WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $tenant = $row && $row['tenant_id'] !== null ? (int)$row['tenant_id'] : getDefaultTenantId($conn);
        if (!$row || ($ctx->companyScope !== null && !in_array($tenant, $ctx->companyScope, true))) {
            throw new ServiceError('not_found', 'not_found', 'Asset not found.');
        }
        return $row;
    }

    private static function item(string $name, array $in): array
    {
        $strings = match ($name) {
            'software' => ['display_name' => 512, 'publisher' => 512, 'display_version' => 100, 'install_date' => 50, 'uninstall_string' => 8192, 'install_location' => 8192, 'estimated_size' => 100],
            'disks' => ['external_id' => 128, 'drive' => 10, 'label' => 100, 'file_system' => 20],
            'network_adapters' => ['external_id' => 128, 'name' => 255, 'mac_address' => 17, 'ip_address' => 45, 'subnet_mask' => 45, 'gateway' => 45],
        };
        $numbers = $name === 'disks' ? ['size_bytes', 'free_bytes', 'used_percent'] : [];
        $booleans = match ($name) { 'software' => ['system_component'], 'network_adapters' => ['dhcp_enabled'], default => [] };
        self::keys($in, array_merge(array_keys($strings), $numbers, $booleans));
        $out = [];
        foreach ($strings as $field => $max) { $out[$field] = self::text($in, $field, $max, $field === ($name === 'software' ? 'display_name' : 'external_id')); }
        if ($name === 'software') { $out['display_name'] = trim($out['display_name']); if ($out['publisher'] === '') { $out['publisher'] = null; } }
        foreach ($booleans as $field) {
            $value = $in[$field] ?? ($field === 'system_component' ? false : null);
            if ($field === 'system_component' && array_key_exists($field, $in) && $in[$field] === null) { self::invalid('system_component must be boolean.'); }
            if ($value !== null && !is_bool($value)) { self::invalid("$field must be boolean."); }
            $out[$field] = $value === null ? null : (int)$value;
        }
        foreach ($numbers as $field) {
            $value = $in[$field] ?? null;
            if ($value !== null && (($field === 'used_percent' ? !is_int($value) && !is_float($value) : !is_int($value))
                || $value < 0 || ($field === 'used_percent' && $value > 100))) { self::invalid("Invalid non-negative $field."); }
            $out[$field] = $value;
        }
        if ($name === 'disks' && $out['size_bytes'] !== null && $out['free_bytes'] !== null && $out['free_bytes'] > $out['size_bytes']) { self::invalid('free_bytes exceeds size_bytes.'); }
        if ($name === 'disks' && $out['used_percent'] === null && $out['size_bytes'] > 0 && $out['free_bytes'] !== null) {
            $out['used_percent'] = round(100 * ($out['size_bytes'] - $out['free_bytes']) / $out['size_bytes'], 1);
        }
        if ($name === 'network_adapters') {
            foreach (['ip_address', 'subnet_mask', 'gateway'] as $key) { if ($out[$key] !== null && !filter_var($out[$key], FILTER_VALIDATE_IP)) { self::invalid("Invalid $key."); } }
            if ($out['mac_address'] !== null && !filter_var($out['mac_address'], FILTER_VALIDATE_MAC)) { self::invalid('Invalid mac_address.'); }
        }
        return $out;
    }

    private static function text(array $in, string $field, int $max, bool $required = false): ?string
    {
        $value = $in[$field] ?? null;
        if ($value === null && !$required) { return null; }
        if (!is_string($value) || mb_strlen($value) > $max || str_contains($value, "\0") || ($required && trim($value) === '')) { self::invalid("Invalid $field (maximum $max characters)."); }
        return $value;
    }

    private static function keys(array $in, array $allowed): void
    {
        if ($unknown = array_diff(array_keys($in), $allowed)) { self::invalid('Unknown fields: ' . implode(', ', $unknown)); }
    }

    private static function invalid(string $message): never
    {
        throw new ServiceError('validation', 'invalid_field', $message);
    }
}
