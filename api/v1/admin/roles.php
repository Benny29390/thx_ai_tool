<?php
/**
 * Rollen-Defaults verwalten.
 *
 * GET  /admin/roles            — alle Rollen-Defaults als Map
 * POST /admin/roles            — Defaults einer Rolle setzen
 *   Body: {
 *     role: 'manager',
 *     capabilities: ['chat', ...],
 *     apply_to_existing: true|false   // optional: bestehende User mit dieser Rolle synchronisieren
 *   }
 */
use Core\Auth;
use Core\Response;

if (!Auth::isAdmin()) Response::forbidden('Nur Admin');

global $db, $method, $input;

if ($method === 'GET') {
    Response::success([
        'roles' => Auth::allRoleDefaults(),
        'all_caps' => Auth::ALL_CAPS,
    ]);
}

if ($method !== 'POST') Response::error('Method not allowed', 405);

$role = trim((string)($input['role'] ?? ''));
$caps = $input['capabilities'] ?? [];
$applyToExisting = !empty($input['apply_to_existing']);

if (!in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER, ROLE_GUEST], true)) {
    Response::error('Ungueltige Rolle');
}
if (!is_array($caps)) {
    Response::error('capabilities muss ein Array sein');
}
$caps = array_values(array_filter($caps, 'is_string'));

// Admin behaelt immer alle Caps — auch wenn jemand versehentlich was abwaehlt.
if ($role === ROLE_ADMIN) {
    $caps = Auth::ALL_CAPS;
}

Auth::setRoleDefaults($role, $caps);

$syncedUsers = 0;
if ($applyToExisting) {
    $userIds = array_column(
        $db->query("SELECT id FROM users WHERE role = ? AND is_active = 1", [$role]),
        'id'
    );
    foreach ($userIds as $uid) {
        Auth::setCapabilities((int)$uid, $caps, Auth::id());
        $syncedUsers++;
    }
}

Response::success([
    'role' => $role,
    'capabilities' => $caps,
    'synced_users' => $syncedUsers,
], $syncedUsers > 0
    ? "Rollen-Defaults gespeichert und auf $syncedUsers bestehende User uebertragen"
    : 'Rollen-Defaults gespeichert');
