<?php
/**
 * Prompt-Insights — Imports API
 *
 * GET    /admin/prompt-insights/imports                  Liste der eigenen Imports
 * POST   /admin/prompt-insights/imports                  Upload + Verarbeitung (multipart/form-data: zip)
 * DELETE /admin/prompt-insights/imports/{id}             Lösch-Workflow (alle abhängigen Daten cascaden)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PromptInsightsService;

if (!Auth::can(CAP_PROMPT_INSIGHTS)) Response::forbidden();

require_once SERVICES_PATH . '/PromptInsightsService.php';
$svc = new PromptInsightsService(Database::getInstance());
$user = Auth::user();
$userId = (int)($user['id'] ?? 0);
if (!$userId) Response::error('Nicht eingeloggt');

$method   = $_SERVER['REQUEST_METHOD'];
$importId = (int)($_GET['import_id'] ?? 0);

if ($method === 'GET') {
    Response::success(['imports' => $svc->listImports($userId)]);
}

if ($method === 'POST') {
    if (empty($_FILES['zip'])) {
        // Tritt auf wenn POST-Größe > post_max_size (PHP verwirft dann den ganzen Body)
        $serverPost = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        Response::error('Datei zu groß? Upload kam nicht beim Server an. '
            . 'CONTENT_LENGTH=' . round($serverPost / 1024 / 1024, 1) . ' MB · '
            . 'post_max_size=' . ini_get('post_max_size') . ' · '
            . 'upload_max_filesize=' . ini_get('upload_max_filesize'));
    }
    if ($_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'Datei > upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'Datei > MAX_FILE_SIZE im Form',
            UPLOAD_ERR_PARTIAL    => 'Upload abgebrochen — nur teilweise empfangen',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewählt',
            UPLOAD_ERR_NO_TMP_DIR => 'Server-Temp-Verzeichnis fehlt',
            UPLOAD_ERR_CANT_WRITE => 'Server konnte nicht auf Disk schreiben',
            UPLOAD_ERR_EXTENSION  => 'PHP-Extension blockiert Upload',
        ];
        $msg = $codes[$_FILES['zip']['error']] ?? ('Unbekannter Upload-Fehler (Code ' . $_FILES['zip']['error'] . ')');
        Response::error('Upload-Fehler: ' . $msg);
    }
    $tmp = $_FILES['zip']['tmp_name'];
    $orig = $_FILES['zip']['name'];
    if (!str_ends_with(strtolower($orig), '.zip')) Response::error('Bitte ZIP-Datei hochladen');
    try {
        $res = $svc->importZip($tmp, $orig, $userId);
        Response::success($res, "Import erfolgreich: {$res['chats']} Chats, {$res['messages']} Nachrichten ({$res['source']})");
    } catch (\Throwable $e) {
        Response::error('Import fehlgeschlagen: ' . $e->getMessage());
    }
}

if ($method === 'DELETE') {
    if (!$importId) Response::error('import_id fehlt');
    $ok = $svc->deleteImport($importId, $userId);
    if (!$ok) Response::notFound('Import nicht gefunden');
    Response::success(['deleted' => $importId], 'Import gelöscht');
}

Response::error('Methode nicht unterstützt', 405);
