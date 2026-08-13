/**
 * Site-Monitoring-Widget — exakte Render-Funktionen aus dem Steckbrief
 * (customer-steckbrief.php: smUpClr/smDotClr/smDailyRespChart/sbSmRenderFinal).
 * Identischer Code, im Portal read-only verwendet. Daten via /portal/monitor-stats.
 */
(function () {
    function esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function smUpClr(u) { return u >= 99 ? 'var(--emerald-700)' : (u >= 95 ? 'var(--amber-700)' : 'var(--rose-700)'); }
    function smDotClr(status) { return status === 'up' ? '#10b981' : (status === 'down' ? '#ef4444' : '#cbd5e1'); }

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
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--slate-400);margin:4px 2px 0;">
                <span>${fmtDay(dr[0].d)}</span>
                <span>Ø ${avgMs || 0} ms · max ${Math.round(maxMs)} ms</span>
                <span>${fmtDay(dr[dr.length - 1].d)}</span>
            </div>`;
    }

    function sbSmRenderFinal(s, monitors, dailyResp) {
        const stat = (label, val, clr) => `
            <div class="cs-stat" style="padding:7px 9px;min-width:0;flex:1;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:10px;text-align:center;">
                <div class="cs-stat-label" style="font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--slate-400);text-transform:uppercase;letter-spacing:.3px;">${label}</div>
                <div class="cs-stat-value" style="font-size:16px;font-weight:700;${clr ? 'color:' + clr : ''}">${val}</div>
            </div>`;

        const stripe = (daily) => (daily || []).map(d => {
            const clr = d.status === 'up' ? '#10b981' : (d.status === 'down' ? '#ef4444' : '#e2e8f0');
            const lbl = new Date(d.date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
            const stateLbl = d.status === 'up' ? 'online' : (d.status === 'down' ? 'offline' : 'kein Check');
            return `<span title="${lbl}: ${stateLbl}" style="display:inline-block;flex:1;height:14px;min-width:3px;background:${clr};border-radius:2px;"></span>`;
        }).join('');

        const blocks = monitors.map(m => `
            <div style="padding:6px 4px;border-radius:6px;">
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
            <div style="display:flex;flex-direction:column;gap:2px;">${blocks}</div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:9px;color:var(--slate-400);margin:5px 0 14px;">
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

    // Render-Einstieg fuer das Portal: holt Daten + zeichnet in den Container.
    window.pfRenderMonitor = async function (boxId, query) {
        const box = document.getElementById(boxId);
        if (!box) return;
        try {
            const r = await App.get('/portal/monitor-stats' + (query || ''));
            if (!r.success || !r.data || !(r.data.monitors || []).length) { box.innerHTML = '<p style="color:var(--slate-400);font-size:var(--d-fs-sm);">Keine Monitoring-Daten verfügbar.</p>'; return; }
            const d = r.data;
            box.innerHTML = sbSmRenderFinal(d.summary, d.monitors, d.daily_response);
        } catch (e) { box.innerHTML = '<p style="color:var(--slate-400);font-size:var(--d-fs-sm);">Monitoring konnte nicht geladen werden.</p>'; }
    };
})();
