<?php $activeModul = 'linkprofil'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamAufraeumen()" x-init="init()" x-cloak
     @keydown.window="onKey($event)">

<div class="thx-page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <div>
        <h1 class="thx-page-title">Aufräum-Modus</h1>
        <div class="thx-page-subtitle">
            Pro Domain die beste Deep-URL ermitteln und Duplikate löschen.
            Wissensbasis lernt aus Deinen Entscheidungen.
        </div>
    </div>
    <a class="lam-btn lam-btn-secondary" href="/lam/linkprofil">← zurück zur Linkprofil-Tabelle</a>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<!-- Filter / Kundenwahl -->
<section class="lam-filter-card">
    <div class="lam-filter-grid">
        <div class="lam-filter-col-4">
            <label class="lam-filter-label">Kunde</label>
            <select class="lam-filter-select" x-model="customerId" @change="laden()">
                <option value="">— Kunde wählen —</option>
                <template x-for="k in kunden" :key="k.id">
                    <option :value="k.id" x-text="(k.abbreviation || k.name) + ' — ' + k.name"></option>
                </template>
            </select>
        </div>
        <div class="lam-filter-col-4">
            <label class="lam-filter-label">Ansicht</label>
            <div style="display:flex;gap:4px;">
                <button type="button" @click="setzeModus('liste')"
                        :style="{ background: modus==='liste' ? '#0369a1' : '#fff', color: modus==='liste' ? '#fff' : '#334155', border:'1px solid #cbd5e1' }"
                        style="padding:6px 14px;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                    Liste
                </button>
                <button type="button" @click="setzeModus('fokus')"
                        :style="{ background: modus==='fokus' ? '#0369a1' : '#fff', color: modus==='fokus' ? '#fff' : '#334155', border:'1px solid #cbd5e1' }"
                        style="padding:6px 14px;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                    Fokus-Modus
                </button>
            </div>
        </div>
        <div class="lam-filter-col-4" style="display:flex;align-items:end;gap:8px;flex-wrap:wrap;">
            <span class="muted" style="font-size:var(--d-fs-xs);" x-show="!laedt && customerId && modus === 'liste'">
                <strong x-text="vorschlaege.gesamt"></strong> Vorschläge:
                <span style="color:#047857;" x-text="vorschlaege.klar.length + ' klar'"></span> ·
                <span style="color:#64748b;" x-text="vorschlaege.unbestimmt.length + ' unbestimmt'"></span>
            </span>
            <span class="muted" style="font-size:var(--d-fs-xs);" x-show="laedt">Laden …</span>
        </div>
    </div>
</section>

<!-- Kunden-Kontext-Hint nur im Listen-Modus (im Fokus-Modus ist er direkt im Wizard rechts) -->
<div x-show="modus === 'liste' && customerId" x-cloak
     style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:0.8rem;color:#64748b;background:#f8fafc;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
    <div>
        <span style="font-size:1rem;">💡</span>
        <strong style="color:#0f172a;">Kunden-Kontext für KI:</strong>
        <span x-show="!kontextText.length" style="color:#94a3b8;">noch leer — Klick zum Hinterlegen</span>
        <span x-show="kontextText.length > 0" x-text="kontextText.substring(0, 100) + (kontextText.length > 100 ? '…' : '')" style="color:#475569;"></span>
    </div>
    <button @click="kontextLightboxOffen = true"
            style="padding:4px 10px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.75rem;cursor:pointer;flex-shrink:0;">
        <span x-text="kontextText.length ? 'Bearbeiten' : 'Hinzufügen'"></span>
    </button>
</div>

<!-- Kontext-Lightbox -->
<div x-show="kontextLightboxOffen" x-cloak @click.self="kontextLightboxOffen=false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1050;">
    <div style="width:100%;max-width:640px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:1rem;font-weight:600;">Kunden-Kontext für die KI</h3>
            <button @click="kontextLightboxOffen=false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="padding:18px 20px;">
            <p style="font-size:0.8rem;color:#64748b;margin:0 0 10px;line-height:1.4;">
                Sonderfälle für diesen Kunden, die die KI bei der Klassifikation berücksichtigen soll.
                Beispiel: „Datenschutz-Modals in Cookiebannern verlinken auf datenschutz-steinmann.de —
                klassifiziere als 'sonstiges' mit Empfehlung 'unsicher (klären)'."
                Wird in jeden Klassifikations-Prompt eingefügt (max. 8000 Zeichen).
            </p>
            <textarea x-model="kontextText" @input="kontextDirty=true" rows="10"
                      style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-family:inherit;resize:vertical;"
                      placeholder="z.B. Bei diesem Kunden gilt: ..."></textarea>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
            <button @click="kontextLightboxOffen=false"
                    style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;">
                Abbrechen
            </button>
            <button @click="kontextSpeichern();kontextLightboxOffen=false" :disabled="!kontextDirty"
                    style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                    :style="!kontextDirty ? 'opacity:0.5;cursor:not-allowed;' : ''">
                Speichern
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ FOKUS-WIZARD ═══════════════════════════ -->
<div x-show="modus === 'fokus' && customerId" x-cloak style="margin-bottom:16px;">

    <style>
        .qf-tile { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 18px; }
        .qf-tile + .qf-tile { margin-top:18px; }
        .qf-row { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:6px; }
        .qf-row:first-of-type { margin-top:0; }
        .qf-label { font-size:0.65rem; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; font-weight:600; min-width:58px; }
        .qf-chip { background:#fff; border:1px solid #cbd5e1; color:#334155; border-radius:99px; padding:3px 9px; font-size:0.72rem; cursor:pointer; line-height:1.3; font-family:inherit; display:inline-flex; align-items:center; gap:4px; transition:background 0.1s, border-color 0.1s; }
        .qf-chip:hover { background:#eff6ff; border-color:#93c5fd; }
        .qf-chip.is-active { background:#0369a1; border-color:#0369a1; color:#fff; }
        .qf-chip.is-disabled { opacity:0.4; cursor:not-allowed; }
        .qf-chip.is-disabled:hover { background:#fff; border-color:#cbd5e1; }
        .qf-count { font-size:0.66rem; opacity:0.7; font-variant-numeric:tabular-nums; }
        .qf-chip.is-active .qf-count { opacity:0.85; }
        .qf-preset { font-weight:500; }
        .qf-sep { width:1px; height:16px; background:#cbd5e1; margin:0 4px; }
        .qf-tagesziel-input { width:42px; padding:2px 4px; border:1px solid #cbd5e1; border-radius:4px; font-size:0.72rem; text-align:center; font-variant-numeric:tabular-nums; }
    </style>

    <!-- ═══ Kachel 1: Presets (Schnellstart) ═══ -->
    <div class="qf-tile">
        <div class="qf-row">
            <span class="qf-label">Presets</span>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('quickwin')"
                    title="Klassifizierte Einzellinks — schnell durchnicken">⚡ Quick-Wins</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('linktext_reparatur')"
                    title="Domains ohne Linktext, klein zuerst">✏ Linktext-Reparatur</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('gross')"
                    title="Große Cluster (10+) — fokussiert reduzieren">🔧 Große Cluster</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('sitewide')"
                    title="Sitewide-Cluster (25+) — Marathon">🏗 Sitewide</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('wertvoll')"
                    title="Hoher Sistrix-SI — die wertvollen Domains zuerst">⭐ SI ≥ 5</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('schrott')"
                    title="Niedriger oder fehlender SI — schnell wegwerfen">🗑 SI niedrig/fehlt</button>
            <button type="button" class="qf-chip qf-preset" @click="presetAnwenden('unbestimmt')"
                    title="Nur Domains, die noch nicht klassifiziert sind">❓ Unklassifiziert</button>
        </div>
    </div>

    <!-- ═══ Kachel 2: Filter & Sortierung + Counter + Progressbar ═══ -->
    <div class="qf-tile">
        <!-- Topzeile: Counter + Sortierung + Reset + Tagesziel -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div style="display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;">
                <span style="font-size:0.875rem;color:#0f172a;font-weight:500;">
                    Domain <strong x-text="fokusIndex + 1"></strong> / <strong x-text="fokusListe.length"></strong>
                    <span x-show="vorschlaege.gesamt > fokusListe.length" style="color:#64748b;font-weight:400;">
                        &nbsp;·&nbsp; <span x-text="vorschlaege.gesamt"></span> insgesamt offen
                    </span>
                    <span x-show="fokusListe.length > 0" style="color:#94a3b8;font-weight:400;font-size:0.75rem;">
                        (≈ <span x-text="schaetzungMin(fokusListe.length - fokusIndex)"></span> Min übrig)
                    </span>
                </span>
                <span style="font-size:0.75rem;color:#94a3b8;" x-show="fokusErledigt > 0">
                    · <span x-text="fokusErledigt"></span> erledigt
                    <template x-if="fokusTagesziel > 0">
                        <span> / <span x-text="fokusTagesziel"></span>
                            <span x-show="fokusErledigt >= fokusTagesziel" style="color:#16a34a;font-weight:600;margin-left:3px;">✓ geschafft!</span>
                        </span>
                    </template>
                </span>
            </div>
            <div style="display:flex;gap:6px;align-items:center;font-size:0.72rem;">
                <span style="color:#64748b;">Sortierung:</span>
                <select x-model="fokusSort" @change="sortiereFokus()" style="padding:2px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.72rem;">
                    <option value="klar_zuerst">Klare zuerst (Default)</option>
                    <option value="anzahl">Anzahl ↓ (groß zuerst)</option>
                    <option value="anzahl_asc">Anzahl ↑ (klein zuerst)</option>
                    <option value="linkart">gruppiert nach Linkart</option>
                    <option value="si_desc">SI ↓ (wertvolle zuerst)</option>
                    <option value="si_asc">SI ↑ (Schrott zuerst)</option>
                    <option value="domain">Domain (A-Z)</option>
                </select>
                <span class="qf-sep"></span>
                <span style="color:#64748b;">Tagesziel:</span>
                <input type="number" min="0" max="999" class="qf-tagesziel-input"
                       :value="fokusTagesziel" @change="setzeTagesziel($event.target.value)"
                       title="Wieviele Domains willst Du heute schaffen? 0 = aus">
                <button type="button" class="qf-chip" @click="filterReset()"
                        title="Alle Filter zurücksetzen">↺ Reset</button>
                <button type="button" class="qf-chip"
                        :class="nachbearbeitung ? 'is-active' : ''"
                        @click="toggleNachbearbeitung()"
                        title="Auch bereits aufgeraeumte Domains zeigen (z.B. Domains mit verbliebenen Dubletten oder Empfehlung unsicher)">
                    🔄 Nachbearbeitung
                </button>
                <span class="qf-sep"></span>
                <button type="button" class="qf-chip" @click="oeffneMassenAktion()"
                        style="background:#0369a1;color:#fff;border-color:#0369a1;"
                        title="URL/Linktext-Pattern suchen + Aktion auf alle Treffer anwenden">
                    🎯 Massen-Aktion
                </button>
            </div>
        </div>

        <!-- Filter-Chips: Anzahl -->
        <div class="qf-row">
            <span class="qf-label">Anzahl</span>
            <template x-for="b in ['1','2-4','5-9','10+','25+']" :key="b">
                <button type="button" class="qf-chip"
                        :class="[filterAktiv('anzahl', b) ? 'is-active' : '', countFuerFilter('anzahl', b) === 0 ? 'is-disabled' : '']"
                        @click="countFuerFilter('anzahl', b) > 0 && toggleFilter('anzahl', b)">
                    <span x-text="b === '1' ? 'Einzellinks' : b === '2-4' ? 'klein (2-4)' : b === '5-9' ? 'mittel (5-9)' : b === '10+' ? 'groß (10-24)' : 'sitewide (25+)'"></span>
                    <span class="qf-count" x-text="'(' + countFuerFilter('anzahl', b) + ')'"></span>
                </button>
            </template>
        </div>

        <!-- Filter-Chips: Linktext + Status + SI -->
        <div class="qf-row">
            <span class="qf-label">Linktext</span>
            <template x-for="b in [['alle','alle haben'],['gemischt','gemischt'],['keine','keiner hat']]" :key="b[0]">
                <button type="button" class="qf-chip"
                        :class="[filterAktiv('linktext', b[0]) ? 'is-active' : '', countFuerFilter('linktext', b[0]) === 0 ? 'is-disabled' : '']"
                        @click="countFuerFilter('linktext', b[0]) > 0 && toggleFilter('linktext', b[0])">
                    <span x-text="b[1]"></span>
                    <span class="qf-count" x-text="'(' + countFuerFilter('linktext', b[0]) + ')'"></span>
                </button>
            </template>
            <span class="qf-sep"></span>
            <span class="qf-label">Status</span>
            <button type="button" class="qf-chip"
                    :class="fokusKategorie === 'alle' ? 'is-active' : ''"
                    @click="fokusKategorie = 'alle'; sortiereFokus()">alle</button>
            <button type="button" class="qf-chip"
                    :class="fokusKategorie === 'klar' ? 'is-active' : ''"
                    @click="fokusKategorie = 'klar'; sortiereFokus()">klassifiziert</button>
            <button type="button" class="qf-chip"
                    :class="fokusKategorie === 'unbestimmt' ? 'is-active' : ''"
                    @click="fokusKategorie = 'unbestimmt'; sortiereFokus()">unbestimmt</button>
            <span class="qf-sep"></span>
            <span class="qf-label">SI</span>
            <template x-for="b in [['hoch','≥ 5'],['mittel','1–5'],['niedrig','< 1'],['fehlt','fehlt']]" :key="b[0]">
                <button type="button" class="qf-chip"
                        :class="[filterAktiv('si', b[0]) ? 'is-active' : '', countFuerFilter('si', b[0]) === 0 ? 'is-disabled' : '']"
                        @click="countFuerFilter('si', b[0]) > 0 && toggleFilter('si', b[0])">
                    <span x-text="b[1]"></span>
                    <span class="qf-count" x-text="'(' + countFuerFilter('si', b[0]) + ')'"></span>
                </button>
            </template>
            <span class="qf-sep"></span>
            <span class="qf-label">Sonst.</span>
            <template x-for="b in [['unsicher','Empfehlung &bdquo;unsicher&ldquo;'],['mit_dubletten','mit Dubletten (&gt;1)'],['nachbearbeitung','schon bearbeitet'],['nicht_bearbeitet','noch nicht bearbeitet']]" :key="b[0]">
                <button type="button" class="qf-chip"
                        :class="[filterAktiv('sonstiges', b[0]) ? 'is-active' : '', countFuerFilter('sonstiges', b[0]) === 0 ? 'is-disabled' : '']"
                        @click="countFuerFilter('sonstiges', b[0]) > 0 && toggleFilter('sonstiges', b[0])">
                    <span x-text="b[1]"></span>
                    <span class="qf-count" x-text="'(' + countFuerFilter('sonstiges', b[0]) + ')'"></span>
                </button>
            </template>
        </div>

        <!-- Progressbar -->
        <div style="margin-top:10px;height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
            <div style="height:100%;background:#10b981;border-radius:99px;transition:width 0.4s ease-out;"
                 :style="{ width: (fokusListe.length > 0 ? ((fokusIndex / fokusListe.length) * 100) : 0) + '%' }"></div>
        </div>
    </div>

    <!-- ═══ Kachel 3: Arbeitsfläche (Wizard) ═══ -->
    <div class="qf-tile" style="padding:0;overflow:hidden;">
    <template x-if="aktuelleDomain">
        <div style="padding:14px 18px;">

            <style>
                .wz-box { background:#fafbfc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
                .wz-row { display:flex; align-items:center; gap:10px; padding:4px 0; }
                .wz-row + .wz-row { margin-top:3px; }
                .wz-label { font-size:0.68rem; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; font-weight:600; width:120px; flex-shrink:0; }
                .wz-label-step { color:#0369a1; }
                .wz-chips { display:flex; flex-wrap:wrap; gap:5px; flex:1; align-items:center; }
                .wz-chip { background:#fff; border:1px solid #cbd5e1; color:#334155; border-radius:6px; padding:4px 11px; font-size:0.75rem; cursor:pointer; transition:background 0.12s, border-color 0.12s; line-height:1.2; font-family:inherit; }
                .wz-chip:hover { background:#eff6ff; border-color:#93c5fd; }
                .wz-chip.is-active { background:#0369a1; color:#fff; border-color:#0369a1; }
                .wz-chip.is-kunde { background:#fff; border-color:#cbd5e1; color:#334155; }
                .wz-chip.is-kunde:hover { background:#eff6ff; border-color:#93c5fd; }
                .wz-chip.is-kunde.is-active { background:#0369a1; border-color:#0369a1; color:#fff; }
                .wz-chip.is-neu { border-style:dashed; color:#64748b; font-weight:400; }
                .wz-chip.is-neu:hover { background:#f8fafc; color:#0369a1; border-color:#0369a1; }
                .wz-divider { width:1px; height:18px; background:#e2e8f0; margin:0 6px; }
                /* URL-Tabelle */
                .url-tab-grid { display:grid; grid-template-columns:32px minmax(220px,1.6fr) minmax(160px,1.1fr) minmax(160px,1.1fr) 60px 60px; gap:18px; align-items:center; }
                .url-tab-row { padding:8px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background 0.15s; font-size:0.78rem; }
                .url-tab-row:hover { background:#f8fafc !important; }
                .url-tab-row.keep { background:#f0fdf4; }
                .url-tab-open { display:inline-flex;align-items:center;justify-content:center;gap:4px;background:#0369a1;border:1px solid #0369a1;color:#fff;padding:5px 10px;border-radius:5px;text-decoration:none;font-weight:600;font-size:0.75rem;line-height:1;flex-shrink:0; }
                .url-tab-open:hover { background:#075985;border-color:#075985; }
                .url-tab-metric { text-align:center;font-variant-numeric:tabular-nums;font-weight:600; }
            </style>

            <!-- ═══ Header: Domain + Status + SI (alles in einer Zeile) ═══ -->
            <div style="margin-bottom:12px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1.1rem;font-weight:600;color:#0f172a;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span x-text="aktuelleDomain.domain" style="word-break:break-all;"></span>
                    <button @click="wizardReklassifiziere()" title="Mit aktuellem Kunden-Kontext neu klassifizieren"
                            style="background:#fff;border:1px solid #cbd5e1;color:#64748b;border-radius:4px;padding:2px 8px;font-size:0.7rem;cursor:pointer;font-weight:400;">
                        ↻ neu klassifizieren
                    </button>
                </h2>
                <div style="display:flex;gap:10px;align-items:center;font-size:0.78rem;color:#64748b;">
                    <span><strong x-text="aktuelleDomain.anzahl"></strong> Verlinkung(en)</span>
                    <span x-show="aktuelleDomain.sistrix_si != null" style="color:#0369a1;">
                        · SI <strong style="font-size:0.95rem;font-variant-numeric:tabular-nums;"
                                     x-text="Number(aktuelleDomain.sistrix_si).toFixed(2)"></strong>
                    </span>
                    <span x-show="aktuelleDomain.kategorie === 'klar'" style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:99px;font-size:0.7rem;">aus Wissensbasis</span>
                    <span x-show="aktuelleDomain.kategorie === 'unbestimmt'" style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:99px;font-size:0.7rem;">unbestimmt</span>
                    <span x-show="aktuelleDomain.ist_nachbearbeitung" style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:99px;font-size:0.7rem;" :title="aktuelleDomain.anzahl_bestaetigt + ' Verlinkung(en) bereits aufgeräumt'">🔄 Nachbearbeitung</span>
                    <span x-show="aktuelleDomain.hat_unsichere_empfehlung" style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:99px;font-size:0.7rem;" :title="aktuelleDomain.anzahl_unsicher + ' Verlinkung(en) mit Empfehlung &bdquo;unsicher&ldquo;'">⚠ unsicher</span>
                    <!-- KI briefen Button -->
                    <button @click="kiBriefenOffen = true"
                            style="background:#0369a1;border:none;color:#fff;padding:4px 12px;border-radius:5px;font-size:0.72rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"
                            title="Notiz für diese Domain + globale Regeln + Muster verwalten">
                        💡 KI briefen
                        <span x-show="domainNotizText && domainNotizText.trim().length > 0"
                              style="background:#0c4a6e;padding:1px 6px;border-radius:99px;font-size:0.6rem;">Notiz</span>
                    </button>
                </div>
            </div>

            <!-- ═══ Schritt 1: URL-Tabelle (volle Breite) ═══ -->
            <div style="margin-bottom:12px;">

                <!-- URL-Tabelle -->
                <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:0.7rem;color:#0369a1;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">Schritt 1 · URLs prüfen, behalten anhaken</span>
                        <span style="font-size:0.8rem;font-weight:600;">
                            <span style="color:#047857;"><span x-text="wizardBehalten.length"></span> behalten</span>
                            · <span style="color:#dc2626;"><span x-text="wizardGeloescht.length"></span> löschen</span>
                        </span>
                    </div>
                    <!-- Merge-Hinweis -->
                    <div x-show="wizardMergeHinweis" x-cloak
                         style="padding:6px 12px;background:#fefce8;border-bottom:1px solid #fde68a;font-size:0.72rem;color:#92400e;display:flex;justify-content:space-between;gap:8px;">
                        <span>⇄ <span x-text="wizardMergeHinweis"></span></span>
                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:#78350f;">
                            <input type="checkbox" x-model="wizard.mergeLinktext" style="cursor:pointer;">
                            <span style="font-size:0.68rem;">Linktexte mergen</span>
                        </label>
                    </div>
                    <!-- Tabellen-Header -->
                    <div class="url-tab-grid" style="padding:6px 12px;background:#fafbfc;border-bottom:1px solid #e2e8f0;font-size:0.62rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
                        <span></span>
                        <span>Quell-URL</span>
                        <span>Linktext</span>
                        <span>→ Linkziel</span>
                        <span style="text-align:center;" title="URL-Tiefe = Anzahl Pfadsegmente. 0 = Startseite, 2 = /kategorie/seite, je tiefer desto spezifischer (Deeplink)">Tiefe</span>
                        <span style="text-align:center;" title="Score 0.00–1.00: gewichtete Bewertung aus SI, HTTP, URL-Tiefe, Linktext, Alter. Höher = bessere URL">Score</span>
                    </div>
                    <div style="max-height:340px;overflow-y:auto;">
                        <template x-for="v in (wizardDetail.verlinkungen || [])" :key="v.id">
                            <div @click="wizardToggleId(v.id)" class="url-tab-grid url-tab-row"
                                 :class="wizardIstBehalten(v.id) ? 'keep' : ''">
                                <!-- ✓ -->
                                <div style="text-align:center;">
                                    <span x-show="wizardIstBehalten(v.id)" style="color:#047857;font-weight:bold;font-size:1.1rem;">✓</span>
                                    <span x-show="!wizardIstBehalten(v.id)" style="color:#cbd5e1;font-size:1.1rem;">○</span>
                                </div>
                                <!-- Quell-URL + Öffnen-Button dahinter -->
                                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                                    <span :style="{ color: wizardIstBehalten(v.id) ? '#0f172a' : '#94a3b8', textDecoration: wizardIstBehalten(v.id) ? 'none' : 'line-through' }"
                                          style="word-break:break-all;line-height:1.3;flex:1;min-width:0;"
                                          x-text="kurzUrl(v.verlinkende_url)"></span>
                                    <a :href="v.verlinkende_url" target="_blank" rel="noopener" @click.stop
                                       class="url-tab-open" title="URL im neuen Tab öffnen">öffnen ↗</a>
                                </div>
                                <!-- Linktext (inline editierbar) -->
                                <div @click.stop style="overflow:hidden;">
                                    <template x-if="linktextEditId !== v.id">
                                        <div style="display:flex;gap:6px;align-items:center;min-width:0;cursor:pointer;"
                                             @click="oeffneLinktextEditor(v)"
                                             :title="v.linktext ? 'Klicken zum Bearbeiten: ' + v.linktext : 'Klicken um Linktext zu erfassen'">
                                            <span x-show="v.linktext"
                                                  style="color:#475569;font-style:italic;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;"
                                                  x-text="'&bdquo;' + (v.linktext || '') + '&ldquo;'"></span>
                                            <span x-show="!v.linktext" style="color:#94a3b8;font-size:0.7rem;text-decoration:underline dotted;">✏ eintragen</span>
                                        </div>
                                    </template>
                                    <template x-if="linktextEditId === v.id">
                                        <div style="display:flex;gap:4px;align-items:center;">
                                            <input type="text" :id="'linktext-edit-' + v.id" x-model="linktextEditWert"
                                                   @keydown.enter="speichereLinktext(v)" @keydown.escape="linktextEditId = null"
                                                   placeholder="Linktext eintragen"
                                                   style="flex:1;min-width:0;padding:3px 6px;border:1px solid #0369a1;border-radius:4px;font-size:0.72rem;font-family:inherit;">
                                            <button @click="speichereLinktext(v)" title="Speichern (Enter)"
                                                    style="background:#0369a1;border:none;color:#fff;padding:3px 7px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;">✓</button>
                                            <button @click="linktextEditId = null" title="Abbrechen (Esc)"
                                                    style="background:#fff;border:1px solid #cbd5e1;color:#64748b;padding:3px 7px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;">×</button>
                                        </div>
                                    </template>
                                </div>
                                <!-- Linkziel: ermitteln (Crawl) oder manuell eintragen -->
                                <div @click.stop style="overflow:hidden;">
                                    <template x-if="zielEditId !== v.id">
                                        <div style="display:flex;gap:6px;align-items:center;min-width:0;">
                                            <template x-if="v.ziel_url">
                                                <span style="color:#0369a1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;flex:1;min-width:0;"
                                                      :title="'Klicken zum Bearbeiten: ' + v.ziel_url"
                                                      @click="oeffneZielEditor(v)"
                                                      x-text="kurzUrl(v.ziel_url)"></span>
                                            </template>
                                            <template x-if="!v.ziel_url">
                                                <div style="display:flex;gap:4px;align-items:center;">
                                                    <button @click="ermittleZielUrl(v)" :disabled="zielErmittleId === v.id"
                                                            :style="{ opacity: zielErmittleId === v.id ? 0.5 : 1 }"
                                                            style="background:#fff;border:1px solid #bae6fd;color:#0369a1;padding:2px 8px;border-radius:4px;font-size:0.7rem;cursor:pointer;font-weight:500;"
                                                            title="Crawlt die Quell-URL und sucht den Anker zur Kunden-Domain">
                                                        <span x-show="zielErmittleId !== v.id">🔍 ermitteln</span>
                                                        <span x-show="zielErmittleId === v.id">… crawlt</span>
                                                    </button>
                                                    <button @click="oeffneZielEditor(v)"
                                                            style="background:#fff;border:1px solid #cbd5e1;color:#64748b;padding:2px 8px;border-radius:4px;font-size:0.7rem;cursor:pointer;"
                                                            title="Linkziel manuell eintragen">
                                                        ✏ eintragen
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="zielEditId === v.id">
                                        <div style="display:flex;gap:4px;align-items:center;">
                                            <button @click="ermittleZielUrl(v, true)" :disabled="zielErmittleId === v.id"
                                                    :style="{ opacity: zielErmittleId === v.id ? 0.5 : 1 }"
                                                    style="background:#fff;border:1px solid #bae6fd;color:#0369a1;padding:3px 7px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;"
                                                    title="Crawlt die Quell-URL und füllt das Feld mit dem gefundenen Linkziel">
                                                <span x-show="zielErmittleId !== v.id">🔍</span>
                                                <span x-show="zielErmittleId === v.id">…</span>
                                            </button>
                                            <input type="text" :id="'ziel-edit-' + v.id" x-model="zielEditWert"
                                                   @keydown.enter="speichereZielUrl(v)" @keydown.escape="zielEditId = null"
                                                   placeholder="https://kunden-domain.de/seite"
                                                   style="flex:1;min-width:0;padding:3px 6px;border:1px solid #0369a1;border-radius:4px;font-size:0.72rem;font-family:inherit;">
                                            <button @click="speichereZielUrl(v)" title="Speichern (Enter)"
                                                    style="background:#0369a1;border:none;color:#fff;padding:3px 7px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;">✓</button>
                                            <button @click="zielEditId = null" title="Abbrechen (Esc)"
                                                    style="background:#fff;border:1px solid #cbd5e1;color:#64748b;padding:3px 7px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;">×</button>
                                        </div>
                                    </template>
                                </div>
                                <!-- Tiefe -->
                                <div class="url-tab-metric" style="color:#475569;font-size:0.95rem;" x-text="v.url_tiefe"></div>
                                <!-- Score -->
                                <div class="url-tab-metric" style="color:#0f172a;font-size:0.95rem;"
                                     x-text="Number(v.score).toFixed(2)"></div>
                            </div>
                        </template>
                        <template x-if="!(wizardDetail.verlinkungen || []).length">
                            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:0.8rem;">Keine Verlinkungen geladen</div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ═══ Entscheidungen (Schritt 2–4): 2-Spalten-Layout ═══ -->
            <div class="wz-box" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start;">

                <!-- ──── Linke Spalte: Linkart + Wieviele behalten ──── -->
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <!-- Schritt 2: Linkart -->
                    <div class="wz-row" style="align-items:flex-start;">
                        <span class="wz-label wz-label-step">Schritt 2 · Linkart</span>
                        <div class="wz-chips">
                            <template x-for="la in linkarten" :key="la">
                                <button type="button" class="wz-chip"
                                        :class="wizard.linkart === la ? 'is-active' : ''"
                                        @click="wizard.linkart = la; wizard.geaendert = true"
                                        x-text="formatLinkart(la)"></button>
                            </template>
                            <template x-if="(vorschlaege.kunden_linkarten || []).length">
                                <span class="wz-divider"></span>
                            </template>
                            <template x-for="kl in (vorschlaege.kunden_linkarten || [])" :key="kl.id">
                                <button type="button" class="wz-chip is-kunde"
                                        :class="wizard.linkart === kl.linkart_key ? 'is-active' : ''"
                                        :title="kl.beschreibung || 'Kundenspezifisch'"
                                        @click="wizard.linkart = kl.linkart_key; wizard.strategie = kl.default_strategie || wizard.strategie; wizard.geaendert = true; ladeWizardDetail()">
                                    ★ <span x-text="kl.label"></span>
                                </button>
                            </template>
                            <button type="button" class="wz-chip is-neu" @click="oeffneNeueLinkart()" title="Neue kundenspezifische Linkart anlegen">+ Neu</button>
                            <button type="button" class="wz-chip is-neu" @click="oeffneLinkartVerwaltung()"
                                    x-show="(vorschlaege.kunden_linkarten || []).length"
                                    title="Kundenspezifische Linkarten bearbeiten / löschen">⚙ verwalten</button>
                        </div>
                    </div>

                    <!-- Schritt 4: Strategie (alle/auf 1/auf 2) -->
                    <div class="wz-row" style="border-top:1px dashed #e2e8f0;padding-top:8px;margin-top:2px;">
                        <span class="wz-label wz-label-step">Schritt 4 · Wieviele behalten</span>
                        <div class="wz-chips" style="flex:0 0 auto;">
                            <button type="button" class="wz-chip" :class="wizard.strategie === 'alle_behalten' ? 'is-active' : ''"
                                    @click="wizard.strategie='alle_behalten'; wizard.geaendert=true; ladeWizardDetail()"
                                    title="Alle Verlinkungen behalten — nichts entfernen">alle behalten</button>
                            <button type="button" class="wz-chip" :class="wizard.strategie === 'reduktion_auf_1' ? 'is-active' : ''"
                                    @click="wizard.strategie='reduktion_auf_1'; wizard.geaendert=true; ladeWizardDetail()"
                                    title="Nur die beste URL behalten, Rest löschen">nur die beste</button>
                            <button type="button" class="wz-chip" :class="wizard.strategie === 'reduktion_auf_2' ? 'is-active' : ''"
                                    @click="wizard.strategie='reduktion_auf_2'; wizard.geaendert=true; ladeWizardDetail()"
                                    title="Die zwei besten URLs behalten">die besten 2</button>
                        </div>
                    </div>
                </div>

                <!-- ──── Rechte Spalte: Empfehlung + Erweitert ──── -->
                <div style="display:flex;flex-direction:column;gap:8px;border-left:1px solid #e2e8f0;padding-left:14px;">
                    <!-- Schritt 3: Empfehlung -->
                    <div class="wz-row" style="align-items:flex-start;">
                        <span class="wz-label wz-label-step">Schritt 3 · Empfehlung</span>
                        <div class="wz-chips">
                            <template x-for="e in (vorschlaege.empfehlungen || [])" :key="e">
                                <button type="button" class="wz-chip"
                                        :class="wizard.empfehlung === e ? 'is-active' : ''"
                                        @click="wizard.empfehlung = (wizard.empfehlung === e ? '' : e); wizard.geaendert = true"
                                        x-text="formatEmpfehlung(e)"></button>
                            </template>
                        </div>
                    </div>

                    <!-- Erweitert: URL-Pref + Linkziel — nur sichtbar wenn reduziert wird -->
                    <div x-show="wizard.strategie !== 'alle_behalten'" x-cloak
                         style="border-top:1px dashed #e2e8f0;padding-top:8px;margin-top:2px;display:flex;flex-direction:column;gap:6px;">
                        <span style="font-size:0.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;font-weight:600;">
                            ⚙ Erweitert (greift nur beim Reduzieren)
                        </span>
                        <div class="wz-row">
                            <span class="wz-label" style="color:#94a3b8;">Welche bevorzugen?</span>
                            <div class="wz-chips">
                                <button type="button" class="wz-chip" :class="wizard.urlStrategie === 'auto' ? 'is-active' : ''"
                                        @click="wizard.urlStrategie='auto'; wizard.geaendert=true; ladeWizardDetail()"
                                        title="Score-basiert (Default)">Auto (Score)</button>
                                <button type="button" class="wz-chip" :class="wizard.urlStrategie === 'tiefste' ? 'is-active' : ''"
                                        @click="wizard.urlStrategie='tiefste'; wizard.geaendert=true; ladeWizardDetail()"
                                        title="Tiefere URLs (Deeplinks) bevorzugen">Deeplink</button>
                                <button type="button" class="wz-chip" :class="wizard.urlStrategie === 'kuerzeste' ? 'is-active' : ''"
                                        @click="wizard.urlStrategie='kuerzeste'; wizard.geaendert=true; ladeWizardDetail()"
                                        title="Kürzeste URL (z.B. Startseite) bevorzugen">Kürzeste</button>
                                <button type="button" class="wz-chip" :class="wizard.urlStrategie === 'deeplink_aber_score' ? 'is-active' : ''"
                                        @click="wizard.urlStrategie='deeplink_aber_score'; wizard.geaendert=true; ladeWizardDetail()"
                                        title="Bei Gleichstand Deeplink, sonst Score">Score + Deeplink</button>
                            </div>
                        </div>
                        <div class="wz-row">
                            <span class="wz-label" style="color:#94a3b8;">Ziel-URL Hint</span>
                            <div class="wz-chips">
                                <button type="button" class="wz-chip"
                                        :class="!wizard.zielUrl ? 'is-active' : ''"
                                        @click="wizard.zielUrl = ''; wizard.geaendert = true">— offen —</button>
                                <template x-for="lz in (vorschlaege.linkziele || [])" :key="lz.id">
                                    <button type="button" class="wz-chip"
                                            :class="wizard.zielUrl === lz.url ? 'is-active' : ''"
                                            :title="lz.url"
                                            @click="wizard.zielUrl = lz.url; wizard.geaendert = true"
                                            x-text="lz.thema || kurzUrl(lz.url)"></button>
                                </template>
                                <button type="button" class="wz-chip is-neu" @click="wizardZielErmitteln()" title="Häufigstes Linkziel aus den behaltenen URLs übernehmen">
                                    ↳ aus URLs ermitteln
                                </button>
                                <span x-show="wizard.zielUrl" style="color:#64748b;font-size:0.7rem;margin-left:4px;" :title="wizard.zielUrl"
                                      x-text="kurzUrl(wizard.zielUrl)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="wizardLaedt" style="margin-top:8px;font-size:0.7rem;color:#94a3b8;">Lade Detail …</div>
        </div>
    </template>

    <template x-if="!aktuelleDomain && customerId && !laedt">
        <div style="padding:40px;text-align:center;color:#64748b;">
            Keine Vorschläge in der gewählten Filterung.
        </div>
    </template>

    <!-- Sticky Footer mit Aktions-Buttons + Tastatur-Hinweise -->
    <div style="position:sticky;bottom:0;padding:10px 18px;background:#fff;border-top:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <div style="font-size:0.7rem;color:#94a3b8;">
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">↵</kbd> übernehmen ·
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">→</kbd> skip ·
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">←</kbd> zurück ·
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">E</kbd> bearbeiten ·
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">Ctrl+Z</kbd> undo ·
            <kbd style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;padding:1px 5px;font-family:inherit;">Esc</kbd> Liste
        </div>
        <div style="display:flex;gap:6px;">
            <button @click="wizardSkip()" :disabled="!aktuelleDomain"
                    style="padding:6px 12px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                Skip →
            </button>
            <button @click="oeffneDetail(aktuelleDomain)" :disabled="!aktuelleDomain"
                    style="padding:6px 12px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                Bearbeiten (E)
            </button>
            <button @click="wizardAnnehmen()" :disabled="!aktuelleDomain || !wizard.linkart || wizardLaedt"
                    style="padding:6px 16px;border:none;background:#047857;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                    :style="{ opacity: (!aktuelleDomain || !wizard.linkart || wizardLaedt) ? 0.5 : 1 }">
                ✓ Übernehmen (↵)
            </button>
        </div>
    </div>
    </div><!-- /Kachel 3 -->
</div><!-- /Fokus-Wizard Wrapper -->

<!-- Undo-Toast -->
<div x-show="undoToast.text" x-cloak
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 16px;border-radius:6px;font-size:0.875rem;display:flex;align-items:center;gap:12px;z-index:1100;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
    <span x-text="undoToast.text"></span>
    <button @click="undoAusfuehren()" style="background:#1e293b;border:none;color:#fff;padding:4px 10px;border-radius:4px;font-size:0.8rem;cursor:pointer;">
        Rückgängig
    </button>
</div>

<!-- ═══════════════════ MUSTER-VORSCHLÄGE (nach KI-Analyse) ═══════════════════ -->
<div x-show="musterVorschlaegeOffen" x-cloak @click.self="musterVorschlaegeOffen = false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;">
    <div style="width:100%;max-width:780px;max-height:90vh;background:#fff;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;font-weight:600;color:#0f172a;">🧩 Muster erkannt</h3>
                <div style="font-size:0.75rem;color:#64748b;margin-top:2px;">
                    Aus Deiner Notiz zu <strong x-text="musterVorschlaegeUrsprung.domain"></strong> hat die KI mögliche Muster abgeleitet. Wähle pro Muster, was passieren soll.
                </div>
            </div>
            <button @click="musterVorschlaegeOffen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 22px;">
            <template x-if="musterVorschlaege.length === 0">
                <div style="padding:30px;text-align:center;color:#94a3b8;">Keine Vorschläge.</div>
            </template>
            <template x-for="(v, idx) in musterVorschlaege" :key="idx">
                <div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:10px;background:#fafbfc;">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.85rem;font-weight:600;color:#0f172a;">
                                <span style="color:#0369a1;" x-text="formatMusterTyp(v.muster_typ)"></span>
                                <code style="background:#fef3c7;padding:1px 6px;border-radius:4px;font-size:0.8rem;margin-left:4px;" x-text="v.muster_value"></code>
                            </div>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:4px;">
                                → linkart: <strong x-text="v.aktion_linkart || '—'"></strong>
                                <template x-if="v.aktion_strategie"><span> &middot; strategie: <strong x-text="v.aktion_strategie"></strong></span></template>
                                <template x-if="v.aktion_empfehlung"><span> &middot; empfehlung: <strong x-text="v.aktion_empfehlung"></strong></span></template>
                            </div>
                            <div x-show="v.beschreibung" style="font-size:0.72rem;color:#475569;margin-top:4px;font-style:italic;" x-text="v.beschreibung"></div>
                            <div style="font-size:0.7rem;color:#94a3b8;margin-top:6px;">
                                <span x-show="v.treffer_count > 0" style="color:#0369a1;font-weight:600;">
                                    🎯 <span x-text="v.treffer_count"></span> Domain(s) würden matchen
                                </span>
                                <span x-show="v.treffer_count === 0" style="color:#94a3b8;">
                                    Keine matchenden Domains aktuell offen
                                </span>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;font-size:0.72rem;">
                            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;">
                                <input type="radio" :name="'mv-' + idx" value="verwerfen" x-model="musterVorschlaege[idx].auswahl">
                                <span style="color:#94a3b8;">verwerfen</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;">
                                <input type="radio" :name="'mv-' + idx" value="nur_speichern" x-model="musterVorschlaege[idx].auswahl">
                                <span style="color:#475569;">nur speichern</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;" :style="{ opacity: (v.treffer_count > 0 && v.aktion_linkart) ? 1 : 0.4 }">
                                <input type="radio" :name="'mv-' + idx" value="speichern_und_anwenden" x-model="musterVorschlaege[idx].auswahl" :disabled="!(v.treffer_count > 0 && v.aktion_linkart)">
                                <span style="color:#0369a1;font-weight:600;">speichern + anwenden</span>
                            </label>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;gap:8px;align-items:center;">
            <span style="font-size:0.7rem;color:#94a3b8;">„Anwenden" setzt matchende Domains direkt in der Wissensbasis — bestätigt manuelle Einträge bleiben unberührt.</span>
            <div style="display:flex;gap:8px;">
                <button @click="musterVorschlaegeOffen = false" style="background:#fff;border:1px solid #cbd5e1;color:#475569;padding:7px 14px;border-radius:6px;font-size:0.85rem;cursor:pointer;">Abbrechen</button>
                <button @click="musterVorschlaegeUebernehmen()" style="background:#0369a1;border:none;color:#fff;padding:7px 16px;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;">Auswahl übernehmen</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MUSTER-LISTE (bestätigte Muster verwalten) ═══════════════════ -->
<div x-show="musterListeOffen" x-cloak @click.self="musterListeOffen = false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;">
    <div style="width:100%;max-width:720px;max-height:90vh;background:#fff;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;font-weight:600;color:#0f172a;">🧩 Aktive Muster</h3>
                <div style="font-size:0.75rem;color:#64748b;margin-top:2px;">
                    Bestätigte Regeln, die in jeden KI-Klassifikations-Prompt für diesen Kunden einfließen.
                </div>
            </div>
            <button @click="musterListeOffen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 22px;">
            <template x-if="musterListe.length === 0">
                <div style="padding:30px;text-align:center;color:#94a3b8;font-size:0.85rem;">
                    Noch keine Muster bestätigt. Schreibe eine Notiz zu einer Domain und klicke „Speichern + Muster suchen".
                </div>
            </template>
            <template x-for="m in musterListe" :key="m.id">
                <div style="border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:8px;display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.85rem;color:#0f172a;">
                            <span style="color:#0369a1;" x-text="formatMusterTyp(m.muster_typ)"></span>
                            <code style="background:#fef3c7;padding:1px 6px;border-radius:4px;font-size:0.8rem;margin-left:4px;" x-text="m.muster_value"></code>
                            → <strong x-text="m.aktion_linkart"></strong>
                            <template x-if="m.aktion_empfehlung"><span style="color:#64748b;"> &middot; <span x-text="m.aktion_empfehlung"></span></span></template>
                        </div>
                        <div x-show="m.beschreibung" style="font-size:0.7rem;color:#475569;margin-top:3px;font-style:italic;" x-text="m.beschreibung"></div>
                        <div style="font-size:0.65rem;color:#94a3b8;margin-top:4px;">
                            <span x-text="m.herkunft === 'ki_extrahiert' ? '🤖 KI' : '✋ manuell'"></span>
                            <template x-if="m.ursprungs_domain"><span> &middot; aus <span x-text="m.ursprungs_domain"></span></span></template>
                            <template x-if="m.anzahl_anwendungen > 0"><span> &middot; <span x-text="m.anzahl_anwendungen"></span>x angewendet</span></template>
                        </div>
                    </div>
                    <button @click="loescheMuster(m.id)" style="background:#fff;border:1px solid #fecaca;color:#b91c1c;padding:4px 9px;border-radius:4px;font-size:0.7rem;cursor:pointer;flex-shrink:0;" title="Muster löschen">
                        Löschen
                    </button>
                </div>
            </template>
        </div>
        <div style="padding:12px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">
            <button @click="musterListeOffen = false" style="background:#fff;border:1px solid #cbd5e1;color:#475569;padding:6px 14px;border-radius:6px;font-size:0.8rem;cursor:pointer;">Schließen</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ KI BRIEFEN (Domain-Notiz + Globale Regeln + Muster) ═══════════════════════════ -->
<div x-show="kiBriefenOffen" x-cloak @click.self="kiBriefenOffen = false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;">
    <div style="width:100%;max-width:780px;max-height:90vh;background:#fff;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden;display:flex;flex-direction:column;">
        <!-- Header -->
        <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;font-weight:600;color:#0f172a;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    💡 KI briefen
                    <span x-show="aktuelleDomain" style="font-size:0.85rem;color:#64748b;font-weight:400;" x-text="'· ' + (aktuelleDomain ? aktuelleDomain.domain : '')"></span>
                </h3>
                <div style="font-size:0.72rem;color:#64748b;margin-top:2px;">
                    Was soll die KI über diese Domain wissen? Optional Muster ableiten, die auf ähnliche Domains übertragen werden.
                </div>
            </div>
            <button @click="kiBriefenOffen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>

        <!-- Body -->
        <div style="flex:1;overflow-y:auto;padding:14px 22px;display:flex;flex-direction:column;gap:14px;">

            <!-- Globale Regeln + Muster Zugriff -->
            <div style="display:flex;gap:8px;align-items:center;font-size:0.75rem;flex-wrap:wrap;">
                <span style="color:#64748b;">Weitere Kontexte für diesen Kunden:</span>
                <button @click="kiBriefenOffen = false; oeffneGlobaleRegeln()"
                        style="background:#fff;border:1px solid #cbd5e1;color:#475569;border-radius:99px;padding:4px 12px;font-size:0.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"
                        title="Kundenweite Regeln bearbeiten (gilt für alle Domains dieses Kunden)">
                    🌐 Globale Regeln
                    <span x-show="kontextText && kontextText.trim().length > 0"
                          style="background:#e0f2fe;color:#0369a1;padding:0 6px;border-radius:99px;font-size:0.65rem;font-weight:600;"
                          x-text="kontextText.trim().length + ' Z.'"></span>
                </button>
                <button @click="kiBriefenOffen = false; oeffneMusterListe()"
                        style="background:#fff;border:1px solid #cbd5e1;color:#475569;border-radius:99px;padding:4px 12px;font-size:0.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px;"
                        title="Bestätigte Muster für diesen Kunden anzeigen">
                    🧩 Muster
                    <span x-show="(musterListe || []).length > 0"
                          style="background:#dcfce7;color:#166534;padding:0 6px;border-radius:99px;font-size:0.65rem;font-weight:600;"
                          x-text="(musterListe || []).length"></span>
                </button>
            </div>

            <!-- Domain-Notiz -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:10px 14px;background:#f0f9ff;border-bottom:1px solid #bae6fd;font-size:0.75rem;color:#0369a1;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                    📝 Notiz zu DIESER Domain
                </div>
                <textarea x-model="domainNotizText" @input="domainNotizDirty = true"
                          placeholder="Was ist das Besondere an dieser Domain? Beispiel: „Cookiebanner auf datenschutz-steinmann.de → sonstiges, Empfehlung unsicher. Gilt vermutlich auch für andere datenschutz-*-Domains."
                          style="width:100%;padding:12px 14px;border:none;font-size:0.85rem;font-family:inherit;resize:vertical;outline:none;line-height:1.45;min-height:240px;"></textarea>
                <div style="padding:8px 14px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:0.68rem;color:#94a3b8;">
                    Geht in jeden KI-Prompt für diese Domain &middot; „Speichern + Muster suchen" lässt die KI ähnliche Domains finden &middot; max 4.000 Z.
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
            <button @click="kiBriefenOffen = false"
                    style="background:#fff;border:1px solid #cbd5e1;color:#475569;padding:7px 14px;border-radius:6px;font-size:0.85rem;cursor:pointer;">Schließen</button>
            <button @click="speichereDomainNotiz(false).then(() => kiBriefenOffen = false)"
                    :disabled="!domainNotizDirty || domainNotizSpeichert"
                    :style="{ opacity: domainNotizDirty && !domainNotizSpeichert ? 1 : 0.4, cursor: domainNotizDirty && !domainNotizSpeichert ? 'pointer' : 'default' }"
                    style="background:#fff;border:1px solid #cbd5e1;color:#475569;padding:7px 14px;border-radius:6px;font-size:0.85rem;font-weight:600;">
                Nur speichern
            </button>
            <button @click="speichereDomainNotiz(true)"
                    :disabled="!domainNotizText || domainNotizSpeichert"
                    :style="{ opacity: domainNotizText && !domainNotizSpeichert ? 1 : 0.4, cursor: domainNotizText && !domainNotizSpeichert ? 'pointer' : 'default' }"
                    style="background:#0369a1;border:none;color:#fff;padding:7px 16px;border-radius:6px;font-size:0.85rem;font-weight:600;"
                    title="Speichern + KI sucht ähnliche Domains anhand der Notiz">
                <span x-show="!domainNotizSpeichert">💾 Speichern + Muster suchen</span>
                <span x-show="domainNotizSpeichert">… KI denkt</span>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ MASSEN-AKTION (manueller Muster-Editor) ═══════════════════════════ -->
<div x-show="massenAktionOffen" x-cloak @click.self="massenAktionOffen = false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.55);z-index:1200;">
    <div style="width:100%;max-width:820px;max-height:90vh;background:#fff;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden;display:flex;flex-direction:column;">
        <!-- Header -->
        <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;font-weight:600;color:#0f172a;">🎯 Massen-Aktion</h3>
                <div style="font-size:0.72rem;color:#64748b;margin-top:2px;">
                    Pattern + Aktion definieren → wird auf alle matchenden Domains angewendet (sind danach mit Klassifikation in der Wissensbasis und tauchen im Fokus-Wizard mit ausgewählten Chips auf).
                </div>
            </div>
            <button @click="massenAktionOffen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <!-- Body -->
        <div style="flex:1;overflow-y:auto;padding:14px 22px;display:flex;flex-direction:column;gap:14px;">

            <!-- Pattern-Eingabe -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;background:#fafbfc;">
                <div style="font-size:0.68rem;color:#0369a1;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Schritt 1 · Pattern</div>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                    <span style="font-size:0.7rem;color:#64748b;">Wo suchen:</span>
                    <template x-for="opt in [
                        ['ziel_url_pattern', 'Ziel-URL enthält'],
                        ['domain_pattern',   'Quell-Domain enthält'],
                        ['domain_suffix',    'Quell-Domain endet auf'],
                        ['linktext_pattern', 'Linktext enthält'],
                        ['keyword',          'Quell-URL enthält']
                    ]" :key="opt[0]">
                        <button type="button" class="wz-chip"
                                :class="massenAktion.muster_typ === opt[0] ? 'is-active' : ''"
                                @click="massenAktion.muster_typ = opt[0]; massenPreviewLaden()"
                                x-text="opt[1]"></button>
                    </template>
                </div>
                <input type="text" x-model="massenAktion.muster_value" @input="massenPreviewDebounced()"
                       placeholder="z.B. /datenschutz oder website-list oder fryka.de"
                       style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-family:inherit;">
                <!-- Live-Preview -->
                <div style="margin-top:10px;font-size:0.8rem;color:#0f172a;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <template x-if="massenPreview.laedt">
                        <span style="color:#94a3b8;">… suche</span>
                    </template>
                    <template x-if="!massenPreview.laedt && massenAktion.muster_value.trim() !== ''">
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <span x-show="massenPreview.anzahl_domains === 0" style="color:#dc2626;">⚠ Keine Treffer</span>
                            <span x-show="massenPreview.anzahl_domains > 0" style="color:#047857;font-weight:600;">
                                🎯 <span x-text="massenPreview.anzahl_domains"></span> Domain(s) ·
                                <span x-text="massenPreview.anzahl_verlinkungen"></span> Verlinkung(en)
                            </span>
                            <details x-show="massenPreview.beispiele.length > 0" style="font-size:0.72rem;color:#64748b;">
                                <summary style="cursor:pointer;">Beispiele zeigen (max 15)</summary>
                                <ul style="margin:6px 0 0;padding:0 0 0 18px;max-height:140px;overflow-y:auto;">
                                    <template x-for="b in massenPreview.beispiele" :key="b.domain">
                                        <li><span x-text="b.domain"></span> <span style="color:#94a3b8;" x-text="'· ' + b.anzahl + ' Link(s)'"></span></li>
                                    </template>
                                </ul>
                            </details>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Aktion -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;background:#fafbfc;">
                <div style="font-size:0.68rem;color:#0369a1;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Schritt 2 · Aktion (was setzen wir?)</div>

                <!-- Linkart -->
                <div class="wz-row" style="align-items:flex-start;">
                    <span class="wz-label" style="width:90px;">Linkart *</span>
                    <div class="wz-chips">
                        <template x-for="la in linkarten" :key="la">
                            <button type="button" class="wz-chip"
                                    :class="massenAktion.aktion_linkart === la ? 'is-active' : ''"
                                    @click="massenAktion.aktion_linkart = la"
                                    x-text="formatLinkart(la)"></button>
                        </template>
                        <template x-for="kl in (vorschlaege.kunden_linkarten || [])" :key="kl.id">
                            <button type="button" class="wz-chip is-kunde"
                                    :class="massenAktion.aktion_linkart === kl.linkart_key ? 'is-active' : ''"
                                    @click="massenAktion.aktion_linkart = kl.linkart_key">
                                ★ <span x-text="kl.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Empfehlung -->
                <div class="wz-row" style="margin-top:4px;">
                    <span class="wz-label" style="width:90px;">Empfehlung</span>
                    <div class="wz-chips">
                        <template x-for="e in (vorschlaege.empfehlungen || [])" :key="e">
                            <button type="button" class="wz-chip"
                                    :class="massenAktion.aktion_empfehlung === e ? 'is-active' : ''"
                                    @click="massenAktion.aktion_empfehlung = (massenAktion.aktion_empfehlung === e ? '' : e)"
                                    x-text="formatEmpfehlung(e)"></button>
                        </template>
                    </div>
                </div>

                <!-- Strategie -->
                <div class="wz-row" style="margin-top:4px;">
                    <span class="wz-label" style="width:90px;">Strategie</span>
                    <div class="wz-chips">
                        <button type="button" class="wz-chip" :class="massenAktion.aktion_strategie === 'alle_behalten' ? 'is-active' : ''" @click="massenAktion.aktion_strategie = (massenAktion.aktion_strategie === 'alle_behalten' ? '' : 'alle_behalten')">alle behalten</button>
                        <button type="button" class="wz-chip" :class="massenAktion.aktion_strategie === 'reduktion_auf_1' ? 'is-active' : ''" @click="massenAktion.aktion_strategie = (massenAktion.aktion_strategie === 'reduktion_auf_1' ? '' : 'reduktion_auf_1')">nur die beste</button>
                        <button type="button" class="wz-chip" :class="massenAktion.aktion_strategie === 'reduktion_auf_2' ? 'is-active' : ''" @click="massenAktion.aktion_strategie = (massenAktion.aktion_strategie === 'reduktion_auf_2' ? '' : 'reduktion_auf_2')">die besten 2</button>
                    </div>
                </div>

                <!-- Notiz für später -->
                <div class="wz-row" style="margin-top:8px;align-items:flex-start;">
                    <span class="wz-label" style="width:90px;">Notiz</span>
                    <input type="text" x-model="massenAktion.beschreibung"
                           placeholder="z.B. Datenschutz-Footer-Links — Standard-Behandlung"
                           style="flex:1;padding:5px 10px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.75rem;font-family:inherit;">
                </div>
            </div>
        </div>
        <!-- Footer -->
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;gap:8px;align-items:center;">
            <span style="font-size:0.7rem;color:#94a3b8;">Muster wird gespeichert und sofort angewendet · Domains landen mit Klassifikation in der Wissensbasis · überschreibt frühere manuelle Werte</span>
            <div style="display:flex;gap:8px;">
                <button @click="massenAktionOffen = false" :disabled="massenLaeuft"
                        style="background:#fff;border:1px solid #cbd5e1;color:#475569;padding:7px 14px;border-radius:6px;font-size:0.85rem;cursor:pointer;">Abbrechen</button>
                <button @click="massenAnwenden()"
                        :disabled="massenLaeuft || !massenAktion.muster_value.trim() || !massenAktion.aktion_linkart || massenPreview.anzahl_domains === 0"
                        :style="{ opacity: (massenLaeuft || !massenAktion.muster_value.trim() || !massenAktion.aktion_linkart || massenPreview.anzahl_domains === 0) ? 0.4 : 1 }"
                        style="background:#0369a1;border:none;color:#fff;padding:7px 18px;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;">
                    <span x-show="!massenLaeuft">✓ Auf <span x-text="massenPreview.anzahl_domains"></span> Domain(s) anwenden</span>
                    <span x-show="massenLaeuft">… läuft</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ───────────────────────────── SITEWIDE-CLUSTER ───────────────────────────── -->
<section class="lam-card" x-show="modus === 'liste' && customerId && cluster.length > 0" x-cloak
         style="margin-bottom:16px;border-left:4px solid #f59e0b;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;"
         @click="clusterOffen = !clusterOffen">
        <h3 style="margin:0;font-size:1rem;">
            <span x-text="clusterOffen ? '▾' : '▸'"></span>
            ⚠ Sitewide-Cluster &middot; <span x-text="cluster.length"></span> Domains mit vielen Verlinkungen
        </h3>
        <div style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:0.7rem;color:#64748b;">Schwelle:</label>
            <input type="number" min="2" max="50" x-model.number.debounce.400ms="clusterSchwelle" @change="ladeCluster()"
                   style="width:60px;padding:3px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.75rem;">
            <button class="lam-btn lam-btn-primary lam-btn-small" @click.stop="bulkAlleClusterAufloesen()"
                    :disabled="cluster.length === 0 || clusterBulkLaeuft">
                <span x-show="!clusterBulkLaeuft">Alle <span x-text="cluster.length"></span> auflösen</span>
                <span x-show="clusterBulkLaeuft">… läuft</span>
            </button>
        </div>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:0.75rem;">
        Domains mit &ge; <strong x-text="clusterSchwelle"></strong> Verlinkungen vom selben Kunden — typisch fuer
        Sidebar-/Footer-Links. Beim Aufloesen wird die beste URL behalten, der Rest soft-geloescht.
    </p>
    <div x-show="clusterOffen" x-transition style="margin-top:12px;">
        <template x-for="c in cluster" :key="c.domain">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-top:1px solid #f1f5f9;gap:10px;font-size:0.875rem;">
                <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;flex:1;min-width:0;">
                    <strong x-text="c.domain" style="word-break:break-all;"></strong>
                    <span class="muted" style="font-size:0.75rem;">
                        <strong x-text="c.anzahl"></strong> Verlinkungen
                        <span x-show="c.erreichbar_anzahl > 0" style="color:#047857;margin-left:6px;" x-text="c.erreichbar_anzahl + ' ✓'"></span>
                        <span x-show="c.tot_anzahl > 0" style="color:#b91c1c;margin-left:6px;" x-text="c.tot_anzahl + ' ✗'"></span>
                        <span x-show="c.ungeprueft_anzahl > 0" style="color:#94a3b8;margin-left:6px;" x-text="c.ungeprueft_anzahl + ' ?'"></span>
                    </span>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button class="lam-btn lam-btn-small lam-btn-secondary" @click="oeffneDetail({domain: c.domain, vorschlag_strategie: 'reduktion_auf_1'})">Details</button>
                    <button class="lam-btn lam-btn-small" @click="loeseClusterAuf(c)">Auflösen (auf 1)</button>
                </div>
            </div>
        </template>
    </div>
</section>

<!-- ───────────────────────────── KLAR ───────────────────────────── -->
<section class="lam-card" x-show="modus === 'liste' && customerId && vorschlaege.klar.length > 0" x-cloak
         style="margin-bottom:16px;border-left:4px solid #10b981;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;"
         @click="klarOffen = !klarOffen">
        <h3 style="margin:0;font-size:1rem;">
            <span x-text="klarOffen ? '▾' : '▸'"></span>
            ✓ Klar klassifiziert &middot; <span x-text="vorschlaege.klar.length"></span> Domains
        </h3>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click.stop="bulkAlleKlarStart()"
                :disabled="vorschlaege.klar.length === 0">
            Alle <span x-text="vorschlaege.klar.length"></span> klaren übernehmen
        </button>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:var(--d-fs-xs);">
        Bekannt aus der Wissensbasis (manuell oder von der KI mit ≥ 80 % Confidence eingestuft).
        Können in einem Rutsch übernommen werden &mdash; oder einzeln prüfen.
    </p>
    <div x-show="klarOffen" x-transition style="margin-top:12px;">
        <template x-for="d in vorschlaege.klar" :key="d.domain">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-top:1px solid #f1f5f9;gap:10px;font-size:0.875rem;">
                <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;flex:1;min-width:0;">
                    <strong x-text="d.domain" style="word-break:break-all;"></strong>
                    <span class="muted" style="font-size:0.75rem;" x-text="d.anzahl + (d.anzahl === 1 ? ' Verlinkung' : ' Verlinkungen')"></span>
                    <span x-show="d.quelle === 'wissensbasis'" style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:99px;font-size:0.7rem;">Wissensbasis</span>
                    <span style="color:#475569;font-size:0.8rem;">→ <span x-text="formatLinkart(d.vorschlag_linkart)"></span>, <span x-text="formatStrategie(d.vorschlag_strategie)"></span></span>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button class="lam-btn lam-btn-small lam-btn-secondary" @click="oeffneDetail(d)">Details</button>
                    <button class="lam-btn lam-btn-small" @click="annehmenSchnell(d)">Einzeln übernehmen</button>
                </div>
            </div>
        </template>
    </div>
</section>

<!-- ─────────────────────────── UNBESTIMMT ─────────────────────────── -->
<section class="lam-card" x-show="modus === 'liste' && customerId && vorschlaege.unbestimmt.length > 0" x-cloak
         style="margin-bottom:16px;border-left:4px solid #94a3b8;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <h3 style="margin:0;font-size:1rem;">
            ? Unbestimmt &middot; <span x-text="vorschlaege.unbestimmt.length"></span> Domains
        </h3>
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <span class="muted" style="font-size:0.75rem;" x-show="!kiLaeuft">Nicht in der Wissensbasis. Per KI klassifizieren oder einzeln prüfen.</span>
            <span style="font-size:0.75rem;color:#0369a1;" x-show="kiLaeuft" x-text="`KI klassifiziert: ${kiDone} / ${kiTotal}`"></span>
            <button class="lam-btn lam-btn-primary lam-btn-small" @click="kiAlleStart()"
                    :disabled="kiLaeuft || vorschlaege.unbestimmt.length === 0">
                <span x-show="!kiLaeuft">KI: alle klassifizieren (Claude Sonnet)</span>
                <span x-show="kiLaeuft">… läuft</span>
            </button>
        </div>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:0.75rem;">
        Sonnet 4.6 schlägt Linkart + Reduktionsstrategie vor und legt das Ergebnis in der Wissensbasis ab.
        Domains mit Confidence ≥ 80 % wandern danach in „Klar".
    </p>
    <div style="margin-top:12px;max-height:540px;overflow-y:auto;">
        <template x-for="d in vorschlaege.unbestimmt.slice(0, anzeigeUnbestimmt)" :key="d.domain">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-top:1px solid #f1f5f9;gap:10px;font-size:0.875rem;">
                <div style="flex:1;min-width:0;">
                    <strong x-text="d.domain" style="word-break:break-all;"></strong>
                    <span class="muted" style="font-size:0.75rem;margin-left:8px;" x-text="d.anzahl + (d.anzahl === 1 ? ' Verlinkung' : ' Verlinkungen')"></span>
                    <div class="muted" style="font-size:0.7rem;margin-top:2px;" x-show="d.linktexte.length"
                         x-text="'Linktexte: ' + d.linktexte.slice(0, 2).join(' | ')"></div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button class="lam-btn lam-btn-small lam-btn-secondary" @click="oeffneDetail(d)">Details</button>
                </div>
            </div>
        </template>
        <div x-show="vorschlaege.unbestimmt.length > anzeigeUnbestimmt" style="text-align:center;padding:10px;">
            <button class="lam-btn lam-btn-small lam-btn-secondary" @click="anzeigeUnbestimmt += 50">
                + 50 weitere anzeigen (von <span x-text="vorschlaege.unbestimmt.length - anzeigeUnbestimmt"></span>)
            </button>
        </div>
    </div>
</section>

<!-- Leer-Zustand -->
<section class="lam-card" x-show="modus === 'liste' && customerId && !laedt && vorschlaege.gesamt === 0" x-cloak>
    <p style="text-align:center;color:#64748b;padding:30px 0;">
        Keine offenen Aufräum-Vorschläge für diesen Kunden. Alles sauber!
    </p>
</section>

<!-- Linkart-Verwaltungs-Lightbox: alle kundenspezifischen Linkarten editierbar -->
<div x-show="linkartVerwaltung.offen" x-cloak @click.self="linkartVerwaltung.offen=false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1055;">
    <div style="width:100%;max-width:680px;max-height:85vh;background:#fff;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:1rem;font-weight:600;">Kundenspezifische Linkarten</h3>
            <button @click="linkartVerwaltung.offen=false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="padding:18px 20px;overflow-y:auto;flex:1;">
            <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px 0;line-height:1.4;">
                Diese Linkarten gelten nur für diesen Kunden. Aenderungen wirken sofort &mdash; bestehende
                Wissensbasis-Eintraege mit dem alten Key bleiben technisch gueltig, nutzen aber das neue Label.
                Beim <strong>Loeschen</strong> wird der Linkart-Wert bei bestehenden Verlinkungen nicht entfernt,
                erscheint aber im Dropdown nicht mehr.
            </p>
            <template x-for="(kl, idx) in (vorschlaege.kunden_linkarten || [])" :key="kl.id">
                <div style="border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;margin-bottom:8px;">
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <input type="text" x-model="kl.label"
                                       style="flex:1;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.85rem;font-weight:600;">
                                <span style="font-size:0.7rem;color:#94a3b8;font-family:monospace;" :title="'Interner Key: ' + kl.linkart_key" x-text="kl.linkart_key"></span>
                            </div>
                            <textarea x-model="kl.beschreibung" rows="2"
                                      placeholder="Beschreibung — hilft der KI bei der Erkennung"
                                      style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.78rem;font-family:inherit;resize:vertical;"></textarea>
                            <div style="display:flex;gap:8px;margin-top:6px;align-items:center;">
                                <label style="font-size:0.7rem;color:#64748b;">Default-Strategie:</label>
                                <select x-model="kl.default_strategie" style="padding:3px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:0.75rem;">
                                    <option value="alle_behalten">alle behalten</option>
                                    <option value="reduktion_auf_1">auf 1 reduzieren</option>
                                    <option value="reduktion_auf_2">auf 2 reduzieren</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                            <button @click="speichereVerwaltung(kl)" :disabled="linkartVerwaltung.laeuft"
                                    style="padding:5px 10px;border:none;background:#0369a1;color:#fff;border-radius:4px;font-size:0.75rem;cursor:pointer;">
                                Speichern
                            </button>
                            <button @click="loescheKundenLinkart(kl)" :disabled="linkartVerwaltung.laeuft"
                                    style="padding:5px 10px;border:1px solid #fecaca;background:#fff;color:#b91c1c;border-radius:4px;font-size:0.75rem;cursor:pointer;">
                                Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="!(vorschlaege.kunden_linkarten || []).length">
                <div style="padding:20px;text-align:center;color:#94a3b8;font-size:0.85rem;">
                    Noch keine kundenspezifischen Linkarten angelegt.
                </div>
            </template>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;gap:8px;">
            <button @click="linkartVerwaltung.offen=false; oeffneNeueLinkart();"
                    style="padding:6px 14px;border:1px dashed #cbd5e1;background:#fff;color:#0369a1;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                + Neue Linkart anlegen
            </button>
            <button @click="linkartVerwaltung.offen=false"
                    style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                Schließen
            </button>
        </div>
    </div>
</div>

<!-- Neue-Linkart-Lightbox: schlanke Eingabe Label + Beschreibung + Default-Strategie -->
<div x-show="neueLinkart.offen" x-cloak @click.self="neueLinkart.offen=false"
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1055;">
    <div style="width:100%;max-width:520px;background:#fff;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:1rem;font-weight:600;">Neue kundenspezifische Linkart</h3>
            <button @click="neueLinkart.offen=false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="padding:18px 20px;">
            <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px 0;line-height:1.4;">
                Gilt nur für diesen Kunden. Wird in der KI-Klassifikation als zusätzliche Option mit angeboten —
                wenn Sonnet ein passendes Muster erkennt, wählt es sie statt „sonstiges".
            </p>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:0.7rem;color:#64748b;font-weight:600;margin-bottom:3px;">Label</label>
                <input type="text" x-model="neueLinkart.label" placeholder="z.B. Cookie-Banner-Link"
                       style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:5px;font-size:0.85rem;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:0.7rem;color:#64748b;font-weight:600;margin-bottom:3px;">Default-Strategie</label>
                <select x-model="neueLinkart.default_strategie" style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:5px;font-size:0.85rem;">
                    <option value="alle_behalten">Alle behalten</option>
                    <option value="reduktion_auf_1">Auf 1 reduzieren</option>
                    <option value="reduktion_auf_2">Auf 2 reduzieren</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:0.7rem;color:#64748b;font-weight:600;margin-bottom:3px;">Beschreibung (hilft der KI bei der Erkennung)</label>
                <textarea x-model="neueLinkart.beschreibung" rows="3"
                          placeholder="z.B. Cookiebanner-Verlinkungen aus dem Steinmann-Videoplayer. URLs beginnen mit /datenschutz/."
                          style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:5px;font-size:0.85rem;font-family:inherit;resize:vertical;"></textarea>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
            <button @click="neueLinkart.offen=false"
                    style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;cursor:pointer;">
                Abbrechen
            </button>
            <button @click="speichereNeueLinkart()" :disabled="!neueLinkart.label.trim()"
                    style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;"
                    :style="{ opacity: neueLinkart.label.trim() ? 1 : 0.5 }">
                Anlegen & verwenden
            </button>
        </div>
    </div>
</div>

<!-- ───────────────────────────── DETAIL-LIGHTBOX ───────────────────────────── -->
<div x-show="drawer.offen" x-cloak
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1040;"
     @click.self="drawer.offen = false"
     @keydown.escape.window="drawer.offen = false">
    <div style="width:min(920px,calc(100vw - 32px));max-height:90vh;background:#fff;border-radius:8px;display:flex;flex-direction:column;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;">
        <!-- Header -->
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;" x-text="drawer.domain"></h3>
                <div class="muted" style="font-size:0.75rem;margin-top:2px;">
                    <span x-text="drawer.verlinkungen.length + ' Verlinkungen offen'"></span>
                    <span x-show="drawer.linkart" style="margin-left:8px;">&middot; Wissensbasis: <strong x-text="formatLinkart(drawer.linkart)"></strong></span>
                </div>
            </div>
            <button @click="drawer.offen = false" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <!-- Klassifikations-Steuerung -->
        <div style="padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:0.875rem;">
            <div>
                <label style="display:block;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Linkart</label>
                <select x-model="drawer.linkart" @change="drawerAuswahlGeaendert = true" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                    <option value="">— wählen —</option>
                    <template x-for="la in linkarten" :key="la"><option :value="la" x-text="formatLinkart(la)"></option></template>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">URL-Bevorzugung</label>
                <select x-model="drawer.urlStrategie" @change="drawerAuswahlGeaendert = true;reBerechneAuswahl()" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;"
                        title="Welche URL hat Vorrang, wenn reduziert wird? Wird in der Wissensbasis gespeichert und automatisch beim nächsten Import dieser Domain angewendet.">
                    <option value="auto">Auto (Score)</option>
                    <option value="tiefste">Deeplink bevorzugt</option>
                    <option value="kuerzeste">Kürzeste URL bevorzugt</option>
                    <option value="deeplink_aber_score">Score, bei Gleichstand Deeplink</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Reduktion</label>
                <select x-model="drawer.strategie" @change="drawerAuswahlGeaendert = true;reBerechneAuswahl()" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                    <option value="alle_behalten">Alle behalten</option>
                    <option value="reduktion_auf_1">Auf 1 reduzieren</option>
                    <option value="reduktion_auf_2">Auf 2 reduzieren</option>
                </select>
            </div>
        </div>
        <!-- Verlinkungs-Tabelle mit Scores: fixe Spaltenbreiten, URL bricht um, kein horizontaler Scroll -->
        <div style="flex:1;overflow-y:auto;overflow-x:hidden;padding:8px 20px;">
            <table style="width:100%;font-size:0.8rem;border-collapse:collapse;table-layout:fixed;">
                <thead>
                    <tr style="text-align:left;color:#64748b;border-bottom:1px solid #e2e8f0;font-weight:500;">
                        <th style="padding:6px 4px;width:50px;text-align:center;">Behalten</th>
                        <th style="padding:6px 4px;word-break:break-all;">URL</th>
                        <th style="padding:6px 4px;width:50px;text-align:right;">SI</th>
                        <th style="padding:6px 4px;width:30px;text-align:center;" title="Erreichbar">HTTP</th>
                        <th style="padding:6px 4px;width:40px;text-align:right;" title="URL-Tiefe (Pfad-Segmente)">Tiefe</th>
                        <th style="padding:6px 4px;width:60px;text-align:right;" title="Berechneter Score 0..1">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="v in drawer.verlinkungen" :key="v.id">
                        <tr style="border-bottom:1px solid #f1f5f9;"
                            :style="{ background: drawer.behaltenIds.has(v.id) ? '#ecfdf5' : '#fff' }">
                            <td style="padding:6px 4px;text-align:center;">
                                <input type="checkbox"
                                       :checked="drawer.behaltenIds.has(v.id)"
                                       @change="toggleBehalten(v.id)">
                            </td>
                            <td style="padding:6px 4px;word-break:break-all;">
                                <a :href="v.verlinkende_url" target="_blank" rel="noopener" x-text="kurzUrl(v.verlinkende_url)" style="color:#0369a1;text-decoration:none;"></a>
                                <span x-show="v.linktext" class="muted" style="font-size:0.7rem;display:block;" x-text="'Linktext: ' + v.linktext"></span>
                            </td>
                            <td style="padding:6px 4px;text-align:right;font-variant-numeric:tabular-nums;"
                                x-text="v.sistrix_index != null ? Number(v.sistrix_index).toFixed(2) : '—'"></td>
                            <td style="padding:6px 4px;text-align:center;"
                                x-text="v.letzter_http_erreichbar == 1 ? '✓' : (v.letzter_http_status ? '✗' : '?')"
                                :style="{ color: v.letzter_http_erreichbar == 1 ? '#047857' : (v.letzter_http_status ? '#b91c1c' : '#94a3b8') }"></td>
                            <td style="padding:6px 4px;text-align:right;font-variant-numeric:tabular-nums;" x-text="v.url_tiefe"></td>
                            <td style="padding:6px 4px;text-align:right;font-variant-numeric:tabular-nums;font-weight:600;"
                                :style="{ color: v.score >= 0.7 ? '#047857' : (v.score >= 0.4 ? '#0369a1' : '#64748b') }"
                                x-text="Number(v.score).toFixed(3)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- Footer -->
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <div style="font-size:0.8rem;color:#64748b;">
                <strong x-text="drawer.behaltenIds.size"></strong> behalten,
                <strong x-text="drawer.verlinkungen.length - drawer.behaltenIds.size"></strong> löschen
            </div>
            <div style="display:flex;gap:8px;">
                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="drawer.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary lam-btn-small" @click="drawerAnnehmen()" :disabled="!drawer.linkart">
                    Übernehmen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ───────────────────────────── KI-FORTSCHRITT ───────────────────────────── -->
<div x-show="kiLaeuft || kiFertig" x-cloak
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1050;">
    <div style="width:100%;max-width:440px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;">
            <h3 style="margin:0;font-size:1rem;font-weight:600;">KI klassifiziert (Claude Sonnet 4.6)</h3>
        </div>
        <div style="padding:18px 20px;">
            <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px 0;"
               x-text="kiFertig ? 'Fertig.' : 'Läuft …'"></p>
            <div style="display:flex;justify-content:space-between;font-size:0.875rem;">
                <span style="font-weight:600;font-variant-numeric:tabular-nums;">
                    <span x-text="kiDone"></span> / <span x-text="kiTotal"></span>
                </span>
                <span style="color:#64748b;font-size:0.75rem;" x-text="Math.round((kiDone / Math.max(kiTotal,1)) * 100) + ' %'"></span>
            </div>
            <div style="margin-top:6px;height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
                <div style="height:100%;background:#0369a1;transition:width 0.4s ease-out;border-radius:99px;"
                     :style="{ width: ((kiDone / Math.max(kiTotal,1)) * 100) + '%' }"></div>
            </div>
            <div style="margin-top:14px;font-size:0.875rem;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Klassifiziert</span>
                    <span style="color:#047857;font-weight:600;font-variant-numeric:tabular-nums;" x-text="kiOk"></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;" x-show="kiFehler.length">
                    <span style="color:#64748b;">Fehler</span>
                    <span style="color:#b91c1c;font-weight:600;font-variant-numeric:tabular-nums;" x-text="kiFehler.length"></span>
                </div>
            </div>
            <details x-show="kiFehler.length" style="margin-top:8px;font-size:0.75rem;">
                <summary style="cursor:pointer;color:#64748b;">Fehlerdetails</summary>
                <ul style="margin:6px 0 0;padding:8px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:6px;list-style:none;max-height:120px;overflow-y:auto;">
                    <template x-for="(f, i) in kiFehler" :key="i"><li x-text="f"></li></template>
                </ul>
            </details>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
            <button x-show="kiLaeuft && !kiFertig" @click="kiAbbrechen = true" :disabled="kiAbbrechen"
                    style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;"
                    x-text="kiAbbrechen ? 'Wird abgebrochen …' : 'Abbrechen'"></button>
            <button x-show="kiFertig" @click="kiSchliessen()"
                    style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;">
                Schließen
            </button>
        </div>
    </div>
</div>

<!-- ───────────────────────────── BULK-FORTSCHRITT ───────────────────────────── -->
<div x-show="bulk.offen" x-cloak
     class="thx-lightbox" style="background:rgba(15,23,42,0.45);z-index:1050;">
    <div style="width:100%;max-width:440px;background:#fff;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;">
            <h3 style="margin:0;font-size:1rem;font-weight:600;">Klare Vorschläge übernehmen</h3>
        </div>
        <div style="padding:18px 20px;">
            <p style="font-size:0.75rem;color:#64748b;margin:0 0 12px 0;"
               x-text="bulk.fertig ? 'Fertig.' : (bulk.abbrechen ? 'Wird abgebrochen …' : 'Läuft …')"></p>
            <div style="display:flex;justify-content:space-between;font-size:0.875rem;">
                <span style="font-weight:600;font-variant-numeric:tabular-nums;">
                    <span x-text="bulk.done"></span> / <span x-text="bulk.total"></span>
                </span>
                <span style="color:#64748b;font-size:0.75rem;" x-text="Math.round((bulk.done / Math.max(bulk.total,1)) * 100) + ' %'"></span>
            </div>
            <div style="margin-top:6px;height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
                <div style="height:100%;background:#0369a1;transition:width 0.4s ease-out;border-radius:99px;"
                     :style="{ width: ((bulk.done / Math.max(bulk.total,1)) * 100) + '%' }"></div>
            </div>
            <div style="margin-top:14px;font-size:0.875rem;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Domains übernommen</span>
                    <span style="color:#047857;font-weight:600;font-variant-numeric:tabular-nums;" x-text="bulk.erfolge"></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Verlinkungen behalten</span>
                    <span style="color:#0f172a;font-weight:600;font-variant-numeric:tabular-nums;" x-text="bulk.behalten"></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#64748b;">Verlinkungen gelöscht</span>
                    <span style="color:#b45309;font-weight:600;font-variant-numeric:tabular-nums;" x-text="bulk.geloescht"></span>
                </div>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
            <button x-show="!bulk.fertig" @click="bulk.abbrechen = true" :disabled="bulk.abbrechen"
                    style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.875rem;"
                    x-text="bulk.abbrechen ? 'Wird abgebrochen …' : 'Abbrechen'"></button>
            <button x-show="bulk.fertig" @click="bulkSchliessen()"
                    style="padding:6px 14px;border:none;background:#0369a1;color:#fff;border-radius:6px;font-size:0.875rem;font-weight:600;">
                Schließen
            </button>
        </div>
    </div>
</div>

</div>

<script>
function lamAufraeumen() {
    return {
        kunden: [],
        customerId: '',
        laedt: false,
        vorschlaege: { klar: [], unsicher: [], unbestimmt: [], gesamt: 0 },

        klarOffen: false,
        anzeigeUnbestimmt: 50,
        // Sitewide-Cluster
        cluster: [], clusterOffen: true, clusterSchwelle: 5, clusterBulkLaeuft: false,

        // Kunden-Kontext fuer KI (kundenweite "globale Regeln")
        kontextLightboxOffen: false, kontextText: '', kontextDirty: false,

        // Per-Domain-Notiz (neu) — Inhalt + Modal-State
        domainNotizText: '', domainNotizDirty: false, domainNotizSpeichert: false,
        kiBriefenOffen: false,

        // Linkziel-Inline-Edit + Crawl-Ermittlung pro Verlinkung
        zielEditId: null, zielEditWert: '', zielErmittleId: null,

        // Linktext-Inline-Edit pro Verlinkung
        linktextEditId: null, linktextEditWert: '',

        // Muster (Patterns)
        musterListe: [],
        musterVorschlaegeOffen: false, musterVorschlaege: [], musterVorschlaegeUrsprung: { domain: '', notiz: '' },
        musterListeOffen: false, musterAnwendenLaeuft: {},

        // Massen-Aktion (manueller Muster-Editor mit Live-Preview)
        massenAktionOffen: false,
        massenAktion: {
            muster_typ: 'ziel_url_pattern',
            muster_value: '',
            aktion_linkart: '',
            aktion_strategie: '',
            aktion_empfehlung: '',
            beschreibung: '',
        },
        massenPreview: { anzahl_domains: 0, anzahl_verlinkungen: 0, beispiele: [], laedt: false },
        massenPreviewTimeout: null,
        massenLaeuft: false,

        // ─── Fokus-Wizard-State ────────────────────────────────────
        modus: localStorage.getItem('thx_lam_aufraeum_modus') || 'liste',
        fokusListe: [], fokusIndex: 0, fokusErledigt: 0,
        fokusSort: localStorage.getItem('thx_lam_aufraeum_sort') || 'klar_zuerst',
        fokusKategorie: localStorage.getItem('thx_lam_aufraeum_kat') || 'alle',
        // Neue Quick-Filter (Multi-Chip-Auswahl, Mengen als Sets im localStorage als JSON-Array)
        fokusFilter: (() => {
            const def = { anzahl: [], linktext: [], si: [], sonstiges: [] };
            try {
                const raw = localStorage.getItem('thx_lam_aufraeum_filter');
                if (!raw) return def;
                const p = JSON.parse(raw);
                return {
                    anzahl:    Array.isArray(p.anzahl)    ? p.anzahl    : [],
                    linktext:  Array.isArray(p.linktext)  ? p.linktext  : [],
                    si:        Array.isArray(p.si)        ? p.si        : [],
                    sonstiges: Array.isArray(p.sonstiges) ? p.sonstiges : [],
                };
            } catch (e) { return def; }
        })(),
        // Nachbearbeitungs-Modus: bereits aufgeraeumte Domains mit einbeziehen
        nachbearbeitung: localStorage.getItem('thx_lam_aufraeum_nachb') === '1',
        // Tagesziel + Sitzungs-Counter
        fokusTagesziel: parseInt(localStorage.getItem('thx_lam_aufraeum_tagesziel') || '0', 10) || 0,
        sekProDomain: 12, // grobe Schaetzung fuer Zeit-Anzeige
        wizard: { linkart: '', strategie: 'alle_behalten', urlStrategie: 'auto', geaendert: false, mergeLinktext: true, empfehlung: '', zielUrl: '' },

        // Neue Linkart anlegen (Mini-Lightbox)
        neueLinkart: { offen: false, label: '', beschreibung: '', default_strategie: 'reduktion_auf_1', saved: false },
        // Linkart-Verwaltung (Liste editieren/loeschen)
        linkartVerwaltung: { offen: false, laeuft: false },
        wizardDetail: { verlinkungen: [], behalten_ids: [] },
        wizardLaedt: false,
        undoStack: [],          // [{domain, customerId, when}]
        undoToast: { text: '', timeoutId: null },

        // Drawer
        drawer: { offen: false, domain: '', verlinkungen: [], behaltenIds: new Set(),
                  linkart: '', strategie: 'alle_behalten', urlStrategie: 'auto' },
        drawerAuswahlGeaendert: false,

        // KI
        kiLaeuft: false, kiFertig: false, kiAbbrechen: false,
        kiDone: 0, kiTotal: 0, kiOk: 0, kiFehler: [],

        // Bulk
        bulk: { offen: false, done: 0, total: 0, erfolge: 0, behalten: 0, geloescht: 0, abbrechen: false, fertig: false },

        linkarten: [
            'spam','branchenverzeichnis','fachverzeichnis','online_magazin','portal','blog',
            'presseportal','forum','referenzprojekt','partner','sponsoring','stellenboerse',
            'veranstaltung','kommentarlink','podcast','weiterleitung','social_media','sonstiges'
        ],

        async init() {
            try {
                const r = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success && j.data.length) {
                    this.kunden = j.data;
                    const url = new URL(location.href);
                    this.customerId = url.searchParams.get('customer_id') || localStorage.getItem('thx_lam_aufraeum_kunde') || this.kunden[0].id;
                }
            } catch (e) { /* noop */ }
            if (this.customerId) await Promise.all([this.ladeVorschlaege(), this.ladeKontext(), this.ladeMuster()]);
        },
        async laden() {
            // Wrapper fuer @change auf Kunden-Select. Speichert + laedt.
            if (!this.customerId) return;
            try { localStorage.setItem('thx_lam_aufraeum_kunde', this.customerId); } catch (e) {}
            await Promise.all([this.ladeVorschlaege(), this.ladeKontext(), this.ladeMuster()]);
        },
        async ladeKontext() {
            if (!this.customerId) return;
            try {
                const r = await fetch('/api/v1/lam/kunden-kontext?customer_id=' + encodeURIComponent(this.customerId), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) { this.kontextText = j.data.lam_kontext || ''; this.kontextDirty = false; }
            } catch (e) { /* ignorieren */ }
        },
        async kontextSpeichern() {
            if (!this.customerId) return;
            try {
                const r = await fetch('/api/v1/lam/kunden-kontext', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.customerId, lam_kontext: this.kontextText, auto_refresh: true })
                });
                const j = await r.json();
                if (j.success) {
                    this.kontextDirty = false;
                    const refresh = j.data.refresh || {};
                    if (refresh.domains && refresh.domains.length) {
                        App.showNotification(
                            `Kontext gespeichert. ${refresh.ok} Domain(s) per KI re-klassifiziert: ${refresh.domains.join(', ')}`,
                            'success'
                        );
                        await this.ladeVorschlaege();
                    } else {
                        App.showNotification('Kunden-Kontext gespeichert', 'success');
                    }
                } else App.showNotification(j.message || 'Fehler beim Speichern', 'error');
            } catch (e) { App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error'); }
        },

        // ─── Globale Regeln (kundenweit) ──────────────────────────────────
        oeffneGlobaleRegeln() { this.kontextLightboxOffen = true; },

        // ─── Massen-Aktion (manueller Muster-Editor) ──────────────────────
        oeffneMassenAktion() {
            this.massenAktion = {
                muster_typ: 'ziel_url_pattern',
                muster_value: '',
                aktion_linkart: '',
                aktion_strategie: '',
                aktion_empfehlung: '',
                beschreibung: '',
            };
            this.massenPreview = { anzahl_domains: 0, anzahl_verlinkungen: 0, beispiele: [], laedt: false };
            this.massenAktionOffen = true;
        },
        massenPreviewDebounced() {
            if (this.massenPreviewTimeout) clearTimeout(this.massenPreviewTimeout);
            this.massenPreviewTimeout = setTimeout(() => this.massenPreviewLaden(), 350);
        },
        async massenPreviewLaden() {
            const wert = (this.massenAktion.muster_value || '').trim();
            if (!wert || !this.customerId) {
                this.massenPreview = { anzahl_domains: 0, anzahl_verlinkungen: 0, beispiele: [], laedt: false };
                return;
            }
            this.massenPreview.laedt = true;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-muster-preview', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        muster_typ: this.massenAktion.muster_typ,
                        muster_value: wert,
                    })
                });
                const j = await r.json();
                if (j.success) {
                    this.massenPreview = { ...j.data, laedt: false };
                } else {
                    this.massenPreview = { anzahl_domains: 0, anzahl_verlinkungen: 0, beispiele: [], laedt: false };
                }
            } catch (e) {
                this.massenPreview = { anzahl_domains: 0, anzahl_verlinkungen: 0, beispiele: [], laedt: false };
            }
        },
        async massenAnwenden() {
            const m = this.massenAktion;
            if (!m.muster_value.trim() || !m.aktion_linkart) {
                App.showNotification('Pattern und Linkart sind Pflicht', 'error');
                return;
            }
            if (this.massenPreview.anzahl_domains === 0) {
                App.showNotification('Keine Domains matchen das Pattern', 'error');
                return;
            }
            if (!confirm(`Wirklich auf ${this.massenPreview.anzahl_domains} Domain(s) anwenden? Bestehende manuelle Klassifikationen werden überschrieben.`)) return;

            this.massenLaeuft = true;
            try {
                // 1. Muster speichern
                const sr = await fetch('/api/v1/lam/aufraeum-muster', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        muster_typ: m.muster_typ,
                        muster_value: m.muster_value.trim(),
                        aktion_linkart: m.aktion_linkart,
                        aktion_strategie: m.aktion_strategie || null,
                        aktion_empfehlung: m.aktion_empfehlung || null,
                        beschreibung: m.beschreibung || 'Massen-Aktion',
                        herkunft: 'manuell',
                    })
                });
                const sj = await sr.json();
                if (!sj.success) {
                    App.showNotification('Muster konnte nicht gespeichert werden: ' + (sj.message || ''), 'error');
                    this.massenLaeuft = false; return;
                }
                // 2. Anwenden
                const ar = await fetch('/api/v1/lam/aufraeum-muster-anwenden', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ muster_id: sj.data.id, customer_id: this.customerId })
                });
                const aj = await ar.json();
                if (!aj.success) {
                    App.showNotification('Anwenden fehlgeschlagen: ' + (aj.message || ''), 'error');
                    this.massenLaeuft = false; return;
                }
                this.massenAktionOffen = false;
                App.showNotification(`Muster angewendet auf ${aj.data.angewendet || 0} Domain(s). Wissensbasis ist aktualisiert.`, 'success');
                const aktuelleDomain = this.aktuelleDomain ? this.aktuelleDomain.domain : null;
                await Promise.all([this.ladeMuster(), this.ladeVorschlaege()]);
                if (this.modus === 'fokus') {
                    this.sortiereFokus();
                    if (aktuelleDomain) {
                        const idx = this.fokusListe.findIndex(x => x.domain === aktuelleDomain);
                        if (idx >= 0) { this.fokusIndex = idx; await this.wizardInitFuerDomain(); }
                    }
                }
            } catch (e) {
                App.showNotification('Fehler: ' + e.message, 'error');
            } finally {
                this.massenLaeuft = false;
            }
        },

        // ─── Linktext-Editor öffnen + speichern ──────────────────────────
        oeffneLinktextEditor(verlinkung) {
            this.linktextEditId = verlinkung.id;
            this.linktextEditWert = verlinkung.linktext || '';
            this.$nextTick(() => {
                const el = document.getElementById('linktext-edit-' + verlinkung.id);
                if (el) { el.focus(); el.select(); }
            });
        },
        async speichereLinktext(verlinkung) {
            const neu = (this.linktextEditWert || '').trim();
            if (neu === (verlinkung.linktext || '')) { this.linktextEditId = null; return; }
            try {
                const r = await fetch('/api/v1/lam/verlinkung-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: verlinkung.id, feld: 'linktext', wert: neu === '' ? null : neu })
                });
                const j = await r.json();
                if (j.success) {
                    verlinkung.linktext = neu === '' ? null : neu;
                    this.linktextEditId = null;
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) {
                App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
            }
        },

        // ─── Linkziel-Editor öffnen ──────────────────────────────────────
        oeffneZielEditor(verlinkung) {
            this.zielEditId = verlinkung.id;
            this.zielEditWert = verlinkung.ziel_url || '';
            this.$nextTick(() => {
                const el = document.getElementById('ziel-edit-' + verlinkung.id);
                if (el) el.focus();
            });
        },

        // ─── Linkziel per Crawl ermitteln ────────────────────────────────
        // editor=true: nach Erfolg den Editor öffnen mit dem Vorschlag (User kann anpassen).
        // editor=false: zeigt den Vorschlag direkt im Editor (öffnet ihn implizit).
        async ermittleZielUrl(verlinkung, editor = false) {
            if (this.zielErmittleId) return; // schon einer am Laufen
            this.zielErmittleId = verlinkung.id;
            try {
                const r = await fetch('/api/v1/lam/verlinkung-ziel-ermitteln', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: verlinkung.id, customer_id: this.customerId })
                });
                const j = await r.json();
                if (!j.success) {
                    App.showNotification('Ermittlung fehlgeschlagen: ' + (j.message || 'unbekannt'), 'error');
                    return;
                }
                const d = j.data;
                if (!d.erfolg) {
                    App.showNotification('Linkziel nicht gefunden: ' + (d.fehler || ''), 'error');
                    return;
                }
                // Vorschlag in den Editor übernehmen — User entscheidet, ob speichern.
                this.zielEditId = verlinkung.id;
                this.zielEditWert = d.vorschlag;
                const meldung = d.anzahl > 1
                    ? `Vorschlag: ${d.vorschlag} (${d.anzahl} Treffer auf ${d.kunden_host}, ersten genommen — bitte prüfen)`
                    : `Vorschlag: ${d.vorschlag}`;
                App.showNotification(meldung, 'success');
                this.$nextTick(() => {
                    const el = document.getElementById('ziel-edit-' + verlinkung.id);
                    if (el) { el.focus(); el.select(); }
                });
            } catch (e) {
                App.showNotification('Ermittlung fehlgeschlagen: ' + e.message, 'error');
            } finally {
                this.zielErmittleId = null;
            }
        },

        // ─── Linkziel inline speichern ────────────────────────────────────
        async speichereZielUrl(verlinkung) {
            const neu = (this.zielEditWert || '').trim();
            // Nichts geaendert? Schliessen.
            if (neu === (verlinkung.ziel_url || '')) { this.zielEditId = null; return; }
            try {
                const r = await fetch('/api/v1/lam/verlinkung-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: verlinkung.id, feld: 'ziel_url', wert: neu === '' ? null : neu })
                });
                const j = await r.json();
                if (j.success) {
                    verlinkung.ziel_url = neu === '' ? null : neu;
                    this.zielEditId = null;
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) {
                App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
            }
        },

        // ─── Per-Domain-Notiz ─────────────────────────────────────────────
        async speichereDomainNotiz(sucheMuster) {
            const d = this.aktuelleDomain;
            if (!d || !this.customerId) return;
            this.domainNotizSpeichert = true;
            try {
                // 1. Notiz persistieren
                const sr = await fetch('/api/v1/lam/aufraeum-domain-notiz', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.customerId, domain: d.domain, notiz: this.domainNotizText })
                });
                const sj = await sr.json();
                if (!sj.success) {
                    App.showNotification(sj.message || 'Notiz konnte nicht gespeichert werden', 'error');
                    this.domainNotizSpeichert = false; return;
                }
                this.domainNotizDirty = false;
                // Auch in der aktuellen Vorschlags-Liste aktualisieren, damit nach Skip+Back die Notiz da ist
                d.notiz = this.domainNotizText;

                // 1b. AKTUELLE DOMAIN mit force_refresh sofort re-klassifizieren,
                //     damit die Notiz unmittelbar auf den gerade bearbeiteten Eintrag wirkt
                //     (sonst wirkt sie nur auf Folge-Klassifikationen + per Muster).
                if (this.domainNotizText.trim()) {
                    try {
                        const rk = await fetch('/api/v1/lam/aufraeum-klassifiziere-ki', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                domains: [{ domain: d.domain, anzahl: d.anzahl, beispiel_urls: d.beispiel_urls, linktexte: d.linktexte }],
                                customer_id: this.customerId,
                                force_refresh: true
                            })
                        });
                        const rj = await rk.json();
                        if (rj.success && (rj.data.ok || 0) > 0) {
                            await this.ladeVorschlaege();
                            if (this.modus === 'fokus') this.sortiereFokus();
                            // Nach reload sicherstellen, dass wir noch auf derselben Domain stehen
                            const idx = this.fokusListe.findIndex(x => x.domain === d.domain);
                            if (idx >= 0) {
                                this.fokusIndex = idx;
                                await this.wizardInitFuerDomain();
                            }
                        }
                    } catch (e) { /* still success: notiz wurde gespeichert */ }
                }

                if (!sucheMuster || !this.domainNotizText.trim()) {
                    App.showNotification('Notiz gespeichert + aktuelle Domain neu klassifiziert', 'success');
                    this.domainNotizSpeichert = false; return;
                }

                // 2. KI-Analyse: Muster extrahieren
                const ar = await fetch('/api/v1/lam/aufraeum-muster-analysieren', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: this.customerId, domain: d.domain, notiz: this.domainNotizText })
                });
                const aj = await ar.json();
                if (!aj.success) {
                    App.showNotification('Notiz gespeichert, Muster-Analyse fehlgeschlagen: ' + (aj.message || ''), 'error');
                    this.domainNotizSpeichert = false; return;
                }
                const vorschlaege = aj.data.vorschlaege || [];
                if (vorschlaege.length === 0) {
                    App.showNotification('Notiz gespeichert. KI sieht kein generalisierbares Muster.', 'success');
                    this.kiBriefenOffen = false;
                } else {
                    this.musterVorschlaege = vorschlaege.map(v => ({ ...v, auswahl: 'verwerfen' })); // verwerfen | nur_speichern | speichern_und_anwenden
                    this.musterVorschlaegeUrsprung = { domain: d.domain, notiz: this.domainNotizText };
                    this.kiBriefenOffen = false; // KI-Briefen-Modal schliessen, damit Muster-Vorschlaege im Vordergrund sind
                    this.musterVorschlaegeOffen = true;
                }
            } catch (e) {
                App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
            } finally {
                this.domainNotizSpeichert = false;
            }
        },

        // ─── Muster ───────────────────────────────────────────────────────
        async ladeMuster() {
            if (!this.customerId) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-muster?customer_id=' + encodeURIComponent(this.customerId) + '&nur_bestaetigt=1',
                                      { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.musterListe = j.data.muster || [];
            } catch (e) { /* ignore */ }
        },
        oeffneMusterListe() { this.musterListeOffen = true; },
        async loescheMuster(musterId) {
            if (!confirm('Dieses Muster wirklich löschen? Bereits klassifizierte Domains bleiben unverändert.')) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-muster?id=' + musterId + '&customer_id=' + this.customerId,
                                      { method: 'DELETE', credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    await this.ladeMuster();
                    App.showNotification('Muster gelöscht', 'success');
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification('Löschen fehlgeschlagen: ' + e.message, 'error'); }
        },
        formatMusterTyp(typ) {
            return ({
                'domain_suffix':    'Quell-Domain endet auf',
                'domain_pattern':   'Quell-Domain enthält',
                'linktext_pattern': 'Linktext enthält',
                'ziel_url_pattern': 'Ziel-URL enthält',
                'keyword':          'Quell-URL enthält',
                'url_pattern':      'URL-Muster',
            })[typ] || typ;
        },
        async bestaetigeMusterVorschlag(idx) {
            const v = this.musterVorschlaege[idx];
            if (!v || v.auswahl === 'verwerfen') return null;
            // 1. Muster speichern
            const sr = await fetch('/api/v1/lam/aufraeum-muster', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_id: this.customerId,
                    muster_typ: v.muster_typ, muster_value: v.muster_value,
                    aktion_linkart: v.aktion_linkart, aktion_strategie: v.aktion_strategie,
                    aktion_empfehlung: v.aktion_empfehlung, beschreibung: v.beschreibung,
                    herkunft: 'ki_extrahiert',
                    ursprungs_domain: this.musterVorschlaegeUrsprung.domain,
                    ursprungs_notiz: this.musterVorschlaegeUrsprung.notiz,
                })
            });
            const sj = await sr.json();
            if (!sj.success) {
                App.showNotification('Muster konnte nicht gespeichert werden: ' + (sj.message || ''), 'error');
                return null;
            }
            const musterId = sj.data.id;
            // 2. Optional: anwenden
            if (v.auswahl === 'speichern_und_anwenden') {
                const ar = await fetch('/api/v1/lam/aufraeum-muster-anwenden', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ muster_id: musterId, customer_id: this.customerId })
                });
                const aj = await ar.json();
                if (aj.success) return { musterId, angewendet: aj.data.angewendet || 0 };
                return { musterId, angewendet: 0 };
            }
            return { musterId, angewendet: 0 };
        },
        async musterVorschlaegeUebernehmen() {
            const aktuelleDomain = this.aktuelleDomain ? this.aktuelleDomain.domain : null;
            const ergebnisse = [];
            for (let i = 0; i < this.musterVorschlaege.length; i++) {
                const r = await this.bestaetigeMusterVorschlag(i);
                if (r) ergebnisse.push(r);
            }
            const muster = ergebnisse.length;
            const angewendet = ergebnisse.reduce((s, e) => s + e.angewendet, 0);
            this.musterVorschlaegeOffen = false;
            this.musterVorschlaege = [];
            if (muster > 0) {
                App.showNotification(`${muster} Muster gespeichert, ${angewendet} Domain(s) re-klassifiziert.`, 'success');
                await Promise.all([this.ladeMuster(), this.ladeVorschlaege()]);
                if (this.modus === 'fokus') {
                    this.sortiereFokus();
                    // Nach reload auf der ursprünglichen Domain bleiben (sortiereFokus setzt sonst Index=0)
                    if (aktuelleDomain) {
                        const idx = this.fokusListe.findIndex(x => x.domain === aktuelleDomain);
                        if (idx >= 0) {
                            this.fokusIndex = idx;
                            await this.wizardInitFuerDomain();
                        }
                    }
                }
            } else {
                App.showNotification('Keine Muster übernommen.', 'success');
            }
        },

        // Manuelles Re-Klassifizieren der aktuellen Domain im Wizard:
        // Nutzt den aktuellen Kontext + force_refresh, kostet Tokens fuer EINE Domain.
        async wizardReklassifiziere() {
            const d = this.aktuelleDomain;
            if (!d) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-klassifiziere-ki', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        domains: [{ domain: d.domain, anzahl: d.anzahl, beispiel_urls: d.beispiel_urls, linktexte: d.linktexte }],
                        customer_id: this.customerId,
                        force_refresh: true
                    })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification(`${d.domain} per KI neu klassifiziert (${j.data.ok || 0} ok, ${j.data.fehler || 0} Fehler)`, 'success');
                    await this.ladeVorschlaege();
                    if (this.modus === 'fokus') this.sortiereFokus();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification('Re-Klassifikation fehlgeschlagen: ' + e.message, 'error'); }
        },
        async ladeVorschlaege() {
            if (!this.customerId) return;
            this.laedt = true;
            try {
                const auchBestaetigt = this.nachbearbeitung ? '&auch_bestaetigt=1' : '';
                const [vorschlaege, cluster] = await Promise.all([
                    fetch('/api/v1/lam/aufraeum-vorschlaege?customer_id=' + encodeURIComponent(this.customerId) + auchBestaetigt, { credentials: 'same-origin' }).then(r => r.json()),
                    fetch('/api/v1/lam/sitewide-cluster?customer_id=' + encodeURIComponent(this.customerId) + '&schwelle=' + this.clusterSchwelle, { credentials: 'same-origin' }).then(r => r.json()),
                ]);
                if (vorschlaege.success) this.vorschlaege = vorschlaege.data;
                if (cluster.success)     this.cluster     = cluster.data || [];
                this.anzeigeUnbestimmt = 50;
                if (this.modus === 'fokus') this.sortiereFokus();
            } catch (e) { App.showNotification('Konnte Vorschläge nicht laden: ' + e.message, 'error'); }
            finally { this.laedt = false; }
        },
        async ladeCluster() {
            if (!this.customerId) return;
            try {
                const r = await fetch('/api/v1/lam/sitewide-cluster?customer_id=' + encodeURIComponent(this.customerId) + '&schwelle=' + this.clusterSchwelle, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.cluster = j.data || [];
            } catch (e) { /* ignorieren */ }
        },
        async loeseClusterAuf(c) {
            if (!confirm(`Cluster „${c.domain}" wirklich auflösen? Es werden ${c.anzahl - 1} von ${c.anzahl} Verlinkungen soft-gelöscht (die beste bleibt).`)) return;
            try {
                // Domain-Detail mit Strategie reduktion_auf_1 laden, dann uebernehmen
                const p = new URLSearchParams({ customer_id: this.customerId, domain: c.domain, strategie: 'reduktion_auf_1' });
                const dr = await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' });
                const dj = await dr.json();
                if (!dj.success) throw new Error(dj.message || 'Detail-Fehler');
                const ar = await fetch('/api/v1/lam/aufraeum-annehmen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        domain: c.domain,
                        linkart: dj.data.linkart || 'sonstiges',
                        strategie: 'reduktion_auf_1',
                        url_strategie: dj.data.url_strategie || 'auto',
                        behalten_ids: dj.data.behalten_ids,
                        manuell_geaendert: false,
                    })
                });
                const aj = await ar.json();
                if (!aj.success) throw new Error(aj.message || 'Annehmen-Fehler');
                App.showNotification(`${c.domain}: ${aj.data.behalten} behalten, ${aj.data.geloescht} gelöscht`, 'success');
                // Cluster und Vorschlaege neu laden
                await this.ladeVorschlaege();
            } catch (e) { App.showNotification('Auflösen fehlgeschlagen: ' + e.message, 'error'); }
        },
        async bulkAlleClusterAufloesen() {
            if (!this.cluster.length) return;
            if (!confirm(`Alle ${this.cluster.length} Cluster auflösen? Pro Domain wird die beste URL behalten, der Rest gelöscht. Das kann nicht in einem Klick rückgängig gemacht werden.`)) return;
            this.clusterBulkLaeuft = true;
            const liste = this.cluster.slice();
            let ok = 0, gel = 0;
            try {
                for (const c of liste) {
                    try {
                        const p = new URLSearchParams({ customer_id: this.customerId, domain: c.domain, strategie: 'reduktion_auf_1' });
                        const dj = await (await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' })).json();
                        if (!dj.success) continue;
                        const aj = await (await fetch('/api/v1/lam/aufraeum-annehmen', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                customer_id: this.customerId, domain: c.domain,
                                linkart: dj.data.linkart || 'sonstiges',
                                strategie: 'reduktion_auf_1',
                                url_strategie: dj.data.url_strategie || 'auto',
                                behalten_ids: dj.data.behalten_ids,
                                manuell_geaendert: false,
                            })
                        })).json();
                        if (aj.success) { ok++; gel += aj.data.geloescht || 0; }
                    } catch (_) {}
                }
                App.showNotification(`${ok} Cluster aufgelöst, ${gel} Verlinkungen entfernt`, 'success');
                await this.ladeVorschlaege();
            } finally { this.clusterBulkLaeuft = false; }
        },

        // ─── Detail-Drawer ─────────────────────────────────────────
        async oeffneDetail(d) {
            Object.assign(this.drawer, { offen: true, domain: d.domain, verlinkungen: [], behaltenIds: new Set(),
                linkart: d.vorschlag_linkart || '', strategie: d.vorschlag_strategie || 'alle_behalten',
                urlStrategie: 'auto' });
            this.drawerAuswahlGeaendert = false;
            try {
                const p = new URLSearchParams({ customer_id: this.customerId, domain: d.domain });
                if (this.drawer.strategie) p.set('strategie', this.drawer.strategie);
                const r = await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.drawer.verlinkungen = j.data.verlinkungen;
                    this.drawer.behaltenIds = new Set(j.data.behalten_ids);
                    if (!this.drawer.linkart && j.data.linkart) this.drawer.linkart = j.data.linkart;
                    this.drawer.strategie    = j.data.strategie     || this.drawer.strategie;
                    this.drawer.urlStrategie = j.data.url_strategie || this.drawer.urlStrategie;
                }
            } catch (e) { App.showNotification('Detail-Laden fehlgeschlagen', 'error'); }
        },
        toggleBehalten(id) {
            this.drawerAuswahlGeaendert = true;
            if (this.drawer.behaltenIds.has(id)) this.drawer.behaltenIds.delete(id);
            else this.drawer.behaltenIds.add(id);
        },
        async reBerechneAuswahl() {
            // Strategie oder URL-Bevorzugung geändert → vom Server neu berechnen.
            // Wissensbasis speichern wir erst beim "Übernehmen" — hier nur live anwenden.
            try {
                const p = new URLSearchParams({
                    customer_id: this.customerId,
                    domain: this.drawer.domain,
                    strategie: this.drawer.strategie || 'alle_behalten',
                    url_strategie: this.drawer.urlStrategie || 'auto',
                });
                const r = await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.drawer.verlinkungen = j.data.verlinkungen;
                    this.drawer.behaltenIds  = new Set(j.data.behalten_ids);
                }
            } catch (e) { /* ignore */ }
        },
        async drawerAnnehmen() {
            if (!this.drawer.linkart) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-annehmen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        domain: this.drawer.domain,
                        linkart: this.drawer.linkart,
                        strategie: this.drawer.strategie,
                        url_strategie: this.drawer.urlStrategie,
                        behalten_ids: Array.from(this.drawer.behaltenIds),
                        manuell_geaendert: this.drawerAuswahlGeaendert,
                    })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification(`${this.drawer.domain}: ${j.data.behalten} behalten, ${j.data.geloescht} gelöscht`, 'success');
                    this.drawer.offen = false;
                    await this.ladeVorschlaege();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error'); }
        },

        // ─── Einzeln aus Klar-Liste annehmen ───────────────────────
        async annehmenSchnell(d) {
            try {
                const p = new URLSearchParams({ customer_id: this.customerId, domain: d.domain, strategie: d.vorschlag_strategie || '' });
                const r1 = await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' });
                const j1 = await r1.json();
                if (!j1.success) throw new Error(j1.message || 'Detail-Fehler');

                const r2 = await fetch('/api/v1/lam/aufraeum-annehmen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        domain: d.domain,
                        linkart: d.vorschlag_linkart,
                        strategie: d.vorschlag_strategie,
                        behalten_ids: j1.data.behalten_ids,
                        manuell_geaendert: false,
                    })
                });
                const j2 = await r2.json();
                if (j2.success) {
                    App.showNotification(`${d.domain}: ${j2.data.behalten} behalten, ${j2.data.geloescht} gelöscht`, 'success');
                    // Optimistisch aus Liste entfernen
                    this.vorschlaege.klar = this.vorschlaege.klar.filter(x => x.domain !== d.domain);
                    this.vorschlaege.gesamt = this.vorschlaege.klar.length + this.vorschlaege.unbestimmt.length;
                }
            } catch (e) { App.showNotification('Fehler: ' + e.message, 'error'); }
        },

        // ─── KI-Bulk-Klassifikation ────────────────────────────────
        async kiAlleStart() {
            const offene = this.vorschlaege.unbestimmt.slice();
            if (!offene.length) return;
            Object.assign(this, { kiLaeuft: true, kiFertig: false, kiAbbrechen: false,
                                  kiDone: 0, kiTotal: offene.length, kiOk: 0, kiFehler: [] });
            const batchSize = 25; // Sonnet kann mehr, aber wir wollen Fortschritt sehen
            for (let i = 0; i < offene.length; i += batchSize) {
                if (this.kiAbbrechen) break;
                const chunk = offene.slice(i, i + batchSize);
                try {
                    const r = await fetch('/api/v1/lam/aufraeum-klassifiziere-ki', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ domains: chunk, customer_id: this.customerId })
                    });
                    const j = await r.json();
                    if (j.success) {
                        this.kiOk += j.data.ok || 0;
                        if (j.data.fehler_liste && j.data.fehler_liste.length) this.kiFehler.push(...j.data.fehler_liste);
                    } else this.kiFehler.push(j.message || 'Chunk fehlgeschlagen');
                } catch (e) { this.kiFehler.push(`Chunk ${i+1}: ${e.message}`); }
                this.kiDone = Math.min(i + batchSize, offene.length);
                // Kein requestAnimationFrame: feuert nicht im Hintergrund-Tab -> Schleife blieb haengen.
                await new Promise(r => setTimeout(r, 50));
            }
            this.kiFertig = true;
        },
        async kiSchliessen() {
            Object.assign(this, { kiLaeuft: false, kiFertig: false, kiAbbrechen: false });
            await this.laden();
        },

        // ─── Bulk-Annahme aller klaren ─────────────────────────────
        async bulkAlleKlarStart() {
            const alle = this.vorschlaege.klar.slice();
            if (!alle.length) return;
            if (!confirm(`${alle.length} Domains wirklich übernehmen? Pro Domain wird die beste URL behalten, der Rest gelöscht.`)) return;
            Object.assign(this.bulk, { offen: true, done: 0, total: alle.length, erfolge: 0,
                                       behalten: 0, geloescht: 0, abbrechen: false, fertig: false });
            const chunkSize = 20;
            for (let i = 0; i < alle.length; i += chunkSize) {
                if (this.bulk.abbrechen) break;
                const chunk = alle.slice(i, i + chunkSize).map(d => ({
                    domain: d.domain, linkart: d.vorschlag_linkart, strategie: d.vorschlag_strategie
                }));
                try {
                    const r = await fetch('/api/v1/lam/aufraeum-bulk-annehmen', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ customer_id: this.customerId, domains: chunk })
                    });
                    const j = await r.json();
                    if (j.success) {
                        this.bulk.erfolge += j.data.ok || 0;
                        this.bulk.behalten += j.data.behalten || 0;
                        this.bulk.geloescht += j.data.geloescht || 0;
                    }
                } catch (e) { /* ignore */ }
                this.bulk.done = Math.min(i + chunkSize, alle.length);
                // Kein requestAnimationFrame: feuert nicht im Hintergrund-Tab -> Schleife blieb haengen.
                await new Promise(r => setTimeout(r, 50));
            }
            this.bulk.fertig = true;
        },
        async bulkSchliessen() {
            this.bulk.offen = false;
            await this.laden();
        },

        // ─── Fokus-Wizard ──────────────────────────────────────────
        setzeModus(m) {
            this.modus = m;
            try { localStorage.setItem('thx_lam_aufraeum_modus', m); } catch (e) {}
            if (m === 'fokus') this.sortiereFokus();
        },
        get aktuelleDomain() {
            return this.fokusListe[this.fokusIndex] || null;
        },
        get wizardBehalten() {
            const ids = new Set(this.wizardDetail.behalten_ids || []);
            return (this.wizardDetail.verlinkungen || []).filter(v => ids.has(v.id));
        },
        get wizardGeloescht() {
            const ids = new Set(this.wizardDetail.behalten_ids || []);
            return (this.wizardDetail.verlinkungen || []).filter(v => !ids.has(v.id));
        },
        get wizardGesamt() {
            return (this.wizardDetail.verlinkungen || []).length;
        },
        wizardToggleId(id) {
            // ID zwischen Behalten und Geloescht hin- und herschieben.
            // Manuelle Aenderung markieren, damit Wissensbasis auf 'manuell' lernt.
            const arr = (this.wizardDetail.behalten_ids || []).slice();
            const idx = arr.indexOf(id);
            if (idx >= 0) arr.splice(idx, 1);
            else          arr.push(id);
            this.wizardDetail.behalten_ids = arr;
            this.wizard.geaendert = true;
        },
        wizardIstBehalten(id) {
            return (this.wizardDetail.behalten_ids || []).indexOf(id) >= 0;
        },
        oeffneNeueLinkart() {
            this.neueLinkart.offen = true;
            this.neueLinkart.label = '';
            this.neueLinkart.beschreibung = '';
            this.neueLinkart.default_strategie = 'reduktion_auf_1';
        },
        async oeffneLinkartVerwaltung() {
            // Vorschlaege ggf. neu laden, damit kunden_linkarten frisch ist
            await this.ladeVorschlaege();
            this.linkartVerwaltung.offen = true;
        },
        async speichereVerwaltung(kl) {
            this.linkartVerwaltung.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/kunden-linkarten', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        linkart_key: kl.linkart_key,
                        label: kl.label,
                        beschreibung: kl.beschreibung,
                        default_strategie: kl.default_strategie,
                    })
                });
                const j = await r.json();
                if (j.success) App.showNotification(`„${kl.label}" gespeichert`, 'success');
                else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error'); }
            finally { this.linkartVerwaltung.laeuft = false; }
        },
        async loescheKundenLinkart(kl) {
            if (!confirm(`Linkart „${kl.label}" wirklich löschen? Bereits klassifizierte Verlinkungen behalten den Wert „${kl.linkart_key}", aber im Dropdown taucht er nicht mehr auf.`)) return;
            this.linkartVerwaltung.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/kunden-linkarten', {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: kl.id })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification(`„${kl.label}" gelöscht`, 'success');
                    await this.ladeVorschlaege();
                } else App.showNotification(j.message || 'Fehler', 'error');
            } catch (e) { App.showNotification('Löschen fehlgeschlagen: ' + e.message, 'error'); }
            finally { this.linkartVerwaltung.laeuft = false; }
        },
        wizardZielErmitteln() {
            // Haeufigstes ziel_url aus den behaltenen URLs nehmen; falls keines da,
            // aus allen Verlinkungen. Filtert leere Werte.
            const verlinkungen = this.wizardDetail.verlinkungen || [];
            const behaltenIds = new Set(this.wizardDetail.behalten_ids || []);
            const kandidaten = verlinkungen.filter(v => behaltenIds.has(v.id));
            const quelle = kandidaten.length ? kandidaten : verlinkungen;
            const zaehler = {};
            quelle.forEach(v => {
                const z = (v.ziel_url || '').trim();
                if (z) zaehler[z] = (zaehler[z] || 0) + 1;
            });
            const sortiert = Object.entries(zaehler).sort((a, b) => b[1] - a[1]);
            if (sortiert.length) {
                this.wizard.zielUrl = sortiert[0][0];
                this.wizard.geaendert = true;
                App.showNotification(`Linkziel übernommen: ${sortiert[0][0]} (${sortiert[0][1]}x in Verlinkungen)`, 'success');
            } else {
                App.showNotification('Kein Linkziel in den Verlinkungen vorhanden', 'warning');
            }
        },
        async speichereNeueLinkart() {
            const label = this.neueLinkart.label.trim();
            if (!label) return;
            try {
                const r = await fetch('/api/v1/lam/kunden-linkarten', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        label: label,
                        beschreibung: this.neueLinkart.beschreibung.trim(),
                        default_strategie: this.neueLinkart.default_strategie,
                    })
                });
                const j = await r.json();
                if (!j.success) { App.showNotification(j.message || 'Fehler', 'error'); return; }
                this.neueLinkart.offen = false;
                // Vorschlaege neu laden, damit die neue Linkart im Dropdown ist
                await this.ladeVorschlaege();
                // Im Wizard direkt setzen (der gespeicherte Key wird vom Backend gebildet)
                const liste = this.vorschlaege.kunden_linkarten || [];
                const neu = liste.find(k => k.label === label);
                if (neu) {
                    this.wizard.linkart   = neu.linkart_key;
                    this.wizard.strategie = neu.default_strategie || this.wizard.strategie;
                    this.wizard.geaendert = true;
                }
                App.showNotification(`Linkart „${label}" angelegt`, 'success');
            } catch (e) {
                App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error');
            }
        },
        get wizardMergeHinweis() {
            // Zeigt einen Hinweis, wenn beim Annehmen ein Linktext aus zu loeschenden URLs
            // auf eine behaltene URL ohne Linktext uebernommen werden wuerde.
            if (!this.wizard.mergeLinktext) return '';
            const behalten = this.wizardBehalten;
            const geloescht = this.wizardGeloescht;
            if (!behalten.length || !geloescht.length) return '';
            const allesBehaltenHabenLinktext = behalten.every(v => (v.linktext || '').trim() !== '');
            if (allesBehaltenHabenLinktext) return '';
            const ersterLinktext = geloescht.map(v => (v.linktext || '').trim()).find(t => t !== '');
            if (!ersterLinktext) return '';
            return `Linktext „${ersterLinktext.length > 50 ? ersterLinktext.substring(0,50)+'…' : ersterLinktext}" wird übernommen`;
        },
        sortiereFokus() {
            try { localStorage.setItem('thx_lam_aufraeum_sort', this.fokusSort); } catch (e) {}
            try { localStorage.setItem('thx_lam_aufraeum_kat',  this.fokusKategorie); } catch (e) {}
            try { localStorage.setItem('thx_lam_aufraeum_filter', JSON.stringify(this.fokusFilter)); } catch (e) {}
            let arr = [];
            if (this.fokusKategorie === 'alle' || this.fokusKategorie === 'klar')
                arr = arr.concat(this.vorschlaege.klar.map(d => ({ ...d, kategorie: 'klar' })));
            if (this.fokusKategorie === 'alle' || this.fokusKategorie === 'unbestimmt')
                arr = arr.concat(this.vorschlaege.unbestimmt.map(d => ({ ...d, kategorie: 'unbestimmt' })));

            // Quick-Filter anwenden (jeweils OR innerhalb einer Gruppe, AND zwischen Gruppen).
            // Leeres Set = "kein Filter dieser Gruppe".
            const f = this.fokusFilter;
            if (f.anzahl.length) {
                arr = arr.filter(d => f.anzahl.some(b => this.matchAnzahlBucket(d.anzahl, b)));
            }
            if (f.linktext.length) {
                arr = arr.filter(d => f.linktext.includes(d.linktext_status || 'keine'));
            }
            if (f.si.length) {
                arr = arr.filter(d => {
                    const si = d.sistrix_si;
                    return f.si.some(b => {
                        if (b === 'fehlt')    return si == null;
                        if (b === 'niedrig')  return si != null && si < 1;
                        if (b === 'mittel')   return si != null && si >= 1 && si < 5;
                        if (b === 'hoch')     return si != null && si >= 5;
                        return false;
                    });
                });
            }
            if (f.sonstiges.length) {
                arr = arr.filter(d => {
                    return f.sonstiges.some(b => {
                        if (b === 'unsicher')         return !!d.hat_unsichere_empfehlung;
                        if (b === 'mit_dubletten')    return d.anzahl > 1;
                        if (b === 'nachbearbeitung')  return !!d.ist_nachbearbeitung;
                        if (b === 'nicht_bearbeitet') return !d.ist_nachbearbeitung;
                        return false;
                    });
                });
            }

            if (this.fokusSort === 'klar_zuerst') {
                // Klare Fälle zuerst, dann unbestimmte. Innerhalb jeder Gruppe: höchste Anzahl Verlinkungen
                // zuerst (mehr Auswirkung pro Klick), Domain-A-Z als Tiebreaker.
                arr.sort((a, b) => {
                    const katA = a.kategorie === 'klar' ? 0 : 1;
                    const katB = b.kategorie === 'klar' ? 0 : 1;
                    if (katA !== katB) return katA - katB;
                    if ((b.anzahl || 0) !== (a.anzahl || 0)) return (b.anzahl || 0) - (a.anzahl || 0);
                    return (a.domain || '').localeCompare(b.domain || '');
                });
            }
            if (this.fokusSort === 'anzahl')      arr.sort((a, b) => b.anzahl - a.anzahl);
            if (this.fokusSort === 'anzahl_asc')  arr.sort((a, b) => a.anzahl - b.anzahl);
            if (this.fokusSort === 'domain')      arr.sort((a, b) => a.domain.localeCompare(b.domain));
            if (this.fokusSort === 'si_desc')     arr.sort((a, b) => (b.sistrix_si ?? -1) - (a.sistrix_si ?? -1));
            if (this.fokusSort === 'si_asc')      arr.sort((a, b) => (a.sistrix_si ?? 99) - (b.sistrix_si ?? 99));
            if (this.fokusSort === 'linkart')     arr.sort((a, b) =>
                (a.vorschlag_linkart || 'zzz').localeCompare(b.vorschlag_linkart || 'zzz')
                || b.anzahl - a.anzahl);
            this.fokusListe = arr;
            this.fokusIndex = 0;
            this.wizardInitFuerDomain();
        },
        // ─── Quick-Filter-Helfer ──────────────────────────────────────
        matchAnzahlBucket(anzahl, bucket) {
            if (bucket === '1')    return anzahl === 1;
            if (bucket === '2-4')  return anzahl >= 2 && anzahl <= 4;
            if (bucket === '5-9')  return anzahl >= 5 && anzahl <= 9;
            if (bucket === '10+')  return anzahl >= 10 && anzahl < 25;
            if (bucket === '25+')  return anzahl >= 25;
            return false;
        },
        toggleFilter(gruppe, wert) {
            const idx = this.fokusFilter[gruppe].indexOf(wert);
            if (idx >= 0) {
                this.fokusFilter[gruppe].splice(idx, 1);
            } else {
                // „schon bearbeitet" und „noch nicht bearbeitet" schliessen sich aus — die
                // Sonst.-Filter sind ODER-verknuepft, beide zusammen ergaeben wieder alles.
                const gegenteil = { nachbearbeitung: 'nicht_bearbeitet', nicht_bearbeitet: 'nachbearbeitung' }[wert];
                if (gegenteil) {
                    const gi = this.fokusFilter[gruppe].indexOf(gegenteil);
                    if (gi >= 0) this.fokusFilter[gruppe].splice(gi, 1);
                }
                this.fokusFilter[gruppe].push(wert);
            }
            this.sortiereFokus();
        },
        filterAktiv(gruppe, wert) {
            return this.fokusFilter[gruppe].includes(wert);
        },
        // Liefert die Gesamt-Liste (alle Vorschlaege ohne Filter) — fuer Counter
        get alleVorschlaegeFlach() {
            return [
                ...(this.vorschlaege.klar || []).map(d => ({ ...d, kategorie: 'klar' })),
                ...(this.vorschlaege.unbestimmt || []).map(d => ({ ...d, kategorie: 'unbestimmt' })),
            ];
        },
        // Counter: wieviele Domains wuerde dieser Filter-Wert (zusaetzlich oder alleine) treffen
        countFuerFilter(gruppe, wert) {
            const liste = this.alleVorschlaegeFlach;
            if (gruppe === 'anzahl')   return liste.filter(d => this.matchAnzahlBucket(d.anzahl, wert)).length;
            if (gruppe === 'linktext') return liste.filter(d => (d.linktext_status || 'keine') === wert).length;
            if (gruppe === 'si') {
                return liste.filter(d => {
                    const si = d.sistrix_si;
                    if (wert === 'fehlt')   return si == null;
                    if (wert === 'niedrig') return si != null && si < 1;
                    if (wert === 'mittel')  return si != null && si >= 1 && si < 5;
                    if (wert === 'hoch')    return si != null && si >= 5;
                    return false;
                }).length;
            }
            if (gruppe === 'sonstiges') {
                return liste.filter(d => {
                    if (wert === 'unsicher')        return !!d.hat_unsichere_empfehlung;
                    if (wert === 'mit_dubletten')   return d.anzahl > 1;
                    if (wert === 'nachbearbeitung')  return !!d.ist_nachbearbeitung;
                    if (wert === 'nicht_bearbeitet') return !d.ist_nachbearbeitung;
                    return false;
                }).length;
            }
            return 0;
        },
        filterReset() {
            this.fokusFilter = { anzahl: [], linktext: [], si: [], sonstiges: [] };
            this.fokusKategorie = 'alle';
            this.sortiereFokus();
        },
        async toggleNachbearbeitung() {
            this.nachbearbeitung = !this.nachbearbeitung;
            try { localStorage.setItem('thx_lam_aufraeum_nachb', this.nachbearbeitung ? '1' : '0'); } catch (e) {}
            await this.ladeVorschlaege();
        },
        // Presets — schnelle Auswahl typischer Haeppchen
        presetAnwenden(name) {
            this.fokusFilter = { anzahl: [], linktext: [], si: [] };
            this.fokusKategorie = 'alle';
            if (name === 'quickwin') {
                // Klassifizierte Einzellinks — Maximal-Speed
                this.fokusFilter.anzahl = ['1'];
                this.fokusKategorie = 'klar';
                this.fokusSort = 'anzahl';
            } else if (name === 'linktext_reparatur') {
                this.fokusFilter.linktext = ['keine'];
                this.fokusSort = 'anzahl_asc';
            } else if (name === 'sitewide') {
                this.fokusFilter.anzahl = ['25+'];
                this.fokusSort = 'anzahl';
            } else if (name === 'gross') {
                this.fokusFilter.anzahl = ['10+', '25+'];
                this.fokusSort = 'anzahl';
            } else if (name === 'wertvoll') {
                this.fokusFilter.si = ['hoch'];
                this.fokusSort = 'si_desc';
            } else if (name === 'schrott') {
                this.fokusFilter.si = ['niedrig', 'fehlt'];
                this.fokusSort = 'anzahl_asc';
            } else if (name === 'unbestimmt') {
                this.fokusKategorie = 'unbestimmt';
                this.fokusSort = 'anzahl';
            }
            this.sortiereFokus();
        },
        // Zeit-Schaetzung in Minuten, gerundet
        schaetzungMin(anzahl) {
            if (!anzahl) return 0;
            return Math.max(1, Math.round((anzahl * this.sekProDomain) / 60));
        },
        setzeTagesziel(n) {
            this.fokusTagesziel = Math.max(0, parseInt(n, 10) || 0);
            try { localStorage.setItem('thx_lam_aufraeum_tagesziel', String(this.fokusTagesziel)); } catch (e) {}
        },
        // Initialisiert die Wizard-Auswahl (Linkart, Empfehlung, Linkziel, Strategie)
        // aus den Vorschlaegen der aktuellen Domain. Wird NUR beim Domain-Wechsel
        // aufgerufen — sonst wuerde es Klick-Auswahlen ueberschreiben.
        async wizardInitFuerDomain() {
            const d = this.aktuelleDomain;
            if (!d) { this.wizardDetail = { verlinkungen: [], behalten_ids: [] }; this.domainNotizText = ''; this.domainNotizDirty = false; return; }
            this.wizard.linkart    = d.vorschlag_linkart || '';
            this.wizard.strategie  = d.vorschlag_strategie || 'alle_behalten';
            this.wizard.empfehlung = d.vorschlag_empfehlung || '';
            this.wizard.zielUrl    = d.vorschlag_ziel_url || '';
            this.wizard.geaendert  = false;
            // Domain-Notiz: aus der Vorschlags-Liste vorhanden (kommt vom Backend),
            // sonst leer. Wird sofort sichtbar, bevor das Detail geladen ist.
            this.domainNotizText  = d.notiz || '';
            this.domainNotizDirty = false;
            await this.ladeWizardDetail();
        },
        // Nur die Verlinkungen + behalten_ids vom Server neu laden (bei Strategie-/URL-Pref-Wechsel).
        // Beruehrt KEINE Wizard-Auswahlen.
        async ladeWizardDetail() {
            const d = this.aktuelleDomain;
            if (!d) { this.wizardDetail = { verlinkungen: [], behalten_ids: [] }; return; }
            this.wizardLaedt = true;
            try {
                const p = new URLSearchParams({
                    customer_id: this.customerId, domain: d.domain,
                    strategie: this.wizard.strategie,
                    url_strategie: this.wizard.urlStrategie,
                });
                // Im Nachbearbeitungs-Modus auch bereits bestaetigte Verlinkungen laden,
                // sonst zeigt der Wizard "Keine Verlinkungen geladen" obwohl die Domain
                // noch Dubletten hat, die der User reduzieren will.
                if (this.nachbearbeitung) p.set('auch_bestaetigt', '1');
                const r = await fetch('/api/v1/lam/aufraeum-domain-detail?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.wizardDetail = j.data;
                    // url_strategie aus Wissensbasis nur uebernehmen wenn der User
                    // selber noch nichts gewaehlt hat (Initial-Load).
                    if (!this.wizard.geaendert && j.data.url_strategie) {
                        this.wizard.urlStrategie = j.data.url_strategie;
                    }
                }
            } catch (e) { /* ignore */ }
            finally { this.wizardLaedt = false; }
        },
        async wizardAnnehmen() {
            const d = this.aktuelleDomain;
            if (!d || !this.wizard.linkart) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-annehmen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.customerId,
                        domain: d.domain,
                        linkart: this.wizard.linkart,
                        strategie: this.wizard.strategie,
                        url_strategie: this.wizard.urlStrategie,
                        behalten_ids: this.wizardDetail.behalten_ids,
                        manuell_geaendert: this.wizard.geaendert,
                        merge_linktext: this.wizard.mergeLinktext,
                        empfehlung: this.wizard.empfehlung,
                        ziel_url: this.wizard.zielUrl,
                        auch_bestaetigt: this.nachbearbeitung,
                    })
                });
                const j = await r.json();
                if (!j.success) { App.showNotification(j.message || 'Fehler', 'error'); return; }
                this.fokusErledigt++;
                const mergeInfo = (j.data.merge?.linktext || j.data.merge?.ziel_url) ? ' (Linktext gemerged)' : '';
                this.zeigeUndoToast(`${d.domain}: ${j.data.behalten} behalten, ${j.data.geloescht} gelöscht${mergeInfo}`, d.domain);
                // Aktuelle Domain aus Liste entfernen + naechste laden (mit Vorauswahl!)
                this.fokusListe.splice(this.fokusIndex, 1);
                if (this.fokusIndex >= this.fokusListe.length) this.fokusIndex = Math.max(0, this.fokusListe.length - 1);
                this.wizardInitFuerDomain();
            } catch (e) { App.showNotification('Speichern fehlgeschlagen: ' + e.message, 'error'); }
        },
        wizardSkip() {
            if (!this.aktuelleDomain) return;
            this.fokusIndex++;
            if (this.fokusIndex >= this.fokusListe.length) this.fokusIndex = this.fokusListe.length - 1;
            this.wizardInitFuerDomain();
        },
        wizardZurueck() {
            if (this.fokusIndex > 0) {
                this.fokusIndex--;
                this.wizardInitFuerDomain();
            }
        },
        zeigeUndoToast(text, domain) {
            this.undoStack.push({ domain, customerId: this.customerId, when: Date.now() });
            if (this.undoStack.length > 10) this.undoStack.shift();
            this.undoToast.text = text;
            if (this.undoToast.timeoutId) clearTimeout(this.undoToast.timeoutId);
            this.undoToast.timeoutId = setTimeout(() => { this.undoToast.text = ''; }, 6000);
        },
        async undoAusfuehren() {
            const last = this.undoStack.pop();
            if (!last) return;
            try {
                const r = await fetch('/api/v1/lam/aufraeum-rueckgaengig', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: last.customerId, domain: last.domain })
                });
                const j = await r.json();
                if (j.success) {
                    App.showNotification(`Wiederhergestellt: ${last.domain} (${j.data.wiederhergestellt} Verlinkungen)`, 'success');
                    this.undoToast.text = '';
                    await this.ladeVorschlaege();
                    if (this.modus === 'fokus') this.sortiereFokus();
                } else App.showNotification(j.message || 'Undo fehlgeschlagen', 'error');
            } catch (e) { App.showNotification('Undo-Fehler: ' + e.message, 'error'); }
        },
        onKey(e) {
            if (this.modus !== 'fokus') return;
            // Eingaben in Inputs/Selects nicht abfangen
            const tag = (e.target?.tagName || '').toLowerCase();
            if (['input','select','textarea'].includes(tag)) return;
            if (this.drawer.offen) return; // Drawer hat Vorrang

            if (e.key === 'Enter')                      { e.preventDefault(); this.wizardAnnehmen(); }
            else if (e.key === 'ArrowRight')            { e.preventDefault(); this.wizardSkip(); }
            else if (e.key === 'ArrowLeft')             { e.preventDefault(); this.wizardZurueck(); }
            else if (e.key.toLowerCase() === 'e')       { e.preventDefault(); if (this.aktuelleDomain) this.oeffneDetail(this.aktuelleDomain); }
            else if (e.key.toLowerCase() === 'd')       { e.preventDefault(); this.wizard.urlStrategie = this.wizard.urlStrategie === 'tiefste' ? 'auto' : 'tiefste'; this.wizard.geaendert = true; this.ladeWizardDetail(); }
            else if (e.key === '1')                     { e.preventDefault(); this.wizard.strategie = 'alle_behalten';     this.wizard.geaendert=true; this.ladeWizardDetail(); }
            else if (e.key === '2')                     { e.preventDefault(); this.wizard.strategie = 'reduktion_auf_1';   this.wizard.geaendert=true; this.ladeWizardDetail(); }
            else if (e.key === '3')                     { e.preventDefault(); this.wizard.strategie = 'reduktion_auf_2';   this.wizard.geaendert=true; this.ladeWizardDetail(); }
            else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); this.undoAusfuehren(); }
            else if (e.key === 'Escape')                { e.preventDefault(); this.setzeModus('liste'); }
        },

        // ─── Helpers ───────────────────────────────────────────────
        kurzUrl(u) { return u ? u.replace(/^https?:\/\//, '').replace(/\/$/, '') : ''; },
        formatLinkart(la) {
            const map = { spam: 'Spam', branchenverzeichnis: 'Branchenverzeichnis', fachverzeichnis: 'Fachverzeichnis',
                online_magazin: 'Online-Magazin', portal: 'Portal', blog: 'Blog', presseportal: 'Presseportal',
                forum: 'Forum', referenzprojekt: 'Referenzprojekt', partner: 'Partner', sponsoring: 'Sponsoring',
                stellenboerse: 'Stellenbörse', veranstaltung: 'Veranstaltung', kommentarlink: 'Kommentarlink',
                podcast: 'Podcast', weiterleitung: 'Weiterleitung', social_media: 'Social Media', sonstiges: 'Sonstiges' };
            return map[la] || la || '—';
        },
        formatStrategie(s) {
            const map = { alle_behalten: 'alle behalten', reduktion_auf_1: 'Strategie auf 1 reduzieren', reduktion_auf_2: 'Strategie auf 2 reduzieren' };
            return map[s] || s || '—';
        },
        formatEmpfehlung(e) {
            const map = { lassen: 'lassen', aendern: 'ändern', loeschen: 'löschen', disavow: 'disavow', geloescht: 'gelöscht', unsicher: 'unsicher (klären)' };
            return map[e] || e || '—';
        },
    };
}
</script>
