<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/timezone.php';

I18n::initFromSession();
Tz::init();
requireModuleAccess('checklists');
$current_page = 'templates';
$path_prefix = '../';

$conn = connectToDatabase();

// Schema lives in database/freeitsm.sql + includes/db_verify_schema.php, like
// every other module's. It used to be created from here as well - see the wiki
// page Checklists-Module-House-Style, "the same table, created five ways".

// Fetch templates and items
$templates = $conn->query("SELECT * FROM checklist_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$allItems = $conn->query("SELECT * FROM checklist_template_items ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$itemsByTemplate = [];
foreach ($allItems as $it) {
    $itemsByTemplate[$it['template_id']][] = $it;
}

// Fetch categories
$categories = [];
try {
    $categories = $conn->query("SELECT * FROM checklist_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$catCounts = [];
foreach ($templates as $t) {
    $c = $t['category'] ?: 'General';
    $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
}

// Defined roles. The default eight are seeded by Database Verification on an
// empty table, the same way ticket resolution codes are - not created and
// inserted from inside a page render.
$definedRoles = $conn->query("SELECT id, name FROM checklist_roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Compute template counts per role
$roleCounts = [];
foreach ($templates as $t) {
    $tItems = $itemsByTemplate[$t['id']] ?? [];
    $rolesInThisTpl = [];
    foreach ($tItems as $it) {
        if (!empty($it['suggested_role'])) {
            $rolesInThisTpl[trim($it['suggested_role'])] = true;
        }
    }
    foreach (array_keys($rolesInThisTpl) as $rn) {
        $roleCounts[$rn] = ($roleCounts[$rn] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Checklists</title>
    <link rel="stylesheet" href="../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=70">
    <link rel="stylesheet" href="../assets/css/mobile.css?v=139">
    <style>
        body { margin: 0; padding: 0; background: var(--app-bg, #f8fafc); }
        .chk-layout { display: flex; height: calc(100vh - 48px); width: 100%; overflow: hidden; }
        .chk-sidebar { width: 260px; background: var(--surface, #fff); border-right: 1px solid var(--border-soft, #e2e8f0); padding: 20px; overflow-y: auto; flex-shrink: 0; display: flex; flex-direction: column; gap: 20px; }
        .chk-main { flex: 1; overflow-y: auto; padding: 28px 36px; }
        .chk-btn-primary { background: #0d9488; color: #fff; border: none; border-radius: 6px; padding: 10px 16px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; }
        .chk-btn-primary:hover { background: #0f766e; }
        .chk-search { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--border-soft, #cbd5e1); border-radius: 6px; font-size: 13px; background: var(--app-bg, #f8fafc); color: var(--text, #1e293b); }
        .chk-filter-link { display: flex; justify-content: space-between; align-items: center; padding: 7px 10px; border-radius: 6px; color: var(--text, #334155); text-decoration: none; font-size: 13px; font-weight: 500; cursor: pointer; }
        .chk-filter-link:hover, .chk-filter-link.active { background: rgba(13, 148, 136, 0.1); color: #0d9488; font-weight: 600; }
        .chk-filter-count { font-size: 11px; background: var(--border-soft, #e2e8f0); color: var(--text-muted, #64748b); padding: 1px 6px; border-radius: 10px; }
        .chk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        .chk-card { background: var(--surface, #fff); border: 1px solid var(--border-soft, #e2e8f0); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.04); transition: transform 0.15s, box-shadow 0.15s; }
        .chk-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .chk-pill { font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .chk-pill-ticket { background: #e0f2fe; color: #0284c7; }
        .chk-pill-task { background: #dcfce7; color: #16a34a; }
        .chk-pill-both { background: #f3e8ff; color: #9333ea; }
        .chk-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .chk-modal-dialog { background: var(--surface, #fff); width: 100%; max-width: 680px; border-radius: 10px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); }
        .chk-modal-header { padding: 18px 24px; border-bottom: 1px solid var(--border-soft, #e2e8f0); display: flex; justify-content: space-between; align-items: center; }
        .chk-modal-body { padding: 20px 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
        .chk-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-soft, #e2e8f0); display: flex; justify-content: flex-end; gap: 10px; background: var(--app-bg, #f8fafc); }
        .chk-input-group { display: flex; flex-direction: column; gap: 6px; }
        .chk-input-group label { font-size: 12px; font-weight: 600; color: var(--text-muted, #64748b); text-transform: uppercase; }
        .chk-input { padding: 8px 12px; border: 1px solid var(--border-soft, #cbd5e1); border-radius: 6px; font-size: 13px; color: var(--text, #1e293b); background: var(--surface, #fff); }
        .chk-item-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
    </style>
</head>
<body data-analyst-id="<?php echo $_SESSION['analyst_id'] ?? ''; ?>">
    <?php include 'includes/header.php'; ?>

    <div class="chk-layout">
        <!-- Sidebar -->
        <aside class="chk-sidebar">
            <button class="chk-btn-primary" onclick="openTemplateModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                New template
            </button>

            <div>
                <input type="text" class="chk-search" id="chkSearchInput" placeholder="Search templates..." oninput="filterTemplates()">
            </div>

            <!-- Scope Filter -->
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted, #94a3b8); text-transform: uppercase; margin-bottom: 4px;">Scope</div>
                <a class="chk-filter-link active scope-link" onclick="setScopeFilter('all', this)">All scopes <span class="chk-filter-count"><?php echo count($templates); ?></span></a>
                <a class="chk-filter-link scope-link" onclick="setScopeFilter('ticket', this)">Ticket</a>
                <a class="chk-filter-link scope-link" onclick="setScopeFilter('task', this)">Task</a>
            </div>

            <!-- Category Quick-Filter -->
            <div style="display: flex; flex-direction: column; gap: 4px; border-top: 1px solid var(--border-soft, #e2e8f0); padding-top: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--text-muted, #94a3b8); text-transform: uppercase;">Categories</span>
                    <a href="settings/?tab=categories" style="font-size: 11px; color: #0d9488; text-decoration: none; font-weight: 600;">Manage</a>
                </div>
                <a class="chk-filter-link active cat-link" onclick="setCategoryFilter('all', this)">All categories</a>
                <?php foreach ($categories as $cat): ?>
                    <?php $cName = $cat['name']; $cCount = $catCounts[$cName] ?? 0; ?>
                    <a class="chk-filter-link cat-link" onclick="setCategoryFilter('<?php echo htmlspecialchars(strtolower($cName)); ?>', this)">
                        <span><?php echo htmlspecialchars($cName); ?></span>
                        <span class="chk-filter-count"><?php echo $cCount; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Suggested Roles Quick-Filter -->
            <div style="display: flex; flex-direction: column; gap: 4px; border-top: 1px solid var(--border-soft, #e2e8f0); padding-top: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--text-muted, #94a3b8); text-transform: uppercase;">Suggested Roles</span>
                    <a href="settings/?tab=roles" style="font-size: 11px; color: #0d9488; text-decoration: none; font-weight: 600;">Manage</a>
                </div>
                <a class="chk-filter-link active role-link" onclick="setRoleFilter('all', this)">All roles</a>
                <?php foreach ($definedRoles as $r): ?>
                    <?php $rName = $r['name']; $rCount = $roleCounts[$rName] ?? 0; ?>
                    <a class="chk-filter-link role-link" onclick="setRoleFilter('<?php echo htmlspecialchars(strtolower($rName)); ?>', this)">
                        <span><?php echo htmlspecialchars($rName); ?></span>
                        <span class="chk-filter-count"><?php echo $rCount; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="chk-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 style="font-size: 22px; font-weight: 700; margin: 0 0 4px 0; color: var(--text, #0f172a);">Checklist and SOP templates</h1>
                    <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">Multi-use Standard Operating Procedures with task-level role assignments</p>
                </div>
            </div>

            <div class="chk-grid" id="chkCardsGrid">
                <?php foreach ($templates as $t): ?>
                    <?php
                        $items = $itemsByTemplate[$t['id']] ?? [];
                        $badgeClass = 'chk-pill-' . htmlspecialchars($t['scope']);
                        $badgeLabel = $t['scope'] === 'ticket' ? 'Ticket' : ($t['scope'] === 'task' ? 'Task' : 'Both');

                        $tplRoles = [];
                        foreach ($items as $it) {
                            if (!empty($it['suggested_role'])) {
                                $tplRoles[] = strtolower(trim($it['suggested_role']));
                            }
                        }
                        $tplRolesStr = implode('|', array_unique($tplRoles));
                    ?>
                                        <div class="chk-card"
                         data-scope="<?php echo htmlspecialchars($t['scope']); ?>"
                         data-category="<?php echo htmlspecialchars(strtolower($t['category'] ?: 'general')); ?>"
                         data-roles="<?php echo htmlspecialchars($tplRolesStr); ?>"
                         data-title="<?php echo htmlspecialchars(strtolower($t['title'])); ?>"
                         data-keywords="<?php echo htmlspecialchars(strtolower($t['keywords'] ?? '')); ?>"
                         data-desc="<?php echo htmlspecialchars(strtolower($t['description'] ?? '')); ?>">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <h3 style="font-size: 15px; font-weight: 600; margin: 0; color: var(--text, #0f172a);"><?php echo htmlspecialchars($t['title']); ?></h3>
                                <span class="chk-pill <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <span style="font-size: 12px; color: #0d9488; font-weight: 600; background: rgba(13,148,136,0.1); padding: 2px 6px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($t['category'] ?: 'General'); ?>
                                </span>
                            </div>

                            <p style="font-size: 12px; color: var(--text-muted, #64748b); margin: 0 0 14px 0; line-height: 1.5;"><?php echo htmlspecialchars($t['description'] ?: 'No description provided.'); ?></p>

                            <!-- Task-Level Steps with Assigned Role / Owner -->
                            <div style="border-top: 1px solid var(--border-soft, #e2e8f0); padding-top: 10px; font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                                <?php if (empty($items)): ?>
                                    <span style="color: var(--text-muted, #94a3b8); font-style: italic;">No checklist steps defined</span>
                                <?php else: ?>
                                    <?php foreach (array_slice($items, 0, 4) as $it): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                            <div style="display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <span style="color: <?php echo $it['is_mandatory'] ? '#0d9488' : '#94a3b8'; ?>;">✓</span>
                                                <span style="color: var(--text, #334155); overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($it['title']); ?></span>
                                                <?php if ($it['is_mandatory']): ?>
                                                    <span style="font-size: 10px; color: #ef4444; font-weight: bold;">*</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($it['suggested_role'])): ?>
                                                <span style="font-size: 10px; background: rgba(13,148,136,0.1); color: #0d9488; font-weight: 600; padding: 1px 6px; border-radius: 4px; white-space: nowrap;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 3px; display: inline-block; vertical-align: -1px;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo htmlspecialchars($it['suggested_role']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 4): ?>
                                        <div style="font-size: 11px; color: var(--text-muted, #94a3b8); margin-top: 2px;">+ <?php echo count($items) - 4; ?> more steps...</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div style="border-top: 1px solid var(--border-soft, #e2e8f0); margin-top: 16px; padding-top: 12px; display: flex; justify-content: flex-end; gap: 8px;">
                            <button onclick="editTemplate(<?php echo (int)$t['id']; ?>)" style="padding: 5px 12px; font-size: 12px; border: 1px solid var(--border-soft, #cbd5e1); border-radius: 4px; background: var(--surface, #fff); color: var(--text, #334155); cursor: pointer; font-weight: 500;">
                                Edit
                            </button>
                            <button onclick="deleteTemplate(<?php echo (int)$t['id']; ?>)" style="padding: 5px 12px; font-size: 12px; border: 1px solid #fecaca; border-radius: 4px; background: #fff; color: #ef4444; cursor: pointer; font-weight: 500;">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Create / Edit Template Modal -->
    <div class="chk-modal-backdrop" id="chkModal">
        <div class="chk-modal-dialog">
            <div class="chk-modal-header">
                <h3 id="chkModalTitle" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text, #0f172a);">New template</h3>
                <button type="button" onclick="closeTemplateModal()" style="border: none; background: none; font-size: 20px; cursor: pointer; color: var(--text-muted, #64748b);">&times;</button>
            </div>
            <div class="chk-modal-body">
                <input type="hidden" id="chkTemplateId" value="0">

                <div class="chk-input-group">
                    <label>Template Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" class="chk-input" id="chkInputTitle" placeholder="e.g. New Employee Workstation Setup">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="chk-input-group">
                        <label>Category</label>
                        <input type="text" class="chk-input" id="chkInputCategory" list="categoriesList" placeholder="e.g. Network, HR, General">
                        <datalist id="categoriesList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="chk-input-group">
                        <label>Scope</label>
                        <select class="chk-input" id="chkInputScope">
                            <option value="both">Both (Tickets &amp; Tasks)</option>
                            <option value="ticket">Ticket Only</option>
                            <option value="task">Task Only</option>
                        </select>
                    </div>
                </div>

                <div class="chk-input-group">
                    <label>Description</label>
                    <textarea class="chk-input" id="chkInputDesc" rows="2" placeholder="Brief explanation of when and why to apply this checklist..."></textarea>
                </div>

                <div class="chk-input-group">
                    <label>Keywords / Tags (Comma-Separated)</label>
                    <input type="text" class="chk-input" id="chkInputKeywords" placeholder="e.g. vpn, cisco, anyconnect, remote access, token">
                    <small style="font-size: 11px; color: var(--text-muted, #64748b);">Used for fast searching in modals and smart suggestions on tickets.</small>
                </div>

                <!-- Template Steps -->
                <div class="chk-input-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label>Checklist Steps &amp; Role Assignments</label>
                        <button type="button" onclick="addItemRow()" style="font-size: 12px; color: #0d9488; font-weight: 600; border: none; background: none; cursor: pointer;">Add step</button>
                    </div>
                    <div id="chkItemsContainer" style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Dynamically populated rows -->
                    </div>
                </div>
            </div>
            <div class="chk-modal-footer">
                <button type="button" onclick="closeTemplateModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-soft, #cbd5e1); background: var(--surface, #fff); color: var(--text, #334155); cursor: pointer; font-weight: 500; font-size: 13px;">
                    Cancel
                </button>
                <button type="button" onclick="saveTemplate()" style="padding: 8px 18px; border-radius: 6px; border: none; background: #0d9488; color: #fff; cursor: pointer; font-weight: 600; font-size: 13px;">
                    Save
                </button>
            </div>
        </div>
    </div>

    <script src="search_scoring.js?v=1"></script>
<script>
        const DEFINED_ROLES = <?php echo json_encode(array_column($definedRoles, 'name')); ?>;
        let currentScopeFilter = 'all';
        let currentCatFilter = 'all';
        let currentRoleFilter = 'all';

        function setScopeFilter(scope, el) {
            currentScopeFilter = scope;
            document.querySelectorAll('.scope-link').forEach(a => a.classList.remove('active'));
            el.classList.add('active');
            filterTemplates();
        }

        function setCategoryFilter(cat, el) {
            currentCatFilter = cat;
            document.querySelectorAll('.cat-link').forEach(a => a.classList.remove('active'));
            el.classList.add('active');
            filterTemplates();
        }

        function setRoleFilter(role, el) {
            currentRoleFilter = role;
            document.querySelectorAll('.role-link').forEach(a => a.classList.remove('active'));
            el.classList.add('active');
            filterTemplates();
        }


        function filterTemplates() {
            const query = (document.getElementById('chkSearchInput').value || '').trim();
            const grid = document.getElementById('chkCardsGrid');
            const cards = Array.from(document.querySelectorAll('.chk-card'));

            const cardScores = [];
            cards.forEach(card => {
                const cardScope = card.getAttribute('data-scope');
                const cardCat = card.getAttribute('data-category') || '';
                const cardRoles = (card.getAttribute('data-roles') || '').split('|').filter(Boolean);

                const item = {
                    title: card.getAttribute('data-title') || '',
                    category: cardCat,
                    keywords: card.getAttribute('data-keywords') || '',
                    description: card.getAttribute('data-desc') || ''
                };

                const matchScope = (currentScopeFilter === 'all') || (cardScope === currentScopeFilter) || (cardScope === 'both');
                const matchCat = (currentCatFilter === 'all') || (cardCat === currentCatFilter);
                const matchRole = (currentRoleFilter === 'all') || cardRoles.includes(currentRoleFilter);

                const res = scoreChecklistTemplate(item, query);
                const isVisible = (matchScope && matchCat && matchRole && res.matched);
                card.style.display = isVisible ? 'flex' : 'none';
                if (isVisible) {
                    cardScores.push({ card, score: res.score, title: item.title });
                }
            });

            // Re-order visible cards in DOM according to relevance score descending
            if (query && grid) {
                cardScores.sort((a, b) => b.score - a.score || a.title.localeCompare(b.title));
                cardScores.forEach(cs => grid.appendChild(cs.card));
            }
        }

        function openTemplateModal() {
            document.getElementById('chkModalTitle').innerText = 'New template';
            document.getElementById('chkTemplateId').value = '0';
            document.getElementById('chkInputTitle').value = '';
            document.getElementById('chkInputScope').value = 'both';
            document.getElementById('chkInputDesc').value = '';
            document.getElementById('chkInputKeywords').value = '';
            document.getElementById('chkItemsContainer').innerHTML = '';
            addItemRow('Verify user request and authorization', 'Tier 1 Support', true);
            addItemRow('Configure firewall and access credentials', 'Network Admin', true);
            addItemRow('Notify user of successful completion', 'Tier 1 Support', false);
            document.getElementById('chkModal').style.display = 'flex';
        }

        function closeTemplateModal() {
            document.getElementById('chkModal').style.display = 'none';
        }

        function addItemRow(title = '', role = '', isMandatory = true, requiresInput = false, placeholder = '') {
            const container = document.getElementById('chkItemsContainer');
            const row = document.createElement('div');
            row.className = 'chk-item-row';
            row.style.display = 'flex';
            row.style.flexDirection = 'column';
            row.style.gap = '6px';
            row.style.padding = '8px 10px';
            row.style.marginBottom = '8px';
            row.style.border = '1px solid var(--border, #e2e8f0)';
            row.style.borderRadius = '6px';
            row.style.background = 'var(--surface-hover, #f8fafc)';

            const trimmedRole = (role || '').trim();
            let roleOptionsHtml = '<option value="">-- Role (Optional) --</option>';
            let matched = false;
            DEFINED_ROLES.forEach(r => {
                const sel = (r.toLowerCase() === trimmedRole.toLowerCase()) ? 'selected' : '';
                if (sel) matched = true;
                roleOptionsHtml += `<option value="${escapeHtml(r)}" ${sel}>${escapeHtml(r)}</option>`;
            });
            if (trimmedRole && !matched) {
                roleOptionsHtml += `<option value="${escapeHtml(trimmedRole)}" selected>${escapeHtml(trimmedRole)}</option>`;
            }

            row.innerHTML = `
                <div style="display: flex; gap: 8px; align-items: center; width: 100%;">
                    <input type="text" class="chk-input item-title" style="flex: 2; padding: 6px 8px; font-size: 13px;" placeholder="Step action / title" value="${escapeHtml(title)}">
                    <select class="chk-input item-role" style="flex: 1.2; padding: 6px 8px; font-size: 12px; cursor: pointer;">
                        ${roleOptionsHtml}
                    </select>
                    <button type="button" onclick="this.closest('.chk-item-row').remove()" style="border: none; background: none; color: #ef4444; font-size: 18px; cursor: pointer; line-height: 1;" title="Remove step">&times;</button>
                </div>
                <div style="display: flex; gap: 14px; align-items: center; font-size: 11px; color: var(--text, #334155);">
                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; white-space: nowrap; user-select: none;">
                        <input type="checkbox" class="item-mand" ${isMandatory ? 'checked' : ''}> Mandatory Step
                    </label>
                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; white-space: nowrap; user-select: none;">
                        <input type="checkbox" class="item-req-input" ${requiresInput ? 'checked' : ''} onchange="togglePlaceholderInput(this)"> 📝 Require Note / Value
                    </label>
                    <div class="placeholder-wrap" style="flex: 1; display: ${requiresInput ? 'block' : 'none'};">
                        <input type="text" class="chk-input item-placeholder" style="width: 100%; box-sizing: border-box; padding: 4px 8px; font-size: 11px;" placeholder="Prompt / Placeholder (e.g. Asset tag, Serial #, or note)" value="${escapeHtml(placeholder)}">
                    </div>
                </div>
            `;
            container.appendChild(row);
        }

        function togglePlaceholderInput(chk) {
            const wrap = chk.closest('.chk-item-row').querySelector('.placeholder-wrap');
            if (wrap) {
                wrap.style.display = chk.checked ? 'block' : 'none';
                if (chk.checked) wrap.querySelector('input').focus();
            }
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        async function editTemplate(id) {
            try {
                const res = await fetch(`api.php?action=get&id=${id}`);
                const data = await res.json();
                if (!data.success) {
                    alert(data.error || 'Failed to load template');
                    return;
                }
                const t = data.template;
                document.getElementById('chkModalTitle').innerText = 'Edit template';
                document.getElementById('chkTemplateId').value = t.id;
                document.getElementById('chkInputTitle').value = t.title;
                document.getElementById('chkInputCategory').value = t.category || '';
                document.getElementById('chkInputScope').value = t.scope;
                document.getElementById('chkInputDesc').value = t.description || '';
                document.getElementById('chkInputKeywords').value = t.keywords || '';

                const container = document.getElementById('chkItemsContainer');
                container.innerHTML = '';
                if (t.items && t.items.length) {
                    t.items.forEach(it => addItemRow(it.title, it.suggested_role || '', it.is_mandatory == 1, it.requires_input == 1, it.input_placeholder || ''));
                } else {
                    addItemRow();
                }
                document.getElementById('chkModal').style.display = 'flex';
            } catch (e) {
                alert('Error loading template: ' + e.message);
            }
        }

        async function deleteTemplate(id) {
            if (!confirm('Are you sure you want to delete this checklist template?')) return;
            try {
                const res = await fetch('api.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to delete');
                }
            } catch (e) {
                alert('Delete error: ' + e.message);
            }
        }

        async function saveTemplate() {
            const id = parseInt(document.getElementById('chkTemplateId').value) || 0;
            const title = document.getElementById('chkInputTitle').value.trim();
            if (!title) {
                alert('Please enter a template title.');
                return;
            }
            const category = document.getElementById('chkInputCategory').value;
            const scope = document.getElementById('chkInputScope').value;
            const description = document.getElementById('chkInputDesc').value.trim();
            const keywords = document.getElementById('chkInputKeywords').value.trim();

            const itemRows = document.querySelectorAll('#chkItemsContainer .chk-item-row');
            const items = [];
            itemRows.forEach(row => {
                const itTitle = row.querySelector('.item-title').value.trim();
                const itRole = row.querySelector('.item-role').value.trim();
                const isMand = row.querySelector('.item-mand').checked ? 1 : 0;
                const reqInput = row.querySelector('.item-req-input').checked ? 1 : 0;
                const placeholder = (row.querySelector('.item-placeholder')?.value || '').trim();
                if (itTitle) {
                    items.push({
                        title: itTitle,
                        suggested_role: itRole,
                        is_mandatory: isMand,
                        requires_input: reqInput,
                        input_placeholder: placeholder
                    });
                }
            });

            try {
                const res = await fetch('api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, title, category, scope, description, keywords, items })
                });
                const data = await res.json();
                if (data.success) {
                    closeTemplateModal();
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save');
                }
            } catch (e) {
                alert('Save error: ' + e.message);
            }
        }
    </script>
</body>
</html>
