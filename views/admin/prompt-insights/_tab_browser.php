<?php /* Prompt-Insights — Prompt-Browser */ ?>
<style>
.pi-br-filter { display: flex; gap: var(--d-row-gap); flex-wrap: wrap; background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-card-pad); margin-bottom: var(--d-section-gap); }
.pi-br-filter input, .pi-br-filter select { padding: var(--d-row-pad-y) var(--d-control-pad-x); border: 1px solid var(--slate-200); border-radius: var(--d-control-radius); font-size: var(--d-control-fs); font-family: inherit; }
.pi-br-filter input[type=search] { min-width: 220px; flex: 1; }
.pi-br-table { width: 100%; border-collapse: collapse; font-size: var(--d-fs-sm); background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); overflow: hidden; }
.pi-br-table th { text-align: left; padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); color: var(--slate-500); font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; border-bottom: 2px solid var(--slate-200); }
.pi-br-table td { padding: var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom: 1px solid var(--slate-100); vertical-align: top; }
.pi-br-content { max-width: 600px; max-height: 80px; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; }
.pi-br-meta { font-size: var(--d-fs-xs); color: var(--slate-500); }
</style>

<div class="pi-br-filter">
    <input type="search" id="pi-br-search" placeholder="Volltext-Suche in anonymisiertem Inhalt…" oninput="piBrDebounce()">
    <select id="pi-br-source" onchange="piBrLoad()">
        <option value="">Alle Quellen</option>
        <option value="claude">Claude</option>
        <option value="chatgpt">ChatGPT</option>
    </select>
    <select id="pi-br-role" onchange="piBrLoad()">
        <option value="">Alle Rollen</option>
        <option value="user">Nur User</option>
        <option value="assistant">Nur Assistant</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);color:var(--slate-600);">
        <input type="checkbox" id="pi-br-initial" onchange="piBrLoad()"> Nur Initial-Prompts
    </label>
</div>

<div id="pi-br-stats" style="margin-bottom:var(--d-section-gap);font-size:var(--d-fs-xs);color:var(--slate-500);"></div>

<table class="pi-br-table">
    <thead><tr>
        <th>Inhalt (anonymisiert)</th><th style="width:140px;">Chat</th><th style="width:90px;">Quelle</th>
        <th style="width:80px;">Rolle</th><th style="width:50px;text-align:right;">Wörter</th><th style="width:120px;">Zeitpunkt</th>
    </tr></thead>
    <tbody id="pi-br-tbody"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</td></tr></tbody>
</table>

<script>
'use strict';
let piBrTimer = null;
function piBrDebounce() { clearTimeout(piBrTimer); piBrTimer = setTimeout(piBrLoad, 300); }

async function piBrLoad() {
    const tbody = document.getElementById('pi-br-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--slate-400);">Lädt…</td></tr>';
    const params = new URLSearchParams();
    const s = document.getElementById('pi-br-search').value.trim();
    if (s) params.set('search', s);
    const src = document.getElementById('pi-br-source').value;
    if (src) params.set('source', src);
    const role = document.getElementById('pi-br-role').value;
    if (role) params.set('role', role);
    if (document.getElementById('pi-br-initial').checked) params.set('initial_only', '1');
    params.set('limit', '100');
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/browse?' + params.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piBrRender(j.data);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:var(--rose-600);text-align:center;padding:30px;">' + e.message + '</td></tr>';
    }
}

function piBrRender(d) {
    document.getElementById('pi-br-stats').textContent = `${d.messages.length} von ${d.total} Treffer angezeigt`;
    const tbody = document.getElementById('pi-br-tbody');
    if (!d.messages.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--slate-400);">Keine Treffer.</td></tr>';
        return;
    }
    tbody.innerHTML = d.messages.map(m => {
        const d2 = m.sent_at ? new Date(m.sent_at).toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' }) : '—';
        return `<tr>
            <td>
                <div class="pi-br-content">${piEsc(String(m.content_anon || '').substring(0, 400))}</div>
                ${m.has_attachment ? '<div class="pi-br-meta">📎 ' + piEsc(m.attachment_type || 'Anhang') + '</div>' : ''}
            </td>
            <td><div class="pi-br-content">${piEsc(m.chat_title || '—')}</div></td>
            <td><span class="pi-source-badge pi-source-${piEsc(m.source)}">${piEsc(m.source)}</span></td>
            <td>${piEsc(m.role)}${m.is_initial ? ' <span style="background:var(--thoxan-100);color:var(--thoxan-700);font-size:var(--d-fs-xs);padding:0 4px;border-radius:999px;font-weight:700;">init</span>' : ''}</td>
            <td style="text-align:right;font-family:ui-monospace,monospace;">${m.word_count}</td>
            <td style="font-size:var(--d-fs-xs);color:var(--slate-500);">${d2}</td>
        </tr>`;
    }).join('');
}
function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
piBrLoad();
</script>
<style>
.pi-source-badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: var(--d-fs-xs); font-weight: 700; text-transform: uppercase; }
.pi-source-claude  { background: rgba(168, 85, 247, 0.12); color: #7e22ce; }
.pi-source-chatgpt { background: var(--emerald-50); color: var(--emerald-700); }
.pi-source-unknown { background: var(--slate-100); color: var(--slate-600); }
</style>
