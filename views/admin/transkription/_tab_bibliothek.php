<?php /* Transkription — Bibliothek (Kachelansicht + Detail-Modal) */ ?>
<style>
/* Toolbar */
.tr-lib-toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:var(--d-section-gap); }
.tr-lib-toolbar .thx-input[type=search] { min-width:240px; flex:1; max-width:380px; }
.tr-lib-toolbar .thx-select { min-width:150px; }

/* Kachelraster */
.tr-lib-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }
.tr-lib-tile {
    background:#fff; border:1px solid var(--slate-200); border-radius:12px;
    padding:16px; cursor:pointer; transition: border-color .15s, box-shadow .15s, transform .1s;
    display:flex; flex-direction:column; gap:10px; min-height:160px;
}
.tr-lib-tile:hover { border-color:var(--thoxan-400); box-shadow:0 4px 14px rgba(0,76,155,0.08); transform:translateY(-1px); }
.tr-lib-tile-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.tr-lib-tile-icon {
    width:36px;height:36px;border-radius:9px;background:var(--thoxan-50); color:var(--thoxan-700);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.tr-lib-tile-icon .material-symbols-rounded { font-size:22px; }
.tr-lib-tile-title { font-size:var(--d-fs-base); font-weight:600; line-height:1.3; color:var(--slate-800); margin:0; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.tr-lib-tile-meta { font-size:var(--d-fs-xs); color:var(--slate-500); display:flex; gap:10px; flex-wrap:wrap; margin-top:auto; }
.tr-lib-tile-meta span { display:inline-flex; align-items:center; gap:3px; }
.tr-lib-tile-meta .material-symbols-rounded { font-size:14px; }
.tr-lib-tile-badges { display:flex; gap:6px; flex-wrap:wrap; }
.tr-lib-badge { padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.tr-lib-badge-loom    { background:#f3e8ff; color:#7e22ce; }
.tr-lib-badge-upload  { background:var(--slate-100); color:var(--slate-700); }
.tr-lib-badge-done    { background:var(--emerald-50); color:var(--emerald-700); }
.tr-lib-badge-running { background:var(--amber-50); color:var(--amber-700); }
.tr-lib-badge-failed  { background:var(--rose-50); color:var(--rose-700); }
.tr-lib-badge-queued  { background:var(--slate-100); color:var(--slate-600); }
.tr-lib-badge-outputs { background:var(--thoxan-50); color:var(--thoxan-700); }
.tr-lib-empty { background:#fff; border:1px dashed var(--slate-300); border-radius:12px; padding:48px; text-align:center; color:var(--slate-500); }

/* Detail-Modal */
.tr-lib-backdrop {
    position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:1000;
    display:flex; align-items:center; justify-content:center; padding:20px;
}
.tr-lib-modal {
    background:#fff; border-radius:14px; max-width:880px; width:100%;
    max-height:90vh; display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 12px 60px rgba(0,0,0,0.25);
}
.tr-lib-modal-head { padding:18px 22px; border-bottom:1px solid var(--slate-200); display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.tr-lib-modal-head h2 { margin:0; font-size:var(--d-fs-xl); font-weight:700; color:var(--slate-800); display:flex; align-items:center; gap:8px; }
.tr-lib-title-edit { background:transparent; border:none; cursor:text; padding:2px 4px; border-radius:4px; min-width:100px; font:inherit; color:inherit; width:100%; }
.tr-lib-title-edit:hover { background:var(--slate-100); }
.tr-lib-title-edit:focus { background:#fff; outline:2px solid var(--thoxan-400); }
.tr-lib-modal-close { background:transparent; border:none; cursor:pointer; padding:4px; border-radius:6px; color:var(--slate-500); }
.tr-lib-modal-close:hover { background:var(--slate-100); color:var(--slate-700); }
.tr-lib-modal-meta { padding:12px 22px; background:var(--slate-50); border-bottom:1px solid var(--slate-100); display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:8px 16px; font-size:var(--d-fs-xs); color:var(--slate-600); }
.tr-lib-modal-meta strong { color:var(--slate-800); display:block; font-size:var(--d-fs-sm); font-weight:600; }
.tr-lib-modal-body { padding:18px 22px; overflow-y:auto; flex:1; }
.tr-lib-modal-foot { padding:14px 22px; border-top:1px solid var(--slate-200); display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; background:var(--slate-50); }

/* Accordion fuer Outputs */
.tr-acc { display:flex; flex-direction:column; gap:8px; }
.tr-acc-item { border:1px solid var(--slate-200); border-radius:8px; overflow:hidden; background:#fff; }
.tr-acc-head {
    width:100%; padding:12px 14px; background:#fff; border:none; cursor:pointer;
    display:flex; justify-content:space-between; align-items:center; gap:10px;
    font-size:var(--d-fs-sm); font-weight:600; color:var(--slate-700); text-align:left;
}
.tr-acc-head:hover { background:var(--slate-50); }
.tr-acc-head .material-symbols-rounded.tr-acc-chevron { transition:transform .2s; font-size:18px; color:var(--slate-400); }
.tr-acc-item.is-open .tr-acc-chevron { transform:rotate(180deg); }
.tr-acc-body { padding:0 14px 14px; display:none; }
.tr-acc-item.is-open .tr-acc-body { display:block; }
.tr-acc-actions { display:flex; gap:6px; margin-bottom:10px; flex-wrap:wrap; align-items:center; }
.tr-acc-output {
    font-family:Frutiger,Calibri,sans-serif; font-size:var(--d-fs-sm); line-height:1.6;
    background:var(--slate-50); padding:14px; border-radius:8px; border:1px solid var(--slate-100);
    max-height:420px; overflow-y:auto;
}
.tr-acc-output h2 { font-size:var(--d-fs-xl); margin:14px 0 8px; font-weight:700; color:var(--thoxan-800); }
.tr-acc-output h3 { font-size:var(--d-fs-lg); margin:12px 0 6px; font-weight:700; color:var(--thoxan-700); }
.tr-acc-output h4 { font-size:var(--d-fs-base); margin:10px 0 4px; font-weight:700; color:var(--slate-700); }
.tr-acc-output ul, .tr-acc-output ol { margin:6px 0 6px 22px; padding:0; }
.tr-acc-output li { margin:2px 0; }
.tr-acc-output p  { margin:6px 0; }
.tr-acc-output strong { font-weight:700; }
.tr-acc-output em { font-style:italic; }
.tr-acc-output code { background:var(--slate-200); padding:1px 4px; border-radius:3px; font-size:0.95em; }
.tr-acc-generate {
    display:flex; gap:8px; align-items:center; padding:10px 14px;
    background:var(--thoxan-50); border-radius:8px; font-size:var(--d-fs-sm); color:var(--thoxan-700);
}
</style>

<div class="tr-lib-toolbar">
    <input type="search" class="thx-input" id="tr-lib-q" placeholder="Suchen (Titel, Dateiname, Kunde) …" oninput="trLibFilter()">
    <select class="thx-select" id="tr-lib-status" onchange="trLibFilter()">
        <option value="">alle Status</option>
        <option value="done" selected>fertig</option>
        <option value="running">laeuft</option>
        <option value="queued">eingereiht</option>
        <option value="failed">fehlgeschlagen</option>
    </select>
    <select class="thx-select" id="tr-lib-source" onchange="trLibFilter()">
        <option value="">alle Quellen</option>
        <option value="loom">Loom</option>
        <option value="upload">Upload</option>
    </select>
    <select class="thx-select" id="tr-lib-sort" onchange="trLibFilter()">
        <option value="created_desc">Neueste zuerst</option>
        <option value="created_asc">Aelteste zuerst</option>
        <option value="title_asc">Titel A-Z</option>
        <option value="duration_desc">Dauer absteigend</option>
    </select>
</div>

<div id="tr-lib-grid" class="tr-lib-grid">
    <div class="tr-lib-empty">Laedt …</div>
</div>

<div id="tr-lib-modal-slot"></div>

<script>
'use strict';
(function() {
    let allJobs = [];
    let currentJobId = 0;

    function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function fmtDate(s) {
        if (!s) return '–';
        return new Date(s).toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }
    function fmtDur(sec) {
        if (!sec) return '–';
        const s = Math.round(sec), m = Math.floor(s/60), h = Math.floor(m/60);
        if (h > 0) return h + 'h ' + (m%60) + 'min';
        return m + ':' + String(s%60).padStart(2,'0') + ' min';
    }
    function displayTitle(j) {
        if (j.title && j.title.trim()) return j.title;
        if (j.source === 'loom') return 'Loom-Aufnahme · ' + fmtDate(j.created_at).split(',')[0];
        return (j.filename || 'Aufnahme').replace(/\.[a-z0-9]+$/i, '');
    }
    function statusBadge(s) {
        const map = { done:'fertig', running:'laeuft', queued:'eingereiht', failed:'fehlgeschlagen', cancelled:'abgebrochen' };
        return '<span class="tr-lib-badge tr-lib-badge-' + s + '">' + (map[s] || s) + '</span>';
    }
    function sourceBadge(s) {
        if (s === 'loom') return '<span class="tr-lib-badge tr-lib-badge-loom">Loom</span>';
        return '<span class="tr-lib-badge tr-lib-badge-upload">Upload</span>';
    }

    function mdRender(src) {
        if (!src) return '';
        const lines = String(src).split(/\r?\n/);
        const out = []; let inUl=false, inOl=false, inP=false;
        const closeP = () => { if (inP) { out.push('</p>'); inP=false; } };
        const closeUl = () => { if (inUl) { out.push('</ul>'); inUl=false; } };
        const closeOl = () => { if (inOl) { out.push('</ol>'); inOl=false; } };
        const closeAll = () => { closeP(); closeUl(); closeOl(); };
        const inline = (t) => {
            t = esc(t);
            t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
            t = t.replace(/\*\*([^\*]+)\*\*/g, '<strong>$1</strong>');
            t = t.replace(/(^|[^\*])\*([^\*\n]+)\*/g, '$1<em>$2</em>');
            return t;
        };
        for (const raw of lines) {
            const line = raw.replace(/\s+$/, ''); let m;
            if (!line.trim()) { closeAll(); continue; }
            if ((m = line.match(/^(#{1,4})\s+(.+)$/))) { closeAll(); const lvl = Math.min(m[1].length+1, 4); out.push('<h'+lvl+'>'+inline(m[2])+'</h'+lvl+'>'); continue; }
            if (/^---+$/.test(line)) { closeAll(); out.push('<hr>'); continue; }
            if ((m = line.match(/^\s*[-\*]\s+(.+)$/))) { closeP(); closeOl(); if (!inUl){out.push('<ul>'); inUl=true;} out.push('<li>'+inline(m[1])+'</li>'); continue; }
            if ((m = line.match(/^\s*\d+\.\s+(.+)$/))) { closeP(); closeUl(); if (!inOl){out.push('<ol>'); inOl=true;} out.push('<li>'+inline(m[1])+'</li>'); continue; }
            closeUl(); closeOl();
            if (!inP) { out.push('<p>'); inP=true; out.push(inline(line)); } else { out.push('<br>'+inline(line)); }
        }
        closeAll();
        return out.join('\n');
    }

    async function load() {
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs');
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            allJobs = j.data.jobs;
            trLibFilter();
        } catch (e) {
            document.getElementById('tr-lib-grid').innerHTML =
                '<div class="tr-lib-empty" style="color:var(--rose-700);">Fehler: ' + esc(e.message) + '</div>';
        }
    }

    window.trLibFilter = function() {
        const q = document.getElementById('tr-lib-q').value.trim().toLowerCase();
        const status = document.getElementById('tr-lib-status').value;
        const source = document.getElementById('tr-lib-source').value;
        const sort = document.getElementById('tr-lib-sort').value;

        let items = allJobs.slice();
        if (status) items = items.filter(j => j.status === status);
        if (source) items = items.filter(j => j.source === source);
        if (q) {
            items = items.filter(j => {
                const t = (displayTitle(j) + ' ' + (j.filename||'') + ' ' + (j.customer_name||'')).toLowerCase();
                return t.includes(q);
            });
        }
        const cmpDate = (a,b) => new Date(b.created_at) - new Date(a.created_at);
        switch (sort) {
            case 'created_asc':    items.sort((a,b) => -cmpDate(a,b)); break;
            case 'title_asc':      items.sort((a,b) => displayTitle(a).localeCompare(displayTitle(b), 'de')); break;
            case 'duration_desc':  items.sort((a,b) => (b.duration_sec||0) - (a.duration_sec||0)); break;
            default: items.sort(cmpDate);
        }
        render(items);
    };

    function render(items) {
        const host = document.getElementById('tr-lib-grid');
        if (!items.length) {
            host.innerHTML = '<div class="tr-lib-empty">Keine Aufnahmen mit diesen Filtern. <a href="/admin/transkription?tab=upload" style="color:var(--thoxan-700);">Neue hochladen →</a></div>';
            return;
        }
        host.innerHTML = items.map(j => {
            const title = displayTitle(j);
            const outputsLabel = j.status === 'done'
                ? '<span class="tr-lib-badge tr-lib-badge-outputs">Vorlagen verfuegbar</span>'
                : '';
            return `
                <div class="tr-lib-tile" onclick="trLibOpen(${j.job_id})">
                    <div class="tr-lib-tile-head">
                        <div class="tr-lib-tile-icon"><span class="material-symbols-rounded">${j.source==='loom'?'movie':'graphic_eq'}</span></div>
                        <div class="tr-lib-tile-badges">${sourceBadge(j.source)} ${statusBadge(j.status)}</div>
                    </div>
                    <h3 class="tr-lib-tile-title">${esc(title)}</h3>
                    <div class="tr-lib-tile-meta">
                        <span><span class="material-symbols-rounded">schedule</span> ${fmtDate(j.created_at).split(',')[0]}</span>
                        ${j.duration_sec ? `<span><span class="material-symbols-rounded">timer</span> ${fmtDur(j.duration_sec)}</span>` : ''}
                        ${j.customer_name ? `<span><span class="material-symbols-rounded">business</span> ${esc(j.customer_name)}</span>` : ''}
                        ${j.speaker_count > 1 ? `<span><span class="material-symbols-rounded">groups</span> ${j.speaker_count}</span>` : ''}
                    </div>
                    <div class="tr-lib-tile-badges">${outputsLabel}</div>
                </div>
            `;
        }).join('');
    }

    const tplOrder = ['memo','workshop','call','tutorial','raw'];
    const tplLabel = { memo:'Kurz-Memo', workshop:'Workshop-Protokoll (DOCX)', call:'Call-Notiz', tutorial:'Tutorial-Doku', raw:'Rohtext' };

    window.trLibOpen = async function(jobId) {
        currentJobId = jobId;
        const job = allJobs.find(j => j.job_id === jobId);
        if (!job) return;
        let outputs = [];
        if (job.status === 'done') {
            try {
                const r = await fetch('/api/v1/admin/transkription/jobs/' + jobId + '/outputs');
                const j = await r.json();
                if (j.success) outputs = j.data.outputs;
            } catch (e) {}
        }
        renderModal(job, outputs);
    };

    function renderModal(job, outputs) {
        const title = displayTitle(job);
        const byTpl = {};
        outputs.forEach(o => { byTpl[o.template_type] = o; });

        const accordionHtml = tplOrder.map(tpl => {
            const o = byTpl[tpl];
            if (o) {
                const docxBtn = o.output_format === 'docx' && o.docx_path
                    ? `<a class="thx-btn thx-btn-small thx-btn-secondary" href="/api/v1/admin/transkription/outputs/${o.id}/download" download><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">download</span> DOCX</a>` : '';
                const knowledgeChip = o.knowledge_doc_id
                    ? `<span style="font-size:var(--d-fs-xs);color:var(--emerald-700);">✓ im Wissen (#${o.knowledge_doc_id})</span>` : '';
                return `
                    <div class="tr-acc-item">
                        <button class="tr-acc-head" onclick="trAccToggle(this)">
                            <span><span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;color:var(--emerald-600);">check_circle</span> ${esc(tplLabel[tpl])}</span>
                            <span class="material-symbols-rounded tr-acc-chevron">expand_more</span>
                        </button>
                        <div class="tr-acc-body">
                            <div class="tr-acc-actions">
                                ${docxBtn}
                                <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="trAccCopy(${o.id})"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">content_copy</span> Kopieren</button>
                                <button class="thx-btn thx-btn-small thx-btn-secondary" onclick="trAccRegen('${tpl}')"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">refresh</span> Neu erzeugen</button>
                                <button class="thx-btn thx-btn-small thx-btn-primary" onclick="trAccKnowledge(${o.id})"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">library_books</span> Ins Wissen</button>
                                <button class="thx-btn thx-btn-small thx-btn-danger" onclick="trAccDelete(${o.id})"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">delete</span></button>
                                ${knowledgeChip}
                            </div>
                            <div class="tr-acc-output">${mdRender(o.output_text || '')}</div>
                            <textarea id="tr-raw-${o.id}" style="display:none;">${esc(o.output_text || '')}</textarea>
                        </div>
                    </div>`;
            } else {
                return `
                    <div class="tr-acc-item">
                        <button class="tr-acc-head" onclick="trAccToggle(this)">
                            <span><span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;color:var(--slate-400);">radio_button_unchecked</span> ${esc(tplLabel[tpl])}</span>
                            <span class="material-symbols-rounded tr-acc-chevron">expand_more</span>
                        </button>
                        <div class="tr-acc-body">
                            <div class="tr-acc-generate">
                                <span>Noch nicht erzeugt.</span>
                                <button class="thx-btn thx-btn-small thx-btn-primary" onclick="trAccRegen('${tpl}')"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">auto_awesome</span> Jetzt erzeugen</button>
                            </div>
                        </div>
                    </div>`;
            }
        }).join('');

        document.getElementById('tr-lib-modal-slot').innerHTML = `
            <div class="tr-lib-backdrop" onclick="if(event.target===this) trLibClose()">
                <div class="tr-lib-modal">
                    <div class="tr-lib-modal-head">
                        <h2>
                            <span class="material-symbols-rounded" style="color:var(--thoxan-600);">${job.source==='loom'?'movie':'graphic_eq'}</span>
                            <input class="tr-lib-title-edit" id="tr-lib-title" value="${esc(title)}" onblur="trLibSaveTitle()" onkeydown="if(event.key==='Enter')this.blur();">
                        </h2>
                        <button class="tr-lib-modal-close" onclick="trLibClose()"><span class="material-symbols-rounded">close</span></button>
                    </div>
                    <div class="tr-lib-modal-meta">
                        <div><strong>${fmtDate(job.created_at)}</strong>erstellt</div>
                        <div><strong>${fmtDur(job.duration_sec)}</strong>Dauer</div>
                        <div><strong>${esc(job.customer_name || '–')}</strong>Kunde</div>
                        <div><strong>${job.speaker_count || 1}</strong>Sprecher</div>
                        <div><strong>${esc(job.model || '–')}</strong>Modell</div>
                        <div><strong>${esc(job.language_detected || job.language || 'auto')}</strong>Sprache</div>
                    </div>
                    <div class="tr-lib-modal-body">
                        <div class="tr-acc">${accordionHtml}</div>
                    </div>
                    <div class="tr-lib-modal-foot">
                        <div style="display:flex;gap:6px;">
                            <a class="thx-btn thx-btn-small thx-btn-secondary" href="/admin/transkription?tab=editor&job=${job.job_id}"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">edit_note</span> Im Editor oeffnen</a>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <button class="thx-btn thx-btn-small thx-btn-danger" onclick="trLibDelete()"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">delete</span> Aufnahme loeschen</button>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    window.trLibClose = function() {
        document.getElementById('tr-lib-modal-slot').innerHTML = '';
        currentJobId = 0;
    };

    window.trAccToggle = function(btn) { btn.parentElement.classList.toggle('is-open'); };

    window.trAccCopy = function(outId) {
        const raw = document.getElementById('tr-raw-' + outId);
        if (raw) navigator.clipboard.writeText(raw.value).then(() => App.showNotification('In Zwischenablage kopiert', 'success'));
    };

    window.trAccRegen = async function(tpl) {
        if (!currentJobId) return;
        if (!confirm('Vorlage „' + tplLabel[tpl] + '" neu erzeugen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId + '/outputs', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},
                body: JSON.stringify({ template_type: tpl }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
            trLibOpen(currentJobId);
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trAccDelete = async function(outId) {
        if (!confirm('Diese Variante loeschen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/outputs/' + outId, {
                method:'DELETE', headers:{'X-CSRF-Token':App.csrfToken},
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification('Geloescht', 'success');
            trLibOpen(currentJobId);
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trAccKnowledge = async function(outId) {
        if (!currentJobId) return;
        if (!confirm('Diese Variante ins Wissen einspeisen?')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId + '/to-knowledge', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},
                body: JSON.stringify({ output_id: outId }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification(j.message, 'success');
            trLibOpen(currentJobId);
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trLibSaveTitle = async function() {
        if (!currentJobId) return;
        const el = document.getElementById('tr-lib-title');
        if (!el) return;
        const newTitle = el.value.trim();
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId, {
                method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-Token':App.csrfToken},
                body: JSON.stringify({ title: newTitle }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            const job = allJobs.find(x => x.job_id === currentJobId);
            if (job) job.title = newTitle || null;
            trLibFilter();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    window.trLibDelete = async function() {
        if (!currentJobId) return;
        if (!confirm('Diese Aufnahme samt aller Outputs loeschen? Kann nicht rueckgaengig gemacht werden.')) return;
        try {
            const r = await fetch('/api/v1/admin/transkription/jobs/' + currentJobId, {
                method:'DELETE', headers:{'X-CSRF-Token':App.csrfToken},
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            App.showNotification('Geloescht', 'success');
            allJobs = allJobs.filter(x => x.job_id !== currentJobId);
            trLibClose();
            trLibFilter();
        } catch (e) { App.showNotification(e.message, 'error'); }
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.querySelector('.tr-lib-backdrop')) trLibClose();
    });

    load();
})();
</script>
