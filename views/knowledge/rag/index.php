<?php
$isAdmin = \Core\Auth::isAdmin();
?>

<style>
/* ============================================================================
   Wissen — Recherche-Layout (THX-Design)
   Aufbau: 2 Spalten (Sidebar | Main) + optionaler Detail-Drawer rechts.
   Klassen: .kb-* sind wissen-spezifisch und nutzen die globalen --thoxan/-slate-Vars.
   ============================================================================ */

.kb-page {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    overflow: visible;
    font-size: var(--d-fs-sm);
}
.kb-sidebar,
.kb-main {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}

/* ===== Sidebar (gleiche Breite wie Chat/PP) ===== */
.kb-sidebar {
    width: 360px;
    min-width: 360px;
    background: var(--slate-50);
    border-right: 1px solid var(--slate-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width 0.2s ease, min-width 0.2s ease;
}
.kb-sidebar.collapsed {
    width: 56px;
    min-width: 56px;
}
.kb-sidebar.collapsed .kb-sidebar-head,
.kb-sidebar.collapsed .kb-sidebar-search,
.kb-sidebar.collapsed .kb-sidebar-body {
    display: none !important;
}
.kb-sidebar-collapsed-bar {
    display: none;
    flex-direction: column;
    align-items: center;
    padding: 12px 4px;
    gap: 8px;
}
.kb-sidebar.collapsed .kb-sidebar-collapsed-bar { display: flex; }

.kb-sidebar-head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
}
.kb-sidebar-title { font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-800); flex: 1; }
.kb-sidebar-toggle {
    width: 32px; height: 32px; border: none; background: transparent;
    color: var(--slate-500); cursor: pointer; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
}
.kb-sidebar-toggle:hover { background: var(--slate-100); color: var(--slate-800); }

/* Hero-Suche */
.kb-sidebar-search {
    padding: 12px 14px;
    background: #fff;
    border-bottom: 1px solid var(--slate-200);
    display: flex; flex-direction: column; gap: 8px;
}
.kb-search-wrap { position: relative; }
.kb-search-input {
    width: 100%; padding: 9px 12px 9px 36px;
    border: 1px solid var(--slate-300); border-radius: 8px;
    font-size: var(--d-fs-sm); font-family: inherit;
    background: var(--slate-50); color: var(--slate-800);
    box-sizing: border-box;
}
.kb-search-input:focus { outline: none; border-color: var(--thoxan-600); background: #fff; box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
.kb-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: var(--slate-400); font-size: 18px;
}
.kb-search-mode {
    display: flex; gap: 0; background: var(--slate-100); border-radius: 8px; padding: 2px;
}
.kb-search-mode-btn {
    flex: 1; padding: 5px 8px; border: none; background: transparent;
    font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-600);
    cursor: pointer; border-radius: 6px; font-family: inherit;
}
.kb-search-mode-btn.is-active { background: #fff; color: var(--thoxan-700); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.kb-search-hint {
    font-size: var(--d-fs-xs); color: var(--slate-500);
    background: var(--thoxan-50); border-left: 3px solid var(--thoxan-300);
    padding: 6px 8px; border-radius: 4px; line-height: 1.4;
}

/* Sidebar Tab-Bar (Icons) */
.kb-sidebar-tabs {
    display: flex; gap: 4px;
    padding: 8px 12px;
    background: #fff;
    border-bottom: 1px solid var(--slate-200);
}
.kb-sidebar-tab {
    flex: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 2px; padding: 6px 4px;
    background: transparent; border: 1px solid transparent; border-radius: 8px;
    color: var(--slate-500); cursor: pointer; font-family: inherit;
    font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;
    transition: all 0.12s ease;
}
.kb-sidebar-tab:hover { background: var(--slate-100); color: var(--slate-800); }
.kb-sidebar-tab.is-active {
    background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600);
}
.kb-sidebar-tab .material-symbols-rounded { font-size: 20px; }
.kb-sidebar-tab-badge {
    position: absolute; top: 0; right: 0;
    background: var(--rose-500); color: #fff;
    border-radius: 999px; padding: 1px 5px;
    font-size: 9px; font-weight: 700;
}

/* Customer-Filter (Status + Art) — nur im Kunden-Tab sichtbar */
.kb-customer-filters {
    display: flex; flex-direction: column; gap: 8px;
    padding: 10px 14px;
    background: #fff;
    border-bottom: 1px solid var(--slate-200);
}
.kb-cf-row { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
.kb-cf-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    color: var(--slate-500); letter-spacing: 0.05em;
    margin-right: 4px;
}
.kb-cf-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px;
    background: var(--slate-100); color: var(--slate-700);
    border: 1px solid transparent; border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 600;
    cursor: pointer; font-family: inherit;
    transition: all 0.1s;
}
.kb-cf-pill:hover { background: var(--slate-200); }
.kb-cf-pill.is-active { background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600); }
.kb-cf-pill.is-active .kb-cf-count { background: rgba(255,255,255,0.25); color: #fff; }
.kb-cf-dot { width: 6px; height: 6px; border-radius: 999px; }
.kb-cf-count {
    background: rgba(255,255,255,0.6); color: var(--slate-600);
    padding: 0 5px; border-radius: 999px; font-size: 10px;
}

/* Sidebar Body (Liste) */
.kb-sidebar-body {
    flex: 1; overflow-y: auto; padding: 6px 0;
}
.kb-sidebar-body::-webkit-scrollbar { width: 6px; }
.kb-sidebar-body::-webkit-scrollbar-thumb { background: var(--slate-300); border-radius: 3px; }

.kb-facet-list {
    display: flex; flex-direction: column; gap: 1px;
    padding: 0 8px 4px 8px;
}
.kb-facet-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 8px; border-radius: 6px; cursor: pointer;
    color: var(--slate-700); font-size: var(--d-fs-sm);
    transition: background 0.1s;
}
.kb-facet-item:hover { background: #fff; }
.kb-facet-item.is-active { background: var(--thoxan-600); color: #fff; font-weight: 600; }
.kb-facet-item.is-active .kb-facet-count { background: rgba(255,255,255,0.25); color: #fff; }
.kb-facet-item-icon { color: var(--slate-400); font-size: 16px; flex-shrink: 0; }
.kb-facet-item.is-active .kb-facet-item-icon { color: rgba(255,255,255,0.85); }
.kb-facet-customer .kb-facet-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-facet-abbr {
    width: 36px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 6px;
    font-size: var(--d-fs-xs); font-weight: 700; letter-spacing: 0.02em;
    color: var(--slate-700);
    flex-shrink: 0;
}
.kb-facet-customer.is-active .kb-facet-abbr {
    background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.3); color: #fff;
}
.kb-facet-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-facet-count {
    background: var(--slate-200); color: var(--slate-700);
    padding: 1px 8px; border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 600;
    min-width: 28px; text-align: center;
}

/* ===== Main ===== */
.kb-main { display: flex; flex-direction: column; overflow: hidden; }
.kb-main-head {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
    flex-wrap: wrap;
}
.kb-main-tabs { display: flex; gap: 2px; }
.kb-tab {
    padding: 7px 14px; border: 1px solid transparent; border-radius: 8px;
    background: transparent; color: var(--slate-600);
    font-size: var(--d-fs-sm); font-weight: 600; font-family: inherit;
    cursor: pointer; display: flex; align-items: center; gap: 6px;
}
.kb-tab:hover { background: var(--slate-100); color: var(--slate-800); }
.kb-tab.is-active { background: var(--thoxan-600); color: #fff; }
.kb-tab .material-symbols-rounded { font-size: 18px; }

.kb-main-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.kb-active-filters {
    display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
    padding: 8px 18px;
    border-bottom: 1px solid var(--slate-200);
    background: var(--slate-50);
    min-height: 16px;
}
.kb-active-filters:empty { display: none; }
.kb-filter-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 3px 4px 3px 10px;
    background: #fff; border: 1px solid var(--slate-300);
    border-radius: 999px; font-size: var(--d-fs-xs); color: var(--slate-700);
}
.kb-filter-chip strong { font-weight: 600; color: var(--slate-900); }
.kb-filter-chip-x {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 999px;
    border: none; background: transparent; color: var(--slate-500);
    cursor: pointer;
}
.kb-filter-chip-x:hover { background: var(--slate-200); color: var(--slate-800); }
.kb-active-filters-clear {
    color: var(--thoxan-700); font-size: var(--d-fs-xs); font-weight: 600;
    background: none; border: none; cursor: pointer;
}

.kb-main-body { flex: 1; overflow-y: auto; }
.kb-main-body::-webkit-scrollbar { width: 8px; }
.kb-main-body::-webkit-scrollbar-thumb { background: var(--slate-300); border-radius: 4px; }

/* ===== Übersicht-Tab: Stats + Heatmap ===== */
.kb-overview { padding: 18px; }
.kb-stat-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px; margin-bottom: 20px;
}
.kb-stat-card {
    background: linear-gradient(135deg, #fff, var(--slate-50));
    border: 1px solid var(--slate-200); border-radius: 10px;
    padding: 12px 14px;
}
.kb-stat-label { font-size: var(--d-fs-xs); color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.05em; }
.kb-stat-value { font-size: var(--d-fs-xl); font-weight: 700; color: var(--slate-900); margin-top: 2px; }
.kb-stat-sub { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 1px; }
.kb-stat-card.is-warning .kb-stat-value { color: var(--rose-600); }
.kb-stat-card.is-warning .kb-stat-label { color: var(--rose-600); }

.kb-section-title {
    font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-800);
    margin: 24px 0 10px 0; display: flex; align-items: center; gap: 8px;
}
.kb-section-title .material-symbols-rounded { font-size: 18px; color: var(--thoxan-600); }

.kb-heatmap-wrap {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 10px;
    overflow: auto;
}
.kb-heatmap {
    width: 100%; border-collapse: separate; border-spacing: 0;
    font-size: var(--d-fs-sm);
}
.kb-heatmap th, .kb-heatmap td {
    padding: 7px 10px; border-bottom: 1px solid var(--slate-100);
    text-align: center; white-space: nowrap;
}
.kb-heatmap th {
    background: var(--slate-50); color: var(--slate-600); font-weight: 600;
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em;
    position: sticky; top: 0; z-index: 1;
}
.kb-heatmap th.kb-heatmap-name, .kb-heatmap td.kb-heatmap-name {
    text-align: left; position: sticky; left: 0; background: #fff; z-index: 2;
    font-weight: 600; color: var(--slate-800); min-width: 220px;
}
.kb-heatmap th.kb-heatmap-name { background: var(--slate-50); }
.kb-sort-h { cursor: pointer; user-select: none; transition: background 0.1s; }
.kb-sort-h:hover { background: var(--thoxan-50) !important; color: var(--thoxan-700) !important; }
.kb-heatmap tr:hover td { background: var(--thoxan-50); }
.kb-heatmap tr:hover td.kb-heatmap-name { background: var(--thoxan-50); }
.kb-heatmap-cell {
    cursor: pointer; border-radius: 6px;
    font-variant-numeric: tabular-nums;
}
.kb-heatmap-cell:hover { outline: 2px solid var(--thoxan-600); outline-offset: -2px; }
.kb-heatmap-cell.is-empty { color: var(--rose-500); font-weight: 600; }
.kb-heatmap-cell.is-empty:hover { background: var(--rose-50); }
.kb-heatmap-total {
    background: var(--slate-100); font-weight: 700; color: var(--slate-800);
}
.kb-heatmap-row-total { font-weight: 700; color: var(--thoxan-700); }
.kb-name-link { color: var(--slate-800); text-decoration: none; }
.kb-name-link:hover { color: var(--thoxan-700); }
.kb-customer-meta { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 400; margin-top: 2px; }

/* Heatmap-Farben: je nach Count */
.heat-0 { background: var(--rose-50); color: var(--rose-400); }
.heat-1 { background: #ecfdf5; color: #059669; }
.heat-2 { background: #d1fae5; color: #047857; }
.heat-3 { background: #a7f3d0; color: #065f46; }
.heat-4 { background: #6ee7b7; color: #064e3b; }
.heat-5 { background: #34d399; color: #fff; }

/* ===== Liste-Tab ===== */
.kb-list-toolbar {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    padding: 10px 18px;
    border-bottom: 1px solid var(--slate-200); background: #fff;
}
.kb-list-toolbar select,
.kb-list-toolbar input {
    padding: 5px 8px; border: 1px solid var(--slate-300); border-radius: 6px;
    font-size: var(--d-fs-sm); background: #fff; color: var(--slate-800);
    font-family: inherit;
}
.kb-list-toolbar input[type=date] { padding: 4px 8px; }
.kb-toolbar-group {
    display: inline-flex; gap: 4px; align-items: center;
    padding: 0 6px; border-left: 1px solid var(--slate-200);
}
.kb-toolbar-group:first-child { border-left: none; padding-left: 0; }
.kb-toolbar-label { font-size: var(--d-fs-xs); color: var(--slate-500); }

.kb-list-info {
    padding: 8px 18px; font-size: var(--d-fs-xs); color: var(--slate-500);
    border-bottom: 1px solid var(--slate-100); background: #fff;
}

.kb-list-rows { padding: 0; }
.kb-list-head {
    display: grid;
    grid-template-columns: 6px 28px 1fr 140px 70px 90px 90px;
    align-items: center; gap: 10px;
    padding: 8px 18px 8px 0;
    position: sticky; top: 0; z-index: 5;
    background: #fff; border-bottom: 1px solid var(--slate-200);
    font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; color: var(--slate-500);
}
.kb-list-head .kb-h-sort {
    cursor: pointer; user-select: none; display: flex; align-items: center; gap: 4px;
    padding: 4px 6px; border-radius: 4px; transition: background 0.1s;
}
.kb-list-head .kb-h-sort:hover { background: var(--thoxan-50); color: var(--thoxan-700); }
.kb-list-head .kb-h-sort.is-active { color: var(--thoxan-700); }
.kb-list-head .kb-h-sort .material-symbols-rounded { font-size: 14px; }
.kb-list-head .kb-h-right { justify-content: flex-end; text-align: right; }
.kb-row {
    display: grid;
    grid-template-columns: 6px 28px 1fr 140px 70px 90px 90px;
    align-items: center;
    gap: 10px;
    padding: 9px 18px 9px 0;
    border-bottom: 1px solid var(--slate-100);
    cursor: pointer; transition: background 0.1s;
    position: relative;
}
.kb-row:hover { background: var(--thoxan-50); }
.kb-row.is-selected { background: rgba(0,76,155,0.08); }
.kb-row-stripe {
    align-self: stretch;
    background: var(--slate-300);
}
.kb-row-icon {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    background: var(--slate-100); border-radius: 6px; color: var(--slate-600);
}
.kb-row-icon .material-symbols-rounded { font-size: 16px; }
.kb-row-main { min-width: 0; }
.kb-row-title { font-size: var(--d-fs-sm); font-weight: 600; color: var(--slate-900);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-row-sub {
    font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 1px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    display: flex; gap: 8px; align-items: center;
}
.kb-row-tag { background: var(--slate-100); color: var(--slate-700); padding: 0 6px; border-radius: 4px; font-weight: 500; }
.kb-row-cell { font-size: var(--d-fs-xs); color: var(--slate-600); text-align: right; font-variant-numeric: tabular-nums; }
.kb-row-cell.is-left { text-align: left; }
.kb-row-customer { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Source-Streifen-Farben */
.kb-row[data-source="asana"]            .kb-row-stripe { background: #f97316; }
.kb-row[data-source="web"]              .kb-row-stripe { background: #0891b2; }
.kb-row[data-source="upload"]           .kb-row-stripe { background: #6366f1; }
.kb-row[data-source="text"]             .kb-row-stripe { background: #64748b; }
.kb-row[data-source="chat"]             .kb-row-stripe { background: #06b6d4; }
.kb-row[data-source="kundensteckbrief"] .kb-row-stripe { background: var(--thoxan-700); }
.kb-row[data-source="transcript"]       .kb-row-stripe { background: #ec4899; }
.kb-row[data-source="projektplan"]      .kb-row-stripe { background: #10b981; }

/* Asana-Dämpfung */
.kb-asana-collapse {
    padding: 14px 18px; background: linear-gradient(135deg, #fff7ed, #ffedd5);
    border-bottom: 1px solid #fed7aa; cursor: pointer;
    display: flex; gap: 10px; align-items: center;
}
.kb-asana-collapse:hover { background: linear-gradient(135deg, #ffedd5, #fed7aa); }
.kb-asana-collapse .material-symbols-rounded { color: #c2410c; }
.kb-asana-collapse strong { color: #9a3412; font-size: var(--d-fs-sm); }
.kb-asana-collapse small { color: #c2410c; font-size: var(--d-fs-xs); }

/* Empty/Load State */
.kb-empty {
    padding: 60px 30px; text-align: center; color: var(--slate-500);
}
.kb-empty .material-symbols-rounded { font-size: 48px; opacity: 0.4; }
.kb-loading {
    padding: 40px 30px; text-align: center; color: var(--slate-500);
}
.kb-loading-spinner {
    width: 32px; height: 32px; margin: 0 auto 12px;
    border: 3px solid var(--slate-200); border-top-color: var(--thoxan-600);
    border-radius: 50%; animation: kb-spin 0.8s linear infinite;
}
@keyframes kb-spin { to { transform: rotate(360deg); } }

/* Load-More-Button */
.kb-load-more {
    margin: 14px auto 24px; display: block;
    padding: 8px 18px; background: #fff; border: 1px solid var(--slate-300);
    border-radius: 8px; color: var(--slate-700); font-size: var(--d-fs-sm);
    font-weight: 600; cursor: pointer;
}
.kb-load-more:hover { background: var(--thoxan-50); border-color: var(--thoxan-600); color: var(--thoxan-700); }

/* ===== Detail-Drawer rechts ===== */
.kb-drawer {
    position: fixed; top: calc(var(--topbar-h) + var(--d-gutter));
    right: var(--d-gutter); width: 540px; max-width: 95vw;
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
    transform: translateX(580px); transition: transform 0.25s ease;
    z-index: 80;
    display: flex; flex-direction: column; overflow: hidden;
}
.kb-drawer.open { transform: translateX(0); }
.kb-drawer-head {
    padding: 14px 18px; border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: flex-start; gap: 10px;
}
.kb-drawer-head h2 {
    flex: 1; margin: 0; font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-900);
    line-height: 1.35;
}
.kb-drawer-close {
    width: 32px; height: 32px; border: none; background: transparent;
    color: var(--slate-500); cursor: pointer; border-radius: 6px;
    flex-shrink: 0;
}
.kb-drawer-close:hover { background: var(--slate-100); color: var(--slate-800); }
.kb-drawer-body { flex: 1; overflow-y: auto; padding: 16px 18px; }
.kb-drawer-meta {
    display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: var(--d-fs-xs);
    color: var(--slate-500); margin-bottom: 14px;
}
.kb-drawer-meta strong { color: var(--slate-700); }
.kb-drawer-section { margin-bottom: 20px; }
.kb-drawer-section h3 {
    margin: 0 0 8px 0; font-size: var(--d-fs-xs); font-weight: 700;
    color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.05em;
}
.kb-drawer-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.kb-drawer-chunk {
    padding: 8px 10px; background: var(--slate-50); border-left: 3px solid var(--thoxan-300);
    border-radius: 6px; margin-bottom: 6px;
    font-size: var(--d-fs-xs); color: var(--slate-700); line-height: 1.5;
    white-space: pre-wrap;
    max-height: 120px; overflow: hidden; position: relative;
}
.kb-drawer-actions {
    display: flex; gap: 6px; padding: 12px 18px;
    border-top: 1px solid var(--slate-200); background: var(--slate-50);
}

/* ===== Semantische Such-Trefferliste ===== */
.kb-search-results {
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--thoxan-50), #fff);
    border-bottom: 1px solid var(--thoxan-200);
}
.kb-search-results-head {
    font-size: var(--d-fs-xs); color: var(--thoxan-700); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 10px;
    display: flex; gap: 8px; align-items: center;
}
.kb-search-hit {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 8px;
    padding: 10px 12px; margin-bottom: 6px; cursor: pointer;
    transition: border-color 0.1s;
}
.kb-search-hit:hover { border-color: var(--thoxan-600); }
.kb-search-hit-title { font-weight: 600; color: var(--slate-900); font-size: var(--d-fs-sm); }
.kb-search-hit-snippet {
    font-size: var(--d-fs-xs); color: var(--slate-600); margin-top: 4px; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.kb-search-hit-meta {
    font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 6px;
    display: flex; gap: 10px;
}
.kb-search-hit-score {
    background: var(--thoxan-100); color: var(--thoxan-800);
    padding: 1px 6px; border-radius: 4px; font-weight: 600;
}

/* ===== Bereich-befuellen-Modal Buttons ===== */
.kb-fill-opt {
    display: flex; flex-direction: column; gap: 4px; align-items: flex-start;
    padding: 14px 16px; background: #fff; border: 1px solid var(--slate-200);
    border-radius: 10px; cursor: pointer; transition: all 0.12s;
    font-family: inherit; text-align: left;
}
.kb-fill-opt:hover { border-color: var(--thoxan-600); background: var(--thoxan-50); }
.kb-fill-opt .material-symbols-rounded { font-size: 22px; color: var(--thoxan-700); }
.kb-fill-opt strong { font-size: var(--d-fs-sm); color: var(--slate-900); }
.kb-fill-opt small { color: var(--slate-500); font-size: var(--d-fs-xs); }
.kb-fill-opt-primary { background: var(--thoxan-700); border-color: var(--thoxan-700); }
.kb-fill-opt-primary .material-symbols-rounded { color: #fff; }
.kb-fill-opt-primary strong { color: #fff; }
.kb-fill-opt-primary small { color: rgba(255,255,255,0.85); }
.kb-fill-opt-primary:hover { background: var(--thoxan-800); border-color: var(--thoxan-800); }

/* ===== Neuer-Eintrag-Modal ===== */
.kb-new-entry-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5);
    display: none; align-items: center; justify-content: center;
    z-index: 1050; padding: 24px;
}
.kb-new-entry-overlay.open { display: flex; }
.kb-new-entry-modal {
    background: #fff; border-radius: 14px;
    width: 920px; max-width: 95vw;
    height: 720px; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(15,23,42,0.3);
}
.kb-new-entry-head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    padding: 14px 18px; border-bottom: 1px solid var(--slate-200); background: var(--slate-50);
    flex-shrink: 0;
}
.kb-new-entry-frame {
    flex: 1; width: 100%; border: 0; background: #fff;
}
</style>

<div class="kb-page">
    <!-- ============================= Sidebar ============================= -->
    <aside class="kb-sidebar" id="kb-sidebar">
        <div class="kb-sidebar-collapsed-bar">
            <button class="kb-sidebar-toggle" onclick="kbToggleSidebar()" title="Aufklappen">
                <span class="material-symbols-rounded">menu</span>
            </button>
        </div>

        <div class="kb-sidebar-head">
            <div class="kb-sidebar-title">Wissensbasis</div>
            <button class="kb-sidebar-toggle" onclick="kbToggleSidebar()" title="Einklappen">
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
        </div>

        <div class="kb-sidebar-search">
            <div class="kb-search-wrap">
                <span class="material-symbols-rounded kb-search-icon">search</span>
                <input type="search" class="kb-search-input" id="kb-search"
                       placeholder="Suchen, Frage stellen..." oninput="kbDebouncedSearch()" onkeydown="if(event.key==='Enter')kbRunSearchNow()">
            </div>
            <div class="kb-search-mode">
                <button class="kb-search-mode-btn is-active" data-mode="text" onclick="kbSetSearchMode('text')"
                        title="Wortlaut-Suche: durchsucht Titel und Beschreibung nach exakt deinem Suchbegriff (LIKE %suche%). Schnell, präzise, aber findet keine Synonyme.">
                    Wortlaut
                </button>
                <button class="kb-search-mode-btn" data-mode="rag" onclick="kbSetSearchMode('rag')"
                        title="Semantische Suche: KI vergleicht die Bedeutung deiner Frage mit dem Inhalt aller Dokumente. Findet auch ähnliche Begriffe und sinnverwandte Stellen — gut, um Fragen zu stellen statt nur Worte zu suchen.">
                    Semantisch
                </button>
            </div>
            <div class="kb-search-hint" id="kb-search-hint" style="display:none;"></div>
        </div>

        <div class="kb-sidebar-tabs">
            <button class="kb-sidebar-tab is-active" data-stab="customers" onclick="kbSetSidebarTab('customers')" title="Kunden">
                <span class="material-symbols-rounded">apartment</span>
                Kunden
            </button>
            <button class="kb-sidebar-tab" data-stab="sources" onclick="kbSetSidebarTab('sources')" title="Quellen">
                <span class="material-symbols-rounded">inventory_2</span>
                Quellen
            </button>
            <button class="kb-sidebar-tab" data-stab="categories" onclick="kbSetSidebarTab('categories')" title="Kategorien">
                <span class="material-symbols-rounded">category</span>
                Kategorien
            </button>
            <button class="kb-sidebar-tab" data-stab="tags" onclick="kbSetSidebarTab('tags')" title="Tags">
                <span class="material-symbols-rounded">sell</span>
                Tags
            </button>
        </div>

        <div class="kb-customer-filters" id="kb-customer-filters" style="display:none;">
            <!-- per JS gerendert -->
        </div>

        <div class="kb-sidebar-body" id="kb-sidebar-body">
            <div class="kb-loading"><div class="kb-loading-spinner"></div></div>
        </div>
    </aside>

    <!-- ============================= Main ============================= -->
    <section class="kb-main">
        <div class="kb-main-head">
            <div class="kb-main-tabs">
                <button class="kb-tab is-active" data-tab="overview" onclick="kbSetTab('overview')">
                    <span class="material-symbols-rounded">dashboard</span> Übersicht
                </button>
                <button class="kb-tab" data-tab="list" onclick="kbSetTab('list')">
                    <span class="material-symbols-rounded">list</span> Liste
                </button>
            </div>
            <div class="kb-main-actions">
                <?php if (\Core\Auth::can(CAP_TRANSCRIPTION)): ?>
                <a href="/admin/transkription" class="thx-btn thx-btn-ghost thx-btn-small" title="Zu Transkripten wechseln">
                    <span class="material-symbols-rounded" style="font-size:16px;">mic</span>
                    Transkripte
                </a>
                <?php endif; ?>
                <a href="/wissen-graph" class="thx-btn thx-btn-ghost thx-btn-small">
                    <span class="material-symbols-rounded" style="font-size:16px;">hub</span>
                    Graph
                </a>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="kbOpenNewEntryModal()" type="button">
                    <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                    Neuer Eintrag
                </button>
            </div>
        </div>

        <div class="kb-active-filters" id="kb-active-filters"></div>

        <div class="kb-main-body" id="kb-main-body">
            <!-- wird per JS gerendert -->
        </div>
    </section>
</div>

<!-- Detail-Drawer -->
<div class="kb-drawer" id="kb-drawer">
    <!-- per JS -->
</div>

<script>
(function() {
    const csrfToken = '<?= \Core\Session::getCsrfToken() ?>';
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    // ===== Globaler State =====
    const state = {
        tab: 'overview',
        sidebarTab: 'customers', // 'customers' | 'sources' | 'categories' | 'tags'
        filters: {
            customer_id: '',
            source_type: '',
            ingest_mode: '',
            category: '',
            tags: [],
            customer_status: '',
            customer_tags: [],
            date_from: '',
            date_to: '',
            size_bucket: '',
            status: '',
            search: '',
        },
        searchMode: 'text',  // 'text' | 'rag'
        ragHits: null,
        list: { items: [], total: 0, offset: 0, limit: 100, asanaCollapsed: true, sort_by: 'updated_at', sort_dir: 'desc' },
        facetCache: null, // letzter Facets-Response, fuer Tab-Switch ohne Reload
    };

    // ===== Helpers =====
    const SOURCE_LABELS = {
        upload: 'Datei-Upload', web: 'Web', text: 'Text',
        chat: 'Chat', asana: 'Asana', kundensteckbrief: 'Steckbrief',
        transcript: 'Transkript', projektplan: 'Projektplan',
    };
    const SOURCE_ICONS = {
        upload: 'upload_file', web: 'language', text: 'edit_note',
        chat: 'forum', asana: 'task_alt', kundensteckbrief: 'contact_page',
        transcript: 'mic', projektplan: 'event_note',
    };
    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function fmtDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }
    function fmtRelDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'gerade eben';
        if (diff < 3600) return Math.floor(diff/60) + ' Min.';
        if (diff < 86400) return Math.floor(diff/3600) + ' Std.';
        if (diff < 86400 * 7) return Math.floor(diff/86400) + ' Tg.';
        if (diff < 86400 * 30) return Math.floor(diff/86400/7) + ' Wo.';
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }
    async function apiGet(url) {
        const r = await fetch('/api/v1' + url, { headers: { 'X-CSRF-Token': csrfToken } });
        return r.json();
    }
    async function apiPost(url, body) {
        const r = await fetch('/api/v1' + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(body || {}),
        });
        return r.json();
    }

    // ===== Sidebar Toggle =====
    window.kbToggleSidebar = function() {
        const sb = document.getElementById('kb-sidebar');
        sb.classList.toggle('collapsed');
        localStorage.setItem('kb-sidebar-collapsed', sb.classList.contains('collapsed') ? '1' : '0');
    };
    if (localStorage.getItem('kb-sidebar-collapsed') === '1') {
        document.getElementById('kb-sidebar').classList.add('collapsed');
    }

    // ===== Filter-Param-Builder =====
    function buildFilterParams() {
        const p = new URLSearchParams();
        const f = state.filters;
        if (f.customer_id !== '') p.set('customer_id', f.customer_id);
        if (f.source_type) p.set('source_type', f.source_type);
        if (f.ingest_mode) p.set('ingest_mode', f.ingest_mode);
        if (f.category) p.set('category', f.category);
        if (f.search) p.set('search', f.search);
        if (f.date_from) p.set('date_from', f.date_from);
        if (f.date_to) p.set('date_to', f.date_to);
        if (f.size_bucket) p.set('size_bucket', f.size_bucket);
        if (f.status) p.set('status', f.status);
        if (f.tags && f.tags.length) p.set('tags', f.tags.join(','));
        if (f.customer_status) p.set('customer_status', f.customer_status);
        if (f.customer_tags && f.customer_tags.length) p.set('customer_tags', f.customer_tags.join(','));
        return p;
    }

    function setFilter(key, value) {
        if (key === 'tags' || key === 'customer_tags') {
            const arr = state.filters[key] || [];
            const idx = arr.indexOf(value);
            if (idx >= 0) arr.splice(idx, 1); else arr.push(value);
            state.filters[key] = arr;
        } else {
            // Toggle wenn gleicher Wert
            if (state.filters[key] === value) state.filters[key] = '';
            else state.filters[key] = value;
        }
        // Doc-spezifische Filter (Quelle, Kategorie, Doc-Tags, Kunde, Status, Größe)
        // → automatisch in die Liste-Sicht wechseln, denn dort sieht man die Treffer.
        // Customer-Status + Customer-Art schränken den Kunden-Pool ein → das ist genau
        // das, was die Heatmap braucht, also in der Übersicht bleiben.
        const docFilters = ['customer_id', 'source_type', 'ingest_mode', 'category', 'tags', 'status', 'size_bucket'];
        if (docFilters.includes(key) && state.tab === 'overview') {
            const isNowActive = key === 'tags'
                ? (state.filters.tags?.length || 0) > 0
                : state.filters[key] !== '';
            if (isNowActive) {
                renderActiveFilters();
                renderSidebar();
                kbSetTab('list');
                return;
            }
        }
        renderActiveFilters();
        renderSidebar();
        if (state.tab === 'list') loadList(true);
        if (state.tab === 'overview') loadOverview();
    }
    window.kbSetFilter = setFilter;

    function clearFilters() {
        state.filters = { customer_id: '', source_type: '', category: '', tags: [], customer_status: '', customer_tags: [], date_from: '', date_to: '', size_bucket: '', status: '', search: '' };
        document.getElementById('kb-search').value = '';
        renderActiveFilters();
        renderSidebar();
        if (state.tab === 'list') loadList(true);
        if (state.tab === 'overview') loadOverview();
    }
    window.kbClearFilters = clearFilters;

    function renderActiveFilters() {
        const wrap = document.getElementById('kb-active-filters');
        const chips = [];
        const f = state.filters;
        if (f.customer_id) {
            const name = f.customer_id === 'null' ? 'Ohne Kunde' : (window.kbCustomerMap?.[f.customer_id] || 'Kunde #' + f.customer_id);
            chips.push(filterChipHtml('Kunde', name, () => setFilter('customer_id', f.customer_id)));
        }
        if (f.customer_status) chips.push(filterChipHtml('Kunden-Status', f.customer_status === 'active' ? 'Aktiv' : 'Inaktiv', () => setFilter('customer_status', f.customer_status)));
        for (const ct of (f.customer_tags || [])) chips.push(filterChipHtml('Art', ct, () => setFilter('customer_tags', ct)));
        if (f.source_type) chips.push(filterChipHtml('Quelle', SOURCE_LABELS[f.source_type] || f.source_type, () => setFilter('source_type', f.source_type)));
        if (f.ingest_mode) chips.push(filterChipHtml('Modus', f.ingest_mode === 'auto' ? 'automatisch' : 'manuell', () => setFilter('ingest_mode', f.ingest_mode)));
        if (f.category) chips.push(filterChipHtml('Kategorie', f.category, () => setFilter('category', f.category)));
        if (f.status === 'inactive') chips.push(filterChipHtml('Status', 'archiviert', () => setFilter('status', 'inactive')));
        if (f.size_bucket) chips.push(filterChipHtml('Größe', { kurz: 'kurz', mittel: 'mittel', lang: 'lang' }[f.size_bucket], () => setFilter('size_bucket', f.size_bucket)));
        if (f.date_from || f.date_to) chips.push(filterChipHtml('Datum', (f.date_from || '...') + ' – ' + (f.date_to || 'heute'), () => { state.filters.date_from = ''; state.filters.date_to = ''; renderActiveFilters(); if (state.tab === 'list') loadList(true); }));
        for (const t of (f.tags || [])) chips.push(filterChipHtml('Tag', '#' + t, () => setFilter('tags', t)));
        if (f.search) chips.push(filterChipHtml('Suche', f.search, () => { state.filters.search = ''; document.getElementById('kb-search').value=''; renderActiveFilters(); if (state.tab === 'list') loadList(true); }));

        wrap.innerHTML = chips.join('') + (chips.length > 0 ? '<button class="kb-active-filters-clear" onclick="kbClearFilters()">Alle Filter zurücksetzen</button>' : '');
    }
    function filterChipHtml(label, value, onRemove) {
        const id = 'fc' + Math.random().toString(36).slice(2, 8);
        window['__kbChip_' + id] = onRemove;
        return `<span class="kb-filter-chip"><strong>${esc(label)}:</strong> ${esc(value)}<button class="kb-filter-chip-x" onclick="window.__kbChip_${id}()" title="Filter entfernen"><span class="material-symbols-rounded" style="font-size:14px;">close</span></button></span>`;
    }

    // ===== Sidebar: Facets ===== //
    async function renderSidebar() {
        const body = document.getElementById('kb-sidebar-body');
        body.innerHTML = '<div class="kb-loading"><div class="kb-loading-spinner"></div></div>';
        const params = buildFilterParams();
        const r = await apiGet('/knowledge/facets?' + params.toString());
        if (!r.success) { body.innerHTML = '<div class="kb-empty">Fehler</div>'; return; }
        state.facetCache = r.data;
        const f = r.data;
        // Customer-Map für Active-Filter-Chips merken
        window.kbCustomerMap = {};
        f.customers.forEach(c => { if (c.facet_value !== null) window.kbCustomerMap[c.facet_value] = c.facet_label || ('Kunde #' + c.facet_value); });

        renderSidebarTab();
    }

    function renderSidebarTab() {
        // Tab-Buttons mit Filter-Badge (zeigt aktive Filter pro Tab)
        const f = state.filters;
        const badgeCounts = {
            customers: (f.customer_id ? 1 : 0) + (f.customer_status ? 1 : 0) + (f.customer_tags?.length || 0),
            sources: f.source_type ? 1 : 0,
            categories: f.category ? 1 : 0,
            tags: f.tags?.length || 0,
        };
        document.querySelectorAll('.kb-sidebar-tab').forEach(t => {
            const k = t.dataset.stab;
            t.classList.toggle('is-active', k === state.sidebarTab);
            t.style.position = 'relative';
            const oldBadge = t.querySelector('.kb-sidebar-tab-badge');
            if (oldBadge) oldBadge.remove();
            if (badgeCounts[k] > 0) {
                const b = document.createElement('span');
                b.className = 'kb-sidebar-tab-badge';
                b.textContent = badgeCounts[k];
                t.appendChild(b);
            }
        });

        // Customer-Filter-Bereich nur im Kunden-Tab
        const cf = document.getElementById('kb-customer-filters');
        if (state.sidebarTab === 'customers' && isAdmin) {
            cf.style.display = 'flex';
            cf.innerHTML = renderCustomerFiltersHtml();
        } else {
            cf.style.display = 'none';
            cf.innerHTML = '';
        }

        // Body: die Liste des aktiven Tabs
        const body = document.getElementById('kb-sidebar-body');
        const fc = state.facetCache;
        if (!fc) { body.innerHTML = '<div class="kb-loading"><div class="kb-loading-spinner"></div></div>'; return; }
        let items = [];
        let key = '';
        let activeValue = null;
        let activeArray = null;
        let getIcon = () => 'circle';
        let labelFn = (v) => v;
        if (state.sidebarTab === 'customers') {
            items = fc.customers || [];
            key = 'customer_id'; activeValue = state.filters.customer_id;
            getIcon = () => 'apartment';
        } else if (state.sidebarTab === 'sources') {
            items = (fc.sources || []).map(s => ({ ...s, facet_label: SOURCE_LABELS[s.facet_value] || s.facet_value }));
            key = 'source_type'; activeValue = state.filters.source_type;
            getIcon = (v) => SOURCE_ICONS[v] || 'description';

            // Sub-Filter "Modus" (auto/manuell) unter der Quellen-Liste
            const modes = fc.ingestModes || [];
            if (modes.length > 0) {
                const fs = state.filters;
                const modeHtml = modes.map(m => {
                    const isActive = fs.ingest_mode === m.facet_value;
                    const label = m.facet_value === 'auto' ? 'automatisch' : 'manuell';
                    const icon = m.facet_value === 'auto' ? 'autorenew' : 'edit';
                    return `<button class="kb-cf-pill ${isActive ? 'is-active' : ''}" onclick="kbSetFilter('ingest_mode', '${m.facet_value}')">
                        <span class="material-symbols-rounded" style="font-size:14px;">${icon}</span>
                        ${esc(label)}<span class="kb-cf-count">${m.n}</span>
                    </button>`;
                }).join('');
                document.getElementById('kb-customer-filters').style.display = 'flex';
                document.getElementById('kb-customer-filters').innerHTML = `<div class="kb-cf-row"><span class="kb-cf-label">Modus</span>${modeHtml}</div>`;
            }
        } else if (state.sidebarTab === 'categories') {
            items = fc.categories || [];
            key = 'category'; activeValue = state.filters.category;
            getIcon = () => 'category';
        } else if (state.sidebarTab === 'tags') {
            items = fc.tags || [];
            key = 'tags'; activeArray = state.filters.tags;
            getIcon = () => 'sell';
        }
        body.innerHTML = facetListHtml(items, key, activeValue, activeArray, getIcon);
    }

    window.kbSetSidebarTab = function(tab) {
        state.sidebarTab = tab;
        renderSidebarTab();
    };

    function facetListHtml(items, key, activeValue, activeArray, getIcon) {
        if (!items || items.length === 0) {
            return `<div class="kb-empty" style="padding:30px 20px;">
                <span class="material-symbols-rounded">filter_alt_off</span>
                <p style="font-size:var(--d-fs-sm);margin-top:8px;">Keine Einträge</p>
            </div>`;
        }
        const rows = items.map(it => {
            const val = it.facet_value;
            const isActive = activeArray
                ? activeArray.includes(String(val))
                : (val !== null && String(val) === String(activeValue));
            const safeVal = (val === null ? 'null' : String(val)).replace(/'/g, "\\'");
            // Kunden: Kuerzel-Avatar (Chat-Style) statt Material-Icon
            if (state.sidebarTab === 'customers' && val !== null && val !== 'null') {
                const label = it.facet_label || ('Kunde #' + val);
                const abbr = (it.facet_abbr || makeInitials(label)).substring(0, 3).toUpperCase();
                return `<div class="kb-facet-item kb-facet-customer ${isActive ? 'is-active' : ''}" onclick="kbSetFilter('${key}', '${safeVal}')">
                    <span class="kb-facet-abbr">${esc(abbr)}</span>
                    <span class="kb-facet-label">${esc(label)}</span>
                    <span class="kb-facet-count">${it.n}</span>
                </div>`;
            }
            return `<div class="kb-facet-item ${isActive ? 'is-active' : ''}" onclick="kbSetFilter('${key}', '${safeVal}')">
                <span class="material-symbols-rounded kb-facet-item-icon">${getIcon(val)}</span>
                <span class="kb-facet-label">${esc(it.facet_label || it.facet_value || '(ohne)')}</span>
                <span class="kb-facet-count">${it.n}</span>
            </div>`;
        }).join('');
        return `<div class="kb-facet-list">${rows}</div>`;
    }
    function makeInitials(name) {
        const parts = (name || '').trim().split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[parts.length-1][0]).toUpperCase();
        return (parts[0] || '?').substring(0, 2).toUpperCase();
    }

    function renderCustomerFiltersHtml() {
        const fc = state.facetCache;
        if (!fc) return '';
        const fs = state.filters;
        const stat = fc.customerStatus || [];
        const tags = fc.customerTags || [];
        // Status: Aktiv / Inaktiv mit Counts (alphabetisch sortiert)
        const statHtml = stat.map(s => {
            const isActive = fs.customer_status === s.facet_value;
            const dot = s.facet_value === 'active' ? '#10b981' : '#dc2626';
            return `<button class="kb-cf-pill ${isActive ? 'is-active' : ''}" onclick="kbSetFilter('customer_status', '${s.facet_value}')">
                <span class="kb-cf-dot" style="background:${dot};"></span>
                ${esc(s.facet_label)}<span class="kb-cf-count">${s.n}</span>
            </button>`;
        }).join('');
        const tagsHtml = tags.map(t => {
            const isActive = (fs.customer_tags || []).includes(t.facet_value);
            const safeVal = String(t.facet_value).replace(/'/g, "\\'");
            return `<button class="kb-cf-pill ${isActive ? 'is-active' : ''}" onclick="kbSetFilter('customer_tags', '${safeVal}')">
                ${esc(t.facet_label)}<span class="kb-cf-count">${t.n}</span>
            </button>`;
        }).join('');
        return (statHtml ? `<div class="kb-cf-row"><span class="kb-cf-label">Status</span>${statHtml}</div>` : '')
             + (tagsHtml ? `<div class="kb-cf-row"><span class="kb-cf-label">Art</span>${tagsHtml}</div>` : '');
    }

    // ===== Suche (Wortlaut vs RAG) =====
    let kbSearchTimer = null;
    window.kbDebouncedSearch = function() {
        clearTimeout(kbSearchTimer);
        const q = document.getElementById('kb-search').value;
        // Live-Hint: erkennt Fragen und schlaegt Semantik vor
        updateSearchHintForQuery(q);
        kbSearchTimer = setTimeout(() => kbRunSearchNow(q), 320);
    };
    function looksLikeQuestion(q) {
        const s = (q || '').trim().toLowerCase();
        if (!s) return false;
        if (s.endsWith('?')) return true;
        return /^(wer|was|wie|wo|wann|warum|welche|welcher|welches|wieso|weshalb)\b/.test(s);
    }
    function updateSearchHintForQuery(q) {
        const hint = document.getElementById('kb-search-hint');
        if (!hint) return;
        if (state.searchMode === 'rag') return; // RAG-Hint laeuft separat
        if (looksLikeQuestion(q)) {
            hint.style.display = 'block';
            hint.innerHTML = `Das sieht nach einer <strong>Frage</strong> aus — <a href="javascript:kbSwitchToSemantic()" style="color:var(--thoxan-700);font-weight:600;">semantische Suche</a> findet hier mehr.`;
        } else {
            hint.style.display = 'none';
            hint.innerHTML = '';
        }
    }
    window.kbSwitchToSemantic = function() {
        const q = document.getElementById('kb-search').value;
        kbSetSearchMode('rag');
        // searchMode setzt sich, dann RAG-Suche mit der bestehenden Eingabe
        kbRunSearchNow(q);
    };
    window.kbRunSearchNow = function(q) {
        const val = (q !== undefined ? q : document.getElementById('kb-search').value).trim();
        state.filters.search = state.searchMode === 'text' ? val : '';
        if (state.searchMode === 'rag') {
            runRagSearch(val);
            return;
        }
        state.ragHits = null;
        renderActiveFilters();
        renderSidebar();
        if (state.tab === 'list') loadList(true);
        if (state.tab === 'overview' && !val) {/* nichts zu tun */}
        if (state.tab === 'overview' && val) kbSetTab('list');
    };
    window.kbSetSearchMode = function(mode) {
        state.searchMode = mode;
        document.querySelectorAll('.kb-search-mode-btn').forEach(b => b.classList.toggle('is-active', b.dataset.mode === mode));
        const hint = document.getElementById('kb-search-hint');
        const q = document.getElementById('kb-search').value.trim();
        if (mode === 'rag') {
            hint.style.display = 'block';
            hint.innerHTML = q
                ? '<strong>Semantisch:</strong> KI sucht sinnverwandte Stellen, auch ohne exaktes Wort.'
                : '<strong>Semantisch:</strong> stelle eine Frage oder beschreibe, was du suchst — die KI findet passende Stellen.';
            kbSetTab('list');
        } else {
            hint.style.display = 'none';
            hint.innerHTML = '';
        }
        kbRunSearchNow();
    };
    async function runRagSearch(query) {
        if (!query || query.length < 2) { state.ragHits = null; if (state.tab === 'list') renderListBody(); return; }
        state.ragHits = { loading: true, query, hits: [] };
        if (state.tab === 'list') renderListBody();
        const body = { query, top_k: 12 };
        if (state.filters.customer_id && state.filters.customer_id !== 'null') body.customer_id = parseInt(state.filters.customer_id, 10);
        const r = await apiPost('/knowledge/search', body);
        if (!r.success) { state.ragHits = { error: r.message || 'Fehler', query }; }
        else { state.ragHits = { query, hits: r.data.chunks || [] }; }
        if (state.tab === 'list') renderListBody();
    }

    // ===== Tabs ===== //
    window.kbSetTab = function(tab) {
        state.tab = tab;
        document.querySelectorAll('.kb-tab').forEach(t => t.classList.toggle('is-active', t.dataset.tab === tab));
        if (tab === 'overview') loadOverview();
        else loadList(true);
    };

    // ===== Übersicht-Tab: Heatmap =====
    async function loadOverview() {
        const body = document.getElementById('kb-main-body');
        body.innerHTML = '<div class="kb-loading"><div class="kb-loading-spinner"></div></div>';
        // Customer-Filter durchreichen, damit Heatmap nur die gefilterten Kunden zeigt
        const p = new URLSearchParams();
        if (state.filters.customer_status) p.set('customer_status', state.filters.customer_status);
        if (state.filters.customer_tags?.length) p.set('customer_tags', state.filters.customer_tags.join(','));
        const r = await apiGet('/knowledge/dashboard' + (p.toString() ? '?' + p.toString() : ''));
        if (!r.success) { body.innerHTML = '<div class="kb-empty">Fehler beim Laden</div>'; return; }
        const d = r.data;
        const s = d.stats || {};

        const statHtml = `
            <div class="kb-stat-row">
                <div class="kb-stat-card"><div class="kb-stat-label">Dokumente</div><div class="kb-stat-value">${s.docs.toLocaleString('de-DE')}</div><div class="kb-stat-sub">${s.chunks.toLocaleString('de-DE')} Chunks</div></div>
                <div class="kb-stat-card"><div class="kb-stat-label">Kunden mit Wissen</div><div class="kb-stat-value">${s.customers}</div></div>
                <div class="kb-stat-card"><div class="kb-stat-label">Mit Steckbrief</div><div class="kb-stat-value">${s.with_steckbrief}</div><div class="kb-stat-sub">von ${s.customers}</div></div>
                <div class="kb-stat-card ${s.without_steckbrief > 0 ? 'is-warning' : ''}"><div class="kb-stat-label">Ohne Steckbrief</div><div class="kb-stat-value">${s.without_steckbrief}</div><div class="kb-stat-sub">Lücken</div></div>
            </div>`;

        // Heatmap-Header — Spalten sortierbar
        const themes = d.themes || [];
        state.heatmapData = { customers: d.customers || [], themes };
        const sortKey = state.heatmapSort?.key || 'total';
        const sortDir = state.heatmapSort?.dir || 'desc';
        const arrow = (k) => sortKey === k ? (sortDir === 'asc' ? '<span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_upward</span>' : '<span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_downward</span>') : '';
        const headHtml = `<tr>
            <th class="kb-heatmap-name kb-sort-h" onclick="kbHeatmapSort('name')">Kunde ${arrow('name')}</th>
            ${themes.map(t => `<th class="kb-sort-h" onclick="kbHeatmapSort('${t.key}')">${esc(t.label)} ${arrow(t.key)}</th>`).join('')}
            <th class="kb-heatmap-total kb-sort-h" onclick="kbHeatmapSort('total')">Gesamt ${arrow('total')}</th>
        </tr>`;
        // Heatmap-Rows
        function heatClass(n) {
            if (n === 0) return 'heat-0';
            if (n < 3) return 'heat-1';
            if (n < 10) return 'heat-2';
            if (n < 50) return 'heat-3';
            if (n < 200) return 'heat-4';
            return 'heat-5';
        }
        // Sortieren
        const sorted = (d.customers || []).slice();
        sorted.sort((a, b) => {
            let av, bv;
            if (sortKey === 'name') { av = a.name?.toLowerCase() || ''; bv = b.name?.toLowerCase() || ''; }
            else if (sortKey === 'total') { av = a.total || 0; bv = b.total || 0; }
            else { av = a.themes[sortKey] || 0; bv = b.themes[sortKey] || 0; }
            if (av < bv) return sortDir === 'asc' ? -1 : 1;
            if (av > bv) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
        const rowsHtml = sorted.map(c => `
            <tr data-customer-id="${c.id}">
                <td class="kb-heatmap-name">
                    <a class="kb-name-link" href="javascript:kbDrillIntoCustomer(${c.id})">${esc(c.name)}</a>
                    <div class="kb-customer-meta">Letzte Aktualisierung: ${fmtRelDate(c.last_update)}</div>
                </td>
                ${themes.map(t => {
                    const n = c.themes[t.key] || 0;
                    return `<td class="kb-heatmap-cell ${heatClass(n)}" data-theme="${t.key}" data-customer-id="${c.id}" onclick="kbDrillIntoTheme(${c.id}, '${t.key}')">${n === 0 ? '–' : n}</td>`;
                }).join('')}
                <td class="kb-heatmap-row-total">${c.total}</td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="kb-overview">
                ${statHtml}
                <div class="kb-section-title">
                    <span class="material-symbols-rounded">grid_view</span>
                    Lücken-Übersicht — Wissen pro Kunde und Schlüsselthema
                </div>
                <div class="kb-heatmap-wrap">
                    <table class="kb-heatmap">
                        <thead>${headHtml}</thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
                <p style="color:var(--slate-500);font-size:var(--d-fs-xs);margin-top:12px;">
                    Klick auf einen Kundennamen → öffnet die Liste mit Kunden-Filter.
                    Klick auf eine Zelle → öffnet die Liste mit Kunde + Thema gefiltert.
                    Rote „–" markieren fehlendes Wissen zu einem Thema.
                </p>
            </div>`;
    }
    window.kbHeatmapSort = function(key) {
        const cur = state.heatmapSort || { key: 'total', dir: 'desc' };
        if (cur.key === key) cur.dir = cur.dir === 'asc' ? 'desc' : 'asc';
        else { cur.key = key; cur.dir = key === 'name' ? 'asc' : 'desc'; }
        state.heatmapSort = cur;
        loadOverview();
    };
    window.kbDrillIntoCustomer = function(id) {
        state.filters.customer_id = String(id);
        kbSetTab('list');
        renderActiveFilters();
        renderSidebar();
    };
    window.kbDrillIntoTheme = function(customerId, themeKey) {
        state.filters.customer_id = String(customerId);
        const THEME_MAP = {
            steckbrief: { source_type: 'kundensteckbrief' },
            marke: { category: 'Marketing' },
            recht: { category: 'Rechtlich' },
            technik: { category: 'Technik' },
            prozess: { category: 'Prozess' },
            referenz: { category: 'Referenz' },
            asana: { source_type: 'asana' },
            website: { source_type: 'web', ingest_mode: 'auto' },
            transkript: { source_type: 'transcript' },
        };
        const f = THEME_MAP[themeKey] || {};
        state.filters.source_type = f.source_type || '';
        state.filters.category = f.category || '';
        kbSetTab('list');
        renderActiveFilters();
        renderSidebar();
    };

    // ===== Liste-Tab =====
    function listToolbarHtml() {
        const f = state.filters;
        return `
        <div class="kb-list-toolbar">
            <div class="kb-toolbar-group">
                <span class="kb-toolbar-label">Datum</span>
                <input type="date" value="${esc(f.date_from)}" onchange="kbSetDate('date_from', this.value)" title="Von">
                <span style="color:var(--slate-400);">–</span>
                <input type="date" value="${esc(f.date_to)}" onchange="kbSetDate('date_to', this.value)" title="Bis">
            </div>
            <div class="kb-toolbar-group">
                <span class="kb-toolbar-label">Größe</span>
                <select onchange="kbSetFilter('size_bucket', this.value)">
                    <option value="" ${!f.size_bucket?'selected':''}>alle</option>
                    <option value="kurz" ${f.size_bucket==='kurz'?'selected':''}>kurz (≤ 2 Chunks)</option>
                    <option value="mittel" ${f.size_bucket==='mittel'?'selected':''}>mittel (3–10)</option>
                    <option value="lang" ${f.size_bucket==='lang'?'selected':''}>lang (&gt; 10)</option>
                </select>
            </div>
            <div class="kb-toolbar-group">
                <span class="kb-toolbar-label">Status</span>
                <select onchange="kbSetFilter('status', this.value)">
                    <option value="" ${f.status===''?'selected':''}>aktiv</option>
                    <option value="inactive" ${f.status==='inactive'?'selected':''}>archiviert</option>
                </select>
            </div>
        </div>`;
    }
    window.kbSetDate = function(key, val) {
        state.filters[key] = val;
        renderActiveFilters();
        loadList(true);
    };

    async function loadList(reset) {
        if (reset) { state.list.items = []; state.list.offset = 0; }
        const body = document.getElementById('kb-main-body');
        if (reset) body.innerHTML = listToolbarHtml() + '<div class="kb-loading"><div class="kb-loading-spinner"></div></div>';

        const params = buildFilterParams();
        params.set('limit', String(state.list.limit));
        params.set('offset', String(state.list.offset));
        if (state.list.sort_by) params.set('sort_by', state.list.sort_by);
        if (state.list.sort_dir) params.set('sort_dir', state.list.sort_dir);
        const r = await apiGet('/knowledge/documents?' + params.toString());
        if (!r.success) { body.innerHTML = '<div class="kb-empty">Fehler: ' + esc(r.message) + '</div>'; return; }
        state.list.items = reset ? r.data.items : state.list.items.concat(r.data.items);
        state.list.total = r.data.total || r.data.items.length;
        renderListBody();
    }
    function renderListBody() {
        const body = document.getElementById('kb-main-body');
        const items = state.list.items;
        const total = state.list.total;
        const loaded = items.length;

        // RAG-Hits oben anzeigen (wenn aktiv)
        let ragHtml = '';
        if (state.searchMode === 'rag' && state.ragHits) {
            ragHtml = renderRagHits();
        }

        const info = `<div class="kb-list-info">${loaded.toLocaleString('de-DE')} von ${total.toLocaleString('de-DE')} Dokumenten</div>`;

        if (items.length === 0 && !ragHtml) {
            const q = (state.filters.search || '').trim();
            let emptyExtra = '';
            if (q && state.searchMode === 'text') {
                emptyExtra = `<div style="margin-top:14px;">
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="kbSwitchToSemantic()">
                        <span class="material-symbols-rounded" style="font-size:16px;">auto_awesome</span>
                        „${esc(q)}" semantisch suchen
                    </button>
                    <div style="margin-top:8px;color:var(--slate-500);font-size:var(--d-fs-xs);">
                        Wortlaut hat nichts gefunden. Bei Fragen oder Begriffen, die nicht wörtlich im Titel stehen, hilft die semantische Suche.
                    </div>
                </div>`;
            }
            body.innerHTML = listToolbarHtml() + ragHtml + info +
                `<div class="kb-empty">
                    <span class="material-symbols-rounded">library_books</span>
                    <p>Keine Einträge gefunden.</p>
                    ${emptyExtra}
                    ${!q ? '<p style="margin-top:14px;"><a href="javascript:kbOpenNewEntryModal()">Ersten Eintrag erstellen</a></p>' : ''}
                </div>`;
            return;
        }

        // Asana-Dämpfung: zähle Asana-Anteil
        const asanaCount = items.filter(d => d.source_type === 'asana').length;
        const showAsana = !state.list.asanaCollapsed || state.filters.source_type === 'asana';
        const visibleItems = showAsana ? items : items.filter(d => d.source_type !== 'asana');
        const hiddenAsanaCount = asanaCount - (showAsana ? asanaCount : 0);

        let rowsHtml = visibleItems.map(rowHtml).join('');
        let asanaBanner = '';
        if (hiddenAsanaCount > 0 && state.filters.source_type !== 'asana') {
            asanaBanner = `<div class="kb-asana-collapse" onclick="kbToggleAsana()">
                <span class="material-symbols-rounded">task_alt</span>
                <div style="flex:1;">
                    <strong>${hiddenAsanaCount} Asana-Einträge ausgeblendet</strong><br>
                    <small>Klick zum Anzeigen oder „Asana" in der Sidebar wählen</small>
                </div>
                <span class="material-symbols-rounded" style="color:var(--slate-500);">unfold_more</span>
            </div>`;
        }

        const more = loaded < total ? `<button class="kb-load-more" onclick="kbLoadMore()">Weitere ${Math.min(state.list.limit, total - loaded)} laden</button>` : '';

        body.innerHTML = listToolbarHtml() + ragHtml + info + listHeaderHtml() + `<div class="kb-list-rows">${asanaBanner}${rowsHtml}</div>${more}`;
    }
    function listHeaderHtml() {
        const sb = state.list.sort_by;
        const sd = state.list.sort_dir;
        const arrow = (k) => sb === k ? `<span class="material-symbols-rounded">${sd === 'asc' ? 'arrow_upward' : 'arrow_downward'}</span>` : '';
        const cls = (k) => 'kb-h-sort' + (sb === k ? ' is-active' : '');
        return `<div class="kb-list-head">
            <div></div>
            <div></div>
            <div class="${cls('title')}" onclick="kbListSort('title')">Titel ${arrow('title')}</div>
            <div class="${cls('customer')}" onclick="kbListSort('customer')">Kunde ${arrow('customer')}</div>
            <div class="${cls('chunk_count')} kb-h-right" onclick="kbListSort('chunk_count')">Chunks ${arrow('chunk_count')}</div>
            <div class="${cls('source_type')} kb-h-right" onclick="kbListSort('source_type')">Quelle ${arrow('source_type')}</div>
            <div class="${cls('updated_at')} kb-h-right" onclick="kbListSort('updated_at')">Datum ${arrow('updated_at')}</div>
        </div>`;
    }
    window.kbListSort = function(key) {
        if (state.list.sort_by === key) state.list.sort_dir = state.list.sort_dir === 'asc' ? 'desc' : 'asc';
        else { state.list.sort_by = key; state.list.sort_dir = key === 'title' || key === 'customer' || key === 'source_type' ? 'asc' : 'desc'; }
        loadList(true);
    };
    function rowHtml(d) {
        const tags = Array.isArray(d.tags) ? d.tags : [];
        const tagsHtml = tags.slice(0, 3).map(t => `<span class="kb-row-tag">#${esc(t)}</span>`).join('');
        const more = tags.length > 3 ? `<span class="kb-row-tag">+${tags.length - 3}</span>` : '';
        return `
        <div class="kb-row" data-source="${esc(d.source_type)}" data-id="${d.id}" onclick="kbOpenDrawer(${d.id})">
            <div class="kb-row-stripe"></div>
            <div class="kb-row-icon"><span class="material-symbols-rounded">${SOURCE_ICONS[d.source_type] || 'description'}</span></div>
            <div class="kb-row-main">
                <div class="kb-row-title">${esc(d.title || '(ohne Titel)')}</div>
                <div class="kb-row-sub">
                    ${d.category ? `<span>${esc(d.category)}</span>` : ''}
                    ${tagsHtml}${more}
                </div>
            </div>
            <div class="kb-row-cell kb-row-customer is-left">${esc(d.customer_name || '–')}</div>
            <div class="kb-row-cell">${d.chunk_count || 0} chk</div>
            <div class="kb-row-cell">${esc(SOURCE_LABELS[d.source_type] || d.source_type)}</div>
            <div class="kb-row-cell">${fmtRelDate(d.updated_at)}</div>
        </div>`;
    }
    window.kbToggleAsana = function() {
        state.list.asanaCollapsed = !state.list.asanaCollapsed;
        renderListBody();
    };
    window.kbLoadMore = function() {
        state.list.offset += state.list.limit;
        loadList(false);
    };

    function renderRagHits() {
        const r = state.ragHits;
        if (!r) {
            // Modus ist RAG, aber noch keine Suche gestartet (leeres Suchfeld)
            return `<div class="kb-search-results">
                <div class="kb-search-results-head">
                    <span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span>
                    Semantische Suche aktiv
                </div>
                <div style="color:var(--slate-600);font-size:var(--d-fs-sm);line-height:1.5;">
                    Tippe oben eine <strong>Frage</strong> oder <strong>Beschreibung</strong> ein — die KI findet passende Stellen aus deiner Wissensbasis,
                    auch wenn die exakten Wörter dort nicht vorkommen.<br>
                    <span style="color:var(--slate-500);font-size:var(--d-fs-xs);">
                        Beispiele: „Wie war das Briefing bei Wittekind?" · „Welcher Hoster für Mischioff?" · „Brand-Farben Pflegedienst"
                    </span>
                </div>
            </div>`;
        }
        if (r.loading) {
            return `<div class="kb-search-results"><div class="kb-search-results-head"><span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span> Semantische Suche…</div><div class="kb-loading"><div class="kb-loading-spinner"></div></div></div>`;
        }
        if (r.error) {
            return `<div class="kb-search-results"><div style="color:var(--rose-700);">Fehler: ${esc(r.error)}</div></div>`;
        }
        if (!r.hits || r.hits.length === 0) {
            return `<div class="kb-search-results"><div class="kb-search-results-head"><span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span> Semantische Suche</div><div style="color:var(--slate-500);font-size:var(--d-fs-sm);">Keine Treffer für „${esc(r.query)}"</div></div>`;
        }
        const hits = r.hits.map(h => `
            <div class="kb-search-hit" onclick="kbOpenDrawer(${h.document_id})">
                <div class="kb-search-hit-title">${esc(h.title || '(ohne Titel)')}</div>
                <div class="kb-search-hit-snippet">${esc(h.content_preview || '')}</div>
                <div class="kb-search-hit-meta">
                    ${h.customer_name ? `<span>${esc(h.customer_name)}</span>` : ''}
                    ${h.category ? `<span>${esc(h.category)}</span>` : ''}
                    <span class="kb-search-hit-score">Score ${h.score}</span>
                </div>
            </div>`).join('');
        return `<div class="kb-search-results">
            <div class="kb-search-results-head"><span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span> Semantische Suche · ${r.hits.length} Treffer für „${esc(r.query)}"</div>
            ${hits}
        </div>`;
    }

    // ===== Drawer =====
    window.kbOpenDrawer = async function(docId) {
        const drawer = document.getElementById('kb-drawer');
        drawer.innerHTML = `<div class="kb-drawer-head"><h2>Lade…</h2><button class="kb-drawer-close" onclick="kbCloseDrawer()"><span class="material-symbols-rounded">close</span></button></div><div class="kb-drawer-body"><div class="kb-loading"><div class="kb-loading-spinner"></div></div></div>`;
        drawer.classList.add('open');
        document.querySelectorAll('.kb-row.is-selected').forEach(r => r.classList.remove('is-selected'));
        document.querySelector(`.kb-row[data-id="${docId}"]`)?.classList.add('is-selected');

        const r = await apiGet('/knowledge/documents?id=' + docId);
        if (!r.success) { drawer.querySelector('.kb-drawer-body').innerHTML = `<div class="kb-empty">${esc(r.message)}</div>`; return; }
        renderDrawer(r.data);
    };
    function renderDrawer(d) {
        const drawer = document.getElementById('kb-drawer');
        const tags = Array.isArray(d.tags) ? d.tags : [];
        const chunks = (d.chunks || []).slice(0, 8);
        const entities = (d.entities || []).slice(0, 16);

        drawer.innerHTML = `
            <div class="kb-drawer-head">
                <h2>${esc(d.title || '(ohne Titel)')}</h2>
                <button class="kb-drawer-close" onclick="kbCloseDrawer()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="kb-drawer-body">
                <div class="kb-drawer-meta">
                    <span><strong>Quelle</strong> ${esc(SOURCE_LABELS[d.source_type] || d.source_type)}</span>
                    ${d.category ? `<span><strong>Kategorie</strong> ${esc(d.category)}</span>` : ''}
                    ${d.customer_name ? `<span><strong>Kunde</strong> ${esc(d.customer_name)}</span>` : ''}
                    <span><strong>Aktualisiert</strong> ${fmtRelDate(d.updated_at)}</span>
                    ${d.creator_name ? `<span><strong>Von</strong> ${esc(d.creator_name)}</span>` : ''}
                </div>
                ${tags.length ? `<div class="kb-drawer-section"><h3>Tags</h3><div class="kb-drawer-tags">${tags.map(t => `<span class="thx-chip" style="cursor:pointer;" onclick="kbSetFilter('tags','${esc(t).replace(/'/g,'')}')">#${esc(t)}</span>`).join('')}</div></div>` : ''}
                ${d.description ? `<div class="kb-drawer-section"><h3>Beschreibung</h3><div style="font-size:var(--d-fs-sm);color:var(--slate-700);line-height:1.55;">${esc(d.description)}</div></div>` : ''}
                ${d.source_ref ? `<div class="kb-drawer-section"><h3>Referenz</h3><div style="font-size:var(--d-fs-xs);color:var(--slate-600);word-break:break-all;">${esc(d.source_ref)}</div></div>` : ''}
                <div class="kb-drawer-section">
                    <h3>Inhalt — erste ${chunks.length} Chunks</h3>
                    ${chunks.map(c => `<div class="kb-drawer-chunk">${esc(c.content || '').slice(0, 800)}</div>`).join('')}
                    ${(d.chunks || []).length > 8 ? `<div style="color:var(--slate-500);font-size:var(--d-fs-xs);">… ${d.chunks.length - 8} weitere Chunks</div>` : ''}
                </div>
                ${entities.length ? `<div class="kb-drawer-section"><h3>Entities</h3><div class="kb-drawer-tags">${entities.map(e => `<span class="thx-chip">${esc(e.name || e.canonical_name || '')}</span>`).join('')}</div></div>` : ''}
            </div>
            <div class="kb-drawer-actions">
                <a href="/wissen/${d.id}" class="thx-btn thx-btn-primary thx-btn-small">
                    <span class="material-symbols-rounded" style="font-size:16px;">edit</span>
                    Öffnen / Bearbeiten
                </a>
                ${d.source_type === 'web' ? `<a href="${esc(d.source_ref || '#')}" target="_blank" class="thx-btn thx-btn-ghost thx-btn-small"><span class="material-symbols-rounded" style="font-size:16px;">open_in_new</span> Quelle</a>` : ''}
            </div>
        `;
    }
    window.kbCloseDrawer = function() {
        document.getElementById('kb-drawer').classList.remove('open');
        document.querySelectorAll('.kb-row.is-selected').forEach(r => r.classList.remove('is-selected'));
    };

    // ESC schließt Drawer
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') kbCloseDrawer();
    });

    // ===== Rechtsklick-Menues =====

    // Sidebar — Kunden / Quellen / Kategorien / Tags
    if (window.ThxContextMenu) {
        ThxContextMenu.bind('.kb-facet-item', (e, target) => {
            const stab = state.sidebarTab;
            const facetEl = target;
            const label = facetEl.querySelector('.kb-facet-label')?.textContent || '';
            // Wert aus onclick parsen
            const m = (facetEl.getAttribute('onclick') || '').match(/kbSetFilter\('([^']+)',\s*'([^']*)'\)/);
            const key = m?.[1]; const value = m?.[2];

            if (stab === 'customers' && key === 'customer_id' && value && value !== 'null') {
                const cid = parseInt(value, 10);
                return [
                    { header: label },
                    { icon: 'contact_page', label: 'Steckbrief öffnen', onClick: () => window.open('/admin/customers/' + cid + '/steckbrief', '_blank') },
                    { icon: 'auto_awesome', label: 'Steckbrief autobefüllen', onClick: () => kbAutoSuggestForCustomer(cid, label) },
                    { icon: 'inbox', label: 'Bereich befüllen…', onClick: () => kbOpenFillModal({ customer_id: cid, customer_name: label }) },
                    { divider: true },
                    { icon: 'task_alt', label: 'Asana neu syncen', onClick: () => kbTriggerSync('asana', cid, label) },
                    { icon: 'language', label: 'Website crawlen…', onClick: () => window.open('/wissen/neu?customer_id=' + cid + '#website', '_blank') },
                    { divider: true },
                    { icon: 'forum', label: 'Im Chat fragen', onClick: () => window.open('/chat?customer_id=' + cid, '_blank') },
                    { icon: 'list', label: 'Alle Docs anzeigen', onClick: () => { setFilter('customer_id', String(cid)); } },
                ];
            }
            if (stab === 'tags' && key === 'tags') {
                return [
                    { header: '#' + label },
                    { icon: 'filter_alt', label: 'Filter setzen', onClick: () => setFilter('tags', value) },
                    { icon: 'edit', label: 'Tag umbenennen…', onClick: () => kbTagRename(value) },
                    { icon: 'merge', label: 'Mit anderem Tag mergen…', onClick: () => kbTagMerge(value) },
                    { divider: true },
                    { icon: 'delete', label: 'Tag aus allen Docs entfernen', danger: true, onClick: () => kbTagRemove(value) },
                ];
            }
            if (stab === 'sources' || stab === 'categories') {
                return [
                    { header: label },
                    { icon: 'filter_alt', label: 'Filter setzen', onClick: () => setFilter(key, value) },
                    { icon: 'list', label: 'Alle anzeigen', onClick: () => { setFilter(key, value); kbSetTab('list'); } },
                ];
            }
            return [];
        });

        // Matrix-Zellen
        ThxContextMenu.bind('.kb-heatmap-cell', (e, cell) => {
            const cid = parseInt(cell.dataset.customerId, 10);
            const theme = cell.dataset.theme;
            const n = parseInt(cell.textContent.trim(), 10) || 0;
            const themeLabel = state.heatmapData?.themes?.find(t => t.key === theme)?.label || theme;
            const custName = cell.closest('tr')?.querySelector('.kb-name-link')?.textContent?.trim() || '';
            const items = [
                { header: custName + ' · ' + themeLabel + (n > 0 ? ' (' + n + ')' : ' (Lücke)') },
            ];
            if (n > 0) items.push({ icon: 'list', label: 'Drilldown öffnen', onClick: () => kbDrillIntoTheme(cid, theme) });
            items.push({ icon: 'inbox', label: 'Bereich befüllen…', onClick: () => kbOpenFillModal({ customer_id: cid, customer_name: custName, theme }) });
            items.push({ icon: 'auto_awesome', label: 'KI-Vorschläge generieren', onClick: () => kbAutoSuggestForCustomer(cid, custName) });
            items.push({ divider: true });
            items.push({ icon: 'compare_arrows', label: 'Andere Kunden mit viel "' + themeLabel + '"', onClick: () => kbDrillIntoTheme(0, theme) });
            return items;
        });

        // Matrix — Kunden-Name
        ThxContextMenu.bind('.kb-heatmap-name', (e, cell) => {
            const tr = cell.closest('tr');
            const cid = parseInt(tr?.dataset.customerId, 10);
            if (!cid) return [];
            const name = cell.querySelector('.kb-name-link')?.textContent?.trim() || '';
            return [
                { header: name },
                { icon: 'contact_page', label: 'Steckbrief öffnen', onClick: () => window.open('/admin/customers/' + cid + '/steckbrief', '_blank') },
                { icon: 'auto_awesome', label: 'Steckbrief autobefüllen', onClick: () => kbAutoSuggestForCustomer(cid, name) },
                { icon: 'inbox', label: 'Bereich befüllen…', onClick: () => kbOpenFillModal({ customer_id: cid, customer_name: name }) },
                { divider: true },
                { icon: 'forum', label: 'Im Chat fragen', onClick: () => window.open('/chat?customer_id=' + cid, '_blank') },
                { icon: 'list', label: 'Alle Docs', onClick: () => kbDrillIntoCustomer(cid) },
            ];
        });

        // Liste — Doc-Zeilen
        ThxContextMenu.bind('.kb-row', (e, row) => {
            const id = parseInt(row.dataset.id, 10);
            if (!id) return [];
            const doc = state.list.items.find(d => d.id === id);
            const items = [
                { header: doc?.title || ('Doc #' + id) },
                { icon: 'visibility', label: 'Detail öffnen', onClick: () => kbOpenDrawer(id) },
                { icon: 'edit', label: 'Bearbeiten', onClick: () => window.open('/wissen/' + id, '_blank') },
            ];
            if (doc?.source_type === 'web' && doc?.source_ref) {
                items.push({ icon: 'open_in_new', label: 'Original-URL öffnen', onClick: () => window.open(doc.source_ref, '_blank') });
            }
            items.push({ divider: true });
            items.push({ icon: 'auto_awesome', label: 'Ähnliche finden (semantisch)', onClick: () => kbFindSimilar(doc) });
            items.push({ icon: 'refresh', label: 'Re-Process (Embeddings neu)', onClick: () => kbReprocess(id) });
            items.push({ divider: true });
            items.push({ icon: 'delete', label: 'Löschen', danger: true, onClick: () => kbDeleteDoc(id, doc?.title) });
            return items;
        });
    }

    // ===== Aktions-Handler =====
    async function kbTagRename(oldTag) {
        const neu = prompt(`Tag „${oldTag}" umbenennen in:`, oldTag);
        if (!neu || neu.trim() === '' || neu === oldTag) return;
        const r = await apiPost('/knowledge/tag-bulk', { action: 'rename', from: oldTag, to: neu.trim() });
        if (r.success) { App.showNotification(`Tag umbenannt (${r.data.docs_touched} Docs)`, 'success'); renderSidebar(); if (state.tab === 'list') loadList(true); }
        else App.showNotification(r.message || 'Fehler', 'error');
    }
    async function kbTagMerge(tag) {
        const ziel = prompt(`Tag „${tag}" mergen in welches Ziel-Tag?`);
        if (!ziel || ziel.trim() === '' || ziel === tag) return;
        const r = await apiPost('/knowledge/tag-bulk', { action: 'merge', from: tag, to: ziel.trim() });
        if (r.success) { App.showNotification(`Tags zusammengeführt (${r.data.docs_touched} Docs)`, 'success'); renderSidebar(); if (state.tab === 'list') loadList(true); }
        else App.showNotification(r.message || 'Fehler', 'error');
    }
    async function kbTagRemove(tag) {
        if (!confirm(`Tag „${tag}" aus allen Docs entfernen?`)) return;
        const r = await apiPost('/knowledge/tag-bulk', { action: 'remove', from: tag });
        if (r.success) { App.showNotification(`Tag entfernt (${r.data.docs_touched} Docs)`, 'success'); renderSidebar(); if (state.tab === 'list') loadList(true); }
        else App.showNotification(r.message || 'Fehler', 'error');
    }
    async function kbAutoSuggestForCustomer(cid, name) {
        if (!confirm(`KI-Vorschläge für "${name}" generieren? Das durchsucht die Wissensbasis und schlägt Inhalte für alle Karten vor.`)) return;
        App.showNotification('Starte Vorschlags-Generierung…', 'info');
        const r = await apiPost('/admin/customers/' + cid + '/steckbrief-suggest', {});
        if (r.success) App.showNotification(`Fertig: ${r.data.suggestions_created} Vorschläge für ${r.data.cards_processed} Karten`, 'success');
        else App.showNotification(r.message || 'Fehler', 'error');
    }
    async function kbTriggerSync(type, cid, name) {
        if (type !== 'asana') return;
        if (!confirm(`Asana neu syncen für ${name}?`)) return;
        App.showNotification('Asana-Sync läuft…', 'info');
        const r = await apiPost('/admin/customers/' + cid + '/asana-sync', {});
        if (r.success) App.showNotification('Asana-Sync abgeschlossen', 'success');
        else App.showNotification(r.message || 'Asana-Sync fehlgeschlagen', 'error');
    }
    async function kbFindSimilar(doc) {
        if (!doc) return;
        const q = doc.title || '';
        document.getElementById('kb-search').value = q;
        state.filters.search = '';
        kbSetSearchMode('rag');
        kbRunSearchNow(q);
    }
    async function kbReprocess(id) {
        if (!confirm('Re-Process: Chunks und Embeddings für diesen Eintrag neu berechnen?')) return;
        App.showNotification('Re-Process läuft…', 'info');
        const r = await apiPost('/knowledge/documents/' + id + '/reprocess', {});
        if (r.success) App.showNotification('Re-Process abgeschlossen', 'success');
        else App.showNotification(r.message || 'Fehler', 'error');
    }
    async function kbDeleteDoc(id, title) {
        if (!confirm(`Eintrag "${title || id}" wirklich löschen?`)) return;
        const r = await fetch('/api/v1/knowledge/documents/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': csrfToken },
        });
        const j = await r.json();
        if (j.success) { App.showNotification('Gelöscht', 'success'); loadList(true); }
        else App.showNotification(j.message || 'Fehler', 'error');
    }

    // ===== Bereich-befüllen-Modal =====
    window.kbOpenFillModal = function(ctx) {
        let overlay = document.getElementById('kb-fill-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'kb-fill-overlay';
            overlay.className = 'thx-modal-backdrop';
            document.body.appendChild(overlay);
        }
        const cid = ctx.customer_id;
        const cname = ctx.customer_name || '';
        const theme = ctx.theme || null;
        const themeLabel = theme ? (state.heatmapData?.themes?.find(t => t.key === theme)?.label || theme) : null;
        overlay.innerHTML = `
            <div class="thx-modal" onclick="event.stopPropagation()" style="max-width:560px;">
                <div class="thx-modal-header">
                    <h3 style="margin:0;">Bereich befüllen</h3>
                    <button class="thx-modal-close" onclick="kbCloseFillModal()"><span class="material-symbols-rounded">close</span></button>
                </div>
                <div class="thx-modal-body">
                    <p style="margin:0 0 16px 0;color:var(--slate-600);">
                        <strong>${esc(cname)}</strong>${themeLabel ? ' · Thema <strong>' + esc(themeLabel) + '</strong>' : ''}
                        <br><small>Wie willst Du Inhalte ergänzen?</small>
                    </p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button class="kb-fill-opt" onclick="window.open('/wissen/neu?customer_id=${cid}#upload','_blank');kbCloseFillModal();">
                            <span class="material-symbols-rounded">upload_file</span>
                            <strong>Datei hochladen</strong>
                            <small>PDF, DOCX, TXT</small>
                        </button>
                        <button class="kb-fill-opt" onclick="window.open('/wissen/neu?customer_id=${cid}#url','_blank');kbCloseFillModal();">
                            <span class="material-symbols-rounded">link</span>
                            <strong>URL hinzufügen</strong>
                            <small>Einzelne Webseite</small>
                        </button>
                        <button class="kb-fill-opt" onclick="window.open('/wissen/neu?customer_id=${cid}#website','_blank');kbCloseFillModal();">
                            <span class="material-symbols-rounded">language</span>
                            <strong>Website crawlen</strong>
                            <small>Ganze Domain</small>
                        </button>
                        <button class="kb-fill-opt" onclick="window.open('/wissen/neu?customer_id=${cid}#text','_blank');kbCloseFillModal();">
                            <span class="material-symbols-rounded">edit_note</span>
                            <strong>Text einfügen</strong>
                            <small>Roh-Text + KI-Analyse</small>
                        </button>
                        <button class="kb-fill-opt" onclick="window.open('/admin/transkription','_blank');kbCloseFillModal();">
                            <span class="material-symbols-rounded">mic</span>
                            <strong>Transkript</strong>
                            <small>Audio/Loom</small>
                        </button>
                        <button class="kb-fill-opt kb-fill-opt-primary" onclick="kbAutoSuggestForCustomer(${cid},'${esc(cname).replace(/'/g,'')}');kbCloseFillModal();">
                            <span class="material-symbols-rounded">auto_awesome</span>
                            <strong>KI-Vorschläge</strong>
                            <small>Aus vorhandenem Wissen</small>
                        </button>
                    </div>
                </div>
            </div>`;
        overlay.style.display = 'flex';
        overlay.onclick = (e) => { if (e.target === overlay) kbCloseFillModal(); };
    };
    window.kbCloseFillModal = function() {
        const o = document.getElementById('kb-fill-overlay');
        if (o) { o.style.display = 'none'; o.innerHTML = ''; }
    };

    // ===== Neuer-Eintrag-Modal =====
    window.kbOpenNewEntryModal = function() {
        let overlay = document.getElementById('kb-new-entry-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'kb-new-entry-overlay';
            overlay.className = 'kb-new-entry-overlay';
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="kb-new-entry-modal" onclick="event.stopPropagation()">
                <div class="kb-new-entry-head">
                    <div>
                        <h3 style="margin:0;font-size:var(--d-fs-lg);">Neuer Wissens-Eintrag</h3>
                        <small style="color:var(--slate-500);">Dokument, URL, Website, Text oder Chat-Beitrag als Wissen aufnehmen</small>
                    </div>
                    <button class="thx-modal-close" onclick="kbCloseNewEntryModal()" title="Schließen">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
                <iframe class="kb-new-entry-frame" src="/wissen/neu?embed=1" frameborder="0"></iframe>
            </div>`;
        overlay.classList.add('open');
        overlay.onclick = (e) => { if (e.target === overlay) kbCloseNewEntryModal(); };
        // Iframe-Save-Event abfangen via postMessage
        window.__kbNewEntryListener = (e) => {
            if (e.data && e.data.type === 'wissen-saved') {
                kbCloseNewEntryModal();
                if (typeof loadList === 'function') loadList(true);
                if (state.tab === 'overview') loadOverview();
                if (window.App?.showNotification) App.showNotification('Wissens-Eintrag gespeichert', 'success');
            }
        };
        window.addEventListener('message', window.__kbNewEntryListener);
    };
    window.kbCloseNewEntryModal = function() {
        const o = document.getElementById('kb-new-entry-overlay');
        if (o) { o.classList.remove('open'); o.innerHTML = ''; }
        if (window.__kbNewEntryListener) {
            window.removeEventListener('message', window.__kbNewEntryListener);
            window.__kbNewEntryListener = null;
        }
    };

    // ===== Init =====
    renderSidebar();
    loadOverview();
})();
</script>
