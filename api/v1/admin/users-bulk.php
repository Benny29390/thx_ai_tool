<?php
/**
 * Bulk-Aktionen fuer User-Liste.
 *
 * POST /admin/users/bulk
 * Body: {
 *   ids: [int, ...],
 *   action: 'set_role'|'activate'|'deactivate'|'reset_caps_to_defaults'|'assign_customers',
 *   value: ...                  // je nach action
 * }
 *
 * Aktionen:
 *   set_role               value=string (admin|manager|user|guest)
 *   activate               -
 *   deactivate             -
 *   reset_caps_to_defaults -  setzt alle ausgewaehlten User auf die Defaults ihrer aktuellen Rolle
 *   assign_customers       value={mode:'set'|'add'|'remove', ids:[int,...]}
 */
use Core\Auth;
use Core\Response;

if (!Auth::isAdmin()) Response::forbidden('Nur Admin');

global $db, $method, $input;
if ($method !== 'POST') Response::error('Method not allowed', 405);

$ids = $input['ids'] ?? [];
$action = trim((string)($input['action'] ?? ''));
$value  = $input['value'] ?? null;

if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich');
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
if (empty($ids)) Response::error('Keine gueltigen IDs');

// Eigenen Account aus Selbstschaden-Aktionen ausklammern
$selfId = (int)Auth::id();
$canAffectSelf = ['reset_caps_to_defaults', 'assign_customers'];
$filteredIds = $ids;
if (!in_array($action, $canAffectSelf, true)) {
    $filteredIds = array_values(array_filter($ids, fn($i) => $i !== $selfId));
}

$results = ['affected' => 0, 'skipped' => count($ids) - count($filteredIds)];

switch ($action) {
    case 'set_role':
        if (!in_array($value, [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER, ROLE_GUEST], true)) {
            Response::error('Ungueltige Rolle');
        }
        foreach ($filteredIds as $uid) {
            $before = $db->queryValue("SELECT role FROM users WHERE id = ?", [$uid]);
            if ($before === $value) continue;
            $db->execute("UPDATE users SET role = ? WHERE id = ?", [$value, $uid]);
            \Core\AuditLog::record(
                \Core\AuditLog::TARGET_USER, (string)$uid,
                \Core\AuditLog::ACTION_ROLE_CHANGED,
                ['before' => $before, 'after' => $value]
            );
            // Caps auf Default der neuen Rolle setzen
            Auth::setCapabilities($uid, Auth::defaultCapsFor($value), $selfId);
            $results['affected']++;
        }
        break;

    case 'activate':
        foreach ($filteredIds as $uid) {
            $before = (int)$db->queryValue("SELECT is_active FROM users WHERE id = ?", [$uid]);
            if ($before === 1) continue;
            $db->execute("UPDATE users SET is_active = 1 WHERE id = ?", [$uid]);
            $results['affected']++;
        }
        break;

    case 'deactivate':
        foreach ($filteredIds as $uid) {
            $before = (int)$db->queryValue("SELECT is_active FROM users WHERE id = ?", [$uid]);
            if ($before === 0) continue;
            $db->execute("UPDATE users SET is_active = 0 WHERE id = ?", [$uid]);
            \Core\AuditLog::record(
                \Core\AuditLog::TARGET_USER, (string)$uid,
                \Core\AuditLog::ACTION_USER_DEACTIVATED, null
            );
            $results['affected']++;
        }
        break;

    case 'reset_caps_to_defaults':
        foreach ($ids as $uid) {  // hier auch self erlaubt — entzieht nichts irreversibles
            $role = $db->queryValue("SELECT role FROM users WHERE id = ?", [$uid]);
            if (!$role) continue;
            Auth::setCapabilities($uid, Auth::defaultCapsFor($role), $selfId);
            $results['affected']++;
        }
        break;

    case 'assign_customers':
        if (!is_array($value)) Response::error('value erfordert ein Objekt mit mode + ids');
        $mode = $value['mode'] ?? 'set';
        $cIds = array_map('intval', $value['ids'] ?? []);
        if (!in_array($mode, ['set','add','remove'], true)) Response::error('Ungueltiger mode');

        foreach ($ids as $uid) {
            $current = array_map('intval', array_column(
                $db->query("SELECT customer_id FROM user_customers WHERE user_id = ?", [$uid]),
                'customer_id'
            ));
            if ($mode === 'set') {
                $target = $cIds;
            } elseif ($mode === 'add') {
                $target = array_values(array_unique(array_merge($current, $cIds)));
            } else { // remove
                $target = array_values(array_diff($current, $cIds));
            }
            sort($current); $targetSorted = $target; sort($targetSorted);
            if ($current === $targetSorted) continue;

            $db->execute("DELETE FROM user_customers WHERE user_id = ?", [$uid]);
            $isFirst = true;
            foreach ($target as $cid) {
                if ($cid <= 0) continue;
                $db->insert('user_customers', [
                    'user_id'     => $uid,
                    'customer_id' => $cid,
                    'is_default'  => $isFirst ? 1 : 0,
                ]);
                $isFirst = false;
            }
            \Core\AuditLog::record(
                \Core\AuditLog::TARGET_USER, (string)$uid,
                \Core\AuditLog::ACTION_CUSTOMERS_CHANGED,
                ['before' => $current, 'after' => $targetSorted]
            );
            $results['affected']++;
        }
        break;

    default:
        Response::error('Unbekannte action');
}

Response::success($results, 'Bulk-Aktion abgeschlossen');
