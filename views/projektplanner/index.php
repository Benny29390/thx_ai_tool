<?php /** @var array $title; @var int|null $ppDeepLinkId */ ?>
<?php if (!empty($ppDeepLinkId)): ?>
<script>window.PP_DEEP_LINK_ID = <?= (int)$ppDeepLinkId ?>;</script>
<?php endif; ?>
<style>
/* ============================================================================
   Projektplanner — Editor (THX-Design)
   Aufbau: 2-Spalten — Plan-Sidebar (links) + Editor (rechts).
   Klassen: .pp-* sind editor-spezifisch und nutzen die globalen --thoxan/-slate-Vars.
   ============================================================================ */

/* Projektplanner: Spalten sind eigene Cards mit gap = --d-gutter (gleich wie
   das Padding aussenrum). */
.pp-page {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter));
    min-height: 480px;
    background: transparent;
    overflow: visible;
    font-size: var(--d-fs-sm);
}
.pp-sidebar,
.pp-main {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
}
/* (Sidebar-Collapse + per-Editor-Schrift wurden entfernt — Topbar liefert beides global) */

/* ===== Plan-Sidebar (gleiche Breite und Mechanik wie Chat-Sidebar) ===== */
.pp-sidebar {
    width: 360px;
    min-width: 360px;
    background: var(--slate-50);
    border-right: 1px solid var(--slate-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width 0.2s ease, min-width 0.2s ease;
}
.pp-sidebar.collapsed {
    width: 56px;
    min-width: 56px;
}
.pp-sidebar.collapsed .pp-sidebar-head,
.pp-sidebar.collapsed .pp-sidebar-filter,
.pp-sidebar.collapsed .pp-sidebar-selection,
.pp-sidebar.collapsed .pp-plans-list,
.pp-sidebar.collapsed .pp-sidebar-foot {
    display: none !important;
}
.pp-sidebar-collapsed-bar {
    display: none;
    flex-direction: column;
    align-items: center;
    padding: 8px 4px;
    gap: 4px;
    overflow-y: auto;
    flex: 1;
}
.pp-sidebar.collapsed .pp-sidebar-collapsed-bar { display: flex; }

/* Abbreviation-Badge im collapsed-Modus — klickbar fuer Plan-Navigation */
.pp-collapsed-abbr {
    width: 40px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-control-radius);
    font-size: var(--d-fs-xs); font-weight: 700;
    color: var(--slate-700);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}
.pp-collapsed-abbr:hover {
    background: var(--thoxan-50); border-color: var(--thoxan-300); color: var(--thoxan-700);
}
.pp-collapsed-abbr.is-active {
    background: var(--thoxan-600); border-color: var(--thoxan-600); color: #fff;
}
.pp-collapsed-divider {
    width: 24px; height: 1px; background: var(--slate-200);
    margin: 4px 0; flex-shrink: 0;
}
.pp-sidebar-head {
    padding: var(--d-gutter);
    border-bottom: 1px solid var(--slate-100);
}
.pp-sidebar-title-row {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    margin-bottom: 6px;
}
.pp-sidebar-title {
    font-weight: 700; color: var(--slate-800); font-size: var(--d-fs-sm);
    display: flex; align-items: center; gap: 6px;
}
.pp-sidebar-title .material-symbols-rounded { color: var(--thoxan-600); font-size: 16px; }
.pp-sidebar-search {
    width: 100%; padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-size: var(--d-control-fs); font-family: inherit;
    background: var(--slate-50);
}
.pp-sidebar-search:focus { outline: none; border-color: var(--thoxan-400); background: #fff; }
.pp-sidebar-filter {
    padding: var(--d-row-pad-y) var(--d-gutter);
    border-bottom: 1px solid var(--slate-100);
    display: flex; flex-wrap: wrap; gap: var(--d-row-gap); align-items: center;
}
/* Sidebar-Filter als kompakte Icon-Leiste — alle 7 Status-Icons + Select-all in eine Zeile */
.pp-sidebar-filter .pp-filter-icon {
    width: 30px; height: 30px;
    border-radius: var(--d-control-radius);
    color: var(--slate-500);
    transition: background 0.1s, color 0.1s;
}
.pp-sidebar-filter .pp-filter-icon:hover { background: var(--slate-100); color: var(--slate-800); }
.pp-sidebar-filter .pp-filter-icon .material-symbols-rounded { font-size: 18px; }
.pp-sidebar-filter .thx-icon-btn.is-active { background: var(--thoxan-600); color: #fff; }
.pp-sidebar-filter .thx-icon-btn.is-active:hover { background: var(--thoxan-700); color: #fff; }
.pp-sidebar-filter .thx-icon-btn.is-active .material-symbols-rounded { color: #fff; }
.pp-filter-chip {
    background: transparent; color: var(--slate-600);
    border: 1px solid var(--slate-200);
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    font-size: var(--d-fs-xs); border-radius: 999px;
    cursor: pointer; font-family: inherit; line-height: 1.4;
    transition: all 0.1s;
}
.pp-filter-chip:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); }
.pp-filter-chip.is-active {
    background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600);
}
.pp-filter-icon-btn {
    margin-left: auto;
    background: transparent; color: var(--slate-500);
    border: 1px solid var(--slate-200);
    width: 22px; height: 22px; border-radius: var(--d-control-radius);
    cursor: pointer; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.1s;
}
.pp-filter-icon-btn:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); background: var(--thoxan-50); }
.pp-filter-icon-btn .material-symbols-rounded { font-size: 14px; }
.pp-sidebar-selection {
    padding: var(--d-row-pad-y) var(--d-row-pad-x);
    background: var(--thoxan-50);
    border-bottom: 1px solid var(--thoxan-100);
    display: flex; align-items: center; gap: var(--d-row-gap);
    font-size: var(--d-fs-xs); color: var(--thoxan-800);
}
.pp-sidebar-selection strong { font-weight: 700; }
.pp-sidebar-selection .pp-clear-sel {
    margin-left: auto; background: transparent; border: 0;
    color: var(--thoxan-700); cursor: pointer; padding: 2px 6px;
    font-size: var(--d-fs-sm); line-height: 1; border-radius: var(--d-control-radius);
}
.pp-sidebar-selection .pp-clear-sel:hover { background: rgba(0,0,0,0.06); }
.pp-plans-list { flex: 1; overflow-y: auto; padding: var(--d-row-gap) 0; }
.pp-plan-item {
    padding: var(--d-row-pad-y) var(--d-gutter);
    cursor: pointer;
    border-left: 3px solid transparent; transition: background 0.08s, border-color 0.08s;
    background: transparent;
}
.pp-plan-item:hover { background: var(--slate-50); }
.pp-plan-item.is-active { background: var(--thoxan-50); border-left-color: var(--thoxan-600); }
.pp-plan-title {
    color: var(--slate-800); font-weight: 600; font-size: var(--d-fs-xs);
    margin-bottom: 1px; line-height: 1.3;
    display: flex; align-items: center; gap: 5px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.pp-plan-color-dot {
    /* Bewusst klein und neutral — die Kunden-Farben sollen nicht dominieren */
    width: 6px; height: 6px; border-radius: 50%;
    flex-shrink: 0; background: var(--slate-400);
    opacity: 0.6;
}
.pp-plan-meta {
    color: var(--slate-500); font-size: var(--d-fs-xs); line-height: 1.25;
    display: flex; align-items: center; gap: 5px;
    flex-wrap: nowrap; overflow: hidden; white-space: nowrap;
}
.pp-plan-meta-customer {
    color: var(--slate-600); font-weight: 500;
    overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1 1 auto;
}
.pp-plan-status-pill { flex-shrink: 0; }
.pp-plan-status-pill {
    background: var(--slate-100); color: var(--slate-600);
    padding: 0 5px; border-radius: var(--d-control-radius); font-size: var(--d-fs-xs); line-height: 1.5;
    text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700;
}
/* Risiko-Marker — dezent, monochrom, kein Hintergrund-Color.
   Position: rechts neben dem Plan-Titel; signalisiert manuell uebersteuerten Risiko-Status.
   Auto-Modus zeigt nichts (Default). */
.pp-risiko-mark {
    flex-shrink: 0; margin-left: auto;
    display: inline-flex; align-items: center; justify-content: center;
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    line-height: 1; user-select: none;
}
.pp-risiko-mark.is-eskaliert {
    width: 13px; height: 13px;
    border: 1px solid var(--slate-700);
    color: var(--slate-800);
    font-size: 9px; font-weight: 800;
    border-radius: 2px;
    letter-spacing: 0;
}
.pp-risiko-mark.is-gruen {
    color: var(--slate-400);
    font-size: 12px; font-weight: 600;
    padding-right: 1px;
}
.pp-risiko-mark.is-nicht-relevant {
    color: var(--slate-300);
    font-size: 11px; font-weight: 700;
    letter-spacing: -1px;
}
.pp-plan-item.is-risiko-nicht-relevant { opacity: 0.55; }
.pp-plan-item.is-risiko-nicht-relevant .pp-plan-title { font-style: italic; }
/* Aktiver/markierter Item-Zustand soll die Marker nicht aufhellen */
.pp-plan-item.is-active .pp-risiko-mark.is-eskaliert { border-color: var(--slate-800); color: var(--slate-900); }
/* Collapsed-Sidebar: kleiner Eck-Indikator am Kuerzel-Badge */
.pp-collapsed-abbr { position: relative; }
.pp-collapsed-abbr .pp-risiko-corner {
    position: absolute; top: 2px; right: 2px;
    width: 5px; height: 5px; border-radius: 50%;
}
.pp-collapsed-abbr .pp-risiko-corner.is-eskaliert { background: var(--slate-800); }
.pp-collapsed-abbr .pp-risiko-corner.is-gruen     { background: transparent; border: 1px solid var(--slate-400); }
.pp-collapsed-abbr.is-risiko-nicht-relevant       { opacity: 0.5; }
.pp-plan-status-pill.aktiv         { background: var(--emerald-50); color: var(--emerald-700); }
.pp-plan-status-pill.entwurf       { background: var(--slate-100); color: var(--slate-600); }
.pp-plan-status-pill.abgeschlossen { background: var(--slate-100); color: var(--slate-500); }
.pp-plan-status-pill.archiviert    { background: var(--slate-50); color: var(--slate-400); }
.pp-plan-status-pill.einzelprojekt { background: rgba(168, 85, 247, 0.12); color: #7e22ce; }
.pp-plan-status-pill.reporting     { background: var(--amber-50); color: var(--amber-700); }
.pp-plan-status-pill.geloescht     { background: #fef2f2; color: #b91c1c; }
.pp-unread-badge {
    margin-left: auto; background: var(--rose-600); color: #fff;
    font-size: var(--d-fs-xs); font-weight: 700; border-radius: 7px; padding: 0 5px;
}
.pp-sidebar-foot {
    padding: var(--d-row-pad-y) var(--d-row-pad-x); border-top: 1px solid var(--slate-100);
    display: flex; gap: var(--d-row-gap);
}

/* ===== Main / Editor ===== */
.pp-main { display: flex; flex-direction: column; overflow: hidden; background: #fff; }

/* Editor-Body: Mittel-Spalte (Sektionen + KPI-Widget) + Tabelle nebeneinander */
.pp-body {
    flex: 1;
    display: flex;
    min-height: 0;
    overflow: hidden;
}
.pp-body-main {
    flex: 1;
    min-width: 0;
    display: flex; flex-direction: column;
    overflow: hidden;
}

/* ===== Mittel-Spalte: Sektions-Tabs + KPI-Widget =====
   Standard: ~320px, einklappbar zu schmalem Strich-Modus (wie Chat-TOC).
   Breite so gewaehlt, dass Ist/Geplant + TS in einer Subline-Zeile passt
   und das KPI-Widget gut atmet. */
.pp-sectionbar {
    width: 320px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--slate-200);
    background: var(--slate-50);
    transition: width 0.16s ease;
    overflow: hidden;
}
.pp-sectionbar.is-collapsed { width: 56px; background: #fff; }

.pp-sb-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px var(--d-gutter) 8px;
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
}
.pp-sectionbar.is-collapsed .pp-sb-head {
    justify-content: center;
    padding: 10px 0 8px;
    background: transparent;
    border-bottom-color: var(--slate-100);
}
.pp-sb-head-title {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.pp-sectionbar.is-collapsed .pp-sb-head-title { display: none; }
.pp-sb-toggle {
    border: 0; background: transparent; cursor: pointer;
    color: var(--slate-500);
    width: 24px; height: 24px; border-radius: var(--d-control-radius);
    display: inline-flex; align-items: center; justify-content: center;
}
.pp-sb-toggle:hover { background: var(--slate-100); color: var(--slate-800); }
.pp-sb-toggle .material-symbols-rounded { font-size: 18px; }

/* ===== Sektions-Tab-Liste (expanded) ===== */
.pp-sb-tabs { padding: 8px 0; overflow-y: auto; flex: 1; }
.pp-sb-tab {
    display: block;
    width: 100%;
    padding: 8px var(--d-gutter);
    background: transparent; border: 0;
    border-left: 2px solid transparent;
    text-align: left;
    color: var(--slate-700);
    cursor: pointer;
    font-size: var(--d-fs-sm);
    font-family: inherit;
    line-height: 1.3;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.pp-sb-tab:hover { background: #fff; color: var(--slate-900); }
.pp-sb-tab.is-active {
    background: #fff;
    border-left-color: var(--thoxan-600);
    color: var(--slate-900);
    font-weight: 600;
}
/* Zwei-Zeilen-Tab: Titel + Count oben, Subline (Ist/Geplant) darunter.
   Display:flex auf .pp-sb-tab-main als eigene Zeile, .pp-sb-tab-sub explizit block. */
.pp-sb-tab-main {
    display: flex; align-items: center; gap: 8px;
    width: 100%;
    min-width: 0;
}
.pp-sb-tab-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-sb-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 18px;
    line-height: 1;
    font-size: var(--d-fs-xs);
    color: var(--slate-400);
    background: var(--slate-100);
    /* Frutiger sitzt optisch leicht zu hoch — minimaler Top-Bias rückt die Zahl mittig */
    padding: 1px 7px 0;
    border-radius: 999px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.pp-sb-tab.is-active .pp-sb-tab-count {
    background: var(--thoxan-50);
    color: var(--thoxan-700);
}
.pp-sb-tab-sub {
    display: flex; align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 6px;
    font-size: var(--d-fs-xs);
    color: var(--slate-400);
    font-weight: 500;
    line-height: 1.25;
    font-variant-numeric: tabular-nums;
}
.pp-sb-tab.is-active .pp-sb-tab-sub { color: var(--slate-500); }
.pp-sb-tab-sub-empty {
    display: block;
    color: var(--slate-300); font-style: italic;
    margin-top: 3px;
}
/* Kompakte Sub-Chips: Icon + Stunden + dezent kleinere TS-Zahl.
   Einheitlich neutral — Slate-Skala, keine Kategorie-Farben.
   Wird sowohl in der Mittel-Spalten-Subline als auch in der Section-Header-
   Zeile in der Tabelle genutzt — gleiche Optik, gleiche Hoehe. */
.pp-sb-sub-chip {
    display: inline-flex; align-items: center;
    gap: 5px;
    line-height: 1.1;
    white-space: nowrap;
    color: var(--slate-500);
    font-weight: 500;
    font-size: var(--d-fs-xs);
    font-variant-numeric: tabular-nums;
}
.pp-sb-sub-chip .material-symbols-rounded {
    font-size: 13px;
    line-height: 1;
    color: var(--slate-400);
    /* Material-Symbols-Glyph sitzt minimal tief gegenueber Frutiger-x-Hoehe — leichter Push nach oben. */
    transform: translateY(-2px);
}
.pp-sb-sub-ts { color: var(--slate-400); font-weight: 500; }

/* Container fuer die zwei Chips in der Section-Header-Zeile.
   Selbe Schriftgroesse wie in der Sidebar, aber kraeftiger gewichtet,
   damit die Sektions-Summen in der Tabellenzeile lesbar bleiben. */
.pp-sec-totals {
    display: inline-flex; align-items: center;
    gap: 14px;
    line-height: 1.1;
}
.pp-sec-totals .pp-sb-sub-chip {
    color: var(--slate-700);
    font-weight: 600;
}
.pp-sec-totals .pp-sb-sub-chip .material-symbols-rounded {
    color: var(--slate-500);
}
/* "Alle"-Tab als visueller Anker oben: kleiner Akzent links, leicht dickerer Look */
.pp-sb-tab.is-all { border-left-color: var(--slate-300); }
.pp-sb-tab.is-all.is-active { border-left-color: var(--thoxan-700); }
/* Trenner unter dem "Alle"-Tab */
.pp-sb-tab.is-all { border-bottom: 1px solid var(--slate-200); margin-bottom: 4px; }


/* ===== Sektions-Tab-Liste (collapsed: Strich-Modus wie Chat-TOC) ===== */
.pp-sb-strokes {
    padding: 8px 0;
    display: flex; flex-direction: column;
    gap: 2px;
    overflow-y: auto; flex: 1;
}
.pp-sb-stroke {
    display: flex; align-items: center; justify-content: flex-start;
    padding: 6px var(--d-gutter);
    width: 100%; background: transparent; border: 0;
    cursor: pointer;
    position: relative;
    transition: background 0.1s;
}
.pp-sb-stroke::before {
    content: '';
    display: block;
    width: 18px; height: 3px;
    background: var(--slate-300);
    border-radius: 2px;
    transition: width 0.12s, background 0.12s, height 0.12s;
}
.pp-sb-stroke:hover { background: var(--slate-50); }
.pp-sb-stroke:hover::before { width: 28px; background: var(--slate-600); }
.pp-sb-stroke.is-active { background: var(--thoxan-50); }
.pp-sb-stroke.is-active::before {
    width: 30px; height: 4px; background: var(--thoxan-600);
}
/* "Alle"-Strich (oben) visuell prominenter: breiter + dicker + dunkler */
.pp-sb-stroke.is-all::before {
    width: 32px; height: 5px;
    background: var(--slate-500);
    border-radius: 3px;
}
.pp-sb-stroke.is-all:hover::before { background: var(--slate-700); }
.pp-sb-stroke.is-all.is-active::before {
    width: 36px; height: 6px;
    background: var(--thoxan-700);
}
/* Optischer Abstand nach dem "Alle"-Strich (wie der Trenner-Strich im Tab-Modus) */
.pp-sb-stroke.is-all { margin-bottom: 4px; padding-bottom: 8px; border-bottom: 1px solid var(--slate-200); }
.pp-sb-stroke[data-preview]:hover::after {
    content: attr(data-preview);
    position: absolute;
    left: calc(100% + 4px); top: 50%;
    transform: translateY(-50%);
    background: var(--slate-900); color: #fff;
    padding: 6px 10px; border-radius: 6px;
    font-size: 11px; line-height: 1.35;
    white-space: nowrap;
    z-index: 50;
    pointer-events: none;
}

/* ===== KPI-Widget (Plan-Kennzahlen) — gemeinsame Basis fuer 3 Varianten ===== */
.pp-kpi-widget {
    border-top: 1px solid var(--slate-200);
    background: #fff;
    padding: 14px var(--d-gutter) 16px;
    display: flex; flex-direction: column;
    gap: 10px;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}
.pp-sectionbar.is-collapsed .pp-kpi-widget { display: none; }

.pp-kpi-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px;
}
.pp-kpi-widget-title {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* ===== Plan-Karten-Kopf: Kunde, Plan-Titel, Projekt-Info ===== */
.pp-kpi-cust {
    display: flex; align-items: center; gap: 10px;
}
.pp-kpi-cust-logo {
    width: 36px; height: 36px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    padding: 3px;
    flex-shrink: 0;
}
.pp-kpi-cust-abbr {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    background: var(--thoxan-50);
    color: var(--thoxan-800);
    border: 1px solid var(--thoxan-200);
    border-radius: 6px;
    font-size: 11px; font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    /* Frutiger sitzt minimal zu hoch — kleiner Push runter */
    padding-top: 1px;
}
/* Logo klickbar zur Kunden-Detailseite */
.pp-kpi-cust-link {
    display: inline-flex; align-items: center;
    text-decoration: none;
    transition: opacity 0.1s;
    flex-shrink: 0;
}
.pp-kpi-cust-link:hover { opacity: 0.85; }
.pp-kpi-cust-web {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: var(--d-fs-sm);
    font-weight: 500;
    color: var(--slate-600);
    text-decoration: none;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    min-width: 0;
    transition: color 0.1s;
}
.pp-kpi-cust-web:hover { color: var(--thoxan-700); }
.pp-kpi-cust-web .material-symbols-rounded {
    font-size: 12px;
    color: var(--slate-400);
    transform: translateY(-2px);
    flex-shrink: 0;
}
.pp-kpi-cust-web:hover .material-symbols-rounded { color: var(--thoxan-600); }

/* Haupt-Ansprechpartner — direkt unter dem Logo+Website-Block, ohne Trenner */
.pp-kpi-contact {
    display: flex; align-items: center; gap: 8px;
    padding: 2px 0 8px;
    border-bottom: 1px solid var(--slate-100);
}
.pp-kpi-contact-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    background: var(--slate-100);
    color: var(--slate-700);
    border-radius: 50%;
    font-size: 10px; font-weight: 700;
    flex-shrink: 0;
    padding-top: 1px;
}
.pp-kpi-contact-meta { flex: 1; min-width: 0; }
.pp-kpi-contact-name {
    font-size: var(--d-fs-xs); font-weight: 600;
    color: var(--slate-700);
    line-height: 1.2;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.pp-kpi-contact-role {
    font-size: 10px; color: var(--slate-500);
    margin-top: 1px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.pp-kpi-contact-mail {
    font-size: 14px; color: var(--slate-400);
    flex-shrink: 0;
    transform: translateY(-2px);
}
.pp-kpi-contact:hover .pp-kpi-contact-mail { color: var(--thoxan-600); }

/* Plan-Titel — Headline direkt vor dem Donut (klickbar zur Plan-Seite) */
.pp-kpi-plan-title {
    display: block;
    font-size: var(--d-fs-base);
    font-weight: 700;
    color: var(--slate-900);
    text-decoration: none;
    line-height: 1.3;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    transition: color 0.1s;
}
.pp-kpi-plan-title:hover { color: var(--thoxan-700); }

/* Projekt-Info / Abrechnungs-Notiz unter der Kunden-Zeile */
.pp-kpi-info {
    display: flex; flex-direction: column;
    gap: 2px;
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
    line-height: 1.4;
    padding: 4px 0 8px;
    border-bottom: 1px solid var(--slate-100);
    transition: color 0.1s;
}
.pp-kpi-info[onclick]:hover { color: var(--slate-800); }
.pp-kpi-info.is-placeholder { color: var(--slate-400); }
.pp-kpi-info-summary { font-weight: 600; color: var(--slate-700); }
.pp-kpi-info-notes { color: var(--slate-500); }

/* Gemeinsame Zeilen/Werte (von A genutzt, auch fuer Fallbacks) */
.pp-kpi-row {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 8px;
    font-size: var(--d-fs-sm);
    line-height: 1.3;
}
.pp-kpi-rows { display: flex; flex-direction: column; gap: 4px; }
.pp-kpi-rows .pp-kpi-row + .pp-kpi-row { padding-top: 4px; border-top: 1px dashed var(--slate-100); }
.pp-kpi-label { color: var(--slate-500); font-weight: 500; }
.pp-kpi-value {
    font-weight: 700;
    color: var(--slate-800);
    font-variant-numeric: tabular-nums;
    text-align: right;
}
.pp-kpi-sub {
    font-size: var(--d-fs-xs);
    font-weight: 500;
    color: var(--slate-400);
}
.pp-kpi-value.is-ist { color: var(--thoxan-700); }
.pp-kpi-value.is-planned { color: #7e22ce; }
.pp-kpi-value.is-budget-ok { color: var(--emerald-700); }
.pp-kpi-value.is-budget-warn { color: var(--amber-700); }
.pp-kpi-value.is-budget-over { color: var(--rose-700); }
.pp-kpi-value.is-gap-ok, .pp-kpi-row-gap.is-gap-ok .pp-kpi-value { color: var(--emerald-700); }
.pp-kpi-value.is-gap-warn, .pp-kpi-row-gap.is-gap-warn .pp-kpi-value { color: var(--amber-700); }
.pp-kpi-value.is-gap-over, .pp-kpi-row-gap.is-gap-over .pp-kpi-value { color: var(--rose-700); }
.pp-kpi-row-gap {
    margin-top: 4px; padding: 6px 8px !important; border-top: 0 !important;
    border-radius: 6px;
    background: var(--slate-50);
}
.pp-kpi-row-gap.is-gap-ok { background: var(--emerald-50); }
.pp-kpi-row-gap.is-gap-warn { background: var(--amber-50); }
.pp-kpi-row-gap.is-gap-over { background: var(--rose-50); }

.pp-kpi-progress {
    display: block;
    width: 100%;
    height: 5px; border-radius: 3px;
    background: var(--slate-200); overflow: hidden;
    margin-top: 2px;
}
.pp-kpi-progress-fill { display: block; height: 100%; border-radius: 3px; }
.pp-kpi-progress-fill.is-ok   { background: var(--emerald-500); }
.pp-kpi-progress-fill.is-warn { background: var(--amber-500); }
.pp-kpi-progress-fill.is-over { background: var(--rose-500); }
.pp-kpi-kb { margin-top: 4px; }

/* ===== Variante A: Donut + Stack-Bar ===== */
.pp-kpi-donut-row {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 2px;
}
.pp-kpi-donut {
    --deg: 0deg;
    width: 64px; height: 64px;
    border-radius: 50%;
    flex-shrink: 0;
    background: conic-gradient(var(--thoxan-600) var(--deg), var(--slate-200) 0);
    position: relative;
}
.pp-kpi-donut-inner {
    position: absolute; inset: 6px;
    background: #fff; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    line-height: 1;
}
.pp-kpi-donut-pct { font-size: 16px; font-weight: 700; color: var(--slate-800); padding-top: 1px; }
.pp-kpi-donut-sub { font-size: 9px; color: var(--slate-400); margin-top: 2px; }
.pp-kpi-donut-meta { flex: 1; min-width: 0; }
.pp-kpi-meta-label { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 500; }
.pp-kpi-meta-strong { font-size: 18px; font-weight: 700; color: var(--slate-800); margin-top: 2px; line-height: 1.1; }
.pp-kpi-meta-sub { font-size: var(--d-fs-xs); color: var(--slate-400); margin-top: 4px; }

.pp-kpi-stack {
    position: relative;
    display: flex;
    width: 100%; height: 8px;
    background: var(--slate-100);
    border-radius: 4px;
    margin-top: 2px;
    overflow: visible; /* Soll-Marker darf rausragen */
}
.pp-kpi-stack-seg { height: 100%; transition: width 0.2s; }
.pp-kpi-stack-seg:first-child { border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
.pp-kpi-stack-seg:last-child { border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
.pp-kpi-stack-seg.is-ist { background: var(--thoxan-700); }
.pp-kpi-stack-seg.is-planned { background: var(--thoxan-400); }
/* Ueber-Soll ist KEIN Problem, sondern Bonus — daher hellblau, kein Rot.
   Rot bleibt fuer echte Warnungen (Unterdeckung, fehlende Stunden) reserviert. */
.pp-kpi-stack-seg.is-over { background: var(--thoxan-200); }
/* Einzelprojekt: Ueberplanung ist ein Problem -> Rot statt Hellblau */
.pp-kpi-stack-seg.is-over-warn { background: var(--rose-500); }
.pp-kpi-stack-seg.is-rest { background: transparent; }
/* Soll-Marker: kleine vertikale Linie an der Soll-Grenze, sichtbar wenn ueberplant */
.pp-kpi-stack-marker {
    position: absolute; top: -2px; bottom: -2px;
    width: 2px; background: var(--slate-700);
    border-radius: 1px;
    transform: translateX(-1px);
    pointer-events: none;
}
.pp-kpi-stack-legend {
    display: flex; gap: 10px;
    font-size: var(--d-fs-xs); color: var(--slate-500);
    flex-wrap: wrap;
}
.pp-kpi-stack-legend span { display: inline-flex; align-items: center; gap: 4px; }
.pp-kpi-dot { display: inline-block; width: 8px; height: 8px; border-radius: 2px; }
.pp-kpi-dot.is-ist { background: var(--thoxan-700); }
.pp-kpi-dot.is-planned { background: var(--thoxan-400); }
.pp-kpi-dot.is-rest { background: var(--slate-200); }
.pp-kpi-dot.is-over { background: var(--thoxan-200); }
.pp-kpi-dot.is-over-warn { background: var(--rose-500); }

/* ===== Aufwand-Kachel-Trio (Ist | Geplant | Soll) =====
   Weisse Cards mit dezenter Border, nur die Wert-Zahl ist farbig akzentuiert. */
.pp-kpi-cells {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
}
/* Wie .pp-kpi-puffer (Spielraum), nur in hellblau. */
.pp-kpi-offen {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 8px;
    font-size: var(--d-fs-xs);
    color: var(--sky-800, #075985);
    padding: 5px 10px;
    border-radius: 6px;
    background: var(--sky-50, #f0f9ff);
    margin-top: 6px;
}
.pp-kpi-offen-label { font-weight: 500; }
.pp-kpi-offen-value { font-weight: 700; font-variant-numeric: tabular-nums; }
.pp-kpi-offen .pp-kpi-sub { color: var(--sky-700, #0369a1); margin-left: 4px; font-weight: 400; }
.pp-kpi-cell {
    display: flex; flex-direction: column; align-items: flex-start;
    gap: 1px;
    padding: 8px 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--slate-200);
    min-width: 0;
}
.pp-kpi-cell-label {
    font-size: 9px; font-weight: 700; color: var(--slate-500);
    text-transform: uppercase; letter-spacing: 0.4px;
}
.pp-kpi-cell-num {
    font-size: 17px; font-weight: 700; color: var(--slate-800);
    line-height: 1.1; margin-top: 3px;
    font-variant-numeric: tabular-nums;
}
.pp-kpi-cell.is-ist     .pp-kpi-cell-num { color: var(--thoxan-700); }
.pp-kpi-cell.is-planned .pp-kpi-cell-num { color: var(--thoxan-500); }
.pp-kpi-cell.is-soll    .pp-kpi-cell-num { color: var(--slate-800); }
.pp-kpi-cell-unit { font-size: 11px; color: var(--slate-400); font-weight: 500; margin-left: 1px; }
.pp-kpi-cell-sub { font-size: 10px; color: var(--slate-400); font-weight: 500; line-height: 1.3; margin-top: 2px; }

/* Carryover-Hinweis: erklaert warum die Kacheln den effektiven Soll zeigen.
   Dezent neutral — nur Text, keine farbliche Box (Farbe ist dem Spielraum vorbehalten). */
.pp-kpi-carryover {
    padding: 4px 0 2px;
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
    line-height: 1.4;
}
.pp-kpi-carryover-head { font-weight: 500; }
.pp-kpi-carryover-head strong { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--slate-800); }
.pp-kpi-carryover-sub {
    margin-top: 2px;
    font-size: 11px;
    color: var(--slate-400);
}

/* Reserve / Fehl-Zeile — kleine, dezente Zeile unter den Aufwand-Kacheln.
   Hellgruener Hintergrund wenn noch Reserve da ist, hellrot wenn Fehlmenge. */
.pp-kpi-puffer {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 8px;
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
    padding: 5px 10px;
    border-radius: 6px;
    background: var(--slate-50);
}
.pp-kpi-puffer.is-positive { background: var(--emerald-50); color: var(--emerald-800); }
.pp-kpi-puffer.is-negative { background: var(--rose-50);    color: var(--rose-800); }
.pp-kpi-puffer-label { font-weight: 500; }
.pp-kpi-puffer-value {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 900px) {
    .pp-sectionbar { width: 220px; }
    .pp-sectionbar.is-collapsed { width: 48px; }
}
.pp-empty {
    flex: 1;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 14px;
    padding: var(--d-gutter); text-align: center; color: var(--slate-500);
}
.pp-empty .material-symbols-rounded { font-size: var(--d-fs-2xl); color: var(--slate-300); }
.pp-empty h2 { margin: 0; color: var(--slate-700); font-size: var(--d-fs-lg); }
.pp-empty p { margin: 0; max-width: 460px; line-height: 1.6; font-size: var(--d-fs-sm); }

/* Editor-Header */
/* ===== Editor-Head: Title-Zeile oben, Meta-Chips darunter, Actions rechts ===== */
.pp-editor-head {
    padding: var(--d-gutter);
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
    display: flex; align-items: flex-start; gap: var(--d-gutter);
}
.pp-head-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
#pp-editor .thx-page-title {
    font-size: var(--d-fs-lg); font-weight: 700; color: var(--slate-800);
    margin: 0; padding: 2px 6px; border-radius: var(--d-control-radius); outline: none; cursor: text;
    min-height: 24px; line-height: 1.25;
}
#pp-editor .thx-page-title:hover, #pp-editor .thx-page-title:focus { background: var(--slate-50); }
#pp-editor .thx-page-title:empty::before {
    content: 'Plan-Titel…'; color: var(--slate-300);
}
/* Plan-Status-Badge neben dem Titel — kurze Visualisierung des plan_status */
.pp-plan-status-badge {
    display: inline-flex; align-items: center;
    padding: 2px 8px 3px;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    background: var(--slate-100); color: var(--slate-600);
    border-radius: 999px;
    flex-shrink: 0;
    line-height: 1.2;
}
/* Status-Farben identisch zur .pp-plan-status-pill in der Sidebar — einheitliche Optik */
.pp-plan-status-badge.is-aktiv         { background: var(--emerald-50);     color: var(--emerald-700); }
.pp-plan-status-badge.is-entwurf       { background: var(--slate-100);      color: var(--slate-600); }
.pp-plan-status-badge.is-einzelprojekt { background: rgba(168, 85, 247, 0.12); color: #7e22ce; }
.pp-plan-status-badge.is-reporting     { background: var(--amber-50);       color: var(--amber-700); }
.pp-plan-status-badge.is-abgeschlossen { background: var(--slate-100);      color: var(--slate-500); }
.pp-plan-status-badge.is-archiviert    { background: var(--slate-50);       color: var(--slate-400); }
.pp-head-meta-inline {
    display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap;
    color: var(--slate-500); font-size: var(--d-fs-xs);
}
.pp-head-meta-inline .pp-meta-sep {
    width: 3px; height: 3px; background: var(--slate-300); border-radius: 50%;
    margin: 0 2px; flex-shrink: 0;
}
.pp-meta-chip {
    display: inline-flex; align-items: center; gap: 4px;
    background: transparent; border: 1px solid transparent;
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border-radius: var(--d-control-radius);
    color: var(--slate-700); font-size: var(--d-fs-xs); font-family: inherit;
    cursor: pointer; line-height: 1.3;
    transition: background 0.1s, border-color 0.1s;
}
.pp-meta-chip:hover { background: var(--slate-50); border-color: var(--slate-200); }
.pp-meta-chip .material-symbols-rounded { font-size: var(--d-fs-sm); color: var(--slate-400); }
.pp-meta-chip select, .pp-meta-chip input {
    border: 0; background: transparent; padding: 0;
    color: inherit; font-size: inherit; font-family: inherit;
    cursor: pointer; outline: none;
}
.pp-meta-chip input[type=date] { width: 110px; cursor: text; }
.pp-meta-chip.is-status select { font-weight: 600; }
.pp-saving-pill {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--slate-100); color: var(--slate-500);
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border-radius: 999px; font-size: var(--d-fs-xs);
    opacity: 0; transition: opacity 0.2s;
}
.pp-saving-pill.is-show { opacity: 1; }
.pp-saving-pill.is-saved { color: var(--emerald-700); background: var(--emerald-50); }
.pp-saving-pill.is-error { color: var(--rose-700); background: var(--rose-50); }
.pp-saving-pill .material-symbols-rounded { font-size: var(--d-fs-sm); }

/* Knowledge-Sync-Pill — dezent grau in der Stats-Bar links. Nur bei Fehler rot.
   Im Erfolgsfall klickbar (oeffnet die Quelle in /wissen). */
.pp-kb-pill {
    display: inline-flex; align-items: center; gap: 5px;
    margin-right: auto;            /* in der Stats-Bar links festkleben */
    padding: 2px 4px 2px 10px;
    border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 500;
    background: transparent;
    color: var(--slate-500);
    border: 1px solid var(--slate-200);
    white-space: nowrap;
    cursor: default;
}
.pp-kb-pill.is-clickable { cursor: pointer; }
.pp-kb-pill.is-clickable:hover { background: var(--slate-50); color: var(--slate-700); }
.pp-kb-pill .material-symbols-rounded { font-size: 13px; opacity: 0.75; }
.pp-kb-pill.is-error {
    background: var(--rose-50); color: var(--rose-700);
    border-color: var(--rose-200); cursor: pointer; font-weight: 600;
    padding: 2px 10px;
}
.pp-kb-pill.is-error:hover { background: #fecdd3; }
.pp-kb-pill.is-error .material-symbols-rounded { opacity: 1; color: var(--rose-600); }
.pp-kb-pill-label { flex: 1; min-width: 0; }
/* Resync-Button rechts in der Pill */
.pp-kb-pill-resync {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px;
    border: 0;
    background: transparent;
    color: var(--slate-500);
    border-radius: 999px;
    cursor: pointer;
    margin-left: 2px;
    transition: background 0.1s, color 0.1s;
}
.pp-kb-pill-resync:hover { background: var(--slate-200); color: var(--slate-800); }
.pp-kb-pill-resync .material-symbols-rounded { font-size: 13px; opacity: 0.85; transform: translateY(-1px); }
.pp-spin { animation: pp-spin 1s linear infinite; }
@keyframes pp-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

.pp-head-actions { display: flex; gap: 6px; align-items: center; }
/* Alle Header-Action-Buttons gleich gross — uniformes Padding und Min-Hoehe */
.pp-head-actions .thx-btn {
    padding: 6px 12px;
    min-height: 32px;
    font-weight: 500;
}
.pp-head-actions .thx-btn .material-symbols-rounded { font-size: 16px; }

/* ⋯ Mehr-Menü */
.pp-more-wrap { position: relative; }
.pp-more-menu {
    position: absolute; top: calc(100% + 4px); right: 0;
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius); box-shadow: 0 8px 24px rgba(0,0,0,0.10);
    min-width: 180px; padding: 4px; z-index: 50;
}
.pp-more-menu button, .pp-more-menu a {
    display: flex; align-items: center; gap: 8px; width: 100%;
    background: transparent; border: 0;
    padding: var(--d-row-pad-y) var(--d-row-pad-x);
    border-radius: var(--d-control-radius); cursor: pointer; text-align: left;
    color: var(--slate-700); font-size: var(--d-fs-xs); font-family: inherit;
    text-decoration: none; line-height: 1.3;
}
.pp-more-menu button:hover, .pp-more-menu a:hover { background: var(--slate-50); color: var(--thoxan-700); }
.pp-more-menu .material-symbols-rounded { font-size: 16px; color: var(--slate-500); }
.pp-more-menu button:hover .material-symbols-rounded, .pp-more-menu a:hover .material-symbols-rounded { color: var(--thoxan-600); }
.pp-more-menu .pp-more-sep { height: 1px; background: var(--slate-100); margin: 4px 2px; }
.pp-more-badge { margin-left: auto; background: var(--rose-600); color: #fff; font-size: var(--d-fs-xs); font-weight: 700; border-radius: var(--d-card-radius); padding: 0 6px; }

.pp-asana-dot {
    display: inline-block; width: 7px; height: 7px; border-radius: 50%;
    background: var(--emerald-500); margin-left: 4px;
}

/* ===== Stats-Bar: schlanke Pillen-Leiste — gleicher Look wie LAM-Filter-Card ===== */
.pp-stats-bar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    justify-content: flex-end;     /* Pills rechtsbündig in der Zeile */
    padding: 12px var(--d-gutter);
    background: var(--slate-50);
    border-bottom: 1px solid var(--slate-200);
    font-size: var(--d-fs-xs);
}
.pp-stat-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    background: #fff; border: 1px solid var(--slate-200);
    color: var(--slate-700);
    line-height: 1.3;
    font-size: var(--d-fs-xs);  /* explizit, damit sie auf jedem Row-Background gleich aussehen */
    white-space: nowrap;
}
.pp-stat-pill-label { color: var(--slate-500); font-weight: 500; }
.pp-stat-pill-value {
    font-weight: 700; color: var(--slate-800);
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
}
.pp-stat-pill-sub { color: var(--slate-400); font-size: var(--d-fs-xs); }
.pp-stat-pill.is-ist .pp-stat-pill-value     { color: var(--thoxan-700); }
.pp-stat-pill.is-planned .pp-stat-pill-value { color: #7e22ce; }
.pp-stat-pill.is-budget.is-ok .pp-stat-pill-value   { color: var(--emerald-700); }
.pp-stat-pill.is-budget.is-warn .pp-stat-pill-value { color: var(--amber-700); }
.pp-stat-pill.is-budget.is-over .pp-stat-pill-value { color: var(--rose-700); }
.pp-stat-pill.is-gap.is-over   { background: var(--rose-50); border-color: var(--rose-100); }
.pp-stat-pill.is-gap.is-warn   { background: var(--amber-50); border-color: var(--amber-100); }
.pp-stat-pill.is-gap.is-ok     { background: var(--emerald-50); border-color: var(--emerald-100); }
.pp-stat-pill.is-gap.is-over .pp-stat-pill-value { color: var(--rose-700); }
.pp-stat-pill.is-gap.is-warn .pp-stat-pill-value { color: var(--amber-700); }
.pp-stat-pill.is-gap.is-ok   .pp-stat-pill-value { color: var(--emerald-700); }
.pp-stat-pill.is-done .pp-stat-pill-value { color: var(--emerald-700); }
.pp-stat-pill-progress {
    width: 70px; height: 5px; border-radius: 3px;
    background: var(--slate-200); overflow: hidden; margin-left: 4px;
}
.pp-stat-pill-progress-fill { display: block; height: 100%; border-radius: 3px; }
.pp-stat-pill-progress-fill.is-ok   { background: var(--emerald-500); }
.pp-stat-pill-progress-fill.is-warn { background: var(--amber-500); }
.pp-stat-pill-progress-fill.is-over { background: var(--rose-500); }

/* ===== Sticky-Compact-Mode: Header + Stats kollabieren beim Scrollen ===== */
.pp-sticky-head {
    position: sticky; top: 0; z-index: 30;
    background: #fff;
}
/* "is-compact"-Variante ENTFERNT — sie hat beim Scrollen Reflows und Flackern
   ausgeloest. Header bleibt jetzt einfach sticky in einer Groesse. */

/* ===== Bulk-Bar (sichtbar wenn Zeilen markiert) ===== */
.pp-bulk-bar {
    display: flex; align-items: center; gap: var(--d-section-gap); flex-wrap: wrap;
    padding: var(--d-row-pad-y) var(--d-card-pad);
    background: var(--thoxan-50); border-bottom: 1px solid var(--thoxan-200);
    font-size: var(--d-fs-xs);
}
.pp-bulk-count { color: var(--thoxan-800); }
.pp-bulk-count strong { font-weight: 700; }
.pp-bulk-label { color: var(--slate-600); font-weight: 600; margin-left: 4px; }
.pp-bulk-select {
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-size: var(--d-control-fs); font-family: inherit; background: #fff; color: var(--slate-700);
}
.pp-bulk-sep { width: 1px; height: 20px; background: var(--thoxan-200); margin: 0 4px; }
.pp-bulk-close {
    margin-left: auto; background: transparent; border: 0;
    color: var(--thoxan-700); cursor: pointer; font-size: 18px; line-height: 1;
    padding: 2px 8px; border-radius: var(--d-control-radius);
}
.pp-bulk-close:hover { background: rgba(0, 0, 0, 0.05); }
.pp-bulk-col { width: 28px; text-align: center; }
.pp-bulk-cb { cursor: pointer; }
.pp-row.is-bulk-selected td { background: var(--thoxan-50) !important; }

/* ===== Sortierbare Spalten-Header + Sort-Banner ===== */
.pp-sort-th {
    cursor: pointer; user-select: none;
    transition: background 0.1s;
}
.pp-sort-th:hover { background: var(--slate-100); }
.pp-sort-banner {
    display: flex; align-items: center; gap: var(--d-section-gap);
    padding: var(--d-row-pad-y) var(--d-card-pad);
    background: var(--amber-50); border-bottom: 1px solid var(--amber-200);
    color: var(--amber-800); font-size: var(--d-fs-xs);
}

/* Inline-Edit-Feedback: pulse beim Speichern, Bestätigungs-Aufblitzen, Fehler-Rahmen */
[data-field].pp-cell-saving { box-shadow: inset 0 -2px 0 var(--thoxan-300); transition: box-shadow 0.2s; }
[data-field].pp-cell-saved  { box-shadow: inset 0 -2px 0 var(--emerald-500); animation: ppCellSavedFade 0.8s ease; }
[data-field].pp-cell-error  {
    box-shadow: inset 0 0 0 2px var(--rose-500), 0 0 0 1px var(--rose-200);
    background: var(--rose-50) !important; position: relative;
}
[data-field].pp-cell-error::after {
    content: '⚠'; position: absolute; top: 2px; right: 4px;
    font-size: var(--d-fs-xs); color: var(--rose-700); font-weight: 700; pointer-events: none;
}
@keyframes ppCellSavedFade {
    0%   { box-shadow: inset 0 -2px 0 var(--emerald-500), 0 0 0 3px rgba(16,185,129,0.15); }
    100% { box-shadow: inset 0 -2px 0 var(--emerald-500); }
}

/* ===== Tablet & Mobile Responsive ===== */

/* Tablet (≤1024px): Sidebar collapsible, Tabelle behält Min-Width aber scrollbar */
@media (max-width: 1024px) {
    .pp-page { grid-template-columns: 60px 1fr; }
    .pp-sidebar { width: 60px; overflow: hidden; transition: width 0.2s; }
    .pp-sidebar:hover, body.pp-sidebar-open .pp-sidebar { width: 240px; overflow: visible; box-shadow: 2px 0 12px rgba(0,0,0,0.08); z-index: 40; }
    .pp-sidebar:not(:hover):not(body.pp-sidebar-open *) .pp-sidebar-head,
    .pp-sidebar:not(:hover):not(body.pp-sidebar-open *) .pp-sidebar-filter,
    .pp-sidebar:not(:hover):not(body.pp-sidebar-open *) .pp-plan-meta { display: none; }
    .pp-stats-bar { flex-wrap: wrap; gap: 4px; padding: 5px 10px; }
    .pp-stat-pill { font-size: var(--d-fs-xs); padding: 2px 7px; }
    .pp-stat-pill-progress { width: 50px; }
    .pp-editor-head { padding: 8px 10px 6px; }
    #pp-editor .thx-page-title { font-size: var(--d-fs-md); }
    .pp-head-actions .thx-btn-small span:not(.material-symbols-rounded) { display: none; }
    .pp-filter-bar { overflow-x: auto; flex-wrap: nowrap; padding: 5px 10px; -webkit-overflow-scrolling: touch; }
    .pp-fb-search { width: 140px; flex-shrink: 0; }
}

/* Mobile (≤640px): aggressive Reduktion */
@media (max-width: 640px) {
    .pp-page { grid-template-columns: 0 1fr; height: calc(100vh - 44px); }
    .pp-sidebar { position: fixed; top: 44px; left: 0; bottom: 0; width: 0; z-index: 50; }
    body.pp-sidebar-open .pp-sidebar { width: 85vw; max-width: 320px; }
    body.pp-sidebar-open::before {
        content: ''; position: fixed; inset: 44px 0 0 0;
        background: rgba(0,0,0,0.4); z-index: 45;
    }
    .pp-editor-head { flex-direction: column; align-items: stretch; gap: 6px; padding: 8px 10px; }
    .pp-head-main { width: 100%; }
    .pp-head-actions { width: 100%; justify-content: flex-end; }
    .pp-head-meta-inline { font-size: var(--d-fs-xs); }
    .pp-meta-chip input[type=date] { width: 100px; font-size: var(--d-fs-xs); }
    .pp-stats-bar { gap: 4px; }
    .pp-stat-pill-sub { display: none; }
    .pp-table { min-width: 720px; font-size: var(--d-fs-sm); }
    .pp-table thead th { font-size: var(--d-fs-xs); padding: 6px 4px; }
    .pp-table tbody td { padding: 6px 4px; }
    /* Wenig kritische Spalten auf Mobile verbergen: Aufwand, Bemerkungen */
    .pp-table colgroup col:nth-child(11),
    .pp-table colgroup col:nth-child(12) { width: 0; }
    .pp-table thead th:nth-child(11),
    .pp-table thead th:nth-child(12),
    .pp-table tbody tr td:nth-child(11),
    .pp-table tbody tr td:nth-child(12) { display: none; }
    .pp-bulk-bar { gap: 4px; padding: 5px 8px; flex-wrap: wrap; }
    .pp-bulk-label, .pp-bulk-count { font-size: var(--d-fs-xs); }
    .pp-saving-pill { display: none; }
    /* Mobile-Sidebar-Open-Button (Floating) */
    .pp-mobile-menu-btn {
        position: fixed; bottom: 16px; left: 16px; z-index: 55;
        width: 48px; height: 48px; border-radius: 50%;
        background: var(--thoxan-600); color: #fff; border: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .pp-mobile-menu-btn .material-symbols-rounded { font-size: 24px; }
}
@media (min-width: 641px) { .pp-mobile-menu-btn { display: none; } }

/* ===== Print-Stylesheet: Plan als PDF / Papier ===== */
@media print {
    @page { size: A4 landscape; margin: 1.2cm 1cm; }
    body { background: #fff !important; color: #000; font-size: 10pt; }
    .thx-topbar, .pp-sidebar, .pp-head-actions, .pp-row-actions, .pp-row-inserter,
    .pp-bulk-bar, .pp-bulk-col, .pp-drag-handle, .pp-sec-buttons, .pp-saving-pill,
    .pp-sectionbar,
    .thx-topbar-sim-banner, .lam-mobile-nav, .feedback-widget-btn { display: none !important; }
    .pp-body { display: block; }
    .pp-body-main { display: block; }
    .pp-page { display: block; height: auto; margin: 0; background: #fff; font-size: 10pt; }
    .pp-sticky-head { position: static; }
    .pp-editor-head { padding: 4px 0; border: 0; }
    .pp-stats-bar { padding: 4px 0; border-top: 1px solid #999; border-bottom: 1px solid #999; }
    .pp-stat-pill { border: 0; background: transparent; padding: 2px 8px; }
    .pp-filter-bar { display: none; }
    .pp-table-wrap { overflow: visible; }
    .pp-table { border-collapse: collapse; width: 100%; min-width: 0 !important; }
    .pp-table th { background: #eee; border-bottom: 1px solid #999; padding: 4px 6px; font-size: 8pt; }
    .pp-table td { border-bottom: 1px solid #ddd; padding: 4px 6px; vertical-align: top; }
    .pp-row-section td { background: #f5f5f5 !important; font-weight: bold; }
    .pp-row-done td { color: #666; }
    .pp-edit, input, select { border: 0 !important; background: transparent !important; -webkit-appearance: none; appearance: none; padding: 0 !important; }
    /* Asana, Color-Dots, Material-Icons in der Tabelle verstecken */
    .material-symbols-rounded { display: none; }
    a { color: #000; text-decoration: none; }
}

/* Filter-Leiste */
.pp-filter-bar {
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid var(--slate-200);
    display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
}
.pp-fb-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.pp-fb-label {
    font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-right: 4px;
}
.pp-fb-chip {
    background: var(--slate-50); color: var(--slate-700);
    border: 1px solid var(--slate-200);
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    font-size: var(--d-fs-xs); border-radius: 999px;
    cursor: pointer; font-family: inherit; line-height: 1.4;
    transition: all 0.1s;
}
.pp-fb-chip:hover { color: var(--thoxan-700); border-color: var(--thoxan-300); }
.pp-fb-chip.is-active {
    background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-600);
}
.pp-fb-select {
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-size: var(--d-control-fs); font-family: inherit; background: #fff;
    color: var(--slate-700);
}
.pp-fb-select:focus { outline: none; border-color: var(--thoxan-400); }
.pp-fb-search {
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-size: var(--d-control-fs); font-family: inherit;
    width: 180px; background: #fff;
}
.pp-fb-search:focus { outline: none; border-color: var(--thoxan-400); }
.pp-fb-banner {
    background: var(--amber-50); color: var(--amber-800);
    padding: var(--d-row-pad-y) var(--d-control-pad-x);
    border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 600;
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid var(--amber-200);
}
.pp-fb-banner button {
    background: none; border: none; color: var(--amber-700);
    cursor: pointer; font-size: 14px; padding: 0; line-height: 1;
}

/* ===== Tabelle: fluide, aber bei zu engem Container horizontal scrollbar ===== */
.pp-table-wrap { flex: 1; overflow: auto; background: #fff; }
.pp-table {
    /* Mindest-Breite, damit alle Fix-Spalten plus die zwei auto-Spalten Platz haben.
       Wird der Container schmaler als das, scrollt horizontal. Wird er breiter,
       expandieren die auto-Spalten. */
    min-width: 1100px;
    width: 100%;
    table-layout: fixed;
    background: #fff;
}
/* Außenrand der Tabelle: 18px (gleich wie globaler --d-gutter) links UND rechts */
.pp-table thead th:first-child,
.pp-table tbody td:first-child { padding-left: var(--d-gutter); }
.pp-table thead th:last-child,
.pp-table tbody td:last-child { padding-right: var(--d-gutter); }
.pp-table thead th {
    position: sticky; top: 0; z-index: 5;
    user-select: none;
    white-space: normal; /* Header-Text darf umbrechen wenn Spalte schmal */
    text-align: left;    /* Standardm. linksbuendig; is-right/is-center override */
    /* Sticky-Header braucht einen deckenden Hintergrund, sonst scrollt der
       Tabellen-Inhalt sichtbar darunter durch. */
    background: var(--slate-50);
    box-shadow: 0 1px 0 var(--slate-200);
    /* sticky etabliert einen containing block — der Resizer-Grip kann rechts
       absolut andocken, ohne dass die sticky-Funktion verloren geht. */
}
/* User-Vorgabe: alle Inhalte uneinheitlich linksbuendig. is-right verlassen,
   is-center bleibt nur fuer die echten Icon-Spalten (Done + Asana + Bulk). */
.pp-table tbody td { text-align: left; }
.pp-table tbody td.is-right { text-align: left; }
.pp-table thead th.is-right { text-align: left; }
.pp-table tbody td.is-center { text-align: center; }
.pp-table thead th.is-center { text-align: center; }
/* Drag-Grip am rechten Rand jedes Headers — klein, dezent, hover-sichtbar */
.pp-col-resizer {
    position: absolute;
    top: 0; right: 0; bottom: 0;
    width: 6px;
    cursor: col-resize;
    user-select: none;
    z-index: 3;
    background: transparent;
    transition: background 0.1s;
}
.pp-col-resizer:hover,
.pp-col-resizer:active {
    background: var(--thoxan-400);
}
/* KEIN Hover-Background in der Tabelle (verursachte Flackern). Originale Row-
   Backgrounds (Section thoxan-50, Notiz slate-50) bleiben erhalten. */
.pp-table tbody tr:hover { background: transparent !important; }
/* Keine Transitions auf TR/TD — flickering durch Animation entfaellt. */
.pp-table tbody tr,
.pp-table tbody td {
    transition: none !important;
}
/* Doppel-Regeln entfernt — Vorgabe linksbündig steht oben (Zeile 567+574+575). */
.pp-table tbody td.is-center { vertical-align: middle; }

/* Row-Typen — Section mit dezenter Thoxan-Hellblau-Hervorhebung */
.pp-table tbody tr.pp-row-section td {
    background: var(--thoxan-50);
    font-weight: 700; color: var(--slate-800);
    border-top: 1px solid var(--thoxan-100);
    border-bottom: 1px solid var(--thoxan-100);
    padding-top: 10px; padding-bottom: 10px;
}
/* pp-sec-subtotal: ersetzt durch pp-stat-pill (Ist + Soll) im Section-Markup,
   damit die Sektions-Werte 1:1 wie die Top-Stats-Bar aussehen. */
/* Section-Buttons: immer sichtbar (kein opacity-Hover-Toggle mehr) */
.pp-sec-buttons {
    display: inline-flex; gap: 2px; margin-left: 8px;
}
.pp-sec-buttons button {
    background: transparent; border: none; color: var(--slate-500);
    cursor: pointer; padding: 2px 4px; border-radius: 3px;
    display: inline-flex; align-items: center; justify-content: center;
    line-height: 1;
}
.pp-sec-buttons button:hover { background: var(--slate-200); color: var(--slate-800); }
/* Material-Symbols sitzen optisch leicht zu tief — minimaler Push nach oben,
   damit sie auf einer Linie mit den Sub-Chip-Zahlen liegen. */
.pp-sec-buttons button .material-symbols-rounded { transform: translateY(-2px); }
.pp-row-section.is-collapsed { background: var(--slate-100); }

/* Insert-Line zwischen Rows */
.pp-row-inserter {
    position: relative; height: 0; padding: 0; border: none; pointer-events: none;
}
.pp-row-inserter > td { padding: 0; border: none; position: relative; height: 0; }
.pp-row-inserter .pp-inserter-bar {
    position: absolute; left: 30px; right: 30px; top: -2px;
    height: 4px; pointer-events: auto;
    opacity: 0; transition: opacity 0.1s;
    display: flex; justify-content: center; align-items: center;
}
.pp-row-inserter:hover .pp-inserter-bar,
.pp-inserter-bar:hover { opacity: 1; }
.pp-inserter-line {
    position: absolute; left: 0; right: 0; top: 1px; height: 2px;
    background: var(--thoxan-400); border-radius: 1px;
}
.pp-inserter-buttons {
    display: inline-flex; gap: 2px; background: var(--thoxan-600);
    border-radius: var(--d-card-radius); padding: 1px 4px; position: relative; z-index: 1;
}
.pp-inserter-buttons button {
    background: transparent; border: none; color: #fff;
    cursor: pointer; padding: 2px 6px; border-radius: var(--d-card-radius);
    font-size: var(--d-fs-xs); font-weight: 600; text-transform: uppercase;
}
.pp-inserter-buttons button:hover { background: var(--thoxan-700); }

/* Context-Menu */
.pp-ctx-item {
    display: flex; align-items: center; gap: 8px;
    width: 100%; text-align: left;
    padding: var(--d-row-pad-y) var(--d-row-pad-x);
    border: none; background: transparent;
    cursor: pointer; font-size: var(--d-fs-sm); color: var(--slate-700);
    border-radius: var(--d-control-radius); font-family: inherit;
}
.pp-ctx-item:hover { background: var(--thoxan-50); }
.pp-ctx-item .material-symbols-rounded { color: var(--slate-500); }

/* Permission-Banner unter Editor-Header */
.pp-perm-banner {
    padding: var(--d-row-pad-y) var(--d-card-pad); font-size: var(--d-fs-xs);
    border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: center; gap: var(--d-section-gap);
}
.pp-perm-banner.is-read  { background: var(--slate-50);   color: var(--slate-700); }
.pp-perm-banner.is-edit  { background: var(--amber-50);   color: var(--amber-800); }
.pp-perm-banner.is-write { background: var(--thoxan-50);  color: var(--thoxan-800); }
.pp-perm-banner .material-symbols-rounded { font-size: 16px; }

/* Body-Klassen: Read-Only versteckt schreibende Aktionen */
body.pp-perm-read .pp-row-actions,
body.pp-perm-read .pp-sec-buttons,
body.pp-perm-read .pp-row-inserter,
body.pp-perm-read .pp-done-btn,
body.pp-perm-read .pp-chip-add,
body.pp-perm-read .pp-chip-remove,
body.pp-perm-read .pp-table-foot,
body.pp-perm-read .pp-head-actions button:not([data-perm-read]),
body.pp-perm-read .pp-edit { pointer-events: none; }
body.pp-perm-read .pp-head-actions button:not([data-perm-read]) { display: none; }

/* Edit-Permission: nur ist_hours/actual_hours/notes/is_done editierbar */
body.pp-perm-edit .pp-row-actions,
body.pp-perm-edit .pp-sec-buttons,
body.pp-perm-edit .pp-row-inserter,
body.pp-perm-edit .pp-chip-add,
body.pp-perm-edit .pp-chip-remove,
body.pp-perm-edit .pp-table-foot,
body.pp-perm-edit .pp-head-actions button:not([data-perm-edit]):not([data-perm-read]) { display: none; }
body.pp-perm-edit .pp-edit[data-field="description"],
body.pp-perm-edit .pp-edit[data-field="timeframe"],
body.pp-perm-edit .pp-edit[data-field="planned_hours"],
body.pp-perm-edit .pp-edit[data-field="lead_responsible"],
body.pp-perm-edit .pp-edit[data-field="responsible"],
body.pp-perm-edit .pp-edit[data-field="deadline"] {
    pointer-events: none;
    opacity: 0.65;
    background: repeating-linear-gradient(45deg, transparent, transparent 4px, var(--slate-100) 4px, var(--slate-100) 8px);
    cursor: not-allowed;
}
body.pp-perm-edit #pp-editor .thx-page-title { pointer-events: none; opacity: 0.7; }
body.pp-perm-edit .pp-chip { pointer-events: none; opacity: 0.7; }
body.pp-perm-edit .pp-chips-cell::after {
    content: '🔒';
    font-size: var(--d-fs-xs); opacity: 0.4; margin-left: 4px;
}

/* Multi-Plan-Modus: einige plan-spezifische Aktionen verstecken */
/* Multi-Plan-Modus: per-Plan-Aktionen ausblenden (Header zeigt nur Titel "X Pläne") */
/* HINWEIS: ppSharePlan ist bewusst NICHT mehr ausgeblendet — es erzeugt im Multi-Modus
   einen gemeinsamen Übersichts-Sharelink (siehe ppShareMultiPlan). */
body.pp-multi-plan .pp-head-meta-inline,
body.pp-multi-plan .pp-head-actions a[href*="/export"],
body.pp-multi-plan .pp-head-actions button[onclick*="ppOpenAsanaConnect"],
body.pp-multi-plan .pp-head-actions button[onclick*="ppDeletePlan"],
body.pp-multi-plan .pp-head-actions button[onclick*="ppRestorePlan"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="customer"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="budget"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="verlauf"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="dupl"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="feedback"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="asana-sync"],
body.pp-multi-plan .pp-more-menu button[data-pp-more="asana-cache"],
body.pp-multi-plan .pp-more-menu .pp-more-sep { display: none; }
body.pp-multi-plan #pp-editor .thx-page-title { pointer-events: none; }
/* Edit-spezifisch: nur erlaubte Felder bekommen Hover-Cursor */
body.pp-perm-edit .pp-edit[data-field="ist_hours"],
body.pp-perm-edit .pp-edit[data-field="actual_hours"],
body.pp-perm-edit .pp-edit[data-field="notes"] {
    background: var(--amber-50);
}

@keyframes pp-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

/* Feedback-Indicator pro Row */
.pp-row-fb-indicator {
    display: inline-flex; align-items: center; gap: 2px;
    background: var(--amber-50); color: var(--amber-700);
    padding: 1px 6px; border-radius: var(--d-card-radius);
    font-size: var(--d-fs-xs); font-weight: 700;
    margin-left: 6px; vertical-align: middle;
    cursor: pointer;
}
.pp-row-fb-indicator.is-read { background: var(--emerald-50); color: var(--emerald-700); }
/* Section-Hover ENTFERNT — verursachte Flackern beim Scrollen ueber Sektionen */
/* Notiz, Spacer und Platzhalter teilen denselben unauffaelligen warmen
   Grau-Hintergrund — alle drei treten optisch nach hinten. */
.pp-table tbody tr.pp-row-note td,
.pp-table tbody tr.pp-row-spacer td,
.pp-table tbody tr.pp-row-placeholder td {
    background: #f4f4f5;
    color: var(--slate-500); font-style: italic;
}
.pp-table tbody tr.pp-row-note td {
    font-size: var(--d-fs-xs);
    padding-top: 4px; padding-bottom: 4px;
}
/* Platzhalter zusaetzlich mit Opazitaet — Zeile soll fast verschwinden. */
.pp-table tbody tr.pp-row-placeholder { opacity: 0.55; }
.pp-table tbody tr.pp-row-placeholder td.pp-col-desc { color: var(--slate-500); }
/* Done: klar erkennbarer grüner Hintergrund — soll auf den ersten Blick auffallen.
   Vorher war 7 % Opacity zu dezent; der User hat den Zustand uebersehen. */
.pp-table tbody tr.pp-row-done td {
    background: var(--emerald-50);
}
.pp-table tbody tr.pp-row-done td.pp-col-desc { color: var(--slate-600); }
/* Fokus-Zeilen: gesamte Zeile rot eingefaerbt, damit sie sofort auffaellt.
   Hat Vorrang vor done/placeholder. */
.pp-table tbody tr.pp-row-focus td {
    background: rgba(244, 63, 94, 0.10);
}
.pp-table tbody tr.pp-row-focus td.pp-col-desc { color: var(--rose-800); }
/* Kombiniert: focus dominiert immer */
.pp-table tbody tr.pp-row-focus.pp-row-done td,
.pp-table tbody tr.pp-row-focus.pp-row-placeholder td {
    background: rgba(244, 63, 94, 0.10);
}

/* Review-Markierung: Zeilen, die im 600ms-Bug-Zeitraum bearbeitet wurden und
   die der User auf Datenverlust prüfen sollte. Amber-Streifen + linke Border. */
.pp-table tbody tr.pp-row-review td {
    background: rgba(245, 158, 11, 0.08);
}
.pp-table tbody tr.pp-row-review td:first-child {
    box-shadow: inset 3px 0 0 var(--amber-500);
}
.pp-table tbody tr.pp-row-review.pp-row-done td {
    background: rgba(245, 158, 11, 0.05);
}
/* Review-Häkchen-Button in der Actions-Zelle */
.pp-review-btn {
    background: var(--amber-50);
    border: 1px solid var(--amber-300);
    color: var(--amber-800);
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    line-height: 1.4;
    margin-left: 4px;
}
.pp-review-btn:hover {
    background: var(--amber-100);
    border-color: var(--amber-500);
}
.pp-review-btn .material-symbols-rounded { font-size: 13px; }
/* KI-Status-Pill (z.B. „Zu klären") — rot, damit sie sich vom amber „passt" abhebt. */
.pp-ai-clarify-pill {
    display: inline-flex;
    align-items: center;
    background: var(--rose-50, #fef2f2);
    border: 1px solid var(--rose-300, #fca5a5);
    color: var(--rose-700, #b91c1c);
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.4;
    margin-left: 4px;
    white-space: nowrap;
}
.pp-reject-btn {
    background: transparent;
    border: 1px solid var(--slate-300);
    color: var(--slate-600);
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    line-height: 1.4;
    margin-left: 4px;
}
.pp-reject-btn:hover { background: var(--rose-50, #fef2f2); border-color: var(--rose-300, #fca5a5); color: var(--rose-700, #b91c1c); }
.pp-reject-btn .material-symbols-rounded { font-size: 13px; }
/* ===== KI-Sparring-Panel ===== */
.pp-spar-panel { position: fixed; top: 44px; right: 0; bottom: 0; width: 460px; max-width: 94vw; background: #fff; border-left: 1px solid var(--slate-200); box-shadow: -8px 0 28px rgba(0,0,0,0.10); z-index: 60; display: flex; flex-direction: column; }
.pp-spar-head { padding: 11px 14px; border-bottom: 1px solid var(--slate-200); display: flex; align-items: center; gap: 8px; background: #fff; }
.pp-spar-head .material-symbols-rounded { color: var(--thoxan-600); }
.pp-spar-title { font-weight: 700; font-size: 14px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pp-spar-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.pp-spar-msg { font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.pp-spar-msg.is-user { align-self: flex-end; background: var(--thoxan-600); color: #fff; border-radius: 12px 12px 3px 12px; padding: 8px 12px; max-width: 85%; }
.pp-spar-msg.is-assistant { align-self: flex-start; background: var(--slate-100); color: var(--slate-800); border-radius: 12px 12px 12px 3px; padding: 8px 12px; max-width: 94%; }
.pp-spar-sources { font-size: 11px; color: var(--slate-500); align-self: flex-start; font-style: italic; }
.pp-spar-foot { border-top: 1px solid var(--slate-200); padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.pp-spar-composer { display: flex; gap: 6px; align-items: flex-end; }
.pp-spar-composer textarea { flex: 1; resize: none; font-size: 13px; padding: 8px; border: 1px solid var(--slate-300); border-radius: 8px; font-family: inherit; line-height: 1.4; max-height: 120px; }
.pp-spar-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.pp-spar-card { border: 1px solid var(--slate-200); border-radius: 8px; padding: 10px; font-size: 12px; background: #fafbfc; align-self: stretch; }
.pp-spar-card-sec { font-weight: 700; color: var(--slate-500); font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
.pp-spar-card.is-remove { background: #fef2f2; border-color: var(--rose-300, #fca5a5); }
.pp-spar-card.is-remove .pp-spar-card-sec { color: var(--rose-700, #b91c1c); }
/* Panel (460px) schiebt den Plan-Inhalt zur Seite — exakt 18px Abstand, statt zu überdecken. */
@media (min-width: 1201px) {
    body.pp-spar-open .main-content { padding-right: 478px !important; box-sizing: border-box; transition: padding-right .18s ease; }
}

/* Asana-Verknüpfung pro Zeile — zwei Icons untereinander, spart Spaltenbreite */
.pp-asana-cell {
    display: inline-flex; flex-direction: column; align-items: center; gap: 2px;
}
.pp-asana-cell-btn {
    background: transparent;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-control-radius);
    padding: 1px 4px;
    cursor: pointer;
    color: var(--slate-400);
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
    text-decoration: none;
    line-height: 1;
}
.pp-asana-cell-btn:hover {
    background: var(--slate-50);
    border-color: var(--slate-400);
    color: var(--slate-700);
}
.pp-asana-cell-btn.is-linked {
    color: var(--emerald-600);
    border-color: var(--emerald-200);
    background: var(--emerald-50);
}
.pp-asana-cell-btn.is-linked:hover {
    border-color: var(--emerald-400);
    background: #ecfdf5;
}
.pp-asana-cell-btn.is-noticket {
    color: var(--slate-500);
    background: var(--slate-100);
    border-color: var(--slate-200);
}
.pp-asana-cell-btn .material-symbols-rounded { font-size: 14px; }
.pp-asana-cell-empty { color: var(--slate-300); font-size: var(--d-fs-xs); }

/* Popover-Menü für die Asana-Zelle (Verknüpfen / Ändern / Lösen / Kein Ticket) */
.pp-asana-pop {
    position: absolute; z-index: 200;
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 200px; padding: 4px;
}
.pp-asana-pop button, .pp-asana-pop a {
    display: flex; align-items: center; gap: 8px; width: 100%;
    background: transparent; border: 0;
    padding: var(--d-row-pad-y) var(--d-row-pad-x);
    border-radius: var(--d-control-radius); cursor: pointer; text-align: left;
    color: var(--slate-700); font-size: var(--d-fs-xs); font-family: inherit;
    text-decoration: none; line-height: 1.3;
}
.pp-asana-pop button:hover, .pp-asana-pop a:hover { background: var(--slate-50); color: var(--thoxan-700); }
.pp-asana-pop .material-symbols-rounded { font-size: 16px; color: var(--slate-500); }
.pp-asana-pop button:hover .material-symbols-rounded,
.pp-asana-pop a:hover .material-symbols-rounded { color: var(--thoxan-600); }
.pp-asana-pop .pp-asana-pop-sep { height: 1px; background: var(--slate-100); margin: 4px 2px; }
.pp-asana-pop .pp-asana-pop-danger { color: var(--rose-700); }
.pp-asana-pop .pp-asana-pop-danger:hover { background: var(--rose-50); color: var(--rose-800); }
.pp-asana-pop .pp-asana-pop-danger .material-symbols-rounded { color: var(--rose-500); }
.pp-asana-pop .pp-asana-pop-danger:hover .material-symbols-rounded { color: var(--rose-700); }

/* Drag */
.pp-drag-handle {
    width: 16px; color: var(--slate-300); cursor: grab;
    text-align: center; user-select: none;
    vertical-align: middle;
}
.pp-drag-handle:hover { color: var(--slate-500); }
tr.is-dragging { opacity: 0.4; }
tr.is-drag-above td { border-top: 2px solid var(--thoxan-500); }
tr.is-drag-below td { border-bottom: 2px solid var(--thoxan-500); }

/* Inline-Edit — mehr vertikale Luft, konsistent mit Tabellen-Padding */
.pp-edit {
    outline: none; border-radius: 3px;
    padding: 4px 6px; min-height: 22px;
    line-height: 1.5;
    word-break: break-word; overflow-wrap: break-word;
    /* Einheitliche Schrift fuer ALLE Tabellen-Zellen — Beschreibung, Termin,
       Ist, Soll, Aufwand, Notiz nutzen dieselbe Familie und Groesse.
       --d-fs-sm = Standard-Body-Schrift (skaliert mit Master-Skala + Density). */
    font-family: inherit;
    font-size: var(--d-fs-sm);
}
/* Multi-Line-Felder: Zeilenumbruch (\n) wird als echter Umbruch dargestellt.
   Eingabe via Shift+Enter oder Cmd/Alt+Enter (Excel-Style). */
.pp-edit[data-field="actual_hours"],
.pp-edit[data-field="notes"],
.pp-edit[data-field="description"] {
    white-space: pre-wrap;
}

/* (Description-Templates-Popover ist als #pp-autocomplete an anderer Stelle definiert) */
/* Kein Hover-Background auf Inline-Edit-Zellen — sorgte fuer Flackern beim
   Ueberscrollen der Tabelle. Stattdessen nur beim Focus dezent. */
.pp-edit:focus { background: #fff; box-shadow: 0 0 0 2px rgba(0, 76, 155, 0.18); }
/* Placeholder ueber CSS :empty statt JS-Klasse — reagiert live auf Tippen
   und Loeschen, ohne dass die Zeile neu gerendert werden muss. */
.pp-edit:empty::before,
.pp-edit.is-empty:empty::before {
    content: attr(data-placeholder); color: var(--slate-300);
}
/* Zahlen-Spalten: linksbuendig, nutzen sonst die normale .pp-edit-Schrift. */
.pp-edit.is-num { text-align: left; }
/* Sicher gehen, dass auch nicht-editable Tabellen-Texte (Chips, Spans) dieselbe
   Groesse nutzen. Header bleibt etwas kleiner via thead-Regeln oben. */
.pp-table tbody td { font-size: var(--d-fs-sm); }

/* Done-Checkbox: gruener Haken im Tallyr-Stil */
.pp-done-btn {
    appearance: none; width: 20px; height: 20px;
    border: 1.5px solid var(--slate-300); border-radius: var(--d-control-radius);
    cursor: pointer; background: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.1s;
    vertical-align: middle;
}
.pp-done-btn:hover { border-color: var(--emerald-500); }
/* Done-State: das Hakerl ist erkennbar mit gefuelltem Emerald-Hintergrund + weissem Haken.
   Zusaetzlich faerbt sich die ganze Zeile gruen (siehe .pp-row-done). */
.pp-done-btn.is-done {
    background: var(--emerald-600); border-color: var(--emerald-600);
}
.pp-done-btn .material-symbols-rounded {
    font-size: 16px; color: var(--emerald-600);
    font-variation-settings: 'FILL' 0, 'wght' 700;
    opacity: 0;
}
.pp-done-btn.is-done .material-symbols-rounded { opacity: 1; color: #fff; }

/* Personen-Chips */
.pp-chips-cell { display: flex; flex-wrap: wrap; gap: 3px; align-items: center; min-height: 22px; }
.pp-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 1px 7px; border-radius: var(--d-card-radius);
    background: var(--slate-100); color: var(--slate-700);
    font-size: var(--d-fs-xs); font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer; max-width: 100%;
}
.pp-chip:hover { background: var(--slate-200); }
.pp-chip-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-chip-remove {
    cursor: pointer; opacity: 0; transition: opacity 0.1s;
    margin-left: 2px; line-height: 1;
}
.pp-chip:hover .pp-chip-remove { opacity: 0.7; }
.pp-chip-remove:hover { opacity: 1; }
.pp-chip-add {
    width: 18px; height: 18px; border-radius: 50%;
    border: 1px dashed var(--slate-300); color: var(--slate-400);
    background: transparent; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 14px; line-height: 1; padding: 0;
}
.pp-chip-add:hover { color: var(--thoxan-600); border-color: var(--thoxan-400); }
.pp-chip-lead {
    background: var(--thoxan-100); color: var(--thoxan-800); font-weight: 700;
}
.pp-chip-lead:hover { background: var(--thoxan-200); }

/* Autocomplete-Popover */
.pp-autocomplete {
    position: absolute; z-index: 9500;
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius); box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
    min-width: 220px; max-height: 280px; overflow-y: auto;
    padding: 4px;
}
.pp-ac-item {
    padding: 6px 10px; border-radius: var(--d-control-radius); cursor: pointer;
    display: flex; align-items: center; gap: 8px;
    font-size: var(--d-fs-sm); color: var(--slate-700);
}
.pp-ac-item:hover, .pp-ac-item.is-highlight { background: var(--thoxan-50); }
.pp-ac-item.is-disabled { color: var(--slate-300); cursor: not-allowed; }
.pp-ac-kuerzel {
    font-weight: 700; font-size: var(--d-fs-xs); padding: 1px 6px; border-radius: var(--d-card-radius);
    background: var(--slate-100); color: var(--slate-700); flex-shrink: 0;
}
.pp-ac-empty { padding: 10px; text-align: center; color: var(--slate-400); font-size: var(--d-fs-xs); }

/* Row-Actions — Icons immer sichtbar, in der Aktion-Spalte rechts neben Done */
.pp-row-actions {
    display: flex; gap: 2px; align-items: center;
}
.pp-col-actions { vertical-align: middle; text-align: left; }
.pp-row-actions-cell {
    display: flex; align-items: center; gap: 6px; justify-content: flex-start;
}
.pp-row-actions button, .pp-row-actions a {
    background: transparent; border: none; color: var(--slate-400);
    cursor: pointer; padding: 3px; border-radius: 3px;
    display: inline-flex; align-items: center; text-decoration: none;
}
.pp-row-actions button:hover { background: var(--slate-100); color: var(--slate-700); }
.pp-row-actions .pp-action-delete:hover { color: var(--rose-600); }
.pp-row-actions .pp-action-focus.is-active { color: var(--amber-600); }
.pp-row-actions .pp-action-noticket.is-active { color: var(--slate-500); }
.pp-row-actions .pp-action-placeholder.is-active { color: var(--slate-500); }

/* Footer mit Add-Buttons */
.pp-table-foot {
    padding: 8px 14px; background: #fff;
    border-top: 1px solid var(--slate-200);
    display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
}
.pp-table-foot-spacer { flex: 1; }
.pp-table-foot .pp-kb-pill { margin-right: 0; }

/* Modal/Drawer im THX-Stil — wir nutzen .thx-modal-backdrop usw. */
.pp-modal-body .pp-field { margin-bottom: 12px; }
.pp-modal-body label {
    display: block; font-size: var(--d-fs-xs); font-weight: 600;
    color: var(--slate-600); margin-bottom: 4px;
}
.pp-modal-body input, .pp-modal-body select {
    width: 100%; padding: 7px 10px;
    border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-size: var(--d-fs-sm); font-family: inherit;
}
.pp-modal-body input:focus, .pp-modal-body select:focus { outline: none; border-color: var(--thoxan-400); }

/* ===== Budget-/Abrechnungs-Modal — Inline-Edit-Optik =====
   Felder wirken wie Text, werden erst bei Hover/Focus zu sichtbaren Eingaben. */
.pp-budget-input {
    width: 64px; padding: 4px 6px;
    border: 1px solid transparent;
    background: transparent;
    border-radius: var(--d-control-radius);
    text-align: right; font-variant-numeric: tabular-nums;
    font-family: inherit; font-size: var(--d-fs-sm);
    color: var(--slate-800);
    transition: background 0.1s, border-color 0.1s;
}
.pp-budget-input:hover { border-color: var(--slate-200); background: #fff; }
.pp-budget-input:focus { outline: none; border-color: var(--thoxan-500); background: #fff; box-shadow: 0 0 0 2px rgba(0, 76, 155, 0.08); }
.pp-budget-input::placeholder { color: var(--slate-300); }
.pp-budget-input.pp-budget-note { width: 100%; text-align: left; }
/* Marker fuer manuell ueberschriebenen Wert: amber Farbe + dezente Hintergrundtinte */
.pp-budget-input.is-override {
    color: var(--amber-800);
    background: var(--amber-50);
    border-color: var(--amber-200);
}
.pp-budget-input.is-override:hover { background: var(--amber-100); }
.pp-budget-input.is-override:focus { border-color: var(--amber-500); box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.15); }

.pp-budget-table {
    width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); margin-top: 4px;
}
.pp-budget-table th {
    padding: 6px 8px; text-align: left;
    font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--slate-500); border-bottom: 2px solid var(--slate-200);
    background: var(--slate-50);
}
.pp-budget-table th.is-right { text-align: right; }
.pp-budget-table td { padding: 5px 8px; border-bottom: 1px solid var(--slate-100); vertical-align: middle; }
.pp-budget-table td.is-right { text-align: right; }
.pp-budget-table td.pp-bm-name { font-weight: 600; color: var(--slate-700); padding-left: 10px; }
/* Quartals-Umschalter Retainer/auf Zuruf im Perioden-Header (gemischte Projekte). */
.pp-mode-toggle { display: inline-flex; margin-left: 8px; border: 1px solid var(--slate-300); border-radius: 5px; overflow: hidden; vertical-align: middle; }
.pp-mode-toggle button { border: none; background: #fff; color: var(--slate-500); font-size: 10px; font-weight: 600; padding: 1px 7px; cursor: pointer; line-height: 1.6; text-transform: none; letter-spacing: 0; }
.pp-mode-toggle button.is-active { background: var(--thoxan-600); color: #fff; }
.pp-mode-toggle button:not(.is-active):hover { background: var(--slate-100); }
.pp-budget-table td.pp-bm-num { font-variant-numeric: tabular-nums; color: var(--slate-700); }
.pp-bm-plan-link { color: var(--slate-800); font-weight: 600; text-decoration: none; }
.pp-bm-plan-link:hover { color: var(--thoxan-700); }
.pp-bm-plan-period { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; }
/* Einzelprojekt-Angebots-Eingabe: TS + h nebeneinander, synchron */
.pp-bm-offer-inputs {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: var(--d-fs-xs);
}
.pp-bm-offer-inputs .pp-budget-input { width: 56px; }
.pp-bm-offer-sep { color: var(--slate-400); font-weight: 500; font-size: 11px; }
.pp-budget-table td.pp-bm-ist-h { font-variant-numeric: tabular-nums; color: var(--slate-600); }
.pp-budget-table tr.pp-budget-qrow td {
    background: var(--slate-50);
    font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 600;
    padding: 4px 8px;
    border-bottom: 1px solid var(--slate-200);
    border-top: 1px solid var(--slate-200);
}
.pp-budget-table tr.pp-budget-qrow td.pp-bm-name { font-weight: 700; color: var(--slate-700); text-transform: uppercase; letter-spacing: 0.04em; }
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum { font-variant-numeric: tabular-nums; color: var(--slate-600); text-align: right; }
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum-input { padding: 4px 8px; }
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum-input .pp-budget-input { background: #fff; }
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum-input .pp-budget-unit { color: var(--slate-400); margin-left: 4px; font-size: var(--d-fs-xs); }
.pp-budget-table td.pp-bm-empty { color: var(--slate-300); text-align: center; font-style: italic; font-size: var(--d-fs-xs); }
/* Period-Inputs: gleiche Inline-Edit-Optik wie Monatsfelder, nur fett (= Summe) */
.pp-budget-period-input { font-weight: 600; }

/* Überhang-Karte: Hintergrund-Akzent je nach Vorzeichen */
.pp-budget-stat-ueberhang {
    padding: 6px 10px;
    border-radius: 6px;
    margin: -6px -10px;
    transition: background 0.12s;
}
.pp-budget-stat-ueberhang.is-pos { background: var(--emerald-50); }
.pp-budget-stat-ueberhang.is-pos .pp-budget-stat-value { color: var(--emerald-700); }
.pp-budget-stat-ueberhang.is-pos .pp-budget-stat-label { color: var(--emerald-700); }
.pp-budget-stat-ueberhang.is-neg { background: var(--rose-50); }
.pp-budget-stat-ueberhang.is-neg .pp-budget-stat-value { color: var(--rose-700); }
.pp-budget-stat-ueberhang.is-neg .pp-budget-stat-label { color: var(--rose-700); }
.pp-budget-pos { color: var(--emerald-700); }
.pp-budget-neg { color: var(--rose-700); }
/* Diff-Periode-Zelle mit Hintergrund: hellgruen Ueberhang, hellrot Unterdeckung */
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum.pp-budget-pos { background: var(--emerald-50); color: var(--emerald-800); }
.pp-budget-table tr.pp-budget-qrow td.pp-bm-qsum.pp-budget-neg { background: var(--rose-50);    color: var(--rose-800); }
.pp-budget-future { opacity: 0.55; }

.pp-budget-summary {
    background: var(--slate-50); border-radius: var(--d-card-radius);
    padding: 12px 16px; margin: 16px 0 12px;
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
}
.pp-budget-stat-label { font-size: var(--d-fs-xs); color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
.pp-budget-stat-value { font-size: var(--d-fs-lg); font-weight: 700; font-variant-numeric: tabular-nums; }
.pp-budget-stat-sub { font-size: var(--d-fs-xs); color: var(--slate-400); margin-top: 2px; }

/* Konfig-Block */
.pp-budget-config {
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius); padding: 12px 16px;
    margin-bottom: 4px;
}
.pp-budget-config-row {
    display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;
}
.pp-budget-config-row + .pp-budget-config-row { margin-top: 10px; }
.pp-budget-field { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.pp-budget-field label {
    font-size: var(--d-fs-xs); color: var(--slate-500);
    text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600;
}
.pp-budget-field select,
.pp-budget-field input[type="number"],
.pp-budget-field textarea {
    padding: 5px 8px; border: 1px solid var(--slate-200); border-radius: var(--d-control-radius);
    font-family: inherit; font-size: var(--d-fs-sm);
    background: #fff;
}
.pp-budget-field input[type="number"] { font-variant-numeric: tabular-nums; }
.pp-budget-field textarea { resize: vertical; min-height: 36px; }

.pp-budget-legend {
    margin-top: 10px; font-size: var(--d-fs-xs); color: var(--slate-400);
    line-height: 1.5;
}
</style>

<div class="pp-page">

    <!-- ===== Plan-Sidebar ===== -->
    <aside class="pp-sidebar" id="pp-sidebar">
        <!-- Collapsed-Bar (nur sichtbar wenn Sidebar eingeklappt) -->
        <div class="pp-sidebar-collapsed-bar">
            <button class="thx-icon-btn" onclick="ppToggleSidebar()" title="Sidebar einblenden">
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
            <button class="thx-icon-btn" onclick="ppOpenNewPlanModal()" title="Neuer Plan">
                <span class="material-symbols-rounded">add</span>
            </button>
            <div class="pp-collapsed-divider"></div>
            <!-- Plan-Kuerzel-Liste fuer Schnellnavigation (JS-gerendert) -->
            <div id="pp-collapsed-list"></div>
        </div>
        <div class="pp-sidebar-head">
            <div class="pp-sidebar-title-row">
                <button class="thx-icon-btn" onclick="ppToggleSidebar()" title="Sidebar einklappen">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
                <h2 class="thx-page-title" style="font-size:var(--d-fs-base);margin:0;flex:1;text-align:center;">
                    Pläne
                </h2>
                <button class="thx-icon-btn" onclick="ppOpenNewPlanModal()" title="Neuer Plan">
                    <span class="material-symbols-rounded">add</span>
                </button>
            </div>
            <input type="text" class="thx-input" id="pp-sidebar-search"
                   placeholder="Suchen…" oninput="ppRenderPlanList()">
        </div>
        <div class="pp-sidebar-filter">
            <button class="thx-icon-btn pp-filter-icon" data-filter="all" onclick="ppSetFilter('all')" title="Alle">
                <span class="material-symbols-rounded">inbox</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="entwurf" onclick="ppSetFilter('entwurf')" title="Entwurf">
                <span class="material-symbols-rounded">edit_note</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon is-active" data-filter="aktiv" onclick="ppSetFilter('aktiv')" title="Aktiv">
                <span class="material-symbols-rounded">play_circle</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="einzelprojekt" onclick="ppSetFilter('einzelprojekt')" title="Einzelprojekte">
                <span class="material-symbols-rounded">bookmark</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="reporting" onclick="ppSetFilter('reporting')" title="Reporting (fertig, noch nicht an Kunden reportet)">
                <span class="material-symbols-rounded">assessment</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="abgeschlossen" onclick="ppSetFilter('abgeschlossen')" title="Fertig">
                <span class="material-symbols-rounded">task_alt</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="archived" onclick="ppSetFilter('archived')" title="Archiv (plan_status='archiviert')">
                <span class="material-symbols-rounded">inventory_2</span>
            </button>
            <button class="thx-icon-btn pp-filter-icon" data-filter="trash" onclick="ppSetFilter('trash')" title="Papierkorb — gelöschte Pläne (endgültig löschen oder wiederherstellen)">
                <span class="material-symbols-rounded">delete</span>
            </button>
            <span style="flex:1;"></span>
            <button class="thx-icon-btn" onclick="ppSelectAllVisible()" title="Alle sichtbaren auswählen (Cmd/Strg+A)">
                <span class="material-symbols-rounded">select_all</span>
            </button>
        </div>
        <div class="pp-sidebar-selection" id="pp-sidebar-selection" style="display:none;">
            <span><strong id="pp-selection-count">0</strong>&nbsp;Pläne ausgewählt</span>
            <button class="pp-clear-sel" onclick="ppClearSelection()" title="Auswahl löschen">×</button>
        </div>
        <div class="pp-plans-list" id="pp-plans-list">
            <div style="padding:20px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-xs);">Lädt…</div>
        </div>
        <div class="pp-sidebar-foot">
            <a href="/admin/projektplanner/dashboard" class="thx-btn thx-btn-secondary thx-btn-small" style="flex:1;justify-content:center;">
                <span class="material-symbols-rounded" style="font-size:14px;">insights</span>
                Dashboard
            </a>
            <a href="/admin/users?tab=benutzer" class="thx-btn thx-btn-secondary thx-btn-small" title="Benutzer (Kapazität, Farbe pro User in der Detailansicht)">
                <span class="material-symbols-rounded" style="font-size:14px;">group</span>
            </a>
        </div>
    </aside>

    <!-- ===== Editor ===== -->
    <main class="pp-main" id="pp-main">
        <div class="pp-empty" id="pp-empty">
            <span class="material-symbols-rounded">view_kanban</span>
            <h2>Plan auswählen oder neu anlegen</h2>
            <p>Wähle links einen Plan aus oder lege einen neuen an. Die Pläne sind nach Kunde und Zeitraum sortiert.</p>
            <button class="thx-btn thx-btn-primary" onclick="ppOpenNewPlanModal()">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span>
                Neuen Plan anlegen
            </button>
        </div>
        <div id="pp-editor" style="display:none;flex:1;flex-direction:column;overflow:hidden;"></div>
    </main>
</div>

<!-- ===== Modal: Neuer Plan ===== -->
<!-- Erledigt-Modal: wird angezeigt, wenn beim Auf-Erledigt-Setzen
     Zeitraum fehlt, IST fehlt oder IST und Soll deutlich abweichen.
     Im Modal koennen alle drei Werte korrigiert werden und werden danach
     gemeinsam gespeichert. -->
<div class="thx-modal-backdrop" id="pp-done-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseDoneModal()">
    <div class="thx-modal" style="width:480px;max-width:96vw;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Aufgabe abschließen</h3>
            <button class="thx-modal-close" onclick="ppCloseDoneModal()">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body">
            <p id="pp-done-modal-hint" style="margin:0 0 14px 0;font-size:0.85rem;color:#64748b;line-height:1.4;"></p>
            <div class="pp-field">
                <label for="pp-done-zeitraum">Zeitraum (Erledigt am)</label>
                <input type="text" id="pp-done-zeitraum" placeholder="DD.MM. oder DD.MM.-DD.MM." autocomplete="off"
                       onblur="this.value = ppFormatTimeframe(this.value)">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="pp-field">
                    <label for="pp-done-ist">Ist (Stunden)</label>
                    <input type="text" id="pp-done-ist" placeholder="z.B. 1,5" autocomplete="off">
                </div>
                <div class="pp-field">
                    <label for="pp-done-soll">Soll (Stunden)</label>
                    <input type="text" id="pp-done-soll" placeholder="z.B. 2" autocomplete="off">
                </div>
            </div>
            <div id="pp-done-warning" style="display:none;font-size:0.8rem;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;padding:6px 10px;border-radius:6px;margin-top:6px;"></div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn" onclick="ppCloseDoneModal()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="ppConfirmDoneModal()">Übernehmen &amp; abschließen</button>
        </div>
    </div>
</div>

<div class="thx-modal-backdrop" id="pp-new-plan-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-new-plan-modal')">
    <div class="thx-modal" style="width:520px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Neuer Plan</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-new-plan-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body">
            <div style="display:flex;gap:4px;margin-bottom:14px;">
                <button class="thx-chip is-active" data-newmode="manual" onclick="ppSwitchNewMode('manual')">Manuell</button>
                <button class="thx-chip" data-newmode="ai" onclick="ppSwitchNewMode('ai')">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">auto_awesome</span>
                    KI-Vorschlag
                </button>
            </div>

            <!-- ===== Manuell-Tab ===== -->
            <div id="pp-new-tab-manual">
                <div class="pp-field">
                    <label>Titel</label>
                    <input type="text" id="pp-new-title" placeholder="z.B. BKK 2026-05+06">
                </div>
                <div class="pp-field">
                    <label>Kunde</label>
                    <select id="pp-new-customer"><option value="">— Kein Kunde —</option></select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="pp-field">
                        <label>Von</label>
                        <input type="date" id="pp-new-from">
                    </div>
                    <div class="pp-field">
                        <label>Bis</label>
                        <input type="date" id="pp-new-to">
                    </div>
                </div>
                <div class="pp-field">
                    <label>Status</label>
                    <select id="pp-new-status">
                        <option value="entwurf">Entwurf</option>
                        <option value="aktiv" selected>Aktiv</option>
                        <option value="einzelprojekt">Einzelprojekt</option>
                        <option value="reporting">Reporting</option>
                    </select>
                </div>
            </div>

            <!-- ===== KI-Tab ===== -->
            <div id="pp-new-tab-ai" style="display:none;">
                <p style="margin:0 0 12px;color:var(--slate-600);font-size:var(--d-fs-sm);">
                    Claude erzeugt einen Entwurf nach Muster der letzten Pläne dieses Kunden — inkl. typischer Sektionen
                    aus der Section-Taxonomy. Der neue Plan ist als <strong>Entwurf</strong> gespeichert, Du kannst alles inline editieren.
                </p>
                <div class="pp-field">
                    <label>Kunde <span style="color:var(--rose-600);">*</span></label>
                    <select id="pp-ai-customer"><option value="">— Kunden wählen —</option></select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="pp-field">
                        <label>Von <span style="color:var(--rose-600);">*</span></label>
                        <input type="date" id="pp-ai-from">
                    </div>
                    <div class="pp-field">
                        <label>Bis <span style="color:var(--rose-600);">*</span></label>
                        <input type="date" id="pp-ai-to">
                    </div>
                </div>
                <div class="pp-field">
                    <label>Briefing (optional)</label>
                    <textarea id="pp-ai-briefing" rows="4" class="thx-textarea"
                              placeholder="z.B. Fokus auf neue Gastartikel-Reihe zu Wellness. Linkprofil-Analyse wie üblich. Diesmal kein Google Ads."
                              style="width:100%;font-family:inherit;"></textarea>
                </div>
                <div id="pp-ai-status" style="margin-top:8px;font-size:var(--d-fs-xs);color:var(--slate-500);"></div>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="ppCloseModal('pp-new-plan-modal')">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="pp-new-submit" onclick="ppCreatePlan()">Anlegen</button>
        </div>
    </div>
</div>

<!-- ===== Modal: Budget (Kunden-Abrechnung) ===== -->
<div class="thx-modal-backdrop" id="pp-budget-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-budget-modal')">
    <div class="thx-modal" style="width:1080px;max-width:98vw;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title" id="pp-budget-title">Abrechnung</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-budget-modal')">&times;</button>
        </div>
        <div class="thx-modal-body" id="pp-budget-body" style="max-height:82vh;overflow:auto;"></div>
    </div>
</div>

<!-- ===== Modal: Kunde wechseln ===== -->
<div class="thx-modal-backdrop" id="pp-customer-change-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-customer-change-modal')">
    <div class="thx-modal" style="width:420px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Kunde wechseln</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-customer-change-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body" id="pp-customer-change-body"></div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" onclick="ppCloseModal('pp-customer-change-modal')">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="ppApplyCustomerChange()">Übernehmen</button>
        </div>
    </div>
</div>

<!-- ===== Modal: Asana Connect ===== -->
<div class="thx-modal-backdrop" id="pp-asana-connect-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-asana-connect-modal')">
    <div class="thx-modal" style="width:480px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Asana-Projekt verknüpfen</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-asana-connect-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body" id="pp-asana-connect-body"></div>
    </div>
</div>

<!-- ===== Modal: Asana Task ===== -->
<div class="thx-modal-backdrop" id="pp-asana-task-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-asana-task-modal')">
    <div class="thx-modal" style="width:560px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Asana-Task</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-asana-task-modal')">&times;</button>
        </div>
        <!-- Feste Mindesthoehe, damit das Modal beim Tab-Wechsel zwischen Create/Link
             und beim Laden der Search-Results nicht in der Hoehe huepft. Der Inhalt
             (Search-Results-Box mit 300px) wird damit immer vollstaendig untergebracht. -->
        <div class="thx-modal-body pp-modal-body" id="pp-asana-task-body" style="min-height:440px;"></div>
    </div>
</div>

<!-- ===== Modal: Sharing (Public-Link + User-Permissions) ===== -->
<div class="thx-modal-backdrop" id="pp-share-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-share-modal')">
    <div class="thx-modal" style="width:640px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Plan teilen</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-share-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body" id="pp-share-body"></div>
    </div>
</div>

<!-- ===== Modal: Feedback-Viewer ===== -->
<div class="thx-modal-backdrop" id="pp-feedback-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-feedback-modal')">
    <div class="thx-modal" style="width:680px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Feedback zum Plan</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-feedback-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body" id="pp-feedback-body" style="max-height:70vh;overflow-y:auto;"></div>
    </div>
</div>

<!-- ===== Modal: Revisionen ===== -->
<div class="thx-modal-backdrop" id="pp-revisions-modal" style="display:none;"
     onclick="if(event.target===this)ppCloseModal('pp-revisions-modal')">
    <div class="thx-modal" style="width:600px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Versionsverlauf</h3>
            <button class="thx-modal-close" onclick="ppCloseModal('pp-revisions-modal')">&times;</button>
        </div>
        <div class="thx-modal-body pp-modal-body" id="pp-revisions-body" style="max-height:70vh;overflow-y:auto;"></div>
    </div>
</div>

<!-- ===== Context-Menü (Row Right-Click) ===== -->
<div id="pp-context-menu" class="thx-contextmenu" style="display:none;"></div>

<!-- Autocomplete-Popover (wird per JS positioniert) -->
<div class="pp-autocomplete" id="pp-autocomplete" style="display:none;"></div>

<script>
'use strict';

/* ============================================================================
   Projektplanner — Frontend
   Bestehende Endpoints (von Benny) werden unverändert genutzt.
   ============================================================================ */

const ppState = {
    plans: [],
    customers: [],
    team: [],                  // [{id, user_id, name, abbreviation, capacity_hours, hex_color}]
    activePlanId: null,
    activePlan: null,
    activeRows: [],
    activePlanIds: [],          // Multi-Plan: Liste aller derzeit angezeigten Pläne
    planBudget: { soll_h: 0, soll_ts: 0, months: 0 },
    sidebarFilter: 'aktiv',
    saveTimers: {},
    dragRowId: null,
    collapsedSections: new Set(),
    editorFilters: {
        status: ['all'],       // multi: 'all', 'open', 'done', 'placeholder', 'no-asana', 'no-ticket', 'focus'
        lead: '',              // exact-match (abbreviation oder name)
        responsible: '',
        search: '',
        col: {},               // {field: 'exact_value'}
    },
    bulkSelection: new Set(),  // Set<row.id> für markierte Item-Zeilen
    sortBy: null,              // null = manuelle Reihenfolge, sonst { field: 'planned_hours'|'ist_hours'|'deadline'|..., dir: 'asc'|'desc' }
    // Mittel-Spalte: Sektions-Tabs + KPI-Widget. 'all' = alle Sektionen wie bisher,
    // sonst die ID einer Section-Row. Wird pro Plan in localStorage gemerkt.
    activeSection: 'all',
    // Mittel-Spalte einklappbar (Strich-Modus wie Chat-TOC) — global persistiert.
    sectionbarCollapsed: false,
};

/* ===== Mittel-Spalte: Sektion + KPI-Widget Helpers ===== */
function ppLoadActiveSection(planId) {
    try {
        const v = localStorage.getItem('pp_active_section_' + planId);
        return v || 'all';
    } catch (_) { return 'all'; }
}
function ppSaveActiveSection(planId, value) {
    try { localStorage.setItem('pp_active_section_' + planId, value || 'all'); } catch (_) {}
}
function ppLoadSectionbarCollapsed() {
    try { return localStorage.getItem('pp_sectionbar_collapsed') === '1'; }
    catch (_) { return false; }
}
function ppSaveSectionbarCollapsed(v) {
    try { localStorage.setItem('pp_sectionbar_collapsed', v ? '1' : '0'); } catch (_) {}
}
window.ppSetActiveSection = function(id) {
    ppState.activeSection = id || 'all';
    if (ppState.activePlanId) ppSaveActiveSection(ppState.activePlanId, ppState.activeSection);
    ppRenderEditor();
};
window.ppToggleSectionbar = function() {
    ppState.sectionbarCollapsed = !ppState.sectionbarCollapsed;
    ppSaveSectionbarCollapsed(ppState.sectionbarCollapsed);
    ppRenderEditor();
};

/** Liefert eine Map row.id -> sectionId (oder '_orphan' wenn vor der ersten Sektion).
 *  Spacer/Notes erben die Sektion ihres letzten Items. plan_header gehoert zu '_orphan'. */
function ppRowSectionMap() {
    const m = new Map();
    let currentSection = '_orphan';
    for (const r of ppState.activeRows) {
        if (r.row_type === 'section') {
            currentSection = r.id;
            m.set(r.id, r.id);
        } else {
            m.set(r.id, currentSection);
        }
    }
    return m;
}

/* ===== Sektion-Helpers ===== */
function ppSectionItems(sectionId) {
    // Liefert alle Item-Rows nach dieser Sektion bis zur nächsten Sektion (oder Ende)
    const idx = ppState.activeRows.findIndex(r => r.id === sectionId);
    if (idx < 0) return [];
    const items = [];
    for (let i = idx + 1; i < ppState.activeRows.length; i++) {
        const r = ppState.activeRows[i];
        if (r.row_type === 'section') break;
        if (r.row_type === 'item') items.push(r);
    }
    return items;
}
function ppSectionSubtotal(sectionId) {
    // "Kein Ticket noetig" ist eine Asana-Metainfo — die Arbeit existiert trotzdem,
    // also zaehlen die Stunden in die Sektions-Summe. Nur echte Platzhalter werden
    // ausgenommen.
    const items = ppSectionItems(sectionId);
    let ist = 0, soll = 0;
    items.forEach(r => {
        if (!parseInt(r.is_placeholder)) {
            ist += parseFloat(r.ist_hours) || 0;
            soll += parseFloat(r.planned_hours) || 0;
        }
    });
    return { ist, soll, count: items.length };
}

async function ppSectionMove(sectionId, direction) {
    // Verschiebt eine ganze Sektion (Section + alle Items darunter bis nächste Section) um eine Sektions-Position
    const rows = ppState.activeRows;
    const startIdx = rows.findIndex(r => r.id === sectionId);
    if (startIdx < 0) return;
    let endIdx = rows.length;
    for (let i = startIdx + 1; i < rows.length; i++) {
        if (rows[i].row_type === 'section') { endIdx = i; break; }
    }
    const block = rows.slice(startIdx, endIdx);
    if (direction < 0) {
        // Vorherige Sektion finden
        let prevSecIdx = -1;
        for (let i = startIdx - 1; i >= 0; i--) {
            if (rows[i].row_type === 'section') { prevSecIdx = i; break; }
        }
        if (prevSecIdx < 0) return;
        rows.splice(startIdx, block.length);
        rows.splice(prevSecIdx, 0, ...block);
    } else {
        if (endIdx >= rows.length) return;
        // Nächste Sektion: ihren Block finden
        let nextEndIdx = rows.length;
        for (let i = endIdx + 1; i < rows.length; i++) {
            if (rows[i].row_type === 'section') { nextEndIdx = i; break; }
        }
        const nextBlock = rows.slice(endIdx, nextEndIdx);
        rows.splice(startIdx, block.length + nextBlock.length, ...nextBlock, ...block);
    }
    ppRenderEditor();
    ppPersistReorder();
}

function ppSectionTogglePlaceholder(sectionId) {
    const items = ppSectionItems(sectionId);
    const allPlaceholder = items.every(r => parseInt(r.is_placeholder));
    const newVal = allPlaceholder ? 0 : 1;
    items.forEach(r => {
        r.is_placeholder = newVal;
        ppDoSaveRow(r.id, 'is_placeholder', newVal);
    });
    ppRenderEditor();
}

function ppSectionToggleCollapse(sectionId) {
    if (ppState.collapsedSections.has(sectionId)) ppState.collapsedSections.delete(sectionId);
    else ppState.collapsedSections.add(sectionId);
    try { localStorage.setItem('pp_collapsed_' + ppState.activePlanId, JSON.stringify([...ppState.collapsedSections])); } catch (_) {}
    ppRenderEditor();
}

/* ===== Utils ===== */
function ppEscape(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function ppOpenModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'flex'; }
function ppCloseModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
function ppFmtDate(s) {
    if (!s) return '?';
    return new Date(s).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
function ppParseNames(raw) {
    if (!raw) return [];
    return String(raw).split(',').map(s => s.trim()).filter(Boolean);
}
function ppJoinNames(arr) { return (arr || []).map(s => String(s).trim()).filter(Boolean).join(', '); }

/** Findet ein Team-Mitglied per Name oder Kürzel (case-insensitive). */
function ppFindTeamMember(needle) {
    if (!needle) return null;
    const n = String(needle).trim().toLowerCase();
    return ppState.team.find(t =>
        (t.name && t.name.toLowerCase() === n) ||
        (t.abbreviation && t.abbreviation.toLowerCase() === n)
    ) || null;
}
function ppChipLabel(rawName) {
    const m = ppFindTeamMember(rawName);
    if (m && m.abbreviation) return m.abbreviation;
    if (m && m.name) return m.name.substring(0, 3).toUpperCase();
    if (!rawName) return '';
    // Fallback: erste 3 Initialen-Zeichen wenn kein Team-Match
    const parts = String(rawName).trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }
    return parts[0].substring(0, 3).toUpperCase();
}
function ppChipColor(rawName) {
    const m = ppFindTeamMember(rawName);
    return m && m.hex_color ? m.hex_color : null;
}

/** Sortiertes Team alphabetisch nach Kürzel, dann Name. activeOnly=true für Dropdowns. */
function ppTeamSorted(activeOnly) {
    const arr = (ppState.team || []).filter(t => !activeOnly || t.is_active);
    return arr.slice().sort((a, b) => {
        const aa = (a.abbreviation || '').toUpperCase();
        const bb = (b.abbreviation || '').toUpperCase();
        if (aa && !bb) return -1;
        if (!aa && bb) return 1;
        if (aa !== bb) return aa.localeCompare(bb, 'de');
        return (a.name || '').localeCompare(b.name || '', 'de');
    });
}

/* ===== Init ===== */
async function ppInit() {
    try {
        const [plansRes, customersRes, teamRes] = await Promise.all([
            fetch('/api/v1/admin/projektplanner/plans').then(r => r.json()),
            fetch('/api/v1/admin/customers').then(r => r.json()),
            fetch('/api/v1/admin/projektplanner/team').then(r => r.json()),
        ]);
        if (!plansRes.success) throw new Error(plansRes.message);
        ppState.plans = plansRes.data.plans || [];
        ppState.loadedPlanState = 'active';
        ppState.customers = (customersRes.data || []).filter(c => c.is_active);
        ppState.team = (teamRes.data?.team || []);
        ppRenderPlanList();
        ppFillCustomerSelect();
        ppInstallSelectAllShortcut();
        // URL-Hash #budget-customer=ID öffnet das Abrechnungs-Modal (Aufruf aus dem Dashboard)
        const hashMatch = (window.location.hash || '').match(/budget-customer=(\d+)/);
        if (hashMatch) {
            const cid = parseInt(hashMatch[1], 10);
            if (cid > 0) {
                setTimeout(() => ppOpenBudgetForCustomer(cid), 300);
                window.history.replaceState({}, '', window.location.pathname);
            }
        }
        // Deep-Link: wenn URL /admin/projektplanner/plan/{id} → diesen Plan automatisch öffnen.
        // PHP setzt das in das globale window.PP_DEEP_LINK_ID; Fallback: aus URL parsen.
        let deepLinkId = (typeof window.PP_DEEP_LINK_ID !== 'undefined' && window.PP_DEEP_LINK_ID) ? window.PP_DEEP_LINK_ID : null;
        if (!deepLinkId) {
            const m = window.location.pathname.match(/\/admin\/projektplanner\/plan\/(\d+)/);
            if (m) deepLinkId = parseInt(m[1], 10);
        }
        // Fallback: zuletzt geoeffneter Plan aus localStorage
        if (!deepLinkId) {
            try {
                const lastId = parseInt(localStorage.getItem('pp_last_plan_id') || '0', 10);
                if (lastId > 0) deepLinkId = lastId;
            } catch (_) {}
        }
        if (deepLinkId) {
            const exists = ppState.plans.some(p => p.id === deepLinkId);
            if (exists) await ppOpenPlan(deepLinkId);
            else {
                // Plan wurde inzwischen geloescht/archiviert -> Memo loeschen, damit nicht ewig erfolglos
                try { localStorage.removeItem('pp_last_plan_id'); } catch (_) {}
            }
        }
    } catch (e) {
        if (typeof App !== 'undefined') App.showNotification('Fehler beim Laden: ' + e.message, 'error');
        else alert('Fehler: ' + e.message);
    }
}

/** Cmd/Strg+A wählt alle sichtbaren Pläne aus — solange Fokus nicht in einem Eingabefeld liegt. */
function ppInstallSelectAllShortcut() {
    if (window.__ppSelectAllInstalled) return;
    window.__ppSelectAllInstalled = true;
    document.addEventListener('keydown', (ev) => {
        if (!(ev.key === 'a' || ev.key === 'A')) return;
        if (!(ev.metaKey || ev.ctrlKey)) return;
        const t = ev.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
        ev.preventDefault();
        ppSelectAllVisible();
    });
}

function ppFillCustomerSelect() {
    const sel = document.getElementById('pp-new-customer');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Kein Kunde —</option>' +
        ppState.customers.map(c => `<option value="${c.id}">${ppEscape(c.name)}</option>`).join('');
}

/* ===== Plan-Sidebar ===== */
/** Liefert die dezenten Risiko-Marker fuer einen Plan in der Sidebar.
 *  Monochrom (Slate), kein Hintergrund-Color, keine bunten Emojis.
 *  - eskaliert      → kleines bordürtes „!" Quadrat rechts neben dem Titel
 *  - gruen          → schmaler Haken in slate-400
 *  - nicht_relevant → kein Symbol; Item bekommt Klasse fuer Opacity + Kursiv-Titel
 *  - auto/null      → nichts. */
function ppRisikoMark(p) {
    const m = p.risiko_modus || 'auto';
    const notiz = (p.risiko_notiz || '').toString().trim();
    const out = { markHtml: '', cornerHtml: '', itemClass: '', tooltipSuffix: '' };
    if (m === 'eskaliert') {
        const t = 'Brennt' + (notiz ? ' — ' + notiz : '');
        out.markHtml = `<span class="pp-risiko-mark is-eskaliert" title="${ppEscape(t)}">!</span>`;
        out.cornerHtml = `<span class="pp-risiko-corner is-eskaliert"></span>`;
        out.tooltipSuffix = ' · ' + t;
    } else if (m === 'gruen') {
        const t = 'Erledigt' + (notiz ? ' — ' + notiz : '');
        out.markHtml = `<span class="pp-risiko-mark is-gruen" title="${ppEscape(t)}">✓︎</span>`;
        out.cornerHtml = `<span class="pp-risiko-corner is-gruen"></span>`;
        out.tooltipSuffix = ' · ' + t;
    } else if (m === 'nicht_relevant') {
        const t = 'Läuft mit' + (notiz ? ' — ' + notiz : '');
        out.itemClass = 'is-risiko-nicht-relevant';
        out.tooltipSuffix = ' · ' + t;
    }
    return out;
}

function ppRenderPlanList() {
    const wrap = document.getElementById('pp-plans-list');
    const search = (document.getElementById('pp-sidebar-search')?.value || '').trim().toLowerCase();
    const filter = ppState.sidebarFilter;
    let list = ppFilterPlans(ppState.plans, filter);
    if (search) list = list.filter(p =>
        (p.title || '').toLowerCase().includes(search) ||
        (p.customer_name || '').toLowerCase().includes(search) ||
        (p.customer_abbr || '').toLowerCase().includes(search)
    );

    if (!list.length) {
        wrap.innerHTML = '<div style="padding:20px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-xs);">Keine Pläne.</div>';
        return;
    }

    wrap.innerHTML = list.map(p => {
        const isActive = p.id === ppState.activePlanId || ppState.activePlanIds.includes(p.id);
        const color = p.customer_color || 'var(--slate-300)';
        const unread = parseInt(p.unread_feedback || 0);
        const permBadge = p._permission && p._permission !== 'owner' ? `<span style="background:var(--amber-50);color:var(--amber-700);padding:1px 6px;border-radius:8px;font-size:9px;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">${p._permission}</span>` : '';
        const risiko = ppRisikoMark(p);
        // Im Papierkorb (state=2) den Status als "gelöscht" ausweisen, nicht als plan_status.
        const isTrashed = parseInt(p.state) === 2;
        const statusPillClass = isTrashed ? 'geloescht' : p.plan_status;
        const statusPillLabel = isTrashed ? 'gelöscht' : p.plan_status;
        return `
        <div class="pp-plan-item ${isActive ? 'is-active' : ''} ${risiko.itemClass}" onclick="ppPlanItemClick(event, ${p.id})" oncontextmenu="ppShowPlanCardMenu(event, ${p.id})">
            <div class="pp-plan-title">
                <span class="pp-plan-color-dot" style="background:${color}"></span>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1 1 auto;min-width:0;">${ppEscape(p.title)}</span>
                ${unread > 0 ? `<span class="pp-unread-badge">${unread}</span>` : ''}
                ${risiko.markHtml}
            </div>
            <div class="pp-plan-meta">
                <span class="pp-plan-meta-customer" title="${ppEscape(p.customer_name || '')}">${ppEscape(p.customer_name || '—')}</span>
                <span class="pp-plan-status-pill ${statusPillClass}" title="${p.row_count || 0} Zeilen">${statusPillLabel}</span>
                ${permBadge}
            </div>
        </div>`;
    }).join('');

    // Collapsed-Sidebar: gleiche Liste als kompakte Kuerzel-Badges
    const cw = document.getElementById('pp-collapsed-list');
    if (cw) {
        cw.innerHTML = list.map(p => {
            const isActive = p.id === ppState.activePlanId || ppState.activePlanIds.includes(p.id);
            const abbr = (p.customer_abbr || (p.customer_name || p.title || '?').substr(0, 3)).toUpperCase();
            const label = (p.title || p.customer_name || '');
            const risiko = ppRisikoMark(p);
            return `
            <button class="pp-collapsed-abbr ${isActive ? 'is-active' : ''} ${risiko.itemClass}"
                    title="${ppEscape(label)}${risiko.tooltipSuffix}"
                    onclick="ppPlanItemClick(event, ${p.id})">${ppEscape(abbr)}${risiko.cornerHtml}</button>`;
        }).join('');
    }

    ppUpdateSelectionBar();
}

/** Aktualisiert die "X Pläne ausgewählt"-Leiste über der Plan-Liste. */
function ppUpdateSelectionBar() {
    const bar = document.getElementById('pp-sidebar-selection');
    if (!bar) return;
    const n = ppState.activePlanIds.length;
    if (n >= 2) {
        bar.style.display = 'flex';
        const c = document.getElementById('pp-selection-count');
        if (c) c.textContent = n;
    } else {
        bar.style.display = 'none';
    }
}

/** Filter-Helfer: kapselt die Logik für Sidebar-Chips.
 *  - 'archived': nur state=1 plus plan_status='archiviert'
 *  - 'trash': nur state=2 (soft-deleted, wartet auf endgueltige Loeschung oder Restore)
 *  - 'all': alles inkl. archivierte (nur state=2 wird ausgeblendet — die sitzen im Papierkorb)
 *  - sonst: exakter plan_status-Match (aktiv/einzelprojekt/entwurf/abgeschlossen/reporting). */
function ppFilterPlans(plans, filter) {
    if (filter === 'archived') return plans.filter(p => p.plan_status === 'archiviert' && parseInt(p.state) === 1);
    if (filter === 'trash')    return plans.filter(p => parseInt(p.state) === 2);
    // Alle anderen Modi: niemals state=2 anzeigen
    const visible = plans.filter(p => parseInt(p.state) !== 2);
    if (filter === 'all') return visible;  // inkl. archivierte, damit sie durchsuchbar bleiben
    return visible.filter(p => p.plan_status === filter);
}

/** Liefert die aktuell sichtbaren Pläne (gemäß Suche + Status-Filter). */
function ppVisiblePlans() {
    const search = (document.getElementById('pp-sidebar-search')?.value || '').trim().toLowerCase();
    let list = ppFilterPlans(ppState.plans, ppState.sidebarFilter);
    if (search) list = list.filter(p =>
        (p.title || '').toLowerCase().includes(search) ||
        (p.customer_name || '').toLowerCase().includes(search) ||
        (p.customer_abbr || '').toLowerCase().includes(search)
    );
    return list;
}

/** Alle sichtbaren Pläne auswählen und im Multi-Plan-View öffnen. */
function ppSelectAllVisible() {
    const list = ppVisiblePlans();
    if (!list.length) return;
    const ids = list.map(p => p.id);
    if (ids.length === 1) {
        ppState.activePlanIds = [];
        ppOpenPlan(ids[0]);
    } else {
        ppState.activePlanIds = ids;
        ppOpenMultiPlan(ids);
    }
}

/** Auswahl leeren und Editor-Leeransicht zeigen. */
function ppClearSelection() {
    ppState.activePlanIds = [];
    ppState.activePlanId = null;
    ppState.activePlan = null;
    const emp = document.getElementById('pp-empty');
    const ed = document.getElementById('pp-editor');
    if (emp) emp.style.display = 'flex';
    if (ed) ed.style.display = 'none';
    ppRenderPlanList();
}

async function ppSetFilter(f) {
    ppState.sidebarFilter = f;
    // Sowohl Chips als auch Icon-Buttons (Papierkorb) markieren
    document.querySelectorAll('.pp-sidebar-filter [data-filter]').forEach(b =>
        b.classList.toggle('is-active', b.dataset.filter === f));
    // 3 Datensaetze, die unterschiedliche Server-Calls brauchen:
    //  - 'trash' (state=2): eigener Endpoint mit ?state=2
    //  - 'archived' (state=1 + plan_status='archiviert'): per status-Filter laden
    //  - alles andere (active): default state=1
    const target = (f === 'trash') ? 'trash' : ((f === 'archived') ? 'archived' : 'active');
    if (ppState.loadedPlanState !== target) {
        try {
            let url;
            if (target === 'trash')         url = '/api/v1/admin/projektplanner/plans?state=2';
            else if (target === 'archived') url = '/api/v1/admin/projektplanner/plans?status=archiviert';
            else                            url = '/api/v1/admin/projektplanner/plans';
            const r = await fetch(url);
            const j = await r.json();
            if (j.success) {
                ppState.plans = j.data.plans || [];
                ppState.loadedPlanState = target;
            }
        } catch (e) { App.showNotification(e.message, 'error'); }
    }
    ppRenderPlanList();
}

/* ===== Neuer Plan ===== */
let ppNewMode = 'manual';

function ppOpenNewPlanModal() {
    document.getElementById('pp-new-title').value = '';
    document.getElementById('pp-new-customer').value = '';
    document.getElementById('pp-new-from').value = '';
    document.getElementById('pp-new-to').value = '';
    document.getElementById('pp-new-status').value = 'aktiv';
    document.getElementById('pp-ai-briefing').value = '';
    document.getElementById('pp-ai-customer').value = '';
    document.getElementById('pp-ai-from').value = '';
    document.getElementById('pp-ai-to').value = '';
    document.getElementById('pp-ai-status').textContent = '';
    // Kunden-Select fuer den AI-Tab spiegeln
    const aiSel = document.getElementById('pp-ai-customer');
    const manSel = document.getElementById('pp-new-customer');
    aiSel.innerHTML = manSel.innerHTML;
    ppSwitchNewMode('manual');
    ppOpenModal('pp-new-plan-modal');
    setTimeout(() => document.getElementById('pp-new-title').focus(), 50);
}

function ppSwitchNewMode(mode) {
    ppNewMode = mode;
    document.querySelectorAll('#pp-new-plan-modal .thx-chip').forEach(c =>
        c.classList.toggle('is-active', c.dataset.newmode === mode));
    document.getElementById('pp-new-tab-manual').style.display = (mode === 'manual') ? '' : 'none';
    document.getElementById('pp-new-tab-ai').style.display     = (mode === 'ai') ? '' : 'none';
    const btn = document.getElementById('pp-new-submit');
    if (mode === 'ai') {
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">auto_awesome</span> KI-Entwurf erstellen';
        btn.onclick = ppGeneratePlan;
    } else {
        btn.innerHTML = 'Anlegen';
        btn.onclick = ppCreatePlan;
    }
}

async function ppCreatePlan() {
    const title = document.getElementById('pp-new-title').value.trim();
    if (!title) { App.showNotification('Titel erforderlich', 'error'); return; }
    const payload = {
        title,
        customer_id: document.getElementById('pp-new-customer').value || null,
        period_from: document.getElementById('pp-new-from').value || null,
        period_to: document.getElementById('pp-new-to').value || null,
        plan_status: document.getElementById('pp-new-status').value,
    };
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppCloseModal('pp-new-plan-modal');
        App.showNotification('Plan erstellt', 'success');
        await ppInit();
        ppOpenPlan(j.data.id);
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
}

/** KI-Plan-Generator — schickt Briefing an Claude und oeffnet danach den Entwurf. */
async function ppGeneratePlan() {
    const customerId = document.getElementById('pp-ai-customer').value;
    const from       = document.getElementById('pp-ai-from').value;
    const to         = document.getElementById('pp-ai-to').value;
    const briefing   = document.getElementById('pp-ai-briefing').value.trim();
    if (!customerId) { App.showNotification('Kunde ist Pflicht', 'error'); return; }
    if (!from || !to) { App.showNotification('Zeitraum (Von + Bis) ist Pflicht', 'error'); return; }

    const status = document.getElementById('pp-ai-status');
    const btn = document.getElementById('pp-new-submit');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded pp-spin" style="font-size:14px;vertical-align:-2px;">autorenew</span> Claude denkt nach… (~15-20 Sek)';
    status.style.color = 'var(--slate-500)';
    status.textContent = 'Hole letzte Pläne, Taxonomy + Steckbrief. Sende an Claude…';

    try {
        const r = await fetch('/api/v1/admin/projektplanner/generate-plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({
                customer_id: parseInt(customerId, 10),
                period_from: from, period_to: to, briefing,
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const d = j.data;
        ppCloseModal('pp-new-plan-modal');
        App.showNotification(
            `Entwurf erstellt: ${d.sections} Sektionen, ${d.items} Items (${d.tokens_in}+${d.tokens_out} Token)`,
            'success'
        );
        await ppInit();
        ppOpenPlan(d.plan_id);
    } catch (e) {
        status.style.color = 'var(--rose-600)';
        status.textContent = 'Fehler: ' + (e.message || 'unbekannt');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">auto_awesome</span> KI-Entwurf erstellen';
    }
}

/* ===== Plan öffnen / Editor ===== */
function ppPlanItemClick(event, planId) {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
        // Multi-Select: zur aktiven Liste hinzu/entfernen
        if (!ppState.activePlanIds.includes(ppState.activePlanId) && ppState.activePlanId) {
            ppState.activePlanIds.push(ppState.activePlanId);
        }
        const idx = ppState.activePlanIds.indexOf(planId);
        if (idx >= 0) ppState.activePlanIds.splice(idx, 1);
        else ppState.activePlanIds.push(planId);
        if (ppState.activePlanIds.length === 1) {
            ppOpenPlan(ppState.activePlanIds[0]);
            ppState.activePlanIds = [];
        } else if (ppState.activePlanIds.length > 1) {
            ppOpenMultiPlan(ppState.activePlanIds);
        } else {
            // alle abgewählt
            document.getElementById('pp-empty').style.display = 'flex';
            document.getElementById('pp-editor').style.display = 'none';
            ppState.activePlanId = null;
            ppState.activePlan = null;
            ppRenderPlanList();
        }
        return;
    }
    // Normaler Klick → Single
    ppState.activePlanIds = [];
    ppOpenPlan(planId);
}

async function ppExitMultiPlan(planId) {
    ppState.activePlanIds = [];
    ppOpenPlan(planId);
}

async function ppOpenMultiPlan(planIds) {
    // KRITISCH: vor Plan-Wechsel alle pending Saves abwarten, sonst gehen
    // gerade eingetippte Werte verloren (siehe ppSaveRowField).
    await ppFlushAllPendingAwait();
    document.getElementById('pp-empty').style.display = 'none';
    const editor = document.getElementById('pp-editor');
    editor.style.display = 'flex';
    editor.innerHTML = '<div style="padding:40px;text-align:center;color:var(--slate-400);">Lädt ' + planIds.length + ' Pläne…</div>';
    try {
        // Pläne + Budgets parallel laden
        const planPromises = planIds.map(id =>
            fetch('/api/v1/admin/projektplanner/plans/' + id).then(r => r.json())
        );
        const budgetPromises = planIds.map(id =>
            fetch('/api/v1/admin/projektplanner/plans/' + id + '/budget-soll').then(r => r.json()).catch(() => null)
        );
        const [planResponses, budgetResponses] = await Promise.all([
            Promise.all(planPromises),
            Promise.all(budgetPromises),
        ]);
        const plans = planResponses.filter(r => r.success).map(r => r.data);
        if (!plans.length) throw new Error('Pläne konnten nicht geladen werden');

        // Budget-Aggregation: dedupliziert pro Kunde + Periode (verhindert Doppelzählung bei mehreren Plänen pro Kunde im gleichen Zeitraum)
        const budgetKeys = new Set();
        let sumSollH = 0, sumSollTs = 0, maxMonths = 0;
        budgetResponses.forEach((br, i) => {
            if (!br || !br.success || !br.data) return;
            const p = plans[i];
            if (!p || !p.customer_id) return;
            const key = p.customer_id + '|' + (p.period_from || '') + '|' + (p.period_to || '');
            if (budgetKeys.has(key)) return;
            budgetKeys.add(key);
            sumSollH  += parseFloat(br.data.soll_h)  || 0;
            sumSollTs += parseFloat(br.data.soll_ts) || 0;
            maxMonths = Math.max(maxMonths, parseInt(br.data.months) || 0);
        });
        ppState.planBudget = { soll_h: sumSollH, soll_ts: sumSollTs, months: maxMonths };

        // Multi-Plan-State
        ppState.activePlanId = null;
        ppState.multiPlans = plans;
        // Alle Rows kombinieren mit virtuellen plan_header-Rows
        const combinedRows = [];
        plans.forEach(p => {
            combinedRows.push({
                id: 'plan_header_' + p.id,
                row_type: 'plan_header',
                description: (p.customer_name || '—') + ' — ' + p.title,
                _planId: p.id,
                _planColor: p.customer_color || '#94a3b8',
            });
            (p.rows || []).forEach(r => combinedRows.push({ ...r, _planId: p.id }));
        });
        ppState.activeRows = combinedRows;
        // Effektive Permission im Multi-Plan = niedrigste über alle geöffneten Pläne
        const levels = { 'read': 0, 'edit': 1, 'write': 2, 'owner': 3 };
        let minLevel = 3;
        plans.forEach(p => {
            const lvl = levels[p._permission || 'owner'] ?? 0;
            if (lvl < minLevel) minLevel = lvl;
        });
        const effPerm = Object.keys(levels).find(k => levels[k] === minLevel) || 'owner';

        ppState.activePlan = {
            id: null,
            title: plans.length + ' Pläne',
            customer_name: plans.map(p => p.customer_abbr || p.customer_name || '?').filter((v, i, a) => a.indexOf(v) === i).join(', '),
            _isMulti: true,
            feedback: [],
            _permission: effPerm,
        };
        ppState.editorFilters = { status: ['all'], lead: '', responsible: '', search: '', col: {} };
        ppState.collapsedSections = new Set();
        ppState.activeSection = 'all';
        ppState.sectionbarCollapsed = ppLoadSectionbarCollapsed();
        document.body.classList.remove('pp-perm-read', 'pp-perm-edit', 'pp-perm-write', 'pp-perm-owner');
        document.body.classList.add('pp-perm-' + effPerm, 'pp-multi-plan');
        ppRenderEditor();
        ppRenderPlanList();
    } catch (e) {
        editor.innerHTML = '<div style="padding:40px;text-align:center;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
    }
}

async function ppOpenPlan(id) {
    // KRITISCH: vor Plan-Wechsel alle pending Saves abwarten.
    await ppFlushAllPendingAwait();
    ppState.activePlanId = id;
    // URL aktualisieren, damit Plan per Refresh / Deep-Link aufrufbar ist
    try {
        const url = '/admin/projektplanner/plan/' + id;
        if (window.location.pathname !== url) {
            window.history.replaceState({ planId: id }, '', url);
        }
    } catch (_) {}
    // Zuletzt geoeffneten Plan merken — beim naechsten Besuch der Planner-Hauptseite
    // automatisch wieder oeffnen.
    try { localStorage.setItem('pp_last_plan_id', String(id)); } catch (_) {}
    document.getElementById('pp-empty').style.display = 'none';
    const editor = document.getElementById('pp-editor');
    editor.style.display = 'flex';
    editor.innerHTML = '<div style="padding:40px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const [planRes, budgetRes] = await Promise.all([
            fetch('/api/v1/admin/projektplanner/plans/' + id).then(r => r.json()),
            fetch('/api/v1/admin/projektplanner/plans/' + id + '/budget-soll').then(r => r.json()).catch(() => null),
        ]);
        if (!planRes.success) throw new Error(planRes.message);
        ppState.activePlan = planRes.data;
        ppState.activeRows = planRes.data.rows || [];
        ppState.planBudget = (budgetRes && budgetRes.success) ? budgetRes.data : { soll_h: 0, soll_ts: 0, months: 0 };
        // Body-Klassen für Permission-Anzeige
        document.body.classList.remove('pp-perm-read', 'pp-perm-edit', 'pp-perm-write', 'pp-perm-owner');
        const perm = planRes.data._permission || 'owner';
        document.body.classList.add('pp-perm-' + perm);
        // Filter aus localStorage wiederherstellen (pro Plan)
        const fkey = 'pp_filters_' + id;
        try {
            const saved = JSON.parse(localStorage.getItem(fkey) || 'null');
            ppState.editorFilters = saved && saved.status ? saved : { status: ['all'], lead: '', responsible: '', search: '' };
        } catch (_) {
            ppState.editorFilters = { status: ['all'], lead: '', responsible: '', search: '' };
        }
        // Sektion-Collapse-State aus localStorage
        try {
            const sc = JSON.parse(localStorage.getItem('pp_collapsed_' + id) || '[]');
            ppState.collapsedSections = new Set(sc);
        } catch (_) { ppState.collapsedSections = new Set(); }
        // Aktive Sektion (Mittel-Spalte) wiederherstellen — wenn die gemerkte
        // Sektion nicht mehr existiert, faellt es auf 'all' zurueck. Loose-Vergleich,
        // weil row.id numerisch ist und der LocalStorage-Wert String.
        const savedSec = ppLoadActiveSection(id);
        const sectionExists = ppState.activeRows.some(r => r.row_type === 'section' && String(r.id) === String(savedSec));
        ppState.activeSection = (savedSec === 'all' || sectionExists) ? savedSec : 'all';
        ppState.sectionbarCollapsed = ppLoadSectionbarCollapsed();
        ppRenderEditor();
        ppRenderPlanList();
    } catch (e) { App.showNotification('Plan laden fehlgeschlagen: ' + e.message, 'error'); }
}

function ppCalcStats() {
    // Konsistent mit ppSectionSubtotal: nur Platzhalter werden ausgeschlossen.
    // "Kein Ticket noetig" Zeilen zaehlen mit, weil sie reale Arbeit darstellen.
    const items = ppState.activeRows.filter(r =>
        r.row_type === 'item' && !parseInt(r.is_placeholder)
    );
    let ist = 0, planned = 0, done = 0, offen = 0;
    items.forEach(r => {
        const h = parseFloat(r.planned_hours) || 0;
        ist += parseFloat(r.ist_hours) || 0;
        planned += h;
        if (parseInt(r.is_done)) done++;
        else                     offen += h; // Planstunden der noch nicht erledigten
    });
    return { ist, planned, done, offen, total: items.length };
}

/** Anwenden der Editor-Filter auf activeRows. Sektionen/Notes/Spacers werden mitgeführt
 *  (Sektion bleibt, wenn mindestens eine zugehörige Item-Zeile durchgeht). */
function ppFilteredRows() {
    const f = ppState.editorFilters;
    // Wenn eine einzelne Sektion aktiv ist, beschneiden wir activeRows vorher
    // auf den Bereich dieser Sektion und blenden die Sektions-Zeile selbst aus
    // (die Sektion ist ja schon der aktive Tab in der Mittel-Spalte).
    // Im Multi-Plan-Modus ignorieren wir den Section-Filter — dort gibt es
    // sectionsuebergreifende plan_header-Zeilen.
    let all = ppState.activeRows;
    const activeSec = ppState.activeSection || 'all';
    const isMulti = !!(ppState.activePlan && ppState.activePlan._isMulti);
    if (activeSec !== 'all' && !isMulti) {
        const startIdx = all.findIndex(r => r.row_type === 'section' && r.id == activeSec);
        if (startIdx >= 0) {
            let endIdx = all.length;
            for (let i = startIdx + 1; i < all.length; i++) {
                if (all[i].row_type === 'section') { endIdx = i; break; }
            }
            // Inklusive Sektions-Zeile selbst — sie liefert oben in der Tabelle
            // Titel + Subtotal + Aktions-Buttons (Pfeile zum Umsortieren).
            all = all.slice(startIdx, endIdx);
        } else {
            // Sektion existiert nicht mehr — Fallback auf 'all'
            ppState.activeSection = 'all';
        }
    }
    const matchesItem = (r) => {
        // Status-Filter
        if (!f.status.includes('all')) {
            for (const s of f.status) {
                if (s === 'open' && (parseInt(r.is_done) || parseInt(r.is_placeholder))) return false;
                if (s === 'done' && !parseInt(r.is_done)) return false;
                if (s === 'placeholder' && !parseInt(r.is_placeholder)) return false;
                if (s === 'no-asana' && r.asana_gid) return false;
                if (s === 'no-ticket' && !parseInt(r.no_ticket)) return false;
                // „Entscheidung offen" = echte eingeplante Aufgabe (kein Platzhalter),
                // weder mit Asana verknüpft noch als „Kein Ticket" markiert → User muss
                // sich entscheiden: Ticket verknüpfen oder bewusst auf „Kein Ticket" setzen.
                if (s === 'decision' && (r.asana_gid || parseInt(r.no_ticket) || parseInt(r.is_placeholder))) return false;
                if (s === 'focus' && !parseInt(r.is_focus)) return false;
                // Review: Zeilen mit gesetztem review_flag — Recovery-Helfer für Save-Bug-Zeitraum
                if (s === 'review' && !parseInt(r.review_flag)) return false;
            }
        }
        // Personen-Filter
        if (f.lead) {
            const lead = (r.lead_responsible || '').toLowerCase();
            if (!lead.includes(f.lead.toLowerCase())) return false;
        }
        if (f.responsible) {
            const resp = (r.responsible || '').toLowerCase();
            if (!resp.includes(f.responsible.toLowerCase())) return false;
        }
        // Suche
        if (f.search) {
            const haystack = `${r.description || ''} ${r.notes || ''} ${r.responsible || ''} ${r.lead_responsible || ''}`.toLowerCase();
            if (!haystack.includes(f.search.toLowerCase())) return false;
        }
        // Column-Filter
        if (f.col) {
            for (const [field, value] of Object.entries(f.col)) {
                if (!value) continue;
                if (field === 'responsible') {
                    const names = (r.responsible || '').split(',').map(s => s.trim());
                    if (!names.includes(value)) return false;
                } else if ((r[field] || '').trim() !== value) return false;
            }
        }
        return true;
    };

    const hasColFilter = f.col && Object.values(f.col).some(v => v);
    const filterActive = !f.status.includes('all') || f.lead || f.responsible || f.search || hasColFilter;
    const collapsed = ppState.collapsedSections;
    let working = all;

    // Bei Personen-Filter (Lead oder Umsetzung) sowie bei jedem Status-Filter
    // (außer 'all') Notizen + Spacer ausblenden — die haengen optisch an Items
    // und haben selbst keinen Status.
    const statusActive = !f.status.includes('all');
    const focusMode = !!(f.lead || f.responsible) || statusActive;

    if (filterActive) {
        // Items filtern. Sektionen + plan_header bleiben nur erhalten, wenn mindestens ein Item passt.
        // Notes/Spacer nur ohne Personen-Filter mitführen.
        const result = [];
        let lastSectionIdx = -1; let sectionHasMatch = false;
        let lastPlanHeaderIdx = -1; let planHasMatch = false;
        for (const r of all) {
            if (r.row_type === 'plan_header') {
                if (lastSectionIdx >= 0 && !sectionHasMatch) result.splice(lastSectionIdx, 1);
                if (lastPlanHeaderIdx >= 0 && !planHasMatch) result.splice(lastPlanHeaderIdx, 1);
                lastPlanHeaderIdx = result.length;
                planHasMatch = false;
                lastSectionIdx = -1; sectionHasMatch = false;
                result.push(r);
            } else if (r.row_type === 'section') {
                if (lastSectionIdx >= 0 && !sectionHasMatch) result.splice(lastSectionIdx, 1);
                lastSectionIdx = result.length;
                sectionHasMatch = false;
                result.push(r);
            } else if (r.row_type === 'item') {
                if (matchesItem(r)) { result.push(r); sectionHasMatch = true; planHasMatch = true; }
            } else if (r.row_type === 'note' || r.row_type === 'spacer') {
                if (!focusMode) result.push(r);
            } else {
                result.push(r);
            }
        }
        if (lastSectionIdx >= 0 && !sectionHasMatch) result.splice(lastSectionIdx, 1);
        if (lastPlanHeaderIdx >= 0 && !planHasMatch) result.splice(lastPlanHeaderIdx, 1);
        working = result;
    }

    // Collapsed Sections ausblenden (alles bis zur nächsten Section verstecken)
    if (collapsed && collapsed.size > 0) {
        const out = [];
        let hidingFromSection = null;
        for (const r of working) {
            if (r.row_type === 'section') {
                hidingFromSection = collapsed.has(r.id) ? r.id : null;
                out.push(r);
                continue;
            }
            if (hidingFromSection) continue;
            out.push(r);
        }
        working = out;
    }

    // Sort-Modus: nur Items zeigen, sortiert nach Feld. Sektionen/Notes/Spacer + plan_header werden ausgeblendet
    if (ppState.sortBy && ppState.sortBy.field) {
        const f = ppState.sortBy.field;
        const dir = ppState.sortBy.dir === 'desc' ? -1 : 1;
        const items = working.filter(r => r.row_type === 'item');
        items.sort((a, b) => {
            let va = a[f], vb = b[f];
            if (f === 'planned_hours' || f === 'ist_hours') {
                va = parseFloat(va) || 0; vb = parseFloat(vb) || 0;
                return (va - vb) * dir;
            }
            if (f === 'is_done' || f === 'is_focus' || f === 'is_placeholder') {
                return ((parseInt(va) || 0) - (parseInt(vb) || 0)) * dir;
            }
            // String-Vergleich (Beschreibung, Deadline-Datum, Zeitraum, Verantwortlich, Aufwand)
            va = String(va ?? ''); vb = String(vb ?? '');
            return va.localeCompare(vb, 'de') * dir;
        });
        return items;
    }

    return working;
}

/* ===== Bulk-Auswahl ===== */
function ppBulkToggle(id) {
    if (ppState.bulkSelection.has(id)) ppState.bulkSelection.delete(id);
    else ppState.bulkSelection.add(id);
    ppUpdateBulkBar();
    ppUpdateRowSelectionUI(id);
}
function ppBulkToggleAll(checked) {
    const visible = ppFilteredRows().filter(r => r.row_type === 'item');
    if (checked) visible.forEach(r => ppState.bulkSelection.add(r.id));
    else visible.forEach(r => ppState.bulkSelection.delete(r.id));
    ppUpdateBulkBar();
    visible.forEach(r => ppUpdateRowSelectionUI(r.id));
}
function ppBulkClear() {
    const ids = Array.from(ppState.bulkSelection);
    ppState.bulkSelection.clear();
    ppUpdateBulkBar();
    ids.forEach(id => ppUpdateRowSelectionUI(id));
}
function ppUpdateRowSelectionUI(rowId) {
    const tr = document.querySelector(`tr[data-row-id="${rowId}"]`);
    if (!tr) return;
    const cb = tr.querySelector('.pp-bulk-cb');
    const sel = ppState.bulkSelection.has(rowId);
    if (cb) cb.checked = sel;
    tr.classList.toggle('is-bulk-selected', sel);
}
function ppUpdateBulkBar() {
    const bar = document.getElementById('pp-bulk-bar');
    if (!bar) return;
    const n = ppState.bulkSelection.size;
    const cntEl = document.getElementById('pp-bulk-count');
    if (cntEl) cntEl.textContent = n;
    bar.style.display = n > 0 ? 'flex' : 'none';
    // Header-Checkbox an die effektive Auswahl anpassen
    const head = document.getElementById('pp-bulk-cb-all');
    if (head) {
        const visible = ppFilteredRows().filter(r => r.row_type === 'item');
        const visibleSelected = visible.filter(r => ppState.bulkSelection.has(r.id)).length;
        head.checked = visible.length > 0 && visibleSelected === visible.length;
        head.indeterminate = visibleSelected > 0 && visibleSelected < visible.length;
    }
}

/** Parallel-PUT für mehrere Zeilen mit gleichem Patch. Bestimmt plan_id pro Zeile aus _planId (Multi-Plan) oder activePlanId. */
async function ppBulkPatch(patch, label) {
    const ids = Array.from(ppState.bulkSelection);
    if (!ids.length) return;
    const reqs = ids.map(id => {
        const row = ppState.activeRows.find(r => r.id === id);
        if (!row) return Promise.resolve({ ok: false });
        const planId = row._planId || ppState.activePlanId;
        return fetch(`/api/v1/admin/projektplanner/plans/${planId}/rows/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(patch),
        }).then(r => r.json()).then(j => {
            if (j.success) {
                Object.assign(row, patch);  // optimistisch lokal anwenden
            }
            return j;
        }).catch(() => ({ success: false }));
    });
    const results = await Promise.all(reqs);
    const ok = results.filter(r => r.success).length;
    App.showNotification(`${ok}/${ids.length} ${label || 'aktualisiert'}`, ok === ids.length ? 'success' : 'error');
    ppBulkClear();
    ppRenderEditor();
}

async function ppBulkSetDone(done) {
    await ppBulkPatch({ is_done: done ? 1 : 0 }, done ? 'erledigt' : 'wieder offen');
}
async function ppBulkSetFocus(focus) {
    await ppBulkPatch({ is_focus: focus ? 1 : 0 }, focus ? 'fokussiert' : 'Fokus entfernt');
}
async function ppBulkSetLead(name) {
    await ppBulkPatch({ lead_responsible: name }, 'Hauptverantwortlich gesetzt');
}
async function ppBulkSetResponsible(name) {
    await ppBulkPatch({ responsible: name }, 'Umsetzung gesetzt');
}
async function ppBulkDelete() {
    const ids = Array.from(ppState.bulkSelection);
    if (!ids.length) return;
    if (!confirm(`${ids.length} Zeilen wirklich löschen?`)) return;
    const reqs = ids.map(id => {
        const row = ppState.activeRows.find(r => r.id === id);
        if (!row) return Promise.resolve({ success: false });
        const planId = row._planId || ppState.activePlanId;
        return fetch(`/api/v1/admin/projektplanner/plans/${planId}/rows/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': App.csrfToken },
        }).then(r => r.json()).catch(() => ({ success: false }));
    });
    const results = await Promise.all(reqs);
    const ok = results.filter(r => r.success).length;
    ppState.activeRows = ppState.activeRows.filter(r => !(ok && ppState.bulkSelection.has(r.id)));
    App.showNotification(`${ok}/${ids.length} gelöscht`, ok === ids.length ? 'success' : 'error');
    ppBulkClear();
    ppRenderEditor();
}

/* ===== Sortierung ===== */
function ppSetSort(field) {
    if (!ppState.sortBy || ppState.sortBy.field !== field) {
        ppState.sortBy = { field, dir: 'asc' };
    } else if (ppState.sortBy.dir === 'asc') {
        ppState.sortBy = { field, dir: 'desc' };
    } else {
        ppState.sortBy = null;
    }
    ppRenderEditor();
}
function ppSortArrow(field) {
    if (!ppState.sortBy || ppState.sortBy.field !== field) return '';
    return ppState.sortBy.dir === 'asc' ? ' ↑' : ' ↓';
}

function ppRenderEditor() {
    const p = ppState.activePlan;
    if (!p) return;
    const editor = document.getElementById('pp-editor');
    // Scroll-Position der Tabelle merken, damit Toggles (Platzhalter/Fokus/Done)
    // nicht zum Tabellen-Anfang zurueckspringen.
    const prevWrap = editor.querySelector('.pp-table-wrap');
    const prevScroll = prevWrap ? prevWrap.scrollTop : 0;
    const isMulti = !!(p && p._isMulti);
    editor.innerHTML =
        '<div class="pp-sticky-head" id="pp-sticky-head">' +
            ppRenderHeaderHtml(p) +
            ppRenderPermBannerHtml() +
            (isMulti ? ppRenderStatsBarHtml() : '') +
            ppRenderFilterBarHtml() +
        '</div>' +
        '<div class="pp-body">' +
            ppRenderSectionbarHtml() +
            '<div class="pp-body-main">' +
                ppRenderTableHtml() +
                ppRenderFooterHtml() +
            '</div>' +
        '</div>';
    ppAttachDragHandlers();
    ppApplyTabSkip();
    ppApplyColumnWidths();
    ppInstallColumnResizers();
    const newWrap = editor.querySelector('.pp-table-wrap');
    if (newWrap && prevScroll) newWrap.scrollTop = prevScroll;
}

/** Setzt tabindex=-1 auf gesperrte Inputs/contenteditables, damit Tab sie überspringt.
 *  Im Read-Only-Mode betrifft das alle editierbaren Felder; im Edit-Mode (eingeschränkt)
 *  nur die nicht für Edit freigegebenen Felder (Heuristik: Felder ohne data-perm-edit). */
function ppApplyTabSkip() {
    const body = document.body;
    const isRead = body.classList.contains('pp-perm-read');
    const isEditOnly = body.classList.contains('pp-perm-edit');
    if (!isRead && !isEditOnly) return;
    const wrap = document.querySelector('#pp-editor .pp-table-wrap');
    if (!wrap) return;
    const targets = wrap.querySelectorAll('[contenteditable="true"], input, select, button');
    targets.forEach(el => {
        if (isRead) { el.setAttribute('tabindex', '-1'); return; }
        // Edit-Mode: nur die NICHT für Edit-Felder unterdrücken
        // Heuristik: ein data-field auf der Whitelist (status/ist_hours/actual_hours/notes) bleibt fokussierbar
        const f = el.dataset?.field;
        const editAllowed = ['is_done', 'ist_hours', 'actual_hours', 'notes'].includes(f || '');
        if (!editAllowed && el.matches('[contenteditable], input:not([type=checkbox]), select')) {
            el.setAttribute('tabindex', '-1');
        }
    });
}

/** Beim Scrollen der Tabelle wird der Sticky-Head zu einer Compact-Bar. */
/* ===== Spaltenbreiten: per Drag verschiebbar, in localStorage gemerkt =====
   Globaler Speicher fuer ALLE Plaene (Key 'pp_col_widths', JSON-Map index -> px). */
const PP_COL_WIDTHS_KEY = 'pp_col_widths';

function ppGetSavedColumnWidths() {
    try { return JSON.parse(localStorage.getItem(PP_COL_WIDTHS_KEY) || '{}') || {}; }
    catch (_) { return {}; }
}

function ppApplyColumnWidths() {
    const cols = document.querySelectorAll('#pp-editor .pp-table colgroup col');
    if (!cols.length) return;
    const saved = ppGetSavedColumnWidths();
    cols.forEach((col, i) => {
        const v = saved[i];
        if (v == null) return;
        // Backwards-compat: alter Wert war eine reine Zahl (= Pixel)
        if (typeof v === 'number') col.style.width = v + 'px';
        else col.style.width = v; // String "120px" oder "18%"
    });
}

function ppInstallColumnResizers() {
    const table = document.querySelector('#pp-editor .pp-table');
    if (!table) return;
    const cols = table.querySelectorAll('colgroup col');
    const ths = table.querySelectorAll('thead th');
    if (!cols.length || !ths.length) return;

    ths.forEach((th, i) => {
        // Doppelt aufrufen schon vorhandene Resizer raeumen
        th.querySelectorAll('.pp-col-resizer').forEach(el => el.remove());

        const grip = document.createElement('div');
        grip.className = 'pp-col-resizer';
        grip.title = 'Spalte ziehen — Breite wird global gespeichert';
        grip.addEventListener('mousedown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const startX = e.clientX;
            const startWidth = ths[i].offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            const onMove = (ev) => {
                const newW = Math.max(28, startWidth + (ev.clientX - startX));
                cols[i].style.width = newW + 'px';
            };
            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                const saved = ppGetSavedColumnWidths();
                saved[i] = ths[i].offsetWidth + 'px';
                try { localStorage.setItem(PP_COL_WIDTHS_KEY, JSON.stringify(saved)); } catch (_) {}
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
        // Doppelklick auf Resizer setzt diese Spalte zurueck auf Default
        grip.addEventListener('dblclick', (e) => {
            e.preventDefault();
            e.stopPropagation();
            cols[i].style.removeProperty('width');
            const saved = ppGetSavedColumnWidths();
            delete saved[i];
            try { localStorage.setItem(PP_COL_WIDTHS_KEY, JSON.stringify(saved)); } catch (_) {}
        });
        // Rechtsklick: exakte Breite eingeben (px oder %)
        const onCtx = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const currentPx = ths[i].offsetWidth;
            const wrapW = table.parentElement?.offsetWidth || table.offsetWidth || 1;
            const currentPct = Math.round((currentPx / wrapW) * 100);
            const headerLabel = (ths[i].textContent || '').replace(/\s+/g, ' ').trim().substring(0, 30) || 'Spalte';
            const input = prompt(
                'Spaltenbreite für "' + headerLabel + '":\n' +
                '· Pixel mit oder ohne "px"  (z.B. 120 oder 120px)\n' +
                '· Prozent mit "%"           (z.B. 18%)\n' +
                'Leer + OK setzt auf Default zurück.',
                currentPx + 'px'
            );
            if (input === null) return; // Abbrechen
            const raw = input.trim();
            if (raw === '') {
                cols[i].style.removeProperty('width');
                const saved = ppGetSavedColumnWidths();
                delete saved[i];
                try { localStorage.setItem(PP_COL_WIDTHS_KEY, JSON.stringify(saved)); } catch (_) {}
                return;
            }
            let newWidth = null;
            if (raw.endsWith('%')) {
                const pct = parseFloat(raw.slice(0, -1));
                if (isFinite(pct) && pct > 0 && pct < 100) newWidth = pct + '%';
            } else {
                const px = parseInt(raw.replace(/px$/i, ''), 10);
                if (isFinite(px) && px >= 20 && px <= 2000) newWidth = px + 'px';
            }
            if (!newWidth) { alert('Ungültige Eingabe. Erlaubt: 20–2000 px oder 1–99 %.'); return; }
            cols[i].style.width = newWidth;
            const saved = ppGetSavedColumnWidths();
            saved[i] = newWidth;
            try { localStorage.setItem(PP_COL_WIDTHS_KEY, JSON.stringify(saved)); } catch (_) {}
        };
        grip.addEventListener('contextmenu', onCtx);
        // Auch Rechtsklick auf den Header (ohne Resizer-Treffer) zaehlt
        th.addEventListener('contextmenu', (e) => {
            if (e.target === grip) return; // Resizer hat eigenen Handler
            onCtx(e);
        });
        th.appendChild(grip);
    });
}

/* ppInstallStickyCompact() entfernt — der Scroll-Listener hat das is-compact-
   Toggle ausgeloest, was Reflows und sichtbares Flackern verursacht hat. Sticky-
   Header bleibt jetzt in einer Groesse stehen. */

function ppRenderPermBannerHtml() {
    const p = ppState.activePlan;
    const perm = p._permission;
    if (!perm || perm === 'owner' || perm === 'write') return '';
    if (perm === 'read') {
        return `<div class="pp-perm-banner is-read">
            <span class="material-symbols-rounded">visibility</span>
            Nur-Lesen-Zugriff — Du siehst alles, kannst aber nichts ändern.
            ${p.created_by_name ? `Eigentümer: <strong>${ppEscape(p.created_by_name)}</strong>` : ''}
        </div>`;
    }
    if (perm === 'edit') {
        return `<div class="pp-perm-banner is-edit">
            <span class="material-symbols-rounded">edit</span>
            Eingeschränkter Zugriff — Du kannst nur Status, Ist-Stunden, Aufwand und Bemerkungen ändern.
            ${p.created_by_name ? `Eigentümer: <strong>${ppEscape(p.created_by_name)}</strong>` : ''}
        </div>`;
    }
    return '';
}

function ppRenderHeaderHtml(p) {
    const unread = ppGetUnreadFeedbackCount();
    const asanaConnected = !!p.asana_project_gid;
    const custName = p.customer_name || '— Kein Kunde —';
    const statusLabels = {
        entwurf: 'Entwurf', aktiv: 'Aktiv', einzelprojekt: 'Einzelprojekt',
        reporting: 'Reporting', abgeschlossen: 'Abgeschlossen', archiviert: 'Archiviert'
    };
    const statusKey = p.plan_status || 'aktiv';
    const statusBadge = `<span class="pp-plan-status-badge is-${ppEscape(statusKey)}" title="Plan-Status">${ppEscape(statusLabels[statusKey] || statusKey)}</span>`;

    // Projekt-Status-Select (risiko_modus). Im Multi-Plan-Modus arbeitet er als
    // Massenbearbeitung ueber alle ausgewaehlten Plaene; bei uneinheitlichem
    // Status zeigt er "— gemischt —".
    const isMulti = !!p._isMulti;
    let rmVal = p.risiko_modus || 'auto';
    let rmMixed = false;
    if (isMulti) {
        const modi = [...new Set((ppState.multiPlans || []).map(x => x.risiko_modus || 'auto'))];
        rmMixed = modi.length > 1;
        rmVal = rmMixed ? '' : (modi[0] || 'auto');
    }
    const rmSel = v => (rmVal === v ? 'selected' : '');
    const risikoSelect = `
            <select class="thx-select" data-field="risiko_modus" data-id="${p.id || ''}"
                    onchange="${isMulti ? 'ppSavePlanRisikoBulk(this)' : 'ppSavePlanRisiko(this)'}"
                    title="${isMulti ? 'Projekt-Status für alle ' + (ppState.activePlanIds || []).length + ' ausgewählten Pläne auf einmal setzen' : 'Projekt-Status (Ampel) — jederzeit änderbar, auch nach Reporting/Abschluss'}">
                    ${isMulti && rmMixed ? '<option value="" selected>— gemischt —</option>' : ''}
                    <option value="auto"           ${rmSel('auto')}>⚙ In Arbeit</option>
                    <option value="eskaliert"      ${rmSel('eskaliert')}>🔥 Brennt</option>
                    <option value="gruen"          ${rmSel('gruen')}>✓ Erledigt</option>
                    <option value="nicht_relevant" ${rmSel('nicht_relevant')}>⏸ Läuft mit</option>
            </select>`;
    return `
    <div class="thx-page-header" style="margin:0;padding:var(--d-gutter);border-bottom:1px solid var(--slate-200);background:#fff;align-items:center;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
            <h1 class="thx-page-title" contenteditable="true" data-field="title" data-id="${p.id}"
                onblur="ppSavePlanField(this)"
                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                style="flex:0 1 auto;min-width:0;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${ppEscape(p.title || '')}</h1>
            ${statusBadge}
            <!-- Save-Indicator gleich neben dem Titel — verschiebt nichts wenn er ein-/ausgeblendet wird -->
            <span class="pp-saving-pill" id="pp-save-indicator"></span>
        </div>
        <div class="pp-head-controls" style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
            <input type="date" class="thx-input" style="min-width:120px;width:120px;" data-field="period_from" data-id="${p.id}"
                   value="${p.period_from || ''}" onblur="ppSavePlanField(this)" title="Zeitraum von">
            <span style="color:var(--slate-400);">–</span>
            <input type="date" class="thx-input" style="min-width:120px;width:120px;" data-field="period_to" data-id="${p.id}"
                   value="${p.period_to || ''}" onblur="ppSavePlanField(this)" title="Zeitraum bis">
            <select class="thx-select" data-field="plan_typ" data-id="${p.id}"
                    onchange="ppSavePlanField(this)" title="Projekt-Typ: Quartalsprojekt nutzt das laufende Kundenbudget, Einzelprojekt hat ein eigenes Festbudget (offer_ts).">
                    <option value="quartalsprojekt" ${(p.plan_typ || 'quartalsprojekt') === 'quartalsprojekt' ? 'selected' : ''}>Quartalsprojekt</option>
                    <option value="einzelprojekt"   ${p.plan_typ === 'einzelprojekt' ? 'selected' : ''}>Einzelprojekt</option>
            </select>
            <select class="thx-select" data-field="plan_status" data-id="${p.id}"
                    onchange="ppSavePlanField(this)" title="Workflow-Status">
                    <option value="entwurf"       ${p.plan_status === 'entwurf' ? 'selected' : ''}>Entwurf</option>
                    <option value="aktiv"         ${p.plan_status === 'aktiv' ? 'selected' : ''}>Aktiv</option>
                    <option value="einzelprojekt" ${p.plan_status === 'einzelprojekt' ? 'selected' : ''}>Einzelprojekt (alt)</option>
                    <option value="reporting"     ${p.plan_status === 'reporting' ? 'selected' : ''}>Reporting</option>
                    <option value="abgeschlossen" ${p.plan_status === 'abgeschlossen' ? 'selected' : ''}>Abgeschlossen</option>
                    <option value="archiviert"    ${p.plan_status === 'archiviert' ? 'selected' : ''}>Archiviert</option>
            </select>
            ${risikoSelect}
        </div>
        <div class="thx-page-actions" style="flex-shrink:0;">
            ${p.share_hash ? `
            <a href="/projektplan/${p.share_hash}" target="_blank" rel="noopener" class="thx-btn thx-btn-secondary thx-btn-small" title="Geteilte Ansicht öffnen" style="text-decoration:none;">
                <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span> Ansehen
            </a>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSharePlan()" title="Freigabe verwalten">
                <span class="material-symbols-rounded" style="font-size:14px;">settings</span> Teilen
            </button>` : `
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSharePlan()" title="Sharelink generieren">
                <span class="material-symbols-rounded" style="font-size:14px;">share</span> Teilen
            </button>`}
            <a href="/api/v1/admin/projektplanner/plans/${p.id}/export" class="thx-btn thx-btn-secondary thx-btn-small" title="Als Excel exportieren" data-perm-read="1" data-perm-edit="1" style="text-decoration:none;">
                <span class="material-symbols-rounded" style="font-size:14px;">download</span> Export
            </a>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppOpenAsanaConnect()"
                    title="${asanaConnected ? 'Asana-Projekt verknüpft' : 'Mit Asana-Projekt verknüpfen'}">
                <span class="material-symbols-rounded" style="font-size:14px;color:${asanaConnected ? 'var(--emerald-600)' : 'inherit'};">${asanaConnected ? 'link' : 'link_off'}</span>
                Asana${asanaConnected ? '<span class="pp-asana-dot"></span>' : ''}
            </button>
            <div class="pp-more-wrap">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppToggleMoreMenu(event)" title="Mehr Aktionen" style="position:relative;">
                    <span class="material-symbols-rounded" style="font-size:16px;">more_horiz</span>
                    ${unread > 0 ? `<span style="position:absolute;top:-4px;right:-4px;background:var(--rose-600);color:#fff;font-size:10px;font-weight:700;border-radius:9px;padding:1px 5px;min-width:14px;text-align:center;">${unread}</span>` : ''}
                </button>
                <div class="pp-more-menu" id="pp-more-menu" style="display:none;" onclick="event.stopPropagation()">
                    <button onclick="ppCloseMoreMenu();ppOpenCustomerChange()" data-pp-more="customer"><span class="material-symbols-rounded">business</span>Kunde: ${ppEscape(custName)}</button>
                    ${p.customer_id ? `<button onclick="ppCloseMoreMenu();ppOpenBudget()" data-pp-more="budget"><span class="material-symbols-rounded">euro_symbol</span>Budget</button>` : ''}
                    <button onclick="ppCloseMoreMenu();ppOpenRevisionsModal()" data-pp-more="verlauf"><span class="material-symbols-rounded">history</span>Verlauf</button>
                    <button onclick="ppCloseMoreMenu();ppDuplicatePlan()" data-pp-more="dupl"><span class="material-symbols-rounded">content_copy</span>Duplizieren</button>
                    <button onclick="ppCloseMoreMenu();ppOpenSparring()" data-pp-more="sparring"><span class="material-symbols-rounded">forum</span>KI-Sparring</button>
                    <button onclick="ppCloseMoreMenu();ppOpenAiRules()" data-pp-more="ki-rules"><span class="material-symbols-rounded">school</span>KI-Regeln (Lernschleife)</button>
                    <button onclick="ppCloseMoreMenu();ppOpenFeedbackModal()" data-pp-more="feedback"><span class="material-symbols-rounded">chat</span>Feedback${unread > 0 ? `<span class="pp-more-badge">${unread}</span>` : ''}</button>
                    <a href="/admin/projektplanner/import" data-pp-more="import"><span class="material-symbols-rounded">upload</span>Import</a>
                    <button onclick="ppCloseMoreMenu();ppPrintPlan()" data-pp-more="print"><span class="material-symbols-rounded">picture_as_pdf</span>Drucken / PDF</button>
                    <div class="pp-more-sep"></div>
                    <button onclick="ppCloseMoreMenu();ppSyncKnowledge(this)" data-pp-more="kb-sync"><span class="material-symbols-rounded pp-spin-target">cloud_sync</span>Wissensdatenbank synchronisieren</button>
                    ${asanaConnected ? `<div class="pp-more-sep"></div>
                    <button onclick="ppCloseMoreMenu();ppAsanaCacheRefresh()" data-pp-more="asana-cache"><span class="material-symbols-rounded">refresh</span>Asana-Cache leeren</button>` : ''}
                </div>
            </div>
            ${p.state == 2 ? `
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppRestorePlan()" title="Aus Archiv wiederherstellen">
                    <span class="material-symbols-rounded" style="font-size:14px;color:var(--emerald-600);">restore_from_trash</span>
                </button>
            ` : `
                <button class="thx-btn thx-btn-secondary thx-btn-small thx-btn-danger" onclick="ppDeletePlan()" title="Plan löschen">
                    <span class="material-symbols-rounded" style="font-size:14px;">delete</span>
                </button>
            `}
        </div>
    </div>`;
}

/** Öffnet ein kleines Modal zum Wechseln des Plan-Kunden. */
function ppOpenCustomerChange() {
    const p = ppState.activePlan;
    if (!p) return;
    const opts = '<option value="">— Kein Kunde —</option>' + ppState.customers.map(c =>
        `<option value="${c.id}" ${c.id == p.customer_id ? 'selected' : ''}>${ppEscape(c.name)}</option>`).join('');
    const body = document.getElementById('pp-customer-change-body');
    if (!body) return;
    body.innerHTML = `
        <div class="pp-field">
            <label>Kunde</label>
            <select id="pp-customer-change-select" class="thx-select" style="width:100%;">${opts}</select>
        </div>
        <p style="margin:8px 0 0;color:var(--slate-500);font-size:var(--d-fs-xs);">
            Ein Wechsel des Kunden lädt das Budget neu und ordnet den Plan einem anderen Kunden zu.
        </p>`;
    ppOpenModal('pp-customer-change-modal');
}

/** Übernimmt die Auswahl aus dem Kunden-Wechsel-Modal. */
async function ppApplyCustomerChange() {
    const sel = document.getElementById('pp-customer-change-select');
    if (!sel) return;
    const p = ppState.activePlan;
    if (!p) return;
    const newVal = sel.value || null;
    if (String(newVal || '') === String(p.customer_id || '')) {
        ppCloseModal('pp-customer-change-modal');
        return;
    }
    ppShowSaving('saving');
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + p.id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ customer_id: newVal }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        p.customer_id = newVal;
        const idx = ppState.plans.findIndex(x => x.id === p.id);
        const c = ppState.customers.find(x => x.id == newVal);
        if (idx >= 0) {
            ppState.plans[idx].customer_id = newVal;
            ppState.plans[idx].customer_name = c ? c.name : null;
            ppState.plans[idx].customer_color = c ? c.hex_color : null;
        }
        p.customer_name = c ? c.name : null;
        p.customer_color = c ? c.hex_color : null;
        try {
            const bRes = await fetch('/api/v1/admin/projektplanner/plans/' + p.id + '/budget-soll').then(r => r.json());
            if (bRes.success) { ppState.planBudget = bRes.data; ppUpdateStatsBar(); }
        } catch (_) {}
        ppRenderPlanList();
        ppRenderEditor();
        ppShowSaving('saved');
        ppCloseModal('pp-customer-change-modal');
    } catch (e) {
        App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
        ppShowSaving('error');
    }
}

/** Status-Pill fuer den Wissensdatenbank-Sync.
 *  Default: grau, dezent. NUR bei Fehler rot — dann klickbar (zeigt Fehler + Retry).
 *  Im Erfolgsfall (synced) ist der Pill klickbar und oeffnet die Quelle in /wissen. */
function ppRenderKnowledgePill(p) {
    if (!p || !p.id || p.state == 2) return '';
    const synced = p.knowledge_synced_at || null;
    const docId  = p.knowledge_doc_id || null;
    const dirty  = parseInt(p.knowledge_dirty || 0);
    const err    = p.knowledge_sync_error || '';
    const hasCustomer = !!p.customer_id;
    const isDraft = p.plan_status === 'entwurf';
    const canSync = hasCustomer && !isDraft; // Pflicht-Voraussetzungen fuer Sync

    // FEHLER -> rot, klickbar
    if (err !== '') {
        return `<span class="pp-kb-pill is-error" title="Klick für Details + Retry"
            onclick="ppShowKnowledgeError()">
            <span class="material-symbols-rounded">error</span>Sync-Fehler
        </span>`;
    }

    // Sonst: alles grau, dezent
    let label, title, openHref = null;
    if (!hasCustomer) {
        label = 'Kein Kunde — kein Sync';
        title = 'Plan ohne Kundenzuordnung — kein Wissensdatenbank-Sync';
    } else if (isDraft) {
        label = 'Entwurf — kein Sync';
        title = 'Entwürfe werden nicht synchronisiert';
    } else if (dirty) {
        label = 'Sync ausstehend';
        title = 'Änderung erkannt — laufender Cron synct in unter 90 Sek. Resync-Button startet sofort.';
    } else if (synced && docId) {
        label = 'Synced ' + ppRelTime(synced);
        title = 'In Wissensdatenbank · ' + synced + ' (Klick öffnet Quelle)';
        openHref = '/wissen/' + docId;
    } else {
        label = 'Noch nicht synchronisiert';
        title = 'Plan wurde noch nicht in die Wissensdatenbank übertragen — Resync-Button startet jetzt.';
    }

    // Resync-Button: zeigt nur wenn ein Sync ueberhaupt moeglich ist
    const resyncBtn = canSync ? `
        <button class="pp-kb-pill-resync" onclick="event.stopPropagation();ppSyncKnowledge(this)"
                title="Jetzt synchronisieren">
            <span class="material-symbols-rounded pp-spin-target">refresh</span>
        </button>` : '';

    // Pill-Wrapper — clickable wenn Quelle offen, sonst nur Status
    const wrapperOnclick = openHref ? `onclick="window.open('${openHref}', '_blank')"` : '';
    return `<span class="pp-kb-pill ${openHref ? 'is-clickable' : ''}" title="${ppEscape(title)}" ${wrapperOnclick}>
        <span class="material-symbols-rounded">cloud_sync</span>
        <span class="pp-kb-pill-label">${ppEscape(label)}</span>
        ${resyncBtn}
    </span>`;
}

/** Zeigt die Sync-Fehlermeldung + Retry-Button. */
async function ppShowKnowledgeError() {
    const p = ppState.activePlan;
    if (!p) return;
    const err = p.knowledge_sync_error || 'Unbekannter Fehler';
    if (!confirm('Sync-Fehler:\n\n' + err + '\n\nJetzt erneut versuchen?')) return;
    await ppSyncKnowledge(null);
}

/** Format relative Zeit ('vor 2 Min', 'vor 3 Tagen'). */
function ppRelTime(dateStr) {
    if (!dateStr) return '';
    const t = new Date(dateStr.replace(' ', 'T')).getTime();
    if (!t) return '';
    const sec = Math.max(1, Math.round((Date.now() - t) / 1000));
    if (sec < 60)      return 'gerade eben';
    if (sec < 3600)    return 'vor ' + Math.round(sec / 60) + ' Min';
    if (sec < 86400)   return 'vor ' + Math.round(sec / 3600) + ' Std';
    if (sec < 2592000) return 'vor ' + Math.round(sec / 86400) + ' Tagen';
    return 'vor ' + Math.round(sec / 2592000) + ' Mon';
}

/** Manuell den Sync für diesen Plan triggern. */
async function ppSyncKnowledge(btnEl) {
    const id = ppState.activePlanId;
    if (!id) return;
    const spin = btnEl?.querySelector('.material-symbols-rounded');
    if (spin) spin.classList.add('pp-spin');
    ppShowSaving('saving');
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + id + '/sync-knowledge', {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Sync fehlgeschlagen');
        const a = j.data?.action || 'sync';
        const msg = { created: 'In Wissensdatenbank angelegt', updated: 'Wissensdatenbank aktualisiert',
                      removed: 'Aus Wissensdatenbank entfernt', skipped: 'Übersprungen: ' + (j.data?.reason || '—') }[a] || 'Synced';
        App.showNotification(msg, a === 'skipped' ? 'info' : 'success');
        // Plan-Daten neu laden (für aktualisierte knowledge_doc_id/synced_at)
        try {
            const pr = await fetch('/api/v1/admin/projektplanner/plans/' + id);
            const pj = await pr.json();
            if (pj.success && pj.data?.plan) {
                Object.assign(ppState.activePlan, {
                    knowledge_doc_id:     pj.data.plan.knowledge_doc_id,
                    knowledge_synced_at:  pj.data.plan.knowledge_synced_at,
                    knowledge_dirty:      pj.data.plan.knowledge_dirty,
                });
            }
        } catch (_) {}
        ppRenderEditor();
        ppShowSaving('saved');
    } catch (e) {
        App.showNotification(e.message, 'error');
        ppShowSaving('error');
    } finally {
        if (spin) spin.classList.remove('pp-spin');
    }
}

/** Öffnet/schließt das ⋯ Mehr-Menü und schließt es beim Klick außerhalb. */
function ppToggleMoreMenu(ev) {
    if (ev) ev.stopPropagation();
    const m = document.getElementById('pp-more-menu');
    if (!m) return;
    const isOpen = m.style.display !== 'none';
    if (isOpen) { m.style.display = 'none'; return; }
    m.style.display = 'block';
    setTimeout(() => {
        const close = (e) => {
            if (!m.contains(e.target)) { m.style.display = 'none'; document.removeEventListener('click', close); }
        };
        document.addEventListener('click', close);
    }, 0);
}
function ppCloseMoreMenu() {
    const m = document.getElementById('pp-more-menu');
    if (m) m.style.display = 'none';
}

/** Deutsche Zahlen-Formatierung: ohne unnoetige Nachkommastellen.
 *  Ganze Zahlen → "1", "2"; Halbe → "0,5", "1,5"; Viertel → "0,25", "1,75".
 *  Maximal 2 Nachkommastellen, ueberschuessige Nullen werden geschnitten.
 *  Beispiele: 0.5 -> "0,5"  ·  3 -> "3"  ·  1.25 -> "1,25"  ·  1.5 -> "1,5". */
function ppFormatNum(v) {
    if (v === null || v === undefined || v === '') return '0';
    const n = (typeof v === 'number') ? v : parseFloat(String(v).replace(',', '.'));
    if (!isFinite(n)) return '0';
    // Auf max. 2 Stellen runden, dann ueberschuessige Nullen abschneiden
    const rounded = Math.round(n * 100) / 100;
    let s = rounded.toFixed(2);  // "1.00", "0.50", "1.25"
    s = s.replace(/\.?0+$/, ''); // -> "1", "0.5", "1.25"
    return s.replace('.', ',');
}

function ppHoursToTs(h) {
    if (h <= 0) return 0;
    const full = Math.floor(h / 8);
    const rest = h - full * 8;
    return full + (rest >= 4 ? 0.5 : 0);
}

/* ===== Mittel-Spalte: Sektions-Tabs + KPI-Widget ===== */
function ppRenderSectionbarHtml() {
    // Multi-Plan: in der kombinierten Ansicht macht eine Sektions-Auswahl keinen
    // Sinn (Sektionen mehrerer Plaene werden vermischt). Spalte komplett aus.
    if (ppState.activePlan && ppState.activePlan._isMulti) return '';
    const collapsed = !!ppState.sectionbarCollapsed;
    const sections = ppState.activeRows.filter(r => r.row_type === 'section');
    const active = ppState.activeSection || 'all';

    // Pro Tab: Anzahl Items + Ist/Geplant-Subline. "Alle" zeigt die Plan-Summe.
    const tabFor = (id, label) => {
        if (id === 'all') {
            const s = ppCalcStats();
            return { id, label, count: s.total, ist: s.ist, planned: s.planned };
        }
        const sub = ppSectionSubtotal(id);
        return { id, label, count: sub.count, ist: sub.ist, planned: sub.soll };
    };
    const tabs = [tabFor('all', 'Alle Sektionen')]
        .concat(sections.map(s => tabFor(s.id, (s.description || '').trim() || '— ohne Titel —')));

    const sublineHtml = (t) => {
        if (!t.ist && !t.planned) {
            return `<span class="pp-sb-tab-sub pp-sb-tab-sub-empty">noch leer</span>`;
        }
        const istTs = ppHoursToTs(t.ist);
        const plnTs = ppHoursToTs(t.planned);
        return `<span class="pp-sb-tab-sub">
            <span class="pp-sb-sub-chip" title="Ist: ${ppFormatNum(t.ist)} h / ${ppFormatNum(istTs)} TS">
                <span class="material-symbols-rounded">history</span>
                ${ppFormatNum(t.ist)} h <span class="pp-sb-sub-ts">${ppFormatNum(istTs)} TS</span>
            </span>
            <span class="pp-sb-sub-chip" title="Geplant: ${ppFormatNum(t.planned)} h / ${ppFormatNum(plnTs)} TS">
                <span class="material-symbols-rounded">event</span>
                ${ppFormatNum(t.planned)} h <span class="pp-sb-sub-ts">${ppFormatNum(plnTs)} TS</span>
            </span>
        </span>`;
    };

    if (collapsed) {
        const strokes = tabs.map(t => `
            <button class="pp-sb-stroke ${t.id === 'all' ? 'is-all' : ''} ${t.id == active ? 'is-active' : ''}"
                    onclick="ppSetActiveSection('${ppEscape(String(t.id))}')"
                    data-preview="${ppEscape(t.label)} · ${t.count} · Ist ${ppFormatNum(t.ist)} h · Geplant ${ppFormatNum(t.planned)} h"
                    title="${ppEscape(t.label)}"></button>
        `).join('');
        return `
        <aside class="pp-sectionbar is-collapsed" id="pp-sectionbar">
            <div class="pp-sb-head">
                <button class="pp-sb-toggle" onclick="ppToggleSectionbar()" title="Ausklappen">
                    <span class="material-symbols-rounded">chevron_right</span>
                </button>
            </div>
            <div class="pp-sb-strokes">${strokes}</div>
        </aside>`;
    }

    const tabsHtml = tabs.map(t => {
        const isAll = t.id === 'all';
        return `
        <button class="pp-sb-tab ${isAll ? 'is-all' : ''} ${t.id == active ? 'is-active' : ''}"
                onclick="ppSetActiveSection('${ppEscape(String(t.id))}')"
                data-section-id="${ppEscape(String(t.id))}"
                title="${ppEscape(t.label)}">
            <span class="pp-sb-tab-main">
                <span class="pp-sb-tab-label">${ppEscape(t.label)}</span>
                <span class="pp-sb-tab-count">${t.count}</span>
            </span>
            ${sublineHtml(t)}
        </button>`;
    }).join('');

    return `
    <aside class="pp-sectionbar" id="pp-sectionbar">
        <div class="pp-sb-head">
            <span class="pp-sb-head-title">Sektionen</span>
            <button class="pp-sb-toggle" onclick="ppToggleSectionbar()" title="Einklappen">
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
        </div>
        <div class="pp-sb-tabs">${tabsHtml}</div>
        ${ppRenderKpiWidgetHtml()}
    </aside>`;
}


/** Berechnet die KPI-Snapshot-Daten. */
function ppKpiSnapshot() {
    const s = ppCalcStats();
    const budgetH = parseFloat(ppState.planBudget.soll_h) || 0;
    const budgetTs = parseFloat(ppState.planBudget.soll_ts) || 0;
    const months = parseInt(ppState.planBudget.months) || 0;
    const tsPerMonth = months > 0 && budgetTs > 0 ? (budgetTs / months) : 0;
    // Carryover/Übertrag aus reporteten Vorperioden:
    // + = Überhang (wir haben überliefert) → effektiv weniger zu leisten
    // − = Unterdeckung (Kunde hat Vorschuss) → effektiv mehr zu leisten
    const carryTs = parseFloat(ppState.planBudget.carryover_ts) || 0;
    const carryH  = parseFloat(ppState.planBudget.carryover_h) || 0;
    // Effektiver Soll = Standard − Überhang
    const effSollH  = Math.max(0, budgetH  - carryH);
    const effSollTs = Math.max(0, budgetTs - carryTs);
    // Spielraum berechnen wir jetzt gegen den effektiven Soll (also unter
    // Berücksichtigung von Carryover). Mehr geplant als effektiv Soll → positiv.
    const gap = effSollH > 0 ? (s.planned - effSollH) : (s.planned - s.ist);

    let budgetClass = '';
    if (effSollH > 0) {
        const ratio = s.planned / effSollH;
        budgetClass = ratio > 1 ? 'is-budget-over' : (ratio > 0.9 ? 'is-budget-warn' : 'is-budget-ok');
    }
    let gapClass = 'is-gap-neutral';
    if (effSollH > 0) gapClass = gap > 0 ? 'is-gap-ok' : (gap < -effSollH * 0.1 ? 'is-gap-over' : 'is-gap-warn');

    const placeholders = ppState.activeRows.filter(r => r.row_type === 'item' && !parseInt(r.no_ticket) && parseInt(r.is_placeholder));
    const phSoll = placeholders.reduce((a, r) => a + (parseFloat(r.planned_hours) || 0), 0);

    const progress = s.total > 0 ? Math.round(s.done / s.total * 100) : 0;
    const progressClass = progress >= 75 ? 'is-ok' : (progress >= 40 ? 'is-warn' : 'is-over');

    return {
        ist: s.ist, planned: s.planned, done: s.done, offen: s.offen, total: s.total,
        budgetH, budgetTs, months, tsPerMonth, gap, phSoll,
        carryTs, carryH, effSollH, effSollTs,
        budgetClass, gapClass, progress, progressClass,
        kbPill: ppRenderKnowledgePill(ppState.activePlan || {}),
    };
}

/** KPI-Widget — Plan-Karte: Kunden-Identitaet oben, Plan-Titel als Headline,
 *  Projekt-Info (Briefing-artig) darunter, dann Donut + Aufwand-Kacheln +
 *  Spielraum. Wird sowohl in der Mittel-Spalte des Projektplanners als auch
 *  (spaeter) als Karte auf der Kunden-Detailseite genutzt.
 *  Wichtig: alle drei Kacheln und die Stack-Bar beziehen sich auf den
 *  EFFEKTIVEN Soll (Standard − Übertrag). Standard kommt als Kontext-Info. */
function ppRenderKpiWidgetHtml() {
    const k = ppKpiSnapshot();
    const p = ppState.activePlan || {};
    // Effektiv-Soll als Bezugsgroesse — Carryover ist bereits eingerechnet.
    const refSollH  = k.effSollH > 0 ? k.effSollH : k.budgetH;
    const refSollTs = k.effSollTs > 0 ? k.effSollTs : k.budgetTs;
    // Stack-Bar-Skalierung: Bar-Maximum = max(soll, geplant, ist). Damit erkennt
    // man eine Überplanung (Geplant > Soll) als roten "Über-Soll"-Bereich rechts
    // vom Soll-Marker. Bei Unterplanung erscheint stattdessen "Rest Soll".
    const refMax = Math.max(refSollH, k.planned, k.ist) || 1;
    const istEnd  = refSollH > 0 ? (k.ist     / refMax) * 100 : 0;
    const planEnd = refSollH > 0 ? (k.planned / refMax) * 100 : 0;
    const sollEnd = refSollH > 0 ? (refSollH  / refMax) * 100 : 100;
    // Segment-Breiten
    const segIst             = istEnd;
    const segPlannedInSoll   = Math.max(0, Math.min(planEnd, sollEnd) - istEnd);
    const segOverplanned     = Math.max(0, planEnd - sollEnd);
    const segRestSoll        = Math.max(0, sollEnd - Math.max(planEnd, istEnd));
    const isOverplanned = segOverplanned > 0.001;
    // Legacy-Variablen fuer das alte Markup beibehalten (sonst muss ich tiefer eingreifen)
    const pctIst = istEnd;
    const pctIstSeg = segIst;
    const pctPlannedSeg = segPlannedInSoll;
    const donutDeg = k.progress * 3.6;

    const cell = (cls, label, h, ts) => `
        <div class="pp-kpi-cell ${cls}">
            <div class="pp-kpi-cell-label">${label}</div>
            <div class="pp-kpi-cell-num">${ppFormatNum(h)} <span class="pp-kpi-cell-unit">h</span></div>
            <div class="pp-kpi-cell-sub">${ts != null && (h > 0 || cls === 'is-soll') ? ppFormatNum(ts) + ' TS' : '—'}</div>
        </div>`;

    // Kunden-Identitaet — Logo wenn vorhanden, sonst Kuerzel-Badge.
    // Daneben Kunde + Kuerzel + optional Website-Link mit Extern-Icon.
    const custName = p.customer_name || '';
    const custAbbr = (p.customer_abbr || (custName || p.title || '?').substr(0, 3)).toUpperCase();
    const custLogo = p.customer_logo ? `/uploads/customers/logos/${p.customer_logo.split('/').pop()}` : null;
    let custWebsiteHref = (p.customer_website || '').trim();
    let custWebsiteLabel = '';
    if (custWebsiteHref) {
        // Ohne Protokoll = relative URL annehmen; mit Protokoll = lassen
        if (!/^https?:\/\//i.test(custWebsiteHref)) custWebsiteHref = 'https://' + custWebsiteHref;
        try {
            const u = new URL(custWebsiteHref);
            custWebsiteLabel = u.hostname.replace(/^www\./, '');
        } catch (_) { custWebsiteLabel = custWebsiteHref; }
    }
    // Reduziert: Nur Logo (klickbar zur Kunden-Detailseite) + Website (extern).
    // Name + Kuerzel sind redundant — das Logo zeigt schon, um wen es geht.
    const custDetailUrl = p.customer_id ? `/admin/customers/${p.customer_id}/steckbrief` : null;
    const logoEl = custLogo
        ? `<img class="pp-kpi-cust-logo" src="${ppEscape(custLogo)}" alt="${ppEscape(custName)}">`
        : `<span class="pp-kpi-cust-abbr">${ppEscape(custAbbr)}</span>`;
    // Haupt-Ansprechpartner — wird aus der Steckbrief-Kontaktkarte des Kunden gezogen
    // (erste Person der ersten Gruppe). Pflege auf der Kunden-Detailseite, nicht hier.
    const contactName  = (p.customer_main_contact_name  || '').trim();
    const contactRole  = (p.customer_main_contact_role  || '').trim();
    const contactEmail = (p.customer_main_contact_email || '').trim();
    const contactInitials = (p.customer_main_contact_initials || '').trim().toUpperCase()
        || contactName.split(/\s+/).map(s => s.charAt(0).toUpperCase()).slice(0, 2).join('');
    let contactHtml = '';
    if (contactName) {
        const emailHref = contactEmail ? `mailto:${contactEmail}` : null;
        contactHtml = `
            <div class="pp-kpi-contact" ${emailHref ? `onclick="event.stopPropagation(); window.location.href='${ppEscape(emailHref)}'" style="cursor:pointer;" title="${ppEscape(contactEmail)} schreiben"` : ''}>
                <span class="pp-kpi-contact-avatar">${ppEscape(contactInitials || '?')}</span>
                <div class="pp-kpi-contact-meta">
                    <div class="pp-kpi-contact-name">${ppEscape(contactName)}</div>
                    ${contactRole ? `<div class="pp-kpi-contact-role">${ppEscape(contactRole)}</div>` : ''}
                </div>
                ${contactEmail ? `<span class="material-symbols-rounded pp-kpi-contact-mail">mail</span>` : ''}
            </div>`;
    }

    const custBlock = custName ? `
        <div class="pp-kpi-cust">
            ${custDetailUrl
                ? `<a class="pp-kpi-cust-link" href="${ppEscape(custDetailUrl)}" title="${ppEscape(custName)} öffnen">${logoEl}</a>`
                : logoEl}
            ${custWebsiteHref ? `
                <a class="pp-kpi-cust-web" href="${ppEscape(custWebsiteHref)}" target="_blank" rel="noopener"
                   title="${ppEscape(custWebsiteHref)}">
                    ${ppEscape(custWebsiteLabel)}
                    <span class="material-symbols-rounded">open_in_new</span>
                </a>` : ''}
        </div>
        ${contactHtml}` : '';

    // Projekt-Info aus dem Kunden-Abrechnungs-Profil zusammensetzen:
    //   "4 TS/Monat · bi-monatlich · Übertrag 2025 +2 TS · siehe Bemerkungen"
    const billingModelLabels = {
        fix_monatlich: 'fester Retainer · monatlich',
        fix_bimonatlich: 'fester Retainer · 2-monatlich',
        fix_quartalsweise: 'fester Retainer · quartalsweise',
        zuruf_monat: 'auf Zuruf · monatlich',
        zuruf_quartal: 'auf Zuruf · quartalsweise',
        einzelprojekt: 'Einzelprojekt',
    };
    const infoParts = [];
    const isEinzelprojekt = p.plan_status === 'einzelprojekt';
    if (isEinzelprojekt) {
        // Einzelprojekt: zeigt das Angebot statt Retainer-TS/Monat
        infoParts.push('Einzelprojekt');
        const offerTs = parseFloat(p.offer_ts || 0);
        if (offerTs > 0) infoParts.push(`Angebot: ${ppFormatNum(offerTs)} TS`);
        else infoParts.push('Angebot noch nicht gepflegt');
    } else {
        if (p.customer_ts_per_month && parseFloat(p.customer_ts_per_month) > 0) {
            infoParts.push(`${ppFormatNum(p.customer_ts_per_month)} TS/Monat`);
        }
        if (p.customer_billing_model && billingModelLabels[p.customer_billing_model]) {
            infoParts.push(billingModelLabels[p.customer_billing_model]);
        }
    }
    // Vorjahres-Übertrag wird NICHT hier oben angezeigt — er fliesst unten in
    // den Carryover-Block ein (Plan-bezogener effektiver Soll).
    const customNotes = (p.customer_billing_notes || '').trim();
    const summary = infoParts.length > 0 ? infoParts.join(' · ') : '';
    const hasInfo = !!(summary || customNotes);

    const infoBlock = `
        <div class="pp-kpi-info ${hasInfo ? '' : 'is-placeholder'}"
             ${p.customer_id ? `onclick="ppOpenBudget()" style="cursor:pointer;" title="Abrechnung öffnen"` : ''}>
            ${hasInfo
                ? `${summary ? `<span class="pp-kpi-info-summary">${ppEscape(summary)}</span>` : ''}
                   ${customNotes ? `<span class="pp-kpi-info-notes">${ppEscape(customNotes)}</span>` : ''}`
                : 'Abrechnungs-Profil noch nicht gepflegt — Klick öffnet die Abrechnungs-Konfiguration.'}
        </div>`;

    // Projekt-Status als Pill (risiko_modus) — gleiche Zustaende/Farben wie Dashboard + Kopfleiste.
    const _rm = p.risiko_modus || 'auto';
    const _rmMap = {
        auto:           { icon: '⚙', label: 'In Arbeit',  color: '#475569', bg: '#eef2f7' },
        eskaliert:      { icon: '🔥', label: 'Brennt',     color: '#b91c1c', bg: '#fef2f2' },
        gruen:          { icon: '✓', label: 'Erledigt',   color: '#047857', bg: '#f0fdf4' },
        nicht_relevant: { icon: '⏸', label: 'Läuft mit',  color: '#475569', bg: '#f1f5f9' },
    };
    const _rmi = _rmMap[_rm] || _rmMap.auto;
    const _rmNotiz = (p.risiko_notiz || '').toString().trim();
    const statusPillHtml = `<span class="pp-kpi-status-pill" title="Projekt-Status${_rmNotiz ? ' — ' + ppEscape(_rmNotiz) : ''}" style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;background:${_rmi.bg};color:${_rmi.color};white-space:nowrap;">${_rmi.icon} ${ppEscape(_rmi.label)}</span>`;

    // Kein Kontingent für den Zeitraum dieses Plans (auf Zuruf / pausiert): Basis-Soll = 0.
    // WICHTIG: am Basis-Soll (k.budgetH) festmachen, nicht am effektiven — der Kunden-Übertrag
    // darf bei einem Zuruf-Plan kein Phantom-Soll erzeugen. Dann: kein Soll/Spielraum/Übertrag.
    const noSoll = k.budgetH <= 0;

    return `
    <div class="pp-kpi-widget" id="pp-kpi-widget">
        ${custBlock}
        ${infoBlock}
        <div class="pp-kpi-head">
            <a class="pp-kpi-plan-title" href="${p.id ? '/admin/projektplanner/plan/' + p.id : '#'}"
               title="${ppEscape(p.title || '')}">${ppEscape(p.title || 'Plan')}</a>
        </div>
        <div style="margin:2px 0 6px;">${statusPillHtml}</div>
        <div class="pp-kpi-donut-row">
            <div class="pp-kpi-donut" style="--deg:${donutDeg}deg;" title="${k.done} von ${k.total} erledigt">
                <div class="pp-kpi-donut-inner">
                    <span class="pp-kpi-donut-pct">${k.progress}%</span>
                    <span class="pp-kpi-donut-sub">${k.done}/${k.total}</span>
                </div>
            </div>
            <div class="pp-kpi-donut-meta">
                <div class="pp-kpi-meta-label">Erledigt</div>
                <div class="pp-kpi-meta-strong">${k.done} <span class="pp-kpi-sub">/ ${k.total}</span></div>
                ${!noSoll && refSollH > 0 ? `<div class="pp-kpi-meta-sub">Ist: ${ppFormatNum(pctIst)} % vom Soll</div>` : ''}
            </div>
        </div>
        ${k.budgetH > 0 ? `
        <div class="pp-kpi-stack ${isOverplanned ? 'is-overplanned' : ''}"
             title="Aufteilung gegen ${isEinzelprojekt ? 'Angebot' : 'Soll'}${isOverplanned ? ' — überplant um ' + ppFormatNum(k.planned - refSollH) + ' h' : ''}">
            <span class="pp-kpi-stack-seg is-ist" style="width:${segIst}%"></span>
            <span class="pp-kpi-stack-seg is-planned" style="width:${segPlannedInSoll}%"></span>
            ${isOverplanned
                ? `<span class="pp-kpi-stack-seg ${isEinzelprojekt ? 'is-over-warn' : 'is-over'}" style="width:${segOverplanned}%"></span>`
                : `<span class="pp-kpi-stack-seg is-rest" style="width:${segRestSoll}%"></span>`}
            ${isOverplanned ? `<span class="pp-kpi-stack-marker" style="left:${sollEnd}%" title="${isEinzelprojekt ? 'Angebots-Grenze' : 'Soll-Linie'}"></span>` : ''}
        </div>
        <div class="pp-kpi-stack-legend">
            <span><i class="pp-kpi-dot is-ist"></i>Ist</span>
            <span><i class="pp-kpi-dot is-planned"></i>Geplant</span>
            ${isOverplanned
                ? `<span><i class="pp-kpi-dot ${isEinzelprojekt ? 'is-over-warn' : 'is-over'}"></i>${isEinzelprojekt ? 'Über Angebot' : 'Über Soll'}</span>`
                : `<span><i class="pp-kpi-dot is-rest"></i>${isEinzelprojekt ? 'Rest Angebot' : 'Rest Soll'}</span>`}
        </div>` : ''}
        <div class="pp-kpi-cells">
            ${cell('is-ist', 'Ist', k.ist, k.ist > 0 ? ppHoursToTs(k.ist) : 0)}
            ${cell('is-planned', 'Geplant', k.planned, k.planned > 0 ? ppHoursToTs(k.planned) : 0)}
            ${noSoll ? '' : cell('is-soll', 'Soll', refSollH, refSollTs)}
        </div>
        ${k.offen > 0.001 ? `
        <div class="pp-kpi-offen"
             title="Plan-Stunden aller noch nicht erledigten Aufgaben. Differenz zu (Geplant − Ist) kann durch Mehr-/Minderaufwand bei erledigten Aufgaben entstehen.">
            <span class="pp-kpi-offen-label">Noch offen</span>
            <span class="pp-kpi-offen-value">${ppFormatNum(k.offen)} h${ppHoursToTs(k.offen) > 0 ? `<span class="pp-kpi-sub">/ ${ppFormatNum(ppHoursToTs(k.offen))} TS</span>` : ''}</span>
        </div>` : ''}
        ${!noSoll && Math.abs(k.carryTs) > 0.001 ? `
        <div class="pp-kpi-carryover"
             title="${k.carryTs > 0 ? 'Wir haben in Vorperioden überliefert — diese Periode darf entsprechend weniger geplant werden (abbummeln)' : 'Kunde hat in Vorperioden Vorschuss — diese Periode muss entsprechend mehr geliefert werden'}">
            <div class="pp-kpi-carryover-head">
                Übertrag: <strong>${(k.carryTs > 0 ? '+' : '') + ppFormatNum(k.carryTs)} TS</strong>
                — ${k.carryTs > 0 ? 'abbummeln' : 'nachliefern'}
            </div>
            <div class="pp-kpi-carryover-sub">
                Standard: ${ppFormatNum(k.budgetTs)} TS / ${ppFormatNum(k.budgetH)} h
            </div>
        </div>` : ''}
        <div class="pp-kpi-puffer ${refSollH > 0 ? (isEinzelprojekt
                ? (k.gap > 0.001 ? 'is-negative' : (k.gap < -0.001 ? 'is-positive' : ''))
                : (k.gap >= 0 ? 'is-positive' : 'is-negative')) : ''}"${noSoll ? ' style="display:none"' : ''}
             title="${isEinzelprojekt
                ? 'Geplant vs. Angebot — positiv = überzogen (rot), negativ = im Rahmen (grün)'
                : 'Geplant vs. ' + (Math.abs(k.carryTs) > 0.001 ? 'effektivem ' : '') + 'Soll (' + ppFormatNum(refSollH) + ' h)'}">
            <span class="pp-kpi-puffer-label">${isEinzelprojekt
                ? (k.gap > 0.001 ? 'Über Angebot' : 'Im Budgetrahmen')
                : 'Spielraum'}</span>
            <span class="pp-kpi-puffer-value">${(k.gap >= 0 ? '+' : '') + ppFormatNum(k.gap)} h</span>
        </div>
    </div>`;
}

function ppRenderStatsBarHtml() {
    const s = ppCalcStats();
    const budgetH = parseFloat(ppState.planBudget.soll_h) || 0;
    const budgetTs = parseFloat(ppState.planBudget.soll_ts) || 0;
    const gap = budgetH > 0 ? (budgetH - s.planned) : (s.planned - s.ist);
    const gapLabel = budgetH > 0 ? 'Noch verplanbar (h)' : 'Gap Ist↔Geplant (h)';

    let budgetClass = '';
    if (budgetH > 0) {
        const ratio = s.planned / budgetH;
        budgetClass = ratio > 1 ? 'is-over' : (ratio > 0.9 ? 'is-warn' : 'is-ok');
    }
    let gapClass = '';
    if (budgetH > 0) gapClass = gap < 0 ? 'is-over' : (gap < budgetH * 0.1 ? 'is-warn' : 'is-ok');

    // Platzhalter
    const placeholders = ppState.activeRows.filter(r => r.row_type === 'item' && !parseInt(r.no_ticket) && parseInt(r.is_placeholder));
    const phSoll = placeholders.reduce((a, r) => a + (parseFloat(r.planned_hours) || 0), 0);
    const phIst = placeholders.reduce((a, r) => a + (parseFloat(r.ist_hours) || 0), 0);

    const progress = s.total > 0 ? Math.round(s.done / s.total * 100) : 0;
    const progressClass = progress >= 75 ? 'is-ok' : (progress >= 40 ? 'is-warn' : 'is-over');

    return `
    <div class="pp-stats-bar">
        ${ppRenderKnowledgePill(ppState.activePlan || {})}
        <span class="pp-stat-pill is-ist" title="Ist-Stunden">
            <span class="pp-stat-pill-label">Ist</span>
            <span class="pp-stat-pill-value">${ppFormatNum(s.ist)} h</span>
            ${s.ist > 0 ? `<span class="pp-stat-pill-sub">${ppFormatNum(ppHoursToTs(s.ist))} TS</span>` : ''}
        </span>
        <span class="pp-stat-pill is-planned" title="Geplante Stunden">
            <span class="pp-stat-pill-label">Geplant</span>
            <span class="pp-stat-pill-value">${ppFormatNum(s.planned)} h</span>
            ${s.planned > 0 ? `<span class="pp-stat-pill-sub">${ppFormatNum(ppHoursToTs(s.planned))} TS${phSoll > 0 ? ' · +' + ppFormatNum(phSoll) + 'h PH' : ''}</span>` : ''}
        </span>
        <span class="pp-stat-pill is-budget ${budgetClass}" title="Budget-Soll (Kunde)">
            <span class="pp-stat-pill-label">Soll</span>
            <span class="pp-stat-pill-value">${budgetH > 0 ? ppFormatNum(budgetH) + ' h' : '—'}</span>
            ${budgetTs > 0 ? `<span class="pp-stat-pill-sub">${ppFormatNum(budgetTs)} TS · ${ppState.planBudget.months} Mon.</span>` : ''}
        </span>
        <span class="pp-stat-pill is-gap ${gapClass}" title="${gapLabel}">
            <span class="pp-stat-pill-label">${budgetH > 0 ? 'Verplanbar' : 'Gap'}</span>
            <span class="pp-stat-pill-value">${(gap >= 0 ? '+' : '') + ppFormatNum(gap)} h</span>
        </span>
        <span class="pp-stat-pill is-done" title="Erledigte Zeilen">
            <span class="pp-stat-pill-label">Erledigt</span>
            <span class="pp-stat-pill-value">${s.done} <span style="color:var(--slate-400);font-weight:400;">/ ${s.total}</span></span>
            <span class="pp-stat-pill-progress"><span class="pp-stat-pill-progress-fill ${progressClass}" style="width:${progress}%"></span></span>
        </span>
    </div>`;
}

function ppRenderFilterBarHtml() {
    const f = ppState.editorFilters;
    const statusChips = [
        { k: 'all', l: 'Alle' },
        { k: 'open', l: 'Offen' },
        { k: 'done', l: 'Erledigt' },
        { k: 'placeholder', l: 'Platzhalter' },
        { k: 'focus', l: 'Fokus' },
        { k: 'no-ticket', l: 'Kein Ticket' },
        { k: 'no-asana', l: 'Ohne Asana' },
        { k: 'decision',  l: 'Entscheidung offen' },
        { k: 'review',    l: '⚠ Prüfen' },
    ];
    const teamOptions = ['<option value="">Alle</option>']
        .concat(ppTeamSorted().map(t => {
            const nick = t.nickname ? ` (${ppEscape(t.nickname)})` : '';
            return `<option value="${ppEscape(t.name)}">${ppEscape(t.abbreviation || '—')} · ${ppEscape(t.name)}${nick}</option>`;
        }))
        .join('');

    // Banner mit Anzahl gefilterter Zeilen + Soll/Ist-Summen
    const filterActive = !f.status.includes('all') || f.lead || f.responsible || f.search;
    let banner = '';
    if (filterActive) {
        const filtered = ppFilteredRows().filter(r => r.row_type === 'item' && !parseInt(r.no_ticket));
        let ist = 0, planned = 0;
        filtered.forEach(r => { ist += parseFloat(r.ist_hours) || 0; planned += parseFloat(r.planned_hours) || 0; });
        banner = `
            <span class="pp-fb-banner">
                <span class="material-symbols-rounded" style="font-size:13px;">filter_alt</span>
                ${filtered.length} Zeilen · Ist ${ppFormatNum(ist)} h · Geplant ${ppFormatNum(planned)} h
                <button onclick="ppResetFilters()" title="Filter zurücksetzen">&times;</button>
            </span>`;
    }

    return `
    <div class="pp-filter-bar">
        <div class="pp-fb-group">
            ${statusChips.map(c => `
                <button class="pp-fb-chip ${f.status.includes(c.k) ? 'is-active' : ''}"
                        onclick="ppToggleStatusFilter('${c.k}')">${c.l}</button>
            `).join('')}
        </div>
        <div class="pp-fb-group">
            <span class="pp-fb-label">Lead</span>
            <select class="pp-fb-select" onchange="ppSetEditorFilter('lead', this.value)">${teamOptions.replace(`value="${ppEscape(f.lead)}"`, `value="${ppEscape(f.lead)}" selected`)}</select>
            <span class="pp-fb-label" style="margin-left:8px;">Umsetzung</span>
            <select class="pp-fb-select" onchange="ppSetEditorFilter('responsible', this.value)">${teamOptions.replace(`value="${ppEscape(f.responsible)}"`, `value="${ppEscape(f.responsible)}" selected`)}</select>
        </div>
        <input type="text" class="pp-fb-search" placeholder="Suchen…" value="${ppEscape(f.search)}"
               oninput="ppSetEditorFilter('search', this.value)">
        ${banner}
    </div>`;
}

function ppRenderTableHtml() {
    const rows = ppFilteredRows();
    const sortActive = !!(ppState.sortBy && ppState.sortBy.field);
    return `
    ${ppRenderBulkBarHtml()}
    ${sortActive ? `<div class="pp-sort-banner">
        <span class="material-symbols-rounded" style="font-size:14px;">sort</span>
        Sortiert nach <strong>${ppEscape(ppState.sortBy.field)}</strong> (${ppState.sortBy.dir === 'asc' ? 'aufsteigend' : 'absteigend'}).
        Sektionen + manuelle Reihenfolge ausgeblendet.
        <button onclick="ppState.sortBy = null; ppRenderEditor();" style="margin-left:auto;background:transparent;border:0;color:var(--thoxan-700);cursor:pointer;font-weight:600;">Reset</button>
    </div>` : ''}
    <div class="pp-table-wrap">
        <table class="pp-table">
            <colgroup>
                <!-- 1 bulk · 2 drag · 3 Aktion (Done + Row-Actions) · 4 Aufgabe (auto)
                     · 5 Zeitraum · 6 Ist · 7 Soll · 8 Lead · 9 Team · 10 Termin
                     · 11 Aufwand · 12 Asana · 13 Bemerkung (auto)
                     Zwei auto-Spalten (Aufgabe + Bemerkung) teilen die Restbreite gleich. -->
                <col style="width:26px;"><col style="width:26px;"><col style="width:140px;"><col><col style="width:78px;">
                <col style="width:46px;"><col style="width:46px;"><col style="width:78px;">
                <col style="width:120px;"><col style="width:84px;"><col style="width:78px;">
                <col style="width:36px;"><col>
            </colgroup>
            <thead>
                <tr>
                    <th class="is-center pp-bulk-col">
                        <input type="checkbox" id="pp-bulk-cb-all" onchange="ppBulkToggleAll(this.checked)" title="Alle sichtbaren Zeilen markieren">
                    </th>
                    <th></th>
                    <th class="is-center" title="Status / Aktionen">Aktion</th>
                    <th class="pp-sort-th" onclick="ppSetSort('description')" title="Klick zum Sortieren">Beschreibung${ppSortArrow('description')} ${ppColFilterIcon('description')}</th>
                    <th class="pp-sort-th" onclick="ppSetSort('timeframe')" title="Klick zum Sortieren">Zeitraum${ppSortArrow('timeframe')} ${ppColFilterIcon('timeframe')}</th>
                    <th class="is-right pp-sort-th" onclick="ppSetSort('ist_hours')" title="Klick zum Sortieren">Ist${ppSortArrow('ist_hours')}</th>
                    <th class="is-right pp-sort-th" onclick="ppSetSort('planned_hours')" title="Klick zum Sortieren">Soll${ppSortArrow('planned_hours')}</th>
                    <th title="Hauptverantwortlich">Lead ${ppColFilterIcon('lead_responsible')}</th>
                    <th title="Umsetzung — Beteiligte">Team ${ppColFilterIcon('responsible')}</th>
                    <th class="pp-sort-th" onclick="ppSetSort('deadline')" title="Umgesetzt bis">Termin${ppSortArrow('deadline')} ${ppColFilterIcon('deadline')}</th>
                    <th class="is-right">Aufwand</th>
                    <th class="is-center" title="Asana-Verknüpfung">Asana</th>
                    <th>Bemerkung</th>
                </tr>
            </thead>
            <tbody id="pp-tbody">
                ${(() => {
                    const f = ppState.editorFilters;
                    const focusMode = !!(f.lead || f.responsible);
                    return rows.map(r => ppRenderRow(r) + (focusMode ? '' : ppRenderInserter(r.id))).join('');
                })()}
                ${rows.length === 0 ? `<tr><td colspan="13" style="text-align:center;padding:40px;color:var(--slate-400);">${ppState.activeRows.length === 0 ? 'Noch keine Zeilen. Unten „+ Zeile" klicken.' : 'Keine Zeilen entsprechen den Filtern.'}</td></tr>` : ''}
            </tbody>
        </table>
    </div>`;
}

function ppRenderBulkBarHtml() {
    const n = ppState.bulkSelection.size;
    // Bei multi-plan haben Zeilen evtl. unterschiedliche plan_ids — Bulk-Aktionen wirken trotzdem (jede PUT/DELETE geht an die richtige plan_id pro Zeile)
    const teamOpts = '<option value="">— Person wählen —</option>' +
        ppTeamSorted(true).map(t => {
            const nick = t.nickname ? ` (${ppEscape(t.nickname)})` : '';
            return `<option value="${ppEscape(t.name)}">${ppEscape(t.abbreviation || '—')} · ${ppEscape(t.name)}${nick}</option>`;
        }).join('');
    return `
    <div class="pp-bulk-bar" id="pp-bulk-bar" style="display:${n > 0 ? 'flex' : 'none'};">
        <span class="pp-bulk-count"><strong id="pp-bulk-count">${n}</strong> Zeilen</span>
        <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="ppBulkSetDone(true)">
            <span class="material-symbols-rounded" style="font-size:14px;">check</span> Erledigt
        </button>
        <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="ppBulkSetDone(false)">
            <span class="material-symbols-rounded" style="font-size:14px;">close</span> Wieder offen
        </button>
        <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="ppBulkSetFocus(true)" title="Fokus-Flag setzen">
            <span class="material-symbols-rounded" style="font-size:14px;">flag</span> Fokus
        </button>
        <span class="pp-bulk-sep"></span>
        <span class="pp-bulk-label">Lead:</span>
        <select class="pp-bulk-select" onchange="if(this.value){ppBulkSetLead(this.value);this.value='';}">${teamOpts}</select>
        <span class="pp-bulk-label">Umsetzung:</span>
        <select class="pp-bulk-select" onchange="if(this.value){ppBulkSetResponsible(this.value);this.value='';}">${teamOpts}</select>
        <span class="pp-bulk-sep"></span>
        <button class="thx-btn thx-btn-small thx-btn-danger" onclick="ppBulkDelete()" data-perm-write="1">
            <span class="material-symbols-rounded" style="font-size:14px;">delete</span> Löschen
        </button>
        <button class="thx-bulk-close" onclick="ppBulkClear()" title="Auswahl aufheben">×</button>
    </div>`;
}

function ppColFilterIcon(field) {
    const active = ppState.editorFilters.col && ppState.editorFilters.col[field];
    return `<span style="cursor:pointer;color:${active ? 'var(--thoxan-600)' : 'var(--slate-300)'};margin-left:4px;font-size:11px;" onclick="event.stopPropagation();ppOpenColFilter(event,'${field}')" title="Spalten-Filter">▼</span>`;
}

function ppOpenColFilter(e, field) {
    const all = ppState.activeRows.filter(r => r.row_type === 'item');
    const valueSet = new Map();
    all.forEach(r => {
        const v = (r[field] || '').trim();
        if (field === 'responsible') {
            v.split(',').map(s => s.trim()).filter(Boolean).forEach(p => valueSet.set(p, (valueSet.get(p) || 0) + 1));
        } else if (v) {
            valueSet.set(v, (valueSet.get(v) || 0) + 1);
        }
    });
    const values = [...valueSet.entries()].sort((a, b) => b[1] - a[1]).slice(0, 20);
    const menu = document.getElementById('pp-context-menu');
    const current = ppState.editorFilters.col[field] || '';
    menu.innerHTML = `
        <div style="font-size:10px;color:var(--slate-400);padding:4px 10px;text-transform:uppercase;letter-spacing:0.04em;">Filter: ${field}</div>
        <button class="thx-contextmenu-item" onclick="ppSetColFilter('${field}','')">
            <span class="material-symbols-rounded" style="font-size:14px;">${!current ? 'check' : 'radio_button_unchecked'}</span> Alle (${all.length})
        </button>
        ${values.length === 0 ? '<div style="padding:6px 10px;color:var(--slate-400);font-size:11px;">Keine Werte</div>' :
            values.map(([v, c]) => `<button class="thx-contextmenu-item" onclick="ppSetColFilter('${field}','${ppEscape(v).replace(/'/g, "\\'")}')">
                <span class="material-symbols-rounded" style="font-size:14px;">${current === v ? 'check' : 'radio_button_unchecked'}</span>
                <span style="flex:1;">${ppEscape(v)}</span>
                <span style="color:var(--slate-400);font-size:10px;">${c}</span>
            </button>`).join('')
        }`;
    menu.style.display = 'block';
    ppPositionContextMenu(menu, e);
    setTimeout(() => document.addEventListener('click', ppHideContextMenu, { once: true }), 0);
}

function ppSetColFilter(field, value) {
    ppHideContextMenu();
    if (value) ppState.editorFilters.col[field] = value;
    else delete ppState.editorFilters.col[field];
    ppSaveFilters();
    ppRenderEditor();
}

function ppRenderFooterHtml() {
    const kbPill = ppRenderKnowledgePill(ppState.activePlan || {});
    return `
    <div class="pp-table-foot">
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppAddRow('item')">
            <span class="material-symbols-rounded" style="font-size:14px;">add</span> Zeile
        </button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppAddRow('section')">
            <span class="material-symbols-rounded" style="font-size:14px;">subject</span> Sektion
        </button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppAddRow('note')">
            <span class="material-symbols-rounded" style="font-size:14px;">sticky_note_2</span> Notiz
        </button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppAddRow('spacer')">
            <span class="material-symbols-rounded" style="font-size:14px;">space_bar</span> Spacer
        </button>
        ${kbPill ? `<span class="pp-table-foot-spacer"></span>${kbPill}` : ''}
    </div>`;
}

/* ===== Row-Renderer ===== */
function ppRenderRow(r) {
    const rowClass = [
        'pp-row',
        'pp-row-' + r.row_type,
        parseInt(r.is_done) ? 'pp-row-done' : '',
        parseInt(r.is_placeholder) ? 'pp-row-placeholder' : '',
        parseInt(r.is_focus) ? 'pp-row-focus' : '',
        parseInt(r.review_flag) ? 'pp-row-review' : '',
    ].filter(Boolean).join(' ');

    if (r.row_type === 'section') {
        const sub = ppSectionSubtotal(r.id);
        const collapsed = ppState.collapsedSections && ppState.collapsedSections.has(r.id);
        // Color-Zyklus 6 Farben — Index = Position der Sektion im Plan
        return `
        <tr class="${rowClass} ${collapsed ? 'is-collapsed' : ''}" data-id="${r.id}" draggable="true">
            <td></td>
            <td class="pp-drag-handle">⋮⋮</td>
            <td colspan="10">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="pp-edit ${!r.description ? 'is-empty' : ''}" contenteditable="true"
                         data-field="description" data-id="${r.id}" data-placeholder="Sektion-Überschrift…"
                         style="flex:1;"
                         onblur="ppSaveRowField(this)">${ppEscape(r.description || '')}</div>
                    <!-- Sektion-Summen als kompakte Chip-Darstellung (gleiche Optik
                         wie die Subline in der Mittel-Spalte). Immer rendern, der
                         In-Place-Updater toggelt nur display. -->
                    <span class="pp-sec-totals" style="margin-left:auto;${(sub.ist > 0 || sub.soll > 0) ? '' : 'display:none;'}">
                        <span class="pp-sb-sub-chip is-ist" title="Ist-Stunden dieser Sektion">
                            <span class="material-symbols-rounded">history</span>
                            ${ppFormatNum(sub.ist)} h <span class="pp-sb-sub-ts">${ppFormatNum(ppHoursToTs(sub.ist))} TS</span>
                        </span>
                        <span class="pp-sb-sub-chip is-planned" title="Geplante Stunden dieser Sektion">
                            <span class="material-symbols-rounded">event</span>
                            ${ppFormatNum(sub.soll)} h <span class="pp-sb-sub-ts">${ppFormatNum(ppHoursToTs(sub.soll))} TS</span>
                        </span>
                    </span>
                    <span class="pp-sec-buttons">
                        <button onclick="ppSectionMove(${r.id}, -1)" title="Sektion nach oben">
                            <span class="material-symbols-rounded" style="font-size:14px;">arrow_upward</span>
                        </button>
                        <button onclick="ppSectionMove(${r.id}, 1)" title="Sektion nach unten">
                            <span class="material-symbols-rounded" style="font-size:14px;">arrow_downward</span>
                        </button>
                        <button onclick="ppSectionTogglePlaceholder(${r.id})" title="Alle Items als Platzhalter togglen">
                            <span class="material-symbols-rounded" style="font-size:14px;">hourglass_empty</span>
                        </button>
                        <button onclick="ppSectionToggleCollapse(${r.id})" title="${collapsed ? 'Ausklappen' : 'Einklappen'}">
                            <span class="material-symbols-rounded" style="font-size:14px;">${collapsed ? 'unfold_more' : 'unfold_less'}</span>
                        </button>
                    </span>
                </div>
            </td>
            <td>${ppRowActionsHtml(r, true)}</td>
        </tr>`;
    }
    if (r.row_type === 'note') {
        const noteText = r.notes || '';
        const isUrl = /^https?:\/\//.test(noteText.trim());
        return `
        <tr class="${rowClass}" data-id="${r.id}" draggable="true">
            <td></td>
            <td class="pp-drag-handle">⋮⋮</td>
            <td class="pp-col-actions">${ppRowActionsHtml(r, true)}</td>
            <!-- Notiz darf ueber alle restlichen Spalten laufen, damit lange URLs
                 nicht umbrechen. -->
            <td class="pp-col-desc" colspan="10">
                <div class="pp-edit" contenteditable="true"
                     data-field="notes" data-id="${r.id}" data-placeholder="Notiz oder URL…"
                     onblur="ppSaveRowField(this)" style="display:inline-block;max-width:100%;">${ppEscape(noteText)}</div>
                ${isUrl ? `<a href="${ppEscape(noteText.trim())}" target="_blank" rel="noopener" style="font-size:11px;color:var(--thoxan-600);text-decoration:none;margin-left:4px;" onclick="event.stopPropagation();" title="Link öffnen">↗</a>` : ''}
            </td>
        </tr>`;
    }
    if (r.row_type === 'plan_header') {
        // Aggregat pro Plan berechnen
        const planRows = ppState.activeRows.filter(x => x.row_type === 'item' && x._planId === r._planId && !parseInt(x.no_ticket));
        let ist = 0, soll = 0, done = 0;
        planRows.forEach(x => {
            ist += parseFloat(x.ist_hours) || 0;
            soll += parseFloat(x.planned_hours) || 0;
            if (parseInt(x.is_done)) done++;
        });
        return `<tr class="pp-row-plan-header" data-plan-header="${r._planId}">
            <td colspan="13" style="background:${r._planColor}22;border-top:3px solid ${r._planColor};border-bottom:1px solid ${r._planColor}55;padding:8px 14px;font-weight:700;color:var(--slate-800);">
                <span class="material-symbols-rounded" style="vertical-align:middle;font-size:14px;color:${r._planColor};">view_kanban</span>
                ${ppEscape(r.description)}
                <span style="font-weight:500;color:var(--slate-600);margin-left:10px;font-family:ui-monospace,monospace;font-size:var(--d-fs-xs);">
                    ${planRows.length} Aufgaben · Ist ${ppFormatNum(ist)} h · Soll ${ppFormatNum(soll)} h · ${done}/${planRows.length} erledigt
                </span>
                <a href="javascript:void(0)" onclick="ppExitMultiPlan(${r._planId})" style="float:right;font-size:11px;color:var(--slate-500);text-decoration:none;font-weight:500;">
                    nur diesen Plan zeigen →
                </a>
            </td>
        </tr>`;
    }
    if (r.row_type === 'spacer') {
        return `
        <tr class="${rowClass}" data-id="${r.id}" draggable="true">
            <td></td>
            <td class="pp-drag-handle">⋮⋮</td>
            <td class="pp-col-actions">${ppRowActionsHtml(r, true)}</td>
            <td colspan="10" style="height:8px;"></td>
        </tr>`;
    }

    // type === 'item'
    const istVal = parseFloat(r.ist_hours);
    const sollVal = parseFloat(r.planned_hours);
    // Feedback-Indicator
    const fbs = (ppState.activePlan && ppState.activePlan.feedback || []).filter(f => f.row_id === r.id);
    const fbUnread = fbs.filter(f => !f.read_at).length;
    const fbIndicator = fbs.length === 0 ? '' :
        `<span class="pp-row-fb-indicator ${fbUnread === 0 ? 'is-read' : ''}" onclick="ppOpenFeedbackModal()" title="${fbs.length} Feedback (${fbUnread} ungelesen)">💬${fbs.length}</span>`;
    const bulkSel = ppState.bulkSelection.has(r.id);
    return `
    <tr class="${rowClass}${bulkSel ? ' is-bulk-selected' : ''}" data-id="${r.id}" data-row-id="${r.id}" draggable="true" oncontextmenu="ppShowContextMenu(event, ${r.id})">
        <td class="is-center pp-bulk-col" onclick="event.stopPropagation();">
            <input type="checkbox" class="pp-bulk-cb" ${bulkSel ? 'checked' : ''} onchange="ppBulkToggle(${r.id})" title="Für Bulk-Aktion markieren">
        </td>
        <td class="pp-drag-handle">⋮⋮</td>
        <td class="pp-col-actions">
            <div class="pp-row-actions-cell">
                <button class="pp-done-btn ${parseInt(r.is_done) ? 'is-done' : ''}"
                        onclick="ppToggleDone(${r.id})" title="Als erledigt markieren">
                    <span class="material-symbols-rounded">check</span>
                </button>
                ${ppRowActionsHtml(r, true)}
            </div>
        </td>
        <td class="pp-col-desc">
            <div class="pp-edit ${!r.description ? 'is-empty' : ''}" contenteditable="true"
                 data-field="description" data-id="${r.id}" data-placeholder="Aufgabe…"
                 onblur="ppSaveRowField(this)">${ppEscape(r.description || '')}</div>
            ${fbIndicator}
            ${r.review_note ? `<span class="pp-ai-clarify-pill" title="Von der KI markiert — bitte prüfen und ggf. löschen">${ppEscape(r.review_note)}</span>` : ''}
            ${parseInt(r.review_flag) ? `<button class="pp-review-btn" onclick="ppMarkReviewed(${r.id})" title="Von der KI angepasst bzw. zu prüfen. Klick = passt, Markierung entfernen.">
                <span class="material-symbols-rounded">verified</span> passt
            </button><button class="pp-reject-btn" onclick="ppRejectAiRow(${r.id})" title="Passt nicht — als Regel für künftige Pläne dieses Kunden festhalten (Lernschleife).">
                <span class="material-symbols-rounded">school</span> passt nicht
            </button>` : ''}
        </td>
        <td>
            <div class="pp-edit" contenteditable="true"
                 data-field="timeframe" data-id="${r.id}"
                 onblur="ppSaveRowField(this)">${ppEscape(r.timeframe || '')}</div>
        </td>
        <td class="is-right">
            <div class="pp-edit is-num" contenteditable="true"
                 data-field="ist_hours" data-id="${r.id}" data-numeric="1"
                 onblur="ppSaveRowField(this)">${istVal > 0 ? ppFormatNum(istVal) : ''}</div>
        </td>
        <td class="is-right">
            <div class="pp-edit is-num" contenteditable="true"
                 data-field="planned_hours" data-id="${r.id}" data-numeric="1"
                 onblur="ppSaveRowField(this)">${sollVal > 0 ? ppFormatNum(sollVal) : ''}</div>
        </td>
        <td>${ppRenderLeadCell(r)}</td>
        <td>${ppRenderRespCell(r)}</td>
        <td>
            <div class="pp-edit ${!r.deadline ? 'is-empty' : ''}" contenteditable="true"
                 data-field="deadline" data-id="${r.id}" data-placeholder="—"
                 onblur="ppSaveRowField(this)">${ppEscape(r.deadline || '')}</div>
        </td>
        <td class="is-right">
            <!-- Aufwand = Freitext: kann z.B. „3,00 Michi 15.05." enthalten,
                 deshalb KEIN data-numeric und KEIN .is-num-Styling. -->
            <div class="pp-edit" contenteditable="true"
                 data-field="actual_hours" data-id="${r.id}" data-placeholder="—"
                 onblur="ppSaveRowField(this)">${ppEscape(r.actual_hours || '')}</div>
        </td>
        <td class="is-center pp-col-asana">${ppRenderAsanaCell(r)}</td>
        <td>
            <div class="pp-edit ${!r.notes ? 'is-empty' : ''}" contenteditable="true"
                 data-field="notes" data-id="${r.id}" data-placeholder="Notiz…"
                 onblur="ppSaveRowField(this)">${ppEscape(r.notes || '')}</div>
        </td>
    </tr>`;
}

/** Asana-Status-Zelle: Icon + Tooltip pro Zeile. */
function ppRenderAsanaCell(r) {
    // Status 1: Zeile als "Kein Ticket nötig" markiert
    if (parseInt(r.no_ticket)) {
        return `<div class="pp-asana-cell">
            <button class="pp-asana-cell-btn is-noticket"
                    onclick="ppToggleRowFlag(${r.id}, 'no_ticket')"
                    title="Kein Asana-Ticket nötig — Klick zum Reaktivieren">
                <span class="material-symbols-rounded">block</span>
            </button>
        </div>`;
    }
    // Status 2: Bereits mit Asana-Task verknüpft
    if (r.asana_gid) {
        const url = r.asana_url || ('https://app.asana.com/0/0/' + r.asana_gid);
        return `<div class="pp-asana-cell">
            <a class="pp-asana-cell-btn is-linked" href="${ppEscape(url)}" target="_blank" rel="noopener"
               onclick="event.stopPropagation();"
               title="Direkt in Asana öffnen: ${ppEscape(r.asana_task_name || '')}">
                <span class="material-symbols-rounded">open_in_new</span>
            </a>
            <button class="pp-asana-cell-btn"
                    onclick="ppOpenAsanaCellMenu(event, ${r.id})"
                    title="Aktionen für Asana-Verknüpfung">
                <span class="material-symbols-rounded">more_vert</span>
            </button>
        </div>`;
    }
    // Status 3: Plan hat Asana-Projekt, Zeile aber noch ungenutzt
    if (ppState.activePlan && ppState.activePlan.asana_project_gid) {
        return `<div class="pp-asana-cell">
            <button class="pp-asana-cell-btn"
                    onclick="ppOpenAsanaCellMenu(event, ${r.id})"
                    title="Task erstellen oder verknüpfen">
                <span class="material-symbols-rounded">add_link</span>
            </button>
            <button class="pp-asana-cell-btn"
                    onclick="ppToggleRowFlag(${r.id}, 'no_ticket')"
                    title="Kein Asana-Ticket nötig">
                <span class="material-symbols-rounded">block</span>
            </button>
        </div>`;
    }
    // Status 4: Plan ohne Asana — nur "Kein Ticket nötig" anbieten
    return `<div class="pp-asana-cell">
        <button class="pp-asana-cell-btn"
                onclick="ppToggleRowFlag(${r.id}, 'no_ticket')"
                title="Diese Zeile braucht kein Asana-Ticket">
            <span class="material-symbols-rounded" style="color:var(--slate-300);">block</span>
        </button>
    </div>`;
}

/** Popover-Menü an der Asana-Zelle mit kontextabhängigen Aktionen. */
function ppOpenAsanaCellMenu(ev, rowId) {
    ev.stopPropagation();
    ppCloseAsanaCellMenu();
    const row = ppState.activeRows.find(x => x.id === rowId);
    if (!row) return;
    const linked = !!row.asana_gid;
    const planHasAsana = !!(ppState.activePlan && ppState.activePlan.asana_project_gid);

    const items = [];
    if (linked) {
        const url = row.asana_url || ('https://app.asana.com/0/0/' + row.asana_gid);
        items.push(`<button onclick="ppCloseAsanaCellMenu();ppShowAsanaTaskDetail('${ppEscape(row.asana_gid)}')">
            <span class="material-symbols-rounded">info</span>Details anzeigen</button>`);
        items.push(`<a href="${ppEscape(url)}" target="_blank" rel="noopener" onclick="ppCloseAsanaCellMenu()">
            <span class="material-symbols-rounded">open_in_new</span>Direkt in Asana öffnen</a>`);
        items.push(`<button onclick="ppCloseAsanaCellMenu();ppOpenAsanaTaskModal(${rowId})">
            <span class="material-symbols-rounded">edit</span>Verknüpfung ändern</button>`);
        items.push(`<button onclick="ppCloseAsanaCellMenu();ppOpenSubtaskImport(${rowId})">
            <span class="material-symbols-rounded">account_tree</span>Unteraufgaben importieren</button>`);
        items.push(`<div class="pp-asana-pop-sep"></div>`);
        items.push(`<button class="pp-asana-pop-danger" onclick="ppCloseAsanaCellMenu();ppUnlinkAsanaTask(${rowId})">
            <span class="material-symbols-rounded">link_off</span>Verknüpfung lösen</button>`);
    } else if (planHasAsana) {
        // Verknuepfen kommt zuerst — der haeufigere Anwendungsfall im Alltag.
        items.push(`<button onclick="ppCloseAsanaCellMenu();ppOpenAsanaTaskModal(${rowId},'link')">
            <span class="material-symbols-rounded">link</span>Bestehenden Task verknüpfen</button>`);
        items.push(`<button onclick="ppCloseAsanaCellMenu();ppOpenAsanaTaskModal(${rowId},'create')">
            <span class="material-symbols-rounded">add</span>Neuen Task erstellen</button>`);
        items.push(`<div class="pp-asana-pop-sep"></div>`);
    }
    items.push(`<button onclick="ppCloseAsanaCellMenu();ppToggleRowFlag(${rowId},'no_ticket')">
        <span class="material-symbols-rounded">block</span>Kein Ticket nötig</button>`);

    const pop = document.createElement('div');
    pop.className = 'pp-asana-pop';
    pop.id = 'pp-asana-pop';
    pop.innerHTML = items.join('');
    document.body.appendChild(pop);

    // Positionierung: rechts unter dem auslösenden Button, mit Viewport-Flip
    // wenn unten/rechts kein Platz mehr ist.
    const btn = ev.currentTarget;
    const rect = btn.getBoundingClientRect();
    pop.style.left = '0px'; pop.style.top = '0px';
    const popRect = pop.getBoundingClientRect();
    const popW = popRect.width || 220;
    const popH = popRect.height || 200;
    const vw = window.innerWidth, vh = window.innerHeight;
    const margin = 8;
    let left = rect.right - popW;
    if (left + popW > vw - margin) left = vw - margin - popW;
    if (left < margin) left = margin;
    let top = rect.bottom + 4;
    if (top + popH > vh - margin) top = Math.max(margin, rect.top - 4 - popH);
    pop.style.left = (left + window.scrollX) + 'px';
    pop.style.top  = (top  + window.scrollY) + 'px';

    setTimeout(() => {
        const close = (e) => {
            if (!pop.contains(e.target)) ppCloseAsanaCellMenu();
        };
        document.addEventListener('click', close, { once: false });
        pop._close = close;
    }, 0);
}

function ppCloseAsanaCellMenu() {
    const pop = document.getElementById('pp-asana-pop');
    if (!pop) return;
    if (pop._close) document.removeEventListener('click', pop._close);
    pop.remove();
}

/** Verknüpfung einer Plan-Zeile zu Asana lösen (Asana-Task bleibt unberührt). */
async function ppUnlinkAsanaTask(rowId) {
    if (!confirm('Verknüpfung dieser Zeile zum Asana-Task wirklich lösen?\n\nDer Task in Asana bleibt erhalten.')) return;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/unlink', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_row_id: rowId }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Verknüpfung gelöst', 'success');
        const row = ppState.activeRows.find(x => x.id === rowId);
        if (row) { row.asana_gid = null; row.asana_url = null; row.asana_task_name = null; }
        ppRenderEditor();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppRowActionsHtml(r, minimal) {
    const isItem = r.row_type === 'item';
    return `
    <div class="pp-row-actions">
        ${isItem ? `
            <button class="pp-action-focus ${parseInt(r.is_focus) ? 'is-active' : ''}"
                    onclick="ppToggleRowFlag(${r.id}, 'is_focus')" title="Fokus">
                <span class="material-symbols-rounded" style="font-size:14px;">flag</span>
            </button>
            <button class="pp-action-placeholder ${parseInt(r.is_placeholder) ? 'is-active' : ''}"
                    onclick="ppToggleRowFlag(${r.id}, 'is_placeholder')" title="Platzhalter">
                <span class="material-symbols-rounded" style="font-size:14px;">help_outline</span>
            </button>
        ` : ''}
        <button class="pp-action-delete" onclick="ppDeleteRow(${r.id})" title="Löschen">
            <span class="material-symbols-rounded" style="font-size:14px;">delete</span>
        </button>
    </div>`;
}

function ppRenderInserter(afterRowId) {
    return `<tr class="pp-row-inserter"><td colspan="13">
        <div class="pp-inserter-bar">
            <span class="pp-inserter-line"></span>
            <span class="pp-inserter-buttons">
                <button onclick="ppInsertAfter(${afterRowId}, 'item')" title="Zeile einfügen">+ Zeile</button>
                <button onclick="ppInsertAfter(${afterRowId}, 'section')" title="Sektion einfügen">+ Sektion</button>
                <button onclick="ppInsertAfter(${afterRowId}, 'note')" title="Notiz einfügen">+ Notiz</button>
                <button onclick="ppInsertAfter(${afterRowId}, 'spacer')" title="Spacer einfügen">+ Spacer</button>
            </span>
        </div>
    </td></tr>`;
}

async function ppInsertAfter(afterRowId, type) {
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ row_type: type, description: '' }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const newId = j.data.id;
        // Hole die frischen Row-Daten und sortiere lokal direkt nach afterRowId
        const planRes = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId).then(x => x.json());
        if (!planRes.success) throw new Error(planRes.message);
        const allRows = planRes.data.rows || [];
        const newRow = allRows.find(x => x.id === newId);
        // Rows ohne newRow + an richtige Position einfügen
        const otherRows = allRows.filter(x => x.id !== newId);
        const idx = otherRows.findIndex(x => x.id === afterRowId);
        if (idx >= 0) otherRows.splice(idx + 1, 0, newRow);
        else otherRows.push(newRow);
        ppState.activeRows = otherRows;
        ppRenderEditor();
        ppPersistReorder();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Personen-Chips ===== */
function ppRenderLeadCell(r) {
    const lead = (r.lead_responsible || '').trim();
    if (!lead) {
        return `<div class="pp-chips-cell">
            <button class="pp-chip-add" onclick="ppOpenAutocomplete(event, ${r.id}, 'lead_responsible', false)" title="Lead setzen">+</button>
        </div>`;
    }
    const color = ppChipColor(lead);
    return `<div class="pp-chips-cell">
        <span class="pp-chip pp-chip-lead" onclick="ppOpenAutocomplete(event, ${r.id}, 'lead_responsible', false)"
              ${color ? `style="background:${color}22;color:${color};"` : ''}
              title="${ppEscape(lead)} (Lead)">
            <span class="pp-chip-name">${ppEscape(ppChipLabel(lead))}</span>
            <span class="pp-chip-remove" onclick="event.stopPropagation();ppSetField(${r.id}, 'lead_responsible', '')">&times;</span>
        </span>
    </div>`;
}

function ppRenderRespCell(r) {
    const names = ppParseNames(r.responsible);
    const chips = names.map(n => {
        const color = ppChipColor(n);
        const escaped = ppEscape(n).replace(/'/g, "\\'");
        return `<span class="pp-chip" ${color ? `style="background:${color}22;color:${color};"` : ''} title="${ppEscape(n)} — Rechtsklick für Optionen"
                      oncontextmenu="event.preventDefault();ppShowRespChipMenu(event, ${r.id}, '${escaped}')">
            <span class="pp-chip-name">${ppEscape(ppChipLabel(n))}</span>
            <span class="pp-chip-remove" onclick="ppRemoveResp(${r.id}, '${escaped}')">&times;</span>
        </span>`;
    }).join('');
    return `<div class="pp-chips-cell">
        ${chips}
        <button class="pp-chip-add" onclick="ppOpenAutocomplete(event, ${r.id}, 'responsible', true)" title="Person hinzufügen">+</button>
    </div>`;
}

function ppShowPlanCardMenu(e, planId) {
    e.preventDefault(); e.stopPropagation();
    const plan = ppState.plans.find(p => p.id === planId);
    if (!plan) return;
    const inTrash = parseInt(plan.state) === 2;
    const menu = document.getElementById('pp-context-menu');

    if (inTrash) {
        // Papierkorb-spezifisches Menue: Wiederherstellen oder endgueltig loeschen
        menu.innerHTML = `
            <div style="font-size:10px;color:var(--slate-400);padding:4px 10px;text-transform:uppercase;letter-spacing:0.04em;">Papierkorb</div>
            <button class="thx-contextmenu-item" onclick="ppRestorePlanFromMenu(${planId})">
                <span class="material-symbols-rounded" style="font-size:14px;color:var(--emerald-600);">restore_from_trash</span> Wiederherstellen
            </button>
            <div style="height:1px;background:var(--slate-200);margin:4px 0;"></div>
            <button class="thx-contextmenu-item" style="color:var(--rose-700);" onclick="ppHardDeletePlan(${planId})">
                <span class="material-symbols-rounded" style="font-size:14px;">delete_forever</span> Endgültig löschen
            </button>`;
    } else {
        const statuses = ['entwurf', 'aktiv', 'einzelprojekt', 'reporting', 'abgeschlossen', 'archiviert'];
        const labels = {entwurf:'Entwurf', aktiv:'Aktiv', einzelprojekt:'Einzelprojekt', reporting:'Reporting', abgeschlossen:'Abgeschlossen', archiviert:'Archiviert'};
        menu.innerHTML = `
            <div style="font-size:10px;color:var(--slate-400);padding:4px 10px;text-transform:uppercase;letter-spacing:0.04em;">Status setzen</div>
            ${statuses.map(s => `<button class="thx-contextmenu-item" onclick="ppPlanSetStatus(${planId},'${s}')">
                <span class="material-symbols-rounded" style="font-size:14px;">${plan.plan_status === s ? 'check' : 'radio_button_unchecked'}</span> ${labels[s]}
            </button>`).join('')}
            <div style="height:1px;background:var(--slate-200);margin:4px 0;"></div>
            <button class="thx-contextmenu-item" onclick="ppPlanItemClick({}, ${planId});ppHideContextMenu();">
                <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span> Öffnen
            </button>
            <button class="thx-contextmenu-item" onclick="ppHideContextMenu();ppDuplicatePlan(${planId})">
                <span class="material-symbols-rounded" style="font-size:14px;">content_copy</span> Duplizieren
            </button>
            <button class="thx-contextmenu-item" style="color:var(--rose-600);" onclick="ppDeletePlanFromMenu(${planId})">
                <span class="material-symbols-rounded" style="font-size:14px;">delete</span> In Papierkorb verschieben
            </button>`;
    }
    menu.style.display = 'block';
    ppPositionContextMenu(menu, e);
    setTimeout(() => document.addEventListener('click', ppHideContextMenu, { once: true }), 0);
}

/** Plan im Papierkorb wiederherstellen (state=2 -> state=1). */
async function ppRestorePlanFromMenu(planId) {
    ppHideContextMenu();
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId + '/restore', {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Plan wiederhergestellt', 'success');
        ppState.plans = ppState.plans.filter(p => p.id !== planId);
        ppRenderPlanList();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/** Plan endgueltig loeschen (Hard-Delete, inkl. Knowledge-Doc + Chunks). */
async function ppHardDeletePlan(planId) {
    ppHideContextMenu();
    const plan = ppState.plans.find(p => p.id === planId);
    const title = plan ? (plan.title || ('Plan ' + planId)) : ('Plan ' + planId);
    if (!confirm('„' + title + "\" wirklich ENDGÜLTIG löschen?\n\n"
                 + 'Das entfernt den Plan, alle Zeilen, alle Sharelinks, alle Snapshots\n'
                 + 'und das zugehörige Wissensdatenbank-Dokument unwiderruflich.')) return;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId + '/hard', {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Plan endgültig gelöscht', 'success');
        ppState.plans = ppState.plans.filter(p => p.id !== planId);
        if (ppState.activePlanId === planId) { ppClearSelection(); }
        ppRenderPlanList();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppPlanSetStatus(planId, status) {
    ppHideContextMenu();
    try {
        await fetch('/api/v1/admin/projektplanner/plans/' + planId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_status: status }),
        });
        const p = ppState.plans.find(x => x.id === planId);
        if (p) p.plan_status = status;
        if (ppState.activePlan && ppState.activePlan.id === planId) {
            ppState.activePlan.plan_status = status;
            ppRenderEditor();
        }
        ppRenderPlanList();
        App.showNotification('Status: ' + status, 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppDeletePlanFromMenu(planId) {
    ppHideContextMenu();
    if (!confirm('Plan wirklich löschen? Kann später aus dem Archiv wiederhergestellt werden.')) return;
    try {
        await fetch('/api/v1/admin/projektplanner/plans/' + planId, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        ppState.plans = ppState.plans.filter(x => x.id !== planId);
        if (ppState.activePlanId === planId) {
            ppState.activePlanId = null; ppState.activePlan = null;
            document.getElementById('pp-empty').style.display = 'flex';
            document.getElementById('pp-editor').style.display = 'none';
        }
        ppRenderPlanList();
        App.showNotification('Plan gelöscht', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppShowRespChipMenu(e, rowId, personName) {
    const menu = document.getElementById('pp-context-menu');
    menu.innerHTML = `
        <button class="thx-contextmenu-item" onclick="ppSetAsLead(${rowId}, '${ppEscape(personName).replace(/'/g, "\\'")}')">
            <span class="material-symbols-rounded" style="font-size:14px;">flag</span> Als Hauptverantw. setzen
        </button>
        <button class="thx-contextmenu-item" onclick="ppRemoveResp(${rowId}, '${ppEscape(personName).replace(/'/g, "\\'")}');ppHideContextMenu();">
            <span class="material-symbols-rounded" style="font-size:14px;">close</span> Entfernen
        </button>`;
    menu.style.display = 'block';
    ppPositionContextMenu(menu, e);
    setTimeout(() => document.addEventListener('click', ppHideContextMenu, { once: true }), 0);
}

function ppSetAsLead(rowId, personName) {
    ppHideContextMenu();
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    // Aus responsible entfernen
    const names = ppParseNames(row.responsible).filter(n => n !== personName);
    row.responsible = ppJoinNames(names);
    ppDoSaveRow(rowId, 'responsible', row.responsible);
    // Als Lead setzen
    ppSetField(rowId, 'lead_responsible', personName);
}

function ppRemoveResp(rowId, name) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const names = ppParseNames(row.responsible).filter(n => n !== name);
    ppSetField(rowId, 'responsible', ppJoinNames(names));
}

/* ===== Autocomplete für Personen ===== */
const ppAc = { rowId: null, field: null, multi: false, highlight: 0, items: [] };

function ppOpenAutocomplete(evt, rowId, field, multi) {
    evt.stopPropagation();
    ppAc.rowId = rowId; ppAc.field = field; ppAc.multi = multi; ppAc.highlight = 0;
    const row = ppState.activeRows.find(r => r.id === rowId);
    const existing = field === 'responsible' ? ppParseNames(row?.responsible).map(s => s.toLowerCase()) : [];
    ppAc.items = ppState.team
        .filter(t => !existing.includes((t.name || '').toLowerCase()))
        .map(t => ({ name: t.name, abbr: t.abbreviation, color: t.hex_color }));

    const pop = document.getElementById('pp-autocomplete');
    pop.innerHTML = `
        <input type="text" id="pp-ac-input" placeholder="Suchen…" autofocus
               style="width:100%;border:none;border-bottom:1px solid var(--slate-100);padding:6px 8px;outline:none;font-family:inherit;font-size:var(--d-fs-sm);">
        <div id="pp-ac-list"></div>`;
    const rect = evt.target.getBoundingClientRect();
    pop.style.left = (rect.left + window.scrollX) + 'px';
    pop.style.top = (rect.bottom + window.scrollY + 4) + 'px';
    pop.style.display = 'block';
    ppAcRenderList('');
    // Viewport-Flip nach dem Render
    setTimeout(() => ppFlipFixedPopover(pop, rect), 50);
    setTimeout(() => {
        const inp = document.getElementById('pp-ac-input');
        inp.focus();
        inp.addEventListener('input', () => ppAcRenderList(inp.value));
        inp.addEventListener('keydown', ppAcKey);
    }, 30);
    document.addEventListener('click', ppAcCloseHandler, { capture: true });
}

function ppAcRenderList(query) {
    const q = (query || '').trim().toLowerCase();
    const filtered = ppAc.items.filter(it =>
        !q || (it.name && it.name.toLowerCase().includes(q)) || (it.abbr && it.abbr.toLowerCase().includes(q))
    );
    const list = document.getElementById('pp-ac-list');
    if (!list) return;
    ppAc.highlight = 0;
    if (!filtered.length) {
        list.innerHTML = '<div class="pp-ac-empty">Keine Treffer. Tab/Enter um „' + ppEscape(query) + '" zu übernehmen.</div>';
        ppAc.items = []; ppAc.customValue = query;
        return;
    }
    ppAc.customValue = null;
    list.innerHTML = filtered.map((it, i) => `
        <div class="pp-ac-item ${i === 0 ? 'is-highlight' : ''}" onclick="ppAcPick('${ppEscape(it.name).replace(/'/g, "\\'")}')">
            <span class="pp-ac-kuerzel" ${it.color ? `style="background:${it.color}22;color:${it.color};"` : ''}>${ppEscape(it.abbr || '?')}</span>
            <span>${ppEscape(it.name)}</span>
        </div>`).join('');
    ppAc.filtered = filtered;
}

function ppAcKey(e) {
    const list = document.getElementById('pp-ac-list');
    if (!list) return;
    const items = list.querySelectorAll('.pp-ac-item');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        ppAc.highlight = Math.min(items.length - 1, ppAc.highlight + 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        ppAc.highlight = Math.max(0, ppAc.highlight - 1);
    } else if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        if (ppAc.filtered && ppAc.filtered.length) {
            ppAcPick(ppAc.filtered[ppAc.highlight].name);
        } else if (ppAc.customValue) {
            ppAcPick(ppAc.customValue);
        }
        return;
    } else if (e.key === 'Escape') {
        ppAcClose();
        return;
    }
    items.forEach((el, i) => el.classList.toggle('is-highlight', i === ppAc.highlight));
}

function ppAcPick(name) {
    const row = ppState.activeRows.find(r => r.id === ppAc.rowId);
    if (!row) { ppAcClose(); return; }
    if (ppAc.field === 'responsible') {
        const names = ppParseNames(row.responsible);
        if (!names.includes(name)) names.push(name);
        ppSetField(row.id, 'responsible', ppJoinNames(names));
    } else {
        ppSetField(row.id, 'lead_responsible', name);
    }
    ppAcClose();
}

function ppAcClose() {
    const pop = document.getElementById('pp-autocomplete');
    if (pop) pop.style.display = 'none';
    document.removeEventListener('click', ppAcCloseHandler, { capture: true });
    ppAc.rowId = null;
}
function ppAcCloseHandler(e) {
    const pop = document.getElementById('pp-autocomplete');
    if (pop && !pop.contains(e.target)) ppAcClose();
}

/* ===== UI-Persistenz: Sidebar-Collapse (gleiches Pattern wie Chat) ===== */
function ppToggleSidebar() {
    const sidebar = document.getElementById('pp-sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed');
    const collapsed = sidebar.classList.contains('collapsed');
    try { localStorage.setItem('pp_sidebar_collapsed', collapsed ? '1' : '0'); } catch (_) {}
}
function ppRestoreUiState() {
    try {
        // Alte font-size-keys raeumen (Topbar-A-/A+ uebernimmt das)
        localStorage.removeItem('pp_fontsize');
    } catch (_) {}
    const page = document.querySelector('.pp-page');
    if (page) page.classList.remove('is-fz-sm', 'is-fz-md', 'is-fz-lg');
    // Sidebar-Collapse aus localStorage wiederherstellen
    try {
        const collapsed = localStorage.getItem('pp_sidebar_collapsed') === '1';
        if (collapsed) {
            const sidebar = document.getElementById('pp-sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    } catch (_) {}
}
window.ppToggleSidebar = ppToggleSidebar;

/* ===== Revisionen (Versionsverlauf) ===== */
async function ppOpenRevisionsModal() {
    if (!ppState.activePlanId) return;
    ppOpenModal('pp-revisions-modal');
    await ppLoadRevisions();
}

async function ppLoadRevisions() {
    const body = document.getElementById('pp-revisions-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/revisions');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppRenderRevisions(j.data.revisions);
    } catch (e) {
        body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
    }
}

function ppRenderRevisions(revs) {
    const body = document.getElementById('pp-revisions-body');
    body.innerHTML = `
        <div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">
            <input type="text" id="pp-rev-label" placeholder="Label (optional, z.B. „Vor großer Umstellung")"
                   style="flex:1;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-size:var(--d-fs-sm);">
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppCreateRevision()">Snapshot anlegen</button>
        </div>
        <div style="font-size:11px;color:var(--slate-500);margin-bottom:10px;">
            Max ${50} Versionen pro Plan. Älteste werden automatisch entfernt. Beim Restore wird ein Sicherheits-Snapshot des aktuellen Stands erstellt.
        </div>
        ${revs.length === 0 ? '<div style="padding:20px;text-align:center;color:var(--slate-400);">Noch keine Snapshots.</div>' : `
            <div style="display:flex;flex-direction:column;gap:6px;">
                ${revs.map((r, i) => {
                    const date = new Date(r.created_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
                    return `<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--slate-50);border-radius:6px;">
                        <span class="material-symbols-rounded" style="color:${i === 0 ? 'var(--emerald-600)' : 'var(--slate-400)'};font-size:18px;">${i === 0 ? 'commit' : 'history'}</span>
                        <div style="flex:1;">
                            <div style="font-weight:600;color:var(--slate-800);font-size:var(--d-fs-sm);">${ppEscape(r.label || 'Snapshot')}</div>
                            <div style="font-size:11px;color:var(--slate-500);">${date} · ${ppEscape(r.user_name || 'System')} · ${r.row_count || 0} Zeilen</div>
                        </div>
                        ${i === 0 ? '<span style="font-size:10px;background:var(--emerald-100);color:var(--emerald-700);padding:2px 8px;border-radius:10px;font-weight:700;text-transform:uppercase;">Neueste</span>' : `
                            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppRestoreRevision(${r.id})" title="Wiederherstellen">
                                <span class="material-symbols-rounded" style="font-size:14px;">restore</span> Restore
                            </button>
                        `}
                    </div>`;
                }).join('')}
            </div>
        `}`;
}

async function ppCreateRevision() {
    const label = document.getElementById('pp-rev-label').value.trim();
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/revisions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ label }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Snapshot erstellt', 'success');
        document.getElementById('pp-rev-label').value = '';
        ppLoadRevisions();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppRestoreRevision(revId) {
    if (!confirm('Diese Version wiederherstellen?\n\nDer aktuelle Stand wird vorher automatisch als Snapshot gesichert.')) return;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/revisions/${revId}/restore`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Wiederhergestellt', 'success');
        ppCloseModal('pp-revisions-modal');
        ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Right-Click-Kontextmenü auf Rows ===== */
function ppShowContextMenu(e, rowId) {
    e.preventDefault();
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const menu = document.getElementById('pp-context-menu');
    const isItem = row.row_type === 'item';

    const otherPlans = ppState.plans.filter(p => p.id !== ppState.activePlanId);
    const moveSubmenu = otherPlans.slice(0, 8).map(p =>
        `<button onclick="ppContextMoveToPlan(${rowId}, ${p.id})" style="display:block;width:100%;text-align:left;padding:5px 28px;border:none;background:transparent;cursor:pointer;font-size:var(--d-fs-xs);color:var(--slate-700);">→ ${ppEscape(p.title)}</button>`
    ).join('');

    menu.innerHTML = isItem ? `
        <button class="thx-contextmenu-item" onclick="ppContextDuplicate(${rowId})">
            <span class="material-symbols-rounded" style="font-size:14px;">content_copy</span> Duplizieren
        </button>
        <button class="thx-contextmenu-item" onclick="ppToggleDone(${rowId});ppHideContextMenu();">
            <span class="material-symbols-rounded" style="font-size:14px;">${parseInt(row.is_done) ? 'check_circle' : 'radio_button_unchecked'}</span> ${parseInt(row.is_done) ? 'Nicht erledigt' : 'Erledigt'}
        </button>
        <button class="thx-contextmenu-item" onclick="ppToggleRowFlag(${rowId},'is_focus');ppHideContextMenu();">
            <span class="material-symbols-rounded" style="font-size:14px;">flag</span> ${parseInt(row.is_focus) ? 'Fokus aus' : 'Fokus an'}
        </button>
        <button class="thx-contextmenu-item" onclick="ppToggleRowFlag(${rowId},'is_placeholder');ppHideContextMenu();">
            <span class="material-symbols-rounded" style="font-size:14px;">help_outline</span> ${parseInt(row.is_placeholder) ? 'Platzhalter aus' : 'Platzhalter'}
        </button>
        <button class="thx-contextmenu-item" onclick="ppToggleRowFlag(${rowId},'no_ticket');ppHideContextMenu();">
            <span class="material-symbols-rounded" style="font-size:14px;">block</span> ${parseInt(row.no_ticket) ? 'Doch Ticket' : 'Kein Ticket'}
        </button>
        ${otherPlans.length > 0 ? `
            <div style="height:1px;background:var(--slate-200);margin:4px 0;"></div>
            <div style="font-size:10px;color:var(--slate-400);padding:4px 10px;text-transform:uppercase;letter-spacing:0.04em;">In anderen Plan verschieben</div>
            ${moveSubmenu}
        ` : ''}
        <div style="height:1px;background:var(--slate-200);margin:4px 0;"></div>
        <button class="thx-contextmenu-item" style="color:var(--rose-600);" onclick="ppContextDelete(${rowId})">
            <span class="material-symbols-rounded" style="font-size:14px;">delete</span> Löschen
        </button>
    ` : `
        <button class="thx-contextmenu-item" style="color:var(--rose-600);" onclick="ppContextDelete(${rowId})">
            <span class="material-symbols-rounded" style="font-size:14px;">delete</span> Löschen
        </button>
    `;
    menu.style.display = 'block';
    ppPositionContextMenu(menu, e);
    setTimeout(() => document.addEventListener('click', ppHideContextMenu, { once: true }), 0);
}
function ppHideContextMenu() {
    document.getElementById('pp-context-menu').style.display = 'none';
}

/** Klappt ein bereits positioniertes Popover nach oben/links, wenn es ueber
 *  den Viewport hinausragt. Nutzt das tatsaechlich gerenderte Bounding-Rect.
 *  Anchor-Rect = das DOM-Element, an dem das Popover hing — wird fuer Top-Flip benutzt. */
function ppFlipFixedPopover(pop, anchorRect) {
    if (!pop || pop.style.display === 'none') return;
    const margin = 8;
    const vw = window.innerWidth, vh = window.innerHeight;
    const sx = window.scrollX || 0, sy = window.scrollY || 0;
    const r = pop.getBoundingClientRect();
    let needFlipX = r.right > vw - margin;
    let needFlipY = r.bottom > vh - margin;
    if (needFlipX) {
        // Rechts vom Anchor links flippen
        const newLeftViewport = Math.max(margin, (anchorRect ? anchorRect.right - r.width : vw - margin - r.width));
        pop.style.left = (newLeftViewport + sx) + 'px';
    }
    if (needFlipY) {
        // Oberhalb des Anchors statt darunter
        const newTopViewport = anchorRect
            ? Math.max(margin, anchorRect.top - 4 - r.height)
            : Math.max(margin, vh - margin - r.height);
        pop.style.top = (newTopViewport + sy) + 'px';
    }
    // Wenn das Popover hoeher ist als der Viewport, max-height + scroll
    const r2 = pop.getBoundingClientRect();
    if (r2.height > vh - 2 * margin) {
        pop.style.maxHeight = (vh - 2 * margin) + 'px';
        pop.style.overflowY = 'auto';
    }
}

/** Positioniert ein bereits sichtbares Kontextmenue so, dass es vollstaendig
 *  im Viewport bleibt. Klappt nach oben bzw. links, wenn rechts/unten kein Platz ist.
 *  Muss aufgerufen werden NACHDEM menu.style.display = 'block' gesetzt wurde. */
function ppPositionContextMenu(menu, ev) {
    const margin = 8;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    // Erst grob ankern (Viewport-Koord, kein Page-Scroll noetig wenn menu position:fixed waere;
    // wir nutzen pageX/Y -> dokumentbezogen, dann gegen Viewport bounding-rect testen).
    const sx = window.scrollX || window.pageXOffset || 0;
    const sy = window.scrollY || window.pageYOffset || 0;
    let x = ev.pageX, y = ev.pageY;
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
    // Jetzt sind Width/Height bekannt — flip wenn noetig
    const rect = menu.getBoundingClientRect();
    // Rechts ueber den Rand -> nach links flippen
    if (rect.right > vw - margin) {
        x = Math.max(sx + margin, ev.pageX - rect.width);
    }
    // Unten ueber den Rand -> nach oben flippen
    if (rect.bottom > vh - margin) {
        y = Math.max(sy + margin, ev.pageY - rect.height);
    }
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
    // Wenn das Menue trotzdem hoeher als der Viewport ist (sehr lange Listen):
    // max-height begrenzen und scrollbar machen
    const finalRect = menu.getBoundingClientRect();
    if (finalRect.height > vh - 2 * margin) {
        menu.style.maxHeight = (vh - 2 * margin) + 'px';
        menu.style.overflowY = 'auto';
    } else {
        menu.style.maxHeight = '';
        menu.style.overflowY = '';
    }
}

async function ppContextDuplicate(rowId) {
    ppHideContextMenu();
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    try {
        // EIN POST mit allen relevanten Feldern. ist_hours + is_done werden zurueckgesetzt,
        // damit die Kopie als "neue offene Aufgabe" startet — der Rest ist identisch.
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({
                row_type:         row.row_type,
                description:      row.description,
                planned_hours:    row.planned_hours,
                ist_hours:        0,
                responsible:      row.responsible,
                lead_responsible: row.lead_responsible,
                timeframe:        row.timeframe,
                deadline:         row.deadline,
                notes:            row.notes,
                actual_hours:     row.actual_hours,
                date_from:        row.date_from,
                date_to:          row.date_to,
                is_focus:         row.is_focus,
                is_placeholder:   row.is_placeholder,
                no_ticket:        row.no_ticket,
                is_done:          0,
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const newId = j.data.id;
        // Plan frisch laden -> neue Row finden -> direkt hinter das Original einsortieren -> Reorder persistieren
        const planRes = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId).then(x => x.json());
        if (!planRes.success) throw new Error(planRes.message);
        const allRows = planRes.data.rows || [];
        const newRow = allRows.find(x => x.id === newId);
        if (!newRow) throw new Error('Neue Zeile konnte nicht geladen werden');
        const others = allRows.filter(x => x.id !== newId);
        const idx = others.findIndex(x => x.id === rowId);
        if (idx >= 0) others.splice(idx + 1, 0, newRow); else others.push(newRow);
        ppState.activeRows = others;
        ppRenderEditor();
        ppPersistReorder();
        App.showNotification('Zeile dupliziert', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppContextMoveToPlan(rowId, targetPlanId) {
    ppHideContextMenu();
    const targetPlan = ppState.plans.find(p => p.id === targetPlanId);
    if (!confirm(`Zeile in Plan „${targetPlan.title}" verschieben?`)) return;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows/${rowId}/move`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ target_plan_id: targetPlanId, position: 0 }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Verschoben', 'success');
        ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Undo-Delete-Toast ===== */
const ppUndoState = { lastDeleted: null };

function ppContextDelete(rowId) {
    ppHideContextMenu();
    ppDeleteRowWithUndo(rowId);
}

async function ppDeleteRowWithUndo(rowId) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    // Snapshot vor dem Löschen
    ppUndoState.lastDeleted = { row: { ...row }, position: ppState.activeRows.indexOf(row) };
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows/${rowId}`, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppState.activeRows = ppState.activeRows.filter(r => r.id !== rowId);
        ppRenderEditor();
        ppShowUndoToast();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppShowUndoToast() {
    const old = document.getElementById('pp-undo-toast');
    if (old) old.remove();
    const toast = document.createElement('div');
    toast.id = 'pp-undo-toast';
    toast.style.cssText = `
        position:fixed;bottom:20px;left:50%;transform:translateX(-50%);
        background:var(--slate-800);color:#fff;padding:10px 16px;border-radius:8px;
        box-shadow:0 10px 30px rgba(0,0,0,.25);
        display:flex;align-items:center;gap:14px;z-index:10000;
        font-size:var(--d-fs-sm);
    `;
    toast.innerHTML = `
        <span>Zeile gelöscht</span>
        <button onclick="ppUndoDelete()" style="background:transparent;color:var(--thoxan-300);border:none;cursor:pointer;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.04em;">Rückgängig</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 8000);
}

async function ppUndoDelete() {
    if (!ppUndoState.lastDeleted) return;
    const { row, position } = ppUndoState.lastDeleted;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({
                row_type: row.row_type, description: row.description,
                planned_hours: row.planned_hours, ist_hours: row.ist_hours,
                responsible: row.responsible, lead_responsible: row.lead_responsible,
                timeframe: row.timeframe, deadline: row.deadline, notes: row.notes,
                actual_hours: row.actual_hours,
                is_done: row.is_done, is_focus: row.is_focus,
                is_placeholder: row.is_placeholder, no_ticket: row.no_ticket,
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppUndoState.lastDeleted = null;
        const toast = document.getElementById('pp-undo-toast');
        if (toast) toast.remove();
        ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Editor-Filter ===== */
function ppToggleStatusFilter(k) {
    const s = ppState.editorFilters.status;
    // Gegenseitig ausschliessende Filter-Gruppen. Wer eines aktiviert, dem
    // werden alle in derselben Gruppe gelisteten anderen automatisch entfernt.
    // - open/done: ein Item ist entweder offen oder erledigt
    // - no-ticket/no-asana/decision: alle drei sind verschiedene Asana-Status,
    //   können nicht gleichzeitig zutreffen.
    const exklusivGruppen = {
        open:       ['done'],
        done:       ['open'],
        'no-ticket': ['no-asana', 'decision'],
        'no-asana':  ['no-ticket', 'decision'],
        decision:    ['no-ticket', 'no-asana'],
    };
    if (k === 'all') {
        ppState.editorFilters.status = ['all'];
    } else {
        const idx = s.indexOf(k);
        if (idx >= 0) s.splice(idx, 1);
        else {
            (exklusivGruppen[k] || []).forEach(other => {
                const i = s.indexOf(other);
                if (i >= 0) s.splice(i, 1);
            });
            s.push(k);
        }
        const filtered = s.filter(x => x !== 'all');
        ppState.editorFilters.status = filtered.length ? filtered : ['all'];
    }
    ppSaveFilters();
    ppRenderEditor();
}
function ppSetEditorFilter(key, value) {
    ppState.editorFilters[key] = value;
    ppSaveFilters();
    ppRenderEditor();
}
function ppResetFilters() {
    ppState.editorFilters = { status: ['all'], lead: '', responsible: '', search: '' };
    ppSaveFilters();
    ppRenderEditor();
}
function ppSaveFilters() {
    if (!ppState.activePlanId) return;
    try { localStorage.setItem('pp_filters_' + ppState.activePlanId, JSON.stringify(ppState.editorFilters)); } catch (_) {}
}

/* ===== Save ===== */
function ppShowSaving(state) {
    const pill = document.getElementById('pp-save-indicator');
    if (!pill) return;
    pill.classList.remove('is-saved', 'is-error');
    if (state === 'saving') {
        pill.classList.add('is-show');
        pill.innerHTML = '<span class="material-symbols-rounded">sync</span> speichert…';
    } else if (state === 'saved') {
        pill.classList.add('is-show', 'is-saved');
        pill.innerHTML = '<span class="material-symbols-rounded">check</span> gespeichert';
        setTimeout(() => pill.classList.remove('is-show'), 1500);
    } else if (state === 'error') {
        pill.classList.add('is-show', 'is-error');
        pill.innerHTML = '<span class="material-symbols-rounded">error</span> Fehler';
    }
}

async function ppSavePlanField(el) {
    const field = el.dataset.field;
    const id = parseInt(el.dataset.id);
    if (!field || !id) return;
    let value = el.tagName === 'SELECT' ? el.value : (el.tagName === 'INPUT' ? el.value : el.textContent.trim());
    if (field === 'title' && !value) { el.textContent = ppState.activePlan.title; return; }
    ppShowSaving('saving');
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ [field]: value || null }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppState.activePlan[field] = value;
        const idx = ppState.plans.findIndex(p => p.id === id);
        if (idx >= 0) ppState.plans[idx][field] = value;

        // Bei Customer- oder Zeitraum-Änderung: Budget neu laden
        if (['customer_id', 'period_from', 'period_to'].includes(field)) {
            try {
                const bRes = await fetch('/api/v1/admin/projektplanner/plans/' + id + '/budget-soll').then(r => r.json());
                if (bRes.success) {
                    ppState.planBudget = bRes.data;
                    ppUpdateStatsBar();
                }
            } catch (_) {}
        }
        // Customer-Wechsel: Customer-Color für Sidebar nachladen
        if (field === 'customer_id') {
            const c = ppState.customers.find(c => c.id == value);
            if (idx >= 0) {
                ppState.plans[idx].customer_name = c ? c.name : null;
                ppState.plans[idx].customer_color = c ? c.hex_color : null;
            }
        }
        ppRenderPlanList();
        ppShowSaving('saved');
    } catch (e) {
        App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
        ppShowSaving('error');
    }
}

/** Speichert den Projekt-Status (risiko_modus) — eigener Endpunkt, unabhaengig vom
 *  Workflow-Status (plan_status). Funktioniert jederzeit, auch bei abgeschlossenen
 *  oder reporteten Plaenen (keine Lebenszyklus-Einschraenkung serverseitig). */
async function ppSavePlanRisiko(el) {
    const id = parseInt(el.dataset.id);
    const modus = el.value;
    if (!id) return;
    const notiz = (ppState.activePlan && ppState.activePlan.id === id ? ppState.activePlan.risiko_notiz : '') || '';
    ppShowSaving('saving');
    try {
        const r = await fetch('/api/v1/projektplanner/plan-risiko', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: id, modus, notiz }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        if (ppState.activePlan && ppState.activePlan.id === id) ppState.activePlan.risiko_modus = modus;
        const idx = ppState.plans.findIndex(p => p.id === id);
        if (idx >= 0) ppState.plans[idx].risiko_modus = modus;
        ppRenderPlanList();
        ppShowSaving('saved');
    } catch (e) {
        App.showNotification('Status konnte nicht gespeichert werden: ' + e.message, 'error');
        ppShowSaving('error');
    }
}

/** Massenbearbeitung: setzt den Projekt-Status (risiko_modus) fuer ALLE aktuell
 *  ausgewaehlten Plaene (Multi-Plan-Modus) auf einmal. */
async function ppSavePlanRisikoBulk(el) {
    const modus = el.value;
    if (!modus) return; // "— gemischt —"-Platzhalter, nichts zu tun
    const ids = (ppState.activePlanIds || []).slice();
    if (!ids.length) return;
    ppShowSaving('saving');
    try {
        const results = await Promise.all(ids.map(id =>
            fetch('/api/v1/projektplanner/plan-risiko', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: id, modus, notiz: '' }),
            }).then(r => r.json()).catch(() => ({ success: false }))
        ));
        const ok = results.filter(r => r && r.success).length;
        // Lokalen State aktualisieren, damit Sidebar-Marker + Select sofort stimmen.
        (ppState.multiPlans || []).forEach(pl => { if (ids.includes(pl.id)) pl.risiko_modus = modus; });
        ids.forEach(id => { const i = ppState.plans.findIndex(p => p.id === id); if (i >= 0) ppState.plans[i].risiko_modus = modus; });
        ppRenderPlanList();
        if (ok === ids.length) {
            ppShowSaving('saved');
            App.showNotification(`Status für ${ok} Pläne gesetzt.`, 'success');
        } else {
            ppShowSaving('error');
            App.showNotification(`Status: nur ${ok} von ${ids.length} Plänen gesetzt.`, 'error');
        }
    } catch (e) {
        ppShowSaving('error');
        App.showNotification('Massen-Status fehlgeschlagen: ' + e.message, 'error');
    }
}

/** Speichert ein Feld direkt (ohne Debounce, ohne contenteditable-Element). */
function ppSetField(rowId, field, value) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (row) row[field] = value;
    // planId fuer den nachfolgenden Save sicher mitgeben — sonst kann ein
    // Plan-Wechsel zwischen Toggle und Server-Response den falschen Plan treffen.
    ppState._pendingSavePlanId = ppState._pendingSavePlanId || {};
    ppState._pendingSavePlanId[rowId] = (row && row._planId) || ppState.activePlanId;
    ppDoSaveRow(rowId, field, value);
    // Personen-Chips müssen sofort re-rendern, ebenso Done/Focus/Placeholder
    if (['responsible', 'lead_responsible', 'is_done', 'is_focus', 'is_placeholder', 'no_ticket'].includes(field)) {
        ppRenderEditor();
    }
}

function ppToggleDone(rowId) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const istErledigt = parseInt(row.is_done);

    // Wieder-Öffnen: ohne Nachfrage direkt umschalten.
    if (istErledigt) {
        ppSetField(rowId, 'is_done', 0);
        return;
    }

    // Auf-Erledigt-Setzen: pruefen, ob Modal noetig ist.
    // Kriterien: Zeitraum leer | IST leer | IST weicht von Soll deutlich ab.
    const zeitraum = (row.timeframe || '').toString().trim();
    const ist  = parseFloat(row.ist_hours)     || 0;
    const soll = parseFloat(row.planned_hours) || 0;

    const zeitraumLeer = zeitraum === '';
    const istLeer      = !(parseFloat(row.ist_hours) > 0);
    // "Deutliche Abweichung" = mehr als 25% Differenz UND mind. 0.25h absolut.
    const abweichung   = (ist > 0 && soll > 0)
        ? (Math.abs(ist - soll) > Math.max(0.25, soll * 0.25))
        : false;

    if (!zeitraumLeer && !istLeer && !abweichung) {
        // Alles okay — direkt abschliessen ohne Modal.
        ppSetField(rowId, 'is_done', 1);
        return;
    }

    // Modal vorbereiten + oeffnen
    const today = new Date();
    const defaultDate = `${String(today.getDate()).padStart(2,'0')}.${String(today.getMonth()+1).padStart(2,'0')}.`;
    ppState._doneModalRowId = rowId;
    document.getElementById('pp-done-zeitraum').value = zeitraum || defaultDate;
    // Vorschlag IST: wenn leer -> Soll uebernehmen (haeufiger Fall: ist = wie geplant)
    document.getElementById('pp-done-ist').value  = ist > 0 ? ppFormatNum(ist) : (soll > 0 ? ppFormatNum(soll) : '');
    document.getElementById('pp-done-soll').value = soll > 0 ? ppFormatNum(soll) : '';

    // Erklaerung warum das Modal kommt
    const hinweise = [];
    if (zeitraumLeer) hinweise.push('Zeitraum ist leer — bitte Erledigungs-Datum eintragen.');
    if (istLeer)      hinweise.push('IST-Stunden sind leer — Soll als Vorschlag uebernommen.');
    if (abweichung)   hinweise.push(`IST (${ppFormatNum(ist)} h) weicht deutlich vom Soll (${ppFormatNum(soll)} h) ab — bitte pruefen.`);
    document.getElementById('pp-done-modal-hint').innerHTML = hinweise.join('<br>') || 'Werte bestätigen oder anpassen.';

    const warn = document.getElementById('pp-done-warning');
    if (warn) warn.style.display = 'none';

    ppOpenModal('pp-done-modal');
    setTimeout(() => document.getElementById(zeitraumLeer ? 'pp-done-zeitraum' : 'pp-done-ist').focus(), 50);
}

function ppCloseDoneModal() {
    ppCloseModal('pp-done-modal');
    ppState._doneModalRowId = null;
}

function ppConfirmDoneModal() {
    const rowId = ppState._doneModalRowId;
    if (!rowId) return;
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;

    // Zeitraum vor dem Speichern auch dann normalisieren, wenn der User direkt
    // auf "Übernehmen" klickt ohne das Feld zu verlassen (onblur greift dann nicht).
    const zeitraum = ppFormatTimeframe(document.getElementById('pp-done-zeitraum').value.trim());
    const istStr   = document.getElementById('pp-done-ist').value.trim();
    const sollStr  = document.getElementById('pp-done-soll').value.trim();

    if (!zeitraum) {
        const w = document.getElementById('pp-done-warning');
        w.textContent = 'Zeitraum ist Pflicht.';
        w.style.display = 'block';
        return;
    }

    // Komma -> Punkt, dann parseFloat
    const parse = (s) => {
        const n = parseFloat(String(s).replace(',', '.'));
        return isNaN(n) ? null : n;
    };
    const ist  = parse(istStr);
    const soll = parse(sollStr);

    // Nur Felder schreiben, die sich tatsaechlich geaendert haben — spart Save-Calls.
    if (zeitraum !== (row.timeframe || '').toString().trim()) {
        ppSetField(rowId, 'timeframe', zeitraum);
    }
    if (ist !== null && ist !== (parseFloat(row.ist_hours) || 0)) {
        ppSetField(rowId, 'ist_hours', ist);
    }
    if (soll !== null && soll !== (parseFloat(row.planned_hours) || 0)) {
        ppSetField(rowId, 'planned_hours', soll);
    }
    ppSetField(rowId, 'is_done', 1);
    ppCloseDoneModal();
}
function ppToggleRowFlag(rowId, flag) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const newVal = parseInt(row[flag]) ? 0 : 1;
    ppSetField(rowId, flag, newVal);
}

/** Normalisiert eine Zeitraum-Eingabe auf das Schema DD.MM. bzw. DD.-DD.MM.
 *  bzw. DD.MM.-DD.MM. — mit fuehrenden Nullen.
 *  Beispiele:
 *    "13.4"      -> "13.04."
 *    "1-2.5"     -> "01.-02.05."
 *    "13.5-2.6"  -> "13.05.-02.06."
 *  Bei nicht-erkennbarer Eingabe wird der Original-Text zurueckgegeben. */
function ppFormatTimeframe(raw) {
    if (!raw) return '';
    const s = raw.replace(/\s+/g, '').replace(/[—–]/g, '-');
    if (!/\d/.test(s)) return raw;
    const pad = (n) => String(n).padStart(2, '0');
    const parsePart = (p) => {
        const t = p.replace(/\.+$/, '');
        const m = t.match(/^(\d{1,2})(?:\.(\d{1,2}))?$/);
        if (!m) return null;
        const day = parseInt(m[1], 10);
        const month = m[2] ? parseInt(m[2], 10) : null;
        if (day < 1 || day > 31) return null;
        if (month !== null && (month < 1 || month > 12)) return null;
        return { day, month };
    };
    const formatSpan = (span) => {
        const parts = span.split('-');
        if (parts.length === 1) {
            const p = parsePart(parts[0]);
            if (!p || p.month === null) return span;
            return `${pad(p.day)}.${pad(p.month)}.`;
        }
        if (parts.length === 2) {
            const a = parsePart(parts[0]);
            const b = parsePart(parts[1]);
            if (!a || !b || b.month === null) return span;
            if (a.month === null) return `${pad(a.day)}.-${pad(b.day)}.${pad(b.month)}.`;
            return `${pad(a.day)}.${pad(a.month)}.-${pad(b.day)}.${pad(b.month)}.`;
        }
        return span;
    };
    // Mehrere Zeitspannen via Komma trennen, jede einzeln formatieren.
    const fragments = s.split(',').map(x => x.trim()).filter(Boolean);
    if (fragments.length > 1) return fragments.map(formatSpan).join(', ');
    return formatSpan(fragments[0] || s);
}

/** „Passt alles" — entfernt das Review-Flag einer Zeile. */
async function ppMarkReviewed(rowId) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const planId = row._planId || ppState.activePlanId;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${planId}/rows/${rowId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ review_flag: 0, review_note: '' }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        row.review_flag = 0;
        row.review_note = null;
        const tr = document.querySelector(`tr[data-id="${rowId}"]`);
        if (tr) {
            tr.classList.remove('pp-row-review');
            const btn = tr.querySelector('.pp-review-btn');
            if (btn) btn.remove();
            const pill = tr.querySelector('.pp-ai-clarify-pill');
            if (pill) pill.remove();
        }
        ppUpdateStatsBar();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

// ===== Lernschleife: „passt nicht" → Regel-Vorschlag + Regel-Manager =====

/** Kleiner API-Helfer fuer KI-Regeln. */
async function ppApiRule(method, id, body) {
    const url = '/api/v1/admin/projektplanner/ai-rules' + (id ? '/' + id : '');
    const r = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
        body: body ? JSON.stringify(body) : undefined,
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.message);
    return j.data;
}

/** „Passt nicht" auf einer KI-Zeile — haelt die Korrektur als Regel-Vorschlag fuer den Kunden fest. */
async function ppRejectAiRow(rowId) {
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const cid = ppState.activePlan && ppState.activePlan.customer_id ? ppState.activePlan.customer_id : null;
    if (!cid) { App.showNotification('Dem Plan ist kein Kunde zugeordnet — Regel nicht speicherbar.', 'error'); return; }
    const ref = (row.description || '').replace(/\s+/g, ' ').slice(0, 90);
    const rule = prompt('Was soll die KI bei diesem Kunden künftig anders machen?\nDeine Eingabe wird als Regel-Vorschlag gespeichert.\n\nBezug: „' + ref + '…"', '');
    if (rule === null) return;
    const t = rule.trim();
    if (!t) return;
    try {
        await ppApiRule('POST', 0, { customer_id: cid, rule_text: t, status: 'vorschlag', source: 'feedback' });
        App.showNotification('Als Regel-Vorschlag gespeichert — unter „KI-Regeln" aktivierbar.', 'success');
        ppMarkReviewed(rowId); // Zeile ist damit bearbeitet
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/** Regel-Manager fuer den aktuellen Kunden (anlegen, aktivieren, bearbeiten, loeschen). */
async function ppOpenAiRules() {
    const cid = (ppState.activePlan && ppState.activePlan.customer_id) ? ppState.activePlan.customer_id : 0;
    const cname = (ppState.activePlan && ppState.activePlan.customer_name) ? ppState.activePlan.customer_name : 'Kunde';
    if (!cid) { App.showNotification('Bitte einen Plan mit Kunde öffnen.', 'error'); return; }

    const wrap = document.createElement('div');
    wrap.className = 'thx-modal-backdrop';
    wrap.style.display = 'flex';
    wrap.innerHTML = `
        <div class="thx-modal" style="width:640px;max-width:94vw;">
            <div class="thx-modal-header"><h3 class="thx-modal-title">KI-Regeln — ${ppEscape(cname)}</h3>
                <button class="thx-modal-close" data-x>&times;</button></div>
            <div class="thx-modal-body pp-modal-body">
                <p style="font-size:12px;color:var(--slate-500);margin:0 0 6px;">Aktive Regeln fließen automatisch in „Duplizieren mit KI" für diesen Kunden ein. Vorschläge (aus „passt nicht") werden erst wirksam, wenn Du sie aktivierst.</p>
                <p style="margin:0 0 10px;"><a href="/rules?scope=${cid}" target="_blank" rel="noopener" style="font-size:12px;color:var(--thoxan-600);">Alle Regeln dieses Kunden in /rules verwalten →</a></p>
                <div id="ppar-body" style="max-height:52vh;overflow:auto;">Lädt…</div>
            </div>
            <div class="thx-modal-footer" style="gap:8px;">
                <input type="text" id="ppar-new" placeholder="Neue Regel (z.B. „Google Ads immer 2 h einplanen")…" style="flex:1;padding:6px 8px;">
                <button class="thx-btn thx-btn-primary" data-add>+ Regel</button>
            </div>
        </div>`;
    document.body.appendChild(wrap);
    const close = () => wrap.remove();

    const load = async () => {
        const body = wrap.querySelector('#ppar-body');
        try {
            const res = await fetch('/api/v1/admin/projektplanner/ai-rules?customer_id=' + cid).then(r => r.json());
            if (!res.success) throw new Error(res.message);
            wrap._rules = res.data.rules || [];
            body.innerHTML = ppRenderAiRulesList(wrap._rules);
        } catch (e) { body.innerHTML = '<div style="color:var(--rose-600);padding:12px;">' + ppEscape(e.message) + '</div>'; }
    };

    wrap.addEventListener('click', async (e) => {
        if (e.target === wrap || e.target.closest('[data-x]')) { close(); return; }
        if (e.target.closest('[data-add]')) {
            const inp = wrap.querySelector('#ppar-new'); const t = inp.value.trim(); if (!t) return;
            try { await ppApiRule('POST', 0, { customer_id: cid, rule_text: t, status: 'aktiv', source: 'manuell' }); inp.value = ''; await load(); }
            catch (err) { App.showNotification(err.message, 'error'); }
            return;
        }
        const btn = e.target.closest('[data-act]'); if (!btn) return;
        const id = parseInt(btn.dataset.id || 0); const act = btn.dataset.act;
        try {
            if (act === 'del') { if (!confirm('Regel wirklich löschen?')) return; await ppApiRule('DELETE', id); }
            else if (act === 'activate') { await ppApiRule('POST', id, { status: 'aktiv', is_active: 1 }); }
            else if (act === 'toggle')   { await ppApiRule('POST', id, { is_active: btn.dataset.on === '1' ? 0 : 1 }); }
            else if (act === 'edit') {
                const rule = (wrap._rules || []).find(x => x.id === id);
                const nt = prompt('Regel bearbeiten:', rule ? rule.rule_text : ''); if (nt === null) return;
                await ppApiRule('POST', id, { rule_text: nt });
            }
            await load();
        } catch (err) { App.showNotification(err.message, 'error'); }
    });
    load();
}

function ppRenderAiRulesList(rules) {
    if (!rules || !rules.length) {
        return '<div style="padding:14px;color:var(--slate-500);font-size:13px;">Noch keine Regeln. Lege unten eine an — oder nutze bei einer KI-Zeile „passt nicht", dann entsteht hier ein Vorschlag.</div>';
    }
    return rules.map(r => {
        const isSug = r.status === 'vorschlag';
        const isGlobal = !r.customer_id;
        const active = parseInt(r.is_active) === 1 && !isSug;
        const badge = isSug
            ? '<span style="background:var(--amber-50);color:var(--amber-800);border:1px solid var(--amber-300);border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;">VORSCHLAG</span>'
            : (active
                ? '<span style="background:var(--emerald-50);color:var(--emerald-700);border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;">AKTIV</span>'
                : '<span style="background:var(--slate-100);color:var(--slate-500);border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;">INAKTIV</span>');
        const gl = isGlobal ? ' <span style="font-size:10px;color:var(--slate-400);">· global</span>' : '';
        const src = r.source === 'feedback' ? 'aus Feedback' : 'manuell';
        const rightBtn = isSug
            ? `<button class="thx-btn thx-btn-small thx-btn-primary" data-act="activate" data-id="${r.id}">Aktivieren</button>`
            : `<button class="thx-btn thx-btn-small thx-btn-secondary" data-act="toggle" data-id="${r.id}" data-on="${active ? 1 : 0}">${active ? 'Deaktivieren' : 'Aktivieren'}</button>`;
        return `<div style="display:flex;gap:8px;align-items:flex-start;padding:8px 2px;border-bottom:1px solid var(--slate-100);">
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;color:var(--slate-800);white-space:pre-wrap;">${ppEscape(r.rule_text)}</div>
                <div style="margin-top:3px;">${badge}${gl} <span style="font-size:10px;color:var(--slate-400);">${src}</span></div>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0;">
                ${rightBtn}
                <button class="thx-btn thx-btn-small thx-btn-secondary" data-act="edit" data-id="${r.id}" title="Bearbeiten"><span class="material-symbols-rounded" style="font-size:14px;">edit</span></button>
                <button class="thx-btn thx-btn-small thx-btn-secondary" data-act="del" data-id="${r.id}" title="Löschen" style="color:var(--rose-600);"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button>
            </div>
        </div>`;
    }).join('');
}

function ppSaveRowField(el) {
    const field = el.dataset.field;
    const id = parseInt(el.dataset.id);
    if (!field || !id) return;
    let value;
    if (el.type === 'checkbox') value = el.checked ? 1 : 0;
    else if (el.dataset.numeric) {
        // Komma → Punkt für numerische Felder
        const text = el.textContent.trim().replace(',', '.');
        value = parseFloat(text) || 0;
        // Anzeige in der Zelle auf das deutsche Standardformat normalisieren
        // ("0,5" und "0.5" wird beides zu "0,50").
        el.textContent = value > 0 ? ppFormatNum(value) : '';
    } else value = el.textContent.trim();

    // Zeitraum-Feld: Eingabe automatisch in DD.MM.-Schema bringen.
    if (field === 'timeframe') {
        const fmt = ppFormatTimeframe(value);
        if (fmt !== value) { value = fmt; el.textContent = fmt; }
    }

    const row = ppState.activeRows.find(r => r.id === id);
    if (row) row[field] = value;

    // Sofort-Update: Top-Stats-Bar UND Sektions-Pillen, ohne auf den Server zu warten.
    // Server-Save passiert weiterhin debounced via ppDoSaveRow.
    if (['ist_hours', 'planned_hours', 'is_done', 'no_ticket'].includes(field)) {
        ppUpdateStatsBar();
        ppUpdateSectionPillForRow(id);
    }

    // Pending-Save-Queue: alle ausstehenden Saves vorhalten, damit sie bei
    // Tab-Schließen, Plan-Wechsel oder beforeunload geflusht werden können.
    // Bisher landeten Saves nur in setTimeout-Callbacks — wenn dazwischen
    // etwas die JS-Welt resettet, gehen die Daten verloren.
    ppState.pendingSaves = ppState.pendingSaves || {};
    const planId = (row && row._planId) || ppState.activePlanId;
    // Wenn schon ein Save für dieselbe Row im Timer ist: nur das aktuelle Feld
    // überschreiben (verschiedene Felder einer Row können nebeneinander warten).
    const prev = ppState.pendingSaves[id] || { planId, fields: {} };
    prev.planId = planId;
    prev.fields[field] = value;
    if (prev.timerId) clearTimeout(prev.timerId);
    // Sofort speichern — onblur ist bereits eine bewusste User-Aktion, kein
    // Debounce nötig. Pending-Queue bleibt, damit Flush-on-Leave + Plan-Wechsel
    // weiterhin sicher greifen, falls aus irgendeinem Grund noch ein Save offen ist.
    prev.timerId = setTimeout(() => ppFlushPendingSave(id), 0);
    ppState.pendingSaves[id] = prev;
    // Legacy-Kompat: saveTimers leeren — wird sonst von altem Code referenziert
    if (ppState.saveTimers[id]) { clearTimeout(ppState.saveTimers[id]); delete ppState.saveTimers[id]; }
}

/** Schickt alle Pending-Felder einer Zeile in einem Request raus. */
async function ppFlushPendingSave(id) {
    const p = ppState.pendingSaves && ppState.pendingSaves[id];
    if (!p) return;
    // aus der Queue entfernen BEVOR wir senden, sonst kann ein paralleler
    // ppFlushAllPending() denselben Save nochmal feuern.
    delete ppState.pendingSaves[id];
    if (p.timerId) clearTimeout(p.timerId);
    for (const [field, value] of Object.entries(p.fields)) {
        // _planId ist beim Schedulen festgenagelt, damit auch nach Plan-Wechsel
        // der Save am korrekten Plan landet.
        try {
            await ppDoSaveRow(id, field, value, p.planId);
        } catch (_) { /* Fehler wird in ppDoSaveRow visualisiert */ }
    }
}

/** Wartet alle pending Saves ab (für Plan-Wechsel — User sieht erst neuen Plan
 *  wenn alle Eingaben sicher gespeichert sind). */
async function ppFlushAllPendingAwait() {
    const ids = Object.keys(ppState.pendingSaves || {});
    for (const idStr of ids) {
        try { await ppFlushPendingSave(parseInt(idStr)); } catch (_) {}
    }
}

/** Flusht ALLE pending Saves synchron (für beforeunload / Plan-Wechsel). */
function ppFlushAllPending(useBeacon = false) {
    const ids = Object.keys(ppState.pendingSaves || {});
    if (ids.length === 0) return;
    ids.forEach(id => {
        const p = ppState.pendingSaves[id];
        if (!p) return;
        if (p.timerId) clearTimeout(p.timerId);
        delete ppState.pendingSaves[id];
        for (const [field, value] of Object.entries(p.fields)) {
            // beforeunload: fetch wird vom Browser oft abgebrochen → sendBeacon nutzen.
            // sendBeacon ist POST-only, wir nutzen daher einen Spezial-Endpoint, der
            // PUT-Daten als POST entgegen nimmt (siehe handler.php-Route).
            const url = `/api/v1/admin/projektplanner/plans/${p.planId}/rows/${id}/save-beacon`;
            const body = JSON.stringify({ [field]: value });
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
            } else {
                // Synchrones fetch mit keepalive: läuft auch bei Tab-Schließen weiter (modern browsers).
                fetch(`/api/v1/admin/projektplanner/plans/${p.planId}/rows/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                    body, keepalive: true,
                }).catch(() => {});
            }
        }
    });
}

/** Aktualisiert die Subtotal-Pillen der Sektion, zu der diese Zeile gehört,
 *  in-place ohne die ganze Tabelle neu zu rendern. */
function ppUpdateSectionPillForRow(rowId) {
    const idx = ppState.activeRows.findIndex(r => r.id === rowId);
    if (idx < 0) return;
    // Sektion oberhalb finden
    let sectionId = null;
    for (let i = idx; i >= 0; i--) {
        if (ppState.activeRows[i].row_type === 'section') { sectionId = ppState.activeRows[i].id; break; }
    }
    if (!sectionId) return;
    const tr = document.querySelector(`tr[data-id="${sectionId}"]`);
    if (!tr) return;
    const sub = ppSectionSubtotal(sectionId);
    const totals = tr.querySelector('.pp-sec-totals');
    if (!totals) return;
    const istChip = totals.querySelector('.pp-sb-sub-chip.is-ist');
    const plnChip = totals.querySelector('.pp-sb-sub-chip.is-planned');
    if (istChip) {
        // Struktur: <icon> "X,XX h" <span.pp-sb-sub-ts>X,XX TS</span>
        // → das erste Text-Node nach dem Icon ersetzen, dann den TS-Span aktualisieren.
        const icon = istChip.querySelector('.material-symbols-rounded');
        const ts = istChip.querySelector('.pp-sb-sub-ts');
        if (icon && icon.nextSibling) icon.nextSibling.nodeValue = ' ' + ppFormatNum(sub.ist) + ' h ';
        if (ts) ts.textContent = ppFormatNum(ppHoursToTs(sub.ist)) + ' TS';
    }
    if (plnChip) {
        const icon = plnChip.querySelector('.material-symbols-rounded');
        const ts = plnChip.querySelector('.pp-sb-sub-ts');
        if (icon && icon.nextSibling) icon.nextSibling.nodeValue = ' ' + ppFormatNum(sub.soll) + ' h ';
        if (ts) ts.textContent = ppFormatNum(ppHoursToTs(sub.soll)) + ' TS';
    }
    // Container sichtbar machen, falls die Sektion zuerst mit 0/0 gerendert wurde.
    const visible = (sub.ist > 0 || sub.soll > 0);
    totals.style.display = visible ? '' : 'none';
}

/* Global Keyboard + Paste Handler für Inline-Edit-Felder */
document.addEventListener('focusin', e => {
    if (e.target.classList && e.target.classList.contains('pp-edit')) {
        // Snapshot für Esc-Undo
        e.target.dataset.undo = e.target.textContent;
        // Select-all bei numerischen oder kurzen Feldern
        if (e.target.dataset.numeric || e.target.dataset.field === 'deadline' || e.target.dataset.field === 'timeframe') {
            setTimeout(() => {
                const range = document.createRange();
                range.selectNodeContents(e.target);
                const sel = window.getSelection();
                sel.removeAllRanges(); sel.addRange(range);
            }, 10);
        }
    }
});
document.addEventListener('keydown', e => {
    const el = e.target;
    if (!el.classList || !el.classList.contains('pp-edit')) return;
    if (e.key === 'Escape') {
        // Revert zu undo-Snapshot
        const undo = el.dataset.undo;
        if (undo !== undefined) el.textContent = undo;
        el.blur();
        e.preventDefault();
    } else if (e.key === 'Enter') {
        // Enter ohne Modifier committet. Mit Shift/Alt/Meta/Ctrl fügen wir
        // einen Zeilenumbruch ein (Excel-Style: Opt+Cmd+Enter geht auch).
        if (!e.shiftKey && !e.metaKey && !e.altKey && !e.ctrlKey) {
            e.preventDefault();
            el.blur();
        } else {
            e.preventDefault();
            const sel = window.getSelection();
            if (sel && sel.rangeCount) {
                const range = sel.getRangeAt(0);
                range.deleteContents();
                const tn = document.createTextNode('\n');
                range.insertNode(tn);
                range.setStartAfter(tn);
                range.setEndAfter(tn);
                sel.removeAllRanges(); sel.addRange(range);
            }
        }
    } else if (e.key === 'Tab') {
        // Nächstes pp-edit fokussieren
        e.preventDefault();
        const all = Array.from(document.querySelectorAll('.pp-edit'));
        const idx = all.indexOf(el);
        if (idx >= 0) {
            const next = e.shiftKey ? all[idx - 1] : all[idx + 1];
            if (next) next.focus();
        }
    }
});
document.addEventListener('paste', e => {
    const el = e.target;
    if (!el.classList || !el.classList.contains('pp-edit')) return;
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
});

async function ppDoSaveRow(id, field, value, explicitPlanId) {
    ppShowSaving('saving');
    // Markiere die Zelle als „speichert" — pulse-effect bis Antwort da ist
    const cell = document.querySelector(`tr[data-id="${id}"] [data-field="${field}"]`);
    if (cell) { cell.classList.remove('pp-cell-saved', 'pp-cell-error'); cell.classList.add('pp-cell-saving'); }
    try {
        // plan_id-Hierarchie: expliziter Parameter > activeRows-Lookup > _planId-Snapshot.
        // Explizit gewinnt — sonst kann ein verspaeteter debounced Save nach Plan-Wechsel an den falschen Plan gehen.
        let planId = explicitPlanId;
        if (!planId) {
            const row = ppState.activeRows.find(r => r.id === id);
            planId = (row && row._planId) || ppState.activePlanId;
            if (!row && ppState._pendingSavePlanId && ppState._pendingSavePlanId[id]) {
                planId = ppState._pendingSavePlanId[id];
            }
        }
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${planId}/rows/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ [field]: value }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppShowSaving('saved');
        if (cell) {
            cell.classList.remove('pp-cell-saving');
            cell.classList.add('pp-cell-saved');
            cell.removeAttribute('data-error');
            setTimeout(() => cell.classList.remove('pp-cell-saved'), 800);
        }
        if (['ist_hours', 'planned_hours', 'is_done', 'no_ticket'].includes(field)) ppUpdateStatsBar();
    } catch (e) {
        App.showNotification('Zeile speichern fehlgeschlagen: ' + e.message, 'error');
        ppShowSaving('error');
        if (cell) {
            cell.classList.remove('pp-cell-saving');
            cell.classList.add('pp-cell-error');
            cell.setAttribute('data-error', e.message || 'Fehler');
            cell.setAttribute('title', '⚠ ' + (e.message || 'Speichern fehlgeschlagen'));
        }
    }
}

function ppUpdateStatsBar() {
    // Frueher: die Stats-Pillen im Header neu rendern. Jetzt sitzt die Stats-Info
    // im Mittel-Spalten-Widget. Wir tauschen das Widget aus und aktualisieren auch
    // die Tab-Counts (die Item-Zahlen pro Sektion koennen sich geaendert haben).
    const w = document.getElementById('pp-kpi-widget');
    if (w) {
        const tmp = document.createElement('div');
        tmp.innerHTML = ppRenderKpiWidgetHtml();
        const next = tmp.firstElementChild;
        if (next) w.replaceWith(next);
    }
    document.querySelectorAll('#pp-sectionbar .pp-sb-tab').forEach(tab => {
        const onclick = tab.getAttribute('onclick') || '';
        const m = onclick.match(/ppSetActiveSection\('([^']+)'\)/);
        if (!m) return;
        const id = m[1];
        const n = (id === 'all')
            ? ppState.activeRows.filter(r => r.row_type === 'item').length
            : ppSectionItems(id).length;
        const c = tab.querySelector('.pp-sb-tab-count');
        if (c) c.textContent = String(n);
    });
}

/* ===== Row-CRUD ===== */
async function ppAddRow(type) {
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ row_type: type, description: '' }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppDeleteRow(id) {
    if (!confirm('Zeile wirklich löschen?')) return;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppState.activeRows = ppState.activeRows.filter(r => r.id !== id);
        ppRenderEditor();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Plan-Aktionen ===== */
/* ===== KI-Sparring-Panel ===== */
const ppSparState = { planId: null, convId: null, streaming: false };

function ppBuildSparPanel() {
    const el = document.createElement('div');
    el.className = 'pp-spar-panel';
    el.id = 'pp-spar-panel';
    el.innerHTML = `
        <div class="pp-spar-head">
            <span class="material-symbols-rounded">forum</span>
            <span class="pp-spar-title" id="pp-spar-title">KI-Sparring</span>
            <button class="thx-icon-btn" title="Schließen" onclick="ppCloseSparring()"><span class="material-symbols-rounded">close</span></button>
        </div>
        <div class="pp-spar-body" id="pp-spar-body"></div>
        <div class="pp-spar-foot">
            <div class="pp-spar-composer">
                <textarea id="pp-spar-input" rows="2" placeholder="Frag mich, oder schildere den Projektstand…"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();ppSparSend();}"></textarea>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppSparSend()" title="Senden (Enter)"><span class="material-symbols-rounded" style="font-size:16px;">send</span></button>
            </div>
            <div class="pp-spar-actions">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSparEnrich()" title="Plan aufs neue Quartal bringen: Daten, offene Tickets, Standard-Formulierungen"><span class="material-symbols-rounded" style="font-size:14px;">auto_fix_high</span> Mechanisch vorbereiten</button>
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSparMaterialize()" title="Aus dem Gespräch konkrete Plan-Schritte ableiten"><span class="material-symbols-rounded" style="font-size:14px;">playlist_add</span> Vorschläge erzeugen</button>
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSparRule()" title="Eine Vorgabe als Regel für diesen Kunden merken"><span class="material-symbols-rounded" style="font-size:14px;">school</span> Regel merken</button>
            </div>
        </div>`;
    return el;
}

function ppSparAppendMsg(role, content) {
    const body = document.getElementById('pp-spar-body');
    const div = document.createElement('div');
    div.className = 'pp-spar-msg is-' + (role === 'assistant' ? 'assistant' : 'user');
    div.textContent = content || '';
    body.appendChild(div);
    return div;
}
function ppSparScroll() { const b = document.getElementById('pp-spar-body'); if (b) b.scrollTop = b.scrollHeight; }
function ppCloseSparring() { const el = document.getElementById('pp-spar-panel'); if (el) el.style.display = 'none'; document.body.classList.remove('pp-spar-open'); }

async function ppOpenSparring() {
    const p = ppState.activePlan;
    if (!p || !p.id) { App.showNotification('Bitte einen einzelnen Plan öffnen', 'error'); return; }
    let el = document.getElementById('pp-spar-panel');
    if (!el) { el = ppBuildSparPanel(); document.body.appendChild(el); }
    el.style.display = 'flex';
    document.body.classList.add('pp-spar-open');
    ppSparState.planId = p.id;
    document.getElementById('pp-spar-title').textContent = 'KI-Sparring — ' + (p.title || 'Plan');
    const body = document.getElementById('pp-spar-body');
    body.innerHTML = '<div style="text-align:center;color:var(--slate-400);padding:20px;">Lädt…</div>';
    try {
        const j = await (await fetch(`/api/v1/admin/projektplanner/plans/${p.id}/sparring`)).json();
        if (!j.success) throw new Error(j.message);
        ppSparState.convId = j.data.conversation_id;
        body.innerHTML = '';
        (j.data.messages || []).forEach(m => ppSparAppendMsg(m.role, m.content));
        if (!(j.data.messages || []).length) {
            ppSparAppendMsg('assistant', 'Lass uns den Plan gemeinsam schärfen. Was steht bei diesem Kunden gerade an, was ist erledigt, wo bist Du unsicher? Ich ziehe passende Impulse aus anderen Projekten dazu. Wenn wir Schritte vereinbart haben, klick auf „Vorschläge erzeugen".');
        }
        ppSparScroll();
        setTimeout(() => document.getElementById('pp-spar-input')?.focus(), 50);
    } catch (e) { body.innerHTML = '<div style="color:var(--rose-600);padding:14px;">' + ppEscape(e.message) + '</div>'; }
}

async function ppSparSend() {
    const ta = document.getElementById('pp-spar-input');
    const msg = ta.value.trim();
    if (!msg || ppSparState.streaming) return;
    ta.value = '';
    ppSparAppendMsg('user', msg);
    const asstEl = ppSparAppendMsg('assistant', '…');
    ppSparState.streaming = true;
    ppSparScroll();
    try {
        const resp = await fetch(`/api/v1/admin/projektplanner/plans/${ppSparState.planId}/sparring/stream`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ message: msg }),
        });
        const reader = resp.body.getReader();
        const dec = new TextDecoder();
        let buf = '', full = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();
            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                let d; try { d = JSON.parse(line.slice(6)); } catch (_) { continue; }
                if (d.type === 'token') { full += d.content; asstEl.textContent = full; ppSparScroll(); }
                else if (d.type === 'sources' && (d.sources || []).length) {
                    const src = document.createElement('div');
                    src.className = 'pp-spar-sources';
                    src.textContent = 'angeregt durch: ' + d.sources.map(s => s.title).join(' · ');
                    asstEl.insertAdjacentElement('beforebegin', src);
                }
                else if (d.type === 'error') { asstEl.textContent = '⚠ ' + d.message; }
            }
        }
        if (full === '') asstEl.textContent = '(keine Antwort)';
    } catch (e) { asstEl.textContent = '⚠ ' + e.message; }
    finally { ppSparState.streaming = false; ppSparScroll(); }
}

async function ppSparApplyRaw(s) {
    const j = await (await fetch(`/api/v1/admin/projektplanner/plans/${ppSparState.planId}/sparring/apply`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
        body: JSON.stringify({ suggestion: s }),
    })).json();
    if (!j.success) throw new Error(j.message);
}
async function ppSparApply(s, card) {
    try {
        await ppSparApplyRaw(s);
        const btn = card.querySelector('[data-act="apply"]');
        if (btn) { btn.textContent = s.action === 'remove' ? '✓ entfernt' : '✓ übernommen'; btn.disabled = true; }
        card.style.opacity = '0.55';
        ppOpenPlan(ppSparState.planId); // Plan neu laden
        App.showNotification(s.action === 'remove' ? 'Zeile entfernt' : 'In den Plan übernommen', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppSparMaterialize() {
    if (ppSparState.streaming) return;
    const body = document.getElementById('pp-spar-body');
    const loading = document.createElement('div'); loading.className = 'pp-spar-sources'; loading.textContent = 'Leite Vorschläge ab… (Opus 4.7)';
    body.appendChild(loading); ppSparScroll();
    try {
        const j = await (await fetch(`/api/v1/admin/projektplanner/plans/${ppSparState.planId}/sparring/materialize`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        })).json();
        loading.remove();
        if (!j.success) throw new Error(j.message);
        const sugg = j.data.suggestions || [];
        if (!sugg.length) { ppSparAppendMsg('assistant', 'Aus unserem Gespräch ergeben sich noch keine konkreten Schritte — lass uns weiter schärfen.'); ppSparScroll(); return; }
        const wrap = document.createElement('div');
        const head = document.createElement('div'); head.className = 'pp-spar-sources'; head.textContent = 'Vorschläge (' + sugg.length + ') — einzeln oder alle übernehmen:'; wrap.appendChild(head);
        sugg.forEach(s => {
            const isRemove = s.action === 'remove';
            const card = document.createElement('div'); card.className = 'pp-spar-card' + (isRemove ? ' is-remove' : '');
            const meta = [s.planned_hours ? ppFormatNum(s.planned_hours) + ' h' : '', s.lead || '', s.timeframe || '', s.deadline || ''].filter(Boolean).join(' · ');
            card.innerHTML = `<div class="pp-spar-card-sec">${isRemove ? 'Zeile entfernen' : ppEscape(s.section || '—')}</div>
                <div style="margin:3px 0;color:var(--slate-800);">${isRemove ? '<strong>Entfernen:</strong> ' : ''}${ppEscape(s.description || '')}</div>
                ${(!isRemove && meta) ? `<div style="color:var(--slate-500);font-size:11px;">${ppEscape(meta)}</div>` : ''}
                <div style="display:flex;gap:6px;margin-top:7px;">
                    <button class="thx-btn ${isRemove ? 'thx-btn-secondary' : 'thx-btn-primary'} thx-btn-small" data-act="apply"${isRemove ? ' style="color:var(--rose-600);"' : ''}>${isRemove ? 'Entfernen' : 'Übernehmen'}</button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" data-act="drop">Verwerfen</button>
                </div>`;
            card.querySelector('[data-act="apply"]').onclick = () => ppSparApply(s, card);
            card.querySelector('[data-act="drop"]').onclick = () => card.remove();
            wrap.appendChild(card);
        });
        const allBtn = document.createElement('button');
        allBtn.className = 'thx-btn thx-btn-secondary thx-btn-small'; allBtn.style.marginTop = '4px';
        allBtn.textContent = 'Alle übernehmen';
        allBtn.onclick = async () => {
            allBtn.disabled = true;
            let ok = 0;
            for (const s of sugg) { try { await ppSparApplyRaw(s); ok++; } catch (_) {} }
            wrap.querySelectorAll('.pp-spar-card').forEach(c => { c.style.opacity = '0.55'; });
            ppOpenPlan(ppSparState.planId);
            App.showNotification(ok + ' Schritte übernommen', 'success');
        };
        wrap.appendChild(allBtn);
        body.appendChild(wrap); ppSparScroll();
    } catch (e) { loading.remove(); App.showNotification(e.message, 'error'); }
}

async function ppSparRule() {
    const text = prompt('Welche Vorgabe soll die KI bei diesem Kunden künftig beachten? (wird als Regel-Vorschlag gespeichert)');
    if (text === null) return;
    const t = text.trim(); if (!t) return;
    try {
        const j = await (await fetch(`/api/v1/admin/projektplanner/plans/${ppSparState.planId}/sparring/rule`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ text: t }),
        })).json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Als Regel-Vorschlag gespeichert — unter „KI-Regeln" aktivierbar.', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/** Mechanische Vorbereitung im Sparring: Plan aufs neue Quartal bringen (Daten/Deadlines),
 *  offene Asana-Tickets verknüpfen, Formulierungen standardisieren. Nutzt den ai-enrich-Endpunkt. */
async function ppSparEnrich() {
    if (ppSparState.streaming) return;
    if (!confirm('Plan mechanisch aufs neue Quartal bringen?\nZeiträume/Deadlines anpassen, offene Asana-Tickets verknüpfen, Formulierungen an den Kunden-Standard angleichen.\n(Nur bei Entwürfen; geänderte Zeilen werden zum Prüfen markiert.)')) return;
    const prog = ppStartAiProgress();
    try {
        const j = await (await fetch(`/api/v1/admin/projektplanner/plans/${ppSparState.planId}/ai-enrich`, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ briefing: '', link_asana: true }),
        })).json();
        if (!j.success) { prog.fail(); App.showNotification('Vorbereiten fehlgeschlagen: ' + j.message, 'error'); return; }
        const d = j.data || {};
        await prog.finish(`${d.rows_updated || 0} Zeilen angepasst · ${d.rows_linked || 0} Tickets verknüpft`);
        ppOpenPlan(ppSparState.planId);
        ppSparAppendMsg('assistant', `Ich habe den Plan mechanisch vorbereitet: ${d.rows_updated || 0} Zeilen angepasst, ${d.rows_linked || 0} offene Tickets verknüpft, ${d.rows_unclear || 0}× als „zu klären" markiert. Lass uns jetzt gemeinsam durchgehen, was wirklich ansteht und was raus kann.`);
        ppSparScroll();
    } catch (e) { prog.fail(); App.showNotification('Vorbereiten fehlgeschlagen: ' + e.message, 'error'); }
}

/** Fortschritts-Overlay für die (synchrone) KI-Anreicherung. Da der Server-Call keine
 *  Zwischenschritte meldet, füllt sich der Balken zeitbasiert asymptotisch bis ~92 % und
 *  springt bei Abschluss auf 100 %. Rückgabe: { finish(msg)→Promise, fail() }. */
function ppStartAiProgress() {
    const wrap = document.createElement('div');
    wrap.className = 'thx-modal-backdrop';
    wrap.style.display = 'flex';
    wrap.style.zIndex = '10000';
    wrap.innerHTML = `
        <div class="thx-modal" style="width:440px;max-width:92vw;">
            <div class="thx-modal-body" style="padding:26px 26px 22px;text-align:center;">
                <span class="material-symbols-rounded pp-spin" style="font-size:34px;color:var(--thoxan-600);">auto_awesome</span>
                <h3 style="margin:10px 0 4px;font-size:16px;font-weight:700;">Claude reichert den Plan an…</h3>
                <p id="ppaip-status" style="font-size:12px;color:var(--slate-500);margin:0 0 16px;">Offene Asana-Tickets abgleichen, Beschreibungen &amp; Zeiträume anpassen…</p>
                <div style="height:9px;background:var(--slate-100);border-radius:99px;overflow:hidden;">
                    <div id="ppaip-bar" style="height:100%;width:3%;background:var(--thoxan-600);border-radius:99px;transition:width .45s ease;"></div>
                </div>
                <div id="ppaip-pct" style="font-size:11px;color:var(--slate-400);margin-top:7px;font-variant-numeric:tabular-nums;">3 %</div>
            </div>
        </div>`;
    document.body.appendChild(wrap);
    const bar = wrap.querySelector('#ppaip-bar');
    const pct = wrap.querySelector('#ppaip-pct');
    const start = Date.now();
    const est = 45000; // grobe Schätzung ~45 s; Balken nähert sich 92 % und bleibt dort bis fertig
    const timer = setInterval(() => {
        const t = Date.now() - start;
        const p = Math.min(92, Math.max(3, Math.round((1 - Math.exp(-t / est)) * 100)));
        bar.style.width = p + '%';
        pct.textContent = p + ' %';
    }, 400);
    return {
        finish(msg) {
            clearInterval(timer);
            bar.style.width = '100%';
            pct.textContent = '100 %';
            const s = wrap.querySelector('#ppaip-status');
            if (s) s.textContent = msg || 'Fertig';
            return new Promise(res => setTimeout(() => { wrap.remove(); res(); }, 650));
        },
        fail() { clearInterval(timer); wrap.remove(); },
    };
}

async function ppDuplicatePlan(srcArg) {
    // Quelle: explizit uebergebener Plan (Rechtsklick-Menue) oder der aktuell geoeffnete Plan.
    let src = (srcArg == null) ? ppState.activePlan
            : (typeof srcArg === 'object' ? srcArg : ppState.plans.find(p => p.id === srcArg));
    if (!src && ppState.activePlan && ppState.activePlan.id === srcArg) src = ppState.activePlan;
    if (!src || !src.id) return;
    const srcId = src.id;
    // Vorschlag für Folgequartal/-monat: Periode um die alte Länge verschieben
    let suggestTitle = (src.title || '') + ' (Kopie)';
    let suggestFrom = src.period_from || '';
    let suggestTo = src.period_to || '';
    if (suggestFrom && suggestTo) {
        try {
            // Zeitzonensicher über die ISO-Komponenten rechnen (kein Date-Parsing).
            const [fy, fm] = suggestFrom.split('-').map(Number);
            const [ty, tm] = suggestTo.split('-').map(Number);
            // Länge des Quell-Zeitraums in vollen MONATEN (1, 2, 3, 6, …).
            const monthCount = (ty - fy) * 12 + (tm - fm) + 1;
            // Folge-Zeitraum: 1. des Monats NACH dem Quell-Ende bis zum Monatsende — immer volle
            // Monate, egal wie viele Tage. So wird aus 01.04.–30.06. sauber 01.07.–30.09. statt 29.09.
            let nfY = ty, nfM = tm + 1; if (nfM > 12) { nfM = 1; nfY++; }
            let endY = nfY, endM = nfM + monthCount - 1; while (endM > 12) { endM -= 12; endY++; }
            const lastDay = new Date(endY, endM, 0).getDate(); // TZ-sicher: nur Tageszahl des Monats
            suggestFrom = `${nfY}-${String(nfM).padStart(2, '0')}-01`;
            suggestTo = `${endY}-${String(endM).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
            // Titel-Heuristik: Quartals-Suffix Q1/Q2/.../Q4 hochzählen
            const qMatch = (src.title || '').match(/(.*?)(\d{4})-Q([1-4])(.*)$/);
            if (qMatch) {
                let y = parseInt(qMatch[2]), q = parseInt(qMatch[3]) + 1;
                if (q > 4) { q = 1; y++; }
                suggestTitle = qMatch[1] + y + '-Q' + q + qMatch[4];
            }
        } catch (_) {}
    }
    const opts = await ppOpenDuplicateModal({
        title: suggestTitle, from: suggestFrom, to: suggestTo,
        srcTitle: src.title, srcFrom: src.period_from, srcTo: src.period_to,
    });
    if (!opts) return;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${srcId}/duplicate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(opts),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const newId = j.data.id;
        App.showNotification('Plan dupliziert — für KI im Plan „KI-Sparring" öffnen', 'success');
        await ppInit();
        ppOpenPlan(newId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/** Promise-basiertes Duplizieren-Modal. Resolved mit {title, period_from, period_to, shift_dates, reset_ist, reset_done} oder null. */
function ppOpenDuplicateModal(defaults) {
    return new Promise((resolve) => {
        const wrap = document.createElement('div');
        wrap.className = 'thx-modal-backdrop';
        wrap.style.display = 'flex';
        wrap.innerHTML = `
            <div class="thx-modal" style="width:520px;">
                <div class="thx-modal-header"><h3 class="thx-modal-title">Plan duplizieren / Wiederkehrend</h3>
                    <button class="thx-modal-close" data-act="cancel">&times;</button></div>
                <div class="thx-modal-body pp-modal-body">
                    <div class="pp-field"><label>Titel</label>
                        <input type="text" id="ppd-title" value="${ppEscape(defaults.title)}"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="pp-field"><label>Von</label><input type="date" id="ppd-from" value="${defaults.from}"></div>
                        <div class="pp-field"><label>Bis</label><input type="date" id="ppd-to"   value="${defaults.to}"></div>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;margin:8px 0;cursor:pointer;">
                        <input type="checkbox" id="ppd-shift" checked> Deadlines + Datums-Bereiche proportional verschieben
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;margin:4px 0;cursor:pointer;">
                        <input type="checkbox" id="ppd-reset-ist" checked> Ist-Stunden auf 0 zurücksetzen
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;margin:4px 0;cursor:pointer;">
                        <input type="checkbox" id="ppd-reset-done" checked> Erledigt-Häkchen zurücksetzen
                    </label>
                    <p style="font-size:11px;color:var(--slate-500);margin-top:10px;padding-top:10px;border-top:1px solid var(--slate-200);">
                        Quelle: <strong>${ppEscape(defaults.srcTitle || '?')}</strong> · ${defaults.srcFrom || '?'} – ${defaults.srcTo || '?'} · reine Kopie.<br>
                        Für KI-Unterstützung danach im Plan <strong>„KI-Sparring"</strong> öffnen — dort bringst Du den Plan mechanisch aufs neue Quartal und schärfst ihn gemeinsam.
                    </p>
                </div>
                <div class="thx-modal-footer">
                    <button class="thx-btn thx-btn-secondary" data-act="cancel">Abbrechen</button>
                    <button class="thx-btn thx-btn-primary" data-act="ok">Duplizieren</button>
                </div>
            </div>`;
        document.body.appendChild(wrap);
        const close = (val) => { document.body.removeChild(wrap); resolve(val); };
        wrap.addEventListener('click', e => {
            const a = e.target.closest('[data-act]')?.dataset.act;
            if (a === 'cancel' || e.target === wrap) close(null);
            else if (a === 'ok') {
                close({
                    title: wrap.querySelector('#ppd-title').value.trim() || defaults.title,
                    period_from: wrap.querySelector('#ppd-from').value || null,
                    period_to:   wrap.querySelector('#ppd-to').value   || null,
                    shift_dates: wrap.querySelector('#ppd-shift').checked,
                    reset_ist:   wrap.querySelector('#ppd-reset-ist').checked,
                    reset_done:  wrap.querySelector('#ppd-reset-done').checked,
                });
            }
        });
    });
}

/* ===== Feedback-Viewer (Admin) ===== */
function ppGetUnreadFeedbackCount() {
    if (!ppState.activePlan || !ppState.activePlan.feedback) return 0;
    return ppState.activePlan.feedback.filter(f => !f.read_at).length;
}

async function ppOpenFeedbackModal() {
    if (!ppState.activePlanId) return;
    ppOpenModal('pp-feedback-modal');
    await ppLoadFeedbackModal();
}

async function ppLoadFeedbackModal() {
    const body = document.getElementById('pp-feedback-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/feedback');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppRenderFeedbackModal(j.data.feedback);
    } catch (e) {
        body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
    }
}

function ppRenderFeedbackModal(items) {
    const body = document.getElementById('pp-feedback-body');
    if (!items.length) {
        body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Noch kein Feedback.</div>';
        return;
    }
    const unread = items.filter(i => !i.read_at).length;
    const grouped = {};
    items.forEach(i => {
        const k = i.row_id || 0;
        if (!grouped[k]) grouped[k] = { desc: i.row_description, items: [] };
        grouped[k].items.push(i);
    });

    let html = '';
    if (unread > 0) {
        html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding:8px 12px;background:var(--amber-50);border-radius:6px;font-size:var(--d-fs-xs);">
            <span><strong>${unread}</strong> ungelesen</span>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppMarkAllFeedbackRead()">Alle als gelesen markieren</button>
        </div>`;
    }
    Object.entries(grouped).forEach(([rowId, g]) => {
        const desc = g.desc || (rowId == 0 ? 'Plan-allgemein' : 'Gelöschte Zeile');
        html += `<div style="margin-bottom:18px;">
            <div style="font-weight:600;color:var(--slate-700);font-size:var(--d-fs-sm);margin-bottom:6px;border-bottom:1px solid var(--slate-200);padding-bottom:4px;">
                ${ppEscape(desc)}
            </div>`;
        g.items.forEach(i => {
            const date = new Date(i.created_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
            const icon = i.feedback_type === 'like' ? '👍' : (i.feedback_type === 'dislike' ? '👎' : '💬');
            const isRead = !!i.read_at;
            html += `<div style="padding:8px 12px;background:var(--slate-50);border-radius:6px;margin-bottom:6px;border-left:3px solid ${isRead ? 'var(--emerald-500)' : 'var(--amber-500)'};">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;font-size:var(--d-fs-xs);">
                    <div style="flex:1;">
                        <span style="margin-right:6px;">${icon}</span>
                        <strong>${ppEscape(i.author_name || 'Anonym')}</strong>
                        <span style="color:var(--slate-400);margin-left:6px;">${date}</span>
                        ${i.message ? `<div style="margin-top:4px;color:var(--slate-700);">${ppEscape(i.message).replace(/\n/g, '<br>')}</div>` : ''}
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppToggleFeedbackRead(${i.id}, ${isRead ? 'true' : 'false'})" style="padding:2px 8px;">
                            ${isRead ? '↩' : '✓'}
                        </button>
                        <button class="thx-btn thx-btn-secondary thx-btn-small thx-btn-danger" onclick="ppDeleteFeedback(${i.id})" style="padding:2px 8px;">×</button>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
    });
    body.innerHTML = html;
}

async function ppMarkAllFeedbackRead() {
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/feedback/read-all`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        // Cache aktualisieren
        if (ppState.activePlan.feedback) {
            ppState.activePlan.feedback.forEach(f => { if (!f.read_at) f.read_at = new Date().toISOString(); });
        }
        await ppLoadFeedbackModal();
        ppUpdateFeedbackBadge();
        ppRefreshPlanInList();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppToggleFeedbackRead(fbId, isRead) {
    const action = isRead ? 'unread' : 'read';
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/feedback/${fbId}/${action}`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        // Cache aktualisieren
        if (ppState.activePlan.feedback) {
            const fb = ppState.activePlan.feedback.find(f => f.id === fbId);
            if (fb) fb.read_at = isRead ? null : new Date().toISOString();
        }
        await ppLoadFeedbackModal();
        ppUpdateFeedbackBadge();
        ppRefreshPlanInList();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppDeleteFeedback(fbId) {
    if (!confirm('Feedback löschen?')) return;
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/feedback/${fbId}`, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        if (ppState.activePlan.feedback) {
            ppState.activePlan.feedback = ppState.activePlan.feedback.filter(f => f.id !== fbId);
        }
        await ppLoadFeedbackModal();
        ppUpdateFeedbackBadge();
        ppRefreshPlanInList();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppUpdateFeedbackBadge() {
    // Header re-rendern (Badge-Update)
    if (ppState.activePlan) ppRenderEditor();
}

function ppRefreshPlanInList() {
    // unread_feedback im Sidebar-Plan-Eintrag aktualisieren
    if (!ppState.activePlanId) return;
    const idx = ppState.plans.findIndex(p => p.id === ppState.activePlanId);
    if (idx >= 0 && ppState.activePlan.feedback) {
        ppState.plans[idx].unread_feedback = ppState.activePlan.feedback.filter(f => !f.read_at).length;
        ppRenderPlanList();
    }
}

/* ===== Sharing-Modal: Public-Link + Team-Member-Permissions ===== */
async function ppSharePlan() {
    // Multi-Plan-Modus → gemeinsamen Übersichts-Sharelink erzeugen statt Per-Plan-Share
    if (ppState.multiPlans && ppState.multiPlans.length > 0 && !ppState.activePlanId) {
        return ppShareMultiPlan();
    }
    if (!ppState.activePlanId) return;
    ppOpenModal('pp-share-modal');
    await ppLoadShareModal();
}

async function ppShareMultiPlan() {
    if (!ppState.multiPlans || ppState.multiPlans.length === 0) return;
    const planIds = ppState.multiPlans.map(p => p.id);
    const ef = ppState.editorFilters || {};
    const statusFilter = (Array.isArray(ef.status) && ef.status.length === 1 && ef.status[0] !== 'all') ? ef.status[0] : 'all';
    const filters = {
        status:       statusFilter,
        lead:         ef.lead || '',
        responsible:  ef.responsible || '',
        search:       ef.search || '',
    };
    let vorschlag = planIds.length + ' Pläne';
    if (filters.responsible) vorschlag += ' · ' + filters.responsible;
    if (filters.status && filters.status !== 'all') vorschlag += ' · ' + filters.status;

    // Konfig-Modal vor dem Erstellen — Optionen für Passwort, Ablauf, Snapshot
    ppOpenModal('pp-share-modal');
    const body = document.getElementById('pp-share-body');
    const filterPills = Object.entries(filters)
        .filter(([k, v]) => v && v !== 'all')
        .map(([k, v]) => `<span style="background:var(--amber-100);color:var(--amber-900);padding:2px 8px;border-radius:10px;font-size:0.72rem;font-weight:600;">${ppEscape(k)}: ${ppEscape(v)}</span>`)
        .join(' ');
    body.innerHTML = `
        <h4 style="margin:0 0 14px;font-size:var(--d-fs-base);color:var(--slate-700);">Übersichts-Sharelink erstellen</h4>
        <div style="font-size:0.82rem;color:var(--slate-700);margin-bottom:14px;">
            <strong>${planIds.length}</strong> Pläne werden geteilt
            ${filterPills ? `<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;"><span style="font-size:0.72rem;color:var(--slate-500);">Filter:</span>${filterPills}</div>` : ''}
        </div>
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">Titel</label>
            <input type="text" id="pp-mshare-title" value="${ppEscape(vorschlag)}" style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">Passwort (optional)</label>
                <input type="password" id="pp-mshare-pw" placeholder="leer = kein Schutz" autocomplete="new-password" style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">Ablauf (optional)</label>
                <input type="date" id="pp-mshare-exp" style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
            </div>
        </div>
        <div style="margin-bottom:14px;background:rgba(168, 85, 247, 0.06);border:1px solid rgba(168, 85, 247, 0.2);padding:10px 12px;border-radius:6px;">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                <input type="checkbox" id="pp-mshare-snap" style="margin-top:2px;">
                <div>
                    <div style="font-weight:600;color:#7e22ce;font-size:0.85rem;">📸 Snapshot-Modus</div>
                    <div style="font-size:0.72rem;color:var(--slate-600);">Friert den jetzigen Stand ein. Spätere Änderungen sind im Link nicht sichtbar.</div>
                </div>
            </label>
        </div>
        <div style="display:flex;justify-content:space-between;gap:10px;">
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppOpenMyMultiShares()" type="button">
                Meine Übersichts-Links
            </button>
            <button class="thx-btn thx-btn-primary" onclick="ppCreateMultiShareSubmit(${JSON.stringify(planIds).replace(/"/g,'&quot;')}, ${JSON.stringify(filters).replace(/"/g,'&quot;')})" type="button">
                Link erstellen
            </button>
        </div>
    `;
}

async function ppCreateMultiShareSubmit(planIds, filters) {
    const title = document.getElementById('pp-mshare-title').value;
    const password = document.getElementById('pp-mshare-pw').value;
    const expires_at = document.getElementById('pp-mshare-exp').value;
    const is_snapshot = document.getElementById('pp-mshare-snap').checked;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/multi-share', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_ids: planIds, filters, title: title.trim() || null, password, expires_at, is_snapshot }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const url = window.location.origin + j.data.url;
        const body = document.getElementById('pp-share-body');
        body.innerHTML = `
            <h4 style="margin:0 0 10px;font-size:var(--d-fs-base);color:var(--slate-700);">✓ Link erstellt</h4>
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:12px;">
                <input type="text" value="${ppEscape(url)}" readonly id="pp-share-url"
                       style="flex:1;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-family:ui-monospace,monospace;font-size:var(--d-fs-xs);">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppCopyShareUrl()">Kopieren</button>
                <a href="${ppEscape(url)}" target="_blank" class="thx-btn thx-btn-secondary thx-btn-small">Öffnen ↗</a>
            </div>
            <div style="text-align:right;">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppOpenMyMultiShares()">Meine Übersichts-Links</button>
            </div>
        `;
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppOpenMyMultiShares() {
    ppOpenModal('pp-share-modal');
    const body = document.getElementById('pp-share-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/multi-share');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const shares = j.data.shares || [];
        if (shares.length === 0) {
            body.innerHTML = `<div style="text-align:center;padding:30px;color:var(--slate-500);">
                Noch keine Übersichts-Links erstellt.
                ${ppState.multiPlans && ppState.multiPlans.length > 0 ? `
                <div style="margin-top:14px;"><button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppShareMultiPlan()">+ Neuen Link erstellen</button></div>
                ` : ''}
            </div>`;
            return;
        }
        const fmtDate = (s) => { if (!s) return '—'; const d = new Date(s.replace(' ','T')); return d.toLocaleDateString('de-DE', {day:'2-digit',month:'short',year:'numeric'}); };
        const rows = shares.map(s => {
            const url = window.location.origin + s.url;
            const filterStr = Object.entries(s.filters || {}).filter(([k,v]) => v && v !== 'all').map(([k,v]) => k + ':' + v).join(' · ') || '—';
            const badges = [
                s.has_password ? '<span style="background:var(--slate-100);color:var(--slate-700);padding:1px 7px;border-radius:8px;font-size:0.7rem;font-weight:600;" title="Passwort-geschützt">🔒</span>' : '',
                s.is_snapshot  ? '<span style="background:rgba(168, 85, 247, 0.12);color:#7e22ce;padding:1px 7px;border-radius:8px;font-size:0.7rem;font-weight:600;" title="Snapshot">📸</span>' : '',
                s.expired      ? '<span style="background:var(--rose-50);color:var(--rose-700);padding:1px 7px;border-radius:8px;font-size:0.7rem;font-weight:600;">abgelaufen</span>' : (s.expires_at ? `<span style="background:var(--amber-50);color:var(--amber-800);padding:1px 7px;border-radius:8px;font-size:0.7rem;" title="Läuft ab am ${fmtDate(s.expires_at)}">⏳ ${fmtDate(s.expires_at)}</span>` : ''),
            ].filter(Boolean).join(' ');
            return `<tr style="border-bottom:1px solid var(--slate-100);">
                <td style="padding:8px 10px;vertical-align:top;">
                    <div style="font-weight:600;color:var(--slate-800);">${ppEscape(s.title || '—')}</div>
                    <div style="font-size:0.72rem;color:var(--slate-500);margin-top:2px;">${s.plan_count} Pläne · ${ppEscape(filterStr)}</div>
                    <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap;">${badges}</div>
                </td>
                <td style="padding:8px 10px;vertical-align:top;font-size:0.72rem;color:var(--slate-500);white-space:nowrap;">
                    ${fmtDate(s.created_at)}<br>
                    <span style="color:${s.access_count > 0 ? 'var(--emerald-700)' : 'var(--slate-400)'};font-weight:${s.access_count > 0 ? '600' : '400'};">
                        ${s.access_count}× geöffnet${s.accessed_at ? '<br>zuletzt ' + fmtDate(s.accessed_at) : ''}
                    </span>
                </td>
                <td style="padding:8px 6px;vertical-align:top;text-align:right;white-space:nowrap;">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="navigator.clipboard.writeText('${ppEscape(url)}');App.showNotification('Link kopiert.','success');" title="Link kopieren"><span class="material-symbols-rounded" style="font-size:14px;">content_copy</span></button>
                    <a href="${ppEscape(url)}" target="_blank" class="thx-btn thx-btn-secondary thx-btn-small" title="Öffnen"><span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span></a>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppEditMultiShare(${s.id})" title="Bearbeiten"><span class="material-symbols-rounded" style="font-size:14px;">edit</span></button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppDeleteMultiShare(${s.id})" style="color:var(--rose-700);" title="Löschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button>
                </td>
            </tr>`;
        }).join('');
        body.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="margin:0;font-size:var(--d-fs-base);color:var(--slate-700);">Meine Übersichts-Links</h4>
                ${ppState.multiPlans && ppState.multiPlans.length > 0 ? `<button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppShareMultiPlan()">+ Neuer Link</button>` : ''}
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--slate-200);">
                        <th style="text-align:left;padding:8px 10px;color:var(--slate-500);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;">Übersicht</th>
                        <th style="text-align:left;padding:8px 10px;color:var(--slate-500);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;">Erstellt · Zugriffe</th>
                        <th style="text-align:right;padding:8px 6px;color:var(--slate-500);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } catch (e) { body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>'; }
}
window.ppOpenMyMultiShares = ppOpenMyMultiShares;

async function ppEditMultiShare(shareId) {
    // Aktuelle Share-Daten aus Liste holen (im Modal eh schon geladen)
    let share = null;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/multi-share');
        const j = await r.json();
        if (j.success) share = (j.data.shares || []).find(s => s.id === shareId);
    } catch (e) {}
    if (!share) { App.showNotification('Share-Daten nicht ladbar.', 'error'); return; }
    const body = document.getElementById('pp-share-body');
    const expDate = share.expires_at ? share.expires_at.split(' ')[0] : '';
    body.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppOpenMyMultiShares()" type="button">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_back</span>
                Zurück
            </button>
            <h4 style="margin:0;font-size:var(--d-fs-base);color:var(--slate-700);">Übersichts-Link bearbeiten</h4>
        </div>
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">Titel</label>
            <input type="text" id="pp-mshare-edit-title" value="${ppEscape(share.title || '')}"
                   placeholder="z.B. „BJU-Aufgaben Q2"
                   style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">
                    Passwort
                    ${share.has_password ? '<span style="color:var(--emerald-700);font-weight:400;font-size:0.7rem;">🔒 aktiv</span>' : ''}
                </label>
                <input type="password" id="pp-mshare-edit-pw" autocomplete="new-password"
                       placeholder="${share.has_password ? 'Neues Passwort eingeben oder leer lassen' : 'leer = kein Schutz'}"
                       style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
                ${share.has_password ? `<label style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:0.72rem;color:var(--rose-700);cursor:pointer;">
                    <input type="checkbox" id="pp-mshare-edit-pw-clear"> Passwort entfernen
                </label>` : ''}
            </div>
            <div>
                <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--slate-700);margin-bottom:4px;">Ablauf</label>
                <input type="date" id="pp-mshare-edit-exp" value="${ppEscape(expDate)}"
                       style="width:100%;padding:8px 10px;border:1px solid var(--slate-200);border-radius:6px;">
                ${share.expires_at ? `<label style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:0.72rem;color:var(--rose-700);cursor:pointer;">
                    <input type="checkbox" id="pp-mshare-edit-exp-clear"> Ablauf entfernen
                </label>` : ''}
            </div>
        </div>
        <div style="margin-bottom:14px;background:rgba(168, 85, 247, 0.06);border:1px solid rgba(168, 85, 247, 0.2);padding:10px 12px;border-radius:6px;">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                <input type="checkbox" id="pp-mshare-edit-snap" ${share.is_snapshot ? 'checked' : ''} style="margin-top:2px;">
                <div>
                    <div style="font-weight:600;color:#7e22ce;font-size:0.85rem;">📸 Snapshot-Modus</div>
                    <div style="font-size:0.72rem;color:var(--slate-600);">
                        ${share.is_snapshot
                            ? 'Aktuell aktiv. Erneutes Speichern bei aktivem Snapshot friert den jetzigen Stand neu ein.'
                            : 'Friert den jetzigen Stand ein. Spätere Änderungen sind im Link unsichtbar.'}
                    </div>
                </div>
            </label>
        </div>
        <div style="display:flex;justify-content:space-between;gap:10px;border-top:1px solid var(--slate-100);padding-top:14px;">
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppDeleteMultiShare(${shareId})" type="button" style="color:var(--rose-700);">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                Link löschen
            </button>
            <button class="thx-btn thx-btn-primary" onclick="ppSaveEditMultiShare(${shareId})" type="button">Speichern</button>
        </div>
    `;
}

async function ppSaveEditMultiShare(shareId) {
    const title = document.getElementById('pp-mshare-edit-title').value;
    const pwField = document.getElementById('pp-mshare-edit-pw');
    const pwClear = document.getElementById('pp-mshare-edit-pw-clear');
    const expField = document.getElementById('pp-mshare-edit-exp');
    const expClear = document.getElementById('pp-mshare-edit-exp-clear');
    const snap = document.getElementById('pp-mshare-edit-snap').checked;
    const body = { title, is_snapshot: snap };
    // Passwort: nur senden, wenn neu eingegeben ODER explizit gelöscht
    if (pwClear && pwClear.checked)      body.password = '';
    else if (pwField.value.trim() !== '') body.password = pwField.value;
    // Ablauf: nur senden, wenn neu eingegeben ODER explizit gelöscht
    if (expClear && expClear.checked)    body.expires_at = '';
    else if (expField.value)             body.expires_at = expField.value;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/multi-share/' + shareId, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify(body),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Gespeichert.', 'success');
        ppOpenMyMultiShares();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppDeleteMultiShare(shareId) {
    if (!confirm('Diesen Sharelink wirklich löschen? Wer den Link hat, kann ihn dann nicht mehr öffnen.')) return;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/multi-share/' + shareId, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Link gelöscht.', 'success');
        ppOpenMyMultiShares();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppLoadShareModal() {
    const body = document.getElementById('pp-share-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const [sharesRes, usersRes] = await Promise.all([
            fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/shares').then(r => r.json()),
            fetch('/api/v1/admin/projektplanner/users-for-share?plan_id=' + ppState.activePlanId).then(r => r.json()),
        ]);
        if (!sharesRes.success) throw new Error(sharesRes.message);
        ppRenderShareModal(sharesRes.data.shares, usersRes.success ? (usersRes.data.users || []) : []);
    } catch (e) {
        body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
    }
}

function ppRenderShareModal(shares, availableUsers) {
    const p = ppState.activePlan;
    const publicUrl = p.share_hash ? (window.location.origin + '/projektplan/' + p.share_hash) : null;
    const body = document.getElementById('pp-share-body');
    const userOpts = (availableUsers || []).map(u =>
        `<option value="${u.id}">${ppEscape(u.name)} · ${ppEscape(u.email)}</option>`).join('');

    body.innerHTML = `
        <h4 style="margin:0 0 10px;font-size:var(--d-fs-base);color:var(--slate-700);">Öffentlicher Link</h4>
        ${publicUrl ? `
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px;">
                <input type="text" value="${ppEscape(publicUrl)}" readonly id="pp-share-url"
                       style="flex:1;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-family:ui-monospace,monospace;font-size:var(--d-fs-xs);">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppCopyShareUrl()">Kopieren</button>
                <button class="thx-btn thx-btn-secondary thx-btn-small thx-btn-danger" onclick="ppRevokeShareHash()">Link aufheben</button>
            </div>
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px;">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-600);min-width:80px;">Passwort:</span>
                <input type="text" id="pp-share-pw-input" placeholder="${p.share_password ? '••••• (gesetzt) — neues Passwort eingeben oder leer lassen' : 'Optional — Sharelink mit Passwort schützen'}"
                       style="flex:1;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-size:var(--d-fs-sm);">
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppSetSharePassword()">Setzen</button>
                ${p.share_password ? `<button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSetSharePassword(true)" title="Passwort entfernen">×</button>` : ''}
            </div>
            <div style="font-size:11px;color:var(--slate-500);margin-bottom:18px;">
                Jeder mit dem Link kann den Plan ansehen${p.share_password ? ' <strong>(Passwort erforderlich)</strong>' : ''} und Feedback hinterlassen.
            </div>
        ` : `
            <button class="thx-btn thx-btn-secondary" onclick="ppGenerateShareHash()">
                <span class="material-symbols-rounded" style="font-size:16px;">link</span>
                Öffentlichen Link erzeugen
            </button>
            <div style="font-size:11px;color:var(--slate-500);margin:6px 0 18px;">
                Erzeugt einen Sharelink für Kunden ohne Account. Optional mit Passwort schützen.
            </div>
        `}

        <h4 style="margin:18px 0 10px;font-size:var(--d-fs-base);color:var(--slate-700);">Team-Mitgliedern Zugriff geben</h4>
        <div style="margin-bottom:12px;">
            ${shares.length === 0 ? '<div style="font-size:var(--d-fs-xs);color:var(--slate-400);padding:8px 0;">Noch keine Freigaben.</div>' : `
                <table style="width:100%;font-size:var(--d-fs-sm);">
                    <thead><tr style="color:var(--slate-500);font-size:11px;text-transform:uppercase;border-bottom:1px solid var(--slate-200);">
                        <th style="text-align:left;padding:6px 0;">Person</th>
                        <th style="text-align:left;padding:6px 0;width:140px;">Zugriff</th>
                        <th style="width:30px;"></th>
                    </tr></thead>
                    <tbody>
                        ${shares.map(s => `
                            <tr style="border-bottom:1px solid var(--slate-100);">
                                <td style="padding:6px 0;">${ppEscape(s.user_name)} <span style="color:var(--slate-400);font-size:11px;">· ${ppEscape(s.user_email)}</span></td>
                                <td><select class="pp-fb-select" onchange="ppUpdateShare(${s.user_id}, this.value)">
                                    <option value="read"  ${s.permission === 'read'  ? 'selected' : ''}>Nur lesen</option>
                                    <option value="edit"  ${s.permission === 'edit'  ? 'selected' : ''}>Status/Ist/Notiz</option>
                                    <option value="write" ${s.permission === 'write' ? 'selected' : ''}>Vollzugriff</option>
                                </select></td>
                                <td><button class="pp-action-delete" onclick="ppRemoveShare(${s.user_id})" title="Freigabe entfernen" style="background:none;border:none;color:var(--slate-400);cursor:pointer;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">delete</span>
                                </button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `}
        </div>

        ${availableUsers.length > 0 ? `
            <div style="display:flex;gap:6px;align-items:center;margin-top:8px;">
                <select id="pp-share-user" style="flex:1;padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-size:var(--d-fs-sm);">
                    <option value="">— User wählen —</option>
                    ${userOpts}
                </select>
                <select id="pp-share-perm" style="padding:7px 10px;border:1px solid var(--slate-200);border-radius:6px;font-size:var(--d-fs-sm);">
                    <option value="read">Nur lesen</option>
                    <option value="edit">Status/Ist/Notiz</option>
                    <option value="write">Vollzugriff</option>
                </select>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppAddShare()">Hinzufügen</button>
            </div>
        ` : (shares.length > 0 ? '<div style="font-size:11px;color:var(--slate-400);">Alle aktiven User sind bereits freigegeben.</div>' : '')}

        <div style="font-size:11px;color:var(--slate-500);margin-top:18px;line-height:1.5;">
            <strong>Nur lesen:</strong> sieht alles, kann nichts ändern.<br>
            <strong>Status/Ist/Notiz:</strong> kann Erledigt-Häkchen, Ist-Stunden, Aufwand und Bemerkungen bearbeiten.<br>
            <strong>Vollzugriff:</strong> kann alles bearbeiten außer dem Plan selbst löschen oder die Freigabe ändern.
        </div>
    `;
}

async function ppGenerateShareHash() {
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/share`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppState.activePlan.share_hash = j.data.share_hash;
        await ppLoadShareModal();
        ppRenderEditor(); // Topbar aktualisieren: „Teilen" wird zu „Ansehen" + „Teilen"
        App.showNotification('Sharelink erzeugt', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppRevokeShareHash() {
    if (!confirm('Öffentlichen Link wirklich aufheben? Der bisherige Link funktioniert dann nicht mehr.')) return;
    try {
        await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ share_hash: null }),
        });
        ppState.activePlan.share_hash = null;
        await ppLoadShareModal();
        ppRenderEditor(); // Topbar aktualisieren: zurueck zu einem „Teilen"-Button
        App.showNotification('Link aufgehoben', 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppSetSharePassword(clear) {
    const pw = clear ? '' : document.getElementById('pp-share-pw-input').value.trim();
    if (!clear && !pw) { App.showNotification('Passwort eingeben oder × zum Entfernen', 'error'); return; }
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/share-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ password: pw }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppState.activePlan.share_password = pw ? 'set' : null;
        await ppLoadShareModal();
        App.showNotification(j.message || (pw ? 'Passwort gesetzt' : 'Passwort entfernt'), 'success');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppCopyShareUrl() {
    const inp = document.getElementById('pp-share-url');
    if (!inp) return;
    inp.select(); document.execCommand('copy');
    App.showNotification('Kopiert', 'success');
}

async function ppAddShare() {
    const userId = document.getElementById('pp-share-user').value;
    if (!userId) { App.showNotification('User wählen', 'error'); return; }
    const perm = document.getElementById('pp-share-perm').value;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/shares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ user_id: parseInt(userId), permission: perm }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        await ppLoadShareModal();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppUpdateShare(userId, perm) {
    try {
        await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/shares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ user_id: userId, permission: perm }),
        });
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppRemoveShare(userId) {
    if (!confirm('Freigabe entfernen?')) return;
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/shares/${userId}`, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        await ppLoadShareModal();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppRestorePlan() {
    if (!confirm('Plan aus dem Archiv wiederherstellen?')) return;
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/restore`, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        App.showNotification('Plan wiederhergestellt', 'success');
        ppSetFilter('aktiv');
        ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppDeletePlan() {
    if (!confirm('Plan wirklich löschen? Kann später wiederhergestellt werden.')) return;
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}`, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Plan gelöscht', 'success');
        ppState.activePlanId = null; ppState.activePlan = null;
        document.getElementById('pp-editor').style.display = 'none';
        document.getElementById('pp-empty').style.display = 'flex';
        ppInit();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Drag & Drop ===== */
function ppAttachDragHandlers() {
    const tbody = document.getElementById('pp-tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr[draggable]').forEach(tr => {
        tr.addEventListener('dragstart', () => {
            ppState.dragRowId = parseInt(tr.dataset.id);
            tr.classList.add('is-dragging');
        });
        tr.addEventListener('dragend', () => {
            tr.classList.remove('is-dragging');
            tbody.querySelectorAll('.is-drag-above, .is-drag-below').forEach(el =>
                el.classList.remove('is-drag-above', 'is-drag-below'));
        });
        tr.addEventListener('dragover', e => {
            e.preventDefault();
            const rect = tr.getBoundingClientRect();
            const above = e.clientY < rect.top + rect.height / 2;
            tbody.querySelectorAll('.is-drag-above, .is-drag-below').forEach(el =>
                el.classList.remove('is-drag-above', 'is-drag-below'));
            tr.classList.add(above ? 'is-drag-above' : 'is-drag-below');
        });
        tr.addEventListener('drop', e => {
            e.preventDefault();
            const targetId = parseInt(tr.dataset.id);
            if (targetId === ppState.dragRowId) return;
            const rect = tr.getBoundingClientRect();
            const above = e.clientY < rect.top + rect.height / 2;
            const dragRow = ppState.activeRows.find(r => r.id === ppState.dragRowId);
            const targetRow = ppState.activeRows.find(r => r.id === targetId);
            // Cross-Plan-Detektion im Multi-Plan-Modus
            if (dragRow && targetRow && dragRow._planId && targetRow._planId && dragRow._planId !== targetRow._planId) {
                ppReorderInState(ppState.dragRowId, targetId, above);
                ppCrossPlanMove(ppState.dragRowId, targetRow._planId, 0);
                return;
            }
            ppReorderInState(ppState.dragRowId, targetId, above);
            ppPersistReorder();
        });
    });
}

function ppReorderInState(dragId, targetId, above) {
    const dragIdx = ppState.activeRows.findIndex(r => r.id === dragId);
    if (dragIdx < 0) return;
    const dragRow = ppState.activeRows.splice(dragIdx, 1)[0];
    let targetIdx = ppState.activeRows.findIndex(r => r.id === targetId);
    if (targetIdx < 0) return;
    if (!above) targetIdx++;
    ppState.activeRows.splice(targetIdx, 0, dragRow);
    ppRenderEditor();
}

async function ppPersistReorder() {
    if (ppState.activePlanId) {
        const order = ppState.activeRows.filter(r => typeof r.id === 'number').map(r => r.id);
        try {
            await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId}/rows/reorder`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({ order }),
            });
            ppShowSaving('saved');
        } catch (e) { App.showNotification('Reorder fehlgeschlagen', 'error'); }
    } else {
        // Multi-Plan: nur innerhalb desselben Plans reorder, sonst Move
        // Pro Plan Order zusammenstellen und Reorder absetzen
        const byPlan = {};
        ppState.activeRows.forEach(r => {
            if (typeof r.id !== 'number') return;
            const pid = r._planId;
            if (!byPlan[pid]) byPlan[pid] = [];
            byPlan[pid].push(r.id);
        });
        for (const [pid, order] of Object.entries(byPlan)) {
            try {
                await fetch(`/api/v1/admin/projektplanner/plans/${pid}/rows/reorder`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                    body: JSON.stringify({ order }),
                });
            } catch (_) {}
        }
        ppShowSaving('saved');
    }
}

async function ppCrossPlanMove(dragRowId, targetPlanId, targetIdx) {
    // Move-Endpoint, dann lokal updaten
    try {
        await fetch(`/api/v1/admin/projektplanner/plans/${ppState.activePlanId || '0'}/rows/${dragRowId}/move`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ target_plan_id: targetPlanId, position: targetIdx }),
        });
        const row = ppState.activeRows.find(r => r.id === dragRowId);
        if (row) row._planId = targetPlanId;
        ppShowSaving('saved');
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Budget-Modal ===== */
const ppBudgetState = { customerId: null, year: null };

async function ppOpenBudget() {
    const p = ppState.activePlan;
    if (!p || !p.customer_id) { App.showNotification('Plan hat keinen Kunden zugeordnet', 'error'); return; }
    // Einzelprojekt → eigene Bilanz mit Festbudget (offer_ts) statt Kunden-Quartals-Tabelle
    if ((p.plan_typ || '') === 'einzelprojekt' || p.plan_status === 'einzelprojekt') {
        ppOpenModal('pp-budget-modal');
        await ppLoadAbrechnungEinzel(p.id);
        return;
    }
    ppBudgetState.customerId = p.customer_id;
    ppBudgetState.year = parseInt((p.period_from || '').slice(0, 4)) || new Date().getFullYear();
    ppOpenModal('pp-budget-modal');
    await ppLoadBudget();
}

async function ppLoadAbrechnungEinzel(planId) {
    const body = document.getElementById('pp-budget-body');
    body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId + '/abrechnung-einzel');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppRenderAbrechnungEinzel(j.data);
    } catch (e) {
        body.innerHTML = '<div style="color:var(--rose-600);padding:20px;">' + ppEscape(e.message) + '</div>';
    }
}

function ppRenderAbrechnungEinzel(d) {
    document.getElementById('pp-budget-title').textContent =
        'Abrechnung (Einzelprojekt) — ' + (d.plan_title || '') + (d.customer_name ? ' · ' + d.customer_name : '');
    const fmt = (v) => v === null || v === undefined ? '—' : ppFormatNum(v);
    const sollTs = d.soll_ts || 0;
    const istTs = d.ist_ts || 0;
    const restTs = d.restbudget_ts;
    const overrun = (sollTs > 0 && istTs > sollTs);
    const statusBadge = d.abgerechnet_ts !== null
        ? `<span style="background:var(--emerald-50);color:var(--emerald-700);padding:3px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">✓ Abgerechnet ${ppFormatNum(d.abgerechnet_ts)} TS${d.abgerechnet_am ? ' · ' + d.abgerechnet_am.split('-').reverse().join('.') : ''}</span>`
        : `<span style="background:var(--amber-50);color:var(--amber-800);padding:3px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">Noch nicht abgerechnet</span>`;

    document.getElementById('pp-budget-body').innerHTML = `
        <div style="max-width:720px;margin:0 auto;">
            <div style="margin-bottom:18px;">${statusBadge}</div>

            <!-- KPI-Block -->
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:12px;margin-bottom:24px;">
                <div style="background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;padding:14px;">
                    <div style="font-size:0.7rem;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Soll (Angebot)</div>
                    <div style="font-size:1.4rem;font-weight:700;color:var(--slate-800);margin-top:4px;">${fmt(sollTs)} TS</div>
                    <div style="font-size:0.7rem;color:var(--slate-500);">${fmt(d.soll_h)} h</div>
                </div>
                <div style="background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;padding:14px;">
                    <div style="font-size:0.7rem;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Ist (Geleistet)</div>
                    <div style="font-size:1.4rem;font-weight:700;color:${overrun ? 'var(--rose-700)' : 'var(--slate-800)'};margin-top:4px;">${fmt(istTs)} TS</div>
                    <div style="font-size:0.7rem;color:var(--slate-500);">${fmt(d.ist_h)} h · kulant ${fmt(d.ist_ts_kulanz)} TS</div>
                </div>
                <div style="background:${restTs !== null && restTs < 0 ? 'var(--rose-50)' : 'var(--emerald-50)'};border:1px solid ${restTs !== null && restTs < 0 ? 'var(--rose-200)' : 'var(--emerald-200)'};border-radius:8px;padding:14px;">
                    <div style="font-size:0.7rem;color:${restTs !== null && restTs < 0 ? 'var(--rose-700)' : 'var(--emerald-700)'};text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">${restTs !== null && restTs < 0 ? 'Überlauf' : 'Restbudget'}</div>
                    <div style="font-size:1.4rem;font-weight:700;color:${restTs !== null && restTs < 0 ? 'var(--rose-700)' : 'var(--emerald-700)'};margin-top:4px;">${restTs !== null ? (restTs >= 0 ? '+' : '') + ppFormatNum(restTs) + ' TS' : '—'}</div>
                    <div style="font-size:0.7rem;color:var(--slate-500);">Soll − Ist</div>
                </div>
                <div style="background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;padding:14px;">
                    <div style="font-size:0.7rem;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Diff zur Abrechnung</div>
                    <div style="font-size:1.4rem;font-weight:700;color:${d.diff_ts !== null && d.diff_ts > 0 ? 'var(--amber-700)' : 'var(--slate-800)'};margin-top:4px;">${d.diff_ts !== null ? (d.diff_ts >= 0 ? '+' : '') + ppFormatNum(d.diff_ts) + ' TS' : '—'}</div>
                    <div style="font-size:0.7rem;color:var(--slate-500);">Ist − Abgerechnet</div>
                </div>
            </div>

            <!-- Abrechnungs-Eingabe -->
            <div style="background:#fff;border:1px solid var(--slate-200);border-radius:8px;padding:16px;">
                <h4 style="margin:0 0 14px 0;font-size:0.9rem;color:var(--slate-700);">Abrechnung erfassen</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:0.78rem;color:var(--slate-600);font-weight:600;display:block;margin-bottom:4px;">Abgerechnet (TS)</label>
                        <input type="text" inputmode="decimal" id="pp-einzel-abg-ts" class="thx-input"
                               value="${d.abgerechnet_ts !== null ? ppFormatNum(d.abgerechnet_ts) : ''}"
                               placeholder="z.B. 6,50"
                               style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:0.78rem;color:var(--slate-600);font-weight:600;display:block;margin-bottom:4px;">Abgerechnet am</label>
                        <input type="date" id="pp-einzel-abg-am" class="thx-input"
                               value="${d.abgerechnet_am || ''}" style="width:100%;">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:0.78rem;color:var(--slate-600);font-weight:600;display:block;margin-bottom:4px;">Notiz zur Abrechnung (intern)</label>
                    <textarea id="pp-einzel-abg-notiz" class="thx-input" rows="2"
                              placeholder="z.B. „Angebot vom 09.11.2025 · Rechnung Nr. 2026-0042"" style="width:100%;font-family:inherit;">${ppEscape(d.abrechnung_notiz || '')}</textarea>
                </div>
                <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:10px;">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppEinzelAbrechnungLeeren(${d.plan_id})" style="color:var(--rose-700);">Abrechnung zurücksetzen</button>
                    <button class="thx-btn thx-btn-primary" onclick="ppEinzelAbrechnungSpeichern(${d.plan_id})">Speichern</button>
                </div>
            </div>

            <div style="margin-top:14px;font-size:0.72rem;color:var(--slate-500);">
                Hinweis: Einzelprojekte werden NICHT in die laufende Kunden-Quartalsbilanz eingerechnet — ihre Stunden bleiben hier eigenständig.
            </div>
        </div>
    `;
}

async function ppEinzelAbrechnungSpeichern(planId) {
    const tsRaw = document.getElementById('pp-einzel-abg-ts').value.trim();
    const am    = document.getElementById('pp-einzel-abg-am').value || null;
    const notiz = document.getElementById('pp-einzel-abg-notiz').value || null;
    const ts = tsRaw === '' ? null : parseFloat(tsRaw.replace(',', '.'));
    if (tsRaw !== '' && (isNaN(ts) || ts < 0)) { App.showNotification('Ungültiger TS-Wert', 'error'); return; }
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ abgerechnet_ts: ts, abgerechnet_am: am, abrechnung_notiz: notiz }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Abrechnung gespeichert.', 'success');
        await ppLoadAbrechnungEinzel(planId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function ppEinzelAbrechnungLeeren(planId) {
    if (!confirm('Abrechnung wirklich zurücksetzen?')) return;
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ abgerechnet_ts: null, abgerechnet_am: null, abrechnung_notiz: null }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        await ppLoadAbrechnungEinzel(planId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/** Direkter Modal-Open ueber Kunden-ID (z.B. aus dem Dashboard via Hash). */
async function ppOpenBudgetForCustomer(customerId) {
    if (!customerId) return;
    ppBudgetState.customerId = customerId;
    ppBudgetState.year = new Date().getFullYear();
    ppOpenModal('pp-budget-modal');
    await ppLoadBudget();
}
window.ppOpenBudgetForCustomer = ppOpenBudgetForCustomer;

async function ppLoadBudget() {
    const body = document.getElementById('pp-budget-body');
    body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch(`/api/v1/admin/projektplanner/budget/${ppBudgetState.customerId}/${ppBudgetState.year}`);
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppRenderBudget(j.data);
    } catch (e) {
        body.innerHTML = '<div style="color:var(--rose-600);padding:20px;">' + ppEscape(e.message) + '</div>';
    }
}

function ppRenderBudget(d) {
    const c = d.customer;
    const t = d.total_all;
    const monthNames = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
    document.getElementById('pp-budget-title').textContent = `Abrechnung — ${c.name}`;

    // Bei Einzelprojekt-Kunden statt 12-Monats-Tabelle die Plan-Liste rendern
    if (c.billing_model === 'einzelprojekt') {
        ppRenderBudgetEinzelprojekt(d);
        return;
    }

    const yearOptions = d.years.map(y => `<option value="${y}" ${y === d.year ? 'selected' : ''}>${y}</option>`).join('');
    const billingModelOptions = Object.entries(d.billing_models || {}).map(([k, v]) =>
        `<option value="${k}" ${c.billing_model === k ? 'selected' : ''}>${ppEscape(v.label)}</option>`
    ).join('');

    // Reporting-Zyklus aus dem Billing-Modell ableiten. Default: quartalsweise (3 Monate).
    // cycle = 1 (monatlich): jeder Monat ist eigene Periode, Diff pro Monat sichtbar.
    // cycle = 2 (bi-monatlich): 6 Perioden je 2 Monate.
    // cycle = 3 (quartalsweise): 4 Quartale.
    // cycle = 0 (einzelprojekt) oder >= 12: keine Gruppierung, Diff am Jahresende.
    const billingCycle = (d.billing_models && c.billing_model && d.billing_models[c.billing_model])
        ? d.billing_models[c.billing_model].cycle : 3;
    // Effektiver Gruppen-Cycle. 0 = einzelprojekt → wir behandeln wie 1 (keine Gruppen).
    const grpCycle = billingCycle > 0 ? billingCycle : 1;
    const showMonthDiff = grpCycle === 1; // nur bei monatlichem Reporting sinnvoll

    // Helper: TS aus Stunden mit kundenspezifischer Konvertierung (halbe Tage).
    const hoursPerTs = parseFloat(c.hours_per_ts) || 8;
    const hoursToTs = (h) => {
        if (h <= 0) return 0;
        const full = Math.floor(h / hoursPerTs);
        const rest = h - full * hoursPerTs;
        return rest >= hoursPerTs / 2 ? full + 0.5 : full;
    };
    // Wir rechnen im Viertelstunden-Takt (0,25 h). Plan-Distribution liefert oft
    // krumme Werte (z.B. 36,875) — auf 0,25 snappen damit Anzeige sauber wird.
    const roundQuarter = (h) => Math.round((+h || 0) * 4) / 4;

    // Perioden bauen: bei cycle=1 keine Gruppen-Header, sonst je grpCycle Monate
    const periods = [];
    for (let start = 1; start <= 12; start += grpCycle) {
        const end = Math.min(start + grpCycle - 1, 12);
        const monthsInPeriod = d.months.filter(m => m.month >= start && m.month <= end);
        const soll = monthsInPeriod.reduce((a, m) => a + (m.soll_ts || 0), 0);
        let abg = 0;
        monthsInPeriod.forEach(m => { if (m.abgerechnet_ts !== null) abg += m.abgerechnet_ts; });
        // abgHas = JEDER Monat der Periode ist abgerechnet (= Periode ist vollständig reportet).
        // Vorher: schon EIN abgerechneter Monat reichte, das verfaelschte die Bilanz bei
        // halb abgerechneten laufenden Perioden.
        const abgHas = monthsInPeriod.length > 0 && monthsInPeriod.every(m => m.abgerechnet_ts !== null);
        const istHraw = monthsInPeriod.reduce((a, m) => a + (m.ist_h || 0), 0);
        const istH = roundQuarter(istHraw); // auf 15-min-Takt snappen
        // TS-Logik fuer die Periode: der erste Monat ist Anker. Hat dieser ein
        // ist_ts_override gesetzt, gilt das als Periode-Override. Sonst wird
        // einmalig aus der Gesamt-Stunde berechnet (halbtages-genau).
        const anchor = monthsInPeriod[0];
        const hasOverride = anchor && anchor.ist_ts_override !== null && anchor.ist_ts_override !== undefined;
        const istTs = hasOverride ? parseFloat(anchor.ist_ts_override) : hoursToTs(istH);
        // Label je nach Cycle. Header in der Tabelle wird bei cycle=1 nicht gerendert,
        // aber den Label-Text brauchen wir trotzdem fuer die Bisher-Anzeige.
        let label;
        if (grpCycle === 1)       label = monthNames[start-1];
        else if (grpCycle === 2)  label = `${monthNames[start-1]}–${monthNames[end-1]}`;
        else if (grpCycle === 3)  label = `Quartal ${Math.ceil(start/3)}`;
        else if (grpCycle === 6)  label = `Halbjahr ${start === 1 ? 1 : 2}`;
        else                      label = `Jahr ${d.year}`;
        const mode = (anchor && anchor.mode) ? anchor.mode : 'retainer';
        const quarter = Math.ceil(start / 3);
        periods.push({ start, end, label, monthsInPeriod, soll, abg, abgHas, istH, istTs, mode, quarter });
    }

    // Monats-Zeile — bei cycle > 1 sind Ist-Spalten leer (Ist wird am Perioden-Header erfasst)
    const renderMonthRow = (m) => {
        const diffTsClass = m.diff_ts > 0 ? 'pp-budget-pos' : (m.diff_ts < 0 ? 'pp-budget-neg' : '');
        const futureClass = m.is_future ? 'pp-budget-future' : '';
        const istManuallySet = m.ist_manual !== null;
        const istTsManuallySet = m.ist_ts_override !== null;
        const istCells = showMonthDiff ? `
            <td class="is-right pp-bm-ist-h" title="${istManuallySet ? 'manuell' : 'aus Plan-Stunden berechnet'}">
                ${ppFormatNum(m.ist_h)}${istManuallySet ? ' *' : ''}
            </td>
            <td><input type="text" inputmode="decimal" value="${m.ist_ts_override !== null ? ppFormatNum(m.ist_ts_override) : (m.ist_ts > 0 ? ppFormatNum(m.ist_ts) : '')}" placeholder="${ppFormatNum(m.ist_ts)}"
                       class="pp-budget-input ${istTsManuallySet ? 'is-override' : ''}"
                       onblur="ppSaveIstTs(${m.month}, this.value)"
                       title="${istTsManuallySet ? 'manueller Override' : 'aus Std berechnet — Klick zum Überschreiben'}"></td>
            <td class="is-right ${diffTsClass}" style="font-variant-numeric:tabular-nums;font-weight:600;">
                ${m.is_future ? '—' : (m.diff_ts > 0 ? '+' : '') + ppFormatNum(m.diff_ts)}
            </td>`
            : `<td class="pp-bm-empty">—</td><td class="pp-bm-empty">—</td><td></td>`;
        return `
        <tr class="${futureClass}" data-month="${m.month}">
            <td class="pp-bm-name">${monthNames[m.month-1]}</td>
            ${m.mode === 'zuruf'
                ? `<td class="pp-bm-empty is-right">—</td>`
                : `<td><input type="text" inputmode="decimal" value="${m.soll_ts > 0 ? ppFormatNum(m.soll_ts) : ''}" placeholder="0" class="pp-budget-input" onblur="ppSaveBudgetMonth(${m.month}, this.value)" title="Soll-Tagessätze"></td>`}
            <td><input type="text" inputmode="decimal" value="${m.abgerechnet_ts !== null ? ppFormatNum(m.abgerechnet_ts) : ''}" placeholder="—"
                       class="pp-budget-input" onblur="ppSaveAbgerechnet(${m.month}, this.value)" title="${m.mode === 'zuruf' ? 'Abgerechnete Stunden' : 'Abgerechnet-TS'}"></td>
            ${istCells}
            <td><input type="text" value="${ppEscape(m.bemerkung || '')}" placeholder="—"
                       class="pp-budget-input pp-budget-note" onblur="ppSaveBemerkung(${m.month}, this.value)"></td>
        </tr>`;
    };

    // Vorjahres-Uebertrag wird der ERSTEN reporteten Periode zugeschlagen — sonst
    // suggeriert die Diff-Spalte nur das isolierte Quartal, ohne die Eroeffnungs-
    // Bilanz aus dem Vorjahr. Beispiel: 2025-Uebertrag +1 TS, Q1 isoliert -2 TS
    // → zeigt Q1 effektiv -1 TS (was real der Stand nach Q1 ist).
    const carryoverPrevYearForPeriod = parseFloat(c.uebertrag_ts) || 0;
    let carryoverApplied = false;
    const renderPeriodRow = (p) => {
        const modeToggle = `<span class="pp-mode-toggle" title="Abrechnungs-Modell dieses Quartals umschalten"><button class="${p.mode !== 'zuruf' ? 'is-active' : ''}" onclick="ppSaveQuarterMode(${p.quarter}, 'retainer')">Retainer</button><button class="${p.mode === 'zuruf' ? 'is-active' : ''}" onclick="ppSaveQuarterMode(${p.quarter}, 'zuruf')">auf Zuruf</button></span>`;
        if (p.mode === 'zuruf') {
            // Auf Zuruf: Stunden-basiert, kein Soll/Überhang. Diff = Geleistet − Abgerechnet (h).
            const hasAbg = p.abgHas || p.abg > 0;
            const offen = hasAbg ? (p.istH - p.abg) : null;
            const offenCls = offen == null ? '' : (offen > 0.001 ? 'pp-budget-pos' : (offen < -0.001 ? 'pp-budget-neg' : ''));
            return `
        <tr class="pp-budget-qrow">
            <td class="pp-bm-name">${ppEscape(p.label)} ${modeToggle}</td>
            <td class="is-right pp-bm-qsum pp-bm-empty">—</td>
            <td class="is-right pp-bm-qsum">${hasAbg ? ppFormatNum(p.abg) + ' h' : '—'}</td>
            <td class="is-right pp-bm-qsum-input">
                <input type="text" inputmode="decimal" value="${p.istH > 0 ? ppFormatNum(p.istH) : ''}" placeholder="—"
                       class="pp-budget-input pp-budget-period-input"
                       onblur="ppSavePeriodIstH(${p.start}, ${p.end}, this.value)"
                       title="Geleistete Stunden dieser Periode">
                <span class="pp-budget-unit">h</span>
            </td>
            <td class="is-right pp-bm-qsum pp-bm-empty" title="informativ, aus Stunden">${p.istH > 0 ? '≈ ' + ppFormatNum(hoursToTs(p.istH)) + ' TS' : '—'}</td>
            <td class="is-right pp-bm-qsum ${offenCls}" title="Geleistet − Abgerechnet (Stunden)">
                ${offen == null ? '' : (offen > 0 ? '+' : '') + ppFormatNum(offen) + ' h'}
            </td>
            <td></td>
        </tr>`;
        }
        // Retainer: Soll/Abgerechnet/Überhang in TS. Der Vorjahres-Übertrag zählt NUR hier
        // (erste Retainer-Periode) — Zuruf-Perioden überspringen ihn automatisch.
        const isolatedDiff = p.abgHas ? (p.istTs - p.abg) : null;
        let istVsAbg = isolatedDiff;
        let hasCarryover = false;
        if (p.abgHas && !carryoverApplied && Math.abs(carryoverPrevYearForPeriod) > 0.001) {
            istVsAbg = isolatedDiff + carryoverPrevYearForPeriod;
            carryoverApplied = true;
            hasCarryover = true;
        }
        const diffCls = istVsAbg == null ? '' : (istVsAbg > 0.001 ? 'pp-budget-pos' : (istVsAbg < -0.001 ? 'pp-budget-neg' : ''));
        const diffTitle = hasCarryover
            ? `Ist − Abgerechnet (${isolatedDiff > 0 ? '+' : ''}${ppFormatNum(isolatedDiff)}) + Uebertrag Vorjahr (${carryoverPrevYearForPeriod > 0 ? '+' : ''}${ppFormatNum(carryoverPrevYearForPeriod)})`
            : 'Ist − Abgerechnet (überliefert = grün, unterliefert = rot)';
        return `
        <tr class="pp-budget-qrow">
            <td class="pp-bm-name">${ppEscape(p.label)} ${modeToggle}</td>
            <td class="is-right pp-bm-qsum">${ppFormatNum(p.soll)} TS</td>
            <td class="is-right pp-bm-qsum">${p.abgHas ? ppFormatNum(p.abg) + ' TS' : '—'}</td>
            <td class="is-right pp-bm-qsum-input">
                <input type="text" inputmode="decimal" value="${p.istH > 0 ? ppFormatNum(p.istH) : ''}" placeholder="—"
                       class="pp-budget-input pp-budget-period-input"
                       onblur="ppSavePeriodIstH(${p.start}, ${p.end}, this.value)"
                       title="Ist-Stunden für die ganze Periode — wird auf den ersten Monat geschrieben">
                <span class="pp-budget-unit">h</span>
            </td>
            <td class="pp-bm-qsum-input">
                <input type="text" inputmode="decimal" value="${p.istTs > 0 ? ppFormatNum(p.istTs) : ''}" placeholder="—"
                       class="pp-budget-input pp-budget-period-input"
                       onblur="ppSavePeriodIstTs(${p.start}, ${p.end}, this.value)"
                       title="Ist-Tagessätze für die ganze Periode (Bauchgefühl, kulant)">
            </td>
            <td class="is-right pp-bm-qsum ${diffCls}" title="${diffTitle}">
                ${istVsAbg == null ? '' : (istVsAbg > 0 ? '+' : '') + ppFormatNum(istVsAbg) + ' TS'}${hasCarryover ? ' <span style="font-size:9px;color:var(--slate-400);font-weight:500;" title="inkl. Übertrag Vorjahr">*</span>' : ''}
            </td>
            <td></td>
        </tr>`;
    };

    // Tabelle: Perioden-Header + Monatszeilen (bei cycle=1 nur Monatszeilen)
    const tableBody = [];
    periods.forEach(p => {
        if (grpCycle > 1) tableBody.push(renderPeriodRow(p));
        p.monthsInPeriod.forEach(m => tableBody.push(renderMonthRow(m)));
    });

    // "Bisher"-Aggregate: alle Perioden bis einschliesslich der laufenden.
    // Vergangene Jahre: ganzes Jahr. Zukuenftige Jahre: nichts.
    const nowDate = new Date();
    const cYear = nowDate.getFullYear();
    const cMonth = nowDate.getMonth() + 1;
    let lastPeriodIdx = -1;
    if (d.year < cYear) lastPeriodIdx = periods.length - 1;
    else if (d.year === cYear) lastPeriodIdx = periods.findIndex(p => p.start <= cMonth && cMonth <= p.end);
    // Bisher-Aggregate. Ueberhang zaehlt NUR Perioden, die schon vollstaendig
    // abgerechnet wurden — laufende/unfertige Perioden verfaelschen sonst die Bilanz.
    // Vorjahres-Übertrag (uebertrag_ts) fliesst als Startwert in den Ueberhang ein.
    const carryoverPrevYear = parseFloat(c.uebertrag_ts) || 0;
    // Bisher-Aggregate — getrennt nach Modus. Retainer rechnet in TS (inkl. Vorjahres-Übertrag),
    // Auf Zuruf in Stunden. So bekommt ein gemischtes Jahr zwei saubere Bilanzen.
    const bRet = { soll: 0, abg: 0, istTs: 0, ueberhangTs: carryoverPrevYear, reported: 0 };
    const bZur = { istH: 0, abg: 0, reported: 0 };
    for (let i = 0; i <= lastPeriodIdx; i++) {
        const pr = periods[i];
        if (pr.mode === 'zuruf') {
            bZur.istH += pr.istH;
            if (pr.abgHas || pr.abg > 0) { bZur.abg += pr.abg; bZur.reported++; }
        } else {
            bRet.soll += pr.soll;
            bRet.istTs += pr.istTs;
            const zaehlt = pr.abgHas || (pr.abg > 0 && pr.istTs > 0);
            if (zaehlt) { bRet.abg += pr.abg; bRet.ueberhangTs += (pr.istTs - pr.abg); bRet.reported++; }
        }
    }
    const hasRetainer = bRet.reported > 0 || bRet.soll > 0.001 || Math.abs(carryoverPrevYear) > 0.001;
    const hasZuruf = bZur.reported > 0 || bZur.istH > 0.001;
    const retUeberhangData = bRet.reported > 0 || Math.abs(carryoverPrevYear) > 0.001;
    const retUeberhang = retUeberhangData ? bRet.ueberhangTs : null;
    const retUeberhangClass = retUeberhang == null ? '' : (retUeberhang > 0.001 ? 'is-pos' : (retUeberhang < -0.001 ? 'is-neg' : ''));
    const retSubParts = [];
    if (Math.abs(carryoverPrevYear) > 0.001) retSubParts.push('Übertrag ' + (d.year - 1));
    if (bRet.reported > 0) retSubParts.push(bRet.reported + ' reportete Period' + (bRet.reported === 1 ? 'e' : 'en'));
    const retUeberhangSub = retSubParts.length > 0 ? retSubParts.join(' + ') : 'noch keine Period abgerechnet';
    const zurOffen = bZur.istH - bZur.abg;
    const bisherLabel = lastPeriodIdx < 0 ? 'noch nichts' : 'bis ' + periods[lastPeriodIdx].label.replace('Quartal ', 'Q');

    document.getElementById('pp-budget-body').innerHTML = `
        <!-- ===== Konfigurations-Block ===== -->
        <div class="pp-budget-config">
            <div class="pp-budget-config-row">
                <div class="pp-budget-field">
                    <label>Jahr</label>
                    <select onchange="ppBudgetState.year = parseInt(this.value); ppLoadBudget();">${yearOptions}</select>
                </div>
                <div class="pp-budget-field" style="flex:2;">
                    <label>Abrechnungs-Modell</label>
                    <select id="pp-cfg-billing-model" onchange="ppSaveBillingConfig()">
                        <option value="">— nicht gesetzt —</option>
                        ${billingModelOptions}
                    </select>
                </div>
                <div class="pp-budget-field">
                    <label>TS / Monat</label>
                    <input type="text" inputmode="decimal" id="pp-cfg-ts-per-month"
                           value="${c.ts_per_month !== null ? ppFormatNum(c.ts_per_month) : ''}" placeholder="—"
                           onblur="ppSaveBillingConfig()">
                </div>
                <div class="pp-budget-field">
                    <label>Std / TS (Kulanz)</label>
                    <div style="display:flex;gap:4px;align-items:center;">
                        <input type="text" inputmode="decimal" id="pp-cfg-hours-per-ts"
                               value="${ppFormatNum(parseFloat(c.hours_per_ts) || 8)}" onblur="ppSaveBillingConfig()" style="width:60px;">
                        <span style="color:var(--slate-400);">–</span>
                        <input type="text" inputmode="decimal" id="pp-cfg-hours-per-ts-max"
                               value="${ppFormatNum(parseFloat(c.hours_per_ts_max) || 10)}" onblur="ppSaveBillingConfig()" style="width:60px;">
                    </div>
                </div>
                <div class="pp-budget-field">
                    <label>Übertrag ${d.year - 1} (TS)</label>
                    <input type="text" inputmode="decimal" id="pp-uebertrag-ts"
                           value="${ppFormatNum(parseFloat(c.uebertrag_ts) || 0)}" onblur="ppSaveUebertrag()">
                </div>
                <div class="pp-budget-field" style="flex:0 0 auto;">
                    <button class="thx-btn thx-btn-secondary thx-btn-small"
                            onclick="ppApplyBudgetDefaults()" title="Soll für alle 12 Monate aus TS/Monat füllen (nur leere)">
                        <span class="material-symbols-rounded" style="font-size:13px;">auto_awesome</span>
                        Soll auto-füllen
                    </button>
                </div>
            </div>
            <div class="pp-budget-config-row">
                <div class="pp-budget-field" style="flex:1;">
                    <label>Notizen zur Abrechnung (intern)</label>
                    <textarea id="pp-cfg-billing-notes" onblur="ppSaveBillingConfig()" rows="2"
                              placeholder="z.B. „4 TS/Monat · bi-monatlich · Sondervereinbarung 2026"">${ppEscape(c.billing_notes || '')}</textarea>
                </div>
            </div>
        </div>

        <!-- ===== Status-Leiste — Retainer- und/oder Zuruf-Block ===== -->
        ${hasRetainer ? `
        <div class="pp-budget-summary">
            <div><div class="pp-budget-stat-label">Soll bisher${hasZuruf ? ' · Retainer' : ''}</div>
                <div class="pp-budget-stat-value">${ppFormatNum(bRet.soll)} TS</div>
                <div class="pp-budget-stat-sub">${ppEscape(bisherLabel)}</div>
            </div>
            <div><div class="pp-budget-stat-label">Abgerechnet</div>
                <div class="pp-budget-stat-value">${bRet.reported > 0 ? ppFormatNum(bRet.abg) + ' TS' : '—'}</div>
            </div>
            <div><div class="pp-budget-stat-label">Geleistet (Ist)</div>
                <div class="pp-budget-stat-value">${ppFormatNum(bRet.istTs)} TS</div>
            </div>
            <div class="pp-budget-stat-ueberhang ${retUeberhangClass}"
                 title="${retUeberhang != null && retUeberhang > 0 ? 'Wir haben überliefert — abbummelbar oder extra abrechnen' : (retUeberhang != null && retUeberhang < 0 ? 'Kunde hat Vorschuss — wir müssen noch nachliefern' : 'Noch keine Periode abgerechnet')}">
                <div class="pp-budget-stat-label">Überhang ${retUeberhang != null && retUeberhang > 0 ? '(unsere Gunst)' : (retUeberhang != null && retUeberhang < 0 ? '(Kunde Gunst)' : '')}</div>
                <div class="pp-budget-stat-value">${retUeberhang == null ? '—' : (retUeberhang > 0 ? '+' : '') + ppFormatNum(retUeberhang) + ' TS'}</div>
                <div class="pp-budget-stat-sub">${ppEscape(retUeberhangSub)}</div>
            </div>
        </div>` : ''}
        ${hasZuruf ? `
        <div class="pp-budget-summary">
            <div><div class="pp-budget-stat-label">Geleistet (Ist)${hasRetainer ? ' · auf Zuruf' : ''}</div>
                <div class="pp-budget-stat-value">${ppFormatNum(bZur.istH)} h</div>
                <div class="pp-budget-stat-sub">≈ ${ppFormatNum(hoursToTs(bZur.istH))} TS · ${ppEscape(bisherLabel)}</div>
            </div>
            <div><div class="pp-budget-stat-label">Abgerechnet</div>
                <div class="pp-budget-stat-value">${bZur.reported > 0 ? ppFormatNum(bZur.abg) + ' h' : '—'}</div>
                <div class="pp-budget-stat-sub">${bZur.reported > 0 ? '≈ ' + ppFormatNum(hoursToTs(bZur.abg)) + ' TS' : 'noch nichts abgerechnet'}</div>
            </div>
            <div class="pp-budget-stat-ueberhang ${Math.abs(zurOffen) < 0.01 ? 'is-pos' : ''}"
                 title="Geleistet − Abgerechnet. 0 = ausgeglichen; positiv = noch zu fakturieren. Auf Zuruf gibt es kein Soll.">
                <div class="pp-budget-stat-label">Offen abzurechnen</div>
                <div class="pp-budget-stat-value">${(zurOffen > 0 ? '+' : '') + ppFormatNum(zurOffen)} h</div>
                <div class="pp-budget-stat-sub">auf Zuruf · kein Soll</div>
            </div>
        </div>` : ''}

        <!-- ===== 12-Monats-Tabelle gegliedert nach Reporting-Zyklus ===== -->
        <table class="pp-budget-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Soll (TS)</th>
                    <th>Abger.</th>
                    <th class="is-right">Ist (h)</th>
                    <th>Ist (TS)</th>
                    <th class="is-right">${showMonthDiff ? 'Diff TS' : 'Diff Periode'}</th>
                    <th>Bemerkung</th>
                </tr>
            </thead>
            <tbody>${tableBody.join('')}</tbody>
        </table>
        <div class="pp-budget-legend">
            * = manuelle Ist-Std-Überschreibung · TS-Felder mit Wert = manueller Override (sonst aus Std berechnet) ·
            1 TS = ${d.hours_per_ts} h (kulant bis ${d.hours_per_ts_max} h)
            ${grpCycle > 1 ? `· Differenz wird auf Perioden-Ebene angezeigt (Reporting-Zyklus: ${grpCycle} Monate)` : ''}
        </div>`;
}

/* ===== Einzelprojekt-Modus der Abrechnungs-Lightbox =====
   Statt der 12-Monats-Tabelle: Konfig + Liste aller Einzelprojekt-Plaene mit
   Angebot (offer_ts), Geplant, Geleistet, Spielraum pro Plan. */
function ppRenderBudgetEinzelprojekt(d) {
    const c = d.customer;
    const plans = d.einzelprojekt_plans || [];
    const yearOptions = d.years.map(y => `<option value="${y}" ${y === d.year ? 'selected' : ''}>${y}</option>`).join('');
    const billingModelOptions = Object.entries(d.billing_models || {}).map(([k, v]) =>
        `<option value="${k}" ${c.billing_model === k ? 'selected' : ''}>${ppEscape(v.label)}</option>`
    ).join('');

    // Aggregate fuer die Status-Leiste
    const totals = plans.reduce((acc, p) => {
        acc.offer_ts += p.offer_ts || 0;
        acc.offer_h  += p.offer_h  || 0;
        acc.planned_h += p.planned_h || 0;
        acc.ist_h    += p.ist_h    || 0;
        return acc;
    }, { offer_ts: 0, offer_h: 0, planned_h: 0, ist_h: 0 });
    const totalSpielraum = totals.offer_h > 0 ? (totals.planned_h - totals.offer_h) : 0;
    const istVsOfferH = totals.ist_h - totals.offer_h; // wie viel mehr/weniger geleistet als Angebot

    const fmtDate = (iso) => {
        if (!iso) return '';
        const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}.${m[2]}.${m[1].substring(2)}` : iso;
    };

    const planRows = plans.length === 0
        ? `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--slate-400);">Noch keine Einzelprojekt-Pläne in ${d.year}. Lege im Projektplanner einen Plan an und setze Status auf „Einzelprojekt".</td></tr>`
        : plans.map(p => {
            // Einzelprojekt: positiv (mehr geplant als Angebot) = ROT, negativ = GRÜN
            const spielraumCls = p.spielraum_h > 0.01 ? 'pp-budget-neg' : (p.spielraum_h < -0.01 ? 'pp-budget-pos' : '');
            const period = p.period_from
                ? (fmtDate(p.period_from) + (p.period_to ? ' – ' + fmtDate(p.period_to) : ''))
                : '—';
            return `
            <tr data-plan-id="${p.id}">
                <td><a href="${ppEscape(p.plan_url)}" class="pp-bm-plan-link">${ppEscape(p.title)}</a>
                    <div class="pp-bm-plan-period">${ppEscape(period)}</div>
                </td>
                <td>
                    <div class="pp-bm-offer-inputs">
                        <input type="text" inputmode="decimal" value="${p.offer_ts > 0 ? ppFormatNum(p.offer_ts) : ''}" placeholder="—"
                               class="pp-budget-input pp-bm-offer-ts" onblur="ppSavePlanOfferTs(${p.id}, this.value, ${d.hours_per_ts})"
                               title="Angebot in Tagessätzen">
                        <span class="pp-bm-offer-sep">TS</span>
                        <span style="color:var(--slate-300);">/</span>
                        <input type="text" inputmode="decimal" value="${p.offer_h > 0 ? ppFormatNum(p.offer_h) : ''}" placeholder="—"
                               class="pp-budget-input pp-bm-offer-h" onblur="ppSavePlanOfferH(${p.id}, this.value, ${d.hours_per_ts})"
                               title="Angebot in Stunden — wird automatisch in TS umgerechnet">
                        <span class="pp-bm-offer-sep">h</span>
                    </div>
                </td>
                <td class="is-right pp-bm-num">${ppFormatNum(p.planned_h)} h</td>
                <td class="is-right pp-bm-num">${ppFormatNum(p.ist_h)} h</td>
                <td class="is-right ${spielraumCls}" style="font-weight:600;font-variant-numeric:tabular-nums;"
                    title="Geplant − Angebot. Positiv = überzogen (rot), negativ = im Rahmen (grün)">
                    ${p.offer_h > 0 ? (p.spielraum_h > 0 ? '+' : '') + ppFormatNum(p.spielraum_h) + ' h' : '—'}
                </td>
            </tr>`;
        }).join('');

    document.getElementById('pp-budget-body').innerHTML = `
        <!-- Konfig (schlank — keine TS/Monat oder Übertrag bei Einzelprojekt-Kunden) -->
        <div class="pp-budget-config">
            <div class="pp-budget-config-row">
                <div class="pp-budget-field">
                    <label>Jahr</label>
                    <select onchange="ppBudgetState.year = parseInt(this.value); ppLoadBudget();">${yearOptions}</select>
                </div>
                <div class="pp-budget-field" style="flex:2;">
                    <label>Abrechnungs-Modell</label>
                    <select id="pp-cfg-billing-model" onchange="ppSaveBillingConfig()">
                        <option value="">— nicht gesetzt —</option>
                        ${billingModelOptions}
                    </select>
                </div>
                <div class="pp-budget-field">
                    <label>Std / TS (Kulanz)</label>
                    <div style="display:flex;gap:4px;align-items:center;">
                        <input type="text" inputmode="decimal" id="pp-cfg-hours-per-ts"
                               value="${ppFormatNum(parseFloat(c.hours_per_ts) || 8)}" onblur="ppSaveBillingConfig()" style="width:60px;">
                        <span style="color:var(--slate-400);">–</span>
                        <input type="text" inputmode="decimal" id="pp-cfg-hours-per-ts-max"
                               value="${ppFormatNum(parseFloat(c.hours_per_ts_max) || 10)}" onblur="ppSaveBillingConfig()" style="width:60px;">
                    </div>
                </div>
            </div>
            <div class="pp-budget-config-row">
                <div class="pp-budget-field" style="flex:1;">
                    <label>Notizen zur Abrechnung (intern)</label>
                    <textarea id="pp-cfg-billing-notes" onblur="ppSaveBillingConfig()" rows="2"
                              placeholder="z.B. „Abrechnung über Abschläge, Schlussrechnung am Projektende"">${ppEscape(c.billing_notes || '')}</textarea>
                </div>
                <!-- Versteckte Felder, damit ppSaveBillingConfig() nicht ueber undefined stolpert -->
                <input type="hidden" id="pp-cfg-ts-per-month" value="">
                <input type="hidden" id="pp-uebertrag-ts" value="${ppFormatNum(parseFloat(c.uebertrag_ts) || 0)}">
            </div>
        </div>

        <!-- Status-Leiste (Aggregate ueber alle Einzelprojekt-Plaene) -->
        <div class="pp-budget-summary">
            <div><div class="pp-budget-stat-label">Angebote (Σ)</div>
                <div class="pp-budget-stat-value">${ppFormatNum(totals.offer_ts)} TS</div>
                <div class="pp-budget-stat-sub">${ppFormatNum(totals.offer_h)} h</div>
            </div>
            <div><div class="pp-budget-stat-label">Geplant (Σ)</div>
                <div class="pp-budget-stat-value">${ppFormatNum(totals.planned_h)} h</div>
            </div>
            <div><div class="pp-budget-stat-label">Geleistet (Ist)</div>
                <div class="pp-budget-stat-value">${ppFormatNum(totals.ist_h)} h</div>
            </div>
            <div class="pp-budget-stat-ueberhang ${istVsOfferH > 0.01 ? 'is-neg' : (istVsOfferH < -0.01 ? 'is-pos' : '')}"
                 title="Ist − Angebot. Positiv = überzogen (rot), negativ = im Rahmen (grün)">
                <div class="pp-budget-stat-label">${istVsOfferH > 0.01 ? 'Über Angebot' : (istVsOfferH < -0.01 ? 'Im Rahmen' : 'Ausgeglichen')}</div>
                <div class="pp-budget-stat-value">${istVsOfferH > 0 ? '+' : ''}${ppFormatNum(istVsOfferH)} h</div>
                <div class="pp-budget-stat-sub">${plans.length} ${plans.length === 1 ? 'Projekt' : 'Projekte'}</div>
            </div>
        </div>

        <!-- Plan-Liste -->
        <table class="pp-budget-table">
            <thead>
                <tr>
                    <th>Projekt</th>
                    <th>Angebot (TS)</th>
                    <th class="is-right">Geplant</th>
                    <th class="is-right">Geleistet</th>
                    <th class="is-right">Spielraum</th>
                </tr>
            </thead>
            <tbody>${planRows}</tbody>
        </table>
        <div class="pp-budget-legend">
            Angebot wird je Plan gepflegt (Gesamt-Tagessätze laut Angebot/Kostenschätzung) ·
            Geplant + Ist kommen direkt aus den Plan-Zeilen · 1 TS = ${d.hours_per_ts} h
        </div>`;
}

async function ppSavePlanOfferTs(planId, value, hoursPerTs) {
    const raw = String(value).trim().replace(',', '.');
    const offerTs = raw === '' ? null : parseFloat(raw);
    await ppSavePlanOffer(planId, offerTs);
}
async function ppSavePlanOfferH(planId, value, hoursPerTs) {
    const raw = String(value).trim().replace(',', '.');
    if (raw === '') { await ppSavePlanOffer(planId, null); return; }
    const hours = parseFloat(raw);
    const offerTs = hoursPerTs > 0 ? hours / hoursPerTs : 0;
    await ppSavePlanOffer(planId, offerTs);
}
async function ppSavePlanOffer(planId, offerTs) {
    try {
        const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ offer_ts: offerTs }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Speichern fehlgeschlagen');
    } catch (e) { App.showNotification(e.message, 'error'); return; }
    ppRefreshAfterBudgetSave();
}

async function ppBudgetPost(action, body) {
    const url = '/api/v1/admin/projektplanner/budget/' + ppBudgetState.customerId + (action ? '?action=' + action : '');
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ year: ppBudgetState.year, ...body }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        return j;
    } catch (e) { App.showNotification(e.message, 'error'); throw e; }
}

async function ppRefreshAfterBudgetSave() {
    await ppLoadBudget();
    if (ppState.activePlanId) {
        try {
            // Vollen Plan neu laden (damit u.a. customer_main_contact_* im Widget aktuell sind)
            const pr = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId).then(r => r.json());
            if (pr.success && pr.data) {
                // Rows behalten (keine teure Re-Render), nur Plan-Felder mergen
                const newPlan = pr.data;
                ppState.activePlan = Object.assign({}, ppState.activePlan || {}, newPlan, {
                    rows: ppState.activeRows,
                });
            }
            const b = await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId + '/budget-soll').then(r => r.json());
            if (b.success) { ppState.planBudget = b.data; }
        } catch (_) {}
        ppRenderEditor();
    }
}

async function ppSaveBudgetMonth(month, value) {
    const sollTs = parseFloat(String(value).replace(',', '.')) || 0;
    await ppBudgetPost('', { month, soll_ts: sollTs });
    ppRefreshAfterBudgetSave();
}

async function ppSaveAbgerechnet(month, value) {
    const v = String(value).trim();
    const val = v === '' ? null : parseFloat(v.replace(',', '.'));
    await ppBudgetPost('abgerechnet', { month, abgerechnet_ts: val });
    ppRefreshAfterBudgetSave();
}

async function ppSaveIstTs(month, value) {
    const v = String(value).trim();
    const val = v === '' ? null : parseFloat(v.replace(',', '.'));
    await ppBudgetPost('ist-ts', { month, ist_ts: val });
    ppRefreshAfterBudgetSave();
}

async function ppSaveBemerkung(month, value) {
    await ppBudgetPost('bemerkung', { month, bemerkung: value });
}

/** Abrechnungs-Modell eines Quartals umschalten (Retainer / auf Zuruf) — gemischte Projekte. */
async function ppSaveQuarterMode(quarter, mode) {
    await ppBudgetPost('mode', { quarter, mode });
    await ppLoadBudget(); // Modell-Wechsel betrifft Tabelle + Zusammenfassung → voll neu rendern
}

async function ppSaveBillingConfig() {
    const billingModel = document.getElementById('pp-cfg-billing-model').value || null;
    const tsPerMonth   = document.getElementById('pp-cfg-ts-per-month').value;
    const hoursPerTs   = document.getElementById('pp-cfg-hours-per-ts').value;
    const hoursPerTsMx = document.getElementById('pp-cfg-hours-per-ts-max').value;
    const notes        = document.getElementById('pp-cfg-billing-notes').value;
    await ppBudgetPost('config', {
        billing_model: billingModel,
        ts_per_month: tsPerMonth === '' ? null : parseFloat(tsPerMonth.replace(',', '.')),
        hours_per_ts: hoursPerTs === '' ? 8 : parseFloat(hoursPerTs.replace(',', '.')),
        hours_per_ts_max: hoursPerTsMx === '' ? 10 : parseFloat(hoursPerTsMx.replace(',', '.')),
        billing_notes: notes || null,
    });
    ppRefreshAfterBudgetSave();
}

async function ppApplyBudgetDefaults() {
    const force = confirm('Soll-Werte für alle 12 Monate aus „TS/Monat" füllen?\n\n„OK" = nur leere Monate füllen\n„Abbrechen" um zu stoppen.');
    if (!force) return;
    const j = await ppBudgetPost('apply-defaults', { force: false });
    App.showNotification((j.message || '') + '.', 'success');
    ppRefreshAfterBudgetSave();
}

/** Period-Ist (Std) speichern — schreibt den Wert auf den ERSTEN Monat der Periode,
 *  die übrigen Monate werden auf NULL (= zurueck zur Auto-Berechnung) zurueckgesetzt. */
async function ppSavePeriodIstH(startMonth, endMonth, value) {
    const v = String(value).trim();
    const val = v === '' ? null : parseFloat(v.replace(',', '.'));
    // First-Month: Wert; alle weiteren Monate der Periode: NULL (keine Stunden-Override)
    await ppBudgetPost('override', { month: startMonth, ist_override: val, ist_note: null });
    for (let m = startMonth + 1; m <= endMonth; m++) {
        await ppBudgetPost('override', { month: m, ist_override: null, ist_note: null });
    }
    ppRefreshAfterBudgetSave();
}

/** Period-Ist (TS) speichern — analog: First-Month traegt, andere NULL. */
async function ppSavePeriodIstTs(startMonth, endMonth, value) {
    const v = String(value).trim();
    const val = v === '' ? null : parseFloat(v.replace(',', '.'));
    await ppBudgetPost('ist-ts', { month: startMonth, ist_ts: val });
    for (let m = startMonth + 1; m <= endMonth; m++) {
        await ppBudgetPost('ist-ts', { month: m, ist_ts: null });
    }
    ppRefreshAfterBudgetSave();
}

async function ppSaveUebertrag() {
    const raw = String(document.getElementById('pp-uebertrag-ts').value).replace(',', '.');
    const ts = parseFloat(raw) || 0;
    await ppBudgetPost('uebertrag', { uebertrag_ts: ts, abrechnungsmodus: 'quarterly' });
}

/* ===== Asana Connect ===== */
async function ppOpenAsanaConnect() {
    if (!ppState.activePlan) return;
    ppOpenModal('pp-asana-connect-modal');
    const body = document.getElementById('pp-asana-connect-body');
    body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--slate-400);">Asana-Projekte werden geladen…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/projects');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const current = ppState.activePlan.asana_project_gid;
        const opts = (j.data.projects || []).map(p =>
            `<option value="${p.gid}" ${p.gid === current ? 'selected' : ''}>${ppEscape(p.name)}</option>`
        ).join('');
        body.innerHTML = `
            <div class="pp-field">
                <label>Asana-Projekt</label>
                <select id="pp-asana-project-select" onchange="ppLoadAsanaSections(this.value)">
                    <option value="">— Keine Verknüpfung —</option>
                    ${opts}
                </select>
            </div>
            <div class="pp-field" id="pp-asana-section-wrap" style="display:none;">
                <label>Section (optional)</label>
                <select id="pp-asana-section-select"><option value="">— Keine Section —</option></select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button class="thx-btn thx-btn-secondary" onclick="ppCloseModal('pp-asana-connect-modal')">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" onclick="ppSaveAsanaConnect()">Speichern</button>
            </div>`;
        if (current) ppLoadAsanaSections(current);
    } catch (e) {
        body.innerHTML = `<div style="padding:20px;color:var(--rose-600);">${ppEscape(e.message)}</div>`;
    }
}

async function ppLoadAsanaSections(projectGid) {
    const wrap = document.getElementById('pp-asana-section-wrap');
    const sel = document.getElementById('pp-asana-section-select');
    if (!projectGid) { wrap.style.display = 'none'; return; }
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/sections?project_gid=' + encodeURIComponent(projectGid));
        const j = await r.json();
        if (!j.success) return;
        const current = ppState.activePlan.asana_section_gid;
        sel.innerHTML = '<option value="">— Keine Section —</option>' +
            (j.data.sections || []).map(s => `<option value="${s.gid}" ${s.gid === current ? 'selected' : ''}>${ppEscape(s.name)}</option>`).join('');
        wrap.style.display = 'block';
    } catch (_) {}
}

async function ppSaveAsanaConnect() {
    const projectGid = document.getElementById('pp-asana-project-select').value || null;
    const sectionGid = projectGid ? (document.getElementById('pp-asana-section-select').value || null) : null;
    try {
        await fetch('/api/v1/admin/projektplanner/plans/' + ppState.activePlanId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ asana_project_gid: projectGid, asana_section_gid: sectionGid }),
        });
        ppState.activePlan.asana_project_gid = projectGid;
        ppState.activePlan.asana_section_gid = sectionGid;
        App.showNotification('Asana-Verknüpfung gespeichert', 'success');
        ppCloseModal('pp-asana-connect-modal');
        ppRenderEditor();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Asana Task ===== */
async function ppOpenAsanaTaskModal(rowId, initialMode) {
    if (!ppState.activePlan || !ppState.activePlan.asana_project_gid) {
        App.showNotification('Plan zuerst mit Asana-Projekt verknüpfen', 'error'); return;
    }
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (!row) return;
    const mode = (initialMode === 'link') ? 'link' : 'create';
    ppOpenModal('pp-asana-task-modal');
    const body = document.getElementById('pp-asana-task-body');
    body.innerHTML = `
        <div style="display:flex;gap:4px;margin-bottom:14px;">
            <button class="thx-chip ${mode === 'create' ? 'is-active' : ''}" data-mode="create" onclick="ppAsanaTabSwitch('create', ${rowId})">Neu erstellen</button>
            <button class="thx-chip ${mode === 'link' ? 'is-active' : ''}" data-mode="link" onclick="ppAsanaTabSwitch('link', ${rowId})">Bestehenden verknüpfen</button>
        </div>
        <div id="pp-asana-task-content"></div>`;
    ppAsanaTabSwitch(mode, rowId);
}

function ppAsanaTabSwitch(mode, rowId) {
    document.querySelectorAll('#pp-asana-task-body .thx-chip').forEach(b =>
        b.classList.toggle('is-active', b.dataset.mode === mode));
    const wrap = document.getElementById('pp-asana-task-content');
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (mode === 'create') {
        wrap.innerHTML = `
            <div class="pp-field"><label>Task-Name</label>
                <input type="text" id="pp-asana-task-name" value="${ppEscape(row.description || '')}" autofocus></div>
            <div class="pp-field"><label>Beschreibung (optional)</label>
                <input type="text" id="pp-asana-task-notes" value="${ppEscape(row.notes || '')}"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button class="thx-btn thx-btn-secondary" onclick="ppCloseModal('pp-asana-task-modal')">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" onclick="ppCreateAsanaTask(${rowId})">Task erstellen + verknüpfen</button>
            </div>`;
    } else {
        wrap.innerHTML = `
            <div class="pp-field">
                <label>Task suchen oder URL einfügen</label>
                <input type="text" id="pp-asana-search-input" placeholder="Suchbegriff ODER app.asana.com-URL…" oninput="ppAsanaSearch(${rowId})">
                <div style="font-size:11px;color:var(--slate-400);margin-top:4px;">Du kannst direkt eine Asana-URL einfügen — wir extrahieren die Task-ID automatisch.</div>
            </div>
            <div id="pp-asana-search-results" style="height:300px;overflow-y:auto;margin:8px 0;border:1px solid var(--slate-200);border-radius:6px;"></div>`;
        ppAsanaSearch(rowId);
    }
}

let ppAsanaSearchTimer = null;
function ppAsanaSearch(rowId) {
    clearTimeout(ppAsanaSearchTimer);
    ppAsanaSearchTimer = setTimeout(async () => {
        const q = (document.getElementById('pp-asana-search-input')?.value || '').trim();
        const wrap = document.getElementById('pp-asana-search-results');

        // URL-Erkennung: app.asana.com/0/<projectGid>/<taskGid> oder /1/<workspaceGid>/...
        const urlMatch = q.match(/asana\.com\/[\d]+\/[\d]+\/([\d]+)/);
        if (urlMatch) {
            const gid = urlMatch[1];
            wrap.innerHTML = `<div style="padding:14px;background:var(--thoxan-50);border-radius:6px;text-align:center;">
                Asana-URL erkannt — Task-ID <code>${gid}</code>
                <div style="margin-top:8px;"><button class="thx-btn thx-btn-primary thx-btn-small" onclick="ppLinkAsanaTask(${rowId}, '${gid}')">Verknüpfen</button></div>
            </div>`;
            return;
        }

        wrap.innerHTML = '<div style="padding:10px;color:var(--slate-400);text-align:center;">Lädt…</div>';
        try {
            const r = await fetch('/api/v1/admin/projektplanner/asana/search?project_gid=' + encodeURIComponent(ppState.activePlan.asana_project_gid) + '&q=' + encodeURIComponent(q));
            const j = await r.json();
            const tasks = j.data?.tasks || [];
            wrap.innerHTML = tasks.length === 0
                ? '<div style="padding:10px;color:var(--slate-400);text-align:center;">Keine Treffer</div>'
                : tasks.map(t => `
                    <div onclick="ppLinkAsanaTask(${rowId}, '${t.gid}')"
                         style="padding:10px 12px;border-bottom:1px solid var(--slate-100);cursor:pointer;display:flex;align-items:flex-start;gap:8px;"
                         onmouseover="this.style.background='var(--slate-50)'" onmouseout="this.style.background=''">
                        <span class="material-symbols-rounded" style="font-size:14px;margin-top:2px;color:${t.completed ? 'var(--emerald-600)' : 'var(--slate-400)'};">${t.completed ? 'check_circle' : 'radio_button_unchecked'}</span>
                        <span style="flex:1;min-width:0;">
                            <span style="display:block;word-break:break-word;">${ppEscape(t.name)}</span>
                            ${t.parent && t.parent.name ? `<span style="display:block;font-size:11px;color:var(--slate-400);margin-top:2px;">↳ Unteraufgabe von: ${ppEscape(t.parent.name)}</span>` : ''}
                        </span>
                    </div>`).join('');
        } catch (e) {
            wrap.innerHTML = '<div style="padding:10px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
        }
    }, 300);
}

async function ppCreateAsanaTask(rowId) {
    const name = document.getElementById('pp-asana-task-name').value.trim();
    if (!name) { App.showNotification('Name erforderlich', 'error'); return; }
    const notes = document.getElementById('pp-asana-task-notes').value.trim();
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({
                project_gid: ppState.activePlan.asana_project_gid,
                section_gid: ppState.activePlan.asana_section_gid || null,
                name, notes: notes || null, plan_row_id: rowId,
            }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Task erstellt und verknüpft', 'success');
        ppCloseModal('pp-asana-task-modal');
        // Nur die lokale Zeile aktualisieren + Editor neu rendern (preserveiert Scroll).
        // Vorher: ppOpenPlan() → kompletter Reload → Sprung nach oben.
        const t = j.data?.task || {};
        const row = ppState.activeRows.find(x => x.id === rowId);
        if (row) {
            row.asana_gid = t.gid || '';
            row.asana_url = t.permalink_url || ('https://app.asana.com/0/0/' + t.gid);
            row.asana_task_name = t.name || name;
        }
        ppRenderEditor();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

function ppPrintPlan() {
    // CSS @media print regelt das Layout — wir triggern nur den Druck-Dialog.
    // Vor dem Druck Sticky-Compact + Bulk-Bar etc. abdecken via Body-Klasse.
    document.body.classList.add('pp-printing');
    setTimeout(() => {
        window.print();
        setTimeout(() => document.body.classList.remove('pp-printing'), 500);
    }, 50);
}

async function ppAsanaCacheRefresh() {
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/refresh-cache', {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        App.showNotification(j.success ? 'Asana-Cache geleert' : (j.message || 'Fehler'), j.success ? 'success' : 'error');
    } catch (e) { App.showNotification(e.message || 'Verbindungsfehler', 'error'); }
}

async function ppAsanaSyncStatus(btn) {
    const icon = btn?.querySelector('.pp-spin-target');
    if (icon) icon.style.animation = 'pp-spin 1s linear infinite';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/sync-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_id: ppState.activePlanId }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message || 'Sync OK', 'success');
        if ((j.data?.changed || 0) > 0) ppOpenPlan(ppState.activePlanId);
    } catch (e) {
        App.showNotification('Sync: ' + e.message, 'error');
    } finally {
        if (icon) setTimeout(() => icon.style.animation = '', 500);
    }
}

async function ppShowAsanaTaskDetail(taskGid) {
    ppOpenModal('pp-asana-task-modal');
    const body = document.getElementById('pp-asana-task-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/task/' + encodeURIComponent(taskGid));
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const t = j.data.task;
        const stories = j.data.stories || [];
        body.innerHTML = `
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:14px;">
                <span class="material-symbols-rounded" style="color:${t.completed ? 'var(--emerald-600)' : 'var(--slate-400)'};font-size:22px;">${t.completed ? 'check_circle' : 'radio_button_unchecked'}</span>
                <div style="flex:1;">
                    <h3 style="margin:0;color:var(--slate-800);font-size:var(--d-fs-base);">${ppEscape(t.name || '')}</h3>
                    ${t.assignee ? `<div style="font-size:11px;color:var(--slate-500);">Verantwortlich: ${ppEscape(t.assignee.name || '')}</div>` : ''}
                    ${t.due_on ? `<div style="font-size:11px;color:var(--slate-500);">Fällig: ${t.due_on}</div>` : ''}
                </div>
                <a href="${ppEscape(t.permalink_url || '#')}" target="_blank" class="thx-btn thx-btn-secondary thx-btn-small" style="text-decoration:none;">
                    <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span>
                    In Asana
                </a>
            </div>
            ${t.notes ? `<div style="background:var(--slate-50);border-radius:6px;padding:10px;font-size:var(--d-fs-sm);white-space:pre-wrap;margin-bottom:14px;">${ppEscape(t.notes)}</div>` : ''}
            ${stories.length > 0 ? `<div>
                <h4 style="margin:0 0 8px;font-size:var(--d-fs-sm);color:var(--slate-700);">Kommentare (${stories.length})</h4>
                ${stories.map(s => `<div style="padding:6px 10px;background:var(--slate-50);border-radius:6px;margin-bottom:4px;font-size:var(--d-fs-xs);">
                    <strong>${ppEscape(s.created_by?.name || 'Unbekannt')}</strong>
                    <span style="color:var(--slate-400);"> · ${(s.created_at || '').slice(0, 10)}</span>
                    <div style="margin-top:2px;color:var(--slate-700);">${ppEscape(s.text || '')}</div>
                </div>`).join('')}
            </div>` : ''}
        `;
    } catch (e) {
        body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + ppEscape(e.message) + '</div>';
    }
}

async function ppLinkAsanaTask(rowId, taskGid) {
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/link', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_row_id: rowId, task_gid: taskGid }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Verknüpft', 'success');
        ppCloseModal('pp-asana-task-modal');
        // Nur die lokale Zeile aktualisieren + Editor neu rendern, statt ppOpenPlan().
        // Spart einen kompletten Reload und vor allem den Scroll-Sprung nach oben.
        const t = j.data?.task || {};
        const row = ppState.activeRows.find(x => x.id === rowId);
        if (row) {
            row.asana_gid = t.gid || taskGid;
            row.asana_url = t.permalink_url || ('https://app.asana.com/0/0/' + (t.gid || taskGid));
            row.asana_task_name = t.name || '';
        }
        ppRenderEditor();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Asana-Unteraufgaben ins Board importieren ===== */
async function ppOpenSubtaskImport(rowId) {
    const row = ppState.activeRows.find(x => x.id === rowId);
    if (!row || !row.asana_gid) { App.showNotification('Zeile ist mit keinem Asana-Task verknüpft', 'error'); return; }

    const wrap = document.createElement('div');
    wrap.className = 'thx-modal-backdrop';
    wrap.id = 'pp-subtask-modal';
    wrap.style.display = 'flex';
    wrap.onclick = (e) => { if (e.target === wrap) wrap.remove(); };
    wrap.innerHTML = `
        <div class="thx-modal" style="width:640px;max-width:94vw;">
            <div class="thx-modal-header">
                <h3 class="thx-modal-title">Unteraufgaben importieren</h3>
                <button class="thx-modal-close" onclick="document.getElementById('pp-subtask-modal').remove()">&times;</button>
            </div>
            <div class="thx-modal-body pp-modal-body" id="pp-subtask-body">
                <div style="padding:30px;text-align:center;color:var(--slate-400);">Lade Unteraufgaben aus Asana…</div>
            </div>
        </div>`;
    document.body.appendChild(wrap);

    try {
        const r = await fetch(`/api/v1/admin/projektplanner/asana/subtasks?task_gid=${encodeURIComponent(row.asana_gid)}&plan_id=${ppState.activePlanId}`);
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        ppRenderSubtaskImport(rowId, j.data);
    } catch (e) {
        const body = document.getElementById('pp-subtask-body');
        if (body) body.innerHTML = `<div style="padding:24px;color:var(--rose-600);">Fehler: ${ppEscape(e.message)}</div>`;
    }
}

function ppRenderSubtaskImport(rowId, data) {
    const body = document.getElementById('pp-subtask-body');
    if (!body) return;
    const subs = data.subtasks || [];
    if (subs.length === 0) {
        body.innerHTML = `<div style="padding:24px;color:var(--slate-500);">Dieser Task hat keine Unteraufgaben in Asana.</div>`;
        return;
    }
    ppState._subtaskImport = subs; // fuer den Import merken
    const list = subs.map((s, i) => {
        const disabled = s.in_board;
        return `
        <label class="pp-subtask-item" style="display:flex;gap:10px;align-items:flex-start;padding:9px 10px;border-bottom:1px solid var(--slate-100);${disabled ? 'opacity:.55;' : 'cursor:pointer;'}">
            <input type="checkbox" class="pp-subtask-cb" data-i="${i}" ${disabled ? 'disabled' : 'checked'} style="margin-top:3px;">
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;color:var(--slate-800);word-break:break-word;">${ppEscape(s.name || '(ohne Titel)')}</div>
                <div style="font-size:11px;margin-top:2px;display:flex;gap:10px;flex-wrap:wrap;">
                    ${s.completed ? '<span style="color:var(--emerald-600);">✓ in Asana erledigt</span>' : ''}
                    ${s.in_board ? '<span style="color:var(--thoxan-600);">bereits im Board</span>' : '<span style="color:var(--slate-400);">neu</span>'}
                </div>
            </div>
        </label>`;
    }).join('');
    body.innerHTML = `
        <div style="font-size:12px;color:var(--slate-500);margin-bottom:10px;line-height:1.5;">
            <strong>${ppEscape(data.parent_name || '')}</strong><br>
            ${data.total} Unteraufgaben · <strong>${data.missing}</strong> noch nicht im Board. Ausgewählte werden als neue Zeilen direkt unter dieser hinzugefügt (mit Asana-Verknüpfung, Erledigt-Status wird übernommen).
        </div>
        <div style="display:flex;gap:8px;margin-bottom:8px;">
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSubtaskToggleAll(true)">Alle neuen</button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppSubtaskToggleAll(false)">Keine</button>
        </div>
        <div style="max-height:46vh;overflow-y:auto;border:1px solid var(--slate-200);border-radius:8px;">${list}</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
            <button class="thx-btn thx-btn-secondary" onclick="document.getElementById('pp-subtask-modal').remove()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" onclick="ppImportSubtasks(${rowId})">Ausgewählte importieren</button>
        </div>`;
}

function ppSubtaskToggleAll(on) {
    document.querySelectorAll('#pp-subtask-modal .pp-subtask-cb:not(:disabled)').forEach(cb => { cb.checked = on; });
}

async function ppImportSubtasks(rowId) {
    const subs = ppState._subtaskImport || [];
    const chosen = [];
    document.querySelectorAll('#pp-subtask-modal .pp-subtask-cb').forEach(cb => {
        if (cb.checked && !cb.disabled) {
            const s = subs[parseInt(cb.dataset.i)];
            if (s) chosen.push({ gid: s.gid, name: s.name, url: s.url, completed: s.completed });
        }
    });
    if (chosen.length === 0) { App.showNotification('Keine Unteraufgaben ausgewählt', 'error'); return; }
    try {
        const r = await fetch('/api/v1/admin/projektplanner/asana/import-subtasks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ plan_id: ppState.activePlanId, parent_row_id: rowId, subtasks: chosen }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        document.getElementById('pp-subtask-modal')?.remove();
        App.showNotification(j.message || (j.data.created + ' importiert'), 'success');
        await ppOpenPlan(ppState.activePlanId);
    } catch (e) { App.showNotification(e.message, 'error'); }
}

/* ===== Asana-Templates für Description-Autocomplete ===== */
let ppTemplatesCache = null;
async function ppLoadTemplates(force) {
    if (ppTemplatesCache && !force) return ppTemplatesCache;
    try {
        const url = '/api/v1/admin/projektplanner/asana/templates' + (force ? '?refresh=1' : '');
        const r = await fetch(url);
        const j = await r.json();
        ppTemplatesCache = j.success && j.data.configured ? (j.data.templates || []) : [];
    } catch (_) { ppTemplatesCache = []; }
    return ppTemplatesCache;
}

/* Templates-Popover bei Description-Eingabe (≥2 Zeichen) */
let ppTplTimer = null, ppTplOpenRowId = null;
document.addEventListener('input', e => {
    const el = e.target;
    if (!el.classList || !el.classList.contains('pp-edit') || el.dataset.field !== 'description') return;
    clearTimeout(ppTplTimer);
    const text = el.textContent.trim();
    if (text.length < 2) { ppHideTemplates(); return; }
    ppTplTimer = setTimeout(async () => {
        const templates = await ppLoadTemplates();
        if (!templates.length) return;
        const lower = text.toLowerCase();
        const matches = templates.filter(t => t.name && t.name.toLowerCase().includes(lower)).slice(0, 8);
        if (!matches.length) { ppHideTemplates(); return; }
        ppShowTemplates(el, matches);
    }, 250);
});

let ppTplCurrentMatches = [];
function ppShowTemplates(el, matches) {
    const pop = document.getElementById('pp-autocomplete');
    const rect = el.getBoundingClientRect();
    pop.style.left = (rect.left + window.scrollX) + 'px';
    pop.style.top = (rect.bottom + window.scrollY + 4) + 'px';
    pop.style.minWidth = Math.max(280, rect.width) + 'px';
    // Viewport-Flip nach dem Render
    setTimeout(() => ppFlipFixedPopover(pop, rect), 30);
    ppTplOpenRowId = parseInt(el.dataset.id);
    ppTplCurrentMatches = matches; // fuer Pick-Callback per Index
    pop.innerHTML = '<div style="padding:4px 8px;font-size:10px;color:var(--slate-400);text-transform:uppercase;letter-spacing:0.04em;">Asana-Templates</div>' +
        matches.map((t, i) => {
            // Stunden-Extract aus Name (z.B. "Logo-Design // 3.5 Std")
            const hoursMatch = (t.name || '').match(/\/\/\s*([0-9]+(?:[.,][0-9]+)?)\s*(?:Std|h)/i);
            const hours = hoursMatch ? hoursMatch[1].replace(',', '.') : '';
            const cleanName = (t.name || '').replace(/\s*\/\/\s*[0-9.,]+\s*(?:Std|h).*$/i, '');
            return `<div class="pp-ac-item" onclick="ppApplyTemplate(${i})">
                <span style="flex:1;">${ppEscape(cleanName)}</span>
                ${hours ? `<span class="pp-ac-kuerzel">${hours}h</span>` : ''}
            </div>`;
        }).join('');
    pop.style.display = 'block';
}

function ppHideTemplates() {
    const pop = document.getElementById('pp-autocomplete');
    if (pop && ppTplOpenRowId) { pop.style.display = 'none'; ppTplOpenRowId = null; }
}

/** Pick: schreibt die NOTES (Beschreibung) des Asana-Tickets in die Description,
 *  nicht den Titel. Fallback auf Titel, wenn Notes leer. Stunden werden weiterhin
 *  aus dem Titel ("// X Std") extrahiert. */
function ppApplyTemplate(idx) {
    if (!ppTplOpenRowId) return;
    const t = ppTplCurrentMatches[idx];
    if (!t) return;
    const rowId = ppTplOpenRowId;
    // Stunden + Cleanname noch einmal aus dem Titel (fuer den Hours-Fallback)
    const hoursMatch = (t.name || '').match(/\/\/\s*([0-9]+(?:[.,][0-9]+)?)\s*(?:Std|h)/i);
    const hours = hoursMatch ? hoursMatch[1].replace(',', '.') : '';
    const cleanName = (t.name || '').replace(/\s*\/\/\s*[0-9.,]+\s*(?:Std|h).*$/i, '');
    // Description = NOTES aus Asana (Fallback: Cleanname)
    const text = (t.notes && t.notes.trim()) ? t.notes.trim() : cleanName;
    const descEl = document.querySelector(`.pp-edit[data-field="description"][data-id="${rowId}"]`);
    if (descEl) descEl.textContent = text;
    ppSetField(rowId, 'description', text);
    const row = ppState.activeRows.find(r => r.id === rowId);
    if (row && hours && (!row.planned_hours || row.planned_hours == 0)) {
        const phEl = document.querySelector(`.pp-edit[data-field="planned_hours"][data-id="${rowId}"]`);
        if (phEl) phEl.textContent = ppFormatNum(parseFloat(hours));
        ppSetField(rowId, 'planned_hours', parseFloat(hours));
    }
    ppHideTemplates();
}

/* ===== Init ===== */
document.addEventListener('DOMContentLoaded', () => {
    ppRestoreUiState();
    ppInit();
    // SAVE-SAFETY: pending Saves bei jedem Browser-Verlassen, Tab-Wechsel
    // und Sichtbarkeits-Wechsel flushen — sonst gehen 300ms-Debounce-Saves
    // beim Tab-Schließen oder Plan-Wechsel verloren.
    window.addEventListener('beforeunload', () => ppFlushAllPending(false /* keepalive */));
    window.addEventListener('pagehide',     () => ppFlushAllPending(true  /* sendBeacon */));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') ppFlushAllPending(true);
    });
    // Vor JS-internem Navigieren (z.B. Klick auf Sidebar-Plan) → activeElement blurren,
    // damit onblur die letzte Eingabe noch in die Pending-Queue schiebt.
    document.body.addEventListener('mousedown', (e) => {
        const ae = document.activeElement;
        if (ae && ae.classList && ae.classList.contains('pp-edit') && !ae.contains(e.target)) {
            try { ae.blur(); } catch (_) {}
        }
    }, true);
});
</script>
