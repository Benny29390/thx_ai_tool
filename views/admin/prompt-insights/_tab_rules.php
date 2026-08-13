<?php /* Prompt-Insights — Regel-Editor (Vorschläge + manuelle Regeln) */ ?>
<style>
.pi-rules-grid { display: grid; grid-template-columns: 320px 1fr; gap: var(--d-section-gap); }
.pi-rule-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-row-pad-y) var(--d-row-pad-x); margin-bottom: var(--d-row-gap); cursor: pointer; transition: border-color 0.1s; font-size: var(--d-fs-sm); }
.pi-rule-card:hover { border-color: var(--thoxan-300); }
.pi-rule-card.is-active { border-color: var(--thoxan-500); background: var(--thoxan-50); }
.pi-rule-status { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; }
.pi-rule-status.vorschlag { background: var(--amber-100); color: var(--amber-800); }
.pi-rule-status.freigegeben { background: var(--emerald-100); color: var(--emerald-800); }
.pi-rule-status.verworfen { background: var(--slate-200); color: var(--slate-600); }
.pi-rule-source.auto { color: var(--thoxan-700); }
.pi-rule-source.manuell { color: var(--emerald-700); }
</style>

<div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);margin-bottom:var(--d-section-gap);">
    <div style="display:flex;gap:var(--d-section-gap);align-items:center;flex-wrap:wrap;">
        <strong>Cluster auswählen für KI-Regelvorschlag:</strong>
        <select id="pi-r-cluster" style="padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);min-width:300px;">
            <option value="">— Cluster wählen —</option>
        </select>
        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="piRDerive()">
            <span class="material-symbols-rounded" style="font-size:14px;">auto_awesome</span> KI-Regeln ableiten
        </button>
        <span id="pi-r-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
    </div>
</div>

<div class="pi-rules-grid">
    <div>
        <div style="display:flex;gap:var(--d-row-gap);margin-bottom:var(--d-row-gap);">
            <button class="thx-btn thx-btn-small <?= 'thx-btn-' ?>secondary" data-filter="" onclick="piRFilter('', this)">Alle</button>
            <button class="thx-btn thx-btn-small thx-btn-secondary" data-filter="vorschlag" onclick="piRFilter('vorschlag', this)">Vorschläge</button>
            <button class="thx-btn thx-btn-small thx-btn-secondary" data-filter="freigegeben" onclick="piRFilter('freigegeben', this)">Freigegeben</button>
            <button class="thx-btn thx-btn-small thx-btn-secondary" data-filter="verworfen" onclick="piRFilter('verworfen', this)">Verworfen</button>
        </div>
        <div id="pi-r-list"><div style="color:var(--slate-400);text-align:center;padding:20px;font-size:var(--d-fs-sm);">Lädt Regeln …</div></div>
    </div>

    <div id="pi-r-detail" style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);min-height:300px;">
        <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Neue manuelle Regel</h3>
        <textarea id="pi-r-newtext" placeholder="Eigene Spielregel (Imperativ, kurz, konkret) — z.B. „Schreibe für Marketing-Texte immer mit Du-Ansprache, kein Sie."
            style="width:100%;min-height:80px;padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);font-family:inherit;resize:vertical;"></textarea>
        <div style="margin-top:var(--d-row-gap);display:flex;gap:var(--d-row-gap);align-items:center;">
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="piRCreate()">Regel hinzufügen (freigegeben)</button>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);">Manuelle Regeln sind direkt freigegeben.</span>
        </div>
    </div>
</div>

<script>
'use strict';
let piRCurrentFilter = '';
let piRClusters = [];

async function piRLoadClusters() {
    const r = await fetch('/api/v1/admin/prompt-insights/clusters');
    const j = await r.json();
    if (j.success) {
        piRClusters = j.data.clusters;
        document.getElementById('pi-r-cluster').innerHTML = '<option value="">— Cluster wählen —</option>' +
            piRClusters.map(c => `<option value="${c.id}">${piEsc(c.label)} (${c.message_count})</option>`).join('');
    }
}

async function piRLoad() {
    const list = document.getElementById('pi-r-list');
    list.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:20px;">Lädt …</div>';
    const params = new URLSearchParams();
    if (piRCurrentFilter) params.set('status', piRCurrentFilter);
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/rules?' + params.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piRRender(j.data.rules);
    } catch (e) {
        list.innerHTML = '<div style="color:var(--rose-600);text-align:center;padding:20px;">' + e.message + '</div>';
    }
}

function piRRender(rules) {
    const list = document.getElementById('pi-r-list');
    if (!rules.length) {
        list.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:20px;font-size:var(--d-fs-sm);">Keine Regeln.</div>';
        return;
    }
    list.innerHTML = rules.map(r => `
        <div class="pi-rule-card" data-rid="${r.id}" onclick="piRSelect(${r.id})">
            <div style="margin-bottom:var(--d-row-gap);">
                <span class="pi-rule-status ${r.status}">${r.status}</span>
                <span class="pi-rule-source ${r.source}" style="font-size:var(--d-fs-xs);font-weight:600;">${r.source === 'auto' ? '🤖 KI' : '✋ manuell'}</span>
                ${r.cluster_label ? '<span style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-left:6px;">· ' + piEsc(r.cluster_label) + '</span>' : ''}
            </div>
            <div style="line-height:1.4;">${piEsc(r.text)}</div>
        </div>
    `).join('');
}

function piRFilter(status, btn) {
    piRCurrentFilter = status;
    document.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('thx-btn-primary', b === btn));
    document.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('thx-btn-secondary', b !== btn));
    piRLoad();
}

async function piRSelect(id) {
    const r = await fetch('/api/v1/admin/prompt-insights/rules?status=');
    const j = await r.json();
    const rule = j.data.rules.find(x => x.id === id);
    if (!rule) return;
    document.querySelectorAll('.pi-rule-card').forEach(c => c.classList.toggle('is-active', parseInt(c.dataset.rid) === id));
    const det = document.getElementById('pi-r-detail');
    det.innerHTML = `
        <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Regel bearbeiten</h3>
        <textarea id="pi-r-edit-text" style="width:100%;min-height:120px;padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);font-family:inherit;resize:vertical;">${piEsc(rule.text)}</textarea>
        <div style="margin:var(--d-row-gap) 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
            Cluster: <strong>${piEsc(rule.cluster_label || '—')}</strong> · Quelle: <strong>${rule.source === 'auto' ? 'KI' : 'manuell'}</strong> · Status: <strong>${rule.status}</strong>
        </div>
        <div style="display:flex;gap:var(--d-row-gap);flex-wrap:wrap;">
            <button class="thx-btn thx-btn-success thx-btn-small" onclick="piRPut(${id}, 'freigegeben')">✓ Freigeben</button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="piRPut(${id}, 'vorschlag')">↺ Als Vorschlag</button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="piRPut(${id}, 'verworfen')">✗ Verwerfen</button>
            <button class="thx-btn thx-btn-primary thx-btn-small" onclick="piRSaveText(${id})">Text speichern</button>
            <button class="thx-btn thx-btn-danger thx-btn-small" onclick="piRDelete(${id})">🗑 Löschen</button>
        </div>
    `;
}

async function piRPut(id, status) {
    try {
        await fetch('/api/v1/admin/prompt-insights/rules/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ status }),
        });
        App.showNotification('Status: ' + status, 'success');
        piRLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function piRSaveText(id) {
    const text = document.getElementById('pi-r-edit-text').value.trim();
    if (text.length < 5) { App.showNotification('Text zu kurz', 'error'); return; }
    try {
        await fetch('/api/v1/admin/prompt-insights/rules/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ text }),
        });
        App.showNotification('Gespeichert', 'success');
        piRLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function piRDelete(id) {
    if (!confirm('Regel löschen?')) return;
    try {
        await fetch('/api/v1/admin/prompt-insights/rules/' + id, {
            method: 'DELETE', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        App.showNotification('Gelöscht', 'success');
        piRLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function piRCreate() {
    const text = document.getElementById('pi-r-newtext').value.trim();
    if (text.length < 5) { App.showNotification('Text zu kurz', 'error'); return; }
    try {
        await fetch('/api/v1/admin/prompt-insights/rules', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ text }),
        });
        document.getElementById('pi-r-newtext').value = '';
        App.showNotification('Regel hinzugefügt', 'success');
        piRLoad();
    } catch (e) { App.showNotification(e.message, 'error'); }
}

async function piRDerive() {
    const cid = parseInt(document.getElementById('pi-r-cluster').value);
    if (!cid) { App.showNotification('Cluster wählen', 'error'); return; }
    const st = document.getElementById('pi-r-status');
    st.textContent = '⏳ KI analysiert Cluster (kann ~10 Sek dauern) …';
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/rules/derive/' + cid, {
            method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        st.textContent = '✓ ' + j.message;
        App.showNotification(j.message, 'success');
        piRFilter('vorschlag', document.querySelector('[data-filter="vorschlag"]'));
    } catch (e) { st.textContent = '✗ ' + e.message; App.showNotification(e.message, 'error'); }
}

function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
piRLoadClusters();
piRLoad();
</script>
