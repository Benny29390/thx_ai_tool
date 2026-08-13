<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $suche = trim((string)($_GET['suche'] ?? ''));
    if ($suche !== '') {
        Response::success(['branchen' => $db->query(
            "SELECT id, name, anzahl_firmen FROM crm_branchen WHERE name LIKE ? ORDER BY anzahl_firmen DESC, name ASC LIMIT 30",
            ['%' . $suche . '%']
        )]);
    }
    Response::success(['branchen' => $db->query("SELECT id, name, anzahl_firmen FROM crm_branchen ORDER BY anzahl_firmen DESC, sort_order ASC, name ASC")]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::can(CAP_CRM_VOKABULAR)) Response::forbidden();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') Response::error('Name leer');
    try {
        $db->execute("INSERT IGNORE INTO crm_branchen (name) VALUES (?)", [$name]);
        $id = (int)$db->queryValue("SELECT id FROM crm_branchen WHERE name = ?", [$name]);
        Response::success(['id' => $id, 'name' => $name]);
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}
Response::error('Methode nicht erlaubt', 405);
