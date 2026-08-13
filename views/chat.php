<?php
$db = \Core\Database::getInstance();
$userId = \Core\Auth::id();
$_chatCurrentUser = \Core\Auth::user();
$_currentUserAbbr = trim($_chatCurrentUser['abbreviation'] ?? '');
if ($_currentUserAbbr === '' && !empty($_chatCurrentUser['name'])) {
    $_parts = preg_split('/\s+/', trim($_chatCurrentUser['name']));
    $_currentUserAbbr = mb_strtoupper(
        mb_substr($_parts[0] ?? '', 0, 1) .
        (count($_parts) >= 2 ? mb_substr($_parts[count($_parts) - 1], 0, 1) : '')
    );
}
$_currentUserName = $_chatCurrentUser['name'] ?? 'Du';

// Available models
$models = $db->query("SELECT model_id, display_name, provider FROM ai_models WHERE is_active = 1 AND chat_enabled = 1 ORDER BY sort_order, display_name");

// Available customers for the user
$customers = [];
if (\Core\Auth::isAdmin()) {
    $customers = $db->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
} elseif (\Core\Auth::activeCustomer()) {
    $customers = \Core\Auth::customers();
}

// Settings
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$defaultModel = $settings['default_model'] ?? 'gpt-4';
$_isAdmin = \Core\Auth::isAdmin();
?>

<style>
/* ==========================================
   Chat Layout
   ========================================== */
/* Chat lebt im main-content-Gutter wie Kunden/Wissen/etc. Spalten sind eigene
   Cards mit gap = --d-gutter (gleiche Breite wie das Padding aussenrum). */
.chat-container {
    display: flex;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    overflow: visible;
}
/* Sidebar und Main als eigene Card-Frames */
.chat-sidebar,
.chat-main {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}

/* ==========================================
   Chat Sidebar
   ========================================== */
.chat-sidebar {
    width: 360px;
    min-width: 360px;
    border-right: 1px solid var(--slate-200);
    display: flex;
    flex-direction: column;
    background: var(--slate-50);
    overflow: hidden;
    transition: width 0.2s ease, min-width 0.2s ease;
}
.chat-sidebar.collapsed {
    width: 56px;
    min-width: 56px;
}
.chat-sidebar.collapsed .chat-sidebar-header,
.chat-sidebar.collapsed .chat-sidebar-content,
.chat-sidebar.collapsed .explorer-nav {
    display: none !important;
}
/* Kunden-Kuerzel-Badge im collapsed-Modus — gleicher Look wie PP-Sidebar */
.chat-collapsed-abbr {
    width: 40px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-control-radius);
    font-size: var(--d-fs-xs); font-weight: 700;
    color: var(--slate-700);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}
.chat-collapsed-abbr:hover {
    background: var(--thoxan-50); border-color: var(--thoxan-300); color: var(--thoxan-700);
}
.chat-collapsed-abbr.is-active {
    background: var(--thoxan-600); border-color: var(--thoxan-600); color: #fff;
}
.chat-collapsed-divider {
    width: 24px; height: 1px; background: var(--slate-200);
    margin: 4px 0; flex-shrink: 0;
}
.chat-sidebar-collapse-btn {
    background: none;
    border: none;
    padding: 4px;
    border-radius: var(--radius-sm);
    color: var(--color-gray-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}
.chat-sidebar-collapse-btn:hover {
    background: var(--color-gray-200);
    color: var(--color-gray-700);
}
.chat-sidebar-collapse-btn .material-symbols-rounded { font-size: 20px; }
.chat-sidebar-collapsed-bar {
    display: none;
    flex-direction: column;
    align-items: center;
    padding: 8px 4px;
    gap: 4px;
    overflow-y: auto;
    flex: 1;
}
.chat-sidebar.collapsed .chat-sidebar-collapsed-bar {
    display: flex;
}

.chat-sidebar-header {
    padding: var(--d-gutter);
    border-bottom: 1px solid var(--color-gray-200);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-sidebar-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chat-sidebar-title {
    font-size: var(--d-fs-sm);
    font-weight: 700;
    color: var(--color-gray-800);
}
.chat-sidebar-toolbar-actions {
    display: flex;
    gap: 2px;
}
.chat-toolbar-btn {
    background: none;
    border: none;
    padding: 4px;
    border-radius: var(--radius-sm);
    color: var(--color-gray-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}
.chat-toolbar-btn:hover {
    background: var(--color-gray-200);
    color: var(--color-gray-700);
}
.chat-toolbar-btn .material-symbols-rounded { font-size: 18px; }

.chat-sidebar-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.chat-search-icon {
    position: absolute;
    left: 8px;
    font-size: 16px;
    color: var(--color-gray-400);
    pointer-events: none;
}
.chat-search {
    width: 100%;
    padding: 6px 10px 6px 28px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
    background: rgba(255, 255, 255, 0.7);
    outline: none;
    transition: border-color var(--transition-fast);
}
.chat-search:focus { border-color: var(--color-gray-400); background: white; }

.chat-sidebar-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 4px 0;
    scrollbar-width: thin;
    scrollbar-color: var(--color-gray-300) transparent;
}
.chat-sidebar-content::-webkit-scrollbar { width: 6px; }
.chat-sidebar-content::-webkit-scrollbar-track { background: transparent; }
.chat-sidebar-content::-webkit-scrollbar-thumb { background: var(--color-gray-300); border-radius: 3px; }
.chat-sidebar-content::-webkit-scrollbar-thumb:hover { background: var(--color-gray-400); }

/* Kontext-Folder (Privat / Projektübergreifend / Pro Kunde) */
.ctx-folders {
    padding: 8px 4px 12px;
    border-bottom: 1px solid var(--color-gray-200);
    margin-bottom: 4px;
}

/* Icon-Filter-Bar (Stil wie im Projektplanner) — kompakte Filter-Reihe oben.
   Padding nutzt --d-gutter, damit die Icons buendig mit dem restlichen Sidebar-
   Innenrand sitzen. */
.ctx-filter-bar {
    display: flex; gap: 2px; align-items: center; flex-wrap: nowrap;
    padding: 6px var(--d-gutter, 18px) 8px;
    margin-bottom: 4px;
    border-bottom: 1px solid var(--color-gray-100);
}
.ctx-filter-icon {
    width: 30px; height: 30px;
    border-radius: var(--d-control-radius, 6px);
    display: inline-flex; align-items: center; justify-content: center;
    background: transparent; border: none; cursor: pointer;
    color: var(--slate-500);
    transition: background 0.1s, color 0.1s;
    flex-shrink: 0;
}
.ctx-filter-icon:hover { background: var(--slate-100); color: var(--slate-800); }
.ctx-filter-icon .material-symbols-rounded { font-size: 18px; }
.ctx-filter-icon.is-active { background: var(--thoxan-600); color: #fff; }
.ctx-filter-icon.is-active:hover { background: var(--thoxan-700); }
.ctx-filter-icon.is-active .material-symbols-rounded { color: #fff; }
.ctx-filter-sep {
    width: 1px; align-self: stretch; background: var(--slate-200);
    margin: 2px 4px;
}

/* Fokus-Modus: nur aktiver Kontext + Zurück-Pfeil */
.ctx-focused {
    display: flex; align-items: center; gap: 10px;
    padding: 10px var(--d-gutter, 18px);
    border-bottom: 1px solid var(--slate-100);
}
.ctx-back {
    background: transparent; border: 0; cursor: pointer;
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    color: var(--slate-500); transition: background 0.1s, color 0.1s; flex-shrink: 0;
}
.ctx-back:hover { background: var(--slate-100); color: var(--slate-900); }
.ctx-back .material-symbols-rounded { font-size: 18px; }
/* Kontext-Badge im Fokus-Modus — kompakt + dezent (analog Toolbar-Look) */
.ctx-focused-badge {
    width: 34px; height: 34px;
    border-radius: var(--d-control-radius);
    background: #fff !important;     /* ueberschreibt ggf. Inline-Style mit Gradient */
    color: var(--thoxan-700);
    border: 1px solid var(--slate-200);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; letter-spacing: 0.4px;
    flex-shrink: 0; text-transform: uppercase;
}
.ctx-focused-badge .material-symbols-rounded { font-size: 18px; color: var(--thoxan-600); }
.ctx-focused-label { flex: 1; min-width: 0; }
.ctx-focused-label strong { display: block; font-size: var(--d-fs-sm); color: var(--slate-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.ctx-focused-label small { display: block; font-size: 11px; color: var(--slate-400); margin-top: 1px; }

/* Themen-Ordner-Bar im Kontext-Fokus — schlanke, einheitliche Pills + Inline-Actions */
.ctx-folder-bar {
    display: flex; flex-wrap: wrap; gap: 4px; align-items: center;
    padding: 8px var(--d-gutter, 18px) 10px;
    border-bottom: 1px solid var(--slate-100);
}
/* Themen-Chips eckig (var(--d-control-radius)) — analog allen THX-Buttons */
.ctx-fbar-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 10px;
    height: 26px; line-height: 1;
    border-radius: var(--d-control-radius);
    background: #fff; border: 1px solid var(--slate-200);
    color: var(--slate-700); font-size: var(--d-fs-xs); font-weight: 500;
    cursor: pointer; font-family: inherit;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
    white-space: nowrap;
}
.ctx-fbar-chip:hover {
    border-color: var(--slate-300); background: var(--slate-50); color: var(--slate-900);
}
.ctx-fbar-chip.active {
    background: var(--thoxan-50); color: var(--thoxan-800);
    border-color: var(--thoxan-300); font-weight: 600;
}
.ctx-fbar-chip.active .ctx-fbar-count {
    background: var(--thoxan-100); color: var(--thoxan-800);
}
.ctx-fbar-chip .material-symbols-rounded { font-size: 14px; color: var(--slate-400); }
.ctx-fbar-chip.active .material-symbols-rounded { color: var(--thoxan-700); }
.ctx-fbar-count {
    background: var(--slate-100); color: var(--slate-500);
    padding: 0 6px; border-radius: 4px; font-size: 10px; font-weight: 700;
    line-height: 16px; min-width: 18px; text-align: center;
}
.ctx-fbar-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
/* + Neuer Ordner + KI-Vorschlag: eckige Icon-Buttons in gleicher Hoehe wie Chips */
.ctx-fbar-add, .ctx-fbar-ai {
    width: 26px; height: 26px;
    border-radius: var(--d-control-radius);
    background: transparent;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
    flex-shrink: 0;
}
.ctx-fbar-add {
    border: 1px dashed var(--slate-300);
    color: var(--slate-400);
}
.ctx-fbar-add:hover {
    border-color: var(--thoxan-500); border-style: solid;
    color: var(--thoxan-700); background: var(--thoxan-50);
}
.ctx-fbar-ai {
    border: 1px solid var(--emerald-200);
    color: var(--emerald-600);
}
.ctx-fbar-ai:hover { background: var(--emerald-50); border-color: var(--emerald-400); }
.ctx-fbar-add .material-symbols-rounded,
.ctx-fbar-ai .material-symbols-rounded { font-size: 14px; }
.ctx-fbar-chip.drop-target {
    background: var(--emerald-50);
    border-color: var(--emerald-400); color: var(--emerald-800);
}
.finder-item.dragging { opacity: 0.4; }

/* KI-Cluster-Modal: Cards pro Themen-Cluster */
.sb-cluster {
    border: 1px solid #e2e8f0; border-radius: 11px;
    padding: 12px 14px; margin-bottom: 8px;
    transition: all 0.15s;
}
.sb-cluster.accepted { background: #fff; border-color: #bbf7d0; }
.sb-cluster.rejected { background: #f8fafc; opacity: 0.55; }
.sb-cluster-head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
}
.sb-cluster-name {
    flex: 1; min-width: 0;
    padding: 4px 8px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-weight: 700; color: #1e293b; background: transparent; font-family: inherit;
}
.sb-cluster-name:hover { background: #f8fafc; border-color: #e2e8f0; }
.sb-cluster-name:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-cluster-count { color: #64748b; font-size: var(--d-fs-sm); font-weight: 600; flex-shrink: 0; }
.sb-cluster-chats { display: flex; flex-wrap: wrap; gap: 4px; }
.sb-cluster-chip {
    display: inline-block; padding: 2px 9px; background: #f1f5f9; color: #475569;
    border-radius: 10px; font-size: var(--d-fs-xs);
}

/* Farb-Wähler im Folder-Kontextmenü */
.sb-color-dot {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid transparent; cursor: pointer; padding: 0;
    transition: transform 0.1s, border-color 0.1s;
}
.sb-color-dot:hover { transform: scale(1.15); }
.sb-color-dot.active { border-color: #1e293b; box-shadow: 0 0 0 2px rgba(0,0,0,0.06); }

/* Reassign-Modal-Zeilen */
.sb-reassign-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px;
    margin-bottom: 4px; cursor: pointer; transition: all 0.12s;
}
.sb-reassign-row:hover { border-color: var(--thoxan-700); background: rgba(0,76,155,0.04); }
.sb-reassign-row.current { background: #f8fafc; cursor: default; }
.sb-reassign-row.current:hover { border-color: #e2e8f0; background: #f8fafc; }
.sb-reassign-row .ctx-folder-abbr { font-size: var(--d-fs-xs); }
.ctx-folder {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px var(--d-gutter, 18px);
    border-left: 3px solid transparent;
    cursor: pointer;
    transition: background 0.1s, border-color 0.1s;
    font-size: var(--d-fs-sm);
}
.ctx-folder:hover { background: var(--slate-50); }
.ctx-folder.active {
    background: var(--thoxan-50);
    border-left-color: var(--thoxan-600);
    color: var(--slate-900);
    font-weight: 600;
}
.ctx-folder-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ctx-folder-abbr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 22px;
    padding: 0;
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200);
    font-size: 11px;
    font-weight: 700;
    border-radius: 5px;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}
.ctx-folder.active .ctx-folder-abbr {
    background: #fff;
    color: var(--thoxan-700);
    border-color: var(--thoxan-300);
}
.ctx-folder-count {
    font-size: var(--d-fs-xs);
    color: #94a3b8;
    background: var(--color-gray-100);
    padding: 1px 7px;
    border-radius: 10px;
    font-weight: 600;
}
.ctx-folder.active .ctx-folder-count { background: rgba(0, 76, 155,0.15); color: var(--thoxan-900); }
.ctx-folder-section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--slate-400);
    padding: 12px var(--d-gutter, 18px) 4px;
}
.sidebar-empty, .sidebar-hint {
    text-align: center;
    padding: 28px 16px;
    color: var(--color-gray-400);
    font-size: var(--d-fs-sm);
    line-height: 1.5;
}
.sidebar-empty small { color: #cbd5e1; font-size: var(--d-fs-xs); display: block; margin-top: 4px; }
.sidebar-hint {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

/* Creator-Avatar + Customer-Badge in Conv-Liste — beide im Outline-Stil,
   einheitlich mit den Kuerzel-Badges in Sidebar und Message-Header. */
.creator-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 26px;
    height: 18px;
    padding: 0 5px;
    border-radius: var(--d-control-radius);
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    text-transform: uppercase;
}
.customer-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    background: #fff;
    color: var(--slate-700);
    border: 1px solid var(--slate-200);
    border-radius: var(--d-control-radius);
    letter-spacing: 0.3px;
    flex-shrink: 0;
    text-transform: uppercase;
}

/* Welcome-Screen Kontext-Pille (klein und unauffaellig oben) */
.welcome-context-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px 4px 8px;
    border-radius: 999px;
    font-size: var(--d-fs-sm);
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    margin: 0 0 16px;
}
.welcome-context-pill button {
    background: none; border: none; cursor: pointer;
    color: #94a3b8; font-size: var(--d-fs-xs); padding: 0 0 0 4px;
    text-decoration: underline; font-family: inherit;
}
.welcome-context-pill button:hover { color: #475569; }

/* Kontext-Picker (drei Karten) */
.welcome-context-picker {
    width: 100%;
    max-width: 760px;
    margin: 8px auto 12px;
    text-align: center;
}
.welcome-context-hint {
    text-align: center;
    color: #64748b;
    font-size: var(--d-fs-sm);
    margin: 4px auto 20px;
    max-width: 540px;
}
.welcome-context-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 720px) {
    .welcome-context-grid { grid-template-columns: 1fr; }
}
.welcome-ctx-card {
    background: #fff;
    border: 1px solid var(--color-gray-200);
    border-radius: 14px;
    padding: 22px 18px;
    cursor: pointer;
    transition: all 0.18s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-align: center;
    font-family: inherit;
}
.welcome-ctx-card:hover {
    border-color: var(--thoxan-700);
    box-shadow: 0 6px 24px rgba(0, 76, 155,0.10);
    transform: translateY(-1px);
}
.welcome-ctx-card .material-symbols-rounded { font-size: 32px; }
.welcome-ctx-card strong { font-size: var(--d-fs-sm); color: #1e293b; }
.welcome-ctx-card small { font-size: var(--d-fs-sm); color: #94a3b8; }

.welcome-customer-picker {
    margin-top: 16px;
    background: #fff;
    border: 1px solid var(--color-gray-200);
    border-radius: 14px;
    padding: 14px;
}
.welcome-customer-picker input {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--color-gray-200);
    border-radius: 8px;
    font-size: var(--d-fs-sm);
    box-sizing: border-box;
    margin-bottom: 10px;
    font-family: inherit;
}
.welcome-customer-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.1s;
}
.welcome-customer-row:hover { background: var(--color-gray-50); }
.welcome-customer-abbr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 26px;
    padding: 0 8px;
    background: linear-gradient(135deg, #e6f0fa, #d6e7f5);
    color: var(--thoxan-900);
    font-size: var(--d-fs-xs);
    font-weight: 700;
    border-radius: 6px;
    letter-spacing: 0.4px;
}
.welcome-customer-name { flex: 1; font-size: var(--d-fs-sm); color: #1e293b; }
.welcome-customer-meta { font-size: var(--d-fs-xs); color: #94a3b8; }

/* Finder Section Header */
.finder-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px var(--d-gutter, 18px) 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--slate-400);
    user-select: none;
}
.finder-section-header button {
    background: none;
    border: none;
    color: var(--color-gray-400);
    padding: 2px;
    border-radius: 4px;
    display: flex;
    cursor: pointer;
}
.finder-section-header button:hover { color: var(--color-gray-600); background: var(--color-gray-200); }
.finder-section-header button .material-symbols-rounded { font-size: 14px; }

/* Finder Folder (Project) */
/* Explorer Navigation Bar */
.explorer-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--color-gray-100);
    min-height: 36px;
    flex-shrink: 0;
}
.explorer-back-btn {
    background: none; border: none;
    padding: 4px; border-radius: var(--radius-sm);
    color: var(--color-gray-500); cursor: pointer;
    display: flex; align-items: center;
    transition: all var(--transition-fast);
}
.explorer-back-btn:hover { background: var(--color-gray-200); color: var(--color-gray-700); }
.explorer-back-btn .material-symbols-rounded { font-size: 20px; }
.explorer-nav-title {
    font-size: var(--d-fs-sm); font-weight: 600;
    color: var(--color-gray-700);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;
}

/* Explorer Folder Row */
.explorer-folder-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; margin: 2px 6px;
    border-radius: var(--radius-md); cursor: pointer;
    font-size: var(--d-fs-sm); font-weight: 500;
    color: var(--color-gray-700);
    transition: background var(--transition-fast);
    user-select: none;
}
.explorer-folder-row:hover { background: rgba(0,0,0,0.04); }
.explorer-folder-row-icon { font-size: 18px; flex-shrink: 0; }
.explorer-folder-row-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.explorer-folder-row-meta {
    font-size: var(--d-fs-xs); color: var(--color-gray-400);
    background: var(--color-gray-100); padding: 1px 7px;
    border-radius: 10px; flex-shrink: 0;
}
.explorer-folder-row-arrow { font-size: 16px; color: var(--color-gray-300); flex-shrink: 0; }
.explorer-folder-row-actions { display: none; gap: 2px; flex-shrink: 0; }
.explorer-folder-row:hover .explorer-folder-row-actions { display: flex; }
.explorer-folder-row:hover .explorer-folder-row-meta,
.explorer-folder-row:hover .explorer-folder-row-arrow { display: none; }
.explorer-folder-row-actions button {
    background: none; border: none; padding: 2px;
    color: var(--color-gray-400); border-radius: 4px;
    display: flex; cursor: pointer;
}
.explorer-folder-row-actions button:hover { color: var(--color-gray-700); background: rgba(0,0,0,0.06); }
.explorer-folder-row-actions .material-symbols-rounded { font-size: 14px; }

/* Drag & Drop */
.explorer-folder-row.drag-over {
    background: rgba(0,122,255,0.08);
    outline: 2px dashed rgba(0,122,255,0.4);
    outline-offset: -2px;
}
.explorer-folder-row[draggable="true"] { cursor: grab; }
.explorer-folder-row.dragging { opacity: 0.4; }
.finder-root-drop-zone.drag-over {
    background: rgba(0,122,255,0.06);
    outline: 2px dashed rgba(0,122,255,0.3);
    min-height: 24px !important;
}

/* Slide Animations */
@keyframes explorerSlideIn {
    from { transform: translateX(30px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes explorerSlideBack {
    from { transform: translateX(-30px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.explorer-slide-enter { animation: explorerSlideIn 200ms ease-out; }
.explorer-slide-back { animation: explorerSlideBack 200ms ease-out; }

/* Finder Item (Conversation) */
.finder-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px var(--d-gutter, 18px);
    border-radius: 0;
    border-left: 3px solid transparent;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: var(--slate-700);
    transition: background 0.1s, border-color 0.1s;
    user-select: none;
}
.finder-item:hover { background: var(--slate-50); }
.finder-item.active {
    background: var(--thoxan-50);
    border-left-color: var(--thoxan-600);
    color: var(--slate-900);
    font-weight: 500;
}
.finder-item-icon {
    font-size: 16px;
    color: var(--slate-400);
    flex-shrink: 0;
}
.finder-item.active .finder-item-icon { color: var(--thoxan-600); }
.finder-item-title {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.finder-item-badges {
    display: flex;
    gap: 4px;
    align-items: center;
    flex-shrink: 0;
}
.finder-item-shared {
    font-size: 12px;
    color: var(--color-info);
}
.finder-item-actions {
    display: none;
    gap: 2px;
    flex-shrink: 0;
}
.finder-item:hover .finder-item-actions { display: flex; }
.finder-item:hover .finder-item-badges { display: none; }
.finder-item-actions button {
    background: none;
    border: none;
    padding: 2px;
    color: var(--color-gray-400);
    border-radius: 4px;
    display: flex;
    cursor: pointer;
}
.finder-item-actions button:hover { color: var(--color-gray-700); background: rgba(0, 0, 0, 0.06); }
.finder-item-actions .material-symbols-rounded { font-size: 14px; }
.finder-item[draggable="true"] { cursor: grab; }
.finder-item.dragging { opacity: 0.4; background: rgba(0, 122, 255, 0.06); }

/* Date group */
.finder-date-label {
    padding: 12px 12px 4px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--color-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    user-select: none;
}

/* Auto-Mode Badge */
.chat-model-badge.auto-mode {
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-900));
    color: white;
}

/* Artifact Toggle Switch */
/* Toolbar-Toggle (Web / Wissen / Dual): wir nutzen die globale .thx-btn-Klasse,
   damit der Look identisch zu allen anderen Buttons (PP/LAM/Settings) ist.
   Das <input> ist versteckt; aktiv-Zustand kommt ueber :has() ans Label. */
.chat-artifact-toggle {
    cursor: pointer;
}
.chat-artifact-toggle input[type="checkbox"],
.toggle-switch { display: none; }
/* Aktive Toggle-Buttons: subtiler Thoxan-Fuell-Akzent (gleiches Pattern wie
   die "Erledigt"-Done-Buttons im PP — keine eigene Farbschule). */
.chat-artifact-toggle:has(input:checked) {
    background: var(--thoxan-50);
    border-color: var(--thoxan-300);
    color: var(--thoxan-800);
}
.chat-artifact-toggle:has(input:checked) .material-symbols-rounded {
    color: var(--thoxan-700);
}

/* Artifact Suggestion Cards */
.artifact-suggestion-card {
    padding: 10px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
    background: white;
}
.artifact-suggestion-card .btn-sm {
    padding: 3px 8px;
    font-size: var(--d-fs-xs);
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid var(--color-gray-200);
    background: white;
}
.artifact-suggestion-card .btn-sm.btn-primary {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

/* ==========================================
   Chat Main Area
   ========================================== */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    overflow: hidden;
}

/* Chat Header — Inner-Padding identisch zum globalen Gutter */
/* Chat-Header: zweizeilig (Titel+Kunde / Toolbar) — gleiche Bausteine wie der
   Projektplanner-Header, nur dass die Actions in eine eigene Zeile rutschen. */
.chat-header {
    margin: 0;
    padding: 10px var(--d-gutter) var(--d-gutter);   /* unten 18px, damit unter dem Toolbar
                                                        sichtbarer Abstand zum Messages-Bereich entsteht */
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: stretch;   /* WICHTIG: ueberschreibt das geerbte align-items:center
                               aus .thx-page-header — sonst werden die Rows zentriert. */
    justify-content: flex-start;
    gap: 10px;
}
.chat-header-row {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    justify-content: flex-start;
    width: 100%;
}
.chat-header-row-top { gap: 8px; }
/* Override des globalen .thx-page-actions: hier linksbuendig, nicht rechtsbuendig.
   space-between aus .thx-page-header greift im column-Modus nicht — sicher ist sicher. */
#chat-header .thx-page-actions { justify-content: flex-start; margin: 0; gap: 6px; }
#chat-header .thx-page-title,
#chat-header .chat-header-title-input {
    margin: 0;
    font-size: var(--d-fs-lg);
    font-weight: 700;
    color: var(--slate-900);
    line-height: 1.4;
    /* Padding nach links wird per negativem Margin kompensiert, damit der
       sichtbare Text exakt auf derselben x-Achse startet wie die Buttons in
       der zweiten Zeile (= Container-Kante). Hover/Focus-Padding bleibt aber
       fuer einen anstaendigen Klickbereich erhalten. */
    padding: 3px 8px;
    margin-left: -8px;
    border-radius: var(--d-control-radius);
    border: 1px solid transparent;
    max-width: 600px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    flex: 0 1 auto;
    transition: background 0.1s, border-color 0.1s;
    font-family: inherit;
    background: transparent;
    height: auto;
}
#chat-header .thx-page-title { cursor: text; }
#chat-header .thx-page-title:hover {
    background: var(--slate-50);
    border-color: var(--slate-200);
}
/* Inline-Edit-Input erbt die gleichen Maße + bekommt Fokus-Ring */
#chat-header .chat-header-title-input {
    width: 480px;
    outline: none;
    background: #fff;
    border-color: var(--thoxan-400);
    box-shadow: 0 0 0 3px rgba(0, 76, 155, 0.12);
}
.chat-header-title {
    font-size: var(--d-fs-sm);
    font-weight: 600;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid transparent;
    min-width: 60px;
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.chat-header-title:hover { border-color: var(--color-gray-300); }
/* .chat-header-title-input — moved to the chat-header block above so it inherits
   the same size as .thx-page-title. */
.chat-header-badges {
    display: flex;
    gap: 6px;
    align-items: center;
    flex: 1;
}
.chat-badge {
    font-size: var(--d-fs-xs);
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
    white-space: nowrap;
}
.chat-badge-model {
    background: var(--color-gray-100);
    color: var(--color-gray-600);
}
.chat-badge-customer {
    background: #dbeafe;
    color: #1d4ed8;
}
.chat-badge-shared {
    background: #f0fdf4;
    color: var(--emerald-600);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;
}
.chat-badge-shared .material-symbols-rounded { font-size: 12px; }

.chat-header-actions {
    display: flex;
    gap: 4px;
    margin-left: auto;
}
.chat-header-actions button {
    background: none;
    border: none;
    padding: 6px;
    border-radius: var(--radius-sm);
    color: var(--color-gray-500);
    display: flex;
    cursor: pointer;
}
.chat-header-actions button:hover { background: var(--color-gray-100); color: var(--color-gray-700); }
.chat-header-actions .material-symbols-rounded { font-size: 20px; }

/* Wissen-Engine-Select: schmaler als der Default-thx-select-min-width:200px,
   aber sonst identisches Styling. */
#chat-knowledge-engine.thx-select,
#welcome-knowledge-engine.thx-select { min-width: 0; }

/* ==========================================
   Messages Area
   ========================================== */
/* Wrapper haelt TOC (links) + Messages (Mitte) nebeneinander. */
.chat-messages-wrap {
    flex: 1;
    display: flex;
    min-height: 0;
    overflow: hidden;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: var(--d-gutter);
    display: flex;
    flex-direction: column;
    gap: 0;
    /* scroll-behavior bleibt 'auto' (= instant). Smooth-Scroll triggern wir
       gezielt nur fuer den TOC-Klick, nicht fuer den Chat-Load. */
}

/* Inhalts-Verzeichnis links — komfortable Klick-Flaeche pro User-Prompt.
   Jeder Eintrag ist eine ganze Zeile (Breite + sichtbarer Strich + Padding),
   nicht nur der duenne Strich allein — wesentlich leichter zu treffen. */
.chat-toc {
    flex-shrink: 0;
    width: 64px;
    padding: var(--d-gutter) 0;
    display: flex; flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    border-right: 1px solid var(--slate-100);
    background: #fff;
}
.chat-toc:empty { display: none; }
.chat-toc-item {
    display: flex; align-items: center; justify-content: flex-start;
    padding: 6px var(--d-gutter);
    width: 100%; height: auto;
    background: transparent;
    border: 0;
    cursor: pointer;
    transition: background 0.1s;
    position: relative;
    text-align: left;
}
/* Der sichtbare Strich — visuell mittig in der Klick-Zeile */
.chat-toc-item::before {
    content: '';
    display: block;
    width: 18px; height: 3px;
    background: var(--slate-300);
    border-radius: 2px;
    transition: width 0.12s, background 0.12s, height 0.12s;
}
/* Hover + Aktiv-Markierung — kein Extra-Highlight fuer Anfang/Ende mehr,
   weil das parallel zur aktiv-Markierung "doppelte" Striche erzeugt hat. */
.chat-toc-item:hover { background: var(--slate-50); }
.chat-toc-item:hover::before { width: 28px; background: var(--slate-600); }
.chat-toc-item:focus { outline: none; background: var(--slate-50); }
.chat-toc-item:focus::before { width: 28px; background: var(--slate-700); }
.chat-toc-item.is-active { background: var(--thoxan-50); }
.chat-toc-item.is-active::before {
    width: 30px; height: 4px; background: var(--thoxan-600);
}
.chat-toc-item.is-active:focus::before {
    width: 30px; background: var(--thoxan-700);
}
.chat-toc:focus { outline: none; }
/* Tooltip mit Prompt-Anfang — rein per CSS, ohne JS-Libs. */
.chat-toc-item[data-preview]:hover::after {
    content: attr(data-preview);
    position: absolute;
    left: calc(100% + 4px); top: 50%;
    transform: translateY(-50%);
    background: var(--slate-900); color: #fff;
    padding: 6px 10px; border-radius: 6px;
    font-size: 11px; line-height: 1.35;
    max-width: 360px; min-width: 140px;
    white-space: normal; word-break: break-word;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
    z-index: 30;
    pointer-events: none;
}

.chat-msg {
    max-width: 960px;
    width: 100%;
    margin: 0 auto;
    padding: 18px 0;
}
.chat-msg + .chat-msg { margin-top: 4px; }

.chat-msg-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: var(--d-fs-sm);
    font-weight: 600;
}
.msg-time {
    color: #94a3b8; font-size: var(--d-fs-xs); font-weight: 500;
    margin-left: 4px; cursor: help;
}
/* Aktions-Gruppe rechts neben der Zeit (Kopieren / DOCX / Wissen) —
   icon-only Buttons, gleich gross, sauber abgesetzt vom Meta-Block */
.msg-actions {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: auto;
}
.msg-actions .thx-icon-btn { color: var(--slate-500); }
.msg-actions .thx-icon-btn:hover { color: var(--thoxan-700); }

/* Mitarbeiter-/KI-Kuerzel im Message-Header — dezenter Outline-Style,
   gleiche Linie wie alle Kuerzel-Badges in der App (kein Gradient mehr) */
.msg-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 22px;
    padding: 0 6px;
    border-radius: var(--d-control-radius);
    border: 1px solid var(--slate-200);
    background: #fff;
    color: var(--slate-700);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    text-transform: uppercase;
}
.msg-avatar-user {
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border-color: var(--thoxan-200);
}
.msg-avatar-assistant {
    background: var(--slate-50);
    color: var(--slate-700);
    border-color: var(--slate-200);
    min-width: 22px;
    width: 22px;
    padding: 0;
    text-transform: none;
}

/* User-Message: Bubble mit Akzent-Hintergrund + linker Border */
.chat-msg.user .chat-msg-header {
    color: var(--thoxan-900);
}
.chat-msg.user .chat-msg-body {
    background: #e6f0fa;
    border-left: 3px solid var(--thoxan-700);
    border-radius: 0 12px 12px 0;
    padding: 14px 18px;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: var(--d-fs-sm);
    line-height: 1.65;
    color: #1e293b;
    margin-left: 36px;
}

/* Assistant-Message: prominenter Body, kein Background, mehr Luft */
.chat-msg.assistant .chat-msg-header {
    color: var(--color-gray-500);
}
.chat-msg.assistant .chat-msg-body {
    font-size: var(--d-fs-base);
    line-height: 1.75;
    color: #1e293b;
    padding: 4px 0;
    margin-left: 30px;
}

.chat-msg-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.chat-attachment-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-200);
    border-radius: 20px;
    font-size: var(--d-fs-xs);
    color: var(--color-gray-600);
    text-decoration: none;
}
.chat-attachment-chip.chat-attachment-linked {
    background: #e6f0fa;
    border-color: #b3d0ec;
    color: var(--thoxan-900);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}
.chat-attachment-chip.chat-attachment-linked:hover {
    background: #d6e7f5;
    border-color: var(--thoxan-700);
}

/* Artifacts used (collapsible per message) */
.artifacts-used-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
    padding: 3px 10px;
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-500);
    cursor: pointer;
    transition: all var(--transition-fast);
    user-select: none;
}
.artifacts-used-toggle:hover {
    background: var(--color-gray-100);
    color: var(--color-gray-700);
}
.artifacts-used-toggle .material-symbols-rounded {
    font-size: 14px;
    transition: transform 0.2s;
}
.artifacts-used-toggle.open .material-symbols-rounded.arrow-icon {
    transform: rotate(180deg);
}
.artifacts-used-list {
    display: none;
    flex-direction: column;
    gap: 4px;
    margin-top: 6px;
    padding: 8px 10px;
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: var(--d-fs-xs);
}
.artifacts-used-list.open { display: flex; }
.artifact-used-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--transition-fast);
}
.artifact-used-item:hover { background: var(--color-gray-100); }
.artifact-used-type {
    font-size: var(--d-fs-xs);
    padding: 1px 6px;
    border-radius: 8px;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
}
.artifact-used-type[data-type="Regel"] { background: #dbeafe; color: #1e40af; }
.artifact-used-type[data-type="Wissen"] { background: #dcfce7; color: #166534; }
.artifact-used-type[data-type="Profil"] { background: #fef3c7; color: #92400e; }
.artifact-used-type[data-type="Autor"] { background: #d6e7f5; color: #002a5a; }
.artifact-used-type[data-type="Namespace"] { background: #fce7f3; color: #9d174d; }
.artifact-used-type[data-type="Artefakt"] { background: var(--color-gray-100); color: var(--color-gray-600); }
.artifact-used-name { flex: 1; color: var(--color-gray-700); }
.artifact-used-source {
    font-size: var(--d-fs-xs);
    color: var(--color-gray-400);
}
.artifact-used-namespace {
    font-size: var(--d-fs-xs);
    color: var(--color-gray-400);
    margin-left: auto;
}
.artifact-used-detail {
    display: none;
    margin-top: 4px;
    padding: 8px 12px;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-600);
    line-height: 1.5;
    max-height: 200px;
    overflow-y: auto;
}
.artifact-used-detail.open { display: block; }

.chat-model-badge {
    font-size: var(--d-fs-xs);
    padding: 2px 8px;
    border-radius: 10px;
    background: var(--color-gray-100);
    color: var(--color-gray-500);
}

.chat-copy-btn {
    background: none;
    border: none;
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--color-gray-400);
    font-size: var(--d-fs-xs);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;
    margin-left: auto;
}
.chat-copy-btn:hover { background: var(--color-gray-100); color: var(--color-gray-600); }
.chat-copy-btn .material-symbols-rounded { font-size: 14px; }

/* Markdown rendered content — KI-Antworten lesefreundlich */
.markdown-rendered h1, .markdown-rendered h2, .markdown-rendered h3,
.markdown-rendered h4, .markdown-rendered h5, .markdown-rendered h6 {
    margin: 22px 0 10px;
    line-height: 1.3;
    font-weight: 700;
    color: #0f172a;
}
.markdown-rendered h1 { font-size: var(--d-fs-xl); padding-bottom: 6px; border-bottom: 1px solid var(--color-gray-200); }
.markdown-rendered h2 { font-size: var(--d-fs-lg); }
.markdown-rendered h3 { font-size: var(--d-fs-base); color: var(--thoxan-900); }
.markdown-rendered h4 { font-size: var(--d-fs-base); color: #1e293b; }
.markdown-rendered > *:first-child { margin-top: 0; }

.markdown-rendered p { margin: 10px 0; }
.markdown-rendered p:first-child { margin-top: 0; }
.markdown-rendered p:last-child { margin-bottom: 0; }

.markdown-rendered strong { font-weight: 700; color: #0f172a; }
.markdown-rendered em { color: #1e293b; }

.markdown-rendered ul, .markdown-rendered ol {
    margin: 10px 0;
    padding-left: 22px;
}
.markdown-rendered li { margin: 6px 0; line-height: 1.65; }
.markdown-rendered li > p { margin: 4px 0; }
.markdown-rendered ul ul, .markdown-rendered ol ol,
.markdown-rendered ul ol, .markdown-rendered ol ul {
    margin: 4px 0;
}
.markdown-rendered ul li::marker { color: var(--thoxan-700); }
.markdown-rendered ol li::marker { color: var(--thoxan-700); font-weight: 600; }

.markdown-rendered blockquote {
    border-left: 3px solid var(--thoxan-700);
    background: #f5f9fd;
    padding: 10px 14px;
    color: #334155;
    margin: 12px 0;
    border-radius: 0 8px 8px 0;
}
.markdown-rendered blockquote p { margin: 4px 0; }
.markdown-rendered table {
    border-collapse: collapse;
    margin: 12px 0;
    font-size: var(--d-fs-sm);
    width: 100%;
}
.markdown-rendered th, .markdown-rendered td {
    border: 1px solid var(--color-gray-200);
    padding: 6px 10px;
    text-align: left;
}
.markdown-rendered th { background: var(--color-gray-50); font-weight: 600; }
.markdown-rendered a { color: var(--color-info); text-decoration: underline; }
.markdown-rendered hr { border: none; border-top: 1px solid var(--color-gray-200); margin: 16px 0; }

/* Code blocks */
.markdown-rendered pre {
    position: relative;
    margin: 12px 0;
    border-radius: var(--radius-sm);
    overflow: hidden;
}
.markdown-rendered pre code {
    display: block;
    padding: 14px 16px;
    background: var(--color-gray-900);
    color: #e5e5e5;
    font-family: var(--font-mono);
    font-size: var(--d-fs-sm);
    line-height: 1.5;
    overflow-x: auto;
}
.markdown-rendered code {
    font-family: var(--font-mono);
    font-size: var(--d-fs-sm);
    background: var(--color-gray-100);
    padding: 1px 5px;
    border-radius: 3px;
}
.markdown-rendered pre code {
    background: var(--color-gray-900);
    padding: 14px 16px;
}

.code-block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 12px;
    background: var(--color-gray-800);
    font-size: var(--d-fs-xs);
    color: var(--color-gray-400);
}
.code-block-copy {
    background: none;
    border: none;
    color: var(--color-gray-400);
    font-size: var(--d-fs-xs);
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.code-block-copy:hover { background: var(--color-gray-700); color: white; }

/* Streaming cursor */
.streaming-cursor::after {
    content: '▊';
    animation: blink 0.8s infinite;
    color: var(--color-gray-400);
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* Web Search */
@keyframes spin {
    to { transform: rotate(360deg); }
}
.spin {
    display: inline-block;
    animation: spin 1s linear infinite;
}
.web-search-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
}
.web-sources {
    margin-top: 12px;
    padding: 10px 14px;
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
}
.web-sources-label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--color-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 8px;
}
.web-sources-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.web-source-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 6px 10px;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    text-decoration: none;
    font-size: var(--d-fs-sm);
    max-width: 220px;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.web-source-item:hover {
    border-color: var(--color-primary);
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.web-source-domain {
    color: var(--color-gray-400);
    font-size: var(--d-fs-xs);
}
.web-source-title {
    color: var(--color-gray-700);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ==========================================
   Dual Mode
   ========================================== */
.dual-model-select {
    border: 2px solid var(--color-primary);
    background: rgba(59,130,246,0.04);
}
.chat-msg-dual {
    max-width: 960px;
    width: 100%;
    margin: 0 auto;
    padding: 20px 40px;
}
.dual-split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.dual-panel {
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    overflow: hidden;
    min-width: 0;
}
.dual-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--color-gray-50);
    border-bottom: 1px solid var(--color-gray-200);
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--color-gray-600);
}
.dual-panel-header .chat-copy-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    color: var(--color-gray-400);
    display: flex;
}
.dual-panel-header .chat-copy-btn:hover { color: var(--color-gray-600); }
.dual-panel-header .chat-copy-btn .material-symbols-rounded { font-size: 14px; }
.dual-panel-body {
    padding: 14px;
    font-size: var(--d-fs-sm);
    line-height: 1.7;
    overflow-x: auto;
}
.dual-panel-body p:first-child { margin-top: 0; }
.dual-panel-body p:last-child { margin-bottom: 0; }
.dual-comparison {
    margin-top: 12px;
    border: 1px solid rgba(102,126,234,0.25);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.dual-comparison .dual-panel-header {
    background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08));
    border-bottom-color: rgba(102,126,234,0.2);
}
.dual-comparison .dual-panel-body {
    background: rgba(102,126,234,0.02);
}
.dual-compare-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 12px auto 0;
}
.dual-compare-row select {
    padding: 5px 10px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-md);
    background: white;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
    cursor: pointer;
}
.dual-compare-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-md);
    background: white;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-600);
    transition: all var(--transition-fast);
}
.dual-compare-btn:hover {
    background: var(--color-gray-50);
    border-color: var(--color-gray-400);
}
@media (max-width: 768px) {
    .dual-split { grid-template-columns: 1fr; }
    .chat-msg-dual { padding: 12px 16px; }
}

/* ==========================================
   Input Area
   ========================================== */
.chat-input-area {
    padding: var(--d-gutter);
    background: white;
    border-top: 1px solid var(--slate-100);
}

/* Read-only-Banner: fremder Chat ohne Schreibzugriff */
.chat-readonly-banner {
    max-width: 960px; margin: 0 auto;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 12px 16px; border-radius: 12px;
    background: var(--amber-50, #fffbeb); border: 1px solid var(--amber-200, #fde68a);
    color: var(--slate-700);
}
.chat-readonly-banner .cr-icon { color: var(--amber-600, #d97706); flex: 0 0 auto; }
.chat-readonly-banner .cr-text { flex: 1; min-width: 200px; font-size: var(--d-fs-sm); line-height: 1.4; }
.chat-readonly-banner .cr-text strong { color: var(--slate-900); }
.chat-readonly-banner .cr-btn {
    flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px;
    background: var(--thoxan-700); color: #fff; border: none; border-radius: 8px;
    padding: 8px 14px; cursor: pointer; font-size: var(--d-fs-sm); font-family: inherit;
}
.chat-readonly-banner .cr-btn:hover { background: var(--thoxan-800, #003a6b); }
.chat-readonly-banner .cr-btn:disabled { background: var(--emerald-600, #059669); cursor: default; opacity: 0.95; }
.chat-readonly-banner .cr-btn .material-symbols-rounded { font-size: 16px; }
/* Admin-Override: „Chat übernehmen" — bewusst andere, dezent-dunkle Optik */
.chat-readonly-banner .cr-btn-takeover { background: var(--slate-700, #334155); }
.chat-readonly-banner .cr-btn-takeover:hover { background: var(--slate-900, #0f172a); }
.chat-readonly-banner .cr-btn-takeover:disabled { background: var(--slate-400, #94a3b8); }
/* Hinweis ueber dem Eingabefeld: fremder Chat (mit Schreibzugriff) */
.chat-foreign-write-hint {
    max-width: 960px; margin: 0 auto 8px; padding: 6px 12px; border-radius: 8px;
    display: flex; align-items: center; gap: 6px;
    background: var(--amber-50, #fffbeb); border: 1px solid var(--amber-200, #fde68a);
    color: var(--amber-700, #b45309); font-size: var(--d-fs-xs); line-height: 1.3;
}
.chat-foreign-write-hint .material-symbols-rounded { font-size: 15px; flex: 0 0 auto; }
.chat-foreign-write-hint strong { color: var(--amber-800, #92400e); }
/* „Chat von X"-Badge im Header */
.chat-foreign-badge { background: var(--amber-50, #fffbeb) !important; color: var(--amber-700, #b45309) !important; border: 1px solid var(--amber-200, #fde68a); }
.chat-requests-badge { background: var(--rose-600, #e11d48) !important; color: #fff !important; cursor: pointer; }

.chat-input-wrapper {
    max-width: 960px;
    margin: 0 auto;
    width: 100%;
}

.chat-input-area .chat-input-box {
    width: 100%;
}

.chat-input-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.chat-input-attachment-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-200);
    border-radius: 20px;
    font-size: var(--d-fs-xs);
    color: var(--color-gray-600);
}
.chat-input-attachment-chip button {
    background: none;
    border: none;
    padding: 0;
    color: var(--color-gray-400);
    cursor: pointer;
    display: flex;
    line-height: 1;
}
.chat-input-attachment-chip button:hover { color: var(--color-error); }
.chat-input-attachment-chip .material-symbols-rounded { font-size: 14px; }

.chat-input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    border: 1px solid var(--color-gray-200);
    border-radius: 20px;
    padding: 16px 20px;
    background: var(--color-gray-50);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.chat-input-box:focus-within {
    border-color: var(--color-gray-400);
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.chat-input-box textarea {
    flex: 1;
    border: none;
    outline: none;
    resize: none;
    font-size: var(--d-fs-sm);
    line-height: 1.6;
    max-height: 240px;
    min-height: 56px;
    padding: 4px 0;
    background: transparent;
    color: var(--color-gray-800);
}
.chat-input-box textarea::placeholder {
    color: var(--color-gray-400);
}

.chat-input-btn {
    background: none;
    border: none;
    padding: 6px;
    border-radius: 50%;
    color: var(--color-gray-400);
    display: flex;
    cursor: pointer;
    flex-shrink: 0;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.chat-input-btn:hover { color: var(--color-gray-600); background: var(--color-gray-200); }
.chat-input-btn .material-symbols-rounded { font-size: 22px; }

.chat-send-btn {
    background: var(--color-black);
    border: none;
    padding: 8px;
    border-radius: 50%;
    color: white;
    display: flex;
    cursor: pointer;
    flex-shrink: 0;
    transition: background var(--transition-fast), opacity var(--transition-fast);
}
.chat-send-btn:hover { background: var(--color-gray-800); }
.chat-send-btn:disabled { opacity: 0.3; cursor: default; }
.chat-send-btn .material-symbols-rounded { font-size: 20px; }

.chat-input-hint {
    text-align: center;
    font-size: var(--d-fs-xs);
    color: var(--color-gray-300);
    margin-top: 6px;
}

/* Model select in header */
.chat-model-select {
    font-size: var(--d-fs-xs);
    padding: 3px 8px;
    border: 1px solid var(--color-gray-200);
    border-radius: 10px;
    background: var(--color-gray-50);
    color: var(--color-gray-500);
    outline: none;
    cursor: pointer;
    max-width: 180px;
}
.chat-model-select:hover {
    border-color: var(--color-gray-300);
    color: var(--color-gray-700);
}
/* Welcome screen model select */
.chat-welcome-model {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 14px;
    padding: 8px 12px;
    flex-wrap: wrap;
    justify-content: center;
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: 999px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.chat-welcome-model .chat-model-select {
    font-size: var(--d-fs-sm);
    padding: 6px 12px;
    border-radius: 999px;
    background: #fff;
    color: var(--color-gray-700);
    border-color: var(--color-gray-200);
    max-width: 240px;
    font-weight: 500;
}
.chat-welcome-model .chat-model-select:hover {
    border-color: var(--thoxan-700);
    color: var(--thoxan-900);
}
.chat-welcome-model .chat-artifact-toggle {
    margin-left: 0;
    padding: 4px 10px 4px 4px;
    border-radius: 999px;
    transition: background 0.15s;
}
.chat-welcome-model .chat-artifact-toggle:hover {
    background: rgba(0, 76, 155, 0.08);
    color: var(--color-gray-700);
}
.chat-welcome-model .chat-artifact-toggle:has(input:checked) {
    background: #e6f0fa;
    color: var(--thoxan-900);
}
.chat-welcome-model > .chat-welcome-model-divider {
    width: 1px;
    height: 18px;
    background: var(--color-gray-200);
    margin: 0 2px;
}

/* ==========================================
   Welcome Screen
   ========================================== */
.chat-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    gap: 24px;
}
.chat-welcome-icon .material-symbols-rounded {
    font-size: 64px;
    color: var(--color-gray-300);
}
.chat-welcome h2 {
    font-size: var(--d-fs-2xl);
    font-weight: 600;
    color: var(--color-gray-800);
}
.chat-welcome-input {
    width: 100%;
    max-width: 600px;
}

/* ==========================================
   Artifact Panel (Right Drawer)
   ========================================== */
.artifact-panel {
    width: 320px;
    min-width: 320px;
    border-left: 1px solid var(--color-gray-200);
    display: flex;
    flex-direction: column;
    background: var(--color-gray-50);
    overflow-y: auto;
}
.artifact-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-gray-200);
    flex-shrink: 0;
}
.artifact-panel-header h4 { margin: 0; font-size: var(--d-fs-sm); font-weight: 600; }
.artifact-panel-section {
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-gray-100);
}
.artifact-panel-label {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-gray-400);
    margin-bottom: 8px;
}
.artifact-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: 6px;
    margin-bottom: 4px;
    font-size: var(--d-fs-sm);
}
.artifact-chip-type {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    text-transform: uppercase;
    background: #cfe2f5;
    color: var(--thoxan-900);
    flex-shrink: 0;
}
.artifact-chip-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.artifact-chip button {
    background: none; border: none; cursor: pointer; padding: 2px;
    color: var(--color-gray-400); border-radius: 3px; display: flex; align-items: center;
}
.artifact-chip button:hover { background: var(--color-gray-100); color: var(--color-gray-600); }
.artifact-search-input {
    width: 100%; padding: 7px 10px;
    border: 1px solid var(--color-gray-200); border-radius: 6px;
    font-size: var(--d-fs-sm); outline: none; margin-bottom: 8px; background: white;
}
.artifact-search-input:focus { border-color: var(--color-gray-400); }
.artifact-type-filter {
    width: 100%; padding: 6px 8px;
    border: 1px solid var(--color-gray-200); border-radius: 6px;
    font-size: var(--d-fs-sm); outline: none; margin-bottom: 8px; background: white;
}
.artifact-ns-select {
    width: 100%; padding: 6px 8px;
    border: 1px solid var(--color-gray-200); border-radius: 6px;
    font-size: var(--d-fs-sm); outline: none; margin-bottom: 8px; background: white;
}
.artifact-search-item {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 8px; border-radius: 6px; cursor: default; margin-bottom: 2px;
}
.artifact-search-item:hover { background: white; }
.artifact-search-item .artifact-search-info { flex: 1; min-width: 0; }
.artifact-search-item .artifact-search-name { font-size: var(--d-fs-sm); font-weight: 500; }
.artifact-search-item .artifact-search-preview {
    font-size: var(--d-fs-xs); color: var(--color-gray-400); margin-top: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.artifact-search-item button {
    background: none; border: 1px solid var(--color-gray-300); border-radius: 4px;
    cursor: pointer; padding: 2px 8px; font-size: var(--d-fs-xs); color: var(--color-gray-600);
    flex-shrink: 0; margin-top: 2px;
}
.artifact-search-item button:hover { background: var(--color-gray-100); }
.artifact-search-item.attached button { display: none; }
.artifact-search-item.attached::after {
    content: 'Angehaengt'; font-size: var(--d-fs-xs); color: var(--color-gray-400);
    padding: 3px 6px; flex-shrink: 0;
}
.chat-badge-artifact {
    background: #fef3c7; color: #92400e;
    cursor: pointer; display: inline-flex; align-items: center; gap: 3px;
}
.artifact-form-label {
    display: block; font-size: var(--d-fs-sm); font-weight: 500;
    color: var(--color-gray-600); margin-bottom: 4px;
}

/* ==========================================
   Share Modal
   ========================================== */
.chat-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.chat-modal-overlay.active { display: flex; }

.chat-modal {
    background: white;
    border-radius: var(--radius-md);
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    width: 440px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.chat-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--color-gray-200);
}
.chat-modal-header h3 { font-size: var(--d-fs-base); margin: 0; }
.chat-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    color: var(--color-gray-400);
    cursor: pointer;
    display: flex;
}
.chat-modal-close:hover { color: var(--color-gray-700); }

.chat-modal-body {
    padding: 16px 20px;
    overflow-y: auto;
    flex: 1;
}

.chat-share-search {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    font-size: var(--d-fs-sm);
    margin-bottom: 12px;
    outline: none;
}
.chat-share-search:focus { border-color: var(--color-gray-400); }

.chat-share-results {
    max-height: 150px;
    overflow-y: auto;
    margin-bottom: 16px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    display: none;
}
.chat-share-results.has-results { display: block; }
.chat-share-result-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    cursor: pointer;
    font-size: var(--d-fs-sm);
    border-bottom: 1px solid var(--color-gray-100);
}
.chat-share-result-item:last-child { border-bottom: none; }
.chat-share-result-item:hover { background: var(--color-gray-50); }
.chat-share-result-name { font-weight: 500; }
.chat-share-result-email { color: var(--color-gray-400); font-size: var(--d-fs-xs); }

.chat-share-list-label {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--color-gray-500);
    margin-bottom: 8px;
}

.chat-share-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.chat-share-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
}
.chat-share-item-info { flex: 1; }
.chat-share-item-name { font-size: var(--d-fs-sm); font-weight: 500; }
.chat-share-item-email { font-size: var(--d-fs-xs); color: var(--color-gray-400); }

.chat-share-item select {
    font-size: var(--d-fs-xs);
    padding: 2px 6px;
    border: 1px solid var(--color-gray-200);
    border-radius: 4px;
    background: white;
    cursor: pointer;
}

.chat-share-item .chat-share-remove {
    background: none;
    border: none;
    color: var(--color-gray-400);
    cursor: pointer;
    padding: 2px;
    display: flex;
}
.chat-share-item .chat-share-remove:hover { color: var(--color-error); }
.chat-share-item .chat-share-remove .material-symbols-rounded { font-size: 18px; }
/* Schreibrechte-Dialog: Write-Badge + „Für alle freigeben"-Schalter */
.chat-share-badge-write {
    font-size: var(--d-fs-xs); font-weight: 600; color: var(--thoxan-700, #0050a0);
    background: rgba(0,76,155,0.08); border-radius: 6px; padding: 3px 8px; white-space: nowrap;
}
.chat-share-openall {
    display: flex; align-items: center; gap: 8px; margin-top: 12px; padding: 10px 12px;
    border: 1px dashed var(--color-gray-300, #cbd5e1); border-radius: 10px;
    cursor: pointer; font-size: var(--d-fs-sm); color: var(--color-gray-700, #334155);
}
.chat-share-openall:hover { border-color: var(--thoxan-700, #0050a0); }
.chat-share-openall.is-on { background: rgba(0,76,155,0.05); border-color: var(--thoxan-700, #0050a0); border-style: solid; }
.chat-share-openall input[type=checkbox] { width: 16px; height: 16px; }
.chat-share-openall .material-symbols-rounded { font-size: 18px; color: var(--thoxan-700, #0050a0); }
.chat-share-openall input:disabled ~ span { opacity: 0.7; }

.chat-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--color-gray-200);
    text-align: right;
}

/* ==========================================
   Feedback
   ========================================== */
.feedback-thumbs {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 16px;
}
.feedback-thumb {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 16px 28px;
    border: 2px solid var(--color-gray-200);
    border-radius: 12px;
    background: var(--color-white);
    cursor: pointer;
    transition: all 0.2s;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.feedback-thumb .material-symbols-rounded { font-size: 28px; }
.feedback-thumb:hover { border-color: var(--color-gray-400); background: var(--color-gray-50); }
.feedback-thumb.active.positive { border-color: var(--emerald-500); color: var(--emerald-500); background: #f0fdf4; }
.feedback-thumb.active.negative { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
#btn-feedback.has-positive { color: var(--emerald-500); }
#btn-feedback.has-negative { color: #ef4444; }

/* ==========================================
   Context Menu (3-dot)
   ========================================== */
.chat-context-menu {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    min-width: 180px;
    z-index: 999;
    padding: 4px 0;
}
.chat-context-menu.active { display: block; }
.chat-context-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    font-size: var(--d-fs-sm);
    color: var(--color-gray-700);
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}
.chat-context-menu-item:hover { background: var(--color-gray-100); }
.chat-context-menu-item .material-symbols-rounded { font-size: 16px; color: var(--color-gray-500); }
.chat-context-menu-item.danger { color: var(--color-error); }
.chat-context-menu-item.danger .material-symbols-rounded { color: var(--color-error); }
.chat-context-menu-divider { height: 1px; background: var(--color-gray-200); margin: 4px 0; }
</style>

<div class="chat-container">
    <!-- Sidebar -->
    <div class="chat-sidebar" id="chat-sidebar">
        <!-- Collapsed bar (visible when sidebar is collapsed) -->
        <div class="chat-sidebar-collapsed-bar">
            <button class="thx-icon-btn" onclick="toggleSidebar()" title="Sidebar einblenden">
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
            <button class="thx-icon-btn" onclick="newChat()" title="Neuer Chat">
                <span class="material-symbols-rounded">edit_square</span>
            </button>
            <div class="chat-collapsed-divider"></div>
            <!-- Kunden-Kuerzel als Schnellnavigation (JS-gerendert) -->
            <div id="chat-collapsed-list" style="display:flex;flex-direction:column;gap:4px;align-items:center;width:100%;"></div>
        </div>
        <div class="chat-sidebar-header">
            <div class="chat-sidebar-toolbar">
                <button class="thx-icon-btn" onclick="toggleSidebar()" title="Sidebar einklappen">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
                <h2 class="thx-page-title" style="font-size:var(--d-fs-base);margin:0;">Chats</h2>
                <div class="chat-sidebar-toolbar-actions">
                    <button class="thx-icon-btn" onclick="openTrash()" title="Papierkorb" id="trash-btn">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                    <button class="thx-icon-btn" onclick="newChat()" title="Neuer Chat">
                        <span class="material-symbols-rounded">edit_square</span>
                    </button>
                </div>
            </div>
            <div class="chat-sidebar-search-wrap">
                <span class="material-symbols-rounded chat-search-icon">search</span>
                <input type="text" class="thx-input chat-search" placeholder="Suchen..." oninput="handleSearch(this.value)">
            </div>
        </div>
        <div class="chat-sidebar-content" id="sidebar-content">
            <!-- Rendered by JS -->
        </div>
    </div>

    <!-- Main -->
    <div class="chat-main" id="chat-main">
        <!-- Welcome screen (default) -->
        <div class="chat-welcome" id="welcome-screen">
            <div class="chat-welcome-icon">
                <span class="material-symbols-rounded">chat</span>
            </div>
            <h2 id="welcome-title">Wie kann ich helfen?</h2>
            <div id="welcome-context-banner"></div>

            <!-- Kontext-Picker (sichtbar solange kein Kontext gewählt) -->
            <div id="welcome-context-picker" class="welcome-context-picker" style="display:none;">
                <p class="welcome-context-hint">In welchem Kontext arbeitest du gerade?</p>
                <div class="welcome-context-grid">
                    <button type="button" class="welcome-ctx-card" onclick="selectContext('private')">
                        <span class="material-symbols-rounded" style="color:#5b8fd1;">lock</span>
                        <strong>Privat</strong>
                        <small>Nur für dich sichtbar</small>
                    </button>
                    <button type="button" class="welcome-ctx-card" onclick="selectContext('projectwide')">
                        <span class="material-symbols-rounded" style="color:#10b981;">public</span>
                        <strong>Projektübergreifend</strong>
                        <small>Allgemeine Themen, kein Kunde</small>
                    </button>
                    <button type="button" class="welcome-ctx-card" onclick="welcomeOpenCustomerPicker()">
                        <span class="material-symbols-rounded" style="color:var(--thoxan-700);">business</span>
                        <strong>Kunde wählen</strong>
                        <small id="welcome-ctx-customer-count">…</small>
                    </button>
                </div>
                <div id="welcome-customer-picker" class="welcome-customer-picker" style="display:none;">
                    <input type="text" id="welcome-customer-search" placeholder="Kunde suchen…" oninput="welcomeFilterCustomers()">
                    <div id="welcome-customer-list"></div>
                </div>
            </div>

            <div id="welcome-input-wrap" class="chat-welcome-input">
                <div class="chat-welcome-model">
                    <select class="thx-select" id="welcome-model-select" onchange="saveSettingsToLocalStorage()">
                        <option value="auto">&#9889; Automatik</option>
                        <?php foreach ($models as $m): ?>
                            <option value="<?= htmlspecialchars($m['model_id']) ?>" <?= $m['model_id'] === $defaultModel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="chat-artifact-toggle" title="Web-Suche — die KI sucht bei Bedarf im Internet nach aktuellen Informationen">
                        <input type="checkbox" id="welcome-use-web-search" onchange="chatState.useWebSearch=this.checked;syncWebSearchCheckbox();saveSettingsToLocalStorage()">
                        <span class="toggle-switch"></span>
                        <span class="material-symbols-rounded" style="font-size:16px">travel_explore</span> Web
                    </label>
                    <label class="chat-artifact-toggle" title="Wissen — Hybrid-RAG über deine hinterlegten Kundendaten (Dense+Sparse+Graph)">
                        <input type="checkbox" id="welcome-use-knowledge" checked onchange="chatState.useKnowledge=this.checked;syncKnowledgeCheckbox();saveSettingsToLocalStorage()">
                        <span class="toggle-switch"></span>
                        <span class="material-symbols-rounded" style="font-size:16px">library_books</span> Wissen
                    </label>
                    <label class="chat-artifact-toggle" title="Dual Mode — zwei Modelle gleichzeitig befragen und Antworten vergleichen">
                        <input type="checkbox" id="welcome-use-dual" onchange="toggleDualMode(this.checked);saveSettingsToLocalStorage()">
                        <span class="toggle-switch"></span>
                        <span class="material-symbols-rounded" style="font-size:16px">compare</span> Dual
                    </label>
                    <select class="thx-select dual-model-select" id="welcome-dual-model-select" style="display:none" onchange="syncDualModelB(this.value)">
                        <?php foreach ($models as $m): ?>
                            <option value="<?= htmlspecialchars($m['model_id']) ?>" <?= strpos($m['model_id'], 'claude') !== false ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="chat-input-attachments" id="welcome-attachments"></div>
                <div class="chat-input-box">
                    <button class="thx-icon-btn" onclick="triggerUpload()" title="Datei anhaengen">
                        <span class="material-symbols-rounded">attach_file</span>
                    </button>
                    <button class="thx-icon-btn" onclick="openSnippetPicker('welcome-textarea')" title="Textbaustein einfuegen">
                        <span class="material-symbols-rounded">playlist_add</span>
                    </button>
                    <textarea id="welcome-textarea" rows="3" placeholder="Nachricht eingeben..."
                        onkeydown="handleWelcomeKey(event)" oninput="autoResize(this); scheduleDraftSave()"></textarea>
                    <button class="thx-btn thx-btn-primary" onclick="sendWelcomeMessage()" id="welcome-send-btn">
                        <span class="material-symbols-rounded">arrow_upward</span>
                    </button>
                </div>
                <span class="chat-input-hint">Enter = Senden, Shift+Enter = Neue Zeile</span>
            </div>
        </div>

        <!-- Active chat view (hidden by default) -->
        <div id="chat-view" style="display:none; flex-direction:row; height:100%;">
            <!-- Main chat column -->
            <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
                <!-- Header -->
                <div class="thx-page-header chat-header" id="chat-header">
                    <!-- Zeile 1: Titel + Kunden/Status-Pills -->
                    <div class="chat-header-row chat-header-row-top">
                        <h1 class="thx-page-title" id="chat-title" onclick="startTitleEdit()" title="Klick zum Umbenennen"></h1>
                        <div id="chat-badges" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;"></div>
                    </div>
                    <!-- Zeile 2: Modell + Toggles + Ins Wissen + ⋯ -->
                    <div class="chat-header-row chat-header-row-actions thx-page-actions">
                        <select class="thx-select" id="chat-model-select" onchange="saveSettingsToLocalStorage()">
                            <option value="auto">&#9889; Automatik</option>
                            <?php foreach ($models as $m): ?>
                                <option value="<?= htmlspecialchars($m['model_id']) ?>" <?= $m['model_id'] === $defaultModel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="chat-artifact-toggle thx-btn thx-btn-secondary thx-btn-small" title="Web-Suche — die KI sucht bei Bedarf im Internet nach aktuellen Informationen">
                            <input type="checkbox" id="chat-use-web-search" onchange="chatState.useWebSearch=this.checked;syncWebSearchCheckbox();saveSettingsToLocalStorage()">
                            <span class="material-symbols-rounded" style="font-size:14px;">travel_explore</span> Web
                        </label>
                        <label class="chat-artifact-toggle thx-btn thx-btn-secondary thx-btn-small" title="Wissen — Hybrid-RAG über deine hinterlegten Kundendaten">
                            <input type="checkbox" id="chat-use-knowledge" checked onchange="chatState.useKnowledge=this.checked;syncKnowledgeCheckbox();saveSettingsToLocalStorage()">
                            <span class="material-symbols-rounded" style="font-size:14px;">library_books</span> Wissen
                        </label>
                        <label class="chat-artifact-toggle thx-btn thx-btn-secondary thx-btn-small" title="Dual Mode — zwei Modelle gleichzeitig befragen und Antworten vergleichen">
                            <input type="checkbox" id="chat-use-dual" onchange="toggleDualMode(this.checked);saveSettingsToLocalStorage()">
                            <span class="material-symbols-rounded" style="font-size:14px;">compare</span> Dual
                        </label>
                        <select class="thx-select dual-model-select" id="chat-dual-model-select" style="display:none" onchange="syncDualModelB(this.value)">
                            <?php foreach ($models as $m): ?>
                                <option value="<?= htmlspecialchars($m['model_id']) ?>" <?= strpos($m['model_id'], 'claude') !== false ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="event.stopPropagation();openShareDialog('conversation')" title="Diesen Chat mit Teammitgliedern teilen" id="btn-share-chat" style="display:none;">
                            <span class="material-symbols-rounded" style="font-size:14px;">share</span> Teilen<span id="btn-share-badge" style="display:none;margin-left:4px;background:var(--rose-600,#e11d48);color:#fff;border-radius:9px;padding:0 6px;font-size:11px;font-weight:700;"></span>
                        </button>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="event.stopPropagation();openTransferToKnowledge()" title="Diesen Chat in die Wissensdatenbank uebernehmen" id="btn-transfer-knowledge">
                            <span class="material-symbols-rounded" style="font-size:14px;">library_add</span> Ins Wissen
                        </button>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="event.stopPropagation();showConvMenu(event)" title="Mehr">
                            <span class="material-symbols-rounded" style="font-size:16px;">more_horiz</span>
                        </button>
                    </div>
                </div>

                <!-- Messages + TOC links -->
                <div class="chat-messages-wrap">
                    <!-- TOC: pro User-Prompt ein anklickbarer Strich -->
                    <nav class="chat-toc" id="chat-toc" aria-label="Prompt-Verzeichnis"></nav>
                    <div class="chat-messages" id="chat-messages"></div>
                </div>

                <!-- Input -->
                <div class="chat-input-area" id="chat-input-area">
                    <!-- Hinweis: Du schreibst in einem fremden Chat (mit Schreibzugriff) -->
                    <div class="chat-foreign-write-hint" id="chat-foreign-write-hint" style="display:none;"></div>
                    <div class="chat-input-wrapper" id="chat-input-wrapper">
                        <div class="chat-input-attachments" id="chat-attachments"></div>
                        <div class="chat-input-box">
                            <button class="thx-icon-btn" onclick="triggerUpload()" title="Datei anhaengen">
                                <span class="material-symbols-rounded">attach_file</span>
                            </button>
                            <button class="thx-icon-btn" onclick="openSnippetPicker('chat-textarea')" title="Textbaustein einfuegen">
                                <span class="material-symbols-rounded">playlist_add</span>
                            </button>
                            <textarea id="chat-textarea" rows="2" placeholder="Nachricht eingeben..."
                                onkeydown="handleChatKey(event)" oninput="autoResize(this); scheduleDraftSave()"></textarea>
                            <button class="thx-btn thx-btn-primary" onclick="sendMessage()" id="chat-send-btn">
                                <span class="material-symbols-rounded">arrow_upward</span>
                            </button>
                        </div>
                    </div>
                    <!-- Read-only-Hinweis fuer fremde Chats ohne Schreibzugriff -->
                    <div class="chat-readonly-banner" id="chat-readonly-banner" style="display:none;"></div>
                </div>
            </div>

            <!-- Artifact Panel (right drawer) -->
        </div>
    </div>
</div>

<!-- Nachrichten-Info-Panel (rechtes Drawer: Prompt, Tokens, Modell, Wissen) -->
<div id="msg-info-panel" class="msg-info-panel" style="display:none;">
    <div class="msg-info-header">
        <h3 style="margin:0;font-size:var(--d-fs-base);">Antwort-Details</h3>
        <button onclick="closeMessageInfo()" class="thx-icon-btn" title="Schließen"><span class="material-symbols-rounded">close</span></button>
    </div>
    <div id="msg-info-body" class="msg-info-body"></div>
</div>
<style>
.msg-info-panel {
    position: fixed; top: 0; right: 0; height: 100vh; width: 440px; max-width: 92vw;
    background: var(--color-bg-primary, #fff); border-left: 1px solid var(--color-border, #e2e8f0);
    box-shadow: -8px 0 30px rgba(0,0,0,0.12); z-index: 1200; display: flex; flex-direction: column;
}
.msg-info-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.1rem; border-bottom: 1px solid var(--color-border, #e2e8f0); flex: 0 0 auto;
}
.msg-info-body { flex: 1 1 auto; overflow-y: auto; padding: 1rem 1.1rem; font-size: var(--d-fs-sm); }
.msg-info-section { margin-bottom: 1.25rem; }
.msg-info-section h4 { margin: 0 0 .5rem; font-size: var(--d-fs-sm); color: var(--color-text-secondary,#475569); text-transform: uppercase; letter-spacing: .03em; }
.msg-info-kv { display: grid; grid-template-columns: max-content 1fr; gap: .3rem .9rem; }
.msg-info-kv dt { color: var(--color-text-secondary,#64748b); }
.msg-info-kv dd { margin: 0; }
.msg-info-chunk { border: 1px solid var(--color-border,#e2e8f0); border-radius: 8px; padding: .5rem .65rem; margin-bottom: .5rem; }
.msg-info-chunk summary { cursor: pointer; display: flex; justify-content: space-between; gap: .5rem; font-weight: 600; }
.msg-info-chunk pre, .msg-info-prompt pre { white-space: pre-wrap; word-break: break-word; margin: .5rem 0 0; font-family: inherit; font-size: .92em; }
.msg-info-badge { padding: 1px 7px; border-radius: 999px; font-size: .82em; font-weight: 500; }
.msg-info-badge.crm { background:#fee2e2; color:#991b1b; }
.msg-info-badge.src { background:#e0e7ff; color:#3730a3; }
.msg-info-prompt pre { background: var(--color-gray-50,#f8fafc); padding: .75rem; border-radius: 8px; }
</style>

<!-- Snippet Picker Modal -->
<div id="snippet-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--color-bg-primary,#fff);border-radius:14px;width:90%;max-width:520px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border,#e2e8f0);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="margin:0;font-size: var(--d-fs-base);">Textbausteine</h3>
            <div style="display:flex;gap:0.5rem;">
                <button class="thx-btn thx-btn-primary" style="font-size: var(--d-fs-sm);padding:0.3rem 0.75rem;" onclick="openSnippetEditor()">+ Neu</button>
                <button onclick="closeSnippetPicker()" style="background:none;border:none;cursor:pointer;color:var(--color-text-secondary,#64748b);font-size: var(--d-fs-lg);">&times;</button>
            </div>
        </div>
        <div style="padding:0.75rem 1.25rem 0;">
            <input type="text" id="snippet-search" placeholder="Suchen..." oninput="searchSnippets(this.value)"
                style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);box-sizing:border-box;">
        </div>
        <div id="snippet-list" style="flex:1;overflow-y:auto;padding:0.75rem 1.25rem;"></div>
    </div>
</div>

<!-- Snippet Editor Modal -->
<div id="snippet-editor" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1001;align-items:center;justify-content:center;">
    <div style="background:var(--color-bg-primary,#fff);border-radius:14px;width:90%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,0.2);padding:1.5rem;">
        <h3 style="margin:0 0 1rem;" id="snippet-editor-title">Neuer Textbaustein</h3>
        <input type="hidden" id="snippet-edit-id">
        <div style="margin-bottom:0.75rem;">
            <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;">Titel</label>
            <input type="text" id="snippet-edit-name" placeholder="z.B. SEO Briefing"
                style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);box-sizing:border-box;">
        </div>
        <div style="margin-bottom:1rem;">
            <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;">Inhalt</label>
            <textarea id="snippet-edit-content" rows="6" placeholder="Der Text der eingefuegt wird..."
                style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);resize:vertical;font-family:inherit;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <button id="snippet-delete-btn" class="thx-btn thx-btn-danger" style="font-size: var(--d-fs-sm);display:none;" onclick="deleteSnippet()">Loeschen</button>
            <div style="display:flex;gap:0.5rem;margin-left:auto;">
                <button class="thx-btn thx-btn-secondary" style="font-size: var(--d-fs-sm);" onclick="closeSnippetEditor()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" style="font-size: var(--d-fs-sm);" onclick="saveSnippet()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Knowledge Save Modal (Inline im Chat) -->
<div id="kb-save-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1002;align-items:center;justify-content:center;">
    <div style="background:var(--color-bg-primary,#fff);border-radius:14px;width:92%;max-width:640px;max-height:90vh;overflow-y:auto;padding:1.75rem;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <!-- Phase 1: Textauswahl -->
        <div id="kb-save-phase-1">
            <h3 style="margin:0 0 1rem;font-size: var(--d-fs-lg);">Als Wissen speichern</h3>
            <p style="color:var(--color-text-secondary,#64748b);font-size: var(--d-fs-sm);margin:0 0 1rem;">
                Der komplette Nachrichten-Text wird unten angezeigt. Du kannst ihn bearbeiten oder kuerzen, bevor er analysiert wird.
            </p>
            <div style="margin-bottom:0.75rem;">
                <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Inhalt</label>
                <textarea id="kb-save-content" rows="10" style="width:100%;padding:0.6rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;box-sizing:border-box;resize:vertical;" placeholder="Text..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.5rem;">
                <button class="thx-btn thx-btn-secondary" onclick="closeKbSaveModal()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" id="kb-save-analyze-btn" onclick="kbAnalyze()">
                    <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">auto_awesome</span>
                    Analysieren
                </button>
            </div>
        </div>

        <!-- Phase 2: Vorschau + Bestaetigen -->
        <div id="kb-save-phase-2" style="display:none;">
            <h3 style="margin:0 0 1rem;font-size: var(--d-fs-lg);">KI-Vorschlag bestaetigen</h3>
            <div id="kb-save-stats" style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;"></div>
            <input type="hidden" id="kb-save-prepared-id">
            <div style="margin-bottom:0.75rem;">
                <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Titel</label>
                <input type="text" id="kb-save-title" style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);box-sizing:border-box;">
            </div>
            <div style="margin-bottom:0.75rem;">
                <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Beschreibung</label>
                <textarea id="kb-save-description" rows="2" style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                <div>
                    <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Kunde</label>
                    <select id="kb-save-customer" style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);">
                        <option value="">⚡ KI wählt Kunden</option>
                        <?php foreach ($customers ?? [] as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Kategorie</label>
                    <select id="kb-save-category" style="width:100%;padding:0.5rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;font-size: var(--d-fs-sm);">
                        <option>Produktinfo</option>
                        <option>Referenz</option>
                        <option>Prozess</option>
                        <option>FAQ</option>
                        <option>Notiz</option>
                        <option>Rechtlich</option>
                        <option>Technik</option>
                        <option>Marketing</option>
                        <option>Sonstige</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:0.75rem;">
                <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Tags</label>
                <div id="kb-save-tags-list" style="display:flex;flex-wrap:wrap;gap:0.3rem;padding:0.4rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;min-height:32px;"></div>
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size: var(--d-fs-sm);font-weight:500;display:block;margin-bottom:0.25rem;color:var(--color-text-secondary,#64748b);">Top Entities</label>
                <div id="kb-save-entities" style="display:flex;flex-wrap:wrap;gap:0.3rem;"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.5rem;">
                <button class="thx-btn thx-btn-secondary" onclick="closeKbSaveModal()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" id="kb-save-commit-btn" onclick="kbCommit()">
                    <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span>
                    Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden file input -->
<input type="file" id="file-upload-input" style="display:none" multiple
    onchange="handleFileSelect(event)">

<!-- Share Modal -->
<div class="thx-modal-backdrop" id="share-modal" style="display:none;">
    <div class="thx-modal">
        <div class="thx-modal-header">
            <h3 id="share-modal-title">Chat teilen</h3>
            <button class="thx-modal-close" onclick="closeShareModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="thx-modal-body">
            <!-- Offene Zugriffsanfragen (nur Chat, nur Owner) -->
            <div id="share-requests-wrap" style="display:none;">
                <div class="chat-share-list-label" style="color:var(--rose-700,#be123c);">Offene Zugriffsanfragen:</div>
                <div class="chat-share-list" id="share-requests"></div>
                <div style="height:14px;"></div>
            </div>
            <input type="text" class="chat-share-search" placeholder="Benutzer suchen..."
                oninput="debounceSearchUsers(this.value)" id="share-search-input">
            <div class="chat-share-results" id="share-results"></div>
            <div class="chat-share-list-label" id="share-list-label">Geteilte Personen:</div>
            <div class="chat-share-list" id="share-list"></div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="closeShareModal()">Schliessen</button>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div class="thx-contextmenu" id="context-menu" style="display:none;"></div>



<!-- Ins-Wissen-übertragen Modal -->
<div class="thx-modal-backdrop" id="tk-modal" style="display:none;">
    <div class="thx-modal" style="width:560px;max-width:92vw;">
        <div class="thx-modal-header">
            <h3>Ins Wissen übertragen</h3>
            <button class="thx-modal-close" onclick="closeTransferToKnowledge()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="thx-modal-body">
            <p style="color:#64748b;font-size: var(--d-fs-sm);margin:0 0 1rem;">
                Der Chat-Verlauf wird als Wissens-Dokument abgelegt — die KI nutzt es künftig automatisch bei passenden Fragen. Bei wiederholtem Übertragen wird der bestehende Eintrag aktualisiert.
            </p>
            <div class="tk-form-group">
                <label class="tk-label">Kunde <span style="color:var(--rose-600);">*</span></label>
                <select id="tk-customer" style="width:100%;padding:0.55rem 0.75rem;border:1px solid var(--color-gray-200);border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;">
                    <option value="">— Kunden wählen —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="tk-customer-hint" style="display:none;color:var(--emerald-600);font-size: var(--d-fs-xs);margin-top:0.25rem;"></small>
            </div>
            <div class="tk-form-group">
                <label class="tk-label">Titel <small style="font-weight:normal;color:#94a3b8;">(optional, KI schlägt sonst vor)</small></label>
                <input type="text" id="tk-title" placeholder="Wird aus dem Chat-Verlauf extrahiert wenn leer"
                       style="width:100%;padding:0.55rem 0.75rem;border:1px solid var(--color-gray-200);border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;">
            </div>
            <div class="tk-form-group">
                <label class="tk-label">Kontext / Hinweis <small style="font-weight:normal;color:#94a3b8;">(optional)</small></label>
                <textarea id="tk-context" rows="3" placeholder="z.B. „Wichtiges Briefing zum Projekt X" — hilft der KI bei Kategorisierung"
                          style="width:100%;padding:0.55rem 0.75rem;border:1px solid var(--color-gray-200);border-radius:8px;font-size: var(--d-fs-sm);font-family:inherit;resize:vertical;"></textarea>
            </div>
            <div id="tk-status" style="font-size: var(--d-fs-sm);margin-top:0.5rem;"></div>
        </div>
        <div class="thx-modal-footer" style="display:flex;justify-content:flex-end;gap:0.5rem;padding:0.75rem 1rem;border-top:1px solid var(--color-gray-200);">
            <button class="thx-btn thx-btn-secondary" onclick="closeTransferToKnowledge()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="submitTransferToKnowledge()" id="tk-submit-btn">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">library_add</span>
                Übertragen
            </button>
        </div>
    </div>
</div>
<style>
.tk-form-group { margin-bottom: 0.85rem; }
.tk-form-group:last-child { margin-bottom: 0; }
.tk-label { display:block; font-size: var(--d-fs-sm); font-weight:500; color:var(--color-gray-700); margin-bottom:0.3rem; }
.tk-label small { font-weight:normal; }

/* Stream-Stall-Banner (wenn KI-Antwort stockt) */
.stream-stall-banner {
    position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%) translateY(20px);
    display: flex; align-items: center; gap: 0.85rem;
    padding: 14px 18px; min-width: 380px; max-width: 92vw;
    background: #fff; border: 1px solid #fcd34d; border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    z-index: 500; opacity: 0; pointer-events: none;
    transition: opacity 0.2s, transform 0.25s;
}
.stream-stall-banner.open { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
.stream-stall-banner > .material-symbols-rounded {
    font-size: 26px; flex-shrink: 0;
    animation: stall-pulse 1.6s ease-in-out infinite;
}
@keyframes stall-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.12); } }
.stream-stall-banner strong { display: block; font-size: var(--d-fs-sm); color: #1e293b; }
.stream-stall-banner small { display: block; color: #64748b; font-size: var(--d-fs-sm); margin-top: 2px; }
.stream-stall-btn {
    border: 1px solid #e2e8f0; background: #fff; color: #475569;
    padding: 7px 12px; border-radius: 9px; cursor: pointer;
    font-size: var(--d-fs-sm); font-weight: 600; font-family: inherit;
    display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.15s;
}
.stream-stall-btn:hover { border-color: #cbd5e1; background: #f8fafc; }
.stream-stall-btn.primary {
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)); color: #fff; border-color: transparent;
}
.stream-stall-btn.primary:hover { background: linear-gradient(135deg, var(--thoxan-900), var(--thoxan-700)); }
.stream-stall-btn .material-symbols-rounded { font-size: 16px; }

/* Papierkorb-Modal */
.trash-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(6px);
    z-index: 410; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.trash-overlay.open { display: flex; animation: trash-fade 0.18s ease-out; }
@keyframes trash-fade { from { opacity: 0; } to { opacity: 1; } }

.trash-modal {
    width: 94vw; max-width: 780px; max-height: 88vh;
    background: #fff; border-radius: 16px; box-shadow: 0 24px 70px rgba(0, 0, 0, 0.3);
    display: flex; flex-direction: column; overflow: hidden;
    animation: trash-zoom 0.2s ease-out;
}
@keyframes trash-zoom { from { transform: scale(0.97); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.trash-head {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 1.1rem 1.3rem; border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
    flex-shrink: 0;
}
.trash-head-icon {
    width: 40px; height: 40px; border-radius: 11px;
    background: linear-gradient(135deg, var(--rose-600), #f87171); color: #fff;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.trash-head-icon .material-symbols-rounded { font-size: 22px; }
.trash-head-text { flex: 1; min-width: 0; }
.trash-head-text h3 { margin: 0; font-size: var(--d-fs-lg); color: #1e293b; font-weight: 700; line-height: 1.25; }
.trash-head-text small { display: block; color: #64748b; font-size: var(--d-fs-sm); margin-top: 3px; line-height: 1.45; }
.trash-close {
    width: 36px; height: 36px; border-radius: 9px;
    background: transparent; border: 0; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #94a3b8; flex-shrink: 0; transition: all 0.15s;
}
.trash-close:hover { background: #f1f5f9; color: #1e293b; }
.trash-close .material-symbols-rounded { font-size: 22px; }

.trash-body { flex: 1; overflow-y: auto; padding: 0.6rem 1rem 1rem; min-height: 0; }
.trash-item {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 14px 14px; border-bottom: 1px solid #f1f5f9;
    transition: background 0.1s;
}
.trash-item:hover { background: #fafbfc; }
.trash-item:last-child { border-bottom: 0; }
.trash-item-main { flex: 1; min-width: 0; }
.trash-item-title { font-weight: 600; font-size: var(--d-fs-sm); color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.trash-item-meta { font-size: var(--d-fs-sm); color: #94a3b8; margin-top: 4px; line-height: 1.5; }
.trash-item-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>

<!-- System Prompt Modal -->
<div class="thx-modal-backdrop" id="sysprompt-modal" style="display:none;">
    <div class="thx-modal" style="width:500px">
        <div class="thx-modal-header">
            <h3>System-Prompt</h3>
            <button class="thx-modal-close" onclick="closeSysPromptModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="thx-modal-body">
            <textarea id="sysprompt-textarea" style="width:100%;min-height:150px;padding:10px;border:1px solid var(--color-gray-200);border-radius:var(--radius-sm);font-size: var(--d-fs-sm);resize:vertical;outline:none" placeholder="Systemanweisung fuer den Chat..."></textarea>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-primary" onclick="saveSysPrompt()">Speichern</button>
        </div>
    </div>
</div>

<!-- Create Artifact Modal -->
<div class="thx-modal-backdrop" id="artifact-create-modal" style="display:none;">
    <div class="thx-modal" style="width:500px">
        <div class="thx-modal-header">
            <h3>Artefakt erstellen</h3>
            <button class="thx-modal-close" onclick="closeCreateArtifactModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="thx-modal-body">
            <div style="margin-bottom:12px">
                <label class="artifact-form-label">Typ</label>
                <select id="artifact-create-type" style="width:100%;padding:8px;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm)">
                    <option value="Regel">Regel</option>
                    <option value="Profil">Profil</option>
                    <option value="Wissen" selected>Wissen</option>
                    <option value="Autor">Autor</option>
                </select>
            </div>
            <div style="margin-bottom:12px">
                <label class="artifact-form-label">Name *</label>
                <input type="text" id="artifact-create-name" placeholder="Name des Artefakts..."
                    style="width:100%;padding:8px;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm)">
            </div>
            <div style="margin-bottom:12px">
                <label class="artifact-form-label">Tags (kommagetrennt)</label>
                <input type="text" id="artifact-create-tags" placeholder="tag1, tag2, ..."
                    style="width:100%;padding:8px;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm)">
            </div>
            <div style="margin-bottom:12px">
                <label class="artifact-form-label">Namespace (optional)</label>
                <input type="text" id="artifact-create-namespace" placeholder="Namespace..."
                    style="width:100%;padding:8px;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm)"
                    list="artifact-ns-datalist">
                <datalist id="artifact-ns-datalist"></datalist>
            </div>
            <div style="margin-bottom:12px">
                <label class="artifact-form-label">Inhalt *</label>
                <textarea id="artifact-create-content" rows="10"
                    style="width:100%;padding:8px;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm);resize:vertical;line-height:1.5"></textarea>
            </div>
        </div>
        <div class="thx-modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
            <button class="thx-btn thx-btn-secondary" onclick="closeCreateArtifactModal()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="saveNewArtifact()">Erstellen</button>
        </div>
    </div>
</div>

<div class="thx-modal-backdrop" id="feedback-modal" style="display:none;">
    <div class="thx-modal" style="max-width:440px">
        <div class="thx-modal-header">
            <h3>Chat bewerten</h3>
            <button class="thx-modal-close" onclick="closeChatFeedback()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="thx-modal-body">
            <div class="feedback-thumbs">
                <button class="feedback-thumb positive" id="fb-thumb-positive" onclick="selectFeedbackRating('positive')">
                    <span class="material-symbols-rounded">thumb_up</span>
                    <span>Gut</span>
                </button>
                <button class="feedback-thumb negative" id="fb-thumb-negative" onclick="selectFeedbackRating('negative')">
                    <span class="material-symbols-rounded">thumb_down</span>
                    <span>Schlecht</span>
                </button>
            </div>
            <textarea id="feedback-comment" placeholder="Was war gut oder schlecht? (optional)" rows="3"
                style="width:100%;padding:10px 12px;border:1px solid var(--color-gray-200);border-radius:8px;font-size: var(--d-fs-sm);resize:vertical;font-family:inherit"></textarea>
        </div>
        <div class="thx-modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
            <button class="thx-btn thx-btn-secondary" id="btn-delete-feedback" onclick="deleteChatFeedback()" style="display:none;margin-right:auto">Loeschen</button>
            <button class="thx-btn thx-btn-secondary" onclick="closeChatFeedback()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="submitChatFeedback()" id="btn-submit-feedback">Speichern</button>
        </div>
    </div>
</div>

<!-- marked + highlight.js werden LOKAL gehostet (kein esm.sh-CDN mehr).
     Grund: esm.sh fiel zeitweise aus, dann schlug der Import fehl, das
     Event "chatLibsReady" feuerte nie und das gesamte Antwort-Rendering
     kippte fuer die ganze Sitzung auf rohen Markdown-Text zurueck
     (Bugfix 09.06.2026). Lokal = deterministisch, kein externer Single
     Point of Failure. -->
<script src="/assets/js/vendor/marked.min.js"></script>
<script src="/assets/js/vendor/highlight.min.js"></script>
<script>
(function () {
    if (!window.marked || !window.hljs) {
        console.error('Markdown-Libs nicht geladen (marked/hljs fehlen)');
        return;
    }
    // Global verfuegbar machen (Rest des Codes nutzt markedLib/hljsLib)
    window.markedLib = window.marked;
    window.hljsLib = window.hljs;

    // marked konfigurieren — GFM, weiche Zeilenumbrueche, Listen-Tolerant
    window.marked.setOptions({
        highlight: function (code, lang) {
            try {
                if (lang && window.hljs.getLanguage(lang)) {
                    return window.hljs.highlight(code, { language: lang }).value;
                }
                return window.hljs.highlightAuto(code).value;
            } catch (e) { return code; }
        },
        breaks: true,
        gfm: true,
        pedantic: false,
    });

    // Signal fuer spaet hinzukommende Listener (belt-and-suspenders; da diese
    // Scripts synchron laden, ist libsReady unten ohnehin schon true).
    window.dispatchEvent(new Event('chatLibsReady'));
})();
</script>

<script>
// Deep-Link aus URL: PHP setzt deepLinkChatId, falls /chat/{id} aufgerufen wurde.
window.CHAT_DEEP_LINK_ID = <?= (int) ($deepLinkChatId ?? 0) ?>;
// ==========================================
// State
// ==========================================
const chatState = {
    projects: [],
    conversations: [],
    activeId: null,
    activeConv: null,
    messages: [],
    pendingAttachments: [],
    isStreaming: false,
    selectedModel: '<?= htmlspecialchars($defaultModel) ?>',
    isAdmin: <?= $_isAdmin ? 'true' : 'false' ?>,
    trashConversations: [],
    searchQuery: '',
    shareContext: null, // { type: 'conversation'|'project', id: int }
    editProjectId: null,
    editProjectColor: null,
    attachedArtifacts: [],
    artifactPanelOpen: false,
    artifactNamespaces: [],
    useArtifacts: false,
    useWebSearch: false,
    useKnowledge: true,
    useRules: false,
    dualMode: false,
    dualModelB: null,
    artifactSuggestions: [],
    currentFolderId: null,
    feedback: null,
    // Neue Kontext-Logik: aktiver Filter (Privat / Projektübergreifend / pro Kunde)
    currentContext: null,   // null = alle, oder {type:'private'|'projectwide'|'customer', customerId?}
    folderCounts: { private_count: 0, projectwide_count: 0, customers: [], tag_counts: {} },
    sidebarFilter: 'all', // 'all'|'private'|'projectwide'|'tag:<tagname>'
    // Pro Chat-ID die zuletzt via TOC angesprungene Message-ID merken. Beim erneuten
    // Oeffnen springt der Chat dort hin (sonst zum Ende). Bleibt nur im Memory.
    lastTocByChat: {},
    // Themen-Ordner pro Kontext (Variante C)
    contextFolders: [],     // Array {id, name, color, conversation_count}
    activeFolderId: null,   // gewählter Ordner innerhalb des aktiven Kontexts
};

const customers = <?= json_encode($customers) ?>;
const currentUserAbbr = <?= json_encode($_currentUserAbbr) ?>;
const currentUserName = <?= json_encode($_currentUserName) ?>;
// Die Vendor-Scripts oben laden synchron, also sind die Libs hier i.d.R. schon da.
// Der Event-Listener bleibt als Fallback (falls jemals async geladen wird).
let libsReady = !!(window.markedLib && window.hljsLib);

window.addEventListener('chatLibsReady', () => { libsReady = true; });

// ==========================================
// Init
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
    loadProjects();
    loadFolders();
    await loadConversations();
    restoreSettingsFromLocalStorage();
    restoreSidebarState();
    updateContextBanner();
    // Deep-Link: /chat/{id} → die Konversation direkt oeffnen.
    // PHP setzt window.CHAT_DEEP_LINK_ID; Fallback: aus URL parsen.
    let deepId = parseInt(window.CHAT_DEEP_LINK_ID || 0, 10);
    if (!deepId) {
        const m = window.location.pathname.match(/^\/chat\/(\d+)$/);
        if (m) deepId = parseInt(m[1], 10);
    }
    if (deepId > 0) {
        try { await selectConversation(deepId); } catch (e) {
            console.warn('Deep-Link Chat ' + deepId + ' konnte nicht geoeffnet werden:', e);
        }
    }
    document.addEventListener('click', (e) => {
        const cm = document.getElementById('context-menu');
        if (cm.classList.contains('active') && !cm.contains(e.target)) {
            cm.classList.remove('active');
        }
    });
});

// ==========================================
// API helpers
// ==========================================
async function api(method, endpoint, data = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken }
    };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    const res = await fetch(App.apiUrl + endpoint, opts);
    return res.json();
}

async function apiUpload(endpoint, file) {
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch(App.apiUrl + endpoint, {
        method: 'POST',
        headers: { 'X-CSRF-Token': App.csrfToken },
        body: fd
    });
    return res.json();
}

// ==========================================
// Load data
// ==========================================
async function loadProjects() {
    const res = await api('GET', '/chat/projects');
    if (res.success) chatState.projects = res.data || [];
    renderSidebar();
}

async function loadConversations() {
    let url = '/chat/conversations?visibility=all';
    if (chatState.searchQuery) url += '&search=' + encodeURIComponent(chatState.searchQuery);
    const res = await api('GET', url);
    if (res.success) chatState.conversations = res.data || [];
    renderSidebar();
}

async function loadFolders() {
    const res = await api('GET', '/chat/folders');
    if (res.success) {
        chatState.folderCounts = res.data || { private_count: 0, projectwide_count: 0, customers: [], tag_counts: {} };
        if (!chatState.folderCounts.tag_counts) chatState.folderCounts.tag_counts = {};
    }
    // Persistierten Filter wiederherstellen (einmalig)
    if (!chatState._filterRestored) {
        try {
            const saved = localStorage.getItem('chat_sidebar_filter');
            if (saved) chatState.sidebarFilter = saved;
        } catch (_) {}
        chatState._filterRestored = true;
    }
    renderSidebar();
}

// Themen-Ordner pro aktivem Kontext laden (Variante C — manuelle Ordner)
async function loadContextFolders() {
    const ctx = chatState.currentContext;
    if (!ctx) { chatState.contextFolders = []; return; }
    let url = '/chat/projects?context_scope=' + encodeURIComponent(ctx.type);
    if (ctx.type === 'customer') url += '&customer_id=' + ctx.customerId;
    try {
        const res = await api('GET', url);
        chatState.contextFolders = (res && res.data) ? res.data : [];
    } catch (e) { chatState.contextFolders = []; }
}

async function selectContext(type, customerId = null) {
    if (type === null) {
        chatState.currentContext = null;
        chatState.contextFolders = [];
        chatState.activeFolderId = null;
    } else if (chatState.currentContext &&
        chatState.currentContext.type === type &&
        chatState.currentContext.customerId == customerId) {
        // bleibt
    } else {
        chatState.currentContext = { type, customerId };
        chatState.activeFolderId = null;
        await loadContextFolders();
    }
    renderSidebar();
    updateContextBanner();
}

function getActiveContextLabel() {
    const c = chatState.currentContext;
    if (!c) return null;
    if (c.type === 'private') return { label: 'Privat', icon: 'lock', color: '#5b8fd1' };
    if (c.type === 'projectwide') return { label: 'Projektübergreifend', icon: 'public', color: '#10b981' };
    if (c.type === 'customer') {
        const cust = chatState.folderCounts.customers.find(cu => cu.id == c.customerId)
                  || customers.find(cu => cu.id == c.customerId);
        return { label: cust?.name || 'Kunde', icon: 'business', color: 'var(--thoxan-700)' };
    }
    return null;
}

function updateContextBanner() {
    const pill = document.getElementById('welcome-context-banner');
    const picker = document.getElementById('welcome-context-picker');
    const inputWrap = document.getElementById('welcome-input-wrap');
    const title = document.getElementById('welcome-title');
    if (!pill) return;

    const ctx = getActiveContextLabel();

    if (ctx) {
        // Kontext gewählt: dezente Pille oben, Input sichtbar, Picker aus
        pill.className = 'welcome-context-pill';
        pill.style.display = '';
        pill.innerHTML = `
            <span class="material-symbols-rounded" style="font-size:14px;color:${ctx.color};">${ctx.icon}</span>
            <span>${esc(ctx.label)}</span>
            <button type="button" onclick="resetContext()">ändern</button>
        `;
        if (picker) picker.style.display = 'none';
        if (inputWrap) inputWrap.style.display = '';
        if (title) title.textContent = 'Wie kann ich helfen?';
    } else {
        // Kein Kontext: Picker an, Input aus
        pill.style.display = 'none';
        pill.innerHTML = '';
        if (picker) {
            picker.style.display = '';
            const picker2 = document.getElementById('welcome-customer-picker');
            if (picker2) picker2.style.display = 'none';
            const counter = document.getElementById('welcome-ctx-customer-count');
            if (counter) {
                const cnt = (chatState.folderCounts.customers || []).length;
                counter.textContent = cnt + ' verfügbar';
            }
        }
        if (inputWrap) inputWrap.style.display = 'none';
        if (title) title.textContent = 'Womit möchtest du starten?';
    }
}

window.resetContext = function() {
    chatState.currentContext = null;
    renderSidebar();
    updateContextBanner();
};

window.welcomeOpenCustomerPicker = function() {
    const picker = document.getElementById('welcome-customer-picker');
    if (!picker) return;
    picker.style.display = '';
    welcomeRenderCustomers();
    setTimeout(() => document.getElementById('welcome-customer-search')?.focus(), 50);
};

window.welcomeFilterCustomers = function() {
    welcomeRenderCustomers();
};

function welcomeRenderCustomers() {
    const list = document.getElementById('welcome-customer-list');
    if (!list) return;
    const search = (document.getElementById('welcome-customer-search')?.value || '').toLowerCase().trim();
    const all = chatState.folderCounts.customers || [];
    const filtered = search
        ? all.filter(c => (c.name || '').toLowerCase().includes(search) ||
                          (c.abbreviation || '').toLowerCase().includes(search) ||
                          (c.slug || '').toLowerCase().includes(search))
        : all;
    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:18px;color:#94a3b8;font-size: var(--d-fs-sm);">Keine Kunden gefunden</div>';
        return;
    }
    list.innerHTML = filtered.map(c => {
        const abbr = c.abbreviation || (c.name || '?').substring(0, 3).toUpperCase();
        const cnt = c.chat_count || 0;
        return `<div class="welcome-customer-row" onclick="selectContext('customer', ${c.id})">
            <span class="welcome-customer-abbr">${esc(abbr)}</span>
            <span class="welcome-customer-name">${esc(c.name)}</span>
            <span class="welcome-customer-meta">${cnt} Chat${cnt === 1 ? '' : 's'}</span>
        </div>`;
    }).join('');
}

// ==========================================
// Sidebar Render (Finder-Style)
// ==========================================
function getFavorites() {
    const ids = JSON.parse(localStorage.getItem('chat_pinned_convs') || '[]');
    return ids.map(id => chatState.conversations.find(c => c.id === id)).filter(Boolean).slice(0, 5);
}

function togglePinConversation(convId) {
    let ids = JSON.parse(localStorage.getItem('chat_pinned_convs') || '[]');
    const idx = ids.indexOf(convId);
    idx >= 0 ? ids.splice(idx, 1) : ids.unshift(convId);
    localStorage.setItem('chat_pinned_convs', JSON.stringify(ids));
    renderSidebar();
}

// Explorer Navigation
function getChildFolders(parentId) {
    // IDs aller sichtbaren Projekte sammeln
    const visibleIds = new Set(chatState.projects.map(p => p.id));

    return chatState.projects
        .filter(p => {
            if (parentId === null) {
                // Root-Level: Projekte ohne Parent ODER deren Parent nicht sichtbar ist
                return !p.parent_id || !visibleIds.has(p.parent_id);
            }
            return p.parent_id == parentId;
        })
        .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de'));
}

function getFolderConversations(folderId) {
    let convs = chatState.conversations
        .filter(c => folderId === null ? !c.project_id : c.project_id == folderId);
    convs = filterConversationsByContext(convs);
    return convs.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
}

function renderFolderRow(folder) {
    const count = folder.total_conversation_count || countFolderItems(folder.id);
    const iconColor = folder.color ? `style="color:${folder.color}"` : '';
    const sharedIcon = folder.shared_by_name ? `<span class="material-symbols-rounded" style="font-size:14px;color:var(--color-gray-400)" title="Von ${esc(folder.shared_by_name)}">person</span>` : '';
    const folderIcon = folder.shared_by_name ? 'folder_shared' : 'folder';
    return `<div class="explorer-folder-row" onclick="navigateToFolder(${folder.id})" oncontextmenu="showProjectMenu(event,${folder.id})"
        draggable="true" ondragstart="onFolderDragStart(event,${folder.id})" ondragend="onFolderDragEnd(event)"
        ondragover="onProjectDragOver(event)" ondragleave="onProjectDragLeave(event)" ondrop="onProjectDrop(event,${folder.id})">
        <span class="material-symbols-rounded explorer-folder-row-icon" ${iconColor}>${folderIcon}</span>
        <span class="explorer-folder-row-name">${esc(folder.name)}</span>
        ${sharedIcon}
        <span class="explorer-folder-row-meta">${count}</span>
        <span class="material-symbols-rounded explorer-folder-row-arrow">chevron_right</span>
        <div class="explorer-folder-row-actions">
            <button onclick="event.stopPropagation();showProjectMenu(event,${folder.id})" title="Mehr"><span class="material-symbols-rounded">more_horiz</span></button>
        </div>
    </div>`;
}

function countFolderItems(folderId) {
    let total = chatState.conversations.filter(c => c.project_id == folderId).length;
    const children = chatState.projects.filter(p => p.parent_id == folderId);
    total += children.length;
    children.forEach(cf => { total += countFolderItems(cf.id); });
    return total;
}

function navigateToFolder(folderId) {
    chatState.currentFolderId = folderId;
    renderSidebar();
    const content = document.getElementById('sidebar-content');
    content.classList.remove('explorer-slide-enter', 'explorer-slide-back');
    void content.offsetWidth;
    content.classList.add('explorer-slide-enter');
}

function navigateUp() {
    if (chatState.currentFolderId === null) return;
    const folder = chatState.projects.find(p => p.id == chatState.currentFolderId);
    chatState.currentFolderId = (folder && folder.parent_id) ? folder.parent_id : null;
    renderSidebar();
    const content = document.getElementById('sidebar-content');
    content.classList.remove('explorer-slide-enter', 'explorer-slide-back');
    void content.offsetWidth;
    content.classList.add('explorer-slide-back');
}

function updateExplorerNav() {
    const nav = document.getElementById('explorer-nav');
    const title = document.getElementById('explorer-nav-title');
    if (chatState.currentFolderId === null) {
        nav.style.display = 'none';
    } else {
        nav.style.display = 'flex';
        const folder = chatState.projects.find(p => p.id == chatState.currentFolderId);
        title.textContent = folder ? folder.name : 'Ordner';
    }
}

function renderSidebar() {
    // Collapsed-Sidebar: parallele Kuerzel-Liste fuer Schnellnavigation
    renderCollapsedSidebar();

    const el = document.getElementById('sidebar-content');
    const explorerNav = document.getElementById('explorer-nav');
    if (explorerNav) explorerNav.style.display = 'none';

    let html = '';

    // Suchmodus: nur Treffer (innerhalb aktivem Kontext, falls einer da ist)
    if (chatState.searchQuery) {
        html += renderContextFolders();
        const convs = filterConversationsByContext(chatState.conversations);
        if (convs.length === 0) {
            html += '<div class="sidebar-empty">Keine Treffer</div>';
        } else {
            html += '<div class="finder-section-header">Suche</div>';
            convs.forEach(c => { html += renderFinderItem(c); });
        }
        el.innerHTML = html;
        return;
    }

    // Kontext-Folder oben — immer sichtbar
    html += renderContextFolders();

    // Wenn kein Kontext gewählt: nichts weiter zeigen
    if (!chatState.currentContext) {
        html += '<div class="sidebar-hint"><span class="material-symbols-rounded" style="font-size:18px;color:#94a3b8;">arrow_upward</span> Wähle oben einen Kontext, um die Chats zu sehen.</div>';
        el.innerHTML = html;
        return;
    }

    // Ordner-Bar für den aktiven Kontext (Variante C — Themen-Ordner)
    html += renderContextFolderBar();

    // Mit Kontext: nur die Chats dieses Kontexts (gruppiert nach Datum)
    let conversations = filterConversationsByContext(chatState.conversations);
    if (chatState.activeFolderId !== null) {
        conversations = conversations.filter(c => c.project_id == chatState.activeFolderId);
    }
    conversations = conversations.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

    if (conversations.length === 0) {
        html += '<div class="sidebar-empty">Noch keine Chats in diesem Kontext.<br><small>Starte rechts einen neuen.</small></div>';
        el.innerHTML = html;
        return;
    }

    html += '<div class="finder-section-header" style="padding-top:14px;">Chats <span style="color:#94a3b8;font-weight:500;">· ' + conversations.length + '</span></div>';
    const groups = groupByDate(conversations);
    for (const [label, convs] of Object.entries(groups)) {
        html += `<div class="finder-date-label">${label}</div>`;
        convs.forEach(c => { html += renderFinderItem(c); });
    }

    el.innerHTML = html;
}

function renderCollapsedSidebar() {
    const el = document.getElementById('chat-collapsed-list');
    if (!el) return;
    const fc = chatState.folderCounts || {};
    const ctx = chatState.currentContext;
    const filter = chatState.sidebarFilter || 'all';
    const items = [];

    // Welche Eintraege gehoeren je nach Filter angezeigt (gleich wie im aufgeklappten Zustand):
    const showPrivate     = (filter === 'all' || filter === 'private');
    const showProjectwide = (filter === 'all' || filter === 'projectwide');
    let customers = fc.customers || [];
    if (filter.startsWith('tag:')) {
        const tag = filter.slice(4);
        customers = customers.filter(c => (c.tags || []).includes(tag));
    } else if (filter === 'private' || filter === 'projectwide') {
        customers = [];
    }

    if (showPrivate) {
        items.push({
            key: 'private', label: 'Privat', abbr: '🔒',
            active: ctx?.type === 'private',
            onclick: "selectContext('private')",
        });
    }
    if (showProjectwide) {
        items.push({
            key: 'projectwide', label: 'Projektübergreifend', abbr: '🌐',
            active: ctx?.type === 'projectwide',
            onclick: "selectContext('projectwide')",
        });
    }
    // Kunden alphabetisch (gleich wie in der aufgeklappten Liste)
    customers
        .slice()
        .sort((a, b) => (a.abbreviation || a.name || '').localeCompare(b.abbreviation || b.name || '', 'de', { sensitivity: 'base' }))
        .forEach(c => {
            const abbr = (c.abbreviation || (c.name || '?').substring(0, 3)).toUpperCase();
            items.push({
                key: 'cust-' + c.id,
                label: c.name,
                abbr: abbr,
                active: ctx?.type === 'customer' && ctx?.customerId == c.id,
                onclick: `selectContext('customer', ${c.id})`,
            });
        });

    el.innerHTML = items.map(it => `
        <button class="chat-collapsed-abbr ${it.active ? 'is-active' : ''}"
                title="${esc(it.label)}"
                onclick="${it.onclick}">${esc(it.abbr)}</button>
    `).join('');
}

function renderContextFolders() {
    const fc = chatState.folderCounts;
    const ctx = chatState.currentContext;
    const q = (chatState.searchQuery || '').toLowerCase().trim();

    // ===== Fokus-Modus: aktiver Kontext gewählt — nur Header mit Zurück-Pfeil =====
    if (ctx && !q) {
        let label, badgeInner, count = 0;
        if (ctx.type === 'private') {
            label = 'Privat';
            badgeInner = '<span class="material-symbols-rounded">lock</span>';
            count = fc.private_count || 0;
        } else if (ctx.type === 'projectwide') {
            label = 'Projektübergreifend';
            badgeInner = '<span class="material-symbols-rounded">public</span>';
            count = fc.projectwide_count || 0;
        } else {
            const cust = (fc.customers || []).find(c => c.id == ctx.customerId);
            label = cust?.name || 'Kunde';
            const abbr = cust?.abbreviation || (cust?.name || '?').substring(0, 3).toUpperCase();
            badgeInner = esc(abbr);
            count = cust?.chat_count || 0;
        }
        return `
            <div class="ctx-focused">
                <button class="ctx-back" onclick="selectContext(null)" title="Zurück zur Übersicht">
                    <span class="material-symbols-rounded">arrow_back</span>
                </button>
                <div class="ctx-focused-badge">${badgeInner}</div>
                <div class="ctx-focused-label">
                    <strong>${esc(label)}</strong>
                    <small>${count} ${count === 1 ? 'Chat' : 'Chats'}</small>
                </div>
            </div>
        `;
    }

    // ===== Übersichts-Modus oder Suche: alle Kontexte zeigen =====
    const filter = chatState.sidebarFilter || 'all';
    let html = '';

    // Icon-Filter-Bar (analog Projektplanner). Nur ausserhalb der Suche.
    if (!q) {
        html += renderCtxFilterBar(filter);
    }

    html += '<div class="ctx-folders">';

    // Tag-Mapping: Tag-Name -> Icon + Farbe
    const TAG_META = ctxTagMeta();

    // Bestimmen, was angezeigt wird je nach Filter
    const showPrivate     = !q && (filter === 'all' || filter === 'private');
    const showProjectwide = !q && (filter === 'all' || filter === 'projectwide');
    let customers = fc.customers || [];

    // Bei Tag-Filter Kunden auf jene mit diesem Tag reduzieren
    if (filter.startsWith('tag:')) {
        const tag = filter.slice(4);
        customers = customers.filter(c => (c.tags || []).includes(tag));
    }
    // Bei privat/projektübergreifend Kunden komplett ausblenden
    if (filter === 'private' || filter === 'projectwide') {
        customers = [];
    }

    if (q) {
        customers = customers.filter(cust =>
            (cust.name || '').toLowerCase().includes(q) ||
            (cust.abbreviation || '').toLowerCase().includes(q) ||
            (cust.slug || '').toLowerCase().includes(q)
        );
    }

    if (showPrivate) {
        html += `<div class="ctx-folder" onclick="selectContext('private')">
            <span class="material-symbols-rounded" style="color:#5b8fd1;font-size:18px;">lock</span>
            <span class="ctx-folder-name">Privat</span>
            <span class="ctx-folder-count">${fc.private_count || 0}</span>
        </div>`;
    }
    if (showProjectwide) {
        html += `<div class="ctx-folder" onclick="selectContext('projectwide')">
            <span class="material-symbols-rounded" style="color:#10b981;font-size:18px;">public</span>
            <span class="ctx-folder-name">Projektübergreifend</span>
            <span class="ctx-folder-count">${fc.projectwide_count || 0}</span>
        </div>`;
    }

    const renderCustRow = (cust) => {
        const abbr = cust.abbreviation ? cust.abbreviation : (cust.name || '?').substring(0, 3).toUpperCase();
        return `<div class="ctx-folder" onclick="selectContext('customer', ${cust.id})">
            <span class="ctx-folder-abbr">${esc(abbr)}</span>
            <span class="ctx-folder-name">${esc(cust.name)}</span>
            <span class="ctx-folder-count">${cust.chat_count || 0}</span>
        </div>`;
    };

    if (customers.length > 0) {
        const sorted = customers.slice().sort((a, b) => (a.abbreviation || a.name || '').localeCompare(b.abbreviation || b.name || '', 'de', { sensitivity: 'base' }));
        const headerLabel = filter.startsWith('tag:') ? filter.slice(4) : 'Kunden';
        html += '<div class="ctx-folder-section-label">' + esc(headerLabel) + ' <span style="color:#94a3b8;font-weight:500;">· ' + sorted.length + '</span></div>';
        sorted.forEach(cust => { html += renderCustRow(cust); });
    } else if (filter.startsWith('tag:') || q) {
        const what = filter.startsWith('tag:') ? ('„' + filter.slice(4) + '"') : 'Treffer';
        html += '<div class="sidebar-empty" style="padding:12px;font-size:var(--d-fs-xs);color:#94a3b8;text-align:center;">Keine Kunden für ' + esc(what) + '.</div>';
    }

    html += '</div>';
    return html;
}

/** Filter-Icon-Bar im Stil des Projektplanners.
 *  Reihenfolge: Alle | Privat | Projektübergreifend || Tags (in absteigender Haeufigkeit). */
function renderCtxFilterBar(activeFilter) {
    const fc = chatState.folderCounts || {};
    const TAG_META = ctxTagMeta();
    const tagCounts = fc.tag_counts || {};

    // Tags in der Reihenfolge wie sie in admin/customers vorkommen (absteigend nach Haeufigkeit)
    const sortedTags = Object.keys(tagCounts)
        .filter(t => t !== 'Inaktiv')
        .sort((a, b) => (tagCounts[b] - tagCounts[a]));

    const btn = (id, icon, title, cls = '') => `
        <button class="ctx-filter-icon ${activeFilter === id ? 'is-active' : ''} ${cls}"
                title="${esc(title)}" onclick="ctxSetFilter('${esc(id)}')">
            <span class="material-symbols-rounded">${icon}</span>
        </button>`;

    let html = '<div class="ctx-filter-bar">';
    html += btn('all',         'inbox',  'Alle');
    html += btn('private',     'lock',   'Privat');
    html += btn('projectwide', 'public', 'Projektübergreifend');
    if (sortedTags.length) html += '<span class="ctx-filter-sep"></span>';
    sortedTags.forEach(tag => {
        const meta = TAG_META[tag] || { icon: 'label', title: tag };
        html += btn('tag:' + tag, meta.icon, tag + (tagCounts[tag] ? ' (' + tagCounts[tag] + ')' : ''));
    });
    html += '</div>';
    return html;
}

/** Icon-Mapping pro Tag — gleicher Stil wie in admin/customers. */
function ctxTagMeta() {
    return {
        'Kunde':        { icon: 'business' },
        'Eigenprojekt': { icon: 'auto_awesome' },
        'Portal':       { icon: 'language' },
        'Pro Bono':     { icon: 'volunteer_activism' },
        'E-Commerce':   { icon: 'shopping_cart' },
        'Affiliate':    { icon: 'share' },
    };
}

/** Setzt den Sidebar-Filter und rendert neu. */
function ctxSetFilter(id) {
    // Privat + Projektübergreifend haben jeweils nur einen Eintrag — direkt den
    // Kontext oeffnen, statt erst zur einzeiligen Filter-Liste zu navigieren.
    if (id === 'private') {
        chatState.sidebarFilter = 'private';
        try { localStorage.setItem('chat_sidebar_filter', 'private'); } catch (_) {}
        selectContext('private');
        return;
    }
    if (id === 'projectwide') {
        chatState.sidebarFilter = 'projectwide';
        try { localStorage.setItem('chat_sidebar_filter', 'projectwide'); } catch (_) {}
        selectContext('projectwide');
        return;
    }
    chatState.sidebarFilter = id;
    try { localStorage.setItem('chat_sidebar_filter', id); } catch (_) {}
    renderSidebar();
}

// ===== Themen-Ordner-Bar (pro Kontext, Variante C) =====
function renderContextFolderBar() {
    if (!chatState.currentContext) return '';
    const folders = chatState.contextFolders || [];
    const ctxConvs = filterConversationsByContext(chatState.conversations);
    const allCount = ctxConvs.length;

    let html = '<div class="ctx-folder-bar">';
    const allActive = chatState.activeFolderId === null;
    html += `<button class="ctx-fbar-chip ${allActive ? 'active' : ''}" onclick="selectFolder(null)">
        <span class="material-symbols-rounded" style="font-size:14px;">inbox</span>
        Alle <span class="ctx-fbar-count">${allCount}</span>
    </button>`;
    folders.forEach(f => {
        const isActive = chatState.activeFolderId == f.id;
        const dot = f.color ? `<span class="ctx-fbar-dot" style="background:${esc(f.color)};"></span>` : '';
        html += `<button class="ctx-fbar-chip ${isActive ? 'active' : ''}"
            onclick="selectFolder(${f.id})"
            oncontextmenu="sbFolderContextMenu(event, ${f.id})"
            ondragover="sbFolderDragOver(event)" ondragleave="sbFolderDragLeave(event)" ondrop="sbFolderDrop(event,${f.id})">
            ${dot}${esc(f.name)} <span class="ctx-fbar-count">${f.conversation_count || 0}</span>
        </button>`;
    });
    html += `<button class="ctx-fbar-add" onclick="sbCreateFolder()" title="Neuer Ordner">
        <span class="material-symbols-rounded">add</span>
    </button>`;
    html += `<button class="ctx-fbar-ai" onclick="sbSuggestFolders()" title="KI-Themen-Vorschläge">
        <span class="material-symbols-rounded">auto_awesome</span>
    </button>`;
    html += '</div>';
    return html;
}

// Drag-Handler: Chat-Items werden draggable, beim Drop auf einen Folder-Chip wird zugewiesen
window.sbFolderDragOver = function(e) { e.preventDefault(); e.currentTarget.classList.add('drop-target'); };
window.sbFolderDragLeave = function(e) { e.currentTarget.classList.remove('drop-target'); };
window.sbFolderDrop = function(e, folderId) {
    e.preventDefault();
    e.currentTarget.classList.remove('drop-target');
    const convId = parseInt(e.dataTransfer.getData('text/conv-id'));
    if (!convId) return;
    assignChatToFolder(convId, folderId);
};

// ===== KI-Themen-Vorschläge =====
window.sbSuggestFolders = async function() {
    const ctx = chatState.currentContext;
    if (!ctx) return;
    sbShowSuggestModal('loading');
    try {
        const payload = { context_scope: ctx.type };
        if (ctx.type === 'customer') payload.customer_id = ctx.customerId;
        const r = await api('POST', '/chat/projects/suggest', payload);
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbRenderSuggestions(r.data.clusters || [], r.data.total_chats || 0);
    } catch (e) {
        sbShowSuggestModal('error', e.message || 'Fehler');
    }
};

function sbShowSuggestModal(state, msg) {
    let overlay = document.getElementById('sb-suggest-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-suggest-overlay';
        overlay.className = 'trash-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseSuggest(); };
        document.body.appendChild(overlay);
    }
    if (state === 'loading') {
        overlay.innerHTML = `
            <div class="trash-modal" style="max-width:620px;">
                <div class="trash-head" style="background:linear-gradient(180deg,#f0fdf4 0%,#fff 100%);">
                    <div class="trash-head-icon" style="background:linear-gradient(135deg,#10b981,#34d399);"><span class="material-symbols-rounded">auto_awesome</span></div>
                    <div class="trash-head-text">
                        <h3>KI analysiert deine Chats…</h3>
                        <small>Themen werden geclustert — dauert 10–30 Sekunden</small>
                    </div>
                </div>
                <div class="trash-body" style="text-align:center;padding:2.5rem 1rem;color:#94a3b8;">
                    <span style="display:inline-block;width:20px;height:20px;border:3px solid rgba(16,185,129,0.3);border-top-color:#10b981;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:10px;"></span>
                    Bitte einen Moment Geduld…
                </div>
            </div>`;
    } else if (state === 'error') {
        overlay.innerHTML = `
            <div class="trash-modal" style="max-width:520px;">
                <div class="trash-head">
                    <div class="trash-head-icon" style="background:linear-gradient(135deg,var(--rose-600),#f87171);"><span class="material-symbols-rounded">error</span></div>
                    <div class="trash-head-text"><h3>Fehler</h3><small>${esc(msg || '')}</small></div>
                    <button class="trash-close" onclick="sbCloseSuggest()"><span class="material-symbols-rounded">close</span></button>
                </div>
            </div>`;
    }
    overlay.classList.add('open');
}

let sbSuggestClusters = [];

function sbRenderSuggestions(clusters, totalChats) {
    sbSuggestClusters = clusters.map(c => ({ ...c, accepted: true }));
    const overlay = document.getElementById('sb-suggest-overlay');
    overlay.innerHTML = `
        <div class="trash-modal" style="max-width:720px;">
            <div class="trash-head" style="background:linear-gradient(180deg,#f0fdf4 0%,#fff 100%);">
                <div class="trash-head-icon" style="background:linear-gradient(135deg,#10b981,#34d399);"><span class="material-symbols-rounded">auto_awesome</span></div>
                <div class="trash-head-text">
                    <h3>${clusters.length} Themen vorgeschlagen</h3>
                    <small>aus ${totalChats} Chats — wähle aus, was du übernehmen möchtest</small>
                </div>
                <button class="trash-close" onclick="sbCloseSuggest()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="trash-body" id="sb-suggest-body"></div>
            <div style="padding:0.9rem 1.3rem;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                <small style="color:#64748b;" id="sb-suggest-summary"></small>
                <div style="display:flex;gap:0.4rem;">
                    <button class="thx-btn thx-btn-secondary" onclick="sbCloseSuggest()">Abbrechen</button>
                    <button class="thx-btn thx-btn-primary" onclick="sbApplySuggestions()" id="sb-suggest-apply">Übernehmen</button>
                </div>
            </div>
        </div>
    `;
    sbRenderSuggestList();
}

function sbRenderSuggestList() {
    const body = document.getElementById('sb-suggest-body');
    if (!body) return;
    body.innerHTML = sbSuggestClusters.map((c, idx) => `
        <div class="sb-cluster ${c.accepted ? 'accepted' : 'rejected'}">
            <div class="sb-cluster-head">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1;min-width:0;">
                    <input type="checkbox" ${c.accepted ? 'checked' : ''} onchange="sbToggleCluster(${idx})">
                    <input type="text" class="sb-cluster-name" value="${esc(c.name)}" oninput="sbRenameCluster(${idx}, this.value)">
                </label>
                <span class="sb-cluster-count">${c.chats.length} Chats</span>
            </div>
            <div class="sb-cluster-chats">
                ${c.chats.map(ch => `<span class="sb-cluster-chip" title="${esc(ch.title)}">${esc(ch.title.length > 32 ? ch.title.substring(0, 32) + '…' : ch.title)}</span>`).join('')}
            </div>
        </div>
    `).join('');
    sbUpdateSuggestSummary();
}

function sbUpdateSuggestSummary() {
    const summary = document.getElementById('sb-suggest-summary');
    if (!summary) return;
    const accepted = sbSuggestClusters.filter(c => c.accepted);
    const totalChats = accepted.reduce((s, c) => s + c.chats.length, 0);
    summary.textContent = accepted.length + ' Ordner · ' + totalChats + ' Chats werden zugeordnet';
}

window.sbToggleCluster = function(idx) {
    sbSuggestClusters[idx].accepted = !sbSuggestClusters[idx].accepted;
    sbRenderSuggestList();
};

window.sbRenameCluster = function(idx, name) {
    sbSuggestClusters[idx].name = name;
};

window.sbApplySuggestions = async function() {
    const accepted = sbSuggestClusters.filter(c => c.accepted && c.name.trim() !== '');
    if (!accepted.length) { App.showNotification('Keine Cluster ausgewählt', 'info'); return; }
    const ctx = chatState.currentContext;
    const btn = document.getElementById('sb-suggest-apply');
    btn.disabled = true; btn.textContent = 'Übernehme…';
    try {
        const payload = {
            context_scope: ctx.type,
            apply: true,
            approved: accepted.map(c => ({ name: c.name, chat_ids: c.chat_ids })),
        };
        if (ctx.type === 'customer') payload.customer_id = ctx.customerId;
        const r = await api('POST', '/chat/projects/suggest', payload);
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbCloseSuggest();
        await loadContextFolders();
        await loadConversations();
        App.showNotification(`${r.data.folders_created} Ordner angelegt, ${r.data.chats_assigned} Chats zugeordnet`, 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false; btn.textContent = 'Übernehmen';
    }
};

window.sbCloseSuggest = function() {
    const overlay = document.getElementById('sb-suggest-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

window.selectFolder = function(folderId) {
    chatState.activeFolderId = folderId;
    renderSidebar();
};

window.sbCreateFolder = async function() {
    const ctx = chatState.currentContext;
    if (!ctx) return;
    const name = prompt('Name des neuen Ordners:');
    if (!name || !name.trim()) return;
    try {
        const payload = { name: name.trim(), context_scope: ctx.type };
        if (ctx.type === 'customer') payload.customer_id = ctx.customerId;
        const r = await api('POST', '/chat/projects', payload);
        if (!r.success) throw new Error(r.message || 'Fehler');
        await loadContextFolders();
        chatState.activeFolderId = r.data?.id || null;
        renderSidebar();
        App.showNotification('Ordner angelegt', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbDeleteFolder = async function(folderId, name) {
    if (!confirm('Ordner „' + name + '" löschen? Die Chats bleiben erhalten und sind danach ohne Ordner.')) return;
    try {
        await api('DELETE', '/chat/projects/' + folderId);
        await loadContextFolders();
        if (chatState.activeFolderId == folderId) chatState.activeFolderId = null;
        renderSidebar();
        loadConversations();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

// ===== Rechtsklick-Menü für Ordner (Umbenennen / Farbe / Löschen) =====
const SB_FOLDER_COLORS = ['#3b82f6','#10b981','#f59e0b','#ec4899','#a78bfa','#14b8a6','#f97316','#06b6d4','#ef4444','#64748b'];

window.sbFolderContextMenu = function(e, folderId) {
    e.preventDefault(); e.stopPropagation();
    const folder = (chatState.contextFolders || []).find(f => f.id == folderId);
    if (!folder) return;
    const cm = document.getElementById('context-menu');
    const colorRow = SB_FOLDER_COLORS.map(c => `
        <button class="sb-color-dot ${folder.color === c ? 'active' : ''}" style="background:${c};" onclick="sbFolderSetColor(${folderId}, '${c}')" title="${c}"></button>
    `).join('');
    cm.innerHTML = `
        <button class="thx-contextmenu-item" onclick="sbFolderRename(${folderId})">
            <span class="material-symbols-rounded">edit</span>Umbenennen
        </button>
        <div class="thx-contextmenu-divider"></div>
        <div style="padding:6px 12px 2px;font-size: var(--d-fs-xs);text-transform:uppercase;color:#94a3b8;letter-spacing:0.5px;font-weight:700;">Farbe</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px;padding:6px 12px 10px;">
            <button class="sb-color-dot ${!folder.color ? 'active' : ''}" style="background:#fff;border:1px solid #cbd5e1;" onclick="sbFolderSetColor(${folderId}, null)" title="Keine Farbe"></button>
            ${colorRow}
        </div>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item is-danger" onclick="sbDeleteFolder(${folderId}, '${esc(folder.name).replace(/'/g, "\\'")}');document.getElementById('context-menu').classList.remove('active');">
            <span class="material-symbols-rounded">delete</span>Löschen
        </button>
    `;
    cm.classList.add('active');
    positionContextMenu(cm, e.clientX, e.clientY);
};

window.sbFolderRename = async function(folderId) {
    document.getElementById('context-menu').classList.remove('active');
    const folder = (chatState.contextFolders || []).find(f => f.id == folderId);
    if (!folder) return;
    const newName = prompt('Neuer Name:', folder.name);
    if (!newName || newName.trim() === '' || newName.trim() === folder.name) return;
    try {
        await api('PUT', '/chat/projects/' + folderId, { name: newName.trim() });
        await loadContextFolders();
        renderSidebar();
        App.showNotification('Umbenannt', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbFolderSetColor = async function(folderId, color) {
    try {
        await api('PUT', '/chat/projects/' + folderId, { color });
        const f = (chatState.contextFolders || []).find(f => f.id == folderId);
        if (f) f.color = color;
        document.getElementById('context-menu').classList.remove('active');
        renderSidebar();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

// ===== Chat zu anderem Kunden / Kontext verschieben =====
window.sbReassignChat = function(convId) {
    document.getElementById('context-menu').classList.remove('active');
    const conv = chatState.conversations.find(c => c.id == convId);
    if (!conv) return;

    let overlay = document.getElementById('sb-reassign-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-reassign-overlay';
        overlay.className = 'trash-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbReassignClose(); };
        document.body.appendChild(overlay);
    }
    const cust = (chatState.folderCounts.customers || []).slice()
        .sort((a, b) => (a.abbreviation || a.name || '').localeCompare(b.abbreviation || b.name || '', 'de'));

    overlay.innerHTML = `
        <div class="trash-modal" style="max-width:560px;">
            <div class="trash-head">
                <div class="trash-head-icon" style="background:linear-gradient(135deg,var(--thoxan-700),var(--thoxan-600));"><span class="material-symbols-rounded">business</span></div>
                <div class="trash-head-text">
                    <h3>Chat verschieben</h3>
                    <small>Wähle einen neuen Kontext für „${esc(conv.title || '')}"</small>
                </div>
                <button class="trash-close" onclick="sbReassignClose()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="trash-body" style="padding:0.6rem 1rem 0;">
                <input type="text" id="sb-reassign-search" placeholder="Kunde suchen…" oninput="sbReassignFilter(this.value)"
                       style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size: var(--d-fs-sm);margin-bottom:0.6rem;">
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size: var(--d-fs-sm);color:#92400e;margin-bottom:0.7rem;">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;margin-right:4px;">info</span>
                    Falls dieser Chat bereits ins Wissen übertragen wurde, wandert der Eintrag mit zum neuen Kunden.
                </div>
            </div>
            <div class="trash-body" id="sb-reassign-list" style="padding-top:0;"></div>
        </div>
    `;
    overlay.classList.add('open');

    // Optionen rendern: Privat, Projektübergreifend, alle Kunden
    sbReassignAllOptions = [
        { type: 'private', label: 'Privat (nur ich)', icon: 'lock', color: '#5b8fd1' },
        { type: 'projectwide', label: 'Projektübergreifend', icon: 'public', color: '#10b981' },
        ...cust.map(c => ({
            type: 'customer', id: c.id, label: c.name,
            abbr: c.abbreviation || (c.name || '?').substring(0, 3).toUpperCase(),
        })),
    ];
    sbReassignCurrent = { convId, currentCustomerId: conv.customer_id, isPrivate: !!conv.is_private };
    sbReassignRender('');
};

let sbReassignAllOptions = [];
let sbReassignCurrent = null;

function sbReassignRender(query) {
    const q = (query || '').toLowerCase().trim();
    const list = document.getElementById('sb-reassign-list');
    if (!list) return;
    const filtered = q
        ? sbReassignAllOptions.filter(o => o.label.toLowerCase().includes(q) || (o.abbr || '').toLowerCase().includes(q))
        : sbReassignAllOptions;
    list.innerHTML = filtered.map(o => {
        let isCurrent = false;
        if (o.type === 'private') isCurrent = sbReassignCurrent.isPrivate;
        else if (o.type === 'projectwide') isCurrent = !sbReassignCurrent.isPrivate && !sbReassignCurrent.currentCustomerId;
        else isCurrent = !sbReassignCurrent.isPrivate && sbReassignCurrent.currentCustomerId == o.id;

        const icon = o.type === 'customer'
            ? `<span class="ctx-folder-abbr" style="width:32px;height:32px;">${esc(o.abbr)}</span>`
            : `<span class="material-symbols-rounded" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:${o.color};background:#f8fafc;border-radius:8px;">${o.icon}</span>`;
        return `
            <div class="sb-reassign-row ${isCurrent ? 'current' : ''}" onclick="sbReassignApply('${o.type}', ${o.id || 'null'})">
                ${icon}
                <span style="flex:1;font-size: var(--d-fs-sm);color:#1e293b;">${esc(o.label)}</span>
                ${isCurrent ? '<span style="color:#10b981;font-size: var(--d-fs-xs);font-weight:600;">aktuell</span>' : '<span class="material-symbols-rounded" style="color:#cbd5e1;font-size:18px;">chevron_right</span>'}
            </div>
        `;
    }).join('');
}

window.sbReassignFilter = function(q) { sbReassignRender(q); };

window.sbReassignApply = async function(type, customerId) {
    if (!sbReassignCurrent) return;
    const convId = sbReassignCurrent.convId;
    let payload;
    if (type === 'private') payload = { is_private: 1, customer_id: null };
    else if (type === 'projectwide') payload = { is_private: 0, customer_id: null };
    else payload = { is_private: 0, customer_id: customerId };

    try {
        await api('PUT', '/chat/conversations/' + convId, payload);
        // Lokal anpassen
        const c = chatState.conversations.find(c => c.id == convId);
        if (c) {
            c.is_private = payload.is_private;
            c.customer_id = payload.customer_id;
            c.project_id = null;
        }
        sbReassignClose();
        loadFolders();         // Counter aktualisieren
        loadConversations();   // Liste neu laden
        App.showNotification('Chat verschoben', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbReassignClose = function() {
    const overlay = document.getElementById('sb-reassign-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

window.sbChatDragStart = function(e, convId) {
    e.dataTransfer.setData('text/conv-id', String(convId));
    e.dataTransfer.effectAllowed = 'move';
    e.currentTarget.classList.add('dragging');
};
window.sbChatDragEnd = function(e) { e.currentTarget.classList.remove('dragging'); };

window.assignChatToFolder = async function(convId, folderId) {
    try {
        await api('PUT', '/chat/conversations/' + convId, { project_id: folderId });
        const c = chatState.conversations.find(c => c.id == convId);
        if (c) c.project_id = folderId;
        await loadContextFolders();
        renderSidebar();
        App.showNotification(folderId ? 'Chat in Ordner verschoben' : 'Chat aus Ordner entfernt', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

function filterConversationsByContext(convs) {
    const ctx = chatState.currentContext;
    if (!ctx) return convs; // alle
    return convs.filter(c => {
        if (ctx.type === 'private') return !!c.is_private && c.user_id == <?= $userId ?>;
        if (ctx.type === 'projectwide') return !c.is_private && !c.customer_id;
        if (ctx.type === 'customer') return !c.is_private && c.customer_id == ctx.customerId;
        return true;
    });
}

function renderFinderItem(c) {
    const isActive = c.id == chatState.activeId;
    const isMine = c.user_id == <?= $userId ?>;

    // Creator-Initiale (nur wenn nicht eigener Chat)
    let creatorBadge = '';
    if (!isMine && c.creator_name) {
        // Kuerzel bevorzugt, sonst Initialen aus Name
        let abbr = (c.creator_abbreviation || '').trim();
        if (!abbr) {
            const parts = (c.creator_name || '').trim().split(/\s+/);
            abbr = parts.length >= 2
                ? (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
                : c.creator_name.charAt(0).toUpperCase();
        }
        creatorBadge = `<span class="creator-avatar" title="von ${esc(c.creator_name)}">${esc(abbr)}</span>`;
    }

    // Customer-Badge (nur sichtbar wenn nicht in Customer-Folder)
    let customerBadge = '';
    if (c.customer_id && c.customer_abbreviation
        && (!chatState.currentContext || chatState.currentContext.type !== 'customer')) {
        customerBadge = `<span class="customer-badge" title="${esc(c.customer_name || '')}">${esc(c.customer_abbreviation)}</span>`;
    }

    const privateIcon = c.is_private
        ? `<span class="material-symbols-rounded" style="font-size:13px;color:#5b8fd1;" title="Privat">lock</span>`
        : '';
    const attachBadge = (c.attachment_count > 0)
        ? `<span class="material-symbols-rounded finder-item-shared" title="${c.attachment_count} Datei(en)" style="font-size:14px;">attach_file</span>`
        : '';
    const icon = 'chat_bubble_outline';

    return `<div class="finder-item ${isActive ? 'active' : ''}" onclick="selectConversation(${c.id})" oncontextmenu="showConvContextMenu(event,${c.id})" data-conv="${c.id}"
        draggable="true" ondragstart="sbChatDragStart(event,${c.id})" ondragend="sbChatDragEnd(event)">
        <span class="material-symbols-rounded finder-item-icon">${icon}</span>
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;">
            <div style="display:flex;align-items:center;gap:4px;min-width:0;">
                ${customerBadge}
                <span class="finder-item-title" style="flex:1;min-width:0;">${esc(c.title)}</span>
                ${privateIcon}
            </div>
        </div>
        <div class="finder-item-badges">${attachBadge}${creatorBadge}</div>
        <div class="finder-item-actions">
            <button onclick="event.stopPropagation();showConvContextMenu(event,${c.id})" title="Mehr (oder Rechtsklick)"><span class="material-symbols-rounded">more_horiz</span></button>
        </div>
    </div>`;
}

function groupByDate(conversations) {
    const groups = {};
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
    const weekAgo = new Date(today); weekAgo.setDate(today.getDate() - 7);

    conversations.forEach(c => {
        const d = new Date(c.updated_at || c.created_at);
        let label;
        if (d >= today) label = 'Heute';
        else if (d >= yesterday) label = 'Gestern';
        else if (d >= weekAgo) label = 'Letzte 7 Tage';
        else label = 'Aelter';

        if (!groups[label]) groups[label] = [];
        groups[label].push(c);
    });
    return groups;
}

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// Relativ-Zeit "vor 3 Tagen / 2 Wochen" — älter als 1 Jahr → "vor X Jahren"
function formatRelativeTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace ? iso.replace(' ', 'T') : iso);
    if (isNaN(d.getTime())) return '';
    const diffSec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diffSec < 60) return 'gerade eben';
    if (diffSec < 3600) { const m = Math.floor(diffSec / 60); return 'vor ' + m + ' Min'; }
    if (diffSec < 86400) { const h = Math.floor(diffSec / 3600); return 'vor ' + h + ' Std'; }
    if (diffSec < 7 * 86400) { const t = Math.floor(diffSec / 86400); return 'vor ' + t + (t === 1 ? ' Tag' : ' Tagen'); }
    if (diffSec < 30 * 86400) { const w = Math.floor(diffSec / (7 * 86400)); return 'vor ' + w + (w === 1 ? ' Woche' : ' Wochen'); }
    if (diffSec < 365 * 86400) { const mo = Math.floor(diffSec / (30 * 86400)); return 'vor ' + mo + (mo === 1 ? ' Monat' : ' Monaten'); }
    const y = Math.floor(diffSec / (365 * 86400));
    return 'vor ' + y + (y === 1 ? ' Jahr' : ' Jahren');
}
function formatFullDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace ? iso.replace(' ', 'T') : iso);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ==========================================
// Conversation Actions
// ==========================================
function newChat(projectId = null) {
    saveDraft();   // Entwurf des bisherigen Chats sichern (Feedback #58)

    chatState.activeId = null;
    chatState.activeConv = null;
    chatState.messages = [];
    chatState.pendingAttachments = [];
    chatState.attachedArtifacts = [];
    chatState.artifactPanelOpen = false;
    const ap = document.getElementById('artifact-panel');
    if (ap) ap.style.display = 'none';

    document.getElementById('chat-view').style.display = 'none';
    document.getElementById('welcome-screen').style.display = '';

    // URL zurueck auf /chat (kein Deep-Link)
    try {
        if (window.location.pathname !== '/chat') {
            window.history.replaceState({}, '', '/chat');
        }
    } catch (_) {}

    // Restore settings from last chat
    restoreSettingsFromLocalStorage();

    renderSidebar();
    updateContextBanner();

    // Auch der Welcome-Screen behält seinen Entwurf (Schlüssel 'new').
    const ta = document.getElementById('welcome-textarea');
    restoreDraft(null, 'welcome-textarea');
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
    renderAttachments('welcome-attachments');
}

async function selectConversation(id) {
    // Entwurf des BISHERIGEN Chats sichern, bevor wir wechseln (Feedback #58).
    // Muss vor dem await passieren — sonst kann zwischendurch schon neu gerendert werden.
    saveDraft();

    const res = await api('GET', '/chat/conversations/' + id);
    if (!res.success) {
        App.showNotification(res.message || 'Fehler beim Laden', 'error');
        return;
    }

    chatState.activeId = id;
    chatState.activeConv = res.data;
    // URL aktualisieren, damit der Chat per Refresh / Sharelink direkt aufrufbar ist
    try {
        const want = '/chat/' + id;
        if (window.location.pathname !== want) {
            window.history.replaceState({ chatId: id }, '', want);
        }
    } catch (_) {}
    chatState.messages = groupMessagesForDisplay(res.data.messages || []);
    chatState.pendingAttachments = [];
    chatState.attachedArtifacts = [];

    // Load feedback
    const fbRes = await api('GET', '/chat/conversations/' + id + '/feedback');
    chatState.feedback = fbRes.success ? fbRes.data : null;
    updateFeedbackButton();

    document.getElementById('welcome-screen').style.display = 'none';
    const cv = document.getElementById('chat-view');
    cv.style.display = 'flex';

    // Set model
    if (res.data.model) {
        const sel = document.getElementById('chat-model-select');
        if (sel.querySelector(`option[value="${res.data.model}"]`)) {
            sel.value = res.data.model;
        }
    }

    // Restore toggle states
    chatState.useArtifacts = !!res.data.use_artifacts;
    chatState.useWebSearch = !!res.data.use_web_search;
    chatState.useKnowledge = !!res.data.use_knowledge;
    chatState.useRules = res.data.use_rules === undefined ? true : !!res.data.use_rules;
    syncArtifactCheckbox();
    syncWebSearchCheckbox();
    syncKnowledgeCheckbox();
    syncRulesCheckbox();

    // Restore dual mode
    const wasDual = !!res.data.dual_mode;
    toggleDualMode(wasDual);
    if (wasDual && res.data.dual_model_b) {
        chatState.dualModelB = res.data.dual_model_b;
        const dualSel = document.getElementById('chat-dual-model-select');
        if (dualSel && dualSel.querySelector(`option[value="${res.data.dual_model_b}"]`)) {
            dualSel.value = res.data.dual_model_b;
        }
    }

    renderChatHeader();
    renderMessages();
    renderAttachments('chat-attachments');

    // Toggle-States auch in localStorage speichern damit sie beim Reload stimmen
    saveSettingsToLocalStorage();

    // Refresh artifact panel if open
    if (chatState.artifactPanelOpen) {
        renderAttachedArtifacts();
    }

    // Sidebar-Kontext (Privat / Projektübergreifend / Kunde) anhand der
    // geladenen Konversation setzen, damit der Chat in der Liste sichtbar ist
    // (z.B. wenn er ueber /chat/{id} direkt aufgerufen wird).
    let wantCtx = null;
    if (res.data.is_private) {
        wantCtx = { type: 'private', customerId: null };
    } else if (res.data.customer_id) {
        wantCtx = { type: 'customer', customerId: parseInt(res.data.customer_id, 10) };
    } else {
        wantCtx = { type: 'projectwide', customerId: null };
    }
    const curCtx = chatState.currentContext;
    const ctxChanged = !curCtx
        || curCtx.type !== wantCtx.type
        || (curCtx.customerId || null) != (wantCtx.customerId || null);
    if (ctxChanged) {
        chatState.currentContext = wantCtx;
        chatState.activeFolderId = null;
        try { await loadContextFolders(); } catch (_) {}
        updateContextBanner();
    }

    // Navigate to the folder containing this conversation
    const convFolderId = res.data.project_id || null;
    if (convFolderId != chatState.currentFolderId) {
        chatState.currentFolderId = convFolderId;
    }
    renderSidebar();

    // Aktive Konversation in der Sidebar sichtbar machen
    requestAnimationFrame(() => {
        const el = document.querySelector(`.finder-item[data-conv="${id}"]`);
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    });

    // Eingabefeld: Entwurf dieses Chats wiederherstellen statt bedingungslos leeren.
    // Genau hier ging Michis vorformulierte Nachricht bisher verloren.
    const ta = document.getElementById('chat-textarea');
    const hatteEntwurf = restoreDraft(id, 'chat-textarea');
    if (hatteEntwurf) {
        // Cursor ans Ende, damit direkt weitergeschrieben werden kann
        ta.focus();
        ta.setSelectionRange(ta.value.length, ta.value.length);
    }
    // Scroll-Position kommt aus renderMessages() (oben in dieser Funktion) —
    // entweder ans Ende oder zur letzten via TOC besuchten Stelle.
}

function renderChatHeader() {
    const c = chatState.activeConv;
    if (!c) return;

    const titleEl = document.getElementById('chat-title');
    // Nicht ueberschreiben, wenn der Nutzer gerade tippt (contenteditable)
    if (titleEl && titleEl.dataset.editing !== '1') {
        titleEl.textContent = c.title || 'Neuer Chat';
    }

    // Kunden- und Status-Pills im Stil der globalen .thx-chip (gleiche Optik wie LAM/PP).
    let badges = '';
    if (c.customer_id) {
        const cust = customers.find(cu => cu.id == c.customer_id);
        if (cust) {
            badges += `<span class="thx-chip" title="Kunden-Kontext: ${esc(cust.name)}">${esc(cust.name)}</span>`;
        }
    }
    if (c.is_private) {
        badges += `<span class="thx-chip" title="Privater Chat">
            <span class="material-symbols-rounded" style="font-size:12px;">lock</span> Privat
        </span>`;
    }
    if (c.share_count > 0) {
        badges += `<span class="thx-chip" onclick="openShareDialog('conversation')" style="cursor:pointer;" title="Geteilt mit ${c.share_count} Person${c.share_count !== 1 ? 'en' : ''}">
            <span class="material-symbols-rounded" style="font-size:12px;">people</span> ${c.share_count}
        </span>`;
    }
    // Fremder Chat: klar kennzeichnen, wessen Chat das ist
    if (c.is_owner === false) {
        badges += `<span class="thx-chip chat-foreign-badge" title="Dies ist der Chat von ${esc(c.owner_name || 'einer anderen Person')}">
            <span class="material-symbols-rounded" style="font-size:12px;">person</span> Chat von ${esc(c.owner_name || '?')}
        </span>`;
    }
    // „Für alle freigegeben"-Hinweis
    if (c.write_open) {
        badges += `<span class="thx-chip" onclick="openShareDialog('conversation')" style="cursor:pointer;" title="Alle, die diesen Chat sehen, dürfen schreiben">
            <span class="material-symbols-rounded" style="font-size:12px;">groups</span> Alle dürfen schreiben
        </span>`;
    }
    // Eigener Chat mit offenen Zugriffsanfragen
    if (c.is_owner && c.pending_access_requests > 0) {
        badges += `<span class="thx-chip chat-requests-badge" onclick="openShareDialog('conversation')" title="Offene Zugriffsanfragen — zum Freigeben anklicken">
            <span class="material-symbols-rounded" style="font-size:12px;">how_to_reg</span> ${c.pending_access_requests} Anfrage${c.pending_access_requests !== 1 ? 'n' : ''}
        </span>`;
    }
    if (chatState.attachedArtifacts.length > 0) {
        badges += `<span class="thx-chip" onclick="toggleArtifactPanel()" style="cursor:pointer;" title="Artefakte angehängt">
            <span class="material-symbols-rounded" style="font-size:12px;">library_books</span> ${chatState.attachedArtifacts.length} Artefakt${chatState.attachedArtifacts.length !== 1 ? 'e' : ''}
        </span>`;
    }
    document.getElementById('chat-badges').innerHTML = badges;
    applyChatWriteState();
}

// Eingabe sperren + Read-only-Hinweis bei fremden Chats ohne Schreibzugriff
function applyChatWriteState() {
    const c = chatState.activeConv;
    const wrap = document.getElementById('chat-input-wrapper');
    const banner = document.getElementById('chat-readonly-banner');
    if (!wrap || !banner) return;

    // „Teilen"-Button: nur fuer den Owner sichtbar, mit Badge fuer offene Anfragen
    const shareBtn = document.getElementById('btn-share-chat');
    const shareBadge = document.getElementById('btn-share-badge');
    // Schreibrechte-Button: für ALLE sichtbar, die den Chat sehen — jeder darf sehen, wer schreiben darf.
    // (Ändern selbst bleibt serverseitig auf Ersteller/Admin beschränkt.)
    if (shareBtn) shareBtn.style.display = (c && c.id) ? '' : 'none';
    if (shareBadge) {
        const n = (c && c.pending_access_requests) || 0;
        shareBadge.style.display = n > 0 ? '' : 'none';
        shareBadge.textContent = n;
    }
    // Neuer/eigener Chat: immer schreiben. Sonst can_write vom Server.
    const canWrite = !c || c.is_owner === true || c.is_owner === undefined || c.can_write === true || c.can_write === undefined;
    const foreignHint = document.getElementById('chat-foreign-write-hint');
    if (canWrite) {
        wrap.style.display = '';
        banner.style.display = 'none';
        // Fremder Chat MIT Schreibzugriff (geteilt/übernommen): dezenter Hinweis am Eingabefeld
        if (foreignHint) {
            if (c && c.id && c.is_owner === false) {
                foreignHint.style.display = 'flex';
                foreignHint.innerHTML = `<span class="material-symbols-rounded">edit_note</span>
                    Du schreibst im Chat von <strong>${esc(c.owner_name || 'einer anderen Person')}</strong>. Deine Beiträge werden mit Deinem Namen gekennzeichnet.`;
            } else {
                foreignHint.style.display = 'none';
            }
        }
        return;
    }
    if (foreignHint) foreignHint.style.display = 'none';
    wrap.style.display = 'none';
    banner.style.display = 'flex';
    const owner = esc(c.owner_name || 'einer anderen Person');
    const pending = !!c.access_request_pending;
    // Admins können den Chat ohne Freigabe übernehmen
    const adminBtn = chatState.isAdmin
        ? `<button class="cr-btn cr-btn-takeover" id="cr-takeover-btn" onclick="takeOverChat()" title="Als Admin sofort Schreibzugriff verschaffen (ohne Freigabe)">
               <span class="material-symbols-rounded">admin_panel_settings</span> Chat übernehmen
           </button>`
        : '';
    banner.innerHTML = `
        <span class="material-symbols-rounded cr-icon">visibility</span>
        <span class="cr-text">Dies ist der Chat von <strong>${owner}</strong>. Du kannst mitlesen, aber nicht schreiben.</span>
        <button class="cr-btn cr-btn-request" id="cr-request-btn" onclick="requestChatAccess()" ${pending ? 'disabled' : ''}>
            <span class="material-symbols-rounded">${pending ? 'check' : 'lock_open'}</span>
            ${pending ? 'Anfrage gesendet' : 'Schreibzugriff anfragen'}
        </button>
        ${adminBtn}`;
}

async function takeOverChat() {
    const c = chatState.activeConv;
    if (!c || !c.id) return;
    const btn = document.getElementById('cr-takeover-btn');
    if (btn) btn.disabled = true;
    const res = await api('POST', '/chat/conversations/' + c.id + '/take-over', {});
    if (res.success) {
        c.can_write = true;
        applyChatWriteState();
        renderChatHeader();
        App.showNotification('Chat übernommen — Du hast jetzt Schreibzugriff', 'success');
        const ta = document.getElementById('chat-textarea');
        if (ta) ta.focus();
    } else {
        if (btn) btn.disabled = false;
        App.showNotification(res.message || 'Übernahme fehlgeschlagen', 'error');
    }
}

async function requestChatAccess() {
    const c = chatState.activeConv;
    if (!c || !c.id) return;
    const btn = document.getElementById('cr-request-btn');
    if (btn) btn.disabled = true;
    const res = await api('POST', '/chat/conversations/' + c.id + '/access-request', {});
    if (res.success) {
        c.access_request_pending = true;
        applyChatWriteState();
        App.showNotification('Anfrage an ' + (c.owner_name || 'den Ersteller') + ' gesendet', 'success');
    } else {
        if (btn) btn.disabled = false;
        App.showNotification(res.message || 'Anfrage fehlgeschlagen', 'error');
    }
}

function getModelName(modelId) {
    const sel = document.getElementById('chat-model-select');
    const opt = sel.querySelector(`option[value="${modelId}"]`);
    return opt ? opt.textContent.trim() : modelId;
}

function getModelInitial(modelId) {
    if (!modelId) return 'AI';
    const id = String(modelId).toLowerCase();
    if (id.includes('claude')) return 'C';
    if (id.includes('gemini')) return 'G';
    if (id.includes('mistral')) return 'M';
    if (id.includes('gpt')) return 'G';
    return 'AI';
}

// ==========================================
// Messages
// ==========================================
// Aktions-Buttons einer Assistant-Nachricht (zentral, damit renderMessages und der
// Stream-Abschluss exakt dieselben Buttons mit derselben Nachrichten-ID erzeugen).
// Waehrend des Streamings hat die Nachricht eine temporaere ID (Date.now()-basiert,
// ~1.7e12). Erst wenn die echte, kleine DB-ID vorliegt, blenden wir die ID-abhaengigen
// Buttons ein — sonst wuerde ein Klick auf eine nicht existierende ID laufen.
function assistantActionsHtml(id) {
    const copy = `<button class="thx-icon-btn" onclick="copyMessage(this)" title="Kopieren"><span class="material-symbols-rounded">content_copy</span></button>`;
    const isSaved = id && id < 1e12;
    if (!isSaved) return copy;
    return copy
        + `<button class="thx-icon-btn" onclick="downloadMessageAsDocx(${id},this)" title="Als Word herunterladen"><span class="material-symbols-rounded">download</span></button>`
        + `<button class="thx-icon-btn" onclick="saveAsKnowledge(${id},this)" title="Als Wissen speichern"><span class="material-symbols-rounded">library_add</span></button>`
        + `<button class="thx-icon-btn" onclick="showMessageInfo(${id})" title="Details: Prompt, Tokens, Modell, Wissen"><span class="material-symbols-rounded">info</span></button>`;
}

function renderMessages() {
    const mc = document.getElementById('chat-messages');
    if (!chatState.messages.length) {
        mc.innerHTML = '';
        return;
    }

    mc.innerHTML = chatState.messages.map(m => {
        if (m.role === 'system') return '';
        if (m.role === 'dual') return renderDualMessageHtml(m);

        const isUser = m.role === 'user';
        let body = '';
        if (isUser) {
            body = esc(m.content);
        } else {
            body = renderMarkdown(m.content);
        }

        let attachmentsHtml = '';
        const atts = m.attachments || [];
        if (atts.length) {
            attachmentsHtml = '<div class="chat-msg-attachments">' +
                atts.map(a => {
                    const wc = (a.word_count || 0).toLocaleString();
                    const inner = `📄 ${esc(a.filename)} (${wc} W.)`;
                    if (a.knowledge_document_id) {
                        return `<a class="thx-chip is-active" href="/wissen/${a.knowledge_document_id}" target="_blank" title="Im Wissen öffnen">${inner}<span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;margin-left:3px;">library_books</span></a>`;
                    }
                    return `<span class="thx-chip">${inner}</span>`;
                }).join('') +
                '</div>';
        }

        const artifactsHtml = (!isUser && m.artifacts_used) ? renderArtifactsUsedHtml(m.artifacts_used) : '';
        const knowledgeHtml = (!isUser && m.knowledge_used) ? renderKnowledgeUsedHtml(m.knowledge_used) : '';

        // User-Message-Header: Sender pro Message (sender_name/sender_abbreviation),
        // Fallback: Conversation-Creator, dann eingeloggter User
        let userName = currentUserName, userAbbr = currentUserAbbr;
        if (isUser) {
            const c = chatState.activeConv;
            if (m.sender_name) { userName = m.sender_name; userAbbr = m.sender_abbreviation || ''; }
            else if (c && c.creator_name && c.user_id != <?= $userId ?>) {
                userName = c.creator_name; userAbbr = c.creator_abbreviation || '';
            }
            if (!userAbbr && userName) {
                const parts = userName.trim().split(/\s+/);
                userAbbr = parts.length >= 2
                    ? (parts[0].charAt(0) + parts[parts.length-1].charAt(0)).toUpperCase()
                    : userName.charAt(0).toUpperCase();
            }
        }

        const timeBadge = m.created_at
            ? `<span class="msg-time" title="${esc(formatFullDateTime(m.created_at))}">${esc(formatRelativeTime(m.created_at))}</span>`
            : '';

        const header = isUser
            ? `<div class="chat-msg-header">
                <span class="msg-avatar msg-avatar-user" title="${esc(userName)}">${esc(userAbbr || 'DU')}</span>
                <span>${esc(userName)}</span>
                ${timeBadge}
               </div>`
            : `<div class="chat-msg-header">
                <span class="msg-avatar msg-avatar-assistant" title="KI">${getModelInitial(m.model_used)}</span>
                ${m.model_used ? `<span class="chat-model-badge ${m.auto_mode ? 'auto-mode' : ''}">${m.auto_mode ? '&#9889; ' : ''}${esc(getModelName(m.model_used))}</span>` : ''}
                ${timeBadge}
                <div class="msg-actions">${assistantActionsHtml(m.id)}</div>
               </div>`;

        // Anker-ID: User-Prompts werden als Anker fuer die TOC genutzt.
        const anchorId = (m.id ? ('chat-anchor-' + m.id) : '');
        return `<div class="chat-msg ${m.role}" data-msg-id="${m.id}"${anchorId ? ` id="${anchorId}"` : ''}>
            ${header}
            <div class="chat-msg-body ${isUser ? '' : 'markdown-rendered'}">${body}</div>
            ${attachmentsHtml}
            ${artifactsHtml}
            ${knowledgeHtml}
        </div>`;
    }).join('');

    addCodeBlockButtons();
    renderChatToc();

    // Scroll-Ziel: zuletzt via TOC besuchte Stelle DIESES Chats, sonst ganz nach unten.
    // Immer instant (kein Smooth) — bei langen Chats nervt das Auto-Scrolling.
    requestAnimationFrame(() => {
        const lastId = chatState.activeId ? chatState.lastTocByChat[chatState.activeId] : null;
        const anchor = lastId ? document.getElementById('chat-anchor-' + lastId) : null;
        if (anchor) {
            // Position des Ankers relativ zum Scroll-Container
            const top = anchor.offsetTop;
            mc.scrollTop = Math.max(0, top - 8);
        } else {
            mc.scrollTop = mc.scrollHeight;
        }
        updateChatTocActive();
    });
}

/** Erzeugt links das Mini-Inhaltsverzeichnis: pro User-Prompt einen anklickbaren Strich.
 *  Beim Hovern erscheint die ersten ~120 Zeichen der Nachricht als Tooltip. */
function renderChatToc() {
    const toc = document.getElementById('chat-toc');
    if (!toc) return;
    const userMsgs = (chatState.messages || []).filter(m => m.role === 'user' && m.id);
    if (userMsgs.length < 2) { toc.innerHTML = ''; return; }
    toc.innerHTML = userMsgs.map((m, i) => {
        const preview = String(m.content || '').replace(/\s+/g, ' ').trim().substring(0, 120);
        return `<button class="chat-toc-item" type="button"
                        data-msg-id="${m.id}"
                        data-preview="${esc(preview)}"
                        title="Prompt ${i + 1}: ${esc(preview)}"
                        onclick="scrollToChatAnchor(${m.id})"></button>`;
    }).join('');
    installTocKeyboardNav();
    updateChatTocActive();
}

/** Pfeil-Hoch/Runter (+ Home/End) navigieren durch die TOC-Eintraege.
 *  Greift, sobald der Fokus innerhalb der TOC liegt — also nach Klick auf einen
 *  Strich oder auf die TOC-Spalte selbst. */
function installTocKeyboardNav() {
    const toc = document.getElementById('chat-toc');
    if (!toc || toc.dataset.kbReady === '1') return;
    toc.dataset.kbReady = '1';
    // Container selbst fokussierbar machen, damit ein Klick irgendwo auf die TOC
    // den Fokus dorthin holt — dann greifen die Pfeiltasten sofort.
    toc.setAttribute('tabindex', '0');
    toc.addEventListener('click', (e) => {
        if (e.target === toc) {
            // Klick auf leere TOC-Flaeche → den aktiven Strich fokussieren
            const active = toc.querySelector('.chat-toc-item.is-active') || toc.querySelector('.chat-toc-item');
            if (active) active.focus();
        }
    });
    toc.addEventListener('keydown', (e) => {
        if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(e.key)) return;
        e.preventDefault();
        const items = Array.from(toc.querySelectorAll('.chat-toc-item'));
        if (!items.length) return;
        let idx = items.indexOf(document.activeElement);
        if (idx < 0) {
            const activeIdx = items.findIndex(it => it.classList.contains('is-active'));
            idx = activeIdx >= 0 ? activeIdx : 0;
        }
        let next = idx;
        if (e.key === 'ArrowUp')     next = Math.max(0, idx - 1);
        else if (e.key === 'ArrowDown') next = Math.min(items.length - 1, idx + 1);
        else if (e.key === 'Home')   next = 0;
        else if (e.key === 'End')    next = items.length - 1;
        const target = items[next];
        if (!target) return;
        target.focus();
        const msgId = parseInt(target.dataset.msgId, 10);
        if (msgId) scrollToChatAnchor(msgId);
    });
}

window.scrollToChatAnchor = function(msgId) {
    const el = document.getElementById('chat-anchor-' + msgId);
    const mc = document.getElementById('chat-messages');
    if (!el || !mc) return;
    // getBoundingClientRect liefert die tatsaechliche Viewport-Position. Daraus
    // berechnen wir den absoluten Scroll-Offset im Container — robuster als
    // offsetTop, weil offsetParent unter Flex-Layouts falsche Werte gibt.
    const elRect = el.getBoundingClientRect();
    const mcRect = mc.getBoundingClientRect();
    const targetScrollTop = mc.scrollTop + (elRect.top - mcRect.top) - 8;
    mc.scrollTop = Math.max(0, targetScrollTop);
    // Merke die letzte angesprungene Position pro Chat — beim Wiederkehren landen wir dort
    if (chatState.activeId) {
        chatState.lastTocByChat[chatState.activeId] = msgId;
    }
    updateChatTocActive();
};

/** Markiert den TOC-Eintrag, der der aktuell sichtbaren User-Message am naechsten ist. */
function updateChatTocActive() {
    const toc = document.getElementById('chat-toc');
    if (!toc) return;
    const mc = document.getElementById('chat-messages');
    if (!mc) return;
    const items = toc.querySelectorAll('.chat-toc-item');
    if (!items.length) return;
    // Spezialfall: am Ende des Scroll-Containers angekommen — dann ist immer die
    // LETZTE Nachricht „aktiv", egal wo der Anker pixelmaessig liegt.
    const atBottom = (mc.scrollHeight - mc.scrollTop - mc.clientHeight) < 4;
    if (atBottom) {
        const lastId = items[items.length - 1].dataset.msgId;
        items.forEach(it => it.classList.toggle('is-active', it.dataset.msgId === lastId));
        return;
    }
    const mcTop = mc.getBoundingClientRect().top;
    let activeId = null;
    items.forEach(it => {
        const id = it.dataset.msgId;
        const anchor = document.getElementById('chat-anchor-' + id);
        if (!anchor) return;
        const top = anchor.getBoundingClientRect().top - mcTop;
        if (top <= 80) activeId = id;
    });
    items.forEach(it => it.classList.toggle('is-active', it.dataset.msgId === activeId));
}

// Scroll-Listener im Messages-Container fuer die TOC-Aktiv-Markierung
document.addEventListener('DOMContentLoaded', () => {
    const mc = document.getElementById('chat-messages');
    if (mc) {
        let t = null;
        mc.addEventListener('scroll', () => {
            if (t) cancelAnimationFrame(t);
            t = requestAnimationFrame(updateChatTocActive);
        });
    }
});

function renderMarkdown(text) {
    if (!text) return '';
    if (!libsReady || !window.markedLib) return esc(text);
    try {
        return window.markedLib.parse(text);
    } catch(e) {
        return esc(text);
    }
}

function addCodeBlockButtons() {
    document.querySelectorAll('#chat-messages pre').forEach(pre => {
        if (pre.querySelector('.code-block-header')) return;
        const code = pre.querySelector('code');
        if (!code) return;

        const lang = (code.className.match(/language-(\w+)/) || [])[1] || '';
        const header = document.createElement('div');
        header.className = 'code-block-header';
        header.innerHTML = `<span>${lang}</span><button class="code-block-copy" onclick="copyCodeBlock(this)"><span class="material-symbols-rounded" style="font-size:14px">content_copy</span> Kopieren</button>`;
        pre.insertBefore(header, code);
    });
}

// ==========================================
// Send Message
// ==========================================
async function sendWelcomeMessage() {
    const ta = document.getElementById('welcome-textarea');
    const msg = ta.value.trim();
    if (!msg || chatState.isStreaming) return;

    if (!chatState.currentContext) {
        App.showNotification('Bitte zuerst einen Kontext wählen', 'error');
        updateContextBanner();
        return;
    }

    const model = document.getElementById('welcome-model-select').value;
    const ctx = chatState.currentContext;

    // Create conversation first
    const res = await api('POST', '/chat/conversations', {
        title: 'Neuer Chat',
        model: model,
        customer_id: ctx.type === 'customer' ? ctx.customerId : null,
        is_private: ctx.type === 'private' ? 1 : 0,
        projectwide: ctx.type === 'projectwide' ? 1 : 0,
    });
    if (!res.success) {
        App.showNotification(res.message || 'Fehler', 'error');
        return;
    }

    const convId = res.data.id;

    // Switch to chat view
    chatState.activeId = convId;
    chatState.activeConv = { id: convId, title: 'Neuer Chat', model, messages: [], user_id: <?= $userId ?>, is_owner: true, can_write: true };
    chatState.messages = [];

    document.getElementById('welcome-screen').style.display = 'none';
    const cv = document.getElementById('chat-view');
    cv.style.display = 'flex';

    document.getElementById('chat-model-select').value = model;
    renderChatHeader();

    // Move textarea content
    const chatTa = document.getElementById('chat-textarea');
    chatTa.value = '';

    // Transfer attachments
    const attachments = [...chatState.pendingAttachments];
    chatState.pendingAttachments = [];
    ta.value = '';
    clearDraft(null);   // Welcome-Entwurf ist jetzt ein echter Chat — Entwurf verwerfen

    // Reload sidebar
    loadConversations();

    // Now send
    // Save settings for next new chat
    saveSettingsToLocalStorage();

    if (chatState.dualMode) {
        const modelB = document.getElementById('welcome-dual-model-select').value;
        chatState.dualModelB = modelB;
        await doSendDualMessage(convId, msg, model, modelB, attachments);
    } else {
        await doSendMessage(convId, msg, model, attachments);
    }
}

async function sendMessage() {
    const ta = document.getElementById('chat-textarea');
    const msg = ta.value.trim();
    if (!msg || chatState.isStreaming) return;
    if (!chatState.activeId) return;
    // Fremder Chat ohne Schreibzugriff: nicht senden (Eingabe ist eh ausgeblendet)
    const ac = chatState.activeConv;
    if (ac && ac.is_owner === false && ac.can_write === false) {
        App.showNotification('Kein Schreibzugriff — fordere ihn beim Ersteller an.', 'error');
        return;
    }

    const model = document.getElementById('chat-model-select').value;
    const attachments = [...chatState.pendingAttachments];
    chatState.pendingAttachments = [];
    ta.value = '';
    clearDraft(chatState.activeId);   // abgeschickt = kein Entwurf mehr
    autoResize(ta);
    renderAttachments('chat-attachments');

    // Save settings for next new chat
    saveSettingsToLocalStorage();

    if (chatState.dualMode) {
        const modelB = document.getElementById('chat-dual-model-select').value;
        chatState.dualModelB = modelB;
        await doSendDualMessage(chatState.activeId, msg, model, modelB, attachments);
    } else {
        await doSendMessage(chatState.activeId, msg, model, attachments);
    }
}

// ==========================================
// Stream-Watchdog: erkennt stockende SSE-Streams und bietet Retry/Abort an
// ==========================================
const STREAM_STALL_SECONDS = 25;

function createStreamWatchdog(handlers) {
    let lastActivity = Date.now();
    let bannerShown = false;
    const interval = setInterval(() => {
        const since = (Date.now() - lastActivity) / 1000;
        if (since > STREAM_STALL_SECONDS && !bannerShown) {
            bannerShown = true;
            showStreamStallBanner(handlers);
        }
    }, 1500);
    return {
        ping: () => {
            lastActivity = Date.now();
            if (bannerShown) { hideStreamStallBanner(); bannerShown = false; }
        },
        stop: () => { clearInterval(interval); hideStreamStallBanner(); }
    };
}

function showStreamStallBanner(handlers) {
    let banner = document.getElementById('stream-stall-banner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'stream-stall-banner';
        banner.className = 'stream-stall-banner';
        document.body.appendChild(banner);
    }
    banner.innerHTML = `
        <span class="material-symbols-rounded" style="color:#f59e0b;">warning</span>
        <div style="flex:1;min-width:0;">
            <strong>Antwort stockt</strong>
            <small>Die KI braucht ungewöhnlich lange – möglicherweise überlastet.</small>
        </div>
        <button class="thx-btn thx-btn-primary thx-btn-small" id="stream-stall-retry">
            <span class="material-symbols-rounded">restart_alt</span>
            Neu versuchen
        </button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" id="stream-stall-cancel">Abbrechen</button>
    `;
    banner.classList.add('open');
    document.getElementById('stream-stall-retry').onclick = () => { hideStreamStallBanner(); handlers.retry(); };
    document.getElementById('stream-stall-cancel').onclick = () => { hideStreamStallBanner(); handlers.cancel(); };
}

function hideStreamStallBanner() {
    const banner = document.getElementById('stream-stall-banner');
    if (banner) { banner.classList.remove('open'); setTimeout(() => banner.remove(), 200); }
}

async function doSendMessage(convId, message, model, attachments) {
    chatState.isStreaming = true;
    updateSendButtons();

    // Add user message to UI
    const nowIso = new Date().toISOString();
    const userMsg = {
        id: Date.now(),
        role: 'user',
        content: message,
        model_used: model,
        attachments: attachments,
        created_at: nowIso,
    };
    chatState.messages.push(userMsg);

    // Add placeholder for assistant
    const assistantMsg = {
        id: Date.now() + 1,
        role: 'assistant',
        content: '',
        model_used: model,
        attachments: [],
        created_at: nowIso,
    };
    chatState.messages.push(assistantMsg);
    renderMessages();
    renderStreamingMessage('', model, true);

    // Stream-Watchdog + Abort-Controller
    const abortCtrl = new AbortController();
    chatState._activeStream = abortCtrl;
    const watchdog = createStreamWatchdog({
        retry: () => { abortCtrl.abort('retry'); chatState._streamRetry = { fn: doSendMessage, args: [convId, message, model, attachments] }; },
        cancel: () => { abortCtrl.abort('user-cancel'); },
    });

    // Start SSE stream via fetch
    try {
        const response = await fetch(App.apiUrl + '/chat/conversations/' + convId + '/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': App.csrfToken
            },
            body: JSON.stringify({ message, model, attachments, use_web_search: chatState.useWebSearch, use_knowledge: chatState.useKnowledge, use_rules: chatState.useRules }),
            signal: abortCtrl.signal
        });

        if (!response.ok) {
            let errMsg = 'Fehler ' + response.status;
            try {
                const errData = await response.json();
                errMsg = errData.message || errMsg;
            } catch(e) {}
            throw new Error(errMsg);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let fullContent = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            watchdog.ping();

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                try {
                    const data = JSON.parse(line.slice(6));
                    if (data.type === 'auto_info') {
                        // Auto-Mode: Server hat Modell + Artefakte gewaehlt
                        if (data.model) {
                            model = data.model;
                            assistantMsg.model_used = data.model;
                            assistantMsg.auto_mode = true;
                            renderStreamingMessage(fullContent, data.model, true);
                        }
                        if (data.artifacts && data.artifacts.length > 0) {
                            loadAttachedArtifacts();
                            App.showNotification('Automatik: ' + (data.model_name || data.model) + ' + ' + data.artifacts.length + ' Artefakt(e)', 'info');
                        } else if (data.model_name) {
                            App.showNotification('Automatik: ' + data.model_name, 'info');
                        }
                    } else if (data.type === 'web_search') {
                        if (data.status === 'searching') {
                            // Suche laeuft — Indikator anzeigen
                            fullContent += '\n\n<div class="web-search-indicator"><span class="material-symbols-rounded spin" style="font-size:14px">travel_explore</span> Suche: ' + (data.queries || []).join(', ') + '...</div>\n\n';
                            renderStreamingMessage(fullContent, model, true);
                        } else if (data.status === 'done') {
                            // Suche fertig — Indikator entfernen, Quellen merken
                            fullContent = fullContent.replace(/<div class="web-search-indicator">[\s\S]*?<\/div>/g, '');
                            assistantMsg._webSources = data.sources || [];
                            renderStreamingMessage(fullContent, model, true);
                        } else if (data.status === 'error') {
                            fullContent = fullContent.replace(/<div class="web-search-indicator">[\s\S]*?<\/div>/g, '');
                            renderStreamingMessage(fullContent, model, true);
                        }
                    } else if (data.type === 'artifacts_used') {
                        assistantMsg.artifacts_used = data.artifacts || [];
                    } else if (data.type === 'customer_detected') {
                        chatState.detectedCustomer = data;
                        showCustomerDetectedBanner(data);
                    } else if (data.type === 'knowledge_used') {
                        assistantMsg.knowledge_used = data.chunks || [];
                    } else if (data.type === 'token') {
                        fullContent += data.content;
                        renderStreamingMessage(fullContent, model, true);
                    } else if (data.type === 'truncated') {
                        // Antwort lief ins Längen-Limit — sichtbar machen statt still abbrechen
                        assistantMsg._truncated = true;
                        App.showNotification(data.message || 'Die Antwort wurde abgeschnitten. Schreib „Weiter", um sie fortzusetzen.', 'warning');
                    } else if (data.type === 'done') {
                        assistantMsg.content = fullContent;
                        assistantMsg.id = data.message_id;
                        assistantMsg.model_used = data.model || model;
                        assistantMsg.tokens_input = data.tokens_input;
                        assistantMsg.tokens_output = data.tokens_output;
                        if (data.auto_mode) assistantMsg.auto_mode = true;
                        if (assistantMsg._webSources) assistantMsg.web_sources = assistantMsg._webSources;
                        if (data.artifacts_used && data.artifacts_used.length > 0) {
                            assistantMsg.artifacts_used = data.artifacts_used;
                        }
                        renderStreamingMessage(fullContent, data.model || model, false);
                        // Aktions-Buttons mit der jetzt bekannten echten Nachrichten-ID neu
                        // setzen (renderStreamingMessage baut nur den Text neu) — sonst behalten
                        // Info/Download/Wissen die temporaere ID und schlagen fehl.
                        if (assistantMsg.id) {
                            const mcEl = document.getElementById('chat-messages');
                            const assistantNodes = mcEl ? mcEl.querySelectorAll('.chat-msg.assistant') : [];
                            const lastNode = assistantNodes.length ? assistantNodes[assistantNodes.length - 1] : null;
                            if (lastNode) {
                                lastNode.dataset.msgId = assistantMsg.id;
                                const actions = lastNode.querySelector('.msg-actions');
                                if (actions) actions.innerHTML = assistantActionsHtml(assistantMsg.id);
                            }
                        }
                        // Artefakte anzeigen
                        if (assistantMsg.artifacts_used && assistantMsg.artifacts_used.length > 0) {
                            renderArtifactsUsedInMessage(assistantMsg);
                        }
                        // Web-Quellen anzeigen
                        if (assistantMsg.web_sources && assistantMsg.web_sources.length > 0) {
                            renderWebSources(assistantMsg.web_sources);
                        }
                    } else if (data.type === 'error') {
                        App.showNotification(data.message || 'Fehler bei der Antwort', 'error');
                        chatState.messages.pop();
                        renderMessages();
                    }
                } catch(e) {}
            }
        }
    } catch(err) {
        if (err.name !== 'AbortError') {
            App.showNotification('Verbindungsfehler: ' + err.message, 'error');
            chatState.messages.pop();
            renderMessages();
        }
    } finally {
        watchdog.stop();
        chatState._activeStream = null;
    }

    chatState.isStreaming = false;
    updateSendButtons();

    // Retry nach Stall? Nutzer → letzte beiden Messages weg, neu starten
    if (chatState._streamRetry) {
        const r = chatState._streamRetry;
        chatState._streamRetry = null;
        chatState.messages.pop(); // assistant placeholder
        chatState.messages.pop(); // user msg
        renderMessages();
        await r.fn.apply(null, r.args);
        return;
    }

    // Update title if it was auto-generated
    if (chatState.activeConv && chatState.activeConv.title === 'Neuer Chat') {
        const r = await api('GET', '/chat/conversations/' + convId);
        if (r.success && r.data.title !== 'Neuer Chat') {
            chatState.activeConv.title = r.data.title;
            renderChatHeader();
            loadConversations();
        }
    }
}

function showCustomerDetectedBanner(data) {
    // Banner direkt im Chat anzeigen — fragt User ob fuer alle Folge-Nachrichten merken
    const mc = document.getElementById('chat-messages');
    if (!mc) return;
    // Nur einen Banner gleichzeitig
    const existing = mc.querySelector('.cd-banner');
    if (existing) existing.remove();

    const banner = document.createElement('div');
    banner.className = 'cd-banner';
    banner.style.cssText = 'background:linear-gradient(135deg,#e6f0fa,#f1f7fc);border:1px solid #b3d0ec;border-radius:10px;padding:0.75rem 1rem;margin:0.5rem 0;display:flex;align-items:center;gap:0.75rem;font-size: var(--d-fs-sm);';
    banner.innerHTML = `
        <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:20px;">person_search</span>
        <div style="flex:1;">
            Erkannt: <strong>${data.customer_name || ''}</strong>
            <small style="color:#64748b;display:block;">Wissen wird gerade auf diesen Kunden gefiltert. Fuer alle weiteren Nachrichten in diesem Chat merken?</small>
        </div>
        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="rememberCustomer(${data.customer_id}, this)">Ja, merken</button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="this.parentElement.remove()">Nein</button>
    `;
    mc.appendChild(banner);
}

window.rememberCustomer = async function(customerId, btn) {
    btn.disabled = true;
    try {
        if (chatState.activeId) {
            await App.put('/chat/conversations/' + chatState.activeId, { customer_id: customerId });
            App.showNotification('Kunde gemerkt fuer diesen Chat', 'success');
        }
        const banner = btn.closest('.cd-banner');
        if (banner) banner.remove();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false;
    }
};

function renderStreamingMessage(content, model, streaming) {
    const mc = document.getElementById('chat-messages');
    // Letzten Assistant-Bubble holen. NICHT ':last-child' nutzen — andere Elemente
    // (z.B. das Kunden-Erkannt-Banner .cd-banner) werden ebenfalls in #chat-messages
    // angehaengt und wuerden den Bubble als letztes Kind verdraengen → Selektor liefert
    // null → Stream rendert nicht und der Platzhalter bleibt leer (Bugfix 23.06.2026).
    const assistants = mc.querySelectorAll('.chat-msg.assistant');
    const lastMsg = assistants.length ? assistants[assistants.length - 1] : null;
    if (lastMsg) {
        const body = lastMsg.querySelector('.chat-msg-body');
        body.innerHTML = renderMarkdown(content) + (streaming ? '<span class="streaming-cursor"></span>' : '');
        body.classList.add('markdown-rendered');

        // Update model badge
        const badge = lastMsg.querySelector('.chat-model-badge');
        if (badge) {
            const lastAssistant = chatState.messages[chatState.messages.length - 1];
            const isAuto = lastAssistant && lastAssistant.auto_mode;
            badge.className = 'chat-model-badge' + (isAuto ? ' auto-mode' : '');
            badge.innerHTML = (isAuto ? '&#9889; ' : '') + esc(getModelName(model));
        }

        if (!streaming) addCodeBlockButtons();
    }
    smartStreamScroll(mc, lastMsg, streaming);
}

/**
 * Smart-Scroll während Streaming:
 *  - beim ALLERERSTEN Token (oder dem Start einer neuen Antwort): einmal so scrollen, dass
 *    der Anfang der KI-Antwort oben sichtbar ist → User kann sofort lesen
 *  - bei weiteren Tokens: NICHT mitscrollen, außer der User klebt ohnehin am unteren Rand
 *  - sobald der User selbst scrollt, hört das Auto-Folgen für diese Antwort auf
 */
function smartStreamScroll(mc, lastMsg, streaming) {
    if (!mc) return;
    if (!streaming) return; // bei Final-Message kein Auto-Scroll mehr — User liest evtl. schon weiter oben

    if (lastMsg && lastMsg.dataset.scrollAnchored !== '1') {
        // Erstes Update für diese Antwort → Anfang oben sichtbar
        lastMsg.dataset.scrollAnchored = '1';
        // Header-Höhe abziehen (Sticky-Bereich) damit Begrüßung nicht abgeschnitten ist
        const headerOffset = 12;
        const top = lastMsg.offsetTop - headerOffset;
        mc.scrollTo({ top, behavior: 'smooth' });
        // Listener: wenn User aktiv scrollt, "Folgen" beim Bottom auch zukünftig nicht reaktivieren
        if (!mc.dataset.scrollListenerAttached) {
            mc.dataset.scrollListenerAttached = '1';
            mc.addEventListener('scroll', () => {
                const distance = mc.scrollHeight - mc.scrollTop - mc.clientHeight;
                mc.dataset.atBottom = distance < 80 ? '1' : '0';
            }, { passive: true });
        }
        return;
    }
    // Weitere Tokens: nur folgen, wenn User effektiv am Bottom klebt
    const distance = mc.scrollHeight - mc.scrollTop - mc.clientHeight;
    if (distance < 80) {
        mc.scrollTop = mc.scrollHeight;
    }
}

function updateSendButtons() {
    const ws = document.getElementById('welcome-send-btn');
    const cs = document.getElementById('chat-send-btn');
    if (ws) ws.disabled = chatState.isStreaming;
    if (cs) cs.disabled = chatState.isStreaming;
}

// ==========================================
// Keyboard
// ==========================================
function handleWelcomeKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendWelcomeMessage();
    }
}
function handleChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ==========================================
// Auto-resize textarea
// ==========================================
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 200) + 'px';
}

// ==========================================
// Nachrichten-Entwürfe pro Chat
//
// Feedback von Michi (#58): Wer zwischen Chats springt, verliert eine bereits
// vorformulierte Nachricht — selectConversation() hat das Eingabefeld bedingungslos
// geleert. Jetzt wird pro Chat ein Entwurf gehalten: beim Verlassen gesichert, beim
// Zurückkehren wiederhergestellt, nach dem Absenden verworfen.
//
// Ablage in localStorage, nicht nur im Speicher: So überlebt ein Entwurf auch einen
// Reload, ein versehentlich geschlossenes Tab oder einen Browser-Absturz.
// ==========================================
const DRAFT_PREFIX = 'thx_chat_draft_';
const DRAFT_MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000;   // 30 Tage
let draftSaveTimer = null;

/** Schlüssel je Chat. Der Welcome-Screen (noch kein Chat) nutzt 'new'. */
function draftKey(convId) {
    return DRAFT_PREFIX + (convId || 'new');
}

/** Entwurf des AKTUELL offenen Chats sichern (leerer Text löscht ihn). */
function saveDraft(convId, text) {
    const id = convId !== undefined ? convId : chatState.activeId;
    const ta = document.getElementById(chatState.activeId ? 'chat-textarea' : 'welcome-textarea');
    const wert = text !== undefined ? text : (ta ? ta.value : '');
    try {
        if (!wert || !wert.trim()) {
            localStorage.removeItem(draftKey(id));
            return;
        }
        localStorage.setItem(draftKey(id), JSON.stringify({ text: wert, ts: Date.now() }));
    } catch (_) { /* localStorage voll oder gesperrt — Entwurf ist dann nur nicht persistent */ }
}

/** Entwurf in das Eingabefeld zurückholen. Gibt true zurück, wenn etwas geladen wurde. */
function restoreDraft(convId, textareaId) {
    const ta = document.getElementById(textareaId || 'chat-textarea');
    if (!ta) return false;
    let entwurf = '';
    try {
        const roh = localStorage.getItem(draftKey(convId));
        if (roh) {
            const d = JSON.parse(roh);
            // Zu alte Entwürfe nicht mehr aufdrängen
            if (d && d.text && (Date.now() - (d.ts || 0)) < DRAFT_MAX_AGE_MS) entwurf = d.text;
            else localStorage.removeItem(draftKey(convId));
        }
    } catch (_) {}
    ta.value = entwurf;
    autoResize(ta);
    return entwurf !== '';
}

function clearDraft(convId) {
    try { localStorage.removeItem(draftKey(convId)); } catch (_) {}
}

/** Beim Tippen verzögert sichern — nicht bei jedem Tastendruck in den Speicher schreiben. */
function scheduleDraftSave() {
    clearTimeout(draftSaveTimer);
    draftSaveTimer = setTimeout(() => saveDraft(), 400);
}

/** Verwaiste Entwürfe aufräumen, damit localStorage nicht zuwächst. */
function pruneDrafts() {
    try {
        const jetzt = Date.now();
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const k = localStorage.key(i);
            if (!k || !k.startsWith(DRAFT_PREFIX)) continue;
            try {
                const d = JSON.parse(localStorage.getItem(k));
                if (!d || !d.text || (jetzt - (d.ts || 0)) > DRAFT_MAX_AGE_MS) localStorage.removeItem(k);
            } catch (_) { localStorage.removeItem(k); }
        }
    } catch (_) {}
}

// Beim Verlassen der Seite den offenen Entwurf noch sichern.
window.addEventListener('beforeunload', () => saveDraft());
document.addEventListener('DOMContentLoaded', pruneDrafts);

// ==========================================
// File Upload
// ==========================================
function triggerUpload() {
    document.getElementById('file-upload-input').click();
}

async function handleFileSelect(e) {
    const fileList = e.target ? e.target.files : e;
    if (!fileList || fileList.length === 0) return;
    // WICHTIG: erst kopieren, DANN input zuruecksetzen — sonst leert value=''
    // die FileList (Live-Referenz) bevor wir die Dateien hochladen.
    const files = Array.from(fileList);
    if (e.target) e.target.value = '';

    await uploadFiles(files);
}

async function uploadFiles(files) {
    if (!files || files.length === 0) return;

    // Conversation sicherstellen
    let endpoint;
    if (chatState.activeId) {
        endpoint = '/chat/conversations/' + chatState.activeId + '/upload';
    } else {
        if (!chatState.currentContext) {
            App.showNotification('Bitte zuerst einen Kontext wählen', 'error');
            updateContextBanner();
            return;
        }
        const ctx = chatState.currentContext;
        const res = await api('POST', '/chat/conversations', {
            title: 'Neuer Chat',
            customer_id: ctx.type === 'customer' ? ctx.customerId : null,
            is_private: ctx.type === 'private' ? 1 : 0,
            projectwide: ctx.type === 'projectwide' ? 1 : 0,
        });
        if (!res.success) {
            App.showNotification(res.message || 'Fehler beim Erstellen', 'error');
            return;
        }
        chatState.activeId = res.data.id;
        chatState.activeConv = { id: res.data.id, title: 'Neuer Chat', messages: [], user_id: <?= $userId ?>, is_owner: true, can_write: true };
        endpoint = '/chat/conversations/' + res.data.id + '/upload';
    }

    const count = files.length;
    App.showNotification(count + ' Datei' + (count > 1 ? 'en werden' : ' wird') + ' verarbeitet...', 'info');

    for (const file of files) {
        try {
            const res = await apiUpload(endpoint, file);
            if (res.success) {
                chatState.pendingAttachments.push(res.data);
                App.showNotification(res.data.filename + ' verarbeitet', 'success');
            } else {
                App.showNotification(file.name + ': ' + (res.message || 'Fehler'), 'error');
            }
        } catch(err) {
            App.showNotification(file.name + ': ' + err.message, 'error');
        }
    }

    // Auf Welcome- oder Chat-Screen rendern (pruefen was sichtbar ist)
    const welcomeVisible = document.getElementById('welcome-screen')?.style.display !== 'none';
    const attachId = welcomeVisible ? 'welcome-attachments' : 'chat-attachments';
    renderAttachments(attachId);
    // Sicherheitshalber auch den anderen Container updaten
    renderAttachments(welcomeVisible ? 'chat-attachments' : 'welcome-attachments');
}

function renderAttachments(containerId) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!chatState.pendingAttachments.length) {
        el.innerHTML = '';
        return;
    }
    el.innerHTML = chatState.pendingAttachments.map((a, i) => {
        let kbHint = '';
        if (a.knowledge_document_id) {
            const label = a.knowledge_status === 'duplicate' ? 'Wissen (existiert)' : 'Im Wissen';
            kbHint = `<a href="/wissen/${a.knowledge_document_id}" target="_blank" title="Im Wissen öffnen" style="color:var(--thoxan-700);text-decoration:none;display:inline-flex;align-items:center;gap:2px;margin-left:4px;font-weight:500;">
                <span class="material-symbols-rounded" style="font-size:13px;">library_books</span>${label}
            </a>`;
        } else if (a.knowledge_status === 'failed') {
            kbHint = `<span title="Datei konnte nicht ins Wissen übernommen werden" style="color:var(--rose-600);margin-left:4px;font-size: var(--d-fs-xs);">!</span>`;
        }
        return `<span class="thx-chip">
            📄 ${esc(a.filename)} (${(a.word_count||0).toLocaleString()} W.)
            ${kbHint}
            <button onclick="removeAttachment(${i},'${containerId}')"><span class="material-symbols-rounded">close</span></button>
        </span>`;
    }).join('');
}

function removeAttachment(idx, containerId) {
    chatState.pendingAttachments.splice(idx, 1);
    renderAttachments(containerId);
}

// ==========================================
// Title Edit
// ==========================================
function startTitleEdit() {
    // Server prueft eigene Berechtigung — wir blockieren nur, wenn gar kein Chat aktiv ist.
    if (!chatState.activeConv) return;
    if (chatState.activeConv.is_owner === false || chatState.activeConv.can_write === false) {
        // Echte Read-Only-Faelle (z.B. fremder geteilter Chat) abfangen
        if (chatState.activeConv.is_owner === false && chatState.activeConv.can_write === false) return;
    }
    const el = document.getElementById('chat-title');
    if (!el) return;
    // Bereits im Edit-Modus? — nicht doppelt initialisieren
    if (el.dataset.editing === '1') return;

    const current = (el.textContent || '').trim();
    el.dataset.editing = '1';
    el.contentEditable = 'true';
    el.spellcheck = false;

    // Cursor + Selektion
    requestAnimationFrame(() => {
        el.focus();
        const range = document.createRange();
        range.selectNodeContents(el);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    });

    let committed = false;
    const commit = async (save) => {
        if (committed) return;
        committed = true;
        el.contentEditable = 'false';
        el.removeAttribute('contenteditable');
        delete el.dataset.editing;
        const newTitle = (el.textContent || '').trim();
        if (!save || !newTitle || newTitle === current) {
            // Verwerfen / unveraendert: Originaltext zurueck
            el.textContent = current || 'Neuer Chat';
            cleanup();
            return;
        }
        try {
            const res = await api('PUT', '/chat/conversations/' + chatState.activeId, { title: newTitle });
            if (res && res.success === false) throw new Error(res.message || 'Fehler');
            chatState.activeConv.title = newTitle;
            el.textContent = newTitle;
            if (typeof App !== 'undefined' && App.showNotification) {
                App.showNotification('Titel gespeichert', 'success');
            }
            loadConversations();
        } catch (e) {
            // Bei Fehler: zurueck auf Original
            el.textContent = current || 'Neuer Chat';
            if (typeof App !== 'undefined' && App.showNotification) {
                App.showNotification('Titel speichern fehlgeschlagen: ' + (e.message || 'Fehler'), 'error');
            }
        }
        cleanup();
    };
    const cleanup = () => {
        el.removeEventListener('keydown', onKey);
        el.removeEventListener('blur', onBlur);
        el.removeEventListener('paste', onPaste);
    };
    const onKey = (e) => {
        if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
        else if (e.key === 'Escape') { e.preventDefault(); commit(false); }
    };
    const onBlur = () => commit(true);
    // Paste: nur Text uebernehmen (kein Rich-Markup)
    const onPaste = (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    };
    el.addEventListener('keydown', onKey);
    el.addEventListener('blur', onBlur);
    el.addEventListener('paste', onPaste);
}

// ==========================================
// Search
// ==========================================
let searchTimeout;
function handleSearch(val) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        chatState.searchQuery = val;
        loadConversations();
    }, 300);
}

// ==========================================
// Copy
// ==========================================
function copyMessage(btn) {
    const msg = btn.closest('.chat-msg');
    const body = msg.querySelector('.chat-msg-body');
    const html = body.innerHTML;
    const text = body.innerText || body.textContent;

    // Rich-Text (HTML) + Plain-Text kopieren
    try {
        const htmlBlob = new Blob([html], { type: 'text/html' });
        const textBlob = new Blob([text], { type: 'text/plain' });
        navigator.clipboard.write([
            new ClipboardItem({ 'text/html': htmlBlob, 'text/plain': textBlob })
        ]).then(() => showCopyFeedback(btn));
    } catch (e) {
        // Fallback fuer aeltere Browser
        navigator.clipboard.writeText(text).then(() => showCopyFeedback(btn));
    }
}

// ==========================================
// Als Wissen speichern — Inline-Dialog im Chat
// ==========================================
let kbSaveTags = [];
let kbSaveMsgId = null;

// ===== Ins Wissen übertragen (gesamter Chat) =====
window.openTransferToKnowledge = function() {
    if (!chatState.activeConv) return;
    const modal = document.getElementById('tk-modal');
    const status = document.getElementById('tk-status');
    const customerSelect = document.getElementById('tk-customer');
    const customerHint = document.getElementById('tk-customer-hint');

    document.getElementById('tk-title').value = '';
    document.getElementById('tk-context').value = '';
    status.innerHTML = '';

    // Vor-Selektion: Conv-Customer wenn vorhanden
    const presetCustId = chatState.activeConv.customer_id;
    if (presetCustId) {
        customerSelect.value = presetCustId;
        const opt = customerSelect.querySelector(`option[value="${presetCustId}"]`);
        if (opt) {
            customerHint.style.display = '';
            customerHint.textContent = '✓ Aus Chat-Kontext übernommen — kannst du anpassen';
        }
    } else {
        customerSelect.value = '';
        customerHint.style.display = 'none';
    }

    modal.classList.add('active');
};

window.closeTransferToKnowledge = function() {
    document.getElementById('tk-modal').classList.remove('active');
};

window.submitTransferToKnowledge = async function() {
    const btn = document.getElementById('tk-submit-btn');
    const status = document.getElementById('tk-status');
    const customerId = parseInt(document.getElementById('tk-customer').value) || 0;
    const title = document.getElementById('tk-title').value.trim();
    const context = document.getElementById('tk-context').value.trim();

    if (!customerId) {
        status.innerHTML = '<span style="color:var(--rose-600);">Bitte einen Kunden wählen.</span>';
        return;
    }
    if (!chatState.activeConv) return;

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>Übertrage…';
    status.innerHTML = '<span style="color:#64748b;">KI verarbeitet den Chat-Verlauf…</span>';

    try {
        const r = await App.post('/knowledge/chat-transfer', {
            conversation_id: chatState.activeId,
            customer_id: customerId,
            title: title,
            context: context,
        });
        if (!r.success) throw new Error(r.message || 'Fehler');
        const d = r.data;
        let label;
        if (d.unchanged) label = ' (unverändert)';
        else if (d.updated) label = ' (aktualisiert)';
        else label = ' (neu angelegt)';
        status.innerHTML = `<span style="color:#047857;">✓ <strong>${esc(d.title || '-')}</strong>${label} — <a href="/wissen/${d.document_id}" target="_blank">öffnen</a></span>`;
        App.showNotification(d.unchanged ? 'Unverändert' : (d.updated ? 'Wissen aktualisiert' : 'Ins Wissen übertragen'), 'success');
        setTimeout(() => closeTransferToKnowledge(), 2000);
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-600);">Fehler: ' + esc(e.message || '') + '</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

function saveAsKnowledge(msgId, btn) {
    // Message-Content aus DOM extrahieren (innerText fuer sauberen Text ohne HTML)
    const msgEl = btn.closest('.chat-msg');
    const body = msgEl.querySelector('.chat-msg-body');
    const text = body.innerText || body.textContent || '';

    kbSaveMsgId = msgId;
    kbSaveTags = [];
    document.getElementById('kb-save-content').value = text;
    document.getElementById('kb-save-phase-1').style.display = 'block';
    document.getElementById('kb-save-phase-2').style.display = 'none';
    document.getElementById('kb-save-modal').style.display = 'flex';
    // Auto-select conversation's customer if any
    const custSel = document.getElementById('kb-save-customer');
    if (custSel) {
        // "Ohne Kunde"-Option entfernen, da Pflicht
        const noneOpt = custSel.querySelector('option[value=""]');
        if (noneOpt) {
            noneOpt.textContent = '— bitte auswählen —';
            noneOpt.disabled = true;
        }
        if (chatState.activeConv && chatState.activeConv.customer_id) {
            custSel.value = chatState.activeConv.customer_id;
        }
        // "Neuen Kunden anlegen…"-Option anhaengen
        if (window.ThxQuickCustomer) ThxQuickCustomer.attachToSelect(custSel);
    }
}
window.saveAsKnowledge = saveAsKnowledge;

window.closeKbSaveModal = function() {
    document.getElementById('kb-save-modal').style.display = 'none';
};

window.kbAnalyze = async function() {
    const content = document.getElementById('kb-save-content').value.trim();
    if (content.length < 20) { App.showNotification('Inhalt zu kurz (min 20 Zeichen)', 'error'); return; }

    // Hard-Check: Kunde Pflicht
    const customerId = document.getElementById('kb-save-customer')?.value || '';
    if (!customerId || customerId === '__new__') {
        App.showNotification('Bitte erst einen Kunden auswählen — oder über das Dropdown einen neuen anlegen.', 'error');
        document.getElementById('kb-save-customer')?.focus();
        return;
    }

    const btn = document.getElementById('kb-save-analyze-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;animation:kb-spin 0.8s linear infinite;">progress_activity</span> Analysiere...';

    try {
        const r = await api('POST', '/knowledge/text', { content, customer_id: customerId });

        if (!r.success) throw new Error(r.message || 'Fehler');

        // Phase 1 → Phase 2
        document.getElementById('kb-save-phase-1').style.display = 'none';
        document.getElementById('kb-save-phase-2').style.display = 'block';

        document.getElementById('kb-save-prepared-id').value = r.data.prepared_id;
        document.getElementById('kb-save-title').value = r.data.suggestion.title;
        document.getElementById('kb-save-description').value = r.data.suggestion.description;
        document.getElementById('kb-save-category').value = r.data.suggestion.category || 'Sonstige';

        // Tags
        kbSaveTags = Array.isArray(r.data.suggestion.tags) ? [...r.data.suggestion.tags] : [];
        renderKbSaveTags();

        // Stats
        const s = r.data.stats || {};
        document.getElementById('kb-save-stats').innerHTML = [
            `<span style="padding:0.3rem 0.6rem;background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border,#e2e8f0);border-radius:6px;font-size: var(--d-fs-sm);">${s.chunks||0} Chunks</span>`,
            `<span style="padding:0.3rem 0.6rem;background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border,#e2e8f0);border-radius:6px;font-size: var(--d-fs-sm);">${s.entities||0} Entities</span>`,
            `<span style="padding:0.3rem 0.6rem;background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border,#e2e8f0);border-radius:6px;font-size: var(--d-fs-sm);">${s.relations||0} Relations</span>`,
        ].join('');

        // Entities
        const ents = r.data.top_entities || [];
        document.getElementById('kb-save-entities').innerHTML = ents.map(e =>
            `<span style="padding:0.2rem 0.5rem;background:#d6e7f5;color:var(--thoxan-700);border-radius:4px;font-size: var(--d-fs-xs);">${esc(e.name)}<span style="opacity:0.6;font-size: var(--d-fs-xs);margin-left:3px;">(${e.type})</span></span>`
        ).join('');

    } catch (e) {
        App.showNotification(e.message || 'Analyse fehlgeschlagen', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">auto_awesome</span> Analysieren';
};

function renderKbSaveTags() {
    const list = document.getElementById('kb-save-tags-list');
    list.innerHTML = kbSaveTags.map((t, i) =>
        `<span style="padding:0.15rem 0.5rem;background:var(--color-bg-secondary,#f8fafc);border-radius:4px;font-size: var(--d-fs-xs);color:var(--color-text-secondary,#64748b);">
            #${esc(t)} <button onclick="kbRemoveTag(${i})" style="background:none;border:none;cursor:pointer;color:var(--color-text-tertiary,#94a3b8);font-size: var(--d-fs-sm);line-height:1;margin-left:3px;">&times;</button>
        </span>`
    ).join('') + '<input type="text" placeholder="Tag + Enter" onkeydown="kbHandleTagKey(event)" style="flex:1;min-width:80px;border:none;outline:none;padding:0.25rem;background:transparent;font-size: var(--d-fs-sm);">';
}

window.kbHandleTagKey = function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const v = e.target.value.trim().replace(/,$/, '').toLowerCase();
        if (v && !kbSaveTags.includes(v)) {
            kbSaveTags.push(v);
            renderKbSaveTags();
            // Focus neues Input
            const newInput = document.getElementById('kb-save-tags-list').querySelector('input');
            if (newInput) newInput.focus();
        } else {
            e.target.value = '';
        }
    } else if (e.key === 'Backspace' && e.target.value === '' && kbSaveTags.length > 0) {
        kbSaveTags.pop();
        renderKbSaveTags();
        const newInput = document.getElementById('kb-save-tags-list').querySelector('input');
        if (newInput) newInput.focus();
    }
};

window.kbRemoveTag = function(i) { kbSaveTags.splice(i, 1); renderKbSaveTags(); };

window.kbCommit = async function() {
    const btn = document.getElementById('kb-save-commit-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;animation:kb-spin 0.8s linear infinite;">progress_activity</span> Speichere...';

    try {
        const payload = {
            prepared_id: document.getElementById('kb-save-prepared-id').value,
            title: document.getElementById('kb-save-title').value.trim(),
            description: document.getElementById('kb-save-description').value.trim(),
            customer_id: document.getElementById('kb-save-customer').value,
            category: document.getElementById('kb-save-category').value,
            tags: kbSaveTags,
        };
        const r = await api('POST', '/knowledge/commit', payload);
        if (r.success) {
            closeKbSaveModal();
            App.showNotification('Als Wissen gespeichert', 'success');
            // Optional: Link zum Eintrag zeigen
            setTimeout(() => {
                App.showNotification('Oeffnen: /wissen/' + r.data.document_id, 'info');
            }, 2000);
        } else {
            App.showNotification(r.message || 'Fehler', 'error');
        }
    } catch (e) {
        App.showNotification('Fehler: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">save</span> Speichern';
};

// Click outside modal to close
document.getElementById('kb-save-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeKbSaveModal();
});

// Kleine CSS-Animation falls nicht schon da
(function() {
    if (!document.querySelector('style[data-kb-spin]')) {
        const s = document.createElement('style');
        s.setAttribute('data-kb-spin', '1');
        s.textContent = '@keyframes kb-spin { to { transform: rotate(360deg); } }';
        document.head.appendChild(s);
    }
})();

async function downloadMessageAsDocx(msgId, btn) {
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-rounded">progress_activity</span>';
    btn.disabled = true;

    try {
        const resp = await fetch('/api/v1/chat/export-docx', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ message_id: msgId })
        });
        if (!resp.ok) throw new Error('Export fehlgeschlagen');
        const blob = await resp.blob();
        const title = (chatState.activeConv?.title || 'chat').toLowerCase().replace(/[^a-z0-9]+/g, '-').substring(0, 40);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = title + '-' + new Date().toISOString().slice(0, 10) + '.docx';
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }

    btn.innerHTML = origHtml;
    btn.disabled = false;
}

window.downloadMessageAsDocx = downloadMessageAsDocx;

function showCopyFeedback(btn) {
    btn.innerHTML = '<span class="material-symbols-rounded">check</span>';
    btn.title = 'Kopiert';
    setTimeout(() => {
        btn.innerHTML = '<span class="material-symbols-rounded">content_copy</span>';
        btn.title = 'Kopieren';
    }, 2000);
}

function copyCodeBlock(btn) {
    const pre = btn.closest('pre');
    const code = pre.querySelector('code');
    navigator.clipboard.writeText(code.textContent).then(() => {
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px">check</span> Kopiert';
        setTimeout(() => {
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px">content_copy</span> Kopieren';
        }, 2000);
    });
}

// ==========================================
// Projects
// ==========================================
function openProjectModal(editId = null, defaultParentId = null) {
    chatState.editProjectId = editId;
    const modal = document.getElementById('project-modal');
    const titleEl = document.getElementById('project-modal-title');
    const nameInput = document.getElementById('project-name-input');
    const parentSelect = document.getElementById('project-parent-select');

    // Populate parent select
    const tree = buildFolderTree(chatState.projects);
    let opts = '<option value="">-- Kein (Root-Ebene) --</option>';
    const renderOpts = (nodes, depth) => {
        nodes.forEach(n => {
            if (editId && n.id == editId) return; // Cannot be own parent
            const prefix = '\u00A0\u00A0'.repeat(depth);
            opts += `<option value="${n.id}">${prefix}${esc(n.name)}</option>`;
            renderOpts(n.children, depth + 1);
        });
    };
    renderOpts(tree, 0);
    parentSelect.innerHTML = opts;

    if (editId) {
        const p = chatState.projects.find(pr => pr.id == editId);
        titleEl.textContent = 'Ordner bearbeiten';
        nameInput.value = p ? p.name : '';
        parentSelect.value = p && p.parent_id ? p.parent_id : '';
        selectProjectColor(p ? p.color : null);
    } else {
        titleEl.textContent = 'Neuer Ordner';
        nameInput.value = '';
        parentSelect.value = defaultParentId || '';
        selectProjectColor(null);
    }

    modal.classList.add('active');
    nameInput.focus();
}

function closeProjectModal() {
    document.getElementById('project-modal').classList.remove('active');
}

function selectProjectColor(color) {
    chatState.editProjectColor = color;
    document.querySelectorAll('.project-color-btn').forEach(btn => {
        btn.style.borderColor = (btn.dataset.color === (color || '')) ? 'var(--color-gray-700)' : 'transparent';
        if (!btn.dataset.color) btn.style.borderColor = !color ? 'var(--color-gray-700)' : 'var(--color-gray-300)';
    });
}

async function saveProject() {
    const name = document.getElementById('project-name-input').value.trim();
    if (!name) {
        App.showNotification('Name erforderlich', 'error');
        return;
    }
    const parentVal = document.getElementById('project-parent-select').value;
    const data = { name, color: chatState.editProjectColor, parent_id: parentVal ? parseInt(parentVal) : null };

    if (chatState.editProjectId) {
        await api('PUT', '/chat/projects/' + chatState.editProjectId, data);
    } else {
        await api('POST', '/chat/projects', data);
    }
    closeProjectModal();
    loadProjects();
    loadConversations();
}

async function deleteProject(id) {
    if (!confirm('Ordner loeschen? Unterordner werden ebenfalls geloescht. Chats bleiben erhalten.')) return;
    document.getElementById('context-menu').classList.remove('active');
    await api('DELETE', '/chat/projects/' + id);
    if (chatState.currentFolderId == id) {
        chatState.currentFolderId = null;
    }
    loadProjects();
    loadConversations();
}

// ==========================================
// Context Menus
// ==========================================
function showConvContextMenu(e, convId) {
    e.preventDefault();
    e.stopPropagation();
    const conv = chatState.conversations.find(c => c.id === convId);
    if (!conv) return;

    const isOwner = conv.is_owner == 1 || conv.is_owner === true;
    const cm = document.getElementById('context-menu');

    const pinnedIds = JSON.parse(localStorage.getItem('chat_pinned_convs') || '[]');
    const isPinned = pinnedIds.includes(convId);

    let items = '';
    items += `<button class="thx-contextmenu-item" onclick="togglePinConversation(${convId})"><span class="material-symbols-rounded">${isPinned ? 'push_pin' : 'push_pin'}</span>${isPinned ? 'Lösen' : 'Anheften'}</button>`;
    items += `<button class="thx-contextmenu-item" onclick="renameConv(${convId})"><span class="material-symbols-rounded">edit</span>Umbenennen</button>`;
    items += `<button class="thx-contextmenu-item" onclick="sbReassignChat(${convId})"><span class="material-symbols-rounded">business</span>Kontext / Kunde ändern</button>`;
    // Ordner-Zuweisung (nur wenn Kontext aktiv und Ordner vorhanden)
    if (chatState.currentContext && chatState.contextFolders && chatState.contextFolders.length > 0) {
        items += `<div class="thx-contextmenu-divider"></div>`;
        items += `<div style="padding:6px 12px 2px;font-size: var(--d-fs-xs);text-transform:uppercase;color:#94a3b8;letter-spacing:0.5px;font-weight:700;">In Ordner</div>`;
        if (conv.project_id) {
            items += `<button class="thx-contextmenu-item" onclick="assignChatToFolder(${convId}, null)"><span class="material-symbols-rounded">folder_off</span>Aus Ordner entfernen</button>`;
        }
        chatState.contextFolders.forEach(f => {
            if (conv.project_id == f.id) return;
            items += `<button class="thx-contextmenu-item" onclick="assignChatToFolder(${convId}, ${f.id})"><span class="material-symbols-rounded">folder</span>${esc(f.name)}</button>`;
        });
    }
    if (isOwner) {
        items += `<button class="thx-contextmenu-item" onclick="openShareDialogFor('conversation',${convId})"><span class="material-symbols-rounded">share</span>Teilen</button>`;
    }
    items += '<div class="thx-contextmenu-divider"></div>';
    if (isOwner || chatState.isAdmin) {
        items += `<button class="thx-contextmenu-item is-danger" onclick="deleteConv(${convId})"><span class="material-symbols-rounded">delete</span>In Papierkorb</button>`;
    }

    cm.innerHTML = items;
    cm.classList.add('active');
    positionContextMenu(cm, e.clientX, e.clientY);
}

// Hilfsfunktion: klemmt das Context-Menu an den Viewport, so dass es nie abgeschnitten wird
function positionContextMenu(cm, anchorX, anchorY) {
    cm.style.left = '0px';
    cm.style.top  = '0px';
    const rect = cm.getBoundingClientRect();
    const margin = 6;
    let left = anchorX, top = anchorY;
    if (left + rect.width  > window.innerWidth  - margin) left = window.innerWidth  - rect.width  - margin;
    if (top  + rect.height > window.innerHeight - margin) top  = Math.max(margin, anchorY - rect.height);
    if (top  < margin) top  = margin;
    if (left < margin) left = margin;
    cm.style.left = left + 'px';
    cm.style.top  = top  + 'px';
}

function showProjectMenu(e, projId) {
    e.preventDefault();
    e.stopPropagation();
    const p = chatState.projects.find(pr => pr.id === projId);
    if (!p) return;

    const cm = document.getElementById('context-menu');
    let items = '';
    items += `<button class="thx-contextmenu-item" onclick="openProjectModal(null,${projId})"><span class="material-symbols-rounded">create_new_folder</span>Neuer Unterordner</button>`;
    items += `<button class="thx-contextmenu-item" onclick="openProjectModal(${projId})"><span class="material-symbols-rounded">edit</span>Bearbeiten</button>`;
    items += `<button class="thx-contextmenu-item" onclick="openMoveFolderModal(${projId})"><span class="material-symbols-rounded">drive_file_move</span>Verschieben</button>`;
    items += `<button class="thx-contextmenu-item" onclick="openShareDialogFor('project',${projId})"><span class="material-symbols-rounded">share</span>Teilen</button>`;
    items += '<div class="thx-contextmenu-divider"></div>';
    items += `<button class="thx-contextmenu-item is-danger" onclick="deleteProject(${projId})"><span class="material-symbols-rounded">delete</span>Loeschen</button>`;

    cm.innerHTML = items;
    cm.classList.add('active');
    positionContextMenu(cm, e.clientX, e.clientY);
}

function showConvMenu(e) {
    if (!chatState.activeConv) return;
    const c = chatState.activeConv;
    const cm = document.getElementById('context-menu');

    // Titel-Umbenennen ist Inline auf dem Titel selbst (Klick auf den H1) — daher nicht
    // hier nochmal. Sharen / Loeschen bleiben.
    let items = '';
    items += `<button class="thx-contextmenu-item" onclick="openShareDialog('conversation')"><span class="material-symbols-rounded">share</span>Teilen</button>`;
    if (c.is_owner) {
        items += '<div class="thx-contextmenu-divider"></div>';
        items += `<button class="thx-contextmenu-item is-danger" onclick="deleteConv(${c.id})"><span class="material-symbols-rounded">delete</span>Löschen</button>`;
    }

    cm.innerHTML = items;
    cm.classList.add('active');
    const rect = e.target.closest('button').getBoundingClientRect();
    positionContextMenu(cm, rect.right - 180, rect.bottom);
}

// ==========================================
// Conversation CRUD
// ==========================================
async function renameConv(id) {
    document.getElementById('context-menu').classList.remove('active');
    const conv = chatState.conversations.find(c => c.id === id);
    const newTitle = prompt('Neuer Titel:', conv ? conv.title : '');
    if (!newTitle || !newTitle.trim()) return;
    await api('PUT', '/chat/conversations/' + id, { title: newTitle.trim() });
    if (chatState.activeId === id && chatState.activeConv) {
        chatState.activeConv.title = newTitle.trim();
        renderChatHeader();
    }
    loadConversations();
}

async function deleteConv(id) {
    document.getElementById('context-menu').classList.remove('active');
    if (!confirm('Chat in den Papierkorb verschieben?\n\nDer Chat fliegt sofort aus der Wissensbasis raus und bleibt 1 Jahr lang wiederherstellbar.')) return;
    try {
        await api('DELETE', '/chat/conversations/' + id);
        if (chatState.activeId === id) newChat();
        loadConversations();
        App.showNotification('In Papierkorb verschoben', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
}

// ==========================================
// Move to Project
// ==========================================
function buildFolderTree(projects) {
    const map = {};
    const roots = [];
    projects.forEach(p => { map[p.id] = { ...p, children: [] }; });
    projects.forEach(p => {
        if (p.parent_id && map[p.parent_id]) {
            map[p.parent_id].children.push(map[p.id]);
        } else {
            roots.push(map[p.id]);
        }
    });
    const sortFn = (nodes) => {
        nodes.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de'));
        nodes.forEach(n => sortFn(n.children));
    };
    sortFn(roots);
    return roots;
}

let moveConvId = null;
let moveFolderId = null;

function renderMoveTree(folders, depth = 0, excludeId = null) {
    let html = '';
    folders.forEach(f => {
        if (excludeId && f.id == excludeId) return;
        const indent = depth * 20;
        const iconColor = f.color ? `color:${f.color}` : 'color:var(--color-gray-400)';
        html += `<div class="chat-conv-item" onclick="${moveFolderId ? `moveFolderToParent(${f.id})` : `moveToProject(${f.id})`}" style="cursor:pointer;margin-bottom:2px;padding-left:${indent}px;padding:4px 8px 4px ${8 + indent}px;border-radius:var(--radius-sm)" onmouseover="this.style.background='rgba(0,122,255,0.08)'" onmouseout="this.style.background=''">
            <span class="material-symbols-rounded" style="font-size:16px;${iconColor}">folder</span>
            <span>${esc(f.name)}</span>
        </div>`;
        html += renderMoveTree(f.children, depth + 1, excludeId);
    });
    return html;
}

function openMoveModal(convId) {
    document.getElementById('context-menu').classList.remove('active');
    moveConvId = convId;
    moveFolderId = null;
    document.getElementById('move-modal-title').textContent = 'In Ordner verschieben';

    const tree = buildFolderTree(chatState.projects);
    let html = `<div class="chat-conv-item" onclick="moveToProject(null)" style="cursor:pointer;margin-bottom:2px;padding:4px 8px;border-radius:var(--radius-sm)" onmouseover="this.style.background='rgba(0,122,255,0.08)'" onmouseout="this.style.background=''">
        <span class="material-symbols-rounded" style="font-size:16px;color:var(--color-gray-400)">folder_off</span>
        <span>Kein Ordner</span>
    </div>`;
    html += renderMoveTree(tree);
    document.getElementById('move-project-list').innerHTML = html;
    document.getElementById('move-modal').classList.add('active');
}

function openMoveFolderModal(folderId) {
    document.getElementById('context-menu').classList.remove('active');
    moveFolderId = folderId;
    moveConvId = null;
    document.getElementById('move-modal-title').textContent = 'Ordner verschieben';

    const tree = buildFolderTree(chatState.projects);
    let html = `<div class="chat-conv-item" onclick="moveFolderToParent(null)" style="cursor:pointer;margin-bottom:2px;padding:4px 8px;border-radius:var(--radius-sm)" onmouseover="this.style.background='rgba(0,122,255,0.08)'" onmouseout="this.style.background=''">
        <span class="material-symbols-rounded" style="font-size:16px;color:var(--color-gray-400)">folder_off</span>
        <span>Root-Ebene</span>
    </div>`;
    html += renderMoveTree(tree, 0, folderId);
    document.getElementById('move-project-list').innerHTML = html;
    document.getElementById('move-modal').classList.add('active');
}

async function moveFolderToParent(parentId) {
    if (!moveFolderId) return;
    try {
        await api('PUT', '/chat/projects/' + moveFolderId, { parent_id: parentId });
    } catch (err) {
        App.showNotification(err.message || 'Fehler beim Verschieben', 'error');
        return;
    }
    closeMoveModal();
    loadProjects();
    loadConversations();
}

function closeMoveModal() {
    document.getElementById('move-modal').classList.remove('active');
}

async function moveToProject(projectId) {
    if (!moveConvId) return;
    await api('PUT', '/chat/conversations/' + moveConvId, { project_id: projectId });
    closeMoveModal();
    if (chatState.activeId === moveConvId && chatState.activeConv) {
        chatState.activeConv.project_id = projectId;
    }
    loadProjects();
    loadConversations();
}

// ==========================================
// Drag & Drop: Chat/Ordner → Ordner
// ==========================================
let dragConvId = null;
let dragFolderId = null;

function onConvDragStart(e, convId) {
    dragConvId = convId;
    dragFolderId = null;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', 'conv:' + convId);
    e.target.classList.add('dragging');
}

function onConvDragEnd(e) {
    e.target.classList.remove('dragging');
    document.querySelectorAll('.explorer-folder-row.drag-over').forEach(el => el.classList.remove('drag-over'));
    document.querySelectorAll('.finder-root-drop-zone.drag-over').forEach(el => el.classList.remove('drag-over'));
    dragConvId = null;
}

function onFolderDragStart(e, folderId) {
    e.stopPropagation();
    dragFolderId = folderId;
    dragConvId = null;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', 'folder:' + folderId);
    e.currentTarget.classList.add('dragging');
}

function onFolderDragEnd(e) {
    e.currentTarget.classList.remove('dragging');
    document.querySelectorAll('.explorer-folder-row.drag-over').forEach(el => el.classList.remove('drag-over'));
    document.querySelectorAll('.finder-root-drop-zone.drag-over').forEach(el => el.classList.remove('drag-over'));
    dragFolderId = null;
}

function onProjectDragOver(e) {
    if (!dragConvId && !dragFolderId) return;
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-over');
}

function onProjectDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

async function onProjectDrop(e, projectId) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');

    if (dragConvId) {
        await api('PUT', '/chat/conversations/' + dragConvId, { project_id: projectId });
        if (chatState.activeId === dragConvId && chatState.activeConv) {
            chatState.activeConv.project_id = projectId;
        }
        dragConvId = null;
    } else if (dragFolderId) {
        if (dragFolderId === projectId) return;
        // Client-side cycle check
        let check = projectId;
        while (check) {
            if (check == dragFolderId) {
                App.showNotification('Zirkulaere Referenz nicht moeglich', 'error');
                dragFolderId = null;
                return;
            }
            const p = chatState.projects.find(pr => pr.id == check);
            check = p && p.parent_id ? p.parent_id : null;
        }
        try {
            await api('PUT', '/chat/projects/' + dragFolderId, { parent_id: projectId });
        } catch (err) {
            App.showNotification(err.message || 'Fehler beim Verschieben', 'error');
        }
        dragFolderId = null;
    }
    loadProjects();
    loadConversations();
}

function onRootDropZoneDragOver(e) {
    if (!dragConvId && !dragFolderId) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-over');
}

function onRootDropZoneDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

async function onRootDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');

    if (dragConvId) {
        await api('PUT', '/chat/conversations/' + dragConvId, { project_id: null });
        if (chatState.activeId === dragConvId && chatState.activeConv) {
            chatState.activeConv.project_id = null;
        }
        dragConvId = null;
    } else if (dragFolderId) {
        try {
            await api('PUT', '/chat/projects/' + dragFolderId, { parent_id: null });
        } catch (err) {
            App.showNotification(err.message || 'Fehler', 'error');
        }
        dragFolderId = null;
    }
    loadProjects();
    loadConversations();
}

// ==========================================
// System Prompt
// ==========================================
function openSysPromptModal() {
    document.getElementById('context-menu').classList.remove('active');
    if (!chatState.activeConv) return;
    document.getElementById('sysprompt-textarea').value = chatState.activeConv.system_prompt || '';
    document.getElementById('sysprompt-modal').classList.add('active');
}

function closeSysPromptModal() {
    document.getElementById('sysprompt-modal').classList.remove('active');
}

async function saveSysPrompt() {
    const prompt = document.getElementById('sysprompt-textarea').value;
    await api('PUT', '/chat/conversations/' + chatState.activeId, { system_prompt: prompt });
    chatState.activeConv.system_prompt = prompt;
    closeSysPromptModal();
    App.showNotification('System-Prompt gespeichert', 'success');
}

// ==========================================
// Customer Select
// ==========================================
function showCustomerSelect() {
    document.getElementById('context-menu').classList.remove('active');
    if (!chatState.activeConv || customers.length === 0) return;

    let options = customers.map(c => `${c.name} (${c.id})`);
    const choice = prompt('Kunde zuordnen (ID eingeben, leer fuer keinen):\n\n' + options.join('\n'));

    if (choice === null) return;
    const customerId = choice.trim() === '' ? null : parseInt(choice.trim());

    if (customerId && !customers.find(c => c.id == customerId)) {
        App.showNotification('Ungueltige Kunden-ID', 'error');
        return;
    }

    api('PUT', '/chat/conversations/' + chatState.activeId, { customer_id: customerId }).then(() => {
        chatState.activeConv.customer_id = customerId;
        renderChatHeader();
        App.showNotification('Kunde zugeordnet', 'success');
    });
}

// ==========================================
// Share Dialog
// ==========================================
function openShareDialog(type) {
    if (!chatState.activeId) return;
    openShareDialogFor(type, chatState.activeId);
}

async function openShareDialogFor(type, id) {
    document.getElementById('context-menu').classList.remove('active');
    chatState.shareContext = { type, id };

    const title = type === 'project' ? 'Projekt teilen' : 'Schreibrechte';
    document.getElementById('share-modal-title').textContent = title;
    const lbl = document.getElementById('share-list-label');
    if (lbl) lbl.textContent = type === 'project' ? 'Geteilte Personen:' : 'Wer darf schreiben:';
    document.getElementById('share-search-input').value = '';
    document.getElementById('share-results').innerHTML = '';
    document.getElementById('share-results').classList.remove('has-results');

    // Load current shares
    const endpoint = type === 'project'
        ? '/chat/projects/' + id + '/share'
        : '/chat/conversations/' + id + '/share';

    const res = await api('GET', endpoint);
    renderShareList(res.success ? res.data : [], type);

    // Offene Zugriffsanfragen laden (nur Chat)
    const reqWrap = document.getElementById('share-requests-wrap');
    if (type === 'conversation') {
        const rr = await api('GET', '/chat/conversations/' + id + '/access-requests');
        renderAccessRequests(rr.success ? rr.data : [], id);
    } else if (reqWrap) {
        reqWrap.style.display = 'none';
    }

    document.getElementById('share-modal').classList.add('active');
}

function renderAccessRequests(reqs, convId) {
    const wrap = document.getElementById('share-requests-wrap');
    const list = document.getElementById('share-requests');
    if (!wrap || !list) return;
    if (!reqs || !reqs.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    list.innerHTML = reqs.map(r => `<div class="chat-share-item">
        <div class="chat-share-item-info">
            <div class="chat-share-item-name">${esc(r.requester_name)}</div>
            <div class="chat-share-item-email">${esc(r.requester_email || '')}${r.message ? ' · „' + esc(r.message) + '"' : ''}</div>
        </div>
        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="resolveAccessRequest(${convId}, ${r.id}, 'approve')" title="Schreibzugriff freigeben">Freigeben</button>
        <button class="chat-share-remove" onclick="resolveAccessRequest(${convId}, ${r.id}, 'deny')" title="Ablehnen"><span class="material-symbols-rounded">close</span></button>
    </div>`).join('');
}

async function resolveAccessRequest(convId, rid, action) {
    const res = await api('POST', '/chat/conversations/' + convId + '/access-requests/' + rid, { action });
    if (!res.success) { App.showNotification(res.message || 'Fehler', 'error'); return; }
    App.showNotification(action === 'approve' ? 'Freigegeben' : 'Abgelehnt', 'success');
    // Dialog-Inhalte neu laden (Anfragen + Freigaben)
    openShareDialogFor('conversation', convId);
    // Header-Badge aktualisieren, falls dieser Chat offen ist
    if (chatState.activeConv && chatState.activeConv.id == convId && typeof chatState.activeConv.pending_access_requests === 'number') {
        chatState.activeConv.pending_access_requests = Math.max(0, chatState.activeConv.pending_access_requests - 1);
        renderChatHeader();
    }
}

function closeShareModal() {
    document.getElementById('share-modal').classList.remove('active');
    chatState.shareContext = null;
}

function renderShareList(data, type) {
    const el = document.getElementById('share-list');
    const searchInput = document.getElementById('share-search-input');

    // ----- Projekt: altes Format (Array, read/write) — unveraendert -----
    if (Array.isArray(data)) {
        if (searchInput) searchInput.style.display = '';
        if (!data.length) {
            el.innerHTML = '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-400);padding:8px">Noch mit niemandem geteilt</div>';
            return;
        }
        el.innerHTML = data.map(s => `<div class="chat-share-item">
            <div class="chat-share-item-info">
                <div class="chat-share-item-name">${esc(s.user_name)}</div>
                <div class="chat-share-item-email">${esc(s.user_email)}</div>
            </div>
            <select onchange="updateSharePermission(${s.shared_with}, this.value)">
                <option value="read" ${s.permission === 'read' ? 'selected' : ''}>Lesen</option>
                <option value="write" ${s.permission === 'write' ? 'selected' : ''}>Schreiben</option>
            </select>
            <button class="chat-share-remove" onclick="removeShare(${s.shared_with})"><span class="material-symbols-rounded">close</span></button>
        </div>`).join('');
        return;
    }

    // ----- Chat: neues Format (Objekt) — Schreibrechte -----
    const shares = data.shares || [];
    const owner = data.owner || {};
    const canManage = !!data.can_manage;
    const writeOpen = !!data.write_open;
    chatState.shareCanManage = canManage;

    // Suchfeld (Person als Schreiber hinzufuegen) nur fuer Verwalter
    if (searchInput) searchInput.style.display = canManage ? '' : 'none';

    const rmBtn = (uid) => canManage
        ? `<button class="chat-share-remove" onclick="removeShare(${uid})" title="Schreibzugriff entziehen"><span class="material-symbols-rounded">close</span></button>`
        : '';

    // Owner-Zeile (immer, nicht entfernbar)
    let html = `<div class="chat-share-item">
        <div class="chat-share-item-info">
            <div class="chat-share-item-name">${esc(owner.name || 'Ersteller')}</div>
            <div class="chat-share-item-email">Ersteller · darf immer schreiben</div>
        </div>
        <span class="chat-share-badge-write">Owner</span>
    </div>`;

    if (writeOpen) {
        html += `<div class="chat-share-item" style="opacity:.85;">
            <div class="chat-share-item-info">
                <div class="chat-share-item-name">Alle mit Kundenzugriff</div>
                <div class="chat-share-item-email">„Für alle freigegeben" ist aktiv — jeder, für den dieser Kunde freigeschaltet ist, darf schreiben.</div>
            </div>
            <span class="chat-share-badge-write">Schreiben</span>
        </div>`;
    }

    // Einzelne Schreibberechtigte
    html += shares.map(s => `<div class="chat-share-item">
        <div class="chat-share-item-info">
            <div class="chat-share-item-name">${esc(s.user_name)}</div>
            <div class="chat-share-item-email">${esc(s.user_email || '')}</div>
        </div>
        <span class="chat-share-badge-write">Schreiben</span>
        ${rmBtn(s.shared_with)}
    </div>`).join('');

    if (!writeOpen && shares.length === 0) {
        html += '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-400);padding:8px">Außer dem Ersteller darf niemand schreiben.</div>';
    }

    // „Alle dürfen schreiben"-Schalter — nur für Verwalter (Owner/Admin)
    if (canManage) {
        html += `<label class="chat-share-openall ${writeOpen ? 'is-on' : ''}">
            <input type="checkbox" ${writeOpen ? 'checked' : ''} onchange="toggleWriteOpen(this.checked)">
            <span class="material-symbols-rounded">groups</span>
            <span>Diesen Chat für alle freigeben, die ihn sehen dürfen (Kundenzugriff)</span>
        </label>`;
    }

    el.innerHTML = html;
}

let shareSearchTimeout;
function debounceSearchUsers(val) {
    clearTimeout(shareSearchTimeout);
    shareSearchTimeout = setTimeout(() => searchUsers(val), 300);
}

async function searchUsers(query) {
    const resEl = document.getElementById('share-results');
    if (query.length < 2) {
        resEl.innerHTML = '';
        resEl.classList.remove('has-results');
        return;
    }
    const res = await api('GET', '/chat/users?search=' + encodeURIComponent(query));
    if (!res.success || !res.data.length) {
        resEl.innerHTML = '';
        resEl.classList.remove('has-results');
        return;
    }
    resEl.innerHTML = res.data.map(u => `<div class="chat-share-result-item" onclick="addShare(${u.id}, '${esc(u.name)}', '${esc(u.email)}')">
        <div><span class="chat-share-result-name">${esc(u.name)}</span><br><span class="chat-share-result-email">${esc(u.email)}</span></div>
    </div>`).join('');
    resEl.classList.add('has-results');
}

async function addShare(userId, name, email) {
    if (!chatState.shareContext) return;
    const { type, id } = chatState.shareContext;
    const endpoint = type === 'project'
        ? '/chat/projects/' + id + '/share'
        : '/chat/conversations/' + id + '/share';

    // Freigabe = Schreibzugriff (Lesen kann jeder, der den Chat sieht)
    await api('POST', endpoint, { user_id: userId, permission: 'write' });

    // Clear search
    document.getElementById('share-search-input').value = '';
    document.getElementById('share-results').innerHTML = '';
    document.getElementById('share-results').classList.remove('has-results');

    // Reload shares
    const res = await api('GET', endpoint);
    renderShareList(res.success ? res.data : [], type);
    if (chatState.activeConv && chatState.activeConv.id == id) { try { await refreshActiveConvWrite(); } catch(_){} }

    App.showNotification('Schreibzugriff erteilt', 'success');
}

async function toggleWriteOpen(on) {
    const ctx = chatState.shareContext;
    if (!ctx || ctx.type !== 'conversation') return;
    const res = await api('POST', '/chat/conversations/' + ctx.id + '/share', { write_open: on ? 1 : 0 });
    if (!res.success) { App.showNotification(res.message || 'Fehler', 'error'); openShareDialogFor('conversation', ctx.id); return; }
    App.showNotification(on ? 'Für alle freigegeben' : 'Freigabe für alle aufgehoben', 'success');
    openShareDialogFor('conversation', ctx.id);
    if (chatState.activeConv && chatState.activeConv.id == ctx.id) { try { await refreshActiveConvWrite(); } catch(_){} }
}

// Aktiven Chat neu laden, damit can_write/Banner/Eingabe sofort stimmen
async function refreshActiveConvWrite() {
    const cid = chatState.activeConv && chatState.activeConv.id;
    if (!cid) return;
    const r = await api('GET', '/chat/conversations/' + cid);
    if (r.success) {
        chatState.activeConv.can_write = r.data.can_write;
        chatState.activeConv.write_open = r.data.write_open;
        applyChatWriteState();
        renderChatHeader();
    }
}

async function updateSharePermission(userId, permission) {
    if (!chatState.shareContext) return;
    const { type, id } = chatState.shareContext;
    const endpoint = type === 'project'
        ? '/chat/projects/' + id + '/share'
        : '/chat/conversations/' + id + '/share';
    await api('POST', endpoint, { user_id: userId, permission });
}

async function removeShare(userId) {
    if (!chatState.shareContext) return;
    const { type, id } = chatState.shareContext;
    const endpoint = type === 'project'
        ? '/chat/projects/' + id + '/share/' + userId
        : '/chat/conversations/' + id + '/share/' + userId;
    await api('DELETE', endpoint);

    // Reload
    const getEndpoint = type === 'project'
        ? '/chat/projects/' + id + '/share'
        : '/chat/conversations/' + id + '/share';
    const res = await api('GET', getEndpoint);
    renderShareList(res.success ? res.data : [], type);
    if (chatState.activeConv && chatState.activeConv.id == id) { try { await refreshActiveConvWrite(); } catch(_){} }

    App.showNotification('Schreibzugriff entzogen', 'success');
}

// ==========================================
// Artifact Panel
// ==========================================
function syncArtifactCheckbox() {
    const w = document.getElementById('welcome-use-artifacts');
    const c = document.getElementById('chat-use-artifacts');
    if (w) w.checked = chatState.useArtifacts;
    if (c) c.checked = chatState.useArtifacts;
}

function syncWebSearchCheckbox() {
    const w = document.getElementById('welcome-use-web-search');
    const c = document.getElementById('chat-use-web-search');
    if (w) w.checked = chatState.useWebSearch;
    if (c) c.checked = chatState.useWebSearch;
}

function syncKnowledgeCheckbox() {
    const w = document.getElementById('welcome-use-knowledge');
    const c = document.getElementById('chat-use-knowledge');
    if (w) w.checked = chatState.useKnowledge;
    if (c) c.checked = chatState.useKnowledge;
}

function syncRulesCheckbox() {
    const w = document.getElementById('welcome-use-rules');
    const c = document.getElementById('chat-use-rules');
    if (w) w.checked = chatState.useRules;
    if (c) c.checked = chatState.useRules;
}

function syncDualModelB(value) {
    chatState.dualModelB = value;
    ['welcome-dual-model-select', 'chat-dual-model-select'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value !== value) el.value = value;
    });
    saveSettingsToLocalStorage();
}

function toggleSidebar() {
    const sidebar = document.getElementById('chat-sidebar');
    sidebar.classList.toggle('collapsed');
    const collapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('chat_sidebar_collapsed', collapsed ? '1' : '0');
}

function restoreSidebarState() {
    const collapsed = localStorage.getItem('chat_sidebar_collapsed') === '1';
    if (collapsed) {
        const sidebar = document.getElementById('chat-sidebar');
        if (sidebar) sidebar.classList.add('collapsed');
    }
}

function saveSettingsToLocalStorage() {
    const model = document.getElementById('chat-model-select')?.value
        || document.getElementById('welcome-model-select')?.value
        || chatState.selectedModel;
    localStorage.setItem('chat_last_settings', JSON.stringify({
        model: model,
        useArtifacts: chatState.useArtifacts,
        useWebSearch: chatState.useWebSearch,
        useKnowledge: chatState.useKnowledge,
        useRules: chatState.useRules,
        dualMode: chatState.dualMode,
        dualModelB: chatState.dualModelB,
    }));
}

function restoreSettingsFromLocalStorage() {
    try {
        const saved = JSON.parse(localStorage.getItem('chat_last_settings'));
        if (!saved) return;

        // Restore model
        if (saved.model && saved.model !== 'auto') {
            const sel = document.getElementById('welcome-model-select');
            if (sel && sel.querySelector(`option[value="${saved.model}"]`)) {
                sel.value = saved.model;
            }
        }

        // Restore toggles
        chatState.useArtifacts = !!saved.useArtifacts;
        chatState.useWebSearch = !!saved.useWebSearch;
        // useKnowledge: Default ist an — nur explizit "false" deaktiviert
        chatState.useKnowledge = saved.useKnowledge !== undefined ? !!saved.useKnowledge : true;
        chatState.useRules = saved.useRules !== undefined ? !!saved.useRules : false;
        syncArtifactCheckbox();
        syncWebSearchCheckbox();
        syncKnowledgeCheckbox();
        syncRulesCheckbox();

        // Restore dual mode
        if (saved.dualMode) {
            toggleDualMode(true);
            if (saved.dualModelB) {
                chatState.dualModelB = saved.dualModelB;
                ['welcome-dual-model-select', 'chat-dual-model-select'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.querySelector(`option[value="${saved.dualModelB}"]`)) {
                        el.value = saved.dualModelB;
                    }
                });
            }
        }
    } catch(e) {}
}

function renderWebSources(sources) {
    if (!sources || sources.length === 0) return;
    const container = document.querySelector('.chat-messages');
    if (!container) return;

    // Quellen-Box nach der letzten Nachricht einfuegen — letztes .chat-msg statt
    // ':last-child' (das Banner .cd-banner kann das letzte Kind sein, s. renderStreamingMessage).
    const msgs = container.querySelectorAll('.chat-msg');
    const lastMsg = msgs.length ? msgs[msgs.length - 1] : null;
    if (!lastMsg) return;

    let existing = lastMsg.querySelector('.web-sources');
    if (existing) existing.remove();

    let html = '<div class="web-sources"><div class="web-sources-label"><span class="material-symbols-rounded" style="font-size:14px">travel_explore</span> Quellen</div><div class="web-sources-list">';
    const seen = new Set();
    for (const s of sources) {
        if (seen.has(s.url)) continue;
        seen.add(s.url);
        const domain = (new URL(s.url)).hostname.replace('www.', '');
        html += `<a href="${s.url}" target="_blank" rel="noopener" class="web-source-item" title="${s.title}"><span class="web-source-domain">${domain}</span><span class="web-source-title">${s.title}</span></a>`;
    }
    html += '</div></div>';

    lastMsg.insertAdjacentHTML('beforeend', html);
}

// ==========================================
// Artifacts Used (per message)
// ==========================================
function renderKnowledgeUsedHtml(chunks) {
    if (!chunks || chunks.length === 0) return '';
    const items = chunks.map(c => {
        const sources = (c.sources || []).map(s => {
            const label = s === 'dense' ? 'Vector' : (s === 'sparse' ? 'Keyword' : (s === 'graph' ? 'Graph' : s));
            return `<span class="artifact-used-source">${label}</span>`;
        }).join('');
        return `<div class="artifact-used-item" onclick="window.open('/wissen/${c.document_id}', '_blank')" style="cursor:pointer;">
            <span class="artifact-used-type">Wissen</span>
            <span class="artifact-used-name">${esc(c.title || '(ohne Titel)')}</span>
            ${c.category ? `<span class="artifact-used-namespace">${esc(c.category)}</span>` : ''}
            ${sources}
            <span class="artifact-used-source" title="Score">${(c.score || 0).toFixed(2)}</span>
        </div>`;
    }).join('');

    return `<div class="artifacts-used-toggle" onclick="toggleArtifactsUsed(this)">
        <span class="material-symbols-rounded" style="font-size:13px">library_books</span>
        ${chunks.length} Wissens-Eintr${chunks.length !== 1 ? 'aege' : 'ag'} verwendet
        <span class="material-symbols-rounded arrow-icon">expand_more</span>
    </div>
    <div class="artifacts-used-list">${items}</div>`;
}

function renderArtifactsUsedHtml(artifacts) {
    if (!artifacts || artifacts.length === 0) return '';
    const sourceLabel = (s) => {
        if (s === 'manual') return 'angehängt';
        if (s === 'keyword') return 'Keyword';
        if (s === 'embedding') return 'RAG';
        return s || '';
    };
    let items = artifacts.map(a => {
        const ns = a.namespace ? `<span class="artifact-used-namespace">${esc(a.namespace)}</span>` : '';
        return `<div class="artifact-used-item" onclick="toggleArtifactDetail(${a.id}, this)" data-artifact-id="${a.id}">
            <span class="artifact-used-type" data-type="${esc(a.type)}">${esc(a.type)}</span>
            <span class="artifact-used-name">${esc(a.name)}</span>
            ${ns}
            <span class="artifact-used-source">${sourceLabel(a.source)}</span>
        </div>
        <div class="artifact-used-detail" id="artifact-detail-${a.id}"></div>`;
    }).join('');

    return `<div class="artifacts-used-toggle" onclick="toggleArtifactsUsed(this)">
        <span class="material-symbols-rounded" style="font-size:13px">library_books</span>
        ${artifacts.length} Artefakt${artifacts.length !== 1 ? 'e' : ''} verwendet
        <span class="material-symbols-rounded arrow-icon">expand_more</span>
    </div>
    <div class="artifacts-used-list">${items}</div>`;
}

function renderArtifactsUsedInMessage(msg) {
    if (!msg.artifacts_used || msg.artifacts_used.length === 0) return;
    const container = document.querySelector('.chat-messages');
    if (!container) return;
    // letztes Assistant-.chat-msg statt ':last-child' (Banner kann letztes Kind sein)
    const assistants = container.querySelectorAll('.chat-msg.assistant');
    const lastMsg = assistants.length ? assistants[assistants.length - 1] : null;
    if (!lastMsg) return;
    // Remove existing
    const existing = lastMsg.querySelector('.artifacts-used-toggle');
    if (existing) existing.remove();
    const existingList = lastMsg.querySelector('.artifacts-used-list');
    if (existingList) existingList.remove();
    lastMsg.insertAdjacentHTML('beforeend', renderArtifactsUsedHtml(msg.artifacts_used));
}

function toggleArtifactsUsed(toggleEl) {
    toggleEl.classList.toggle('open');
    const list = toggleEl.nextElementSibling;
    if (list) list.classList.toggle('open');
}

async function toggleArtifactDetail(id, itemEl) {
    const detail = document.getElementById('artifact-detail-' + id);
    if (!detail) return;
    if (detail.classList.contains('open')) {
        detail.classList.remove('open');
        return;
    }
    // Load if empty
    if (!detail.innerHTML) {
        detail.innerHTML = '<em>Laden...</em>';
        try {
            const res = await api('GET', '/admin/artifacts/' + id);
            if (res.success) {
                const a = res.data;
                const meta = a.meta || {};
                let html = `<strong>${esc(meta.name || a.slug)}</strong>`;
                if (meta.namespace) html += ` <span style="color:var(--color-gray-400)">· ${esc(meta.namespace)}</span>`;
                if (meta.tags) html += `<br><span style="color:var(--color-gray-400)">Tags: ${esc(meta.tags)}</span>`;
                html += `<hr style="margin:6px 0;border:0;border-top:1px solid var(--color-gray-200)">`;
                html += `<div>${esc(a.resolved_content || a.content || '').substring(0, 500)}${(a.resolved_content || a.content || '').length > 500 ? '...' : ''}</div>`;
                detail.innerHTML = html;
            } else {
                detail.innerHTML = '<em>Fehler beim Laden</em>';
            }
        } catch(e) {
            detail.innerHTML = '<em>Fehler beim Laden</em>';
        }
    }
    detail.classList.add('open');
}

// ==========================================
// Dual Mode
// ==========================================
function toggleDualMode(enabled) {
    chatState.dualMode = enabled;
    // Sync alle Dual-Checkboxen
    ['welcome-use-dual', 'chat-use-dual'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = enabled;
    });
    // Zweiten Modell-Selector anzeigen/verstecken
    ['welcome-dual-model-select', 'chat-dual-model-select'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = enabled ? 'inline-block' : 'none';
    });
    // Auto deaktivieren wenn Dual aktiv
    ['welcome-model-select', 'chat-model-select'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const autoOpt = el.querySelector('option[value="auto"]');
        if (autoOpt) {
            autoOpt.disabled = enabled;
            if (enabled && el.value === 'auto') el.value = el.options[1]?.value || '';
        }
    });
}

async function doSendDualMessage(convId, message, modelA, modelB, attachments) {
    chatState.isStreaming = true;
    updateSendButtons();

    const groupId = crypto.randomUUID();

    // User-Nachricht anzeigen
    chatState.messages.push({
        id: Date.now(), role: 'user', content: message, attachments,
        created_at: new Date().toISOString(),
    });

    // Dual-Platzhalter
    const dualMsg = {
        id: Date.now() + 1,
        role: 'dual',
        dual_group_id: groupId,
        model_a: modelA,
        model_b: modelB,
        content_a: '',
        content_b: '',
        done_a: false,
        done_b: false,
        comparison: null,
        comparison_model: null,
    };
    chatState.messages.push(dualMsg);
    renderMessages();

    // Zwei parallele Streams
    chatState._dualWatchdog = null;
    chatState._dualStreams = {};
    await Promise.all([
        streamDualSlot(convId, message, modelA, attachments, groupId, 'a', dualMsg),
        streamDualSlot(convId, message, modelB, attachments, groupId, 'b', dualMsg),
    ]);
    if (chatState._dualWatchdog) { chatState._dualWatchdog.stop(); chatState._dualWatchdog = null; }
    chatState._dualStreams = {};

    chatState.isStreaming = false;
    updateSendButtons();
    addCodeBlockButtons();

    // Retry nach Stall (Dual)
    if (chatState._dualRetry) {
        const r = chatState._dualRetry;
        chatState._dualRetry = null;
        chatState.messages.pop(); // dual placeholder
        chatState.messages.pop(); // user msg
        renderMessages();
        await r.fn.apply(null, r.args);
        return;
    }

    // Sidebar aktualisieren
    if (chatState.activeConv && chatState.activeConv.title === 'Neuer Chat') {
        const r = await api('GET', '/chat/conversations/' + convId);
        if (r.success && r.data.title !== 'Neuer Chat') {
            chatState.activeConv.title = r.data.title;
            renderChatHeader();
            loadConversations();
        }
    }
}

async function streamDualSlot(convId, message, model, attachments, groupId, slot, dualMsg) {
    const abortCtrl = new AbortController();
    chatState._dualStreams = chatState._dualStreams || {};
    chatState._dualStreams[slot] = abortCtrl;
    // Nur ein Watchdog pro Dual-Gruppe — slot A erstellt ihn, slot B teilt ihn
    let watchdog = chatState._dualWatchdog;
    if (!watchdog) {
        watchdog = createStreamWatchdog({
            retry: () => {
                // Beide Streams abbrechen, Retry mit gleicher Message
                Object.values(chatState._dualStreams || {}).forEach(c => c.abort('retry'));
                chatState._dualRetry = { fn: doSendDualMessage, args: [convId, message, model, chatState.dualModelB, attachments] };
            },
            cancel: () => {
                Object.values(chatState._dualStreams || {}).forEach(c => c.abort('user-cancel'));
            },
        });
        chatState._dualWatchdog = watchdog;
    }
    try {
        const payload = {
            message, model, attachments,
            use_web_search: chatState.useWebSearch,
            use_knowledge: chatState.useKnowledge,
            use_rules: chatState.useRules,
            dual_mode: true,
            dual_group_id: groupId,
            dual_slot: slot,
        };
        if (slot === 'a' && chatState.dualModelB) {
            payload.dual_model_b = chatState.dualModelB;
        }
        const response = await fetch(App.apiUrl + '/chat/conversations/' + convId + '/stream', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(payload),
            signal: abortCtrl.signal
        });

        if (!response.ok) {
            dualMsg['content_' + slot] = 'Fehler ' + response.status;
            dualMsg['done_' + slot] = true;
            renderDualPanel(dualMsg, slot);
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            watchdog.ping();
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                try {
                    const data = JSON.parse(line.slice(6));
                    if (data.type === 'artifacts_used') {
                        dualMsg['artifacts_' + slot] = data.artifacts || [];
                    } else if (data.type === 'knowledge_used') {
                        dualMsg['knowledge_' + slot] = data.chunks || [];
                    } else if (data.type === 'token') {
                        dualMsg['content_' + slot] += data.content;
                        renderDualPanel(dualMsg, slot);
                    } else if (data.type === 'done') {
                        dualMsg['done_' + slot] = true;
                        dualMsg['message_id_' + slot] = data.message_id;
                        dualMsg['model_used_' + slot] = data.model || model;
                        if (data.artifacts_used && data.artifacts_used.length > 0) {
                            dualMsg['artifacts_' + slot] = data.artifacts_used;
                        }
                        renderDualPanel(dualMsg, slot);
                        // Info-Icon in den Slot-Header einfuegen (renderDualPanel baut nur den Body neu)
                        if (data.message_id) {
                            const dc = document.querySelector(`[data-dual-group="${dualMsg.dual_group_id}"]`);
                            const hdr = dc ? dc.querySelector(`.dual-panel[data-slot="${slot}"] .dual-panel-header`) : null;
                            const copyBtn = hdr ? hdr.querySelector('.thx-icon-btn') : null;
                            if (copyBtn && !hdr.querySelector('button[onclick^="showMessageInfo"]')) {
                                copyBtn.insertAdjacentHTML('beforebegin', `<button class="thx-icon-btn" onclick="showMessageInfo(${data.message_id})" title="Details: Prompt, Tokens, Modell, Wissen"><span class="material-symbols-rounded">info</span></button>`);
                            }
                        }
                        if (dualMsg.done_a && dualMsg.done_b) showCompareButton(dualMsg);
                    } else if (data.type === 'error') {
                        dualMsg['content_' + slot] = 'Fehler: ' + (data.message || 'Unbekannt');
                        dualMsg['done_' + slot] = true;
                        renderDualPanel(dualMsg, slot);
                    }
                } catch(e) {}
            }
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            dualMsg['content_' + slot] = 'Verbindungsfehler: ' + err.message;
            dualMsg['done_' + slot] = true;
            renderDualPanel(dualMsg, slot);
        }
    } finally {
        if (chatState._dualStreams) delete chatState._dualStreams[slot];
    }
}

function renderDualPanel(dualMsg, slot) {
    const container = document.querySelector(`[data-dual-group="${dualMsg.dual_group_id}"]`);
    if (!container) { renderMessages(); return; }
    const panelEl = container.querySelector(`.dual-panel[data-slot="${slot}"]`);
    if (!panelEl) return;
    const body = panelEl.querySelector('.dual-panel-body');
    if (!body) return;
    const cursor = dualMsg['done_' + slot] ? '' : '<span class="streaming-cursor"></span>';
    body.innerHTML = renderMarkdown(dualMsg['content_' + slot]) + cursor;
    body.classList.add('markdown-rendered');

    // Artefakte anzeigen wenn fertig
    if (dualMsg['done_' + slot] && dualMsg['artifacts_' + slot]) {
        let existing = panelEl.querySelector('.artifacts-used-toggle');
        if (!existing) {
            panelEl.insertAdjacentHTML('beforeend', renderArtifactsUsedHtml(dualMsg['artifacts_' + slot]));
        }
    }

    // Smart-Scroll: einmal an den Beginn des Dual-Vergleichs, dann nicht mehr jagen
    const stillStreaming = !dualMsg['done_a'] || !dualMsg['done_b'];
    const mc = document.getElementById('chat-messages');
    smartStreamScroll(mc, container, stillStreaming);
}

function buildCompareModelOptions(groupId) {
    const sel = document.getElementById('chat-model-select');
    const current = sel ? sel.value : '';
    let html = '';
    for (const opt of sel.options) {
        if (opt.value === 'auto') continue;
        const selected = opt.value === current ? ' selected' : '';
        html += `<option value="${opt.value}"${selected}>${opt.textContent.trim()}</option>`;
    }
    return html;
}

function showCompareButton(dualMsg) {
    const container = document.querySelector(`[data-dual-group="${dualMsg.dual_group_id}"]`);
    if (!container || container.querySelector('.dual-compare-row')) return;
    const gid = dualMsg.dual_group_id;
    container.insertAdjacentHTML('beforeend',
        `<div class="dual-compare-row">
            <select id="comp-model-${gid}">${buildCompareModelOptions(gid)}</select>
            <button class="thx-btn thx-btn-secondary thx-btn-small dual-compare-btn" onclick="startDualComparison('${gid}')"><span class="material-symbols-rounded" style="font-size:16px">compare</span> Vergleichen</button>
        </div>`
    );
}

function renderDualMessageHtml(m) {
    const cursorA = m.done_a ? '' : '<span class="streaming-cursor"></span>';
    const cursorB = m.done_b ? '' : '<span class="streaming-cursor"></span>';
    const nameA = getModelName(m.model_a);
    const nameB = getModelName(m.model_b);

    let html = `<div class="chat-msg-dual" data-dual-group="${m.dual_group_id}">
        <div class="dual-split">
            <div class="dual-panel" data-slot="a">
                <div class="dual-panel-header">
                    <span>${nameA}</span>
                    ${m.message_id_a ? `<button class="thx-icon-btn" onclick="showMessageInfo(${m.message_id_a})" title="Details: Prompt, Tokens, Modell, Wissen"><span class="material-symbols-rounded">info</span></button>` : ''}
                    <button class="thx-icon-btn" onclick="navigator.clipboard.writeText(this.closest('.dual-panel').querySelector('.dual-panel-body').innerText)" title="Kopieren">
                        <span class="material-symbols-rounded">content_copy</span>
                    </button>
                </div>
                <div class="dual-panel-body markdown-rendered">${renderMarkdown(m.content_a)}${cursorA}</div>
                ${m.artifacts_a ? renderArtifactsUsedHtml(m.artifacts_a) : ''}
            </div>
            <div class="dual-panel" data-slot="b">
                <div class="dual-panel-header">
                    <span>${nameB}</span>
                    ${m.message_id_b ? `<button class="thx-icon-btn" onclick="showMessageInfo(${m.message_id_b})" title="Details: Prompt, Tokens, Modell, Wissen"><span class="material-symbols-rounded">info</span></button>` : ''}
                    <button class="thx-icon-btn" onclick="navigator.clipboard.writeText(this.closest('.dual-panel').querySelector('.dual-panel-body').innerText)" title="Kopieren">
                        <span class="material-symbols-rounded">content_copy</span>
                    </button>
                </div>
                <div class="dual-panel-body markdown-rendered">${renderMarkdown(m.content_b)}${cursorB}</div>
                ${m.artifacts_b ? renderArtifactsUsedHtml(m.artifacts_b) : ''}
            </div>
        </div>`;

    if (m.done_a && m.done_b && !m.comparison) {
        html += `<div class="dual-compare-row">
            <select id="comp-model-${m.dual_group_id}">${buildCompareModelOptions(m.dual_group_id)}</select>
            <button class="thx-btn thx-btn-secondary thx-btn-small dual-compare-btn" onclick="startDualComparison('${m.dual_group_id}')"><span class="material-symbols-rounded" style="font-size:16px">compare</span> Vergleichen</button>
        </div>`;
    }

    if (m.comparison) {
        const compName = getModelName(m.comparison_model);
        html += `<div class="dual-comparison">
            <div class="dual-panel-header"><span>${compName} — Vergleich</span>
                ${m.comparison_message_id ? `<button class="thx-icon-btn" onclick="showMessageInfo(${m.comparison_message_id})" title="Details: Prompt, Tokens, Modell"><span class="material-symbols-rounded">info</span></button>` : ''}
            </div>
            <div class="dual-panel-body markdown-rendered">${renderMarkdown(m.comparison)}</div>
        </div>`;
    }

    html += '</div>';
    return html;
}

async function startDualComparison(groupId) {
    const dualMsg = chatState.messages.find(m => m.dual_group_id === groupId);
    if (!dualMsg) return;

    const container = document.querySelector(`[data-dual-group="${groupId}"]`);
    if (!container) return;

    // Modell aus dem Compare-Select lesen
    const compSelect = document.getElementById('comp-model-' + groupId);
    const compModel = compSelect ? compSelect.value : (dualMsg.model_a || 'gpt-4');

    // Compare-Row ersetzen
    const row = container.querySelector('.dual-compare-row');
    const btn = container.querySelector('.dual-compare-btn');
    if (btn) btn.innerHTML = '<span class="material-symbols-rounded spin" style="font-size:16px">compare</span> Vergleich laeuft...';
    if (compSelect) compSelect.disabled = true;

    try {
        const response = await fetch(App.apiUrl + '/chat/conversations/' + chatState.activeId + '/dual-compare', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ dual_group_id: groupId, comparison_model: compModel })
        });

        if (!response.ok) throw new Error('Fehler ' + response.status);

        // Compare-Row entfernen, Comparison-Container einfuegen
        if (row) row.remove();
        dualMsg.comparison = '';
        dualMsg.comparison_model = compModel;

        const compHtml = `<div class="dual-comparison" data-comp-group="${groupId}">
            <div class="dual-panel-header"><span>${getModelName(compModel)} — Vergleich</span></div>
            <div class="dual-panel-body markdown-rendered"></div>
        </div>`;
        container.insertAdjacentHTML('beforeend', compHtml);

        const compBody = container.querySelector(`[data-comp-group="${groupId}"] .dual-panel-body`);
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                try {
                    const data = JSON.parse(line.slice(6));
                    if (data.type === 'token') {
                        dualMsg.comparison += data.content;
                        compBody.innerHTML = renderMarkdown(dualMsg.comparison) + '<span class="streaming-cursor"></span>';
                    } else if (data.type === 'done') {
                        compBody.innerHTML = renderMarkdown(dualMsg.comparison);
                        addCodeBlockButtons();
                        if (data.message_id) {
                            dualMsg.comparison_message_id = data.message_id;
                            // Info-Icon nachtraeglich in den Vergleichs-Header einfuegen
                            const compHeader = container.querySelector(`[data-comp-group="${groupId}"] .dual-panel-header`);
                            if (compHeader && !compHeader.querySelector('.material-symbols-rounded')) {
                                compHeader.insertAdjacentHTML('beforeend',
                                    `<button class="thx-icon-btn" onclick="showMessageInfo(${data.message_id})" title="Details: Prompt, Tokens, Modell"><span class="material-symbols-rounded">info</span></button>`);
                            }
                        }
                    }
                } catch(e) {}
            }
        }
    } catch (err) {
        App.showNotification('Vergleich fehlgeschlagen: ' + err.message, 'error');
        if (btn) { btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px">compare</span> Vergleichen'; }
        if (compSelect) compSelect.disabled = false;
    }
}

function groupMessagesForDisplay(messages) {
    const result = [];
    const dualGroups = {};

    // Erst alle Dual-Gruppen sammeln
    for (const m of messages) {
        if (!m.dual_group_id) continue;
        if (!dualGroups[m.dual_group_id]) {
            dualGroups[m.dual_group_id] = {
                role: 'dual', dual_group_id: m.dual_group_id,
                model_a: null, model_b: null,
                content_a: '', content_b: '',
                done_a: true, done_b: true,
                comparison: null, comparison_model: null,
            };
        }
        const g = dualGroups[m.dual_group_id];
        if (m.role === 'assistant' && m.dual_slot === 'a') { g.content_a = m.content; g.model_a = m.model_used; g.artifacts_a = m.artifacts_used || null; g.message_id_a = m.id; }
        else if (m.role === 'assistant' && m.dual_slot === 'b') { g.content_b = m.content; g.model_b = m.model_used; g.artifacts_b = m.artifacts_used || null; g.message_id_b = m.id; }
        else if (m.role === 'assistant' && m.dual_slot === 'comparison') { g.comparison = m.content; g.comparison_model = m.model_used; g.comparison_message_id = m.id; }
    }

    // Nachrichten aufbauen
    const usedGroups = new Set();
    for (const m of messages) {
        if (m.dual_group_id && m.role === 'assistant') continue; // Skip, wird als Dual-Block gerendert
        result.push(m);
        if (m.dual_group_id && m.role === 'user' && dualGroups[m.dual_group_id] && !usedGroups.has(m.dual_group_id)) {
            result.push(dualGroups[m.dual_group_id]);
            usedGroups.add(m.dual_group_id);
        }
    }
    return result;
}

function toggleArtifactPanel() {
    if (!chatState.activeId) return;
    chatState.artifactPanelOpen = !chatState.artifactPanelOpen;
    const panel = document.getElementById('artifact-panel');
    panel.style.display = chatState.artifactPanelOpen ? 'flex' : 'none';
    if (chatState.artifactPanelOpen) {
        renderAttachedArtifacts();
        loadNamespaces();
    }
}

// ===== Nachrichten-Info-Panel (Prompt, Tokens, Modell, Wissen) =====
function closeMessageInfo() {
    const p = document.getElementById('msg-info-panel');
    if (p) p.style.display = 'none';
}

async function showMessageInfo(messageId) {
    const panel = document.getElementById('msg-info-panel');
    const body = document.getElementById('msg-info-body');
    if (!panel || !body) return;
    panel.style.display = 'flex';
    body.innerHTML = '<p style="color:var(--color-text-secondary,#64748b);">Lade Details…</p>';
    try {
        const res = await api('GET', '/chat/message-debug?id=' + encodeURIComponent(messageId));
        if (!res.success) {
            body.innerHTML = '<p style="color:#be123c;">' + esc(res.message || 'Konnte Details nicht laden.') + '</p>';
            return;
        }
        body.innerHTML = renderMessageInfo(res.data);
    } catch (e) {
        body.innerHTML = '<p style="color:#be123c;">' + esc(e.message || 'Verbindungsfehler') + '</p>';
    }
}

function renderMessageInfo(d) {
    const fmt = n => (n === null || n === undefined) ? '–' : new Intl.NumberFormat('de-DE').format(n);
    let html = '';

    // Kennzahlen
    html += '<div class="msg-info-section"><h4>Kennzahlen</h4><dl class="msg-info-kv">';
    html += '<dt>Modell</dt><dd><strong>' + esc(d.model || '–') + '</strong>' + (d.provider ? ' <span style="color:#64748b;">(' + esc(d.provider) + ')</span>' : '') + '</dd>';
    html += '<dt>Tokens</dt><dd>' + fmt(d.tokens_input) + ' rein / ' + fmt(d.tokens_output) + ' raus' + (d.tokens_total ? ' · ' + fmt(d.tokens_total) + ' gesamt' : '') + '</dd>';
    if (d.total_ms !== null && d.total_ms !== undefined) {
        html += '<dt>Dauer</dt><dd>' + fmt(d.total_ms) + ' ms' + (d.ttft_ms ? ' (1. Wort nach ' + fmt(d.ttft_ms) + ' ms)' : '') + '</dd>';
    }
    if (d.created_at) html += '<dt>Zeitpunkt</dt><dd>' + esc(formatFullDateTime(d.created_at)) + '</dd>';
    html += '</dl></div>';

    if (!d.has_detail) {
        html += '<div class="msg-info-section"><p style="color:#64748b;">Für diese Nachricht ist kein vollständiges Protokoll (Prompt + Wissen) vorhanden. Das gibt es erst für neuere Anfragen, und die Detaildaten werden nach 90 Tagen automatisch gelöscht.</p></div>';
        return html;
    }

    // Reranking
    if (d.rerank && d.rerank.enabled) {
        const rk = d.rerank;
        html += '<div class="msg-info-section"><h4>Reranking</h4>';
        if (rk.applied) {
            html += '<dl class="msg-info-kv">'
                + '<dt>Reranker</dt><dd>' + esc(rk.model || '') + ' <span style="color:#64748b;">(' + esc(rk.provider || '') + ')</span></dd>'
                + '<dt>Kandidaten</dt><dd>' + esc(rk.candidates) + ' geprüft → ' + esc(rk.kept) + ' behalten' + (rk.ms != null ? ' · ' + esc(rk.ms) + ' ms' : '') + '</dd>'
                + '</dl>';
            const ranking = rk.ranking || [];
            if (ranking.length) {
                html += '<details style="margin-top:.5rem;"><summary style="cursor:pointer;font-weight:600;">Vollständige Rangliste (' + ranking.length + ')</summary><div style="margin-top:.4rem;">';
                ranking.forEach((r, i) => {
                    const st = r.source_type || '';
                    const isCrm = st.indexOf('crm') === 0;
                    const badge = st ? '<span class="msg-info-badge ' + (isCrm ? 'crm' : 'src') + '">' + esc(st) + '</span> ' : '';
                    const keptMark = r.kept
                        ? '<span style="color:#16a34a;font-weight:600;">✓</span> '
                        : '<span style="color:#94a3b8;">·</span> ';
                    html += '<div style="display:flex;justify-content:space-between;gap:8px;padding:2px 0;' + (r.kept ? '' : 'opacity:.6;') + '">'
                        + '<span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + keptMark + (i + 1) + '. ' + esc(r.title || 'Ohne Titel') + '</span>'
                        + '<span style="white-space:nowrap;color:#64748b;">' + badge + esc(Number(r.score).toFixed(3)) + '</span></div>';
                });
                html += '</div></details>';
            }
        } else {
            html += '<p style="color:#be123c;">Reranking war aktiv, wurde aber nicht angewandt' + (rk.error ? ': ' + esc(rk.error) : '') + '. Es gilt die normale Vektor-Reihenfolge.</p>';
        }
        html += '</div>';
    }

    // Wissens-Chunks
    const chunks = d.chunks || [];
    html += '<div class="msg-info-section"><h4>Herangezogenes Wissen (' + chunks.length + ')</h4>';
    if (!chunks.length) {
        html += '<p style="color:#64748b;">Kein Wissen herangezogen (Wissens-Schalter aus oder kein Treffer).</p>';
    } else {
        chunks.forEach((c, i) => {
            const st = c.source_type || '';
            const isCrm = st.indexOf('crm') === 0;
            const badge = st ? '<span class="msg-info-badge ' + (isCrm ? 'crm' : 'src') + '">' + esc(st) + '</span> ' : '';
            const wc = (c.word_count !== null && c.word_count !== undefined) ? esc(c.word_count) + ' W · ' : '';
            const score = (c.score !== null && c.score !== undefined) ? 'Score ' + esc(Number(c.score).toFixed(3)) : '';
            html += '<details class="msg-info-chunk"><summary><span>' + (i + 1) + '. ' + esc(c.title || 'Ohne Titel') + '</span>'
                 + '<span style="font-weight:400;white-space:nowrap;color:#64748b;">' + badge + wc + score + '</span></summary>'
                 + '<pre>' + esc(c.content || '') + '</pre></details>';
        });
    }
    html += '</div>';

    // System-Prompt
    html += '<div class="msg-info-section msg-info-prompt"><h4>Vollständiger System-Prompt</h4>'
         + '<details><summary style="cursor:pointer;font-weight:600;">Anzeigen (' + fmt((d.system_prompt || '').length) + ' Zeichen)</summary>'
         + '<pre>' + esc(d.system_prompt || '') + '</pre></details></div>';

    return html;
}

async function loadAttachedArtifacts() {
    if (!chatState.activeId) return;
    const res = await api('GET', '/chat/conversations/' + chatState.activeId + '/artifacts');
    if (res.success) {
        chatState.attachedArtifacts = res.data || [];
        renderAttachedArtifacts();
        renderChatHeader();
    }
}

function renderAttachedArtifacts() {
    const el = document.getElementById('attached-artifacts-list');
    if (!el) return;
    if (!chatState.attachedArtifacts.length) {
        el.innerHTML = '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-400);padding:4px 0">Keine Artefakte angehaengt</div>';
        return;
    }
    el.innerHTML = chatState.attachedArtifacts.map(a => {
        const meta = a.meta || {};
        return `<div class="thx-chip">
            <span class="artifact-chip-type">${esc(meta.type || '?')}</span>
            <span class="artifact-chip-name" title="${esc(meta.name || a.slug)}">${esc(meta.name || a.slug)}</span>
            <button onclick="detachArtifact(${a.id})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size:14px">close</span>
            </button>
        </div>`;
    }).join('');
}

async function loadNamespaces() {
    const res = await api('GET', '/chat/artifacts?action=namespaces');
    if (res.success) {
        chatState.artifactNamespaces = res.data || [];
        const sel = document.getElementById('artifact-namespace-select');
        if (sel) {
            sel.innerHTML = '<option value="">Namespace waehlen...</option>' +
                chatState.artifactNamespaces.map(n =>
                    `<option value="${esc(n.namespace)}">${esc(n.namespace)} (${n.count})</option>`
                ).join('');
        }
        // Also update datalist in create modal
        const dl = document.getElementById('artifact-ns-datalist');
        if (dl) {
            dl.innerHTML = chatState.artifactNamespaces.map(n =>
                `<option value="${esc(n.namespace)}">`
            ).join('');
        }
    }
}

async function attachNamespace(ns) {
    if (!ns || !chatState.activeId) return;
    const res = await api('POST', '/chat/conversations/' + chatState.activeId + '/artifacts', { namespace: ns });
    if (res.success) {
        App.showNotification('Namespace "' + ns + '" angehaengt', 'success');
        loadAttachedArtifacts();
    } else {
        App.showNotification(res.message || 'Fehler', 'error');
    }
    document.getElementById('artifact-namespace-select').value = '';
}

async function attachArtifact(artifactId) {
    if (!chatState.activeId) return;
    const res = await api('POST', '/chat/conversations/' + chatState.activeId + '/artifacts', { artifact_ids: [artifactId] });
    if (res.success) {
        loadAttachedArtifacts();
        // Re-render search results to update "attached" state
        const searchInput = document.getElementById('artifact-search-input');
        if (searchInput && searchInput.value) searchArtifacts(searchInput.value);
    }
}

async function detachArtifact(artifactId) {
    if (!chatState.activeId) return;
    await api('DELETE', '/chat/conversations/' + chatState.activeId + '/artifacts/' + artifactId);
    loadAttachedArtifacts();
    // Re-render search results
    const searchInput = document.getElementById('artifact-search-input');
    if (searchInput && searchInput.value) searchArtifacts(searchInput.value);
}

let artifactSearchTimeout;
function debounceArtifactSearch(val) {
    clearTimeout(artifactSearchTimeout);
    artifactSearchTimeout = setTimeout(() => searchArtifacts(val), 300);
}

function filterArtifactsByType() {
    const searchInput = document.getElementById('artifact-search-input');
    searchArtifacts(searchInput ? searchInput.value : '');
}

async function searchArtifacts(query) {
    const type = document.getElementById('artifact-type-filter')?.value || '';
    let url = '/chat/artifacts?limit=20';
    if (query) url += '&search=' + encodeURIComponent(query);
    if (type) url += '&type=' + encodeURIComponent(type);

    const res = await api('GET', url);
    if (res.success) {
        renderArtifactSearchResults(res.data.items || []);
    }
}

function renderArtifactSearchResults(items) {
    const el = document.getElementById('artifact-search-results');
    if (!el) return;
    if (!items.length) {
        el.innerHTML = '<div style="font-size: var(--d-fs-sm);color:var(--color-gray-400);padding:8px 0">Keine Ergebnisse</div>';
        return;
    }
    const attachedIds = chatState.attachedArtifacts.map(a => a.id);
    el.innerHTML = items.map(a => {
        const meta = a.meta || {};
        const isAttached = attachedIds.includes(a.id);
        const preview = (a.resolved_content || '').substring(0, 80);
        return `<div class="artifact-search-item ${isAttached ? 'attached' : ''}">
            <div class="artifact-search-info">
                <div><span class="artifact-chip-type">${esc(meta.type || '?')}</span> <span class="artifact-search-name">${esc(meta.name || a.slug)}</span></div>
                ${preview ? `<div class="artifact-search-preview">${esc(preview)}...</div>` : ''}
            </div>
            <button onclick="attachArtifact(${a.id})">+</button>
        </div>`;
    }).join('');
}

// ==========================================
// Create Artifact from Chat
// ==========================================
let createArtifactSourceMsgId = null;

function openCreateArtifactModal(msgId = null, prefill = null) {
    createArtifactSourceMsgId = msgId;

    if (prefill) {
        document.getElementById('artifact-create-content').value = prefill.content || '';
        document.getElementById('artifact-create-name').value = prefill.name || '';
        document.getElementById('artifact-create-tags').value = (prefill.tags || []).join(', ');
        document.getElementById('artifact-create-type').value = prefill.type || 'Wissen';
        document.getElementById('artifact-create-namespace').value = prefill.namespace || '';
    } else if (msgId) {
        const msg = chatState.messages.find(m => m.id === msgId);
        if (!msg) return;
        document.getElementById('artifact-create-content').value = msg.content || '';
        document.getElementById('artifact-create-name').value = '';
        document.getElementById('artifact-create-tags').value = '';
        document.getElementById('artifact-create-type').value = 'Wissen';
        document.getElementById('artifact-create-namespace').value = '';
    }

    // Load namespaces for datalist if not yet loaded
    if (!chatState.artifactNamespaces.length) loadNamespaces();

    document.getElementById('artifact-create-modal').classList.add('active');
}

// ==========================================
// Conversation Artifact Analysis
// ==========================================
async function analyzeConversationForArtifacts() {
    if (!chatState.activeId || chatState.isStreaming) return;

    const btn = document.getElementById('btn-analyze-artifacts');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded spin" style="font-size:16px">progress_activity</span> Wird vorbereitet...';

    try {
        // Chat-Verlauf als Text sammeln
        const messages = chatState.messages.filter(m => m.role === 'user' || m.role === 'assistant');
        let chatText = '';
        for (const m of messages) {
            if (m.role === 'dual') {
                chatText += `\n\n[Modell A]: ${m.content_a || ''}\n\n[Modell B]: ${m.content_b || ''}`;
            } else {
                const role = m.role === 'user' ? 'Nutzer' : 'Assistent';
                chatText += `\n\n[${role}]: ${m.content || ''}`;
            }
        }
        chatText = chatText.trim();
        if (!chatText) { App.showNotification('Kein Chat-Inhalt vorhanden', 'info'); return; }

        // Kontext bauen — mit spezieller Anweisung fuer Chat-Analyse
        const title = chatState.activeConv?.title || 'Chat';
        const model = chatState.activeConv?.model || '';
        const context = `Chat-Konversation "${title}". WICHTIG: Dies ist ein Chat-Verlauf. Nutzer-Aussagen enthalten oft implizite Regeln, Korrekturen und Vorgaben. Beispiele: "Fazit ist keine Ueberschrift" = Regel fuer Ueberschriften. "Bitte per Du" = Anrede-Regel. "Nicht so formell" = Tonalitaet. Extrahiere solche Anweisungen als eigenstaendige Artefakte (Typ: Regel oder Tonalitaet).`;

        // Als Text-Import anlegen (ueber die Import-API)
        const blob = new Blob([chatText], { type: 'text/plain' });
        const file = new File([blob], `chat-${chatState.activeId}-${title}.txt`, { type: 'text/plain' });
        const fd = new FormData();
        fd.append('file', file);
        fd.append('user_context', context);

        const uploadRes = await fetch(App.apiUrl + '/admin/artifact-import', {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken },
            body: fd
        });
        const uploadData = await uploadRes.json();

        if (!uploadData.success) {
            throw new Error(uploadData.message || 'Import fehlgeschlagen');
        }

        // Zum Artefakt-Browser weiterleiten mit Import-ID
        const importId = uploadData.data.id;
        window.location.href = `/admin/artifacts?import=${importId}&context=${encodeURIComponent(context)}`;

    } catch (e) {
        App.showNotification('Fehler: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px">auto_awesome</span> Konversation analysieren';
    }
}

function renderArtifactSuggestions(suggestions) {
    const container = document.getElementById('artifact-suggestions');
    const list = document.getElementById('artifact-suggestions-list');
    container.style.display = 'block';

    list.innerHTML = suggestions.map((s, i) => `
        <div class="artifact-suggestion-card" id="suggestion-${i}">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                <span class="artifact-chip-type">${esc(s.type)}</span>
                <strong style="font-size: var(--d-fs-sm)">${esc(s.name)}</strong>
            </div>
            <div style="font-size: var(--d-fs-xs);color:var(--color-gray-500);margin-bottom:8px;max-height:60px;overflow:hidden;line-height:1.4">
                ${esc((s.content || '').substring(0, 200))}${s.content && s.content.length > 200 ? '...' : ''}
            </div>
            <div style="display:flex;gap:6px">
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="approveSuggestion(${i})">Erstellen</button>
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="editSuggestion(${i})">Bearbeiten</button>
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="dismissSuggestion(${i})" style="color:var(--color-gray-400)">Verwerfen</button>
            </div>
        </div>
    `).join('');
}

async function approveSuggestion(index) {
    const s = chatState.artifactSuggestions[index];
    if (!s) return;

    const tags = Array.isArray(s.tags) ? s.tags : [];
    const meta = { type: s.type, name: s.name, tags, is_active: true };
    if (s.namespace) meta.namespace = s.namespace;

    const payload = { text: s.content, meta };
    if (chatState.activeId) payload.conversation_id = chatState.activeId;

    const res = await api('POST', '/chat/artifacts', payload);
    if (res.success) {
        App.showNotification('Artefakt "' + s.name + '" erstellt', 'success');
        const card = document.getElementById('suggestion-' + index);
        if (card) card.remove();
        checkSuggestionsEmpty();
        if (chatState.activeId) loadAttachedArtifacts();
    } else {
        App.showNotification(res.message || 'Fehler', 'error');
    }
}

function editSuggestion(index) {
    const s = chatState.artifactSuggestions[index];
    if (!s) return;
    openCreateArtifactModal(null, {
        type: s.type,
        name: s.name,
        content: s.content,
        tags: s.tags || [],
        namespace: s.namespace || '',
    });
    const card = document.getElementById('suggestion-' + index);
    if (card) card.remove();
    checkSuggestionsEmpty();
}

function dismissSuggestion(index) {
    const card = document.getElementById('suggestion-' + index);
    if (card) card.remove();
    checkSuggestionsEmpty();
}

function checkSuggestionsEmpty() {
    const list = document.getElementById('artifact-suggestions-list');
    if (list && !list.children.length) {
        document.getElementById('artifact-suggestions').style.display = 'none';
    }
}

function closeCreateArtifactModal() {
    document.getElementById('artifact-create-modal').classList.remove('active');
    createArtifactSourceMsgId = null;
}

async function saveNewArtifact() {
    const text = document.getElementById('artifact-create-content').value.trim();
    const name = document.getElementById('artifact-create-name').value.trim();
    const type = document.getElementById('artifact-create-type').value;
    const tagsStr = document.getElementById('artifact-create-tags').value.trim();
    const namespace = document.getElementById('artifact-create-namespace').value.trim();

    if (!text || !name) {
        App.showNotification('Name und Inhalt erforderlich', 'error');
        return;
    }

    const tags = tagsStr ? tagsStr.split(',').map(t => t.trim()).filter(Boolean) : [];
    const meta = { type, name, tags, is_active: true };
    if (namespace) meta.namespace = namespace;

    const payload = { text, meta };
    if (chatState.activeId) payload.conversation_id = chatState.activeId;

    const res = await api('POST', '/chat/artifacts', payload);
    if (res.success) {
        App.showNotification('Artefakt "' + name + '" erstellt', 'success');
        closeCreateArtifactModal();
        // Refresh attached list
        if (chatState.activeId) {
            loadAttachedArtifacts();
        }
    } else {
        App.showNotification(res.message || 'Fehler beim Erstellen', 'error');
    }
}

// ==========================================
// Feedback
// ==========================================
let feedbackRating = null;

function openChatFeedback() {
    if (!chatState.activeId) return;
    const modal = document.getElementById('feedback-modal');
    const fb = chatState.feedback;
    feedbackRating = fb ? fb.rating : null;
    document.getElementById('feedback-comment').value = fb ? (fb.comment || '') : '';
    document.getElementById('fb-thumb-positive').classList.toggle('active', feedbackRating === 'positive');
    document.getElementById('fb-thumb-negative').classList.toggle('active', feedbackRating === 'negative');
    document.getElementById('btn-delete-feedback').style.display = fb ? 'inline-flex' : 'none';
    modal.classList.add('active');
}

function closeChatFeedback() {
    document.getElementById('feedback-modal').classList.remove('active');
}

function selectFeedbackRating(rating) {
    feedbackRating = feedbackRating === rating ? null : rating;
    document.getElementById('fb-thumb-positive').classList.toggle('active', feedbackRating === 'positive');
    document.getElementById('fb-thumb-negative').classList.toggle('active', feedbackRating === 'negative');
}

async function submitChatFeedback() {
    if (!chatState.activeId || !feedbackRating) {
        App.showNotification('Bitte waehle eine Bewertung', 'warning');
        return;
    }
    const res = await api('POST', '/chat/conversations/' + chatState.activeId + '/feedback', {
        rating: feedbackRating,
        comment: document.getElementById('feedback-comment').value.trim()
    });
    if (res.success) {
        chatState.feedback = res.data;
        updateFeedbackButton();
        closeChatFeedback();
        App.showNotification('Feedback gespeichert', 'success');
    } else {
        App.showNotification(res.message || 'Fehler', 'error');
    }
}

async function deleteChatFeedback() {
    if (!chatState.activeId) return;
    const res = await api('DELETE', '/chat/conversations/' + chatState.activeId + '/feedback');
    if (res.success) {
        chatState.feedback = null;
        updateFeedbackButton();
        closeChatFeedback();
        App.showNotification('Feedback geloescht', 'success');
    }
}

function updateFeedbackButton() {
    const btn = document.getElementById('btn-feedback');
    if (!btn) return;
    btn.classList.remove('has-positive', 'has-negative');
    if (chatState.feedback && chatState.feedback.rating) {
        btn.classList.add('has-' + chatState.feedback.rating);
    }
}

// ==========================================
// Expose functions globally
// ==========================================
window.newChat = newChat;
window.selectConversation = selectConversation;
window.handleSearch = handleSearch;
window.triggerUpload = triggerUpload;
window.handleFileSelect = handleFileSelect;
window.uploadFiles = uploadFiles;
window.removeAttachment = removeAttachment;
window.handleWelcomeKey = handleWelcomeKey;
window.handleChatKey = handleChatKey;
window.sendWelcomeMessage = sendWelcomeMessage;
window.sendMessage = sendMessage;
window.autoResize = autoResize;
window.startTitleEdit = startTitleEdit;
window.copyMessage = copyMessage;
window.copyCodeBlock = copyCodeBlock;
window.navigateToFolder = navigateToFolder;
window.navigateUp = navigateUp;
window.togglePinConversation = togglePinConversation;
window.openProjectModal = openProjectModal;
window.closeProjectModal = closeProjectModal;
window.selectProjectColor = selectProjectColor;
window.saveProject = saveProject;
window.deleteProject = deleteProject;
window.showConvContextMenu = showConvContextMenu;
window.showProjectMenu = showProjectMenu;
window.showConvMenu = showConvMenu;
window.renameConv = renameConv;
window.deleteConv = deleteConv;
window.openMoveModal = openMoveModal;
window.closeMoveModal = closeMoveModal;
window.moveToProject = moveToProject;
window.onConvDragStart = onConvDragStart;
window.onConvDragEnd = onConvDragEnd;
window.onFolderDragStart = onFolderDragStart;
window.onFolderDragEnd = onFolderDragEnd;
window.onProjectDragOver = onProjectDragOver;
window.onProjectDragLeave = onProjectDragLeave;
window.onProjectDrop = onProjectDrop;
window.onRootDropZoneDragOver = onRootDropZoneDragOver;
window.onRootDropZoneDragLeave = onRootDropZoneDragLeave;
window.onRootDrop = onRootDrop;
window.openMoveFolderModal = openMoveFolderModal;
window.moveFolderToParent = moveFolderToParent;
window.openSysPromptModal = openSysPromptModal;
window.closeSysPromptModal = closeSysPromptModal;
window.saveSysPrompt = saveSysPrompt;
window.showCustomerSelect = showCustomerSelect;
window.openShareDialog = openShareDialog;
window.openShareDialogFor = openShareDialogFor;
window.closeShareModal = closeShareModal;
window.debounceSearchUsers = debounceSearchUsers;
window.addShare = addShare;
window.updateSharePermission = updateSharePermission;
window.removeShare = removeShare;
window.syncArtifactCheckbox = syncArtifactCheckbox;
window.syncWebSearchCheckbox = syncWebSearchCheckbox;
window.syncKnowledgeCheckbox = syncKnowledgeCheckbox;
window.syncDualModelB = syncDualModelB;
window.saveSettingsToLocalStorage = saveSettingsToLocalStorage;
window.toggleSidebar = toggleSidebar;
window.toggleArtifactsUsed = toggleArtifactsUsed;
window.toggleArtifactDetail = toggleArtifactDetail;
window.toggleArtifactPanel = toggleArtifactPanel;
window.attachNamespace = attachNamespace;
window.attachArtifact = attachArtifact;
window.detachArtifact = detachArtifact;
window.debounceArtifactSearch = debounceArtifactSearch;
window.filterArtifactsByType = filterArtifactsByType;
window.openCreateArtifactModal = openCreateArtifactModal;
window.closeCreateArtifactModal = closeCreateArtifactModal;
window.saveNewArtifact = saveNewArtifact;
window.analyzeConversationForArtifacts = analyzeConversationForArtifacts;
window.approveSuggestion = approveSuggestion;
window.editSuggestion = editSuggestion;
window.dismissSuggestion = dismissSuggestion;
window.openChatFeedback = openChatFeedback;
window.closeChatFeedback = closeChatFeedback;
window.selectFeedbackRating = selectFeedbackRating;
window.submitChatFeedback = submitChatFeedback;
window.deleteChatFeedback = deleteChatFeedback;

// ==========================================
// Textbausteine (Snippets)
// ==========================================
let snippetTargetTextarea = null;
let snippetsCache = null;

window.openSnippetPicker = function(textareaId) {
    snippetTargetTextarea = document.getElementById(textareaId);
    document.getElementById('snippet-modal').style.display = 'flex';
    document.getElementById('snippet-search').value = '';
    document.getElementById('snippet-search').focus();
    loadSnippets();
};

window.closeSnippetPicker = function() {
    document.getElementById('snippet-modal').style.display = 'none';
};

async function loadSnippets(search = '') {
    const url = search ? '/chat/snippets?search=' + encodeURIComponent(search) : '/chat/snippets';
    const res = await api('GET', url);
    const list = document.getElementById('snippet-list');

    if (!res.success || !res.data.length) {
        list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--color-text-secondary,#64748b);font-size: var(--d-fs-sm);">' +
            (search ? 'Keine Treffer' : 'Noch keine Textbausteine. Klicke auf "+ Neu" um einen zu erstellen.') + '</div>';
        return;
    }

    snippetsCache = res.data;
    list.innerHTML = res.data.map(s => `
        <div style="padding:0.625rem 0.75rem;border:1px solid var(--color-border,#e2e8f0);border-radius:8px;margin-bottom:0.5rem;cursor:pointer;transition:border-color 0.15s;"
             onmouseenter="this.style.borderColor='var(--thoxan-700)'" onmouseleave="this.style.borderColor=''"
             onclick="insertSnippet(${s.id})">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="font-size: var(--d-fs-sm);">${esc(s.title)}</strong>
                <button onclick="event.stopPropagation();editSnippet(${s.id})" style="background:none;border:none;cursor:pointer;color:var(--color-text-secondary,#64748b);padding:2px;"
                    title="Bearbeiten"><span class="material-symbols-rounded" style="font-size:16px;">edit</span></button>
            </div>
            <div style="font-size: var(--d-fs-sm);color:var(--color-text-secondary,#64748b);margin-top:0.25rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${esc(s.content)}</div>
        </div>
    `).join('');
}

window.searchSnippets = function(q) {
    loadSnippets(q);
};

window.insertSnippet = function(id) {
    const snippet = snippetsCache?.find(s => s.id === id);
    if (!snippet || !snippetTargetTextarea) return;

    const ta = snippetTargetTextarea;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const before = ta.value.substring(0, start);
    const after = ta.value.substring(end);
    ta.value = before + snippet.content + after;
    ta.selectionStart = ta.selectionEnd = start + snippet.content.length;
    ta.focus();
    autoResize(ta);
    closeSnippetPicker();
};

window.openSnippetEditor = function(id) {
    if (id) {
        const s = snippetsCache?.find(x => x.id === id);
        if (!s) return;
        document.getElementById('snippet-edit-id').value = s.id;
        document.getElementById('snippet-edit-name').value = s.title;
        document.getElementById('snippet-edit-content').value = s.content;
        document.getElementById('snippet-editor-title').textContent = 'Textbaustein bearbeiten';
        document.getElementById('snippet-delete-btn').style.display = 'block';
    } else {
        document.getElementById('snippet-edit-id').value = '';
        document.getElementById('snippet-edit-name').value = '';
        document.getElementById('snippet-edit-content').value = '';
        document.getElementById('snippet-editor-title').textContent = 'Neuer Textbaustein';
        document.getElementById('snippet-delete-btn').style.display = 'none';
    }
    document.getElementById('snippet-editor').style.display = 'flex';
    document.getElementById('snippet-edit-name').focus();
};

window.editSnippet = function(id) {
    openSnippetEditor(id);
};

window.closeSnippetEditor = function() {
    document.getElementById('snippet-editor').style.display = 'none';
};

window.saveSnippet = async function() {
    const id = document.getElementById('snippet-edit-id').value;
    const title = document.getElementById('snippet-edit-name').value.trim();
    const content = document.getElementById('snippet-edit-content').value.trim();
    if (!title || !content) { document.getElementById('snippet-edit-name').focus(); return; }

    let res;
    if (id) {
        res = await api('PUT', '/chat/snippets/' + id, { title, content });
    } else {
        res = await api('POST', '/chat/snippets', { title, content });
    }

    if (res.success) {
        closeSnippetEditor();
        loadSnippets(document.getElementById('snippet-search')?.value || '');
        App.showNotification(id ? 'Textbaustein aktualisiert' : 'Textbaustein erstellt', 'success');
    } else {
        App.showNotification(res.message || 'Fehler', 'error');
    }
};

window.deleteSnippet = async function() {
    const id = document.getElementById('snippet-edit-id').value;
    if (!id || !confirm('Textbaustein loeschen?')) return;
    await api('DELETE', '/chat/snippets/' + id);
    closeSnippetEditor();
    loadSnippets(document.getElementById('snippet-search')?.value || '');
    App.showNotification('Textbaustein geloescht', 'success');
};

// Close modals on overlay click
document.getElementById('snippet-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSnippetPicker();
});
document.getElementById('snippet-editor')?.addEventListener('click', function(e) {
    if (e.target === this) closeSnippetEditor();
});

// ==========================================
// Drag & Drop File Upload
// ==========================================
(function() {
    const chatMain = document.getElementById('chat-main');
    if (!chatMain) return;

    // Overlay erstellen
    const dropOverlay = document.createElement('div');
    dropOverlay.id = 'drop-overlay';
    dropOverlay.style.cssText = 'display:none;position:absolute;inset:0;background:rgba(0, 76, 155,0.08);border:2px dashed var(--thoxan-700);border-radius:12px;z-index:50;pointer-events:none;align-items:center;justify-content:center;';
    dropOverlay.innerHTML = '<div style="background:#fff;padding:1rem 2rem;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);font-size: var(--d-fs-sm);color:var(--thoxan-700);font-weight:600;display:flex;align-items:center;gap:0.5rem;"><span class="material-symbols-rounded">upload_file</span>Dateien hier ablegen</div>';
    chatMain.style.position = 'relative';
    chatMain.appendChild(dropOverlay);

    let dragCounter = 0;

    chatMain.addEventListener('dragenter', (e) => {
        e.preventDefault();
        dragCounter++;
        if (e.dataTransfer.types.includes('Files')) {
            dropOverlay.style.display = 'flex';
        }
    });

    chatMain.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            dropOverlay.style.display = 'none';
        }
    });

    chatMain.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    chatMain.addEventListener('drop', (e) => {
        e.preventDefault();
        dragCounter = 0;
        dropOverlay.style.display = 'none';

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            uploadFiles(Array.from(files));
        }
    });
})();

// =================================================================
// PAPIERKORB — gelöschte Chats wiederherstellen / endgültig löschen
// =================================================================
window.openTrash = async function() {
    let overlay = document.getElementById('trash-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'trash-overlay';
        overlay.className = 'trash-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) closeTrash(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="trash-modal">
            <div class="trash-head">
                <div class="trash-head-icon"><span class="material-symbols-rounded">delete</span></div>
                <div class="trash-head-text">
                    <h3>Papierkorb</h3>
                    <small>${chatState.isAdmin ? 'Alle gelöschten Chats (Admin-Ansicht)' : 'Deine gelöschten Chats'}<br>1 Jahr Aufbewahrung, danach endgültige Löschung</small>
                </div>
                <button class="trash-close" onclick="closeTrash()" title="Schließen" aria-label="Schließen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="trash-body" id="trash-list">
                <div style="text-align:center;padding:2rem;color:#94a3b8;font-size: var(--d-fs-sm);"><span class="cw-spinner" style="vertical-align:middle;margin-right:6px;display:inline-block;width:14px;height:14px;border:2px solid rgba(0,76,155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;"></span>Lade Papierkorb…</div>
            </div>
        </div>
    `;
    overlay.classList.add('open');

    try {
        const url = '/chat/conversations?visibility=trash&limit=500';
        const r = await api('GET', url);
        const convs = (r && r.success !== false) ? (Array.isArray(r) ? r : (r.data || [])) : [];
        chatState.trashConversations = convs;
        renderTrashList(convs);
    } catch (e) {
        document.getElementById('trash-list').innerHTML = '<div style="color:var(--rose-600);padding:1rem;">' + esc(e.message || 'Fehler') + '</div>';
    }
};

function renderTrashList(convs) {
    const el = document.getElementById('trash-list');
    if (!convs.length) {
        el.innerHTML = '<div style="text-align:center;padding:2.5rem 1rem;color:#94a3b8;"><span class="material-symbols-rounded" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:8px;">recycling</span>Papierkorb ist leer</div>';
        return;
    }
    el.innerHTML = convs.map(c => {
        const deletedAt = c.deleted_at ? new Date(c.deleted_at.replace(' ', 'T')) : null;
        const expiresAt = deletedAt ? new Date(deletedAt.getTime() + 365 * 24 * 60 * 60 * 1000) : null;
        const remainingDays = expiresAt ? Math.max(0, Math.round((expiresAt - new Date()) / (24*60*60*1000))) : 0;
        return `
            <div class="trash-item">
                <div class="trash-item-main">
                    <div class="trash-item-title">${esc(c.title || 'Ohne Titel')}</div>
                    <div class="trash-item-meta">
                        ${c.creator_name ? esc(c.creator_name) + ' · ' : ''}
                        ${c.customer_name ? esc(c.customer_name) + ' · ' : ''}
                        Gelöscht: ${deletedAt ? deletedAt.toLocaleString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '?'}
                        ${c.deleted_by_name ? ' von ' + esc(c.deleted_by_name) : ''}
                        ${remainingDays > 0 ? ` · noch ${remainingDays} Tage` : ' · läuft heute ab'}
                    </div>
                </div>
                <div class="trash-item-actions">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="restoreConv(${c.id})" title="Wiederherstellen">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">restore</span>
                        Wiederherstellen
                    </button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" style="color:var(--rose-600);" onclick="forceDeleteConv(${c.id})" title="Endgültig löschen">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete_forever</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

window.restoreConv = async function(id) {
    try {
        await api('POST', '/chat/conversations/' + id + '/restore', {});
        chatState.trashConversations = chatState.trashConversations.filter(c => c.id !== id);
        renderTrashList(chatState.trashConversations);
        loadConversations();
        App.showNotification('Chat wiederhergestellt — Wissens-Eintrag wird nicht automatisch zurückgeholt', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.forceDeleteConv = async function(id) {
    if (!confirm('Diesen Chat ENDGÜLTIG löschen? Das kann nicht rückgängig gemacht werden.')) return;
    try {
        await api('DELETE', '/chat/conversations/' + id + '?force=1');
        chatState.trashConversations = chatState.trashConversations.filter(c => c.id !== id);
        renderTrashList(chatState.trashConversations);
        App.showNotification('Endgültig gelöscht', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.closeTrash = function() {
    const overlay = document.getElementById('trash-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};
</script>
