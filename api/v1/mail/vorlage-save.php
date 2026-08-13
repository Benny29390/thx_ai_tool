<?php
/** POST /mail/vorlage-save Body: { id?, name, kategorie?, betreff_template?, body_template, aktiv?, platzhalter? } */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = !empty($input['id']) ? (int)$input['id'] : null;
$name = trim((string)($input['name'] ?? ''));
$body = trim((string)($input['body_template'] ?? ''));
if ($name === '' || $body === '') Response::error('name + body_template erforderlich', 400);

$db = Database::getInstance();
$felder = [
    'name' => $name,
    'kategorie' => $input['kategorie'] ?? null,
    'betreff_template' => $input['betreff_template'] ?? null,
    'body_template' => $body,
    'platzhalter' => isset($input['platzhalter']) && is_array($input['platzhalter']) ? json_encode($input['platzhalter']) : null,
    'aktiv' => !empty($input['aktiv']) ? 1 : 0,
];

if ($id) {
    $sets = [];
    $params = [];
    foreach ($felder as $f => $v) { $sets[] = "`$f` = ?"; $params[] = $v; }
    $params[] = $id;
    $db->execute("UPDATE mail_vorlagen SET " . implode(',', $sets) . " WHERE id = ?", $params);
    Response::success(['id' => $id]);
} else {
    $cols = array_keys($felder);
    $ph = array_fill(0, count($cols), '?');
    $db->execute("INSERT INTO mail_vorlagen (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")", array_values($felder));
    $newId = (int)$db->queryValue("SELECT LAST_INSERT_ID()");
    Response::success(['id' => $newId]);
}
