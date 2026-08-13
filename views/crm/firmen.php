<?php $activeModul = 'firmen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="margin-bottom:8px;">
    <div>
        <h1 class="thx-page-title">Firmen</h1>
        <div class="thx-page-subtitle">Alle Firmen mit verknüpften Kontakten · Rechtsklick = Schnellaktionen</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmFirmen()" x-init="initial()" x-cloak @click="ctxMenu.offen = false">
    <div class="thx-shell">

        <!-- ─── SIDEBAR ─── -->
        <aside class="thx-shell-side">
            <div class="thx-shell-side-header">
                <span class="thx-shell-side-title">Filter</span>
                <button x-show="hatAktiveFilter()" @click="filterReset()" class="thx-icon-btn" title="Filter zurücksetzen">
                    <span class="material-symbols-rounded">filter_alt_off</span>
                </button>
            </div>

            <div class="thx-shell-side-search">
                <span class="material-symbols-rounded thx-shell-search-icon">search</span>
                <input type="text" class="thx-shell-search-input" x-model.debounce.350ms="filter.suche" placeholder="Firmenname, Branche, Website …">
            </div>

            <div class="thx-shell-side-content">

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">domain</span>Branche</div>
                    <div class="thx-shell-chips thx-shell-chips-scroll">
                        <template x-for="b in branchen" :key="b.branche">
                            <button type="button" class="thx-shell-chip" :class="filter.branche.includes(b.branche) ? 'is-active' : ''"
                                    @click="toggleMulti('branche', b.branche, $event)">
                                <span x-text="b.branche"></span>
                                <span class="thx-shell-chip-count" x-text="b.anzahl"></span>
                            </button>
                        </template>
                        <template x-if="branchen.length === 0"><span style="font-size:0.78rem;color:var(--slate-400);">Keine Branchen vergeben.</span></template>
                    </div>
                </div>

                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">tune</span>Sonderfilter</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="filter.mit_kontakten ? 'is-active' : ''" @click="filter.mit_kontakten = !filter.mit_kontakten; filter.ohne_kontakte = false; laden(true)">mit Kontakten</button>
                        <button type="button" class="thx-shell-chip" :class="filter.ohne_kontakte ? 'is-active' : ''" @click="filter.ohne_kontakte = !filter.ohne_kontakte; filter.mit_kontakten = false; laden(true)">ohne Kontakte</button>
                        <button type="button" class="thx-shell-chip" :class="filter.mit_zoho_legacy ? 'is-active' : ''" @click="filter.mit_zoho_legacy = !filter.mit_zoho_legacy; laden(true)">aus Zoho</button>
                    </div>
                </div>

            </div>
        </aside>

        <!-- ─── MAIN ─── -->
        <main class="thx-shell-main">
            <div class="thx-shell-toolbar">
                <div style="font-size:0.85rem;color:var(--slate-600);">
                    <strong x-text="gesamt.toLocaleString('de-DE')"></strong> Firma(en)
                    <span x-show="eintraege.length > 0" style="color:var(--slate-400);">· <span x-text="(offset + 1) + '–' + Math.min(offset + eintraege.length, gesamt)"></span></span>
                    <span x-show="hatAktiveFilter()" style="color:var(--thoxan-600);margin-left:6px;display:inline-flex;align-items:center;gap:3px;"><span class="material-symbols-rounded" style="font-size:14px;">filter_alt</span>gefiltert</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <select x-model.number="limit" @change="laden(true)" class="thx-shell-select" style="font-size:0.78rem;padding:4px 8px;width:auto;">
                        <option :value="25">25 / Seite</option>
                        <option :value="50">50 / Seite</option>
                        <option :value="100">100 / Seite</option>
                        <option :value="200">200 / Seite</option>
                    </select>
                </div>
            </div>

            <div x-show="laedt" style="padding:30px;text-align:center;color:var(--slate-400);">Lade …</div>
            <template x-if="!laedt && eintraege.length === 0">
                <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">Keine Firmen gefunden.</div>
            </template>

            <template x-if="!laedt && eintraege.length > 0">
                <div class="thx-shell-table-wrap">
                    <table class="thx-shell-table">
                        <thead>
                            <tr>
                                <th class="sortable" :class="sortKlasse('firmenname')" @click="sortBy('firmenname')">Firmenname <span class="sort-icon" x-text="sortPfeil('firmenname')"></span></th>
                                <th class="sortable" :class="sortKlasse('branche')" @click="sortBy('branche')">Branche <span class="sort-icon" x-text="sortPfeil('branche')"></span></th>
                                <th>Website</th>
                                <th>Telefon</th>
                                <th class="center sortable" :class="sortKlasse('beschaeftigte')" @click="sortBy('beschaeftigte')">Beschäftigte <span class="sort-icon" x-text="sortPfeil('beschaeftigte')"></span></th>
                                <th class="center sortable" :class="sortKlasse('kontakte')" @click="sortBy('kontakte')">Kontakte <span class="sort-icon" x-text="sortPfeil('kontakte')"></span></th>
                                <th class="sortable" :class="sortKlasse('geaendert_am')" @click="sortBy('geaendert_am')">Geändert <span class="sort-icon" x-text="sortPfeil('geaendert_am')"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="f in eintraege" :key="f.id">
                                <tr @contextmenu.prevent="oeffneCtxMenu($event, f)">
                                    <td class="thx-row-clickable" @click="oeffneDetail(f.id)" style="font-weight:500;color:var(--slate-900);" x-text="f.firmenname"></td>
                                    <td x-text="f.branche || ''" style="color:var(--slate-500);"></td>
                                    <td>
                                        <a x-show="f.website" :href="f.website" target="_blank" rel="noopener" @click.stop style="color:var(--thoxan-600);" x-text="f.website"></a>
                                        <span x-show="!f.website" style="color:var(--slate-300);">—</span>
                                    </td>
                                    <td style="font-size:0.78rem;">
                                        <a x-show="f.telefon" :href="'tel:' + f.telefon" @click.stop style="color:var(--slate-700);" x-text="f.telefon"></a>
                                        <span x-show="!f.telefon" style="color:var(--slate-300);">—</span>
                                    </td>
                                    <td class="center" style="font-variant-numeric:tabular-nums;color:var(--slate-600);" x-text="f.beschaeftigte || '—'"></td>
                                    <td class="center">
                                        <a x-show="f.anzahl_kontakte > 0" :href="'/crm/kontakte?firma_id=' + f.id" @click.stop style="font-weight:500;color:var(--thoxan-600);" x-text="f.anzahl_kontakte"></a>
                                        <span x-show="!f.anzahl_kontakte" style="color:var(--slate-300);">0</span>
                                    </td>
                                    <td style="font-size:0.78rem;color:var(--slate-500);font-variant-numeric:tabular-nums;" x-text="formatDate(f.geaendert_am || f.erstellt_am)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <div x-show="!laedt && gesamt > limit" style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;color:var(--slate-500);">
                <button class="thx-shell-btn" :disabled="offset === 0" @click="seitenZurueck()">‹ Zurück</button>
                <span>Seite <strong x-text="Math.floor(offset/limit) + 1"></strong> von <strong x-text="Math.ceil(gesamt/limit)"></strong></span>
                <button class="thx-shell-btn" :disabled="(offset + limit) >= gesamt" @click="seitenVor()">Weiter ›</button>
            </div>
        </main>
    </div>

    <!-- Rechtsklick-Menü -->
    <div x-show="ctxMenu.offen" x-cloak class="thx-contextmenu" :style="'top:' + ctxMenu.y + 'px;left:' + ctxMenu.x + 'px;'" @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.firma?.firmenname || ''"></div>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item" @click="oeffneDetail(ctxMenu.firma.id); ctxMenu.offen = false">📂 Detail öffnen</button>
        <a x-show="ctxMenu.firma?.website" class="thx-contextmenu-item" :href="ctxMenu.firma?.website" target="_blank" rel="noopener" @click="ctxMenu.offen = false">🌐 Website öffnen</a>
        <a x-show="ctxMenu.firma?.telefon" class="thx-contextmenu-item" :href="'tel:' + ctxMenu.firma?.telefon" @click="ctxMenu.offen = false">📞 Anrufen</a>
        <a x-show="ctxMenu.firma?.anzahl_kontakte > 0" class="thx-contextmenu-item" :href="'/crm/kontakte?firma_id=' + ctxMenu.firma?.id" @click="ctxMenu.offen = false">👥 Kontakte zeigen</a>
    </div>
</div>

<style>
.thx-contextmenu { position:fixed; background:#fff; border:1px solid var(--slate-300); border-radius:6px; box-shadow:0 8px 22px rgba(0,0,0,0.15); padding:4px; z-index:1100; min-width:200px; }
.thx-contextmenu-item { display:block; width:100%; text-align:left; padding:5px 10px; background:none; border:0; cursor:pointer; font:inherit; font-size:0.82rem; color:inherit; border-radius:4px; text-decoration:none; }
.thx-contextmenu-item:hover { background:var(--thoxan-50); }
.thx-contextmenu-label { padding:5px 10px; font-size:0.7rem; color:var(--slate-500); text-transform:uppercase; letter-spacing:0.04em; font-weight:600; }
.thx-contextmenu-divider { border-top:1px solid var(--slate-200); margin:3px 0; }
</style>

<script>
function crmFirmen() {
    return {
        eintraege: [], gesamt: 0, laedt: false,
        limit: 50, offset: 0, sort: 'firmenname', order: 'asc',
        branchen: [],
        filter: { suche:'', branche:[], mit_kontakten:false, ohne_kontakte:false, mit_zoho_legacy:false },
        ctxMenu: { offen: false, x: 0, y: 0, firma: null },

        async initial() {
            this.$watch('filter.suche', () => this.laden(true));
            await this.ladeBranchen();
            this.laden();
        },
        async ladeBranchen() {
            try {
                const r = await fetch('/api/v1/crm/firmen?branchen=1');
                const j = await r.json();
                if (j.success) this.branchen = j.data.branchen || [];
            } catch (e) {}
        },
        hatAktiveFilter() {
            const f = this.filter;
            return !!(f.suche || f.branche.length || f.mit_kontakten || f.ohne_kontakte || f.mit_zoho_legacy);
        },
        toggleMulti(feld, wert, ev) {
            const arr = this.filter[feld];
            const idx = arr.indexOf(wert);
            const additiv = ev && (ev.shiftKey || ev.ctrlKey || ev.metaKey);
            if (additiv) { if (idx >= 0) arr.splice(idx, 1); else arr.push(wert); }
            else { if (arr.length === 1 && idx === 0) this.filter[feld] = []; else this.filter[feld] = [wert]; }
            this.laden(true);
        },
        async laden(reset = false) {
            if (reset) this.offset = 0;
            this.laedt = true;
            try {
                const p = new URLSearchParams();
                p.set('limit', this.limit); p.set('offset', this.offset);
                p.set('sort', this.sort); p.set('order', this.order);
                if (this.filter.suche) p.set('suche', this.filter.suche);
                this.filter.branche.forEach(v => p.append('branche[]', v));
                if (this.filter.mit_kontakten) p.set('mit_kontakten', '1');
                if (this.filter.ohne_kontakte) p.set('ohne_kontakte', '1');
                if (this.filter.mit_zoho_legacy) p.set('mit_zoho_legacy', '1');
                const r = await fetch('/api/v1/crm/firmen?' + p);
                const j = await r.json();
                if (j.success) { this.eintraege = j.data.eintraege || []; this.gesamt = j.data.gesamt || 0; }
            } catch (e) {}
            this.laedt = false;
        },
        filterReset() {
            this.filter = { suche:'', branche:[], mit_kontakten:false, ohne_kontakte:false, mit_zoho_legacy:false };
            this.laden(true);
        },
        oeffneDetail(id) { window.location.href = '/crm/firmen/' + id; },
        oeffneCtxMenu(ev, firma) { this.ctxMenu = { offen: true, x: ev.clientX, y: ev.clientY, firma }; },
        sortBy(feld) {
            if (this.sort === feld) this.order = this.order === 'asc' ? 'desc' : 'asc';
            else { this.sort = feld; this.order = 'asc'; }
            this.laden(true);
        },
        sortKlasse(feld) { return this.sort === feld ? ('is-sorted-' + this.order) : ''; },
        sortPfeil(feld) { return this.sort !== feld ? '' : (this.order === 'asc' ? '↑' : '↓'); },
        seitenVor() { this.offset += this.limit; this.laden(); window.scrollTo(0,0); },
        seitenZurueck() { this.offset = Math.max(0, this.offset - this.limit); this.laden(); window.scrollTo(0,0); },
        formatDate(d) { if (!d) return ''; return new Date(d.replace(' ','T')).toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit' }); },
    };
}
</script>
