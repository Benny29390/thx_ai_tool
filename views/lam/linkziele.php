<?php $activeModul = 'linkziele'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamLinkziele()" x-init="laden()" x-cloak>

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Linkziele</h1>
        <div class="thx-page-subtitle">Pro Kunde: Themen, Ziel-URLs und bevorzugte Linktexte als Stammdaten.</div>
    </div>
    <button class="lam-btn lam-btn-primary" @click="oeffneAnlegen()">+ Neues Linkziel</button>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-head">
        <h2>Filter</h2>
        <span style="font-size:var(--d-fs-xs);color:var(--slate-400);" x-text="rows.length ? (rows.length + ' Linkziele') : ''"></span>
    </div>
    <div class="lam-filter-grid">
        <div class="lam-filter-col-4">
            <label class="lam-filter-label">Kunde</label>
            <select class="lam-filter-select" x-model="filter.customer_id" @change="laden()">
                <option value="">alle Kunden</option>
                <template x-for="k in kunden" :key="k.id">
                    <option :value="k.id" x-text="(k.abbreviation || k.name) + ' — ' + k.name"></option>
                </template>
            </select>
        </div>
        <div class="lam-filter-col-4">
            <label class="lam-filter-label">Status</label>
            <div class="lam-chip-row">
                <button class="lam-chip lam-chip-reset" :class="filter.status === '' ? 'is-active' : ''" @click="filter.status = ''; laden()">alle</button>
                <button class="lam-chip" :class="filter.status === 'aktiv' ? 'is-active' : ''" @click="filter.status = 'aktiv'; laden()">aktiv</button>
                <button class="lam-chip" :class="filter.status === 'pausiert' ? 'is-active' : ''" @click="filter.status = 'pausiert'; laden()">pausiert</button>
                <button class="lam-chip" :class="filter.status === 'archiviert' ? 'is-active' : ''" @click="filter.status = 'archiviert'; laden()">archiviert</button>
            </div>
        </div>
        <div class="lam-filter-col-4">
            <label class="lam-filter-label">Suche</label>
            <input type="text" class="lam-filter-input" placeholder="URL, Thema oder Linktext …"
                   x-model="filter.suche" @input.debounce.300ms="laden()">
        </div>
    </div>
</section>

<section class="lam-table-card">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th>Thema</th>
                    <th>Ziel-URL</th>
                    <th>Bevorzugter Linktext</th>
                    <th>Status</th>
                    <th class="right" style="width:140px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="lz in rows" :key="lz.id">
                    <tr class="thx-row-clickable" @click="oeffneBearbeiten(lz)">
                        <td><strong x-text="lz.customer_kuerzel || '—'"></strong></td>
                        <td x-text="lz.thema || '—'"></td>
                        <td class="url-cell"><a :href="lz.url" target="_blank" rel="noopener" style="color:var(--thoxan-700);" @click.stop x-text="lz.url"></a></td>
                        <td x-text="lz.bevorzugter_linktext || '—'"></td>
                        <td>
                            <span class="lam-badge" :style="statusStyle(lz.status)" x-text="lz.status"></span>
                        </td>
                        <td class="right" @click.stop>
                            <button class="lam-btn lam-btn-sm" @click="oeffneBearbeiten(lz)">bearbeiten</button>
                            <button class="lam-btn lam-btn-sm lam-btn-danger" @click="loeschen(lz)">löschen</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Linkziele.</div>
        <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

<!-- Drawer -->
<div class="thx-drawer-backdrop" x-show="drawer.offen" x-transition.opacity @click.self="schliesseDrawer()">
    <div class="thx-drawer">
        <div class="thx-drawer-header">
            <h2 x-text="drawer.form.id ? 'Linkziel bearbeiten' : 'Neues Linkziel'"></h2>
            <button class="thx-icon-btn" @click="schliesseDrawer()">✕</button>
        </div>
        <div class="thx-drawer-body">
            <div class="thx-form-field">
                <label>Kunde *</label>
                <select x-model="drawer.form.customer_id" :disabled="!!drawer.form.id">
                    <option value="">— bitte wählen —</option>
                    <template x-for="k in kunden" :key="k.id">
                        <option :value="k.id" x-text="(k.abbreviation || k.name) + ' — ' + k.name"></option>
                    </template>
                </select>
            </div>
            <div class="thx-form-field">
                <label>Thema *</label>
                <input type="text" x-model="drawer.form.thema" placeholder="z.B. Vorteile von Solaranlagen">
            </div>
            <div class="thx-form-field">
                <label>Ziel-URL *</label>
                <input type="url" x-model="drawer.form.url" placeholder="https://www.kunde.de/seite">
            </div>
            <div class="thx-form-field">
                <label>Bevorzugter Linktext</label>
                <input type="text" x-model="drawer.form.bevorzugter_linktext" placeholder="z.B. Solaranlage installieren">
            </div>
            <div class="thx-form-field">
                <label>Status</label>
                <select x-model="drawer.form.status">
                    <option value="aktiv">aktiv</option>
                    <option value="pausiert">pausiert</option>
                    <option value="archiviert">archiviert</option>
                </select>
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

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function lamLinkziele() {
    return {
        laedt: false,
        rows: [],
        kunden: [],
        filter: { customer_id: '', status: '', suche: '' },
        drawer: { offen: false, form: {}, laedt: false, fehler: '' },

        async laden() {
            this.laedt = true;
            try {
                if (this.kunden.length === 0) {
                    const rk = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                    const jk = await rk.json();
                    this.kunden = jk.success ? jk.data : [];
                }
                const p = new URLSearchParams();
                if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
                if (this.filter.status) p.set('status', this.filter.status);
                if (this.filter.suche) p.set('suche', this.filter.suche);
                const r = await fetch('/api/v1/lam/linkziele?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
        },

        statusStyle(s) {
            const map = {
                'aktiv': 'background:var(--emerald-100);color:var(--emerald-800);',
                'pausiert': 'background:var(--amber-100);color:#92400e;',
                'archiviert': 'background:var(--slate-200);color:var(--slate-700);',
            };
            return map[s] || 'background:var(--slate-100);color:var(--slate-600);';
        },

        oeffneAnlegen() {
            this.drawer.form = {
                customer_id: this.filter.customer_id || '',
                url: '', thema: '', bevorzugter_linktext: '', status: 'aktiv'
            };
            this.drawer.fehler = '';
            this.drawer.offen = true;
        },
        oeffneBearbeiten(lz) {
            this.drawer.form = {
                id: lz.id,
                customer_id: lz.customer_id,
                url: lz.url, thema: lz.thema,
                bevorzugter_linktext: lz.bevorzugter_linktext || '',
                status: lz.status || 'aktiv',
            };
            this.drawer.fehler = '';
            this.drawer.offen = true;
        },
        schliesseDrawer() { this.drawer.offen = false; this.drawer.fehler = ''; },

        async speichern() {
            const f = this.drawer.form;
            if (!f.customer_id || !f.thema?.trim() || !f.url?.trim()) {
                this.drawer.fehler = 'Kunde, Thema und URL sind Pflichtfelder.';
                return;
            }
            this.drawer.laedt = true;
            try {
                const r = await fetch('/api/v1/lam/linkziel-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(f),
                });
                const j = await r.json();
                if (!j.success) { this.drawer.fehler = j.error || 'Speichern fehlgeschlagen.'; return; }
                this.drawer.offen = false;
                await this.laden();
            } finally { this.drawer.laedt = false; }
        },

        async loeschen(lz) {
            if (!confirm('Linkziel "' + lz.thema + '" löschen?')) return;
            const r = await fetch('/api/v1/lam/linkziel-loeschen', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: lz.id }),
            });
            const j = await r.json();
            if (!j.success) { alert(j.error || 'Löschen fehlgeschlagen.'); return; }
            await this.laden();
        },
    };
}
</script>
