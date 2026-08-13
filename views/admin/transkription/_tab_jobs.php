<?php /* Transkription — Jobs-Tab */ ?>
<style>
.tr-jobs-table { width:100%; border-collapse:collapse; font-size:var(--d-fs-sm); background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); overflow:hidden; }
.tr-jobs-table th { text-align:left; padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); color:var(--slate-500); font-size:var(--d-fs-xs); text-transform:uppercase; letter-spacing:0.04em; font-weight:600; border-bottom:2px solid var(--slate-200); background:var(--slate-50); }
.tr-jobs-table td { padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom:1px solid var(--slate-100); vertical-align:middle; }
.tr-jobs-table tr:last-child td { border-bottom:none; }
.tr-status-chip { display:inline-block; padding:2px var(--d-control-pad-x); border-radius:999px; font-size:var(--d-fs-xs); font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
.tr-status-queued    { background:var(--slate-100);   color:var(--slate-700); }
.tr-status-running   { background:var(--amber-50);    color:var(--amber-700); }
.tr-status-done      { background:var(--emerald-50);  color:var(--emerald-700); }
.tr-status-failed    { background:var(--rose-50);     color:var(--rose-700); }
.tr-status-cancelled { background:var(--slate-100);   color:var(--slate-500); }
.tr-progress { display:inline-block; width:80px; height:6px; border-radius:3px; background:var(--slate-200); overflow:hidden; vertical-align:middle; margin-left:8px; }
.tr-progress > span { display:block; height:100%; background:var(--thoxan-500); transition:width .3s; }
.tr-source-icon { font-size:18px; vertical-align:-3px; color:var(--slate-500); }
.tr-row-actions { white-space:nowrap; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--d-row-gap);">
    <h3 style="margin:0;font-size:var(--d-fs-base);">Transkriptions-Jobs</h3>
    <div style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:var(--d-fs-xs);color:var(--slate-600);">Status:</label>
        <select class="thx-select" id="tr-filter-status" onchange="trLoadJobs()">
            <option value="">alle</option>
            <option value="queued">eingereiht</option>
            <option value="running">laeuft</option>
            <option value="done">fertig</option>
            <option value="failed">fehlgeschlagen</option>
        </select>
        <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="trLoadJobs()" title="Aktualisieren">
            <span class="material-symbols-rounded" style="font-size:16px;">refresh</span>
        </button>
    </div>
</div>

<table class="tr-jobs-table">
    <thead><tr>
        <th style="width:30px;"></th>
        <th>Datei</th>
        <th>Kunde</th>
        <th>Modell</th>
        <th>Dauer</th>
        <th>Sprecher</th>
        <th>Status</th>
        <th>Erstellt</th>
        <th style="width:120px;">Aktionen</th>
    </tr></thead>
    <tbody id="tr-jobs-tbody">
        <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--slate-400);">Laedt …</td></tr>
    </tbody>
</table>

<script>
'use strict';
(function() {
    let pollHandle = null;

    function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // Lange yt-dlp/Whisper-Errors auf einen kurzen, freundlichen Klartext mappen.
    function friendlyReason(note) {
        if (!note) return '';
        const n = String(note);
        if (/Requested format is not available|not yet available|still processing|GraphQL.*loom/i.test(n))
            return 'Loom transcodet das Video noch';
        if (/HTTP Error 5\d\d/i.test(n))           return 'Loom-Server gerade nicht erreichbar';
        if (/Unable to download/i.test(n))          return 'Loom-Download fehlgeschlagen';
        if (/ffmpeg/i.test(n))                      return 'Audio-Konvertierung schlug fehl';
        if (/Whisper|whisper-runner/i.test(n))      return 'Whisper-Fehler';
        // Default: erste Zeile, max 80 Zeichen
        return n.split('|')[0].split('\n')[0].trim().slice(0, 80);
    }
    function fmtDur(sec) {
        if (!sec || sec <= 0) return '–';
        const s = Math.round(sec);
        const m = Math.floor(s / 60), r = s % 60;
        return m > 0 ? (m + ':' + String(r).padStart(2, '0')) : (r + 's');
    }
    function fmtSize(bytes) {
        if (!bytes) return '–';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024*1024) return Math.round(bytes/1024) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }
    function fmtDate(s) {
        if (!s) return '–';
        return new Date(s).toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' });
    }

    window.trLoadJobs = async function() {
        const status = document.getElementById('tr-filter-status').value;
        const url = '/api/v1/admin/transkription/jobs' + (status ? ('?status=' + status) : '');
        try {
            const r = await fetch(url);
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            trRenderJobs(j.data.jobs);

            // Polling nur wenn aktive Jobs vorhanden
            const anyActive = j.data.jobs.some(x => x.status === 'queued' || x.status === 'running');
            if (pollHandle) { clearTimeout(pollHandle); pollHandle = null; }
            if (anyActive) {
                pollHandle = setTimeout(trLoadJobs, 5000);
            }
        } catch (e) {
            document.getElementById('tr-jobs-tbody').innerHTML =
                '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--rose-600);">' + trEsc(e.message) + '</td></tr>';
        }
    };

    function trRenderJobs(jobs) {
        const tbody = document.getElementById('tr-jobs-tbody');
        if (!jobs.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--slate-400);">Noch keine Jobs. <a href="/admin/transkription?tab=upload" style="color:var(--thoxan-700);">Datei hochladen →</a></td></tr>';
            return;
        }
        tbody.innerHTML = jobs.map(j => {
            const stCls = 'tr-status-' + j.status;
            const stLabel = {
                queued: 'eingereiht', running: 'laeuft', done: 'fertig',
                failed: 'fehlgeschlagen', cancelled: 'abgebrochen'
            }[j.status] || j.status;
            const srcIcon = j.source === 'loom' ? 'movie' : 'graphic_eq';
            let progress = '';
            if (j.status === 'running') {
                progress = '<span class="tr-progress"><span style="width:' + (j.progress_pct || 0) + '%"></span></span>';
                if (j.progress_note) {
                    progress += '<div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;">' + trEsc(j.progress_note) + '</div>';
                } else if (j.started_at) {
                    const startMs = new Date(j.started_at).getTime();
                    const elapsedSec = Math.max(0, Math.round((Date.now() - startMs) / 1000));
                    const m = Math.floor(elapsedSec/60), s = elapsedSec%60;
                    progress += '<div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;">laeuft seit ' + m + ':' + String(s).padStart(2,'0') + '</div>';
                }
            } else if (j.status === 'queued') {
                if (j.next_retry_at) {
                    const retryMs = new Date(j.next_retry_at).getTime();
                    const inSec = Math.max(0, Math.round((retryMs - Date.now()) / 1000));
                    const m = Math.floor(inSec/60), s = inSec%60;
                    const inFmt = m > 0 ? (m + ' Min') : (s + ' s');
                    const reason = friendlyReason(j.progress_note);
                    progress = `
                        <div style="font-size:var(--d-fs-xs);color:var(--amber-700);margin-top:2px;">
                            <strong>Wiederholung ${j.retry_count||0}/6</strong> in ${inFmt}
                        </div>
                        ${reason ? `<div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:1px;">${trEsc(reason)}</div>` : ''}`;
                } else {
                    progress = '<div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;">wartet auf Worker (Cron alle 2 Min)</div>';
                }
            }
            const errLine = j.error_message
                ? '<br><small style="color:var(--rose-600);">' + trEsc(friendlyReason(j.error_message)) + '</small>'
                : '';
            const dur = j.duration_sec || (j.result_id ? j.duration_sec : 0);

            let actions = '';
            if (j.status === 'done' && j.result_id) {
                actions += `<a class="thx-btn thx-btn-small thx-btn-secondary" href="/admin/transkription?tab=editor&job=${j.job_id}" title="Im Editor oeffnen"><span class="material-symbols-rounded" style="font-size:14px;">edit_note</span></a> `;
            }
            if (j.status === 'failed') {
                actions += `<button class="thx-btn thx-btn-small thx-btn-primary" onclick="trRetryJob(${j.job_id})" title="Erneut versuchen"><span class="material-symbols-rounded" style="font-size:14px;">refresh</span></button> `;
            }
            actions += `<button class="thx-btn thx-btn-small thx-btn-danger" onclick="trDeleteJob(${j.job_id})" title="Job + Quelle loeschen"><span class="material-symbols-rounded" style="font-size:14px;">delete</span></button>`;

            return `<tr>
                <td><span class="material-symbols-rounded tr-source-icon" title="${j.source}">${srcIcon}</span></td>
                <td><strong>${trEsc(j.title || (j.filename || '–').replace(/\.[a-z0-9]+$/i, ''))}</strong> <small style="color:var(--slate-500);">${fmtSize(j.filesize)}</small>${errLine}</td>
                <td>${trEsc(j.customer_name || '–')}</td>
                <td><code style="font-size:var(--d-fs-xs);background:var(--slate-100);padding:1px 4px;border-radius:3px;">${trEsc(j.model)}</code></td>
                <td>${fmtDur(dur)}</td>
                <td>${j.speaker_count ?? '–'}${j.language_detected ? ' <small style="color:var(--slate-500);">' + trEsc(j.language_detected) + '</small>' : ''}</td>
                <td><span class="tr-status-chip ${stCls}">${trEsc(stLabel)}</span>${progress}</td>
                <td style="font-size:var(--d-fs-xs);color:var(--slate-500);">${fmtDate(j.created_at)}</td>
                <td class="tr-row-actions">${actions}</td>
            </tr>`;
        }).join('');
    }

    window.trRetryJob = async function(id) {
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + id + '/retry', {
                method: 'POST',
                headers: { 'X-CSRF-Token': App.csrfToken },
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
            trLoadJobs();
        } catch (e) {
            App.showNotification(e.message, 'error');
        }
    };

    window.trDeleteJob = async function(id) {
        if (!confirm('Diesen Job inkl. Quelldatei und Transkript loeschen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': App.csrfToken },
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification('Job geloescht', 'success');
            trLoadJobs();
        } catch (e) {
            App.showNotification(e.message, 'error');
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') trLoadJobs();
    });

    trLoadJobs();
})();
</script>
