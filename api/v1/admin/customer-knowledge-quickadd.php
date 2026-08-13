<?php
/**
 * Customer Knowledge Quick-Add — prepare + commit in einem Call (kein Vorschau-Modal).
 *
 * POST /admin/customers/{id}/knowledge-quickadd
 * Body: {source: 'url'|'text', url?, title?, content?}  ODER multipart {source: 'file', file}
 *
 * Liefert {document_id, title}.
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\DocumentProcessor;
use Services\WebSearchService;

global $db, $method, $input;

if (!Auth::isAdmin()) Response::forbidden();
if ($method !== 'POST') Response::error('Method not allowed', 405);

$customerId = (int) ($_GET['customer_id'] ?? 0);
if (!$customerId) Response::error('Customer-ID erforderlich');

$customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

require_once API_PATH . '/v1/knowledge/_helpers.php';
$services = knowledgeBuildServices($db);

$source = $input['source'] ?? $_POST['source'] ?? '';
$text = '';
$sourceRef = null;
$titleHint = null;

if ($source === 'url') {
    $url = trim($input['url'] ?? $_POST['url'] ?? '');
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) Response::error('Gueltige URL erforderlich');

    require_once SERVICES_PATH . '/WebSearchService.php';
    $braveKey = $services['settings']['brave_search_api_key'] ?? '';
    $ws = new WebSearchService($braveKey);
    $text = $ws->fetchUrlContent($url, 15, 50000) ?? '';
    if (mb_strlen(trim($text)) < 100) Response::error('URL konnte nicht gelesen werden oder enthaelt zu wenig Text');

    // LLM-Bereinigung
    $openaiKey = $services['settings']['openai_api_key'] ?? '';
    if (!empty($openaiKey) && mb_strlen($text) > 500) {
        try {
            require_once SERVICES_PATH . '/AIService.php';
            $cleanAi = new AIService($openaiKey, 'openai');
            $cleanAi->setModel('gpt-4o-mini');
            $cleanAi->setTimeout(45);
            $cleanResp = $cleanAi->chat([
                ['role' => 'user', 'content' => "Extrahiere NUR den inhaltlichen Kerntext (Fliesstext, Produktinfos, Artikel). Entferne Navigation, Cookies, Footer, Wiederholungen, UI-Labels.\n\n---\n" . mb_substr($text, 0, 30000) . "\n---"]
            ], 'Du bist ein Text-Bereiniger. Antwortest NUR mit dem bereinigten Fliesstext, keine Kommentare.');
            $cleaned = trim($cleanResp['content'] ?? '');
            if (mb_strlen($cleaned) > 100) $text = $cleaned;
        } catch (\Exception $e) {}
    }
    $sourceRef = $url;
} elseif ($source === 'file') {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::error('Upload fehlgeschlagen (Code ' . ($_FILES['file']['error'] ?? '?') . ')');
    }
    $file = $_FILES['file'];
    if ($file['size'] > 25 * 1024 * 1024) Response::error('Datei zu gross (max 25 MB)');

    require_once SERVICES_PATH . '/DocumentProcessor.php';
    $processor = new DocumentProcessor();
    try {
        $result = $processor->processFile($file['tmp_name'], $file['type'], $file['name']);
        $text = $result['text'];
    } catch (\Exception $e) {
        Response::error('Text-Extraktion fehlgeschlagen: ' . $e->getMessage());
    }
    if (mb_strlen(trim($text)) < 50) Response::error('Dokument enthaelt zu wenig Text');
    $sourceRef = $file['name'];
} elseif ($source === 'text') {
    $text = trim($input['content'] ?? $_POST['content'] ?? '');
    $titleHint = trim($input['title'] ?? $_POST['title'] ?? '') ?: null;
    if (mb_strlen($text) < 30) Response::error('Inhalt zu kurz (mind. 30 Zeichen)');
    $sourceRef = 'manual';
} else {
    Response::error('source: url | file | text');
}

// Dedup
$contentHash = hash('sha256', trim($text));
$existing = $services['knowledgeService']->findByContentHash($contentHash);
if ($existing) {
    Response::success([
        'document_id' => (int) $existing['id'],
        'title' => $existing['title'],
        'duplicate' => true,
    ], 'Dokument existierte bereits');
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

try {
    $prepared = $services['ingestService']->prepare($text, [
        'customer_name' => $customer['name'] ?? null,
    ]);
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

$overrides = [
    'title' => $titleHint ?: ($prepared['metadata']['title'] ?? 'Ohne Titel'),
    'description' => $prepared['metadata']['description'],
    'customer_id' => $customerId,
    'category' => $prepared['metadata']['category'],
    'tags' => $prepared['metadata']['tags'],
];
$meta = [
    'source_type' => $source === 'file' ? 'upload' : $source,
    'source_ref' => $sourceRef,
    'created_by' => Auth::id(),
];

try {
    $docId = $services['ingestService']->commit($prepared, $overrides, $meta);
} catch (\Exception $e) {
    Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
}

Response::success([
    'document_id' => $docId,
    'title' => $overrides['title'],
    'category' => $overrides['category'],
    'chunks' => count($prepared['chunks']),
    'entities' => count($prepared['metadata']['entities'] ?? []),
], 'Wissen hinzugefuegt');
