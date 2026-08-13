<?php
/**
 * E-Mail-Tab — zwei Bereiche:
 * 1. System-Versand-SMTP (für Einladungen, Passwort-Reset, interne Benachrichtigungen)
 * 2. Mail-Konten (IMAP+SMTP-Postfächer für das Mail-Tool unter /mail)
 */
?>
<div class="settings-card">
    <h2>System-Versand-SMTP</h2>
    <p class="settings-card-sub">
        Zentrales Versand-Konto für system-generierte Mails (Benutzer-Einladungen, Passwort-Reset, interne Benachrichtigungen).
        Postfach-Verwaltung für das Mail-Tool steht weiter unten.
    </p>
    <form id="form-smtp" onsubmit="event.preventDefault(); SettingsSave(this);">
        <div class="settings-row three-col">
            <div class="settings-field">
                <label for="smtp_host">SMTP-Host</label>
                <input type="text" id="smtp_host" name="smtp_host"
                       value="<?= htmlspecialchars($valueOf('smtp_host')) ?>"
                       placeholder="smtp.example.com">
            </div>
            <div class="settings-field">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="smtp_port"
                       value="<?= htmlspecialchars($valueOf('smtp_port', '587')) ?>"
                       placeholder="587">
            </div>
            <div class="settings-field">
                <label for="smtp_encryption">Verschlüsselung</label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <?php $enc = $valueOf('smtp_encryption', 'tls'); ?>
                    <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                    <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                </select>
            </div>
        </div>
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="smtp_username">Benutzername</label>
                <input type="text" id="smtp_username" name="smtp_username"
                       value="<?= htmlspecialchars($valueOf('smtp_username')) ?>"
                       placeholder="user@example.com">
            </div>
            <div class="settings-field">
                <label for="smtp_password">
                    Passwort
                    <?php if ($isConfigured('smtp_password')): ?>
                        <span class="key-status ja">Gesetzt</span>
                    <?php else: ?>
                        <span class="key-status nein">Nicht gesetzt</span>
                    <?php endif; ?>
                </label>
                <input type="password" id="smtp_password" name="smtp_password" autocomplete="new-password"
                       placeholder="<?= $isConfigured('smtp_password') ? 'Neu eingeben zum Ersetzen…' : 'SMTP-Passwort' ?>">
            </div>
        </div>
        <div class="settings-row two-col">
            <div class="settings-field">
                <label for="smtp_from_email">Absender E-Mail</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email"
                       value="<?= htmlspecialchars($valueOf('smtp_from_email')) ?>"
                       placeholder="noreply@example.com">
            </div>
            <div class="settings-field">
                <label for="smtp_from_name">Absender Name</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name"
                       value="<?= htmlspecialchars($valueOf('smtp_from_name')) ?>"
                       placeholder="<?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'KI Text Tool') ?>">
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">System-SMTP speichern</button>
            <button type="button" class="thx-btn thx-btn-secondary" id="smtp-test-btn"
                    onclick="testeSmtp()">Test-Mail senden</button>
            <span id="smtp-test-status" class="status-msg muted"></span>
        </div>
    </form>
</div>

<!-- ====== Mail-Konten (für das Mail-Tool) ====== -->
<div class="settings-card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <h2 style="margin:0;">Mail-Konten <span style="font-weight:normal;color:var(--slate-500);font-size:var(--d-fs-sm);">— für das Mail-Tool unter <a href="/mail" style="color:var(--thoxan-700);">/mail</a></span></h2>
        <button type="button" class="thx-btn thx-btn-primary" onclick="mailKontoOeffnenNeu()">+ Neues Konto</button>
    </div>
    <p class="settings-card-sub">
        Pro Konto IMAP für Empfang und SMTP für Versand. Jedes Konto wird vom Mail-Tool als eigenes Postfach behandelt
        (Posteingang, KI-Klassifikation, Antwort-Editor). Passwörter werden AES-256-GCM verschlüsselt gespeichert.
    </p>

    <div id="mail-konten-liste" style="margin-top:10px;">
        <div class="muted">Lade Konten …</div>
    </div>

    <div id="mail-konto-test-ergebnis" style="display:none;margin-top:10px;padding:10px;border-radius:4px;font-size:var(--d-fs-sm);"></div>
</div>

<!-- Konto-Editor-Modal -->
<div id="mail-konto-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;max-width:720px;width:100%;max-height:92vh;overflow-y:auto;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 id="mail-konto-modal-titel" style="margin:0;">Neues Konto</h2>
            <button type="button" onclick="mailKontoSchliessen()" class="thx-icon-btn" title="Schließen"><span class="material-symbols-rounded">close</span></button>
        </div>

        <form id="mail-konto-form" onsubmit="event.preventDefault(); mailKontoSpeichern();">
            <input type="hidden" name="id" value="">

            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>Name (Anzeige) *</label>
                    <input type="text" name="name" required placeholder="z.B. PR Thoxan">
                </div>
                <div class="settings-field">
                    <label>E-Mail-Adresse *</label>
                    <input type="email" name="email_adresse" required placeholder="pr@thoxan.com">
                </div>
            </div>

            <h3 style="margin-top:20px;margin-bottom:8px;font-size:var(--d-fs-base);">Anmeldeart</h3>
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>Wie meldet sich das Tool am Postfach an?</label>
                    <select name="auth_typ" onchange="mailAuthTypWechsel()">
                        <option value="passwort">Benutzername + Passwort (IONOS, eigener Server)</option>
                        <option value="oauth2">Microsoft 365 (Exchange Online)</option>
                    </select>
                </div>
                <div class="settings-field">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="nur_lesen" value="1">
                        <span>Nur lesen (Postfach nicht verändern)</span>
                    </label>
                    <small class="muted">Pflicht für Postfächer, in denen Du parallel mit Outlook arbeitest:
                        Es wird nichts verschoben und nichts als gelesen markiert.</small>
                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">
                        <input type="checkbox" name="ist_standard" value="1">
                        <span>Standard-Postfach</span>
                    </label>
                    <small class="muted">Wird in /mail geöffnet, solange noch kein anderes gewählt wurde.
                        Danach merkt sich das Werkzeug Deine letzte Wahl.</small>
                </div>
            </div>

            <!-- Microsoft 365: erscheint nur bei Anmeldeart "Microsoft 365" -->
            <div id="mail-oauth-block" style="display:none;border:1px solid var(--color-gray-200);border-radius:8px;padding:14px;margin-bottom:8px;background:var(--color-gray-50);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <strong style="font-size:var(--d-fs-sm);">Microsoft-365-Verbindung</strong>
                    <span id="mail-oauth-status" class="muted" style="font-size:var(--d-fs-xs);">nicht verbunden</span>
                </div>
                <p class="muted" style="font-size:var(--d-fs-xs);margin:0 0 10px;">
                    Die drei Werte stammen aus der App-Registrierung in Entra ID
                    (Anleitung: <code>docs/entra-id-app-registrierung.md</code>).
                    Umleitungs-URI dort: <code id="mail-oauth-redirect"></code>
                </p>
                <div class="settings-row two-col">
                    <div class="settings-field">
                        <label>Verzeichnis-ID (Tenant)</label>
                        <input type="text" name="oauth_tenant_id" placeholder="z.B. 8f1c…-…">
                    </div>
                    <div class="settings-field">
                        <label>Anwendungs-ID (Client)</label>
                        <input type="text" name="oauth_client_id" placeholder="z.B. 4b2d…-…">
                    </div>
                </div>
                <div class="settings-field">
                    <label>Geheimer Clientschlüssel (Spalte „Wert")</label>
                    <input type="password" name="oauth_client_secret" autocomplete="new-password" placeholder="leer = unverändert">
                    <small class="muted">Wird verschlüsselt gespeichert und nie wieder angezeigt.</small>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-sm" onclick="mailOauthVerbinden()">
                        <span class="material-symbols-rounded">link</span> Mit Microsoft verbinden
                    </button>
                    <small class="muted">Erst speichern, dann verbinden.</small>
                </div>
            </div>

            <h3 style="margin-top:20px;margin-bottom:8px;font-size:var(--d-fs-base);">IMAP (Empfang)</h3>
            <div class="settings-row three-col">
                <div class="settings-field">
                    <label>IMAP-Host *</label>
                    <input type="text" name="imap_host" placeholder="imap.ionos.de / outlook.office365.com">
                </div>
                <div class="settings-field">
                    <label>Port</label>
                    <input type="number" name="imap_port" value="993">
                </div>
                <div class="settings-field">
                    <label>Verschlüsselung</label>
                    <select name="imap_encryption">
                        <option value="ssl">SSL/TLS (993)</option>
                        <option value="starttls">STARTTLS (143)</option>
                        <option value="none">keine</option>
                    </select>
                </div>
            </div>
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>IMAP-Benutzer</label>
                    <input type="text" name="imap_username" placeholder="meist = E-Mail-Adresse">
                </div>
                <div class="settings-field">
                    <label>IMAP-Passwort</label>
                    <input type="password" name="imap_password" autocomplete="new-password" placeholder="leer = unverändert">
                </div>
            </div>
            <div class="settings-row three-col">
                <div class="settings-field">
                    <label>Inbox-Ordner</label>
                    <input type="text" name="imap_folder_inbox" value="INBOX">
                </div>
                <div class="settings-field">
                    <label>Verarbeitet</label>
                    <input type="text" name="imap_folder_verarbeitet" value="INBOX.Verarbeitet">
                </div>
                <div class="settings-field">
                    <label>Fehler</label>
                    <input type="text" name="imap_folder_fehler" value="INBOX.Fehler">
                </div>
            </div>

            <h3 style="margin-top:20px;margin-bottom:8px;font-size:var(--d-fs-base);">SMTP (Versand)</h3>
            <div class="settings-row three-col">
                <div class="settings-field">
                    <label>SMTP-Host *</label>
                    <input type="text" name="smtp_host" placeholder="smtp.ionos.de">
                </div>
                <div class="settings-field">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="587">
                </div>
                <div class="settings-field">
                    <label>Verschlüsselung</label>
                    <select name="smtp_encryption">
                        <option value="starttls">STARTTLS (587)</option>
                        <option value="ssl">SSL (465)</option>
                        <option value="none">keine</option>
                    </select>
                </div>
            </div>
            <div class="settings-row two-col">
                <div class="settings-field">
                    <label>SMTP-Benutzer</label>
                    <input type="text" name="smtp_username" placeholder="meist = E-Mail-Adresse">
                </div>
                <div class="settings-field">
                    <label>SMTP-Passwort</label>
                    <input type="password" name="smtp_password" autocomplete="new-password" placeholder="leer = unverändert">
                </div>
            </div>

            <h3 style="margin-top:20px;margin-bottom:8px;font-size:var(--d-fs-base);">Signatur</h3>
            <div class="settings-field">
                <textarea name="signatur" rows="4" placeholder="Mit freundlichen Grüßen ..."></textarea>
            </div>

            <div class="settings-row three-col" style="align-items:end;">
                <div class="settings-field">
                    <label><input type="checkbox" name="aktiv" value="1" checked> Konto aktiv (Polling läuft)</label>
                </div>
                <div class="settings-field">
                    <label><input type="checkbox" name="auto_antwort_aktiv" value="1"> Auto-Versand erlaubt</label>
                </div>
                <div class="settings-field">
                    <label>Konfidenz-Schwelle Auto-Versand</label>
                    <input type="number" step="0.01" min="0.5" max="1" name="auto_antwort_konfidenz_min" value="0.95">
                </div>
            </div>

            <div id="mail-konto-fehler" style="color:var(--rose-700);font-size:var(--d-fs-sm);margin-top:10px;display:none;"></div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;border-top:1px solid var(--slate-200);padding-top:16px;">
                <button type="button" class="thx-btn thx-btn-secondary" onclick="mailKontoSchliessen()">Abbrechen</button>
                <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- Ordner-Auswahl je Konto -->
<div id="mail-ordner-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;max-width:820px;width:100%;max-height:92vh;overflow-y:auto;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 id="mail-ordner-titel" style="margin:0;">Ordner auswählen</h2>
            <button type="button" onclick="mailOrdnerSchliessen()" class="thx-icon-btn" title="Schließen"><span class="material-symbols-rounded">close</span></button>
        </div>
        <div id="mail-ordner-body"></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;border-top:1px solid var(--slate-200);padding-top:16px;">
            <button type="button" class="thx-btn thx-btn-secondary" onclick="mailOrdnerSchliessen()">Abbrechen</button>
            <button type="button" class="thx-btn thx-btn-primary" onclick="mailOrdnerSpeichern()">Auswahl speichern</button>
        </div>
    </div>
</div>

<script>
// Rueckmeldung von Microsoft (kommt per Adresszeile aus dem OAuth-Callback)
(function () {
    const p = new URLSearchParams(window.location.search);
    const status = p.get('oauth');
    if (!status) return;
    const text = p.get('meldung') || '';
    if (status === 'ok') {
        alert('✓ ' + text);
    } else {
        alert('Microsoft-Verbindung fehlgeschlagen:\n\n' + text);
    }
    // Adresszeile aufraeumen, damit die Meldung nicht bei jedem Neuladen erscheint
    p.delete('oauth'); p.delete('meldung');
    history.replaceState({}, '', window.location.pathname + (p.toString() ? '?' + p : ''));
})();

// ============ System-SMTP-Test (Original-Funktion) ============
async function testeSmtp() {
    const btn = document.getElementById('smtp-test-btn');
    const status = document.getElementById('smtp-test-status');
    const adminEmail = '<?= htmlspecialchars(\Core\Auth::user()['email'] ?? '') ?>';
    btn.disabled = true;
    status.textContent = 'Verbinde mit SMTP-Server…';
    status.className = 'status-msg muted';
    try {
        const resp = await App.request('POST', '/admin/settings', { action: 'test_smtp' });
        if (resp.success) {
            status.textContent = 'Gesendet an ' + adminEmail;
            status.className = 'status-msg ok';
            App.showNotification(resp.message || 'Test-Mail gesendet', 'success');
        } else {
            status.textContent = resp.message || 'Fehler';
            status.className = 'status-msg err';
            App.showNotification(resp.message || 'Fehlgeschlagen', 'error');
        }
    } catch (e) {
        const msg = e.message || 'Verbindung fehlgeschlagen';
        status.textContent = msg;
        status.className = 'status-msg err';
        App.showNotification(msg, 'error');
    }
    btn.disabled = false;
}

// ============ Mail-Konten (Vanilla-JS, kein Alpine) ============
let mailKontenListe = [];

async function mailKontenLaden() {
    const container = document.getElementById('mail-konten-liste');
    container.innerHTML = '<div class="muted">Lade …</div>';
    try {
        const r = await fetch('/api/v1/mail/konten', { credentials: 'same-origin' });
        const j = await r.json();
        mailKontenListe = j.success ? j.data : [];
        mailKontenRendern();
    } catch (e) {
        container.innerHTML = '<div class="status-msg err">Fehler beim Laden: ' + e.message + '</div>';
    }
}

function mailKontenRendern() {
    const container = document.getElementById('mail-konten-liste');
    if (mailKontenListe.length === 0) {
        container.innerHTML = '<div class="muted" style="padding:12px;">Noch kein Konto angelegt. Klick auf „+ Neues Konto" oben rechts.</div>';
        return;
    }
    let html = '<table class="lam-table" style="font-size:var(--d-fs-sm);"><thead><tr>'
             + '<th>Name</th><th>E-Mail</th><th>IMAP</th><th>SMTP</th><th>Aktiv</th><th class="right">Aktion</th>'
             + '</tr></thead><tbody>';
    mailKontenListe.forEach(k => {
        const istOauth = (k.auth_typ === 'oauth2');
        const imapOk = istOauth ? parseInt(k.oauth_verbunden) : parseInt(k.imap_password_gesetzt);
        const smtpOk = istOauth ? parseInt(k.oauth_verbunden) : parseInt(k.smtp_password_gesetzt);
        const imapStatus = istOauth
            ? (imapOk ? '<span style="color:var(--emerald-700);">Microsoft 365 verbunden</span>'
                      : '<span style="color:var(--rose-600);">nicht verbunden</span>')
            : (imapOk ? '<span style="color:var(--emerald-700);">Passwort gesetzt</span>'
                      : '<span style="color:var(--rose-600);">Passwort fehlt</span>');
        html += '<tr>'
             + '<td><strong>' + escapeHtml(k.name) + '</strong>'
             + (parseInt(k.nur_lesen) ? '<br><span class="muted" style="font-size:var(--d-fs-xs);">nur lesen</span>' : '')
             + '</td>'
             + '<td>' + escapeHtml(k.email_adresse) + '</td>'
             + '<td class="muted" style="font-size:var(--d-fs-xs);">' + escapeHtml(k.imap_host || '—') + ':' + (k.imap_port || '') + '<br>'
             + imapStatus
             + '</td>'
             + '<td class="muted" style="font-size:var(--d-fs-xs);">' + escapeHtml(k.smtp_host || '—') + ':' + (k.smtp_port || '') + '<br>'
             + (smtpOk ? '<span style="color:var(--emerald-700);">Passwort gesetzt</span>' : '<span style="color:var(--rose-600);">Passwort fehlt</span>')
             + '</td>'
             + '<td>' + (parseInt(k.aktiv) ? '<span class="key-status ja">ja</span>' : '<span class="key-status nein">pausiert</span>') + '</td>'
             + '<td class="right" style="white-space:nowrap;">'
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="mailKontoOeffnenBearbeiten(' + k.id + ')">bearbeiten</button> '
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="mailOrdnerOeffnen(' + k.id + ', \'' + escapeHtml(k.name).replace(/'/g, "\\'") + '\')">Ordner</button> '
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="mailStilLernen(' + k.id + ')" title="Aus Deinen eigenen Mails lernen, wie Du schreibst">Stil lernen</button> '
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="mailKontoTesten(' + k.id + ', \'imap\')">test IMAP</button> '
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="mailKontoTesten(' + k.id + ', \'smtp\')">test SMTP</button> '
             + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);color:var(--rose-700);" onclick="mailKontoLoeschen(' + k.id + ', \'' + escapeHtml(k.name).replace(/'/g, "\\'") + '\')">löschen</button>'
             + '</td>'
             + '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

function mailKontoOeffnenNeu() {
    const form = document.getElementById('mail-konto-form');
    form.reset();
    form.querySelector('[name=id]').value = '';
    form.querySelector('[name=aktiv]').checked = true;
    form.querySelector('[name=imap_port]').value = '993';
    form.querySelector('[name=smtp_port]').value = '587';
    form.querySelector('[name=imap_encryption]').value = 'ssl';
    form.querySelector('[name=smtp_encryption]').value = 'starttls';
    form.querySelector('[name=imap_folder_inbox]').value = 'INBOX';
    form.querySelector('[name=imap_folder_verarbeitet]').value = 'INBOX.Verarbeitet';
    form.querySelector('[name=imap_folder_fehler]').value = 'INBOX.Fehler';
    form.querySelector('[name=auto_antwort_konfidenz_min]').value = '0.95';
    form.querySelector('[name=auth_typ]').value = 'passwort';
    mailAktuelleKontoId = null;
    mailAuthTypWechsel();
    document.getElementById('mail-oauth-status').textContent = 'nicht verbunden';
    document.getElementById('mail-konto-modal-titel').textContent = 'Neues Mail-Konto';
    document.getElementById('mail-konto-fehler').style.display = 'none';
    document.getElementById('mail-konto-modal').style.display = 'flex';
}

function mailKontoOeffnenBearbeiten(id) {
    const k = mailKontenListe.find(x => x.id == id);
    if (!k) return;
    const form = document.getElementById('mail-konto-form');
    form.reset();
    Object.entries(k).forEach(([key, value]) => {
        const el = form.querySelector('[name=' + key + ']');
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!parseInt(value);
        else el.value = value || '';
    });
    form.querySelector('[name=imap_password]').value = '';
    form.querySelector('[name=smtp_password]').value = '';
    form.querySelector('[name=oauth_client_secret]').value = '';
    mailAktuelleKontoId = k.id;
    mailAuthTypWechsel();
    const st = document.getElementById('mail-oauth-status');
    if (parseInt(k.oauth_verbunden)) {
        st.textContent = '✓ verbunden';
        st.style.color = 'var(--emerald-700)';
    } else {
        st.textContent = 'nicht verbunden';
        st.style.color = '';
    }
    document.getElementById('mail-konto-modal-titel').textContent = 'Konto bearbeiten: ' + k.name;
    document.getElementById('mail-konto-fehler').style.display = 'none';
    document.getElementById('mail-konto-modal').style.display = 'flex';
}

function mailKontoSchliessen() {
    document.getElementById('mail-konto-modal').style.display = 'none';
}

/* ---------- Microsoft 365 (OAuth2) ---------- */

let mailAktuelleKontoId = null;

// Blendet die Microsoft-Felder ein/aus und macht klar, dass bei Microsoft 365
// kein Passwort mehr gebraucht wird (das verwirrt sonst am meisten).
function mailAuthTypWechsel() {
    const form = document.getElementById('mail-konto-form');
    const istOauth = form.querySelector('[name=auth_typ]').value === 'oauth2';

    document.getElementById('mail-oauth-block').style.display = istOauth ? 'block' : 'none';
    document.getElementById('mail-oauth-redirect').textContent =
        window.location.origin + '/api/v1/mail/oauth-callback';

    const imapPw = form.querySelector('[name=imap_password]');
    const smtpPw = form.querySelector('[name=smtp_password]');
    [imapPw, smtpPw].forEach(el => {
        el.disabled = istOauth;
        el.placeholder = istOauth
            ? 'bei Microsoft 365 nicht nötig'
            : 'leer = unverändert';
    });

    // Bei Microsoft 365 sind Host/Port fix — vorbelegen, wenn noch leer.
    if (istOauth) {
        const ih = form.querySelector('[name=imap_host]');
        const sh = form.querySelector('[name=smtp_host]');
        if (!ih.value) ih.value = 'outlook.office365.com';
        if (!sh.value) sh.value = 'smtp.office365.com';
        form.querySelector('[name=imap_port]').value = '993';
        form.querySelector('[name=smtp_port]').value = '587';
        form.querySelector('[name=imap_encryption]').value = 'ssl';
        form.querySelector('[name=smtp_encryption]').value = 'starttls';
        // Nur-Lesen bei NEUEN Microsoft-Konten vorschlagen (dort arbeitet der Nutzer
        // parallel in Outlook). Bei bestehenden Konten NICHT überschreiben — sonst
        // würde der gespeicherte Wert beim Bearbeiten stillschweigend umgestellt.
        const istNeu = !form.querySelector('[name=id]').value;
        if (istNeu) form.querySelector('[name=nur_lesen]').checked = true;
    }
}

function mailOauthVerbinden() {
    const form = document.getElementById('mail-konto-form');
    const id = form.querySelector('[name=id]').value;
    if (!id) {
        alert('Bitte das Konto zuerst speichern — danach kannst Du es mit Microsoft verbinden.');
        return;
    }
    window.location.href = '/api/v1/mail/oauth-start?konto_id=' + encodeURIComponent(id);
}

/* ---------- Stil lernen ---------- */

async function mailStilLernen(id) {
    if (!confirm('Ich durchsuche die zum Stil-Lernen freigegebenen Ordner nach Deinen EIGENEN Mails '
              + 'und leite daraus ab, wie Du schreibst.\n\n'
              + 'Diese Mails landen nicht im Posteingang. Das kann ein bis zwei Minuten dauern.\n\nStarten?')) return;

    const btn = event.target;
    const alt = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'lerne …';
    try {
        const r = await fetch('/api/v1/mail/stil-lernen', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ konto_id: id }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        alert('✓ ' + j.message + '\n\n--- Erkannter Stil ---\n\n' + (j.data.profil || '').slice(0, 1500));
    } catch (e) {
        alert('Stil-Lernen fehlgeschlagen:\n\n' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = alt;
    }
}

/* ---------- Ordner-Auswahl ---------- */

let mailOrdnerKontoId = null;

async function mailOrdnerOeffnen(id, name) {
    mailOrdnerKontoId = id;
    document.getElementById('mail-ordner-titel').textContent = 'Ordner auswählen: ' + name;
    const body = document.getElementById('mail-ordner-body');
    body.innerHTML = '<div class="muted" style="padding:12px;">Katalog wird geladen …</div>';
    document.getElementById('mail-ordner-modal').style.display = 'flex';
    await mailOrdnerLaden();
}

async function mailOrdnerLaden() {
    const body = document.getElementById('mail-ordner-body');
    try {
        const r = await fetch('/api/v1/mail/ordner-auswahl?konto_id=' + encodeURIComponent(mailOrdnerKontoId));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        const d = j.data;

        if (d.status === 'laeuft') {
            body.innerHTML = '<div style="padding:16px;">'
                + '<p>Dein Postfach wird eingelesen … <strong>' + d.fortschritt + ' von ' + (d.gesamt || '?') + '</strong> Ordnern.</p>'
                + '<p class="muted" style="font-size:var(--d-fs-xs);">Das passiert nur einmal. Danach ist die Auswahl sofort da.</p></div>';
            setTimeout(mailOrdnerLaden, 3000);   // weiterpollen
            return;
        }
        if (d.status === 'leer' || !(d.ordner || []).length) {
            body.innerHTML = '<div style="padding:16px;">'
                + '<p>Für dieses Postfach gibt es noch keinen Ordner-Katalog.</p>'
                + '<p class="muted" style="font-size:var(--d-fs-xs);">Ich lese Struktur und Mail-Anzahl einmal ein. '
                + 'Danach kannst Du sofort auswählen und siehst, welche Ordner überhaupt Mails enthalten.</p>'
                + '<button class="thx-btn thx-btn-primary" onclick="mailOrdnerScan()">Ordner jetzt einlesen</button></div>';
            return;
        }
        if (d.status === 'fehler') {
            body.innerHTML = '<div style="padding:16px;color:var(--rose-700);">Einlesen fehlgeschlagen: '
                + escapeHtml(d.meldung || '') + '<br><br>'
                + '<button class="thx-btn thx-btn-secondary" onclick="mailOrdnerScan()">Nochmal versuchen</button></div>';
            return;
        }
        mailOrdnerRendern(d.ordner, d.meldung);
    } catch (e) {
        body.innerHTML = '<div style="color:var(--rose-700);padding:12px;">' + escapeHtml(e.message) + '</div>';
    }
}

async function mailOrdnerScan() {
    document.getElementById('mail-ordner-body').innerHTML =
        '<div class="muted" style="padding:16px;">Einlesen wird gestartet …</div>';
    try {
        await fetch('/api/v1/mail/ordner-auswahl', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ konto_id: mailOrdnerKontoId, aktion: 'scan' }),
        });
    } catch (e) { /* Status kommt eh per Polling */ }
    setTimeout(mailOrdnerLaden, 1500);
}

let mailOrdnerDaten = [];
let mailOrdnerOffen = {};      // pfad -> aufgeklappt?

function mailOrdnerRendern(ordner, meldung) {
    mailOrdnerDaten = ordner || [];
    mailOrdnerOffen = {};
    const body = document.getElementById('mail-ordner-body');
    if (!mailOrdnerDaten.length) {
        body.innerHTML = '<div class="muted" style="padding:12px;">Keine Ordner im Katalog.</div>';
        return;
    }

    body.innerHTML =
        '<p class="muted" style="font-size:var(--d-fs-xs);margin-top:0;">'
      + '<strong>Abholen</strong> = erscheint in /mail zum Lesen und Beantworten. &nbsp;·&nbsp; '
      + '<strong>Ins Wissen</strong> = für die KI durchsuchbar. &nbsp;·&nbsp; '
      + '<strong>Stil lernen</strong> = aus <em>Deinen eigenen</em> Mails darin wird gelernt, wie Du schreibst '
      + '(diese landen NICHT im Posteingang). &nbsp;·&nbsp; '
      + '<strong>+Unter</strong> = gilt für alle Unterordner mit.</p>'

      + '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">'
      + '<input type="text" id="mail-ordner-suche" placeholder="Ordner suchen …" '
      + 'style="flex:1;min-width:180px;padding:6px 10px;" oninput="mailOrdnerFiltern()">'
      + '<label style="font-size:var(--d-fs-xs);display:flex;gap:5px;align-items:center;">'
      + '<input type="checkbox" id="mo-nur-mails" checked onchange="mailOrdnerFiltern()"> nur Ordner mit Mails</label>'
      + '<label style="font-size:var(--d-fs-xs);display:flex;gap:5px;align-items:center;" '
      + 'title="Kalender, Kontakte, Kategorien — das sind keine Mail-Ordner">'
      + '<input type="checkbox" id="mo-system" onchange="mailOrdnerFiltern()"> Nicht-Mail-Ordner zeigen</label>'
      + '</div>'

      + '<div style="display:flex;gap:6px;align-items:center;margin-bottom:8px;">'
      + '<span class="muted" style="font-size:var(--d-fs-xs);">Alle sichtbaren:</span>'
      + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:2px 8px;font-size:var(--d-fs-xs);" onclick="mailOrdnerAlle(\'abholen\',1)">Abholen an</button>'
      + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:2px 8px;font-size:var(--d-fs-xs);" onclick="mailOrdnerAlle(\'stil_lernen\',1)">Stil lernen an</button>'
      + '<button type="button" class="thx-btn thx-btn-secondary" style="padding:2px 8px;font-size:var(--d-fs-xs);" onclick="mailOrdnerAlle(null,0)">alles aus</button>'
      + '<span style="flex:1;"></span>'
      + '<span id="mail-ordner-zaehler" class="muted" style="font-size:var(--d-fs-xs);"></span>'
      + '</div>'

      + '<div id="mail-ordner-liste" style="max-height:46vh;overflow-y:auto;border:1px solid var(--color-gray-200);border-radius:6px;"></div>'
      + '<p class="muted" style="font-size:var(--d-fs-xs);margin:6px 0 0;">'
      + (meldung ? escapeHtml(meldung) + ' · ' : '')
      + '<a href="#" onclick="event.preventDefault();mailOrdnerScan()">Ordner neu einlesen</a></p>';

    mailOrdnerListeRendern();
}

/** Ist ein Ordner nach den aktiven Filtern ueberhaupt relevant? */
function mailOrdnerRelevant(o) {
    const nurMails = document.getElementById('mo-nur-mails')?.checked;
    const zeigeSystem = document.getElementById('mo-system')?.checked;
    const gewaehlt = o.abholen || o.ins_wissen || o.stil_lernen;

    // Kategorien und Kontaktlisten sind KEINE Mail-Ordner. Exchange gibt sie über IMAP
    // mit Eintragszahl heraus, rückt aber keine Nachrichten heraus. Sie gehören hier
    // grundsätzlich nicht hin — nur der Systemordner-Schalter blendet sie zur Kontrolle ein.
    if (o.mailordner === 0 && !zeigeSystem && !gewaehlt) return false;

    if (o.system && !zeigeSystem && !gewaehlt) return false;
    // Leere Ordner ausblenden — aber nie etwas verstecken, das ausgewählt ist,
    // und nie einen Ast, unter dem noch Ordner haengen.
    if (nurMails && !o.anzahl && !o.kinder && !gewaehlt) return false;
    return true;
}

/**
 * Echter Baum: nur die oberste Ebene, Unterordner per Pfeil aufklappen.
 * Bei 2000 Ordnern — davon rund 80 % leer und viele davon Outlook-Altlasten wie
 * "Kalender" oder "Kontakte" — ist eine flache Liste unbedienbar.
 */
function mailOrdnerListeRendern(suche) {
    suche = (suche || '').trim().toLowerCase();
    const liste = document.getElementById('mail-ordner-liste');

    let html = '<table class="lam-table" style="font-size:var(--d-fs-sm);margin:0;">'
             + '<thead><tr style="position:sticky;top:0;background:#fff;z-index:1;">'
             + '<th>Ordner</th><th style="width:64px;">Abholen</th><th style="width:70px;">Ins Wissen</th>'
             + '<th style="width:70px;">Stil lernen</th><th style="width:56px;">+Unter</th>'
             + '</tr></thead><tbody>';

    let sichtbar = 0;
    mailOrdnerDaten.forEach(o => {
        if (!mailOrdnerRelevant(o)) return;

        let zeigen;
        if (suche !== '') {
            zeigen = o.voll.toLowerCase().includes(suche);   // Suche geht quer durch alle Ebenen
        } else {
            zeigen = (o.tiefe === 0)
                  || !!mailOrdnerOffen[o.eltern]
                  || !!(o.abholen || o.ins_wissen || o.stil_lernen);
        }
        if (!zeigen) return;
        sichtbar++;

        const einzug = 8 + (suche !== '' ? 0 : (o.tiefe || 0) * 16);
        const pfeil = o.kinder
            ? '<span onclick="mailOrdnerKlappen(this)" data-p="' + escapeHtml(o.pfad) + '" '
              + 'style="cursor:pointer;user-select:none;display:inline-block;width:14px;color:var(--color-gray-500);">'
              + (mailOrdnerOffen[o.pfad] ? '▾' : '▸') + '</span>'
            : '<span style="display:inline-block;width:14px;"></span>';

        html += '<tr data-pfad="' + escapeHtml(o.pfad) + '">'
             + '<td style="padding-left:' + einzug + 'px;">' + pfeil + ' '
             + escapeHtml(suche !== '' ? o.voll : o.name)
             + (o.anzahl ? ' <span class="muted" style="font-size:var(--d-fs-xs);">(' + o.anzahl + ')</span>' : '')
             + (o.system ? ' <span class="muted" style="font-size:var(--d-fs-xs);">· System</span>' : '')
             + '</td>'
             + '<td><input type="checkbox" class="mo-abholen" ' + (o.abholen ? 'checked' : '') + '></td>'
             + '<td><input type="checkbox" class="mo-wissen" ' + (o.ins_wissen ? 'checked' : '') + '></td>'
             + '<td><input type="checkbox" class="mo-stil" ' + (o.stil_lernen ? 'checked' : '') + '></td>'
             + '<td><input type="checkbox" class="mo-rekursiv" ' + (o.rekursiv ? 'checked' : '')
             + (o.kinder ? '' : ' disabled title="keine Unterordner"') + '></td>'
             + '</tr>';
    });
    html += '</tbody></table>';
    if (sichtbar === 0) html = '<div class="muted" style="padding:12px;">Kein Ordner passt zu den Filtern.</div>';
    liste.innerHTML = html;

    const gewaehlt = mailOrdnerDaten.filter(o => o.abholen || o.ins_wissen || o.stil_lernen).length;
    document.getElementById('mail-ordner-zaehler').textContent =
        sichtbar + ' sichtbar · ' + gewaehlt + ' ausgewählt · ' + mailOrdnerDaten.length + ' insgesamt';
}

function mailOrdnerKlappen(el) {
    mailOrdnerAuswahlUebernehmen();
    const p = el.dataset.p;
    mailOrdnerOffen[p] = !mailOrdnerOffen[p];
    mailOrdnerListeRendern(document.getElementById('mail-ordner-suche').value);
}

/** Sammelaktion auf alle gerade sichtbaren Zeilen. feld=null => alles aus. */
function mailOrdnerAlle(feld, wert) {
    mailOrdnerAuswahlUebernehmen();

    const zeilen = [...document.querySelectorAll('#mail-ordner-liste tbody tr')];
    const treffer = zeilen
        .map(tr => mailOrdnerDaten.find(x => x.pfad === tr.dataset.pfad))
        .filter(Boolean);

    // Vorwarnen, wenn eine Sammelaktion viel Volumen scharfschaltet.
    // Ein unbedachter Klick hat schon einmal 249 Ordner mit 1898 Mails aktiviert —
    // der Knopf muss vorher sagen, was er anrichtet.
    if (feld !== null && wert) {
        const mails = treffer.reduce((s, o) => s + (parseInt(o.anzahl) || 0), 0);
        const was = feld === 'abholen' ? 'abgeholt' : 'zum Stil-Lernen durchsucht';
        if (treffer.length > 5 || mails > 200) {
            if (!confirm('Das schaltet ' + treffer.length + ' Ordner mit zusammen etwa '
                       + mails.toLocaleString('de-DE') + ' Mails scharf.\n\n'
                       + 'Diese Mails werden beim nächsten Abruf ' + was + '.\n\nFortfahren?')) {
                return;
            }
        }
    }

    treffer.forEach(o => {
        if (feld === null) { o.abholen = 0; o.ins_wissen = 0; o.stil_lernen = 0; o.rekursiv = 0; }
        else { o[feld] = wert; }
    });
    mailOrdnerListeRendern(document.getElementById('mail-ordner-suche').value);
}

function mailOrdnerFiltern() {
    // Auswahl der sichtbaren Zeilen sichern, bevor neu gezeichnet wird —
    // sonst gingen Haken beim Tippen oder Filtern verloren.
    mailOrdnerAuswahlUebernehmen();
    mailOrdnerListeRendern(document.getElementById('mail-ordner-suche').value);
}


/** Haken aus dem DOM zurueck in mailOrdnerDaten schreiben. */
function mailOrdnerAuswahlUebernehmen() {
    document.querySelectorAll('#mail-ordner-liste tbody tr').forEach(tr => {
        const o = mailOrdnerDaten.find(x => x.pfad === tr.dataset.pfad);
        if (!o) return;
        o.abholen     = tr.querySelector('.mo-abholen').checked ? 1 : 0;
        o.ins_wissen  = tr.querySelector('.mo-wissen').checked ? 1 : 0;
        o.stil_lernen = tr.querySelector('.mo-stil').checked ? 1 : 0;
        o.rekursiv    = tr.querySelector('.mo-rekursiv').checked ? 1 : 0;
    });
}

function mailOrdnerSchliessen() {
    document.getElementById('mail-ordner-modal').style.display = 'none';
}

async function mailOrdnerSpeichern() {
    // Erst die sichtbaren Haken uebernehmen, dann ALLE Ordner senden — sonst wuerde
    // eine aktive Suche die Auswahl der gerade ausgeblendeten Ordner loeschen.
    mailOrdnerAuswahlUebernehmen();
    const ordner = mailOrdnerDaten
        .filter(o => o.abholen || o.ins_wissen || o.stil_lernen)
        .map(o => ({
            pfad: o.pfad,
            abholen: o.abholen ? 1 : 0,
            ins_wissen: o.ins_wissen ? 1 : 0,
            stil_lernen: o.stil_lernen ? 1 : 0,
            rekursiv: o.rekursiv ? 1 : 0,
        }));

    // Vor dem Speichern zeigen, WAS beim nächsten Abruf passiert — inklusive der
    // Unterordner, die „+Unter" stillschweigend mitzieht. Genau die haben aus
    // einer scheinbar kleinen Auswahl 1898 Mails gemacht.
    const trenner = '/';
    const abholPfade = new Set();
    ordner.filter(o => o.abholen).forEach(o => {
        abholPfade.add(o.pfad);
        if (!o.rekursiv) return;
        mailOrdnerDaten.forEach(k => {
            if (k.pfad !== o.pfad && k.pfad.startsWith(o.pfad + trenner)) abholPfade.add(k.pfad);
        });
    });
    const mails = mailOrdnerDaten
        .filter(o => abholPfade.has(o.pfad))
        .reduce((s, o) => s + (parseInt(o.anzahl) || 0), 0);

    if (abholPfade.size === 0) {
        if (!confirm('Kein Ordner zum Abholen ausgewählt.\n\n'
                   + 'Beim nächsten Abruf wird nichts geholt. Speichern?')) return;
    } else if (!confirm('Zum Abholen ausgewählt: ' + abholPfade.size + ' Ordner'
               + (abholPfade.size > ordner.filter(o => o.abholen).length
                    ? ' (inkl. Unterordner)' : '')
               + '\nEnthalten insgesamt etwa ' + mails.toLocaleString('de-DE') + ' Mails.\n\n'
               + 'Diese werden beim nächsten Abruf ins Werkzeug geholt. Speichern?')) {
        return;
    }

    try {
        const r = await fetch('/api/v1/mail/ordner-auswahl', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ konto_id: mailOrdnerKontoId, ordner: ordner }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Fehler');
        alert(j.message || 'Gespeichert.');
        mailOrdnerSchliessen();
    } catch (e) {
        alert('Fehler: ' + e.message);
    }
}

async function mailKontoSpeichern() {
    const form = document.getElementById('mail-konto-form');
    const fd = new FormData(form);
    const daten = {};
    for (const [k, v] of fd.entries()) daten[k] = v;
    daten.aktiv = form.querySelector('[name=aktiv]').checked ? 1 : 0;
    daten.auto_antwort_aktiv = form.querySelector('[name=auto_antwort_aktiv]').checked ? 1 : 0;
    if (daten.id === '') delete daten.id;

    try {
        const r = await fetch('/api/v1/mail/konto-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(daten),
        });
        const j = await r.json();
        if (!j.success) {
            const fehler = document.getElementById('mail-konto-fehler');
            fehler.textContent = j.error || j.message || 'Speichern fehlgeschlagen.';
            fehler.style.display = 'block';
            return;
        }
        mailKontoSchliessen();
        await mailKontenLaden();
    } catch (e) {
        const fehler = document.getElementById('mail-konto-fehler');
        fehler.textContent = 'Verbindungsfehler: ' + e.message;
        fehler.style.display = 'block';
    }
}

async function mailKontoLoeschen(id, name) {
    if (!confirm('Konto „' + name + '" wirklich löschen? Empfangene Mails bleiben in der DB, werden aber nicht mehr abgerufen.')) return;
    try {
        const r = await fetch('/api/v1/mail/konto-loeschen', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id }),
        });
        const j = await r.json();
        if (!j.success) { alert(j.error || 'Fehler.'); return; }
        await mailKontenLaden();
    } catch (e) { alert('Verbindungsfehler: ' + e.message); }
}

async function mailKontoTesten(id, typ) {
    const box = document.getElementById('mail-konto-test-ergebnis');
    box.style.display = 'block';
    box.style.background = '#f1f5f9';
    box.style.color = '#475569';
    box.textContent = 'Teste ' + typ.toUpperCase() + ' …';

    try {
        const r = await fetch('/api/v1/mail/konto-test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id, typ }),
        });
        const j = await r.json();
        if (j.success && j.data.ok) {
            box.style.background = '#dcfce7';
            box.style.color = '#15803d';
            box.textContent = '✓ ' + (j.data.meldung || 'OK');
        } else {
            box.style.background = '#fee2e2';
            box.style.color = '#b91c1c';
            box.textContent = '✗ ' + (j.data?.fehler || j.error || 'Fehler');
        }
    } catch (e) {
        box.style.background = '#fee2e2';
        box.style.color = '#b91c1c';
        box.textContent = '✗ Verbindungsfehler: ' + e.message;
    }
    setTimeout(() => { box.style.display = 'none'; }, 15000);
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
}

// Init beim Laden
mailKontenLaden();
</script>
