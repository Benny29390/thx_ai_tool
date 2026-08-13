<?php $activeModul = 'linkquellen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamLinkquellen()" x-init="init()" @click="ctxMenu.offen = false">

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Linkquellen</h1>
        <div class="thx-page-subtitle">Pool aller Domains. Filtern, durchsuchen, Detail öffnen, Inline editieren.</div>
    </div>
    <div class="thx-page-actions">
        <a class="lam-btn lam-btn-secondary" href="/lam/tags" title="Tag-Verwaltung — Tags anlegen, umbenennen, zusammenführen">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;margin-right:4px;">label</span>
            Tags
        </a>
        <button class="lam-btn lam-btn-secondary" @click="lqImport.offen = true" title="XLSX/CSV mit URL-Spalte importieren — direkter Linkquellen-Import">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;margin-right:4px;">upload_file</span>
            Linkquellen-Import
        </button>
        <button class="lam-btn lam-btn-secondary" @click="piOeffnen()" title="Anbieter-Mediadaten (E-Mail/PDF/Excel) per KI importieren">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;margin-right:4px;">auto_awesome</span>
            Portfolio importieren
        </button>
        <button class="lam-btn lam-btn-primary" @click="oeffneNeuDrawer()">+ Neue Linkquelle</button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

    <!-- Filter -->
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                      x-text="gesamt > 0 ? (zahl(gesamt) + ' Treffer') : ''"></span>
                <button type="button" @click="filterZuruecksetzen()"
                        style="font-size:0.75rem;color:var(--slate-500);background:none;border:0;cursor:pointer;text-decoration:underline;">
                    zurücksetzen
                </button>
            </div>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Volltext URL / Notiz</label>
                <input type="text" class="lam-filter-input" placeholder="z.B. blogspot"
                       x-model="filter.suche" @input.debounce.300ms="reload(true)">
            </div>
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Anbieter</label>
                <select class="lam-filter-select" x-model="filter.anbieter_id" @change="reload(true)">
                    <option value="">alle Anbieter</option>
                    <option value="__ohne__">ohne Anbieter</option>
                    <template x-for="a in anbieter" :key="a.id">
                        <option :value="a.id" x-text="a.name"></option>
                    </template>
                </select>
            </div>
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Kunde (Linkpool)</label>
                <select class="lam-filter-select" x-model="filter.customer_id" @change="reload(true)">
                    <option value="">alle Kunden</option>
                    <option value="__ohne__">ohne Kunde</option>
                    <template x-for="k in kunden" :key="k.id">
                        <option :value="k.id" x-text="k.name"></option>
                    </template>
                </select>
            </div>
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Pro Seite</label>
                <select class="lam-filter-select" x-model="filter.limit" @change="reload(true)">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250">250</option>
                    <option value="500">500</option>
                </select>
            </div>

            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Verifikation <span class="lam-filter-hint">Klick = nur dieser · Shift = mehrere</span></label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-reset" :class="filter.verifikation.length === 0 ? 'is-active' : ''" @click="filter.verifikation = []; reload(true)">alle</button>
                    <template x-for="s in verifikationStatus" :key="s">
                        <button type="button" :class="'lam-chip lam-chip-status-' + s + (filter.verifikation.includes(s) ? ' is-active' : '')" @click="toggleVerifikation(s, $event)" x-text="s"></button>
                    </template>
                </div>
            </div>

            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Linkart <span class="lam-filter-hint">Klick = nur dieser · Shift = mehrere</span></label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-reset" :class="filter.linkart.length === 0 ? 'is-active' : ''" @click="filter.linkart = []; reload(true)">alle</button>
                    <template x-for="la in linkartListe" :key="la.slug">
                        <button type="button" class="lam-chip" :class="filter.linkart.includes(la.slug) ? 'is-active' : ''" @click="toggleLinkart(la.slug, $event)" x-text="la.label"></button>
                    </template>
                </div>
            </div>

            <div class="lam-filter-col-12" x-show="alleTags.length > 0">
                <label class="lam-filter-label">Tags <span class="lam-filter-hint">Mehrfachauswahl</span></label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-reset" :class="filter.tag_ids.length === 0 ? 'is-active' : ''" @click="filter.tag_ids = []; reload(true)">alle</button>
                    <template x-for="t in alleTags" :key="t.id">
                        <button type="button" class="lam-chip" :class="filter.tag_ids.includes(t.id) ? 'is-active' : ''" @click="toggleTagFilter(t.id, $event)" x-text="t.name"></button>
                    </template>
                </div>
            </div>

            <div class="lam-filter-col-6">
                <label class="lam-filter-label">SI-Bereich</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" step="0.1" min="0" class="lam-filter-input" placeholder="min"
                           x-model="filter.si_min" @input.debounce.400ms="reload(true)" style="width:50%;">
                    <span class="muted">bis</span>
                    <input type="number" step="0.1" min="0" class="lam-filter-input" placeholder="max"
                           x-model="filter.si_max" @input.debounce.400ms="reload(true)" style="width:50%;">
                </div>
            </div>
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Preis-Bereich (€)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" step="10" min="0" class="lam-filter-input" placeholder="min"
                           x-model="filter.preis_min" @input.debounce.400ms="reload(true)" style="width:50%;">
                    <span class="muted">bis</span>
                    <input type="number" step="10" min="0" class="lam-filter-input" placeholder="max"
                           x-model="filter.preis_max" @input.debounce.400ms="reload(true)" style="width:50%;">
                </div>
            </div>

            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Zusätzliche Optionen</label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_disqualifiziert ? 'is-active' : ''" @click="filter.nur_disqualifiziert = !filter.nur_disqualifiziert; reload(true)">nur disqualifiziert</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_nicht_erreichbar ? 'is-active' : ''" @click="filter.nur_nicht_erreichbar = !filter.nur_nicht_erreichbar; reload(true)">nur nicht erreichbar</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_ungeprueft ? 'is-active' : ''" @click="filter.nur_ungeprueft = !filter.nur_ungeprueft; reload(true)">nur ungeprüft</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_si ? 'is-active' : ''"
                            @click="filter.ohne_si = !filter.ohne_si; reload(true)"
                            title="Noch keine Sichtbarkeit (SI) erfasst — genau die, die in der SI-Spalte „—“ zeigen">ohne SI</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.ohne_dp ? 'is-active' : ''"
                            @click="filter.ohne_dp = !filter.ohne_dp; reload(true)"
                            title="Noch keine Domain-Popularität (DP) erfasst">ohne DP</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_in_wartezeit ? 'is-active' : ''" @click="filter.nur_in_wartezeit = !filter.nur_in_wartezeit; reload(true)" title="Domains mit aktiver Wartezeit (Mindestabstand nach Buchung)">in Wartezeit</button>
                    <button type="button" class="lam-chip lam-chip-ohne" :class="filter.nur_verfuegbar ? 'is-active' : ''" @click="filter.nur_verfuegbar = !filter.nur_verfuegbar; reload(true)" title="Wartezeit abgelaufen oder nie gesetzt">verfügbar</button>
                </div>
            </div>
        </div>
    </section>

    <!-- „Alle X Treffer auswählen"-Banner — sobald gefilterte Liste >0 und nichts ausgewählt ist -->
    <div x-show="!alleTrefferAusgewaehlt && rows.length > 0 && auswahl.size === 0 && (filter.suche || filter.verifikation.length || filter.linkart.length || filter.tag_ids.length || filter.anbieter_id || filter.customer_id || filter.nur_disqualifiziert || filter.nur_nicht_erreichbar || filter.nur_ungeprueft || filter.ohne_si || filter.ohne_dp || filter.si_min !== '' || filter.si_max !== '' || filter.preis_min !== '' || filter.preis_max !== '')" x-cloak
         style="margin:8px 0;padding:12px 18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1e40af;font-size:var(--d-fs-sm);display:flex;justify-content:space-between;align-items:center;gap:14px;">
        <span>
            <strong x-text="zahl(gesamt)"></strong> Treffer mit diesen Filtern.
            Schnellaktion: alles auf einmal selektieren und bulk-bearbeiten.
        </span>
        <button class="lam-btn lam-btn-small" style="background:#1e40af;color:#fff;border:0;white-space:nowrap;" @click="alleTrefferAuswaehlen()">
            ✓ Alle <span x-text="zahl(gesamt)"></span> Treffer auswählen
        </button>
    </div>
    <!-- Sichtbar-Seite ausgewählt: Hinweis auf „auch die anderen Seiten" -->
    <div x-show="alleSichtbarGewaehlt() && gesamt > rows.length && !alleTrefferAusgewaehlt" x-cloak
         style="margin:8px 0;padding:10px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;color:#92400e;font-size:var(--d-fs-sm);display:flex;justify-content:space-between;align-items:center;">
        <span>
            Die <strong x-text="rows.length"></strong> sichtbaren Treffer sind ausgewählt. Insgesamt gibt es
            <strong x-text="zahl(gesamt)"></strong> Treffer.
        </span>
        <button class="lam-btn lam-btn-small" style="background:#92400e;color:#fff;border:0;" @click="alleTrefferAuswaehlen()">
            Alle <span x-text="zahl(gesamt)"></span> auswählen
        </button>
    </div>
    <div x-show="alleTrefferAusgewaehlt" x-cloak
         style="margin:8px 0;padding:10px 16px;background:var(--emerald-50);border:1px solid var(--emerald-200);border-radius:8px;color:var(--emerald-800);font-size:var(--d-fs-sm);display:flex;justify-content:space-between;align-items:center;">
        <span>✓ Alle <strong x-text="zahl(gesamt)"></strong> Treffer ausgewählt — Bulk-Aktionen wirken auf die gesamte gefilterte Liste.</span>
        <button class="lam-btn lam-btn-small lam-btn-secondary" @click="auswahlLeeren()">Aufheben</button>
    </div>

    <!-- Bulk-Toolbar -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
        <span class="thx-bulk-count"><span x-text="auswahl.size"></span> ausgewählt</span>
        <span class="thx-divider"></span>
        <select x-model="bulkAktion" class="lam-filter-select" style="width:auto;">
            <option value="">Aktion …</option>
            <option value="verifikation_setzen">Verifikation setzen</option>
            <option value="anbieter_setzen">Anbieter setzen</option>
            <option value="tag_setzen">Tag zuweisen</option>
            <option value="tag_entfernen">Tag entfernen</option>
            <option value="disqualifizieren">Disqualifizieren</option>
            <option value="rehabilitieren">Rehabilitieren</option>
            <option value="loeschen">Löschen (soft)</option>
        </select>
        <select x-show="bulkAktion === 'verifikation_setzen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Wert —</option>
            <template x-for="s in verifikationStatus" :key="s"><option :value="s" x-text="s"></option></template>
        </select>
        <select x-show="bulkAktion === 'anbieter_setzen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Anbieter —</option>
            <template x-for="a in anbieter" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
        </select>
        <select x-show="bulkAktion === 'tag_setzen' || bulkAktion === 'tag_entfernen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Tag —</option>
            <template x-for="t in alleTags" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
        </select>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="bulkAusfuehren()" :disabled="bulkLaeuft || !bulkAktion || (['verifikation_setzen','anbieter_setzen','tag_setzen','tag_entfernen'].includes(bulkAktion) && !bulkWert)">
            <span x-show="!bulkLaeuft">Anwenden</span><span x-show="bulkLaeuft">…</span>
        </button>

        <span class="thx-divider"></span>

        <!-- Sistrix-Bulk -->
        <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;">Sistrix:</span>
        <button class="lam-btn lam-btn-secondary lam-btn-small"
                @click="sistrixBulk('si')" :disabled="bulkLaeuft"
                title="Sichtbarkeitsindex pro Domain (1 Credit, gecacht 1×/Tag)">
            SI · <span x-text="auswahl.size"></span>
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small"
                @click="sistrixBulk('alter')" :disabled="bulkLaeuft"
                title="Sichtbar-seit-Datum (10 Credits pro Domain)">
            Alter · <span x-text="auswahl.size * 10"></span>
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small"
                @click="sistrixBulk('dp')" :disabled="bulkLaeuft"
                title="Verlinkende Domains (25 Credits pro Domain)">
            DP · <span x-text="auswahl.size * 25"></span>
        </button>
        <button class="lam-btn lam-btn-accent lam-btn-small"
                @click="sistrixBulk('alles')" :disabled="bulkLaeuft"
                title="Alles in einem Rutsch (36 Credits pro Domain)">
            Alles · <span x-text="auswahl.size * 36"></span>
        </button>

        <span class="thx-divider"></span>

        <button class="lam-btn lam-btn-secondary lam-btn-small"
                @click="erreichbarkeitBulk()" :disabled="bulkLaeuft || auswahl.size > 500"
                title="HTTP-HEAD-Check für alle ausgewählten Domains (kostenlos, ~200ms pro Domain)">
            🩺 Erreichbarkeit · <span x-text="auswahl.size"></span>
        </button>

        <span class="thx-divider"></span>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneLinkpoolAdd()" :disabled="bulkLaeuft"
                title="Ausgewählte Domains zum Linkpool eines Kunden hinzufügen (= ‚parken‘, ohne sie schon konkret vorzuschlagen)">
            ➕ Zu Linkpool · <span x-text="auswahl.size"></span>
        </button>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="oeffneVorschlagslisteAdd()" :disabled="bulkLaeuft"
                title="Ausgewählte Domains auf eine Vorschlagsliste setzen (legt auch Linkpool-Mitgliedschaft an)">
            📋 Auf Vorschlagsliste · <span x-text="auswahl.size"></span>
        </button>

        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <!-- Modal: Zu Linkpool eines Kunden hinzufügen -->
    <div class="thx-modal-backdrop" x-show="addPool.offen" x-cloak @click.self="addPool.offen = false">
        <div class="thx-modal" style="max-width:540px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title">➕ Zu Linkpool hinzufügen</h2>
                <button class="thx-modal-close" @click="addPool.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                <div style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    <strong x-text="addPool.domainIds.length"></strong> Domain(s) zum Linkpool des Kunden hinzufügen.
                    Dort kannst Du sie später per Filter durchsuchen und konkret auf Vorschlagslisten setzen.
                </div>
                <div class="thx-form-field">
                    <label>Kunde *</label>
                    <select x-model="addPool.customerId" :disabled="addPool.laeuft">
                        <option value="">— Kunde wählen —</option>
                        <template x-for="k in addPool.kunden" :key="k.id">
                            <option :value="k.id" x-text="(k.abbreviation ? k.abbreviation + ' · ' : '') + k.name + ' (' + (k.linkpool_count || 0) + ' im Pool)'"></option>
                        </template>
                    </select>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button class="lam-btn lam-btn-secondary" @click="addPool.offen = false">Abbrechen</button>
                    <button class="lam-btn lam-btn-primary" @click="addPoolAusfuehren()" :disabled="!addPool.customerId || addPool.laeuft">
                        <span x-show="!addPool.laeuft" x-text="addPool.domainIds.length + ' zum Linkpool hinzufügen'"></span>
                        <span x-show="addPool.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Auf Vorschlagsliste setzen -->
    <div class="thx-modal-backdrop" x-show="addVL.offen" x-cloak @click.self="addVL.offen = false">
        <div class="thx-modal" style="max-width:560px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title">Auf Vorschlagsliste setzen</h2>
                <button class="thx-modal-close" @click="addVL.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                <div style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    <strong x-text="addVL.domainIds.length"></strong> Domain(s) werden auf die gewählte Vorschlagsliste gesetzt (Status: vorgeschlagen).
                </div>
                <div class="thx-form-field">
                    <label>Vorschlagsliste *</label>
                    <select x-model="addVL.listenId" :disabled="addVL.laedt">
                        <option value="">— wählen —</option>
                        <template x-for="l in addVL.listen" :key="l.id">
                            <option :value="l.id" x-text="(l.customer_kuerzel ? l.customer_kuerzel + ' · ' : '') + l.name + ' (' + (l.eintrag_count || 0) + ' Einträge)'"></option>
                        </template>
                    </select>
                    <div x-show="!addVL.laedt && addVL.listen.length === 0" style="font-size:var(--d-fs-xs);color:var(--rose-700);margin-top:4px;">
                        Noch keine Vorschlagslisten vorhanden — bitte <a href="/lam/vorschlagslisten" style="color:var(--thoxan-700);">hier eine anlegen</a>.
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Artikelthema (optional, gilt für alle gewählten)</label>
                    <input type="text" x-model="addVL.artikelthema" placeholder="z.B. Lebensphasen 50plus">
                </div>
                <div class="thx-form-field">
                    <label>Notiz (optional)</label>
                    <textarea x-model="addVL.notiz" rows="2" placeholder="Zusatzinfo für die Akquise"></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button class="lam-btn lam-btn-secondary" @click="addVL.offen = false">Abbrechen</button>
                    <button class="lam-btn lam-btn-primary" @click="vorschlagslisteAddAusfuehren()" :disabled="!addVL.listenId || addVL.laeuft">
                        <span x-show="!addVL.laeuft">Hinzufügen</span><span x-show="addVL.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabelle -->
    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th class="thx-bulk-col">
                            <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                        </th>
                        <th class="sortable" :class="sortKlasse('url')" @click="sortBy('url')">URL <span class="sort-icon" x-text="sortPfeil('url')"></span></th>
                        <th class="sortable" :class="sortKlasse('anbieter')" @click="sortBy('anbieter')">Anbieter <span class="sort-icon" x-text="sortPfeil('anbieter')"></span></th>
                        <th>Tags</th>
                        <th class="right">SI / DP</th>
                        <th class="right">Preis</th>
                        <th class="sortable" :class="sortKlasse('verifikation_status')" @click="sortBy('verifikation_status')">Status <span class="sort-icon" x-text="sortPfeil('verifikation_status')"></span></th>
                        <th>Kunden</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="d in rows" :key="d.id">
                        <tr :class="auswahl.has(d.id) ? 'is-bulk-selected' : ''"
                            @contextmenu.prevent="oeffneCtxMenu($event, d)">
                            <td class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(d.id)" @change="toggleAuswahl(d.id)" @click.stop>
                            </td>
                            <td class="url-cell">
                                <a :href="'/lam/linkquellen/' + encodeURIComponent(d.id)" x-text="d.url" style="color:var(--thoxan-700);"></a>
                                <a :href="extUrl(d.url)" target="_blank" rel="noopener" @click.stop
                                   title="Website in neuem Tab öffnen"
                                   style="color:var(--slate-400);text-decoration:none;padding:0 4px;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">open_in_new</span>
                                </a>
                                <a :href="'/lam/linkquellen/' + encodeURIComponent(d.id)" title="Detail-Ansicht öffnen"
                                   style="color:var(--slate-400);text-decoration:none;padding:0 4px;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">edit</span>
                                </a>
                                <button type="button" @click.stop="kopiereURL(d.url)" title="URL kopieren"
                                        style="background:none;border:none;cursor:pointer;color:var(--slate-400);padding:0 4px;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">content_copy</span>
                                </button>
                                <span x-show="d.notiz_kurz" :title="d.notiz_kurz" style="color:var(--amber-600);padding:0 2px;cursor:help;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">sticky_note_2</span>
                                </span>
                                <template x-if="d.disqualifiziert == 1">
                                    <span class="muted" style="color:var(--rose-600);display:block;">disqualifiziert</span>
                                </template>
                                <template x-if="d.letzter_http_erreichbar !== null && d.letzter_http_erreichbar == 0">
                                    <span style="display:block;"><span class="lam-dot error"></span>
                                        <span class="muted">nicht erreichbar (HTTP <span x-text="d.letzter_http_status"></span>)</span>
                                    </span>
                                </template>
                            </td>
                            <!-- Anbieter (Betreiber prominent, Vermittler kompakt darunter) -->
                            <td>
                                <template x-if="!istOffen(d.id, 'anbieter_id')">
                                    <div>
                                        <button class="thx-inline-edit" :class="!d.anbieter_name ? 'is-empty' : ''"
                                                @click="oeffneEdit(d, 'anbieter_id')"
                                                style="font-weight:600;display:flex;align-items:center;gap:6px;text-align:left;width:100%;flex-wrap:wrap;">
                                            <span x-text="d.anbieter_name || '— Betreiber zuweisen'"></span>
                                            <span x-show="parseInt(d.anbieter_ist_vermittler) === 1" class="lam-chip"
                                                  style="background:var(--amber-100);color:var(--amber-800);font-size:0.62rem;padding:1px 6px;font-weight:600;letter-spacing:0.02em;"
                                                  title="Anbieter ist nur Vermittler — kein echter Betreiber bekannt">Vermittler</span>
                                        </button>
                                        <template x-if="d.vermittler_namen">
                                            <div style="font-size:0.72rem;color:var(--slate-500);margin-top:2px;line-height:1.2;">
                                                über
                                                <span x-text="d.vermittler_namen.split('|').slice(0, 2).join(', ')"></span>
                                                <span x-show="d.vermittler_namen.split('|').length > 2" x-text="' (+' + (d.vermittler_namen.split('|').length - 2) + ')'"></span>
                                                <span x-show="d.vermittler_preis_min" x-text="' · ab ' + parseFloat(d.vermittler_preis_min).toFixed(0) + ' €'"
                                                      style="color:var(--slate-600);"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="istOffen(d.id, 'anbieter_id')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="">— ohne —</option>
                                            <template x-for="a in anbieter" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(d, 'anbieter_id')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <button class="thx-inline-edit" style="text-align:left;width:100%;" @click="oeffneTagEdit(d)">
                                    <template x-if="d.tags">
                                        <span>
                                            <template x-for="t in d.tags.split('|')" :key="t">
                                                <span class="lam-badge lam-badge-tag" style="margin-right:4px;" x-text="t"></span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="!d.tags"><span class="empty">+ Tags</span></template>
                                </button>
                            </td>
                            <td class="right" :title="d.si_aktuell_am ? ('Letzte Sistrix-Abfrage: ' + d.si_aktuell_am) : 'noch keine Sistrix-Daten'">
                                <template x-if="d.si_aktuell !== null || d.dp_aktuell !== null">
                                    <div style="text-align:right;">
                                        <span class="si-cell">
                                            <span x-text="d.si_aktuell !== null ? zahlSi(d.si_aktuell) : '—'"></span>
                                            <span class="muted"> / <span x-text="d.dp_aktuell !== null ? d.dp_aktuell : '—'"></span></span>
                                            <button @click.stop="sistrixAktualisieren(d.id)" title="SI/DP neu abrufen (Sistrix-Credits)"
                                                    style="background:none;border:0;cursor:pointer;color:var(--slate-400);padding:1px 4px;margin-left:2px;">↻</button>
                                        </span>
                                        <div class="si-cell-datum" x-show="d.si_aktuell_am" x-text="'Check: ' + formatiereCheckDatum(d.si_aktuell_am)"></div>
                                    </div>
                                </template>
                                <template x-if="d.si_aktuell === null && d.dp_aktuell === null">
                                    <div style="text-align:right;">
                                        <span class="empty">—</span>
                                        <button @click.stop="sistrixAktualisieren(d.id)" title="SI/DP jetzt abrufen"
                                                style="background:none;border:0;cursor:pointer;color:var(--slate-400);padding:1px 6px;font-size:0.85rem;">↻ Sistrix</button>
                                    </div>
                                </template>
                            </td>
                            <td class="right">
                                <template x-if="d.preis_min !== null">
                                    <span>
                                        <span x-text="euro(d.preis_min)"></span>
                                        <template x-if="d.preis_max !== null && d.preis_max != d.preis_min">
                                            <span class="muted"> – <span x-text="euro(d.preis_max)"></span></span>
                                        </template>
                                    </span>
                                </template>
                                <template x-if="d.preis_min === null"><span class="empty">—</span></template>
                            </td>
                            <!-- Status (Inline-Edit) -->
                            <td>
                                <template x-if="!istOffen(d.id, 'verifikation_status')">
                                    <button class="thx-inline-edit" @click="oeffneEdit(d, 'verifikation_status')" x-text="d.verifikation_status"></button>
                                </template>
                                <template x-if="istOffen(d.id, 'verifikation_status')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <template x-for="s in verifikationStatus" :key="s"><option :value="s" x-text="s"></option></template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(d, 'verifikation_status')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="d.kunden">
                                    <span>
                                        <template x-for="k in d.kunden.split('|')" :key="k">
                                            <span class="lam-badge lam-badge-tag" style="margin-right:4px;" x-text="k"></span>
                                        </template>
                                    </span>
                                </template>
                                <template x-if="!d.kunden"><span class="empty">—</span></template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Domains mit diesen Filtern.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span>Lade Linkquellen …</div>
        </div>
        <div class="lam-pagination">
            <div>Zeige <strong x-text="rows.length ? (filter.offset + 1) : 0"></strong> – <strong x-text="filter.offset + rows.length"></strong> von <strong x-text="zahl(gesamt)"></strong></div>
            <div class="lam-pagination-actions">
                <button class="lam-btn lam-btn-small" :disabled="filter.offset === 0" @click="seiteZurueck()">‹ Vorherige</button>
                <button class="lam-btn lam-btn-small" :disabled="filter.offset + rows.length >= gesamt" @click="seiteVor()">Nächste ›</button>
            </div>
        </div>
    </section>

    <!-- Rechtsklick-Kontextmenue -->
    <div class="thx-contextmenu" x-show="ctxMenu.offen" x-cloak :style="`top: ${ctxMenu.y}px; left: ${ctxMenu.x}px;`" @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.ziel?.url || ''"></div>
        <a class="thx-contextmenu-item" :href="ctxMenu.ziel ? '/lam/linkquellen/' + encodeURIComponent(ctxMenu.ziel.id) : '#'" style="text-decoration:none;">Detail-Seite öffnen</a>
        <button class="thx-contextmenu-item" @click="oeffneBearbeitenDrawer(ctxMenu.ziel); ctxMenu.offen = false">Bearbeiten …</button>
        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Verifikation setzen</div>
        <template x-for="s in verifikationStatus" :key="s">
            <button class="thx-contextmenu-item" @click="schnellAktion(ctxMenu.ziel, 'verifikation_status', s); ctxMenu.offen = false" x-text="s"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item" x-show="ctxMenu.ziel && ctxMenu.ziel.disqualifiziert != 1" @click="schnellAktion(ctxMenu.ziel, 'disqualifiziert', 1); ctxMenu.offen = false">Disqualifizieren</button>
        <button class="thx-contextmenu-item" x-show="ctxMenu.ziel && ctxMenu.ziel.disqualifiziert == 1" @click="schnellAktion(ctxMenu.ziel, 'disqualifiziert', 0); ctxMenu.offen = false">Rehabilitieren</button>
        <button class="thx-contextmenu-item is-danger" @click="loescheDomain(ctxMenu.ziel); ctxMenu.offen = false">Löschen</button>
    </div>

    <!-- Portfolio-Import: Upload-Drawer + Preview-Modal -->
    <div class="thx-drawer-backdrop" x-show="pi.drawerOffen" @click.self="piSchliessen()" x-cloak>
        <div class="thx-drawer" style="width:560px;max-width:95vw;">
            <div class="thx-drawer-header">
                <h3 style="margin:0;">
                    <span class="material-symbols-rounded" style="vertical-align:middle;color:var(--thoxan-700);">auto_awesome</span>
                    Portfolio importieren
                </h3>
                <button class="thx-modal-close" type="button" @click="piSchliessen()">&times;</button>
            </div>
            <div class="thx-drawer-body" style="display:flex;flex-direction:column;gap:1rem;">
                <p style="margin:0;color:var(--slate-600);font-size:var(--d-fs-sm);line-height:1.5;">
                    Zieh die Anbieter-Mail (EML), ihr Portfolio (XLSX/CSV) oder Mediadaten (PDF) hier rein.
                    Die KI extrahiert Anbieter, Kontakte, Domains und Preise — Du prüfst und winkst durch.
                </p>

                <div @dragover.prevent="pi.dragOver = true"
                     @dragleave.prevent="pi.dragOver = false"
                     @drop.prevent="piDrop($event)"
                     :class="['pi-dropzone', pi.dragOver ? 'is-over' : '']"
                     @click="$refs.piFiles.click()">
                    <div class="pi-dropzone-icon">
                        <span class="material-symbols-rounded" x-text="pi.dragOver ? 'file_download' : 'cloud_upload'">cloud_upload</span>
                    </div>
                    <div class="pi-dropzone-headline">
                        <span x-show="!pi.dragOver">Dateien hierhin ziehen <span class="pi-link">oder klicken</span></span>
                        <span x-show="pi.dragOver">Jetzt loslassen …</span>
                    </div>
                    <div class="pi-dropzone-sub">
                        Mehrere Dateien möglich · bis 25 MB pro Datei
                    </div>
                    <div class="pi-typchips">
                        <span class="pi-typchip"><span class="material-symbols-rounded">mail</span>EML/MSG</span>
                        <span class="pi-typchip"><span class="material-symbols-rounded">table_chart</span>XLSX</span>
                        <span class="pi-typchip"><span class="material-symbols-rounded">table_rows</span>CSV</span>
                        <span class="pi-typchip" style="color:var(--rose-700);"><span class="material-symbols-rounded">picture_as_pdf</span>PDF</span>
                    </div>
                </div>
                <input type="file" x-ref="piFiles" multiple accept=".eml,.msg,.xlsx,.csv,.pdf" style="display:none;"
                       @change="piFilesGewaehlt($event.target.files)">

                <template x-if="pi.dateien.length">
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <template x-for="(f, i) in pi.dateien" :key="i">
                            <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:#fff;border:1px solid var(--slate-200);border-radius:8px;font-size:var(--d-fs-sm);">
                                <span class="material-symbols-rounded" style="font-size:18px;color:var(--slate-500);"
                                      x-text="piIcon(f.name)"></span>
                                <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="f.name"></span>
                                <span style="color:var(--slate-400);font-size:var(--d-fs-xs);" x-text="piSize(f.size)"></span>
                                <button @click="pi.dateien.splice(i,1)" style="border:none;background:transparent;cursor:pointer;color:var(--slate-400);">&times;</button>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="pi.fehler">
                    <div style="padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:var(--rose-700);font-size:var(--d-fs-sm);" x-text="pi.fehler"></div>
                </template>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn" @click="piSchliessen()" :disabled="pi.laeuft">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="piStart()"
                        :disabled="!pi.dateien.length || pi.laeuft">
                    <span x-show="!pi.laeuft">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">auto_awesome</span>
                        KI-Analyse starten
                    </span>
                    <span x-show="pi.laeuft" x-text="pi.statusText">Lädt…</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Preview-Modal: zeigt extrahierte Daten -->
    <div class="thx-modal-backdrop" x-show="pi.previewOffen" @click.self="pi.previewOffen = false" x-cloak
         style="z-index:9000;">
        <div class="thx-modal" style="width:1100px;max-width:96vw;max-height:92vh;display:flex;flex-direction:column;">
            <div class="thx-modal-header" style="display:flex;align-items:center;gap:8px;">
                <span class="material-symbols-rounded" style="color:var(--thoxan-700);">fact_check</span>
                <h3 class="thx-modal-title" style="margin:0;flex:1;">Vorschau · Was die KI gefunden hat</h3>
                <span style="font-size:var(--d-fs-xs);color:var(--slate-500);padding:3px 10px;background:#f1f5f9;border-radius:10px;"
                      x-text="pi.batch ? 'Batch ' + pi.batch.substring(0,8) + '…' : ''"></span>
                <button class="thx-modal-close" type="button" @click="pi.previewOffen = false">&times;</button>
            </div>

            <div style="display:flex;gap:4px;padding:0 18px;border-bottom:1px solid var(--slate-200);background:#fafbfc;">
                <button class="pi-tab" :class="pi.tab === 'anbieter' ? 'is-active' : ''" @click="pi.tab = 'anbieter'">
                    Anbieter <span class="pi-count">1</span>
                </button>
                <button class="pi-tab" :class="pi.tab === 'kontakte' ? 'is-active' : ''" @click="pi.tab = 'kontakte'">
                    Kontakte <span class="pi-count" x-text="(pi.data?.kontakte || []).length"></span>
                </button>
                <button class="pi-tab" :class="pi.tab === 'domains' ? 'is-active' : ''" @click="pi.tab = 'domains'">
                    Domains <span class="pi-count" x-text="(pi.data?.domains || []).length"></span>
                </button>
                <button class="pi-tab" :class="pi.tab === 'deals' ? 'is-active' : ''" @click="pi.tab = 'deals'">
                    Sonderdeals <span class="pi-count" x-text="(pi.data?.sonderdeals || []).length"></span>
                </button>
            </div>

            <div class="thx-modal-body" style="flex:1;overflow-y:auto;padding:18px;">
                <!-- Tab Anbieter -->
                <template x-if="pi.tab === 'anbieter' && pi.data?.anbieter">
                    <div>
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);font-weight:500;cursor:pointer;">
                                <input type="checkbox" x-model="pi.anbieterUebernehmen" style="width:16px;height:16px;">
                                Anbieter übernehmen
                            </label>
                            <span class="pi-badge" :class="'pi-badge-' + pi.data.anbieter.status" x-text="pi.data.anbieter.status === 'neu' ? 'NEU' : 'UPDATE BESTEHEND'"></span>
                            <template x-if="pi.data.anbieter.match">
                                <span style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                                    Match: <strong x-text="pi.data.anbieter.match.firma || pi.data.anbieter.match.name"></strong>
                                </span>
                            </template>
                        </div>
                        <div class="pi-grid">
                            <div class="pi-field">
                                <div class="pi-label">Name (intern, editierbar)</div>
                                <input type="text" x-model="pi.anbieterEdit.name" class="pi-input">
                            </div>
                            <div class="pi-field">
                                <div class="pi-label">Firma (editierbar)</div>
                                <input type="text" x-model="pi.anbieterEdit.firma" class="pi-input">
                            </div>
                            <div class="pi-field"><div class="pi-label">Strasse</div><div class="pi-value" x-text="pi.data.anbieter.vorschlag.strasse || '—'"></div></div>
                            <div class="pi-field"><div class="pi-label">PLZ / Ort</div><div class="pi-value" x-text="(pi.data.anbieter.vorschlag.plz || '') + ' ' + (pi.data.anbieter.vorschlag.ort || '')"></div></div>
                            <div class="pi-field"><div class="pi-label">Web</div><div class="pi-value" x-text="pi.data.anbieter.vorschlag.web || '—'"></div></div>
                            <div class="pi-field"><div class="pi-label">Handelsregister</div><div class="pi-value" x-text="pi.data.anbieter.vorschlag.handelsregister || '—'"></div></div>
                            <div class="pi-field" style="grid-column:1/-1;">
                                <div class="pi-label">Geschäftsführer</div>
                                <div class="pi-value" x-text="(pi.data.anbieter.vorschlag.geschaeftsfuehrer || []).join(', ') || '—'"></div>
                            </div>
                        </div>
                        <div style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-500);background:#f8fafc;padding:8px 10px;border-radius:6px;">
                            Adresse, Handelsregister, Geschäftsführer landen strukturiert im Anbieter-Feld <code>notizen</code>.
                        </div>
                    </div>
                </template>

                <!-- Tab Kontakte -->
                <template x-if="pi.tab === 'kontakte'">
                    <div>
                        <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center;font-size:var(--d-fs-xs);color:var(--slate-500);">
                            <button class="pi-bulk-btn" @click="piSelAlle('kontakte', true)">Alle</button>
                            <button class="pi-bulk-btn" @click="piSelAlle('kontakte', false)">Keine</button>
                            <span style="margin-left:auto;"><strong x-text="piAuswahlCount('kontakte')"></strong> ausgewählt</span>
                        </div>
                        <table class="pi-table">
                            <thead><tr>
                                <th style="width:32px;"></th><th>Status</th><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Telefon</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(k, i) in (pi.data?.kontakte || [])" :key="i">
                                    <tr :class="!pi.kontakteSel[i] ? 'pi-row-skip' : ''">
                                        <td><input type="checkbox" x-model="pi.kontakteSel[i]"></td>
                                        <td><span class="pi-badge" :class="'pi-badge-' + k.status" x-text="k.status === 'neu' ? 'neu' : 'update'"></span></td>
                                        <td x-text="(k.vorschlag.vorname || '') + ' ' + (k.vorschlag.nachname || '')"></td>
                                        <td x-text="k.vorschlag.email || '—'"></td>
                                        <td x-text="k.vorschlag.rolle || '—'"></td>
                                        <td x-text="k.vorschlag.telefon || '—'"></td>
                                    </tr>
                                </template>
                                <template x-if="!(pi.data?.kontakte || []).length">
                                    <tr><td colspan="6" style="text-align:center;color:var(--slate-400);padding:20px;">Keine Kontakte extrahiert.</td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Tab Domains -->
                <template x-if="pi.tab === 'domains'">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:var(--d-fs-xs);color:var(--slate-500);flex-wrap:wrap;">
                            <button class="pi-bulk-btn" @click="piSelAlle('domains', true)">Alle</button>
                            <button class="pi-bulk-btn" @click="piSelAlle('domains', false)">Keine</button>
                            <button class="pi-bulk-btn" @click="piSelNurNeu()">Nur neue</button>
                            <span><strong x-text="piAuswahlCount('domains')"></strong> ausgewählt</span>
                            <span style="margin-left:auto;">
                                <strong style="color:var(--emerald-700);" x-text="(pi.data?.domains || []).filter(d => d.status === 'neu').length"></strong> neu ·
                                <strong style="color:var(--amber-700);" x-text="(pi.data?.domains || []).filter(d => d.status === 'update').length"></strong> bestehend
                            </span>
                            <input type="text" x-model="pi.filter" placeholder="Domain filtern…"
                                   style="padding:4px 8px;border:1px solid var(--slate-300);border-radius:6px;font-size:var(--d-fs-xs);">
                        </div>
                        <table class="pi-table">
                            <thead><tr>
                                <th style="width:32px;"></th>
                                <th>Status</th><th>Domain</th><th>Themen-Block</th>
                                <th style="text-align:right;">Beitrag</th>
                                <th style="text-align:right;">Special</th>
                                <th style="text-align:right;">Werbung</th>
                                <th style="text-align:right;">Startseite</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(d, i) in piDomainsGefiltert()" :key="i">
                                    <tr :class="!pi.domainsSel[d.__idx ?? i] ? 'pi-row-skip' : ''">
                                        <td><input type="checkbox" x-model="pi.domainsSel[d.__idx ?? i]"></td>
                                        <td><span class="pi-badge" :class="'pi-badge-' + d.status" x-text="d.status === 'neu' ? 'neu' : 'update'"></span></td>
                                        <td><strong x-text="d.vorschlag.url"></strong></td>
                                        <td style="color:var(--slate-500);font-size:var(--d-fs-xs);" x-text="d.vorschlag.thema_block || '—'"></td>
                                        <td style="text-align:right;font-family:ui-monospace,monospace;" x-text="piPrice(d.vorschlag.preise?.beitrag)"></td>
                                        <td style="text-align:right;font-family:ui-monospace,monospace;" x-text="piPrice(d.vorschlag.preise?.beitrag_special)"></td>
                                        <td style="text-align:right;font-family:ui-monospace,monospace;" x-text="piPrice(d.vorschlag.preise?.werbung)"></td>
                                        <td style="text-align:right;font-family:ui-monospace,monospace;" x-text="piPrice(d.vorschlag.preise?.startseite)"></td>
                                    </tr>
                                </template>
                                <template x-if="!piDomainsGefiltert().length">
                                    <tr><td colspan="8" style="text-align:center;color:var(--slate-400);padding:20px;">Keine Domains.</td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Tab Sonderdeals -->
                <template x-if="pi.tab === 'deals'">
                    <div>
                        <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center;font-size:var(--d-fs-xs);color:var(--slate-500);">
                            <button class="pi-bulk-btn" @click="piSelAlle('deals', true)">Alle</button>
                            <button class="pi-bulk-btn" @click="piSelAlle('deals', false)">Keine</button>
                            <span style="margin-left:auto;"><strong x-text="piAuswahlCount('deals')"></strong> ausgewählt</span>
                        </div>
                        <table class="pi-table">
                            <thead><tr>
                                <th style="width:32px;"></th>
                                <th>Domain</th><th>Buchungstyp</th><th style="text-align:right;">Preis</th><th>Laufzeit</th><th>inkl. Text</th><th>Notiz</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(d, i) in (pi.data?.sonderdeals || [])" :key="i">
                                    <tr :class="!pi.dealsSel[i] ? 'pi-row-skip' : ''">
                                        <td><input type="checkbox" x-model="pi.dealsSel[i]"></td>
                                        <td><strong x-text="d.domain"></strong></td>
                                        <td x-text="d.buchungstyp || '—'"></td>
                                        <td style="text-align:right;font-family:ui-monospace,monospace;" x-text="piPrice(d.preis_eur)"></td>
                                        <td x-text="d.laufzeit_monate ? d.laufzeit_monate + ' Mon.' : '—'"></td>
                                        <td x-text="d.inkl_text ? 'ja' : 'nein'"></td>
                                        <td style="color:var(--slate-500);font-size:var(--d-fs-xs);" x-text="d.notiz || '—'"></td>
                                    </tr>
                                </template>
                                <template x-if="!(pi.data?.sonderdeals || []).length">
                                    <tr><td colspan="7" style="text-align:center;color:var(--slate-400);padding:20px;">Keine Sonderdeals erkannt.</td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <!-- Footer: Commit oder Erfolg -->
            <div style="padding:14px 18px;border-top:1px solid var(--slate-200);background:#fafbfc;display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <template x-if="!pi.commitErgebnis">
                    <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                        <strong>Auswahl:</strong>
                        <span x-show="pi.anbieterUebernehmen">Anbieter ·</span>
                        <span><strong x-text="piAuswahlCount('kontakte')"></strong> Kontakte · </span>
                        <span><strong x-text="piAuswahlCount('domains')"></strong> Domains · </span>
                        <span><strong x-text="piAuswahlCount('deals')"></strong> Sonderdeals</span>
                    </div>
                </template>
                <template x-if="pi.commitErgebnis">
                    <div style="font-size:var(--d-fs-sm);color:var(--emerald-700);font-weight:600;display:flex;align-items:center;gap:6px;">
                        <span class="material-symbols-rounded" style="color:var(--emerald-700);">check_circle</span>
                        Importiert:
                        <span x-text="pi.commitErgebnis.anbieter"></span> Anbieter ·
                        <span x-text="pi.commitErgebnis.kontakte"></span> Kontakte ·
                        <span x-text="pi.commitErgebnis.domains_neu"></span> neue Domains ·
                        <span x-text="pi.commitErgebnis.domains_update"></span> Updates ·
                        <span x-text="pi.commitErgebnis.konditionen"></span> Konditionen ·
                        <span x-text="pi.commitErgebnis.sonderdeals"></span> Sonderdeals
                    </div>
                </template>
                <div style="display:flex;gap:6px;">
                    <button class="lam-btn" @click="pi.previewOffen = false" :disabled="pi.committing"
                            x-text="pi.commitErgebnis ? 'Schliessen' : 'Abbrechen'"></button>
                    <button class="lam-btn lam-btn-primary" @click="piCommit()"
                            x-show="!pi.commitErgebnis" :disabled="pi.committing">
                        <span x-show="!pi.committing">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">save</span>
                            Importieren
                        </span>
                        <span x-show="pi.committing">Wird geschrieben…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .pi-tab {
            padding:8px 14px;border:none;background:transparent;cursor:pointer;
            font-size:var(--d-fs-sm);color:var(--slate-500);
            border-bottom:2px solid transparent;font-weight:500;
        }
        .pi-tab:hover { color:var(--slate-800); }
        .pi-tab.is-active { color:var(--thoxan-800);border-bottom-color:var(--thoxan-700);font-weight:600; }
        .pi-count {
            margin-left:5px;padding:1px 7px;background:var(--slate-100);border-radius:9px;
            font-size:10px;color:var(--slate-600);font-weight:500;
        }
        .pi-tab.is-active .pi-count { background:var(--thoxan-100);color:var(--thoxan-800); }
        .pi-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:12px; }
        .pi-field { background:#f8fafc;padding:8px 10px;border-radius:6px; }
        .pi-label { font-size:10px;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px; }
        .pi-value { font-size:var(--d-fs-sm);color:var(--slate-800);word-break:break-word; }
        .pi-table { width:100%;border-collapse:collapse;font-size:var(--d-fs-sm); }
        .pi-table thead th {
            text-align:left;padding:7px 10px;background:#f1f5f9;color:var(--slate-600);
            font-weight:600;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.03em;
        }
        .pi-table tbody td { padding:6px 10px;border-bottom:1px solid var(--slate-100); }
        .pi-table tbody tr:hover { background:#fafbfc; }
        .pi-badge {
            display:inline-block;padding:2px 8px;border-radius:9px;font-size:10px;
            font-weight:600;text-transform:uppercase;letter-spacing:0.04em;
        }
        .pi-badge-neu { background:#d1fae5;color:var(--emerald-700); }
        .pi-badge-update { background:#fef3c7;color:var(--amber-700); }
        .pi-badge-konflikt { background:#fee2e2;color:var(--rose-700); }
        .pi-row-skip { opacity:0.4; }
        .pi-row-skip td { background:#fafbfc; }
        .pi-bulk-btn {
            padding:3px 9px;font-size:var(--d-fs-xs);border:1px solid var(--slate-200);
            background:#fff;border-radius:5px;cursor:pointer;color:var(--slate-700);
        }
        .pi-bulk-btn:hover { background:var(--slate-50);border-color:var(--slate-300); }
        .pi-input {
            width:100%;padding:4px 8px;border:1px solid var(--slate-300);border-radius:5px;
            font-size:var(--d-fs-sm);color:var(--slate-800);background:#fff;
        }
        .pi-input:focus { outline:none;border-color:var(--thoxan-700);box-shadow:0 0 0 2px var(--thoxan-100); }
    </style>

    <!-- Anlegen-/Bearbeiten-Drawer -->
    <div class="thx-drawer-backdrop" x-show="drawerOffen" @click.self="schliesseDrawer()" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title" x-text="drawer.id ? 'Linkquelle bearbeiten' : 'Neue Linkquelle'"></h2>
                <button class="thx-modal-close" @click="schliesseDrawer()">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>URL * (ohne https://)</label>
                    <input type="text" x-model="drawer.url" placeholder="z.B. beispiel.de">
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Anbieter</label>
                        <select x-model="drawer.anbieter_id">
                            <option value="">— kein Anbieter —</option>
                            <template x-for="a in anbieter" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                        </select>
                    </div>
                    <div class="thx-form-field">
                        <label>Verifikation</label>
                        <select x-model="drawer.verifikation_status">
                            <template x-for="s in verifikationStatus" :key="s"><option :value="s" x-text="s"></option></template>
                        </select>
                    </div>
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Linkart</label>
                        <input type="text" x-model="drawer.linkart" placeholder="z.B. blog, online_magazin">
                    </div>
                    <div class="thx-form-field">
                        <label>Buchbar via</label>
                        <input type="text" x-model="drawer.buchbar_via" placeholder="z.B. direkt, sistrix">
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Notizen</label>
                    <textarea x-model="drawer.notizen" rows="4"></textarea>
                </div>
                <div class="thx-form-field">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
                        <input type="checkbox" x-model="drawer.disqualifiziert"> disqualifiziert
                    </label>
                </div>
                <div class="thx-error" x-show="drawer.flashFehler" x-text="drawer.flashFehler"></div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="schliesseDrawer()">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereDrawer()" :disabled="drawer.laeuft">
                    <span x-show="!drawer.laeuft">Speichern</span><span x-show="drawer.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Detail-Modal -->
    <div class="thx-modal-backdrop" x-show="detailOffen" @click.self="schliesseDetail()" x-cloak>
        <div class="thx-modal" x-show="detailOffen">
            <div class="thx-modal-header">
                <div>
                    <h2 class="thx-modal-title" x-text="detail?.url || ''"></h2>
                    <div style="margin-top:4px;font-size:var(--d-fs-sm);color:var(--slate-500);">
                        <span x-text="detail?.anbieter_name || 'kein Anbieter'"></span>
                        <span x-show="detail?.verifikation_status" x-text="' · ' + (detail?.verifikation_status || '')"></span>
                        <span x-show="detail?.disqualifiziert == 1" style="color:var(--rose-600);"> · disqualifiziert</span>
                    </div>
                </div>
                <button class="thx-modal-close" @click="schliesseDetail()">×</button>
            </div>
            <div class="thx-modal-body">
                <div class="thx-modal-section">
                    <h3>Kerndaten</h3>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <tbody>
                            <tr><td class="muted" style="width:35%;">Buchbar via</td><td x-text="detail?.buchbar_via || '—'"></td></tr>
                            <tr><td class="muted">Linkart</td><td x-text="detail?.linkart || '—'"></td></tr>
                            <tr><td class="muted">Herkunft</td><td x-text="detail?.herkunft || '—'"></td></tr>
                            <tr><td class="muted">Sistrix sichtbar seit</td><td x-text="detail?.sistrix_sichtbar_seit || '—'"></td></tr>
                            <tr><td class="muted">Letzter Check</td><td x-text="detail?.letzter_check_am || '—'"></td></tr>
                            <tr><td class="muted">HTTP-Status</td><td x-text="detail?.letzter_http_status || '—'"></td></tr>
                            <tr><td class="muted">Notizen</td><td><span style="white-space:pre-wrap;" x-text="detail?.notizen || '—'"></span></td></tr>
                        </tbody>
                    </table>
                </div>

                <template x-if="(detail?.konditionen || []).length > 0">
                    <div class="thx-modal-section">
                        <h3>Konditionen (<span x-text="detail.konditionen.length"></span>)</h3>
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Buchungstyp</th><th class="right">Preis</th><th>Via</th></tr></thead>
                            <tbody>
                                <template x-for="k in detail.konditionen" :key="k.id">
                                    <tr>
                                        <td x-text="k.buchungstyp || '—'"></td>
                                        <td class="right" x-text="euro(k.preis)"></td>
                                        <td x-text="k.via_anbieter_name || 'direkt'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template x-if="(detail?.kennzahlen || []).length > 0">
                    <div class="thx-modal-section">
                        <h3>Kennzahlen (letzte <span x-text="detail.kennzahlen.length"></span>)</h3>
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Erfasst</th><th class="right">SI</th><th class="right">DP</th><th class="right">Domain-Alter</th><th>Quelle</th></tr></thead>
                            <tbody>
                                <template x-for="kz in detail.kennzahlen" :key="kz.erfasst_am">
                                    <tr>
                                        <td x-text="kz.erfasst_am"></td>
                                        <td class="right" x-text="kz.si || '—'"></td>
                                        <td class="right" x-text="kz.dp || '—'"></td>
                                        <td class="right" x-text="kz.domain_alter || '—'"></td>
                                        <td x-text="kz.quelle || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template x-if="(detail?.tags || []).length > 0">
                    <div class="thx-modal-section">
                        <h3>Tags</h3>
                        <div class="lam-chip-row">
                            <template x-for="t in detail.tags" :key="t.id">
                                <span class="lam-badge lam-badge-tag" x-text="t.name"></span>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="(detail?.kunden || []).length > 0">
                    <div class="thx-modal-section">
                        <h3>Verknüpfte Kunden</h3>
                        <div class="lam-chip-row">
                            <template x-for="c in detail.kunden" :key="c.id">
                                <span class="lam-badge lam-badge-tag">
                                    <strong x-text="c.abbreviation"></strong><span class="muted" x-text="' · ' + c.name"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="schliesseDetail()">Schließen</button>
                <button class="lam-btn lam-btn-primary" @click="schliesseDetail(); oeffneBearbeitenDrawer(detail)">Bearbeiten</button>
            </div>
        </div>
    </div>

    <!-- Tag-Editor-Popover (Multi-Select) -->
    <div class="thx-modal-backdrop" x-show="tagEdit.offen" @click.self="tagEdit.offen = false" x-cloak>
        <div class="thx-modal" style="max-width:520px;">
            <div class="thx-modal-header">
                <h2>Tags zuweisen</h2>
                <button class="thx-icon-btn" @click="tagEdit.offen = false">✕</button>
            </div>
            <div class="thx-modal-body">
                <div class="muted" style="margin-bottom:10px;font-size:var(--d-fs-sm);">
                    <span x-text="tagEdit.domain?.url"></span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;max-height:320px;overflow-y:auto;">
                    <template x-for="t in alleTags" :key="t.id">
                        <button type="button"
                                class="lam-chip"
                                :class="tagEdit.ausgewaehlt.includes(t.id) ? 'is-active' : ''"
                                @click="toggleTagAuswahl(t.id)"
                                x-text="t.name"></button>
                    </template>
                </div>
                <div x-show="alleTags.length === 0" class="muted" style="padding:12px 0;">
                    Noch keine Tags angelegt. <a href="/lam/tags" style="color:var(--thoxan-700);">Tags verwalten →</a>
                </div>
            </div>
            <div class="thx-modal-footer">
                <button class="lam-btn lam-btn-secondary" @click="tagEdit.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereTags()" :disabled="tagEdit.laeuft">
                    <span x-show="!tagEdit.laeuft">Speichern</span>
                    <span x-show="tagEdit.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Sistrix Pre-Confirm Modal (Domain-basiert): Kosten + Budget vor Bulk-Lauf -->
    <div class="thx-lightbox" x-show="sistrixPre.offen" x-cloak @click.self="sistrixPreSchliessen()"
         style="background:rgba(15,23,42,0.45);z-index:1050;">
        <div style="width:100%;max-width:480px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                <h3 style="margin:0;font-size:1rem;font-weight:600;color:#0f172a;">Sistrix abrufen: <span x-text="sistrixPre.label"></span></h3>
                <button type="button" @click="sistrixPreSchliessen()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div style="padding:18px 20px;">
                <template x-if="sistrixPre.laedt">
                    <div style="padding:24px 0;text-align:center;color:#64748b;font-size:0.875rem;">Vorschau wird geladen …</div>
                </template>
                <template x-if="!sistrixPre.laedt && sistrixPre.vorschau">
                    <div>
                        <p style="font-size:0.8rem;color:#64748b;margin:0 0 14px 0;line-height:1.4;">
                            Cache-Hits werden nicht erneut abgerechnet, das Maximum ist
                            <strong x-text="zahl(sistrixPre.vorschau.kosten_max)"></strong> Credits.
                        </p>
                        <div style="font-size:0.875rem;">
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <span style="color:#64748b;">Domains ausgewählt</span>
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
                            <div style="display:flex;justify-content:space-between;padding:10px 0 6px;border-top:1px solid #cbd5e1;margin-top:4px;">
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
                           style="margin:14px 0 0;padding:8px 10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:6px;font-size:0.8rem;">
                            Maximalkosten uebersteigen das verbleibende Wochenbudget &mdash; der Lauf wird vorzeitig abgebrochen, sobald das Kontingent aufgebraucht ist.
                        </p>
                    </div>
                </template>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="button" @click="sistrixPreSchliessen()"
                        style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">Abbrechen</button>
                <button type="button"
                        :disabled="sistrixPre.laedt || !sistrixPre.vorschau || sistrixPre.vorschau.neu_abzurufen === 0"
                        @click="sistrixPreAnwenden()"
                        style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                        :style="{ opacity: (sistrixPre.laedt || !sistrixPre.vorschau || sistrixPre.vorschau.neu_abzurufen === 0) ? 0.5 : 1 }">
                    Jetzt abrufen
                </button>
            </div>
        </div>
    </div>

    <!-- Live-Fortschritts-Modal -->
    <div class="thx-lightbox" x-show="fortschritt.offen" x-cloak
         style="background:rgba(15,23,42,0.45);z-index:1050;">
        <div style="width:100%;max-width:440px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                <h3 style="margin:0;font-size:1rem;font-weight:600;color:#0f172a;" x-text="fortschritt.label"></h3>
                <button type="button" x-show="fortschritt.fertig" @click="fortschrittSchliessen()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div style="padding:18px 20px;">
                <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px;" x-text="fortschritt.fertig ? 'Fertig.' : (fortschritt.abbrechen ? 'Wird abgebrochen …' : 'Läuft …')"></p>
                <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:0.875rem;">
                    <span style="font-weight:600;font-variant-numeric:tabular-nums;">
                        <span x-text="zahl(fortschritt.done)"></span> / <span x-text="zahl(fortschritt.total)"></span>
                    </span>
                    <span style="color:#64748b;font-size:0.75rem;" x-text="Math.round((fortschritt.done / Math.max(fortschritt.total,1)) * 100) + ' %'"></span>
                </div>
                <div style="margin-top:6px;height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
                    <div style="height:100%;background:#0369a1;transition:width 0.4s ease-out;border-radius:99px;"
                         :style="{ width: ((fortschritt.done / Math.max(fortschritt.total,1)) * 100) + '%' }"></div>
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
                        style="margin:6px 0 0;padding:8px;max-height:160px;overflow-y:auto;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:6px;font-size:0.75rem;list-style:none;">
                        <template x-for="(f, i) in fortschritt.fehler" :key="i">
                            <li style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:1px 0;" :title="f" x-text="f"></li>
                        </template>
                    </ul>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="button" x-show="!fortschritt.fertig" :disabled="fortschritt.abbrechen" @click="fortschritt.abbrechen = true"
                        style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;"
                        x-text="fortschritt.abbrechen ? 'Wird abgebrochen …' : 'Abbrechen'"></button>
                <button type="button" x-show="fortschritt.fertig" @click="fortschrittSchliessen()"
                        style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;">Schließen</button>
            </div>
        </div>
    </div>

    <!-- ═════════ Linkquellen-Import (XLSX/CSV mit URL-Spalte) ═════════ -->
    <div class="thx-drawer-backdrop" x-show="lqImport.offen" x-cloak @click.self="lqImport.offen = false">
        <div class="thx-drawer" style="max-width:880px;">
            <div class="thx-drawer-header">
                <h2>Linkquellen-Import</h2>
                <button class="thx-modal-close" @click="lqImport.offen = false">×</button>
            </div>
            <div class="thx-drawer-body" style="display:flex;flex-direction:column;gap:14px;">
                <!-- Schritt 1: Datei wählen -->
                <template x-if="lqImport.phase === 'upload'">
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <p style="margin:0;color:var(--slate-600);font-size:var(--d-fs-sm);line-height:1.5;">
                            XLSX oder CSV mit einer URL-Spalte hochladen. Der Importer erkennt automatisch:
                            URL-Spalte (Header: <code>URL</code> / <code>Website</code> / <code>Domain</code> / <code>Projekt</code>),
                            Themengebiet → Tag, Anmerkung → Notiz, SI/DP-Snapshot.
                            Existierende Domains werden mit den Notizen + Tags + SI/DP-Snapshot des Imports angereichert (kein bestehender Wert wird überschrieben).
                        </p>
                        <div>
                            <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Kontext (optional)</label>
                            <input type="text" x-model="lqImport.kontext"
                                   placeholder="z.B. „Referenz von Fryka" oder „Empfehlung von Bantle Media"
                                   style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                            <p style="margin:4px 0 0;font-size:0.72rem;color:var(--slate-500);">
                                Wird als Herkunfts-Notiz an jede neu angelegte / angereicherte Linkquelle angehängt. Hilft der späteren KI-Analyse einzuordnen woher die Liste kommt.
                            </p>
                        </div>
                        <div class="pi-dropzone" @click="$refs.lqFile.click()"
                             @dragover.prevent="lqImport.dragOver = true"
                             @dragleave.prevent="lqImport.dragOver = false"
                             @drop.prevent="lqDateiDrop($event)"
                             :class="lqImport.dragOver ? 'is-over' : ''">
                            <div class="pi-dropzone-icon">
                                <span class="material-symbols-rounded">cloud_upload</span>
                            </div>
                            <div class="pi-dropzone-headline">
                                Datei hierhin ziehen <span class="pi-link">oder klicken</span>
                            </div>
                            <div class="pi-dropzone-sub">XLSX, XLSM, CSV — bis 10 MB</div>
                        </div>
                        <input type="file" x-ref="lqFile" accept=".xlsx,.xlsm,.csv" style="display:none;"
                               @change="lqDateiGewaehlt($event.target.files[0])">
                        <div x-show="lqImport.fehler" x-cloak style="color:var(--rose-700);padding:10px 14px;background:var(--rose-50);border-radius:6px;font-size:var(--d-fs-sm);" x-text="lqImport.fehler"></div>
                    </div>
                </template>

                <!-- Schritt 2: Preview -->
                <template x-if="lqImport.phase === 'preview'">
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;gap:14px;flex-wrap:wrap;padding:12px 16px;background:var(--slate-50);border-radius:8px;font-size:var(--d-fs-sm);">
                            <div><strong x-text="lqImport.datei"></strong></div>
                            <div style="color:var(--slate-500);">·</div>
                            <div><strong x-text="lqImport.stats.gesamt"></strong> erkannte URLs</div>
                            <div style="color:var(--emerald-700);"><strong x-text="lqImport.stats.neu"></strong> neu</div>
                            <div style="color:var(--amber-700);" :title="'Bestehende Domains werden mit Notiz/Tag/Kennzahl-Snapshot angereichert statt übersprungen'"><strong x-text="lqImport.stats.dubletten"></strong> bereits vorhanden (werden angereichert)</div>
                        </div>
                        <div x-show="lqImport.spalten" style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                            Erkannte Spalten: URL=<strong x-text="lqImport.spalten?.url ?? '—'"></strong>,
                            Thema=<strong x-text="lqImport.spalten?.thema ?? '—'"></strong>,
                            Notiz=<strong x-text="lqImport.spalten?.notiz ?? '—'"></strong>,
                            SI=<strong x-text="lqImport.spalten?.si ?? '—'"></strong>,
                            DP=<strong x-text="lqImport.spalten?.dp ?? '—'"></strong>
                        </div>

                        <div style="display:flex;gap:10px;align-items:center;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);">
                                <input type="checkbox" :checked="lqImport.kandidaten.every(k => k._wahl)"
                                       @change="lqAlleWahl($event.target.checked)">
                                Alle <span x-text="'(' + lqImport.kandidaten.filter(k => !k.existiert).length + ' neue, ' + lqImport.kandidaten.filter(k => k.existiert).length + ' Dubletten)'"></span>
                            </label>
                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="lqNurNeueWahl()">Nur neue auswählen</button>
                            <span style="color:var(--slate-500);font-size:var(--d-fs-sm);margin-left:auto;">
                                Importieren: <strong x-text="lqImport.kandidaten.filter(k => k._wahl && !k.existiert).length"></strong> Linkquellen
                            </span>
                        </div>

                        <!-- Schnellaktion: direkt zum Linkpool eines Kunden hinzufügen -->
                        <div style="padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span style="font-size:var(--d-fs-sm);color:#1e40af;">🚀 Zusätzlich zum Linkpool eines Kunden hinzufügen (auch Dubletten!):</span>
                            <select x-model="lqImport.poolCustomerId" style="flex:1;min-width:200px;">
                                <option value="">— Kunde wählen (optional) —</option>
                                <template x-for="k in lqImport.kunden" :key="k.id">
                                    <option :value="k.id" x-text="(k.abbreviation ? k.abbreviation + ' · ' : '') + k.name"></option>
                                </template>
                            </select>
                            <span x-show="lqImport.poolCustomerId" style="font-size:var(--d-fs-xs);color:#1e40af;">
                                → Alle <strong x-text="lqImport.kandidaten.length"></strong> erkannten URLs landen im Linkpool
                            </span>
                        </div>

                        <div style="max-height:380px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;">
                            <table style="width:100%;font-size:var(--d-fs-sm);border-collapse:collapse;">
                                <thead style="background:var(--slate-50);position:sticky;top:0;">
                                    <tr>
                                        <th style="padding:8px 10px;text-align:left;width:34px;"></th>
                                        <th style="padding:8px 10px;text-align:left;">URL</th>
                                        <th style="padding:8px 10px;text-align:left;">Thema</th>
                                        <th style="padding:8px 10px;text-align:right;">SI</th>
                                        <th style="padding:8px 10px;text-align:right;">DP</th>
                                        <th style="padding:8px 10px;text-align:left;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(k, i) in lqImport.kandidaten" :key="i">
                                        <tr :style="k.existiert ? 'opacity:0.5;' : ''"
                                            style="border-top:1px solid var(--slate-100);">
                                            <td style="padding:6px 10px;">
                                                <input type="checkbox" x-model="k._wahl" :disabled="k.existiert">
                                            </td>
                                            <td style="padding:6px 10px;font-weight:500;" x-text="k.url"></td>
                                            <td style="padding:6px 10px;color:var(--slate-600);" x-text="k.thema || '—'"></td>
                                            <td style="padding:6px 10px;text-align:right;" x-text="k.si !== undefined ? parseFloat(k.si).toFixed(4) : '—'"></td>
                                            <td style="padding:6px 10px;text-align:right;" x-text="k.dp !== undefined ? k.dp : '—'"></td>
                                            <td style="padding:6px 10px;">
                                                <span x-show="k.existiert" style="color:var(--amber-700);font-size:0.7rem;">Dublette</span>
                                                <span x-show="!k.existiert" style="color:var(--emerald-700);font-size:0.7rem;">neu</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <!-- Schritt 3: Erfolg -->
                <template x-if="lqImport.phase === 'fertig'">
                    <div style="padding:24px 20px;">
                        <div style="text-align:center;margin-bottom:20px;">
                            <div style="font-size:3rem;margin-bottom:10px;">✓</div>
                            <h3 style="margin:0 0 8px 0;color:var(--emerald-700);">Import abgeschlossen</h3>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;">
                            <div style="text-align:center;padding:14px;background:var(--emerald-50);border-radius:8px;">
                                <div style="font-size:1.6rem;font-weight:700;color:var(--emerald-700);" x-text="lqImport.ergebnis?.neu || 0"></div>
                                <div style="font-size:var(--d-fs-xs);color:var(--slate-600);">neu angelegt</div>
                            </div>
                            <div style="text-align:center;padding:14px;background:var(--amber-50);border-radius:8px;">
                                <div style="font-size:1.6rem;font-weight:700;color:#9a3412;" x-text="lqImport.ergebnis?.angereichert || 0"></div>
                                <div style="font-size:var(--d-fs-xs);color:var(--slate-600);">angereichert</div>
                            </div>
                            <div style="text-align:center;padding:14px;background:var(--slate-50);border-radius:8px;">
                                <div style="font-size:1.6rem;font-weight:700;color:var(--slate-600);" x-text="lqImport.ergebnis?.['unverändert'] || lqImport.ergebnis?.unveraendert || 0"></div>
                                <div style="font-size:var(--d-fs-xs);color:var(--slate-600);">unverändert</div>
                            </div>
                            <div x-show="lqImport.ergebnis?.fehler?.length > 0" style="text-align:center;padding:14px;background:var(--rose-50);border-radius:8px;">
                                <div style="font-size:1.6rem;font-weight:700;color:var(--rose-700);" x-text="lqImport.ergebnis?.fehler?.length || 0"></div>
                                <div style="font-size:var(--d-fs-xs);color:var(--slate-600);">Fehler</div>
                            </div>
                        </div>

                        <!-- Anreicherungs-Details (was wurde wo ergänzt) -->
                        <div x-show="lqImport.ergebnis?.anreicherungs_details?.length > 0" style="margin-top:10px;">
                            <details style="background:var(--slate-50);border-radius:8px;padding:12px 16px;">
                                <summary style="cursor:pointer;font-size:var(--d-fs-sm);color:var(--slate-700);font-weight:600;">
                                    Anreicherungs-Details (<span x-text="lqImport.ergebnis?.anreicherungs_details?.length || 0"></span> Domains)
                                </summary>
                                <ul style="margin:10px 0 0 0;padding-left:18px;font-size:var(--d-fs-xs);color:var(--slate-700);max-height:240px;overflow-y:auto;">
                                    <template x-for="(d, i) in (lqImport.ergebnis?.anreicherungs_details || [])" :key="i">
                                        <li style="margin-bottom:4px;">
                                            <strong x-text="d.url"></strong>:
                                            <span x-text="d.felder.join(', ')"></span>
                                        </li>
                                    </template>
                                </ul>
                            </details>
                        </div>

                        <!-- Fehler -->
                        <div x-show="lqImport.ergebnis?.fehler?.length > 0" style="margin-top:10px;">
                            <details style="background:var(--rose-50);border-radius:8px;padding:12px 16px;">
                                <summary style="cursor:pointer;font-size:var(--d-fs-sm);color:var(--rose-800);font-weight:600;">Fehler (<span x-text="lqImport.ergebnis?.fehler?.length"></span>)</summary>
                                <ul style="margin:10px 0 0 0;padding-left:18px;font-size:var(--d-fs-xs);color:var(--rose-700);max-height:200px;overflow-y:auto;">
                                    <template x-for="(f, i) in (lqImport.ergebnis?.fehler || [])" :key="i">
                                        <li x-text="f"></li>
                                    </template>
                                </ul>
                            </details>
                        </div>
                    </div>
                </template>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="lqImport.offen = false">
                    <span x-text="lqImport.phase === 'fertig' ? 'Schließen' : 'Abbrechen'"></span>
                </button>
                <button class="lam-btn lam-btn-primary"
                        x-show="lqImport.phase === 'preview'"
                        :disabled="lqImport.laeuft || (lqImport.kandidaten.filter(k => k._wahl && !k.existiert).length === 0 && !lqImport.poolCustomerId)"
                        @click="lqImportieren()">
                    <span x-show="!lqImport.laeuft"
                          x-text="(lqImport.kandidaten.filter(k => k._wahl && !k.existiert).length > 0 ? (lqImport.kandidaten.filter(k => k._wahl && !k.existiert).length + ' Linkquellen importieren') : 'Übernehmen') + (lqImport.poolCustomerId ? ' + Linkpool' : '')"></span>
                    <span x-show="lqImport.laeuft">…</span>
                </button>
                <button class="lam-btn lam-btn-primary" x-show="lqImport.phase === 'fertig'" @click="lqImport.offen = false; reload(true)">
                    Liste neu laden
                </button>
            </div>
        </div>
    </div>

</div>

<style>
[x-cloak] { display: none !important; }
/* SI-Zelle: einheitlich Slate, kein Farb-Code je nach Alter (Styleguide: nicht so bunt).
   Das „letzter Check"-Datum erscheint klein als zweite Zeile darunter. */
.si-cell { color: var(--slate-700); }
.si-cell.si-fresh, .si-cell.si-mid, .si-cell.si-old, .si-cell.si-stale {
    color: var(--slate-700); font-weight: 500;
}
.si-cell-datum { font-size: 0.7rem; color: var(--slate-400); margin-top: 2px; line-height: 1.1; }

/* Portfolio-Import: schöne, große Drop-Zone */
.pi-dropzone {
    position: relative;
    min-height: 220px;
    border: 2px dashed var(--slate-300);
    border-radius: 14px;
    padding: 36px 24px;
    text-align: center;
    background: linear-gradient(180deg, #fafbfc 0%, #f3f5f8 100%);
    transition: border-color 0.18s, background 0.18s, transform 0.12s, box-shadow 0.18s;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
}
.pi-dropzone:hover {
    border-color: var(--thoxan-500);
    background: linear-gradient(180deg, #f6f9ff 0%, #eef3fb 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.pi-dropzone.is-over {
    border-color: var(--thoxan-700);
    background: linear-gradient(180deg, #e8f0fe 0%, #d9e6fc 100%);
    border-style: solid;
    transform: scale(1.01);
}
.pi-dropzone-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid var(--slate-200);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--thoxan-600);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    transition: transform 0.18s, background 0.18s;
}
.pi-dropzone:hover .pi-dropzone-icon { transform: translateY(-2px); }
.pi-dropzone.is-over .pi-dropzone-icon { background: var(--thoxan-700); color: #fff; transform: scale(1.05); }
.pi-dropzone-icon .material-symbols-rounded { font-size: 40px; }
.pi-dropzone-headline {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--slate-800);
}
.pi-dropzone-headline .pi-link {
    color: var(--thoxan-700);
    text-decoration: underline;
    text-decoration-color: var(--thoxan-200);
    text-underline-offset: 2px;
}
.pi-dropzone-sub {
    font-size: 0.8rem;
    color: var(--slate-500);
    line-height: 1.5;
}
.pi-typchips {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 2px;
}
.pi-typchip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid var(--slate-200);
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--slate-600);
    letter-spacing: 0.02em;
}
.pi-typchip .material-symbols-rounded { font-size: 12px; }
</style>

<script>
function lamLinkquellen() {
    return {
        laedt: true, anbieter: [], rows: [], gesamt: 0, alleTags: [], kunden: [],
        detailOffen: false, detail: null,
        verifikationStatus: ['neu', 'in_arbeit', 'geprueft', 'veraltet', 'geloescht'],
        alleTrefferAusgewaehlt: false,

        // Linkquellen-Import-State (XLSX/CSV mit URL-Spalte → direkt in lam_domains)
        lqImport: {
            offen: false, phase: 'upload', dragOver: false, fehler: '',
            datei: '', token: '', spalten: null, kandidaten: [], stats: {gesamt: 0, neu: 0, dubletten: 0},
            laeuft: false, ergebnis: null,
            poolCustomerId: '', kunden: [],
            kontext: '',
        },

        // Inline-Edit
        editZelle: { id: null, feld: null }, editWert: '', editLaeuft: false,

        // Tag-Editor (Multi-Select-Popover)
        tagEdit: { offen: false, domain: null, ausgewaehlt: [], laeuft: false },

        // Bulk
        auswahl: new Set(), bulkAktion: '', bulkWert: '', bulkLaeuft: false,

        // Vorschlagsliste-Modal: ausgewählte Domains auf eine Liste setzen
        addPool: { offen: false, laeuft: false, customerId: '', kunden: [], domainIds: [] },
        addVL: { offen: false, laedt: false, laeuft: false, listenId: '', listen: [],
                 artikelthema: '', notiz: '', domainIds: [] },

        // Sistrix-Pre-Confirm-Modal + Live-Progress (Pattern wie im Linkprofil)
        sistrixPre: {
            offen: false, teil: 'si', label: 'SI', ids: [],
            laedt: false, vorschau: null, status: null, budgetReicht: true,
        },
        fortschritt: {
            offen: false, label: '', total: 0, done: 0, erfolge: 0,
            fehler: [], extra: '', abbrechen: false, fertig: false, fehlerOffen: false,
        },

        // Rechtsklick
        ctxMenu: { offen: false, x: 0, y: 0, ziel: null },

        // Drawer
        drawerOffen: false,
        drawer: {
            id: null, url: '', anbieter_id: '', verifikation_status: 'neu',
            linkart: '', buchbar_via: '', notizen: '', disqualifiziert: false,
            laeuft: false, flashFehler: null
        },

        // Portfolio-Import (Phase 2: Upload + Preview + Commit)
        pi: {
            drawerOffen: false, previewOffen: false,
            dateien: [], dragOver: false, laeuft: false, fehler: null,
            statusText: '', batch: null, data: null, tab: 'anbieter', filter: '',
            // Auswahl-Maps: kontakte[idx]=true/false, domains[idx]=true/false, sonderdeals[idx]=true/false
            anbieterUebernehmen: true,
            anbieterEdit: { name: '', firma: '' },
            kontakteSel: {}, domainsSel: {}, dealsSel: {},
            committing: false, commitErgebnis: null
        },

        filter: {
            suche: '', verifikation: [], anbieter_id: '', customer_id: '',
            linkart: [], tag_ids: [],
            si_min: '', si_max: '', preis_min: '', preis_max: '',
            nur_disqualifiziert: false, nur_nicht_erreichbar: false, nur_ungeprueft: false, ohne_si: false, ohne_dp: false, nur_in_wartezeit: false, nur_verfuegbar: false,
            sort: 'erstellt_am', order: 'desc', limit: 50, offset: 0,
        },
        // Linkquellen-Linkart: gleiches Vokabular wie Linkprofil-Analyse (17 Werte ohne Spam), alphabetisch
        linkartListe: [
            { slug: 'blog',                label: 'Blog' },
            { slug: 'branchenverzeichnis', label: 'Branchenverzeichnis' },
            { slug: 'fachverzeichnis',     label: 'Fachverzeichnis' },
            { slug: 'forum',               label: 'Forum' },
            { slug: 'kommentarlink',       label: 'Kommentarlink' },
            { slug: 'online_magazin',      label: 'Online-Magazin' },
            { slug: 'partner',             label: 'Partner' },
            { slug: 'podcast',             label: 'Podcast' },
            { slug: 'portal',              label: 'Portal' },
            { slug: 'presseportal',        label: 'Presseportal' },
            { slug: 'referenzprojekt',     label: 'Referenzprojekt' },
            { slug: 'social_media',        label: 'Social Media' },
            { slug: 'sonstiges',           label: 'Sonstiges' },
            { slug: 'sponsoring',          label: 'Sponsoring' },
            { slug: 'stellenboerse',       label: 'Stellenbörse' },
            { slug: 'veranstaltung',       label: 'Veranstaltung' },
            { slug: 'weiterleitung',       label: 'Weiterleitung' },
        ],

        async init() {
            // Sticky-Filter aus localStorage
            this.STORAGE_KEY = 'thx_lam_filter_linkquellen';
            try {
                const gespeichert = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
                Object.assign(this.filter, gespeichert);
            } catch (e) {}
            this.$watch('filter', (v) => {
                try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(v)); } catch (e) {}
            }, { deep: true });

            this.anbieter = await this.lade('/api/v1/lam/anbieter-kurz');
            this.alleTags = await this.lade('/api/v1/lam/tags');
            this.kunden   = await this.lade('/api/v1/lam/linkoptionen-kunden');
            this.reload();
        },
        filterZuruecksetzen() {
            try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) {}
            this.filter = {
                suche: '', verifikation: [], anbieter_id: '', customer_id: '',
                linkart: [], tag_ids: [],
                si_min: '', si_max: '', preis_min: '', preis_max: '',
                nur_disqualifiziert: false, nur_nicht_erreichbar: false, nur_ungeprueft: false, ohne_si: false, ohne_dp: false, nur_in_wartezeit: false, nur_verfuegbar: false,
                sort: 'erstellt_am', order: 'desc', limit: 50, offset: 0,
            };
            this.reload();
        },

        oeffneTagEdit(domain) {
            this.tagEdit.domain = domain;
            const aktuell = (domain.tag_ids || '').toString().split(',').map(s => parseInt(s)).filter(n => n > 0);
            this.tagEdit.ausgewaehlt = aktuell;
            this.tagEdit.offen = true;
        },
        toggleTagAuswahl(tagId) {
            const i = this.tagEdit.ausgewaehlt.indexOf(tagId);
            if (i >= 0) this.tagEdit.ausgewaehlt.splice(i, 1);
            else this.tagEdit.ausgewaehlt.push(tagId);
        },
        async speichereTags() {
            if (!this.tagEdit.domain) return;
            this.tagEdit.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-tags-set', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        domain_id: this.tagEdit.domain.id,
                        tag_ids: this.tagEdit.ausgewaehlt,
                    }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Speichern fehlgeschlagen.'); return; }
                this.tagEdit.offen = false;
                await this.reload();
            } finally { this.tagEdit.laeuft = false; }
        },
        async lade(url) {
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            return json.success ? json.data : [];
        },

        toggleVerifikation(s, event) {
            const liste = this.filter.verifikation;
            const idx = liste.indexOf(s);
            if (event && (event.shiftKey || event.ctrlKey || event.metaKey)) {
                if (idx === -1) liste.push(s); else liste.splice(idx, 1);
            } else {
                if (liste.length === 1 && liste[0] === s) this.filter.verifikation = [];
                else this.filter.verifikation = [s];
            }
            this.reload(true);
        },
        toggleLinkart(la, event) {
            const liste = this.filter.linkart;
            const idx = liste.indexOf(la);
            if (event && (event.shiftKey || event.ctrlKey || event.metaKey)) {
                if (idx === -1) liste.push(la); else liste.splice(idx, 1);
            } else {
                if (liste.length === 1 && liste[0] === la) this.filter.linkart = [];
                else this.filter.linkart = [la];
            }
            this.reload(true);
        },
        toggleTagFilter(tid, event) {
            const liste = this.filter.tag_ids;
            const idx = liste.indexOf(tid);
            if (event && (event.shiftKey || event.ctrlKey || event.metaKey)) {
                if (idx === -1) liste.push(tid); else liste.splice(idx, 1);
            } else {
                if (liste.length === 1 && liste[0] === tid) this.filter.tag_ids = [];
                else this.filter.tag_ids = [tid];
            }
            this.reload(true);
        },

        async reload(resetOffset = false) {
            if (resetOffset) this.filter.offset = 0;
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            this.filter.verifikation.forEach(v => p.append('verifikation_status[]', v));
            if (this.filter.anbieter_id === '__ohne__') p.set('ohne_anbieter', '1');
            else if (this.filter.anbieter_id) p.set('anbieter_id', this.filter.anbieter_id);
            if (this.filter.customer_id === '__ohne__') p.set('ohne_kunde', '1');
            else if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
            if (this.filter.nur_disqualifiziert) p.set('nur_disqualifiziert', '1');
            if (this.filter.nur_nicht_erreichbar) p.set('nur_nicht_erreichbar', '1');
            if (this.filter.nur_ungeprueft) p.set('nur_ungeprueft', '1');
            if (this.filter.ohne_si) p.set('ohne_si', '1');
            if (this.filter.ohne_dp) p.set('ohne_dp', '1');
            if (this.filter.nur_in_wartezeit) p.set('nur_in_wartezeit', '1');
            if (this.filter.nur_verfuegbar) p.set('nur_verfuegbar', '1');
            (this.filter.linkart || []).forEach(la => p.append('linkart[]', la));
            (this.filter.tag_ids || []).forEach(tid => p.append('tag_ids[]', tid));
            if (this.filter.si_min !== '') p.set('si_min', this.filter.si_min);
            if (this.filter.si_max !== '') p.set('si_max', this.filter.si_max);
            if (this.filter.preis_min !== '') p.set('preis_min', this.filter.preis_min);
            if (this.filter.preis_max !== '') p.set('preis_max', this.filter.preis_max);
            p.set('sort', this.filter.sort); p.set('order', this.filter.order);
            p.set('limit', this.filter.limit); p.set('offset', this.filter.offset);
            try {
                const res = await fetch('/api/v1/lam/linkquellen?' + p, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) { this.rows = json.data.rows; this.gesamt = json.data.gesamt; }
                else { this.rows = []; this.gesamt = 0; }
            } finally { this.laedt = false; }
        },

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
        seiteZurueck() { this.filter.offset = Math.max(0, this.filter.offset - parseInt(this.filter.limit)); this.reload(); },
        seiteVor() { this.filter.offset += parseInt(this.filter.limit); this.reload(); },

        // ─ Inline-Edit ─────────────────────────────────────────────────
        istOffen(id, feld) { return this.editZelle.id === id && this.editZelle.feld === feld; },
        oeffneEdit(d, feld) {
            if (this.editLaeuft) return;
            this.editZelle = { id: d.id, feld };
            this.editWert = d[feld] ?? '';
            if (feld === 'anbieter_id') this.editWert = d.anbieter_id || '';
        },
        schliesseEdit() { this.editZelle = { id: null, feld: null }; this.editWert = ''; },
        async speichereInline(d, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: d.id, feld, wert: this.editWert })
                });
                const json = await res.json();
                if (!json.success) { alert(json.message || 'Fehler'); return; }
                d[feld] = this.editWert;
                if (feld === 'anbieter_id') {
                    const a = this.anbieter.find(x => x.id === this.editWert);
                    d.anbieter_name = a ? a.name : null;
                }
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
            if (this.alleSichtbarGewaehlt()) { this.rows.forEach(r => neu.delete(r.id)); this.alleTrefferAusgewaehlt = false; }
            else this.rows.forEach(r => neu.add(r.id));
            this.auswahl = neu;
        },
        /**
         * Lädt ALLE Treffer (kein Pagination-Limit) mit den aktiven Filtern und
         * markiert sie als ausgewählt. Bulk-Aktionen wirken damit auf die komplette
         * gefilterte Liste, nicht nur auf die sichtbare Seite.
         */
        async alleTrefferAuswaehlen() {
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            this.filter.verifikation.forEach(v => p.append('verifikation_status[]', v));
            if (this.filter.anbieter_id === '__ohne__') p.set('ohne_anbieter', '1');
            else if (this.filter.anbieter_id) p.set('anbieter_id', this.filter.anbieter_id);
            if (this.filter.customer_id === '__ohne__') p.set('ohne_kunde', '1');
            else if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
            if (this.filter.nur_disqualifiziert) p.set('nur_disqualifiziert', '1');
            if (this.filter.nur_nicht_erreichbar) p.set('nur_nicht_erreichbar', '1');
            if (this.filter.nur_ungeprueft) p.set('nur_ungeprueft', '1');
            if (this.filter.ohne_si) p.set('ohne_si', '1');
            if (this.filter.ohne_dp) p.set('ohne_dp', '1');
            if (this.filter.nur_in_wartezeit) p.set('nur_in_wartezeit', '1');
            if (this.filter.nur_verfuegbar) p.set('nur_verfuegbar', '1');
            (this.filter.linkart || []).forEach(la => p.append('linkart[]', la));
            (this.filter.tag_ids || []).forEach(tid => p.append('tag_ids[]', tid));
            if (this.filter.si_min !== '') p.set('si_min', this.filter.si_min);
            if (this.filter.si_max !== '') p.set('si_max', this.filter.si_max);
            if (this.filter.preis_min !== '') p.set('preis_min', this.filter.preis_min);
            if (this.filter.preis_max !== '') p.set('preis_max', this.filter.preis_max);
            p.set('limit', Math.max(500, this.gesamt));
            p.set('offset', 0);
            try {
                const res = await fetch('/api/v1/lam/linkquellen?' + p, { credentials: 'same-origin' });
                const j = await res.json();
                if (!j.success) throw new Error(j.message);
                this.auswahl = new Set((j.data.rows || []).map(r => r.id));
                this.alleTrefferAusgewaehlt = true;
            } catch (e) { alert('Konnte nicht alle Treffer laden: ' + e.message); }
        },
        auswahlLeeren() { this.auswahl = new Set(); this.bulkAktion = ''; this.bulkWert = ''; this.alleTrefferAusgewaehlt = false; },
        async bulkAusfuehren() {
            if (this.bulkLaeuft || !this.bulkAktion || this.auswahl.size === 0) return;
            if (['verifikation_setzen','anbieter_setzen','tag_setzen','tag_entfernen'].includes(this.bulkAktion) && !this.bulkWert) return;
            if (this.bulkAktion === 'loeschen' && !confirm(`${this.auswahl.size} Domains wirklich löschen?`)) return;
            this.bulkLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/domain-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: this.bulkAktion, wert: this.bulkWert || null })
                });
                if ((await res.json()).success) { this.auswahlLeeren(); await this.reload(); }
            } finally { this.bulkLaeuft = false; }
        },

        async oeffneLinkpoolAdd() {
            if (this.auswahl.size === 0) return;
            this.addPool = { offen: true, laeuft: false, customerId: '', kunden: this.addPool.kunden, domainIds: Array.from(this.auswahl) };
            if (this.addPool.kunden.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkoptionen-kunden', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.addPool.kunden = j.data;
                } catch (e) {}
                // Falls noch keine Kunden mit LAM-Aktivität: alle Kunden aus dem allgemeinen Endpoint
                if (this.addPool.kunden.length === 0) {
                    try {
                        const r = await fetch('/api/v1/lam/anbieter-kurz', { credentials: 'same-origin' });
                        // Fallback: globale Kunden
                        const r2 = await fetch('/api/v1/admin/customers?limit=200', { credentials: 'same-origin' });
                        const j2 = await r2.json();
                        if (j2.success) this.addPool.kunden = (j2.data.rows || j2.data || []).map(c => ({
                            id: c.id, name: c.name, abbreviation: c.abbreviation, linkpool_count: 0,
                        }));
                    } catch (e) {}
                }
            }
        },
        async addPoolAusfuehren() {
            if (!this.addPool.customerId || this.addPool.domainIds.length === 0) return;
            this.addPool.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkpool-add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.addPool.customerId, domain_ids: this.addPool.domainIds }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                const kuerzel = (this.addPool.kunden.find(k => k.id == this.addPool.customerId) || {}).abbreviation || 'Kunde';
                alert(`✓ ${j.data.added} Domain(s) zum Linkpool von ${kuerzel} hinzugefügt${j.data.skipped > 0 ? ', ' + j.data.skipped + ' bereits drin' : ''}.`);
                this.addPool.offen = false;
                this.auswahlLeeren();
            } catch (e) { alert('Fehler: ' + e.message); }
            this.addPool.laeuft = false;
        },

        async oeffneVorschlagslisteAdd() {
            if (this.auswahl.size === 0) return;
            this.addVL.domainIds = Array.from(this.auswahl);
            this.addVL.listenId = '';
            this.addVL.artikelthema = '';
            this.addVL.notiz = '';
            this.addVL.offen = true;
            this.addVL.laedt = true;
            try {
                const r = await fetch('/api/v1/lam/vorschlagslisten?status=aktiv', { credentials: 'same-origin' });
                const j = await r.json();
                this.addVL.listen = j.success ? (j.data || []) : [];
            } catch (e) { this.addVL.listen = []; }
            this.addVL.laedt = false;
        },
        async vorschlagslisteAddAusfuehren() {
            if (!this.addVL.listenId || this.addVL.domainIds.length === 0 || this.addVL.laeuft) return;
            this.addVL.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/vorschlagsliste-eintrag-add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        vorschlagsliste_id: this.addVL.listenId,
                        domain_ids: this.addVL.domainIds,
                        artikelthema: this.addVL.artikelthema || null,
                        notiz: this.addVL.notiz || null,
                    }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                alert(`✓ ${j.data.added} hinzugefügt, ${j.data.skipped.length} bereits auf der Liste/ungültig (übersprungen).`);
                this.addVL.offen = false;
                this.auswahlLeeren();
            } catch (e) {
                alert('Fehler: ' + e.message);
            }
            this.addVL.laeuft = false;
        },

        async erreichbarkeitBulk() {
            if (this.bulkLaeuft || this.auswahl.size === 0) return;
            if (this.auswahl.size > 500) { alert('Max 500 pro Bulk-Lauf'); return; }
            const dauer = Math.ceil(this.auswahl.size * 0.3);
            if (!confirm(`${this.auswahl.size} Domains auf Erreichbarkeit prüfen? Dauert etwa ${dauer} Sekunden.`)) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-erreichbarkeit-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl) }),
                });
                const j = await r.json();
                if (j.success) {
                    alert(`Bulk-Check fertig: ${j.data.ok} geprüft, ${j.data.erreichbar} erreichbar, ${j.data.nicht_erreichbar} nicht erreichbar, ${j.data.fehler} Fehler.`);
                    this.auswahlLeeren();
                    await this.reload();
                } else {
                    alert(j.message || 'Bulk fehlgeschlagen');
                }
            } catch (e) {
                alert('Verbindungsfehler');
            } finally { this.bulkLaeuft = false; }
        },

        // Sistrix-Bulk oeffnet jetzt das Pre-Confirm-Modal mit Kosten-/Budget-Vorschau.
        async sistrixBulk(teil) {
            if (this.bulkLaeuft || this.auswahl.size === 0) return;
            await this.sistrixPreOeffnen(teil, Array.from(this.auswahl));
        },

        async sistrixPreOeffnen(teil, ids) {
            const labels = { si: 'SI', dp: 'DP', alter: 'Alter', alles: 'Alles (SI+Alter+DP)' };
            Object.assign(this.sistrixPre, {
                offen: true, teil: teil, label: labels[teil] || teil.toUpperCase(),
                ids: ids.slice(), laedt: true, vorschau: null, status: null, budgetReicht: true,
            });
            try {
                const teile = teil === 'alles' ? ['si', 'alter', 'dp'] : [teil];
                const res = await fetch('/api/v1/lam/sistrix-vorschau', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: ids, teile: teile, quelle: 'domain' })
                });
                const j = await res.json();
                if (!j.success) throw new Error(j.message || 'Vorschau fehlgeschlagen');
                this.sistrixPre.vorschau     = j.data.vorschau;
                this.sistrixPre.status       = j.data.status;
                this.sistrixPre.budgetReicht = !!j.data.budget_reicht;
            } catch (e) {
                this.sistrixPre.offen = false;
                App.showNotification('Konnte Vorschau nicht laden: ' + e.message, 'error');
            } finally {
                this.sistrixPre.laedt = false;
            }
        },
        sistrixPreSchliessen() { this.sistrixPre.offen = false; },
        async sistrixPreAnwenden() {
            const teil = this.sistrixPre.teil;
            const ids  = this.sistrixPre.ids.slice();
            const label = `Sistrix abrufen: ${this.sistrixPre.label}`;
            this.sistrixPre.offen = false;
            await this.sistrixBulkInChunks(teil, ids, label);
        },

        /** Chunked Sistrix-Bulk gegen /lam/sistrix-bulk (Domain-IDs). */
        async sistrixBulkInChunks(teil, ids, label) {
            if (!ids.length) return;
            Object.assign(this.fortschritt, {
                offen: true, label: label, total: ids.length, done: 0,
                erfolge: 0, fehler: [], extra: '', abbrechen: false, fertig: false, fehlerOffen: false,
            });
            let creditsVerbraucht = 0, cacheHits = 0;
            // Kleinere Chunks: eine Domain braucht bei "Alles" bis zu ~7s. Bei 10 Stueck lief ein
            // Request bis zu 70s — der Balken stand ewig auf 0 und der Request lief ins Timeout-Risiko.
            const chunkSize = 5;
            for (let i = 0; i < ids.length; i += chunkSize) {
                if (this.fortschritt.abbrechen) break;
                const chunk = ids.slice(i, i + chunkSize);
                try {
                    const res = await fetch('/api/v1/lam/sistrix-bulk', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: chunk, teil })
                    });
                    const j = await res.json();
                    if (!j.success) {
                        // API-Key-Fehler speziell behandeln (Hinweis auf Settings)
                        if ((j.message || '').includes('API-Key nicht')) {
                            this.fortschritt.fehler.push('Sistrix-API-Key nicht gesetzt — bitte unter /admin/settings?tab=sistrix eintragen.');
                            break;
                        }
                        throw new Error(j.message || 'Chunk fehlgeschlagen');
                    }
                    const d = j.data || {};
                    this.fortschritt.erfolge += (d.ok || 0);
                    creditsVerbraucht += (d.credits_verbraucht || 0);
                    cacheHits        += (d.cache_hits || 0);
                    if (Array.isArray(d.fehler) && d.fehler.length) {
                        d.fehler.forEach(f => this.fortschritt.fehler.push(`${f.id}: ${f.fehler}`));
                    }
                    const parts = [];
                    if (creditsVerbraucht > 0) parts.push(creditsVerbraucht.toLocaleString('de-DE') + ' Credits verbraucht');
                    if (cacheHits > 0)        parts.push(cacheHits + ' aus Cache');
                    if (parts.length) this.fortschritt.extra = parts.join(' · ');
                } catch (e) {
                    this.fortschritt.fehler.push(`Chunk ab Position ${i + 1}: ${e.message || 'Netzwerkfehler'}`);
                }
                this.fortschritt.done = Math.min(i + chunkSize, ids.length);
                // WICHTIG: kein requestAnimationFrame! Das feuert nicht, wenn der Tab im Hintergrund
                // ist — die Schleife blieb dann nach dem ersten Chunk fuer immer haengen (ohne Fehler,
                // ohne Fortschritt). setTimeout laeuft auch im Hintergrund-Tab weiter.
                await new Promise(r => setTimeout(r, 50));
            }
            this.fortschritt.fertig = true;
            await this.reload();
        },
        fortschrittSchliessen() {
            this.fortschritt.offen = false;
            if (!this.fortschritt.abbrechen) this.auswahlLeeren?.();
        },
        zahl(n) { return n == null ? '0' : new Intl.NumberFormat('de-DE').format(n); },

        // ─ Detail-Modal ────────────────────────────────────────────────
        async oeffneDetail(d) {
            if (!d) return;
            this.detailOffen = true;
            this.detail = d;
            try {
                const res = await fetch('/api/v1/lam/domain-detail?id=' + encodeURIComponent(d.id), { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) this.detail = json.data;
            } catch (e) {}
        },
        schliesseDetail() { this.detailOffen = false; this.detail = null; },

        // ─ Rechtsklick ─────────────────────────────────────────────────
        oeffneCtxMenu(event, ziel) {
            const x = event.clientX, y = event.clientY;
            const px = (x + 220 > window.innerWidth) ? x - 220 : x;
            const py = (y + 380 > window.innerHeight) ? y - 380 : y;
            this.ctxMenu = { offen: true, x: px, y: py, ziel };
        },
        async schnellAktion(ziel, feld, wert) {
            if (!ziel) return;
            try {
                const res = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld, wert })
                });
                if ((await res.json()).success) ziel[feld] = wert;
            } catch (e) {}
        },
        async loescheDomain(ziel) {
            if (!ziel) return;
            if (!confirm(`"${ziel.url}" wirklich löschen?`)) return;
            await fetch('/api/v1/lam/domain-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [ziel.id], aktion: 'loeschen' })
            });
            await this.reload();
        },

        // ─ Drawer (Anlegen/Bearbeiten) ─────────────────────────────────
        oeffneNeuDrawer() {
            this.drawer = { id: null, url: '', anbieter_id: '', verifikation_status: 'neu',
                            linkart: '', buchbar_via: '', notizen: '', disqualifiziert: false,
                            laeuft: false, flashFehler: null };
            this.drawerOffen = true;
        },
        oeffneBearbeitenDrawer(d) {
            if (!d) return;
            this.drawer = {
                id: d.id, url: d.url || '', anbieter_id: d.anbieter_id || '',
                verifikation_status: d.verifikation_status || 'neu',
                linkart: d.linkart || '', buchbar_via: d.buchbar_via || '',
                notizen: d.notizen || '', disqualifiziert: d.disqualifiziert == 1,
                laeuft: false, flashFehler: null
            };
            this.drawerOffen = true;
        },
        schliesseDrawer() { this.drawerOffen = false; },
        async speichereDrawer() {
            if (this.drawer.laeuft) return;
            this.drawer.flashFehler = null;
            if (!this.drawer.url.trim()) { this.drawer.flashFehler = 'URL ist erforderlich'; return; }
            this.drawer.laeuft = true;
            try {
                const res = await fetch('/api/v1/lam/domain-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.drawer)
                });
                const json = await res.json();
                if (!json.success) { this.drawer.flashFehler = json.message || 'Fehler'; return; }
                this.drawerOffen = false; await this.reload();
            } finally { this.drawer.laeuft = false; }
        },

        zahl(n) { return n == null ? '0' : new Intl.NumberFormat('de-DE').format(n); },
        zahlSi(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        formatiereCheckDatum(d) {
            if (!d) return '';
            const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}.${m[2]}.${m[1]}` : d;
        },
        async sistrixAktualisieren(domainId) {
            if (!confirm('SI + DP für diese Domain neu abrufen? Kostet ein paar Sistrix-Credits.')) return;
            try {
                const r = await fetch('/api/v1/lam/sistrix-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_ids: [domainId], variante: 'alles' }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.reload(false);
            } catch (e) { alert('Sistrix-Abruf fehlgeschlagen: ' + e.message); }
        },
        euro(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 0 }); },
        kopiereURL(url) {
            if (!url) return;
            navigator.clipboard?.writeText(url).then(
                () => { /* still */ },
                () => alert('Konnte nicht kopieren')
            );
        },
        siAlterKlasse(dateStr) {
            if (!dateStr) return 'si-stale';
            const tage = (Date.now() - new Date(dateStr).getTime()) / 86400000;
            if (tage < 7) return 'si-fresh';
            if (tage < 30) return 'si-mid';
            if (tage < 90) return 'si-old';
            return 'si-stale';
        },

        // ===== Portfolio-Import =====
        // ===== Linkquellen-Import (XLSX/CSV) =====
        lqDateiDrop(ev) {
            this.lqImport.dragOver = false;
            if (ev.dataTransfer.files && ev.dataTransfer.files[0]) this.lqDateiGewaehlt(ev.dataTransfer.files[0]);
        },
        async lqDateiGewaehlt(file) {
            if (!file) return;
            const ext = file.name.toLowerCase().split('.').pop();
            if (!['xlsx', 'xlsm', 'csv'].includes(ext)) {
                this.lqImport.fehler = 'Nur XLSX/XLSM/CSV erlaubt';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                this.lqImport.fehler = 'Datei zu groß (max. 10 MB)';
                return;
            }
            this.lqImport.fehler = '';
            const fd = new FormData();
            fd.append('file', file);
            try {
                const r = await fetch('/api/v1/lam/linkquellen-import-preview', { method: 'POST', body: fd, credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                if ((j.data.kandidaten || []).length === 0) {
                    this.lqImport.fehler = 'Keine URLs gefunden — bitte sicherstellen, dass eine Spalte „URL"/„Domain"/„Website" existiert oder die Werte URL-Pattern haben.';
                    return;
                }
                this.lqImport.token = j.data.token;
                this.lqImport.datei = j.data.datei;
                this.lqImport.spalten = j.data.spalten;
                this.lqImport.stats = j.data.stats;
                // Default: alle neuen vorausgewählt, Dubletten abgewählt
                this.lqImport.kandidaten = (j.data.kandidaten || []).map(k => ({ ...k, _wahl: !k.existiert }));
                this.lqImport.phase = 'preview';
                // Kundenliste laden für den „Direkt zu Linkpool"-Dropdown
                if (this.lqImport.kunden.length === 0) {
                    try {
                        const kr = await fetch('/api/v1/admin/customers?limit=200', { credentials: 'same-origin' });
                        const kj = await kr.json();
                        if (kj.success) {
                            const liste = kj.data.rows || kj.data || [];
                            this.lqImport.kunden = liste.map(c => ({ id: c.id, name: c.name, abbreviation: c.abbreviation }))
                                                       .sort((a, b) => (a.abbreviation || '').localeCompare(b.abbreviation || ''));
                        }
                    } catch (e) {}
                }
            } catch (e) { this.lqImport.fehler = 'Verarbeitung fehlgeschlagen: ' + e.message; }
        },
        lqAlleWahl(an) {
            this.lqImport.kandidaten.forEach(k => { if (!k.existiert) k._wahl = an; });
        },
        lqNurNeueWahl() {
            this.lqImport.kandidaten.forEach(k => { k._wahl = !k.existiert; });
        },
        async lqImportieren() {
            const urls = this.lqImport.kandidaten.filter(k => k._wahl && !k.existiert).map(k => k.url);
            const poolCustomerId = parseInt(this.lqImport.poolCustomerId) || 0;
            if (urls.length === 0 && poolCustomerId === 0) {
                alert('Keine Auswahl: weder neue Domains noch ein Kunde für den Linkpool.');
                return;
            }
            this.lqImport.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkquellen-import-commit', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: this.lqImport.token,
                        urls,
                        herkunft: 'Linkquellen-Import: ' + this.lqImport.datei + (this.lqImport.kontext ? ' · Kontext: ' + this.lqImport.kontext : ''),
                        kontext: this.lqImport.kontext || '',
                        linkpool_customer_id: poolCustomerId,
                    }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.lqImport.ergebnis = j.data;
                this.lqImport.phase = 'fertig';
            } catch (e) {
                this.lqImport.fehler = 'Import fehlgeschlagen: ' + e.message;
            }
            this.lqImport.laeuft = false;
        },

        piOeffnen() {
            this.pi.drawerOffen = true;
            this.pi.dateien = []; this.pi.fehler = null; this.pi.laeuft = false;
        },
        piSchliessen() { this.pi.drawerOffen = false; },
        piDrop(e) {
            this.pi.dragOver = false;
            this.piFilesGewaehlt(e.dataTransfer.files);
        },
        piFilesGewaehlt(fl) {
            this.pi.fehler = null;
            for (const f of fl) {
                const n = f.name.toLowerCase();
                if (!n.match(/\.(eml|msg|xlsx|csv|pdf)$/)) { this.pi.fehler = 'Nur EML/MSG/XLSX/CSV/PDF erlaubt: ' + f.name; continue; }
                if (f.size > 25 * 1024 * 1024) { this.pi.fehler = f.name + ' ist > 25 MB'; continue; }
                this.pi.dateien.push(f);
            }
        },
        piIcon(name) {
            const n = name.toLowerCase();
            if (n.endsWith('.eml') || n.endsWith('.msg')) return 'mail';
            if (n.endsWith('.xlsx') || n.endsWith('.csv')) return 'table_chart';
            if (n.endsWith('.pdf')) return 'picture_as_pdf';
            return 'description';
        },
        piSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1024 * 1024) return (b/1024).toFixed(0) + ' KB';
            return (b/1024/1024).toFixed(1) + ' MB';
        },
        piPrice(v) { return (v == null || v === 0) ? '—' : this.euro(v); },
        piDomainsGefiltert() {
            const list = (this.pi.data?.domains || []).map((d, i) => ({ ...d, __idx: i }));
            const q = (this.pi.filter || '').toLowerCase().trim();
            if (!q) return list;
            return list.filter(d => (d.vorschlag.url || '').toLowerCase().includes(q));
        },
        async piStart() {
            if (!this.pi.dateien.length) return;
            this.pi.laeuft = true; this.pi.fehler = null;
            try {
                this.pi.statusText = 'Lädt hoch…';
                const fd = new FormData();
                for (const f of this.pi.dateien) fd.append('files[]', f);
                const upRes = await fetch('/api/v1/lam/portfolio-import/upload', { method: 'POST', body: fd, credentials: 'same-origin' });
                const upJson = await upRes.json();
                if (!upJson.success) throw new Error(upJson.message || 'Upload fehlgeschlagen');
                this.pi.batch = upJson.data.batch_id;

                this.pi.statusText = 'KI analysiert…';
                const anRes = await fetch('/api/v1/lam/portfolio-import/' + this.pi.batch + '/analyse', { credentials: 'same-origin' });
                const anJson = await anRes.json();
                if (!anJson.success) throw new Error(anJson.message || 'Analyse fehlgeschlagen');
                this.pi.data = anJson.data.extraction;
                this.pi.tab = 'anbieter';
                this.pi.drawerOffen = false;
                this.pi.previewOffen = true;
                this.piInitAuswahl();
            } catch (e) {
                this.pi.fehler = e.message || 'Fehler';
            } finally {
                this.pi.laeuft = false; this.pi.statusText = '';
            }
        },

        // Auswahl-Defaults nach Analyse: alles "übernehmen", Match-Edits in Anbieter-Form vorblenden
        piInitAuswahl() {
            const d = this.pi.data;
            this.pi.anbieterUebernehmen = !!(d?.anbieter);
            this.pi.anbieterEdit = {
                name: d?.anbieter?.vorschlag?.name || '',
                firma: d?.anbieter?.vorschlag?.firma || ''
            };
            this.pi.kontakteSel = {};
            (d?.kontakte || []).forEach((k, i) => { this.pi.kontakteSel[i] = !k.match; }); // bestehende Kontakte default NICHT
            this.pi.domainsSel = {};
            (d?.domains || []).forEach((dom, i) => { this.pi.domainsSel[i] = true; });
            this.pi.dealsSel = {};
            (d?.sonderdeals || []).forEach((sd, i) => { this.pi.dealsSel[i] = true; });
            this.pi.commitErgebnis = null;
        },

        piSelAlle(typ, val) {
            if (typ === 'kontakte') (this.pi.data?.kontakte || []).forEach((_, i) => this.pi.kontakteSel[i] = val);
            else if (typ === 'domains') (this.pi.data?.domains || []).forEach((_, i) => this.pi.domainsSel[i] = val);
            else if (typ === 'deals') (this.pi.data?.sonderdeals || []).forEach((_, i) => this.pi.dealsSel[i] = val);
        },
        piSelNurNeu() {
            (this.pi.data?.domains || []).forEach((d, i) => this.pi.domainsSel[i] = (d.status === 'neu'));
        },

        piAuswahlCount(typ) {
            const sel = typ === 'kontakte' ? this.pi.kontakteSel
                       : typ === 'domains' ? this.pi.domainsSel
                       : this.pi.dealsSel;
            return Object.values(sel).filter(Boolean).length;
        },

        async piCommit() {
            if (!this.pi.batch || this.pi.committing) return;
            const sicher = confirm('Import endgültig speichern? Diese Aktion ist nicht automatisch rückgängig zu machen.');
            if (!sicher) return;
            this.pi.committing = true;
            try {
                const auswahl = {
                    anbieter: {
                        uebernehmen: this.pi.anbieterUebernehmen,
                        vorschlag: {
                            name: this.pi.anbieterEdit.name,
                            firma: this.pi.anbieterEdit.firma
                        }
                    },
                    kontakte: Object.keys(this.pi.kontakteSel).map(i => ({ idx: parseInt(i, 10), uebernehmen: this.pi.kontakteSel[i] })),
                    domains: Object.keys(this.pi.domainsSel).map(i => ({ idx: parseInt(i, 10), uebernehmen: this.pi.domainsSel[i] })),
                    sonderdeals: Object.keys(this.pi.dealsSel).map(i => ({ idx: parseInt(i, 10), uebernehmen: this.pi.dealsSel[i] }))
                };
                const res = await fetch('/api/v1/lam/portfolio-import/' + this.pi.batch + '/commit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ auswahl })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Commit fehlgeschlagen');
                this.pi.commitErgebnis = json.data?.stats || {};
                // Liste neu laden (Domains-Pool hat sich geändert)
                setTimeout(() => this.reload(true), 800);
            } catch (e) {
                alert('Fehler: ' + (e.message || 'unbekannt'));
            } finally {
                this.pi.committing = false;
            }
        }
    };
}
</script>
