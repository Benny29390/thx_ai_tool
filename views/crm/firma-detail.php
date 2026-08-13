<?php $activeModul = 'firmen'; $firmaId = (int)($firmaId ?? 0); $bodyClass = 'crm-detail-page'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>document.body.classList.add('crm-detail-page');</script>

<div x-data="crmFirmaDetail(<?= $firmaId ?>)" x-init="laden()" x-cloak class="crm-detail-root">

    <div x-show="laedt" style="padding:30px;text-align:center;color:var(--slate-400);">Lade …</div>

    <template x-if="!laedt && !f">
        <div class="thx-card" style="padding:30px;text-align:center;color:var(--rose-700);">
            Firma nicht gefunden.
            <div style="margin-top:10px;"><a href="/crm/firmen" class="thx-btn thx-btn-secondary">← Zur Liste</a></div>
        </div>
    </template>

    <template x-if="!laedt && f">
        <div>
            <!-- ═══════════ TOP-ACTION-BAR ═══════════ -->
            <div class="thx-page-header crm-detail-topbar">
                <div class="crm-detail-topbar-left">
                    <a href="/crm/firmen" class="thx-btn thx-btn-secondary thx-btn-small" title="Zurück">←</a>
                    <h1 class="thx-page-title" style="margin:0;font-size:1.1rem;" x-text="f.firmenname"></h1>
                    <span x-show="f.branche" class="lam-chip" x-text="f.branche"></span>
                    <span x-show="f.firmen_typ" class="lam-chip" style="background:var(--thoxan-100);color:var(--thoxan-700);" x-text="f.firmen_typ"></span>
                </div>
                <nav class="thx-tabs crm-detail-topbar-tabs">
                    <a href="#" @click.prevent="wechsleTab('infos')" :class="['thx-tab', tab==='infos'?'is-active':'']">Infos</a>
                    <a href="#" @click.prevent="wechsleTab('kontakte')" :class="['thx-tab', tab==='kontakte'?'is-active':'']">
                        Kontakte <span x-show="(f.kontakte||[]).length > 0" style="color:var(--slate-400);font-size:0.78rem;margin-left:4px;" x-text="'(' + (f.kontakte||[]).length + ')'"></span>
                    </a>
                </nav>
                <div class="crm-detail-topbar-right">
                    <button class="thx-btn thx-btn-small crm-editmode-toggle"
                            :class="editMode ? 'thx-btn-primary is-active' : 'thx-btn-secondary'"
                            @click="editMode = !editMode"
                            :title="editMode ? 'Bearbeitung aus' : 'Bearbeitung ein'">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;" x-text="editMode ? 'edit_off' : 'edit'"></span>
                        <span x-text="editMode ? 'Lesemodus' : 'Bearbeiten'"></span>
                    </button>
                    <a x-show="f.website" :href="f.website" target="_blank" rel="noopener" class="thx-btn thx-btn-primary thx-btn-small">🌐 Website</a>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click="softDelete()" style="color:var(--rose-700);">Löschen</button>
                </div>
            </div>

            <!-- ═══════════ 3-SPALTEN-GRID ═══════════ -->
            <div class="crm-detail-grid3" :class="tocCollapsed ? 'is-toc-collapsed' : ''">

                <!-- ─── LINKE FUNKTIONS-SIDEBAR ─── -->
                <aside class="thx-shell-side crm-detail-side-funcs" style="padding:0;">
                    <!-- Profil-Header oben mit Logo + Hover-Edit-Menü -->
                    <div class="crm-side-profil">
                        <div class="firma-logo-wrap" :class="logoUploading ? 'is-uploading' : ''" :id="'firma-logo-wrap-' + f.id">
                            <template x-if="f.logo_path">
                                <img :src="logoUrl(f.logo_path)" class="crm-side-profil-avatar firma-logo-img" :alt="f.firmenname" @click="toggleLogoMenu()">
                            </template>
                            <template x-if="!f.logo_path">
                                <span class="crm-side-profil-avatar crm-side-profil-avatar-fallback"
                                      x-text="(f.firmenname||'?').split(' ').filter(w => w.length > 0).slice(0,2).map(w => w[0]).join('').toUpperCase()"
                                      @click="toggleLogoMenu()"></span>
                            </template>
                            <input type="file" id="firma-logo-file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" style="display:none;" @change="uploadLogo($event.target)">
                            <button class="firma-logo-edit-btn" type="button" @click.stop="toggleLogoMenu()" title="Logo bearbeiten">
                                <span class="material-symbols-rounded">edit</span>
                            </button>
                            <div class="firma-logo-menu" :class="logoMenuOffen ? 'is-open' : ''" @click.outside="logoMenuOffen = false">
                                <button type="button" class="firma-logo-menu-item" @click="document.getElementById('firma-logo-file').click(); logoMenuOffen = false">
                                    <span class="material-symbols-rounded">upload_file</span> Logo hochladen
                                </button>
                                <button type="button" class="firma-logo-menu-item" @click="fetchFavicon(); logoMenuOffen = false"
                                        :disabled="!f.website"
                                        :title="f.website ? 'Favicon von Website holen' : 'Keine Website hinterlegt'">
                                    <span class="material-symbols-rounded">language</span> Favicon von Website
                                </button>
                                <button x-show="f.logo_path" type="button" class="firma-logo-menu-item is-danger" @click="deleteLogo(); logoMenuOffen = false">
                                    <span class="material-symbols-rounded">delete</span> Logo entfernen
                                </button>
                            </div>
                        </div>
                        <div class="crm-side-profil-name" x-text="f.firmenname"></div>
                        <div class="crm-side-profil-firma" x-text="f.branche || '— ohne Branche —'" :style="!f.branche ? 'color:var(--slate-400);' : ''"></div>
                        <div class="crm-side-profil-meta">
                            <span x-show="f.firmen_typ" class="crm-side-pill crm-side-pill-soi">
                                <span class="material-symbols-rounded" style="font-size:11px !important;">business</span>
                                <span x-text="f.firmen_typ"></span>
                            </span>
                            <span x-show="f.bewertung" class="crm-side-pill crm-side-pill-score">
                                <span class="material-symbols-rounded" style="font-size:11px !important;">star</span>
                                <span x-text="f.bewertung + '/5'"></span>
                            </span>
                        </div>
                    </div>

                    <div class="thx-shell-side-content">
                        <!-- Quick-Aktionen -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">bolt</span>Quick-Aktionen</div>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <a x-show="f.website" :href="f.website" target="_blank" rel="noopener" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">language</span> Website
                                </a>
                                <a x-show="f.telefon" :href="'tel:' + f.telefon" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">call</span> Tel <span style="color:var(--slate-500);" x-text="f.telefon"></span>
                                </a>
                                <a x-show="f.email" :href="'mailto:' + f.email" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">mail</span> E-Mail
                                </a>
                                <a :href="'/crm/kontakte?firma_id=' + f.id" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">groups</span> Alle Kontakte
                                </a>
                            </div>
                        </div>

                        <!-- Stats (aggregiert über alle Kontakte) -->
                        <div class="thx-shell-group" x-show="f.stats">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">analytics</span>Aktivitäten (alle Kontakte)</div>
                            <dl style="margin:0;">
                                <div class="crm-stat-row"><dt>Kontakte</dt><dd x-text="(f.kontakte||[]).length"></dd></div>
                                <div class="crm-stat-row"><dt>Aktivitäten gesamt</dt><dd x-text="f.stats?.aktivitaeten_gesamt || 0"></dd></div>
                                <div class="crm-stat-row" x-show="f.stats?.mails_geoeffnet"><dt>E-Mails geöffnet</dt><dd x-text="f.stats?.mails_geoeffnet"></dd></div>
                                <div class="crm-stat-row" x-show="f.stats?.mails_geklickt"><dt>E-Mail-Klicks</dt><dd x-text="f.stats?.mails_geklickt"></dd></div>
                                <div class="crm-stat-row" x-show="f.stats?.anrufe"><dt>Telefonate</dt><dd x-text="f.stats?.anrufe"></dd></div>
                                <div class="crm-stat-row" x-show="f.stats?.notizen"><dt>Notizen</dt><dd x-text="f.stats?.notizen"></dd></div>
                                <div class="crm-stat-row" x-show="f.stats?.letzte_aktivitaet" style="border-top:1px solid var(--slate-200);margin-top:4px;padding-top:6px;">
                                    <dt>Letzte Aktivität</dt><dd style="font-weight:500;" x-text="formatDate(f.stats?.letzte_aktivitaet)"></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Konzern: Parent + Töchter -->
                        <div class="thx-shell-group" x-show="f.parent_firma || (f.tochter_firmen||[]).length > 0">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">account_tree</span>Konzern</div>
                            <div x-show="f.parent_firma" style="font-size:0.78rem;margin-bottom:6px;">
                                <div style="color:var(--slate-500);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;">Mutter</div>
                                <a :href="'/crm/firmen/' + f.parent_firma?.id" style="color:var(--thoxan-600);" x-text="f.parent_firma?.firmenname"></a>
                            </div>
                            <div x-show="(f.tochter_firmen||[]).length > 0">
                                <div style="color:var(--slate-500);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;">Töchter</div>
                                <template x-for="t in (f.tochter_firmen||[])" :key="t.id">
                                    <div style="font-size:0.78rem;padding:2px 0;">
                                        <a :href="'/crm/firmen/' + t.id" style="color:var(--thoxan-600);" x-text="t.firmenname"></a>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Wissensdatenbank-Sync-Status (dezent, Hintergrundprozess) -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">memory</span>Wissensdatenbank</div>
                            <template x-if="f.wissens_queued">
                                <div class="crm-sync-pill is-queued" title="Worker arbeitet die Warteschlange ab (max. 60s + 30s Debounce)">
                                    <span class="material-symbols-rounded crm-sync-spin">sync</span>
                                    <span>wird synchronisiert …</span>
                                </div>
                            </template>
                            <template x-if="!f.wissens_queued && f.wissens_doc && f.wissens_doc.is_active == 1">
                                <div>
                                    <div class="crm-sync-pill is-ok">
                                        <span class="material-symbols-rounded">check_circle</span>
                                        <span>synchronisiert</span>
                                        <a :href="'/admin/wissen-v2?doc=' + f.wissens_doc.id" class="crm-sync-link" target="_blank" rel="noopener" title="Dokument im Wissens-V2 ansehen">
                                            <span class="material-symbols-rounded">open_in_new</span>
                                        </a>
                                        <span class="crm-sync-since" :title="f.wissens_doc.updated_at" x-text="formatRelative(f.wissens_doc.updated_at)"></span>
                                    </div>
                                    <div class="crm-sync-meta">
                                        <strong x-text="f.wissens_doc.customer_name || 'Standard'"></strong> · <span x-text="f.wissens_doc.chunks + ' Chunks'"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!f.wissens_queued && (!f.wissens_doc || f.wissens_doc.is_active == 0)">
                                <div class="crm-sync-pill is-missing">
                                    <span class="material-symbols-rounded">cloud_off</span>
                                    <span>nicht in Wissensbasis</span>
                                </div>
                            </template>
                        </div>

                        <!-- Externe Systeme: Zoho immer da, bei Fehlen ausgegraut -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">open_in_new</span>Externe Systeme</div>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <template x-if="f.legacy_zoho_id">
                                    <a :href="'https://crm.zoho.eu/crm/tab/Accounts/' + (f.legacy_zoho_id || '').replace(/^zcrm_/, '')"
                                       target="_blank" rel="noopener"
                                       class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Zoho öffnen
                                    </a>
                                </template>
                                <template x-if="!f.legacy_zoho_id">
                                    <span class="thx-shell-btn is-disabled" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;" title="Nicht mit Zoho verknüpft">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Zoho öffnen
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Meta -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">info</span>Meta</div>
                            <dl style="margin:0;">
                                <div class="crm-stat-row"><dt>Angelegt</dt><dd x-text="formatDate(f.erstellt_am)"></dd></div>
                                <div class="crm-stat-row"><dt>Geändert</dt><dd x-text="formatDate(f.geaendert_am)"></dd></div>
                            </dl>
                        </div>
                    </div>
                </aside>

                <!-- ─── TOC (Inhaltsverzeichnis) ─── -->
                <aside class="crm-detail-toc" :class="tocCollapsed ? 'is-collapsed' : ''"
                       @keydown.arrow-up.prevent="navigiereTOC(-1)"
                       @keydown.arrow-down.prevent="navigiereTOC(1)"
                       @keydown.home.prevent="springZumAnfang()"
                       tabindex="-1">
                    <div class="crm-detail-toc-header">
                        <span class="crm-detail-toc-title" x-show="!tocCollapsed">Inhalt</span>
                        <button class="thx-icon-btn crm-toc-top" @click="springZumAnfang()" title="Ganz nach oben">
                            <span class="material-symbols-rounded">vertical_align_top</span>
                        </button>
                        <button class="thx-icon-btn" @click="tocCollapsed = !tocCollapsed; localStorage.setItem('crm_toc_collapsed', tocCollapsed ? '1' : '0')" :title="tocCollapsed ? 'Ausklappen' : 'Einklappen'" x-show="!tocCollapsed">
                            <span class="material-symbols-rounded">chevron_left</span>
                        </button>
                        <button class="thx-icon-btn" @click="tocCollapsed = !tocCollapsed; localStorage.setItem('crm_toc_collapsed', tocCollapsed ? '1' : '0')" :title="'Ausklappen'" x-show="tocCollapsed">
                            <span class="material-symbols-rounded">chevron_right</span>
                        </button>
                    </div>
                    <nav class="crm-detail-toc-list">
                        <template x-for="s in tocSektionen" :key="s.id">
                            <button type="button" @click="springZuSektion(s.id)"
                               :class="['crm-toc-item', aktiveSektion === s.id ? 'is-active' : '']"
                               :title="tocCollapsed ? s.label : ''"
                               :data-sektion="s.id">
                                <span class="material-symbols-rounded" x-text="s.icon"></span>
                                <span class="crm-toc-item-label" x-text="s.label"></span>
                            </button>
                        </template>
                    </nav>
                </aside>

                <!-- ─── RECHTE CARDS ─── -->
                <main>
                    <!-- TAB INFOS -->
                    <div x-show="tab==='infos'" class="crm-detail-tab-stack">

                        <div class="thx-card" id="sec-stammdaten">
                            <h2 class="crm-section-titel">Stammdaten</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['firmenname','Firmenname','text'],['branche','Branche','text'],['firmen_typ','Firmen-Typ','text'],['website','Website','url'],['email','E-Mail','email'],['telefon','Telefon','tel'],['fax','Fax','tel'],['beschaeftigte','Beschäftigte','number'],['jahreseinnahmen','Jahreseinnahmen (€)','number'],['bewertung','Bewertung (1-5)','number']]"; include __DIR__ . '/_firma-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <div class="thx-card" id="sec-adressen">
                            <h2 class="crm-section-titel">Adressen <span class="crm-section-count" x-text="'(' + (f.adressen||[]).length + ')'"></span></h2>
                            <template x-if="(f.adressen||[]).length === 0">
                                <div style="color:var(--slate-400);font-size:0.85rem;">Keine Adressen hinterlegt.</div>
                            </template>
                            <template x-for="(a, idx) in (f.adressen||[])" :key="a.id">
                                <div :style="idx > 0 ? 'margin-top:14px;border-top:1px dashed var(--slate-200);padding-top:10px;' : ''">
                                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);margin-bottom:6px;font-weight:600;" x-text="a.typ"></div>
                                    <dl class="crm-fields">
                                        <div class="crm-field"><dt class="crm-field-label">Straße</dt><dd class="crm-field-wert" x-text="a.strasse || '—'"></dd></div>
                                        <div class="crm-field"><dt class="crm-field-label">PLZ</dt><dd class="crm-field-wert" x-text="a.plz || '—'"></dd></div>
                                        <div class="crm-field"><dt class="crm-field-label">Stadt</dt><dd class="crm-field-wert" x-text="a.stadt || '—'"></dd></div>
                                        <div class="crm-field"><dt class="crm-field-label">Bundesland</dt><dd class="crm-field-wert" x-text="a.bundesland || '—'"></dd></div>
                                        <div class="crm-field"><dt class="crm-field-label">Land</dt><dd class="crm-field-wert" x-text="a.land || '—'"></dd></div>
                                    </dl>
                                </div>
                            </template>
                        </div>

                        <div class="thx-card" id="sec-kontakte-preview">
                            <h2 class="crm-section-titel">Verknüpfte Kontakte <span class="crm-section-count" x-text="'(' + (f.kontakte||[]).length + ')'"></span></h2>
                            <template x-if="(f.kontakte||[]).length === 0">
                                <div style="color:var(--slate-400);font-size:0.85rem;">Keine Kontakte zugeordnet.</div>
                            </template>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <template x-for="k in (f.kontakte||[]).slice(0, 8)" :key="k.id">
                                    <a :href="'/crm/kontakte/' + k.id" class="crm-firma-kontakt" style="font-size:0.85rem;padding:8px 10px;">
                                        <span class="crm-firma-kontakt-avatar">
                                            <template x-if="k.foto_path"><img :src="k.foto_path"></template>
                                            <template x-if="!k.foto_path">
                                                <span x-text="((k.vorname||'?')[0]||'') + ((k.nachname||'?')[0]||'')"></span>
                                            </template>
                                        </span>
                                        <div style="min-width:0;flex:1;">
                                            <div class="crm-firma-kontakt-name" x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></div>
                                            <div x-show="k.funktion" class="crm-firma-kontakt-funktion" x-text="k.funktion"></div>
                                        </div>
                                        <span x-show="k.email_primaer" style="color:var(--thoxan-600);font-size:0.78rem;" x-text="k.email_primaer"></span>
                                    </a>
                                </template>
                            </div>
                            <a x-show="(f.kontakte||[]).length > 8" :href="'/crm/kontakte?firma_id=' + f.id" style="display:block;margin-top:10px;font-size:0.82rem;color:var(--thoxan-600);text-align:center;">→ Alle <span x-text="(f.kontakte||[]).length"></span> Kontakte zeigen</a>
                        </div>

                        <div class="thx-card" id="sec-notizen">
                            <h2 class="crm-section-titel">Notizen</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['notizen','Notizen','textarea']]"; include __DIR__ . '/_firma-feld-snippet.php'; ?>
                            </dl>
                        </div>

                    </div>

                    <!-- TAB KONTAKTE (volle Tabelle) -->
                    <div x-show="tab==='kontakte'" class="crm-detail-tab-stack">
                        <div class="thx-card" id="sec-alle-kontakte">
                            <h2 class="crm-section-titel">Alle Kontakte dieser Firma</h2>
                            <template x-if="(f.kontakte||[]).length === 0">
                                <div style="color:var(--slate-400);font-size:0.85rem;">Keine Kontakte zugeordnet.</div>
                            </template>
                            <template x-if="(f.kontakte||[]).length > 0">
                                <table class="thx-shell-table" style="margin-top:8px;">
                                    <thead><tr><th>Name</th><th>Funktion</th><th>E-Mail</th><th>Telefon/Mobil</th><th class="center">Status</th></tr></thead>
                                    <tbody>
                                        <template x-for="k in (f.kontakte||[])" :key="k.id">
                                            <tr style="cursor:pointer;" @click="window.location.href='/crm/kontakte/' + k.id">
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:8px;">
                                                        <template x-if="k.foto_path"><img :src="k.foto_path" style="width:24px;height:24px;border-radius:50%;object-fit:cover;"></template>
                                                        <template x-if="!k.foto_path"><span style="width:24px;height:24px;border-radius:50%;background:var(--slate-200);color:var(--slate-600);display:inline-flex;align-items:center;justify-content:center;font-size:0.62rem;font-weight:600;" x-text="((k.vorname||'?')[0]||'') + ((k.nachname||'?')[0]||'')"></span></template>
                                                        <strong x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></strong>
                                                    </div>
                                                </td>
                                                <td x-text="k.funktion || ''" style="color:var(--slate-600);"></td>
                                                <td><a :href="'mailto:' + k.email_primaer" @click.stop style="color:var(--thoxan-600);" x-text="k.email_primaer"></a></td>
                                                <td x-text="k.mobil || k.telefon || ''" style="font-size:0.78rem;color:var(--slate-600);"></td>
                                                <td class="center"><span x-show="k.kontakt_status" class="lam-chip" x-text="k.kontakt_status"></span></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>
                        </div>
                    </div>

                </main>
            </div>
        </div>
    </template>
</div>

<script>
function crmFirmaDetail(id) {
    return {
        firmaId: id,
        laedt: true, f: null,
        tab: 'infos',
        editMode: localStorage.getItem('crm_firma_edit_mode') === '1', // sticky firmenübergreifend
        editFeld: null, editTyp: null, editWert: '',
        logoMenuOffen: false,
        logoUploading: false,
        tocCollapsed: localStorage.getItem('crm_toc_collapsed') === '1',
        aktiveSektion: 'sec-stammdaten',
        tocInfosSektionen: [
            { id: 'sec-stammdaten',        label: 'Stammdaten',    icon: 'apartment' },
            { id: 'sec-adressen',          label: 'Adressen',      icon: 'pin_drop' },
            { id: 'sec-kontakte-preview',  label: 'Kontakte',      icon: 'groups' },
            { id: 'sec-notizen',           label: 'Notizen',       icon: 'description' },
        ],
        get tocSektionen() {
            if (this.tab === 'kontakte') {
                return [{ id: 'sec-alle-kontakte', label: 'Alle Kontakte', icon: 'groups' }];
            }
            return this.tocInfosSektionen;
        },

        async laden() {
            this.laedt = true;
            try {
                const r = await fetch('/api/v1/crm/firmen/' + this.firmaId, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.f = j.data;
            } catch (e) {}
            this.laedt = false;
            if (this.f) {
                this.initScrollSpy();
                window.addEventListener('resize', () => this.setzePaddingBottom());
                // Wechsel in den Lesemodus → ggf. offenen Editor schließen + Modus persistieren
                this.$watch('editMode', v => {
                    if (!v) this.schliesseEdit();
                    try { localStorage.setItem('crm_firma_edit_mode', v ? '1' : '0'); } catch (e) {}
                    try { window.parent && window.parent !== window && window.parent.postMessage({ type: 'crm:editModeChanged', value: v }, window.location.origin); } catch (e) {}
                });
                // Parent-Drawer-Steuerung: Edit-Mode kann vom Drawer-Header per postMessage umgeschaltet werden
                window.addEventListener('message', (ev) => {
                    if (ev.origin !== window.location.origin) return;
                    if (ev.data?.type === 'crm:setEditMode') this.editMode = !!ev.data.value;
                });
                // Wenn ein anderer Tab/Frame den Modus ändert, mitziehen
                window.addEventListener('storage', (ev) => {
                    if (ev.key === 'crm_firma_edit_mode' && ev.newValue !== null) {
                        const neu = ev.newValue === '1';
                        if (this.editMode !== neu) this.editMode = neu;
                    }
                });
            }
        },

        wechsleTab(neuerTab) {
            this.tab = neuerTab;
            this.$nextTick(() => this.initScrollSpy());
        },

        // Inline-Edit
        istOffen(feld) { return this.editFeld === feld; },
        oeffneEdit(feld, typ) {
            this.editFeld = feld;
            this.editTyp = typ;
            this.editWert = this.f[feld] ?? '';
        },
        schliesseEdit() { this.editFeld = null; this.editTyp = null; this.editWert = ''; },
        async speichern() {
            if (!this.editFeld) return;
            try {
                const r = await fetch('/api/v1/crm/firmen/' + this.firmaId, {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ [this.editFeld]: this.editWert })
                });
                const j = await r.json();
                if (j.success) {
                    this.f[this.editFeld] = this.editWert === '' ? null : this.editWert;
                    this.schliesseEdit();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        formatFeldwert(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '— setzen';
            if (feld === 'jahreseinnahmen') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },
        formatFeldwertLese(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '—';
            if (feld === 'jahreseinnahmen') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },

        // TOC / Scroll-Spy (identisch zu Kontakt-Detail)
        _spyLock: false,
        springZuSektion(id) {
            const el = document.getElementById(id);
            if (!el) return;
            this.aktiveSektion = id;
            this._spyLock = true;
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => { this._spyLock = false; }, 700);
        },
        springZumAnfang() {
            this._spyLock = true;
            if (this.tocSektionen[0]) this.aktiveSektion = this.tocSektionen[0].id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => { this._spyLock = false; }, 600);
        },
        navigiereTOC(delta) {
            const sektionen = this.tocSektionen;
            if (!sektionen.length) return;
            const idx = sektionen.findIndex(s => s.id === this.aktiveSektion);
            const next = Math.max(0, Math.min(sektionen.length - 1, (idx < 0 ? 0 : idx + delta)));
            if (sektionen[next]) {
                this.springZuSektion(sektionen[next].id);
                this.$nextTick(() => {
                    const btn = document.querySelector('[data-sektion="' + sektionen[next].id + '"]');
                    if (btn) btn.focus({ preventScroll: true });
                });
            }
        },
        _observer: null,
        initScrollSpy() {
            if (this._observer) this._observer.disconnect();
            const opts = { root: null, rootMargin: '-80px 0px -60% 0px', threshold: 0 };
            this._observer = new IntersectionObserver((entries) => {
                if (this._spyLock) return;
                const sichtbar = entries.filter(e => e.isIntersecting).sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (sichtbar.length > 0) this.aktiveSektion = sichtbar[0].target.id;
            }, opts);
            this.$nextTick(() => {
                this.tocSektionen.forEach(s => {
                    const el = document.getElementById(s.id);
                    if (el) this._observer.observe(el);
                });
                if (this.tocSektionen[0]) this.aktiveSektion = this.tocSektionen[0].id;
                this.setzePaddingBottom();
            });
        },
        // Dynamisches padding-bottom: genau so groß dass die letzte Card bis oben scrollt
        // Wichtig: nur SICHTBARE Cards zählen (im aktiven Tab) — versteckte Cards aus
        // inaktiven Tabs haben height=0 und würden die Rechnung verfälschen.
        setzePaddingBottom() {
            const main = document.querySelector('.crm-detail-grid3 > main');
            if (!main) return;
            const cards = Array.from(main.querySelectorAll('.thx-card'))
                .filter(c => c.offsetParent !== null);
            if (!cards.length) { main.style.paddingBottom = '0'; return; }
            const letzteCard = cards[cards.length - 1];
            const cardHoehe = letzteCard.getBoundingClientRect().height;
            const topbarH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--topbar-h')) || 44;
            const offset = topbarH + 18;
            const padding = Math.max(0, window.innerHeight - cardHoehe - offset);
            main.style.paddingBottom = padding + 'px';
        },

        async softDelete() {
            if (!confirm('Diese Firma wirklich löschen?')) return;
            const r = await fetch('/api/v1/crm/firmen/' + this.firmaId, { method: 'DELETE', credentials: 'same-origin' });
            if ((await r.json()).success) {
                App.showNotification('Gelöscht', 'success');
                setTimeout(() => window.location.href = '/crm/firmen', 500);
            }
        },

        // ─── Firma-Logo: Upload + Favicon-Fetch + Löschen ───
        logoUrl(path) {
            if (!path) return '';
            if (path.startsWith('/') || path.startsWith('http')) return path;
            return '/uploads/crm/firmenlogos/' + path;
        },
        toggleLogoMenu() { this.logoMenuOffen = !this.logoMenuOffen; },
        async uploadLogo(input) {
            const file = input.files && input.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('logo', file);
            this.logoUploading = true;
            try {
                const r = await fetch('/api/v1/crm/firmen/' + this.firmaId + '/logo', {
                    method: 'POST', credentials: 'same-origin', body: fd
                });
                const j = await r.json();
                if (j.success) {
                    this.f.logo_path = j.data.logo_url.split('/').pop();
                    App.showNotification('Logo gespeichert', 'success');
                } else App.showNotification(j.message || 'Upload fehlgeschlagen', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.logoUploading = false;
            input.value = '';
        },
        async fetchFavicon() {
            if (!this.f.website) return;
            this.logoUploading = true;
            try {
                const r = await fetch('/api/v1/crm/firmen/' + this.firmaId + '/fetch-favicon', {
                    method: 'POST', credentials: 'same-origin'
                });
                const j = await r.json();
                if (j.success) {
                    this.f.logo_path = j.data.logo_url.split('/').pop();
                    App.showNotification('Favicon übernommen', 'success');
                } else App.showNotification(j.message || 'Favicon-Fetch fehlgeschlagen', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.logoUploading = false;
        },
        async deleteLogo() {
            if (!confirm('Logo wirklich entfernen?')) return;
            try {
                const r = await fetch('/api/v1/crm/firmen/' + this.firmaId + '/logo', {
                    method: 'DELETE', credentials: 'same-origin'
                });
                const j = await r.json();
                if (j.success) {
                    this.f.logo_path = null;
                    App.showNotification('Logo entfernt', 'success');
                } else App.showNotification(j.message || 'Löschen fehlgeschlagen', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },

        formatDate(d) {
            if (!d) return '';
            const dt = new Date(d.replace(' ', 'T'));
            return dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric' });
        },
        formatRelative(d) {
            if (!d) return '';
            const dt = new Date(d.replace(' ', 'T'));
            const diffSec = Math.max(0, Math.floor((Date.now() - dt.getTime()) / 1000));
            if (diffSec < 60) return 'vor ' + diffSec + 's';
            if (diffSec < 3600) return 'vor ' + Math.floor(diffSec / 60) + ' Min';
            if (diffSec < 86400) return 'vor ' + Math.floor(diffSec / 3600) + ' Std';
            if (diffSec < 604800) return 'vor ' + Math.floor(diffSec / 86400) + ' Tg';
            return dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric' });
        },
    };
}
</script>
