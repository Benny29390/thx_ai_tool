<?php
/**
 * POST /mail/antwort-anhang-upload (multipart, field: anhang)
 * Returnt: { pfad, name, mime, size }
 *
 * Speichert temporär in /var/www/storage/mail/anhaenge/_uploads/{userid}/{token}/,
 * damit es nachher beim antwort-senden referenzierbar ist.
 */
use Core\Auth;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

if (empty($_FILES['anhang']) || $_FILES['anhang']['error'] !== UPLOAD_ERR_OK) {
    Response::error('Datei fehlt', 400);
}

$maxMb = (int)\Core\Settings::get('mail_anhang_max_mb', 25);
if ($_FILES['anhang']['size'] > $maxMb * 1024 * 1024) {
    Response::error('Datei zu groß (max ' . $maxMb . ' MB)', 400);
}

$user = Auth::user();
$uid = (int)($user['id'] ?? 0);

$basis = '/var/www/storage/mail/anhaenge/_uploads/' . $uid;
if (!is_dir($basis)) { @mkdir($basis, 0775, true); }

// Sicherer Dateiname + Eindeutigkeit
$sicherName = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['anhang']['name']);
$zielPfad = $basis . '/' . time() . '-' . bin2hex(random_bytes(4)) . '-' . $sicherName;

if (!move_uploaded_file($_FILES['anhang']['tmp_name'], $zielPfad)) {
    Response::error('Speichern fehlgeschlagen', 500);
}
@chmod($zielPfad, 0664);

Response::success([
    'pfad' => $zielPfad,
    'name' => $_FILES['anhang']['name'],
    'mime' => $_FILES['anhang']['type'] ?: 'application/octet-stream',
    'size' => filesize($zielPfad),
]);
