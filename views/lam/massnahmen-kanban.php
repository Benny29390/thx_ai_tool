<?php $activeModul = 'massnahmen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamKanban()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Maßnahmen — Kanban</h1>
        <div class="thx-page-subtitle">Per Drag-and-Drop Status ändern. Spalten = Status (vorgeschlagen → live).</div>
    </div>
    <div style="display:flex;gap:8px;">
        <a class="lam-btn lam-btn-secondary" href="/lam/massnahmen">📋 Liste</a>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Suche</label>
            <input type="text" class="lam-filter-input" placeholder="Domain, Linktext oder Kunde …"
                   x-model="filter.suche" @input.debounce.300ms="laden()">
        </div>
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Kunde</label>
            <select class="lam-filter-select" x-model="filter.customer_id" @change="laden()">
                <option value="">alle Kunden</option>
                <template x-for="k in kunden" :key="k.id">
                    <option :value="k.id" x-text="(k.kuerzel || k.abbreviation || k.name) + ' — ' + k.name"></option>
                </template>
            </select>
        </div>
    </div>
</section>

<section class="lam-card" style="padding:0;background:transparent;border:0;">
    <div class="kanban-board">
        <template x-for="spalte in spalten" :key="spalte.key">
            <div class="kanban-col"
                 :class="dragOverSpalte === spalte.key ? 'is-over' : ''"
                 @dragover.prevent="dragOverSpalte = spalte.key"
                 @dragleave="dragOverSpalte = null"
                 @drop="drop($event, spalte.key)">
                <div class="kanban-col-head" :style="'border-top:3px solid ' + spalte.farbe + ';'">
                    <h3 style="margin:0;font-size:var(--d-fs-sm);" x-text="spalte.titel"></h3>
                    <span class="muted" style="font-size:var(--d-fs-xs);" x-text="(byStatus[spalte.key] || []).length + ' Maßnahmen'"></span>
                </div>
                <div class="kanban-col-body">
                    <template x-for="m in (byStatus[spalte.key] || [])" :key="m.id">
                        <a class="kanban-card"
                           draggable="true"
                           :href="'/lam/massnahmen/' + m.id"
                           @dragstart="dragStart($event, m)"
                           @dragend="dragEnd()"
                           @click.ctrl.prevent @click.meta.prevent>
                            <div class="kanban-card-kunde" x-text="m.customer_kuerzel"></div>
                            <div class="kanban-card-domain" x-text="m.domain_url"></div>
                            <div class="kanban-card-meta">
                                <span x-show="m.geplant_am" class="muted" style="font-size:var(--d-fs-xs);">📅 <span x-text="formatDatum(m.geplant_am)"></span></span>
                                <span x-show="m.veroeffentlicht_am" class="muted" style="font-size:var(--d-fs-xs);">✓ <span x-text="formatDatum(m.veroeffentlicht_am)"></span></span>
                            </div>
                            <div x-show="m.linktext" class="kanban-card-linktext" x-text="m.linktext"></div>
                        </a>
                    </template>
                    <div class="kanban-empty" x-show="(byStatus[spalte.key] || []).length === 0">leer</div>
                </div>
            </div>
        </template>
    </div>
</section>

<div class="kanban-flash" x-show="flash.text" :class="'is-' + flash.typ" x-transition x-text="flash.text"></div>

</div>

<style>
[x-cloak]{display:none!important}
/* Kanban-Höhe 100% bis zum Footer — horizontaler Scroll nur wenn Spalten nicht reinpassen,
   vertikaler Scroll PER SPALTE damit alle Spalten gleich hoch bleiben */
.kanban-board {
    display: grid;
    grid-template-columns: repeat(7, minmax(220px, 1fr));
    gap: 12px;
    overflow-x: auto;
    padding: 8px 0 16px;
    /* Volle Höhe ab Kanban-Container bis ~unten (minus Top-Bar + Page-Header + Tabs + Filter ~ 290px) */
    height: calc(100vh - 290px);
    min-height: 480px;
}
.kanban-col {
    background: var(--slate-50);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    transition: background .15s;
    overflow: hidden; /* Body scrollt selbst */
}
.kanban-col.is-over { background: var(--thoxan-50); outline: 2px dashed var(--thoxan-400); }
.kanban-col-head {
    padding: 10px 12px 8px;
    background: #fff;
    border-radius: 8px 8px 0 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.kanban-col-body { padding: 8px; display: flex; flex-direction: column; gap: 6px; flex: 1 1 0; overflow-y: auto; }
.kanban-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    padding: 8px 10px;
    cursor: grab;
    display: block;
    color: inherit;
    text-decoration: none;
    transition: box-shadow .15s, transform .1s;
}
.kanban-card:hover { box-shadow: 0 2px 6px rgba(0,0,0,.06); border-color: var(--thoxan-400); }
.kanban-card:active { cursor: grabbing; transform: scale(.99); }
.kanban-card-kunde { font-weight: 600; font-size: var(--d-fs-xs); color: var(--thoxan-700); }
.kanban-card-domain { font-size: var(--d-fs-sm); margin: 2px 0; word-break: break-all; }
.kanban-card-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 4px; }
.kanban-card-linktext { font-size: var(--d-fs-xs); color: var(--slate-600); margin-top: 4px; font-style: italic; }
.kanban-empty { color: var(--slate-400); font-size: var(--d-fs-xs); text-align: center; padding: 12px; }
.kanban-flash {
    position: fixed; bottom: 20px; right: 20px;
    background: var(--emerald-700); color: #fff;
    padding: 10px 16px; border-radius: 6px;
    font-size: var(--d-fs-sm); z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.kanban-flash.is-error { background: var(--rose-600); }
</style>

<script>
function lamKanban() {
    return {
        rows: [],
        kunden: [],
        filter: { suche: '', customer_id: '' },
        dragged: null,
        dragOverSpalte: null,
        flash: { text: '', typ: 'ok' },
        // 7 Status laut Briefing 01b: Idee → Akquise → Bei Kunde → Beauftragt → Bei Anbieter → Live → Archiv
        // Aktive Spalten in der Mitte kräftig, passive (Idee/Archiv) gedämpft.
        spalten: [
            { key: 'idee',         titel: 'Idee',          farbe: '#cbd5e1' },
            { key: 'akquise',      titel: 'Akquise',       farbe: '#0ea5e9' },
            { key: 'bei_kunde',    titel: 'Beim Kunden',   farbe: '#8b5cf6' },
            { key: 'beauftragt',   titel: 'Beauftragt',    farbe: '#f59e0b' },
            { key: 'bei_anbieter', titel: 'Beim Anbieter', farbe: '#3b82f6' },
            { key: 'live',         titel: 'Live',          farbe: '#10b981' },
            { key: 'archiv',       titel: 'Archiv',        farbe: '#94a3b8' },
        ],
        vorgangstypLabels: {
            erstveroeffentlichung: 'Erstveröffentlichung',
            re_veroeffentlichung:  'Re-Veröffentlichung',
            sammelbuchung:         'Sammelbuchung',
            nachbuchung:           'Nachbuchung',
        },
        vorgangstypLabel(s) { return this.vorgangstypLabels[s] || s; },
        formatDatum(d) {
            if (!d) return '';
            const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}.${m[2]}.${m[1]}` : d;
        },

        get byStatus() {
            const map = {};
            this.spalten.forEach(s => map[s.key] = []);
            this.rows.forEach(m => {
                const k = (m.status || '').toLowerCase();
                if (map[k]) map[k].push(m);
                else (map['idee'] = map['idee'] || []).push(m);
            });
            return map;
        },

        async laden() {
            if (this.kunden.length === 0) {
                const rk = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                const jk = await rk.json();
                this.kunden = jk.success ? jk.data : [];
            }
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
            p.set('limit', '500'); // Kanban zeigt alle Spalten — kein Pagination-Limit
            const r = await fetch('/api/v1/lam/massnahmen?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            this.rows = j.success ? (j.data.rows || j.data) : [];
        },

        dragStart(ev, m) {
            this.dragged = m;
            ev.dataTransfer.effectAllowed = 'move';
            try { ev.dataTransfer.setData('text/plain', m.id); } catch (e) {}
        },
        dragEnd() {
            this.dragged = null;
            this.dragOverSpalte = null;
        },
        async drop(ev, neuerStatus) {
            ev.preventDefault();
            this.dragOverSpalte = null;
            if (!this.dragged) return;
            const m = this.dragged;
            const alterStatus = m.status;
            if (alterStatus === neuerStatus) return;
            // optimistic update
            m.status = neuerStatus;
            try {
                const r = await fetch('/api/v1/lam/massnahme-inline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, feld: 'status', wert: neuerStatus }),
                });
                const j = await r.json();
                if (!j.success) {
                    m.status = alterStatus;
                    this.zeigeFlash('Statuswechsel fehlgeschlagen: ' + (j.error || ''), 'error');
                } else {
                    this.zeigeFlash('Status geändert auf "' + neuerStatus + '"');
                }
            } catch (e) {
                m.status = alterStatus;
                this.zeigeFlash('Verbindungsfehler', 'error');
            }
        },
        zeigeFlash(text, typ = 'ok') {
            this.flash.text = text;
            this.flash.typ = typ;
            setTimeout(() => { this.flash.text = ''; }, 3000);
        },
    };
}
</script>
