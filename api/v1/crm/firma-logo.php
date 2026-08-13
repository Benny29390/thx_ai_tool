<?php
/**
 * POST   /crm/firmen/{id}/logo   – Logo hochladen
 * DELETE /crm/firmen/{id}/logo   – Logo löschen
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\CrmKnowledgeSyncQueue;

if (!Auth::can('crm')) Response::error('Kein Zugriff', 403);

$db  = Database::getInstance();
$fid = (int) ($_GET['firma_id'] ?? 0);
if (!$fid) Response::error('Ungültige Firma-ID', 400);

$firma = $db->queryOne("SELECT id, firmenname, logo_path FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$fid]);
if (!$firma) Response::error('Firma nicht gefunden', 404);

$uploadDir = ROOT_PATH . '/uploads/crm/firmenlogos/';
$method = $_SERVER['REQUEST_METHOD'];

/* ---- LÖSCHEN ---- */
if ($method === 'DELETE') {
    if ($firma['logo_path']) {
        $full = $uploadDir . basename($firma['logo_path']);
        if (file_exists($full)) @unlink($full);
    }
    $db->execute("UPDATE crm_firmen SET logo_path = NULL WHERE id = ?", [$fid]);
    CrmKnowledgeSyncQueue::enqueueFirma($fid);
    Response::success([], 'Logo gelöscht');
}

/* ---- UPLOAD ---- */
if ($method !== 'POST') Response::error('Methode nicht erlaubt', 405);

if (empty($_FILES['logo'])) Response::error('Keine Datei übertragen', 400);

$file    = $_FILES['logo'];
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$maxSize = 2 * 1024 * 1024; // 2 MB

if ($file['error'] !== UPLOAD_ERR_OK) Response::error('Upload-Fehler: Code ' . $file['error'], 400);
if (!in_array($file['type'], $allowed, true)) Response::error('Nur JPG, PNG, GIF, WebP und SVG erlaubt', 400);
if ($file['size'] > $maxSize) Response::error('Datei zu groß (max. 2 MB)', 400);

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName = 'firma_' . $fid . '_' . time() . '.' . $ext;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
    @chown($uploadDir, 'www-data');
}

$dest = $uploadDir . $safeName;

// Altes Logo löschen
if ($firma['logo_path']) {
    $old = $uploadDir . basename($firma['logo_path']);
    if (file_exists($old)) @unlink($old);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Response::error('Datei konnte nicht gespeichert werden', 500);
}

$db->execute("UPDATE crm_firmen SET logo_path = ? WHERE id = ?", [$safeName, $fid]);
CrmKnowledgeSyncQueue::enqueueFirma($fid);

Response::success(['logo_url' => '/uploads/crm/firmenlogos/' . $safeName], 'Logo gespeichert');
