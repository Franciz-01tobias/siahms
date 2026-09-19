<?php
/**
 * Forms Module - View Submissions
 */
session_start();
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/branding.php';   // brandingLogoUrl() for the PDF export - NOT in functions.php
require_once '../includes/i18n.php';
require_once '../includes/theme.php';
require_once '../includes/timezone.php';
I18n::initFromSession();
Tz::init();

requireModuleAccess('forms');

$current_page = 'forms';
$path_prefix = '../';
$translationNamespaces = ['common', 'forms'];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <link rel="icon" type="image/svg+xml" href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('forms.subs.page_title')); ?></title>
    <script>window.translations = <?php echo json_encode(I18n::exportForJs($translationNamespaces), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="../assets/js/i18n.js?v=2"></script>
    <?php echo Tz::scriptTag(); ?>
    <script src="../assets/js/tz.js?v=5"></script>
    <!-- For FormLogic.formatDateValue() — date answers are naive local values and must
         NOT go through Tz, which would shift them into the reader's timezone. -->
    <script src="../assets/js/form-logic.js?v=2"></script>
    <script src="../assets/js/vendor/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/theme.css?v=23">
    <link rel="stylesheet" href="../assets/css/inbox.css?v=70">
    <style>
        /* Module accent (teal). */
        body { --accent: var(--forms-accent, #00897b); --accent-hover: var(--forms-accent-hover, #00695c); }

        .subs-container {
            flex: 1;
            overflow-y: auto;
            background-color: var(--app-bg, #f5f7fa);
        }

        /* Full width, edge to edge (Ed). ⚠️ Dropping the cap alone does
           NOTHING here: `.main-container` is `display: flex`, so this is a flex
           item and `margin: 0 auto` absorbs the free space - it would keep its
           gutters and look exactly as if the 1400px cap were still in place.
           The `margin: 0` is the half that actually does the work. */
        .subs-content {
            flex: 1 1 auto;
            min-width: 0;
            max-width: none;
            width: 100%;
            margin: 0;
            box-sizing: border-box;
            padding: 24px 32px 40px;
        }

        .subs-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .subs-toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .subs-toolbar-left h2 {
            margin: 0;
            font-size: 20px;
            color: var(--text, #333);
        }

        .subs-toolbar-left .sub-count {
            font-size: 13px;
            color: var(--text-dim, #888);
            background: var(--surface-hover, #f0f0f0);
            padding: 3px 10px;
            border-radius: 12px;
        }

        .subs-toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted, #666);
        }

        .filter-group input[type="date"] {
            padding: 6px 10px;
            border: 1px solid var(--border, #ddd);
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            background: var(--surface, #fff);
            color: var(--text, #333);
        }

        .filter-group input[type="date"]:focus {
            outline: none;
            border-color: var(--forms-accent, #00897b);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background-color 0.15s;
        }

        .btn-secondary { background: var(--surface-2, #f5f7fa); color: var(--text, #333); border: 1px solid var(--border, #ddd); }
        .btn-secondary:hover { background: var(--surface-hover, #eef0f2); }
        .btn-primary { background: var(--forms-accent, #00897b); color: white; }
        .btn-primary:hover { background: var(--forms-accent-hover, #00695c); }
        .btn-export { background: #1565c0; color: white; }
        .btn-export:hover { background: #0d47a1; }

        .subs-card {
            background: var(--surface, #fff);
            border-radius: 8px;
            box-shadow: 0 1px 4px var(--shadow, rgba(0,0,0,0.08));
            overflow: hidden;
        }

        .subs-table-wrap {
            overflow-x: auto;
        }

        .subs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .subs-table th {
            background: var(--surface-2, #f8f9fa);
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted, #555);
            border-bottom: 2px solid var(--border, #e8e8e8);
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        .subs-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-soft, #f0f0f0);
            color: var(--text, #333);
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .subs-table tr:hover td {
            background: #f8fbff;
        }
        /* Keep the pale light-mode hover as-is; give dark a glow-safe teal tint. */
        [data-theme-mode="dark"] .subs-table tr:hover td {
            background: var(--forms-accent-soft);
        }

        .subs-table tr {
            cursor: pointer;
        }

        .subs-table .cb-value {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            text-align: center;
            line-height: 18px;
            font-size: 11px;
            font-weight: 700;
        }

        .cb-yes { background: var(--success-bg, #e8f5e9); color: var(--success-text, #2e7d32); }
        .cb-no { background: var(--surface-hover, #f5f5f5); color: var(--text-faint, #999); }

        .subs-table .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--danger-text, #d32f2f);
            padding: 4px 6px;
            border-radius: 3px;
        }

        .subs-table .delete-btn:hover {
            background: var(--danger-bg, #ffebee);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-dim, #888);
        }

        .empty-state h3 {
            margin: 15px 0 8px;
            font-size: 16px;
            color: var(--text-muted, #666);
        }

        .empty-state p {
            margin: 0;
            font-size: 14px;
        }

        /* Detail modal */
        .detail-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .detail-overlay.open { display: flex; }

        .detail-box {
            background: var(--surface, #fff);
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 30px var(--shadow, rgba(0,0,0,0.2));
        }

        .detail-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-soft, #eee);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--surface, #fff);
            border-radius: 8px 8px 0 0;
        }

        .detail-header h3 {
            margin: 0;
            font-size: 16px;
            color: var(--text, #333);
        }

        .detail-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: var(--text-faint, #999);
            font-size: 20px;
            line-height: 1;
        }

        .detail-close:hover { color: var(--text, #333); }

        .detail-body {
            padding: 22px;
        }

        .detail-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: var(--text-dim, #888);
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-soft, #f0f0f0);
        }

        .detail-field {
            margin-bottom: 16px;
        }

        .detail-field-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dim, #888);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .detail-field-value {
            font-size: 14px;
            color: var(--text, #333);
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .detail-field-value.empty {
            color: var(--text-faint, #ccc);
            font-style: italic;
        }

        /* Confirm delete overlay */
        .confirm-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .confirm-overlay.open { display: flex; }

        .confirm-box {
            background: var(--surface, #fff);
            border-radius: 8px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 30px var(--shadow, rgba(0,0,0,0.2));
        }

        .confirm-box h3 { margin: 0 0 8px; font-size: 16px; color: var(--text, #333); }
        .confirm-box p { margin: 0 0 20px; font-size: 14px; color: var(--text-muted, #666); }

        .confirm-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-cancel { background: var(--surface-hover, #f5f5f5); color: var(--text, #333); border: 1px solid var(--border, #ddd); }
        .btn-danger { background: #d32f2f; color: white; }
        .btn-danger:hover { background: #b71c1c; }

        .detail-actions { display: flex; align-items: center; gap: 10px; }

        /* Matches .delete-btn's shape so the two read as one control group;
           only the hover colour differs, because one of them is destructive. */
        /* The selection bar. ⚠️ NOT `display:flex` with `[hidden]` - an element
           with a display rule of its own ignores the hidden attribute entirely,
           which is how a "hidden" toolbar ends up on screen. Hidden is the
           default state here and `.open` is what turns it on. */
        .subs-selbar {
            display: none;
            align-items: center;
            gap: 10px;
            margin: 0 0 12px;
            padding: 10px 14px;
            border-radius: 6px;
            background: var(--surface-alt, #f1f5f9);
            border: 1px solid var(--border, #e2e8f0);
        }
        .subs-selbar.open { display: flex; }
        .subs-selbar .sel-count { font-weight: 600; font-size: 14px; }
        .subs-selbar .sel-spacer { flex: 1; }

        /* The tick column stays narrow and does not travel when the table
           scrolls sideways - it is a control, not data. */
        .subs-table th.sel-col, .subs-table td.sel-col {
            width: 34px;
            padding-left: 10px;
            padding-right: 0;
            text-align: center;
        }
        .subs-table .sel-box { cursor: pointer; margin: 0; vertical-align: middle; }
        .row-pdf-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            margin-right: 2px;
            color: var(--text-muted, #94a3b8);
            border-radius: 4px;
            vertical-align: middle;
        }
        .row-pdf-btn:hover { color: var(--accent, #00897b); background: var(--surface-hover, #f1f5f9); }

        /* The approval block sits above the answers: on an audit trail the
           decision is the headline, not a footnote. */
        .detail-approval {
            border: 1px solid var(--border-soft, #e2e8f0);
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 16px;
            background: var(--surface-2, #f8fafc);
            font-size: 13px;
        }
        .detail-approval .da-row { display: flex; gap: 8px; margin-bottom: 4px; }
        .detail-approval .da-row:last-child { margin-bottom: 0; }
        .detail-approval .da-label { color: var(--text-muted, #64748b); min-width: 88px; }
        .da-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-weight: 600; font-size: 12px; }
        .da-approved { background: #dcfce7; color: #16a34a; }
        .da-rejected { background: #fee2e2; color: #dc2626; }
        .da-pending  { background: #fef3c7; color: #b45309; }

        @media print {
            .header, .subs-toolbar-right, .subs-table .delete-btn,
            .subs-table .row-pdf-btn, .subs-selbar, .sel-col,
            .detail-overlay, .confirm-overlay { display: none !important; }
            .subs-content { padding: 0; max-width: 100%; }
            .subs-card { box-shadow: none; }
            .subs-table td { max-width: none; white-space: normal; }
        }
    </style>
    <!-- Mobile layer. Linked AFTER this page's inline <style> on purpose: the
         mobile rules must win on equal specificity, and a link placed above it
         would silently lose to the desktop block below (the load-order trap). -->
    <link rel="stylesheet" href="../assets/css/mobile.css?v=151">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container subs-container">
        <div class="subs-content">
            <div class="subs-toolbar">
                <div class="subs-toolbar-left">
                    <a href="./" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <?php echo htmlspecialchars(t('forms.subs.back')); ?>
                    </a>
                    <h2 id="pageTitle"><?php echo htmlspecialchars(t('forms.subs.heading')); ?></h2>
                    <span class="sub-count" id="subCount"></span>
                </div>
                <div class="subs-toolbar-right">
                    <div class="filter-group">
                        <label><?php echo htmlspecialchars(t('forms.subs.from')); ?></label>
                        <input type="date" id="dateFrom" onchange="applyFilter()">
                    </div>
                    <div class="filter-group">
                        <label><?php echo htmlspecialchars(t('forms.subs.to')); ?></label>
                        <input type="date" id="dateTo" onchange="applyFilter()">
                    </div>
                    <button class="btn btn-secondary" onclick="clearFilter()"><?php echo htmlspecialchars(t('forms.subs.clear')); ?></button>
                    <button class="btn btn-export" onclick="exportCSV()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <?php echo htmlspecialchars(t('forms.subs.export_csv')); ?>
                    </button>
                </div>
            </div>

            <!-- Appears the moment something is ticked; `.open`, not the
                 hidden attribute, because this has a display rule. -->
            <div class="subs-selbar" id="selBar">
                <span class="sel-count" id="selCount"></span>
                <span class="sel-spacer"></span>
                <button class="btn btn-secondary" onclick="exportSelected('bundle')" title="<?php echo htmlspecialchars(t('forms.subs.sel_bundle_hint')); ?>"><?php echo htmlspecialchars(t('forms.subs.sel_bundle')); ?></button>
                <button class="btn btn-secondary" onclick="exportSelected('separate')" title="<?php echo htmlspecialchars(t('forms.subs.sel_separate_hint')); ?>"><?php echo htmlspecialchars(t('forms.subs.sel_separate')); ?></button>
                <button class="btn btn-secondary" onclick="clearSelection()"><?php echo htmlspecialchars(t('forms.subs.sel_clear')); ?></button>
            </div>

            <div class="subs-card">
                <div class="subs-table-wrap">
                    <div id="subsContent">
                        <div style="text-align:center;padding:40px;color:var(--text-dim, #888)"><?php echo htmlspecialchars(t('forms.subs.loading')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail modal -->
    <div class="detail-overlay" id="detailOverlay" onclick="if(event.target===this)closeDetail()">
        <div class="detail-box">
            <div class="detail-header">
                <h3><?php echo htmlspecialchars(t('forms.subs.detail_heading')); ?></h3>
                <div class="detail-actions">
                    <button class="btn btn-secondary" id="detailPdfBtn" onclick="exportSubmissionPdf()">
                        <?php echo htmlspecialchars(t('forms.subs.export_pdf')); ?>
                    </button>
                    <button class="detail-close" onclick="closeDetail()">&times;</button>
                </div>
            </div>
            <div class="detail-body" id="detailBody"></div>
        </div>
    </div>

    <!-- Confirm delete -->
    <div class="confirm-overlay" id="confirmOverlay" onclick="if(event.target===this)closeConfirm()">
        <div class="confirm-box">
            <h3><?php echo htmlspecialchars(t('forms.subs.confirm_title')); ?></h3>
            <p><?php echo htmlspecialchars(t('forms.subs.confirm_message')); ?></p>
            <div class="confirm-actions">
                <button class="btn btn-cancel" onclick="closeConfirm()"><?php echo htmlspecialchars(t('forms.subs.confirm_cancel')); ?></button>
                <button class="btn btn-danger" id="confirmDeleteBtn"><?php echo htmlspecialchars(t('forms.subs.confirm_delete')); ?></button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../api/forms/';
        let formData = null;
        let allSubmissions = [];
        let filteredSubmissions = [];

        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('id');
            if (id) {
                loadSubmissions(id);
            } else {
                document.getElementById('subsContent').innerHTML = '<p style="color:var(--danger-text, #c00);text-align:center;padding:20px">' + esc(window.t('forms.subs.no_id')) + '</p>';
            }
        });

        async function loadSubmissions(formId) {
            try {
                const res = await fetch(API_BASE + 'get_submissions.php?form_id=' + formId);
                const data = await res.json();

                if (data.success) {
                    formData = data.form;
                    formData.fields = data.fields;
                    allSubmissions = data.submissions;
                    filteredSubmissions = allSubmissions;

                    document.getElementById('pageTitle').textContent = window.t('forms.subs.heading_named', { title: formData.title });
                    document.title = window.t('forms.subs.page_title_named', { title: formData.title });

                    renderTable();
                } else {
                    document.getElementById('subsContent').innerHTML = '<p style="color:var(--danger-text, #c00);text-align:center;padding:20px">' + esc(data.error) + '</p>';
                }
            } catch (e) {
                console.error(e);
                document.getElementById('subsContent').innerHTML = '<p style="color:var(--danger-text, #c00);text-align:center;padding:20px">' + esc(window.t('forms.subs.load_failed')) + '</p>';
            }
        }

        function renderTable() {
            /* The ticks belong to the rows that were on screen, and this
               redraws them - a filter change, a delete, a reload. Exporting
               something you can no longer see is the worse surprise.
               🔴 At the TOP, before the empty-list branch returns: putting it
               at the foot meant the one redraw that most obviously invalidates
               a selection - filtering down to nothing - was the only one that
               never cleared it, and the bar went on offering to export rows
               that were no longer there. */
            clearSelection();
            const count = filteredSubmissions.length;
            document.getElementById('subCount').textContent = count !== 1
                ? window.t('forms.subs.count_plural', { n: count })
                : window.t('forms.subs.count', { n: count });

            if (count === 0) {
                document.getElementById('subsContent').innerHTML = `<div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    <h3>${esc(window.t('forms.subs.empty_title'))}</h3>
                    <p>${window.t('forms.subs.empty_body', { url: 'fill.php?id=' + formData.id })}</p>
                </div>`;
                return;
            }

            let html = '<table class="subs-table"><thead><tr>';
            html += `<th class="sel-col"><input type="checkbox" class="sel-box" id="selAll" onchange="toggleSelectAll(this.checked)" title="${escAttr(window.t('forms.subs.sel_all'))}" aria-label="${escAttr(window.t('forms.subs.sel_all'))}"></th>`;
            html += '<th>' + esc(window.t('forms.subs.col_num')) + '</th>';
            html += '<th>' + esc(window.t('forms.subs.col_submitted_by')) + '</th>';
            html += '<th>' + esc(window.t('forms.subs.col_date')) + '</th>';

            // Retired questions keep their column. The answers people gave them are
            // still here, and a heading that just disappeared would make those answers
            // look like they had never been given.
            formData.fields.forEach(f => {
                const retired = f.is_deleted == 1
                    ? ` <span class="col-retired" title="${escAttr(window.t('forms.subs.retired_hint'))}">${esc(window.t('forms.subs.retired'))}</span>` : '';
                html += `<th${f.is_deleted == 1 ? ' class="th-retired"' : ''}>${esc(f.label)}${retired}</th>`;
            });

            html += '<th></th>';
            html += '</tr></thead><tbody>';

            filteredSubmissions.forEach((sub, idx) => {
                html += `<tr onclick="showDetail(${idx})">`;
                /* stopPropagation on the click as well as the change: without it
                   ticking a row opens that row's detail panel over the list. */
                html += `<td class="sel-col"><input type="checkbox" class="sel-box" data-sub-id="${sub.id}" onclick="event.stopPropagation()" onchange="toggleSelect(${sub.id}, this.checked)" aria-label="${escAttr(window.t('forms.subs.sel_row'))}"></td>`;
                html += `<td>${count - idx}</td>`;
                html += `<td>${esc(sub.submitted_by || window.t('forms.subs.unknown_user'))}</td>`;
                html += `<td>${esc(formatDate(sub.submitted_date))}</td>`;

                formData.fields.forEach(f => {
                    const val = sub.data[f.id] ?? '';
                    if (f.field_type === 'checkbox') {
                        const checked = val === '1';
                        html += `<td><span class="cb-value ${checked ? 'cb-yes' : 'cb-no'}">${checked ? '&#10003;' : '&#10007;'}</span></td>`;
                    } else if (f.field_type === 'checkboxes') {
                        // Stored as a JSON-encoded array; show as a
                        // comma-joined list. Title attr keeps the raw
                        // value visible on hover for long lists.
                        const list = decodeMultiValue(val);
                        const display = list.length ? list.join(', ') : '';
                        html += `<td title="${esc(display)}">${esc(display) || '<span style="color:var(--text-faint, #ccc)">—</span>'}</td>`;
                    } else if (f.field_type === 'lookup') {
                        // Show the label the person actually chose. The id stays
                        // in the stored JSON for anything that wants the record.
                        const lbl = FormLogic.lookupLabel(val);
                        html += `<td title="${esc(lbl)}">${esc(lbl) || '<span style="color:var(--text-faint, #ccc)">—</span>'}</td>`;
                    } else if (f.field_type === 'datetime') {
                        const shown = FormLogic.formatDateValue(val);
                        html += `<td title="${esc(shown)}">${esc(shown) || '<span style="color:var(--text-faint, #ccc)">—</span>'}</td>`;
                    } else {
                        html += `<td title="${esc(val)}">${esc(val) || '<span style="color:var(--text-faint, #ccc)">—</span>'}</td>`;
                    }
                });

                /* Export sits before Delete: the harmless action first, and the
                   destructive one furthest from where the eye lands. stopPropagation
                   because the row itself opens the panel. */
                html += `<td><button class="row-pdf-btn" onclick="event.stopPropagation();exportSubmissionPdf(${idx})" title="${escAttr(window.t('forms.subs.export_pdf'))}" aria-label="${escAttr(window.t('forms.subs.export_pdf'))}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </button><button class="delete-btn" onclick="event.stopPropagation();confirmDelete(${sub.id})" title="${escAttr(window.t('forms.subs.delete'))}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            document.getElementById('subsContent').innerHTML = html;
            refreshSelBar();
        }

        /* Which submission the detail panel is showing, so the PDF button knows
           what to export. Set here and nowhere else. */
        let currentDetailIndex = -1;

        function showDetail(idx) {
            const sub = filteredSubmissions[idx];
            if (!sub) return;
            currentDetailIndex = idx;

            let html = `<div class="detail-meta">
                <span><strong>${esc(window.t('forms.subs.detail_submitted_by'))}</strong> ${esc(sub.submitted_by || window.t('forms.subs.unknown_user'))}</span>
                <span><strong>${esc(window.t('forms.subs.detail_date'))}</strong> ${esc(formatDate(sub.submitted_date))}</span>
            </div>`;

            /* The approval trail. It has been in `form_submissions` since the
               catalogue approvals work and this panel never showed it, so the
               screen said less than the database held. */
            const ap = approvalParts(sub);
            html += `<div class="detail-approval">
                <div class="da-row">
                    <span class="da-label">${esc(window.t('forms.subs.detail_approval'))}</span>
                    <span class="da-pill ${ap.cls}">${esc(ap.label)}</span>
                </div>`;
            if (sub.approval_decided_by) {
                html += `<div class="da-row">
                    <span class="da-label">${esc(window.t('forms.approval.approver'))}</span>
                    <span>${esc(sub.approval_decided_by)}${sub.approval_decided_datetime ? ' &middot; ' + esc(formatDate(sub.approval_decided_datetime)) : ''}</span>
                </div>`;
            }
            if (sub.approval_comment) {
                html += `<div class="da-row">
                    <span class="da-label">${esc(window.t('forms.subs.approval_comment'))}</span>
                    <span>${esc(sub.approval_comment)}</span>
                </div>`;
            }
            html += '</div>';

            formData.fields.forEach(f => {
                const p = fieldValueParts(f, sub.data[f.id]);
                const retired = f.is_deleted == 1
                    ? ` <span class="col-retired" title="${escAttr(window.t('forms.subs.retired_hint'))}">${esc(window.t('forms.subs.retired'))}</span>` : '';
                html += `<div class="detail-field">
                    <div class="detail-field-label">${esc(f.label)}${retired}</div>`;

                if (p.kind === 'bool') {
                    html += `<div class="detail-field-value"><span class="cb-value ${p.checked ? 'cb-yes' : 'cb-no'}">${p.checked ? '&#10003;' : '&#10007;'}</span> ${esc(p.text)}</div>`;
                } else if (p.kind === 'list') {
                    html += p.empty
                        ? `<div class="detail-field-value empty">${esc(window.t('forms.subs.no_response'))}</div>`
                        : `<div class="detail-field-value"><ul style="margin:0; padding-left: 18px;">${p.list.map(v => `<li>${esc(v)}</li>`).join('')}</ul></div>`;
                } else {
                    html += `<div class="detail-field-value ${p.empty ? 'empty' : ''}">${p.empty ? esc(window.t('forms.subs.no_response')) : esc(p.text)}</div>`;
                }

                html += '</div>';
            });

            document.getElementById('detailBody').innerHTML = html;
            document.getElementById('detailOverlay').classList.add('open');
        }

        /* ------------------------------------------------------------------
           ONE formatter, two renderers.

           The detail panel and the PDF must never disagree about what a
           submission says. So the decision about how a stored value READS
           lives here, and both callers format the result their own way -
           HTML for the panel, plain text for the document. Duplicating the
           if/else would guarantee drift the first time a field type is added.

           Returns { kind: 'bool' | 'list' | 'text', text, list, empty }.
           ------------------------------------------------------------------ */
        function fieldValueParts(f, raw) {
            const val = raw ?? '';
            if (f.field_type === 'checkbox') {
                const checked = val === '1';
                return {
                    kind: 'bool',
                    checked: checked,
                    text: checked ? window.t('forms.subs.yes') : window.t('forms.subs.no'),
                    empty: false
                };
            }
            if (f.field_type === 'checkboxes') {
                const list = decodeMultiValue(val);
                return { kind: 'list', list: list, text: list.join(', '), empty: list.length === 0 };
            }
            if (f.field_type === 'lookup') {
                /* The label the person actually chose. The id stays in the
                   stored JSON for anything that wants the record. */
                const lbl = FormLogic.lookupLabel(val) || '';
                return { kind: 'text', text: lbl, empty: !lbl };
            }
            if (f.field_type === 'datetime') {
                const shown = FormLogic.formatDateValue(val) || '';
                return { kind: 'text', text: shown, empty: !shown };
            }
            return { kind: 'text', text: String(val), empty: !val };
        }

        /* The approval outcome, as words and a class. `not_required` is not a
           non-answer - it means nobody had to approve this, which is worth
           saying on a record rather than leaving blank. */
        function approvalParts(sub) {
            const st = (sub.approval_status || 'not_required');
            if (st === 'approved') return { st, cls: 'da-approved', label: window.t('forms.approval.status_approved') };
            if (st === 'rejected') return { st, cls: 'da-rejected', label: window.t('forms.approval.status_rejected') };
            if (st === 'pending')  return { st, cls: 'da-pending',  label: window.t('forms.subs.approval_awaiting') };
            return { st, cls: '', label: window.t('forms.subs.approval_none') };
        }

        /* ------------------------------------------------------------------
           Export the open submission as a PDF.

           Built like the Knowledge export - a real document with selectable,
           searchable text rather than an image of the screen - so an auditor
           can search it and copy out of it.
           ------------------------------------------------------------------ */
        /* "Mobile phone request - Ed Mozley - 19.09.2026.pdf"
           The date is the operator's OWN format, so a US install files it as
           09.19.2026 without this knowing anything about locales.

           🔑 fmtDate, not fmtNaiveDate: `submitted_date` is a real instant
           stamped by the server and converts into the viewer's zone. A rota day
           is the other kind and must NOT be converted. Both helpers exist and
           choosing wrongly is silently wrong for everyone outside UTC. */
        /* A date template is not a filename. DD/MM/YYYY is a perfectly good
           preference and a slash is illegal in a filename everywhere, so
           separators become dots instead of vanishing. Windows also refuses
           a trailing dot or space. Shared by the single export and the bundle -
           one of them getting a fix the other missed is exactly the drift this
           file has been avoiding all along. */
        function cleanForFileName(v) {
            return String(v || '')
                .replace(/[\/\\]/g, '.')
                .replace(/[<>:"|?*\x00-\x1f]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/[. ]+$/, '');
        }
        function submissionFileName(sub) {
            /* A date template is not a filename. DD/MM/YYYY is a perfectly good
               preference and a slash is illegal in a filename everywhere, so
               separators become dots instead of vanishing. Windows also refuses
               a trailing dot or space. */
            const clean = cleanForFileName;

            const when = (typeof window.fmtDate === 'function')
                ? window.fmtDate(sub.submitted_date) : '';

            const parts = [
                clean(formData.title || 'Submission'),
                clean(sub.submitted_by || window.t('forms.subs.unknown_user')),
                clean(when)
            ].filter(Boolean);

            /* Long form titles are common and 255 is the practical filename
               limit, so leave room for the extension and any "(1)" a browser
               adds when two files collide. */
            let name = parts.join(' - ');
            if (name.length > 180) name = name.slice(0, 180).replace(/[. ]+$/, '');
            return name + '.pdf';
        }
        /* `idx` is passed by the row icon; the panel's own button passes nothing
           and gets whatever is open. One function, two entry points - a second
           copy for the list would drift from this one. */
        /* The branding image, fetched once per page load however many documents
           are made. Resolves to null if it cannot be loaded - a missing logo
           must never cost somebody their record. */
        let brandLogoPromise = null;
        function loadBrandLogo() {
            if (brandLogoPromise) return brandLogoPromise;
            brandLogoPromise = new Promise((resolve) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = () => resolve(null);
                img.src = <?php echo json_encode(brandingLogoUrl()); ?>;
            });
            return brandLogoPromise;
        }

        /* Draw ONE submission into an existing document, starting at `y`, and
           return the y it finished at. Everything that decides what a record
           LOOKS like lives here and nowhere else, so the single export and the
           bundle can never disagree about what a record contains. */
        function drawSubmission(doc, sub, idx, logo) {
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();
            const margin = 15;
            const contentW = pageW - margin * 2;
            let y = margin;

            /* Start a new page when the next block would run off the foot.
               Checked BEFORE writing each block rather than after, or the
               last line of a long answer lands in the margin. */
            const room = (needed) => {
                if (y + needed > pageH - margin) { doc.addPage(); y = margin; }
            };

            // --- Logo: the operator's own, not a hard-coded file -------------
            if (logo) {
                const maxH = 12;
                /* alias + compression, both deliberate: the alias means a bundle
                   of many submissions embeds the logo ONCE rather than per page,
                   and FAST deflates it - an uncompressed branding PNG took a
                   one-page document to 1.45 MB, which is a lot to keep for a
                   record you are storing by the hundred. */
                doc.addImage(logo, 'PNG', margin, y, maxH * (logo.width / logo.height), maxH, 'brandlogo', 'FAST');
                y += maxH + 6;
            }

            // --- Title -------------------------------------------------------
            doc.setFontSize(18);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(30, 30, 30);
            const titleLines = doc.splitTextToSize(formData.title || '', contentW);
            doc.text(titleLines, margin, y);
            y += titleLines.length * 7 + 2;

            // --- Meta --------------------------------------------------------
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(120, 120, 120);
            const num = filteredSubmissions.length - idx;
            const meta = '#' + num + '  |  ' +
                window.t('forms.subs.detail_submitted_by') + ' ' +
                (sub.submitted_by || window.t('forms.subs.unknown_user')) + '  |  ' +
                window.t('forms.subs.detail_date') + ' ' + formatDate(sub.submitted_date);
            doc.text(doc.splitTextToSize(meta, contentW), margin, y);
            y += 8;

            // --- Approval trail ----------------------------------------------
            // The reason this document exists. A list of answers with no
            // decision on it is a form, not a record.
            const ap = approvalParts(sub);
            room(18);
            doc.setDrawColor(210, 214, 220);
            doc.setFillColor(248, 250, 252);
            const apLines = [window.t('forms.subs.detail_approval') + ': ' + ap.label];
            if (sub.approval_decided_by) {
                apLines.push(window.t('forms.approval.approver') + ': ' + sub.approval_decided_by +
                    (sub.approval_decided_datetime ? '  (' + formatDate(sub.approval_decided_datetime) + ')' : ''));
            }
            if (sub.approval_comment) {
                apLines.push(window.t('forms.subs.approval_comment') + ': ' + sub.approval_comment);
            }
            const apWrapped = [];
            apLines.forEach(l => doc.splitTextToSize(l, contentW - 8).forEach(w => apWrapped.push(w)));
            const apH = apWrapped.length * 5 + 6;
            doc.roundedRect(margin, y, contentW, apH, 1.5, 1.5, 'FD');
            doc.setFontSize(10);
            doc.setTextColor(60, 60, 60);
            doc.text(apWrapped, margin + 4, y + 6);
            y += apH + 8;

            // --- Fields ---------------------------------------------------
            formData.fields.forEach(f => {
                const p = fieldValueParts(f, sub.data[f.id]);
                let valueLines;
                if (p.kind === 'bool') {
                    valueLines = [(p.checked ? '[x] ' : '[ ] ') + p.text];
                } else if (p.kind === 'list') {
                    valueLines = p.empty
                        ? [window.t('forms.subs.no_response')]
                        : p.list.map(v => '\u2022 ' + v);
                } else {
                    valueLines = doc.splitTextToSize(
                        p.empty ? window.t('forms.subs.no_response') : p.text, contentW);
                }

                const label = (f.label || '') +
                    (f.is_deleted == 1 ? ' (' + window.t('forms.subs.retired') + ')' : '');
                const labelLines = doc.splitTextToSize(label, contentW);
                room(labelLines.length * 5 + valueLines.length * 5 + 6);

                doc.setFontSize(9);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(100, 116, 139);
                doc.text(labelLines, margin, y);
                y += labelLines.length * 5;

                doc.setFontSize(11);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(p.empty ? 150 : 30, p.empty ? 150 : 30, p.empty ? 150 : 30);
                // A long answer can outrun a page on its own, so wrap line by
                // line rather than trusting the block to fit.
                valueLines.forEach(line => { room(5); doc.text(line, margin, y); y += 5; });
                y += 4;
            });

            return y;
        }

        /* Page numbers, stamped once the document is complete - they cannot be
           written as you go, because you do not know the total until the end. */
        function stampFooters(doc) {
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();
            const pages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(window.t('forms.subs.pdf_footer', { n: i, total: pages }),
                    pageW - 15, pageH - 8, { align: 'right' });
            }
        }

        function newPdfDoc() {
            const { jsPDF } = window.jspdf;
            return new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
        }

        /* Ticked rows, held by submission id rather than by index: an index is a
           position in the CURRENT filter and means something different the moment
           the list is redrawn. */
        const selectedIds = new Set();

        function toggleSelect(id, on) {
            if (on) selectedIds.add(id); else selectedIds.delete(id);
            refreshSelBar();
        }

        function toggleSelectAll(on) {
            selectedIds.clear();
            if (on) filteredSubmissions.forEach(s => selectedIds.add(s.id));
            document.querySelectorAll('.subs-table .sel-box[data-sub-id]')
                .forEach(b => { b.checked = on; });
            refreshSelBar();
        }

        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.subs-table .sel-box').forEach(b => { b.checked = false; });
            refreshSelBar();
        }

        function refreshSelBar() {
            const bar = document.getElementById('selBar');
            if (!bar) return;
            const n = selectedIds.size;
            bar.classList.toggle('open', n > 0);
            document.getElementById('selCount').textContent = window.t('forms.subs.sel_count', { n: n });

            /* The header tick shows all / none / some honestly. `indeterminate` is
               a property, never an attribute, so it cannot be set in the markup. */
            const all = document.getElementById('selAll');
            if (all) {
                const total = filteredSubmissions.length;
                all.checked = total > 0 && n === total;
                all.indeterminate = n > 0 && n < total;
            }
        }

        /* Which submissions are ticked, in the order the list shows them - so a
           bundle reads down the page the way the table does, rather than in the
           order somebody happened to click. */
        function selectedSubmissions() {
            return filteredSubmissions
                .map((sub, idx) => ({ sub: sub, idx: idx }))
                .filter(r => selectedIds.has(r.sub.id));
        }

        /* "Software Request - 6 submissions - 19.09.2026.pdf" - the same shape as
           a single export, and the same sanitising, because a form title can carry
           anything a filesystem refuses. */
        function bundleFileName(n) {
            const when = (typeof window.fmtDate === 'function') ? window.fmtDate(new Date()) : '';
            return cleanForFileName(window.t('forms.subs.bundle_name', {
                title: formData.title || 'Submissions',
                n: n,
                date: when
            })) + '.pdf';
        }

        async function exportSelected(mode) {
            const rows = selectedSubmissions();
            if (!rows.length || !formData) return;

            /* One save per file, and a browser asks once before it will accept a
               run of them. Worth saying so before forty start, rather than after. */
            if (mode === 'separate' && rows.length > 10 &&
                !confirm(window.t('forms.subs.sel_many_confirm', { n: rows.length }))) return;

            const bar = document.getElementById('selBar');
            if (bar) bar.querySelectorAll('button').forEach(b => { b.disabled = true; });
            try {
                const logo = await loadBrandLogo();

                if (mode === 'bundle') {
                    const doc = newPdfDoc();
                    rows.forEach((r, i) => {
                        // Each record starts its own page. The first one is the
                        // page the document already has.
                        if (i > 0) doc.addPage();
                        drawSubmission(doc, r.sub, r.idx, logo);
                    });
                    stampFooters(doc);
                    doc.save(bundleFileName(rows.length));
                } else {
                    for (const r of rows) {
                        const doc = newPdfDoc();
                        drawSubmission(doc, r.sub, r.idx, logo);
                        stampFooters(doc);
                        doc.save(submissionFileName(r.sub));
                        /* A beat between saves. Fired back to back, browsers drop
                           all but the first few - the downloads are queued by the
                           page, not by the click. */
                        await new Promise(res => setTimeout(res, 150));
                    }
                }
            } catch (e) {
                console.error(e);
                if (typeof showToast === 'function') showToast(window.t('forms.subs.pdf_error'), 'error');
            } finally {
                if (bar) bar.querySelectorAll('button').forEach(b => { b.disabled = false; });
            }
        }

        async function exportSubmissionPdf(idx) {
            if (typeof idx !== 'number') idx = currentDetailIndex;
            const sub = filteredSubmissions[idx];
            if (!sub || !formData) return;

            const btn = document.getElementById('detailPdfBtn');
            const fromPanel = (idx === currentDetailIndex) && document.getElementById('detailOverlay').classList.contains('open');
            if (btn && fromPanel) btn.disabled = true;
            try {
                const doc = newPdfDoc();
                drawSubmission(doc, sub, idx, await loadBrandLogo());
                stampFooters(doc);
                doc.save(submissionFileName(sub));
            } catch (e) {
                console.error(e);
                if (typeof showToast === 'function') showToast(window.t('forms.subs.pdf_error'), 'error');
            } finally {
                if (btn && fromPanel) btn.disabled = false;
            }
        }


        function closeDetail() {
            document.getElementById('detailOverlay').classList.remove('open');
        }

        // Date filter
        function applyFilter() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;

            filteredSubmissions = allSubmissions.filter(sub => {
                // Bucket by the LOCAL (analyst-zone) date so the filter
                // matches the date shown in the table — submitted_date is
                // a UTC instant, so use the zone-aware YYYY-MM-DD.
                const d = ymdInZone(parseUTCDate(sub.submitted_date));
                if (from && d < from) return false;
                if (to && d > to) return false;
                return true;
            });

            renderTable();
        }

        function clearFilter() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            filteredSubmissions = allSubmissions;
            renderTable();
        }

        // CSV export
        function exportCSV() {
            if (!formData || filteredSubmissions.length === 0) return;

            const headers = [window.t('forms.subs.csv_num'), window.t('forms.subs.csv_submitted_by'), window.t('forms.subs.csv_date')];
            formData.fields.forEach(f => headers.push(f.label));

            const rows = [headers.map(h => csvCell(h)).join(',')];

            filteredSubmissions.forEach((sub, idx) => {
                const row = [
                    filteredSubmissions.length - idx,
                    sub.submitted_by || window.t('forms.subs.unknown_user'),
                    formatDate(sub.submitted_date)
                ];

                formData.fields.forEach(f => {
                    const val = sub.data[f.id] ?? '';
                    if (f.field_type === 'checkbox') {
                        row.push(val === '1' ? window.t('forms.subs.csv_yes') : window.t('forms.subs.csv_no'));
                    } else if (f.field_type === 'checkboxes') {
                        row.push(decodeMultiValue(val).join('; '));
                    } else if (f.field_type === 'lookup') {
                        // Show the label the person actually chose. The id stays
                        // in the stored JSON for anything that wants the record.
                        const lbl = FormLogic.lookupLabel(val);
                        html += `<td title="${esc(lbl)}">${esc(lbl) || '<span style="color:var(--text-faint, #ccc)">—</span>'}</td>`;
                    } else if (f.field_type === 'datetime') {
                        // 'T' swapped for a space so Excel recognises it as a date/time
                        // rather than importing it as a lump of text. Still not
                        // timezone-converted — see FormLogic.formatDateValue().
                        row.push(FormLogic.formatDateValue(val));
                    } else {
                        row.push(val);
                    }
                });

                rows.push(row.map(c => csvCell(String(c))).join(','));
            });

            const csv = '\uFEFF' + rows.join('\r\n'); // BOM for Excel
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (formData.title || window.t('forms.subs.csv_default_name')).replace(/[^a-zA-Z0-9 _-]/g, '') + '_submissions.csv';
            a.click();
            URL.revokeObjectURL(url);
        }

        function csvCell(text) {
            if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                return '"' + text.replace(/"/g, '""') + '"';
            }
            return text;
        }

        // Delete submission
        let deleteSubId = null;

        function confirmDelete(id) {
            deleteSubId = id;
            document.getElementById('confirmOverlay').classList.add('open');
        }

        function closeConfirm() {
            document.getElementById('confirmOverlay').classList.remove('open');
            deleteSubId = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            if (!deleteSubId) return;
            try {
                const res = await fetch(API_BASE + 'delete_submission.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: deleteSubId })
                });
                const data = await res.json();
                if (data.success) {
                    closeConfirm();
                    closeDetail();
                    // Remove from arrays
                    allSubmissions = allSubmissions.filter(s => s.id !== deleteSubId);
                    filteredSubmissions = filteredSubmissions.filter(s => s.id !== deleteSubId);
                    renderTable();
                }
            } catch (e) {
                console.error(e);
            }
        });

        // Keyboard
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetail();
                closeConfirm();
            }
        });

        // Helpers
        // submitted_date is the server-stamped UTC receipt timestamp
        // (kind 1): parse as UTC and render in the analyst's zone.
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = parseUTCDate(dateStr);
            if (!d || isNaN(d)) return dateStr;
            return fmtDateTime(d);
        }

        function esc(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        function escAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // Decode the JSON-encoded array stored for multi-checkbox values.
        // Tolerant of empty / null / non-JSON garbage — never throws.
        function decodeMultiValue(raw) {
            if (raw == null || raw === '') return [];
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed.map(v => String(v)) : [];
            } catch (e) {
                return [];
            }
        }
    </script>
    <!-- Mobile layer. Adds the views hamburger and the module drawer on a phone.
         Loaded last so it can wrap the page's own globals rather than edit them. -->
    <script src="../assets/js/mobile.js?v=65"></script>
</body>
</html>
