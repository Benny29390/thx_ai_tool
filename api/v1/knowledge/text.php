<?php
/**
 * Knowledge Text — Plain-Text direkt als Wissen
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';

$content = trim($input['content'] ?? '');
$userTitle = trim($input['title'] ?? '');

if (mb_strlen($content) < 20) {
    Response::error('Text zu kurz (min 20 Zeichen)');
}

$services = knowledgeBuildServices($db);
$contentHash = hash('sha256', $content);
$existing = $services['knowledgeService']->findByContentHash($contentHash);
if ($existing) {
    Response::error('Inhalt existiert bereits: "' . $existing['title'] . '" (ID ' . $existing['id'] . ')');
}

$customerId = isset($input['customer_id']) && $input['customer_id'] !== '' ? (int) $input['customer_id'] : null;
knowledgeAssertWriteAccess($customerId);
$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

try {
    $prepared = $services['ingestService']->prepare($content, [
        'customer_name' => $customerName,
    ]);

    // User-Titel hat Vorrang
    if ($userTitle !== '') {
        $prepared['metadata']['title'] = $userTitle;
    }
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

$extra = [
    'source_type' => 'text',
    'source_ref' => null,
    'customer_id_suggestion' => $customerId,
];

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$preparedId = knowledgeSavePrepared($prepared, $extra);
knowledgePreparedResponse($preparedId, $prepared, $extra);
