<?php
/**
 * Audit-Log-Tab — chronologische Sicht auf Berechtigungs-Aenderungen.
 *
 * Variablen vom Controller:
 *   $auditEntries  array  (mit actor_name, actor_email, target_type, target_key, action, diff, occurred_at)
 *   $auditTotal    int
 */
$actionLabels = [
    'caps_changed'           => 'User-Caps geändert',
    'role_changed'           => 'Rolle geändert',
    'customers_changed'      => 'Kunden geändert',
    'role_caps_changed'      => 'Rollen-Defaults geändert',
    'role_customers_changed' => 'Rollen-Kunden geändert',
    'user_created'           => 'User angelegt',
    'user_deactivated'       => 'User deaktiviert',
];
$actionColor = [
    'caps_changed'           => 'thoxan',
    'role_changed'           => 'amber',
    'customers_changed'      => 'emerald',
    'role_caps_changed'      => 'thoxan',
    'role_customers_changed' => 'emerald',
    'user_created'           => 'emerald',
    'user_deactivated'       => 'rose',
];

// Lookup-Map fuer Target-User-Namen (damit wir „User #5" durch Namen ersetzen koennen)
$db = \Core\Database::getInstance();
$userMap = [];
foreach ($db->query("SELECT id, name FROM users") as $u) $userMap[(int)$u['id']] = $u['name'];

$selectedAction = $_GET['action'] ?? '';
$selectedTargetType = $_GET['target_type'] ?? '';
?>

<div class="lam-card" style="margin-bottom:16px;padding:14px 18px;">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="tab" value="audit">
            <select name="action" class="thx-input" onchange="this.form.submit()" style="font-size:var(--d-fs-sm);padding:6px 10px;">
                <option value="">Alle Aktionen</option>
                <?php foreach ($actionLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $selectedAction === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="target_type" class="thx-input" onchange="this.form.submit()" style="font-size:var(--d-fs-sm);padding:6px 10px;">
                <option value="">Alle Ziele</option>
                <option value="user" <?= $selectedTargetType === 'user' ? 'selected' : '' ?>>Nutzer</option>
                <option value="role" <?= $selectedTargetType === 'role' ? 'selected' : '' ?>>Rolle</option>
            </select>
            <?php if ($selectedAction || $selectedTargetType): ?>
                <a href="/admin/users?tab=audit" class="lam-btn lam-btn-secondary" style="font-size:var(--d-fs-xs);padding:6px 12px;">× zurücksetzen</a>
            <?php endif; ?>
        </form>
        <span style="margin-left:auto;font-size:var(--d-fs-xs);color:var(--slate-500);">
            <?= count($auditEntries) ?> von <?= (int)$auditTotal ?> Einträgen
        </span>
    </div>
</div>

<div class="lam-card" style="padding:0;overflow:hidden;">
    <?php if (empty($auditEntries)): ?>
        <div style="padding:32px;text-align:center;color:var(--slate-500);font-size:var(--d-fs-sm);">
            Noch keine Audit-Einträge.
        </div>
    <?php else: ?>
        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width:140px;">Wann</th>
                    <th style="width:160px;">Wer</th>
                    <th style="width:160px;">Aktion</th>
                    <th style="width:180px;">Ziel</th>
                    <th>Änderung</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditEntries as $e):
                    $action = $e['action'];
                    $color  = $actionColor[$action] ?? 'slate';
                    $targetType = $e['target_type'];
                    $targetKey  = $e['target_key'];
                    $targetLabel = $targetKey;
                    if ($targetType === 'user') {
                        $tid = (int)$targetKey;
                        $targetLabel = $userMap[$tid] ?? ('User #' . $tid);
                    } else {
                        $targetLabel = ucfirst($targetKey);
                    }
                ?>
                    <tr>
                        <td class="audit-time" title="<?= htmlspecialchars($e['occurred_at']) ?>">
                            <?= date('d.m.Y', strtotime($e['occurred_at'])) ?><br>
                            <small style="color:var(--slate-500);"><?= date('H:i', strtotime($e['occurred_at'])) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($e['actor_name'])): ?>
                                <strong><?= htmlspecialchars($e['actor_name']) ?></strong>
                            <?php else: ?>
                                <em style="color:var(--slate-400);">System</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="audit-action audit-action-<?= $color ?>">
                                <?= htmlspecialchars($actionLabels[$action] ?? $action) ?>
                            </span>
                        </td>
                        <td>
                            <span class="audit-target audit-target-<?= htmlspecialchars($targetType) ?>">
                                <span class="material-symbols-rounded" style="font-size:13px;vertical-align:middle;">
                                    <?= $targetType === 'user' ? 'person' : 'group' ?>
                                </span>
                                <?= htmlspecialchars($targetLabel) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $diff = $e['diff'];
                            if (!$diff || !is_array($diff)) { echo '<span style="color:var(--slate-400);">—</span>'; continue; }
                            $before = $diff['before'] ?? null;
                            $after  = $diff['after'] ?? null;
                            if ($before === null || $after === null) {
                                echo '<code style="font-size:var(--d-fs-xs);">' . htmlspecialchars(json_encode($diff)) . '</code>';
                                continue;
                            }
                            // Diff-Sichtbarkeit: was wurde entfernt (in before, nicht in after) + was hinzugefuegt
                            $bArr = is_array($before) ? $before : [$before];
                            $aArr = is_array($after)  ? $after  : [$after];
                            $added   = array_diff($aArr, $bArr);
                            $removed = array_diff($bArr, $aArr);
                            ?>
                            <div class="audit-diff">
                                <?php foreach ($added as $item): ?>
                                    <span class="audit-pill audit-pill-add">+ <?= htmlspecialchars((string)$item) ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($removed as $item): ?>
                                    <span class="audit-pill audit-pill-remove">− <?= htmlspecialchars((string)$item) ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($added) && empty($removed)): ?>
                                    <span style="color:var(--slate-400);font-size:var(--d-fs-xs);">keine Änderung</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.audit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--d-fs-sm);
}
.audit-table thead th {
    background: var(--slate-50);
    border-bottom: 1px solid var(--slate-200);
    padding: 8px 12px;
    text-align: left;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    color: var(--slate-600);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.audit-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--slate-100);
    vertical-align: top;
}
.audit-table tbody tr:hover td { background: var(--slate-50); }
.audit-time {
    font-size: var(--d-fs-xs);
    color: var(--slate-700);
    white-space: nowrap;
    line-height: 1.3;
}

.audit-action {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.audit-action-thoxan  { background: var(--thoxan-100);  color: var(--thoxan-700); }
.audit-action-emerald { background: var(--emerald-100); color: var(--emerald-800); }
.audit-action-amber   { background: #fef3c7;            color: #92400e; }
.audit-action-rose    { background: var(--rose-100);    color: var(--rose-800); }
.audit-action-slate   { background: var(--slate-100);   color: var(--slate-700); }

.audit-target {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: var(--d-fs-xs);
    background: #fff;
    border: 1px solid var(--slate-200);
}
.audit-target-user strong { color: var(--slate-900); }

.audit-diff {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 520px;
}
.audit-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: var(--d-fs-xs);
    font-weight: 600;
}
.audit-pill-add    { background: var(--emerald-50, #ecfdf5); color: var(--emerald-800); border: 1px solid var(--emerald-200, #a7f3d0); }
.audit-pill-remove { background: var(--rose-50, #fff1f2);    color: var(--rose-800);    border: 1px solid var(--rose-200, #fecdd3); }
</style>
