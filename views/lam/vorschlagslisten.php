<?php
/**
 * Vorschlagslisten-Übersicht — pro Kunde verwaltbar.
 */
$activeModul = 'linkoptionen';
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamVorschlagslisten()" x-init="laden()">

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Vorschlagslisten</h1>
        <div class="thx-page-subtitle">Kuratierte Listen pro Kunde — Pool von potenziellen Linkquellen mit Status-Pipeline.</div>
    </div>
    <button class="lam-btn lam-btn-primary" @click="oeffneNeu()">+ Neue Liste</button>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Suche</label>
            <input type="text" class="lam-filter-input" x-model="filter.suche" @input.debounce.300ms="laden()">
        </div>
        <div class="lam-filter-col-6">
            <label class="lam-filter-label">Status</label>
            <div class="lam-chip-row">
                <button class="lam-chip lam-chip-reset" :class="filter.status === '' ? 'is-active' : ''" @click="filter.status = ''; laden()">alle</button>
                <template x-for="s in statusListe" :key="s">
                    <button class="lam-chip" :class="filter.status === s ? 'is-active' : ''" @click="filter.status = s; laden()" x-text="s"></button>
                </template>
            </div>
        </div>
    </div>
</section>

<section class="lam-table-card">
    <div class="lam-table-wrap">
        <table class="lam-table">
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th class="right">Einträge</th>
                    <th class="right">davon Maßnahmen</th>
                    <th class="right">Ziel</th>
                    <th>Angelegt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="l in rows" :key="l.id">
                    <tr>
                        <td><strong x-text="l.customer_kuerzel || l.customer_name"></strong></td>
                        <td>
                            <a :href="'/lam/vorschlagslisten/' + encodeURIComponent(l.id)" style="color:var(--thoxan-700);font-weight:600;" x-text="l.name"></a>
                            <div x-show="l.notiz" class="muted" style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;" x-text="l.notiz"></div>
                        </td>
                        <td><span class="lam-badge" x-text="l.status"></span></td>
                        <td class="right" x-text="l.eintrag_count"></td>
                        <td class="right" x-text="l.massnahme_count"></td>
                        <td class="right" x-text="l.zielzahl ?? '—'"></td>
                        <td x-text="formatDatum(l.erstellt_am)"></td>
                        <td>
                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneEdit(l)">✎</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Listen.</div>
        <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
    </div>
</section>

<!-- Drawer: Anlegen/Bearbeiten -->
<div class="thx-drawer-backdrop" x-show="drawer.offen" @click.self="drawer.offen = false" x-cloak>
    <div class="thx-drawer">
        <div class="thx-drawer-header">
            <h2 class="thx-drawer-title" x-text="drawer.id ? 'Liste bearbeiten' : 'Neue Liste'"></h2>
            <button class="thx-modal-close" @click="drawer.offen = false">×</button>
        </div>
        <div class="thx-drawer-body">
            <div class="thx-form-field">
                <label>Kunde *</label>
                <select x-model="drawer.customer_id" :disabled="!!drawer.id">
                    <option value="">— wählen —</option>
                    <template x-for="k in kundenListe" :key="k.id">
                        <option :value="k.id" x-text="(k.kuerzel ? k.kuerzel + ' · ' : '') + k.name"></option>
                    </template>
                </select>
            </div>
            <div class="thx-form-field">
                <label>Name der Liste *</label>
                <input type="text" x-model="drawer.name" placeholder="z.B. Q3 Outreach Familienkasse">
            </div>
            <div class="thx-form-field">
                <label>Status</label>
                <select x-model="drawer.status">
                    <template x-for="s in statusListe" :key="s"><option :value="s" x-text="s"></option></template>
                </select>
            </div>
            <div class="thx-form-field">
                <label>Zielzahl (Backlinks, optional)</label>
                <input type="number" x-model="drawer.zielzahl" min="1">
            </div>
            <div class="thx-form-field">
                <label>Notiz</label>
                <textarea x-model="drawer.notiz" rows="3"></textarea>
            </div>
        </div>
        <div class="thx-drawer-footer">
            <button class="lam-btn lam-btn-secondary" @click="drawer.offen = false">Abbrechen</button>
            <button x-show="drawer.id" class="lam-btn" style="color:var(--rose-700);" @click="loesche()">Löschen</button>
            <button class="lam-btn lam-btn-primary" @click="speichere()" :disabled="drawer.laeuft || !drawer.name || !drawer.customer_id">
                <span x-show="!drawer.laeuft">Speichern</span><span x-show="drawer.laeuft">…</span>
            </button>
        </div>
    </div>
</div>

</div>

<style>[x-cloak]{display:none!important;}</style>

<script>
function lamVorschlagslisten() {
    return {
        laedt: true, rows: [], filter: { suche: '', status: '' },
        statusListe: ['entwurf','aktiv','abgeschlossen','archiviert'],
        kundenListe: [],
        drawer: { offen: false, laeuft: false, id: null, customer_id: '', name: '', status: 'entwurf', zielzahl: '', notiz: '' },

        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.status) p.set('status', this.filter.status);
            try {
                const r = await fetch('/api/v1/lam/vorschlagslisten?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.rows = j.success ? j.data : [];
            } finally { this.laedt = false; }
            if (this.kundenListe.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.kundenListe = j.data || [];
                } catch (e) {}
            }
        },
        oeffneNeu() {
            this.drawer = { offen: true, laeuft: false, id: null, customer_id: '', name: '', status: 'entwurf', zielzahl: '', notiz: '' };
        },
        oeffneEdit(l) {
            this.drawer = {
                offen: true, laeuft: false,
                id: l.id, customer_id: l.customer_id,
                name: l.name, status: l.status,
                zielzahl: l.zielzahl ?? '', notiz: l.notiz ?? '',
            };
        },
        async speichere() {
            this.drawer.laeuft = true;
            try {
                const payload = {
                    id: this.drawer.id,
                    customer_id: this.drawer.customer_id,
                    name: this.drawer.name,
                    status: this.drawer.status,
                    zielzahl: this.drawer.zielzahl || null,
                    notiz: this.drawer.notiz || null,
                };
                const r = await fetch('/api/v1/lam/vorschlagsliste-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const j = await r.json();
                if (j.success) {
                    this.drawer.offen = false;
                    this.laden();
                } else { alert(j.message || 'Fehler'); }
            } catch (e) { alert('Verbindungsfehler'); }
            finally { this.drawer.laeuft = false; }
        },
        async loesche() {
            if (!confirm('Liste wirklich löschen? Die Einträge bleiben in der DB, sind aber nicht mehr verlinkt.')) return;
            await fetch('/api/v1/lam/vorschlagsliste-loeschen', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: this.drawer.id }),
            });
            this.drawer.offen = false;
            this.laden();
        },
        formatDatum(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        },
    };
}
</script>
