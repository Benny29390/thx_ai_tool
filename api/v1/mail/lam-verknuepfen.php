<?php
/** POST /mail/lam-verknuepfen Body: { mail_id, typ: 'anbieter'|'massnahme'|'korrespondenz', ziel_id } */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$mailId = (int)($input['mail_id'] ?? 0);
$typ = (string)($input['typ'] ?? '');
$zielId = trim((string)($input['ziel_id'] ?? ''));
if ($mailId <= 0 || !in_array($typ, ['anbieter', 'massnahme', 'korrespondenz', 'aufgabe'], true) || $zielId === '') {
    Response::error('mail_id, typ, ziel_id erforderlich', 400);
}

$db = Database::getInstance();
// Dublette vermeiden
$exists = $db->queryValue(
    "SELECT id FROM mail_lam_verknuepfung WHERE mail_id = ? AND typ = ? AND ziel_id = ?",
    [$mailId, $typ, $zielId]
);
if (!$exists) {
    $db->execute(
        "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, ?, ?, 0)",
        [$mailId, $typ, $zielId]
    );
}
Response::success(['ok' => true]);
