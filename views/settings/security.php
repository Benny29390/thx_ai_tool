<?php
$isAdminViewer = \Core\Auth::isAdmin();
$asanaLinked = !empty($currentUser['asana_user_gid']);
?>
<div class="thx-page-header">
    <div>
        <a href="/" style="font-size:var(--d-fs-xs);color:var(--slate-500);text-decoration:none;">‹ Zurück zum Dashboard</a>
        <h1 class="thx-page-title" style="margin-top:4px;">Mein Konto</h1>
        <p class="thx-page-subtitle">Zwei-Faktor-Authentifizierung, Profilangaben und externe Verknüpfungen.</p>
    </div>
</div>

<div class="sec-page">

    <!-- ===== 2FA ===== -->
    <div class="thx-card">
        <div class="sec-card-head">
            <div>
                <h3 class="thx-card-title" style="display:flex;align-items:center;gap:8px;">
                    <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:20px;">security</span>
                    Zwei-Faktor-Authentifizierung
                </h3>
                <div class="thx-card-sub">Schütze Deinen Account mit einem zusätzlichen Sicherheitsfaktor.</div>
            </div>
            <span class="sec-status-pill <?= $has2FA ? 'is-on' : 'is-off' ?>">
                <?= $has2FA ? 'Aktiv' : 'Nicht aktiviert' ?>
            </span>
        </div>

        <?php if ($has2FA): ?>
            <div class="sec-banner is-success">
                <span class="material-symbols-rounded">verified_user</span>
                <div>
                    <strong>2FA ist aktiviert</strong>
                    <p>Dein Account ist durch Zwei-Faktor-Authentifizierung geschützt.</p>
                </div>
            </div>

            <div class="sec-info-row">
                <span>Verbleibende Backup-Codes</span>
                <strong><?= $backupCodesRemaining ?> von 10</strong>
            </div>

            <?php if ($backupCodesRemaining <= 3): ?>
                <div class="sec-banner is-warning">
                    <span class="material-symbols-rounded">warning</span>
                    <div>
                        <strong>Wenige Backup-Codes übrig</strong>
                        <p>Deaktiviere und reaktiviere 2FA, um neue Codes zu generieren.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sec-actions">
                <button type="button" class="thx-btn thx-btn-danger" onclick="showDisable2FAModal()">
                    <span class="material-symbols-rounded">lock_open</span>
                    2FA deaktivieren
                </button>
            </div>
        <?php else: ?>
            <div id="setup-intro">
                <div class="sec-banner is-warning">
                    <span class="material-symbols-rounded">shield</span>
                    <div>
                        <strong>2FA nicht aktiviert</strong>
                        <p>Aktiviere 2FA, um Deinen Account besser zu schützen.</p>
                    </div>
                </div>

                <ul class="sec-feature-list">
                    <li><span class="material-symbols-rounded">check_circle</span>Verhindert unbefugten Zugriff, selbst wenn Dein Passwort bekannt ist</li>
                    <li><span class="material-symbols-rounded">check_circle</span>Funktioniert mit Apps wie Google Authenticator, Authy oder 1Password</li>
                    <li><span class="material-symbols-rounded">check_circle</span>10 Backup-Codes für den Notfall</li>
                </ul>

                <div class="sec-actions">
                    <button type="button" class="thx-btn thx-btn-primary" onclick="startSetup()">
                        <span class="material-symbols-rounded">security</span>
                        2FA aktivieren
                    </button>
                </div>
            </div>

            <!-- Setup Step 1: QR Code -->
            <div id="setup-step1" style="display: none;">
                <h4 class="sec-step-title">Schritt 1: QR-Code scannen</h4>
                <p class="sec-step-sub">Scanne den QR-Code mit Deiner Authenticator-App.</p>

                <div class="sec-qr-container">
                    <div id="qr-code" class="sec-qr-image"></div>
                </div>

                <details class="sec-manual">
                    <summary>QR-Code funktioniert nicht? Secret manuell eingeben</summary>
                    <div class="sec-manual-body">
                        <label>Secret Key</label>
                        <code id="secret-key" class="sec-secret"></code>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="copySecret()">
                            <span class="material-symbols-rounded">content_copy</span> Kopieren
                        </button>
                    </div>
                </details>

                <div class="sec-actions">
                    <button type="button" class="thx-btn thx-btn-secondary" onclick="cancelSetup()">Abbrechen</button>
                    <button type="button" class="thx-btn thx-btn-primary" onclick="showStep2()">Weiter</button>
                </div>
            </div>

            <!-- Setup Step 2: Verify -->
            <div id="setup-step2" style="display: none;">
                <h4 class="sec-step-title">Schritt 2: Code verifizieren</h4>
                <p class="sec-step-sub">Gib den 6-stelligen Code aus Deiner Authenticator-App ein.</p>

                <div style="display:flex;justify-content:center;margin:24px 0;">
                    <input type="text" id="verify-code" class="sec-code-input"
                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           placeholder="000000" autofocus>
                </div>

                <div class="sec-actions">
                    <button type="button" class="thx-btn thx-btn-secondary" onclick="showStep1()">Zurück</button>
                    <button type="button" class="thx-btn thx-btn-primary" onclick="confirmSetup()">Bestätigen</button>
                </div>
            </div>

            <!-- Setup Step 3: Backup Codes -->
            <div id="setup-step3" style="display: none;">
                <div class="sec-banner is-success">
                    <span class="material-symbols-rounded">check_circle</span>
                    <div>
                        <strong>2FA erfolgreich aktiviert!</strong>
                        <p>Speichere diese Backup-Codes sicher ab. Du kannst sie verwenden, wenn Du keinen Zugriff auf Deine Authenticator-App hast.</p>
                    </div>
                </div>

                <div class="sec-backup-wrap">
                    <div class="sec-backup-grid" id="backup-codes"></div>
                    <div class="sec-backup-actions">
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="copyBackupCodes()">
                            <span class="material-symbols-rounded">content_copy</span> Kopieren
                        </button>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="downloadBackupCodes()">
                            <span class="material-symbols-rounded">download</span> Herunterladen
                        </button>
                    </div>
                </div>

                <div class="sec-banner is-warning">
                    <span class="material-symbols-rounded">warning</span>
                    <div>
                        <strong>Wichtig:</strong>
                        <p>Diese Codes werden nur einmal angezeigt. Speichere sie jetzt sicher ab.</p>
                    </div>
                </div>

                <div class="sec-actions">
                    <button type="button" class="thx-btn thx-btn-primary" onclick="finishSetup()">Fertig</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Profil ===== -->
    <div class="thx-card">
        <div class="sec-card-head">
            <div>
                <h3 class="thx-card-title" style="display:flex;align-items:center;gap:8px;">
                    <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:20px;">badge</span>
                    Profil
                </h3>
                <div class="thx-card-sub">Anzeigename und Kürzel — wird z.B. in Chat-Listen gezeigt, wenn andere Deine Chats sehen.</div>
            </div>
        </div>

        <div class="sec-profile-grid">
            <div class="thx-form-field" style="margin:0;">
                <label for="profile-name">Name</label>
                <input type="text" id="profile-name" class="thx-input"
                       value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
            </div>
            <div class="thx-form-field" style="margin:0;">
                <label for="profile-abbreviation">
                    Kürzel
                    <?php if ($isAdminViewer): ?>
                        <small style="font-weight:400;color:var(--slate-400);">(max. 5 Zeichen)</small>
                    <?php else: ?>
                        <small style="font-weight:400;color:var(--slate-400);">— wird vom Admin vergeben</small>
                    <?php endif; ?>
                </label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="sec-abbr-preview" id="profile-abbr-preview">
                        <?= htmlspecialchars($currentUser['abbreviation'] ?? '?') ?>
                    </span>
                    <input type="text" id="profile-abbreviation" class="thx-input" maxlength="5" pattern="[A-Za-z0-9]+"
                           value="<?= htmlspecialchars($currentUser['abbreviation'] ?? '') ?>"
                           <?= $isAdminViewer ? '' : 'readonly disabled' ?>
                           <?php if ($isAdminViewer): ?>
                           oninput="this.value=this.value.toUpperCase();document.getElementById('profile-abbr-preview').textContent=this.value || '?'"
                           <?php endif; ?>
                           style="text-transform:uppercase;<?= $isAdminViewer ? '' : 'background:var(--slate-50);color:var(--slate-500);cursor:not-allowed;' ?>">
                </div>
            </div>
        </div>

        <div class="sec-actions" style="margin-top:14px;">
            <button type="button" class="thx-btn thx-btn-primary" onclick="saveProfile()" id="profile-save-btn">
                <span class="material-symbols-rounded">save</span> Speichern
            </button>
            <span id="profile-save-status" style="font-size:var(--d-fs-xs);color:var(--slate-500);align-self:center;"></span>
        </div>
    </div>

    <!-- ===== Asana ===== -->
    <div class="thx-card">
        <div class="sec-card-head">
            <div>
                <h3 class="thx-card-title" style="display:flex;align-items:center;gap:8px;">
                    <span class="material-symbols-rounded" style="color:#f97316;font-size:20px;">task_alt</span>
                    Asana-Konto verknüpfen
                </h3>
                <div class="thx-card-sub">Damit der Chat „meine offenen Aufgaben" korrekt zuordnen kann, brauchen wir Deine Asana-User-ID.</div>
            </div>
            <span class="sec-status-pill <?= $asanaLinked ? 'is-on' : 'is-off' ?>" id="asana-link-status-badge">
                <?= $asanaLinked ? 'Verknüpft' : 'Nicht verknüpft' ?>
            </span>
        </div>

        <?php if (empty($asanaConfigured)): ?>
            <div class="sec-banner is-warning">
                <span class="material-symbols-rounded">warning</span>
                <div>
                    <strong>Asana ist im System noch nicht konfiguriert</strong>
                    <p>Ein Admin muss zuerst unter <a href="/admin/settings" style="color:var(--thoxan-700);">Einstellungen</a> einen Personal Access Token hinterlegen.</p>
                </div>
            </div>
        <?php else: ?>
            <div id="asana-link-card">
                <?php if ($asanaLinked): ?>
                    <div class="sec-banner is-success">
                        <span class="material-symbols-rounded">check_circle</span>
                        <div>
                            <strong>Verknüpft</strong>
                            <p id="asana-current-info">Asana-User-GID: <code><?= htmlspecialchars($currentUser['asana_user_gid']) ?></code></p>
                        </div>
                    </div>
                    <div class="sec-actions">
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="asanaShowPicker()">
                            <span class="material-symbols-rounded">person_search</span>
                            Anderen Asana-User wählen
                        </button>
                        <button type="button" class="thx-btn thx-btn-danger thx-btn-small" onclick="asanaUnlink()">
                            <span class="material-symbols-rounded">link_off</span>
                            Verknüpfung trennen
                        </button>
                    </div>
                <?php else: ?>
                    <p style="color:var(--slate-600);font-size:var(--d-fs-xs);margin:0 0 12px;">
                        Wähle Deinen Asana-User aus der Liste — wir versuchen Dich anhand Deiner E-Mail
                        (<strong><?= htmlspecialchars($currentUser['email'] ?? '') ?></strong>)
                        automatisch vorzuselektieren.
                    </p>
                <?php endif; ?>

                <div id="asana-picker" style="margin-top:8px;<?= $asanaLinked ? 'display:none;' : '' ?>">
                    <div id="asana-picker-loading" class="sec-loading">
                        <span class="sec-spin"></span> Lade Asana-User…
                    </div>
                    <div id="asana-picker-content" style="display:none;">
                        <input type="text" id="asana-search" class="thx-input" placeholder="Nach Name oder E-Mail suchen…"
                               oninput="asanaFilterUsers()" style="width:100%;margin-bottom:8px;">
                        <div id="asana-user-list" class="sec-user-list"></div>
                        <details style="margin-top:10px;">
                            <summary style="color:var(--slate-500);font-size:var(--d-fs-xs);cursor:pointer;">GID manuell eingeben</summary>
                            <div style="margin-top:6px;display:flex;gap:6px;align-items:center;">
                                <input type="text" id="asana-manual-gid" class="thx-input" placeholder="z.B. 1234567890" pattern="[0-9]+" style="flex:1;">
                                <button type="button" class="thx-btn thx-btn-primary thx-btn-small" onclick="asanaLinkManual()">Speichern</button>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
            <div id="asana-link-status" style="margin-top:8px;font-size:var(--d-fs-xs);"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: 2FA deaktivieren -->
<div class="thx-modal-backdrop" id="disable-modal" style="display:none;"
     onclick="if(event.target===this)hideDisable2FAModal()">
    <div class="thx-modal" style="max-width:420px;">
        <div class="thx-modal-header">
            <h3 class="thx-modal-title">2FA deaktivieren</h3>
            <button class="thx-modal-close" onclick="hideDisable2FAModal()">&times;</button>
        </div>
        <div class="thx-modal-body" style="padding:20px;">
            <p style="margin:0 0 12px;color:var(--slate-600);font-size:var(--d-fs-sm);">
                Gib Dein Passwort ein, um 2FA zu deaktivieren.
            </p>

            <div class="thx-form-field">
                <label for="disable-password">Passwort</label>
                <input type="password" id="disable-password" class="thx-input" placeholder="Dein Passwort">
            </div>

            <div class="sec-banner is-warning" style="margin-bottom:0;">
                <span class="material-symbols-rounded">warning</span>
                <div>
                    <strong>Dein Account wird weniger sicher sein ohne 2FA.</strong>
                </div>
            </div>
        </div>
        <div class="thx-modal-footer" style="display:flex;gap:8px;justify-content:flex-end;padding:14px 20px;border-top:1px solid var(--slate-200);">
            <button type="button" class="thx-btn thx-btn-secondary" onclick="hideDisable2FAModal()">Abbrechen</button>
            <button type="button" class="thx-btn thx-btn-danger" onclick="disable2FA()">Deaktivieren</button>
        </div>
    </div>
</div>

<style>
/* ===== Security/Settings — leichte Anpassungen oberhalb der THX-Klassen ===== */
.sec-page {
    max-width: 760px;
    display: flex; flex-direction: column;
    gap: var(--d-gutter);
}
.sec-card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 12px; margin-bottom: 14px;
}
.sec-status-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px;
    font-size: var(--d-fs-xs); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em;
    white-space: nowrap; flex-shrink: 0;
}
.sec-status-pill.is-on  { background: var(--emerald-50); color: var(--emerald-700); border: 1px solid var(--emerald-200); }
.sec-status-pill.is-off { background: var(--amber-50);   color: var(--amber-700);   border: 1px solid var(--amber-200); }

.sec-banner {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 12px 14px;
    border-radius: var(--d-card-radius);
    margin-bottom: 14px;
}
.sec-banner .material-symbols-rounded { font-size: 22px; flex-shrink: 0; }
.sec-banner strong { display: block; margin-bottom: 2px; font-size: var(--d-fs-sm); color: var(--slate-800); }
.sec-banner p { margin: 0; font-size: var(--d-fs-xs); color: var(--slate-600); line-height: 1.45; }
.sec-banner.is-success {
    background: var(--emerald-50); border: 1px solid var(--emerald-200);
}
.sec-banner.is-success .material-symbols-rounded { color: var(--emerald-600); }
.sec-banner.is-warning {
    background: var(--amber-50); border: 1px solid var(--amber-200);
}
.sec-banner.is-warning .material-symbols-rounded { color: var(--amber-700); }

.sec-info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0;
    border-top: 1px solid var(--slate-100);
    border-bottom: 1px solid var(--slate-100);
    font-size: var(--d-fs-sm);
    color: var(--slate-700);
    margin-bottom: 14px;
}

.sec-actions {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-top: 14px;
}

.sec-feature-list {
    list-style: none; padding: 0; margin: 0 0 16px;
    display: flex; flex-direction: column; gap: 6px;
}
.sec-feature-list li {
    display: flex; align-items: center; gap: 8px;
    font-size: var(--d-fs-sm); color: var(--slate-700);
}
.sec-feature-list .material-symbols-rounded {
    color: var(--emerald-600); font-size: 18px;
}

.sec-step-title { margin: 0 0 4px; font-size: var(--d-fs-base); color: var(--slate-800); font-weight: 700; }
.sec-step-sub { margin: 0 0 14px; font-size: var(--d-fs-xs); color: var(--slate-500); }

.sec-qr-container {
    text-align: center; padding: 18px 0;
}
.sec-qr-image {
    display: inline-block;
    border: 1px solid var(--slate-200); border-radius: var(--d-card-radius);
    padding: 14px; background: #fff;
    min-width: 200px; min-height: 200px;
}
.sec-qr-image canvas, .sec-qr-image img { display: block; border-radius: 4px; }

.sec-manual {
    margin: 0 0 14px;
    font-size: var(--d-fs-xs); color: var(--slate-600);
}
.sec-manual summary { cursor: pointer; color: var(--slate-500); padding: 4px 0; }
.sec-manual-body {
    margin-top: 8px; padding: 10px 12px;
    background: var(--slate-50); border-radius: var(--d-control-radius);
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.sec-manual-body label { font-weight: 600; color: var(--slate-600); }
.sec-secret {
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
    background: #fff; padding: 4px 10px;
    border-radius: var(--d-control-radius);
    border: 1px solid var(--slate-200);
    letter-spacing: 0.08em; font-size: var(--d-fs-sm);
}

.sec-code-input {
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
    font-size: var(--d-fs-xl);
    text-align: center; letter-spacing: 0.4rem;
    padding: 14px 12px;
    width: 220px;
    border: 1px solid var(--slate-300);
    border-radius: var(--d-card-radius);
    background: #fff;
}
.sec-code-input:focus { outline: none; border-color: var(--thoxan-500); box-shadow: 0 0 0 3px rgba(0, 76, 155, 0.12); }

.sec-backup-wrap {
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    padding: 14px;
    margin-bottom: 14px;
}
.sec-backup-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 6px;
    font-family: ui-monospace, "JetBrains Mono", Consolas, monospace;
    font-size: var(--d-fs-sm);
    margin-bottom: 12px;
}
.sec-backup-grid span {
    background: #fff;
    padding: 6px 10px;
    border-radius: var(--d-control-radius);
    border: 1px solid var(--slate-200);
    text-align: center;
    color: var(--slate-700);
}
.sec-backup-actions { display: flex; gap: 6px; justify-content: center; }

.sec-profile-grid {
    display: grid; grid-template-columns: 2fr 1fr; gap: 12px;
    align-items: end;
}
@media (max-width: 640px) {
    .sec-profile-grid { grid-template-columns: 1fr; }
}
.sec-abbr-preview {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 28px; padding: 0 8px;
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-500));
    color: #fff; font-size: var(--d-fs-sm); font-weight: 700;
    border-radius: 14px; letter-spacing: 0.04em; flex-shrink: 0;
}

.sec-loading {
    color: var(--slate-500); font-size: var(--d-fs-xs);
    display: flex; align-items: center; gap: 6px;
    padding: 8px 0;
}
.sec-spin {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(0, 76, 155, 0.2);
    border-top-color: var(--thoxan-600);
    border-radius: 50%;
    animation: sec-spin 1s linear infinite;
}
@keyframes sec-spin { to { transform: rotate(360deg); } }

.sec-user-list {
    max-height: 320px; overflow-y: auto;
    border: 1px solid var(--slate-200);
    border-radius: var(--d-card-radius);
    background: #fff;
}
.sec-user-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--slate-100);
    cursor: pointer;
    transition: background 0.1s;
    background: #fff;
}
.sec-user-row:last-child { border-bottom: none; }
.sec-user-row:hover { background: var(--thoxan-50); }
.sec-user-row.is-recommended { background: linear-gradient(90deg, var(--thoxan-50), #fff); }
.sec-user-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--thoxan-700), var(--thoxan-500));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: var(--d-fs-sm);
    flex-shrink: 0;
}
.sec-user-info { flex: 1; min-width: 0; }
.sec-user-name { font-weight: 600; font-size: var(--d-fs-sm); color: var(--slate-800); }
.sec-user-email { font-size: var(--d-fs-xs); color: var(--slate-500); }
.sec-user-email code { font-size: 11px; color: var(--slate-500); }
.sec-user-badge {
    font-size: 10px; padding: 2px 7px; border-radius: 999px;
    background: var(--thoxan-600); color: #fff; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em;
    flex-shrink: 0;
}
.sec-user-badge.is-active { background: var(--emerald-600); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const _currentUserEmail = <?= json_encode($currentUser['email'] ?? '') ?>;
const _currentAsanaGid = <?= json_encode($currentUser['asana_user_gid'] ?? null) ?>;
let _asanaUsers = [];
let setupSecret = '';
let backupCodes = [];

async function saveProfile() {
    const btn = document.getElementById('profile-save-btn');
    const status = document.getElementById('profile-save-status');
    btn.disabled = true;
    status.textContent = 'Speichere…';
    status.style.color = 'var(--slate-500)';
    try {
        const payload = { name: document.getElementById('profile-name').value.trim() };
        const abbrInput = document.getElementById('profile-abbreviation');
        if (abbrInput && !abbrInput.disabled) {
            payload.abbreviation = abbrInput.value.trim().toUpperCase();
        }
        const r = await App.put('/me/profile', payload);
        if (r.success) {
            App.showNotification('Profil gespeichert', 'success');
            status.textContent = 'Gespeichert';
            status.style.color = 'var(--emerald-600)';
        } else {
            status.textContent = r.message || 'Fehler';
            status.style.color = 'var(--rose-600)';
        }
    } catch (e) {
        status.textContent = e.message || 'Fehler';
        status.style.color = 'var(--rose-600)';
    }
    btn.disabled = false;
}

async function asanaLoadUsers() {
    const loading = document.getElementById('asana-picker-loading');
    const content = document.getElementById('asana-picker-content');
    try {
        const r = await App.get('/me/asana-link?with_users=1');
        if (!r.success) {
            loading.innerHTML = '<span style="color:var(--rose-600);">' + esc(r.message || 'Fehler') + '</span>';
            return;
        }
        _asanaUsers = r.data?.users || [];
        if (r.data?.users_error) {
            loading.innerHTML = '<span style="color:var(--rose-600);">Fehler: ' + esc(r.data.users_error) + '</span>';
            return;
        }
        if (_asanaUsers.length === 0) {
            loading.textContent = 'Keine Asana-User im Workspace gefunden.';
            return;
        }
        loading.style.display = 'none';
        content.style.display = '';
        asanaRenderUserList();
    } catch (e) {
        loading.innerHTML = '<span style="color:var(--rose-600);">' + esc(e.message || 'Verbindungsfehler') + '</span>';
    }
}

function asanaRenderUserList() {
    const list = document.getElementById('asana-user-list');
    const search = (document.getElementById('asana-search')?.value || '').toLowerCase().trim();
    const myEmail = (_currentUserEmail || '').toLowerCase();

    let users = _asanaUsers;
    if (search) {
        users = users.filter(u =>
            (u.name || '').toLowerCase().includes(search) ||
            (u.email || '').toLowerCase().includes(search)
        );
    }

    users.sort((a, b) => {
        const aIsMine = (a.email || '').toLowerCase() === myEmail ? -1 : 0;
        const bIsMine = (b.email || '').toLowerCase() === myEmail ? -1 : 0;
        if (aIsMine !== bIsMine) return aIsMine - bIsMine;
        return (a.name || '').localeCompare(b.name || '', 'de');
    });

    if (users.length === 0) {
        list.innerHTML = '<div style="padding:14px;text-align:center;color:var(--slate-400);font-size:var(--d-fs-xs);">Keine Treffer</div>';
        return;
    }

    list.innerHTML = users.map(u => {
        const isMatch = (u.email || '').toLowerCase() === myEmail;
        const isCurrent = String(u.gid) === String(_currentAsanaGid);
        const initial = ((u.name || u.email || '?').trim()[0] || '?').toUpperCase();
        return `
            <div class="sec-user-row ${isMatch ? 'is-recommended' : ''}" onclick="asanaPickUser('${esc(u.gid)}', '${esc(u.name || '').replace(/'/g, '&#39;')}')">
                <div class="sec-user-avatar">${esc(initial)}</div>
                <div class="sec-user-info">
                    <div class="sec-user-name">${esc(u.name || '?')}</div>
                    <div class="sec-user-email">${esc(u.email || '')} <span style="color:var(--slate-300);">·</span> <code>${esc(u.gid)}</code></div>
                </div>
                ${isMatch ? '<span class="sec-user-badge">Vermutlich Du</span>' : ''}
                ${isCurrent ? '<span class="sec-user-badge is-active">Aktiv</span>' : ''}
            </div>
        `;
    }).join('');
}

function asanaFilterUsers() { asanaRenderUserList(); }

async function asanaPickUser(gid, name) {
    if (!gid) return;
    const status = document.getElementById('asana-link-status');
    status.style.color = 'var(--slate-500)';
    status.textContent = 'Speichere…';
    try {
        const r = await App.post('/me/asana-link', { gid });
        if (r.success) {
            App.showNotification('Verknüpft als ' + (name || gid), 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            status.textContent = r.message || 'Fehler';
            status.style.color = 'var(--rose-600)';
        }
    } catch (e) {
        status.textContent = e.message || 'Fehler';
        status.style.color = 'var(--rose-600)';
    }
}

async function asanaLinkManual() {
    const gid = document.getElementById('asana-manual-gid').value.trim();
    if (!gid || !/^\d+$/.test(gid)) {
        App.showNotification('Bitte gültige numerische GID eingeben', 'error');
        return;
    }
    asanaPickUser(gid, 'GID ' + gid);
}

async function asanaUnlink() {
    if (!confirm('Asana-Verknüpfung wirklich trennen?')) return;
    try {
        await App.delete('/me/asana-link');
        App.showNotification('Getrennt', 'success');
        setTimeout(() => location.reload(), 400);
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
    }
}

function asanaShowPicker() {
    document.getElementById('asana-picker').style.display = '';
    if (_asanaUsers.length === 0) asanaLoadUsers();
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

// Auto-Load wenn Picker initial sichtbar (= noch nicht verknüpft)
if (document.getElementById('asana-picker') && document.getElementById('asana-picker').style.display !== 'none') {
    asanaLoadUsers();
}

/* ===== 2FA Setup ===== */
async function startSetup() {
    try {
        const response = await App.post('/api/v1/auth/2fa/setup');
        setupSecret = response.data.secret;

        const qrContainer = document.getElementById('qr-code');
        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
            text: response.data.otpauth_url,
            width: 180,
            height: 180,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });

        document.getElementById('secret-key').textContent = setupSecret;
        document.getElementById('setup-intro').style.display = 'none';
        document.getElementById('setup-step1').style.display = 'block';
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

function showStep1() {
    document.getElementById('setup-step2').style.display = 'none';
    document.getElementById('setup-step1').style.display = 'block';
}

function showStep2() {
    document.getElementById('setup-step1').style.display = 'none';
    document.getElementById('setup-step2').style.display = 'block';
    document.getElementById('verify-code').focus();
}

function cancelSetup() {
    setupSecret = '';
    document.getElementById('setup-step1').style.display = 'none';
    document.getElementById('setup-step2').style.display = 'none';
    document.getElementById('setup-intro').style.display = 'block';
}

async function confirmSetup() {
    const code = document.getElementById('verify-code').value;
    if (!code || code.length !== 6) {
        App.showNotification('Bitte 6-stelligen Code eingeben', 'error');
        return;
    }
    try {
        const response = await App.post('/api/v1/auth/2fa/confirm', { code });
        backupCodes = response.data.backup_codes;
        const container = document.getElementById('backup-codes');
        container.innerHTML = backupCodes.map(c => `<span>${c}</span>`).join('');
        document.getElementById('setup-step2').style.display = 'none';
        document.getElementById('setup-step3').style.display = 'block';
        App.showNotification('2FA erfolgreich aktiviert!', 'success');
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

function finishSetup() { window.location.reload(); }

function copySecret() {
    navigator.clipboard.writeText(setupSecret);
    App.showNotification('Secret kopiert', 'success');
}

function copyBackupCodes() {
    navigator.clipboard.writeText(backupCodes.join('\n'));
    App.showNotification('Backup-Codes kopiert', 'success');
}

function downloadBackupCodes() {
    const text = '<?= APP_NAME ?> Backup-Codes\n' +
                 '============================\n\n' +
                 backupCodes.join('\n') +
                 '\n\nBewahre diese Codes sicher auf!';
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = '2fa-backup-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
}

function showDisable2FAModal() {
    const m = document.getElementById('disable-modal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('disable-password').focus(), 50);
}

function hideDisable2FAModal() {
    document.getElementById('disable-modal').style.display = 'none';
    document.getElementById('disable-password').value = '';
}

async function disable2FA() {
    const password = document.getElementById('disable-password').value;
    if (!password) {
        App.showNotification('Bitte Passwort eingeben', 'error');
        return;
    }
    try {
        await App.post('/api/v1/auth/2fa/disable', { password });
        App.showNotification('2FA deaktiviert', 'success');
        window.location.reload();
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
}

// Auto-submit bei 6 Ziffern
document.getElementById('verify-code')?.addEventListener('input', function() {
    if (this.value.length === 6 && /^\d{6}$/.test(this.value)) {
        confirmSetup();
    }
});
</script>
