<?php
/**
 * Site-Monitor-Stats-Modal als wiederverwendbares Partial.
 * Wird vom Site-Monitor (admin/site-monitor.php) und der Kunden-Master-Seite
 * (admin/customers.php) eingebunden — beide oeffnen dasselbe Modal beim Klick
 * auf den Status-Indikator eines Monitors.
 *
 * Public API:
 *   window.smOpenStats(monitorId, optionalLabel, optionalDays)
 *   window.smCloseStatsModal()
 *   window.smGetPreferredDays() — gewuenschter Zeitraum aus localStorage (Default 30)
 *
 * Backend: GET /api/v1/admin/site-monitor/{id}/stats?days=N
 */
?>
<div class="thx-modal-backdrop" id="sm-stats-modal" style="display:none;"
     onclick="if(event.target===this)smCloseStatsModal()">
    <div class="thx-modal" style="width:780px;max-width:96vw;">
        <div class="thx-modal-header" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <h3 class="thx-modal-title" id="sm-stats-title" style="flex:1;min-width:0;">Statistik</h3>
            <div id="sm-stats-range" style="display:flex;gap:4px;align-items:center;">
                <!-- Range-Chips werden per JS erzeugt, damit sie nur einmal global existieren -->
            </div>
            <button type="button" id="sm-stats-export" class="sm-range-chip" onclick="smExportPdf()"
                    title="Verfügbarkeits-Report als PDF (gewählter Zeitraum)"
                    style="display:inline-flex;gap:5px;align-items:center;">
                <span class="material-symbols-rounded" style="font-size:14px;">picture_as_pdf</span> PDF-Export
            </button>
            <button class="thx-modal-close" type="button" onclick="smCloseStatsModal()">&times;</button>
        </div>
        <div class="thx-modal-body" id="sm-stats-body" style="max-height:70vh;overflow-y:auto;"></div>
    </div>
</div>

<style>
.sm-range-chip {
    padding: 3px 9px; font-size: 11px; border: 1px solid var(--slate-200);
    background: #fff; color: var(--slate-600); border-radius: 5px; cursor: pointer;
    font-weight: 500; transition: all 0.12s;
}
.sm-range-chip:hover { border-color: var(--slate-400); color: var(--slate-800); }
.sm-range-chip.is-active {
    border-color: var(--thoxan-700); background: var(--thoxan-50);
    color: var(--thoxan-800); font-weight: 600;
}
</style>

<script>
(function () {
    // Erlaubte Zeitraeume (in Tagen) + Label
    const RANGES = [
        { d: 7,   label: '7 T' },
        { d: 30,  label: '30 T' },
        { d: 90,  label: 'Quartal' },
        { d: 365, label: 'Jahr' },
    ];
    const LS_KEY = 'thx_sm_stats_days';

    function smEscLocal(s) {
        const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML;
    }

    window.smGetPreferredDays = function () {
        try {
            const v = parseInt(localStorage.getItem(LS_KEY), 10);
            if (RANGES.some(r => r.d === v)) return v;
        } catch (_) {}
        return 30;
    };

    function setPreferredDays(d) {
        try { localStorage.setItem(LS_KEY, String(d)); } catch (_) {}
    }

    // Modal-State: zuletzt geoeffneter Monitor (fuer Range-Change-Reload)
    let smCurrentId = null;
    let smCurrentLabel = '';
    let smCurrentDays = 30;
    let smCurrentCustomerId = null;   // Kunde des aktuellen Monitors — für den PDF-Export

    function renderRangeChips() {
        const wrap = document.getElementById('sm-stats-range');
        if (!wrap) return;
        wrap.innerHTML = RANGES.map(r => `
            <button type="button" class="sm-range-chip ${r.d === smCurrentDays ? 'is-active' : ''}"
                    onclick="smChangeRange(${r.d})">${r.label}</button>
        `).join('');
    }

    window.smChangeRange = function (days) {
        if (!RANGES.some(r => r.d === days)) return;
        if (days === smCurrentDays) return;
        smCurrentDays = days;
        setPreferredDays(days);
        renderRangeChips();
        // Titel-Suffix mit aktuellem Zeitraum aktualisieren
        document.getElementById('sm-stats-title').textContent =
            (smCurrentLabel || 'Statistik') + ' — ' + rangeLabel(days);
        if (smCurrentId) {
            // Stats neu laden mit neuem Zeitraum
            loadAndRender(smCurrentId, smCurrentLabel, days);
        }
    };

    function rangeLabel(days) {
        const r = RANGES.find(x => x.d === days);
        return r ? (r.d === 365 ? '1 Jahr' : (r.d === 90 ? 'Quartal' : (r.d + ' Tage'))) : (days + ' Tage');
    }

    function smRenderStatsLocal(d, label) {
        const s = d.summary || {};
        const fmtDt = min => { if (min <= 0) return '–'; if (min < 60) return min + ' Min'; const h = Math.floor(min/60), r = min%60; return h + ' Std' + (r ? ' ' + r + ' Min' : ''); };
        const upClr = u => u >= 99 ? 'var(--emerald-700)' : (u >= 95 ? 'var(--amber-700)' : 'var(--rose-700)');
        const fmtDay = ds => { const x = new Date(ds + 'T00:00:00'); return isNaN(x) ? ds : x.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }); };
        const dr = d.daily_response || [];
        const maxMs = Math.max(1, ...dr.map(x => x.avg_ms || 0));
        const drChart = dr.length ? `
            <h4 style="margin:0 0 8px;color:var(--slate-700);font-size:var(--d-fs-base);">Antwortzeit pro Tag (Mittelwert)</h4>
            <div style="display:flex;align-items:flex-end;gap:2px;height:120px;padding:8px;background:var(--slate-50);border-radius:8px;overflow-x:auto;">
                ${dr.map(x => {
                    const h = Math.max(2, Math.round(((x.avg_ms || 0) / maxMs) * 104));
                    return `<div title="${fmtDay(x.d)}: ${x.avg_ms} ms (${(x.cnt || 0).toLocaleString('de-DE')} Checks)" style="flex:1 0 3px;height:${h}px;background:var(--thoxan-400);border-radius:2px 2px 0 0;"></div>`;
                }).join('')}
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--slate-400);margin:4px 2px 18px;">
                <span>${fmtDay(dr[0].d)}</span>
                <span>Ø ${s.avg_ms || 0} ms · max ${Math.round(maxMs)} ms</span>
                <span>${fmtDay(dr[dr.length - 1].d)}</span>
            </div>
        ` : '';
        document.getElementById('sm-stats-body').innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:18px;">
                <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
                    <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Checks</div>
                    <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;">${(s.checks||0).toLocaleString('de-DE')}</div>
                </div>
                <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
                    <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Uptime</div>
                    <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;color:${upClr(s.uptime||100)};">${s.uptime||100}%</div>
                </div>
                <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
                    <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Ausfälle</div>
                    <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;">${s.outages||0}</div>
                </div>
                <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
                    <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Ausfallzeit</div>
                    <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;">${fmtDt(s.downtime_min||0)}</div>
                </div>
                <div style="background:var(--slate-50);padding:12px;border-radius:8px;">
                    <div style="font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;">Response</div>
                    <div style="font-size:18px;font-weight:700;font-family:ui-monospace,monospace;">${s.avg_ms||0} ms</div>
                </div>
            </div>

            ${drChart}

            ${(d.urls || []).length > 1 ? `
                <h4 style="margin:0 0 10px;color:var(--slate-700);font-size:var(--d-fs-base);">Pro URL</h4>
                <table style="width:100%;font-size:var(--d-fs-xs);border-collapse:collapse;margin-bottom:18px;">
                    <thead><tr style="border-bottom:1px solid var(--slate-200);">
                        <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">URL</th>
                        <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Checks</th>
                        <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Uptime</th>
                        <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Avg ms</th>
                    </tr></thead>
                    <tbody>${d.urls.map(u => `<tr style="border-bottom:1px solid var(--slate-100);">
                        <td style="padding:6px 10px;word-break:break-all;">${smEscLocal(u.url)}</td>
                        <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${u.checks}</td>
                        <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;color:${upClr(u.uptime)};">${u.uptime}%</td>
                        <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${u.avg_ms}</td>
                    </tr>`).join('')}</tbody>
                </table>
            ` : ''}

            ${(d.incidents || []).length > 0 ? `
                <h4 style="margin:0 0 10px;color:var(--slate-700);font-size:var(--d-fs-base);">Letzte Ausfälle (${d.incidents.length})</h4>
                <table style="width:100%;font-size:var(--d-fs-xs);border-collapse:collapse;">
                    <thead><tr style="border-bottom:1px solid var(--slate-200);">
                        <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">Beginn</th>
                        <th style="text-align:left;padding:6px 10px;color:var(--slate-500);">Ende</th>
                        <th style="text-align:right;padding:6px 10px;color:var(--slate-500);">Dauer</th>
                    </tr></thead>
                    <tbody>${d.incidents.slice(0, 200).map(i => `<tr style="border-bottom:1px solid var(--slate-100);">
                        <td style="padding:6px 10px;">${new Date(i.started_at.replace(' ', 'T') + 'Z').toLocaleString('de-DE')}</td>
                        <td style="padding:6px 10px;color:${i.ended_at ? 'inherit' : 'var(--rose-600)'};">${i.ended_at ? new Date(i.ended_at.replace(' ', 'T') + 'Z').toLocaleString('de-DE') : 'läuft noch'}</td>
                        <td style="text-align:right;padding:6px 10px;font-family:ui-monospace,monospace;">${fmtDt(i.duration_minutes || 0)}</td>
                    </tr>`).join('')}
                    ${d.incidents.length > 200 ? `<tr><td colspan="3" style="padding:6px 10px;text-align:center;color:var(--slate-400);font-size:10px;">… ${d.incidents.length - 200} weitere Eintraege gekuerzt</td></tr>` : ''}
                    </tbody>
                </table>
            ` : '<div style="padding:20px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-xs);">Keine Ausfälle im Zeitraum.</div>'}
        `;
    }

    async function loadAndRender(id, label, days) {
        const body = document.getElementById('sm-stats-body');
        body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--slate-400);">Lädt…</div>';
        try {
            const r = await fetch('/api/v1/admin/site-monitor/' + id + '/stats?days=' + days);
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Fehler');
            smRenderStatsLocal(j.data, label);
        } catch (e) {
            body.innerHTML = '<div style="padding:20px;color:var(--rose-600);">' + (e.message || 'Fehler') + '</div>';
        }
    }

    window.smOpenStats = async function (id, label, days) {
        // Label + Kunde aus smState ableiten, falls verfuegbar
        smCurrentCustomerId = null;
        if (window.smState && Array.isArray(window.smState.monitors)) {
            const m = window.smState.monitors.find(x => x.id === id);
            if (m) {
                if (!label) label = m.label;
                smCurrentCustomerId = m.customer_id ? parseInt(m.customer_id, 10) : null;
            }
        }
        smCurrentId = id;
        smCurrentLabel = label || 'Statistik';
        smCurrentDays = (days && RANGES.some(r => r.d === days)) ? days : smGetPreferredDays();

        document.getElementById('sm-stats-title').textContent = smCurrentLabel + ' — ' + rangeLabel(smCurrentDays);
        document.getElementById('sm-stats-modal').style.display = 'flex';
        renderRangeChips();
        await loadAndRender(smCurrentId, smCurrentLabel, smCurrentDays);
    };

    window.smCloseStatsModal = function () {
        document.getElementById('sm-stats-modal').style.display = 'none';
    };

    // Report als druckfertige Seite oeffnen (neuer Tab, oeffnet den Druckdialog selbst).
    // Gehoert der Monitor einem Kunden -> Kunden-Report (alle Websites). Sonst -> nur diese Website.
    // Zeitraum = der aktuell im Modal gewaehlte (smCurrentDays), wie mit Thomas festgelegt.
    window.smExportPdf = function () {
        const p = smCurrentCustomerId
            ? 'customer_id=' + encodeURIComponent(smCurrentCustomerId)
            : 'monitor_id=' + encodeURIComponent(smCurrentId);
        window.open('/admin/site-monitor/report?' + p + '&days=' + encodeURIComponent(smCurrentDays) + '&print=1', '_blank');
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('sm-stats-modal')?.style.display === 'flex') {
            smCloseStatsModal();
        }
    });
})();
</script>
