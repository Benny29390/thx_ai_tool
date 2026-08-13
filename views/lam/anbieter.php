<?php $activeModul = 'anbieter'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamAnbieter()" x-init="initFilter(); laden()" @click="ctxMenu.offen = false">

<div class="thx-page-header">
    <div>
        <h1 class="thx-page-title">Anbieter</h1>
        <div class="thx-page-subtitle">Betreiber und Vermittler von Linkquellen, mit Beziehungsstatus und Kontaktzahl.</div>
    </div>
    <div class="thx-page-actions">
        <button class="lam-btn lam-btn-primary" @click="oeffneNeuDrawer()">+ Neuer Anbieter</button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

    <!-- Filter-Card -->
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Einträge') : ''"></span>
        </div>

        <div class="lam-filter-grid">
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Suche (Name oder Firma)</label>
                <input type="text" class="lam-filter-input"
                       placeholder="z.B. Bantle, Verlag, performance"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
            </div>
            <div class="lam-filter-col-6">
                <label class="lam-filter-label">Rolle</label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-reset"
                            :class="filter.rolle === '' ? 'is-active' : ''"
                            @click="filter.rolle = ''; filter.offset = 0; laden()">alle</button>
                    <button type="button" class="lam-chip"
                            :class="filter.rolle === 'betreiber' ? 'is-active' : ''"
                            @click="filter.rolle = 'betreiber'; filter.offset = 0; laden()">nur Betreiber</button>
                    <button type="button" class="lam-chip"
                            :class="filter.rolle === 'vermittler' ? 'is-active' : ''"
                            @click="filter.rolle = 'vermittler'; filter.offset = 0; laden()">nur Vermittler</button>
                    <button type="button" class="lam-chip"
                            :class="filter.rolle === 'beides' ? 'is-active' : ''"
                            @click="filter.rolle = 'beides'; filter.offset = 0; laden()">beides</button>
                </div>
            </div>
            <div class="lam-filter-col-12" style="display:flex;justify-content:flex-end;">
                <button type="button" class="thx-btn thx-btn-secondary thx-btn-small"
                        @click="filterZuruecksetzen()"
                        style="font-size:0.75rem;color:var(--slate-500);">
                    Filter zurücksetzen
                </button>
            </div>
            <div class="lam-filter-col-12">
                <label class="lam-filter-label">Beziehung</label>
                <div class="lam-chip-row">
                    <button type="button" class="lam-chip lam-chip-reset"
                            :class="filter.beziehung === '' ? 'is-active' : ''"
                            @click="filter.beziehung = ''; filter.offset = 0; laden()">alle</button>
                    <template x-for="b in ['neu','etabliert','vertrauensvoll','abgekuehlt']" :key="b">
                        <button type="button" class="lam-chip"
                                :class="filter.beziehung === b ? 'is-active' : ''"
                                @click="filter.beziehung = b; filter.offset = 0; laden()"
                                x-text="beziehungLabel(b)"></button>
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
            <option value="">Aktion wählen …</option>
            <option value="beziehung_setzen">Beziehung setzen</option>
            <option value="rolle_setzen">Rolle setzen</option>
            <option value="loeschen">Löschen (soft)</option>
        </select>
        <select x-show="bulkAktion === 'beziehung_setzen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Wert wählen —</option>
            <option value="neu">Neu</option>
            <option value="etabliert">Etabliert</option>
            <option value="vertrauensvoll">Vertrauensvoll</option>
            <option value="abgekuehlt">Abgekühlt</option>
        </select>
        <select x-show="bulkAktion === 'rolle_setzen'" x-model="bulkWert" class="lam-filter-select" style="width:auto;">
            <option value="">— Wert wählen —</option>
            <option value="betreiber">Nur Betreiber</option>
            <option value="vermittler">Nur Vermittler</option>
            <option value="beides">Beides</option>
        </select>
        <button class="lam-btn lam-btn-primary lam-btn-small"
                @click="bulkAusfuehren()"
                :disabled="bulkLaeuft || !bulkAktion || (bulkAktion !== 'loeschen' && !bulkWert)">
            <span x-show="!bulkLaeuft">Anwenden</span>
            <span x-show="bulkLaeuft">Läuft …</span>
        </button>
        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <!-- Tabelle mit Inline-Edit + Bulk + Rechtsklick -->
    <section class="lam-table-card">
        <div class="lam-table-wrap">
            <table class="lam-table">
                <thead>
                    <tr>
                        <th class="thx-bulk-col">
                            <input type="checkbox" class="thx-bulk-checkbox"
                                   :checked="alleSichtbarGewaehlt()"
                                   @change="toggleAlleSichtbar()" title="Alle auswählen">
                        </th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('name')">
                            Name <span class="sort-icon" x-text="sortPfeil('name')"></span>
                        </th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('firma')">
                            Firma <span class="sort-icon" x-text="sortPfeil('firma')"></span>
                        </th>
                        <th>Rolle</th>
                        <th style="cursor:pointer;user-select:none;" @click="sortBy('beziehung')">
                            Beziehung <span class="sort-icon" x-text="sortPfeil('beziehung')"></span>
                        </th>
                        <th class="right" style="cursor:pointer;user-select:none;" @click="sortBy('domains')">
                            Domains <span class="sort-icon" x-text="sortPfeil('domains')"></span>
                        </th>
                        <th class="right" style="cursor:pointer;user-select:none;" @click="sortBy('kontakte')">
                            Kontakte <span class="sort-icon" x-text="sortPfeil('kontakte')"></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="a in rows" :key="a.id">
                        <tr :class="auswahl.has(a.id) ? 'is-bulk-selected' : ''"
                            @contextmenu.prevent="oeffneCtxMenu($event, a)">
                            <td class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox"
                                       :checked="auswahl.has(a.id)"
                                       @change="toggleAuswahl(a.id)" @click.stop>
                            </td>

                            <!-- Name: Klick = Detail-Seite, Stift = Inline-Edit -->
                            <td>
                                <template x-if="!istOffen(a.id, 'name')">
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <a :href="'/lam/anbieter/' + encodeURIComponent(a.id)"
                                           style="font-weight:600;color:var(--slate-800);text-decoration:none;"
                                           onmouseover="this.style.textDecoration='underline'"
                                           onmouseout="this.style.textDecoration='none'"
                                           x-text="a.name"></a>
                                        <button class="thx-inline-edit-pen"
                                                @click.stop="oeffneEdit(a, 'name')"
                                                title="Inline bearbeiten">✎</button>
                                    </div>
                                </template>
                                <template x-if="istOffen(a.id, 'name')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <input type="text" class="thx-inline-edit-input"
                                               x-model="editWert" x-init="$el.focus(); $el.select()"
                                               @keydown.enter="speichereInline(a, 'name')">
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small"
                                                    @click="speichereInline(a, 'name')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <!-- Firma -->
                            <td>
                                <template x-if="!istOffen(a.id, 'firma')">
                                    <button class="thx-inline-edit" :class="!a.firma ? 'is-empty' : ''"
                                            @click="oeffneEdit(a, 'firma')"
                                            x-text="a.firma || '— ergänzen'"></button>
                                </template>
                                <template x-if="istOffen(a.id, 'firma')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <input type="text" class="thx-inline-edit-input"
                                               x-model="editWert" x-init="$el.focus(); $el.select()"
                                               placeholder="z.B. Bantle Media GmbH"
                                               @keydown.enter="speichereInline(a, 'firma')">
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small"
                                                    @click="speichereInline(a, 'firma')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <!-- Rolle (mit Farbgebung) -->
                            <td>
                                <template x-if="!istOffen(a.id, 'rolle')">
                                    <button class="thx-inline-edit lam-rolle-badge"
                                            :style="rolleStyle(a)"
                                            @click="oeffneEdit(a, 'rolle')" x-text="a.rollen_label"></button>
                                </template>
                                <template x-if="istOffen(a.id, 'rolle')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="betreiber">Nur Betreiber</option>
                                            <option value="vermittler">Nur Vermittler</option>
                                            <option value="beides">Beides (Betreiber + Vermittler)</option>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small"
                                                    @click="speichereInline(a, 'rolle')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <!-- Beziehung -->
                            <td>
                                <template x-if="!istOffen(a.id, 'beziehungsstatus')">
                                    <button class="thx-inline-edit lam-rolle-badge"
                                            :style="beziehungStyle(a.beziehungsstatus)"
                                            @click="oeffneEdit(a, 'beziehungsstatus')"
                                            x-text="beziehungLabel(a.beziehungsstatus)"></button>
                                </template>
                                <template x-if="istOffen(a.id, 'beziehungsstatus')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="neu">Neu</option>
                                            <option value="etabliert">Etabliert</option>
                                            <option value="vertrauensvoll">Vertrauensvoll</option>
                                            <option value="abgekuehlt">Abgekühlt</option>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small"
                                                    @click="speichereInline(a, 'beziehungsstatus')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <td class="right" x-text="a.domains_count ?? 0"></td>
                            <td class="right" x-text="a.kontakte_count ?? 0"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Anbieter mit diesen Filtern.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0">
                <span class="lam-spinner"></span> Lade Anbieter …
            </div>
        </div>

        <!-- Pagination -->
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;padding:12px 16px;border-top:1px solid var(--slate-100);background:var(--slate-50);font-size:var(--d-fs-sm);">
            <div style="display:flex;align-items:center;gap:10px;color:var(--slate-600);">
                <span>Pro Seite</span>
                <select x-model.number="filter.limit" @change="wechsleSeitengroesse()"
                        style="padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250">250</option>
                </select>
                <span style="color:var(--slate-400);">·</span>
                <span><strong x-text="totalCount"></strong> Anbieter</span>
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
    <div class="thx-contextmenu"
         x-show="ctxMenu.offen" x-cloak
         :style="`top: ${ctxMenu.y}px; left: ${ctxMenu.x}px;`"
         @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.ziel?.name || ''"></div>
        <a class="thx-contextmenu-item" :href="ctxMenu.ziel ? '/lam/anbieter/' + encodeURIComponent(ctxMenu.ziel.id) : '#'" style="text-decoration:none;">Detail-Seite öffnen</a>
        <button class="thx-contextmenu-item" @click="oeffneBearbeitenDrawer(ctxMenu.ziel); ctxMenu.offen = false">Bearbeiten …</button>
        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Beziehungsstatus setzen</div>
        <template x-for="b in ['neu','etabliert','vertrauensvoll','abgekuehlt']" :key="b">
            <button class="thx-contextmenu-item"
                    @click="schnellAktion(ctxMenu.ziel, 'beziehungsstatus', b); ctxMenu.offen = false"
                    x-text="beziehungLabel(b)"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item is-danger"
                @click="loescheAnbieter(ctxMenu.ziel); ctxMenu.offen = false">
            Löschen
        </button>
    </div>

    <!-- Anlegen-/Bearbeiten-Drawer -->
    <div class="thx-drawer-backdrop" x-show="drawerOffen" @click.self="schliesseDrawer()" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title" x-text="drawer.id ? 'Anbieter bearbeiten' : 'Neuer Anbieter'"></h2>
                <button class="thx-modal-close" @click="schliesseDrawer()">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>Name *</label>
                    <input type="text" x-model="drawer.name" placeholder="z.B. Bantle Media">
                    <div class="thx-error" x-show="drawer.fehler.name" x-text="drawer.fehler.name"></div>
                </div>
                <div class="thx-form-field">
                    <label>Firma</label>
                    <input type="text" x-model="drawer.firma" placeholder="z.B. Bantle Media GmbH">
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Rolle</label>
                        <select x-model="drawer.rolle">
                            <option value="betreiber">Nur Betreiber</option>
                            <option value="vermittler">Nur Vermittler</option>
                            <option value="beides">Beides</option>
                        </select>
                    </div>
                    <div class="thx-form-field">
                        <label>Beziehung</label>
                        <select x-model="drawer.beziehungsstatus">
                            <option value="neu">neu</option>
                            <option value="etabliert">etabliert</option>
                            <option value="vertrauensvoll">vertrauensvoll</option>
                            <option value="abgekuehlt">abgekühlt</option>
                        </select>
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Notizen</label>
                    <textarea x-model="drawer.notizen" rows="5" placeholder="freie Notizen, Vereinbarungen etc."></textarea>
                </div>
                <div class="thx-error" x-show="drawer.flashFehler" x-text="drawer.flashFehler" style="margin-top:8px;"></div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="schliesseDrawer()">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereDrawer()" :disabled="drawer.laeuft">
                    <span x-show="!drawer.laeuft">Speichern</span>
                    <span x-show="drawer.laeuft">Läuft …</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Kontakt-Drawer -->
    <div class="thx-drawer-backdrop" x-show="kontaktDrawer.offen" @click.self="schliesseKontaktDrawer()" x-cloak style="z-index:360;">
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title" x-text="kontaktDrawer.id ? 'Kontakt bearbeiten' : 'Neuer Kontakt'"></h2>
                <button class="thx-modal-close" @click="schliesseKontaktDrawer()">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Vorname</label>
                        <input type="text" x-model="kontaktDrawer.vorname">
                    </div>
                    <div class="thx-form-field">
                        <label>Nachname *</label>
                        <input type="text" x-model="kontaktDrawer.nachname">
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>E-Mail</label>
                    <input type="email" x-model="kontaktDrawer.email">
                </div>
                <div class="thx-form-field">
                    <label>Telefon</label>
                    <input type="text" x-model="kontaktDrawer.telefon">
                </div>
                <div class="thx-form-field">
                    <label>Rolle (z.B. Vertrieb, Redaktion)</label>
                    <input type="text" x-model="kontaktDrawer.rolle">
                </div>
                <div class="thx-error" x-show="kontaktDrawer.flashFehler" x-text="kontaktDrawer.flashFehler"></div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="schliesseKontaktDrawer()">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereKontaktDrawer()" :disabled="kontaktDrawer.laeuft">
                    <span x-show="!kontaktDrawer.laeuft">Speichern</span>
                    <span x-show="kontaktDrawer.laeuft">Läuft …</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Detail-Modal -->
    <div class="thx-modal-backdrop" x-show="detailOffen" @click.self="schliesseDetail()" x-cloak>
        <div class="thx-modal" x-show="detailOffen">
            <div class="thx-modal-header">
                <div>
                    <h2 class="thx-modal-title" x-text="detail?.name || ''"></h2>
                    <div style="margin-top:4px;font-size:var(--d-fs-sm);color:var(--slate-500);">
                        <span x-text="detail?.rollen_label || ''"></span>
                        <span x-show="detail?.firma" x-text="' · ' + (detail?.firma || '')"></span>
                        <span x-show="detail?.beziehungsstatus" x-text="' · ' + (detail?.beziehungsstatus || '')"></span>
                    </div>
                </div>
                <button class="thx-modal-close" @click="schliesseDetail()">×</button>
            </div>
            <div class="thx-modal-body">
                <template x-if="detail?.notizen">
                    <div class="thx-modal-section">
                        <h3>Notizen</h3>
                        <div x-text="detail.notizen" style="white-space:pre-wrap;color:var(--slate-700);"></div>
                    </div>
                </template>

                <div class="thx-modal-section">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <h3 style="margin:0;">Kontakte (<span x-text="(detail?.kontakte || []).length"></span>)</h3>
                        <button class="lam-btn lam-btn-accent lam-btn-small" @click="oeffneKontaktDrawer(null)">+ Kontakt</button>
                    </div>
                    <template x-if="(detail?.kontakte || []).length > 0">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th></th><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Rolle</th><th></th></tr></thead>
                            <tbody>
                                <template x-for="k in (detail?.kontakte || [])" :key="k.id">
                                    <tr>
                                        <td class="center" style="width:24px;">
                                            <template x-if="k.prioritaet == 1">
                                                <span title="Primärkontakt" style="color:var(--amber-500);">★</span>
                                            </template>
                                        </td>
                                        <td x-text="k.name || k.nachname || '—'"></td>
                                        <td x-text="k.email || '—'"></td>
                                        <td x-text="k.telefon || '—'"></td>
                                        <td x-text="k.rolle || '—'"></td>
                                        <td style="white-space:nowrap;">
                                            <button class="thx-inline-edit-pen" @click="setzeKontaktPrimaer(k)" title="Als Primär markieren">★</button>
                                            <button class="thx-inline-edit-pen" @click="oeffneKontaktDrawer(k)" title="Bearbeiten">✎</button>
                                            <button class="thx-inline-edit-pen" @click="loescheKontakt(k)" title="Löschen" style="color:var(--rose-600);">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                    <template x-if="(detail?.kontakte || []).length === 0">
                        <div class="muted">Keine Kontakte hinterlegt.</div>
                    </template>
                </div>

                <div class="thx-modal-section">
                    <h3>Domains (<span x-text="(detail?.domains || []).length"></span>)</h3>
                    <template x-if="(detail?.domains || []).length > 0">
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>URL</th><th>Verifikation</th><th>Letzter Check</th></tr></thead>
                            <tbody>
                                <template x-for="d in (detail?.domains || [])" :key="d.id">
                                    <tr>
                                        <td class="url-cell">
                                            <a :href="'https://' + d.url" target="_blank" rel="noopener" x-text="d.url"></a>
                                        </td>
                                        <td x-text="d.verifikation_status"></td>
                                        <td x-text="d.letzter_check_am || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                    <template x-if="(detail?.domains || []).length === 0">
                        <div class="muted">Keine Domains zugeordnet.</div>
                    </template>
                </div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="schliesseDetail()">Schließen</button>
                <button class="lam-btn lam-btn-primary" @click="schliesseDetail(); oeffneBearbeitenDrawer({ id: detail?.id, name: detail?.name, firma: detail?.firma, ist_betreiber: detail?.ist_betreiber, ist_vermittler: detail?.ist_vermittler, beziehungsstatus: detail?.beziehungsstatus, notizen: detail?.notizen })">Bearbeiten</button>
            </div>
        </div>
    </div>

</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
function lamAnbieter() {
    return {
        laedt: true,
        rows: [],
        filter: {
            suche: '',
            rolle: '',
            beziehung: '',
            sort: 'name_asc',  // name_asc/name_desc, firma_asc/desc, beziehung, domains_desc, kontakte_desc
            limit: 50,
            offset: 0,
        },
        totalCount: 0,

        // Lesbare Labels
        beziehungLabels: { neu: 'Neu', etabliert: 'Etabliert', vertrauensvoll: 'Vertrauensvoll', abgekuehlt: 'Abgekühlt' },
        beziehungLabel(s) { return this.beziehungLabels[s] || s; },
        beziehungStyle(s) {
            const m = {
                neu: 'background:var(--amber-100);color:var(--amber-800);',
                etabliert: 'background:var(--thoxan-100);color:var(--thoxan-700);',
                vertrauensvoll: 'background:var(--emerald-100);color:var(--emerald-800);',
                abgekuehlt: 'background:var(--slate-200);color:var(--slate-600);',
            };
            return m[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },

        // Sortierung
        sortBy(feld) {
            const aktuell = this.filter.sort;
            if (aktuell === feld + '_asc') this.filter.sort = feld + '_desc';
            else this.filter.sort = feld + '_asc';
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
            if (this.filter.offset + this.filter.limit < this.totalCount) {
                this.filter.offset += this.filter.limit;
                this.laden();
            }
        },
        seiteZurueck() {
            if (this.filter.offset > 0) {
                this.filter.offset = Math.max(0, this.filter.offset - this.filter.limit);
                this.laden();
            }
        },
        wechsleSeitengroesse() { this.filter.offset = 0; this.laden(); },

        // Sticky Filter (localStorage)
        STORAGE_KEY: 'thx_lam_filter_anbieter',
        initFilter() {
            try {
                const gespeichert = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
                Object.assign(this.filter, gespeichert);
            } catch (e) {}
            this.$watch('filter', (v) => {
                try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(v)); } catch (e) {}
            }, { deep: true });
        },
        filterZuruecksetzen() {
            try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) {}
            this.filter = { suche: '', rolle: '', beziehung: '', sort: 'name_asc', limit: 50, offset: 0 };
            this.laden();
        },

        // Inline-Edit
        editZelle: { id: null, feld: null },
        editWert: '',
        editLaeuft: false,

        // Bulk-Auswahl
        auswahl: new Set(),
        bulkAktion: '',
        bulkWert: '',
        bulkLaeuft: false,

        // Detail-Modal
        detailOffen: false,
        detail: null,

        // Rechtsklick-Menue
        ctxMenu: { offen: false, x: 0, y: 0, ziel: null },

        // Anlegen-/Bearbeiten-Drawer
        drawerOffen: false,
        drawer: {
            id: null, name: '', firma: '', rolle: 'betreiber',
            beziehungsstatus: 'neu', notizen: '',
            laeuft: false, flashFehler: null, fehler: {}
        },

        // Kontakt-Drawer
        kontaktDrawer: {
            offen: false, id: null,
            vorname: '', nachname: '', email: '', telefon: '', rolle: '',
            laeuft: false, flashFehler: null
        },

        async laden() {
            this.laedt = true;
            const params = new URLSearchParams();
            if (this.filter.suche) params.set('suche', this.filter.suche);
            if (this.filter.rolle) params.set('rolle', this.filter.rolle);
            if (this.filter.beziehung) params.set('beziehung', this.filter.beziehung);
            params.set('sort', this.filter.sort);
            params.set('limit', this.filter.limit);
            params.set('offset', this.filter.offset);
            try {
                const res = await fetch('/api/v1/lam/anbieter?' + params, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) {
                    this.rows = json.data.rows || json.data;
                    this.totalCount = json.data.total ?? this.rows.length;
                } else { this.rows = []; this.totalCount = 0; }
            } finally { this.laedt = false; }
        },

        // ─ Inline-Edit ────────────────────────────────────────────────
        istOffen(id, feld) { return this.editZelle.id === id && this.editZelle.feld === feld; },
        aktuelleRolle(a) {
            if (!a) return '';
            if (a.ist_betreiber && a.ist_vermittler) return 'beides';
            if (a.ist_vermittler) return 'vermittler';
            return 'betreiber';
        },
        rolleStyle(a) {
            const r = this.aktuelleRolle(a);
            if (r === 'beides') return 'background:#fef3c7;color:#92400e;border-color:#fcd34d;';
            if (r === 'vermittler') return 'background:var(--thoxan-100);color:var(--thoxan-700);border-color:var(--thoxan-200);';
            return 'background:var(--emerald-100);color:var(--emerald-800);border-color:var(--emerald-200);';
        },
        oeffneEdit(a, feld) {
            if (this.editLaeuft) return;
            this.editZelle = { id: a.id, feld };
            if (feld === 'rolle') this.editWert = this.aktuelleRolle(a);
            else this.editWert = a[feld] ?? '';
        },
        schliesseEdit() {
            this.editZelle = { id: null, feld: null };
            this.editWert = '';
        },
        async speichereInline(a, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/anbieter-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: a.id, feld, wert: this.editWert })
                });
                const json = await res.json();
                if (!json.success) { alert(json.message || 'Fehler'); return; }
                if (feld === 'rolle') {
                    a.ist_betreiber = (this.editWert === 'betreiber' || this.editWert === 'beides') ? 1 : 0;
                    a.ist_vermittler = (this.editWert === 'vermittler' || this.editWert === 'beides') ? 1 : 0;
                    if (a.ist_betreiber && a.ist_vermittler) a.rollen_label = 'Betreiber + Vermittler';
                    else if (a.ist_vermittler) a.rollen_label = 'Vermittler';
                    else a.rollen_label = 'Betreiber';
                } else {
                    a[feld] = this.editWert;
                }
                this.schliesseEdit();
            } finally { this.editLaeuft = false; }
        },

        // ─ Bulk-Auswahl ───────────────────────────────────────────────
        toggleAuswahl(id) {
            const neu = new Set(this.auswahl);
            if (neu.has(id)) neu.delete(id); else neu.add(id);
            this.auswahl = neu;
        },
        alleSichtbarGewaehlt() {
            return this.rows.length > 0 && this.rows.every(r => this.auswahl.has(r.id));
        },
        toggleAlleSichtbar() {
            const neu = new Set(this.auswahl);
            if (this.alleSichtbarGewaehlt()) this.rows.forEach(r => neu.delete(r.id));
            else this.rows.forEach(r => neu.add(r.id));
            this.auswahl = neu;
        },
        auswahlLeeren() {
            this.auswahl = new Set();
            this.bulkAktion = ''; this.bulkWert = '';
        },
        async bulkAusfuehren() {
            if (this.bulkLaeuft || !this.bulkAktion || this.auswahl.size === 0) return;
            if (this.bulkAktion !== 'loeschen' && !this.bulkWert) return;
            if (this.bulkAktion === 'loeschen' && !confirm(`${this.auswahl.size} Anbieter wirklich löschen?`)) return;
            this.bulkLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/anbieter-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: this.bulkAktion, wert: this.bulkWert || null })
                });
                const json = await res.json();
                if (json.success) { this.auswahlLeeren(); await this.laden(); }
            } finally { this.bulkLaeuft = false; }
        },

        // ─ Detail-Modal ────────────────────────────────────────────────
        async oeffneDetail(a) {
            if (!a) return;
            this.detailOffen = true;
            this.detail = { id: a.id, name: a.name, rollen_label: a.rollen_label, firma: a.firma,
                            ist_betreiber: a.ist_betreiber, ist_vermittler: a.ist_vermittler,
                            beziehungsstatus: a.beziehungsstatus, notizen: a.notizen, kontakte: [], domains: [] };
            try {
                const res = await fetch('/api/v1/lam/anbieter-detail?id=' + encodeURIComponent(a.id), { credentials: 'same-origin' });
                const json = await res.json();
                if (json.success) this.detail = json.data;
            } catch (e) {}
        },
        schliesseDetail() { this.detailOffen = false; this.detail = null; },

        // ─ Rechtsklick-Kontextmenue ────────────────────────────────────
        oeffneCtxMenu(event, ziel) {
            const x = event.clientX, y = event.clientY;
            // Verhindern dass das Menue rechts/unten aus dem Viewport ragt
            const menuBreite = 220, menuHoehe = 380;
            const px = (x + menuBreite > window.innerWidth) ? x - menuBreite : x;
            const py = (y + menuHoehe > window.innerHeight) ? y - menuHoehe : y;
            this.ctxMenu = { offen: true, x: px, y: py, ziel };
        },
        async schnellAktion(ziel, feld, wert) {
            if (!ziel) return;
            try {
                const res = await fetch('/api/v1/lam/anbieter-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld, wert })
                });
                const json = await res.json();
                if (json.success) ziel[feld] = wert;
            } catch (e) {}
        },
        async loescheAnbieter(ziel) {
            if (!ziel) return;
            if (!confirm(`"${ziel.name}" wirklich löschen?`)) return;
            try {
                const res = await fetch('/api/v1/lam/anbieter-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [ziel.id], aktion: 'loeschen' })
                });
                const json = await res.json();
                if (json.success) await this.laden();
            } catch (e) {}
        },

        // ─ Anlegen-/Bearbeiten-Drawer ─────────────────────────────────
        oeffneNeuDrawer() {
            this.drawer = { id: null, name: '', firma: '', rolle: 'betreiber',
                            beziehungsstatus: 'neu', notizen: '',
                            laeuft: false, flashFehler: null, fehler: {} };
            this.drawerOffen = true;
        },
        oeffneBearbeitenDrawer(a) {
            if (!a) return;
            this.drawer = {
                id: a.id, name: a.name, firma: a.firma || '',
                rolle: this.aktuelleRolle(a),
                beziehungsstatus: a.beziehungsstatus || 'neu',
                notizen: a.notizen || '',
                laeuft: false, flashFehler: null, fehler: {}
            };
            this.drawerOffen = true;
        },
        schliesseDrawer() { this.drawerOffen = false; },
        // ─ Kontakt-CRUD ────────────────────────────────────────────────
        oeffneKontaktDrawer(k) {
            this.kontaktDrawer = {
                offen: true,
                id: k?.id || null,
                vorname: k?.vorname || '',
                nachname: k?.nachname || '',
                email: k?.email || '',
                telefon: k?.telefon || '',
                rolle: k?.rolle || '',
                laeuft: false, flashFehler: null
            };
        },
        schliesseKontaktDrawer() { this.kontaktDrawer.offen = false; },
        async speichereKontaktDrawer() {
            if (this.kontaktDrawer.laeuft) return;
            if (!this.kontaktDrawer.nachname.trim()) {
                this.kontaktDrawer.flashFehler = 'Nachname ist erforderlich';
                return;
            }
            this.kontaktDrawer.laeuft = true;
            try {
                const res = await fetch('/api/v1/lam/kontakt-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.kontaktDrawer.id,
                        anbieter_id: this.detail?.id,
                        vorname: this.kontaktDrawer.vorname,
                        nachname: this.kontaktDrawer.nachname,
                        email: this.kontaktDrawer.email,
                        telefon: this.kontaktDrawer.telefon,
                        rolle: this.kontaktDrawer.rolle
                    })
                });
                const json = await res.json();
                if (!json.success) {
                    this.kontaktDrawer.flashFehler = json.message || 'Fehler';
                    return;
                }
                this.kontaktDrawer.offen = false;
                // Detail neu laden, damit Kontakte aktualisiert sind
                if (this.detail?.id) await this.oeffneDetail({ id: this.detail.id, name: this.detail.name });
            } finally {
                this.kontaktDrawer.laeuft = false;
            }
        },
        async setzeKontaktPrimaer(k) {
            await fetch('/api/v1/lam/kontakt-aktion', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: k.id, aktion: 'primaer_setzen' })
            });
            if (this.detail?.id) await this.oeffneDetail({ id: this.detail.id, name: this.detail.name });
        },
        async loescheKontakt(k) {
            if (!confirm(`Kontakt "${k.name || k.nachname}" wirklich löschen?`)) return;
            await fetch('/api/v1/lam/kontakt-aktion', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: k.id, aktion: 'loeschen' })
            });
            if (this.detail?.id) await this.oeffneDetail({ id: this.detail.id, name: this.detail.name });
        },

        async speichereDrawer() {
            if (this.drawer.laeuft) return;
            this.drawer.fehler = {};
            this.drawer.flashFehler = null;
            if (!this.drawer.name.trim()) {
                this.drawer.fehler.name = 'Name ist erforderlich';
                return;
            }
            this.drawer.laeuft = true;
            try {
                const body = {
                    id: this.drawer.id,
                    name: this.drawer.name,
                    firma: this.drawer.firma,
                    rolle: this.drawer.rolle,
                    beziehungsstatus: this.drawer.beziehungsstatus,
                    notizen: this.drawer.notizen,
                };
                let res = await fetch('/api/v1/lam/anbieter-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                let json = await res.json();
                // Dubletten-Erkennung: API liefert spezifische Meldung zurück
                if (!json.success && json.message && json.message.indexOf('existiert vermutlich schon') !== -1) {
                    if (confirm(json.message + '\n\nTrotzdem als neuen Anbieter anlegen?')) {
                        body.dublette_ignorieren = 1;
                        res = await fetch('/api/v1/lam/anbieter-save', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(body),
                        });
                        json = await res.json();
                    } else {
                        this.drawer.flashFehler = 'Anlegen abgebrochen.';
                        return;
                    }
                }
                if (!json.success) {
                    this.drawer.flashFehler = json.message || 'Fehler';
                    return;
                }
                this.drawerOffen = false;
                await this.laden();
            } catch (e) {
                this.drawer.flashFehler = 'Netzwerkfehler';
            } finally {
                this.drawer.laeuft = false;
            }
        }
    };
}
</script>
