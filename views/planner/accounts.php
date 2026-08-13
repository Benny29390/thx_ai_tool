<?php
/**
 * Tagesplaner: Asana-Accounts verwalten.
 * Ein User kann mehrere Asana-PATs hinterlegen (z.B. Thoxan + Hills & Valleys ehrenamtlich).
 * Jeder Account wird separat gesynct, Tasks bekommen ein farbiges Badge zur Unterscheidung.
 */
$accounts = $accounts ?? [];
?>
<?php include __DIR__ . '/../admin/_customer_master_styles.php'; ?>
<style>
.pa-page { max-width: 880px; margin: 0 auto; padding: 16px; }
.pa-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.pa-head h1 { margin: 0; font-size: var(--d-fs-xl); }
.pa-head .pa-sub { color: var(--slate-500); font-size: var(--d-fs-sm); flex: 1; }

.pa-card { background: #fff; border: 1px solid var(--slate-200); border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; display: flex; align-items: center; gap: 14px; }
.pa-card.is-inactive { opacity: 0.55; }
.pa-color-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.pa-card-body { flex: 1; min-width: 0; }
.pa-card-title { font-weight: 700; color: var(--slate-800); font-size: var(--d-fs-md); display: flex; align-items: center; gap: 8px; }
.pa-default-badge { padding: 2px 8px; border-radius: 8px; background: var(--thoxan-100); color: var(--thoxan-800); font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.pa-card-meta { color: var(--slate-500); font-size: var(--d-fs-xs); margin-top: 4px; line-height: 1.4; }
.pa-card-actions { display: flex; gap: 6px; flex-shrink: 0; }
.pa-card-actions button { background: transparent; border: 1px solid var(--slate-200); padding: 6px 10px; border-radius: 8px; cursor: pointer; color: var(--slate-700); font-size: var(--d-fs-xs); display: inline-flex; align-items: center; gap: 4px; }
.pa-card-actions button:hover { background: var(--slate-50); border-color: var(--slate-300); }
.pa-card-actions .is-danger { color: #b91c1c; }
.pa-card-actions .is-danger:hover { background: #fef2f2; border-color: #fca5a5; }

.pa-add { background: #f8fafc; border: 1px dashed var(--slate-300); border-radius: 12px; padding: 18px; }
.pa-add-head { font-weight: 700; color: var(--slate-700); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.pa-form-row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; align-items: center; margin-bottom: 10px; }
.pa-form-row label { font-size: var(--d-fs-sm); color: var(--slate-700); font-weight: 500; }
.pa-form-row input { padding: 8px 12px; border: 1px solid var(--slate-300); border-radius: 8px; font-family: inherit; font-size: var(--d-fs-sm); width: 100%; box-sizing: border-box; }
.pa-form-row input:focus { outline: none; border-color: var(--thoxan-600); box-shadow: 0 0 0 3px rgba(0,76,155,0.1); }
.pa-form-row input[type=color] { width: 60px; padding: 0; height: 36px; cursor: pointer; }
.pa-form-foot { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }
.pa-empty { text-align: center; color: var(--slate-500); padding: 32px; }
</style>

<div class="pa-page">
    <div class="pa-head">
        <a href="/tagesplan" class="thx-btn thx-btn-secondary" style="padding:6px 12px;font-size:var(--d-fs-xs);">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span> Zurück
        </a>
        <h1>Asana-Accounts</h1>
    </div>
    <div class="pa-sub" style="margin-bottom:16px;color:var(--slate-500);font-size:var(--d-fs-sm);">
        Hier kannst Du mehrere Asana-Accounts hinzufügen (z.B. Beruf + Ehrenamt). Jeder Account wird separat gesynct,
        Tasks bekommen ein farbiges Badge zur Unterscheidung.
    </div>

    <?php if (empty($accounts)): ?>
        <div class="pa-empty">Noch kein Account verbunden. Trag unten Deinen ersten PAT ein.</div>
    <?php else: ?>
        <?php foreach ($accounts as $a): ?>
            <div class="pa-card <?= $a['is_active'] ? '' : 'is-inactive' ?>" data-account-id="<?= (int)$a['id'] ?>">
                <span class="pa-color-dot" style="background: <?= htmlspecialchars($a['color_hex']) ?>;"></span>
                <div class="pa-card-body">
                    <div class="pa-card-title">
                        <?= htmlspecialchars($a['account_label']) ?>
                        <?php if ($a['is_default']): ?><span class="pa-default-badge">Default</span><?php endif; ?>
                    </div>
                    <div class="pa-card-meta">
                        <?= htmlspecialchars($a['asana_user_name'] ?? 'unbekannt') ?>
                        <?php if (!empty($a['asana_user_email'])): ?> · <?= htmlspecialchars($a['asana_user_email']) ?><?php endif; ?>
                        · <?= (int)$a['task_count'] ?> Tasks
                        <?php if (!empty($a['last_sync_at'])): ?> · letzter Sync <?= date('d.m. H:i', strtotime($a['last_sync_at'])) ?><?php endif; ?>
                        <?php if (!empty($a['default_customer_name'])): ?><br><span style="color:var(--slate-500);">Default-Kunde für unzugewiesene Tasks: <strong><?= htmlspecialchars($a['default_customer_name']) ?></strong></span><?php endif; ?>
                    </div>
                </div>
                <div class="pa-card-actions">
                    <button onclick="paEditAccount(<?= (int)$a['id'] ?>, '<?= htmlspecialchars(addslashes($a['account_label'])) ?>', '<?= htmlspecialchars($a['color_hex']) ?>')">
                        <span class="material-symbols-rounded" style="font-size:14px;">edit</span> Bearbeiten
                    </button>
                    <button onclick="paEditDefaultCustomer(<?= (int)$a['id'] ?>, <?= (int)($a['default_customer_id'] ?? 0) ?>)">
                        <span class="material-symbols-rounded" style="font-size:14px;">business</span> Default-Kunde
                    </button>
                    <?php if (!$a['is_default']): ?>
                        <button class="is-danger" onclick="paDeleteAccount(<?= (int)$a['id'] ?>, '<?= htmlspecialchars(addslashes($a['account_label'])) ?>', <?= (int)$a['task_count'] ?>)">
                            <span class="material-symbols-rounded" style="font-size:14px;">delete</span> Löschen
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="pa-add" style="margin-top:16px;">
        <div class="pa-add-head">
            <span class="material-symbols-rounded">add_circle</span> Neuen Account hinzufügen
        </div>
        <div class="pa-form-row">
            <label for="pa-label">Label</label>
            <input type="text" id="pa-label" placeholder="z.B. Hills &amp; Valleys" maxlength="60">
        </div>
        <div class="pa-form-row">
            <label for="pa-color">Badge-Farbe</label>
            <input type="color" id="pa-color" value="#7c3aed">
        </div>
        <div class="pa-form-row">
            <label for="pa-pat">Asana-PAT</label>
            <input type="password" id="pa-pat" placeholder="1/123456:abc... (Personal Access Token aus Asana)">
        </div>
        <div class="pa-form-row">
            <label for="pa-default-customer">Default-Kunde</label>
            <select id="pa-default-customer">
                <option value="">— keinen Default zuweisen (optional) —</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="font-size:var(--d-fs-xs);color:var(--slate-500);margin:-4px 0 10px 150px;">
            Tasks aus diesem Asana-Account ohne Projekt-/Präfix-Match werden diesem Kunden zugeordnet.
            <br>Beispiel: H&amp;V-Asana → Default „Hills &amp; Valleys".
        </div>
        <div class="pa-form-foot">
            <button class="thx-btn thx-btn-primary" onclick="paAddAccount()">Verbinden</button>
        </div>
    </div>
</div>

<script>
async function paAddAccount() {
    const label = document.getElementById('pa-label').value.trim();
    const color = document.getElementById('pa-color').value;
    const pat = document.getElementById('pa-pat').value.trim();
    const defaultCustomer = document.getElementById('pa-default-customer').value;
    if (!label || !pat) { App.showNotification('Label und PAT sind Pflicht', 'error'); return; }
    try {
        await App.post('/planner/accounts', { account_label: label, color_hex: color, pat: pat, default_customer_id: defaultCustomer ? parseInt(defaultCustomer, 10) : null });
        App.showNotification('Account hinzugefügt — der nächste Sync zieht die Tasks', 'success');
        setTimeout(() => location.reload(), 800);
    } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
}
async function paEditAccount(id, currentLabel, currentColor) {
    const label = prompt('Neues Label:', currentLabel);
    if (label === null) return;
    const color = prompt('Neue Farbe (Hex, z.B. #7c3aed):', currentColor);
    if (color === null) return;
    try {
        await App.post('/planner/accounts/' + id, { account_label: label.trim(), color_hex: color.trim() });
        location.reload();
    } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
}
async function paEditDefaultCustomer(id, current) {
    const customers = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $customers)) ?>;
    const list = customers.map((c, i) => `${i+1}. ${c.name}${c.id == current ? ' ✓' : ''}`).join('\n');
    const choice = prompt(
        'Default-Kunde für Tasks aus diesem Asana-Account wählen.\n' +
        'Tasks ohne klare Zuordnung werden diesem Kunden zugeordnet.\n\n' +
        list + '\n\n0 = kein Default-Kunde\n\nNummer eingeben:',
        current ? String(customers.findIndex(c => c.id == current) + 1) : '0'
    );
    if (choice === null) return;
    const n = parseInt(choice, 10);
    if (isNaN(n) || n < 0 || n > customers.length) { App.showNotification('Ungültige Nummer', 'error'); return; }
    const newId = n === 0 ? null : customers[n-1].id;
    try {
        await App.post('/planner/accounts/' + id, { default_customer_id: newId });
        location.reload();
    } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
}
async function paDeleteAccount(id, label, taskCount) {
    if (!confirm(`Account "${label}" löschen?\n\nDamit verschwinden ${taskCount} Tasks aus dem Tagesplaner (in Asana bleiben sie).`)) return;
    try {
        await App.post('/planner/accounts/' + id + '/delete', {});
        location.reload();
    } catch (e) { App.showNotification('Fehler: ' + (e.message || ''), 'error'); }
}
</script>
