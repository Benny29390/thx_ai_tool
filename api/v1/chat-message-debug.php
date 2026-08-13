<?php
/**
 * Chat Message Debug — Detail zu einer Assistant-Nachricht (fuer das Info-Panel im Chat).
 * Liefert Modell, Tokens, Dauer, finaler System-Prompt und genutzte Wissens-Chunks
 * aus llm_request_detail (per message_id). Nur Owner der Konversation oder Admin.
 *
 * GET ?id=<message_id>
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($messageId <= 0) {
    Response::error('Nachrichten-ID fehlt');
}

// Nachricht + Konversation laden und Zugriff pruefen (Owner oder Admin)
$msg = $db->queryOne(
    "SELECT m.id, m.conversation_id, m.model_used, m.tokens_input, m.tokens_output, m.created_at,
            c.user_id
     FROM chat_conversation_messages m
     JOIN chat_conversations c ON c.id = m.conversation_id
     WHERE m.id = ?",
    [$messageId]
);
if (!$msg) {
    Response::error('Nachricht nicht gefunden', 404);
}
if ((int)$msg['user_id'] !== (int)Auth::id() && !Auth::isAdmin()) {
    Response::forbidden();
}

// Detail aus dem Log (jüngster Eintrag zu dieser Nachricht)
$detail = $db->queryOne(
    "SELECT l.model, l.provider, l.tokens_input, l.tokens_output, l.tokens_total,
            l.total_ms, l.ttft_ms, l.created_at, l.success,
            d.system_prompt, d.user_message, d.response_text, d.rag_chunks, d.rerank_meta
     FROM llm_request_detail d
     JOIN llm_request_log l ON l.id = d.log_id
     WHERE d.message_id = ?
     ORDER BY d.id DESC
     LIMIT 1",
    [$messageId]
);

if (!$detail) {
    // Kein Detail vorhanden (ältere Nachricht, nach 90 Tagen rotiert, oder Dual-Modus).
    // Trotzdem das Wenige liefern, das in der Nachricht selbst steht.
    Response::success([
        'has_detail'    => false,
        'model'         => $msg['model_used'],
        'tokens_input'  => (int)$msg['tokens_input'],
        'tokens_output' => (int)$msg['tokens_output'],
        'created_at'    => $msg['created_at'],
    ]);
}

$chunks = [];
if (!empty($detail['rag_chunks'])) {
    $decoded = json_decode($detail['rag_chunks'], true);
    if (is_array($decoded)) $chunks = $decoded;
}
$rerank = null;
if (!empty($detail['rerank_meta'])) {
    $decoded = json_decode($detail['rerank_meta'], true);
    if (is_array($decoded)) $rerank = $decoded;
}

Response::success([
    'has_detail'    => true,
    'model'         => $detail['model'],
    'provider'      => $detail['provider'],
    'tokens_input'  => (int)$detail['tokens_input'],
    'tokens_output' => (int)$detail['tokens_output'],
    'tokens_total'  => (int)$detail['tokens_total'],
    'total_ms'      => $detail['total_ms'] !== null ? (int)$detail['total_ms'] : null,
    'ttft_ms'       => $detail['ttft_ms'] !== null ? (int)$detail['ttft_ms'] : null,
    'created_at'    => $detail['created_at'],
    'success'       => (int)$detail['success'] === 1,
    'system_prompt' => $detail['system_prompt'],
    'chunks'        => $chunks,
    'rerank'        => $rerank,
]);
