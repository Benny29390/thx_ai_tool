<?php
/**
 * Order Duplicate API - Auftrag duplizieren
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input, $uri;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Order-ID aus URI extrahieren
if (!preg_match('#^/orders/(\d+)/duplicate$#', $uri, $matches)) {
    Response::notFound('Endpunkt nicht gefunden');
}

$orderId = (int) $matches[1];

// Auftrag laden und Zugriff prüfen
$order = $db->queryOne(
    "SELECT o.* FROM orders o WHERE o.id = ?",
    [$orderId]
);

if (!$order) {
    Response::notFound('Auftrag nicht gefunden');
}

if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
    Response::forbidden();
}

try {
    $newId = $db->insert('orders', [
        'title' => $order['title'] . ' (Kopie)',
        'context_id' => $order['context_id'],
        'customer_id' => $order['customer_id'],
        'briefing_content' => $order['briefing_content'],
        'model' => $order['model'],
        'status' => 'briefing',
        'current_version' => 0,
        'article_content' => null,
        'created_by' => Auth::id()
    ]);

    Response::success(['id' => $newId], 'Auftrag dupliziert');
} catch (\Exception $e) {
    Response::error('Duplizierung fehlgeschlagen: ' . $e->getMessage());
}
