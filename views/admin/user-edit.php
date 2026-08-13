<?php
/**
 * Benutzer-Detailseite (Edit). Stil-Vorbild: LAM-Detailseiten + Customer-Steckbrief.
 *
 * Erwartete Variablen:
 *   $user                 — array (id, email, name, abbreviation, role, is_active, ...)
 *   $customers            — array of (id, name, slug, is_active)
 *   $assignedCustomerIds  — int[]
 *   $capabilities         — string[]
 */
use Core\Auth;

$roleLabels = [
    'admin'   => 'Admin',
    'manager' => 'Manager',
    'user'    => 'User',
    'guest'   => 'Guest',
];
$roleDescriptions = [
    'admin'   => 'Voller Zugriff inkl. Einstellungen + Benutzerverwaltung. Caps werden ignoriert — Admin kann immer alles.',
    'manager' => 'Kernteam. Volle Schreibrechte in den freigeschalteten Capabilities.',
    'user'    => 'Standard-Mitarbeiter. Kann eigene Inhalte anlegen und bearbeiten.',
    'guest'   => 'Lesemodus. Schreibaktionen werden serverseitig blockiert. Auch für externe Personen geeignet.',
];
$roleBadgeStyle = [
    'admin'   => 'background:#fecaca;color:#991b1b;',
    'manager' => 'background:var(--thoxan-100);color:var(--thoxan-700);',
    'user'    => 'background:var(--emerald-100);color:var(--emerald-800);',
    'guest'   => 'background:var(--slate-200);color:var(--slate-700);',
];

// Cap-Gruppen kommen zentral aus Auth::CAP_META — neue Caps werden dort
// einmal gepflegt und erscheinen automatisch hier in der User-Edit-Sicht.
$capGroups = \Core\Auth::capGroups();

$activeCaps = array_flip($capabilities ?? []);
$isSelf = (int)$user['id'] === (int)Auth::id();

// Initialen für Avatar
$nameParts = explode(' ', trim($user['name']));
$initials = mb_strtoupper(
    mb_substr($nameParts[0] ?? '', 0, 1)
    . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : mb_substr($nameParts[0] ?? '', 1, 1))
);
?>

<div class="thx-page-header" style="display:flex;align-items:flex-start;gap:16px;">
    <!-- Avatar mit Initialen, im Stil des Customer-Steckbriefs -->
    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>

    <div style="flex:1;min-width:0;">
        <a href="/admin/users" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zur Benutzerliste</a>
        <h1 class="thx-page-title" style="margin-top:4px;">
            <?= htmlspecialchars($user['name']) ?>
            <span class="lam-badge" style="<?= $roleBadgeStyle[$user['role']] ?? '' ?>font-size: var(--d-fs-xs);vertical-align:middle;margin-left:8px;">
                <?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role']) ?>
            </span>
            <?php if (!$user['is_active']): ?>
                <span class="lam-badge" style="background:var(--slate-200);color:var(--slate-700);font-size: var(--d-fs-xs);vertical-align:middle;">Inaktiv</span>
            <?php endif; ?>
        </h1>
        <p style="margin:2px 0 0 0;font-size:var(--d-fs-sm);color:var(--slate-500);"><?= htmlspecialchars($user['email']) ?></p>
    </div>

    <div class="thx-page-actions" style="display:flex;gap:8px;">
        <?php if (!$isSelf): ?>
            <button type="button" class="lam-btn lam-btn-secondary" onclick="viewAsUser()">
                <span class="material-symbols-rounded" style="font-size:16px;">visibility</span>
                Sicht ansehen
            </button>
        <?php endif; ?>
        <button type="button" class="lam-btn lam-btn-primary" onclick="speichern()" id="btn-speichern">Speichern</button>
    </div>
</div>

<form id="user-form" onsubmit="event.preventDefault(); speichern();">
<input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

<div class="lam-grid-2" style="grid-template-columns: 2fr 1fr;">

    <!-- LINKS: Stammdaten + Rolle + Caps -->
    <section style="display:flex;flex-direction:column;gap:20px;">

        <div class="lam-card">
            <h3>Stammdaten</h3>
            <div class="ue-row" style="grid-template-columns:2fr 1fr 1fr;">
                <div class="ue-field">
                    <label>Name <small>(offizieller Name — wird überall angezeigt)</small></label>
                    <input type="text" name="name" class="thx-input" required value="<?= htmlspecialchars($user['name']) ?>">
                </div>
                <div class="ue-field">
                    <label>Spitzname <small>(optional)</small></label>
                    <input type="text" name="nickname" class="thx-input" maxlength="50"
                           placeholder="z.B. Benny, Gaby"
                           value="<?= htmlspecialchars($user['nickname'] ?? '') ?>">
                </div>
                <div class="ue-field">
                    <label>Kürzel <small>(max. 5)</small></label>
                    <input type="text" name="abbreviation" class="thx-input" maxlength="5"
                           style="text-transform:uppercase;"
                           value="<?= htmlspecialchars($user['abbreviation'] ?? '') ?>">
                </div>
            </div>
            <div class="ue-field">
                <label>E-Mail</label>
                <input type="email" name="email" class="thx-input" required value="<?= htmlspecialchars($user['email']) ?>">
            </div>
            <div class="ue-row" style="grid-template-columns:1fr auto;align-items:end;">
                <div class="ue-field" style="margin-bottom:0;">
                    <label>Neues Passwort <small>(leer = nicht ändern)</small></label>
                    <input type="password" name="password" class="thx-input" autocomplete="new-password" minlength="8" placeholder="••••••••">
                </div>
                <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;cursor:pointer;white-space:nowrap;">
                    <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
                    <span>Account aktiv</span>
                </label>
            </div>
        </div>

        <div class="lam-card">
            <h3>Rolle</h3>
            <div class="ue-field" style="margin-bottom:0;">
                <select name="role" id="role-select" class="thx-input"
                        style="max-width:560px;"
                        onchange="onRoleChange(); applyDefaultCaps();">
                    <?php foreach ($roleLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $user['role'] === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="role-description" style="margin:8px 0 0 0;font-size:var(--d-fs-sm);color:var(--slate-600);line-height:1.4;">
                    <?= htmlspecialchars($roleDescriptions[$user['role']] ?? '') ?>
                </p>
            </div>
        </div>

        <div class="lam-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
                <h3 style="margin:0;">Capabilities <small style="font-weight:400;color:var(--slate-500);">— was darf <?= htmlspecialchars($user['name']) ?>?</small></h3>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="lam-btn lam-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="setAllCaps(true)">Alle</button>
                    <button type="button" class="lam-btn lam-btn-secondary" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="setAllCaps(false)">Keine</button>
                    <button type="button" class="lam-btn lam-btn-accent" style="padding:4px 10px;font-size:var(--d-fs-xs);" onclick="applyDefaultCaps()">Defaults der Rolle</button>
                </div>
            </div>
            <?php if ($user['role'] === 'admin'): ?>
                <div style="background:#fef9c3;border:1px solid #facc15;border-radius:6px;padding:8px 12px;font-size:var(--d-fs-sm);color:#854d0e;margin-bottom:12px;">
                    <strong>Hinweis:</strong> Admin hat automatisch alle Caps. Die Häkchen unten sind nur zur Anzeige — serverseitig werden sie ignoriert.
                </div>
            <?php endif; ?>

            <?php foreach ($capGroups as $groupName => $caps): ?>
                <div class="ue-cap-group">
                    <div class="ue-cap-group-title"><?= htmlspecialchars($groupName) ?></div>
                    <div class="ue-cap-list">
                        <?php foreach ($caps as $key => [$label, $desc]): ?>
                            <label class="ue-cap-item">
                                <input type="checkbox" name="capabilities[]" value="<?= htmlspecialchars($key) ?>"
                                       <?= isset($activeCaps[$key]) ? 'checked' : '' ?>>
                                <div>
                                    <strong><?= htmlspecialchars($label) ?></strong>
                                    <small><?= htmlspecialchars($desc) ?></small>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="lam-card">
            <h3>Projektplanner <small style="font-weight:400;color:var(--slate-500);">— Daten für Plan-Zeilen</small></h3>
            <div class="ue-row" style="grid-template-columns:1fr 1fr auto;align-items:end;">
                <div class="ue-field" style="margin-bottom:0;">
                    <label>Monatskapazität <small>(Stunden)</small></label>
                    <input type="number" name="pp_capacity_hours" class="thx-input"
                           min="0" step="10"
                           value="<?= (int)($ppTeam['capacity_hours'] ?? 160) ?>">
                </div>
                <div class="ue-field" style="margin-bottom:0;">
                    <label>Farbe <small>(Chip in Plan-Zeilen)</small></label>
                    <input type="color" name="pp_hex_color" class="thx-input"
                           value="<?= htmlspecialchars($ppTeam['hex_color'] ?? '#3b82f6') ?>"
                           style="height:38px;padding:2px;">
                </div>
                <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:6px;cursor:pointer;white-space:nowrap;height:38px;box-sizing:border-box;">
                    <input type="checkbox" name="pp_team_active" value="1" <?= ($ppTeam['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Im PP-Team aktiv</span>
                </label>
            </div>
            <p style="margin:8px 0 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
                Diese Person erscheint in Plan-Dropdowns „Hauptverantw." und „Umsetzung". Das Kürzel (aus „Stammdaten") wird als Chip dargestellt.
                Capability „Projektplanner" oben muss zusätzlich gesetzt sein, damit die Person das Tool öffnen darf.
            </p>
        </div>

    </section>

    <!-- RECHTS: Status, Kunden, Asana, Gefahrenzone -->
    <aside style="display:flex;flex-direction:column;gap:20px;">

        <div class="lam-card">
            <h3>Status</h3>
            <table class="lam-table" style="font-size:var(--d-fs-sm);">
                <tbody>
                    <tr><td class="muted" style="color:var(--slate-500);">Account</td><td><strong><?= $user['is_active'] ? 'Aktiv' : 'Inaktiv' ?></strong></td></tr>
                    <tr><td class="muted" style="color:var(--slate-500);">Letzter Login</td><td><?= !empty($user['last_login']) ? date('d.m.Y H:i', strtotime($user['last_login'])) : '—' ?></td></tr>
                    <tr><td class="muted" style="color:var(--slate-500);">Angelegt</td><td><?= !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '—' ?></td></tr>
                    <tr><td class="muted" style="color:var(--slate-500);">User-ID</td><td><code><?= (int)$user['id'] ?></code></td></tr>
                </tbody>
            </table>
        </div>

        <div class="lam-card">
            <h3>Zugewiesene Kunden</h3>
            <p style="margin:-6px 0 12px 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
                User/Guest sehen nur diese. Admin sieht alle.
            </p>
            <div style="display:flex;gap:6px;margin-bottom:8px;">
                <input type="text" id="customer-filter" class="thx-input" placeholder="Suche…"
                       oninput="filterCustomers()" style="flex:1;font-size:var(--d-fs-sm);padding:6px 10px;">
            </div>
            <div style="display:flex;gap:6px;margin-bottom:8px;align-items:center;">
                <button type="button" class="lam-btn lam-btn-secondary" style="padding:3px 9px;font-size:var(--d-fs-xs);" onclick="setAllCustomers(true)">Alle</button>
                <button type="button" class="lam-btn lam-btn-secondary" style="padding:3px 9px;font-size:var(--d-fs-xs);" onclick="setAllCustomers(false)">Keine</button>
                <span id="customer-count" style="margin-left:auto;font-size:var(--d-fs-xs);color:var(--slate-500);"></span>
            </div>
            <div class="ue-customer-list" id="customer-list">
                <?php foreach ($customers as $c):
                    $checked = in_array((int)$c['id'], $assignedCustomerIds, true);
                    $isInactive = !$c['is_active']; ?>
                    <label class="ue-customer-row <?= $isInactive ? 'is-inactive' : '' ?>"
                           data-name="<?= htmlspecialchars(mb_strtolower($c['name'])) ?>">
                        <input type="checkbox" name="customer_ids[]" value="<?= (int)$c['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($c['name']) ?><?php if ($isInactive): ?> <small>(inaktiv)</small><?php endif; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="lam-card">
            <h3>Asana-Verknüpfung</h3>
            <input type="hidden" name="asana_user_gid" id="asana-user-gid" value="<?= htmlspecialchars($user['asana_user_gid'] ?? '') ?>">
            <?php if (!empty($user['asana_user_gid'])): ?>
                <div style="display:flex;gap:10px;align-items:center;padding:8px 10px;background:var(--thoxan-50);border:1px solid var(--thoxan-100);border-radius:6px;">
                    <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:18px;">person</span>
                    <div style="flex:1;min-width:0;">
                        <strong style="font-size:var(--d-fs-sm);display:block;"><?= htmlspecialchars($user['asana_user_name'] ?? '?') ?></strong>
                        <small style="color:var(--slate-500);font-size:var(--d-fs-xs);"><?= htmlspecialchars($user['asana_user_email'] ?? '') ?></small>
                    </div>
                    <button type="button" class="lam-btn lam-btn-secondary" style="padding:3px 9px;font-size:var(--d-fs-xs);" onclick="clearAsana()">Entfernen</button>
                </div>
            <?php else: ?>
                <p style="margin:0;font-size:var(--d-fs-sm);color:var(--slate-500);">Nicht verknüpft.</p>
            <?php endif; ?>
        </div>

        <?php if (!$isSelf): ?>
            <div class="lam-card" style="border-color:var(--rose-200);">
                <h3 style="color:var(--rose-700);">Gefahrenzone</h3>
                <p style="margin:-6px 0 12px 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
                    Löschen ist nicht rückgängig zu machen.
                </p>
                <button type="button" class="lam-btn lam-btn-danger" onclick="loeschen()">Benutzer löschen</button>
            </div>
        <?php endif; ?>

    </aside>

</div>
</form>

<style>
.user-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--thoxan-600), var(--thoxan-700));
    color: #fff;
    font-size: var(--d-fs-2xl);
    font-weight: 800;
    letter-spacing: 1px;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(0, 76, 155, 0.18);
}

/* Form-Field-Pattern, kompakt im LAM-Stil */
.ue-row { display: grid; gap: 12px; margin-bottom: 12px; }
.ue-field { margin-bottom: 12px; }
.ue-field:last-child { margin-bottom: 0; }
.ue-field label {
    display: block;
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--slate-600);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.ue-field label small {
    font-weight: 400;
    color: var(--slate-400);
    text-transform: none;
    letter-spacing: 0;
}

/* Capability-Gruppen */
.ue-cap-group { margin-top: 16px; }
.ue-cap-group:first-of-type { margin-top: 4px; }
.ue-cap-group-title {
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--slate-100);
}
.ue-cap-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}
.ue-cap-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.1s;
}
.ue-cap-item:hover { background: var(--slate-50); }
.ue-cap-item input[type=checkbox] {
    margin: 2px 0 0 0;
    width: 16px;
    height: 16px;
    accent-color: var(--thoxan-600);
    flex-shrink: 0;
}
.ue-cap-item:has(input:checked) { background: var(--thoxan-50); }
.ue-cap-item div { flex: 1; min-width: 0; }
.ue-cap-item strong { display: block; font-size: var(--d-fs-sm); color: var(--slate-900); line-height: 1.2; }
.ue-cap-item small { display: block; color: var(--slate-500); font-size: var(--d-fs-xs); margin-top: 1px; line-height: 1.35; }

/* Kunden-Liste */
.ue-customer-list {
    max-height: 320px;
    overflow-y: auto;
    border: 1px solid var(--slate-200);
    border-radius: 6px;
    background: #fff;
}
.ue-customer-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-bottom: 1px solid var(--slate-100);
    cursor: pointer;
    font-size: var(--d-fs-sm);
}
.ue-customer-row:last-child { border-bottom: none; }
.ue-customer-row:hover { background: var(--slate-50); }
.ue-customer-row input[type=checkbox] {
    width: 14px;
    height: 14px;
    accent-color: var(--thoxan-600);
    margin: 0;
    flex-shrink: 0;
}
.ue-customer-row:has(input:checked) { background: var(--thoxan-50); }
.ue-customer-row.is-inactive { opacity: 0.55; }
.ue-customer-row small { color: var(--slate-400); }

/* Mobile */
@media (max-width: 1024px) {
    .lam-grid-2[style*="2fr 1fr"] { grid-template-columns: 1fr !important; }
    .ue-cap-list { grid-template-columns: 1fr; }
}
</style>

<script>
// Rollen-Defaults kommen aus der DB (Tabelle role_capabilities) — zentral
// einstellbar unter /admin/roles. Fallback ist die Konstante in Core\Auth.
const ROLE_DEFAULT_CAPS = <?= json_encode($roleDefaults ?? [], JSON_UNESCAPED_UNICODE) ?>;
const ROLE_DESCRIPTIONS = <?= json_encode($roleDescriptions, JSON_UNESCAPED_UNICODE) ?>;
const USER_ID = <?= (int)$user['id'] ?>;
const USER_NAME = <?= json_encode($user['name'], JSON_UNESCAPED_UNICODE) ?>;
const USER_EMAIL = <?= json_encode($user['email'], JSON_UNESCAPED_UNICODE) ?>;
const USER_IS_ACTIVE = <?= $user['is_active'] ? 'true' : 'false' ?>;

function onRoleChange() {
    const role = document.getElementById('role-select').value;
    const desc = document.getElementById('role-description');
    if (desc) desc.textContent = ROLE_DESCRIPTIONS[role] || '';
}
function setCapCheckboxes(caps) {
    document.querySelectorAll('input[name="capabilities[]"]').forEach(cb => {
        cb.checked = caps.includes(cb.value);
    });
}
function setAllCaps(checked) {
    document.querySelectorAll('input[name="capabilities[]"]').forEach(cb => cb.checked = checked);
}
function applyDefaultCaps() {
    const role = document.getElementById('role-select').value;
    setCapCheckboxes(ROLE_DEFAULT_CAPS[role] || []);
}

function filterCustomers() {
    const q = (document.getElementById('customer-filter').value || '').toLowerCase().trim();
    let shown = 0, total = 0;
    document.querySelectorAll('#customer-list .ue-customer-row').forEach(el => {
        total++;
        const name = el.dataset.name || '';
        const match = !q || name.includes(q);
        el.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    document.getElementById('customer-count').textContent = q ? (shown + ' / ' + total) : (total + ' Kunden');
}
function setAllCustomers(checked) {
    document.querySelectorAll('#customer-list .ue-customer-row').forEach(el => {
        if (el.style.display === 'none') return;
        const cb = el.querySelector('input[type=checkbox]');
        if (cb) cb.checked = checked;
    });
}
function clearAsana() {
    document.getElementById('asana-user-gid').value = '';
    location.reload();
}

async function speichern() {
    const btn = document.getElementById('btn-speichern');
    btn.disabled = true;
    btn.textContent = 'Speichern…';
    try {
        const form = document.getElementById('user-form');
        const data = {
            name: form.name.value.trim(),
            nickname: form.nickname.value.trim(),
            email: form.email.value.trim(),
            abbreviation: form.abbreviation.value.trim(),
            role: form.role.value,
            is_active: form.is_active.checked ? 1 : 0,
            customer_ids: Array.from(form.querySelectorAll('input[name="customer_ids[]"]:checked')).map(cb => parseInt(cb.value, 10)),
            capabilities: Array.from(form.querySelectorAll('input[name="capabilities[]"]:checked')).map(cb => cb.value),
            asana_user_gid: form.asana_user_gid.value,
            pp_capacity_hours: parseInt(form.pp_capacity_hours.value, 10) || 0,
            pp_hex_color: form.pp_hex_color.value,
            pp_team_active: form.pp_team_active.checked ? 1 : 0,
        };
        if (form.password.value) data.password = form.password.value;

        const resp = await App.request('PUT', '/admin/users/' + USER_ID, data);
        if (resp.success) {
            App.showNotification('Benutzer gespeichert', 'success');
            form.password.value = '';
        } else {
            App.showNotification(resp.message || 'Fehler beim Speichern', 'error');
        }
    } catch (e) {
        App.showNotification(e.message || 'Verbindungsfehler', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Speichern';
    }
}

async function viewAsUser() {
    if (!confirm('In die Sicht von "' + USER_NAME + '" wechseln?')) return;
    try {
        const r = await App.post('/auth/login-as', { user_id: USER_ID });
        if (r.success) {
            setTimeout(() => location.href = '/', 200);
        } else {
            App.showNotification(r.message || 'Wechsel fehlgeschlagen', 'error');
        }
    } catch (e) {
        App.showNotification(e.message || 'Wechsel fehlgeschlagen', 'error');
    }
}

async function loeschen() {
    // Schritt 1: User noch aktiv? Erst deaktivieren.
    const formCb = document.querySelector('input[name="is_active"]');
    const stillActive = USER_IS_ACTIVE && (!formCb || formCb.checked);
    if (stillActive) {
        const wantDeactivate = confirm(
            'Vorsicht — endgültiges Löschen ist nicht reversibel.\n\n' +
            '"' + USER_NAME + '" ist noch aktiv. Erst deaktivieren ' +
            '(Daten bleiben dabei erhalten, der User kann sich aber nicht mehr einloggen).\n\n' +
            'Jetzt deaktivieren?'
        );
        if (!wantDeactivate) return;
        try {
            await App.request('PUT', '/admin/users/' + USER_ID, { is_active: 0 });
            App.showNotification('User deaktiviert. Zum endgültigen Löschen die Seite neu laden und nochmal klicken.', 'info');
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            App.showNotification(e.message || 'Fehler beim Deaktivieren', 'error');
        }
        return;
    }

    // Schritt 2: User ist inaktiv → endgültige Löschung mit E-Mail-Bestätigung
    const typed = prompt(
        'Endgültiges Löschen: gib zur Bestätigung die E-Mail-Adresse des Users ein.\n\n' +
        'Bei Eingabe von "' + USER_EMAIL + '" wird der User samt Caps- und Kundenzuordnungen gelöscht. ' +
        'Chats, Wissens-Einträge und Audit-Spuren bleiben anonymisiert erhalten.'
    );
    if (!typed) return;
    if (typed.trim().toLowerCase() !== USER_EMAIL.toLowerCase()) {
        App.showNotification('E-Mail stimmt nicht — Löschen abgebrochen.', 'error');
        return;
    }
    try {
        await App.request('DELETE', '/admin/users/' + USER_ID, { confirm_email: USER_EMAIL });
        App.showNotification('Benutzer gelöscht', 'success');
        setTimeout(() => location.href = '/admin/users', 400);
    } catch (e) {
        App.showNotification(e.message || 'Fehler beim Löschen', 'error');
    }
}

filterCustomers();
</script>
