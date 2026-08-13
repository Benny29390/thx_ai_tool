<?php /* Transkription — Korrektur-Dictionary */ ?>
<style>
.tr-c-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:var(--d-card-pad); margin-bottom:var(--d-section-gap); }
.tr-c-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
.tr-c-row > div { flex:1; min-width:160px; }
.tr-c-row label { font-size:var(--d-fs-xs);color:var(--slate-600);display:block;margin-bottom:4px; }
.tr-c-row .thx-input, .tr-c-row .thx-select { width:100%; }
.tr-c-table { width:100%; border-collapse:collapse; font-size:var(--d-fs-sm); }
.tr-c-table th { text-align:left; padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); color:var(--slate-500); font-size:var(--d-fs-xs); text-transform:uppercase; border-bottom:1px solid var(--slate-200); }
.tr-c-table td { padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom:1px solid var(--slate-100); }
.tr-c-scope-global { background:var(--thoxan-50); color:var(--thoxan-700); padding:1px 6px; border-radius:999px; font-size:var(--d-fs-xs); font-weight:600; }
.tr-c-scope-user   { background:var(--slate-100); color:var(--slate-600); padding:1px 6px; border-radius:999px; font-size:var(--d-fs-xs); font-weight:600; }
</style>

<div class="tr-c-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Neue Korrektur</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Beispiele: „Frika" → „FRYKA", „Toxan" → „Thoxan", „Benni" → „Benny". Wird im Editor per Klick auf
        „Korrekturen anwenden" angewendet (ganze Woerter, case-sensitiv).
    </p>
    <div class="tr-c-row">
        <div><label>Falsch (Original)</label><input class="thx-input" id="tr-c-orig" type="text" placeholder="z.B. Frika"></div>
        <div><label>Richtig (Korrektur)</label><input class="thx-input" id="tr-c-corr" type="text" placeholder="z.B. FRYKA"></div>
        <div style="max-width:200px;">
            <label>Gueltigkeit</label>
            <select class="thx-select" id="tr-c-scope">
                <option value="user">nur fuer mich</option>
                <?php if (\Core\Auth::isAdmin()): ?><option value="global">global (alle)</option><?php endif; ?>
            </select>
        </div>
        <button class="thx-btn thx-btn-primary" onclick="trCAdd()">Hinzufuegen</button>
    </div>
</div>

<div class="tr-c-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Bestehende Korrekturen</h3>
    <table class="tr-c-table">
        <thead><tr>
            <th>Original</th><th>Korrektur</th><th>Gueltigkeit</th><th style="width:60px;"></th>
        </tr></thead>
        <tbody id="tr-c-tbody">
            <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--slate-400);">Laedt …</td></tr>
        </tbody>
    </table>
</div>

<script>
'use strict';
(function() {
    function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    async function load() {
        const r = await fetch('/api/v1/admin/transkription/corrections');
        const j = await r.json();
        const tbody = document.getElementById('tr-c-tbody');
        if (!j.success) { tbody.innerHTML = '<tr><td colspan="4" style="color:var(--rose-600);text-align:center;padding:20px;">' + trEsc(j.message) + '</td></tr>'; return; }
        if (!j.data.corrections.length) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--slate-400);padding:20px;">Noch keine Korrekturen.</td></tr>'; return; }
        tbody.innerHTML = j.data.corrections.map(c => `
            <tr>
                <td><code>${trEsc(c.original)}</code></td>
                <td><strong>${trEsc(c.correction)}</strong></td>
                <td><span class="tr-c-scope-${c.scope}">${trEsc(c.scope)}</span></td>
                <td><button class="thx-btn thx-btn-small thx-btn-danger" onclick="trCDel(${c.id})" title="Loeschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button></td>
            </tr>
        `).join('');
    }

    window.trCAdd = async function() {
        const original = document.getElementById('tr-c-orig').value.trim();
        const correction = document.getElementById('tr-c-corr').value.trim();
        const scope = document.getElementById('tr-c-scope').value;
        if (!original || !correction) { App.showNotification('Beide Felder noetig', 'error'); return; }
        try {
            const r = await fetch('/api/v1/admin/transkription/corrections', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({ original, correction, scope }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            document.getElementById('tr-c-orig').value = '';
            document.getElementById('tr-c-corr').value = '';
            App.showNotification(j.message, 'success');
            load();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trCDel = async function(id) {
        if (!confirm('Diese Korrektur loeschen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/corrections/' + id, {
                method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            load();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    load();
})();
</script>
