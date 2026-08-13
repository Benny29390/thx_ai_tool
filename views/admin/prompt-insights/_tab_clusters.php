<?php /* Prompt-Insights — Cluster-Ansicht */ ?>
<style>
.pi-cluster-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-card-pad); margin-bottom: var(--d-row-gap); cursor: pointer; transition: border-color 0.1s; }
.pi-cluster-card:hover { border-color: var(--thoxan-300); }
.pi-cluster-card.is-active { border-color: var(--thoxan-500); background: var(--thoxan-50); }
.pi-cluster-head { display: flex; align-items: center; gap: var(--d-section-gap); }
.pi-cluster-num { background: var(--thoxan-600); color: #fff; padding: 1px 8px; border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 700; font-family: ui-monospace, monospace; }
.pi-cluster-label { font-weight: 700; color: var(--slate-800); font-size: var(--d-fs-sm); flex: 1; }
.pi-cluster-terms { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; }
.pi-cluster-sample { background: var(--slate-50); border-radius: var(--d-control-radius); padding: var(--d-row-pad-y) var(--d-row-pad-x); margin-top: var(--d-row-gap); font-size: var(--d-fs-xs); color: var(--slate-700); }
.pi-cluster-sample-content { max-height: 60px; overflow: hidden; line-height: 1.4; }
</style>

<div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);margin-bottom:var(--d-section-gap);">
    <div style="display:flex;gap:var(--d-section-gap);align-items:center;flex-wrap:wrap;">
        <strong>Pipeline:</strong>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="piEmbed()">
            <span class="material-symbols-rounded" style="font-size:14px;">hub</span> Embeddings für neue Prompts
        </button>
        <label style="display:flex;align-items:center;gap:var(--d-row-gap);font-size:var(--d-fs-sm);">
            Threshold:
            <input type="number" id="pi-thresh" value="0.78" min="0.5" max="0.95" step="0.01" style="width:70px;padding:var(--d-row-pad-y) var(--d-control-pad-x);border:1px solid var(--slate-200);border-radius:var(--d-control-radius);font-size:var(--d-control-fs);">
            <span style="color:var(--slate-400);font-size:var(--d-fs-xs);">(0.5 locker — 0.95 eng)</span>
        </label>
        <button class="thx-btn thx-btn-primary thx-btn-small" onclick="piRecluster()">
            <span class="material-symbols-rounded" style="font-size:14px;">refresh</span> Re-Cluster
        </button>
        <span id="pi-cl-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:var(--d-section-gap);">
    <div id="pi-cl-list"><div style="color:var(--slate-400);text-align:center;padding:20px;">Lädt Cluster…</div></div>
    <div id="pi-cl-detail" style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);min-height:300px;color:var(--slate-400);text-align:center;display:flex;align-items:center;justify-content:center;">
        <span>Cluster links auswählen für Beispiel-Prompts.</span>
    </div>
</div>

<script>
'use strict';
async function piClLoad() {
    const list = document.getElementById('pi-cl-list');
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/clusters');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        if (!j.data.clusters.length) {
            list.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:20px;font-size:var(--d-fs-sm);">Noch keine Cluster. Erst „Embeddings" und dann „Re-Cluster" klicken.</div>';
            return;
        }
        list.innerHTML = j.data.clusters.map((c, i) => `
            <div class="pi-cluster-card" data-cid="${c.id}" onclick="piClSelect(${c.id})">
                <div class="pi-cluster-head">
                    <span class="pi-cluster-num">${c.message_count}</span>
                    <div style="flex:1;min-width:0;">
                        <div class="pi-cluster-label">${piEsc(c.label || 'Cluster ' + (i+1))}</div>
                        ${c.top_terms ? '<div class="pi-cluster-terms">' + piEsc(c.top_terms) + '</div>' : ''}
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        list.innerHTML = '<div style="color:var(--rose-600);text-align:center;padding:20px;">' + e.message + '</div>';
    }
}

async function piClSelect(id) {
    document.querySelectorAll('.pi-cluster-card').forEach(c => c.classList.toggle('is-active', parseInt(c.dataset.cid) === id));
    const det = document.getElementById('pi-cl-detail');
    det.style.display = 'block';
    det.style.alignItems = '';
    det.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:20px;">Lädt Samples…</div>';
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/clusters/' + id + '/samples');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        const samples = j.data.samples;
        if (!samples.length) {
            det.innerHTML = '<div style="color:var(--slate-400);text-align:center;padding:20px;">Keine Beispiele.</div>';
            return;
        }
        det.innerHTML = `
            <h3 style="margin:0 0 var(--d-section-gap);font-size:var(--d-fs-base);">${samples.length} Beispiel-Prompts (sortiert nach Cluster-Nähe)</h3>
            ${samples.map(s => `
                <div class="pi-cluster-sample">
                    <div style="display:flex;justify-content:space-between;gap:var(--d-row-gap);margin-bottom:2px;">
                        <strong style="color:var(--slate-700);">${piEsc(s.chat_title || '—')}</strong>
                        <span style="color:var(--slate-400);font-family:ui-monospace,monospace;">d=${parseFloat(s.distance).toFixed(3)}</span>
                    </div>
                    <div class="pi-cluster-sample-content">${piEsc(String(s.content_anon || '').substring(0, 400))}</div>
                </div>
            `).join('')}`;
    } catch (e) {
        det.innerHTML = '<div style="color:var(--rose-600);text-align:center;padding:20px;">' + e.message + '</div>';
    }
}

async function piEmbed() {
    const st = document.getElementById('pi-cl-status');
    st.textContent = '⏳ Erzeuge Embeddings (kann je nach Menge dauern) …';
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/embed', { method: 'POST', headers: { 'X-CSRF-Token': App.csrfToken } });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        st.textContent = '✓ ' + j.message;
        App.showNotification(j.message, 'success');
    } catch (e) { st.textContent = '✗ ' + e.message; App.showNotification(e.message, 'error'); }
}

async function piRecluster() {
    const st = document.getElementById('pi-cl-status');
    const threshold = parseFloat(document.getElementById('pi-thresh').value);
    st.textContent = '⏳ Re-Cluster (Threshold ' + threshold + ') …';
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/recluster', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
            body: JSON.stringify({ threshold }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        st.textContent = '✓ ' + j.message;
        App.showNotification(j.message, 'success');
        piClLoad();
    } catch (e) { st.textContent = '✗ ' + e.message; App.showNotification(e.message, 'error'); }
}

function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
piClLoad();
</script>
