<?php
/* Transkription — Editor-Tab */
$hfTokenSet = (string)\Core\Settings::get('huggingface_token') !== '';
?>
<style>
.tr-ed-toolbar { display:flex; gap:8px; align-items:center; margin-bottom:var(--d-row-gap); flex-wrap:wrap; }
.tr-ed-search-wrap { position:relative; min-width:380px; flex:1; max-width:560px; }
.tr-ed-search-list {
    position:absolute; top:100%; left:0; right:0; max-height:340px; overflow-y:auto;
    background:#fff; border:1px solid var(--slate-300); border-top:none;
    border-radius:0 0 var(--d-card-radius) var(--d-card-radius);
    box-shadow:0 6px 18px rgba(0,0,0,0.08); z-index:20; display:none;
}
.tr-ed-search-list.is-open { display:block; }
.tr-ed-search-item {
    padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--slate-100);
    font-size:var(--d-fs-sm);
}
.tr-ed-search-item:hover, .tr-ed-search-item.is-active { background:var(--thoxan-50); }
.tr-ed-search-item-meta { font-size:var(--d-fs-xs); color:var(--slate-500); margin-top:2px; }
.tr-ed-search-empty { padding:14px; color:var(--slate-500); font-size:var(--d-fs-sm); text-align:center; }
.tr-ed-banner-warn {
    background:var(--amber-50); border:1px solid var(--amber-200); border-left:4px solid var(--amber-400);
    padding:10px 14px; border-radius:var(--d-card-radius); margin-bottom:var(--d-row-gap);
    font-size:var(--d-fs-sm); color:var(--amber-800);
}
.tr-ed-card { background:#fff; border:1px solid var(--slate-200); border-radius:var(--d-card-radius); padding:var(--d-card-pad); margin-bottom:var(--d-section-gap); }
.tr-ed-meta { display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:var(--d-row-gap); margin-bottom:var(--d-row-gap); font-size:var(--d-fs-sm); }
.tr-ed-meta-label { color:var(--slate-500); font-size:var(--d-fs-xs); text-transform:uppercase; letter-spacing:.03em; }
.tr-speakers-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:8px; }
.tr-speakers-grid label { font-size:var(--d-fs-xs); color:var(--slate-600); display:block; margin-bottom:2px; }
.tr-speakers-grid .thx-input { width:100%; }
.tr-segments { display:flex; flex-direction:column; gap:8px; max-height:540px; overflow-y:auto; padding-right:6px; }
.tr-segment { display:grid; grid-template-columns:80px 140px 1fr; gap:8px; padding:6px 8px; border-radius:6px; background:var(--slate-50); border:1px solid var(--slate-100); }
.tr-segment-ts { font-family:ui-monospace,monospace; font-size:var(--d-fs-xs); color:var(--slate-500); align-self:center; }
.tr-segment-speaker { font-size:var(--d-fs-xs); color:var(--thoxan-700); font-weight:600; align-self:center; }
.tr-segment-text { font-size:var(--d-fs-sm); line-height:1.5; }
.tr-segment-text[contenteditable=true] { outline:1px dashed var(--thoxan-300); padding:2px 4px; border-radius:3px; background:#fff; }
</style>

<?php if (!$hfTokenSet): ?>
<div class="tr-ed-banner-warn">
    <strong>Sprecher-Erkennung nicht aktiv:</strong> Ohne HuggingFace-Token landet jede Aufnahme als Einzelsprecher.
    Setup unter <a href="/admin/transkription?tab=admin" style="color:var(--amber-900);text-decoration:underline;">Admin-Prompts → Sprecher-Erkennung</a>.
</div>
<?php endif; ?>

<div class="tr-ed-toolbar">
    <div class="tr-ed-search-wrap">
        <input id="tr-ed-search" class="thx-input" type="search"
            placeholder="Aufnahme suchen — Titel, Dateiname, Kunde …"
            autocomplete="off"
            onfocus="trEdSearchOpen()"
            oninput="trEdSearchFilter()">
        <div id="tr-ed-search-list" class="tr-ed-search-list"></div>
    </div>
    <button class="thx-btn thx-btn-secondary thx-btn-small" onclick="trEdApplyCorrections()" id="tr-ed-corr-btn" disabled>
        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">spellcheck</span>
        Korrekturen anwenden
    </button>
    <button class="thx-btn thx-btn-primary thx-btn-small" onclick="trEdSave()" id="tr-ed-save-btn" disabled>
        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">save</span>
        Speichern
    </button>
    <span id="tr-ed-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
</div>

<div id="tr-ed-content" style="display:none;">
    <div class="tr-ed-card">
        <div class="tr-ed-meta">
            <div><div class="tr-ed-meta-label">Dauer</div><div id="tr-ed-dur">–</div></div>
            <div><div class="tr-ed-meta-label">Wortanzahl</div><div id="tr-ed-words">–</div></div>
            <div><div class="tr-ed-meta-label">Sprache</div><div id="tr-ed-lang">–</div></div>
            <div><div class="tr-ed-meta-label">Sprecher</div><div id="tr-ed-spk">–</div></div>
        </div>
    </div>

    <div class="tr-ed-card">
        <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Sprecher benennen</h3>
        <p style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-xs);color:var(--slate-600);">
            Aus den Diarization-Labels SPEAKER_00, SPEAKER_01 … echte Namen machen. Klick auf „Speichern" uebernimmt.
        </p>
        <div id="tr-ed-speakers" class="tr-speakers-grid"></div>
    </div>

    <div class="tr-ed-card">
        <h3 style="margin:0 0 var(--d-row-gap);font-size:var(--d-fs-base);">Transkript</h3>
        <div id="tr-ed-segments" class="tr-segments"></div>
    </div>
</div>

<div id="tr-ed-empty" class="tr-ed-card" style="text-align:center;color:var(--slate-500);padding:36px;">
    Waehle einen fertigen Job aus der Liste, um sein Transkript zu bearbeiten.
</div>

<script>
'use strict';
(function() {
    let current = null;   // { result, segments, speakers }
    let currentJobId = 0;
    let dirty = false;

    function trEsc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtDur(sec) {
        if (!sec) return '–';
        const s = Math.round(sec);
        const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), r = s%60;
        return h > 0 ? (h+':'+String(m).padStart(2,'0')+':'+String(r).padStart(2,'0'))
                     : (m+':'+String(r).padStart(2,'0'));
    }
    function fmtTs(sec) {
        const s = Math.round(sec || 0);
        return Math.floor(s/60)+':'+String(s%60).padStart(2,'0');
    }
    function markDirty() {
        dirty = true;
        document.getElementById('tr-ed-save-btn').disabled = false;
        document.getElementById('tr-ed-status').textContent = 'ungespeicherte Aenderungen';
    }

    let allDoneJobs = [];

    function trDisplayTitle(j) {
        if (j.title && String(j.title).trim()) return j.title;
        if (j.source === 'loom') {
            const d = j.created_at ? new Date(j.created_at).toLocaleDateString('de-DE') : '';
            return 'Loom-Aufnahme' + (d ? ' · ' + d : '');
        }
        return String(j.filename || ('Job #' + j.job_id)).replace(/\.[a-z0-9]+$/i, '');
    }

    async function loadJobOptions() {
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs?status=done');
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            allDoneJobs = j.data.jobs;
            trEdSearchRender(allDoneJobs);

            const qsJob = new URLSearchParams(location.search).get('job');
            if (qsJob && allDoneJobs.some(x => String(x.job_id) === qsJob)) {
                trEdSelectJob(parseInt(qsJob, 10));
            }
        } catch (e) {
            document.getElementById('tr-ed-status').textContent = 'Liste laden fehlgeschlagen: ' + e.message;
        }
    }

    function trEdSearchRender(items) {
        const host = document.getElementById('tr-ed-search-list');
        if (!items.length) {
            host.innerHTML = '<div class="tr-ed-search-empty">Keine fertigen Aufnahmen gefunden.</div>';
            return;
        }
        host.innerHTML = items.slice(0, 50).map(j => {
            const title = trDisplayTitle(j);
            const meta = [
                j.created_at ? new Date(j.created_at).toLocaleDateString('de-DE') : '',
                j.customer_name || 'ohne Kunde',
                j.duration_sec ? Math.round(j.duration_sec/60) + ' min' : '',
                j.speaker_count ? j.speaker_count + ' Sprecher' : '',
            ].filter(Boolean).join(' · ');
            return `
                <div class="tr-ed-search-item" data-job-id="${j.job_id}" onclick="trEdSelectJob(${j.job_id})">
                    <div><strong>${trEsc(title)}</strong></div>
                    <div class="tr-ed-search-item-meta">${trEsc(meta)}</div>
                </div>`;
        }).join('');
    }

    window.trEdSearchOpen = function() {
        document.getElementById('tr-ed-search-list').classList.add('is-open');
    };
    window.trEdSearchFilter = function() {
        const q = document.getElementById('tr-ed-search').value.trim().toLowerCase();
        const filtered = !q ? allDoneJobs : allDoneJobs.filter(j => {
            const hay = (trDisplayTitle(j) + ' ' + (j.filename||'') + ' ' + (j.customer_name||'')).toLowerCase();
            return hay.includes(q);
        });
        trEdSearchRender(filtered);
        trEdSearchOpen();
    };
    // Schliessen, wenn Klick ausserhalb
    document.addEventListener('click', e => {
        const wrap = document.querySelector('.tr-ed-search-wrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('tr-ed-search-list').classList.remove('is-open');
        }
    });

    window.trEdSelectJob = function(jobId) {
        const j = allDoneJobs.find(x => x.job_id === jobId);
        if (!j) return;
        document.getElementById('tr-ed-search').value = trDisplayTitle(j);
        document.getElementById('tr-ed-search-list').classList.remove('is-open');
        trEdLoadById(jobId);
    };

    window.trEdLoad = function() { /* alter Handler fuer Dropdown — nicht mehr genutzt */ };

    async function trEdLoadById(id) {
        if (!id) return;
        if (!id) {
            document.getElementById('tr-ed-content').style.display = 'none';
            document.getElementById('tr-ed-empty').style.display = '';
            return;
        }
        currentJobId = id;
        dirty = false;
        document.getElementById('tr-ed-save-btn').disabled = true;
        document.getElementById('tr-ed-status').textContent = 'Lade …';
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + id + '/result');
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            current = j.data;
            render();
            document.getElementById('tr-ed-status').textContent = '';
            document.getElementById('tr-ed-corr-btn').disabled = false;
        } catch (e) {
            document.getElementById('tr-ed-status').textContent = 'Fehler: ' + e.message;
        }
    };

    function render() {
        document.getElementById('tr-ed-empty').style.display = 'none';
        document.getElementById('tr-ed-content').style.display = '';
        document.getElementById('tr-ed-dur').textContent = fmtDur(current.result.duration_sec);
        document.getElementById('tr-ed-words').textContent = current.result.word_count || '–';
        document.getElementById('tr-ed-lang').textContent = current.result.language_detected || '–';
        document.getElementById('tr-ed-spk').textContent = current.speakers.length;

        // Banner direkt ueber Sprechern, wenn Diarization wahrscheinlich nicht griff
        const banner = document.getElementById('tr-ed-spk-banner');
        if (banner) banner.remove();
        if (current.speakers.length <= 1) {
            const note = document.createElement('div');
            note.id = 'tr-ed-spk-banner';
            note.className = 'tr-ed-banner-warn';
            note.innerHTML = <?= $hfTokenSet ? "'Diarization war aktiv, hat aber nur 1 Sprecher erkannt — bei Mono-Aufnahmen oder sehr aehnlichen Stimmen kann das normal sein. Du kannst manuell mehrere Sprecher unterscheiden, indem Du Segmenten unten verschiedene Labels gibst.'" : "'Sprecher-Erkennung nicht aktiv (kein HuggingFace-Token konfiguriert). Setup unter <a href=\"/admin/transkription?tab=admin\" style=\"color:var(--amber-900);text-decoration:underline;\">Admin-Prompts</a>.'" ?>;
            document.getElementById('tr-ed-speakers').parentElement.prepend(note);
        }

        // Sprecher-Felder
        const sk = document.getElementById('tr-ed-speakers');
        sk.innerHTML = current.speakers.map(s => `
            <div>
                <label>${trEsc(s.label_internal)}</label>
                <input class="thx-input" type="text" data-sp-id="${s.id}" value="${trEsc(s.name_custom || '')}" placeholder="Name eintragen …" oninput="trEdMarkDirty()">
            </div>
        `).join('');

        // Segmente
        const segs = document.getElementById('tr-ed-segments');
        segs.innerHTML = current.segments.map((s, i) => `
            <div class="tr-segment">
                <div class="tr-segment-ts">${fmtTs(s.start)}–${fmtTs(s.end)}</div>
                <div class="tr-segment-speaker">${trEsc(s.speaker || 'SPEAKER_00')}</div>
                <div class="tr-segment-text" contenteditable="true" data-seg-idx="${i}" oninput="trEdMarkDirty()">${trEsc(s.text || '')}</div>
            </div>
        `).join('');
    }

    window.trEdMarkDirty = markDirty;

    window.trEdSave = async function() {
        if (!current) return;
        // Speaker-Mapping sammeln
        const speakers = Array.from(document.querySelectorAll('[data-sp-id]')).map(el => ({
            id: parseInt(el.dataset.spId, 10),
            name_custom: el.value.trim() || null,
        }));
        // Segmente sammeln
        const segPatches = Array.from(document.querySelectorAll('.tr-segment-text')).map((el, i) => ({
            text: el.textContent.trim(),
        }));
        // Volltext rebuilden
        const transcriptText = segPatches.map(s => s.text).filter(Boolean).join('\n');
        try {
            const r1 = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId + '/speakers', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({ speakers }),
            });
            const j1 = await r1.json();
            if (!j1.success) throw new Error(j1.message);

            const r2 = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId + '/result', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': App.csrfToken },
                body: JSON.stringify({ transcript_text: transcriptText, segments: segPatches }),
            });
            const j2 = await r2.json();
            if (!j2.success) throw new Error(j2.message);

            dirty = false;
            document.getElementById('tr-ed-save-btn').disabled = true;
            document.getElementById('tr-ed-status').textContent = '✓ gespeichert';
            App.showNotification('Transkript gespeichert', 'success');
        } catch (e) {
            App.showNotification(e.message, 'error');
        }
    };

    window.trEdApplyCorrections = async function() {
        if (!currentJobId) return;
        if (dirty && !confirm('Ungespeicherte Aenderungen werden ueberschrieben. Trotzdem fortfahren?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId + '/apply-corrections', {
                method: 'POST',
                headers: { 'X-CSRF-Token': App.csrfToken },
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
            trEdLoad();
        } catch (e) {
            App.showNotification(e.message, 'error');
        }
    };

    window.addEventListener('beforeunload', e => {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    loadJobOptions();
})();
</script>
