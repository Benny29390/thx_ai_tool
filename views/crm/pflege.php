<?php $activeModul = 'pflege'; ?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<style>
/* Hauptgrid analog Chat/Wissen: sticky Sidebar links + Wizard rechts */
.pflege-shell { display: grid; grid-template-columns: 280px 1fr; gap: 18px; align-items: start; }
@media (max-width: 900px) { .pflege-shell { grid-template-columns: 1fr; } }

/* Kategorien-Buttons im .thx-shell-side: gleicher Style wie .thx-shell-btn */
.pflege-kat-btn {
    width: 100%;
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    border-radius: 6px;
    background: transparent;
    border: 1px solid transparent;
    color: var(--slate-700);
    font: inherit; font-size: 0.82rem;
    cursor: pointer; text-align: left;
    transition: background 0.1s;
}
.pflege-kat-btn:hover { background: var(--slate-100); }
.pflege-kat-btn.is-active {
    background: var(--thoxan-50);
    border-color: var(--thoxan-300);
    color: var(--thoxan-800);
    font-weight: 600;
}
.pflege-kat-btn .material-symbols-rounded { font-size: 16px; flex-shrink: 0; }
.pflege-kat-count {
    margin-left: auto;
    font-size: 0.7rem;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--slate-200);
    color: var(--slate-700);
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}
.pflege-kat-btn.is-schwere-hoch .pflege-kat-count { background: var(--rose-100); color: var(--rose-800); }
.pflege-kat-btn.is-schwere-mittel .pflege-kat-count { background: var(--amber-100); color: var(--amber-800); }
.pflege-kat-btn.is-active .pflege-kat-count { background: var(--thoxan-200); color: var(--thoxan-800); }

/* Wizard-Bereich */
.pflege-wizard {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    min-height: 500px;
    display: flex; flex-direction: column;
}
.pflege-wizard-head {
    padding: 14px 20px;
    border-bottom: 1px solid var(--slate-200);
    display: flex; align-items: center; gap: 14px;
    background: var(--slate-50);
    border-radius: 8px 8px 0 0;
}
.pflege-wizard-progress {
    display: flex; flex-direction: column; gap: 4px; flex: 1;
}
.pflege-wizard-progress-titel { font-weight: 700; font-size: 0.95rem; color: var(--slate-900); }
.pflege-wizard-progress-bar {
    height: 4px; background: var(--slate-200); border-radius: 2px; overflow: hidden;
}
.pflege-wizard-progress-bar > div {
    height: 100%; background: var(--thoxan-500);
    transition: width 0.2s;
}
.pflege-wizard-counter {
    font-size: 0.78rem; color: var(--slate-500);
    font-variant-numeric: tabular-nums;
}

.pflege-wizard-body {
    padding: 24px;
    flex: 1;
    overflow-y: auto;
}
.pflege-wizard-foot {
    padding: 14px 20px;
    border-top: 1px solid var(--slate-200);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: #fff;
    border-radius: 0 0 8px 8px;
    flex-wrap: wrap;
}
.pflege-wizard-hotkeys {
    font-size: 0.7rem; color: var(--slate-500);
    display: flex; gap: 14px; flex-wrap: wrap;
}
.pflege-wizard-hotkeys kbd {
    background: var(--slate-100);
    border: 1px solid var(--slate-300);
    border-radius: 3px;
    padding: 1px 6px;
    font-family: inherit; font-size: 0.7rem;
    margin-right: 4px;
}
.pflege-wizard-actions { display: flex; gap: 6px; }

/* Issue-Body: Merge-Vergleich */
.pflege-vergleich {
    overflow-x: auto;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
}
.pflege-vergleich-tab { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.pflege-vergleich-tab th, .pflege-vergleich-tab td {
    padding: 7px 10px;
    text-align: left;
    border-bottom: 1px solid var(--slate-200);
    vertical-align: top;
}
.pflege-vergleich-tab th {
    background: var(--slate-100);
    font-weight: 600; color: var(--slate-700);
    position: sticky; top: 0;
}
.pflege-vergleich-tab th.is-master {
    background: var(--thoxan-50); color: var(--thoxan-700);
    border-bottom: 2px solid var(--thoxan-400);
}
.pflege-vergleich-tab td.is-master { background: var(--thoxan-50); }
.pflege-vergleich-tab th.is-ki {
    background: var(--amber-50); color: var(--amber-800);
    border-bottom: 2px solid var(--amber-400);
}
.pflege-vergleich-tab td.is-ki { background: #fffbeb; }
.pflege-cell.is-selected-ki {
    background: var(--amber-100); border-color: var(--amber-400); color: var(--amber-800); font-weight: 600;
}
.pflege-label {
    width: 150px; min-width: 150px;
    font-weight: 600; color: var(--slate-700);
    background: var(--slate-50);
}
.pflege-cell {
    cursor: pointer; padding: 4px 6px; border-radius: 4px;
    border: 1px solid transparent; max-width: 280px; word-break: break-word;
}
.pflege-cell:hover { background: var(--thoxan-50); border-color: var(--thoxan-300); }
.pflege-cell.is-selected {
    background: var(--emerald-50); border-color: var(--emerald-400); color: var(--emerald-800); font-weight: 600;
}
.pflege-cell.is-empty { color: var(--slate-400); font-style: italic; }
.pflege-master-radio {
    padding: 5px 10px; border-radius: 6px; cursor: pointer;
    border: 1px solid var(--slate-300); background: #fff;
    font-size: 0.74rem; font-weight: 500;
}
.pflege-master-radio:hover {
    background: var(--thoxan-50); border-color: var(--thoxan-400); color: var(--thoxan-700);
}
.pflege-master-input {
    width: 100%; box-sizing: border-box;
    padding: 5px 8px;
    border: 1px solid var(--thoxan-300);
    border-radius: 4px;
    background: #fff;
    font: inherit; font-size: 0.85rem;
    color: var(--slate-900);
}
.pflege-master-input:focus {
    outline: none;
    border-color: var(--thoxan-600);
    box-shadow: 0 0 0 2px rgba(0,76,155,0.15);
}
.pflege-master-input::placeholder { color: var(--slate-400); }

/* Einfache-Issue-Body (Fehlt-X, Format-X) */
.pflege-simple-issue {
    text-align: center;
    padding: 20px 0;
}
.pflege-simple-issue-frage {
    font-size: 1.1rem; color: var(--slate-700);
    margin-bottom: 18px;
}
.pflege-simple-issue-frage strong { color: var(--slate-900); }
.pflege-simple-issue-input {
    width: 100%; max-width: 460px;
    padding: 10px 14px; font-size: 1rem;
    border: 1px solid var(--slate-300); border-radius: 6px;
    text-align: center;
}
.pflege-simple-issue-input:focus {
    border-color: var(--thoxan-500); outline: none;
    box-shadow: 0 0 0 3px rgba(0,76,155,0.1);
}
.pflege-vorschlag {
    margin-top: 12px;
    font-size: 0.85rem;
    color: var(--slate-500);
}
.pflege-vorschlag-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px;
    background: var(--amber-50); color: var(--amber-800);
    border: 1px solid var(--amber-300); border-radius: 6px;
    font-size: 0.78rem; cursor: pointer;
    font-family: inherit;
}
.pflege-vorschlag-btn:hover { background: var(--amber-100); }

.pflege-empty-state {
    text-align: center; padding: 60px 20px; color: var(--slate-500);
}
.pflege-empty-state .material-symbols-rounded { font-size: 56px; color: var(--emerald-300); }
.pflege-empty-state-titel { font-size: 1.1rem; margin: 14px 0 6px; color: var(--slate-700); font-weight: 600; }

.pflege-spin { animation: pflege-spin 1s linear infinite; }
@keyframes pflege-spin { to { transform: rotate(360deg); } }

/* Bildersuche-Modal */
.pflege-bilder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}
.pflege-bild-kachel {
    cursor: pointer;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    overflow: hidden;
    background: var(--slate-50);
    display: flex;
    flex-direction: column;
    transition: transform 0.1s, box-shadow 0.1s, border-color 0.1s;
}
.pflege-bild-kachel:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-color: var(--thoxan-400);
}
.pflege-bild-wrap {
    position: relative;
    width: 100%;
    padding-bottom: 100%; /* quadratisch */
    background: var(--slate-100);
    overflow: hidden;
}
.pflege-bild-wrap img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pflege-bild-quelle {
    padding: 5px 7px;
    font-size: 0.7rem;
    color: var(--slate-600);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* LinkedIn-Kandidaten-Auswahl */
.pflege-li-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.pflege-li-card {
    cursor: pointer;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    display: flex;
    gap: 12px;
    padding: 10px;
    transition: transform 0.1s, box-shadow 0.1s, border-color 0.1s;
}
.pflege-li-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: var(--thoxan-400);
}
.pflege-li-thumb {
    flex: none;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--slate-100);
    overflow: hidden;
    position: relative;
}
.pflege-li-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pflege-li-thumb .placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--slate-400);
    font-size: 1.5rem;
}
.pflege-li-info {
    flex: 1;
    min-width: 0;
}
.pflege-li-title {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--slate-800);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
}
.pflege-li-desc {
    margin-top: 4px;
    font-size: 0.78rem;
    color: var(--slate-600);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.35;
}
.pflege-li-url {
    margin-top: 6px;
    font-size: 0.72rem;
    color: var(--thoxan-600);
    word-break: break-all;
}

/* "Firma fehlt"-Wizard: 4 Optionen als Karten */
.ff-option {
    background: #fff;
    border: 1.5px solid var(--slate-200);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: border-color 0.1s, background 0.1s;
}
.ff-option:hover { border-color: var(--thoxan-300); }
.ff-option.is-active { border-color: var(--thoxan-500); background: var(--thoxan-50); }
.ff-option-head { display: flex; align-items: center; gap: 8px; font-size: 0.92rem; color: var(--slate-800); }
.ff-option-head .material-symbols-rounded { font-size: 20px; color: var(--thoxan-600); }
.ff-option-titel { font-weight: 600; }
.ff-option-body { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--slate-200); }

.ff-firma-pill {
    display: block; width: 100%; text-align: left;
    padding: 6px 10px; margin-bottom: 3px;
    background: #fff; border: 1px solid var(--slate-200); border-radius: 5px;
    font: inherit; font-size: 0.82rem; color: var(--slate-700);
    cursor: pointer;
}
.ff-firma-pill:hover { background: var(--slate-50); }
.ff-firma-pill.is-selected { background: var(--emerald-50); border-color: var(--emerald-400); color: var(--emerald-800); }

.ff-typ-pill {
    padding: 3px 10px; border-radius: 999px;
    background: var(--slate-50); border: 1px solid var(--slate-300);
    font: inherit; font-size: 0.78rem; color: var(--slate-700);
    cursor: pointer;
}
.ff-typ-pill:hover { background: var(--slate-100); }
.ff-typ-pill.is-selected { background: var(--thoxan-600); color: #fff; border-color: var(--thoxan-700); }
</style>

<div x-data="crmPflege()" x-init="init()" x-cloak class="crm-detail-root"
     @keydown.window="handleHotkey($event)">

    <div class="thx-page-header">
        <div>
            <h1 class="thx-page-title">Datenpflege</h1>
            <div class="thx-page-subtitle">
                <span x-text="totalIssues + ' offene Issues'"></span> ·
                <span x-show="letzterScan">Letzter Scan: <span x-text="letzterScan"></span></span>
                <span x-show="!letzterScan">noch nicht gescannt</span>
            </div>
        </div>
        <div class="thx-page-actions">
            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="scanJetzt()" :disabled="scannt">
                <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;" :class="scannt ? 'pflege-spin' : ''">refresh</span>
                <span x-text="scannt ? 'Scannt …' : 'Erneut scannen'"></span>
            </button>
        </div>
    </div>

    <?php include __DIR__ . '/_tabs.php'; ?>

    <div class="pflege-shell">
        <!-- Sidebar (Standard-Shell-Pattern wie Chat/Wissen) -->
        <aside class="thx-shell-side">
            <header class="thx-shell-side-header">
                <span class="thx-shell-side-title">Kategorien</span>
                <button class="thx-shell-side-action" @click="aktiveKat = null; aktuell = null">Übersicht</button>
            </header>
            <div class="thx-shell-side-content">
                <template x-for="gruppe in kategorienGruppiert" :key="gruppe.label">
                    <div class="thx-shell-group">
                        <div class="thx-shell-group-label">
                            <span class="material-symbols-rounded" x-text="gruppe.icon"></span>
                            <span x-text="gruppe.label"></span>
                        </div>
                        <template x-for="kat in gruppe.items" :key="kat.typ">
                            <button @click="startWizard(kat.typ)"
                                    :class="['pflege-kat-btn', aktiveKat === kat.typ ? 'is-active' : '', kat.schwere ? 'is-schwere-' + kat.schwere : '']">
                                <span class="material-symbols-rounded" x-text="kat.icon"></span>
                                <span x-text="kat.label"></span>
                                <span class="pflege-kat-count" x-text="kat.anzahl"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <div x-show="kategorienGruppiert.length === 0" style="padding:14px; color: var(--slate-500); font-size: 0.85rem;">
                    Lade …
                </div>
            </div>
        </aside>

        <!-- Wizard-Bereich rechts -->
        <main class="pflege-wizard">
            <!-- Empty State: keine Kat aktiv -->
            <div x-show="!aktiveKat" class="pflege-empty-state">
                <span class="material-symbols-rounded">cleaning_services</span>
                <div class="pflege-empty-state-titel">Datenpflege starten</div>
                <p>Wähle links eine Kategorie um den geführten Workflow zu starten.<br>
                   Du gehst dann Issue für Issue durch — mit Tastatur-Shortcuts geht's am schnellsten.</p>
            </div>

            <!-- Wizard aktiv -->
            <template x-if="aktiveKat">
                <div style="display:flex;flex-direction:column;height:100%;">
                    <!-- Header mit Progress -->
                    <div class="pflege-wizard-head">
                        <div class="pflege-wizard-progress">
                            <div class="pflege-wizard-progress-titel" x-text="katLabel(aktiveKat)"></div>
                            <div class="pflege-wizard-progress-bar">
                                <div :style="'width:' + (queueTotal > 0 ? Math.round(queueIndex / queueTotal * 100) : 0) + '%'"></div>
                            </div>
                        </div>
                        <div class="pflege-wizard-counter">
                            <span x-show="aktuell"><strong x-text="queueIndex"></strong> von <span x-text="queueTotal"></span></span>
                            <span x-show="!aktuell && queueTotal > 0">fertig!</span>
                        </div>
                    </div>

                    <!-- FILTER-BAR — nur bei Issue-Typen mit Aktualitäts-/Schwere-Sortierung -->
                    <div x-show="zeigeZeitFilter() || zeigeSchwereFilter()" style="border-bottom:1px solid var(--slate-200); padding:8px 16px; background:#fff; display:flex; gap:18px; align-items:center; flex-wrap:wrap; font-size:0.8rem;">
                        <template x-if="zeigeZeitFilter()">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <span style="color:var(--slate-500); font-weight:600;">Interaktion seit:</span>
                                <template x-for="opt in [{l:'1 Woche',v:7},{l:'2 Wochen',v:14},{l:'4 Wochen',v:28},{l:'3 Monate',v:90},{l:'6 Monate',v:180}]" :key="opt.v">
                                    <button @click="setzeFilter('interaktion_tage', opt.v)"
                                            :class="['thx-chip', filter.interaktion_tage === opt.v ? 'is-active' : '']"
                                            style="font-size:0.75rem; padding:3px 9px; border-radius:12px; border:1px solid var(--slate-300); background:#fff; cursor:pointer;"
                                            :style="filter.interaktion_tage === opt.v ? 'background:var(--thoxan-100); border-color:var(--thoxan-400); color:var(--thoxan-800); font-weight:600;' : ''"
                                            x-text="opt.l"></button>
                                </template>
                                <button x-show="filter.interaktion_tage" @click="filter.interaktion_tage = null; startWizard(aktiveKat)"
                                        style="font-size:0.7rem; padding:3px 6px; background:transparent; border:none; color:var(--rose-600); cursor:pointer;"
                                        title="Filter aufheben">✕</button>
                            </div>
                        </template>
                        <template x-if="zeigeSchwereFilter()">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <span style="color:var(--slate-500); font-weight:600;">Schwere:</span>
                                <template x-for="opt in [{l:'Hoch',v:'hoch'},{l:'Mittel',v:'mittel'},{l:'Niedrig',v:'niedrig'}]" :key="opt.v">
                                    <button @click="setzeFilter('schwere', opt.v)"
                                            :style="filter.schwere === opt.v ? 'background:var(--thoxan-100); border-color:var(--thoxan-400); color:var(--thoxan-800); font-weight:600;' : ''"
                                            style="font-size:0.75rem; padding:3px 9px; border-radius:12px; border:1px solid var(--slate-300); background:#fff; cursor:pointer;"
                                            x-text="opt.l"></button>
                                </template>
                                <button x-show="filter.schwere" @click="filter.schwere = null; startWizard(aktiveKat)"
                                        style="font-size:0.7rem; padding:3px 6px; background:transparent; border:none; color:var(--rose-600); cursor:pointer;"
                                        title="Filter aufheben">✕</button>
                            </div>
                        </template>
                        <template x-if="zeigeFehltBuckets()">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <span style="color:var(--slate-500); font-weight:600;">Fehlt:</span>
                                <template x-for="opt in [{l:'Viel (4+)',v:'viel'},{l:'Mittel (2-3)',v:'mittel'},{l:'Einzeln (1)',v:'einzeln'}]" :key="opt.v">
                                    <button @click="setzeFilter('fehlt_bucket', opt.v)"
                                            :style="filter.fehlt_bucket === opt.v ? 'background:var(--thoxan-100); border-color:var(--thoxan-400); color:var(--thoxan-800); font-weight:600;' : ''"
                                            style="font-size:0.75rem; padding:3px 9px; border-radius:12px; border:1px solid var(--slate-300); background:#fff; cursor:pointer;"
                                            x-text="opt.l"></button>
                                </template>
                                <button x-show="filter.fehlt_bucket" @click="filter.fehlt_bucket = null; startWizard(aktiveKat)"
                                        style="font-size:0.7rem; padding:3px 6px; background:transparent; border:none; color:var(--rose-600); cursor:pointer;"
                                        title="Filter aufheben">✕</button>
                            </div>
                        </template>
                        <div style="margin-left:auto; display:flex; gap:10px; align-items:center;" x-show="queueTotal > 0">
                            <template x-if="zeigeFehltBuckets()">
                                <button @click="bulkIgnoriereWenig()" class="thx-btn thx-btn-secondary thx-btn-small"
                                        style="font-size:0.72rem; padding:3px 8px; color:var(--rose-700);"
                                        title="Alle Issues mit nur 1 (oder wenigen) fehlenden Feldern auf einmal ignorieren">
                                    <span class="material-symbols-rounded" style="font-size:13px; vertical-align:middle;">do_not_disturb_on</span>
                                    Bulk-Ignorieren
                                </button>
                            </template>
                            <span style="color:var(--slate-500); font-size:0.75rem;">
                                sortiert: schwerste / aktuellste zuerst
                            </span>
                        </div>
                    </div>

                    <!-- Top-Action-Bar (Spiegel der Footer-Actions, damit man oben + unten Buttons hat) -->
                    <div class="pflege-wizard-foot" x-show="aktuell" style="border-top:0; border-bottom:1px solid var(--slate-200); border-radius:0;">
                        <div class="pflege-wizard-hotkeys">
                            <span><kbd>↵</kbd> Übernehmen</span>
                            <span><kbd>S</kbd> Skip</span>
                            <span><kbd>N</kbd> Ignorieren</span>
                            <span x-show="aktuell?.typ === 'verwaister_tag' || (loeschenVerfuegbar() && (kiZielTyp() === 'firma' || kiZielTyp() === 'kontakt'))"><kbd>D</kbd> Löschen</span>
                            <span><kbd>Esc</kbd> zurück</span>
                        </div>
                        <div class="pflege-wizard-actions">
                            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="skipIssue()">Skip →</button>
                            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="ignoriereIssue()" style="color:var(--rose-700);">Ignorieren</button>
                            <template x-if="aktuell?.typ === 'verwaister_tag'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheTag()" style="color:var(--rose-700);">Tag löschen</button>
                            </template>
                            <template x-if="loeschenVerfuegbar() && kiZielTyp() === 'firma'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheFirma()" style="color:var(--rose-700); border-color:var(--rose-400);"
                                        :title="ki.existiert_nicht_mehr ? 'Firma existiert laut KI nicht mehr — entfernen' : 'Diesen Datensatz aus dem CRM entfernen'">
                                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                                    <span x-show="!ki.existiert_nicht_mehr" x-text="aktuell?.typ === 'dublette_firma' ? 'Alle Firmen löschen' : 'Firma löschen'"></span>
                                    <span x-show="ki.existiert_nicht_mehr">Firma löschen (existiert nicht mehr)</span>
                                </button>
                            </template>
                            <template x-if="loeschenVerfuegbar() && kiZielTyp() === 'kontakt'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheKontakt()" style="color:var(--rose-700); border-color:var(--rose-400);"
                                        title="Diesen Datensatz aus dem CRM entfernen">
                                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                                    <span x-text="aktuell?.typ === 'dublette_kontakt' ? 'Alle Kontakte löschen' : 'Kontakt löschen'"></span>
                                </button>
                            </template>
                            <button class="thx-btn thx-btn-primary thx-btn-small" @click="annehmen()" :disabled="aktiv">
                                <span x-show="!aktiv">✓ Übernehmen</span>
                                <span x-show="aktiv">…</span>
                            </button>
                        </div>
                    </div>

                    <!-- Body: Issue-spezifischer Inhalt -->
                    <div class="pflege-wizard-body">
                        <div x-show="laedtIssue" style="text-align:center; padding:40px; color: var(--slate-400);">
                            Lade nächstes Issue …
                        </div>

                        <!-- Fehler beim Laden — Retry-Button -->
                        <template x-if="!laedtIssue && !aktuell && ladeFehler">
                            <div class="pflege-empty-state">
                                <span class="material-symbols-rounded" style="color:var(--rose-500);">error</span>
                                <div class="pflege-empty-state-titel" style="color:var(--rose-700);">Fehler beim Laden</div>
                                <p style="max-width:520px; margin:0 auto 14px;" x-text="ladeFehler"></p>
                                <button class="thx-btn thx-btn-primary thx-btn-small" @click="startWizard(aktiveKat)">
                                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">refresh</span>
                                    Erneut versuchen
                                </button>
                            </div>
                        </template>

                        <!-- Alle Issues abgearbeitet -->
                        <template x-if="!laedtIssue && !aktuell && !ladeFehler">
                            <div class="pflege-empty-state">
                                <span class="material-symbols-rounded" style="color:var(--emerald-400);">check_circle</span>
                                <div class="pflege-empty-state-titel">Alle abgearbeitet 🎉</div>
                                <p>Diese Kategorie hat keine offenen Issues mehr.</p>
                            </div>
                        </template>

                        <!-- DEDIZIERTER FEHLT-FIRMA-WIZARD: 4 klare Optionen, keine Tabelle nötig -->
                        <template x-if="!laedtIssue && aktuell && (aktuell.typ === 'fehlt_firma' || aktuell.typ === 'pflege_backlog')">
                            <div style="max-width:680px; margin:0 auto;">
                                <div style="font-size:1.05rem; color:var(--slate-700); margin-bottom:6px;">
                                    Was machen wir mit <strong x-text="ffWizard.kontaktName"></strong>?
                                </div>
                                <div style="font-size:0.82rem; color:var(--slate-500); margin-bottom:18px;" x-show="ffWizard.kontaktInfo" x-text="ffWizard.kontaktInfo"></div>

                                <!-- Option 1: Firma zuweisen -->
                                <div :class="['ff-option', ffWizard.mode === 'zuweisen' ? 'is-active' : '']" @click="ffWizard.mode = 'zuweisen'">
                                    <div class="ff-option-head">
                                        <span class="material-symbols-rounded">domain</span>
                                        <span class="ff-option-titel">Existierende Firma zuweisen</span>
                                    </div>
                                    <div class="ff-option-body" x-show="ffWizard.mode === 'zuweisen'">
                                        <input type="text" class="pflege-master-input" style="width:100%; padding:8px 12px;"
                                               placeholder="Firma suchen …"
                                               x-model="ffWizard.firmaSuche"
                                               @input.debounce.250="ladeFirmenVorschlaege(ffWizard.firmaSuche)"
                                               @click.stop>
                                        <div x-show="firmenVorschlaege.length > 0" style="margin-top:6px; max-height:260px; overflow-y:auto;">
                                            <template x-for="f in firmenVorschlaege" :key="f.id">
                                                <button @click.stop="ffWizard.firmaId = f.id; ffWizard.firmaSuche = f.firmenname; firmenVorschlaege = []"
                                                        :class="['ff-firma-pill', ffWizard.firmaId === f.id ? 'is-selected' : '']">
                                                    <strong x-text="f.firmenname"></strong>
                                                    <span x-show="f.branche" style="color:var(--slate-500);font-weight:normal;" x-text="' · ' + f.branche"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Option 2: Neue Organisation anlegen -->
                                <div :class="['ff-option', ffWizard.mode === 'neu' ? 'is-active' : '']" @click="ffWizard.mode = 'neu'">
                                    <div class="ff-option-head">
                                        <span class="material-symbols-rounded">add_business</span>
                                        <span class="ff-option-titel">Neue Organisation / Firma anlegen</span>
                                    </div>
                                    <div class="ff-option-body" x-show="ffWizard.mode === 'neu'">
                                        <input type="text" class="pflege-master-input" style="width:100%; padding:8px 12px; margin-bottom:6px;"
                                               placeholder="Name (z.B. „TuS Müller" oder „Müller GmbH")"
                                               x-model="ffWizard.neuName" @click.stop>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <template x-for="t in ['GmbH','AG','UG','GbR','e.V.','Verein','Kirchengemeinde','Schule','Behörde','Privat','Sonstige']" :key="t">
                                                <button @click.stop="ffWizard.neuTyp = t"
                                                        :class="['ff-typ-pill', ffWizard.neuTyp === t ? 'is-selected' : '']" x-text="t"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Option 3: Ohne Firmenbezug (privat) -->
                                <div :class="['ff-option', ffWizard.mode === 'privat' ? 'is-active' : '']" @click="ffWizard.mode = 'privat'">
                                    <div class="ff-option-head">
                                        <span class="material-symbols-rounded">person</span>
                                        <span class="ff-option-titel">Privater Kontakt — keine Firma</span>
                                    </div>
                                    <div class="ff-option-body" x-show="ffWizard.mode === 'privat'" style="color:var(--slate-600); font-size:0.85rem;">
                                        Familie, Freunde, kirchliche Kontakte etc. — bewusst ohne Firmen-Verknüpfung. Wird nicht mehr im „Firma fehlt"-Workflow auftauchen.
                                    </div>
                                </div>

                                <!-- Option 4: Später entscheiden -->
                                <div :class="['ff-option', ffWizard.mode === 'spaeter' ? 'is-active' : '']" @click="ffWizard.mode = 'spaeter'">
                                    <div class="ff-option-head">
                                        <span class="material-symbols-rounded">schedule</span>
                                        <span class="ff-option-titel">Später entscheiden (Backlog)</span>
                                    </div>
                                    <div class="ff-option-body" x-show="ffWizard.mode === 'spaeter'" style="color:var(--slate-600); font-size:0.85rem;">
                                        Kommt in die separate Kategorie „Pflege-Backlog" — kannst Du in einer ruhigeren Stunde abarbeiten.
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- DEDIZIERTER „PERSON FEHLT"-WIZARD (Spiegel zu „Firma fehlt") -->
                        <template x-if="!laedtIssue && aktuell && aktuell.typ === 'firma_ohne_kontakte'">
                            <div style="display:grid; grid-template-columns: minmax(280px, 1fr) minmax(420px, 1.5fr); gap:28px; align-items:start;">
                                <!-- LINKS: Firma-Stammdaten als Orientierung -->
                                <div style="background:var(--slate-50); border:1px solid var(--slate-200); border-radius:8px; padding:16px;">
                                    <div style="font-size:0.7rem; color:var(--slate-500); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Firma</div>
                                    <div style="font-size:1.05rem; font-weight:600; color:var(--slate-800); margin-bottom:4px;" x-text="pfWizard.firmaName"></div>
                                    <div style="font-size:0.7rem; color:var(--slate-400); margin-bottom:14px;">#<span x-text="pfWizard.firmaId"></span></div>

                                    <template x-if="pfWizard.firma">
                                        <dl style="font-size:0.85rem; color:var(--slate-700); margin:0; display:grid; grid-template-columns: 90px 1fr; gap:6px 10px;">
                                            <template x-if="pfWizard.firma.website">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Website</dt>
                                                    <dd style="margin:0;"><a :href="pfWizard.firma.website.startsWith('http') ? pfWizard.firma.website : 'https://' + pfWizard.firma.website" target="_blank" style="color:var(--thoxan-600); word-break:break-all;" x-text="pfWizard.firma.website"></a></dd>
                                                </div>
                                            </template>
                                            <template x-if="pfWizard.firma.branche">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Branche</dt>
                                                    <dd style="margin:0;" x-text="pfWizard.firma.branche"></dd>
                                                </div>
                                            </template>
                                            <template x-if="pfWizard.firma.firmen_typ">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Typ</dt>
                                                    <dd style="margin:0;" x-text="pfWizard.firma.firmen_typ"></dd>
                                                </div>
                                            </template>
                                            <template x-if="pfWizard.firma.telefon">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Telefon</dt>
                                                    <dd style="margin:0;" x-text="pfWizard.firma.telefon"></dd>
                                                </div>
                                            </template>
                                            <template x-if="pfWizard.firma.email">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">E-Mail</dt>
                                                    <dd style="margin:0;"><a :href="'mailto:' + pfWizard.firma.email" style="color:var(--thoxan-600);" x-text="pfWizard.firma.email"></a></dd>
                                                </div>
                                            </template>
                                            <template x-if="(pfWizard.firma.adressen || [])[0]">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Adresse</dt>
                                                    <dd style="margin:0;">
                                                        <span x-show="pfWizard.firma.adressen[0].strasse" x-text="pfWizard.firma.adressen[0].strasse"></span><br x-show="pfWizard.firma.adressen[0].strasse">
                                                        <span x-text="((pfWizard.firma.adressen[0].plz||'') + ' ' + (pfWizard.firma.adressen[0].stadt||'')).trim()"></span>
                                                    </dd>
                                                </div>
                                            </template>
                                            <template x-if="pfWizard.firma.beschreibung">
                                                <div style="display:contents;">
                                                    <dt style="color:var(--slate-500);">Notiz</dt>
                                                    <dd style="margin:0; font-size:0.8rem; color:var(--slate-600); white-space:pre-wrap;" x-text="pfWizard.firma.beschreibung"></dd>
                                                </div>
                                            </template>
                                        </dl>
                                    </template>

                                    <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--slate-200);">
                                        <a :href="'/crm/firmen/' + pfWizard.firmaId" target="_blank" style="font-size:0.78rem; color:var(--thoxan-600); text-decoration:none;">
                                            <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">open_in_new</span>
                                            Firma in neuem Tab öffnen
                                        </a>
                                    </div>
                                </div>

                                <!-- RECHTS: Aktions-Optionen -->
                                <div>
                                    <div style="font-size:1.05rem; color:var(--slate-700); margin-bottom:14px;">
                                        Wer ist Ansprechpartner bei <strong x-text="pfWizard.firmaName"></strong>?
                                    </div>

                                <!-- Option 1: Existierende Person zuweisen -->
                                <div :class="['ff-option', pfWizard.mode === 'zuweisen' ? 'is-active' : '']" @click="pfWizard.mode = 'zuweisen'">
                                    <div class="ff-option-head">
                                        <span class="material-symbols-rounded">person_search</span>
                                        <span class="ff-option-titel">Existierenden Kontakt zuweisen</span>
                                    </div>
                                    <div class="ff-option-body" x-show="pfWizard.mode === 'zuweisen'">
                                        <input type="text" class="pflege-master-input" style="width:100%; padding:8px 12px;"
                                               placeholder="Name, E-Mail oder Funktion suchen …"
                                               x-model="pfWizard.kontaktSuche"
                                               @input.debounce.250="ladeKontaktVorschlaege(pfWizard.kontaktSuche)"
                                               @click.stop>
                                        <div style="font-size:0.7rem; color: var(--slate-500); margin-top:4px;">
                                            Kontakte ohne Firma-Zuordnung erscheinen oben. Bereits zugeordnete Kontakte zeigen rechts ihre aktuelle Firma — Klick weist sie trotzdem zu (Bestätigung folgt).
                                        </div>
                                        <div x-show="pfWizard.kontaktVorschlaege.length > 0" style="margin-top:6px; max-height:300px; overflow-y:auto;">
                                            <template x-for="k in pfWizard.kontaktVorschlaege" :key="k.id">
                                                <button @click.stop="pfWizard.kontaktId = k.id; pfWizard.kontaktSuche = ((k.vorname||'')+' '+(k.nachname||'')).trim() || k.email_primaer; pfWizard.kontaktVorschlaege = []"
                                                        :class="['ff-firma-pill', pfWizard.kontaktId === k.id ? 'is-selected' : '']">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                                        <div style="min-width:0;">
                                                            <strong x-text="((k.vorname||'')+' '+(k.nachname||'')).trim() || k.email_primaer"></strong>
                                                            <span x-show="k.funktion" style="color:var(--slate-500);font-weight:normal;" x-text="' · ' + k.funktion"></span>
                                                            <span x-show="k.email_primaer" style="color:var(--slate-400);font-weight:normal;font-size:0.75rem; display:block;" x-text="k.email_primaer"></span>
                                                        </div>
                                                        <span x-show="!k.firma_id" style="flex:none; font-size:0.7rem; color:var(--emerald-700); background:var(--emerald-50); padding:2px 6px; border-radius:3px; font-weight:600;">ohne Firma</span>
                                                        <span x-show="k.firma_id" style="flex:none; font-size:0.7rem; color:var(--amber-800); background:var(--amber-50); padding:2px 6px; border-radius:3px; font-weight:600;" :title="'Bei: ' + (k.firma_name || '#' + k.firma_id)">
                                                            <span class="material-symbols-rounded" style="font-size:11px;vertical-align:middle;">domain</span>
                                                            <span x-text="k.firmenname || ('Firma #' + k.firma_id)"></span>
                                                        </span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Option 2: Neue Person anlegen -->
                                <div :class="['ff-option', pfWizard.mode === 'neu' ? 'is-active' : '']" @click="pfWizard.mode = 'neu'">
                                    <div class="ff-option-head" style="display:flex; align-items:center; justify-content:space-between;">
                                        <div>
                                            <span class="material-symbols-rounded">person_add</span>
                                            <span class="ff-option-titel">Neuen Kontakt anlegen</span>
                                        </div>
                                        <button @click.stop="pfWizardKiVorausfuellen()" :disabled="pfWizard.kiAktiv"
                                                class="thx-btn thx-btn-secondary thx-btn-small"
                                                style="font-size:0.75rem; padding:3px 8px;"
                                                :title="pfWizard.firmaWebsite ? 'KI holt Impressum + extrahiert Geschäftsführer' : 'Keine Website hinterlegt — KI versucht Web-Suche'">
                                            <span x-show="!pfWizard.kiAktiv">✨ KI vorausfüllen</span>
                                            <span x-show="pfWizard.kiAktiv">…</span>
                                        </button>
                                    </div>
                                    <div class="ff-option-body" x-show="pfWizard.mode === 'neu'">
                                        <div x-show="pfWizard.kiFehler" style="font-size:0.75rem; color:var(--rose-600); margin-bottom:6px;" x-text="pfWizard.kiFehler"></div>
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                                            <input type="text" class="pflege-master-input" placeholder="Vorname"
                                                   x-model="pfWizard.neu.vorname" @click.stop>
                                            <input type="text" class="pflege-master-input" placeholder="Nachname"
                                                   x-model="pfWizard.neu.nachname" @click.stop>
                                        </div>
                                        <input type="text" class="pflege-master-input" style="width:100%; margin-top:6px;" placeholder="Funktion (z.B. Geschäftsführer)"
                                               x-model="pfWizard.neu.funktion" @click.stop>
                                        <input type="email" class="pflege-master-input" style="width:100%; margin-top:6px;" placeholder="E-Mail (optional)"
                                               x-model="pfWizard.neu.email" @click.stop>
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:6px;">
                                            <input type="text" class="pflege-master-input" placeholder="Telefon"
                                                   x-model="pfWizard.neu.telefon" @click.stop>
                                            <input type="text" class="pflege-master-input" placeholder="Mobil"
                                                   x-model="pfWizard.neu.mobil" @click.stop>
                                        </div>
                                    </div>
                                </div>

                                    <div style="font-size:0.78rem; color:var(--slate-500); margin-top:14px;">
                                        Wenn die Firma irrelevant ist: <kbd>D</kbd> oder „Firma löschen" oben rechts.
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- WIZARD-TABELLE: für Dubletten + andere Single-Issues -->
                        <template x-if="!laedtIssue && aktuell && merge.records.length > 0 && !['fehlt_firma','pflege_backlog'].includes(aktuell.typ)">
                            <div>
                                <!-- "Warum bin ich hier?"-Box (prominent oben) -->
                                <template x-if="aktuell.beschreibung_struct?.fehlt">
                                    <div style="margin-bottom:14px; padding:12px 14px; background:var(--amber-50); border-left:3px solid var(--amber-500); border-radius:4px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                        <div>
                                            <div style="font-size:0.7rem; color:var(--amber-800); font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Fehlt</div>
                                            <div style="font-size:0.95rem; font-weight:600; color:var(--slate-800);" x-text="aktuell.beschreibung_struct.fehlt.join(', ')"></div>
                                        </div>
                                        <div x-show="aktuell.beschreibung_struct.letzte_interaktion" style="margin-left:auto; text-align:right;">
                                            <div style="font-size:0.7rem; color:var(--slate-500); font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Letzte Interaktion</div>
                                            <div style="font-size:0.85rem; color:var(--slate-700);" x-text="formatZeitSeit(aktuell.beschreibung_struct.tage_seit)"></div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="!aktuell.beschreibung_struct?.fehlt" style="margin-bottom:14px; color: var(--slate-700);" x-text="aktuell.titel"></div>
                                <div class="pflege-vergleich">
                                    <table class="pflege-vergleich-tab">
                                        <thead>
                                            <tr>
                                                <th class="pflege-label">Feld</th>
                                                <!-- Master-Spalte zuerst (immer ganz links) -->
                                                <th class="is-master">
                                                    <div style="font-size:0.7rem; color: var(--thoxan-600); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">★ Master (editierbar)</div>
                                                    <template x-for="r in merge.records.filter(r => r.id === merge.masterId)" :key="r.id">
                                                        <div>
                                                            <div style="font-weight:600;" x-text="recordTitel(r)"></div>
                                                            <div style="font-size:0.7rem; color: var(--slate-500);">#<span x-text="r.id"></span></div>
                                                        </div>
                                                    </template>
                                                </th>
                                                <!-- Loser-Spalten in der Mitte -->
                                                <template x-for="r in merge.records.filter(r => r.id !== merge.masterId)" :key="r.id">
                                                    <th>
                                                        <button @click="setMaster(r.id)" class="pflege-master-radio">Als Master</button>
                                                        <div style="margin-top:5px; font-weight:600;" x-text="recordTitel(r)"></div>
                                                        <div style="font-size:0.7rem; color: var(--slate-500);">#<span x-text="r.id"></span></div>
                                                    </th>
                                                </template>
                                                <!-- KI-Spalte ganz rechts, immer sichtbar -->
                                                <th x-show="kiUnterstuetzt()" class="is-ki">
                                                    <button @click="ladeKiAnreicherung()" :disabled="ki.aktiv"
                                                            class="pflege-master-radio" style="border-color:var(--amber-400); color:var(--amber-800); background:#fff;">
                                                        <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;" :class="ki.aktiv ? 'pflege-spin' : ''" x-text="ki.aktiv ? 'sync' : 'auto_awesome'"></span>
                                                        <span x-show="!ki.aktiv && !ki.geladen">Generieren</span>
                                                        <span x-show="ki.aktiv">Lade …</span>
                                                        <span x-show="!ki.aktiv && ki.geladen">Erneut</span>
                                                    </button>
                                                    <div style="margin-top:5px; font-weight:600; color:var(--amber-800);">
                                                        <span x-show="!ki.geladen || ki.modus === 'impressum'">✨ Impressum-KI</span>
                                                        <span x-show="ki.geladen && ki.modus === 'websearch'">🔎 Web-Suche-KI</span>
                                                    </div>
                                                    <div x-show="ki.geladen && ki.existiert_nicht_mehr" style="font-size:0.72rem; color: var(--rose-700); margin-top:2px; font-weight:600;">
                                                        ⚠ Firma scheint nicht mehr zu existieren
                                                    </div>
                                                    <div x-show="ki.geladen && ki.quelle" style="font-size:0.7rem; color: var(--slate-500); margin-top:2px;">
                                                        <a :href="ki.quelle" target="_blank" style="color:var(--thoxan-600);" x-text="ki.quelle?.replace(/^https?:\/\//, '').substring(0, 40)"></a>
                                                        <span x-show="ki.confidence">· <strong x-text="Math.round(ki.confidence * 100) + '%'"></strong></span>
                                                    </div>
                                                    <div x-show="ki.geladen && ki.quelle && kiQuelleAndereDomain()"
                                                         style="font-size:0.7rem; color: var(--amber-800); margin-top:2px; font-weight:600;"
                                                         :title="'Master-Website: ' + (merge.masterValues.website || '—')">
                                                        ⚠ Quelle ist eine andere Domain als die Master-Website
                                                    </div>
                                                    <div x-show="ki.fehler" style="font-size:0.7rem; color: var(--rose-600); margin-top:2px;" x-text="ki.fehler"></div>
                                                    <div x-show="!ki.geladen && !ki.aktiv && !ki.fehler" style="font-size:0.7rem; color: var(--slate-500); margin-top:2px;">noch nicht geladen</div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="feld in merge.felder" :key="feld.key">
                                                <tr :style="feldFehltImIssue(feld.key) ? 'background:rgba(245, 158, 11, 0.05);' : ''">
                                                    <td class="pflege-label">
                                                        <span x-text="feld.label"></span>
                                                        <template x-if="feldFehltImIssue(feld.key)">
                                                            <button @click="schnellAnreichern(feld.key)"
                                                                    :disabled="schnellAnreicherStatus[feld.key] === 'aktiv'"
                                                                    style="background:transparent; border:none; color:var(--amber-600); margin-left:4px; cursor:pointer; padding:0; font-size:1rem;"
                                                                    :title="schnellAnreicherTitel(feld.key)">
                                                                <span x-show="schnellAnreicherStatus[feld.key] !== 'aktiv'">⚠</span>
                                                                <span x-show="schnellAnreicherStatus[feld.key] === 'aktiv'" class="material-symbols-rounded pflege-spin" style="font-size:13px; vertical-align:middle;">sync</span>
                                                            </button>
                                                        </template>
                                                    </td>
                                                    <!-- Master-Zelle: editierbares Input (Textarea bei mehrzeiligen Feldern) -->
                                                    <td class="is-master">
                                                        <div style="display:flex; align-items:flex-start; gap:6px;">
                                                            <template x-if="istLangtextFeld(feld.key)">
                                                                <textarea class="pflege-master-input"
                                                                          x-model="merge.masterValues[feld.key]"
                                                                          :placeholder="(merge.records.find(r => r.id === merge.masterId)?.[feld.key]) || '—'"
                                                                          rows="5"
                                                                          style="flex:1; min-width:0; resize:vertical; font-family:inherit; line-height:1.4;"></textarea>
                                                            </template>
                                                            <!-- Firma-Widget: Suche statt rohem ID-Input + Neu-Anlegen -->
                                                            <template x-if="feld.key === 'firma_id'">
                                                                <div style="flex:1; min-width:0; position:relative;">
                                                                    <input type="text" class="pflege-master-input" style="width:100%;"
                                                                           :placeholder="merge.records.find(r => r.id === merge.masterId)?.firma_name || '— Firma suchen oder anlegen —'"
                                                                           x-model="firmaWidget.suche"
                                                                           @input.debounce.250="ladeFirmenVorschlaege(firmaWidget.suche)"
                                                                           @focus="firmaWidget.offen = true"
                                                                           @click.stop>
                                                                    <div x-show="firmaWidget.offen && (firmenVorschlaege.length > 0 || firmaWidget.suche.length >= 2)"
                                                                         @click.outside="firmaWidget.offen = false"
                                                                         x-cloak
                                                                         style="position:absolute; top:100%; left:0; right:0; margin-top:2px; background:#fff; border:1px solid var(--slate-300); border-radius:4px; max-height:260px; overflow-y:auto; z-index:50; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                                                                        <template x-for="f in firmenVorschlaege" :key="f.id">
                                                                            <button type="button" @click.stop="waehleFirmaImWidget(f)"
                                                                                    style="display:block; width:100%; text-align:left; padding:8px 10px; border:none; background:transparent; cursor:pointer; font-size:0.85rem; border-bottom:1px solid var(--slate-100);"
                                                                                    onmouseover="this.style.background='var(--slate-50)'"
                                                                                    onmouseout="this.style.background='transparent'">
                                                                                <strong x-text="f.firmenname"></strong>
                                                                                <span x-show="f.branche" style="color:var(--slate-500);font-weight:normal;" x-text="' · ' + f.branche"></span>
                                                                            </button>
                                                                        </template>
                                                                        <button type="button" @click.stop="legeFirmaInWidgetAn()"
                                                                                x-show="firmaWidget.suche.trim().length >= 2"
                                                                                :disabled="firmaWidget.aktiv"
                                                                                style="display:block; width:100%; text-align:left; padding:10px; border:none; background:var(--thoxan-50); cursor:pointer; font-size:0.85rem; color:var(--thoxan-700); font-weight:600; border-top:1px solid var(--thoxan-100);">
                                                                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add_business</span>
                                                                            <span x-show="!firmaWidget.aktiv">Neue Firma „<span x-text="firmaWidget.suche.trim()"></span>" anlegen</span>
                                                                            <span x-show="firmaWidget.aktiv">… wird angelegt</span>
                                                                        </button>
                                                                        <div x-show="firmenVorschlaege.length === 0 && firmaWidget.suche.trim().length >= 2 && !firmaWidget.aktiv" style="padding:8px 10px; font-size:0.75rem; color:var(--slate-500);">
                                                                            Keine bestehende Firma gefunden.
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <template x-if="!istLangtextFeld(feld.key) && feld.key !== 'firma_id'">
                                                                <input type="text" class="pflege-master-input"
                                                                       x-model="merge.masterValues[feld.key]"
                                                                       :placeholder="(merge.records.find(r => r.id === merge.masterId)?.[feld.key]) || '—'"
                                                                       style="flex:1; min-width:0;">
                                                            </template>
                                                            <template x-if="istUrlFeld(feld.key) && masterUrlAuf(feld.key)">
                                                                <a :href="masterUrlAuf(feld.key)" target="_blank" rel="noopener"
                                                                   title="In neuem Tab öffnen"
                                                                   style="flex:none; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid var(--slate-200); border-radius:4px; color:var(--thoxan-600); text-decoration:none;">
                                                                    <span class="material-symbols-rounded" style="font-size:16px;">open_in_new</span>
                                                                </a>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <!-- Loser-Zellen: klickbar zum Übernehmen -->
                                                    <template x-for="r in merge.records.filter(r => r.id !== merge.masterId)" :key="r.id">
                                                        <td>
                                                            <div :class="['pflege-cell', wertGleich(feld.key, r.id) ? 'is-selected' : '', (r[feld.key] === null || r[feld.key] === '' || r[feld.key] === undefined) ? 'is-empty' : '']"
                                                                 @click="uebernehmeWert(feld.key, r.id)"
                                                                 :title="(r[feld.key] === null || r[feld.key] === '' || r[feld.key] === undefined) ? 'Leer' : 'Klick: in Master übernehmen'"
                                                                 x-text="formatVal(feld, r)"></div>
                                                        </td>
                                                    </template>
                                                    <!-- KI-Zelle ganz rechts -->
                                                    <td x-show="kiUnterstuetzt()" class="is-ki">
                                                        <template x-if="ki.geladen && kiWertFuerFeld(feld.key)">
                                                            <div :class="['pflege-cell', String(merge.masterValues[feld.key] ?? '') === String(kiWertFuerFeld(feld.key)) ? 'is-selected-ki' : '']"
                                                                 @click="uebernehmeAusKi(feld.key)"
                                                                 title="Klick: in Master übernehmen"
                                                                 x-text="kiWertFuerFeld(feld.key)"></div>
                                                        </template>
                                                        <template x-if="!ki.geladen || !kiWertFuerFeld(feld.key)">
                                                            <span class="pflege-cell is-empty">—</span>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Inline-Block: Shared-Email-Alternative (nur bei E-Mail-Dubletten) -->
                                <div x-show="aktuell?.typ === 'dublette_kontakt' && (aktuell?.beschreibung || '').includes('email')"
                                     style="margin-top:14px; background:var(--amber-50); border:1px solid var(--amber-300); border-radius:8px; padding:12px 16px;">
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                        <span class="material-symbols-rounded" style="color:var(--amber-700);">info</span>
                                        <strong style="color:var(--amber-900); font-size:0.9rem;">Sind das zwei verschiedene Personen mit geteilter E-Mail?</strong>
                                    </div>
                                    <div style="font-size:0.82rem; color:var(--slate-700); margin-bottom:10px;">
                                        Ehepartner, Sekretariat, Shared-Inbox … dann sollen sie zwei separate Kontakte bleiben.
                                    </div>

                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <!-- Option 1: shared akzeptieren -->
                                        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="aktionSharedAkzeptieren()" :disabled="aktiv">
                                            <span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;">groups</span>
                                            Geteilte Mailbox akzeptieren
                                        </button>
                                        <!-- Option 2: personalisieren -->
                                        <button class="thx-btn thx-btn-secondary thx-btn-small" @click="sharedForm.offen = !sharedForm.offen; if(sharedForm.offen) initSharedForm();">
                                            <span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;">edit_note</span>
                                            E-Mails personalisieren
                                        </button>
                                    </div>

                                    <!-- Personalisieren-Form -->
                                    <div x-show="sharedForm.offen" x-cloak style="margin-top:12px; padding-top:12px; border-top:1px solid var(--amber-300);">
                                        <div style="font-size:0.82rem; color:var(--slate-700); margin-bottom:8px;">Trag pro Kontakt die jeweilige eigene E-Mail ein:</div>
                                        <template x-for="r in merge.records" :key="r.id">
                                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                                <div style="min-width:200px; font-weight:600; color:var(--slate-700);" x-text="recordTitel(r) + ' (#' + r.id + ')'"></div>
                                                <input type="email" class="pflege-master-input" style="flex:1;"
                                                       x-model="sharedForm.emails[r.id]"
                                                       :placeholder="r.email_primaer || 'name@firma.de'">
                                            </div>
                                        </template>
                                        <div style="margin-top:10px;">
                                            <button class="thx-btn thx-btn-primary thx-btn-small" @click="aktionPersonalisieren()" :disabled="aktiv">
                                                ✓ E-Mails speichern
                                            </button>
                                            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="sharedForm.offen = false">Abbrechen</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sub-Daten-Block: Was passiert mit Tags, Notizen, Adressen, …? -->
                                <div x-show="merge.subdaten" style="margin-top:14px;">
                                    <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--slate-500); margin-bottom:8px; font-weight:600;">Sub-Daten</div>

                                    <!-- Verknüpfte Kontakte: namentlich + klickbar (Merge ODER Single mit Kontakten) -->
                                    <div x-show="merge.subdaten?.kontakte && merge.subdaten.kontakte.length > 0"
                                         style="background:#fff; border:1px solid var(--slate-200); border-radius:6px; padding:10px 14px; margin-bottom:8px;">
                                        <div style="font-weight:600; color:var(--slate-700); margin-bottom:6px; font-size:0.85rem;">
                                            👥 <span x-text="merge.subdaten.kontakte.length"></span>
                                            <span x-show="merge.records.length > 1"> verknüpfte Kontakte werden alle auf den Master umgehängt:</span>
                                            <span x-show="merge.records.length === 1"> verknüpfte Kontakte hängen an dieser Firma:</span>
                                        </div>
                                        <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                            <template x-for="k in (merge.subdaten.kontakte || [])" :key="k.kontakt_id">
                                                <a :href="'/crm/kontakte/' + k.kontakt_id" target="_blank"
                                                   style="display:inline-flex; align-items:center; gap:5px; padding:3px 9px; background:var(--slate-50); border:1px solid var(--slate-200); border-radius:999px; font-size:0.78rem; color:var(--slate-700); text-decoration:none;">
                                                    <span x-text="k.name || ('#' + k.kontakt_id)" style="font-weight:500;"></span>
                                                    <span x-show="k.funktion" style="color:var(--slate-500);" x-text="'· ' + k.funktion"></span>
                                                    <span class="material-symbols-rounded" style="font-size:13px; color:var(--slate-400);">open_in_new</span>
                                                </a>
                                            </template>
                                        </div>
                                        <div x-show="merge.records.length === 1" style="margin-top:8px; font-size:0.78rem; color:var(--slate-500);">
                                            💡 Bei „Firma löschen" wirst Du gefragt, ob diese Kontakte mitgelöscht oder behalten werden sollen.
                                        </div>
                                    </div>

                                    <!-- Additiv: nur bei Merge (mehrere Records) sinnvoll -->
                                    <div x-show="merge.records.length > 1" style="background:var(--emerald-50); border:1px solid var(--emerald-200); border-radius:6px; padding:10px 14px; font-size:0.82rem; color:var(--emerald-800);">
                                        <strong>✓ Folgendes wird vom Master + allen Losern zusammengeführt</strong> (Duplikate werden übersprungen, kein Datenverlust):
                                        <div style="margin-top:6px; color:var(--slate-700); display:flex; flex-wrap:wrap; gap:14px;">
                                            <span x-show="merge.subdaten.additiv?.tags > 0"><strong x-text="merge.subdaten.additiv.tags"></strong> Tags</span>
                                            <span x-show="merge.subdaten.additiv?.listen > 0"><strong x-text="merge.subdaten.additiv.listen"></strong> Listen-Mitgliedschaften</span>
                                            <span x-show="merge.subdaten.additiv?.aktivitaeten > 0"><strong x-text="merge.subdaten.additiv.aktivitaeten"></strong> Aktivitäten (Notizen, Telefonate, Meetings)</span>
                                            <span x-show="merge.subdaten.additiv?.mail_events > 0"><strong x-text="merge.subdaten.additiv.mail_events"></strong> Mail-Events</span>
                                            <span x-show="merge.subdaten.additiv?.opt_in_events > 0"><strong x-text="merge.subdaten.additiv.opt_in_events"></strong> Opt-In-Events</span>
                                            <span x-show="merge.subdaten.additiv?.lead_magnet_events > 0"><strong x-text="merge.subdaten.additiv.lead_magnet_events"></strong> Lead-Magnet-Events</span>
                                            <span x-show="merge.subdaten.additiv?.kunden_zuordnungen > 0"><strong x-text="merge.subdaten.additiv.kunden_zuordnungen"></strong> Kunden-Zuordnungen</span>
                                            <span x-show="merge.subdaten.additiv?.kontakte > 0"><strong x-text="merge.subdaten.additiv.kontakte"></strong> verknüpfte Kontakte</span>
                                            <span x-show="merge.subdaten.additiv?.adressen > 0"><strong x-text="merge.subdaten.additiv.adressen"></strong> Adressen</span>
                                            <span x-show="!subdatenHatAdditiv()" style="color:var(--slate-500);">keine</span>
                                        </div>
                                    </div>

                                    <!-- Konflikte: User muss aktiv entscheiden -->
                                    <div x-show="merge.subdaten.hat_konflikte" style="background:var(--rose-50); border:1px solid var(--rose-300); border-radius:6px; padding:10px 14px; margin-top:8px; font-size:0.82rem; color:var(--rose-800);">
                                        <strong>⚠ Achtung — Konflikte bei eindeutigen Sub-Daten:</strong>
                                        <div style="margin-top:8px; color:var(--slate-800);">
                                            <template x-for="(konflikt, ki) in merge.subdaten.konflikte" :key="ki">
                                                <div style="background:#fff; border:1px solid var(--rose-200); border-radius:4px; padding:8px 10px; margin-bottom:6px;">
                                                    <div style="font-weight:600; margin-bottom:4px;" x-text="konflikt.label"></div>
                                                    <div style="font-size:0.78rem; color:var(--slate-500); margin-bottom:6px;" x-text="konflikt.beschreibung + ' — der Master-Wert bleibt, andere werden verworfen wenn Du nicht den Master tauschst:'"></div>
                                                    <ul style="margin:0; padding-left:18px; font-size:0.8rem;">
                                                        <template x-for="(v, vi) in konflikt.varianten" :key="vi">
                                                            <li :style="v.kontakt_id === merge.masterId ? 'color:var(--emerald-700);font-weight:600;' : 'color:var(--slate-600);'">
                                                                <span x-text="formatVariante(konflikt.typ, v)"></span>
                                                                <span x-show="v.kontakt_id === merge.masterId"> ← bleibt (Master #<span x-text="v.kontakt_id"></span>)</span>
                                                                <span x-show="v.kontakt_id !== merge.masterId" style="color:var(--rose-600);"> ← geht verloren (#<span x-text="v.kontakt_id"></span>)</span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </div>
                                            </template>
                                            <div style="font-size:0.78rem; color:var(--slate-600); margin-top:6px;">
                                                💡 Wenn Du eine der „geht verloren"-Varianten behalten willst: oben „Als Master" auf die entsprechende Spalte klicken.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- VERWAISTER-TAG-WIZARD -->
                        <template x-if="!laedtIssue && aktuell && aktuell.typ === 'verwaister_tag'">
                            <div class="pflege-simple-issue" style="text-align:center; padding:20px 0;">
                                <div style="font-size:1.1rem; color:var(--slate-700); margin-bottom:18px;">
                                    Tag <strong x-text="entityName()"></strong> wird nirgends genutzt.
                                </div>
                                <div style="color:var(--slate-500);">Löschen oder behalten?</div>
                            </div>
                        </template>

                        <!-- (alter VERWAISTER-TAG wurde oben behalten) -->
                        <template x-if="!laedtIssue && aktuell && aktuell.typ === 'verwaister_tag'">
                            <div class="pflege-simple-issue">
                                <div class="pflege-simple-issue-frage">
                                    Tag <strong x-text="entityName()"></strong> wird nirgends genutzt.
                                </div>
                                <div style="color:var(--slate-500);">Löschen oder behalten?</div>
                            </div>
                        </template>
                    </div>

                    <!-- Footer mit Aktionen + Tastatur-Hinweisen -->
                    <div class="pflege-wizard-foot" x-show="aktuell">
                        <div class="pflege-wizard-hotkeys">
                            <span><kbd>↵</kbd> Übernehmen</span>
                            <span><kbd>S</kbd> Skip</span>
                            <span><kbd>N</kbd> Ignorieren</span>
                            <span x-show="aktuell?.typ === 'verwaister_tag' || (loeschenVerfuegbar() && (kiZielTyp() === 'firma' || kiZielTyp() === 'kontakt'))"><kbd>D</kbd> Löschen</span>
                            <span><kbd>Esc</kbd> zurück</span>
                        </div>
                        <div class="pflege-wizard-actions">
                            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="skipIssue()">Skip →</button>
                            <button class="thx-btn thx-btn-secondary thx-btn-small" @click="ignoriereIssue()" style="color:var(--rose-700);">Ignorieren</button>
                            <template x-if="aktuell?.typ === 'verwaister_tag'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheTag()" style="color:var(--rose-700);">Tag löschen</button>
                            </template>
                            <template x-if="loeschenVerfuegbar() && kiZielTyp() === 'firma'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheFirma()" style="color:var(--rose-700); border-color:var(--rose-400);"
                                        :title="ki.existiert_nicht_mehr ? 'Firma existiert laut KI nicht mehr — entfernen' : 'Diesen Datensatz aus dem CRM entfernen'">
                                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                                    <span x-show="!ki.existiert_nicht_mehr" x-text="aktuell?.typ === 'dublette_firma' ? 'Alle Firmen löschen' : 'Firma löschen'"></span>
                                    <span x-show="ki.existiert_nicht_mehr">Firma löschen (existiert nicht mehr)</span>
                                </button>
                            </template>
                            <template x-if="loeschenVerfuegbar() && kiZielTyp() === 'kontakt'">
                                <button class="thx-btn thx-btn-secondary thx-btn-small" @click="loescheKontakt()" style="color:var(--rose-700); border-color:var(--rose-400);"
                                        title="Diesen Datensatz aus dem CRM entfernen">
                                    <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">delete</span>
                                    <span x-text="aktuell?.typ === 'dublette_kontakt' ? 'Alle Kontakte löschen' : 'Kontakt löschen'"></span>
                                </button>
                            </template>
                            <button class="thx-btn thx-btn-primary thx-btn-small" @click="annehmen()" :disabled="aktiv">
                                <span x-show="!aktiv">✓ Übernehmen</span>
                                <span x-show="aktiv">…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </main>
    </div>

    <!-- LinkedIn-Profil-Auswahl-Modal — mehrere Kandidaten mit Foto, Titel, Snippet -->
    <template x-teleport="body">
        <div x-show="linkedinModal.offen" x-cloak @click.self="linkedinModal.offen = false"
             class="thx-modal-backdrop" style="z-index:99999; display:flex;">
            <div @click.stop class="thx-modal" style="max-width:920px; width:100%; max-height:90vh; display:flex; flex-direction:column;">
                <div class="thx-modal-header">
                    <div style="flex:1;">
                        <div class="thx-modal-title">LinkedIn-Profil auswählen</div>
                        <div style="font-size:0.78rem; color:var(--slate-500); margin-top:2px;" x-text="'Suche: ' + linkedinModal.query"></div>
                    </div>
                    <button @click="linkedinModal.offen = false" class="thx-modal-close" title="Schließen">×</button>
                </div>
                <div class="thx-modal-body" style="overflow-y:auto; flex:1; min-height:200px;">
                    <div x-show="linkedinModal.laedt" style="text-align:center; padding:40px; color:var(--slate-400);">
                        <span class="material-symbols-rounded pflege-spin" style="font-size:36px;">sync</span>
                        <div>LinkedIn-Profile werden gesucht …</div>
                    </div>
                    <div x-show="!linkedinModal.laedt && linkedinModal.fehler" style="text-align:center; padding:40px; color:var(--rose-600);" x-text="linkedinModal.fehler"></div>
                    <div x-show="!linkedinModal.laedt && !linkedinModal.fehler && linkedinModal.kandidaten.length === 0" style="text-align:center; padding:40px; color:var(--slate-500);">
                        Keine LinkedIn-Profile gefunden.
                    </div>
                    <div x-show="!linkedinModal.laedt && linkedinModal.kandidaten.length > 0" class="pflege-li-grid">
                        <template x-for="(k, idx) in linkedinModal.kandidaten" :key="idx">
                            <div class="pflege-li-card" @click="waehleLinkedin(k.url)">
                                <div class="pflege-li-thumb">
                                    <template x-if="k.thumbnail">
                                        <img :src="k.thumbnail" alt="" referrerpolicy="no-referrer" loading="lazy"
                                             onerror="this.style.display='none'; this.parentNode.innerHTML += '<div class=\'placeholder\'>👤</div>';">
                                    </template>
                                    <template x-if="!k.thumbnail">
                                        <div class="placeholder">👤</div>
                                    </template>
                                </div>
                                <div class="pflege-li-info">
                                    <div class="pflege-li-title" x-text="k.title"></div>
                                    <div class="pflege-li-desc" x-show="k.description" x-text="k.description"></div>
                                    <div class="pflege-li-url" x-text="k.url.replace(/^https?:\/\//, '')"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div style="padding:10px 20px; border-top:1px solid var(--slate-200); font-size:0.72rem; color:var(--slate-500); text-align:center; flex-shrink:0;">
                    Wähle das Profil, dessen Foto, Funktion und Firma zum Kontakt passen. Enter speichert dann den Link.
                </div>
            </div>
        </div>
    </template>

    <!-- Foto-Bildersuche-Modal — per Teleport ans Body-Ende, nutzt das etablierte .thx-modal-Pattern
         mit 100vw/100vh (Viewport-Units sind transform/contain-resistent). -->
    <template x-teleport="body">
        <div x-show="fotoModal.offen" x-cloak @click.self="fotoModal.offen = false"
             class="thx-modal-backdrop" style="z-index:99999; display:flex;">
            <div @click.stop class="thx-modal" style="max-width:1000px; width:100%; max-height:90vh; display:flex; flex-direction:column; position:relative;"
                 @dragover.prevent="fotoModal.dropAktiv = true"
                 @dragleave.prevent="fotoModal.dropAktiv = false"
                 @drop.prevent="handleDrop($event)">
                <!-- Drag-Overlay über dem gesamten Modal — wird sichtbar wenn Datei hineingezogen wird -->
                <div x-show="fotoModal.dropAktiv" x-cloak
                     style="position:absolute; inset:0; background:rgba(43, 109, 234, 0.15); border:3px dashed var(--thoxan-500); border-radius:8px; pointer-events:none; z-index:1; display:flex; align-items:center; justify-content:center;">
                    <div style="background:#fff; padding:20px 28px; border-radius:6px; font-size:1.05rem; color:var(--thoxan-700); font-weight:600; box-shadow:0 4px 16px rgba(0,0,0,0.15);">
                        <span class="material-symbols-rounded" style="vertical-align:middle; margin-right:6px;">file_upload</span>
                        Hier loslassen zum Hochladen
                    </div>
                </div>
                <div class="thx-modal-header">
                    <div style="flex:1;">
                        <div class="thx-modal-title">Bild auswählen</div>
                        <div style="font-size:0.78rem; color:var(--slate-500); margin-top:2px;" x-text="'Suche: ' + fotoModal.query"></div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <label style="font-size:0.8rem; display:flex; align-items:center; gap:6px; cursor:pointer; white-space:nowrap;">
                            <input type="checkbox" x-model="fotoModal.linkedinOnly"
                                   @change="ladeBilder(merge.records[0]?.id)">
                            Nur LinkedIn
                        </label>
                        <label class="thx-btn thx-btn-secondary thx-btn-small" style="cursor:pointer; margin:0;" :class="{'is-disabled': fotoModal.lädtHoch}">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">upload</span>
                            <span x-show="!fotoModal.lädtHoch">Eigenes Bild …</span>
                            <span x-show="fotoModal.lädtHoch">Lädt hoch …</span>
                            <input type="file" accept="image/*" style="display:none;"
                                   @change="ladeFotoHoch($event.target.files[0]); $event.target.value=''">
                        </label>
                        <button @click="fotoModal.offen = false" class="thx-modal-close" title="Schließen">×</button>
                    </div>
                </div>
                <div class="thx-modal-body" style="overflow-y:auto; flex:1; min-height:200px;">
                    <div style="margin-bottom:12px; padding:8px 12px; background:var(--slate-50); border:1px dashed var(--slate-300); border-radius:6px; font-size:0.78rem; color:var(--slate-600); text-align:center;">
                        Kein passendes Bild dabei? Eigenes Bild per <strong>Drag &amp; Drop</strong> hierher ziehen oder oben rechts „Eigenes Bild …" klicken.
                    </div>
                    <div x-show="fotoModal.laedt" style="text-align:center; padding:40px; color:var(--slate-400);">
                        <span class="material-symbols-rounded pflege-spin" style="font-size:36px;">sync</span>
                        <div>Bilder werden geladen …</div>
                    </div>
                    <div x-show="!fotoModal.laedt && fotoModal.fehler" style="text-align:center; padding:40px; color:var(--rose-600);" x-text="fotoModal.fehler"></div>
                    <div x-show="!fotoModal.laedt && !fotoModal.fehler && fotoModal.bilder.length === 0" style="text-align:center; padding:40px; color:var(--slate-500);">
                        Keine Bilder gefunden.
                    </div>
                    <!-- Eigene Klasse, damit Grid-Regel nicht von inline-style überschrieben werden kann -->
                    <div class="pflege-bilder-grid" x-show="!fotoModal.laedt && fotoModal.bilder.length > 0">
                        <template x-for="(bild, idx) in fotoModal.bilder" :key="idx">
                            <div class="pflege-bild-kachel" @click="waehleFoto(bild.url || bild.thumbnail)">
                                <div class="pflege-bild-wrap">
                                    <img :src="bild.thumbnail || bild.url" alt="" referrerpolicy="no-referrer" loading="lazy"
                                         onerror="this.style.display='none'; this.parentNode.innerHTML += '<div style=\'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--slate-400);font-size:0.7rem;\'>Bild blockiert</div>';">
                                </div>
                                <div class="pflege-bild-quelle" x-text="bild.source || bild.title || ''"></div>
                            </div>
                        </template>
                    </div>
                </div>
                <div style="padding:10px 20px; border-top:1px solid var(--slate-200); font-size:0.72rem; color:var(--slate-500); text-align:center; flex-shrink:0;">
                    Klick auf ein Bild lädt es herunter und speichert es als Profilbild.
                </div>
            </div>
        </div>
    </template>

    <!-- Undo-Toast -->
    <div x-show="undoToast.text" x-cloak
         style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--slate-900);color:#fff;padding:10px 16px;border-radius:6px;font-size:0.875rem;display:flex;align-items:center;gap:12px;z-index:1100;box-shadow:0 4px 12px rgba(0,0,0,0.2);">
        <span x-text="undoToast.text"></span>
        <button x-show="undoToast.undo" @click="undoToast.undo()" style="background:var(--slate-700);border:none;color:#fff;padding:4px 10px;border-radius:4px;font-size:0.8rem;cursor:pointer;">
            Rückgängig
        </button>
    </div>
</div>

<script>
function crmPflege() {
    return {
        kategorien: [], kategorienGruppiert: [],
        totalIssues: 0,
        scannt: false,
        letzterScan: null,

        // Wizard-State
        aktiveKat: null,
        queue: [],         // alle Issues dieser Kategorie
        queueIndex: 0,     // 1-basiert für Anzeige
        queueTotal: 0,
        aktuell: null,     // aktuelles Issue
        laedtIssue: false,
        ladeFehler: null,  // gesetzt wenn fetch/Vorbereitung fehlschlägt — UI zeigt Retry statt "abgearbeitet"

        // Filter (nur sichtbar bei Issue-Typen, wo Aktualität/Schwere Sinn ergeben)
        filter: { interaktion_tage: null, schwere: null, fehlt_bucket: null }, // bucket: 'viel'|'mittel'|'einzeln'
        aktiv: false,

        // Merge-Variablen (für Dubletten)
        // masterValues hält den finalen Wert pro Feld — wird editiert ODER per Klick aus den Loser-Spalten übernommen
        merge: { records: [], felder: [], masterId: null, masterValues: {}, subdaten: null },
        // KI-Anreicherung
        ki: { aktiv: false, fehler: null, fields: {}, quelle: null, confidence: null, geladen: false, modus: null, existiert_nicht_mehr: false, web_treffer: [] },
        // Einfache-Issue-Variablen
        einfacheEingabe: '',
        originalWert: '',
        branchenVorschlaege: [],
        firmenVorschlaege: [],
        firmaWahl: null,
        firmaWebsite: '',
        // "Firma fehlt"-Wizard: 4-Optionen-Modus
        ffWizard: { mode: 'zuweisen', kontaktName: '', kontaktInfo: '',
                    firmaSuche: '', firmaId: null,
                    neuName: '', neuTyp: 'GmbH' },
        // "Person fehlt"-Wizard: Firma hat keinen Ansprechpartner — zuweisen/anlegen/KI
        pfWizard: { mode: 'zuweisen', firmaId: null, firmaName: '', firmaWebsite: '', firma: null,
                    kontaktSuche: '', kontaktVorschlaege: [], kontaktId: null,
                    neu: { vorname: '', nachname: '', funktion: '', email: '', telefon: '', mobil: '' },
                    kiAktiv: false, kiFehler: null },
        // Shared-Email-Form bei E-Mail-Dubletten
        sharedForm: { offen: false, emails: {} },
        // Schnell-Anreicherung pro Feld (LinkedIn-Suche, Foto-Suche etc.)
        schnellAnreicherStatus: {}, // feldKey -> 'aktiv' | 'fertig' | 'fehler'
        // Foto-Modal (Bildersuche)
        fotoModal: { offen: false, bilder: [], laedt: false, fehler: null, query: '', linkedinOnly: false, dropAktiv: false, lädtHoch: false },
        // LinkedIn-Auswahl-Modal (mehrere Profil-Kandidaten zur User-Auswahl)
        linkedinModal: { offen: false, kandidaten: [], laedt: false, fehler: null, query: '' },
        // Firma-Widget innerhalb der Wizard-Tabelle (Suche + Schnell-Anlegen)
        firmaWidget: { offen: false, suche: '', aktiv: false },

        // Undo
        undoToast: { text: '', undo: null, timeout: null },

        katMeta: {
            'dublette_firma':         { label: 'Firmen-Dubletten',    icon: 'domain',         gruppe: 'Dubletten' },
            'dublette_kontakt':       { label: 'Kontakt-Dubletten',   icon: 'group',          gruppe: 'Dubletten' },
            'fehlt_branche':          { label: 'Branche fehlt',       icon: 'business',       gruppe: 'Fehlende Felder' },
            'fehlt_firma':            { label: 'Firma fehlt',         icon: 'person_off',     gruppe: 'Fehlende Felder' },
            'firma_ohne_kontakte':    { label: 'Person fehlt',        icon: 'group_off',      gruppe: 'Fehlende Felder' },
            'fehlt_email':            { label: 'E-Mail fehlt',        icon: 'mail',           gruppe: 'Fehlende Felder' },
            'fehlt_linkedin':         { label: 'LinkedIn fehlt',      icon: 'public',         gruppe: 'Fehlende Felder' },
            'aktiv_unvollstaendig':   { label: 'Aktiv & unvollständig', icon: 'priority_high', gruppe: 'Aktive Kontakte' },
            'email_funktional':       { label: 'Funktionale E-Mail',  icon: 'alternate_email', gruppe: 'E-Mail-Qualität' },
            'email_format_ungueltig': { label: 'E-Mail-Format ungültig', icon: 'rule',        gruppe: 'E-Mail-Qualität' },
            'email_domain_mismatch':  { label: 'E-Mail/Firma-Domain Mismatch', icon: 'compare_arrows', gruppe: 'E-Mail-Qualität' },
            'pflege_backlog':         { label: 'Pflege-Backlog',      icon: 'schedule',       gruppe: 'Backlog' },
            'format_telefon':         { label: 'Telefon-Format',      icon: 'call',           gruppe: 'Format' },
            'format_website':         { label: 'Website ohne https',  icon: 'language',       gruppe: 'Format' },
            'name_gleich':            { label: 'Vor = Nachname',      icon: 'badge',          gruppe: 'Hygiene' },
            'telefon_mobil_gleich':   { label: 'Telefon = Mobil',     icon: 'phonelink_ring', gruppe: 'Hygiene' },
            'plz_unplausibel':        { label: 'PLZ unplausibel',     icon: 'pin_drop',       gruppe: 'Hygiene' },
            'tag_aehnlich':           { label: 'Ähnliche Tags',       icon: 'sell',           gruppe: 'Tags' },
            'verwaister_tag':         { label: 'Verwaiste Tags',      icon: 'delete',         gruppe: 'Tags' },
        },

        async init() { await this.ladeStats(); },

        async ladeStats() {
            const r = await fetch('/api/v1/crm/pflege?action=stats', { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) return;
            const agg = {};
            let total = 0;
            for (const s of j.data.stats) {
                if (!agg[s.typ]) agg[s.typ] = { typ: s.typ, anzahl: 0, schwere: null };
                agg[s.typ].anzahl += parseInt(s.anzahl);
                if (s.schwere === 'hoch') agg[s.typ].schwere = 'hoch';
                else if (s.schwere === 'mittel' && agg[s.typ].schwere !== 'hoch') agg[s.typ].schwere = 'mittel';
                total += parseInt(s.anzahl);
            }
            this.totalIssues = total;
            // Pro Typ Meta-Daten anreichern
            this.kategorien = Object.values(agg).map(k => ({
                ...k,
                label: this.katMeta[k.typ]?.label || k.typ,
                icon: this.katMeta[k.typ]?.icon || 'help',
                gruppe: this.katMeta[k.typ]?.gruppe || 'Sonstige',
            }));
            // Gruppieren
            const gruppen = {};
            const gruppenOrder = ['Aktive Kontakte','Dubletten','E-Mail-Qualität','Fehlende Felder','Format','Hygiene','Tags','Backlog','Sonstige'];
            const gruppenIcons = { 'Aktive Kontakte': 'local_fire_department', Dubletten: 'content_copy', 'E-Mail-Qualität': 'mark_email_read', 'Fehlende Felder': 'warning', Format: 'rule', Hygiene: 'cleaning_services', Tags: 'sell', Backlog: 'schedule', Sonstige: 'more_horiz' };
            for (const k of this.kategorien) {
                if (!gruppen[k.gruppe]) gruppen[k.gruppe] = { label: k.gruppe, icon: gruppenIcons[k.gruppe] || 'folder', items: [] };
                gruppen[k.gruppe].items.push(k);
            }
            for (const g of Object.values(gruppen)) {
                g.items.sort((a,b) => {
                    const ord = { hoch: 0, mittel: 1, niedrig: 2, null: 3 };
                    if (ord[a.schwere] !== ord[b.schwere]) return ord[a.schwere] - ord[b.schwere];
                    return b.anzahl - a.anzahl;
                });
            }
            this.kategorienGruppiert = gruppenOrder.map(name => gruppen[name]).filter(Boolean);
        },

        katLabel(typ) { return this.katMeta[typ]?.label || typ; },

        // Welche Filter sind für die aktuelle Kategorie sinnvoll?
        zeigeZeitFilter() {
            return ['aktiv_unvollstaendig','fehlt_linkedin'].includes(this.aktiveKat);
        },
        zeigeSchwereFilter() {
            // Schwere-Filter immer da, außer bei Issue-Typen ohne unterschiedliche Schwere
            return !['verwaister_tag','pflege_backlog'].includes(this.aktiveKat);
        },
        zeigeFehltBuckets() {
            // Nur bei aktiv_unvollstaendig sinnvoll (Score korreliert mit Anzahl fehlender Felder)
            return this.aktiveKat === 'aktiv_unvollstaendig';
        },

        async setzeFilter(key, wert) {
            // Toggle: nochmal klicken hebt auf
            this.filter[key] = (this.filter[key] === wert) ? null : wert;
            await this.startWizard(this.aktiveKat);
        },

        // Bulk-Skip: alle Issues mit ≤ N fehlenden Feldern ignorieren
        async bulkIgnoriereWenig() {
            const n = prompt(
                'Wie viele fehlende Felder maximal? Alle Issues dieser Kategorie mit höchstens X fehlenden Feldern werden ignoriert.\n\n' +
                'Beispiel: 1 → ignoriert alle „nur 1 Feld fehlt"-Fälle.',
                '1'
            );
            if (n === null) return;
            const max = parseInt(n);
            if (isNaN(max) || max < 1 || max > 6) {
                App.showNotification('Bitte 1 bis 6 angeben.', 'error');
                return;
            }
            if (!confirm('Alle „' + this.katLabel(this.aktiveKat) + '"-Issues mit höchstens ' + max + ' fehlenden Feld' + (max === 1 ? '' : 'ern') + ' ignorieren? Diese Aktion ist nicht direkt rückgängig zu machen.')) return;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=bulk_ignore', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typ: this.aktiveKat, fehlt_max: max })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                App.showNotification(j.data.affected + ' Issues ignoriert.', 'success');
                await this.ladeStats();
                await this.startWizard(this.aktiveKat); // Queue neu laden
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
        },

        async startWizard(typ) {
            this.aktiveKat = typ;
            this.queue = [];
            this.queueIndex = 0;
            this.queueTotal = 0;
            this.aktuell = null;
            this.ladeFehler = null;
            this.laedtIssue = true;
            // Filter NICHT resetten beim Wechseln zwischen Kategorien — der User will dieselbe Zeit-Auswahl beibehalten
            try {
                let url = '/api/v1/crm/pflege?action=issues&typ=' + encodeURIComponent(typ) + '&limit=500';
                if (this.filter.interaktion_tage) url += '&interaktion_tage=' + this.filter.interaktion_tage;
                if (this.filter.schwere) url += '&schwere=' + encodeURIComponent(this.filter.schwere);
                // Anzahl-Felder-Buckets (nur für aktiv_unvollstaendig sinnvoll)
                if (this.filter.fehlt_bucket === 'viel')    url += '&fehlt_min=4';
                if (this.filter.fehlt_bucket === 'mittel')  url += '&fehlt_min=2&fehlt_max=3';
                if (this.filter.fehlt_bucket === 'einzeln') url += '&fehlt_max=1';
                const r = await fetch(url, { credentials: 'same-origin' });
                if (!r.ok) throw new Error('Server-Fehler ' + r.status + ' beim Laden der Issues');
                const j = await r.json();
                if (!j.success) throw new Error(j.message || 'Unbekannter Server-Fehler beim Laden der Issues');
                this.queue = j.data.issues || [];
                this.queueTotal = this.queue.length;
                if (typ === 'fehlt_branche') await this.ladeBranchenListe();
                await this.ladeNext();
            } catch (e) {
                this.ladeFehler = e.message || String(e);
                App.showNotification('Issues konnten nicht geladen werden: ' + this.ladeFehler, 'error');
            }
            this.laedtIssue = false;
        },

        async ladeNext() {
            this.aktuell = null;
            this.einfacheEingabe = '';
            this.originalWert = '';
            this.firmaWahl = null;
            this.firmenVorschlaege = [];
            this.firmaWebsite = '';
            // KI-Spalte resetten
            this.ki = { aktiv: false, fehler: null, fields: {}, quelle: null, confidence: null, geladen: false, modus: null, existiert_nicht_mehr: false, web_treffer: [] };
            // Merge-Tabelle resetten
            this.merge = { records: [], felder: [], masterId: null, masterValues: {}, subdaten: null };
            // Shared-Email-Form resetten
            this.sharedForm = { offen: false, emails: {} };
            // Firma-Widget resetten
            this.firmaWidget = { offen: false, suche: '', aktiv: false };
            const next = this.queue[this.queueIndex];
            if (!next) return;
            this.aktuell = next;
            this.queueIndex++;
            // Vorbereitung — die meisten Issue-Typen nutzen den Single-Preview-Wizard.
            if (next.typ === 'dublette_firma' || next.typ === 'dublette_kontakt') {
                await this.bereiteMergeVor(next);
            } else if (next.typ === 'fehlt_firma' || next.typ === 'pflege_backlog') {
                await this.bereiteFfWizardVor(next);
            } else if (next.typ === 'firma_ohne_kontakte') {
                await this.bereitePfWizardVor(next);
            } else if (next.typ === 'verwaister_tag') {
                // Tag → einfache Bestätigung, keine Tabelle
            } else {
                // Default: alle anderen Issue-Typen werden im Single-Preview gezeigt
                // (fehlt_branche, fehlt_email, fehlt_linkedin, format_*, name_gleich,
                // telefon_mobil_gleich, plz_unplausibel, email_*, aktiv_unvollstaendig,
                // firma_ohne_kontakte, ...).
                await this.bereiteSinglePreviewVor(next);
            }
        },

        /** Bereite "Firma fehlt"-Wizard (4 Optionen) vor */
        async bereiteFfWizardVor(issue) {
            const e = (issue.entities || [])[0];
            if (!e || e.typ !== 'kontakt') return;
            // Kontakt-Stammdaten holen für Namen + Kontext
            const r = await fetch('/api/v1/crm/kontakte/' + e.id, { credentials: 'same-origin' });
            const j = await r.json();
            if (!j.success) return;
            const k = j.data;
            this.ffWizard.kontaktName = ((k.vorname || '') + ' ' + (k.nachname || '')).trim() || (k.email_primaer || 'Kontakt #' + k.id);
            const info = [];
            if (k.funktion) info.push(k.funktion);
            if (k.email_primaer) info.push(k.email_primaer);
            if (k.mobil || k.telefon) info.push(k.mobil || k.telefon);
            this.ffWizard.kontaktInfo = info.join(' · ');
            // Defaults
            this.ffWizard.mode = issue.typ === 'pflege_backlog' ? 'zuweisen' : 'zuweisen';
            this.ffWizard.firmaSuche = '';
            this.ffWizard.firmaId = null;
            this.ffWizard.neuName = '';
            this.ffWizard.neuTyp = 'GmbH';
        },

        /** „Person fehlt"-Wizard — Firma hat keinen Ansprechpartner. */
        async bereitePfWizardVor(issue) {
            const e = (issue.entities || [])[0];
            if (!e || e.typ !== 'firma') return;
            // Firma-Stammdaten (komplett, für Anzeige als Orientierung links)
            try {
                const r = await fetch('/api/v1/crm/firmen/' + e.id, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.pfWizard.firmaId = j.data.id;
                this.pfWizard.firmaName = j.data.firmenname || ('Firma #' + e.id);
                this.pfWizard.firmaWebsite = j.data.website || '';
                this.pfWizard.firma = j.data; // komplettes Objekt für Stamm-Anzeige
            } catch (err) {
                this.ladeFehler = 'Firma-Daten für Issue #' + issue.id + ' konnten nicht geladen werden: ' + err.message;
                App.showNotification(this.ladeFehler, 'error');
                return;
            }
            this.pfWizard.mode = 'zuweisen';
            this.pfWizard.kontaktSuche = '';
            this.pfWizard.kontaktId = null;
            this.pfWizard.kontaktVorschlaege = [];
            this.pfWizard.neu = { vorname: '', nachname: '', funktion: '', email: '', telefon: '', mobil: '' };
            this.pfWizard.kiAktiv = false;
            this.pfWizard.kiFehler = null;
        },

        async ladeKontaktVorschlaege(query) {
            if (!query || query.length < 2) { this.pfWizard.kontaktVorschlaege = []; return; }
            try {
                // Alle Kontakte suchen — Firma-Status wird im UI visualisiert,
                // damit der User entscheiden kann (kein Hard-Filter, sonst werden
                // Vorschläge wie „Ralf Lokay (bei X)" gar nicht erst gefunden).
                const r = await fetch('/api/v1/crm/kontakte?suche=' + encodeURIComponent(query) + '&limit=12', { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) return;
                // Sortiere: ohne Firma zuerst, dann Treffer mit (anderer) Firma
                const eintraege = (j.data.eintraege || []).slice().sort((a, b) => {
                    const aOhne = !a.firma_id ? 0 : 1;
                    const bOhne = !b.firma_id ? 0 : 1;
                    return aOhne - bOhne;
                });
                this.pfWizard.kontaktVorschlaege = eintraege;
            } catch (e) {}
        },

        /** KI: Impressum holen, Geschäftsführer extrahieren, „Neu"-Form vorausfüllen. */
        async pfWizardKiVorausfuellen() {
            if (!this.pfWizard.firmaId || this.pfWizard.kiAktiv) return;
            this.pfWizard.kiAktiv = true;
            this.pfWizard.kiFehler = null;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=ai_enrich', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typ: 'firma', entity_ids: [this.pfWizard.firmaId] })
                });
                const j = await r.json();
                if (!j.success || j.data.fehler) {
                    this.pfWizard.kiFehler = j.data?.fehler || j.message || 'KI-Anreicherung fehlgeschlagen';
                    return;
                }
                const f = j.data.fields || {};
                // Versuche aus geschaeftsfuehrung-Array den ersten Namen zu nehmen
                const gf = Array.isArray(f.geschaeftsfuehrung) ? f.geschaeftsfuehrung[0] : (f.geschaeftsfuehrung || '');
                if (gf) {
                    const parts = String(gf).trim().split(/\s+/);
                    if (parts.length >= 2) {
                        this.pfWizard.neu.vorname = parts.slice(0, -1).join(' ');
                        this.pfWizard.neu.nachname = parts.slice(-1)[0];
                    } else {
                        this.pfWizard.neu.nachname = String(gf).trim();
                    }
                    this.pfWizard.neu.funktion = 'Geschäftsführung';
                }
                if (f.email && !this.pfWizard.neu.email) this.pfWizard.neu.email = f.email;
                if (f.telefon && !this.pfWizard.neu.telefon) this.pfWizard.neu.telefon = f.telefon;
                this.pfWizard.mode = 'neu';
            } catch (e) {
                this.pfWizard.kiFehler = e.message;
            }
            this.pfWizard.kiAktiv = false;
        },

        /** Wendet die im „Person fehlt"-Wizard gewählte Option an. */
        async aktionPfWizard() {
            const firmaId = this.pfWizard.firmaId;
            if (!firmaId) throw new Error('Keine Firma');
            const mode = this.pfWizard.mode;
            if (mode === 'zuweisen') {
                if (!this.pfWizard.kontaktId) throw new Error('Bitte einen Kontakt auswählen');
                // Bestätigen, wenn der Kontakt bereits einer anderen Firma zugeordnet ist
                const gewaehlt = (this.pfWizard.kontaktVorschlaege || []).find(k => k.id === this.pfWizard.kontaktId);
                if (gewaehlt && gewaehlt.firma_id && gewaehlt.firma_id !== firmaId) {
                    const ok = confirm(
                        'Dieser Kontakt ist aktuell „' + (gewaehlt.firmenname || ('Firma #' + gewaehlt.firma_id)) + '" zugeordnet.\n\n' +
                        'Soll er stattdessen „' + this.pfWizard.firmaName + '" zugewiesen werden? Die alte Zuordnung wird überschrieben.'
                    );
                    if (!ok) throw new Error('Abgebrochen');
                }
                const r = await fetch('/api/v1/crm/pflege?action=assign_kontakt', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firma_id: firmaId, kontakt_id: this.pfWizard.kontaktId, issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } else if (mode === 'neu') {
                const n = this.pfWizard.neu;
                if (!n.vorname && !n.nachname) throw new Error('Mindestens Vor- oder Nachname nötig');
                const r = await fetch('/api/v1/crm/pflege?action=create_kontakt_fuer_firma', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firma_id: firmaId, ...n, issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } else {
                throw new Error('Unbekannter Modus: ' + mode);
            }
        },

        /** Single-Issue (kein Merge) — ein Record in der Tabelle, KI-Spalte aktiv. */
        async bereiteSinglePreviewVor(issue) {
            const e = (issue.entities || [])[0];
            if (!e) return;
            let j;
            try {
                const body = { typ: e.typ, id: e.id, issue_id: issue.id };
                if (e.adresse_id) body.adresse_id = e.adresse_id; // für plz_unplausibel + Verwandte
                const r = await fetch('/api/v1/crm/pflege?action=single_preview', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                if (!r.ok) throw new Error('Server-Fehler ' + r.status + ' (single_preview, ' + e.typ + ' #' + e.id + ')');
                j = await r.json();
                if (!j.success) throw new Error(j.message || 'single_preview lieferte keinen Erfolg');
            } catch (err) {
                this.ladeFehler = 'Vorschau für Issue #' + issue.id + ' fehlgeschlagen: ' + (err.message || String(err));
                App.showNotification(this.ladeFehler, 'error');
                return;
            }
            // Stale Issue: Entity wurde zwischenzeitlich gelöscht, Server hat Issue auf obsolet gesetzt
            if (j.data.obsolete) {
                this.zeigeUndoToast('Übersprungen — ' + (j.data.grund || 'Datensatz nicht mehr vorhanden'), null);
                this.ladeStats();
                await this.ladeNext();
                return;
            }
            const rec = j.data.record;
            rec._subdata = { adressen: [], tags: [], listen: [], social: [],
                aktivitaeten_count: 0, mails_count: 0, opt_in_count: 0, lead_magnet_count: 0, kunden_count: 0 };
            this.merge.records = [rec];
            this.merge.felder = j.data.felder;
            this.merge.masterId = rec.id;
            // Bei single-Firma: zeige verknüpfte Kontakte (wichtig fürs Löschen!)
            if (j.data.kontakte && j.data.kontakte.length > 0) {
                this.merge.subdaten = {
                    kontakte: j.data.kontakte,
                    additiv: { kontakte: j.data.kontakte.length },
                    hat_konflikte: false,
                    konflikte: [],
                };
            } else {
                this.merge.subdaten = null;
            }
            this.initMasterValues();
        },

        async bereiteMergeVor(issue) {
            const typ = issue.typ === 'dublette_firma' ? 'firma' : 'kontakt';
            const ids = (issue.entities || []).map(e => e.id);
            let j;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=merge_preview', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typ, ids, issue_id: issue.id })
                });
                if (!r.ok) throw new Error('Server-Fehler ' + r.status + ' (merge_preview, IDs: ' + ids.join(',') + ')');
                j = await r.json();
                if (!j.success) throw new Error(j.message || 'merge_preview lieferte keinen Erfolg');
            } catch (e) {
                this.ladeFehler = 'Merge-Vorschau für Issue #' + issue.id + ' fehlgeschlagen: ' + (e.message || String(e));
                App.showNotification(this.ladeFehler, 'error');
                return;
            }
            // Stale Issue: einer der Dubletten-Records wurde zwischenzeitlich gelöscht
            if (j.data.obsolete) {
                this.zeigeUndoToast('Übersprungen — ' + (j.data.grund || 'Datensatz nicht mehr vorhanden'), null);
                this.ladeStats();
                await this.ladeNext();
                return;
            }
            this.merge.records = j.data.records;
            this.merge.felder = j.data.felder;
            this.merge.masterId = j.data.master_vorschlag;
            this.merge.subdaten = j.data.subdaten_zusammenfassung || null;
            this.initMasterValues();
        },
        subdatenHatAdditiv() {
            if (!this.merge.subdaten) return false;
            const a = this.merge.subdaten.additiv || {};
            return Object.values(a).some(v => v > 0);
        },
        formatVariante(typ, v) {
            if (typ === 'adresse') {
                const a = v.adresse || {};
                return [a.strasse, a.plz + ' ' + a.stadt, a.land].filter(x => x && x.trim()).join(', ');
            }
            if (typ === 'social') return v.url || '';
            return JSON.stringify(v);
        },
        initMasterValues() {
            // Initial: Master-Werte. Wenn leer, nimm nicht-leeren Wert eines anderen Records.
            this.merge.masterValues = {};
            const master = this.merge.records.find(r => r.id === this.merge.masterId);
            if (!master) return;
            for (const f of this.merge.felder) {
                let val = master[f.key];
                if (val === null || val === '' || val === undefined) {
                    for (const r of this.merge.records) {
                        if (r.id !== this.merge.masterId && r[f.key]) {
                            val = r[f.key];
                            break;
                        }
                    }
                }
                this.merge.masterValues[f.key] = val ?? '';
            }
            // Issue-spezifische Auto-Korrektur: Website ohne https → https:// davorsetzen
            if (this.aktuell?.typ === 'format_website') {
                const w = String(this.merge.masterValues.website || '').trim();
                if (w !== '' && !/^https?:\/\//i.test(w)) {
                    this.merge.masterValues.website = 'https://' + w.replace(/^\/+/, '');
                }
            }
        },

        async holeOriginalWert(issue, feldname) {
            const e = (issue.entities || [])[0];
            if (!e) return '';
            const endpoint = e.typ === 'firma' ? '/api/v1/crm/firmen/' + e.id : '/api/v1/crm/kontakte/' + e.id;
            const r = await fetch(endpoint, { credentials: 'same-origin' });
            const j = await r.json();
            return j.success ? (j.data[feldname] || '') : '';
        },

        async ladeBranchenListe() {
            try {
                const r = await fetch('/api/v1/crm/firmen?suche=&limit=500', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    const branchen = new Set();
                    for (const f of (j.data.eintraege || [])) if (f.branche) branchen.add(f.branche);
                    this.branchenVorschlaege = Array.from(branchen).sort();
                }
            } catch (e) {}
        },
        // Firma im Wizard-Widget gewählt — setzt firma_id + visualisiert über Placeholder
        waehleFirmaImWidget(f) {
            this.merge.masterValues.firma_id = f.id;
            // record-Wert mit aktualisieren, damit der Placeholder den neuen Namen zeigt
            const m = this.merge.records.find(r => r.id === this.merge.masterId);
            if (m) m.firma_name = f.firmenname;
            this.firmaWidget.suche = '';
            this.firmaWidget.offen = false;
            this.firmenVorschlaege = [];
            App.showNotification('Firma „' + f.firmenname + '" zugewiesen.', 'success');
        },
        // „+ Neue Firma anlegen" — ruft quick_firma und übernimmt die ID
        async legeFirmaInWidgetAn() {
            const name = this.firmaWidget.suche.trim();
            if (name.length < 2 || this.firmaWidget.aktiv) return;
            this.firmaWidget.aktiv = true;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=quick_firma', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firmenname: name }) // kein kontakt_id → Übernehmen-Klick verknüpft normal
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.waehleFirmaImWidget({ id: j.data.firma_id, firmenname: name });
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.firmaWidget.aktiv = false;
        },

        async ladeFirmenVorschlaege(query) {
            if (!query || query.length < 2) { this.firmenVorschlaege = []; return; }
            try {
                const r = await fetch('/api/v1/crm/firmen?suche=' + encodeURIComponent(query) + '&limit=8', { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) this.firmenVorschlaege = j.data.eintraege || [];
            } catch (e) {}
        },

        entityName() {
            const e = (this.aktuell?.entities || [])[0];
            if (!e) return '';
            // Aus Titel ableiten falls möglich
            const m = this.aktuell.titel.match(/„([^"]+)"/);
            if (m) return m[1];
            return e.typ + ' #' + e.id;
        },
        entityUrl() {
            const e = (this.aktuell?.entities || [])[0];
            if (!e) return '#';
            return e.typ === 'firma' ? '/crm/firmen/' + e.id : '/crm/kontakte/' + e.id;
        },

        // ─── Merge-Helper ───
        setMaster(id) { this.merge.masterId = id; this.initMasterValues(); },

        // KI-Spalte: in welchen Issue-Typen ist sie nützlich?
        kiUnterstuetzt() {
            const t = this.aktuell?.typ;
            return [
                'dublette_firma','dublette_kontakt',
                'fehlt_branche','fehlt_firma','firma_ohne_kontakte',
                'format_website','format_telefon',
                'fehlt_linkedin',
                'aktiv_unvollstaendig',
                'email_funktional','email_domain_mismatch',
            ].includes(t);
        },
        kiZielTyp() {
            // Welcher Entity-Typ wird angereichert?
            const t = this.aktuell?.typ;
            if (['dublette_firma','fehlt_branche','firma_ohne_kontakte'].includes(t)) return 'firma';
            if (['dublette_kontakt','fehlt_firma','fehlt_linkedin','aktiv_unvollstaendig','email_funktional','email_domain_mismatch'].includes(t)) return 'kontakt';
            // format_website / format_telefon: aus entities ableiten
            const e = (this.aktuell?.entities || [])[0];
            return e?.typ || 'firma';
        },
        kiModus() {
            // Spezial-Modus: LinkedIn-Issue → site:linkedin.com/in-Suche statt allgemeiner Web-Search
            return this.aktuell?.typ === 'fehlt_linkedin' ? 'linkedin' : null;
        },

        // ── KI-Anreicherung (Impressum für Firma, Web-Search für Kontakt) ──
        async ladeKiAnreicherung() {
            if (!this.aktuell || !this.kiUnterstuetzt()) return;
            this.ki.aktiv = true; this.ki.fehler = null;
            try {
                const ids = (this.aktuell.entities || []).map(e => e.id);
                const zielTyp = this.kiZielTyp();
                const modus = this.kiModus();
                const body = { typ: zielTyp, entity_ids: ids };
                if (modus) body.modus = modus;
                const r = await fetch('/api/v1/crm/pflege?action=ai_enrich', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const j = await r.json();
                if (!j.success) { this.ki.fehler = j.message || 'Fehler'; this.ki.geladen = true; this.ki.aktiv = false; return; }
                if (j.data.fehler) { this.ki.fehler = j.data.fehler; this.ki.geladen = true; this.ki.aktiv = false; return; }
                this.ki.fields = j.data.fields || {};
                this.ki.quelle = j.data.quelle_url;
                this.ki.confidence = j.data.confidence;
                this.ki.modus = j.data.modus || null;
                this.ki.existiert_nicht_mehr = !!j.data.existiert_nicht_mehr;
                this.ki.web_treffer = j.data.web_treffer || [];
                this.ki.geladen = true;
                // Auto-Übernehmen: leere Master-Felder mit nicht-leeren KI-Werten füllen
                this.uebernehmeAusKiAuto();
            } catch (e) {
                this.ki.fehler = e.message;
                this.ki.geladen = true;
            }
            this.ki.aktiv = false;
        },
        // KI-Feld-Mapping: KI-Key (vom LLM) → Merge-Feld-Key
        kiFeldMapping() {
            return {
                firmenname: 'firmenname', branche: 'branche', firmen_typ: 'firmen_typ',
                telefon: 'telefon', fax: 'fax', email: 'email', website: 'website',
                funktion: 'funktion', abteilung: 'abteilung', mobil: 'mobil',
                beschreibung: 'beschreibung',
                linkedin: 'linkedin', xing: 'xing',
            };
        },
        uebernehmeAusKiAuto() {
            const mapping = this.kiFeldMapping();
            for (const [kiKey, feldKey] of Object.entries(mapping)) {
                const kiVal = this.ki.fields[kiKey];
                const cur = this.merge.masterValues[feldKey];
                if (kiVal && (!cur || cur === '')) {
                    this.merge.masterValues[feldKey] = kiVal;
                }
            }
        },
        uebernehmeAusKi(feldKey) {
            const mapping = this.kiFeldMapping();
            const reverse = Object.fromEntries(Object.entries(mapping).map(([k,v]) => [v, k]));
            const kiKey = reverse[feldKey];
            if (!kiKey) return;
            const kiVal = this.ki.fields[kiKey];
            if (kiVal !== undefined && kiVal !== '') {
                this.merge.masterValues[feldKey] = kiVal;
            }
        },
        kiWertFuerFeld(feldKey) {
            const mapping = this.kiFeldMapping();
            const reverse = Object.fromEntries(Object.entries(mapping).map(([k,v]) => [v, k]));
            const kiKey = reverse[feldKey];
            if (!kiKey) return null;
            const v = this.ki.fields[kiKey];
            return (v === undefined || v === '') ? null : v;
        },
        // Löschen-Button für Datensatz anzeigen?
        // Nicht bei Wizard-Typen, die eigene Aktionen haben (fehlt_firma, pflege_backlog, verwaister_tag).
        loeschenVerfuegbar() {
            const t = this.aktuell?.typ;
            if (!t) return false;
            return !['fehlt_firma', 'pflege_backlog', 'verwaister_tag'].includes(t);
        },
        // URL-Felder, die direkt anklickbar sein sollen
        istUrlFeld(feldKey) {
            return ['website', 'linkedin', 'xing', 'facebook', 'instagram'].includes(feldKey);
        },
        // Mehrzeilige Felder (Textarea statt Input)
        istLangtextFeld(feldKey) {
            return ['beschreibung', 'notizen', 'notiz'].includes(feldKey);
        },
        // Menschlich lesbare Zeitspanne
        formatZeitSeit(tage) {
            if (tage === null || tage === undefined) return '';
            if (tage === 0) return 'heute';
            if (tage === 1) return 'gestern';
            if (tage < 7) return 'vor ' + tage + ' Tagen';
            if (tage < 14) return 'letzte Woche';
            if (tage < 31) return 'vor ' + Math.round(tage/7) + ' Wochen';
            if (tage < 365) return 'vor ' + Math.round(tage/30) + ' Monaten';
            return 'vor über einem Jahr';
        },
        // Ist das Feld in der „Fehlt"-Liste dieses Issues?
        feldFehltImIssue(feldKey) {
            const fehlt = this.aktuell?.beschreibung_struct?.fehlt || [];
            if (fehlt.length === 0) return false;
            const map = {
                funktion: 'Funktion', firma_id: 'Firma',
                telefon: 'Telefon', mobil: 'Telefon',
                linkedin: 'LinkedIn', beschreibung: 'Beschreibung',
                foto_path: 'Foto',
            };
            const label = map[feldKey];
            return label && fehlt.includes(label);
        },
        schnellAnreicherTitel(feldKey) {
            const map = {
                linkedin: 'Brave-Suche: LinkedIn-Profil ermitteln',
                foto_path: 'Bildersuche: Foto auswählen',
            };
            return map[feldKey] || 'KI-Schnell-Anreicherung';
        },
        async schnellAnreichern(feldKey) {
            const rec = this.merge.records[0];
            if (!rec) return;
            this.schnellAnreicherStatus[feldKey] = 'aktiv';
            try {
                if (feldKey === 'linkedin') {
                    // LinkedIn-Modal mit mehreren Kandidaten öffnen — Auswahl per Klick
                    await this.oeffneLinkedinModal(rec.id);
                } else if (feldKey === 'foto_path') {
                    // Foto-Modal mit Brave-Bildersuche öffnen
                    await this.oeffneFotoModal(rec.id);
                } else {
                    // Fallback: allgemeine Kontakt-Anreicherung
                    await this.ladeKiAnreicherung();
                }
                this.schnellAnreicherStatus[feldKey] = 'fertig';
            } catch (e) {
                this.schnellAnreicherStatus[feldKey] = 'fehler';
                App.showNotification(e.message, 'error');
            }
        },
        async oeffneLinkedinModal(kontaktId) {
            this.linkedinModal = { offen: true, kandidaten: [], laedt: true, fehler: null, query: '' };
            try {
                const r = await fetch('/api/v1/crm/pflege?action=linkedin_kandidaten', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: kontaktId })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.linkedinModal.kandidaten = j.data.kandidaten || [];
                this.linkedinModal.query = j.data.query || '';
                this.linkedinModal.fehler = j.data.fehler || null;
            } catch (e) {
                this.linkedinModal.fehler = e.message;
            }
            this.linkedinModal.laedt = false;
        },
        waehleLinkedin(url) {
            // Setzt die Master-URL — beim Übernehmen speichert aktionSingleUpdate via set_social_link
            this.merge.masterValues.linkedin = url;
            this.linkedinModal.offen = false;
            App.showNotification('LinkedIn-Profil ausgewählt — Enter speichert.', 'success');
        },
        async oeffneFotoModal(kontaktId) {
            // Default: allgemeine Bildersuche — LinkedIn-Treffer sind erfahrungsgemäß
            // meist Posting-Vorschauen oder Listing-Kacheln, nicht das Profilbild.
            this.fotoModal = { offen: true, bilder: [], laedt: true, fehler: null, query: '', linkedinOnly: false };
            await this.ladeBilder(kontaktId);
        },
        async ladeBilder(kontaktId) {
            this.fotoModal.laedt = true;
            this.fotoModal.fehler = null;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=image_search', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: kontaktId, linkedin_only: this.fotoModal.linkedinOnly })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.fotoModal.bilder = j.data.bilder || [];
                this.fotoModal.query = j.data.query;
            } catch (e) {
                this.fotoModal.fehler = e.message;
            }
            this.fotoModal.laedt = false;
        },
        async ladeFotoHoch(datei) {
            const rec = this.merge.records[0];
            if (!rec || !datei) return;
            if (!datei.type.startsWith('image/')) {
                App.showNotification('Nur Bild-Dateien erlaubt.', 'error');
                return;
            }
            if (datei.size > 8 * 1024 * 1024) {
                App.showNotification('Datei zu groß (max 8 MB).', 'error');
                return;
            }
            this.fotoModal.lädtHoch = true;
            try {
                const fd = new FormData();
                fd.append('foto', datei);
                const r = await fetch('/api/v1/crm/kontakte/' + rec.id + '/foto', {
                    method: 'POST', credentials: 'same-origin', body: fd
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                rec.foto_path = j.data.foto_path;
                this.merge.masterValues.foto_path = j.data.foto_path;
                this.fotoModal.offen = false;
                App.showNotification('Foto hochgeladen.', 'success');
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.fotoModal.lädtHoch = false;
        },
        handleDrop(ev) {
            this.fotoModal.dropAktiv = false;
            const datei = ev.dataTransfer?.files?.[0];
            if (datei) this.ladeFotoHoch(datei);
        },
        async waehleFoto(url) {
            const rec = this.merge.records[0];
            if (!rec) return;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=save_kontakt_foto_url', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: rec.id, url })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Foto ist serverseitig schon gespeichert — ALT-Wert + Master-Wert synchron setzen,
                // damit der Diff-Check beim Übernehmen das Feld nicht nochmal als Änderung schickt
                // (sonst „Feld nicht erlaubt: foto_path" vom Inline-Endpoint).
                rec.foto_path = j.data.foto_path;
                this.merge.masterValues.foto_path = j.data.foto_path;
                this.fotoModal.offen = false;
                App.showNotification('Foto gespeichert.', 'success');
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
        },
        masterUrlAuf(feldKey) {
            const v = String(this.merge.masterValues[feldKey] || '').trim();
            if (v === '') return '';
            return /^https?:\/\//i.test(v) ? v : 'https://' + v.replace(/^\/+/, '');
        },
        // Host-Vergleich: liegt die KI-Quelle auf einer anderen Domain als die Master-Website?
        kiQuelleAndereDomain() {
            const q = this.ki.quelle || '';
            const w = this.merge.masterValues.website || '';
            if (!q || !w) return false;
            try {
                const hQ = new URL(q).hostname.replace(/^www\./, '').toLowerCase();
                const hW = new URL(/^https?:\/\//i.test(w) ? w : 'https://' + w).hostname.replace(/^www\./, '').toLowerCase();
                return hQ !== '' && hW !== '' && hQ !== hW;
            } catch (e) { return false; }
        },

        // Klick auf eine Loser-Zelle: Wert in Master-Input übernehmen
        uebernehmeWert(feldKey, recordId) {
            const r = this.merge.records.find(r => r.id === recordId);
            if (!r) return;
            this.merge.masterValues[feldKey] = r[feldKey] ?? '';
        },
        // Hilfsfunktion: ist der Master-Wert aktuell == dem Wert dieses Records (für Highlight)
        wertGleich(feldKey, recordId) {
            const r = this.merge.records.find(r => r.id === recordId);
            if (!r) return false;
            const a = (r[feldKey] === null || r[feldKey] === undefined) ? '' : String(r[feldKey]);
            const b = this.merge.masterValues[feldKey] === undefined ? '' : String(this.merge.masterValues[feldKey]);
            return a === b && a !== '';
        },
        formatVal(feld, r) {
            const v = r[feld.key];
            if (v === null || v === '' || v === undefined) return '—';
            if (feld.transform === 'firma') return r.firma_name || ('Firma #' + v);
            if (feld.key === 'deal_wert') return Number(v).toLocaleString('de-DE') + ' €';
            return String(v);
        },
        recordTitel(r) {
            if (this.aktuell.typ === 'dublette_kontakt') {
                return ((r.vorname || '') + ' ' + (r.nachname || '')).trim() || (r.email_primaer || 'Kontakt #' + r.id);
            }
            return r.firmenname || ('Firma #' + r.id);
        },

        // ─── Aktionen ───
        async annehmen() {
            if (!this.aktuell || this.aktiv) return;
            this.aktiv = true;
            try {
                if (this.aktuell.typ === 'dublette_firma' || this.aktuell.typ === 'dublette_kontakt') {
                    await this.aktionMerge();
                } else if (this.aktuell.typ === 'fehlt_firma' || this.aktuell.typ === 'pflege_backlog') {
                    await this.aktionFfWizard();
                } else if (this.aktuell.typ === 'firma_ohne_kontakte') {
                    await this.aktionPfWizard();
                } else if (this.aktuell.typ === 'verwaister_tag') {
                    await this.markiereErledigt('manuell_erledigt');
                } else if (this.merge.records.length === 1) {
                    await this.aktionSingleUpdate();
                } else {
                    await this.markiereErledigt('manuell_erledigt');
                }
                this.zeigeUndoToast('Übernommen.', null);
                await this.ladeNext();
                this.ladeStats();
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.aktiv = false;
        },

        /** Wendet die im "Firma fehlt"-Wizard gewählte Option an. */
        async aktionFfWizard() {
            const kontaktId = (this.aktuell.entities || [])[0]?.id;
            if (!kontaktId) throw new Error('Kein Kontakt');
            const mode = this.ffWizard.mode;
            if (mode === 'zuweisen') {
                if (!this.ffWizard.firmaId) throw new Error('Bitte eine Firma aus den Vorschlägen wählen');
                const r = await fetch('/api/v1/crm/kontakte/' + kontaktId + '/inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feld: 'firma_id', wert: this.ffWizard.firmaId })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Außerdem firma_status auf 'verknuepft' setzen
                await fetch('/api/v1/crm/pflege?action=set_firma_status', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: kontaktId, status: 'verknuepft', issue_id: this.aktuell.id })
                });
            } else if (mode === 'neu') {
                if (!this.ffWizard.neuName.trim()) throw new Error('Bitte Namen eingeben');
                const r = await fetch('/api/v1/crm/pflege?action=quick_firma', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        firmenname: this.ffWizard.neuName.trim(),
                        firmen_typ: this.ffWizard.neuTyp,
                        kontakt_id: kontaktId,
                        issue_id: this.aktuell.id,
                    })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } else if (mode === 'privat') {
                const r = await fetch('/api/v1/crm/pflege?action=set_firma_status', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: kontaktId, status: 'ohne_firmenbezug', issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } else if (mode === 'spaeter') {
                const r = await fetch('/api/v1/crm/pflege?action=set_firma_status', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_id: kontaktId, status: 'pflege_offen', issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            }
        },

        /** Schreibt alle geänderten Felder eines single-record-Issues zurück. */
        async aktionSingleUpdate() {
            const rec = this.merge.records[0];
            if (!rec) return;
            const entityTyp = this.kiZielTyp();
            // Diff berechnen
            const aenderungen = {};
            for (const feldKey of Object.keys(this.merge.masterValues)) {
                const neu = this.merge.masterValues[feldKey];
                const alt = rec[feldKey];
                if ((neu === null || neu === '') && (alt === null || alt === '' || alt === undefined)) continue;
                if (String(neu) !== String(alt ?? '')) aenderungen[feldKey] = neu;
            }
            if (Object.keys(aenderungen).length === 0) {
                // Nichts geändert — als erledigt markieren (User hat geprüft + abgehakt)
                await this.markiereErledigt('manuell_erledigt');
                return;
            }
            // Virtuelle Adress-Felder vorab abspalten — die gehen an einen eigenen Endpoint
            const adressFeldMap = { adresse_strasse: 'strasse', adresse_plz: 'plz', adresse_stadt: 'stadt', adresse_land: 'land' };
            const adresseId = rec.adresse_id ? Number(rec.adresse_id) : null;
            const adressUpdates = {};
            for (const [feld, wert] of Object.entries(aenderungen)) {
                if (adressFeldMap[feld] && adresseId) {
                    adressUpdates[adressFeldMap[feld]] = wert;
                    delete aenderungen[feld];
                }
            }
            for (const [adrFeld, wert] of Object.entries(adressUpdates)) {
                const r = await fetch('/api/v1/crm/pflege?action=set_adresse_feld', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ adresse_id: adresseId, feld: adrFeld, wert })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            }

            if (entityTyp === 'firma') {
                if (Object.keys(aenderungen).length > 0) {
                    const r = await fetch('/api/v1/crm/firmen/' + rec.id, {
                        method: 'PUT', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(aenderungen)
                    });
                    const j = await r.json();
                    if (!j.success) throw new Error(j.message);
                }
            } else {
                // kontakt: virtuelle Social-Felder (linkedin/xing) gehen an set_social_link,
                // alles andere ans inline-Endpoint
                const socialFelder = ['linkedin','xing','facebook','instagram','twitter_x','youtube','tiktok'];
                for (const [feld, wert] of Object.entries(aenderungen)) {
                    if (socialFelder.includes(feld)) {
                        const r = await fetch('/api/v1/crm/pflege?action=set_social_link', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ kontakt_id: rec.id, plattform: feld, url: wert })
                        });
                        const j = await r.json();
                        if (!j.success) throw new Error(j.message);
                    } else {
                        const r = await fetch('/api/v1/crm/kontakte/' + rec.id + '/inline', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ feld, wert })
                        });
                        const j = await r.json();
                        if (!j.success) throw new Error(j.message);
                    }
                }
            }
            await this.markiereErledigt('manuell_korrigiert');
            // Idee 7: verwandte Issues (LinkedIn, E-Mail, Branche, …) für denselben Datensatz
            // automatisch miterledigen, wenn ihre Bedingung nun nicht mehr greift
            try {
                const r = await fetch('/api/v1/crm/pflege?action=refresh_entity_issues', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typ: entityTyp, id: rec.id })
                });
                const j = await r.json();
                if (j.success && j.data.closed > 0) {
                    App.showNotification('+ ' + j.data.closed + ' verwandtes Issue mitgeschlossen', 'success');
                }
            } catch (e) { /* nicht-kritisch */ }
        },

        // ─── Shared-Email-Aktionen ───
        initSharedForm() {
            this.sharedForm.emails = {};
            for (const r of this.merge.records) {
                this.sharedForm.emails[r.id] = r.email_primaer || '';
            }
        },
        async aktionSharedAkzeptieren() {
            if (this.aktiv) return;
            if (!confirm('Alle ' + this.merge.records.length + ' Kontakte als Mehrpersonen-Adresse markieren? Sie werden künftig vom Dubletten-Detektor ignoriert.')) return;
            this.aktiv = true;
            try {
                const ids = this.merge.records.map(r => r.id);
                const r = await fetch('/api/v1/crm/pflege?action=mark_shared_email', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kontakt_ids: ids, issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.zeigeUndoToast('Als Mehrpersonen-Adresse markiert.', null);
                await this.ladeNext();
                this.ladeStats();
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.aktiv = false;
        },
        async aktionPersonalisieren() {
            if (this.aktiv) return;
            this.aktiv = true;
            try {
                const mapping = {};
                for (const [kid, mail] of Object.entries(this.sharedForm.emails)) {
                    const m = (mail || '').trim();
                    if (m === '' || !m.includes('@')) {
                        const rec = this.merge.records.find(r => r.id == kid);
                        throw new Error('Ungültige E-Mail für ' + (rec ? this.recordTitel(rec) : 'Kontakt ' + kid));
                    }
                    mapping[kid] = m;
                }
                const r = await fetch('/api/v1/crm/pflege?action=personalize_emails', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mapping, issue_id: this.aktuell.id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.zeigeUndoToast('E-Mails personalisiert.', null);
                await this.ladeNext();
                this.ladeStats();
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.aktiv = false;
        },

        /** Firma löschen (Soft-Delete) wenn KI feststellt: existiert nicht mehr. */
        async loescheFirma() {
            // Verknüpfte Kontakte ermitteln (aus Sub-Daten oder direkt nachladen)
            const kontakte = this.merge.subdaten?.kontakte || [];
            // Bei Dublette: alle Loser + Master löschen. Bei Single: nur den.
            const firmaIds = this.aktuell.typ === 'dublette_firma'
                ? this.merge.records.map(r => r.id)
                : [this.merge.records[0]?.id || (this.aktuell.entities || [])[0]?.id].filter(Boolean);
            if (firmaIds.length === 0) return;

            // Wenn Kontakte verknüpft sind: User fragen was damit passieren soll
            let kontaktAktion = 'backlog'; // 'backlog' (ins Pflege-Backlog) | 'delete' (mitlöschen) | 'privat' (ohne_firmenbezug)
            if (kontakte.length > 0) {
                const namen = kontakte.slice(0, 5).map(k => k.name || ('#' + k.kontakt_id)).join(', ');
                const mehr = kontakte.length > 5 ? ` und ${kontakte.length - 5} weitere` : '';
                const wahl = prompt(
                    `Die Firma hat ${kontakte.length} verknüpfte Kontakt${kontakte.length === 1 ? '' : 'e'}:\n${namen}${mehr}\n\n` +
                    `Was soll mit diesen Kontakten passieren?\n\n` +
                    `  B = ins Pflege-Backlog (Empfehlung — später entscheiden)\n` +
                    `  P = als privat markieren (ohne Firmenbezug)\n` +
                    `  L = mitlöschen (Soft-Delete — wenn Firma komplett fake/tot)\n` +
                    `  Abbrechen = nichts tun\n\n` +
                    `Tippe B, P oder L:`,
                    'B'
                );
                if (wahl === null) return;
                const w = wahl.trim().toUpperCase();
                if (w === 'L') kontaktAktion = 'delete';
                else if (w === 'P') kontaktAktion = 'privat';
                else if (w === 'B') kontaktAktion = 'backlog';
                else { App.showNotification('Ungültige Eingabe — Abbruch', 'error'); return; }
            } else {
                if (!confirm('Firma wirklich als gelöscht markieren? (Soft-Delete)')) return;
            }

            this.aktiv = true;
            try {
                // Erst Kontakte behandeln
                if (kontakte.length > 0) {
                    if (kontaktAktion === 'delete') {
                        for (const k of kontakte) {
                            const r = await fetch('/api/v1/crm/kontakte/' + k.kontakt_id, { method: 'DELETE', credentials: 'same-origin' });
                            const j = await r.json();
                            if (!j.success) throw new Error('Kontakt ' + k.kontakt_id + ': ' + j.message);
                        }
                    } else {
                        // backlog oder privat: firma_status setzen (firma_id wird vom Backend auf NULL gesetzt)
                        const status = kontaktAktion === 'privat' ? 'ohne_firmenbezug' : 'pflege_offen';
                        for (const k of kontakte) {
                            const r = await fetch('/api/v1/crm/pflege?action=set_firma_status', {
                                method: 'POST', credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ kontakt_id: k.kontakt_id, status })
                            });
                            const j = await r.json();
                            if (!j.success) throw new Error('Kontakt ' + k.kontakt_id + ': ' + j.message);
                        }
                    }
                }
                // Dann Firmen
                for (const id of firmaIds) {
                    const r = await fetch('/api/v1/crm/firmen/' + id, { method: 'DELETE', credentials: 'same-origin' });
                    const j = await r.json();
                    if (!j.success) throw new Error('Firma ' + id + ': ' + j.message);
                }
                await this.markiereErledigt('geloescht_existiert_nicht_mehr');
                const aktTexte = { delete: 'mitgelöscht', backlog: 'im Pflege-Backlog geparkt', privat: 'als privat markiert' };
                const msg = kontakte.length > 0
                    ? `Firma gelöscht, ${kontakte.length} Kontakt${kontakte.length === 1 ? '' : 'e'} ${aktTexte[kontaktAktion]}.`
                    : 'Firma gelöscht.';
                this.zeigeUndoToast(msg, null);
                await this.ladeNext();
                this.ladeStats();
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.aktiv = false;
        },

        async aktionMerge() {
            const typ = this.aktuell.typ === 'dublette_firma' ? 'firma' : 'kontakt';
            const loserIds = this.merge.records.filter(r => r.id !== this.merge.masterId).map(r => r.id);
            // Sende NUR Felder, deren neuer Master-Wert sich vom DB-Master-Wert unterscheidet
            const fieldValues = {};
            const master = this.merge.records.find(r => r.id === this.merge.masterId);
            for (const feldKey of Object.keys(this.merge.masterValues)) {
                const neu = this.merge.masterValues[feldKey];
                const alt = master?.[feldKey];
                if ((neu === null || neu === '') && (alt === null || alt === '' || alt === undefined)) continue;
                if (String(neu) !== String(alt ?? '')) {
                    fieldValues[feldKey] = neu;
                }
            }
            const r = await fetch('/api/v1/crm/pflege?action=merge', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    typ, master_id: this.merge.masterId, loser_ids: loserIds,
                    field_values: fieldValues, issue_id: this.aktuell.id
                })
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
        },

        async aktionFeldSetzen(feld, wert) {
            const e = (this.aktuell.entities || [])[0];
            if (!e) throw new Error('Keine Entity');
            const url = e.typ === 'firma' ? '/api/v1/crm/firmen/' + e.id : '/api/v1/crm/kontakte/' + e.id + '/inline';
            const body = e.typ === 'firma' ? { [feld]: wert } : { feld, wert };
            const r = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            await this.markiereErledigt('manuell_korrigiert');
        },

        async markiereErledigt(aktion) {
            await fetch('/api/v1/crm/pflege?action=issue_status', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issue_id: this.aktuell.id, neuer_status: 'erledigt', notiz: aktion })
            });
        },

        async skipIssue() {
            // Skip — gehe zum nächsten ohne Aktion (Issue bleibt offen)
            await this.ladeNext();
        },

        async ignoriereIssue() {
            await fetch('/api/v1/crm/pflege?action=issue_status', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issue_id: this.aktuell.id, neuer_status: 'ignoriert' })
            });
            this.zeigeUndoToast('Ignoriert.', null);
            await this.ladeNext();
            this.ladeStats();
        },

        async loescheKontakt() {
            const kontaktIds = this.aktuell?.typ === 'dublette_kontakt'
                ? this.merge.records.map(r => r.id)
                : [this.merge.records[0]?.id || (this.aktuell?.entities || [])[0]?.id].filter(Boolean);
            if (kontaktIds.length === 0) return;
            const namen = this.merge.records.length > 0
                ? this.merge.records.map(r => ((r.vorname || '') + ' ' + (r.nachname || '')).trim() || r.email_primaer || ('#' + r.id)).join(', ')
                : 'Kontakt #' + kontaktIds[0];
            const txt = kontaktIds.length === 1
                ? `Kontakt „${namen}" wirklich löschen? (Soft-Delete)`
                : `${kontaktIds.length} Kontakte (${namen}) wirklich löschen? (Soft-Delete)`;
            if (!confirm(txt)) return;
            this.aktiv = true;
            try {
                for (const id of kontaktIds) {
                    const r = await fetch('/api/v1/crm/kontakte/' + id, { method: 'DELETE', credentials: 'same-origin' });
                    const j = await r.json();
                    if (!j.success) throw new Error('Kontakt ' + id + ': ' + j.message);
                }
                await this.markiereErledigt('manuell_geloescht');
                this.zeigeUndoToast(kontaktIds.length === 1 ? 'Kontakt gelöscht.' : `${kontaktIds.length} Kontakte gelöscht.`, null);
                await this.ladeNext();
                this.ladeStats();
            } catch (e) {
                App.showNotification(e.message, 'error');
            }
            this.aktiv = false;
        },

        async loescheTag() {
            const e = (this.aktuell.entities || [])[0];
            if (!e) return;
            const r = await fetch('/api/v1/crm/tags/' + e.id, { method: 'DELETE', credentials: 'same-origin' });
            const j = await r.json();
            if (j.success) {
                await this.markiereErledigt('manuell_erledigt');
                this.zeigeUndoToast('Tag gelöscht.', null);
                await this.ladeNext();
                this.ladeStats();
            }
        },

        async scanJetzt() {
            this.scannt = true;
            try {
                const r = await fetch('/api/v1/crm/pflege?action=scan', { method: 'POST', credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.letzterScan = new Date().toLocaleString('de-DE');
                    App.showNotification('Scan abgeschlossen', 'success');
                    await this.ladeStats();
                    if (this.aktiveKat) await this.startWizard(this.aktiveKat);
                }
            } catch (e) { App.showNotification(e.message, 'error'); }
            this.scannt = false;
        },

        zeigeUndoToast(text, undoFn) {
            if (this.undoToast.timeout) clearTimeout(this.undoToast.timeout);
            this.undoToast.text = text;
            this.undoToast.undo = undoFn;
            this.undoToast.timeout = setTimeout(() => { this.undoToast.text = ''; this.undoToast.undo = null; }, 4000);
        },

        // ─── Tastatur ───
        handleHotkey(ev) {
            if (!this.aktiveKat || !this.aktuell) return;
            // Wenn in Eingabefeld: nur Enter behandeln, sonst durchlassen
            const inInput = ['INPUT','TEXTAREA','SELECT'].includes(ev.target.tagName);
            if (ev.key === 'Enter') {
                if (inInput && ev.target.type !== 'checkbox') {
                    ev.preventDefault();
                    this.annehmen();
                } else if (!inInput) {
                    ev.preventDefault();
                    this.annehmen();
                }
                return;
            }
            if (inInput) return; // andere Tasten in Inputs nicht abfangen
            if (ev.key === 's' || ev.key === 'S' || ev.key === 'ArrowRight') { ev.preventDefault(); this.skipIssue(); }
            else if (ev.key === 'n' || ev.key === 'N') { ev.preventDefault(); this.ignoriereIssue(); }
            else if (ev.key === 'd' || ev.key === 'D') {
                // D: Tag/Firma/Kontakt löschen — je nach Issue-Typ
                if (this.aktuell?.typ === 'verwaister_tag') { ev.preventDefault(); this.loescheTag(); }
                else if (this.loeschenVerfuegbar() && this.kiZielTyp() === 'firma') { ev.preventDefault(); this.loescheFirma(); }
                else if (this.loeschenVerfuegbar() && this.kiZielTyp() === 'kontakt') { ev.preventDefault(); this.loescheKontakt(); }
            }
            else if (ev.key === 'Escape') { ev.preventDefault(); this.aktiveKat = null; this.aktuell = null; }
        },
    };
}
</script>
