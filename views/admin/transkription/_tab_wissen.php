<?php /* Transkription — Wissens-Integration */ ?>
<style>
.tr-w-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:var(--d-card-pad); margin-bottom:var(--d-section-gap); }
.tr-w-table { width:100%; border-collapse:collapse; font-size:var(--d-fs-sm); }
.tr-w-table th { text-align:left; padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); color:var(--slate-500); font-size:var(--d-fs-xs); text-transform:uppercase; border-bottom:1px solid var(--slate-200); }
.tr-w-table td { padding:var(--d-tbl-pad-y) var(--d-tbl-pad-x); border-bottom:1px solid var(--slate-100); }
.tr-w-linked { color:var(--emerald-700); font-weight:600; }
.tr-w-unlinked { color:var(--slate-500); }
</style>

<div class="tr-w-card">
    <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Transkripte ↔ Wissensdatenbank</h3>
    <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-sm);color:var(--slate-600);">
        Hier siehst Du, welche fertigen Transkripte schon in der Wissensdatenbank liegen (Volltext-Suche, RAG, Graph)
        und welche noch nicht. „Einspeisen" laesst die KI das Transkript chunkken, Embeddings erzeugen und Entities ableiten.
    </p>
    <table class="tr-w-table">
        <thead><tr>
            <th>Datei</th><th>Kunde</th><th>Dauer</th><th>Wissens-Doc</th><th style="width:140px;">Aktion</th>
        </tr></thead>
        <tbody id="tr-w-tbody">
            <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--slate-400);">Laedt …</td></tr>
        </tbody>
    </table>
</div>

<script>
'use strict';
(function() {
    function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtDur(sec) {
        if (!sec) return '–';
        const s = Math.round(sec), m = Math.floor(s/60);
        return m > 0 ? (m+'m'+(s%60)+'s') : (s+'s');
    }

    async function load() {
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs?status=done');
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            const rows = await Promise.all(j.data.jobs.map(async job => {
                const o = await fetch('/api/v1/admin/transkription/jobs/' + job.job_id + '/outputs').then(r => r.json()).catch(() => null);
                const linked = o && o.success ? o.data.outputs.find(x => x.knowledge_doc_id) : null;
                return { job, linkedDoc: linked ? linked.knowledge_doc_id : null };
            }));
            render(rows);
        } catch (e) {
            document.getElementById('tr-w-tbody').innerHTML =
                '<tr><td colspan="5" style="text-align:center;color:var(--rose-600);padding:24px;">' + trEsc(e.message) + '</td></tr>';
        }
    }

    function render(rows) {
        if (!rows.length) {
            document.getElementById('tr-w-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--slate-400);">Keine fertigen Transkripte.</td></tr>';
            return;
        }
        document.getElementById('tr-w-tbody').innerHTML = rows.map(({job, linkedDoc}) => `
            <tr>
                <td><strong>${trEsc(job.filename || '–')}</strong></td>
                <td>${trEsc(job.customer_name || '–')}</td>
                <td>${fmtDur(job.duration_sec)}</td>
                <td>${linkedDoc
                    ? `<span class="tr-w-linked">✓ Doc <a href="/wissen/${linkedDoc}" style="color:inherit;">#${linkedDoc}</a></span>`
                    : '<span class="tr-w-unlinked">— noch nicht eingespeist</span>'}</td>
                <td>
                    ${linkedDoc
                        ? `<a class="thx-btn thx-btn-secondary thx-btn-small" href="/wissen/${linkedDoc}">Oeffnen</a>`
                        : `<button class="thx-btn thx-btn-primary thx-btn-small" onclick="trWSend(${job.job_id})">Einspeisen</button>`}
                </td>
            </tr>
        `).join('');
    }

    window.trWSend = async function(jobId) {
        if (!confirm('Transkript ins Wissen einspeisen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + jobId + '/to-knowledge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({}),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
            load();
        } catch (e) {
            App.showNotification(e.message, 'error');
        }
    };

    load();
})();
</script>
