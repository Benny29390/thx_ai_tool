<?php /* Prompt-Insights — Anonymisierungs-Whitelist */ ?>
<style>
.pi-wl-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); overflow: hidden; }
.pi-wl-table th { text-align: left; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); color: var(--slate-500); font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; border-bottom: 2px solid var(--slate-200); }
.pi-wl-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); }
.pi-wl-source { font-size: var(--d-fs-xs); padding: 1px 6px; border-radius: 999px; background: var(--slate-100); color: var(--slate-600); }
.pi-wl-source.auto-customer { background: var(--thoxan-50); color: var(--thoxan-700); }
.pi-wl-source.auto-person { background: var(--emerald-50); color: var(--emerald-700); }
</style>

<div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);margin-bottom:var(--d-section-gap);">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Anonymisierungs-Whitelist</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Liste von Eigennamen (Kunden, Mitarbeiter, Firmen, Projekte), die in importierten Prompts <strong>vor</strong> dem Speichern in der DB durch Platzhalter ersetzt werden.
        Vollworten-Match, Groß-/Kleinschreibung egal. <em>E-Mails, Telefonnummern, IBANs und URLs werden zusätzlich per Regex erkannt.</em>
    </p>
    <form onsubmit="event.preventDefault();piWlAdd()" style="display:flex;gap:var(--d-row-gap);flex-wrap:wrap;">
        <input type="text" id="pi-wl-original" placeholder="Eigenname (z.B. „Thoxan", „MaxMustermann")" style="flex:1;min-width:240px;padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);font-family:inherit;">
        <input type="text" id="pi-wl-placeholder" placeholder="&lt;NAME&gt;" value="<NAME>" style="width:160px;padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);font-family:inherit;">
        <button type="submit" class="thx-btn thx-btn-primary thx-btn-small">Hinzufügen</button>
        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="piWlInit()" title="Aus Kunden + Team auto-vorschlagen">
            <span class="material-symbols-rounded" style="font-size:14px;">auto_fix_high</span> Aus Kunden+Team
        </button>
    </form>
</div>

<table class="pi-wl-table">
    <thead><tr>
        <th>Eigenname</th><th style="width:140px;">Platzhalter</th><th style="width:110px;">Quelle</th><th style="width:50px;"></th>
    </tr></thead>
    <tbody id="pi-wl-tbody"><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</td></tr></tbody>
</table>

<script>
'use strict';
async function piWlLoad() {
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/whitelist');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piWlRender(j.data.entries);
    } catch (e) {
        document.getElementById('pi-wl-tbody').innerHTML = '<tr><td colspan="4" style="color:var(--rose-600);text-align:center;padding:30px;">' + e.message + '</td></tr>';
    }
}
function piWlRender(entries) {
    const tbody = document.getElementById('pi-wl-tbody');
    if (!entries.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--slate-400);">Noch keine Einträge. Klick auf „Aus Kunden+Team" für Auto-Vorschlag.</td></tr>';
        return;
    }
    tbody.innerHTML = entries.map(e => `<tr>
        <td><strong>${piEsc(e.original)}</strong></td>
        <td style="font-family:ui-monospace,monospace;font-size:var(--d-fs-xs);color:var(--slate-600);">${piEsc(e.placeholder)}</td>
        <td><span class="pi-wl-source ${piEsc(e.source)}">${piEsc(e.source)}</span></td>
        <td><button class="thx-btn thx-btn-small thx-btn-danger" onclick="piWlDelete(${e.id})" title="Eintrag löschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button></td>
    </tr>`).join('');
}
function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
async function piWlAdd() {
    const original = document.getElementById('pi-wl-original').value.trim();
    const placeholder = document.getElementById('pi-wl-placeholder').value.trim() || '<NAME>';
    if (original.length < 2) { App.showNotification('Mindestens 2 Zeichen', 'error'); return; }
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/whitelist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ original, placeholder }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        document.getElementById('pi-wl-original').value = '';
        App.showNotification('Hinzugefügt', 'success');
        piWlLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}
async function piWlDelete(id) {
    if (!confirm('Eintrag löschen?')) return;
    try {
        await fetch('/api/v1/admin/prompt-insights/whitelist/' + id, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        piWlLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}
async function piWlInit() {
    if (!confirm('Aus Kunden + Team auto-befüllen? Bestehende Einträge werden nicht ersetzt.')) return;
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/whitelist/init', {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification(j.message, 'success');
        piWlLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}
piWlLoad();
</script>
