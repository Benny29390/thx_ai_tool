<?php $activeModul = 'massnahmen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamMassnahmen()" x-init="init()" @click="ctxMenu.offen = false">

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Maßnahmen</h1>
        <div class="thx-page-subtitle">Status-Pipeline der konkreten Backlink-Vorhaben pro Kunde.</div>
    </div>
    <div style="display:flex;gap:8px;">
        <a class="lam-btn lam-btn-secondary" href="/lam/massnahmen/kanban">📋 Kanban</a>
        <a class="lam-btn lam-btn-secondary" href="/api/v1/lam/massnahmen-export">📤 CSV-Export</a>
        <button class="lam-btn lam-btn-primary" @click="oeffneAnlegen()">
            <span style="font-size:14px;">+</span> Neue Maßnahme
        </button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                      x-text="totalCount + ' Maßnahmen'"></span>
                <button type="button" @click="filterZuruecksetzen()"
                        style="font-size:0.75rem;color:var(--slate-500);background:none;border:0;cursor:pointer;text-decoration:underline;">
                    zurücksetzen
                </button>
            </div>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Suche (Domain, Linktext, URL)</label>
                <input type="text" class="lam-filter-input"
                       x-model="filter.suche" @input.debounce.300ms="filter.offset = 0; laden()">
            </div>
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Status</label>
                <div class="lam-chip-row">
                    <button class="lam-chip lam-chip-reset" :class="filter.status === '' ? 'is-active' : ''" @click="filter.status = ''; filter.offset = 0; laden()">alle</button>
                    <template x-for="s in statusListe" :key="s.slug">
                        <button class="lam-chip" :class="filter.status === s.slug ? 'is-active' : ''" @click="filter.status = s.slug; filter.offset = 0; laden()" x-text="s.label"></button>
                    </template>
                </div>
            </div>
        </div>
    </section>

    <!-- Bulk-Toolbar -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
        <span class="thx-bulk-count"><span x-text="auswahl.size"></span> ausgewählt</span>
        <span class="thx-divider"></span>
        <select x-model="bulkAktion" class="lam-filter-select" style="width:auto;">
            <option value="">Aktion …</option>
            <option value="status_setzen">Status setzen</option>
            <option value="loeschen">Löschen (soft)</option>
        </select>
        <select x-show="bulkAktion === 'status_setzen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Status —</option>
            <template x-for="s in statusListe" :key="s.slug"><option :value="s.slug" x-text="s.label"></option></template>
        </select>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="bulkAusfuehren()" :disabled="bulkLaeuft || !bulkAktion || (bulkAktion !== 'loeschen' && !bulkWert)">
            <span x-show="!bulkLaeuft">Anwenden</span><span x-show="bulkLaeuft">…</span>
        </button>
        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th class="thx-bulk-col">
                            <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                        </th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('kunde')">Kunde <span class="sort-icon" x-text="sortPfeil('kunde')"></span></th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('domain')">Domain <span class="sort-icon" x-text="sortPfeil('domain')"></span></th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('status')">Status <span class="sort-icon" x-text="sortPfeil('status')"></span></th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('veroeffentlicht')">Veröffentlicht <span class="sort-icon" x-text="sortPfeil('veroeffentlicht')"></span></th>
                        <th>Veröffentlichungs-URL</th>
                        <th>Linktext</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="m in rows" :key="m.id">
                        <tr :class="auswahl.has(m.id) ? 'is-bulk-selected' : ''" @contextmenu.prevent="oeffneCtxMenu($event, m)">
                            <td class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(m.id)" @change="toggleAuswahl(m.id)" @click.stop>
                            </td>
                            <td><strong x-text="m.customer_kuerzel"></strong></td>
                            <td class="url-cell">
                                <a :href="'/lam/massnahmen/' + encodeURIComponent(m.id)" x-text="m.domain_url" style="color:var(--thoxan-700);"></a>
                                <a x-show="m.domain_url" :href="extUrl(m.domain_url)" target="_blank" rel="noopener" @click.stop
                                   title="Website in neuem Tab öffnen" style="color:var(--slate-400);text-decoration:none;padding:0 4px;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">open_in_new</span>
                                </a>
                            </td>
                            <!-- Status Inline-Edit -->
                            <td>
                                <template x-if="!istOffen(m.id, 'status')">
                                    <button class="thx-inline-edit lam-rolle-badge"
                                            :style="statusStyle(m.status)"
                                            @click="oeffneEdit(m, 'status')" x-text="statusLabel(m.status)"></button>
                                </template>
                                <template x-if="istOffen(m.id, 'status')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <template x-for="s in statusListe" :key="s.slug"><option :value="s.slug" x-text="s.label"></option></template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(m, 'status')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td x-text="m.veroeffentlicht_am ? formatDatum(m.veroeffentlicht_am) : '—'"></td>
                            <td class="url-cell">
                                <template x-if="m.veroeffentlichungs_url">
                                    <a :href="m.veroeffentlichungs_url" target="_blank" rel="noopener" x-text="kurzUrl(m.veroeffentlichungs_url)" style="color:var(--thoxan-700);"></a>
                                </template>
                                <template x-if="!m.veroeffentlichungs_url"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <template x-if="m.linktext"><span x-text="m.linktext"></span></template>
                                <template x-if="!m.linktext"><span class="empty">—</span></template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Maßnahmen.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>

        <!-- Pagination -->
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;padding:12px 16px;border-top:1px solid var(--slate-100);background:var(--slate-50);font-size:var(--d-fs-sm);">
            <div style="display:flex;align-items:center;gap:10px;color:var(--slate-600);">
                <span>Pro Seite</span>
                <select x-model.number="filter.limit" @change="filter.offset = 0; laden()"
                        style="padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250">250</option>
                </select>
                <span style="color:var(--slate-400);">·</span>
                <span><strong x-text="totalCount"></strong> Maßnahmen</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="seiteZurueck()" :disabled="filter.offset === 0">‹ Zurück</button>
                <span style="color:var(--slate-600);">
                    Seite <strong x-text="aktuelleSeite()"></strong> von <strong x-text="seitenZahl()"></strong>
                </span>
                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="seiteVor()" :disabled="filter.offset + filter.limit >= totalCount">Weiter ›</button>
            </div>
        </div>
    </section>

    <!-- Rechtsklick-Kontextmenue -->
    <div class="thx-contextmenu" x-show="ctxMenu.offen" x-cloak :style="`top: ${ctxMenu.y}px; left: ${ctxMenu.x}px;`" @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.ziel?.domain_url || ''"></div>
        <a class="thx-contextmenu-item" :href="ctxMenu.ziel ? '/lam/massnahmen/' + encodeURIComponent(ctxMenu.ziel.id) : '#'" style="text-decoration:none;">Detail-Seite öffnen</a>
        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Status setzen</div>
        <template x-for="s in statusListe" :key="s.slug">
            <button class="thx-contextmenu-item" @click="schnellStatus(ctxMenu.ziel, s.slug); ctxMenu.offen = false" x-text="s.label"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item is-danger" @click="loescheMassnahme(ctxMenu.ziel); ctxMenu.offen = false">Löschen</button>
    </div>

    <!-- Detail-Modal entfernt — Klick auf Domain-URL öffnet die Detail-Seite /lam/massnahmen/{id} -->

    <!-- Drawer: Neue Maßnahme anlegen -->
    <div class="thx-drawer-backdrop" x-show="anlegen.offen" @click.self="anlegen.offen = false" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title">Neue Maßnahme</h2>
                <button class="thx-modal-close" @click="anlegen.offen = false">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>Kunde *</label>
                    <select x-model="anlegen.customer_id">
                        <option value="">— wählen —</option>
                        <template x-for="k in kundenListe" :key="k.id">
                            <option :value="k.id" x-text="(k.kuerzel ? k.kuerzel + ' · ' : '') + k.name"></option>
                        </template>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Domain *</label>
                    <input type="text" x-model="anlegen.domainSuche" placeholder="URL eintippen, dann auswählen" @input.debounce.300ms="domainSuchen()">
                    <div x-show="anlegen.domainSuche && domainTreffer.length > 0" style="max-height:200px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:4px;margin-top:4px;background:#fff;">
                        <template x-for="d in domainTreffer" :key="d.id">
                            <div @click="domainWaehlen(d)"
                                 style="padding:6px 10px;cursor:pointer;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-sm);"
                                 onmouseover="this.style.background='var(--slate-50)'"
                                 onmouseout="this.style.background=''">
                                <strong x-text="d.url"></strong>
                                <span class="muted" x-show="d.anbieter_name" style="font-size:var(--d-fs-xs);color:var(--slate-500);"> · <span x-text="d.anbieter_name"></span></span>
                            </div>
                        </template>
                    </div>
                    <div x-show="anlegen.domain_id" class="muted" style="font-size:var(--d-fs-xs);color:var(--emerald-700);margin-top:4px;">
                        ✓ <span x-text="anlegen.domain_url"></span>
                    </div>
                    <div x-show="domainKonditionInklText"
                         style="margin-top:6px;padding:6px 10px;background:var(--emerald-50);border:1px solid var(--emerald-200);border-radius:6px;font-size:var(--d-fs-xs);color:var(--emerald-800);">
                        💡 Diese Domain hat eine Kondition <strong>inkl. Text</strong> — Texterstellung ist im Preis enthalten.
                    </div>
                </div>
                <!-- Wiederholungstäter-Hinweis -->
                <div x-show="vorherigeMassnahmen.length > 0" x-cloak
                     style="padding:12px 14px;background:#fff7ed;border:1px solid #fdba74;border-radius:6px;margin-bottom:12px;font-size:var(--d-fs-sm);">
                    <div style="display:flex;align-items:center;gap:8px;color:#9a3412;font-weight:600;">
                        <span style="font-size:1.2rem;">⚠</span>
                        <span>Wiederholungstäter — diese Domain hatte bereits <strong x-text="vorherigeMassnahmen.length"></strong> Maßnahme<span x-show="vorherigeMassnahmen.length !== 1">n</span> für diesen Kunden.</span>
                    </div>
                    <ul style="margin:6px 0 0 26px;padding:0;font-size:var(--d-fs-xs);color:#7c2d12;">
                        <template x-for="v in vorherigeMassnahmen.slice(0, 5)" :key="v.id">
                            <li>
                                <a :href="'/lam/massnahmen/' + v.id" target="_blank" style="color:var(--thoxan-700);"
                                   x-text="(v.veroeffentlicht_am ? formatDatum(v.veroeffentlicht_am) : (v.geplant_am ? 'geplant ' + formatDatum(v.geplant_am) : 'ohne Datum')) + ' · Status: ' + statusLabel(v.status)"></a>
                            </li>
                        </template>
                        <li x-show="vorherigeMassnahmen.length > 5">
                            … <span x-text="vorherigeMassnahmen.length - 5"></span> weitere
                        </li>
                    </ul>
                </div>

                <div class="thx-form-field">
                    <label>Status</label>
                    <select x-model="anlegen.status">
                        <template x-for="s in statusListe" :key="s.slug"><option :value="s.slug" x-text="s.label"></option></template>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Buchungstyp (optional)</label>
                    <select x-model="anlegen.buchungstyp">
                        <option value="">— optional —</option>
                        <template x-for="b in buchungstypListe" :key="b.slug"><option :value="b.slug" x-text="b.label"></option></template>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Linkziel (optional, aus Stammdaten)</label>
                    <select x-model="anlegen.linkziel_id" @change="linkzielWaehlen($event)" :disabled="!anlegen.customer_id">
                        <option value="">— optional auswählen —</option>
                        <template x-for="lz in linkzielListe" :key="lz.id">
                            <option :value="lz.id" x-text="lz.thema + ' — ' + lz.url"></option>
                        </template>
                    </select>
                    <div class="muted" style="font-size:var(--d-fs-xs);margin-top:4px;">
                        <a :href="anlegen.customer_id ? '/lam/linkziele?customer_id=' + anlegen.customer_id : '/lam/linkziele'"
                           target="_blank" style="color:var(--thoxan-700);">Linkziele verwalten →</a>
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Linktext (optional)</label>
                    <input type="text" x-model="anlegen.linktext">
                </div>
                <div class="thx-form-field">
                    <label>Geplant am (optional)</label>
                    <input type="date" x-model="anlegen.geplant_am">
                </div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="anlegen.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereAnlegen()" :disabled="anlegen.laeuft || !anlegen.customer_id || !anlegen.domain_id">
                    <span x-show="!anlegen.laeuft">Anlegen</span><span x-show="anlegen.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
function lamMassnahmen() {
    return {
        laedt: true, rows: [], totalCount: 0,
        filter: { suche: '', status: '', sonderstatus: '', sort: 'veroeffentlicht_desc', limit: 50, offset: 0 },

        statusListe: [
            { slug: 'idee',         label: 'Idee' },
            { slug: 'akquise',      label: 'Akquise' },
            { slug: 'bei_kunde',    label: 'Beim Kunden' },
            { slug: 'beauftragt',   label: 'Beauftragt' },
            { slug: 'bei_anbieter', label: 'Beim Anbieter' },
            { slug: 'live',         label: 'Live' },
            { slug: 'archiv',       label: 'Archiv' },
        ],
        vorgangstypListe: [
            { slug: 'erstveroeffentlichung', label: 'Erstveröffentlichung' },
            { slug: 're_veroeffentlichung',  label: 'Re-Veröffentlichung' },
            { slug: 'sammelbuchung',         label: 'Sammelbuchung' },
            { slug: 'nachbuchung',           label: 'Nachbuchung' },
        ],
        buchungstypListe: [
            { slug: 'gastartikel',     label: 'Gastartikel' },
            { slug: 'advertorial',     label: 'Advertorial' },
            { slug: 'pressemitteilung', label: 'Pressemitteilung' },
            { slug: 'interview',       label: 'Interview' },
            { slug: 'verzeichnis',     label: 'Verzeichnis' },
            { slug: 'startseite',      label: 'Startseite' },
        ],
        sonderstatusListe: [
            { slug: 'normal',       label: 'Normal' },
            { slug: 'storniert',    label: 'Storniert' },
            { slug: 'intern',       label: 'Intern' },
            { slug: 'plan_b',       label: 'Plan B' },
            { slug: 'sammelposten', label: 'Sammelposten' },
        ],

        statusLabel(s) { const t = this.statusListe.find(x => x.slug === s); return t ? t.label : (s || '—'); },
        vorgangstypLabel(s) { const t = this.vorgangstypListe.find(x => x.slug === s); return t ? t.label : (s || '—'); },
        statusStyle(s) {
            const m = {
                idee:         'background:var(--slate-100);color:var(--slate-700);',
                akquise:      'background:var(--amber-100);color:var(--amber-800);',
                bei_kunde:    'background:var(--thoxan-100);color:var(--thoxan-700);',
                beauftragt:   'background:#dbeafe;color:#1e40af;',
                bei_anbieter: 'background:#ede9fe;color:#5b21b6;',
                live:         'background:var(--emerald-100);color:var(--emerald-800);',
                archiv:       'background:var(--slate-200);color:var(--slate-600);',
            };
            return m[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },

        // Sortierung
        sortBy(feld) {
            this.filter.sort = (this.filter.sort === feld + '_asc') ? feld + '_desc' : feld + '_asc';
            this.filter.offset = 0;
            this.laden();
        },
        sortPfeil(feld) {
            if (this.filter.sort === feld + '_asc') return '▲';
            if (this.filter.sort === feld + '_desc') return '▼';
            return '';
        },

        // Pagination
        seitenZahl() { return Math.max(1, Math.ceil(this.totalCount / this.filter.limit)); },
        aktuelleSeite() { return Math.floor(this.filter.offset / this.filter.limit) + 1; },
        seiteVor() {
            if (this.filter.offset + this.filter.limit < this.totalCount) { this.filter.offset += this.filter.limit; this.laden(); }
        },
        seiteZurueck() {
            if (this.filter.offset > 0) { this.filter.offset = Math.max(0, this.filter.offset - this.filter.limit); this.laden(); }
        },
        filterZuruecksetzen() {
            try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) {}
            this.filter = { suche: '', status: '', sonderstatus: '', sort: 'veroeffentlicht_desc', limit: 50, offset: 0 };
            this.laden();
        },

        formatDatum(d) {
            if (!d) return '—';
            // ISO-Datum (YYYY-MM-DD) → dd.mm.yyyy
            const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}.${m[2]}.${m[1]}` : d;
        },

        editZelle: { id: null, feld: null }, editWert: '', editLaeuft: false,
        auswahl: new Set(), bulkAktion: '', bulkWert: '', bulkLaeuft: false,
        ctxMenu: { offen: false, x: 0, y: 0, ziel: null },
        anlegen: {
            offen: false, laeuft: false,
            customer_id: '', domain_id: '', domain_url: '', domainSuche: '',
            status: 'idee',
            buchungstyp: '', linktext: '', geplant_am: '', linkziel_id: '',
            plan_a_massnahme_id: null,
            sonderstatus: 'normal',
        },
        vorherigeMassnahmen: [],

        async pruefeWiederholung() {
            if (!this.anlegen.customer_id || !this.anlegen.domain_id) {
                this.vorherigeMassnahmen = [];
                return;
            }
            try {
                const p = new URLSearchParams({
                    customer_id: this.anlegen.customer_id,
                    domain_id: this.anlegen.domain_id,
                    limit: '20', offset: '0'
                });
                const r = await fetch('/api/v1/lam/massnahmen?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.vorherigeMassnahmen = (j.data.rows || j.data || []);
            } catch (e) { this.vorherigeMassnahmen = []; }
        },
        kundenListe: [], domainTreffer: [], linkzielListe: [],
        domainKonditionInklText: false,
        planAQuelle: null,

        async oeffneAnlegen(opts = {}) {
            this.anlegen = {
                offen: true, laeuft: false,
                customer_id: opts.customer_id || '',
                domain_id: '', domain_url: '', domainSuche: '',
                vorgangstyp: opts.vorgangstyp || 'erstveroeffentlichung',
                status: opts.status || 'idee',
                buchungstyp: '', linktext: '', geplant_am: '', linkziel_id: '',
                plan_a_massnahme_id: opts.plan_a_massnahme_id || null,
                sonderstatus: opts.sonderstatus || 'normal',
            };
            this.linkzielListe = [];
            // Kunden laden, falls noch nicht da
            if (this.kundenListe.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                    const json = await r.json();
                    if (json.success) this.kundenListe = json.data || [];
                } catch (e) {}
            }
            // Watcher for customer change → load Linkziele + Wiederholungs-Check
            this.$watch('anlegen.customer_id', async (cid) => {
                this.linkzielListe = [];
                this.anlegen.linkziel_id = '';
                this.pruefeWiederholung();
                if (!cid) return;
                try {
                    const r = await fetch('/api/v1/lam/linkziele-kunde?customer_id=' + encodeURIComponent(cid), { credentials: 'same-origin' });
                    const json = await r.json();
                    if (json.success) this.linkzielListe = json.data || [];
                } catch (e) {}
            });
            this.$watch('anlegen.domain_id', () => { this.pruefeWiederholung(); });
        },
        linkzielWaehlen(ev) {
            const lz = this.linkzielListe.find(x => x.id === this.anlegen.linkziel_id);
            if (lz && !this.anlegen.linktext) {
                this.anlegen.linktext = lz.bevorzugter_linktext || '';
            }
        },
        async domainSuchen() {
            const q = (this.anlegen.domainSuche || '').trim();
            if (q.length < 2) { this.domainTreffer = []; return; }
            try {
                const r = await fetch('/api/v1/lam/linkquellen?' + new URLSearchParams({ suche: q, limit: '20' }), { credentials: 'same-origin' });
                const json = await r.json();
                if (json.success) this.domainTreffer = (json.data?.rows || []).slice(0, 20);
            } catch (e) {}
        },
        async domainWaehlen(d) {
            this.anlegen.domain_id = d.id;
            this.anlegen.domain_url = d.url;
            this.anlegen.domainSuche = d.url;
            this.domainTreffer = [];
            this.domainKonditionInklText = false;
            // Prüfen, ob eine Kondition dieser Domain inkl. Text ist
            try {
                const rd = await fetch('/api/v1/lam/domain-detail?id=' + encodeURIComponent(d.id), { credentials: 'same-origin' });
                const jd = await rd.json();
                if (jd.success && Array.isArray(jd.data?.konditionen)) {
                    this.domainKonditionInklText = jd.data.konditionen.some(k => parseInt(k.inkl_text) === 1);
                }
            } catch (e) {}
        },
        async speichereAnlegen() {
            if (!this.anlegen.customer_id || !this.anlegen.domain_id) return;
            this.anlegen.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/massnahme-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: parseInt(this.anlegen.customer_id, 10),
                        domain_id: this.anlegen.domain_id,
                        vorgangstyp: this.anlegen.vorgangstyp,
                        status: this.anlegen.status,
                        buchungstyp: this.anlegen.buchungstyp || null,
                        linktext: this.anlegen.linktext || null,
                        geplant_am: this.anlegen.geplant_am || null,
                        linkziel_id: this.anlegen.linkziel_id || null,
                        plan_a_massnahme_id: this.anlegen.plan_a_massnahme_id || null,
                        sonderstatus: this.anlegen.sonderstatus || 'normal',
                    }),
                });
                const json = await r.json();
                if (json.success) {
                    this.anlegen.offen = false;
                    location.href = '/lam/massnahmen/' + encodeURIComponent(json.data.id);
                } else {
                    alert(json.message || 'Fehler beim Anlegen');
                }
            } catch (e) {
                alert('Verbindungsfehler');
            } finally {
                this.anlegen.laeuft = false;
            }
        },
        async init() {
            // Sticky-Filter aus localStorage laden
            this.STORAGE_KEY = 'thx_lam_filter_massnahmen';
            try {
                const gespeichert = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
                Object.assign(this.filter, gespeichert);
            } catch (e) {}
            this.$watch('filter', (v) => {
                try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(v)); } catch (e) {}
            }, { deep: true });

            // URL-Parameter haben Vorrang vor localStorage
            const params = new URLSearchParams(window.location.search);
            const planA = params.get('plan_b_zu');
            const kunde = params.get('kunde');
            if (params.get('sonderstatus')) this.filter.sonderstatus = params.get('sonderstatus');
            await this.laden();
            if (planA) {
                await this.oeffneAnlegen({
                    customer_id: kunde || '',
                    plan_a_massnahme_id: planA,
                    sonderstatus: 'plan_b',
                    vorgangstyp: 're_veroeffentlichung',
                });
            }
        },
        async laden() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.status) p.set('status', this.filter.status);
            if (this.filter.sonderstatus) p.set('sonderstatus', this.filter.sonderstatus);
            p.set('sort', this.filter.sort);
            p.set('limit', this.filter.limit);
            p.set('offset', this.filter.offset);
            try {
                const r = await fetch('/api/v1/lam/massnahmen?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.rows = j.data.rows || j.data;
                    this.totalCount = j.data.total ?? this.rows.length;
                } else { this.rows = []; this.totalCount = 0; }
            } finally { this.laedt = false; }
        },
        // Inline-Edit
        istOffen(id, feld) { return this.editZelle.id === id && this.editZelle.feld === feld; },
        oeffneEdit(m, feld) {
            if (this.editLaeuft) return;
            this.editZelle = { id: m.id, feld };
            this.editWert = m[feld] ?? '';
        },
        schliesseEdit() { this.editZelle = { id: null, feld: null }; this.editWert = ''; },
        async speichereInline(m, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/massnahme-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: m.id, feld, wert: this.editWert })
                });
                if ((await res.json()).success) { m[feld] = this.editWert; this.schliesseEdit(); }
            } finally { this.editLaeuft = false; }
        },

        // Bulk
        toggleAuswahl(id) {
            const neu = new Set(this.auswahl);
            if (neu.has(id)) neu.delete(id); else neu.add(id);
            this.auswahl = neu;
        },
        alleSichtbarGewaehlt() { return this.rows.length > 0 && this.rows.every(r => this.auswahl.has(r.id)); },
        toggleAlleSichtbar() {
            const neu = new Set(this.auswahl);
            if (this.alleSichtbarGewaehlt()) this.rows.forEach(r => neu.delete(r.id));
            else this.rows.forEach(r => neu.add(r.id));
            this.auswahl = neu;
        },
        auswahlLeeren() { this.auswahl = new Set(); this.bulkAktion = ''; this.bulkWert = ''; },
        async bulkAusfuehren() {
            if (this.bulkLaeuft || !this.bulkAktion || this.auswahl.size === 0) return;
            if (this.bulkAktion === 'loeschen' && !confirm(`${this.auswahl.size} Maßnahmen wirklich löschen?`)) return;
            this.bulkLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/massnahme-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: this.bulkAktion, wert: this.bulkWert || null })
                });
                if ((await res.json()).success) { this.auswahlLeeren(); await this.laden(); }
            } finally { this.bulkLaeuft = false; }
        },

        // Rechtsklick
        oeffneCtxMenu(event, ziel) {
            const x = event.clientX, y = event.clientY;
            const px = (x + 220 > window.innerWidth) ? x - 220 : x;
            const py = (y + 380 > window.innerHeight) ? y - 380 : y;
            this.ctxMenu = { offen: true, x: px, y: py, ziel };
        },
        async schnellStatus(ziel, wert) {
            if (!ziel) return;
            try {
                const res = await fetch('/api/v1/lam/massnahme-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld: 'status', wert })
                });
                if ((await res.json()).success) ziel.status = wert;
            } catch (e) {}
        },
        async loescheMassnahme(ziel) {
            if (!ziel) return;
            if (!confirm(`Maßnahme für "${ziel.domain_url}" wirklich löschen?`)) return;
            await fetch('/api/v1/lam/massnahme-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [ziel.id], aktion: 'loeschen' })
            });
            await this.laden();
        },

        kurzUrl(u) { return u ? u.replace(/^https?:\/\//, '').replace(/\/$/, '') : ''; },
        euro(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', {style:'currency', currency:'EUR'}); }
    };
}
</script>
