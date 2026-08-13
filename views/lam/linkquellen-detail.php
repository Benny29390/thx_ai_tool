<?php
/**
 * Linkquellen-Detail-Seite — /lam/linkquellen/{id}
 * Server-rendered, nach Original-Layout des Prototypen
 * + bewahrte Verbesserungen: Backlinks-Aggregat, Domain-Wissen, Audit-Log.
 */
use Core\Database;
use Services\LamService;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$d = $svc->getDomainDetail($domainId ?? '');

if (!$d) {
    echo '<div class="thx-page-header"><h1 class="thx-page-title">Linkquelle nicht gefunden</h1></div>';
    echo '<a href="/lam/linkquellen" style="color:var(--thoxan-700);">‹ Zurück zur Liste</a>';
    return;
}

$alleAnbieter = $svc->listeAnbieterKurz();
$alleKunden = $svc->listeKundenKurz();
$alleTags = $svc->listeTagsKurz();
// Sistrix-Wochenstatus für die Budget-Anzeige
require_once SERVICES_PATH . '/SistrixService.php';
$sistrixSvc = new \Services\SistrixService(Database::getInstance());
$sistrixStatus = $sistrixSvc->wochenStatus();
// Zugeordnete IDs
$zugeordnetKundenIds = array_map(fn($c) => (int)$c['id'], $d['kunden']);
$zugeordnetTagIds = array_map(fn($t) => (int)$t['id'], $d['tags']);

$activeModul = 'linkquellen';

$statusStyle = function($status) {
    $m = [
        'neu' => 'background:var(--amber-100);color:var(--amber-800);',
        'in_arbeit' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
        'geprueft' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'verifiziert' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'veraltet' => 'background:#fff7ed;color:#9a3412;',
        'geloescht' => 'background:var(--rose-100);color:var(--rose-800);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};
$massnahmeStatusStyle = function($status) {
    $m = [
        'idee' => 'background:var(--slate-100);color:var(--slate-700);',
        'vorgeschlagen' => 'background:var(--amber-100);color:var(--amber-800);',
        'geplant' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
        'beauftragt' => 'background:var(--indigo-100);color:var(--indigo-700);',
        'live' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'storniert' => 'background:var(--rose-100);color:var(--rose-800);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};

// SI-Wert (neuestes Snapshot)
$aktuellesSi = $d['kennzahlen'][0]['si'] ?? null;
$aktuelleQuelle = $d['kennzahlen'][0]['quelle'] ?? null;

$euro = function($n) {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, 0, ',', '.') . ' €';
};
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div x-data="lamDomainDetail()">

    <!-- Header -->
    <div class="thx-page-header">
        <div>
            <a href="/lam/linkquellen" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zu Linkquellen</a>
            <h1 class="thx-page-title" style="margin-top:4px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <template x-if="!urlEdit">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <a :href="'https://' + url" target="_blank" rel="noopener" style="color:var(--slate-800);text-decoration:none;" title="In neuem Tab öffnen" x-text="url + ' ↗'"></a>
                        <button @click="urlEdit = true" title="URL bearbeiten" style="background:none;border:0;cursor:pointer;color:var(--slate-400);font-size:0.9rem;padding:2px 6px;border-radius:4px;" onmouseover="this.style.background='var(--slate-100)';" onmouseout="this.style.background='none';">
                            <span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;">edit</span>
                        </button>
                    </span>
                </template>
                <template x-if="urlEdit">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <input type="text" x-model="url" @keydown.enter="speichereUrl()" @keydown.escape="urlEdit = false; url = <?= json_encode($d['url']) ?>"
                               style="font-size:1.2rem;font-weight:600;padding:4px 10px;border:1px solid var(--thoxan-400);border-radius:6px;min-width:320px;">
                        <button @click="speichereUrl()" class="lam-btn lam-btn-primary lam-btn-small">OK</button>
                        <button @click="urlEdit = false; url = <?= json_encode($d['url']) ?>" class="lam-btn lam-btn-secondary lam-btn-small">×</button>
                    </span>
                </template>
                <span class="lam-badge" :style="statusStyleFor(verifikation_status) + ';font-size:var(--d-fs-sm);'" x-text="statusLabel(verifikation_status)"></span>
                <?php if ($d['disqualifiziert']): ?>
                    <span class="lam-badge" style="background:var(--rose-600);color:#fff;">disqualifiziert</span>
                <?php endif; ?>
                <?php if ($aktuellesSi !== null): ?>
                    <span style="font-size:var(--d-fs-sm);color:var(--slate-500);font-weight:normal;">
                        SI <strong style="color:var(--slate-800);"><?= number_format((float)$aktuellesSi, 4, ',', '.') ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($d['letzter_check_am'])): ?>
                    <span style="font-size:var(--d-fs-sm);color:var(--slate-500);font-weight:normal;">Check <?= htmlspecialchars(substr($d['letzter_check_am'], 0, 16)) ?></span>
                <?php endif; ?>
            </h1>
            <?php if (!empty($alleTags)): ?>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                    <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-right:4px;">Tags</span>
                    <?php foreach ($alleTags as $t):
                        $aktiv = in_array((int)$t['id'], $zugeordnetTagIds, true);
                    ?>
                        <button type="button"
                                class="lam-chip lam-chip-kunde<?= $aktiv ? ' is-active' : '' ?>"
                                @click="toggleTag(<?= (int)$t['id'] ?>)"
                                title="<?= htmlspecialchars($t['name']) ?> <?= $aktiv ? '(zugeordnet)' : '(nicht zugeordnet)' ?>">
                            <?= htmlspecialchars($t['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="thx-page-actions">
            <button class="lam-btn lam-btn-danger" @click="loeschen()">Löschen</button>
        </div>
    </div>

    <?php include __DIR__ . '/_tabs.php'; ?>

    <!-- Aktionen-Bar -->
    <section class="lam-card" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-right:8px;">Status</span>
            <button class="lam-btn lam-btn-small" :style="verifikation_status === 'neu' ? 'background:var(--amber-100);color:var(--amber-800);border-color:var(--amber-300);' : 'background:#fff;color:var(--slate-700);border:1px solid var(--slate-300);'" @click="setzeStatus('neu')" title="Als neu markieren">Neu</button>
            <button class="lam-btn lam-btn-small" :style="verifikation_status === 'in_arbeit' ? 'background:var(--thoxan-100);color:var(--thoxan-700);border-color:var(--thoxan-300);' : 'background:#fff;color:var(--slate-700);border:1px solid var(--slate-300);'" @click="setzeStatus('in_arbeit')" title="Als in Arbeit markieren">In Arbeit</button>
            <button class="lam-btn lam-btn-small" :style="verifikation_status === 'geprueft' ? 'background:var(--emerald-100);color:var(--emerald-800);border-color:var(--emerald-300);' : 'background:#fff;color:var(--slate-700);border:1px solid var(--slate-300);'" @click="setzeStatus('geprueft')" title="Als geprüft markieren">Geprüft</button>
            <button class="lam-btn lam-btn-small" :style="verifikation_status === 'veraltet' ? 'background:#fed7aa;color:#9a3412;border-color:#fdba74;' : 'background:#fff;color:var(--slate-700);border:1px solid var(--slate-300);'" @click="setzeStatus('veraltet')" title="Als veraltet markieren">Veraltet</button>
            <button class="lam-btn lam-btn-small" :style="verifikation_status === 'geloescht' ? 'background:var(--rose-100);color:var(--rose-800);border-color:var(--rose-300);' : 'background:#fff;color:var(--slate-700);border:1px solid var(--slate-300);'" @click="setzeStatus('geloescht')" title="Als gelöscht markieren">Gelöscht</button>

            <span style="width:1px;height:24px;background:var(--slate-300);margin:0 4px;"></span>

            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-right:4px;">Sistrix</span>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="sistrixStub('si')" title="Sichtbarkeitsindex (1 Credit)">SI · 1</button>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="sistrixStub('alter')" title="Sichtbar seit (10 Credits)">Alter · 10</button>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="sistrixStub('dp')" title="Verlinkende Domains (25 Credits)">DP · 25</button>
            <button class="lam-btn lam-btn-accent lam-btn-small" @click="sistrixStub('alles')" title="Alles in einem Rutsch (36 Credits)">Alles · 36</button>

            <span style="width:1px;height:24px;background:var(--slate-300);margin:0 4px;"></span>

            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="erreichbarkeitStub()">Erreichbarkeit</button>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="kiTagsVorschlag()">🏷 KI-Tags</button>
            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="kiRecherche()">🔍 KI-Recherche</button>

            <?php if ($d['disqualifiziert']): ?>
                <span style="width:1px;height:24px;background:var(--slate-300);margin:0 4px;"></span>
                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="rehabilitieren()">Rehabilitieren</button>
            <?php else: ?>
                <span style="width:1px;height:24px;background:var(--slate-300);margin:0 4px;"></span>
                <button class="lam-btn lam-btn-small" style="background:var(--rose-100);color:var(--rose-800);" @click="disqualifizieren()">Disqualifizieren</button>
            <?php endif; ?>

            <span style="margin-left:auto;font-size:var(--d-fs-xs);color:var(--slate-400);">
                Sistrix-Budget
                <strong style="color:var(--slate-700);"><?= number_format($sistrixStatus['credits_verbleibend'], 0, ',', '.') ?></strong>
                / <?= number_format($sistrixStatus['wochenkontingent'], 0, ',', '.') ?>
                <?php if (!$sistrixStatus['konfiguriert']): ?>
                    · <a href="/admin/settings?tab=sistrix" style="color:var(--thoxan-700);">Key setzen</a>
                <?php endif; ?>
            </span>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

        <!-- LINKS -->
        <section style="display:flex;flex-direction:column;gap:20px;">

            <!-- ANBIETER & KONTAKTE -->
            <div class="lam-card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:18px;">
                    <h3 style="margin:0;">Anbieter & Kontakte</h3>
                    <button class="lam-btn lam-btn-primary lam-btn-small" @click="anbPicker.offen = true">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                        Anbieter zuordnen
                    </button>
                </div>
                <!-- Anbieter-Liste: manuelle Reihenfolge, jeder Anbieter kann Betreiber + Vermittler zugleich sein -->
                <?php if (!empty($d['anbieter_liste'])): ?>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($d['anbieter_liste'] as $idx => $a):
                            $istBetreiber = (int) $a['dom_betreiber'] === 1;
                            $istVermittler = (int) $a['dom_vermittler'] === 1;
                            $istErster = $idx === 0;
                            $istLetzter = $idx === count($d['anbieter_liste']) - 1;
                        ?>
                        <div style="background:#fff;border:1px solid var(--slate-200);border-radius:8px;padding:18px 20px;display:flex;gap:16px;align-items:flex-start;">
                            <!-- Position-Pfeile (nur wenn mehr als 1 Anbieter) -->
                            <?php if (count($d['anbieter_liste']) > 1): ?>
                            <div style="display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;flex-shrink:0;color:var(--slate-400);">
                                <button @click="anbieterVerschieben('<?= htmlspecialchars($a['junction_id']) ?>', -1)"
                                        :disabled="<?= $istErster ? 'true' : 'false' ?>"
                                        title="Nach oben"
                                        style="background:none;border:0;cursor:pointer;color:inherit;padding:2px 6px;font-size:0.85rem;<?= $istErster ? 'opacity:0.2;cursor:default;' : '' ?>">▲</button>
                                <button @click="anbieterVerschieben('<?= htmlspecialchars($a['junction_id']) ?>', 1)"
                                        :disabled="<?= $istLetzter ? 'true' : 'false' ?>"
                                        title="Nach unten"
                                        style="background:none;border:0;cursor:pointer;color:inherit;padding:2px 6px;font-size:0.85rem;<?= $istLetzter ? 'opacity:0.2;cursor:default;' : '' ?>">▼</button>
                            </div>
                            <?php endif; ?>

                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                            <strong style="font-size:1.1rem;color:var(--slate-800);"><?= htmlspecialchars($a['name'] ?: '—') ?></strong>
                                            <?php if ($istBetreiber): ?>
                                                <span style="border:1px solid var(--slate-300);color:var(--slate-600);padding:2px 10px;border-radius:12px;font-size:0.75rem;">Betreiber</span>
                                            <?php endif; ?>
                                            <?php if ($istVermittler): ?>
                                                <span style="border:1px solid var(--slate-300);color:var(--slate-600);padding:2px 10px;border-radius:12px;font-size:0.75rem;">Vermittler</span>
                                            <?php endif; ?>
                                            <?php if (!$istBetreiber && !$istVermittler): ?>
                                                <span style="color:var(--slate-400);font-size:0.8rem;">ohne Rolle</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($a['firma']) && $a['firma'] !== $a['name']): ?>
                                            <div style="font-size:0.9rem;color:var(--slate-500);margin-bottom:6px;">
                                                <?= htmlspecialchars($a['firma']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($a['hauptkontakt_email']) || !empty($a['hauptkontakt_telefon'])): ?>
                                            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:0.9rem;">
                                                <?php if (!empty($a['hauptkontakt_email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($a['hauptkontakt_email']) ?>" style="color:var(--thoxan-700);text-decoration:none;"><?= htmlspecialchars($a['hauptkontakt_email']) ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($a['hauptkontakt_telefon'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($a['hauptkontakt_telefon']) ?>" style="color:var(--slate-600);text-decoration:none;"><?= htmlspecialchars($a['hauptkontakt_telefon']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;gap:14px;align-items:center;flex-shrink:0;font-size:0.85rem;">
                                        <a href="/lam/anbieter/<?= htmlspecialchars($a['id']) ?>" style="color:var(--thoxan-700);text-decoration:none;white-space:nowrap;">Detail →</a>
                                        <button @click="anbieterEntfernen('<?= htmlspecialchars($a['junction_id']) ?>', '<?= htmlspecialchars(addslashes($a['name'])) ?>')"
                                                style="background:none;border:0;color:var(--slate-400);cursor:pointer;font-size:0.85rem;padding:0;text-decoration:underline;">
                                            entfernen
                                        </button>
                                    </div>
                                </div>

                                <!-- Rollen-Checkboxen -->
                                <div style="display:flex;gap:18px;padding-top:12px;margin-top:12px;border-top:1px solid var(--slate-100);font-size:0.85rem;color:var(--slate-600);">
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                        <input type="checkbox" <?= $istBetreiber ? 'checked' : '' ?>
                                               @change="anbieterFlagsSetzen('<?= htmlspecialchars($a['junction_id']) ?>', 'betreiber', $event.target.checked)">
                                        <span>Betreiber</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                        <input type="checkbox" <?= $istVermittler ? 'checked' : '' ?>
                                               @change="anbieterFlagsSetzen('<?= htmlspecialchars($a['junction_id']) ?>', 'vermittler', $event.target.checked)">
                                        <span>Vermittler</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding:24px;background:var(--slate-50);border:1px dashed var(--slate-300);border-radius:8px;text-align:center;font-size:var(--d-fs-sm);color:var(--slate-500);">
                        <div style="font-size:1.5rem;margin-bottom:6px;opacity:0.5;">🏢</div>
                        Noch kein Anbieter zugeordnet. <br>
                        <strong>Impressum crawlen</strong> oder oben Anbieter zuordnen.
                    </div>
                <?php endif; ?>

                <!-- Type-Ahead-Picker für Anbieter (Modal) -->
                <div x-show="anbPicker.offen" x-cloak
                     style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:80px;"
                     @click.self="anbPicker.offen = false">
                    <div style="background:#fff;border-radius:10px;width:560px;max-width:calc(100% - 40px);box-shadow:0 12px 32px rgba(15,23,42,0.18);overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="margin:0;font-size:1rem;">Anbieter zuordnen</h3>
                            <button @click="anbPicker.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
                        </div>
                        <div style="padding:14px 20px;display:flex;flex-direction:column;gap:10px;">
                            <input type="text" x-model="anbPicker.suche" @input="anbPickerFiltern()" placeholder="Name, Firma, E-Mail eintippen — mind. 2 Zeichen" autofocus
                                   style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.95rem;">
                            <div style="display:flex;gap:10px;align-items:center;">
                                <label style="font-size:var(--d-fs-xs);color:var(--slate-500);">Rolle für diese Domain:</label>
                                <select x-model="anbPicker.rolle" style="padding:4px 8px;font-size:var(--d-fs-sm);border:1px solid var(--slate-300);border-radius:4px;">
                                    <option value="betreiber">Betreiber</option>
                                    <option value="vermittler">Vermittler</option>
                                </select>
                            </div>
                            <div style="max-height:380px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;background:#fafbfc;">
                                <template x-for="a in anbPicker.treffer" :key="a.id">
                                    <button type="button"
                                            @click="anbieterZuordnen(a)"
                                            style="display:block;width:100%;text-align:left;padding:10px 14px;background:none;border:0;border-bottom:1px solid var(--slate-100);cursor:pointer;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                            <div style="font-weight:600;color:var(--slate-800);" x-text="a.name"></div>
                                            <div style="display:flex;gap:4px;">
                                                <span x-show="a.ist_betreiber" class="lam-chip" style="font-size:0.6rem;background:var(--emerald-100);color:var(--emerald-800);padding:1px 6px;">Betreiber</span>
                                                <span x-show="a.ist_vermittler" class="lam-chip" style="font-size:0.6rem;background:var(--amber-100);color:var(--amber-800);padding:1px 6px;">Vermittler</span>
                                            </div>
                                        </div>
                                        <div x-show="a.firma" style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;" x-text="a.firma"></div>
                                    </button>
                                </template>
                                <div x-show="anbPicker.suche.length >= 2 && anbPicker.treffer.length === 0" style="padding:20px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
                                    Keine Treffer für „<span x-text="anbPicker.suche"></span>".
                                </div>
                                <div x-show="anbPicker.suche.length < 2" style="padding:20px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-sm);">
                                    Mindestens 2 Zeichen eingeben.
                                </div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:1px solid var(--slate-100);">
                                <button class="lam-btn lam-btn-secondary lam-btn-small" @click="anbieterNeuAnlegen()">+ Neuer Anbieter</button>
                                <span style="font-size:var(--d-fs-xs);color:var(--slate-400);" x-show="anbPicker.treffer.length > 0" x-text="anbPicker.treffer.length + ' Treffer'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--slate-200);">
                    <div style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-bottom:8px;">Anbieter ermitteln</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <button class="lam-btn lam-btn-primary lam-btn-small" @click="impressumCrawl()" :disabled="impressumLaeuft" style="align-self:flex-start;">
                            <span x-show="!impressumLaeuft">🔎 Impressum crawlen + Betreiber extrahieren</span>
                            <span x-show="impressumLaeuft">Lädt Impressum + KI-Analyse …</span>
                        </button>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);">oder URL manuell:</span>
                            <input type="text" x-model="impressumUrl" class="thx-input" placeholder="https://domain.de/impressum" style="flex:1;">
                            <button class="lam-btn lam-btn-secondary lam-btn-small" @click="speichereImpressum()">setzen</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KONDITIONEN -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;">Konditionen (<?= count($d['konditionen']) ?>)</h3>
                    <button class="lam-btn lam-btn-accent lam-btn-small" @click="oeffneKonditionDrawer(null)">+ neu</button>
                </div>
                <?php if (!empty($d['konditionen'])): ?>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <thead><tr><th>Buchungstyp</th><th class="right">Preis</th><th>Via</th><th>Notiz</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($d['konditionen'] as $k): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($k['buchungstyp'] ?: '—') ?>
                                        <?php if (!empty($k['inkl_text'])): ?>
                                            <span class="lam-badge" style="background:var(--emerald-100);color:var(--emerald-800);font-size:var(--d-fs-xs);margin-left:4px;" title="Texterstellung im Preis enthalten">inkl. Text</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="right">
                                        <?php if ($k['preis'] !== null): ?>
                                            <?= number_format((float)$k['preis'], 0, ',', '.') ?> €
                                        <?php else: ?>
                                            <span class="empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($k['via_anbieter_name'] ?: 'direkt') ?></td>
                                    <td><span class="muted"><?= htmlspecialchars($k['notiz'] ?? '—') ?></span></td>
                                    <td>
                                        <select class="thx-inline-edit-select" style="font-size:var(--d-fs-xs);"
                                                onchange="window.aktualisiereKonditionVerifikation('<?= htmlspecialchars($k['id']) ?>', this.value)">
                                            <?php foreach (\Services\LamService::VERIFIKATION_STATUS as $v): ?>
                                                <option value="<?= $v ?>" <?= ($k['verifikation_status'] ?? 'neu') === $v ? 'selected' : '' ?>><?= $v ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button class="thx-inline-edit-pen" @click='oeffneKonditionDrawer(<?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Bearbeiten">✎</button>
                                        <button class="thx-inline-edit-pen" @click="loescheKondition('<?= htmlspecialchars($k['id']) ?>')" title="Löschen" style="color:var(--rose-600);">✕</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Keine Konditionen erfasst.</p>
                <?php endif; ?>
                <p class="muted" style="margin-top:10px;font-size:var(--d-fs-xs);"><em>Medien-Anhänge (Mediadaten als PDF/Bild) folgen in einer späteren Iteration.</em></p>
            </div>

            <!-- KUNDEN (Toggle-Chips) -->
            <div class="lam-card">
                <h3>Kunden</h3>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 10px 0;">
                    Klick auf Kürzel = aktivieren/deaktivieren. Aktivieren setzt den Status auf <strong>„In Arbeit"</strong>, wenn er noch „Neu" oder „Veraltet" war.
                </p>
                <div class="lam-chip-row">
                    <?php foreach ($alleKunden as $c):
                        $aktiv = in_array((int)$c['id'], $zugeordnetKundenIds, true);
                    ?>
                        <button class="lam-chip lam-chip-kunde<?= $aktiv ? ' is-active' : '' ?>"
                                @click="toggleKunde(<?= (int)$c['id'] ?>)"
                                title="<?= htmlspecialchars($c['name']) ?>">
                            <?= htmlspecialchars($c['abbreviation']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- AKTIVITÄT (Linkoptionen + Maßnahmen) -->
            <div class="lam-card">
                <h3>Aktivität</h3>

                <!-- Linkoptionen-Listen -->
                <div style="margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">In Linkoptionen-Listen</span>
                        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneLinkpoolModal()">+ in Linkpool</button>
                    </div>
                    <?php if (!empty($d['linkoptionen'])): ?>
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Liste</th><th>Status</th><th class="right">Preis</th></tr></thead>
                            <tbody>
                                <?php foreach ($d['linkoptionen'] as $lo): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($lo['customer_kuerzel'] ?: '—') ?></strong></td>
                                        <td><a href="/lam/linkoptionen/<?= htmlspecialchars($lo['id']) ?>" style="color:var(--thoxan-700);"><?= htmlspecialchars($lo['liste_titel']) ?></a></td>
                                        <td><?= htmlspecialchars($lo['status']) ?></td>
                                        <td class="right">
                                            <?php if ($lo['preis_kunde'] !== null): ?>
                                                <?= number_format((float)$lo['preis_kunde'], 0, ',', '.') ?> €
                                            <?php else: ?>
                                                <span class="empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">— nicht in einer Linkoption</p>
                    <?php endif; ?>
                </div>

                <!-- Maßnahmen -->
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Maßnahmen</span>
                        <button class="lam-btn lam-btn-secondary lam-btn-small" @click="oeffneMassnahmeModal()">+ neue Maßnahme</button>
                    </div>
                    <?php if (!empty($d['massnahmen'])): ?>
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Kunde</th><th>Status</th><th>Linktext</th><th>Geplant/Veröffentlicht</th></tr></thead>
                            <tbody>
                                <?php
                                $massnahmeStatusLabel = fn($s) => \Services\LamService::MASSNAHME_STATUS_LABELS[$s] ?? $s;
                                ?>
                                <?php foreach ($d['massnahmen'] as $m): ?>
                                    <tr>
                                        <td><a href="/lam/massnahmen/<?= htmlspecialchars($m['id']) ?>" style="color:var(--thoxan-700);"><strong><?= htmlspecialchars($m['customer_kuerzel'] ?: '—') ?></strong></a></td>
                                        <td><span class="lam-badge" style="<?= $massnahmeStatusStyle($m['status']) ?>"><?= htmlspecialchars($massnahmeStatusLabel($m['status'])) ?></span></td>
                                        <td><?= htmlspecialchars($m['linktext'] ?? '') ?: '<span class="empty">—</span>' ?></td>
                                        <td>
                                            <?php if (!empty($m['veroeffentlicht_am'])): ?>
                                                ✓ <?= htmlspecialchars($m['veroeffentlicht_am']) ?>
                                            <?php elseif (!empty($m['geplant_am'])): ?>
                                                geplant <?= htmlspecialchars($m['geplant_am']) ?>
                                            <?php else: ?>
                                                <span class="empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">— keine Maßnahme auf dieser Domain</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BACKLINKS aus Linkprofil pro Kunde (bewahrt - im Original nicht so sichtbar) -->
            <div class="lam-card">
                <h3>Backlinks aus Linkprofil pro Kunde</h3>
                <?php if (!empty($d['verlinkungen_pro_kunde'])): ?>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <thead>
                            <tr>
                                <th>Kunde</th>
                                <th class="right">Gesamt</th>
                                <th class="right">Neu</th>
                                <th class="right">Lassen</th>
                                <th class="right">Disavow</th>
                                <th class="right">Ohne Empf.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['verlinkungen_pro_kunde'] as $v): ?>
                                <tr>
                                    <td>
                                        <a href="/lam/linkprofil?customer_id=<?= (int)$v['customer_id'] ?>" style="color:var(--thoxan-700);text-decoration:none;">
                                            <strong><?= htmlspecialchars($v['abbreviation']) ?></strong>
                                            <span class="muted"> · <?= htmlspecialchars($v['customer_name']) ?></span>
                                        </a>
                                    </td>
                                    <td class="right"><?= (int)$v['anzahl_gesamt'] ?></td>
                                    <td class="right"><?= (int)$v['anzahl_neu'] ?></td>
                                    <td class="right"><?= (int)$v['anzahl_lassen'] ?></td>
                                    <td class="right" style="color:var(--rose-600);"><?= (int)$v['anzahl_disavow'] ?></td>
                                    <td class="right"><?= (int)$v['anzahl_ohne_empfehlung'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Keine Verlinkungen aus dem Linkprofil-Modul.</p>
                <?php endif; ?>
            </div>

            <!-- KENNZAHL-HISTORIE -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" @click="kennzahlOffen = !kennzahlOffen">
                    <h3 style="margin:0;">Kennzahl-Historie (<?= count($d['kennzahlen']) ?>)</h3>
                    <span style="font-size:var(--d-fs-lg);color:var(--slate-400);" x-text="kennzahlOffen ? '▾' : '▸'"></span>
                </div>
                <div x-show="kennzahlOffen" x-cloak style="margin-top:12px;">
                    <?php if (!empty($d['kennzahlen'])): ?>
                        <table class="lam-table" style="font-size:var(--d-fs-sm);">
                            <thead><tr><th>Erfasst</th><th class="right">SI</th><th class="right">DP</th><th class="right">Alter</th><th>Quelle</th></tr></thead>
                            <tbody>
                                <?php foreach ($d['kennzahlen'] as $kz): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($kz['erfasst_am']) ?></td>
                                        <td class="right"><?= $kz['si'] !== null ? number_format((float)$kz['si'], 2, ',', '.') : '—' ?></td>
                                        <td class="right"><?= $kz['dp'] !== null ? (int)$kz['dp'] : '—' ?></td>
                                        <td class="right"><?= $kz['domain_alter'] !== null ? (int)$kz['domain_alter'] : '—' ?></td>
                                        <td><span class="muted"><?= htmlspecialchars($kz['quelle'] ?: '—') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted">Noch keine Kennzahl-Snapshots.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
                $wissen = $d['domain_wissen'] ?? [];
                $host = parse_url($d['url'], PHP_URL_HOST) ?: $d['url'];
            ?>
            <!-- DOMAIN-WISSEN: ausgelagert in /lam/domain-wissensbasis. Hier nur kompakte Anzeige falls KI was klassifiziert hat. -->
            <?php if (false): // Block deaktiviert — Wissensbasis ist die richtige Stelle, hier war es redundant ?>
            <div class="lam-card" x-data="domainWissenBox(<?= htmlspecialchars(json_encode([
                'domain' => $host,
                'linkart' => $wissen['linkart'] ?? '',
                'reduktionsstrategie' => $wissen['reduktionsstrategie'] ?? '',
                'empfehlung_default' => $wissen['empfehlung_default'] ?? '',
                'branche' => $wissen['branche'] ?? '',
                'thema' => $wissen['thema'] ?? '',
                'tonalitaet' => $wissen['tonalitaet'] ?? '',
                'risikofaktoren' => $wissen['risikofaktoren'] ?? '',
                'notiz' => $wissen['notiz'] ?? '',
                'confidence' => $wissen['confidence'] ?? '',
                'klassifikationen' => (int)($wissen['anzahl_klassifikationen'] ?? 0),
                'manuell' => (int)($wissen['manuell_gepflegt'] ?? 0),
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <h3 style="margin:0;">Domain-Wissen
                        <span x-show="form.manuell" class="lam-badge" style="margin-left:6px;background:var(--emerald-100);color:var(--emerald-800);font-size:var(--d-fs-xs);">manuell gepflegt</span>
                        <span x-show="!form.manuell && form.klassifikationen > 0" class="lam-badge" style="margin-left:6px;background:var(--slate-200);color:var(--slate-700);font-size:var(--d-fs-xs);">KI-klassifiziert</span>
                    </h3>
                    <div style="display:flex;gap:6px;">
                        <button class="lam-btn lam-btn-sm" x-show="!bearbeiten && (form.linkart || form.empfehlung_default)" @click="anwenden()" :disabled="anwendenLaeuft"
                                title="Linkart und Empfehlung auf alle bekannten Verlinkungen dieser Domain ausrollen (kundenübergreifend)">
                            <span x-show="!anwendenLaeuft">→ anwenden</span>
                            <span x-show="anwendenLaeuft">…</span>
                        </button>
                        <button class="lam-btn lam-btn-sm" x-show="!bearbeiten" @click="bearbeiten = true">bearbeiten</button>
                    </div>
                </div>
                <div style="margin-top:8px;font-size:var(--d-fs-xs);color:var(--slate-500);"><?= htmlspecialchars($host) ?></div>

                <!-- Read-Mode -->
                <table class="lam-table" style="font-size:var(--d-fs-sm);margin-top:10px;" x-show="!bearbeiten">
                    <tbody>
                        <tr><td class="muted" style="width:35%;">Linkart</td><td x-text="form.linkart || '—'"></td></tr>
                        <tr><td class="muted">Branche</td><td x-text="form.branche || '—'"></td></tr>
                        <tr><td class="muted">Thema</td><td x-text="form.thema || '—'"></td></tr>
                        <tr><td class="muted">Tonalität</td><td x-text="form.tonalitaet || '—'"></td></tr>
                        <tr><td class="muted">Reduktions-Strategie</td><td x-text="form.reduktionsstrategie || '—'"></td></tr>
                        <tr><td class="muted">Default-Empfehlung</td><td x-text="form.empfehlung_default || '—'"></td></tr>
                        <tr x-show="form.confidence"><td class="muted">KI-Confidence</td><td x-text="form.confidence"></td></tr>
                        <tr x-show="form.klassifikationen > 0"><td class="muted">KI-Klassifikationen</td><td x-text="form.klassifikationen"></td></tr>
                        <tr x-show="form.risikofaktoren">
                            <td class="muted">Risikofaktoren</td>
                            <td style="white-space:pre-wrap;" x-text="form.risikofaktoren"></td>
                        </tr>
                        <tr x-show="form.notiz">
                            <td class="muted">Notiz</td>
                            <td style="white-space:pre-wrap;" x-text="form.notiz"></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Edit-Mode -->
                <div x-show="bearbeiten" style="margin-top:12px;">
                    <div class="thx-form-field">
                        <label>Linkart</label>
                        <input type="text" x-model="form.linkart" placeholder="z.B. ratgeber, blog, magazin">
                    </div>
                    <div class="thx-form-field">
                        <label>Branche</label>
                        <input type="text" x-model="form.branche" placeholder="z.B. Gesundheit, Finanzen">
                    </div>
                    <div class="thx-form-field">
                        <label>Thema</label>
                        <input type="text" x-model="form.thema" placeholder="z.B. Schwangerschaft & Geburt">
                    </div>
                    <div class="thx-form-field">
                        <label>Tonalität</label>
                        <input type="text" x-model="form.tonalitaet" placeholder="z.B. informativ, persönlich, sachlich">
                    </div>
                    <div class="thx-form-field">
                        <label>Reduktions-Strategie</label>
                        <input type="text" x-model="form.reduktionsstrategie" placeholder="z.B. nur_lifestyle">
                    </div>
                    <div class="thx-form-field">
                        <label>Default-Empfehlung</label>
                        <select x-model="form.empfehlung_default">
                            <option value="">—</option>
                            <option value="ja">ja</option>
                            <option value="nein">nein</option>
                            <option value="bedingt">bedingt</option>
                        </select>
                    </div>
                    <div class="thx-form-field">
                        <label>Risikofaktoren</label>
                        <textarea x-model="form.risikofaktoren" rows="2" placeholder="z.B. Geld-für-Link-Texte, übertriebene Werbung …"></textarea>
                    </div>
                    <div class="thx-form-field">
                        <label>Notiz</label>
                        <textarea x-model="form.notiz" rows="2" placeholder="freier Hinweis …"></textarea>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:10px;">
                        <button class="lam-btn lam-btn-primary lam-btn-sm" @click="speichern()" :disabled="laeuft">
                            <span x-show="!laeuft">Speichern</span><span x-show="laeuft">…</span>
                        </button>
                        <button class="lam-btn lam-btn-secondary lam-btn-sm" @click="bearbeiten = false">Abbrechen</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- AUDIT-LOG (bewahrt) -->
            <?php if (!empty($d['audit_log'])): ?>
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" @click="auditOffen = !auditOffen">
                    <h3 style="margin:0;">Audit-Log (<?= count($d['audit_log']) ?>)</h3>
                    <span style="font-size:var(--d-fs-lg);color:var(--slate-400);" x-text="auditOffen ? '▾' : '▸'"></span>
                </div>
                <div x-show="auditOffen" x-cloak style="margin-top:12px;">
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <thead><tr><th>Zeitpunkt</th><th>Aktion</th><th>User</th><th>Details</th></tr></thead>
                        <tbody>
                            <?php foreach ($d['audit_log'] as $al): ?>
                                <tr>
                                    <td><?= htmlspecialchars($al['zeitpunkt']) ?></td>
                                    <td><?= htmlspecialchars($al['aktion']) ?></td>
                                    <td><?= htmlspecialchars($al['user_name'] ?: 'System') ?></td>
                                    <td>
                                        <?php if ($al['ist_bulk']): ?>
                                            <span class="muted">Bulk (<?= (int)$al['anzahl_betroffen'] ?> betroffen)</span>
                                        <?php elseif (!empty($al['payload'])): ?>
                                            <span class="muted" style="font-size:var(--d-fs-xs);"><?= htmlspecialchars(mb_substr($al['payload'], 0, 60)) ?><?= mb_strlen($al['payload']) > 60 ? '…' : '' ?></span>
                                        <?php else: ?>
                                            <span class="empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </section>

        <!-- RECHTS Sidebar -->
        <aside style="display:flex;flex-direction:column;gap:20px;">

            <!-- KURZBESCHREIBUNG -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h3 style="margin:0;">Kurzbeschreibung</h3>
                    <button class="lam-btn lam-btn-primary lam-btn-small" @click="kiKurzbeschreibungGenerieren()" :disabled="kbLaeuft">
                        <span x-show="!kbLaeuft">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">auto_awesome</span>
                            KI generieren
                        </span>
                        <span x-show="kbLaeuft">… (crawlt + KI)</span>
                    </button>
                </div>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 10px 0;color:var(--slate-500);">Aus Startseite + „Über uns" der Domain. Editierbar.</p>
                <textarea x-model="kurzbeschreibung"
                          @blur="speichereKurzbeschreibung()"
                          placeholder="Noch keine Kurzbeschreibung. KI-Knopf rechts oben klicken oder selbst eintippen."
                          style="width:100%;min-height:220px;padding:12px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:0.9rem;line-height:1.55;font-family:inherit;resize:vertical;background:#fff;transition:border-color 0.15s, box-shadow 0.15s;"
                          onfocus="this.style.borderColor='var(--thoxan-500)';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.12)';"
                          onblur="this.style.borderColor='var(--slate-300)';this.style.boxShadow='none';"></textarea>
                <?php if (!empty($d['ki_kurzbeschreibung_generiert_am'])): ?>
                    <p class="muted" style="margin-top:8px;font-size:var(--d-fs-xs);color:var(--slate-500);display:flex;align-items:center;gap:6px;">
                        <span class="material-symbols-rounded" style="font-size:14px;">history</span>
                        Zuletzt KI-generiert: <?= htmlspecialchars($d['ki_kurzbeschreibung_generiert_am']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- EXTERNE LINKS (inline editierbar) -->
            <div class="lam-card">
                <h3 style="margin:0 0 4px 0;">Externe Links</h3>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 14px 0;color:var(--slate-500);">
                    Beispielartikel, Mediadaten-PDF, Preisliste, AGB. Der erste „Beispielartikel"-Eintrag landet im Kunden-Excel-Export.
                </p>

                <!-- Impressum-Zeile (eigene Logik, eigenes Feld) -->
                <?php if (!empty($d['impressum_url'])): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-sm);">
                        <span style="width:140px;color:var(--slate-500);font-size:var(--d-fs-xs);">Impressum</span>
                        <a href="<?= htmlspecialchars($d['impressum_url']) ?>" target="_blank" rel="noopener" style="flex:1;color:var(--thoxan-700);text-decoration:none;"><?= htmlspecialchars($d['impressum_url']) ?> ↗</a>
                    </div>
                <?php endif; ?>

                <!-- Existierende Links inline -->
                <div style="display:flex;flex-direction:column;">
                    <template x-for="(el, i) in externeLinks" :key="el.id">
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100);font-size:var(--d-fs-sm);">
                            <select x-model="el.typ" @change="speichereLinkInline(el)"
                                    style="width:140px;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                                <option value="beispiellink">Beispielartikel</option>
                                <option value="mediadaten">Mediadaten</option>
                                <option value="preisliste">Preisliste</option>
                                <option value="agb">AGB</option>
                                <option value="kontakt">Kontaktseite</option>
                                <option value="impressum">Impressum</option>
                                <option value="sonstiges">Sonstiges</option>
                            </select>
                            <input type="text" x-model="el.label" @change="speichereLinkInline(el)"
                                   placeholder="Label (optional)"
                                   style="width:180px;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                            <input type="url" x-model="el.url" @change="speichereLinkInline(el)"
                                   placeholder="https://…"
                                   style="flex:1;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                            <a x-show="el.url" :href="el.url" target="_blank" rel="noopener" style="color:var(--thoxan-700);text-decoration:none;font-size:0.85rem;" title="Öffnen">↗</a>
                            <button @click="loescheExternenLink(el.id)" title="Entfernen"
                                    style="background:none;border:0;color:var(--slate-400);cursor:pointer;font-size:0.85rem;padding:2px 6px;">×</button>
                        </div>
                    </template>
                </div>

                <!-- Neuer Link inline -->
                <div style="display:flex;align-items:center;gap:10px;padding:10px 0 0;font-size:var(--d-fs-sm);">
                    <select x-model="neuerLink.typ"
                            style="width:140px;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                        <option value="beispiellink">Beispielartikel</option>
                        <option value="mediadaten">Mediadaten</option>
                        <option value="preisliste">Preisliste</option>
                        <option value="agb">AGB</option>
                        <option value="kontakt">Kontaktseite</option>
                        <option value="impressum">Impressum</option>
                        <option value="sonstiges">Sonstiges</option>
                    </select>
                    <input type="text" x-model="neuerLink.label" placeholder="Label (optional)"
                           style="width:180px;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                    <input type="url" x-model="neuerLink.url" @keydown.enter="speichereNeuenLink()"
                           placeholder="https://… und Enter zum Hinzufügen"
                           style="flex:1;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                    <button @click="speichereNeuenLink()" :disabled="!neuerLink.url" class="lam-btn lam-btn-primary lam-btn-small">+ hinzufügen</button>
                </div>

                <p x-show="externeLinks.length === 0 && !<?= !empty($d['impressum_url']) ? 'true' : 'false' ?>" class="muted" style="margin:14px 0 0 0;font-size:var(--d-fs-xs);color:var(--slate-400);">
                    Noch keine externen Links hinterlegt.
                </p>
            </div>

            <!-- Kerndaten (Position 2: direkt unter Kurzbeschreibung) -->
            <div class="lam-card">
                <h3>Kerndaten</h3>
                <table class="lam-table" style="font-size:var(--d-fs-sm);">
                    <tbody>
                        <tr>
                            <td class="muted" style="width:45%;">Linkart</td>
                            <td>
                                <select x-model="linkart" @change="speichereFeldInline('linkart', linkart)"
                                        style="width:100%;padding:4px 8px;border:1px solid var(--slate-300);border-radius:4px;background:#fff;font-size:var(--d-fs-sm);">
                                    <option value="">— nicht gesetzt —</option>
                                    <?php foreach (\Services\LamService::DOMAIN_LINKART_LABELS as $slug => $label): ?>
                                        <option value="<?= $slug ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php if (!empty($d['sistrix_sichtbar_seit'])): ?>
                            <tr><td class="muted">Sistrix sichtbar seit</td><td><?= htmlspecialchars($d['sistrix_sichtbar_seit']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($d['letzter_http_status']): ?>
                            <tr><td class="muted">HTTP-Status</td>
                                <td>
                                    <?= (int)$d['letzter_http_status'] ?>
                                    <?php if (in_array($d['letzter_http_erreichbar'], [0, '0'], true)): ?>
                                        <span style="color:var(--rose-600);"> (nicht erreichbar)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($d['disqualifikations_grund'])): ?>
                            <tr><td class="muted">Disqual.-Grund</td><td style="color:var(--rose-700);"><?= htmlspecialchars($d['disqualifikations_grund']) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- NOTIZEN (Position 3: nach Kerndaten) -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h3 style="margin:0;">Notizen</h3>
                    <span style="font-size:var(--d-fs-xs);color:var(--slate-400);">wird beim Verlassen gespeichert</span>
                </div>
                <textarea x-model="notizen"
                          @blur="speichereNotizen()"
                          placeholder="Stammdaten, Beobachtungen, Kontext, interne Notizen — frei strukturiert. Markdown wird unterstützt."
                          style="width:100%;min-height:240px;padding:14px 16px;border:1px solid var(--slate-300);border-radius:8px;font-size:0.9rem;line-height:1.55;font-family:inherit;resize:vertical;background:#fff;transition:border-color 0.15s, box-shadow 0.15s;"
                          onfocus="this.style.borderColor='var(--thoxan-500)';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.12)';"
                          onblur="this.style.borderColor='var(--slate-300)';this.style.boxShadow='none';"></textarea>
            </div>

            <!-- WARTEZEIT (temporäre Aussetzung) -->
            <div class="lam-card">
                <h3>Wartezeit</h3>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 8px 0;">
                    Domain temporär aussetzen (Mindestabstand nach Buchung etc.). In Filtern erscheint sie erst wieder ab diesem Datum.
                </p>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="date" x-model="wartezeitBis" @change="speichereWartezeit()"
                           class="lam-filter-input" style="width:auto;">
                    <button class="lam-btn lam-btn-sm" @click="wartezeitBis = ''; speichereWartezeit()" x-show="wartezeitBis">aufheben</button>
                </div>
            </div>

        </aside>
    </div>

    <!-- Impressum-Crawl-Ergebnis-Modal -->
    <div x-show="impressumErgebnis.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;"
         @click.self="impressumErgebnis.offen = false; window.location.reload();">
        <div style="background:#fff;border-radius:10px;width:540px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:1.05rem;">
                    <template x-if="!impressumErgebnis.daten?._fehler">
                        <span style="color:var(--emerald-700);">✓ Impressum analysiert</span>
                    </template>
                    <template x-if="impressumErgebnis.daten?._fehler">
                        <span style="color:var(--rose-700);">Impressum-Crawl fehlgeschlagen</span>
                    </template>
                </h3>
                <button @click="impressumErgebnis.offen = false; window.location.reload();" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:22px 24px;">
                <template x-if="impressumErgebnis.daten?._fehler">
                    <div style="color:var(--rose-700);font-size:0.9rem;" x-text="impressumErgebnis.daten._fehler"></div>
                </template>
                <template x-if="!impressumErgebnis.daten?._fehler">
                    <table style="width:100%;font-size:0.9rem;border-collapse:collapse;">
                        <tbody>
                            <tr><td style="padding:6px 0;color:var(--slate-500);width:42%;">Ansprechpartner</td><td style="font-weight:600;" x-text="ergebnisPerson() || '— keine Person erkannt —'"></td></tr>
                            <tr x-show="impressumErgebnis.daten?.ansprechpartner_rolle"><td style="padding:6px 0;color:var(--slate-500);">Rolle</td><td x-text="impressumErgebnis.daten?.ansprechpartner_rolle"></td></tr>
                            <tr><td style="padding:6px 0;color:var(--slate-500);">Firma</td><td x-text="impressumErgebnis.daten?.firma || '—'"></td></tr>
                            <tr x-show="impressumErgebnis.daten?.email"><td style="padding:6px 0;color:var(--slate-500);">E-Mail</td><td><a :href="'mailto:' + impressumErgebnis.daten?.email" style="color:var(--thoxan-700);" x-text="impressumErgebnis.daten?.email"></a></td></tr>
                            <tr x-show="impressumErgebnis.daten?.telefon"><td style="padding:6px 0;color:var(--slate-500);">Telefon</td><td x-text="impressumErgebnis.daten?.telefon"></td></tr>
                            <tr x-show="impressumErgebnis.daten?.strasse || impressumErgebnis.daten?.ort">
                                <td style="padding:6px 0;color:var(--slate-500);">Anschrift</td>
                                <td>
                                    <div x-show="impressumErgebnis.daten?.strasse" x-text="impressumErgebnis.daten?.strasse"></div>
                                    <div x-show="impressumErgebnis.daten?.plz || impressumErgebnis.daten?.ort">
                                        <span x-text="impressumErgebnis.daten?.plz"></span>
                                        <span x-text="' ' + (impressumErgebnis.daten?.ort || '')"></span>
                                    </div>
                                </td>
                            </tr>
                            <tr><td style="padding:6px 0;color:var(--slate-500);">Konfidenz</td><td><span x-text="impressumErgebnis.daten?.konfidenz || '—'" style="text-transform:capitalize;"></span></td></tr>
                        </tbody>
                    </table>
                </template>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--slate-100);font-size:0.85rem;color:var(--slate-500);">
                    Anbieter und Kontakt wurden in der Datenbank aktualisiert. Die Seite wird beim Schließen neu geladen.
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                    <button @click="impressumErgebnis.offen = false; window.location.reload();" class="lam-btn lam-btn-primary">Schließen + Seite neu laden</button>
                </div>
            </div>
        </div>
    </div>

    <!-- KI-Tags-Vorschlag-Modal -->
    <div x-show="kiTagsModal.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;"
         @click.self="kiTagsModal.offen = false">
        <div style="background:#fff;border-radius:10px;width:540px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">🏷 KI-Tag-Vorschlag</h3>
                <button @click="kiTagsModal.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:22px 24px;">
                <template x-if="kiTagsModal.laeuft">
                    <div style="text-align:center;padding:24px;color:var(--slate-500);">… KI klassifiziert</div>
                </template>
                <template x-if="!kiTagsModal.laeuft && kiTagsModal.slugs.length > 0">
                    <div>
                        <p style="margin:0 0 14px 0;font-size:var(--d-fs-sm);color:var(--slate-600);">Vorgeschlagene Tags:</p>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px;">
                            <template x-for="slug in kiTagsModal.slugs" :key="slug">
                                <span style="background:var(--thoxan-100);color:var(--thoxan-800);padding:4px 12px;border-radius:999px;font-size:0.85rem;font-weight:500;" x-text="slug"></span>
                            </template>
                        </div>
                        <div x-show="kiTagsModal.begruendung" style="background:var(--slate-50);border-radius:6px;padding:12px 14px;font-size:var(--d-fs-sm);color:var(--slate-700);line-height:1.5;">
                            <strong style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);">Begründung</strong><br>
                            <span x-text="kiTagsModal.begruendung"></span>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
                            <button @click="kiTagsModal.offen = false" class="lam-btn lam-btn-secondary">Verwerfen</button>
                            <button @click="kiTagsUebernehmen()" class="lam-btn lam-btn-primary">+ Tags zur Domain hinzufügen</button>
                        </div>
                    </div>
                </template>
                <template x-if="!kiTagsModal.laeuft && kiTagsModal.slugs.length === 0">
                    <div>
                        <p style="font-size:var(--d-fs-sm);color:var(--slate-600);">KI hat keine passenden Tags vorgeschlagen.</p>
                        <div x-show="kiTagsModal.begruendung" style="background:var(--slate-50);border-radius:6px;padding:12px 14px;font-size:var(--d-fs-sm);color:var(--slate-700);margin-top:10px;line-height:1.5;" x-text="kiTagsModal.begruendung"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- KI-Recherche-Ergebnis-Modal -->
    <div x-show="kiRechercheModal.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:80px;"
         @click.self="kiRechercheModal.offen = false; if (kiRechercheModal.daten) window.location.reload();">
        <div style="background:#fff;border-radius:10px;width:600px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">🔍 KI-Recherche-Ergebnis</h3>
                <button @click="kiRechercheModal.offen = false; if (kiRechercheModal.daten) window.location.reload();" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:22px 24px;">
                <template x-if="kiRechercheModal.laeuft">
                    <div style="text-align:center;padding:30px;color:var(--slate-500);">
                        … KI crawlt Startseite und Impressum, klassifiziert die Domain
                    </div>
                </template>
                <template x-if="!kiRechercheModal.laeuft && kiRechercheModal.fehler">
                    <div style="color:var(--rose-700);" x-text="'Fehler: ' + kiRechercheModal.fehler"></div>
                </template>
                <template x-if="!kiRechercheModal.laeuft && kiRechercheModal.daten">
                    <div>
                        <table style="width:100%;font-size:0.9rem;border-collapse:collapse;">
                            <tbody>
                                <tr><td style="padding:8px 0;color:var(--slate-500);width:38%;">Betreiber</td><td style="font-weight:600;" x-text="kiRechercheModal.daten.betreiber || '—'"></td></tr>
                                <tr><td style="padding:8px 0;color:var(--slate-500);">Rechtsform</td><td x-text="kiRechercheModal.daten.rechtsform || '—'"></td></tr>
                                <tr><td style="padding:8px 0;color:var(--slate-500);">Themenschwerpunkt</td><td x-text="kiRechercheModal.daten.themenschwerpunkt || '—'"></td></tr>
                                <tr><td style="padding:8px 0;color:var(--slate-500);">Zielgruppe</td><td x-text="kiRechercheModal.daten.zielgruppe || '—'"></td></tr>
                                <tr><td style="padding:8px 0;color:var(--slate-500);">Redaktionell</td><td x-text="kiRechercheModal.daten.redaktionell ? 'ja' : 'nein'"></td></tr>
                                <tr><td style="padding:8px 0;color:var(--slate-500);">Kommerziell</td><td x-text="kiRechercheModal.daten.kommerziell ? 'ja' : 'nein'"></td></tr>
                            </tbody>
                        </table>
                        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--slate-100);">
                            <div style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Kurzbeschreibung</div>
                            <div style="font-size:0.9rem;color:var(--slate-700);line-height:1.5;background:var(--slate-50);padding:12px 14px;border-radius:6px;" x-text="kiRechercheModal.daten.kurzbeschreibung || '—'"></div>
                        </div>
                        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--slate-100);font-size:var(--d-fs-xs);color:var(--slate-500);">
                            Die Kurzbeschreibung wurde in die Domain gespeichert. Beim Schließen wird die Seite neu geladen.
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
                            <button @click="kiRechercheModal.offen = false; window.location.reload();" class="lam-btn lam-btn-primary">Schließen + neu laden</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Linkpool-Aufnahme-Modal -->
    <div x-show="linkpoolModal.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;"
         @click.self="linkpoolModal.offen = false">
        <div style="background:#fff;border-radius:10px;width:480px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">In Linkpool aufnehmen</h3>
                <button @click="linkpoolModal.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:22px 24px;">
                <p style="margin:0 0 16px 0;font-size:var(--d-fs-sm);color:var(--slate-600);">
                    Domain dem Linkpool eines Kunden hinzufügen. Status wird automatisch auf <strong>„In Arbeit"</strong> gesetzt, falls er aktuell „Neu" oder „Veraltet" ist.
                </p>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Kunde</label>
                    <select x-model.number="linkpoolModal.customerId"
                            style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                        <option value="">— Kunde wählen —</option>
                        <template x-for="k in linkpoolModal.kunden" :key="k.id">
                            <option :value="k.id" x-text="(k.abbreviation || k.kuerzel || '?') + ' · ' + k.name"></option>
                        </template>
                    </select>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button @click="linkpoolModal.offen = false" class="lam-btn lam-btn-secondary">Abbrechen</button>
                    <button @click="speichereLinkpool()" :disabled="!linkpoolModal.customerId || linkpoolModal.laeuft"
                            class="lam-btn lam-btn-primary">
                        <span x-show="!linkpoolModal.laeuft">In Pool aufnehmen</span>
                        <span x-show="linkpoolModal.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Neue-Maßnahme-Modal -->
    <div x-show="massnahmeModal.offen" x-cloak
         style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;"
         @click.self="massnahmeModal.offen = false">
        <div style="background:#fff;border-radius:10px;width:560px;max-width:calc(100% - 40px);box-shadow:0 14px 40px rgba(15,23,42,0.2);overflow:hidden;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;">Neue Maßnahme aus dieser Domain</h3>
                <button @click="massnahmeModal.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:22px 24px;">
                <p style="margin:0 0 16px 0;font-size:var(--d-fs-sm);color:var(--slate-600);">
                    Konkrete Maßnahme für einen Kunden anlegen. Nach Speichern landest Du direkt auf der Maßnahme-Detail-Seite.
                </p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Kunde <span style="color:var(--rose-600);">*</span></label>
                        <select x-model.number="massnahmeModal.customerId"
                                style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                            <option value="">— wählen —</option>
                            <template x-for="k in massnahmeModal.kunden" :key="k.id">
                                <option :value="k.id" x-text="(k.abbreviation || k.kuerzel || '?') + ' · ' + k.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Buchungstyp</label>
                        <select x-model="massnahmeModal.buchungstyp"
                                style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                            <option value="">— optional —</option>
                            <option value="gastartikel">Gastartikel</option>
                            <option value="advertorial">Advertorial</option>
                            <option value="pressemitteilung">Pressemitteilung</option>
                            <option value="interview">Interview</option>
                            <option value="verzeichnis">Verzeichnis</option>
                            <option value="startseite">Startseite</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Preis (€, an Kunden weiterverrechnet)</label>
                        <input type="number" step="0.01" min="0" x-model="massnahmeModal.preis"
                               placeholder="z. B. 250.00"
                               style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                    </div>
                    <div>
                        <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Geplant am</label>
                        <input type="date" x-model="massnahmeModal.geplantAm"
                               style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:6px;">Linktext (optional)</label>
                    <input type="text" x-model="massnahmeModal.linktext"
                           placeholder="z. B. „Mehr erfahren""
                           style="width:100%;padding:8px 12px;border:1px solid var(--slate-300);border-radius:5px;background:#fff;font-size:0.9rem;">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button @click="massnahmeModal.offen = false" class="lam-btn lam-btn-secondary">Abbrechen</button>
                    <button @click="speichereMassnahme()" :disabled="!massnahmeModal.customerId || massnahmeModal.laeuft"
                            class="lam-btn lam-btn-primary">
                        <span x-show="!massnahmeModal.laeuft">Anlegen + zur Detail-Seite</span>
                        <span x-show="massnahmeModal.laeuft">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Konditions-Drawer -->
    <div class="thx-drawer-backdrop" x-show="kondDrawer.offen" @click.self="kondDrawer.offen = false" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title" x-text="kondDrawer.id ? 'Kondition bearbeiten' : 'Neue Kondition'"></h2>
                <button class="thx-modal-close" @click="kondDrawer.offen = false">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>Buchungstyp</label>
                    <input type="text" x-model="kondDrawer.buchungstyp" placeholder="z.B. gastbeitrag, banner, link">
                </div>
                <div class="thx-form-field">
                    <label>Preis (€)</label>
                    <input type="number" step="0.01" x-model="kondDrawer.preis">
                </div>
                <div class="thx-form-field">
                    <label>Via Anbieter (Vermittler, optional)</label>
                    <select x-model="kondDrawer.via_anbieter_id">
                        <option value="">— direkt —</option>
                        <?php foreach ($alleAnbieter as $a): ?>
                            <option value="<?= htmlspecialchars($a['id']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Notiz</label>
                    <textarea x-model="kondDrawer.notiz" rows="3"></textarea>
                </div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="kondDrawer.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereKondition()" :disabled="kondDrawer.laeuft">Speichern</button>
            </div>
        </div>
    </div>

</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
function lamDomainDetail() {
    const DOMAIN_ID = <?= json_encode($d['id']) ?>;
    return {
        anbieterId: <?= json_encode($d['anbieter_id'] ?? '') ?>,
        impressumUrl: <?= json_encode($d['impressum_url'] ?? '') ?>,
        impressumLaeuft: false,
        kbLaeuft: false,
        notizen: <?= json_encode($d['notizen'] ?? '') ?>,
        kurzbeschreibung: <?= json_encode($d['ki_kurzbeschreibung'] ?? '') ?>,
        kennzahlOffen: true,
        auditOffen: false,
        // Anbieter-Picker (Type-Ahead)
        anbPicker: { offen: false, suche: '', treffer: [], rolle: 'betreiber' },

        // Inline-Editable Header- und Kerndaten-Felder
        url: <?= json_encode($d['url']) ?>,
        urlEdit: false,
        verifikation_status: <?= json_encode($d['verifikation_status']) ?>,
        linkart: <?= json_encode($d['linkart'] ?? '') ?>,
        buchbarVia: <?= json_encode($d['buchbar_via'] ?? '') ?>,

        kondDrawer: { offen: false, id: null, buchungstyp: '', preis: '', via_anbieter_id: '', notiz: '', laeuft: false },
        // Externe Links inline editierbar
        externeLinks: <?= json_encode($d['externe_links'] ?? []) ?>,
        neuerLink: { typ: 'beispiellink', label: '', url: '' },

        statusStyleFor(s) {
            const map = {
                'neu': 'background:var(--amber-100);color:var(--amber-800);',
                'in_arbeit': 'background:var(--thoxan-100);color:var(--thoxan-700);',
                'geprueft': 'background:var(--emerald-100);color:var(--emerald-800);',
                'veraltet': 'background:#fed7aa;color:#9a3412;',
                'geloescht': 'background:var(--rose-100);color:var(--rose-800);',
            };
            return map[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },
        statusLabel(s) {
            const map = { 'neu': 'Neu', 'in_arbeit': 'In Arbeit', 'geprueft': 'Geprüft', 'veraltet': 'Veraltet', 'geloescht': 'Gelöscht' };
            return map[s] || s;
        },

        // Status-Schnellwechsel — optimistisch, kein Reload
        async setzeStatus(s) {
            const alt = this.verifikation_status;
            this.verifikation_status = s;
            try {
                const r = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: DOMAIN_ID, feld: 'verifikation_status', wert: s })
                });
                if (!(await r.json()).success) throw new Error('Fehler');
            } catch (e) {
                this.verifikation_status = alt;
                alert('Status-Wechsel fehlgeschlagen.');
            }
        },
        async speichereFeldInline(feld, wert) {
            try {
                const r = await fetch('/api/v1/lam/domain-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: DOMAIN_ID, feld, wert })
                });
                if (!(await r.json()).success) throw new Error('Fehler');
            } catch (e) { alert(feld + ' speichern fehlgeschlagen: ' + e.message); }
        },
        async speichereUrl() {
            const neu = (this.url || '').trim();
            if (!neu) { alert('URL darf nicht leer sein.'); return; }
            try {
                const r = await fetch('/api/v1/lam/domain-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: DOMAIN_ID, url: neu })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.urlEdit = false;
            } catch (e) { alert('URL speichern fehlgeschlagen: ' + e.message); }
        },
        async disqualifizieren() {
            if (!confirm('Domain disqualifizieren?')) return;
            await fetch('/api/v1/lam/domain-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [DOMAIN_ID], aktion: 'disqualifizieren' })
            });
            window.location.reload();
        },
        async rehabilitieren() {
            await fetch('/api/v1/lam/domain-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [DOMAIN_ID], aktion: 'rehabilitieren' })
            });
            window.location.reload();
        },
        async loeschen() {
            if (!confirm('Domain wirklich löschen? (Soft-Delete)')) return;
            await fetch('/api/v1/lam/domain-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [DOMAIN_ID], aktion: 'loeschen' })
            });
            window.location.href = '/lam/linkquellen';
        },

        impressumLaeuft: false,
        async anbieterAusImpressum() {
            if (!confirm('KI crawlt das Impressum und legt einen Anbieter an. Verbraucht Anthropic-Tokens. Fortfahren?')) return;
            this.impressumLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/anbieter-aus-impressum', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || j.message || 'Fehler.'); return; }
                const d = j.data;
                let msg = (d.anbieter_neu ? '✓ Neuer Anbieter angelegt:\n\n' : '✓ An bestehenden Anbieter gehängt:\n\n');
                msg += `Firma: ${d.daten.firma}\n`;
                msg += `Rechtsform: ${d.daten.rechtsform || '—'}\n`;
                msg += `Adresse: ${[d.daten.strasse, d.daten.plz + ' ' + d.daten.ort].filter(Boolean).join(', ')}\n`;
                msg += `Mail: ${d.daten.email || '—'}\n`;
                msg += `Telefon: ${d.daten.telefon || '—'}\n`;
                msg += `Geschäftsführer: ${d.daten.geschaeftsfuehrer || '—'}\n`;
                msg += `\nKI-Konfidenz: ${d.daten.konfidenz || 'mittel'}`;
                alert(msg);
                location.reload();
            } catch (e) { alert('Verbindungsfehler: ' + e.message); }
            finally { this.impressumLaeuft = false; }
        },

        // Anbieter setzen
        async speichereAnbieter() {
            await fetch('/api/v1/lam/domain-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: DOMAIN_ID, feld: 'anbieter_id', wert: this.anbieterId || null })
            });
            window.location.reload();
        },
        async speichereImpressum() {
            if (!this.impressumUrl.trim()) return;
            await fetch('/api/v1/lam/domain-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: DOMAIN_ID, feld: 'impressum_url', wert: this.impressumUrl })
            });
            window.location.reload();
        },
        // ===== Anbieter-Picker (Type-Ahead) =====
        async anbPickerFiltern() {
            if (this.anbPicker.suche.length < 2) { this.anbPicker.treffer = []; return; }
            const p = new URLSearchParams({ suche: this.anbPicker.suche });
            try {
                const r = await fetch('/api/v1/lam/anbieter-kurz?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                this.anbPicker.treffer = (j.success ? (j.data || []) : []).slice(0, 30);
            } catch (e) { this.anbPicker.treffer = []; }
        },
        async anbieterZuordnen(a) {
            try {
                const r = await fetch('/api/v1/lam/domain-anbieter-zuordnen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID, anbieter_id: a.id, rolle: this.anbPicker.rolle }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) { alert('Zuordnen fehlgeschlagen: ' + e.message); }
        },
        async anbieterNeuAnlegen() {
            const name = prompt('Name des neuen Anbieters (Vorname Nachname der Hauptansprechperson, oder Firma falls keine Person):', this.anbPicker.suche);
            if (!name || !name.trim()) return;
            try {
                const r = await fetch('/api/v1/lam/anbieter-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name.trim(), rolle: this.anbPicker.rolle, beziehungsstatus: 'neu' }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Direkt der Domain zuordnen
                await this.anbieterZuordnen({ id: j.data.id });
            } catch (e) { alert('Anlegen fehlgeschlagen: ' + e.message); }
        },
        async anbieterFlagsSetzen(junctionId, flag, an) {
            try {
                const r = await fetch('/api/v1/lam/domain-anbieter-flags', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ junction_id: junctionId, flag, wert: an ? 1 : 0 }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Kein Reload — Checkbox ist schon visuell aktualisiert
            } catch (e) { alert('Rolle ändern fehlgeschlagen: ' + e.message); }
        },
        async anbieterVerschieben(junctionId, richtung) {
            try {
                const r = await fetch('/api/v1/lam/domain-anbieter-verschieben', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ junction_id: junctionId, richtung }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) { alert('Verschieben fehlgeschlagen: ' + e.message); }
        },
        async anbieterEntfernen(junctionId, anbieterName) {
            if (!confirm(`Anbieter „${anbieterName}" von dieser Domain entfernen?\n\nDer Anbieter selbst bleibt in der Datenbank — nur die Verknüpfung zu dieser Domain wird gelöscht.`)) return;
            try {
                const r = await fetch('/api/v1/lam/domain-anbieter-entfernen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ junction_id: junctionId }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) { alert('Entfernen fehlgeschlagen: ' + e.message); }
        },

        // Ergebnis-Modal für Impressum-Crawl
        impressumErgebnis: { offen: false, daten: null },

        async impressumCrawl() {
            if (this.impressumLaeuft) return;
            this.impressumLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/anbieter-aus-impressum', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message || 'Fehler');
                this.impressumErgebnis = { offen: true, daten: j.data.daten || {} };
            } catch (e) {
                this.impressumErgebnis = { offen: true, daten: { _fehler: e.message } };
            }
            this.impressumLaeuft = false;
        },
        ergebnisPerson() {
            const d = this.impressumErgebnis.daten || {};
            return [d.ansprechpartner_vorname, d.ansprechpartner_nachname].filter(Boolean).join(' ') || d.geschaeftsfuehrer || '';
        },

        // Notizen + Kurzbeschreibung Inline-Save
        async speichereWartezeit() {
            await fetch('/api/v1/lam/domain-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: DOMAIN_ID, feld: 'wartezeit_bis', wert: this.wartezeitBis || null })
            });
        },
        async speichereNotizen() {
            await fetch('/api/v1/lam/domain-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: DOMAIN_ID, feld: 'notizen', wert: this.notizen })
            });
        },
        async speichereKurzbeschreibung() {
            await fetch('/api/v1/lam/domain-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: DOMAIN_ID, feld: 'ki_kurzbeschreibung', wert: this.kurzbeschreibung })
            });
        },

        // Tag-Toggle
        async toggleTag(tagId) {
            await fetch('/api/v1/lam/domain-tag-toggle', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ domain_id: DOMAIN_ID, tag_id: tagId })
            });
            window.location.reload();
        },

        // Kunden-Toggle
        async toggleKunde(kundeId) {
            try {
                const r = await fetch('/api/v1/lam/domain-kunde-toggle', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID, kunde_id: kundeId })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message || 'Fehler beim Speichern');
                // Status kann sich serverseitig geändert haben (neu/veraltet → in_arbeit) — Seite neu laden
                window.location.reload();
            } catch (e) { alert('Kunden-Zuordnung fehlgeschlagen: ' + e.message); }
        },

        // Konditionen
        oeffneKonditionDrawer(k) {
            this.kondDrawer = {
                offen: true,
                id: k?.id || null,
                buchungstyp: k?.buchungstyp || '',
                preis: k?.preis || '',
                via_anbieter_id: k?.via_anbieter_id || '',
                notiz: k?.notiz || '',
                laeuft: false
            };
        },
        async speichereKondition() {
            if (this.kondDrawer.laeuft) return;
            this.kondDrawer.laeuft = true;
            try {
                await fetch('/api/v1/lam/kondition-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.kondDrawer.id,
                        domain_id: DOMAIN_ID,
                        buchungstyp: this.kondDrawer.buchungstyp,
                        preis: this.kondDrawer.preis,
                        via_anbieter_id: this.kondDrawer.via_anbieter_id,
                        notiz: this.kondDrawer.notiz
                    })
                });
                window.location.reload();
            } finally { this.kondDrawer.laeuft = false; }
        },
        async loescheKondition(id) {
            if (!confirm('Kondition wirklich löschen?')) return;
            await fetch('/api/v1/lam/kondition-loeschen', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            window.location.reload();
        },

        // Externe Links — inline editierbar (kein Modal)
        async speichereLinkInline(el) {
            if (!el.url || !el.url.trim()) return;
            try {
                const r = await fetch('/api/v1/lam/domain-link-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: el.id, domain_id: DOMAIN_ID,
                        typ: el.typ, label: el.label, url: el.url,
                    }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } catch (e) { alert('Link speichern fehlgeschlagen: ' + e.message); }
        },
        async speichereNeuenLink() {
            const url = (this.neuerLink.url || '').trim();
            if (!url) return;
            try {
                const r = await fetch('/api/v1/lam/domain-link-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        domain_id: DOMAIN_ID,
                        typ: this.neuerLink.typ,
                        label: this.neuerLink.label,
                        url: url,
                    }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                // Frischen Eintrag zur Liste ergänzen + Form zurücksetzen
                this.externeLinks.push({ id: j.data.id, typ: this.neuerLink.typ, label: this.neuerLink.label, url: url });
                this.neuerLink = { typ: 'beispiellink', label: '', url: '' };
            } catch (e) { alert('Link anlegen fehlgeschlagen: ' + e.message); }
        },
        async loescheExternenLink(id) {
            if (!confirm('Link wirklich löschen?')) return;
            try {
                const r = await fetch('/api/v1/lam/domain-link-loeschen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.externeLinks = this.externeLinks.filter(l => l.id !== id);
            } catch (e) { alert('Löschen fehlgeschlagen: ' + e.message); }
        },

        // Sistrix-API-Aufruf (echt)
        async sistrixStub(teil) {
            const teile = teil === 'alles' ? ['si','alter','dp'] : [teil];
            try {
                const res = await fetch('/api/v1/lam/sistrix-abruf', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID, teile })
                });
                const json = await res.json();
                if (!json.success) {
                    if (json.message && json.message.indexOf('API-Key nicht') !== -1) {
                        if (confirm('Sistrix-API-Key ist noch nicht gesetzt. Jetzt zu den Einstellungen?')) {
                            window.location.href = '/admin/settings?tab=sistrix';
                        }
                    } else {
                        alert('Sistrix-Fehler: ' + (json.message || 'unbekannt'));
                    }
                    return;
                }
                const w = json.data.werte;
                let msg = 'Sistrix-Aufruf erfolgreich.\n';
                if (w.si !== null && w.si !== undefined) msg += '\nSI: ' + w.si;
                if (w.dp !== null && w.dp !== undefined) msg += '\nDP: ' + w.dp;
                if (w.sichtbar_seit) msg += '\nSichtbar seit: ' + w.sichtbar_seit;
                msg += '\n\nCredits verbraucht: ' + json.data.credits_verbraucht;
                if (json.data.cached) msg += ' (aus Cache, kein API-Call)';
                if (json.data.fehler && json.data.fehler.length) msg += '\n\nWarnungen:\n' + json.data.fehler.join('\n');
                alert(msg);
                window.location.reload();
            } catch (e) {
                alert('Netzwerkfehler bei Sistrix-Aufruf: ' + e.message);
            }
        },
        erreichbarkeitStub() { alert('HTTP-Erreichbarkeitscheck: kommt als Backend-Job.'); },
        // KI-Tags-Modal-State
        kiTagsModal: { offen: false, laeuft: false, slugs: [], begruendung: '' },
        async kiTagsVorschlag() {
            if (!confirm('KI schlägt passende Tags für diese Domain vor (verbraucht Anthropic-Tokens). Fortfahren?')) return;
            this.kiTagsModal.laeuft = true;
            this.kiTagsModal.offen = true;
            this.kiTagsModal.slugs = [];
            this.kiTagsModal.begruendung = '';
            try {
                const r = await fetch('/api/v1/lam/ki-tags-vorschlag', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.kiTagsModal.slugs = j.data.tag_slugs || [];
                this.kiTagsModal.begruendung = j.data.begruendung || '';
            } catch (e) {
                this.kiTagsModal.begruendung = 'Fehler: ' + e.message;
            } finally { this.kiTagsModal.laeuft = false; }
        },
        async kiTagsUebernehmen() {
            if (this.kiTagsModal.slugs.length === 0) return;
            try {
                const rt = await fetch('/api/v1/lam/tags', { credentials: 'same-origin' });
                const jt = await rt.json();
                if (!jt.success) throw new Error('Tags-Liste laden fehlgeschlagen');
                const ids = jt.data.filter(t => this.kiTagsModal.slugs.includes(t.slug)).map(t => t.id);
                await fetch('/api/v1/lam/domain-tags-set', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID, tag_ids: ids }),
                });
                location.reload();
            } catch (e) { alert('Übernahme fehlgeschlagen: ' + e.message); }
        },
        // KI-Recherche-Modal-State
        kiRechercheModal: { offen: false, laeuft: false, daten: null, fehler: '' },
        async kiRecherche() {
            if (!confirm('KI recherchiert Eigentümer, Impressum und Themenschwerpunkt dieser Domain (crawlt Startseite + Impressum, verbraucht Anthropic-Tokens). Fortfahren?')) return;
            this.kiRechercheModal.offen = true;
            this.kiRechercheModal.laeuft = true;
            this.kiRechercheModal.daten = null;
            this.kiRechercheModal.fehler = '';
            try {
                const r = await fetch('/api/v1/lam/ki-recherche-domain', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.kiRechercheModal.daten = j.data;
            } catch (e) {
                this.kiRechercheModal.fehler = e.message;
            } finally { this.kiRechercheModal.laeuft = false; }
        },
        /** Generiert Kurzbeschreibung via KI-Recherche (crawlt Startseite + Über-uns + Impressum). */
        async kiKurzbeschreibungGenerieren() {
            if (this.kbLaeuft) return;
            this.kbLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/ki-recherche-domain', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message || j.error || 'Unbekannter Fehler');
                if (j.data.kurzbeschreibung) {
                    this.kurzbeschreibung = j.data.kurzbeschreibung;
                }
                this.showToast('✓ Kurzbeschreibung generiert. Bei Bedarf gleich editierbar.', 'ok');
            } catch (e) {
                this.showToast('Generierung fehlgeschlagen: ' + e.message, 'fehler');
            }
            this.kbLaeuft = false;
        },
        showToast(text, typ) {
            const farben = { ok: 'var(--emerald-600)', warn: 'var(--amber-600)', fehler: 'var(--rose-600)' };
            const el = document.createElement('div');
            el.textContent = text;
            el.style.cssText = 'position:fixed;top:80px;right:24px;z-index:10000;background:' + (farben[typ || 'ok']) + ';color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 6px 20px rgba(15,23,42,0.18);font-size:0.9rem;max-width:420px;line-height:1.45;transition:opacity 0.3s, transform 0.3s;';
            document.body.appendChild(el);
            setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(-8px)'; }, 4500);
            setTimeout(() => el.remove(), 5000);
        },
        kiGenerierenStub() { this.kiKurzbeschreibungGenerieren(); },
        // Linkpool-Aufnahme
        linkpoolModal: { offen: false, customerId: '', kunden: [], laeuft: false },
        async oeffneLinkpoolModal() {
            this.linkpoolModal.offen = true;
            if (this.linkpoolModal.kunden.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.linkpoolModal.kunden = j.data || [];
                } catch (e) {}
            }
        },
        async speichereLinkpool() {
            if (!this.linkpoolModal.customerId) return;
            this.linkpoolModal.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-kunde-toggle', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: DOMAIN_ID, kunde_id: this.linkpoolModal.customerId })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) {
                alert('Aufnahme fehlgeschlagen: ' + e.message);
                this.linkpoolModal.laeuft = false;
            }
        },

        // Neue Maßnahme
        massnahmeModal: { offen: false, customerId: '', buchungstyp: '', preis: '', geplantAm: '', linktext: '', kunden: [], laeuft: false },
        async oeffneMassnahmeModal() {
            this.massnahmeModal.offen = true;
            if (this.massnahmeModal.kunden.length === 0) {
                try {
                    const r = await fetch('/api/v1/lam/linkprofil/kunden', { credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.success) this.massnahmeModal.kunden = j.data || [];
                } catch (e) {}
            }
        },
        async speichereMassnahme() {
            if (!this.massnahmeModal.customerId) return;
            this.massnahmeModal.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/massnahme-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_id: this.massnahmeModal.customerId,
                        domain_id: DOMAIN_ID,
                        status: 'idee',
                        buchungstyp: this.massnahmeModal.buchungstyp || null,
                        linktext: this.massnahmeModal.linktext || null,
                        geplant_am: this.massnahmeModal.geplantAm || null,
                    })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message || j.error);
                const neueMassnahmeId = j.data.id;

                // Preis (weiterverrechnet) → Auslage anlegen
                if (this.massnahmeModal.preis) {
                    try {
                        await fetch('/api/v1/lam/auslage-save', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                massnahme_id: neueMassnahmeId,
                                weiterverrechnet: this.massnahmeModal.preis,
                                sonderfall: 'normal',
                            })
                        });
                    } catch (e) {}
                }

                window.location.href = '/lam/massnahmen/' + encodeURIComponent(neueMassnahmeId);
            } catch (e) {
                alert('Maßnahme anlegen fehlgeschlagen: ' + e.message);
                this.massnahmeModal.laeuft = false;
            }
        },
    };
}

window.aktualisiereKonditionVerifikation = async function(id, status) {
    const r = await fetch('/api/v1/lam/kondition-verifikation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id, status }),
    });
    const j = await r.json();
    if (!j.success) alert(j.error || 'Status konnte nicht gesetzt werden.');
};

function domainWissenBox(init) {
    return {
        form: init,
        bearbeiten: false,
        laeuft: false,
        anwendenLaeuft: false,
        async anwenden() {
            if (!confirm('Linkart und Empfehlung dieser Domain wirklich auf alle bekannten Verlinkungen (kundenübergreifend) ausrollen? Nicht gesetzte Empfehlungen werden überschrieben, manuelle Bewertungen bleiben unangetastet.')) return;
            this.anwendenLaeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-wissen-anwenden', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ domain: this.form.domain }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Anwenden fehlgeschlagen.'); return; }
                alert(`Aktualisiert: Linkart bei ${j.data.linkart_aktualisiert} Verlinkungen, Empfehlung bei ${j.data.empfehlung_aktualisiert}.`);
            } finally { this.anwendenLaeuft = false; }
        },
        async speichern() {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/domain-wissen-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.form),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Speichern fehlgeschlagen.'); return; }
                this.form.manuell = 1;
                this.bearbeiten = false;
            } finally { this.laeuft = false; }
        },
    };
}
</script>
