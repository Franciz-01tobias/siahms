<?php
/**
 * API Endpoint: save a standard-change template's working draft, and optionally submit it
 * for approval as a new version (#180 §8).
 *
 *   POST { id?, name, scope_use, scope_not, values: {change column: value}, open_fields: [],
 *          team_ids: [], submit: bool }
 *
 * Behind "Write standard-change templates". Saving the draft never touches an approved
 * version: the version in use stays in use until a submitted one is approved.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/rbac.php';
require_once '../../includes/changes/templates.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('changes');
requireModuleWriteJson('changes');
requireCapabilityJson(Cap::CHANGES_TEMPLATES);   // settings tab - see docs/design/rbac.md

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') throw new Exception(t('change-management.templates.name_required'));

    $values = is_array($in['values'] ?? null) ? $in['values'] : [];
    $cols = []; $args = [];
    foreach (ChangeTemplates::FIELDS as $col => [$tcol, $kind]) {
        $v = $values[$col] ?? null;
        $v = ($v === '' || $v === null) ? null : ($kind === 'short' ? (int)$v : (string)$v);
        $cols[] = $tcol;
        $args[] = $v;
    }
    $open = array_values(array_intersect(is_array($in['open_fields'] ?? null) ? $in['open_fields'] : [], array_keys(ChangeTemplates::FIELDS)));
    $teamIds = array_values(array_unique(array_filter(array_map('intval', is_array($in['team_ids'] ?? null) ? $in['team_ids'] : []))));

    $conn = connectToDatabase();
    $conn->beginTransaction();
    $id = (int)($in['id'] ?? 0);
    if ($id > 0) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
        $conn->prepare("UPDATE change_templates SET name = ?, scope_use = ?, scope_not = ?, open_fields = ?, $set WHERE id = ?")
             ->execute(array_merge([$name, trim((string)($in['scope_use'] ?? '')) ?: null, trim((string)($in['scope_not'] ?? '')) ?: null, implode(',', $open)], $args, [$id]));
    } else {
        $conn->prepare("INSERT INTO change_templates (name, scope_use, scope_not, open_fields, " . implode(', ', $cols) . ", created_by_id, created_datetime)
                        VALUES (?, ?, ?, ?, " . implode(', ', array_fill(0, count($cols), '?')) . ", ?, UTC_TIMESTAMP())")
             ->execute(array_merge([$name, trim((string)($in['scope_use'] ?? '')) ?: null, trim((string)($in['scope_not'] ?? '')) ?: null, implode(',', $open)], $args, [(int)$_SESSION['analyst_id']]));
        $id = (int)$conn->lastInsertId();
    }
    $conn->prepare("DELETE FROM change_template_teams WHERE template_id = ?")->execute([$id]);
    $ins = $conn->prepare("INSERT INTO change_template_teams (template_id, team_id) VALUES (?, ?)");
    foreach ($teamIds as $tid) $ins->execute([$id, $tid]);

    $versionId = null;
    if (!empty($in['submit'])) {
        $versionId = ChangeTemplates::submitVersion($conn, $id, (int)$_SESSION['analyst_id']);
    }
    $conn->commit();
    echo json_encode(['success' => true, 'id' => $id, 'version_id' => $versionId]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
