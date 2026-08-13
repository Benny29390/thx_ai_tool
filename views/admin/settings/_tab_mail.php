<?php
/**
 * Mail-Tool-Tab — globale Einstellungen, Vorlagen, Regeln, Diagnose.
 * Konto-Verwaltung (IMAP/SMTP-Zugangsdaten) liegt im „E-Mail"-Tab.
 */
$autoVersandGlobal = (string)\Core\Settings::get('mail_auto_versand_global_aktiv', '0');
$pullIntervall = (int)\Core\Settings::get('mail_pull_intervall_minuten', 10);
$anhangMaxMb = (int)\Core\Settings::get('mail_anhang_max_mb', 25);
$stopWoerter = (string)\Core\Settings::get('mail_stop_woerter', 'Anwalt,Klage,Datenschutz,GDPR,Abmahnung,Reklamation,Beschwerde,Inkasso');

$db = \Core\Database::getInstance();
$vorlagen = $db->query("SELECT * FROM mail_vorlagen ORDER BY kategorie, name");
$regeln = $db->query("SELECT * FROM mail_regeln ORDER BY prioritaet ASC, id ASC");

// Konten fuer die Lern-Karte (Stil + Regeln haengen immer an EINEM Postfach)
$lernKonten = $db->query("SELECT id, name, email_adresse FROM mail_konten ORDER BY ist_standard DESC, name");
?>

<!-- ============ Lernsystem: Stil + gelernte Regeln ============ -->
<div class="settings-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;">Stil &amp; gelernte Regeln</h2>
        <div style="display:flex;gap:8px;align-items:center;">
            <select id="lern-konto" onchange="lernLaden()" class="thx-select" style="min-width:220px;">
                <?php foreach ($lernKonten as $k): ?>
                    <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['name']) ?> (<?= htmlspecialchars($k['email_adresse']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="thx-btn thx-btn-secondary" onclick="lernStilStarten()">Stil neu lernen</button>
            <button type="button" class="thx-btn thx-btn-secondary" onclick="lernKorrekturen()">Aus Korrekturen lernen</button>
        </div>
    </div>
    <p class="settings-card-sub">
        Die KI lernt aus <strong>Deinen eigenen Mails</strong>, wie Du schreibst, und aus
        <strong>Deinen Korrekturen</strong> an ihren Entwürfen, was sie falsch macht.
        Jede abgeleitete Regel ist zuerst nur ein <strong>Vorschlag</strong> — sie wirkt erst,
        wenn Du sie freigibst. Welche Ordner gelernt werden dürfen, stellst Du im Tab
        <a href="/admin/settings?tab=smtp" style="color:var(--thoxan-700);">„E-Mail"</a> unter „Ordner" ein.
    </p>

    <div id="lern-status" class="muted" style="font-size:var(--d-fs-sm);margin-bottom:12px;">wird geladen …</div>
    <div id="lern-inhalt"></div>
</div>

<div class="settings-card" style="background:#f8fafc;border-left:4px solid var(--thoxan-500);">
    <p style="margin:0;font-size:var(--d-fs-sm);">
        💡 <strong>Mail-Konten (IMAP/SMTP-Zugangsdaten)</strong> verwaltest Du im Tab
        <a href="/admin/settings?tab=smtp" style="color:var(--thoxan-700);font-weight:600;">„E-Mail"</a>.
        Hier nur globale Einstellungen für das Mail-Tool unter <a href="/mail" style="color:var(--thoxan-700);">/mail</a>.
    </p>
</div>

<!-- ====== Hybrid: welches Modell entwirft? ====== -->
<?php
$entwurfModell = (string)\Core\Settings::get('mail_entwurf_modell', '');
$lokaleModelle = $db->query("SELECT model_id, display_name FROM ai_models WHERE provider='local' AND is_active=1 ORDER BY sort_order");
$anthropicDa   = \Core\Settings::get('anthropic_api_key') ? true : false;
?>
<div class="settings-card">
    <h2>Antwort-Erstentwurf: Modell</h2>
    <p class="settings-card-sub">
        Welches Modell schreibt den ersten Antwortentwurf? <strong>Lokal</strong> bedeutet: Der
        Mail-Inhalt verlässt den Server nicht — datensouverän, kostenlos, etwas langsamer und
        gröber. Für den Feinschliff einzelner Mails gibt es im Antwort-Editor den Knopf
        <strong>„✨ mit Claude"</strong>, der bewusst die Cloud nutzt. Der Wissens-Zugriff läuft
        ohnehin komplett lokal.
    </p>
    <form id="form-mail-modell" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Erstentwurf-Modell gespeichert' });">
        <div class="settings-field" style="max-width:460px;">
            <label for="mail_entwurf_modell">Modell für Klassifikation + Erstentwurf</label>
            <select id="mail_entwurf_modell" name="mail_entwurf_modell">
                <?php foreach ($lokaleModelle as $m): $val = 'local:'.$m['model_id']; ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $entwurfModell === $val ? 'selected' : '' ?>>
                        Lokal: <?= htmlspecialchars($m['display_name']) ?> (datensouverän)
                    </option>
                <?php endforeach; ?>
                <?php if ($anthropicDa): ?>
                    <option value="anthropic" <?= $entwurfModell === 'anthropic' ? 'selected' : '' ?>>
                        Cloud: Claude Haiku (schneller, Mail geht in die Cloud)
                    </option>
                <?php endif; ?>
            </select>
            <span class="field-hint">
                Empfehlung aus dem Vergleich: <strong>qwen2.5:32b</strong> trifft Deinen Stil am besten,
                <strong>gpt-oss:20b</strong> ist schneller. Leer = lokal bevorzugt.
            </span>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<!-- ====== Globale Schalter ====== -->
<div class="settings-card">
    <h2>Automatischer Versand</h2>
    <p class="settings-card-sub">
        Master-Schalter für alle Konten. Solange auf „nein", wird KEINE Mail automatisch verschickt.
    </p>
    <form id="form-mail-auto" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Auto-Versand-Einstellung gespeichert' });">
        <div class="settings-field" style="max-width:380px;">
            <label for="mail_auto_versand_global_aktiv">Auto-Versand global aktiv?</label>
            <select id="mail_auto_versand_global_aktiv" name="mail_auto_versand_global_aktiv">
                <option value="0" <?= $autoVersandGlobal === '0' ? 'selected' : '' ?>>nein — Mensch immer dazwischen (Empfehlung)</option>
                <option value="1" <?= $autoVersandGlobal === '1' ? 'selected' : '' ?>>ja — auto-Versand nach Konfidenz-Schwelle pro Konto</option>
            </select>
            <span class="field-hint">
                Bei „ja" greift zusätzlich: Konto-Schalter aktiv + Konfidenz-Schwelle erreicht + keine Stop-Wörter
                + keine Mailingliste + Rate-Limit 50/h.
            </span>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Polling und Anhänge</h2>
    <form id="form-mail-polling" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Polling-Einstellung gespeichert' });">
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="mail_pull_intervall_minuten">IMAP-Pull-Intervall (Minuten)</label>
                <input type="number" id="mail_pull_intervall_minuten" name="mail_pull_intervall_minuten"
                       value="<?= $pullIntervall ?>" min="1" max="120">
                <span class="field-hint">Cron läuft alle 5 Min als Tick. Das Skript prüft pro Konto, ob das Intervall abgelaufen ist.</span>
            </div>
            <div class="settings-field">
                <label for="mail_anhang_max_mb">Max. Anhang-Größe (MB)</label>
                <input type="number" id="mail_anhang_max_mb" name="mail_anhang_max_mb"
                       value="<?= $anhangMaxMb ?>" min="1" max="200">
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<div class="settings-card">
    <h2>Stop-Wörter</h2>
    <p class="settings-card-sub">Mails mit diesen Begriffen werden NIE automatisch beantwortet. Komma-getrennt.</p>
    <form id="form-mail-stopwoerter" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Stop-Wörter gespeichert' });">
        <div class="settings-field">
            <label for="mail_stop_woerter">Stop-Wörter</label>
            <textarea id="mail_stop_woerter" name="mail_stop_woerter" rows="3"><?= htmlspecialchars($stopWoerter) ?></textarea>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
        </div>
    </form>
</div>

<!-- ====== Antwort-Vorlagen ====== -->
<div class="settings-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h2 style="margin:0;">Antwort-Vorlagen</h2>
        <button type="button" class="thx-btn thx-btn-primary" onclick="mailVorlageOeffnenNeu()">+ Neue Vorlage</button>
    </div>
    <p class="settings-card-sub">
        Vorgefertigte Antworten mit Platzhaltern (<code>{{vorname}}</code>, <code>{{firma}}</code>).
        Werden im Mail-Tool als Vorschlag angeboten — die KI füllt die Platzhalter passend zum Kontext aus.
    </p>
    <table class="lam-table" style="font-size:var(--d-fs-sm);margin-top:10px;" id="mail-vorlagen-tabelle">
        <thead><tr><th>Name</th><th>Kategorie</th><th>Betreff</th><th>Aktiv</th><th class="right">Aktion</th></tr></thead>
        <tbody>
            <?php if (empty($vorlagen)): ?>
                <tr><td colspan="5" class="muted" style="padding:12px;">Noch keine Vorlagen. Klick auf „+ Neue Vorlage".</td></tr>
            <?php else: foreach ($vorlagen as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                    <td><?= htmlspecialchars($v['kategorie'] ?: '—') ?></td>
                    <td class="muted" style="font-size:var(--d-fs-xs);"><?= htmlspecialchars(mb_substr($v['betreff_template'] ?: '—', 0, 60)) ?></td>
                    <td><?= $v['aktiv'] ? '<span class="key-status ja">ja</span>' : '<span class="key-status nein">aus</span>' ?></td>
                    <td class="right">
                        <button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);"
                                onclick='mailVorlageBearbeiten(<?= json_encode($v, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>bearbeiten</button>
                        <button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);color:var(--rose-700);"
                                onclick="mailVorlageLoeschen(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['name'])) ?>')">löschen</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Vorlage-Editor-Modal -->
<div id="mail-vorlage-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;max-width:720px;width:100%;max-height:92vh;overflow-y:auto;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 id="mail-vorlage-modal-titel" style="margin:0;">Neue Vorlage</h2>
            <button type="button" onclick="mailVorlageSchliessen()" class="thx-icon-btn" title="Schließen"><span class="material-symbols-rounded">close</span></button>
        </div>
        <form id="mail-vorlage-form" onsubmit="event.preventDefault(); mailVorlageSpeichern();">
            <input type="hidden" name="id" value="">
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="z.B. Pressekit-Versand">
                </div>
                <div class="settings-field">
                    <label>Kategorie</label>
                    <select name="kategorie">
                        <option value="">— keine —</option>
                        <option value="anbieter_antwort">anbieter_antwort</option>
                        <option value="standardfrage">standardfrage</option>
                        <option value="kundenanfrage">kundenanfrage</option>
                        <option value="presseanfrage">presseanfrage</option>
                        <option value="spam">spam (Ignorieren)</option>
                        <option value="info">info</option>
                        <option value="sonstiges">sonstiges</option>
                    </select>
                </div>
            </div>
            <div class="settings-field">
                <label>Betreff-Template</label>
                <input type="text" name="betreff_template" placeholder="z.B. Re: {{original_betreff}}">
            </div>
            <div class="settings-field">
                <label>Body-Template * (Platzhalter wie {{vorname}}, {{firma}})</label>
                <textarea name="body_template" rows="10" required placeholder="Hallo {{vorname}},&#10;&#10;vielen Dank für Deine Nachricht ..."></textarea>
            </div>
            <div class="settings-field">
                <label><input type="checkbox" name="aktiv" value="1" checked> Vorlage aktiv</label>
            </div>
            <div id="mail-vorlage-fehler" style="color:var(--rose-700);font-size:var(--d-fs-sm);margin-top:10px;display:none;"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;border-top:1px solid var(--slate-200);padding-top:16px;">
                <button type="button" class="thx-btn thx-btn-secondary" onclick="mailVorlageSchliessen()">Abbrechen</button>
                <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== Regel-Engine ====== -->
<div class="settings-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h2 style="margin:0;">Klassifikations-Regeln</h2>
        <button type="button" class="thx-btn thx-btn-primary" onclick="mailRegelOeffnenNeu()">+ Neue Regel</button>
    </div>
    <p class="settings-card-sub">
        Manuelle Regeln werden VOR der KI geprüft (spart Tokens). Pattern sind PHP-Regex (case-insensitive).
        Niedrige Priorität-Zahl = wird zuerst getestet.
    </p>
    <table class="lam-table" style="font-size:var(--d-fs-sm);margin-top:10px;" id="mail-regeln-tabelle">
        <thead><tr><th>Prio</th><th>Name</th><th>Match</th><th>Kategorie</th><th>Folgeaktion</th><th>Aktiv</th><th class="right">Aktion</th></tr></thead>
        <tbody>
            <?php if (empty($regeln)): ?>
                <tr><td colspan="7" class="muted" style="padding:12px;">Noch keine Regeln.</td></tr>
            <?php else: foreach ($regeln as $r): ?>
                <tr>
                    <td><?= (int)$r['prioritaet'] ?></td>
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td class="muted" style="font-size:var(--d-fs-xs);max-width:280px;">
                        <?php
                            $teile = [];
                            if ($r['absender_pattern']) $teile[] = 'von: <code>' . htmlspecialchars($r['absender_pattern']) . '</code>';
                            if ($r['betreff_pattern']) $teile[] = 'betreff: <code>' . htmlspecialchars($r['betreff_pattern']) . '</code>';
                            if ($r['body_pattern']) $teile[] = 'body: <code>' . htmlspecialchars($r['body_pattern']) . '</code>';
                            echo implode(' &middot; ', $teile) ?: '<em>kein Pattern</em>';
                        ?>
                    </td>
                    <td><?= htmlspecialchars($r['kategorie'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($r['folgeaktion'] ?: '—') ?></td>
                    <td><?= $r['aktiv'] ? '<span class="key-status ja">ja</span>' : '<span class="key-status nein">aus</span>' ?></td>
                    <td class="right">
                        <button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);"
                                onclick='mailRegelBearbeiten(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>bearbeiten</button>
                        <button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);color:var(--rose-700);"
                                onclick="mailRegelLoeschen(<?= (int)$r['id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>')">löschen</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Regel-Editor-Modal -->
<div id="mail-regel-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;max-width:720px;width:100%;max-height:92vh;overflow-y:auto;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 id="mail-regel-modal-titel" style="margin:0;">Neue Regel</h2>
            <button type="button" onclick="mailRegelSchliessen()" class="thx-icon-btn" title="Schließen"><span class="material-symbols-rounded">close</span></button>
        </div>
        <form id="mail-regel-form" onsubmit="event.preventDefault(); mailRegelSpeichern();">
            <input type="hidden" name="id" value="">
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="z.B. Blogtec-Spam ignorieren">
                </div>
                <div class="settings-field">
                    <label>Priorität</label>
                    <input type="number" name="prioritaet" value="10" min="1" max="999">
                </div>
            </div>
            <div class="settings-field">
                <label>Absender-Pattern (Regex, optional)</label>
                <input type="text" name="absender_pattern" placeholder="z.B. @blogtec\.io">
            </div>
            <div class="settings-field">
                <label>Betreff-Pattern (Regex, optional)</label>
                <input type="text" name="betreff_pattern" placeholder="z.B. ^pressekit|presse-anfrage">
            </div>
            <div class="settings-field">
                <label>Body-Pattern (Regex, optional)</label>
                <input type="text" name="body_pattern" placeholder="z.B. high da backlinks">
            </div>
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>Kategorie wenn Match</label>
                    <select name="kategorie">
                        <option value="">— behalten —</option>
                        <option value="anbieter_antwort">anbieter_antwort</option>
                        <option value="standardfrage">standardfrage</option>
                        <option value="kundenanfrage">kundenanfrage</option>
                        <option value="presseanfrage">presseanfrage</option>
                        <option value="spam">spam</option>
                        <option value="info">info</option>
                        <option value="sonstiges">sonstiges</option>
                    </select>
                </div>
                <div class="settings-field">
                    <label>Folgeaktion</label>
                    <select name="folgeaktion">
                        <option value="">— keine —</option>
                        <option value="vorlage_vorschlagen">vorlage_vorschlagen</option>
                        <option value="auto_antworten">auto_antworten</option>
                        <option value="lam_korrespondenz_anlegen">lam_korrespondenz_anlegen</option>
                        <option value="lam_anbieter_zuordnen">lam_anbieter_zuordnen</option>
                        <option value="manuell_pruefen">manuell_pruefen</option>
                        <option value="ignorieren">ignorieren</option>
                    </select>
                </div>
            </div>
            <div class="settings-field">
                <label>Vorlage anhängen (optional)</label>
                <select name="vorlage_id">
                    <option value="">— keine —</option>
                    <?php foreach ($vorlagen as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="settings-field">
                <label><input type="checkbox" name="aktiv" value="1" checked> Regel aktiv</label>
            </div>
            <div id="mail-regel-fehler" style="color:var(--rose-700);font-size:var(--d-fs-sm);margin-top:10px;display:none;"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;border-top:1px solid var(--slate-200);padding-top:16px;">
                <button type="button" class="thx-btn thx-btn-secondary" onclick="mailRegelSchliessen()">Abbrechen</button>
                <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== Diagnose ====== -->
<div class="settings-card">
    <h2>Diagnose — IMAP-Pull-Verlauf</h2>
    <?php
        $logs = $db->query(
            "SELECT l.gestartet_am, l.trigger_typ, l.erfolg_count, l.dublette_count, l.fehler_count,
                    l.dauer_ms, l.verbindungs_fehler, k.name AS konto_name
             FROM mail_pull_logs l
             LEFT JOIN mail_konten k ON k.id = l.konto_id
             ORDER BY l.id DESC LIMIT 20"
        );
    ?>
    <?php if (empty($logs)): ?>
        <p class="muted">Noch keine Pull-Läufe.</p>
    <?php else: ?>
        <table class="lam-table" style="font-size:var(--d-fs-sm);">
            <thead>
                <tr><th>Zeit</th><th>Konto</th><th>Trigger</th><th class="right">Erfolg</th><th class="right">Dubl.</th><th class="right">Fehler</th><th class="right">Dauer</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['gestartet_am']) ?></td>
                        <td><?= htmlspecialchars($l['konto_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($l['trigger_typ']) ?></td>
                        <td class="right"><?= (int)$l['erfolg_count'] ?></td>
                        <td class="right"><?= (int)$l['dublette_count'] ?></td>
                        <td class="right"><?= (int)$l['fehler_count'] ?></td>
                        <td class="right"><?= (int)$l['dauer_ms'] ?> ms</td>
                        <td><?= $l['verbindungs_fehler'] ? '<span style="color:var(--rose-700);">' . htmlspecialchars($l['verbindungs_fehler']) . '</span>' : '<span style="color:var(--emerald-700);">OK</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
/* ================= Lernsystem: Stil + gelernte Regeln ================= */

/** Eigene Escape-Funktion: escapeHtml() lebt in _tab_smtp.php und ist hier nicht
 *  garantiert geladen (die Tabs werden einzeln eingebunden). Ein fehlendes escapeHtml
 *  haette die ganze Lern-Karte lautlos abstuerzen lassen. */
function lernEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}


function lernKontoId() { return document.getElementById('lern-konto')?.value || 0; }

async function lernLaden() {
    const id = lernKontoId();
    if (!id) return;
    const statusEl = document.getElementById('lern-status');
    const box = document.getElementById('lern-inhalt');
    try {
        const r = await fetch('/api/v1/mail/lernen?konto_id=' + encodeURIComponent(id));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        const d = j.data;

        // Laeuft gerade ein Stil-Lauf? Dann weiterpollen statt den Nutzer raten lassen.
        if (d.status === 'laeuft') {
            statusEl.innerHTML = '⏳ Stil-Lauf läuft … <em>' + lernEsc(d.meldung || '') + '</em>';
            box.innerHTML = '';
            setTimeout(lernLaden, 5000);
            return;
        }
        if (d.status === 'fehler') {
            statusEl.innerHTML = '<span style="color:var(--rose-700);">Stil-Lauf fehlgeschlagen: '
                + lernEsc(d.meldung || '') + '</span>';
        } else if (d.status === 'fertig') {
            statusEl.innerHTML = '✓ ' + lernEsc(d.meldung || '')
                + (d.stil_am ? ' <span class="muted">(' + lernEsc(d.stil_am) + ')</span>' : '');
        } else {
            statusEl.innerHTML = 'Noch nichts gelernt. Gib im Tab „E-Mail" unter „Ordner" ein paar Ordner '
                + 'zum <strong>Stil lernen</strong> frei und klicke dann „Stil neu lernen".';
        }

        let html = '';

        // --- Stilprofil ---
        if (d.profil && d.profil.profil_text) {
            html += '<details style="margin-bottom:16px;" open>'
                 + '<summary style="cursor:pointer;font-weight:600;">Dein Schreibstil '
                 + '<span class="muted" style="font-weight:400;">(aus ' + (d.profil.basis_anzahl || 0)
                 + ' eigenen Mails gelernt)</span></summary>'
                 + '<pre style="white-space:pre-wrap;font-family:inherit;font-size:var(--d-fs-sm);'
                 + 'background:var(--slate-50);padding:12px;border-radius:6px;margin:8px 0 0;">'
                 + lernEsc(d.profil.profil_text) + '</pre></details>';
        }

        // --- Offene Korrekturen ---
        if (d.offene_korrekturen > 0) {
            html += '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;margin-bottom:14px;font-size:var(--d-fs-sm);">'
                 + '<strong>' + d.offene_korrekturen + ' Korrektur(en)</strong> warten auf Auswertung — '
                 + 'Antworten, bei denen Du den KI-Entwurf geändert hast. '
                 + '<a href="#" onclick="event.preventDefault();lernKorrekturen()">Jetzt daraus lernen</a></div>';
        }

        // --- Regel-Vorschläge ---
        html += '<h3 style="margin:14px 0 6px;font-size:var(--d-fs-base);">Vorschläge '
             + '<span class="muted" style="font-weight:400;">(wirken erst nach Deiner Freigabe)</span></h3>';
        if (!(d.vorschlaege || []).length) {
            html += '<p class="muted" style="font-size:var(--d-fs-sm);margin:0 0 12px;">Keine offenen Vorschläge.</p>';
        } else {
            html += '<table class="lam-table" style="font-size:var(--d-fs-sm);margin-bottom:16px;"><thead><tr>'
                 + '<th style="width:90px;">Bereich</th><th>Regel</th><th style="width:80px;">Quelle</th>'
                 + '<th style="width:150px;">Entscheidung</th></tr></thead><tbody>';
            d.vorschlaege.forEach(v => {
                html += '<tr>'
                     + '<td>' + lernEsc(v.kategorie || '—') + '</td>'
                     + '<td><input type="text" value="' + lernEsc(v.regel_text) + '" '
                     + 'style="width:100%;padding:4px 6px;" onchange="lernRegelBearbeiten(' + v.id + ', this.value)">'
                     + (v.begruendung ? '<div class="muted" style="font-size:var(--d-fs-xs);margin-top:3px;">'
                          + lernEsc(v.begruendung) + '</div>' : '')
                     + (v.beispiel ? '<div class="muted" style="font-size:var(--d-fs-xs);font-style:italic;">„'
                          + lernEsc(v.beispiel) + '"</div>' : '')
                     + '</td>'
                     + '<td class="muted" style="font-size:var(--d-fs-xs);">'
                     + (v.quelle === 'korrektur' ? 'Deine Korrektur' : 'Stilanalyse') + '</td>'
                     + '<td style="white-space:nowrap;">'
                     + '<button class="thx-btn thx-btn-primary" style="padding:3px 10px;font-size:var(--d-fs-xs);" '
                     + 'onclick="lernRegel(' + v.id + ',\'freigeben\')">Freigeben</button> '
                     + '<button class="thx-btn thx-btn-secondary" style="padding:3px 10px;font-size:var(--d-fs-xs);" '
                     + 'onclick="lernRegel(' + v.id + ',\'verwerfen\')">Verwerfen</button>'
                     + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        // --- Aktive Regeln ---
        html += '<h3 style="margin:14px 0 6px;font-size:var(--d-fs-base);">Aktive Regeln '
             + '<span class="muted" style="font-weight:400;">(fließen in jeden Antwortentwurf ein)</span></h3>';
        if (!(d.aktive || []).length) {
            html += '<p class="muted" style="font-size:var(--d-fs-sm);margin:0;">Noch keine Regel freigegeben. '
                 + 'Die KI antwortet bis dahin ohne gelernte Vorgaben.</p>';
        } else {
            html += '<table class="lam-table" style="font-size:var(--d-fs-sm);"><thead><tr>'
                 + '<th style="width:90px;">Bereich</th><th>Regel</th><th style="width:100px;"></th></tr></thead><tbody>';
            d.aktive.forEach(v => {
                html += '<tr><td>' + lernEsc(v.kategorie || '—') + '</td>'
                     + '<td>' + lernEsc(v.regel_text) + '</td>'
                     + '<td><button class="thx-btn thx-btn-secondary" style="padding:3px 10px;font-size:var(--d-fs-xs);" '
                     + 'onclick="lernRegel(' + v.id + ',\'verwerfen\')">Abschalten</button></td></tr>';
            });
            html += '</tbody></table>';
        }

        box.innerHTML = html;
    } catch (e) {
        statusEl.innerHTML = '<span style="color:var(--rose-700);">' + lernEsc(e.message) + '</span>';
    }
}

async function lernRegel(regelId, aktion) {
    try {
        const r = await fetch('/api/v1/mail/lernen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ regel_id: regelId, aktion: aktion }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        lernLaden();
    } catch (e) { alert('Fehler: ' + e.message); }
}

async function lernRegelBearbeiten(regelId, text) {
    try {
        await fetch('/api/v1/mail/lernen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ regel_id: regelId, aktion: 'bearbeiten', text: text }),
        });
    } catch (e) { alert('Fehler: ' + e.message); }
}

async function lernStilStarten() {
    if (!confirm('Ich durchsuche die zum Stil-Lernen freigegebenen Ordner nach Deinen EIGENEN Mails, '
              + 'leite daraus Deinen Schreibstil ab und schlage Regeln vor.\n\n'
              + 'Diese Mails landen NICHT im Posteingang. Das Postfach wird nicht verändert.\n'
              + 'Der Lauf dauert einige Minuten.\n\nStarten?')) return;
    try {
        const r = await fetch('/api/v1/mail/lernen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ konto_id: lernKontoId(), aktion: 'stil_lernen' }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        lernLaden();
    } catch (e) { alert('Fehler: ' + e.message); }
}

async function lernKorrekturen() {
    const el = document.getElementById('lern-status');
    el.textContent = 'Korrekturen werden ausgewertet …';
    try {
        const r = await fetch('/api/v1/mail/lernen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ konto_id: lernKontoId(), aktion: 'korrekturen' }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        alert(j.message);
        lernLaden();
    } catch (e) { alert('Fehler: ' + e.message); lernLaden(); }
}

document.addEventListener('DOMContentLoaded', lernLaden);
if (document.readyState !== 'loading') lernLaden();

// ============ Vorlagen-CRUD ============
function mailVorlageOeffnenNeu() {
    const form = document.getElementById('mail-vorlage-form');
    form.reset();
    form.querySelector('[name=id]').value = '';
    form.querySelector('[name=aktiv]').checked = true;
    document.getElementById('mail-vorlage-modal-titel').textContent = 'Neue Vorlage';
    document.getElementById('mail-vorlage-fehler').style.display = 'none';
    document.getElementById('mail-vorlage-modal').style.display = 'flex';
}
function mailVorlageBearbeiten(v) {
    const form = document.getElementById('mail-vorlage-form');
    form.reset();
    Object.entries(v).forEach(([k, val]) => {
        const el = form.querySelector('[name=' + k + ']');
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!parseInt(val);
        else el.value = val || '';
    });
    document.getElementById('mail-vorlage-modal-titel').textContent = 'Vorlage bearbeiten: ' + v.name;
    document.getElementById('mail-vorlage-fehler').style.display = 'none';
    document.getElementById('mail-vorlage-modal').style.display = 'flex';
}
function mailVorlageSchliessen() {
    document.getElementById('mail-vorlage-modal').style.display = 'none';
}
async function mailVorlageSpeichern() {
    const form = document.getElementById('mail-vorlage-form');
    const fd = new FormData(form);
    const daten = {};
    for (const [k, v] of fd.entries()) daten[k] = v;
    daten.aktiv = form.querySelector('[name=aktiv]').checked ? 1 : 0;
    if (daten.id === '') delete daten.id;
    try {
        const r = await fetch('/api/v1/mail/vorlage-save', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify(daten),
        });
        const j = await r.json();
        if (!j.success) {
            const f = document.getElementById('mail-vorlage-fehler');
            f.textContent = j.error || j.message || 'Fehler.';
            f.style.display = 'block';
            return;
        }
        location.reload();
    } catch (e) {
        const f = document.getElementById('mail-vorlage-fehler');
        f.textContent = 'Verbindungsfehler: ' + e.message;
        f.style.display = 'block';
    }
}
async function mailVorlageLoeschen(id, name) {
    if (!confirm('Vorlage „' + name + '" löschen?')) return;
    try {
        const r = await fetch('/api/v1/mail/vorlage-loeschen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!j.success) { alert(j.error || 'Fehler.'); return; }
        location.reload();
    } catch (e) { alert('Verbindungsfehler: ' + e.message); }
}

// ============ Regel-CRUD ============
function mailRegelOeffnenNeu() {
    const form = document.getElementById('mail-regel-form');
    form.reset();
    form.querySelector('[name=id]').value = '';
    form.querySelector('[name=aktiv]').checked = true;
    form.querySelector('[name=prioritaet]').value = '10';
    document.getElementById('mail-regel-modal-titel').textContent = 'Neue Regel';
    document.getElementById('mail-regel-fehler').style.display = 'none';
    document.getElementById('mail-regel-modal').style.display = 'flex';
}
function mailRegelBearbeiten(r) {
    const form = document.getElementById('mail-regel-form');
    form.reset();
    Object.entries(r).forEach(([k, val]) => {
        const el = form.querySelector('[name=' + k + ']');
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!parseInt(val);
        else el.value = val || '';
    });
    document.getElementById('mail-regel-modal-titel').textContent = 'Regel bearbeiten: ' + r.name;
    document.getElementById('mail-regel-fehler').style.display = 'none';
    document.getElementById('mail-regel-modal').style.display = 'flex';
}
function mailRegelSchliessen() {
    document.getElementById('mail-regel-modal').style.display = 'none';
}
async function mailRegelSpeichern() {
    const form = document.getElementById('mail-regel-form');
    const fd = new FormData(form);
    const daten = {};
    for (const [k, v] of fd.entries()) daten[k] = v;
    daten.aktiv = form.querySelector('[name=aktiv]').checked ? 1 : 0;
    if (daten.id === '') delete daten.id;
    try {
        const r = await fetch('/api/v1/mail/regel-save', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify(daten),
        });
        const j = await r.json();
        if (!j.success) {
            const f = document.getElementById('mail-regel-fehler');
            f.textContent = j.error || j.message || 'Fehler.';
            f.style.display = 'block';
            return;
        }
        location.reload();
    } catch (e) {
        const f = document.getElementById('mail-regel-fehler');
        f.textContent = 'Verbindungsfehler: ' + e.message;
        f.style.display = 'block';
    }
}
async function mailRegelLoeschen(id, name) {
    if (!confirm('Regel „' + name + '" löschen?')) return;
    try {
        const r = await fetch('/api/v1/mail/regel-loeschen', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin', body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!j.success) { alert(j.error || 'Fehler.'); return; }
        location.reload();
    } catch (e) { alert('Verbindungsfehler: ' + e.message); }
}
</script>
