<?php
/**
 * Rollen-Defaults verwalten — kompakte Matrix-Tabelle: Caps (Zeilen) × Rollen (Spalten).
 *
 * Erwartete Variablen:
 *   $roleDefaults — array<role, string[]>  aktuelle Defaults aus DB
 *   $userCounts   — array<role, int>       aktive User je Rolle
 */
use Core\Auth;

$rollen = [
    'admin'   => ['label' => 'Admin',   'farbe' => 'rose'],
    'manager' => ['label' => 'Manager', 'farbe' => 'thoxan'],
    'user'    => ['label' => 'User',    'farbe' => 'emerald'],
    'guest'   => ['label' => 'Guest',   'farbe' => 'slate'],
];

// Capability-Gruppen kommen ab jetzt zentral aus Auth::capGroups() —
// neue Capabilities werden in Auth::CAP_META eingetragen und erscheinen
// automatisch hier in der Matrix.
$capGroups = Auth::capGroups();

// Aktiv-Map fuer schnellen Lookup im Render
$aktiv = [];
foreach (['admin','manager','user','guest'] as $r) {
    $aktiv[$r] = array_flip($roleDefaults[$r] ?? []);
}
?>
<div class="thx-page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
    <div>
        <a href="/admin/users" style="font-size:var(--d-fs-sm);color:var(--slate-500);text-decoration:none;">‹ Zurück zur Benutzerliste</a>
        <h1 class="thx-page-title" style="margin-top:4px;">Rollen &amp; Capabilities</h1>
        <p style="margin:2px 0 0 0;font-size:var(--d-fs-sm);color:var(--slate-500);">
            Default-Caps pro Rolle. Klick auf die Rolle in der Kopfzeile = ganze Spalte toggeln, Klick auf den Cap-Namen = ganze Zeile toggeln.
        </p>
    </div>
    <div class="thx-page-actions" style="display:flex;gap:8px;">
        <button type="button" class="lam-btn lam-btn-secondary" onclick="zuruecksetzen()">
            <span class="material-symbols-rounded" style="font-size:16px;">undo</span>
            Verworfen
        </button>
        <button type="button" class="lam-btn lam-btn-primary" id="btn-save" onclick="speichereAlles()">
            Speichern
            <span id="dirty-count" class="dirty-count" style="display:none;"></span>
        </button>
    </div>
</div>

<div class="lam-card" style="padding:0;overflow:hidden;">
    <table class="role-matrix">
        <thead>
            <tr>
                <th class="cap-col">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span>Capability</span>
                        <small style="color:var(--slate-400);font-weight:400;"><?= count(Auth::ALL_CAPS) ?> Funktionen</small>
                    </div>
                </th>
                <?php foreach ($rollen as $key => $meta): ?>
                    <th class="role-col role-col-<?= $meta['farbe'] ?>"
                        onclick="toggleSpalte('<?= $key ?>')"
                        title="Klick: alle Caps fuer <?= htmlspecialchars($meta['label']) ?> umschalten">
                        <div class="role-col-label"><?= htmlspecialchars($meta['label']) ?></div>
                        <div class="role-col-meta">
                            <?= (int)($userCounts[$key] ?? 0) ?> User
                        </div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($capGroups as $groupName => $caps): ?>
                <tr class="cap-group-row">
                    <td colspan="5"><?= htmlspecialchars($groupName) ?></td>
                </tr>
                <?php foreach ($caps as $capKey => [$label, $desc]): ?>
                    <tr class="cap-row" data-cap="<?= htmlspecialchars($capKey) ?>">
                        <td class="cap-col" onclick="toggleZeile('<?= htmlspecialchars($capKey) ?>')"
                            title="Klick: diese Cap fuer alle Rollen umschalten">
                            <div class="cap-label"><?= htmlspecialchars($label) ?></div>
                            <div class="cap-desc"><?= htmlspecialchars($desc) ?></div>
                        </td>
                        <?php foreach ($rollen as $rkey => $rmeta):
                            $isAdmin = ($rkey === 'admin');
                            // Admin: serverseitig immer voll berechtigt — UI zeigt alle Caps gehakt
                            $isOn = $isAdmin ? true : isset($aktiv[$rkey][$capKey]);
                        ?>
                            <td class="cell role-col-<?= $rmeta['farbe'] ?>">
                                <label class="cell-toggle" title="<?= htmlspecialchars($rmeta['label']) ?>: <?= htmlspecialchars($label) ?>">
                                    <input type="checkbox"
                                           data-role="<?= $rkey ?>"
                                           data-cap="<?= htmlspecialchars($capKey) ?>"
                                           <?= $isOn ? 'checked' : '' ?>
                                           <?= $isAdmin ? 'disabled' : '' ?>
                                           onchange="markDirty('<?= $rkey ?>')">
                                    <span class="check-visual"></span>
                                </label>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>

        </tbody>
    </table>
</div>

<div class="role-hint">
    <strong>Admin-Schutz:</strong> Admin hat serverseitig immer alle Caps — die Häkchen in der Admin-Spalte sind ausgegraut und werden ignoriert.
    <br>
    <strong>Beim Speichern</strong> fragt Dich ein Dialog, ob die Änderungen auch auf <em>bestehende User</em> dieser Rolle ausgerollt werden sollen.
</div>

<!-- Modal: Sync-Frage pro geänderter Rolle -->
<div id="sync-modal" class="sync-modal-backdrop" style="display:none;" onclick="if(event.target===this)schliesseModal()">
    <div class="sync-modal">
        <div class="sync-modal-header">
            <h2>Änderungen ausrollen?</h2>
            <button type="button" onclick="schliesseModal()" aria-label="Schliessen">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="sync-modal-body">
            <p style="margin:0 0 16px 0;color:var(--slate-700);">
                Du hast die Default-Caps für folgende Rollen geändert. Sollen die Änderungen auch auf <strong>bestehende aktive User</strong> dieser Rolle übernommen werden?
            </p>
            <div id="sync-modal-list">
                <!-- per JS gefuellt -->
            </div>
            <p style="margin:16px 0 0 0;font-size:var(--d-fs-xs);color:var(--slate-500);">
                <strong style="color:var(--rose-700);">Hinweis:</strong> Beim Ausrollen werden auch <em>individuelle Cap-Anpassungen</em> der jeweiligen User überschrieben. Wer nur die Defaults ändert, behält die individuellen Caps der bestehenden User.
            </p>
        </div>
        <div class="sync-modal-footer">
            <button type="button" class="lam-btn lam-btn-secondary" onclick="schliesseModal()">Abbrechen</button>
            <button type="button" class="lam-btn lam-btn-primary" onclick="doSpeichern()">Speichern</button>
        </div>
    </div>
</div>

<style>
.dirty-count {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--amber-500);
    color: #fff;
    font-size: var(--d-fs-xs);
    font-weight: 700;
}

.role-matrix {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--d-fs-sm);
}
.role-matrix th, .role-matrix td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--slate-100);
    vertical-align: middle;
}
.role-matrix thead th {
    position: sticky;
    top: 44px; /* unter Topbar */
    background: var(--slate-50);
    border-bottom: 2px solid var(--slate-200);
    text-align: center;
    cursor: pointer;
    user-select: none;
    z-index: 2;
}
.role-matrix thead th:first-child {
    text-align: left;
    cursor: default;
}
.role-matrix thead th:hover:not(:first-child) {
    background: var(--slate-100);
}

.role-col-label { font-size: var(--d-fs-sm); font-weight: 700; color: var(--slate-800); }
.role-col-meta  { font-size: var(--d-fs-xs); color: var(--slate-500); font-weight: 400; margin-top: 2px; }

/* Spalten-Akzente */
.role-col-rose    .role-col-label { color: var(--rose-700); }
.role-col-thoxan  .role-col-label { color: var(--thoxan-700); }
.role-col-emerald .role-col-label { color: var(--emerald-700); }
.role-col-slate   .role-col-label { color: var(--slate-700); }

/* Zell-Hintergrund leicht eingefaerbt */
td.cell {
    text-align: center;
    width: 14%;
    transition: background 0.1s;
}
td.cell.role-col-rose:has(input:checked)    { background: rgba(254, 202, 202, 0.4); }
td.cell.role-col-thoxan:has(input:checked)  { background: rgba(191, 219, 254, 0.4); }
td.cell.role-col-emerald:has(input:checked) { background: rgba(187, 247, 208, 0.4); }
td.cell.role-col-slate:has(input:checked)   { background: rgba(226, 232, 240, 0.5); }

td.cap-col {
    width: 44%;
    background: #fff;
    cursor: pointer;
    user-select: none;
}
td.cap-col:hover { background: var(--slate-50); }

.cap-label { font-weight: 600; color: var(--slate-900); font-size: var(--d-fs-sm); }
.cap-desc  { font-size: var(--d-fs-xs); color: var(--slate-500); margin-top: 2px; line-height: 1.35; }

/* Gruppen-Trenner */
.cap-group-row td {
    background: var(--slate-100);
    padding: 6px 14px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--slate-600);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: default;
}

/* Custom Checkbox */
.cell-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    cursor: pointer;
}
.cell-toggle input { display: none; }
.check-visual {
    width: 22px;
    height: 22px;
    border-radius: 5px;
    border: 1.5px solid var(--slate-300);
    background: #fff;
    transition: all 0.1s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.check-visual::after {
    content: '';
    width: 12px;
    height: 7px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: rotate(-45deg) translate(1px, -1px);
    opacity: 0;
    transition: opacity 0.1s;
}
.cell-toggle input:checked + .check-visual {
    background: var(--thoxan-600);
    border-color: var(--thoxan-600);
}
.cell-toggle input:checked + .check-visual::after { opacity: 1; }
.cell-toggle input:disabled + .check-visual {
    background: var(--slate-200);
    border-color: var(--slate-300);
    cursor: not-allowed;
}
.cell-toggle input:disabled:checked + .check-visual::after {
    border-color: var(--slate-500);
}

.role-hint {
    margin-top: 16px;
    padding: 12px 16px;
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    font-size: var(--d-fs-xs);
    color: var(--slate-600);
    line-height: 1.5;
}
.role-hint strong { color: var(--slate-800); }
.role-hint em { color: var(--rose-700); font-style: normal; font-weight: 600; }

@media (max-width: 900px) {
    .cap-col { width: auto; }
    td.cell { width: auto; padding: 6px 8px; }
    .cap-desc { display: none; }
}

/* === Sync-Modal === */
.sync-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 16px;
}
.sync-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.35);
}
.sync-modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--slate-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sync-modal-header h2 {
    margin: 0;
    font-size: var(--d-fs-lg);
    font-weight: 700;
    color: var(--slate-900);
}
.sync-modal-header button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--slate-500);
    padding: 4px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.sync-modal-header button:hover { background: var(--slate-100); color: var(--slate-900); }

.sync-modal-body {
    padding: 22px;
    overflow-y: auto;
    flex: 1;
}
.sync-modal-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--slate-200);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: var(--slate-50);
    border-radius: 0 0 12px 12px;
}

.sync-role-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.1s;
}
.sync-role-row:last-child { margin-bottom: 0; }
.sync-role-row:hover { border-color: var(--thoxan-300); background: var(--slate-50); }
.sync-role-row:has(input:checked) {
    border-color: var(--thoxan-500);
    background: var(--thoxan-50);
}
.sync-role-row.no-users {
    opacity: 0.55;
    cursor: not-allowed;
}
.sync-role-row.no-users:hover { border-color: var(--slate-200); background: #fff; }

.sync-role-row input[type=checkbox] {
    width: 18px;
    height: 18px;
    accent-color: var(--thoxan-600);
    flex-shrink: 0;
    cursor: pointer;
}
.sync-role-row .role-info { flex: 1; min-width: 0; }
.sync-role-row .role-info strong {
    display: block;
    font-size: var(--d-fs-sm);
    color: var(--slate-900);
}
.sync-role-row .role-info small {
    display: block;
    font-size: var(--d-fs-xs);
    color: var(--slate-500);
    margin-top: 2px;
}
.sync-role-row .role-badge {
    padding: 2px 10px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sync-role-row .role-badge.rose    { background: var(--rose-100); color: var(--rose-800); }
.sync-role-row .role-badge.thoxan  { background: var(--thoxan-100); color: var(--thoxan-700); }
.sync-role-row .role-badge.emerald { background: var(--emerald-100); color: var(--emerald-800); }
.sync-role-row .role-badge.slate   { background: var(--slate-200); color: var(--slate-700); }
</style>

<script>
const dirtyRoles = new Set();
const USER_COUNTS = <?= json_encode($userCounts, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_META = {
    admin:   { label: 'Admin',   farbe: 'rose' },
    manager: { label: 'Manager', farbe: 'thoxan' },
    user:    { label: 'User',    farbe: 'emerald' },
    guest:   { label: 'Guest',   farbe: 'slate' },
};

function markDirty(role) {
    dirtyRoles.add(role);
    updateDirtyBadge();
}
function updateDirtyBadge() {
    const badge = document.getElementById('dirty-count');
    if (dirtyRoles.size > 0) {
        badge.style.display = 'inline-block';
        badge.textContent = dirtyRoles.size;
    } else {
        badge.style.display = 'none';
    }
}

function toggleSpalte(role) {
    const cbs = document.querySelectorAll('input[data-role="' + role + '"][data-cap]');
    if (cbs.length === 0) return;
    const enabled = Array.from(cbs).filter(cb => !cb.disabled);
    if (enabled.length === 0) return;
    const checkedCount = enabled.filter(cb => cb.checked).length;
    const ziel = checkedCount < enabled.length;
    enabled.forEach(cb => cb.checked = ziel);
    markDirty(role);
}

function toggleZeile(cap) {
    const cbs = document.querySelectorAll('input[data-cap="' + cap + '"][data-role]');
    const enabled = Array.from(cbs).filter(cb => !cb.disabled);
    if (enabled.length === 0) return;
    const checkedCount = enabled.filter(cb => cb.checked).length;
    const ziel = checkedCount < enabled.length;
    enabled.forEach(cb => {
        cb.checked = ziel;
        markDirty(cb.dataset.role);
    });
}

function zuruecksetzen() {
    if (dirtyRoles.size === 0) return;
    if (!confirm('Ungespeicherte Änderungen verwerfen?')) return;
    location.reload();
}

function sammelCaps(role) {
    return Array.from(document.querySelectorAll('input[data-role="' + role + '"][data-cap]:checked'))
        .map(cb => cb.dataset.cap);
}

// === Modal-Workflow ===
function speichereAlles() {
    if (dirtyRoles.size === 0) {
        App.showNotification('Nichts zu speichern.', 'info');
        return;
    }

    // Modal-Inhalt zusammenstellen — pro geaenderter Rolle eine Auswahl-Reihe.
    // Defaultwert: aktiviert, wenn User mit dieser Rolle existieren (sonst inaktiv).
    const list = document.getElementById('sync-modal-list');
    list.innerHTML = '';
    const sorted = Array.from(dirtyRoles).sort((a, b) => {
        const order = ['admin', 'manager', 'user', 'guest'];
        return order.indexOf(a) - order.indexOf(b);
    });

    for (const role of sorted) {
        const meta = ROLE_META[role];
        const count = USER_COUNTS[role] || 0;
        const hasUsers = count > 0;
        const newCaps = sammelCaps(role);
        const capCount = newCaps.length;

        const row = document.createElement('label');
        row.className = 'sync-role-row' + (hasUsers ? '' : ' no-users');
        row.innerHTML = `
            <input type="checkbox" data-sync-modal="${role}" ${hasUsers ? 'checked' : 'disabled'}>
            <div class="role-info">
                <strong>${meta.label}</strong>
                <small>${count} aktive${count === 1 ? 'r' : ''} User · ${capCount} Cap${capCount === 1 ? '' : 's'} aktiv</small>
            </div>
            <span class="role-badge ${meta.farbe}">${meta.label}</span>
        `;
        list.appendChild(row);
    }

    document.getElementById('sync-modal').style.display = 'flex';
}

function schliesseModal() {
    document.getElementById('sync-modal').style.display = 'none';
}

async function doSpeichern() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true;

    const payload = [];
    let sumSync = 0;
    for (const role of dirtyRoles) {
        const caps = sammelCaps(role);
        const cb = document.querySelector('input[data-sync-modal="' + role + '"]');
        const sync = !!(cb && cb.checked && !cb.disabled);
        if (sync) sumSync++;
        payload.push({ role, capabilities: caps, apply_to_existing: sync });
    }

    schliesseModal();

    let okCount = 0;
    let totalSyncedUsers = 0;
    let errors = [];
    for (const change of payload) {
        try {
            const resp = await App.post('/admin/roles', change);
            if (resp.success) {
                okCount++;
                if (resp.data && resp.data.synced_users) totalSyncedUsers += resp.data.synced_users;
            } else {
                errors.push(change.role + ': ' + (resp.message || 'Fehler'));
            }
        } catch (e) {
            errors.push(change.role + ': ' + (e.message || 'Verbindungsfehler'));
        }
    }

    btn.disabled = false;
    if (errors.length === 0) {
        const msg = totalSyncedUsers > 0
            ? `Gespeichert: ${okCount} Rolle(n), ${totalSyncedUsers} User aktualisiert`
            : `Gespeichert: ${okCount} Rolle(n)`;
        App.showNotification(msg, 'success');
        dirtyRoles.clear();
        updateDirtyBadge();
    } else {
        App.showNotification('Fehler: ' + errors.join('; '), 'error');
    }
}

// ESC schliesst Modal
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('sync-modal').style.display === 'flex') {
        schliesseModal();
    }
});
</script>
