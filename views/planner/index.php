<?php
/**
 * /tagesplan — 6-Step-Workflow zum Tagesplan.
 *
 * Layout: cm-page Standard (Sidebar links mit Steps + Main rechts).
 *
 * Step 1: Sync (Asana → planner_tasks)
 * Step 2: KI-Vorab-Analyse (Aufwand + Summary + Significance + Empfehlung)
 * Step 3: Vorsortierung (Kanban nach wählbarer Achse)
 * Step 4: Prio schärfen (Liste mit Filter + Inline-Edit)
 * Step 5: Tagesplanung (Kanban: Heute / Morgen / Diese Woche / Pool)
 * Step 6: Tagesplan-Ausgabe (KI baut Sequenz aus dem Heute-Slot)
 */
?>
<?php include __DIR__ . '/../admin/_customer_master_styles.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
/* Tagesplan: cm-page Layout (Sidebar mit Steps + Main mit voller Breite) */
.tp-wrap { display: flex; flex-direction: column; gap: 14px; padding-bottom: 12px; }

/* Sidebar — vertikaler Stepper im Chat-Sidebar-Stil (fein, hell, dezent).
   Breite, Slate-50-Hintergrund und Abbrev-Badges spiegeln /chat. */
#tp-sidebar {
    width: 360px; min-width: 360px;
    transition: width 0.18s ease, min-width 0.18s ease;
    background: var(--slate-50);
    border-right: 1px solid var(--slate-200);
}
#tp-sidebar.is-collapsed { width: 56px; min-width: 56px; }

#tp-sidebar .cm-sb-head {
    padding: 12px 14px;
    background: var(--slate-50);
    border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: center; gap: 8px;
}
#tp-sidebar .cm-sb-title {
    flex: 1;
    font-size: var(--d-fs-sm);
    font-weight: 700;
    color: var(--slate-800);
    text-align: center;
    letter-spacing: 0.01em;
}
#tp-sidebar.is-collapsed .cm-sb-head { padding: 12px 6px; justify-content: center; }
#tp-sidebar.is-collapsed .cm-sb-title { display: none; }

.tp-sb-section-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--slate-400);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 14px 14px 6px;
}
#tp-sidebar.is-collapsed .tp-sb-section-label { display: none; }

.tp-sb { padding: 2px 6px 12px; display: flex; flex-direction: column; gap: 1px; }
.tp-sb-step {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 8px;
    border-radius: 8px;
    cursor: pointer;
    background: transparent;
    border: none;
    color: var(--slate-700);
    text-align: left;
    transition: background 0.1s;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
}
.tp-sb-step:hover { background: rgba(255,255,255,0.7); }
.tp-sb-step.is-active {
    background: #fff;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04);
}
.tp-sb-step-num {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 26px;
    padding: 0 8px;
    background: linear-gradient(135deg, #e6f0fa, #d6e7f5);
    color: var(--thoxan-900);
    font-size: var(--d-fs-xs);
    font-weight: 700;
    border-radius: 6px;
    letter-spacing: 0.4px;
    flex-shrink: 0;
}
.tp-sb-step.is-active .tp-sb-step-num {
    background: linear-gradient(135deg, var(--thoxan-600), var(--thoxan-700));
    color: #fff;
}
.tp-sb-step.is-done .tp-sb-step-num {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}
.tp-sb-step-body { min-width: 0; flex: 1; }
.tp-sb-step-label {
    font-size: var(--d-fs-sm);
    font-weight: 500;
    color: #1e293b;
    line-height: 1.25;
}
.tp-sb-step.is-active .tp-sb-step-label { font-weight: 600; }
.tp-sb-step-sub {
    font-size: 11px;
    color: var(--slate-400);
    margin-top: 2px;
    line-height: 1.3;
}
.tp-sb-step-badge {
    display: inline-flex; align-items: center; justify-content: center;
    margin-left: auto;
    min-width: 22px; height: 20px;
    padding: 0 7px;
    border-radius: 10px;
    background: var(--thoxan-600);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    flex-shrink: 0;
}
.tp-sb-divider {
    height: 1px;
    background: var(--slate-200);
    margin: 10px 14px 4px;
}

/* Collapsed-Modus: nur die Nummer-Badges, alles andere weg */
#tp-sidebar.is-collapsed .tp-sb { padding: 4px; gap: 4px; }
#tp-sidebar.is-collapsed .tp-sb-step { padding: 4px; justify-content: center; gap: 0; }
#tp-sidebar.is-collapsed .tp-sb-step-body,
#tp-sidebar.is-collapsed .tp-sb-step-badge,
#tp-sidebar.is-collapsed .tp-sb-divider { display: none; }
#tp-sidebar.is-collapsed .tp-sb-step-num { min-width: 38px; padding: 0; }
#tp-sidebar.is-collapsed .tp-sb-foot { display: none; }
.tp-sb-foot { padding: 14px 16px 16px; font-size: 11px; color: var(--slate-400); border-top: 1px solid var(--slate-200); margin-top: auto; text-align: center; line-height: 1.4; background: var(--slate-50); }

/* Archiv-Zeilen (Step 7) — kompakte, datums-gruppierte Liste */
.tp-archive-list { display: flex; flex-direction: column; gap: 2px; }
.tp-archive-row {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.1s;
    border: 1px solid transparent;
}
.tp-archive-row:hover { background: var(--slate-50); border-color: var(--slate-200); }
.tp-archive-main { flex: 1; min-width: 0; }
.tp-archive-name { font-size: var(--d-fs-sm); font-weight: 500; color: var(--slate-700); text-decoration: line-through; line-height: 1.3; }
.tp-archive-meta {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    margin-top: 3px; font-size: 11px; color: var(--slate-500);
}
.tp-archive-cust { display: inline-flex; align-items: center; gap: 5px; color: var(--slate-600); }
.tp-archive-cust-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.tp-archive-activity { padding: 1px 8px; border-radius: 8px; background: var(--slate-100); color: var(--slate-600); font-size: 10px; font-weight: 500; }
.tp-archive-eff { font-family: ui-monospace, monospace; font-size: 10px; color: var(--slate-500); }
.tp-archive-when { color: var(--slate-400); margin-left: auto; }
.tp-archive-actions { display: flex; gap: 2px; flex-shrink: 0; }
.tp-archive-actions a, .tp-archive-actions button {
    background: transparent; border: none; padding: 5px 7px; border-radius: 6px;
    color: var(--slate-400); display: inline-flex; cursor: pointer; text-decoration: none;
}
.tp-archive-actions a:hover, .tp-archive-actions button:hover {
    background: var(--slate-100); color: var(--thoxan-700);
}
.tp-archive-actions .material-symbols-rounded { font-size: 17px; }

/* Archiv: Zeitraum-Pills als schlanke Sticky-Bar.
   top: 0 weil sticky-Ankerpunkt relativ zum Scroll-Container (cm-main-inner), nicht zur Topbar.
   margin-bottom: 18px erzeugt klare Trennung zum scrollenden Inhalt darunter. */
/* Archiv-Inhalt liegt in einem eigenen Container OHNE Flex-Gap (die .tp-wrap nutzt gap:14px, der sich
   nicht zuverlaessig pro Element verkleinern laesst). Abstaende kommen hier allein aus Margins —
   so ist der Abstand unter der Sticky-Bar exakt steuerbar und vollstaendig deckbar. */
.tp-archive-scope { display: flex; flex-direction: column; }
.tp-archive-scope > * + * { margin-top: 12px; }
.tp-archive-scope > .tp-archive-pillbar + * { margin-top: 18px; } /* Abstand Bar → Filter-Card */

.tp-archive-pillbar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: #fff;
    padding: 12px 16px;
    border: 1px solid var(--slate-200);
    /* KEIN border-radius: abgerundete Ecken lassen an den 4 Ecken transparente Dreiecke frei,
       durch die beim Scrollen die Umrisse der Cards durchblitzen. Eckig = volle Deckung bis in die Ecke. */
    border-radius: 0;
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    box-shadow: 0 2px 4px rgba(15,23,42,0.06);
}
/* Deckend-weisse Streifen ueber UND unter der gepinnten Bar (cm-main ist #fff): blenden alles aus,
   was sonst beim Scrollen durch die transparenten Margins schiene (oben das cm-main-inner-padding-Band,
   unten der 6px-Abstand zur Filter-Card). left/right ueber die volle Breite, damit keine Rand-Linie bleibt. */
.tp-archive-pillbar::before,
.tp-archive-pillbar::after {
    content: '';
    position: absolute;
    left: -1px; right: -1px;
    background: #fff;
    pointer-events: none;
    z-index: 1;
}
.tp-archive-pillbar::before { bottom: calc(100% + 1px); height: 22px; }
.tp-archive-pillbar::after  { top: calc(100% + 1px);   height: 18px; }
.tp-archive-pillbar .tp-pill-label { color: var(--slate-500); font-size: var(--d-fs-xs); margin-right: 4px; }
.tp-archive-pillbar .tp-pill { white-space: nowrap; }
.tp-archive-pillbar .tp-pill[data-empty="1"] { opacity: 0.4; cursor: default; }

/* Floating Top-Button rechts unten */
.tp-archive-top-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--thoxan-700);
    color: #fff;
    border: none;
    cursor: pointer;
    display: none;
    align-items: center; justify-content: center;
    box-shadow: 0 4px 14px rgba(0,76,155,0.35);
    z-index: 70;
    transition: background 0.15s;
}
.tp-archive-top-btn:hover { background: var(--thoxan-800); }
.tp-archive-top-btn .material-symbols-rounded { font-size: 24px; }
.tp-archive-top-btn.is-visible { display: inline-flex; }

/* Scroll-Offset, damit die geankerten Sektionen nicht unter dem sticky Header verschwinden */
[id^="archiv-"] { scroll-margin-top: calc(var(--topbar-h, 44px) + 240px); }
.tp-card { background: #fff; border: 1px solid var(--slate-200); border-radius: 12px; padding: 18px 20px; }
.tp-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.tp-card-head h2 { margin: 0; font-size: var(--d-fs-lg); flex: 1; min-width: 0; }
.tp-card-head .tp-sub { font-size: var(--d-fs-xs); color: var(--slate-500); }
.tp-empty { padding: 28px; text-align: center; color: var(--slate-500); }

/* Stepper */
.tp-stepper { display: flex; gap: 4px; padding: 4px; background: #fff; border: 1px solid var(--slate-200); border-radius: 12px; overflow-x: auto; }
.tp-step { flex: 1; min-width: 140px; padding: 10px 14px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.12s; color: var(--slate-600); border: none; background: transparent; text-align: left; }
.tp-step:hover { background: var(--slate-50); }
.tp-step.is-active { background: var(--thoxan-700); color: #fff; }
.tp-step.is-done { color: var(--slate-700); }
.tp-step-num { width: 24px; height: 24px; border-radius: 50%; background: var(--slate-200); color: var(--slate-700); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.tp-step.is-active .tp-step-num { background: rgba(255,255,255,0.25); color: #fff; }
.tp-step.is-done .tp-step-num { background: #10b981; color: #fff; }
.tp-step-body { min-width: 0; }
.tp-step-label { font-size: var(--d-fs-sm); font-weight: 600; line-height: 1.1; }
.tp-step-sub { font-size: 10px; opacity: 0.7; margin-top: 1px; line-height: 1.1; }

/* PAT-Setup */
.tp-pat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
.tp-pat-form input { width: 100%; padding: 8px 10px; border: 1px solid var(--slate-200); border-radius: 6px; font-family: ui-monospace, monospace; font-size: var(--d-fs-sm); }
.tp-pat-info { background: var(--slate-50); border-radius: 8px; padding: 12px 14px; font-size: var(--d-fs-sm); line-height: 1.5; color: var(--slate-700); }
.tp-pat-info ol { margin: 6px 0 0 20px; padding: 0; }

/* Sync-Step KPIs */
.tp-kpi-row { display: flex; gap: 10px; flex-wrap: wrap; }
.tp-kpi-card { flex: 1; min-width: 140px; background: var(--slate-50); border-radius: 8px; padding: 12px 14px; }
.tp-kpi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--slate-500); }
.tp-kpi-value { font-size: var(--d-fs-lg); color: var(--slate-800); font-weight: 600; margin-top: 4px; }
.tp-kpi-hint { font-size: 11px; color: var(--slate-500); margin-top: 4px; }

/* Filter-Card */
.tp-filter-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.tp-filter-row + .tp-filter-row { margin-top: 6px; }
.tp-search { flex: 1; min-width: 200px; padding: 6px 10px; border: 1px solid var(--slate-200); border-radius: 6px; font-size: var(--d-fs-sm); background: #fff; }
.tp-pill { padding: 3px 9px; border: 1px solid var(--slate-200); background: #fff; border-radius: 12px; font-size: var(--d-fs-xs); color: var(--slate-700); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.12s; }
.tp-pill:hover { border-color: var(--slate-300); }
.tp-pill.is-active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }
.tp-pill-count { font-size: 10px; opacity: 0.75; font-family: ui-monospace, monospace; }
.tp-pill-label { color: var(--slate-500); font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; margin-right: 4px; }

/* Task-Listen (Step 4) — cleaner Asana-Style */
.tp-list { display: flex; flex-direction: column; gap: 4px; }
.tp-task {
    display: grid;
    grid-template-columns: 20px 1fr auto auto;
    gap: 12px;
    align-items: start;
    padding: 12px 14px;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 10px;
    transition: border-color 0.12s, box-shadow 0.12s;
    cursor: pointer;
    position: relative;
}
.tp-task:hover { border-color: var(--slate-300); box-shadow: 0 1px 6px rgba(15,23,42,0.04); }
.tp-task.is-overdue { border-left: 3px solid #dc2626; }
.tp-task.is-today { border-left: 3px solid #f59e0b; }
.tp-task.is-completed { opacity: 0.5; background: var(--slate-50); }
.tp-task.is-completed .tp-task-name { text-decoration: line-through; }
.tp-task.is-stale { background: #fefce8; }
.tp-task.is-selected { background: #eff6ff; border-color: #93c5fd; }
.tp-task-check { width: 18px; height: 18px; cursor: pointer; margin-top: 2px; }
.tp-task-body { min-width: 0; }
.tp-task-name { font-weight: 600; color: var(--slate-800); font-size: 15px; line-height: 1.35; letter-spacing: -0.005em; }
.tp-task-summary { font-size: var(--d-fs-sm); color: var(--slate-600); margin-top: 4px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.tp-task-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; font-size: 11px; color: var(--slate-500); align-items: center; }
.tp-task-badge { padding: 2px 9px; border-radius: 10px; font-size: 10px; background: var(--slate-100); color: var(--slate-600); white-space: nowrap; line-height: 1.6; }
/* Sekundäre Inline-Info (Projekt, Zeitstempel) — neutraler, kleiner */
.tp-task-inline { font-size: 11px; color: var(--slate-400); }
.tp-task-inline strong { color: var(--slate-600); font-weight: 600; }
.tp-task-rightcol { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; min-width: 64px; }
.tp-task-edit-btn { background: transparent; border: none; cursor: pointer; padding: 3px; color: var(--slate-400); border-radius: 6px; display: inline-flex; }
.tp-task-edit-btn:hover { background: var(--slate-100); color: var(--thoxan-700); }
.tp-task-edit-btn .material-symbols-rounded { font-size: 16px; }
.tp-task-badge.tp-cust { color: #fff; }
.tp-task-badge.tp-overdue { background: #fee2e2; color: #b91c1c; }
.tp-task-badge.tp-today { background: #fef3c7; color: #92400e; }
.tp-task-badge.tp-stale { background: #fef3c7; color: #854d0e; }
.tp-task-badge.tp-asap { background: #fee2e2; color: #b91c1c; }
.tp-task-badge.tp-drop { background: #f1f5f9; color: #64748b; font-style: italic; }
.tp-task-badge.tp-sig-high { background: #dcfce7; color: #166534; }
.tp-task-actions { display: flex; gap: 2px; flex-shrink: 0; align-items: center; }
.tp-task-actions button { background: transparent; border: none; padding: 4px 6px; cursor: pointer; border-radius: 6px; color: var(--slate-400); }
.tp-task-actions button:hover { background: var(--slate-100); color: var(--thoxan-700); }
.tp-task-actions .material-symbols-rounded { font-size: 16px; }
.tp-task-effort { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-500); padding: 2px 6px; border: 1px solid var(--slate-200); border-radius: 6px; cursor: pointer; white-space: nowrap; }
.tp-task-effort:hover { border-color: var(--thoxan-700); color: var(--thoxan-700); }
.tp-task-effort.is-ai { border-style: dashed; }
.tp-task-score { font-family: ui-monospace, monospace; font-size: 10px; color: var(--slate-400); padding: 2px 6px; min-width: 36px; text-align: right; }

/* Kanban */
.tp-kanban { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; }
.tp-kanban-col { flex: 1; min-width: 260px; background: var(--slate-50); border-radius: 10px; padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.tp-step6-board > .tp-kanban-col { min-width: 0; }
.tp-kanban-col-head { display: flex; align-items: center; gap: 8px; padding: 4px 4px 8px; border-bottom: 1px solid var(--slate-200); margin-bottom: 4px; }
.tp-kanban-col-head h3 { margin: 0; font-size: var(--d-fs-sm); color: var(--slate-700); flex: 1; min-width: 0; }
.tp-kanban-col-count { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-500); }
.tp-kanban-col-dot { display: none; }
.tp-kanban-body { display: flex; flex-direction: column; gap: 6px; min-height: 60px; flex: 1; }
.tp-kanban-card { background: #fff; border: 1px solid var(--slate-200); border-radius: 8px; padding: 8px 10px; cursor: grab; transition: box-shadow 0.12s; }
.tp-kanban-card:hover { box-shadow: 0 2px 6px rgba(15,23,42,0.06); }
.tp-kanban-card.sortable-ghost { opacity: 0.4; }
.tp-kanban-card.sortable-chosen { cursor: grabbing; }
.tp-kanban-card-name { font-weight: 600; font-size: var(--d-fs-sm); color: var(--slate-800); line-height: 1.25; }
.tp-kanban-card-meta { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 5px; }
.tp-kanban-card-reason { font-size: 11px; color: var(--slate-500); font-style: italic; margin-top: 6px; padding-top: 5px; border-top: 1px dashed var(--slate-200); line-height: 1.35; }
.tp-cap-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding-top: 6px; border-top: 1px solid var(--slate-100); margin-top: 8px; }
.tp-cap-input { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--slate-600); }
.tp-cap-input input { width: 70px; padding: 4px 6px; border: 1px solid var(--slate-200); border-radius: 5px; font-size: var(--d-fs-xs); font-family: ui-monospace, monospace; }
.tp-kanban-empty { color: var(--slate-400); font-size: 11px; padding: 10px; text-align: center; border: 1px dashed var(--slate-300); border-radius: 6px; }

/* Context-Menu (Rechtsklick) */
.tp-ctx { position: fixed; z-index: 200; background: #fff; border: 1px solid var(--slate-200); border-radius: 8px; box-shadow: 0 4px 16px rgba(15,23,42,0.12); padding: 4px; min-width: 200px; display: none; }
.tp-ctx.is-open { display: block; }
.tp-ctx-item { display: flex; align-items: center; gap: 8px; padding: 7px 10px; cursor: pointer; border-radius: 6px; font-size: var(--d-fs-sm); color: var(--slate-700); }
.tp-ctx-item:hover { background: var(--slate-100); color: var(--thoxan-700); }
.tp-ctx-item.is-danger:hover { background: #fee2e2; color: #b91c1c; }
.tp-ctx-divider { height: 1px; background: var(--slate-200); margin: 4px 0; }

/* Drawer */
.tp-drawer-backdrop { position: fixed; top: var(--topbar-h, 44px); inset-inline: 0; bottom: 0; background: rgba(15,23,42,0.4); z-index: 90; display: none; }
.tp-drawer-backdrop.is-open { display: block; }
.tp-drawer { position: fixed; top: var(--topbar-h, 44px); right: 0; bottom: 0; width: 480px; max-width: 90vw; background: #fff; box-shadow: -2px 0 12px rgba(0,0,0,0.1); z-index: 100; transform: translateX(100%); transition: transform 0.22s; display: flex; flex-direction: column; }
.tp-drawer.is-open { transform: translateX(0); }
.tp-drawer-head { padding: 16px 20px; border-bottom: 1px solid var(--slate-200); display: flex; align-items: center; gap: 8px; }
.tp-drawer-head h3 { margin: 0; font-size: var(--d-fs-md); flex: 1; min-width: 0; }
.tp-drawer-close { background: transparent; border: none; cursor: pointer; padding: 4px; border-radius: 6px; color: var(--slate-500); }
.tp-drawer-body { flex: 1; overflow-y: auto; padding: 16px 20px; }
.tp-drawer-section { margin-bottom: 18px; }
.tp-drawer-section h4 { margin: 0 0 6px; font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--slate-500); font-weight: 600; }
.tp-drawer-notes { white-space: pre-wrap; font-size: var(--d-fs-sm); color: var(--slate-700); line-height: 1.5; background: var(--slate-50); padding: 10px 12px; border-radius: 6px; }
.tp-drawer-footer { padding: 12px 20px; border-top: 1px solid var(--slate-200); display: flex; gap: 8px; flex-wrap: wrap; }
.tp-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

/* Step 6 (Output) */
.tp-seq { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.tp-seq-item { display: flex; gap: 12px; padding: 10px 12px; background: var(--slate-50); border-radius: 8px; align-items: flex-start; border-left: 3px solid var(--thoxan-700); }
.tp-seq-num { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-500); width: 24px; text-align: right; padding-top: 2px; }
.tp-seq-body { flex: 1; min-width: 0; }
.tp-seq-name { font-weight: 600; color: var(--slate-800); font-size: var(--d-fs-sm); }
.tp-seq-reason { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; font-style: italic; }
.tp-seq-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; font-size: 10px; color: var(--slate-600); }
.tp-seq-effort { font-family: ui-monospace, monospace; padding-top: 2px; color: var(--slate-500); font-size: 11px; }
.tp-summary { background: #eff6ff; border-left: 3px solid #2563eb; padding: 10px 14px; border-radius: 6px; font-size: var(--d-fs-sm); color: var(--slate-700); margin-top: 10px; }

.tp-time-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tp-time-btn { padding: 8px 16px; border: 1px solid var(--slate-200); background: #fff; border-radius: 999px; cursor: pointer; font-size: var(--d-fs-sm); color: var(--slate-700); }
.tp-time-btn:hover { border-color: var(--thoxan-700); color: var(--thoxan-700); }
.tp-time-btn.is-active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }

/* Step 6 — motivierende Tagesplan-Ansicht */
.tp-day-card { padding: 22px 24px; }
.tp-day-empty { text-align: center; padding: 30px 20px; }
.tp-day-hero { background: linear-gradient(135deg, #fff 0%, #f8fafc 100%); border-color: var(--thoxan-700); border-width: 2px; padding: 22px 24px; }
.tp-day-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
.tp-day-eyebrow { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--thoxan-700); font-weight: 600; }
.tp-day-progress { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: var(--d-fs-xs); font-weight: 600; margin-top: 6px; }
.tp-primary {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    padding: 20px 22px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);
}
.tp-primary-eyebrow { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-500); margin-bottom: 4px; }
.tp-primary-title { font-size: 22px; line-height: 1.25; margin: 0 0 10px; color: var(--slate-800); font-weight: 700; }
.tp-primary-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.tp-primary-summary { font-size: var(--d-fs-sm); color: var(--slate-700); background: var(--slate-50); padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; line-height: 1.5; }
.tp-primary-reason { font-size: var(--d-fs-sm); color: #2563eb; background: #eff6ff; padding: 8px 12px; border-radius: 6px; margin-bottom: 14px; border-left: 3px solid #2563eb; }
.tp-primary-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
.tp-action-big { padding: 10px 18px !important; font-size: var(--d-fs-base) !important; font-weight: 600 !important; display: inline-flex !important; align-items: center; gap: 6px; }
.tp-action-big .material-symbols-rounded { font-size: 20px !important; }
.tp-action-done { background: #15803d !important; color: #fff !important; border-color: #15803d !important; }
.tp-action-done:hover { background: #166534 !important; }

.tp-uplist { display: flex; flex-direction: column; gap: 6px; }
.tp-up-item { display: grid; grid-template-columns: 28px 1fr auto; gap: 10px; padding: 10px 12px; background: var(--slate-50); border-radius: 8px; align-items: flex-start; }
.tp-up-item:hover { background: var(--slate-100); }
.tp-up-num { font-family: ui-monospace, monospace; color: var(--slate-500); font-size: 11px; padding-top: 2px; }
.tp-up-name { font-weight: 600; font-size: var(--d-fs-sm); color: var(--slate-800); line-height: 1.3; }
.tp-up-meta { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
.tp-up-reason { font-size: 11px; color: var(--slate-500); font-style: italic; margin-top: 4px; }
.tp-up-actions { display: flex; gap: 2px; align-items: center; }
.tp-up-actions button, .tp-up-actions a { background: transparent; border: none; padding: 4px 6px; cursor: pointer; border-radius: 6px; color: var(--slate-400); text-decoration: none; display: inline-flex; }
.tp-up-actions button:hover, .tp-up-actions a:hover { background: var(--slate-200); color: var(--thoxan-700); }
.tp-up-actions .material-symbols-rounded { font-size: 18px; }

.tp-preview-card { background: var(--slate-50); }
.tp-preview-list { display: flex; flex-direction: column; gap: 4px; }
.tp-preview-item { display: grid; grid-template-columns: auto 1fr auto auto; gap: 8px; padding: 6px 10px; align-items: center; font-size: var(--d-fs-sm); color: var(--slate-600); border-radius: 6px; }
.tp-preview-item:hover { background: #fff; }
.tp-preview-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
.tp-preview-eff { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-500); }
.tp-preview-add { background: transparent; border: none; padding: 4px 6px; cursor: pointer; border-radius: 6px; color: var(--slate-400); display: inline-flex; }
.tp-preview-add:hover { background: var(--thoxan-700); color: #fff; }
.tp-preview-add .material-symbols-rounded { font-size: 18px; }

/* Quick-Wins-Card */
.tp-quick-card { background: #f0fdf4; border-left: 3px solid #15803d; }
.tp-quick-list { display: flex; flex-direction: column; gap: 4px; }
.tp-quick-item { display: grid; grid-template-columns: auto 1fr auto auto; gap: 8px; padding: 6px 10px; align-items: center; background: #fff; border-radius: 6px; }
.tp-quick-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: var(--d-fs-sm); color: var(--slate-800); cursor: pointer; }
.tp-quick-eff { font-family: ui-monospace, monospace; font-size: 11px; color: #15803d; font-weight: 600; background: #dcfce7; padding: 2px 8px; border-radius: 4px; }
.tp-quick-item button { background: transparent; border: none; padding: 4px 6px; cursor: pointer; border-radius: 6px; color: var(--slate-400); display: inline-flex; }
.tp-quick-item button:hover { background: #15803d; color: #fff; }
.tp-quick-item button .material-symbols-rounded { font-size: 18px; }

/* Quick-Task-Badge auf Tile / Liste */
.tp-task-badge.tp-quick { background: #dcfce7; color: #15803d; }

/* ===================================================================== */
/* UNIFIED CARD — die EINE Card-Komponente für Step 3, 4, 5, 6           */
/* ===================================================================== */
.tp-c-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 10px;
    padding: 12px 14px;
    display: flex; flex-direction: column; gap: 6px;
    cursor: grab;
    transition: border-color 0.12s, box-shadow 0.12s;
    position: relative;
    min-height: 78px;
}
.tp-c-card:hover { border-color: var(--slate-300); box-shadow: 0 1px 4px rgba(15,23,42,0.04); }
.tp-c-card:active, .tp-c-card.sortable-chosen { cursor: grabbing; }
.tp-c-card.sortable-ghost { opacity: 0.4; }
/* Akzent-Linie ist Standard fast unsichtbar — Überfällig nur per Text, keine rote Border
   (sonst wird die ganze Seite rot, weil 80% der Tasks überfällig sind). */
.tp-c-card.is-overdue { border-color: var(--slate-200); }
.tp-c-card.is-stale { background: #fafafa; border-style: dashed; }
.tp-c-card.is-selected { background: var(--slate-50); border-color: var(--slate-400); }
.tp-c-card.is-completed { opacity: 0.45; }
.tp-c-card.is-completed .tp-c-title { text-decoration: line-through; }
/* Warten-Zustand: Ball liegt nicht bei mir, Karte deutlich gedämpft aber lesbar. */
.tp-c-card.is-waiting { background: #fafafa; opacity: 0.7; }
.tp-c-card.is-waiting .tp-c-title { color: var(--slate-600); font-weight: 500; }
/* Signal-Zustand: Auto-Wake nach Asana-Aktivität — Aufmerksamkeit auf die Karte ziehen. */
.tp-c-card.is-signal { border-color: var(--thoxan-400); box-shadow: 0 0 0 2px rgba(0,76,155,0.08); }

/* Warten-/Signal-Banner über dem Titel */
.tp-c-status-banner {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
}
.tp-c-status-banner .material-symbols-rounded { font-size: 14px; }
.tp-c-status-banner.is-waiting { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.tp-c-status-banner.is-waiting .material-symbols-rounded { color: #c2410c; }
.tp-c-ball-name { font-weight: 700; color: #7c2d12; }
.tp-c-status-banner.is-signal {
    background: #fef3c7; color: #92400e;
    cursor: pointer;
    font-weight: 600;
}
.tp-c-status-banner.is-signal:hover { background: #fde68a; }
.tp-c-status-banner.is-reanalyzed {
    background: var(--thoxan-50); color: var(--thoxan-800);
    cursor: pointer;
    font-weight: 500;
}
.tp-c-status-banner.is-reanalyzed:hover { background: var(--thoxan-100); }
.tp-c-status-banner.is-reanalyzed .material-symbols-rounded { color: var(--thoxan-700); }

/* Warten-Modal: Picker mit Team-Liste + Kunden-Kontakte + Freitext */
.tp-waiting-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(15,23,42,0.5);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 24px;
}
.tp-waiting-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%; max-width: 560px;
    max-height: 80vh;
    display: flex; flex-direction: column;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
}
.tp-wm-head {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--slate-200);
}
.tp-wm-head h3 { margin: 0; flex: 1; font-size: var(--d-fs-md); color: var(--slate-800); }
.tp-wm-head .material-symbols-rounded { color: #c2410c; font-size: 22px; }
.tp-wm-close { background: transparent; border: none; cursor: pointer; padding: 4px; border-radius: 6px; color: var(--slate-500); }
.tp-wm-close:hover { background: var(--slate-100); color: var(--slate-800); }
.tp-wm-body { padding: 16px 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 14px; }
.tp-wm-section-label {
    font-size: 10px; font-weight: 700;
    color: var(--slate-400);
    letter-spacing: 0.08em; text-transform: uppercase;
}
.tp-wm-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.tp-wm-chip {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 12px;
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-size: var(--d-fs-sm);
    color: var(--slate-800);
    text-align: left;
}
.tp-wm-chip:hover { background: var(--thoxan-50); border-color: var(--thoxan-300); color: var(--thoxan-800); }
.tp-wm-chip .material-symbols-rounded { font-size: 16px; color: var(--slate-500); }
.tp-wm-abbr {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 22px;
    padding: 0 6px;
    background: linear-gradient(135deg, #e6f0fa, #d6e7f5);
    color: var(--thoxan-900);
    font-size: 10px; font-weight: 700;
    border-radius: 5px; letter-spacing: 0.3px;
}
.tp-wm-funktion { color: var(--slate-500); font-size: 11px; }
.tp-wm-input {
    width: 100%; padding: 10px 12px;
    border: 1px solid var(--slate-300);
    border-radius: 8px;
    font-size: var(--d-fs-sm);
    font-family: inherit;
    box-sizing: border-box;
}
.tp-wm-input:focus { outline: none; border-color: var(--thoxan-600); box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
.tp-wm-foot {
    display: flex; gap: 8px; justify-content: flex-end;
    padding: 12px 20px;
    border-top: 1px solid var(--slate-200);
}

/* Watchdog-Liste (Phase 5 Beobachten) */
.tp-waiting-sortbar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
.tp-waiting-list { display: flex; flex-direction: column; gap: 4px; }
.tp-waiting-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--slate-200);
    background: #fff;
    transition: background 0.1s;
}
.tp-waiting-row:hover { background: var(--slate-50); }
.tp-waiting-main { flex: 1; min-width: 0; }
.tp-waiting-name { font-size: var(--d-fs-sm); font-weight: 600; color: var(--slate-800); line-height: 1.3; }
.tp-waiting-meta {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-top: 4px;
    font-size: 11px; color: var(--slate-600);
}
.tp-waiting-actions { display: flex; gap: 6px; flex-shrink: 0; }
.tp-waiting-actions .thx-btn { padding: 6px 10px !important; font-size: 11px !important; }
.tp-waiting-actions .thx-btn .material-symbols-rounded { font-size: 16px; }
.tp-waiting-due { color: var(--slate-600); }
.tp-waiting-due.is-overdue { color: #b91c1c; font-weight: 600; }
.tp-waiting-due.is-today { color: #92400e; font-weight: 600; }
.tp-waiting-due.is-soon { color: var(--slate-700); }
.tp-waiting-due.is-undated { color: var(--slate-400); font-style: italic; }
/* Gedämpfte Variante (Step 6 für Spalten Quick/Morgen/Woche) — kompakt, passt in 180-220px */
.tp-c-card.is-muted {
    background: #fff;
    border-color: var(--slate-200);
    padding: 8px 10px;
    min-height: 0;
    min-width: 0;
    gap: 3px;
    overflow: hidden;
}
.tp-c-card.is-muted .tp-c-head { gap: 4px; }
.tp-c-card.is-muted .tp-c-title { font-size: 12px; font-weight: 500; color: var(--slate-700); -webkit-line-clamp: 2; line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
.tp-c-card.is-muted .tp-c-summary { display: none; }
.tp-c-card.is-muted .tp-c-cust { color: var(--slate-600); padding: 0; font-size: 10px; max-width: 100%; }
.tp-c-card.is-muted .tp-c-cust > span:last-child { font-size: 10px; }
.tp-c-card.is-muted .tp-c-due { font-size: 10px; padding: 0; }
.tp-c-card.is-muted .tp-c-eff { font-size: 10px; padding: 1px 5px; }
.tp-c-card.is-muted .tp-c-badges,
.tp-c-card.is-muted .tp-c-score,
.tp-c-card.is-muted .tp-c-reason,
.tp-c-card.is-muted .tp-c-order { display: none; }
.tp-c-card.is-muted .tp-c-actions { padding-top: 2px; }
.tp-c-card.is-muted .tp-c-actions button .material-symbols-rounded,
.tp-c-card.is-muted .tp-c-actions a .material-symbols-rounded { font-size: 14px; }

/* Hero (Step 6 Heute-Tasks) — voller Look, mehr Info, neutraler Border (kein Thoxan-Blau,
   sonst zieht jede Heute-Karte die Aufmerksamkeit auf den Rahmen statt auf den Inhalt). */
.tp-c-card.is-hero {
    border: 1px solid var(--slate-300);
    padding: 16px 18px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04);
}
.tp-c-card.is-hero .tp-c-title { font-size: 16px; font-weight: 700; line-height: 1.3; }
/* Erste Heute-Karte (focus) — etwas größer */
.tp-c-card.is-hero.is-focus { padding: 20px 22px; border-color: var(--slate-400); }
.tp-c-card.is-hero.is-focus .tp-c-title { font-size: 18px; }

/* Head: Kunde + Deadline links, Aufwand rechts */
.tp-c-head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.tp-c-head-spacer { flex: 1; }

/* Kunde — neutral. Farbe nur als kleiner 6px-Punkt, Name in Slate.
   Damit ist die Karte nicht mehr bunt, der Kunde aber trotzdem identifizierbar. */
.tp-c-cust {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0;
    font-size: 11px;
    color: var(--slate-600);
    font-weight: 500;
    max-width: 220px;
    overflow: hidden;
    background: transparent;
}
.tp-c-cust > span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tp-c-cust .tp-c-cust-abbr {
    /* Farb-Akzent als kleiner Punkt — Farbe kommt via inline-style mit !important */
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    font-size: 0;
    color: transparent;
    overflow: hidden;
}

/* Deadline — neutral, nur überfällig/heute mit Farbe */
.tp-c-due {
    font-size: 11px;
    padding: 1px 8px; border-radius: 8px;
    background: transparent; color: var(--slate-500);
    font-weight: 500;
}
.tp-c-due.is-overdue { background: transparent; color: #b91c1c; font-weight: 600; }
.tp-c-due.is-today { background: transparent; color: var(--slate-700); font-weight: 600; }

/* Aufwand — neutral, klein */
.tp-c-eff {
    font-family: ui-monospace, monospace;
    font-size: 11px;
    padding: 2px 8px;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    cursor: pointer;
    color: var(--slate-600);
    background: #fff;
    transition: all 0.12s;
    white-space: nowrap;
}
.tp-c-eff:hover { border-color: var(--thoxan-700); color: var(--thoxan-700); }
.tp-c-eff.is-ai { border-style: dashed; color: var(--slate-400); }

/* Title */
.tp-c-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--slate-800);
    line-height: 1.35;
    margin: 0;
}

/* KI-Summary */
.tp-c-summary {
    font-size: 12px;
    color: var(--slate-500);
    line-height: 1.45;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
/* Hero zeigt mehr Text */
.tp-c-card.is-hero .tp-c-summary { -webkit-line-clamp: 4; line-clamp: 4; font-size: var(--d-fs-sm); color: var(--slate-600); }

/* Badges-Reihe — kompakt, sparsam */
.tp-c-badges { display: flex; gap: 4px; flex-wrap: wrap; }
.tp-c-badge {
    font-size: 10px;
    padding: 1px 7px;
    border-radius: 8px;
    background: var(--slate-100);
    color: var(--slate-600);
    white-space: nowrap;
    font-weight: 500;
}
.tp-c-badge.is-quick { background: transparent; color: #047857; padding-left: 0; padding-right: 0; }
.tp-c-badge.is-recurring { background: transparent; color: var(--thoxan-700); padding-left: 0; padding-right: 0; font-weight: 500; }
.tp-c-badge.is-asap { background: transparent; color: #b91c1c; font-weight: 600; padding-left: 0; padding-right: 0; }
.tp-c-badge.is-drop { background: transparent; color: var(--slate-400); font-style: italic; padding-left: 0; padding-right: 0; }
.tp-c-badge.is-stale { background: transparent; color: var(--slate-500); padding-left: 0; padding-right: 0; }
.tp-c-badge.is-sig { background: transparent; color: var(--slate-700); padding-left: 0; padding-right: 0; }
.tp-c-badge.is-toad { background: #dcfce7; color: #166534; font-weight: 700; }
.tp-c-badge.is-deviation { background: #fef3c7; color: #92400e; }
.tp-c-card.is-toad-card { box-shadow: 0 0 0 2px #16a34a, 0 1px 3px rgba(0,0,0,0.08); }

/* KI-Begründung (Step 5) — dezent, kein Blau-Block mehr */
.tp-c-reason {
    font-size: 11px; color: var(--slate-600);
    background: transparent;
    padding: 4px 0 0 0;
    border-top: 1px dashed var(--slate-200);
    margin-top: 2px;
    line-height: 1.4;
    display: flex; gap: 6px; align-items: flex-start;
}
.tp-c-reason .material-symbols-rounded { font-size: 13px; color: var(--slate-400); flex-shrink: 0; margin-top: 1px; }

/* Bulk-Checkbox */
.tp-c-check {
    width: 16px; height: 16px;
    cursor: pointer;
    flex-shrink: 0;
}

/* Score (sehr dezent) — nur in nicht-muted Cards */
.tp-c-score {
    position: absolute;
    top: 8px; right: 38px;
    font-family: ui-monospace, monospace;
    font-size: 9px;
    color: var(--slate-300);
    pointer-events: none;
}
.tp-c-card.is-hero .tp-c-score { top: 12px; right: 50px; }

/* Position-Nummer (1, 2, 3 …) in Step 5/6 */
.tp-c-pos {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: var(--slate-100);
    color: var(--slate-600);
    font-family: ui-monospace, monospace;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}
.tp-c-card.is-hero .tp-c-pos {
    background: var(--thoxan-700); color: #fff;
    width: 28px; height: 28px; font-size: 14px;
}
.tp-c-card.is-muted .tp-c-pos { background: transparent; color: var(--slate-400); width: auto; height: auto; padding: 0 2px; }

/* Pfeil-Buttons für manuelle Sortierung */
.tp-c-order {
    position: absolute;
    top: 4px; right: 4px;
    display: flex; flex-direction: column;
    opacity: 0;
    transition: opacity 0.12s;
}
.tp-c-card:hover .tp-c-order,
.tp-c-card.is-hero .tp-c-order { opacity: 1; }
.tp-c-order-btn {
    background: transparent; border: none; cursor: pointer;
    padding: 0; width: 22px; height: 14px;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--slate-400);
    border-radius: 4px;
}
.tp-c-order-btn:hover { background: var(--slate-100); color: var(--thoxan-700); }
.tp-c-order-btn .material-symbols-rounded { font-size: 20px; }

/* Action-Icons unten rechts */
.tp-c-actions {
    display: flex; gap: 2px;
    margin-top: auto;
    padding-top: 4px;
    justify-content: flex-end;
}
.tp-c-actions button, .tp-c-actions a {
    background: transparent; border: none;
    padding: 5px 7px;
    border-radius: 6px;
    cursor: pointer;
    color: var(--slate-400);
    display: inline-flex; align-items: center;
    text-decoration: none;
}
.tp-c-actions button:hover, .tp-c-actions a:hover {
    background: var(--slate-100); color: var(--thoxan-700);
}
.tp-c-actions .material-symbols-rounded { font-size: 17px; }

/* (i)-Info-Button: überall etwas größer und auffälliger, weil das jetzt der
   EINZIGE Weg ist die Detail-Ansicht zu öffnen (Karten-Klick tut nichts mehr). */
.tp-c-actions .tp-c-info-btn {
    padding: 6px 9px;
    background: var(--slate-100);
    color: var(--slate-600);
}
.tp-c-actions .tp-c-info-btn:hover {
    background: var(--thoxan-100);
    color: var(--thoxan-800);
}
.tp-c-actions .tp-c-info-btn .material-symbols-rounded { font-size: 20px; }
/* In Muted-Karten (Quick/Morgen/Woche) bleibt der Button gut sichtbar — kein
   verkleinern via is-muted-Override. */
.tp-c-card.is-muted .tp-c-actions .tp-c-info-btn { padding: 5px 8px; }
.tp-c-card.is-muted .tp-c-actions .tp-c-info-btn .material-symbols-rounded { font-size: 18px; }

/* Hero-Actions (Step 6 erste Task): große Buttons */
.tp-c-actions-hero {
    display: flex; gap: 8px;
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid var(--slate-200);
    flex-wrap: wrap;
}
.tp-c-actions-hero .thx-btn {
    padding: 9px 16px !important;
    font-size: var(--d-fs-sm) !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
}
.tp-c-actions-hero .material-symbols-rounded { font-size: 18px !important; }
.tp-c-action-done { background: var(--slate-100) !important; color: var(--slate-700) !important; border-color: var(--slate-200) !important; }
.tp-c-action-done:hover { background: #15803d !important; color: #fff !important; border-color: #15803d !important; }

/* Step 6 Kanban: Heute-Spalte deutlich breiter, andere kompakt + grau.
   Eigene Klasse (NICHT tp-kanban als Basis) damit es keine Konflikte mit flex-Default gibt. */
/* Pivot-Toggle (Step 6 Heute-Spalte: Liste / nach Kunde / nach Aktivität) */
.tp-pivot-toggle { display: inline-flex; gap: 0; background: var(--slate-100); border-radius: 8px; padding: 2px; margin-left: auto; }
.tp-pivot-btn { background: transparent; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; color: var(--slate-600); font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
.tp-pivot-btn .material-symbols-rounded { font-size: 14px; }
.tp-pivot-btn.is-active { background: #fff; color: var(--slate-800); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
.tp-pivot-btn:hover:not(.is-active) { color: var(--slate-800); }

/* Bündel-Gruppen in der Heute-Spalte (Pivot-Modus) */
.tp-bundle { margin-bottom: 16px; }
.tp-bundle:last-child { margin-bottom: 0; }
.tp-bundle-head { display: flex; align-items: baseline; gap: 10px; padding: 6px 4px 8px; border-bottom: 1px solid var(--slate-200); margin-bottom: 10px; }
.tp-bundle-label { font-weight: 600; color: var(--slate-700); font-size: 14px; flex-shrink: 0; }
.tp-bundle-meta { font-size: 11px; color: var(--slate-500); font-family: ui-monospace, monospace; }
.tp-bundle-cust-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
.tp-bundle-body { display: flex; flex-direction: column; gap: 10px; }

/* Cross-Pivot: Bündel-Liste über alle Slots hinweg (eine Spalte, weite Karten) */
.tp-bundles-cross { display: flex; flex-direction: column; gap: 22px; max-width: 1100px; margin: 0 auto; }
.tp-bundles-cross .tp-bundle-head { padding-bottom: 10px; border-bottom: 1px solid var(--slate-300); }
.tp-bundles-cross .tp-bundle-label { font-size: 16px; }
.tp-bundles-cross .tp-bundle-meta { margin-left: 4px; }
.tp-bundles-cross .tp-bundle-meta .tp-meta-sep { color: var(--slate-300); margin: 0 6px; }

/* Slot-Badge auf Karte (Cross-Pivot-Modus) */
.tp-c-slot {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 8px;
    background: var(--slate-100);
    color: var(--slate-700);
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}
.tp-c-slot.tp-c-slot-today    { background: var(--slate-800); color: #fff; }
.tp-c-slot.tp-c-slot-tomorrow { background: var(--slate-200); color: var(--slate-700); }
.tp-c-slot.tp-c-slot-this_week { background: var(--slate-100); color: var(--slate-600); }
.tp-c-account {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 6px;
    background: transparent;
    border: 1px solid currentColor;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
    letter-spacing: 0.02em;
    opacity: 0.85;
}

/* Phase 7: eine horizontal scrollbare Reihe; 'Heute eingeplant' links bleibt sticky stehen. */
.tp-step6-board { display: flex !important; flex-wrap: nowrap; gap: 12px; overflow-x: auto; padding-bottom: 8px; }
.tp-step6-board > .tp-kanban-col { flex: 0 0 300px; min-width: 300px !important; max-width: 300px; }
.tp-step6-board > .tp-col-today {
    flex: 0 0 420px; min-width: 420px !important; max-width: 420px;
    position: sticky; left: 0; z-index: 5;
    background: #fff;
    box-shadow: 6px 0 10px -4px rgba(15,23,42,0.12);
}
.tp-step6-board > .tp-kanban-col > .tp-kanban-body { min-width: 0; }
.tp-step6-board .tp-c-card { min-width: 0; max-width: 100%; box-sizing: border-box; }
.tp-col-today { background: #fff; border: 1px solid var(--slate-200); border-radius: 12px; padding: 14px; }
.tp-col-today .tp-kanban-col-head h3 { color: var(--thoxan-700); font-size: var(--d-fs-md); }
.tp-col-today .tp-kanban-body { gap: 10px; }
.tp-col-muted { background: var(--slate-50); border: 1px solid var(--slate-100); border-radius: 12px; padding: 12px; }
.tp-col-muted .tp-kanban-col-head { padding-bottom: 6px; margin-bottom: 6px; }
.tp-col-muted .tp-kanban-col-head h3 { color: var(--slate-500); font-weight: 500; font-size: var(--d-fs-sm); }
.tp-col-muted .tp-kanban-col-count { color: var(--slate-400); }
.tp-col-muted .tp-kanban-col-dot { opacity: 0.5; }
.tp-col-muted .tp-kanban-body { gap: 4px; }

/* Konfetti-Overlay beim Erledigt */
.tp-confetti-overlay {
    position: fixed; inset: 0; pointer-events: none; z-index: 1000;
    display: none; align-items: center; justify-content: center;
}
.tp-confetti-overlay.is-open { display: flex; }
.tp-confetti-card {
    background: #fff; border-radius: 16px; padding: 30px 40px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    text-align: center; max-width: 420px; pointer-events: auto;
    animation: tp-pop 0.32s ease-out;
}
.tp-confetti-title { font-size: 22px; font-weight: 700; color: var(--slate-800); margin: 8px 0; }
.tp-confetti-sub { color: var(--slate-600); font-size: var(--d-fs-sm); margin-bottom: 16px; }
.tp-confetti-emoji { font-size: 48px; line-height: 1; }
@keyframes tp-pop {
    0%   { transform: scale(0.85); opacity: 0; }
    60%  { transform: scale(1.04); }
    100% { transform: scale(1); opacity: 1; }
}
.tp-confetti-piece {
    position: absolute; width: 10px; height: 14px; border-radius: 2px;
    animation: tp-fall 1.6s ease-out forwards;
}
@keyframes tp-fall {
    0%   { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
    100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
}

/* Gamification: Score-Badge in der Sidebar */
.tp-score-badge {
    margin: 0 10px 8px; padding: 8px 10px;
    border: 1px solid var(--slate-200); border-radius: 10px;
    background: linear-gradient(135deg, #fff, var(--thoxan-50));
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: border-color 0.12s, box-shadow 0.12s;
}
.tp-score-badge:hover { border-color: var(--thoxan-400); box-shadow: 0 2px 6px rgba(0,76,155,0.08); }
.tp-score-pts { font-weight: 700; color: var(--thoxan-700); font-size: var(--d-fs-md); line-height: 1; }
.tp-score-pts small { font-weight: 600; font-size: 10px; color: var(--slate-500); margin-left: 1px; }
.tp-score-col { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.tp-score-line { font-size: 10px; color: var(--slate-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tp-score-flame { font-size: var(--d-fs-sm); white-space: nowrap; }
#tp-sidebar.is-collapsed .tp-score-badge { margin: 0 6px 6px; padding: 6px; justify-content: center; }
#tp-sidebar.is-collapsed .tp-score-col, #tp-sidebar.is-collapsed .tp-score-flame { display: none; }

/* Gamification: Wochenrueckblick-Modal */
.tp-week-backdrop {
    position: fixed; inset: 0; z-index: 1001; display: none;
    align-items: center; justify-content: center;
    background: rgba(15,23,42,0.45); padding: 20px;
}
.tp-week-backdrop.is-open { display: flex; }
.tp-week-card {
    background: #fff; border-radius: 16px; padding: 24px 28px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    max-width: 540px; width: 100%; max-height: 88vh; overflow-y: auto;
    animation: tp-pop 0.28s ease-out;
}
.tp-week-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 4px; }
.tp-week-title { font-size: 20px; font-weight: 700; color: var(--slate-800); }
.tp-week-spell { color: var(--slate-600); font-size: var(--d-fs-sm); margin: 10px 0 16px; }
.tp-week-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
.tp-week-stat { background: var(--slate-50); border-radius: 10px; padding: 12px; text-align: center; }
.tp-week-stat-val { font-size: 22px; font-weight: 700; color: var(--thoxan-700); line-height: 1.1; }
.tp-week-stat-lbl { font-size: 10px; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 4px; }
.tp-week-bars { display: flex; align-items: flex-end; gap: 6px; height: 70px; margin: 6px 0 4px; }
.tp-week-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; justify-content: flex-end; }
.tp-week-bar { width: 100%; border-radius: 4px 4px 0 0; background: var(--thoxan-400); min-height: 2px; transition: height 0.2s; }
.tp-week-bar.is-today { background: var(--thoxan-700); }
.tp-week-bar-day { font-size: 10px; color: var(--slate-500); }
.tp-week-facts { display: flex; flex-direction: column; gap: 6px; margin: 14px 0; }
.tp-week-fact { display: flex; align-items: center; gap: 8px; font-size: var(--d-fs-sm); color: var(--slate-700); }
.tp-week-fact .material-symbols-rounded { font-size: 18px; color: var(--thoxan-600); }

/* Lernschleife: Regel-Review-Panel */
.tp-learn-list { display: flex; flex-direction: column; gap: 6px; }
.tp-learn-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border: 1px solid var(--slate-200); border-radius: 8px; background: #fff;
}
.tp-learn-row.is-active { border-color: var(--emerald-300, #6ee7b7); background: var(--emerald-50, #ecfdf5); }
.tp-learn-main { flex: 1; min-width: 0; }
.tp-learn-text { font-size: var(--d-fs-sm); color: var(--slate-800); line-height: 1.35; }
.tp-learn-hint { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 3px; }
.tp-learn-chip {
    display: inline-block; color: #fff; font-size: 10px; font-weight: 700;
    padding: 1px 7px; border-radius: 8px; vertical-align: middle; margin-right: 2px;
    text-transform: uppercase; letter-spacing: 0.03em;
}
.tp-learn-support { font-family: ui-monospace, monospace; font-size: 11px; color: var(--slate-400); }
.tp-learn-active { color: var(--emerald-600, #059669); font-size: var(--d-fs-xs); font-weight: 600; white-space: nowrap; }
.tp-learn-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

.tp-unclear-list { display: flex; flex-direction: column; gap: 6px; }
.tp-unclear-row { display: grid; grid-template-columns: auto 1fr auto auto; gap: 10px; align-items: center; padding: 8px 12px; background: var(--slate-50); border-radius: 8px; }
.tp-unclear-row.is-selected { background: #eff6ff; outline: 1px solid #93c5fd; }
.tp-unclear-check { width: 16px; height: 16px; cursor: pointer; }
.tp-unclear-name { font-size: var(--d-fs-sm); color: var(--slate-800); font-weight: 500; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
.tp-unclear-proj { font-size: 11px; color: var(--slate-500); white-space: nowrap; }
.tp-unclear-select { padding: 5px 8px; border: 1px solid var(--slate-300); border-radius: 6px; font-size: var(--d-fs-xs); background: #fff; cursor: pointer; min-width: 220px; }
.tp-unclear-select:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px var(--thoxan-100); }
.tp-unclear-row.is-done { opacity: 0.4; pointer-events: none; }
.tp-bulkbar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: var(--thoxan-700); color: #fff;
    border-radius: 8px; margin-bottom: 10px;
}
.tp-bulkbar-count { font-weight: 600; font-size: var(--d-fs-sm); }
.tp-bulkbar button, .tp-bulkbar select {
    padding: 6px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.1); color: #fff; font-size: var(--d-fs-xs); cursor: pointer;
}
.tp-bulkbar button:hover, .tp-bulkbar select:hover { background: rgba(255,255,255,0.2); }
.tp-bulkbar select { background: #fff; color: var(--slate-800); }
.tp-bulkbar .tp-bulkbar-cancel { margin-left: auto; opacity: 0.8; }
.tp-bulkbar-sticky { position: sticky; top: 0; z-index: 50; flex-wrap: wrap; }
.tp-bulkbar-group { display: inline-flex; align-items: center; gap: 4px; padding: 0 8px; border-left: 1px solid rgba(255,255,255,0.2); }
.tp-bulkbar-group:first-of-type { border-left: none; }
.tp-bulkbar-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; opacity: 0.75; margin-right: 2px; }

/* Step 4 Task-Row: zwei Checkboxen nebeneinander (Bulk-Sel + Complete) */
.tp-task.is-selected { background: #eff6ff; border-color: #93c5fd; }

/* Effort-Popover */
.tp-effort-popover {
    position: fixed; z-index: 200; background: #fff; border: 1px solid var(--slate-200);
    border-radius: 10px; box-shadow: 0 8px 24px rgba(15,23,42,0.16); padding: 10px;
    width: 300px;
}
.tp-popover-head { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 600; margin-bottom: 8px; }
.tp-popover-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-bottom: 8px; }
.tp-pop-btn { padding: 7px 6px; border: 1px solid var(--slate-200); background: #fff; border-radius: 6px; cursor: pointer; font-size: var(--d-fs-xs); font-family: ui-monospace, monospace; color: var(--slate-700); }
.tp-pop-btn:hover { border-color: var(--thoxan-700); color: var(--thoxan-700); }
.tp-pop-btn.is-active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }
.tp-popover-foot { display: flex; gap: 4px; padding-top: 8px; border-top: 1px solid var(--slate-100); }
.tp-popover-custom { flex: 1; padding: 5px 8px; border: 1px solid var(--slate-200); border-radius: 6px; font-size: var(--d-fs-xs); }
.tp-pop-clear { padding: 5px 10px; border: 1px solid var(--slate-200); background: #fff; border-radius: 6px; cursor: pointer; font-size: var(--d-fs-xs); color: var(--slate-600); }

[x-cloak] { display: none !important; }
</style>

<div class="cm-page">
    <aside class="cm-sidebar" id="tp-sidebar">
        <div class="cm-sb-head">
            <div class="cm-sb-title">Tagesplan</div>
            <button class="cm-sb-toggle" onclick="tpToggleSidebar()" title="Sidebar ein-/ausklappen" type="button" aria-label="Sidebar ein-/ausklappen">
                <span class="material-symbols-rounded" id="tp-sb-toggle-icon">menu_open</span>
            </button>
        </div>
        <div class="tp-score-badge" id="tp-score-badge" onclick="tpOpenWeekReview()" title="Heutiger Punktestand — klicken für den Wochenrückblick" hidden></div>
        <div class="tp-sb" id="tp-sb-steps">
            <div class="tp-empty" style="padding:16px;">Lädt…</div>
        </div>
        <div class="tp-sb-foot">In 6 Schritten von Asana zum sortierten Arbeitstag — Archiv zeigt das Erledigte.</div>
    </aside>
    <main class="cm-main">
        <div class="cm-main-inner">
            <div class="tp-wrap" id="tp-root">
                <div class="tp-empty">
                    <span class="material-symbols-rounded" style="font-size:36px;color:var(--slate-300);">hourglass_top</span>
                    <div style="margin-top:8px;">Lädt…</div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Drawer + Context-Menu -->
<div class="tp-drawer-backdrop" id="tp-drawer-bg" onclick="tpCloseDrawer()"></div>
<aside class="tp-drawer" id="tp-drawer"></aside>
<div class="tp-ctx" id="tp-ctx"></div>
<div class="tp-confetti-overlay" id="tp-confetti"></div>
<div class="tp-week-backdrop" id="tp-week-modal" onclick="if(event.target===this)tpCloseWeekReview()"></div>

<script>
(function () {
    const STEPS = [
        { n: 1, label: 'Sync',          sub: 'Asana laden' },
        { n: 2, label: 'KI-Analyse',    sub: 'Aufwand + Bedeutung' },
        { n: 3, label: 'Vorsortierung', sub: 'Kanban-Buckets' },
        { n: 4, label: 'Prio schärfen',sub: 'Liste + Inline' },
        { n: 5, label: 'Beobachten',    sub: 'Wartende Tasks im Blick' },
        { n: 6, label: 'Tagesplanung',  sub: 'Heute/Morgen/Woche' },
        { n: 7, label: 'Tagesplan',     sub: 'KI-Output' },
        { n: 8, label: 'Archiv',        sub: 'Erledigte Tasks' },
    ];
    let state = {
        currentStep: 1,
        hasPat: false, tasks: [], pat: null,
        syncing: false, analyzing: false, planning: false, plan: null,
        planMinutes: 120,
        filter: { search: '', customer: 'all', effort: 'all', bucket: [], stale: 'all', sig: 'all', quick: 'all', overdue: 'all', deviation: 'all', recurring: 'all', started: 'all', signal: 'all', noeffort: 'all', hotcustomer: 'all', context: 'all', complexity: 'all', activity: 'all', completedRange: 'm3', waiting: 'hide' },
        kanbanAxis: 'bucket', // 'bucket' | 'effort' | 'priority' | 'customer'
        step6Pivot: 'list',     // 'list' | 'customer' | 'activity' — Bündelung in Heute-Spalte
    };

    // Einheitliches 8-Stufen-Zeitraum-Modell (Single Source für Phase 3/4/6 + Badges).
    // daily_slot ist materialisiert: Default aus der Frist, manuell überschreibbar (slot_pinned).
    const TIME_BUCKETS = [
        { key: 'today',      label: 'Heute',             color: '#dc2626' },
        { key: 'tomorrow',   label: 'Morgen',            color: '#ea580c' },
        { key: 'day_after',  label: 'Übermorgen',        color: '#f59e0b' },
        { key: 'rest_week',  label: 'Rest der Woche',    color: '#84cc16' },
        { key: 'next_week',  label: 'Nächste Woche',     color: '#16a34a' },
        { key: 'this_month', label: 'Noch diesen Monat', color: '#0d9488' },
        { key: 'later',      label: 'Später',            color: '#6366f1' },
        { key: 'occasion',   label: 'Bei Gelegenheit',   color: '#64748b' },
    ];
    const BUCKET_LABEL = Object.fromEntries(TIME_BUCKETS.map(b => [b.key, b.label]));
    const BUCKET_COLOR = Object.fromEntries(TIME_BUCKETS.map(b => [b.key, b.color]));
    const bucketLabel = (k) => BUCKET_LABEL[k] || 'Bei Gelegenheit';
    const bucketColor = (k) => BUCKET_COLOR[k] || '#64748b';
    // LOKALES Datum (YYYY-MM-DD) n Tage ab heute — NICHT toISOString() (das ist UTC und schiebt
    // bei Zeitzonen östlich von UTC einen Tag zurück → Off-by-one beim Re-Planen).
    function localDate(offsetDays = 0) {
        const x = new Date();
        x.setHours(12, 0, 0, 0);
        x.setDate(x.getDate() + (offsetDays || 0));
        return `${x.getFullYear()}-${String(x.getMonth()+1).padStart(2,'0')}-${String(x.getDate()).padStart(2,'0')}`;
    }
    // Repräsentatives Frist-Datum eines Buckets — Ziehen/Bulk re-plant die Fälligkeit (Frist = die Wahrheit).
    function bucketToDate(key) {
        const d = (n) => localDate(n);
        switch (key) {
            case 'today': return d(0);
            case 'tomorrow': return d(1);
            case 'day_after': return d(2);
            case 'rest_week': return d(5);
            case 'next_week': return d(10);
            case 'this_month': { const eo = new Date(); eo.setMonth(eo.getMonth()+1, 0); eo.setHours(12,0,0,0); const e = `${eo.getFullYear()}-${String(eo.getMonth()+1).padStart(2,'0')}-${String(eo.getDate()).padStart(2,'0')}`; const p = d(20); return p <= e ? p : e; }
            case 'later': return d(45);
            default: return null; // occasion = keine Frist
        }
    }

    // Aktivitätstyp-Labels (KI-erkannt aus ai_activity_type)
    const ACTIVITY_LABELS = {
        meeting:       'Meetings & Gespräche',
        approval:      'Approvals & Freigaben',
        communication: 'Mails & Kommunikation',
        writing:       'Texten & Schreiben',
        review:        'Review & Feedback',
        research:      'Recherche',
        admin:         'Verwaltung',
        planning:      'Planung & Strategie',
        execution:     'Hands-on Umsetzung',
        creative:      'Kreativ & Design',
        other:         'Sonstige',
    };
    const activityLabel = (type) => ACTIVITY_LABELS[type] || ACTIVITY_LABELS.other;
    let sortableInstances = [];

    const $root = document.getElementById('tp-root');
    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const fmtMin = m => !m ? '?' : (m < 60 ? m + ' min' : (m % 60 === 0 ? Math.floor(m/60) + 'h' : Math.floor(m/60) + 'h ' + (m%60) + 'm'));
    const dueDiff = d => {
        if (!d) return null;
        const due = new Date(d); due.setHours(0,0,0,0);
        const today = new Date(); today.setHours(0,0,0,0);
        return Math.round((due - today) / 86400000);
    };
    function dueClass(d) { const diff = dueDiff(d); if (diff === null) return ''; if (diff < 0) return 'is-overdue'; if (diff === 0) return 'is-today'; return ''; }
    function dueBadge(d) {
        const diff = dueDiff(d);
        if (diff === null) return '';
        if (diff < 0) return `<span class="tp-task-badge tp-overdue">${Math.abs(diff)}d überfällig</span>`;
        if (diff === 0) return `<span class="tp-task-badge tp-today">heute</span>`;
        if (diff === 1) return `<span class="tp-task-badge">morgen</span>`;
        if (diff <= 7) return `<span class="tp-task-badge">in ${diff}d</span>`;
        return `<span class="tp-task-badge">${new Date(d).toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'})}</span>`;
    }
    function effectivePriority(t) {
        return t.manual_priority || t.ai_recommended_when || 'when_possible';
    }
    function effortBucket(t) {
        const m = t.effort_minutes || t.ai_effort_estimate || 0;
        if (m === 0) return 'unknown';
        if (m <= 30) return 'quick';         // ≤30 Min · Quick Wins (2-10 Min) als Sub-Markierung
        if (m <= 60) return 'short';         // bis 1 Std
        if (m <= 240) return 'medium';       // 1-4 Std
        if (m <= 360) return 'half_day';     // 4-6 Std · halber bis dreiviertel Tag
        return 'full_day';                   // 8 Std oder mehr · Tagesblock
    }
    function dueBucket(t) {
        const d = dueDiff(t.due_on);
        if (d === null) return 'undated';
        if (d < 0) return 'overdue';
        if (d === 0) return 'today';
        if (d <= 7) return 'week';
        return 'later';
    }

    async function load() {
        try {
            const s = await App.get('/planner/pat-status');
            state.hasPat = !!s.data?.has_pat;
            state.pat = s.data || {};
        } catch (e) { state.hasPat = false; }
        if (state.hasPat) {
            try {
                const t = await App.get('/planner/tasks?include_completed=1');
                state.tasks = t.data?.tasks || [];
                state.newCount = t.data?.new_count || 0;
                state.autoPushedToday = t.data?.auto_pushed_today || 0;
                state.specialCustomers = t.data?.special_customers || [];
            } catch (e) { state.tasks = []; }
        }
        render();
        if (state.hasPat) { tpRefreshScore(); tpMaybeAutoWeekReview(); }
    }

    // ===================== Gamification (Score-Badge + Wochenrückblick) =====================

    async function tpRefreshScore() {
        try {
            const r = await App.get('/planner/score');
            state.score = r.data || null;
            tpRenderScoreBadge();
        } catch (e) { /* still — Gamification ist optional */ }
    }

    function tpRenderScoreBadge() {
        const el = document.getElementById('tp-score-badge');
        if (!el) return;
        const s = state.score;
        if (!s) { el.hidden = true; return; }
        el.hidden = false;
        const streak = s.streak_active || 0;
        const flame = streak >= 2 ? `<span class="tp-score-flame" title="${streak} Tage in Folge aktiv">🔥${streak}</span>` : '';
        const allToday = s.all_today_done
            ? 'Heute alles geschafft 🎯'
            : (s.today_planned > 0 ? `${s.today_done}/${s.today_planned} Heute-Tasks` : `${s.tasks_completed} erledigt heute`);
        el.innerHTML = `
            <div class="tp-score-pts">${s.points}<small>P</small></div>
            <div class="tp-score-col">
                <div class="tp-score-line" style="font-weight:600;color:var(--slate-700);">Heute</div>
                <div class="tp-score-line">${esc(allToday)}</div>
            </div>
            ${flame}
        `;
    }

    function tpMaybeAutoWeekReview() {
        // Montags morgens einmal pro Woche automatisch öffnen (mittlere Sichtbarkeit, nicht aufdringlich).
        if (new Date().getDay() !== 1) return; // 1 = Montag
        const wk = tpIsoWeekKey();
        if (localStorage.getItem('thx_tp_weekreview_seen') === wk) return;
        localStorage.setItem('thx_tp_weekreview_seen', wk);
        setTimeout(() => tpOpenWeekReview(), 800);
    }

    function tpIsoWeekKey() {
        const d = new Date();
        const onejan = new Date(d.getFullYear(), 0, 1);
        const week = Math.ceil((((d - onejan) / 86400000) + onejan.getDay() + 1) / 7);
        return d.getFullYear() + '-W' + week;
    }

    window.tpOpenWeekReview = async function () {
        const modal = document.getElementById('tp-week-modal');
        if (!modal) return;
        modal.classList.add('is-open');
        modal.innerHTML = `<div class="tp-week-card"><div class="tp-empty" style="padding:30px;text-align:center;color:var(--slate-500);">Lädt…</div></div>`;
        try {
            const r = await App.get('/planner/week-review');
            tpRenderWeekReview(r.data || {});
        } catch (e) {
            modal.innerHTML = `<div class="tp-week-card"><div class="tp-empty" style="padding:30px;text-align:center;">Wochenrückblick gerade nicht verfügbar.</div></div>`;
        }
    };

    window.tpCloseWeekReview = function () {
        const modal = document.getElementById('tp-week-modal');
        if (modal) modal.classList.remove('is-open');
    };

    function tpRenderWeekReview(data) {
        const modal = document.getElementById('tp-week-modal');
        if (!modal) return;
        const totals = data.totals || { tasks: 0, points: 0, effort_minutes: 0 };
        const days = data.days || [];
        // Vollständige 7-Tage-Achse (auch leere Tage) für die Balken.
        const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        const todayStr = new Date().toISOString().slice(0, 10);
        const byDate = {}; days.forEach(d => byDate[d.date] = d);
        const axis = [];
        for (let i = 6; i >= 0; i--) {
            const dt = new Date(Date.now() - i * 86400000);
            const ds = dt.toISOString().slice(0, 10);
            axis.push({ ds, name: dayNames[dt.getDay()], tasks: byDate[ds]?.tasks || 0, isToday: ds === todayStr });
        }
        const maxTasks = Math.max(1, ...axis.map(a => a.tasks));
        const bars = axis.map(a => `
            <div class="tp-week-bar-col">
                <div class="tp-week-bar ${a.isToday ? 'is-today' : ''}" style="height:${Math.round((a.tasks / maxTasks) * 100)}%;" title="${a.tasks} Tasks"></div>
                <div class="tp-week-bar-day">${a.name}</div>
            </div>`).join('');

        const bestHour = data.best_hour !== null && data.best_hour !== undefined
            ? `${String(data.best_hour).padStart(2, '0')}–${String((data.best_hour + 1) % 24).padStart(2, '0')} Uhr`
            : null;
        const facts = [];
        if (data.top_customer) facts.push(`<div class="tp-week-fact"><span class="material-symbols-rounded">workspace_premium</span>Top-Kunde der Woche: <strong>${esc(data.top_customer.name)}</strong> (${data.top_customer.tasks} Tasks)</div>`);
        if (bestHour) facts.push(`<div class="tp-week-fact"><span class="material-symbols-rounded">schedule</span>Produktivste Zeit: <strong>${bestHour}</strong></div>`);

        modal.innerHTML = `
            <div class="tp-week-card">
                <div class="tp-week-head">
                    <div class="tp-week-title">Deine Woche</div>
                    <button class="thx-btn thx-btn-secondary" onclick="tpCloseWeekReview()" title="Schließen"><span class="material-symbols-rounded">close</span></button>
                </div>
                <div class="tp-week-spell">${esc(tpWeekSpell(totals))}</div>
                <div class="tp-week-stats">
                    <div class="tp-week-stat"><div class="tp-week-stat-val">${totals.tasks}</div><div class="tp-week-stat-lbl">Tasks erledigt</div></div>
                    <div class="tp-week-stat"><div class="tp-week-stat-val">${totals.points}</div><div class="tp-week-stat-lbl">Punkte</div></div>
                    <div class="tp-week-stat"><div class="tp-week-stat-val">${fmtMin(totals.effort_minutes)}</div><div class="tp-week-stat-lbl">Aufwand</div></div>
                </div>
                <div class="tp-week-bars">${bars}</div>
                ${facts.length ? `<div class="tp-week-facts">${facts.join('')}</div>` : ''}
                <div style="text-align:right;margin-top:8px;">
                    <button class="thx-btn thx-btn-primary" onclick="tpCloseWeekReview()">Passt, weiter geht's</button>
                </div>
            </div>`;
    }

    function tpWeekSpell(totals) {
        // Lockerer Spruch, kein Praktikanten-Ton.
        if (totals.tasks === 0) return 'Ruhige Woche im Tagesplan — oder Du hast in Asana direkt abgehakt.';
        if (totals.tasks >= 40) return 'Brett. Diese Woche ist richtig was weggegangen.';
        if (totals.tasks >= 20) return 'Solide Woche, da kam ordentlich was vom Tisch.';
        if (totals.tasks >= 8) return 'Gute Woche, ein paar dicke Brocken erledigt.';
        return 'Ein paar Sachen erledigt — jeder Haken zählt.';
    }

    function tpBadgeLine(badges) {
        if (!badges || !badges.length) return '';
        return badges.map(b => `${b.icon} <strong>${esc(b.label)}</strong>`).join(' · ');
    }

    // Inline-Handler laufen im globalen Scope und sehen state/render (IIFE-lokal) NICHT —
    // darum als window.* exponieren, wie alle anderen interaktiven Planner-Buttons.
    window.tpWaitingSort = function (mode) {
        state.waitingSort = mode;
        render();
    };
    window.tpToggleLongWaiting = function () {
        state.showLongWaiting = !state.showLongWaiting;
        render();
    };

    function render() {
        sortableInstances.forEach(s => s.destroy());
        sortableInstances = [];
        const $sb = document.getElementById('tp-sb-steps');
        if (!state.hasPat) {
            $root.innerHTML = renderPatSetup();
            if ($sb) $sb.innerHTML = '<div class="tp-empty" style="padding:16px;">Erst Asana verbinden →</div>';
            return;
        }
        // Wartende Tasks überall ausblenden — sie tauchen NUR in Phase 5 'Beobachten' auf.
        // Phase 5_waiting holt sich sie selbst direkt aus openTasks().
        const open = openTasks().filter(t => t.is_waiting != 1);
        if ($sb) $sb.innerHTML = renderSidebarStepper(open);
        $root.innerHTML = renderStep(open);
        attachStepperListeners();
        attachStepListeners();
    }

    function openTasks() {
        return state.tasks.filter(t => !t.completed_at_local && !t.completed_at_asana);
    }
    function localCompletedTasks() {
        // Tasks, die NUR lokal abgehakt wurden (in Asana noch offen) — wiederherstellbar
        return state.tasks.filter(t => t.completed_at_local && !t.completed_at_asana);
    }
    function analyzedCount() { return openTasks().filter(t => t.is_waiting != 1 && t.ai_summary).length; }
    function unanalyzedCount() { return openTasks().filter(t => t.is_waiting != 1 && !t.ai_summary).length; }

    // ===== PAT-Setup (wenn noch keine Verbindung) =====
    function renderPatSetup() {
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>Verbinde Dein Asana</h2>
                <div class="tp-sub">Einmalig — der Token wird verschlüsselt gespeichert.</div>
            </div>
            <div class="tp-pat-grid">
                <div class="tp-pat-form">
                    <label style="display:block;font-size:var(--d-fs-xs);color:var(--slate-600);font-weight:600;margin-bottom:4px;">Dein Asana Personal Access Token</label>
                    <input type="password" id="tp-pat-input" placeholder="2/1234567890123456/abcdef...">
                    <div style="margin-top:10px;"><button class="thx-btn thx-btn-primary" onclick="tpSavePat()">Speichern &amp; verbinden</button></div>
                    <div id="tp-pat-result" style="margin-top:10px;font-size:var(--d-fs-sm);"></div>
                </div>
                <div class="tp-pat-info">
                    <strong>Token erstellen:</strong>
                    <ol>
                        <li>In Asana einloggen.</li>
                        <li>Profil-Foto → <em>Einstellungen</em> → <em>Apps</em> → ganz unten <em>"Entwickler-Konsole anzeigen"</em>.</li>
                        <li>Links: <em>Personal access tokens</em> → <em>+ Create new token</em>.</li>
                        <li>Token einmalig kopieren und hier einfügen.</li>
                    </ol>
                </div>
            </div>
        </div>`;
    }

    // ===== Sidebar-Stepper =====
    function renderSidebarStepper(open) {
        const cur = state.currentStep;
        const has = open.length > 0;
        const analyzed = analyzedCount();
        const allAnalyzed = has && analyzed === open.length;
        const isDone = (n) => {
            if (n === 1) return has;
            if (n === 2) return allAnalyzed;
            if (n === 3) return has;
            if (n === 4) return has;
            if (n === 5) return state.tasks.some(t => t.is_waiting == 1);  // Beobachten
            if (n === 6) return state.tasks.some(t => t.daily_slot === 'today');  // Tagesplanung
            if (n === 7) return !!state.plan;  // Tagesplan
            if (n === 8) return state.tasks.some(t => t.completed_at_local || t.completed_at_asana);  // Archiv
            return false;
        };
        const newBadge = (n) => (n === 1 && state.newCount > 0)
            ? `<span class="tp-sb-step-badge">${state.newCount}</span>` : '';
        const stepBtn = (s) => `
            <button class="tp-sb-step ${cur===s.n?'is-active':''} ${isDone(s.n)?'is-done':''}" data-step="${s.n}" type="button">
                <span class="tp-sb-step-num">${isDone(s.n) && cur !== s.n ? '✓' : s.n}</span>
                <span class="tp-sb-step-body">
                    <span class="tp-sb-step-label">${s.label}</span>
                    <span class="tp-sb-step-sub">${s.sub}</span>
                </span>
                ${newBadge(s.n)}
            </button>`;
        // Workflow-Schritte (1-7) und Archiv (8) visuell trennen
        const workflowSteps = STEPS.filter(s => s.n <= 7);
        const archiveSteps  = STEPS.filter(s => s.n === 8);
        return `
            <div class="tp-sb-section-label">Schritte</div>
            ${workflowSteps.map(stepBtn).join('')}
            ${archiveSteps.length ? `<div class="tp-sb-divider"></div><div class="tp-sb-section-label">Archiv</div>${archiveSteps.map(stepBtn).join('')}` : ''}
        `;
    }

    function attachStepperListeners() {
        document.querySelectorAll('[data-step]').forEach(b => {
            b.addEventListener('click', () => {
                tpGoStep(parseInt(b.dataset.step, 10));
            });
        });
    }

    // Initial-Step aus URL-Hash lesen (#step-3 etc.)
    (function initStepFromHash() {
        const m = (location.hash || '').match(/^#step-([1-8])$/);
        if (m) state.currentStep = parseInt(m[1], 10);
    })();
    // Bei Hash-Änderungen (z.B. Back-Button) Step neu setzen
    window.addEventListener('hashchange', () => {
        const m = (location.hash || '').match(/^#step-([1-8])$/);
        if (m) { state.currentStep = parseInt(m[1], 10); render(); }
    });

    // ===== Step-Renderer =====
    function renderStep(open) {
        switch (state.currentStep) {
            case 1: return renderStep1(open);
            case 2: return renderStep2(open);
            case 3: return renderStep3(open);
            case 4: return renderStep4(open);
            case 5: return renderStep5_waiting();
            case 6: return renderStep5();   // Tagesplanung (vorher Step 5)
            case 7: return renderStep6();   // Tagesplan (vorher Step 6)
            case 8: return renderStep7();   // Archiv (vorher Step 7)
            default: return '';
        }
    }

    // ----- Step 1: Sync -----
    function renderStep1(open) {
        const last = state.pat?.last_synced || open.reduce((max, t) => t.last_synced_at > max ? t.last_synced_at : max, '');
        const withCust = open.filter(t => t.customer_id).length;
        const privT = open.filter(t => !t.customer_id && t.category_hint === 'private').length;
        const unclear = open.filter(t => !t.customer_id && t.category_hint !== 'private');
        const unclT = unclear.length;
        const allCustomers = collectCustomers(open);
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>1 · Tasks aus Asana laden</h2>
                <div class="tp-sub">Verbunden als <strong>${esc(state.pat?.name || '—')}</strong> (${esc(state.pat?.email || '—')})</div>
            </div>
            <div class="tp-kpi-row">
                <div class="tp-kpi-card"><div class="tp-kpi-label">Offene Tasks</div><div class="tp-kpi-value">${open.length}</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">Mit Kunde erkannt</div><div class="tp-kpi-value" style="color:#15803d;">${withCust}</div><div class="tp-kpi-hint">Per Asana-Projekt oder Titel-Kürzel</div></div>
                ${(state.specialCustomers || []).map(sc => {
                    const cnt = open.filter(t => String(t.customer_id) === String(sc.id)).length;
                    return `<div class="tp-kpi-card"><div class="tp-kpi-label">${esc(sc.name)}</div><div class="tp-kpi-value" style="color:${esc(sc.hex_color || '#0050a0')};">${cnt}</div><div class="tp-kpi-hint">Default-Kunde eines Asana-Accounts</div></div>`;
                }).join('')}
                <div class="tp-kpi-card"><div class="tp-kpi-label">Privat</div><div class="tp-kpi-value" style="color:#7c3aed;">${privT}</div><div class="tp-kpi-hint">PRIV-Präfix im Titel</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">Unklar</div><div class="tp-kpi-value" style="color:${unclT>0?'#f59e0b':'#15803d'};">${unclT}</div><div class="tp-kpi-hint">Kein Kunden-Kürzel gefunden</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">Letzter Sync</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${last ? new Date(last).toLocaleString('de-DE') : '—'}</div></div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <button class="thx-btn thx-btn-primary" onclick="tpSync()" id="tp-sync-btn">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">sync</span>
                    Jetzt synchronisieren
                </button>
                <button class="thx-btn thx-btn-secondary" onclick="tpResolve()" id="tp-resolve-btn" title="Kunden-Erkennung neu berechnen">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_fix_high</span>
                    Kunden neu zuordnen
                </button>
                <a class="thx-btn thx-btn-secondary" href="/tagesplan/accounts" title="Asana-Accounts verwalten (PAT, weitere Accounts wie Hills &amp; Valleys)">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">manage_accounts</span>
                    Asana-Accounts verwalten
                </a>
                ${open.length > 0 ? `<button class="thx-btn thx-btn-secondary" onclick="tpGoStep(2)">Weiter zur KI-Analyse →</button>` : ''}
            </div>
            <div style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                Erkennung: Titel-Präfix wird gegen <code>customers.abbreviation</code> gematched (z.B. „WIT Neue Kampagne" → Kunde WIT). Präfixe „PRIV/PRIVAT" → Privat-Kategorie.
            </div>
        </div>
        ${unclT > 0 ? renderUnclearCard(unclear, allCustomers) : ''}`;
    }

    function collectCustomers(open) {
        const map = new Map();
        open.forEach(t => {
            if (t.customer_id && !map.has(t.customer_id)) {
                map.set(t.customer_id, { id: t.customer_id, name: t.customer_name, abbr: t.customer_abbr, color: t.customer_color });
            }
        });
        return Array.from(map.values()).sort((a,b) => (a.name || '').localeCompare(b.name || ''));
    }

    function renderUnclearCard(unclearTasks, customers) {
        if (!state.unclearSel) state.unclearSel = new Set();
        // Bereinige: ausgewählte IDs, die nicht mehr in der unklar-Liste sind
        const validIds = new Set(unclearTasks.map(t => t.id));
        for (const id of state.unclearSel) if (!validIds.has(id)) state.unclearSel.delete(id);

        const selCount = state.unclearSel.size;
        const allOnPageSelected = unclearTasks.length > 0 && unclearTasks.every(t => state.unclearSel.has(t.id));
        const opts = ['<option value="">— Kunde wählen —</option>']
            .concat(customers.map(c => `<option value="c:${c.id}">${esc(c.abbr || '')} · ${esc(c.name)}</option>`))
            .concat(['<option value="private">Privat</option>'])
            .concat(['<option value="ignore">Aus Planner ausblenden</option>'])
            .join('');
        const bulkCustOpts = ['<option value="">— Kunde für alle markierten —</option>']
            .concat(customers.map(c => `<option value="${c.id}">${esc(c.abbr || '')} · ${esc(c.name)}</option>`))
            .join('');
        const rows = unclearTasks.map(t => `
            <div class="tp-unclear-row ${state.unclearSel.has(t.id) ? 'is-selected' : ''}" data-task-id="${t.id}">
                <input type="checkbox" class="tp-unclear-check" data-bulk-check ${state.unclearSel.has(t.id) ? 'checked' : ''}>
                <div class="tp-unclear-name" data-bulk-toggle title="Klick zum Markieren">${esc(t.name)}</div>
                ${t.asana_project_name ? `<div class="tp-unclear-proj">${esc(t.asana_project_name)}</div>` : '<div></div>'}
                <select class="tp-unclear-select" data-tp-assign onchange="tpAssignUnclear(${t.id}, this.value, this)">
                    ${opts}
                </select>
            </div>
        `).join('');
        const bulkBar = selCount > 0 ? `
            <div class="tp-bulkbar">
                <span class="tp-bulkbar-count">${selCount} ausgewählt</span>
                <button type="button" onclick="tpBulkUnclear('private')">→ Privat</button>
                <button type="button" onclick="tpBulkUnclear('ignore')">→ Ausblenden</button>
                <select onchange="if(this.value){tpBulkUnclear('customer', parseInt(this.value,10));this.value='';}">
                    ${bulkCustOpts}
                </select>
                <button type="button" class="tp-bulkbar-cancel" onclick="tpBulkUnclearClear()">Auswahl aufheben</button>
            </div>` : '';
        return `
        <div class="tp-card" style="border-left:4px solid #f59e0b;">
            <div class="tp-card-head">
                <h2 style="font-size:var(--d-fs-md);"><span class="material-symbols-rounded" style="vertical-align:middle;color:#f59e0b;">help</span> ${unclearTasks.length} unklare Tasks klären</h2>
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:var(--d-fs-xs);color:var(--slate-600);cursor:pointer;margin-left:auto;">
                    <input type="checkbox" id="tp-bulk-all" ${allOnPageSelected ? 'checked' : ''} onchange="tpBulkUnclearToggleAll(this.checked)">
                    Alle markieren
                </label>
            </div>
            ${bulkBar}
            <div class="tp-unclear-list">${rows}</div>
            <div style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                Tipp: Wiederkehrende Präfixe (z.B. „ABC ") als <code>customers.abbreviation</code> setzen — danach <strong>Kunden neu zuordnen</strong>, dann fällt das für alle Folge-Tasks weg.
            </div>
        </div>`;
    }

    // ----- Step 2: KI-Analyse -----
    function renderStep2(open) {
        const analyzed = analyzedCount();
        const missing = open.length - analyzed;
        const quickCount = open.filter(t => t.is_quick_task == 1).length;
        const withActivity = open.filter(t => t.last_activity && t.last_activity.trim() !== '').length;
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>2 · KI-Vorab-Analyse</h2>
                <div class="tp-sub">Aufwand, Bedeutung, Quick-Task-Erkennung aus Asana-Kommentaren</div>
            </div>
            <div class="tp-kpi-row">
                <div class="tp-kpi-card"><div class="tp-kpi-label">Bereits analysiert</div><div class="tp-kpi-value">${analyzed}</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">Noch offen</div><div class="tp-kpi-value" style="color:${missing>0?'#b91c1c':'#15803d'};">${missing}</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">⚡ Quick-Tasks erkannt</div><div class="tp-kpi-value" style="color:#15803d;">${quickCount}</div><div class="tp-kpi-hint">2-10 Min, oft 1-Klick-Rückmeldung</div></div>
                <div class="tp-kpi-card"><div class="tp-kpi-label">Mit Asana-Kommentaren</div><div class="tp-kpi-value" style="color:var(--slate-700);">${withActivity}</div><div class="tp-kpi-hint">Letzte Aktivitäten gezogen</div></div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <button class="thx-btn thx-btn-primary" onclick="tpAnalyze()" id="tp-analyze-btn" ${missing===0?'disabled':''}>
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">psychology</span>
                    ${missing>0 ? `Jetzt ${Math.min(missing, 30)} Tasks analysieren` : 'Alles analysiert'}
                </button>
                <button class="thx-btn thx-btn-secondary" onclick="tpResetAnalysis()" id="tp-reset-btn" title="Setzt die KI-Analyse für alle offenen Tasks zurück — nötig, wenn neue Logik (z.B. Quick-Task-Erkennung) aktiv wurde, nachdem die Tasks schon analysiert wurden.">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">restart_alt</span>
                    Re-Analyse erzwingen
                </button>
                <button class="thx-btn thx-btn-secondary" onclick="tpGoStep(3)">Weiter →</button>
            </div>
            <div style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                ${missing > 30 ? `Hinweis: ${missing} Tasks offen — pro Klick werden bis zu 30 analysiert. Mehrfach klicken bis durch. ` : ''}
                ${quickCount === 0 && analyzed > 0 ? `<strong>Keine Quick-Tasks erkannt?</strong> Klick <em>Re-Analyse erzwingen</em>, dann <em>Jetzt analysieren</em> mehrfach — die KI zieht dabei pro Task die letzten Asana-Kommentare und erkennt schnelle Antworten/Mails.` : ''}
            </div>
        </div>
        ${renderLearnPanel()}`;
    }

    // ----- Lernschleife: gelernte Regeln reviewen + aktivieren -----
    function renderLearnPanel() {
        if (state.learn === undefined) {
            if (!state.learnLoading) { state.learnLoading = true; tpLoadLearnRules(); }
            return `<div class="tp-card" id="tp-learn-panel"><div class="tp-sub" style="padding:4px 0;">Lerndaten werden geladen…</div></div>`;
        }
        const rules = state.learn.rules || [];
        const cnt = state.learn.correction_count || 0;
        const fieldLabel = { effort: 'Aufwand', quick: 'Quick', priority: 'Wichtigkeit', slot: 'Einplanung', customer: 'Kunde', general: 'Allgemein' };
        const fieldColor = { effort: '#0369a1', quick: '#b45309', priority: '#7c3aed', slot: '#0f766e', customer: '#be185d', general: '#475569' };
        const ruleRow = (r) => {
            const chip = `<span class="tp-learn-chip" style="background:${fieldColor[r.field]||'#475569'};">${fieldLabel[r.field]||r.field}</span>`;
            const hint = r.pattern_hint ? `<div class="tp-learn-hint">greift bei: ${esc(r.pattern_hint)}</div>` : '';
            const support = r.support_count > 1 ? `<span class="tp-learn-support" title="aus ${r.support_count} Korrekturen">×${r.support_count}</span>` : '';
            const actions = r.status === 'active'
                ? `<span class="tp-learn-active">● aktiv</span>
                   <button class="thx-btn thx-btn-secondary" onclick="tpRuleStatus(${r.id},'candidate')" title="Deaktivieren"><span class="material-symbols-rounded">pause</span></button>`
                : `<button class="thx-btn thx-btn-primary" onclick="tpRuleStatus(${r.id},'active')"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;">bolt</span> Aktivieren</button>
                   <button class="thx-btn thx-btn-secondary" onclick="tpRuleStatus(${r.id},'dismissed')" title="Verwerfen"><span class="material-symbols-rounded">close</span></button>`;
            return `
                <div class="tp-learn-row ${r.status==='active'?'is-active':''}">
                    <div class="tp-learn-main">
                        <div class="tp-learn-text">${chip} ${esc(r.rule_text)} ${support}</div>
                        ${hint}
                    </div>
                    <div class="tp-learn-actions">${actions}</div>
                </div>`;
        };
        const body = rules.length === 0
            ? `<div class="tp-sub" style="padding:8px 0;">Noch keine Regeln. Korrigiere ein paar Tasks (Aufwand, Quick-ja/nein, Wichtigkeit) — dann <strong>Muster analysieren</strong>, und ich schlage Regeln vor, die die KI künftig befolgt.</div>`
            : `<div class="tp-learn-list">${rules.map(ruleRow).join('')}</div>`;
        return `
        <div class="tp-card" id="tp-learn-panel">
            <div class="tp-card-head">
                <h2 style="font-size:var(--d-fs-md);">Gelernte Regeln</h2>
                <div class="tp-sub">${cnt} Korrektur${cnt===1?'':'en'} protokolliert — daraus erkenne ich Muster und mache sie zu Regeln, die Du freischaltest.</div>
                <button class="thx-btn thx-btn-primary" style="margin-left:auto;" onclick="tpAnalyzeLearn()" id="tp-learn-analyze-btn" ${cnt<3?'disabled':''}>
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">neurology</span>
                    Muster analysieren
                </button>
            </div>
            ${cnt < 3 ? `<div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:8px;">Ab 3 protokollierten Korrekturen kann ich analysieren.</div>` : ''}
            ${body}
        </div>`;
    }

    async function tpLoadLearnRules() {
        try {
            const r = await App.get('/planner/learn/rules');
            state.learn = r.data || { rules: [], correction_count: 0 };
        } catch (e) {
            state.learn = { rules: [], correction_count: 0 };
        }
        state.learnLoading = false;
        render();
    }

    window.tpAnalyzeLearn = async function () {
        const btn = document.getElementById('tp-learn-analyze-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">hourglass_top</span> Analysiere…'; }
        try {
            const r = await App.post('/planner/learn/analyze', {});
            App.showNotification(r.data?.message || r.message || 'Analyse abgeschlossen', 'success');
            state.learn = undefined; state.learnLoading = false;
            render();
        } catch (e) {
            App.showNotification('Analyse fehlgeschlagen: ' + (e.message || ''), 'error');
            state.learn = undefined; state.learnLoading = false;
            render();
        }
    };

    window.tpRuleStatus = async function (ruleId, status) {
        try {
            await App.post('/planner/learn/rule-status', { rule_id: ruleId, status });
            state.learn = undefined; state.learnLoading = false;
            render();
        } catch (e) {
            App.showNotification('Fehler: ' + (e.message || ''), 'error');
        }
    };

    // ----- Step 3: Vorsortierung Kanban -----
    function renderStep3(open) {
        const axisOptions = [
            { v: 'bucket',   l: 'Zeitraum' },
            { v: 'effort',   l: 'Aufwand' },
            { v: 'priority', l: 'Priorität' },
            { v: 'customer', l: 'Kunde' },
        ];
        const axisPills = axisOptions.map(a =>
            `<button class="tp-pill ${state.kanbanAxis===a.v?'is-active':''}" data-axis="${a.v}" type="button">${a.l}</button>`
        ).join('');
        const cols = kanbanColumns(open, state.kanbanAxis);
        const board = cols.map(col => `
            <div class="tp-kanban-col">
                <div class="tp-kanban-col-head">
                    ${col.color ? `<span class="tp-kanban-col-dot" style="background:${esc(col.color)};"></span>` : ''}
                    <h3>${esc(col.label)}</h3>
                    <span class="tp-kanban-col-count">${col.tasks.length}</span>
                </div>
                <div class="tp-kanban-body" data-kanban-col="${esc(col.key)}">
                    ${col.tasks.length === 0
                        ? '<div class="tp-kanban-empty">leer — Karten hierhin ziehen</div>'
                        : col.tasks.map(t => renderUniCard(t)).join('')}
                </div>
            </div>
        `).join('');
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>3 · Vorsortierung (Kanban)</h2>
                <div class="tp-sub">Karten verschieben ändert das jeweilige Feld. Rechtsklick für mehr Aktionen.</div>
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Spalten nach</span>
                ${axisPills}
            </div>
        </div>
        <div class="tp-card" style="padding:14px;">
            <div class="tp-kanban">${board}</div>
        </div>`;
    }

    function kanbanColumns(tasks, axis) {
        if (axis === 'priority') {
            const cols = {
                asap:          { key: 'asap',          label: 'ASAP',             color: '#b91c1c', tasks: [] },
                this_week:     { key: 'this_week',     label: 'Diese Woche',      color: '#f59e0b', tasks: [] },
                when_possible: { key: 'when_possible', label: 'Wenn möglich',     color: '#64748b', tasks: [] },
            };
            tasks.forEach(t => { (cols[effectivePriority(t)] || cols.when_possible).tasks.push(t); });
            return Object.values(cols);
        }
        if (axis === 'customer') {
            // Spalten: Kunde-Buckets + 'Privat' (PRIV-Präfix) + 'Unklar' (kein Match)
            const map = new Map();
            const ensure = (key, label, color) => {
                if (!map.has(key)) map.set(key, { key, label, color, tasks: [] });
                return map.get(key);
            };
            tasks.forEach(t => {
                if (t.customer_id) {
                    ensure('c' + t.customer_id, t.customer_name || ('Kunde #' + t.customer_id), t.customer_color || '#94a3b8').tasks.push(t);
                } else if (t.category_hint === 'private') {
                    ensure('private', 'Privat', '#7c3aed').tasks.push(t);
                } else {
                    ensure('unclear', 'Unklar', '#f59e0b').tasks.push(t);
                }
            });
            // Reihenfolge: Kunden sortiert nach Anzahl, dann Privat, dann Unklar (am Ende, weil "abzuarbeiten")
            const customers = Array.from(map.values()).filter(c => c.key.startsWith('c')).sort((a, b) => b.tasks.length - a.tasks.length);
            const priv = map.get('private');
            const uncl = map.get('unclear');
            return [...customers, ...(priv ? [priv] : []), ...(uncl ? [uncl] : [])];
        }
        if (axis === 'effort') {
            const cols = {
                quick:    { key: 'quick',    label: '≤30 Min · Quick Wins',           color: '#15803d', tasks: [] },
                short:    { key: 'short',    label: 'bis 1 Std',                      color: '#84cc16', tasks: [] },
                medium:   { key: 'medium',   label: '1-4 Std',                        color: '#eab308', tasks: [] },
                half_day: { key: 'half_day', label: '4-6 Std · halber Tag',           color: '#ea580c', tasks: [] },
                full_day: { key: 'full_day', label: '8 Std oder mehr · Tagesblock',   color: '#dc2626', tasks: [] },
                unknown:  { key: 'unknown',  label: '? (nicht geschätzt)',           color: '#94a3b8', tasks: [] },
            };
            tasks.forEach(t => { cols[effortBucket(t)].tasks.push(t); });
            return Object.values(cols);
        }
        // bucket (Zeitraum): die 8 einheitlichen Buckets, gruppiert nach materialisiertem daily_slot
        const cols = {};
        TIME_BUCKETS.forEach(b => { cols[b.key] = { key: b.key, label: b.label, color: b.color, tasks: [] }; });
        tasks.forEach(t => { (cols[t.daily_slot] || cols.occasion).tasks.push(t); });
        return Object.values(cols);
    }

    /**
     * Einheitliche Card-Komponente für alle Steps. Eine Tile, ein Look.
     * opts:
     *   - reason:        KI-Begründung (Step 5)
     *   - hero:          Hero-Variante (Step 6 erste Task) — größer, mit Action-Buttons
     *   - bulkSelectable: zeigt Bulk-Checkbox links (Step 4)
     *   - hideActions:   keine Asana/Check/Info-Icons unten (Pure-Preview-Mode)
     *   - addable:       statt Actions ein "+"-Button (zum In-Heute-Holen)
     */
    function renderUniCard(t, opts = {}) {
        const eff = t.effort_minutes || t.ai_effort_estimate || null;
        const effLabel = eff ? fmtMin(eff) : '?';
        const effClass = (t.effort_minutes ? '' : ' is-ai');
        const ep = effectivePriority(t);
        const dueDiffVal = dueDiff(t.due_on);
        const isDone = !!(t.completed_at_local || t.completed_at_asana);

        // Klassen
        let cls = 'tp-c-card';
        if (opts.hero) {
            cls += ' is-hero';
            if (opts.focus) cls += ' is-focus';
        }
        else if (opts.muted) cls += ' is-muted';
        if (t.is_waiting == 1) cls += ' is-waiting';
        if (t.waiting_signal == 1) cls += ' is-signal';
        if (t.is_toad == 1) cls += ' is-toad-card';
        if (isDone) cls += ' is-completed';
        else if ((t.postpone_count || 0) >= 2) cls += ' is-stale';
        else if (dueDiffVal !== null && dueDiffVal < 0) cls += ' is-overdue';
        const isSelected = state.step4Sel && state.step4Sel.has(t.id);
        if (isSelected) cls += ' is-selected';

        // Kunde — Farbe nur auf das kleine Quadrat (Abbreviation), Rest neutral.
        // Wenn die Task aus einem nicht-default Asana-Account stammt UND der Kunde GLEICH dem
        // Default-Kunden dieses Accounts ist, blenden wir den Customer-Block aus — das
        // Account-Badge (z.B. "H&V") sagt schon alles, kein doppeltes Anzeigen nötig.
        const isCustomerSameAsAccountDefault = (
            t.asana_account_is_default != 1
            && t.asana_account_default_customer_id
            && String(t.customer_id) === String(t.asana_account_default_customer_id)
        );
        const custColor = t.customer_color || '#94a3b8';
        const custAbbr = t.customer_abbr || (t.customer_name || '').substring(0, 3) || '–';
        const custName = t.customer_name || (t.category_hint === 'private' ? 'Privat' : (t.category_hint === 'unclear' ? 'Unklar' : 'Ohne Kunde'));
        const custHtml = isCustomerSameAsAccountDefault ? '' : `<span class="tp-c-cust">
            <span class="tp-c-cust-abbr" style="background:${esc(custColor)};">${esc(custAbbr)}</span>
            <span>${esc(custName)}</span>
        </span>`;

        // Due
        let dueHtml = '';
        if (dueDiffVal !== null) {
            let dueCls = '';
            let dueText = '';
            if (dueDiffVal < 0) { dueCls = 'is-overdue'; dueText = Math.abs(dueDiffVal) + 'd überfällig'; }
            else if (dueDiffVal === 0) { dueCls = 'is-today'; dueText = 'heute'; }
            else if (dueDiffVal === 1) dueText = 'morgen';
            else if (dueDiffVal <= 7) dueText = 'in ' + dueDiffVal + 'd';
            else dueText = new Date(t.due_on).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
            dueHtml = `<span class="tp-c-due ${dueCls}">${dueText}</span>`;
        }

        // Summary
        const isFallbackSummary = !t.ai_summary
            || t.ai_summary === '(KI lieferte keine Zusammenfassung)'
            || (t.ai_summary || '').startsWith('(KI-Analyse fehlgeschlagen');
        const summaryHtml = !isFallbackSummary
            ? `<div class="tp-c-summary">${esc(t.ai_summary)}</div>`
            : '';

        // Badges (sparsam, nur wenn semantisch)
        const badges = [];
        if (t.is_quick_task == 1) badges.push(`<span class="tp-c-badge is-quick">⚡ Quick</span>`);
        if (t.is_recurring == 1) {
            const label = t.recurring_pattern ? esc(t.recurring_pattern) : 'wiederkehrend';
            badges.push(`<span class="tp-c-badge is-recurring" title="Wiederkehrende Task — Frist wird nach Erledigung verlängert">🔁 ${label}</span>`);
        }
        if (ep === 'asap') badges.push(`<span class="tp-c-badge is-asap">ASAP</span>`);
        if (t.ai_significance && t.ai_significance >= 8) badges.push(`<span class="tp-c-badge is-sig" title="KI-Bedeutsamkeit ${t.ai_significance}/10">wichtig ${t.ai_significance}/10</span>`);
        if ((t.postpone_count || 0) >= 2) badges.push(`<span class="tp-c-badge is-stale">${t.postpone_count}× verschoben</span>`);
        if (t.is_toad == 1) badges.push(`<span class="tp-c-badge is-toad" title="Kröte des Tages — heute unbedingt angehen">🐸 Kröte</span>`);
        // Hinweis NUR, wenn die geplante Frist MEHR ALS 1 Tag nach der Asana-Frist liegt.
        // (Früher = ok, 'erledigt bis'; bis +24 Std. Toleranz, erst darüber lohnt das Angleichen in Asana.)
        if (t.asana_due_on && t.due_on) {
            const devDays = Math.round((new Date(t.due_on) - new Date(t.asana_due_on)) / 86400000);
            if (devDays > 1) {
                const ad = new Date(t.asana_due_on).toLocaleDateString('de-DE',{day:'2-digit',month:'short'});
                badges.push(`<span class="tp-c-badge is-deviation" title="Du planst diese Task ${devDays} Tage nach der Asana-Frist (${ad}). Gleiche die Frist in Asana an, dann verschwindet der Hinweis nach dem nächsten Sync.">⚠ Asana: ${ad}</span>`);
            }
        }

        // Reason (Step 5 KI-Begründung oder via Plan)
        const planReason = state.plan?.sequence?.find(s => s.task_id === t.id)?.reason;
        const reason = opts.reason || (opts.hero ? planReason : null);
        const reasonHtml = reason
            ? `<div class="tp-c-reason"><span class="material-symbols-rounded">tips_and_updates</span><span>${esc(reason)}</span></div>`
            : '';

        // Bulk-Checkbox (Step 4)
        const checkHtml = opts.bulkSelectable
            ? `<input type="checkbox" class="tp-c-check" data-step4-sel ${isSelected ? 'checked' : ''} onclick="event.stopPropagation();" title="Markieren">`
            : '';

        // Actions
        let actionsHtml = '';
        if (opts.hero && opts.focus) {
            // Nur die fokussierte (erste) Heute-Karte bekommt die großen Aktions-Buttons.
            actionsHtml = `
                <div class="tp-c-actions-hero">
                    ${t.asana_permalink_url ? `<a class="thx-btn thx-btn-primary" href="${esc(t.asana_permalink_url)}" target="_blank" rel="noopener" onclick="event.stopPropagation();">
                        <span class="material-symbols-rounded">open_in_new</span> In Asana
                    </a>` : ''}
                    <button class="thx-btn thx-btn-secondary tp-c-action-done" onclick="event.stopPropagation();tpCompleteAndCelebrate(${t.id});">
                        <span class="material-symbols-rounded">check_circle</span> Erledigt — feiern!
                    </button>
                    <button class="thx-btn thx-btn-secondary" onclick="event.stopPropagation();openDrawer(${t.id});" title="Details">
                        <span class="material-symbols-rounded">info</span>
                    </button>
                </div>`;
        } else if (opts.hero) {
            // Weitere Heute-Karten: kleine Icon-Aktionen (nicht so dominant wie der Fokus, aber konsistent).
            actionsHtml = `
                <div class="tp-c-actions">
                    ${t.asana_permalink_url ? `<a href="${esc(t.asana_permalink_url)}" target="_blank" onclick="event.stopPropagation();" title="In Asana öffnen"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    <button onclick="event.stopPropagation();tpCompleteAndCelebrate(${t.id});" title="Erledigt"><span class="material-symbols-rounded">check_circle</span></button>
                    <button onclick="event.stopPropagation();openDrawer(${t.id});" title="Details" class="tp-c-info-btn"><span class="material-symbols-rounded">info</span></button>
                </div>`;
        } else if (opts.addable) {
            actionsHtml = `
                <div class="tp-c-actions">
                    <button onclick="event.stopPropagation();tpAddToToday(${t.id});" title="In Heute aufnehmen"><span class="material-symbols-rounded">add</span></button>
                    ${t.asana_permalink_url ? `<a href="${esc(t.asana_permalink_url)}" target="_blank" onclick="event.stopPropagation();" title="In Asana"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    <button onclick="event.stopPropagation();openDrawer(${t.id});" title="Details" class="tp-c-info-btn"><span class="material-symbols-rounded">info</span></button>
                </div>`;
        } else if (!opts.hideActions) {
            actionsHtml = `
                <div class="tp-c-actions">
                    ${t.asana_permalink_url ? `<a href="${esc(t.asana_permalink_url)}" target="_blank" onclick="event.stopPropagation();" title="In Asana öffnen"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    <button onclick="event.stopPropagation();tpCompleteAndCelebrate(${t.id});" title="Erledigt"><span class="material-symbols-rounded">check_circle</span></button>
                    <button onclick="event.stopPropagation();openDrawer(${t.id});" title="Details" class="tp-c-info-btn"><span class="material-symbols-rounded">info</span></button>
                </div>`;
        }

        const scoreHtml = t.score ? `<div class="tp-c-score" title="Score">${parseFloat(t.score).toFixed(1)}</div>` : '';

        // Position-Nummer + Pfeil-Up/Down (Step 6 Heute-Spalte / Step 5 etc.)
        const positionHtml = (opts.position !== undefined && opts.position !== null)
            ? `<span class="tp-c-pos">${opts.position}</span>`
            : '';
        const orderActions = opts.position !== undefined
            ? `<div class="tp-c-order">
                  <button class="tp-c-order-btn" onclick="event.stopPropagation();tpMoveCard(${t.id}, -1);" title="Nach oben">
                      <span class="material-symbols-rounded">arrow_drop_up</span>
                  </button>
                  <button class="tp-c-order-btn" onclick="event.stopPropagation();tpMoveCard(${t.id}, +1);" title="Nach unten">
                      <span class="material-symbols-rounded">arrow_drop_down</span>
                  </button>
               </div>`
            : '';

        // Slot-Badge (nur im Cross-Pivot-Modus angezeigt) — zeigt, in welcher Spalte die Task
        // ursprünglich liegt, damit der Bündel-Block die Slot-Verteilung sichtbar lässt.
        // Wording matcht die Phase-7-Spalten (Heute / Nächste Tage / Kommende Tage).
        const slotBadgeHtml = opts.slotBadge && t.daily_slot
            ? `<span class="tp-c-slot tp-c-slot-${t.daily_slot}" style="--b:${bucketColor(t.daily_slot)};">${bucketLabel(t.daily_slot)}</span>`
            : '';

        // Account-Badge: zeigt das Label nur, wenn die Task NICHT aus dem Default-Account kommt.
        // Default-Account-Tasks bekommen kein Badge (sonst klebt es auf 95% aller Karten).
        // Stil: dezenter Outline-Chip in Account-Farbe (kein praller Füll-Block).
        const accountBadgeHtml = (t.asana_account_label && t.asana_account_is_default != 1)
            ? `<span class="tp-c-account" style="color:${esc(t.asana_account_color || '#7c3aed')};" title="Aus Asana-Account &quot;${esc(t.asana_account_label)}&quot;">${esc(t.asana_account_label)}</span>`
            : '';

        // Warten-/Signal-Banner direkt unter dem Head — sofort sichtbar, kein Scrollen.
        let statusBannerHtml = '';
        if (t.waiting_signal == 1) {
            statusBannerHtml = `<div class="tp-c-status-banner is-signal" onclick="event.stopPropagation();tpAckSignal(${t.id});" title="Quittieren">
                <span class="material-symbols-rounded">notifications_active</span>
                <span>Ball ist zurück bei Dir — neue Asana-Aktivität</span>
            </div>`;
        } else if (t.is_waiting == 1) {
            const since = t.waiting_since ? Math.max(0, Math.floor((Date.now() - new Date(t.waiting_since).getTime()) / 86400000)) : 0;
            const sinceText = since === 0 ? 'seit heute' : `seit ${since} Tag${since===1?'':'en'}`;
            const ballAtHtml = t.waiting_on
                ? `Ball bei <strong class="tp-c-ball-name">${esc(t.waiting_on)}</strong> · ${sinceText}`
                : `Warten · ${sinceText}`;
            statusBannerHtml = `<div class="tp-c-status-banner is-waiting">
                <span class="material-symbols-rounded">pause_circle</span>
                <span>${ballAtHtml}</span>
            </div>`;
        }
        // Re-Analyse-Banner (orthogonal zu Warten/Signal — kann zusätzlich erscheinen).
        // Zeigt, was die KI nach einer Asana-Änderung an der Karte gemacht hat.
        if (t.ai_re_analyzed_signal == 1) {
            const summary = t.ai_re_analyzed_summary || 'KI hat die Karte neu bewertet';
            statusBannerHtml += `<div class="tp-c-status-banner is-reanalyzed" onclick="event.stopPropagation();tpAckReanalyzed(${t.id});" title="Quittieren">
                <span class="material-symbols-rounded">auto_awesome</span>
                <span>KI hat aktualisiert: ${esc(summary)}</span>
            </div>`;
        }

        return `
            <div class="${cls}" data-task-id="${t.id}" data-tp-context>
                ${scoreHtml}
                ${orderActions}
                <div class="tp-c-head">
                    ${positionHtml}
                    ${checkHtml}
                    ${slotBadgeHtml}
                    ${accountBadgeHtml}
                    ${custHtml}
                    ${dueHtml}
                    <div class="tp-c-head-spacer"></div>
                    <button class="tp-c-eff${effClass}" onclick="event.stopPropagation();tpEffortPopover(${t.id}, this);" title="Aufwand setzen">${effLabel}</button>
                </div>
                ${statusBannerHtml}
                <div class="tp-c-title">${esc(t.name)}</div>
                ${summaryHtml}
                ${badges.length ? `<div class="tp-c-badges">${badges.join('')}</div>` : ''}
                ${reasonHtml}
                ${actionsHtml}
            </div>
        `;
    }

    function kanbanCardHtml(t, slotReason) {
        const eff = t.effort_minutes || t.ai_effort_estimate || null;
        const custBadge = t.customer_name
            ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr || t.customer_name.substring(0,3))}</span>`
            : '';
        const dueB = dueBadge(t.due_on);
        const effBadge = eff ? `<span class="tp-task-badge">${fmtMin(eff)}</span>` : `<span class="tp-task-badge" style="border:1px dashed var(--slate-300);">Aufwand?</span>`;
        const reasonHtml = slotReason ? `<div class="tp-kanban-card-reason">${esc(slotReason)}</div>` : '';
        return `
            <div class="tp-kanban-card" data-task-id="${t.id}" data-tp-context>
                <div class="tp-kanban-card-name">${esc(t.name)}</div>
                <div class="tp-kanban-card-meta">${custBadge}${dueB}${effBadge}</div>
                ${reasonHtml}
            </div>`;
    }

    // ----- Step 4: Prio schärfen (Kanban nach wählbarer Achse) -----
    function renderStep4(open) {
        const filtered = open.filter(passesFilter);
        if (!state.step4Sel) state.step4Sel = new Set();
        const visibleIds = new Set(filtered.map(t => t.id));
        for (const id of state.step4Sel) if (!visibleIds.has(id)) state.step4Sel.delete(id);
        const selCount = state.step4Sel.size;
        const allVisibleSelected = filtered.length > 0 && filtered.every(t => state.step4Sel.has(t.id));
        if (!state.step4Axis) state.step4Axis = 'bucket';
        const axisOptions = [
            { v: 'bucket',   l: 'Zeitraum' },
            { v: 'effort',   l: 'Aufwand' },
            { v: 'priority', l: 'Wichtigkeit' },
            { v: 'customer', l: 'Kunde' },
        ];
        const axisPills = axisOptions.map(a =>
            `<button class="tp-pill ${state.step4Axis===a.v?'is-active':''}" data-axis4="${a.v}" type="button">${a.l}</button>`
        ).join('');
        const cols = kanbanColumns(filtered, state.step4Axis);
        const board = cols.map(col => `
            <div class="tp-kanban-col">
                <div class="tp-kanban-col-head">
                    ${col.color ? `<span class="tp-kanban-col-dot" style="background:${esc(col.color)};"></span>` : ''}
                    <h3>${esc(col.label)}</h3>
                    <span class="tp-kanban-col-count">${col.tasks.length}</span>
                </div>
                <div class="tp-kanban-body" data-kanban-col="${esc(col.key)}" data-kanban-axis="${state.step4Axis}">
                    ${col.tasks.length === 0
                        ? '<div class="tp-kanban-empty">leer</div>'
                        : col.tasks.map(t => renderUniCard(t, { bulkSelectable: true })).join('')}
                </div>
            </div>
        `).join('');
        return `
        ${renderFilterCard(open)}
        ${selCount > 0 ? renderStep4Bulkbar(selCount) : ''}
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>4 · Prio schärfen</h2>
                <div class="tp-sub">${filtered.length} von ${open.length} sichtbar · Drag verschiebt zwischen Spalten</div>
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:var(--d-fs-xs);color:var(--slate-600);cursor:pointer;">
                    <input type="checkbox" id="tp-s4-all" ${allVisibleSelected ? 'checked' : ''} onchange="tpStep4ToggleAll(this.checked)">
                    Alle sichtbaren markieren
                </label>
                <button class="thx-btn thx-btn-secondary" onclick="tpGoStep(6)">Weiter zur Tagesplanung →</button>
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Spalten nach</span>
                ${axisPills}
            </div>
        </div>
        <div class="tp-card" style="padding:14px;">
            ${filtered.length === 0
                ? '<div class="tp-empty">Keine Treffer im Filter.</div>'
                : `<div class="tp-kanban">${board}</div>`}
        </div>
        ${renderLocalCompletedSection()}`;
    }

    function renderLocalCompletedSection() {
        const done = localCompletedTasks();
        if (!done.length) return '';
        const recent = done.sort((a,b) => (b.completed_at_local || '').localeCompare(a.completed_at_local || '')).slice(0, 50);
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2 style="font-size:var(--d-fs-md);color:var(--slate-600);">Lokal erledigt (${done.length})</h2>
                <div class="tp-sub">In Asana noch offen — können wiederhergestellt werden</div>
            </div>
            <div class="tp-list">
                ${recent.map(t => `
                    <div class="tp-task is-completed" data-task-id="${t.id}">
                        <span></span>
                        <div class="tp-task-body">
                            <div class="tp-task-name">${esc(t.name)}</div>
                            <div class="tp-task-meta">
                                ${t.customer_abbr ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr)}</span>` : ''}
                                <span class="tp-task-inline">abgehakt am ${new Date(t.completed_at_local).toLocaleString('de-DE')}</span>
                            </div>
                        </div>
                        <span></span>
                        <button class="thx-btn thx-btn-secondary" onclick="tpReactivate(${t.id})" style="padding:5px 12px;font-size:var(--d-fs-xs);">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">restart_alt</span>
                            Wieder aktivieren
                        </button>
                    </div>
                `).join('')}
            </div>
        </div>`;
    }

    window.tpReactivate = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/complete', { completed: false });
            App.showNotification('Wieder aktiviert', 'success');
            await load();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };

    function renderStep4Bulkbar(selCount) {
        return `
        <div class="tp-bulkbar tp-bulkbar-sticky">
            <span class="tp-bulkbar-count">${selCount} ausgewählt</span>

            <div class="tp-bulkbar-group">
                <span class="tp-bulkbar-label">Zeitraum:</span>
                <button type="button" onclick="tpStep4Bulk('slot','today')">Heute</button>
                <button type="button" onclick="tpStep4Bulk('slot','tomorrow')">Morgen</button>
                <button type="button" onclick="tpStep4Bulk('slot','day_after')">Übermorgen</button>
                <button type="button" onclick="tpStep4Bulk('slot','rest_week')">Rest Woche</button>
                <button type="button" onclick="tpStep4Bulk('slot','next_week')">Nächste Woche</button>
                <button type="button" onclick="tpStep4Bulk('slot','this_month')">Diesen Monat</button>
                <button type="button" onclick="tpStep4Bulk('slot','later')">Später</button>
                <button type="button" onclick="tpStep4Bulk('slot','occasion')">Bei Gelegenheit</button>
            </div>

            <div class="tp-bulkbar-group">
                <span class="tp-bulkbar-label">Prio:</span>
                <button type="button" onclick="tpStep4Bulk('priority','asap')">ASAP</button>
                <button type="button" onclick="tpStep4Bulk('priority','this_week')">Diese Woche</button>
                <button type="button" onclick="tpStep4Bulk('priority','when_possible')">Wenn möglich</button>
            </div>

            <div class="tp-bulkbar-group">
                <span class="tp-bulkbar-label">Aufwand:</span>
                <select onchange="if(this.value){tpStep4Bulk('effort', null, parseInt(this.value,10));this.value='';}">
                    <option value="">Setzen…</option>
                    <option value="5">5 min</option>
                    <option value="10">10 min</option>
                    <option value="15">15 min</option>
                    <option value="30">30 min</option>
                    <option value="45">45 min</option>
                    <option value="60">1h</option>
                    <option value="90">1h 30m</option>
                    <option value="120">2h</option>
                    <option value="180">3h</option>
                    <option value="240">4h</option>
                    <option value="480">8h</option>
                </select>
            </div>

            <button type="button" onclick="tpStep4Bulk('complete')">✓ Abhaken</button>
            <button type="button" onclick="tpStep4Bulk('ignore')">✕ Ausblenden</button>
            <button type="button" class="tp-bulkbar-cancel" onclick="tpStep4ClearSel()">Auswahl aufheben</button>
        </div>`;
    }

    function passesFilter(t) {
        const f = state.filter;
        if (f.search) {
            const q = f.search.toLowerCase();
            const hay = (t.name + ' ' + (t.notes||'') + ' ' + (t.ai_summary||'') + ' ' + (t.customer_name||'') + ' ' + (t.asana_project_name||'')).toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (f.customer !== 'all') {
            if (f.customer === 'private') { if (!(t.category_hint === 'private' && !t.customer_id)) return false; }
            else if (f.customer === 'unclear') { if (!(!t.customer_id && t.category_hint !== 'private')) return false; }
            else if (f.customer === '0') { if (t.customer_id) return false; }
            else if (String(t.customer_id) !== String(f.customer)) return false;
        }
        if (f.effort !== 'all' && effortBucket(t) !== f.effort) return false;
        // Zeitraum: Multi-Select über die 8 Buckets (leer = alle).
        if (f.bucket && f.bucket.length && !f.bucket.includes(t.daily_slot || 'occasion')) return false;
        if (f.stale === 'fresh' && (t.postpone_count || 0) > 1) return false;
        if (f.stale === 'stale' && (t.postpone_count || 0) < 2) return false;
        if (f.sig === 'high' && (!t.ai_significance || t.ai_significance < 7)) return false;
        if (f.quick === 'only' && t.is_quick_task != 1) return false;
        // Überfällig = Asana-Frist in der Vergangenheit (matcht Asana; lokal nach vorne geplante zählen mit).
        if (f.overdue === 'only') { const dl = t.asana_due_on || t.due_on; if (!(dl && dl < localDate())) return false; }
        // Asana-Abweichung: geplante Frist > 1 Tag nach der Asana-Frist (gleiche Logik wie der ⚠-Marker).
        if (f.deviation === 'only') {
            const dev = (t.asana_due_on && t.due_on) ? Math.round((new Date(t.due_on) - new Date(t.asana_due_on)) / 86400000) : 0;
            if (dev <= 1) return false;
        }
        if (f.recurring === 'only' && t.is_recurring != 1) return false;
        if (f.started === 'only' && !(t.ai_progress_pct > 0)) return false;
        if (f.signal === 'only' && t.waiting_signal != 1) return false;
        if (f.noeffort === 'only' && (t.effort_minutes || t.ai_effort_estimate)) return false;
        if (f.hotcustomer === 'only' && !(t.customer_is_hot == 1 || t.customer_budget_status === 'over' || t.customer_budget_status === 'risk')) return false;
        if (f.context === 'only' && !(t.last_activity && String(t.last_activity).trim())) return false;
        if (f.complexity && f.complexity !== 'all' && (t.ai_complexity || '') !== f.complexity) return false;
        if (f.activity && f.activity !== 'all' && (t.ai_activity_type || 'other') !== f.activity) return false;
        // Warten-Filter: 'hide' (Default) blendet wartende Tasks aus — Signal-Tasks bleiben sichtbar,
        // weil dort der Ball ja zurück ist. 'only' = nur Warten-Tasks. 'all' = beides.
        if (f.waiting === 'hide' && t.is_waiting == 1) return false;
        if (f.waiting === 'only' && t.is_waiting != 1) return false;
        return true;
    }

    function renderFilterCard(open) {
        const f = state.filter;
        const custMap = new Map();
        open.forEach(t => {
            if (t.customer_id) {
                const cur = custMap.get(String(t.customer_id)) || { id: t.customer_id, name: t.customer_name, color: t.customer_color, count: 0, hot: t.customer_is_hot == 1 };
                cur.count++;
                custMap.set(String(t.customer_id), cur);
            }
        });
        const privCount = open.filter(t => !t.customer_id && t.category_hint === 'private').length;
        const unclearCount = open.filter(t => !t.customer_id && t.category_hint !== 'private').length;
        const customers = Array.from(custMap.values()).sort((a,b) => b.count - a.count);
        const pE = (v, l) => `<button class="tp-pill ${f.effort===v?'is-active':''}" data-pill-effort="${v}" type="button">${l}</button>`;
        const pS = (v, l) => `<button class="tp-pill ${f.stale===v?'is-active':''}" data-pill-stale="${v}" type="button">${l}</button>`;
        const pG = (v, l) => `<button class="tp-pill ${f.sig===v?'is-active':''}" data-pill-sig="${v}" type="button">${l}</button>`;
        const pQ = (v, l) => `<button class="tp-pill ${f.quick===v?'is-active':''}" data-pill-quick="${v}" type="button">${l}</button>`;
        const pA = (v, l, cnt) => `<button class="tp-pill ${f.activity===v?'is-active':''}" data-pill-activity="${v}" type="button">${l}${cnt!==undefined?`<span class="tp-pill-count">${cnt}</span>`:''}</button>`;
        const pW = (v, l, cnt) => `<button class="tp-pill ${f.waiting===v?'is-active':''}" data-pill-waiting="${v}" type="button">${l}${cnt!==undefined?`<span class="tp-pill-count">${cnt}</span>`:''}</button>`;
        const activityCounts = {};
        open.forEach(t => { const a = t.ai_activity_type || 'other'; activityCounts[a] = (activityCounts[a]||0) + 1; });
        const activityKeys = Object.keys(ACTIVITY_LABELS).filter(k => activityCounts[k] > 0);
        const quickCount = open.filter(t => t.is_quick_task == 1).length;
        const overdueCount = open.filter(t => { const dl = t.asana_due_on || t.due_on; return dl && dl < localDate(); }).length;
        const deviationCount = open.filter(t => t.asana_due_on && t.due_on && Math.round((new Date(t.due_on) - new Date(t.asana_due_on)) / 86400000) > 1).length;
        const recurringCount = open.filter(t => t.is_recurring == 1).length;
        const startedCount = open.filter(t => t.ai_progress_pct > 0).length;
        const signalCnt = open.filter(t => t.waiting_signal == 1).length;
        const noeffortCount = open.filter(t => !t.effort_minutes && !t.ai_effort_estimate).length;
        const hotCount = open.filter(t => t.customer_is_hot == 1 || t.customer_budget_status === 'over' || t.customer_budget_status === 'risk').length;
        const contextCount = open.filter(t => t.last_activity && String(t.last_activity).trim()).length;
        const waitingCount = open.filter(t => t.is_waiting == 1).length;
        const signalCount = open.filter(t => t.waiting_signal == 1).length;
        const bucketCounts = {};
        open.forEach(t => { const b = t.daily_slot || 'occasion'; bucketCounts[b] = (bucketCounts[b]||0)+1; });
        const pBk = (k, l, color) => `<button class="tp-pill ${(f.bucket||[]).includes(k)?'is-active':''}" data-pill-bucket="${k}" type="button"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${color};"></span>${l}<span class="tp-pill-count">${bucketCounts[k]||0}</span></button>`;
        const pC = (v, l, color, cnt, hot) => `<button class="tp-pill ${String(f.customer)===String(v)?'is-active':''}" data-pill-cust="${v}" type="button" title="Klick = filtern · Rechtsklick = als 🔥 brennend markieren">${hot?'🔥':''}${color?`<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${esc(color)};"></span>`:''}${esc(l)}${cnt!==undefined?`<span class="tp-pill-count">${cnt}</span>`:''}</button>`;
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2 style="font-size:var(--d-fs-md);">Filter</h2>
                <button class="tp-pill" onclick="tpResetFilters()" type="button" style="margin-left:auto;">Zurücksetzen</button>
            </div>
            <div class="tp-filter-row">
                <input type="search" class="tp-search" id="tp-search" placeholder="Suche…" value="${esc(f.search)}">
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Kunde</span>
                ${pC('all','Alle')}
                ${customers.map(c => pC(c.id, c.name, c.color, c.count, c.hot)).join('')}
                ${privCount ? pC('private','Privat','#7c3aed',privCount) : ''}
                ${unclearCount ? pC('unclear','Unklar','#f59e0b',unclearCount) : ''}
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Aufwand</span>
                ${pE('all','Alle')} ${pE('quick','≤30 Min')} ${pE('short','bis 1h')} ${pE('medium','1-4h')} ${pE('half_day','4-6h')} ${pE('full_day','8h+')} ${pE('unknown','?')}
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Zeitraum</span>
                <button class="tp-pill ${!(f.bucket||[]).length?'is-active':''}" data-pill-bucket="all" type="button">Alle</button>
                ${TIME_BUCKETS.map(b => pBk(b.key, b.label, b.color)).join('')}
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Frische</span>
                ${pS('all','Alle')} ${pS('fresh','Frisch')} ${pS('stale','Karteileiche')}
                <span class="tp-pill-label" style="margin-left:12px;">Wichtigkeit</span>
                ${pG('all','Alle')} ${pG('high','Wichtig ≥7')}
                <span class="tp-pill-label" style="margin-left:12px;">Quick</span>
                ${pQ('all','Alle')}
                <button class="tp-pill ${f.quick==='only'?'is-active':''}" data-pill-quick="only" type="button" ${quickCount===0?'data-empty="1"':''}>⚡ Nur Quick<span class="tp-pill-count">${quickCount}</span></button>
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Status</span>
                <button class="tp-pill ${f.overdue==='only'?'is-active':''}" data-pill-overdue="only" type="button" ${overdueCount===0?'data-empty="1"':''}>⏰ Überfällig<span class="tp-pill-count">${overdueCount}</span></button>
                <button class="tp-pill ${f.deviation==='only'?'is-active':''}" data-pill-deviation="only" type="button" ${deviationCount===0?'data-empty="1"':''}>⚠ Asana-Abweichung<span class="tp-pill-count">${deviationCount}</span></button>
                <button class="tp-pill ${f.recurring==='only'?'is-active':''}" data-pill-recurring="only" type="button" ${recurringCount===0?'data-empty="1"':''}>🔄 Wiederkehrend<span class="tp-pill-count">${recurringCount}</span></button>
                <button class="tp-pill ${f.started==='only'?'is-active':''}" data-pill-started="only" type="button" ${startedCount===0?'data-empty="1"':''}>▶ Angefangen<span class="tp-pill-count">${startedCount}</span></button>
                <button class="tp-pill ${f.signal==='only'?'is-active':''}" data-pill-signal="only" type="button" ${signalCnt===0?'data-empty="1"':''}>🔔 Ball zurück<span class="tp-pill-count">${signalCnt}</span></button>
                <button class="tp-pill ${f.noeffort==='only'?'is-active':''}" data-pill-noeffort="only" type="button" ${noeffortCount===0?'data-empty="1"':''}>❓ Ohne Schätzung<span class="tp-pill-count">${noeffortCount}</span></button>
                <button class="tp-pill ${f.hotcustomer==='only'?'is-active':''}" data-pill-hot="only" type="button" ${hotCount===0?'data-empty="1"':''}>🔥 Brennende Kunden<span class="tp-pill-count">${hotCount}</span></button>
                <button class="tp-pill ${f.context==='only'?'is-active':''}" data-pill-context="only" type="button" ${contextCount===0?'data-empty="1"':''}>💬 Asana-Kontext<span class="tp-pill-count">${contextCount}</span></button>
            </div>
            <div class="tp-filter-row">
                <span class="tp-pill-label">Komplexität</span>
                ${['all','low','medium','high'].map(v => `<button class="tp-pill ${(f.complexity||'all')===v?'is-active':''}" data-pill-complexity="${v}" type="button">${{all:'Alle',low:'🧠 niedrig',medium:'🧠 mittel',high:'🧠 hoch'}[v]}</button>`).join('')}
            </div>
            ${activityKeys.length ? `<div class="tp-filter-row">
                <span class="tp-pill-label">Aktivität</span>
                ${pA('all','Alle')}
                ${activityKeys.map(k => pA(k, ACTIVITY_LABELS[k], activityCounts[k])).join('')}
            </div>` : ''}
            ${waitingCount > 0 || signalCount > 0 ? `<div class="tp-filter-row">
                <span class="tp-pill-label">Warten</span>
                ${pW('hide','Aktive (Default)')}
                ${pW('only','⏸ Nur Warten', waitingCount)}
                ${pW('all','Alle')}
                ${signalCount > 0 ? `<span class="tp-pill" style="background:#fef3c7;color:#92400e;cursor:default;">${signalCount} Signal${signalCount===1?'':'e'} zurück bei Dir</span>` : ''}
            </div>` : ''}
        </div>`;
    }

    function renderTaskRow(t) {
        const cls = (t.completed_at_local || t.completed_at_asana) ? 'is-completed' : ((t.postpone_count || 0) >= 2 ? 'is-stale' : dueClass(t.due_on));
        const isSelected = state.step4Sel && state.step4Sel.has(t.id);
        const selectedCls = isSelected ? ' is-selected' : '';
        const eff = t.effort_minutes || t.ai_effort_estimate || null;
        const effLabel = eff ? fmtMin(eff) : 'Aufwand?';
        const effClass = (t.effort_minutes ? '' : ' is-ai');
        const ep = effectivePriority(t);

        // Minimal-Set Badges — nur wenn semantisch relevant
        const badges = [];
        if (t.is_quick_task == 1) badges.push(`<span class="tp-task-badge tp-quick" title="Quick-Task (2-10 Min)">⚡ Quick</span>`);
        if (ep === 'asap') badges.push(`<span class="tp-task-badge tp-asap">ASAP</span>`);
        if (t.ai_significance && t.ai_significance >= 8) badges.push(`<span class="tp-task-badge tp-sig-high" title="KI-Bedeutsamkeit ${t.ai_significance}/10">wichtig</span>`);
        if ((t.postpone_count || 0) >= 2) badges.push(`<span class="tp-task-badge tp-stale" title="${t.postpone_count}x verschoben">${t.postpone_count}× verschoben</span>`);

        // Meta-Zeile: Kunde · Projekt · Deadline (kompakt, sekundär)
        const metaParts = [];
        if (t.customer_name) metaParts.push(`<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr || t.customer_name.substring(0,3))}</span><span class="tp-task-inline">${esc(t.customer_name)}</span>`);
        if (t.asana_project_name) metaParts.push(`<span class="tp-task-inline">${esc(t.asana_project_name)}</span>`);
        const dueB = dueBadge(t.due_on);
        if (dueB) metaParts.push(dueB);

        // KI-Summary, aber filterte „lieblose"-Fallback-Texte raus
        const isFallback = !t.ai_summary || t.ai_summary === '(KI lieferte keine Zusammenfassung)' || t.ai_summary.startsWith('(KI-Analyse fehlgeschlagen');
        const summary = isFallback ? '' : `<div class="tp-task-summary">${esc(t.ai_summary)}</div>`;

        return `
            <div class="tp-task ${cls}${selectedCls}" data-task-id="${t.id}" data-tp-context>
                <input type="checkbox" class="tp-task-check" data-step4-sel ${isSelected ? 'checked' : ''} onclick="event.stopPropagation();" title="Für Bulk-Aktion markieren">
                <div class="tp-task-body" data-tp-open>
                    <div class="tp-task-name">${esc(t.name)}</div>
                    ${summary}
                    <div class="tp-task-meta">
                        ${metaParts.join('<span style="color:var(--slate-300);">·</span>')}
                        ${badges.length ? '<span style="flex-basis:100%;height:2px;"></span>' + badges.join('') : ''}
                    </div>
                </div>
                <button class="tp-task-effort${effClass}" data-tp-effort title="Aufwand setzen" onclick="event.stopPropagation();tpEffortPopover(${t.id}, this);">${effLabel}</button>
                <div class="tp-task-rightcol">
                    ${t.score ? `<span class="tp-task-score" title="Score">${parseFloat(t.score).toFixed(1)}</span>` : ''}
                    <button class="tp-task-edit-btn" onclick="event.stopPropagation();openDrawer(${t.id});" title="Details">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                </div>
            </div>`;
    }

    // ----- Step 5: Tagesplanung Kanban -----
    // ----- Step 5 NEU: Beobachten — wartende Tasks im Auge behalten, KI flaggt Überfällige -----
    function renderStep5_waiting() {
        // Alle Tasks, die der User als wartend markiert hat — auch wenn das Auto-Wake-Signal
        // (waiting_signal=1) gerade gefeuert hat. Sonst würden Tasks aus der Beobachten-Liste
        // verschwinden, sobald Asana sich gerührt hat, was der User explizit weiter beobachten will.
        const allWaiting = openTasks().filter(t => t.is_waiting == 1 || t.waiting_signal == 1 || (t.waiting_on && t.waiting_on.length > 0));
        const now = Date.now();
        const dayDiff = (iso) => Math.floor((now - new Date(iso).getTime()) / 86400000);

        // Trennung: aktueller Horizont (Hauptliste) vs. ferne Buckets (später/bei Gelegenheit, separat einblendbar)
        const longHorizonSlots = ['later', 'occasion'];
        const longHorizon = allWaiting.filter(t => longHorizonSlots.includes(t.daily_slot));
        const waiting = allWaiting.filter(t => !longHorizonSlots.includes(t.daily_slot));

        // Buckets: 'Heute frisch' / 'Ein paar Tage' / 'Zu lang!' (>=5 Tage) / 'Mit Frist überfällig'
        const fresh = [], few = [], tooLong = [], pastDue = [];
        waiting.forEach(t => {
            const dueDiff = t.due_on ? Math.floor((new Date(t.due_on).getTime() - now) / 86400000) : null;
            if (dueDiff !== null && dueDiff < 0) {
                pastDue.push({ t, sinceWait: t.waiting_since ? dayDiff(t.waiting_since) : 0, dueDiff });
                return;
            }
            const since = t.waiting_since ? dayDiff(t.waiting_since) : 0;
            if (since >= 5) tooLong.push({ t, sinceWait: since });
            else if (since >= 2) few.push({ t, sinceWait: since });
            else fresh.push({ t, sinceWait: since });
        });
        // Wichtigste oben: überfällig zuerst, dann 'zu lang', dann ein paar Tage, dann frisch
        const groups = [
            { key: 'pastDue', label: 'Mit Frist überfällig', color: '#dc2626', items: pastDue.sort((a,b) => a.dueDiff - b.dueDiff), hint: 'Frist ist abgelaufen — nachhaken oder Frist verlängern' },
            { key: 'tooLong', label: 'Schon lange ruhig', color: '#ea580c', items: tooLong.sort((a,b) => b.sinceWait - a.sinceWait), hint: 'Bald 1 Woche ohne Bewegung — vielleicht erinnern' },
            { key: 'few',     label: 'Ein paar Tage ruhig', color: '#f59e0b', items: few.sort((a,b) => b.sinceWait - a.sinceWait), hint: 'Noch im Rahmen, aber im Blick behalten' },
            { key: 'fresh',   label: 'Frisch delegiert',    color: '#84cc16', items: fresh.sort((a,b) => b.sinceWait - a.sinceWait), hint: 'Gerade abgegeben — meist nichts zu tun' },
        ];

        // Sortier-Umschaltung: standardmaessig bleibt die Liegezeit-/Frist-Reihenfolge der Buckets,
        // optional umsortierbar nach Kunde / Frist-Datum / "wartet auf". Wirkt INNERHALB der Buckets,
        // damit die Dringlichkeits-Gruppen (ueberfaellig / lange ruhig / …) erhalten bleiben.
        if (state.waitingSort === undefined) state.waitingSort = 'standard';
        const custOf = (t) => t.customer_name || (t.category_hint === 'private' ? 'Privat' : 'zzz');
        const sortItems = (items) => {
            if (state.waitingSort === 'standard') return items;
            const arr = items.slice();
            if (state.waitingSort === 'customer') {
                arr.sort((a,b) => custOf(a.t).localeCompare(custOf(b.t), 'de') || b.sinceWait - a.sinceWait);
            } else if (state.waitingSort === 'due') {
                // Ohne Frist immer ans Ende, sonst aufsteigend nach Datum (frueheste zuerst).
                arr.sort((a,b) => (a.t.due_on || '9999-12-31').localeCompare(b.t.due_on || '9999-12-31') || b.sinceWait - a.sinceWait);
            } else if (state.waitingSort === 'waiting_on') {
                const w = (t) => (t.waiting_on || 'zzz').toLowerCase();
                arr.sort((a,b) => w(a.t).localeCompare(w(b.t), 'de') || b.sinceWait - a.sinceWait);
            }
            return arr;
        };

        const renderGroup = (g) => g.items.length === 0 ? '' : `
            <div class="tp-card" style="padding:14px;">
                <div class="tp-card-head">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${g.color};"></span>
                    <h2 style="font-size:var(--d-fs-md);margin:0;">${g.label}</h2>
                    <div class="tp-sub">${g.items.length} ${g.items.length===1?'Task':'Tasks'} · ${esc(g.hint)}</div>
                </div>
                <div class="tp-waiting-list">
                    ${sortItems(g.items).map(it => renderWaitingRow(it.t, it.sinceWait, it.dueDiff)).join('')}
                </div>
            </div>`;

        const totalWaiting = waiting.length;
        const totalLong = longHorizon.length;
        if (state.showLongWaiting === undefined) state.showLongWaiting = false;

        // Long-horizon-Sektion (mid_term + long_term) — eingeklappt by default
        const longSection = totalLong === 0 ? '' : `
            <div class="tp-card" style="padding:14px;">
                <div class="tp-card-head" style="cursor:pointer;" onclick="tpToggleLongWaiting()">
                    <span class="material-symbols-rounded" style="color:var(--slate-500);">${state.showLongWaiting ? 'expand_less' : 'expand_more'}</span>
                    <h2 style="font-size:var(--d-fs-md);">Mittel-/Langfristig im Blick</h2>
                    <div class="tp-sub">${totalLong} ${totalLong===1?'Task':'Tasks'} mit Horizont > 30 Tage · wartend, kein akuter Druck</div>
                </div>
                ${state.showLongWaiting ? `<div class="tp-waiting-list" style="margin-top:8px;">
                    ${sortItems(longHorizon.map(t => ({
                        t,
                        sinceWait: t.waiting_since ? dayDiff(t.waiting_since) : 0,
                        dueDiff: t.due_on ? Math.floor((new Date(t.due_on).getTime() - now) / 86400000) : null,
                    })).sort((a,b) => (a.t.due_on || '9999').localeCompare(b.t.due_on || '9999')))
                        .map(it => renderWaitingRow(it.t, it.sinceWait, it.dueDiff)).join('')}
                </div>` : ''}
            </div>`;

        const sortPills = (totalWaiting === 0 && totalLong === 0) ? '' : `
            <div class="tp-waiting-sortbar">
                <span class="tp-pill-label">Sortieren</span>
                <button class="tp-pill ${state.waitingSort==='standard'?'is-active':''}" onclick="tpWaitingSort('standard')">Standard</button>
                <button class="tp-pill ${state.waitingSort==='customer'?'is-active':''}" onclick="tpWaitingSort('customer')">Kunde</button>
                <button class="tp-pill ${state.waitingSort==='due'?'is-active':''}" onclick="tpWaitingSort('due')">Datum</button>
                <button class="tp-pill ${state.waitingSort==='waiting_on'?'is-active':''}" onclick="tpWaitingSort('waiting_on')">Wartet auf</button>
            </div>`;

        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>5 · Beobachten</h2>
                <div class="tp-sub">${totalWaiting} ${totalWaiting===1?'Task wartet':'Tasks warten'} im aktuellen Horizont${totalLong > 0 ? ` · ${totalLong} mittel-/langfristig (unten)` : ''} — Du bist beteiligt, hast aber gerade nichts zu tun. Schau, ob jemand verstaubt.</div>
            </div>
            ${sortPills}
        </div>
        ${totalWaiting === 0 && totalLong === 0
            ? '<div class="tp-card" style="padding:28px;text-align:center;color:var(--slate-500);">Aktuell wartet nichts. Sobald Du Tasks auf <strong>Warten</strong> setzt, erscheinen sie hier.</div>'
            : groups.map(renderGroup).join('') + longSection}
        `;
    }

    function renderWaitingRow(t, sinceWait, dueDiff) {
        const custColor = t.customer_color || '#94a3b8';
        const custName = t.customer_name || (t.category_hint === 'private' ? 'Privat' : 'Ohne Kunde');
        const ballHtml = t.waiting_on
            ? `Ball bei <strong class="tp-c-ball-name">${esc(t.waiting_on)}</strong>`
            : 'Warten';
        const sinceText = sinceWait === 0 ? 'seit heute' : `seit ${sinceWait} Tag${sinceWait===1?'':'en'}`;

        // Asana-Fristanzeige, immer wenn ein due_on gesetzt ist — nicht nur bei überfällig.
        let dueText = '';
        if (t.due_on) {
            const dateStr = new Date(t.due_on).toLocaleDateString('de-DE', { day: '2-digit', month: 'short' });
            if (dueDiff !== undefined && dueDiff !== null && dueDiff < 0) {
                dueText = ` · <span class="tp-waiting-due is-overdue">Frist ${esc(dateStr)} · ${Math.abs(dueDiff)}d überfällig</span>`;
            } else if (dueDiff === 0) {
                dueText = ` · <span class="tp-waiting-due is-today">Frist heute (${esc(dateStr)})</span>`;
            } else if (dueDiff > 0 && dueDiff <= 7) {
                dueText = ` · <span class="tp-waiting-due is-soon">Frist in ${dueDiff} Tag${dueDiff===1?'':'en'} (${esc(dateStr)})</span>`;
            } else {
                dueText = ` · <span class="tp-waiting-due">Frist ${esc(dateStr)}</span>`;
            }
        } else {
            dueText = ` · <span class="tp-waiting-due is-undated">ohne Frist</span>`;
        }

        return `
            <div class="tp-waiting-row" data-task-id="${t.id}" data-tp-context>
                <div class="tp-waiting-main">
                    <div class="tp-waiting-name">${esc(t.name)}</div>
                    <div class="tp-waiting-meta">
                        <span class="tp-archive-cust"><span class="tp-archive-cust-dot" style="background:${esc(custColor)};"></span>${esc(custName)}</span>
                        <span>${ballHtml} · ${sinceText}${dueText}</span>
                    </div>
                </div>
                <div class="tp-waiting-actions">
                    ${t.asana_permalink_url ? `<a class="thx-btn thx-btn-secondary" href="${esc(t.asana_permalink_url)}" target="_blank" onclick="event.stopPropagation();" title="In Asana"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    <button class="thx-btn thx-btn-secondary" onclick="event.stopPropagation();tpSetWaiting(${t.id});" title="Wieder aktiv">
                        <span class="material-symbols-rounded">play_circle</span> Wieder aktiv
                    </button>
                    <button class="thx-btn thx-btn-secondary" onclick="event.stopPropagation();openDrawer(${t.id});" title="Details"><span class="material-symbols-rounded">info</span></button>
                </div>
            </div>`;
    }

    function renderStep5() {
        // Phase 6 (alt Phase 5) ist ein 'Tagesplanungs'-View — immer nur aktive Tasks, wartende ausgeblendet.
        // Der globale state.filter (Phase 4) wird hier bewusst NICHT angewendet, sonst würden
        // Filtereinstellungen aus 'Prio schärfen' unsichtbar in die Tagesplanung mitwandern.
        // Lokale Suche (state.step5Search) ist eigen — entkoppelt von Phase-4-Suche.
        const q = (state.step5Search || '').trim().toLowerCase();
        const matchesSearch = (t) => {
            if (!q) return true;
            const hay = (t.name + ' ' + (t.notes||'') + ' ' + (t.ai_summary||'') + ' ' + (t.customer_name||'') + ' ' + (t.asana_project_name||'')).toLowerCase();
            return hay.includes(q);
        };
        const open = openTasks().filter(t => t.is_waiting != 1 && matchesSearch(t));
        const slots = TIME_BUCKETS.map(b => b.key);
        const labels = BUCKET_LABEL;
        const colors = BUCKET_COLOR;
        const grouped = {};
        slots.forEach(s => grouped[s] = []);
        open.forEach(t => { (grouped[t.daily_slot] || grouped.occasion).push(t); });
        const sumEffort = (arr) => arr.reduce((a, t) => a + (t.effort_minutes || t.ai_effort_estimate || 60), 0);
        const board = slots.map(s => `
            <div class="tp-kanban-col">
                <div class="tp-kanban-col-head">
                    <span class="tp-kanban-col-dot" style="background:${colors[s]};"></span>
                    <h3>${labels[s]}</h3>
                    <span class="tp-kanban-col-count">${grouped[s].length} · ${fmtMin(sumEffort(grouped[s]))}</span>
                </div>
                <div class="tp-kanban-body" data-slot-col="${s}">
                    ${grouped[s].length === 0
                        ? `<div class="tp-kanban-empty">Karten hierher nach <strong>${labels[s]}</strong> ziehen</div>`
                        : grouped[s].sort((a,b) => (b.score||0)-(a.score||0)).map(t => renderUniCard(t, { reason: state.slotReasoning?.[t.id] })).join('')}
                </div>
            </div>
        `).join('');
        const todayCount = grouped.today.length;
        const cap = state.planCapacity || { today: 240, tomorrow: 240, rest_week: 1200 };
        const summary = state.slotSummary;
        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>6 · Tagesplanung</h2>
                <div class="tp-sub">Drag&amp;Drop oder KI-Vorsortierung. <strong>Heute</strong> geht in Schritt 7.${q ? ` · <strong>${open.length}</strong> Treffer für „${esc(q)}"` : ''}</div>
                <input type="search" class="tp-search" id="tp-step5-search" placeholder="Suchen (Titel, Kunde, Projekt, Notes)…" value="${esc(state.step5Search || '')}" style="min-width:280px;margin-left:auto;">
                ${todayCount > 0 ? `<button class="thx-btn thx-btn-primary" onclick="tpGoStep(7)" style="margin-left:auto;">Zum Tagesplan (${todayCount}) →</button>` : ''}
            </div>
            <div class="tp-cap-row">
                <span class="tp-pill-label">Kapazität</span>
                <label class="tp-cap-input">Heute <input type="number" id="tp-cap-today" min="30" max="960" value="${cap.today}" step="30"> Min</label>
                <label class="tp-cap-input">Morgen <input type="number" id="tp-cap-tom" min="30" max="960" value="${cap.tomorrow}" step="30"> Min</label>
                <label class="tp-cap-input">Woche <input type="number" id="tp-cap-week" min="60" max="3000" value="${cap.rest_week}" step="60"> Min</label>
                <button class="thx-btn thx-btn-primary" onclick="tpSortSlots()" id="tp-sort-btn">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                    KI-Vorsortierung anwenden
                </button>
                <div style="margin-left:auto;font-size:11px;color:var(--slate-500);">Berücksichtigt Score, Bedeutung, Aufwand, Deadline, Kunden-Budget &amp; Projektplanner-Risiko.</div>
            </div>
            ${summary ? `<div class="tp-summary" style="margin-top:10px;">${esc(summary)}</div>` : ''}
        </div>
        <div class="tp-card" style="padding:14px;">
            <div class="tp-kanban">${board}</div>
        </div>`;
    }

    // ----- Step 6: Output — Kanban (Heute / Quick Wins / Morgen / Diese Woche) -----
    function renderStep6() {
        // Phase 6 ist der Tagesplan-Output — immer nur aktive Tasks. Wartende Tasks haben hier
        // nichts zu suchen (Ball nicht bei mir), Signal-Tasks (is_waiting=0, waiting_signal=1)
        // sind dagegen weiterhin sichtbar mit ihrem Banner.
        // Der globale state.filter wird hier bewusst NICHT angewendet — Phase 4-Filter bleiben
        // auf Phase 4 beschränkt.
        const s7q = (state.step7Search || '').trim().toLowerCase();
        const matchesStep7 = (t) => !s7q || (t.name + ' ' + (t.notes||'') + ' ' + (t.ai_summary||'') + ' ' + (t.customer_name||'') + ' ' + (t.asana_project_name||'')).toLowerCase().includes(s7q);
        const open = openTasks().filter(t => t.is_waiting != 1 && matchesStep7(t));
        // Spalten zusammenstellen — Heute-Reihenfolge respektiert state.todayOrder (Pfeil-Buttons),
        // sonst Score absteigend als Default.
        if (!state.todayOrder) state.todayOrder = [];
        const todayStr = localDate();
        const todayUnsorted = open.filter(t => t.planned_for_date === todayStr);
        // Neue Tasks ans Ende anhängen, abwesende aus Order entfernen
        const todayIds = new Set(todayUnsorted.map(t => t.id));
        state.todayOrder = state.todayOrder.filter(id => todayIds.has(id));
        todayUnsorted.sort((a,b) => (b.score||0) - (a.score||0)).forEach(t => { if (!state.todayOrder.includes(t.id)) state.todayOrder.push(t.id); });
        const byId = new Map(todayUnsorted.map(t => [t.id, t]));
        const today = state.todayOrder.map(id => byId.get(id)).filter(Boolean);
        const tomorrow = open.filter(t => t.daily_slot === 'tomorrow' || t.daily_slot === 'day_after').sort((a,b) => (b.score||0) - (a.score||0));
        const thisWeek = open.filter(t => t.daily_slot === 'rest_week' || t.daily_slot === 'next_week').sort((a,b) => (b.score||0) - (a.score||0));
        // Heute/überfällig fällig, aber NICHT eingeplant — Kandidaten zum gezielten Reinnehmen (nicht der Plan selbst).
        const dueToday = open.filter(t => t.daily_slot === 'today' && t.planned_for_date !== todayStr).sort((a,b) => (b.score||0) - (a.score||0));
        // Quick-Wins-Heuristik:
        //   1. echte Quick-Tasks (KI-erkannt, 2-10 Min)
        //   2. Aufwand <= 10 Min
        //   3. wiederkehrende Tasks deren nächste Fälligkeit nahe ist (Tag 0..5)
        //      → erscheinen 5 Tage vor Frist als Erinnerung-Quick-Win
        const recurringNearDue = (t) => {
            if (t.is_recurring != 1 || !t.due_on) return false;
            const diff = Math.floor((new Date(t.due_on).getTime() - Date.now()) / 86400000);
            return diff <= 5;
        };
        const quickWins = open.filter(t =>
            t.daily_slot !== 'today' &&
            t.quick_win_user_excluded != 1 &&  // User-Override: explizit raus = NIE wieder rein
            (t.is_quick_task == 1
                || (t.effort_minutes && t.effort_minutes <= 10)
                || (!t.effort_minutes && t.ai_effort_estimate && t.ai_effort_estimate <= 10)
                || recurringNearDue(t))
        ).sort((a,b) => (b.score||0) - (a.score||0));

        const todaySumEffort = today.reduce((a, t) => a + ((t.effort_minutes || t.ai_effort_estimate || 60)), 0);
        const todayDoneCount = state.tasks.filter(t => t.completed_at_local && isSameDay(t.completed_at_local)).length;

        // Smart Labels mit Datum und Workday-Aware:
        // - Heute: Wochentag + Datum
        // - 'Morgen'-Slot heisst eigentlich "nächste 1-2 Arbeitstage" — am Freitag also Mo,
        //   am Samstag sowieso. Skip Sa/So bei der nächsten-Workday-Berechnung.
        // - 'Diese Woche' = im Laufe der kommenden 7 Tage
        const WEEKDAYS = ['So','Mo','Di','Mi','Do','Fr','Sa'];
        const fmtDate = (d) => `${WEEKDAYS[d.getDay()]} ${d.getDate()}.${d.getMonth()+1}.`;
        const now = new Date(); now.setHours(0,0,0,0);
        const nextWorkday = (() => {
            const d = new Date(now); d.setDate(d.getDate() + 1);
            // wenn Sa (6) oder So (0) → weiterspringen bis Mo
            while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() + 1);
            return d;
        })();
        const dayAfterNextWorkday = (() => {
            const d = new Date(nextWorkday); d.setDate(d.getDate() + 1);
            while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() + 1);
            return d;
        })();
        const sevenDaysOut = (() => { const d = new Date(now); d.setDate(d.getDate() + 7); return d; })();

        const tomorrowLabel = nextWorkday.getDay() === 1 && now.getDay() >= 5
            ? `📅 Nächste Tage · ab ${fmtDate(nextWorkday)}`
            : `📅 Nächste 1-2 Tage · ${fmtDate(nextWorkday)} / ${fmtDate(dayAfterNextWorkday)}`;

        // Spalten = 'Heute eingeplant' (committet) + die 8 Frist-Buckets wie in Phase 6 (gleiche Optik).
        // Bucket-Spalten: Kandidaten (noch nicht eingeplant), '+' holt in den Tagesplan, Ziehen re-plant die Frist.
        const candidatesFor = (key) => open.filter(t => t.daily_slot === key && t.planned_for_date !== todayStr).sort((a,b) => (b.score||0) - (a.score||0));
        const cols = [
            { key: 'committed', label: (today.length ? `🎯 Heute eingeplant · ${fmtDate(now)} · ` + fmtMin(todaySumEffort) : `🎯 Heute eingeplant · ${fmtDate(now)}`), color: '#dc2626', tasks: today, drop: 'committed', acceptDrop: true, hero: true },
            ...TIME_BUCKETS.map(b => ({ key: b.key, label: bucketLabel(b.key), color: b.color, tasks: candidatesFor(b.key).slice(0, 30), drop: b.key, acceptDrop: true, addable: true })),
        ];

        const board = cols.map(col => {
            const isHeroCol = !!col.hero;
            const isMutedCol = !col.hero;
            // Heute-Spalte: Pivot-Modus rendert Bündel statt flacher Liste.
            const useBundles = isHeroCol && state.step6Pivot !== 'list' && col.tasks.length > 0;
            return `
            <div class="tp-kanban-col ${isHeroCol ? 'tp-col-today' : 'tp-col-muted'}">
                <div class="tp-kanban-col-head">
                    <span class="tp-kanban-col-dot" style="background:${esc(col.color)};"></span>
                    <h3>${col.label}</h3>
                    <span class="tp-kanban-col-count">${col.tasks.length}</span>
                </div>
                <div class="tp-kanban-body" data-step6-col="${col.drop}" data-step6-accept="${col.acceptDrop ? '1' : '0'}">
                    ${col.tasks.length === 0
                        ? `<div class="tp-kanban-empty">${isHeroCol ? 'Karten hierhin ziehen' : 'leer'}</div>`
                        : useBundles
                          ? renderHeuteBundles(col.tasks, state.step6Pivot)
                          : col.tasks.map((t, i) => {
                                // Heute-Spalte: alle Karten = Hero (volle Aktionen + Begründung).
                                // Die erste bekommt zusätzlich 'is-focus' für leicht größere Optik.
                                if (isHeroCol) return renderUniCard(t, { hero: true, focus: i === 0, position: i + 1 });
                                // Andere Spalten: kompakt + '+'-Button zum Reinholen
                                return renderUniCard(t, { muted: true, addable: true });
                            }).join('')}
                </div>
            </div>`;
        }).join('');

        const summary = state.plan && state.plan.summary ? `<div class="tp-summary">${esc(state.plan.summary)}</div>` : '';

        const pv = state.step6Pivot;
        // Pivot ist sinnvoll, sobald mehr als eine relevante Task existiert (Heute + Morgen + Woche zählen).
        const pivotPoolSize = today.length + tomorrow.length + thisWeek.length;
        const showPivotToggle = pivotPoolSize > 1;
        const pivotToggle = showPivotToggle ? `
            <div class="tp-pivot-toggle" title="Tasks bündeln">
                <button type="button" class="tp-pivot-btn ${pv==='list'?'is-active':''}" onclick="tpSetStep6Pivot('list')">
                    <span class="material-symbols-rounded">view_list</span>Liste
                </button>
                <button type="button" class="tp-pivot-btn ${pv==='customer'?'is-active':''}" onclick="tpSetStep6Pivot('customer')">
                    <span class="material-symbols-rounded">business</span>nach Kunde
                </button>
                <button type="button" class="tp-pivot-btn ${pv==='activity'?'is-active':''}" onclick="tpSetStep6Pivot('activity')">
                    <span class="material-symbols-rounded">category</span>nach Aktivität
                </button>
            </div>` : '';

        // Im Pivot-Modus löst sich das 4-Spalten-Layout auf — eine einspaltige, spaltenübergreifende
        // Bündel-Ansicht über Heute + Morgen + Diese Woche. Slot-Badge bleibt auf jeder Karte.
        const isPivot = pv === 'customer' || pv === 'activity';
        const crossPool = open.filter(t => t.planned_for_date === localDate());
        const crossSumMin = crossPool.reduce((a, t) => a + (t.effort_minutes || t.ai_effort_estimate || 60), 0);
        const subline = isPivot
            ? `${crossPool.length} ${crossPool.length===1?'Task':'Tasks'} übergreifend · ${fmtMin(crossSumMin)} Aufwand`
            : `${today.length} ${today.length===1?'Task':'Tasks'} für heute · ${fmtMin(todaySumEffort)} Aufwand`;

        const mainSection = isPivot
            ? `<div class="tp-card" style="padding:18px;">
                  ${crossPool.length === 0
                    ? '<div class="tp-empty">Keine Tasks in Heute / Morgen / Diese Woche.</div>'
                    : `<div class="tp-bundles-cross">${renderCrossColumnBundles(crossPool, pv, state.todayOrder || [])}</div>`}
               </div>`
            : `<div class="tp-card" style="padding:14px;">
                  <div class="tp-step6-board">${board}</div>
               </div>`;

        return `
        <div class="tp-card">
            <div class="tp-card-head">
                <h2>7 · Tagesplan</h2>
                <div class="tp-sub">${subline}</div>
                ${todayDoneCount > 0 ? `<div class="tp-day-progress" style="margin-left:8px;">Schon ${todayDoneCount} erledigt ✓</div>` : ''}
                <input type="search" class="tp-search" id="tp-step7-search" placeholder="Suchen (Titel, Kunde, Projekt, Notes)…" value="${esc(state.step7Search || '')}" style="min-width:260px;margin-left:auto;">
                ${pivotToggle}
                <button class="thx-btn thx-btn-secondary" onclick="tpRefreshPlan()" title="KI-Begründung pro Heute-Task" ${showPivotToggle ? '' : 'style="margin-left:auto;"'}>
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                    ${state.plan ? 'KI-Sequenz aktualisieren' : 'Mit KI-Begründungen versehen'}
                </button>
            </div>
        </div>
        ${summary}
        ${mainSection}`;
    }

    // Spaltenübergreifende Bündel über Heute + Morgen + Diese Woche.
    // Jedes Bündel zeigt alle zugehörigen Tasks aus allen Slots — damit der Nutzer in einer
    // 'themed session' direkt alles für einen Kunden / eine Aktivität sieht und in einem Rutsch wegmacht.
    //
    // todayOrder = state.todayOrder, die manuelle Reihenfolge der Heute-Tasks (gesetzt per Drag/Pfeil-Buttons
    // in der Liste). Sie bestimmt sowohl die Bündel-Reihenfolge als auch die Heute-Reihenfolge im Bündel —
    // damit Änderungen an der Liste sich 1:1 hier widerspiegeln.
    function renderCrossColumnBundles(pool, pivot, todayOrder) {
        const isCust = pivot === 'customer';
        const keyOf = (t) => isCust
            ? (t.customer_id ? 'c' + t.customer_id : (t.category_hint === 'private' ? 'private' : 'ohne'))
            : (t.ai_activity_type || 'other');
        const labelOf = (t) => isCust
            ? (t.customer_name || (t.category_hint === 'private' ? 'Privat' : 'Ohne Kunde'))
            : activityLabel(t.ai_activity_type);

        // Position-Lookup: task_id → Position in der manuellen Heute-Reihenfolge (1-basiert).
        // Tasks ohne Eintrag bekommen Infinity (landen ans Ende).
        const todayPos = new Map();
        (todayOrder || []).forEach((id, idx) => todayPos.set(id, idx + 1));
        const positionOf = (t) => todayPos.has(t.id) ? todayPos.get(t.id) : Infinity;

        const groups = new Map();
        pool.forEach(t => {
            const k = keyOf(t);
            if (!groups.has(k)) {
                groups.set(k, {
                    key: k, label: labelOf(t),
                    color: isCust ? (t.customer_color || '#94a3b8') : null,
                    tasks: [], totalMin: 0,
                    bySlot: { today: 0, tomorrow: 0, week: 0 },
                    minTodayPos: Infinity,  // beste (kleinste) Heute-Position im Bündel
                });
            }
            const g = groups.get(k);
            g.tasks.push(t);
            g.totalMin += t.effort_minutes || t.ai_effort_estimate || 60;
            // Anzeige-Gruppen: Heute · Morgen (inkl. Übermorgen) · Woche (Rest/Nächste Woche)
            const slotGroup = t.daily_slot === 'today' ? 'today'
                : (t.daily_slot === 'tomorrow' || t.daily_slot === 'day_after') ? 'tomorrow'
                : (t.daily_slot === 'rest_week' || t.daily_slot === 'next_week') ? 'week' : null;
            if (slotGroup) g.bySlot[slotGroup]++;
            if (t.daily_slot === 'today') {
                const p = positionOf(t);
                if (p < g.minTodayPos) g.minTodayPos = p;
            }
        });

        // Bündel-Reihenfolge:
        //   1. minTodayPos aufsteigend — das Bündel mit der Heute-#1-Karte kommt zuerst,
        //      dann das mit Heute-#2 usw. (also exakt die manuelle Liste-Reihenfolge).
        //   2. Bündel ohne Heute-Task ranken hinten, sortiert nach Gesamtaufwand.
        const sortedGroups = Array.from(groups.values()).sort((a, b) => {
            if (a.minTodayPos !== b.minTodayPos) return a.minTodayPos - b.minTodayPos;
            return b.totalMin - a.totalMin;
        });

        // Innerhalb eines Bündels:
        //   - Heute-Tasks: in todayOrder-Reihenfolge (die manuelle Sortierung der Liste)
        //   - Morgen → Diese Woche dahinter, je Slot nach Score
        const slotRank = { today: 0, tomorrow: 1, day_after: 2, rest_week: 3, next_week: 4 };
        sortedGroups.forEach(g => g.tasks.sort((a, b) => {
            const sa = slotRank[a.daily_slot] ?? 9;
            const sb = slotRank[b.daily_slot] ?? 9;
            if (sa !== sb) return sa - sb;
            if (a.daily_slot === 'today') return positionOf(a) - positionOf(b);
            return (b.score || 0) - (a.score || 0);
        }));

        // Fokus-Karte = die Heute-#1 (oder, falls keine Heute-Tasks, erste Karte der ersten Gruppe)
        const focusTaskId = sortedGroups[0]?.tasks.find(t => t.daily_slot === 'today')?.id
                         || sortedGroups[0]?.tasks[0]?.id;

        return sortedGroups.map(g => {
            const dotHtml = isCust
                ? `<span class="tp-bundle-cust-dot" style="background:${esc(g.color)};"></span>`
                : '';
            const slotParts = [
                g.bySlot.today    ? `🎯 ${g.bySlot.today} Heute`    : '',
                g.bySlot.tomorrow ? `📅 ${g.bySlot.tomorrow} Morgen` : '',
                g.bySlot.week ? `🗓 ${g.bySlot.week} Woche` : '',
            ].filter(Boolean).join('<span class="tp-meta-sep">·</span>');

            return `
            <div class="tp-bundle" data-bundle-key="${esc(g.key)}">
                <div class="tp-bundle-head">
                    ${dotHtml}
                    <span class="tp-bundle-label">${esc(g.label)}</span>
                    <span class="tp-bundle-meta">${slotParts}<span class="tp-meta-sep">·</span>${fmtMin(g.totalMin)}</span>
                </div>
                <div class="tp-bundle-body">
                    ${g.tasks.map(t => renderUniCard(t, {
                        hero: true,
                        focus: t.id === focusTaskId,
                        slotBadge: true,
                    })).join('')}
                </div>
            </div>`;
        }).join('');
    }

    // Bündel-Rendering für Heute-Spalte im Pivot-Modus.
    // pivot: 'customer' | 'activity'. Tasks werden nach Schlüssel gruppiert,
    // Gruppen nach Gesamtaufwand absteigend sortiert (größter Block oben).
    function renderHeuteBundles(today, pivot) {
        const isCust = pivot === 'customer';
        const keyOf = (t) => isCust
            ? (t.customer_id ? 'c' + t.customer_id : (t.category_hint === 'private' ? 'private' : 'ohne'))
            : (t.ai_activity_type || 'other');
        const labelOf = (t) => isCust
            ? (t.customer_name || (t.category_hint === 'private' ? 'Privat' : 'Ohne Kunde'))
            : activityLabel(t.ai_activity_type);

        // Erst die globale Heute-Position (aus state.todayOrder) jeder Task festhalten,
        // damit die Score-Reihenfolge auch im Pivot-Modus sichtbar bleibt.
        const groups = new Map();
        today.forEach((t, idx) => {
            const k = keyOf(t);
            if (!groups.has(k)) {
                groups.set(k, {
                    key: k,
                    label: labelOf(t),
                    color: isCust ? (t.customer_color || '#94a3b8') : null,
                    tasks: [],
                    totalMin: 0,
                });
            }
            const g = groups.get(k);
            g.tasks.push({ task: t, position: idx + 1 });
            g.totalMin += t.effort_minutes || t.ai_effort_estimate || 60;
        });

        const sortedGroups = Array.from(groups.values()).sort((a, b) => b.totalMin - a.totalMin);
        // Erste Karte der ersten (größten) Gruppe bekommt 'is-focus' für visuelle Hierarchie
        const focusTaskId = sortedGroups[0]?.tasks[0]?.task?.id;

        return sortedGroups.map(g => {
            const dotHtml = isCust
                ? `<span class="tp-bundle-cust-dot" style="background:${esc(g.color)};"></span>`
                : '';
            return `
            <div class="tp-bundle" data-bundle-key="${esc(g.key)}">
                <div class="tp-bundle-head">
                    ${dotHtml}
                    <span class="tp-bundle-label">${esc(g.label)}</span>
                    <span class="tp-bundle-meta">${g.tasks.length} ${g.tasks.length===1?'Task':'Tasks'} · ${fmtMin(g.totalMin)}</span>
                </div>
                <div class="tp-bundle-body">
                    ${g.tasks.map(({ task, position }) => renderUniCard(task, {
                        hero: true,
                        focus: task.id === focusTaskId,
                        position,
                    })).join('')}
                </div>
            </div>`;
        }).join('');
    }

    // ----- Step 7: Archiv — alle erledigten Tasks, gruppiert nach Datum, mit Filtern wie Phase 4 -----
    function renderStep7() {
        const open = openTasks();
        const done = state.tasks.filter(t => t.completed_at_local || t.completed_at_asana);

        // Effektives Erledigt-Datum (lokales abhaken hat Vorrang vor Asana-Datum, da neuer)
        const doneAt = (t) => t.completed_at_local || t.completed_at_asana || null;

        // Datums-Range-Filter (heute / gestern / vorgestern / 7T / 14T / 4W / 6W / 3M)
        // 'Alle' gibt es nicht mehr — Tasks älter als 3 Monate werden serverseitig gelöscht.
        const cr = state.filter.completedRange || 'm3';
        const now = new Date(); now.setHours(0,0,0,0);
        const cutoffDays = { today: 0, yesterday: 1, day2: 2, d7: 7, d14: 14, w4: 28, w6: 42, m3: 90 };
        const cutoff = (() => {
            const days = cutoffDays[cr]; if (days === undefined) return null;
            const d = new Date(now); d.setDate(d.getDate() - days); return d;
        })();

        const visible = done.filter(t => {
            if (cutoff) {
                const d = doneAt(t); if (!d) return false;
                if (new Date(d) < cutoff) return false;
            }
            return passesFilter(t);
        });

        // Nach Datum gruppieren: Heute / Gestern / Vorgestern / Letzte 7 Tage / Letzte 14 Tage / Letzte 4 Wochen / Letzte 6 Wochen / Letzte 3 Monate
        const startOfDay = (d) => { const x = new Date(d); x.setHours(0,0,0,0); return x; };
        const dayDiff = (d) => Math.floor((startOfDay(now) - startOfDay(new Date(d))) / 86400000);
        const groups = { heute: [], gestern: [], vorgestern: [], d7: [], d14: [], w4: [], w6: [], m3: [] };
        const groupLabels = {
            heute: 'Heute', gestern: 'Gestern', vorgestern: 'Vorgestern',
            d7: 'Letzte 7 Tage', d14: 'Letzte 14 Tage',
            w4: 'Letzte 4 Wochen', w6: 'Letzte 6 Wochen', m3: 'Letzte 3 Monate'
        };
        visible.forEach(t => {
            const d = doneAt(t); if (!d) return;
            const diff = dayDiff(d);
            if (diff <= 0)      groups.heute.push(t);
            else if (diff === 1) groups.gestern.push(t);
            else if (diff === 2) groups.vorgestern.push(t);
            else if (diff <= 7)  groups.d7.push(t);
            else if (diff <= 14) groups.d14.push(t);
            else if (diff <= 28) groups.w4.push(t);
            else if (diff <= 42) groups.w6.push(t);
            else                 groups.m3.push(t);
        });
        // Jede Gruppe absteigend nach Erledigt-Datum
        Object.values(groups).forEach(arr => arr.sort((a, b) => (doneAt(b) || '').localeCompare(doneAt(a) || '')));

        const totalMin = visible.reduce((a, t) => a + (t.effort_minutes || t.ai_effort_estimate || 0), 0);
        const f = state.filter;
        const pR = (v, l) => `<button class="tp-pill ${f.completedRange===v?'is-active':''}" data-pill-cr="${v}" type="button">${l}</button>`;

        // Filter-Card wird mit dem Done-Pool gefüllt (statt open) — Kunden- und Activity-Counts
        // spiegeln dann das Archiv, nicht die offene Liste.
        const filterCardHtml = renderFilterCard(done);

        const renderGroup = (key, items) => items.length === 0 ? '' : `
            <div class="tp-card" style="padding:14px;">
                <div class="tp-card-head">
                    <h2 style="font-size:var(--d-fs-md);">${groupLabels[key]}</h2>
                    <div class="tp-sub">${items.length} ${items.length===1?'Task':'Tasks'}</div>
                </div>
                <div class="tp-archive-list">
                    ${items.map(t => renderArchiveRow(t, doneAt(t))).join('')}
                </div>
            </div>`;

        // Jump-Pill: scrollt zur Sektion, statt nur den Range zu setzen.
        // Pills sind Filter (zeigen weniger an) UND Sprungziele (scrollen zur Sektion).
        // Wenn der Range schon weit genug greift, kein Re-Render — nur scrollen.
        const jR = (groupKey, pillKey, label) => {
            const has = groups[groupKey] && groups[groupKey].length > 0;
            return `<button class="tp-pill ${f.completedRange===pillKey?'is-active':''}" data-pill-cr="${pillKey}" data-jump-to="archiv-${groupKey}" type="button" ${has?'':'data-empty="1"'}>${label}${has?` <span class="tp-pill-count">${groups[groupKey].length}</span>`:''}</button>`;
        };

        const renderGroupAnchored = (key, items) => items.length === 0 ? '' : `
            <div class="tp-card" style="padding:14px;" id="archiv-${key}">
                <div class="tp-card-head">
                    <h2 style="font-size:var(--d-fs-md);">${groupLabels[key]}</h2>
                    <div class="tp-sub">${items.length} ${items.length===1?'Task':'Tasks'}</div>
                </div>
                <div class="tp-archive-list">
                    ${items.map(t => renderArchiveRow(t, doneAt(t))).join('')}
                </div>
            </div>`;

        return `
        <div class="tp-archive-scope">
        <div class="tp-card" id="archiv-top">
            <div class="tp-card-head">
                <h2>8 · Archiv</h2>
                <div class="tp-sub">${visible.length} von ${done.length} erledigt sichtbar${totalMin > 0 ? ` · ${fmtMin(totalMin)} Aufwand erfasst` : ''}</div>
                <button class="tp-pill" onclick="tpResetFilters()" type="button" style="margin-left:auto;">Filter zurücksetzen</button>
            </div>
        </div>
        <div class="tp-archive-pillbar">
            <span class="tp-pill-label">Zeitraum</span>
            ${jR('heute', 'today', 'Heute')}
            ${jR('gestern', 'yesterday', 'Gestern')}
            ${jR('vorgestern', 'day2', 'Vorgestern')}
            ${jR('d7', 'd7', '7 Tage')}
            ${jR('d14', 'd14', '14 Tage')}
            ${jR('w4', 'w4', '4 Wochen')}
            ${jR('w6', 'w6', '6 Wochen')}
            ${jR('m3', 'm3', '3 Monate')}
        </div>
        ${filterCardHtml}
        ${visible.length === 0
            ? '<div class="tp-card" style="padding:28px;text-align:center;color:var(--slate-500);">Keine erledigten Tasks im gewählten Filter.</div>'
            : Object.keys(groups).map(k => renderGroupAnchored(k, groups[k])).join('')}
        <button id="tp-archive-top-btn" class="tp-archive-top-btn" type="button" title="Nach oben" aria-label="Nach oben">
            <span class="material-symbols-rounded">arrow_upward</span>
        </button>
        </div>
        `;
    }

    // Eine Zeile im Archiv — kompakt, mit Kunde, Aktivität, Aufwand, Erledigt-Zeit und Wiederöffnen
    function renderArchiveRow(t, doneAt) {
        const eff = t.effort_minutes || t.ai_effort_estimate || null;
        const effHtml = eff ? `<span class="tp-archive-eff">${fmtMin(eff)}</span>` : '';
        const actHtml = t.ai_activity_type
            ? `<span class="tp-archive-activity">${esc(activityLabel(t.ai_activity_type))}</span>` : '';
        const custColor = t.customer_color || '#94a3b8';
        const custName = t.customer_name || (t.category_hint === 'private' ? 'Privat' : 'Ohne Kunde');
        const custHtml = `<span class="tp-archive-cust"><span class="tp-archive-cust-dot" style="background:${esc(custColor)};"></span>${esc(custName)}</span>`;
        const doneTime = doneAt ? new Date(doneAt).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) : '–';
        return `
            <div class="tp-archive-row" data-task-id="${t.id}" data-tp-context>
                <div class="tp-archive-main">
                    <div class="tp-archive-name">${esc(t.name)}</div>
                    <div class="tp-archive-meta">
                        ${custHtml}
                        ${actHtml}
                        ${effHtml}
                        <span class="tp-archive-when">erledigt ${esc(doneTime)}</span>
                    </div>
                </div>
                <div class="tp-archive-actions">
                    ${t.asana_permalink_url ? `<a href="${esc(t.asana_permalink_url)}" target="_blank" onclick="event.stopPropagation();" title="In Asana"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    <button onclick="event.stopPropagation();tpReopenTask(${t.id});" title="Wieder öffnen"><span class="material-symbols-rounded">undo</span></button>
                </div>
            </div>`;
    }

    function renderStep6_legacy_OBSOLETE() {
        const open = openTasks();
        // Custom-Reihenfolge: state.todayOrder ist Array von task_ids. Tasks, die im Slot sind aber noch nicht in der Order, werden ans Ende gehängt.
        if (!state.todayOrder) state.todayOrder = [];
        const todayInSlot = open.filter(t => t.planned_for_date === localDate());
        const validIds = new Set(todayInSlot.map(t => t.id));
        state.todayOrder = state.todayOrder.filter(id => validIds.has(id));
        todayInSlot.forEach(t => { if (!state.todayOrder.includes(t.id)) state.todayOrder.push(t.id); });
        // Quick-Wins zuerst, dann der Rest in Order — aber nur wenn der User die Quick-Wins-Bundling-Option nicht abgewählt hat
        const byId = new Map(todayInSlot.map(t => [t.id, t]));
        const todayTasks = state.todayOrder.map(id => byId.get(id)).filter(Boolean);

        const tomorrowTasks = open.filter(t => t.daily_slot === 'tomorrow' || t.daily_slot === 'day_after').sort((a,b) => (b.score||0) - (a.score||0));
        const weekTasks = open.filter(t => t.daily_slot === 'rest_week' || t.daily_slot === 'next_week').sort((a,b) => (b.score||0) - (a.score||0));

        // Quick-Wins-Pool: alle <= 10min Tasks aus Pool/Diese-Woche, die noch NICHT in Heute sind
        const quickWinsCandidates = open.filter(t =>
            t.daily_slot !== 'today' &&
            (t.is_quick_task == 1 || (t.effort_minutes && t.effort_minutes <= 10) || (!t.effort_minutes && t.ai_effort_estimate && t.ai_effort_estimate <= 10))
        ).sort((a,b) => (b.score||0) - (a.score||0));

        const todayDoneCount = state.tasks.filter(t => t.completed_at_local && isSameDay(t.completed_at_local)).length;
        const todaySumEffort = todayTasks.reduce((a, t) => a + ((t.effort_minutes || t.ai_effort_estimate || 60)), 0);

        if (todayTasks.length === 0) {
            return `
            <div class="tp-card tp-day-card">
                <div class="tp-day-empty">
                    <span class="material-symbols-rounded" style="font-size:48px;color:var(--slate-300);">checklist</span>
                    <h2 style="margin:10px 0 6px;">Noch nichts für Heute geplant</h2>
                    <p style="color:var(--slate-500);">Geh zurück zu <strong>Schritt 5</strong> oder zieh unten aus Morgen / Quick-Wins rein.</p>
                    <button class="thx-btn thx-btn-primary" onclick="tpGoStep(5)" style="margin-top:14px;">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">arrow_back</span>
                        Zurück zu Schritt 5
                    </button>
                </div>
            </div>
            ${quickWinsCandidates.length ? renderQuickWinsCard(quickWinsCandidates) : ''}
            ${tomorrowTasks.length ? renderAddableSection('Morgen wartet', tomorrowTasks.slice(0,8), '#f59e0b') : ''}
            ${weekTasks.length ? renderAddableSection('Diese Woche', weekTasks.slice(0,8), '#84cc16') : ''}
            `;
        }

        const [primary, ...rest] = todayTasks;
        return `
            <div class="tp-card tp-day-hero">
                <div class="tp-day-head">
                    <div>
                        <div class="tp-day-eyebrow">Dein heutiger Fokus · ${fmtMin(todaySumEffort)} insgesamt</div>
                        <h2 style="margin:6px 0 4px;">${todayTasks.length} ${todayTasks.length===1?'Task':'Tasks'} für heute</h2>
                        ${todayDoneCount > 0 ? `<div class="tp-day-progress">Schon ${todayDoneCount} erledigt ✓</div>` : ''}
                    </div>
                    <button class="thx-btn thx-btn-secondary" onclick="tpRefreshPlan()" title="KI-Sequenz mit Begründungen" style="margin-left:auto;">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                        ${state.plan ? 'Sequenz aktualisieren' : 'Mit KI-Sequenz versehen'}
                    </button>
                </div>
                ${renderPrimaryTask(primary, state.plan)}
            </div>

            ${rest.length ? `
            <div class="tp-card">
                <div class="tp-card-head">
                    <h2 style="font-size:var(--d-fs-md);">Danach dran (${rest.length})</h2>
                    <div class="tp-sub">Drag &amp; Drop für eigene Reihenfolge</div>
                </div>
                <div class="tp-uplist" id="tp-today-list">
                    ${rest.map((t, i) => renderUpcomingItem(t, i + 2, state.plan)).join('')}
                </div>
            </div>` : ''}

            ${state.plan && state.plan.summary ? `<div class="tp-summary">${esc(state.plan.summary)}</div>` : ''}

            ${quickWinsCandidates.length ? renderQuickWinsCard(quickWinsCandidates) : ''}
            ${tomorrowTasks.length ? renderAddableSection('Morgen wartet', tomorrowTasks.slice(0,8), '#f59e0b') : ''}
            ${weekTasks.length ? renderAddableSection('Diese Woche', weekTasks.slice(0,8), '#84cc16') : ''}
        `;
    }

    function renderQuickWinsCard(quickTasks) {
        const totalMin = quickTasks.reduce((a, t) => a + (t.effort_minutes || t.ai_effort_estimate || 5), 0);
        const fitIn30 = [];
        let acc = 0;
        for (const t of quickTasks) {
            const m = t.effort_minutes || t.ai_effort_estimate || 5;
            if (acc + m > 30) break;
            fitIn30.push(t); acc += m;
        }
        return `
        <div class="tp-card tp-quick-card">
            <div class="tp-card-head">
                <span class="material-symbols-rounded" style="color:#15803d;">bolt</span>
                <h2 style="font-size:var(--d-fs-md);margin:0;">Quick Wins ${quickTasks.length > 0 ? `(${quickTasks.length} Tasks · ${fmtMin(totalMin)})` : ''}</h2>
                <div class="tp-sub">In ~5 Min weg: 1-Klick-Antworten, kurze Mails, Approvals</div>
                ${fitIn30.length >= 3 ? `<button class="thx-btn thx-btn-primary" onclick='tpAddQuickWins(${JSON.stringify(fitIn30.map(t=>t.id))})' style="margin-left:auto;">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">add_task</span>
                    Diese ${fitIn30.length} (~${fmtMin(acc)}) in Heute
                </button>` : ''}
            </div>
            <div class="tp-quick-list">
                ${quickTasks.slice(0, 12).map(t => {
                    const eff = t.effort_minutes || t.ai_effort_estimate || 5;
                    const cust = t.customer_abbr ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr)}</span>` : '';
                    return `
                    <div class="tp-quick-item" data-task-id="${t.id}">
                        ${cust}
                        <div class="tp-quick-name" data-tp-open>${esc(t.name)}</div>
                        <div class="tp-quick-eff">${fmtMin(eff)}</div>
                        <button onclick="tpAddToToday(${t.id})" title="In Heute aufnehmen"><span class="material-symbols-rounded">add</span></button>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
    }

    function renderAddableSection(title, tasks, color) {
        return `
        <div class="tp-card tp-preview-card">
            <div class="tp-card-head">
                <span class="tp-kanban-col-dot" style="background:${color};"></span>
                <h2 style="font-size:var(--d-fs-md);margin:0;">${esc(title)}</h2>
                <div class="tp-sub">${tasks.length}</div>
            </div>
            <div class="tp-preview-list">
                ${tasks.map(t => {
                    const eff = t.effort_minutes || t.ai_effort_estimate || 60;
                    const cust = t.customer_abbr ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr)}</span>` : '';
                    return `
                    <div class="tp-preview-item" data-task-id="${t.id}">
                        ${cust}
                        <div class="tp-preview-name" data-tp-open>${esc(t.name)}</div>
                        <div class="tp-preview-eff">${fmtMin(eff)}</div>
                        <button class="tp-preview-add" onclick="tpAddToToday(${t.id})" title="In Heute aufnehmen"><span class="material-symbols-rounded">add</span></button>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
    }

    function renderPrimaryTask(t, plan) {
        const eff = t.effort_minutes || t.ai_effort_estimate || 60;
        const cust = t.customer_name
            ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr || t.customer_name.substring(0,3))} · ${esc(t.customer_name)}</span>`
            : '';
        const due = dueBadge(t.due_on);
        const seqReason = plan?.sequence?.find(s => s.task_id === t.id)?.reason;
        const summary = t.ai_summary && t.ai_summary !== '(KI lieferte keine Zusammenfassung)' ? t.ai_summary : '';
        return `
        <div class="tp-primary" data-task-id="${t.id}">
            <div class="tp-primary-eyebrow">1 · ${fmtMin(eff)}</div>
            <h3 class="tp-primary-title">${esc(t.name)}</h3>
            <div class="tp-primary-meta">
                ${cust}
                ${due}
                ${t.asana_project_name ? `<span class="tp-task-badge">${esc(t.asana_project_name)}</span>` : ''}
            </div>
            ${summary ? `<div class="tp-primary-summary">${esc(summary)}</div>` : ''}
            ${seqReason ? `<div class="tp-primary-reason"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">tips_and_updates</span> ${esc(seqReason)}</div>` : ''}
            <div class="tp-primary-actions">
                ${t.asana_permalink_url
                    ? `<a class="thx-btn thx-btn-primary tp-action-big" href="${esc(t.asana_permalink_url)}" target="_blank" rel="noopener" onclick="tpMarkOpenedInAsana(${t.id})">
                          <span class="material-symbols-rounded">open_in_new</span>
                          In Asana öffnen
                       </a>`
                    : ''}
                <button class="thx-btn thx-btn-secondary tp-action-big tp-action-done" onclick="tpCompleteAndCelebrate(${t.id})">
                    <span class="material-symbols-rounded">check_circle</span>
                    Erledigt — feiern!
                </button>
                <button class="thx-btn thx-btn-secondary" onclick="openDrawer(${t.id})" title="Details ansehen">
                    <span class="material-symbols-rounded">info</span>
                </button>
                <button class="thx-btn thx-btn-secondary" onclick="tpPostponeFromOutput(${t.id})" title="Auf Morgen verschieben">
                    <span class="material-symbols-rounded">schedule</span>
                </button>
            </div>
        </div>`;
    }

    function renderUpcomingItem(t, idx, plan) {
        const eff = t.effort_minutes || t.ai_effort_estimate || 60;
        const cust = t.customer_name
            ? `<span class="tp-task-badge tp-cust" style="background:${esc(t.customer_color || '#94a3b8')};">${esc(t.customer_abbr || t.customer_name.substring(0,3))}</span>`
            : '';
        const due = dueBadge(t.due_on);
        const seqReason = plan?.sequence?.find(s => s.task_id === t.id)?.reason;
        return `
        <div class="tp-up-item" data-task-id="${t.id}">
            <div class="tp-up-num">${idx}.</div>
            <div class="tp-up-body">
                <div class="tp-up-name">${esc(t.name)}</div>
                <div class="tp-up-meta">${cust}${due}<span class="tp-task-badge">${fmtMin(eff)}</span></div>
                ${seqReason ? `<div class="tp-up-reason">${esc(seqReason)}</div>` : ''}
            </div>
            <div class="tp-up-actions">
                ${t.asana_permalink_url ? `<a href="${esc(t.asana_permalink_url)}" target="_blank" rel="noopener" title="In Asana öffnen"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                <button onclick="tpCompleteAndCelebrate(${t.id})" title="Lokal abhaken"><span class="material-symbols-rounded">check_circle</span></button>
            </div>
        </div>`;
    }

    function isSameDay(ts) {
        if (!ts) return false;
        const d = new Date(ts); const t = new Date();
        return d.getFullYear() === t.getFullYear() && d.getMonth() === t.getMonth() && d.getDate() === t.getDate();
    }

    // ===== Step-Listener (delegation + Sortable.js) =====
    function attachStepListeners() {
        // Archiv-Top-Button: scrollt nach oben, Sichtbarkeit über Scroll-Position.
        // Scroll-Listener nur 1× pro Page-Load registrieren (sonst summieren bei Re-Renders).
        const topBtn = document.getElementById('tp-archive-top-btn');
        if (topBtn && !window._tpTopBtnInit) {
            window._tpTopBtnInit = true;
            topBtn.addEventListener('click', () => {
                const head = document.getElementById('archiv-top');
                if (head) head.scrollIntoView({ behavior: 'smooth', block: 'start' });
                else window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            const updateVisibility = () => {
                const btn = document.getElementById('tp-archive-top-btn');
                if (!btn) return;
                if (window.scrollY > 400) btn.classList.add('is-visible');
                else btn.classList.remove('is-visible');
            };
            window.addEventListener('scroll', updateVisibility, { passive: true });
            updateVisibility();
        }
        // Phase 6 hat eine eigene Suche, entkoppelt vom Global-Filter
        const s5 = document.getElementById('tp-step5-search');
        if (s5) {
            let to5 = null;
            s5.addEventListener('input', () => {
                state.step5Search = s5.value;
                const caret = s5.selectionStart;
                clearTimeout(to5);
                to5 = setTimeout(() => {
                    render();
                    const fresh = document.getElementById('tp-step5-search');
                    if (fresh) { fresh.focus(); try { fresh.setSelectionRange(caret, caret); } catch (e) {} }
                }, 220);
            });
        }
        const s7 = document.getElementById('tp-step7-search');
        if (s7) {
            let to7 = null;
            s7.addEventListener('input', () => {
                state.step7Search = s7.value;
                const caret = s7.selectionStart;
                clearTimeout(to7);
                to7 = setTimeout(() => {
                    render();
                    const fresh = document.getElementById('tp-step7-search');
                    if (fresh) { fresh.focus(); try { fresh.setSelectionRange(caret, caret); } catch (e) {} }
                }, 220);
            });
        }
        const s = document.getElementById('tp-search');
        if (s) {
            // Re-Focus auf das (durch render() neu erzeugte) Suchfeld + Cursor wieder ans Ende.
            // Ohne das würde der User nach jedem Tipp aus dem Feld fliegen.
            let to = null;
            s.addEventListener('input', () => {
                state.filter.search = s.value;
                const caret = s.selectionStart;
                clearTimeout(to);
                to = setTimeout(() => {
                    render();
                    const fresh = document.getElementById('tp-search');
                    if (fresh) {
                        fresh.focus();
                        try { fresh.setSelectionRange(caret, caret); } catch (e) { /* ignorieren */ }
                    }
                }, 220);
            });
        }
        // Step 3: Achse + Sortable
        document.querySelectorAll('[data-axis]').forEach(b => {
            b.addEventListener('click', () => { state.kanbanAxis = b.dataset.axis; render(); });
        });
        // Step 4: Achse
        document.querySelectorAll('[data-axis4]').forEach(b => {
            b.addEventListener('click', () => { state.step4Axis = b.dataset.axis4; render(); });
        });
        document.querySelectorAll('[data-kanban-col]').forEach(col => {
            sortableInstances.push(new Sortable(col, {
                group: 'kanban-' + state.kanbanAxis,
                animation: 150,
                draggable: '.tp-c-card',
                filter: 'button, a, input, select, label',
                preventOnFilter: false,
                onEnd: async (ev) => {
                    if (ev.from === ev.to) return;
                    const tid = parseInt(ev.item.dataset.taskId, 10);
                    const toKey = ev.to.dataset.kanbanCol;
                    // Achse aus der Ziel-Spalte (Phase 4 = step4Axis), Fallback Phase 3 = kanbanAxis.
                    const axis = ev.to.dataset.kanbanAxis || ev.from.dataset.kanbanAxis || state.kanbanAxis;
                    await applyKanbanMove(tid, axis, toKey);
                },
            }));
        });
        // Step 6: Kanban-Drop (Drag zwischen den 4 Spalten)
        // Quick-Wins ist source-only (acceptDrop=false), die anderen sind Quelle UND Ziel.
        // filter: 'button, a, input, select' = Drag auf Buttons/Links startet KEINEN Drag
        // (sonst blockieren z.B. die Pfeil-Up/Down-Buttons das Card-Drag).
        document.querySelectorAll('[data-step6-col]').forEach(col => {
            const acceptDrop = col.dataset.step6Accept === '1';
            sortableInstances.push(new Sortable(col, {
                group: { name: 'step6', pull: true, put: acceptDrop },
                animation: 150,
                draggable: '.tp-c-card',
                filter: 'button, a, input, select, label',
                preventOnFilter: false,
                onEnd: async (ev) => {
                    const newSlot = ev.to.dataset.step6Col;
                    const oldSlot = ev.from.dataset.step6Col;
                    const tid = parseInt(ev.item.dataset.taskId, 10);

                    // Helper: liest die aktuelle DOM-Reihenfolge der Heute-Spalte aus und
                    // schreibt sie in state.todayOrder. So spiegelt die Liste die Drop-Position wieder.
                    const syncTodayOrderFromDOM = () => {
                        const todayBody = document.querySelector('[data-step6-col="committed"]');
                        if (!todayBody) return;
                        state.todayOrder = Array.from(todayBody.querySelectorAll('.tp-c-card[data-task-id]'))
                            .map(el => parseInt(el.dataset.taskId, 10))
                            .filter(x => !isNaN(x));
                    };

                    // Same-column-Reorder: nur DOM-Reihenfolge geändert, kein Backend-Update nötig.
                    if (ev.from === ev.to) {
                        if (newSlot === 'committed') {
                            syncTodayOrderFromDOM();
                            render();
                        }
                        return;
                    }

                    // Cross-column: 'committed' = in den Tagesplan holen; Bucket-Spalte = Frist re-planen (+ aus Plan nehmen).
                    try {
                        if (newSlot === 'committed') {
                            await App.post('/planner/tasks/' + tid + '/set-field', { plan_today: 1 });
                            const t = state.tasks.find(x => x.id === tid);
                            if (t) t.planned_for_date = localDate();
                        } else {
                            // In eine Frist-Spalte gezogen = Fälligkeit auf diesen Bucket setzen (re-planen).
                            const newDue = bucketToDate(newSlot);
                            await App.post('/planner/tasks/' + tid + '/set-field', { due_on: newDue, plan_today: 0 });
                            const t = state.tasks.find(x => x.id === tid);
                            if (t) { t.due_on = newDue; t.daily_slot = newSlot; t.due_locally_set = 1; t.planned_for_date = null; }
                        }
                        if (oldSlot === 'committed' || newSlot === 'committed') {
                            syncTodayOrderFromDOM();
                        }
                        render();
                    } catch (e) {
                        App.showNotification('Verschieben fehlgeschlagen: ' + (e.message || ''), 'error');
                        render();
                    }
                },
            }));
        });
        // Step 5: Slot-Sortable
        document.querySelectorAll('[data-slot-col]').forEach(col => {
            sortableInstances.push(new Sortable(col, {
                group: 'planner-slot',
                animation: 150,
                draggable: '.tp-c-card',
                filter: 'button, a, input, select, label',
                preventOnFilter: false,
                onEnd: async (ev) => {
                    if (ev.from === ev.to) return;
                    const tid = parseInt(ev.item.dataset.taskId, 10);
                    const slot = ev.to.dataset.slotCol;
                    // Ziehen im Zeitraum-Board = Re-Planen: Fälligkeit auf einen Tag im Ziel-Bucket setzen.
                    const newDue = bucketToDate(slot);
                    await App.post('/planner/tasks/' + tid + '/set-field', { due_on: newDue });
                    const t = state.tasks.find(x => x.id === tid);
                    if (t) { t.due_on = newDue; t.daily_slot = slot; t.due_locally_set = 1; }
                    render();
                },
            }));
        });
    }

    async function applyKanbanMove(taskId, axis, toKey) {
        let body = null;
        if (axis === 'priority') {
            body = { manual_priority: toKey };
        } else if (axis === 'effort') {
            // Beim Drag in eine Effort-Spalte: setze Aufwand auf den charakteristischen Wert
            // des Buckets — quick=30, bis-1h=60, 1-4h=180, halber Tag=300 (5h), Tagesblock=480.
            const map = { quick: 30, short: 60, medium: 180, half_day: 300, full_day: 480, unknown: null };
            body = { effort_minutes: map[toKey] };
        } else if (axis === 'bucket') {
            // Zeitraum-Achse = Re-Planen: setzt die Fälligkeit (due_on) auf einen Tag im Ziel-Bucket.
            // Lokale Friständerung bleibt erhalten (Asana-Sync überschreibt sie nicht).
            body = { due_on: bucketToDate(toKey) };
        } else if (axis === 'customer') {
            // Kunden-Spalten: 'c<id>' = Kunde, 'private' / 'unclear' = Pseudo-Buckets
            if (toKey === 'private') body = { customer_id: null, category_hint: 'private' };
            else if (toKey === 'unclear') body = { customer_id: null, category_hint: 'unclear' };
            else if (toKey.startsWith('c')) body = { customer_id: parseInt(toKey.substring(1), 10), category_hint: null };
            else { render(); return; }
        }
        if (!body) return;
        try {
            await App.post('/planner/tasks/' + taskId + '/set-field', body);
            const t = state.tasks.find(x => x.id === taskId);
            if (t) Object.assign(t, body);
            // Score neu vom Server (oder Re-Sync)
            await load();
        } catch (e) {
            App.showNotification('Verschieben fehlgeschlagen: ' + (e.message || ''), 'error');
            await load();
        }
    }

    // ===== Globale Click-/Right-Click-Delegation =====
    $root.addEventListener('click', async (ev) => {
        const planBtn = ev.target.closest('[data-plan-min]');
        if (planBtn) {
            state.planMinutes = parseInt(planBtn.dataset.planMin, 10);
            const ci = document.getElementById('tp-plan-custom');
            if (ci) ci.value = state.planMinutes;
            render();
            return;
        }
        const fE = ev.target.closest('[data-pill-effort]');
        const fBk = ev.target.closest('[data-pill-bucket]');
        const fS = ev.target.closest('[data-pill-stale]');
        const fG = ev.target.closest('[data-pill-sig]');
        const fC = ev.target.closest('[data-pill-cust]');
        const fQ = ev.target.closest('[data-pill-quick]');
        if (fE) { state.filter.effort = fE.dataset.pillEffort; render(); return; }
        if (fBk) {
            const k = fBk.dataset.pillBucket;
            const arr = state.filter.bucket || [];
            if (k === 'all') { state.filter.bucket = []; }
            else if (ev.shiftKey || ev.metaKey || ev.ctrlKey) {
                const i = arr.indexOf(k); if (i >= 0) arr.splice(i, 1); else arr.push(k);
                state.filter.bucket = arr;
            } else {
                state.filter.bucket = (arr.length === 1 && arr[0] === k) ? [] : [k];
            }
            render(); return;
        }
        if (fS) { state.filter.stale  = fS.dataset.pillStale;  render(); return; }
        if (fG) { state.filter.sig    = fG.dataset.pillSig;    render(); return; }
        if (fC) { state.filter.customer = fC.dataset.pillCust; render(); return; }
        if (fQ) { state.filter.quick   = fQ.dataset.pillQuick; render(); return; }
        const fOv = ev.target.closest('[data-pill-overdue]');
        if (fOv) { state.filter.overdue = state.filter.overdue === 'only' ? 'all' : 'only'; render(); return; }
        const fDv = ev.target.closest('[data-pill-deviation]');
        if (fDv) { state.filter.deviation = state.filter.deviation === 'only' ? 'all' : 'only'; render(); return; }
        const fToggle = ev.target.closest('[data-pill-recurring],[data-pill-started],[data-pill-signal],[data-pill-noeffort],[data-pill-hot],[data-pill-context]');
        if (fToggle) {
            const map = { pillRecurring: 'recurring', pillStarted: 'started', pillSignal: 'signal', pillNoeffort: 'noeffort', pillHot: 'hotcustomer', pillContext: 'context' };
            const key = Object.keys(fToggle.dataset).map(k => map[k]).find(Boolean);
            if (key) { state.filter[key] = state.filter[key] === 'only' ? 'all' : 'only'; render(); return; }
        }
        const fCx = ev.target.closest('[data-pill-complexity]');
        if (fCx) { state.filter.complexity = fCx.dataset.pillComplexity; render(); return; }
        const fA = ev.target.closest('[data-pill-activity]');
        if (fA) { state.filter.activity = fA.dataset.pillActivity; render(); return; }
        const fR = ev.target.closest('[data-pill-cr]');
        if (fR) {
            state.filter.completedRange = fR.dataset.pillCr;
            const jumpTo = fR.dataset.jumpTo;
            render();
            // Nach dem Re-Render zur Ziel-Sektion scrollen (falls Pill ein data-jump-to hat)
            if (jumpTo) {
                requestAnimationFrame(() => {
                    const target = document.getElementById(jumpTo);
                    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
            return;
        }
        const fW = ev.target.closest('[data-pill-waiting]');
        if (fW) { state.filter.waiting = fW.dataset.pillWaiting; render(); return; }

        const taskEl = ev.target.closest('[data-task-id]');
        if (!taskEl) return;
        const tid = parseInt(taskEl.dataset.taskId, 10);
        if (ev.target.closest('[data-tp-effort]')) {
            // Aufwand-Popover statt prompt() — auch in Step 4-Listen
            const anchor = ev.target.closest('[data-tp-effort]');
            tpEffortPopover(tid, anchor);
        }
        else if (ev.target.closest('[data-tp-open]')) { openDrawer(tid); }
    });

    $root.addEventListener('change', async (ev) => {
        if (ev.target.matches('[data-bulk-check]')) {
            const tid = parseInt(ev.target.closest('[data-task-id]').dataset.taskId, 10);
            tpBulkUnclearToggle(tid, ev.target.checked);
            return;
        }
        if (ev.target.matches('[data-step4-sel]')) {
            const tid = parseInt(ev.target.closest('[data-task-id]').dataset.taskId, 10);
            tpStep4Toggle(tid, ev.target.checked);
            return;
        }
        if (!ev.target.matches('[data-tp-complete]')) return;
        const tid = parseInt(ev.target.closest('[data-task-id]').dataset.taskId, 10);
        try {
            await App.post('/planner/tasks/' + tid + '/complete', { completed: ev.target.checked });
            await load();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    });
    // Klick auf den Task-Namen toggelt die Checkbox (komfortabler bei vielen Tasks)
    $root.addEventListener('click', (ev) => {
        const t = ev.target.closest('[data-bulk-toggle]');
        if (!t) return;
        const row = t.closest('[data-task-id]');
        if (!row) return;
        const cb = row.querySelector('[data-bulk-check]');
        if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true })); }
    });

    // Rechtsklick-Kontextmenu (auf Kanban-Card oder Task-Row)
    document.addEventListener('contextmenu', (ev) => {
        // Rechtsklick auf eine Kunden-Filter-Pille = als 🔥 brennend markieren/entmarkieren.
        const custPill = ev.target.closest('[data-pill-cust]');
        if (custPill && !isNaN(parseInt(custPill.dataset.pillCust, 10))) {
            ev.preventDefault();
            tpToggleCustomerHot(parseInt(custPill.dataset.pillCust, 10));
            return;
        }
        const target = ev.target.closest('[data-tp-context]');
        if (!target) return;
        ev.preventDefault();
        const tid = parseInt(target.dataset.taskId, 10);
        openContextMenu(tid, ev.clientX, ev.clientY);
    });
    document.addEventListener('click', (ev) => {
        if (!ev.target.closest('#tp-ctx')) hideContextMenu();
    });

    function openContextMenu(tid, x, y) {
        const t = state.tasks.find(x => x.id === tid);
        if (!t) return;
        const ctx = document.getElementById('tp-ctx');
        ctx.innerHTML = `
            <div class="tp-ctx-item" data-ctx-open>
                <span class="material-symbols-rounded" style="font-size:16px;">visibility</span>Details ansehen
            </div>
            ${t.asana_permalink_url ? `<a class="tp-ctx-item" href="${esc(t.asana_permalink_url)}" target="_blank" rel="noopener"><span class="material-symbols-rounded" style="font-size:16px;">open_in_new</span>In Asana öffnen</a>` : ''}
            <div class="tp-ctx-item" data-ctx-effort><span class="material-symbols-rounded" style="font-size:16px;">timer</span>Aufwand setzen</div>
            <div class="tp-ctx-item" data-ctx-postpone><span class="material-symbols-rounded" style="font-size:16px;">schedule</span>Auf morgen verschieben</div>
            <div class="tp-ctx-item" data-ctx-toad><span style="font-size:15px;">🐸</span>${t.is_toad == 1 ? 'Kröte des Tages entfernen' : 'Zur Kröte des Tages machen'}</div>
            <div class="tp-ctx-item" data-ctx-wait><span class="material-symbols-rounded" style="font-size:16px;">pause_circle</span>${t.is_waiting == 1 ? 'Wieder aktiv setzen' : 'Auf Warten setzen'}</div>
            <div class="tp-ctx-item" data-ctx-recurring><span class="material-symbols-rounded" style="font-size:16px;">repeat</span>${t.is_recurring == 1 ? 'Wiederkehrend entfernen' : 'Als wiederkehrend markieren'}</div>
            <div class="tp-ctx-item" data-ctx-quickwin><span class="material-symbols-rounded" style="font-size:16px;">bolt</span>${t.quick_win_user_excluded == 1 ? 'Wieder als Quick-Win zulassen' : 'Aus Quick-Wins entfernen'}</div>
            <div class="tp-ctx-item" data-ctx-complete><span class="material-symbols-rounded" style="font-size:16px;">check_circle</span>${t.completed_at_local || t.completed_at_asana ? 'Wieder öffnen' : 'Lokal abhaken'}</div>
            <div class="tp-ctx-divider"></div>
            <div class="tp-ctx-item is-danger" data-ctx-ignore><span class="material-symbols-rounded" style="font-size:16px;">visibility_off</span>Aus Planner ausblenden</div>
        `;
        ctx.dataset.taskId = tid;
        // Erst sichtbar machen (mit offscreen-Position), Größe messen, dann am Viewport-Rand ausrichten.
        ctx.style.left = '-9999px';
        ctx.style.top = '-9999px';
        ctx.classList.add('is-open');
        const rect = ctx.getBoundingClientRect();
        const margin = 8;
        const maxLeft = window.innerWidth - rect.width - margin;
        const maxTop = window.innerHeight - rect.height - margin;
        ctx.style.left = Math.max(margin, Math.min(x, maxLeft)) + 'px';
        ctx.style.top = Math.max(margin, Math.min(y, maxTop)) + 'px';
    }
    function hideContextMenu() { document.getElementById('tp-ctx').classList.remove('is-open'); }

    document.getElementById('tp-ctx').addEventListener('click', async (ev) => {
        const ctx = ev.currentTarget;
        const tid = parseInt(ctx.dataset.taskId, 10);
        const t = state.tasks.find(x => x.id === tid);
        if (ev.target.closest('[data-ctx-open]')) { hideContextMenu(); openDrawer(tid); }
        else if (ev.target.closest('[data-ctx-effort]')) {
            // Aufwand-Popover statt prompt() — Anker ist primär das Effort-Badge auf der Karte
            // (falls vorhanden), sonst das Context-Menü selbst.
            const ctxItem = ev.target.closest('[data-ctx-effort]');
            const cardEl = document.querySelector('[data-task-id="' + tid + '"]');
            const anchor = cardEl?.querySelector('.tp-c-eff, [data-tp-effort]') || ctxItem;
            hideContextMenu();
            tpEffortPopover(tid, anchor);
        }
        else if (ev.target.closest('[data-ctx-toad]')) { hideContextMenu(); await tpToggleToad(tid); }
        else if (ev.target.closest('[data-ctx-wait]')) { hideContextMenu(); await tpSetWaiting(tid); }
        else if (ev.target.closest('[data-ctx-recurring]')) { hideContextMenu(); await tpToggleRecurring(tid); }
        else if (ev.target.closest('[data-ctx-quickwin]')) { hideContextMenu(); await tpToggleQuickWin(tid); }
        else if (ev.target.closest('[data-ctx-postpone]')) {
            hideContextMenu();
            const d = localDate(1);
            await App.post('/planner/tasks/' + tid + '/postpone', { date: d });
            App.showNotification('Verschoben auf morgen', 'success');
            await load();
        } else if (ev.target.closest('[data-ctx-complete]')) {
            hideContextMenu();
            const isDone = !!(t.completed_at_local || t.completed_at_asana);
            await App.post('/planner/tasks/' + tid + '/complete', { completed: !isDone });
            await load();
        } else if (ev.target.closest('[data-ctx-ignore]')) {
            hideContextMenu();
            if (!confirm('Aus Planner ausblenden? In Asana bleibt sie.')) return;
            await App.post('/planner/tasks/' + tid + '/ignore', { ignored: true });
            await load();
        }
    });

    window.tpEffortPopover = function (tid, anchorEl) {
        const buckets = [5, 10, 15, 30, 45, 60, 90, 120, 180, 240, 360, 480];
        const t = state.tasks.find(x => x.id === tid);
        const cur = t?.effort_minutes || t?.ai_effort_estimate || null;
        let pop = document.getElementById('tp-effort-popover');
        if (pop) pop.remove();
        pop = document.createElement('div');
        pop.id = 'tp-effort-popover';
        pop.className = 'tp-effort-popover';
        pop.innerHTML = `
            <div class="tp-popover-head">Aufwand für diese Task</div>
            <div class="tp-popover-grid">
                ${buckets.map(m => `<button type="button" class="tp-pop-btn ${cur===m?'is-active':''}" data-eff="${m}">${fmtMin(m)}</button>`).join('')}
            </div>
            <div class="tp-popover-foot">
                <input type="number" class="tp-popover-custom" placeholder="custom Min" min="1" max="1440">
                <button type="button" class="tp-pop-clear">Leer setzen</button>
            </div>
        `;
        document.body.appendChild(pop);
        const r = anchorEl.getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(window.innerWidth - 320, r.left)) + 'px';
        pop.style.top = (r.bottom + 6) + 'px';
        const close = () => { pop.remove(); document.removeEventListener('click', outside, true); };
        const outside = (ev) => { if (!pop.contains(ev.target) && ev.target !== anchorEl) close(); };
        setTimeout(() => document.addEventListener('click', outside, true), 0);
        pop.addEventListener('click', async (ev) => {
            const b = ev.target.closest('[data-eff]');
            if (b) {
                const m = parseInt(b.dataset.eff, 10);
                close();
                try { await App.post('/planner/tasks/' + tid + '/effort', { minutes: m }); await load(); }
                catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
                return;
            }
            if (ev.target.classList.contains('tp-pop-clear')) {
                close();
                try { await App.post('/planner/tasks/' + tid + '/effort', { minutes: 0 }); await load(); }
                catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
                return;
            }
        });
        const custom = pop.querySelector('.tp-popover-custom');
        custom.addEventListener('keydown', async (ev) => {
            if (ev.key === 'Enter') {
                const m = parseInt(custom.value, 10);
                if (m > 0 && m <= 1440) {
                    close();
                    try { await App.post('/planner/tasks/' + tid + '/effort', { minutes: m }); await load(); }
                    catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
                }
            }
        });
        setTimeout(() => custom.focus(), 30);
    };

    // ===== Step 4 Bulk-Auswahl =====
    window.tpStep4Toggle = function (tid, checked) {
        if (!state.step4Sel) state.step4Sel = new Set();
        if (checked) state.step4Sel.add(tid); else state.step4Sel.delete(tid);
        render();
    };
    window.tpStep4ToggleAll = function (checked) {
        if (!state.step4Sel) state.step4Sel = new Set();
        const open = openTasks();
        const visible = open.filter(passesFilter);
        if (checked) visible.forEach(t => state.step4Sel.add(t.id));
        else state.step4Sel.clear();
        render();
    };
    window.tpStep4ClearSel = function () { state.step4Sel = new Set(); render(); };
    window.tpStep4Bulk = async function (action, value, minutesArg) {
        const ids = Array.from(state.step4Sel || []);
        if (!ids.length) return;
        const body = { task_ids: ids, action };
        if (action === 'slot') body.slot = value;
        else if (action === 'priority') body.priority = value;
        else if (action === 'effort') body.minutes = minutesArg;
        // 'ignore' und 'complete' brauchen keinen extra-Param
        try {
            const r = await App.post('/planner/bulk-set', body);
            if (!r.success) throw new Error(r.message);
            App.showNotification(`${r.data.updated} Tasks aktualisiert`, 'success');
            state.step4Sel = new Set();
            await load();
        } catch (e) {
            App.showNotification('Bulk-Fehler: ' + (e.message || ''), 'error');
        }
    };

    window.openDrawer = function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        if (!t) return;
        const drw = document.getElementById('tp-drawer');
        const bg = document.getElementById('tp-drawer-bg');
        const eff = t.effort_minutes || t.ai_effort_estimate || null;
        drw.innerHTML = `
            <div class="tp-drawer-head">
                <h3>${esc(t.name)}</h3>
                <button class="tp-drawer-close" onclick="tpCloseDrawer()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="tp-drawer-body">
                ${t.ai_summary ? `<div class="tp-drawer-section"><h4>KI-Zusammenfassung</h4><div style="font-size:var(--d-fs-sm);color:var(--slate-700);line-height:1.5;">${esc(t.ai_summary)}</div></div>` : ''}
                <div class="tp-drawer-section">
                    <h4>Kennzahlen</h4>
                    <div class="tp-kpi-grid">
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Kunde</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${esc(t.customer_name || '—')}</div></div>
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Projekt</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${esc(t.asana_project_name || '—')}</div></div>
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Fällig</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${t.due_on ? new Date(t.due_on).toLocaleDateString('de-DE') : '—'}</div></div>
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Aufwand</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${fmtMin(eff)}${t.effort_minutes ? '' : ' (KI)'}</div></div>
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Score</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${t.score ? parseFloat(t.score).toFixed(1) : '—'}</div></div>
                        <div class="tp-kpi-card"><div class="tp-kpi-label">Verschoben</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${t.postpone_count || 0}x</div></div>
                        ${t.ai_significance ? `<div class="tp-kpi-card"><div class="tp-kpi-label">KI-Bedeutsamkeit</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${t.ai_significance}/10</div></div>` : ''}
                        ${effectivePriority(t) ? `<div class="tp-kpi-card"><div class="tp-kpi-label">Prio (Override${t.manual_priority?'':' = KI'})</div><div class="tp-kpi-value" style="font-size:var(--d-fs-sm);">${esc(effectivePriority(t))}</div></div>` : ''}
                    </div>
                </div>
                ${t.notes ? `<div class="tp-drawer-section"><h4>Asana-Notizen</h4><div class="tp-drawer-notes">${esc(t.notes)}</div></div>` : ''}
            </div>
            <div class="tp-drawer-footer">
                ${t.asana_permalink_url ? `<a class="thx-btn thx-btn-secondary" href="${esc(t.asana_permalink_url)}" target="_blank" rel="noopener">In Asana öffnen</a>` : ''}
                <button class="thx-btn thx-btn-secondary" onclick="tpDrawerIgnore(${t.id})">Aus Planner ausblenden</button>
            </div>`;
        drw.classList.add('is-open');
        bg.classList.add('is-open');
    }
    window.tpCloseDrawer = function () { document.getElementById('tp-drawer').classList.remove('is-open'); document.getElementById('tp-drawer-bg').classList.remove('is-open'); };
    window.tpDrawerIgnore = async function (tid) { if (!confirm('Aus Planner ausblenden?')) return; await App.post('/planner/tasks/' + tid + '/ignore', { ignored: true }); tpCloseDrawer(); await load(); };
    window.tpResetFilters = function () { state.filter = { search: '', customer: 'all', effort: 'all', bucket: [], stale: 'all', sig: 'all', quick: 'all', overdue: 'all', deviation: 'all', recurring: 'all', started: 'all', signal: 'all', noeffort: 'all', hotcustomer: 'all', context: 'all', complexity: 'all', activity: 'all', completedRange: 'm3', waiting: 'hide' }; render(); };
    window.tpSetStep6Pivot = function (mode) {
        if (!['list','customer','activity'].includes(mode)) return;
        state.step6Pivot = mode;
        render();
    };
    window.tpSetWaiting = async function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        if (!t) return;
        if (t.is_waiting == 1) {
            // Wieder aktiv setzen — kein Modal nötig
            try {
                await App.post('/planner/tasks/' + tid + '/set-field', { is_waiting: 0 });
                t.is_waiting = 0; t.waiting_signal = 0;
                App.showNotification('Wieder aktiv', 'success');
                render();
            } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
            return;
        }
        // Modal-Picker öffnen: User-Liste + Kunden-Kontakte + Freitext
        let candidates = { internal: [], external: [], customer_name: null };
        try {
            const r = await App.get('/planner/tasks/' + tid + '/waiting-candidates');
            candidates = r.data || candidates;
        } catch (e) { /* Modal funktioniert auch ohne — nur Freitext */ }
        tpOpenWaitingModal(tid, candidates, t.waiting_on || '');
    };

    function tpOpenWaitingModal(tid, candidates, currentValue) {
        let m = document.getElementById('tp-waiting-modal');
        if (m) m.remove();
        m = document.createElement('div');
        m.id = 'tp-waiting-modal';
        m.className = 'tp-waiting-modal-backdrop';

        const internalHtml = candidates.internal.map(u =>
            `<button type="button" class="tp-wm-chip is-internal" data-name="${esc(u.name)}">
                <span class="tp-wm-abbr">${esc(u.abbreviation || (u.name||'?').substring(0,2).toUpperCase())}</span>
                ${esc(u.name)}
            </button>`
        ).join('');
        const externalHtml = candidates.external.length === 0 ? '' : `
            <div class="tp-wm-section-label">${esc(candidates.customer_name || 'Kunde')} · Ansprechpartner</div>
            <div class="tp-wm-grid">
                ${candidates.external.map(k => `
                    <button type="button" class="tp-wm-chip is-external" data-name="${esc(k.name)}">
                        <span class="material-symbols-rounded">badge</span>
                        <span>${esc(k.name)}${k.funktion ? ` · <span class="tp-wm-funktion">${esc(k.funktion)}</span>` : ''}</span>
                    </button>
                `).join('')}
            </div>`;

        m.innerHTML = `
            <div class="tp-waiting-modal">
                <div class="tp-wm-head">
                    <span class="material-symbols-rounded">pause_circle</span>
                    <h3>Wartet auf …</h3>
                    <button type="button" class="tp-wm-close" aria-label="Schließen"><span class="material-symbols-rounded">close</span></button>
                </div>
                <div class="tp-wm-body">
                    ${candidates.internal.length ? `
                        <div class="tp-wm-section-label">Team</div>
                        <div class="tp-wm-grid">${internalHtml}</div>
                    ` : ''}
                    ${externalHtml}
                    <div class="tp-wm-section-label">Oder Freitext</div>
                    <input type="text" class="tp-wm-input" placeholder="z.B. 'Antwort vom Steuerberater'" value="${esc(currentValue)}">
                </div>
                <div class="tp-wm-foot">
                    <button type="button" class="thx-btn thx-btn-secondary tp-wm-cancel">Abbrechen</button>
                    <button type="button" class="thx-btn thx-btn-primary tp-wm-submit">Auf Warten setzen</button>
                </div>
            </div>`;
        document.body.appendChild(m);
        const input = m.querySelector('.tp-wm-input');
        setTimeout(() => input.focus(), 30);

        const close = () => m.remove();
        const submit = async (value) => {
            close();
            try {
                const resp = await App.post('/planner/tasks/' + tid + '/set-field', { is_waiting: 1, waiting_on: value });
                const t = state.tasks.find(x => x.id === tid);
                if (t) { t.is_waiting = 1; t.waiting_on = value || null; t.waiting_since = new Date().toISOString(); }
                App.showNotification('Auf Warten gesetzt', 'success');
                const newBadges = resp?.data?.gamification?.new_achievements;
                if (newBadges && newBadges.length) {
                    App.showNotification('Achievement: ' + newBadges.map(b => b.icon + ' ' + b.label).join(' · '), 'success');
                }
                render();
            } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
        };

        m.addEventListener('click', (ev) => {
            if (ev.target === m) close();
            else if (ev.target.closest('.tp-wm-close, .tp-wm-cancel')) close();
            else if (ev.target.closest('.tp-wm-submit')) submit(input.value.trim());
            else {
                const chip = ev.target.closest('[data-name]');
                if (chip) submit(chip.dataset.name);
            }
        });
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') submit(input.value.trim());
            if (ev.key === 'Escape') close();
        });
    }
    window.tpAckSignal = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/ack-signal', {});
            const t = state.tasks.find(x => x.id === tid);
            if (t) t.waiting_signal = 0;
            render();
        } catch (e) { /* ignorieren */ }
    };
    window.tpToggleQuickWin = async function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        if (!t) return;
        const wasExcluded = t.quick_win_user_excluded == 1;
        const newVal = wasExcluded ? 0 : 1;
        try {
            await App.post('/planner/tasks/' + tid + '/set-field', { quick_win_user_excluded: newVal });
            t.quick_win_user_excluded = newVal;
            // Beim Entfernen: zur Sicherheit auch is_quick_task=0, damit's nicht durch die Hintertür
            // (KI hat is_quick_task gesetzt) wieder reinkommt — der User-Override gewinnt eh.
            App.showNotification(newVal ? 'Aus Quick-Wins entfernt' : 'Wieder als Quick-Win zulässig', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpToggleRecurring = async function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        if (!t) return;
        if (t.is_recurring == 1) {
            try {
                await App.post('/planner/tasks/' + tid + '/set-field', { is_recurring: 0, recurring_pattern: '', recurring_interval_days: 0 });
                t.is_recurring = 0; t.recurring_pattern = null; t.recurring_interval_days = null;
                App.showNotification('Wiederkehrend entfernt', 'success');
                render();
            } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
            return;
        }
        // Pattern auswählen — kleines Inline-Prompt, das auch das Intervall mitliefert
        const PRESETS = [
            { label: 'wöchentlich (7 Tage)', pattern: 'wöchentlich', days: 7 },
            { label: '2-wöchentlich (14 Tage)', pattern: '2-wöchentlich', days: 14 },
            { label: 'monatlich (30 Tage)', pattern: 'monatlich', days: 30 },
            { label: 'quartalsweise (90 Tage)', pattern: 'quartalsweise', days: 90 },
            { label: 'halbjährlich (180 Tage)', pattern: 'halbjährlich', days: 180 },
            { label: 'jährlich (365 Tage)', pattern: 'jährlich', days: 365 },
        ];
        const choice = prompt(
            'Wiederholungs-Intervall wählen — Zahl 1-6 oder eigenes Intervall in Tagen:\n\n' +
            PRESETS.map((p, i) => `${i+1}. ${p.label}`).join('\n') +
            '\n\nOder eine Zahl > 6 für freies Intervall (Tage).',
            '3'
        );
        if (choice === null) return;
        const n = parseInt(choice, 10);
        if (isNaN(n) || n < 1 || n > 730) { App.showNotification('Ungültig', 'error'); return; }
        let pattern, days;
        if (n <= 6) {
            const p = PRESETS[n-1];
            pattern = p.pattern; days = p.days;
        } else {
            pattern = `alle ${n} Tage`; days = n;
        }
        try {
            await App.post('/planner/tasks/' + tid + '/set-field', { is_recurring: 1, recurring_pattern: pattern, recurring_interval_days: days });
            t.is_recurring = 1; t.recurring_pattern = pattern; t.recurring_interval_days = days;
            App.showNotification(`Als wiederkehrend markiert (${pattern})`, 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpAckReanalyzed = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/ack-reanalyzed', {});
            const t = state.tasks.find(x => x.id === tid);
            if (t) { t.ai_re_analyzed_signal = 0; t.ai_re_analyzed_summary = null; }
            render();
        } catch (e) { /* ignorieren */ }
    };
    window.tpReopenTask = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/complete', { completed: false });
            const t = state.tasks.find(x => x.id === tid);
            if (t) { t.completed_at_local = null; }
            App.showNotification('Wieder aktiviert', 'success');
            render();
            tpRefreshScore();
        } catch (e) {
            App.showNotification('Reaktivieren fehlgeschlagen: ' + (e.message || ''), 'error');
        }
    };
    // Sidebar (Phasen-Stepper) ein-/ausklappen — persistiert in localStorage,
    // wird beim Laden direkt unten initialisiert, damit kein Flackern entsteht.
    window.tpToggleSidebar = function () {
        const sb = document.getElementById('tp-sidebar');
        if (!sb) return;
        sb.classList.toggle('is-collapsed');
        const collapsed = sb.classList.contains('is-collapsed');
        try { localStorage.setItem('tp-sb-collapsed', collapsed ? '1' : '0'); } catch (e) {}
        const icon = document.getElementById('tp-sb-toggle-icon');
        if (icon) icon.textContent = collapsed ? 'menu' : 'menu_open';
    };
    (function () {
        try {
            if (localStorage.getItem('tp-sb-collapsed') === '1') {
                document.getElementById('tp-sidebar')?.classList.add('is-collapsed');
                const icon = document.getElementById('tp-sb-toggle-icon');
                if (icon) icon.textContent = 'menu';
            }
        } catch (e) {}
    })();
    window.tpGoStep = function (n) {
        state.currentStep = n;
        try { history.replaceState(null, '', '#step-' + n); } catch (_) {}
        render();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // ===== Global Actions =====
    window.tpSavePat = async function () {
        const pat = document.getElementById('tp-pat-input').value.trim();
        if (!pat) { App.showNotification('Token leer', 'error'); return; }
        const out = document.getElementById('tp-pat-result');
        out.innerHTML = '<span style="color:var(--slate-500);">Prüfe…</span>';
        try {
            const r = await App.post('/planner/pat', { pat });
            if (!r.success) throw new Error(r.message);
            out.innerHTML = `<span style="color:#15803d;">Verbunden als <strong>${esc(r.data.name)}</strong></span>`;
            setTimeout(load, 800);
        } catch (e) { out.innerHTML = '<span style="color:#b91c1c;">' + esc(e.message || 'Fehler') + '</span>'; }
    };
    // ===== Bulk-Selection für Unklar-Klärer =====
    window.tpBulkUnclearToggle = function (tid, checked) {
        if (!state.unclearSel) state.unclearSel = new Set();
        if (checked) state.unclearSel.add(tid);
        else state.unclearSel.delete(tid);
        render();
    };
    window.tpBulkUnclearToggleAll = function (checked) {
        if (!state.unclearSel) state.unclearSel = new Set();
        const open = openTasks();
        const unclear = open.filter(t => !t.customer_id && t.category_hint !== 'private');
        if (checked) unclear.forEach(t => state.unclearSel.add(t.id));
        else state.unclearSel.clear();
        render();
    };
    window.tpBulkUnclearClear = function () {
        state.unclearSel = new Set();
        render();
    };
    window.tpBulkUnclear = async function (action, customerId) {
        const ids = Array.from(state.unclearSel || []);
        if (ids.length === 0) return;
        const body = { task_ids: ids, action };
        if (action === 'customer') {
            if (!customerId) return;
            body.customer_id = customerId;
        }
        try {
            const r = await App.post('/planner/bulk-set', body);
            if (!r.success) throw new Error(r.message);
            App.showNotification(`${r.data.updated} Tasks zugeordnet`, 'success');
            state.unclearSel = new Set();
            await load();
        } catch (e) {
            App.showNotification('Bulk-Fehler: ' + (e.message || ''), 'error');
        }
    };

    window.tpAssignUnclear = async function (tid, value, selectEl) {
        if (!value) return;
        const row = selectEl.closest('.tp-unclear-row');
        selectEl.disabled = true;
        try {
            if (value === 'ignore') {
                await App.post('/planner/tasks/' + tid + '/ignore', { ignored: true });
            } else if (value === 'private') {
                await App.post('/planner/tasks/' + tid + '/set-field', { customer_id: null, category_hint: 'private' });
            } else if (value.startsWith('c:')) {
                const cid = parseInt(value.substring(2), 10);
                await App.post('/planner/tasks/' + tid + '/set-field', { customer_id: cid, category_hint: null });
            } else {
                selectEl.disabled = false;
                return;
            }
            if (row) row.classList.add('is-done');
            // Local state nachziehen ohne kompletten Reload — schneller für Listen-Click-Through
            const t = state.tasks.find(x => x.id === tid);
            if (t) {
                if (value === 'ignore') t.planner_ignored = 1;
                else if (value === 'private') { t.customer_id = null; t.category_hint = 'private'; }
                else if (value.startsWith('c:')) {
                    const cid = parseInt(value.substring(2), 10);
                    const ref = state.tasks.find(x => x.customer_id === cid);
                    t.customer_id = cid;
                    t.category_hint = null;
                    if (ref) { t.customer_name = ref.customer_name; t.customer_abbr = ref.customer_abbr; t.customer_color = ref.customer_color; }
                }
            }
        } catch (e) {
            selectEl.disabled = false;
            selectEl.value = '';
            App.showNotification('Fehler: ' + (e.message || ''), 'error');
        }
    };

    window.tpSortSlots = async function () {
        const btn = document.getElementById('tp-sort-btn');
        const cap = {
            today:     parseInt(document.getElementById('tp-cap-today')?.value || '240', 10),
            tomorrow:  parseInt(document.getElementById('tp-cap-tom')?.value || '240', 10),
            rest_week: parseInt(document.getElementById('tp-cap-week')?.value || '1200', 10),
        };
        state.planCapacity = cap;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite;">auto_awesome</span> KI sortiert…'; }
        try {
            const r = await App.post('/planner/sort-slots', { capacity: cap });
            if (!r.success) throw new Error(r.message);
            state.slotReasoning = r.data.reasoning || {};
            state.slotSummary = r.data.summary || '';
            App.showNotification(`KI hat ${r.data.applied} Tasks in Slots verteilt`, 'success');
            await load();
        } catch (e) {
            App.showNotification('KI-Sortierung fehlgeschlagen: ' + (e.message || ''), 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    window.tpResolve = async function () {
        const btn = document.getElementById('tp-resolve-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite;">auto_fix_high</span> Berechne…'; }
        try {
            const r = await App.post('/planner/resolve-customers', {});
            if (!r.success) throw new Error(r.message);
            const s = r.data || {};
            App.showNotification(`${s.updated || 0} Tasks neu zugeordnet · ${s.with_customer || 0} mit Kunde · ${s.private || 0} Privat · ${s.unclear || 0} Unklar`, 'success');
            await load();
        } catch (e) {
            App.showNotification('Re-Resolve fehlgeschlagen: ' + (e.message || ''), 'error');
        }
    };
    window.tpSync = async function () {
        if (state.syncing) return;
        state.syncing = true;
        const btn = document.getElementById('tp-sync-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite;">sync</span> Sync…'; }
        try {
            const r = await App.post('/planner/sync', {});
            if (!r.success) throw new Error(r.message);
            App.showNotification(`Sync: ${r.data.tasks_seen} gesehen, ${r.data.tasks_created} neu, ${r.data.tasks_updated} aktualisiert`, 'success');
            await load();
        } catch (e) { App.showNotification('Sync fehlgeschlagen: ' + (e.message || ''), 'error'); }
        finally { state.syncing = false; }
    };
    window.tpResetAnalysis = async function () {
        if (!confirm('Re-Analyse erzwingen?\n\nDas setzt KI-Summary und Asana-Aktivitäten für alle offenen Tasks zurück. Du musst danach „Jetzt analysieren" mehrfach klicken, damit die KI alles neu durchgeht — diesmal MIT Asana-Kommentaren und Quick-Task-Erkennung.')) return;
        const btn = document.getElementById('tp-reset-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite;">restart_alt</span> Zurücksetzen…'; }
        try {
            const r = await App.post('/planner/reset-analysis', {});
            if (!r.success) throw new Error(r.message);
            App.showNotification(`${r.data.reset} Tasks für Re-Analyse markiert. Klick jetzt „Jetzt analysieren".`, 'success');
            await load();
        } catch (e) {
            App.showNotification('Reset fehlgeschlagen: ' + (e.message || ''), 'error');
        }
    };

    window.tpAnalyze = async function () {
        if (state.analyzing) return;
        state.analyzing = true;
        const btn = document.getElementById('tp-analyze-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite;">psychology</span> Analysiere…'; }
        try {
            const r = await App.post('/planner/estimate-efforts', {});
            if (!r.success) throw new Error(r.message);
            const d = r.data || {};
            let msg = `KI hat ${d.estimated || 0} Tasks analysiert`;
            if (d.failed && d.failed > 0) {
                msg += ` · ${d.failed} mussten mit Default-Werten bestückt werden`;
                if (d.api_error) msg += ' (API-Fehler: ' + d.api_error.substring(0, 80) + ')';
            }
            App.showNotification(msg, d.failed > 0 ? 'warning' : 'success');
            if (d.failed_names && d.failed_names.length) {
                console.warn('KI konnte folgende Tasks nicht zuverlässig analysieren:', d.failed_names);
            }
            await load();
        } catch (e) { App.showNotification('Analyse fehlgeschlagen: ' + (e.message || ''), 'error'); }
        finally { state.analyzing = false; }
    };
    // ===== Step 6 Actions =====
    /**
     * Verschiebt eine Karte in der Heute-Spalte (Step 6) um eine Position nach oben/unten.
     * Nutzt state.todayOrder als Reihenfolge-Quelle. Falls noch leer, wird sie aus dem aktuellen DOM-State befüllt.
     */
    window.tpMoveCard = function (tid, delta) {
        if (!state.todayOrder) state.todayOrder = [];
        const heute = openTasks().filter(t => t.planned_for_date === localDate()).sort((a,b) => (b.score||0) - (a.score||0));
        // Aktuelle Reihenfolge auffrischen falls Tasks fehlen
        heute.forEach(t => { if (!state.todayOrder.includes(t.id)) state.todayOrder.push(t.id); });
        state.todayOrder = state.todayOrder.filter(id => heute.some(t => t.id === id));
        const i = state.todayOrder.indexOf(tid);
        if (i < 0) return;
        const j = i + delta;
        if (j < 0 || j >= state.todayOrder.length) return;
        [state.todayOrder[i], state.todayOrder[j]] = [state.todayOrder[j], state.todayOrder[i]];
        render();
    };

    window.tpAddToToday = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/set-field', { plan_today: 1 });
            const t = state.tasks.find(x => x.id === tid);
            if (t) {
                t.planned_for_date = localDate();
                if (!state.todayOrder) state.todayOrder = [];
                if (!state.todayOrder.includes(tid)) state.todayOrder.push(tid);
            }
            App.showNotification('In den Tagesplan aufgenommen', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpToggleCustomerHot = async function (cid) {
        const cur = state.tasks.find(t => t.customer_id == cid);
        const willBe = cur && cur.customer_is_hot == 1 ? 0 : 1;
        try {
            await App.post('/planner/customer-hot', { customer_id: cid, hot: willBe });
            state.tasks.forEach(t => { if (t.customer_id == cid) t.customer_is_hot = willBe; });
            App.showNotification(willBe ? '🔥 Kunde als brennend markiert' : 'Brennend-Markierung entfernt', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpToggleToad = async function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        const willBe = t && t.is_toad == 1 ? 0 : 1;
        try {
            await App.post('/planner/tasks/' + tid + '/set-field', { is_toad: willBe });
            // Immer nur eine Kröte: lokal die anderen zurücksetzen.
            state.tasks.forEach(x => { x.is_toad = 0; });
            if (t) t.is_toad = willBe;
            App.showNotification(willBe ? '🐸 Kröte des Tages gesetzt — die gehst Du heute an!' : 'Kröte entfernt', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpAddQuickWins = async function (ids) {
        if (!Array.isArray(ids) || !ids.length) return;
        try {
            await Promise.all(ids.map(id => App.post('/planner/tasks/' + id + '/set-field', { plan_today: 1 })));
            ids.forEach(id => {
                const t = state.tasks.find(x => x.id === id);
                if (t) t.planned_for_date = localDate();
                if (!state.todayOrder.includes(id)) state.todayOrder.push(id);
            });
            App.showNotification(ids.length + ' Quick Wins im Tagesplan', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };

    window.tpCompleteAndCelebrate = async function (tid) {
        const t = state.tasks.find(x => x.id === tid);
        // Recurring-Task-Hinweis VOR dem Abhaken: User soll Asana-Frist verlängern.
        // Wenn user den Hinweis bestätigt, weitermachen. Wenn er abbricht, kein Complete.
        if (t && t.is_recurring == 1) {
            const interval = t.recurring_interval_days || 14;
            const pattern = t.recurring_pattern || 'wiederkehrend';
            const nextDate = new Date(Date.now() + interval * 86400000).toLocaleDateString('de-DE');
            const proceed = confirm(
                `Diese Task ist ${pattern}. Hast Du die Asana-Frist verlängert ` +
                `(nächste Fälligkeit z.B. ${nextDate}, +${interval} Tage)?\n\n` +
                `Wenn ja, hier abhaken — sie verschwindet aus dem Tagesplan, bis Asana sie ` +
                `mit neuem Datum wieder reinholt.\n\nWenn noch nicht: Abbrechen, ` +
                `Frist in Asana setzen, dann hier abhaken.`
            );
            if (!proceed) return;
        }
        try {
            const resp = await App.post('/planner/tasks/' + tid + '/complete', { completed: true });
            const gami = resp?.data?.gamification || null;
            // Lokal updaten, damit Re-Render sofort flüssig wirkt
            if (t) t.completed_at_local = new Date().toISOString();
            // Auto-Refill: wenn der Tagesplan leer wäre, die dringendste fällige Task nachziehen.
            const ptoday = localDate();
            const remainingToday = openTasks().filter(x => x.planned_for_date === ptoday).length;
            let refilled = null;
            if (remainingToday === 0) {
                const candidates = openTasks().filter(x => x.planned_for_date !== ptoday && x.daily_slot === 'today').sort((a,b) => (b.score||0)-(a.score||0));
                if (candidates.length) {
                    refilled = candidates[0];
                    await App.post('/planner/tasks/' + refilled.id + '/set-field', { plan_today: 1, _auto: 1 });
                    refilled.planned_for_date = ptoday;
                }
            }
            tpShowCelebration(t, remainingToday, refilled, gami);
            render();
            tpRefreshScore();
        } catch (e) {
            App.showNotification('Fehler beim Abhaken: ' + (e.message || ''), 'error');
        }
    };

    function tpShowCelebration(task, remainingAfter, refilled, gami) {
        const ov = document.getElementById('tp-confetti');
        const emojis = ['🎉','✨','🚀','🔥','💪','⭐','🎯','👏'];
        const e = emojis[Math.floor(state.tasks.length * 31 % emojis.length)] || '🎉';
        const todayDoneToday = state.tasks.filter(x => x.completed_at_local && isSameDay(x.completed_at_local)).length;
        const next = openTasks().filter(x => x.planned_for_date === localDate()).sort((a,b) => (b.score||0)-(a.score||0))[0];
        const isToad = gami && gami.event === 'toad';
        const headline = isToad
            ? '🐸 Kröte verschluckt!'
            : (remainingAfter === 0 && !refilled
                ? 'Tag geschafft!'
                : (todayDoneToday >= 5 ? 'Stark unterwegs!' : 'Gut gemacht!'));
        const subline = isToad
            ? 'Die schlimmste Aufgabe des Tages ist weg — alles andere geht jetzt leichter.'
            : refilled
            ? `Nächste Task aus Morgen geholt: <strong>${esc(refilled.name)}</strong>`
            : (next
                ? `Nächste Aufgabe wartet: <strong>${esc(next.name)}</strong>`
                : (remainingAfter === 0 ? 'Heute ist erledigt — Pause verdient.' : `Noch ${remainingAfter} Tasks für heute.`));
        // Punkte/Bonus/Badge dezent einblenden (mittlere Sichtbarkeit).
        let pointsHtml = '';
        if (gami && gami.gained > 0) {
            const bonus = gami.bonus > 0 ? ` <span style="color:var(--emerald-600,#059669);">+${gami.bonus} Bonus: alle Heute-Tasks!</span>` : '';
            pointsHtml = `<div class="tp-celebrate-pts" style="font-weight:700;color:var(--thoxan-700);margin-bottom:6px;">+${gami.gained} Punkte${bonus}</div>`;
        }
        const badgeHtml = (gami && gami.new_achievements && gami.new_achievements.length)
            ? `<div class="tp-celebrate-badge" style="margin:4px 0 12px;padding:8px 12px;background:var(--thoxan-50);border-radius:8px;font-size:var(--d-fs-sm);">Neues Achievement: ${tpBadgeLine(gami.new_achievements)}</div>`
            : '';
        ov.innerHTML = `
            <div class="tp-confetti-card">
                <div class="tp-confetti-emoji">${isToad ? '🐸' : e}</div>
                <div class="tp-confetti-title">${headline}</div>
                ${pointsHtml}
                <div class="tp-confetti-sub">${subline}</div>
                ${badgeHtml}
                <div style="display:flex;gap:8px;justify-content:center;">
                    <button class="thx-btn thx-btn-primary" onclick="tpCloseCelebration(); tpSyncQuiet();">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">sync</span>
                        Sync + weiter
                    </button>
                    <button class="thx-btn thx-btn-secondary" onclick="tpCloseCelebration()">OK</button>
                </div>
            </div>
        `;
        // Konfetti-Stücke erzeugen
        const colors = ['#dc2626','#f59e0b','#10b981','#3b82f6','#a855f7','#ec4899'];
        for (let i = 0; i < 36; i++) {
            const piece = document.createElement('div');
            piece.className = 'tp-confetti-piece';
            piece.style.left = (Math.random() * 100) + 'vw';
            piece.style.background = colors[i % colors.length];
            piece.style.animationDelay = (Math.random() * 0.4) + 's';
            ov.appendChild(piece);
        }
        ov.classList.add('is-open');
        setTimeout(() => { tpCloseCelebration(); }, 4200);
    }
    window.tpCloseCelebration = function () {
        const ov = document.getElementById('tp-confetti');
        ov.classList.remove('is-open');
        ov.innerHTML = '';
    };
    window.tpSyncQuiet = async function () {
        try { await App.post('/planner/sync', {}); await load(); } catch (e) {}
    };
    window.tpMarkOpenedInAsana = function (tid) {
        // Klicken auf "In Asana öffnen" — kein Auto-Complete; User klickt nach Erledigen den grünen Button.
        // Wir könnten hier ein Open-Timestamp speichern für Analytics — bewusst nicht jetzt.
    };
    window.tpPostponeFromOutput = async function (tid) {
        try {
            await App.post('/planner/tasks/' + tid + '/set-field', { daily_slot: 'tomorrow' });
            const t = state.tasks.find(x => x.id === tid);
            if (t) t.daily_slot = 'tomorrow';
            App.showNotification('Auf Morgen verschoben', 'success');
            render();
        } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
    };
    window.tpRefreshPlan = async function () {
        const todayTasks = openTasks().filter(t => t.planned_for_date === localDate());
        if (!todayTasks.length) return;
        const sumEffort = todayTasks.reduce((a, t) => a + ((t.effort_minutes || t.ai_effort_estimate || 60)), 0);
        try {
            const r = await App.post('/planner/plan-day', { minutes: sumEffort, slot: 'today' });
            if (r.success) { state.plan = r.data; render(); }
        } catch (e) { App.showNotification('KI-Sequenz fehlgeschlagen: ' + (e.message || ''), 'error'); }
    };

    window.tpPlanDay = async function () {
        const custom = parseInt(document.getElementById('tp-plan-custom')?.value || '120', 10);
        const minutes = isNaN(custom) ? (state.planMinutes || 120) : custom;
        const focusNote = document.getElementById('tp-focus-note')?.value || '';
        state.planning = true; state.plan = null; render();
        try {
            const r = await App.post('/planner/plan-day', { minutes, focus_note: focusNote, slot: 'today' });
            if (!r.success) throw new Error(r.message);
            state.plan = r.data;
        } catch (e) { App.showNotification('Planung fehlgeschlagen: ' + (e.message || ''), 'error'); }
        finally { state.planning = false; render(); }
    };

    const styleEl = document.createElement('style');
    styleEl.textContent = '@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }';
    document.head.appendChild(styleEl);

    function waitForApp(fn) {
        if (window.App && typeof App.get === 'function') { fn(); return; }
        const i = setInterval(() => { if (window.App && typeof App.get === 'function') { clearInterval(i); fn(); } }, 50);
    }
    waitForApp(async () => {
        await load();
        // Anzeigen was passiert ist + Mark-Seen damit's beim nächsten Load wieder bei 0 startet
        if (state.newCount > 0) {
            const msg = state.autoPushedToday > 0
                ? `${state.newCount} neue Tasks seit Deinem letzten Besuch · ${state.autoPushedToday} davon direkt in Heute eingeplant`
                : `${state.newCount} neue Tasks seit Deinem letzten Besuch`;
            App.showNotification(msg, 'info');
        }
        try { await App.post('/planner/mark-seen', {}); } catch (_) {}
    });
})();
</script>
