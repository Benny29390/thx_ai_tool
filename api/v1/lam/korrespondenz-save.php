<?php
/**
 * Korrespondenz anlegen (optional mit Anhang).
 *
 * POST /api/v1/lam/korrespondenz-save  (multipart/form-data wenn Datei dabei)
 * Felder: typ, inhalt, anbieter_id, kontakt_id?, massnahme_id?, vorschlagsliste_eintrag_id?, betreff?, zeitpunkt?
 * Datei:  anhang (optional)
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

// Daten aus $_POST oder JSON
$isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0;
$input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: $_POST);

$data = [
    'typ' => $input['typ'] ?? 'notiz',
    'inhalt' => $input['inhalt'] ?? '',
    'anbieter_id' => $input['anbieter_id'] ?? '',
    'kontakt_id' => $input['kontakt_id'] ?? null,
    'massnahme_id' => $input['massnahme_id'] ?? null,
    'vorschlagsliste_eintrag_id' => $input['vorschlagsliste_eintrag_id'] ?? null,
    'betreff' => $input['betreff'] ?? null,
    'zeitpunkt' => $input['zeitpunkt'] ?? null,
    'user_id' => Auth::id(),
];

// Anhang verarbeiten — sicherer Pfad unter storage/lam-attachments/
if (!empty($_FILES['anhang']) && $_FILES['anhang']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['anhang'];
    if ($file['size'] > 25 * 1024 * 1024) {
        Response::error('Datei zu groß (max 25 MB)');
    }
    $allowed = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv', 'message/rfc822',
        'application/zip', 'application/octet-stream',
    ];
    $mime = function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : 'application/octet-stream';
    if (!in_array($mime, $allowed, true)) {
        Response::error('Dateityp nicht erlaubt: ' . $mime);
    }
    $dir = ROOT_PATH . '/storage/lam-attachments';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $sicherName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
    $zielName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . substr($sicherName, 0, 80);
    $zielPfad = $dir . '/' . $zielName;
    if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
        Response::error('Datei konnte nicht gespeichert werden');
    }
    $data['anhang_pfad'] = 'lam-attachments/' . $zielName;
    $data['anhang_originalname'] = $file['name'];
    $data['anhang_mime'] = $mime;
    $data['anhang_groesse'] = (int)$file['size'];
}

try {
    $id = $svc->speichereKorrespondenz($data);
    Response::success(['id' => $id], 'Korrespondenz angelegt');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
