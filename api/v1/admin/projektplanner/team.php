<?php
/**
 * Projektplanner — Team-Mitglieder API
 *
 * GET    /admin/projektplanner/team                        Liste + Auto-Sync mit users
 * POST   /admin/projektplanner/team                        Neue Person (User oder frei)
 * PUT    /admin/projektplanner/team/{id}                   Bearbeiten
 * DELETE /admin/projektplanner/team/{id}                   Deaktivieren
 * GET    /admin/projektplanner/team/users                  Verfügbare users zum Hinzufügen
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpTeamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpTeamService.php';
$db = Database::getInstance();
$svc = new PpTeamService($db);

$method = $_SERVER['REQUEST_METHOD'];
$memberId = (int) ($_GET['member_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'users' && $method === 'GET') {
    // Users die noch KEIN pp_team_members-Eintrag haben — zum Hinzufügen
    $rows = $db->query(
        "SELECT u.id, u.name, u.email, u.abbreviation
         FROM users u
         LEFT JOIN pp_team_members t ON t.user_id = u.id
         WHERE u.is_active = 1 AND t.id IS NULL
         ORDER BY u.name ASC"
    ) ?: [];
    Response::success(['users' => $rows]);
}

if ($memberId > 0 && $method === 'PUT') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    // Flag rename_in_plans: Name in allen Plan-Zeilen propagieren
    if (!empty($payload['rename_in_plans']) && !empty($payload['name'])) {
        $current = $svc->getById($memberId);
        if ($current && trim($current['name']) !== trim($payload['name'])) {
            $affected = $svc->renamePerson($current['name'], $payload['name'], $memberId);
            unset($payload['rename_in_plans'], $payload['name']);
            if (!empty($payload)) $svc->update($memberId, $payload);
            Response::success(['id' => $memberId, 'rows_affected' => $affected], 'Umbenannt — ' . $affected . ' Plan-Zeilen aktualisiert');
        }
        unset($payload['rename_in_plans']);
    }
    $svc->update($memberId, $payload);
    Response::success(['id' => $memberId], 'Gespeichert');
}

if ($memberId > 0 && $method === 'DELETE') {
    $svc->deactivate($memberId);
    Response::success(['id' => $memberId], 'Deaktiviert');
}

if ($method === 'GET') {
    // Auto-Sync bei jedem Liste-Aufruf
    $created = $svc->syncFromUsers();
    $members = $svc->getAll(true);
    Response::success(['team' => $members, 'auto_synced' => $created]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $newId = $svc->create($payload);
        Response::success(['id' => $newId], 'Mitglied hinzugefügt');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

Response::error('Methode nicht unterstützt', 405);
