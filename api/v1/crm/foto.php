<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id fehlt');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
if (empty($_FILES['foto']['tmp_name'])) Response::error('Keine Datei');

$dir = '/var/www/uploads/crm/avatars';
if (!is_dir($dir)) mkdir($dir, 0775, true);

$mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['foto']['tmp_name']);
finfo_close($finfo);
if (!isset($mimes[$mime])) Response::error('Nur JPG/PNG/WEBP/GIF erlaubt');

$maxKb = (int)\Core\Settings::get('crm_avatar_max_kb', 1024);
if ($_FILES['foto']['size'] > $maxKb * 1024) Response::error('Datei zu groß (max ' . $maxKb . ' KB)');

$filename = $id . '.' . $mimes[$mime];
$target = $dir . '/' . $filename;
if (!move_uploaded_file($_FILES['foto']['tmp_name'], $target)) Response::error('Upload fehlgeschlagen');

$path = '/uploads/crm/avatars/' . $filename;
$db = Database::getInstance();
$db->update('crm_kontakte', ['foto_path' => $path], 'id = ?', [$id]);
Response::success(['foto_path' => $path], 'Foto gespeichert');
