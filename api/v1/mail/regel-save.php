<?php
/** POST /mail/regel-save Body: { id?, name, absender_pattern?, betreff_pattern?, body_pattern?, kategorie?, folgeaktion?, vorlage_id?, prioritaet?, aktiv? } */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = !empty($input['id']) ? (int)$input['id'] : null;
$name = trim((string)($input['name'] ?? ''));
if ($name === '') Response::error('name erforderlich', 400);

// Pattern-Validierung (regex compile-Test)
foreach (['absender_pattern', 'betreff_pattern', 'body_pattern'] as $f) {
    $p = trim((string)($input[$f] ?? ''));
    if ($p !== '' && @preg_match('/' . str_replace('/', '\/', $p) . '/i', '') === false) {
        Response::error("Ungültiger Regex in $f: " . $p, 400);
    }
}

$db = Database::getInstance();
$felder = [
    'name' => $name,
    'absender_pattern' => $input['absender_pattern'] ?: null,
    'betreff_pattern' => $input['betreff_pattern'] ?: null,
    'body_pattern' => $input['body_pattern'] ?: null,
    'kategorie' => $input['kategorie'] ?: null,
    'folgeaktion' => $input['folgeaktion'] ?: null,
    'vorlage_id' => !empty($input['vorlage_id']) ? (int)$input['vorlage_id'] : null,
    'prioritaet' => (int)($input['prioritaet'] ?? 10),
    'aktiv' => !empty($input['aktiv']) ? 1 : 0,
];

if ($id) {
    $sets = [];
    $params = [];
    foreach ($felder as $f => $v) { $sets[] = "`$f` = ?"; $params[] = $v; }
    $params[] = $id;
    $db->execute("UPDATE mail_regeln SET " . implode(',', $sets) . " WHERE id = ?", $params);
    Response::success(['id' => $id]);
} else {
    $cols = array_keys($felder);
    $ph = array_fill(0, count($cols), '?');
    $db->execute("INSERT INTO mail_regeln (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")", array_values($felder));
    Response::success(['id' => (int)$db->queryValue("SELECT LAST_INSERT_ID()")]);
}
