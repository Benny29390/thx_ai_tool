<?php /* Prompt-Insights — Spielregel-Bibliothek */ ?>
<style>
.pi-lib-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-row-pad-y) var(--d-row-pad-x); margin-bottom: var(--d-row-gap); font-size: var(--d-fs-sm); }
.pi-lib-cluster-head { font-size: var(--d-fs-base); font-weight: 700; color: var(--slate-800); margin: var(--d-section-gap) 0 var(--d-row-gap); }
</style>

<div style="display:flex;gap:var(--d-section-gap);align-items:center;flex-wrap:wrap;margin-bottom:var(--d-section-gap);">
    <strong>Freigegebene Regeln:</strong>
    <span id="pi-lib-count" style="color:var(--slate-500);font-size:var(--d-fs-sm);">…</span>
    <span style="flex:1;"></span>
    <a href="/api/v1/admin/prompt-insights/rules/export?format=markdown" class="thx-btn thx-btn-secondary thx-btn-small" style="text-decoration:none;">
        <span class="material-symbols-rounded" style="font-size:14px;">download</span> Markdown
    </a>
    <a href="/api/v1/admin/prompt-insights/rules/export?format=json" class="thx-btn thx-btn-secondary thx-btn-small" style="text-decoration:none;">
        <span class="material-symbols-rounded" style="font-size:14px;">download</span> JSON
    </a>
</div>

<div id="pi-lib-body"><div style="color:var(--slate-400);text-align:center;padding:30px;">Lädt …</div></div>

<script>
'use strict';
async function piLibLoad() {
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/rules?status=freigegeben');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piLibRender(j.data.rules);
    } catch (e) {
        document.getElementById('pi-lib-body').innerHTML = '<div style="color:var(--rose-600);text-align:center;padding:30px;">' + e.message + '</div>';
    }
}
function piLibRender(rules) {
    document.getElementById('pi-lib-count').textContent = `${rules.length} aktiv`;
    if (!rules.length) {
        document.getElementById('pi-lib-body').innerHTML = '<div style="background:#fff;border:1px solid var(--slate-200);border-radius:var(--d-card-radius);padding:var(--d-card-pad);text-align:center;color:var(--slate-500);">Noch keine freigegebenen Regeln. Im <a href="/admin/prompt-insights?tab=rules" style="color:var(--thoxan-700);">Regel-Editor</a> Vorschläge freigeben oder eigene Regeln anlegen.</div>';
        return;
    }
    const byCluster = {};
    rules.forEach(r => {
        const key = r.cluster_label || '— Ohne Cluster —';
        if (!byCluster[key]) byCluster[key] = [];
        byCluster[key].push(r);
    });
    let html = '';
    Object.entries(byCluster).forEach(([cluster, list]) => {
        html += `<div class="pi-lib-cluster-head">${piEsc(cluster)} <small style="font-weight:400;color:var(--slate-500);">(${list.length} Regel${list.length !== 1 ? 'n' : ''})</small></div>`;
        list.forEach(r => {
            html += `<div class="pi-lib-card">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-400);float:right;">${r.source === 'auto' ? '🤖' : '✋'}</span>
                ${piEsc(r.text)}
            </div>`;
        });
    });
    document.getElementById('pi-lib-body').innerHTML = html;
}
function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
piLibLoad();
</script>
