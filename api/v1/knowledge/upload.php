<?php
/**
 * Knowledge Upload — Datei hochladen, extrahieren, Vorschlag generieren
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

require_once __DIR__ . '/_helpers.php';

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    Response::error('Upload fehlgeschlagen (Code ' . $err . ')');
}

$file = $_FILES['file'];

if ($file['size'] > 25 * 1024 * 1024) {
    Response::error('Datei zu gross (max 25 MB)');
}

require_once SERVICES_PATH . '/DocumentProcessor.php';
$processor = new \Services\DocumentProcessor();

try {
    $result = $processor->processFile($file['tmp_name'], $file['type'], $file['name']);
    $text = $result['text'];
} catch (\Exception $e) {
    Response::error('Text-Extraktion fehlgeschlagen: ' . $e->getMessage());
}

if (mb_strlen(trim($text)) < 50) {
    Response::error('Dokument enthaelt zu wenig Text');
}

// Dedup-Check
$services = knowledgeBuildServices($db);
$contentHash = hash('sha256', trim($text));
$existing = $services['knowledgeService']->findByContentHash($contentHash);
if ($existing) {
    Response::error('Dokument existiert bereits: "' . $existing['title'] . '" (ID ' . $existing['id'] . ')');
}

// Kunde aus POST ableiten
$customerId = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int) $_POST['customer_id'] : null;
knowledgeAssertWriteAccess($customerId);
$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

// Prepare (Chunking + Embedding + LLM-Extraktion)
try {
    $prepared = $services['ingestService']->prepare($text, [
        'customer_name' => $customerName,
    ]);
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

// Extra speichern fuer Commit (source_type, original filename)
$extra = [
    'source_type' => 'upload',
    'source_ref' => $file['name'],
    'customer_id_suggestion' => $customerId,
];

// Session freigeben damit Commit nicht blockiert
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$preparedId = knowledgeSavePrepared($prepared, $extra);
knowledgePreparedResponse($preparedId, $prepared, $extra);
