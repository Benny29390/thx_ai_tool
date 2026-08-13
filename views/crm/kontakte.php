<?php $activeModul = 'kontakte'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="thx-page-header" style="margin-bottom:8px;">
    <div>
        <h1 class="thx-page-title">Kontakte</h1>
        <div class="thx-page-subtitle">Filter links · Tabelle rechts · Rechtsklick = Schnellaktionen</div>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<div x-data="crmKontakteListe()" x-init="initial()" x-cloak class="crm-liste-root" @keydown.escape.window="ctxMenu.offen = false; bulkMenu = null">

    <!-- ═══════════ 2-SPALTEN-LAYOUT ═══════════ -->
    <div class="thx-shell">

        <!-- ─── LINKE FILTER-SIDEBAR ─── -->
        <aside class="thx-shell-side">
            <div class="thx-shell-side-header">
                <span class="thx-shell-side-title">Filter</span>
                <button x-show="hatAktiveFilter()" @click="filterReset()" class="thx-icon-btn" title="Filter zurücksetzen">
                    <span class="material-symbols-rounded">filter_alt_off</span>
                </button>
            </div>

            <div class="thx-shell-side-search">
                <span class="material-symbols-rounded thx-shell-search-icon">search</span>
                <input type="text" class="thx-shell-search-input" x-model.debounce.350ms="filter.suche" placeholder="Name, E-Mail, Funktion …">
            </div>

            <div class="thx-shell-side-content">

                <!-- Segment -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">bookmark</span>Segment</div>
                    <select class="thx-shell-select" x-model="aktivesSegment" @change="ladeSegment()">
                        <option value="">— keines —</option>
                        <template x-for="s in segmente" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                    <div class="thx-shell-row" style="margin-top:6px;">
                        <button class="thx-shell-btn" @click="oeffneSegmentSpeichern()" :disabled="!hatAktiveFilter()" style="flex:1;">Aktuelle Filter speichern</button>
                        <button x-show="aktivesSegment" class="thx-shell-btn thx-shell-btn-danger" @click="loescheSegment()" title="Segment löschen">×</button>
                    </div>
                </div>

                <!-- Status -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">flag</span>Status</div>
                    <div class="thx-shell-chips">
                        <template x-for="s in statusOpts" :key="s.value">
                            <button type="button" class="thx-shell-chip" :class="filter.kontakt_status.includes(s.value) ? 'is-active' : ''"
                                    @click="toggleMulti('kontakt_status', s.value, $event)" x-text="s.label"></button>
                        </template>
                    </div>
                </div>

                <!-- Opt-In -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">mark_email_read</span>Opt-In</div>
                    <div class="thx-shell-chips">
                        <template x-for="o in optInOpts" :key="o.value">
                            <button type="button" class="thx-shell-chip" :class="filter.opt_in_status.includes(o.value) ? 'is-active' : ''"
                                    @click="toggleMulti('opt_in_status', o.value, $event)" x-text="o.label"></button>
                        </template>
                    </div>
                </div>

                <!-- Tags -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label" style="justify-content:space-between;">
                        <span style="display:inline-flex;align-items:center;gap:5px;"><span class="material-symbols-rounded">sell</span>Tags</span>
                        <span style="font-size:0.66rem;text-transform:none;letter-spacing:0;font-weight:400;color:var(--slate-400);">
                            <label style="cursor:pointer;"><input type="radio" name="tag_modus" value="oder" x-model="filter.tag_modus" @change="laden(true)" style="margin:0 2px 0 0;vertical-align:middle;">ODER</label>
                            <label style="cursor:pointer;margin-left:6px;"><input type="radio" name="tag_modus" value="und" x-model="filter.tag_modus" @change="laden(true)" style="margin:0 2px 0 0;vertical-align:middle;">UND</label>
                        </span>
                    </div>
                    <div style="font-size:0.7rem;color:var(--slate-400);margin-bottom:6px;">Shift+Klick = NICHT enthalten</div>
                    <div class="thx-shell-chips thx-shell-chips-scroll">
                        <template x-for="t in tagsAlle" :key="t.id">
                            <button type="button" class="thx-shell-chip"
                                    :class="filter.tag_ids.includes(t.id) ? 'is-active' : (filter.ohne_tag_ids.includes(t.id) ? 'is-negated' : '')"
                                    @click="toggleTag(t.id, $event)">
                                <span x-show="filter.ohne_tag_ids.includes(t.id)" style="color:var(--rose-700);font-weight:700;margin-right:2px;">¬</span>
                                <span x-text="t.name"></span>
                                <span class="thx-shell-chip-count" x-text="t.anzahl_kontakte"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Listen -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">format_list_bulleted</span>Listen</div>
                    <div class="thx-shell-chips thx-shell-chips-scroll">
                        <template x-for="l in listenAlle" :key="l.id">
                            <button type="button" class="thx-shell-chip" :class="filter.listen_ids.includes(l.id) ? 'is-active' : ''"
                                    @click="toggleMulti('listen_ids', l.id, $event)">
                                <span x-text="l.name"></span>
                                <span class="thx-shell-chip-count" x-text="l.anzahl_aktive || 0"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Sonderfilter -->
                <div class="thx-shell-group">
                    <div class="thx-shell-group-label"><span class="material-symbols-rounded">tune</span>Sonderfilter</div>
                    <div class="thx-shell-chips">
                        <button type="button" class="thx-shell-chip" :class="filter.ohne_firma ? 'is-active' : ''" @click="filter.ohne_firma = !filter.ohne_firma; laden(true)">ohne Firma</button>
                        <button type="button" class="thx-shell-chip" :class="filter.mit_foto ? 'is-active' : ''" @click="filter.mit_foto = !filter.mit_foto; laden(true)">mit Foto</button>
                        <button type="button" class="thx-shell-chip" :class="filter.mit_zoho_legacy ? 'is-active' : ''" @click="filter.mit_zoho_legacy = !filter.mit_zoho_legacy; laden(true)">aus Zoho</button>
                    </div>
                </div>

            </div>
        </aside>

        <!-- ─── RECHTE TABELLEN-SPALTE ─── -->
        <main class="thx-shell-main">

            <!-- Top-Leiste: Result-Count + Aktionen -->
            <div class="thx-shell-toolbar">
                <div style="font-size:0.85rem;color:var(--slate-600);">
                    <strong x-text="gesamt.toLocaleString('de-DE')"></strong> Kontakt(e)
                    <span x-show="eintraege.length > 0" style="color:var(--slate-400);">· <span x-text="(offset + 1) + '–' + Math.min(offset + eintraege.length, gesamt)"></span></span>
                    <span x-show="hatAktiveFilter()" style="color:var(--thoxan-600);margin-left:6px;display:inline-flex;align-items:center;gap:3px;"><span class="material-symbols-rounded" style="font-size:14px;">filter_alt</span>gefiltert</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <select x-model.number="limit" @change="laden(true)" class="thx-shell-select" style="font-size:0.78rem;padding:4px 8px;">
                        <option :value="25">25 / Seite</option>
                        <option :value="50">50 / Seite</option>
                        <option :value="100">100 / Seite</option>
                        <option :value="200">200 / Seite</option>
                    </select>
                    <div style="position:relative;" @click.outside="spaltenPopup = false">
                        <button class="thx-shell-btn" @click.stop="spaltenPopup = !spaltenPopup" title="Spalten anpassen" style="display:inline-flex;align-items:center;gap:4px;">
                            <span class="material-symbols-rounded" style="font-size:16px;">view_column</span>Spalten
                        </button>
                        <div x-show="spaltenPopup" x-cloak class="thx-shell-popup" @click.stop style="right:0;left:auto;min-width:200px;">
                            <div class="thx-shell-popup-title">Sichtbare Spalten</div>
                            <template x-for="s in spaltenKonfig" :key="s.key">
                                <label class="thx-shell-popup-item">
                                    <input type="checkbox" :checked="spaltenSichtbar[s.key]" @change="toggleSpalte(s.key)">
                                    <span x-text="s.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk-Toolbar -->
            <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
                <span class="thx-bulk-count"><strong x-text="auswahl.size"></strong> ausgewählt</span>
                <div style="position:relative;" @click.outside="bulkMenu = null">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click.stop="bulkMenu = bulkMenu === 'status' ? null : 'status'">Status ▾</button>
                    <div x-show="bulkMenu === 'status'" x-cloak class="crm-bulk-popup">
                        <template x-for="s in statusOpts" :key="s.value">
                            <button class="crm-bulk-popup-item" @click="bulkRun('status_setzen', s.value, 'Status: ' + s.label)" x-text="s.label"></button>
                        </template>
                    </div>
                </div>
                <div style="position:relative;" @click.outside="bulkMenu = null">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click.stop="bulkMenu = bulkMenu === 'optin' ? null : 'optin'">Opt-In ▾</button>
                    <div x-show="bulkMenu === 'optin'" x-cloak class="crm-bulk-popup">
                        <template x-for="o in optInOpts" :key="o.value">
                            <button class="crm-bulk-popup-item" @click="bulkRun('optin_setzen', o.value, 'Opt-In: ' + o.label)" x-text="o.label"></button>
                        </template>
                    </div>
                </div>
                <div style="position:relative;" @click.outside="bulkMenu = null">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click.stop="oeffneBulkMenu('tag')">Tag setzen ▾</button>
                    <div x-show="bulkMenu === 'tag'" x-cloak class="crm-bulk-popup" style="width:260px;" @click.stop>
                        <input type="text" x-model="bulkSuche" x-ref="bulkSearch" placeholder="Tag suchen …"
                               style="width:100%;padding:6px 10px;border:1px solid var(--slate-300);border-radius:5px;background:var(--slate-50);font-size:0.78rem;outline:none;margin-bottom:6px;">
                        <div style="max-height:240px;overflow-y:auto;">
                            <template x-for="t in bulkTagsGefiltert" :key="t.id">
                                <button class="crm-bulk-popup-item" @click="bulkRun('tag_setzen', t.id, 'Tag: ' + t.name)" x-text="t.name"></button>
                            </template>
                            <template x-if="bulkTagsGefiltert.length === 0">
                                <div style="padding:8px 10px;font-size:0.78rem;color:var(--slate-400);">Kein Treffer.</div>
                            </template>
                        </div>
                    </div>
                </div>
                <div style="position:relative;" @click.outside="bulkMenu = null">
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click.stop="oeffneBulkMenu('liste')">Liste ▾</button>
                    <div x-show="bulkMenu === 'liste'" x-cloak class="crm-bulk-popup" style="width:260px;" @click.stop>
                        <input type="text" x-model="bulkSuche" x-ref="bulkSearch" placeholder="Liste suchen …"
                               style="width:100%;padding:6px 10px;border:1px solid var(--slate-300);border-radius:5px;background:var(--slate-50);font-size:0.78rem;outline:none;margin-bottom:6px;">
                        <div style="max-height:240px;overflow-y:auto;">
                            <template x-for="l in bulkListenGefiltert" :key="l.id">
                                <button class="crm-bulk-popup-item" @click="bulkRun('liste_setzen', l.id, 'Liste: ' + l.name)" x-text="l.name"></button>
                            </template>
                            <template x-if="bulkListenGefiltert.length === 0">
                                <div style="padding:8px 10px;font-size:0.78rem;color:var(--slate-400);">Kein Treffer.</div>
                            </template>
                        </div>
                    </div>
                </div>
                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="bulkSoftDelete()" style="color:var(--rose-700);">Löschen</button>
                <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
            </div>

            <!-- Status-States -->
            <div x-show="laedt" style="padding:30px;text-align:center;color:var(--slate-400);">Lade …</div>
            <template x-if="!laedt && fehler"><div class="lam-flash lam-flash-fehler">Fehler: <span x-text="fehler"></span></div></template>
            <template x-if="!laedt && !fehler && eintraege.length === 0">
                <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-500);">
                    <p>Keine Kontakte gefunden.</p>
                    <p style="font-size:0.8rem;color:var(--slate-400);margin-top:6px;">Filter lockern oder zurücksetzen.</p>
                </div>
            </template>

            <!-- ─── TABELLE ─── -->
            <template x-if="!laedt && !fehler && eintraege.length > 0">
                <div class="thx-shell-table-wrap">
                    <table class="thx-shell-table" style="table-layout:fixed;width:100%;">
                        <colgroup>
                            <col style="width:42px;">
                            <template x-for="s in sichtbareSpalten" :key="'col-' + s.key">
                                <col :style="'width:' + (s.width || 'auto') + (s.minWidth ? ';min-width:' + s.minWidth : '')">
                            </template>
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="thx-bulk-col">
                                    <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                                </th>
                                <template x-for="s in sichtbareSpalten" :key="s.key">
                                    <th :class="[s.center ? 'center' : '', s.sortable ? 'sortable ' + sortKlasse(s.sort) : '']" @click="s.sortable ? sortBy(s.sort) : null">
                                        <span x-text="s.label"></span>
                                        <span x-show="s.sortable" class="sort-icon" x-text="sortPfeil(s.sort)"></span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="k in eintraege" :key="k.id">
                                <tr :class="auswahl.has(k.id) ? 'is-bulk-selected' : ''" @contextmenu.prevent="oeffneCtxMenu($event, k)">
                                    <td class="thx-bulk-col" @click.stop>
                                        <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(k.id)" @change="toggleAuswahl(k.id)">
                                    </td>
                                    <!-- Name -->
                                    <td x-show="spaltenSichtbar.name" class="thx-row-clickable" @click="oeffneDetail(k.id)">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <template x-if="k.foto_path"><img :src="k.foto_path" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;align-self:flex-start;margin-top:1px;"></template>
                                            <template x-if="!k.foto_path"><span style="width:26px;height:26px;border-radius:50%;background:var(--slate-100);color:var(--slate-600);display:inline-flex;align-items:center;justify-content:center;font-size:0.62rem;font-weight:600;flex-shrink:0;align-self:flex-start;margin-top:1px;" x-text="((k.vorname||'?')[0]||'') + ((k.nachname||'?')[0]||'')"></span></template>
                                            <div style="min-width:0;">
                                                <div style="font-weight:500;color:var(--slate-900);line-height:1.3;" x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></div>
                                                <div x-show="k.titel || k.funktion" style="font-size:0.7rem;color:var(--slate-500);margin-top:2px;" x-text="[k.titel, k.funktion].filter(v=>v).join(' · ')"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- E-Mail -->
                                    <td x-show="spaltenSichtbar.email">
                                        <a x-show="k.email_primaer" :href="'mailto:' + k.email_primaer" @click.stop style="color:var(--thoxan-600);" x-text="k.email_primaer"></a>
                                        <span x-show="!k.email_primaer" style="color:var(--slate-300);">—</span>
                                    </td>
                                    <!-- Firma -->
                                    <td x-show="spaltenSichtbar.firma">
                                        <template x-if="k.firmenname"><a :href="'/crm/firmen/' + k.firma_id" @click.stop x-text="k.firmenname"></a></template>
                                        <template x-if="!k.firmenname"><span style="color:var(--slate-300);">—</span></template>
                                    </td>
                                    <!-- Funktion -->
                                    <td x-show="spaltenSichtbar.funktion" x-text="k.funktion || '—'" :style="!k.funktion ? 'color:var(--slate-300);' : 'color:var(--slate-600);'"></td>
                                    <!-- Telefon -->
                                    <td x-show="spaltenSichtbar.telefon" style="font-size:0.78rem;">
                                        <a x-show="k.mobil" :href="'tel:' + k.mobil" @click.stop style="color:var(--slate-700);display:block;" x-text="k.mobil"></a>
                                        <a x-show="!k.mobil && k.telefon" :href="'tel:' + k.telefon" @click.stop style="color:var(--slate-700);display:block;" x-text="k.telefon"></a>
                                        <span x-show="!k.mobil && !k.telefon" style="color:var(--slate-300);">—</span>
                                    </td>
                                    <!-- Status (Inline-Edit) -->
                                    <td x-show="spaltenSichtbar.status" class="center">
                                        <template x-if="!istOffen(k.id, 'kontakt_status')">
                                            <button class="thx-inline-edit" :class="!k.kontakt_status ? 'is-empty' : ''" @click.stop="oeffneInline(k, 'kontakt_status')"
                                                    x-text="k.kontakt_status ? formatStatus(k.kontakt_status) : '— setzen'"></button>
                                        </template>
                                        <template x-if="istOffen(k.id, 'kontakt_status')">
                                            <div class="thx-inline-edit-frame" @click.stop @keydown.escape="schliesseInline()">
                                                <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()" @change="speichereInline(k, 'kontakt_status')">
                                                    <option value="">— leeren —</option>
                                                    <template x-for="s in statusOpts" :key="s.value">
                                                        <option :value="s.value" x-text="s.label"></option>
                                                    </template>
                                                </select>
                                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseInline()">×</button>
                                            </div>
                                        </template>
                                    </td>
                                    <!-- Opt-In (Inline-Edit) -->
                                    <td x-show="spaltenSichtbar.optin" class="center">
                                        <template x-if="!istOffen(k.id, 'opt_in_status')">
                                            <button class="thx-inline-edit" :class="!k.opt_in_status ? 'is-empty' : ''" @click.stop="oeffneInline(k, 'opt_in_status')">
                                                <template x-if="k.opt_in_status === 'double_opted_in'"><span class="lam-chip lam-chip-status-geprueft" title="Double Opt-In">DOI</span></template>
                                                <template x-if="k.opt_in_status === 'unsubscribed'"><span class="lam-chip lam-chip-status-geloescht" title="Abgemeldet">Aus</span></template>
                                                <template x-if="k.opt_in_status === 'hard_bounce'"><span class="lam-chip lam-chip-status-geloescht" title="Hard Bounce">!HB</span></template>
                                                <template x-if="k.opt_in_status === 'single_opted_in'"><span class="lam-chip">SOI</span></template>
                                                <template x-if="k.opt_in_status === 'pending'"><span class="lam-chip" style="color:var(--slate-500);">Pend</span></template>
                                                <template x-if="!k.opt_in_status || k.opt_in_status === 'invalid'"><span style="color:var(--slate-400);">— setzen</span></template>
                                            </button>
                                        </template>
                                        <template x-if="istOffen(k.id, 'opt_in_status')">
                                            <div class="thx-inline-edit-frame" @click.stop @keydown.escape="schliesseInline()">
                                                <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()" @change="speichereInline(k, 'opt_in_status')">
                                                    <option value="">— leeren —</option>
                                                    <template x-for="o in optInOpts" :key="o.value">
                                                        <option :value="o.value" x-text="o.label"></option>
                                                    </template>
                                                </select>
                                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseInline()">×</button>
                                            </div>
                                        </template>
                                    </td>
                                    <!-- Tags -->
                                    <td x-show="spaltenSichtbar.tags">
                                        <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                            <template x-for="tag in (k.tags||[]).slice(0, 4)" :key="tag">
                                                <span class="lam-chip" style="font-size:0.66rem;padding:1px 7px;" x-text="tag"></span>
                                            </template>
                                            <span x-show="(k.tags||[]).length > 4" style="font-size:0.65rem;color:var(--slate-400);align-self:center;" x-text="'+' + ((k.tags||[]).length - 4)"></span>
                                        </div>
                                    </td>
                                    <!-- Score (Inline-Edit) -->
                                    <td x-show="spaltenSichtbar.score" class="center" style="font-variant-numeric:tabular-nums;">
                                        <template x-if="!istOffen(k.id, 'thx_score')">
                                            <button class="thx-inline-edit" :class="!k.thx_score ? 'is-empty' : ''" @click.stop="oeffneInline(k, 'thx_score')" x-text="k.thx_score || '—'"></button>
                                        </template>
                                        <template x-if="istOffen(k.id, 'thx_score')">
                                            <div class="thx-inline-edit-frame" @click.stop @keydown.escape="schliesseInline()">
                                                <input type="number" min="0" max="100" class="thx-inline-edit-input" x-model="editWert" x-init="$el.focus()" @keydown.enter="speichereInline(k, 'thx_score')" style="width:60px;">
                                                <button class="thx-btn thx-btn-primary thx-btn-small" @click="speichereInline(k, 'thx_score')">✓</button>
                                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseInline()">×</button>
                                            </div>
                                        </template>
                                    </td>
                                    <!-- Listen -->
                                    <td x-show="spaltenSichtbar.listen" style="font-size:0.78rem;color:var(--slate-600);">
                                        <span x-show="k.anzahl_listen > 0" x-text="k.anzahl_listen + ' Liste(n)'"></span>
                                        <span x-show="!k.anzahl_listen" style="color:var(--slate-300);">—</span>
                                    </td>
                                    <!-- Erstellt -->
                                    <td x-show="spaltenSichtbar.erstellt" style="color:var(--slate-500);font-size:0.78rem;font-variant-numeric:tabular-nums;" x-text="formatDate(k.erstellt_am)"></td>
                                    <!-- Geändert -->
                                    <td x-show="spaltenSichtbar.geaendert" style="color:var(--slate-500);font-size:0.78rem;font-variant-numeric:tabular-nums;" x-text="formatDate(k.geaendert_am || k.erstellt_am)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Pagination -->
            <div x-show="!laedt && gesamt > limit" style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;color:var(--slate-500);">
                <button class="thx-btn thx-btn-secondary thx-btn-small" :disabled="offset === 0" @click="seitenZurueck()">‹ Zurück</button>
                <span>Seite <strong x-text="Math.floor(offset/limit) + 1"></strong> von <strong x-text="Math.ceil(gesamt/limit)"></strong></span>
                <button class="thx-btn thx-btn-secondary thx-btn-small" :disabled="(offset + limit) >= gesamt" @click="seitenVor()">Weiter ›</button>
            </div>
        </main>
    </div>

    <!-- ─── DETAIL-DRAWER: zeigt die Vollansicht der Detail-Seite per iframe
         im embed-Modus (versteckt Topbar/Sidebar). Garantiert 100% Konsistenz
         mit der Detail-Seite, ohne Markup-Duplizierung. ─── -->
    <div x-show="drawer.offen" x-cloak class="crm-drawer-backdrop" @click="drawer.offen = false"></div>
    <aside x-show="drawer.offen" x-cloak class="crm-drawer crm-drawer-iframe-mode" @click.stop>
        <header class="crm-drawer-header">
            <button class="thx-icon-btn" @click="drawer.offen = false" title="Schließen">
                <span class="material-symbols-rounded">close</span>
            </button>
            <!-- Foto -->
            <template x-if="drawer.k && drawer.k.foto_path">
                <img :src="drawer.k.foto_path" class="crm-drawer-avatar" :alt="(drawer.k.vorname||'') + ' ' + (drawer.k.nachname||'')">
            </template>
            <template x-if="drawer.k && !drawer.k.foto_path">
                <span class="crm-drawer-avatar crm-drawer-avatar-fallback" x-text="((drawer.k.vorname||'?')[0]||'') + ((drawer.k.nachname||'?')[0]||'')"></span>
            </template>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:var(--slate-900);font-size:0.95rem;" x-text="drawer.k ? ((drawer.k.vorname||'') + ' ' + (drawer.k.nachname||'')) : 'Lade …'"></div>
                <div style="font-size:0.78rem;color:var(--slate-500);" x-text="drawer.k?.firmenname || ''"></div>
            </div>
            <!-- Quick-Aktionen rechts -->
            <a x-show="drawer.k?.email_primaer" :href="'mailto:' + drawer.k?.email_primaer" class="thx-icon-btn" title="E-Mail schreiben">
                <span class="material-symbols-rounded">mail</span>
            </a>
            <a x-show="drawer.k?.mobil" :href="'tel:' + drawer.k?.mobil" class="thx-icon-btn" title="Mobil anrufen">
                <span class="material-symbols-rounded">smartphone</span>
            </a>
            <a x-show="drawer.k?.telefon && !drawer.k?.mobil" :href="'tel:' + drawer.k?.telefon" class="thx-icon-btn" title="Tel. anrufen">
                <span class="material-symbols-rounded">call</span>
            </a>
            <a x-show="drawer.k?.website" :href="drawer.k?.website" target="_blank" rel="noopener" class="thx-icon-btn" title="Website öffnen">
                <span class="material-symbols-rounded">language</span>
            </a>
            <button class="thx-icon-btn" :class="drawerEditMode ? 'is-active-edit' : ''"
                    @click="toggleDrawerEdit()"
                    :title="drawerEditMode ? 'Bearbeitung aus' : 'Bearbeitung ein'">
                <span class="material-symbols-rounded" x-text="drawerEditMode ? 'edit_off' : 'edit'"></span>
            </button>
            <a :href="drawer.k ? ('/crm/kontakte/' + drawer.k.id) : '#'" class="thx-icon-btn" title="In voller Seite öffnen">
                <span class="material-symbols-rounded">open_in_full</span>
            </a>
        </header>
        <iframe x-show="drawer.k" id="crm-drawer-iframe"
                :src="drawer.k ? ('/crm/kontakte/' + drawer.k.id + '?embed=1&drawer=1') : 'about:blank'"
                class="crm-drawer-iframe" frameborder="0" loading="lazy"></iframe>
        <div x-show="!drawer.k" style="padding:30px;text-align:center;color:var(--slate-400);">Lade …</div>
    </aside>

    <!-- ─── RECHTSKLICK-MENÜ ─── -->
    <div x-show="ctxMenu.offen" x-cloak class="thx-contextmenu" :style="'top:' + ctxMenu.y + 'px;left:' + ctxMenu.x + 'px;'"
         @click.outside="ctxMenu.offen = false" @click.stop>
        <div class="thx-contextmenu-label" x-text="(ctxMenu.kontakt?.vorname || '') + ' ' + (ctxMenu.kontakt?.nachname || '')"></div>
        <div class="thx-contextmenu-divider"></div>

        <a class="thx-contextmenu-item" :href="ctxMenu.kontakt ? ('/crm/kontakte/' + ctxMenu.kontakt.id) : '#'" @click="ctxMenu.offen = false">📂 Detail öffnen</a>
        <a x-show="ctxMenu.kontakt?.email_primaer" class="thx-contextmenu-item" :href="'mailto:' + ctxMenu.kontakt?.email_primaer" @click="ctxMenu.offen = false">✉ E-Mail schreiben</a>
        <a x-show="ctxMenu.kontakt?.mobil" class="thx-contextmenu-item" :href="'tel:' + ctxMenu.kontakt?.mobil" @click="ctxMenu.offen = false">📞 Mobil anrufen</a>
        <a x-show="ctxMenu.kontakt?.telefon && !ctxMenu.kontakt?.mobil" class="thx-contextmenu-item" :href="'tel:' + ctxMenu.kontakt?.telefon" @click="ctxMenu.offen = false">📞 Festnetz anrufen</a>

        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Status setzen</div>
        <template x-for="s in statusOpts" :key="s.value">
            <button class="thx-contextmenu-item" @click="schnellStatus(ctxMenu.kontakt, s.value); ctxMenu.offen = false" x-text="s.label"></button>
        </template>

        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item" @click="toggleAuswahl(ctxMenu.kontakt.id); ctxMenu.offen = false">☑ Zur Auswahl hinzufügen</button>
        <button class="thx-contextmenu-item is-danger" @click="einzelDelete(ctxMenu.kontakt); ctxMenu.offen = false">🗑 Löschen</button>
    </div>

    <!-- ─── SEGMENT-DIALOG ─── -->
    <div x-show="segDialog.offen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;" @click.self="segDialog.offen = false">
        <div class="thx-modal" style="max-width:480px;">
            <div style="padding:14px 22px;border-bottom:1px solid var(--slate-200);"><h3 style="margin:0;font-size:1rem;">Als Segment speichern</h3></div>
            <div style="padding:14px 22px;">
                <label class="thx-shell-group-label">Name</label>
                <input type="text" class="lam-filter-input" x-model="segDialog.name" placeholder='z.B. „Wunschkunden ohne Mail"' x-init="$nextTick(() => $el.focus())">
                <label class="thx-shell-group-label" style="margin-top:10px;">Sichtbarkeit</label>
                <select class="lam-filter-select" x-model="segDialog.sichtbarkeit">
                    <option value="privat">Privat (nur Du)</option>
                    <option value="team">Team</option>
                    <option value="global">Global (alle)</option>
                </select>
                <div style="margin-top:12px;font-size:0.78rem;color:var(--slate-500);">
                    <strong>Aktive Filter:</strong>
                    <pre style="margin:6px 0 0 0;padding:8px;background:var(--slate-100);border-radius:4px;font-size:0.7rem;overflow:auto;max-height:120px;" x-text="JSON.stringify(filterAlsJson(), null, 2)"></pre>
                </div>
            </div>
            <div style="padding:10px 22px;border-top:1px solid var(--slate-200);display:flex;justify-content:flex-end;gap:6px;">
                <button class="thx-btn thx-btn-secondary" @click="segDialog.offen = false">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" @click="speichereSegment()" :disabled="!segDialog.name.trim()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<style>
/* CRM-Kontakte-spezifische Stile (Layout-Klassen .thx-shell-* sind global in thx-components.css) */
.crm-liste-root { padding-bottom: 30px; }
.thx-row-clickable { cursor: pointer; }

/* Detail-Drawer (rechte Seite) — Inhalt nutzt die geteilten .crm-detail-* Klassen aus thx-components.css */
.crm-drawer-backdrop {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.4);
    z-index: 1099;
    backdrop-filter: blur(2px);
}
.crm-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 50%; min-width: 720px; max-width: 1100px;
    background: var(--slate-50);
    border-left: 1px solid var(--slate-200);
    box-shadow: -8px 0 24px rgba(0,0,0,0.08);
    z-index: 1100;
    display: flex; flex-direction: column;
    overflow: hidden;
    animation: drawerIn 0.18s ease-out;
}
@keyframes drawerIn { from { transform: translateX(20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.crm-drawer-header {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
    flex-shrink: 0;
}
.crm-drawer-body { overflow-y: auto; flex: 1; }
@media (max-width: 1100px) {
    .crm-drawer { width: 100%; min-width: 0; max-width: none; }
}
/* iframe-Modus: Drawer-Inhalt ist die Detail-Seite per ?embed=1 */
.crm-drawer-iframe-mode { padding: 0; }
.crm-drawer-iframe {
    flex: 1;
    width: 100%;
    border: 0;
    background: var(--slate-50);
}
/* Foto im Drawer-Header (links neben Name+Firma) */
.crm-drawer-avatar {
    width: 50px; height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--slate-200);
    flex-shrink: 0;
}
.crm-drawer-avatar-fallback {
    background: var(--thoxan-100); color: var(--thoxan-700);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.05rem; font-weight: 600;
}
/* Drawer-Header-Button im Edit-Modus = amber-Akzent */
.crm-drawer-header .thx-icon-btn.is-active-edit {
    background: var(--amber-100);
    color: var(--amber-800);
    border: 1px solid var(--amber-300);
}
.crm-drawer-header .thx-icon-btn.is-active-edit:hover {
    background: var(--amber-200);
}

/* Inline-Edit */
.thx-inline-edit { background:none; border:1px dashed transparent; padding:2px 6px; border-radius:3px; cursor:pointer; font:inherit; color:inherit; }
.thx-inline-edit:hover { border-color:var(--thoxan-300); background:var(--thoxan-50); }
.thx-inline-edit.is-empty { color:var(--slate-400); font-style:italic; }
.thx-inline-edit-frame { display:inline-flex; gap:3px; align-items:center; }
.thx-inline-edit-input, .thx-inline-edit-select { padding:2px 6px; border:1px solid var(--thoxan-400); border-radius:4px; font-size:0.85rem; font-family:inherit; }

/* Bulk-Popup-Menüs */
.crm-bulk-popup { position:absolute; top:100%; left:0; margin-top:3px; background:#fff; border:1px solid var(--slate-300); border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,0.12); padding:4px; z-index:200; min-width:180px; }
.crm-bulk-popup-item { display:block; width:100%; text-align:left; padding:5px 10px; background:none; border:0; cursor:pointer; font:inherit; font-size:0.82rem; color:inherit; border-radius:4px; }
.crm-bulk-popup-item:hover { background:var(--thoxan-50); }

/* Contextmenu */
.thx-contextmenu { position:fixed; background:#fff; border:1px solid var(--slate-300); border-radius:6px; box-shadow:0 8px 22px rgba(0,0,0,0.15); padding:4px; z-index:1100; min-width:200px; }
.thx-contextmenu-item { display:block; width:100%; text-align:left; padding:5px 10px; background:none; border:0; cursor:pointer; font:inherit; font-size:0.82rem; color:inherit; border-radius:4px; text-decoration:none; }
.thx-contextmenu-item:hover { background:var(--thoxan-50); }
.thx-contextmenu-item.is-danger { color:var(--rose-700); }
.thx-contextmenu-item.is-danger:hover { background:var(--rose-50); }
.thx-contextmenu-label { padding:5px 10px; font-size:0.7rem; color:var(--slate-500); text-transform:uppercase; letter-spacing:0.04em; font-weight:600; }
.thx-contextmenu-divider { border-top:1px solid var(--slate-200); margin:3px 0; }
</style>

<script>
function crmKontakteListe() {
    return {
        // ─── Core State ───
        laedt: false, fehler: null, eintraege: [], gesamt: 0,
        limit: 50, offset: 0, sort: 'name', order: 'asc',
        auswahl: new Set(),
        bulkMenu: null,
        bulkSuche: '',
        spaltenPopup: false,

        // ─── Filter ───
        filter: {
            suche: '',
            kontakt_status: [], opt_in_status: [],
            tag_ids: [], ohne_tag_ids: [], tag_modus: 'oder',
            listen_ids: [],
            ohne_firma: false, mit_foto: false, mit_zoho_legacy: false,
        },

        // ─── Options ───
        statusOpts: [
            {value:'lead',label:'Lead'},{value:'interessent',label:'Interessent'},{value:'kunde',label:'Kunde'},
            {value:'ehemaliger_kunde',label:'Ehemalig'},{value:'partner',label:'Partner'},
            {value:'wunschkunde',label:'Wunschkunde'},{value:'dienstleister',label:'Dienstleister'},{value:'sonstiges',label:'Sonstiges'},
        ],
        optInOpts: [
            {value:'pending',label:'Pending'},{value:'single_opted_in',label:'Single Opt-In'},
            {value:'double_opted_in',label:'Double Opt-In'},{value:'unsubscribed',label:'Abgemeldet'},
            {value:'hard_bounce',label:'Hard Bounce'},{value:'invalid',label:'Invalid'},
        ],

        // ─── Spalten-Konfig (Reihenfolge = Tabelle) ───
        spaltenKonfig: [
            { key:'name',      label:'Name',     sortable:true, sort:'name',         width:'24%', minWidth:'220px' },
            { key:'email',     label:'E-Mail',   sortable:true, sort:'email',        width:'22%', minWidth:'200px' },
            { key:'firma',     label:'Firma',    sortable:true, sort:'firma',        width:'18%', minWidth:'180px' },
            { key:'funktion',  label:'Funktion', sortable:false,                     width:'14%', minWidth:'140px' },
            { key:'telefon',   label:'Telefon',  sortable:false,                     width:'130px' },
            { key:'status',    label:'Status',   sortable:false, center:true,        width:'110px' },
            { key:'optin',     label:'Opt-In',   sortable:false, center:true,        width:'80px' },
            { key:'tags',      label:'Tags',     sortable:true, sort:'tags',         width:'18%', minWidth:'180px' },
            { key:'score',     label:'Score',    sortable:true, sort:'thx_score', center:true, width:'70px' },
            { key:'listen',    label:'Listen',   sortable:false,                     width:'100px' },
            { key:'erstellt',  label:'Angelegt', sortable:true, sort:'erstellt_am',  width:'90px' },
            { key:'geaendert', label:'Geändert', sortable:true, sort:'geaendert_am', width:'90px' },
        ],
        spaltenSichtbar: {
            name:true, email:true, firma:true, tags:true, geaendert:true,
            // Default-aus (über Spalten-Popup einblendbar):
            funktion:false, telefon:false, status:false, optin:false, score:false, listen:false, erstellt:false,
        },

        // Stammdaten
        tagsAlle: [], listenAlle: [], segmente: [], aktivesSegment: '',

        // Inline-Edit
        editFeld: null, editKontaktId: null, editWert: '',

        // Rechtsklick
        ctxMenu: { offen: false, x: 0, y: 0, kontakt: null },

        // Detail-Drawer + Inline-Edit-State (analog Detail-View)
        drawer: { offen: false, laedt: false, k: null },
        drawerEditMode: localStorage.getItem('crm_kontakt_edit_mode') === '1', // sticky kontaktübergreifend
        editMode: false, // (legacy state, weiter benutzt von speichern())
        editFeld: null, editTyp: null, editWert: '',

        // Segment-Dialog
        segDialog: { offen: false, name: '', sichtbarkeit: 'privat' },

        get sichtbareSpalten() {
            return this.spaltenKonfig.filter(s => this.spaltenSichtbar[s.key]);
        },
        // Bulk-Suche: alphabetisch sortiert, mit Live-Filter
        get bulkTagsGefiltert() {
            const s = (this.bulkSuche || '').toLowerCase();
            return this.tagsAlle.slice()
                .filter(t => !s || (t.name || '').toLowerCase().includes(s))
                .sort((a,b) => (a.name||'').localeCompare(b.name||'', 'de'));
        },
        get bulkListenGefiltert() {
            const s = (this.bulkSuche || '').toLowerCase();
            return this.listenAlle.slice()
                .filter(l => !s || (l.name || '').toLowerCase().includes(s))
                .sort((a,b) => (a.name||'').localeCompare(b.name||'', 'de'));
        },
        oeffneBulkMenu(typ) {
            const offen = this.bulkMenu === typ;
            this.bulkMenu = offen ? null : typ;
            this.bulkSuche = '';
            if (!offen && (typ === 'tag' || typ === 'liste')) {
                this.$nextTick(() => { if (this.$refs.bulkSearch) this.$refs.bulkSearch.focus(); });
            }
        },
        toggleSpalte(key) {
            this.spaltenSichtbar[key] = !this.spaltenSichtbar[key];
            localStorage.setItem('crm_spalten', JSON.stringify(this.spaltenSichtbar));
        },

        async initial() {
            // Spalten-Präferenz aus LocalStorage
            const sp = localStorage.getItem('crm_spalten');
            if (sp) {
                try { this.spaltenSichtbar = { ...this.spaltenSichtbar, ...JSON.parse(sp) }; } catch (e) {}
            }
            // Suche reagiert über $watch — debounce kümmert sich um Tastendruck-Throttling,
            // $watch greift erst wenn der Wert wirklich geändert wurde (kein stale @input mehr).
            this.$watch('filter.suche', () => this.laden(true));
            await Promise.all([this.ladeTagsListen(), this.ladeSegmente()]);
            this.laden();
        },
        async ladeTagsListen() {
            try {
                const [tr, lr] = await Promise.all([
                    fetch('/api/v1/crm/tags').then(r => r.json()),
                    fetch('/api/v1/crm/listen').then(r => r.json()),
                ]);
                if (tr.success) this.tagsAlle = (tr.data.tags || []).sort((a,b) => (a.name||'').localeCompare(b.name||'', 'de'));
                if (lr.success) this.listenAlle = (lr.data.listen || []).sort((a,b) => (a.name||'').localeCompare(b.name||'', 'de'));
            } catch (e) {}
        },
        async ladeSegmente() {
            try {
                const r = await fetch('/api/v1/crm/segmente');
                const j = await r.json();
                if (j.success) this.segmente = j.data.segmente || [];
            } catch (e) {}
        },
        ladeSegment() {
            if (!this.aktivesSegment) return;
            const seg = this.segmente.find(s => s.id == this.aktivesSegment);
            if (!seg) return;
            try {
                const f = typeof seg.filter_json === 'string' ? JSON.parse(seg.filter_json) : seg.filter_json;
                this.filter = { ...this.filter, ...f };
                this.laden(true);
            } catch (e) { App.showNotification('Segment-Filter ungültig', 'error'); }
        },
        async loescheSegment() {
            if (!this.aktivesSegment) return;
            const seg = this.segmente.find(s => s.id == this.aktivesSegment);
            if (!confirm('Segment „' + seg.name + '" löschen?')) return;
            await fetch('/api/v1/crm/segmente/' + this.aktivesSegment, { method:'DELETE', credentials:'same-origin' });
            this.aktivesSegment = ''; this.ladeSegmente();
            App.showNotification('Segment gelöscht', 'success');
        },

        toggleMulti(feld, wert, ev) {
            const arr = this.filter[feld];
            const idx = arr.indexOf(wert);
            const isAdditiv = ev && (ev.shiftKey || ev.ctrlKey || ev.metaKey);
            if (isAdditiv) {
                if (idx >= 0) arr.splice(idx, 1); else arr.push(wert);
            } else {
                if (arr.length === 1 && idx === 0) this.filter[feld] = [];
                else this.filter[feld] = [wert];
            }
            this.aktivesSegment = '';
            this.laden(true);
        },
        toggleTag(tagId, ev) {
            const hat = this.filter.tag_ids;
            const nicht = this.filter.ohne_tag_ids;
            if (ev && ev.shiftKey) {
                const iP = hat.indexOf(tagId), iN = nicht.indexOf(tagId);
                if (iP >= 0) hat.splice(iP, 1);
                if (iN >= 0) nicht.splice(iN, 1); else nicht.push(tagId);
            } else {
                const iP = hat.indexOf(tagId), iN = nicht.indexOf(tagId);
                if (iN >= 0) nicht.splice(iN, 1);
                if (iP >= 0) hat.splice(iP, 1); else hat.push(tagId);
            }
            this.aktivesSegment = '';
            this.laden(true);
        },
        hatAktiveFilter() {
            const f = this.filter;
            return !!(f.suche || f.kontakt_status.length || f.opt_in_status.length || f.tag_ids.length || f.ohne_tag_ids.length || f.listen_ids.length || f.ohne_firma || f.mit_foto || f.mit_zoho_legacy);
        },
        filterAlsJson() {
            const f = {...this.filter};
            Object.keys(f).forEach(k => {
                if (Array.isArray(f[k]) && f[k].length === 0) delete f[k];
                if (!f[k] && f[k] !== 0) delete f[k];
            });
            return f;
        },

        async laden(reset = false) {
            if (reset) this.offset = 0;
            this.laedt = true; this.fehler = null;
            try {
                const p = new URLSearchParams();
                p.set('limit', this.limit); p.set('offset', this.offset);
                p.set('sort', this.sort); p.set('order', this.order);
                if (this.filter.suche) p.set('suche', this.filter.suche);
                this.filter.kontakt_status.forEach(v => p.append('kontakt_status[]', v));
                this.filter.opt_in_status.forEach(v => p.append('opt_in_status[]', v));
                this.filter.tag_ids.forEach(v => p.append('tag_ids[]', v));
                this.filter.ohne_tag_ids.forEach(v => p.append('ohne_tag_ids[]', v));
                this.filter.listen_ids.forEach(v => p.append('listen_ids[]', v));
                if (this.filter.tag_modus) p.set('tag_modus', this.filter.tag_modus);
                if (this.filter.ohne_firma) p.set('ohne_firma', '1');
                if (this.filter.mit_foto) p.set('mit_foto', '1');
                if (this.filter.mit_zoho_legacy) p.set('mit_zoho_legacy', '1');
                const r = await fetch('/api/v1/crm/kontakte?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) { this.fehler = j.message || 'Fehler'; this.laedt = false; return; }
                this.eintraege = j.data.eintraege || [];
                this.gesamt = j.data.gesamt || 0;
            } catch (e) { this.fehler = e.message; }
            this.laedt = false;
        },
        filterReset() {
            this.filter = { suche:'', kontakt_status:[], opt_in_status:[], tag_ids:[], ohne_tag_ids:[], tag_modus:'oder', listen_ids:[], ohne_firma:false, mit_foto:false, mit_zoho_legacy:false };
            this.aktivesSegment = '';
            this.laden(true);
        },

        // Inline-Edit
        istOffen(id, feld) { return this.editKontaktId === id && this.editFeld === feld; },
        oeffneInline(k, feld) { this.editKontaktId = k.id; this.editFeld = feld; this.editWert = k[feld] ?? ''; },
        schliesseInline() { this.editKontaktId = null; this.editFeld = null; this.editWert = ''; },
        async speichereInline(k, feld) {
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + k.id + '/inline', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ feld, wert: this.editWert })
                });
                const j = await r.json();
                if (j.success) { k[feld] = this.editWert; this.schliesseInline(); }
                else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        // Schnell-Status aus Contextmenu
        async schnellStatus(k, wert) {
            this.editWert = wert; this.editFeld = 'kontakt_status'; this.editKontaktId = k.id;
            await this.speichereInline(k, 'kontakt_status');
            App.showNotification('Status: ' + this.formatStatus(wert), 'success');
        },

        // Contextmenu
        oeffneCtxMenu(ev, kontakt) {
            this.ctxMenu = { offen: true, x: ev.clientX, y: ev.clientY, kontakt };
        },

        async einzelDelete(k) {
            if (!confirm('Kontakt „' + (k.vorname||'') + ' ' + (k.nachname||'') + '" löschen?')) return;
            const r = await fetch('/api/v1/crm/kontakte/' + k.id, { method:'DELETE', credentials:'same-origin' });
            if ((await r.json()).success) { App.showNotification('Gelöscht', 'success'); this.laden(); }
        },

        // Bulk
        async bulkRun(aktion, wert, msg) {
            this.bulkMenu = null;
            const ids = [...this.auswahl];
            try {
                const r = await fetch('/api/v1/crm/kontakte/bulk', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ ids, aktion, wert })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification(msg + ' (' + j.data.ok + '/' + j.data.gesamt + ')', 'success');
                    this.auswahlLeeren(); this.laden();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        async bulkSoftDelete() {
            if (!confirm(this.auswahl.size + ' Kontakte löschen?')) return;
            await this.bulkRun('loeschen', null, 'Gelöscht');
        },
        toggleAuswahl(id) { if (this.auswahl.has(id)) this.auswahl.delete(id); else this.auswahl.add(id); this.auswahl = new Set(this.auswahl); },
        alleSichtbarGewaehlt() { return this.eintraege.length > 0 && this.eintraege.every(k => this.auswahl.has(k.id)); },
        toggleAlleSichtbar() {
            const alle = this.alleSichtbarGewaehlt();
            if (alle) this.eintraege.forEach(k => this.auswahl.delete(k.id));
            else this.eintraege.forEach(k => this.auswahl.add(k.id));
            this.auswahl = new Set(this.auswahl);
        },
        auswahlLeeren() { this.auswahl = new Set(); },

        // Segment speichern
        oeffneSegmentSpeichern() { this.segDialog = { offen: true, name: '', sichtbarkeit: 'privat' }; },
        async speichereSegment() {
            const name = this.segDialog.name.trim();
            if (!name) return;
            const r = await fetch('/api/v1/crm/segmente', {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ name, sichtbarkeit: this.segDialog.sichtbarkeit, filter_json: this.filterAlsJson() })
            });
            const j = await r.json();
            if (j.success) {
                App.showNotification('Segment „' + name + '" gespeichert', 'success');
                this.segDialog.offen = false; this.ladeSegmente(); this.aktivesSegment = j.data.id;
            } else App.showNotification(j.message || 'Fehler', 'error');
        },

        async oeffneDetail(id) {
            this.drawer = { offen: true, laedt: true, k: null };
            // Modus aus localStorage — der iframe initialisiert sich selbst gleich, das hier
            // hält nur den Drawer-Header-Toggle synchron.
            this.drawerEditMode = localStorage.getItem('crm_kontakt_edit_mode') === '1';
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + id, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.drawer.k = j.data;
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.drawer.laedt = false;
        },
        // Edit-Toggle im Drawer-Header — schickt postMessage ins iframe + persistiert
        toggleDrawerEdit() {
            this.drawerEditMode = !this.drawerEditMode;
            try { localStorage.setItem('crm_kontakt_edit_mode', this.drawerEditMode ? '1' : '0'); } catch (e) {}
            const ifr = document.getElementById('crm-drawer-iframe');
            if (ifr && ifr.contentWindow) {
                ifr.contentWindow.postMessage({ type: 'crm:setEditMode', value: this.drawerEditMode }, window.location.origin);
            }
        },
        formatFeld(feld, wert) {
            if (wert === null || wert === undefined || wert === '') return '';
            if (feld === 'kontakt_status') return this.formatStatus(wert);
            if (feld === 'opt_in_status') return this.drawerOptInLabel(wert);
            return wert;
        },
        // ─── Inline-Edit für Drawer (operiert auf drawer.k) ───
        istOffen(feld) { return this.editFeld === feld; },
        oeffneEdit(feld, typ) {
            if (!this.drawer.k) return;
            this.editFeld = feld;
            this.editTyp = typ;
            this.editWert = this.drawer.k[feld] ?? '';
        },
        schliesseEdit() { this.editFeld = null; this.editTyp = null; this.editWert = ''; },
        async speichern() {
            if (!this.editFeld || !this.drawer.k) return;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.drawer.k.id + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: this.editFeld, wert: this.editWert })
                });
                const j = await r.json();
                if (j.success) {
                    this.drawer.k[this.editFeld] = this.editWert === '' ? null : this.editWert;
                    // In der Liste denselben Eintrag aktualisieren (damit Tabelle sofort frisch ist)
                    const lst = this.eintraege.find(e => e.id === this.drawer.k.id);
                    if (lst) lst[this.editFeld] = this.drawer.k[this.editFeld];
                    this.schliesseEdit();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        formatFeldwert(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '— setzen';
            if (feld === 'kontakt_status') return this.formatStatus(wert);
            if (feld === 'opt_in_status') return this.drawerOptInLabel(wert);
            if (feld === 'deal_wert') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },
        formatFeldwertLese(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '—';
            if (feld === 'kontakt_status') return this.formatStatus(wert);
            if (feld === 'opt_in_status') return this.drawerOptInLabel(wert);
            if (feld === 'deal_wert') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },
        drawerOptInLabel(s) { return ({pending:'Pending',single_opted_in:'Single Opt-In',double_opted_in:'bestätigt',unsubscribed:'abgemeldet',hard_bounce:'Hard Bounce',invalid:'invalid'})[s] || s || '— offen —'; },
        drawerOptInBadge() {
            return ({pending:'crm-bigbadge-pend', single_opted_in:'crm-bigbadge-soi', double_opted_in:'crm-bigbadge-doi', unsubscribed:'crm-bigbadge-aus', hard_bounce:'crm-bigbadge-hb', invalid:'crm-bigbadge-leer'})[this.drawer.k?.opt_in_status] || 'crm-bigbadge-leer';
        },
        drawerFormatDate(d) {
            if (!d) return '';
            const dt = new Date(d.replace(' ', 'T'));
            return dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric' });
        },
        sortBy(feld) {
            if (this.sort === feld) this.order = this.order === 'asc' ? 'desc' : 'asc';
            else { this.sort = feld; this.order = 'asc'; }
            this.laden(true);
        },
        sortKlasse(feld) { return this.sort === feld ? ('is-sorted-' + this.order) : ''; },
        sortPfeil(feld) { return this.sort !== feld ? '' : (this.order === 'asc' ? '↑' : '↓'); },
        seitenVor() { this.offset += this.limit; this.laden(); window.scrollTo(0,0); },
        seitenZurueck() { this.offset = Math.max(0, this.offset - this.limit); this.laden(); window.scrollTo(0,0); },
        formatDate(d) { if (!d) return ''; const dt = new Date(d.replace(' ', 'T')); return dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'2-digit' }); },
        formatStatus(s) { return ({ lead:'Lead', interessent:'Interessent', kunde:'Kunde', ehemaliger_kunde:'Ehemalig', partner:'Partner', wunschkunde:'Wunschkunde', dienstleister:'Dienstleister', sonstiges:'Sonstiges' })[s] || s; },
    };
}
</script>
