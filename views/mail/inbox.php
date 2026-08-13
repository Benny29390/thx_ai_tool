<?php /** Mail-Inbox: Sidebar links, Detail rechts, KI-Antwort-Editor */ ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="mailInbox()" x-init="init()" x-cloak class="mail-app"
     @click="ctxMenu.offen = false; ordnerCtx.offen = false"
     @keydown.escape.window="ctxMenu.offen = false; ordnerCtx.offen = false; shortcutsOffen = false"
     @keydown.window="handleKey($event)">

<!-- Page-Header: kompakt, sachlich, im Stil der anderen Module -->
<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <div>
        <h1 class="thx-page-title">Mail</h1>
        <div class="thx-page-subtitle">
            <span x-show="ungelesenInbox() === 0">Posteingang ist leer.</span>
            <span x-show="ungelesenInbox() > 0">
                <strong x-text="ungelesenInbox()"></strong> ungelesen
                <span x-show="(ordnerBaum.system || []).find(x => x.id === 'posteingang')?.anzahl > ungelesenInbox()">
                    · <span x-text="(ordnerBaum.system || []).find(x => x.id === 'posteingang')?.anzahl"></span> gesamt
                </span>
            </span>
        </div>
    </div>
    <div style="display:flex;gap:6px;align-items:center;">
        <select x-model="kontoId" @change="kontoWechseln()" class="thx-select" style="min-width:240px;">
            <option value="">— Konto wählen —</option>
            <template x-for="k in konten" :key="k.id">
                <option :value="k.id" x-text="k.name + ' (' + k.email_adresse + ')'"></option>
            </template>
        </select>
        <button class="thx-btn thx-btn-secondary" @click="pullen()" :disabled="pullLaeuft || !kontoId" title="Neue Mails abrufen">
            <span class="material-symbols-rounded" :class="pullLaeuft ? 'mail-spin' : ''" style="font-size:18px;">refresh</span>
            <span>Abrufen</span>
        </button>
        <button class="thx-btn thx-btn-primary" @click="composeNeu()" :disabled="!kontoId" title="Neue E-Mail verfassen">
            <span class="material-symbols-rounded" style="font-size:18px;">edit_square</span>
            <span>Verfassen</span>
        </button>
        <button class="thx-btn thx-btn-secondary" @click="oeffneUpload()" title="EML-Datei importieren">
            <span class="material-symbols-rounded" style="font-size:18px;">upload_file</span>
            <span>Import</span>
        </button>
        <a class="thx-btn thx-btn-secondary" href="/admin/settings?tab=smtp" title="Mail-Konten verwalten">
            <span class="material-symbols-rounded" style="font-size:18px;">tune</span>
        </a>
        <button class="thx-btn thx-btn-secondary" @click="shortcutsOffen = !shortcutsOffen" title="Tastatur-Shortcuts (?)">
            <span class="material-symbols-rounded" style="font-size:18px;">keyboard</span>
        </button>
    </div>
</div>

<div class="mail-layout">

    <!-- SPALTE 1: Ordner -->
    <aside class="mail-col mail-col-ordner">
        <div class="mail-col-head">
            <span class="mail-col-head-label">Ordner</span>
            <button class="thx-icon-btn" @click="oeffneOrdnerNeu()" title="Neuen Ordner anlegen">
                <span class="material-symbols-rounded">add</span>
            </button>
        </div>
        <div class="mail-col-body">
            <!-- System-Ordner -->
            <template x-for="o in (ordnerBaum.system || [])" :key="o.id">
                <div class="mail-nav-item"
                     :class="aktiverOrdner.typ === 'system' && aktiverOrdner.id === o.id ? 'is-active' : ''"
                     @click="ordnerWaehlen('system', o.id, o.name)">
                    <span class="material-symbols-rounded mail-nav-icon" x-text="sysOrdnerIcon(o.id)"></span>
                    <span class="mail-nav-label" x-text="o.name"></span>
                    <span class="mail-nav-count"
                          :class="parseInt(o.ungelesen) > 0 ? 'is-unread' : ''"
                          x-show="parseInt(o.anzahl) > 0"
                          x-text="parseInt(o.ungelesen) > 0 ? o.ungelesen : o.anzahl"></span>
                </div>
            </template>

            <!-- Manuelle Ordner — hierarchisch mit Unterordnern -->
            <div x-show="(ordnerBaum.ordner || []).length > 0" class="mail-nav-section">Meine Ordner</div>
            <template x-for="o in ordnerBaumFlach()" :key="o.id">
                <div class="mail-nav-item"
                     :class="aktiverOrdner.typ === 'manuell' && aktiverOrdner.id === o.id ? 'is-active' : ''"
                     :style="`padding-left: ${14 + o.depth * 18}px;`"
                     @click="ordnerWaehlen('manuell', o.id, o.name)"
                     @contextmenu.prevent="oeffneOrdnerCtx($event, o)">
                    <!-- Chevron fuer Ordner mit Unterordnern, Platzhalter sonst -->
                    <span x-show="o.hatKinder"
                          class="material-symbols-rounded mail-fold-chev"
                          :style="ordnerOffen[o.id] ? '' : 'transform:rotate(-90deg);'"
                          @click="toggleOrdner(o.id, $event)">expand_more</span>
                    <span x-show="!o.hatKinder" style="width:16px;flex-shrink:0;"></span>
                    <span class="material-symbols-rounded mail-nav-icon"
                          x-text="o.hatKinder && ordnerOffen[o.id] ? 'folder_open' : 'folder'"></span>
                    <span class="mail-nav-label" x-text="o.name"></span>
                    <span class="mail-nav-count"
                          :class="parseInt(o.ungelesen) > 0 ? 'is-unread' : ''"
                          x-show="parseInt(o.anzahl) > 0"
                          x-text="parseInt(o.ungelesen) > 0 ? o.ungelesen : o.anzahl"></span>
                </div>
            </template>

            <!-- LAM-Sichten -->
            <div class="mail-nav-section">LAM-Sichten</div>
            <div class="mail-nav-fold" @click="sichtenOffen.anbieter = !sichtenOffen.anbieter">
                <span class="material-symbols-rounded mail-fold-chev" :style="sichtenOffen.anbieter ? '' : 'transform:rotate(-90deg);'">expand_more</span>
                <span>Anbieter</span>
                <span class="mail-nav-count" x-text="sichten.anbieter?.length || 0"></span>
            </div>
            <div x-show="sichtenOffen.anbieter" x-transition.duration.150ms class="mail-nav-sub">
                <template x-for="a in (sichten.anbieter || [])" :key="a.id">
                    <div class="mail-nav-item mail-nav-sub-item"
                         :class="aktiverOrdner.typ === 'sicht' && aktiverOrdner.id === 'anbieter:' + a.id ? 'is-active' : ''"
                         @click="ordnerWaehlen('sicht', 'anbieter:' + a.id, a.name)">
                        <span class="mail-avatar mail-avatar-xs" :style="avatarFarbe(a.name)" x-text="initialen(a.name)"></span>
                        <span class="mail-nav-label" x-text="a.name"></span>
                        <span class="mail-nav-count" :class="parseInt(a.ungelesen) > 0 ? 'is-unread' : ''"
                              x-text="parseInt(a.ungelesen) > 0 ? a.ungelesen : a.anzahl"></span>
                    </div>
                </template>
                <div x-show="(sichten.anbieter || []).length === 0" class="mail-nav-empty">— keine —</div>
            </div>

            <div class="mail-nav-fold" @click="sichtenOffen.massnahmen = !sichtenOffen.massnahmen">
                <span class="material-symbols-rounded mail-fold-chev" :style="sichtenOffen.massnahmen ? '' : 'transform:rotate(-90deg);'">expand_more</span>
                <span>Maßnahmen</span>
                <span class="mail-nav-count" x-text="sichten.massnahmen?.length || 0"></span>
            </div>
            <div x-show="sichtenOffen.massnahmen" x-transition.duration.150ms class="mail-nav-sub">
                <template x-for="m in (sichten.massnahmen || [])" :key="m.id">
                    <div class="mail-nav-item mail-nav-sub-item"
                         :class="aktiverOrdner.typ === 'sicht' && aktiverOrdner.id === 'massnahme:' + m.id ? 'is-active' : ''"
                         @click="ordnerWaehlen('sicht', 'massnahme:' + m.id, (m.domain || m.id.substring(0,8)))">
                        <span class="material-symbols-rounded mail-nav-icon" style="color:var(--emerald-600);">flag</span>
                        <span class="mail-nav-label" x-text="m.domain || m.id.substring(0,8)"></span>
                        <span class="mail-nav-count" :class="parseInt(m.ungelesen) > 0 ? 'is-unread' : ''"
                              x-text="parseInt(m.ungelesen) > 0 ? m.ungelesen : m.anzahl"></span>
                    </div>
                </template>
                <div x-show="(sichten.massnahmen || []).length === 0" class="mail-nav-empty">— keine —</div>
            </div>

            <div class="mail-nav-fold" @click="sichtenOffen.absender = !sichtenOffen.absender">
                <span class="material-symbols-rounded mail-fold-chev" :style="sichtenOffen.absender ? '' : 'transform:rotate(-90deg);'">expand_more</span>
                <span>Absender</span>
                <span class="mail-nav-count" x-text="sichten.absender?.length || 0"></span>
            </div>
            <div x-show="sichtenOffen.absender" x-transition.duration.150ms class="mail-nav-sub">
                <template x-for="s in (sichten.absender || [])" :key="s.email">
                    <div class="mail-nav-item mail-nav-sub-item"
                         :class="aktiverOrdner.typ === 'sicht' && aktiverOrdner.id === 'absender:' + s.email ? 'is-active' : ''"
                         @click="ordnerWaehlen('sicht', 'absender:' + s.email, s.anbieter_name || s.name || s.email)">
                        <span class="mail-avatar mail-avatar-xs" :style="avatarFarbe(s.anbieter_name || s.name || s.email)" x-text="initialen(s.anbieter_name || s.name || s.email)"></span>
                        <span class="mail-nav-label" x-text="s.anbieter_name || s.name || s.email"></span>
                        <span class="mail-nav-count" :class="parseInt(s.ungelesen) > 0 ? 'is-unread' : ''"
                              x-text="parseInt(s.ungelesen) > 0 ? s.ungelesen : s.anzahl"></span>
                    </div>
                </template>
                <div x-show="(sichten.absender || []).length === 0" class="mail-nav-empty">— keine —</div>
            </div>
        </div>
    </aside>

    <!-- SPALTE 2: Mail-Liste -->
    <aside class="mail-col mail-col-liste">
        <div class="mail-col-head" style="flex-direction:column;align-items:stretch;gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <strong class="mail-liste-title" x-text="aktiverOrdner.label || 'Posteingang'"></strong>
                <span class="mail-liste-count" x-text="mails.length"></span>
            </div>
            <div class="mail-search-wrap">
                <span class="material-symbols-rounded mail-search-icon">search</span>
                <input type="text" class="mail-search-input" placeholder="Suchen …"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
                <button x-show="filter.suche" @click="filter.suche=''; laden()" class="mail-search-clear" title="Suche löschen">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            <div class="mail-filter-chips">
                <button class="mail-chip" :class="filter.nur_ungelesen ? 'is-active' : ''" @click="filter.nur_ungelesen = !filter.nur_ungelesen; laden()">
                    <span class="material-symbols-rounded">mark_email_unread</span>
                    Ungelesen
                </button>
                <button class="mail-chip" :class="threadAnsicht ? 'is-active' : ''" @click="threadAnsicht = !threadAnsicht" title="Mails mit gleichem Betreff gruppieren">
                    <span class="material-symbols-rounded">forum</span>
                    Threads
                </button>
            </div>
        </div>
        <div class="mail-col-body mail-liste-body">
            <template x-for="m in anzeigeMails()" :key="m.id">
                <div class="mail-card"
                     :class="[aktiveMail?.id === m.id ? 'is-active' : '', !parseInt(m.gelesen) ? 'is-unread' : '']"
                     @click="oeffneDetail(m.id)"
                     @contextmenu.prevent="oeffneCtxMenu($event, m)">
                    <span class="mail-card-unread-dot" x-show="!parseInt(m.gelesen)"></span>
                    <span class="mail-avatar" :style="avatarFarbe(m.absender_name || m.absender_email)" x-text="initialen(m.absender_name || m.absender_email)"></span>
                    <div class="mail-card-body">
                        <div class="mail-card-top">
                            <span class="mail-card-from" x-text="m.absender_name || m.absender_email"></span>
                            <span class="mail-card-date" x-text="smartDate(m.empfangen_am)"></span>
                        </div>
                        <div class="mail-card-subject">
                            <span x-show="parseInt(m.markiert)" class="material-symbols-rounded mail-card-star" style="color:var(--amber-500);">star</span>
                            <span x-text="m.betreff || '(kein Betreff)'"></span>
                            <span x-show="threadAnsicht && m.thread_anzahl > 1" class="mail-card-thread-count" x-text="m.thread_anzahl"></span>
                        </div>
                        <div class="mail-card-snippet" x-text="(m.snippet || '').substring(0,100)"></div>
                        <div class="mail-card-tags">
                            <template x-if="m.kategorie">
                                <span class="mail-tag" :class="'mail-tag-' + kategorieKlasse(m.kategorie)">
                                    <span class="mail-tag-dot"></span>
                                    <span x-text="kategorieLabel(m.kategorie)"></span>
                                </span>
                            </template>
                            <template x-if="!m.kategorie">
                                <span class="mail-tag mail-tag-neutral">
                                    <span class="mail-tag-dot"></span>
                                    unklassifiziert
                                </span>
                            </template>
                            <span x-show="parseInt(m.anhaenge_anzahl) > 0" class="mail-card-meta" title="Anhänge">
                                <span class="material-symbols-rounded">attach_file</span>
                            </span>
                            <span x-show="parseInt(m.lam_verknuepft) > 0" class="mail-tag mail-tag-lam">
                                <span class="material-symbols-rounded">link</span>
                                LAM
                            </span>
                        </div>
                    </div>
                    <!-- Hover-Quick-Actions -->
                    <div class="mail-card-quick">
                        <button @click.stop="schnellAktion(m, 'archiv')" title="Archivieren (E)">
                            <span class="material-symbols-rounded">archive</span>
                        </button>
                        <button @click.stop="schnellAktion(m, 'gelesen_toggle')" :title="parseInt(m.gelesen) ? 'Als ungelesen' : 'Als gelesen'">
                            <span class="material-symbols-rounded" x-text="parseInt(m.gelesen) ? 'mark_email_unread' : 'mark_email_read'"></span>
                        </button>
                        <button @click.stop="schnellAktion(m, 'als_spam')" title="Als Spam (!)">
                            <span class="material-symbols-rounded">block</span>
                        </button>
                    </div>
                </div>
            </template>
            <!-- Empty State: keine Mails -->
            <div x-show="!laedt && mails.length === 0" class="mail-empty">
                <div class="mail-empty-icon">
                    <span class="material-symbols-rounded">inbox</span>
                </div>
                <div class="mail-empty-title">Hier ist alles ruhig.</div>
                <div class="mail-empty-text" x-show="!filter.suche">Keine Mails in diesem Ordner. ☕</div>
                <div class="mail-empty-text" x-show="filter.suche">Keine Treffer für „<strong x-text="filter.suche"></strong>".</div>
            </div>
            <div x-show="laedt" class="mail-loading">
                <span class="mail-spinner"></span>
                <span>Lade …</span>
            </div>
        </div>
        <!-- Pull-Ergebnis als Toast (verschwindet automatisch) -->
        <div x-show="pullErgebnis" x-transition class="mail-pull-toast"
             :class="pullErgebnis?.fehler > 0 ? 'is-error' : 'is-success'">
            <span class="material-symbols-rounded" x-text="pullErgebnis?.fehler > 0 ? 'error' : 'check_circle'"></span>
            <span x-text="pullToastText()"></span>
        </div>
    </aside>

    <!-- SPALTE 3: Detail -->
    <main class="mail-col mail-col-detail">
        <div x-show="!aktiveMail" class="mail-empty mail-empty-large">
            <div class="mail-empty-icon mail-empty-icon-lg">
                <span class="material-symbols-rounded">drafts</span>
            </div>
            <div class="mail-empty-title">Wähle eine Mail</div>
            <div class="mail-empty-text">Klick links auf eine Mail oder navigiere mit <kbd>↑</kbd>/<kbd>↓</kbd>.</div>
            <div class="mail-empty-shortcuts">
                <span><kbd>J</kbd>/<kbd>K</kbd> Navigieren</span>
                <span><kbd>E</kbd> Archivieren</span>
                <span><kbd>R</kbd> Antworten</span>
                <span><kbd>?</kbd> Mehr</span>
            </div>
        </div>

        <div x-show="aktiveMail" class="mail-detail">
            <!-- Header -->
            <div class="mail-detail-head">
                <div class="mail-detail-top">
                    <h2 class="mail-detail-subject" x-text="aktiveMail?.betreff || '(kein Betreff)'"></h2>
                    <div class="mail-detail-tools">
                        <button class="thx-icon-btn" @click="markiertToggle()" :title="parseInt(aktiveMail?.markiert) ? 'Markierung entfernen' : 'Markieren'">
                            <span class="material-symbols-rounded" :style="parseInt(aktiveMail?.markiert) ? 'color:var(--amber-500);font-variation-settings:&quot;FILL&quot; 1;' : ''" x-text="parseInt(aktiveMail?.markiert) ? 'star' : 'star_border'"></span>
                        </button>
                        <button class="thx-icon-btn" @click="klassifizieren()" :disabled="klassifiziereLaeuft" title="KI-Klassifikation">
                            <span class="material-symbols-rounded" :class="klassifiziereLaeuft ? 'mail-spin' : ''" x-text="klassifiziereLaeuft ? 'progress_activity' : 'auto_awesome'"></span>
                        </button>
                        <button class="thx-icon-btn" @click="composeWeiterleiten()" title="Weiterleiten">
                            <span class="material-symbols-rounded">forward</span>
                        </button>
                        <button class="thx-icon-btn" @click="archivieren()" title="Archivieren (E)">
                            <span class="material-symbols-rounded">archive</span>
                        </button>
                        <!-- Kein-Spam-Button: nur wenn Mail aktuell in Spam ist -->
                        <button x-show="istInSpam(aktiveMail)"
                                class="thx-btn thx-btn-success thx-btn-small"
                                @click="keinSpam(aktiveMail)"
                                title="Mail ist kein Spam — zurueck in den Posteingang">
                            <span class="material-symbols-rounded" style="font-size:16px;">restore_from_trash</span>
                            Kein Spam
                        </button>
                    </div>
                </div>
                <div class="mail-detail-meta">
                    <span class="mail-avatar mail-avatar-sm" :style="avatarFarbe(aktiveMail?.absender_name || aktiveMail?.absender_email || '')" x-text="initialen(aktiveMail?.absender_name || aktiveMail?.absender_email || '')"></span>
                    <div class="mail-detail-meta-text">
                        <strong x-text="aktiveMail?.absender_name || aktiveMail?.absender_email"></strong>
                        <span class="mail-detail-meta-email" x-show="aktiveMail?.absender_name" x-text="aktiveMail?.absender_email"></span>
                    </div>
                    <span class="mail-detail-meta-date" x-text="smartDateLang(aktiveMail?.empfangen_am)"></span>
                </div>
                <div x-show="(aktiveMail?.lam_verknuepfungen || []).length > 0" style="margin-top:6px;">
                    <template x-for="v in (aktiveMail?.lam_verknuepfungen || [])" :key="v.typ + v.ziel_id">
                        <a :href="lamLink(v)" class="lam-badge" style="background:var(--thoxan-100);color:var(--thoxan-700);text-decoration:none;margin-right:4px;font-size:var(--d-fs-xs);"
                           x-text="'→ ' + v.typ + ' ' + v.ziel_id.substring(0, 8)"></a>
                    </template>
                </div>
                <div x-show="threadKontext().length > 1" style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;font-size:var(--d-fs-xs);">
                    <strong class="muted" style="text-transform:uppercase;letter-spacing:0.04em;">Thread:</strong>
                    <template x-for="(t, idx) in threadKontext()" :key="t.id">
                        <button @click="oeffneDetail(t.id)"
                                :class="t.id == aktiveMail?.id ? 'lam-btn lam-btn-sm lam-btn-primary' : 'lam-btn lam-btn-sm lam-btn-secondary'"
                                style="font-size:var(--d-fs-xs);padding:2px 8px;"
                                :title="t.betreff + ' · ' + (t.absender_name || t.absender_email) + ' · ' + t.empfangen_am">
                            <span x-text="(idx + 1) + '. ' + (t.empfangen_am?.substring(5,10).replace('-','.') || '')"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- LAM-Quick-Actions: jetzt OBEN, direkt unter Header -->
            <div x-show="(aktiveMail?.lam_verknuepfungen || []).some(v => v.typ === 'anbieter' || v.typ === 'massnahme')"
                 style="padding:10px 20px;border-bottom:1px solid var(--slate-200);background:#f0fdf4;">
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <strong style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.04em;color:var(--emerald-700);">LAM-Quick-Actions:</strong>
                    <template x-if="extrahierteKondition()">
                        <button class="lam-btn lam-btn-sm lam-btn-primary" @click="oeffneKonditionAnlegen()">
                            💶 Kondition anlegen
                            <span class="muted" style="font-size:var(--d-fs-xs);"
                                  x-text="'(' + (extrahierteKondition()?.preis || '?') + '€ · ' + (extrahierteKondition()?.buchungstyp || '—') + ')'"></span>
                        </button>
                    </template>
                    <template x-if="verknuepfteMassnahme()">
                        <div style="display:flex;gap:4px;align-items:center;">
                            <span class="muted" style="font-size:var(--d-fs-xs);">Maßnahme-Status:</span>
                            <select x-model="statusVorschlag" style="font-size:var(--d-fs-sm);padding:4px 6px;border:1px solid var(--slate-300);border-radius:3px;">
                                <option value="">— wählen —</option>
                                <option value="bei_kunde">bei_kunde</option>
                                <option value="beauftragt">beauftragt</option>
                                <option value="bei_anbieter">bei_anbieter</option>
                                <option value="live">live</option>
                                <option value="archiv">archiv</option>
                            </select>
                            <button class="lam-btn lam-btn-sm" @click="setzeMassnahmeStatus()" :disabled="!statusVorschlag">→ setzen</button>
                        </div>
                    </template>
                </div>
                <div x-show="lamAktionsErgebnis" style="margin-top:6px;font-size:var(--d-fs-xs);padding:6px 10px;border-radius:3px;"
                     :style="lamAktionsErgebnis?.ok ? 'background:#dcfce7;color:#15803d;' : 'background:#fee2e2;color:#b91c1c;'"
                     x-text="lamAktionsErgebnis?.text"></div>
            </div>

            <!-- ORIGINAL (volle Breite) -->
            <div style="padding:14px 18px;overflow-y:auto;max-height:calc(100vh - 380px);">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
                    <h3 style="margin:0;font-size:var(--d-fs-sm);text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);">Original</h3>
                    <div style="display:flex;gap:4px;">
                        <button class="lam-btn lam-btn-sm" :class="ansicht === 'html' ? 'lam-btn-primary' : 'lam-btn-secondary'"
                                @click="ansicht = 'html'" :disabled="!aktiveMail?.body_html">HTML</button>
                        <button class="lam-btn lam-btn-sm" :class="ansicht === 'text' ? 'lam-btn-primary' : 'lam-btn-secondary'"
                                @click="ansicht = 'text'">Text</button>
                    </div>
                </div>
                <iframe x-show="ansicht === 'html' && aktiveMail?.body_html"
                        :srcdoc="htmlMitSanitizing(aktiveMail?.body_html)"
                        sandbox="allow-same-origin allow-popups"
                        referrerpolicy="no-referrer"
                        style="width:100%;min-height:400px;border:1px solid var(--slate-200);border-radius:4px;background:#fff;"
                        @load="iframeAnpassen($event)"></iframe>
                <pre x-show="ansicht === 'text' || !aktiveMail?.body_html" style="font-family:inherit;white-space:pre-wrap;font-size:var(--d-fs-sm);background:var(--slate-50);padding:10px;border-radius:4px;margin:0;" x-text="aktiveMail?.body_plain || '(kein Plain-Text)'"></pre>

                <div x-show="(aktiveMail?.anhaenge || []).length > 0" style="margin-top:14px;border-top:1px solid var(--slate-200);padding-top:10px;">
                    <strong style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);">Anhänge</strong>
                    <ul style="margin:6px 0 0;padding:0;list-style:none;">
                        <template x-for="a in (aktiveMail?.anhaenge || [])" :key="a.id">
                            <li><a :href="'/api/v1/mail/anhang?id=' + a.id" style="color:var(--thoxan-700);font-size:var(--d-fs-sm);" target="_blank">📎 <span x-text="a.dateiname"></span> (<span x-text="formatBytes(a.groesse_bytes)"></span>)</a></li>
                        </template>
                    </ul>
                </div>
            </div>

            <!-- Audit-Trail unter dem Detail -->
            <div x-show="(aktiveMail?.antworten || []).length > 0" style="padding:10px 18px;border-top:1px solid var(--slate-200);background:var(--slate-50);">
                <strong style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);">Bisherige Antworten</strong>
                <ul style="margin:6px 0 0;padding-left:18px;font-size:var(--d-fs-sm);">
                    <template x-for="a in (aktiveMail?.antworten || [])" :key="a.id">
                        <li>
                            <span x-text="a.versendet_am"></span>
                            <span x-show="parseInt(a.auto_versendet)" class="lam-badge" style="background:var(--amber-100);color:#92400e;font-size:var(--d-fs-xs);">auto</span>
                            <span x-show="parseInt(a.wurde_editiert)" class="muted" style="font-size:var(--d-fs-xs);">(KI-Vorschlag editiert)</span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </main>

    <!-- SPALTE 4: KI-Klassifikation + Antwort-Editor -->
    <aside x-show="aktiveMail" class="mail-col mail-col-antwort">
        <div style="padding:12px 16px;border-bottom:1px solid var(--slate-200);background:var(--slate-50);">
            <strong style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-600);">KI-Klassifikation</strong>
            <div x-show="aktiveMail?.klassifikation" style="margin-top:6px;font-size:var(--d-fs-xs);">
                <div><strong>Kategorie:</strong> <span x-text="aktiveMail?.klassifikation?.kategorie"></span> <span class="muted">(<span x-text="Math.round((aktiveMail?.klassifikation?.kategorie_konfidenz || 0) * 100)"></span>%)</span></div>
                <div><strong>Absicht:</strong> <span x-text="aktiveMail?.klassifikation?.absicht || '—'"></span></div>
                <div><strong>Folgeaktion:</strong> <span x-text="aktiveMail?.klassifikation?.folgeaktion"></span></div>
                <div x-show="aktiveMail?.klassifikation?.dringlichkeit"><strong>Dringlichkeit:</strong> <span x-text="aktiveMail?.klassifikation?.dringlichkeit"></span></div>
            </div>
            <div x-show="!aktiveMail?.klassifikation" class="muted" style="font-size:var(--d-fs-xs);margin-top:6px;">
                Noch nicht klassifiziert. <button class="lam-btn lam-btn-sm" @click="klassifizieren()">🤖 jetzt</button>
            </div>
        </div>
        <div style="padding:12px 16px;overflow-y:auto;flex:1;">
            <strong style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-600);">Antwort</strong>
            <div class="thx-form-field" style="margin-top:6px;">
                <label style="font-size:var(--d-fs-xs);">An</label>
                <input type="text" x-model="antwort.empfaenger" style="font-size:var(--d-fs-sm);">
            </div>
            <div class="thx-form-field">
                <label style="font-size:var(--d-fs-xs);">Betreff</label>
                <input type="text" x-model="antwort.betreff" style="font-size:var(--d-fs-sm);">
            </div>
            <div class="thx-form-field">
                <label style="display:flex;justify-content:space-between;align-items:center;font-size:var(--d-fs-xs);">
                    <span>Text</span>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="lam-btn lam-btn-sm lam-btn-secondary" @click="vorlageWaehlen()" :disabled="vorlagenListe.length === 0" style="font-size:var(--d-fs-xs);padding:2px 6px;">📋</button>
                        <button type="button" class="lam-btn lam-btn-sm lam-btn-secondary" @click="originalZitieren()" :disabled="antwort.zitatEingefuegt" style="font-size:var(--d-fs-xs);padding:2px 6px;">↩ zitieren</button>
                        <!-- Hybrid: Erstentwurf laeuft lokal (datensouveraen). Dieser Knopf
                             schickt ihn BEWUSST an Claude in der Cloud fuer den Feinschliff. -->
                        <button type="button" class="lam-btn lam-btn-sm lam-btn-secondary"
                                @click="mitClaudeVerfeinern()" :disabled="!antwort.text.trim() || verfeinereLaeuft"
                                title="Den Entwurf von Claude (Cloud) verbessern lassen"
                                style="font-size:var(--d-fs-xs);padding:2px 6px;">
                            <span x-text="verfeinereLaeuft ? '✨ …' : '✨ mit Claude'"></span>
                        </button>
                    </div>
                </label>
                <!-- Antworttext ist das, was Thomas tatsaechlich liest und bearbeitet —
                     der gehoert nicht in Kleinschrift. Gleiche Groesse wie der Mail-Text
                     daneben, damit man Original und Entwurf vergleichen kann. -->
                <textarea x-model="antwort.text" rows="16"
                          style="font-family:inherit;font-size:var(--d-fs-base);line-height:1.55;min-height:320px;"></textarea>
            </div>
            <div class="thx-form-field">
                <label style="font-size:var(--d-fs-xs);">Anhänge</label>
                <input type="file" x-ref="anhangInput" @change="anhangHinzufuegen()" multiple style="font-size:var(--d-fs-xs);width:100%;">
                <ul x-show="antwort.anhaenge.length > 0" style="margin:6px 0 0;padding:0;list-style:none;font-size:var(--d-fs-xs);">
                    <template x-for="(a, idx) in antwort.anhaenge" :key="idx">
                        <li style="display:flex;align-items:center;gap:6px;">
                            📎 <span x-text="a.name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;"></span>
                            <button type="button" @click="antwort.anhaenge.splice(idx, 1)" style="background:none;border:none;color:var(--rose-600);cursor:pointer;">✕</button>
                        </li>
                    </template>
                </ul>
            </div>
            <div x-show="stopWortWarnung" style="background:#fef3c7;color:#92400e;padding:8px;border-radius:4px;font-size:var(--d-fs-xs);margin-bottom:8px;">
                ⚠ Stop-Wort: <strong x-text="stopWortWarnung"></strong>
            </div>
        </div>
        <div style="padding:10px 16px;border-top:1px solid var(--slate-200);background:#f8fafc;display:flex;flex-direction:column;gap:6px;">
            <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;">
                <button class="lam-btn lam-btn-secondary lam-btn-sm" @click="alsBeantwortetMarkieren()" title="Ohne Versand">⚐ beantwortet</button>
                <button class="lam-btn lam-btn-primary" @click="senden()" :disabled="sendeLaeuft || !antwort.text || !antwort.betreff">
                    <span x-show="!sendeLaeuft">📤 Senden</span>
                    <span x-show="sendeLaeuft">…</span>
                </button>
            </div>
            <div x-show="sendeErgebnis" style="font-size:var(--d-fs-xs);padding:6px 8px;border-radius:3px;"
                 :style="sendeErgebnis?.ok ? 'background:#dcfce7;color:#15803d;' : 'background:#fee2e2;color:#b91c1c;'"
                 x-text="sendeErgebnis?.text"></div>
        </div>
    </aside>
    <!-- Placeholder rechts, wenn keine Mail aktiv -->
    <aside x-show="!aktiveMail" class="mail-col mail-col-antwort">
        <div class="mail-empty" style="height:100%;">
            <div class="mail-empty-icon">
                <span class="material-symbols-rounded">smart_toy</span>
            </div>
            <div class="mail-empty-title" style="font-size:var(--d-fs-sm);">KI-Antwort</div>
            <div class="mail-empty-text" style="font-size:var(--d-fs-xs);">Erscheint, sobald Du eine Mail auswählst.</div>
        </div>
    </aside>
</div>

<!-- Kontextmenü (Rechtsklick auf Mail-Item) -->
<div x-show="ctxMenu.offen" x-cloak
     @click.stop
     :style="'position:fixed;left:' + ctxMenu.x + 'px;top:' + ctxMenu.y + 'px;z-index:1100;'"
     class="mail-ctxmenu">
    <button class="mail-ctxitem" @click="ctxAktion('oeffnen')">📂 Öffnen</button>
    <button class="mail-ctxitem" @click="ctxAktion('gelesen_toggle')">
        <span x-text="parseInt(ctxMenu.mail?.gelesen) ? '◯ Als ungelesen' : '✓ Als gelesen'"></span>
    </button>
    <button class="mail-ctxitem" @click="ctxAktion('markiert_toggle')">
        <span x-text="parseInt(ctxMenu.mail?.markiert) ? '☆ Markierung entfernen' : '★ Markieren'"></span>
    </button>
    <button class="mail-ctxitem" @click="ctxAktion('klassifizieren')">🤖 KI-Klassifikation</button>
    <button class="mail-ctxitem" @click="ctxAktion('anbieter_zuordnen')">👤 Anbieter zuordnen</button>
    <div class="mail-ctxsep"></div>
    <!-- Verschieben-Submenü als Inline-Liste -->
    <div style="padding:4px 10px;font-size:10px;text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);font-weight:600;">Verschieben in</div>
    <button class="mail-ctxitem" @click="verschiebeIn('posteingang')">📥 Posteingang</button>
    <button class="mail-ctxitem" @click="verschiebeIn('archiv')">🗄 Archiv</button>
    <template x-for="o in (ordnerBaum.ordner || [])" :key="o.id">
        <button class="mail-ctxitem" @click="verschiebeIn(o.id)">
            📁 <span x-text="o.name"></span>
        </button>
    </template>
    <div class="mail-ctxsep"></div>
    <button class="mail-ctxitem"
            x-show="!istInSpam(ctxMenu.mail)"
            @click="ctxAktion('als_spam')">🚫 Als Spam markieren</button>
    <button class="mail-ctxitem"
            x-show="istInSpam(ctxMenu.mail)"
            @click="ctxAktion('kein_spam')">✓ Kein Spam (zurueck in Posteingang)</button>
    <button class="mail-ctxitem mail-ctxdanger" @click="ctxAktion('loeschen')">🗑 Löschen</button>
</div>

<!-- Ordner-Kontextmenü -->
<div x-show="ordnerCtx.offen" x-cloak
     @click.stop
     :style="'position:fixed;left:' + ordnerCtx.x + 'px;top:' + ordnerCtx.y + 'px;z-index:1100;'"
     class="mail-ctxmenu">
    <button class="mail-ctxitem" @click="ordnerCtx.offen = false; oeffneOrdnerNeu(ordnerCtx.ordner.id);">📁 Unterordner anlegen</button>
    <button class="mail-ctxitem" @click="ordnerCtx.offen = false; ordnerDrawer = { offen: true, id: ordnerCtx.ordner.id, name: ordnerCtx.ordner.name, parent_id: ordnerCtx.ordner.parent_id, farbe: ordnerCtx.ordner.farbe || '' };">✏ Umbenennen / Verschieben</button>
    <button class="mail-ctxitem mail-ctxdanger" @click="loescheOrdner(ordnerCtx.ordner.id)">🗑 Ordner löschen</button>
</div>

<!-- Ordner-Drawer (anlegen / umbenennen / verschieben) -->
<div class="thx-modal-backdrop" x-show="ordnerDrawer.offen" x-cloak
     @click.self="ordnerDrawer.offen = false">
    <div class="thx-modal" style="width:440px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title" x-text="ordnerDrawer.id ? 'Ordner umbenennen' : 'Neuer Ordner'"></h3>
            <button class="thx-modal-close" @click="ordnerDrawer.offen = false">&times;</button>
        </div>
        <div class="thx-modal-body">
            <div class="thx-form-field">
                <label>Name *</label>
                <input class="thx-input" type="text" x-model="ordnerDrawer.name" @keyup.enter="speichereOrdner()" autofocus>
            </div>
            <div class="thx-form-field">
                <label>Uebergeordneter Ordner</label>
                <select class="thx-select" x-model="ordnerDrawer.parent_id">
                    <option :value="null">— Top-Level —</option>
                    <template x-for="opt in ordnerOptionen(ordnerDrawer.id)" :key="opt.id">
                        <option :value="opt.id" x-text="'— '.repeat(opt.depth) + opt.name"></option>
                    </template>
                </select>
            </div>
        </div>
        <div class="thx-modal-footer">
            <button class="thx-btn thx-btn-secondary" @click="ordnerDrawer.offen = false">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" @click="speichereOrdner()" :disabled="!ordnerDrawer.name.trim()">Speichern</button>
        </div>
    </div>
</div>

<!-- Anbieter-Picker (Modal) -->
<div x-show="anbieterPicker.offen" x-cloak
     style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1050;display:flex;align-items:center;justify-content:center;"
     @click.self="anbieterPicker.offen = false">
    <div style="background:#fff;border-radius:8px;max-width:480px;width:100%;padding:20px;max-height:80vh;display:flex;flex-direction:column;">
        <h3 style="margin:0 0 10px;">👤 Anbieter zuordnen</h3>
        <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 10px;">Mail einem LAM-Anbieter zuordnen. Erscheint danach in der Sidebar-Sicht „Anbieter".</p>
        <input type="text" x-model="anbieterPicker.suche" placeholder="Anbieter suchen …" style="margin-bottom:10px;padding:6px 10px;border:1px solid var(--slate-300);border-radius:4px;" autofocus>
        <div style="overflow-y:auto;flex:1;border:1px solid var(--slate-200);border-radius:4px;">
            <template x-for="a in anbieterGefiltert()" :key="a.id">
                <div @click="anbieterZuordnen(a)" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--slate-100);" class="mail-anb-row">
                    <strong x-text="a.name"></strong>
                    <span x-show="a.firma" class="muted" x-text="' · ' + a.firma" style="font-size:var(--d-fs-xs);"></span>
                </div>
            </template>
            <div x-show="anbieterGefiltert().length === 0" class="muted" style="padding:10px;text-align:center;">— kein Treffer —</div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:10px;">
            <button class="lam-btn lam-btn-secondary" @click="anbieterPicker.offen = false">Abbrechen</button>
        </div>
    </div>
</div>

<!-- Tastatur-Shortcuts-Übersicht -->
<div x-show="shortcutsOffen" x-cloak class="mail-shortcuts-modal" @click.self="shortcutsOffen = false">
    <div class="mail-shortcuts-card">
        <h3>⌨ Tastatur-Shortcuts</h3>
        <div class="mail-shortcuts-grid">
            <div><span>Nächste Mail</span><kbd>J</kbd></div>
            <div><span>Vorherige Mail</span><kbd>K</kbd></div>
            <div><span>Archivieren</span><kbd>E</kbd></div>
            <div><span>Als Spam</span><kbd>!</kbd></div>
            <div><span>Löschen</span><kbd>#</kbd></div>
            <div><span>Markieren</span><kbd>S</kbd></div>
            <div><span>Gelesen-Toggle</span><kbd>U</kbd></div>
            <div><span>Antwort fokussieren</span><kbd>R</kbd></div>
            <div><span>Diese Hilfe</span><kbd>?</kbd></div>
            <div><span>Schließen</span><kbd>Esc</kbd></div>
        </div>
        <div style="margin-top:18px;text-align:right;">
            <button class="lam-btn lam-btn-secondary" @click="shortcutsOffen = false">Schließen</button>
        </div>
    </div>
</div>

<!-- Konditions-Drawer -->
<div x-show="kondDrawer.offen" x-cloak
     style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;"
     @click.self="kondDrawer.offen = false">
    <div style="background:#fff;border-radius:8px;max-width:560px;width:100%;padding:24px;">
        <h3 style="margin:0 0 10px;">💶 Kondition anlegen</h3>
        <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 12px;">Vorbefüllt aus KI-Extraktion. Bitte Domain wählen und Preis prüfen.</p>
        <div class="thx-form-field">
            <label>Domain (aus Pool) *</label>
            <select x-model="kondDrawer.domain_id" style="width:100%;">
                <option value="">— wählen —</option>
                <template x-for="d in kondDomains" :key="d.id">
                    <option :value="d.id" x-text="d.url"></option>
                </template>
            </select>
            <span class="muted" style="font-size:var(--d-fs-xs);">Nur Domains des verknüpften Anbieters. Falls leer: Pool-Eintrag fehlt → erst in LAM anlegen.</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="thx-form-field">
                <label>Buchungstyp</label>
                <select x-model="kondDrawer.buchungstyp" style="width:100%;">
                    <option value="gastartikel">gastartikel</option>
                    <option value="advertorial">advertorial</option>
                    <option value="pressemitteilung">pressemitteilung</option>
                    <option value="interview">interview</option>
                    <option value="verzeichnis">verzeichnis</option>
                    <option value="startseite">startseite</option>
                </select>
            </div>
            <div class="thx-form-field">
                <label>Preis (€)</label>
                <input type="number" step="0.01" x-model="kondDrawer.preis">
            </div>
        </div>
        <div class="thx-form-field">
            <label>Link-Typ</label>
            <select x-model="kondDrawer.link_typ" style="width:100%;">
                <option value="follow">follow</option>
                <option value="nofollow">nofollow</option>
                <option value="sponsored">sponsored</option>
            </select>
        </div>
        <div class="thx-form-field">
            <label>Notiz</label>
            <textarea x-model="kondDrawer.notiz" rows="2"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button class="lam-btn lam-btn-secondary" @click="kondDrawer.offen = false">Abbrechen</button>
            <button class="lam-btn lam-btn-primary" @click="speichereKondition()" :disabled="!kondDrawer.domain_id || kondDrawer.laeuft">
                <span x-show="!kondDrawer.laeuft">→ Anlegen</span>
                <span x-show="kondDrawer.laeuft">…</span>
            </button>
        </div>
    </div>
</div>

<!-- EML-Upload-Modal -->
<div x-show="uploadOffen" x-cloak
     style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1000;display:flex;align-items:center;justify-content:center;"
     @click.self="uploadOffen = false">
    <div style="background:#fff;border-radius:8px;max-width:520px;width:100%;padding:24px;">
        <h2 style="margin:0 0 12px;">EML hochladen</h2>
        <p class="muted" style="font-size:var(--d-fs-sm);">Eine oder mehrere .eml-Dateien für Konto „<span x-text="konten.find(k => k.id == kontoId)?.name || '—'"></span>" hochladen. Max 20 MB pro Datei.</p>
        <input type="file" x-ref="emlInput" accept=".eml" multiple style="margin:10px 0;">
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button class="lam-btn lam-btn-secondary" @click="uploadOffen = false">Abbrechen</button>
            <button class="lam-btn lam-btn-primary" @click="uploadAusfuehren()" :disabled="uploadLaeuft || !kontoId">
                <span x-show="!uploadLaeuft">Hochladen</span>
                <span x-show="uploadLaeuft">…</span>
            </button>
        </div>
        <div x-show="uploadErgebnis" style="margin-top:10px;font-size:var(--d-fs-sm);background:var(--slate-50);padding:8px;border-radius:4px;">
            <strong x-text="uploadErgebnis?.titel"></strong>
            <ul style="margin:4px 0 0;padding-left:16px;font-size:var(--d-fs-xs);">
                <template x-for="d in (uploadErgebnis?.details || [])" :key="d">
                    <li x-text="d"></li>
                </template>
            </ul>
        </div>
    </div>
</div>

<!-- ============ Compose-Modal: Neue Mail / Weiterleiten ============ -->
<div x-show="compose.offen" x-cloak class="mail-shortcuts-modal" @click.self="compose.offen = false" style="align-items:flex-start;">
    <div style="background:#fff;border-radius:10px;max-width:760px;width:100%;margin:4vh auto;max-height:92vh;overflow-y:auto;padding:22px;box-shadow:0 12px 40px rgba(0,0,0,0.25);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 style="margin:0;font-size:var(--d-fs-lg);" x-text="compose.modus === 'weiterleiten' ? 'E-Mail weiterleiten' : 'Neue E-Mail'"></h2>
            <button class="thx-icon-btn" @click="compose.offen = false"><span class="material-symbols-rounded">close</span></button>
        </div>

        <!-- Empfänger mit Autocomplete -->
        <div class="thx-form-field" style="position:relative;">
            <label style="font-size:var(--d-fs-xs);">An *</label>
            <input type="text" x-model="compose.empfaenger" @input.debounce.250ms="composeSuchen()" @focus="composeSuchen()"
                   autocomplete="off" placeholder="name@firma.de" style="font-size:var(--d-fs-sm);">
            <div x-show="compose.vorschlaege.length" style="position:absolute;z-index:20;left:0;right:0;background:#fff;border:1px solid var(--slate-200);border-radius:6px;box-shadow:0 6px 20px rgba(0,0,0,0.12);max-height:180px;overflow-y:auto;">
                <template x-for="v in compose.vorschlaege" :key="v.email">
                    <div @click="compose.empfaenger = v.email; compose.vorschlaege = []" style="padding:6px 10px;cursor:pointer;font-size:var(--d-fs-sm);" @mouseenter="$el.style.background='var(--slate-50)'" @mouseleave="$el.style.background='#fff'">
                        <strong x-text="v.name || v.email"></strong>
                        <span x-show="v.name" class="muted" style="font-size:var(--d-fs-xs);" x-text="' · ' + v.email"></span>
                    </div>
                </template>
            </div>
        </div>
        <div style="display:flex;gap:12px;">
            <div class="thx-form-field" style="flex:1;"><label style="font-size:var(--d-fs-xs);">Cc</label>
                <input type="text" x-model="compose.cc" placeholder="kommagetrennt" style="font-size:var(--d-fs-sm);"></div>
            <div class="thx-form-field" style="flex:1;"><label style="font-size:var(--d-fs-xs);">Bcc</label>
                <input type="text" x-model="compose.bcc" placeholder="kommagetrennt" style="font-size:var(--d-fs-sm);"></div>
        </div>
        <div class="thx-form-field"><label style="font-size:var(--d-fs-xs);">Betreff *</label>
            <input type="text" x-model="compose.betreff" style="font-size:var(--d-fs-sm);"></div>

        <!-- KI-Stichwortfeld -->
        <div style="background:var(--slate-50);border:1px solid var(--slate-200);border-radius:8px;padding:10px 12px;margin:6px 0 12px;">
            <label style="font-size:var(--d-fs-xs);font-weight:600;display:block;margin-bottom:4px;">
                <span x-text="compose.modus === 'weiterleiten' ? '✨ Begleittext von der KI vorschlagen lassen' : '✨ Entwurf von der KI schreiben lassen'"></span>
            </label>
            <div style="display:flex;gap:8px;align-items:flex-start;">
                <textarea x-model="compose.stichworte" rows="2" style="flex:1;font-size:var(--d-fs-sm);"
                          :placeholder="compose.modus === 'weiterleiten' ? 'Worum geht es? z.B. „an Benny, bitte um Einschätzung zum Aufwand"' : 'Stichworte, z.B. „Milena fragen: 30 Min nächste Woche für Reporting-Durchsprache, Di oder Do nachmittag"'"></textarea>
                <button type="button" class="thx-btn thx-btn-secondary" @click="composeEntwurf()" :disabled="compose.kiLaeuft" style="white-space:nowrap;">
                    <span x-text="compose.kiLaeuft ? '…' : 'Entwurf'"></span>
                </button>
            </div>
        </div>

        <div class="thx-form-field">
            <label style="display:flex;justify-content:space-between;align-items:center;font-size:var(--d-fs-xs);">
                <span>Text *</span>
                <button type="button" class="lam-btn lam-btn-sm lam-btn-secondary" @click="composeVerfeinern()"
                        :disabled="!compose.text.trim() || compose.kiLaeuft" style="font-size:var(--d-fs-xs);padding:2px 6px;"
                        title="Text von Claude (Cloud) glätten lassen">✨ mit Claude</button>
            </label>
            <textarea x-model="compose.text" rows="12" style="font-family:inherit;font-size:var(--d-fs-base);line-height:1.5;min-height:240px;"></textarea>
        </div>

        <!-- Anhänge: bei Weiterleitung vorbefüllt aus dem Original -->
        <div x-show="compose.anhaenge.length" class="thx-form-field">
            <label style="font-size:var(--d-fs-xs);">Anhänge (aus der Original-Mail)</label>
            <ul style="margin:4px 0 0;padding:0;list-style:none;font-size:var(--d-fs-sm);">
                <template x-for="(a, idx) in compose.anhaenge" :key="a.id">
                    <li style="display:flex;align-items:center;gap:8px;padding:2px 0;">
                        <input type="checkbox" x-model="a.mit">
                        📎 <span x-text="a.dateiname"></span>
                        <span class="muted" style="font-size:var(--d-fs-xs);" x-text="'(' + formatBytes(a.groesse_bytes) + ')'"></span>
                    </li>
                </template>
            </ul>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;border-top:1px solid var(--slate-200);padding-top:14px;">
            <button class="thx-btn thx-btn-secondary" @click="compose.offen = false">Abbrechen</button>
            <button class="thx-btn thx-btn-primary" @click="composeSenden()" :disabled="compose.sendeLaeuft">
                <span x-text="compose.sendeLaeuft ? 'Sende …' : 'Senden'"></span>
            </button>
        </div>
    </div>
</div>

</div>

<style>
[x-cloak]{display:none!important}

/* === Mail-Modul Design-Tokens === */
.mail-app {
    --mail-radius: 10px;
    --mail-radius-sm: 6px;
    --mail-gap: 12px;
    --mail-pad: 14px;
    --mail-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 1px 1px rgba(15, 23, 42, 0.03);
    --mail-shadow-lift: 0 4px 16px rgba(15, 23, 42, 0.08);
    --mail-border: var(--slate-200);
    --mail-border-soft: var(--slate-100);
    --mail-bg: #fff;
    --mail-bg-soft: var(--slate-50);
    --mail-text-primary: var(--slate-900);
    --mail-text-secondary: var(--slate-600);
    --mail-text-muted: var(--slate-400);
    --mail-accent: var(--thoxan-600);
    --mail-accent-soft: var(--thoxan-50);
    --mail-accent-bg: var(--thoxan-100);
    --mail-success: var(--emerald-600);
    --mail-warn: var(--amber-500);
    --mail-danger: var(--rose-600);
}

/* === Hero === */
.mail-hero {
    display: flex; align-items: center; justify-content: space-between;
    gap: 20px; padding: 18px 22px; border-radius: var(--mail-radius);
    background: linear-gradient(135deg, var(--thoxan-600) 0%, var(--thoxan-700) 100%);
    color: #fff; margin-bottom: 16px; box-shadow: var(--mail-shadow-lift);
    transition: background .4s ease;
}
.mail-hero-zero {
    background: linear-gradient(135deg, var(--emerald-500) 0%, var(--emerald-700) 100%);
}
.mail-hero-stat { display: flex; gap: 16px; align-items: center; min-width: 0; }
.mail-hero-icon {
    width: 52px; height: 52px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.15); border-radius: 12px;
    font-size: 28px;
}
.mail-hero-title { font-size: var(--d-fs-2xl); font-weight: 700; line-height: 1.2; }
.mail-hero-sub { font-size: var(--d-fs-sm); opacity: 0.85; margin-top: 2px; }
.mail-hero-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
.mail-hero-select {
    appearance: none; background: rgba(255,255,255,0.15); color: #fff;
    border: 1px solid rgba(255,255,255,0.25); border-radius: var(--mail-radius-sm);
    padding: 8px 28px 8px 12px; font-size: var(--d-fs-sm); cursor: pointer;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M7 10l5 5 5-5z"/></svg>');
    background-repeat: no-repeat; background-position: right 8px center;
}
.mail-hero-select option { color: var(--slate-900); }
.mail-hero-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px;
    background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--mail-radius-sm); cursor: pointer; font-size: var(--d-fs-sm);
    text-decoration: none; transition: background .15s;
}
.mail-hero-btn:hover { background: rgba(255,255,255,0.25); }
.mail-hero-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.mail-hero-btn .material-symbols-rounded { font-size: 18px; }
.mail-spin { animation: mail-spin 1s linear infinite; }
@keyframes mail-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* === Layout ===
   4 Spalten: Ordner | Liste | Original | Antwort
   - Ordner und Liste je 360px — dieselbe Breite wie die Chat-Sidebar, damit das
     Werkzeug sich nicht bei jedem Modul anders anfuehlt.
   - Original und Antwort teilen sich den Rest zu gleichen Teilen.
   - minmax(0, 1fr) statt 1fr ist der entscheidende Teil: Ohne die 0 als Minimum
     nimmt eine Grid-Spalte die BREITE IHRES INHALTS als Untergrenze. HTML-Mails mit
     langen Zeilen sprengen dann ihre Spalte und quetschen die Antwort-Spalte platt.
   - 18px Abstand zwischen allen Spalten (Standard im Werkzeug). */
.mail-layout {
    display: grid;
    grid-template-columns: 360px 360px minmax(0, 1fr) minmax(0, 1fr);
    gap: 18px;
    align-items: stretch;
    margin-top: var(--d-gutter);
    height: calc(100vh - var(--topbar-h) - 2 * var(--d-gutter) - 80px);
    min-height: 480px;
    background: transparent;
    overflow: visible;
}
.mail-col {
    background: var(--mail-bg);
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    display: flex; flex-direction: column; overflow: hidden;
    /* Guertel und Hosentraeger zum minmax(0, 1fr) im Raster: Ohne min-width:0 darf ein
       Flex-/Grid-Kind nicht kleiner werden als sein Inhalt — eine einzige lange Zeile in
       einer HTML-Mail wuerde die Spalte wieder aufblaehen. */
    min-width: 0;
}
.mail-col-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--d-gutter); border-bottom: 1px solid var(--mail-border-soft);
    background: var(--mail-bg);
}
.mail-col-head-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--mail-text-secondary);
}
/* Zu breite Inhalte (Tabellen in HTML-Mails, lange Links) scrollen INNERHALB der Spalte,
   statt das ganze Raster zu verschieben. */
.mail-col-body { overflow-y: auto; overflow-x: auto; flex: 1; min-width: 0; }

/* === Icon-Buttons === */
.mail-icon-btn {
    background: none; border: none; cursor: pointer; padding: 6px;
    border-radius: var(--mail-radius-sm); display: inline-flex;
    align-items: center; justify-content: center; color: var(--mail-text-secondary);
    transition: background .12s, color .12s;
}
.mail-icon-btn:hover { background: var(--mail-bg-soft); color: var(--mail-text-primary); }
.mail-icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.mail-icon-btn .material-symbols-rounded { font-size: 20px; }

/* === Ordner-Spalte: Nav === */
.mail-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px; cursor: pointer; user-select: none;
    transition: background .12s, color .12s;
    color: var(--mail-text-primary); font-size: var(--d-fs-sm);
}
.mail-nav-item:hover { background: var(--mail-bg-soft); }
.mail-nav-item.is-active {
    background: var(--mail-accent-soft); color: var(--mail-accent); font-weight: 600;
    box-shadow: inset 3px 0 0 var(--mail-accent);
}
.mail-nav-icon { font-size: 19px !important; color: var(--mail-text-secondary); flex-shrink: 0; }
.mail-nav-item.is-active .mail-nav-icon { color: var(--mail-accent); }
.mail-nav-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mail-nav-count {
    background: var(--slate-100); color: var(--slate-600);
    padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;
    flex-shrink: 0; min-width: 24px; text-align: center;
}
.mail-nav-count.is-unread { background: var(--mail-accent); color: #fff; }
.mail-nav-section {
    padding: 12px 14px 4px; font-size: 10px; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--mail-text-muted); font-weight: 700;
}
.mail-nav-fold {
    display: flex; align-items: center; gap: 6px; padding: 6px 14px;
    cursor: pointer; user-select: none; font-size: var(--d-fs-xs);
    color: var(--mail-text-secondary); font-weight: 600;
}
.mail-nav-fold:hover { background: var(--mail-bg-soft); }
.mail-fold-chev { font-size: 16px !important; transition: transform .15s; }
.mail-nav-sub { padding-left: 4px; }
.mail-nav-sub-item { padding-left: 24px; font-size: var(--d-fs-xs); }
.mail-nav-empty { padding: 4px 14px 8px 28px; font-size: var(--d-fs-xs); color: var(--mail-text-muted); }

/* === Mail-Liste: Header === */
.mail-liste-title { font-size: var(--d-fs-base); font-weight: 700; color: var(--mail-text-primary); }
.mail-liste-count { font-size: var(--d-fs-xs); color: var(--mail-text-muted); background: var(--mail-bg-soft); padding: 2px 8px; border-radius: 999px; }
.mail-search-wrap { position: relative; }
.mail-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    font-size: 18px !important; color: var(--mail-text-muted);
}
.mail-search-input {
    width: 100%; padding: 8px 32px 8px 34px; border: 1px solid var(--mail-border);
    border-radius: var(--mail-radius-sm); font-size: var(--d-fs-sm); outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.mail-search-input:focus { border-color: var(--mail-accent); box-shadow: 0 0 0 3px var(--mail-accent-soft); }
.mail-search-clear {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; padding: 4px;
    color: var(--mail-text-muted); border-radius: 4px;
}
.mail-search-clear:hover { background: var(--mail-bg-soft); }
.mail-search-clear .material-symbols-rounded { font-size: 14px; }

.mail-filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.mail-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; background: var(--mail-bg-soft); color: var(--mail-text-secondary);
    border: 1px solid transparent; border-radius: 999px; cursor: pointer;
    font-size: var(--d-fs-xs); font-weight: 500; transition: all .15s;
}
.mail-chip:hover { background: var(--slate-100); }
.mail-chip.is-active {
    background: var(--mail-accent); color: #fff; border-color: var(--mail-accent);
}
.mail-chip .material-symbols-rounded { font-size: 14px; }

/* === Mail-Cards === */
.mail-liste-body { padding: 4px; }
.mail-card {
    position: relative; display: flex; gap: 10px; padding: 10px 12px;
    border-radius: var(--mail-radius-sm); cursor: pointer;
    transition: background .12s, transform .08s;
    border-bottom: 1px solid var(--mail-border-soft);
}
.mail-card:hover { background: var(--mail-bg-soft); }
.mail-card.is-active { background: var(--mail-accent-soft); }
.mail-card.is-active:hover { background: var(--mail-accent-soft); }
.mail-card.is-active::before {
    content: ''; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px;
    background: var(--mail-accent); border-radius: 0 3px 3px 0;
}
.mail-card-unread-dot {
    position: absolute; left: 4px; top: 50%; transform: translateY(-50%);
    width: 8px; height: 8px; background: var(--mail-accent); border-radius: 50%;
}
.mail-card.is-unread { padding-left: 18px; }
.mail-card.is-active.is-unread .mail-card-unread-dot { background: var(--mail-accent); }

/* === Avatare === */
.mail-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 600; font-size: var(--d-fs-sm); flex-shrink: 0;
    user-select: none;
}
.mail-avatar-sm { width: 32px; height: 32px; font-size: 12px; }
.mail-avatar-xs { width: 20px; height: 20px; font-size: 10px; }

.mail-card-body { flex: 1; min-width: 0; }
.mail-card-top { display: flex; justify-content: space-between; align-items: baseline; gap: 6px; }
.mail-card-from {
    font-weight: 600; color: var(--mail-text-primary); font-size: var(--d-fs-sm);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;
}
.mail-card.is-unread .mail-card-from { color: var(--mail-text-primary); }
.mail-card:not(.is-unread) .mail-card-from { font-weight: 500; color: var(--mail-text-secondary); }
.mail-card-date { font-size: 11px; color: var(--mail-text-muted); flex-shrink: 0; }
.mail-card-subject {
    margin-top: 2px; font-size: var(--d-fs-sm);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    display: flex; align-items: center; gap: 4px;
}
.mail-card.is-unread .mail-card-subject { font-weight: 600; color: var(--mail-text-primary); }
.mail-card:not(.is-unread) .mail-card-subject { color: var(--mail-text-secondary); }
.mail-card-star { font-size: 14px !important; flex-shrink: 0; font-variation-settings: 'FILL' 1; }
.mail-card-thread-count {
    background: var(--slate-200); color: var(--slate-600);
    padding: 0 6px; border-radius: 8px; font-size: 10px; font-weight: 600;
}
.mail-card-snippet {
    font-size: var(--d-fs-xs); color: var(--mail-text-muted); margin-top: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mail-card-tags { display: flex; gap: 6px; align-items: center; margin-top: 6px; flex-wrap: wrap; }
.mail-card-meta { display: inline-flex; color: var(--mail-text-muted); }
.mail-card-meta .material-symbols-rounded { font-size: 13px; }

/* === Tags / Kategorie === */
.mail-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 999px;
    font-size: 11px; font-weight: 500; line-height: 1;
}
.mail-tag-dot { width: 6px; height: 6px; border-radius: 50%; }
.mail-tag .material-symbols-rounded { font-size: 12px; }
/* Kategorien — konsistente Farbcodierung */
.mail-tag-anbieter   { background: #dbeafe; color: #1d4ed8; }
.mail-tag-anbieter .mail-tag-dot { background: #3b82f6; }
.mail-tag-standardfrage { background: #d1fae5; color: #065f46; }
.mail-tag-standardfrage .mail-tag-dot { background: #10b981; }
.mail-tag-kunde      { background: #ede9fe; color: #5b21b6; }
.mail-tag-kunde .mail-tag-dot { background: #8b5cf6; }
.mail-tag-presse     { background: #cffafe; color: #155e75; }
.mail-tag-presse .mail-tag-dot { background: #06b6d4; }
.mail-tag-spam       { background: #fee2e2; color: #991b1b; }
.mail-tag-spam .mail-tag-dot { background: #ef4444; }
.mail-tag-info       { background: #f1f5f9; color: #475569; }
.mail-tag-info .mail-tag-dot { background: #94a3b8; }
.mail-tag-neutral    { background: var(--mail-bg-soft); color: var(--mail-text-muted); }
.mail-tag-neutral .mail-tag-dot { background: var(--mail-text-muted); }
.mail-tag-lam        { background: var(--thoxan-100); color: var(--thoxan-700); }

/* === Hover-Quick-Actions === */
.mail-card-quick {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    display: none; gap: 2px; background: #fff;
    padding: 4px; border-radius: var(--mail-radius-sm);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.mail-card:hover .mail-card-quick { display: flex; }
.mail-card-quick button {
    background: none; border: none; cursor: pointer; padding: 5px;
    border-radius: 4px; color: var(--mail-text-secondary);
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .12s, color .12s;
}
.mail-card-quick button:hover { background: var(--mail-accent-soft); color: var(--mail-accent); }
.mail-card-quick .material-symbols-rounded { font-size: 17px; }

/* === Empty States === */
.mail-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 40px 20px; text-align: center; color: var(--mail-text-muted); gap: 8px;
}
.mail-empty-large { min-height: 400px; }
.mail-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--mail-bg-soft); display: flex; align-items: center; justify-content: center;
    color: var(--mail-text-muted); margin-bottom: 6px;
}
.mail-empty-icon .material-symbols-rounded { font-size: 28px; }
.mail-empty-icon-lg { width: 72px; height: 72px; }
.mail-empty-icon-lg .material-symbols-rounded { font-size: 36px; }
.mail-empty-title { font-size: var(--d-fs-base); font-weight: 600; color: var(--mail-text-secondary); }
.mail-empty-text { font-size: var(--d-fs-sm); color: var(--mail-text-muted); }
.mail-empty-shortcuts {
    display: flex; flex-wrap: wrap; gap: 12px; justify-content: center;
    margin-top: 14px; font-size: var(--d-fs-xs); color: var(--mail-text-muted);
}
.mail-empty-shortcuts kbd {
    background: var(--mail-bg-soft); border: 1px solid var(--mail-border);
    border-radius: 4px; padding: 1px 6px; font-family: ui-monospace, monospace;
    font-size: 11px; margin: 0 2px;
}

/* === Loading === */
.mail-loading { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 20px; color: var(--mail-text-muted); font-size: var(--d-fs-sm); }
.mail-spinner { width: 16px; height: 16px; border: 2px solid var(--mail-border); border-top-color: var(--mail-accent); border-radius: 50%; animation: mail-spin 0.7s linear infinite; }

/* === Pull-Toast === */
.mail-pull-toast {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-top: 1px solid var(--mail-border-soft);
    font-size: var(--d-fs-xs); font-weight: 500;
}
.mail-pull-toast.is-success { background: #dcfce7; color: #15803d; }
.mail-pull-toast.is-error { background: #fee2e2; color: #b91c1c; }
.mail-pull-toast .material-symbols-rounded { font-size: 16px; }

/* === Detail-Spalte === */
.mail-detail { display: flex; flex-direction: column; height: 100%; }
.mail-detail-head { padding: var(--d-gutter); border-bottom: 1px solid var(--mail-border-soft); }
.mail-detail-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.mail-detail-subject {
    margin: 0; font-size: var(--d-fs-lg); font-weight: 700; color: var(--mail-text-primary);
    line-height: 1.35; flex: 1;
}
.mail-detail-tools { display: flex; gap: 2px; flex-shrink: 0; }
.mail-detail-meta { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.mail-detail-meta-text { flex: 1; min-width: 0; line-height: 1.3; }
.mail-detail-meta-text strong { display: block; color: var(--mail-text-primary); font-size: var(--d-fs-sm); }
.mail-detail-meta-email { color: var(--mail-text-muted); font-size: var(--d-fs-xs); }
.mail-detail-meta-date { color: var(--mail-text-muted); font-size: var(--d-fs-xs); flex-shrink: 0; }

/* === Kontextmenü === */
.mail-ctxmenu { background: #fff; border: 1px solid var(--mail-border); border-radius: var(--mail-radius-sm); box-shadow: var(--mail-shadow-lift); min-width: 220px; padding: 4px; }
.mail-ctxitem { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 7px 12px; background: none; border: none; border-radius: 4px; cursor: pointer; font-size: var(--d-fs-sm); color: var(--mail-text-primary); }
.mail-ctxitem:hover { background: var(--mail-accent-soft); color: var(--mail-accent); }
.mail-ctxdanger { color: var(--mail-danger); }
.mail-ctxdanger:hover { background: #fee2e2; color: #991b1b; }
.mail-ctxsep { height: 1px; background: var(--mail-border-soft); margin: 4px 2px; }

/* === Shortcuts-Overlay === */
.mail-shortcuts-modal { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1100; display: flex; align-items: center; justify-content: center; padding: 20px; }
.mail-shortcuts-card { background: #fff; border-radius: var(--mail-radius); padding: 24px; max-width: 480px; width: 100%; box-shadow: 0 25px 60px rgba(15, 23, 42, 0.35); }
.mail-shortcuts-card h3 { margin: 0 0 16px; color: var(--mail-text-primary); }
.mail-shortcuts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: var(--d-fs-sm); }
.mail-shortcuts-grid div { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; }
.mail-shortcuts-grid kbd { background: var(--mail-bg-soft); border: 1px solid var(--mail-border); border-radius: 4px; padding: 2px 8px; font-family: ui-monospace, monospace; font-size: 12px; }

/* === Responsive ===
   Auf schmalen Bildschirmen schrumpfen nur die beiden festen Spalten.
   Original und Antwort behalten ihre 50/50-Teilung — und ihr minmax(0, …),
   sonst waere der Ueberlauf sofort zurueck. */
@media (max-width: 1600px) {
    .mail-layout { grid-template-columns: 320px 320px minmax(0, 1fr) minmax(0, 1fr); }
}
@media (max-width: 1400px) {
    .mail-layout { grid-template-columns: 260px 300px minmax(0, 1fr) minmax(0, 1fr); }
    .mail-hero { padding: 14px 18px; }
    .mail-hero-title { font-size: var(--d-fs-xl); }
}
@media (max-width: 1200px) {
    .mail-layout { grid-template-columns: 200px 260px minmax(0, 1fr) minmax(0, 1fr); }
}
</style>

<script>
function mailInbox() {
    return {
        kontoId: '',
        konten: [],
        mails: [],
        aktiveMail: null,
        laedt: false,
        filter: { suche: '', nur_ungelesen: false, nur_markiert: false },
        pullLaeuft: false,
        pullErgebnis: null,
        klassifiziereLaeuft: false,
        antwort: { empfaenger: '', betreff: '', text: '' },
        stopWortWarnung: null,
        sendeLaeuft: false,
        sendeErgebnis: null,
        verfeinereLaeuft: false,
        compose: {
            offen: false, modus: 'neu', originalId: null,
            empfaenger: '', cc: '', bcc: '', betreff: '', text: '', stichworte: '',
            anhaenge: [], vorschlaege: [],
            kiLaeuft: false, sendeLaeuft: false,
        },
        uploadOffen: false,
        uploadLaeuft: false,
        uploadErgebnis: null,
        statusVorschlag: '',
        lamAktionsErgebnis: null,
        kondDrawer: { offen: false, laeuft: false, domain_id: '', buchungstyp: 'gastartikel', preis: '', link_typ: 'follow', notiz: '' },
        kondDomains: [],
        vorlagenListe: [],
        anhangLaeuft: false,
        threadAnsicht: false,
        shortcutsOffen: false,
        pullToastTimer: null,
        ctxMenu: { offen: false, x: 0, y: 0, mail: null },
        sichten: { anbieter: [], massnahmen: [], absender: [] },
        sichtenOffen: { anbieter: true, massnahmen: false, absender: true },
        anbieterPicker: { offen: false, suche: '', alle: [], mailId: null },
        ordnerBaum: { system: [], ordner: [] },
        aktiverOrdner: { typ: 'system', id: 'posteingang', label: 'Posteingang' },
        ordnerDrawer: { offen: false, id: null, name: '', parent_id: null, farbe: '' },
        /** id -> bool: welcher Ordner ist im Baum ausgeklappt */
        ordnerOffen: {},
        ordnerCtx: { offen: false, x: 0, y: 0, ordner: null },

        /** Postfach wechseln und die Wahl fuer den naechsten Aufruf merken. */
        async kontoWechseln() {
            if (this.kontoId) {
                localStorage.setItem('thx_mail_last_konto', String(this.kontoId));
            }
            await this.laden();
        },

        async init() {
            const rk = await fetch('/api/v1/mail/konten', { credentials: 'same-origin' });
            const jk = await rk.json();
            this.konten = jk.success ? jk.data : [];

            // Zuletzt genutztes Postfach merken. Reihenfolge:
            // 1. das zuletzt gewählte (localStorage)  2. das als Standard markierte
            // 3. das erste aktive. So landet man nicht bei jedem Aufruf im falschen Postfach.
            const aktive = this.konten.filter(k => parseInt(k.aktiv));
            const gemerkt = localStorage.getItem('thx_mail_last_konto');
            const gewaehlt =
                  aktive.find(k => String(k.id) === String(gemerkt))
               || aktive.find(k => parseInt(k.ist_standard))
               || aktive[0];
            if (gewaehlt) {
                this.kontoId = gewaehlt.id;
                await this.laden();
            }
            await this.ladeVorlagen();
            await this.ladeSichten();
            await this.ladeOrdner();
            // (kontoWechseln() merkt die Auswahl, siehe unten)

            // Deep-Link aus LAM: ?reply=<mail_id> öffnet die Mail im Antwort-Modus
            const params = new URLSearchParams(window.location.search);
            const replyId = parseInt(params.get('reply'), 10);
            if (replyId) {
                const ziel = this.mails.find(m => m.id === replyId);
                if (ziel) {
                    this.oeffneMail(ziel);
                } else {
                    // Mail nicht im aktuellen Sicht-Filter — direkt laden
                    try {
                        const r = await fetch('/api/v1/mail/nachricht-detail?id=' + replyId, { credentials: 'same-origin' });
                        const j = await r.json();
                        if (j.success) this.aktiveMail = j.data;
                    } catch (e) {}
                }
            }
        },

        // Betreff normalisieren (Re:, AW:, Fwd:, WG: weg)
        normBetreff(s) {
            return (s || '').replace(/^\s*((re|aw|fwd|wg|antw|fw)\s*:\s*)+/gi, '').trim().toLowerCase();
        },

        threadKey(m) {
            const b = this.normBetreff(m.betreff);
            return b || '__id_' + m.id;
        },

        anzeigeMails() {
            if (!this.threadAnsicht) return this.mails;
            // Gruppieren, neueste pro Gruppe als Repräsentant
            const map = new Map();
            for (const m of this.mails) {
                const k = this.threadKey(m);
                if (!map.has(k)) map.set(k, { repr: m, count: 1 });
                else {
                    const g = map.get(k);
                    g.count++;
                    // Neueste = größtes empfangen_am
                    if ((m.empfangen_am || '') > (g.repr.empfangen_am || '')) g.repr = m;
                }
            }
            return Array.from(map.values()).map(g => ({ ...g.repr, thread_anzahl: g.count }));
        },

        threadKontext() {
            if (!this.aktiveMail) return [];
            const k = this.threadKey(this.aktiveMail);
            return this.mails.filter(m => this.threadKey(m) === k)
                .sort((a, b) => (a.empfangen_am || '').localeCompare(b.empfangen_am || ''));
        },

        async ladeVorlagen(kategorie) {
            try {
                const url = '/api/v1/mail/vorlagen' + (kategorie ? '?kategorie=' + encodeURIComponent(kategorie) : '');
                const r = await fetch(url, { credentials: 'same-origin' });
                const j = await r.json();
                this.vorlagenListe = j.success ? (j.data || []) : [];
            } catch (e) { this.vorlagenListe = []; }
        },

        async laden() {
            if (!this.kontoId) { this.mails = []; return; }
            this.laedt = true;
            try {
                const p = new URLSearchParams({ konto_id: this.kontoId });
                if (this.filter.suche) p.set('suche', this.filter.suche);
                if (this.filter.nur_ungelesen) p.set('nur_ungelesen', '1');
                // Aktiver Ordner → Filter
                if (this.aktiverOrdner.typ === 'system') {
                    p.set('system_ordner', this.aktiverOrdner.id);
                } else if (this.aktiverOrdner.typ === 'manuell') {
                    p.set('ordner_id', this.aktiverOrdner.id);
                } else if (this.aktiverOrdner.typ === 'sicht') {
                    const [art, key] = this.aktiverOrdner.id.split(':');
                    if (art === 'anbieter') p.set('lam_anbieter_id', key);
                    else if (art === 'massnahme') p.set('lam_massnahme_id', key);
                    else if (art === 'absender') p.set('absender', key);
                }
                const r = await fetch('/api/v1/mail/nachrichten?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.mails = j.success ? j.data : [];
            } finally { this.laedt = false; }
            // Sichten + Ordner parallel aktualisieren
            this.ladeSichten();
            this.ladeOrdner();
        },

        async ladeSichten() {
            if (!this.kontoId) { this.sichten = { anbieter: [], massnahmen: [], absender: [] }; return; }
            try {
                const r = await fetch('/api/v1/mail/personen-sicht?konto_id=' + this.kontoId, { credentials: 'same-origin' });
                const j = await r.json();
                this.sichten = j.success ? j.data : { anbieter: [], massnahmen: [], absender: [] };
            } catch (e) { /* ignore */ }
        },

        async ladeOrdner() {
            if (!this.kontoId) { this.ordnerBaum = { system: [], ordner: [] }; return; }
            try {
                const r = await fetch('/api/v1/mail/ordner?konto_id=' + this.kontoId, { credentials: 'same-origin' });
                const j = await r.json();
                this.ordnerBaum = j.success ? j.data : { system: [], ordner: [] };
            } catch (e) { /* ignore */ }
        },

        ordnerWaehlen(typ, id, label) {
            this.aktiverOrdner = { typ, id, label };
            this.aktiveMail = null;
            this.laden();
        },

        eigeneOrdner() {
            return (this.ordnerBaum.ordner || []).filter(o => !o.parent_id);
        },

        /** Ist eine Mail im Spam-Ordner (Status ignoriert oder Klassifikation spam)? */
        istInSpam(m) {
            if (!m) return false;
            return m.status === 'ignoriert' || (m.kategorie || '').toLowerCase() === 'spam';
        },

        async keinSpam(m) {
            if (!m) return;
            await fetch('/api/v1/mail/nachricht-aktion', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ id: m.id, aktion: 'kein_spam' }),
            });
            if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
            await this.laden();
        },

        /**
         * Liefert die Ordner-Hierarchie flachgelegt als Array von Eintraegen,
         * jeweils mit depth (0,1,2,…) und hatKinder-Flag.
         * Kinder sind nur enthalten wenn der Parent in ordnerOffen=true ist.
         */
        ordnerBaumFlach() {
            const alle = this.ordnerBaum.ordner || [];
            const kinderVon = (pid) => alle.filter(o => (o.parent_id || null) === pid);
            const result = [];
            const walk = (parentId, depth) => {
                kinderVon(parentId).forEach(o => {
                    const hatKinder = kinderVon(o.id).length > 0;
                    result.push({ ...o, depth, hatKinder });
                    if (hatKinder && this.ordnerOffen[o.id]) {
                        walk(o.id, depth + 1);
                    }
                });
            };
            walk(null, 0);
            return result;
        },

        toggleOrdner(id, ev) {
            if (ev) ev.stopPropagation();
            this.ordnerOffen[id] = !this.ordnerOffen[id];
        },

        /** Alle Ordner als flache Liste fuer den Parent-Select im Drawer */
        ordnerOptionen(ausschlussId) {
            const alle = this.ordnerBaum.ordner || [];
            const kinderVon = (pid) => alle.filter(o => (o.parent_id || null) === pid);
            const result = [];
            const sammleNachfahren = (id) => {
                const s = new Set([id]);
                const queue = [id];
                while (queue.length) {
                    const cur = queue.shift();
                    kinderVon(cur).forEach(k => { s.add(k.id); queue.push(k.id); });
                }
                return s;
            };
            const blockiert = ausschlussId ? sammleNachfahren(ausschlussId) : new Set();
            const walk = (parentId, depth) => {
                kinderVon(parentId).forEach(o => {
                    if (!blockiert.has(o.id)) {
                        result.push({ id: o.id, name: o.name, depth });
                        walk(o.id, depth + 1);
                    }
                });
            };
            walk(null, 0);
            return result;
        },

        oeffneOrdnerNeu(parentId = null) {
            this.ordnerDrawer = { offen: true, id: null, name: '', parent_id: parentId, farbe: '' };
        },

        oeffneOrdnerCtx(ev, ordner) {
            const x = Math.min(ev.clientX, window.innerWidth - 220);
            const y = Math.min(ev.clientY, window.innerHeight - 140);
            this.ordnerCtx = { offen: true, x, y, ordner };
        },

        async speichereOrdner() {
            const d = this.ordnerDrawer;
            if (!d.name.trim()) { alert('Name erforderlich.'); return; }
            try {
                const r = await fetch('/api/v1/mail/ordner-save', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: d.id, name: d.name, parent_id: d.parent_id, farbe: d.farbe, konto_id: this.kontoId }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler.'); return; }
                this.ordnerDrawer.offen = false;
                await this.ladeOrdner();
            } catch (e) { alert('Verbindungsfehler.'); }
        },

        async loescheOrdner(id) {
            if (!confirm('Ordner löschen? Mails darin landen wieder im Posteingang.')) return;
            await fetch('/api/v1/mail/ordner-loeschen', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ id }),
            });
            this.ordnerCtx.offen = false;
            if (this.aktiverOrdner.typ === 'manuell' && this.aktiverOrdner.id === id) {
                this.aktiverOrdner = { typ: 'system', id: 'posteingang', label: 'Posteingang' };
            }
            await this.ladeOrdner();
            await this.laden();
        },

        async verschiebeIn(ziel) {
            const m = this.ctxMenu.mail;
            this.ctxMenu.offen = false;
            if (!m) return;
            await fetch('/api/v1/mail/verschieben', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ ids: [m.id], ziel }),
            });
            if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
            await this.laden();
        },

        async pullen() {
            this.pullLaeuft = true;
            this.pullErgebnis = null;
            try {
                const r = await fetch('/api/v1/mail/pull', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ konto_id: this.kontoId }),
                });
                const j = await r.json();
                this.pullErgebnis = j.success ? j.data : { erfolg: 0, fehler: 1 };
                await this.laden();
                // Toast nach 4 Sek ausblenden
                if (this.pullToastTimer) clearTimeout(this.pullToastTimer);
                this.pullToastTimer = setTimeout(() => { this.pullErgebnis = null; }, 4000);
            } finally { this.pullLaeuft = false; }
        },

        ansicht: 'html', // 'html' oder 'text'

        async oeffneDetail(id) {
            const r = await fetch('/api/v1/mail/nachricht-detail?id=' + id, { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) return;
            this.aktiveMail = j.data;
            this.stopWortWarnung = null;
            this.sendeErgebnis = null;
            this.ansicht = this.aktiveMail?.body_html ? 'html' : 'text';
            this.antwort = {
                empfaenger: this.aktiveMail.absender_email,
                betreff: 'Re: ' + (this.aktiveMail.betreff || '').replace(/^Re:\s*/i, ''),
                text: this.aktiveMail?.klassifikation?.vorgeschlagene_antwort || '',
                zitatEingefuegt: false,
                anhaenge: [],
            };
            // Mark als gelesen in der Liste
            const m = this.mails.find(x => x.id == id);
            if (m) m.gelesen = 1;
            // Auto-Klassifizieren, falls noch nicht geschehen
            if (!this.aktiveMail.klassifikation) {
                this.klassifizieren();
            }
        },

        // HTML-Mail vor iframe-Render säubern
        htmlMitSanitizing(html) {
            if (!html) return '';
            // Scripts + on*-Handler + javascript:-URLs entfernen
            let bereinigt = html
                .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
                .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, m => m) // styles erlauben (Mail-Layout)
                .replace(/\son\w+\s*=\s*"[^"]*"/gi, '')
                .replace(/\son\w+\s*=\s*'[^']*'/gi, '')
                .replace(/javascript:/gi, 'invalid:')
                .replace(/<iframe\b[^>]*>[\s\S]*?<\/iframe>/gi, '');
            // Wrapper mit lesbarem Default-Styling
            return `<!DOCTYPE html><html><head><meta charset="UTF-8"><base target="_blank">
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
                           font-size: 14px; line-height: 1.5; color: #1e293b; margin: 12px; background: #fff; }
                    img { max-width: 100% !important; height: auto !important; }
                    table { max-width: 100% !important; }
                    a { color: #0369a1; }
                    pre, code { white-space: pre-wrap; word-wrap: break-word; }
                    /* Tracking-Pixel ausblenden */
                    img[width="1"], img[height="1"] { display: none !important; }
                </style>
            </head><body>${bereinigt}</body></html>`;
        },

        iframeAnpassen(ev) {
            try {
                const ifr = ev.target;
                const h = ifr.contentDocument?.body?.scrollHeight;
                if (h && h > 300) ifr.style.height = Math.min(h + 40, 800) + 'px';
            } catch (e) { /* cross-origin: ignore */ }
        },

        // === UX-Helper ===

        // Klassennamen für Kategorie-Tag (statt Inline-Style)
        kategorieKlasse(k) {
            const map = {
                'anbieter_antwort': 'anbieter',
                'standardfrage': 'standardfrage',
                'kundenanfrage': 'kunde',
                'presseanfrage': 'presse',
                'spam': 'spam',
                'info': 'info',
            };
            return map[k] || 'neutral';
        },

        kategorieLabel(k) {
            const map = {
                'anbieter_antwort': 'Anbieter',
                'standardfrage': 'Standardfrage',
                'kundenanfrage': 'Kunde',
                'presseanfrage': 'Presse',
                'spam': 'Spam',
                'info': 'Info',
            };
            return map[k] || k;
        },

        // Initialen aus Name extrahieren
        initialen(name) {
            if (!name) return '?';
            const t = name.replace(/<[^>]+>/g, '').trim();
            const teile = t.split(/[\s\.@]+/).filter(Boolean);
            if (teile.length === 0) return '?';
            if (teile.length === 1) return teile[0].substring(0, 2).toUpperCase();
            return (teile[0][0] + teile[teile.length - 1][0]).toUpperCase();
        },

        // Deterministische Farbe pro Name (Hash-basiert)
        avatarFarbe(name) {
            const palette = [
                ['#3b82f6', '#1d4ed8'], ['#10b981', '#047857'], ['#f59e0b', '#b45309'],
                ['#8b5cf6', '#6d28d9'], ['#06b6d4', '#0e7490'], ['#ef4444', '#b91c1c'],
                ['#ec4899', '#be185d'], ['#84cc16', '#4d7c0f'], ['#6366f1', '#4338ca'],
            ];
            let h = 0;
            for (let i = 0; i < (name || '').length; i++) h = ((h << 5) - h) + name.charCodeAt(i);
            const [bg, fg] = palette[Math.abs(h) % palette.length];
            return `background:linear-gradient(135deg, ${bg}, ${fg});`;
        },

        // Datum smart formatieren: "10:30", "Gestern", "Mo.", "12.05."
        smartDate(iso) {
            if (!iso) return '';
            const d = new Date(iso.replace(' ', 'T'));
            const now = new Date();
            const heute = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const tag = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diffTage = Math.round((heute - tag) / 86400000);
            const uhrzeit = d.toTimeString().substring(0, 5);
            if (diffTage === 0) return uhrzeit;
            if (diffTage === 1) return 'Gestern';
            if (diffTage < 7) return ['So.','Mo.','Di.','Mi.','Do.','Fr.','Sa.'][d.getDay()];
            if (d.getFullYear() === now.getFullYear()) {
                return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
            }
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        },

        // Längere Datums-Version (Detail-Header)
        smartDateLang(iso) {
            if (!iso) return '';
            const d = new Date(iso.replace(' ', 'T'));
            const now = new Date();
            const heute = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const tag = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diffTage = Math.round((heute - tag) / 86400000);
            const uhrzeit = d.toTimeString().substring(0, 5);
            if (diffTage === 0) return 'Heute ' + uhrzeit;
            if (diffTage === 1) return 'Gestern ' + uhrzeit;
            if (diffTage < 7) return ['So.','Mo.','Di.','Mi.','Do.','Fr.','Sa.'][d.getDay()] + ' ' + uhrzeit;
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' }) + ' · ' + uhrzeit;
        },

        // Icon-Namen für System-Ordner (Material Symbols)
        sysOrdnerIcon(id) {
            return { posteingang: 'inbox', markiert: 'star', gesendet: 'send',
                     archiv: 'archive', spam: 'block', papierkorb: 'delete' }[id] || 'folder';
        },

        // Anzahl ungelesener Mails im Posteingang (Inbox-Zero-Hero)
        ungelesenInbox() {
            const o = (this.ordnerBaum.system || []).find(x => x.id === 'posteingang');
            return o ? parseInt(o.ungelesen) || 0 : 0;
        },

        // Toast-Text aus Pull-Ergebnis
        pullToastText() {
            const p = this.pullErgebnis || {};
            const teile = [];
            if (p.erfolg) teile.push(p.erfolg + ' neu');
            if (p.dublette) teile.push(p.dublette + ' Dubletten');
            if (p.fehler) teile.push(p.fehler + ' Fehler');
            return teile.length === 0 ? 'Posteingang ist aktuell.' : teile.join(' · ');
        },

        // Schnelle Aktion direkt aus der Liste (Hover-Buttons)
        async schnellAktion(m, aktion) {
            if (aktion === 'archiv') {
                await fetch('/api/v1/mail/verschieben', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ ids: [m.id], ziel: 'archiv' }),
                });
                if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
            } else if (aktion === 'gelesen_toggle') {
                const neu = !parseInt(m.gelesen);
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: neu ? 'gelesen' : 'ungelesen' }),
                });
            } else if (aktion === 'als_spam') {
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: 'als_spam' }),
                });
                if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
            }
            await this.laden();
        },

        // Tastatur-Shortcuts
        handleKey(ev) {
            // Nicht in Inputs/Textareas reagieren
            const t = ev.target.tagName;
            if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || ev.target.isContentEditable) return;
            if (ev.metaKey || ev.ctrlKey || ev.altKey) return;
            const k = ev.key;
            if (k === '?') { this.shortcutsOffen = !this.shortcutsOffen; ev.preventDefault(); return; }
            if (k === 'Escape') { this.shortcutsOffen = false; return; }
            // Navigation
            const liste = this.anzeigeMails();
            const idx = liste.findIndex(x => x.id == this.aktiveMail?.id);
            if (k === 'j' || k === 'ArrowDown') {
                const next = liste[Math.min(idx + 1, liste.length - 1)];
                if (next) this.oeffneDetail(next.id);
                ev.preventDefault();
                return;
            }
            if (k === 'k' || k === 'ArrowUp') {
                const prev = liste[Math.max(idx - 1, 0)];
                if (prev) this.oeffneDetail(prev.id);
                ev.preventDefault();
                return;
            }
            if (!this.aktiveMail) return;
            if (k === 'e') { this.archivieren(); ev.preventDefault(); }
            else if (k === '!') {
                this.schnellAktion(this.aktiveMail, 'als_spam');
                ev.preventDefault();
            }
            else if (k === '#') { this.archivieren(); ev.preventDefault(); }
            else if (k === 's') { this.markiertToggle(); ev.preventDefault(); }
            else if (k === 'u') {
                this.schnellAktion(this.aktiveMail, 'gelesen_toggle');
                ev.preventDefault();
            }
            else if (k === 'r') {
                const ta = document.querySelector('.mail-col-antwort textarea');
                if (ta) { ta.focus(); ev.preventDefault(); }
            }
        },

        lamLink(v) {
            if (v.typ === 'anbieter') return '/lam/anbieter/' + v.ziel_id;
            if (v.typ === 'massnahme') return '/lam/massnahmen/' + v.ziel_id;
            if (v.typ === 'korrespondenz') return '/lam/korrespondenz';
            if (v.typ === 'aufgabe') return '/lam/aufgaben';
            return '#';
        },

        formatBytes(b) {
            b = parseInt(b);
            if (b < 1024) return b + ' B';
            if (b < 1024 * 1024) return Math.round(b / 1024) + ' KB';
            return (b / 1024 / 1024).toFixed(1) + ' MB';
        },

        async klassifizieren() {
            if (!this.aktiveMail) return;
            this.klassifiziereLaeuft = true;
            try {
                const r = await fetch('/api/v1/mail/klassifizieren', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: this.aktiveMail.id }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler.'); return; }
                // Detail nachladen
                await this.oeffneDetail(this.aktiveMail.id);
                await this.laden();
            } finally { this.klassifiziereLaeuft = false; }
        },

        async markiertToggle() {
            const r = await fetch('/api/v1/mail/nachricht-aktion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: this.aktiveMail.id, aktion: 'markiert_toggle' }),
            });
            const j = await r.json();
            if (j.success) this.aktiveMail.markiert = j.data.markiert ? 1 : 0;
            await this.laden();
        },

        oeffneCtxMenu(ev, mail) {
            // Position innerhalb des Viewports halten
            const menuBreite = 230;
            const menuHoehe = 260;
            const x = Math.min(ev.clientX, window.innerWidth - menuBreite - 8);
            const y = Math.min(ev.clientY, window.innerHeight - menuHoehe - 8);
            this.ctxMenu = { offen: true, x, y, mail };
        },

        async ctxAktion(typ) {
            const m = this.ctxMenu.mail;
            this.ctxMenu.offen = false;
            if (!m) return;
            if (typ === 'oeffnen') {
                await this.oeffneDetail(m.id);
                return;
            }
            if (typ === 'klassifizieren') {
                await this.oeffneDetail(m.id);
                await this.klassifizieren();
                return;
            }
            if (typ === 'gelesen_toggle') {
                const neuGelesen = !parseInt(m.gelesen);
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: neuGelesen ? 'gelesen' : 'ungelesen' }),
                });
                await this.laden();
                return;
            }
            if (typ === 'markiert_toggle') {
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: 'markiert_toggle' }),
                });
                await this.laden();
                return;
            }
            if (typ === 'anbieter_zuordnen') {
                await this.oeffneAnbieterPicker(m.id);
                return;
            }
            if (typ === 'als_spam') {
                if (!confirm('Mail als Spam markieren? Klassifikation wird auf „spam" gesetzt, Status auf „ignoriert".')) return;
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: 'als_spam' }),
                });
                if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
                await this.laden();
                return;
            }
            if (typ === 'kein_spam') {
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: 'kein_spam' }),
                });
                if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
                await this.laden();
                return;
            }
            if (typ === 'loeschen') {
                if (!confirm('Mail wirklich löschen (Soft-Delete)?')) return;
                await fetch('/api/v1/mail/nachricht-aktion', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ id: m.id, aktion: 'loeschen' }),
                });
                if (this.aktiveMail?.id == m.id) this.aktiveMail = null;
                await this.laden();
                return;
            }
        },

        async oeffneAnbieterPicker(mailId) {
            this.anbieterPicker.mailId = mailId;
            this.anbieterPicker.suche = '';
            this.anbieterPicker.offen = true;
            if (this.anbieterPicker.alle.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/anbieter-kurz', { credentials: 'same-origin' });
                    const j = await r.json();
                    this.anbieterPicker.alle = j.success ? j.data : [];
                } catch (e) { this.anbieterPicker.alle = []; }
            }
        },

        anbieterGefiltert() {
            const s = (this.anbieterPicker.suche || '').toLowerCase().trim();
            const liste = this.anbieterPicker.alle || [];
            if (!s) return liste.slice(0, 50);
            return liste.filter(a =>
                (a.name || '').toLowerCase().includes(s) ||
                (a.firma || '').toLowerCase().includes(s)
            ).slice(0, 50);
        },

        async anbieterZuordnen(anbieter) {
            const mailId = this.anbieterPicker.mailId;
            if (!mailId || !anbieter?.id) return;
            try {
                const r = await fetch('/api/v1/mail/lam-verknuepfen', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                    body: JSON.stringify({ mail_id: mailId, typ: 'anbieter', ziel_id: anbieter.id }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler beim Zuordnen.'); return; }
                this.anbieterPicker.offen = false;
                // Detail neu laden, falls offen
                if (this.aktiveMail?.id == mailId) await this.oeffneDetail(mailId);
                await this.laden();
            } catch (e) {
                alert('Verbindungsfehler beim Zuordnen.');
            }
        },

        async archivieren() {
            if (!confirm('Mail archivieren (Soft-Delete)?')) return;
            await fetch('/api/v1/mail/nachricht-aktion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: this.aktiveMail.id, aktion: 'loeschen' }),
            });
            this.aktiveMail = null;
            await this.laden();
        },

        async alsBeantwortetMarkieren() {
            await fetch('/api/v1/mail/nachricht-aktion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: this.aktiveMail.id, aktion: 'status_setzen', wert: 'beantwortet' }),
            });
            this.sendeErgebnis = { ok: true, text: 'Als beantwortet markiert (ohne Versand).' };
            await this.laden();
        },

        vorlageWaehlen() {
            if (this.vorlagenListe.length === 0) return;
            // Vorzugsweise passende Kategorie
            const kat = this.aktiveMail?.klassifikation?.kategorie;
            const passend = kat ? this.vorlagenListe.filter(v => v.kategorie === kat) : [];
            const auswahl = passend.length > 0 ? passend : this.vorlagenListe;
            // Einfaches Prompt-basiertes Picker
            const optionen = auswahl.map((v, i) => (i + 1) + ') ' + v.name + (v.kategorie ? ' [' + v.kategorie + ']' : '')).join('\n');
            const eingabe = prompt('Vorlage wählen (Nummer eingeben):\n\n' + optionen);
            if (!eingabe) return;
            const idx = parseInt(eingabe, 10) - 1;
            if (isNaN(idx) || idx < 0 || idx >= auswahl.length) return;
            const v = auswahl[idx];
            // Platzhalter ersetzen
            let body = v.body_template || '';
            let betreff = v.betreff_template || this.antwort.betreff;
            const ersetzungen = {
                '{{absender_name}}': this.aktiveMail?.absender_name || this.aktiveMail?.absender_email || '',
                '{{absender_email}}': this.aktiveMail?.absender_email || '',
                '{{betreff}}': this.aktiveMail?.betreff || '',
                '{{datum}}': new Date().toLocaleDateString('de-DE'),
            };
            for (const [k, val] of Object.entries(ersetzungen)) {
                body = body.split(k).join(val);
                betreff = betreff.split(k).join(val);
            }
            this.antwort.text = body;
            if (betreff) this.antwort.betreff = betreff;
        },

        originalZitieren() {
            if (this.antwort.zitatEingefuegt) return;
            const original = this.aktiveMail?.body_plain || '';
            if (!original) {
                alert('Kein Plain-Text-Original verfügbar zum Zitieren.');
                return;
            }
            const datum = this.aktiveMail?.empfangen_am || '';
            const absender = (this.aktiveMail?.absender_name || '') + ' <' + (this.aktiveMail?.absender_email || '') + '>';
            const kopf = '\n\n----- Am ' + datum + ' schrieb ' + absender + ': -----\n';
            const zitat = original.split('\n').map(z => '> ' + z).join('\n');
            this.antwort.text = (this.antwort.text || '') + kopf + zitat;
            this.antwort.zitatEingefuegt = true;
        },

        // Hybrid: den (lokal erzeugten) Entwurf bewusst von Claude in der Cloud verbessern lassen.
        async mitClaudeVerfeinern() {
            if (!this.aktiveMail || !this.antwort.text.trim()) return;
            const auftrag = prompt('Optionaler Wunsch an Claude (z.B. „kürzer", „förmlicher") — leer lassen für allgemeinen Feinschliff:', '');
            if (auftrag === null) return;   // abgebrochen
            this.verfeinereLaeuft = true;
            try {
                const res = await App.post('/mail/antwort-verfeinern', {
                    mail_id: this.aktiveMail.id,
                    entwurf: this.antwort.text,
                    auftrag: auftrag || '',
                });
                const d = res.data || res;
                if (d.text) {
                    this.antwort.text = d.text;
                    App.showNotification('Von Claude verfeinert.', 'success');
                }
            } catch (e) {
                App.showNotification(e.message || 'Verfeinern fehlgeschlagen', 'error');
            } finally {
                this.verfeinereLaeuft = false;
            }
        },

        /* ===================== Compose: Neu / Weiterleiten ===================== */

        composeReset() {
            this.compose.empfaenger = '';
            this.compose.cc = '';
            this.compose.bcc = '';
            this.compose.betreff = '';
            this.compose.text = '';
            this.compose.stichworte = '';
            this.compose.anhaenge = [];
            this.compose.vorschlaege = [];
            this.compose.originalId = null;
        },

        composeNeu() {
            if (!this.kontoId) return;
            this.composeReset();
            this.compose.modus = 'neu';
            this.compose.offen = true;
        },

        composeWeiterleiten() {
            if (!this.aktiveMail) return;
            this.composeReset();
            this.compose.modus = 'weiterleiten';
            this.compose.originalId = this.aktiveMail.id;
            const b = this.aktiveMail.betreff || '';
            this.compose.betreff = /^(fwd|wg):/i.test(b) ? b : 'Fwd: ' + b;
            // Original-Anhaenge vorbefuellen (alle angehakt) — Thomas kann einzelne abwaehlen
            this.compose.anhaenge = (this.aktiveMail.anhaenge || []).map(a => ({ ...a, mit: true }));
            this.compose.offen = true;
        },

        async composeSuchen() {
            const q = this.compose.empfaenger.trim();
            if (q.length < 2) { this.compose.vorschlaege = []; return; }
            try {
                const r = await fetch('/api/v1/mail/empfaenger-vorschlaege?q=' + encodeURIComponent(q));
                const j = await r.json();
                this.compose.vorschlaege = (j.success ? j.data : []).slice(0, 8);
            } catch (e) { this.compose.vorschlaege = []; }
        },

        async composeEntwurf() {
            this.compose.kiLaeuft = true;
            try {
                if (this.compose.modus === 'weiterleiten') {
                    const res = await App.post('/mail/weiterleiten-entwurf', {
                        mail_id: this.compose.originalId, stichworte: this.compose.stichworte,
                    });
                    const d = res.data || res;
                    if (d.text) this.compose.text = d.text;
                } else {
                    if (!this.compose.stichworte.trim()) { App.showNotification('Bitte Stichworte eingeben.', 'error'); return; }
                    const res = await App.post('/mail/neue-mail-entwurf', {
                        konto_id: this.kontoId, stichworte: this.compose.stichworte, empfaenger: this.compose.empfaenger,
                    });
                    const d = res.data || res;
                    if (d.betreff && !this.compose.betreff) this.compose.betreff = d.betreff;
                    if (d.text) this.compose.text = d.text;
                }
                App.showNotification('Entwurf erstellt.', 'success');
            } catch (e) {
                App.showNotification(e.message || 'Entwurf fehlgeschlagen', 'error');
            } finally { this.compose.kiLaeuft = false; }
        },

        // Feinschliff per Claude — nur im Neu-Modus sinnvoll (Antwort-Verfeinern braucht eine Original-Mail).
        async composeVerfeinern() {
            if (!this.compose.text.trim()) return;
            this.compose.kiLaeuft = true;
            try {
                const mailId = this.compose.originalId || 0;   // bei „neu" 0 -> Endpoint nutzt konto_id + Stil
                const res = await App.post('/mail/antwort-verfeinern', {
                    mail_id: mailId, konto_id: this.kontoId, entwurf: this.compose.text, auftrag: '',
                });
                const d = res.data || res;
                if (d.text) { this.compose.text = d.text; App.showNotification('Von Claude verfeinert.', 'success'); }
            } catch (e) {
                App.showNotification(e.message || 'Verfeinern fehlgeschlagen', 'error');
            } finally { this.compose.kiLaeuft = false; }
        },

        async composeSenden() {
            const c = this.compose;
            if (!c.empfaenger.trim()) { App.showNotification('Empfänger fehlt.', 'error'); return; }
            if (!c.betreff.trim())    { App.showNotification('Betreff fehlt.', 'error'); return; }
            if (!c.text.trim())       { App.showNotification('Text fehlt.', 'error'); return; }
            const ccArr  = c.cc.split(',').map(s => s.trim()).filter(Boolean);
            const bccArr = c.bcc.split(',').map(s => s.trim()).filter(Boolean);
            c.sendeLaeuft = true;
            try {
                if (c.modus === 'weiterleiten') {
                    const anhangIds = c.anhaenge.filter(a => a.mit).map(a => a.id);
                    await App.post('/mail/weiterleiten', {
                        mail_id: c.originalId, konto_id: this.kontoId, empfaenger: c.empfaenger,
                        betreff: c.betreff, begleittext: c.text, cc: ccArr, bcc: bccArr, anhang_ids: anhangIds,
                    });
                } else {
                    await App.post('/mail/mail-senden', {
                        konto_id: this.kontoId, empfaenger: c.empfaenger, betreff: c.betreff,
                        text: c.text, cc: ccArr, bcc: bccArr,
                    });
                }
                App.showNotification('Gesendet.', 'success');
                c.offen = false;
                this.laden();
            } catch (e) {
                App.showNotification(e.message || 'Versand fehlgeschlagen', 'error');
            } finally { c.sendeLaeuft = false; }
        },

        async anhangHinzufuegen() {
            const dateien = this.$refs.anhangInput?.files;
            if (!dateien || dateien.length === 0) return;
            this.anhangLaeuft = true;
            try {
                for (const f of dateien) {
                    const fd = new FormData();
                    fd.append('anhang', f);
                    const r = await fetch('/api/v1/mail/antwort-anhang-upload', {
                        method: 'POST', credentials: 'same-origin', body: fd,
                    });
                    const j = await r.json();
                    if (j.success) {
                        this.antwort.anhaenge.push({
                            pfad: j.data.pfad,
                            name: j.data.name,
                            mime: j.data.mime,
                            size: j.data.size,
                        });
                    } else {
                        alert('Upload-Fehler bei „' + f.name + '": ' + (j.error || 'unbekannt'));
                    }
                }
                // Input leeren, damit gleicher Dateiname erneut hochgeladen werden kann
                if (this.$refs.anhangInput) this.$refs.anhangInput.value = '';
            } finally { this.anhangLaeuft = false; }
        },

        async senden() {
            if (!confirm('Antwort wirklich an „' + this.antwort.empfaenger + '" senden?')) return;
            this.sendeLaeuft = true;
            this.sendeErgebnis = null;
            try {
                const r = await fetch('/api/v1/mail/antwort-senden', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        id: this.aktiveMail.id,
                        finaler_text: this.antwort.text,
                        betreff: this.antwort.betreff,
                        empfaenger: this.antwort.empfaenger,
                        ki_vorschlag: this.aktiveMail?.klassifikation?.vorgeschlagene_antwort,
                        anhaenge: this.antwort.anhaenge.map(a => ({ pfad: a.pfad, name: a.name, mime: a.mime })),
                    }),
                });
                const j = await r.json();
                if (j.success) {
                    this.sendeErgebnis = { ok: true, text: '✓ Versendet. Mail-ID: ' + j.data.ausgang_mail_id };
                    if (j.data.stop_wort_gefunden) this.stopWortWarnung = j.data.stop_wort_gefunden;
                    await this.laden();
                } else {
                    this.sendeErgebnis = { ok: false, text: '✗ ' + (j.error || j.message || 'Fehler.') };
                }
            } catch (e) {
                this.sendeErgebnis = { ok: false, text: '✗ Verbindungsfehler' };
            } finally { this.sendeLaeuft = false; }
        },

        oeffneUpload() {
            if (!this.kontoId) { alert('Bitte zuerst ein Konto wählen.'); return; }
            this.uploadOffen = true;
            this.uploadErgebnis = null;
        },

        extrahierteKondition() {
            const meta = this.aktiveMail?.klassifikation?.ki_meta;
            if (!meta) return null;
            try {
                const parsed = typeof meta === 'string' ? JSON.parse(meta) : meta;
                const f = parsed?.extrahierte_felder;
                if (!f) return null;
                if (f.preis === null && !f.linktext && !f.veroeffentlichungs_url) return null;
                return {
                    preis: f.preis,
                    buchungstyp: f.buchungstyp || 'gastartikel',
                    linktext: f.linktext,
                    ziel_url: f.linkziel || f.veroeffentlichungs_url,
                };
            } catch (e) { return null; }
        },

        verknuepfteMassnahme() {
            return (this.aktiveMail?.lam_verknuepfungen || []).find(v => v.typ === 'massnahme');
        },

        async oeffneKonditionAnlegen() {
            const k = this.extrahierteKondition();
            const anbieterVk = (this.aktiveMail?.lam_verknuepfungen || []).find(v => v.typ === 'anbieter');
            if (!anbieterVk) { alert('Kein Anbieter mit dieser Mail verknüpft.'); return; }
            this.kondDrawer.offen = true;
            this.kondDrawer.domain_id = '';
            this.kondDrawer.buchungstyp = k?.buchungstyp || 'gastartikel';
            this.kondDrawer.preis = k?.preis || '';
            this.kondDrawer.link_typ = 'follow';
            this.kondDrawer.notiz = 'Aus Mail #' + this.aktiveMail.id + ' (' + (this.aktiveMail.absender_email || '') + ')';
            // Domains des Anbieters laden
            try {
                const r = await fetch('/api/v1/lam/anbieter-detail?id=' + encodeURIComponent(anbieterVk.ziel_id), { credentials: 'same-origin' });
                const j = await r.json();
                this.kondDomains = j.success ? (j.data?.domains || []) : [];
                if (this.kondDomains.length === 1) this.kondDrawer.domain_id = this.kondDomains[0].id;
            } catch (e) { this.kondDomains = []; }
        },

        async speichereKondition() {
            this.kondDrawer.laeuft = true;
            try {
                const r = await fetch('/api/v1/mail/lam-kondition-anlegen', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        mail_id: this.aktiveMail.id,
                        domain_id: this.kondDrawer.domain_id,
                        buchungstyp: this.kondDrawer.buchungstyp,
                        preis: this.kondDrawer.preis || null,
                        link_typ: this.kondDrawer.link_typ,
                        notiz: this.kondDrawer.notiz,
                    }),
                });
                const j = await r.json();
                if (j.success) {
                    this.kondDrawer.offen = false;
                    this.lamAktionsErgebnis = { ok: true, text: '✓ ' + j.data.meldung };
                    // Mail neu laden für aktualisierte Verknüpfungen
                    await this.oeffneDetail(this.aktiveMail.id);
                } else {
                    alert(j.error || j.message || 'Fehler.');
                }
            } finally { this.kondDrawer.laeuft = false; }
        },

        async setzeMassnahmeStatus() {
            if (!this.statusVorschlag) return;
            if (!confirm('Maßnahme-Status auf „' + this.statusVorschlag + '" setzen?')) return;
            try {
                const r = await fetch('/api/v1/mail/lam-massnahme-status', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ mail_id: this.aktiveMail.id, neuer_status: this.statusVorschlag }),
                });
                const j = await r.json();
                if (j.success) {
                    this.lamAktionsErgebnis = { ok: true, text: '✓ Maßnahme-Status auf „' + j.data.neuer_status + '" gesetzt.' };
                    this.statusVorschlag = '';
                } else {
                    this.lamAktionsErgebnis = { ok: false, text: '✗ ' + (j.error || 'Fehler.') };
                }
            } catch (e) {
                this.lamAktionsErgebnis = { ok: false, text: '✗ Verbindungsfehler' };
            }
        },

        async uploadAusfuehren() {
            const dateien = this.$refs.emlInput?.files;
            if (!dateien || dateien.length === 0) return;
            this.uploadLaeuft = true;
            try {
                const fd = new FormData();
                fd.append('konto_id', this.kontoId);
                for (const f of dateien) fd.append('eml[]', f);
                const r = await fetch('/api/v1/mail/eml-upload', {
                    method: 'POST', credentials: 'same-origin', body: fd,
                });
                const j = await r.json();
                if (j.success) {
                    this.uploadErgebnis = { titel: `${j.data.erfolg} importiert · ${j.data.dublette} Dubletten · ${j.data.fehler} Fehler`, details: j.data.details };
                    await this.laden();
                } else {
                    this.uploadErgebnis = { titel: 'Fehler: ' + (j.error || ''), details: [] };
                }
            } finally { this.uploadLaeuft = false; }
        },
    };
}
</script>
