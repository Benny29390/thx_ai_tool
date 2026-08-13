<?php /* Prompt-Insights — Statistik (Layer 2) */ ?>
<style>
.pi-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--d-section-gap); margin-bottom: var(--d-section-gap); }
.pi-kpi { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-card-pad); }
.pi-kpi-label { font-size: var(--d-fs-xs); text-transform: uppercase; letter-spacing: 0.04em; color: var(--slate-500); font-weight: 600; }
.pi-kpi-value { font-size: var(--d-fs-2xl); font-weight: 800; color: var(--slate-800); font-family: ui-monospace, monospace; margin-top: var(--d-row-gap); }
.pi-kpi-sub { font-size: var(--d-fs-xs); color: var(--slate-400); margin-top: 2px; }

.pi-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--d-card-radius); padding: var(--d-card-pad); margin-bottom: var(--d-section-gap); }
.pi-card h3 { margin: 0 0 var(--d-row-gap); font-size: var(--d-fs-base); color: var(--slate-700); }

.pi-heatmap { display: grid; grid-template-columns: 50px repeat(24, 1fr); gap: 1px; font-size: var(--d-fs-xs); }
.pi-heatmap .hd { color: var(--slate-500); text-align: center; padding: 2px; }
.pi-heatmap .row-label { text-align: right; padding: 2px 6px; color: var(--slate-600); font-weight: 600; }
.pi-heatmap .cell { padding: 4px 2px; text-align: center; font-family: ui-monospace, monospace; }

.pi-bar-list { display: flex; flex-direction: column; gap: var(--d-row-gap); }
.pi-bar-row { display: grid; grid-template-columns: 140px 1fr 40px; gap: var(--d-row-gap); align-items: center; font-size: var(--d-fs-sm); }
.pi-bar-bg { background: var(--slate-100); border-radius: var(--d-control-radius); height: 14px; overflow: hidden; }
.pi-bar-fill { background: var(--thoxan-500); height: 100%; transition: width 0.3s; }
.pi-bar-num { text-align: right; font-family: ui-monospace, monospace; color: var(--slate-600); font-weight: 600; }
</style>

<div id="pi-stats-body"><div class="pi-card" style="text-align:center;color:var(--slate-400);">Lädt…</div></div>

<script>
'use strict';
async function piStatsLoad() {
    const body = document.getElementById('pi-stats-body');
    try {
        const r = await fetch('/api/v1/admin/prompt-insights/stats');
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        piStatsRender(j.data);
    } catch (e) {
        body.innerHTML = '<div class="pi-card" style="color:var(--rose-600);text-align:center;">' + e.message + '</div>';
    }
}

function piStatsRender(d) {
    const t = d.totals;
    if (t.messages === 0) {
        document.getElementById('pi-stats-body').innerHTML = '<div class="pi-card" style="text-align:center;color:var(--slate-500);">Noch keine importierten Chats. Im <a href="/admin/prompt-insights?tab=imports" style="color:var(--thoxan-700);">Importe-Tab</a> ein ZIP hochladen.</div>';
        return;
    }
    const wc = d.word_count;
    const ac = d.avg_chat;
    const sources = d.by_source.map(s => `${s.source}: ${s.chats} Chats / ${s.messages} Msg`).join('  ·  ');
    const attachPct = t.user_messages > 0 ? Math.round(t.with_attachment / t.user_messages * 100) : 0;

    let html = `
        <div class="pi-kpi-grid">
            <div class="pi-kpi"><div class="pi-kpi-label">Importe</div><div class="pi-kpi-value">${t.imports}</div></div>
            <div class="pi-kpi"><div class="pi-kpi-label">Chats</div><div class="pi-kpi-value">${t.chats}</div></div>
            <div class="pi-kpi"><div class="pi-kpi-label">Nachrichten</div><div class="pi-kpi-value">${t.messages}</div><div class="pi-kpi-sub">davon User: ${t.user_messages}</div></div>
            <div class="pi-kpi"><div class="pi-kpi-label">Initial-Prompts</div><div class="pi-kpi-value">${t.initial_prompts}</div></div>
            <div class="pi-kpi"><div class="pi-kpi-label">Mit Anhang</div><div class="pi-kpi-value">${t.with_attachment}</div><div class="pi-kpi-sub">${attachPct}% der User-Msg</div></div>
        </div>

        <div class="pi-card">
            <h3>Prompt-Länge (Wortzahl der User-Nachrichten)</h3>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:var(--d-section-gap);">
                <div><div class="pi-kpi-label">P25</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--thoxan-700);font-family:ui-monospace,monospace;">${wc.p25}</div></div>
                <div><div class="pi-kpi-label">Median</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--thoxan-700);font-family:ui-monospace,monospace;">${wc.median}</div></div>
                <div><div class="pi-kpi-label">P75</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--thoxan-700);font-family:ui-monospace,monospace;">${wc.p75}</div></div>
                <div><div class="pi-kpi-label">Max</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--rose-700);font-family:ui-monospace,monospace;">${wc.max}</div></div>
            </div>
            <p style="margin:var(--d-section-gap) 0 0;font-size:var(--d-fs-xs);color:var(--slate-500);">${wc.count} Werte ausgewertet.</p>
        </div>

        <div class="pi-card">
            <h3>Iterationen pro Chat (User-Prompts)</h3>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--d-section-gap);">
                <div><div class="pi-kpi-label">Ø Initial</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--slate-700);font-family:ui-monospace,monospace;">${ac.initial}</div></div>
                <div><div class="pi-kpi-label">Ø Folgeprompts</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--amber-700);font-family:ui-monospace,monospace;">${ac.followup}</div></div>
                <div><div class="pi-kpi-label">Ø Gesamt</div><div style="font-size:var(--d-fs-lg);font-weight:700;color:var(--emerald-700);font-family:ui-monospace,monospace;">${ac.total}</div></div>
            </div>
        </div>

        <div class="pi-card">
            <h3>Top-Eröffnungs-Wörter</h3>`;
    if (!d.top_verbs.length) {
        html += '<p style="color:var(--slate-400);font-size:var(--d-fs-sm);">Keine Initial-Prompts gefunden.</p>';
    } else {
        const maxC = Math.max(...d.top_verbs.map(v => v.count));
        html += '<div class="pi-bar-list">';
        d.top_verbs.forEach(v => {
            html += `<div class="pi-bar-row">
                <div>${piEsc(v.term)}</div>
                <div class="pi-bar-bg"><div class="pi-bar-fill" style="width:${Math.round(v.count / maxC * 100)}%"></div></div>
                <div class="pi-bar-num">${v.count}</div>
            </div>`;
        });
        html += '</div>';
    }
    html += `</div>

        <div class="pi-card">
            <h3>Quellen-Verteilung</h3>
            <p style="font-size:var(--d-fs-sm);color:var(--slate-700);">${piEsc(sources) || '—'}</p>
        </div>

        <div class="pi-card">
            <h3>Zeitliche Verteilung (Wochentag × Stunde)</h3>
            <div class="pi-heatmap">
                <div></div>${Array.from({length: 24}, (_, i) => `<div class="hd">${i}</div>`).join('')}
                ${[1,2,3,4,5,6,7].map(w => {
                    const days = ['Mo','Di','Mi','Do','Fr','Sa','So'];
                    let row = `<div class="row-label">${days[w-1]}</div>`;
                    const maxRow = Math.max(...d.heatmap[w]);
                    for (let h = 0; h < 24; h++) {
                        const v = d.heatmap[w][h];
                        const intensity = maxRow > 0 ? Math.round(v / maxRow * 100) : 0;
                        const bg = v === 0 ? 'var(--slate-50)' : `rgba(0,109,184,${0.15 + intensity / 100 * 0.6})`;
                        const fg = intensity > 50 ? '#fff' : 'var(--slate-700)';
                        row += `<div class="cell" style="background:${bg};color:${fg};">${v || ''}</div>`;
                    }
                    return row;
                }).join('')}
            </div>
        </div>
    `;
    document.getElementById('pi-stats-body').innerHTML = html;
}
function piEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
piStatsLoad();
</script>
