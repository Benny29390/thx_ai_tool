<?php
/**
 * Order History API - Chronologischer Log aller Aktionen einer Order
 * GET /orders/{id}/history
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$orderId = (int) $matches[1];

// Order laden + Zugriff prüfen
$order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
if (!$order) {
    Response::notFound('Order nicht gefunden');
}
if ($order['customer_id'] && !Auth::canAccessCustomer((int)$order['customer_id'])) {
    Response::forbidden();
}

// 1. Chat Messages
$messages = $db->query(
    "SELECT id, role, content, phase, tokens_used, model_used, created_at
     FROM order_chat_messages
     WHERE order_id = ?
     ORDER BY created_at DESC",
    [$orderId]
);

// 2. Versions
$versions = $db->query(
    "SELECT ov.id, ov.version_number, ov.change_description, ov.change_source, ov.word_count, ov.created_at,
            u.name as creator_name
     FROM order_versions ov
     LEFT JOIN users u ON ov.created_by = u.id
     WHERE ov.order_id = ?
     ORDER BY ov.created_at DESC",
    [$orderId]
);

// 3. Usage Logs (via metadata->order_id)
$usage = $db->query(
    "SELECT id, action_type, model_used, tokens_input, tokens_output, cost_estimate, created_at
     FROM usage_logs
     WHERE JSON_EXTRACT(metadata, '$.order_id') = ?
     ORDER BY created_at DESC",
    [$orderId]
);

// 4. Rule Suggestions
$suggestions = $db->query(
    "SELECT id, rule_name, suggested_rule, status, created_at
     FROM order_rule_suggestions
     WHERE order_id = ?
     ORDER BY created_at DESC",
    [$orderId]
);

Response::success([
    'messages' => $messages,
    'versions' => $versions,
    'usage' => $usage,
    'suggestions' => $suggestions
]);
