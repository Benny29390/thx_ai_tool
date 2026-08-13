<?php
/**
 * Asana-Projekte aus Workspace listen / Details holen.
 *
 * GET /admin/asana-projects?workspace_gid=X[&archived=0]   → Liste
 * GET /admin/asana-projects/{gid}                           → Details
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if (!Auth::isAdmin()) Response::forbidden();
if ($method !== 'GET') Response::error('Method not allowed', 405);

require_once SERVICES_PATH . '/AsanaService.php';

$pat = \Core\Settings::get('asana_pat') ?? '';
if (empty($pat)) Response::error('Asana PAT nicht konfiguriert');

$asana = new \Services\AsanaService($pat);

$gid = $_GET['gid'] ?? null;

try {
    if ($gid) {
        $project = $asana->getProject($gid);
        Response::success(['project' => $project]);
    }

    $workspaceGid = $_GET['workspace_gid'] ?? null;
    if (!$workspaceGid) {
        // Default-Workspace aus Settings
        $workspaceGid = \Core\Settings::get('asana_workspace_gid');
    }

    $archived = !empty($_GET['archived']) && $_GET['archived'] !== '0';

    // Wenn keine Workspace-GID: alle Workspaces durchgehen und Projekte aus allen sammeln
    if (!$workspaceGid) {
        $workspaces = $asana->listWorkspaces();
        if (empty($workspaces)) Response::error('Keine Asana-Workspaces zugaenglich (PAT pruefen)');

        // Bei genau einem Workspace: persistieren und nutzen
        if (count($workspaces) === 1) {
            $workspaceGid = $workspaces[0]['gid'];
            // In Settings hinterlegen, damit es beim naechsten Mal direkt gewaehlt wird
            $existing = $db->queryOne("SELECT id FROM settings WHERE setting_key = 'asana_workspace_gid'");
            if ($existing) {
                $db->execute("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$workspaceGid, 'asana_workspace_gid']);
            } else {
                $db->insert('settings', [
                    'setting_key' => 'asana_workspace_gid',
                    'setting_value' => $workspaceGid,
                    'setting_type' => 'string',
                    'description' => 'Asana Workspace GID (auto-detected)',
                ]);
            }
            $projects = $asana->listProjects($workspaceGid, $archived);
            // Workspace-Info anhaengen
            foreach ($projects as &$p) {
                $p['workspace'] = ['gid' => $workspaceGid, 'name' => $workspaces[0]['name'] ?? ''];
            }
            unset($p);
            Response::success([
                'projects' => $projects,
                'workspace_gid' => $workspaceGid,
                'workspaces' => $workspaces,
            ]);
        }

        // Mehrere Workspaces: alle Projekte aus allen Workspaces sammeln
        $allProjects = [];
        foreach ($workspaces as $ws) {
            try {
                $projects = $asana->listProjects($ws['gid'], $archived);
                foreach ($projects as $p) {
                    $p['workspace'] = ['gid' => $ws['gid'], 'name' => $ws['name'] ?? ''];
                    $allProjects[] = $p;
                }
            } catch (\Exception $e) {
                // einzelner Workspace-Fehler nicht fatal
                error_log('asana-projects ws ' . $ws['gid'] . ': ' . $e->getMessage());
            }
        }
        Response::success([
            'projects' => $allProjects,
            'workspaces' => $workspaces,
            'workspace_gid' => null, // nicht gesetzt — UI soll Gruppierung nutzen
        ]);
    }

    // Spezifischer Workspace
    $projects = $asana->listProjects($workspaceGid, $archived);
    Response::success([
        'projects' => $projects,
        'workspace_gid' => $workspaceGid,
    ]);
} catch (\Exception $e) {
    Response::error('Asana-Fehler: ' . $e->getMessage());
}
