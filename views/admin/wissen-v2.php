<div class="page-header">
    <div>
        <h1>Wissens-Status</h1>
        <p class="text-muted" style="margin:.25rem 0 0;max-width:70ch;">
            Status der Wissensdatenbank. Embedding läuft lokal über <code>ki.thoxan.com</code> mit bge-m3 (1024 Dim),
            Vektor-Storage in Qdrant. Neue/geänderte Dokumente werden vom Worker automatisch nachgezogen.
        </p>
    </div>
    <div><button class="thx-btn thx-btn-secondary" onclick="wv2LoadStatus()">Status aktualisieren</button></div>
</div>

<div id="wv2-status-cards" class="stats-grid mb-lg">
    <div class="stat-card"><div class="stat-content"><span class="stat-value" id="wv2-qdrant">…</span><span class="stat-label">Qdrant</span></div></div>
    <div class="stat-card"><div class="stat-content"><span class="stat-value" id="wv2-points">…</span><span class="stat-label">Vektoren in Qdrant</span></div></div>
    <div class="stat-card"><div class="stat-content"><span class="stat-value" id="wv2-progress">…</span><span class="stat-label">Backfill (synced / gesamt)</span></div></div>
    <div class="stat-card"><div class="stat-content"><span class="stat-value" id="wv2-jobs">…</span><span class="stat-label">Offene Sync-Jobs</span></div></div>
</div>

<div class="card mb-lg">
    <div id="wv2-meta" class="text-muted" style="font-size:var(--d-fs-sm,.9rem);">Lade Status…</div>
    <div id="wv2-backfill-hint" style="display:none;margin-top:.75rem;padding:.75rem 1rem;background:var(--amber-50,#fffbeb);border:1px solid var(--amber-200,#fde68a);border-radius:8px;">
        <strong>Backfill noch nicht vollständig.</strong> Initialer Aufbau per CLI (läuft auch resumabel):
        <pre style="margin:.5rem 0 0;white-space:pre-wrap;">php /var/www/cli/qdrant-backfill.php</pre>
        Neue/geänderte Dokumente werden danach automatisch über die Worker-Queue nachgezogen.
    </div>
</div>

<!-- ===== Projektplan-Sync Health ===== -->
<div class="card mb-lg">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
            <h3 class="card-title" style="margin:0;">Projektplan → Wissensdatenbank</h3>
            <p class="text-muted" style="margin:.25rem 0 0;font-size:var(--d-fs-xs);">
                Automatischer Sync per Cron. Pläne ohne Kunde und Entwürfe werden geskippt.
            </p>
        </div>
        <div style="display:flex;gap:6px;">
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppksLoadStatus()">Aktualisieren</button>
            <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppksSyncAll()" id="ppks-syncall-btn">
                <span class="material-symbols-rounded" style="font-size:14px;">cloud_sync</span>
                Alle markieren
            </button>
        </div>
    </div>
    <div id="ppks-stats" class="stats-grid" style="margin-top:1rem;">
        <div class="stat-card"><div class="stat-content"><span class="stat-value" id="ppks-synced">…</span><span class="stat-label">Synced</span></div></div>
        <div class="stat-card"><div class="stat-content"><span class="stat-value" id="ppks-dirty">…</span><span class="stat-label">Ausstehend (dirty)</span></div></div>
        <div class="stat-card"><div class="stat-content"><span class="stat-value" id="ppks-skipped">…</span><span class="stat-label">Geskippt (kein Kunde/Entwurf)</span></div></div>
        <div class="stat-card"><div class="stat-content"><span class="stat-value" id="ppks-last">…</span><span class="stat-label">Letzter Sync</span></div></div>
    </div>
    <div style="margin-top:1rem;">
        <h4 style="margin:0 0 .5rem;font-size:var(--d-fs-sm);">Pläne in der Wissensdatenbank</h4>
        <div id="ppks-list" style="max-height:300px;overflow:auto;border:1px solid var(--slate-200);border-radius:6px;"></div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Test-Suche</h3>
    <p class="text-muted" style="font-size:.85rem;margin:.25rem 0 .75rem;">
        Diagnostische Suche direkt gegen die Wissensdatenbank — gleiche Trefferquelle wie Chat und <a href="/wissen">Wissens-Seite</a>.
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem;">
        <div style="flex:1;min-width:240px;">
            <label style="display:block;font-size:.85rem;color:var(--slate-600);margin-bottom:4px;">Suchanfrage</label>
            <input type="text" id="wv2-q" class="thx-input" placeholder="z.B. Was ist FRYKA?" style="width:100%;"
                   onkeydown="if(event.key==='Enter')wv2Search()">
        </div>
        <div style="width:160px;">
            <label style="display:block;font-size:.85rem;color:var(--slate-600);margin-bottom:4px;">Kunde-ID (optional)</label>
            <input type="number" id="wv2-cust" class="thx-input" placeholder="leer = alle" style="width:100%;">
        </div>
        <button class="thx-btn thx-btn-primary" onclick="wv2Search()">Suchen</button>
    </div>
    <div id="wv2-search-status" class="text-muted" style="font-size:.85rem;margin-bottom:.5rem;"></div>
    <div><h4 id="wv2-v2-label" style="margin:0 0 .5rem;">Ergebnisse</h4><div id="wv2-v2"></div></div>
</div>

<script>
'use strict';
(function () {
    function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    window.wv2LoadStatus = async function () {
        try {
            const r = await fetch('/api/v1/admin/wissen-v2-status');
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Fehler');
            const d = j.data;
            const q = d.qdrant, b = d.backfill, e = d.embedding;

            document.getElementById('wv2-qdrant').textContent = q.reachable ? 'erreichbar' : 'offline';
            document.getElementById('wv2-qdrant').style.color = q.reachable ? 'var(--emerald-600,#059669)' : 'var(--rose-600,#e11d48)';
            document.getElementById('wv2-points').textContent = (q.points ?? '–').toLocaleString('de-DE');
            document.getElementById('wv2-progress').textContent =
                (b.synced).toLocaleString('de-DE') + ' / ' + (b.total_chunks).toLocaleString('de-DE');
            document.getElementById('wv2-jobs').textContent = d.sync_jobs_open.toLocaleString('de-DE');

            const pct = b.total_chunks > 0 ? Math.round(b.synced * 100 / b.total_chunks) : 0;
            document.getElementById('wv2-meta').innerHTML =
                'Embedding: <code>' + esc(e.model) + '</code> (' + e.dim + ' Dim), Key ' + (e.key_set ? 'gesetzt' : '<strong style="color:var(--rose-600,#e11d48)">fehlt</strong>') +
                ' · Qdrant: <code>' + esc(q.url) + '</code> → Collection <code>' + esc(q.collection) + '</code>' +
                ' · Backfill: <strong>' + pct + '%</strong>' + (b.error > 0 ? ' · <span style="color:var(--rose-600,#e11d48)">' + b.error + ' Fehler</span>' : '');

            const hint = document.getElementById('wv2-backfill-hint');
            hint.style.display = (b.synced < b.total_chunks) ? 'block' : 'none';
        } catch (err) {
            document.getElementById('wv2-meta').innerHTML = '<span style="color:var(--rose-600,#e11d48)">Status-Fehler: ' + esc(err.message) + '</span>';
        }
    };

    function renderResults(box, data) {
        if (data.error) { box.innerHTML = '<div style="color:var(--rose-600,#e11d48);font-size:.85rem;">Fehler: ' + esc(data.error) + '</div>'; return; }
        if (!data.results.length) { box.innerHTML = '<div class="text-muted" style="font-size:.85rem;">Keine Treffer.</div>'; return; }
        box.innerHTML = data.results.map((c, i) => `
            <div style="padding:.5rem .6rem;border:1px solid var(--slate-100,#f1f5f9);border-radius:6px;margin-bottom:.4rem;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong style="font-size:.85rem;">${i + 1}. ${esc(c.title) || '(ohne Titel)'}</strong>
                    <span class="text-muted" style="font-size:.75rem;white-space:nowrap;">${c.score} · ${esc((c.sources || []).join('+'))}</span>
                </div>
                <div class="text-muted" style="font-size:.78rem;margin-top:2px;">${esc(c.preview)}…</div>
            </div>`).join('');
    }

    window.wv2Search = async function () {
        const q = document.getElementById('wv2-q').value.trim();
        const cust = document.getElementById('wv2-cust').value.trim();
        if (q.length < 2) return;
        const st = document.getElementById('wv2-search-status');
        st.textContent = 'Suche läuft…';
        document.getElementById('wv2-v2').innerHTML = '';
        try {
            const url = '/api/v1/admin/wissen-v2-search?q=' + encodeURIComponent(q) + (cust !== '' ? '&customer_id=' + encodeURIComponent(cust) : '');
            const r = await fetch(url);
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Fehler');
            const d = j.data;
            document.getElementById('wv2-v2-label').textContent = 'Ergebnisse (' + d.v2.ms + ' ms)';
            renderResults(document.getElementById('wv2-v2'), d.v2);
            st.textContent = '';
        } catch (err) {
            st.innerHTML = '<span style="color:var(--rose-600,#e11d48)">' + esc(err.message) + '</span>';
        }
    };

    // ===== Projektplan-Sync Health =====
    window.ppksLoadStatus = async function () {
        try {
            const r = await fetch('/api/v1/admin/projektplanner/knowledge-sync-status');
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'Fehler');
            const d = j.data;
            document.getElementById('ppks-synced').textContent  = (d.counts.synced  || 0).toLocaleString('de-DE');
            document.getElementById('ppks-dirty').textContent   = (d.counts.dirty   || 0).toLocaleString('de-DE');
            document.getElementById('ppks-skipped').textContent = (d.counts.skipped || 0).toLocaleString('de-DE');
            document.getElementById('ppks-last').textContent    = d.last_sync_rel || '—';

            const list = document.getElementById('ppks-list');
            if (!d.plans.length) { list.innerHTML = '<div class="text-muted" style="padding:1rem;text-align:center;font-size:var(--d-fs-xs);">Noch keine synced Pläne.</div>'; return; }
            list.innerHTML = d.plans.map(p => {
                const stateCls = p.status === 'synced' ? 'is-on' : (p.status === 'dirty' ? 'is-dirty' : 'is-off');
                const stateLbl = { synced: 'Synced', dirty: 'Ausstehend', skipped: 'Skipped' }[p.status] || p.status;
                const docLink = p.doc_id ? `<a href="/wissen/${p.doc_id}" target="_blank" style="color:var(--thoxan-600);text-decoration:none;">→ Quelle</a>` : '';
                return `<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-xs);">
                    <div style="flex:1;min-width:0;">
                        <strong>${esc(p.customer_abbr || '—')}</strong> · ${esc(p.title)}
                        <div class="text-muted" style="font-size:11px;">${esc(p.plan_status)} · ${p.chunks_count} Chunks · ${p.synced_rel || 'nie'}</div>
                    </div>
                    <span class="pp-kb-pill ${stateCls}" style="font-size:11px;padding:1px 8px;border-radius:999px;">${stateLbl}</span>
                    ${docLink}
                    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="ppksSyncOne(${p.plan_id}, this)" title="Plan jetzt syncen">⟳</button>
                </div>`;
            }).join('');
        } catch (e) {
            document.getElementById('ppks-list').innerHTML = '<div style="color:var(--rose-600);padding:1rem;font-size:var(--d-fs-xs);">' + esc(e.message) + '</div>';
        }
    };

    window.ppksSyncOne = async function (planId, btn) {
        const old = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '…';
        try {
            const r = await fetch('/api/v1/admin/projektplanner/plans/' + planId + '/sync-knowledge', { method: 'POST' });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            await ppksLoadStatus();
        } catch (e) {
            alert(e.message);
        } finally {
            btn.disabled = false; btn.innerHTML = old;
        }
    };

    window.ppksSyncAll = async function () {
        if (!confirm('Alle aktiven Pläne als sync-pflichtig markieren? Der Cron arbeitet sie dann nach und nach ab.')) return;
        const btn = document.getElementById('ppks-syncall-btn');
        btn.disabled = true;
        try {
            const r = await fetch('/api/v1/admin/projektplanner/knowledge-sync-status?action=mark-all-dirty', { method: 'POST' });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            alert(j.data.marked + ' Pläne markiert. Cron arbeitet sie ab.');
            await ppksLoadStatus();
        } catch (e) {
            alert(e.message);
        } finally {
            btn.disabled = false;
        }
    };

    document.addEventListener('DOMContentLoaded', () => { wv2LoadStatus(); ppksLoadStatus(); });
    if (document.readyState !== 'loading') { wv2LoadStatus(); ppksLoadStatus(); }
})();
</script>
