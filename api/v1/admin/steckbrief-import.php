<?php

/**
 * Steckbrief-Dokument-Import (Stufe A).
 *
 * POST /admin/customers/{id}/steckbrief-import
 *   - multipart-Upload: { file }
 *   - oder JSON-Body:   { text, label? }   — Text statt Datei
 *   -> { id, status: 'uploaded', original_filename, ... }
 *
 * POST /admin/customers/{id}/steckbrief-import/{importId}/analyze
 *   -> { id, status: 'ready', proposed_cards_decoded: [...] }
 *
 * POST /admin/customers/{id}/steckbrief-import/{importId}/commit
 *   - Body: { accepted: [0, 2, 3, ...] }   Indizes aus proposed_cards_decoded
 *   -> { imported, card_ids: [...] }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\SteckbriefImportService;

require_once SERVICES_PATH . '/SteckbriefImportService.php';

$db = Database::getInstance();
$customerId = (int) ($_GET['customer_id'] ?? 0);
$importId = (int) ($_GET['import_id'] ?? 0);
$action = (string) ($_GET['action'] ?? '');
$userId = (int) Auth::id();
$method = $_SERVER['REQUEST_METHOD'];

if ($customerId <= 0) Response::error('customer_id fehlt');

// OpenAI-Key
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") ?: [] as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);
$openaiKey = (string) ($settings['openai_api_key'] ?? '');
if ($openaiKey === '') Response::error('OpenAI API-Key nicht konfiguriert');

$uploadDir = ROOT_PATH . '/uploads/customers/' . $customerId . '/steckbrief-import';
$svc = new SteckbriefImportService($db, $openaiKey, $uploadDir);

if ($method !== 'POST') Response::error('Methode nicht erlaubt');

// 1) Upload / Text-Ingest
if ($action === '' && $importId === 0) {
    try {
        if (!empty($_FILES['file'])) {
            $row = $svc->uploadFile($customerId, $_FILES['file'], $userId);
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $text = (string) ($input['text'] ?? '');
            $label = (string) ($input['label'] ?? '');
            if (trim($text) === '') Response::error('Keine Datei und kein Text');
            $row = $svc->ingestText($customerId, $text, $label, $userId);
        }
        Response::success($row, 'Hochgeladen');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Upload fehlgeschlagen');
    }
}

// 2) Analyse (LLM)
if ($action === 'analyze' && $importId > 0) {
    try {
        $row = $svc->analyze($importId);
        Response::success($row, 'Analyse abgeschlossen');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Analyse fehlgeschlagen');
    }
}

// 3) Commit
if ($action === 'commit' && $importId > 0) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $accepted = $input['accepted'] ?? [];
    if (!is_array($accepted)) Response::error('accepted muss ein Array sein');
    try {
        $res = $svc->commit($importId, $accepted, $userId);
        Response::success($res, 'Karten erstellt');
    } catch (\Exception $e) {
        Response::error($e->getMessage() ?: 'Commit fehlgeschlagen');
    }
}

Response::error('Unbekannte Aktion');
