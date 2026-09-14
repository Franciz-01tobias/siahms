<?php
ini_set('display_errors', 0);
session_start();

$path_prefix = '../../';
$current_page = 'settings';

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/theme.php';
require_once __DIR__ . '/../../includes/timezone.php';

I18n::initFromSession();
Tz::init();
requireModuleAccess('checklists');

if (!isset($_SESSION['analyst_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$conn = connectToDatabase();

// Schema: database/freeitsm.sql + includes/db_verify_schema.php only.

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT IGNORE INTO checklist_categories (name) VALUES (?)");
            $stmt->execute([$name]);
        }
        header('Location: ' . BASE_URL . 'checklists/settings/?tab=categories');
        exit;
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM checklist_categories WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: ' . BASE_URL . 'checklists/settings/?tab=categories');
        exit;
    } elseif ($action === 'add_role') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT IGNORE INTO checklist_roles (name) VALUES (?)");
            $stmt->execute([$name]);
        }
        header('Location: ' . BASE_URL . 'checklists/settings/?tab=roles');
        exit;
    } elseif ($action === 'delete_role') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM checklist_roles WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: ' . BASE_URL . 'checklists/settings/?tab=roles');
        exit;
    } elseif ($action === 'save_sidebar_mode') {
        $mode = ($_POST['mode'] ?? '') === 'hover' ? 'hover' : 'always';
        $stmt = $conn->prepare("INSERT INTO user_preferences (analyst_id, preference_key, preference_value) VALUES (?, 'checklists_sidebar_mode', ?) ON DUPLICATE KEY UPDATE preference_value = ?");
        $stmt->execute([(int)$_SESSION['analyst_id'], $mode, $mode]);
        header('Location: ' . BASE_URL . 'checklists/settings/?tab=layout');
        exit;
    }
}

// Current sidebar preference
$sidebarMode = 'always';
try {
    $prefStmt = $conn->prepare("SELECT preference_value FROM user_preferences WHERE analyst_id = ? AND preference_key = 'checklists_sidebar_mode' LIMIT 1");
    $prefStmt->execute([(int)$_SESSION['analyst_id']]);
    $row = $prefStmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['preference_value'] === 'hover') {
        $sidebarMode = 'hover';
    }
} catch (Throwable $e) {}

// Categories, with how many templates use each. One grouped query rather than a
// COUNT per row - the original issued 1 + N queries to render a list that is
// usually short but need not be.
$categories = $conn->query(
    "SELECT c.id, c.name, COUNT(t.id) AS template_count
       FROM checklist_categories c
       LEFT JOIN checklist_templates t ON t.category = c.name
      GROUP BY c.id, c.name
      ORDER BY c.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Suggested roles, with how many template steps name each. Same shape, and the
// default eight are seeded by Database Verification, not from this render.
$roles = $conn->query(
    "SELECT r.id, r.name, COUNT(i.id) AS step_count
       FROM checklist_roles r
       LEFT JOIN checklist_template_items i ON i.suggested_role = r.name
      GROUP BY r.id, r.name
      ORDER BY r.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$activeTab = $_GET['tab'] ?? 'categories';
if (!in_array($activeTab, ['categories', 'roles', 'layout'])) {
    $activeTab = 'categories';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Checklist Settings</title>
    <link rel="stylesheet" href="../../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../../assets/css/inbox.css?v=62">
    <link rel="stylesheet" href="../../assets/css/mobile.css?v=133">
    <style>
        html, body { height: auto !important; min-height: 100vh; overflow-y: auto !important; overflow-x: hidden; margin: 0; padding: 0; background: var(--app-bg, #f8fafc); }
        .settings-container { max-width: 960px; margin: 30px auto 60px auto; padding: 0 20px; }
        .tab-bar { display: flex; gap: 8px; border-bottom: 1px solid var(--border-soft, #e2e8f0); margin-bottom: 24px; }
        .tab-btn { padding: 10px 18px; font-size: 14px; font-weight: 600; color: var(--text-muted, #64748b); border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab-btn.active { color: #0d9488; border-bottom-color: #0d9488; }
        .settings-card { background: var(--surface, #fff); border: 1px solid var(--border-soft, #e2e8f0); border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .table th, .table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border-soft, #e2e8f0); font-size: 13px; color: var(--text, #1e293b); }
        .table th { font-weight: 600; color: var(--text-muted, #64748b); background: var(--app-bg, #f8fafc); }
        .btn-teal { background: #0d9488; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-teal:hover { background: #0f766e; }
        .btn-del { color: #ef4444; border: 1px solid #fecaca; background: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .btn-del:hover { background: #fef2f2; }
    </style>
</head>
<body data-analyst-id="<?php echo $_SESSION['analyst_id'] ?? ''; ?>">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="settings-container">
        <div style="margin-bottom: 20px;">
            <h1 style="font-size: 22px; font-weight: 700; color: var(--text, #0f172a); margin: 0 0 6px 0;">Checklist Settings</h1>
            <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">Manage categories, suggested roles, layout preferences, and SOP defaults</p>
        </div>

        <div class="tab-bar">
            <button class="tab-btn <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" onclick="switchTab('categories', this)">Categories</button>
            <button class="tab-btn <?php echo $activeTab === 'roles' ? 'active' : ''; ?>" onclick="switchTab('roles', this)">Suggested Roles</button>
            <button class="tab-btn <?php echo $activeTab === 'layout' ? 'active' : ''; ?>" onclick="switchTab('layout', this)">Left Panel</button>
        </div>

        <!-- Tab 1: Categories -->
        <div id="tabCategories" class="tab-pane" style="display: <?php echo $activeTab === 'categories' ? 'block' : 'none'; ?>;">
            <div class="settings-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: var(--text, #0f172a);">Checklist Categories</h3>
                        <p style="margin: 0; font-size: 13px; color: var(--text-muted, #64748b);">Define departments and service categories for your templates</p>
                    </div>
                </div>

                <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="hidden" name="action" value="add_category">
                    <input type="text" name="name" required placeholder="New Category Name (e.g. Cloud Operations)" style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-soft, #cbd5e1); border-radius: 6px; font-size: 13px; background: var(--surface, #fff); color: var(--text, #1e293b);">
                    <button type="submit" class="btn-teal">+ Add Category</button>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Associated Templates</th>
                            <th style="width: 100px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo (int)($c['template_count'] ?? 0); ?> templates</td>
                                <td style="text-align: right;">
                                    <form method="POST" onsubmit="return confirm('Delete this category?')" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" class="btn-del">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Suggested Roles -->
        <div id="tabRoles" class="tab-pane" style="display: <?php echo $activeTab === 'roles' ? 'block' : 'none'; ?>;">
            <div class="settings-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: var(--text, #0f172a);">Suggested Roles</h3>
                        <p style="margin: 0; font-size: 13px; color: var(--text-muted, #64748b);">Define team roles and ownership positions available when creating SOP steps</p>
                    </div>
                </div>

                <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="hidden" name="action" value="add_role">
                    <input type="text" name="name" required placeholder="New Role Name (e.g. Incident Commander, QA Tester)" style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-soft, #cbd5e1); border-radius: 6px; font-size: 13px; background: var(--surface, #fff); color: var(--text, #1e293b);">
                    <button type="submit" class="btn-teal">+ Add Role</button>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Active Steps Using This Role</th>
                            <th style="width: 100px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted, #64748b);">No roles defined yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($roles as $r): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <span style="font-size: 11px; background: rgba(13,148,136,0.1); color: #0d9488; padding: 3px 8px; border-radius: 4px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; display: inline-block; vertical-align: -1px;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo htmlspecialchars($r['name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int)($r['step_count'] ?? 0); ?> SOP steps</td>
                                    <td style="text-align: right;">
                                        <form method="POST" onsubmit="return confirm('Delete this role?')" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_role">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn-del">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Left Panel (Hover Mode) -->
        <div id="tabLayout" class="tab-pane" style="display: <?php echo $activeTab === 'layout' ? 'block' : 'none'; ?>;">
            <div class="settings-card">
                <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: var(--text, #0f172a);">Left Panel Visibility</h3>
                <p style="margin: 0 0 20px 0; font-size: 13px; color: var(--text-muted, #64748b);">Choose how the checklist templates sidebar behaves on your account.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="save_sidebar_mode">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                            <input type="radio" name="mode" value="always" <?php echo $sidebarMode === 'always' ? 'checked' : ''; ?> style="margin-top: 3px;">
                            <div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--text, #0f172a);">Always visible</div>
                                <div style="font-size: 12px; color: var(--text-muted, #64748b);">Keep the 260px filter panel permanently pinned to the left of the page.</div>
                            </div>
                        </label>

                        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                            <input type="radio" name="mode" value="hover" <?php echo $sidebarMode === 'hover' ? 'checked' : ''; ?> style="margin-top: 3px;">
                            <div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--text, #0f172a);">Show on hover</div>
                                <div style="font-size: 12px; color: var(--text-muted, #64748b);">Collapse the sidebar into a thin 16px strip that slides open when your cursor approaches it.</div>
                            </div>
                        </label>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn-teal">Save Layout Preference</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            if (el) el.classList.add('active');
            document.getElementById('tabCategories').style.display = tab === 'categories' ? 'block' : 'none';
            document.getElementById('tabRoles').style.display = tab === 'roles' ? 'block' : 'none';
            document.getElementById('tabLayout').style.display = tab === 'layout' ? 'block' : 'none';
            if (history.replaceState) {
                history.replaceState(null, '', '?tab=' + tab);
            }
        }
    </script>
</body>
</html>
