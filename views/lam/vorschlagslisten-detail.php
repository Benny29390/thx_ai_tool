<?php
/**
 * Vorschlagsliste-Detail — Karten-Sicht pro Eintrag mit Inline-Editoren
 * für Preis, Linkziel, Linktext, Artikelthema, Bemerkungen + Quick-Status-Buttons.
 *
 * Erwartet: $listeId
 */
use Core\Database;
use Services\LamService;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$liste = $svc->getVorschlagsliste($listeId ?? '');

if (!$liste) {
    echo '<div class="thx-page-header"><h1 class="thx-page-title">Liste nicht gefunden</h1></div>';
    echo '<a href="/lam/vorschlagslisten" style="color:var(--thoxan-700);">‹ Zurück zur Übersicht</a>';
    return;
}

$activeModul = 'linkoptionen';

// Status-Zähler: bestätigt + freigegeben gelten als "gesichert"
$counterGesichert = 0;
foreach ($liste['eintraege'] as $e) {
    if (in_array($e['status'], ['bestaetigt', 'kunde_freigegeben', 'abgeschlossen'], true)) $counterGesichert++;
}
$counterTotal = count($liste['eintraege']);
?>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="vlDetail()" x-init="init()">

<div class="thx-page-header" style="display:flex;align-items:flex-start;gap:16px;justify-content:space-between;">
    <div style="flex:1;min-width:0;">
        <a href="/lam/vorschlagslisten" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück</a>
        <h1 class="thx-page-title" style="margin-top:4px;"><?= htmlspecialchars($liste['name']) ?></h1>
        <div style="margin-top:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:var(--d-fs-sm);color:var(--slate-600);">
            <span class="lam-chip" style="background:var(--slate-100);color:var(--slate-700);"><?= htmlspecialchars($liste['status']) ?></span>
            <strong><?= htmlspecialchars($liste['customer_kuerzel'] ?: '?') ?></strong>
            <span style="color:var(--slate-400);">—</span>
            <span><?= htmlspecialchars($liste['customer_name'] ?: '') ?></span>
            <span style="color:var(--slate-400);">·</span>
            <span><strong><?= $counterGesichert ?></strong> / <?= $liste['zielzahl'] ?: $counterTotal ?></span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;">
        <button class="lam-btn lam-btn-secondary" @click="alleSichtbarWaehlen()"
                title="Alle sichtbaren (gefilterten) Einträge markieren — dann unten mit der Bulk-Leiste öffnen, Status setzen oder entfernen.">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">check_box</span>
            Alle auswählen<span x-show="sichtbareAnzahl() > 0" x-text="' (' + sichtbareAnzahl() + ')'"></span>
        </button>
        <a href="/api/v1/lam/vorschlagsliste-excel?id=<?= htmlspecialchars($liste['id']) ?>&variante=intern"
           class="lam-btn lam-btn-secondary" style="color:var(--emerald-700);border-color:var(--emerald-200);"
           title="Vollständige Spalten inkl. Cluster, Anbieter, Themengebiet, Preis-Range">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">download</span>
            Excel (intern)
        </a>
        <a href="/api/v1/lam/vorschlagsliste-excel?id=<?= htmlspecialchars($liste['id']) ?>&variante=kunde"
           class="lam-btn lam-btn-secondary" style="color:var(--thoxan-700);border-color:var(--thoxan-200);"
           title="Reduziert auf Kunden-Spalten (ohne Anbieter, mit Beispiellink). Nach Briefing 03 ▸ 13.">
            <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">download</span>
            Excel (für Kunden)
        </a>
        <button class="lam-btn lam-btn-secondary" @click="oeffneListeBearbeiten()">Bearbeiten</button>
        <button class="lam-btn lam-btn-secondary" @click="loescheListe()"
                style="color:var(--rose-700);border-color:var(--rose-300);">Löschen</button>
    </div>
</div>

<?php include __DIR__ . '/_tabs.php'; ?>

<?php if (!empty($liste['notiz'])): ?>
<div class="lam-card" style="margin-bottom:16px;padding:12px 16px;">
    <div style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;margin-bottom:4px;">Notiz</div>
    <div style="white-space:pre-wrap;font-size:var(--d-fs-sm);color:var(--slate-700);"><?= htmlspecialchars($liste['notiz']) ?></div>
</div>
<?php endif; ?>

<!-- 2-Spalter: Sub-Sidebar (Filter/Sort) links + Hauptbereich rechts -->
<div class="vl-container">

  <!-- ───────── Sub-Sidebar links (Stil: chat-sidebar) ───────── -->
  <aside class="vl-sidebar">
    <div class="vl-sidebar-header">
        <h2 class="thx-page-title" style="font-size:var(--d-fs-base);margin:0;">Filter &amp; Sortierung</h2>
        <button type="button" @click="filterStatus=''; sortKey='position'; aktualisiereAnzeige()"
                :disabled="filterStatus==='' && sortKey==='position'"
                :style="(filterStatus==='' && sortKey==='position') ? 'opacity:0.35;cursor:default;' : 'cursor:pointer;'"
                style="background:none;border:0;padding:0;color:var(--thoxan-600);font-size:0.75rem;text-decoration:underline;">
            zurücksetzen
        </button>
    </div>
    <div class="vl-sidebar-content">

        <!-- ── Section: Status ── -->
        <div class="vl-section">
            <h4 class="vl-section-title">Status</h4>
            <div class="vl-filter-list">
                <button type="button" class="vl-filter-row" :class="filterStatus==='' ? 'is-active' : ''"
                        @click="filterStatus=''; aktualisiereAnzeige()">
                    <span class="vl-filter-row-label" style="font-weight:600;">Alle Einträge</span>
                    <span class="vl-filter-row-count" x-text="counters.gesamt"></span>
                </button>
                <template x-for="s in statusOrdnung" :key="s">
                    <button type="button" class="vl-filter-row" :class="filterStatus===s ? 'is-active' : ''"
                            @click="filterStatus=s; aktualisiereAnzeige()">
                        <span class="lam-chip vl-filter-row-chip" :class="'lam-chip-status-' + s" x-text="statusLabels[s]"></span>
                        <span class="vl-filter-row-count" x-text="counters[s] || 0"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- ── Section: Sortierung ── -->
        <div class="vl-section">
            <h4 class="vl-section-title">Sortierung</h4>
            <div class="vl-filter-list">
                <template x-for="opt in sortOptionen" :key="opt.key">
                    <button type="button" class="vl-filter-row" :class="sortKey===opt.key ? 'is-active' : ''"
                            @click="sortKey=opt.key; aktualisiereAnzeige()">
                        <span class="vl-filter-row-label" x-text="opt.label"></span>
                        <span x-show="sortKey===opt.key" style="color:var(--thoxan-600);font-size:0.85rem;">✓</span>
                    </button>
                </template>
                <p x-show="sortKey === 'position' && !filterStatus"
                   style="margin:6px 10px 0;font-size:0.7rem;color:var(--slate-500);line-height:1.4;">
                    Reihenfolge per Drag &amp; Drop am ⋮⋮-Handle anpassbar.
                </p>
                <p x-show="sortKey === 'position' && filterStatus"
                   style="margin:6px 10px 0;font-size:0.7rem;color:var(--amber-700);line-height:1.4;">
                    Drag &amp; Drop ist bei aktivem Status-Filter aus — sonst wäre die Position nicht eindeutig.
                </p>
            </div>
        </div>

    </div>
  </aside>

  <!-- ───────── Hauptbereich rechts ───────── -->
  <main class="vl-main">

<section class="lam-card" style="padding:0;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--slate-100);">
        <h3 style="margin:0;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-600);">
            Domains in der Liste
            <span x-show="filterStatus" style="margin-left:8px;font-size:0.7rem;color:var(--slate-400);text-transform:none;letter-spacing:0;">
                · gefiltert nach <span x-text="statusLabels[filterStatus]" style="color:var(--thoxan-700);"></span>
                <button type="button" @click="filterStatus=''; aktualisiereAnzeige()"
                        style="background:none;border:0;color:var(--rose-600);cursor:pointer;font-size:0.85rem;margin-left:4px;line-height:1;" title="Filter entfernen">×</button>
            </span>
        </h3>
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" @click="alleAufklappen()"
                    style="background:none;border:0;color:var(--slate-500);font-size:0.75rem;cursor:pointer;text-decoration:underline;">alle aufklappen</button>
            <button type="button" @click="alleEinklappen()"
                    style="background:none;border:0;color:var(--slate-500);font-size:0.75rem;cursor:pointer;text-decoration:underline;">alle einklappen</button>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneDomainHinzufuegen()" style="margin-left:8px;">
                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                Domain hinzufügen
            </button>
        </div>
    </div>

    <!-- Bulk-Leiste: erscheint, sobald mindestens ein Eintrag markiert ist. -->
    <div class="thx-bulk-toolbar" x-show="auswahl.size > 0" x-cloak
         style="position:sticky;top:8px;z-index:20;margin-bottom:10px;">
        <span class="thx-bulk-count"><span x-text="auswahl.size"></span> ausgewählt</span>
        <span class="thx-divider"></span>
        <button class="lam-btn lam-btn-primary lam-btn-small" @click="bulkOeffnen()">
            <span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;">open_in_new</span> Öffnen
        </button>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="bulkKopieren()">URLs kopieren</button>
        <span class="thx-divider"></span>
        <select x-model="bulkStatusWert" class="lam-filter-select" style="width:auto;">
            <option value="">Status setzen …</option>
            <template x-for="s in statusOrdnung" :key="'bs-' + s">
                <option :value="s" x-text="statusLabels[s] || s"></option>
            </template>
        </select>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="bulkStatus()" :disabled="!bulkStatusWert || bulkLaeuft">Anwenden</button>
        <span class="thx-divider"></span>
        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="bulkLoeschen()" :disabled="bulkLaeuft"
                style="color:var(--rose-700);border-color:var(--rose-300);">
            <span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;">delete</span> Aus Liste entfernen
        </button>
        <span style="flex:1;"></span>
        <button class="thx-bulk-clear" @click="auswahlAufheben()">Auswahl aufheben</button>
    </div>

    <?php if (empty($liste['eintraege'])): ?>
        <div style="padding:40px;text-align:center;color:var(--slate-500);">
            Noch keine Einträge in dieser Liste.
            <div style="margin-top:12px;">
                <button class="lam-btn lam-btn-primary lam-btn-small" @click="oeffneDomainHinzufuegen()">
                    Erste Domain hinzufügen
                </button>
            </div>
        </div>
    <?php else: ?>
    <div class="vl-eintrag-liste">
        <?php foreach ($liste['eintraege'] as $idx => $e):
            $statusKlasse = 'lam-chip-status-' . $e['status'];
        ?>
        <article class="vl-eintrag" :id="'eintrag-' + <?= json_encode($e['id']) ?>"
                 x-data="vlEintrag(<?= htmlspecialchars(json_encode($e, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)"
                 :class="(entfernt ? 'is-entfernt ' : '') + (dropZielOben ? 'is-drop-above ' : '') + (dropZielUnten ? 'is-drop-below ' : '')"
                 x-show="!entfernt && _sichtbar"
                 :style="{ order: _sortOrder }"
                 @dragover.prevent="onDragOver($event)"
                 @dragleave="onDragLeave($event)"
                 @drop.prevent="onDrop($event)">
            <!-- Kopfzeile (immer sichtbar, Klick → ein/ausklappen) -->
            <header @click="aufgeklappt = !aufgeklappt"
                    style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;cursor:pointer;user-select:none;padding:4px 0;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex:1;min-width:0;">
                    <input type="checkbox" class="thx-bulk-checkbox" @click.stop
                           x-model="ausgewaehlt"
                           @change="window.__vlDetailRoot && window.__vlDetailRoot.setAuswahl(e.id, $event.target.checked)"
                           title="Für Massenbearbeitung markieren"
                           style="flex-shrink:0;cursor:pointer;width:17px;height:17px;">
                    <span x-show="kannVerschieben()" @click.stop @mousedown.stop
                          draggable="true" @dragstart="onDragStart($event)" @dragend="onDragEnd($event)"
                          class="vl-drag-handle"
                          title="Ziehen, um die Reihenfolge zu ändern (nur bei Sortierung „Position")">⋮⋮</span>
                    <span style="color:var(--slate-400);font-size:0.85rem;width:14px;flex-shrink:0;" x-text="aufgeklappt ? '▾' : '▸'"></span>
                    <a :href="'/lam/linkquellen/' + encodeURIComponent(e.domain_id)" @click.stop
                       style="font-size:1rem;font-weight:600;color:var(--slate-800);text-decoration:none;" x-text="e.domain_url"></a>
                    <a :href="'https://' + e.domain_url" target="_blank" rel="noopener" @click.stop
                       style="color:var(--slate-400);font-size:0.85rem;" title="extern öffnen">↗</a>
                    <span x-show="e.anbieter_name" style="color:var(--slate-600);font-size:var(--d-fs-sm);display:inline-flex;align-items:center;gap:5px;">
                        <span x-text="e.anbieter_name"></span>
                        <span x-show="parseInt(e.anbieter_ist_vermittler) === 1"
                              style="background:var(--amber-100);color:var(--amber-800);font-size:0.6rem;padding:1px 5px;border-radius:999px;font-weight:600;letter-spacing:0.02em;"
                              title="Vermittler — kein echter Betreiber bekannt">Vermittler</span>
                    </span>
                    <span class="lam-chip" :class="'lam-chip-status-' + e.status" style="font-size:var(--d-fs-xs);" x-text="statusLabel(e.status)"></span>
                    <span x-show="e.si_aktuell !== null" style="color:var(--slate-500);font-size:var(--d-fs-xs);">
                        · SI <span x-text="parseFloat(e.si_aktuell).toFixed(4)"></span> / DP <span x-text="e.dp_aktuell !== null && e.dp_aktuell !== undefined ? parseInt(e.dp_aktuell).toLocaleString('de-DE') : '—'"></span>
                    </span>
                    <span x-show="e.preis_kunde" style="color:var(--slate-600);font-size:var(--d-fs-xs);">
                        · <strong x-text="parseFloat(e.preis_kunde).toLocaleString('de-DE') + ' €'"></strong>
                        <span x-show="e.preis_anbieter"
                              :title="'Marge: ' + (parseFloat(e.preis_kunde||0) - parseFloat(e.preis_anbieter||0)).toLocaleString('de-DE') + ' € (intern)'"
                              style="color:var(--slate-400);font-size:0.7rem;margin-left:4px;">
                            (EK <span x-text="parseFloat(e.preis_anbieter).toLocaleString('de-DE') + ' €'"></span>)
                        </span>
                    </span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;" @click.stop>
                    <a x-show="(e.status === 'kunde_freigegeben' || e.status === 'bestaetigt') && !e.massnahme_id"
                       :href="'/lam/massnahmen?neu_aus_linkoption=' + encodeURIComponent(e.id)"
                       class="lam-btn lam-btn-primary lam-btn-small"
                       style="font-size:0.75rem;">→ Maßnahme</a>
                    <a x-show="e.massnahme_id"
                       :href="'/lam/massnahmen/' + encodeURIComponent(e.massnahme_id)"
                       style="color:var(--emerald-700);font-size:0.78rem;text-decoration:none;">↗ Maßnahme</a>
                    <button class="lam-btn-link" @click="entferneEintrag()"
                            style="color:var(--rose-600);background:none;border:0;cursor:pointer;font-size:0.78rem;">✕</button>
                </div>
            </header>

            <!-- Aufklappbarer Inhalt -->
            <div x-show="aufgeklappt" x-cloak><div style="padding-top:12px;border-top:1px solid var(--slate-100);margin-top:8px;">

            <!-- Import-Hinweis: zeigt was aus dem Asana-Ticket übernommen wurde -->
            <div x-show="importHinweis" x-cloak
                 style="background:var(--emerald-50);border:1px solid var(--emerald-200);color:var(--emerald-800);border-radius:6px;padding:8px 12px;margin-bottom:10px;font-size:0.82rem;display:flex;align-items:center;gap:8px;">
                <span style="font-size:1rem;">✓</span>
                <span x-text="importHinweis" style="flex:1;"></span>
                <button @click="importHinweis = ''" style="background:none;border:0;cursor:pointer;color:var(--emerald-700);font-size:1.1rem;line-height:1;">×</button>
            </div>

            <!-- Tags + Meta-Zeile -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:var(--d-fs-xs);color:var(--slate-500);">
                <template x-for="t in (e.tags ? e.tags.split('|') : [])" :key="t">
                    <span class="lam-chip" style="background:var(--slate-100);color:var(--slate-700);font-size:0.7rem;" x-text="t"></span>
                </template>
                <span x-show="e.kontakt_am">Kontakt: <strong x-text="formatDatum(e.kontakt_am)"></strong></span>
                <span x-show="e.letzte_rueckmeldung_am">· Rückmeldung: <strong x-text="formatDatum(e.letzte_rueckmeldung_am)"></strong>
                    <span x-show="e.letzte_rueckmeldung_typ">(<span x-text="e.letzte_rueckmeldung_typ"></span>)</span>
                </span>
            </div>

            <!-- Aufbau-Reihenfolge identisch zum Asana-Ticket: Preise → Linkziel → Linktext → Artikelthema → Kontext für Linkeinbau → Bemerkungen -->

            <!-- Preise: Kunde (öffentlich) + Anbieter (intern) — beide kompakt nebeneinander -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                <div class="thx-form-field">
                    <label style="display:flex;align-items:center;justify-content:space-between;">
                        <span>Preis Kunde (EUR)</span>
                        <span x-show="e.abgerechnet" style="font-size:0.7rem;color:var(--emerald-700);">abgerechnet</span>
                    </label>
                    <input type="number" step="0.01" x-model.lazy="e.preis_kunde" @change="speichereFeld('preis_kunde', e.preis_kunde)" placeholder="z.B. 200 (im Asana-Ticket sichtbar)">
                </div>
                <div class="thx-form-field">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <span>Preis Anbieter (EUR)</span>
                        <span title="Intern. Niemals im Asana-Ticket oder Kunden-Excel."
                              style="background:var(--amber-100);color:var(--amber-800);font-size:0.65rem;padding:1px 6px;border-radius:999px;font-weight:600;letter-spacing:0.02em;">🔒 intern</span>
                    </label>
                    <input type="number" step="0.01" x-model.lazy="e.preis_anbieter" @change="speichereFeld('preis_anbieter', e.preis_anbieter)" placeholder="z.B. 80 (nur intern)">
                </div>
            </div>

            <!-- Beispielartikel / Kategorie — optional, direkt hinter Preis -->
            <div class="thx-form-field" style="margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:8px;">
                    <span>Beispielartikel / Kategorie (URL)</span>
                    <span style="font-size:0.7rem;color:var(--slate-400);">optional — falls leer, im Asana-Ticket weggelassen</span>
                </label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="url" x-model.lazy="e.beispielartikel_url" @change="speichereFeld('beispielartikel_url', e.beispielartikel_url)"
                           placeholder="https://zieldomain.de/beispielartikel oder /kategorie" style="flex:1;">
                    <a x-show="e.beispielartikel_url" :href="e.beispielartikel_url" target="_blank" rel="noopener"
                       style="color:var(--thoxan-600);font-size:1rem;text-decoration:none;padding:0 6px;" title="extern öffnen">↗</a>
                </div>
            </div>

            <!-- Linkziel + Linktext -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                <div class="thx-form-field">
                    <label>Linkziel (Kunden-URL)</label>
                    <input type="url" x-model.lazy="e.ziel_url" @change="speichereFeld('ziel_url', e.ziel_url)" placeholder="https://kunden-seite.de/zielseite (tba.)">
                </div>
                <div class="thx-form-field">
                    <label>Linktext</label>
                    <input type="text" x-model.lazy="e.vorgeschlagener_linktext" @change="speichereFeld('vorgeschlagener_linktext', e.vorgeschlagener_linktext)" placeholder="z.B. Mehr erfahren (tba.)">
                </div>
            </div>

            <!-- Kontext für Linkeinbau (links) + Artikelthema (rechts) — gleiche Reihenfolge wie im Asana-Ticket -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                <div class="thx-form-field">
                    <label style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                        <span>Kontext für Linkeinbau</span>
                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:0.74rem;font-weight:400;color:var(--slate-600);text-transform:none;letter-spacing:0;">
                            <input type="checkbox" x-model="e.mit_anbieternennung_bool"
                                   @change="setzeAnbieternennung(e.mit_anbieternennung_bool)"
                                   style="margin:0;cursor:pointer;">
                            <span title="Wenn aktiv: KI darf den Kundennamen (Marke) im Kontext erwähnen. Wenn aus: KI hält den Kontext markenneutral. Manche Plattformen verbieten Markennennung.">
                                mit Anbieternennung
                            </span>
                        </label>
                    </label>
                    <textarea x-model.lazy="e.kontext_einbau" @change="speichereFeld('kontext_einbau', e.kontext_einbau)"
                              rows="3" placeholder="Konkreter Absatz, in dem der Link erscheinen soll (Zitat-/Beispiel-Format)"
                              style="resize:vertical;min-height:60px;"></textarea>
                </div>
                <div class="thx-form-field">
                    <label style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                        <span>Artikelthema</span>
                        <button type="button" @click.stop="vorschlaegeOeffnen()" :disabled="vorschlaegeLaeuft || !e.ziel_url"
                                :title="!e.ziel_url ? 'Linkziel erst eintragen' : 'KI-Vorschläge für Thema + Kontext'"
                                style="background:none;border:0;padding:0;cursor:pointer;color:var(--thoxan-600);font-size:0.78rem;display:flex;align-items:center;gap:3px;line-height:1;"
                                :style="(!e.ziel_url || vorschlaegeLaeuft) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                            <span x-show="!vorschlaegeLaeuft">✨</span>
                            <span x-show="vorschlaegeLaeuft" style="display:inline-block;animation:spin 1s linear infinite;">⏳</span>
                            <span x-text="vorschlaegeLaeuft ? 'denkt nach…' : 'KI-Vorschläge'"></span>
                        </button>
                    </label>
                    <textarea x-model.lazy="e.artikelthema" @change="speichereFeld('artikelthema', e.artikelthema)"
                              rows="3" placeholder="Themen-Brief: z.B. Anwendungsfälle für B2B-Apps, mind. 1.000 Wörter mit 2-3 Beispielen"
                              style="resize:vertical;min-height:60px;"></textarea>
                </div>
            </div>
            <div class="thx-form-field" style="margin-bottom:12px;">
                <label>Bemerkungen (Excel + Asana-Ticket)</label>
                <textarea x-model.lazy="e.notiz" @change="speichereFeld('notiz', e.notiz)" rows="2" placeholder="Hinweise, die im Excel an den Kunden und im Asana-Ticket erscheinen"></textarea>
            </div>

            <!-- Status-Pipeline-Buttons -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--slate-100);">
                <span style="font-size:0.7rem;color:var(--slate-500);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;">Status setzen</span>
                <button class="lam-btn lam-btn-small" @click="setzeStatus('kunde_freigegeben')"
                        :style="e.status === 'kunde_freigegeben' ? 'background:var(--emerald-100);color:var(--emerald-800);border-color:var(--emerald-300);' : 'background:var(--thoxan-50);color:var(--thoxan-700);border:1px solid var(--thoxan-200);'">
                    → Kunde freigegeben
                </button>
                <button class="lam-btn lam-btn-small" @click="setzeStatus('kunde_abgelehnt')"
                        :style="e.status === 'kunde_abgelehnt' ? 'background:var(--rose-100);color:var(--rose-800);border-color:var(--rose-300);' : 'background:var(--slate-100);color:var(--slate-700);border:1px solid var(--slate-200);'">
                    → Kunde abgelehnt
                </button>
                <span style="font-size:0.7rem;color:var(--slate-400);">weitere:</span>
                <template x-for="s in weitereStatus" :key="s">
                    <button class="lam-chip" :class="e.status === s ? 'is-active' : ''" @click="setzeStatus(s)" x-text="statusLabel(s)"></button>
                </template>
            </div>

            <!-- Korrespondenz (collapsible) -->
            <div style="margin-top:10px;border-top:1px dashed var(--slate-100);padding-top:8px;">
                <button @click="korrOffen = !korrOffen" style="background:none;border:0;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:var(--d-fs-sm);color:var(--slate-600);padding:4px 0;">
                    <span class="material-symbols-rounded" style="font-size:16px;">forum</span>
                    <strong>Korrespondenz</strong>
                    <span x-show="e.korr_count > 0" x-text="'(' + e.korr_count + ')'" style="color:var(--thoxan-700);"></span>
                    <span x-show="!e.korr_count" style="color:var(--slate-400);">noch keine</span>
                    <span style="margin-left:auto;color:var(--slate-400);" x-text="korrOffen ? '▴' : '▸'"></span>
                </button>
                <div x-show="korrOffen" x-cloak style="padding:6px 0 0 24px;font-size:var(--d-fs-sm);color:var(--slate-600);">
                    <a :href="'/lam/korrespondenz?vorschlagsliste_eintrag_id=' + encodeURIComponent(e.id)"
                       style="color:var(--thoxan-700);">→ Korrespondenz öffnen / Eintrag erfassen</a>
                </div>
            </div>

            <!-- Asana-Info -->
            <div style="margin-top:6px;border-top:1px dashed var(--slate-100);padding-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:var(--d-fs-sm);">
                <span class="material-symbols-rounded" style="font-size:16px;color:var(--slate-500);">task_alt</span>
                <strong style="color:var(--slate-600);">Asana</strong>
                <template x-if="e.asana_task_gid">
                    <span style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <a :href="'https://app.asana.com/0/0/' + e.asana_task_gid" target="_blank" rel="noopener" style="color:var(--thoxan-700);">→ Task öffnen ↗</a>
                        <button @click="asanaEntkoppeln(e.id)"
                                style="background:none;border:0;color:var(--rose-600);cursor:pointer;font-size:0.8rem;text-decoration:underline;">entkoppeln</button>
                    </span>
                </template>
                <template x-if="!e.asana_task_gid && asanaKonfiguriert">
                    <span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <button @click="asanaNeu(e)" class="lam-btn lam-btn-sm" style="background:#f06a6a;color:#fff;border-color:#f06a6a;font-size:0.78rem;">
                            📋 Neues Ticket
                        </button>
                        <button @click="asanaVerknuepfen(e.id)" class="lam-btn lam-btn-sm lam-btn-secondary" style="font-size:0.78rem;">
                            🔗 Bestehendes verknüpfen
                        </button>
                    </span>
                </template>
                <template x-if="!e.asana_task_gid && !asanaKonfiguriert">
                    <a href="/admin/settings?tab=asana" style="color:var(--amber-700);font-size:var(--d-fs-xs);">Asana global konfigurieren →</a>
                </template>
            </div>

            </div></div><!-- /aufgeklappt -->

            <!-- KI-Vorschläge-Modal pro Eintrag -->
            <div x-show="vorschlaegeOffen" x-cloak class="lam-modal-overlay" @click.self="vorschlaegeOffen = false">
                <div class="lam-modal" style="max-width:760px;width:100%;background:#fff;border-radius:8px;box-shadow:0 20px 50px rgba(0,0,0,0.18);max-height:85vh;display:flex;flex-direction:column;">
                    <div style="padding:16px 24px;border-bottom:1px solid var(--slate-100);display:flex;justify-content:space-between;align-items:center;">
                        <h3 style="margin:0;font-size:1rem;font-weight:600;color:var(--slate-800);">
                            ✨ KI-Vorschläge für <span x-text="e.domain_url" style="color:var(--thoxan-700);"></span>
                        </h3>
                        <button @click="vorschlaegeOffen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
                    </div>
                    <div style="padding:18px 24px;overflow-y:auto;flex:1;">
                        <template x-if="vorschlaegeLaeuft">
                            <div style="text-align:center;padding:40px;color:var(--slate-600);">
                                <div style="font-size:1.5rem;margin-bottom:10px;">⏳</div>
                                <div style="font-size:0.9rem;">KI analysiert Linkziel und Zielwebsite…</div>
                                <div style="font-size:0.75rem;color:var(--slate-400);margin-top:6px;">(Seiten werden geladen, Vorschläge generiert — kann 15–30 Sek dauern)</div>
                            </div>
                        </template>
                        <template x-if="!vorschlaegeLaeuft && vorschlaegeFehler">
                            <div style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:6px;padding:14px;font-size:0.85rem;color:var(--rose-700);" x-text="vorschlaegeFehler"></div>
                        </template>
                        <template x-if="!vorschlaegeLaeuft && !vorschlaegeFehler">
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                <div style="font-size:0.78rem;color:var(--slate-500);">
                                    Klick auf einen Vorschlag, um ihn in das Feld zu übernehmen. <em>„Nur Thema"</em> übernimmt nur den Themen-Brief, <em>„Thema + Kontext"</em> übernimmt beides zusammen.
                                </div>
                                <template x-for="(v, i) in vorschlaegeListe" :key="i">
                                    <div style="border:1px solid var(--slate-200);border-radius:6px;padding:14px;background:#fff;">
                                        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);font-weight:600;margin-bottom:4px;">Thema</div>
                                        <div style="font-size:0.92rem;color:var(--slate-800);margin-bottom:10px;line-height:1.5;" x-text="v.thema"></div>
                                        <div x-show="v.kontext">
                                            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--slate-500);font-weight:600;margin-bottom:4px;">Kontext (Linkeinbau)</div>
                                            <div style="font-size:0.88rem;color:var(--slate-700);background:var(--slate-50);border-left:3px solid var(--thoxan-300);padding:8px 12px;border-radius:0 4px 4px 0;line-height:1.55;font-style:italic;" x-text="v.kontext"></div>
                                        </div>
                                        <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                                            <button @click="vorschlagThemaUebernehmen(v)" class="lam-btn lam-btn-secondary lam-btn-small">Nur Thema</button>
                                            <button @click="vorschlagKontextUebernehmen(v)" class="lam-btn lam-btn-secondary lam-btn-small" :disabled="!v.kontext" :style="!v.kontext ? 'opacity:0.4;cursor:not-allowed;' : ''">Nur Kontext</button>
                                            <button @click="vorschlagUebernehmen(v)" class="lam-btn lam-btn-primary lam-btn-small">Beides übernehmen</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <div style="padding:12px 24px;border-top:1px solid var(--slate-100);background:var(--slate-50);display:flex;justify-content:space-between;align-items:center;">
                        <button @click="vorschlaegeOeffnen()" :disabled="vorschlaegeLaeuft"
                                style="background:none;border:0;color:var(--thoxan-600);font-size:0.82rem;cursor:pointer;" :style="vorschlaegeLaeuft ? 'opacity:0.4;' : ''">↻ Neu generieren</button>
                        <button @click="vorschlaegeOffen = false" class="lam-btn lam-btn-secondary">Schließen</button>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

  </main><!-- /.vl-main -->
</div><!-- /.vl-container -->

<!-- Domain-Hinzufügen-Modal -->
<div class="thx-modal-backdrop" x-show="addDom.offen" x-cloak @click.self="addDom.offen = false">
    <div class="thx-modal" style="max-width:680px;">
        <div class="thx-modal-header">
            <h2 class="thx-modal-title">Domain zur Liste hinzufügen</h2>
            <button class="thx-modal-close" @click="addDom.offen = false">×</button>
        </div>
        <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:14px;">
            <div class="thx-form-field">
                <label>Domain suchen (URL eingeben — Live-Suche)</label>
                <input type="text" x-model="addDom.suche" @input.debounce.300ms="suchePool()" placeholder="z.B. zahnarzt oder ratgeber.de">
            </div>
            <div x-show="addDom.suche.length >= 2" style="max-height:340px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;">
                <template x-for="d in addDom.treffer" :key="d.id">
                    <div @click="addDom.gewaehlt[d.id] = !addDom.gewaehlt[d.id]"
                         :style="addDom.gewaehlt[d.id] ? 'background:var(--thoxan-50);' : ''"
                         style="padding:8px 12px;border-bottom:1px solid var(--slate-100);cursor:pointer;display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" :checked="!!addDom.gewaehlt[d.id]" @click.stop="addDom.gewaehlt[d.id] = !addDom.gewaehlt[d.id]">
                        <div style="flex:1;">
                            <div style="font-weight:600;" x-text="d.url"></div>
                            <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                                <span x-text="d.anbieter_name || '—'"></span>
                                <span x-show="d.si_aktuell !== null"> · SI <span x-text="parseFloat(d.si_aktuell).toFixed(4)"></span></span>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="addDom.treffer.length === 0" style="padding:14px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
                    Keine Treffer.
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button class="lam-btn lam-btn-secondary" @click="addDom.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="fuegeGewaehlteHinzu()" :disabled="!anzahlGewaehlt() || addDom.laeuft">
                    <span x-show="!addDom.laeuft" x-text="anzahlGewaehlt() + ' Domain(s) hinzufügen'"></span>
                    <span x-show="addDom.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Listen-Bearbeiten-Modal -->
<div class="thx-modal-backdrop" x-show="editListe.offen" x-cloak @click.self="editListe.offen = false">
    <div class="thx-modal" style="max-width:520px;">
        <div class="thx-modal-header">
            <h2 class="thx-modal-title">Liste bearbeiten</h2>
            <button class="thx-modal-close" @click="editListe.offen = false">×</button>
        </div>
        <div class="thx-modal-body" style="display:flex;flex-direction:column;gap:12px;">
            <div class="thx-form-field"><label>Name *</label><input type="text" x-model="editListe.name"></div>
            <div class="thx-form-field">
                <label>Status</label>
                <select x-model="editListe.status">
                    <option value="entwurf">entwurf</option>
                    <option value="aktiv">aktiv</option>
                    <option value="abgeschlossen">abgeschlossen</option>
                    <option value="archiviert">archiviert</option>
                </select>
            </div>
            <div class="thx-form-field"><label>Zielzahl Backlinks</label><input type="number" x-model="editListe.zielzahl" placeholder="optional"></div>
            <div class="thx-form-field"><label>Notiz</label><textarea x-model="editListe.notiz" rows="3"></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button class="lam-btn lam-btn-secondary" @click="editListe.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereListe()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Asana-Vorschau/Verknüpfen-Modal -->
<div x-show="asanaModal.offen" x-cloak class="lam-modal-overlay"
     @click.self="asanaModal.offen = false">
    <div style="background:#fff;border-radius:10px;width:680px;max-width:calc(100% - 40px);max-height:calc(100vh - 120px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">
                <template x-if="asanaModal.modus === 'neu'"><span>📋 Asana-Ticket anlegen</span></template>
                <template x-if="asanaModal.modus === 'verknuepfen'"><span>🔗 Bestehendes Asana-Ticket verknüpfen</span></template>
            </h3>
            <button @click="asanaModal.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
        </div>
        <div style="padding:22px 24px;overflow-y:auto;flex:1;">
            <template x-if="asanaModal.laeuft && asanaModal.modus === 'neu' && !asanaModal.titel">
                <div style="text-align:center;color:var(--slate-500);padding:24px;">… Vorschau wird geladen</div>
            </template>

            <template x-if="asanaModal.modus === 'neu' && asanaModal.titel">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <p style="margin:0;font-size:0.85rem;color:var(--slate-600);">
                        So wird das Ticket angelegt. Du kannst Titel und Beschreibung vor dem Anlegen anpassen.
                    </p>
                    <div>
                        <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Titel</label>
                        <input type="text" x-model="asanaModal.titel"
                               style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.95rem;font-weight:600;">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:5px;">Beschreibung</label>
                        <textarea x-model="asanaModal.beschreibung" rows="14"
                                  style="width:100%;padding:10px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.85rem;font-family:inherit;line-height:1.5;resize:vertical;"></textarea>
                    </div>
                    <p style="margin:0;font-size:0.72rem;color:var(--slate-500);">
                        Das Ticket landet automatisch in der für den Kunden konfigurierten Linkoptionen-Section.
                    </p>
                </div>
            </template>

            <template x-if="asanaModal.modus === 'verknuepfen'">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <!-- Section-Auswahl -->
                    <div x-show="asanaModal.sections.length > 0">
                        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Asana-Section</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            <template x-for="s in asanaModal.sections" :key="s.gid">
                                <button type="button"
                                        @click="asanaModal.sectionGid = s.gid; asanaModalSuchen()"
                                        :style="asanaModal.sectionGid === s.gid ? 'background:var(--thoxan-600);color:#fff;border-color:var(--thoxan-600);' : 'background:#fff;color:var(--slate-700);border-color:var(--slate-300);'"
                                        style="padding:4px 10px;border:1px solid;border-radius:999px;cursor:pointer;font-size:0.75rem;">
                                    <span x-text="s.name"></span>
                                    <span x-show="s.ist_default" style="opacity:0.8;font-size:0.65rem;margin-left:4px;">★</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <!-- Suchfeld -->
                    <div>
                        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.03em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Suchen</div>
                        <input type="text" x-model="asanaModal.suche"
                               @keydown.enter.prevent="asanaModalSuchen()"
                               placeholder="Task-Name filtern (Enter)"
                               style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                    </div>
                    <!-- Trefferliste -->
                    <div style="border:1px solid var(--slate-200);border-radius:6px;max-height:340px;overflow-y:auto;background:#fff;">
                        <template x-if="asanaModal.trefferLaeuft">
                            <div style="padding:20px;text-align:center;color:var(--slate-500);font-size:0.85rem;">… lädt Tasks</div>
                        </template>
                        <template x-if="!asanaModal.trefferLaeuft && asanaModal.treffer.length === 0">
                            <div style="padding:20px;text-align:center;color:var(--slate-500);font-size:0.85rem;">Keine Tasks in dieser Section.</div>
                        </template>
                        <template x-for="t in asanaModal.treffer" :key="t.gid">
                            <button type="button" @click="asanaModalTaskWaehlen(t)" :disabled="asanaModal.laeuft"
                                    style="display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:10px 14px;border:0;background:#fff;border-bottom:1px solid var(--slate-100);cursor:pointer;text-align:left;font-size:0.9rem;"
                                    onmouseover="this.style.background='var(--slate-50)'"
                                    onmouseout="this.style.background='#fff'">
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:500;color:var(--slate-800);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="t.name"></div>
                                    <div style="font-size:0.7rem;color:var(--slate-500);margin-top:2px;">
                                        <span x-text="t.gid"></span>
                                        <span x-show="t.completed" style="margin-left:8px;color:var(--emerald-700);">✓ erledigt</span>
                                        <span x-show="t.due_on" style="margin-left:8px;">📅 <span x-text="t.due_on"></span></span>
                                    </div>
                                </div>
                                <span style="color:var(--thoxan-600);font-size:0.8rem;flex-shrink:0;">verknüpfen →</span>
                            </button>
                        </template>
                    </div>
                    <!-- Fallback: manuell URL/GID -->
                    <details style="font-size:0.8rem;color:var(--slate-600);">
                        <summary style="cursor:pointer;">Manuell URL/GID eintragen</summary>
                        <div style="margin-top:8px;display:flex;gap:8px;">
                            <input type="text" x-model="asanaModal.verknuepfungsEingabe"
                                   placeholder="https://app.asana.com/0/.../... oder GID"
                                   style="flex:1;padding:8px 10px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.85rem;">
                            <button @click="asanaModalVerknuepfen()" :disabled="asanaModal.laeuft || !asanaModal.verknuepfungsEingabe"
                                    class="lam-btn lam-btn-primary lam-btn-small">Verknüpfen</button>
                        </div>
                    </details>
                </div>
            </template>

            <div x-show="asanaModal.fehler" style="background:var(--rose-50);border:1px solid var(--rose-200);border-radius:6px;padding:10px 14px;font-size:0.85rem;color:var(--rose-700);margin-top:14px;" x-text="asanaModal.fehler"></div>
        </div>
        <div style="padding:14px 24px;border-top:1px solid var(--slate-100);background:var(--slate-50);display:flex;justify-content:flex-end;gap:10px;">
            <button @click="asanaModal.offen = false" class="lam-btn lam-btn-secondary">Abbrechen</button>
            <template x-if="asanaModal.modus === 'neu'">
                <button @click="asanaModalAnlegen()" :disabled="asanaModal.laeuft || !asanaModal.titel"
                        class="lam-btn" style="background:#f06a6a;color:#fff;border-color:#f06a6a;">
                    <span x-show="!asanaModal.laeuft">📋 Ticket in Asana anlegen</span>
                    <span x-show="asanaModal.laeuft">… legt an</span>
                </button>
            </template>
        </div>
    </div>
</div>

</div>

<style>
[x-cloak] { display: none !important; }
.lam-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 1000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 80px;
}
/* Karten: jeder Eintrag eine eigene Karte mit Luft + sanftem Rahmen */
.vl-eintrag {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 10px;
    padding: 20px 24px !important;
    margin: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: box-shadow 0.15s, border-color 0.15s;
}
.vl-eintrag:hover {
    border-color: var(--slate-300);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}
.vl-eintrag.is-entfernt { display: none; }
/* Container muss die Karten als separate Boxen mit Lücke layouten,
   nicht als angeklebte Reihen */
.vl-eintrag-liste {
    display: flex !important;
    flex-direction: column;
    gap: 14px;
    padding: 16px;
    background: var(--slate-50);
}
.lam-chip-status-in_planung { background: var(--slate-100); color: var(--slate-500); border: 1px dashed var(--slate-300); }
@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

/* Drag-Handle + Drop-Markierung */
.vl-drag-handle {
    cursor: grab;
    color: var(--slate-400);
    font-size: 0.95rem;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 4px;
    user-select: none;
    flex-shrink: 0;
}
.vl-drag-handle:hover { color: var(--thoxan-600); background: var(--slate-100); }
.vl-drag-handle:active { cursor: grabbing; }
.vl-eintrag.is-drop-above { box-shadow: inset 0 3px 0 0 var(--thoxan-500); }
.vl-eintrag.is-drop-below { box-shadow: inset 0 -3px 0 0 var(--thoxan-500); }

/* ───────── Sub-Sidebar (Filter & Sortierung) — Stil analog chat-sidebar ───────── */
.vl-container {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.vl-main {
    flex: 1;
    min-width: 0;
}
.vl-sidebar {
    width: 360px;
    min-width: 360px;
    flex-shrink: 0;
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 14px;
    max-height: calc(100vh - 100px);
}
.vl-sidebar-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--slate-200);
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-shrink: 0;
}
.vl-sidebar-content {
    overflow-y: auto;
    flex: 1;
    padding: 16px 14px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.vl-sidebar-content::-webkit-scrollbar { width: 6px; }
.vl-sidebar-content::-webkit-scrollbar-track { background: transparent; }
.vl-sidebar-content::-webkit-scrollbar-thumb { background: var(--slate-300); border-radius: 3px; }
.vl-sidebar-content::-webkit-scrollbar-thumb:hover { background: var(--slate-400); }

.vl-section { display: flex; flex-direction: column; gap: 6px; }
.vl-section-title {
    margin: 0 6px 4px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--slate-500);
    font-weight: 700;
}
.vl-filter-list { display: flex; flex-direction: column; gap: 2px; }
.vl-filter-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 7px 10px;
    background: transparent;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
    font-size: 0.85rem;
    color: var(--slate-700);
    transition: background 0.1s;
}
.vl-filter-row:hover { background: #fff; }
.vl-filter-row.is-active { background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,0.06); }
.vl-filter-row.is-active .vl-filter-row-label { color: var(--thoxan-700); font-weight: 600; }
.vl-filter-row-label { flex: 1; min-width: 0; }
.vl-filter-row-chip { flex-shrink: 0; }
.vl-filter-row-count {
    flex-shrink: 0;
    font-size: 0.72rem;
    color: var(--slate-500);
    background: var(--slate-100);
    padding: 1px 8px;
    border-radius: 999px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.vl-filter-row.is-active .vl-filter-row-count {
    background: var(--thoxan-100);
    color: var(--thoxan-700);
}

/* Bei schmalem Viewport (< 1100 px) Sidebar unter den Content */
@media (max-width: 1100px) {
    .vl-container { flex-direction: column; }
    .vl-sidebar { width: 100%; min-width: 0; position: relative; max-height: none; top: 0; }
}
.lam-chip-status-vorgeschlagen { background: var(--slate-100); color: var(--slate-700); }
.lam-chip-status-in_akquise { background: var(--amber-100); color: var(--amber-800); }
.lam-chip-status-bestaetigt { background: var(--emerald-100); color: var(--emerald-800); }
.lam-chip-status-abgelehnt { background: var(--rose-100); color: var(--rose-800); }
.lam-chip-status-ohne_antwort { background: var(--slate-100); color: var(--slate-500); }
.lam-chip-status-kunde_freigegeben { background: var(--emerald-200); color: var(--emerald-900); }
.lam-chip-status-kunde_abgelehnt { background: var(--rose-200); color: var(--rose-900); }
.lam-chip-status-abgeschlossen { background: var(--thoxan-100); color: var(--thoxan-700); }
</style>

<script>
const VL_LISTE = <?= json_encode([
    'id' => $liste['id'],
    'name' => $liste['name'],
    'status' => $liste['status'],
    'zielzahl' => $liste['zielzahl'],
    'notiz' => $liste['notiz'],
    'customer_id' => $liste['customer_id'],
], JSON_UNESCAPED_UNICODE) ?>;

function vlDetail() {
    return {
        addDom: { offen: false, suche: '', treffer: [], gewaehlt: {}, laeuft: false },
        editListe: { offen: false, name: '', status: '', zielzahl: '', notiz: '' },
        // Asana-Modal: „neu" (Vorschau) oder „verknuepfen" (Section + Suche + Tasks)
        asanaModal: {
            offen: false, modus: 'neu', laeuft: false, fehler: '',
            linkoptionId: '', titel: '', beschreibung: '', verknuepfungsEingabe: '',
            sections: [], sectionGid: '', suche: '', treffer: [], trefferLaeuft: false,
            onAngelegt: null, onVerknuepft: null,
        },
        async asanaVorschauOeffnen(linkoptionId, onAngelegt) {
            this.asanaModal = {
                offen: true, modus: 'neu', laeuft: true, fehler: '',
                linkoptionId, titel: '', beschreibung: '', verknuepfungsEingabe: '',
                onAngelegt, onVerknuepft: null,
            };
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-vorschau?linkoption_id=' + encodeURIComponent(linkoptionId), { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.asanaModal.titel = j.data.titel;
                this.asanaModal.beschreibung = j.data.beschreibung;
                // Original merken — beim Anlegen nur dann als Override schicken,
                // wenn Tom in der Vorschau tatsächlich editiert hat. Sonst Default-Pfad
                // im Backend → html_notes mit unterstrichenem Linktext.
                this.asanaModal.beschreibungOriginal = j.data.beschreibung;
            } catch (e) {
                this.asanaModal.fehler = e.message;
            } finally { this.asanaModal.laeuft = false; }
        },
        async asanaVerknuepfungOeffnen(linkoptionId, onVerknuepft) {
            this.asanaModal = {
                offen: true, modus: 'verknuepfen', laeuft: false, fehler: '',
                linkoptionId, titel: '', beschreibung: '', verknuepfungsEingabe: '',
                sections: [], sectionGid: '', suche: '', treffer: [], trefferLaeuft: false,
                onAngelegt: null, onVerknuepft,
            };
            // Sections laden + Default-Section vorauswählen → Tasks holen
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-sections?linkoption_id=' + encodeURIComponent(linkoptionId), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.asanaModal.sections = j.data || [];
                    const def = this.asanaModal.sections.find(s => s.ist_default);
                    if (def) this.asanaModal.sectionGid = def.gid;
                    this.asanaModalSuchen();
                }
            } catch (e) {}
        },
        async asanaModalSuchen() {
            this.asanaModal.trefferLaeuft = true;
            this.asanaModal.fehler = '';
            try {
                const p = new URLSearchParams({ linkoption_id: this.asanaModal.linkoptionId });
                if (this.asanaModal.suche) p.set('suche', this.asanaModal.suche);
                if (this.asanaModal.sectionGid) p.set('section_gid', this.asanaModal.sectionGid);
                const r = await fetch('/api/v1/lam/linkoption-asana-tasks?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) { this.asanaModal.fehler = j.error || j.message || 'Fehler.'; this.asanaModal.treffer = []; return; }
                this.asanaModal.treffer = j.data || [];
            } finally { this.asanaModal.trefferLaeuft = false; }
        },
        async asanaModalTaskWaehlen(task) {
            this.asanaModal.laeuft = true; this.asanaModal.fehler = '';
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-verknuepfen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: this.asanaModal.linkoptionId, task_gid_oder_url: task.gid }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                const cb = this.asanaModal.onVerknuepft;
                this.asanaModal.offen = false;
                if (typeof cb === 'function') cb(j.data);
            } catch (e) {
                this.asanaModal.fehler = e.message;
            } finally { this.asanaModal.laeuft = false; }
        },
        async asanaModalAnlegen() {
            this.asanaModal.laeuft = true; this.asanaModal.fehler = '';
            try {
                const body = {
                    linkoption_id: this.asanaModal.linkoptionId,
                    titel: this.asanaModal.titel,
                };
                // Nur als Override schicken, wenn Tom den Text wirklich editiert hat —
                // sonst nimmt das Backend das frisch gebaute html_notes (mit <u>Linktext</u>).
                if (this.asanaModal.beschreibung !== this.asanaModal.beschreibungOriginal) {
                    body.beschreibung = this.asanaModal.beschreibung;
                }
                const r = await fetch('/api/v1/lam/linkoption-asana-neu', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                const cb = this.asanaModal.onAngelegt;
                this.asanaModal.offen = false;
                if (typeof cb === 'function') cb(j.data);
            } catch (e) {
                this.asanaModal.fehler = e.message;
            } finally { this.asanaModal.laeuft = false; }
        },
        async asanaModalVerknuepfen() {
            const eingabe = (this.asanaModal.verknuepfungsEingabe || '').trim();
            if (!eingabe) { this.asanaModal.fehler = 'URL oder GID eintragen.'; return; }
            this.asanaModal.laeuft = true; this.asanaModal.fehler = '';
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-verknuepfen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: this.asanaModal.linkoptionId, task_gid_oder_url: eingabe }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                const cb = this.asanaModal.onVerknuepft;
                this.asanaModal.offen = false;
                if (typeof cb === 'function') cb(j.data);
            } catch (e) {
                this.asanaModal.fehler = e.message;
            } finally { this.asanaModal.laeuft = false; }
        },

        // Filter + Sortierung + Drag&Drop
        filterStatus: '',
        sortKey: 'position',
        draggedId: null,
        verschiebeLaeuft: false,
        counters: { gesamt: 0 },
        statusOrdnung: ['in_planung','vorgeschlagen','in_akquise','bestaetigt','kunde_freigegeben','kunde_abgelehnt','abgelehnt','ohne_antwort','abgeschlossen'],
        statusLabels: {
            in_planung: 'In Planung', vorgeschlagen: 'Vorgeschlagen', in_akquise: 'In Akquise',
            bestaetigt: 'Bestätigt', abgelehnt: 'Abgelehnt', ohne_antwort: 'Ohne Antwort',
            kunde_freigegeben: 'Kunde freigegeben', kunde_abgelehnt: 'Kunde abgelehnt',
            abgeschlossen: 'Abgeschlossen',
        },
        statusFarben: {
            in_planung:        { bg: 'var(--slate-100)',   fg: 'var(--slate-500)' },
            vorgeschlagen:     { bg: 'var(--slate-100)',   fg: 'var(--slate-700)' },
            in_akquise:        { bg: 'var(--amber-100)',   fg: 'var(--amber-800)' },
            bestaetigt:        { bg: 'var(--emerald-100)', fg: 'var(--emerald-800)' },
            kunde_freigegeben: { bg: 'var(--emerald-200)', fg: 'var(--emerald-900)' },
            kunde_abgelehnt:   { bg: 'var(--rose-200)',    fg: 'var(--rose-900)' },
            abgelehnt:         { bg: 'var(--rose-100)',    fg: 'var(--rose-800)' },
            ohne_antwort:      { bg: 'var(--slate-100)',   fg: 'var(--slate-500)' },
            abgeschlossen:     { bg: 'var(--thoxan-100)',  fg: 'var(--thoxan-700)' },
        },
        statusChipAktiv(s) {
            const f = this.statusFarben[s] || { bg: 'var(--thoxan-600)', fg: '#fff' };
            return `background:${f.bg};color:${f.fg};border-color:${f.bg};`;
        },
        statusChipInaktiv(s) { return 'background:#fff;color:var(--slate-700);border-color:var(--slate-300);'; },
        sortOptionen: [
            { key: 'position',     label: 'Position (Default)' },
            { key: 'status',       label: 'Status (Pipeline-Reihenfolge)' },
            { key: 'status_desc',  label: 'Status (rückwärts)' },
            { key: 'domain',       label: 'Domain A–Z' },
            { key: 'preis',        label: 'Preis aufsteigend' },
            { key: 'preis_desc',   label: 'Preis absteigend' },
        ],

        init() {
            window.__vlDetailRoot = this;
            // Counters initial setzen + Anzeige aktualisieren
            this.$nextTick(() => { this.aktualisiereCounters(); this.aktualisiereAnzeige(); });
        },

        _alleEintraege() {
            return Array.from(document.querySelectorAll('.vl-eintrag'))
                .map(el => Alpine.$data(el))
                .filter(Boolean);
        },
        alleAufklappen() { this._alleEintraege().forEach(d => { d.aufgeklappt = true; }); },
        alleEinklappen() { this._alleEintraege().forEach(d => { d.aufgeklappt = false; }); },

        // ===== Massenbearbeitung (Auswahl per Checkbox) =====
        // Auswahl im Root gehalten; die Eintrags-Checkboxen lesen/schreiben ueber
        // window.__vlDetailRoot. Set wird bei jeder Aenderung NEU zugewiesen -> Alpine-reaktiv.
        auswahl: new Set(),
        bulkStatusWert: '',
        bulkLaeuft: false,
        // Checkbox-Zustand liegt lokal am Eintrag (ausgewaehlt) — hier nur die ID-Menge spiegeln.
        setAuswahl(id, an) {
            const s = new Set(this.auswahl);
            an ? s.add(id) : s.delete(id);
            this.auswahl = s;
        },
        sichtbareAnzahl() {
            return this.filterStatus ? (this.counters[this.filterStatus] || 0) : (this.counters.gesamt || 0);
        },
        alleSichtbarWaehlen() {
            const s = new Set(this.auswahl);
            this._alleEintraege().filter(d => d._sichtbar !== false && !d.entfernt).forEach(d => {
                d.ausgewaehlt = true;   // lokal reaktiv -> Checkbox schaltet um
                s.add(d.e.id);
            });
            this.auswahl = s;
        },
        auswahlAufheben() {
            this._alleEintraege().forEach(d => { d.ausgewaehlt = false; });
            this.auswahl = new Set();
        },
        _gewaehlteEintraege() {
            return this._alleEintraege().filter(d => this.auswahl.has(d.e && d.e.id) && !d.entfernt);
        },
        bulkOeffnen() {
            const urls = [...new Set(this._gewaehlteEintraege()
                .map(d => window.extUrl ? window.extUrl(d.e.domain_url) : ('https://' + d.e.domain_url)))];
            if (urls.length === 0) return;
            if (urls.length > 30 && !confirm(urls.length + ' Links in neuen Tabs öffnen?')) return;
            // Popup-Blocker: der Browser fragt hoechstens EINMAL pro Seite nach der Erlaubnis
            // ("mehrere Tabs zulassen?"). Danach oeffnen sich alle. Bis dahin ggf. "URLs kopieren".
            urls.forEach(u => window.open(u, '_blank', 'noopener'));
        },
        bulkKopieren() {
            const urls = [...new Set(this._gewaehlteEintraege()
                .map(d => window.extUrl ? window.extUrl(d.e.domain_url) : ('https://' + d.e.domain_url)))];
            if (urls.length === 0) return;
            const txt = urls.join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(txt).then(() => alert(urls.length + ' URLs kopiert.'));
            else prompt('URLs (kopieren mit Strg/Cmd+C):', txt);
        },
        async bulkStatus() {
            const ids = [...this.auswahl];
            if (ids.length === 0 || !this.bulkStatusWert) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, aktion: 'status_setzen', wert: this.bulkStatusWert }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                const wert = this.bulkStatusWert;
                this._gewaehlteEintraege().forEach(d => { d.e.status = wert; });
                this.bulkStatusWert = '';
                this.auswahlAufheben();
                this.aktualisiereCounters();
                this.aktualisiereAnzeige();
            } catch (e) { alert('Status setzen fehlgeschlagen: ' + e.message); }
            finally { this.bulkLaeuft = false; }
        },
        async bulkLoeschen() {
            const ids = [...this.auswahl];
            if (ids.length === 0) return;
            if (!confirm(ids.length + ' Einträge aus der Liste entfernen?\n\n(Die Domains bleiben im Pool, nur die Listen-Einträge werden gelöscht.)')) return;
            this.bulkLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids, aktion: 'loeschen' }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Entfernte Eintraege lokal ausblenden (kein Reload noetig).
                this._gewaehlteEintraege().forEach(d => { d.entfernt = true; });
                this.auswahlAufheben();
                this.aktualisiereCounters();
                this.aktualisiereAnzeige();
            } catch (e) { alert('Entfernen fehlgeschlagen: ' + e.message); }
            finally { this.bulkLaeuft = false; }
        },

        async verschiebeEintrag(srcId, dstId, einfuegenOberhalb) {
            if (this.verschiebeLaeuft) return;
            this.verschiebeLaeuft = true;
            // Aktuelle Reihenfolge nach position sortieren (nur die sichtbaren, nicht-entfernten)
            const alle = this._alleEintraege()
                .filter(d => !d.entfernt)
                .slice()
                .sort((a, b) => (parseInt(a.e.position)||0) - (parseInt(b.e.position)||0));
            const src = alle.find(d => d.e.id === srcId);
            if (!src) { this.verschiebeLaeuft = false; return; }
            const reduziert = alle.filter(d => d.e.id !== srcId);
            const dstIdx = reduziert.findIndex(d => d.e.id === dstId);
            if (dstIdx < 0) { this.verschiebeLaeuft = false; return; }
            const insertAt = einfuegenOberhalb ? dstIdx : dstIdx + 1;
            reduziert.splice(insertAt, 0, src);
            // Neue position-Werte vergeben (1..n) und _sortOrder aktualisieren
            const ids = [];
            reduziert.forEach((d, i) => {
                d.e.position = i + 1;
                d._sortOrder = i;
                ids.push(d.e.id);
            });
            try {
                const r = await fetch('/api/v1/lam/linkoption-reorder', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ liste_id: VL_LISTE.id, ids }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
            } catch (err) {
                alert('Reihenfolge speichern fehlgeschlagen: ' + err.message);
                // Rollback per Reload — am sichersten
                location.reload();
            } finally {
                this.verschiebeLaeuft = false;
            }
        },
        aktualisiereCounters() {
            const c = { gesamt: 0 };
            this.statusOrdnung.forEach(s => c[s] = 0);
            this._alleEintraege().forEach(d => {
                if (d.entfernt) return;
                c.gesamt++;
                const s = d.e?.status || 'in_planung';
                c[s] = (c[s] || 0) + 1;
            });
            this.counters = c;
        },
        aktualisiereAnzeige() {
            const eintraege = this._alleEintraege();
            // Filter
            eintraege.forEach(d => {
                d._sichtbar = (!this.filterStatus || d.e?.status === this.filterStatus);
            });
            // Sortierung über CSS-order
            const ordnung = this.statusOrdnung;
            const sortiert = eintraege.slice().sort((a, b) => {
                if (!a._sichtbar && b._sichtbar) return 1;
                if (a._sichtbar && !b._sichtbar) return -1;
                switch (this.sortKey) {
                    case 'status':       return (ordnung.indexOf(a.e.status) - ordnung.indexOf(b.e.status)) || ((a.e.position||0) - (b.e.position||0));
                    case 'status_desc':  return (ordnung.indexOf(b.e.status) - ordnung.indexOf(a.e.status)) || ((a.e.position||0) - (b.e.position||0));
                    case 'domain':       return (a.e.domain_url || '').localeCompare(b.e.domain_url || '');
                    case 'preis':        return (parseFloat(a.e.preis_kunde) || 99999) - (parseFloat(b.e.preis_kunde) || 99999);
                    case 'preis_desc':   return (parseFloat(b.e.preis_kunde) || -1) - (parseFloat(a.e.preis_kunde) || -1);
                    case 'position':
                    default:             return (a.e.position||0) - (b.e.position||0);
                }
            });
            sortiert.forEach((d, i) => { d._sortOrder = i; });
        },

        oeffneDomainHinzufuegen() {
            this.addDom = { offen: true, suche: '', treffer: [], gewaehlt: {}, laeuft: false };
        },
        async suchePool() {
            if (this.addDom.suche.length < 2) { this.addDom.treffer = []; return; }
            const p = new URLSearchParams({ suche: this.addDom.suche, limit: 30 });
            const r = await fetch('/api/v1/lam/linkquellen?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            this.addDom.treffer = j.success ? (j.data.rows || []) : [];
        },
        anzahlGewaehlt() { return Object.values(this.addDom.gewaehlt).filter(Boolean).length; },
        async fuegeGewaehlteHinzu() {
            const ids = Object.keys(this.addDom.gewaehlt).filter(k => this.addDom.gewaehlt[k]);
            if (ids.length === 0) return;
            this.addDom.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/vorschlagsliste-eintrag-add', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ vorschlagsliste_id: VL_LISTE.id, domain_ids: ids }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                location.reload();
            } catch (e) { alert('Fehler: ' + e.message); }
            this.addDom.laeuft = false;
        },

        oeffneListeBearbeiten() {
            this.editListe = { offen: true, name: VL_LISTE.name, status: VL_LISTE.status, zielzahl: VL_LISTE.zielzahl || '', notiz: VL_LISTE.notiz || '' };
        },
        async speichereListe() {
            try {
                const r = await fetch('/api/v1/lam/vorschlagsliste-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: VL_LISTE.id, customer_id: VL_LISTE.customer_id,
                        name: this.editListe.name, status: this.editListe.status,
                        zielzahl: this.editListe.zielzahl || null, notiz: this.editListe.notiz || null,
                    }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                location.reload();
            } catch (e) { alert('Fehler: ' + e.message); }
        },
        async loescheListe() {
            if (!confirm('Liste „' + VL_LISTE.name + '" wirklich löschen?')) return;
            try {
                const r = await fetch('/api/v1/lam/vorschlagsliste-loeschen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: VL_LISTE.id }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                location.href = '/lam/vorschlagslisten';
            } catch (e) { alert('Fehler: ' + e.message); }
        },
    };
}

function vlEintrag(eintragData) {
    // mit_anbieternennung kommt als TINYINT(0|1) — für Alpine x-model brauchen wir Boolean
    eintragData.mit_anbieternennung_bool = parseInt(eintragData.mit_anbieternennung || 0) === 1;
    return {
        e: eintragData,
        aufgeklappt: false,
        korrOffen: false,
        entfernt: false,
        ausgewaehlt: false,   // Massenbearbeitung — lokal reaktiv, Root spiegelt es in seine Auswahl-Menge
        _sichtbar: true,
        _sortOrder: 0,
        dropZielOben: false,
        dropZielUnten: false,
        vorschlaegeLaeuft: false,
        vorschlaegeOffen: false,
        vorschlaegeFehler: '',
        vorschlaegeListe: [],
        weitereStatus: ['in_planung', 'vorgeschlagen', 'in_akquise', 'bestaetigt', 'abgelehnt', 'ohne_antwort', 'abgeschlossen'],
        asanaKonfiguriert: <?= !empty(\Core\Settings::get('asana_pat')) ? 'true' : 'false' ?>,
        statusLabels: {
            in_planung: 'In Planung',
            vorgeschlagen: 'Vorgeschlagen', in_akquise: 'In Akquise', bestaetigt: 'Bestätigt',
            abgelehnt: 'Abgelehnt', ohne_antwort: 'Ohne Antwort',
            kunde_freigegeben: 'Kunde freigegeben', kunde_abgelehnt: 'Kunde abgelehnt', abgeschlossen: 'Abgeschlossen',
        },
        statusLabel(s) { return this.statusLabels[s] || s; },

        init() {
            // Auto-Aufklappen wenn URL-Anker auf diesen Eintrag zeigt (#eintrag-{id})
            if (window.location.hash === '#eintrag-' + this.e.id) {
                this.aufgeklappt = true;
                setTimeout(() => {
                    document.getElementById('eintrag-' + this.e.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        },

        formatDatum(d) {
            if (!d) return '';
            try { return new Date(d).toLocaleDateString('de-DE'); } catch (e) { return d; }
        },

        // === Asana ===
        // ruft das Modal im vlDetail-Wurzel auf (globaler Hook, weil $root vom Eintrag
        // aus nur auf das eigene <article> zeigt).
        asanaNeu(eintrag) {
            const self = this;
            if (!window.__vlDetailRoot) { alert('Bereitschaft noch nicht initialisiert, bitte Reload.'); return; }
            window.__vlDetailRoot.asanaVorschauOeffnen(eintrag.id, (data) => {
                self.e.asana_task_gid = data.task_gid;
                if (data.permalink_url) window.open(data.permalink_url, '_blank');
            });
        },
        asanaVerknuepfen(linkoptionId) {
            const self = this;
            if (!window.__vlDetailRoot) { alert('Bereitschaft noch nicht initialisiert, bitte Reload.'); return; }
            window.__vlDetailRoot.asanaVerknuepfungOeffnen(linkoptionId, (data) => {
                self.e.asana_task_gid = data.gid || data.task_gid || '';
                // Felder, die das Backend aus der Ticket-Beschreibung extrahiert + befüllt hat
                const importiert = data._importiert || {};
                const feldLabels = {
                    preis_kunde: 'Preis Kunde', beispielartikel_url: 'Beispielartikel',
                    ziel_url: 'Linkziel', vorgeschlagener_linktext: 'Linktext',
                    kontext_einbau: 'Kontext für Linkeinbau', artikelthema: 'Artikelthema',
                    notiz: 'Bemerkungen',
                };
                const uebernommen = [];
                Object.keys(importiert).forEach(feld => {
                    self.e[feld] = importiert[feld];
                    uebernommen.push(feldLabels[feld] || feld);
                });
                if (uebernommen.length > 0) {
                    self.aufgeklappt = true;
                    self.zeigeImportHinweis(uebernommen);
                }
            });
        },
        // Kurzer In-Card-Hinweis: welche Felder aus dem Ticket übernommen wurden
        importHinweis: '',
        zeigeImportHinweis(felder) {
            this.importHinweis = 'Aus dem Asana-Ticket übernommen: ' + felder.join(', ');
            setTimeout(() => { this.importHinweis = ''; }, 7000);
        },
        async asanaEntkoppeln(linkoptionId) {
            if (!confirm('Asana-Verknüpfung entfernen?')) return;
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-entkoppeln', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: linkoptionId }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.e.asana_task_gid = null;
            } catch (err) { alert('Entkoppeln fehlgeschlagen: ' + err.message); }
        },

        async speichereFeld(feld, wert) {
            try {
                const r = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.e.id, feld, wert: wert === '' ? null : wert }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } catch (err) { alert('Speichern fehlgeschlagen: ' + err.message); }
        },
        // ─── Drag & Drop für Reihenfolge ───
        kannVerschieben() {
            const root = window.__vlDetailRoot;
            return root && root.sortKey === 'position' && !root.filterStatus;
        },
        onDragStart(ev) {
            const root = window.__vlDetailRoot;
            if (!root) return;
            root.draggedId = this.e.id;
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', this.e.id);
            // ghost-Drag des ganzen Articles statt nur des Handles
            const article = ev.target.closest('article.vl-eintrag');
            if (article) ev.dataTransfer.setDragImage(article, 20, 20);
        },
        onDragEnd() {
            const root = window.__vlDetailRoot;
            if (root) root.draggedId = null;
            // visuelle Markierungen aller Einträge entfernen
            document.querySelectorAll('.vl-eintrag').forEach(el => {
                const d = Alpine.$data(el);
                if (d) { d.dropZielOben = false; d.dropZielUnten = false; }
            });
        },
        onDragOver(ev) {
            const root = window.__vlDetailRoot;
            if (!root || !root.draggedId || root.draggedId === this.e.id) return;
            ev.dataTransfer.dropEffect = 'move';
            // Drop oberhalb wenn Maus in der oberen Hälfte
            const rect = ev.currentTarget.getBoundingClientRect();
            const oben = (ev.clientY - rect.top) < rect.height / 2;
            this.dropZielOben = oben;
            this.dropZielUnten = !oben;
        },
        onDragLeave(ev) {
            // nur tatsächlich verlassen, nicht beim Wechsel auf Kindelement
            if (ev.currentTarget.contains(ev.relatedTarget)) return;
            this.dropZielOben = false;
            this.dropZielUnten = false;
        },
        async onDrop(ev) {
            const root = window.__vlDetailRoot;
            if (!root || !root.draggedId || root.draggedId === this.e.id) return;
            const srcId = root.draggedId;
            const dstId = this.e.id;
            const oben = this.dropZielOben;
            this.dropZielOben = false; this.dropZielUnten = false;
            await root.verschiebeEintrag(srcId, dstId, oben);
        },

        setzeAnbieternennung(neu) {
            const wert = neu ? 1 : 0;
            this.e.mit_anbieternennung = wert;
            this.speichereFeld('mit_anbieternennung', wert);
        },

        async vorschlaegeOeffnen() {
            if (this.vorschlaegeLaeuft) return;
            this.vorschlaegeLaeuft = true;
            this.vorschlaegeFehler = '';
            this.vorschlaegeListe = [];
            this.vorschlaegeOffen = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-artikelthemen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: this.e.id }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.vorschlaegeListe = (j.data && j.data.vorschlaege) || [];
                if (this.vorschlaegeListe.length === 0) this.vorschlaegeFehler = 'Keine Vorschläge zurückgekommen.';
            } catch (err) {
                this.vorschlaegeFehler = err.message;
            } finally { this.vorschlaegeLaeuft = false; }
        },
        vorschlagUebernehmen(v) {
            this.e.artikelthema = v.thema || '';
            this.e.kontext_einbau = v.kontext || '';
            this.speichereFeld('artikelthema', this.e.artikelthema);
            this.speichereFeld('kontext_einbau', this.e.kontext_einbau);
            this.vorschlaegeOffen = false;
        },
        vorschlagThemaUebernehmen(v) {
            this.e.artikelthema = v.thema || '';
            this.speichereFeld('artikelthema', this.e.artikelthema);
            this.vorschlaegeOffen = false;
        },
        vorschlagKontextUebernehmen(v) {
            this.e.kontext_einbau = v.kontext || '';
            this.speichereFeld('kontext_einbau', this.e.kontext_einbau);
            this.vorschlaegeOffen = false;
        },
        async setzeStatus(neuerStatus) {
            const alt = this.e.status;
            this.e.status = neuerStatus;
            try {
                const r = await fetch('/api/v1/lam/linkoption-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.e.id, feld: 'status', wert: neuerStatus }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                if (window.__vlDetailRoot) {
                    window.__vlDetailRoot.aktualisiereCounters();
                    window.__vlDetailRoot.aktualisiereAnzeige();
                }
            } catch (err) { this.e.status = alt; alert('Status-Wechsel fehlgeschlagen: ' + err.message); }
        },
        async entferneEintrag() {
            if (!confirm('Eintrag „' + this.e.domain_url + '" aus der Liste entfernen?\n\n(Die Domain bleibt im Pool, nur der Listen-Eintrag wird gelöscht.)')) return;
            try {
                const r = await fetch('/api/v1/lam/linkoption-bulk', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [this.e.id], aktion: 'loeschen' }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.entfernt = true;
                if (window.__vlDetailRoot) {
                    window.__vlDetailRoot.aktualisiereCounters();
                    window.__vlDetailRoot.aktualisiereAnzeige();
                }
            } catch (err) { alert('Entfernen fehlgeschlagen: ' + err.message); }
        },
    };
}
</script>
