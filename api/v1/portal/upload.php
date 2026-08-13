<?php
/** Kundenportal-Chat: Datei-Upload zu einer Frage (PDF/DOCX/TXT/Bild). Text wird fuer die KI extrahiert. */
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $svc, $customerId, $userId, $resolveConversation

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') Response::error('Nur POST', 405);

$convId = (int) ($_POST['conversation_id'] ?? 0);
if ($convId) $resolveConversation($convId); else $convId = null;

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    Response::error('Keine Datei hochgeladen');
}
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) Response::error('Upload-Fehler (Code: ' . $file['error'] . ')');
if ($file['size'] > 20 * 1024 * 1024) Response::error('Datei zu groß (max. 20 MB)');

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','docx','txt','md','csv','html','htm','png','jpg','jpeg','gif','webp'];
if (!in_array($ext, $allowed, true)) Response::error('Dateityp nicht erlaubt (' . htmlspecialchars($ext) . ')');

// Storage ausserhalb des Web-Roots, pro Kunde getrennt
$dir = UPLOADS_PATH . '/portal/' . $customerId;
if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) Response::error('Speicherort nicht verfügbar');

$stored = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) Response::error('Datei konnte nicht gespeichert werden');

// Text extrahieren (fuer KI-Kontext) — nur Text-Formate
$text = null;
$textExtractable = ['txt','md','pdf','docx','html','htm','csv'];
if (in_array($ext, $textExtractable, true)) {
    try {
        if ($ext === 'csv' || $ext === 'txt' || $ext === 'md') {
            $text = (string) file_get_contents($dir . '/' . $stored);
        } else {
            require_once SERVICES_PATH . '/DocumentProcessor.php';
            $res = (new \Services\DocumentProcessor())->processFile($dir . '/' . $stored, $file['type'] ?? '', $file['name']);
            $text = $res['text'] ?? null;
        }
        if ($text !== null) $text = mb_substr(trim($text), 0, 60000);
    } catch (\Throwable $e) { $text = null; }
}

$id = $svc->storeAttachmentRecord($customerId, $convId, $file['name'], $stored, $file['type'] ?? null, (int)$file['size'], $text, $userId);
Response::success(['id' => $id, 'name' => $file['name'], 'size' => (int)$file['size']], 'Hochgeladen');
