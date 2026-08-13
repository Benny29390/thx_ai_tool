<?php
/** GET /mail/vorlagen?kategorie= */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$db = Database::getInstance();
$where = ['aktiv = 1'];
$params = [];
if (!empty($_GET['kategorie'])) {
    $where[] = 'kategorie = ?';
    $params[] = $_GET['kategorie'];
}
$rows = $db->query(
    "SELECT id, name, kategorie, betreff_template, body_template, platzhalter, aktiv, erstellt_am
     FROM mail_vorlagen WHERE " . implode(' AND ', $where) . " ORDER BY name",
    $params
);
Response::success($rows);
