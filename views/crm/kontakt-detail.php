<?php $activeModul = 'kontakte'; $kontaktId = (int)($kontaktId ?? 0); $bodyClass = 'crm-detail-page'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
document.body.classList.add('crm-detail-page');
// iframe-Drawer-Erkennung läuft SYNCHRON vor Alpine-Render → kein Banner-Flicker
(function() {
    var sp = new URLSearchParams(window.location.search);
    if (sp.get('drawer') === '1' || window.parent !== window) {
        document.body.classList.add('in-iframe-drawer');
    }
})();
</script>

<div x-data="crmKontaktDetail(<?= $kontaktId ?>)" x-init="laden()" x-cloak class="crm-detail-root">

    <div x-show="laedt" style="padding:30px;text-align:center;color:var(--slate-400);">Lade …</div>

    <template x-if="!laedt && !k">
        <div class="thx-card" style="padding:30px;text-align:center;color:var(--rose-700);">
            Kontakt nicht gefunden.
            <div style="margin-top:10px;"><a href="/crm/kontakte" class="thx-btn thx-btn-secondary">← Zur Liste</a></div>
        </div>
    </template>

    <template x-if="!laedt && k">
        <div>
            <!-- ═══════════════ TOP-ACTION-BAR (mit Tabs in der Mitte) ═══════════════ -->
            <div class="thx-page-header crm-detail-topbar">
                <div class="crm-detail-topbar-left">
                    <a href="/crm/kontakte" class="thx-btn thx-btn-secondary thx-btn-small" title="Zurück zur Liste">←</a>
                    <h1 class="thx-page-title" style="margin:0;font-size:1.1rem;" x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></h1>
                    <span x-show="k.kontakt_status" class="lam-chip" x-text="formatStatus(k.kontakt_status)"></span>
                    <template x-for="l in (k.listen||[]).filter(l => l.status === 'aktiv').slice(0, 2)" :key="l.id">
                        <span class="lam-chip" style="background:var(--thoxan-100);color:var(--thoxan-700);" x-text="l.name"></span>
                    </template>
                </div>
                <nav class="thx-tabs crm-detail-topbar-tabs">
                    <a href="#" @click.prevent="wechsleTab('infos')" :class="['thx-tab', tab==='infos'?'is-active':'']">Infos</a>
                    <a href="#" @click.prevent="wechsleTab('emails')" :class="['thx-tab', tab==='emails'?'is-active':'']">
                        E-Mails<span x-show="(k.mails||[]).length > 0" style="color:var(--slate-400);font-size:0.78rem;margin-left:4px;" x-text="'(' + (k.mails||[]).length + ')'"></span>
                    </a>
                    <a href="#" @click.prevent="wechsleTab('zeitlinie')" :class="['thx-tab', tab==='zeitlinie'?'is-active':'']">Zeitlinie</a>
                </nav>
                <div class="crm-detail-topbar-right">
                    <button class="thx-btn thx-btn-small crm-editmode-toggle"
                            :class="editMode ? 'thx-btn-primary is-active' : 'thx-btn-secondary'"
                            @click="editMode = !editMode"
                            :title="editMode ? 'Bearbeitung aus — Felder nur lesbar' : 'Bearbeitung ein — Felder klickbar'">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;" x-text="editMode ? 'edit_off' : 'edit'"></span>
                        <span x-text="editMode ? 'Lesemodus' : 'Bearbeiten'"></span>
                    </button>
                    <a x-show="k.email_primaer" :href="'mailto:' + k.email_primaer" class="thx-btn thx-btn-primary thx-btn-small">✉ E-Mail senden</a>
                    <button class="thx-btn thx-btn-secondary thx-btn-small" @click="oeffneNotizDialog()">📝 Notiz</button>
                    <div style="position:relative;" @click.outside="mehrMenu = false">
                        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="mehrMenu = !mehrMenu">⋯</button>
                        <div x-show="mehrMenu" x-cloak class="crm-dropdown">
                            <a x-show="k.mobil" :href="'tel:' + k.mobil" class="crm-dropdown-item">📞 Mobil: <span x-text="k.mobil"></span></a>
                            <a x-show="k.telefon" :href="'tel:' + k.telefon" class="crm-dropdown-item">📞 Tel: <span x-text="k.telefon"></span></a>
                            <a x-show="k.website" :href="k.website" target="_blank" rel="noopener" class="crm-dropdown-item">🌐 Website</a>
                            <a x-show="k.asana_task_gid" :href="'https://app.asana.com/0/0/' + k.asana_task_gid" target="_blank" class="crm-dropdown-item">🎯 Asana-Task</a>
                            <div class="crm-dropdown-sep"></div>
                            <button class="crm-dropdown-item" @click="softDelete()" style="color:var(--rose-700);">🗑 Kontakt löschen</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ EMBED-MODUS-BANNER: Foto + Pills + Quick-Aktionen ═══════════
                 nur sichtbar im Iframe-Drawer (body.thx-embed), wenn die linke
                 Funktions-Sidebar wegen Schmalheit versteckt ist. Sorgt dafür, dass
                 der User trotzdem Foto + Kernstatus sieht. -->
            <div class="crm-embed-profile-banner">
                <label class="crm-embed-avatar-wrap" title="Foto hochladen">
                    <input type="file" accept="image/*" @change="ladeFotoHoch($event)" style="display:none;">
                    <template x-if="k.foto_path">
                        <img :src="k.foto_path" class="crm-embed-avatar">
                    </template>
                    <template x-if="!k.foto_path">
                        <span class="crm-embed-avatar crm-embed-avatar-fallback" x-text="((k.vorname||'?')[0]||'') + ((k.nachname||'?')[0]||'')"></span>
                    </template>
                    <span class="crm-embed-avatar-cam"><span class="material-symbols-rounded">photo_camera</span></span>
                </label>
                <div class="crm-embed-profile-meta">
                    <span :class="['crm-side-pill', sidePillClass()]" :title="'Opt-In: ' + (formatOptIn(k.opt_in_status) || '—')">
                        <span class="material-symbols-rounded" style="font-size:11px !important;">mark_email_read</span>
                        <span x-text="formatOptIn(k.opt_in_status) || '— offen —'"></span>
                    </span>
                    <span class="crm-side-pill crm-side-pill-score" title="THX-Score">
                        <span class="material-symbols-rounded" style="font-size:11px !important;">stars</span>
                        <span x-text="(k.thx_score !== null && k.thx_score !== undefined && k.thx_score !== '') ? k.thx_score : '—'"></span>
                    </span>
                    <a x-show="k.email_primaer" :href="'mailto:' + k.email_primaer" class="crm-embed-action" title="E-Mail schreiben">
                        <span class="material-symbols-rounded">mail</span>
                    </a>
                    <a x-show="k.mobil" :href="'tel:' + k.mobil" class="crm-embed-action" title="Mobil anrufen">
                        <span class="material-symbols-rounded">smartphone</span>
                    </a>
                    <a x-show="k.telefon" :href="'tel:' + k.telefon" class="crm-embed-action" title="Tel. anrufen">
                        <span class="material-symbols-rounded">call</span>
                    </a>
                    <a x-show="k.website" :href="k.website" target="_blank" rel="noopener" class="crm-embed-action" title="Website öffnen">
                        <span class="material-symbols-rounded">language</span>
                    </a>
                </div>
            </div>

            <!-- ═══════════════ GRID: SIDEBAR + MAIN ═══════════════ -->
            <div class="crm-detail-grid3" :class="tocCollapsed ? 'is-toc-collapsed' : ''">

                <!-- ═════════════════ LINKE FUNKTIONS-SIDEBAR ═════════════════ -->
                <aside class="thx-shell-side crm-detail-side-funcs" style="padding:0;">
                    <!-- Profil-Header oben -->
                    <div class="crm-side-profil">
                        <label class="crm-side-profil-avatar-wrap" title="Foto hochladen">
                            <input type="file" accept="image/*" @change="ladeFotoHoch($event)" style="display:none;">
                            <template x-if="k.foto_path">
                                <img :src="k.foto_path" class="crm-side-profil-avatar">
                            </template>
                            <template x-if="!k.foto_path">
                                <span class="crm-side-profil-avatar crm-side-profil-avatar-fallback" x-text="((k.vorname||'?')[0]||'') + ((k.nachname||'?')[0]||'')"></span>
                            </template>
                            <span class="crm-side-profil-cam"><span class="material-symbols-rounded">photo_camera</span></span>
                        </label>
                        <div class="crm-side-profil-name" x-text="(k.vorname||'') + ' ' + (k.nachname||'')"></div>
                        <a x-show="k.firma_id" :href="'/crm/firmen/' + k.firma_id" class="crm-side-profil-firma" x-text="k.firmenname"></a>
                        <span x-show="!k.firma_id && (!k.firma_status || k.firma_status === 'verknuepft')" class="crm-side-profil-firma" style="color:var(--slate-400);">— keine Firma —</span>
                        <span x-show="!k.firma_id && k.firma_status === 'ohne_firmenbezug'" class="crm-side-profil-firma" style="display:inline-flex;align-items:center;gap:4px;color:var(--slate-600);background:var(--slate-100);padding:2px 8px;border-radius:999px;font-size:0.75rem;font-weight:500;">
                            <span class="material-symbols-rounded" style="font-size:12px;">person</span> Privater Kontakt
                        </span>
                        <span x-show="!k.firma_id && k.firma_status === 'pflege_offen'" class="crm-side-profil-firma" style="display:inline-flex;align-items:center;gap:4px;color:var(--amber-800);background:var(--amber-50);border:1px solid var(--amber-200);padding:2px 8px;border-radius:999px;font-size:0.75rem;font-weight:500;">
                            <span class="material-symbols-rounded" style="font-size:12px;">schedule</span> Pflege-Backlog: Firma zuweisen
                        </span>
                        <span x-show="k.shared_email == 1" :title="'Diese E-Mail teilt sich mit einem oder mehreren anderen Kontakten — Dubletten-Detektor lässt sie in Ruhe'" style="display:inline-flex;align-items:center;gap:4px;color:var(--thoxan-700);background:var(--thoxan-50);border:1px solid var(--thoxan-200);padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:500;margin-top:4px;">
                            <span class="material-symbols-rounded" style="font-size:12px;">groups</span> Geteilte Mailbox
                        </span>
                        <div class="crm-side-profil-meta">
                            <span :class="['crm-side-pill', sidePillClass()]" :title="'Opt-In: ' + (formatOptIn(k.opt_in_status) || '—')">
                                <span class="material-symbols-rounded" style="font-size:11px !important;">mark_email_read</span>
                                <span x-text="formatOptIn(k.opt_in_status) || '— offen —'"></span>
                            </span>
                            <span class="crm-side-pill crm-side-pill-score" title="THX-Score">
                                <span class="material-symbols-rounded" style="font-size:11px !important;">stars</span>
                                <span x-text="(k.thx_score !== null && k.thx_score !== undefined && k.thx_score !== '') ? k.thx_score : '—'"></span>
                            </span>
                        </div>
                    </div>

                    <div class="thx-shell-side-content">

                        <!-- Quick-Aktionen -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">bolt</span>Quick-Aktionen</div>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <a x-show="k.email_primaer" :href="'mailto:' + k.email_primaer" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">mail</span> E-Mail schreiben
                                </a>
                                <a x-show="k.mobil" :href="'tel:' + k.mobil" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">smartphone</span> Mobil <span style="color:var(--slate-500);" x-text="k.mobil"></span>
                                </a>
                                <a x-show="k.telefon" :href="'tel:' + k.telefon" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">call</span> Tel <span style="color:var(--slate-500);" x-text="k.telefon"></span>
                                </a>
                                <a x-show="k.website" :href="k.website" target="_blank" rel="noopener" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">language</span> Website öffnen
                                </a>
                                <button @click="oeffneNotizDialog()" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">edit_note</span> Notiz hinzufügen
                                </button>
                                <a x-show="k.asana_task_gid" :href="'https://app.asana.com/0/0/' + k.asana_task_gid" target="_blank" rel="noopener" class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">task_alt</span> Asana-Task
                                </a>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="thx-shell-group" x-show="k.stats">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">analytics</span>Aktivitäten</div>
                            <dl style="margin:0;">
                                <div class="crm-stat-row"><dt>Aktivitäten gesamt</dt><dd x-text="k.stats?.aktivitaeten_gesamt || 0"></dd></div>
                                <div class="crm-stat-row" x-show="k.stats?.mails_geoeffnet"><dt>E-Mails geöffnet</dt><dd x-text="k.stats?.mails_geoeffnet"></dd></div>
                                <div class="crm-stat-row" x-show="k.stats?.mails_geklickt"><dt>E-Mail-Klicks</dt><dd x-text="k.stats?.mails_geklickt"></dd></div>
                                <div class="crm-stat-row" x-show="k.stats?.anrufe"><dt>Telefonate</dt><dd x-text="k.stats?.anrufe"></dd></div>
                                <div class="crm-stat-row" x-show="k.stats?.notizen"><dt>Notizen</dt><dd x-text="k.stats?.notizen"></dd></div>
                                <div class="crm-stat-row" x-show="k.stats?.letzte_aktivitaet" style="border-top:1px solid var(--slate-200);margin-top:4px;padding-top:6px;">
                                    <dt>Letzte Aktivität</dt><dd style="font-weight:500;" x-text="formatDate(k.stats?.letzte_aktivitaet)"></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- (Block "Aus derselben Firma" wurde in Card sec-firma im Main-Bereich verschoben) -->

                        <!-- Wissensdatenbank-Sync-Status (dezent, Hintergrundprozess) -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">memory</span>Wissensdatenbank</div>
                            <template x-if="k.wissens_queued">
                                <div class="crm-sync-pill is-queued" title="Worker arbeitet die Warteschlange ab (max. 60s + 30s Debounce)">
                                    <span class="material-symbols-rounded crm-sync-spin">sync</span>
                                    <span>wird synchronisiert …</span>
                                </div>
                            </template>
                            <template x-if="!k.wissens_queued && k.wissens_doc && k.wissens_doc.is_active == 1">
                                <div>
                                    <div class="crm-sync-pill is-ok">
                                        <span class="material-symbols-rounded">check_circle</span>
                                        <span>synchronisiert</span>
                                        <a :href="'/admin/wissen-v2?doc=' + k.wissens_doc.id" class="crm-sync-link" target="_blank" rel="noopener" title="Dokument im Wissens-V2 ansehen">
                                            <span class="material-symbols-rounded">open_in_new</span>
                                        </a>
                                        <span class="crm-sync-since" :title="k.wissens_doc.updated_at" x-text="formatRelative(k.wissens_doc.updated_at)"></span>
                                    </div>
                                    <div class="crm-sync-meta">
                                        <strong x-text="k.wissens_doc.customer_name || 'Standard'"></strong> · <span x-text="k.wissens_doc.chunks + ' Chunks'"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!k.wissens_queued && (!k.wissens_doc || k.wissens_doc.is_active == 0)">
                                <div class="crm-sync-pill is-missing">
                                    <span class="material-symbols-rounded">cloud_off</span>
                                    <span>nicht in Wissensbasis</span>
                                </div>
                            </template>
                        </div>

                        <!-- Externe Systeme: immer beide Buttons, bei Fehlen ausgegraut -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">open_in_new</span>Externe Systeme</div>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <template x-if="k.legacy_zoho_id">
                                    <a :href="'https://crm.zoho.eu/crm/tab/Contacts/' + (k.legacy_zoho_id || '').replace(/^zcrm_/, '')"
                                       target="_blank" rel="noopener"
                                       class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Zoho öffnen
                                    </a>
                                </template>
                                <template x-if="!k.legacy_zoho_id">
                                    <span class="thx-shell-btn is-disabled" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;" title="Nicht mit Zoho verknüpft">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Zoho öffnen
                                    </span>
                                </template>
                                <template x-if="k.brevo_id">
                                    <a :href="'https://app.brevo.com/contact/index/' + k.brevo_id"
                                       target="_blank" rel="noopener"
                                       class="thx-shell-btn" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Brevo öffnen
                                    </a>
                                </template>
                                <template x-if="!k.brevo_id">
                                    <span class="thx-shell-btn is-disabled" style="display:flex;align-items:center;gap:6px;justify-content:flex-start;" title="Nicht mit Brevo verknüpft">
                                        <span class="material-symbols-rounded" style="font-size:16px;">north_east</span> In Brevo öffnen
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Meta -->
                        <div class="thx-shell-group">
                            <div class="thx-shell-group-label"><span class="material-symbols-rounded">info</span>Meta</div>
                            <dl style="margin:0;">
                                <div class="crm-stat-row"><dt>Angelegt</dt><dd x-text="formatDate(k.erstellt_am)"></dd></div>
                                <div class="crm-stat-row"><dt>Geändert</dt><dd x-text="formatDate(k.geaendert_am)"></dd></div>
                            </dl>
                        </div>

                    </div>
                </aside>

                <!-- ═════════════════ TOC (Inhaltsverzeichnis) ═════════════════ -->
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
                        <template x-if="tocSektionen.length === 0">
                            <div style="padding:14px 10px;color:var(--slate-400);font-size:0.74rem;text-align:center;">
                                <span x-show="!tocCollapsed">Noch keine Einträge.</span>
                            </div>
                        </template>
                    </nav>
                </aside>

                <!-- ─── RECHTE SPALTE (Tabs sind oben in der Topbar) ─── -->
                <main>
                    <!-- ─── TAB INFOS (konsolidiert: ehemals Infos + Alle Felder) ─── -->
                    <div x-show="tab==='infos'" class="crm-detail-tab-stack">

                        <div class="thx-card" id="sec-kontaktdaten">
                            <h2 class="crm-section-titel">Kontaktdaten</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['vorname','Vorname','text'],['nachname','Nachname','text'],['anrede','Anrede','text'],['titel','Titel','text'],['funktion','Funktion','text'],['abteilung','Abteilung','text'],['email_primaer','E-Mail','email'],['email_zweit','Zweite E-Mail','email'],['telefon','Tel.','tel'],['telefon_alt','Tel. (alt)','tel'],['mobil','Mobil','tel'],['fax','Fax','tel'],['website','Website','url'],['geburtsdatum','Geburtsdatum','date']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- Adressen — beide Typen (geschäftlich + privat) immer sichtbar -->
                        <div class="thx-card" id="sec-adressen">
                            <h2 class="crm-section-titel">Adressdaten</h2>
                            <template x-for="(typ, idx) in ['geschaeftlich','privat']" :key="typ">
                                <div :style="idx > 0 ? 'margin-top:14px;border-top:1px dashed var(--slate-200);padding-top:10px;' : ''">
                                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);margin-bottom:6px;font-weight:600;" x-text="typ === 'geschaeftlich' ? 'Geschäftlich' : 'Privat'"></div>
                                    <dl class="crm-fields">
                                        <template x-for="feld in [['strasse','Straße'],['plz','PLZ'],['stadt','Stadt'],['bundesland','Bundesland'],['land','Land']]" :key="typ + '-' + feld[0]">
                                            <div class="crm-field">
                                                <dt class="crm-field-label" x-text="feld[1]"></dt>
                                                <dd class="crm-field-wert">
                                                    <!-- Lesemodus -->
                                                    <template x-if="!editMode">
                                                        <span :class="['crm-readonly-wert', adresseWert(typ, feld[0]) ? '' : 'is-empty']"
                                                              x-text="adresseWert(typ, feld[0]) || '—'"></span>
                                                    </template>
                                                    <!-- Editmodus: Inline-Edit-Button -->
                                                    <template x-if="editMode && !istAdresseOffen(typ, feld[0])">
                                                        <button type="button" class="thx-inline-edit"
                                                                :class="adresseWert(typ, feld[0]) ? '' : 'is-empty'"
                                                                @click="oeffneAdresseEdit(typ, feld[0])"
                                                                x-text="adresseWert(typ, feld[0]) || '— setzen'"></button>
                                                    </template>
                                                    <!-- Editmodus: aktiver Editor -->
                                                    <template x-if="editMode && istAdresseOffen(typ, feld[0])">
                                                        <div class="thx-inline-edit-frame" @keydown.escape="schliesseAdresseEdit()">
                                                            <input type="text" class="thx-inline-edit-input" x-model="adresseEditWert"
                                                                   x-init="$el.focus()" @keydown.enter="speichereAdresseFeld(typ, feld[0])">
                                                            <div class="thx-inline-edit-actions">
                                                                <button type="button" class="thx-btn thx-btn-primary thx-btn-small" @click="speichereAdresseFeld(typ, feld[0])">✓</button>
                                                                <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseAdresseEdit()">×</button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </dd>
                                            </div>
                                        </template>
                                    </dl>
                                </div>
                            </template>
                        </div>

                        <!-- ─── Tags-Card (zwischen Adressen und Status) ─── -->
                        <div class="thx-card" id="sec-tags">
                            <h2 class="crm-section-titel">Tags <span class="crm-section-count" x-text="'(' + (k.tags||[]).length + ')'"></span></h2>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;" @click.outside="tagCombo.offen = false">
                                <template x-for="t in (k.tags||[])" :key="t.id">
                                    <span class="lam-chip" :style="t.farbe ? ('background:' + t.farbe + '20;color:' + t.farbe + ';border-color:' + t.farbe) : ''">
                                        <span x-text="t.name"></span>
                                        <button x-show="editMode" @click="entferneTag(t.id)" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:4px;opacity:0.6;" title="Tag entfernen">×</button>
                                    </span>
                                </template>
                                <template x-if="(k.tags||[]).length === 0">
                                    <span style="color:var(--slate-400);font-style:italic;font-size:0.85rem;">Keine Tags vergeben.</span>
                                </template>
                                <div style="position:relative;" x-show="editMode">
                                    <button class="lam-chip" @click.stop="tagCombo.offen = !tagCombo.offen; if(tagCombo.offen) ladeTagOptionen()" style="border-style:dashed;color:var(--slate-500);">+ Tag</button>
                                    <div x-show="tagCombo.offen" x-cloak class="crm-tag-combobox" @click.stop>
                                        <input type="text" x-model="tagCombo.suche" @input.debounce.150ms="ladeTagOptionen()" placeholder="Tag suchen oder neu …"
                                               x-init="$watch('tagCombo.offen', v => { if(v) $nextTick(() => $el.focus()); })"
                                               style="width:100%;padding:6px 8px;border:1px solid var(--slate-300);border-radius:4px;margin-bottom:6px;font-size:0.85rem;">
                                        <div style="max-height:240px;overflow-y:auto;">
                                            <template x-for="t in tagCombo.optionen" :key="t.id">
                                                <button @click="setzeTag(t.id)" class="lam-chip" style="margin:2px;display:inline-block;"
                                                        :style="t.farbe ? ('background:' + t.farbe + '20;color:' + t.farbe) : ''">
                                                    <span x-text="t.name"></span>
                                                    <span style="color:var(--slate-400);font-size:0.7rem;margin-left:3px;" x-text="'(' + t.anzahl_kontakte + ')'"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <template x-if="tagCombo.suche.trim() !== '' && !tagCombo.optionen.find(t => t.name.toLowerCase() === tagCombo.suche.toLowerCase())">
                                            <div style="margin-top:6px;padding-top:6px;border-top:1px solid var(--slate-200);">
                                                <button @click="legeNeuenTagAn()" class="thx-btn thx-btn-primary thx-btn-small" style="width:100%;">
                                                    + Neuen Tag „<span x-text="tagCombo.suche.trim()"></span>" anlegen
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ─── Firma & Ansprechpartner ─── -->
                        <div class="thx-card" id="sec-firma">
                            <h2 class="crm-section-titel">
                                Firma & Ansprechpartner
                                <span class="crm-section-count" x-show="k.firma_id" x-text="'(' + ((k.firma_kontakte||[]).length + 1) + ')'"></span>
                            </h2>
                            <!-- Mit Firma: Standard-Darstellung -->
                            <template x-if="k.firma_id">
                                <div>
                                    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;margin-bottom:10px;">
                                        <span class="material-symbols-rounded" style="color:var(--thoxan-600);">apartment</span>
                                        <a :href="'/crm/firmen/' + k.firma_id" style="font-weight:600;color:var(--slate-900);text-decoration:none;flex:1;" x-text="k.firmenname"></a>
                                        <a :href="'/crm/firmen/' + k.firma_id" style="color:var(--thoxan-600);font-size:0.78rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                            Firma öffnen <span class="material-symbols-rounded" style="font-size:14px;">north_east</span>
                                        </a>
                                    </div>
                                    <template x-if="(k.firma_kontakte||[]).length === 0">
                                        <div style="color:var(--slate-400);font-size:0.85rem;">Keine weiteren Ansprechpartner.</div>
                                    </template>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <template x-for="fk in (k.firma_kontakte||[])" :key="fk.id">
                                            <a :href="'/crm/kontakte/' + fk.id" class="crm-firma-kontakt" style="font-size:0.85rem;padding:8px 10px;">
                                                <span class="crm-firma-kontakt-avatar">
                                                    <template x-if="fk.foto_path"><img :src="fk.foto_path"></template>
                                                    <template x-if="!fk.foto_path">
                                                        <span x-text="((fk.vorname||'?')[0]||'') + ((fk.nachname||'?')[0]||'')"></span>
                                                    </template>
                                                </span>
                                                <div style="min-width:0;flex:1;">
                                                    <div class="crm-firma-kontakt-name" x-text="(fk.vorname||'') + ' ' + (fk.nachname||'')"></div>
                                                    <div x-show="fk.funktion" class="crm-firma-kontakt-funktion" x-text="fk.funktion"></div>
                                                </div>
                                                <span x-show="fk.email_primaer" style="color:var(--thoxan-600);font-size:0.78rem;" x-text="fk.email_primaer"></span>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Ohne Firma + Status „privat": klare Anzeige + Aufheben-Link -->
                            <template x-if="!k.firma_id && k.firma_status === 'ohne_firmenbezug'">
                                <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;">
                                    <span class="material-symbols-rounded" style="color:var(--slate-500);">person</span>
                                    <div style="flex:1;">
                                        <div style="font-weight:600;color:var(--slate-700);">Privater Kontakt</div>
                                        <div style="font-size:0.78rem;color:var(--slate-500);">Bewusst ohne Firma — wird vom Pflege-Workflow ignoriert.</div>
                                    </div>
                                    <button @click="setzeFirmaEntscheidung('reopen')" class="thx-btn thx-btn-secondary thx-btn-small">Doch zuordnen</button>
                                </div>
                            </template>

                            <!-- Ohne Firma + Status „Backlog": Hinweis + Direkt-Entscheidung -->
                            <template x-if="!k.firma_id && k.firma_status === 'pflege_offen'">
                                <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:6px;">
                                    <span class="material-symbols-rounded" style="color:var(--amber-700);">schedule</span>
                                    <div style="flex:1;">
                                        <div style="font-weight:600;color:var(--amber-800);">Im Pflege-Backlog</div>
                                        <div style="font-size:0.78rem;color:var(--amber-700);">Du wolltest später entscheiden — willst Du es jetzt klären?</div>
                                    </div>
                                    <button @click="setzeFirmaEntscheidung('reopen')" class="thx-btn thx-btn-secondary thx-btn-small">Jetzt entscheiden</button>
                                </div>
                            </template>

                            <!-- Ohne Firma + ohne Entscheidung: Action-Box mit Optionen -->
                            <template x-if="!k.firma_id && (!k.firma_status || k.firma_status === 'verknuepft' || firmaEntscheidung.offen)">
                                <div style="padding:14px;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:6px;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                                        <span class="material-symbols-rounded" style="color:var(--amber-700);">warning</span>
                                        <div style="font-weight:600;color:var(--amber-800);">Firma fehlt — bitte zuordnen oder klären</div>
                                    </div>

                                    <!-- Option 1: Firma suchen / anlegen -->
                                    <div style="position:relative;margin-bottom:10px;">
                                        <input type="text" class="thx-form-field" style="width:100%;"
                                               placeholder="Firma suchen oder Name eingeben um neu anzulegen …"
                                               x-model="firmaEntscheidung.suche"
                                               @input.debounce.250="ladeFirmenVorschlaegeFuerEntscheidung(firmaEntscheidung.suche)"
                                               @focus="firmaEntscheidung.dropdownOffen = true"
                                               @click.outside="firmaEntscheidung.dropdownOffen = false">
                                        <div x-show="firmaEntscheidung.dropdownOffen && (firmaEntscheidung.vorschlaege.length > 0 || firmaEntscheidung.suche.trim().length >= 2)"
                                             x-cloak
                                             style="position:absolute;top:100%;left:0;right:0;margin-top:2px;background:#fff;border:1px solid var(--slate-300);border-radius:4px;max-height:240px;overflow-y:auto;z-index:50;box-shadow:0 4px 12px rgba(0,0,0,0.12);">
                                            <template x-for="f in firmaEntscheidung.vorschlaege" :key="f.id">
                                                <button type="button" @click.stop="waehleFirmaEntscheidung(f)"
                                                        style="display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;cursor:pointer;font-size:0.85rem;border-bottom:1px solid var(--slate-100);"
                                                        onmouseover="this.style.background='var(--slate-50)'"
                                                        onmouseout="this.style.background='transparent'">
                                                    <strong x-text="f.firmenname"></strong>
                                                    <span x-show="f.branche" style="color:var(--slate-500);font-weight:normal;" x-text="' · ' + f.branche"></span>
                                                </button>
                                            </template>
                                            <button type="button" @click.stop="legeFirmaInEntscheidungAn()"
                                                    x-show="firmaEntscheidung.suche.trim().length >= 2"
                                                    :disabled="firmaEntscheidung.aktiv"
                                                    style="display:block;width:100%;text-align:left;padding:10px 12px;border:none;background:var(--thoxan-50);cursor:pointer;font-size:0.85rem;color:var(--thoxan-700);font-weight:600;border-top:1px solid var(--thoxan-100);">
                                                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add_business</span>
                                                <span x-show="!firmaEntscheidung.aktiv">Neue Firma „<span x-text="firmaEntscheidung.suche.trim()"></span>" anlegen</span>
                                                <span x-show="firmaEntscheidung.aktiv">… wird angelegt</span>
                                            </button>
                                            <div x-show="firmaEntscheidung.vorschlaege.length === 0 && firmaEntscheidung.suche.trim().length >= 2 && !firmaEntscheidung.aktiv" style="padding:8px 12px;font-size:0.75rem;color:var(--slate-500);">
                                                Keine bestehende Firma gefunden.
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Alternative Entscheidungen -->
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button @click="setzeFirmaStatus('ohne_firmenbezug')" class="thx-btn thx-btn-secondary thx-btn-small" title="Privater Kontakt — bewusst keine Firma">
                                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">person</span>
                                            Hat keine Firma
                                        </button>
                                        <button @click="setzeFirmaStatus('pflege_offen')" class="thx-btn thx-btn-secondary thx-btn-small" title="In den Pflege-Backlog verschieben">
                                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">schedule</span>
                                            Später entscheiden
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="thx-card" id="sec-status">
                            <h2 class="crm-section-titel">Kontakt-Status</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['kontakt_status','Kontakt-Status','select-status'],['lead_quelle','Lead-Quelle','text'],['opt_in_status','Opt-In-Status','select-optin'],['thx_score','THX-Score','number'],['bevorzugtes_thema','Bevorzugtes Thema','text']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <div class="thx-card" id="sec-listen">
                            <h2 class="crm-section-titel">Aktive Listen <span class="crm-section-count" x-text="'(' + (k.listen||[]).filter(l => l.status === 'aktiv').length + ')'"></span></h2>
                            <dl class="crm-fields" x-show="(k.listen||[]).length > 0">
                                <template x-for="l in (k.listen||[])" :key="l.id">
                                    <div class="crm-field">
                                        <dt class="crm-field-label" x-text="l.name"></dt>
                                        <dd class="crm-field-wert">
                                            <template x-if="l.status === 'aktiv'"><span style="color:var(--emerald-700);font-weight:600;">X</span></template>
                                            <template x-if="l.status !== 'aktiv'"><span style="color:var(--slate-400);">—</span></template>
                                        </dd>
                                    </div>
                                </template>
                            </dl>
                            <div x-show="(k.listen||[]).length === 0" style="color:var(--slate-400);font-size:0.82rem;">Keine Listen-Mitgliedschaften.</div>
                        </div>

                        <!-- Social Media — alle 6 Plattformen, immer sichtbar (auch wenn leer) -->
                        <div class="thx-card" id="sec-social">
                            <h2 class="crm-section-titel">Social Media</h2>
                            <dl class="crm-fields">
                                <template x-for="plattform in ['LinkedIn','XING','Facebook','Instagram','Twitter','YouTube']" :key="plattform">
                                    <div class="crm-field">
                                        <dt class="crm-field-label" x-text="plattform"></dt>
                                        <dd class="crm-field-wert">
                                            <!-- Lesemodus: Link wenn vorhanden, sonst — -->
                                            <template x-if="!editMode">
                                                <template x-if="socialUrl(plattform)">
                                                    <a :href="socialUrl(plattform)" target="_blank" rel="noopener" x-text="socialUrl(plattform)"></a>
                                                </template>
                                            </template>
                                            <template x-if="!editMode && !socialUrl(plattform)">
                                                <span class="crm-readonly-wert is-empty">—</span>
                                            </template>
                                            <!-- Editmodus: Inline-Edit-Button -->
                                            <template x-if="editMode && !istSocialOffen(plattform)">
                                                <button type="button" class="thx-inline-edit"
                                                        :class="socialUrl(plattform) ? '' : 'is-empty'"
                                                        @click="oeffneSocialEdit(plattform)"
                                                        x-text="socialUrl(plattform) || '— setzen'"></button>
                                            </template>
                                            <!-- Editmodus: aktiver Editor -->
                                            <template x-if="editMode && istSocialOffen(plattform)">
                                                <div class="thx-inline-edit-frame" @keydown.escape="schliesseSocialEdit()">
                                                    <input type="url" class="thx-inline-edit-input" x-model="socialEditWert"
                                                           x-init="$el.focus()" @keydown.enter="speichereSocial(plattform)"
                                                           placeholder="https://...">
                                                    <div class="thx-inline-edit-actions">
                                                        <button type="button" class="thx-btn thx-btn-primary thx-btn-small" @click="speichereSocial(plattform)">✓</button>
                                                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" @click="schliesseSocialEdit()">×</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </dd>
                                    </div>
                                </template>
                            </dl>
                        </div>

                        <!-- Verkaufschance — immer sichtbar -->
                        <div class="thx-card" id="sec-verkauf">
                            <h2 class="crm-section-titel">Verkaufschance</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['asana_task_gid','Asana-Task','text'],['deal_wert','Deal-Wert (€)','number'],['deal_stufe','Deal-Stufe','text']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- Lead-Magnet-Infos — immer sichtbar -->
                        <div class="thx-card" id="sec-leadmagnet">
                            <h2 class="crm-section-titel">Lead-Magnet</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['lead_magnet_name','Name','text'],['lead_magnet_url','URL','url']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- Wunschkunden-Podcast — immer sichtbar -->
                        <div class="thx-card" id="sec-podcast">
                            <h2 class="crm-section-titel">Wunschkunden-Podcast</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['podcast_titel','Titel','text'],['podcast_subtitel','Subtitel','text'],['podcast_release_datum','Release-Datum','date'],['podcast_release_url','Release-URL','url'],['podcast_release_mail','Release-Mail','email']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- UTM-Tracking — immer sichtbar -->
                        <div class="thx-card" id="sec-utm">
                            <h2 class="crm-section-titel">UTM-Tracking &amp; Herkunft</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['utm_source','utm_source','text'],['utm_medium','utm_medium','text'],['utm_campaign','utm_campaign','text'],['utm_content','utm_content','text'],['utm_term','utm_term','text'],['herkunft_referrer','Herkunft / Referrer','url']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- Trigger & Sync -->
                        <div class="thx-card" id="sec-trigger">
                            <h2 class="crm-section-titel">Trigger &amp; Sync</h2>
                            <dl class="crm-fields">
                                <template x-for="f in [
                                    ['ac_sync','AC Sync'],
                                    ['trigger_kontaktformular','Trigger Kontaktformular'],
                                    ['trigger_terminbuchung','Trigger Terminbuchung'],
                                    ['trigger_strategie_check','Trigger Strategie-Check'],
                                    ['trigger_lead_magnet','Trigger Lead-Magnet'],
                                    ['trigger_test','Test-Trigger']
                                ]" :key="f[0]">
                                    <div class="crm-field">
                                        <dt class="crm-field-label" x-text="f[1]"></dt>
                                        <dd class="crm-field-wert">
                                            <button type="button" class="thx-inline-edit" @click="toggleBool(f[0])"
                                                    :style="k[f[0]] == 1 ? 'color:var(--emerald-700);font-weight:600;' : 'color:var(--slate-400);'"
                                                    x-text="k[f[0]] == 1 ? '✓ aktiv' : '— inaktiv'"></button>
                                        </dd>
                                    </div>
                                </template>
                                <?php $felderJs = "[['stand_datensatz','Stand Datensatz','text'],['layout_name','Layout','text'],['kuendigungsoption','E-Mail-Kündigungsoption','text']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                        <!-- Profil & Notizen (textareas — bewusst am Ende) -->
                        <div class="thx-card" id="sec-profil">
                            <h2 class="crm-section-titel">Profil &amp; Notizen</h2>
                            <dl class="crm-fields">
                                <?php $felderJs = "[['interessen','Interessen','textarea'],['merkmale','Merkmale','textarea'],['beschreibung','Beschreibung','textarea']]"; include __DIR__ . '/_kontakt-feld-snippet.php'; ?>
                            </dl>
                        </div>

                    </div>

                    <!-- ─── TAB E-MAILS (Brevo-Events nach Kampagne gruppiert) ─── -->
                    <div x-show="tab==='emails'" class="crm-detail-tab-stack">
                        <template x-if="(k.mails||[]).length === 0">
                            <div class="thx-card" style="padding:30px;text-align:center;color:var(--slate-400);">
                                Noch keine E-Mail-Events. Sobald Brevo Sends/Opens/Clicks meldet, erscheinen sie hier.
                            </div>
                        </template>
                        <template x-for="(m, idx) in (k.mails||[])" :key="idx">
                            <div class="thx-card" :id="'mail-' + idx">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
                                    <div style="min-width:0;flex:1;">
                                        <h2 class="crm-section-titel" style="margin:0;border:0;padding:0;" x-text="m.campaign_name"></h2>
                                        <div style="font-size:0.78rem;color:var(--slate-500);margin-top:4px;">
                                            <span x-show="m.campaign_id">Kampagne #<span x-text="m.campaign_id"></span> · </span>
                                            Erstes Event <span x-text="formatDate(m.erster_event)"></span>
                                            <span x-show="m.letzter_event !== m.erster_event">
                                                · Letztes <span x-text="formatDate(m.letzter_event)"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;">
                                    <div class="crm-mail-stat" x-show="+m.n_sent > 0"><div class="crm-mail-stat-n" x-text="m.n_sent"></div><div class="crm-mail-stat-l">Versendet</div></div>
                                    <div class="crm-mail-stat" x-show="+m.n_delivered > 0"><div class="crm-mail-stat-n" x-text="m.n_delivered"></div><div class="crm-mail-stat-l">Zugestellt</div></div>
                                    <div class="crm-mail-stat crm-mail-stat-good" x-show="+m.n_open > 0"><div class="crm-mail-stat-n" x-text="m.n_open"></div><div class="crm-mail-stat-l">Geöffnet</div></div>
                                    <div class="crm-mail-stat crm-mail-stat-good" x-show="+m.n_click > 0"><div class="crm-mail-stat-n" x-text="m.n_click"></div><div class="crm-mail-stat-l">Klicks</div></div>
                                    <div class="crm-mail-stat crm-mail-stat-bad" x-show="+m.n_bounce > 0"><div class="crm-mail-stat-n" x-text="m.n_bounce"></div><div class="crm-mail-stat-l">Bounce</div></div>
                                    <div class="crm-mail-stat crm-mail-stat-bad" x-show="+m.n_unsubscribe > 0"><div class="crm-mail-stat-n" x-text="m.n_unsubscribe"></div><div class="crm-mail-stat-l">Abmeldung</div></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ─── TAB ZEITLINIE (mit Monats-Anchors für TOC) ─── -->
                    <div x-show="tab==='zeitlinie'" class="crm-detail-tab-stack">
                        <div class="thx-card">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <h2 class="crm-section-titel" style="margin:0;border:0;padding:0;">Zeitlinienverlauf</h2>
                                <div style="display:flex;gap:6px;">
                                    <button class="thx-shell-btn" @click="oeffneNotizDialog()" style="display:inline-flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:15px;">edit_note</span>Notiz</button>
                                    <button class="thx-shell-btn" @click="ladeAktivitaeten()" title="Neu laden"><span class="material-symbols-rounded" style="font-size:15px;">refresh</span></button>
                                </div>
                            </div>
                            <div class="crm-timeline">
                                <template x-for="(monatsGruppe, monatsKey) in aktivitaetenNachMonat()" :key="monatsKey">
                                    <div :id="'zeit-' + monatsKey" style="scroll-margin-top:80px;">
                                        <div class="crm-timeline-datum" style="font-size:0.85rem;background:var(--thoxan-50);color:var(--thoxan-700);padding:6px 14px;" x-text="monatsGruppe.label"></div>
                                        <template x-for="a in monatsGruppe.eintraege" :key="a.id">
                                            <div class="crm-timeline-eintrag">
                                                <div class="crm-timeline-zeit" x-text="formatTagZeit(a.erstellt_am)"></div>
                                                <div class="crm-timeline-icon" x-text="iconTyp(a.typ)"></div>
                                                <div class="crm-timeline-inhalt">
                                                    <div style="font-weight:500;color:var(--slate-900);">
                                                        <span x-text="formatTyp(a.typ)"></span>
                                                        <template x-if="a.titel"><span> · <span x-text="a.titel"></span></span></template>
                                                    </div>
                                                    <div x-show="a.inhalt" style="color:var(--slate-700);margin-top:2px;white-space:pre-wrap;" x-text="a.inhalt"></div>
                                                    <div style="font-size:0.7rem;color:var(--slate-400);margin-top:3px;" x-text="a.quelle"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="aktivitaeten.length === 0">
                                    <div style="color:var(--slate-400);font-size:0.85rem;padding:20px;text-align:center;">Noch keine Aktivitäten.</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </template>

    <!-- ═══════════════ NOTIZ-DIALOG ═══════════════ -->
    <div x-show="notizDialog.offen" x-cloak class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;" @click.self="notizDialog.offen = false">
        <div class="thx-modal" style="max-width:560px;">
            <div style="padding:14px 22px;border-bottom:1px solid var(--slate-200);"><h3 style="margin:0;font-size:1rem;">+ Notiz hinzufügen</h3></div>
            <div style="padding:14px 22px;">
                <textarea x-model="notizDialog.text" rows="5" placeholder="Notiz, Telefonat, Meeting-Inhalt …" x-init="$el.focus()"
                          style="width:100%;padding:8px;border:1px solid var(--slate-300);border-radius:6px;font-family:inherit;"></textarea>
                <div style="margin-top:8px;display:flex;gap:6px;">
                    <label style="font-size:0.8rem;color:var(--slate-500);">Typ:</label>
                    <select x-model="notizDialog.typ" style="padding:3px 8px;border:1px solid var(--slate-300);border-radius:4px;">
                        <option value="notiz">Notiz</option><option value="telefonat">Telefonat</option><option value="meeting">Meeting</option><option value="sonstiges">Sonstiges</option>
                    </select>
                </div>
            </div>
            <div style="padding:10px 22px;border-top:1px solid var(--slate-200);display:flex;justify-content:flex-end;gap:6px;">
                <button class="thx-btn thx-btn-secondary" @click="notizDialog.offen = false">Abbrechen</button>
                <button class="thx-btn thx-btn-primary" @click="speichereNotiz()" :disabled="!notizDialog.text.trim()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<style>
.crm-detail-root { padding-bottom: 40px; }

/* ─── Grid ─── */
.crm-detail-grid { display:grid; grid-template-columns:240px 1fr; gap:14px; align-items:start; }
@media (max-width: 900px) { .crm-detail-grid { grid-template-columns: 1fr; } }

/* ─── Sidebar (alle Cards übernehmen .thx-card-Optik) ─── */
.crm-sidebar { display:flex; flex-direction:column; gap:10px; }
.crm-sidebar-card { padding:14px; text-align:center; }
.crm-sidebar-cardtitel { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--slate-400); }

.crm-avatar-wrap { position:relative; display:inline-block; cursor:pointer; }
.crm-avatar-img { width:130px; height:130px; border-radius:50%; object-fit:cover; border:3px solid var(--thoxan-100); display:block; }
.crm-avatar-fallback { width:130px; height:130px; border-radius:50%; background:var(--thoxan-100); color:var(--thoxan-700); display:inline-flex; align-items:center; justify-content:center; font-size:2.8rem; font-weight:600; border:3px solid var(--thoxan-100); }
.crm-avatar-badge { position:absolute; bottom:6px; right:6px; background:var(--thoxan-600); color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; border:2px solid #fff; }
.crm-sidebar-name { font-weight:600; font-size:1.05rem; color:var(--slate-900); margin-top:10px; }
.crm-sidebar-firma { display:block; color:var(--slate-500); font-size:0.85rem; margin-top:2px; text-decoration:none; }
.crm-sidebar-firma:hover { color:var(--thoxan-600); }

/* Opt-In Big-Badge — Thoxan-Farben statt Zoho-Grün */
.crm-bigbadge { padding:14px 12px; border-radius:6px; text-align:center; }
.crm-bigbadge-doi  { background:var(--emerald-50); color:var(--emerald-700); border:1px solid var(--emerald-200); }
.crm-bigbadge-soi  { background:var(--thoxan-50); color:var(--thoxan-700); border:1px solid var(--thoxan-200); }
.crm-bigbadge-pend { background:var(--amber-50); color:var(--amber-800); border:1px solid var(--amber-200); }
.crm-bigbadge-aus  { background:var(--rose-50); color:var(--rose-700); border:1px solid var(--rose-200); }
.crm-bigbadge-hb   { background:var(--rose-50); color:var(--rose-700); border:1px solid var(--rose-200); }
.crm-bigbadge-leer { background:var(--slate-100); color:var(--slate-500); border:1px solid var(--slate-200); }
.crm-bigbadge-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em; opacity:0.75; }
.crm-bigbadge-wert  { font-size:1.05rem; font-weight:600; margin-top:3px; }

/* Score-Bar */
.crm-scorebar { height:6px; background:var(--slate-100); border-radius:3px; margin-top:8px; overflow:hidden; }
.crm-scorebar-fill { height:100%; background:linear-gradient(to right, var(--amber-400), var(--emerald-500)); }

.crm-sidebar-meta { font-size:0.7rem; color:var(--slate-400); padding:6px 4px; line-height:1.6; text-align:center; }

/* ─── Section-Titel (innerhalb thx-card) ─── */
.crm-section-titel { font-size:0.92rem; font-weight:600; color:var(--slate-900); margin:0 0 12px 0; padding-bottom:8px; border-bottom:1px solid var(--slate-100); }
.crm-section-count { color:var(--slate-400); font-weight:400; font-size:0.8rem; margin-left:4px; }

/* ─── Felder-Grid (2 Spalten wie Zoho) ─── */
.crm-fields { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:4px 28px; margin:0; }
@media (max-width: 700px) { .crm-fields { grid-template-columns: 1fr; } }
.crm-field { display:grid; grid-template-columns:140px 1fr; gap:8px; align-items:baseline; padding:5px 0; border-bottom:1px solid var(--slate-50); min-height:30px; }
.crm-field-wide { grid-column: 1 / -1; }
.crm-field-label { color:var(--slate-500); font-size:0.82rem; margin:0; font-weight:400; }
.crm-field-wert { margin:0; color:var(--slate-900); font-weight:500; font-size:0.85rem; min-width:0; word-wrap:break-word; }
.crm-field-wert a { color:var(--thoxan-600); text-decoration:none; }
.crm-field-wert a:hover { text-decoration:underline; }

/* ─── Inline-Edit ─── */
.thx-inline-edit { background:none; border:1px dashed transparent; padding:2px 6px; border-radius:3px; cursor:pointer; font:inherit; color:inherit; text-align:left; max-width:100%; white-space:normal; word-break:break-word; }
.thx-inline-edit:hover { border-color:var(--thoxan-300); background:var(--thoxan-50); }
.thx-inline-edit.is-empty { color:var(--slate-400); font-style:italic; font-weight:400; }
.thx-inline-edit-frame { display:flex; gap:3px; align-items:center; flex-wrap:wrap; }
.thx-inline-edit-frame.is-stacked { flex-direction:column; align-items:stretch; }
.thx-inline-edit-input, .thx-inline-edit-select { padding:3px 6px; border:1px solid var(--thoxan-400); border-radius:4px; font:inherit; font-size:0.85rem; max-width:100%; }
.thx-inline-edit-actions { display:flex; gap:3px; margin-top:4px; justify-content:flex-end; align-items:center; }

/* ─── Dropdown-Menu ─── */
.crm-dropdown { position:absolute; top:100%; right:0; margin-top:4px; background:#fff; border:1px solid var(--slate-300); border-radius:6px; box-shadow:0 6px 20px rgba(0,0,0,0.12); padding:4px; min-width:240px; z-index:100; }
.crm-dropdown-item { display:block; width:100%; text-align:left; padding:6px 10px; background:none; border:0; cursor:pointer; font:inherit; color:inherit; border-radius:4px; text-decoration:none; }
.crm-dropdown-item:hover { background:var(--thoxan-50); }
.crm-dropdown-sep { border-top:1px solid var(--slate-200); margin:3px 0; }

/* ─── Tag-Combobox ─── */
.crm-tag-combobox { position:absolute; top:100%; left:0; margin-top:4px; background:#fff; border:1px solid var(--slate-300); border-radius:6px; box-shadow:0 6px 20px rgba(0,0,0,0.12); padding:8px; width:280px; z-index:50; }

/* ─── Timeline ─── */
.crm-timeline { padding:4px; }
.crm-timeline-datum { display:inline-block; padding:3px 12px; background:var(--slate-100); color:var(--slate-700); font-weight:500; font-size:0.78rem; border-radius:12px; margin:14px 0 6px 0; }
.crm-timeline-eintrag { display:grid; grid-template-columns:60px 24px 1fr; gap:8px; padding:8px 0; border-left:2px solid var(--slate-200); padding-left:14px; margin-left:30px; }
.crm-timeline-zeit { color:var(--slate-500); font-size:0.78rem; font-variant-numeric:tabular-nums; text-align:right; }
.crm-timeline-icon { width:24px; height:24px; border-radius:50%; background:var(--thoxan-100); color:var(--thoxan-700); display:flex; align-items:center; justify-content:center; font-size:0.8rem; }
.crm-timeline-inhalt { font-size:0.85rem; }
</style>

<script>
function crmKontaktDetail(id) {
    return {
        kontaktId: id,
        laedt: true, k: null, aktivitaeten: [],
        tab: 'infos',
        mehrMenu: false,
        editMode: localStorage.getItem('crm_kontakt_edit_mode') === '1', // sticky: zuletzt gewählter Modus kontaktübergreifend
        editFeld: null, editTyp: null, editWert: '',
        adresseEditTyp: null, adresseEditFeld: null, adresseEditWert: '',
        socialEditPlatform: null, socialEditWert: '',
        tagCombo: { offen: false, suche: '', optionen: [] },
        notizDialog: { offen: false, text: '', typ: 'notiz' },
        // Firma-Entscheidung (nur sichtbar wenn firma_id leer + Status unklar)
        firmaEntscheidung: { offen: false, suche: '', vorschlaege: [], dropdownOffen: false, aktiv: false },

        // TOC (Inhaltsverzeichnis) + Scroll-Spy
        tocCollapsed: localStorage.getItem('crm_toc_collapsed') === '1',
        aktiveSektion: 'sec-kontaktdaten',
        tocInfosSektionen: [
            { id: 'sec-kontaktdaten', label: 'Kontaktdaten', icon: 'badge' },
            { id: 'sec-adressen',     label: 'Adressdaten',  icon: 'pin_drop' },
            { id: 'sec-tags',         label: 'Tags',         icon: 'sell' },
            { id: 'sec-firma',        label: 'Firma',        icon: 'apartment' },
            { id: 'sec-status',       label: 'Status',       icon: 'flag' },
            { id: 'sec-listen',       label: 'Listen',       icon: 'format_list_bulleted' },
            { id: 'sec-social',       label: 'Social Media', icon: 'public' },
            { id: 'sec-verkauf',      label: 'Verkaufschance', icon: 'euro' },
            { id: 'sec-leadmagnet',   label: 'Lead-Magnet',  icon: 'flag_circle' },
            { id: 'sec-podcast',      label: 'Podcast',      icon: 'mic' },
            { id: 'sec-utm',          label: 'UTM-Tracking', icon: 'analytics' },
            { id: 'sec-trigger',      label: 'Trigger & Sync', icon: 'sync' },
            { id: 'sec-profil',       label: 'Profil & Notizen', icon: 'description' },
        ],
        // Dynamisches TOC je nach aktivem Tab
        get tocSektionen() {
            if (this.tab === 'emails') {
                return (this.k?.mails || []).map((m, i) => ({
                    id: 'mail-' + i,
                    label: m.campaign_name,
                    icon: 'mail'
                }));
            }
            if (this.tab === 'zeitlinie') {
                // Pro Monat einen Eintrag
                const monate = new Map();
                (this.aktivitaeten || []).forEach(a => {
                    if (!a.erstellt_am) return;
                    const dt = new Date(a.erstellt_am.replace(' ', 'T'));
                    const key = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2, '0');
                    if (!monate.has(key)) {
                        monate.set(key, {
                            id: 'zeit-' + key,
                            label: dt.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' }),
                            icon: 'event',
                            count: 0
                        });
                    }
                    monate.get(key).count++;
                });
                return Array.from(monate.values());
            }
            return this.tocInfosSektionen;
        },
        _spyLock: false,
        springZuSektion(id) {
            const el = document.getElementById(id);
            if (!el) return;
            this.aktiveSektion = id;
            this._spyLock = true;
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => { this._spyLock = false; }, 700);
        },
        // Scrollt zum Page-Anfang. Im embed-Modus (iframe) scrollt nicht window,
        // sondern .main-content (overflow:auto) — beide rufen schadet nicht.
        springZumAnfang() {
            this._spyLock = true;
            if (this.tocSektionen[0]) this.aktiveSektion = this.tocSektionen[0].id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.querySelector('.main-content')?.scrollTo({ top: 0, behavior: 'smooth' });
            document.documentElement.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => { this._spyLock = false; }, 600);
        },
        // Pfeil-Navigation im TOC (Up/Down)
        navigiereTOC(delta) {
            const sektionen = this.tocSektionen;
            if (!sektionen.length) return;
            const idx = sektionen.findIndex(s => s.id === this.aktiveSektion);
            const next = Math.max(0, Math.min(sektionen.length - 1, (idx < 0 ? 0 : idx + delta)));
            if (sektionen[next]) {
                this.springZuSektion(sektionen[next].id);
                // Fokus auf das gewählte Item (für nächsten Pfeildruck)
                this.$nextTick(() => {
                    const btn = document.querySelector('[data-sektion="' + sektionen[next].id + '"]');
                    if (btn) btn.focus({ preventScroll: true });
                });
            }
        },
        _observer: null,
        initScrollSpy() {
            // Bei jedem Tab-Wechsel neu initialisieren
            if (this._observer) this._observer.disconnect();
            const opts = { root: null, rootMargin: '-80px 0px -60% 0px', threshold: 0 };
            this._observer = new IntersectionObserver((entries) => {
                if (this._spyLock) return;  // während Smooth-Scroll nicht reagieren
                const sichtbar = entries.filter(e => e.isIntersecting).sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (sichtbar.length > 0) this.aktiveSektion = sichtbar[0].target.id;
            }, opts);
            this.$nextTick(() => {
                this.tocSektionen.forEach(s => {
                    const el = document.getElementById(s.id);
                    if (el) this._observer.observe(el);
                });
                // Reset auf erste Sektion beim Tab-Wechsel
                if (this.tocSektionen[0]) this.aktiveSektion = this.tocSektionen[0].id;
                this.setzePaddingBottom();
            });
        },
        // Dynamisches padding-bottom: genau so groß, dass die LETZTE Card bis ganz
        // oben scrollen kann, ohne überflüssigen Whitespace.
        // Formel: viewport - letzte-card-höhe - topbar - gutter
        setzePaddingBottom() {
            const main = document.querySelector('.crm-detail-grid3 > main');
            if (!main) return;
            // Nur sichtbare Cards zählen — versteckte Cards aus inaktiven Tabs
            // (display:none via x-show) haben height=0 und würden Rechnung verfälschen.
            const cards = Array.from(main.querySelectorAll('.thx-card'))
                .filter(c => c.offsetParent !== null);
            if (!cards.length) { main.style.paddingBottom = '0'; return; }
            const letzteCard = cards[cards.length - 1];
            const cardHoehe = letzteCard.getBoundingClientRect().height;
            const topbarH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--topbar-h')) || 44;
            const offset = topbarH + 18; // = scroll-margin-top
            const padding = Math.max(0, window.innerHeight - cardHoehe - offset);
            main.style.paddingBottom = padding + 'px';
        },
        wechsleTab(neuerTab) {
            this.tab = neuerTab;
            if (neuerTab === 'zeitlinie') this.ladeAktivitaeten();
            this.$nextTick(() => this.initScrollSpy());
        },

        async laden() {
            this.laedt = true;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.k = j.data;
            } catch (e) {}
            this.laedt = false;
            if (this.k) {
                this.ladeAktivitaeten();
                this.initScrollSpy();
                window.addEventListener('resize', () => this.setzePaddingBottom());
                // Wechsel in den Lesemodus → ggf. offenen Editor schließen + Modus persistieren
                this.$watch('editMode', v => {
                    if (!v) this.schliesseEdit();
                    try { localStorage.setItem('crm_kontakt_edit_mode', v ? '1' : '0'); } catch (e) {}
                    // Anderen Tabs / dem Parent-Drawer den neuen Modus mitteilen
                    try { window.parent && window.parent !== window && window.parent.postMessage({ type: 'crm:editModeChanged', value: v }, window.location.origin); } catch (e) {}
                });
                // Parent-Drawer-Steuerung: Edit-Mode kann vom Drawer-Header per
                // postMessage umgeschaltet werden.
                window.addEventListener('message', (ev) => {
                    if (ev.origin !== window.location.origin) return;
                    if (ev.data?.type === 'crm:setEditMode') {
                        this.editMode = !!ev.data.value;
                    }
                });
                // Wenn ein anderer Tab/Frame den Modus ändert, mitziehen (kontaktübergreifend konsistent)
                window.addEventListener('storage', (ev) => {
                    if (ev.key === 'crm_kontakt_edit_mode' && ev.newValue !== null) {
                        const neu = ev.newValue === '1';
                        if (this.editMode !== neu) this.editMode = neu;
                    }
                });
            }
        },
        // ──── Firma-Entscheidung (wenn firma_id leer) ────
        async ladeFirmenVorschlaegeFuerEntscheidung(query) {
            if (!query || query.length < 2) { this.firmaEntscheidung.vorschlaege = []; return; }
            try {
                const r = await fetch('/api/v1/crm/firmen?suche=' + encodeURIComponent(query) + '&limit=8', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.firmaEntscheidung.vorschlaege = j.data.eintraege || [];
            } catch (e) {}
        },
        async waehleFirmaEntscheidung(f) {
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: 'firma_id', wert: f.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Auch firma_status auf verknuepft setzen
                await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: 'firma_status', wert: 'verknuepft' })
                });
                this.k.firma_id = f.id;
                this.k.firmenname = f.firmenname;
                this.k.firma_status = 'verknuepft';
                this.firmaEntscheidung = { offen: false, suche: '', vorschlaege: [], dropdownOffen: false, aktiv: false };
                App.showNotification('Firma „' + f.firmenname + '" zugewiesen.', 'success');
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
        },
        async legeFirmaInEntscheidungAn() {
            const name = this.firmaEntscheidung.suche.trim();
            if (name.length < 2 || this.firmaEntscheidung.aktiv) return;
            this.firmaEntscheidung.aktiv = true;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=quick_firma', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firmenname: name, kontakt_id: this.kontaktId })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.k.firma_id = j.data.firma_id;
                this.k.firmenname = name;
                this.k.firma_status = 'verknuepft';
                this.firmaEntscheidung = { offen: false, suche: '', vorschlaege: [], dropdownOffen: false, aktiv: false };
                App.showNotification('Firma „' + name + '" angelegt und zugewiesen.', 'success');
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.firmaEntscheidung.aktiv = false;
        },
        async setzeFirmaStatus(status) {
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: 'firma_status', wert: status })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.k.firma_status = status;
                this.firmaEntscheidung.offen = false;
                const labels = { ohne_firmenbezug: 'Privater Kontakt — gespeichert.', pflege_offen: 'Im Pflege-Backlog gemerkt.' };
                App.showNotification(labels[status] || 'Status gesetzt.', 'success');
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
        },
        setzeFirmaEntscheidung(modus) {
            // „reopen" aus einem festen Status zurück in die Auswahl
            if (modus === 'reopen') this.firmaEntscheidung.offen = true;
        },

        async ladeAktivitaeten() {
            const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/aktivitaeten?limit=50');
            const j = await r.json();
            if (j.success) this.aktivitaeten = j.data.aktivitaeten || [];
        },
        aktivitaetenGruppiert() {
            const g = {};
            for (const a of this.aktivitaeten) {
                const d = (a.erstellt_am || '').split(' ')[0];
                if (!d) continue;
                const dt = new Date(d);
                const key = dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric' });
                if (!g[key]) g[key] = [];
                g[key].push(a);
            }
            return g;
        },
        // Nach Monat gruppiert für Zeitlinie-Anchors
        aktivitaetenNachMonat() {
            const g = {};
            for (const a of this.aktivitaeten) {
                if (!a.erstellt_am) continue;
                const dt = new Date(a.erstellt_am.replace(' ', 'T'));
                const key = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2, '0');
                if (!g[key]) g[key] = { label: dt.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' }), eintraege: [] };
                g[key].eintraege.push(a);
            }
            return g;
        },
        formatTagZeit(d) {
            if (!d) return '';
            const dt = new Date(d.replace(' ', 'T'));
            return dt.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit' }) + ' ' + dt.toLocaleTimeString('de-DE', { hour:'2-digit', minute:'2-digit' });
        },

        // Inline-Edit
        istOffen(feld) { return this.editFeld === feld; },
        oeffneEdit(feld, typ) {
            this.editFeld = feld;
            this.editTyp = typ;
            this.editWert = this.k[feld] ?? '';
        },
        schliesseEdit() {
            this.editFeld = null; this.editTyp = null; this.editWert = '';
            this.adresseEditTyp = null; this.adresseEditFeld = null; this.adresseEditWert = '';
            this.socialEditPlatform = null; this.socialEditWert = '';
        },
        async speichern() {
            if (!this.editFeld) return;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: this.editFeld, wert: this.editWert })
                });
                const j = await r.json();
                if (j.success) {
                    this.k[this.editFeld] = this.editWert === '' ? null : this.editWert;
                    this.schliesseEdit();
                    this.ladeAktivitaeten();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        // Boolean-Toggle (für Trigger/AC-Sync)
        async toggleBool(feld) {
            const neu = this.k[feld] == 1 ? 0 : 1;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld, wert: neu })
                });
                const j = await r.json();
                if (j.success) { this.k[feld] = neu; this.ladeAktivitaeten(); }
                else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        formatFeldwert(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '— setzen';
            if (feld === 'kontakt_status') return this.formatStatus(wert);
            if (feld === 'opt_in_status') return this.formatOptIn(wert);
            if (feld === 'deal_wert') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },
        // Lese-Modus: leere Werte als „—" (statt „— setzen" das eine Edit-Aktion suggeriert)
        formatFeldwertLese(feld, typ, wert) {
            if (wert === null || wert === undefined || wert === '') return '—';
            if (feld === 'kontakt_status') return this.formatStatus(wert);
            if (feld === 'opt_in_status') return this.formatOptIn(wert);
            if (feld === 'deal_wert') return Number(wert||0).toLocaleString('de-DE') + ' €';
            return wert;
        },
        // Opt-In-Pill-Klasse für linke Sidebar
        sidePillClass() {
            return ({pending:'crm-side-pill-pend', single_opted_in:'crm-side-pill-soi', double_opted_in:'crm-side-pill-doi', unsubscribed:'crm-side-pill-aus', hard_bounce:'crm-side-pill-aus'})[this.k?.opt_in_status] || '';
        },
        // Helper für Social-Media: URL eines bestimmten Plattform-Namens
        socialUrl(plattform) {
            const liste = this.k?.social || [];
            const treffer = liste.find(s => (s.plattform || '').toLowerCase() === plattform.toLowerCase());
            return treffer ? treffer.url : null;
        },
        // Helper für Adressen: Wert eines bestimmten Feldes für einen Typ
        adresseWert(typ, feld) {
            const liste = this.k?.adressen || [];
            const treffer = liste.find(a => a.typ === typ);
            return treffer ? treffer[feld] : null;
        },
        // Inline-Edit für Adressen
        istAdresseOffen(typ, feld) {
            return this.adresseEditTyp === typ && this.adresseEditFeld === feld;
        },
        oeffneAdresseEdit(typ, feld) {
            this.adresseEditTyp = typ;
            this.adresseEditFeld = feld;
            this.adresseEditWert = this.adresseWert(typ, feld) || '';
        },
        schliesseAdresseEdit() {
            this.adresseEditTyp = null;
            this.adresseEditFeld = null;
            this.adresseEditWert = '';
        },
        async speichereAdresseFeld(typ, feld) {
            const liste = this.k?.adressen || [];
            const bestehend = liste.find(a => a.typ === typ);
            // Vorhandene Felder uebernehmen + neues setzen, sonst Feld leer überschreiben
            const body = {
                id: bestehend?.id || null,
                typ: typ,
                ist_primaer: bestehend?.ist_primaer ? 1 : 0,
                strasse: bestehend?.strasse || '',
                plz: bestehend?.plz || '',
                stadt: bestehend?.stadt || '',
                bundesland: bestehend?.bundesland || '',
                land: bestehend?.land || 'Deutschland',
            };
            body[feld] = this.adresseEditWert;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/adressen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const j = await r.json();
                if (j.success) {
                    // Liste lokal aktualisieren
                    if (bestehend) {
                        bestehend[feld] = this.adresseEditWert;
                    } else {
                        if (!this.k.adressen) this.k.adressen = [];
                        this.k.adressen.push({ ...body, id: j.data?.id });
                    }
                    this.schliesseAdresseEdit();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },
        // Inline-Edit für Social-Media-Links
        istSocialOffen(plattform) {
            return this.socialEditPlatform === plattform;
        },
        oeffneSocialEdit(plattform) {
            this.socialEditPlatform = plattform;
            this.socialEditWert = this.socialUrl(plattform) || '';
        },
        schliesseSocialEdit() {
            this.socialEditPlatform = null;
            this.socialEditWert = '';
        },
        async speichereSocial(plattform) {
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/social', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ plattform: plattform.toLowerCase(), url: this.socialEditWert })
                });
                const j = await r.json();
                if (j.success) {
                    if (!this.k.social) this.k.social = [];
                    const idx = this.k.social.findIndex(s => (s.plattform || '').toLowerCase() === plattform.toLowerCase());
                    if (this.socialEditWert.trim() === '') {
                        if (idx >= 0) this.k.social.splice(idx, 1);
                    } else {
                        if (idx >= 0) this.k.social[idx].url = this.socialEditWert;
                        else this.k.social.push({ plattform: plattform.toLowerCase(), url: this.socialEditWert });
                    }
                    this.schliesseSocialEdit();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
        },

        // Tag-Combobox
        async ladeTagOptionen() {
            const p = this.tagCombo.suche ? '?suche=' + encodeURIComponent(this.tagCombo.suche) : '';
            const r = await fetch('/api/v1/crm/tags' + p);
            const j = await r.json();
            if (j.success) this.tagCombo.optionen = (j.data.tags || []).filter(t => !(this.k.tags||[]).some(kt => kt.id === t.id));
        },
        async setzeTag(tagId) {
            const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/tags', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tag_id: tagId, aktion: 'setzen' })
            });
            if ((await r.json()).success) {
                this.tagCombo.suche = ''; this.tagCombo.offen = false;
                this.laden();
            }
        },
        async entferneTag(tagId) {
            const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/tags', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tag_id: tagId, aktion: 'entfernen' })
            });
            if ((await r.json()).success) this.laden();
        },
        async legeNeuenTagAn() {
            const name = this.tagCombo.suche.trim();
            if (!name) return;
            const r = await fetch('/api/v1/crm/tags', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name })
            });
            const j = await r.json();
            if (j.success) {
                await this.setzeTag(j.data.id);
                App.showNotification('Tag „' + name + '" angelegt + vergeben', 'success');
            } else App.showNotification(j.message || 'Fehler', 'error');
        },

        // Foto
        async ladeFotoHoch(event) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData(); fd.append('foto', file);
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/foto', { method: 'POST', credentials: 'same-origin', body: fd });
                const j = await r.json();
                if (j.success) {
                    this.k.foto_path = j.data.foto_path + '?t=' + Date.now();
                    App.showNotification('Foto gespeichert', 'success');
                } else App.showNotification(j.message || 'Upload fehlgeschlagen', 'error');
            } catch (e) { App.showNotification(e.message, 'error'); }
            event.target.value = '';
        },

        // Notiz
        oeffneNotizDialog() { this.notizDialog = { offen: true, text: '', typ: 'notiz' }; },
        async speichereNotiz() {
            if (!this.notizDialog.text.trim()) return;
            try {
                const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId + '/aktivitaet', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typ: this.notizDialog.typ, inhalt: this.notizDialog.text.trim() })
                });
                if ((await r.json()).success) {
                    App.showNotification('Notiz gespeichert', 'success');
                    this.notizDialog.offen = false;
                    this.ladeAktivitaeten();
                    this.tab = 'zeitlinie';
                }
            } catch (e) { App.showNotification(e.message, 'error'); }
        },

        async softDelete() {
            if (!confirm('Diesen Kontakt löschen? (Soft-Delete, wiederherstellbar)')) return;
            const r = await fetch('/api/v1/crm/kontakte/' + this.kontaktId, { method: 'DELETE', credentials: 'same-origin' });
            if ((await r.json()).success) {
                App.showNotification('Gelöscht', 'success');
                setTimeout(() => window.location.href = '/crm/kontakte', 500);
            }
        },

        // Formatter
        formatStatus(s) { return ({ lead:'Lead', interessent:'Interessent', kunde:'Kunde', ehemaliger_kunde:'Ehemaliger Kunde', partner:'Partner', wunschkunde:'Wunschkunde', dienstleister:'Dienstleister', sonstiges:'Sonstiges' })[s] || s || ''; },
        formatOptIn(s) { return ({ pending:'Pending', single_opted_in:'Single Opt-In', double_opted_in:'bestätigt', unsubscribed:'abgemeldet', hard_bounce:'Hard Bounce', invalid:'invalid' })[s] || s || ''; },
        optInBadgeKlasse() { return ({ pending:'crm-bigbadge-pend', single_opted_in:'crm-bigbadge-soi', double_opted_in:'crm-bigbadge-doi', unsubscribed:'crm-bigbadge-aus', hard_bounce:'crm-bigbadge-hb', invalid:'crm-bigbadge-leer' })[this.k?.opt_in_status] || 'crm-bigbadge-leer'; },
        formatTyp(t) {
            return ({
                kontakt_angelegt:'Kontakt angelegt', kontakt_geaendert:'Kontakt geändert',
                tag_hinzugefuegt:'Tag hinzugefügt', tag_entfernt:'Tag entfernt',
                liste_beigetreten:'Liste beigetreten', liste_verlassen:'Liste verlassen',
                opt_in_erfasst:'Opt-In erfasst', doi_bestaetigt:'DOI bestätigt',
                lead_magnet:'Lead-Magnet', mail_open:'E-Mail geöffnet',
                mail_click:'E-Mail-Klick', mail_bounce:'E-Mail-Bounce', mail_unsubscribe:'Abmeldung',
                notiz:'Notiz', telefonat:'Telefonat', meeting:'Meeting'
            })[t] || t;
        },
        iconTyp(t) {
            return ({
                mail_open:'📭', mail_click:'🖱', mail_bounce:'⚠', mail_unsubscribe:'🚫',
                tag_hinzugefuegt:'+', tag_entfernt:'−',
                liste_beigetreten:'+', liste_verlassen:'−',
                doi_bestaetigt:'✓', opt_in_erfasst:'✉',
                lead_magnet:'🧲',
                notiz:'📝', telefonat:'📞', meeting:'👥',
                kontakt_angelegt:'✦', kontakt_geaendert:'✎'
            })[t] || '·';
        },
        formatZeit(d) {
            if (!d) return '';
            const dt = new Date(d.replace(' ', 'T'));
            return dt.toLocaleTimeString('de-DE', { hour:'2-digit', minute:'2-digit' });
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
