<?php $activeModul = 'tags'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamTags()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Tags</h1>
        <div class="thx-page-subtitle">Themen-Tags für Linkquellen (Stammdaten).</div>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="lam-btn lam-btn-secondary" @click="oeffneMerge()" :disabled="auswahl.length !== 2">
            ⇆ Zusammenführen <span x-show="auswahl.length === 2" x-text="'(' + auswahl.length + ')'"></span>
        </button>
        <button class="lam-btn lam-btn-primary" @click="oeffneAnlegen()">+ Neuer Tag</button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-head">
        <h2>Filter</h2>
        <span style="font-size:var(--d-fs-xs);color:var(--slate-400);" x-text="tags.length ? (tags.length + ' Tags') : ''"></span>
    </div>
    <div class="lam-filter-grid">
        <div class="lam-filter-col-9">
            <label class="lam-filter-label">Suche</label>
            <input type="text" class="lam-filter-input" placeholder="Name oder Beschreibung …"
                   x-model="filter.suche" @input.debounce.300ms="laden()">
        </div>
        <div class="lam-filter-col-3">
            <label class="lam-filter-label">&nbsp;</label>
            <button class="lam-chip" :class="filter.nur_unbenutzt ? 'is-active' : ''"
                    @click="filter.nur_unbenutzt = !filter.nur_unbenutzt; laden()">nur unbenutzt</button>
        </div>
    </div>
</section>

<section class="lam-table-card">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Beschreibung</th>
                    <th class="right">Domains</th>
                    <th>Erstellt</th>
                    <th class="right" style="width:140px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="t in tags" :key="t.id">
                    <tr>
                        <td>
                            <input type="checkbox" :value="t.id" :checked="auswahl.includes(t.id)" @change="toggleAuswahl(t.id)">
                        </td>
                        <td><strong x-text="t.name"></strong></td>
                        <td><code x-text="t.slug"></code></td>
                        <td class="muted" x-text="t.beschreibung || '—'"></td>
                        <td class="right" x-text="t.verwendungs_zahl || 0"></td>
                        <td class="muted" x-text="t.erstellt_am ? t.erstellt_am.substring(0,10) : '—'"></td>
                        <td class="right">
                            <button class="lam-btn lam-btn-sm" @click="oeffneBearbeiten(t)">bearbeiten</button>
                            <button class="lam-btn lam-btn-sm lam-btn-danger" @click="loeschen(t)" :disabled="parseInt(t.verwendungs_zahl) > 0">löschen</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && tags.length === 0">Keine Tags.</div>
        <div class="lam-loading" x-show="laedt && tags.length === 0"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

<!-- Drawer: Neu / Bearbeiten -->
<div class="thx-drawer-backdrop" x-show="drawer.offen" x-transition.opacity @click.self="schliesseDrawer()">
    <div class="thx-drawer">
        <div class="thx-drawer-header">
            <h2 x-text="drawer.form.id ? 'Tag bearbeiten' : 'Neuer Tag'"></h2>
            <button class="thx-icon-btn" @click="schliesseDrawer()">✕</button>
        </div>
        <div class="thx-drawer-body">
            <div class="thx-form-field">
                <label>Name *</label>
                <input type="text" x-model="drawer.form.name" placeholder="z.B. Gesundheit">
            </div>
            <div class="thx-form-field">
                <label>Slug (optional, wird sonst aus Name erzeugt)</label>
                <input type="text" x-model="drawer.form.slug" placeholder="gesundheit">
            </div>
            <div class="thx-form-field">
                <label>Beschreibung</label>
                <textarea x-model="drawer.form.beschreibung" rows="3" placeholder="kurze Erläuterung, was unter diesen Tag fällt …"></textarea>
            </div>
            <div x-show="drawer.fehler" class="lam-flash lam-flash-fehler" x-text="drawer.fehler"></div>
        </div>
        <div class="thx-drawer-footer">
            <button class="lam-btn lam-btn-secondary" @click="schliesseDrawer()">Abbrechen</button>
            <button class="lam-btn lam-btn-primary" @click="speichern()" :disabled="drawer.laedt">
                <span x-show="!drawer.laedt">Speichern</span>
                <span x-show="drawer.laedt">…</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Merge -->
<div class="thx-modal-backdrop" x-show="merge.offen" x-transition.opacity @click.self="merge.offen = false">
    <div class="thx-modal" style="max-width:520px;">
        <div class="thx-modal-header">
            <h2>Tags zusammenführen</h2>
            <button class="thx-icon-btn" @click="merge.offen = false">✕</button>
        </div>
        <div class="thx-modal-body">
            <p style="margin-top:0;">Wähle, welcher Tag erhalten bleibt. Der andere wird gelöscht; alle Domain-Zuweisungen wandern zum Ziel-Tag.</p>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <template x-for="id in auswahl" :key="id">
                    <label style="display:flex;align-items:center;gap:10px;padding:10px;border:1px solid var(--slate-200);border-radius:8px;cursor:pointer;"
                           :style="merge.target === id ? 'border-color:var(--thoxan-500);background:var(--thoxan-50);' : ''">
                        <input type="radio" :value="id" x-model.number="merge.target">
                        <span x-text="tagsById[id]?.name + ' (' + (tagsById[id]?.verwendungs_zahl || 0) + ' Domains)'"></span>
                    </label>
                </template>
            </div>
            <div x-show="merge.fehler" class="lam-flash lam-flash-fehler" style="margin-top:10px;" x-text="merge.fehler"></div>
        </div>
        <div class="thx-modal-footer">
            <button class="lam-btn lam-btn-secondary" @click="merge.offen = false">Abbrechen</button>
            <button class="lam-btn lam-btn-primary" @click="merged()" :disabled="!merge.target || merge.laedt">
                <span x-show="!merge.laedt">Zusammenführen</span>
                <span x-show="merge.laedt">…</span>
            </button>
        </div>
    </div>
</div>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamTags() {
    return {
        laedt: false,
        tags: [],
        filter: { suche: '', nur_unbenutzt: false },
        auswahl: [],
        drawer: { offen: false, form: {}, laedt: false, fehler: '' },
        merge: { offen: false, target: null, laedt: false, fehler: '' },

        get tagsById() {
            const map = {};
            this.tags.forEach(t => map[t.id] = t);
            return map;
        },

        async laden() {
            this.laedt = true;
            try {
                const p = new URLSearchParams();
                if (this.filter.suche) p.set('suche', this.filter.suche);
                if (this.filter.nur_unbenutzt) p.set('nur_unbenutzt', '1');
                const r = await fetch('/api/v1/lam/tags?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.tags = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },

        toggleAuswahl(id) {
            const i = this.auswahl.indexOf(id);
            if (i >= 0) this.auswahl.splice(i, 1);
            else if (this.auswahl.length < 2) this.auswahl.push(id);
            else alert('Bitte maximal zwei Tags für die Zusammenführung auswählen.');
        },

        oeffneAnlegen() {
            this.drawer.form = { name: '', slug: '', beschreibung: '' };
            this.drawer.fehler = '';
            this.drawer.offen = true;
        },

        oeffneBearbeiten(t) {
            this.drawer.form = { id: t.id, name: t.name, slug: t.slug, beschreibung: t.beschreibung || '' };
            this.drawer.fehler = '';
            this.drawer.offen = true;
        },

        schliesseDrawer() {
            this.drawer.offen = false;
            this.drawer.fehler = '';
        },

        async speichern() {
            if (!this.drawer.form.name?.trim()) {
                this.drawer.fehler = 'Name erforderlich.';
                return;
            }
            this.drawer.laedt = true;
            this.drawer.fehler = '';
            try {
                const r = await fetch('/api/v1/lam/tag-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.drawer.form),
                });
                const j = await r.json();
                if (!j.success) { this.drawer.fehler = j.error || 'Fehler.'; return; }
                this.drawer.offen = false;
                await this.laden();
            } finally { this.drawer.laedt = false; }
        },

        async loeschen(t) {
            if (parseInt(t.verwendungs_zahl || 0) > 0) {
                alert('Dieser Tag wird noch verwendet. Bitte zuerst zusammenführen oder von Domains entfernen.');
                return;
            }
            if (!confirm('Tag "' + t.name + '" löschen?')) return;
            const r = await fetch('/api/v1/lam/tag-loeschen', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: t.id }),
            });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Löschen fehlgeschlagen.'); return; }
            this.auswahl = this.auswahl.filter(id => id !== t.id);
            await this.laden();
        },

        oeffneMerge() {
            if (this.auswahl.length !== 2) return;
            this.merge.target = null;
            this.merge.fehler = '';
            this.merge.offen = true;
        },

        async merged() {
            const source = this.auswahl.find(id => id !== this.merge.target);
            if (!source || !this.merge.target) return;
            this.merge.laedt = true;
            this.merge.fehler = '';
            try {
                const r = await fetch('/api/v1/lam/tag-merge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ source_id: source, target_id: this.merge.target }),
                });
                const j = await r.json();
                if (!j.success) { this.merge.fehler = j.error || 'Fehler.'; return; }
                this.merge.offen = false;
                this.auswahl = [];
                await this.laden();
            } finally { this.merge.laedt = false; }
        },
    };
}
</script>
