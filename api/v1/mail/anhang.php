<?php
/** GET /mail/anhang?id=X — Datei-Download (mit Path-Traversal-Schutz) */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);

$db = Database::getInstance();
$row = $db->queryOne("SELECT * FROM mail_anhaenge WHERE id = ?", [$id]);
if (!$row) Response::notFound('Anhang nicht gefunden');

$pfad = $row['pfad'];
// Path-Traversal-Schutz: muss in /var/www/storage/mail/anhaenge/ liegen
$basis = realpath('/var/www/storage/mail/anhaenge');
$realPfad = realpath($pfad);
if (!$realPfad || strpos($realPfad, $basis) !== 0 || !is_readable($realPfad)) {
    Response::notFound('Datei nicht zugänglich');
}

header('Content-Type: ' . ($row['mime_typ'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $row['dateiname']) . '"');
header('Content-Length: ' . filesize($realPfad));
readfile($realPfad);
