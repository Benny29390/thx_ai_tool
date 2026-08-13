<?php
$db = \Core\Database::getInstance();
$customerId = (int) $customer['id'];

// Asana-Settings
$settingsJson = json_decode($customer['settings'] ?? '{}', true) ?: [];
$asanaCfg = $settingsJson['asana'] ?? [];
$projectGids = $asanaCfg['project_gids'] ?? [];
$lastSyncAt = $asanaCfg['last_sync_at'] ?? null;

// Wissen-Stats
$kbDocsTotal = (int) ($db->queryValue(
    "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ?",
    [$customerId]
) ?: 0);
$kbDocsAsana = (int) ($db->queryValue(
    "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ? AND source_type = 'asana'",
    [$customerId]
) ?: 0);
$kbTaskCount = (int) ($db->queryValue(
    "SELECT COUNT(*) FROM knowledge_documents WHERE customer_id = ? AND source_type = 'asana' AND external_id LIKE 'task:%'",
    [$customerId]
) ?: 0);

// Letzte 10 Wissen-Eintraege
$latestDocs = $db->query(
    "SELECT id, title, source_type, category, created_at, source_ref
     FROM knowledge_documents
     WHERE customer_id = ?
     ORDER BY updated_at DESC
     LIMIT 10",
    [$customerId]
) ?: [];

// Letzter Asana-Job
$lastJob = $db->queryOne(
    "SELECT id, status, created_at, started_at, completed_at, error_message
     FROM generation_jobs
     WHERE customer_id = ? AND job_type = 'asana_sync'
     ORDER BY id DESC LIMIT 1",
    [$customerId]
);
?>

<style>
.cs-wrap { font-size: var(--d-fs-sm); }
.cs-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; }
.cs-header h1 { margin: 0 0 0.25rem; font-size: var(--d-fs-2xl); }
.cs-customer-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    height: 88px;
    padding: 0 14px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--thoxan-700), #1976d2);
    color: #fff;
    font-size: var(--d-fs-2xl);
    font-weight: 800;
    letter-spacing: 1px;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(0, 76, 155, 0.18);
    text-transform: uppercase;
}
/* Logo-Wrap (Badge ODER Bild + Upload-/Delete-Buttons als Overlay) */
.cs-logo-wrap {
    position: relative;
    width: 88px;
    height: 88px;
    flex-shrink: 0;
}
.cs-logo-wrap .cs-customer-badge,
.cs-logo-wrap .cs-customer-logo {
    width: 100%;
    height: 100%;
    min-width: 0;
}
.cs-customer-logo {
    object-fit: contain;
    border-radius: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 6px;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
    cursor: pointer;
}
/* Ein einziger Edit-Button — nur bei Hover sichtbar, dezent unten rechts.
   Klick oeffnet das Mini-Menue mit allen Logo-Aktionen. */
.cs-logo-edit-btn {
    position: absolute;
    bottom: 4px; right: 4px;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(4px);
    border: 1px solid var(--slate-200);
    color: var(--slate-700);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    opacity: 0;
    transform: translateY(2px);
    transition: opacity 0.12s, transform 0.12s, background 0.1s, color 0.1s;
    z-index: 2;
    padding: 0;
}
.cs-logo-edit-btn .material-symbols-rounded { font-size: 14px; }
.cs-logo-wrap:hover .cs-logo-edit-btn,
.cs-logo-wrap:focus-within .cs-logo-edit-btn,
.cs-logo-menu.is-open ~ .cs-logo-edit-btn,
.cs-logo-wrap.is-menu-open .cs-logo-edit-btn {
    opacity: 1;
    transform: translateY(0);
}
.cs-logo-edit-btn:hover { background: #fff; color: var(--thoxan-700); border-color: var(--thoxan-300); }

/* Popover-Menue: oeffnet nach rechts unter dem Logo (Logo sitzt am linken Rand
   neben der Sidebar, daher KEIN right:0 — sonst ragt das Menue in die Sidebar). */
.cs-logo-menu {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: 200px;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    padding: 4px;
    z-index: 100;
    display: none;
}
.cs-logo-menu.is-open { display: block; }
.cs-logo-menu-item {
    display: flex; align-items: center; gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border: 0; background: transparent;
    text-align: left;
    font-family: inherit; font-size: var(--d-fs-sm);
    color: var(--slate-700);
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.08s, color 0.08s;
}
.cs-logo-menu-item:hover:not(:disabled) { background: var(--slate-50); color: var(--slate-900); }
.cs-logo-menu-item:disabled { opacity: 0.4; cursor: not-allowed; }
.cs-logo-menu-item .material-symbols-rounded { font-size: 18px; color: var(--slate-500); }
.cs-logo-menu-item:hover:not(:disabled) .material-symbols-rounded { color: var(--thoxan-600); }
.cs-logo-menu-item.is-danger { color: var(--rose-700); }
.cs-logo-menu-item.is-danger .material-symbols-rounded { color: var(--rose-500); }
.cs-logo-menu-item.is-danger:hover:not(:disabled) { background: var(--rose-50); color: var(--rose-800); }
.cs-logo-wrap.is-uploading::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.65);
    border-radius: 18px;
    backdrop-filter: blur(2px);
    z-index: 1;
}
.cs-logo-wrap.is-uploading::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    width: 24px; height: 24px;
    margin: -12px 0 0 -12px;
    border: 3px solid #cbd5e1;
    border-top-color: var(--thoxan-700);
    border-radius: 50%;
    animation: cs-logo-spin 0.7s linear infinite;
    z-index: 3;
}
@keyframes cs-logo-spin { to { transform: rotate(360deg); } }
.cs-header .subtitle { color: #64748b; font-size: var(--d-fs-sm); }
.cs-header-actions { display: flex; gap: 0.5rem; }
.cs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: start; }
@media (max-width:768px){ .cs-grid { grid-template-columns: 1fr; } }
.cs-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; }
.cs-card h3 { margin: 0 0 0.5rem; font-size: var(--d-fs-lg); display:flex;align-items:center;gap:0.5rem; }
.cs-card h3 small { color:#94a3b8; font-weight:normal; font-size: var(--d-fs-xs); }
.cs-row { display: grid; grid-template-columns: 140px 1fr; gap:0.75rem; padding:0.4rem 0; font-size: var(--d-fs-sm); border-bottom:1px solid #f1f5f9; }
.cs-row:last-child { border-bottom: none; }
.cs-row-label { color:#64748b; font-weight: 500; }
.cs-row-value { white-space: pre-wrap; word-break: break-word; }
.cs-stats { display:flex; gap:1rem; margin: 0.5rem 0 1rem; flex-wrap:wrap; }
.cs-stat { padding: 0.6rem 0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; }
.cs-stat-label { font-size: var(--d-fs-xs); color:#64748b; text-transform:uppercase; letter-spacing:0.5px; }
.cs-stat-value { font-size: var(--d-fs-xl); font-weight: 700; color:#1e293b; margin-top:2px; }
.cs-empty { text-align:center; padding:1.5rem; color:#94a3b8; font-size: var(--d-fs-sm); }
.cs-doc-row { display: flex; gap: 0.6rem; padding: 0.5rem 0.6rem; border-bottom: 1px solid #f1f5f9; align-items:center; }
.cs-doc-row:last-child { border-bottom: none; }
.cs-doc-icon { color: var(--thoxan-700); font-size: 18px; }
.cs-doc-title { flex:1; min-width:0; font-size: var(--d-fs-sm); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cs-doc-title a { color: inherit; text-decoration:none; }
.cs-doc-title a:hover { color: var(--thoxan-700); }
.cs-doc-type { font-size: var(--d-fs-xs); padding:1px 6px; border-radius:4px; background:#e6f0fa; color:var(--thoxan-900); }
.cs-doc-type-asana { background:#fff7ed; color:#c2410c; }

/* ===== Toolbar (Suche + Add) ===== */
.sb-toolbar {
    position: sticky; top: 0; z-index: 50;
    display: flex; gap: 0.75rem; align-items: center;
    padding: 0.75rem; margin-bottom: 1rem;
    background: rgba(255,255,255,0.85); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border: 1px solid #e2e8f0; border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
}
.sb-search-wrap {
    flex: 1; position: relative; display: flex; align-items: center;
}
.sb-search-icon {
    position: absolute; left: 12px; color: #94a3b8; font-size: 20px; pointer-events: none;
    transition: color 0.2s, transform 0.2s;
}
.sb-search-wrap:focus-within .sb-search-icon { color: var(--thoxan-700); transform: scale(1.05); }
#sb-search {
    width: 100%; padding: 0.7rem 2.5rem 0.7rem 40px;
    border: 1px solid #e2e8f0; border-radius: 10px; font-size: var(--d-fs-sm);
    background: #fff; transition: all 0.2s;
}
#sb-search:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0,76,155,0.12); }
.sb-search-clear {
    position: absolute; right: 40px; background: none; border: none; cursor: pointer;
    color: #64748b; padding: 4px; border-radius: 6px; display: flex; align-items: center;
}
.sb-search-clear:hover { background: #f1f5f9; }
.sb-search-clear .material-symbols-rounded { font-size: 18px; }
#sb-search { padding-right: 70px; }
.sb-search-ai {
    position: absolute; right: 8px; background: none; border: none; cursor: pointer;
    color: #10b981; padding: 4px 6px; border-radius: 6px; display: flex; align-items: center;
    transition: all 0.15s;
}
.sb-search-ai:hover { background: rgba(16,185,129,0.1); transform: scale(1.08); }
.sb-search-ai.busy .material-symbols-rounded { animation: sb-ai-spin 1.4s linear infinite; }
.sb-search-ai .material-symbols-rounded { font-size: 19px; }

/* KI-Such-Ergebnis-Panel */
.sb-ai-result-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
    z-index: 410; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.sb-ai-result-overlay.open { display: flex; animation: sb-fade-in 0.2s; }
.sb-ai-result-modal {
    width: 92vw; max-width: 720px; max-height: 90vh;
    background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    display: flex; flex-direction: column; overflow: hidden;
    animation: sb-zoom-in 0.2s ease-out;
}
.sb-ai-result-head {
    display: flex; gap: 0.6rem; align-items: center;
    padding: 1rem 1.2rem; border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(135deg, var(--emerald-50) 0%, #fff 100%);
}
.sb-ai-result-head .material-symbols-rounded:first-child {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #10b981, #34d399); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
    flex-shrink: 0;
}
.sb-ai-result-head h3 { margin: 0; font-size: var(--d-fs-base); color: #1e293b; }
.sb-ai-result-head small { display: block; color: #94a3b8; font-size: var(--d-fs-sm); margin-top: 2px; }
.sb-ai-result-body { flex: 1; overflow-y: auto; padding: 1rem 1.2rem; min-height: 0; }
.sb-ai-answer {
    background: var(--emerald-50); border: 1px solid #bbf7d0; border-radius: 10px;
    padding: 14px 16px; margin-bottom: 1rem; font-size: var(--d-fs-sm); line-height: 1.6; color: #064e3b;
    white-space: pre-wrap;
}
.sb-ai-answer.loading { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
.sb-ai-matches-head {
    font-size: var(--d-fs-sm); text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8;
    margin: 0 0 0.5rem; font-weight: 700;
}
.sb-ai-match {
    display: block; padding: 10px 12px; margin-bottom: 6px;
    border: 1px solid #e2e8f0; border-radius: 10px;
    text-decoration: none; color: inherit; transition: all 0.15s; cursor: pointer;
}
.sb-ai-match:hover { border-color: #cbd5e1; background: #fafbfc; }
.sb-ai-match-type {
    display: inline-block; padding: 1px 8px; border-radius: 4px;
    font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    margin-right: 6px;
}
.sb-ai-match-type.card { background: #e6f0fa; color: var(--thoxan-900); }
.sb-ai-match-type.chat { background: #f3e8ff; color: #6b21a8; }
.sb-ai-match-title { font-weight: 600; color: #1e293b; font-size: var(--d-fs-sm); }
.sb-ai-match-snippet { color: #64748b; font-size: var(--d-fs-sm); margin-top: 3px; line-height: 1.4; }
.sb-ai-result-foot { padding: 0.8rem 1.2rem; display: flex; justify-content: flex-end; }

.sb-add-wrap { position: relative; }
.sb-more-wrap { position: relative; }

/* Per-Card Edit-Mode: NUR Inhalts-EDIT-Werkzeuge (Formatieren, einzeln Loeschen)
   sind versteckt bis Card .editing hat. Inhalt HINZUFUEGEN (+ Link, Drop-Zone)
   muss immer sichtbar sein, sonst sind leere Cards unbrauchbar. */
.sb-card-user:not(.editing) .sb-link-remove,
.sb-card-user:not(.editing) .sb-richtext-toolbar,
.sb-card-user:not(.editing) .sb-image-remove,
.sb-card-user:not(.editing) .sb-file-remove {
    display: none !important;
}

/* LAYOUT-EDIT-MODE (Admin-Toggle): Resize-, Move-, Delete-, Drag-Werkzeuge
   sind im Default GANZ versteckt — Layout bleibt konsistent ueber alle Kunden.
   Nur wenn body.cs-layout-edit aktiv ist, tauchen die Werkzeuge auf. */
body:not(.cs-layout-edit) .sb-resize-btn,
body:not(.cs-layout-edit) .sb-move-btn,
body:not(.cs-layout-edit) .sb-card-delete-btn,
body:not(.cs-layout-edit) .cs-shell-actions .sb-card-action-wrap {
    display: none !important;
}
body:not(.cs-layout-edit) .sb-card-user .sb-card-head { cursor: default !important; }
body:not(.cs-layout-edit) .sb-card-user[draggable="true"] { /* Drag-Visual neutralisieren */ }
body.cs-layout-edit .thx-page-actions #cs-layout-edit-toggle {
    background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700);
}
/* Template-Buttons NUR im Layout-Edit-Modus sichtbar */
body:not(.cs-layout-edit) .cs-tpl-action { display: none !important; }
body.cs-layout-edit::before {
    content: 'Layout-Anpassungs-Modus — Verschieben, Größe ändern, Löschen aktiv. Wirkt für alle User. Mit „Fertig" verlassen.';
    position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
    padding: 8px 16px;
    background: var(--amber-100); color: var(--amber-800);
    font-size: var(--d-fs-xs); font-weight: 600;
    text-align: center;
    border-bottom: 1px solid var(--amber-300);
}
body.cs-layout-edit { padding-top: 32px; }
/* Hover-Affordances im View neutralisieren */
.sb-card-user:not(.editing) .sb-link-row input:hover,
.sb-card-user:not(.editing) .sb-contact-row input:hover,
.sb-card-user:not(.editing) .sb-card-title:hover,
.sb-card-user:not(.editing) .cs-inline-input:not(.cs-pf-input):hover {
    background: transparent !important; border-color: transparent !important;
}
/* Card-Head ist immer Drag-Handle — Cursor: grab beim Hover, grabbing beim Ziehen */
.sb-card-user .sb-card-head { cursor: grab; }
.sb-card-user .sb-card-head:active { cursor: grabbing; }
.sb-card-user .sb-card-head .sb-card-title,
.sb-card-user .sb-card-head .sb-card-actions { cursor: auto; }

/* Edit-Button: nur im Edit-Modus sichtbar (Check als Abschluss-Button)
   Klick-in-Edit ersetzt den Stift — der Edit-Button war nur sichtbar, wenn
   man schon im Edit-Modus ist, und dient dann als „Fertig"-Aktion. */
/* Stift-Button: immer sichtbar (Bearbeiten starten). Im Edit-Modus wird er zum
   gefuellten Check (Bearbeitung abschliessen). */
.sb-card-edit-btn { color: var(--thoxan-700); }
.sb-card-edit-btn:hover { background: rgba(0,76,155,0.08); color: var(--thoxan-900); }
.sb-card-edit-btn.is-active {
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)) !important;
    color: #fff !important;
}
.sb-card-edit-btn.is-active:hover {
    background: linear-gradient(135deg, var(--thoxan-900), var(--thoxan-700)) !important;
}
.sb-card.editing { border-color: var(--thoxan-300); box-shadow: 0 0 0 3px rgba(0, 76, 155, 0.10); }
/* Klick-Affordance: Body sieht klickbar/textuell aus, wenn Card nicht im Edit-Modus */
.sb-card-user:not(.editing) .sb-card-body { cursor: text; }
.sb-card-user:not(.editing) .sb-card-body:hover { background: rgba(248, 250, 252, 0.6); }
.sb-more-btn {
    background: transparent;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 34px; height: 34px;
    padding: 0;
    cursor: pointer;
    color: #94a3b8;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
}
.sb-more-btn:hover { border-color: #cbd5e1; color: #475569; background: #f8fafc; }
.sb-more-btn .material-symbols-rounded { font-size: 18px; }
.sb-more-btn.busy { color: #10b981; border-color: #10b981; }
.sb-more-btn.busy .material-symbols-rounded { animation: sb-ai-spin 1.4s linear infinite; }
@keyframes sb-ai-spin { to { transform: rotate(360deg); } }
.sb-more-menu {
    display: none; position: absolute; top: calc(100% + 6px); right: 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12); padding: 6px; min-width: 320px; z-index: 60;
    animation: sb-menu-fade 0.15s ease-out;
}
.sb-more-menu.open { display: block; }
.sb-more-menu button {
    display: flex; gap: 0.7rem; align-items: flex-start; width: 100%; text-align: left;
    background: none; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer;
    font-family: inherit; transition: background 0.1s;
}
.sb-more-menu button:hover { background: #f8fafc; }
.sb-more-menu button .material-symbols-rounded { color: #10b981; font-size: 22px; flex-shrink: 0; margin-top: 2px; }
.sb-more-menu button strong { display: block; font-size: var(--d-fs-sm); color: #1e293b; }
.sb-more-menu button small { display: block; font-size: var(--d-fs-xs); color: #64748b; margin-top: 2px; }
.sb-add-btn { white-space: nowrap; }
.sb-add-menu {
    display: none; position: absolute; top: calc(100% + 6px); right: 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12); padding: 6px; min-width: 320px; z-index: 60;
    animation: sb-menu-fade 0.15s ease-out;
}
.sb-add-menu.open { display: block; }
@keyframes sb-menu-fade { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
.sb-add-menu button {
    display: flex; gap: 0.7rem; align-items: flex-start; width: 100%; text-align: left;
    background: none; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer;
    font-family: inherit; transition: background 0.1s;
}
.sb-add-menu button:hover { background: #f8fafc; }
.sb-add-menu button .material-symbols-rounded { color: var(--thoxan-700); font-size: 22px; flex-shrink: 0; margin-top: 2px; }
.sb-add-menu button strong { display: block; font-size: var(--d-fs-sm); color: #1e293b; }
.sb-add-menu button small { display: block; font-size: var(--d-fs-xs); color: #64748b; margin-top: 2px; }

/* ===== Inline-Edit Profil ===== */
.cs-inline-input {
    display: block; width: 100%; min-height: 1.4em;
    padding: 4px 6px; border: 1px solid transparent; border-radius: 5px;
    font-size: var(--d-fs-sm); font-family: inherit; line-height: 1.4;
    background: transparent; transition: all 0.15s;
    box-sizing: border-box;
}
.cs-inline-input:hover { background: #f8fafc; border-color: #e2e8f0; }
.cs-inline-input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
.cs-inline-input[contenteditable=""] + .cs-row-value::after,
.cs-inline-input:empty::before { content: attr(data-placeholder); color: #cbd5e1; }
textarea.cs-inline-input, div.cs-inline-input { white-space: pre-wrap; word-break: break-word; min-height: 1.4em; }
#cs-profile-status { color: #64748b; font-size: var(--d-fs-xs); font-weight: normal; }
#cs-profile-status.saved { color: #059669; }

/* In der Profil-System-Card: alle Felder untereinander als kleine Edit-Karten */
.sb-card[data-system-key="profile"] .sb-card-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 0.7rem 0.85rem;
}
.sb-card[data-system-key="profile"] #cs-profile-status { font-size: var(--d-fs-xs); color: #94a3b8; }

.cs-pf-field {
    background: #fff;
    border: 1px solid #eef2f6;
    border-radius: 9px;
    padding: 7px 10px;
    transition: all 0.15s;
}
.cs-pf-field:hover { border-color: #cbd5e1; background: #fafbfc; }
.cs-pf-field.editing {
    border-color: var(--thoxan-700);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0, 76, 155, 0.08);
}

.cs-pf-head {
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 2px;
}
.cs-pf-label {
    flex: 1;
    font-size: var(--d-fs-xs);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #94a3b8;
    font-weight: 700;
}
.cs-pf-edit-btn {
    background: none; border: 0; cursor: pointer;
    color: #cbd5e1; padding: 2px; border-radius: 5px;
    display: flex; align-items: center; opacity: 0; transition: all 0.15s;
}
.cs-pf-field:hover .cs-pf-edit-btn { opacity: 1; }
.cs-pf-edit-btn:hover { color: var(--thoxan-700); background: #f1f5f9; }
.cs-pf-edit-btn .material-symbols-rounded { font-size: 16px; }

.cs-pf-actions { display: none; gap: 2px; }
.cs-pf-field.editing .cs-pf-edit-btn { display: none; }
.cs-pf-field.editing .cs-pf-actions { display: flex; }
.cs-pf-save-btn, .cs-pf-cancel-btn {
    background: none; border: 0; cursor: pointer;
    padding: 3px; border-radius: 5px; display: flex; align-items: center;
    transition: all 0.1s;
}
.cs-pf-save-btn { color: #059669; }
.cs-pf-save-btn:hover { background: #d1fae5; }
.cs-pf-cancel-btn { color: #94a3b8; }
.cs-pf-cancel-btn:hover { background: #f1f5f9; color: var(--rose-600); }
.cs-pf-save-btn .material-symbols-rounded,
.cs-pf-cancel-btn .material-symbols-rounded { font-size: 16px; }

.cs-pf-value { min-width: 0; }
.cs-pf-input {
    width: 100%;
    font-size: var(--d-fs-sm);
    padding: 4px 6px;
    border: 1px solid transparent;
    border-radius: 5px;
    background: transparent;
    line-height: 1.5;
    color: #1e293b;
    font-family: inherit;
    box-sizing: border-box;
    white-space: pre-wrap;
    word-break: break-word;
    min-height: 1.4em;
    cursor: default;
}
input.cs-pf-input { white-space: nowrap; }
.cs-pf-input:read-only { user-select: text; }
.cs-pf-input.cs-pf-empty:not(:focus)::before { content: attr(data-placeholder); color: #cbd5e1; }
input.cs-pf-input.cs-pf-empty::placeholder { color: #cbd5e1; }
.cs-pf-field.editing .cs-pf-input {
    background: #f8fafc;
    border-color: #e2e8f0;
    cursor: text;
}
.cs-pf-field.editing .cs-pf-input:focus {
    background: #fff;
    border-color: var(--thoxan-700);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 76, 155, 0.1);
}

/* Status-Toggle */
.cs-status-toggle {
    display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;
}
.cs-status-toggle input { position: absolute; opacity: 0; pointer-events: none; }
.cs-status-slider {
    width: 38px; height: 22px; background: #cbd5e1; border-radius: 11px;
    position: relative; transition: background 0.2s;
}
.cs-status-slider::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 18px; height: 18px; background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: transform 0.2s;
}
.cs-status-toggle input:checked + .cs-status-slider { background: #10b981; }
.cs-status-toggle input:checked + .cs-status-slider::after { transform: translateX(16px); }
.cs-status-text { font-size: var(--d-fs-sm); font-weight: 600; color: #1e293b; }

/* Tags / Art-Pills */
.cs-tag-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 999px;
    background: var(--thoxan-100); color: var(--thoxan-900);
    font-size: var(--d-fs-sm); font-weight: 600;
    margin: 2px 3px 2px 0; transition: all 0.15s;
}
.cs-tag-pill.suggestion {
    background: #f1f5f9; color: #64748b; cursor: pointer; border: 1px dashed #cbd5e1;
}
.cs-tag-pill.suggestion:hover { background: #e6f0fa; color: var(--thoxan-900); border-color: var(--thoxan-700); border-style: solid; }
.cs-tag-remove {
    background: none; border: 0; cursor: pointer; padding: 0; margin-left: 2px;
    display: flex; align-items: center; color: inherit; opacity: 0.6;
}
.cs-tag-remove:hover { opacity: 1; color: var(--rose-600); }
.cs-tag-remove .material-symbols-rounded { font-size: 14px; }
.cs-tag-input {
    background: transparent; border: 1px dashed #cbd5e1; border-radius: 999px;
    padding: 3px 10px; font-size: var(--d-fs-sm); font-family: inherit;
    color: #64748b; outline: none; min-width: 110px; max-width: 180px;
    transition: all 0.15s;
}
.cs-tag-input:focus { border-color: var(--thoxan-700); border-style: solid; background: #fff; color: #1e293b; }

/* Multi-Domain Editor */
.cs-domain-row { display: flex; gap: 6px; align-items: center; padding: 3px 0; flex-wrap: wrap; }
.cs-domain-edit-row { display: grid; grid-template-columns: 110px 1fr auto; gap: 6px; padding: 4px 0; align-items: center; }
.cs-domain-edit-row input {
    padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: #f8fafc;
}
.cs-domain-edit-row input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.cs-domain-remove-btn {
    background: none; border: 0; cursor: pointer; padding: 4px; border-radius: 6px;
    color: #cbd5e1; display: flex;
}
.cs-domain-remove-btn:hover { background: var(--rose-50); color: var(--rose-600); }

/* ===== Cards — Tile-Grid mit feinen Zeilen für saubere Resize-Stufen ===== */
.sb-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: 60px;          /* Feingranulare Zeilen → kleine collapsed-Lücke */
    grid-auto-flow: dense;          /* Lücken werden mit kleineren Cards aufgefüllt */
    gap: 0.85rem;
    margin-top: 1rem;
}
/* ===== Tab-Navigation auf der Kundenseite ===== */
.cs-tabs {
    display: flex; gap: 4px; flex-wrap: wrap;
    margin: 1rem 0 1.25rem;
    border-bottom: 1px solid var(--slate-200);
}
.cs-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 14px 11px;
    background: transparent; border: 0; border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: var(--d-fs-sm); font-weight: 600;
    color: var(--slate-500); font-family: inherit;
    transition: color 0.08s, border-color 0.08s;
}
.cs-tab:hover { color: var(--slate-800); }
.cs-tab.is-active { color: var(--thoxan-700); border-bottom-color: var(--thoxan-600); }
.cs-tab .material-symbols-rounded { font-size: 18px; transform: translateY(-1px); }
.cs-tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px;
    padding: 0 7px;
    box-sizing: border-box;
    background: var(--slate-300); color: var(--slate-800);
    border-radius: 999px;
    font-size: 11px; font-weight: 700;
    line-height: 1;
    margin-left: 6px;
    text-align: center;
    vertical-align: middle;
    /* line-height:1 statt translate, damit die Zahl wirklich mittig sitzt */
}
.cs-tab.is-active .cs-tab-count { background: var(--thoxan-700); color: #fff; }
.cs-tab-count:empty { display: none; }
/* Sonstiges-Tab: amber-Akzent, damit klar ist "braucht Aufraeumen" */
.cs-tab-sonstiges { color: var(--amber-700); }
.cs-tab-sonstiges:hover { color: var(--amber-800); }
.cs-tab-sonstiges.is-active { color: var(--amber-800); border-bottom-color: var(--amber-500); }
.cs-tab-sonstiges .cs-tab-count { background: var(--amber-100); color: var(--amber-800); }
.cs-tab-sonstiges.is-active .cs-tab-count { background: var(--amber-200); color: var(--amber-900); }
/* WIP-Tabs (Personen/Dateien/Marke) visuell zurueckhaltender */
.cs-tab-wip { opacity: 0.65; }
.cs-tab-wip:hover { opacity: 1; }
.cs-tab-wip.is-active { opacity: 1; }
.cs-tab-wip::after {
    content: 'in Arbeit';
    margin-left: 6px;
    padding: 2px 6px;
    font-size: 9px; font-weight: 600; letter-spacing: 0.04em;
    background: var(--amber-100); color: var(--amber-800);
    border-radius: 4px; text-transform: uppercase;
}

.cs-tab-panel { min-height: 200px; }
/* Kanban: oben optionale Hero-Zone fuer 2- oder 3-Spalten-Karten,
   darunter 3 vertikale Stapel-Spalten. */
.cs-tab-panel.cs-kanban {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-template-areas:
        "hero hero hero"
        "col1 col2 col3";
    gap: 12px;
    align-items: start;
}
.cs-hero {
    grid-area: hero;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
    min-height: 0;
}
.cs-hero:empty { display: none; }
.cs-hero > .sb-card[data-col-span="2"], .cs-hero > .cs-card-shell[data-col-span="2"] { grid-column: span 2; }
.cs-hero > .sb-card[data-col-span="3"], .cs-hero > .cs-card-shell[data-col-span="3"] { grid-column: span 3; }
.cs-hero > .sb-card[data-col-span="1"], .cs-hero > .cs-card-shell[data-col-span="1"] { grid-column: span 1; }
.cs-col[data-col="1"] { grid-area: col1; }
.cs-col[data-col="2"] { grid-area: col2; }
.cs-col[data-col="3"] { grid-area: col3; }
.cs-col {
    min-width: 0;
    display: flex; flex-direction: column;
    gap: 12px;
    min-height: 80px;
}
.cs-col-drop-hint {
    border: 2px dashed var(--slate-300);
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.02);
}
/* In Kanban-Modus haben Cards immer volle Spaltenbreite (data-w wird ignoriert) */
.cs-kanban .sb-card { width: 100%; }
.cs-kanban .cs-card-shell { width: 100%; }
/* Hero-Karten haben Border-Akzent, Standardkarten in Spalten genauso. */

/* Rechtsklick-Kontextmenue auf Karten (nur im Layout-Edit-Modus) */
.cs-ctx-menu {
    animation: cs-ctx-fade 0.12s ease-out;
}
@keyframes cs-ctx-fade {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
.cs-ctx-section-title {
    padding: 6px 10px 4px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--slate-400);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.cs-ctx-item {
    display: flex; align-items: center; gap: 10px;
    width: 100%;
    padding: 8px 12px;
    background: transparent;
    border: 0; border-radius: 6px;
    cursor: pointer;
    text-align: left;
    font-family: inherit;
    font-size: var(--d-fs-sm);
    color: var(--slate-700);
    transition: background 0.1s, color 0.1s;
}
.cs-ctx-item:hover { background: var(--slate-50); color: var(--slate-900); }
.cs-ctx-item.is-active { background: var(--thoxan-50); color: var(--thoxan-800); font-weight: 600; }
.cs-ctx-item .material-symbols-rounded { font-size: 18px; color: var(--slate-500); }
.cs-ctx-item:hover .material-symbols-rounded,
.cs-ctx-item.is-active .material-symbols-rounded { color: var(--thoxan-700); }
.cs-ctx-item.is-danger { color: var(--rose-700); }
.cs-ctx-item.is-danger .material-symbols-rounded { color: var(--rose-500); }
.cs-ctx-item.is-danger:hover { background: var(--rose-50); color: var(--rose-800); }
.cs-ctx-sep {
    height: 1px;
    margin: 4px 6px;
    background: var(--slate-100);
}

/* Notiz-Bereich am Ende von Personen/Dateien/Marke */
.cs-tab-notes {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.cs-tab-notes-add {
    grid-column: 1 / -1;
    background: transparent;
    border: 2px dashed var(--slate-300);
    border-radius: 12px;
    padding: 14px;
    color: var(--slate-500); font-size: var(--d-fs-sm); font-weight: 600;
    cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    transition: all 0.12s;
}
.cs-tab-notes-add:hover {
    border-color: var(--thoxan-400);
    background: var(--thoxan-50);
    color: var(--thoxan-700);
}
.cs-tab-notes-add .material-symbols-rounded { font-size: 18px; color: inherit; }
/* Groessen-Stufen im 12er-Grid — data-w 1..12 entspricht direkt der Spaltenzahl.
   Default fuer Cards: 4 (Drittel). */
.cs-tab-panel .sb-card[data-w="1"]  { grid-column: span 1; }
.cs-tab-panel .sb-card[data-w="2"]  { grid-column: span 2; }
.cs-tab-panel .sb-card[data-w="3"]  { grid-column: span 3; }
.cs-tab-panel .sb-card[data-w="4"]  { grid-column: span 4; }
.cs-tab-panel .sb-card[data-w="5"]  { grid-column: span 5; }
.cs-tab-panel .sb-card[data-w="6"]  { grid-column: span 6; }
.cs-tab-panel .sb-card[data-w="7"]  { grid-column: span 7; }
.cs-tab-panel .sb-card[data-w="8"]  { grid-column: span 8; }
.cs-tab-panel .sb-card[data-w="9"]  { grid-column: span 9; }
.cs-tab-panel .sb-card[data-w="10"] { grid-column: span 10; }
.cs-tab-panel .sb-card[data-w="11"] { grid-column: span 11; }
.cs-tab-panel .sb-card[data-w="12"] { grid-column: span 12; }
.cs-tab-panel .sb-card { grid-row: auto; }
@media (max-width: 1100px) {
    .cs-tab-panel { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .cs-tab-panel .sb-card[data-w="1"]  { grid-column: span 1; }
    .cs-tab-panel .sb-card[data-w="2"]  { grid-column: span 2; }
    .cs-tab-panel .sb-card[data-w="3"]  { grid-column: span 2; }
    .cs-tab-panel .sb-card[data-w="4"]  { grid-column: span 3; }
    .cs-tab-panel .sb-card[data-w="5"]  { grid-column: span 3; }
    .cs-tab-panel .sb-card[data-w="6"]  { grid-column: span 4; }
    .cs-tab-panel .sb-card[data-w="7"]  { grid-column: span 4; }
    .cs-tab-panel .sb-card[data-w="8"]  { grid-column: span 5; }
    .cs-tab-panel .sb-card[data-w="9"]  { grid-column: span 5; }
    .cs-tab-panel .sb-card[data-w="10"] { grid-column: span 6; }
    .cs-tab-panel .sb-card[data-w="11"] { grid-column: span 6; }
    .cs-tab-panel .sb-card[data-w="12"] { grid-column: span 6; }
}
@media (max-width: 760px) {
    .cs-tab-panel { grid-template-columns: 1fr; }
    .cs-tab-panel .sb-card { grid-column: span 1 !important; }
}

/* Plan-Widget als Pseudo-Card — nutzt dieselben data-w-Stufen wie sb-card */
.cs-card-shell {
    background: #fff; border: 1px solid var(--slate-200);
    border-radius: 12px; overflow: hidden;
    border-left: 3px solid var(--thoxan-500);
    display: flex; flex-direction: column;
    grid-column: span 4; /* Default: 1/3 Breite */
}
.cs-tab-panel .cs-card-shell[data-w="1"]  { grid-column: span 1; }
.cs-tab-panel .cs-card-shell[data-w="2"]  { grid-column: span 2; }
.cs-tab-panel .cs-card-shell[data-w="3"]  { grid-column: span 3; }
.cs-tab-panel .cs-card-shell[data-w="4"]  { grid-column: span 4; }
.cs-tab-panel .cs-card-shell[data-w="5"]  { grid-column: span 5; }
.cs-tab-panel .cs-card-shell[data-w="6"]  { grid-column: span 6; }
.cs-tab-panel .cs-card-shell[data-w="7"]  { grid-column: span 7; }
.cs-tab-panel .cs-card-shell[data-w="8"]  { grid-column: span 8; }
.cs-tab-panel .cs-card-shell[data-w="9"]  { grid-column: span 9; }
.cs-tab-panel .cs-card-shell[data-w="10"] { grid-column: span 10; }
.cs-tab-panel .cs-card-shell[data-w="11"] { grid-column: span 11; }
.cs-tab-panel .cs-card-shell[data-w="12"] { grid-column: span 12; }
/* Slot-Wrapper auf display:contents — historische Notiz, wird nicht mehr genutzt */
.cs-tab-slot { display: contents; }

/* Kanban-Style Drop-Platzhalter: erscheint waehrend des Drags an der Position,
   an der die Karte landen wuerde. Gestrichelte Linie, sanft eingeblendet. */
.cs-drop-placeholder {
    border: 2px dashed var(--thoxan-500);
    border-radius: 12px;
    background: rgba(0, 76, 155, 0.06);
    min-height: 80px;
    pointer-events: none;
    animation: cs-drop-pulse 1.2s ease-in-out infinite;
}
@keyframes cs-drop-pulse {
    0%, 100% { background: rgba(0, 76, 155, 0.04); border-color: var(--thoxan-400); }
    50%      { background: rgba(0, 76, 155, 0.12); border-color: var(--thoxan-600); }
}
.cs-drop-placeholder[data-w="1"]  { grid-column: span 1; }
.cs-drop-placeholder[data-w="2"]  { grid-column: span 2; }
.cs-drop-placeholder[data-w="3"]  { grid-column: span 3; }
.cs-drop-placeholder[data-w="4"]  { grid-column: span 4; }
.cs-drop-placeholder[data-w="5"]  { grid-column: span 5; }
.cs-drop-placeholder[data-w="6"]  { grid-column: span 6; }
.cs-drop-placeholder[data-w="7"]  { grid-column: span 7; }
.cs-drop-placeholder[data-w="8"]  { grid-column: span 8; }
.cs-drop-placeholder[data-w="9"]  { grid-column: span 9; }
.cs-drop-placeholder[data-w="10"] { grid-column: span 10; }
.cs-drop-placeholder[data-w="11"] { grid-column: span 11; }
.cs-drop-placeholder[data-w="12"] { grid-column: span 12; }
/* Gezogene Karte: komplett aus dem Grid raus (display:none), damit der Placeholder
   ihren visuellen Platz uebernimmt — sonst hat das Grid waehrend des Drags ein
   Element mehr und Karten brechen in die naechste Reihe um. Drag-Image wurde
   beim dragstart-Snapshot bereits genommen, daher bleibt es sichtbar am Cursor. */
.sb-card.dragging, .cs-card-shell.dragging { display: none !important; }
.sb-card.drop-target, .cs-card-shell.drop-target { box-shadow: none !important; }

/* Dedizierte Tabs (Personen/Dateien/Marke): Block-Layout statt Grid.
   KEIN !important — csSetTab() schaltet via inline style="display:none" um;
   !important wuerde das verhindern und alle Tabs gleichzeitig anzeigen. */
.cs-tab-panel.cs-tab-panel-flow {
    display: block;
    background: transparent;
}

/* ===== Personen-Tab: Kontakt-Tabelle ===== */
.cs-people-toolbar {
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
    padding: 12px 16px; margin-bottom: 12px;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
}
.cs-people-search {
    flex: 1; min-width: 200px;
    padding: 8px 12px; border: 1px solid var(--slate-200); border-radius: 8px;
    font-size: var(--d-fs-sm); font-family: inherit;
}
.cs-people-search:focus { outline: none; border-color: var(--thoxan-500); box-shadow: 0 0 0 3px rgba(0,76,155,0.10); }
.cs-people-empty {
    background: #fff; border: 1px dashed var(--slate-300); border-radius: 12px;
    padding: 40px 20px; text-align: center; color: var(--slate-500);
}
.cs-people-empty .material-symbols-rounded { font-size: 40px; color: var(--slate-300); display: block; margin-bottom: 10px; }
.cs-people-group {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    margin-bottom: 16px; overflow: hidden;
}
.cs-people-group-head {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    background: var(--slate-50);
    border-bottom: 1px solid var(--slate-200);
    font-size: var(--d-fs-sm); font-weight: 700; color: var(--slate-800);
}
.cs-people-group-head .cs-people-source {
    margin-left: auto;
    font-size: var(--d-fs-xs); font-weight: 500; color: var(--slate-500);
}
.cs-people-table { width: 100%; border-collapse: collapse; }
.cs-people-table th {
    text-align: left;
    padding: 8px 12px;
    font-size: var(--d-fs-xs); font-weight: 600; color: var(--slate-500);
    text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 1px solid var(--slate-200);
}
.cs-people-table td {
    padding: 8px 12px;
    font-size: var(--d-fs-sm); color: var(--slate-800);
    border-bottom: 1px solid var(--slate-100);
    vertical-align: middle;
}
.cs-people-table tr:last-child td { border-bottom: 0; }
.cs-people-table tr:hover td { background: var(--slate-50); }
.cs-people-cell-edit {
    display: block;
    padding: 4px 6px; margin: -4px -6px;
    border: 1px solid transparent; border-radius: 5px;
    min-width: 30px; min-height: 22px;
    font-family: inherit; font-size: inherit; color: inherit;
    cursor: text;
}
.cs-people-cell-edit:hover { background: #fff; border-color: var(--slate-200); }
.cs-people-cell-edit:focus { outline: none; background: #fff; border-color: var(--thoxan-500); box-shadow: 0 0 0 2px rgba(0,76,155,0.10); }
/* Placeholder nur wenn leer UND nicht fokussiert. pointer-events:none, damit der
   Klick auf den Placeholder das contenteditable-Parent fokussiert (sonst schluckt
   das Pseudo den Klick und es laesst sich nicht ins Feld klicken). */
.cs-people-cell-edit[data-empty="1"]:not(:focus)::before {
    content: attr(data-placeholder);
    color: var(--slate-300);
    pointer-events: none;
}
.cs-people-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--thoxan-200), var(--thoxan-100));
    color: var(--thoxan-800);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: var(--d-fs-xs);
    text-transform: uppercase;
    flex-shrink: 0;
}
.cs-people-mail-link, .cs-people-tel-link {
    color: var(--thoxan-700); text-decoration: none;
}
.cs-people-mail-link:hover, .cs-people-tel-link:hover { text-decoration: underline; }
.cs-people-del-btn {
    background: transparent; border: 0; cursor: pointer;
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    color: var(--slate-400); opacity: 0;
    transition: all 0.12s;
}
.cs-people-table tr:hover .cs-people-del-btn { opacity: 1; }
.cs-people-del-btn:hover { color: var(--rose-700); background: var(--rose-50); }
.cs-people-add-row {
    background: var(--slate-50);
    text-align: center;
}
.cs-people-add-btn {
    background: transparent; border: 0; cursor: pointer;
    padding: 8px 12px;
    color: var(--thoxan-700); font-size: var(--d-fs-sm); font-weight: 600;
    display: inline-flex; align-items: center; gap: 6px;
    font-family: inherit;
}
.cs-people-add-btn:hover { color: var(--thoxan-900); }
.cs-people-add-btn .material-symbols-rounded { font-size: 18px; }

/* ===== Personen-Tiles ===== */
.cs-people-tile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    padding: 14px 16px 16px;
}
.cs-person-tile {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 10px;
    padding: 14px;
    position: relative;
    transition: border-color 0.12s, box-shadow 0.12s;
}
.cs-person-tile:hover { border-color: var(--slate-300); box-shadow: 0 2px 8px rgba(15,23,42,0.04); }
.cs-person-tile-del {
    position: absolute; top: 6px; right: 6px;
    background: transparent; border: 0; cursor: pointer;
    width: 24px; height: 24px;
    border-radius: 6px;
    color: var(--slate-400); opacity: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.12s;
}
.cs-person-tile:hover .cs-person-tile-del { opacity: 1; }
.cs-person-tile-del:hover { background: var(--rose-50); color: var(--rose-600); }
.cs-person-tile-del .material-symbols-rounded { font-size: 16px; color: inherit; }
.cs-person-tile-head {
    display: flex; gap: 10px; align-items: flex-start;
    padding-right: 24px; margin-bottom: 12px;
}
.cs-person-tile-avatar {
    width: 40px; height: 40px;
    flex-shrink: 0;
    font-size: var(--d-fs-sm);
}
.cs-person-tile-id { min-width: 0; flex: 1; }
.cs-person-tile-name {
    font-weight: 700;
    font-size: var(--d-fs-base);
    color: var(--slate-900);
    margin-bottom: 2px;
}
.cs-person-tile-role {
    font-size: var(--d-fs-xs);
    color: var(--slate-500);
}
.cs-person-tile-fields {
    display: flex; flex-direction: column;
    gap: 6px;
}
.cs-person-tile-field {
    display: flex; align-items: center; gap: 8px;
    font-size: var(--d-fs-sm);
    color: var(--slate-700);
    min-width: 0;
}
.cs-person-tile-icon {
    flex-shrink: 0;
    font-size: 14px !important;
    color: var(--slate-400);
}
.cs-person-tile-field .cs-people-cell-edit {
    flex: 1; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cs-person-tile-note-field .cs-people-cell-edit {
    white-space: normal;
    font-style: italic;
    color: var(--slate-600);
}
.cs-person-tile-link {
    color: var(--slate-400);
    text-decoration: none;
    flex-shrink: 0;
    display: inline-flex; align-items: center;
    transition: color 0.12s;
}
.cs-person-tile-link:hover { color: var(--thoxan-700); }
.cs-person-tile-link .material-symbols-rounded { font-size: 14px; color: inherit; }
.cs-person-tile-add {
    border: 2px dashed var(--slate-300);
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    font-size: var(--d-fs-sm); font-weight: 600;
    color: var(--slate-500);
    padding: 14px;
    min-height: 100px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px;
    transition: all 0.12s;
}
.cs-person-tile-add:hover {
    border-color: var(--thoxan-400);
    background: var(--thoxan-50);
    color: var(--thoxan-700);
}
.cs-person-tile-add .material-symbols-rounded { font-size: 24px; color: inherit; }

/* ===== Dateien-Tab: Datei-Browser ===== */
.cs-files-toolbar {
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
    padding: 12px 16px; margin-bottom: 12px;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
}
.cs-files-search { flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid var(--slate-200); border-radius: 8px; font-size: var(--d-fs-sm); font-family: inherit; }
.cs-files-search:focus { outline: none; border-color: var(--thoxan-500); box-shadow: 0 0 0 3px rgba(0,76,155,0.10); }
.cs-files-filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.cs-files-chip {
    padding: 6px 12px; border: 1px solid var(--slate-200); border-radius: 999px;
    background: #fff; color: var(--slate-700);
    font-size: var(--d-fs-xs); font-weight: 600; cursor: pointer;
    font-family: inherit;
    transition: all 0.12s;
}
.cs-files-chip:hover { border-color: var(--thoxan-400); color: var(--thoxan-700); }
.cs-files-chip.active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-700); }
.cs-files-grid {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    overflow: hidden;
}
.cs-files-row {
    display: grid;
    grid-template-columns: 36px 1fr 100px 100px 140px 80px;
    gap: 12px; align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--slate-100);
    font-size: var(--d-fs-sm);
}
.cs-files-row:last-child { border-bottom: 0; }
.cs-files-row.is-head {
    background: var(--slate-50);
    font-weight: 600; color: var(--slate-500);
    text-transform: uppercase; letter-spacing: 0.04em;
    font-size: var(--d-fs-xs);
}
.cs-files-row:not(.is-head):hover { background: var(--slate-50); }
.cs-files-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: var(--slate-100); color: var(--slate-600);
    display: flex; align-items: center; justify-content: center;
}
.cs-files-icon .material-symbols-rounded { font-size: 20px; color: inherit; }
.cs-files-icon-pdf { background: #fee2e2; color: #b91c1c; }
.cs-files-icon-doc { background: #dbeafe; color: #1d4ed8; }
.cs-files-icon-img { background: #fce7f3; color: #be185d; }
.cs-files-icon-asana { background: #fff7ed; color: #c2410c; }
.cs-files-name { font-weight: 600; color: var(--slate-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cs-files-name-source { display: block; font-size: var(--d-fs-xs); font-weight: 400; color: var(--slate-500); margin-top: 2px; }
.cs-files-type { color: var(--slate-600); font-size: var(--d-fs-xs); text-transform: uppercase; }
.cs-files-size, .cs-files-date { color: var(--slate-600); font-size: var(--d-fs-xs); }
.cs-files-actions { display: flex; gap: 4px; }
.cs-files-action {
    background: transparent; border: 0; cursor: pointer;
    width: 28px; height: 28px; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--slate-500); text-decoration: none;
    transition: all 0.1s;
}
.cs-files-action:hover { background: var(--slate-100); color: var(--thoxan-700); }
.cs-files-action .material-symbols-rounded { font-size: 18px; color: inherit; }
.cs-files-empty {
    background: #fff; border: 1px dashed var(--slate-300); border-radius: 12px;
    padding: 40px 20px; text-align: center; color: var(--slate-500);
}
.cs-files-empty .material-symbols-rounded { font-size: 40px; color: var(--slate-300); display: block; margin-bottom: 10px; }

/* ===== Marke-Tab: CI-Block ===== */
/* Marke: gleiches 3-Spalten-Kanban wie Übersicht/Inhalte fuer Konsistenz */
.cs-brand-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
}
.cs-brand-section {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    padding: 16px;
}
.cs-brand-section-logo   { grid-column: 1 / 2; grid-row: 1; }
.cs-brand-section-colors { grid-column: 2 / 3; grid-row: 1 / 3; }
.cs-brand-section-fonts  { grid-column: 3 / 4; grid-row: 1; }
.cs-brand-section-notes  { grid-column: 1 / 2; grid-row: 2; }
@media (max-width: 900px) {
    .cs-brand-grid { grid-template-columns: 1fr; }
    .cs-brand-section-logo,
    .cs-brand-section-colors,
    .cs-brand-section-fonts,
    .cs-brand-section-notes { grid-column: 1; grid-row: auto; }
}
.cs-brand-section h3 {
    margin: 0 0 12px;
    font-size: var(--d-fs-xs); font-weight: 700; color: var(--slate-500);
    text-transform: uppercase; letter-spacing: 0.06em;
    display: flex; align-items: center; gap: 8px;
}
.cs-brand-section h3 .material-symbols-rounded { font-size: 16px; color: var(--slate-400); }
.cs-brand-logo-wrap {
    background: var(--slate-50); border-radius: 10px;
    padding: 24px; min-height: 140px;
    display: flex; align-items: center; justify-content: center;
}
.cs-brand-logo-wrap img { max-width: 100%; max-height: 120px; object-fit: contain; }
.cs-brand-logo-wrap .cs-brand-no-logo { color: var(--slate-400); font-size: var(--d-fs-sm); }
.cs-brand-color-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
.cs-brand-color {
    border: 1px solid var(--slate-200); border-radius: 10px;
    overflow: hidden;
    transition: box-shadow 0.1s;
}
.cs-brand-color:hover { box-shadow: 0 4px 12px rgba(15,23,42,0.08); }
.cs-brand-color-swatch { position: relative; cursor: pointer; }
.cs-brand-color-swatch { height: 60px; }
.cs-brand-color-meta { padding: 8px 10px; }
.cs-brand-color-name { font-weight: 700; font-size: var(--d-fs-sm); color: var(--slate-800); }
.cs-brand-color-value { font-size: var(--d-fs-xs); color: var(--slate-500); font-family: monospace; margin-top: 2px; }
.cs-brand-font-list { display: flex; flex-direction: column; gap: 10px; }
.cs-brand-font {
    padding: 12px; border: 1px solid var(--slate-200); border-radius: 8px;
    background: var(--slate-50);
}
.cs-brand-font-name { font-weight: 700; font-size: var(--d-fs-sm); color: var(--slate-800); }
.cs-brand-font-note { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 4px; }
.cs-brand-notes-text {
    font-size: var(--d-fs-sm); color: var(--slate-700); line-height: 1.55;
    white-space: pre-wrap;
}
.cs-brand-empty {
    grid-column: 1 / -1;
    background: #fff; border: 1px dashed var(--slate-300); border-radius: 12px;
    padding: 40px 20px; text-align: center; color: var(--slate-500);
}
.cs-brand-empty .material-symbols-rounded { font-size: 40px; color: var(--slate-300); display: block; margin-bottom: 10px; }
.cs-brand-empty-action {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    background: var(--thoxan-700); color: #fff !important;
    border-radius: 8px;
    font-size: var(--d-fs-sm); font-weight: 600; text-decoration: none;
    margin-top: 12px;
}
.cs-brand-empty-action:hover { background: var(--thoxan-800); }

/* Platzhalter fuer noch nicht implementierte Tabs (Personen/Dateien/Marke) */
.cs-tab-placeholder {
    grid-column: 1 / -1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center;
    padding: 64px 32px;
    background: #fff;
    border: 1px dashed var(--slate-300);
    border-radius: 14px;
    color: var(--slate-600);
}
.cs-tab-placeholder .material-symbols-rounded {
    font-size: 48px;
    color: var(--slate-400);
    margin-bottom: 16px;
}
.cs-tab-placeholder h3 {
    margin: 0 0 8px;
    font-size: var(--d-fs-xl);
    color: var(--slate-800);
    font-weight: 700;
}
.cs-tab-placeholder p {
    margin: 0 0 20px;
    max-width: 520px;
    font-size: var(--d-fs-sm);
    line-height: 1.55;
}
.cs-tab-placeholder strong { color: var(--slate-800); }
.cs-tab-placeholder-link {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    background: var(--thoxan-700); color: #fff !important;
    border-radius: 8px;
    font-size: var(--d-fs-sm); font-weight: 600;
    text-decoration: none;
    transition: background 0.12s;
}
.cs-tab-placeholder-link:hover { background: var(--thoxan-800); }
/* Plan-Widget-Header benutzt jetzt die gleichen Klassen wie normale Karten
   (sb-card-head + sb-card-icon + sb-card-title). Keine zusaetzlichen Overrides. */
.cs-shell-actions { display: flex; gap: 2px; align-items: center; }
.cs-shell-actions .sb-card-action {
    background: transparent; border: 0; cursor: pointer;
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    color: var(--slate-500); transition: background 0.1s, color 0.1s;
    padding: 0;
}
.cs-shell-actions .sb-card-action:hover { background: var(--slate-100); color: var(--thoxan-700); }
.cs-shell-actions .sb-card-action .material-symbols-rounded { font-size: 16px !important; color: inherit !important; }
.cs-card-shell-readonly-badge {
    font-size: 9px; font-weight: 600; letter-spacing: 0.05em;
    background: var(--slate-200); color: var(--slate-600);
    padding: 2px 6px; border-radius: 4px; text-transform: uppercase;
}
.cs-card-shell-open {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px;
    background: var(--thoxan-700); color: #fff;
    border-radius: 6px;
    font-size: var(--d-fs-xs); font-weight: 600; text-decoration: none;
    text-transform: none; letter-spacing: 0;
    transition: background 0.12s;
}
.cs-card-shell-open:hover { background: var(--thoxan-800); }
.cs-card-shell-open .material-symbols-rounded { font-size: 14px !important; color: #fff !important; }
.cs-card-shell-body { padding: 0; flex: 1; overflow: auto; }
.cs-card-shell-body .pp-widget { border: 0; border-radius: 0; padding: 14px 16px 16px; }
/* Read-only: Widget-Inputs deaktivieren, damit klar ist, dass Bearbeitung im PP passiert */
.cs-card-shell.is-readonly .cs-card-shell-body { pointer-events: none; }
.cs-card-shell.is-readonly .cs-card-shell-body * { user-select: text !important; }

/* === Einheitlicher Card-Look: kein Gradient-Icon mehr, linker Akzent je Typ === */
/* Icon-Wrapper neutralisieren — Slate, kein Gradient */
.sb-card-icon {
    background: var(--slate-100) !important;
    color: var(--slate-600) !important;
}
.sb-card-icon .material-symbols-rounded { color: var(--slate-600) !important; }
/* Linker 3-px-Akzent-Streifen je Card-Typ */
.sb-card { border-left: 3px solid var(--slate-300); }
.sb-card[data-card-type="contacts"]   { border-left-color: #0891b2; }
.sb-card[data-card-type="richtext"]   { border-left-color: var(--thoxan-500); }
.sb-card[data-card-type="documents"]  { border-left-color: var(--amber-500); }
.sb-card[data-card-type="images"]     { border-left-color: #ec4899; }
.sb-card[data-card-type="brand"]      { border-left-color: #14b8a6; }
.sb-card[data-card-type="links"]      { border-left-color: #6366f1; }
.sb-card[data-system-key="profile"]   { border-left-color: var(--thoxan-700); }
.sb-card[data-system-key="asana"]     { border-left-color: #f97316; }
.sb-card[data-system-key="knowledge"] { border-left-color: var(--emerald-600); }
.sb-card[data-system-key="website"]   { border-left-color: #0891b2; }
.sb-card[data-system-key="site_monitor"] { border-left-color: #6366f1; }
.sb-card[data-system-key="markenprofil"] { border-left-color: #ec4899; }
.sb-card[data-system-key="regeln"] { border-left-color: #8b5cf6; }
.reg-cat { margin-bottom: 8px; }
.reg-cat-head {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 8px; background: #fafbfc; border-radius: 6px;
    font-size: var(--d-fs-sm); color: var(--slate-700);
    border-left: 3px solid var(--cat-color, #9ca3af);
}
.reg-cat-count { color: var(--slate-500); font-size: var(--d-fs-xs); font-family: ui-monospace, monospace; margin-left: auto; }
.reg-bulk-btn {
    padding: 1px 7px; font-size: 10px; border: 1px solid var(--slate-200);
    background: #fff; border-radius: 4px; cursor: pointer; color: var(--slate-600);
}
.reg-bulk-btn:hover { background: var(--slate-50); border-color: var(--slate-300); }
.reg-cat-body { padding: 4px 0 4px 12px; }
.reg-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 8px 8px; border-radius: 6px;
    transition: background 0.12s; font-size: var(--d-fs-sm);
}
.reg-row:hover { background: var(--slate-50); }
.reg-row.is-disabled .reg-row-body { opacity: 0.45; }
.reg-row.is-disabled .reg-row-name { text-decoration: line-through; }
.reg-row-body { flex: 1; min-width: 0; }
.reg-row-name { font-weight: 500; color: var(--slate-800); }
.reg-row-content {
    font-size: var(--d-fs-xs); color: var(--slate-600);
    margin-top: 3px; line-height: 1.45; white-space: pre-wrap; word-break: break-word;
}
.reg-row-desc { font-size: 11px; color: var(--slate-400); margin-top: 2px; line-height: 1.3; font-style: italic; }
.reg-row-type {
    font-size: 10px; padding: 1px 7px; background: var(--slate-100);
    border-radius: 9px; margin-left: 4px;
}
.reg-row-edit {
    border: none; background: transparent; cursor: pointer; padding: 2px 4px;
    color: var(--slate-400); opacity: 0; transition: opacity 0.15s;
    align-self: flex-start; margin-top: 1px;
}
.reg-row:hover .reg-row-edit { opacity: 1; }
.reg-row-edit:hover { color: var(--thoxan-700); }
.reg-row-edit .material-symbols-rounded { font-size: 16px; }

/* Toggle-Switch */
.reg-toggle { position: relative; display: inline-block; width: 30px; height: 17px; flex-shrink: 0; margin-top: 1px; cursor: pointer; }
.reg-toggle input { opacity: 0; width: 0; height: 0; }
.reg-toggle-slider {
    position: absolute; inset: 0; background: var(--slate-300);
    border-radius: 17px; transition: 0.18s; cursor: pointer;
}
.reg-toggle-slider:before {
    content: ''; position: absolute; height: 13px; width: 13px;
    left: 2px; bottom: 2px; background: white; border-radius: 50%;
    transition: 0.18s; box-shadow: 0 1px 2px rgba(0,0,0,0.15);
}
.reg-toggle input:checked + .reg-toggle-slider { background: #10b981; }
.reg-toggle input:checked + .reg-toggle-slider:before { transform: translateX(13px); }
.reg-toggle input:focus + .reg-toggle-slider { box-shadow: 0 0 0 2px rgba(16,185,129,0.2); }
.cs-rg-input {
    width: 100%; padding: 7px 10px; border: 1px solid var(--slate-300);
    border-radius: 6px; font-size: var(--d-fs-sm); color: var(--slate-800);
    background: #fff; font-family: inherit;
}
.cs-rg-input:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px var(--thoxan-100); }
/* Website-Tab System-Cards: natuerliche Hoehe, kein Scroll, Body waechst mit Inhalt.
   Ueberschreibt das grid-row span aus data-h. */
.sb-card[data-system-key="site_monitor"],
.sb-card[data-system-key="website"] { grid-row: auto !important; }
.sb-card[data-system-key="site_monitor"] .sb-card-body,
.sb-card[data-system-key="website"] .sb-card-body { overflow: visible; }
@media (max-width: 1100px) { .sb-cards { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 760px)  { .sb-cards { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .sb-cards { grid-template-columns: 1fr; } }

/* Flachere, ruhigere Section-Optik — kein Gradient-Header, dezente Border,
   konsistent ueber alle Kunden. */
.sb-card {
    background: #fff; border: 1px solid var(--slate-200); border-radius: 12px;
    padding: 0;
    overflow: hidden;
    display: flex; flex-direction: column;
    grid-column: span 2;
    grid-row: span 4;
    min-width: 0;
}
.sb-card[data-w="2"] { grid-column: span 2; }
.sb-card[data-w="3"] { grid-column: span 3; }
.sb-card[data-h="1"] { grid-row: span 4; }   /* ~252px */
.sb-card[data-h="2"] { grid-row: span 7; }   /* ~444px */
.sb-card[data-h="3"] { grid-row: span 10; }  /* ~636px */
.sb-card.collapsed { grid-row: span 1 !important; }  /* ~60px = nur Header */

@media (max-width: 1100px) {
    .sb-card[data-w="3"] { --w: 3; }
}
@media (max-width: 760px) {
    .sb-card[data-w="3"] { --w: 2; }
}
@media (max-width: 480px) {
    .sb-card[data-w="2"], .sb-card[data-w="3"] { --w: 1; }
    .sb-card[data-h="2"], .sb-card[data-h="3"] { --h: 1; }
}
.sb-card:hover { border-color: var(--slate-300); }
body.cs-layout-edit .sb-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.04); }
.sb-card.dragging { opacity: 0.4; }
.sb-card.drop-target { border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0,76,155,0.12); }
.sb-card.search-hit { animation: sb-card-pulse 0.6s ease-out; }
@keyframes sb-card-pulse {
    0% { box-shadow: 0 0 0 0 rgba(0,76,155,0.4); }
    100% { box-shadow: 0 0 0 6px rgba(0,76,155,0); }
}
/* Deep-Link-Highlight: nach #card-<id>-Scroll kurz hervorgehoben */
.sb-card.sb-card-highlight {
    box-shadow: 0 0 0 3px var(--thoxan-500), 0 8px 24px rgba(0,76,155,0.15);
    animation: sb-card-highlight-flash 2s ease-out;
}
@keyframes sb-card-highlight-flash {
    0%   { box-shadow: 0 0 0 0   rgba(0,76,155,0.6); }
    20%  { box-shadow: 0 0 0 8px rgba(0,76,155,0.30); }
    100% { box-shadow: 0 0 0 0   rgba(0,76,155,0); }
}

.sb-card-head {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.85rem 1rem; border-bottom: 1px solid transparent;
    background: var(--slate-50);
    cursor: default;
}
.sb-card.collapsed .sb-card-head { border-bottom-color: transparent; }
.sb-card:not(.collapsed) .sb-card-head { border-bottom-color: var(--slate-200); }
body.cs-layout-edit .sb-card-user .sb-card-head { cursor: grab; }
/* Plan-Widget-Shell: auch im Layout-Edit-Modus per Drag verschiebbar */
body.cs-layout-edit .cs-card-shell { cursor: grab; }
body.cs-layout-edit .cs-card-shell.dragging { opacity: 0.4; }
body.cs-layout-edit .cs-card-shell.drop-target { box-shadow: 0 0 0 3px rgba(0,76,155,0.3); }
/* Header-Inhalte (Buttons/Link) NICHT als Drag-Trigger */
.cs-card-shell a, .cs-card-shell button, .cs-card-shell .sb-card-action-wrap { cursor: pointer; }
.sb-card-icon {
    width: 28px; height: 28px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-600)); color: #fff; flex-shrink: 0;
}
.sb-card-icon .material-symbols-rounded { font-size: 16px; }
.sb-card-icon.type-richtext { background: linear-gradient(135deg, #7c3aed, #a78bfa); }
.sb-card-icon.type-documents { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.sb-card-icon.type-images { background: linear-gradient(135deg, #ec4899, #f472b6); }
.sb-card-icon.type-brand { background: linear-gradient(135deg, #14b8a6, #2dd4bf); }
.sb-card-icon.type-contacts { background: linear-gradient(135deg, #0891b2, #22d3ee); }
.sb-card-title {
    flex: 1; min-width: 0; font-size: var(--d-fs-base); font-weight: 600; color: #1e293b;
    border: 1px solid transparent; border-radius: 6px; padding: 4px 6px;
    transition: all 0.15s; cursor: text;
}
.sb-card-title:hover { background: #f8fafc; border-color: #e2e8f0; }
.sb-card-title:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0,76,155,0.1); cursor: text; }
.sb-card-actions { display: flex; gap: 2px; }
.sb-card-action {
    background: none; border: none; cursor: pointer; padding: 6px; border-radius: 6px;
    color: #94a3b8; transition: all 0.1s; display: flex;
}
.sb-card-action:hover { background: #f1f5f9; color: #1e293b; }
.sb-card-action.danger:hover { background: var(--rose-50); color: var(--rose-600); }
.sb-card-action .material-symbols-rounded { font-size: 18px; }
/* Kundenportal-Sichtbarkeit: grünes Auge = sichtbar, blasses Auge = aus */
.sb-vis-btn:not(.is-on) { color: var(--slate-300); }
.sb-vis-btn:not(.is-on):hover { background: var(--slate-100); color: var(--slate-600); }
.sb-vis-btn.is-on { color: var(--emerald-600); }
.sb-vis-btn.is-on:hover { background: var(--emerald-50); color: var(--emerald-700); }
.sb-card-toggle .material-symbols-rounded { transition: transform 0.2s; }
.sb-card.collapsed .sb-card-toggle .material-symbols-rounded { transform: rotate(-90deg); }

.sb-card-body {
    padding: 0.9rem 1rem 1rem;
    overflow-y: auto;
    flex: 1; min-height: 0;
    transition: opacity 0.15s ease, max-height 0.25s ease, padding 0.25s ease;
    position: relative;
}
/* Eingeklappt: nur Header sichtbar, Body komplett ausgeblendet */
.sb-card.collapsed .sb-card-body {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    overflow: hidden;
    pointer-events: none;
    opacity: 0;
}
.sb-card-body::-webkit-scrollbar { width: 6px; }
.sb-card-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.sb-card-body::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Resize-Popover — 4×3-Grid fuer 12 Spaltenstufen. Kompakt genug, damit es auch
   in schmale Cards passt und nicht in die Sidebar ueberlaeuft. */
.sb-size-pop {
    position: absolute; top: 100%; right: 0; margin-top: 4px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    display: none; z-index: 30;
    width: 168px;
    animation: sb-menu-fade 0.15s ease-out;
}
.sb-size-pop.open { display: block; }
.sb-size-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
}
.sb-size-cell {
    background: #f1f5f9; border: 2px solid transparent; border-radius: 6px;
    cursor: pointer; transition: all 0.1s;
    height: 32px;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: var(--d-fs-xs); color: #64748b; font-weight: 600;
    font-family: inherit;
}
.sb-size-cell:hover { background: #e6f0fa; color: var(--thoxan-700); }
.sb-size-cell.active { background: var(--thoxan-700); color: #fff; border-color: var(--thoxan-900); }

/* Move-Popover (Verschieben zwischen Tabs) */
.sb-move-pop {
    position: absolute; top: 100%; right: 0; margin-top: 4px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    display: none; z-index: 30;
    width: 200px;
    animation: sb-menu-fade 0.15s ease-out;
}
.sb-move-pop.open { display: block; }
.sb-move-item {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: 8px 10px;
    background: transparent; border: 0; border-radius: 6px;
    cursor: pointer; transition: background 0.1s, color 0.1s;
    font-family: inherit; font-size: var(--d-fs-sm); color: var(--slate-700);
    text-align: left;
}
.sb-move-item:hover { background: var(--slate-50); color: var(--slate-900); }
.sb-move-item .material-symbols-rounded { font-size: 18px; color: var(--slate-500); }
.sb-move-item:hover .material-symbols-rounded { color: var(--thoxan-700); }
.sb-move-item.active { background: var(--thoxan-50); color: var(--thoxan-800); font-weight: 600; }
.sb-move-item.active .material-symbols-rounded { color: var(--thoxan-700); }
/* Move-Button im View-Modus auch sichtbar (im Gegensatz zu Resize) — User soll
   ohne Edit-Mode in andere Tabs verschieben koennen. */
.sb-card-user .sb-move-btn { display: block !important; }
.sb-card-action-wrap { position: relative; }

/* ===== Card-Typen ===== */
/* Links: zwei Ebenen — Ansicht (klickbar) + Bearbeiten (Felder), wie Konten & IDs */
.sb-link-row { padding: 7px 0; border-bottom: 1px solid #f1f5f9; }
.sb-link-row:last-of-type { border-bottom: none; }
.sb-link-view { display: flex; flex-direction: column; gap: 2px; }
.sb-link-v-title { font-weight: 600; font-size: var(--d-fs-sm); color: #0f172a; }
.sb-link-v-link {
    display: block; max-width: 100%; color: var(--thoxan-700); text-decoration: none;
    font-size: var(--d-fs-sm); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sb-link-v-link:hover { text-decoration: underline; }
.sb-link-edit { display: grid; grid-template-columns: 1fr auto; gap: 4px; align-items: start; }
.sb-link-edit .sb-link-inputs { display: grid; gap: 3px; min-width: 0; }
.sb-link-edit input {
    width: 100%; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: #fff;
}
.sb-link-edit input:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-link-edit .sb-link-title { font-weight: 600; }
.sb-link-edit .sb-link-url { color: var(--thoxan-700); }
.sb-card-user:not(.editing) .sb-link-edit { display: none; }
.sb-card-user.editing .sb-link-view { display: none; }
.sb-link-remove { color: #cbd5e1; background: none; border: none; cursor: pointer; padding: 4px; border-radius: 6px; }
.sb-link-remove:hover { background: var(--rose-50); color: var(--rose-600); }
.sb-link-add {
    display: flex; align-items: center; gap: 4px; background: none; border: 1px dashed #cbd5e1;
    border-radius: 8px; padding: 6px 10px; color: #64748b; cursor: pointer; font-size: var(--d-fs-sm);
    width: 100%; justify-content: center; margin-top: 4px;
}
.sb-link-add:hover { border-color: var(--thoxan-700); color: var(--thoxan-700); background: rgba(0,76,155,0.04); }

/* Konten & IDs (accounts): zwei Ebenen — Ansicht (klickbar) + Bearbeiten (Felder).
   Umschaltung ueber die .editing-Klasse der Card (Stift-Button). */
.sb-account-row { padding: 7px 0; border-bottom: 1px solid #f1f5f9; }
.sb-account-row:last-of-type { border-bottom: none; }

/* --- Ansicht --- */
.sb-account-view { display: flex; flex-direction: column; gap: 2px; }
.sb-account-v-label { font-weight: 600; font-size: var(--d-fs-sm); color: #0f172a; }
.sb-account-v-id { display: flex; align-items: center; gap: 6px; }
.sb-account-v-idval {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    letter-spacing: 0.02em; font-size: var(--d-fs-sm); color: #334155;
}
.sb-account-copy {
    flex: 0 0 auto; color: #94a3b8; background: none; border: none; cursor: pointer;
    padding: 2px; border-radius: 5px; display: flex; transition: all 0.1s;
}
.sb-account-copy:hover { background: rgba(0,76,155,0.08); color: var(--thoxan-700); }
.sb-account-v-link {
    display: block; max-width: 100%; color: var(--thoxan-700); text-decoration: none;
    font-size: var(--d-fs-sm); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sb-account-v-link:hover { text-decoration: underline; }

/* --- Bearbeiten --- */
.sb-account-edit { display: grid; grid-template-columns: 1fr auto; gap: 4px; align-items: start; }
.sb-account-edit .sb-account-inputs { display: grid; gap: 3px; min-width: 0; }
.sb-account-edit input {
    width: 100%; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: #fff;
}
.sb-account-edit input:focus { outline: none; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-account-edit .sb-account-label { font-weight: 600; }
.sb-account-edit .sb-account-id { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.sb-account-edit .sb-account-url { color: var(--thoxan-700); }

/* Umschaltung Ansicht <-> Bearbeiten */
.sb-card-user:not(.editing) .sb-account-edit { display: none; }
.sb-card-user.editing .sb-account-view { display: none; }
.sb-card-user.editing .sb-card-empty-hint { display: none; }

.sb-richtext-toolbar {
    display: flex; gap: 2px; padding: 4px; margin-bottom: 6px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; flex-wrap: wrap;
}
.sb-richtext-toolbar button {
    background: none; border: none; cursor: pointer; padding: 5px 8px; border-radius: 6px;
    color: #475569; font-size: var(--d-fs-sm); font-family: inherit;
    display: flex; align-items: center; gap: 3px;
}
.sb-richtext-toolbar button:hover { background: #fff; color: var(--thoxan-700); }
.sb-richtext-toolbar button.active { background: #fff; color: var(--thoxan-700); box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.sb-richtext-toolbar .material-symbols-rounded { font-size: 16px; }
.sb-richtext-toolbar .sep { width: 1px; background: #e2e8f0; margin: 4px 2px; }
.sb-richtext-editor {
    min-height: 100px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: var(--d-fs-sm); line-height: 1.6; outline: none; background: #fff;
}
.sb-richtext-editor:focus { border-color: var(--thoxan-700); box-shadow: 0 0 0 3px rgba(0,76,155,0.08); }
.sb-richtext-editor h1 { font-size: var(--d-fs-lg); margin: 0.5em 0 0.3em; }
.sb-richtext-editor h2 { font-size: var(--d-fs-base); margin: 0.5em 0 0.3em; }
.sb-richtext-editor h3 { font-size: var(--d-fs-sm); margin: 0.4em 0 0.3em; }
.sb-richtext-editor ul, .sb-richtext-editor ol { padding-left: 1.5em; margin: 0.3em 0; }
.sb-richtext-editor a { color: var(--thoxan-700); text-decoration: underline; cursor: pointer; }
.sb-richtext-editor a:hover { background: rgba(0,76,155,0.06); border-radius: 3px; }
.sb-richtext-editor:empty::before { content: attr(data-placeholder); color: #cbd5e1; }
/* Empty-Hint fuer Cards ohne Inhalt — sofort sichtbar, nicht erst nach Klick. */
.sb-card-empty-hint {
    padding: 12px 0 8px;
    font-size: var(--d-fs-sm);
    color: var(--slate-400);
    font-style: italic;
}

/* Wissens-Liste: Mehr-anzeigen-Toggle nach 5 Eintraegen */
.cs-kb-toggle {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%;
    padding: 10px 12px;
    background: var(--slate-50);
    border: 1px dashed var(--slate-300);
    border-radius: 8px;
    color: var(--slate-600); font-size: var(--d-fs-sm); font-weight: 600;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
    font-family: inherit;
}
.cs-kb-toggle:hover {
    background: var(--thoxan-50);
    color: var(--thoxan-700);
    border-color: var(--thoxan-300);
}
.cs-kb-toggle .material-symbols-rounded { font-size: 18px; }

.sb-drop-zone {
    border: 2px dashed #cbd5e1; border-radius: 10px; padding: 1.4rem 1rem; text-align: center;
    color: #64748b; font-size: var(--d-fs-sm); transition: all 0.15s; cursor: pointer;
    background: #fafbfc;
}
.sb-drop-zone:hover { border-color: var(--thoxan-700); background: rgba(0,76,155,0.03); color: var(--thoxan-700); }
.sb-drop-zone.dragover { border-color: var(--thoxan-700); background: rgba(0,76,155,0.08); color: var(--thoxan-700); transform: scale(1.01); }
.sb-drop-zone .material-symbols-rounded { font-size: 28px; margin-bottom: 4px; display: block; }
.sb-drop-zone small { display: block; font-size: var(--d-fs-xs); margin-top: 4px; color: #94a3b8; }

.sb-file-list { margin-top: 0.7rem; display: flex; flex-direction: column; gap: 4px; }
.sb-file-item {
    display: flex; gap: 8px; align-items: center; padding: 7px 10px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: var(--d-fs-sm);
}
.sb-file-item .material-symbols-rounded { color: var(--thoxan-700); font-size: 18px; }
.sb-file-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-file-name a { color: inherit; text-decoration: none; }
.sb-file-name a:hover { color: var(--thoxan-700); }
.sb-file-size { font-size: var(--d-fs-xs); color: #94a3b8; white-space: nowrap; }
.sb-file-remove {
    background: none; border: none; cursor: pointer; color: #cbd5e1; padding: 4px;
    border-radius: 6px; display: flex;
}
.sb-file-remove:hover { background: var(--rose-50); color: var(--rose-600); }

.sb-image-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px;
    margin-top: 0.7rem;
}
.sb-image-tile {
    position: relative; aspect-ratio: 1 / 1; border-radius: 8px; overflow: hidden;
    background: #f1f5f9; border: 1px solid #e2e8f0;
}
.sb-image-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sb-image-tile .sb-image-remove {
    position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.55); color: #fff;
    border: none; border-radius: 6px; padding: 4px; cursor: pointer; opacity: 0; transition: opacity 0.15s;
    display: flex;
}
.sb-image-tile:hover .sb-image-remove { opacity: 1; }
.sb-image-tile .sb-image-remove .material-symbols-rounded { font-size: 14px; }

.sb-brand-section { margin-bottom: 0.8rem; }
.sb-brand-section h4 { margin: 0 0 0.3rem; font-size: var(--d-fs-sm); text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; }
.sb-brand-row {
    display: grid; grid-template-columns: 32px 1fr 1fr auto; gap: 6px; align-items: center;
    padding: 4px 0;
}
.sb-color-swatch {
    width: 32px; height: 32px; border-radius: 8px; border: 2px solid #fff;
    box-shadow: 0 0 0 1px #e2e8f0, inset 0 0 0 1px rgba(0,0,0,0.04);
}
.sb-brand-row input {
    padding: 6px 8px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: transparent;
}
.sb-brand-row input:hover { background: #f8fafc; border-color: #e2e8f0; }
.sb-brand-row input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-brand-row input[type="color"] { padding: 0; border-radius: 8px; cursor: pointer; height: 32px; width: 32px; background: transparent; border: none; }
.sb-brand-row input[type="color"]::-webkit-color-swatch { border-radius: 6px; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; }
.sb-brand-row input[type="color"]::-moz-color-swatch { border-radius: 6px; border: 2px solid #fff; }
.sb-font-preview {
    padding: 6px 10px; border-radius: 8px; background: linear-gradient(135deg, #f8fafc, #fff);
    border: 1px solid #e2e8f0; font-size: var(--d-fs-sm); color: #1e293b;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ===== KPI ===== */
.sb-kpi-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 6px; }
.sb-kpi-row {
    display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; align-items: start;
    padding: 8px; background: linear-gradient(135deg, #f8fafc, #fff);
    border: 1px solid #e2e8f0; border-radius: 10px;
}
.sb-kpi-cell { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sb-kpi-row input {
    padding: 5px 8px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: transparent;
    width: 100%; box-sizing: border-box; min-width: 0;
}
.sb-kpi-row input:hover { background: #fff; border-color: #e2e8f0; }
.sb-kpi-row input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-kpi-value { font-weight: 700; color: var(--thoxan-800); font-size: var(--d-fs-base) !important; }
.sb-kpi-label { color: #64748b; font-size: var(--d-fs-xs) !important; text-transform: uppercase; letter-spacing: 0.4px; }
.sb-kpi-target, .sb-kpi-period { color: #1e293b; font-size: var(--d-fs-sm); }

/* ===== Tracking-Status ===== */
.sb-track-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 6px; }
.sb-track-row {
    display: grid; grid-template-columns: 110px 1fr 1fr auto; gap: 6px; align-items: center;
    padding: 4px 6px; border-radius: 8px; border: 1px solid transparent;
}
.sb-track-row:hover { background: #f8fafc; border-color: #e2e8f0; }
.sb-track-row .sb-track-status {
    padding: 4px 8px; border-radius: 6px; font-size: var(--d-fs-xs); font-weight: 600;
    border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-family: inherit;
}
.sb-track-row.sb-track-ok    .sb-track-status { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.sb-track-row.sb-track-fehlt .sb-track-status { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.sb-track-row.sb-track-tbd   .sb-track-status { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.sb-track-row.sb-track-na    .sb-track-status { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
.sb-track-row input {
    padding: 5px 8px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: transparent;
    width: 100%; box-sizing: border-box; min-width: 0;
}
.sb-track-row input:hover { background: #fff; border-color: #e2e8f0; }
.sb-track-row input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-track-label { font-weight: 600; color: #1e293b; }
.sb-track-note  { color: #64748b; font-size: var(--d-fs-xs) !important; }

/* ===== Import V2 (mit Vorschau) ===== */
.sb-impv2-overlay { display: none; align-items: center; justify-content: center; }
.sb-impv2-overlay.open { display: flex; }
.sb-impv2-modal { max-width: 720px; width: 92vw; max-height: 90vh; display: flex; flex-direction: column; }
.sb-impv2-modal-wide { max-width: 920px; }
.sb-impv2-preview-body { overflow: auto; max-height: 64vh; }
.sb-impv2-choice { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.sb-impv2-choice-btn {
    display: flex; flex-direction: column; gap: 6px; align-items: center; padding: 28px 16px;
    background: #fff; border: 2px dashed #e2e8f0; border-radius: 12px; cursor: pointer;
    transition: all 0.18s ease; font-family: inherit; color: #1e293b;
}
.sb-impv2-choice-btn:hover { border-color: var(--thoxan-700); background: rgba(0,76,155,0.04); }
.sb-impv2-choice-btn .material-symbols-rounded { font-size: 32px; color: var(--thoxan-700); }
.sb-impv2-choice-btn strong { font-size: var(--d-fs-base); }
.sb-impv2-choice-btn small { color: #64748b; font-size: var(--d-fs-xs); }
.sb-impv2-spinner {
    width: 48px; height: 48px; border-radius: 50%;
    border: 4px solid #e2e8f0; border-top-color: var(--thoxan-700);
    margin: 0 auto; animation: sb-impv2-spin 0.8s linear infinite;
}
@keyframes sb-impv2-spin { to { transform: rotate(360deg); } }
.sb-impv2-toolbar {
    display: flex; gap: 6px; align-items: center; padding: 8px 0 12px 0;
    border-bottom: 1px solid #e2e8f0; margin-bottom: 12px;
}
.sb-impv2-list { display: flex; flex-direction: column; gap: 8px; }
.sb-impv2-item {
    display: grid; grid-template-columns: 28px 1fr; gap: 12px; align-items: start;
    padding: 12px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 10px;
    cursor: pointer; transition: all 0.15s ease;
}
.sb-impv2-item.is-checked { background: rgba(0,76,155,0.04); border-color: var(--thoxan-300); }
.sb-impv2-item:hover { border-color: var(--thoxan-700); }
.sb-impv2-item input[type=checkbox] { margin-top: 2px; width: 18px; height: 18px; cursor: pointer; }
.sb-impv2-item-body { min-width: 0; }
.sb-impv2-item-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.sb-impv2-item-type {
    width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; color: var(--thoxan-700);
}
.sb-impv2-item-type .material-symbols-rounded { font-size: 18px; }
.sb-impv2-item-title { font-weight: 700; font-size: var(--d-fs-base); color: #1e293b; flex: 1; }
.sb-impv2-item-tab {
    display: inline-block; padding: 2px 8px; background: #f1f5f9; color: #475569;
    border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 600;
}
.sb-impv2-item-preview { font-size: var(--d-fs-sm); color: #334155; line-height: 1.5; }
.sb-impv2-item-preview ul { margin: 0; }
.sb-impv2-item-reason { color: #94a3b8; font-size: var(--d-fs-xs); margin-top: 4px; }
.sb-impv2-html-preview { background: #fff; padding: 6px 10px; border-radius: 6px; border: 1px solid #e2e8f0; max-height: 140px; overflow: auto; }

/* ===== KI-Vorschlaege-Drawer (Stufe B) ===== */
.sb-suggest-drawer {
    position: fixed; top: 88px; right: 16px; width: 420px; max-width: 92vw;
    max-height: calc(100vh - 120px); display: flex; flex-direction: column;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
    transform: translateX(440px); transition: transform 0.25s ease;
    z-index: 80;
}
.sb-suggest-drawer.open { transform: translateX(0); }
.sb-suggest-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 16px; border-bottom: 1px solid #e2e8f0;
}
.sb-suggest-head strong { display: block; font-size: var(--d-fs-base); color: #1e293b; }
.sb-suggest-head small { color: #64748b; font-size: var(--d-fs-xs); }
.sb-suggest-close {
    width: 32px; height: 32px; border: none; background: transparent;
    color: #64748b; cursor: pointer; border-radius: 6px;
}
.sb-suggest-close:hover { background: #f1f5f9; color: #1e293b; }
.sb-suggest-toolbar {
    display: flex; gap: 8px; align-items: center; padding: 10px 16px;
    border-bottom: 1px solid #e2e8f0; background: #f8fafc;
}
.sb-suggest-meta { color: #64748b; font-size: var(--d-fs-xs); }
.sb-suggest-body { overflow: auto; padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.sb-suggest-hint {
    padding: 24px 16px; text-align: center; color: #64748b;
    font-size: var(--d-fs-sm); background: #f8fafc; border-radius: 10px;
}
.sb-suggest-error { background: var(--rose-50); color: var(--rose-700); border: 1px solid var(--rose-200); }
.sb-suggest-item {
    padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    display: flex; flex-direction: column; gap: 8px;
}
.sb-suggest-item.sb-suggest-busy { opacity: 0.5; pointer-events: none; }
.sb-suggest-item-snippet {
    font-size: var(--d-fs-sm); color: #1e293b; line-height: 1.5;
    white-space: pre-wrap; word-break: break-word;
}
.sb-suggest-item-sources {
    display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
    color: #94a3b8; font-size: var(--d-fs-xs);
}
.sb-suggest-source-chip {
    display: inline-block; padding: 2px 6px; background: #f1f5f9;
    border-radius: 4px; color: #475569;
    max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sb-suggest-item-actions { display: flex; gap: 6px; justify-content: flex-end; }
.sb-spin { animation: sb-impv2-spin 0.9s linear infinite; }

/* Suggest-Button auf der Karte */
.sb-card-suggest-btn { color: var(--thoxan-700); }
.sb-card-suggest-btn:hover { color: var(--thoxan-900); background: rgba(0,76,155,0.08); }

/* ===== Contacts ===== */
.sb-contacts { display: flex; flex-direction: column; gap: 10px; }
.sb-contact-group {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 8px 10px;
}
.sb-contact-group-head { display: flex; gap: 4px; align-items: center; margin-bottom: 6px; }
.sb-contact-group-title {
    flex: 1; padding: 4px 6px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-weight: 700; color: #1e293b; background: transparent; font-family: inherit;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.sb-contact-group-title:hover { background: #fff; border-color: #e2e8f0; }
.sb-contact-group-title:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }

.sb-contact-people { display: flex; flex-direction: column; gap: 6px; min-height: 8px; }
.sb-contact-person {
    display: grid; grid-template-columns: 16px 36px 1fr auto; gap: 8px;
    align-items: start; background: #fff; border: 1px solid #f1f5f9; border-radius: 8px;
    padding: 6px;
    transition: opacity 0.15s ease, box-shadow 0.15s ease;
}
.sb-contact-person.sb-contact-dragging { opacity: 0.35; }
.sb-contact-handle {
    display: flex; align-items: center; justify-content: center;
    color: #cbd5e1; cursor: grab; user-select: none;
    align-self: stretch;
}
.sb-contact-handle:hover { color: var(--thoxan-700); }
.sb-contact-handle .material-symbols-rounded { font-size: 18px; }
.sb-contact-person:active .sb-contact-handle { cursor: grabbing; }
.sb-contact-drop-line {
    height: 3px; background: var(--thoxan-700); border-radius: 2px;
    margin: 2px 0; box-shadow: 0 0 0 3px rgba(0,76,155,0.15);
}
.sb-contact-avatar {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #0891b2, #22d3ee); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: var(--d-fs-sm); letter-spacing: 0.5px;
}
.sb-contact-fields { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.sb-contact-row { display: flex; gap: 4px; }
.sb-contact-row-2col { display: grid; grid-template-columns: 1fr 80px; gap: 4px; }
.sb-contact-row input {
    width: 100%; padding: 5px 8px; border: 1px solid transparent; border-radius: 6px;
    font-size: var(--d-fs-sm); font-family: inherit; background: transparent;
    box-sizing: border-box; min-width: 0;
}
.sb-contact-row input:hover { background: #f8fafc; border-color: #e2e8f0; }
.sb-contact-row input:focus { outline: none; background: #fff; border-color: var(--thoxan-700); box-shadow: 0 0 0 2px rgba(0,76,155,0.1); }
.sb-contact-role { font-weight: 600; color: #475569; }
.sb-contact-initials { text-transform: uppercase; text-align: center; font-weight: 700; }

/* ===== Maximized-Card-Modal ===== */
.sb-maximize-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
    z-index: 400; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.sb-maximize-overlay.open { display: flex; animation: sb-fade-in 0.2s; }
@keyframes sb-fade-in { from { opacity: 0; } to { opacity: 1; } }
.sb-maximize-card {
    width: 95vw; max-width: 1200px; height: 96vh;
    background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    display: flex; flex-direction: column; overflow: hidden;
    animation: sb-zoom-in 0.2s ease-out;
}
@keyframes sb-zoom-in { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.sb-maximize-head {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
}
.sb-maximize-body {
    flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem;
    min-height: 0;
}

/* ===== Sitemap-Liste ===== */
.sm-host-block { margin-bottom: 1.1rem; }
.sm-host-head {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; background: #f0fdfa; border: 1px solid #ccfbf1;
    border-radius: 10px 10px 0 0;
}
.sm-host-count {
    margin-left: auto; background: #0891b2; color: #fff;
    padding: 2px 9px; border-radius: 10px; font-size: var(--d-fs-xs); font-weight: 700;
}
.sm-pages {
    border: 1px solid #e2e8f0; border-top: 0;
    border-radius: 0 0 10px 10px; overflow: hidden;
}
.sm-page {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; border-bottom: 1px solid #f1f5f9;
    transition: background 0.1s;
}
.sm-page:last-child { border-bottom: 0; }
.sm-page:hover { background: #fafbfc; }
.sm-page-link {
    flex: 1; min-width: 0; text-decoration: none; color: inherit;
    display: flex; flex-direction: column; gap: 1px;
}
.sm-page-title {
    font-size: var(--d-fs-sm); font-weight: 500; color: #1e293b;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sm-page-url {
    font-size: var(--d-fs-xs); color: #64748b;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    font-family: ui-monospace, monospace;
}
.sm-page-kb {
    color: #cbd5e1; padding: 6px; border-radius: 7px;
    text-decoration: none; transition: all 0.1s;
    display: flex; align-items: center;
}
.sm-page-kb:hover { color: #10b981; background: #f1f5f9; }
.sm-page-kb .material-symbols-rounded { font-size: 16px; }
.sm-page-date { font-size: var(--d-fs-xs); color: #94a3b8; flex-shrink: 0; min-width: 38px; text-align: right; }

/* ===== Import-Progress-Modal ===== */
.sb-import-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
    z-index: 410; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.sb-import-overlay.open { display: flex; animation: sb-fade-in 0.2s; }
.sb-import-modal {
    width: 92vw; max-width: 520px;
    background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: sb-zoom-in 0.25s ease-out;
}
.sb-import-head {
    padding: 1.2rem 1.4rem 0.5rem;
    display: flex; align-items: center; gap: 0.6rem;
}
.sb-import-head .material-symbols-rounded {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #10b981, #34d399); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    animation: sb-pulse-glow 2s ease-in-out infinite;
}
@keyframes sb-pulse-glow { 0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); } 50% { box-shadow: 0 0 0 6px rgba(16,185,129,0.15); } }
.sb-import-head h3 { margin: 0; font-size: var(--d-fs-base); }
.sb-import-head small { display: block; color: #94a3b8; font-size: var(--d-fs-xs); margin-top: 2px; }
.sb-import-body { padding: 0.6rem 1.4rem 1.2rem; }
.sb-import-step {
    display: flex; gap: 10px; align-items: center; padding: 8px 4px;
    color: #94a3b8; font-size: var(--d-fs-sm); transition: color 0.2s;
}
.sb-import-step .step-dot {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid #cbd5e1; background: #fff; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.25s;
}
.sb-import-step .step-dot .material-symbols-rounded { font-size: 14px; color: transparent; }
.sb-import-step.active { color: #047857; font-weight: 600; }
.sb-import-step.active .step-dot {
    border-color: #10b981; background: #fff;
    box-shadow: 0 0 0 4px rgba(16,185,129,0.12);
}
.sb-import-step.active .step-dot::before {
    content: ''; position: absolute; width: 8px; height: 8px; border-radius: 50%;
    background: #10b981; animation: sb-pulse-dot 1s ease-in-out infinite;
}
@keyframes sb-pulse-dot { 0%,100% { transform: scale(1); opacity: 0.6; } 50% { transform: scale(1.3); opacity: 1; } }
.sb-import-step.done { color: #475569; }
.sb-import-step.done .step-dot { border-color: #10b981; background: #10b981; }
.sb-import-step.done .step-dot .material-symbols-rounded { color: #fff; }
.sb-import-step.error { color: var(--rose-700); }
.sb-import-step.error .step-dot { border-color: var(--rose-600); background: #fee2e2; }
.sb-import-hint {
    margin-top: 12px; padding: 10px 12px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    font-size: var(--d-fs-sm); color: #64748b;
}
.sb-import-progress {
    margin-top: 12px; height: 4px; background: #f1f5f9; border-radius: 2px; overflow: hidden;
}
.sb-import-progress::after {
    content: ''; display: block; width: 30%; height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    animation: sb-import-bar 1.6s ease-in-out infinite;
}
@keyframes sb-import-bar {
    0% { margin-left: -30%; }
    100% { margin-left: 100%; }
}
.sb-import-foot { padding: 0.8rem 1.4rem 1.2rem; display: flex; justify-content: flex-end; gap: 0.5rem; }

/* ===== History-Modal ===== */
.sb-history-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
    z-index: 405; display: none; align-items: center; justify-content: center; padding: 2vh;
}
.sb-history-overlay.open { display: flex; animation: sb-fade-in 0.2s; }
.sb-history-modal {
    width: 92vw; max-width: 720px; max-height: 90vh;
    background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    display: flex; flex-direction: column; overflow: hidden;
    animation: sb-zoom-in 0.2s ease-out;
}
.sb-history-head {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
}
.sb-history-head h3 { margin: 0; font-size: var(--d-fs-base); }
.sb-history-head small { display: block; color: #94a3b8; font-size: var(--d-fs-sm); margin-top: 1px; }
.sb-history-body { flex: 1; overflow-y: auto; padding: 0.5rem; min-height: 0; }
.sb-history-item {
    border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px;
    background: #fff; transition: all 0.15s;
}
.sb-history-item:hover { border-color: #cbd5e1; background: #fafbfc; }
.sb-history-meta {
    display: flex; align-items: baseline; gap: 8px; font-size: var(--d-fs-sm); color: #64748b;
    margin-bottom: 4px; flex-wrap: wrap;
}
.sb-history-meta strong { color: #1e293b; font-size: var(--d-fs-sm); }
.sb-history-meta small { color: #94a3b8; }
.sb-history-title-pre {
    font-size: var(--d-fs-sm); color: #475569; padding: 4px 0; font-style: italic;
}
.sb-history-actions {
    display: flex; gap: 6px; margin-top: 6px;
}
.sb-history-preview {
    margin-top: 8px; padding: 10px; border-radius: 8px;
    background: #f8fafc; border: 1px solid #e2e8f0;
}
.sb-history-preview-content { font-size: var(--d-fs-sm); line-height: 1.55; max-height: 280px; overflow-y: auto; color: #334155; }
.sb-history-preview-content h1, .sb-history-preview-content h2, .sb-history-preview-content h3 { margin: 0.4em 0 0.2em; }
.sb-history-preview-content ul, .sb-history-preview-content ol { padding-left: 1.5em; margin: 0.3em 0; }

/* ===== Empty States ===== */
.sb-empty-state {
    text-align: center; padding: 2.5rem 1rem; color: #94a3b8;
    background: linear-gradient(180deg, #f8fafc, #fff); border: 2px dashed #e2e8f0; border-radius: 14px;
    margin-top: 1rem;
}
.sb-empty-state .material-symbols-rounded { font-size: 40px; color: #cbd5e1; margin-bottom: 0.4rem; }
.sb-empty-state h3 { margin: 0 0 0.4rem; color: #475569; font-size: var(--d-fs-base); }
.sb-empty-state p { margin: 0; font-size: var(--d-fs-sm); }

/* ===== Suchen — filtered out ===== */
.sb-card.sb-hidden, .cs-card.sb-hidden { display: none; }
.sb-card mark, .cs-card mark { background: #fde68a; color: inherit; padding: 0 2px; border-radius: 3px; }

/* ===== Collapse für ALLE Cards ===== */
.cs-card.collapsed .sb-collapsible-body { max-height: 0; padding: 0; opacity: 0; overflow: hidden; }
.cs-card .sb-collapsible-body { transition: max-height 0.25s ease, opacity 0.2s ease; }
.cs-card .sb-toggle-icon { transition: transform 0.2s; }
.cs-card.collapsed .sb-toggle-icon { transform: rotate(180deg); }

/* ===== Saved-Indicator ===== */
.sb-save-pill {
    display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 10px;
    font-size: var(--d-fs-xs); font-weight: 600; transition: all 0.2s;
}
.sb-save-pill.saving { background: #fef3c7; color: #92400e; }
.sb-save-pill.saved { background: #d1fae5; color: #047857; }
.sb-save-pill.error { background: #fee2e2; color: #991b1b; }
.sb-save-pill .material-symbols-rounded { font-size: 12px; }
</style>

<?php
$_csAbbr = trim($customer['abbreviation'] ?? '');
if ($_csAbbr === '') {
    $_csAbbr = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/u', '', $customer['name'] ?? '?'), 0, 3));
}
$_csLogo = trim($customer['logo_path'] ?? '');
$_csLogoUrl = $_csLogo ? '/uploads/customers/logos/' . htmlspecialchars(basename($_csLogo)) : '';

// Master-Detail-Layout: Sidebar links + Steckbrief rechts
$activeCustomerId = (int) $customer['id'];
include __DIR__ . '/_customer_master_styles.php';
$db = \Core\Database::getInstance();
$customers = $db->query("SELECT * FROM customers ORDER BY name") ?: [];
?>
<div class="cm-page">
    <?php include __DIR__ . '/_customer_master_sidebar.php'; ?>
    <section class="cm-main">
        <div class="cm-main-inner">
<div class="cs-wrap">
    <div class="thx-page-header" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:1.25rem;flex:1;min-width:0;">
            <!-- Logo / Badge -->
            <div class="cs-logo-wrap" id="cs-logo-wrap">
                <?php if ($_csLogoUrl): ?>
                <img src="<?= $_csLogoUrl ?>" class="cs-customer-logo" id="cs-customer-logo"
                     alt="<?= htmlspecialchars($customer['name']) ?>" title="Logo bearbeiten">
                <?php else: ?>
                <div class="cs-customer-badge" id="cs-customer-badge" title="Kürzel: <?= htmlspecialchars($_csAbbr) ?>">
                    <?= htmlspecialchars($_csAbbr) ?>
                </div>
                <?php endif; ?>
                <!-- Verstecktes File-Input, von der Menü-Option getriggert -->
                <input type="file" id="cs-logo-file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" style="display:none;" onchange="sbUploadLogo(this)">
                <!-- Einziger sichtbarer Edit-Trigger (nur bei Hover) -->
                <button class="cs-logo-edit-btn" type="button" onclick="sbToggleLogoMenu(event)" title="Logo bearbeiten">
                    <span class="material-symbols-rounded">edit</span>
                </button>
                <!-- Popover-Menü mit allen Aktionen -->
                <div class="cs-logo-menu" id="cs-logo-menu">
                    <button type="button" class="cs-logo-menu-item" onclick="document.getElementById('cs-logo-file').click();sbCloseLogoMenu();">
                        <span class="material-symbols-rounded">upload_file</span> Logo hochladen
                    </button>
                    <button type="button" class="cs-logo-menu-item" onclick="sbFetchFavicon();sbCloseLogoMenu();"
                            <?= empty($customer['website']) ? 'disabled' : '' ?>
                            title="<?= !empty($customer['website']) ? 'Favicon von Website holen' : 'Keine Website hinterlegt' ?>">
                        <span class="material-symbols-rounded">language</span> Favicon von Website
                    </button>
                    <?php if ($_csLogoUrl): ?>
                    <button type="button" class="cs-logo-menu-item is-danger" onclick="sbDeleteLogo();sbCloseLogoMenu();">
                        <span class="material-symbols-rounded">delete</span> Logo entfernen
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div style="min-width:0;">
                <div style="display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
                    <h1 class="thx-page-title" style="margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($customer['name']) ?></h1>
                    <label class="cs-status-toggle" style="margin-top:2px;">
                        <input type="checkbox" id="cs-active-toggle" <?= !empty($customer['is_active']) ? 'checked' : '' ?> onchange="sbToggleActive(this)">
                        <span class="cs-status-slider"></span>
                        <span class="cs-status-text"><?= !empty($customer['is_active']) ? 'Aktiv' : 'Inaktiv' ?></span>
                    </label>
                </div>
                <div class="thx-page-subtitle"><?= htmlspecialchars($customer['industry'] ?? 'Kunde') ?></div>
                <!-- CRM-Firma-Verknüpfung: Chip mit Inline-Edit -->
                <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <?php if (!empty($crmFirmaLinked)): ?>
                    <span id="cs-crmfirma-chip" style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:var(--emerald-50);border:1px solid var(--emerald-200);color:var(--emerald-700);font-size:0.78rem;font-weight:600;">
                        <span class="material-symbols-rounded" style="font-size:14px;">link</span>
                        CRM:
                        <a href="/crm/firmen/<?= (int)$crmFirmaLinked['id'] ?>" target="_blank" style="color:inherit;text-decoration:underline;" id="cs-crmfirma-name"><?= htmlspecialchars($crmFirmaLinked['firmenname']) ?></a>
                        <button type="button" onclick="csCrmFirmaOpenSearch()" title="Andere Firma verknüpfen" style="background:none;border:0;color:var(--emerald-700);cursor:pointer;padding:0;display:inline-flex;align-items:center;">
                            <span class="material-symbols-rounded" style="font-size:13px;">edit</span>
                        </button>
                        <button type="button" onclick="csCrmFirmaUnlink()" title="Verknüpfung lösen" style="background:none;border:0;color:var(--rose-600);cursor:pointer;padding:0;display:inline-flex;align-items:center;">
                            <span class="material-symbols-rounded" style="font-size:13px;">link_off</span>
                        </button>
                    </span>
                    <?php else: ?>
                    <button type="button" id="cs-crmfirma-chip" onclick="csCrmFirmaOpenSearch()" style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:var(--slate-50);border:1px dashed var(--slate-300);color:var(--slate-600);font-size:0.78rem;font-weight:600;cursor:pointer;">
                        <span class="material-symbols-rounded" style="font-size:14px;">link</span>
                        Mit CRM-Firma verknüpfen
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="thx-page-actions">
            <a href="/admin/customers/<?= (int)$customer['id'] ?>/portal" class="thx-btn thx-btn-secondary thx-btn-small" title="Kundenportal verwalten: Module freischalten, Inhalte sichtbar machen, Zugänge anlegen">
                <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">storefront</span>
                Kundenportal
            </a>
            <?php if (\Core\Auth::isAdmin() || \Core\Auth::isManager()): ?>
                <button id="cs-layout-edit-toggle" class="thx-btn thx-btn-secondary thx-btn-small" onclick="csToggleLayoutEdit()" title="Karten verschieben, vergrößern, löschen — bewusst aus, um konsistentes Layout über alle Kunden zu halten">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">tune</span>
                    <span id="cs-layout-edit-label">Layout anpassen</span>
                </button>
                <!-- Layout-Templates: nur sichtbar im Layout-Edit-Modus -->
                <button class="thx-btn thx-btn-secondary thx-btn-small cs-tpl-action" onclick="csTplOpenApply()" title="Karten-Layout aus einem Template anwenden">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">file_download</span>
                    Layout anwenden
                </button>
                <button class="thx-btn thx-btn-secondary thx-btn-small cs-tpl-action" onclick="csTplOpenSave()" title="Aktuelles Layout als Template speichern">
                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">save</span>
                    Als Standard speichern
                </button>
            <?php endif; ?>
            <a href="/admin/customers" class="thx-btn thx-btn-secondary thx-btn-small">← Zurück</a>
            <?php if (\Core\Auth::isAdmin()): ?>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="csCustomerDelete()"
                    style="color:var(--rose-700);border-color:var(--rose-300);"
                    title="Kunde komplett aus der Datenbank entfernen (mit Dependency-Check + Name-Bestätigung)">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                Kunde löschen
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- CRM-Firma-Such-Modal -->
    <div id="cs-crmfirma-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:flex-start;justify-content:center;padding-top:80px;" onclick="if(event.target===this)csCrmFirmaCloseSearch()">
        <div style="background:#fff;border-radius:8px;width:520px;max-width:calc(100% - 40px);box-shadow:0 10px 40px rgba(0,0,0,0.2);overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--slate-200);display:flex;align-items:center;justify-content:space-between;">
                <h3 style="margin:0;font-size:1rem;">CRM-Firma verknüpfen</h3>
                <button onclick="csCrmFirmaCloseSearch()" style="background:none;border:0;cursor:pointer;color:var(--slate-500);"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div style="padding:14px 18px;">
                <input type="text" id="cs-crmfirma-input" autocomplete="off"
                       placeholder="Firma suchen (Name, Branche, Website) …"
                       oninput="csCrmFirmaSuche()"
                       style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.9rem;">
                <div id="cs-crmfirma-results" style="margin-top:8px;max-height:340px;overflow:auto;"></div>
                <p style="margin:12px 0 0;font-size:0.78rem;color:var(--slate-500);">Bestimmt, in welchen Wissensbucket CRM-Kontakte und ihre Firma synchronisiert werden.</p>
            </div>
        </div>
    </div>

    <!-- Suchleiste + Add-Card -->
    <div class="sb-toolbar" id="sb-toolbar">
        <div class="sb-search-wrap">
            <span class="material-symbols-rounded sb-search-icon">search</span>
            <input type="text" id="sb-search" placeholder="Suchen — oder Frage stellen (Enter für KI-Antwort)"
                   oninput="sbApplySearch(this.value)"
                   onkeydown="if(event.key==='Enter' && this.value.trim().length>2){event.preventDefault();sbAskAI(this.value);}">
            <button class="sb-search-ai" id="sb-search-ai" onclick="const v=document.getElementById('sb-search').value.trim();if(v.length>2)sbAskAI(v);" title="Mit KI fragen (Enter)">
                <span class="material-symbols-rounded">auto_awesome</span>
            </button>
            <button class="sb-search-clear" id="sb-search-clear" onclick="sbClearSearch()" title="Zurücksetzen" style="display:none;">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="sb-add-wrap">
            <button class="thx-btn thx-btn-primary thx-btn-small sb-add-btn" onclick="sbToggleAddMenu(event)">
                <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">add</span>
                Card hinzufügen
            </button>
            <div class="sb-add-menu" id="sb-add-menu">
                <button onclick="sbCreateCard('links')"><span class="material-symbols-rounded">link</span><div><strong>Links</strong><small>Sammlung von URLs mit Titel</small></div></button>
                <button onclick="sbCreateCard('contacts')"><span class="material-symbols-rounded">groups</span><div><strong>Kontakte</strong><small>Personen mit Rolle, Kürzel, Mail — gruppierbar</small></div></button>
                <button onclick="sbCreateCard('richtext')"><span class="material-symbols-rounded">edit_note</span><div><strong>Notiz</strong><small>Formatierter Text mit Listen, Headlines, Links</small></div></button>
                <button onclick="sbCreateCard('documents')"><span class="material-symbols-rounded">folder_zip</span><div><strong>Dokumente</strong><small>Dateien hochladen — landen in der Wissensbasis</small></div></button>
                <button onclick="sbCreateCard('images')"><span class="material-symbols-rounded">image</span><div><strong>Bilder</strong><small>Logos, Screenshots, Mood-Bilder</small></div></button>
                <button onclick="sbCreateCard('brand')"><span class="material-symbols-rounded">palette</span><div><strong>Markenidentität</strong><small>Farben &amp; Schriftarten mit Vorschau</small></div></button>
                <button onclick="sbCreateCard('kpi')"><span class="material-symbols-rounded">monitoring</span><div><strong>Kennzahlen</strong><small>Zahlen, Budgets, Ziele mit Zeitraum</small></div></button>
                <button onclick="sbCreateCard('tracking_status')"><span class="material-symbols-rounded">fact_check</span><div><strong>Tracking-Status</strong><small>Checkliste: aktiv / fehlt / offen / n/a</small></div></button>
                <button onclick="sbCreateCard('accounts')"><span class="material-symbols-rounded">key</span><div><strong>Konten &amp; IDs</strong><small>Account-IDs (Google Ads, GA4, Meta …) mit Kopier-Knopf</small></div></button>
            </div>
        </div>
        <div class="sb-more-wrap">
            <button class="sb-more-btn" onclick="sbToggleMoreMenu(event)" title="Mehr Aktionen" id="sb-more-btn">
                <span class="material-symbols-rounded">more_vert</span>
            </button>
            <div class="sb-more-menu" id="sb-more-menu">
                <button onclick="sbAutoArrange()">
                    <span class="material-symbols-rounded">auto_awesome</span>
                    <div><strong>Mit KI anordnen</strong><small>Reihenfolge und Größen automatisch anpassen</small></div>
                </button>
                <button onclick="sbStartImportPreview()">
                    <span class="material-symbols-rounded">post_add</span>
                    <div><strong>Steckbrief importieren (mit Vorschau)</strong><small>Word/PDF/Text · KI schlägt Karten vor · du wählst welche übernommen werden</small></div>
                </button>
                <button onclick="sbAutoSuggestAll()">
                    <span class="material-symbols-rounded">auto_awesome</span>
                    <div><strong>Steckbrief autobefüllen</strong><small>KI durchsucht die Wissensbasis und schlägt Inhalte für alle Karten vor</small></div>
                </button>
                <button onclick="sbStartImport()">
                    <span class="material-symbols-rounded">upload_file</span>
                    <div><strong>Schnell-Import (überschreibt direkt)</strong><small>Word/PDF · alter Flow ohne Vorschau, ergänzt Profil und Karten sofort</small></div>
                </button>
            </div>
            <input type="file" id="sb-import-input" accept=".docx,.pdf,.txt,.md,.html,.htm" style="display:none;" onchange="sbImportFileChosen(this.files)">
            <input type="file" id="sb-import-input-v2" accept=".docx,.pdf,.txt,.md,.html,.htm" style="display:none;" onchange="sbImportV2FileChosen(this.files)">
        </div>
    </div>

    <!-- System-Card-Templates (versteckt, JS holt sie raus) -->
    <div id="sb-system-templates" style="display:none;">
        <?php
        // Stammdaten = Identität (5 Felder), Marken-Profil = Inhalt/Tonalität (6 Felder).
        // 'website' + 'weitere Websites' hängen am Websites-Tab (Website-Crawl-Karte zeigt sie).
        $profileStammdaten = [
            ['key' => 'name',                 'label' => 'Firmenname',      'type' => 'text',     'placeholder' => 'Firmenname'],
            ['key' => 'abbreviation',         'label' => 'Kürzel',          'type' => 'text',     'placeholder' => 'WTK · WTÜK · B&Q'],
            ['key' => 'industry',             'label' => 'Branche',         'type' => 'text',     'placeholder' => 'z. B. Maschinenbau'],
        ];
        $profileMarken = [
            ['key' => 'description',          'label' => 'Beschreibung',    'type' => 'textarea', 'placeholder' => 'Was macht das Unternehmen?'],
            ['key' => 'target_audience',      'label' => 'Zielgruppe',      'type' => 'textarea', 'placeholder' => 'Wer wird angesprochen?'],
            ['key' => 'products_services',    'label' => 'Produkte/Services','type' => 'textarea', 'placeholder' => 'Was wird angeboten?'],
            ['key' => 'unique_selling_points','label' => 'USPs',            'type' => 'textarea', 'placeholder' => 'Was unterscheidet sie?'],
            ['key' => 'tone_of_voice',        'label' => 'Tonalität',       'type' => 'textarea', 'placeholder' => 'Wie wird kommuniziert?'],
            ['key' => 'brand_values',         'label' => 'Markenwerte',     'type' => 'textarea', 'placeholder' => 'Werte, Haltung'],
        ];
        $_domains = $customer['domains'] ?? [];

        // Inline-Helper, der ein Profil-Feld rendert (gleiche Optik wie heute)
        $renderPfField = function (array $f) use ($customer) {
            $val = $customer[$f['key']] ?? '';
            $hasVal = trim((string) $val) !== '';
            ob_start(); ?>
            <div class="cs-pf-field sb-searchable-row" data-field-key="<?= htmlspecialchars($f['key']) ?>" data-sb-field="<?= $f['label'] ?>">
                <div class="cs-pf-head">
                    <span class="cs-pf-label"><?= $f['label'] ?></span>
                    <button type="button" class="cs-pf-edit-btn" onclick="sbPfEdit(this)" title="Bearbeiten">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                    <div class="cs-pf-actions">
                        <button type="button" class="cs-pf-save-btn" onclick="sbPfSave(this)" title="Speichern (Enter)">
                            <span class="material-symbols-rounded">check</span>
                        </button>
                        <button type="button" class="cs-pf-cancel-btn" onclick="sbPfCancel(this)" title="Abbrechen (Esc)">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>
                <div class="cs-pf-value">
                    <?php if ($f['type'] === 'textarea'): ?>
                        <div class="cs-inline-input cs-pf-input <?= $hasVal ? '' : 'cs-pf-empty' ?>" data-field="<?= htmlspecialchars($f['key']) ?>" data-type="textarea"
                             contenteditable="false" data-placeholder="<?= htmlspecialchars($f['placeholder']) ?>"><?= htmlspecialchars($val) ?></div>
                    <?php else: ?>
                        <input type="<?= $f['type'] ?>" class="cs-inline-input cs-pf-input <?= $hasVal ? '' : 'cs-pf-empty' ?>"
                               data-field="<?= htmlspecialchars($f['key']) ?>"
                               value="<?= htmlspecialchars($val) ?>"
                               placeholder="<?= htmlspecialchars($f['placeholder']) ?>"
                               readonly>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        };
        ?>

        <!-- Stammdaten: Identität -->
        <div data-system-key="profile" id="cs-profile-card">
            <div style="font-size: var(--d-fs-xs);color:#64748b;margin-bottom:0.4rem;" id="cs-profile-status"></div>
            <!-- Tags / Art -->
            <div class="cs-pf-field" id="cs-tags-field">
                <div class="cs-pf-head">
                    <span class="cs-pf-label">Art</span>
                </div>
                <div class="cs-pf-value">
                    <div id="cs-tags-list" data-tags='<?= htmlspecialchars(json_encode($customer['tags'] ?? []), ENT_QUOTES) ?>'></div>
                </div>
            </div>
            <?php foreach ($profileStammdaten as $f) echo $renderPfField($f); ?>
            <div class="cs-pf-field cs-pf-readonly" title="Wird intern für Logo-Pfade und Embeddings gebraucht. Nach Anlage nicht änderbar.">
                <div class="cs-pf-head"><span class="cs-pf-label">Slug</span></div>
                <div class="cs-pf-value" style="font-family:ui-monospace,monospace;color:var(--slate-500);font-size:var(--d-fs-xs);"><?= htmlspecialchars($customer['slug']) ?></div>
            </div>
        </div>

        <!-- Marken-Profil: Inhalt/Tonalität (separate System-Card im Tab Marke) -->
        <div data-system-key="markenprofil" id="cs-markenprofil-card">
            <div style="font-size: var(--d-fs-xs);color:#64748b;margin-bottom:0.4rem;display:flex;align-items:center;gap:6px;">
                <span style="flex:1;">Was die KI über die Marke wissen soll. Inline-Klick zum Bearbeiten.</span>
                <button type="button" onclick="sbMpAutofill()" title="Aus Website neu befüllen"
                        style="border:1px solid var(--thoxan-200);background:var(--thoxan-50);color:var(--thoxan-700);padding:3px 8px;border-radius:5px;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                    <span class="material-symbols-rounded" style="font-size:13px;">auto_awesome</span>
                    KI-Autofill
                </button>
            </div>
            <?php foreach ($profileMarken as $f) echo $renderPfField($f); ?>
        </div>

        <!-- Versteckte Container fuer Website + weitere Websites — werden vom Website-System-Slot
             (Tab Websites) eingebunden, aber im Edit-Modus auch hier nutzbar fuer sbPfSave/sbDomainsAdd. -->
        <div id="cs-domains-shadow" style="display:none;">
            <input type="hidden" data-field="website" value="<?= htmlspecialchars($customer['website'] ?? '') ?>">
            <div id="cs-domains-list" data-domains='<?= htmlspecialchars(json_encode($_domains), ENT_QUOTES) ?>'></div>
        </div>

        <!-- Asana -->
        <div data-system-key="asana">
            <?php if (empty($projectGids)): ?>
                <div style="padding:1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;text-align:center;">
                    <p style="margin:0 0 0.5rem;color:#64748b;font-size: var(--d-fs-sm);">Noch keine Asana-Projekte verbunden.</p>
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="openAsanaConfig()">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">link</span>
                        Asana-Projekte verbinden
                    </button>
                </div>
            <?php else: ?>
                <div class="cs-stats">
                    <div class="cs-stat"><div class="cs-stat-label">Projekte</div><div class="cs-stat-value"><?= count($projectGids) ?></div></div>
                    <div class="cs-stat"><div class="cs-stat-label">Tasks</div><div class="cs-stat-value"><?= $kbTaskCount ?></div></div>
                    <div class="cs-stat"><div class="cs-stat-label">Wissen-Docs</div><div class="cs-stat-value"><?= $kbDocsAsana ?></div></div>
                </div>
                <div class="cs-row">
                    <div class="cs-row-label">Auto-Sync</div>
                    <div class="cs-row-value">
                        <?php if (!empty($asanaCfg['sync_enabled'])):
                            $_h = (int)($asanaCfg['sync_interval_hours'] ?? 72);
                            $_lbl = match (true) {
                                $_h >= 168 => 'wöchentlich',
                                $_h >= 72  => 'alle 3 Tage',
                                $_h >= 24  => 'täglich',
                                default    => 'alle '.$_h.'h',
                            };
                            echo 'Aktiv, '.$_lbl;
                        else: echo 'Aus'; endif; ?>
                    </div>
                </div>
                <div class="cs-row">
                    <div class="cs-row-label">Letzter Sync</div>
                    <div class="cs-row-value" id="last-sync"><?= $lastSyncAt ? htmlspecialchars(date('d.m.Y H:i', strtotime($lastSyncAt))) : 'Noch nie' ?></div>
                </div>
                <?php if ($lastJob): ?>
                <div class="cs-row">
                    <div class="cs-row-label">Letzter Job</div>
                    <div class="cs-row-value">
                        #<?= $lastJob['id'] ?> · <?= htmlspecialchars($lastJob['status']) ?>
                        <?php if ($lastJob['error_message']): ?><br><small style="color:var(--rose-600);"><?= htmlspecialchars($lastJob['error_message']) ?></small><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div style="margin-top:0.75rem;display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="syncAsana()" id="sync-btn">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">sync</span>
                        Jetzt synchronisieren
                    </button>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="openAsanaConfig()">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">settings</span>
                        Projekte verwalten
                    </button>
                    <button id="lam-asana-edit-btn" type="button" class="thx-btn thx-btn-secondary thx-btn-small"
                            onclick="lamAsanaOeffneModal()"
                            title="Asana-Projekt + Section für LAM-Linkoptionen einrichten">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">link</span>
                        LAM-Sync
                        <span id="lam-asana-status-inline" style="font-size:0.7rem;color:#94a3b8;font-weight:normal;margin-left:4px;">…</span>
                    </button>
                    <span id="sync-status" style="font-size: var(--d-fs-sm);color:#64748b;"></span>
                </div>
            <?php endif; ?>

            <!-- Modal-Container (selbst unsichtbar, enthält nur das Asana-Modal) -->
            <div id="lam-asana">

                <!-- Modal: nutzt einfache CSS-Toggling via display -->
                <div id="lam-asana-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;align-items:flex-start;justify-content:center;padding-top:80px;"
                     onclick="if(event.target===this){this.style.display='none';}">
                    <div style="background:#fff;border-radius:10px;width:600px;max-width:calc(100% - 40px);box-shadow:0 12px 32px rgba(15,23,42,0.18);overflow:hidden;">
                        <div style="padding:16px 22px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="margin:0;font-size:1rem;">LAM-Linkoptionen → Asana</h3>
                            <button type="button" onclick="document.getElementById('lam-asana-modal').style.display='none';" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
                        </div>
                        <div style="padding:18px 22px;display:flex;flex-direction:column;gap:14px;">
                            <div class="thx-form-field" style="position:relative;">
                                <label>Asana-Projekt</label>
                                <input id="lam-asana-projekt-suche" type="text"
                                       placeholder="Tippe zum Filtern — z.B. STEINMANN, VID, Linkaufbau …"
                                       autocomplete="off"
                                       oninput="lamAsanaProjektFiltern()"
                                       onfocus="lamAsanaProjektFiltern()"
                                       onblur="setTimeout(()=>{document.getElementById('lam-asana-projekt-liste').style.display='none';},150);"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.9rem;">
                                <input type="hidden" id="lam-asana-projekt">
                                <div id="lam-asana-projekt-liste"
                                     style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:2px;max-height:300px;overflow-y:auto;background:#fff;border:1px solid var(--slate-300);border-radius:6px;box-shadow:0 6px 18px rgba(15,23,42,0.12);z-index:10;"></div>
                                <div id="lam-asana-laedt" style="display:none;font-size:0.8rem;color:var(--slate-500);margin-top:4px;">lädt …</div>
                                <div id="lam-asana-projekt-aktuell" style="display:none;font-size:0.8rem;color:var(--emerald-700);margin-top:6px;"></div>
                            </div>
                            <div id="lam-asana-section-wrap" class="thx-form-field" style="display:none;position:relative;">
                                <label>Section (Spalte)</label>
                                <input id="lam-asana-section-suche" type="text"
                                       placeholder="Tippe zum Filtern …"
                                       autocomplete="off"
                                       oninput="lamAsanaSectionFiltern()"
                                       onfocus="lamAsanaSectionFiltern()"
                                       onblur="setTimeout(()=>{document.getElementById('lam-asana-section-liste').style.display='none';},150);"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.9rem;">
                                <input type="hidden" id="lam-asana-section">
                                <div id="lam-asana-section-liste"
                                     style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:2px;max-height:300px;overflow-y:auto;background:#fff;border:1px solid var(--slate-300);border-radius:6px;box-shadow:0 6px 18px rgba(15,23,42,0.12);z-index:10;"></div>
                                <div id="lam-asana-section-aktuell" style="display:none;font-size:0.8rem;color:var(--emerald-700);margin-top:6px;"></div>
                            </div>
                            <div id="lam-asana-fehler" style="display:none;padding:10px 14px;background:var(--rose-50);border-radius:6px;color:var(--rose-700);font-size:0.85rem;"></div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--slate-100);">
                                <button type="button" class="thx-btn thx-btn-secondary" onclick="document.getElementById('lam-asana-modal').style.display='none';">Abbrechen</button>
                                <button type="button" id="lam-asana-save" class="thx-btn thx-btn-primary" onclick="lamAsanaSpeichern()">Speichern</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Website -->
        <div data-system-key="website" id="cs-website-card">
            <div id="cs-website-content">
                <div class="cs-empty" style="padding:0.6rem 0;">
                    <span class="cw-spinner" style="display:inline-block;width:12px;height:12px;border:2px solid rgba(0, 76, 155, 0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>
                    Lade Website-Konfiguration…
                </div>
            </div>
            <!-- Kunden-Websites (pm_monitors = einzige Quelle) -->
            <div id="cs-cwebs" style="margin-top:14px;border-top:1px solid var(--slate-200);padding-top:12px;">
                <div style="font-size:var(--d-fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;margin-bottom:8px;">Websites des Kunden</div>
                <div id="cs-cwebs-list" style="display:flex;flex-direction:column;gap:6px;"></div>
                <div style="display:grid;grid-template-columns:1fr 110px auto;gap:6px;align-items:center;margin-top:10px;">
                    <input type="url" id="cs-cweb-url" class="thx-input" placeholder="https://…">
                    <input type="text" id="cs-cweb-label" class="thx-input" placeholder="Bezeichnung">
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="csWebAdd()"><span class="material-symbols-rounded" style="font-size:14px;">add</span> Hinzufügen</button>
                </div>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:var(--d-fs-xs);color:var(--slate-500);cursor:pointer;">
                    <input type="checkbox" id="cs-cweb-mon" checked> Beim Hinzufügen direkt ins Monitoring aufnehmen
                </label>
            </div>
        </div>

        <!-- Regeln (KI-Schreibregeln, Kategorien mit Checkbox-Liste) -->
        <div data-system-key="regeln" id="cs-regeln-card">
            <div id="cs-regeln-content">
                <div class="cs-empty" style="padding:0.6rem 0;">
                    <span class="cw-spinner" style="display:inline-block;width:12px;height:12px;border:2px solid rgba(99,102,241,0.3);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>
                    Lade Regeln…
                </div>
            </div>
        </div>

        <!-- Site-Monitor (Monitoring fuer alle Sites des Kunden) -->
        <div data-system-key="site_monitor" id="cs-sitemonitor-card">
            <div id="cs-sitemonitor-content">
                <div class="cs-empty" style="padding:0.6rem 0;">
                    <span class="cw-spinner" style="display:inline-block;width:12px;height:12px;border:2px solid rgba(99,102,241,0.3);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>
                    Lade Monitoring-Daten…
                </div>
            </div>
        </div>

        <!-- Wissen -->
        <div data-system-key="knowledge" id="cs-knowledge-card">
            <div style="display:flex;align-items:center;gap:0.4rem;font-size: var(--d-fs-sm);color:#64748b;margin-bottom:0.6rem;">
                <span id="kb-count"><?= $kbDocsTotal ?> Einträge</span>
            </div>
            <div>

            <!-- Quick-Add: URL/Datei/Text -->
            <div style="background:linear-gradient(135deg,var(--emerald-50),#ecfdf5);border:1px solid #bbf7d0;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                    <span class="material-symbols-rounded" style="color:#059669;font-size:18px;">auto_awesome</span>
                    <strong style="color:#047857;">Schnell ins Wissen</strong>
                </div>
                <div style="display:flex;gap:0.25rem;margin-bottom:0.5rem;border-bottom:1px solid #d1fae5;">
                    <button type="button" class="qa-tab active" data-qa="url" onclick="qaTab('url')">URL</button>
                    <button type="button" class="qa-tab" data-qa="website" onclick="qaTab('website')">Ganze Website</button>
                    <button type="button" class="qa-tab" data-qa="file" onclick="qaTab('file')">Datei</button>
                    <button type="button" class="qa-tab" data-qa="text" onclick="qaTab('text')">Text</button>
                </div>
                <div id="qa-url" class="qa-pane">
                    <div style="display:flex;gap:0.4rem;">
                        <input type="url" id="qa-url-input" placeholder="https://..." style="flex:1;padding:0.55rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);">
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="qaSubmit('url')" id="qa-url-btn">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                            Crawlen &amp; speichern
                        </button>
                    </div>
                    <small style="color:#64748b;font-size: var(--d-fs-xs);">Eine einzelne URL — KI bereinigt und legt sofort als Wissen ab.</small>
                </div>
                <div id="qa-website" class="qa-pane" style="display:none;">
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:end;">
                        <div style="flex:1;min-width:200px;">
                            <input type="url" id="qa-web-url" placeholder="https://firma.de" style="width:100%;padding:0.55rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);">
                        </div>
                        <div style="display:flex;gap:0.3rem;align-items:center;">
                            <label style="font-size: var(--d-fs-xs);color:#64748b;">Max. Seiten:</label>
                            <input type="number" id="qa-web-pages" value="15" min="1" max="50" style="width:60px;padding:0.45rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);">
                            <label style="font-size: var(--d-fs-xs);color:#64748b;">Tiefe:</label>
                            <select id="qa-web-depth" style="padding:0.45rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);">
                                <option value="1">1</option>
                                <option value="2" selected>2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="qaSubmitWebsite()" id="qa-web-btn">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">language</span>
                            Website crawlen
                        </button>
                    </div>
                    <small style="color:#64748b;font-size: var(--d-fs-xs);">Folgt internen Links der Domain, legt pro Seite ein Wissen-Dokument an. Live-Progress unten.</small>
                    <div id="qa-web-progress" style="display:none;margin-top:0.6rem;">
                        <div style="background:#e5e7eb;height:5px;border-radius:3px;overflow:hidden;margin-bottom:0.4rem;">
                            <div id="qa-web-bar" style="height:100%;background:linear-gradient(90deg,#10b981,#059669);width:0%;transition:width 0.3s;"></div>
                        </div>
                        <div id="qa-web-counter" style="font-size: var(--d-fs-xs);color:#64748b;margin-bottom:0.3rem;"></div>
                        <div id="qa-web-log" style="font-family:monospace;font-size: var(--d-fs-xs);max-height:140px;overflow-y:auto;color:#64748b;background:#fff;border:1px solid #d1fae5;border-radius:6px;padding:0.4rem;"></div>
                    </div>
                </div>
                <div id="qa-file" class="qa-pane" style="display:none;">
                    <div style="display:flex;gap:0.4rem;">
                        <input type="file" id="qa-file-input" accept=".pdf,.docx,.txt,.md,.html,.htm" style="flex:1;padding:0.55rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);background:#fff;">
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="qaSubmit('file')" id="qa-file-btn">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">upload</span>
                            Hochladen &amp; speichern
                        </button>
                    </div>
                    <small style="color:#64748b;font-size: var(--d-fs-xs);">PDF, Word, Text, Markdown, HTML — max 25 MB.</small>
                </div>
                <div id="qa-text" class="qa-pane" style="display:none;">
                    <input type="text" id="qa-text-title" placeholder="Titel (optional, sonst KI-Vorschlag)" style="width:100%;padding:0.55rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);margin-bottom:0.4rem;">
                    <textarea id="qa-text-content" rows="4" placeholder="Text einfuegen..." style="width:100%;padding:0.55rem;border:1px solid #d1fae5;border-radius:6px;font-size: var(--d-fs-sm);resize:vertical;font-family:inherit;"></textarea>
                    <div style="text-align:right;margin-top:0.4rem;">
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="qaSubmit('text')" id="qa-text-btn">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                            Speichern
                        </button>
                    </div>
                </div>
                <div id="qa-status" style="margin-top:0.5rem;font-size: var(--d-fs-sm);"></div>
            </div>

            <!-- Filter / Suche fuer Wissens-Liste -->
            <div style="display:flex;gap:0.4rem;margin-bottom:0.75rem;flex-wrap:wrap;">
                <input type="text" id="kb-search" placeholder="In Titel/Beschreibung suchen..." oninput="kbApplyFilter()" style="flex:1;min-width:200px;padding:0.5rem 0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);">
                <select id="kb-source-filter" onchange="kbApplyFilter()" style="padding:0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);">
                    <option value="">Alle Quellen</option>
                    <option value="upload">Datei</option>
                    <option value="url">URL</option>
                    <option value="text">Text</option>
                    <option value="asana">Asana</option>
                    <option value="chat">Chat</option>
                </select>
            </div>

            <div id="kb-list">
                <div class="cs-empty"><span class="cw-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(0, 76, 155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>Lade Wissen...</div>
            </div>
            </div><!-- /.sb-collapsible-body -->
        </div>
    </div>

    <?php
    // Aktiven Plan dieses Kunden suchen + Widget HTML in Variable speichern
    require_once SERVICES_PATH . '/PpWidgetRenderer.php';
    $_widgetRenderer = new \Services\PpWidgetRenderer(\Core\Database::getInstance());
    $_activePlanId = $_widgetRenderer->findLatestActivePlanIdForCustomer((int) $customer['id']);
    $_planWidgetHtml = $_activePlanId ? $_widgetRenderer->renderForPlan($_activePlanId) : '';
    if ($_activePlanId) echo \Services\PpWidgetRenderer::css();
    $_ppVisRow = \Core\Database::getInstance()->queryOne("SELECT enabled FROM customer_portal_permissions WHERE customer_id = ? AND kind = 'setting' AND module_key = 'shell_projektplanner'", [(int) $customer['id']]);
    $_ppVisible = $_ppVisRow !== null && (int)$_ppVisRow['enabled'] === 1;
    ?>

    <!-- Tab-Leiste — Reihenfolge: Übersicht, Personen, Websites, Inhalte, Dateien, Marke -->
    <div class="cs-tabs">
        <button class="cs-tab is-active" data-tab="uebersicht" onclick="csSetTab('uebersicht')">
            <span class="material-symbols-rounded">dashboard</span>Übersicht<span class="cs-tab-count" data-count-for="uebersicht"></span>
        </button>
        <button class="cs-tab" data-tab="personen" onclick="csSetTab('personen')">
            <span class="material-symbols-rounded">groups</span>Personen<span class="cs-tab-count" data-count-for="personen"></span>
        </button>
        <button class="cs-tab" data-tab="websites" onclick="csSetTab('websites')">
            <span class="material-symbols-rounded">language</span>Websites<span class="cs-tab-count" data-count-for="websites"></span>
        </button>
        <button class="cs-tab" data-tab="inhalte" onclick="csSetTab('inhalte')">
            <span class="material-symbols-rounded">edit_note</span>Inhalte<span class="cs-tab-count" data-count-for="inhalte"></span>
        </button>
        <button class="cs-tab" data-tab="dateien" onclick="csSetTab('dateien')">
            <span class="material-symbols-rounded">folder_zip</span>Dateien<span class="cs-tab-count" data-count-for="dateien"></span>
        </button>
        <button class="cs-tab" data-tab="marke" onclick="csSetTab('marke')">
            <span class="material-symbols-rounded">palette</span>Marke<span class="cs-tab-count" data-count-for="marke"></span>
        </button>
        <button class="cs-tab cs-tab-sonstiges" data-tab="sonstiges" onclick="csSetTab('sonstiges')"
                title="Karten, die durch einen Template-Apply hier abgelegt wurden — bitte sortieren oder loeschen">
            <span class="material-symbols-rounded">inbox</span>Sonstiges<span class="cs-tab-count" data-count-for="sonstiges"></span>
        </button>
    </div>

    <!-- Übersicht-Tab: Profil-Karte + Plan-Widget + Hauptkontakt
         Slot-Wrapper nutzen display:contents, damit die per JS einsortierten
         Cards direkte Grid-Kinder bleiben und ihre data-w-Breiten respektieren. -->
    <div class="cs-tab-panel cs-kanban" id="cs-tab-uebersicht">
        <div class="cs-hero" data-tab="uebersicht" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="uebersicht" ondragover="csColDragOver(event)" ondrop="csColDrop(event)">
            <?php if ($_activePlanId): ?>
            <div id="cs-uebersicht-plan" class="cs-card-shell is-readonly" data-plan-id="<?= (int) $_activePlanId ?>" data-col-span="1"
             draggable="true"
             ondragstart="csShellDragStart(event)"
             ondragend="csShellDragEnd(event)"
             ondragover="csShellDragOver(event)"
             ondragleave="csShellDragLeave(event)"
             ondrop="csShellDrop(event)">
            <div class="cs-card-shell-head sb-card-head">
                <div class="sb-card-icon" style="background: var(--slate-100);"><span class="material-symbols-rounded">view_kanban</span></div>
                <div class="cs-card-shell-title sb-card-title" contenteditable="plaintext-only"
                     onblur="csShellSaveTitle(this.textContent)"
                     onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">Aktiver Plan</div>
                <div class="cs-shell-actions">
                    <button class="sb-card-action sb-vis-btn <?= $_ppVisible ? 'is-on' : '' ?>" onclick="csShellToggleVisible(this)"
                            title="<?= $_ppVisible ? 'Für Kunden sichtbar — klicken zum Ausblenden' : 'Im Kundenportal anzeigen' ?>">
                        <span class="material-symbols-rounded"><?= $_ppVisible ? 'visibility' : 'visibility_off' ?></span>
                    </button>
                    <a class="sb-card-action"
                       href="/admin/projektplanner/plan/<?= (int) $_activePlanId ?>"
                       title="Im Projektplanner oeffnen (read-only — Bearbeitung dort)">
                        <span class="material-symbols-rounded">open_in_new</span>
                    </a>
                    <div class="sb-card-action-wrap">
                        <button class="sb-card-action" onclick="csShellToggleMovePop(event)" title="In anderen Tab verschieben">
                            <span class="material-symbols-rounded">swap_horiz</span>
                        </button>
                        <div class="sb-move-pop" id="cs-shell-move-pop">
                            <div style="font-size: var(--d-fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Verschieben nach</div>
                            <button class="sb-move-item" data-tab="uebersicht" onclick="csShellSetTargetTab('uebersicht')">
                                <span class="material-symbols-rounded">dashboard</span>Übersicht
                            </button>
                            <button class="sb-move-item" data-tab="personen" onclick="csShellSetTargetTab('personen')">
                                <span class="material-symbols-rounded">groups</span>Personen
                            </button>
                            <button class="sb-move-item" data-tab="websites" onclick="csShellSetTargetTab('websites')">
                                <span class="material-symbols-rounded">language</span>Websites
                            </button>
                            <button class="sb-move-item" data-tab="inhalte" onclick="csShellSetTargetTab('inhalte')">
                                <span class="material-symbols-rounded">edit_note</span>Inhalte
                            </button>
                            <button class="sb-move-item" data-tab="dateien" onclick="csShellSetTargetTab('dateien')">
                                <span class="material-symbols-rounded">folder_zip</span>Dateien
                            </button>
                            <button class="sb-move-item" data-tab="marke" onclick="csShellSetTargetTab('marke')">
                                <span class="material-symbols-rounded">palette</span>Marke
                            </button>
                        </div>
                    </div>
                    <div class="sb-card-action-wrap">
                        <button class="sb-card-action" onclick="csShellToggleSizePop(event)" title="Größe anpassen">
                            <span class="material-symbols-rounded">aspect_ratio</span>
                        </button>
                        <div class="sb-size-pop" id="cs-shell-size-pop">
                            <div class="sb-size-grid" id="cs-shell-size-grid"></div>
                            <div style="font-size: var(--d-fs-xs);color:#94a3b8;text-align:center;margin-top:6px;">Größe</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cs-card-shell-body">
                <?= $_planWidgetHtml ?>
            </div>
        </div>
            <?php endif; ?>
        </div>
        <div class="cs-col" data-col="2" data-tab="uebersicht" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="uebersicht" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>

    <!-- Inhalte-Tab: gleiches 3-Spalten-Kanban mit Hero-Zone -->
    <div class="cs-tab-panel cs-kanban" id="cs-tab-inhalte" style="display:none;">
        <div class="cs-hero" data-tab="inhalte" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="inhalte" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="inhalte" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="inhalte" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>
    <!-- Personen / Dateien / Marke: gleicher Kanban-Aufbau wie Uebersicht/Inhalte.
         Karten des jeweiligen Typs (contacts/documents/brand) landen hier als
         echte sb-cards mit voller Resize/Move/Delete/Drag-Unterstuetzung. -->
    <div class="cs-tab-panel cs-kanban" id="cs-tab-personen" style="display:none;">
        <div class="cs-hero" data-tab="personen" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="personen" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="personen" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="personen" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>
    <div class="cs-tab-panel cs-kanban" id="cs-tab-dateien" style="display:none;">
        <div class="cs-hero" data-tab="dateien" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="dateien" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="dateien" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="dateien" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>
    <div class="cs-tab-panel cs-kanban" id="cs-tab-sonstiges" style="display:none;">
        <div class="cs-hero" data-tab="sonstiges" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="sonstiges" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="sonstiges" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="sonstiges" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>
    <div class="cs-tab-panel cs-kanban" id="cs-tab-marke" style="display:none;">
        <div class="cs-hero" data-tab="marke" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="marke" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="marke" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="marke" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>
    <!-- Websites-Tab: Crawl-Karte (Website-System-Card), Monitoring-Karte (TBD),
         plus weitere Karten zu allem rund um die Websites des Kunden. -->
    <div class="cs-tab-panel cs-kanban" id="cs-tab-websites" style="display:none;">
        <div class="cs-hero" data-tab="websites" data-col="hero" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="1" data-tab="websites" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="2" data-tab="websites" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
        <div class="cs-col" data-col="3" data-tab="websites" ondragover="csColDragOver(event)" ondrop="csColDrop(event)"></div>
    </div>

    <!-- Card-Container: Cards landen hier beim Laden, dann verschiebt sie csRouteCards in die Tabs -->
    <div class="sb-cards" id="sb-cards" style="display:none;">
        <div class="cs-empty" id="sb-cards-loading"><span class="cw-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(0, 76, 155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>Lade Cards…</div>
    </div>

    <div class="sb-empty-state" id="sb-empty-state" style="display:none;">
        <span class="material-symbols-rounded">dashboard_customize</span>
        <h3>Noch keine Cards</h3>
        <p>Lege beliebige Karten an: Links, Notizen, Dokumente, Bilder oder Markenidentität — alle landen automatisch in der Wissensbasis.</p>
    </div>
</div>
        </div><!-- /cm-main-inner -->
    </section><!-- /cm-main -->
</div><!-- /cm-page -->

<!-- ===== Modal: Layout als Template speichern ===== -->
<div class="thx-modal-backdrop" id="cs-tpl-save-modal" style="display:none;"
     onclick="if(event.target===this)csTplCloseSave()">
    <div class="thx-modal" style="width:520px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Layout als Standard speichern</h3>
            <button class="thx-modal-close" onclick="csTplCloseSave()">&times;</button>
        </div>
        <div class="thx-modal-body">
            <p style="font-size:var(--d-fs-sm);color:var(--slate-600);margin:0 0 16px;">
                Speichert die aktuelle Karten-Anordnung (Tab, Spalte, Größe) als Vorlage.
                Du kannst sie später auf andere Kunden anwenden.
            </p>
            <div class="thx-form-field">
                <label>Name</label>
                <input type="text" id="cs-tpl-save-name" class="thx-input" placeholder="z.B. Retainer-Standard 2026" autofocus>
            </div>
            <div class="thx-form-field">
                <label>Beschreibung (optional)</label>
                <textarea id="cs-tpl-save-desc" class="thx-input" rows="3" placeholder="Notizen wofür dieses Template gedacht ist"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button class="thx-btn thx-btn-secondary" onclick="csTplCloseSave()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" onclick="csTplSubmitSave()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal: Template auswählen + anwenden ===== -->
<div class="thx-modal-backdrop" id="cs-tpl-apply-modal" style="display:none;"
     onclick="if(event.target===this)csTplCloseApply()">
    <div class="thx-modal" style="width:640px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">Layout-Template anwenden</h3>
            <button class="thx-modal-close" onclick="csTplCloseApply()">&times;</button>
        </div>
        <div class="thx-modal-body" id="cs-tpl-apply-body" style="min-height:300px;">
            <div style="text-align:center;padding:40px;color:var(--slate-400);">Lade Templates…</div>
        </div>
    </div>
</div>

<!-- Wissens-Detail-Drawer (rechts ausfahrend) -->
<div class="kb-drawer-overlay" id="kb-drawer-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:300;" onclick="closeKbDrawer()"></div>
<div class="kb-drawer" id="kb-drawer" style="display:none;position:fixed;top:0;right:0;width:90%;max-width:720px;height:100vh;background:#fff;box-shadow:-10px 0 30px rgba(0,0,0,0.15);z-index:301;overflow-y:auto;padding:1.5rem;">
    <div id="kb-drawer-content"></div>
</div>

<style>
.cs-doc-row {
    display: flex;
    gap: 0.6rem;
    padding: 0.6rem 0.7rem;
    border-bottom: 1px solid #f1f5f9;
    align-items: center;
    cursor: pointer;
    transition: background 0.1s;
}
.cs-doc-row:hover { background: #fafbff; }
.cs-doc-row.hidden { display: none; }
.cs-doc-actions { display:flex; gap:0.2rem; opacity:0; transition: opacity 0.15s; }
.cs-doc-row:hover .cs-doc-actions { opacity: 1; }
.cs-doc-action-btn {
    background: none;
    border: 1px solid transparent;
    border-radius: 6px;
    padding: 3px 5px;
    cursor: pointer;
    color: #94a3b8;
}
.cs-doc-action-btn:hover { background: #f1f5f9; color: #1e293b; border-color: #e2e8f0; }
.cs-doc-action-btn.danger:hover { background: var(--rose-50); color: var(--rose-600); border-color: var(--rose-200); }
.cs-doc-action-btn .material-symbols-rounded { font-size: 14px; display: block; }

.kb-drawer h2 { margin: 0 0 0.5rem; font-size: var(--d-fs-lg); }
.kb-drawer-section { margin-bottom: 1.5rem; }
.kb-drawer-label {
    font-size: var(--d-fs-xs);
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.3rem;
}
.kb-drawer input, .kb-drawer textarea, .kb-drawer select {
    width: 100%;
    padding: 0.55rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: var(--d-fs-sm);
    box-sizing: border-box;
    font-family: inherit;
}
.kb-drawer textarea { min-height: 80px; resize: vertical; }
.kb-drawer-content-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.85rem;
    max-height: 300px;
    overflow-y: auto;
    font-size: var(--d-fs-sm);
    line-height: 1.5;
    white-space: pre-wrap;
    color: #475569;
}
.kb-tag-chip {
    display: inline-block;
    padding: 2px 8px;
    background: #e6f0fa;
    color: var(--thoxan-900);
    border-radius: 10px;
    font-size: var(--d-fs-xs);
    margin: 0 4px 4px 0;
}
.kb-stat-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: var(--d-fs-sm);
    color: #64748b;
}
.kb-stat-row strong { color: #1e293b; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- Asana-Konfig-Modal -->
<div class="ac-modal-overlay" id="ac-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;justify-content:center;align-items:center;">
    <div class="ac-modal" style="background:#fff;border-radius:14px;width:92%;max-width:760px;max-height:85vh;overflow-y:auto;padding:1.5rem;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <h2 style="margin:0;font-size: var(--d-fs-lg);">Asana-Projekte verbinden</h2>
            <button class="btn-icon" onclick="closeAsanaConfig()" style="background:none;border:none;cursor:pointer;font-size: var(--d-fs-2xl);color:#64748b;">&times;</button>
        </div>
        <p style="margin:0 0 0.75rem;color:#64748b;font-size: var(--d-fs-sm);">
            Vorschläge basierend auf <strong><?= htmlspecialchars($customer['name']) ?></strong> erscheinen oben mit
            <span style="background:var(--thoxan-700);color:#fff;font-size: var(--d-fs-xs);padding:1px 7px;border-radius:10px;">Match</span>-Badge.
        </p>
        <div id="ac-state" style="margin-bottom:0.6rem;color:#64748b;font-size: var(--d-fs-sm);">Lade Asana-Projekte...</div>
        <div style="display:flex;gap:0.4rem;margin-bottom:0.5rem;flex-wrap:wrap;align-items:center;">
            <input type="text" id="ac-search" placeholder="Projekt suchen..." oninput="acFilter()" style="flex:1;min-width:200px;padding:0.5rem 0.75rem;border:1px solid #e2e8f0;border-radius:6px;font-size: var(--d-fs-sm);font-family:inherit;">
            <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="acToggleSelectedOnly()" id="ac-only-selected">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">checklist</span>
                Nur ausgewählte
            </button>
        </div>
        <div id="ac-projects" style="max-height:340px;overflow-y:auto;margin-bottom:1rem;border:1px solid #e2e8f0;border-radius:8px;"></div>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center;padding:0.75rem;background:#f8fafc;border-radius:8px;margin-bottom:1rem;font-size: var(--d-fs-sm);">
            <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                <input type="checkbox" id="ac-sync-enabled">
                Auto-Sync aktivieren
            </label>
            <label style="display:flex;align-items:center;gap:0.4rem;">
                Intervall:
                <select id="ac-sync-interval" style="padding:0.3rem;border:1px solid #e2e8f0;border-radius:6px;">
                    <option value="1">1 Stunde</option>
                    <option value="4">4 Stunden</option>
                    <option value="12">12 Stunden</option>
                    <option value="24">Täglich</option>
                    <option value="72" selected>Alle 3 Tage</option>
                    <option value="168">Wöchentlich</option>
                </select>
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:0.5rem;">
            <button class="thx-btn thx-btn-secondary" onclick="closeAsanaConfig()">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" id="ac-save-btn" onclick="saveAsanaConfig()">Speichern &amp; Sync starten</button>
        </div>
    </div>
</div>

<style>
.qa-tab {
    background: none;
    border: none;
    padding: 0.4rem 0.8rem;
    font-size: var(--d-fs-sm);
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    font-family: inherit;
}
.qa-tab.active {
    color: #047857;
    border-bottom-color: #10b981;
    font-weight: 600;
}
.ac-project {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.55rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 0.3rem;
    cursor: pointer;
    transition: all 0.15s;
}
.ac-project:hover { border-color: var(--thoxan-700); background: #fafbff; }
.ac-project.selected { border-color: var(--thoxan-700); background: #e6f0fa; }
.ac-project input { margin-top: 3px; }
.ac-project-info { flex: 1; min-width: 0; }
.ac-project-name { font-weight: 600; font-size: var(--d-fs-sm); }
.ac-project-meta { font-size: var(--d-fs-xs); color: #94a3b8; margin-top: 2px; }
</style>

<script>
// LAM-Asana-Konfig — Vanilla JS, isoliert, kein Framework-Konflikt
(function() {
    const LAM_CUSTOMER_ID = <?= (int) $customer['id'] ?>;
    const $ = id => document.getElementById(id);
    let projekte = [], sections = [], aktuelleProjektGid = '', aktuelleSectionGid = '';

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function zeigeFehler(t) { const el = $('lam-asana-fehler'); if (!t) { el.style.display = 'none'; return; } el.textContent = t; el.style.display = 'block'; }

    async function ladeAktuelleKonfig() {
        const statusEl = $('lam-asana-status-inline');
        if (!statusEl) return;
        try {
            const r = await fetch('/api/v1/lam/asana-kunde-konfig?customer_id=' + LAM_CUSTOMER_ID, { credentials: 'same-origin' });
            const j = await r.json();
            const data = (j.success && j.data) ? j.data : {};
            aktuelleProjektGid = data.asana_projekt_gid || '';
            aktuelleSectionGid = data.asana_section_gid || '';
            if (data.asana_projekt_name) {
                // Kompaktes Kürzel im Button (volle Info als Tooltip am Button-Title)
                statusEl.textContent = '✓';
                statusEl.style.color = 'var(--emerald-600)';
                statusEl.style.fontWeight = '600';
                const btn = $('lam-asana-edit-btn');
                if (btn) btn.title = 'LAM-Linkoptionen → Asana: ' + data.asana_projekt_name
                    + (data.asana_section_name ? ' › ' + data.asana_section_name : ' (Inbox)');
            } else {
                statusEl.textContent = '○';
                statusEl.style.color = '#cbd5e1';
            }
        } catch (e) {
            statusEl.textContent = '!';
            statusEl.style.color = 'var(--rose-500)';
        }
    }

    function rendereListe(items, listenEl, sucheEl, hiddenEl, aktuellEl, anAuswahl) {
        const q = (sucheEl.value || '').toLowerCase().trim();
        const gefiltert = q ? items.filter(it => (it.name || '').toLowerCase().includes(q)) : items;
        if (gefiltert.length === 0) {
            listenEl.innerHTML = '<div style="padding:14px;color:var(--slate-500);font-size:0.85rem;text-align:center;">Keine Treffer</div>';
        } else {
            const aktGid = hiddenEl.value;
            listenEl.innerHTML = gefiltert.slice(0, 100).map(it =>
                '<div data-gid="' + escapeHtml(it.gid) + '" data-name="' + escapeHtml(it.name) + '" '
                + 'style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--slate-100);font-size:0.9rem;'
                + (it.gid === aktGid ? 'background:var(--emerald-50);font-weight:600;' : '')
                + '" onmouseover="this.style.background=\'var(--slate-100)\'" onmouseout="this.style.background=\'' + (it.gid === aktGid ? 'var(--emerald-50)' : '#fff') + '\'">'
                + escapeHtml(it.name) + '</div>'
            ).join('');
            // Klick-Handler
            listenEl.querySelectorAll('[data-gid]').forEach(el => {
                el.addEventListener('mousedown', e => { e.preventDefault(); anAuswahl(el.dataset.gid, el.dataset.name); });
            });
        }
        listenEl.style.display = 'block';
    }

    window.lamAsanaProjektFiltern = function() {
        rendereListe(projekte, $('lam-asana-projekt-liste'), $('lam-asana-projekt-suche'),
                     $('lam-asana-projekt'), $('lam-asana-projekt-aktuell'),
                     (gid, name) => {
                         $('lam-asana-projekt').value = gid;
                         $('lam-asana-projekt-suche').value = name;
                         $('lam-asana-projekt-liste').style.display = 'none';
                         window.lamAsanaLadeSections();
                     });
    };

    window.lamAsanaSectionFiltern = function() {
        rendereListe(sections, $('lam-asana-section-liste'), $('lam-asana-section-suche'),
                     $('lam-asana-section'), $('lam-asana-section-aktuell'),
                     (gid, name) => {
                         $('lam-asana-section').value = gid;
                         $('lam-asana-section-suche').value = name;
                         $('lam-asana-section-liste').style.display = 'none';
                     });
    };

    window.lamAsanaOeffneModal = async function() {
        zeigeFehler('');
        $('lam-asana-modal').style.display = 'flex';
        $('lam-asana-laedt').style.display = 'block';
        // Reset
        $('lam-asana-projekt-suche').value = '';
        $('lam-asana-projekt').value = aktuelleProjektGid;
        $('lam-asana-section-suche').value = '';
        $('lam-asana-section').value = aktuelleSectionGid;
        $('lam-asana-section-wrap').style.display = 'none';
        try {
            const r = await fetch('/api/v1/admin/projektplanner/asana/projects', { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Projekte konnten nicht geladen werden');
            projekte = j.data.projects || j.data || [];
            if (aktuelleProjektGid) {
                const akt = projekte.find(p => p.gid === aktuelleProjektGid);
                if (akt) {
                    $('lam-asana-projekt-suche').value = akt.name;
                    await window.lamAsanaLadeSections();
                }
            }
        } catch (e) { zeigeFehler('Laden fehlgeschlagen: ' + e.message); }
        $('lam-asana-laedt').style.display = 'none';
    };

    window.lamAsanaLadeSections = async function() {
        const projektGid = $('lam-asana-projekt').value;
        const wrap = $('lam-asana-section-wrap');
        if (!projektGid) { wrap.style.display = 'none'; sections = []; return; }
        wrap.style.display = 'block';
        $('lam-asana-laedt').style.display = 'block';
        try {
            const r = await fetch('/api/v1/admin/projektplanner/asana/sections?project_gid=' + encodeURIComponent(projektGid), { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            sections = j.data.sections || [];
            // Aktuelle Section vorbelegen wenn im neuen Projekt vorhanden
            const aktSection = sections.find(s => s.gid === aktuelleSectionGid);
            if (aktSection) {
                $('lam-asana-section').value = aktSection.gid;
                $('lam-asana-section-suche').value = aktSection.name;
            } else {
                $('lam-asana-section').value = '';
                $('lam-asana-section-suche').value = '';
            }
        } catch (e) { zeigeFehler('Sections-Fehler: ' + e.message); }
        $('lam-asana-laedt').style.display = 'none';
    };

    window.lamAsanaSpeichern = async function() {
        const btn = $('lam-asana-save');
        btn.disabled = true; const original = btn.textContent; btn.textContent = '…';
        zeigeFehler('');
        try {
            const projektGid = $('lam-asana-projekt').value;
            const sectionGid = $('lam-asana-section') ? $('lam-asana-section').value : '';
            const projekt = projekte.find(p => p.gid === projektGid);
            const section = sections.find(s => s.gid === sectionGid);
            const r = await fetch('/api/v1/lam/asana-kunde-konfig', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: LAM_CUSTOMER_ID,
                    asana_projekt_gid: projektGid || null,
                    asana_projekt_name: projekt ? projekt.name : null,
                    asana_section_gid: sectionGid || null,
                    asana_section_name: section ? section.name : null,
                }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            $('lam-asana-modal').style.display = 'none';
            ladeAktuelleKonfig();
        } catch (e) { zeigeFehler('Speichern fehlgeschlagen: ' + e.message); }
        btn.disabled = false; btn.textContent = original;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ladeAktuelleKonfig);
    } else {
        ladeAktuelleKonfig();
    }
})();
</script>
<script>
function waitForApp(fn) {
    if (window.App && typeof window.App.get === 'function') { fn(); return; }
    const i = setInterval(() => {
        if (window.App && typeof window.App.get === 'function') { clearInterval(i); fn(); }
    }, 50);
}

async function syncAsana() {
    const btn = document.getElementById('sync-btn');
    const status = document.getElementById('sync-status');
    btn.disabled = true;
    status.textContent = 'Job wird in Queue gestellt...';
    try {
        const r = await App.post('/admin/customers/<?= $customerId ?>/asana-sync', {});
        if (r.success) {
            status.textContent = 'Sync-Job #' + (r.data.job_id || '?') + ' eingereiht. Pruefe in 1-2 Min Status.';
            status.style.color = 'var(--emerald-600)';
            App.showNotification('Sync gestartet', 'success');
            // Polling auf Status
            setTimeout(checkSyncStatus, 5000);
        } else {
            status.textContent = r.message || 'Fehler';
            status.style.color = 'var(--rose-600)';
        }
    } catch (e) {
        status.textContent = e.message || 'Verbindungsfehler';
        status.style.color = 'var(--rose-600)';
    }
    btn.disabled = false;
}

async function checkSyncStatus() {
    try {
        const r = await App.get('/admin/customers/<?= $customerId ?>/asana-status');
        if (r.success && r.data.last_job) {
            const j = r.data.last_job;
            const status = document.getElementById('sync-status');
            if (j.status === 'completed') {
                status.textContent = 'Sync abgeschlossen — Seite neu laden fuer Stats';
                status.style.color = 'var(--emerald-600)';
            } else if (j.status === 'failed') {
                status.textContent = 'Fehlgeschlagen: ' + (j.error_message || '');
                status.style.color = 'var(--rose-600)';
            } else {
                status.textContent = 'Status: ' + j.status + ' ...';
                setTimeout(checkSyncStatus, 5000);
            }
        }
    } catch (e) {}
}

// ===== Asana-Konfig-Modal =====
const customerId = <?= $customerId ?>;
const customerName = <?= json_encode($customer['name'] ?? '') ?>;
const customerSlug = <?= json_encode($customer['slug'] ?? '') ?>;
const customerAbbr = <?= json_encode($customer['abbreviation'] ?? '') ?>;

/** Kunde komplett löschen — mit Live-Dependency-Check + zweistufiger Bestätigung. */
async function csCustomerDelete() {
    // 1) Dependencies live abfragen
    let info = null;
    try {
        const r = await fetch('/api/v1/admin/customers/' + customerId + '/dependencies');
        const j = await r.json();
        if (j.success) info = j.data;
    } catch (e) {}

    let warnung = '';
    if (info) {
        const items = Object.entries(info.counts || {}).filter(([k, v]) => v > 0);
        if (items.length > 0) {
            warnung = '\n\n⚠ Folgendes hängt am Kunden:\n' +
                items.map(([k, v]) => '  • ' + v + 'x ' + k).join('\n') +
                '\n\nBeim Löschen werden Referenzen entweder genullt (Pläne, Knowledge, …) oder mit-gelöscht (User-Zuordnungen, Junctions).\nDer Löschvorgang ist sofort und endgültig.';
        }
    }

    if (!confirm('Kunde „' + customerName + '" wirklich löschen?' + warnung)) return;
    const eingabe = prompt('Zur Sicherheit: Tippe den Kundennamen „' + customerName + '" exakt ein, um zu bestätigen:');
    if (eingabe === null) return;
    if (eingabe.trim() !== customerName) { alert('Name stimmt nicht überein — Löschvorgang abgebrochen.'); return; }

    try {
        const r = await fetch('/api/v1/admin/customers/' + customerId, { method: 'DELETE' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        alert('Kunde „' + customerName + '" wurde gelöscht.');
        window.location.href = '/admin/customers';
    } catch (e) {
        alert('Fehler beim Löschen: ' + e.message);
    }
}
let acProjects = [];
let acSelected = new Set(<?= json_encode($projectGids) ?>);
let acShowSelectedOnly = false;

window.openAsanaConfig = async function() {
    document.getElementById('ac-modal').style.display = 'flex';
    const state = document.getElementById('ac-state');
    const list = document.getElementById('ac-projects');
    state.textContent = 'Lade Asana-Projekte...';
    list.innerHTML = '';

    try {
        const cfg = await App.get('/admin/customers/' + customerId + '/asana-config');
        if (cfg.success) {
            acSelected = new Set((cfg.data.project_gids || []).map(String));
            document.getElementById('ac-sync-enabled').checked = !!cfg.data.sync_enabled;
            document.getElementById('ac-sync-interval').value = cfg.data.sync_interval_hours || 72;
        }

        const r = await App.get('/admin/asana-projects');
        if (!r.success) {
            state.innerHTML = '<span style="color:var(--rose-600);">' + (r.message || 'Asana nicht erreichbar') +
                '</span> &mdash; <a href="/admin/settings" target="_blank">in Einstellungen einrichten</a>';
            return;
        }
        acProjects = r.data?.projects || [];
        if (!acProjects.length) {
            state.innerHTML = '<span>Keine Projekte gefunden.</span>';
            return;
        }

        // Match-Score pro Projekt: vergleicht Project-Name mit Customer-Name/Slug/Abbr
        const needles = [customerName, customerSlug, customerAbbr]
            .filter(Boolean)
            .map(s => String(s).toLowerCase().trim())
            .filter(s => s.length >= 2);

        acProjects.forEach(p => {
            const pname = (p.name || '').toLowerCase();
            let score = 0;
            for (const n of needles) {
                if (!n) continue;
                if (pname === n) score = Math.max(score, 100);
                else if (pname.includes(n)) score = Math.max(score, 50);
                else {
                    // Word-Match (z.B. "Wittekind" als Wort im Project-Namen)
                    const re = new RegExp('\\b' + n.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&') + '\\b', 'i');
                    if (re.test(p.name || '')) score = Math.max(score, 70);
                }
            }
            p._matchScore = score;
        });

        // Alphabetisch sortieren (Sortierung wird im Render zusätzlich Match-priorisiert)
        acProjects.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de', { sensitivity: 'base' }));

        const matches = acProjects.filter(p => p._matchScore > 0).length;
        const selCount = acProjects.filter(p => acSelected.has(String(p.gid))).length;
        state.innerHTML = `<strong>${acProjects.length}</strong> Projekte verfügbar` +
            (matches > 0 ? `, <strong style="color:var(--thoxan-700);">${matches}</strong> Vorschläge zu „${esc(customerName)}"` : '') +
            (selCount > 0 ? `, <strong style="color:var(--emerald-600);">${selCount}</strong> ausgewählt` : '');

        acRenderList();
    } catch (e) {
        state.innerHTML = '<span style="color:var(--rose-600);">Fehler: ' + esc(e.message || '') + '</span>';
    }
};

function acRenderList() {
    const list = document.getElementById('ac-projects');
    const search = (document.getElementById('ac-search')?.value || '').toLowerCase().trim();

    let filtered = acProjects.filter(p => {
        if (acShowSelectedOnly && !acSelected.has(String(p.gid))) return false;
        if (search && !(p.name || '').toLowerCase().includes(search)) return false;
        return true;
    });

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size: var(--d-fs-sm);">Keine Treffer</div>';
        return;
    }

    // Aufteilen in Vorschläge + Rest, beide alphabetisch
    const suggested = filtered.filter(p => (p._matchScore || 0) > 0)
        .sort((a, b) => (b._matchScore - a._matchScore) || (a.name || '').localeCompare(b.name || '', 'de'));
    const others = filtered.filter(p => !(p._matchScore > 0));

    let html = '';
    if (suggested.length > 0) {
        html += `<div style="font-size: var(--d-fs-xs);font-weight:700;color:var(--thoxan-700);text-transform:uppercase;letter-spacing:0.5px;padding:0.6rem 0.75rem 0.3rem;background:var(--thoxan-100);">
            Vorschläge zu „${esc(customerName)}"
        </div>`;
        html += suggested.map(p => acProjectRow(p, true)).join('');
    }
    if (others.length > 0) {
        if (suggested.length > 0) {
            html += `<div style="font-size: var(--d-fs-xs);font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding:0.6rem 0.75rem 0.3rem;background:#f8fafc;">
                Alle weiteren (${others.length})
            </div>`;
        }
        html += others.map(p => acProjectRow(p, false)).join('');
    }
    list.innerHTML = html;
}

function acProjectRow(p, isMatch) {
    const sel = acSelected.has(String(p.gid));
    const meta = [
        p.team?.name ? esc(p.team.name) : '',
        p.archived ? '<span style="color:var(--rose-600);">archiviert</span>' : '',
        p.modified_at ? 'Geändert: ' + p.modified_at.substring(0, 10) : '',
    ].filter(Boolean).join(' · ');
    return `
        <label class="ac-project ${sel ? 'selected' : ''}">
            <input type="checkbox" value="${p.gid}" ${sel ? 'checked' : ''} onchange="acToggle(this, '${p.gid}')">
            <div class="ac-project-info">
                <div class="ac-project-name" style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                    ${esc(p.name || '?')}
                    ${isMatch ? '<span style="background:var(--thoxan-700);color:#fff;font-size: var(--d-fs-xs);padding:1px 6px;border-radius:8px;font-weight:700;">Match</span>' : ''}
                </div>
                <div class="ac-project-meta">${meta}</div>
            </div>
        </label>
    `;
}

window.acFilter = function() { acRenderList(); };

window.acToggleSelectedOnly = function() {
    acShowSelectedOnly = !acShowSelectedOnly;
    const btn = document.getElementById('ac-only-selected');
    if (acShowSelectedOnly) {
        btn.classList.remove('thx-btn-secondary');
        btn.classList.add('thx-btn-primary');
    } else {
        btn.classList.add('thx-btn-secondary');
        btn.classList.remove('thx-btn-primary');
    }
    acRenderList();
};

window.closeAsanaConfig = function() { document.getElementById('ac-modal').style.display = 'none'; };

window.acToggle = function(cb, gid) {
    const id = String(gid);
    if (cb.checked) acSelected.add(id);
    else acSelected.delete(id);
    cb.closest('.ac-project').classList.toggle('selected', cb.checked);

    // Counter aktualisieren
    const state = document.getElementById('ac-state');
    if (state && acProjects.length) {
        const matches = acProjects.filter(p => p._matchScore > 0).length;
        const selCount = acProjects.filter(p => acSelected.has(String(p.gid))).length;
        state.innerHTML = `<strong>${acProjects.length}</strong> Projekte verfügbar` +
            (matches > 0 ? `, <strong style="color:var(--thoxan-700);">${matches}</strong> Vorschläge zu „${esc(customerName)}"` : '') +
            (selCount > 0 ? `, <strong style="color:var(--emerald-600);">${selCount}</strong> ausgewählt` : '');
    }
};

window.saveAsanaConfig = async function() {
    const btn = document.getElementById('ac-save-btn');
    btn.disabled = true;
    btn.textContent = 'Speichere...';
    try {
        const r = await App.post('/admin/customers/' + customerId + '/asana-config', {
            project_gids: Array.from(acSelected),
            sync_enabled: document.getElementById('ac-sync-enabled').checked,
            sync_interval_hours: parseInt(document.getElementById('ac-sync-interval').value) || 72,
            trigger_sync: acSelected.size > 0,
        });
        if (r.success) {
            App.showNotification('Asana-Konfiguration gespeichert' + (r.data.sync_job_id ? ' — Sync-Job #' + r.data.sync_job_id + ' gestartet' : ''), 'success');
            closeAsanaConfig();
            setTimeout(() => location.reload(), 600);
        } else {
            App.showNotification(r.message || 'Fehler', 'error');
            btn.disabled = false;
            btn.textContent = 'Speichern & Sync starten';
        }
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false;
        btn.textContent = 'Speichern & Sync starten';
    }
};

// ===== Wissen Quick-Add =====
window.qaTab = function(t) {
    document.querySelectorAll('.qa-tab').forEach(b => b.classList.toggle('active', b.dataset.qa === t));
    ['url', 'file', 'text'].forEach(k => {
        document.getElementById('qa-' + k).style.display = k === t ? '' : 'none';
    });
};

window.qaSubmit = async function(src) {
    const status = document.getElementById('qa-status');
    const btn = document.getElementById('qa-' + src + '-btn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>Verarbeite...';
    status.innerHTML = '<span style="color:#64748b;">KI verarbeitet die Quelle...</span>';

    try {
        let resp;
        if (src === 'url') {
            const url = document.getElementById('qa-url-input').value.trim();
            if (!url) throw new Error('URL eingeben');
            resp = await App.post('/admin/customers/' + customerId + '/knowledge-quickadd', { source: 'url', url });
        } else if (src === 'file') {
            const fileEl = document.getElementById('qa-file-input');
            if (!fileEl.files[0]) throw new Error('Datei waehlen');
            const fd = new FormData();
            fd.append('source', 'file');
            fd.append('file', fileEl.files[0]);
            const r = await fetch('/api/v1/admin/customers/' + customerId + '/knowledge-quickadd', {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-Token': App.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            });
            resp = await r.json();
        } else {
            const title = document.getElementById('qa-text-title').value.trim();
            const content = document.getElementById('qa-text-content').value.trim();
            if (content.length < 30) throw new Error('Mindestens 30 Zeichen Inhalt');
            resp = await App.post('/admin/customers/' + customerId + '/knowledge-quickadd', { source: 'text', title, content });
        }

        if (!resp.success) throw new Error(resp.message || 'Fehler');
        const d = resp.data;
        const dup = d.duplicate ? ' (war bereits vorhanden)' : '';
        status.innerHTML = `<span style="color:#047857;">
            ✓ <strong>${esc(d.title || '-')}</strong>${dup} —
            <a href="/wissen/${d.document_id}">oeffnen</a>
        </span>`;
        // Inputs leeren
        if (src === 'url') document.getElementById('qa-url-input').value = '';
        else if (src === 'file') document.getElementById('qa-file-input').value = '';
        else { document.getElementById('qa-text-title').value = ''; document.getElementById('qa-text-content').value = ''; }
        // Liste reloaden
        setTimeout(() => location.reload(), 1500);
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-600);">' + esc(e.message || 'Fehler') + '</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

function esc(s) {
    const div = document.createElement('div');
    div.textContent = s ?? '';
    return div.innerHTML;
}

// ===== Wissen — vollstaendige Liste mit Inline-Detail =====
let kbDocs = [];

async function kbLoad() {
    const list = document.getElementById('kb-list');
    list.innerHTML = '<div class="cs-empty"><span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(0, 76, 155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>Lade Wissen...</div>';
    try {
        const r = await App.get('/knowledge/documents?customer_id=' + customerId + '&limit=200');
        if (!r.success) throw new Error(r.message || 'Fehler');
        kbDocs = r.data.items || [];
        document.getElementById('kb-count').textContent = kbDocs.length + ' Eintraege';
        kbRender();
    } catch (e) {
        list.innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">Fehler: ' + esc(e.message || '') + '</div>';
    }
}

function kbRender() {
    const list = document.getElementById('kb-list');
    const search = (document.getElementById('kb-search')?.value || '').toLowerCase().trim();
    const sourceFilter = document.getElementById('kb-source-filter')?.value || '';
    const filtered = kbDocs.filter(d => {
        if (sourceFilter && d.source_type !== sourceFilter) return false;
        if (search) {
            const hay = ((d.title || '') + ' ' + (d.description || '')).toLowerCase();
            if (!hay.includes(search)) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        list.innerHTML = '<div class="cs-empty">' + (kbDocs.length === 0
            ? 'Noch kein Wissen — nutze die Quick-Add-Box oben.'
            : 'Keine Eintraege passen zum Filter.') + '</div>';
        return;
    }

    const iconMap = {upload:'description', url:'link', text:'edit_note', chat:'chat', asana:'task_alt'};
    const expanded = window.kbExpanded === true;
    const LIMIT = 5;
    const visible = expanded ? filtered : filtered.slice(0, LIMIT);
    list.innerHTML = visible.map(d => `
        <div class="cs-doc-row" onclick="kbOpen(${d.id})" data-id="${d.id}">
            <span class="material-symbols-rounded cs-doc-icon">${iconMap[d.source_type] || 'article'}</span>
            <div class="cs-doc-title" style="flex:1;min-width:0;">
                <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(d.title)}</strong>
                ${d.description ? '<small style="color:#64748b;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(d.description.substring(0, 100)) + '</small>' : ''}
            </div>
            ${d.category ? '<span class="cs-doc-type" style="background:#e6f0fa;color:var(--thoxan-900);">' + esc(d.category) + '</span>' : ''}
            <span class="cs-doc-type cs-doc-type-${d.source_type}">${d.source_type}</span>
            <span style="font-size: var(--d-fs-xs);color:#94a3b8;white-space:nowrap;">${formatDate(d.updated_at || d.created_at)}</span>
            <div class="cs-doc-actions" onclick="event.stopPropagation()">
                <button class="cs-doc-action-btn" onclick="kbReprocess(${d.id})" title="Neu verarbeiten">
                    <span class="material-symbols-rounded">refresh</span>
                </button>
                <button class="cs-doc-action-btn danger" onclick="kbDelete(${d.id})" title="Loeschen">
                    <span class="material-symbols-rounded">delete</span>
                </button>
            </div>
        </div>
    `).join('') + (filtered.length > LIMIT ? `
        <button class="cs-kb-toggle" onclick="kbToggleExpand()">
            ${expanded
                ? '<span class="material-symbols-rounded">expand_less</span> Weniger zeigen'
                : `<span class="material-symbols-rounded">expand_more</span> Alle ${filtered.length} Eintraege zeigen (${filtered.length - LIMIT} weitere)`}
        </button>
    ` : '');
}

window.kbToggleExpand = function() {
    window.kbExpanded = !window.kbExpanded;
    kbRender();
};

function formatDate(dt) {
    if (!dt) return '-';
    const d = new Date(dt);
    return d.toLocaleDateString('de-DE', {day:'2-digit', month:'2-digit'});
}

window.kbApplyFilter = function() { kbRender(); };

window.kbOpen = async function(id) {
    document.getElementById('kb-drawer-overlay').style.display = 'block';
    document.getElementById('kb-drawer').style.display = 'block';
    const c = document.getElementById('kb-drawer-content');
    c.innerHTML = '<div class="cs-empty"><span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(0, 76, 155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>Lade Detail...</div>';
    try {
        const r = await App.get('/knowledge/documents/' + id);
        if (!r.success) throw new Error(r.message || 'Fehler');
        const doc = r.data;
        kbRenderDrawer(doc);
    } catch (e) {
        c.innerHTML = '<div style="color:var(--rose-600);">' + esc(e.message || '') + '</div>';
    }
};

window.closeKbDrawer = function() {
    document.getElementById('kb-drawer-overlay').style.display = 'none';
    document.getElementById('kb-drawer').style.display = 'none';
};

function kbRenderDrawer(doc) {
    const c = document.getElementById('kb-drawer-content');
    let tags = doc.tags;
    if (typeof tags === 'string') { try { tags = JSON.parse(tags); } catch(e) { tags = []; } }
    if (!Array.isArray(tags)) tags = [];

    const iconMap = {upload:'description', url:'link', text:'edit_note', chat:'chat', asana:'task_alt'};
    const sourceLabel = {upload:'Datei', url:'URL', text:'Text', chat:'Chat', asana:'Asana'}[doc.source_type] || doc.source_type;
    const chunksCount = (doc.chunks || []).length;
    const entitiesCount = (doc.entities || []).length;

    c.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;">
                    <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:22px;">${iconMap[doc.source_type] || 'article'}</span>
                    <span style="font-size: var(--d-fs-xs);color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">${esc(sourceLabel)}</span>
                </div>
                <h2>${esc(doc.title)}</h2>
                ${doc.source_ref ? '<small style="color:#64748b;">Quelle: ' + (doc.source_type === 'url' ? '<a href="' + esc(doc.source_ref) + '" target="_blank">' + esc(doc.source_ref) + '</a>' : esc(doc.source_ref)) + '</small>' : ''}
            </div>
            <button class="btn-icon" onclick="closeKbDrawer()" style="background:none;border:none;cursor:pointer;font-size: var(--d-fs-2xl);color:#64748b;">&times;</button>
        </div>

        <div class="kb-stat-row" style="margin-bottom:1rem;">
            <span><strong>${chunksCount}</strong> Chunks</span>
            <span><strong>${entitiesCount}</strong> Entities</span>
            <span>Erstellt: ${formatDate(doc.created_at)}</span>
            ${doc.updated_at ? '<span>Geaendert: ' + formatDate(doc.updated_at) + '</span>' : ''}
        </div>

        <div class="kb-drawer-section">
            <div class="kb-drawer-label">Titel</div>
            <input type="text" id="kbd-title" value="${esc(doc.title)}">
        </div>

        <div class="kb-drawer-section">
            <div class="kb-drawer-label">Beschreibung</div>
            <textarea id="kbd-description">${esc(doc.description || '')}</textarea>
        </div>

        <div class="kb-drawer-section">
            <div class="kb-drawer-label">Kategorie</div>
            <input type="text" id="kbd-category" value="${esc(doc.category || '')}">
        </div>

        <div class="kb-drawer-section">
            <div class="kb-drawer-label">Tags <small style="font-weight:normal;text-transform:none;">(komma-getrennt)</small></div>
            <input type="text" id="kbd-tags" value="${esc(tags.join(', '))}">
            ${tags.length ? '<div style="margin-top:0.4rem;">' + tags.map(t => '<span class="kb-tag-chip">#' + esc(t) + '</span>').join('') + '</div>' : ''}
        </div>

        <div class="kb-drawer-section">
            <div class="kb-drawer-label">Original-Inhalt <small style="font-weight:normal;text-transform:none;">(bei Speichern wird neu verarbeitet)</small></div>
            <textarea id="kbd-content" rows="10" style="font-family:monospace;font-size: var(--d-fs-sm);">${esc(doc.original_content || '')}</textarea>
        </div>

        <div style="display:flex;gap:0.4rem;margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;flex-wrap:wrap;">
            <button class="thx-btn thx-btn-primary" onclick="kbSave(${doc.id})" id="kbd-save">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">save</span>
                Speichern
            </button>
            <button class="thx-btn thx-btn-secondary" onclick="kbReprocess(${doc.id})">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">refresh</span>
                Neu verarbeiten
            </button>
            <a href="/wissen/${doc.id}/graph" target="_blank" class="thx-btn thx-btn-secondary">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">hub</span>
                Graph
            </a>
            <div style="flex:1;"></div>
            <button class="thx-btn thx-btn-secondary" style="color:var(--rose-600);" onclick="kbDelete(${doc.id}, true)">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                Loeschen
            </button>
        </div>
    `;
}

window.kbSave = async function(id) {
    const btn = document.getElementById('kbd-save');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = 'Speichere...';

    const tags = document.getElementById('kbd-tags').value
        .split(',').map(t => t.trim()).filter(Boolean);
    const content = document.getElementById('kbd-content').value.trim();

    try {
        // Reprocess-Endpoint nutzt content + metadata gleichzeitig
        const r = await App.post('/knowledge/documents/' + id + '/reprocess', {
            title: document.getElementById('kbd-title').value.trim(),
            description: document.getElementById('kbd-description').value.trim(),
            category: document.getElementById('kbd-category').value.trim(),
            tags: tags,
            content: content,
        });
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification(r.data.reanalyzed ? 'Neu verarbeitet' : 'Aenderungen gespeichert', 'success');
        closeKbDrawer();
        kbLoad();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

window.kbReprocess = async function(id) {
    if (!confirm('Dokument neu verarbeiten? Chunks/Embeddings werden komplett neu erstellt.')) return;
    try {
        const r = await App.post('/knowledge/documents/' + id + '/reprocess', {});
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification('Neu verarbeitet', 'success');
        if (document.getElementById('kb-drawer').style.display === 'block') closeKbDrawer();
        kbLoad();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.kbDelete = async function(id, fromDrawer = false) {
    if (!confirm('Dokument wirklich loeschen? Chunks, Embeddings und Entities werden mitentfernt.')) return;
    try {
        await App.delete('/knowledge/documents/' + id);
        App.showNotification('Geloescht', 'success');
        if (fromDrawer) closeKbDrawer();
        kbLoad();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

// Quick-Add: nach Erfolg statt full reload nur kbLoad
const origQaSubmit = window.qaSubmit;
window.qaSubmit = async function(src) {
    const status = document.getElementById('qa-status');
    const btn = document.getElementById('qa-' + src + '-btn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>Verarbeite...';
    status.innerHTML = '<span style="color:#64748b;">KI verarbeitet die Quelle...</span>';

    try {
        let resp;
        if (src === 'url') {
            const url = document.getElementById('qa-url-input').value.trim();
            if (!url) throw new Error('URL eingeben');
            resp = await App.post('/admin/customers/' + customerId + '/knowledge-quickadd', { source: 'url', url });
        } else if (src === 'file') {
            const fileEl = document.getElementById('qa-file-input');
            if (!fileEl.files[0]) throw new Error('Datei waehlen');
            const fd = new FormData();
            fd.append('source', 'file');
            fd.append('file', fileEl.files[0]);
            const r = await fetch('/api/v1/admin/customers/' + customerId + '/knowledge-quickadd', {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-Token': App.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            });
            resp = await r.json();
        } else {
            const title = document.getElementById('qa-text-title').value.trim();
            const content = document.getElementById('qa-text-content').value.trim();
            if (content.length < 30) throw new Error('Mindestens 30 Zeichen Inhalt');
            resp = await App.post('/admin/customers/' + customerId + '/knowledge-quickadd', { source: 'text', title, content });
        }

        if (!resp.success) throw new Error(resp.message || 'Fehler');
        const d = resp.data;
        const dup = d.duplicate ? ' (war bereits vorhanden)' : '';
        status.innerHTML = '<span style="color:#047857;">✓ <strong>' + esc(d.title || '-') + '</strong>' + dup + ' angelegt</span>';
        if (src === 'url') document.getElementById('qa-url-input').value = '';
        else if (src === 'file') document.getElementById('qa-file-input').value = '';
        else { document.getElementById('qa-text-title').value = ''; document.getElementById('qa-text-content').value = ''; }
        // Liste live nachladen
        kbLoad();
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-600);">' + esc(e.message || 'Fehler') + '</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

// ===== Website-Crawler (mehrere Seiten via SSE) =====
window.qaSubmitWebsite = async function() {
    const btn = document.getElementById('qa-web-btn');
    const url = document.getElementById('qa-web-url').value.trim();
    const maxPages = parseInt(document.getElementById('qa-web-pages').value) || 15;
    const maxDepth = parseInt(document.getElementById('qa-web-depth').value) || 2;
    const status = document.getElementById('qa-status');
    const progressBox = document.getElementById('qa-web-progress');
    const bar = document.getElementById('qa-web-bar');
    const counter = document.getElementById('qa-web-counter');
    const log = document.getElementById('qa-web-log');

    if (!url) { status.innerHTML = '<span style="color:var(--rose-600);">Bitte Start-URL eingeben</span>'; return; }

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>Crawl laeuft...';
    status.innerHTML = '';
    progressBox.style.display = 'block';
    bar.style.width = '0%';
    counter.textContent = '';
    log.innerHTML = '';

    const logLine = (text, color) => {
        const div = document.createElement('div');
        div.textContent = text;
        if (color) div.style.color = color;
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    };

    let created = 0, skipped = 0, failed = 0;

    try {
        const response = await fetch('/api/v1/knowledge/website', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ url, customer_id: customerId, max_pages: maxPages, max_depth: maxDepth })
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const parts = buffer.split('\n\n');
            buffer = parts.pop();

            for (const chunk of parts) {
                if (!chunk.trim()) continue;
                let event = 'message', data = {};
                for (const line of chunk.split('\n')) {
                    if (line.startsWith('event: ')) event = line.substring(7);
                    else if (line.startsWith('data: ')) {
                        try { data = JSON.parse(line.substring(6)); } catch (e) {}
                    }
                }

                if (event === 'start') {
                    logLine('Start: ' + data.url + ' (max ' + data.max_pages + ' Seiten, Tiefe ' + data.max_depth + ')');
                } else if (event === 'fetching') {
                    counter.textContent = data.done + '/' + data.total + ' gecrawlt';
                } else if (event === 'fetched') {
                    logLine('✓ ' + data.url + ' (' + data.chars + ' Zeichen)');
                    bar.style.width = Math.min(50, (data.done / (data.total || 1)) * 50) + '%';
                } else if (event === 'crawled') {
                    logLine('── Crawl fertig: ' + data.count + ' Seiten gefunden. Analysiere…', '#059669');
                } else if (event === 'processing') {
                    counter.textContent = data.index + '/' + data.total + ' analysiert';
                    bar.style.width = (50 + (data.index / data.total) * 50) + '%';
                } else if (event === 'created') {
                    created++;
                    logLine('+ ' + data.title + ' (' + data.chunks + ' Chunks)', '#059669');
                } else if (event === 'skipped') {
                    skipped++;
                    logLine('~ ' + data.url + ' (' + data.reason + ')', '#94a3b8');
                } else if (event === 'failed') {
                    failed++;
                    logLine('✗ ' + data.url + ': ' + data.error, 'var(--rose-600)');
                } else if (event === 'done') {
                    bar.style.width = '100%';
                    status.innerHTML = '<span style="color:#047857;">✓ <strong>' + data.created + '</strong> neu, <strong>' +
                        data.skipped + '</strong> uebersprungen' + (data.failed > 0 ? ', <strong style="color:var(--rose-600);">' + data.failed + '</strong> fehlgeschlagen' : '') + '</span>';
                    document.getElementById('qa-web-url').value = '';
                    kbLoad();
                } else if (event === 'error') {
                    throw new Error(data.message || 'Unbekannter Fehler');
                }
            }
        }
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-600);">Fehler: ' + esc(e.message || '') + '</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

// Per-Card-Edit-Mode (Stift pro Card). Default: alle Cards schreibgeschützt.
function sbApplyMode() {
    // System-Cards-Inhalte (Wissen-Suche, Asana-Form-Inputs) bleiben editierbar — die haben eigene UX.
    // Für User-Cards (ohne data-system-key) gilt: editing-Class steuert readonly via JS-Toggle (sbToggleCardEdit).
    document.querySelectorAll('.sb-card-user').forEach(card => {
        const isEditing = card.classList.contains('editing');
        sbApplyCardEditState(card, isEditing);
    });
}

function sbApplyCardEditState(card, isEditing) {
    card.querySelectorAll('input:not([type="file"]):not([type="checkbox"]):not([type="radio"]):not([type="color"])').forEach(el => el.readOnly = !isEditing);
    card.querySelectorAll('textarea').forEach(el => el.readOnly = !isEditing);
    // Titel bleibt IMMER editierbar — Karten-Beschriftung gehoert nicht zur
    // Inhalts-Edit-Logik, soll jederzeit klickbar+anpassbar sein.
    card.querySelectorAll('[contenteditable]:not(.sb-card-title)').forEach(el => el.contentEditable = isEditing ? 'true' : 'false');
    card.querySelectorAll('.sb-card-title[contenteditable]').forEach(el => el.contentEditable = 'plaintext-only');
    // Karten sind IMMER draggable — Reordering per Drag aus dem Card-Head, ohne erst
    // in den Edit-Modus zu muessen. Der sbDragStart filtert eh auf Header-Bereich.
    card.draggable = true;
    // Edit-Btn Icon umschalten
    // Edit-Btn ist nur im Edit-Modus sichtbar (per CSS) und immer ein Check
    const btn = card.querySelector('.sb-card-edit-btn');
    if (btn) {
        const icon = btn.querySelector('.material-symbols-rounded');
        if (icon) icon.textContent = isEditing ? 'check' : 'edit';
        btn.title = isEditing ? 'Bearbeitung abschliessen' : 'Bearbeiten';
        btn.classList.toggle('is-active', isEditing);
    }
}

window.sbToggleCardEdit = function(cardId, forceState) {
    const card = document.querySelector(`.sb-card[data-card-id="${cardId}"]`);
    if (!card) return;
    const newState = (typeof forceState === 'boolean') ? forceState : !card.classList.contains('editing');
    if (card.classList.contains('editing') === newState) return;
    card.classList.toggle('editing', newState);
    sbApplyCardEditState(card, newState);
    if (newState) {
        setTimeout(() => {
            const focusable = card.querySelector('.sb-card-body input:not([readonly]), .sb-card-body textarea:not([readonly]), .sb-card-body [contenteditable="true"]');
            focusable?.focus?.();
        }, 50);
    } else {
        // Beim Verlassen: aktuelles Fokus-Feld blurren, damit onblur-Handler speichern
        const active = card.querySelector(':focus');
        active?.blur?.();
        // Accounts-Card: Eingaben sofort sichern und Ansicht neu aufbauen,
        // damit die klickbare Ansicht den aktuellen Stand zeigt.
        const c = sbCards.find(x => x.id == cardId);
        if (c && c.type === 'accounts') {
            sbCommitAccounts(cardId);
            sbRerenderCardBody(cardId);
        } else if (c && c.type === 'links') {
            sbCommitLinks(cardId);
            sbRerenderCardBody(cardId);
        }
    }
};

/* === Klick-in-Edit ===
   - Klick irgendwo in den Card-Body einer User-Card (kein System-Card) → Edit-Modus.
   - Klick ausserhalb einer Edit-Card → speichern & verlassen.
   - Esc → speichern & verlassen.
   Action-Buttons und der Header sind ausgenommen. */
document.addEventListener('click', (e) => {
    const card = e.target.closest('.sb-card-user');
    if (!card) return;
    if (card.dataset.systemKey) return;
    if (card.classList.contains('editing')) return;
    if (e.target.closest('.sb-card-actions, .sb-card-head')) return;
    // Klick auf Links/Buttons (z.B. Konto-Link öffnen, ID kopieren, „+ hinzufügen")
    // soll die jeweilige Aktion ausführen, NICHT den Edit-Modus starten.
    if (e.target.closest('a, button')) return;
    // Karten mit eigenem Ansicht/Bearbeiten-Umschalter (Stift): NICHT per Body-Klick
    // in den Edit-Modus — nur ueber den Stift. Sonst oeffnet ein Titel-Klick die Bearbeitung.
    if (card.dataset.cardType === 'accounts' || card.dataset.cardType === 'links') return;
    const body = e.target.closest('.sb-card-body');
    if (!body) return;
    const cardId = card.dataset.cardId;
    const target = e.target;
    sbToggleCardEdit(cardId, true);
    // Fokus auf das tatsaechlich angeklickte Element refokussieren, falls editierbar
    setTimeout(() => {
        if (target.matches('input:not([type="file"]), textarea, [contenteditable]')) {
            try { target.focus(); } catch (_) {}
        }
    }, 60);
});

document.addEventListener('mousedown', (e) => {
    document.querySelectorAll('.sb-card-user.editing').forEach(card => {
        if (card.contains(e.target)) return;
        // Popovers/Modals/Menues, die zur Card gehoeren, nicht als „aussen" werten
        if (e.target.closest('.sb-size-pop, .cs-logo-menu, .sb-tag-suggest, .sb-history-modal, .sb-card-modal, .sb-image-zoom, .sb-emoji-pop')) return;
        sbToggleCardEdit(card.dataset.cardId, false);
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.sb-card-user.editing').forEach(card => {
        sbToggleCardEdit(card.dataset.cardId, false);
    });
});

// Initial load — auf App warten. sbLoadCards rendert auch die System-Cards
// und ruft danach sbInitProfileEdit() für die Profil-Inputs auf. kbLoad() bleibt
// für die Wissens-Liste innerhalb der Wissen-System-Card.
waitForApp(() => { sbLoadCards().then(() => { kbLoad(); sbLoadWebsite(); sbLoadSiteMonitor(); sbLoadRegeln(); }); });

// ===== Website-System-Card =====
let sbWebsiteState = null;
let sbWebsitePoll = null;

async function sbLoadWebsite() {
    const box = document.getElementById('cs-website-content');
    if (!box) return;
    try {
        const r = await App.get('/admin/customers/' + customerId + '/website-crawl');
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbWebsiteState = r.data;
        sbRenderWebsite();
        // Bei laufendem Job: weiter pollen
        const lj = sbWebsiteState.last_job;
        if (lj && (lj.status === 'pending' || lj.status === 'processing')) {
            clearInterval(sbWebsitePoll);
            sbWebsitePoll = setInterval(sbLoadWebsite, 4000);
        } else {
            clearInterval(sbWebsitePoll);
        }
    } catch (e) {
        box.innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">' + esc(e.message || '') + '</div>';
    }
}

function sbRenderWebsite() {
    const d = sbWebsiteState || {};
    const box = document.getElementById('cs-website-content');
    if (!box) return;
    const start = d.start_url || '';
    const lastSync = d.last_sync_at ? new Date(d.last_sync_at.replace(' ', 'T')).toLocaleString('de-DE', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : 'Noch nie';
    const intervalDays = d.sync_interval_days || 60;
    const stats = d.last_stats || null;
    const lj = d.last_job || null;
    const isRunning = lj && (lj.status === 'pending' || lj.status === 'processing');

    if (!start) {
        const profileUrl = (d.profile_website || '').trim();
        if (profileUrl) {
            // Profil hat eine URL — Vorschlag zum Aktivieren
            box.innerHTML = `
                <div style="padding:0.8rem;background:#f0f9ff;border:1px dashed #bae6fd;border-radius:10px;">
                    <p style="margin:0 0 0.5rem;color:#0c4a6e;font-size: var(--d-fs-sm);">Profil-Website erkannt: <strong>${esc(profileUrl)}</strong></p>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbTriggerWebsite()">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">sync</span>
                            Diese Website crawlen
                        </button>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbOpenWebsiteConfig()">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">settings</span>
                            Andere URL / Optionen
                        </button>
                    </div>
                </div>
            `;
        } else {
            box.innerHTML = `
                <div style="padding:0.8rem;background:#f0f9ff;border:1px dashed #bae6fd;border-radius:10px;text-align:center;">
                    <p style="margin:0 0 0.5rem;color:#0c4a6e;font-size: var(--d-fs-sm);">Noch keine Website verbunden.</p>
                    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbOpenWebsiteConfig()">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">link</span>
                        Website verbinden
                    </button>
                </div>
            `;
        }
        return;
    }

    // Kompakte KPI-Kacheln im gleichen Stil wie Monitoring-Karte
    const tile = (label, val) => `
        <div style="background:var(--slate-50);padding:6px 8px;border-radius:6px;min-width:0;">
            <div style="font-size:9px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${label}</div>
            <div style="font-size:15px;font-weight:700;font-family:ui-monospace,monospace;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${val}</div>
        </div>`;
    const syncDisp = d.sync_enabled ? 'Auto · ' + intervalDays + 'T' : 'Aus';

    // Websites-Liste: Label + URL untereinander statt 2-Spalten-Grid (klappt in 1/3-Breite zusammen)
    const websitesHtml = (d.all_domains || []).map((dom, i) => `
        <div style="margin-bottom:6px;min-width:0;">
            ${dom.label ? `<div style="font-size:var(--d-fs-xs);color:#64748b;font-weight:500;margin-bottom:1px;display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                <span>${esc(dom.label)}</span>
                ${i === 0 && d.start_url_inherited ? `<span style="font-size:9px;color:#94a3b8;background:#f1f5f9;padding:1px 5px;border-radius:8px;">aus Profil</span>` : ''}
            </div>` : ''}
            <a href="${esc(dom.url)}" target="_blank" rel="noopener"
               style="color:var(--thoxan-700);text-decoration:none;font-size:var(--d-fs-sm);word-break:break-all;display:inline-block;line-height:1.3;">
               ${esc(dom.url)}
            </a>
        </div>
    `).join('');

    box.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(72px,1fr));gap:5px;margin-bottom:0.6rem;">
            ${tile('Seiten', d.docs_count || 0)}
            ${tile('Auto-Sync', syncDisp)}
        </div>

        <div style="margin-bottom:0.5rem;min-width:0;">${websitesHtml}</div>

        <div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:0.5rem;">
            <span style="color:var(--slate-700);">Letzter Sync:</span> ${esc(lastSync)}
        </div>

        ${stats ? `<div style="font-size:var(--d-fs-xs);color:var(--slate-600);margin-bottom:0.5rem;line-height:1.5;">
            <span style="color:#047857;">+${stats.created || 0}</span> ·
            <span style="color:#0369a1;">${stats.updated || 0}↻</span> ·
            <span style="color:#94a3b8;">${stats.unchanged || 0}=</span> ·
            <span style="color:var(--rose-700);">${stats.deleted || 0}✕</span>
            ${stats.failed ? ' · <span style="color:var(--rose-600);">' + stats.failed + ' Fehler</span>' : ''}
        </div>` : ''}

        ${isRunning ? `<div style="font-size:var(--d-fs-xs);color:var(--thoxan-700);margin-bottom:0.5rem;">
            <span style="display:inline-block;width:8px;height:8px;border:2px solid rgba(0,76,155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:4px;"></span>
            Job #${lj.id} läuft
        </div>` : ''}

        ${(lj && lj.status === 'failed') ? '<div style="font-size:var(--d-fs-xs);color:var(--rose-700);margin-bottom:0.5rem;word-break:break-word;">Fehler: ' + esc(lj.error_message || '') + '</div>' : ''}

        <div style="display:flex;gap:0.3rem;flex-wrap:wrap;border-top:1px solid var(--slate-100);padding-top:0.4rem;">
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbTriggerWebsite()" ${isRunning ? 'disabled' : ''}
                    style="flex:1;min-width:0;justify-content:center;">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">sync</span>
                <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${isRunning ? 'Läuft…' : 'Sync'}</span>
            </button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbOpenSitemap()" ${(d.docs_count || 0) === 0 ? 'disabled' : ''}
                    title="Sitemap (${d.docs_count || 0})">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">account_tree</span>
            </button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbOpenWebsiteConfig()" title="Konfigurieren">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">settings</span>
            </button>
        </div>
    `;
}

// ===== Sitemap-Modal =====
window.sbOpenSitemap = async function() {
    let overlay = document.getElementById('sb-sitemap-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-sitemap-overlay';
        overlay.className = 'sb-maximize-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseSitemap(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-maximize-card" style="max-width:880px;">
            <div class="sb-maximize-head">
                <div class="sb-card-icon" style="background:#0891b2;"><span class="material-symbols-rounded">account_tree</span></div>
                <strong style="flex:1;">Sitemap — gecrawlte Seiten</strong>
                <input type="text" class="sb-search-bypass" id="sb-sitemap-search" placeholder="URL oder Titel suchen…"
                       oninput="sbSitemapFilter(this.value)"
                       style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size: var(--d-fs-sm);min-width:200px;">
                <button class="sb-card-action" onclick="sbCloseSitemap()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="sb-maximize-body" id="sb-sitemap-body">
                <div style="text-align:center;color:#94a3b8;padding:2rem;">
                    <span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(8,145,178,0.3);border-top-color:#0891b2;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:8px;"></span>
                    Lade Sitemap…
                </div>
            </div>
        </div>
    `;
    overlay.classList.add('open');

    try {
        const r = await App.get('/admin/customers/' + customerId + '/website-crawl/sitemap');
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbSitemapState = { hosts: r.data.hosts || {}, total: r.data.total || 0 };
        sbRenderSitemap('');
    } catch (e) {
        document.getElementById('sb-sitemap-body').innerHTML = '<div style="color:var(--rose-600);padding:1rem;">' + esc(e.message || 'Fehler') + '</div>';
    }
};

let sbSitemapState = null;

window.sbSitemapFilter = function(query) {
    sbRenderSitemap((query || '').toLowerCase().trim());
};

// ===== Regeln-System-Card (KI-Schreibregeln, Kategorien mit Checkbox-Liste) =====
let sbRegelnState = null;

async function sbLoadRegeln() {
    const box = document.getElementById('cs-regeln-content');
    if (!box) return;
    try {
        const r = await App.get('/admin/customers/' + customerId + '/rules');
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbRegelnState = r.data;
        sbRenderRegeln();
    } catch (e) {
        box.innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">' + esc(e.message || '') + '</div>';
    }
}
window.sbLoadRegeln = sbLoadRegeln;

function sbRenderRegeln() {
    const d = sbRegelnState || { grouped: [], specific_count: 0, global_count: 0 };
    const box = document.getElementById('cs-regeln-content');
    if (!box) return;
    const cats = d.grouped || [];

    const headerHtml = `
        <div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:0.6rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span style="flex:1;">
                <strong style="color:var(--slate-700);">${d.specific_count}</strong> spezifisch für diesen Kunden
                · ${d.global_count} globale Regeln gelten zusätzlich
            </span>
            <button onclick="sbRegelnNeuOeffnen()" style="border:1px solid var(--thoxan-200);background:var(--thoxan-50);color:var(--thoxan-700);padding:3px 8px;border-radius:5px;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                <span class="material-symbols-rounded" style="font-size:13px;">add</span>
                Regel anlegen
            </button>
            <a href="/rules" style="color:var(--thoxan-700);font-size:11px;text-decoration:none;" title="Alle Regeln (auch globale) im Pool verwalten">Pool ↗</a>
        </div>
    `;

    if (!cats.length) {
        box.innerHTML = headerHtml + `
            <div style="padding:1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;text-align:center;color:var(--slate-600);font-size:var(--d-fs-sm);">
                <span class="material-symbols-rounded" style="font-size:28px;color:#94a3b8;display:block;margin-bottom:0.3rem;">rule</span>
                <div>Noch keine kundenspezifische Regel angelegt.</div>
                <div style="margin-top:0.5rem;font-size:var(--d-fs-xs);color:var(--slate-500);">
                    Beispiel: „Alle Wörter beginnen mit B" — gilt dann nur für diesen Kunden zusätzlich zu den ${d.global_count} globalen Regeln.
                </div>
            </div>
        `;
        return;
    }

    const catHtml = cats.map(c => {
        const rules = c.rules || [];
        const rowHtml = rules.map(r => {
            const content = (r.rule_content || '').trim();
            const contentShort = content.length > 200 ? content.substring(0, 200) + '…' : content;
            const inactive = !r.is_active;
            return `
            <div class="reg-row ${inactive ? 'is-disabled' : ''}" data-rule-id="${r.id}">
                <div class="reg-row-body" onclick="sbRegelEdit(${r.id})">
                    <div class="reg-row-name">${esc(r.name)}${r.type_name ? ` <span class="reg-row-type" style="color:${r.type_color || '#64748b'};">${esc(r.type_name)}</span>` : ''}${inactive ? ' <span class="reg-row-type" style="color:var(--slate-500);">inaktiv</span>' : ''}</div>
                    ${content ? `<div class="reg-row-content">${esc(contentShort)}</div>` : ''}
                </div>
                <div class="reg-row-actions">
                    <button class="reg-row-edit" onclick="sbRegelEdit(${r.id})" title="Bearbeiten">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                    <button class="reg-row-edit reg-row-del" onclick="sbRegelLoeschen(${r.id}, ${JSON.stringify(r.name)})" title="Löschen">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </div>`;
        }).join('');
        return `
            <div class="reg-cat" data-cat-id="${c.id || 0}">
                <div class="reg-cat-head" style="--cat-color:${c.color || '#9ca3af'};">
                    <span class="material-symbols-rounded" style="color:${c.color || '#9ca3af'};font-size:18px;">${esc(c.icon || 'help')}</span>
                    <strong>${esc(c.name)}</strong>
                    <span class="reg-cat-count">${rules.length}</span>
                </div>
                <div class="reg-cat-body">${rowHtml}</div>
            </div>
        `;
    }).join('');

    box.innerHTML = headerHtml + catHtml;
}

// Inline-Edit einer kundenspezifischen Regel — gilt nur fuer DIESEN Kunden, kein Warnhinweis noetig
// Adapter — Steckbrief nutzt das shared Rule-Modal aus _rule_modal.php
window.sbRegelEdit = function (ruleId) {
    if (typeof rmOpen === 'function') rmOpen(ruleId, customerId);
    else console.error('rmOpen nicht geladen — _rule_modal.php-Partial fehlt');
};
window.sbRegelLoeschen = async function (ruleId, name) {
    if (!confirm('Regel "' + name + '" wirklich löschen?')) return;
    try {
        const r = await App.delete('/rules?id=' + ruleId);
        if (!r.success) throw new Error(r.message || 'Fehler');
        await sbLoadRegeln();
        App.showNotification('Regel gelöscht', 'success');
    } catch (e) {
        App.showNotification('Löschen fehlgeschlagen: ' + (e.message || ''), 'error');
    }
};
window.sbRegelnNeuOeffnen = function () {
    if (typeof rmOpen === 'function') rmOpen(0, customerId);
    else console.error('rmOpen nicht geladen — _rule_modal.php-Partial fehlt');
};
// Reload-Hook: das shared Modal feuert 'rm:saved' nach Save/Delete
document.addEventListener('rm:saved', () => { if (typeof sbLoadRegeln === 'function') sbLoadRegeln(); });

// ===== Site-Monitor-System-Card (alle Monitors des Kunden, 30 Tage) =====
let sbSiteMonitorState = null;

async function sbLoadSiteMonitor() {
    const box = document.getElementById('cs-sitemonitor-content');
    if (!box) return;
    try {
        const r = await App.get('/admin/site-monitor/customer/' + customerId + '/stats?days=30');
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbSiteMonitorState = r.data;
        sbRenderSiteMonitor();
    } catch (e) {
        box.innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">' + esc(e.message || 'Fehler beim Laden') + '</div>';
    }
}

// ===== Shared Helpers =====
function smFmtDt(min) { if (min <= 0) return '–'; if (min < 60) return min + 'm'; const h = Math.floor(min/60), r = min%60; return h + 'h' + (r ? ' ' + r + 'm' : ''); }
function smUpClr(u) { return u >= 99 ? 'var(--emerald-700)' : (u >= 95 ? 'var(--amber-700)' : 'var(--rose-700)'); }
function smDotClr(status) { return status === 'up' ? '#10b981' : (status === 'down' ? '#ef4444' : '#cbd5e1'); }
// Tages-Balkendiagramm der Antwortzeit (Mittelwert je Tag)
function smDailyRespChart(dr, avgMs) {
    dr = dr || [];
    if (!dr.length) return '';
    const fmtDay = ds => { const x = new Date(ds + 'T00:00:00'); return isNaN(x) ? ds : x.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }); };
    const maxMs = Math.max(1, ...dr.map(x => x.avg_ms || 0));
    return `
        <h4 style="margin:0 0 8px;color:var(--slate-700);font-size:var(--d-fs-base);">Antwortzeit pro Tag (Mittelwert)</h4>
        <div style="display:flex;align-items:flex-end;gap:2px;height:120px;padding:8px;background:var(--slate-50);border-radius:8px;overflow-x:auto;">
            ${dr.map(x => {
                const h = Math.max(2, Math.round(((x.avg_ms || 0) / maxMs) * 104));
                return `<div title="${fmtDay(x.d)}: ${x.avg_ms} ms (${(x.cnt || 0).toLocaleString('de-DE')} Checks)" style="flex:1 0 3px;height:${h}px;background:var(--thoxan-400);border-radius:2px 2px 0 0;"></div>`;
            }).join('')}
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--slate-400);margin:4px 2px 18px;">
            <span>${fmtDay(dr[0].d)}</span>
            <span>Ø ${avgMs || 0} ms · max ${Math.round(maxMs)} ms</span>
            <span>${fmtDay(dr[dr.length - 1].d)}</span>
        </div>`;
}
// Stats-Modal eigenstaendig im Steckbrief — unabhaengig vom externen Partial.
const CS_SM_RANGES = [
    { d: 7,   label: '7 T' },
    { d: 30,  label: '30 T' },
    { d: 90,  label: 'Quartal' },
    { d: 365, label: 'Jahr' },
];
const CS_SM_LS_KEY = 'thx_sm_stats_days'; // gleicher Key wie das globale Partial

let csSmCurrentId = null, csSmCurrentLabel = '', csSmCurrentDays = 30;

function csSmGetPreferredDays() {
    try {
        const v = parseInt(localStorage.getItem(CS_SM_LS_KEY), 10);
        if (CS_SM_RANGES.some(r => r.d === v)) return v;
    } catch (_) {}
    return 30;
}
function csSmRangeLabel(days) {
    if (days === 365) return '1 Jahr';
    if (days === 90)  return 'Quartal';
    return days + ' Tage';
}
function csSmRenderRangeChips() {
    const wrap = document.getElementById('cs-sm-range');
    if (!wrap) return;
    wrap.innerHTML = CS_SM_RANGES.map(r => `
        <button type="button"
                onclick="csSmChangeRange(${r.d})"
                style="padding:3px 9px;font-size:11px;border:1px solid ${r.d === csSmCurrentDays ? 'var(--thoxan-700)' : 'var(--slate-200)'};
                       background:${r.d === csSmCurrentDays ? 'var(--thoxan-50)' : '#fff'};
                       color:${r.d === csSmCurrentDays ? 'var(--thoxan-800)' : 'var(--slate-600)'};
                       border-radius:5px;cursor:pointer;font-weight:${r.d === csSmCurrentDays ? 600 : 500};">
            ${r.label}
        </button>
    `).join('');
}
window.csSmChangeRange = function(days) {
    if (!CS_SM_RANGES.some(r => r.d === days)) return;
    if (days === csSmCurrentDays) return;
    csSmCurrentDays = days;
    try { localStorage.setItem(CS_SM_LS_KEY, String(days)); } catch (_) {}
    csSmRenderRangeChips();
    document.getElementById('cs-sm-stats-title').textContent = (csSmCurrentLabel || 'Statistik') + ' — ' + csSmRangeLabel(days);
    csSmLoadStats(csSmCurrentId, csSmCurrentLabel, days);
};

function smEnsureStatsModal() {
    if (document.getElementById('cs-sm-stats-modal')) return;
    const m = document.createElement('div');
    m.id = 'cs-sm-stats-modal';
    m.className = 'thx-modal-backdrop';
    m.style.cssText = 'display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,0.5);align-items:center;justify-content:center;padding:20px;';
    m.innerHTML = `
        <div class="thx-modal" style="background:#fff;border-radius:14px;width:780px;max-width:96vw;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <div class="thx-modal-header" style="display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--slate-200);flex-wrap:wrap;">
                <h3 class="thx-modal-title" id="cs-sm-stats-title" style="margin:0;flex:1;min-width:0;font-size:var(--d-fs-lg);color:var(--slate-900);">Statistik</h3>
                <div id="cs-sm-range" style="display:flex;gap:4px;align-items:center;"></div>
                <button class="thx-modal-close" type="button" onclick="smCloseMonitorModal()" style="background:transparent;border:none;font-size:24px;color:var(--slate-500);cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div id="cs-sm-stats-body" style="padding:18px;overflow-y:auto;max-height:70vh;"></div>
        </div>`;
    m.addEventListener('click', e => { if (e.target === m) smCloseMonitorModal(); });
    document.body.appendChild(m);
    if (!window._cs_sm_esc_bound) {
        window._cs_sm_esc_bound = true;
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && document.getElementById('cs-sm-stats-modal')?.style.display === 'flex') smCloseMonitorModal();
        });
    }
}

window.smCloseMonitorModal = function() {
    const el = document.getElementById('cs-sm-stats-modal');
    if (el) el.style.display = 'none';
};

async function csSmLoadStats(id, label, days) {
    const body = document.getElementById('cs-sm-stats-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
    try {
        const r = await fetch('/api/v1/admin/site-monitor/' + id + '/stats?days=' + days, { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        smRenderMonitorStats(j.data, label);
    } catch (e) {
        body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + esc(e.message || 'Fehler') + '</div>';
    }
}

window.smOpenMonitorModal = async function(id, label) {
    smEnsureStatsModal();
    csSmCurrentId = id;
    csSmCurrentLabel = label || 'Statistik';
    csSmCurrentDays = csSmGetPreferredDays();
    document.getElementById('cs-sm-stats-title').textContent = csSmCurrentLabel + ' — ' + csSmRangeLabel(csSmCurrentDays);
    document.getElementById('cs-sm-stats-modal').style.display = 'flex';
    csSmRenderRangeChips();
    await csSmLoadStats(id, csSmCurrentLabel, csSmCurrentDays);
};

function smRenderMonitorStats(d, label) {
    const s = d.summary || {};
    const upClr = smUpClr(s.uptime || 100);
    const tile = (lbl, val, clr) => `
        <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
            <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">${lbl}</div>
            <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;${clr ? 'color:' + clr : ''}">${val}</div>
        </div>`;
    const urlRows = (d.urls || []).map(u => `
        <tr style="border-bottom:1px solid var(--slate-100);">
            <td style="padding:6px 10px;word-break:break-all;">${esc(u.url)}</td>
            <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${u.checks}</td>
            <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;color:${smUpClr(u.uptime)};">${u.uptime}%</td>
            <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${u.avg_ms}</td>
        </tr>`).join('');
    const incRows = (d.incidents || []).map(i => `
        <tr style="border-bottom:1px solid var(--slate-100);">
            <td style="padding:6px 10px;">${new Date((i.started_at||'').replace(' ','T') + 'Z').toLocaleString('de-DE')}</td>
            <td style="padding:6px 10px;color:${i.ended_at ? 'inherit' : 'var(--rose-600)'};">${i.ended_at ? new Date(i.ended_at.replace(' ','T') + 'Z').toLocaleString('de-DE') : 'läuft noch'}</td>
            <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${smFmtDt(i.duration_minutes || 0)}</td>
        </tr>`).join('');
    document.getElementById('cs-sm-stats-body').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:18px;">
            ${tile('Checks', (s.checks || 0).toLocaleString('de-DE'))}
            ${tile('Uptime', (s.uptime || 100) + '%', upClr)}
            ${tile('Ausfälle', s.outages || 0)}
            ${tile('Ausfallzeit', smFmtDt(s.downtime_min || 0))}
            ${tile('Response', (s.avg_ms || 0) + ' ms')}
        </div>
        ${smDailyRespChart(d.daily_response, s.avg_ms)}
        ${(d.urls || []).length > 1 ? `
            <h4 style="margin:0 0 10px;color:var(--slate-700);font-size:var(--d-fs-base);">Pro URL</h4>
            <table style="width:100%;font-size:var(--d-fs-xs);border-collapse:collapse;margin-bottom:18px;">
                <thead><tr style="border-bottom:1px solid var(--slate-200);">
                    <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">URL</th>
                    <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Checks</th>
                    <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Uptime</th>
                    <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Avg ms</th>
                </tr></thead>
                <tbody>${urlRows}</tbody>
            </table>` : ''}
        ${(d.incidents || []).length > 0 ? `
            <h4 style="margin:0 0 10px;color:var(--slate-700);font-size:var(--d-fs-base);">Letzte Ausfälle</h4>
            <table style="width:100%;font-size:var(--d-fs-xs);border-collapse:collapse;">
                <thead><tr style="border-bottom:1px solid var(--slate-200);">
                    <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">Beginn</th>
                    <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">Ende</th>
                    <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Dauer</th>
                </tr></thead>
                <tbody>${incRows}</tbody>
            </table>` : '<div style="padding:20px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-xs);">Keine Ausfälle im Zeitraum.</div>'}
    `;
}

function sbSmEmpty() {
    return `
        <div style="padding:1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;text-align:center;color:#64748b;font-size: var(--d-fs-sm);">
            <span class="material-symbols-rounded" style="font-size:24px;color:#94a3b8;display:block;margin-bottom:0.3rem;">monitor_heart</span>
            Keine Site-Monitors für diesen Kunden eingerichtet.
            <div style="margin-top:0.5rem;">
                <a href="/admin/site-monitor" class="thx-btn thx-btn-secondary thx-btn-small">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                    Monitor anlegen
                </a>
            </div>
        </div>`;
}

// ===== Finales Layout: KPIs + Heatmap pro Site. Klick auf Site-Zeile = Modal =====
function sbSmRenderFinal(s, monitors, incidents, dailyResp) {
    const stat = (label, val, clr) => `
        <div class="cs-stat" style="padding:7px 9px;min-width:0;flex:1;">
            <div class="cs-stat-label" style="font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${label}</div>
            <div class="cs-stat-value" style="font-size:16px;${clr ? 'color:' + clr : ''}">${val}</div>
        </div>`;

    const stripe = (daily) => (daily || []).map(d => {
        const clr = d.status === 'up' ? '#10b981' : (d.status === 'down' ? '#ef4444' : '#e2e8f0');
        const lbl = new Date(d.date).toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit' });
        const stateLbl = d.status === 'up' ? 'online' : (d.status === 'down' ? 'offline' : 'kein Check');
        return `<span title="${lbl}: ${stateLbl}" style="display:inline-block;flex:1;height:14px;min-width:3px;background:${clr};border-radius:2px;"></span>`;
    }).join('');

    const blocks = monitors.map(m => `
        <div class="sm-heatmap-row" role="button" tabindex="0"
             data-monitor-id="${m.id}" data-monitor-label="${esc(m.label)}"
             title="Klick: Statistik-Modal (30 Tage)"
             style="padding:6px 4px;border-radius:6px;cursor:pointer;transition:background 0.12s;"
             onmouseover="this.style.background='var(--slate-50)'" onmouseout="this.style.background='transparent'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:var(--d-fs-xs);">
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${smDotClr(m.status)};flex-shrink:0;"></span>
                <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;color:var(--slate-800);">${esc(m.label)}</span>
                <span style="color:${smUpClr(m.uptime)};font-family:ui-monospace,monospace;flex-shrink:0;font-weight:600;">${m.uptime}%</span>
            </div>
            <div style="display:flex;gap:1px;">${stripe(m.daily)}</div>
        </div>
    `).join('');

    return `
        <div style="display:flex;gap:5px;margin-bottom:0.7rem;">
            ${stat('Sites', s.monitor_count)}
            ${stat('Uptime', s.uptime + '%', smUpClr(s.uptime))}
            ${stat('Ø Resp.', s.avg_ms + 'ms')}
        </div>
        <div id="cs-sitemonitor-rows" style="display:flex;flex-direction:column;gap:2px;">${blocks}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:var(--slate-400);margin-top:5px;">
            <span>30 T zurück</span>
            <span style="display:flex;align-items:center;gap:3px;">
                <span style="display:inline-block;width:7px;height:7px;background:#10b981;border-radius:2px;"></span>up
                <span style="display:inline-block;width:7px;height:7px;background:#ef4444;border-radius:2px;margin-left:3px;"></span>down
                <span style="display:inline-block;width:7px;height:7px;background:#e2e8f0;border-radius:2px;margin-left:3px;"></span>n/a
            </span>
            <span>heute</span>
        </div>
        ${smDailyRespChart(dailyResp, s.avg_ms)}`;
}


function sbRenderSiteMonitor() {
    const d = sbSiteMonitorState || {};
    const box = document.getElementById('cs-sitemonitor-content');
    if (!box) return;
    const s = d.summary || { checks: 0, uptime: 100, avg_ms: 0, outages: 0, downtime_min: 0, monitor_count: 0 };
    const monitors = d.monitors || [];
    const incidents = d.incidents || [];

    if (!monitors.length) { box.innerHTML = sbSmEmpty(); return; }
    box.innerHTML = sbSmRenderFinal(s, monitors, incidents, d.daily_response);
    // Event-Delegation: Klick/Enter/Space auf .sm-heatmap-row → Modal
    box.querySelectorAll('.sm-heatmap-row').forEach(row => {
        const open = () => smOpenMonitorModal(parseInt(row.dataset.monitorId, 10), row.dataset.monitorLabel || '');
        row.addEventListener('click', open);
        row.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    });
}
window.sbLoadSiteMonitor = sbLoadSiteMonitor;

function sbRenderSitemap(q) {
    const body = document.getElementById('sb-sitemap-body');
    if (!body || !sbSitemapState) return;
    const hosts = sbSitemapState.hosts;
    const hostKeys = Object.keys(hosts).sort();
    let html = '';
    let totalShown = 0;
    for (const host of hostKeys) {
        const pages = hosts[host];
        const filtered = q
            ? pages.filter(p => ((p.source_ref || '') + ' ' + (p.title || '')).toLowerCase().includes(q))
            : pages;
        if (filtered.length === 0) continue;
        totalShown += filtered.length;
        html += `<div class="sm-host-block">
            <div class="sm-host-head">
                <span class="material-symbols-rounded" style="color:#0891b2;font-size:18px;">language</span>
                <a href="https://${esc(host)}" target="_blank" rel="noopener" style="color:#1e293b;font-weight:700;text-decoration:none;">${esc(host)}</a>
                <span class="sm-host-count">${filtered.length}${q ? ' / ' + pages.length : ''}</span>
            </div>
            <div class="sm-pages">
                ${filtered.map(p => `
                    <div class="sm-page">
                        <a href="${esc(p.source_ref)}" target="_blank" rel="noopener" class="sm-page-link" title="${esc(p.source_ref)}">
                            <span class="sm-page-title">${esc(p.title || p.source_ref)}</span>
                            <span class="sm-page-url">${esc((p.source_ref || '').replace(/^https?:\/\/[^/]+/, '') || '/')}</span>
                        </a>
                        <a class="sm-page-kb" href="/wissen/${p.id}" target="_blank" title="Im Wissen öffnen">
                            <span class="material-symbols-rounded">library_books</span>
                        </a>
                        <span class="sm-page-date" title="Aktualisiert">${p.updated_at ? new Date(p.updated_at.replace(' ', 'T')).toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'}) : ''}</span>
                    </div>
                `).join('')}
            </div>
        </div>`;
    }
    if (totalShown === 0) {
        html = '<div style="text-align:center;color:#94a3b8;padding:2.5rem 1rem;">Keine Treffer</div>';
    }
    body.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
            <small style="color:#64748b;">${totalShown} ${totalShown === 1 ? 'Seite' : 'Seiten'} · ${hostKeys.length} ${hostKeys.length === 1 ? 'Domain' : 'Domains'}</small>
        </div>
        ${html}
    `;
}

window.sbCloseSitemap = function() {
    const overlay = document.getElementById('sb-sitemap-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

window.sbTriggerWebsite = async function() {
    try {
        const r = await App.post('/admin/customers/' + customerId + '/website-crawl/sync', {});
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification('Sync gestartet (Job #' + r.data.job_id + ')', 'success');
        sbLoadWebsite();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbOpenWebsiteConfig = function() {
    const d = sbWebsiteState || {};
    let overlay = document.getElementById('sb-website-config');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-website-config';
        overlay.className = 'sb-maximize-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseWebsiteConfig(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-maximize-card" style="max-width:560px;height:auto;max-height:88vh;">
            <div class="sb-maximize-head">
                <div class="sb-card-icon" style="background:#0891b2;"><span class="material-symbols-rounded">language</span></div>
                <strong style="flex:1;">Website-Konfiguration</strong>
                <button class="sb-card-action" onclick="sbCloseWebsiteConfig()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="sb-maximize-body">
                <div class="cs-row" style="display:block;border:0;padding:0 0 0.8rem;">
                    <div class="cs-row-label" style="margin-bottom:4px;">Start-URL</div>
                    <input type="url" id="sb-web-url" value="${esc(d.start_url || '')}" placeholder="https://firma.de"
                           style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size: var(--d-fs-sm);">
                    <small style="color:#94a3b8;">Der Crawler folgt internen Links derselben Domain.</small>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:0.8rem;">
                    <div>
                        <div class="cs-row-label" style="margin-bottom:4px;">Max. Seiten</div>
                        <input type="number" id="sb-web-max-pages" value="${d.max_pages || 120}" min="1" max="200"
                               style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                    </div>
                    <div>
                        <div class="cs-row-label" style="margin-bottom:4px;">Max. Tiefe</div>
                        <input type="number" id="sb-web-max-depth" value="${d.max_depth || 4}" min="1" max="5"
                               style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                    </div>
                </div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:0.8rem;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size: var(--d-fs-sm);font-weight:600;color:#1e293b;">
                        <input type="checkbox" id="sb-web-sync-enabled" ${d.sync_enabled ? 'checked' : ''}>
                        Automatischer Sync alle
                        <input type="number" id="sb-web-interval" value="${d.sync_interval_days || 60}" min="1" max="365"
                               style="width:60px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:6px;">
                        Tage
                    </label>
                    <small style="color:#94a3b8;display:block;margin-top:6px;">Beim Sync werden neue Seiten angelegt, geänderte aktualisiert und entfernte aus der Wissensbasis gelöscht.</small>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:0.4rem;">
                    <button class="thx-btn thx-btn-secondary" onclick="sbCloseWebsiteConfig()">Abbrechen</button>
                    <button class="thx-btn thx-btn-primary" id="sb-web-save-btn" onclick="sbSaveWebsiteConfig()">Speichern &amp; Sync starten</button>
                </div>
            </div>
        </div>
    `;
    overlay.classList.add('open');
};

window.sbCloseWebsiteConfig = function() {
    const overlay = document.getElementById('sb-website-config');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

window.sbSaveWebsiteConfig = async function() {
    const btn = document.getElementById('sb-web-save-btn');
    if (!btn) return;
    btn.disabled = true;
    const origLabel = btn.textContent;
    btn.textContent = 'Speichere…';
    try {
        const r = await App.put('/admin/customers/' + customerId + '/website-crawl', {
            start_url: document.getElementById('sb-web-url').value.trim(),
            max_pages: parseInt(document.getElementById('sb-web-max-pages').value) || 30,
            max_depth: parseInt(document.getElementById('sb-web-max-depth').value) || 3,
            sync_enabled: document.getElementById('sb-web-sync-enabled').checked,
            sync_interval_days: parseInt(document.getElementById('sb-web-interval').value) || 30,
            trigger_sync: true,
        });
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification('Konfiguration gespeichert' + (r.data.sync_job_id ? ' — Sync-Job #' + r.data.sync_job_id + ' gestartet' : ''), 'success');
        sbCloseWebsiteConfig();
        sbLoadWebsite();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        btn.disabled = false;
        btn.textContent = origLabel;
    }
};

// =================================================================
// STECKBRIEF — Inline-Edit, Cards, Suche, Drag-Reorder
// =================================================================

// ----- Profil: per-Feld Edit mit Stift-Icon -----
function sbInitProfileEdit() {
    // Profil-Karte (Stammdaten) + Marken-Profil-Karte teilen dieselben Inline-Edit-Hooks
    document.querySelectorAll('#cs-profile-card .cs-pf-input, #cs-markenprofil-card .cs-pf-input').forEach(el => {
        if (el.tagName === 'INPUT') el.readOnly = true;
        else el.contentEditable = 'false';
        el.addEventListener('keydown', sbPfKey);
    });
}

// KI-Autofill der Marken-Profil-Felder per Website-URL
window.sbMpAutofill = async function () {
    const profileUrl = (window.customerWebsiteUrl || document.querySelector('[data-field="website"]')?.value || '').trim();
    let url = prompt('Aus welcher URL soll die KI das Marken-Profil befüllen?', profileUrl || 'https://');
    if (!url || !url.match(/^https?:\/\//i)) return;
    const status = document.querySelector('#cs-markenprofil-card div:first-child span');
    const oldText = status?.textContent || '';
    if (status) status.textContent = 'KI analysiert ' + url + '…';
    try {
        const r = await App.post('/admin/customer-profile-suggest', { source: 'url', url });
        if (!r.success) throw new Error(r.message || 'Fehler');
        const v = r.data || {};
        // Vorschau-Modal: alle Felder mit alt → vorschlag
        const felder = ['description', 'target_audience', 'products_services', 'unique_selling_points', 'tone_of_voice', 'brand_values', 'industry'];
        const diffs = felder.map(k => {
            const inp = document.querySelector(`[data-field="${k}"]`);
            const alt = inp ? (inp.value !== undefined ? inp.value : inp.textContent || '') : '';
            const neu = v[k] || '';
            return { k, alt: alt.trim(), neu: neu.trim() };
        }).filter(d => d.neu && d.neu !== d.alt);
        if (!diffs.length) {
            App.showNotification('KI hat keine neuen Vorschläge geliefert.', 'info');
            if (status) status.textContent = oldText;
            return;
        }
        const uebernehmen = confirm(
            'KI-Vorschlag für ' + diffs.length + ' Felder:\n\n' +
            diffs.map(d => '• ' + d.k + ' → ' + d.neu.substring(0, 70) + (d.neu.length > 70 ? '…' : '')).join('\n') +
            '\n\nAlle übernehmen?'
        );
        if (!uebernehmen) {
            if (status) status.textContent = oldText;
            return;
        }
        // Felder direkt im DOM updaten + per Save-API speichern
        for (const d of diffs) {
            const patch = {}; patch[d.k] = d.neu;
            await App.put('/admin/customers/' + customerId, patch);
            const inp = document.querySelector(`[data-field="${d.k}"]`);
            if (inp) {
                if (inp.tagName === 'INPUT') inp.value = d.neu;
                else inp.textContent = d.neu;
                inp.classList.remove('cs-pf-empty');
            }
        }
        App.showNotification(diffs.length + ' Felder per KI befüllt', 'success');
        if (status) status.textContent = oldText;
    } catch (e) {
        App.showNotification('KI-Autofill: ' + (e.message || 'Fehler'), 'error');
        if (status) status.textContent = oldText;
    }
};

function sbPfKey(e) {
    const field = e.target.closest('.cs-pf-field');
    if (!field || !field.classList.contains('editing')) return;
    if (e.key === 'Escape') { e.preventDefault(); sbPfCancel(field.querySelector('.cs-pf-cancel-btn')); }
    else if (e.key === 'Enter' && (e.target.tagName === 'INPUT' || e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        sbPfSave(field.querySelector('.cs-pf-save-btn'));
    }
}

window.sbPfEdit = function(btn) {
    const field = btn.closest('.cs-pf-field');
    if (!field || field.classList.contains('editing')) return;
    const input = field.querySelector('.cs-pf-input');
    // Original-Wert merken
    field._sbOriginal = input.tagName === 'INPUT' ? input.value : input.textContent;
    field.classList.add('editing');
    if (input.tagName === 'INPUT') input.readOnly = false;
    else input.contentEditable = 'true';
    input.focus();
    // Caret ans Ende
    if (input.tagName !== 'INPUT') {
        const range = document.createRange();
        range.selectNodeContents(input);
        range.collapse(false);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }
};

window.sbPfCancel = function(btn) {
    const field = btn.closest('.cs-pf-field');
    if (!field) return;
    const input = field.querySelector('.cs-pf-input');
    const original = field._sbOriginal ?? '';
    if (input.tagName === 'INPUT') input.value = original;
    else input.textContent = original;
    sbPfLeaveEdit(field, input, original);
};

window.sbPfSave = async function(btn) {
    const field = btn.closest('.cs-pf-field');
    if (!field) return;
    const input = field.querySelector('.cs-pf-input');
    const fieldKey = input.dataset.field;
    const newValue = (input.tagName === 'INPUT' ? input.value : input.textContent).trim();
    const original = field._sbOriginal ?? '';
    if (newValue === original) { sbPfLeaveEdit(field, input, newValue); return; }

    btn.disabled = true;
    try {
        const r = await App.put('/admin/customers/' + customerId, { [fieldKey]: newValue });
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbPfLeaveEdit(field, input, newValue);
        // Live-Update von Header (Name/Kürzel)
        if (fieldKey === 'name') {
            const h1 = document.querySelector('.cs-wrap .thx-page-title');
            if (h1) h1.textContent = newValue;
        }
        if (fieldKey === 'abbreviation' && newValue !== '') {
            const badge = document.querySelector('.cs-customer-badge');
            if (badge) badge.textContent = newValue.toUpperCase();
        }
        App.showNotification('Gespeichert', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    } finally {
        btn.disabled = false;
    }
};

function sbPfLeaveEdit(field, input, finalValue) {
    field.classList.remove('editing');
    if (input.tagName === 'INPUT') input.readOnly = true;
    else input.contentEditable = 'false';
    input.classList.toggle('cs-pf-empty', !finalValue || finalValue.trim() === '');
    delete field._sbOriginal;
}

// ===== Tags / Art =====
let sbTags = [];
const SB_TAG_SUGGESTIONS = ['Kunde', 'Eigenprojekt', 'Portal', 'Pitch', 'Inaktiv', 'Test'];

(function initSbTags() {
    const list = document.getElementById('cs-tags-list');
    if (!list) return;
    try { sbTags = JSON.parse(list.dataset.tags || '[]'); } catch (e) { sbTags = []; }
    sbRenderTags();
})();

function sbRenderTags() {
    const list = document.getElementById('cs-tags-list');
    if (!list) return;
    const suggestions = SB_TAG_SUGGESTIONS.filter(s => !sbTags.includes(s));
    list.innerHTML = `
        ${sbTags.map(t => `
            <span class="cs-tag-pill">
                ${esc(t)}
                <button class="cs-tag-remove" onclick="sbTagRemove('${esc(t).replace(/'/g, "\\'")}')" title="Entfernen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </span>
        `).join('')}
        ${suggestions.map(s => `
            <span class="cs-tag-pill suggestion" onclick="sbTagAdd('${esc(s).replace(/'/g, "\\'")}')" title="Hinzufügen">
                <span class="material-symbols-rounded" style="font-size:13px;">add</span>${esc(s)}
            </span>
        `).join('')}
        <input type="text" class="cs-tag-input" placeholder="Eigene Art…"
               onkeydown="if(event.key==='Enter' && this.value.trim()){event.preventDefault();sbTagAdd(this.value.trim());this.value='';}"
               onblur="if(this.value.trim()){sbTagAdd(this.value.trim());this.value='';}">
    `;
}

window.sbTagAdd = function(tag) {
    tag = (tag || '').trim();
    if (!tag || sbTags.includes(tag)) return;
    sbTags.push(tag);
    sbRenderTags();
    sbTagCommit();
};

window.sbTagRemove = function(tag) {
    sbTags = sbTags.filter(t => t !== tag);
    sbRenderTags();
    sbTagCommit();
};

let sbTagTimer = null;
function sbTagCommit() {
    clearTimeout(sbTagTimer);
    sbTagTimer = setTimeout(async () => {
        try {
            await App.put('/admin/customers/' + customerId, { tags: sbTags });
        } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
    }, 400);
}

// ===== CRM-Firma-Verknüpfung =====
let csCrmFirmaSucheTimer = null;
window.csCrmFirmaOpenSearch = function() {
    const m = document.getElementById('cs-crmfirma-modal');
    if (!m) return;
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('cs-crmfirma-input')?.focus(), 50);
};
window.csCrmFirmaCloseSearch = function() {
    const m = document.getElementById('cs-crmfirma-modal');
    if (m) m.style.display = 'none';
    const i = document.getElementById('cs-crmfirma-input');
    if (i) i.value = '';
    const r = document.getElementById('cs-crmfirma-results');
    if (r) r.innerHTML = '';
};
window.csCrmFirmaSuche = function() {
    clearTimeout(csCrmFirmaSucheTimer);
    const input = document.getElementById('cs-crmfirma-input');
    const results = document.getElementById('cs-crmfirma-results');
    const q = (input.value || '').trim();
    if (q.length < 2) { results.innerHTML = ''; return; }
    csCrmFirmaSucheTimer = setTimeout(async () => {
        try {
            const r = await fetch('/api/v1/crm/firmen?suche=' + encodeURIComponent(q) + '&limit=15', { credentials: 'same-origin' });
            const j = await r.json();
            const eintraege = j?.data?.eintraege || [];
            if (!eintraege.length) {
                results.innerHTML = '<div style="padding:12px;color:var(--slate-500);font-size:0.85rem;text-align:center;">Keine Treffer</div>';
                return;
            }
            const esc = s => (s || '').replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
            results.innerHTML = eintraege.map(f => {
                const meta = [f.branche, f.website].filter(Boolean).join(' · ');
                const kontakte = f.anzahl_kontakte > 0 ? f.anzahl_kontakte + ' Kontakt' + (f.anzahl_kontakte === 1 ? '' : 'e') : 'keine Kontakte';
                return '<div onclick="csCrmFirmaPick(' + f.id + ',\'' + esc(f.firmenname).replace(/'/g, "\\'") + '\')" '
                    + 'style="padding:10px 12px;cursor:pointer;border:1px solid var(--slate-200);border-radius:6px;margin-bottom:4px;" '
                    + 'onmouseover="this.style.background=\'var(--thoxan-50)\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600;font-size:0.88rem;">' + esc(f.firmenname) + '</div>'
                    + '<div style="font-size:0.75rem;color:var(--slate-500);">' + esc(meta) + (meta ? ' · ' : '') + kontakte + '</div>'
                    + '</div>';
            }).join('');
        } catch (e) {
            results.innerHTML = '<div style="padding:12px;color:var(--rose-700);">Fehler: ' + (e.message || 'unbekannt') + '</div>';
        }
    }, 250);
};
window.csCrmFirmaPick = async function(firmaId, firmaName) {
    try {
        await App.put('/admin/customers/' + customerId, { crm_firma_id: firmaId });
        App.showNotification('Mit „' + firmaName + '" verknüpft', 'success');
        setTimeout(() => location.reload(), 400);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};
window.csCrmFirmaUnlink = async function() {
    if (!confirm('Verknüpfung zur CRM-Firma wirklich lösen?')) return;
    try {
        await App.put('/admin/customers/' + customerId, { crm_firma_id: null });
        App.showNotification('Verknüpfung gelöst', 'success');
        setTimeout(() => location.reload(), 400);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

// ===== Status-Toggle (aktiv/inaktiv) =====
window.sbToggleActive = async function(input) {
    const newState = input.checked;
    const textEl = input.parentElement.querySelector('.cs-status-text');
    if (textEl) textEl.textContent = newState ? 'Aktiv' : 'Inaktiv';
    try {
        await App.put('/admin/customers/' + customerId, { is_active: newState ? 1 : 0 });
        App.showNotification(newState ? 'Kunde aktiviert' : 'Kunde deaktiviert', 'success');
    } catch (e) {
        // Rollback
        input.checked = !newState;
        if (textEl) textEl.textContent = !newState ? 'Aktiv' : 'Inaktiv';
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

// ===== Logo-Bearbeiten: Mini-Menü öffnen/schließen =====
window.sbToggleLogoMenu = function(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const menu = document.getElementById('cs-logo-menu');
    if (!menu) return;
    const open = !menu.classList.contains('is-open');
    sbCloseLogoMenu();
    if (open) {
        menu.classList.add('is-open');
        document.getElementById('cs-logo-wrap')?.classList.add('is-menu-open');
        // Klick irgendwohin schließt das Menü
        setTimeout(() => {
            document.addEventListener('mousedown', sbLogoMenuOutsideClick);
        }, 0);
    }
};
window.sbCloseLogoMenu = function() {
    const menu = document.getElementById('cs-logo-menu');
    if (menu) menu.classList.remove('is-open');
    document.getElementById('cs-logo-wrap')?.classList.remove('is-menu-open');
    document.removeEventListener('mousedown', sbLogoMenuOutsideClick);
};
function sbLogoMenuOutsideClick(e) {
    const wrap = document.getElementById('cs-logo-wrap');
    if (!wrap || !wrap.contains(e.target)) sbCloseLogoMenu();
}

// ===== Logo-Upload / Delete =====
window.sbUploadLogo = async function(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    const wrap = document.getElementById('cs-logo-wrap');
    if (!wrap) return;
    wrap.classList.add('is-uploading');
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const r = await fetch('/api/v1/admin/customers/' + customerId + '/logo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken },
            body: fd
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Upload fehlgeschlagen');
        sbRenderLogo(j.data.logo_url);
        App.showNotification('Logo gespeichert', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler beim Upload', 'error');
    } finally {
        wrap.classList.remove('is-uploading');
        input.value = '';
    }
};

window.sbFetchFavicon = async function() {
    const btn = document.getElementById('cs-logo-favicon-btn');
    const wrap = document.getElementById('cs-logo-wrap');
    if (!wrap || !btn || btn.disabled) return;
    wrap.classList.add('is-uploading');
    btn.disabled = true;
    try {
        const r = await fetch('/api/v1/admin/customers/' + customerId + '/fetch-favicon', {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken }
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Favicon konnte nicht geladen werden');
        sbRenderLogo(j.data.logo_url);
        const dim = (j.data.width && j.data.height) ? ` (${j.data.width}x${j.data.height})` : '';
        App.showNotification('Favicon übernommen' + dim, 'success');
    } catch (e) {
        App.showNotification(e.message || 'Favicon-Fehler', 'error');
    } finally {
        wrap.classList.remove('is-uploading');
        btn.disabled = false;
    }
};

window.sbDeleteLogo = async function() {
    if (!confirm('Logo wirklich entfernen?')) return;
    const wrap = document.getElementById('cs-logo-wrap');
    wrap.classList.add('is-uploading');
    try {
        const r = await fetch('/api/v1/admin/customers/' + customerId + '/logo', {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': App.csrfToken }
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Löschen fehlgeschlagen');
        sbRenderLogo(null);
        App.showNotification('Logo entfernt', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    } finally {
        wrap.classList.remove('is-uploading');
    }
};

function sbRenderLogo(url) {
    const wrap = document.getElementById('cs-logo-wrap');
    if (!wrap) return;
    const existingLogo = document.getElementById('cs-customer-logo');
    const existingBadge = document.getElementById('cs-customer-badge');
    const existingDelete = wrap.querySelector('.cs-logo-delete-btn');
    if (url) {
        if (existingBadge) existingBadge.remove();
        if (existingLogo) {
            existingLogo.src = url + '?t=' + Date.now(); // Cache-Bust
        } else {
            const img = document.createElement('img');
            img.id = 'cs-customer-logo';
            img.className = 'cs-customer-logo';
            img.src = url + '?t=' + Date.now();
            img.alt = '';
            img.title = 'Logo ändern';
            wrap.insertBefore(img, wrap.firstChild);
        }
        if (!existingDelete) {
            const btn = document.createElement('button');
            btn.className = 'cs-logo-delete-btn';
            btn.title = 'Logo entfernen';
            btn.onclick = window.sbDeleteLogo;
            btn.innerHTML = '<span class="material-symbols-rounded">close</span>';
            wrap.appendChild(btn);
        }
    } else {
        if (existingLogo) existingLogo.remove();
        if (existingDelete) existingDelete.remove();
        if (!existingBadge) {
            const badge = document.createElement('div');
            badge.id = 'cs-customer-badge';
            badge.className = 'cs-customer-badge';
            badge.title = 'Kürzel: ' + (customerAbbr || '?');
            badge.textContent = customerAbbr || '?';
            wrap.insertBefore(badge, wrap.firstChild);
        }
    }
}

// ===== Multi-Domain-Editor =====
let sbDomains = [];
(function initSbDomains() {
    const list = document.getElementById('cs-domains-list');
    if (!list) return;
    try { sbDomains = JSON.parse(list.dataset.domains || '[]'); } catch (e) { sbDomains = []; }
})();

window.sbDomainsAdd = function() {
    sbDomains.push({ label: '', url: '' });
    sbDomainsRender(true);
    sbDomainsFocusLast();
};

function sbDomainsRender(editMode) {
    const list = document.getElementById('cs-domains-list');
    if (!list) return;
    if (!editMode && sbDomains.length === 0) {
        list.innerHTML = '<small style="color:#cbd5e1;font-size: var(--d-fs-sm);">–</small>';
        return;
    }
    if (editMode) {
        list.innerHTML = sbDomains.map((d, i) => `
            <div class="cs-domain-edit-row" data-idx="${i}">
                <input type="text" placeholder="Bezeichnung (z.B. Shop)" value="${esc(d.label || '')}" oninput="sbDomainsUpdate(${i}, 'label', this.value)">
                <input type="url" placeholder="https://…" value="${esc(d.url || '')}" oninput="sbDomainsUpdate(${i}, 'url', this.value)" onblur="sbDomainsCommit()">
                <button class="cs-domain-remove-btn" onclick="sbDomainsRemove(${i})" title="Entfernen">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span>
                </button>
            </div>
        `).join('') + `
            <div style="margin-top:6px;display:flex;gap:6px;">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbDomainsAdd()">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                    Weitere Domain
                </button>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbDomainsCommit(true)">Fertig</button>
            </div>
        `;
    } else {
        list.innerHTML = sbDomains.map(d => `
            <div class="cs-domain-row">
                ${d.label ? `<strong style="font-size: var(--d-fs-sm);color:#475569;">${esc(d.label)}:</strong>` : ''}
                <a href="${esc(d.url)}" target="_blank" rel="noopener" style="color:var(--thoxan-700);font-size: var(--d-fs-sm);text-decoration:none;">${esc(d.url || '')}</a>
                <button class="cs-domain-remove-btn" onclick="sbDomainsEdit()" style="margin-left:auto;color:#cbd5e1;" title="Bearbeiten">
                    <span class="material-symbols-rounded" style="font-size:14px;">edit</span>
                </button>
            </div>
        `).join('');
    }
}

window.sbDomainsEdit = function() { sbDomainsRender(true); sbDomainsFocusLast(); };
window.sbDomainsUpdate = function(idx, field, value) {
    if (!sbDomains[idx]) return;
    sbDomains[idx][field] = value;
};
window.sbDomainsRemove = function(idx) {
    sbDomains.splice(idx, 1);
    sbDomainsRender(true);
    sbDomainsCommit();
};
let sbDomainsCommitTimer = null;
window.sbDomainsCommit = function(closeEditor) {
    clearTimeout(sbDomainsCommitTimer);
    sbDomainsCommitTimer = setTimeout(async () => {
        // Leere Einträge filtern für API
        const clean = sbDomains.filter(d => (d.url || '').trim() !== '');
        try {
            await App.put('/admin/customers/' + customerId, { domains: clean });
            sbDomains = clean;
            if (closeEditor) sbDomainsRender(false);
        } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
    }, closeEditor ? 0 : 600);
};
function sbDomainsFocusLast() {
    setTimeout(() => {
        const inputs = document.querySelectorAll('#cs-domains-list .cs-domain-edit-row input[type="url"]');
        if (inputs.length) inputs[inputs.length - 1].focus();
    }, 30);
}

// ----- Cards: Laden / Rendern -----
let sbCards = [];

async function sbLoadCards() {
    try {
        const r = await App.get('/admin/customers/' + customerId + '/cards');
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbCards = r.data.cards || [];
        sbRenderCards();
    } catch (e) {
        document.getElementById('sb-cards').innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">Cards: ' + esc(e.message || '') + '</div>';
    }
    return true;
}

function sbRenderCards() {
    const container = document.getElementById('sb-cards');
    const empty = document.getElementById('sb-empty-state');
    const userCardCount = sbCards.filter(c => !c.is_system).length;

    // Vor Re-Render: System-Card-Bodies zurück ins Template parken,
    // damit der Inhalt (Profil-Inputs, Wissens-Liste, Asana-State) erhalten bleibt
    sbStashSystemCards();

    if (sbCards.length === 0) {
        container.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = userCardCount === 0 ? 'block' : 'none';
    container.innerHTML = sbCards.map(c => sbRenderCard(c)).join('');

    sbHydrateSystemCards();
    sbInitProfileEdit();
    sbApplyMode();
    sbApplySearch(document.getElementById('sb-search')?.value || '');
    // Cards in die Tab-Panels einsortieren
    csRouteCards();
    // Plan-Widget-Shell an den gespeicherten Ziel-Tab anhaengen (passiert nach
    // dem Card-Routing, damit das Shell nicht versehentlich weggeraeumt wird)
    if (typeof csShellInit === 'function') csShellInit();
    // Tabs Personen/Dateien/Marke neu rendern (sie lesen aus sbCards)
    // Erst Hauptkontakt-Pseudo-Karte ins DOM, DANN Counts — sonst zaehlt der
    // Uebersicht-Tab die Hauptkontakt-Karte nicht mit.
    if (typeof csRenderHauptkontakt    === 'function') csRenderHauptkontakt();
    if (typeof csUpdateDedicatedCounts === 'function') csUpdateDedicatedCounts();
    // Tiefe Verlinkung: #card-<id> → richtigen Tab + Scroll + Highlight
    if (typeof csResolveCardHash === 'function') csResolveCardHash();
}

/* Tab-Counts: ueber alle Tabs einheitlich = Anzahl Karten im jeweiligen Tab-Panel
   (Plan-Widget und Hauptkontakt zaehlen mit). Sonstiges-Tab nur sichtbar wenn
   Karten drin sind. */
window.csUpdateDedicatedCounts = function() {
    const set = (k, n) => {
        const el = document.querySelector(`.cs-tab-count[data-count-for="${k}"]`);
        if (el) el.textContent = n > 0 ? n : '';
    };
    ['uebersicht','personen','websites','inhalte','dateien','marke','sonstiges'].forEach(tab => {
        const n = document.querySelectorAll(`#cs-tab-${tab} .sb-card, #cs-tab-${tab} .cs-card-shell`).length;
        set(tab, n);
    });
    const sonstCnt = document.querySelectorAll('#cs-tab-sonstiges .sb-card').length;
    const sonstTab = document.querySelector('.cs-tab[data-tab="sonstiges"]');
    if (sonstTab) sonstTab.style.display = sonstCnt > 0 ? '' : 'none';
};

/* ===== Tab-Routing =====
   Strikte Tab-Rollen:
   - Übersicht: Profil (Slot), Plan-Widget (Slot), Cards mit target_tab='uebersicht'
   - Inhalte:   alle anderen User-Cards (target_tab='inhalte' = Default) + System-Cards
                Asana/Wissen/Website
   - Personen / Dateien / Marke: dedizierte Strukturen (kein Card-Routing)
*/
function csRouteCards() {
    const cards = Array.from(document.querySelectorAll('#sb-cards > .sb-card'));
    if (!cards.length) return;
    const counts = { uebersicht: 0, inhalte: 0, personen: 0, dateien: 0, marke: 0, websites: 0 };
    // Alle Spalten + Hero-Zonen leeren
    document.querySelectorAll('.cs-kanban .cs-col, .cs-kanban .cs-hero').forEach(col => {
        Array.from(col.children).forEach(el => {
            if (el.classList.contains('sb-card')) el.remove();
        });
    });

    // Routing-Priorität: column_idx ist Wahrheit.
    //  0 → Hero · 1/2/3 → Spalte. Wenn unset (legacy), Fallback per colSpan: >=2 → Hero, sonst Spalte 2.
    cards.forEach(card => {
        const targetTab = card.dataset.targetTab || 'inhalte';
        const colRaw = card.dataset.columnIdx;
        const colIdx = (colRaw === undefined || colRaw === '' || colRaw === 'NaN') ? null : parseInt(colRaw, 10);
        const colSpan = parseInt(card.dataset.colSpan || '1', 10);
        let target = null;
        if (colIdx === 0) {
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-hero`);
        } else if (colIdx >= 1 && colIdx <= 3) {
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-col[data-col="${colIdx}"]`);
        } else if (colSpan >= 2) {
            // Legacy: keine column_idx gesetzt, aber breite Karte → Hero
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-hero`);
        } else {
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-col[data-col="2"]`);
        }
        if (target) {
            target.appendChild(card);
            if (counts[targetTab] !== undefined) counts[targetTab]++;
        }
    });
    // Tab-Counts setzen
    Object.entries(counts).forEach(([t, n]) => {
        const el = document.querySelector(`.cs-tab-count[data-count-for="${t}"]`);
        if (el) el.textContent = n > 0 ? n : '';
    });
}

/* ===== Personen-Tab: Tabellen-Ansicht aller Kontakte aus contacts-Cards ===== */
function csEscHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function csInitials(name) {
    return String(name || '').trim().split(/\s+/).map(p => p[0] || '').slice(0, 2).join('').toUpperCase();
}
// Globaler Input-Listener: aktualisiert data-empty="1" auf cs-people-cell-edit
// Feldern beim Tippen, damit der Placeholder verschwindet sobald Inhalt da ist.
document.addEventListener('input', (e) => {
    const cell = e.target.closest && e.target.closest('.cs-people-cell-edit');
    if (!cell) return;
    if ((cell.textContent || '').trim()) cell.removeAttribute('data-empty');
    else cell.setAttribute('data-empty', '1');
});

/* Hauptkontakt-Card auf Uebersicht — automatisch aus erster Kontakt-Person
   der ersten Kontakte-Card. Wird nach csRouteCards/csRenderPersonen in eine
   feste Position in Uebersicht Col 1 oder Col 2 eingehaengt. */
/* Notiz in einem der dedizierten Tabs (personen/dateien/marke) anlegen */
window.csAddNoteToTab = async function(tab) {
    try {
        const r = await App.post('/admin/customers/' + customerId + '/cards', { type: 'richtext' });
        if (!r.success) throw new Error(r.message || 'Fehler');
        // target_tab auf den entsprechenden Tab setzen
        await App.put('/admin/customers/' + customerId + '/cards/' + r.data.id, { target_tab: tab });
        r.data.target_tab = tab;
        sbCards.push(r.data);
        sbRenderCards();
        csSetTab(tab);
        setTimeout(() => {
            const el = document.querySelector(`.sb-card[data-card-id="${r.data.id}"]`);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.querySelector('.sb-card-title')?.focus();
        }, 80);
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

/* Hauptkontakt-Card: Position (Spalte) + Titel in localStorage, sonst Default */
function csHauptkontaktKey() { return 'cs_hauptkontakt_' + customerId; }
function csHauptkontaktState() {
    try {
        const raw = localStorage.getItem(csHauptkontaktKey());
        if (!raw) return { col: 2, title: 'Hauptkontakt' };
        const s = JSON.parse(raw);
        return {
            col: (s.col >= 1 && s.col <= 3) ? s.col : 2,
            title: (typeof s.title === 'string' && s.title.trim()) ? s.title : 'Hauptkontakt',
        };
    } catch (_) { return { col: 2, title: 'Hauptkontakt' }; }
}
function csHauptkontaktSave(p) {
    const cur = csHauptkontaktState();
    try { localStorage.setItem(csHauptkontaktKey(), JSON.stringify({ ...cur, ...p })); } catch (_) {}
}
window.csHauptkontaktSaveTitle = function(value) {
    const t = (value || '').trim() || 'Hauptkontakt';
    csHauptkontaktSave({ title: t });
};
let csHkDragActive = false;
function csHauptkontaktDragStart(e) {
    if (!document.body.classList.contains('cs-layout-edit')) { e.preventDefault(); return; }
    csHkDragActive = true;
    e.dataTransfer.effectAllowed = 'move';
    const el = e.currentTarget;
    setTimeout(() => el.classList.add('dragging'), 0);
}
function csHauptkontaktDragEnd(e) {
    e.currentTarget.classList.remove('dragging');
    csHidePlaceholder?.();
    csHkDragActive = false;
}

window.csRenderHauptkontakt = function() {
    const uebersichtPanel = document.getElementById('cs-tab-uebersicht');
    if (!uebersichtPanel) return;
    // Alte Hauptkontakt-Card entfernen
    document.querySelectorAll('.cs-hauptkontakt-card').forEach(el => el.remove());
    const contactCards = sbCards.filter(c => c.type === 'contacts' && !c.is_system);
    let firstPerson = null;
    let groupTitle = null;
    let sourceCardId = null;
    let groupIdx = null;
    let personIdx = null;
    for (const card of contactCards) {
        const groups = card.body_decoded?.groups || [];
        for (let gi = 0; gi < groups.length; gi++) {
            const ppl = groups[gi].people || [];
            if (ppl.length > 0) {
                firstPerson = ppl[0];
                groupTitle = groups[gi].title || 'Hauptkontakt';
                sourceCardId = card.id;
                groupIdx = gi;
                personIdx = 0;
                break;
            }
        }
        if (firstPerson) break;
    }
    if (!firstPerson) return;
    const initials = (firstPerson.initials && firstPerson.initials.trim()) || csInitials(firstPerson.name);
    const card = document.createElement('div');
    card.className = 'sb-card cs-hauptkontakt-card';
    card.dataset.colSpan = '1';
    card.dataset.cardId = 'hauptkontakt';
    card.dataset.targetTab = 'uebersicht';
    card.dataset.columnIdx = String(csHauptkontaktState().col);
    card.draggable = true;
    card.ondragstart = (e) => csHauptkontaktDragStart(e);
    card.ondragend   = (e) => csHauptkontaktDragEnd(e);
    const hkTitle = csHauptkontaktState().title || 'Hauptkontakt';
    card.innerHTML = `
        <div class="sb-card-head">
            <div class="sb-card-icon" style="background: var(--slate-100);"><span class="material-symbols-rounded">contact_emergency</span></div>
            <div class="sb-card-title" contenteditable="plaintext-only"
                 onblur="csHauptkontaktSaveTitle(this.textContent)"
                 onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(hkTitle)}</div>
            <div class="sb-card-actions">
                <a class="sb-card-action" href="#card-${sourceCardId}" title="Zur Kontakte-Karte springen" onclick="event.preventDefault();csSetTab('personen');setTimeout(()=>{const t=document.getElementById('card-${sourceCardId}');t?.scrollIntoView({behavior:'smooth',block:'center'});t?.classList.add('sb-card-highlight');setTimeout(()=>t?.classList.remove('sb-card-highlight'),2000);},100);">
                    <span class="material-symbols-rounded">open_in_new</span>
                </a>
            </div>
        </div>
        <div class="sb-card-body" style="padding: 14px 16px;">
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
                <div class="cs-people-avatar" style="width:44px;height:44px;font-size:var(--d-fs-sm);">${csEscHtml(initials || '?')}</div>
                <div style="min-width:0;flex:1;">
                    <div style="font-weight:700; font-size:var(--d-fs-base); color:var(--slate-900);">${csEscHtml(firstPerson.name || '—')}</div>
                    <div style="font-size:var(--d-fs-xs); color:var(--slate-500);">${csEscHtml(firstPerson.role || groupTitle)}</div>
                </div>
            </div>
            ${firstPerson.email ? `<div style="display:flex;align-items:center;gap:8px;font-size:var(--d-fs-sm);color:var(--slate-700);margin-bottom:4px;">
                <span class="material-symbols-rounded" style="font-size:14px;color:var(--slate-400);">mail</span>
                <a href="mailto:${csEscHtml(firstPerson.email)}" style="color:var(--thoxan-700);text-decoration:none;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${csEscHtml(firstPerson.email)}</a>
            </div>` : ''}
            ${firstPerson.phone ? `<div style="display:flex;align-items:center;gap:8px;font-size:var(--d-fs-sm);color:var(--slate-700);">
                <span class="material-symbols-rounded" style="font-size:14px;color:var(--slate-400);">call</span>
                <a href="tel:${csEscHtml(firstPerson.phone)}" style="color:var(--thoxan-700);text-decoration:none;">${csEscHtml(firstPerson.phone)}</a>
            </div>` : ''}
        </div>
    `;
    // In gespeicherte Spalte einhaengen (Default col 2), oben
    const colIdx = csHauptkontaktState().col;
    const col = uebersichtPanel.querySelector(`.cs-col[data-col="${colIdx}"]`)
        || uebersichtPanel.querySelector('.cs-col[data-col="2"]');
    if (col) col.insertBefore(card, col.firstChild);
};

window.csRenderPersonen = function() {
    // Deprecated: Personen werden jetzt als normale contacts-Cards via csRouteCards
    // gerendert. Nur fuer Backward-Compat erhalten, macht nichts mehr.
    return;
    // eslint-disable-next-line no-unreachable
    const panel = document.querySelector('#cs-tab-personen .cs-tab-content') || document.getElementById('cs-tab-personen');
    if (!panel) return;
    const contactCards = sbCards.filter(c => c.type === 'contacts' && !c.is_system);
    let total = 0;
    contactCards.forEach(c => (c.body_decoded?.groups || []).forEach(g => total += (g.people || []).length));
    const cntEl = document.querySelector('.cs-tab-count[data-count-for="personen"]');
    if (cntEl) cntEl.textContent = total > 0 ? total : '';

    if (contactCards.length === 0) {
        panel.innerHTML = `
            <div class="cs-people-empty">
                <span class="material-symbols-rounded">groups</span>
                <h3 style="margin:0 0 8px;font-size:var(--d-fs-lg);color:var(--slate-800);">Noch keine Kontakte</h3>
                <p style="margin:0 0 14px;">Leg im Tab <strong>Inhalte</strong> eine Kontakte-Card an, oder ergänze sie hier:</p>
                <button class="cs-tab-placeholder-link" onclick="csPersonAddCard()">+ Kontakte-Card anlegen</button>
            </div>`;
        return;
    }
    let html = `
        <div class="cs-people-toolbar">
            <input class="cs-people-search" type="search" placeholder="Suchen in Name, Rolle, E-Mail, Telefon…" oninput="csPeopleFilter(this.value)">
            <button class="cs-tab-placeholder-link" onclick="csPersonAddCard()" style="background:var(--thoxan-700);"><span class="material-symbols-rounded" style="font-size:16px;color:#fff !important;">add</span>Neue Gruppe</button>
        </div>`;
    contactCards.forEach(card => {
        const groups = card.body_decoded?.groups || [];
        groups.forEach((g, gi) => {
            const ppl = g.people || [];
            html += `
                <div class="cs-people-group" data-card-id="${card.id}" data-gi="${gi}">
                    <div class="cs-people-group-head">
                        <span class="material-symbols-rounded">groups</span>
                        <span class="cs-people-cell-edit" contenteditable="true"
                              data-placeholder="Gruppenname (z.B. Firma, Abteilung)"
                              ${(g.title || '').trim() === '' ? 'data-empty="1"' : ''}
                              onblur="csPersonSaveGroup(${card.id}, ${gi}, 'title', this.textContent)"
                              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(g.title)}</span>
                        <span class="cs-people-source">${ppl.length} ${ppl.length === 1 ? 'Person' : 'Personen'}</span>
                    </div>
                    <div class="cs-people-tile-grid">`;
            ppl.forEach((p, pi) => {
                const initials = (p.initials && p.initials.trim()) || csInitials(p.name);
                html += `
                    <div class="cs-person-tile" data-pi="${pi}">
                        <button class="cs-person-tile-del" onclick="csPersonDelete(${card.id}, ${gi}, ${pi})" title="Person entfernen"><span class="material-symbols-rounded">close</span></button>
                        <div class="cs-person-tile-head">
                            <div class="cs-people-avatar cs-person-tile-avatar">${csEscHtml(initials || '?')}</div>
                            <div class="cs-person-tile-id">
                                <div class="cs-people-cell-edit cs-person-tile-name" contenteditable="true" data-placeholder="Vorname Nachname" ${!p.name ? 'data-empty="1"' : ''}
                                    onblur="csPersonSave(${card.id}, ${gi}, ${pi}, 'name', this.textContent)" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(p.name)}</div>
                                <div class="cs-people-cell-edit cs-person-tile-role" contenteditable="true" data-placeholder="Rolle" ${!p.role ? 'data-empty="1"' : ''}
                                    onblur="csPersonSave(${card.id}, ${gi}, ${pi}, 'role', this.textContent)" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(p.role)}</div>
                            </div>
                        </div>
                        <div class="cs-person-tile-fields">
                            <div class="cs-person-tile-field">
                                <span class="material-symbols-rounded cs-person-tile-icon">mail</span>
                                <span class="cs-people-cell-edit cs-person-tile-mail" contenteditable="true" data-placeholder="name@firma.de" ${!p.email ? 'data-empty="1"' : ''}
                                    onblur="csPersonSave(${card.id}, ${gi}, ${pi}, 'email', this.textContent)" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(p.email)}</span>
                                ${p.email ? `<a class="cs-person-tile-link" href="mailto:${csEscHtml(p.email)}" title="E-Mail schreiben"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                            </div>
                            <div class="cs-person-tile-field">
                                <span class="material-symbols-rounded cs-person-tile-icon">call</span>
                                <span class="cs-people-cell-edit cs-person-tile-phone" contenteditable="true" data-placeholder="+49 …" ${!p.phone ? 'data-empty="1"' : ''}
                                    onblur="csPersonSave(${card.id}, ${gi}, ${pi}, 'phone', this.textContent)" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(p.phone)}</span>
                                ${p.phone ? `<a class="cs-person-tile-link" href="tel:${csEscHtml(p.phone)}" title="Anrufen"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                            </div>
                            <div class="cs-person-tile-field cs-person-tile-note-field">
                                <span class="material-symbols-rounded cs-person-tile-icon">edit_note</span>
                                <span class="cs-people-cell-edit cs-person-tile-note" contenteditable="true" data-placeholder="Notiz zur Person" ${!p.note ? 'data-empty="1"' : ''}
                                    onblur="csPersonSave(${card.id}, ${gi}, ${pi}, 'note', this.textContent)" onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(p.note)}</span>
                            </div>
                        </div>
                    </div>`;
            });
            html += `
                        <button class="cs-person-tile-add" onclick="csPersonAdd(${card.id}, ${gi})">
                            <span class="material-symbols-rounded">person_add</span>
                            Person hinzufügen
                        </button>
                    </div>
                </div>`;
        });
    });
    panel.innerHTML = html;
};

window.csPersonSave = function(cardId, gi, pi, field, value) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const groups = JSON.parse(JSON.stringify(card.body_decoded?.groups || []));
    if (!groups[gi]?.people?.[pi]) return;
    groups[gi].people[pi][field] = String(value || '').trim();
    if (field === 'name') groups[gi].people[pi].initials = csInitials(groups[gi].people[pi].name);
    card.body_decoded.groups = groups;
    sbUpdateCard(cardId, { body: { groups } });
};
window.csPersonSaveGroup = function(cardId, gi, field, value) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const groups = JSON.parse(JSON.stringify(card.body_decoded?.groups || []));
    if (!groups[gi]) return;
    groups[gi][field] = String(value || '').trim();
    card.body_decoded.groups = groups;
    sbUpdateCard(cardId, { body: { groups } });
};
window.csPersonAdd = function(cardId, gi) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const groups = JSON.parse(JSON.stringify(card.body_decoded?.groups || []));
    if (!groups[gi]) return;
    groups[gi].people = groups[gi].people || [];
    groups[gi].people.push({ name: '', role: '', initials: '', email: '', phone: '', note: '' });
    card.body_decoded.groups = groups;
    sbUpdateCard(cardId, { body: { groups } });
    setTimeout(csRenderPersonen, 200);
};
window.csPersonDelete = function(cardId, gi, pi) {
    if (!confirm('Person wirklich entfernen?')) return;
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const groups = JSON.parse(JSON.stringify(card.body_decoded?.groups || []));
    if (!groups[gi]?.people) return;
    groups[gi].people.splice(pi, 1);
    card.body_decoded.groups = groups;
    sbUpdateCard(cardId, { body: { groups } });
    csRenderPersonen();
};
window.csPersonAddCard = function() {
    // Neue Kontakte-Card im Hintergrund anlegen, dann Tab neu rendern
    App.post('/admin/customers/' + customerId + '/cards', { type: 'contacts' }).then(() => {
        sbLoadCards().then(() => csSetTab('personen'));
    }).catch(e => App.showNotification(e.message || 'Fehler', 'error'));
};
window.csPeopleFilter = function(q) {
    q = String(q || '').toLowerCase().trim();
    document.querySelectorAll('#cs-tab-personen .cs-people-table tbody tr[data-pi]').forEach(tr => {
        const txt = tr.textContent.toLowerCase();
        tr.style.display = !q || txt.includes(q) ? '' : 'none';
    });
};

/* ===== Dateien-Tab: Browser ueber alle Card-Anhaenge ===== */
let csFilesFilter = 'all';
window.csRenderDateien = function() {
    return; // Deprecated — siehe csRenderPersonen
    // eslint-disable-next-line no-unreachable
    const panel = document.querySelector('#cs-tab-dateien .cs-tab-content') || document.getElementById('cs-tab-dateien');
    if (!panel) return;
    // Alle Files aller Cards einsammeln
    const all = [];
    sbCards.forEach(card => {
        (card.files || []).forEach(f => {
            const ext = (f.original_filename || f.stored_filename || '').split('.').pop().toLowerCase();
            const isImg = ['jpg','jpeg','png','gif','webp','svg'].includes(ext);
            const isPdf = ext === 'pdf';
            const isDoc = ['doc','docx','txt','md','rtf'].includes(ext);
            all.push({
                id: f.id,
                name: f.original_filename || 'Datei',
                url: f.public_url || '',
                ext, isImg, isPdf, isDoc,
                size: f.file_size || 0,
                date: f.created_at || '',
                card_id: card.id,
                card_title: card.title || ''
            });
        });
    });
    document.querySelector('.cs-tab-count[data-count-for="dateien"]').textContent = all.length > 0 ? all.length : '';

    if (all.length === 0) {
        panel.innerHTML = `
            <div class="cs-files-empty">
                <span class="material-symbols-rounded">folder_open</span>
                <h3 style="margin:0 0 8px;font-size:var(--d-fs-lg);color:var(--slate-800);">Keine Dateien</h3>
                <p style="margin:0;">Anhaenge an Dokumente- oder Bilder-Cards landen hier — leg im Tab <strong>Inhalte</strong> eine entsprechende Card an.</p>
            </div>`;
        return;
    }
    const counts = { all: all.length, pdf: 0, doc: 0, img: 0 };
    all.forEach(f => { if (f.isPdf) counts.pdf++; else if (f.isImg) counts.img++; else if (f.isDoc) counts.doc++; });
    const f = csFilesFilter;
    const filtered = all.filter(it => {
        if (f === 'all') return true;
        if (f === 'pdf') return it.isPdf;
        if (f === 'img') return it.isImg;
        if (f === 'doc') return it.isDoc;
        return true;
    });
    const chips = [
        { k: 'all', label: 'Alle', n: counts.all },
        { k: 'pdf', label: 'PDF', n: counts.pdf },
        { k: 'doc', label: 'Dokumente', n: counts.doc },
        { k: 'img', label: 'Bilder', n: counts.img },
    ];
    let html = `
        <div class="cs-files-toolbar">
            <input class="cs-files-search" type="search" placeholder="Suchen in Dateiname…" oninput="csFilesFilterSearch(this.value)">
            <div class="cs-files-filter-chips">
                ${chips.map(c => `<button class="cs-files-chip ${c.k === f ? 'active' : ''}" onclick="csFilesSetFilter('${c.k}')">${c.label} <span style="opacity:0.7;">(${c.n})</span></button>`).join('')}
            </div>
        </div>
        <div class="cs-files-grid">
            <div class="cs-files-row is-head">
                <div></div>
                <div>Name</div>
                <div>Typ</div>
                <div>Größe</div>
                <div>Datum</div>
                <div></div>
            </div>`;
    filtered.forEach(it => {
        const iconClass = it.isPdf ? 'cs-files-icon-pdf' : (it.isImg ? 'cs-files-icon-img' : (it.isDoc ? 'cs-files-icon-doc' : ''));
        const iconSym = it.isPdf ? 'picture_as_pdf' : (it.isImg ? 'image' : (it.isDoc ? 'description' : 'attach_file'));
        const sizeKb = it.size ? (it.size > 1024*1024 ? (it.size/1024/1024).toFixed(1) + ' MB' : Math.round(it.size/1024) + ' KB') : '';
        const date = it.date ? it.date.substring(0, 10) : '';
        html += `
            <div class="cs-files-row" data-name="${csEscHtml(it.name.toLowerCase())}">
                <div class="cs-files-icon ${iconClass}"><span class="material-symbols-rounded">${iconSym}</span></div>
                <div class="cs-files-name">${csEscHtml(it.name)}<span class="cs-files-name-source">aus „${csEscHtml(it.card_title)}"</span></div>
                <div class="cs-files-type">${csEscHtml(it.ext)}</div>
                <div class="cs-files-size">${sizeKb}</div>
                <div class="cs-files-date">${date}</div>
                <div class="cs-files-actions">
                    ${it.url ? `<a class="cs-files-action" href="${csEscHtml(it.url)}" target="_blank" title="Öffnen"><span class="material-symbols-rounded">open_in_new</span></a>` : ''}
                    ${it.url ? `<a class="cs-files-action" href="${csEscHtml(it.url)}" download title="Herunterladen"><span class="material-symbols-rounded">download</span></a>` : ''}
                </div>
            </div>`;
    });
    html += `</div>`;
    panel.innerHTML = html;
};
window.csFilesSetFilter = function(k) { csFilesFilter = k; csRenderDateien(); };
window.csFilesFilterSearch = function(q) {
    q = String(q || '').toLowerCase().trim();
    document.querySelectorAll('#cs-tab-dateien .cs-files-row:not(.is-head)').forEach(r => {
        r.style.display = !q || (r.dataset.name || '').includes(q) ? '' : 'none';
    });
};

/* ===== Marke-Tab: Logo, Farben, Schriften, Notizen aus brand-Cards ===== */
window.csRenderMarke = function() {
    return; // Deprecated — siehe csRenderPersonen
    // eslint-disable-next-line no-unreachable
    const panel = document.querySelector('#cs-tab-marke .cs-tab-content') || document.getElementById('cs-tab-marke');
    if (!panel) return;
    const brandCards = sbCards.filter(c => c.type === 'brand' && !c.is_system);
    // Logo aus dem Profil holen
    const logoImg = document.querySelector('#cs-logo-wrap img')?.src || '';
    const colors = [];
    const fonts = [];
    const notes = [];
    brandCards.forEach(c => {
        (c.body_decoded?.colors || []).forEach(co => colors.push(co));
        (c.body_decoded?.fonts  || []).forEach(fo => fonts.push(fo));
        const n = (c.body_decoded?.note || '').trim();
        if (n) notes.push({ title: c.title || 'Notiz', text: n });
    });
    const total = colors.length + fonts.length + (logoImg ? 1 : 0);
    document.querySelector('.cs-tab-count[data-count-for="marke"]').textContent = total > 0 ? total : '';

    if (!logoImg && colors.length === 0 && fonts.length === 0 && notes.length === 0) {
        panel.innerHTML = `
            <div class="cs-brand-empty">
                <span class="material-symbols-rounded">palette</span>
                <h3 style="margin:0 0 8px;font-size:var(--d-fs-lg);color:var(--slate-800);">Keine Markenangaben</h3>
                <p style="margin:0;">Leg im Tab <strong>Inhalte</strong> eine „Markenidentität"-Card an — Farben und Schriften erscheinen dann hier.</p>
                <a class="cs-brand-empty-action" href="javascript:csBrandAddCard()">+ Markenidentität-Card anlegen</a>
            </div>`;
        return;
    }
    // Indexierte Farb-/Schrift-Liste — pro Eintrag merken wir uns die Quell-Card-ID,
    // damit Edits zurueck in die richtige brand-Card geschrieben werden.
    const flatColors = [];
    const flatFonts = [];
    brandCards.forEach(c => {
        (c.body_decoded?.colors || []).forEach((co, i) => flatColors.push({ ...co, cardId: c.id, idx: i }));
        (c.body_decoded?.fonts  || []).forEach((fo, i) => flatFonts.push({ ...fo,  cardId: c.id, idx: i }));
    });

    let html = `<div class="cs-brand-grid">`;
    html += `
        <div class="cs-brand-section cs-brand-section-logo">
            <h3><span class="material-symbols-rounded">image</span>Logo</h3>
            <div class="cs-brand-logo-wrap">
                ${logoImg ? `<img src="${csEscHtml(logoImg)}" alt="Logo">` : `<span class="cs-brand-no-logo">Noch kein Logo hochgeladen</span>`}
            </div>
            <p style="font-size:var(--d-fs-xs);color:var(--slate-500);margin:10px 0 0;text-align:center;">Logo wird im Profil verwaltet (Übersicht-Tab).</p>
        </div>`;
    html += `
        <div class="cs-brand-section cs-brand-section-colors">
            <h3><span class="material-symbols-rounded">palette</span>Farbpalette · ${flatColors.length}
                <button class="cs-brand-add-btn" onclick="csBrandAddColor()" style="margin-left:auto;background:transparent;border:0;cursor:pointer;color:var(--thoxan-700);font-size:var(--d-fs-xs);font-weight:600;display:inline-flex;align-items:center;gap:4px;font-family:inherit;"><span class="material-symbols-rounded" style="font-size:14px;color:inherit;">add</span>Farbe</button>
            </h3>
            ${flatColors.length === 0
                ? `<p style="color:var(--slate-400);font-size:var(--d-fs-sm);margin:0;">Noch keine Farben — Klick auf „+ Farbe" oben rechts.</p>`
                : `<div class="cs-brand-color-list">${flatColors.map(c => `
                    <div class="cs-brand-color" data-card-id="${c.cardId}" data-idx="${c.idx}">
                        <div class="cs-brand-color-swatch" style="background:${csEscHtml(c.value)};position:relative;" title="Klick: Farbe ändern">
                            <input type="color" value="${csEscHtml(c.value || '#000000')}" oninput="csBrandSaveColor(${c.cardId}, ${c.idx}, 'value', this.value); this.parentElement.style.background=this.value;" style="position:absolute;top:0;left:0;opacity:0;width:100%;height:100%;cursor:pointer;">
                        </div>
                        <div class="cs-brand-color-meta">
                            <span class="cs-people-cell-edit" contenteditable="true" data-placeholder="Name (z.B. Primaerblau)" ${!c.name ? 'data-empty="1"' : ''}
                                  onblur="csBrandSaveColor(${c.cardId}, ${c.idx}, 'name', this.textContent)"
                                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(c.name)}</span>
                            <div class="cs-brand-color-value">
                                <span class="cs-people-cell-edit" contenteditable="true" data-placeholder="#004c9b"
                                  onblur="csBrandSaveColor(${c.cardId}, ${c.idx}, 'value', this.textContent.trim())"
                                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(c.value)}</span>
                                <button onclick="csBrandCopy('${csEscHtml(c.value)}')" title="HEX kopieren" style="background:transparent;border:0;cursor:pointer;color:var(--slate-400);margin-left:6px;"><span class="material-symbols-rounded" style="font-size:13px;color:inherit;">content_copy</span></button>
                                <button onclick="csBrandDeleteColor(${c.cardId}, ${c.idx})" title="Farbe entfernen" style="background:transparent;border:0;cursor:pointer;color:var(--slate-400);margin-left:2px;"><span class="material-symbols-rounded" style="font-size:13px;color:inherit;">close</span></button>
                            </div>
                        </div>
                    </div>`).join('')}</div>`}
        </div>`;
    html += `
        <div class="cs-brand-section cs-brand-section-fonts">
            <h3><span class="material-symbols-rounded">text_fields</span>Schriften · ${flatFonts.length}
                <button class="cs-brand-add-btn" onclick="csBrandAddFont()" style="margin-left:auto;background:transparent;border:0;cursor:pointer;color:var(--thoxan-700);font-size:var(--d-fs-xs);font-weight:600;display:inline-flex;align-items:center;gap:4px;font-family:inherit;"><span class="material-symbols-rounded" style="font-size:14px;color:inherit;">add</span>Schrift</button>
            </h3>
            ${flatFonts.length === 0
                ? `<p style="color:var(--slate-400);font-size:var(--d-fs-sm);margin:0;">Noch keine Schriften — Klick auf „+ Schrift".</p>`
                : `<div class="cs-brand-font-list">${flatFonts.map(f => `
                    <div class="cs-brand-font" style="position:relative;">
                        <button onclick="csBrandDeleteFont(${f.cardId}, ${f.idx})" title="Schrift entfernen" style="position:absolute;top:6px;right:6px;background:transparent;border:0;cursor:pointer;color:var(--slate-400);"><span class="material-symbols-rounded" style="font-size:14px;color:inherit;">close</span></button>
                        <div class="cs-brand-font-name" style="font-family:'${csEscHtml(f.name)}', sans-serif;">
                            <span class="cs-people-cell-edit" contenteditable="true" data-placeholder="Schriftname" ${!f.name ? 'data-empty="1"' : ''}
                                onblur="csBrandSaveFont(${f.cardId}, ${f.idx}, 'name', this.textContent)"
                                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(f.name)}</span>
                        </div>
                        <div class="cs-brand-font-note">
                            <span class="cs-people-cell-edit" contenteditable="true" data-placeholder="z.B. Headlines, Regular 16px" ${!f.note ? 'data-empty="1"' : ''}
                                onblur="csBrandSaveFont(${f.cardId}, ${f.idx}, 'note', this.textContent)"
                                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${csEscHtml(f.note)}</span>
                        </div>
                    </div>`).join('')}</div>`}
        </div>`;
    if (notes.length > 0 || brandCards.length > 0) {
        html += `
            <div class="cs-brand-section cs-brand-section-notes">
                <h3><span class="material-symbols-rounded">edit_note</span>Notizen</h3>
                ${brandCards.map(c => `
                    <div style="margin-bottom:12px;">
                        <div style="font-weight:700;font-size:var(--d-fs-sm);color:var(--slate-700);margin-bottom:4px;">${csEscHtml(c.title || 'Notiz')}</div>
                        <div class="cs-brand-notes-text cs-people-cell-edit" contenteditable="true" data-placeholder="Notizen zur Marke (Tonalitaet, Don'ts …)" ${!(c.body_decoded?.note || '').trim() ? 'data-empty="1"' : ''}
                             onblur="csBrandSaveNote(${c.id}, this.textContent)"
                             style="display:block;min-height:40px;">${csEscHtml(c.body_decoded?.note || '')}</div>
                    </div>`).join('')}
            </div>`;
    }
    html += `</div>`;
    panel.innerHTML = html;
};

/* Brand-Edit-Helfer */
function csBrandPrimaryCardId() {
    const bc = sbCards.filter(c => c.type === 'brand' && !c.is_system);
    return bc.length > 0 ? bc[0].id : null;
}
window.csBrandSaveColor = function(cardId, idx, field, value) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.colors = body.colors || [];
    if (!body.colors[idx]) return;
    body.colors[idx][field] = String(value || '').trim();
    card.body_decoded = body;
    sbUpdateCard(cardId, { body });
};
window.csBrandSaveFont = function(cardId, idx, field, value) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.fonts = body.fonts || [];
    if (!body.fonts[idx]) return;
    body.fonts[idx][field] = String(value || '').trim();
    card.body_decoded = body;
    sbUpdateCard(cardId, { body });
};
window.csBrandSaveNote = function(cardId, value) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.note = String(value || '').trim();
    card.body_decoded = body;
    sbUpdateCard(cardId, { body });
};
window.csBrandAddColor = async function() {
    let cid = csBrandPrimaryCardId();
    if (!cid) {
        const r = await App.post('/admin/customers/' + customerId + '/cards', { type: 'brand' });
        if (!r.success) return;
        sbCards.push(r.data); cid = r.data.id;
    }
    const card = sbCards.find(c => c.id == cid);
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.colors = body.colors || [];
    body.colors.push({ name: '', value: '#000000' });
    card.body_decoded = body;
    sbUpdateCard(cid, { body });
    setTimeout(csRenderMarke, 150);
};
window.csBrandAddFont = async function() {
    let cid = csBrandPrimaryCardId();
    if (!cid) {
        const r = await App.post('/admin/customers/' + customerId + '/cards', { type: 'brand' });
        if (!r.success) return;
        sbCards.push(r.data); cid = r.data.id;
    }
    const card = sbCards.find(c => c.id == cid);
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.fonts = body.fonts || [];
    body.fonts.push({ name: '', note: '' });
    card.body_decoded = body;
    sbUpdateCard(cid, { body });
    setTimeout(csRenderMarke, 150);
};
window.csBrandDeleteColor = function(cardId, idx) {
    if (!confirm('Farbe wirklich entfernen?')) return;
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.colors = body.colors || [];
    body.colors.splice(idx, 1);
    card.body_decoded = body;
    sbUpdateCard(cardId, { body });
    csRenderMarke();
};
window.csBrandDeleteFont = function(cardId, idx) {
    if (!confirm('Schrift wirklich entfernen?')) return;
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const body = JSON.parse(JSON.stringify(card.body_decoded || { colors: [], fonts: [], note: '' }));
    body.fonts = body.fonts || [];
    body.fonts.splice(idx, 1);
    card.body_decoded = body;
    sbUpdateCard(cardId, { body });
    csRenderMarke();
};
window.csBrandCopy = function(value) {
    if (!value) return;
    navigator.clipboard?.writeText(value).then(() => App.showNotification(value + ' kopiert', 'success'));
};
window.csBrandAddCard = function() {
    App.post('/admin/customers/' + customerId + '/cards', { type: 'brand' }).then(() => {
        sbLoadCards().then(() => csSetTab('inhalte'));
        App.showNotification('Markenidentität-Card angelegt — befüllen im Tab Inhalte', 'success');
    }).catch(e => App.showNotification(e.message || 'Fehler', 'error'));
};

/* ===== Rechtsklick-Kontextmenue auf Karten (nur Layout-Edit-Modus) =====
   Erlaubt: Verschieben in jeden Tab + Spalte, Groesse aendern, Loeschen. */
let csCtxMenu = null;
function csEnsureCtxMenu() {
    if (csCtxMenu) return csCtxMenu;
    csCtxMenu = document.createElement('div');
    csCtxMenu.className = 'cs-ctx-menu';
    csCtxMenu.style.cssText = 'position:fixed; z-index:9999; display:none; background:#fff; border:1px solid var(--slate-200); border-radius:10px; box-shadow:0 12px 32px rgba(15,23,42,0.16); padding:6px; min-width:240px; font-family:inherit; font-size:var(--d-fs-sm);';
    document.body.appendChild(csCtxMenu);
    document.addEventListener('click', (e) => {
        if (!csCtxMenu.contains(e.target)) csHideCtxMenu();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') csHideCtxMenu(); });
    return csCtxMenu;
}
function csHideCtxMenu() { if (csCtxMenu) csCtxMenu.style.display = 'none'; }

document.addEventListener('contextmenu', (e) => {
    if (!document.body.classList.contains('cs-layout-edit')) return;
    const card = e.target.closest('.sb-card[data-card-id], .cs-card-shell[data-plan-id]');
    if (!card) return;
    e.preventDefault();
    const isShell = card.classList.contains('cs-card-shell');
    const isHk    = card.classList.contains('cs-hauptkontakt-card');
    const cardId = card.dataset.cardId;
    const curTab = isHk ? 'uebersicht' : (card.dataset.targetTab || (isShell ? (csShellLoadState().tab) : 'inhalte'));
    const curCol = parseInt(card.dataset.columnIdx || (isShell ? (csShellLoadState().col) : 2), 10);
    const curSize = isShell ? csShellLoadState().w : parseInt(card.dataset.colSpan || '1', 10);
    const menu = csEnsureCtxMenu();
    const tabs = [
        { k: 'uebersicht', l: 'Übersicht', i: 'dashboard' },
        { k: 'inhalte',    l: 'Inhalte',    i: 'edit_note' },
        { k: 'personen',   l: 'Personen',   i: 'groups' },
        { k: 'dateien',    l: 'Dateien',    i: 'folder_zip' },
        { k: 'marke',      l: 'Marke',      i: 'palette' },
        { k: 'sonstiges',  l: 'Sonstiges',  i: 'inbox' },
    ];
    const cols = [1, 2, 3];
    const sizes = [
        { v: 1, l: '1 Spalte' },
        { v: 2, l: '2 Spalten' },
        { v: 3, l: 'Volle Breite (Hero)' },
    ];
    menu.innerHTML = `
        <div class="cs-ctx-section-title">Verschieben in Tab</div>
        ${tabs.map(t => `<button class="cs-ctx-item ${t.k === curTab ? 'is-active' : ''}" data-action="tab" data-tab="${t.k}"><span class="material-symbols-rounded">${t.i}</span>${t.l}</button>`).join('')}
        <div class="cs-ctx-sep"></div>
        <div class="cs-ctx-section-title">Spalte</div>
        ${cols.map(c => `<button class="cs-ctx-item ${c === curCol ? 'is-active' : ''}" data-action="col" data-col="${c}"><span class="material-symbols-rounded">view_column</span>Spalte ${c}</button>`).join('')}
        <div class="cs-ctx-sep"></div>
        <div class="cs-ctx-section-title">Größe</div>
        ${sizes.map(s => `<button class="cs-ctx-item ${s.v === curSize ? 'is-active' : ''}" data-action="size" data-size="${s.v}"><span class="material-symbols-rounded">aspect_ratio</span>${s.l}</button>`).join('')}
        ${!isShell ? `<div class="cs-ctx-sep"></div>
        <button class="cs-ctx-item is-danger" data-action="delete"><span class="material-symbols-rounded">delete</span>Karte löschen</button>` : ''}
    `;
    // Positionieren — bleibt im Viewport
    const vw = window.innerWidth, vh = window.innerHeight;
    menu.style.display = 'block';
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    menu.style.left = Math.min(e.clientX, vw - mw - 8) + 'px';
    menu.style.top  = Math.min(e.clientY, vh - mh - 8) + 'px';
    // Actions
    menu.querySelectorAll('.cs-ctx-item').forEach(btn => {
        btn.onclick = () => {
            const a = btn.dataset.action;
            if (a === 'tab') {
                if (isShell) csShellSetTargetTab(btn.dataset.tab);
                else sbSetTargetTab(parseInt(cardId, 10), btn.dataset.tab);
            } else if (a === 'col') {
                const newCol = parseInt(btn.dataset.col, 10);
                if (isShell) {
                    csShellSaveState({ col: newCol });
                    const target = document.querySelector(`.cs-kanban#cs-tab-${curTab} .cs-col[data-col="${newCol}"]`);
                    if (target) target.appendChild(card);
                } else if (isHk) {
                    csHauptkontaktSave({ col: newCol });
                    const target = document.querySelector(`.cs-kanban#cs-tab-uebersicht .cs-col[data-col="${newCol}"]`);
                    if (target) target.appendChild(card);
                } else {
                    card.dataset.columnIdx = String(newCol);
                    const lc = sbCards.find(c => c.id == cardId); if (lc) lc.column_idx = newCol;
                    const target = document.querySelector(`.cs-kanban#cs-tab-${curTab} .cs-col[data-col="${newCol}"]`);
                    if (target) target.appendChild(card);
                    csPersistKanbanLayout(curTab);
                }
            } else if (a === 'size') {
                const newW = parseInt(btn.dataset.size, 10);
                if (isShell) csShellSetSize(newW);
                else if (!isHk) sbSetSize(parseInt(cardId, 10), newW, 1);
            } else if (a === 'delete') {
                if (!isHk && !isShell) sbDeleteCard(parseInt(cardId, 10));
            }
            csHideCtxMenu();
        };
    });
});

/* Layout-Edit-Modus: Admin-Toggle. Standardmaessig OFF — Layout bleibt
   konsistent ueber alle Kunden, Resize/Move/Delete sind verborgen. */
/* ===== Layout-Templates: Speichern + Anwenden ===== */
window.csTplOpenSave = function() {
    document.getElementById('cs-tpl-save-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('cs-tpl-save-name')?.focus(), 50);
};
window.csTplCloseSave = function() {
    document.getElementById('cs-tpl-save-modal').style.display = 'none';
};
window.csTplSubmitSave = async function() {
    const name = document.getElementById('cs-tpl-save-name').value.trim();
    const desc = document.getElementById('cs-tpl-save-desc').value.trim();
    if (!name) { App.showNotification('Name fehlt', 'error'); return; }
    try {
        const r = await App.post('/admin/card-layout-templates', {
            customer_id: customerId,
            name: name,
            description: desc || null,
        });
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification('Template gespeichert', 'success');
        csTplCloseSave();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.csTplOpenApply = async function() {
    const modal = document.getElementById('cs-tpl-apply-modal');
    const body = document.getElementById('cs-tpl-apply-body');
    modal.style.display = 'flex';
    body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--slate-400);">Lade Templates…</div>';
    try {
        const r = await App.get('/admin/card-layout-templates');
        if (!r.success) throw new Error(r.message || 'Fehler');
        const tpls = r.data.templates || [];
        if (tpls.length === 0) {
            body.innerHTML = `
                <div style="text-align:center;padding:40px;color:var(--slate-500);">
                    <span class="material-symbols-rounded" style="font-size:36px;color:var(--slate-300);">layers</span>
                    <h3 style="margin:8px 0;color:var(--slate-800);">Noch keine Templates gespeichert</h3>
                    <p style="font-size:var(--d-fs-sm);">Speichere zuerst auf einem anderen Kunden ein Layout über „Als Standard speichern".</p>
                </div>`;
            return;
        }
        body.innerHTML = `
            <p style="font-size:var(--d-fs-sm);color:var(--slate-600);margin:0 0 12px;">
                Wende ein gespeichertes Layout auf diesen Kunden an. Inhalte bleiben erhalten, Karten ohne
                passendes Template-Item wandern in den Tab <strong>Sonstiges</strong>.
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                ${tpls.map(t => `
                    <div class="cs-tpl-card" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--slate-200);border-radius:10px;background:#fff;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:var(--slate-900);">${esc(t.name)}</div>
                            <div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;">
                                ${t.item_count} Karten · aus ${esc(t.source_customer_name || 'unbekannt')} · ${t.updated_at?.substring(0,10) || ''}
                            </div>
                            ${t.description ? `<div style="font-size:var(--d-fs-xs);color:var(--slate-600);margin-top:4px;font-style:italic;">${esc(t.description)}</div>` : ''}
                        </div>
                        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="csTplApply(${t.id}, '${esc(t.name).replace(/'/g, '\\\\\'')}')">Anwenden</button>
                        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="csTplDelete(${t.id}, '${esc(t.name).replace(/'/g, '\\\\\'')}')" title="Template löschen">
                            <span class="material-symbols-rounded" style="font-size:14px;color:var(--rose-500);">delete</span>
                        </button>
                    </div>
                `).join('')}
            </div>`;
    } catch (e) {
        body.innerHTML = `<div style="padding:20px;color:var(--rose-700);">${esc(e.message || 'Fehler')}</div>`;
    }
};
window.csTplCloseApply = function() {
    document.getElementById('cs-tpl-apply-modal').style.display = 'none';
};
window.csTplApply = async function(templateId, tplName) {
    if (!confirm(`Template "${tplName}" auf diesen Kunden anwenden? Bestehende Karten-Anordnung wird überschrieben (Inhalte bleiben erhalten).`)) return;
    try {
        const r = await App.post('/admin/customers/' + customerId + '/apply-layout-template', { template_id: templateId });
        if (!r.success) throw new Error(r.message || 'Fehler');
        const s = r.data || {};
        const parts = [];
        if (s.matched_system) parts.push(s.matched_system + ' System-Karten zugeordnet');
        if (s.matched_user) parts.push(s.matched_user + ' Karten erkannt');
        if (s.created) parts.push(s.created + ' neue Karten angelegt');
        if (s.orphaned) parts.push(s.orphaned + ' Karten nach Sonstiges verschoben');
        App.showNotification('Layout angewendet — ' + (parts.join(', ') || 'keine Änderungen'), 'success');
        csTplCloseApply();
        // Frisch laden + Counts explizit auffrischen, nachdem sbRenderCards durch ist
        await sbLoadCards();
        if (typeof csUpdateDedicatedCounts === 'function') csUpdateDedicatedCounts();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};
window.csTplDelete = async function(templateId, tplName) {
    if (!confirm(`Template "${tplName}" wirklich löschen?`)) return;
    try {
        await App.delete('/admin/card-layout-templates/' + templateId);
        App.showNotification('Template gelöscht', 'success');
        csTplOpenApply(); // Liste neu laden
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.csToggleLayoutEdit = function() {
    const on = !document.body.classList.contains('cs-layout-edit');
    document.body.classList.toggle('cs-layout-edit', on);
    const label = document.getElementById('cs-layout-edit-label');
    if (label) label.textContent = on ? 'Layout fertig' : 'Layout anpassen';
};

window.csSetTab = function(t) {
    document.querySelectorAll('.cs-tab').forEach(b => b.classList.toggle('is-active', b.dataset.tab === t));
    document.querySelectorAll('.cs-tab-panel').forEach(p => {
        p.style.display = p.id === ('cs-tab-' + t) ? '' : 'none';
    });
    // URL-Hash aktualisieren
    try {
        if (window.location.hash !== '#' + t) history.replaceState(null, '', '#' + t);
    } catch (_) {}
};

// Initial-Tab aus URL-Hash setzen (falls vorhanden)
document.addEventListener('DOMContentLoaded', () => {
    const hashTab = (window.location.hash || '').replace('#', '');
    if (hashTab && document.querySelector(`.cs-tab[data-tab="${hashTab}"]`)) {
        window.csSetTab(hashTab);
    }
    // Plan-Widget-Shell: gespeicherte Groesse + Ziel-Tab anwenden
    csShellInit();
});

/* ===== Plan-Widget-Shell (cs-card-shell) — Resize + Move-Tab =====
   Persistenz per localStorage pro Kunde: cs_plan_shell_<customerId> = { w, tab }. */
const CS_SHELL_KEY = () => 'cs_plan_shell_' + customerId;
function csShellLoadState() {
    const DEF = { w: 1, tab: 'uebersicht', col: 1, beforeRef: null, title: 'Aktiver Plan' };
    try {
        const raw = localStorage.getItem(CS_SHELL_KEY());
        if (!raw) return { ...DEF };
        const s = JSON.parse(raw);
        let w = (typeof s.w === 'number') ? s.w : DEF.w;
        if (w < 1) w = 1; else if (w > 3) w = (w >= 8 ? 3 : (w >= 4 ? 2 : 1));
        let col = (typeof s.col === 'number' && s.col >= 1 && s.col <= 3) ? s.col : DEF.col;
        let validTabs = ['uebersicht','personen','websites','inhalte','dateien','marke','sonstiges'];
        let tab = validTabs.includes(s.tab) ? s.tab : 'uebersicht';
        let beforeRef = (typeof s.beforeRef === 'string' && s.beforeRef) ? s.beforeRef : null;
        let title = (typeof s.title === 'string' && s.title.trim()) ? s.title : DEF.title;
        return { w, col, tab, beforeRef, title };
    } catch (_) { return { ...DEF }; }
}
window.csShellSaveTitle = function(value) {
    const t = (value || '').trim() || 'Aktiver Plan';
    csShellSaveState({ title: t });
};
function csShellSaveState(patch) {
    const cur = csShellLoadState();
    const next = Object.assign(cur, patch);
    next._v = 2; // Versions-Marker — Loader weiss dann, dass keine Legacy-Migration noetig ist
    try { localStorage.setItem(CS_SHELL_KEY(), JSON.stringify(next)); } catch (_) {}
    return next;
}
function csShellInit() {
    const shell = document.getElementById('cs-uebersicht-plan');
    if (!shell) return;
    const state = csShellLoadState();
    // Move-Pop / Size-Cells aktualisieren (legacy, bleiben fuer den Moment)
    document.querySelectorAll('#cs-shell-move-pop .sb-move-item').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === state.tab);
    });
    const grid = document.getElementById('cs-shell-size-grid');
    if (grid) {
        // Kanban-Modus: 1 Spalte / 2 Spalten / volle Breite (Hero)
        const sizes = [
            { v: 1, l: '1 Spalte',     t: 'Normale Spaltenkarte' },
            { v: 2, l: '2 Spalten',    t: 'Hero ueber 2 Spalten' },
            { v: 3, l: 'Volle Breite', t: 'Hero ueber alle 3 Spalten' },
        ];
        grid.innerHTML = sizes.map(s =>
            `<button class="sb-size-cell ${s.v === state.w ? 'active' : ''}" data-w="${s.v}" onclick="csShellSetSize(${s.v})" title="${s.t}">${s.l}</button>`
        ).join('');
    }
    // Gespeicherten Titel anwenden
    const titleEl = shell.querySelector('.cs-card-shell-title');
    if (titleEl && state.title && titleEl.textContent.trim() !== state.title) {
        titleEl.textContent = state.title;
    }
    // Shell in seine Ziel-Spalte umhaengen (Kanban-Layout)
    const targetCol = document.querySelector(`.cs-kanban#cs-tab-${state.tab} .cs-col[data-col="${state.col}"]`);
    if (targetCol && shell.parentElement !== targetCol) targetCol.appendChild(shell);
    // Position innerhalb der Spalte: vor einer bestimmten Karte
    if (state.beforeRef && state.beforeRef.startsWith('card:')) {
        const anchor = targetCol?.querySelector(`.sb-card[data-card-id="${state.beforeRef.slice(5)}"]`);
        if (anchor && anchor.parentElement === shell.parentElement) {
            shell.parentElement.insertBefore(shell, anchor);
        }
    }
}
window.csShellToggleSizePop = function(e) {
    e.stopPropagation();
    document.querySelectorAll('.sb-size-pop.open, .sb-move-pop.open').forEach(p => { if (p.id !== 'cs-shell-size-pop') p.classList.remove('open'); });
    document.getElementById('cs-shell-size-pop')?.classList.toggle('open');
};
window.csShellToggleMovePop = function(e) {
    e.stopPropagation();
    document.querySelectorAll('.sb-size-pop.open, .sb-move-pop.open').forEach(p => { if (p.id !== 'cs-shell-move-pop') p.classList.remove('open'); });
    document.getElementById('cs-shell-move-pop')?.classList.toggle('open');
};
window.csShellSetSize = function(w) {
    const shell = document.getElementById('cs-uebersicht-plan');
    if (!shell) return;
    shell.dataset.w = w;
    shell.dataset.colSpan = w;
    csShellSaveState({ w });
    // Kanban-Routing: w=1 → Spalte (gespeicherte Col), w≥2 → Hero-Zone
    const state = csShellLoadState();
    let target = null;
    if (w >= 2) {
        target = document.querySelector(`.cs-kanban#cs-tab-${state.tab} .cs-hero`);
    } else {
        target = document.querySelector(`.cs-kanban#cs-tab-${state.tab} .cs-col[data-col="${state.col}"]`);
    }
    if (target && shell.parentElement !== target) target.appendChild(shell);
    document.querySelectorAll('#cs-shell-size-grid .sb-size-cell').forEach(c => c.classList.toggle('active', +c.dataset.w === w));
};
/* Panel-Level Drop: feuert wenn Drop NICHT auf einer Card landet (leerer
   Panel-Bereich). Dropped-Element wird ans Ende des Panels gehaengt. */
/* ===== Kanban-Spalten: Drop-Handler =====
   csColDragOver/Drop ersetzen das alte 12er-Grid-Panel-System. */
window.csColDragOver = function(e) {
    if (!sbDragId && !csShellDragActive) return;
    e.preventDefault();
    const col = e.currentTarget;
    // Position berechnen anhand der Karten in der Spalte (vertikal)
    const items = Array.from(col.querySelectorAll(':scope > .sb-card:not(.dragging), :scope > .cs-card-shell:not(.dragging)'));
    let insertBefore = null;
    for (const item of items) {
        const rect = item.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        if (e.clientY < midY) { insertBefore = item; break; }
    }
    csEnsurePlaceholder();
    if (insertBefore) {
        if (csDragPlaceholder !== insertBefore.previousElementSibling) {
            col.insertBefore(csDragPlaceholder, insertBefore);
        }
    } else {
        if (col.lastElementChild !== csDragPlaceholder) col.appendChild(csDragPlaceholder);
    }
};
window.csColDrop = async function(e) {
    if (!sbDragId && !csShellDragActive) return;
    e.preventDefault();
    const col = e.currentTarget;
    // 'hero' → column_idx=0, sonst 1/2/3
    const newColIdx = col.dataset.col === 'hero' ? 0 : (parseInt(col.dataset.col, 10) || 2);
    const newTab = col.dataset.tab;
    // Shell-Drop
    if (csShellDragActive) {
        const shell = document.getElementById('cs-uebersicht-plan');
        if (shell) {
            if (csDragPlaceholder && csDragPlaceholder.parentElement === col) {
                col.insertBefore(shell, csDragPlaceholder);
            } else {
                col.appendChild(shell);
            }
            csShellSaveState({ col: newColIdx, tab: newTab });
        }
        csHidePlaceholder();
        csShellDragActive = false;
        return;
    }
    // Card-Drop
    const card = document.querySelector(`.sb-card[data-card-id="${sbDragId}"]`);
    if (!card) { csHidePlaceholder(); sbDragId = null; return; }
    if (csDragPlaceholder && csDragPlaceholder.parentElement === col) {
        col.insertBefore(card, csDragPlaceholder);
    } else {
        col.appendChild(card);
    }
    card.dataset.columnIdx = String(newColIdx);
    card.dataset.targetTab = newTab;
    const localCard = sbCards.find(c => c.id == sbDragId);
    if (localCard) {
        localCard.column_idx = newColIdx;
        localCard.target_tab = newTab;
    }
    csHidePlaceholder();
    sbDragId = null;
    await csPersistKanbanLayout(newTab);
};

/* Reihenfolge + column_idx je Karte aus DOM lesen und persistieren */
async function csPersistKanbanLayout(tabKey) {
    // Karten aus cs-col UND cs-hero einsammeln (Hero = column_idx 0)
    const newOrder = [];
    document.querySelectorAll('.cs-kanban .cs-col .sb-card[data-card-id], .cs-kanban .cs-hero .sb-card[data-card-id]').forEach(el => {
        const id = parseInt(el.dataset.cardId, 10);
        const card = sbCards.find(c => c.id == id);
        if (!card) return;
        const parent = el.parentElement;
        const parentCol = parent?.dataset.col;
        const colIdx = parentCol === 'hero' ? 0 : (parseInt(parentCol || el.dataset.columnIdx || '2', 10) || 2);
        const tab = parent?.dataset.tab || parent?.closest('.cs-kanban')?.id?.replace('cs-tab-','') || 'inhalte';
        card.column_idx = colIdx;
        card.target_tab = tab;
        el.dataset.columnIdx = String(colIdx);
        newOrder.push(card);
    });
    sbCards.forEach(c => { if (!newOrder.includes(c)) newOrder.push(c); });
    sbCards = newOrder;
    try {
        await App.post('/admin/customers/' + customerId + '/cards/kanban', {
            cards: newOrder.map(c => ({ id: c.id, column_idx: (c.column_idx ?? 2), target_tab: c.target_tab || 'inhalte' }))
        });
    } catch (err) { App.showNotification(err.message || 'Speichern fehlgeschlagen', 'error'); }
}

window.csPanelDragOver = function(e) {
    if (!sbDragId && !csShellDragActive) return;
    if (e.target.closest('.sb-card, .cs-card-shell')) return;
    e.preventDefault();
    // Wenn der Placeholder bereits im Panel ist, lassen wir ihn an seiner aktuellen
    // Position — sonst flackert er bei jedem Cursor-Pixel, weil der Placeholder
    // selbst pointer-events:none hat und das Event aufs Panel durchschlaegt.
    if (csDragPlaceholder && csDragPlaceholder.parentElement === e.currentTarget) return;
    // Sonst (erster Drag-Eintritt in dieses Panel): ans Ende.
    csShowPlaceholderAtEnd(e.currentTarget);
};
window.csPanelDrop = async function(e, panelName) {
    if (e.target.closest('.sb-card, .cs-card-shell')) return;
    e.preventDefault();
    const panel = e.currentTarget;
    if (csDragPlaceholder && csDragPlaceholder.parentElement === panel) {
        if (csShellDragActive) {
            const shell = document.getElementById('cs-uebersicht-plan');
            if (shell) {
                panel.insertBefore(shell, csDragPlaceholder);
                csShellPersistFromDom();
            }
        } else if (sbDragId) {
            const card = document.querySelector(`.sb-card[data-card-id="${sbDragId}"]`);
            if (card) {
                panel.insertBefore(card, csDragPlaceholder);
                await csPersistCardOrderFromDom();
            }
        }
        csHidePlaceholder();
    }
    csShellDragActive = false;
    sbDragId = null;
};

/* Drag-and-Drop fuer den Plan-Widget-Shell. Nur im Layout-Edit-Modus.
   Auf Drop auf einer Card landet der Shell VOR dieser Card. Drop in den
   leeren Panel-Bereich schiebt ihn ans Ende. Position in localStorage. */
let csShellDragActive = false;
window.csShellDragStart = function(e) {
    if (!document.body.classList.contains('cs-layout-edit')) { e.preventDefault(); return; }
    if (e.target.closest('a, button, input, textarea, [contenteditable], .sb-card-action-wrap')) {
        e.preventDefault(); return;
    }
    csShellDragActive = true;
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', 'shell'); } catch (_) {}
    // dragging-Class ERST im naechsten Tick, damit der Browser das Drag-Image
    // noch vom sichtbaren Element holt, bevor CSS auf display:none umschaltet.
    const el = e.currentTarget;
    setTimeout(() => { el.classList.add('dragging'); }, 0);
};
window.csShellDragEnd = function(e) {
    e.currentTarget.classList.remove('dragging');
    document.querySelectorAll('.drop-target').forEach(el => el.classList.remove('drop-target'));
    csHidePlaceholder();
    csShellDragActive = false;
};
window.csShellDragOver = function(e) {
    if (!sbDragId && !csShellDragActive) return;
    e.preventDefault();
    csUpdatePlaceholderForTarget(e.currentTarget, e);
};
window.csShellDragLeave = function(e) { /* Placeholder bleibt sichtbar */ };
window.csShellDrop = async function(e) {
    e.preventDefault();
    if (csDragPlaceholder && csDragPlaceholder.parentElement) {
        const panel = csDragPlaceholder.parentElement;
        if (sbDragId && !csShellDragActive) {
            const card = document.querySelector(`.sb-card[data-card-id="${sbDragId}"]`);
            if (card) {
                panel.insertBefore(card, csDragPlaceholder);
                await csPersistCardOrderFromDom();
            }
        }
        csHidePlaceholder();
    }
    csShellDragActive = false;
    sbDragId = null;
};

window.csShellSetTargetTab = function(tab) {
    const shell = document.getElementById('cs-uebersicht-plan');
    if (!shell) return;
    const target = document.getElementById('cs-tab-' + tab);
    if (target && shell.parentElement !== target) target.appendChild(shell);
    csShellSaveState({ tab });
    document.querySelectorAll('#cs-shell-move-pop .sb-move-item').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });
    document.getElementById('cs-shell-move-pop')?.classList.remove('open');
    if (typeof csSetTab === 'function') csSetTab(tab);
};

function sbStashSystemCards() {
    const tpl = document.getElementById('sb-system-templates');
    if (!tpl) return;
    document.querySelectorAll('.sb-card[data-system-key]').forEach(el => {
        const key = el.dataset.systemKey;
        if (!key) return;
        const body = el.querySelector('.sb-card-body');
        let slot = tpl.querySelector(`[data-system-key="${key}"]`);
        if (!slot) {
            slot = document.createElement('div');
            slot.dataset.systemKey = key;
            tpl.appendChild(slot);
        }
        if (body) {
            slot.innerHTML = '';
            while (body.firstChild) slot.appendChild(body.firstChild);
        }
    });
}

function sbHydrateSystemCards() {
    const tpl = document.getElementById('sb-system-templates');
    if (!tpl) return;
    const stagingContainer = document.getElementById('sb-cards');
    sbCards.filter(c => c.is_system).forEach(card => {
        // Im Staging-Container suchen, NICHT global. Sonst landen die hydratisierten
        // Inhalte in den alten Karten (in Kanban-Spalten), die csRouteCards
        // gleich danach entfernt — neue Karten blieben dann leer.
        const body = stagingContainer?.querySelector(`.sb-card[data-card-id="${card.id}"] .sb-card-body`);
        const src = tpl.querySelector(`[data-system-key="${card.system_key}"]`);
        if (!body || !src) return;
        body.innerHTML = '';
        while (src.firstChild) body.appendChild(src.firstChild);
    });
}

function sbRenderCard(card) {
    const body = card.body_decoded || {};
    const collapsed = card.is_collapsed ? 'collapsed' : '';
    const iconMap = { links: 'link', richtext: 'edit_note', documents: 'folder_zip', images: 'image', brand: 'palette', contacts: 'groups', kpi: 'monitoring', tracking_status: 'fact_check' };
    const sysIconMap = { profile: 'badge', markenprofil: 'palette', regeln: 'rule', asana: 'task_alt', knowledge: 'library_books', website: 'language', site_monitor: 'monitor_heart' };
    const sysIconColor = { profile: 'var(--thoxan-700)', markenprofil: '#ec4899', regeln: '#8b5cf6', asana: '#f97316', knowledge: '#10b981', website: '#0891b2', site_monitor: '#6366f1' };
    // 12er-Grid: data-w 1..12 = direkte Spaltenzahl. Default 4 (Drittel).
    const rawW = +card.size_w;
    const w = (rawW >= 1 && rawW <= 12) ? rawW : 4;
    const h = Math.max(1, Math.min(3, +card.size_h || 1));
    const isSystem = !!card.is_system;
    const iconSpan = isSystem
        ? `<div class="sb-card-icon type-system" style="background:${sysIconColor[card.system_key] || 'var(--thoxan-700)'};"><span class="material-symbols-rounded">${sysIconMap[card.system_key] || 'dashboard'}</span></div>`
        : `<div class="sb-card-icon type-${card.type}"><span class="material-symbols-rounded">${iconMap[card.type] || 'square'}</span></div>`;
    const sysBadge = '';
    const sysAttr = isSystem ? ` data-system-key="${card.system_key}"` : '';
    const validTabs = ['uebersicht','personen','websites','inhalte','dateien','marke','sonstiges'];
    const targetTab = validTabs.includes(card.target_tab) ? card.target_tab : 'inhalte';
    const columnIdx = Math.max(1, Math.min(3, parseInt(card.column_idx || 2, 10)));
    // Kanban: size_w wird zu data-col-span (1 = Spaltenkarte, 2/3 = Hero)
    const colSpan = Math.max(1, Math.min(3, parseInt(card.size_w || 1, 10)));
    return `
    <div class="sb-card ${collapsed} ${isSystem ? 'sb-card-system' : 'sb-card-user'}" id="card-${card.id}" data-card-id="${card.id}" data-card-type="${card.type}"${sysAttr}
         data-target-tab="${targetTab}"
         data-column-idx="${columnIdx}"
         data-col-span="${colSpan}"
         data-w="${w}" data-h="${h}"
         draggable="true"
         ondragstart="sbDragStart(event, ${card.id})"
         ondragover="sbDragOver(event)"
         ondragleave="sbDragLeave(event)"
         ondrop="sbDrop(event, ${card.id})"
         ondragend="sbDragEnd(event)">
        <div class="sb-card-head">
            ${iconSpan}
            <div class="sb-card-title" contenteditable="plaintext-only"
                 onblur="sbUpdateCard(${card.id}, { title: this.textContent.trim() })"
                 onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}">${esc(card.title || '')}</div>
            ${sysBadge}
            <div class="sb-card-actions">
                <button class="sb-card-action sb-vis-btn ${card.customer_visible == 1 ? 'is-on' : ''}" onclick="sbToggleCustomerVisible(${card.id}, this)" title="${card.customer_visible == 1 ? 'Für Kunden sichtbar — klicken zum Ausblenden' : 'Im Kundenportal anzeigen'}">
                    <span class="material-symbols-rounded">${card.customer_visible == 1 ? 'visibility' : 'visibility_off'}</span>
                </button>
                ${isSystem ? '' : `<div class="sb-card-action-wrap sb-move-btn">
                    <button class="sb-card-action" onclick="sbToggleMovePop(event, ${card.id})" title="In anderen Tab verschieben">
                        <span class="material-symbols-rounded">swap_horiz</span>
                    </button>
                    <div class="sb-move-pop" id="sb-move-pop-${card.id}">
                        <div style="font-size: var(--d-fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Verschieben nach</div>
                        <button class="sb-move-item ${targetTab === 'uebersicht' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'uebersicht')">
                            <span class="material-symbols-rounded">dashboard</span>Übersicht
                        </button>
                        <button class="sb-move-item ${targetTab === 'personen' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'personen')">
                            <span class="material-symbols-rounded">groups</span>Personen
                        </button>
                        <button class="sb-move-item ${targetTab === 'websites' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'websites')">
                            <span class="material-symbols-rounded">language</span>Websites
                        </button>
                        <button class="sb-move-item ${targetTab === 'inhalte' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'inhalte')">
                            <span class="material-symbols-rounded">edit_note</span>Inhalte
                        </button>
                        <button class="sb-move-item ${targetTab === 'dateien' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'dateien')">
                            <span class="material-symbols-rounded">folder_zip</span>Dateien
                        </button>
                        <button class="sb-move-item ${targetTab === 'marke' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'marke')">
                            <span class="material-symbols-rounded">palette</span>Marke
                        </button>
                        <button class="sb-move-item ${targetTab === 'sonstiges' ? 'active' : ''}" onclick="sbSetTargetTab(${card.id}, 'sonstiges')">
                            <span class="material-symbols-rounded">inbox</span>Sonstiges
                        </button>
                    </div>
                </div>`}
                <div class="sb-card-action-wrap sb-resize-btn">
                    <button class="sb-card-action" onclick="sbToggleSizePop(event, ${card.id})" title="Größe anpassen">
                        <span class="material-symbols-rounded">aspect_ratio</span>
                    </button>
                    <div class="sb-size-pop" id="sb-size-pop-${card.id}">
                        <div class="sb-size-grid">
                            ${sbSizeCells(card.id, w, h)}
                        </div>
                        <div style="font-size: var(--d-fs-xs);color:#94a3b8;text-align:center;margin-top:6px;">Größe</div>
                    </div>
                </div>
                ${isSystem ? '' : `<button class="sb-card-action sb-card-suggest-btn" onclick="sbOpenSuggestDrawer(${card.id})" title="KI-Vorschläge aus der Wissensbasis">
                    <span class="material-symbols-rounded">auto_awesome</span>
                </button>`}
                ${isSystem ? '' : `<button class="sb-card-action sb-card-edit-btn" onclick="sbToggleCardEdit(${card.id})" title="Bearbeiten">
                    <span class="material-symbols-rounded">edit</span>
                </button>`}
                <button class="sb-card-action sb-history-btn" onclick="sbOpenHistory(${card.id})" title="Versionshistorie">
                    <span class="material-symbols-rounded">history</span>
                </button>
                <button class="sb-card-action sb-card-link-btn" onclick="sbCopyCardLink(event, ${card.id})" title="Link zur Karte kopieren">
                    <span class="material-symbols-rounded">link</span>
                </button>
                <button class="sb-card-action" onclick="sbMaximizeCard(${card.id})" title="Vollbild-Ansicht">
                    <span class="material-symbols-rounded">open_in_full</span>
                </button>
                <button class="sb-card-action sb-card-toggle" onclick="sbToggleCard(${card.id})" title="Ein-/Ausklappen">
                    <span class="material-symbols-rounded">expand_more</span>
                </button>
                ${isSystem ? '' : `<button class="sb-card-action danger sb-card-delete-btn" onclick="sbDeleteCard(${card.id})" title="Card löschen">
                    <span class="material-symbols-rounded">delete</span>
                </button>`}
            </div>
        </div>
        <div class="sb-card-body">
            ${isSystem ? '' : sbRenderCardBody(card, body)}
        </div>
    </div>`;
}

function sbSizeCells(cardId, curW, curH) {
    // Kanban-Modus: 1 = normale Spaltenkarte, 2 = Hero (2 Spalten), 3 = Hero (volle Breite)
    const labels = [
        { w: 1, label: '1 Spalte', title: 'Normale Spaltenkarte' },
        { w: 2, label: '2 Spalten', title: 'Hero ueber 2 Spalten' },
        { w: 3, label: 'Volle Breite', title: 'Hero ueber alle 3 Spalten' },
    ];
    return labels.map(o => {
        const active = (o.w === curW) ? 'active' : '';
        return `<button class="sb-size-cell ${active}" data-w="${o.w}" onclick="sbSetSize(${cardId}, ${o.w}, ${curH || 1})" title="${o.title}">${o.label}</button>`;
    }).join('');
}

window.sbToggleSizePop = function(e, cardId) {
    e.stopPropagation();
    document.querySelectorAll('.sb-size-pop.open, .sb-move-pop.open').forEach(p => { if (p.id !== 'sb-size-pop-' + cardId) p.classList.remove('open'); });
    document.getElementById('sb-size-pop-' + cardId)?.classList.toggle('open');
};

window.sbToggleMovePop = function(e, cardId) {
    e.stopPropagation();
    document.querySelectorAll('.sb-size-pop.open, .sb-move-pop.open').forEach(p => { if (p.id !== 'sb-move-pop-' + cardId) p.classList.remove('open'); });
    document.getElementById('sb-move-pop-' + cardId)?.classList.toggle('open');
};

window.sbSetTargetTab = function(cardId, tab) {
    if (!['uebersicht','personen','websites','inhalte','dateien','marke','sonstiges'].includes(tab)) return;
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.target_tab = tab;
    const el = document.querySelector(`.sb-card[data-card-id="${cardId}"]`);
    if (el) el.dataset.targetTab = tab;
    document.getElementById('sb-move-pop-' + cardId)?.classList.remove('open');
    // Card in Spalte 1 des Ziel-Tabs einhaengen (Default-Platz nach Verschieben)
    const colIdx = parseInt(el?.dataset.columnIdx || '1', 10);
    const col = document.querySelector(`.cs-kanban#cs-tab-${tab} .cs-col[data-col="${colIdx}"]`)
        || document.querySelector(`.cs-kanban#cs-tab-${tab} .cs-col[data-col="1"]`);
    if (el && col) {
        col.appendChild(el);
        if (typeof csUpdateDedicatedCounts === 'function') csUpdateDedicatedCounts();
        if (typeof csSetTab === 'function') csSetTab(tab);
    }
    App.put('/admin/customers/' + customerId + '/cards/' + cardId, { target_tab: tab }).catch(() => {});
};

window.sbSetSize = function(cardId, w, h) {
    const card = sbCards.find(c => c.id == cardId);
    if (card) { card.size_w = w; card.size_h = h; }
    const el = document.querySelector(`.sb-card[data-card-id="${cardId}"]`);
    if (el) {
        el.dataset.w = w; el.dataset.h = h; el.dataset.colSpan = w;
        // Karte zwischen Hero-Zone und Spalte umhaengen
        const targetTab = el.dataset.targetTab || 'inhalte';
        const colIdx = parseInt(el.dataset.columnIdx || '2', 10);
        let target = null;
        if (w >= 2) {
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-hero`);
        } else {
            target = document.querySelector(`.cs-kanban#cs-tab-${targetTab} .cs-col[data-col="${colIdx}"]`);
        }
        if (target && el.parentElement !== target) target.appendChild(el);
    }
    const pop = document.getElementById('sb-size-pop-' + cardId);
    if (pop) {
        pop.querySelectorAll('.sb-size-cell').forEach(c => c.classList.toggle('active', +c.dataset.w === w));
    }
    App.put('/admin/customers/' + customerId + '/cards/' + cardId, { size_w: w, size_h: h }).catch(() => {});
};

document.addEventListener('click', (e) => {
    if (!e.target.closest('.sb-card-action-wrap')) {
        document.querySelectorAll('.sb-size-pop.open, .sb-move-pop.open').forEach(p => p.classList.remove('open'));
    }
});

function sbRenderCardBody(card, body) {
    switch (card.type) {
        case 'links': return sbRenderLinks(card, body);
        case 'richtext': return sbRenderRichtext(card, body);
        case 'documents': return sbRenderFiles(card, body, 'documents');
        case 'images': return sbRenderFiles(card, body, 'images');
        case 'brand': return sbRenderBrand(card, body);
        case 'contacts': return sbRenderContacts(card, body);
        case 'kpi': return sbRenderKpi(card, body);
        case 'tracking_status': return sbRenderTracking(card, body);
        case 'accounts': return sbRenderAccounts(card, body);
    }
    return '';
}

// ===== Contacts =====
function sbRenderContacts(card, body) {
    const groups = body.groups || [];
    return `
        <div class="sb-contacts" data-card-id="${card.id}">
            ${groups.map((g, gi) => sbContactGroupHtml(card.id, gi, g)).join('')}
        </div>
        <button class="sb-link-add" onclick="sbAddContactGroup(${card.id})">
            <span class="material-symbols-rounded" style="font-size:16px;">create_new_folder</span> Gruppe hinzufügen
        </button>
    `;
}

function sbContactGroupHtml(cardId, gi, g) {
    const people = g.people || [];
    return `
        <div class="sb-contact-group" data-gi="${gi}">
            <div class="sb-contact-group-head">
                <input type="text" class="sb-contact-group-title" placeholder="Gruppenname (z.B. Intern, Kunde)"
                       value="${esc(g.title || '')}" oninput="sbDebouncedContacts(${cardId})">
                <button class="sb-link-remove" onclick="sbRemoveContactGroup(${cardId}, ${gi})" title="Gruppe entfernen">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span>
                </button>
            </div>
            <div class="sb-contact-people"
                 ondragover="sbContactPeopleDragOver(event, ${cardId}, ${gi})"
                 ondrop="sbContactPeopleDrop(event, ${cardId}, ${gi})">
                ${people.map((p, pi) => sbContactPersonHtml(cardId, gi, pi, p)).join('')}
            </div>
            <button class="sb-link-add" onclick="sbAddContactPerson(${cardId}, ${gi})" style="margin-top:4px;">
                <span class="material-symbols-rounded" style="font-size:14px;">person_add</span> Person hinzufügen
            </button>
        </div>
    `;
}

function sbContactPersonHtml(cardId, gi, pi, p) {
    const initials = (p.initials || '').toUpperCase();
    return `
        <div class="sb-contact-person" data-gi="${gi}" data-pi="${pi}"
             draggable="true"
             ondragstart="sbContactPersonDragStart(event, ${cardId}, ${gi}, ${pi})"
             ondragend="sbContactPersonDragEnd(event)">
            <div class="sb-contact-handle" title="Zum Verschieben ziehen"><span class="material-symbols-rounded">drag_indicator</span></div>
            <div class="sb-contact-avatar">${esc(initials || '?')}</div>
            <div class="sb-contact-fields">
                <div class="sb-contact-row">
                    <input type="text" class="sb-contact-role" placeholder="Rolle / Aufgabe" value="${esc(p.role || '')}" oninput="sbDebouncedContacts(${cardId})">
                </div>
                <div class="sb-contact-row sb-contact-row-2col">
                    <input type="text" class="sb-contact-name" placeholder="Name" value="${esc(p.name || '')}"
                           oninput="sbUpdateInitialsLive(this); sbDebouncedContacts(${cardId})">
                    <input type="text" class="sb-contact-initials" placeholder="Kürzel" maxlength="6" value="${esc(p.initials || '')}"
                           oninput="sbDebouncedContacts(${cardId})">
                </div>
                <div class="sb-contact-row sb-contact-row-2col">
                    <input type="email" class="sb-contact-email" placeholder="E-Mail" value="${esc(p.email || '')}" oninput="sbDebouncedContacts(${cardId})">
                    <input type="tel" class="sb-contact-phone" placeholder="Telefon" value="${esc(p.phone || '')}" oninput="sbDebouncedContacts(${cardId})">
                </div>
            </div>
            <button class="sb-link-remove" onclick="sbRemoveContactPerson(${cardId}, ${gi}, ${pi})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size:14px;">close</span>
            </button>
        </div>
    `;
}

// Drag-Drop fuer Personen: innerhalb derselben Gruppe sortieren ODER zwischen Gruppen verschieben
let sbContactDrag = null; // { cardId, fromGi, fromPi }

window.sbContactPersonDragStart = function(e, cardId, gi, pi) {
    sbContactDrag = { cardId, fromGi: gi, fromPi: pi };
    e.stopPropagation();
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', 'contact'); } catch (_) {}
    e.currentTarget.classList.add('sb-contact-dragging');
    // Drag-Bild auf das gesamte Person-Element setzen
    try {
        const rect = e.currentTarget.getBoundingClientRect();
        e.dataTransfer.setDragImage(e.currentTarget, e.clientX - rect.left, e.clientY - rect.top);
    } catch (_) {}
};

window.sbContactPersonDragEnd = function(e) {
    document.querySelectorAll('.sb-contact-dragging').forEach(el => el.classList.remove('sb-contact-dragging'));
    document.querySelectorAll('.sb-contact-drop-line').forEach(el => el.remove());
    sbContactDrag = null;
};

window.sbContactPeopleDragOver = function(e, cardId, gi) {
    if (!sbContactDrag || sbContactDrag.cardId !== cardId) return;
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';
    const list = e.currentTarget;
    // Insertion-Index berechnen
    const persons = Array.from(list.querySelectorAll('.sb-contact-person:not(.sb-contact-dragging)'));
    let insertBefore = null;
    for (const el of persons) {
        const rect = el.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) { insertBefore = el; break; }
    }
    // Drop-Line setzen
    document.querySelectorAll('.sb-contact-drop-line').forEach(el => el.remove());
    const line = document.createElement('div');
    line.className = 'sb-contact-drop-line';
    if (insertBefore) list.insertBefore(line, insertBefore);
    else list.appendChild(line);
    list._sbContactInsertBefore = insertBefore; // merken fuer drop
};

window.sbContactPeopleDrop = function(e, cardId, toGi) {
    if (!sbContactDrag || sbContactDrag.cardId !== cardId) return;
    e.preventDefault();
    e.stopPropagation();
    const list = e.currentTarget;
    const insertBefore = list._sbContactInsertBefore;
    const card = sbCards.find(c => c.id == cardId);
    if (!card || !card.body_decoded?.groups) { sbContactPersonDragEnd(e); return; }

    const { fromGi, fromPi } = sbContactDrag;
    const groups = card.body_decoded.groups;
    if (!groups[fromGi] || !groups[fromGi].people?.[fromPi]) { sbContactPersonDragEnd(e); return; }

    const moving = groups[fromGi].people.splice(fromPi, 1)[0];
    groups[toGi] = groups[toGi] || { title: '', people: [] };
    groups[toGi].people = groups[toGi].people || [];

    // Ziel-Position berechnen
    let toPi = groups[toGi].people.length;
    if (insertBefore) {
        const refPi = parseInt(insertBefore.dataset.pi, 10);
        // Wenn Verschieben innerhalb der Gruppe und Ref nach Quell-Index war, korrigieren
        if (fromGi === toGi && refPi > fromPi) toPi = refPi - 1;
        else toPi = refPi;
    }
    groups[toGi].people.splice(toPi, 0, moving);

    // Leere Gruppen behalten (User koennte sie wieder befuellen wollen)
    sbContactPersonDragEnd(e);
    sbRerenderCardBody(cardId);
    sbCommitContacts(cardId);
};

window.sbAddContactGroup = function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { groups: [] };
    card.body_decoded.groups = card.body_decoded.groups || [];
    card.body_decoded.groups.push({ title: '', people: [{ role: '', name: '', initials: '', email: '', phone: '' }] });
    sbRerenderCardBody(cardId);
    sbCommitContacts(cardId);
};

window.sbAddContactPerson = function(cardId, gi) {
    const card = sbCards.find(c => c.id == cardId);
    const grp = card?.body_decoded?.groups?.[gi];
    if (!grp) return;
    grp.people = grp.people || [];
    grp.people.push({ role: '', name: '', initials: '', email: '', phone: '' });
    sbRerenderCardBody(cardId);
    sbCommitContacts(cardId);
};

window.sbRemoveContactGroup = function(cardId, gi) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card?.body_decoded?.groups) return;
    card.body_decoded.groups.splice(gi, 1);
    sbRerenderCardBody(cardId);
    sbCommitContacts(cardId);
};

window.sbRemoveContactPerson = function(cardId, gi, pi) {
    const card = sbCards.find(c => c.id == cardId);
    const grp = card?.body_decoded?.groups?.[gi];
    if (!grp?.people) return;
    grp.people.splice(pi, 1);
    sbRerenderCardBody(cardId);
    sbCommitContacts(cardId);
};

window.sbDebouncedContacts = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitContacts(cardId), 800);
};

// Thoxan-Standard: 3 Zeichen = 1 Buchstabe Vorname + 2 Buchstaben Nachname.
// Umlaute werden transliteriert (Ä → A, Ö → O, Ü → U, ß → SS).
function sbMakeInitials(name) {
    const trans = (s) => (s || '').toUpperCase()
        .replace(/Ä/g, 'A').replace(/Ö/g, 'O').replace(/Ü/g, 'U')
        .replace(/ß/g, 'SS').replace(/ẞ/g, 'SS')
        .replace(/[ÉÈÊ]/g, 'E').replace(/[ÁÀÂ]/g, 'A')
        .replace(/[ÓÒÔ]/g, 'O').replace(/[ÚÙÛ]/g, 'U');
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return trans(parts[0].charAt(0)) + trans(parts[parts.length - 1].substring(0, 2));
    return trans((parts[0] || '').substring(0, 3));
}

window.sbUpdateInitialsLive = function(nameInput) {
    const row = nameInput.closest('.sb-contact-person');
    const initialsInput = row?.querySelector('.sb-contact-initials');
    const avatar = row?.querySelector('.sb-contact-avatar');
    if (initialsInput && !initialsInput.value) {
        const ini = sbMakeInitials(nameInput.value);
        if (avatar) avatar.textContent = ini || '?';
    }
};

function sbCommitContacts(cardId) {
    const root = document.querySelector(`.sb-contacts[data-card-id="${cardId}"]`);
    if (!root) return;
    const groups = Array.from(root.querySelectorAll('.sb-contact-group')).map(g => ({
        title: g.querySelector('.sb-contact-group-title')?.value || '',
        people: Array.from(g.querySelectorAll('.sb-contact-person')).map(p => ({
            role: p.querySelector('.sb-contact-role')?.value || '',
            name: p.querySelector('.sb-contact-name')?.value || '',
            initials: p.querySelector('.sb-contact-initials')?.value || '',
            email: p.querySelector('.sb-contact-email')?.value || '',
            phone: p.querySelector('.sb-contact-phone')?.value || '',
        })),
    }));
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded = { groups };
    sbUpdateCard(cardId, { body: { groups } });
}

function sbRenderLinks(card, body) {
    const items = body.items || [];
    return `
        <div class="sb-links-list" data-card-id="${card.id}">
            ${items.map((it, i) => sbLinkRowHtml(card.id, i, it)).join('')}
        </div>
        ${items.length === 0 ? `<div class="sb-card-empty-hint">Noch keine Links — auf den Stift klicken und „Link hinzufügen".</div>` : ''}
        <button class="sb-link-add" onclick="sbAddLinkRow(${card.id})">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span> Link hinzufügen
        </button>
    `;
}

function sbLinkRowHtml(cardId, idx, it) {
    const url = (it.url || '').trim();
    const hasUrl = !!url;
    const title = it.title || '';
    const linkText = hasUrl ? url.replace(/^https?:\/\//, '') : '';
    return `
        <div class="sb-link-row" data-idx="${idx}">
            <div class="sb-link-view">
                ${title ? `<div class="sb-link-v-title">${esc(title)}</div>` : ''}
                ${hasUrl ? `<a class="sb-link-v-link" href="${esc(url)}" target="_blank" rel="noopener" title="${esc(url)}">${esc(linkText)}</a>` : ''}
            </div>
            <div class="sb-link-edit">
                <div class="sb-link-inputs">
                    <input type="text" class="sb-link-title" placeholder="Titel" value="${esc(title)}"
                           oninput="sbDebouncedLinks(${cardId})">
                    <input type="url" class="sb-link-url" placeholder="https://..." value="${esc(url)}"
                           oninput="sbDebouncedLinks(${cardId})">
                </div>
                <button class="sb-link-remove" onclick="sbRemoveLinkRow(${cardId}, ${idx})" title="Entfernen">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span>
                </button>
            </div>
        </div>`;
}

window.sbAddLinkRow = function(cardId) {
    sbToggleCardEdit(cardId, true); // in den Bearbeiten-Modus wechseln
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { items: [] };
    card.body_decoded.items = card.body_decoded.items || [];
    card.body_decoded.items.push({ title: '', url: '', note: '' });
    const list = document.querySelector(`.sb-links-list[data-card-id="${cardId}"]`);
    if (list) list.insertAdjacentHTML('beforeend', sbLinkRowHtml(cardId, card.body_decoded.items.length - 1, { title:'', url:'', note:'' }));
    setTimeout(() => list?.lastElementChild?.querySelector('.sb-link-title')?.focus(), 60);
};

window.sbRemoveLinkRow = function(cardId, idx) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded.items.splice(idx, 1);
    // Erst neu rendern (DOM aktualisieren), DANN speichern — sbCommitLinks liest aus dem DOM
    sbRerenderCardBody(cardId);
    sbUpdateCard(cardId, { body: { items: card.body_decoded.items } });
};

const sbDebouncedSavers = {};
window.sbDebouncedLinks = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitLinks(cardId), 700);
};

function sbCommitLinks(cardId) {
    const list = document.querySelector(`.sb-links-list[data-card-id="${cardId}"]`);
    if (!list) return;
    const items = Array.from(list.querySelectorAll('.sb-link-row')).map(row => ({
        title: row.querySelector('.sb-link-title')?.value || '',
        url:   row.querySelector('.sb-link-url')?.value || '',
        note:  '',
    }));
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded.items = items;
    sbUpdateCard(cardId, { body: { items } });
}

// ===== Konten & IDs (accounts) =====
// Zwei Ebenen pro Zeile: Ansicht (klickbarer Link, Kopier-Knopf) + Bearbeiten (Felder).
// Stift-Button der Card schaltet via .editing-Klasse um.
function sbRenderAccounts(card, body) {
    const items = body.items || [];
    return `
        <div class="sb-accounts-list" data-card-id="${card.id}">
            ${items.map((it, i) => sbAccountRowHtml(card.id, i, it)).join('')}
        </div>
        ${items.length === 0 ? `<div class="sb-card-empty-hint">Noch keine Konten — auf den Stift klicken und „Konto hinzufügen".</div>` : ''}
        <button class="sb-link-add sb-account-add" onclick="sbAddAccountRow(${card.id})">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span> Konto hinzufügen
        </button>
    `;
}

function sbAccountRowHtml(cardId, idx, it) {
    const url = (it.url || '').trim();
    const hasUrl = !!url;
    const accId = (it.account_id || '').trim();
    const label = it.label || '';
    const linkText = hasUrl ? url.replace(/^https?:\/\//, '') : '';
    return `
        <div class="sb-account-row" data-idx="${idx}">
            <div class="sb-account-view">
                ${label ? `<div class="sb-account-v-label">${esc(label)}</div>` : ''}
                ${accId ? `<div class="sb-account-v-id">
                    <span class="sb-account-v-idval">${esc(accId)}</span>
                    <button type="button" class="sb-account-copy" onclick="sbCopyAccountView(this)" title="ID kopieren">
                        <span class="material-symbols-rounded" style="font-size:15px;">content_copy</span>
                    </button>
                </div>` : ''}
                ${hasUrl ? `<a class="sb-account-v-link" href="${esc(url)}" target="_blank" rel="noopener" title="${esc(url)}">${esc(linkText)}</a>` : ''}
            </div>
            <div class="sb-account-edit">
                <div class="sb-account-inputs">
                    <input type="text" class="sb-account-label" placeholder="Konto / Account (z.B. Google Ads)" value="${esc(label)}"
                           oninput="sbDebouncedAccounts(${cardId})">
                    <input type="text" class="sb-account-id" placeholder="ID / Kennung (optional)" value="${esc(accId)}"
                           oninput="sbDebouncedAccounts(${cardId})">
                    <input type="url" class="sb-account-url" placeholder="Link (optional)" value="${esc(url)}"
                           oninput="sbDebouncedAccounts(${cardId})">
                </div>
                <button class="sb-link-remove" onclick="sbRemoveAccountRow(${cardId}, ${idx})" title="Entfernen">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span>
                </button>
            </div>
        </div>`;
}

window.sbCopyAccountView = function(btn) {
    const val = (btn.closest('.sb-account-v-id')?.querySelector('.sb-account-v-idval')?.textContent || '').trim();
    if (!val) { App.showNotification('Keine ID zum Kopieren', 'error'); return; }
    navigator.clipboard?.writeText(val).then(() => App.showNotification('ID kopiert: ' + val, 'success'));
};

window.sbAddAccountRow = function(cardId) {
    sbToggleCardEdit(cardId, true); // in den Bearbeiten-Modus wechseln
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { items: [] };
    card.body_decoded.items = card.body_decoded.items || [];
    card.body_decoded.items.push({ label:'', account_id:'', url:'', note:'' });
    const list = document.querySelector(`.sb-accounts-list[data-card-id="${cardId}"]`);
    if (list) list.insertAdjacentHTML('beforeend', sbAccountRowHtml(cardId, card.body_decoded.items.length - 1, { label:'', account_id:'', url:'', note:'' }));
    setTimeout(() => list?.lastElementChild?.querySelector('.sb-account-label')?.focus(), 60);
};

window.sbRemoveAccountRow = function(cardId, idx) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded.items.splice(idx, 1);
    sbRerenderCardBody(cardId);
    sbUpdateCard(cardId, { body: { items: card.body_decoded.items } });
};

window.sbDebouncedAccounts = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitAccounts(cardId), 700);
};

function sbCommitAccounts(cardId) {
    const list = document.querySelector(`.sb-accounts-list[data-card-id="${cardId}"]`);
    if (!list) return;
    const items = Array.from(list.querySelectorAll('.sb-account-row')).map(row => ({
        label:      row.querySelector('.sb-account-label')?.value || '',
        account_id: row.querySelector('.sb-account-id')?.value || '',
        url:        row.querySelector('.sb-account-url')?.value || '',
        note:       '',
    }));
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded.items = items;
    sbUpdateCard(cardId, { body: { items } });
}

function sbRenderRichtext(card, body) {
    const html = body.html || '';
    return `
        <div class="sb-richtext-toolbar" onmousedown="event.preventDefault()">
            <button onclick="sbExec(${card.id}, 'bold')" title="Fett"><span class="material-symbols-rounded">format_bold</span></button>
            <button onclick="sbExec(${card.id}, 'italic')" title="Kursiv"><span class="material-symbols-rounded">format_italic</span></button>
            <button onclick="sbExec(${card.id}, 'underline')" title="Unterstrichen"><span class="material-symbols-rounded">format_underlined</span></button>
            <div class="sep"></div>
            <button onclick="sbExec(${card.id}, 'formatBlock', 'h2')" title="Headline">H</button>
            <button onclick="sbExec(${card.id}, 'formatBlock', 'h3')" title="Sub-Headline">h</button>
            <button onclick="sbExec(${card.id}, 'formatBlock', 'p')" title="Absatz">¶</button>
            <div class="sep"></div>
            <button onclick="sbExec(${card.id}, 'insertUnorderedList')" title="Liste"><span class="material-symbols-rounded">format_list_bulleted</span></button>
            <button onclick="sbExec(${card.id}, 'insertOrderedList')" title="Nummerierte Liste"><span class="material-symbols-rounded">format_list_numbered</span></button>
            <div class="sep"></div>
            <button onclick="sbInsertLink(${card.id})" title="Link einfügen"><span class="material-symbols-rounded">link</span></button>
            <button onclick="sbExec(${card.id}, 'unlink')" title="Link entfernen"><span class="material-symbols-rounded">link_off</span></button>
        </div>
        <div class="sb-richtext-editor" contenteditable="true" data-card-id="${card.id}"
             data-placeholder="Schreibe hier… (Headlines, Listen, Links möglich)"
             oninput="sbDebouncedRichtext(${card.id})"
             onblur="sbCommitRichtext(${card.id})">${html}</div>
    `;
}

window.sbExec = function(cardId, cmd, val) {
    const editor = document.querySelector(`.sb-richtext-editor[data-card-id="${cardId}"]`);
    if (!editor) return;
    editor.focus();
    document.execCommand(cmd, false, val);
    sbCommitRichtext(cardId);
};

window.sbInsertLink = function(cardId) {
    const url = prompt('Link-URL:');
    if (!url) return;
    sbExec(cardId, 'createLink', url);
    // target=_blank am neu eingefügten Link setzen
    setTimeout(() => {
        const editor = document.querySelector(`.sb-richtext-editor[data-card-id="${cardId}"]`);
        editor?.querySelectorAll('a').forEach(a => {
            a.target = '_blank';
            a.rel = 'noopener';
        });
    }, 50);
};

// Globale Delegation: Click auf <a> im Notiz-Editor öffnet die URL
document.addEventListener('click', (e) => {
    const a = e.target.closest('.sb-richtext-editor a');
    if (!a) return;
    if (!a.href) return;
    e.preventDefault();
    window.open(a.href, '_blank', 'noopener');
});

// Read-only URL-Inputs: Klick öffnet die URL in neuem Tab
document.addEventListener('click', (e) => {
    const input = e.target.closest('input[type="url"][readonly]');
    if (!input) return;
    const url = (input.value || '').trim();
    if (!url) return;
    e.preventDefault();
    const fullUrl = /^https?:\/\//i.test(url) ? url : 'https://' + url;
    window.open(fullUrl, '_blank', 'noopener');
});

// Read-only E-Mail-Inputs: Klick → mailto:
document.addEventListener('click', (e) => {
    const input = e.target.closest('input[type="email"][readonly]');
    if (!input) return;
    const val = (input.value || '').trim();
    if (!val || !val.includes('@')) return;
    e.preventDefault();
    window.location.href = 'mailto:' + val;
});

// Cursor + Hover für klickbare readonly-Links/Mails
const sbViewCursorStyle = document.createElement('style');
sbViewCursorStyle.textContent = `
    input[type="url"][readonly]:not([value=""]) { cursor: pointer; color: var(--thoxan-700); }
    input[type="email"][readonly]:not([value=""]) { cursor: pointer; color: var(--thoxan-700); }
    input[type="url"][readonly]:not([value=""]):hover,
    input[type="email"][readonly]:not([value=""]):hover { text-decoration: underline; }
`;
document.head.appendChild(sbViewCursorStyle);

window.sbDebouncedRichtext = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitRichtext(cardId), 800);
};

function sbCommitRichtext(cardId) {
    const editor = document.querySelector(`.sb-richtext-editor[data-card-id="${cardId}"]`);
    if (!editor) return;
    const html = editor.innerHTML;
    const card = sbCards.find(c => c.id == cardId);
    if (card) { card.body_decoded = card.body_decoded || {}; card.body_decoded.html = html; }
    sbUpdateCard(cardId, { body: { html } });
}

function sbRenderFiles(card, body, kind) {
    const files = card.files || [];
    const isImg = kind === 'images';
    const dropHint = isImg
        ? 'Bilder hierhin ziehen oder klicken (jpg, png, webp, gif, svg)'
        : 'Dateien hierhin ziehen oder klicken — landen automatisch in der Wissensbasis';
    return `
        <div class="sb-drop-zone"
             ondragover="sbDropZoneOver(event)" ondragleave="sbDropZoneLeave(event)"
             ondrop="sbDropZoneFiles(event, ${card.id})"
             onclick="sbPickFiles(${card.id})">
            <span class="material-symbols-rounded">${isImg ? 'add_photo_alternate' : 'upload_file'}</span>
            <strong>Hochladen</strong>
            <small>${dropHint} · max 50 MB</small>
        </div>
        <input type="file" id="sb-file-input-${card.id}" multiple style="display:none;"
               ${isImg ? 'accept="image/*"' : ''}
               onchange="sbUploadFiles(${card.id}, this.files); this.value='';">
        ${isImg ? sbImagesGrid(card, files) : sbDocumentsList(card, files)}
    `;
}

function sbDocumentsList(card, files) {
    if (!files.length) return '';
    return '<div class="sb-file-list">' + files.map(f => `
        <div class="sb-file-item">
            <span class="material-symbols-rounded">description</span>
            <div class="sb-file-name">
                <a href="${esc(f.public_url || '#')}" target="_blank" title="${esc(f.file_name)}">${esc(f.file_name)}</a>
            </div>
            <span class="sb-file-size">${sbFmtSize(f.file_size)}</span>
            <button class="sb-file-remove" onclick="sbDeleteFile(${card.id}, ${f.id})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size:14px;">close</span>
            </button>
        </div>
    `).join('') + '</div>';
}

function sbImagesGrid(card, files) {
    if (!files.length) return '';
    return '<div class="sb-image-grid">' + files.map(f => `
        <div class="sb-image-tile">
            <a href="${esc(f.public_url || '#')}" target="_blank">
                <img src="${esc(f.public_url || '')}" alt="${esc(f.file_name)}" loading="lazy">
            </a>
            <button class="sb-image-remove" onclick="sbDeleteFile(${card.id}, ${f.id})" title="Entfernen">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
    `).join('') + '</div>';
}

window.sbPickFiles = function(cardId) {
    document.getElementById('sb-file-input-' + cardId)?.click();
};

window.sbDropZoneOver = function(e) { e.preventDefault(); e.currentTarget.classList.add('dragover'); };
window.sbDropZoneLeave = function(e) { e.currentTarget.classList.remove('dragover'); };
window.sbDropZoneFiles = function(e, cardId) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
    sbUploadFiles(cardId, e.dataTransfer.files);
};

window.sbUploadFiles = async function(cardId, fileList) {
    if (!fileList || !fileList.length) return;
    const list = Array.from(fileList);
    for (const file of list) {
        if (file.size > 50 * 1024 * 1024) {
            App.showNotification(file.name + ': zu groß (max 50 MB)', 'error');
            continue;
        }
        try {
            const fd = new FormData();
            fd.append('file', file);
            const r = await fetch('/api/v1/admin/customers/' + customerId + '/cards/' + cardId + '/files', {
                method: 'POST', body: fd,
                headers: { 'X-CSRF-Token': App.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await r.json();
            if (!json.success) throw new Error(json.message || 'Fehler');
            // Lokal an Card hängen
            const card = sbCards.find(c => c.id == cardId);
            if (card) {
                card.files = card.files || [];
                card.files.push(json.data);
            }
            App.showNotification(file.name + ' hochgeladen', 'success');
        } catch (e) {
            App.showNotification(file.name + ': ' + (e.message || 'Fehler'), 'error');
        }
    }
    sbRerenderCardBody(cardId);
    if (sbCards.find(c => c.id == cardId)?.type === 'documents') kbLoad();
};

window.sbDeleteFile = async function(cardId, fileId) {
    if (!confirm('Datei wirklich entfernen?')) return;
    try {
        await App.delete('/admin/customers/' + customerId + '/cards/' + cardId + '/files/' + fileId);
        const card = sbCards.find(c => c.id == cardId);
        if (card) card.files = (card.files || []).filter(f => f.id !== fileId);
        sbRerenderCardBody(cardId);
        kbLoad();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

function sbRenderBrand(card, body) {
    const colors = body.colors || [];
    const fonts = body.fonts || [];
    return `
        <div class="sb-brand-section">
            <h4>Farben</h4>
            <div class="sb-brand-colors" data-card-id="${card.id}">
                ${colors.map((c, i) => sbBrandColorRow(card.id, i, c)).join('')}
            </div>
            <button class="sb-link-add" onclick="sbAddBrandColor(${card.id})">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span> Farbe hinzufügen
            </button>
        </div>
        <div class="sb-brand-section">
            <h4>Schriftarten</h4>
            <div class="sb-brand-fonts" data-card-id="${card.id}">
                ${fonts.map((f, i) => sbBrandFontRow(card.id, i, f)).join('')}
            </div>
            <button class="sb-link-add" onclick="sbAddBrandFont(${card.id})">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span> Schrift hinzufügen
            </button>
        </div>
    `;
}

function sbBrandColorRow(cardId, idx, c) {
    const val = (c.value || 'var(--thoxan-700)').match(/^#[0-9a-fA-F]{6}$/) ? c.value : 'var(--thoxan-700)';
    return `
        <div class="sb-brand-row" data-idx="${idx}">
            <input type="color" value="${val}" oninput="sbDebouncedBrand(${cardId})">
            <input type="text" placeholder="Hex / RGB" value="${esc(c.value || '')}" oninput="sbDebouncedBrand(${cardId})">
            <input type="text" placeholder="Bezeichnung (z.B. Primär)" value="${esc(c.name || '')}" oninput="sbDebouncedBrand(${cardId})">
            <button class="sb-link-remove" onclick="sbRemoveBrandRow(${cardId}, 'colors', ${idx})">
                <span class="material-symbols-rounded" style="font-size:16px;">close</span>
            </button>
        </div>`;
}

function sbBrandFontRow(cardId, idx, f) {
    return `
        <div class="sb-brand-row" data-idx="${idx}" style="grid-template-columns: 1fr 1fr auto;">
            <input type="text" placeholder="Schriftname (z.B. Inter)" value="${esc(f.name || '')}"
                   oninput="sbUpdateFontPreview(this); sbDebouncedBrand(${cardId})">
            <input type="text" placeholder="Notiz (z.B. Headlines)" value="${esc(f.note || '')}" oninput="sbDebouncedBrand(${cardId})">
            <button class="sb-link-remove" onclick="sbRemoveBrandRow(${cardId}, 'fonts', ${idx})">
                <span class="material-symbols-rounded" style="font-size:16px;">close</span>
            </button>
            <div class="sb-font-preview" style="grid-column: 1 / -1; font-family: ${esc(f.name || 'inherit')};">
                The quick brown fox · Geschmeidiges Beispieltext-ÄÖÜß
            </div>
        </div>`;
}

window.sbUpdateFontPreview = function(input) {
    const row = input.closest('.sb-brand-row');
    const preview = row?.querySelector('.sb-font-preview');
    if (preview) preview.style.fontFamily = input.value || 'inherit';
};

window.sbAddBrandColor = function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { colors: [], fonts: [] };
    card.body_decoded.colors = card.body_decoded.colors || [];
    card.body_decoded.colors.push({ name: '', value: 'var(--thoxan-700)' });
    sbRerenderCardBody(cardId);
    sbCommitBrand(cardId);
};

window.sbAddBrandFont = function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { colors: [], fonts: [] };
    card.body_decoded.fonts = card.body_decoded.fonts || [];
    card.body_decoded.fonts.push({ name: '', note: '' });
    sbRerenderCardBody(cardId);
    sbCommitBrand(cardId);
};

window.sbRemoveBrandRow = function(cardId, kind, idx) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card || !card.body_decoded?.[kind]) return;
    card.body_decoded[kind].splice(idx, 1);
    sbRerenderCardBody(cardId);
    sbCommitBrand(cardId);
};

window.sbDebouncedBrand = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitBrand(cardId), 700);
};

function sbCommitBrand(cardId) {
    const colorRows = document.querySelectorAll(`.sb-brand-colors[data-card-id="${cardId}"] .sb-brand-row`);
    const fontRows = document.querySelectorAll(`.sb-brand-fonts[data-card-id="${cardId}"] .sb-brand-row`);
    const colors = Array.from(colorRows).map(r => {
        const inputs = r.querySelectorAll('input');
        return { value: inputs[1]?.value || inputs[0]?.value || '', name: inputs[2]?.value || '' };
    });
    const fonts = Array.from(fontRows).map(r => {
        const inputs = r.querySelectorAll('input');
        return { name: inputs[0]?.value || '', note: inputs[1]?.value || '' };
    });
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded = { colors, fonts };
    sbUpdateCard(cardId, { body: { colors, fonts } });
}

// ===== KPI =====
function sbRenderKpi(card, body) {
    const items = body.items || [];
    return `
        <div class="sb-kpi-list" data-card-id="${card.id}">
            ${items.map((it, i) => sbKpiRowHtml(card.id, i, it)).join('')}
        </div>
        ${items.length === 0 ? `<div class="sb-card-empty-hint">Noch keine Kennzahlen — klick „Kennzahl hinzufügen" zum Starten.</div>` : ''}
        <button class="sb-link-add" onclick="sbAddKpiRow(${card.id})">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span> Kennzahl hinzufügen
        </button>
    `;
}

function sbKpiRowHtml(cardId, idx, it) {
    return `
        <div class="sb-kpi-row" data-idx="${idx}">
            <div class="sb-kpi-cell sb-kpi-cell-value">
                <input type="text" class="sb-kpi-value" placeholder="z.B. 3.000 EUR"
                       value="${esc(it.value || '')}" oninput="sbDebouncedKpi(${cardId})">
                <input type="text" class="sb-kpi-label" placeholder="Bezeichnung (z.B. Meta-Ads Budget / Monat)"
                       value="${esc(it.label || '')}" oninput="sbDebouncedKpi(${cardId})">
            </div>
            <div class="sb-kpi-cell sb-kpi-cell-meta">
                <input type="text" class="sb-kpi-target" placeholder="Ziel / Vorgabe (z.B. CPL ≤ 10 EUR)"
                       value="${esc(it.target || '')}" oninput="sbDebouncedKpi(${cardId})">
                <input type="text" class="sb-kpi-period" placeholder="Zeitraum (z.B. Monat, Q1, jährlich)"
                       value="${esc(it.period || '')}" oninput="sbDebouncedKpi(${cardId})">
            </div>
            <button class="sb-link-remove" onclick="sbRemoveKpiRow(${cardId}, ${idx})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size:16px;">close</span>
            </button>
        </div>`;
}

window.sbAddKpiRow = function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { items: [] };
    card.body_decoded.items = card.body_decoded.items || [];
    card.body_decoded.items.push({ label: '', value: '', target: '', period: '' });
    sbRerenderCardBody(cardId);
    setTimeout(() => {
        const list = document.querySelector(`.sb-kpi-list[data-card-id="${cardId}"]`);
        list?.lastElementChild?.querySelector('input')?.focus();
    }, 30);
};

window.sbRemoveKpiRow = function(cardId, idx) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded.items.splice(idx, 1);
    sbRerenderCardBody(cardId);
    sbUpdateCard(cardId, { body: { items: card.body_decoded.items } });
};

window.sbDebouncedKpi = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitKpi(cardId), 700);
};

function sbCommitKpi(cardId) {
    const list = document.querySelector(`.sb-kpi-list[data-card-id="${cardId}"]`);
    if (!list) return;
    const items = Array.from(list.querySelectorAll('.sb-kpi-row')).map(row => ({
        label:  row.querySelector('.sb-kpi-label')?.value || '',
        value:  row.querySelector('.sb-kpi-value')?.value || '',
        target: row.querySelector('.sb-kpi-target')?.value || '',
        period: row.querySelector('.sb-kpi-period')?.value || '',
    }));
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded.items = items;
    sbUpdateCard(cardId, { body: { items } });
}

// ===== Tracking-Status =====
function sbRenderTracking(card, body) {
    const items = body.items || [];
    return `
        <div class="sb-track-list" data-card-id="${card.id}">
            ${items.map((it, i) => sbTrackRowHtml(card.id, i, it)).join('')}
        </div>
        ${items.length === 0 ? `<div class="sb-card-empty-hint">Noch keine Tracking-Punkte — klick „Punkt hinzufügen" zum Starten.</div>` : ''}
        <button class="sb-link-add" onclick="sbAddTrackRow(${card.id})">
            <span class="material-symbols-rounded" style="font-size:16px;">add</span> Punkt hinzufügen
        </button>
    `;
}

function sbTrackRowHtml(cardId, idx, it) {
    const status = it.status || 'tbd';
    return `
        <div class="sb-track-row sb-track-${esc(status)}" data-idx="${idx}">
            <select class="sb-track-status" onchange="sbDebouncedTrack(${cardId}); this.parentElement.className = 'sb-track-row sb-track-' + this.value;" title="Status">
                <option value="ok"   ${status==='ok'   ? 'selected':''}>✓ aktiv</option>
                <option value="fehlt" ${status==='fehlt' ? 'selected':''}>✗ fehlt</option>
                <option value="tbd"  ${status==='tbd'  ? 'selected':''}>? offen</option>
                <option value="na"   ${status==='na'   ? 'selected':''}>– n/a</option>
            </select>
            <input type="text" class="sb-track-label" placeholder="Komponente (z.B. GA4 Property)"
                   value="${esc(it.label || '')}" oninput="sbDebouncedTrack(${cardId})">
            <input type="text" class="sb-track-note" placeholder="Notiz / ID (optional)"
                   value="${esc(it.note || '')}" oninput="sbDebouncedTrack(${cardId})">
            <button class="sb-link-remove" onclick="sbRemoveTrackRow(${cardId}, ${idx})" title="Entfernen">
                <span class="material-symbols-rounded" style="font-size:16px;">close</span>
            </button>
        </div>`;
}

window.sbAddTrackRow = function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded = card.body_decoded || { items: [] };
    card.body_decoded.items = card.body_decoded.items || [];
    card.body_decoded.items.push({ label: '', status: 'tbd', note: '' });
    sbRerenderCardBody(cardId);
    setTimeout(() => {
        const list = document.querySelector(`.sb-track-list[data-card-id="${cardId}"]`);
        list?.lastElementChild?.querySelector('.sb-track-label')?.focus();
    }, 30);
};

window.sbRemoveTrackRow = function(cardId, idx) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    card.body_decoded.items.splice(idx, 1);
    sbRerenderCardBody(cardId);
    sbUpdateCard(cardId, { body: { items: card.body_decoded.items } });
};

window.sbDebouncedTrack = function(cardId) {
    clearTimeout(sbDebouncedSavers[cardId]);
    sbDebouncedSavers[cardId] = setTimeout(() => sbCommitTrack(cardId), 700);
};

function sbCommitTrack(cardId) {
    const list = document.querySelector(`.sb-track-list[data-card-id="${cardId}"]`);
    if (!list) return;
    const items = Array.from(list.querySelectorAll('.sb-track-row')).map(row => ({
        label:  row.querySelector('.sb-track-label')?.value || '',
        status: row.querySelector('.sb-track-status')?.value || 'tbd',
        note:   row.querySelector('.sb-track-note')?.value || '',
    }));
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.body_decoded.items = items;
    sbUpdateCard(cardId, { body: { items } });
}

// ----- Card-CRUD -----
/* Link zur Karte in Zwischenablage — URL mit #card-<id>-Hash, beim Laden
   wird zum richtigen Tab gewechselt und zur Karte gescrollt. */
window.sbCopyCardLink = async function(e, cardId) {
    e?.stopPropagation?.();
    const base = window.location.href.split('#')[0];
    const url = base + '#card-' + cardId;
    try {
        await navigator.clipboard.writeText(url);
        App.showNotification('Link kopiert', 'success');
    } catch (_) {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); App.showNotification('Link kopiert', 'success'); } catch (_) {}
        ta.remove();
    }
};

/* Beim Laden: Hash auf #card-<id> aufloesen — in den richtigen Tab wechseln,
   zur Karte scrollen und kurz visuell hervorheben. */
function csResolveCardHash() {
    const m = (window.location.hash || '').match(/^#card-(\d+)$/);
    if (!m) return false;
    const cardId = m[1];
    const cardEl = document.getElementById('card-' + cardId);
    if (!cardEl) return false;
    const tab = cardEl.dataset.targetTab || 'inhalte';
    if (typeof csSetTab === 'function') csSetTab(tab);
    setTimeout(() => {
        cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        cardEl.classList.add('sb-card-highlight');
        setTimeout(() => cardEl.classList.remove('sb-card-highlight'), 2000);
    }, 80);
    return true;
}

window.sbCreateCard = async function(type, targetTabOverride) {
    document.getElementById('sb-add-menu')?.classList.remove('open');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/cards', { type });
        if (!r.success) throw new Error(r.message || 'Fehler');
        // Default: aktuell offener Tab. Falls expliziter Override → den nehmen.
        // Sonst Fallback auf 'inhalte', wenn der aktive Tab keiner unserer 5 ist.
        const validTabs = ['uebersicht','personen','websites','inhalte','dateien','marke','sonstiges'];
        const activeTab = document.querySelector('.cs-tab.is-active')?.dataset.tab;
        const tab = targetTabOverride
            || (validTabs.includes(activeTab) ? activeTab : 'inhalte');
        if (tab !== 'inhalte') {
            await App.put('/admin/customers/' + customerId + '/cards/' + r.data.id, { target_tab: tab }).catch(() => {});
            r.data.target_tab = tab;
        }
        sbCards.push(r.data);
        sbRenderCards();
        csSetTab(tab);
        setTimeout(() => {
            const newEl = document.querySelector(`.sb-card[data-card-id="${r.data.id}"]`);
            newEl?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            newEl?.querySelector('.sb-card-title')?.focus();
        }, 80);
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbUpdateCard = async function(cardId, patch) {
    try {
        const r = await App.put('/admin/customers/' + customerId + '/cards/' + cardId, patch);
        if (!r.success) throw new Error(r.message || 'Fehler');
        // Lokal aktualisieren
        const idx = sbCards.findIndex(c => c.id == cardId);
        if (idx >= 0) sbCards[idx] = r.data;
        // Wissensbasis ggf. neu laden, weil Card-Änderung in KB-Sync mündet
        kbLoad();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

window.sbDeleteCard = async function(cardId) {
    if (!confirm('Card und alle ihre Inhalte wirklich löschen? Verknüpftes Wissen wird ebenfalls entfernt.')) return;
    try {
        await App.delete('/admin/customers/' + customerId + '/cards/' + cardId);
        sbCards = sbCards.filter(c => c.id != cardId);
        sbRenderCards();
        kbLoad();
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

// Karte fuer das Kundenportal sichtbar / unsichtbar schalten (jeder Typ)
window.sbToggleCustomerVisible = async function(cardId, btn) {
    const willShow = !btn.classList.contains('is-on');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/portal/card-visible', { card_id: cardId, visible: willShow ? 1 : 0 });
        if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
        btn.classList.toggle('is-on', willShow);
        btn.querySelector('.material-symbols-rounded').textContent = willShow ? 'visibility' : 'visibility_off';
        btn.title = willShow ? 'Für Kunden sichtbar — klicken zum Ausblenden' : 'Im Kundenportal anzeigen';
        const c = sbCards.find(x => x.id == cardId); if (c) c.customer_visible = willShow ? 1 : 0;
        App.showNotification(willShow ? 'Im Kundenportal sichtbar' : 'Aus dem Kundenportal entfernt', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

// Projektplanner-Dashboard (Shell) fuer das Kundenportal sichtbar / unsichtbar
window.csShellToggleVisible = async function(btn) {
    const willShow = !btn.classList.contains('is-on');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/portal/shell-visible', { key: 'projektplanner', visible: willShow ? 1 : 0 });
        if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
        btn.classList.toggle('is-on', willShow);
        btn.querySelector('.material-symbols-rounded').textContent = willShow ? 'visibility' : 'visibility_off';
        btn.title = willShow ? 'Für Kunden sichtbar — klicken zum Ausblenden' : 'Im Kundenportal anzeigen';
        App.showNotification(willShow ? 'Im Kundenportal sichtbar' : 'Aus dem Kundenportal entfernt', 'success');
    } catch (e) { App.showNotification(e.message || 'Fehler', 'error'); }
};

// ── Kunden-Websites (pm_monitors = einzige Quelle) ──────────────────────────
async function csWebLoad() {
    const box = document.getElementById('cs-cwebs-list');
    if (!box) return;
    try {
        const r = await App.get('/admin/customers/' + customerId + '/websites');
        if (!r.success) return;
        if (!r.data.length) { box.innerHTML = '<small style="color:#cbd5e1;">Noch keine Website.</small>'; return; }
        box.innerHTML = r.data.map(w => `
            <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;border:1px solid var(--slate-200);border-radius:8px;">
                <label title="Monitoring an/aus" style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;flex-shrink:0;">
                    <input type="checkbox" ${w.monitoring ? 'checked' : ''} onchange="csWebToggleMon(${w.id}, this.checked)">
                    <span class="material-symbols-rounded" style="font-size:16px;color:${w.monitoring ? 'var(--emerald-600)' : '#cbd5e1'};">monitor_heart</span>
                </label>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:var(--d-fs-xs);color:#64748b;font-weight:600;">${esc(w.label)}${w.is_primary ? ' <span style="color:var(--thoxan-700);">· Hauptseite</span>' : ''}</div>
                    <a href="${esc(w.url)}" target="_blank" rel="noopener" style="color:var(--thoxan-700);font-size:var(--d-fs-sm);text-decoration:none;word-break:break-all;">${esc(w.url)}</a>
                </div>
                ${w.is_primary ? '' : `<button class="sb-card-action" onclick="csWebSetPrimary(${w.id})" title="Als Hauptseite setzen"><span class="material-symbols-rounded" style="font-size:16px;">star</span></button>`}
                <button class="sb-card-action danger" onclick="csWebDelete(${w.id})" title="Website entfernen"><span class="material-symbols-rounded" style="font-size:16px;">close</span></button>
            </div>
        `).join('');
    } catch (e) {}
}
async function csWebAdd() {
    const url = document.getElementById('cs-cweb-url').value.trim();
    const label = document.getElementById('cs-cweb-label').value.trim();
    const monitor = document.getElementById('cs-cweb-mon').checked;
    if (!url) { App.showNotification('URL fehlt', 'error'); return; }
    const r = await App.post('/admin/customers/' + customerId + '/websites', { url, label, monitor });
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    document.getElementById('cs-cweb-url').value = ''; document.getElementById('cs-cweb-label').value = '';
    App.showNotification('Website hinzugefügt', 'success');
    csWebLoad(); if (typeof sbLoadSiteMonitor === 'function') sbLoadSiteMonitor();
}
async function csWebToggleMon(id, on) {
    const r = await App.post('/admin/customers/' + customerId + '/websites/' + id + '/monitoring', { on: on ? 1 : 0 });
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); csWebLoad(); return; }
    App.showNotification(r.message, 'success'); csWebLoad(); if (typeof sbLoadSiteMonitor === 'function') sbLoadSiteMonitor();
}
async function csWebSetPrimary(id) {
    const r = await App.post('/admin/customers/' + customerId + '/websites/' + id + '/primary', {});
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    App.showNotification('Hauptseite gesetzt', 'success'); csWebLoad();
}
async function csWebDelete(id) {
    if (!confirm('Website entfernen? Das entfernt auch das zugehörige Monitoring.')) return;
    const r = await App.post('/admin/customers/' + customerId + '/websites/' + id + '/delete', {});
    if (!r.success) { App.showNotification(r.message || 'Fehler', 'error'); return; }
    App.showNotification('Website entfernt', 'success');
    csWebLoad(); if (typeof sbLoadSiteMonitor === 'function') sbLoadSiteMonitor();
}
document.addEventListener('DOMContentLoaded', csWebLoad);

window.sbToggleCard = function(cardId) {
    const el = document.querySelector(`.sb-card[data-card-id="${cardId}"]`);
    if (!el) return;
    const collapsed = !el.classList.contains('collapsed');
    el.classList.toggle('collapsed', collapsed);
    const card = sbCards.find(c => c.id == cardId);
    if (card) card.is_collapsed = collapsed ? 1 : 0;
    // Persist (silent)
    App.put('/admin/customers/' + customerId + '/cards/' + cardId, { is_collapsed: collapsed }).catch(() => {});
};

window.sbToggleCollapse = function(elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.classList.toggle('collapsed');
};

function sbRerenderCardBody(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const cardEl = document.querySelector(`.sb-card[data-card-id="${cardId}"] .sb-card-body`);
    if (cardEl) cardEl.innerHTML = sbRenderCardBody(card, card.body_decoded || {});
}

// ----- Drag & Drop Reorder -----
let sbDragId = null;

/* === Kanban-Style Drop-Placeholder === */
let csDragPlaceholder = null;
function csGetDraggedEl() {
    if (sbDragId) return document.querySelector(`.sb-card[data-card-id="${sbDragId}"]`);
    if (csShellDragActive) return document.getElementById('cs-uebersicht-plan');
    return null;
}
function csEnsurePlaceholder() {
    if (!csDragPlaceholder) {
        csDragPlaceholder = document.createElement('div');
        csDragPlaceholder.className = 'cs-drop-placeholder';
    }
    const dragged = csGetDraggedEl();
    if (dragged) csDragPlaceholder.dataset.w = dragged.dataset.w || '4';
    return csDragPlaceholder;
}
function csShowPlaceholder(beforeEl) {
    const ph = csEnsurePlaceholder();
    const parent = beforeEl ? beforeEl.parentElement : null;
    if (!parent) return;
    // Nicht neu einfuegen, wenn bereits an der richtigen Stelle
    if (beforeEl === ph || beforeEl.previousElementSibling === ph) return;
    parent.insertBefore(ph, beforeEl);
}
function csShowPlaceholderAtEnd(parent) {
    const ph = csEnsurePlaceholder();
    if (!parent) return;
    if (ph.parentElement === parent && parent.lastElementChild === ph) return;
    parent.appendChild(ph);
}
function csHidePlaceholder() {
    if (csDragPlaceholder && csDragPlaceholder.parentElement) {
        csDragPlaceholder.parentElement.removeChild(csDragPlaceholder);
    }
}
/* DOM-Order in sbCards uebernehmen und ans Backend persistieren */
async function csPersistCardOrderFromDom() {
    // sbCards in der DOM-Reihenfolge ueber ALLE Panels neu sortieren
    const newOrder = [];
    document.querySelectorAll('.cs-tab-panel .sb-card[data-card-id]').forEach(el => {
        const id = parseInt(el.dataset.cardId, 10);
        const card = sbCards.find(c => c.id == id);
        if (card) newOrder.push(card);
    });
    // Cards, die nicht im DOM sind (z.B. raw #sb-cards), hinten dran
    sbCards.forEach(c => { if (!newOrder.includes(c)) newOrder.push(c); });
    sbCards = newOrder;
    try {
        await App.post('/admin/customers/' + customerId + '/cards/reorder', { ids: sbCards.map(c => c.id) });
    } catch (err) { App.showNotification(err.message || 'Reorder fehlgeschlagen', 'error'); }
}

/* Shell-Position aus aktuellem DOM-Stand in localStorage persistieren */
function csShellPersistFromDom() {
    const shell = document.getElementById('cs-uebersicht-plan');
    if (!shell || !shell.parentElement) return;
    const panel = shell.parentElement;
    const next = shell.nextElementSibling;
    let beforeRef = null;
    if (next) {
        if (next.dataset && next.dataset.cardId) beforeRef = 'card:' + next.dataset.cardId;
        else if (next.id)                        beforeRef = 'el:'   + next.id;
    }
    const tab = panel.id.replace('cs-tab-', '');
    csShellSaveState({ beforeRef, tab });
}

/* Position berechnen: Cursor auf Card → vor oder nach der Card? */
function csUpdatePlaceholderForTarget(targetEl, e) {
    const dragged = csGetDraggedEl();
    if (!dragged || targetEl === dragged) { csHidePlaceholder(); return; }
    const rect = targetEl.getBoundingClientRect();
    const midX = rect.left + rect.width / 2;
    const before = e.clientX < midX;
    if (before) csShowPlaceholder(targetEl);
    else {
        const next = targetEl.nextElementSibling;
        if (next) csShowPlaceholder(next);
        else csShowPlaceholderAtEnd(targetEl.parentElement);
    }
}
window.sbDragStart = function(e, id) {
    if (!document.body.classList.contains('cs-layout-edit')) { e.preventDefault(); return; }
    // Im Layout-Modus nur konkrete Eingabe-Elemente vom Drag ausschliessen.
    // Title, Action-Buttons und contenteditable sind als Drag-Quelle erlaubt —
    // sonst gibt's auf System-Cards (Profil) gar keinen klickbaren Drag-Bereich.
    if (e.target.closest('input, textarea, select')) {
        e.preventDefault();
        return;
    }
    sbDragId = id;
    e.dataTransfer.effectAllowed = 'move';
    // dragging-Class ERST im naechsten Tick, damit der Browser das Drag-Image
    // noch vom sichtbaren Element holt, bevor CSS auf display:none umschaltet.
    const el = e.currentTarget;
    setTimeout(() => { el.classList.add('dragging'); }, 0);
};
window.sbDragOver = function(e) {
    if (!sbDragId && !csShellDragActive) return;
    e.preventDefault();
    csUpdatePlaceholderForTarget(e.currentTarget, e);
};
window.sbDragLeave = function(e) { e.currentTarget.classList.remove('drop-target'); };
window.sbDrop = async function(e, targetId) {
    e.preventDefault();
    e.currentTarget.classList.remove('drop-target');
    // Placeholder ist im DOM an der Stelle, wo gedroppt werden soll.
    // Wir bewegen das gezogene Element dort hin und entfernen den Placeholder.
    if (csDragPlaceholder && csDragPlaceholder.parentElement) {
        const panel = csDragPlaceholder.parentElement;
        if (csShellDragActive) {
            const shell = document.getElementById('cs-uebersicht-plan');
            if (shell) {
                panel.insertBefore(shell, csDragPlaceholder);
                csShellPersistFromDom();
            }
        } else if (sbDragId) {
            const card = document.querySelector(`.sb-card[data-card-id="${sbDragId}"]`);
            if (card) {
                panel.insertBefore(card, csDragPlaceholder);
                await csPersistCardOrderFromDom();
            }
        }
        csHidePlaceholder();
    }
    csShellDragActive = false;
    sbDragId = null;
};
window.sbDragEnd = function(e) {
    document.querySelectorAll('.sb-card.drop-target, .sb-card.dragging').forEach(el => {
        el.classList.remove('drop-target', 'dragging');
    });
    csHidePlaceholder();
    sbDragId = null;
};

// ----- KI-Suche (Cards + Profil + Chats) -----
window.sbAskAI = async function(query) {
    const btn = document.getElementById('sb-search-ai');
    if (btn) btn.classList.add('busy');

    let overlay = document.getElementById('sb-ai-result-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-ai-result-overlay';
        overlay.className = 'sb-ai-result-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseAIResult(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-ai-result-modal">
            <div class="sb-ai-result-head">
                <span class="material-symbols-rounded">auto_awesome</span>
                <div style="flex:1;min-width:0;">
                    <h3>KI-Antwort</h3>
                    <small>${esc(query)}</small>
                </div>
                <button class="sb-card-action" onclick="sbCloseAIResult()" title="Schließen"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="sb-ai-result-body">
                <div class="sb-ai-answer loading">
                    <span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(16,185,129,0.3);border-top-color:#10b981;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:8px;"></span>
                    KI sucht in Profil, Cards und Chats…
                </div>
            </div>
        </div>
    `;
    overlay.classList.add('open');

    try {
        const r = await App.post('/admin/customers/' + customerId + '/cards/ai-search', { query });
        if (!r.success) throw new Error(r.message || 'Fehler');
        const d = r.data || {};
        const ans = (d.answer || '').trim();
        const matches = d.matches || [];

        const body = overlay.querySelector('.sb-ai-result-body');
        body.innerHTML = `
            <div class="sb-ai-answer">${esc(ans)}</div>
            ${matches.length ? `
                <div class="sb-ai-matches-head">${matches.length} Treffer in Steckbrief &amp; Chats</div>
                ${matches.map(m => `
                    <a class="sb-ai-match" ${m.type === 'chat' ? `href="/chat?conv=${m.id}"` : `onclick="sbCloseAIResult(); setTimeout(()=>{const el=document.querySelector('.sb-card[data-card-id=&quot;${m.id}&quot;]');if(el){el.scrollIntoView({behavior:'smooth',block:'center'});el.classList.add('search-hit');setTimeout(()=>el.classList.remove('search-hit'),1200);}},150);"`}>
                        <div><span class="sb-ai-match-type ${m.type}">${m.type === 'card' ? 'Card' : 'Chat'}</span><span class="sb-ai-match-title">${esc(m.title || '')}</span></div>
                        <div class="sb-ai-match-snippet">${esc((m.snippet || '').substring(0, 200))}</div>
                    </a>
                `).join('')}
            ` : '<div style="color:#94a3b8;font-size: var(--d-fs-sm);text-align:center;padding:1rem;">Keine direkten Text-Treffer — siehe KI-Antwort oben.</div>'}
        `;
    } catch (e) {
        const body = overlay.querySelector('.sb-ai-result-body');
        if (body) body.innerHTML = '<div class="sb-ai-answer" style="background:var(--rose-50);border-color:var(--rose-200);color:var(--rose-700);">' + esc(e.message || 'Fehler') + '</div>';
    } finally {
        if (btn) btn.classList.remove('busy');
    }
};

window.sbCloseAIResult = function() {
    const overlay = document.getElementById('sb-ai-result-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

// ----- Versionshistorie -----
window.sbOpenHistory = async function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card || card.is_system) {
        App.showNotification('System-Cards haben keine Historie', 'info');
        return;
    }
    let overlay = document.getElementById('sb-history-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-history-overlay';
        overlay.className = 'sb-history-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseHistory(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-history-modal">
            <div class="sb-history-head">
                <span class="material-symbols-rounded" style="color:var(--thoxan-700);">history</span>
                <div style="flex:1;">
                    <h3>Versionshistorie</h3>
                    <small>${esc(card.title || '')}</small>
                </div>
                <button class="sb-card-action" onclick="sbCloseHistory()" title="Schließen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="sb-history-body" id="sb-history-list">
                <div class="cs-empty"><span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(0, 76, 155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:6px;"></span>Lade Versionen…</div>
            </div>
        </div>
    `;
    overlay.classList.add('open');

    try {
        const r = await App.get('/admin/customers/' + customerId + '/cards/' + cardId + '/versions');
        if (!r.success) throw new Error(r.message || 'Fehler');
        const versions = r.data.versions || [];
        sbRenderHistoryList(cardId, versions);
    } catch (e) {
        document.getElementById('sb-history-list').innerHTML = '<div class="cs-empty" style="color:var(--rose-600);">' + esc(e.message || '') + '</div>';
    }
};

function sbRenderHistoryList(cardId, versions) {
    const list = document.getElementById('sb-history-list');
    if (!list) return;
    if (versions.length === 0) {
        list.innerHTML = '<div class="cs-empty">Noch keine älteren Versionen — die erste Änderung legt automatisch einen Snapshot an.</div>';
        return;
    }
    list.innerHTML = versions.map((v, i) => `
        <div class="sb-history-item" data-version-id="${v.id}">
            <div class="sb-history-meta">
                <strong>${i === 0 ? 'Letzte Version' : 'Version vom'}</strong>
                <span>${sbFormatDateTime(v.snapshot_at)}</span>
                ${v.user_name ? `<small>· ${esc(v.user_name)}</small>` : ''}
            </div>
            <div class="sb-history-title-pre">${esc(v.title || '(kein Titel)')}</div>
            <div class="sb-history-actions">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbPreviewVersion(${cardId}, ${v.id})">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">visibility</span>
                    Vorschau
                </button>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbRestoreVersion(${cardId}, ${v.id})">
                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">restore</span>
                    Wiederherstellen
                </button>
            </div>
            <div class="sb-history-preview" id="sb-history-preview-${v.id}" style="display:none;"></div>
        </div>
    `).join('');
}

window.sbPreviewVersion = async function(cardId, versionId) {
    const box = document.getElementById('sb-history-preview-' + versionId);
    if (!box) return;
    if (box.style.display !== 'none') { box.style.display = 'none'; return; }
    box.style.display = 'block';
    box.innerHTML = '<div class="cs-empty"><span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(0,76,155,0.3);border-top-color:var(--thoxan-700);border-radius:50%;animation:spin 1s linear infinite;"></span> Lade…</div>';
    try {
        const r = await App.get('/admin/customers/' + customerId + '/cards/' + cardId + '/versions?version_id=' + versionId);
        if (!r.success) throw new Error(r.message || 'Fehler');
        const v = r.data;
        box.innerHTML = sbHistoryPreviewHtml(v);
    } catch (e) {
        box.innerHTML = '<div style="color:var(--rose-600);font-size: var(--d-fs-sm);">' + esc(e.message || '') + '</div>';
    }
};

function sbHistoryPreviewHtml(v) {
    const body = v.body_decoded || {};
    switch (v.type) {
        case 'richtext':
            return '<div class="sb-history-preview-content">' + (body.html || '<em>leer</em>') + '</div>';
        case 'links':
            return '<ul class="sb-history-preview-content">' + (body.items || []).map(it => `<li><strong>${esc(it.title || '')}</strong> ${it.url ? '<a href="' + esc(it.url) + '" target="_blank">' + esc(it.url) + '</a>' : ''}</li>`).join('') + '</ul>';
        case 'brand':
            const colors = (body.colors || []).map(c => `<span style="display:inline-block;padding:2px 8px;background:${esc(c.value || '#ccc')};color:#fff;border-radius:6px;font-size: var(--d-fs-xs);margin:2px;">${esc(c.name || c.value || '')}</span>`).join('');
            const fonts = (body.fonts || []).map(f => `<span style="display:inline-block;padding:2px 8px;background:#f1f5f9;border-radius:6px;font-family:${esc(f.name || 'inherit')};font-size: var(--d-fs-sm);margin:2px;">${esc(f.name || '')}</span>`).join('');
            return '<div class="sb-history-preview-content"><div>' + colors + '</div><div>' + fonts + '</div>' + (body.note ? '<p>' + esc(body.note) + '</p>' : '') + '</div>';
        case 'contacts':
            return '<div class="sb-history-preview-content">' + (body.groups || []).map(g => `<strong>${esc(g.title || '')}</strong><ul>${(g.people || []).map(p => '<li>' + esc(p.role || '') + ' — ' + esc(p.name || '') + (p.initials ? ' (' + esc(p.initials) + ')' : '') + '</li>').join('')}</ul>`).join('') + '</div>';
        default:
            return '<div class="sb-history-preview-content"><em>Vorschau für diesen Typ nicht verfügbar</em></div>';
    }
}

window.sbRestoreVersion = async function(cardId, versionId) {
    if (!confirm('Diese Version wiederherstellen? Der aktuelle Stand wird automatisch als neue Version gesichert, du kannst also auch wieder zurück.')) return;
    try {
        const r = await App.post('/admin/customers/' + customerId + '/cards/' + cardId + '/versions/' + versionId + '/restore', {});
        if (!r.success) throw new Error(r.message || 'Fehler');
        // Card lokal updaten
        const idx = sbCards.findIndex(c => c.id == cardId);
        if (idx >= 0) sbCards[idx] = r.data;
        sbRenderCards();
        sbCloseHistory();
        App.showNotification('Version wiederhergestellt', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
};

window.sbCloseHistory = function() {
    const overlay = document.getElementById('sb-history-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

function sbFormatDateTime(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ----- Card Maximize (Vollbild-Modal) -----
let sbMaxState = null; // { cardId, originalParent, originalNext, restoreBody }

window.sbMaximizeCard = function(cardId) {
    const card = document.querySelector(`.sb-card[data-card-id="${cardId}"]`);
    if (!card) return;
    const cardData = sbCards.find(c => c.id == cardId);
    const title = card.querySelector('.sb-card-title')?.textContent || 'Card';
    const iconHtml = card.querySelector('.sb-card-icon')?.outerHTML || '';
    const body = card.querySelector('.sb-card-body');
    if (!body) return;

    // Overlay erzeugen
    let overlay = document.getElementById('sb-maximize-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-maximize-overlay';
        overlay.className = 'sb-maximize-overlay';
        overlay.onclick = (e) => { if (e.target === overlay) sbCloseMaximize(); };
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-maximize-card">
            <div class="sb-maximize-head">
                ${iconHtml}
                <strong style="flex:1;font-size: var(--d-fs-base);">${esc(title)}</strong>
                <button class="sb-card-action" onclick="sbCloseMaximize()" title="Schließen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="sb-maximize-body" id="sb-maximize-body"></div>
        </div>
    `;
    overlay.classList.add('open');

    // Body-Inhalt in den Modal-Body verschieben
    const modalBody = overlay.querySelector('#sb-maximize-body');
    sbMaxState = { cardId, body, originalChildren: Array.from(body.childNodes) };
    body.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:1rem;font-size: var(--d-fs-sm);">— vollständig geöffnet —</div>';
    sbMaxState.originalChildren.forEach(node => modalBody.appendChild(node));
    document.addEventListener('keydown', sbMaxKeyHandler);
};

window.sbCloseMaximize = function() {
    const overlay = document.getElementById('sb-maximize-overlay');
    if (!overlay || !sbMaxState) return;
    const modalBody = overlay.querySelector('#sb-maximize-body');
    // Inhalt zurück in die Original-Card
    const targetBody = sbMaxState.body;
    if (targetBody && modalBody) {
        targetBody.innerHTML = '';
        Array.from(modalBody.childNodes).forEach(node => targetBody.appendChild(node));
    }
    overlay.classList.remove('open');
    overlay.innerHTML = '';
    sbMaxState = null;
    document.removeEventListener('keydown', sbMaxKeyHandler);
};
function sbMaxKeyHandler(e) { if (e.key === 'Escape') sbCloseMaximize(); }

// ----- More-Menu (KI-Anordnung + Import) -----
window.sbToggleMoreMenu = function(e) {
    e.stopPropagation();
    const menu = document.getElementById('sb-more-menu');
    document.getElementById('sb-add-menu')?.classList.remove('open');
    menu?.classList.toggle('open');
};

window.sbAutoArrange = async function() {
    document.getElementById('sb-more-menu')?.classList.remove('open');
    if (!confirm('Aktuelle Anordnung wird durch einen KI-Vorschlag ersetzt. Größen und Reihenfolge ändern sich. Fortfahren?')) return;

    const btn = document.getElementById('sb-more-btn');
    if (btn) btn.classList.add('busy');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/cards/auto-arrange', {});
        if (!r.success) throw new Error(r.message || 'Fehler');
        sbCards = r.data.cards || [];
        sbRenderCards();
        document.querySelectorAll('.sb-card').forEach(el => {
            el.classList.add('search-hit');
            setTimeout(() => el.classList.remove('search-hit'), 800);
        });
        App.showNotification('KI hat ' + (r.data.applied || 0) + ' Cards neu angeordnet', 'success');
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    } finally {
        if (btn) btn.classList.remove('busy');
    }
};

// ----- KI-Steckbrief-Import -----
window.sbStartImport = function() {
    document.getElementById('sb-more-menu')?.classList.remove('open');
    document.getElementById('sb-import-input')?.click();
};

window.sbImportFileChosen = async function(files) {
    if (!files || !files[0]) return;
    const file = files[0];
    const ok = confirm(
        'Steckbrief „' + file.name + '" wird von der KI analysiert.\n\n' +
        '• Profilfelder werden ggf. überschrieben (Beschreibung, Tonalität, USPs etc.)\n' +
        '• Neue Cards werden angelegt\n' +
        '• Bestehende Cards mit passendem Titel werden ergänzt\n\n' +
        'Fortfahren?'
    );
    document.getElementById('sb-import-input').value = '';
    if (!ok) return;

    sbImportShow(file.name);
    sbImportSetStep('upload', 'active');

    try {
        const fd = new FormData();
        fd.append('file', file);
        sbImportSetStep('upload', 'done');
        sbImportSetStep('extract', 'active');

        const r = await fetch('/api/v1/admin/customers/' + customerId + '/cards/auto-import', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-Token': App.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
        });

        sbImportSetStep('extract', 'done');
        sbImportSetStep('analyze', 'active');

        const json = await r.json();
        if (!json.success) throw new Error(json.message || 'Fehler');

        sbImportSetStep('analyze', 'done');
        sbImportSetStep('apply', 'done');

        sbCards = json.data.cards || [];
        sbRenderCards();
        const a = json.data.applied || {};
        sbImportFinish(`Fertig: ${a.created || 0} neu · ${a.merged || 0} ergänzt · ${a.profile_fields || 0} Profil-Felder`);
        setTimeout(() => location.reload(), 1500);
    } catch (e) {
        const lastActive = document.querySelector('.sb-import-step.active');
        if (lastActive) lastActive.classList.replace('active', 'error');
        sbImportError(e.message || 'Fehler');
    }
};

// ===== Import-Progress-Modal =====
function sbImportShow(filename) {
    let overlay = document.getElementById('sb-import-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-import-overlay';
        overlay.className = 'sb-import-overlay';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="sb-import-modal">
            <div class="sb-import-head">
                <span class="material-symbols-rounded">auto_awesome</span>
                <div>
                    <h3>Steckbrief wird verarbeitet…</h3>
                    <small>${esc(filename)}</small>
                </div>
            </div>
            <div class="sb-import-body">
                <div class="sb-import-step" data-step="upload">
                    <div class="step-dot"><span class="material-symbols-rounded">check</span></div>
                    Datei hochladen
                </div>
                <div class="sb-import-step" data-step="extract">
                    <div class="step-dot"><span class="material-symbols-rounded">check</span></div>
                    Text extrahieren
                </div>
                <div class="sb-import-step" data-step="analyze">
                    <div class="step-dot"><span class="material-symbols-rounded">check</span></div>
                    KI analysiert Inhalt &amp; vergleicht mit Cards
                </div>
                <div class="sb-import-step" data-step="apply">
                    <div class="step-dot"><span class="material-symbols-rounded">check</span></div>
                    Cards anlegen &amp; Profil aktualisieren
                </div>
                <div class="sb-import-progress"></div>
                <div class="sb-import-hint">
                    Die KI liest deinen Steckbrief, gleicht ihn mit bestehenden Cards ab und verteilt den Inhalt sinnvoll.
                    Das dauert meist 15–60&nbsp;Sekunden je nach Dokumentgröße — bitte nicht schließen.
                </div>
            </div>
            <div class="sb-import-foot">
                <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="sbImportClose()" id="sb-import-close-btn" disabled>Schließen</button>
            </div>
        </div>
    `;
    overlay.classList.add('open');
}

function sbImportSetStep(stepKey, state) {
    const el = document.querySelector(`.sb-import-step[data-step="${stepKey}"]`);
    if (!el) return;
    el.classList.remove('active', 'done', 'error');
    if (state) el.classList.add(state);
}

function sbImportFinish(msg) {
    const overlay = document.getElementById('sb-import-overlay');
    if (!overlay) return;
    const head = overlay.querySelector('.sb-import-head h3');
    if (head) head.textContent = msg;
    overlay.querySelector('.sb-import-progress')?.remove();
    overlay.querySelector('.sb-import-hint')?.remove();
    const close = overlay.querySelector('#sb-import-close-btn');
    if (close) close.disabled = false;
}

function sbImportError(msg) {
    const overlay = document.getElementById('sb-import-overlay');
    if (!overlay) return;
    const head = overlay.querySelector('.sb-import-head h3');
    if (head) head.textContent = 'Import fehlgeschlagen';
    overlay.querySelector('.sb-import-progress')?.remove();
    const hint = overlay.querySelector('.sb-import-hint');
    if (hint) {
        hint.style.background = 'var(--rose-50)';
        hint.style.borderColor = 'var(--rose-200)';
        hint.style.color = 'var(--rose-700)';
        hint.textContent = msg;
    }
    const close = overlay.querySelector('#sb-import-close-btn');
    if (close) close.disabled = false;
}

window.sbImportClose = function() {
    const overlay = document.getElementById('sb-import-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
};

// =================================================================
// Stufe A: Import mit Vorschau (neue Variante)
// =================================================================
let sbImportV2State = null; // { importId, proposed: [...], accepted: Set, mode: 'upload'|'analyze'|'preview' }

window.sbStartImportPreview = function() {
    document.getElementById('sb-more-menu')?.classList.remove('open');
    sbImportV2OpenChoice();
};

function sbImportV2OpenChoice() {
    let overlay = document.getElementById('sb-impv2-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-impv2-overlay';
        overlay.className = 'thx-modal-backdrop sb-impv2-overlay';
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
        <div class="thx-modal sb-impv2-modal" onclick="event.stopPropagation()">
            <div class="thx-modal-header">
                <h3 style="margin:0;">Steckbrief importieren</h3>
                <button class="thx-modal-close" onclick="sbImportV2Close()" title="Schließen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="thx-modal-body">
                <p style="color:#475569;margin:0 0 16px 0;">Lade ein Steckbrief-Dokument hoch oder füge Text ein. Die KI schlägt Karten vor, die Du einzeln annehmen oder ablehnen kannst.</p>
                <div class="sb-impv2-choice">
                    <button class="sb-impv2-choice-btn" onclick="document.getElementById('sb-import-input-v2').click()">
                        <span class="material-symbols-rounded">upload_file</span>
                        <strong>Datei hochladen</strong>
                        <small>DOCX, PDF, TXT, MD, HTML</small>
                    </button>
                    <button class="sb-impv2-choice-btn" onclick="sbImportV2ShowTextarea()">
                        <span class="material-symbols-rounded">edit_note</span>
                        <strong>Text einfügen</strong>
                        <small>Roher Text aus E-Mail, Notiz, Slack</small>
                    </button>
                </div>
                <div id="sb-impv2-textarea-wrap" style="display:none;margin-top:16px;">
                    <label style="display:block;font-size:var(--d-fs-sm);font-weight:600;color:#334155;margin-bottom:6px;">Steckbrief-Text</label>
                    <textarea id="sb-impv2-textarea" class="thx-input" style="width:100%;min-height:240px;font-family:inherit;font-size:var(--d-fs-sm);"
                              placeholder="Hier den Text einfügen — die KI strukturiert ihn in Karten auf …"></textarea>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                        <button class="thx-btn thx-btn-ghost" onclick="document.getElementById('sb-impv2-textarea-wrap').style.display='none'">Abbrechen</button>
                        <button class="thx-btn thx-btn-primary" onclick="sbImportV2SubmitText()">Analysieren</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    overlay.classList.add('open');
    overlay.onclick = (e) => { if (e.target === overlay) sbImportV2Close(); };
}

window.sbImportV2ShowTextarea = function() {
    document.getElementById('sb-impv2-textarea-wrap').style.display = 'block';
    document.getElementById('sb-impv2-textarea')?.focus();
};

window.sbImportV2SubmitText = async function() {
    const text = document.getElementById('sb-impv2-textarea')?.value || '';
    if (text.trim().length < 30) {
        App.showNotification('Bitte mindestens ein paar Sätze einfügen.', 'error');
        return;
    }
    sbImportV2ShowProgress('Text wird hochgeladen …');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/steckbrief-import', { text, label: 'Text-Eingabe' });
        if (!r.success) throw new Error(r.message || 'Fehler');
        await sbImportV2RunAnalyze(r.data.id);
    } catch (e) {
        sbImportV2ShowError(e.message || 'Fehler');
    }
};

window.sbImportV2FileChosen = async function(files) {
    if (!files || !files[0]) return;
    const file = files[0];
    document.getElementById('sb-import-input-v2').value = '';
    sbImportV2ShowProgress('Datei wird hochgeladen: ' + file.name);
    try {
        const fd = new FormData();
        fd.append('file', file);
        const r = await fetch('/api/v1/admin/customers/' + customerId + '/steckbrief-import', {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-Token': App.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await r.json();
        if (!json.success) throw new Error(json.message || 'Fehler');
        await sbImportV2RunAnalyze(json.data.id);
    } catch (e) {
        sbImportV2ShowError(e.message || 'Fehler');
    }
};

async function sbImportV2RunAnalyze(importId) {
    sbImportV2ShowProgress('KI analysiert den Steckbrief und schlägt Karten vor …');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/steckbrief-import/' + importId + '/analyze', {});
        if (!r.success) throw new Error(r.message || 'Fehler');
        const proposed = r.data.proposed_cards_decoded || [];
        if (proposed.length === 0) {
            sbImportV2ShowError('Die KI konnte keine Karten aus dem Dokument erkennen.');
            return;
        }
        sbImportV2State = {
            importId,
            proposed,
            accepted: new Set(proposed.map((_, i) => i)), // alle vorausgewählt
        };
        sbImportV2RenderPreview();
    } catch (e) {
        sbImportV2ShowError(e.message || 'Fehler');
    }
}

function sbImportV2ShowProgress(msg) {
    const overlay = document.getElementById('sb-impv2-overlay');
    if (!overlay) return;
    overlay.innerHTML = `
        <div class="thx-modal sb-impv2-modal" onclick="event.stopPropagation()">
            <div class="thx-modal-header">
                <h3 style="margin:0;">Steckbrief wird verarbeitet</h3>
            </div>
            <div class="thx-modal-body" style="text-align:center;padding:48px 24px;">
                <div class="sb-impv2-spinner"></div>
                <div style="margin-top:16px;color:#475569;font-size:var(--d-fs-sm);">${esc(msg)}</div>
            </div>
        </div>`;
}

function sbImportV2ShowError(msg) {
    const overlay = document.getElementById('sb-impv2-overlay');
    if (!overlay) return;
    overlay.innerHTML = `
        <div class="thx-modal sb-impv2-modal" onclick="event.stopPropagation()">
            <div class="thx-modal-header">
                <h3 style="margin:0;color:var(--rose-700);">Import fehlgeschlagen</h3>
                <button class="thx-modal-close" onclick="sbImportV2Close()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="thx-modal-body">
                <div style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:8px;padding:12px;color:var(--rose-700);font-size:var(--d-fs-sm);">
                    ${esc(msg)}
                </div>
                <div style="margin-top:16px;text-align:right;">
                    <button class="thx-btn thx-btn-primary" onclick="sbImportV2OpenChoice()">Erneut versuchen</button>
                </div>
            </div>
        </div>`;
}

function sbImportV2RenderPreview() {
    const overlay = document.getElementById('sb-impv2-overlay');
    if (!overlay) return;
    const { proposed, accepted } = sbImportV2State;
    overlay.innerHTML = `
        <div class="thx-modal sb-impv2-modal sb-impv2-modal-wide" onclick="event.stopPropagation()">
            <div class="thx-modal-header">
                <h3 style="margin:0;">Karten-Vorschläge (${proposed.length})</h3>
                <button class="thx-modal-close" onclick="sbImportV2Close()"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="thx-modal-body sb-impv2-preview-body">
                <div class="sb-impv2-toolbar">
                    <button class="thx-btn thx-btn-ghost thx-btn-small" onclick="sbImportV2SetAll(true)">Alle auswählen</button>
                    <button class="thx-btn thx-btn-ghost thx-btn-small" onclick="sbImportV2SetAll(false)">Keine auswählen</button>
                    <div style="flex:1;"></div>
                    <span id="sb-impv2-count" style="color:#64748b;font-size:var(--d-fs-sm);">${accepted.size} von ${proposed.length} ausgewählt</span>
                </div>
                <div class="sb-impv2-list">
                    ${proposed.map((p, i) => sbImportV2CardHtml(i, p)).join('')}
                </div>
            </div>
            <div class="thx-modal-footer">
                <button class="thx-btn thx-btn-ghost" onclick="sbImportV2Close()">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" onclick="sbImportV2Commit()" id="sb-impv2-commit-btn">
                    <span class="material-symbols-rounded">check</span>
                    Auswahl übernehmen
                </button>
            </div>
        </div>`;
}

function sbImportV2CardHtml(idx, p) {
    const checked = sbImportV2State.accepted.has(idx);
    const tabLabel = { uebersicht: 'Übersicht', inhalte: 'Inhalte', personen: 'Personen', marke: 'Marke' }[p.target_tab] || p.target_tab;
    const typeIcon = { links: 'link', richtext: 'edit_note', brand: 'palette', contacts: 'groups', kpi: 'monitoring', tracking_status: 'fact_check' }[p.type] || 'square';
    return `
        <label class="sb-impv2-item ${checked ? 'is-checked' : ''}" data-idx="${idx}">
            <input type="checkbox" ${checked ? 'checked' : ''} onchange="sbImportV2Toggle(${idx}, this.checked)">
            <div class="sb-impv2-item-body">
                <div class="sb-impv2-item-head">
                    <span class="sb-impv2-item-type"><span class="material-symbols-rounded">${typeIcon}</span></span>
                    <span class="sb-impv2-item-title">${esc(p.title || '(ohne Titel)')}</span>
                    <span class="sb-impv2-item-tab">${esc(tabLabel)}</span>
                </div>
                <div class="sb-impv2-item-preview">${sbImportV2PreviewHtml(p)}</div>
                ${p.reason ? `<div class="sb-impv2-item-reason"><em>${esc(p.reason)}</em></div>` : ''}
            </div>
        </label>`;
}

function sbImportV2PreviewHtml(p) {
    const b = p.body || {};
    switch (p.type) {
        case 'links':
            return '<ul style="margin:4px 0;padding-left:18px;">' + (b.items || []).slice(0, 6).map(it =>
                `<li><strong>${esc(it.title || '')}</strong> <span style="color:#64748b;">${esc(it.url || '')}</span></li>`
            ).join('') + ((b.items || []).length > 6 ? `<li style="color:#94a3b8;">… ${(b.items || []).length - 6} weitere</li>` : '') + '</ul>';
        case 'richtext':
            return '<div class="sb-impv2-html-preview">' + (b.html || '') + '</div>';
        case 'brand':
            const colors = (b.colors || []).slice(0, 8).map(c => `<span style="display:inline-block;padding:2px 8px;background:${esc(c.value || '#ccc')};color:#fff;border-radius:6px;font-size:var(--d-fs-xs);margin:2px 2px 2px 0;">${esc(c.name || c.value || '')}</span>`).join('');
            const fonts = (b.fonts || []).slice(0, 4).map(f => `<span style="display:inline-block;padding:2px 8px;background:#f1f5f9;border-radius:6px;font-size:var(--d-fs-xs);margin:2px 2px 2px 0;">${esc(f.name || '')}</span>`).join('');
            return colors + '<br>' + fonts;
        case 'contacts':
            return (b.groups || []).map(g =>
                `<strong>${esc(g.title || '')}</strong>: ${(g.people || []).map(p => esc(p.role || '') + ' ' + esc(p.name || '')).join(', ')}`
            ).join('<br>');
        case 'kpi':
            return '<ul style="margin:4px 0;padding-left:18px;">' + (b.items || []).map(it =>
                `<li><strong>${esc(it.label || '')}</strong>: ${esc(it.value || '')} ${it.period ? '<em style="color:#64748b;">(' + esc(it.period) + ')</em>' : ''} ${it.target ? '— Ziel: ' + esc(it.target) : ''}</li>`
            ).join('') + '</ul>';
        case 'tracking_status':
            const stColor = { ok: '#047857', fehlt: '#b91c1c', tbd: '#b45309', na: '#64748b' };
            const stLabel = { ok: '✓ aktiv', fehlt: '✗ fehlt', tbd: '? offen', na: '– n/a' };
            return '<ul style="margin:4px 0;padding-left:0;list-style:none;">' + (b.items || []).map(it => {
                const c = stColor[it.status] || '#64748b';
                return `<li style="padding:2px 0;"><span style="color:${c};font-weight:600;font-size:var(--d-fs-xs);">${esc(stLabel[it.status] || it.status)}</span> ${esc(it.label || '')} ${it.note ? '<span style="color:#64748b;">— ' + esc(it.note) + '</span>' : ''}</li>`;
            }).join('') + '</ul>';
        default:
            return '<em style="color:#94a3b8;">Vorschau nicht verfügbar</em>';
    }
}

window.sbImportV2Toggle = function(idx, on) {
    if (on) sbImportV2State.accepted.add(idx);
    else sbImportV2State.accepted.delete(idx);
    document.querySelector(`.sb-impv2-item[data-idx="${idx}"]`)?.classList.toggle('is-checked', on);
    const cnt = document.getElementById('sb-impv2-count');
    if (cnt) cnt.textContent = sbImportV2State.accepted.size + ' von ' + sbImportV2State.proposed.length + ' ausgewählt';
};

window.sbImportV2SetAll = function(on) {
    sbImportV2State.accepted = new Set(on ? sbImportV2State.proposed.map((_, i) => i) : []);
    sbImportV2RenderPreview();
};

window.sbImportV2Commit = async function() {
    const btn = document.getElementById('sb-impv2-commit-btn');
    if (btn) btn.disabled = true;
    const accepted = Array.from(sbImportV2State.accepted);
    if (accepted.length === 0) {
        App.showNotification('Bitte mindestens eine Karte auswählen.', 'error');
        if (btn) btn.disabled = false;
        return;
    }
    try {
        const r = await App.post('/admin/customers/' + customerId + '/steckbrief-import/' + sbImportV2State.importId + '/commit', { accepted });
        if (!r.success) throw new Error(r.message || 'Fehler');
        App.showNotification(r.data.imported + ' Karten erstellt', 'success');
        sbImportV2Close();
        // Karten neu laden, damit die neuen sichtbar sind
        if (typeof sbReload === 'function') await sbReload();
        else location.reload();
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        if (btn) btn.disabled = false;
    }
};

window.sbImportV2Close = function() {
    const overlay = document.getElementById('sb-impv2-overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.innerHTML = ''; }
    sbImportV2State = null;
};

// =================================================================
// Stufe B: KI-Vorschlaege pro Karte aus der Wissensbasis
// =================================================================
window.sbOpenSuggestDrawer = async function(cardId) {
    const card = sbCards.find(c => c.id == cardId);
    if (!card) return;
    const supported = ['links','kpi','tracking_status','contacts','brand','richtext'];
    if (!supported.includes(card.type)) {
        App.showNotification('Für diesen Karten-Typ gibt es noch keine KI-Vorschläge.', 'info');
        return;
    }
    sbSuggestDrawerShow(card);
    sbSuggestDrawerLoad(cardId);
};

function sbSuggestDrawerShow(card) {
    let drawer = document.getElementById('sb-suggest-drawer');
    if (!drawer) {
        drawer = document.createElement('div');
        drawer.id = 'sb-suggest-drawer';
        drawer.className = 'sb-suggest-drawer';
        document.body.appendChild(drawer);
    }
    drawer.dataset.cardId = card.id;
    drawer.innerHTML = `
        <div class="sb-suggest-head">
            <div>
                <strong>KI-Vorschläge</strong>
                <small>für „${esc(card.title || 'Karte')}"</small>
            </div>
            <button class="sb-suggest-close" onclick="sbCloseSuggestDrawer()" title="Schließen">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="sb-suggest-toolbar">
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbSuggestRegenerate(${card.id})" id="sb-suggest-regen">
                <span class="material-symbols-rounded">auto_awesome</span>
                Vorschläge generieren
            </button>
            <span style="flex:1;"></span>
            <span class="sb-suggest-meta" id="sb-suggest-meta"></span>
        </div>
        <div class="sb-suggest-body" id="sb-suggest-body">
            <div class="sb-suggest-hint">Lade Vorschläge…</div>
        </div>
    `;
    drawer.classList.add('open');
}

async function sbSuggestDrawerLoad(cardId) {
    const body = document.getElementById('sb-suggest-body');
    if (!body) return;
    try {
        const r = await App.get('/admin/customer-cards/' + cardId + '/suggest');
        if (!r.success) throw new Error(r.message);
        sbSuggestRenderList(r.data.suggestions || []);
    } catch (e) {
        body.innerHTML = `<div class="sb-suggest-hint sb-suggest-error">${esc(e.message || 'Fehler beim Laden')}</div>`;
    }
}

function sbSuggestRenderList(list) {
    const body = document.getElementById('sb-suggest-body');
    const meta = document.getElementById('sb-suggest-meta');
    if (!body) return;
    if (meta) meta.textContent = list.length === 0 ? 'Noch keine Vorschläge' : list.length + ' offene Vorschläge';
    if (list.length === 0) {
        body.innerHTML = `<div class="sb-suggest-hint">Klick auf „Vorschläge generieren", um Belege aus der Wissensbasis zu finden.</div>`;
        return;
    }
    body.innerHTML = list.map(s => sbSuggestItemHtml(s)).join('');
}

function sbSuggestItemHtml(s) {
    const sources = (s.source_docs || []).slice(0, 3);
    const sourcesMore = (s.source_docs || []).length > 3 ? ' …' : '';
    return `
        <div class="sb-suggest-item" data-id="${s.id}">
            <div class="sb-suggest-item-snippet">${esc(s.snippet || '')}</div>
            ${sources.length ? `<div class="sb-suggest-item-sources">
                <span class="material-symbols-rounded" style="font-size:14px;">source</span>
                ${sources.map(t => `<span class="sb-suggest-source-chip">${esc(t)}</span>`).join('')}${sourcesMore}
            </div>` : ''}
            <div class="sb-suggest-item-actions">
                <button class="thx-btn thx-btn-ghost thx-btn-small" onclick="sbSuggestDecide(${s.id}, 'reject')">
                    <span class="material-symbols-rounded">close</span> Ablehnen
                </button>
                <button class="thx-btn thx-btn-primary thx-btn-small" onclick="sbSuggestDecide(${s.id}, 'accept')">
                    <span class="material-symbols-rounded">check</span> Übernehmen
                </button>
            </div>
        </div>`;
}

window.sbSuggestRegenerate = async function(cardId) {
    const btn = document.getElementById('sb-suggest-regen');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded sb-spin">progress_activity</span> Wird analysiert…'; }
    const body = document.getElementById('sb-suggest-body');
    if (body) body.innerHTML = `<div class="sb-suggest-hint"><span class="material-symbols-rounded sb-spin">progress_activity</span> KI durchsucht die Wissensbasis…</div>`;
    try {
        const r = await App.post('/admin/customer-cards/' + cardId + '/suggest', {});
        if (!r.success) throw new Error(r.message);
        if (r.data.created === 0) {
            if (body) body.innerHTML = `<div class="sb-suggest-hint">Keine neuen Fakten in der Wissensbasis gefunden.${r.data.note ? '<br><small>' + esc(r.data.note) + '</small>' : ''}</div>`;
        } else {
            App.showNotification(r.data.created + ' neue Vorschläge', 'success');
        }
        await sbSuggestDrawerLoad(cardId);
    } catch (e) {
        if (body) body.innerHTML = `<div class="sb-suggest-hint sb-suggest-error">${esc(e.message || 'Fehler')}</div>`;
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">auto_awesome</span> Vorschläge generieren';
        }
    }
};

window.sbSuggestDecide = async function(suggestionId, action) {
    const drawer = document.getElementById('sb-suggest-drawer');
    const cardId = drawer?.dataset.cardId;
    const itemEl = document.querySelector(`.sb-suggest-item[data-id="${suggestionId}"]`);
    if (itemEl) itemEl.classList.add('sb-suggest-busy');
    try {
        const r = await App.post('/admin/customer-cards/' + cardId + '/suggestions/' + suggestionId, { action });
        if (!r.success) throw new Error(r.message);
        if (action === 'accept') App.showNotification('Übernommen', 'success');
        // Karte neu laden im sbCards-Array
        if (action === 'accept' && cardId) {
            const card = sbCards.find(c => c.id == cardId);
            if (card) {
                try {
                    const cr = await App.get('/admin/customers/' + customerId + '/cards/' + cardId);
                    if (cr.success && cr.data) {
                        Object.assign(card, cr.data);
                        sbRenderCards();
                    }
                } catch (_) {}
            }
        }
        await sbSuggestDrawerLoad(cardId);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        if (itemEl) itemEl.classList.remove('sb-suggest-busy');
    }
};

window.sbCloseSuggestDrawer = function() {
    document.getElementById('sb-suggest-drawer')?.classList.remove('open');
};

// =================================================================
// Phase 4: Globales „Steckbrief autobefuellen"
// =================================================================
window.sbAutoSuggestAll = async function() {
    document.getElementById('sb-more-menu')?.classList.remove('open');
    if (!confirm('KI durchsucht die Wissensbasis dieses Kunden und schlägt für jede Karte Inhalte vor. Das kann ein paar Minuten dauern. Fortfahren?')) return;
    sbAutoSuggestModal('start');
    try {
        const r = await App.post('/admin/customers/' + customerId + '/steckbrief-suggest', {});
        if (!r.success) throw new Error(r.message);
        sbAutoSuggestModal('done', r.data);
    } catch (e) {
        sbAutoSuggestModal('error', { message: e.message || 'Fehler' });
    }
};

function sbAutoSuggestModal(state, data) {
    let overlay = document.getElementById('sb-autosug-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'sb-autosug-overlay';
        overlay.className = 'thx-modal-backdrop sb-impv2-overlay';
        document.body.appendChild(overlay);
    }
    if (state === 'start') {
        overlay.innerHTML = `
            <div class="thx-modal sb-impv2-modal">
                <div class="thx-modal-header"><h3 style="margin:0;">Steckbrief autobefüllen</h3></div>
                <div class="thx-modal-body" style="text-align:center;padding:48px 24px;">
                    <div class="sb-impv2-spinner"></div>
                    <div style="margin-top:16px;color:#475569;">KI analysiert die Wissensbasis und sammelt Vorschläge für alle Karten…</div>
                </div>
            </div>`;
    } else if (state === 'done') {
        overlay.innerHTML = `
            <div class="thx-modal sb-impv2-modal">
                <div class="thx-modal-header"><h3 style="margin:0;">Fertig</h3>
                    <button class="thx-modal-close" onclick="sbAutoSuggestClose()"><span class="material-symbols-rounded">close</span></button>
                </div>
                <div class="thx-modal-body">
                    <p style="margin:0 0 8px 0;color:#1e293b;">
                        <strong>${data.suggestions_created || 0}</strong> Vorschläge erzeugt,
                        verteilt auf <strong>${data.cards_processed || 0}</strong> Karten.
                        ${data.cards_skipped ? `<br><small style="color:#94a3b8;">${data.cards_skipped} Karten übersprungen.</small>` : ''}
                    </p>
                    <p style="color:#475569;font-size:var(--d-fs-sm);margin:12px 0;">
                        Klick rechts oben auf einer Karte auf <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;color:var(--thoxan-700);">auto_awesome</span>,
                        um die Vorschläge zu prüfen und einzeln zu übernehmen oder abzulehnen.
                    </p>
                </div>
                <div class="thx-modal-footer">
                    <button class="thx-btn thx-btn-primary" onclick="sbAutoSuggestClose()">Verstanden</button>
                </div>
            </div>`;
    } else {
        overlay.innerHTML = `
            <div class="thx-modal sb-impv2-modal">
                <div class="thx-modal-header"><h3 style="margin:0;color:var(--rose-700);">Fehler</h3>
                    <button class="thx-modal-close" onclick="sbAutoSuggestClose()"><span class="material-symbols-rounded">close</span></button>
                </div>
                <div class="thx-modal-body">
                    <div style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:8px;padding:12px;color:var(--rose-700);">
                        ${esc(data.message || 'Fehler')}
                    </div>
                </div>
            </div>`;
    }
    overlay.classList.add('open');
}

window.sbAutoSuggestClose = function() {
    const o = document.getElementById('sb-autosug-overlay');
    if (o) { o.classList.remove('open'); o.innerHTML = ''; }
};

// Outside-Click schließt das More-Menu
document.addEventListener('click', (e) => {
    const menu = document.getElementById('sb-more-menu');
    if (menu && !e.target.closest('.sb-more-wrap')) menu.classList.remove('open');
});

// ----- Add-Menu -----
window.sbToggleAddMenu = function(e) {
    e.stopPropagation();
    const menu = document.getElementById('sb-add-menu');
    menu.classList.toggle('open');
};
document.addEventListener('click', (e) => {
    const menu = document.getElementById('sb-add-menu');
    if (menu && !e.target.closest('.sb-add-wrap')) menu.classList.remove('open');
});

// ----- Suche & Filter -----
let sbPrevSearch = '';
window.sbApplySearch = function(query) {
    const q = (query || '').toLowerCase().trim();
    document.getElementById('sb-search-clear').style.display = q ? 'flex' : 'none';

    // Cards
    document.querySelectorAll('.sb-card').forEach(el => {
        const cardId = el.dataset.cardId;
        const card = sbCards.find(c => c.id == cardId);
        const hay = card ? sbCardHaystack(card) : '';
        const hit = !q || hay.includes(q);
        el.classList.toggle('sb-hidden', !hit);
        if (q) {
            el.classList.remove('collapsed'); // bei Suche immer aufgeklappt
            if (!sbPrevSearch && hit) el.classList.add('search-hit');
            else el.classList.remove('search-hit');
        } else {
            // Original is_collapsed wiederherstellen
            const c = sbCards.find(c => c.id == cardId);
            if (c?.is_collapsed) el.classList.add('collapsed');
            el.classList.remove('search-hit');
        }
    });


    sbPrevSearch = q;
};

window.sbClearSearch = function() {
    const inp = document.getElementById('sb-search');
    if (inp) inp.value = '';
    sbApplySearch('');
};

function sbCardHaystack(card) {
    const parts = [card.title || ''];
    if (card.is_system) {
        // System-Cards: aktuellen DOM-Body durchsuchen (Profil-Felder, Wissens-Liste, Asana-Stats)
        const bodyEl = document.querySelector(`.sb-card[data-card-id="${card.id}"] .sb-card-body`);
        if (bodyEl) parts.push(bodyEl.textContent || '');
        return parts.join(' ').toLowerCase();
    }
    const body = card.body_decoded || {};
    switch (card.type) {
        case 'links':
            (body.items || []).forEach(it => parts.push(it.title || '', it.url || '', it.note || ''));
            break;
        case 'richtext':
            parts.push(stripHtml(body.html || ''));
            break;
        case 'brand':
            (body.colors || []).forEach(c => parts.push(c.name || '', c.value || ''));
            (body.fonts || []).forEach(f => parts.push(f.name || '', f.note || ''));
            parts.push(body.note || '');
            break;
        case 'kpi':
            (body.items || []).forEach(it => parts.push(it.label || '', it.value || '', it.target || '', it.period || ''));
            break;
        case 'tracking_status':
            (body.items || []).forEach(it => parts.push(it.label || '', it.note || '', it.status || ''));
            break;
    }
    (card.files || []).forEach(f => parts.push(f.file_name || '', f.title || ''));
    return parts.join(' ').toLowerCase();
}

function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || '';
}

// ----- Helpers -----
function sbFmtSize(b) {
    if (!b) return '';
    if (b < 1024) return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(0) + ' KB';
    return (b / 1024 / 1024).toFixed(1) + ' MB';
}
</script>

<!-- Site-Monitor-Stats-Modal — fuer Klick auf einzelnen Monitor in Monitoring-Karte -->
<?php include __DIR__ . '/_sm_stats_modal.php'; ?>

<!-- Shared Regel-Modals (gleicher Code wie auf /rules) -->
<?php include __DIR__ . '/../rules/_rule_modal.php'; ?>
<?php include __DIR__ . '/../rules/_project_types_modal.php'; ?>
