<?php
/**
 * Maßnahmen-Detail-Seite — /lam/massnahmen/{id}
 */
use Core\Database;
use Services\LamService;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$m = $svc->getMassnahmeDetail($massnahmeId ?? '');

if (!$m) {
    echo '<div class="thx-page-header"><h1 class="thx-page-title">Maßnahme nicht gefunden</h1></div>';
    echo '<a href="/lam/massnahmen" style="color:var(--thoxan-700);">‹ Zurück zur Liste</a>';
    return;
}

$activeModul = 'massnahmen';

$statusStyle = function($status) {
    $m = [
        'idee'         => 'background:var(--slate-100);color:var(--slate-700);',
        'akquise'      => 'background:var(--thoxan-100);color:var(--thoxan-700);',
        'bei_kunde'    => 'background:#ede9fe;color:#5b21b6;',
        'beauftragt'   => 'background:var(--amber-100);color:#92400e;',
        'bei_anbieter' => 'background:var(--indigo-100);color:var(--indigo-700);',
        'live'         => 'background:var(--emerald-100);color:var(--emerald-800);',
        'archiv'       => 'background:var(--slate-200);color:var(--slate-700);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};

$pipeline = ['idee','akquise','bei_kunde','beauftragt','bei_anbieter','live','archiv'];
$aktuellerIdx = array_search($m['status'], $pipeline);
$istSpezialstatus = !in_array($m['status'], $pipeline, true);

$statusLabel       = fn($s) => \Services\LamService::MASSNAHME_STATUS_LABELS[$s] ?? $s;
$sonderstatusLabel = fn($s) => \Services\LamService::MASSNAHME_SONDERSTATUS_LABELS[$s] ?? $s;
$vorgangstypLabel  = fn($s) => \Services\LamService::MASSNAHME_VORGANGSTYP_LABELS[$s] ?? $s;
$buchungstypLabel  = function($s) {
    $m = ['gastartikel'=>'Gastartikel','advertorial'=>'Advertorial','pressemitteilung'=>'Pressemitteilung','interview'=>'Interview','verzeichnis'=>'Verzeichnis','startseite'=>'Startseite'];
    return $m[$s] ?? ($s ?: '—');
};

$euro = function($n) {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, 2, ',', '.') . ' €';
};
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="/assets/js/lam-mail-compose.js"></script>

<div x-data="Object.assign(lamMailCompose(), lamMassnahmeDetail())">

    <!-- Page-Header -->
    <div class="thx-page-header">
        <div>
            <a href="/lam/massnahmen" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zu Maßnahmen</a>
            <h1 class="thx-page-title" style="margin-top:4px;">
                <span style="color:var(--slate-500);font-weight:400;font-size:var(--d-fs-lg);"><?= htmlspecialchars($m['customer_kuerzel']) ?> ·</span>
                <a href="/lam/linkquellen/<?= htmlspecialchars($m['domain_id']) ?>" style="color:var(--slate-800);text-decoration:none;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($m['domain_url']) ?></a>
            </h1>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <span class="lam-badge" style="<?= $statusStyle($m['status']) ?>"><?= htmlspecialchars($statusLabel($m['status'])) ?></span>
                <?php if (!empty($m['sonderstatus']) && $m['sonderstatus'] !== 'normal'): ?>
                    <span class="lam-badge" style="background:var(--amber-200);color:var(--amber-800);">Sonderstatus: <?= htmlspecialchars($sonderstatusLabel($m['sonderstatus'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="thx-page-actions">
            <?php if (!empty($m['anbieter_id'])): ?>
                <button class="lam-btn lam-btn-secondary" @click="schreibeMail()" title="Neue Mail zum Anbieter — automatisch mit Maßnahme verknüpft">📧 Mail schreiben</button>
            <?php endif; ?>
            <?php if (empty($m['plan_a_massnahme_id'])): ?>
                <a class="lam-btn lam-btn-secondary"
                   href="/lam/massnahmen?plan_b_zu=<?= htmlspecialchars($m['id']) ?>&kunde=<?= (int)$m['customer_id'] ?>"
                   title="Diese Maßnahme als Plan A markieren und Ersatz anlegen">📋 Plan B anlegen</a>
            <?php endif; ?>
            <button class="lam-btn lam-btn-danger" @click="loeschen()">Löschen</button>
        </div>
    </div>

    <?php include __DIR__ . '/_tabs.php'; ?>

    <!-- ASANA-Banner -->
    <?php
        $asanaTaskGid = $m['asana_task_gid'] ?? null;
        $asanaCache = !empty($m['asana_task_cache']) ? json_decode($m['asana_task_cache'], true) : null;
        $asanaKonfiguriert = !empty(\Core\Settings::get('asana_pat'));
    ?>
    <section class="lam-card" style="margin-bottom:16px;border-left:4px solid #f06a6a;" x-data="asanaBanner()">
        <?php if (!$asanaKonfiguriert): ?>
            <div style="display:flex;align-items:center;gap:8px;color:var(--slate-500);">
                <span>📋 Asana ist nicht konfiguriert.</span>
                <a href="/admin/settings?tab=asana" style="color:var(--thoxan-700);">→ Einstellungen</a>
            </div>
        <?php elseif (!$asanaTaskGid): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div>
                    <strong>📋 Asana-Task verknüpfen</strong>
                    <p class="muted" style="margin:4px 0 0;font-size:var(--d-fs-xs);">
                        Tasks aus der Kunden-Section laden oder per URL/GID eintragen.
                    </p>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="text" placeholder="Asana-URL oder GID" x-model="manuelleEingabe"
                           style="padding:6px 10px;border:1px solid var(--slate-300);border-radius:4px;font-size:var(--d-fs-sm);width:240px;">
                    <button class="lam-btn lam-btn-sm" @click="verknuepfenManuell()" :disabled="!manuelleEingabe || laeuft">verknüpfen</button>
                    <span class="muted" style="color:var(--slate-400);">·</span>
                    <button class="lam-btn lam-btn-sm" @click="oeffneSuche()">📋 aus Asana wählen</button>
                </div>
            </div>
            <div x-show="suche.offen" x-transition style="margin-top:12px;">
                <!-- Section-Auswahl: konfigurierte Default-Section + alle weiteren (z.B. „Erledigt") -->
                <div x-show="sections.length > 0" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                    <template x-for="s in sections" :key="s.gid">
                        <button @click="suche.sectionGid = s.gid; suchen()"
                                :style="suche.sectionGid === s.gid
                                    ? 'background:var(--thoxan-600);color:#fff;border-color:var(--thoxan-600);'
                                    : (s.ist_default ? 'background:var(--thoxan-50);color:var(--thoxan-700);border-color:var(--thoxan-300);' : 'background:#fff;color:var(--slate-700);border-color:var(--slate-300);')"
                                style="padding:4px 12px;border:1px solid;border-radius:999px;cursor:pointer;font-size:var(--d-fs-xs);">
                            <span x-text="s.name"></span>
                            <span x-show="s.ist_default && suche.sectionGid !== s.gid" style="font-size:0.7rem;opacity:0.7;"> · Standard</span>
                        </button>
                    </template>
                </div>

                <input type="text" x-model="suche.q" @input.debounce.300ms="suchen()" placeholder="Task-Name eingeben (oder leer für alle Tasks in gewählter Section) …"
                       style="padding:6px 10px;border:1px solid var(--slate-300);border-radius:4px;width:100%;font-size:var(--d-fs-sm);">
                <div x-show="suche.laedt" class="muted" style="font-size:var(--d-fs-xs);margin-top:4px;">… lade Asana</div>
                <ul style="margin-top:8px;list-style:none;padding:0;max-height:300px;overflow-y:auto;">
                    <template x-for="t in suche.treffer" :key="t.gid">
                        <li @click="verknuepfen(t)"
                            style="padding:8px 10px;border-bottom:1px solid var(--slate-100);cursor:pointer;font-size:var(--d-fs-sm);"
                            onmouseover="this.style.background='var(--slate-50)'" onmouseout="this.style.background=''">
                            <strong x-text="t.name"></strong>
                            <span x-show="t.completed" class="lam-badge" style="background:var(--emerald-100);color:var(--emerald-800);font-size:var(--d-fs-xs);margin-left:4px;">✓ erledigt</span>
                            <span x-show="t.due_on" class="muted" style="font-size:var(--d-fs-xs);margin-left:4px;">fällig <span x-text="t.due_on"></span></span>
                        </li>
                    </template>
                    <li x-show="!suche.laedt && suche.treffer.length === 0 && !suche.fehler" class="muted" style="padding:12px;font-size:var(--d-fs-xs);">Keine Tasks gefunden.</li>
                </ul>
                <div x-show="suche.fehler" style="color:var(--rose-600);font-size:var(--d-fs-xs);margin-top:6px;" x-text="suche.fehler"></div>
            </div>
        <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <a href="<?= htmlspecialchars($asanaCache['permalink_url'] ?? '#') ?>" target="_blank" rel="noopener" style="color:#f06a6a;font-weight:600;">
                        📋 <?= htmlspecialchars($asanaCache['name'] ?? $asanaTaskGid) ?> ↗
                    </a>
                    <div class="muted" style="font-size:var(--d-fs-xs);margin-top:2px;">
                        <?php if (!empty($asanaCache['completed'])): ?>
                            <span style="color:var(--emerald-700);">✓ erledigt</span>
                        <?php else: ?>
                            offen
                        <?php endif; ?>
                        <?php if (!empty($asanaCache['due_on'])): ?> · fällig <?= htmlspecialchars($asanaCache['due_on']) ?><?php endif; ?>
                        <?php if (!empty($asanaCache['assignee']['name'])): ?> · @<?= htmlspecialchars($asanaCache['assignee']['name']) ?><?php endif; ?>
                        <?php if (!empty($m['asana_zuletzt_synchronisiert_am'])): ?>
                            · zuletzt synchronisiert <?= htmlspecialchars($m['asana_zuletzt_synchronisiert_am']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:6px;">
                    <button class="lam-btn lam-btn-sm" @click="aktualisieren()" :disabled="laeuft">↻ aktualisieren</button>
                    <button class="lam-btn lam-btn-sm" @click="extrahieren()" :disabled="laeuft">🤖 Felder extrahieren</button>
                    <button class="lam-btn lam-btn-sm lam-btn-danger" @click="entkoppeln()">entkoppeln</button>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Pipeline-Visualisierung -->
    <section class="lam-card" style="margin-bottom:20px;">
        <h3 style="margin:0 0 10px 0;">Pipeline</h3>
        <div style="display:flex;align-items:center;gap:0;">
            <?php foreach ($pipeline as $i => $s):
                $aktiv = $aktuellerIdx !== false && $i <= $aktuellerIdx;
                $istAktuell = $s === $m['status'];
                $farbe = $aktiv ? 'var(--thoxan-600)' : 'var(--slate-300)';
            ?>
                <div style="display:flex;flex-direction:column;align-items:center;flex:1;">
                    <button @click="setzeStatus('<?= $s ?>')"
                            style="width:32px;height:32px;border-radius:50%;background:<?= $farbe ?>;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;<?= $istAktuell ? 'box-shadow:0 0 0 4px var(--thoxan-200);' : '' ?>"
                            title="Auf '<?= $statusLabel($s) ?>' setzen">
                        <?= $i + 1 ?>
                    </button>
                    <div style="margin-top:6px;font-size:var(--d-fs-xs);color:<?= $aktiv ? 'var(--slate-800)' : 'var(--slate-500)' ?>;text-align:center;line-height:1.25;"><?= $statusLabel($s) ?></div>
                </div>
                <?php if ($i < count($pipeline) - 1): ?>
                    <div style="flex:0 0 auto;width:30px;height:2px;background:<?= ($i < ($aktuellerIdx ?? -1)) ? 'var(--thoxan-600)' : 'var(--slate-300)' ?>;margin:0 -8px;align-self:flex-start;margin-top:15px;"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($istSpezialstatus): ?>
            <div style="margin-top:12px;padding:8px 12px;background:var(--amber-50);border:1px solid var(--amber-200);border-radius:4px;font-size:var(--d-fs-sm);">
                Sonderstatus: <strong><?= htmlspecialchars($statusLabel($m['status'])) ?></strong>
                <button @click="setzeStatus('idee')" class="lam-btn lam-btn-small" style="margin-left:8px;">Zurück zu Pipeline</button>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($m['plan_a'])): ?>
    <section class="lam-card" style="margin-bottom:20px;background:var(--amber-50);border-color:var(--amber-200);">
        <h3 style="margin:0 0 8px 0;color:var(--amber-800);">⚠ Diese Maßnahme ist Plan B</h3>
        <p style="margin:0;font-size:var(--d-fs-sm);">
            Ursprünglich war geplant: <strong><?= htmlspecialchars($m['plan_a']['domain_url']) ?></strong>
            (Status: <?= htmlspecialchars($statusLabel($m['plan_a']['status'])) ?>) →
            <a href="/lam/massnahmen/<?= htmlspecialchars($m['plan_a']['id']) ?>" style="color:var(--thoxan-700);">Plan A öffnen</a>
        </p>
    </section>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

        <!-- Linke Spalte -->
        <section style="display:flex;flex-direction:column;gap:20px;">

            <!-- Wiederholungstäter-Hinweis -->
            <?php
                $vorherige = $svc->listeMassnahmen([
                    'customer_id' => (int)$m['customer_id'],
                    'domain_id'   => $m['domain_id'],
                    'limit'       => 20,
                ])['rows'] ?? [];
                $vorherige = array_values(array_filter($vorherige, fn($v) => $v['id'] !== $m['id']));
            ?>
            <?php if (!empty($vorherige)): ?>
            <div class="lam-card" style="border:1px solid #fdba74;background:#fff7ed;">
                <h3 style="margin-top:0;color:#9a3412;">⚠ Wiederholungstäter</h3>
                <p style="margin:0 0 10px 0;font-size:var(--d-fs-sm);color:#7c2d12;">
                    Diese Domain hatte bereits <strong><?= count($vorherige) ?></strong> Maßnahme<?= count($vorherige) !== 1 ? 'n' : '' ?> für <strong><?= htmlspecialchars($m['customer_kuerzel']) ?></strong>.
                </p>
                <ul style="margin:0;padding-left:18px;font-size:var(--d-fs-sm);">
                    <?php foreach (array_slice($vorherige, 0, 5) as $v): ?>
                        <li>
                            <a href="/lam/massnahmen/<?= htmlspecialchars($v['id']) ?>" style="color:var(--thoxan-700);">
                                <?= $v['veroeffentlicht_am']
                                    ? date('d.m.Y', strtotime($v['veroeffentlicht_am']))
                                    : ($v['geplant_am'] ? 'geplant ' . date('d.m.Y', strtotime($v['geplant_am'])) : 'ohne Datum') ?>
                                · Status: <?= htmlspecialchars($statusLabel($v['status'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($vorherige) > 5): ?>
                        <li>… <?= count($vorherige) - 5 ?> weitere</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Buchung (inline editierbar) -->
            <div class="lam-card">
                <h3 style="margin-top:0;">Buchung</h3>
                <table class="lam-table lam-detail-table">
                    <tbody>
                        <tr>
                            <td class="muted" style="width:38%;">Buchungstyp</td>
                            <td>
                                <select x-model="feld.buchungstyp" @change="speichereFeld('buchungstyp')" class="lam-detail-select">
                                    <option value="">— nicht gesetzt —</option>
                                    <option value="gastartikel">Gastartikel</option>
                                    <option value="advertorial">Advertorial</option>
                                    <option value="pressemitteilung">Pressemitteilung</option>
                                    <option value="interview">Interview</option>
                                    <option value="verzeichnis">Verzeichnis</option>
                                    <option value="startseite">Startseite</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td class="muted">Linktext</td>
                            <td><input type="text" x-model="feld.linktext" @change="speichereFeld('linktext')" placeholder="z.B. „Mehr erfahren"" class="lam-detail-input"></td>
                        </tr>
                        <tr>
                            <td class="muted">Brand-Integration</td>
                            <td><input type="text" x-model="feld.brand_integration" @change="speichereFeld('brand_integration')" placeholder="z.B. Markenname im Anchor" class="lam-detail-input"></td>
                        </tr>
                        <tr>
                            <td class="muted">Geplant am</td>
                            <td><input type="date" x-model="feld.geplant_am" @change="speichereFeld('geplant_am')" class="lam-detail-input" style="width:auto;"></td>
                        </tr>
                        <tr>
                            <td class="muted">Sonderstatus</td>
                            <td>
                                <select x-model="feld.sonderstatus" @change="speichereFeld('sonderstatus')" class="lam-detail-select">
                                    <?php foreach (\Services\LamService::MASSNAHME_SONDERSTATUS_LABELS as $slug => $label): ?>
                                        <option value="<?= $slug ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Veröffentlichung (inline editierbar) -->
            <div class="lam-card">
                <h3 style="margin-top:0;">Veröffentlichung</h3>
                <table class="lam-table lam-detail-table">
                    <tbody>
                        <tr>
                            <td class="muted" style="width:38%;">Veröffentlicht am</td>
                            <td><input type="date" x-model="feld.veroeffentlicht_am" @change="speichereFeld('veroeffentlicht_am')" class="lam-detail-input" style="width:auto;"></td>
                        </tr>
                        <tr>
                            <td class="muted">URL</td>
                            <td style="display:flex;align-items:center;gap:8px;">
                                <input type="url" x-model="feld.veroeffentlichungs_url" @change="speichereFeld('veroeffentlichungs_url')" placeholder="https://…" class="lam-detail-input">
                                <a x-show="feld.veroeffentlichungs_url" :href="feld.veroeffentlichungs_url" target="_blank" rel="noopener" style="color:var(--thoxan-700);text-decoration:none;" title="Öffnen">↗</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Eingangsrechnung und Weiterberechnung (Auslage) -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;">Eingangsrechnung und Weiterberechnung</h3>
                    <div style="display:flex;gap:6px;">
                        <button class="lam-btn lam-btn-accent lam-btn-small" @click="oeffneAuslageDrawer()"><?= !empty($m['auslage']) ? 'Bearbeiten' : '+ neu' ?></button>
                        <?php if (!empty($m['auslage'])): ?>
                            <button class="lam-btn lam-btn-small" style="color:var(--rose-700);" @click="loescheAuslage()">Löschen</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($m['auslage'])): ?>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <tbody>
                            <tr><td class="muted" style="width:35%;">Externe Kosten</td><td><?= $euro($m['auslage']['externe_kosten']) ?></td></tr>
                            <tr><td class="muted">Rechnung eingegangen</td><td><?= htmlspecialchars($m['auslage']['rechnung_eingang'] ?: '—') ?></td></tr>
                            <tr><td class="muted">An Kunden weiterverrechnet</td><td><?= $euro($m['auslage']['weiterverrechnet']) ?></td></tr>
                            <tr><td class="muted">Thoxan-Rechnungs-Nr.</td><td><?= htmlspecialchars($m['auslage']['thoxan_rechnung_nr'] ?: '—') ?></td></tr>
                            <tr><td class="muted">Thoxan-Rechnung Datum</td><td><?= htmlspecialchars($m['auslage']['thoxan_rechnung_datum'] ?: '—') ?></td></tr>
                            <tr><td class="muted">Abgerechnet für</td><td><?= htmlspecialchars($m['auslage']['abgerechnet_fuer'] ?: '—') ?></td></tr>
                            <tr><td class="muted">Sonderfall</td><td><?= htmlspecialchars($m['auslage']['sonderfall']) ?></td></tr>
                            <?php if (!empty($m['auslage']['marge'])): ?>
                                <tr>
                                    <td class="muted">Marge (berechnet)</td>
                                    <td>
                                        <strong style="color:<?= ((float)$m['auslage']['marge'] < 0) ? 'var(--rose-700)' : 'var(--emerald-700)' ?>;">
                                            <?= $euro($m['auslage']['marge']) ?>
                                        </strong>
                                        <?php if (!empty($m['auslage']['marge_grund'])): ?>
                                            <span class="muted" style="font-size:var(--d-fs-xs);"> · <?= htmlspecialchars($m['auslage']['marge_grund']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Noch keine Auslagen-Daten erfasst.</p>
                <?php endif; ?>
            </div>

            <!-- Link-Monitoring -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;">Link-Monitoring (<?= count($m['monitoring']) ?>)</h3>
                    <span class="muted" style="font-size:var(--d-fs-xs);">Sofort-Prüfung kommt</span>
                </div>
                <?php if (!empty($m['monitoring'])): ?>
                    <table class="lam-table" style="font-size:var(--d-fs-sm);">
                        <thead><tr><th>Zeitpunkt</th><th class="center">HTTP</th><th class="center">Link</th><th>Typ</th><th class="center">Alert</th><th>Fehler</th></tr></thead>
                        <tbody>
                            <?php foreach ($m['monitoring'] as $mc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($mc['zeitpunkt']) ?></td>
                                    <td class="center"><?= (int)$mc['http_status'] ?: '—' ?></td>
                                    <td class="center">
                                        <?php if ($mc['link_vorhanden']): ?>
                                            <span class="lam-dot ok"></span>
                                        <?php else: ?>
                                            <span class="lam-dot error"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mc['link_typ'] ?: '—') ?></td>
                                    <td class="center">
                                        <?php if ($mc['alert_ausgeloest']): ?>
                                            <span class="lam-badge" style="background:var(--rose-100);color:var(--rose-800);">Alert</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="muted" style="font-size:var(--d-fs-xs);"><?= htmlspecialchars($mc['fehlermeldung'] ?: '—') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Noch keine Monitoring-Checks.</p>
                <?php endif; ?>
            </div>

            <!-- Korrespondenz -->
            <?php if (!empty($m['kommunikation'])): ?>
            <div class="lam-card">
                <h3>Korrespondenz zu dieser Maßnahme (<?= count($m['kommunikation']) ?>)</h3>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($m['kommunikation'] as $k): ?>
                        <div style="border:1px solid var(--slate-200);border-radius:6px;padding:10px 12px;">
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:4px;">
                                <span><strong><?= htmlspecialchars($k['typ']) ?></strong> · <?= htmlspecialchars($k['zeitpunkt']) ?></span>
                                <?php if (!empty($k['kontakt_nachname'])): ?>
                                    <span><?= htmlspecialchars(trim(($k['kontakt_vorname'] ?: '') . ' ' . $k['kontakt_nachname'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($k['betreff'])): ?>
                                <div style="font-weight:500;margin-bottom:4px;"><?= htmlspecialchars($k['betreff']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($k['inhalt'])): ?>
                                <div style="white-space:pre-wrap;color:var(--slate-700);font-size:var(--d-fs-sm);max-height:200px;overflow:auto;"><?= htmlspecialchars($k['inhalt']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($k['anhang_originalname'])): ?>
                                <div style="margin-top:6px;">
                                    <a href="/api/v1/lam/korrespondenz-anhang?id=<?= urlencode($k['id']) ?>" style="color:var(--thoxan-700);font-size:var(--d-fs-xs);">📎 <?= htmlspecialchars($k['anhang_originalname']) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Rechte Sidebar -->
        <aside style="display:flex;flex-direction:column;gap:20px;">

            <!-- Zuordnung -->
            <div class="lam-card">
                <h3>Zuordnung</h3>
                <table class="lam-table" style="font-size:var(--d-fs-sm);">
                    <tbody>
                        <tr>
                            <td class="muted" style="width:40%;">Kunde</td>
                            <td><strong><?= htmlspecialchars($m['customer_kuerzel'] ?: '—') ?></strong> <span class="muted"><?= htmlspecialchars($m['customer_name'] ?: '') ?></span></td>
                        </tr>
                        <tr>
                            <td class="muted">Domain</td>
                            <td>
                                <a href="/lam/linkquellen/<?= htmlspecialchars($m['domain_id']) ?>" style="color:var(--thoxan-700);"><?= htmlspecialchars($m['domain_url']) ?></a>
                            </td>
                        </tr>
                        <?php if (!empty($m['anbieter_name'])): ?>
                            <tr>
                                <td class="muted">Anbieter</td>
                                <td>
                                    <a href="/lam/anbieter/<?= htmlspecialchars($m['anbieter_id']) ?>" style="color:var(--thoxan-700);"><?= htmlspecialchars($m['anbieter_name']) ?></a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($m['verantwortlicher_name'])): ?>
                            <tr><td class="muted">Verantwortlich</td><td><?= htmlspecialchars($m['verantwortlicher_name']) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Linkziel -->
            <?php if (!empty($m['linkziel_url']) || !empty($m['linkziel_thema'])): ?>
            <div class="lam-card">
                <h3>Linkziel</h3>
                <?php if (!empty($m['linkziel_thema'])): ?>
                    <div style="font-weight:500;font-size:var(--d-fs-base);margin-bottom:6px;"><?= htmlspecialchars($m['linkziel_thema']) ?></div>
                <?php endif; ?>
                <?php if (!empty($m['linkziel_url'])): ?>
                    <a href="<?= htmlspecialchars($m['linkziel_url']) ?>" target="_blank" rel="noopener" style="color:var(--thoxan-700);font-size:var(--d-fs-xs);word-break:break-all;">
                        <?= htmlspecialchars($m['linkziel_url']) ?> ↗
                    </a>
                <?php endif; ?>
                <?php if (!empty($m['linkziel_linktext'])): ?>
                    <p style="margin-top:6px;font-size:var(--d-fs-xs);color:var(--slate-600);">Bevorzugter Linktext: <em><?= htmlspecialchars($m['linkziel_linktext']) ?></em></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Asana-Verknüpfung -->
            <?php if (!empty($m['asana_task_gid'])): ?>
            <div class="lam-card">
                <h3>Asana</h3>
                <p style="font-size:var(--d-fs-sm);">Task-GID: <code style="font-size:var(--d-fs-xs);"><?= htmlspecialchars($m['asana_task_gid']) ?></code></p>
                <?php if (!empty($m['asana_zuletzt_synchronisiert_am'])): ?>
                    <p class="muted" style="font-size:var(--d-fs-xs);">zuletzt synchronisiert <?= htmlspecialchars($m['asana_zuletzt_synchronisiert_am']) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </aside>

    </div>

    <!-- Bearbeiten-Drawer entfernt — alle Felder sind direkt inline in der Buchung/Veröffentlichung-Karte editierbar -->

    <!-- Auslage-Drawer (Anlegen/Bearbeiten) -->
    <div class="thx-drawer-backdrop" x-show="auslageDrawer.offen" @click.self="auslageDrawer.offen = false" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title">Auslage bearbeiten</h2>
                <button class="thx-modal-close" @click="auslageDrawer.offen = false">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Externe Kosten (€)</label>
                        <input type="number" step="0.01" x-model="auslageDrawer.externe_kosten">
                    </div>
                    <div class="thx-form-field">
                        <label>An Kunden weiterverrechnet (€)</label>
                        <input type="number" step="0.01" x-model="auslageDrawer.weiterverrechnet">
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Sonderfall</label>
                    <select x-model="auslageDrawer.sonderfall">
                        <option value="normal">normal</option>
                        <option value="storno_mit_weiterberechnung">Storno mit Weiterberechnung</option>
                        <option value="intern">intern (Thoxan-Eigenleistung)</option>
                        <option value="sammelposten">Sammelposten (wiederkehrend)</option>
                        <option value="jahresueberhang">Jahresüberhang (Q4 → Q1)</option>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Marge-Grund (z.B. bei Storno oder negativer Marge)</label>
                    <input type="text" x-model="auslageDrawer.marge_grund">
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Rechnung eingegangen (Datum)</label>
                        <input type="date" x-model="auslageDrawer.rechnung_eingang">
                    </div>
                    <div class="thx-form-field">
                        <label>Thoxan-Rechnung Datum</label>
                        <input type="date" x-model="auslageDrawer.thoxan_rechnung_datum">
                    </div>
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Thoxan-Rechnungs-Nr.</label>
                        <input type="text" x-model="auslageDrawer.thoxan_rechnung_nr">
                    </div>
                    <div class="thx-form-field">
                        <label>Abgerechnet für</label>
                        <input type="text" x-model="auslageDrawer.abgerechnet_fuer" placeholder="z.B. Q2 2026">
                    </div>
                </div>
                <div style="padding:10px;background:var(--slate-50);border-radius:4px;font-size:var(--d-fs-sm);">
                    <strong>Marge (berechnet):</strong>
                    <span x-text="margeBerechnet()" :style="margeFarbe()"></span>
                </div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="auslageDrawer.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereAuslage()" :disabled="auslageDrawer.laeuft">Speichern</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/_mail_compose.php'; ?>

</div>

<style>
[x-cloak] { display: none !important; }
.lam-detail-table { font-size: 0.9rem; }
.lam-detail-table td { padding: 8px 10px; }
.lam-detail-input,
.lam-detail-select {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--slate-300);
    border-radius: 5px;
    background: #fff;
    font-size: 0.9rem;
    font-family: inherit;
    line-height: 1.4;
}
.lam-detail-input:focus,
.lam-detail-select:focus {
    outline: none;
    border-color: var(--thoxan-500);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}
</style>

<script>
window.MASSNAHME_ID = <?= json_encode($m['id']) ?>;
function lamMassnahmeDetail() {
    const MASSNAHME_ID = window.MASSNAHME_ID;
    const MASSNAHME_ANBIETER_ID = <?= json_encode($m['anbieter_id'] ?? null) ?>;
    const MASSNAHME_KONTEXT_BETREFF = <?= json_encode('Maßnahme: ' . ($m['customer_kuerzel'] ?? '?') . ' · ' . ($m['domain_url'] ?? '')) ?>;
    return {
        async schreibeMail() {
            if (!MASSNAHME_ANBIETER_ID) {
                alert('Maßnahme hat keinen Anbieter — Anbieter zuordnen in der Linkquelle.');
                return;
            }
            // Primärkontakt des Anbieters holen
            let empfaenger = '', kontaktName = '';
            try {
                const r = await fetch('/api/v1/lam/anbieter-detail?id=' + encodeURIComponent(MASSNAHME_ANBIETER_ID), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success && j.data && j.data.kontakte) {
                    const prim = j.data.kontakte.find(k => k.prioritaet == 1 && k.email) || j.data.kontakte.find(k => k.email);
                    if (prim) {
                        empfaenger = prim.email;
                        kontaktName = ((prim.vorname || '') + ' ' + (prim.nachname || '')).trim();
                    }
                }
            } catch (e) {}
            this.oeffneMailCompose({
                empfaenger,
                betreff: MASSNAHME_KONTEXT_BETREFF,
                anbieterId: MASSNAHME_ANBIETER_ID,
                massnahmeId: MASSNAHME_ID,
                hinweis: 'An: ' + (kontaktName ? kontaktName + ' (' + empfaenger + ')' : empfaenger || 'Bitte Empfänger eintragen') + ' · Maßnahme + Anbieter werden automatisch verknüpft.',
            });
        },
        onMailComposeGesendet() { alert('✓ Mail gesendet und in der Korrespondenz registriert.'); window.location.reload(); },

        auslageDrawer: {
            offen: false,
            externe_kosten: <?= json_encode($m['auslage']['externe_kosten'] ?? '') ?>,
            weiterverrechnet: <?= json_encode($m['auslage']['weiterverrechnet'] ?? '') ?>,
            marge_grund: <?= json_encode($m['auslage']['marge_grund'] ?? '') ?>,
            rechnung_eingang: <?= json_encode($m['auslage']['rechnung_eingang'] ?? '') ?>,
            thoxan_rechnung_nr: <?= json_encode($m['auslage']['thoxan_rechnung_nr'] ?? '') ?>,
            thoxan_rechnung_datum: <?= json_encode($m['auslage']['thoxan_rechnung_datum'] ?? '') ?>,
            sonderfall: <?= json_encode($m['auslage']['sonderfall'] ?? 'normal') ?>,
            abgerechnet_fuer: <?= json_encode($m['auslage']['abgerechnet_fuer'] ?? '') ?>,
            laeuft: false
        },

        oeffneAuslageDrawer() { this.auslageDrawer.offen = true; },
        margeBerechnet() {
            const e = parseFloat(this.auslageDrawer.externe_kosten);
            const w = parseFloat(this.auslageDrawer.weiterverrechnet);
            if (isNaN(e) || isNaN(w)) return '—';
            return (w - e).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
        },
        margeFarbe() {
            const e = parseFloat(this.auslageDrawer.externe_kosten);
            const w = parseFloat(this.auslageDrawer.weiterverrechnet);
            if (isNaN(e) || isNaN(w)) return 'color:var(--slate-500);';
            return (w - e) < 0 ? 'color:var(--rose-700);font-weight:700;' : 'color:var(--emerald-700);font-weight:700;';
        },
        async speichereAuslage() {
            if (this.auslageDrawer.laeuft) return;
            this.auslageDrawer.laeuft = true;
            try {
                await fetch('/api/v1/lam/auslage-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: MASSNAHME_ID, ...this.auslageDrawer })
                });
                window.location.reload();
            } finally { this.auslageDrawer.laeuft = false; }
        },
        async loescheAuslage() {
            if (!confirm('Auslage wirklich löschen?')) return;
            await fetch('/api/v1/lam/auslage-loeschen', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ massnahme_id: MASSNAHME_ID })
            });
            window.location.reload();
        },

        // Inline-Felder (kein Drawer mehr)
        feld: {
            buchungstyp:         <?= json_encode($m['buchungstyp'] ?? '') ?>,
            linktext:            <?= json_encode($m['linktext'] ?? '') ?>,
            brand_integration:   <?= json_encode($m['brand_integration'] ?? '') ?>,
            geplant_am:          <?= json_encode(substr($m['geplant_am'] ?? '', 0, 10) ?: '') ?>,
            sonderstatus:        <?= json_encode($m['sonderstatus'] ?? 'normal') ?>,
            veroeffentlicht_am:  <?= json_encode(substr($m['veroeffentlicht_am'] ?? '', 0, 10) ?: '') ?>,
            veroeffentlichungs_url: <?= json_encode($m['veroeffentlichungs_url'] ?? '') ?>,
        },
        async speichereFeld(name) {
            try {
                const r = await fetch('/api/v1/lam/massnahme-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: MASSNAHME_ID, feld: name, wert: this.feld[name] })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
            } catch (e) { alert(name + ' speichern fehlgeschlagen: ' + e.message); }
        },
        async setzeStatus(neuerStatus) {
            const res = await fetch('/api/v1/lam/massnahme-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: MASSNAHME_ID, feld: 'status', wert: neuerStatus })
            });
            if ((await res.json()).success) window.location.reload();
        },
        async loeschen() {
            if (!confirm('Maßnahme wirklich loeschen? (Soft-Delete)')) return;
            await fetch('/api/v1/lam/massnahme-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [MASSNAHME_ID], aktion: 'loeschen' })
            });
            window.location.href = '/lam/massnahmen';
        }
    };
}

function asanaBanner() {
    return {
        laeuft: false,
        manuelleEingabe: '',
        sections: [],
        suche: { offen: false, q: '', treffer: [], laedt: false, fehler: '', sectionGid: '' },

        async oeffneSuche() {
            this.suche.offen = true;
            await this.ladeSections();
            await this.suchen();
        },
        async ladeSections() {
            if (this.sections.length > 0) return;
            try {
                const r = await fetch('/api/v1/lam/asana-massnahme-sections?massnahme_id=' + encodeURIComponent(window.MASSNAHME_ID), { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success) {
                    this.sections = j.data || [];
                    const def = this.sections.find(s => s.ist_default);
                    if (def) this.suche.sectionGid = def.gid;
                }
            } catch (e) {}
        },
        async suchen() {
            this.suche.laedt = true;
            this.suche.fehler = '';
            try {
                const p = new URLSearchParams({ massnahme_id: window.MASSNAHME_ID });
                if (this.suche.q) p.set('suche', this.suche.q);
                if (this.suche.sectionGid) p.set('section_gid', this.suche.sectionGid);
                const r = await fetch('/api/v1/lam/asana-massnahme-tasks?' + p, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.success) { this.suche.fehler = j.error || j.message || 'Fehler.'; this.suche.treffer = []; return; }
                this.suche.treffer = j.data;
            } finally { this.suche.laedt = false; }
        },
        async verknuepfen(t) {
            await this.verknuepfenIntern(t.gid);
        },
        async verknuepfenManuell() {
            await this.verknuepfenIntern(this.manuelleEingabe);
        },
        async verknuepfenIntern(eingabe) {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/asana-verknuepfen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: window.MASSNAHME_ID, task_gid_oder_url: eingabe }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || j.message || 'Fehler.'); return; }
                location.reload();
            } finally { this.laeuft = false; }
        },
        async aktualisieren() {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/asana-aktualisieren', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: window.MASSNAHME_ID }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler.'); return; }
                location.reload();
            } finally { this.laeuft = false; }
        },
        async entkoppeln() {
            if (!confirm('Asana-Verknüpfung wirklich entfernen?')) return;
            await fetch('/api/v1/lam/asana-entkoppeln', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ massnahme_id: window.MASSNAHME_ID }),
            });
            location.reload();
        },
        async extrahieren() {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/asana-extrahieren', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: window.MASSNAHME_ID }),
                });
                const j = await r.json();
                if (!j.success) { alert(j.error || 'Fehler.'); return; }
                const v = j.data.vorschlaege || {};
                const lines = Object.entries(v).filter(([k, val]) => val !== null && val !== '').map(([k, val]) => `${k}: ${val}`);
                if (lines.length === 0) { alert('KI hat keine Felder gefunden.'); return; }
                const msg = `KI hat folgende Felder vorgeschlagen (Quelle: ${j.data.quelle}):\n\n${lines.join('\n')}\n\nIns LAM übernehmen?\n(Bestehende Felder werden NICHT überschrieben.)`;
                if (!confirm(msg)) return;
                const r2 = await fetch('/api/v1/lam/asana-uebernehmen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ massnahme_id: window.MASSNAHME_ID, vorschlaege: v }),
                });
                const j2 = await r2.json();
                if (!j2.success) { alert(j2.error || 'Übernahme fehlgeschlagen.'); return; }
                alert(`${j2.data.anzahl} Felder übernommen.`);
                location.reload();
            } finally { this.laeuft = false; }
        },
    };
}
</script>
