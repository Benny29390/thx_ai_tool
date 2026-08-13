<?php
/**
 * Knowledge Chat-Import — aus einer Chat-Nachricht ein Wissens-Eintrag erstellen
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';

$messageId = (int) ($input['message_id'] ?? 0);
if (!$messageId) Response::error('Message-ID erforderlich');

$userId = Auth::id();

// Message laden + Zugriff pruefen (Owner oder Shared)
$msg = $db->queryOne(
    "SELECT m.*, c.title as conv_title, c.user_id as owner_id, c.customer_id
     FROM chat_conversation_messages m
     JOIN chat_conversations c ON m.conversation_id = c.id
     WHERE m.id = ?",
    [$messageId]
);
if (!$msg) Response::notFound('Nachricht nicht gefunden');

if ($msg['owner_id'] != $userId) {
    $share = $db->queryOne(
        "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
        [$userId, $msg['conversation_id']]
    );
    if (!$share && !Auth::isAdmin()) Response::forbidden('Kein Zugriff');
}

$content = trim($msg['content']);
if (mb_strlen($content) < 20) Response::error('Nachricht zu kurz');

$services = knowledgeBuildServices($db);
$contentHash = hash('sha256', $content);
$existing = $services['knowledgeService']->findByContentHash($contentHash);
if ($existing) {
    Response::error('Inhalt existiert bereits: "' . $existing['title'] . '"');
}

$customerId = $msg['customer_id'] ?? null;
$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

try {
    $prepared = $services['ingestService']->prepare($content, [
        'customer_name' => $customerName,
    ]);
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

$extra = [
    'source_type' => 'chat',
    'source_ref' => 'msg:' . $messageId . ' / conv:' . $msg['conversation_id'],
    'customer_id_suggestion' => $customerId,
];

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$preparedId = knowledgeSavePrepared($prepared, $extra);
knowledgePreparedResponse($preparedId, $prepared, $extra);
