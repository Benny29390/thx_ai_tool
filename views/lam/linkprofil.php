<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
/* Dezente Drehbewegung fuer das Hourglass-Icon in der Check-Pille */
@keyframes thx-spin-slow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.thx-spin-slow { display: inline-block; animation: thx-spin-slow 3s linear infinite; }
</style>

<div x-data="lamLinkprofil()" x-init="init()" @click="ctxMenu.offen = false">

<div class="thx-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1 class="thx-page-title">Linkprofil
            <span x-show="checkQueue > 0" x-cloak
                  style="display:inline-flex;align-items:center;gap:6px;background:var(--amber-50);color:var(--amber-800);border:1px solid var(--amber-200);padding:2px 10px;border-radius:999px;font-size:var(--d-fs-xs);font-weight:600;margin-left:8px;vertical-align:middle;cursor:help;"
                  :title="checkQueueTooltip()">
                <span class="material-symbols-rounded thx-spin-slow" style="font-size:14px;">hourglass_top</span>
                <span x-text="zahl(checkQueue)"></span> URLs werden geprueft<span x-show="checkQueueEta" x-cloak> · ETA <span x-text="checkQueueEta"></span></span>
            </span>
        </h1>
        <div class="thx-page-subtitle">Backlink-Analyse pro Kunde. Domain-Wissen und Empfehlungen aus dem Aufräum-Modus.</div>
    </div>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <a class="thx-btn thx-btn-secondary thx-btn-small" href="/lam/linkprofil/domain-wissen" title="Kundenuebergreifende Domain-Klassifikationen (4.497 Eintraege)">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">database</span> Domain-Wissen
        </a>
        <a class="thx-btn thx-btn-secondary thx-btn-small" :href="aktuelleKundeId() ? '/lam/linkprofil/snapshots?customer_id=' + aktuelleKundeId() : '/lam/linkprofil/snapshots'">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">photo_library</span> Snapshots
        </a>
        <a class="thx-btn thx-btn-secondary thx-btn-small" :href="aktuelleKundeId() ? '/lam/linkprofil/statistik?customer_id=' + aktuelleKundeId() : '/lam/linkprofil/statistik'">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">bar_chart</span> Statistik
        </a>
        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="excelExport()" :disabled="!aktiverKunde">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">grid_on</span> Excel
        </button>
        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="urlsKopieren()" :disabled="!aktiverKunde || !rows.length">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">content_copy</span> URLs kopieren
        </button>
        <a class="thx-btn thx-btn-secondary thx-btn-small" :href="aktuelleKundeId() ? '/lam/linkprofil/aufraeumen?customer_id=' + aktuelleKundeId() : '/lam/linkprofil/aufraeumen'">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">cleaning_services</span> Aufräumen
        </a>
        <a class="thx-btn thx-btn-secondary thx-btn-small" :href="aktuelleKundeId() ? '/lam/historien-import?ziel=linkprofil&customer_id=' + aktuelleKundeId() : '/lam/historien-import?ziel=linkprofil'" title="Historische Linkprofil-Excel-Datei importieren">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">history</span> Historie importieren
        </a>
        <button class="thx-btn thx-btn-primary thx-btn-small" @click="importDrawer.offen = true">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">upload_file</span> CSV importieren
        </button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<!-- Drawer: CSV-Import -->
<div class="thx-drawer-backdrop" x-show="importDrawer.offen" @click.self="importDrawer.offen = false" x-cloak>
    <div class="thx-drawer">
        <div class="thx-drawer-header">
            <h2 class="thx-drawer-title">Linkprofil-CSV importieren</h2>
            <button class="thx-modal-close" @click="importDrawer.offen = false">×</button>
        </div>
        <div class="thx-drawer-body">
            <p class="muted" style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                Erkannt werden <strong>Sistrix-Backlinks</strong>, <strong>AHREFs</strong> und generische CSV mit
                <code>verlinkende_url</code>, <code>linktext</code>, <code>ziel_url</code> Spalten.
                Duplikate (gleiche URL) werden übersprungen.
            </p>
            <div class="thx-form-field">
                <label>Kunde *</label>
                <select x-model="importDrawer.customer_id">
                    <option value="">— wählen —</option>
                    <template x-for="k in kunden" :key="k.id">
                        <option :value="k.id" x-text="(k.kuerzel ? k.kuerzel + ' · ' : '') + k.name"></option>
                    </template>
                </select>
            </div>
            <div class="thx-form-field">
                <label>Quelle (optional, z.B. 'sistrix', 'ahrefs', 'xovi', 'gsc')</label>
                <input type="text" x-model="importDrawer.quelle" placeholder="leer = automatisch erkannt">
            </div>
            <div class="thx-form-field">
                <label>CSV-Datei(en) * <span class="muted" style="font-size:var(--d-fs-xs);">— Mehrfachauswahl möglich (max 20)</span></label>
                <input type="file" x-ref="csvDatei" accept=".csv,.tsv,.txt" multiple>
            </div>
            <div x-show="importDrawer.fortschritt" class="muted" style="font-size:var(--d-fs-xs);margin-top:8px;" x-text="importDrawer.fortschritt"></div>
        </div>
        <div class="thx-drawer-footer">
            <button class="lam-btn lam-btn-secondary" @click="importDrawer.offen = false">Abbrechen</button>
            <button class="lam-btn lam-btn-primary" @click="csvImportieren()" :disabled="importDrawer.laeuft || !importDrawer.customer_id">
                <span x-show="!importDrawer.laeuft">Import starten</span><span x-show="importDrawer.laeuft">Lade …</span>
            </button>
        </div>
    </div>
</div>

    <!-- Filter-Card (kompakt) -->
    <style>
    /* Kompaktere Filter-Card — weniger Padding, dichtere Chip-Reihen */
    .lam-filter-card.is-compact { padding: 10px 14px; }
    .lam-filter-card.is-compact .lam-filter-head { margin-bottom: 8px; }
    .lam-filter-card.is-compact .lam-filter-head h2 { font-size: var(--d-fs-sm); margin: 0; }
    .lam-filter-card.is-compact .lam-filter-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom: 6px; }
    .lam-filter-card.is-compact .lam-filter-row > label { font-size: var(--d-fs-xs); color: var(--slate-500); white-space: nowrap; min-width: 70px; }
    .lam-filter-card.is-compact .lam-chip-row { gap: 4px; }
    .lam-filter-card.is-compact .lam-chip { padding: 3px 9px; font-size: var(--d-fs-xs); }
    .lam-filter-card.is-compact .thx-input,
    .lam-filter-card.is-compact .thx-select { padding-top: 4px; padding-bottom: 4px; }
    .lam-filter-card.is-compact .lam-filter-hint { font-size: 10px; color: var(--slate-400); margin-left: 6px; }
    </style>
    <section class="lam-filter-card is-compact">
        <div class="lam-filter-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <h2>Filter</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-500);"
                      x-text="aktiverKunde && gesamt > 0 ? (zahl(gesamt) + ' Verlinkungen') : ''"></span>
                <button type="button" @click="filterZuruecksetzen()"
                        style="font-size:0.75rem;color:var(--slate-500);background:none;border:0;cursor:pointer;text-decoration:underline;">
                    zurücksetzen
                </button>
                <button class="thx-btn thx-btn-small thx-btn-secondary" @click="setzeWeitereFilter(!weitereFilterOffen)">
                    <span x-text="weitereFilterOffen ? '▾ Weniger' : '▸ Mehr'"></span>
                </button>
            </div>
        </div>

        <!-- Kunden-Chips kompakt -->
        <div class="lam-filter-row" x-show="!laedtKunden">
            <label>Kunde</label>
            <div class="lam-chip-row" style="flex:1;">
                <template x-for="k in kunden" :key="k.id">
                    <button type="button"
                            :class="'lam-chip lam-chip-kunde' + (aktiverKunde == k.id ? ' is-active' : '')"
                            @click="kundeWaehlen(k.id)">
                        <span x-text="k.abbreviation"></span>
                        <span style="margin-left:5px;opacity:0.7;" x-text="zahl(k.verlinkungen_aktiv)"></span>
                    </button>
                </template>
            </div>
        </div>
        <div x-show="laedtKunden" class="muted" style="font-size:var(--d-fs-xs);"><span class="lam-spinner"></span> Lade Kunden …</div>

        <template x-if="aktiverKunde">
            <div>
                <!-- Zeile 1: Suche + Pro Seite + Follow nebeneinander -->
                <div class="lam-filter-row">
                    <input type="text" class="thx-input" style="flex:1;min-width:200px;"
                           placeholder="Volltext URL / Domain / Linktext"
                           x-model="filter.suche" @input.debounce.300ms="reload(true)">
                    <select class="thx-select" style="min-width:80px;" x-model="filter.limit" @change="reload(true)" title="Pro Seite">
                        <option value="25">25 / Seite</option>
                        <option value="50">50 / Seite</option>
                        <option value="100">100 / Seite</option>
                        <option value="250">250 / Seite</option>
                    </select>
                    <select class="thx-select" style="min-width:120px;" x-model="filter.follow" @change="reload(true)" title="Follow-Filter">
                        <option value="">alle Follow</option>
                        <option value="follow">nur follow</option>
                        <option value="nofollow">nur nofollow</option>
                        <option value="unbekannt">Follow unbekannt</option>
                    </select>
                </div>

                <!-- Zeile 2: Linkart -->
                <div class="lam-filter-row">
                    <label>Linkart</label>
                    <div class="lam-chip-row" style="flex:1;">
                        <button type="button" class="lam-chip lam-chip-reset" :class="filter.linkart.length === 0 ? 'is-active' : ''" @click="filter.linkart = []; reload(true)">alle</button>
                        <template x-for="la in linkarten" :key="la.wert">
                            <button type="button" class="lam-chip" :class="filter.linkart.includes(la.wert) ? 'is-active' : ''" @click="toggleFilter('linkart', la.wert, $event)" x-text="la.label"></button>
                        </template>
                    </div>
                </div>

                <!-- Zeile 3: Empfehlung -->
                <div class="lam-filter-row">
                    <label>Empfehlung</label>
                    <div class="lam-chip-row" style="flex:1;">
                        <button type="button" class="lam-chip lam-chip-reset" :class="filter.empfehlung.length === 0 ? 'is-active' : ''" @click="filter.empfehlung = []; reload(true)">alle</button>
                        <template x-for="e in empfehlungen" :key="e.wert">
                            <button type="button" class="lam-chip" :class="filter.empfehlung.includes(e.wert) ? 'is-active' : ''" @click="toggleFilter('empfehlung', e.wert, $event)" x-text="e.label"></button>
                        </template>
                    </div>
                </div>

                <!-- Zeile 4: Quelle -->
                <div class="lam-filter-row">
                    <label>Quelle</label>
                    <div class="lam-chip-row" style="flex:1;">
                        <button type="button" class="lam-chip lam-chip-reset" :class="filter.importquelle.length === 0 ? 'is-active' : ''" @click="filter.importquelle = []; reload(true)">alle</button>
                        <template x-for="q in quellen" :key="q.wert">
                            <button type="button" class="lam-chip" :class="filter.importquelle.includes(q.wert) ? 'is-active' : ''" @click="toggleFilter('importquelle', q.wert, $event)" x-text="q.label"></button>
                        </template>
                    </div>
                </div>

                <!-- Zeile 5: Zusätzliche Optionen (klappbar) -->
                <div class="lam-filter-row" x-show="weitereFilterOffen" style="border-top:1px solid var(--slate-200);padding-top:8px;margin-top:4px;">
                    <label>Optionen</label>
                    <div class="lam-chip-row" style="flex:1;">
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_neu ? 'is-active' : ''" @click="filter.nur_neu = !filter.nur_neu; reload(true)">nur neu</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_topp ? 'is-active' : ''" @click="filter.nur_topp = !filter.nur_topp; reload(true)" title="Nur als Topp markierte Verlinkungen">★ nur Topp</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_empfehlung ? 'is-active' : ''" @click="filter.ohne_empfehlung = !filter.ohne_empfehlung; reload(true)">ohne Empfehlung</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_ohne_linkart ? 'is-active' : ''" @click="filter.nur_ohne_linkart = !filter.nur_ohne_linkart; reload(true)">ohne Linkart</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_linktext ? 'is-active' : ''" @click="filter.ohne_linktext = !filter.ohne_linktext; reload(true)">ohne Linktext</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_ziel_url ? 'is-active' : ''" @click="filter.ohne_ziel_url = !filter.ohne_ziel_url; reload(true)" title="Deeplink fehlt (nur Domain als Ziel)">ohne Deeplink</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_bemerkung ? 'is-active' : ''" @click="filter.ohne_bemerkung = !filter.ohne_bemerkung; reload(true)">ohne Bemerkung</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_link_verloren ? 'is-active' : ''" @click="filter.nur_link_verloren = !filter.nur_link_verloren; reload(true)">Link verloren</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nicht_erreichbar ? 'is-active' : ''" @click="filter.nicht_erreichbar = !filter.nicht_erreichbar; reload(true)" title="HTTP-Status liefert nicht 2xx/3xx (vorher Erreichbarkeit pruefen!)">nicht erreichbar</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_si ? 'is-active' : ''" @click="filter.ohne_si = !filter.ohne_si; reload(true)" title="Kein Sistrix-Index-Snapshot fuer die Domain">ohne SI</button>
                        <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_dp ? 'is-active' : ''" @click="filter.ohne_dp = !filter.ohne_dp; reload(true)" title="Keine Domain-Popularitaet-Snapshot fuer die Domain">ohne DP</button>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <!-- Statistik-Zeile -->
    <template x-if="aktiverKunde && statistik">
        <div class="lam-kunden-stats">
            <span>Gesamt <strong x-text="zahl(statistik.gesamt)"></strong></span>
            <span class="neu">Neu <strong x-text="zahl(statistik.neu)"></strong></span>
            <span>Lassen <strong x-text="zahl(statistik.lassen)"></strong></span>
            <span>Ändern <strong x-text="zahl(statistik.aendern)"></strong></span>
            <span>Löschen <strong x-text="zahl(statistik.loeschen)"></strong></span>
            <span class="disavow">Disavow <strong x-text="zahl(statistik.disavow)"></strong></span>
            <span class="unsicher">Unsicher <strong x-text="zahl(statistik.unsicher)"></strong></span>
            <span>Gelöscht <strong x-text="zahl(statistik.geloescht)"></strong></span>
            <span>Ohne Empf. <strong x-text="zahl(statistik.ohne_empfehlung)"></strong></span>
            <span>Ohne Linkart <strong x-text="zahl(statistik.ohne_linkart)"></strong></span>
        </div>
    </template>

    <!-- Bulk-Toolbar -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
        <span class="thx-bulk-count"><strong x-text="auswahl.size"></strong> ausgewählt</span>
        <span class="thx-divider"></span>

        <select x-model="bulkAktion" class="thx-select" style="min-width:280px;">
            <option value="">— Aktion waehlen —</option>
            <optgroup label="Manuell setzen">
                <option value="linkart_setzen">Linkart setzen …</option>
                <option value="empfehlung_setzen">Empfehlung setzen …</option>
            </optgroup>
            <optgroup label="Topp-Links">
                <option value="topp_markieren">★ Als Topp markieren</option>
                <option value="topp_entfernen">☆ Topp-Markierung entfernen</option>
            </optgroup>
            <optgroup label="Aus Domain-Wissensbasis (gratis)">
                <option value="linkart_aus_wissen">Linkart aus Wissensbasis</option>
                <option value="empfehlung_aus_wissen">Empfehlung aus Wissensbasis</option>
            </optgroup>
            <optgroup label="KI-Klassifikation (Claude Haiku)">
                <option value="ki_linkart_schnell">Linkart per KI (max 200, schnell)</option>
                <option value="ki_linkart_crawl">Linkart per KI mit Quellseiten-Crawl (max 50, praezise)</option>
                <option value="ki_empfehlung">Empfehlung per KI (max 200)</option>
            </optgroup>
            <optgroup label="Pruefen / Anreichern">
                <option value="erreichbarkeit_pruefen">Erreichbarkeit (HTTP) pruefen</option>
                <option value="linktext_holen">Linktext aus URL holen (nur falls erreichbar)</option>
            </optgroup>
            <optgroup label="Sistrix-Kennzahlen (kostet Credits)">
                <option value="sistrix_si">Sistrix-Index holen (1 Credit / Domain)</option>
                <option value="sistrix_dp">Domain-Popularitaet holen (25 Credits / Domain)</option>
            </optgroup>
            <optgroup label="In Linkquellen-Pool aufnehmen">
                <option value="in_linkquellen_pool">In Linkquellen-Pool (mit Kunden-Verknüpfung)</option>
            </optgroup>
            <optgroup label="Komplett-Pipeline (mehrere Schritte am Stück)">
                <option value="pipeline_komplett">Komplett: Erreichbarkeit → SI → Linkart-Wissen → KI-Linkart → KI-Empfehlung</option>
                <option value="pipeline_auswahl">… mit Auswahl welcher Schritte (Modal)</option>
            </optgroup>
            <optgroup label="Loeschen">
                <option value="loeschen">Soft-Loeschen</option>
            </optgroup>
        </select>

        <!-- Sekundaerer Dropdown nur bei manueller Setz-Aktion -->
        <select x-show="bulkAktion === 'linkart_setzen'" x-model="bulkWert" class="thx-select" style="min-width:160px;">
            <option value="">— Linkart waehlen —</option>
            <template x-for="la in linkarten" :key="la.wert"><option :value="la.wert" x-text="la.label"></option></template>
        </select>
        <select x-show="bulkAktion === 'empfehlung_setzen'" x-model="bulkWert" class="thx-select" style="min-width:160px;">
            <option value="">— Empfehlung waehlen —</option>
            <template x-for="e in empfehlungen" :key="e.wert"><option :value="e.wert" x-text="e.label"></option></template>
        </select>

        <button class="lam-btn lam-btn-primary lam-btn-small" @click="bulkAusfuehrenNeu()" :disabled="bulkLaeuft || !bulkAktion || bulkBenoetigtWert()">
            <span x-show="!bulkLaeuft">Ausfuehren</span>
            <span x-show="bulkLaeuft">… <span x-text="bulkFortschritt"></span></span>
        </button>

        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <!-- Tabellen-Card -->
    <template x-if="aktiverKunde">
        <section class="lam-table-card">
            <div class="lam-table-wrap">
                <table class="lam-table" id="lam-linkprofil-table">
                    <thead>
                        <tr>
                            <th class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                            </th>
                            <th class="sortable" :class="sortKlasse('url')"         @click="sortBy('url')">URL <span class="sort-icon" x-text="sortPfeil('url')"></span></th>
                            <th class="sortable" :class="sortKlasse('domain')"      @click="sortBy('domain')">Domain <span class="sort-icon" x-text="sortPfeil('domain')"></span></th>
                            <th class="center sortable" :class="sortKlasse('haeufigkeit')" @click="sortBy('haeufigkeit')" title="Anzahl Verlinkungen von dieser Domain für diesen Kunden"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">link</span><span class="sort-icon" x-text="sortPfeil('haeufigkeit')"></span></th>
                            <th class="sortable" :class="sortKlasse('linktext')"    @click="sortBy('linktext')">Linktext <span class="sort-icon" x-text="sortPfeil('linktext')"></span></th>
                            <th class="sortable" :class="sortKlasse('ziel_url')"    @click="sortBy('ziel_url')" title="Ziel-URL (wohin der Backlink zeigt)">→ Ziel-URL <span class="sort-icon" x-text="sortPfeil('ziel_url')"></span></th>
                            <th class="sortable" :class="sortKlasse('linkart')"     @click="sortBy('linkart')">Linkart <span class="sort-icon" x-text="sortPfeil('linkart')"></span></th>
                            <th class="center sortable" :class="sortKlasse('http')" @click="sortBy('http')" title="HTTP-Erreichbarkeit"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">wifi</span><span class="sort-icon" x-text="sortPfeil('http')"></span></th>
                            <th class="center sortable" :class="sortKlasse('sistrix')" @click="sortBy('sistrix')" title="Sistrix Sichtbarkeitsindex (neuester Snapshot)">SI <span class="sort-icon" x-text="sortPfeil('sistrix')"></span></th>
                            <th class="center sortable" :class="sortKlasse('popularitaet')" @click="sortBy('popularitaet')" title="Domain-Popularitaet (neuester Snapshot)">DP <span class="sort-icon" x-text="sortPfeil('popularitaet')"></span></th>
                            <th class="sortable" :class="sortKlasse('empfehlung')"  @click="sortBy('empfehlung')">Empfehlung <span class="sort-icon" x-text="sortPfeil('empfehlung')"></span></th>
                            <th class="center sortable" :class="sortKlasse('topp')" @click="sortBy('topp')" title="Topp-Link (Stern in der Zeile zum Setzen, hier klicken zum Sortieren)">★ <span class="sort-icon" x-text="sortPfeil('topp')"></span></th>
                            <th class="sortable" :class="sortKlasse('bemerkung')"   @click="sortBy('bemerkung')">Bemerkung <span class="sort-icon" x-text="sortPfeil('bemerkung')"></span></th>
                            <th class="center sortable" :class="sortKlasse('neu')"  @click="sortBy('neu')">Neu <span class="sort-icon" x-text="sortPfeil('neu')"></span></th>
                            <th class="sortable" :class="sortKlasse('quelle')"      @click="sortBy('quelle')">Quelle <span class="sort-icon" x-text="sortPfeil('quelle')"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="v in rows" :key="v.id">
                            <tr :class="auswahl.has(v.id) ? 'is-bulk-selected' : ''"
                                @contextmenu.prevent="oeffneCtxMenu($event, v)">
                                <td class="thx-bulk-col">
                                    <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(v.id)" @change="toggleAuswahl(v.id)" @click.stop>
                                </td>
                                <!-- 1. URL (anklickbar + Deeplinks) -->
                                <td class="url-cell">
                                    <a :href="v.verlinkende_url" target="_blank" rel="noopener" x-text="kurzUrl(v.verlinkende_url)"></a>
                                    <button type="button" @click.stop="kopiereURL(v.verlinkende_url)" title="URL kopieren"
                                            style="background:none;border:none;cursor:pointer;color:var(--slate-400);padding:0 4px;">
                                        <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">content_copy</span>
                                    </button>
                                    <a :href="googleDeepUrl(v.verlinkende_url, v.linktext)" target="_blank" rel="noopener noreferrer"
                                       title="Auf Google nach Quell-URL + Linktext suchen — schnell prüfen, ob der Link noch da ist"
                                       @click.stop
                                       style="color:var(--slate-400);padding:0 4px;text-decoration:none;">
                                        <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">search</span>
                                    </a>
                                </td>
                                <!-- 2. Domain -->
                                <td x-text="v.domain"></td>
                                <!-- 3. Anzahl Verlinkungen pro Domain -->
                                <td class="center" :title="v.haeufigkeit_domain + ' Verlinkung' + (v.haeufigkeit_domain == 1 ? '' : 'en') + ' von ' + v.domain"
                                    :style="v.haeufigkeit_domain > 1 ? 'color:var(--thoxan-700);font-weight:600;' : 'color:var(--slate-400);'"
                                    x-text="v.haeufigkeit_domain"></td>
                                <!-- 4. Linktext (Inline-Edit) -->
                                <td>
                                    <template x-if="!istOffen(v.id, 'linktext')">
                                        <button class="thx-inline-edit" :class="!v.linktext ? 'is-empty' : ''" @click="oeffneEdit(v, 'linktext')"
                                                :title="v.linktext || 'Linktext setzen'"
                                                style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;display:inline-block;">
                                            <template x-if="v.linktext"><span x-text="v.linktext"></span></template>
                                            <template x-if="!v.linktext"><span>— setzen</span></template>
                                        </button>
                                    </template>
                                    <template x-if="istOffen(v.id, 'linktext')">
                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                            <input type="text" class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()"
                                                   placeholder="Linktext (Anchor-Text)"
                                                   @keydown.enter.prevent="speichereInline(v, 'linktext')">
                                            <div class="thx-inline-edit-actions">
                                                <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(v, 'linktext')" :disabled="editLaeuft">Speichern</button>
                                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <!-- 4b. Ziel-URL (Inline-Edit) -->
                                <td>
                                    <template x-if="!istOffen(v.id, 'ziel_url')">
                                        <button class="thx-inline-edit" :class="!v.ziel_url ? 'is-empty' : ''" @click="oeffneEdit(v, 'ziel_url')"
                                                :title="v.ziel_url || 'Ziel-URL setzen'"
                                                style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;display:inline-block;">
                                            <template x-if="v.ziel_url">
                                                <span style="color:#0369a1;" x-text="v.ziel_url.replace(/^https?:\/\//,'').replace(/\/$/,'')"></span>
                                            </template>
                                            <template x-if="!v.ziel_url"><span>— setzen</span></template>
                                        </button>
                                    </template>
                                    <template x-if="istOffen(v.id, 'ziel_url')">
                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                            <input type="text" class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()"
                                                   placeholder="https://kunden-domain.de/seite"
                                                   @keydown.enter.prevent="speichereInline(v, 'ziel_url')">
                                            <div class="thx-inline-edit-actions">
                                                <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(v, 'ziel_url')" :disabled="editLaeuft">Speichern</button>
                                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <!-- 5. Linkart (Inline-Edit) -->
                                <td>
                                    <template x-if="!istOffen(v.id, 'linkart')">
                                        <button class="thx-inline-edit" :class="!v.linkart ? 'is-empty' : ''" @click="oeffneEdit(v, 'linkart')" x-text="v.linkart ? linkartLabel(v.linkart) : '— setzen'"></button>
                                    </template>
                                    <template x-if="istOffen(v.id, 'linkart')">
                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                            <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                                <option value="">— leeren —</option>
                                                <template x-for="la in linkarten" :key="la.wert"><option :value="la.wert" x-text="la.label"></option></template>
                                            </select>
                                            <div class="thx-inline-edit-actions">
                                                <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(v, 'linkart')" :disabled="editLaeuft">Speichern</button>
                                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <!-- 6. Erreichbar -->
                                <td class="center">
                                    <span :class="'lam-dot ' + erreichbarkeitKlasse(v)" :title="erreichbarkeitTitel(v)"></span>
                                </td>
                                <!-- 7. Sistrix Index (neuester Snapshot) -->
                                <td class="center" :title="v.sistrix_index != null ? 'Sistrix-Index: ' + v.sistrix_index : 'Kein Snapshot vorhanden'">
                                    <template x-if="v.sistrix_index != null"><span x-text="Number(v.sistrix_index).toLocaleString('de-DE', {maximumFractionDigits: 2})"></span></template>
                                    <template x-if="v.sistrix_index == null"><span class="empty">—</span></template>
                                </td>
                                <!-- 8. Domain-Popularitaet (neuester Snapshot) -->
                                <td class="center" :title="v.domain_popularitaet != null ? 'Domain-Popularitaet: ' + v.domain_popularitaet : 'Kein Snapshot vorhanden'">
                                    <template x-if="v.domain_popularitaet != null"><span x-text="Number(v.domain_popularitaet).toLocaleString('de-DE')"></span></template>
                                    <template x-if="v.domain_popularitaet == null"><span class="empty">—</span></template>
                                </td>
                                <!-- 9. Empfehlung (Inline-Edit) -->
                                <td>
                                    <template x-if="!istOffen(v.id, 'empfehlung')">
                                        <button class="thx-inline-edit" :class="!v.empfehlung ? 'is-empty' : ''" @click="oeffneEdit(v, 'empfehlung')" x-text="v.empfehlung ? empfehlungLabel(v.empfehlung) : '— setzen'"></button>
                                    </template>
                                    <template x-if="istOffen(v.id, 'empfehlung')">
                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                            <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                                <option value="">— leeren —</option>
                                                <template x-for="e in empfehlungen" :key="e.wert"><option :value="e.wert" x-text="e.label"></option></template>
                                            </select>
                                            <div class="thx-inline-edit-actions">
                                                <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(v, 'empfehlung')" :disabled="editLaeuft">Speichern</button>
                                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <!-- Topp-Stern (klickbar zum Setzen/Entfernen) -->
                                <td class="center">
                                    <button type="button" @click.stop="toggleTopp(v)"
                                            :title="v.ist_topp == 1 ? 'Topp-Markierung entfernen' : 'Als Topp markieren'"
                                            style="background:none;border:none;cursor:pointer;padding:0 2px;font-size:15px;line-height:1;"
                                            :style="v.ist_topp == 1 ? 'color:#f59e0b;' : 'color:var(--slate-300);'"
                                            x-text="v.ist_topp == 1 ? '★' : '☆'"></button>
                                </td>
                                <!-- 10. Bemerkung (Inline-Edit) -->
                                <td>
                                    <template x-if="!istOffen(v.id, 'bemerkung')">
                                        <button class="thx-inline-edit" :class="!v.bemerkung ? 'is-empty' : ''" @click="oeffneEdit(v, 'bemerkung')" x-text="v.bemerkung || '—'"></button>
                                    </template>
                                    <template x-if="istOffen(v.id, 'bemerkung')">
                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                            <textarea class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()" rows="2"></textarea>
                                            <div class="thx-inline-edit-actions">
                                                <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(v, 'bemerkung')" :disabled="editLaeuft">Speichern</button>
                                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                                <!-- 10. Neu -->
                                <td class="center">
                                    <template x-if="v.ist_neu == 1"><span class="lam-badge lam-badge-neu">neu</span></template>
                                </td>
                                <!-- 11. Quelle -->
                                <td>
                                    <span class="muted" x-text="quelleKurz(v.imported_from)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Verlinkungen mit diesen Filtern.</div>
                <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span>Lade Verlinkungen …</div>
            </div>
            <div class="thx-pagination">
                <div class="thx-pagination-info">
                    Zeige <strong x-text="rows.length ? (filter.offset + 1) : 0"></strong>
                    – <strong x-text="filter.offset + rows.length"></strong>
                    von <strong x-text="zahl(gesamt)"></strong>
                </div>
                <div class="thx-pagination-controls">
                    <label class="thx-pagination-perpage">
                        Pro Seite
                        <select class="thx-select" x-model="filter.limit" @change="filter.offset = 0; reload(true)">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                    </label>
                    <div class="thx-pagination-pages">
                        <button class="thx-btn thx-btn-small thx-btn-secondary" :disabled="aktSeite() === 1" @click="seiteSetzen(aktSeite() - 1)">‹ Vorherige</button>
                        <template x-for="(p, idx) in seitenAuswahl()" :key="idx">
                            <span>
                                <span x-show="p === '...'" class="thx-pagination-ellipsis">…</span>
                                <button x-show="p !== '...'" class="thx-btn thx-btn-small"
                                        :class="p === aktSeite() ? 'thx-btn-primary' : 'thx-btn-secondary'"
                                        @click="seiteSetzen(p)" x-text="p"></button>
                            </span>
                        </template>
                        <button class="thx-btn thx-btn-small thx-btn-secondary" :disabled="aktSeite() === maxSeite()" @click="seiteSetzen(aktSeite() + 1)">Nächste ›</button>
                    </div>
                </div>
            </div>
        </section>
    </template>

    <template x-if="!aktiverKunde && !laedtKunden">
        <div class="lam-empty">Bitte oben einen Kunden wählen.</div>
    </template>

    <!-- Rechtsklick-Kontextmenue -->
    <div class="thx-contextmenu" x-show="ctxMenu.offen" x-cloak :style="`top: ${ctxMenu.y}px; left: ${ctxMenu.x}px;`" @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.ziel?.domain || ''"></div>
        <div class="thx-contextmenu-label">Empfehlung setzen</div>
        <template x-for="e in empfehlungen" :key="e.wert">
            <button class="thx-contextmenu-item" @click="schnellAktion(ctxMenu.ziel, 'empfehlung', e.wert); ctxMenu.offen = false" x-text="e.label"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item" @click="toggleTopp(ctxMenu.ziel); ctxMenu.offen = false"
                x-text="ctxMenu.ziel?.ist_topp == 1 ? '☆ Topp-Markierung entfernen' : '★ Als Topp markieren'"></button>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item is-danger" @click="loescheVerlinkung(ctxMenu.ziel); ctxMenu.offen = false">Löschen</button>
    </div>

    <!-- Sistrix Pre-Confirm Modal: Kosten + Budget bevor der Bulk laeuft.
         Layout absichtlich inline gestylt, damit es unabhaengig von .thx-*-Resets
         konsistent rendert (Label links, Wert rechts, je Zeile). -->
    <div class="thx-modal-backdrop thx-lightbox" x-show="sistrixPre.offen" x-cloak @click.self="sistrixPreSchliessen()"
         style="background:rgba(15,23,42,0.45);z-index:1050;">
        <div style="width:100%;max-width:460px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                <h3 style="margin:0;font-size:1rem;font-weight:600;color:#0f172a;">Sistrix abrufen: <span x-text="sistrixPre.label"></span></h3>
                <button type="button" @click="sistrixPreSchliessen()" aria-label="Schliessen"
                        style="background:none;border:none;font-size:1.4rem;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">&times;</button>
            </div>
            <div style="padding:18px 20px;">
                <template x-if="sistrixPre.laedt">
                    <div style="padding:24px 0;text-align:center;color:#64748b;font-size:0.875rem;">
                        Vorschau wird geladen …
                    </div>
                </template>
                <template x-if="!sistrixPre.laedt && sistrixPre.vorschau">
                    <div>
                        <p style="font-size:0.8rem;color:#64748b;margin:0 0 14px 0;line-height:1.4;">
                            Cache-Hits werden nicht erneut abgerechnet, das Maximum ist
                            <strong x-text="zahl(sistrixPre.vorschau.kosten_max)"></strong> Credits.
                            Der Abruf funktioniert fuer jede Domain &mdash; unabhaengig davon, ob sie im
                            Linkquellen-Pool ist. Der Pool bleibt also kuratiert.
                        </p>
                        <div style="font-size:0.875rem;">
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Verlinkungen ausgewählt</span>
                                <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.vorschau.verlinkungen)"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Eindeutige Domains</span>
                                <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.vorschau.unique_domains)"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Davon im Cache</span>
                                <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.vorschau.cache_hits)"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Neu abzurufen</span>
                                <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.vorschau.neu_abzurufen)"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Credits je Domain</span>
                                <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.vorschau.credits_pro_domain)"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:10px 0 6px 0;border-top:1px solid #cbd5e1;margin-top:4px;">
                                <span style="color:#0f172a;font-weight:600;">Maximalkosten</span>
                                <span style="color:#0f172a;font-weight:600;font-variant-numeric:tabular-nums;">
                                    <span x-text="zahl(sistrixPre.vorschau.kosten_max)"></span> Credits
                                </span>
                            </div>
                            <template x-if="sistrixPre.status">
                                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                                    <span style="color:#64748b;">Verbleibendes Wochenbudget</span>
                                    <span style="color:#1e293b;font-variant-numeric:tabular-nums;" x-text="zahl(sistrixPre.status.credits_verbleibend)"></span>
                                </div>
                            </template>
                        </div>
                        <p x-show="!sistrixPre.budgetReicht"
                           style="margin:14px 0 0 0;padding:8px 10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:6px;font-size:0.8rem;line-height:1.4;">
                            Maximalkosten uebersteigen das verbleibende Wochenbudget &mdash; der Lauf wird vorzeitig abgebrochen, sobald das Kontingent aufgebraucht ist.
                        </p>
                        <p x-show="sistrixPre.status && !sistrixPre.status.konfiguriert"
                           style="margin:14px 0 0 0;padding:8px 10px;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:6px;font-size:0.8rem;line-height:1.4;">
                            Sistrix-API-Key ist nicht in den Einstellungen hinterlegt.
                        </p>
                    </div>
                </template>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="button" @click="sistrixPreSchliessen()"
                        style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                    Abbrechen
                </button>
                <button type="button"
                        :disabled="sistrixPre.laedt || !sistrixPre.vorschau || sistrixPre.vorschau.neu_abzurufen === 0 || (sistrixPre.status && !sistrixPre.status.konfiguriert)"
                        @click="sistrixPreAnwenden()"
                        style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                        :style="{ opacity: (sistrixPre.laedt || !sistrixPre.vorschau || sistrixPre.vorschau.neu_abzurufen === 0 || (sistrixPre.status && !sistrixPre.status.konfiguriert)) ? '0.5' : '1', cursor: (sistrixPre.laedt || !sistrixPre.vorschau || sistrixPre.vorschau.neu_abzurufen === 0) ? 'not-allowed' : 'pointer' }">
                    Jetzt abrufen
                </button>
            </div>
        </div>
    </div>

    <!-- Pre-Confirm-Modal fuer Bulk-Aktionen (generisch). -->
    <div class="thx-lightbox" x-show="bulkPre.offen" x-cloak @click.self="bulkPre.offen = false"
         style="background:rgba(15,23,42,0.45);">
        <div style="width:100%;max-width:480px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;"
                 :style="{ background: bulkPre.danger ? '#fef2f2' : '#f8fafc' }">
                <h3 style="margin:0;font-size:1rem;font-weight:600;" :style="{ color: bulkPre.danger ? '#991b1b' : '#0f172a' }" x-text="bulkPre.titel"></h3>
                <button @click="bulkPre.offen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div style="padding:18px 20px;">
                <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;font-weight:600;" x-text="bulkPre.counterText"></div>
                <p style="font-size:0.875rem;color:#334155;line-height:1.45;margin:0;" x-html="bulkPre.beschreibung"></p>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
                <button @click="bulkPre.offen = false"
                        style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                    Abbrechen
                </button>
                <button @click="bulkPre.onConfirm && bulkPre.onConfirm()"
                        :style="{ background: bulkPre.danger ? '#b91c1c' : '#0369a1' }"
                        style="padding:6px 14px;border:none;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                        x-text="bulkPre.buttonLabel"></button>
            </div>
        </div>
    </div>

    <!-- Live-Fortschritts-Modal fuer chunked Bulks (Sistrix, evtl. spaeter weitere) -->
    <div class="thx-modal-backdrop thx-lightbox" x-show="fortschritt.offen" x-cloak
         style="background:rgba(15,23,42,0.45);z-index:1050;">
        <div style="width:100%;max-width:440px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                <h3 style="margin:0;font-size:1rem;font-weight:600;color:#0f172a;" x-text="fortschritt.label"></h3>
                <button type="button" x-show="fortschritt.fertig" @click="fortschrittSchliessen()" aria-label="Schliessen"
                        style="background:none;border:none;font-size:1.4rem;line-height:1;color:#64748b;cursor:pointer;padding:0 4px;">&times;</button>
            </div>
            <div style="padding:18px 20px;">
                <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px 0;"
                   x-text="fortschritt.fertig ? 'Fertig.' : (fortschritt.abbrechen ? 'Wird abgebrochen …' : 'Läuft …')"></p>
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:0.875rem;">
                        <span style="font-weight:600;color:#0f172a;font-variant-numeric:tabular-nums;">
                            <span x-text="zahl(fortschritt.done)"></span> / <span x-text="zahl(fortschritt.total)"></span>
                        </span>
                        <span style="color:#64748b;font-size:0.75rem;"
                              x-text="Math.round((fortschritt.done / Math.max(fortschritt.total, 1)) * 100) + ' %'"></span>
                    </div>
                    <div style="margin-top:6px;height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
                        <div style="height:100%;background:#0369a1;transition:width 0.4s ease-out;border-radius:99px;"
                             :style="{ width: ((fortschritt.done / Math.max(fortschritt.total, 1)) * 100) + '%' }"></div>
                    </div>
                </div>
                <div style="margin-top:14px;font-size:0.875rem;">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                        <span style="color:#64748b;">Erfolgreich</span>
                        <span style="color:#047857;font-weight:600;font-variant-numeric:tabular-nums;" x-text="zahl(fortschritt.erfolge)"></span>
                    </div>
                    <div x-show="fortschritt.fehler.length" style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                        <span style="color:#64748b;">Fehler</span>
                        <span style="color:#b91c1c;font-weight:600;font-variant-numeric:tabular-nums;" x-text="zahl(fortschritt.fehler.length)"></span>
                    </div>
                    <div x-show="fortschritt.extra" style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid #cbd5e1;margin-top:4px;">
                        <span style="color:#64748b;">Stand</span>
                        <span style="color:#1e293b;" x-text="fortschritt.extra"></span>
                    </div>
                </div>
                <div x-show="fortschritt.fehler.length" style="margin-top:12px;">
                    <button type="button" @click="fortschritt.fehlerOffen = !fortschritt.fehlerOffen"
                            style="background:none;border:none;color:#64748b;cursor:pointer;font-size:0.75rem;padding:0;">
                        <span x-text="fortschritt.fehlerOffen ? '▾' : '▸'"></span>
                        Fehlerdetails (<span x-text="fortschritt.fehler.length"></span>)
                    </button>
                    <ul x-show="fortschritt.fehlerOffen"
                        style="margin:6px 0 0 0;padding:8px;max-height:160px;overflow-y:auto;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:6px;font-size:0.75rem;list-style:none;line-height:1.4;">
                        <template x-for="(f, i) in fortschritt.fehler" :key="i">
                            <li style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:1px 0;" :title="f" x-text="f"></li>
                        </template>
                    </ul>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="button" x-show="!fortschritt.fertig"
                        :disabled="fortschritt.abbrechen"
                        @click="fortschritt.abbrechen = true"
                        style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;"
                        :style="{ opacity: fortschritt.abbrechen ? '0.5' : '1', cursor: fortschritt.abbrechen ? 'not-allowed' : 'pointer' }"
                        x-text="fortschritt.abbrechen ? 'Wird abgebrochen …' : 'Abbrechen'"></button>
                <button type="button" x-show="fortschritt.fertig" @click="fortschrittSchliessen()"
                        style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;">
                    Schließen
                </button>
            </div>
        </div>
    </div>

    <!-- Pipeline-Auswahl-Modal -->
    <div x-show="pipelineModal.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;"
         @click.self="pipelineModal.offen = false">
        <div style="background:#fff;border-radius:10px;width:520px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">Komplett-Pipeline mit Auswahl</h3>
                <button @click="pipelineModal.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:20px 24px;">
                <p style="margin:0 0 16px 0;font-size:var(--d-fs-sm);color:var(--slate-600);">
                    Welche Schritte sollen für <strong x-text="pipelineModal.ids.length"></strong> Verlinkungen ausgeführt werden? Reihenfolge ist fix.
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:var(--slate-50);border-radius:6px;cursor:pointer;">
                        <input type="checkbox" x-model="pipelineModal.schritte.erreichbarkeit" style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.9rem;">1. Erreichbarkeit prüfen</strong>
                            <div style="font-size:0.8rem;color:var(--slate-500);">HTTP-HEAD pro Quellseite (2–3s/URL). Gratis.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:var(--slate-50);border-radius:6px;cursor:pointer;">
                        <input type="checkbox" x-model="pipelineModal.schritte.sistrix_si" style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.9rem;">2. Sistrix SI holen</strong>
                            <div style="font-size:0.8rem;color:var(--slate-500);">1 Credit pro Domain. Cache-Hits gratis.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:var(--slate-50);border-radius:6px;cursor:pointer;">
                        <input type="checkbox" x-model="pipelineModal.schritte.linkart_wissen" style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.9rem;">3. Linkart aus Wissensbasis</strong>
                            <div style="font-size:0.8rem;color:var(--slate-500);">Gratis, nutzt bekannte Domain-Klassifikationen.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#fef7e0;border-radius:6px;cursor:pointer;">
                        <input type="checkbox" x-model="pipelineModal.schritte.ki_linkart" style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.9rem;">4. Linkart per KI (Claude Haiku)</strong>
                            <div style="font-size:0.8rem;color:var(--slate-500);">~1 Cent pro 50 Einträge. Max 200/Lauf.</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#fef7e0;border-radius:6px;cursor:pointer;">
                        <input type="checkbox" x-model="pipelineModal.schritte.ki_empfehlung" style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.9rem;">5. Empfehlung per KI</strong>
                            <div style="font-size:0.8rem;color:var(--slate-500);">Bewertet (lassen/ändern/löschen/disavow) anhand SI + Linkart + Linktext. Max 200/Lauf.</div>
                        </div>
                    </label>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
                    <button @click="pipelineModal.offen = false" class="lam-btn lam-btn-secondary">Abbrechen</button>
                    <button @click="pipelineAusBeMod()"
                            :disabled="!Object.values(pipelineModal.schritte).some(v => v)"
                            class="lam-btn lam-btn-primary">Pipeline starten</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function lamLinkprofil() {
    return {
        laedtKunden: true,
        laedt: false,
        kunden: [],
        aktiverKunde: null,
        rows: [],
        statistik: null,
        gesamt: 0,
        // Aufgeklappte "Mehr/Weniger"-Filter persistieren in localStorage,
        // damit der Zustand auch nach Reload erhalten bleibt.
        weitereFilterOffen: (() => {
            try { return localStorage.getItem('thx_lam_linkprofil_filter_offen') === '1'; }
            catch (e) { return false; }
        })(),
        setzeWeitereFilter(wert) {
            this.weitereFilterOffen = !!wert;
            try { localStorage.setItem('thx_lam_linkprofil_filter_offen', wert ? '1' : '0'); }
            catch (e) { /* ignorieren */ }
        },
        importDrawer: { offen: false, laeuft: false, customer_id: '', quelle: '', fortschritt: '' },

        aktuelleKundeId() {
            return this.aktiverKunde?.id || null;
        },

        async csvImportieren() {
            const dateien = Array.from(this.$refs.csvDatei?.files || []);
            if (dateien.length === 0) { alert('Bitte mindestens eine CSV-Datei wählen'); return; }
            if (dateien.length > 20) { alert('Max 20 Dateien pro Lauf'); return; }
            if (!this.importDrawer.customer_id) { alert('Bitte Kunde wählen'); return; }
            this.importDrawer.laeuft = true;
            this.importDrawer.fortschritt = '';
            const bericht = [];
            let okGesamt = 0, fehler = 0;
            let snapshotNeu = 0, snapshotWeg = 0;
            try {
                for (let i = 0; i < dateien.length; i++) {
                    const d = dateien[i];
                    this.importDrawer.fortschritt = `Verarbeite ${i + 1}/${dateien.length}: ${d.name} …`;
                    const fd = new FormData();
                    fd.append('customer_id', this.importDrawer.customer_id);
                    fd.append('csv', d);
                    if (this.importDrawer.quelle) fd.append('quelle', this.importDrawer.quelle);
                    try {
                        const r = await fetch('/api/v1/lam/linkprofil-import', { method: 'POST', credentials: 'same-origin', body: fd });
                        const j = await r.json();
                        if (j.success) {
                            okGesamt += j.data.neu || 0;
                            snapshotNeu += j.data.neu_count || 0;
                            snapshotWeg += j.data.verschwunden_count || 0;
                            bericht.push(`✓ ${d.name}: ${j.data.neu} neu, ${j.data.doppelt} doppelt (${j.data.format})`);
                        } else {
                            fehler++;
                            bericht.push(`✗ ${d.name}: ${j.message || 'Fehler'}`);
                        }
                    } catch (e) {
                        fehler++;
                        bericht.push(`✗ ${d.name}: Verbindungsfehler`);
                    }
                }
                let msg = `Multi-Import abgeschlossen.\n\n${dateien.length} Datei(en), ${okGesamt} neue Verlinkungen, ${fehler} Fehler.\n`;
                if (snapshotNeu || snapshotWeg) {
                    msg += `\nSnapshot-Diff: + ${snapshotNeu} neu, − ${snapshotWeg} verschwunden\n`;
                }
                msg += '\nDetails:\n' + bericht.join('\n');
                alert(msg);
                this.importDrawer.offen = false;
                if (this.aktiverKunde && this.aktiverKunde == this.importDrawer.customer_id) {
                    this.laden();
                }
            } finally {
                this.importDrawer.laeuft = false;
                this.importDrawer.fortschritt = '';
            }
        },
        // Aktuelle Filter als URLSearchParams aufbauen — wird für Excel + URLs-kopieren genutzt
        filterParams() {
            const p = new URLSearchParams();
            p.set('customer_id', this.aktiverKunde);
            if (this.filter.suche) p.set('suche', this.filter.suche);
            this.filter.linkart.forEach(v => p.append('linkart[]', v));
            this.filter.empfehlung.forEach(v => p.append('empfehlung[]', v));
            this.filter.importquelle.forEach(v => p.append('importquelle[]', v));
            if (this.filter.follow) p.set('follow', this.filter.follow);
            if (this.filter.nur_neu) p.set('nur_neu', '1');
            if (this.filter.nur_topp) p.set('nur_topp', '1');
            if (this.filter.nur_ohne_linkart) p.set('nur_ohne_linkart', '1');
            if (this.filter.ohne_empfehlung) p.set('ohne_empfehlung', '1');
            if (this.filter.ohne_linktext) p.set('ohne_linktext', '1');
            if (this.filter.ohne_ziel_url) p.set('ohne_ziel_url', '1');
            if (this.filter.ohne_bemerkung) p.set('ohne_bemerkung', '1');
            if (this.filter.nur_link_verloren) p.set('nur_link_verloren', '1');
            if (this.filter.ohne_si) p.set('ohne_si', '1');
            if (this.filter.ohne_dp) p.set('ohne_dp', '1');
            if (this.filter.nicht_erreichbar) p.set('nicht_erreichbar', '1');
            if (this.filter.sort)  p.set('sort',  this.filter.sort);
            if (this.filter.order) p.set('order', this.filter.order);
            return p;
        },
        filterZuruecksetzen() {
            try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) {}
            this.filter = {
                suche: '', linkart: [], empfehlung: [], importquelle: [],
                follow: '', nur_neu: false, nur_ohne_linkart: false,
                ohne_empfehlung: false, ohne_linktext: false, ohne_ziel_url: false, ohne_bemerkung: false,
                nur_link_verloren: false, ohne_si: false, ohne_dp: false,
                nicht_erreichbar: false, sort: this.filter.sort, order: 'desc',
                limit: 50, offset: 0,
            };
            this.reload(true);
        },
        excelExport() {
            if (!this.aktiverKunde) { alert('Bitte erst einen Kunden wählen'); return; }
            // Aktuelle Filter mitgeben — Excel enthält genau die gefilterten Treffer
            window.location.href = '/api/v1/lam/linkprofil-excel?' + this.filterParams().toString();
        },
        csvExport() {
            if (!this.aktiverKunde) { alert('Bitte erst einen Kunden wählen'); return; }
            window.location.href = '/api/v1/lam/linkprofil-export?' + this.filterParams().toString();
        },
        async urlsKopieren() {
            if (!this.aktiverKunde) { alert('Bitte erst einen Kunden wählen'); return; }
            try {
                // Alle gefilterten URLs holen (nicht nur die aktuelle Seite)
                const p = this.filterParams();
                p.set('limit', '10000');
                p.set('offset', '0');
                const res = await fetch('/api/v1/lam/verlinkungen?' + p, { credentials: 'same-origin' });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Fehler');
                const alle = json.data.rows || json.data || [];
                if (!alle.length) { alert('Keine URLs für den aktuellen Filter.'); return; }
                const urls = alle.map(v => v.verlinkende_url).filter(Boolean).join('\n');
                await navigator.clipboard.writeText(urls);
                alert('✓ ' + alle.length + ' URL' + (alle.length !== 1 ? 's' : '') + ' in die Zwischenablage kopiert.');
            } catch (e) { alert('Kopieren fehlgeschlagen: ' + e.message); }
        },

        empfehlungen: [
            { wert: 'lassen',    label: 'lassen' },
            { wert: 'aendern',   label: 'ändern' },
            { wert: 'loeschen',  label: 'löschen' },
            { wert: 'disavow',   label: 'disavow' },
            { wert: 'geloescht', label: 'gelöscht' },
            { wert: 'unsicher',  label: 'unsicher (klären)' },
        ],
        linkartenStandard: [
            { wert: 'spam',               label: 'Spam' },
            { wert: 'branchenverzeichnis',label: 'Branchenverzeichnis' },
            { wert: 'fachverzeichnis',    label: 'Fachverzeichnis' },
            { wert: 'online_magazin',     label: 'Online-Magazin' },
            { wert: 'portal',             label: 'Portal' },
            { wert: 'blog',               label: 'Blog' },
            { wert: 'presseportal',       label: 'Presseportal' },
            { wert: 'forum',              label: 'Forum' },
            { wert: 'social_media',       label: 'Social Media' },
            { wert: 'referenzprojekt',    label: 'Referenzprojekt' },
            { wert: 'partner',            label: 'Partner' },
            { wert: 'sponsoring',         label: 'Sponsoring' },
            { wert: 'stellenboerse',      label: 'Stellenbörse' },
            { wert: 'veranstaltung',      label: 'Veranstaltung' },
            { wert: 'kommentarlink',      label: 'Kommentarlink' },
            { wert: 'podcast',            label: 'Podcast' },
            { wert: 'weiterleitung',      label: 'Weiterleitung' },
            { wert: 'sonstiges',          label: 'Sonstiges' },
        ],
        // Kundenspezifische Linkarten — werden beim Kundenwechsel nachgeladen
        // und an die Standard-Liste angehaengt. So tauchen sie in Filter,
        // Bulk-Toolbar und Inline-Edit auf, sobald der Kunde gewaehlt ist.
        linkartenKunde: [],
        get linkarten() {
            const kunde = (this.linkartenKunde || []).map(k => ({
                wert: k.linkart_key,
                label: '★ ' + k.label,
                kundenspezifisch: true,
            }));
            return this.linkartenStandard.concat(kunde);
        },
        quellen: [
            { wert: 'sistrix', label: 'Sistrix' },
            { wert: 'ahrefs',  label: 'AHREFs' },
            { wert: 'xovi',    label: 'XOVI' },
            { wert: 'gsc',     label: 'GSC' },
            { wert: 'manuell', label: 'Manuell' },
        ],

        filter: {
            suche: '',
            linkart: [],
            empfehlung: [],
            importquelle: [],
            follow: '',
            nur_neu: false,
            nur_topp: false,
            nur_ohne_linkart: false,
            ohne_empfehlung: false,
            ohne_linktext: false,
            ohne_ziel_url: false,
            ohne_bemerkung: false,
            nur_link_verloren: false,
            ohne_si: false,
            ohne_dp: false,
            nicht_erreichbar: false,
            sort: 'erstellt_am',
            order: 'desc',
            limit: 50,
            offset: 0,
        },

        // Inline-Edit
        editZelle: { id: null, feld: null }, editWert: '', editLaeuft: false,

        // Bulk
        auswahl: new Set(), bulkAktion: '', bulkWert: '', bulkLaeuft: false, bulkFortschritt: '',

        // Sistrix-Pre-Confirm-Modal (zeigt Kosten + Budget bevor Bulk laeuft)
        sistrixPre: {
            offen: false,
            teil: 'si',             // 'si' | 'dp' | 'alter'
            label: 'SI',
            ids: [],
            laedt: false,
            vorschau: null,         // Antwort von /lam/sistrix-vorschau
            status: null,
            budgetReicht: true,
        },

        // Pre-Confirm-Modal fuer Bulk-Aktionen (Linkart setzen, Loeschen, KI, ...).
        // Wird vor dem eigentlichen Lauf gezeigt mit Aktions-Beschreibung + Auswirkung.
        bulkPre: {
            offen: false,
            titel: '',
            beschreibung: '',
            danger: false,
            counterText: '',
            buttonLabel: 'Jetzt ausfuehren',
            laeuft: false,
            onConfirm: null, // Callback, wird beim Klick auf "Ausfuehren" gerufen
        },

        // Live-Fortschritts-Modal (chunked Bulk + Abbrechen)
        fortschritt: {
            offen: false,
            label: '',
            total: 0,
            done: 0,
            erfolge: 0,
            fehler: [],         // string[]
            extra: '',          // z.B. "X Credits verbraucht"
            abbrechen: false,
            fertig: false,
            fehlerOffen: false,
        },

        // Check-Queue (ungepruefte URLs warten auf Hintergrund-Worker)
        checkQueue: 0, checkQueueTimer: null, checkQueueLastFetch: null,
        checkQueueEta: '',

        // Rechtsklick
        ctxMenu: { offen: false, x: 0, y: 0, ziel: null },

        async init() {
            // Sticky-Filter aus localStorage (vor Kunden-Laden, damit beim ersten reload alles sitzt)
            this.STORAGE_KEY = 'thx_lam_filter_linkprofil';
            try {
                const gespeichert = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
                Object.assign(this.filter, gespeichert);
            } catch (e) {}
            this.$watch('filter', (v) => {
                try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(v)); } catch (e) {}
            }, { deep: true });

            try {
                const res = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    this.kunden = json.data;
                    if (this.kunden.length) {
                        // Wunsch-Reihenfolge fuer aktiven Kunden:
                        //   1) URL-Param ?customer_id=42  (bookmarkbar / teilbar)
                        //   2) localStorage „letzter Kunde"
                        //   3) erster Kunde als Fallback
                        const fromUrl = parseInt(new URLSearchParams(location.search).get('customer_id'), 10);
                        const fromLs  = parseInt(localStorage.getItem('lam-linkprofil-customer-id'), 10);
                        const verfuegbar = (id) => this.kunden.some(k => k.id == id);
                        let wahl = this.kunden[0].id;
                        if (fromUrl && verfuegbar(fromUrl))    wahl = fromUrl;
                        else if (fromLs && verfuegbar(fromLs)) wahl = fromLs;
                        this.kundeWaehlen(wahl);
                    }
                }
            } finally {
                this.laedtKunden = false;
            }
        },

        kundeWaehlen(id) {
            this.aktiverKunde = id;
            // URL + localStorage persistieren, damit Tab-Wechsel + Browser-Reload
            // den gewaehlten Kunden behalten — und Links teilbar sind.
            try { localStorage.setItem('lam-linkprofil-customer-id', String(id)); } catch (_) {}
            try {
                const url = new URL(location.href);
                url.searchParams.set('customer_id', String(id));
                history.replaceState(null, '', url.toString());
            } catch (_) {}
            // Kundenspezifische Linkarten nachladen
            this.ladeKundenLinkarten();
            // Check-Queue-Polling fuer diesen Kunden starten
            this.starteCheckQueuePolling();
            this.filter.suche = '';
            this.filter.linkart = [];
            this.filter.empfehlung = [];
            this.filter.importquelle = [];
            this.filter.follow = '';
            this.filter.nur_neu = false;
            this.filter.nur_ohne_linkart = false;
            this.filter.ohne_empfehlung = false;
            this.filter.ohne_linktext = false;
            this.filter.ohne_ziel_url = false;
            this.filter.nur_link_verloren = false;
            this.filter.offset = 0;
            this.reload();
        },

        // Multi-Select-Chip-Logik: Klick = exklusiv, Shift/Strg/Cmd = additiv
        toggleFilter(gruppe, wert, event) {
            const liste = this.filter[gruppe];
            const idx = liste.indexOf(wert);
            if (event && (event.shiftKey || event.ctrlKey || event.metaKey)) {
                if (idx === -1) liste.push(wert);
                else liste.splice(idx, 1);
            } else {
                if (liste.length === 1 && liste[0] === wert) {
                    this.filter[gruppe] = [];
                } else {
                    this.filter[gruppe] = [wert];
                }
            }
            this.reload(true);
        },

        async reload(resetOffset = false) {
            if (resetOffset) this.filter.offset = 0;
            if (!this.aktiverKunde) return;
            this.laedt = true;
            const p = new URLSearchParams();
            p.set('customer_id', this.aktiverKunde);
            if (this.filter.suche) p.set('suche', this.filter.suche);
            this.filter.linkart.forEach(v => p.append('linkart[]', v));
            this.filter.empfehlung.forEach(v => p.append('empfehlung[]', v));
            this.filter.importquelle.forEach(v => p.append('importquelle[]', v));
            if (this.filter.follow) p.set('follow', this.filter.follow);
            if (this.filter.nur_neu) p.set('nur_neu', '1');
            if (this.filter.nur_topp) p.set('nur_topp', '1');
            if (this.filter.nur_ohne_linkart) p.set('nur_ohne_linkart', '1');
            if (this.filter.ohne_empfehlung) p.set('ohne_empfehlung', '1');
            if (this.filter.ohne_linktext) p.set('ohne_linktext', '1');
            if (this.filter.ohne_ziel_url) p.set('ohne_ziel_url', '1');
            if (this.filter.ohne_bemerkung) p.set('ohne_bemerkung', '1');
            if (this.filter.nur_link_verloren) p.set('nur_link_verloren', '1');
            if (this.filter.ohne_si) p.set('ohne_si', '1');
            if (this.filter.ohne_dp) p.set('ohne_dp', '1');
            if (this.filter.nicht_erreichbar) p.set('nicht_erreichbar', '1');
            if (this.filter.sort)  p.set('sort',  this.filter.sort);
            if (this.filter.order) p.set('order', this.filter.order);
            p.set('limit', this.filter.limit);
            p.set('offset', this.filter.offset);

            try {
                const res = await fetch('/api/v1/lam/verlinkungen?' + p, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    this.rows = json.data.rows;
                    this.gesamt = json.data.gesamt;
                    this.statistik = json.data.statistik;
                } else {
                    this.rows = []; this.gesamt = 0;
                }
            } finally {
                this.laedt = false;
            }
        },

        seiteZurueck() { this.seiteSetzen(this.aktSeite() - 1); },
        seiteVor()     { this.seiteSetzen(this.aktSeite() + 1); },
        aktSeite() {
            const limit = parseInt(this.filter.limit, 10) || 50;
            return Math.floor(this.filter.offset / limit) + 1;
        },
        maxSeite() {
            const limit = parseInt(this.filter.limit, 10) || 50;
            return Math.max(1, Math.ceil(this.gesamt / limit));
        },
        seiteSetzen(seite) {
            const limit = parseInt(this.filter.limit, 10) || 50;
            seite = Math.max(1, Math.min(this.maxSeite(), seite));
            this.filter.offset = (seite - 1) * limit;
            this.reload();
        },
        /**
         * Erzeugt die Seiten-Sequenz fuer die Pagination-Buttons.
         * Format: [1, 2, 3, '...', 12, 13, '...', 73, 74]
         * Logik: immer Anfang (1-2), aktuelle Seite +/- 2, Ende (n-1, n).
         */
        seitenAuswahl() {
            const akt = this.aktSeite();
            const max = this.maxSeite();
            if (max <= 7) {
                // Bei wenigen Seiten: alle anzeigen
                return Array.from({length: max}, (_, i) => i + 1);
            }
            const seiten = new Set([1, 2, max - 1, max, akt - 1, akt, akt + 1]);
            const sortiert = Array.from(seiten).filter(p => p >= 1 && p <= max).sort((a, b) => a - b);
            // Ellipsen einfuegen wo Luecken > 1
            const result = [];
            for (let i = 0; i < sortiert.length; i++) {
                result.push(sortiert[i]);
                if (i < sortiert.length - 1 && sortiert[i + 1] - sortiert[i] > 1) {
                    result.push('...');
                }
            }
            return result;
        },

        // Sortierung (Backend muss noch unterstuetzen — falls nicht, zeigt nur visuell)
        sortBy(feld) {
            if (this.filter.sort === feld) this.filter.order = this.filter.order === 'asc' ? 'desc' : 'asc';
            else { this.filter.sort = feld; this.filter.order = 'asc'; }
            this.reload(true);
        },
        sortKlasse(feld) { return this.filter.sort === feld ? 'sorted' : ''; },
        sortPfeil(feld) {
            if (this.filter.sort !== feld) return '↕';
            return this.filter.order === 'asc' ? '↑' : '↓';
        },

        // ─ Inline-Edit ─────────────────────────────────────────────────
        istOffen(id, feld) { return this.editZelle.id === id && this.editZelle.feld === feld; },
        oeffneEdit(v, feld) {
            if (this.editLaeuft) return;
            this.editZelle = { id: v.id, feld };
            this.editWert = v[feld] ?? '';
        },
        schliesseEdit() { this.editZelle = { id: null, feld: null }; this.editWert = ''; },
        async speichereInline(v, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/verlinkung-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: v.id, feld, wert: this.editWert })
                });
                const json = await res.json();
                if (!json.success) { alert(json.message || 'Fehler'); return; }
                v[feld] = this.editWert || null;
                this.schliesseEdit();
            } finally { this.editLaeuft = false; }
        },

        // ─ Bulk-Auswahl ────────────────────────────────────────────────
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
        auswahlLeeren() { this.auswahl = new Set(); this.bulkAktion = ''; this.bulkWert = ''; this.bulkFortschritt = ''; },

        // ─ Check-Queue-Polling ─────────────────────────────────────────
        async ladeCheckQueue() {
            if (!this.aktiverKunde) return;
            const vorher = this.checkQueue;
            try {
                const r = await fetch('/api/v1/lam/erreichbarkeit-queue?customer_id=' + encodeURIComponent(this.aktiverKunde));
                const j = await r.json();
                if (j.success) {
                    this.checkQueue = j.data.count || 0;
                    this.checkQueueLastFetch = new Date();
                    // ETA: 250 URLs alle 5 Min = ~50/Min
                    if (this.checkQueue > 0) {
                        const minutes = Math.ceil(this.checkQueue / 50);
                        this.checkQueueEta = minutes > 60
                            ? Math.round(minutes / 60 * 10) / 10 + ' h'
                            : minutes + ' Min';
                    } else {
                        this.checkQueueEta = '';
                    }
                    // Page-Title aktualisieren: zeigt Counter im Browser-Tab
                    if (this.checkQueue > 0) {
                        document.title = '(⏳ ' + this.checkQueue + ') Linkprofil';
                    } else {
                        document.title = 'Linkprofil';
                    }
                    // Notification beim Uebergang auf 0 (war vorher > 0)
                    if (vorher > 0 && this.checkQueue === 0) {
                        App.showNotification('Erreichbarkeits-Check abgeschlossen — alle URLs geprueft.', 'success');
                        try {
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('Linkprofil', { body: 'Erreichbarkeits-Check fertig', icon: '/assets/images/thoxan-x.svg' });
                            }
                        } catch (_) {}
                    }
                }
            } catch (_) {}
        },
        checkQueueTooltip() {
            const seit = this.checkQueueLastFetch
                ? Math.round((Date.now() - this.checkQueueLastFetch.getTime()) / 1000)
                : null;
            return 'Hintergrund-Worker prueft URLs auf HTTP-Erreichbarkeit.\n'
                + 'Cron-Lauf alle 5 Min, 250 URLs pro Lauf (~50/Min).\n'
                + (seit !== null ? 'Letzte Aktualisierung vor ' + seit + 's.' : 'Polling alle 30s.');
        },
        starteCheckQueuePolling() {
            if (this.checkQueueTimer) clearInterval(this.checkQueueTimer);
            // Browser-Notification-Permission einmalig anfragen wenn nicht entschieden
            try {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            } catch (_) {}
            this.ladeCheckQueue();
            this.checkQueueTimer = setInterval(() => {
                this.ladeCheckQueue();
                if (this.checkQueue === 0) {
                    clearInterval(this.checkQueueTimer);
                    this.checkQueueTimer = null;
                }
            }, 30000);
        },

        /* === Komplett-Pipeline (mehrere Aktionen am Stück) === */
        pipelineModal: {
            offen: false,
            ids: [],
            schritte: {
                erreichbarkeit: true,
                sistrix_si: true,
                linkart_wissen: true,
                ki_linkart: true,
                ki_empfehlung: true,
            },
        },
        async pipelineAusBeMod() {
            const sch = { ...this.pipelineModal.schritte };
            const ids = this.pipelineModal.ids.slice();
            this.pipelineModal.offen = false;
            if (ids.length === 0) return;
            await this.pipelineAusfuehren(ids, sch);
        },
        async pipelineAusfuehren(ids, schritte) {
            // Reihenfolge: Erreichbarkeit → SI → Linkart-Wissen → KI-Linkart → KI-Empfehlung
            const reihenfolge = [
                { flag: 'erreichbarkeit',   aktion: 'erreichbarkeit_pruefen' },
                { flag: 'sistrix_si',       aktion: 'sistrix_si' },
                { flag: 'linkart_wissen',   aktion: 'linkart_aus_wissen' },
                { flag: 'ki_linkart',       aktion: 'ki_linkart_schnell' },
                { flag: 'ki_empfehlung',    aktion: 'ki_empfehlung' },
            ];
            const aktiv = reihenfolge.filter(s => schritte[s.flag]);
            if (aktiv.length === 0) { alert('Keine Schritte ausgewählt.'); return; }
            if (!confirm(`Pipeline startet mit ${aktiv.length} Schritt${aktiv.length !== 1 ? 'en' : ''} für ${ids.length} Verlinkung${ids.length !== 1 ? 'en' : ''}.\n\nSchritte:\n• ${aktiv.map(s => s.aktion).join('\n• ')}\n\nDauert insgesamt 1-10 Min je nach Schritten + Anzahl. Starten?`)) return;

            // Schritte sequentiell durchführen, jeder mit eigenem Fortschritts-Modal
            for (let i = 0; i < aktiv.length; i++) {
                const s = aktiv[i];
                // Für Sistrix-SI brauchen wir das eigene Pre-Confirm-Modal NICHT — wir nehmen den direkten Weg
                const def = this._bulkAktionDef(s.aktion);
                if (!def) continue;
                if (def.maxIds && ids.length > def.maxIds) {
                    // Pipeline-Run: bei KI-Aktionen den Chunk auf maxIds beschränken
                    const teilIds = ids.slice(0, def.maxIds);
                    if (!confirm(`Schritt ${i+1}/${aktiv.length} „${def.titel}" hat Max ${def.maxIds} Items — wird auf die ersten ${def.maxIds} beschränkt. Weiter?`)) break;
                    await this._bulkRun(s.aktion, teilIds, def);
                } else {
                    await this._bulkRun(s.aktion, ids, def);
                }
                if (this.fortschritt.abbrechen) break;
            }
            this.fortschritt.extra = (this.fortschritt.extra || '') + ' · Pipeline abgeschlossen';
            await this.reload(false);
        },

        /* === Neuer Bulk-Dispatcher: weiss welche Aktionen Wert brauchen + welche schon Backend haben === */
        bulkBenoetigtWert() {
            return ['linkart_setzen', 'empfehlung_setzen'].includes(this.bulkAktion) && !this.bulkWert;
        },
        async bulkAusfuehrenNeu() {
            if (this.bulkLaeuft || !this.bulkAktion || this.auswahl.size === 0) return;
            const a = this.bulkAktion;
            const ids = Array.from(this.auswahl);

            // Komplett-Pipeline: alle Schritte hintereinander
            if (a === 'pipeline_komplett') {
                await this.pipelineAusfuehren(ids, {
                    erreichbarkeit: true,
                    sistrix_si: true,
                    linkart_wissen: true,
                    ki_linkart: true,
                    ki_empfehlung: true,
                });
                return;
            }
            // Pipeline mit Auswahl: erst Modal mit Checkboxen
            if (a === 'pipeline_auswahl') {
                this.pipelineModal.offen = true;
                this.pipelineModal.ids = ids;
                return;
            }

            // Limit-Checks fuer KI-Aktionen
            if (a === 'ki_linkart_schnell' && ids.length > 200)  { alert('Max 200 Verlinkungen — bitte Auswahl reduzieren.'); return; }
            if (a === 'ki_empfehlung'      && ids.length > 200)  { alert('Max 200 Verlinkungen — bitte Auswahl reduzieren.'); return; }
            if (a === 'ki_linkart_crawl'   && ids.length > 50)   { alert('Max 50 Verlinkungen — bitte Auswahl reduzieren.'); return; }
            if (a === 'loeschen' && !confirm(`${ids.length} Verlinkung(en) wirklich loeschen?`)) return;

            // Sistrix-Aktionen haben ihr eigenes Pre-Confirm-Modal mit Kosten/Budget.
            if (a === 'sistrix_si' || a === 'sistrix_dp') {
                this.sistrixPreOeffnen(a === 'sistrix_si' ? 'si' : 'dp', ids);
                return;
            }

            // Alle anderen Aktionen ueber generischen Pre-Confirm-Flow.
            const def = this._bulkAktionDef(a);
            if (!def) { alert('Unbekannte Aktion: ' + a); return; }

            // Limit-Check fuer KI-Aktionen
            if (def.maxIds && ids.length > def.maxIds) {
                alert(`Max ${def.maxIds} Verlinkungen für „${def.titel}" — bitte Auswahl reduzieren.`);
                return;
            }

            // Pre-Confirm-Modal anzeigen
            this.bulkPre.titel        = def.titel;
            this.bulkPre.beschreibung = def.beschreibung(ids.length, this.bulkWert);
            this.bulkPre.danger       = !!def.danger;
            this.bulkPre.counterText  = `${ids.length} Verlinkung${ids.length === 1 ? '' : 'en'} ausgewählt`;
            this.bulkPre.buttonLabel  = def.danger ? 'Ja, jetzt löschen' : 'Jetzt ausführen';
            this.bulkPre.offen        = true;
            this.bulkPre.onConfirm    = async () => {
                this.bulkPre.offen = false;
                await this._bulkRun(a, ids, def);
            };
        },

        /**
         * Definitions-Map fuer alle Bulk-Aktionen: Titel, Beschreibung, Endpoint,
         * Chunk-Groesse, etc. Saubere Trennung von dispatch + Pre-Modal-Text.
         */
        _bulkAktionDef(a) {
            const wertText = (w) => w ? ` auf „${this.linkartLabel(w) || this.empfehlungLabel(w) || w}"` : '';
            const defs = {
                linkart_setzen: {
                    titel: 'Linkart manuell setzen',
                    beschreibung: (n, w) => `Für die ${n} ausgewählten Verlinkungen wird die Linkart${wertText(w)} gesetzt.`,
                    endpoint: '/api/v1/lam/verlinkung-bulk', payloadKey: 'aktion', wertMit: true, chunkSize: 200,
                },
                empfehlung_setzen: {
                    titel: 'Empfehlung manuell setzen',
                    beschreibung: (n, w) => `Für die ${n} Verlinkungen wird die Empfehlung${wertText(w)} gesetzt.`,
                    endpoint: '/api/v1/lam/verlinkung-bulk', payloadKey: 'aktion', wertMit: true, chunkSize: 200,
                },
                topp_markieren: {
                    titel: 'Als Topp-Link markieren',
                    beschreibung: (n) => `Die ${n} ausgewählten Verlinkungen werden als Topp-Link markiert (★).`,
                    endpoint: '/api/v1/lam/verlinkung-bulk', payloadKey: 'aktion', aktionFix: 'topp_setzen', wertFix: 1, chunkSize: 200,
                },
                topp_entfernen: {
                    titel: 'Topp-Markierung entfernen',
                    beschreibung: (n) => `Bei den ${n} ausgewählten Verlinkungen wird die Topp-Markierung entfernt.`,
                    endpoint: '/api/v1/lam/verlinkung-bulk', payloadKey: 'aktion', aktionFix: 'topp_setzen', wertFix: 0, chunkSize: 200,
                },
                loeschen: {
                    titel: 'Verlinkungen löschen', danger: true,
                    beschreibung: (n) => `Die ${n} ausgewählten Verlinkungen werden soft-gelöscht (geloescht_am=NOW). Reversibel im Audit, aber sie verschwinden aus dem Linkprofil.`,
                    endpoint: '/api/v1/lam/verlinkung-bulk', payloadKey: 'aktion', wertMit: false, chunkSize: 200,
                },
                linkart_aus_wissen: {
                    titel: 'Linkart aus Wissensbasis übernehmen',
                    beschreibung: (n) => `Für die ${n} Verlinkungen wird die Linkart aus der Domain-Wissensbasis übernommen (sofern dort ein Eintrag existiert). Gratis, kein KI-Call.`,
                    endpoint: '/api/v1/lam/verlinkungen-bulk-aktionen', payloadKey: null, sammel: 'linkart_aus_wissen', chunkSize: 200,
                },
                empfehlung_aus_wissen: {
                    titel: 'Empfehlung aus Wissensbasis übernehmen',
                    beschreibung: (n) => `Für die ${n} Verlinkungen wird die Empfehlung aus der Wissensbasis übernommen. Gratis, kein KI-Call.`,
                    endpoint: '/api/v1/lam/verlinkungen-bulk-aktionen', payloadKey: null, sammel: 'empfehlung_aus_wissen', chunkSize: 200,
                },
                ki_linkart_schnell: {
                    titel: 'Linkart per KI (schnell)', maxIds: 200,
                    beschreibung: (n) => `KI klassifiziert die Linkart für ${n} Verlinkungen anhand von Domain + Linktext. Kostet KI-Tokens (~1 Cent pro 50 Eintraege).`,
                    endpoint: '/api/v1/lam/verlinkungen-klassifizieren-bulk', payloadKey: null, kiFlag: false, chunkSize: 50,
                },
                ki_linkart_crawl: {
                    titel: 'Linkart per KI (mit Crawl)', maxIds: 50,
                    beschreibung: (n) => `KI ruft jede Quellseite ab und klassifiziert die Linkart. Genauer, aber langsamer (~4 Sekunden pro URL). Kostet mehr KI-Tokens.`,
                    endpoint: '/api/v1/lam/verlinkungen-klassifizieren-bulk', payloadKey: null, kiFlag: true, chunkSize: 20,
                },
                ki_empfehlung: {
                    titel: 'Empfehlung per KI vorschlagen', maxIds: 200,
                    beschreibung: (n) => `KI bewertet die Verlinkung (lassen / ändern / disavow / löschen) anhand von SI, Linkart und Linktext. Kostet KI-Tokens.`,
                    endpoint: '/api/v1/lam/verlinkungen-bulk-aktionen', payloadKey: null, sammel: 'ki_empfehlung', chunkSize: 25,
                },
                erreichbarkeit_pruefen: {
                    titel: 'Erreichbarkeit prüfen',
                    beschreibung: (n) => `${n} Verlinkungs-URLs werden via HTTP-HEAD geprüft. Dauert ca. 2-3 Sekunden pro URL.`,
                    endpoint: '/api/v1/lam/verlinkungen-bulk-aktionen', payloadKey: null, sammel: 'erreichbarkeit', chunkSize: 25,
                },
                linktext_holen: {
                    titel: 'Linktext aus URL holen',
                    beschreibung: (n) => `${n} Quellseiten werden geladen und der Linktext aus dem &lt;a&gt;-Tag extrahiert (nur falls Verlinkung erreichbar).`,
                    endpoint: '/api/v1/lam/verlinkungen-bulk-aktionen', payloadKey: null, sammel: 'linktext', chunkSize: 25,
                },
                in_linkquellen_pool: {
                    titel: 'In Linkquellen-Pool aufnehmen',
                    beschreibung: (n) => `${n} Verlinkungen werden als Linkquellen in den Pool übernommen und dem aktuellen Kunden zugeordnet. Die ursprüngliche Verlinkungs-URL wird als Beispiellink an der Linkquelle gespeichert.`,
                    endpoint: '/api/v1/lam/verlinkungen-zu-linkquellen', payloadKey: null, sammel: null, chunkSize: 50,
                    extraPayload: () => ({ customer_id: this.aktiverKunde }),
                },
            };
            return defs[a] || null;
        },

        /**
         * Eigentlicher chunked Bulk-Lauf mit Live-Progress-Modal.
         * Nutzt die bestehende fortschritt-State + Modal-UI.
         */
        async _bulkRun(aktionsName, ids, def) {
            Object.assign(this.fortschritt, {
                offen: true, label: def.titel, total: ids.length, done: 0,
                erfolge: 0, fehler: [], extra: '', abbrechen: false, fertig: false, fehlerOffen: false,
            });
            const chunkSize = def.chunkSize || 100;
            for (let i = 0; i < ids.length; i += chunkSize) {
                if (this.fortschritt.abbrechen) break;
                const chunk = ids.slice(i, i + chunkSize);
                try {
                    let body;
                    if (def.endpoint === '/api/v1/lam/verlinkung-bulk') {
                        const wert = (def.wertFix !== undefined) ? def.wertFix : (def.wertMit ? (this.bulkWert || null) : null);
                        body = { ids: chunk, aktion: def.aktionFix || aktionsName, wert };
                    } else if (def.endpoint === '/api/v1/lam/verlinkungen-bulk-aktionen') {
                        body = { aktion: def.sammel, ids: chunk };
                    } else if (def.endpoint === '/api/v1/lam/verlinkungen-klassifizieren-bulk') {
                        body = { ids: chunk, mit_crawl: !!def.kiFlag };
                    } else {
                        body = { ids: chunk };
                    }
                    // Extra-Payload (z.B. customer_id für in_linkquellen_pool)
                    if (typeof def.extraPayload === 'function') {
                        Object.assign(body, def.extraPayload());
                    }
                    const r = await fetch(def.endpoint, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const j = await r.json();
                    if (!j.success) throw new Error(j.message || 'Chunk fehlgeschlagen');
                    const data = j.data || {};
                    const ok = typeof data.erfolge === 'number' ? data.erfolge :
                               typeof data.ok === 'number' ? data.ok :
                               typeof data.geaendert === 'number' ? data.geaendert :
                               chunk.length;
                    this.fortschritt.erfolge += ok;
                    if (Array.isArray(data.fehler_liste) && data.fehler_liste.length) {
                        this.fortschritt.fehler.push(...data.fehler_liste);
                    } else if (typeof data.fehler === 'number' && data.fehler > 0) {
                        this.fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${data.fehler} Fehler`);
                    }
                    const standParts = [];
                    if (typeof data.cache_hits === 'number') standParts.push(`${data.cache_hits} aus Cache`);
                    if (typeof data.kein_wissen === 'number') standParts.push(`${data.kein_wissen} ohne Wissens-Eintrag`);
                    if (standParts.length) this.fortschritt.extra = standParts.join(' · ');
                } catch (e) {
                    this.fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${e.message || 'Netzwerkfehler'}`);
                }
                this.fortschritt.done = Math.min(i + chunkSize, ids.length);
                // Kein requestAnimationFrame: feuert nicht im Hintergrund-Tab -> Schleife blieb haengen.
                await new Promise(r => setTimeout(r, 50));
            }
            this.fortschritt.fertig = true;
            await this.reload();
        },
        async _bulkAktion(aktion, ids) {
            const res = await fetch('/api/v1/lam/verlinkungen-bulk-aktionen', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aktion, ids }),
            });
            const j = await res.json();
            if (!j.success) throw new Error(j.message || 'Fehler');
            return j.data;
        },
        _zeigeBulkErgebnis(aktion, r) {
            let text = '';
            if (r.ok !== undefined)              text += 'OK: ' + r.ok;
            if (r.fehler)                        text += '  Fehler: ' + r.fehler;
            if (r.uebersprungen)                 text += '  Uebersprungen: ' + r.uebersprungen;
            if (r.kein_wissen)                   text += '  Kein Wissens-Eintrag: ' + r.kein_wissen;
            if (r.kein_domain_record)            text += '  Keine Domain in DB: ' + r.kein_domain_record;
            if (r.geprueft_urls !== undefined)   text += '  Unique URLs: ' + r.geprueft_urls;
            if (text) App.showNotification(text, r.fehler > 0 ? 'warning' : 'success');
        },
        async _bulkSimple(aktion, wert, ids) {
            const res = await fetch('/api/v1/lam/verlinkung-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, aktion, wert: wert || null })
            });
            const j = await res.json();
            if (!j.success) throw new Error(j.message || 'Fehler');
        },
        async _bulkKiLinkart(mitCrawl, ids) {
            const res = await fetch('/api/v1/lam/verlinkungen-klassifizieren-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, mit_crawl: mitCrawl })
            });
            const j = await res.json();
            if (!j.success) throw new Error(j.message || 'Fehler');
        },

        async bulkAusfuehren() {
            if (this.bulkLaeuft || !this.bulkAktion || this.auswahl.size === 0) return;
            if (this.bulkAktion !== 'loeschen' && !this.bulkWert) return;
            if (this.bulkAktion === 'loeschen' && !confirm(`${this.auswahl.size} Verlinkungen wirklich löschen?`)) return;
            this.bulkLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/verlinkung-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: this.bulkAktion, wert: this.bulkWert || null })
                });
                if ((await res.json()).success) { this.auswahlLeeren(); await this.reload(); }
            } finally { this.bulkLaeuft = false; }
        },

        async kiKlassifizieren(mitCrawl) {
            if (this.bulkLaeuft || this.auswahl.size === 0) return;
            const max = mitCrawl ? 50 : 200;
            if (this.auswahl.size > max) {
                alert(`Bitte max ${max} Verlinkungen für ${mitCrawl ? 'tiefe' : 'schnelle'} KI-Klassifikation auswählen.`);
                return;
            }
            const dauer = mitCrawl
                ? `~${Math.ceil(this.auswahl.size * 4)} Sekunden`
                : `~${Math.ceil(this.auswahl.size * 0.7)} Sekunden`;
            if (!confirm(`${this.auswahl.size} Verlinkungen ${mitCrawl ? 'tief (mit Crawl)' : 'schnell'} klassifizieren? Dauert etwa ${dauer}.`)) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/verlinkungen-klassifizieren-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), mit_crawl: mitCrawl }),
                });
                const j = await r.json();
                if (j.success) {
                    let msg = `KI fertig: ${j.data.ok} klassifiziert, ${j.data.fehler} Fehler.`;
                    if (j.data.fehler_liste?.length) msg += '\n\nFehler:\n' + j.data.fehler_liste.slice(0, 5).join('\n');
                    alert(msg);
                    this.auswahlLeeren();
                    await this.reload();
                } else {
                    alert(j.message || 'KI-Klassifikation fehlgeschlagen.');
                }
            } finally { this.bulkLaeuft = false; }
        },

        // ─ Rechtsklick ─────────────────────────────────────────────────
        oeffneCtxMenu(event, ziel) {
            const x = event.clientX, y = event.clientY;
            const px = (x + 240 > window.innerWidth) ? x - 240 : x;
            const py = (y + 400 > window.innerHeight) ? y - 400 : y;
            this.ctxMenu = { offen: true, x: px, y: py, ziel };
        },
        async schnellAktion(ziel, feld, wert) {
            if (!ziel) return;
            try {
                const res = await fetch('/api/v1/lam/verlinkung-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld, wert })
                });
                if ((await res.json()).success) ziel[feld] = wert;
            } catch (e) {}
        },
        async toggleTopp(ziel) {
            if (!ziel) return;
            const neu = (ziel.ist_topp == 1) ? 0 : 1;
            try {
                const res = await fetch('/api/v1/lam/verlinkung-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld: 'ist_topp', wert: neu })
                });
                if ((await res.json()).success) ziel.ist_topp = neu;
            } catch (e) {}
        },
        async loescheVerlinkung(ziel) {
            if (!ziel) return;
            if (!confirm(`Verlinkung von "${ziel.domain}" wirklich löschen?`)) return;
            await fetch('/api/v1/lam/verlinkung-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [ziel.id], aktion: 'loeschen' })
            });
            await this.reload();
        },

        linkartLabel(w) {
            const m = this.linkarten.find(x => x.wert === w);
            return m ? m.label : w;
        },
        async ladeKundenLinkarten() {
            if (!this.aktiverKunde) { this.linkartenKunde = []; return; }
            try {
                const r = await fetch('/api/v1/lam/kunden-linkarten?customer_id=' + encodeURIComponent(this.aktiverKunde), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.linkartenKunde = j.data.linkarten || [];
            } catch (e) { /* still */ }
        },
        empfehlungLabel(w) {
            const m = this.empfehlungen.find(x => x.wert === w);
            return m ? m.label : w;
        },
        erreichbarkeitKlasse(v) {
            if (v.letzter_http_erreichbar === null) return '';
            if (v.letzter_http_erreichbar == 0) return 'error';
            if (v.linkziel_gefunden !== null && v.linkziel_gefunden == 0) return 'warn';
            return 'ok';
        },
        erreichbarkeitTitel(v) {
            if (v.letzter_http_erreichbar === null) return 'noch nicht geprüft';
            if (v.letzter_http_erreichbar == 0) return 'nicht erreichbar (HTTP ' + (v.letzter_http_status || '?') + ')';
            if (v.linkziel_gefunden !== null && v.linkziel_gefunden == 0) return 'erreichbar, Linkziel nicht mehr im HTML';
            return 'erreichbar (HTTP ' + (v.letzter_http_status || '200') + ')';
        },
        quelleKurz(q) {
            if (!q) return '—';
            const map = { sistrix: 'Sistrix', ahrefs: 'AHREFs', xovi: 'XOVI', gsc: 'GSC', manuell: 'Manuell', historie: 'Historie' };
            return map[String(q).toLowerCase()] || q;
        },
        kurzUrl(u) {
            if (!u) return '';
            return u.replace(/^https?:\/\//, '').replace(/\/$/, '');
        },
        kopiereURL(url) {
            if (!url) return;
            navigator.clipboard?.writeText(url).catch(() => alert('Konnte nicht kopieren'));
        },
        googleDeepUrl(url, linktext) {
            // Suche nach Quell-URL + (optional) Linktext, damit man schnell prüfen kann,
            // ob der Backlink tatsächlich noch im Index ist.
            try {
                const host = new URL(url).hostname.replace(/^www\./, '');
                const parts = ['site:' + host];
                if (linktext) parts.push('"' + linktext.replace(/"/g, '') + '"');
                return 'https://www.google.com/search?q=' + encodeURIComponent(parts.join(' '));
            } catch (e) {
                return 'https://www.google.com/search?q=' + encodeURIComponent(url);
            }
        },
        zahl(n) { return n == null ? '0' : new Intl.NumberFormat('de-DE').format(n); },

        /* ============================================================
         *  Sistrix-Pre-Confirm-Modal (Kosten + Budget vor Bulk-Lauf)
         * ============================================================ */
        async sistrixPreOeffnen(teil, ids) {
            const labels = { si: 'SI', dp: 'DP', alter: 'Alter' };
            Object.assign(this.sistrixPre, {
                offen: true,
                teil: teil,
                label: labels[teil] || teil.toUpperCase(),
                ids: ids.slice(),
                laedt: true,
                vorschau: null,
                status: null,
                budgetReicht: true,
            });
            try {
                const res = await fetch('/api/v1/lam/sistrix-vorschau', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: ids, teile: [teil] }),
                });
                const j = await res.json();
                if (!j.success) throw new Error(j.message || 'Vorschau fehlgeschlagen');
                this.sistrixPre.vorschau = j.data.vorschau;
                this.sistrixPre.status = j.data.status;
                this.sistrixPre.budgetReicht = !!j.data.budget_reicht;
            } catch (e) {
                this.sistrixPre.offen = false;
                App.showNotification('Konnte Vorschau nicht laden: ' + e.message, 'error');
            } finally {
                this.sistrixPre.laedt = false;
            }
        },
        sistrixPreSchliessen() {
            this.sistrixPre.offen = false;
        },
        async sistrixPreAnwenden() {
            const teil = this.sistrixPre.teil;
            const ids  = this.sistrixPre.ids.slice();
            const label = `Sistrix abrufen: ${this.sistrixPre.label}`;
            // Pre-Modal schliessen, Live-Modal oeffnet sich in bulkInChunks
            this.sistrixPre.offen = false;
            await this.bulkInChunks({
                aktion: teil === 'si' ? 'sistrix_si' : (teil === 'dp' ? 'sistrix_dp' : 'sistrix_si'),
                ids: ids,
                label: label,
                chunkSize: 10, // kleinere Chunks weil Sistrix langsam
            });
        },

        /* ============================================================
         *  Chunked Bulk-Loop mit Live-Fortschritts-Modal + Abbrechen
         * ============================================================ */
        async bulkInChunks({ aktion, ids, label, chunkSize = 25 }) {
            if (!ids || !ids.length) return;
            Object.assign(this.fortschritt, {
                offen: true, label: label, total: ids.length, done: 0,
                erfolge: 0, fehler: [], extra: '', abbrechen: false, fertig: false, fehlerOffen: false,
            });
            let creditsVerbraucht = 0;
            let cacheHitsGesamt  = 0;
            const aktualisiereStand = () => {
                const parts = [];
                if (creditsVerbraucht > 0) parts.push(creditsVerbraucht.toLocaleString('de-DE') + ' Credits verbraucht');
                if (cacheHitsGesamt  > 0) parts.push(cacheHitsGesamt + ' aus Cache');
                this.fortschritt.extra = parts.join(' · ');
            };
            for (let i = 0; i < ids.length; i += chunkSize) {
                if (this.fortschritt.abbrechen) break;
                const chunk = ids.slice(i, i + chunkSize);
                try {
                    const res = await fetch('/api/v1/lam/verlinkungen-bulk-aktionen', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ aktion: aktion, ids: chunk }),
                    });
                    const j = await res.json();
                    if (!j.success) throw new Error(j.message || 'Chunk fehlgeschlagen');
                    const data = j.data || {};
                    const ok = (typeof data.erfolge === 'number' ? data.erfolge :
                                (typeof data.ok === 'number' ? data.ok : chunk.length));
                    this.fortschritt.erfolge += ok;
                    if (Array.isArray(data.fehler_liste) && data.fehler_liste.length) {
                        this.fortschritt.fehler.push(...data.fehler_liste);
                    } else if (typeof data.fehler === 'number' && data.fehler > 0) {
                        this.fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${data.fehler} Fehler`);
                    }
                    if (typeof data.credits_verbraucht === 'number') creditsVerbraucht += data.credits_verbraucht;
                    if (typeof data.cache_hits === 'number')         cacheHitsGesamt  += data.cache_hits;
                    aktualisiereStand();
                    if (data.abgebrochen) {
                        this.fortschritt.fehler.push('Wochenkontingent erschoepft, restliche Chunks uebersprungen.');
                        this.fortschritt.done = Math.min(i + chunk.length, ids.length);
                        break;
                    }
                } catch (e) {
                    this.fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${e.message || 'Netzwerkfehler'}`);
                }
                this.fortschritt.done = Math.min(i + chunkSize, ids.length);
                // Browser-Repaint zwischen schnellen Chunks erzwingen, damit der
                // Balken wirklich animiert statt direkt auf 100% zu springen.
                // Kein requestAnimationFrame: feuert nicht im Hintergrund-Tab -> Schleife blieb haengen.
                await new Promise(r => setTimeout(r, 50));
            }
            this.fortschritt.fertig = true;
            await this.reload();
        },
        fortschrittSchliessen() {
            this.fortschritt.offen = false;
            if (!this.fortschritt.abbrechen) this.auswahlLeeren();
        },
    };
}

// Spaltenbreiten-Steuerung (drag, dblclick, contextmenu) — pro Tabelle eigener Storage-Key.
// table-layout:fixed + Default-Breiten = stabile Spalten unabhaengig von Filter/Kunde.
// User-Anpassungen via Drag gelten global fuer alle Kunden (localStorage).
(function trWaitForColResize(tries) {
    tries = tries || 0;
    const tbl = document.getElementById('lam-linkprofil-table');
    if (tbl && window.thxColResize) {
        window.thxColResize.install({
            table: tbl,
            storageKey: 'lam-linkprofil-cols-v3',
            // Reihenfolge: Bulk, URL, Domain, Anzahl, Linktext, Linkart, Erreichbar,
            //              Sistrix, Pop, Empfehlung, Bemerkung, Neu, Quelle
            defaults: ['36px','280px','200px','50px','220px','140px','60px','60px','60px','130px','220px','55px','90px'],
        });
        return;
    }
    if (tries < 50) setTimeout(() => trWaitForColResize(tries + 1), 50);
    else console.warn('[Linkprofil] thxColResize nicht verfuegbar nach 2.5s — Asset fehlt?');
})();
</script>
