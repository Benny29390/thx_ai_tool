<?php
/**
 * Linkoption-Eintrag-Detail — /lam/linkoptionen/{id}
 */
use Core\Database;
use Services\LamService;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$e = $svc->getLinkoptionDetail($linkoptionId ?? '');

if (!$e) {
    echo '<div class="thx-page-header"><h1 class="thx-page-title">Linkoption-Eintrag nicht gefunden</h1></div>';
    echo '<a href="/lam/linkoptionen" style="color:var(--thoxan-700);">‹ Zurück zur Liste</a>';
    return;
}

$activeModul = 'linkoptionen';

$statusListe = ['in_planung','vorgeschlagen','in_akquise','bestaetigt','abgelehnt','ohne_antwort','kunde_freigegeben','kunde_abgelehnt','abgeschlossen'];
$statusStyle = function($status) {
    $m = [
        'vorgeschlagen' => 'background:var(--slate-100);color:var(--slate-700);',
        'in_akquise' => 'background:var(--amber-100);color:var(--amber-800);',
        'bestaetigt' => 'background:var(--emerald-100);color:var(--emerald-800);',
        'abgelehnt' => 'background:var(--rose-100);color:var(--rose-800);',
        'ohne_antwort' => 'background:var(--slate-200);color:var(--slate-700);',
        'kunde_freigegeben' => 'background:var(--emerald-200);color:var(--emerald-800);',
        'kunde_abgelehnt' => 'background:var(--rose-200);color:var(--rose-800);',
        'abgeschlossen' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
    ];
    return $m[$status] ?? 'background:var(--slate-100);color:var(--slate-700);';
};
$euro = function($n) {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, 0, ',', '.') . ' €';
};
?>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="/assets/js/lam-mail-compose.js"></script>

<div x-data="Object.assign(lamMailCompose(), lamLinkoptionDetail())">

    <!-- Page-Header -->
    <div class="thx-page-header">
        <div>
            <a href="/lam/linkoptionen" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zu Linkoptionen</a>
            <h1 class="thx-page-title" style="margin-top:4px;">
                <span style="color:var(--slate-500);font-weight:400;font-size:var(--d-fs-lg);"><?= htmlspecialchars($e['customer_kuerzel'] ?: '—') ?> ·</span>
                <a href="/lam/linkquellen/<?= htmlspecialchars($e['domain_id']) ?>"
                   style="color:var(--slate-800);text-decoration:none;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($e['domain_url']) ?></a>
            </h1>
            <div style="margin-top:6px;font-size:var(--d-fs-sm);color:var(--slate-500);">
                aus Liste: <strong><?= htmlspecialchars($e['liste_name']) ?></strong>
            </div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
                <span class="lam-badge" style="<?= $statusStyle($e['status']) ?>"><?= htmlspecialchars($e['status']) ?></span>
                <?php if (!empty($e['massnahme_id'])): ?>
                    <a href="/lam/massnahmen/<?= htmlspecialchars($e['massnahme_id']) ?>"
                       class="lam-badge" style="background:var(--thoxan-100);color:var(--thoxan-700);text-decoration:none;">
                        → Maßnahme (<?= htmlspecialchars($e['massnahme_status'] ?: 'unbekannt') ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="thx-page-actions">
            <button class="lam-btn lam-btn-secondary" @click="drawer.offen = true">Bearbeiten</button>
            <?php if (!empty($e['anbieter_id'])): ?>
                <button class="lam-btn lam-btn-secondary" @click="schreibeMail()" title="Neue Mail an Anbieter zur Akquise — wird automatisch in der Korrespondenz registriert">📧 Mail schreiben</button>
            <?php endif; ?>
            <?php if ($e['status'] === 'kunde_freigegeben' && empty($e['massnahme_id'])): ?>
                <button class="lam-btn lam-btn-primary" @click="zuMassnahme()">→ Als Maßnahme buchen</button>
            <?php endif; ?>
            <button class="lam-btn lam-btn-danger" @click="loeschen()">Löschen</button>
        </div>
    </div>

    <?php include __DIR__ . '/_tabs.php'; ?>

    <!-- Asana-Banner: vorhandenen Task verknüpfen oder neuen anlegen -->
    <?php
        $asanaTaskGid = $e['asana_task_gid'] ?? null;
        $asanaCache = !empty($e['asana_task_cache']) ? json_decode($e['asana_task_cache'], true) : null;
        $asanaKonfig = !empty(\Core\Settings::get('asana_pat'));
    ?>
    <?php if ($asanaKonfig): ?>
    <section class="lam-card" style="margin-bottom:16px;border-left:4px solid #f06a6a;" x-data="asanaLinkoptionBanner()">
        <?php if ($asanaTaskGid): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <a href="<?= htmlspecialchars($asanaCache['permalink_url'] ?? '#') ?>" target="_blank" rel="noopener" style="color:#f06a6a;font-weight:600;">
                        📋 <?= htmlspecialchars($asanaCache['name'] ?? $asanaTaskGid) ?> ↗
                    </a>
                    <div class="muted" style="font-size:var(--d-fs-xs);margin-top:2px;">
                        <?php if (!empty($asanaCache['completed'])): ?><span style="color:var(--emerald-700);">✓ erledigt</span><?php else: ?>offen<?php endif; ?>
                        <?php if (!empty($asanaCache['due_on'])): ?> · fällig <?= htmlspecialchars($asanaCache['due_on']) ?><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:6px;">
                    <button class="lam-btn lam-btn-sm" @click="aktualisieren()" :disabled="laeuft">↻ aktualisieren</button>
                    <button class="lam-btn lam-btn-sm lam-btn-danger" @click="entkoppeln()">entkoppeln</button>
                </div>
            </div>
        <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <strong>📋 Asana-Task verknüpfen</strong>
                    <p class="muted" style="margin:4px 0 0;font-size:var(--d-fs-xs);">URL aus Asana kopieren und eintragen. Bei Konvertierung in Maßnahme wird die Verknüpfung übernommen.</p>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="text" placeholder="Asana-URL oder GID" x-model="manuelleEingabe"
                           style="padding:6px 10px;border:1px solid var(--slate-300);border-radius:4px;font-size:var(--d-fs-sm);width:240px;">
                    <button class="lam-btn lam-btn-sm" @click="verknuepfen()" :disabled="!manuelleEingabe || laeuft">verknüpfen</button>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Status-Pipeline -->
    <section class="lam-card" style="margin-bottom:20px;">
        <h3 style="margin:0 0 10px 0;">Statuswechsel</h3>
        <div class="lam-chip-row">
            <?php foreach ($statusListe as $s): ?>
                <button @click="setzeStatus('<?= $s ?>')"
                        class="lam-chip <?= $s === $e['status'] ? 'is-active' : '' ?>"
                        style="<?= $s === $e['status'] ? '' : 'cursor:pointer;' ?>"><?= $s ?></button>
            <?php endforeach; ?>
        </div>
        <p class="muted" style="margin-top:10px;font-size:var(--d-fs-xs);">
            Pipeline: vorgeschlagen → in_akquise → bestaetigt/abgelehnt/ohne_antwort → kunde_freigegeben/kunde_abgelehnt → abgeschlossen
        </p>
    </section>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

        <!-- Linke Spalte -->
        <section style="display:flex;flex-direction:column;gap:20px;">

            <!-- Eintrag-Daten -->
            <div class="lam-card">
                <h3>Eintrag</h3>
                <table class="lam-table" style="font-size:var(--d-fs-sm);">
                    <tbody>
                        <tr><td class="muted" style="width:35%;">Artikelthema</td><td><?= htmlspecialchars($e['artikelthema'] ?: '—') ?></td></tr>
                        <tr><td class="muted">Vorgeschlagener Linktext</td><td><?= htmlspecialchars($e['vorgeschlagener_linktext'] ?: '—') ?></td></tr>
                        <tr>
                            <td class="muted">Ziel-URL</td>
                            <td>
                                <?php if (!empty($e['ziel_url'])): ?>
                                    <a href="<?= htmlspecialchars($e['ziel_url']) ?>" target="_blank" rel="noopener" style="color:var(--thoxan-700);"><?= htmlspecialchars($e['ziel_url']) ?> ↗</a>
                                <?php else: ?>
                                    <span class="empty">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><td class="muted">Preis Kunde</td><td><?= $euro($e['preis_kunde']) ?></td></tr>
                        <tr><td class="muted">Kontakt am</td><td><?= htmlspecialchars($e['kontakt_am'] ?: '—') ?></td></tr>
                        <tr>
                            <td class="muted">Letzte Rückmeldung</td>
                            <td>
                                <?php if (!empty($e['letzte_rueckmeldung_am'])): ?>
                                    <?= htmlspecialchars($e['letzte_rueckmeldung_am']) ?>
                                    <?php if (!empty($e['letzte_rueckmeldung_typ'])): ?>
                                        <span class="muted">(<?= htmlspecialchars($e['letzte_rueckmeldung_typ']) ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="empty">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="muted">Nächste Aktion</td>
                            <td>
                                <?php if (!empty($e['naechste_aktion_am'])): ?>
                                    <?= htmlspecialchars($e['naechste_aktion_am']) ?>
                                    <?php if (!empty($e['naechste_aktion_notiz'])): ?>
                                        <span class="muted">: <?= htmlspecialchars($e['naechste_aktion_notiz']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="empty">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if (!empty($e['notiz'])): ?>
                    <div style="margin-top:12px;padding:10px;background:var(--slate-50);border-radius:4px;font-size:var(--d-fs-sm);">
                        <strong style="font-size:var(--d-fs-xs);color:var(--slate-500);text-transform:uppercase;letter-spacing:0.05em;">Notiz</strong>
                        <p style="white-space:pre-wrap;margin:4px 0 0 0;color:var(--slate-700);"><?= htmlspecialchars($e['notiz']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Korrespondenz zu diesem Eintrag -->
            <?php if (!empty($e['kommunikation'])): ?>
            <div class="lam-card">
                <h3>Korrespondenz (<?= count($e['kommunikation']) ?>)</h3>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($e['kommunikation'] as $k): ?>
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
                                <div style="white-space:pre-wrap;color:var(--slate-700);font-size:var(--d-fs-sm);max-height:160px;overflow:auto;"><?= htmlspecialchars($k['inhalt']) ?></div>
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
            <?php else: ?>
            <div class="lam-card">
                <h3>Korrespondenz</h3>
                <p class="muted">Noch keine Notizen zu diesem Eintrag.</p>
            </div>
            <?php endif; ?>

            <!-- Andere Einträge in derselben Liste -->
            <?php if (!empty($e['liste_geschwister'])): ?>
            <div class="lam-card">
                <h3>Andere Einträge in „<?= htmlspecialchars($e['liste_name']) ?>" (<?= count($e['liste_geschwister']) ?>)</h3>
                <table class="lam-table" style="font-size:var(--d-fs-sm);">
                    <thead><tr><th>Domain</th><th>Status</th><th class="right">Preis</th></tr></thead>
                    <tbody>
                        <?php foreach ($e['liste_geschwister'] as $g): ?>
                            <tr>
                                <td>
                                    <a href="/lam/linkoptionen/<?= htmlspecialchars($g['id']) ?>" style="color:var(--thoxan-700);text-decoration:none;"><?= htmlspecialchars($g['domain_url']) ?></a>
                                </td>
                                <td><span class="lam-badge" style="<?= $statusStyle($g['status']) ?>"><?= htmlspecialchars($g['status']) ?></span></td>
                                <td class="right"><?= $euro($g['preis_kunde']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </section>

        <!-- Rechte Sidebar -->
        <aside style="display:flex;flex-direction:column;gap:20px;">

            <!-- Liste-Info -->
            <div class="lam-card">
                <h3>Vorschlagsliste</h3>
                <p style="font-weight:500;font-size:var(--d-fs-base);margin:0 0 6px 0;"><?= htmlspecialchars($e['liste_name']) ?></p>
                <div style="font-size:var(--d-fs-sm);color:var(--slate-600);">
                    Status: <strong><?= htmlspecialchars($e['liste_status']) ?></strong>
                </div>
                <?php if (!empty($e['liste_zielzahl'])): ?>
                    <div style="font-size:var(--d-fs-sm);color:var(--slate-600);margin-top:2px;">Zielzahl: <?= (int)$e['liste_zielzahl'] ?></div>
                <?php endif; ?>
                <?php if (!empty($e['liste_notiz'])): ?>
                    <p style="margin-top:10px;font-size:var(--d-fs-xs);color:var(--slate-600);white-space:pre-wrap;"><?= htmlspecialchars($e['liste_notiz']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Domain-Info -->
            <div class="lam-card">
                <h3>Domain</h3>
                <a href="/lam/linkquellen/<?= htmlspecialchars($e['domain_id']) ?>" style="font-weight:500;color:var(--thoxan-700);font-size:var(--d-fs-base);"><?= htmlspecialchars($e['domain_url']) ?></a>
                <div style="margin-top:6px;font-size:var(--d-fs-sm);">
                    Verifikation: <span class="lam-badge" style="<?= $statusStyle($e['domain_status']) ?>"><?= htmlspecialchars($e['domain_status']) ?></span>
                </div>
                <?php if (!empty($e['anbieter_name'])): ?>
                    <div style="margin-top:8px;font-size:var(--d-fs-sm);">
                        Anbieter:
                        <a href="/lam/anbieter/<?= htmlspecialchars($e['anbieter_id']) ?>" style="color:var(--thoxan-700);"><?= htmlspecialchars($e['anbieter_name']) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Maßnahme-Verknüpfung -->
            <?php if (!empty($e['massnahme_id'])): ?>
            <div class="lam-card">
                <h3>Verknüpfte Maßnahme</h3>
                <a href="/lam/massnahmen/<?= htmlspecialchars($e['massnahme_id']) ?>"
                   class="lam-btn lam-btn-secondary" style="display:inline-block;text-align:center;text-decoration:none;">
                    → Maßnahme öffnen
                </a>
                <p class="muted" style="margin-top:6px;font-size:var(--d-fs-xs);">Status: <?= htmlspecialchars($e['massnahme_status'] ?: 'unbekannt') ?></p>
            </div>
            <?php endif; ?>

        </aside>
    </div>

    <!-- Bearbeiten-Drawer -->
    <div class="thx-drawer-backdrop" x-show="drawer.offen" @click.self="drawer.offen = false" x-cloak>
        <div class="thx-drawer">
            <div class="thx-drawer-header">
                <h2 class="thx-drawer-title">Eintrag bearbeiten</h2>
                <button class="thx-modal-close" @click="drawer.offen = false">×</button>
            </div>
            <div class="thx-drawer-body">
                <div class="thx-form-field">
                    <label>Status</label>
                    <select x-model="drawer.status">
                        <?php foreach ($statusListe as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="thx-form-field">
                    <label>Artikelthema</label>
                    <input type="text" x-model="drawer.artikelthema">
                </div>
                <div class="thx-form-field">
                    <label>Vorgeschlagener Linktext</label>
                    <input type="text" x-model="drawer.vorgeschlagener_linktext">
                </div>
                <div class="thx-form-field">
                    <label>Ziel-URL</label>
                    <input type="url" x-model="drawer.ziel_url">
                </div>
                <div class="thx-form-field">
                    <label>Preis Kunde (€)</label>
                    <input type="number" step="0.01" x-model="drawer.preis_kunde">
                </div>
                <div class="thx-form-row">
                    <div class="thx-form-field">
                        <label>Kontakt am</label>
                        <input type="date" x-model="drawer.kontakt_am">
                    </div>
                    <div class="thx-form-field">
                        <label>Nächste Aktion</label>
                        <input type="date" x-model="drawer.naechste_aktion_am">
                    </div>
                </div>
                <div class="thx-form-field">
                    <label>Notiz</label>
                    <textarea x-model="drawer.notiz" rows="5"></textarea>
                </div>
                <div class="thx-error" x-show="drawer.flashFehler" x-text="drawer.flashFehler"></div>
            </div>
            <div class="thx-drawer-footer">
                <button class="lam-btn lam-btn-secondary" @click="drawer.offen = false">Abbrechen</button>
                <button class="lam-btn lam-btn-primary" @click="speichereDrawer()" :disabled="drawer.laeuft">
                    <span x-show="!drawer.laeuft">Speichern</span><span x-show="drawer.laeuft">…</span>
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/_mail_compose.php'; ?>

</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
window.LINKOPTION_ID = <?= json_encode($e['id'] ?? '') ?>;
function asanaLinkoptionBanner() {
    return {
        laeuft: false,
        manuelleEingabe: '',
        async verknuepfen() {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-verknuepfen', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: window.LINKOPTION_ID, task_gid_oder_url: this.manuelleEingabe }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                location.reload();
            } catch (e) { alert('Verknüpfen fehlgeschlagen: ' + e.message); }
            finally { this.laeuft = false; }
        },
        async aktualisieren() {
            this.laeuft = true;
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-aktualisieren', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: window.LINKOPTION_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                location.reload();
            } catch (e) { alert('Aktualisieren fehlgeschlagen: ' + e.message); }
            finally { this.laeuft = false; }
        },
        async entkoppeln() {
            if (!confirm('Asana-Task-Verknüpfung entfernen?')) return;
            try {
                const r = await fetch('/api/v1/lam/linkoption-asana-entkoppeln', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ linkoption_id: window.LINKOPTION_ID }),
                });
                const j = await r.json();
                if (!j.success) throw new Error(j.error || j.message);
                location.reload();
            } catch (e) { alert('Entkoppeln fehlgeschlagen: ' + e.message); }
        },
    };
}
function lamLinkoptionDetail() {
    const ID = <?= json_encode($e['id']) ?>;
    const LO_ANBIETER_ID = <?= json_encode($e['anbieter_id'] ?? null) ?>;
    const LO_BETREFF = <?= json_encode('Linkoption-Anfrage: ' . ($e['customer_kuerzel'] ?? '?') . ' · ' . ($e['domain_url'] ?? '')) ?>;
    return {
        async schreibeMail() {
            if (!LO_ANBIETER_ID) return;
            let empfaenger = '', kontaktName = '';
            try {
                const r = await fetch('/api/v1/lam/anbieter-detail?id=' + encodeURIComponent(LO_ANBIETER_ID), { credentials: 'same-origin' });
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
                betreff: LO_BETREFF,
                anbieterId: LO_ANBIETER_ID,
                vorschlagslisteEintragId: ID,
                hinweis: 'An: ' + (kontaktName ? kontaktName + ' (' + empfaenger + ')' : empfaenger || 'Bitte Empfänger eintragen') + ' · Linkoption + Anbieter werden automatisch verknüpft.',
            });
        },
        onMailComposeGesendet() { alert('✓ Mail gesendet und in der Korrespondenz registriert.'); window.location.reload(); },

        drawer: {
            offen: false,
            status: <?= json_encode($e['status']) ?>,
            artikelthema: <?= json_encode($e['artikelthema'] ?? '') ?>,
            vorgeschlagener_linktext: <?= json_encode($e['vorgeschlagener_linktext'] ?? '') ?>,
            ziel_url: <?= json_encode($e['ziel_url'] ?? '') ?>,
            preis_kunde: <?= json_encode($e['preis_kunde'] ?? '') ?>,
            kontakt_am: <?= json_encode($e['kontakt_am'] ?? '') ?>,
            naechste_aktion_am: <?= json_encode($e['naechste_aktion_am'] ?? '') ?>,
            notiz: <?= json_encode($e['notiz'] ?? '') ?>,
            laeuft: false, flashFehler: null
        },
        async speichereDrawer() {
            if (this.drawer.laeuft) return;
            this.drawer.laeuft = true;
            try {
                const felder = ['status','artikelthema','vorgeschlagener_linktext','ziel_url','preis_kunde','kontakt_am','naechste_aktion_am','notiz'];
                for (const feld of felder) {
                    await fetch('/api/v1/lam/linkoption-inline', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: ID, feld, wert: this.drawer[feld] })
                    });
                }
                window.location.reload();
            } finally { this.drawer.laeuft = false; }
        },
        async setzeStatus(s) {
            const res = await fetch('/api/v1/lam/linkoption-inline', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: ID, feld: 'status', wert: s })
            });
            if ((await res.json()).success) window.location.reload();
        },
        async zuMassnahme() {
            if (!confirm('Diesen Eintrag als Maßnahme buchen?\n\nEs wird eine neue Maßnahme im Status "geplant" angelegt und verknüpft.')) return;
            try {
                const r = await fetch('/api/v1/lam/linkoption-zu-massnahme', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ID }),
                });
                const json = await r.json();
                if (json.success && json.data?.massnahme_id) {
                    window.location.href = '/lam/massnahmen/' + encodeURIComponent(json.data.massnahme_id);
                } else {
                    alert(json.message || 'Fehler beim Erstellen der Maßnahme');
                }
            } catch (e) {
                alert('Verbindungsfehler');
            }
        },
        async loeschen() {
            if (!confirm('Eintrag wirklich loeschen?')) return;
            await fetch('/api/v1/lam/linkoption-bulk', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [ID], aktion: 'loeschen' })
            });
            window.location.href = '/lam/linkoptionen';
        }
    };
}
</script>
