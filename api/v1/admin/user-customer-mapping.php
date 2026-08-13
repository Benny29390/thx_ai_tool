<?php
/**
 * Kundenzuordnung (Rollen + User direkt) bulk speichern.
 *
 * POST /admin/user-customer-mapping
 * Body: {
 *   roles: {            // optional — Rollen-Zuordnung komplett ersetzen
 *     manager: [1,2,3],
 *     user:    [4,5],
 *     guest:   []
 *   },
 *   users: {            // optional — Direkt-Zuordnung komplett ersetzen
 *     5: [1,2],         // user_id 5 bekommt customers 1+2
 *     6: [3,4]
 *   }
 * }
 */
use Core\Auth;
use Core\Response;

if (!Auth::isAdmin()) Response::forbidden('Nur Admin');

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

$roles = $input['roles'] ?? [];
$users = $input['users'] ?? [];

$allowedRoles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER, ROLE_GUEST];
$rolesUpdated = 0;
$usersUpdated = 0;

// --- Rollen-Zuordnung ---
if (is_array($roles)) {
    foreach ($roles as $role => $customerIds) {
        if (!in_array($role, $allowedRoles, true)) continue;
        // admin-Rolle ignorieren — sieht eh alles, role_customers fuer 'admin' macht keinen Sinn
        if ($role === ROLE_ADMIN) continue;
        if (!is_array($customerIds)) continue;
        Auth::setRoleCustomers($role, array_map('intval', $customerIds), Auth::id());
        $rolesUpdated++;
    }
}

// --- Direkte User-Zuordnung ---
if (is_array($users)) {
    foreach ($users as $userId => $customerIds) {
        $uid = (int)$userId;
        if ($uid <= 0) continue;
        if (!is_array($customerIds)) continue;
        $before = array_map('intval', array_column(
            $db->query("SELECT customer_id FROM user_customers WHERE user_id = ?", [$uid]),
            'customer_id'
        ));
        $clean = array_values(array_unique(array_map('intval', $customerIds)));
        $db->execute("DELETE FROM user_customers WHERE user_id = ?", [$uid]);
        $isFirst = true;
        foreach ($clean as $cid) {
            if ($cid <= 0) continue;
            $db->insert('user_customers', [
                'user_id'     => $uid,
                'customer_id' => $cid,
                'is_default'  => $isFirst ? 1 : 0,
            ]);
            $isFirst = false;
        }
        sort($before); $cleanSorted = $clean; sort($cleanSorted);
        if ($before !== $cleanSorted) {
            \Core\AuditLog::record(
                \Core\AuditLog::TARGET_USER,
                (string)$uid,
                \Core\AuditLog::ACTION_CUSTOMERS_CHANGED,
                ['before' => $before, 'after' => $cleanSorted]
            );
        }
        $usersUpdated++;
    }
}

Response::success([
    'roles_updated' => $rolesUpdated,
    'users_updated' => $usersUpdated,
], 'Zuordnung gespeichert: ' . $rolesUpdated . ' Rolle(n), ' . $usersUpdated . ' User');
