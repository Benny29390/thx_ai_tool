<?php $activeModul = 'linkoptionen'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamLinkoptionen()" x-init="initFilter(); laden()" @click="ctxMenu.offen = false; poolCtx.offen = false">

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Linkoptionen</h1>
        <div class="thx-page-subtitle">
            <span x-show="tab === 'pool' && !filter.customer_id">Wähle einen Kunden, um seinen Linkpool zu sehen.</span>
            <span x-show="tab === 'pool' && filter.customer_id">
                <span x-text="rows.length"></span> Domains im Linkpool von <strong x-text="kundenName(filter.customer_id)"></strong>
            </span>
            <span x-show="tab === 'auswahl'">Vorschlagslisten-Einträge im Status-Lebenszyklus</span>
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <a x-show="filter.customer_id" x-cloak class="lam-btn lam-btn-secondary"
           :href="'/admin/customers/' + filter.customer_id + '/steckbrief#lam-asana'"
           :title="'Asana-Konfig für ' + kundenName(filter.customer_id) + ' im Kunden-Steckbrief'">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;margin-right:4px;">settings</span>
            Kunden-Einstellungen
        </a>
        <a href="/lam/ki-vorschlaege" class="lam-btn lam-btn-secondary" title="KI schlägt passende Pool-Domains pro Kunde vor">🤖 KI-Vorschläge</a>
        <a href="/lam/vorschlagslisten" class="lam-btn lam-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">list</span>
            Vorschlagslisten
        </a>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<!-- Vorschlagslisten des aktuellen Kunden als Karten-Reihe (zentrale Funktion direkt zugänglich) -->
<section x-show="filter.customer_id && kundenVorschlagslisten.length > 0" style="margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <h3 style="margin:0;font-size:0.95rem;">
            <span style="color:var(--slate-500);">Vorschlagslisten für</span>
            <strong x-text="kundenName(filter.customer_id)"></strong>
        </h3>
        <a href="/lam/vorschlagslisten" style="font-size:0.8rem;color:var(--thoxan-700);">alle anzeigen →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:10px;">
        <template x-for="l in kundenVorschlagslisten" :key="l.id">
            <a :href="'/lam/vorschlagslisten/' + encodeURIComponent(l.id)"
               style="background:#fff;border:1px solid var(--slate-200);border-left:4px solid var(--thoxan-500);border-radius:8px;padding:12px 14px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:4px;transition:box-shadow .12s, transform .12s;"
               onmouseover="this.style.boxShadow='0 6px 20px rgba(15,23,42,0.08)';this.style.transform='translateY(-1px)';"
               onmouseout="this.style.boxShadow='none';this.style.transform='none';">
                <div style="font-weight:600;color:var(--slate-800);font-size:0.95rem;" x-text="l.name"></div>
                <div style="font-size:0.75rem;color:var(--slate-500);">
                    <span x-text="(l.eintrag_count || 0) + ' Einträge'"></span>
                    <span x-show="l.zielzahl" style="color:var(--slate-400);"> · Ziel <span x-text="l.zielzahl"></span></span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                    <span x-show="l.status" :style="vlStatusStyle(l.status)" style="font-size:0.65rem;padding:1px 6px;border-radius:99px;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;" x-text="vlStatusLabel(l.status)"></span>
                </div>
            </a>
        </template>
        <a href="/lam/vorschlagslisten?neu=1"
           style="background:var(--slate-50);border:1px dashed var(--slate-300);border-radius:8px;padding:14px;text-decoration:none;color:var(--slate-600);display:flex;align-items:center;justify-content:center;gap:6px;font-size:0.85rem;font-weight:500;">
            <span>+ Neue Liste anlegen</span>
        </a>
    </div>
</section>

<!-- Linkpool-vs-Vorschlagslisten-Tab-Switch -->
<div class="thx-tabs" style="margin-bottom:16px;">
    <button class="thx-tab" :class="tab === 'pool' ? 'is-active' : ''" @click="wechsleTab('pool')"
            title="Pro Kunde kuratierte Vorauswahl aus den Linkquellen — Ausgangsbasis für Vorschlagslisten">
        Linkpool (pro Kunde)
    </button>
    <button class="thx-tab" :class="tab === 'auswahl' ? 'is-active' : ''" @click="wechsleTab('auswahl')"
            title="Konkrete Vorschlagslisten-Einträge im Status-Lebenszyklus">
        Vorschlagslisten-Einträge
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     POOL-TAB: Linkquellen aus dem Pool mit Kunden-/Verifikations-/Linkart-Filtern
     ═══════════════════════════════════════════════════════════════════ -->
<template x-if="tab === 'pool'">
    <div>
    <section class="lam-filter-card">
        <div class="lam-filter-head" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>Filter</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <button type="button" @click="filterZuruecksetzen()"
                        style="font-size:0.75rem;color:var(--slate-500);background:none;border:0;cursor:pointer;text-decoration:underline;">
                    zurücksetzen
                </button>
                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="erweitert = !erweitert">
                    <span x-text="erweitert ? '▴ Weniger Filter' : '▾ Weitere Filter'"></span>
                </button>
            </div>
        </div>

        <!-- Hauptfilter-Zeile: Suche + Kunden-Pills + Status-in-Auswahl -->
        <div class="lam-filter-grid">
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Volltext URL / Notizen</label>
                <input type="text" class="lam-filter-input" placeholder="z.B. blog"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
            </div>
            <div class="lam-filter-col-5">
                <label class="lam-filter-label">
                    Kunde
                    <span style="color:var(--slate-400);font-weight:normal;font-size:var(--d-fs-xs);">Klick = wechseln · nochmal = alle</span>
                </label>
                <div class="lam-chip-row">
                    <template x-for="k in kunden" :key="k.id">
                        <button class="lam-chip"
                                :class="filter.customer_id == k.id ? 'is-active' : ''"
                                @click="setzeKunde(filter.customer_id == k.id ? '' : k.id)"
                                :title="k.name + ' · ' + (k.linkpool_count || 0) + ' im Linkpool · ' + (k.eintrag_count || 0) + ' Vorschlagslisten-Einträge'"
                                x-text="k.abbreviation || k.name"></button>
                    </template>
                </div>
            </div>
            <div class="lam-filter-col-3">
                <label class="lam-filter-label">Status in Auswahl</label>
                <select class="lam-filter-select" x-model="filter.status_auswahl" @change="laden()">
                    <option value="">alle</option>
                    <option value="ohne">— ohne aktive Auswahl —</option>
                    <template x-for="s in statusListe" :key="s.slug">
                        <option :value="s.slug" x-text="s.label"></option>
                    </template>
                </select>

                <label class="lam-filter-label" style="margin-top:10px;">Bewertung <span style="font-weight:400;color:var(--slate-400);">Mehrfachauswahl</span></label>
                <div class="lam-chip-row">
                    <template x-for="b in bewertungen" :key="b">
                        <button type="button" class="lam-chip"
                                :class="filter.bewertung.includes(b) ? 'is-active' : ''"
                                @click="toggleBewertung(b)"
                                x-text="bewertungLabels[b] || b"></button>
                    </template>
                </div>

                <label class="lam-filter-label" style="margin-top:10px;">Laut Linkprofil</label>
                <select class="lam-filter-select" x-model="filter.verlinkt" @change="laden()"
                        title="Abgleich mit dem Linkprofil des Kunden: verlinkt die Quelle ihn bereits?">
                    <option value="">alle</option>
                    <option value="nein">nur neue Quellen (noch nie verlinkt)</option>
                    <option value="ja">nur bereits verlinkte</option>
                </select>
            </div>
        </div>

        <!-- Erweiterte Filter: Verifikation, Linkart, Bereiche -->
        <template x-if="erweitert">
            <div style="margin-top:12px;">
                <div class="lam-filter-grid" style="margin-bottom:10px;">
                    <div class="lam-filter-col-12">
                        <label class="lam-filter-label">
                            Verifikation
                            <span style="color:var(--slate-400);font-weight:normal;font-size:var(--d-fs-xs);">Klick = nur dieser · Shift/Ctrl = mehrere</span>
                        </label>
                        <div class="lam-chip-row">
                            <template x-for="v in verifikationListe" :key="v">
                                <button class="lam-chip"
                                        :class="filter.verifikation.includes(v) ? 'is-active' : ''"
                                        @click="toggleVerifikation($event, v)"
                                        x-text="v"></button>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="lam-filter-grid" style="margin-bottom:10px;">
                    <div class="lam-filter-col-12">
                        <label class="lam-filter-label">
                            Linkart
                            <span style="color:var(--slate-400);font-weight:normal;font-size:var(--d-fs-xs);">Klick = nur diese · Shift/Ctrl = mehrere</span>
                        </label>
                        <div class="lam-chip-row">
                            <template x-for="la in linkartListe" :key="la">
                                <button class="lam-chip"
                                        :class="filter.linkart.includes(la) ? 'is-active' : ''"
                                        @click="toggleLinkart($event, la)"
                                        x-text="linkartLabels[la] || la"></button>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="lam-filter-grid">
                    <div class="lam-filter-col-4">
                        <label class="lam-filter-label">Sistrix-Bereich (SI)</label>
                        <div style="display:flex;gap:6px;">
                            <input type="number" step="0.0001" class="lam-filter-input" placeholder="min"
                                   x-model="filter.si_min" @input.debounce.400ms="laden()" style="width:50%;">
                            <input type="number" step="0.0001" class="lam-filter-input" placeholder="max"
                                   x-model="filter.si_max" @input.debounce.400ms="laden()" style="width:50%;">
                        </div>
                    </div>
                    <div class="lam-filter-col-4">
                        <label class="lam-filter-label">Preis-Bereich (EUR)</label>
                        <div style="display:flex;gap:6px;">
                            <input type="number" step="1" class="lam-filter-input" placeholder="min"
                                   x-model="filter.preis_min" @input.debounce.400ms="laden()" style="width:50%;">
                            <input type="number" step="1" class="lam-filter-input" placeholder="max"
                                   x-model="filter.preis_max" @input.debounce.400ms="laden()" style="width:50%;">
                        </div>
                    </div>
                    <div class="lam-filter-col-4" style="display:flex;align-items:flex-end;">
                        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="filterReset()">Filter zurücksetzen</button>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <!-- Hinweis-Banner wenn der Pool leer / kein Kunde -->
    <div x-show="poolHinweis" x-cloak style="margin:0 0 14px 0;padding:14px 18px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;color:#854d0e;font-size:var(--d-fs-sm);">
        💡 <span x-text="poolHinweis"></span>
        <template x-if="filter.customer_id">
            <button class="lam-btn lam-btn-primary lam-btn-small" @click="oeffneLinkpoolAdd()" style="margin-left:12px;">
                ➕ Domains zum Linkpool hinzufügen
            </button>
        </template>
    </div>

    <!-- Aktionsleiste: "Domain zum Linkpool" steht immer bereit, wenn Kunde gewählt -->
    <div x-show="filter.customer_id && !poolHinweis" x-cloak style="margin-bottom:10px;display:flex;justify-content:flex-end;">
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneLinkpoolAdd()">
            ➕ Domains zum Linkpool hinzufügen
        </button>
    </div>

    <!-- Bulk-Toolbar Linkpool -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak>
        <span class="thx-bulk-count"><span x-text="auswahl.size"></span> ausgewählt</span>
        <span class="thx-divider"></span>
        <!-- Bewertung für die ganze Auswahl auf einmal setzen (schnellste Triage bei vielen Zeilen) -->
        <select x-model="bulkBewertung" class="lam-filter-select" style="width:auto;" :disabled="bulkLaeuft">
            <option value="">Bewertung setzen …</option>
            <template x-for="b in bewertungen" :key="'bulk-' + b">
                <option :value="b" x-text="bewertungLabels[b] || b"></option>
            </template>
        </select>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="bulkBewertungSetzen()"
                :disabled="bulkLaeuft || !bulkBewertung">Anwenden</button>
        <span class="thx-divider"></span>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="poolAufVorschlagsliste()" :disabled="bulkLaeuft || !filter.customer_id"
                :title="!filter.customer_id ? 'Erst einen Kunden im Filter auswählen' : ('Ausgewählte Domains auf Vorschlagsliste von ' + kundenName(filter.customer_id) + ' setzen')">
            📋 Auf Vorschlagsliste · <span x-text="auswahl.size"></span>
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="kiLinkart()" :disabled="bulkLaeuft"
                title="Ordnet den ausgewählten Quellen per KI eine Linkart aus dem bestehenden Vokabular zu (Blog, Branchenverzeichnis, Presseportal …). Bereits gesetzte bleiben unberührt. Danach ist die Linkart-Filterleiste als Subfilter nutzbar.">
            🤖 Linkart per KI · <span x-text="auswahl.size"></span>
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="entferneAusLinkpool()" :disabled="bulkLaeuft"
                style="color:var(--rose-700);border-color:var(--rose-300);"
                :title="'Aus dem Linkpool von ' + kundenName(filter.customer_id) + ' entfernen (Domain bleibt im globalen Linkquellen-Pool)'">
            ➖ Aus Linkpool entfernen · <span x-text="auswahl.size"></span>
        </button>
        <button class="thx-bulk-clear" @click="auswahlLeeren()">Auswahl aufheben</button>
    </div>

    <!-- Confirm-Modal (statt window.confirm) -->
    <div class="thx-modal-backdrop" x-show="confirmModal.offen" x-cloak @click.self="confirmModal.offen = false">
        <div class="thx-modal" style="max-width:480px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title" x-text="confirmModal.titel"></h2>
                <button class="thx-modal-close" @click="confirmModal.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:10px;">
                <div style="font-size:0.9rem;color:var(--slate-800);line-height:1.5;" x-text="confirmModal.text"></div>
                <div x-show="confirmModal.hinweis" style="font-size:var(--d-fs-sm);color:var(--slate-500);background:var(--slate-50);padding:10px 12px;border-radius:6px;border-left:3px solid var(--slate-300);" x-text="confirmModal.hinweis"></div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                    <button class="lam-btn lam-btn-secondary" @click="confirmModal.offen = false">Abbrechen</button>
                    <button class="lam-btn"
                            :class="confirmModal.ist_destruktiv ? '' : 'lam-btn-primary'"
                            :style="confirmModal.ist_destruktiv ? 'background:var(--rose-600);color:#fff;border:0;' : ''"
                            @click="confirmAusfuehren()"
                            x-text="confirmModal.bestaetigenLabel"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Auf Vorschlagsliste setzen (bestehende ODER neue Liste) -->
    <div class="thx-modal-backdrop" x-show="aufListe.offen" x-cloak @click.self="aufListe.offen = false">
        <div class="thx-modal" style="max-width:600px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title">📋 Auf Vorschlagsliste setzen</h2>
                <button class="thx-modal-close" @click="aufListe.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
                <div style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    <strong x-text="auswahl.size"></strong> Domain(s) aus dem Linkpool von
                    <strong x-text="kundenName(filter.customer_id)"></strong> auf eine Vorschlagsliste setzen.
                </div>

                <!-- Mini-Tab: Bestehende Liste / Neue anlegen -->
                <div style="display:flex;gap:6px;border-bottom:1px solid var(--slate-200);">
                    <button @click="aufListe.modus = 'auswaehlen'"
                            :style="aufListe.modus === 'auswaehlen' ? 'border-bottom:2px solid var(--thoxan-700);color:var(--thoxan-700);font-weight:600;' : 'color:var(--slate-500);'"
                            :disabled="aufListe.listen.length === 0"
                            style="background:none;border:0;cursor:pointer;padding:8px 12px;border-radius:0;">
                        Bestehende Liste
                        <span x-show="aufListe.listen.length > 0" x-text="'(' + aufListe.listen.length + ')'" style="font-weight:normal;color:var(--slate-400);"></span>
                    </button>
                    <button @click="aufListe.modus = 'neu'"
                            :style="aufListe.modus === 'neu' ? 'border-bottom:2px solid var(--thoxan-700);color:var(--thoxan-700);font-weight:600;' : 'color:var(--slate-500);'"
                            style="background:none;border:0;cursor:pointer;padding:8px 12px;border-radius:0;">
                        Neue Liste anlegen
                    </button>
                </div>

                <!-- Mode A: Bestehende Liste wählen -->
                <template x-if="aufListe.modus === 'auswaehlen'">
                    <div style="display:flex;flex-direction:column;gap:8px;max-height:340px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;">
                        <template x-for="l in aufListe.listen" :key="l.id">
                            <label :style="aufListe.listenId == l.id ? 'background:var(--thoxan-50);border-color:var(--thoxan-300);' : ''"
                                   style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid var(--slate-100);cursor:pointer;">
                                <input type="radio" name="aufListe" :value="l.id" x-model="aufListe.listenId" style="margin-top:3px;">
                                <div style="flex:1;">
                                    <div style="font-weight:600;color:var(--slate-800);" x-text="l.name"></div>
                                    <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                                        <span class="lam-chip" style="font-size:0.65rem;padding:1px 6px;" x-text="l.status"></span>
                                        <span x-text="(l.eintrag_count || 0) + ' Einträge'"></span>
                                        <span x-show="l.zielzahl"> · Ziel: <span x-text="l.zielzahl"></span></span>
                                    </div>
                                </div>
                            </label>
                        </template>
                        <div x-show="aufListe.listen.length === 0" style="padding:16px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
                            Noch keine Vorschlagslisten für diesen Kunden.
                        </div>
                    </div>
                </template>

                <!-- Mode B: Neue Liste anlegen -->
                <template x-if="aufListe.modus === 'neu'">
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div class="thx-form-field">
                            <label>Name der neuen Liste *</label>
                            <input type="text" x-model="aufListe.neuerName" placeholder="z.B. Pool Q2 2026">
                        </div>
                        <div style="display:flex;gap:10px;">
                            <div class="thx-form-field" style="flex:1;">
                                <label>Status</label>
                                <select x-model="aufListe.neuerStatus">
                                    <option value="entwurf">entwurf</option>
                                    <option value="aktiv">aktiv</option>
                                </select>
                            </div>
                            <div class="thx-form-field" style="flex:1;">
                                <label>Zielzahl (optional)</label>
                                <input type="number" x-model="aufListe.neueZielzahl" placeholder="z.B. 15">
                            </div>
                        </div>
                        <div class="thx-form-field">
                            <label>Notiz (optional)</label>
                            <textarea x-model="aufListe.neueNotiz" rows="2" placeholder="Kurzbeschreibung der Liste"></textarea>
                        </div>
                    </div>
                </template>

                <div style="display:flex;gap:8px;justify-content:flex-end;border-top:1px solid var(--slate-100);padding-top:12px;">
                    <button class="lam-btn lam-btn-secondary" @click="aufListe.offen = false">Abbrechen</button>
                    <button class="lam-btn lam-btn-primary" @click="aufListeAusfuehren()"
                            :disabled="aufListe.laeuft || (aufListe.modus === 'auswaehlen' && !aufListe.listenId) || (aufListe.modus === 'neu' && !aufListe.neuerName.trim())">
                        <span x-show="!aufListe.laeuft" x-text="aufListe.modus === 'neu' ? 'Liste anlegen + Domains setzen' : (auswahl.size + ' Domain(s) hinzufügen')"></span>
                        <span x-show="aufListe.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Domains aus Linkquellen zum Linkpool hinzufügen -->
    <div class="thx-modal-backdrop" x-show="addLinkpool.offen" x-cloak @click.self="addLinkpool.offen = false">
        <div class="thx-modal" style="max-width:720px;">
            <div class="thx-modal-header">
                <h2 class="thx-modal-title">Domains zum Linkpool hinzufügen</h2>
                <button class="thx-modal-close" @click="addLinkpool.offen = false">×</button>
            </div>
            <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:12px;">
                <div style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    Suche in den globalen Linkquellen, hake die passenden Domains an, hinzufügen → wandern in den Linkpool von
                    <strong x-text="kundenName(filter.customer_id)"></strong>.
                </div>
                <input type="text" class="lam-filter-input" placeholder="Suche (URL, Notiz, Anbieter) — mindestens 2 Zeichen"
                       x-model="addLinkpool.suche" @input.debounce.300ms="sucheLinkquellen()">
                <div x-show="addLinkpool.suche.length >= 2" style="max-height:380px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;">
                    <template x-for="d in addLinkpool.treffer" :key="d.id">
                        <div @click="addLinkpool.gewaehlt[d.id] = !addLinkpool.gewaehlt[d.id]"
                             :style="addLinkpool.gewaehlt[d.id] ? 'background:var(--thoxan-50);' : ''"
                             style="padding:8px 12px;border-bottom:1px solid var(--slate-100);cursor:pointer;display:flex;align-items:center;gap:10px;">
                            <input type="checkbox" :checked="!!addLinkpool.gewaehlt[d.id]" @click.stop="addLinkpool.gewaehlt[d.id] = !addLinkpool.gewaehlt[d.id]">
                            <div style="flex:1;">
                                <div style="font-weight:600;" x-text="d.url"></div>
                                <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                                    <span x-text="d.anbieter_name || '—'"></span>
                                    <span x-show="d.si_aktuell !== null"> · SI <span x-text="parseFloat(d.si_aktuell).toFixed(4)"></span></span>
                                    <span x-show="d.imLinkpool" style="color:var(--emerald-700);"> · bereits im Linkpool</span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="addLinkpool.treffer.length === 0" style="padding:14px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
                        Keine Treffer.
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button class="lam-btn lam-btn-secondary" @click="addLinkpool.offen = false">Abbrechen</button>
                    <button class="lam-btn lam-btn-primary" @click="fuegeZuLinkpoolHinzu()" :disabled="anzahlGewaehltLinkpool() === 0 || addLinkpool.laeuft">
                        <span x-show="!addLinkpool.laeuft" x-text="anzahlGewaehltLinkpool() + ' Domain(s) hinzufügen'"></span>
                        <span x-show="addLinkpool.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="lam-table-card">
        <!-- Spaltenbreiten: ziehen am Trennstrich ODER exakte Werte eintippen. Wird gemerkt. -->
        <div style="display:flex;justify-content:flex-end;gap:8px;align-items:center;margin-bottom:6px;">
            <button type="button" class="lam-btn lam-btn-secondary lam-btn-small" @click="breitenPanel = !breitenPanel">
                ⇔ Spaltenbreiten
            </button>
        </div>
        <div x-show="breitenPanel" x-cloak class="lam-filter-card" style="margin-bottom:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:10px 18px;align-items:flex-end;">
                <template x-for="s in poolSpalten" :key="s.key">
                    <div style="display:flex;flex-direction:column;gap:2px;">
                        <label class="lam-filter-label" x-text="s.label"></label>
                        <input type="number" min="40" max="900" step="10" style="width:80px;"
                               :value="spaltenBreiten[s.key]"
                               @input="setzeBreite(s.key, $event.target.value)">
                    </div>
                </template>
                <button type="button" class="lam-btn lam-btn-secondary lam-btn-small" @click="breitenZuruecksetzen()">Zurücksetzen</button>
            </div>
            <div style="margin-top:8px;color:var(--slate-500);font-size:var(--fs-sm);">
                Du kannst die Breiten auch direkt am Trennstrich zwischen zwei Spaltenköpfen ziehen. Beides wird gespeichert.
            </div>
        </div>

        <div class="lam-table-wrap">
            <table class="lam-table lam-table-fixed">
                <thead>
                    <tr>
                        <th class="thx-bulk-col" :style="breiteStil('check')">
                            <input type="checkbox" class="thx-bulk-checkbox" :checked="alleSichtbarGewaehlt()" @change="toggleAlleSichtbar()">
                            <span class="col-resizer" @click.stop.prevent @mousedown.stop.prevent="startResize($event, 'check')"></span>
                        </th>
                        <template x-for="s in poolSpalten" :key="s.key">
                            <th class="sortable" :class="[poolSort.feld === s.sort ? 'is-sorted' : '', s.right ? 'right' : '']"
                                :style="breiteStil(s.key)" :title="s.hint || ''"
                                @click="sortierePool(s.sort)">
                                <span x-text="s.label"></span> <span x-text="sortPfeil(s.sort)"></span>
                                <span class="col-resizer" @click.stop.prevent @mousedown.stop.prevent="startResize($event, s.key)"></span>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="d in poolSortiert()" :key="d.id">
                        <tr :class="auswahl.has(d.id) ? 'is-bulk-selected' : ''"
                            @contextmenu.prevent="oeffnePoolCtx($event, d)">
                            <td class="thx-bulk-col"><input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(d.id)" @change="toggleAuswahl(d.id)" @click.stop></td>
                            <!-- Redaktionelles Urteil aus der Recherche-Datei — der schnellste Weg zur Shortlist -->
                            <td>
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'bewertung')">
                                    <button class="thx-inline-edit" style="text-align:left;width:100%;"
                                            @click.stop="oeffnePoolEdit(d, 'bewertung')" title="Bewertung ändern">
                                        <span x-show="d.bewertung" class="lam-chip"
                                              :style="bewertungStil(d.bewertung)"
                                              x-text="bewertungLabels[d.bewertung] || d.bewertung"></span>
                                        <span x-show="!d.bewertung" class="empty">—</span>
                                    </button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'bewertung'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="">— keine —</option>
                                            <template x-for="b in bewertungen" :key="b">
                                                <option :value="b" x-text="bewertungLabels[b] || b"></option>
                                            </template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePoolInline(d, 'bewertung')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td class="url-cell">
                                <a :href="'/lam/linkquellen/' + encodeURIComponent(d.id)" style="color:var(--thoxan-700);" x-text="d.url"></a>
                                <a :href="extUrl(d.url)" target="_blank" rel="noopener" @click.stop
                                   title="Website in neuem Tab öffnen" style="color:var(--slate-400);text-decoration:none;padding:0 4px;">
                                    <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">open_in_new</span>
                                </a>
                            </td>
                            <!-- Was IST diese Quelle? Der Themen-Cluster aus dem Import — die eigentliche
                                 Entscheidungsgrundlage, bisher im Notiz-Freitext vergraben. -->
                            <!-- Volltext, kein Tooltip-Versteckspiel: der Begruendungstext ist die
                                 wichtigste Entscheidungshilfe und muss lesbar sein. -->
                            <td class="lp-beschreibung">
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'beschreibung')">
                                    <button class="thx-inline-edit" :class="!d.beschreibung ? 'is-empty' : ''"
                                            style="text-align:left;width:100%;white-space:normal;word-break:break-word;line-height:1.5;"
                                            @click.stop="oeffnePoolEdit(d, 'beschreibung')" title="Beschreibung bearbeiten"
                                            x-text="d.beschreibung || '—'"></button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'beschreibung'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <textarea class="thx-inline-edit-input" rows="5" style="width:100%;resize:vertical;"
                                                  x-model="editWert" x-init="$el.focus()"
                                                  @keydown.enter.meta="speicherePoolInline(d, 'beschreibung')"
                                                  @keydown.enter.ctrl="speicherePoolInline(d, 'beschreibung')"
                                                  placeholder="Was ist diese Quelle? Warum passt sie?"></textarea>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePoolInline(d, 'beschreibung')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'anbieter_id')">
                                    <button class="thx-inline-edit" :class="!d.anbieter_name ? 'is-empty' : ''"
                                            @click.stop="oeffnePoolEdit(d, 'anbieter_id')"
                                            x-text="d.anbieter_name || '—'"></button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'anbieter_id'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="">— kein Anbieter —</option>
                                            <template x-for="a in anbieterListe" :key="a.id">
                                                <option :value="a.id" x-text="a.name"></option>
                                            </template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePoolInline(d, 'anbieter_id')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'tags')">
                                    <button class="thx-inline-edit" :class="!d.tags ? 'is-empty' : ''" style="text-align:left;width:100%;"
                                            @click.stop="oeffneTagEdit(d)"
                                            x-text="d.tags ? d.tags.split('|').slice(0, 3).join(', ') : '—'"></button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'tags'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop style="min-width:220px;">
                                        <div class="lam-chip-row" style="max-height:160px;overflow-y:auto;margin-bottom:6px;">
                                            <template x-for="t in alleTags" :key="t.id">
                                                <button type="button" class="lam-chip"
                                                        :class="tagAuswahl.includes(t.id) ? 'is-active' : ''"
                                                        @click="toggleTagInEdit(t.id)" x-text="t.name"></button>
                                            </template>
                                        </div>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereTags(d)" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'linkart')">
                                    <button class="thx-inline-edit" :class="!d.linkart ? 'is-empty' : ''"
                                            @click.stop="oeffnePoolEdit(d, 'linkart')"
                                            x-text="d.linkart ? (linkartLabels[d.linkart] || d.linkart) : '—'"></button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'linkart'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <option value="">— keine —</option>
                                            <template x-for="la in linkartListe" :key="la">
                                                <option :value="la" x-text="linkartLabels[la] || la"></option>
                                            </template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePoolInline(d, 'linkart')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td class="right">
                                <template x-if="d.si_aktuell !== null">
                                    <div>
                                        <div x-text="parseFloat(d.si_aktuell).toFixed(4)"></div>
                                        <div style="color:var(--slate-400);" x-text="d.dp_aktuell ? 'DP ' + d.dp_aktuell : ''"></div>
                                        <div style="color:var(--slate-400);" x-text="d.si_aktuell_am ? (new Date(d.si_aktuell_am)).toLocaleDateString('de-DE') : ''"></div>
                                    </div>
                                </template>
                                <template x-if="d.si_aktuell === null"><span class="empty">—</span></template>
                            </td>
                            <td class="right">
                                <!-- Mehrere Konditionen (unterschiedliche Buchungstypen/Anbieter) lassen sich nicht
                                     sinnvoll in EINER Zelle bearbeiten -> dann nur Spanne + Link zur Detailseite. -->
                                <template x-if="d.kondition_anzahl > 1">
                                    <a :href="'/lam/linkquellen/' + encodeURIComponent(d.id)"
                                       :title="d.kondition_anzahl + ' Konditionen — auf der Detailseite bearbeiten'"
                                       style="color:var(--thoxan-700);text-decoration:none;"
                                       x-text="(d.preis_min == d.preis_max ? euro(d.preis_min) : (euro(d.preis_min) + ' – ' + euro(d.preis_max))) + ' (' + d.kondition_anzahl + ')'"></a>
                                </template>
                                <template x-if="d.kondition_anzahl <= 1 && !(editZelle.id === d.id && editZelle.feld === 'preis')">
                                    <button class="thx-inline-edit" :class="d.preis_min === null ? 'is-empty' : ''"
                                            style="text-align:right;width:100%;"
                                            @click.stop="oeffnePreisEdit(d)"
                                            x-text="d.preis_min !== null ? euro(d.preis_min) : '—'"></button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'preis'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <input type="number" step="0.01" min="0" class="thx-inline-edit-input"
                                               x-model="editWert" x-init="$el.focus()"
                                               @keydown.enter.prevent="speicherePreis(d)" placeholder="Preis in €">
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePreis(d)" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="!(editZelle.id === d.id && editZelle.feld === 'verifikation_status')">
                                    <button class="thx-inline-edit" @click.stop="oeffnePoolEdit(d, 'verifikation_status')">
                                        <span class="lam-chip" :class="'lam-chip-' + d.verifikation_status" x-text="verifikationLabels[d.verifikation_status] || d.verifikation_status"></span>
                                    </button>
                                </template>
                                <template x-if="editZelle.id === d.id && editZelle.feld === 'verifikation_status'">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop>
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <template x-for="s in verifikationStatus" :key="s">
                                                <option :value="s" x-text="verifikationLabels[s] || s"></option>
                                            </template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speicherePoolInline(d, 'verifikation_status')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <!-- Linkprofil-Abgleich: verlinkt diese Quelle den Kunden schon? -->
                            <td>
                                <template x-if="d.verlinkt_anzahl > 0">
                                    <span class="lam-chip" style="background:var(--amber-100);color:var(--amber-800);"
                                          :title="'Laut Linkprofil bereits ' + d.verlinkt_anzahl + '× verlinkt' + (d.verlinkt_letzte ? ' (zuletzt ' + (new Date(d.verlinkt_letzte)).toLocaleDateString('de-DE') + ')' : '') + ' — eine Buchung wäre eine bewusste Wiederholung.'">
                                        ✓ verlinkt<span x-show="d.verlinkt_anzahl > 1" x-text="' ' + d.verlinkt_anzahl + '×'"></span>
                                    </span>
                                </template>
                                <template x-if="!d.verlinkt_anzahl">
                                    <span style="color:var(--emerald-700);" title="Noch nie verlinkt — echte Neuoption.">neu</span>
                                </template>
                            </td>
                            <td>
                                <template x-if="d.kunden">
                                    <span>
                                        <template x-for="k in d.kunden.split('|')" :key="k">
                                            <span class="lam-chip" style="padding:2px 7px;margin-right:3px;" x-text="k"></span>
                                        </template>
                                    </span>
                                </template>
                                <template x-if="!d.kunden"><span class="empty">—</span></template>
                            </td>
                            <td>
                                <template x-if="d.auswahlen && d.auswahlen.length > 0">
                                    <div style="display:flex;flex-direction:column;gap:2px;">
                                        <template x-for="a in d.auswahlen" :key="a.eintrag_id">
                                            <div>
                                                <!-- Status des Vorschlagslisten-Eintrags direkt hier aenderbar -->
                                                <template x-if="!(editZelle.id === a.eintrag_id && editZelle.feld === 'eintrag_status')">
                                                    <button class="thx-inline-edit" style="padding:0 4px;"
                                                            @click.stop="oeffneEintragEdit(a)"
                                                            :title="'Status ändern — ' + (a.liste_name || '')">
                                                        <span style="color:var(--slate-600);font-weight:600;" x-text="statusLabel(a.status) + ' ·'"></span>
                                                    </button>
                                                </template>
                                                <template x-if="editZelle.id === a.eintrag_id && editZelle.feld === 'eintrag_status'">
                                                    <span class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()" @click.stop style="display:inline-flex;gap:4px;align-items:center;">
                                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                                            <template x-for="s in statusListe" :key="s.slug">
                                                                <option :value="s.slug" x-text="s.label"></option>
                                                            </template>
                                                        </select>
                                                        <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereEintragStatus(a)" :disabled="editLaeuft">OK</button>
                                                        <button class="lam-btn lam-btn-small" @click="schliesseEdit()">×</button>
                                                    </span>
                                                </template>
                                                <a :href="'/lam/vorschlagslisten/' + encodeURIComponent(a.liste_id)"
                                                   :title="'Zur Liste „' + a.liste_name + '" — ' + (a.customer_name || '')"
                                                   style="color:var(--thoxan-700);text-decoration:none;">
                                                    <strong x-text="a.customer_kuerzel || ''"></strong>
                                                    <span x-text="' ' + (a.liste_name || '')" style="color:var(--slate-600);"></span>
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!d.auswahlen || d.auswahlen.length === 0">
                                    <span class="empty">—</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0" style="padding:30px;text-align:center;">
                <div style="font-weight:600;color:var(--slate-700);margin-bottom:6px;">Keine Linkquellen treffen die Filter.</div>
                <div style="color:var(--slate-500);font-size:var(--d-fs-sm);">Filter lockern oder zurücksetzen.</div>
            </div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>
    </div>
</template>

<!-- ═══════════════════════════════════════════════════════════════════
     AUSWAHL-TAB: Vorschlagslisten-Einträge (alter Status-Workflow)
     ═══════════════════════════════════════════════════════════════════ -->
<template x-if="tab === 'auswahl'">
    <div>
    <section class="lam-filter-card">
        <div class="lam-filter-head">
            <h2>Filter</h2>
            <span style="font-size:var(--d-fs-xs);color:var(--slate-400);"
                  x-text="rows.length ? (rows.length + ' Einträge') : ''"></span>
        </div>
        <div class="lam-filter-grid">
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Suche (Domain, Notiz, Artikelthema)</label>
                <input type="text" class="lam-filter-input"
                       x-model="filter.suche" @input.debounce.300ms="laden()">
            </div>
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Kunde</label>
                <select class="lam-filter-select" x-model="filter.customer_id" @change="setzeKunde(filter.customer_id)">
                    <option value="">alle Kunden</option>
                    <template x-for="k in kunden" :key="k.id">
                        <option :value="k.id" x-text="(k.abbreviation ? k.abbreviation + ' · ' : '') + k.name + ' (' + (k.eintrag_count || 0) + ')'"></option>
                    </template>
                </select>
            </div>
            <div class="lam-filter-col-4">
                <label class="lam-filter-label">Status</label>
                <div class="lam-chip-row">
                    <button class="lam-chip lam-chip-reset" :class="filter.status === '' ? 'is-active' : ''" @click="filter.status = ''; laden()">alle</button>
                    <template x-for="s in statusListe" :key="s.slug">
                        <button class="lam-chip" :class="filter.status === s.slug ? 'is-active' : ''" @click="filter.status = s.slug; laden()" x-text="s.label"></button>
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
            <option value="loeschen">Löschen</option>
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
                        <th>Kunde</th>
                        <th>Domain</th>
                        <th>Liste</th>
                        <th>Status</th>
                        <th>Artikelthema</th>
                        <th class="right">Preis Kunde</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="e in rows" :key="e.id">
                        <tr :class="auswahl.has(e.id) ? 'is-bulk-selected' : ''" @contextmenu.prevent="oeffneCtxMenu($event, e)">
                            <td class="thx-bulk-col">
                                <input type="checkbox" class="thx-bulk-checkbox" :checked="auswahl.has(e.id)" @change="toggleAuswahl(e.id)" @click.stop>
                            </td>
                            <td><strong x-text="e.customer_kuerzel"></strong></td>
                            <td class="url-cell">
                                <a :href="'/lam/linkoptionen/' + encodeURIComponent(e.id)" x-text="e.domain_url" style="color:var(--thoxan-700);"></a>
                            </td>
                            <td>
                                <template x-if="e.liste_id">
                                    <a :href="'/lam/vorschlagslisten/' + encodeURIComponent(e.liste_id)"
                                       style="color:var(--thoxan-700);text-decoration:none;" x-text="e.liste_titel"></a>
                                </template>
                                <template x-if="!e.liste_id"><span class="empty">—</span></template>
                            </td>
                            <!-- Status Inline-Edit -->
                            <td>
                                <template x-if="!istOffen(e.id, 'status')">
                                    <button class="thx-inline-edit" @click="oeffneEdit(e, 'status')" x-text="e.status"></button>
                                </template>
                                <template x-if="istOffen(e.id, 'status')">
                                    <div class="thx-inline-edit-frame" @keydown.escape="schliesseEdit()">
                                        <select class="thx-inline-edit-select" x-model="editWert" x-init="$el.focus()">
                                            <template x-for="s in statusListe" :key="s.slug"><option :value="s.slug" x-text="s.label"></option></template>
                                        </select>
                                        <div class="thx-inline-edit-actions">
                                            <button class="lam-btn lam-btn-primary lam-btn-small" @click="speichereInline(e, 'status')" :disabled="editLaeuft">Speichern</button>
                                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="schliesseEdit()">Abbrechen</button>
                                        </div>
                                    </div>
                                </template>
                            </td>
                            <td>
                                <template x-if="e.artikelthema"><span x-text="e.artikelthema"></span></template>
                                <template x-if="!e.artikelthema"><span class="empty">—</span></template>
                            </td>
                            <td class="right">
                                <template x-if="e.preis_kunde !== null"><span x-text="euro(e.preis_kunde)"></span></template>
                                <template x-if="e.preis_kunde === null"><span class="empty">—</span></template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="lam-empty" x-show="!laedt && rows.length === 0">Keine Einträge.</div>
            <div class="lam-loading" x-show="laedt && rows.length === 0"><span class="lam-spinner"></span> Lade …</div>
        </div>
    </section>
    </div>
</template>

    <!-- Rechtsklick-Kontextmenue (gilt für Auswahl-Tab) -->
    <div class="thx-contextmenu" x-show="ctxMenu.offen" x-cloak :style="`top: ${ctxMenu.y}px; left: ${ctxMenu.x}px;`" @click.stop>
        <div class="thx-contextmenu-label" x-text="ctxMenu.ziel?.domain_url || ''"></div>
        <div class="thx-contextmenu-label">Status setzen</div>
        <template x-for="s in statusListe" :key="s">
            <button class="thx-contextmenu-item" @click="schnellStatus(ctxMenu.ziel, s); ctxMenu.offen = false" x-text="s"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item is-danger" @click="loescheEintrag(ctxMenu.ziel); ctxMenu.offen = false">Löschen</button>
    </div>

    <!-- Rechtsklick-Menü für den Linkpool (eigener State, damit das Eintraege-Menue unberuehrt bleibt) -->
    <div class="thx-contextmenu" x-show="poolCtx.offen" x-cloak :style="`top: ${poolCtx.y}px; left: ${poolCtx.x}px;`" @click.stop>
        <div class="thx-contextmenu-label" x-text="poolCtx.ziel?.url || ''"></div>
        <a class="thx-contextmenu-item" :href="poolCtx.ziel ? '/lam/linkquellen/' + encodeURIComponent(poolCtx.ziel.id) : '#'" style="text-decoration:none;">Detail-Seite öffnen</a>
        <a class="thx-contextmenu-item" :href="poolCtx.ziel ? extUrl(poolCtx.ziel.url) : '#'" target="_blank" rel="noopener" style="text-decoration:none;">Website öffnen ↗</a>
        <button class="thx-contextmenu-item" @click="kopiereText(poolCtx.ziel?.url); poolCtx.offen = false">URL kopieren</button>
        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Bewertung setzen</div>
        <template x-for="b in bewertungen" :key="'ctx-' + b">
            <button class="thx-contextmenu-item"
                    @click="poolSchnellAktion(poolCtx.ziel, 'bewertung', b); poolCtx.offen = false"
                    x-text="bewertungLabels[b] || b"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <div class="thx-contextmenu-label">Verifikation setzen</div>
        <template x-for="s in verifikationStatus" :key="s">
            <button class="thx-contextmenu-item" @click="poolSchnellAktion(poolCtx.ziel, 'verifikation_status', s); poolCtx.offen = false" x-text="verifikationLabels[s] || s"></button>
        </template>
        <div class="thx-contextmenu-divider"></div>
        <button class="thx-contextmenu-item" x-show="poolCtx.ziel && poolCtx.ziel.disqualifiziert != 1"
                @click="poolSchnellAktion(poolCtx.ziel, 'disqualifiziert', 1); poolCtx.offen = false">Disqualifizieren</button>
        <button class="thx-contextmenu-item" x-show="poolCtx.ziel && poolCtx.ziel.disqualifiziert == 1"
                @click="poolSchnellAktion(poolCtx.ziel, 'disqualifiziert', 0); poolCtx.offen = false">Rehabilitieren</button>
        <button class="thx-contextmenu-item is-danger" @click="entferneAusPool(poolCtx.ziel); poolCtx.offen = false">Aus Linkpool entfernen</button>
    </div>
</div>

<style>
/* ===== Linkpool: einheitliche, lesbare Typografie =====
   Vorher mischten sich Tabellen-Basis (--fs-sm) und Zell-Overrides (--d-fs-xs) — dadurch
   war alles unterschiedlich gross und die wichtigste Spalte (Beschreibung) als Tooltip
   versteckt. Jetzt: eine Groesse fuer alles, Beschreibung im Volltext. */
.lam-table td { font-size: var(--fs-sm); line-height: 1.5; }
.lam-table td .lam-chip { font-size: var(--fs-sm); }

/* Feste Spaltenbreiten: die Breiten kommen aus dem Kopf (th), die Zellen richten sich danach.
   Ohne "fixed" verteilt der Browser automatisch und die lange Beschreibung frisst die URL auf. */
.lam-table-fixed { table-layout: fixed; width: auto; min-width: 100%; }
.lam-table-fixed th, .lam-table-fixed td {
    overflow: hidden;
    white-space: normal;
    word-break: break-word;
}
.lam-table-fixed thead th { white-space: nowrap; }

/* Ziehgriff am rechten Rand jedes Spaltenkopfs */
.lam-table-fixed thead th { position: relative; }
.col-resizer {
    position: absolute; top: 0; right: 0; bottom: 0;
    width: 7px; cursor: col-resize;
    user-select: none;
}
.col-resizer:hover { background: var(--thoxan-300); }

/* Beschreibung: darf umbrechen und mehrere Zeilen belegen — sie ist die Entscheidungshilfe. */
.lam-table td.lp-beschreibung {
    white-space: normal;
    word-break: break-word;
    color: var(--slate-700);
    line-height: 1.5;
}
.lam-table td.url-cell { white-space: normal; word-break: break-word; }

/* Sortierbarkeit sichtbar machen: dezenter Pfeil auf allen sortierbaren Spalten,
   kraeftig auf der aktiven. */
.lam-table thead th.sortable { position: relative; }
.lam-table thead th.sortable::after {
    content: '↕';
    margin-left: 4px;
    color: var(--slate-300);
    font-size: 0.85em;
}
.lam-table thead th.sortable.is-sorted::after { content: ''; }
.lam-table thead th.sortable.is-sorted { color: var(--thoxan-700); background: var(--thoxan-50); }

[x-cloak] { display: none !important; }
@keyframes toastIn {
    from { transform: translateX(20px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}
.lam-chip.is-active { background: var(--thoxan-700) !important; color: #fff !important; }
</style>

<script>
function lamLinkoptionen() {
    return {
        laedt: true, rows: [], kunden: [], erweitert: false,
        kundenVorschlagslisten: [],
        vlStatusLabels: { aktiv: 'Aktiv', archiv: 'Archiv', gesendet: 'Gesendet', entwurf: 'Entwurf' },
        vlStatusLabel(s) { return this.vlStatusLabels[s] || s; },
        vlStatusStyle(s) {
            const m = {
                aktiv:    'background:var(--thoxan-100);color:var(--thoxan-800);',
                gesendet: 'background:var(--emerald-100);color:var(--emerald-800);',
                archiv:   'background:var(--slate-200);color:var(--slate-600);',
                entwurf:  'background:var(--amber-100);color:var(--amber-800);',
            };
            return m[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },
        async ladeKundenVorschlagslisten() {
            this.kundenVorschlagslisten = [];
            if (!this.filter.customer_id) return;
            try {
                const r = await fetch('/api/v1/lam/vorschlagslisten?customer_id=' + encodeURIComponent(this.filter.customer_id), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.kundenVorschlagslisten = j.data || [];
            } catch (e) {}
        },
        poolHinweis: '',
        addLinkpool: { offen: false, suche: '', treffer: [], gewaehlt: {}, laeuft: false },
        aufListe: { offen: false, laeuft: false, modus: 'auswaehlen', listen: [], listenId: '',
                    neuerName: '', neuerStatus: 'aktiv', neueZielzahl: '', neueNotiz: '' },
        // tab: 'pool' = Linkpool pro Kunde · 'auswahl' = Vorschlagslisten-Einträge
        tab: localStorage.getItem('lam_linkoptionen_tab') || 'pool',
        filter: {
            suche: '', status: '',
            // Kunden-Auswahl bleibt session-übergreifend sticky (localStorage)
            customer_id: localStorage.getItem('lam_linkoptionen_customer_id') || '',
            // Pool-spezifisch
            status_auswahl: '', verlinkt: '', bewertung: [], verifikation: [], linkart: [],
            si_min: '', si_max: '', preis_min: '', preis_max: '',
        },
        statusListe: [
            { slug: 'in_planung',         label: 'In Planung' },
            { slug: 'vorgeschlagen',      label: 'Vorgeschlagen' },
            { slug: 'in_akquise',         label: 'In Akquise' },
            { slug: 'bestaetigt',         label: 'Bestätigt' },
            { slug: 'abgelehnt',          label: 'Abgelehnt' },
            { slug: 'ohne_antwort',       label: 'Ohne Antwort' },
            { slug: 'kunde_freigegeben',  label: 'Kunde freigegeben' },
            { slug: 'kunde_abgelehnt',    label: 'Kunde abgelehnt' },
            { slug: 'abgeschlossen',      label: 'Abgeschlossen' },
        ],
        statusLabel(s) {
            const t = this.statusListe.find(x => x.slug === s);
            return t ? t.label : s;
        },
        verifikationListe: ['neu','in_arbeit','geprueft','veraltet','geloescht'],
        linkartListe: ['blog','branchenverzeichnis','fachverzeichnis','forum','kommentarlink','online_magazin','partner','podcast','portal','presseportal','referenzprojekt','social_media','sonstiges','sponsoring','stellenboerse','veranstaltung','weiterleitung'],
        linkartLabels: {'blog':'Blog','branchenverzeichnis':'Branchenverzeichnis','fachverzeichnis':'Fachverzeichnis','forum':'Forum','kommentarlink':'Kommentarlink','online_magazin':'Online-Magazin','partner':'Partner','podcast':'Podcast','portal':'Portal','presseportal':'Presseportal','referenzprojekt':'Referenzprojekt','social_media':'Social Media','sonstiges':'Sonstiges','sponsoring':'Sponsoring','stellenboerse':'Stellenbörse','veranstaltung':'Veranstaltung','weiterleitung':'Weiterleitung'},
        editZelle: { id: null, feld: null }, editWert: '', editLaeuft: false,
        auswahl: new Set(), bulkAktion: '', bulkWert: '', bulkLaeuft: false,
        ctxMenu: { offen: false, x: 0, y: 0, ziel: null },

        // ===== Linkpool: Spaltenbreiten (ziehbar + eintippbar, gemerkt) =====
        // Ohne feste Breiten verteilt der Browser automatisch — die lange Beschreibung zieht
        // dann alles zu sich und die URL wird gequetscht. Deshalb table-layout:fixed + Breiten.
        BREITEN_KEY: 'lam_linkpool_breiten_v1',
        breitenPanel: false,
        poolSpalten: [
            { key: 'bewertung',   label: 'Bewertung',          sort: 'bewertung',           hint: 'Redaktionelle Bewertung aus der Recherche-Datei (Prio/Urteil)' },
            { key: 'url',         label: 'URL',                sort: 'url' },
            { key: 'beschreibung',label: 'Beschreibung',       sort: 'beschreibung',        hint: 'Was ist diese Quelle? (Begründung aus der Recherche bzw. Import-Cluster)' },
            { key: 'anbieter',    label: 'Anbieter',           sort: 'anbieter_name' },
            { key: 'tags',        label: 'Tags',               sort: 'tags' },
            { key: 'linkart',     label: 'Linkart',            sort: 'linkart' },
            { key: 'sidp',        label: 'SI / DP',            sort: 'si_aktuell',          right: true },
            { key: 'preis',       label: 'Preis',              sort: 'preis_min',           right: true },
            { key: 'status',      label: 'Status',             sort: 'verifikation_status' },
            { key: 'verlinkt',    label: 'Verlinkt',           sort: 'verlinkt_anzahl',     hint: 'Verlinkt diese Quelle den Kunden laut Linkprofil bereits?' },
            { key: 'kunden',      label: 'Kunden',             sort: 'kunden' },
            { key: 'auswahl',     label: 'In aktiver Auswahl', sort: 'auswahlen' },
        ],
        BREITEN_STANDARD: {
            check: 38, bewertung: 100, url: 240, beschreibung: 420, anbieter: 130,
            tags: 110, linkart: 130, sidp: 95, preis: 85, status: 95,
            verlinkt: 95, kunden: 90, auswahl: 160,
        },
        spaltenBreiten: {},
        resize: null,

        breiteStil(key) {
            const b = this.spaltenBreiten[key] || this.BREITEN_STANDARD[key] || 120;
            return `width:${b}px;min-width:${b}px;max-width:${b}px;`;
        },
        setzeBreite(key, wert) {
            const n = Math.max(40, Math.min(900, parseInt(wert) || 0));
            this.spaltenBreiten[key] = n;
            this.speichereBreiten();
        },
        breitenZuruecksetzen() {
            this.spaltenBreiten = { ...this.BREITEN_STANDARD };
            this.speichereBreiten();
        },
        speichereBreiten() {
            try { localStorage.setItem(this.BREITEN_KEY, JSON.stringify(this.spaltenBreiten)); } catch (e) {}
        },
        ladeBreiten() {
            let gespeichert = {};
            try { gespeichert = JSON.parse(localStorage.getItem(this.BREITEN_KEY) || '{}') || {}; } catch (e) {}
            // Standard als Basis, Gespeichertes drueber — so kommen neue Spalten automatisch dazu.
            this.spaltenBreiten = { ...this.BREITEN_STANDARD, ...gespeichert };
        },
        resizeAktiv: false,
        /** Ziehen am Trennstrich zwischen zwei Spaltenkoepfen. */
        startResize(ev, key) {
            const startX = ev.clientX;
            const startB = this.spaltenBreiten[key] || this.BREITEN_STANDARD[key] || 120;
            this.resizeAktiv = true;
            const bewegen = (e) => {
                const neu = Math.max(40, Math.min(900, startB + (e.clientX - startX)));
                this.spaltenBreiten[key] = neu;
            };
            const loslassen = () => {
                document.removeEventListener('mousemove', bewegen);
                document.removeEventListener('mouseup', loslassen);
                document.body.style.userSelect = '';
                this.speichereBreiten();
                // Erst im naechsten Tick freigeben — sonst greift der Sortier-Klick,
                // der direkt nach dem Loslassen noch feuert.
                setTimeout(() => { this.resizeAktiv = false; }, 0);
            };
            document.body.style.userSelect = 'none';   // sonst wird beim Ziehen Text markiert
            document.addEventListener('mousemove', bewegen);
            document.addEventListener('mouseup', loslassen);
        },

        // ===== Linkpool: Sortierung, Inline-Edit, Rechtsklick =====
        poolCtx: { offen: false, x: 0, y: 0, ziel: null },
        poolSort: { feld: '', dir: 'asc' },
        anbieterListe: [],
        verifikationStatus: ['neu', 'in_arbeit', 'geprueft', 'veraltet', 'geloescht'],
        verifikationLabels: { 'neu': 'Neu', 'in_arbeit': 'In Arbeit', 'geprueft': 'Geprüft', 'veraltet': 'Veraltet', 'geloescht': 'Gelöscht' },

        // Redaktionelle Bewertung aus der Recherche-Datei (feste, projektuebergreifende Stufen)
        bewertungen: ['top', 'bedingt', 'ablehnen', 'offen'],
        bewertungLabels: { 'top': 'TOP', 'bedingt': 'Bedingt', 'ablehnen': 'Ablehnen', 'offen': 'Offen' },
        bulkBewertung: '',
        async bulkBewertungSetzen() {
            const ids = Array.from(this.auswahl);
            if (ids.length === 0 || !this.bulkBewertung) return;
            const label = this.bewertungLabels[this.bulkBewertung] || this.bulkBewertung;
            if (!confirm(`${ids.length} Quelle(n) auf Bewertung „${label}" setzen?`)) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, aktion: 'bewertung_setzen', wert: this.bulkBewertung }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.toast(`${j.data.erfolge} auf „${label}" gesetzt`, 'ok');
                this.bulkBewertung = '';
                this.auswahlLeeren();
                await this.laden();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            finally { this.bulkLaeuft = false; }
        },
        bewertungStil(b) {
            if (b === 'top')      return 'background:var(--emerald-100);color:var(--emerald-800);font-weight:600;';
            if (b === 'bedingt')  return 'background:var(--amber-100);color:var(--amber-800);';
            if (b === 'ablehnen') return 'background:var(--rose-100);color:var(--rose-800);';
            return 'background:var(--slate-100);color:var(--slate-600);';
        },
        toggleBewertung(b) {
            const i = this.filter.bewertung.indexOf(b);
            if (i >= 0) this.filter.bewertung.splice(i, 1); else this.filter.bewertung.push(b);
            this.laden();
        },

        sortierePool(feld) {
            // Nach dem Ziehen einer Spaltenbreite darf nicht versehentlich sortiert werden.
            if (this.resizeAktiv) return;
            if (this.poolSort.feld === feld) {
                this.poolSort.dir = this.poolSort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.poolSort = { feld, dir: 'asc' };
            }
        },
        sortPfeil(feld) {
            if (this.poolSort.feld !== feld) return '';
            return this.poolSort.dir === 'asc' ? '▲' : '▼';
        },
        /**
         * Clientseitig sortieren: Der Pool-Endpunkt liefert IMMER alle Domains des Kunden
         * (kein Paging), deshalb ist das korrekt und deckt auch berechnete Spalten ab
         * (Tags, SI/DP, Preis, "In aktiver Auswahl"), die serverseitig aufwendig waeren.
         */
        poolSortiert() {
            const f = this.poolSort.feld;
            if (!f) return this.rows;
            const dir = this.poolSort.dir === 'asc' ? 1 : -1;
            // Bewertung nach SINN sortieren, nicht alphabetisch: TOP zuerst, Ablehnen zuletzt.
            // (Alphabetisch waere "ablehnen, bedingt, offen, top" — genau verkehrt herum.)
            const bewertungRang = { top: 1, bedingt: 2, offen: 3, ablehnen: 4 };
            const wert = (d) => {
                if (f === 'auswahlen') return (d.auswahlen || []).length;
                if (f === 'si_aktuell') return d.si_aktuell === null || d.si_aktuell === undefined ? null : parseFloat(d.si_aktuell);
                if (f === 'preis_min')  return d.preis_min === null || d.preis_min === undefined ? null : parseFloat(d.preis_min);
                if (f === 'bewertung')  return d.bewertung ? (bewertungRang[d.bewertung] || 9) : null;
                const v = d[f];
                return (v === null || v === undefined || v === '') ? null : String(v).toLowerCase();
            };
            return [...this.rows].sort((a, b) => {
                const va = wert(a), vb = wert(b);
                // Leere Werte immer ans Ende — egal ob auf- oder absteigend.
                if (va === null && vb === null) return 0;
                if (va === null) return 1;
                if (vb === null) return -1;
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });
        },

        oeffnePoolCtx(event, ziel) {
            const x = event.clientX, y = event.clientY;
            const px = (x + 220 > window.innerWidth) ? x - 220 : x;
            const py = (y + 400 > window.innerHeight) ? Math.max(8, y - 400) : y;
            this.ctxMenu.offen = false;
            this.poolCtx = { offen: true, x: px, y: py, ziel };
        },
        oeffnePoolEdit(d, feld) {
            if (this.editLaeuft) return;
            this.poolCtx.offen = false;
            this.editZelle = { id: d.id, feld };
            this.editWert = (feld === 'anbieter_id') ? (d.anbieter_id || '') : (d[feld] ?? '');
        },

        // ─ Tags (Mehrfachauswahl) ─
        alleTags: [],
        tagAuswahl: [],
        oeffneTagEdit(d) {
            if (this.editLaeuft) return;
            this.poolCtx.offen = false;
            this.tagAuswahl = String(d.tag_ids || '').split(',').map(s => parseInt(s)).filter(n => n > 0);
            this.editZelle = { id: d.id, feld: 'tags' };
        },
        toggleTagInEdit(tagId) {
            const i = this.tagAuswahl.indexOf(tagId);
            if (i >= 0) this.tagAuswahl.splice(i, 1); else this.tagAuswahl.push(tagId);
        },
        async speichereTags(d) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-tags-set', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: d.id, tag_ids: this.tagAuswahl }),
                });
                const j = await r.json();
                if (!j.success) { this.toast('Fehler: ' + (j.message || 'unbekannt'), 'fehler'); return; }
                // Zeile lokal nachziehen, statt die ganze Tabelle neu zu laden.
                const gewaehlt = this.alleTags.filter(t => this.tagAuswahl.includes(t.id));
                d.tags = gewaehlt.map(t => t.name).join('|');
                d.tag_ids = gewaehlt.map(t => t.id).join(',');
                this.schliesseEdit();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            finally { this.editLaeuft = false; }
        },

        // ─ Preis (nur bei 0 oder 1 Kondition eindeutig) ─
        oeffnePreisEdit(d) {
            if (this.editLaeuft) return;
            this.poolCtx.offen = false;
            this.editZelle = { id: d.id, feld: 'preis' };
            this.editWert = d.preis_min !== null && d.preis_min !== undefined ? d.preis_min : '';
        },
        async speicherePreis(d) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/kondition-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    // kondition_id gesetzt -> bestehende aktualisieren; sonst neue fuer die Domain anlegen.
                    body: JSON.stringify({ id: d.kondition_id || null, domain_id: d.id, preis: this.editWert }),
                });
                const j = await r.json();
                if (!j.success) { this.toast('Fehler: ' + (j.message || 'unbekannt'), 'fehler'); return; }
                const p = this.editWert === '' ? null : parseFloat(this.editWert);
                d.preis_min = p; d.preis_max = p;
                if (!d.kondition_id && j.data?.id) { d.kondition_id = j.data.id; d.kondition_anzahl = 1; }
                this.schliesseEdit();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            finally { this.editLaeuft = false; }
        },

        // ─ Status eines Vorschlagslisten-Eintrags ─
        oeffneEintragEdit(a) {
            if (this.editLaeuft) return;
            this.poolCtx.offen = false;
            this.editZelle = { id: a.eintrag_id, feld: 'eintrag_status' };
            this.editWert = a.status || '';
        },
        async speichereEintragStatus(a) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: a.eintrag_id, feld: 'status', wert: this.editWert }),
                });
                const j = await r.json();
                if (!j.success) { this.toast('Fehler: ' + (j.message || 'unbekannt'), 'fehler'); return; }
                a.status = this.editWert;
                this.schliesseEdit();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            finally { this.editLaeuft = false; }
        },
        async speicherePoolInline(d, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: d.id, feld, wert: this.editWert }),
                });
                const j = await r.json();
                if (!j.success) { this.toast('Fehler: ' + (j.message || 'unbekannt'), 'fehler'); return; }
                d[feld] = this.editWert;
                if (feld === 'anbieter_id') {
                    const a = this.anbieterListe.find(x => String(x.id) === String(this.editWert));
                    d.anbieter_name = a ? a.name : null;
                }
                this.schliesseEdit();
            } catch (e) {
                this.toast('Fehler: ' + e.message, 'fehler');
            } finally { this.editLaeuft = false; }
        },
        async poolSchnellAktion(d, feld, wert) {
            if (!d) return;
            try {
                const r = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: d.id, feld, wert }),
                });
                const j = await r.json();
                if (!j.success) { this.toast('Fehler: ' + (j.message || 'unbekannt'), 'fehler'); return; }
                d[feld] = wert;
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
        },
        async entferneAusPool(d) {
            if (!d || !this.filter.customer_id) return;
            if (!confirm(`„${d.url}" aus dem Linkpool entfernen?`)) return;
            try {
                const r = await fetch('/api/v1/lam/linkpool-remove', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.filter.customer_id, domain_ids: [d.id] }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.toast('Aus dem Linkpool entfernt', 'ok');
                this.laden();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
        },
        kopiereText(t) {
            if (!t) return;
            navigator.clipboard?.writeText(t);
            this.toast('URL kopiert', 'ok');
        },

        /**
         * Linkart per KI setzen — in Haeppchen, damit ein Request nicht ins Timeout laeuft
         * und der Fortschritt sichtbar bleibt. Setzt nur leere Linkarten (kein Ueberschreiben).
         */
        async kiLinkart() {
            const ids = Array.from(this.auswahl);
            if (ids.length === 0) return;
            if (!confirm(`Für ${ids.length} Quelle(n) die Linkart per KI bestimmen?\n\nBereits gesetzte Linkarten bleiben unverändert.`)) return;

            this.bulkLaeuft = true;
            let gesetzt = 0, uebersprungen = 0;
            const fehler = [];
            const chunk = 20;
            try {
                for (let i = 0; i < ids.length; i += chunk) {
                    const teil = ids.slice(i, i + chunk);
                    this.toast(`KI läuft … ${Math.min(i + chunk, ids.length)}/${ids.length}`, 'ok');
                    try {
                        const r = await fetch('/api/v1/lam/ki-linkart', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ids: teil }),
                        });
                        const j = await r.json();
                        if (!j.success) { fehler.push(j.message || 'Fehler'); continue; }
                        gesetzt += (j.data.gesetzt || 0);
                        uebersprungen += (j.data.uebersprungen || 0);
                        (j.data.fehler || []).forEach(f => fehler.push(f));
                    } catch (e) { fehler.push(e.message || 'Netzwerkfehler'); }
                    // Kein requestAnimationFrame — das feuert im Hintergrund-Tab nicht.
                    await new Promise(r => setTimeout(r, 50));
                }
                this.toast(
                    `${gesetzt} Linkart(en) gesetzt` +
                    (uebersprungen ? `, ${uebersprungen} übersprungen (bereits gesetzt)` : '') +
                    (fehler.length ? `, ${fehler.length} Fehler` : ''),
                    fehler.length ? 'warn' : 'ok'
                );
                this.auswahlLeeren();
                await this.laden();
            } finally { this.bulkLaeuft = false; }
        },

        wechsleTab(t) {
            this.tab = t;
            try { localStorage.setItem('lam_linkoptionen_tab', t); } catch (e) {}
            this.auswahl = new Set();
            this.laden();
        },
        /** Setzt den aktiven Kunden + persistiert ihn (sticky über Page-Reloads/Sessions). */
        setzeKunde(id) {
            this.filter.customer_id = id || '';
            try {
                if (id) localStorage.setItem('lam_linkoptionen_customer_id', String(id));
                else localStorage.removeItem('lam_linkoptionen_customer_id');
            } catch (e) {}
            this.auswahl = new Set();
            this.laden();
        },
        filterReset() {
            // customer_id absichtlich erhalten — der Kunden-Kontext ist die sticky Auswahl
            const keepCust = this.filter.customer_id;
            this.filter = { suche: '', status: '', customer_id: keepCust, status_auswahl: '', verlinkt: '', bewertung: [],
                            verifikation: [], linkart: [], si_min: '', si_max: '', preis_min: '', preis_max: '' };
            this.laden();
        },
        toggleVerifikation(ev, v) {
            const arr = [...this.filter.verifikation];
            const multi = ev.shiftKey || ev.ctrlKey || ev.metaKey;
            const idx = arr.indexOf(v);
            if (multi) {
                if (idx >= 0) arr.splice(idx, 1); else arr.push(v);
            } else {
                this.filter.verifikation = (idx >= 0 && arr.length === 1) ? [] : [v];
                return this.laden();
            }
            this.filter.verifikation = arr;
            this.laden();
        },
        toggleLinkart(ev, la) {
            const arr = [...this.filter.linkart];
            const multi = ev.shiftKey || ev.ctrlKey || ev.metaKey;
            const idx = arr.indexOf(la);
            if (multi) {
                if (idx >= 0) arr.splice(idx, 1); else arr.push(la);
            } else {
                this.filter.linkart = (idx >= 0 && arr.length === 1) ? [] : [la];
                return this.laden();
            }
            this.filter.linkart = arr;
            this.laden();
        },
        kundenName(id) { const k = this.kunden.find(k => k.id == id); return k ? (k.abbreviation || k.name) : ''; },

        STORAGE_KEY: 'thx_lam_filter_linkoptionen',
        initFilter() {
            try {
                const gespeichert = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '{}');
                // customer_id + tab haben eigene legacy-Storage-Keys → die nicht überschreiben
                const { customer_id, ...rest } = gespeichert;
                Object.assign(this.filter, rest);
            } catch (e) {}
            this.$watch('filter', (v) => {
                try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(v)); } catch (e) {}
            }, { deep: true });
        },
        filterZuruecksetzen() {
            try { localStorage.removeItem(this.STORAGE_KEY); } catch (e) {}
            const behalteKunde = this.filter.customer_id;
            this.filter = {
                suche: '', status: '', customer_id: behalteKunde,
                status_auswahl: '', verlinkt: '', bewertung: [], verifikation: [], linkart: [],
                si_min: '', si_max: '', preis_min: '', preis_max: '',
            };
            this.laden();
        },

        async laden() {
            this.laedt = true;
            if (Object.keys(this.spaltenBreiten).length === 0) this.ladeBreiten();
            // Vorschlagslisten des Kunden parallel mitladen (nicht-blockierend)
            this.ladeKundenVorschlagslisten();

            // Anbieter- und Tag-Liste fuer den Inline-Edit im Linkpool (einmalig)
            if (this.anbieterListe.length === 0) {
                try {
                    const ra = await fetch('/api/v1/lam/anbieter-kurz', { credentials: 'same-origin' });
                    const ja = await ra.json();
                    if (ja.success) this.anbieterListe = ja.data || [];
                } catch (e) { /* Inline-Edit faellt dann auf "kein Anbieter" zurueck */ }
            }
            if (this.alleTags.length === 0) {
                try {
                    const rt = await fetch('/api/v1/lam/tags', { credentials: 'same-origin' });
                    const jt = await rt.json();
                    if (jt.success) this.alleTags = jt.data || [];
                } catch (e) { /* Tag-Edit bleibt dann leer */ }
            }

            // Kundenliste laden falls noch nicht da
            if (this.kunden.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkoptionen-kunden', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.kunden = j.data;
                } catch (e) {}
            }
            try {
                if (this.tab === 'pool') await this.ladePool();
                else await this.ladeAuswahl();
            } finally { this.laedt = false; }
        },
        async ladePool() {
            this.poolHinweis = '';
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
            if (this.filter.status_auswahl) p.set('status_auswahl', this.filter.status_auswahl);
            if (this.filter.verlinkt) p.set('verlinkt', this.filter.verlinkt);
            this.filter.verifikation.forEach(v => p.append('verifikation_status[]', v));
            this.filter.linkart.forEach(la => p.append('linkart[]', la));
            this.filter.bewertung.forEach(b => p.append('bewertung[]', b));
            if (this.filter.si_min !== '') p.set('si_min', this.filter.si_min);
            if (this.filter.si_max !== '') p.set('si_max', this.filter.si_max);
            if (this.filter.preis_min !== '') p.set('preis_min', this.filter.preis_min);
            if (this.filter.preis_max !== '') p.set('preis_max', this.filter.preis_max);
            const r = await fetch('/api/v1/lam/linkoptionen-pool?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            this.rows = j.success ? (j.data.rows || []) : [];
            this.poolHinweis = j.success ? (j.data.hinweis || '') : '';
        },
        async ladeAuswahl() {
            const p = new URLSearchParams();
            if (this.filter.suche) p.set('suche', this.filter.suche);
            if (this.filter.status) p.set('status', this.filter.status);
            if (this.filter.customer_id) p.set('customer_id', this.filter.customer_id);
            const r = await fetch('/api/v1/lam/linkoptionen?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            this.rows = j.success ? j.data : [];
        },

        // ===== Linkpool-Verwaltung =====
        oeffneLinkpoolAdd() {
            if (!this.filter.customer_id) { this.toast('Erst einen Kunden im Filter wählen.', 'warn'); return; }
            this.addLinkpool = { offen: true, suche: '', treffer: [], gewaehlt: {}, laeuft: false };
        },
        anzahlGewaehltLinkpool() { return Object.values(this.addLinkpool.gewaehlt).filter(Boolean).length; },
        async sucheLinkquellen() {
            if (this.addLinkpool.suche.length < 2) { this.addLinkpool.treffer = []; return; }
            const p = new URLSearchParams({ suche: this.addLinkpool.suche, limit: 40 });
            const r = await fetch('/api/v1/lam/linkquellen?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            const treffer = j.success ? (j.data.rows || []) : [];
            const poolIds = new Set(this.rows.map(r => r.id));
            this.addLinkpool.treffer = treffer.map(d => ({ ...d, imLinkpool: poolIds.has(d.id) }));
        },
        async fuegeZuLinkpoolHinzu() {
            const ids = Object.keys(this.addLinkpool.gewaehlt).filter(k => this.addLinkpool.gewaehlt[k]);
            if (ids.length === 0 || !this.filter.customer_id) return;
            this.addLinkpool.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkpool-add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.filter.customer_id, domain_ids: ids }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.toast(`${j.data.added} Domain(s) zum Linkpool hinzugefügt` + (j.data.skipped > 0 ? `, ${j.data.skipped} bereits drin (übersprungen)` : ''), 'ok');
                this.addLinkpool.offen = false;
                this.laden();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            this.addLinkpool.laeuft = false;
        },
        async entferneAusLinkpool() {
            if (this.auswahl.size === 0 || !this.filter.customer_id) return;
            const n = this.auswahl.size;
            const kunde = this.kundenName(this.filter.customer_id);
            this.bestaetigen({
                titel: 'Aus Linkpool entfernen',
                text: `${n} Domain(s) aus dem Linkpool von „${kunde}" entfernen?`,
                hinweis: 'Die Domains selbst bleiben in der globalen Linkquellen-Liste — nur die Pool-Zuordnung wird gelöscht.',
                bestaetigenLabel: 'Aus Pool entfernen',
                ist_destruktiv: true,
                aktion: async () => {
                    this.bulkLaeuft = true;
                    try {
                        const r = await fetch('/api/v1/lam/linkpool-remove', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ customer_id: this.filter.customer_id, domain_ids: Array.from(this.auswahl) }),
                        });
                        const j = await r.json();
                        if (!j.success) throw new Error(j.message);
                        this.toast(`${j.data.removed} Domain(s) aus dem Linkpool entfernt`, 'ok');
                        this.auswahlLeeren();
                        this.laden();
                    } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
                    this.bulkLaeuft = false;
                },
            });
        },

        async poolAufVorschlagsliste() {
            if (this.auswahl.size === 0 || !this.filter.customer_id) {
                this.toast('Bitte erst einen Kunden im Filter wählen und Domains anhaken.', 'warn');
                return;
            }
            // Vorhandene Listen des Kunden laden + Modal öffnen
            this.aufListe = {
                offen: true, laeuft: false,
                modus: 'auswaehlen', // oder 'neu'
                listen: [],
                listenId: '',
                neuerName: 'Pool ' + new Date().toLocaleDateString('de-DE'),
                neuerStatus: 'aktiv',
                neueZielzahl: '',
                neueNotiz: '',
            };
            try {
                const r = await fetch('/api/v1/lam/vorschlagslisten?customer_id=' + this.filter.customer_id, { credentials: 'same-origin' });
                const j = await r.json();
                this.aufListe.listen = j.success ? (j.data || []) : [];
                // Wenn schon mindestens eine Liste existiert: vorauswählen
                if (this.aufListe.listen.length > 0) {
                    this.aufListe.listenId = this.aufListe.listen[0].id;
                } else {
                    // Sonst direkt in den „Neue Liste"-Modus springen
                    this.aufListe.modus = 'neu';
                }
            } catch (e) { this.toast('Listen konnten nicht geladen werden: ' + e.message, 'fehler'); }
        },
        async aufListeAusfuehren() {
            const a = this.aufListe;
            if (a.modus === 'auswaehlen' && !a.listenId) { this.toast('Bitte eine Liste auswählen.', 'warn'); return; }
            if (a.modus === 'neu' && !a.neuerName.trim()) { this.toast('Bitte einen Namen für die neue Liste angeben.', 'warn'); return; }
            a.laeuft = true;
            try {
                let listenId = a.listenId;
                let listenName = '';
                if (a.modus === 'neu') {
                    const r = await fetch('/api/v1/lam/vorschlagsliste-save', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            customer_id: this.filter.customer_id,
                            name: a.neuerName.trim(),
                            status: a.neuerStatus,
                            zielzahl: a.neueZielzahl || null,
                            notiz: a.neueNotiz || null,
                        }),
                    });
                    const j = await r.json();
                    if (!j.success) throw new Error(j.message);
                    listenId = j.data.id;
                    listenName = a.neuerName.trim();
                } else {
                    listenName = (a.listen.find(l => l.id == a.listenId) || {}).name || '';
                }
                const addRes = await fetch('/api/v1/lam/vorschlagsliste-eintrag-add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ vorschlagsliste_id: listenId, domain_ids: Array.from(this.auswahl) }),
                });
                const addJ = await addRes.json();
                if (!addJ.success) throw new Error(addJ.message);
                const msg = `${addJ.data.added} Domain(s) auf Liste „${listenName}" gesetzt`
                          + (addJ.data.skipped.length ? `, ${addJ.data.skipped.length} bereits auf der Liste (übersprungen)` : '');
                this.toast(msg, 'ok');
                this.aufListe.offen = false;
                this.auswahlLeeren();
                this.laden();
            } catch (e) { this.toast('Fehler: ' + e.message, 'fehler'); }
            a.laeuft = false;
        },
        // ===== Confirm-Modal statt window.confirm =====
        confirmModal: { offen: false, titel: '', text: '', hinweis: '', bestaetigenLabel: 'OK', ist_destruktiv: false, aktion: null },
        bestaetigen(opts) {
            this.confirmModal = {
                offen: true,
                titel: opts.titel || 'Bist Du sicher?',
                text: opts.text || '',
                hinweis: opts.hinweis || '',
                bestaetigenLabel: opts.bestaetigenLabel || 'OK',
                ist_destruktiv: !!opts.ist_destruktiv,
                aktion: opts.aktion,
            };
        },
        async confirmAusfuehren() {
            const akt = this.confirmModal.aktion;
            this.confirmModal.offen = false;
            if (akt) await akt();
        },

        /** Mini-Toast statt alert() — nicht-blockierend, oben rechts. */
        toast(text, typ = 'ok') {
            const farben = { ok: 'var(--emerald-600)', warn: 'var(--amber-600)', fehler: 'var(--rose-600)' };
            const el = document.createElement('div');
            el.textContent = text;
            el.style.cssText = `position:fixed;top:70px;right:20px;z-index:9999;background:${farben[typ] || farben.ok};color:#fff;padding:10px 16px;border-radius:8px;box-shadow:0 6px 16px rgba(15,23,42,0.18);font-size:0.85rem;max-width:380px;line-height:1.4;animation:toastIn 0.2s ease-out;`;
            document.body.appendChild(el);
            setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.3s'; }, 4500);
            setTimeout(() => el.remove(), 5000);
        },
        istOffen(id, feld) { return this.editZelle.id === id && this.editZelle.feld === feld; },
        oeffneEdit(e, feld) {
            if (this.editLaeuft) return;
            this.editZelle = { id: e.id, feld };
            this.editWert = e[feld] ?? '';
        },
        schliesseEdit() { this.editZelle = { id: null, feld: null }; this.editWert = ''; },
        async speichereInline(e, feld) {
            if (this.editLaeuft) return;
            this.editLaeuft = true;
            try {
                const res = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: e.id, feld, wert: this.editWert })
                });
                if ((await res.json()).success) { e[feld] = this.editWert; this.schliesseEdit(); }
            } finally { this.editLaeuft = false; }
        },
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
            const ausfuehren = async () => {
                this.bulkLaeuft = true;
                try {
                    const res = await fetch('/api/v1/lam/linkoption-bulk', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: Array.from(this.auswahl), aktion: this.bulkAktion, wert: this.bulkWert || null })
                    });
                    if ((await res.json()).success) { this.auswahlLeeren(); await this.laden(); }
                } finally { this.bulkLaeuft = false; }
            };
            if (this.bulkAktion === 'loeschen') {
                this.bestaetigen({
                    titel: 'Einträge löschen', text: `${this.auswahl.size} Einträge wirklich löschen?`,
                    bestaetigenLabel: 'Löschen', ist_destruktiv: true, aktion: ausfuehren,
                });
                return;
            }
            await ausfuehren();
        },
        oeffneCtxMenu(event, ziel) {
            const x = event.clientX, y = event.clientY;
            const px = (x + 220 > window.innerWidth) ? x - 220 : x;
            const py = (y + 380 > window.innerHeight) ? y - 380 : y;
            this.ctxMenu = { offen: true, x: px, y: py, ziel };
        },
        async schnellStatus(ziel, status) {
            if (!ziel) return;
            try {
                const res = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ziel.id, feld: 'status', wert: status })
                });
                if ((await res.json()).success) ziel.status = status;
            } catch (e) {}
        },
        async loescheEintrag(ziel) {
            if (!ziel) return;
            this.bestaetigen({
                titel: 'Eintrag löschen', text: `Eintrag „${ziel.domain_url}" wirklich löschen?`,
                bestaetigenLabel: 'Löschen', ist_destruktiv: true,
                aktion: async () => {
                    await fetch('/api/v1/lam/linkoption-bulk', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: [ziel.id], aktion: 'loeschen' })
                    });
                    await this.laden();
                },
            });
        },

        euro(n) { return n == null ? '—' : parseFloat(n).toLocaleString('de-DE', {style:'currency', currency:'EUR'}); }
    };
}
</script>
