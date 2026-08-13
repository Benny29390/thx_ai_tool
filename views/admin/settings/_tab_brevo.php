<?php
/** Brevo-Tab — API-Key + Webhook-Secret fuer CRM-Anbindung. */
?>
<div class="settings-card">
    <h2>Brevo-API</h2>
    <p class="settings-card-sub">
        Verbindet das CRM-Modul mit dem Brevo-Versand. <strong>Wir lesen</strong> Kontakte/Listen
        und <strong>pushen</strong> Stammdaten + Listen-Mitgliedschaften (CRM gewinnt, einseitige Synchronisation).
        Brevo selbst wird im CRM nicht gepflegt — es bleibt die Versand-Engine.
        <br><br>
        API-Key erstellst Du in Brevo unter
        <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener">
            Einstellungen → SMTP &amp; API → API-Keys</a>.
    </p>

    <form id="form-brevo" onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Brevo-Key gespeichert' });">
        <div class="settings-row">
            <div class="settings-field">
                <label for="brevo_api_key">
                    API-Key (v3)
                    <?php if ($isConfigured('brevo_api_key')): ?>
                        <span class="key-status ja">Gesetzt</span>
                    <?php else: ?>
                        <span class="key-status nein">Nicht gesetzt</span>
                    <?php endif; ?>
                </label>
                <input type="password" id="brevo_api_key" name="brevo_api_key" autocomplete="new-password"
                       placeholder="<?= $isConfigured('brevo_api_key') ? 'Neu eingeben zum Ersetzen…' : 'xkeysib-…' ?>">
                <p class="field-hint">
                    Verschluesselte Speicherung (AES-256-GCM). Leer lassen = unveraendert.
                </p>
            </div>
        </div>

        <div class="settings-row">
            <div class="settings-field">
                <label for="brevo_webhook_secret">
                    Webhook-Secret (optional, fuer eingehende Brevo-Events)
                    <?php if ($isConfigured('brevo_webhook_secret')): ?>
                        <span class="key-status ja">Gesetzt</span>
                    <?php endif; ?>
                </label>
                <input type="password" id="brevo_webhook_secret" name="brevo_webhook_secret" autocomplete="new-password"
                       placeholder="<?= $isConfigured('brevo_webhook_secret') ? 'Neu eingeben zum Ersetzen…' : 'wird genutzt um Webhook-Calls zu authentifizieren' ?>">
                <p class="field-hint">
                    Wird beim Brevo-Webhook-Setup als HMAC-Secret verwendet. Kann frei gewaehlt werden
                    (zufaelliger String). Bei leerem Feld wird die Webhook-Signatur nicht geprueft (nur Phase 1 OK).
                </p>
            </div>
        </div>

        <div class="settings-actions">
            <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
            <button type="button" class="thx-btn thx-btn-secondary" id="brevo-test-btn" onclick="testeBrevo()">
                Verbindung testen
            </button>
            <span id="brevo-test-status" class="status-msg muted"></span>
        </div>
    </form>

    <div id="brevo-test-result" style="margin-top:14px;"></div>
</div>

<div class="settings-card">
    <h2>IP-Freigabe in Brevo (falls eingeschraenkt)</h2>
    <p class="settings-card-sub">
        Wenn Du den API-Key in Brevo auf bestimmte IPs einschraenkst, trage folgende ausgehende Server-IP(s) ein:
    </p>
    <div class="settings-status-grid" style="grid-template-columns: 140px 1fr;">
        <dt>IPv4</dt>
        <dd><code style="background:var(--slate-100);padding:3px 8px;border-radius:4px;">46.225.85.168</code></dd>
        <dt>IPv6</dt>
        <dd><code style="background:var(--slate-100);padding:3px 8px;border-radius:4px;">2a01:4f8:1c19:9f7f::1</code></dd>
        <dt>Webhook-URL</dt>
        <dd><code style="background:var(--slate-100);padding:3px 8px;border-radius:4px;"><?= htmlspecialchars(\Core\Brand::url('/api/v1/crm/brevo/webhook')) ?></code>
            <span class="status-msg muted" style="margin-left:6px;">(wird in CRM-Phase 1 Schritt 7 implementiert)</span></dd>
    </div>
</div>

<script>
async function testeBrevo() {
    const btn = document.getElementById('brevo-test-btn');
    const status = document.getElementById('brevo-test-status');
    const out = document.getElementById('brevo-test-result');
    const keyInput = document.getElementById('brevo_api_key');
    btn.disabled = true;
    status.textContent = 'Verbinde mit Brevo…';
    status.className = 'status-msg muted';
    out.innerHTML = '';
    try {
        const body = {};
        if (keyInput.value.trim() !== '') body.api_key = keyInput.value.trim();
        const resp = await App.request('POST', '/admin/brevo-test', body);
        if (resp.success) {
            const acc = resp.data.account || {};
            const lists = resp.data.lists || [];
            status.textContent = 'OK — Account: ' + (acc.email || acc.companyName || 'verbunden');
            status.className = 'status-msg ok';
            let html = '<div style="background:var(--slate-50,#f8fafc);border:1px solid var(--slate-200,#e2e8f0);border-radius:8px;padding:12px;">';
            html += '<strong style="font-size: var(--d-fs-sm);">Account-Info:</strong><br>';
            html += '<dl class="settings-status-grid" style="margin-top:6px;grid-template-columns:140px 1fr;">';
            if (acc.companyName) html += '<dt>Firma</dt><dd>' + esc(acc.companyName) + '</dd>';
            if (acc.email) html += '<dt>E-Mail</dt><dd>' + esc(acc.email) + '</dd>';
            if (acc.plan && acc.plan[0]) {
                html += '<dt>Plan</dt><dd>' + esc(acc.plan[0].type || '?') + ' — ' + (acc.plan[0].credits || '?') + ' Credits</dd>';
            }
            if (acc.relay && acc.relay.enabled) html += '<dt>Transaktionsmail</dt><dd>aktiv</dd>';
            html += '</dl>';
            if (lists.length) {
                html += '<div style="margin-top:14px;"><strong style="font-size: var(--d-fs-sm);">' + lists.length + ' Liste(n) gefunden:</strong>';
                html += '<div style="max-height:280px;overflow-y:auto;background:#fff;border-radius:4px;border:1px solid var(--slate-200);margin-top:6px;">';
                lists.forEach(l => {
                    html += '<div style="padding:5px 10px;border-bottom:1px solid var(--slate-100);font-size: var(--d-fs-xs);display:flex;justify-content:space-between;gap:8px;">'
                          + '<span><code>' + l.id + '</code> · ' + esc(l.name || '?') + '</span>'
                          + '<span style="color:var(--slate-500);">' + (l.totalSubscribers || 0) + ' Abos</span>'
                          + '</div>';
                });
                html += '</div></div>';
            } else {
                html += '<div style="margin-top:10px;color:var(--slate-500);font-size:var(--d-fs-xs);">Keine Listen gefunden (oder Berechtigung fehlt).</div>';
            }
            html += '</div>';
            out.innerHTML = html;
            App.showNotification('Brevo-Verbindung erfolgreich', 'success');
        } else {
            status.textContent = resp.message || 'Fehler';
            status.className = 'status-msg err';
        }
    } catch (e) {
        status.textContent = e.message || 'Verbindungsfehler';
        status.className = 'status-msg err';
    }
    btn.disabled = false;
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
    })[c]);
}
</script>
