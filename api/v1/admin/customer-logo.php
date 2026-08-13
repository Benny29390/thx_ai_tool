<?php
/**
 * POST   /admin/customers/{id}/logo   – Logo hochladen
 * DELETE /admin/customers/{id}/logo   – Logo löschen
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin()) {
    Response::error('Kein Zugriff', 403);
}

$db  = Database::getInstance();
$cid = (int) ($_GET['customer_id'] ?? 0);
if (!$cid) Response::error('Ungültige Kunden-ID', 400);

$customer = $db->queryOne("SELECT id, slug, logo_path FROM customers WHERE id = ?", [$cid]);
if (!$customer) Response::error('Kunde nicht gefunden', 404);

$method = $_SERVER['REQUEST_METHOD'];

/* ---- LÖSCHEN ---- */
if ($method === 'DELETE') {
    if ($customer['logo_path']) {
        $full = ROOT_PATH . '/uploads/customers/logos/' . basename($customer['logo_path']);
        if (file_exists($full)) @unlink($full);
    }
    $db->execute("UPDATE customers SET logo_path = NULL WHERE id = ?", [$cid]);
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

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName = 'logo_' . $customer['slug'] . '_' . time() . '.' . $ext;
$uploadDir = ROOT_PATH . '/uploads/customers/logos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$dest = $uploadDir . $safeName;

// Altes Logo löschen
if ($customer['logo_path']) {
    $old = $uploadDir . basename($customer['logo_path']);
    if (file_exists($old)) @unlink($old);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Response::error('Datei konnte nicht gespeichert werden', 500);
}

$db->execute("UPDATE customers SET logo_path = ? WHERE id = ?", [$safeName, $cid]);

Response::success(['logo_url' => '/uploads/customers/logos/' . $safeName], 'Logo gespeichert');
