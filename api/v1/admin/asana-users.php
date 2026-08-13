<?php
/**
 * Asana-User-Liste (Admin) — fuer den User-Picker im Admin-Form.
 *
 * GET /admin/asana-users
 * Returns: { workspace_gid, users: [{gid, name, email}, ...] }
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if (!Auth::isAdmin()) Response::forbidden();
if ($method !== 'GET') Response::error('Method not allowed', 405);

$pat = \Core\Settings::get('asana_pat');
if (empty($pat)) Response::error('Asana ist nicht konfiguriert. Bitte unter Einstellungen einen PAT hinterlegen.');

$workspaceGid = \Core\Settings::get('asana_workspace_gid');

require_once SERVICES_PATH . '/AsanaService.php';
$asana = new \Services\AsanaService($pat);

try {
    if (empty($workspaceGid)) {
        $workspaces = $asana->listWorkspaces();
        if (empty($workspaces)) Response::error('Kein Asana-Workspace zugaenglich');
        $workspaceGid = $workspaces[0]['gid'];
    }
    $users = $asana->listUsers($workspaceGid);
    usort($users, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
    Response::success([
        'workspace_gid' => $workspaceGid,
        'users' => $users,
    ]);
} catch (\Exception $e) {
    Response::error('Asana-Fehler: ' . $e->getMessage());
}
