<?php /* Prompt-Insights — Importe-Tab */ ?>
<style>
.pi-upload-card {
    background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
    padding: var(--d-card-pad); margin-bottom: var(--d-section-gap);
}
.pi-upload-zone {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; min-height: 120px;
    border: 2px dashed var(--slate-300); border-radius: var(--d-card-radius);
    padding: 24px; text-align: center; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.pi-upload-zone:hover, .pi-upload-zone.is-drag { background: var(--thoxan-50); border-color: var(--thoxan-400); }
.pi-upload-zone .material-symbols-rounded { font-size: 36px; line-height: 1; color: var(--slate-400); display: block; }
.pi-upload-zone p { margin: 0; color: var(--slate-600); font-size: var(--d-fs-sm); }
.pi-import-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); overflow: hidden; }
.pi-import-table th {
    text-align: left; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x);
    color: var(--slate-500); font-size: var(--d-fs-xs);
    text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600;
    border-bottom: 2px solid var(--slate-200);
}
.pi-import-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); }
.pi-import-table tr:last-child td { border-bottom: none; }
.pi-source-badge { display: inline-block; padding: 1px var(--d-control-pad-x); border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; }
.pi-source-claude  { background: rgba(168, 85, 247, 0.12); color: #7e22ce; }
.pi-source-chatgpt { background: var(--emerald-50); color: var(--emerald-700); }
.pi-source-unknown { background: var(--slate-100); color: var(--slate-600); }
.pi-status-done       { color: var(--emerald-700); }
.pi-status-failed     { color: var(--rose-700); }
.pi-status-processing { color: var(--amber-700); }
</style>

<div class="pi-upload-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Neuen Export importieren</h3>
    <p style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        ZIP-Datei aus dem offiziellen Export von <strong>Claude.ai</strong> oder <strong>ChatGPT</strong> hochladen.
        Wird automatisch erkannt und anonymisiert (Mails, Telefon, IBAN, URLs, plus die Eigennamen aus Deiner
        <a href="/admin/prompt-insights?tab=whitelist" style="color:var(--thoxan-700);">Whitelist</a>).
    </p>
    <label class="pi-upload-zone" id="pi-drop">
        <span class="material-symbols-rounded">upload_file</span>
        <p><strong>ZIP-Datei wählen</strong> oder hier hineinziehen</p>
        <input type="file" id="pi-file" accept=".zip" style="display:none;">
    </label>
    <div id="pi-upload-status" style="margin-top:var(--d-section-gap);font-size:var(--d-fs-sm);"></div>
</div>

<h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Bisherige Importe</h3>
<table class="pi-import-table">
    <thead><tr>
        <th>Datei</th><th>Quelle</th><th>Importiert</th>
        <th style="text-align:right;">Chats</th><th style="text-align:right;">Nachrichten</th>
        <th>Status</th><th style="width:60px;"></th>
    </tr></thead>
    <tbody id="pi-imports-tbody">
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</td></tr>
    </tbody>
</table>

<script>
'use strict';
async function piLoadImports() {
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/imports');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piRenderImports(j.data.imports);
    } catch (e) {
        document.getElementById('pi-imports-tbody').innerHTML =
            '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--rose-600);">' + e.message + '</td></tr>';
    }
}
function piRenderImports(imports) {
    const tbody = document.getElementById('pi-imports-tbody');
    if (!imports.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--slate-400);">Noch keine Importe.</td></tr>';
        return;
    }
    tbody.innerHTML = imports.map(i => {
        const srcCls = 'pi-source-' + (i.source || 'unknown');
        const stCls = 'pi-status-' + (i.status || 'processing');
        const d = new Date(i.imported_at).toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' });
        return `<tr>
            <td><strong>${piEsc(i.filename)}</strong>${i.error_message ? `<br><small style="color:var(--rose-600);">${piEsc(i.error_message)}</small>` : ''}</td>
            <td><span class="pi-source-badge ${srcCls}">${piEsc(i.source || 'unknown')}</span></td>
            <td style="font-size:var(--d-fs-xs);color:var(--slate-500);">${d}</td>
            <td style="text-align:right;font-family:ui-monospace,monospace;">${i.chat_count}</td>
            <td style="text-align:right;font-family:ui-monospace,monospace;">${i.message_count}</td>
            <td><span class="${stCls}">${piEsc(i.status)}</span></td>
            <td><button class="thx-btn thx-btn-small thx-btn-danger" onclick="piDeleteImport(${i.id})" title="Import inkl. aller Daten löschen">
                <span class="material-symbols-rounded" style="font-size:14px;">delete</span></button></td>
        </tr>`;
    }).join('');
}
function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function piUpload(file) {
    const status = document.getElementById('pi-upload-status');
    status.innerHTML = '<span style="color:var(--thoxan-700);">⏳ Lade hoch + verarbeite ' + piEsc(file.name) + ' …</span>';
    const fd = new FormData();
    fd.append('zip', file);
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/imports', {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken },
            body: fd,
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        status.innerHTML = '<span style="color:var(--emerald-700);">✓ ' + piEsc(j.message) + '</span>';
        App.showNotification(j.message, 'success');
        piLoadImports();
    } catch (e) {
        status.innerHTML = '<span style="color:var(--rose-700);">✗ ' + piEsc(e.message) + '</span>';
        App.showNotification(e.message, 'error');
    }
}

async function piDeleteImport(id) {
    if (!confirm('Diesen Import komplett löschen? Alle abhängigen Chats, Nachrichten, Embeddings, Cluster-Zuordnungen werden mitgelöscht. Spielregeln bleiben erhalten.')) return;
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/imports/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        App.showNotification('Import gelöscht', 'success');
        piLoadImports();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

// Drag&Drop + File-Input
(function() {
    const zone = document.getElementById('pi-drop');
    const input = document.getElementById('pi-file');
    zone.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { if (input.files[0]) piUpload(input.files[0]); });
    ['dragenter','dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-drag'); }));
    ['dragleave','drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('is-drag'); }));
    zone.addEventListener('drop', e => { if (e.dataTransfer.files[0]) piUpload(e.dataTransfer.files[0]); });
})();
piLoadImports();
</script>
