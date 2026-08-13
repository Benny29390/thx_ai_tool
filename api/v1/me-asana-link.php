<?php
/**
 * Asana-Verknuepfung des eigenen Profils.
 *
 * GET    /me/asana-link          → aktueller Status
 * POST   /me/asana-link          → Auto-Detect via E-Mail (oder Body {gid: ...} fuer manuell)
 * DELETE /me/asana-link          → Verknuepfung entfernen
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$user = Auth::user();
if (!$user) Response::unauthorized();

$userId = (int) $user['id'];

if ($method === 'GET') {
    $resp = [
        'asana_user_gid' => $user['asana_user_gid'] ?? null,
        'email' => $user['email'] ?? null,
        'users' => [],
    ];

    // Optional: Liste aller Asana-User mitliefern (?with_users=1)
    if (!empty($_GET['with_users'])) {
        $pat = \Core\Settings::get('asana_pat');
        if ($pat) {
            $workspaceGid = \Core\Settings::get('asana_workspace_gid');
            require_once SERVICES_PATH . '/AsanaService.php';
            try {
                $asana = new \Services\AsanaService($pat);
                if (empty($workspaceGid)) {
                    $ws = $asana->listWorkspaces();
                    if (!empty($ws)) $workspaceGid = $ws[0]['gid'];
                }
                if ($workspaceGid) {
                    $list = $asana->listUsers($workspaceGid);
                    // sortiert nach Name
                    usort($list, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
                    $resp['users'] = $list;
                }
            } catch (\Exception $e) {
                $resp['users_error'] = $e->getMessage();
            }
        }
    }

    Response::success($resp);
}

if ($method === 'DELETE') {
    $db->update('users', [
        'asana_user_gid' => null,
        'asana_user_email' => null,
        'asana_user_name' => null,
    ], 'id = ?', [$userId]);
    Response::success(null, 'Asana-Verknüpfung entfernt');
}

if ($method !== 'POST') Response::error('Method not allowed', 405);

// Manuelle GID — Email/Name via Lookup nachladen
if (!empty($input['gid'])) {
    $gid = preg_replace('/[^0-9]/', '', (string) $input['gid']);
    if (empty($gid)) Response::error('Ungültige Asana-User-GID');

    $update = ['asana_user_gid' => $gid];

    // Asana-User-Details ermitteln
    $pat = \Core\Settings::get('asana_pat');
    if ($pat) {
        $workspaceGid = \Core\Settings::get('asana_workspace_gid');
        require_once SERVICES_PATH . '/AsanaService.php';
        try {
            $asana = new \Services\AsanaService($pat);
            if (empty($workspaceGid)) {
                $ws = $asana->listWorkspaces();
                if (!empty($ws)) $workspaceGid = $ws[0]['gid'];
            }
            if ($workspaceGid) {
                $list = $asana->listUsers($workspaceGid);
                foreach ($list as $au) {
                    if ((string)($au['gid'] ?? '') === $gid) {
                        $update['asana_user_email'] = $au['email'] ?? null;
                        $update['asana_user_name'] = $au['name'] ?? null;
                        break;
                    }
                }
            }
        } catch (\Exception $e) { /* nicht fatal */ }
    }

    $db->update('users', $update, 'id = ?', [$userId]);
    Response::success([
        'asana_user_gid' => $gid,
        'asana_user_email' => $update['asana_user_email'] ?? null,
        'asana_user_name' => $update['asana_user_name'] ?? null,
    ], 'Asana-Verknüpfung gespeichert');
}

// Auto-Detect via E-Mail
$pat = \Core\Settings::get('asana_pat');
if (empty($pat)) Response::error('Asana ist im System nicht konfiguriert. Bitte Admin kontaktieren.');

$workspaceGid = \Core\Settings::get('asana_workspace_gid');

require_once SERVICES_PATH . '/AsanaService.php';
$asana = new \Services\AsanaService($pat);

try {
    if (empty($workspaceGid)) {
        // Falls kein Default-Workspace: ersten holen
        $workspaces = $asana->listWorkspaces();
        if (empty($workspaces)) Response::error('Kein Asana-Workspace zugänglich');
        $workspaceGid = $workspaces[0]['gid'];
    }

    $email = $user['email'] ?? '';
    if (empty($email)) Response::error('Keine E-Mail-Adresse hinterlegt');

    $asanaUser = $asana->findUserByEmail($workspaceGid, $email);
    if (!$asanaUser) {
        Response::error('Kein Asana-User mit der E-Mail "' . $email . '" gefunden. Du kannst die GID auch manuell unten eingeben.');
    }

    $db->update('users', [
        'asana_user_gid' => $asanaUser['gid'],
        'asana_user_email' => $asanaUser['email'] ?? null,
        'asana_user_name' => $asanaUser['name'] ?? null,
    ], 'id = ?', [$userId]);
    Response::success([
        'asana_user_gid' => $asanaUser['gid'],
        'name' => $asanaUser['name'] ?? null,
        'email' => $asanaUser['email'] ?? null,
    ], 'Mit Asana verknüpft als ' . ($asanaUser['name'] ?? $asanaUser['gid']));
} catch (\Exception $e) {
    Response::error('Asana-Fehler: ' . $e->getMessage());
}
