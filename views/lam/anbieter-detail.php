<?php
/**
 * Anbieter-Detail-Seite — eigene URL /lam/anbieter/{id}
 * Daten werden serverseitig geladen (kein Alpine-Loading mehr).
 */
use Core\Database;
use Services\LamService;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$anbieter = $svc->getAnbieterDetail($anbieterId ?? '');

if (!$anbieter) {
    echo '<div class="thx-page-header"><h1 class="thx-page-title">Anbieter nicht gefunden</h1></div>';
    echo '<a href="/lam/anbieter" style="color:var(--thoxan-700);">‹ Zurück zur Liste</a>';
    return;
}

$activeModul = 'anbieter';

// Helfer für Farben (Inline, damit kein Alpine-JS-Call nötig ist)
$beziehungsStyle = function($status) {
    $m = [
        'neu' => 'background:var(--amber-100);color:var(--amber-800);',
        'etabliert' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
        'vertrauensvoll' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'abgekuehlt' => 'background:var(--slate-200);color:var(--slate-700);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};
$statusStyle = function($status) {
    $m = [
        'neu' => 'background:var(--amber-100);color:var(--amber-800);',
        'in_arbeit' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
        'geprueft' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'verifiziert' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'veraltet' => 'background:#fff7ed;color:#9a3412;',
        'verworfen' => 'background:var(--rose-100);color:var(--rose-800);',
        'geloescht' => 'background:var(--rose-100);color:var(--rose-800);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};
$statusLabel = function($status) {
    $m = [
        'neu' => 'Neu', 'in_arbeit' => 'In Arbeit',
        'geprueft' => 'Geprüft', 'verifiziert' => 'Geprüft',
        'veraltet' => 'Veraltet',
        'verworfen' => 'Gelöscht', 'geloescht' => 'Gelöscht',
    ];
    return $m[$status] ?? $status;
};
$prioLabel = function($p) {
    if ($p == 1) return 'Primär';
    if ($p == 2) return '2. Ansprechpartner';
    if ($p == 3) return '3. Ansprechpartner';
    return $p . '. Ansprechpartner';
};
$aktuelleRolle = ($anbieter['ist_betreiber'] && $anbieter['ist_vermittler'])
    ? 'beides'
    : ($anbieter['ist_vermittler'] ? 'vermittler' : 'betreiber');
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="/assets/js/lam-mail-compose.js"></script>

<div x-data="Object.assign(lamMailCompose(), lamAnbieterDetail())">

    <!-- Page-Header -->
    <?php
        $beziehungLabels = ['neu' => 'Neu', 'etabliert' => 'Etabliert', 'vertrauensvoll' => 'Vertrauensvoll', 'abgekuehlt' => 'Abgekühlt'];
    ?>
    <div class="thx-page-header">
        <div>
            <a href="/lam/anbieter" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zu Anbietern</a>

            <!-- Name (inline editierbar) -->
            <h1 class="thx-page-title" style="margin-top:4px;display:flex;align-items:center;gap:8px;">
                <template x-if="!nameEdit">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <span x-text="name"></span>
                        <button @click="nameEdit = true" title="Name bearbeiten"
                                style="background:none;border:0;cursor:pointer;color:var(--slate-400);padding:2px 6px;border-radius:4px;font-size:0.9rem;"
                                onmouseover="this.style.background='var(--slate-100)';" onmouseout="this.style.background='none';">
                            <span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;">edit</span>
                        </button>
                    </span>
                </template>
                <template x-if="nameEdit">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <input type="text" x-model="name" @keydown.enter="speichereName()" @keydown.escape="abortNameEdit()"
                               style="font-size:1.5rem;font-weight:600;padding:4px 10px;border:1px solid var(--thoxan-400);border-radius:6px;min-width:280px;">
                        <button @click="speichereName()" class="lam-btn lam-btn-primary lam-btn-small">OK</button>
                        <button @click="abortNameEdit()" class="lam-btn lam-btn-secondary lam-btn-small">×</button>
                    </span>
                </template>
            </h1>

            <!-- Firma (inline editierbar, optional) -->
            <div style="margin-top:4px;display:flex;align-items:center;gap:6px;font-size:var(--d-fs-sm);color:var(--slate-500);">
                <template x-if="!firmaEdit">
                    <span style="display:flex;align-items:center;gap:4px;">
                        <span x-show="firma" x-text="firma"></span>
                        <span x-show="!firma" style="font-style:italic;color:var(--slate-400);">— keine Firmierung —</span>
                        <button @click="firmaEdit = true" title="Firma bearbeiten"
                                style="background:none;border:0;cursor:pointer;color:var(--slate-400);padding:2px 6px;border-radius:4px;"
                                onmouseover="this.style.background='var(--slate-100)';" onmouseout="this.style.background='none';">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:-3px;">edit</span>
                        </button>
                    </span>
                </template>
                <template x-if="firmaEdit">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <input type="text" x-model="firma" @keydown.enter="speichereFirma()" @keydown.escape="abortFirmaEdit()"
                               placeholder="z.B. Bantle Media GmbH"
                               style="padding:3px 8px;border:1px solid var(--thoxan-400);border-radius:4px;min-width:240px;font-size:0.85rem;">
                        <button @click="speichereFirma()" class="lam-btn lam-btn-primary lam-btn-small">OK</button>
                        <button @click="abortFirmaEdit()" class="lam-btn lam-btn-secondary lam-btn-small">×</button>
                    </span>
                </template>
            </div>

            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
                <span class="lam-badge" style="<?= $beziehungsStyle($anbieter['beziehungsstatus']) ?>"><?= htmlspecialchars($beziehungLabels[$anbieter['beziehungsstatus']] ?? $anbieter['beziehungsstatus']) ?></span>
                <span class="lam-badge" style="background:var(--slate-800);color:#fff;"><?= htmlspecialchars($anbieter['rollen_label']) ?></span>
            </div>
        </div>
        <div class="thx-page-actions">
            <button class="lam-btn lam-btn-secondary" @click="schreibeMailAnAnbieter()" title="Neue Mail an primären Kontakt schreiben">
                📧 Mail schreiben
            </button>
            <button class="lam-btn lam-btn-danger" @click="loescheAnbieter()">Löschen</button>
        </div>
    </div>

    <?php include __DIR__ . '/_tabs.php'; ?>

    <!-- Aktionen-Bar -->
    <section class="lam-card" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-right:4px;">Beziehung</span>
            <?php foreach ($beziehungLabels as $bz => $bzLabel): ?>
                <button class="lam-chip<?= $anbieter['beziehungsstatus'] === $bz ? ' is-active' : '' ?>"
                        @click="setzeBeziehung('<?= $bz ?>')"><?= $bzLabel ?></button>
            <?php endforeach; ?>

            <span style="width:1px;height:24px;background:var(--slate-300);margin:0 8px;"></span>

            <span style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-right:4px;">Rolle</span>
            <?php
            $aktRolle = ($anbieter['ist_betreiber'] && $anbieter['ist_vermittler']) ? 'beides'
                       : ($anbieter['ist_vermittler'] ? 'vermittler' : 'betreiber');
            $rollen = ['betreiber' => 'Betreiber', 'vermittler' => 'Vermittler', 'beides' => 'Beides'];
            foreach ($rollen as $key => $label):
            ?>
                <button class="lam-chip<?= $aktRolle === $key ? ' is-active' : '' ?>"
                        @click="setzeRolle('<?= $key ?>')"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

        <!-- Linke Spalte: Domains + Kontakte -->
        <section style="display:flex;flex-direction:column;gap:20px;">

            <?php
            // Sektionen nur zeigen, wenn die Rolle dazu aktiviert ist ODER bereits Domains existieren
            $zeigeBetreiber = (int) $anbieter['ist_betreiber'] === 1 || (int) $anbieter['betreibt_domains_anzahl'] > 0;
            $zeigeVermittler = (int) $anbieter['ist_vermittler'] === 1 || (int) $anbieter['vermittelt_domains_anzahl'] > 0;
            ?>

            <?php if ($zeigeBetreiber): ?>
            <!-- Betreibt Domains -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;">Betreibt Domains <span style="color:var(--slate-400);font-weight:500;">(<?= (int)$anbieter['betreibt_domains_anzahl'] ?>)</span></h3>
                    <button class="lam-btn lam-btn-secondary" @click="oeffneDomainPicker('betreiber')">+ Domain zuordnen</button>
                </div>
                <?php if (!empty($anbieter['betreibt_domains'])): ?>
                    <div style="display:flex;flex-direction:column;">
                        <?php foreach ($anbieter['betreibt_domains'] as $d): ?>
                            <div class="lam-domain-row">
                                <a href="/lam/linkquellen/<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['url']) ?></a>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <span class="lam-badge" style="<?= $statusStyle($d['verifikation_status']) ?>"><?= htmlspecialchars($statusLabel($d['verifikation_status'])) ?></span>
                                    <button @click="entferneDomain('<?= htmlspecialchars($d['id']) ?>', 'betreiber', '<?= htmlspecialchars(addslashes($d['url'])) ?>')"
                                            style="background:none;border:0;cursor:pointer;color:var(--slate-400);font-size:0.8rem;text-decoration:underline;"
                                            title="Verknüpfung entfernen (Domain bleibt im Pool)">entfernen</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ((int)$anbieter['betreibt_domains_anzahl'] > 50): ?>
                    <p class="muted">
                        Über 50 Domains. <a href="/lam/linkquellen?anbieter_id=<?= htmlspecialchars($anbieter['id']) ?>" style="color:var(--thoxan-700);">Im Pool filtern →</a>
                    </p>
                <?php else: ?>
                    <p style="padding:24px;text-align:center;color:var(--slate-500);background:var(--slate-50);border-radius:8px;margin:0;font-size:0.9rem;">
                        Noch keine Domains als Betreiber. Klick auf <strong>+ Domain zuordnen</strong>.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($zeigeVermittler): ?>
            <!-- Vermittelt Domains -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;">Vermittelt Domains <span style="color:var(--slate-400);font-weight:500;">(<?= (int)$anbieter['vermittelt_domains_anzahl'] ?>)</span></h3>
                    <button class="lam-btn lam-btn-secondary" @click="oeffneDomainPicker('vermittler')">+ Domain zuordnen</button>
                </div>
                <?php if (!empty($anbieter['vermittelt_domains'])): ?>
                    <div style="display:flex;flex-direction:column;">
                        <?php foreach ($anbieter['vermittelt_domains'] as $d): ?>
                            <div class="lam-domain-row">
                                <a href="/lam/linkquellen/<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['url']) ?></a>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <span class="lam-badge" style="<?= $statusStyle($d['verifikation_status']) ?>"><?= htmlspecialchars($statusLabel($d['verifikation_status'])) ?></span>
                                    <button @click="entferneDomain('<?= htmlspecialchars($d['id']) ?>', 'vermittler', '<?= htmlspecialchars(addslashes($d['url'])) ?>')"
                                            style="background:none;border:0;cursor:pointer;color:var(--slate-400);font-size:0.8rem;text-decoration:underline;"
                                            title="Verknüpfung entfernen (Domain bleibt im Pool)">entfernen</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ((int)$anbieter['vermittelt_domains_anzahl'] > 50): ?>
                    <p class="muted">
                        Über 50 Domains. <a href="/lam/linkquellen?via_anbieter_id=<?= htmlspecialchars($anbieter['id']) ?>" style="color:var(--thoxan-700);">Im Pool filtern →</a>
                    </p>
                <?php else: ?>
                    <p style="padding:24px;text-align:center;color:var(--slate-500);background:var(--slate-50);border-radius:8px;margin:0;font-size:0.9rem;">
                        Noch keine Domains als Vermittler. Klick auf <strong>+ Domain zuordnen</strong>.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Kontakte (inline editierbar) -->
            <div class="lam-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="margin:0;">Kontakte (<span x-text="kontakte.length"></span>)</h3>
                </div>

                <div x-show="kontakte.length === 0" class="muted" style="padding:18px 0;">Noch keine Kontakte. Unten anlegen.</div>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    <template x-for="k in kontakte" :key="k.id">
                        <div :style="(k.prioritaet == 1 ? 'border:1px solid var(--thoxan-300);background:rgba(230,240,248,0.4);' : 'border:1px solid var(--slate-200);') + 'border-radius:8px;padding:12px;'">
                            <div style="display:grid;grid-template-columns:32px 1fr 1fr auto;gap:10px;align-items:center;">
                                <!-- Stern (primär) -->
                                <button @click="setzePrimaer(k.id, k.prioritaet)"
                                        :title="k.prioritaet == 1 ? 'Bereits primärer Ansprechpartner' : 'Als primären Ansprechpartner setzen'"
                                        :style="'font-size:18px;line-height:1;background:transparent;border:none;padding:4px;' + (k.prioritaet == 1 ? 'cursor:default;color:var(--thoxan-600);' : 'cursor:pointer;color:var(--slate-300);')">
                                    <span x-text="k.prioritaet == 1 ? '★' : '☆'"></span>
                                </button>

                                <!-- Vor- + Nachname -->
                                <div style="display:flex;gap:6px;">
                                    <input type="text" x-model="k.vorname" @change="speichereKontaktFeld(k, 'vorname')"
                                           placeholder="Vorname" class="lam-detail-input" style="flex:1;">
                                    <input type="text" x-model="k.nachname" @change="speichereKontaktFeld(k, 'nachname')"
                                           placeholder="Nachname" class="lam-detail-input is-strong" style="flex:1;">
                                </div>

                                <!-- E-Mail + Telefon -->
                                <div style="display:flex;gap:6px;">
                                    <input type="email" x-model="k.email" @change="speichereKontaktFeld(k, 'email')"
                                           placeholder="E-Mail" class="lam-detail-input" style="flex:1;">
                                    <input type="text" x-model="k.telefon" @change="speichereKontaktFeld(k, 'telefon')"
                                           placeholder="Telefon" class="lam-detail-input" style="width:150px;">
                                </div>

                                <!-- Aktionen -->
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <select x-model="k.verifikation_status" @change="kontaktVerifikationSetzen(k)"
                                            class="lam-detail-select">
                                        <option value="neu">Neu</option>
                                        <option value="in_arbeit">In Arbeit</option>
                                        <option value="geprueft">Geprüft</option>
                                        <option value="veraltet">Veraltet</option>
                                        <option value="geloescht">Gelöscht</option>
                                    </select>
                                    <button x-show="k.email" @click="schreibeMailAnAnbieter({ empfaenger: k.email, name: (k.vorname || '') + ' ' + (k.nachname || ''), kontaktId: k.id })"
                                            title="Mail an diesen Kontakt schreiben"
                                            style="background:none;border:0;color:var(--thoxan-700);cursor:pointer;font-size:1rem;padding:4px 8px;">📧</button>
                                    <button @click="loescheKontakt(k.id, k.nachname || k.vorname || '')"
                                            title="Löschen"
                                            style="background:none;border:0;color:var(--rose-500);cursor:pointer;font-size:0.95rem;padding:4px 8px;">✕</button>
                                </div>
                            </div>

                            <!-- Zweite Zeile: Rolle/Position -->
                            <div style="margin-top:8px;display:grid;grid-template-columns:32px 1fr;gap:10px;align-items:center;">
                                <span></span>
                                <input type="text" x-model="k.rolle" @change="speichereKontaktFeld(k, 'rolle')"
                                       placeholder="Position / Rolle (z. B. Geschäftsführer, Redakteur)"
                                       class="lam-detail-input is-muted" style="border-color:var(--slate-200);background:transparent;">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Neuer Kontakt inline -->
                <div style="margin-top:14px;padding:12px;border:1px dashed var(--slate-300);border-radius:8px;background:var(--slate-50);">
                    <div style="font-size:var(--d-fs-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--slate-500);font-weight:600;margin-bottom:8px;">+ Neuer Kontakt</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;align-items:center;">
                        <input type="text" x-model="neuerKontakt.vorname" placeholder="Vorname" class="lam-detail-input">
                        <input type="text" x-model="neuerKontakt.nachname" placeholder="Nachname *" class="lam-detail-input is-strong">
                        <input type="email" x-model="neuerKontakt.email" placeholder="E-Mail" class="lam-detail-input">
                        <input type="text" x-model="neuerKontakt.telefon" placeholder="Telefon" class="lam-detail-input">
                        <button @click="speichereNeuenKontakt()" :disabled="!neuerKontakt.nachname"
                                class="lam-btn lam-btn-primary lam-btn-small">+ Hinzufügen</button>
                    </div>
                    <input type="text" x-model="neuerKontakt.rolle" placeholder="Position / Rolle (optional)"
                           class="lam-detail-input is-muted" style="margin-top:6px;width:100%;">
                </div>
            </div>
        </section>

        <!-- Rechte Spalte: Notizen -->
        <aside>
            <div class="lam-card" style="position:sticky;top:80px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h3 style="margin:0;">
                        <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;color:var(--slate-500);">sticky_note_2</span>
                        Anbieter-Notizen
                    </h3>
                    <span style="font-size:var(--d-fs-xs);color:var(--slate-400);" x-show="notizenSaving">speichert …</span>
                    <span style="font-size:var(--d-fs-xs);color:var(--emerald-600);" x-show="notizenSaved" x-cloak>✓ gespeichert</span>
                </div>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 10px 0;color:var(--slate-500);">Markdown wird unterstützt. Wird beim Verlassen automatisch gespeichert.</p>
                <textarea x-model="notizen"
                          @blur="speichereNotizen()"
                          placeholder="Stammdaten, Konditionen, Erinnerungen, individuelle Vereinbarungen …"
                          style="width:100%;min-height:340px;padding:14px 16px;border:1px solid var(--slate-300);border-radius:8px;font-size:0.9rem;line-height:1.55;font-family:inherit;resize:vertical;background:#fff;transition:border-color 0.15s, box-shadow 0.15s;"
                          onfocus="this.style.borderColor='var(--thoxan-500)';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.12)';"
                          onblur="this.style.borderColor='var(--slate-300)';this.style.boxShadow='none';"></textarea>
                <?php if (!empty($anbieter['erstellt_am']) || !empty($anbieter['aktualisiert_am'])): ?>
                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--slate-100);font-size:var(--d-fs-xs);color:var(--slate-400);display:flex;justify-content:space-between;">
                        <?php if (!empty($anbieter['erstellt_am'])): ?>
                            <span>Angelegt: <?= htmlspecialchars(date('d.m.Y', strtotime($anbieter['erstellt_am']))) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($anbieter['aktualisiert_am']) && $anbieter['aktualisiert_am'] !== $anbieter['erstellt_am']): ?>
                            <span>Geändert: <?= htmlspecialchars(date('d.m.Y', strtotime($anbieter['aktualisiert_am']))) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Korrespondenz, volle Breite -->
        <section style="grid-column:1 / -1;">
            <div class="lam-card">
                <h3>Korrespondenz (alle Vorgänge mit diesem Anbieter)</h3>
                <?php if (!empty($anbieter['korrespondenz'])): ?>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($anbieter['korrespondenz'] as $k): ?>
                            <div style="border:1px solid var(--slate-200);border-radius:6px;padding:10px 12px;">
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:var(--d-fs-xs);color:var(--slate-500);margin-bottom:4px;">
                                    <span><strong><?= htmlspecialchars($k['typ']) ?></strong> · <?= htmlspecialchars($k['zeitpunkt']) ?></span>
                                    <?php if (!empty($k['kontakt_nachname'])): ?>
                                        <span><?= htmlspecialchars(trim(($k['kontakt_vorname'] ?: '') . ' ' . ($k['kontakt_nachname'] ?: ''))) ?></span>
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
                <?php else: ?>
                    <p class="muted">Noch keine Notizen erfasst.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Domain-Picker-Modal -->
    <div x-show="domPicker.offen" x-cloak class="lam-modal-overlay"
         @click.self="domPicker.offen = false">
        <div class="lam-modal-box">
            <div style="padding:16px 22px;border-bottom:1px solid var(--slate-200);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:1rem;">
                    Domain als <strong x-text="domPicker.rolle === 'betreiber' ? 'Betreiber' : 'Vermittler'"></strong> zuordnen
                </h3>
                <button @click="domPicker.offen = false" style="background:none;border:0;cursor:pointer;color:var(--slate-500);font-size:1.4rem;">×</button>
            </div>
            <div style="padding:16px 22px;display:flex;flex-direction:column;gap:12px;">
                <input type="text" x-model="domPicker.suche" @input.debounce.300ms="domPickerFiltern()"
                       placeholder="Domain-URL eintippen — mind. 2 Zeichen"
                       autofocus
                       style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:6px;font-size:0.95rem;">
                <div style="max-height:400px;overflow-y:auto;border:1px solid var(--slate-200);border-radius:6px;background:#fafbfc;">
                    <template x-for="d in domPicker.treffer" :key="d.id">
                        <button type="button"
                                @click="domainZuordnen(d.id, d.url)"
                                style="display:block;width:100%;text-align:left;padding:12px 16px;background:none;border:0;border-bottom:1px solid var(--slate-100);cursor:pointer;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                <div>
                                    <div style="font-weight:600;color:var(--slate-800);" x-text="d.url"></div>
                                    <div x-show="d.anbieter_name" style="font-size:var(--d-fs-xs);color:var(--slate-500);margin-top:2px;">
                                        Aktuell: <span x-text="d.anbieter_name"></span>
                                    </div>
                                </div>
                                <span x-show="d.verifikation_status" class="lam-badge" :style="domainStatusStyle(d.verifikation_status)" style="font-size:0.7rem;padding:2px 8px;" x-text="domainStatusLabel(d.verifikation_status)"></span>
                            </div>
                        </button>
                    </template>
                    <div x-show="domPicker.suche.length >= 2 && domPicker.treffer.length === 0" style="padding:24px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
                        Keine Domains gefunden für „<span x-text="domPicker.suche"></span>".
                    </div>
                    <div x-show="domPicker.suche.length < 2" style="padding:24px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-sm);">
                        Mindestens 2 Zeichen eingeben.
                    </div>
                </div>
                <div style="font-size:var(--d-fs-xs);color:var(--slate-500);">
                    Tipp: Im <a href="/lam/linkquellen" style="color:var(--thoxan-700);">Linkquellen-Pool</a> findest Du alle 6 700+ Domains. Neue Domains dort über „+ Neue Linkquelle" oder per Import anlegen.
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/_mail_compose.php'; ?>

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
.lam-modal-box {
    background: #fff; border-radius: 10px;
    width: 640px; max-width: calc(100% - 40px);
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.2);
    overflow: hidden;
}
/* Einheitliche Schriftgrößen für die Anbieter-Detail-Sektionen */
.lam-detail-input,
.lam-detail-select {
    padding: 6px 10px;
    border: 1px solid var(--slate-300);
    border-radius: 5px;
    background: #fff;
    font-size: 0.9rem;
    font-family: inherit;
    line-height: 1.4;
}
.lam-detail-input.is-strong { font-weight: 600; }
.lam-detail-input.is-muted  { font-size: 0.85rem; color: var(--slate-600); }
.lam-domain-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--slate-100);
    font-size: 0.9rem;
}
.lam-domain-row a { color: var(--slate-800); text-decoration: none; }
.lam-domain-row a:hover { color: var(--thoxan-700); }
</style>

<script>
function lamAnbieterDetail() {
    const ANBIETER_ID = <?= json_encode($anbieter['id']) ?>;
    const PRIMAERKONTAKT = <?php
        $primaer = null;
        foreach (($anbieter['kontakte'] ?? []) as $k) {
            if (((int)($k['prioritaet'] ?? 99)) === 1 && !empty($k['email'])) { $primaer = $k; break; }
        }
        if (!$primaer) {
            foreach (($anbieter['kontakte'] ?? []) as $k) { if (!empty($k['email'])) { $primaer = $k; break; } }
        }
        echo json_encode($primaer ? [
            'id' => $primaer['id'],
            'name' => trim(($primaer['vorname'] ?? '') . ' ' . ($primaer['nachname'] ?? '')),
            'email' => $primaer['email'],
        ] : null);
    ?>;
    return {
        // Mail-Compose-Methode (Mixin liefert die Modal-Daten + Send-Logik)
        schreibeMailAnAnbieter(opts = {}) {
            if (!PRIMAERKONTAKT && !opts.empfaenger) {
                alert('Kein Kontakt mit E-Mail-Adresse beim Anbieter hinterlegt — bitte erst Kontakt mit E-Mail anlegen.');
                return;
            }
            const ziel = opts.empfaenger || PRIMAERKONTAKT.email;
            const name = opts.name || (PRIMAERKONTAKT ? PRIMAERKONTAKT.name : '');
            this.oeffneMailCompose({
                empfaenger: ziel,
                anbieterId: ANBIETER_ID,
                kontaktId: opts.kontaktId || (PRIMAERKONTAKT ? PRIMAERKONTAKT.id : ''),
                hinweis: 'An: ' + (name ? name + ' (' + ziel + ')' : ziel) + ' · Eintrag wird automatisch in der Korrespondenz registriert.',
            });
        },
        onMailComposeGesendet(data) {
            alert('✓ Mail gesendet und in der Korrespondenz registriert.');
            window.location.reload();
        },

        // Inline-Edit Notizen (direkter Zugriff, nicht via Drawer)
        notizen: <?= json_encode($anbieter['notizen'] ?? '') ?>,
        notizenSaving: false, notizenSaved: false,
        // Domain-Picker (Type-Ahead)
        domPicker: { offen: false, suche: '', treffer: [], rolle: 'betreiber' },

        // Helper für Domain-Status-Anzeige
        domainStatusLabel(s) {
            const m = { neu: 'Neu', in_arbeit: 'In Arbeit', geprueft: 'Geprüft', verifiziert: 'Geprüft', veraltet: 'Veraltet', verworfen: 'Gelöscht', geloescht: 'Gelöscht' };
            return m[s] || s;
        },
        domainStatusStyle(s) {
            const m = {
                neu: 'background:var(--amber-100);color:var(--amber-800);',
                in_arbeit: 'background:var(--thoxan-100);color:var(--thoxan-700);',
                geprueft: 'background:var(--emerald-100);color:var(--emerald-800);',
                verifiziert: 'background:var(--emerald-100);color:var(--emerald-800);',
                veraltet: 'background:#fff7ed;color:#9a3412;',
                verworfen: 'background:var(--rose-100);color:var(--rose-800);',
                geloescht: 'background:var(--rose-100);color:var(--rose-800);',
            };
            return m[s] || 'background:var(--slate-100);color:var(--slate-700);';
        },

        // Inline-Edit Header: Name + Firma
        name: <?= json_encode($anbieter['name']) ?>,
        nameEdit: false,
        firma: <?= json_encode($anbieter['firma'] ?? '') ?>,
        firmaEdit: false,
        async speichereName() {
            const wert = (this.name || '').trim();
            if (!wert) { alert('Name darf nicht leer sein.'); return; }
            try {
                const r = await fetch('/api/v1/lam/anbieter-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ANBIETER_ID, feld: 'name', wert })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.nameEdit = false;
            } catch (e) { alert('Name speichern fehlgeschlagen: ' + e.message); }
        },
        abortNameEdit() { this.nameEdit = false; this.name = <?= json_encode($anbieter['name']) ?>; },
        async speichereFirma() {
            const wert = (this.firma || '').trim();
            try {
                const r = await fetch('/api/v1/lam/anbieter-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ANBIETER_ID, feld: 'firma', wert })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                this.firmaEdit = false;
            } catch (e) { alert('Firma speichern fehlgeschlagen: ' + e.message); }
        },
        abortFirmaEdit() { this.firmaEdit = false; this.firma = <?= json_encode($anbieter['firma'] ?? '') ?>; },
        // Kontakte als reaktive Liste (inline editierbar, kein Drawer)
        kontakte: <?php
            // Status-Mapping: alte DB-Werte (verifiziert/verworfen) → neues UI-Vokabular
            $statusMap = ['verifiziert' => 'geprueft', 'verworfen' => 'geloescht'];
            $kontakte = array_map(function($k) use ($statusMap) {
                $s = $k['verifikation_status'] ?? 'neu';
                return [
                    'id' => $k['id'],
                    'vorname' => $k['vorname'] ?? '',
                    'nachname' => $k['nachname'] ?? '',
                    'email' => $k['email'] ?? '',
                    'telefon' => $k['telefon'] ?? '',
                    'rolle' => $k['rolle'] ?? '',
                    'prioritaet' => (int)($k['prioritaet'] ?? 99),
                    'verifikation_status' => $statusMap[$s] ?? $s,
                ];
            }, $anbieter['kontakte'] ?? []);
            echo json_encode($kontakte, JSON_UNESCAPED_UNICODE);
        ?>,
        neuerKontakt: { vorname: '', nachname: '', email: '', telefon: '', rolle: '' },

        // Schnellwechsel aus Aktionen-Bar
        async setzeBeziehung(b) {
            await fetch('/api/v1/lam/anbieter-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: ANBIETER_ID, feld: 'beziehungsstatus', wert: b })
            });
            window.location.reload();
        },
        async setzeRolle(r) {
            await fetch('/api/v1/lam/anbieter-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: ANBIETER_ID, feld: 'rolle', wert: r })
            });
            window.location.reload();
        },
        async speichereNotizen() {
            this.notizenSaving = true; this.notizenSaved = false;
            try {
                await fetch('/api/v1/lam/anbieter-inline', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ANBIETER_ID, feld: 'notizen', wert: this.notizen })
                });
                this.notizenSaved = true;
                setTimeout(() => { this.notizenSaved = false; }, 2500);
            } finally { this.notizenSaving = false; }
        },

        // ===== Domain-Picker =====
        oeffneDomainPicker(rolle) {
            this.domPicker = { offen: true, suche: '', treffer: [], rolle };
        },
        async domPickerFiltern() {
            if (this.domPicker.suche.length < 2) { this.domPicker.treffer = []; return; }
            const p = new URLSearchParams({ suche: this.domPicker.suche, limit: 30 });
            const r = await fetch('/api/v1/lam/linkquellen?' + p, { credentials: 'same-origin' });
            const j = await r.json();
            this.domPicker.treffer = j.success ? (j.data.rows || []) : [];
        },
        async domainZuordnen(domainId, url) {
            try {
                const r = await fetch('/api/v1/lam/domain-anbieter-zuordnen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: domainId, anbieter_id: ANBIETER_ID, rolle: this.domPicker.rolle }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) { alert('Zuordnen fehlgeschlagen: ' + e.message); }
        },
        async entferneDomain(domainId, rolle, url) {
            if (!confirm(`Verknüpfung zu „${url}" als ${rolle === 'betreiber' ? 'Betreiber' : 'Vermittler'} entfernen?\n\nDie Domain bleibt im Linkquellen-Pool — nur die Anbieter-Zuordnung wird gelöscht.`)) return;
            try {
                // Junction-ID nicht direkt verfügbar — Endpoint kann das auflösen via (domain_id + anbieter_id)
                const r = await fetch('/api/v1/lam/anbieter-domain-entfernen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain_id: domainId, anbieter_id: ANBIETER_ID, rolle }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                window.location.reload();
            } catch (e) { alert('Entfernen fehlgeschlagen: ' + e.message); }
        },

        async loescheAnbieter() {
            if (!confirm('Anbieter wirklich loeschen? (Soft-Delete)')) return;
            await fetch('/api/v1/lam/anbieter-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [ANBIETER_ID], aktion: 'loeschen' })
            });
            window.location.href = '/lam/anbieter';
        },

        // Kontakt-Inline-Methoden
        async speichereKontaktFeld(k, feld) {
            try {
                const r = await fetch('/api/v1/lam/kontakt-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: k.id, anbieter_id: ANBIETER_ID,
                        vorname: k.vorname, nachname: k.nachname, email: k.email, telefon: k.telefon, rolle: k.rolle,
                    })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
            } catch (e) { alert(feld + ' speichern fehlgeschlagen: ' + e.message); }
        },
        async speichereNeuenKontakt() {
            if (!this.neuerKontakt.nachname.trim()) { alert('Nachname ist erforderlich.'); return; }
            try {
                const r = await fetch('/api/v1/lam/kontakt-save', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ anbieter_id: ANBIETER_ID, ...this.neuerKontakt })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.kontakte.push({
                    id: j.data.id,
                    ...this.neuerKontakt,
                    prioritaet: 99,
                    verifikation_status: 'neu',
                });
                this.neuerKontakt = { vorname: '', nachname: '', email: '', telefon: '', rolle: '' };
            } catch (e) { alert('Kontakt anlegen fehlgeschlagen: ' + e.message); }
        },
        async setzePrimaer(kontaktId, aktuellePrio) {
            if (aktuellePrio == 1) return;
            try {
                const r = await fetch('/api/v1/lam/kontakt-aktion', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: kontaktId, aktion: 'primaer_setzen' })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.kontakte.forEach(k => { k.prioritaet = (k.id === kontaktId) ? 1 : 99; });
            } catch (e) { alert('Primär setzen fehlgeschlagen: ' + e.message); }
        },
        async loescheKontakt(kontaktId, name) {
            if (!confirm('Kontakt „' + name + '" wirklich löschen?')) return;
            try {
                const r = await fetch('/api/v1/lam/kontakt-aktion', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: kontaktId, aktion: 'loeschen' })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                this.kontakte = this.kontakte.filter(k => k.id !== kontaktId);
            } catch (e) { alert('Löschen fehlgeschlagen: ' + e.message); }
        },
        async kontaktVerifikationSetzen(k) {
            try {
                const r = await fetch('/api/v1/lam/kontakt-verifikation', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: k.id, status: k.verifikation_status })
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
            } catch (e) { alert('Status speichern fehlgeschlagen: ' + e.message); }
        },
    };
}
</script>
