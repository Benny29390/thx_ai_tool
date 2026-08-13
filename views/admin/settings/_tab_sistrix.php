<?php
/** Sistrix-Tab — API-Key, Wochenkontingent, Status. */
use Services\SistrixService;
use Core\Database;

require_once SERVICES_PATH . '/SistrixService.php';
$svc = new SistrixService(Database::getInstance());
$status = $svc->wochenStatus();
?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <section>
        <div class="settings-card">
            <h2>API-Key</h2>
            <p class="settings-card-sub">
                Aus dem Sistrix-Dashboard → Account → API. Wird verwendet für
                <code>domain.sichtbarkeitsindex</code>, <code>…overview</code> und <code>links.overview</code>.
            </p>
            <form id="form-sistrix-key"
                  onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Sistrix-Key gespeichert', reloadOnSuccess: true });">
                <div class="settings-field">
                    <label for="sistrix_api_key">
                        Sistrix API Key
                        <?php if ($isConfigured('sistrix_api_key')): ?>
                            <span class="key-status ja">Gesetzt</span>
                        <?php else: ?>
                            <span class="key-status nein">Nicht gesetzt</span>
                        <?php endif; ?>
                    </label>
                    <input type="password" id="sistrix_api_key" name="sistrix_api_key" autocomplete="new-password"
                           placeholder="<?= $isConfigured('sistrix_api_key') ? 'Neuen Key eingeben zum Ersetzen…' : 'API-Key aus dem Sistrix-Dashboard' ?>">
                    <p class="field-hint">Leeres Feld = aktuellen Key behalten. Komplett entfernen über den Button unten.</p>
                </div>
                <div class="settings-actions">
                    <button type="submit" class="thx-btn thx-btn-primary">Key speichern</button>
                    <button type="button" class="thx-btn thx-btn-secondary"
                            <?= !$isConfigured('sistrix_api_key') ? 'disabled' : '' ?>
                            onclick="testeSistrix()">
                        Verbindung testen
                    </button>
                    <button type="button" class="thx-btn thx-btn-danger"
                            <?= !$isConfigured('sistrix_api_key') ? 'disabled' : '' ?>
                            onclick="entferneSistrixKey()">
                        Key entfernen
                    </button>
                    <span id="sistrix-test-status" class="status-msg muted"></span>
                </div>
                <pre id="sistrix-test-output"
                     style="display:none;margin-top:14px;background:var(--slate-50, #f8fafc);
                            border:1px solid var(--slate-200,#e2e8f0);border-radius:6px;
                            padding:10px;font-size: var(--d-fs-xs);max-height:240px;overflow:auto;"></pre>
            </form>
        </div>

        <div class="settings-card">
            <h2>Wochenkontingent</h2>
            <p class="settings-card-sub">
                Credits pro Woche. Sistrix-Standard ist 20.000 für die meisten Pläne. Wird montags zurückgesetzt.
            </p>
            <form id="form-sistrix-kontingent" onsubmit="event.preventDefault(); SettingsSave(this);">
                <div class="settings-field" style="max-width:240px;">
                    <label for="sistrix_wochenkontingent">Credits pro Woche</label>
                    <input type="number" id="sistrix_wochenkontingent" name="sistrix_wochenkontingent"
                           value="<?= (int)$status['wochenkontingent'] ?>" min="1" step="100">
                </div>
                <div class="settings-actions">
                    <button type="submit" class="thx-btn thx-btn-primary">Speichern</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Credits-Stand manuell korrigieren</h2>
            <p class="settings-card-sub">
                Wenn die im Sistrix-Webinterface angezeigten verbleibenden Credits vom System-Stand abweichen
                (z.B. weil Sistrix außerhalb dieses Tools genutzt wurde), kannst Du den verbleibenden Wert
                hier setzen. Wird automatisch am nächsten Montag 00:00 zurückgesetzt.
            </p>
            <form id="form-sistrix-korrektur"
                  onsubmit="event.preventDefault(); SettingsSave(this, { successMsg: 'Credits-Korrektur gespeichert', reloadOnSuccess: true });">
                <div class="settings-field" style="max-width:280px;">
                    <label for="sistrix_credits_korrektur">Verbleibende Credits laut Sistrix-Dashboard</label>
                    <input type="number" id="sistrix_credits_korrektur" name="sistrix_credits_korrektur"
                           value="<?= htmlspecialchars((string)\Core\Settings::get('sistrix_credits_korrektur', '')) ?>"
                           min="0" placeholder="z.B. 17500">
                </div>
                <div class="settings-actions">
                    <button type="submit" class="thx-btn thx-btn-primary">Korrektur setzen</button>
                </div>
            </form>
        </div>
    </section>

    <aside>
        <div class="settings-card">
            <h2>Aktueller Wochenstatus</h2>
            <dl class="settings-status-grid">
                <dt>Konfiguriert</dt>
                <dd><strong><?= $status['konfiguriert'] ? 'Ja' : 'Nein' ?></strong></dd>

                <dt>Wochenstart</dt>
                <dd><?= htmlspecialchars($status['wochenstart']) ?></dd>

                <dt>Abfragen</dt>
                <dd><?= (int)$status['abfragen'] ?></dd>

                <dt>Credits verbraucht</dt>
                <dd><?= number_format($status['credits_verbraucht'], 0, ',', '.') ?></dd>

                <dt>Credits verbleibend</dt>
                <dd><strong><?= number_format($status['credits_verbleibend'], 0, ',', '.') ?></strong></dd>

                <dt>Wochenkontingent</dt>
                <dd><?= number_format($status['wochenkontingent'], 0, ',', '.') ?></dd>
            </dl>
        </div>

        <div class="settings-card">
            <h2>Credits pro Teil</h2>
            <dl class="settings-status-grid">
                <dt>SI · Sichtbarkeitsindex</dt><dd>1 Credit</dd>
                <dt>Alter · sichtbar seit</dt><dd>10 Credits</dd>
                <dt>DP · verlinkende Domains</dt><dd>25 Credits</dd>
                <dt>Alles · alle drei</dt><dd>36 Credits</dd>
            </dl>
            <p class="field-hint" style="margin-top:10px;">
                Caching: pro Domain &amp; Teil max. 1 API-Call pro Tag. Bei Wiederholung kommt der Wert
                aus dem Snapshot.
            </p>
        </div>
    </aside>
</div>

<script>
async function testeSistrix() {
    const status = document.getElementById('sistrix-test-status');
    const out = document.getElementById('sistrix-test-output');
    status.textContent = 'Teste Verbindung…';
    status.className = 'status-msg muted';
    out.style.display = 'none';
    try {
        const resp = await App.request('GET', '/lam/sistrix-status', null);
        if (resp.success) {
            status.textContent = 'Verbindung OK';
            status.className = 'status-msg ok';
            out.style.display = 'block';
            out.textContent = JSON.stringify(resp.data, null, 2);
        } else {
            status.textContent = resp.message || 'Fehler';
            status.className = 'status-msg err';
        }
    } catch (e) {
        status.textContent = e.message || 'Verbindungsfehler';
        status.className = 'status-msg err';
    }
}

async function entferneSistrixKey() {
    if (!confirm('Sistrix-Key wirklich entfernen?')) return;
    try {
        await App.post('/admin/settings', { sistrix_api_key: '__clear__' });
        App.showNotification('Sistrix-Key entfernt', 'success');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
}
</script>
