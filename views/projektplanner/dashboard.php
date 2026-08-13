<?php /** @var array $title */ ?>
<style>
.pd-page { /* max-width/padding entfernt — .main-content liefert die Gutter konsistent */ }

.pd-filters {
    background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
    padding: var(--d-card-pad); margin-bottom: var(--d-section-gap);
    display: flex; align-items: center; gap: var(--d-section-gap); flex-wrap: wrap;
}
.pd-filters label { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 600; }
.pd-filters input, .pd-filters select {
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-family: inherit; font-size: var(--d-control-fs); background: #fff; color: var(--slate-700);
}
.pd-filters input:focus, .pd-filters select:focus { outline: none; border-color: var(--thoxan-400); }

.pd-preset-row {
    display: flex; gap: var(--d-row-gap); flex-wrap: wrap; align-items: center;
    margin-top: var(--d-row-gap); width: 100%;
}
.pd-preset {
    background: var(--slate-50); color: var(--slate-600);
    border: 1px solid var(--slate-200);
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    font-size: var(--d-fs-xs); border-radius: 999px;
    cursor: pointer; font-family: inherit;
}
.pd-preset:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); }
.pd-preset.is-active { background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600); }

.pd-tabs {
    display: flex; gap: 2px;
    border-bottom: 1px solid var(--slate-200);
    margin-bottom: var(--d-section-gap);
}
.pd-tab {
    padding: var(--d-row-pad-y) var(--d-row-pad-x); cursor: pointer; border: none; background: transparent;
    font-family: inherit; font-size: var(--d-fs-sm); color: var(--slate-600); font-weight: 500;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.pd-tab:hover { color: var(--thoxan-700); border-bottom-color: var(--thoxan-300); }
.pd-tab.is-active { color: var(--thoxan-700); border-bottom-color: var(--thoxan-600); font-weight: 700; }

.pd-body {
    background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
    padding: var(--d-card-pad); min-height: 320px;
}
.pd-empty { text-align: center; padding: 40px 20px; color: var(--slate-400); font-size: var(--d-fs-sm); }

.pd-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--d-section-gap); }
.pd-kpi { padding: var(--d-card-pad); background: var(--slate-50); border-radius: var(--d-card-radius); }
.pd-kpi-label {
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--slate-500); margin-bottom: 6px; font-weight: 600;
}
.pd-kpi-value {
    font-size: var(--d-fs-2xl); font-weight: 800;
    font-family: ui-monospace, monospace; color: var(--slate-800);
}
.pd-kpi.is-soll  .pd-kpi-value { color: #7e22ce; }
.pd-kpi.is-ist   .pd-kpi-value { color: var(--thoxan-700); }
.pd-kpi.is-diff.is-pos .pd-kpi-value { color: var(--emerald-700); }
.pd-kpi.is-diff.is-neg .pd-kpi-value { color: var(--rose-700); }
.pd-kpi.is-done  .pd-kpi-value { color: var(--emerald-700); }
.pd-kpi-sub { font-size: var(--d-fs-xs); color: var(--slate-400); margin-top: 4px; }

.pd-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); }
.pd-table th {
    text-align: left; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); color: var(--slate-500);
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 2px solid var(--slate-200); font-weight: 600;
    cursor: pointer; user-select: none;
}
.pd-table th.is-right { text-align: right; }
.pd-table th .pd-sort-arrow { opacity: 0.3; margin-left: 4px; font-size: var(--d-fs-xs); }
.pd-table th.is-sorted .pd-sort-arrow { opacity: 1; }
.pd-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); }
.pd-table td.is-right { text-align: right; font-family: ui-monospace, monospace; }
.pd-table tr:hover td { background: var(--slate-50); }
.pd-table tr.pd-row-total td {
    background: var(--slate-100); font-weight: 700;
    border-top: 2px solid var(--slate-300); border-bottom: 2px solid var(--slate-300);
}

.pd-customer-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    margin-right: 6px; vertical-align: middle;
}
.pd-pill {
    display: inline-block; padding: var(--d-row-pad-y) var(--d-control-pad-x); border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 700; font-family: ui-monospace, monospace;
}
.pd-pill.is-pos  { background: var(--emerald-50); color: var(--emerald-700); }
.pd-pill.is-neg  { background: var(--rose-50);    color: var(--rose-700);    }
.pd-pill.is-zero { background: var(--slate-100);  color: var(--slate-600);   }

.pd-bar-bg {
    display: inline-block; width: 60px; height: 6px;
    background: var(--slate-100); border-radius: 3px; margin-left: 6px;
    vertical-align: middle; overflow: hidden;
}
.pd-bar-fill { display: block; height: 100%; border-radius: 3px; }
.pd-bar-fill.is-ok   { background: var(--emerald-500); }
.pd-bar-fill.is-warn { background: var(--amber-500); }
.pd-bar-fill.is-over { background: var(--rose-500); }

.pd-forecast-table { min-width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); }
.pd-forecast-table th, .pd-forecast-table td {
    padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100);
    text-align: center; font-family: ui-monospace, monospace;
}
.pd-forecast-table thead th {
    background: var(--slate-50); border-bottom: 2px solid var(--slate-200);
    font-size: var(--d-fs-xs); text-transform: uppercase; color: var(--slate-500); font-weight: 600;
}
.pd-forecast-table tbody td:first-child {
    text-align: left; font-family: inherit; font-weight: 600; color: var(--slate-800);
    background: var(--slate-50); position: sticky; left: 0;
}
.pd-forecast-table .pd-row-cap td {
    background: var(--thoxan-50); font-weight: 700; color: var(--thoxan-800);
    border-top: 2px solid var(--thoxan-200);
}
.pd-forecast-table .pd-row-cap td:first-child { background: var(--thoxan-100); }
.pd-row-avail-pos { color: var(--emerald-700); font-weight: 700; }
.pd-row-avail-neg { color: var(--rose-700); font-weight: 700; }

.pd-person-tasks {
    margin-top: var(--d-section-gap); padding: var(--d-card-pad);
    background: var(--slate-50); border-radius: var(--d-card-radius);
}
.pd-person-tasks h4 { margin: 0 0 var(--d-row-gap); font-size: var(--d-fs-sm); color: var(--slate-700); }
.pd-person-task-row {
    padding: var(--d-row-pad-y) var(--d-row-pad-x); border-bottom: 1px solid var(--slate-200);
    display: grid; grid-template-columns: 1fr 90px 60px 60px 70px; gap: var(--d-row-gap);
    font-size: var(--d-fs-xs); align-items: center;
}
.pd-person-task-row:last-child { border-bottom: none; }
.pd-role-badge {
    display: inline-block; padding: 1px var(--d-control-pad-x); border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase;
}
.pd-role-badge.is-lead { background: var(--thoxan-100); color: var(--thoxan-800); }
.pd-role-badge.is-resp { background: var(--slate-200); color: var(--slate-700); }

/* ===== Projektübersicht-Matrix ===== */
.pd-matrix-wrap { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); }
.pd-matrix-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; border-bottom: 1px solid var(--slate-200);
}
.pd-matrix-legend { font-size: var(--d-fs-xs); color: var(--slate-500); }
.pd-matrix-scroll { overflow-x: auto; }
.pd-matrix {
    width: 100%; border-collapse: collapse;
    font-size: var(--d-fs-xs);
    font-variant-numeric: tabular-nums;
}
.pd-matrix th {
    padding: 8px 6px; text-align: center;
    font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--slate-500); font-weight: 700;
    border-bottom: 2px solid var(--slate-200);
    background: var(--slate-50);
}
.pd-matrix th.pd-mx-name-h { text-align: left; padding-left: 12px; min-width: 220px; }
.pd-matrix th.pd-mx-month-h { min-width: 56px; }
.pd-matrix th.pd-mx-month-h.is-q-end { border-right: 1px solid var(--slate-300); }
.pd-matrix th.pd-mx-total-h { background: var(--slate-100); min-width: 64px; }
.pd-matrix tbody tr { cursor: pointer; transition: background 0.08s; }
.pd-matrix tbody tr:hover { background: var(--slate-50); }
.pd-matrix td { padding: 6px; border-bottom: 1px solid var(--slate-100); vertical-align: top; }
.pd-matrix td.pd-mx-name { padding-left: 12px; vertical-align: middle; min-width: 220px; }
.pd-mx-meta { font-size: 10px; color: var(--slate-500); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pd-mx-logo { width: 28px; height: 28px; object-fit: contain; border-radius: 4px; background: #fff; border: 1px solid var(--slate-200); padding: 2px; flex-shrink: 0; }
.pd-mx-abbr {
    width: 28px; height: 28px; border-radius: 4px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; flex-shrink: 0;
    padding-top: 1px;
}
.pd-matrix td.pd-mx-cell {
    text-align: center;
    line-height: 1.35;
    border-left: 1px solid var(--slate-100);
}
.pd-matrix td.pd-mx-cell:nth-child(4n) { border-right: 1px solid var(--slate-300); }
.pd-mx-cell-soll { font-weight: 600; color: var(--slate-700); }
.pd-mx-cell-abg  { color: var(--slate-500); font-size: 10px; }
.pd-mx-cell-abg.pd-mx-empty { color: var(--slate-300); }
.pd-mx-cell-ist  { color: var(--thoxan-700); font-size: 10px; font-weight: 600; }
.pd-matrix td.pd-mx-cell.is-pos { background: var(--emerald-50); }
.pd-matrix td.pd-mx-cell.is-neg { background: var(--rose-50); }
.pd-matrix td.pd-mx-total {
    text-align: right;
    font-weight: 700;
    background: var(--slate-50);
    padding-right: 12px;
}
.pd-matrix td.pd-mx-total.is-pos { color: var(--emerald-700); }
.pd-matrix td.pd-mx-total.is-neg { color: var(--rose-700); }

/* ===== Pläne (Liste + Kacheln) ===== */
.pd-plans-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}
.pd-sort-toggle {
    display: inline-flex; background: var(--slate-100); border-radius: 6px; padding: 2px;
    gap: 2px;
}
.pd-sort-btn {
    border: 0; background: transparent; padding: 5px 12px;
    font-size: var(--d-fs-xs); font-weight: 600;
    color: var(--slate-600);
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.1s, color 0.1s;
}
.pd-sort-btn:hover { color: var(--slate-800); }
.pd-sort-btn.is-active { background: #fff; color: var(--thoxan-700); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }

.pd-plans-table {
    width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm);
    font-variant-numeric: tabular-nums;
}
.pd-plans-table th {
    padding: 10px 12px; text-align: left;
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--slate-500); font-weight: 700;
    border-bottom: 2px solid var(--slate-200);
    background: var(--slate-50);
}
.pd-plans-table th.is-right { text-align: right; }
.pd-plans-table td { padding: 8px 12px; border-bottom: 1px solid var(--slate-100); }
.pd-plans-table td.is-right { text-align: right; }
.pd-plans-table tbody tr { cursor: pointer; transition: background 0.08s; }
.pd-plans-table tbody tr:hover { background: var(--slate-50); }
.pd-plans-table td.is-pos { color: var(--emerald-700); }
.pd-plans-table td.is-neg { color: var(--rose-700); }

.pd-tiles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    align-items: start;
}
.pd-tiles-grid > .pp-w-tile { width: 100%; }
/* (Compact-Tiles haben fixe Hoehe via .pp-w-tile-Klassen im PHP-Renderer) */

/* ===== Risiko-Faerbung der Plan-Kacheln ====================================
   Kachel selbst bleibt weiss (Inhalt lesbar), aber der Rand und ein Flag-Badge
   zeigen den Risiko-Status. Eskaliert = roter Rand, Gruen = gruener Rand,
   Nicht relevant = ausgegraut. */
.pd-tile {
    position: relative;
    border-radius: 12px;
    transition: transform 0.15s, box-shadow 0.15s;
}
.pd-tile:hover { transform: translateY(-1px); }
.pd-tile.pd-tile-risiko-eskaliert {
    box-shadow: 0 0 0 2px #dc2626, 0 4px 10px rgba(220, 38, 38, 0.2);
    border-radius: 12px;
    overflow: visible;
}
/* Auto = "noch zu erledigen" / Work-in-progress. Diese Plaene verdienen
   Aufmerksamkeit (zwischen brennend und fertig). Blauer Rand + Work-Icon. */
.pd-tile.pd-tile-risiko-auto {
    box-shadow: 0 0 0 2px #2563eb;
    border-radius: 12px;
    overflow: visible;
}
/* Gruen = "fertig": klar erkennbar an gruenem Akzent, aber nicht prominent
   wie eskaliert. Mittelweg zwischen Auto (Aufmerksamkeit) und nicht_relevant
   (komplett ausgegraut). Leichte Opacity-Reduktion + duenner gruener Rand. */
.pd-tile.pd-tile-risiko-gruen {
    box-shadow: 0 0 0 1px #86efac;
    background: #f0fdf4;
    border-radius: 12px;
    opacity: 0.78;
}
.pd-tile.pd-tile-risiko-gruen:hover { opacity: 1; box-shadow: 0 0 0 1px #10b981; }

.pd-tile.pd-tile-risiko-nicht_relevant {
    opacity: 0.5;
    filter: grayscale(50%);
}
.pd-tile.pd-tile-risiko-nicht_relevant:hover { opacity: 0.9; filter: none; }

.pd-tile-flag {
    position: absolute;
    top: -10px;
    left: -8px;
    z-index: 10;
    background: #fff;
    border: 2px solid;
    border-radius: 99px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    line-height: 1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    cursor: help;
}
.pd-tile-flag-eskaliert      { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
.pd-tile-flag-gruen          { border-color: #10b981; color: #047857; background: #f0fdf4; }
.pd-tile-flag-nicht_relevant { border-color: #94a3b8; color: #475569; background: #f8fafc; }
.pd-tile-flag-auto           { border-color: #2563eb; color: #1d4ed8; background: #eff6ff; }

/* Kontext-Menue (Rechtsklick) */
.pd-risiko-menu button:hover { background: #f1f5f9 !important; }

/* ===== Cockpit ===== */
.pd-cockpit-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.pd-kpi-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    padding: 16px 18px;
}
.pd-kpi-card.is-warn { border-color: var(--rose-300); background: var(--rose-50); }
.pd-kpi-label { font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.04em; }
.pd-kpi-value { font-size: 28px; font-weight: 700; color: var(--slate-900); line-height: 1.1; margin-top: 4px; font-variant-numeric: tabular-nums; }
.pd-kpi-u { font-size: 14px; color: var(--slate-400); font-weight: 500; }
.pd-kpi-sub { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 6px; display: flex; align-items: center; flex-wrap: wrap; gap: 2px; }
.pd-kpi-card.is-warn .pd-kpi-value { color: var(--rose-700); }

.pd-health-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    margin-right: 4px;
    flex-shrink: 0;
}
.pd-health-dot.is-green  { background: var(--emerald-500); }
.pd-health-dot.is-yellow { background: var(--amber-500); }
.pd-health-dot.is-red    { background: var(--rose-500); }

.pd-section { margin-bottom: 22px; }
.pd-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.pd-section-link { font-size: var(--d-fs-xs); color: var(--thoxan-700); text-decoration: none; font-weight: 600; }
.pd-section-link:hover { color: var(--thoxan-900); }

.pd-risk-list { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); overflow: hidden; }
.pd-risk-row {
    display: flex; align-items: center; gap: 14px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--slate-100);
    text-decoration: none;
    color: var(--slate-800);
    transition: background 0.08s;
}
.pd-risk-row:last-child { border-bottom: 0; }
.pd-risk-row:hover { background: var(--slate-50); }
.pd-risk-row .pd-health-dot { width: 12px; height: 12px; margin-right: 0; }
.pd-risk-meta { flex: 1; min-width: 0; }
.pd-risk-title { font-weight: 700; font-size: var(--d-fs-sm); color: var(--slate-900); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pd-risk-reason { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; }
.pd-risk-nums { text-align: right; font-variant-numeric: tabular-nums; font-size: var(--d-fs-sm); }
.pd-risk-nums strong { color: var(--thoxan-700); }
.pd-risk-progress { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; }
</style>

<div class="pd-page">
    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Projektplanner — Dashboard</h1>
            <div class="thx-page-subtitle">Übersicht über alle Pläne, Personen-Auslastung und Forecast.</div>
        </div>
        <div class="thx-page-actions">
            <a href="/admin/projektplanner" class="thx-btn thx-btn-secondary">
                <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span>
                Zum Editor
            </a>
        </div>
    </div>

    <div class="pd-filters">
        <label>Von</label>
        <input type="date" id="pd-from" onchange="pdLoad()">
        <label>Bis</label>
        <input type="date" id="pd-to" onchange="pdLoad()">
        <label>Status</label>
        <select id="pd-status" onchange="pdLoad()">
            <option value="">Alle</option>
            <option value="entwurf">Entwurf</option>
            <option value="aktiv" selected>Aktiv</option>
            <option value="einzelprojekt">Einzelprojekt</option>
            <option value="reporting">Reporting</option>
            <option value="abgeschlossen">Abgeschlossen</option>
        </select>
        <label>Kunde</label>
        <select id="pd-customer" onchange="pdLoad()">
            <option value="">Alle</option>
        </select>
        <label>Person</label>
        <select id="pd-person" onchange="pdRenderTab()">
            <option value="">Alle</option>
        </select>
        <span style="flex:1;"></span>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="pdResetFilters()">Reset</button>

        <div class="pd-preset-row">
            <span style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-right:6px;">Zeitraum:</span>
            <button class="pd-preset" onclick="pdPreset('week')">Diese Woche</button>
            <button class="pd-preset" onclick="pdPreset('month')">Dieser Monat</button>
            <button class="pd-preset" onclick="pdPreset('quarter')">Dieses Quartal</button>
            <button class="pd-preset" onclick="pdPreset('half')">Halbjahr</button>
            <button class="pd-preset" onclick="pdPreset('year')">Jahr</button>
            <button class="pd-preset" onclick="pdPreset('prev-month')">Letzter Monat</button>
            <button class="pd-preset" onclick="pdPreset('prev-quarter')">Letztes Quartal</button>
            <button class="pd-preset" onclick="pdPreset('all')">Alles</button>
        </div>
    </div>

    <div class="pd-tabs">
        <button class="pd-tab is-active" data-tab="cockpit"  onclick="pdSetTab('cockpit')">Cockpit</button>
        <button class="pd-tab"           data-tab="plans"    onclick="pdSetTab('plans')">Pläne</button>
        <button class="pd-tab"           data-tab="auslastung" onclick="pdSetTab('auslastung')">Auslastung</button>
        <button class="pd-tab"           data-tab="myopen"   onclick="pdSetTab('myopen')">Meine Aufgaben</button>
        <button class="pd-tab"           data-tab="kunden"   onclick="pdSetTab('kunden')">Kunden</button>
    </div>

    <script>window.PP_CURRENT_USER = <?= json_encode($ppCurrentUser ?? null) ?>;</script>

    <div class="pd-body" id="pd-body">
        <div class="pd-empty">Lädt…</div>
    </div>
</div>

<script>
'use strict';
const pdState = { data: null, tab: 'cockpit', sort: { col: 'soll', dir: 'desc' }, plansView: 'tiles', plansFilter: { suche: '', risiko: 'alle', kunde: 'alle' }, plansColSort: { col: null, dir: 'asc' } };

function pdEsc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function pdInit() {
    // Customer-Filter füllen
    try {
        const cs = await fetch('/api/v1/admin/customers').then(r => r.json());
        const sel = document.getElementById('pd-customer');
        sel.innerHTML = '<option value="">Alle</option>' +
            (cs.data || []).filter(c => c.is_active).map(c =>
                `<option value="${c.id}">${pdEsc(c.name)}</option>`).join('');
    } catch (_) {}
    // Initial-Tab aus URL-Hash (#plantiles, #planlist, ...) — Fallback: kpi
    const hashTab = (window.location.hash || '').replace('#', '');
    if (hashTab && document.querySelector(`.pd-tab[data-tab="${hashTab}"]`)) {
        pdState.tab = hashTab;
        document.querySelectorAll('.pd-tab').forEach(b => b.classList.toggle('is-active', b.dataset.tab === hashTab));
    }
    // Browser back/forward soll Tab umschalten
    window.addEventListener('hashchange', () => {
        const t = (window.location.hash || '').replace('#', '');
        if (t && t !== pdState.tab && document.querySelector(`.pd-tab[data-tab="${t}"]`)) {
            pdState.tab = t;
            document.querySelectorAll('.pd-tab').forEach(b => b.classList.toggle('is-active', b.dataset.tab === t));
            pdRenderTab();
        }
    });
    await pdLoad();
}

async function pdLoad() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    try {
        const params = new URLSearchParams();
        const status = document.getElementById('pd-status').value;
        const from = document.getElementById('pd-from').value;
        const to = document.getElementById('pd-to').value;
        const cust = document.getElementById('pd-customer').value;
        if (status) params.set('status', status);
        if (from) params.set('date_from', from);
        if (to) params.set('date_to', to);
        if (cust) params.set('customer_id', cust);
        const r = await fetch('/api/v1/admin/projektplanner/dashboard?' + params.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        pdState.data = j.data;
        // Personen-Filter füllen
        const personSel = document.getElementById('pd-person');
        const prev = personSel.value;
        const personNames = Object.keys(j.data.by_person || {}).sort();
        personSel.innerHTML = '<option value="">Alle</option>' +
            personNames.map(p => `<option value="${pdEsc(p)}" ${p === prev ? 'selected' : ''}>${pdEsc(p)}</option>`).join('');
        pdRenderTab();
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

function pdSetTab(t) {
    pdState.tab = t;
    document.querySelectorAll('.pd-tab').forEach(b => b.classList.toggle('is-active', b.dataset.tab === t));
    // URL aktualisieren, damit der Tab teilbar/bookmarkbar ist (z.B. .../dashboard#plantiles)
    try {
        if (window.location.hash !== '#' + t) history.replaceState(null, '', '#' + t);
    } catch (_) {}
    pdRenderTab();
}

function pdRenderTab() {
    if (!pdState.data) return;
    const fn = {
        cockpit:    pdRenderCockpit,
        plans:      pdRenderPlansTab,
        auslastung: pdRenderAuslastung,
        myopen:     pdRenderMyOpen,
        kunden:     pdRenderKunden,
    }[pdState.tab];
    if (fn) fn();
}

function pdPreset(name) {
    document.querySelectorAll('.pd-preset').forEach(b => b.classList.remove('is-active'));
    event.target.classList.add('is-active');
    const today = new Date();
    let from = null, to = null;
    const fmt = d => d.toISOString().slice(0, 10);
    const y = today.getFullYear(), m = today.getMonth();
    if (name === 'week') {
        const dow = (today.getDay() + 6) % 7; // Mo=0
        from = new Date(today); from.setDate(today.getDate() - dow);
        to = new Date(from); to.setDate(from.getDate() + 6);
    } else if (name === 'month') {
        from = new Date(y, m, 1); to = new Date(y, m + 1, 0);
    } else if (name === 'quarter') {
        const q = Math.floor(m / 3);
        from = new Date(y, q * 3, 1); to = new Date(y, q * 3 + 3, 0);
    } else if (name === 'half') {
        const h = m < 6 ? 0 : 6;
        from = new Date(y, h, 1); to = new Date(y, h + 6, 0);
    } else if (name === 'year') {
        from = new Date(y, 0, 1); to = new Date(y, 11, 31);
    } else if (name === 'prev-month') {
        from = new Date(y, m - 1, 1); to = new Date(y, m, 0);
    } else if (name === 'prev-quarter') {
        const q = Math.floor(m / 3) - 1;
        const py = q < 0 ? y - 1 : y, pq = (q + 4) % 4;
        from = new Date(py, pq * 3, 1); to = new Date(py, pq * 3 + 3, 0);
    } else if (name === 'all') {
        from = null; to = null;
    }
    document.getElementById('pd-from').value = from ? fmt(from) : '';
    document.getElementById('pd-to').value = to ? fmt(to) : '';
    pdLoad();
}

function pdResetFilters() {
    document.getElementById('pd-from').value = '';
    document.getElementById('pd-to').value = '';
    document.getElementById('pd-status').value = '';
    document.getElementById('pd-customer').value = '';
    document.getElementById('pd-person').value = '';
    document.querySelectorAll('.pd-preset').forEach(b => b.classList.remove('is-active'));
    pdLoad();
}

/* ===== Tab 1: KPIs ===== */
function pdRenderKpis() {
    let t = pdState.data.totals;
    const person = document.getElementById('pd-person').value;
    if (person && pdState.data.by_person[person]) {
        const p = pdState.data.by_person[person];
        t = { soll: p.soll, ist: p.ist, done: p.done, open: p.open, total: p.done + p.open };
    }
    const diff = t.ist - t.soll;
    const diffClass = diff > 0 ? 'is-pos' : (diff < 0 ? 'is-neg' : 'is-zero');
    const progress = t.total > 0 ? Math.round((t.done / t.total) * 100) : 0;
    document.getElementById('pd-body').innerHTML = `
        ${person ? `<div style="color:var(--slate-500);font-size:var(--d-fs-xs);margin-bottom:14px;">Gefiltert auf: <strong>${pdEsc(person)}</strong></div>` : ''}
        <div class="pd-kpis">
            <div class="pd-kpi is-soll">
                <div class="pd-kpi-label">Soll (h)</div>
                <div class="pd-kpi-value">${t.soll.toFixed(0)}</div>
                <div class="pd-kpi-sub">geplanter Aufwand</div>
            </div>
            <div class="pd-kpi is-ist">
                <div class="pd-kpi-label">Ist (h)</div>
                <div class="pd-kpi-value">${t.ist.toFixed(0)}</div>
                <div class="pd-kpi-sub">bisher gearbeitet</div>
            </div>
            <div class="pd-kpi is-diff ${diffClass}">
                <div class="pd-kpi-label">Differenz</div>
                <div class="pd-kpi-value">${diff > 0 ? '+' : ''}${diff.toFixed(0)}</div>
                <div class="pd-kpi-sub">${diff > 0 ? 'über Plan' : (diff < 0 ? 'unter Plan' : 'auf Plan')}</div>
            </div>
            <div class="pd-kpi is-done">
                <div class="pd-kpi-label">Erledigt</div>
                <div class="pd-kpi-value">${t.done} <span style="color:var(--slate-400);font-size: var(--d-fs-lg);font-weight:500;">/ ${t.total}</span></div>
                <div class="pd-kpi-sub">${t.open} offen</div>
            </div>
            <div class="pd-kpi">
                <div class="pd-kpi-label">Fortschritt</div>
                <div class="pd-kpi-value">${progress}%</div>
                <div class="pd-kpi-sub">der Aufgaben fertig</div>
            </div>
        </div>
    `;
}

/* ===== Tab 2: Personen ===== */
function pdRenderPersons() {
    const persons = Object.entries(pdState.data.by_person);
    if (!persons.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Keine Personen-Daten</div>';
        return;
    }
    persons.sort((a, b) => b[1].soll - a[1].soll);
    // Duplikat-Detection: ähnliche Namen über Levenshtein-Distanz erkennen
    const dups = pdDetectDuplicates(persons.map(p => p[0]));
    let totSoll = 0, totIst = 0, totDone = 0, totOpen = 0, totCap = 0;
    const rows = persons.map(([name, v]) => {
        const cap = pdState.data.capacity[name] || 0;
        const util = cap > 0 ? Math.round((v.soll / cap) * 100) : 0;
        const utilClass = util > 100 ? 'is-over' : (util > 80 ? 'is-warn' : 'is-ok');
        const free = cap - v.soll;
        totSoll += v.soll; totIst += v.ist; totDone += v.done; totOpen += v.open; totCap += cap;
        const tasksTotal = v.done + v.open;
        return `
        <tr style="cursor:pointer;" onclick="document.getElementById('pd-person').value='${pdEsc(name)}';pdRenderTab();">
            <td><strong>${pdEsc(name)}</strong></td>
            <td class="is-right">${v.soll.toFixed(1)}</td>
            <td class="is-right">${v.ist.toFixed(1)}</td>
            <td class="is-right">${cap || '—'}</td>
            <td class="is-right" style="color:${free < 0 ? 'var(--rose-700)' : 'var(--slate-600)'};">${cap > 0 ? free.toFixed(0) : '—'}</td>
            <td class="is-right">
                ${cap > 0 ? `${util}%
                <span class="pd-bar-bg"><span class="pd-bar-fill ${utilClass}" style="width:${Math.min(100, util)}%"></span></span>` : '—'}
            </td>
            <td class="is-right"><span class="pd-pill is-zero">${v.done}/${tasksTotal}</span></td>
        </tr>`;
    }).join('');
    const totUtil = totCap > 0 ? Math.round((totSoll / totCap) * 100) : 0;
    const totalRow = `
        <tr class="pd-row-total">
            <td>Gesamt (${persons.length} Personen)</td>
            <td class="is-right">${totSoll.toFixed(1)}</td>
            <td class="is-right">${totIst.toFixed(1)}</td>
            <td class="is-right">${totCap}</td>
            <td class="is-right" style="color:${totCap - totSoll < 0 ? 'var(--rose-700)' : 'var(--slate-700)'};">${(totCap - totSoll).toFixed(0)}</td>
            <td class="is-right">${totUtil}%</td>
            <td class="is-right">${totDone} / ${totDone + totOpen}</td>
        </tr>`;
    const dupBanner = dups.length === 0 ? '' : `
        <div style="background:var(--amber-50);color:var(--amber-800);border:1px solid var(--amber-200);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:var(--d-fs-xs);">
            <strong>Mögliche Duplikate erkannt:</strong>
            ${dups.map(([a, b]) => `<span style="background:#fff;padding:2px 8px;border-radius:6px;margin-left:6px;">${pdEsc(a)} ↔ ${pdEsc(b)}</span>`).join('')}
            <div style="margin-top:4px;color:var(--amber-700);">Im Projektplanner → Einstellungen → Team-Mitglieder kannst Du Namen vereinheitlichen (Confirm bietet „Auch in allen Plänen umbenennen" an).</div>
        </div>`;

    const tableHtml = `
        ${dupBanner}
        <table class="pd-table">
            <thead><tr>
                <th>Person</th>
                <th class="is-right">Soll (h)</th>
                <th class="is-right">Ist (h)</th>
                <th class="is-right">Kapazität</th>
                <th class="is-right">Verfügbar</th>
                <th class="is-right">Auslastung</th>
                <th class="is-right">Aufgaben</th>
            </tr></thead>
            <tbody>${rows}${totalRow}</tbody>
        </table>`;

    // Detail-Liste bei Personen-Filter
    let detail = '';
    const filterPerson = document.getElementById('pd-person').value;
    if (filterPerson && pdState.data.person_tasks[filterPerson]) {
        const tasks = pdState.data.person_tasks[filterPerson];
        detail = `
        <div class="pd-person-tasks">
            <h4>Aufgaben von ${pdEsc(filterPerson)} (${tasks.length})</h4>
            ${tasks.map(t => `
                <div class="pd-person-task-row">
                    <div>
                        <span class="pd-customer-dot" style="background:${t.plan_color}"></span>
                        ${t.is_done ? '<span style="color:var(--emerald-600);margin-right:4px;">✓</span>' : ''}
                        <strong>${pdEsc(t.description)}</strong>
                        <span style="color:var(--slate-400);font-size:11px;display:block;margin-left:14px;">${pdEsc(t.plan_title)}</span>
                    </div>
                    <div><span class="pd-role-badge ${t.role === 'lead' ? 'is-lead' : 'is-resp'}">${t.role === 'lead' ? 'HV' : 'Umsetzung'}</span></div>
                    <div style="text-align:right;font-family:ui-monospace,monospace;">${t.soll}</div>
                    <div style="text-align:right;font-family:ui-monospace,monospace;">${t.ist}</div>
                    <div style="text-align:right;color:var(--slate-500);font-size:11px;">${pdEsc(t.deadline)}</div>
                </div>`).join('')}
        </div>`;
    }

    document.getElementById('pd-body').innerHTML = tableHtml + detail;
}

/* ===== Tab 3: Pläne ===== */
function pdRenderPlans() {
    const plans = Object.entries(pdState.data.by_plan);
    if (!plans.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Keine Pläne im Filter</div>';
        return;
    }
    plans.sort((a, b) => b[1].soll - a[1].soll);
    let totSoll = 0, totIst = 0, totDone = 0, totTotal = 0;
    const rows = plans.map(([id, v]) => {
        const diff = v.ist - v.soll;
        const diffCls = diff > 0 ? 'is-pos' : (diff < 0 ? 'is-neg' : 'is-zero');
        const progress = v.total > 0 ? Math.round((v.done / v.total) * 100) : 0;
        const barCls = progress >= 100 ? 'is-ok' : (progress >= 40 ? 'is-warn' : 'is-over');
        totSoll += v.soll; totIst += v.ist; totDone += v.done; totTotal += v.total;
        return `
        <tr>
            <td>
                <span class="pd-customer-dot" style="background:${v.color}"></span>
                <strong>${pdEsc(v.title)}</strong>
                ${v.customer_name ? `<span style="color:var(--slate-400);font-size:11px;margin-left:6px;">${pdEsc(v.customer_name)}</span>` : ''}
            </td>
            <td class="is-right">${v.soll.toFixed(1)}</td>
            <td class="is-right">${v.ist.toFixed(1)}</td>
            <td class="is-right"><span class="pd-pill ${diffCls}">${diff > 0 ? '+' : ''}${diff.toFixed(1)}</span></td>
            <td class="is-right">${progress}%
                <span class="pd-bar-bg"><span class="pd-bar-fill ${barCls}" style="width:${Math.min(100, progress)}%"></span></span>
            </td>
            <td class="is-right">${v.done} / ${v.total}</td>
        </tr>`;
    }).join('');
    const totDiff = totIst - totSoll;
    const totalRow = `
        <tr class="pd-row-total">
            <td>Gesamt (${plans.length} Pläne)</td>
            <td class="is-right">${totSoll.toFixed(1)}</td>
            <td class="is-right">${totIst.toFixed(1)}</td>
            <td class="is-right"><span class="pd-pill ${totDiff > 0 ? 'is-pos' : (totDiff < 0 ? 'is-neg' : 'is-zero')}">${totDiff > 0 ? '+' : ''}${totDiff.toFixed(1)}</span></td>
            <td class="is-right">${totTotal > 0 ? Math.round(totDone/totTotal*100) : 0}%</td>
            <td class="is-right">${totDone} / ${totTotal}</td>
        </tr>`;
    document.getElementById('pd-body').innerHTML = `
        <table class="pd-table">
            <thead><tr>
                <th>Plan</th>
                <th class="is-right">Soll</th>
                <th class="is-right">Ist</th>
                <th class="is-right">Diff</th>
                <th class="is-right">Fortschritt</th>
                <th class="is-right">Aufgaben</th>
            </tr></thead>
            <tbody>${rows}${totalRow}</tbody>
        </table>`;
}

/* ===== Tab 4: Forecast-Heatmap ===== */
function pdRenderForecast() {
    const forecast = pdState.data.forecast;
    const months = Object.keys(forecast).sort();
    if (!months.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Kein Forecast — Pläne brauchen einen Zeitraum</div>';
        return;
    }
    const personSet = new Set();
    Object.values(forecast).forEach(m => Object.keys(m).forEach(p => personSet.add(p)));
    const persons = [...personSet].sort();
    if (!persons.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Kein Forecast — keine Personen zugewiesen</div>';
        return;
    }

    const monthLabel = ym => {
        const [y, m] = ym.split('-');
        return ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'][parseInt(m)-1] + ' ' + y.slice(2);
    };

    // Farbe + Inhalt: Auslastung = Soll / Monats-Kapazität
    // grün <80%, gelb 80–100%, rot >100% (überbucht)
    const cellContent = (val, cap) => {
        if (!val) return { html: '<span style="color:var(--slate-300);">—</span>', style: '' };
        const pct = cap > 0 ? Math.round((val / cap) * 100) : 0;
        let style = '';
        let warn = '';
        if (cap > 0) {
            if (pct > 100) {
                style = 'background:rgba(244, 63, 94, 0.18);color:var(--rose-800);font-weight:700;';
                warn = ' !';
            } else if (pct >= 80) {
                style = 'background:rgba(245, 158, 11, 0.16);color:var(--amber-800);font-weight:600;';
            } else {
                style = 'background:rgba(16, 185, 129, 0.10);color:var(--emerald-800);';
            }
        }
        const pctTxt = cap > 0 ? `<span style="font-size:10px;opacity:0.75;display:block;">${pct}%${warn}</span>` : '';
        return { html: `${val.toFixed(0)} h${pctTxt}`, style };
    };

    const headerRow = '<th style="text-align:left;background:var(--thoxan-50);">Person <small style="font-weight:400;color:var(--slate-400);">h/Mon</small></th>' + months.map(m => `<th>${monthLabel(m)}</th>`).join('');
    const personRows = persons.map(p => {
        const cap = pdState.data.capacity[p] || 0;
        let overbookedMonths = 0;
        const cells = months.map(m => {
            const cell = (forecast[m] && forecast[m][p]) ? forecast[m][p] : null;
            if (!cell) return '<td style="color:var(--slate-300);">—</td>';
            const c = cellContent(cell.soll, cap);
            if (cap > 0 && cell.soll > cap) overbookedMonths++;
            return `<td style="${c.style}" title="Soll ${cell.soll.toFixed(1)}h · Ist ${cell.ist.toFixed(1)}h · Kap ${(cap || 0).toFixed(0)}h/Mon">${c.html}</td>`;
        }).join('');
        const warnBadge = overbookedMonths > 0
            ? ` <span style="background:var(--rose-100);color:var(--rose-700);padding:1px 6px;border-radius:8px;font-size:9px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;" title="${overbookedMonths} Monat(e) überbucht">${overbookedMonths}× über</span>`
            : '';
        return `<tr><td>${pdEsc(p)} <span style="color:var(--slate-400);font-size:11px;font-weight:400;">${cap || '—'}</span>${warnBadge}</td>${cells}</tr>`;
    }).join('');

    const sumCap = persons.reduce((a, p) => a + (pdState.data.capacity[p] || 0), 0);
    const capRow = `<tr class="pd-row-cap"><td>Kapazität gesamt</td>` + months.map(_ =>
        `<td>${sumCap || '—'}</td>`).join('') + '</tr>';

    const availRow = `<tr><td>Verfügbar</td>` + months.map(m => {
        const used = persons.reduce((a, p) => a + ((forecast[m]?.[p]?.soll) || 0), 0);
        const free = sumCap - used;
        const cls = free >= 0 ? 'pd-row-avail-pos' : 'pd-row-avail-neg';
        return sumCap > 0 ? `<td class="${cls}">${(free >= 0 ? '+' : '') + free.toFixed(0)}</td>` : '<td>—</td>';
    }).join('') + '</tr>';

    document.getElementById('pd-body').innerHTML = `
        <div style="overflow-x:auto;">
            <table class="pd-forecast-table">
                <thead><tr>${headerRow}</tr></thead>
                <tbody>${personRows}${capRow}${availRow}</tbody>
            </table>
        </div>
        <div style="margin-top:10px;font-size:11px;color:var(--slate-400);line-height:1.5;">
            Werte = Soll-Stunden pro Person/Monat (Plan-Zeitraum proportional verteilt), darunter Auslastung in % der Monatskapazität.
            <span style="display:inline-block;width:8px;height:8px;background:rgba(16,185,129,0.5);border-radius:2px;vertical-align:middle;margin:0 2px;"></span>&lt;80%
            <span style="display:inline-block;width:8px;height:8px;background:rgba(245,158,11,0.5);border-radius:2px;vertical-align:middle;margin:0 2px 0 8px;"></span>80–100%
            <span style="display:inline-block;width:8px;height:8px;background:rgba(244,63,94,0.5);border-radius:2px;vertical-align:middle;margin:0 2px 0 8px;"></span>überbucht (mit „!").
            „Verfügbar" = Σ Kapazität − Σ Soll. Badge „N× über" am Personen-Namen zählt überbuchte Monate.
        </div>`;
}

/* ===== Tab 5: Erledigte ===== */
function pdRenderDone() {
    const tasks = pdState.data.done_tasks;
    if (!tasks.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Keine erledigten Aufgaben im Filter</div>';
        return;
    }
    const rows = tasks.map(t => `
        <tr>
            <td><strong>${pdEsc(t.description)}</strong></td>
            <td>${pdEsc(t.responsible || '—')}</td>
            <td class="is-right">${parseFloat(t.soll || 0).toFixed(1)}</td>
            <td class="is-right">${parseFloat(t.ist || 0).toFixed(1)}</td>
            <td>
                <span class="pd-customer-dot" style="background:${t.color}"></span>
                ${pdEsc(t.plan)}
            </td>
            <td>${pdEsc(t.deadline || '')}</td>
        </tr>`).join('');
    document.getElementById('pd-body').innerHTML = `
        <table class="pd-table">
            <thead><tr>
                <th>Aufgabe</th>
                <th>Verantwortlich</th>
                <th class="is-right">Soll</th>
                <th class="is-right">Ist</th>
                <th>Plan</th>
                <th>Deadline</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
}

/* ===== Tab 6: Soll/Ist Budget pro Kunde ===== */
function pdRenderBudget() {
    const byCust = {};
    Object.entries(pdState.data.by_plan).forEach(([id, v]) => {
        const key = v.customer_name || '— Kein Kunde —';
        if (!byCust[key]) byCust[key] = { color: v.color, soll: 0, ist: 0, plans: 0 };
        byCust[key].soll += v.soll;
        byCust[key].ist += v.ist;
        byCust[key].plans++;
    });
    const customers = Object.entries(byCust);
    if (!customers.length) {
        document.getElementById('pd-body').innerHTML = '<div class="pd-empty">Keine Daten</div>';
        return;
    }
    customers.sort((a, b) => b[1].soll - a[1].soll);
    let totSoll = 0, totIst = 0, totPlans = 0;
    const rows = customers.map(([name, v]) => {
        const diff = v.ist - v.soll;
        const cls = diff > 0 ? 'is-pos' : (diff < 0 ? 'is-neg' : 'is-zero');
        totSoll += v.soll; totIst += v.ist; totPlans += v.plans;
        return `
            <tr>
                <td><span class="pd-customer-dot" style="background:${v.color}"></span><strong>${pdEsc(name)}</strong></td>
                <td class="is-right">${v.plans}</td>
                <td class="is-right">${v.soll.toFixed(1)}</td>
                <td class="is-right">${v.ist.toFixed(1)}</td>
                <td class="is-right"><span class="pd-pill ${cls}">${diff > 0 ? '+' : ''}${diff.toFixed(1)}</span></td>
            </tr>`;
    }).join('');
    const totDiff = totIst - totSoll;
    const totalRow = `<tr class="pd-row-total">
        <td>Gesamt (${customers.length} Kunden)</td>
        <td class="is-right">${totPlans}</td>
        <td class="is-right">${totSoll.toFixed(1)}</td>
        <td class="is-right">${totIst.toFixed(1)}</td>
        <td class="is-right"><span class="pd-pill ${totDiff > 0 ? 'is-pos' : (totDiff < 0 ? 'is-neg' : 'is-zero')}">${totDiff > 0 ? '+' : ''}${totDiff.toFixed(1)}</span></td>
    </tr>`;
    document.getElementById('pd-body').innerHTML = `
        <table class="pd-table">
            <thead><tr>
                <th>Kunde</th>
                <th class="is-right">Pläne</th>
                <th class="is-right">Soll (h)</th>
                <th class="is-right">Ist (h)</th>
                <th class="is-right">Diff (h)</th>
            </tr></thead>
            <tbody>${rows}${totalRow}</tbody>
        </table>
        <div style="margin-top:10px;font-size:11px;color:var(--slate-400);">
            Detail-Budget pro Kunde im Plan-Editor → „Budget"-Button. Hier nur Aggregat aus Plan-Zeilen.
        </div>`;
}

/* ===== Kunden-Cockpit (echtes pp_customer_budget pro Kunde) ===== */
let pdCustomersCache = null;
async function pdRenderCustomers() {
    const year = new Date().getFullYear();
    const body = document.getElementById('pd-body');
    if (!pdCustomersCache || pdCustomersCache.year !== year) {
        body.innerHTML = '<div class="pd-empty">Lädt Kunden-Budgets…</div>';
        try {
            const r = await fetch('/api/v1/admin/projektplanner/budget/overview?year=' + year);
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            pdCustomersCache = j.data;
        } catch (e) {
            body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
            return;
        }
    }
    const d = pdCustomersCache;
    if (!d.customers.length) {
        body.innerHTML = `<div class="pd-empty">Noch keine Kunden-Budgets gepflegt für ${d.year}. Im Plan-Editor → „Budget"-Button → Monats-Soll-Werte eintragen.</div>`;
        return;
    }

    // Helper: Bar-Anzeige Soll vs Ist
    const bar = (soll, ist) => {
        if (soll <= 0) return '<span style="color:var(--slate-300);">kein Budget</span>';
        const pct = Math.min(150, Math.round((ist / soll) * 100));
        const w   = Math.min(100, pct);
        let cls = 'is-ok';
        if (pct > 100) cls = 'is-over';
        else if (pct >= 80) cls = 'is-warn';
        return `
            <div class="pdc-bar-wrap" title="${ist.toFixed(1)} h von ${soll.toFixed(1)} h verbraucht (${pct}%)">
                <div class="pdc-bar-fill ${cls}" style="width:${w}%;"></div>
                <span class="pdc-bar-text">${pct}%</span>
            </div>`;
    };

    const statusBadge = (s) => {
        const map = {
            over:        { txt: 'überzogen',    cls: 'pdc-st-over' },
            risk:        { txt: 'Risiko',       cls: 'pdc-st-risk' },
            under:       { txt: 'untergebucht', cls: 'pdc-st-under' },
            ok:          { txt: 'ok',           cls: 'pdc-st-ok' },
            'no-budget': { txt: 'kein Budget',  cls: 'pdc-st-none' },
        };
        const o = map[s] || map.ok;
        return `<span class="pdc-status ${o.cls}">${o.txt}</span>`;
    };

    const rows = d.customers.map(c => {
        const planLink = c.plan_count > 0
            ? `<a href="/admin/projektplanner?customer=${c.customer_id}" style="color:var(--thoxan-700);text-decoration:none;">${c.plan_count} Plan${c.plan_count > 1 ? 'e' : ''} →</a>`
            : '<span style="color:var(--slate-400);">—</span>';
        return `
        <tr>
            <td>
                <span class="pd-customer-dot" style="background:${c.color}"></span>
                <strong>${pdEsc(c.name)}</strong>
                ${c.abbreviation ? `<span style="color:var(--slate-400);font-size:11px;margin-left:6px;">${pdEsc(c.abbreviation)}</span>` : ''}
            </td>
            <td>${statusBadge(c.status)}</td>
            <td class="is-right">${c.soll_h > 0 ? c.soll_h.toFixed(1) : '—'}</td>
            <td class="is-right">${c.ist_h_ytd.toFixed(1)}</td>
            <td class="is-right">${c.planned_h.toFixed(1)}</td>
            <td class="is-right">${c.soll_h > 0 ? ((c.remaining_h >= 0 ? '+' : '') + c.remaining_h.toFixed(1)) : '—'}</td>
            <td style="min-width:180px;">${bar(c.soll_h, c.ist_h_ytd)}</td>
            <td class="is-right">${c.monthly_avg_h.toFixed(1)}</td>
            <td>${planLink}</td>
        </tr>`;
    }).join('');

    body.innerHTML = `
        <div style="margin-bottom:10px;font-size: var(--d-fs-sm);color:var(--slate-600);">
            <strong>Jahr ${d.year}</strong> · ${d.months_elapsed} Monate vorbei · ${d.months_remaining} Monate Rest ·
            <strong>${d.customers.length}</strong> Kunden im Cockpit (sortiert: Risiken oben)
        </div>
        <style>
            .pdc-bar-wrap { position:relative; background:var(--slate-100); border-radius:10px; height:18px; overflow:hidden; }
            .pdc-bar-fill { position:absolute; left:0; top:0; height:100%; border-radius:10px; transition:width 0.2s; }
            .pdc-bar-fill.is-ok   { background:var(--emerald-400); }
            .pdc-bar-fill.is-warn { background:var(--amber-400); }
            .pdc-bar-fill.is-over { background:var(--rose-400); }
            .pdc-bar-text { position:absolute; left:0; right:0; top:0; bottom:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--slate-800); }
            .pdc-status { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
            .pdc-st-over  { background:var(--rose-100);    color:var(--rose-800); }
            .pdc-st-risk  { background:var(--amber-100);   color:var(--amber-800); }
            .pdc-st-under { background:var(--thoxan-100);  color:var(--thoxan-800); }
            .pdc-st-ok    { background:var(--emerald-100); color:var(--emerald-800); }
            .pdc-st-none  { background:var(--slate-100);   color:var(--slate-600); }
        </style>
        <table class="pd-table">
            <thead><tr>
                <th>Kunde</th>
                <th>Status</th>
                <th class="is-right">Soll/Jahr (h)</th>
                <th class="is-right">Ist YTD (h)</th>
                <th class="is-right">Geplant offen (h)</th>
                <th class="is-right">Rest (h)</th>
                <th>Verbrauch</th>
                <th class="is-right">Ø h/Mon</th>
                <th>Pläne</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <div style="margin-top:10px;font-size:11px;color:var(--slate-400);line-height:1.5;">
            <strong>Status:</strong> <em>überzogen</em> = Ist YTD &gt; Soll/Jahr · <em>Risiko</em> = aktueller Ist + Geplant offen sprengt Jahresbudget ·
            <em>untergebucht</em> = liegt &gt; 20pp unter dem proportionalen Soll · <em>ok</em> = im Plan · <em>kein Budget</em> = keine Soll-Werte hinterlegt.
            <br>Soll-Werte pflegen: Plan-Editor → „⋯ Mehr" → „Budget" → Monats-Soll-TS eintragen.
        </div>`;
}

// Cache leeren bei Filter-/Tab-Wechsel ist nicht nötig — Year-basiert
function pdInvalidateCustomersCache() { pdCustomersCache = null; }

/* ===== Plan-Status-Trend Widget ===== */
async function pdRenderPlanStatus() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt Pläne…</div>';
    try {
        const [res1, res2] = await Promise.all([
            fetch('/api/v1/admin/projektplanner/plans').then(r => r.json()),
            fetch('/api/v1/admin/projektplanner/plans?state=2').then(r => r.json()),
        ]);
        const active = (res1.data && res1.data.plans) || [];
        const archived = (res2.data && res2.data.plans) || [];
        const buckets = { entwurf: [], aktiv: [], einzelprojekt: [], reporting: [], abgeschlossen: [], archiviert: archived };
        active.forEach(p => { const k = (p.plan_status || 'aktiv'); if (buckets[k]) buckets[k].push(p); });
        const labels = { entwurf:'Entwurf', aktiv:'Aktiv', einzelprojekt:'Einzelprojekt', reporting:'Reporting', abgeschlossen:'Abgeschlossen', archiviert:'Archiviert' };
        const colors = { entwurf:'var(--slate-500)', aktiv:'var(--emerald-600)', einzelprojekt:'#7e22ce', reporting:'var(--amber-600)', abgeschlossen:'var(--slate-500)', archiviert:'var(--slate-400)' };
        const total = Object.values(buckets).reduce((a,b) => a + b.length, 0);
        body.innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:18px;">
                ${Object.entries(buckets).map(([k, list]) => {
                    const pct = total ? Math.round((list.length / total) * 100) : 0;
                    return `<div style="background:var(--slate-50);border-radius:10px;padding:14px;">
                        <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;">${labels[k]}</div>
                        <div style="font-size:1.7rem;font-weight:800;color:${colors[k]};font-family:ui-monospace,monospace;">${list.length}</div>
                        <div style="font-size:11px;color:var(--slate-400);">${pct}% der Pläne</div>
                    </div>`;
                }).join('')}
            </div>
            <h3 style="margin:18px 0 8px;font-size:0.95rem;color:var(--slate-700);">Aktive Pläne (zuletzt geändert)</h3>
            <table class="pd-table">
                <thead><tr><th>Titel</th><th>Kunde</th><th>Status</th><th>Zeitraum</th><th class="is-right">Zeilen</th></tr></thead>
                <tbody>
                ${active.slice().sort((a,b) => (b.updated_at||'').localeCompare(a.updated_at||'')).map(p => `
                    <tr>
                        <td><strong>${pdEsc(p.title)}</strong></td>
                        <td>${pdEsc(p.customer_name || '—')}</td>
                        <td><span style="background:var(--slate-100);padding:2px 8px;border-radius:8px;font-size:10px;text-transform:uppercase;font-weight:700;color:${colors[p.plan_status]||'var(--slate-600)'};">${p.plan_status}</span></td>
                        <td style="font-size:11px;color:var(--slate-500);">${p.period_from||'—'} – ${p.period_to||'—'}</td>
                        <td class="is-right">${p.row_count||0}</td>
                    </tr>`).join('')}
                </tbody>
            </table>`;
    } catch (e) { body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>'; }
}

/* ===== Meine offenen Aufgaben über alle Pläne ===== */
async function pdRenderMyOpen() {
    const body = document.getElementById('pd-body');
    const me = window.PP_CURRENT_USER;
    if (!me || !me.name) { body.innerHTML = '<div class="pd-empty">Kein eingeloggter User erkannt.</div>'; return; }
    body.innerHTML = '<div class="pd-empty">Lädt Deine offenen Aufgaben…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/my-open-tasks');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const tasks = j.data.tasks || [];
        if (!tasks.length) {
            body.innerHTML = `<div class="pd-empty">Keine offenen Aufgaben für <strong>${pdEsc(me.name)}</strong>${me.nickname ? ` (${pdEsc(me.nickname)})` : ''}. 🎉</div>`;
            return;
        }
        const byPlan = {};
        tasks.forEach(t => {
            const k = t.plan_id;
            if (!byPlan[k]) byPlan[k] = { plan_title: t.plan_title, customer_name: t.customer_name, color: t.customer_color, tasks: [] };
            byPlan[k].tasks.push(t);
        });
        const totalSoll = tasks.reduce((a,t) => a + parseFloat(t.planned_hours||0), 0);
        const totalIst = tasks.reduce((a,t) => a + parseFloat(t.ist_hours||0), 0);
        body.innerHTML = `
            <div style="background:var(--thoxan-50);border:1px solid var(--thoxan-200);border-radius:8px;padding:12px;margin-bottom:14px;font-size:0.875rem;color:var(--thoxan-800);">
                <strong>${pdEsc(me.name)}</strong>${me.nickname ? ` (${pdEsc(me.nickname)})` : ''} ·
                ${tasks.length} offene Aufgaben über ${Object.keys(byPlan).length} Pläne ·
                Σ Soll ${totalSoll.toFixed(1)} h · Σ Ist ${totalIst.toFixed(1)} h
            </div>
            ${Object.values(byPlan).map(p => `
                <div style="margin-bottom:18px;">
                    <h3 style="margin:0 0 6px;font-size:0.95rem;color:var(--slate-700);">
                        <span class="pd-customer-dot" style="background:${p.color}"></span>
                        ${pdEsc(p.plan_title)}
                        <small style="font-weight:400;color:var(--slate-500);"> · ${pdEsc(p.customer_name||'—')}</small>
                        <span style="float:right;font-weight:400;font-size:11px;color:var(--slate-400);">${p.tasks.length} Aufgaben</span>
                    </h3>
                    <table class="pd-table" style="font-size:0.85rem;">
                        <thead><tr><th>Beschreibung</th><th>Rolle</th><th class="is-right">Soll</th><th class="is-right">Ist</th><th>Deadline</th></tr></thead>
                        <tbody>${p.tasks.map(t => `<tr>
                            <td>${pdEsc(String(t.description||'').substring(0,200))}</td>
                            <td>${t.is_lead ? '<span class="pd-role-badge is-lead">Lead</span>' : '<span class="pd-role-badge is-resp">Umsetzung</span>'}</td>
                            <td class="is-right">${parseFloat(t.planned_hours||0).toFixed(1)}</td>
                            <td class="is-right">${parseFloat(t.ist_hours||0).toFixed(1)}</td>
                            <td style="font-size:11px;">${pdEsc(t.deadline||'')}</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>`).join('')}`;
    } catch (e) { body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>'; }
}

function pdLevenshtein(a, b) {
    if (a === b) return 0;
    const al = a.length, bl = b.length;
    if (!al) return bl; if (!bl) return al;
    const dp = Array.from({length: al + 1}, () => new Array(bl + 1).fill(0));
    for (let i = 0; i <= al; i++) dp[i][0] = i;
    for (let j = 0; j <= bl; j++) dp[0][j] = j;
    for (let i = 1; i <= al; i++) for (let j = 1; j <= bl; j++) {
        const cost = a[i-1] === b[j-1] ? 0 : 1;
        dp[i][j] = Math.min(dp[i-1][j] + 1, dp[i][j-1] + 1, dp[i-1][j-1] + cost);
    }
    return dp[al][bl];
}
function pdDetectDuplicates(names) {
    const result = [];
    const seen = new Set();
    for (let i = 0; i < names.length; i++) {
        const a = (names[i] || '').toLowerCase();
        for (let j = i + 1; j < names.length; j++) {
            const b = (names[j] || '').toLowerCase();
            if (!a || !b) continue;
            const key = a < b ? a + '|' + b : b + '|' + a;
            if (seen.has(key)) continue;
            // Substring match
            if (a.includes(b) || b.includes(a)) { result.push([names[i], names[j]]); seen.add(key); continue; }
            // Levenshtein bei kurzer Distanz
            const dist = pdLevenshtein(a, b);
            const maxLen = Math.max(a.length, b.length);
            if (maxLen >= 4 && dist <= Math.max(1, Math.floor(maxLen * 0.2))) {
                result.push([names[i], names[j]]); seen.add(key);
            }
        }
    }
    return result.slice(0, 10);
}

/* ===== Projektübersicht-Matrix (löst die alte Excel ab) ===== */
async function pdRenderMatrix() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    const year = (new Date()).getFullYear();
    try {
        const j = await fetch('/api/v1/admin/projektplanner/budget?action=matrix&year=' + year).then(r => r.json());
        if (!j.success) throw new Error(j.message);
        const d = j.data;
        const monthNames = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
        const modelLabels = d.billing_models || {};
        const fmt = (v) => v == null ? '' : (Number(v).toFixed(2).replace(/\.?0+$/, '').replace('.', ',')) || '0';

        const rows = (d.customers || []).map(c => {
            const monthCells = c.months.map(m => {
                const diff = (m.abg != null) ? (m.abg - m.ist) : null;
                const cls = diff == null ? '' : (diff > 0.25 ? 'is-pos' : (diff < -0.25 ? 'is-neg' : ''));
                return `
                <td class="pd-mx-cell ${cls}" title="Soll ${fmt(m.soll)} · Abger. ${fmt(m.abg)} · Ist ${fmt(m.ist)} TS">
                    <div class="pd-mx-cell-soll">${m.soll > 0 ? fmt(m.soll) : ''}</div>
                    ${m.abg != null ? `<div class="pd-mx-cell-abg">${fmt(m.abg)}</div>` : '<div class="pd-mx-cell-abg pd-mx-empty">—</div>'}
                    ${m.ist > 0 ? `<div class="pd-mx-cell-ist">${fmt(m.ist)}</div>` : ''}
                </td>`;
            }).join('');
            const totalDiff = c.total.diff_ts;
            const totalCls = totalDiff > 0.25 ? 'is-pos' : (totalDiff < -0.25 ? 'is-neg' : '');
            const modelLabel = c.billing_model && modelLabels[c.billing_model] ? modelLabels[c.billing_model].label : '';
            const tsPerMonth = c.ts_per_month != null ? `${fmt(c.ts_per_month)} TS/Mon` : '';
            return `
            <tr onclick="ppOpenBudgetExternal(${c.customer_id})" title="${pdEsc(c.name)} öffnen">
                <td class="pd-mx-name">
                    <div style="display:flex;align-items:center;gap:8px;">
                        ${c.logo_path
                            ? `<img src="/uploads/customers/logos/${pdEsc((c.logo_path).split('/').pop())}" class="pd-mx-logo">`
                            : `<span class="pd-mx-abbr" style="background:${c.color}22;color:${c.color};">${pdEsc(c.abbreviation || c.name.substr(0,3))}</span>`}
                        <div style="min-width:0;">
                            <div style="font-weight:600;color:var(--slate-800);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${pdEsc(c.name)}</div>
                            <div class="pd-mx-meta">${tsPerMonth}${tsPerMonth && modelLabel ? ' · ' : ''}${pdEsc(modelLabel)}</div>
                        </div>
                    </div>
                </td>
                ${monthCells}
                <td class="pd-mx-total">${fmt(c.total.soll_ts)}</td>
                <td class="pd-mx-total">${c.total.abgerechnet_ts > 0 ? fmt(c.total.abgerechnet_ts) : ''}</td>
                <td class="pd-mx-total">${fmt(c.total.ist_ts)}</td>
                <td class="pd-mx-total ${totalCls}">${totalDiff > 0 ? '+' : ''}${fmt(totalDiff)}</td>
            </tr>`;
        }).join('');

        body.innerHTML = `
        <div class="pd-matrix-wrap">
            <div class="pd-matrix-head">
                <h3 style="margin:0;">Projektübersicht ${d.year}</h3>
                <div class="pd-matrix-legend">
                    <span><b>Soll</b> oben · <b>Abgerechnet</b> Mitte · <b>Ist</b> unten · alles in TS</span>
                </div>
            </div>
            <div class="pd-matrix-scroll">
            <table class="pd-matrix">
                <thead>
                    <tr>
                        <th class="pd-mx-name-h">Kunde</th>
                        ${monthNames.map((n, i) => `<th class="pd-mx-month-h ${i % 3 === 2 ? 'is-q-end' : ''}">${n}</th>`).join('')}
                        <th class="pd-mx-total-h">Soll</th>
                        <th class="pd-mx-total-h">Abger.</th>
                        <th class="pd-mx-total-h">Ist</th>
                        <th class="pd-mx-total-h">Diff</th>
                    </tr>
                </thead>
                <tbody>${rows || `<tr><td colspan="17" style="padding:40px;text-align:center;color:var(--slate-400);">Noch keine Kunden mit Budget oder Plänen für ${d.year}.</td></tr>`}</tbody>
            </table>
            </div>
        </div>`;
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

/** Öffnet die Budget-Lightbox auf der Projektplanner-Hauptseite. Vom Dashboard
 *  navigieren wir dorthin und triggern das Modal via URL-Hash. */
function ppOpenBudgetExternal(customerId) {
    window.location.href = `/admin/projektplanner#budget-customer=${customerId}`;
}

/* ===== Pläne (Liste) — Alle laufenden Pläne in einer Tabelle ===== */
let pdPlansCache = null;
// Default = Risiko (Fokus auf brennende Themen). Persistiert in localStorage —
// alphabetisch ist nur auf manuelle Anfrage, beim naechsten Besuch erneut Risiko.
let pdPlansSort = (() => {
    try { return localStorage.getItem('pd_plans_sort') || 'risk'; }
    catch (e) { return 'risk'; }
})();

async function pdLoadPlansWidgets(sort) {
    const params = new URLSearchParams();
    if (sort) params.set('sort', sort);
    const r = await fetch('/api/v1/admin/projektplanner/widgets?' + params.toString());
    const j = await r.json();
    if (!j.success) throw new Error(j.message || 'Fehler beim Laden');
    return j.data;
}

async function pdRenderPlanList() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    try {
        if (!pdPlansCache) pdPlansCache = await pdLoadPlansWidgets(pdPlansSort);
        const items = pdPlansCache.items || [];
        const fmt = (v) => {
            if (v == null) return '';
            const s = Number(v).toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
            return s || '0';
        };
        const rows = items.map(it => {
            const spielraumCls = it.spielraum_h > 0.01 ? 'is-pos' : (it.spielraum_h < -0.01 ? 'is-neg' : '');
            const planUrl = '/admin/projektplanner/plan/' + it.plan_id;
            const modus = it.risiko_modus || 'auto';
            const flagIcon  = ({ eskaliert: '🔥', gruen: '✓', nicht_relevant: '⏸' })[modus] || '';
            const flagTitle = ({ eskaliert: 'Brennt', gruen: 'Erledigt', nicht_relevant: 'Läuft mit' })[modus] || '';
            const rowBg = ({ eskaliert: '#fef2f2', gruen: '#f0fdf4', nicht_relevant: '#f8fafc' })[modus] || '';
            const rowOpacity = modus === 'nicht_relevant' ? '0.6' : '1';
            return `
            <tr class="pd-tile-risiko-${modus}" data-plan-id="${it.plan_id}" data-risiko-modus="${modus}" data-risiko-notiz="${pdEsc(it.risiko_notiz || '')}"
                onclick="window.location.href='${planUrl}'"
                oncontextmenu="pdRisikoContextMenu(event, ${it.plan_id}, '${modus}'); return false;"
                style="cursor:pointer;${rowBg ? `background:${rowBg};` : ''}opacity:${rowOpacity};"
                ${it.risiko_notiz ? `title="${pdEsc(it.risiko_notiz)}"` : ''}>
                <td><span class="pd-mx-abbr" style="background:#e2e8f022;color:var(--slate-700);">${pdEsc(it.customer_abbr || '?')}</span></td>
                <td>${pdEsc(it.customer_name)}</td>
                <td style="font-weight:600;">${flagIcon ? `<span title="${flagTitle}${it.risiko_notiz ? ' — ' + pdEsc(it.risiko_notiz) : ''}" style="margin-right:6px;">${flagIcon}</span>` : ''}${pdEsc(it.plan_title)}</td>
                <td class="is-right">${fmt(it.ist_h)} h</td>
                <td class="is-right">${fmt(it.planned_h)} h</td>
                <td class="is-right">${fmt(it.soll_h)} h</td>
                <td class="is-right ${spielraumCls}" style="font-weight:700;">${it.spielraum_h > 0 ? '+' : ''}${fmt(it.spielraum_h)} h</td>
                <td class="is-right">${it.progress_pct}%</td>
            </tr>`;
        }).join('');

        body.innerHTML = `
        <div class="pd-plans-head">
            <h3 style="margin:0;">Alle laufenden Pläne (${items.length})</h3>
            <div style="display:flex;gap:12px;align-items:center;">
                <span style="font-size:0.7rem;color:#94a3b8;">Rechtsklick auf Zeile → Risiko ändern</span>
                <div class="pd-sort-toggle">
                    <button class="pd-sort-btn ${pdPlansSort === 'alpha' ? 'is-active' : ''}" onclick="pdSetPlansSort('alpha')">Alphabetisch</button>
                    <button class="pd-sort-btn ${pdPlansSort === 'risk' ? 'is-active' : ''}" onclick="pdSetPlansSort('risk')">Nach Risiko</button>
                </div>
            </div>
        </div>
        <div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);overflow:auto;">
            <table class="pd-plans-table">
                <thead><tr>
                    <th>Kürzel</th><th>Kunde</th><th>Plan</th>
                    <th class="is-right">Ist</th>
                    <th class="is-right">Geplant</th>
                    <th class="is-right">Soll</th>
                    <th class="is-right">Spielraum</th>
                    <th class="is-right">Done</th>
                </tr></thead>
                <tbody>${rows || `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--slate-400);">Keine aktiven Pläne.</td></tr>`}</tbody>
            </table>
        </div>`;
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

/* ===== Pläne (Kacheln) — Widget-Snapshot pro Plan ===== */
async function pdRenderPlanTiles() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    try {
        if (!pdPlansCache) pdPlansCache = await pdLoadPlansWidgets(pdPlansSort);
        const items = pdPlansCache.items || [];
        // CSS einmalig einbinden (ist im Server-CSS-String)
        if (!document.getElementById('pp-widget-css')) {
            const tmp = document.createElement('div');
            tmp.innerHTML = pdPlansCache.css || '';
            const styleEl = tmp.querySelector('style');
            if (styleEl) { styleEl.id = 'pp-widget-css'; document.head.appendChild(styleEl); }
        }
        const tiles = items.map(it => `
            <div class="pd-tile pd-tile-risiko-${it.risiko_modus || 'auto'}"
                 data-plan-id="${it.plan_id}"
                 data-risiko-modus="${it.risiko_modus || 'auto'}"
                 data-risiko-notiz="${pdEsc(it.risiko_notiz || '')}"
                 oncontextmenu="pdRisikoContextMenu(event, ${it.plan_id}, '${it.risiko_modus || 'auto'}'); return false;"
                 ${it.risiko_notiz ? `title="${pdEsc(it.risiko_notiz)}"` : ''}>
                ${it.risiko_modus && it.risiko_modus !== 'auto' ? `<span class="pd-tile-flag pd-tile-flag-${it.risiko_modus}" title="${({eskaliert:'Brennt',gruen:'Erledigt',nicht_relevant:'Läuft mit'})[it.risiko_modus] || ''}${it.risiko_notiz ? ' — ' + pdEsc(it.risiko_notiz) : ''}">${({eskaliert:'🔥',gruen:'✓',nicht_relevant:'⏸'})[it.risiko_modus]}</span>` : ''}
                ${it.html}
            </div>`).join('');
        body.innerHTML = `
        <div class="pd-plans-head">
            <h3 style="margin:0;">Aktive Pläne (${items.length})</h3>
            <div style="display:flex;gap:12px;align-items:center;">
                <span style="font-size:0.7rem;color:#94a3b8;">Rechtsklick auf Kachel → Risiko ändern</span>
                <div class="pd-sort-toggle">
                    <button class="pd-sort-btn ${pdPlansSort === 'alpha' ? 'is-active' : ''}" onclick="pdSetPlansSort('alpha')">Alphabetisch</button>
                    <button class="pd-sort-btn ${pdPlansSort === 'risk' ? 'is-active' : ''}" onclick="pdSetPlansSort('risk')">Nach Risiko</button>
                </div>
            </div>
        </div>
        <div class="pd-tiles-grid">${tiles || '<div class="pd-empty">Keine aktiven Pläne.</div>'}</div>`;
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

/* ===== Risiko-Steuerung per Rechtsklick auf Plan-Kachel ===== */
function pdRisikoContextMenu(event, planId, aktuellerModus) {
    event.preventDefault();
    // Bestehendes Menue entfernen
    document.querySelectorAll('.pd-risiko-menu').forEach(m => m.remove());

    const optionen = [
        { key: 'auto',           label: 'In Arbeit', icon: '⚙', color: '#64748b' },
        { key: 'eskaliert',      label: 'Brennt',    icon: '🔥', color: '#dc2626' },
        { key: 'gruen',          label: 'Erledigt',  icon: '✓', color: '#047857' },
        { key: 'nicht_relevant', label: 'Läuft mit', icon: '⏸', color: '#475569' },
    ];

    const menu = document.createElement('div');
    menu.className = 'pd-risiko-menu';
    menu.style.cssText = `position:fixed;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 10px 25px rgba(0,0,0,0.15);z-index:1100;min-width:220px;padding:4px 0;`;
    menu.innerHTML = `
        <div style="padding:6px 12px;font-size:0.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Risiko-Steuerung</div>
        ${optionen.map(o => `
            <button onclick="pdSetzeRisiko(${planId}, '${o.key}')"
                    style="display:flex;align-items:center;gap:10px;width:100%;padding:7px 12px;border:none;background:${o.key === aktuellerModus ? '#f1f5f9' : 'transparent'};color:${o.color};cursor:pointer;font-size:0.85rem;text-align:left;font-family:inherit;"
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='${o.key === aktuellerModus ? '#f1f5f9' : 'transparent'}'">
                <span style="font-size:1rem;">${o.icon}</span>
                <span style="flex:1;">${o.label}</span>
                ${o.key === aktuellerModus ? '<span style="color:#94a3b8;font-size:0.75rem;">aktiv</span>' : ''}
            </button>
        `).join('')}
        <div style="border-top:1px solid #f1f5f9;margin-top:4px;padding:6px 12px 4px;">
            <button onclick="pdRisikoMitNotiz(${planId})" style="background:none;border:none;color:#0369a1;cursor:pointer;font-size:0.75rem;padding:0;font-family:inherit;">
                + Mit Begründung…
            </button>
        </div>`;

    document.body.appendChild(menu);
    // Position innerhalb Viewport halten
    const rect = menu.getBoundingClientRect();
    const px = (event.clientX + rect.width  > window.innerWidth)  ? event.clientX - rect.width  : event.clientX;
    const py = (event.clientY + rect.height > window.innerHeight) ? event.clientY - rect.height : event.clientY;
    menu.style.left = px + 'px';
    menu.style.top  = py + 'px';

    // Schliessen bei Klick ausserhalb
    setTimeout(() => {
        document.addEventListener('click', function once() {
            menu.remove();
            document.removeEventListener('click', once);
        });
    }, 50);
}

async function pdSetzeRisiko(planId, modus, notiz) {
    // Plan-Titel fuer den Toast aus dem Cache holen (vor dem Reload)
    const planTitel = ((pdPlansCache?.items || []).find(it => it.plan_id === planId) || {}).plan_title || 'Plan';
    const label = ({ auto: '⚙ In Arbeit', eskaliert: '🔥 Brennt', gruen: '✓ Erledigt', nicht_relevant: '⏸ Läuft mit' })[modus] || modus;
    try {
        const r = await fetch('/api/v1/projektplanner/plan-risiko', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: planId, modus, notiz: notiz || '' })
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');

        // Wenn der User aktuell alphabetisch sortiert UND einen manuellen Modus setzt,
        // automatisch in die Risiko-Sortierung wechseln — sonst sieht er die
        // Re-Sortierung nicht und denkt der Klick hat nichts bewirkt.
        if (pdPlansSort === 'alpha' && modus !== 'auto') {
            pdPlansSort = 'risk';
        }

        // Cache leeren, dann neu rendern — Sortierung passt sich automatisch an
        pdPlansCache = null;
        await pdRenderTab();
        App.showNotification(`${planTitel}: ${label}`, 'success');
    } catch (e) {
        App.showNotification('Risiko nicht gespeichert: ' + e.message, 'error');
    }
}

function pdRisikoMitNotiz(planId) {
    document.querySelectorAll('.pd-risiko-menu').forEach(m => m.remove());
    const aktuell = (document.querySelector(`.pd-tile[data-plan-id="${planId}"]`)?.dataset || {});
    const modus = prompt('Stufe (auto / eskaliert / gruen / nicht_relevant):', aktuell.risikoModus || 'auto');
    if (!modus) return;
    const notiz = prompt('Begründung (optional):', aktuell.risikoNotiz || '');
    if (notiz === null) return;
    pdSetzeRisiko(planId, modus, notiz);
}

async function pdSetPlansSort(sort) {
    pdPlansSort = sort;
    try { localStorage.setItem('pd_plans_sort', sort); } catch (e) {}
    pdPlansCache = await pdLoadPlansWidgets(sort);
    pdRenderTab();
}
function pdSetPlansView(view) {
    pdState.plansView = view;
    pdRenderTab();
}

/* ===== Cockpit — KPIs + Top-5-Risiken ===== */
async function pdRenderCockpit() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    try {
        if (!pdPlansCache) pdPlansCache = await pdLoadPlansWidgets('risk');
        const items = pdPlansCache.items || [];
        const fmt = (v) => {
            if (v == null) return '0';
            const s = Number(v).toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
            return s || '0';
        };
        // Health-Score pro Plan: Kombi aus Spielraum + Time-vs-Progress.
        // Manuelle Risiko-Stufe ueberschreibt: eskaliert -> red, gruen -> green,
        // nicht_relevant -> wird aus der Risiko-Liste komplett ausgeblendet.
        const health = items.map(it => {
            let h;
            switch (it.risiko_modus) {
                case 'eskaliert':      h = 'red';    break;
                case 'gruen':          h = 'green';  break;
                case 'nicht_relevant': h = 'neutral'; break;
                default: {
                    const risk = it.spielraum_h < -0.1 || (it.progress_pct < 50 && it.risk_score_auto < -0.1);
                    const warn = it.spielraum_h < 0   || (it.progress_pct < 30 && it.risk_score_auto < 0);
                    h = risk ? 'red' : (warn ? 'yellow' : 'green');
                }
            }
            return { ...it, health: h };
        });
        const aktivePlaene  = health.length;
        const riskCount     = health.filter(p => p.health === 'red').length;
        const warnCount     = health.filter(p => p.health === 'yellow').length;
        const okCount       = health.filter(p => p.health === 'green').length;
        const sumPlanned    = health.reduce((a, p) => a + (p.planned_h || 0), 0);
        const sumIst        = health.reduce((a, p) => a + (p.ist_h || 0), 0);
        const sumSoll       = health.reduce((a, p) => a + (p.soll_h || 0), 0);

        // Top 5 Risiken: nutzt den (ggf. manuell uebersteuerten) risk_score aus
        // dem Backend. Plaene mit 'nicht_relevant' werden ausgeblendet.
        const top5 = health
            .filter(p => p.risiko_modus !== 'nicht_relevant' && p.risiko_modus !== 'gruen' && p.health !== 'green')
            .sort((a, b) => a.risk_score - b.risk_score)
            .slice(0, 5);
        const riskRows = top5.length === 0
            ? `<div class="pd-empty" style="padding:30px;">Keine kritischen Pläne 🎉</div>`
            : top5.map(p => {
                const planUrl = '/admin/projektplanner/plan/' + p.plan_id;
                let reason = [];
                if (p.risiko_modus === 'eskaliert') {
                    reason.push('🔥 Manuell eskaliert' + (p.risiko_notiz ? ': ' + p.risiko_notiz : ''));
                } else {
                    if (p.spielraum_h < -0.01) reason.push(`Spielraum ${fmt(p.spielraum_h)} h`);
                    if (p.progress_pct < 50)   reason.push(`Nur ${p.progress_pct}% erledigt`);
                    if (reason.length === 0)   reason.push('Achtsamkeit empfohlen');
                }
                return `
                <a class="pd-risk-row" href="${planUrl}">
                    <span class="pd-health-dot is-${p.health}"></span>
                    <div class="pd-risk-meta">
                        <div class="pd-risk-title">${pdEsc(p.customer_abbr)} · ${pdEsc(p.plan_title)}</div>
                        <div class="pd-risk-reason">${pdEsc(reason.join(' · '))}</div>
                    </div>
                    <div class="pd-risk-nums">
                        <div><strong>${fmt(p.ist_h)}h</strong> / <span style="color:var(--slate-400);">${fmt(p.soll_h)}h</span></div>
                        <div class="pd-risk-progress">${p.progress_pct}%</div>
                    </div>
                </a>`;
            }).join('');

        body.innerHTML = `
        <div class="pd-cockpit-kpis">
            <div class="pd-kpi-card">
                <div class="pd-kpi-label">Aktive Pläne</div>
                <div class="pd-kpi-value">${aktivePlaene}</div>
                <div class="pd-kpi-sub">
                    <span class="pd-health-dot is-green"></span>${okCount} ok
                    <span class="pd-health-dot is-yellow" style="margin-left:8px;"></span>${warnCount} watch
                    <span class="pd-health-dot is-red" style="margin-left:8px;"></span>${riskCount} kritisch
                </div>
            </div>
            <div class="pd-kpi-card">
                <div class="pd-kpi-label">Σ Geplant</div>
                <div class="pd-kpi-value">${fmt(sumPlanned)}<span class="pd-kpi-u"> h</span></div>
                <div class="pd-kpi-sub">vs. Soll ${fmt(sumSoll)} h</div>
            </div>
            <div class="pd-kpi-card">
                <div class="pd-kpi-label">Σ Geleistet</div>
                <div class="pd-kpi-value">${fmt(sumIst)}<span class="pd-kpi-u"> h</span></div>
                <div class="pd-kpi-sub">${sumPlanned > 0 ? Math.round(sumIst / sumPlanned * 100) : 0}% des Geplanten</div>
            </div>
            <div class="pd-kpi-card ${riskCount > 0 ? 'is-warn' : ''}">
                <div class="pd-kpi-label">Pläne in Gefahr</div>
                <div class="pd-kpi-value">${riskCount}</div>
                <div class="pd-kpi-sub">${riskCount > 0 ? 'Sieh unten' : 'alles im grünen Bereich'}</div>
            </div>
        </div>
        <div class="pd-section">
            <div class="pd-section-head">
                <h3 style="margin:0;">🔥 Brennende Themen</h3>
                <a href="#plans" onclick="pdSetTab('plans');return false;" class="pd-section-link">Alle Pläne →</a>
            </div>
            <div class="pd-risk-list">${riskRows}</div>
        </div>`;
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

/* ===== Pläne (Kombi: Toggle Kacheln/Liste) ===== */
function pdFilterPlans(items) {
    const f = pdState.plansFilter;
    const q = (f.suche || '').trim().toLowerCase();
    return items.filter(it => {
        if (f.risiko && f.risiko !== 'alle') {
            const m = it.risiko_modus || 'auto';
            if (m !== f.risiko) return false;
        }
        if (f.kunde && f.kunde !== 'alle') {
            if (String(it.customer_id || '') !== String(f.kunde)) return false;
        }
        if (q) {
            const hay = [it.plan_title, it.customer_name, it.customer_abbr, it.risiko_notiz]
                .filter(Boolean).join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
}
function pdSetPlansSuche(v) {
    pdState.plansFilter.suche = v;
    pdRenderPlansContent();
}
function pdSetPlansRisiko(v) {
    pdState.plansFilter.risiko = v;
    pdRenderPlansContent();
}
function pdSetPlansKunde(v) {
    pdState.plansFilter.kunde = v;
    pdRenderPlansContent();
}
function pdResetPlansFilter() {
    pdState.plansFilter = { suche: '', risiko: 'alle', kunde: 'alle' };
    pdRenderPlansTab();
}
function pdTogglePlansColSort(col) {
    const cur = pdState.plansColSort;
    if (cur.col === col) {
        if (cur.dir === 'asc') { cur.dir = 'desc'; }
        else { cur.col = null; cur.dir = 'asc'; }
    } else {
        cur.col = col;
        cur.dir = 'asc';
    }
    pdRenderPlansContent();
}
function pdSortPlansBy(items) {
    const s = pdState.plansColSort;
    if (!s.col) return items;
    const numericCols = new Set(['ist_h', 'planned_h', 'soll_h', 'spielraum_h', 'progress_pct']);
    const dir = s.dir === 'desc' ? -1 : 1;
    return [...items].sort((a, b) => {
        let va = a[s.col], vb = b[s.col];
        if (numericCols.has(s.col)) {
            va = Number(va) || 0; vb = Number(vb) || 0;
            return (va - vb) * dir;
        }
        return String(va || '').localeCompare(String(vb || ''), 'de', { sensitivity: 'base' }) * dir;
    });
}
function pdRenderPlansContent() {
    const wrap = document.getElementById('pd-plans-content');
    const head = document.getElementById('pd-plans-count');
    const chipBar = document.getElementById('pd-plans-risiko-chips');
    const kundeSel = document.getElementById('pd-plans-kunde-select');
    if (!wrap || !pdPlansCache) return;
    const all = pdPlansCache.items || [];
    const items = pdFilterPlans(all);
    if (head) head.textContent = items.length === all.length
        ? `Aktive Pläne (${all.length})`
        : `Aktive Pläne (${items.length} von ${all.length})`;
    if (chipBar) {
        chipBar.querySelectorAll('button[data-risiko]').forEach(b => {
            b.classList.toggle('is-active', b.dataset.risiko === (pdState.plansFilter.risiko || 'alle'));
        });
    }
    if (kundeSel && kundeSel.value !== String(pdState.plansFilter.kunde || 'alle')) {
        kundeSel.value = String(pdState.plansFilter.kunde || 'alle');
    }
    wrap.innerHTML = pdBuildPlansContent(items);
}
function pdBuildPlansContent(items) {
    const fmt = (v) => {
        if (v == null) return '';
        const s = Number(v).toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
        return s || '0';
    };
    if (!items.length) {
        return '<div class="pd-empty" style="padding:40px;">Keine Pläne im Filter.</div>';
    }
    if (pdState.plansView === 'list') {
        const sorted = pdSortPlansBy(items);
        const s = pdState.plansColSort;
        const hdr = (col, label, right) => {
            const active = s.col === col;
            const arrow = active ? (s.dir === 'asc' ? ' ▲' : ' ▼') : '';
            return `<th class="${right ? 'is-right ' : ''}pd-th-sort${active ? ' is-sorted' : ''}"
                        onclick="pdTogglePlansColSort('${col}')"
                        style="cursor:pointer;user-select:none;${active ? 'color:var(--thoxan-700);' : ''}">${label}${arrow}</th>`;
        };
        const rows = sorted.map(it => {
            const spielraumCls = it.spielraum_h > 0.01 ? 'is-pos' : (it.spielraum_h < -0.01 ? 'is-neg' : '');
            const planUrl = '/admin/projektplanner/plan/' + it.plan_id;
            const modus = it.risiko_modus || 'auto';
            const flagIcon  = ({ eskaliert: '🔥', gruen: '✓', nicht_relevant: '⏸' })[modus] || '';
            const flagTitle = ({ eskaliert: 'Brennt', gruen: 'Erledigt', nicht_relevant: 'Läuft mit' })[modus] || '';
            const rowBg = ({ eskaliert: '#fef2f2', gruen: '#f0fdf4', nicht_relevant: '#f8fafc' })[modus] || '';
            const rowOpacity = modus === 'nicht_relevant' ? '0.6' : '1';
            return `
            <tr data-plan-id="${it.plan_id}" data-risiko-modus="${modus}" data-risiko-notiz="${pdEsc(it.risiko_notiz || '')}"
                onclick="window.location.href='${planUrl}'"
                oncontextmenu="pdRisikoContextMenu(event, ${it.plan_id}, '${modus}'); return false;"
                style="cursor:pointer;${rowBg ? `background:${rowBg};` : ''}opacity:${rowOpacity};"
                ${it.risiko_notiz ? `title="${pdEsc(it.risiko_notiz)}"` : ''}>
                <td><span class="pd-mx-abbr" style="background:#e2e8f022;color:var(--slate-700);">${pdEsc(it.customer_abbr || '?')}</span></td>
                <td>${pdEsc(it.customer_name)}</td>
                <td style="font-weight:600;">${flagIcon ? `<span title="${flagTitle}${it.risiko_notiz ? ' — ' + pdEsc(it.risiko_notiz) : ''}" style="margin-right:6px;">${flagIcon}</span>` : ''}${pdEsc(it.plan_title)}</td>
                <td class="is-right">${fmt(it.ist_h)} h</td>
                <td class="is-right">${fmt(it.planned_h)} h</td>
                <td class="is-right">${fmt(it.soll_h)} h</td>
                <td class="is-right ${spielraumCls}" style="font-weight:700;">${it.spielraum_h > 0 ? '+' : ''}${fmt(it.spielraum_h)} h</td>
                <td class="is-right">${it.progress_pct}%</td>
            </tr>`;
        }).join('');
        return `
        <div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);overflow:auto;">
            <table class="pd-plans-table">
                <thead><tr>
                    ${hdr('customer_abbr', 'Kürzel', false)}
                    ${hdr('customer_name', 'Kunde', false)}
                    ${hdr('plan_title', 'Plan', false)}
                    ${hdr('ist_h', 'Ist', true)}
                    ${hdr('planned_h', 'Geplant', true)}
                    ${hdr('soll_h', 'Soll', true)}
                    ${hdr('spielraum_h', 'Spielraum', true)}
                    ${hdr('progress_pct', 'Done', true)}
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }
    const tiles = items.map(it => {
        const modus = it.risiko_modus || 'auto';
        const flagIcons  = { eskaliert: '🔥', gruen: '✓', nicht_relevant: '⏸', auto: '⚙' };
        const flagLabels = { eskaliert: 'Brennt', gruen: 'Erledigt',
                             nicht_relevant: 'Läuft mit', auto: 'In Arbeit' };
        return `
        <div class="pd-tile pd-tile-risiko-${modus}"
             data-plan-id="${it.plan_id}"
             data-risiko-modus="${modus}"
             data-risiko-notiz="${pdEsc(it.risiko_notiz || '')}"
             oncontextmenu="pdRisikoContextMenu(event, ${it.plan_id}, '${modus}'); return false;"
             ${it.risiko_notiz ? `title="${pdEsc(it.risiko_notiz)}"` : ''}>
            <span class="pd-tile-flag pd-tile-flag-${modus}"
                  title="${flagLabels[modus]}${it.risiko_notiz ? ' — ' + pdEsc(it.risiko_notiz) : ''}">${flagIcons[modus]}</span>
            ${it.html}
        </div>`;
    }).join('');
    return `<div class="pd-tiles-grid">${tiles}</div>`;
}
async function pdRenderPlansTab() {
    const body = document.getElementById('pd-body');
    body.innerHTML = '<div class="pd-empty">Lädt…</div>';
    try {
        if (!pdPlansCache) pdPlansCache = await pdLoadPlansWidgets(pdPlansSort);
        const items = pdPlansCache.items || [];
        // CSS einmalig einbinden
        if (!document.getElementById('pp-widget-css')) {
            const tmp = document.createElement('div');
            tmp.innerHTML = pdPlansCache.css || '';
            const styleEl = tmp.querySelector('style');
            if (styleEl) { styleEl.id = 'pp-widget-css'; document.head.appendChild(styleEl); }
        }
        // Kunden-Liste fuer Select (alphabetisch, einmalig pro Cache)
        const kundenMap = new Map();
        items.forEach(it => {
            const cid = it.customer_id != null ? String(it.customer_id) : '';
            if (!cid || kundenMap.has(cid)) return;
            kundenMap.set(cid, it.customer_name || it.customer_abbr || cid);
        });
        const kundenOptions = [...kundenMap.entries()]
            .sort((a, b) => String(a[1]).localeCompare(String(b[1]), 'de'))
            .map(([id, name]) => `<option value="${pdEsc(id)}">${pdEsc(name)}</option>`).join('');

        const f = pdState.plansFilter;
        const risikoChips = [
            { key: 'alle',           label: 'Alle' },
            { key: 'eskaliert',      label: '🔥 Brennt' },
            { key: 'auto',           label: '⚙ In Arbeit' },
            { key: 'gruen',          label: '✓ Erledigt' },
            { key: 'nicht_relevant', label: '⏸ Läuft mit' },
        ].map(c => `<button class="pd-sort-btn ${(f.risiko || 'alle') === c.key ? 'is-active' : ''}"
                            data-risiko="${c.key}"
                            onclick="pdSetPlansRisiko('${c.key}')">${c.label}</button>`).join('');

        const filterActive = (f.suche && f.suche.trim()) || (f.risiko && f.risiko !== 'alle') || (f.kunde && f.kunde !== 'alle');

        body.innerHTML = `
        <div class="pd-plans-head">
            <h3 style="margin:0;" id="pd-plans-count">Aktive Pläne (${items.length})</h3>
            <div style="display:flex;gap:12px;align-items:center;">
                <span style="font-size:0.7rem;color:#94a3b8;">Rechtsklick → Risiko ändern</span>
                <div class="pd-sort-toggle">
                    <button class="pd-sort-btn ${pdState.plansView === 'tiles' ? 'is-active' : ''}" onclick="pdSetPlansView('tiles')">Kacheln</button>
                    <button class="pd-sort-btn ${pdState.plansView === 'list' ? 'is-active' : ''}" onclick="pdSetPlansView('list')">Liste</button>
                </div>
                <div class="pd-sort-toggle">
                    <button class="pd-sort-btn ${pdPlansSort === 'alpha' ? 'is-active' : ''}" onclick="pdSetPlansSort('alpha')">A–Z</button>
                    <button class="pd-sort-btn ${pdPlansSort === 'risk' ? 'is-active' : ''}" onclick="pdSetPlansSort('risk')">Risiko</button>
                </div>
            </div>
        </div>
        <div class="pd-plans-filter" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 12px;padding:10px 12px;background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);">
            <input type="search" id="pd-plans-suche"
                   placeholder="Suche Plan, Kunde, Kürzel…"
                   value="${pdEsc(f.suche || '')}"
                   oninput="pdSetPlansSuche(this.value)"
                   style="flex:1 1 260px;min-width:220px;padding:6px 10px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.8rem;">
            <select id="pd-plans-kunde-select" onchange="pdSetPlansKunde(this.value)"
                    style="padding:6px 10px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.8rem;background:#fff;">
                <option value="alle">Alle Kunden</option>
                ${kundenOptions}
            </select>
            <div class="pd-sort-toggle" id="pd-plans-risiko-chips" style="flex-wrap:wrap;">
                ${risikoChips}
            </div>
            ${filterActive ? `<button class="pd-sort-btn" onclick="pdResetPlansFilter()" style="margin-left:auto;">Zurücksetzen</button>` : ''}
        </div>
        <div id="pd-plans-content"></div>`;
        // Kundenfilter-State synchron halten (falls Default abweicht)
        const kundeSel = document.getElementById('pd-plans-kunde-select');
        if (kundeSel) kundeSel.value = String(f.kunde || 'alle');
        pdRenderPlansContent();
    } catch (e) {
        body.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>';
    }
}

/* ===== Auslastung (vereint Personen + Forecast) ===== */
function pdRenderAuslastung() {
    if (!pdState.data) return;
    if (typeof pdRenderForecast === 'function') pdRenderForecast();
    // Personen-Block darunter ergaenzen wenn Daten vorhanden
    if (typeof pdRenderPersons === 'function') {
        try {
            const tmp = document.createElement('div');
            const saved = document.getElementById('pd-body').innerHTML;
            // Render Persons separat in einen Container
            const personsBody = document.createElement('div');
            personsBody.id = 'pd-body';
            personsBody.style.display = 'none';
            document.body.appendChild(personsBody);
            pdRenderPersons();
            const personsHtml = personsBody.innerHTML;
            personsBody.remove();
            // Original body wiederherstellen + Personen-Block anhaengen
            document.getElementById('pd-body').innerHTML = saved
                + '<div class="pd-section"><div class="pd-section-head"><h3 style="margin:0;">Personen-Auslastung</h3></div>' + personsHtml + '</div>';
        } catch (_) {}
    }
}

/* ===== Kunden (vereint Cockpit + Matrix) ===== */
async function pdRenderKunden() {
    const body = document.getElementById('pd-body');
    // Toggle-State im pdState
    if (!pdState.kundenView) pdState.kundenView = 'cockpit';
    const toggle = `
    <div class="pd-plans-head">
        <h3 style="margin:0;">Kunden</h3>
        <div class="pd-sort-toggle">
            <button class="pd-sort-btn ${pdState.kundenView === 'cockpit' ? 'is-active' : ''}" onclick="pdSetKundenView('cockpit')">Cockpit</button>
            <button class="pd-sort-btn ${pdState.kundenView === 'matrix' ? 'is-active' : ''}" onclick="pdSetKundenView('matrix')">Übersicht 2026</button>
        </div>
    </div>`;
    body.innerHTML = toggle + '<div id="pd-kunden-inner"><div class="pd-empty">Lädt…</div></div>';
    if (pdState.kundenView === 'matrix' && typeof pdRenderMatrix === 'function') {
        const inner = document.getElementById('pd-kunden-inner');
        // pdRenderMatrix schreibt direkt in #pd-body — wir leiten es um
        const fake = document.createElement('div'); fake.id = 'pd-body'; document.body.appendChild(fake);
        try { await pdRenderMatrix(); inner.innerHTML = fake.innerHTML; } catch (e) { inner.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>'; }
        fake.remove();
    } else if (typeof pdRenderCustomers === 'function') {
        const inner = document.getElementById('pd-kunden-inner');
        const fake = document.createElement('div'); fake.id = 'pd-body'; document.body.appendChild(fake);
        try { await pdRenderCustomers(); inner.innerHTML = fake.innerHTML; } catch (e) { inner.innerHTML = '<div class="pd-empty" style="color:var(--rose-600);">' + pdEsc(e.message) + '</div>'; }
        fake.remove();
    }
}
function pdSetKundenView(v) {
    pdState.kundenView = v;
    pdRenderKunden();
}

document.addEventListener('DOMContentLoaded', pdInit);
</script>
