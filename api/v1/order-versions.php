<?php
/**
 * Order Versions API - Versions-History und Restore
 */

use Core\Auth;
use Core\Response;

global $db, $method, $uri;

// Order-ID und optionale Version aus URI extrahieren
if (preg_match('#^/orders/(\d+)/versions/(\d+)/restore$#', $uri, $matches)) {
    $orderId = (int) $matches[1];
    $versionNumber = (int) $matches[2];
    $action = 'restore';
} elseif (preg_match('#^/orders/(\d+)/versions$#', $uri, $matches)) {
    $orderId = (int) $matches[1];
    $versionNumber = null;
    $action = 'list';
} else {
    Response::notFound('Endpunkt nicht gefunden');
}

// Auftrag laden und Zugriff prüfen
$order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
if (!$order) {
    Response::notFound('Auftrag nicht gefunden');
}

if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
    Response::forbidden();
}

switch ($action) {
    case 'list':
        if ($method !== 'GET') {
            Response::error('Method not allowed', 405);
        }

        $versions = $db->query(
            "SELECT ov.*, u.name as creator_name
             FROM order_versions ov
             LEFT JOIN users u ON ov.created_by = u.id
             WHERE ov.order_id = ?
             ORDER BY ov.version_number DESC",
            [$orderId]
        );

        Response::success($versions);
        break;

    case 'restore':
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }

        // Version finden
        $oldVersion = $db->queryOne(
            "SELECT * FROM order_versions WHERE order_id = ? AND version_number = ?",
            [$orderId, $versionNumber]
        );

        if (!$oldVersion) {
            Response::notFound('Version nicht gefunden');
        }

        // Neue Version als Kopie erstellen
        $maxVersion = $db->queryValue(
            "SELECT MAX(version_number) FROM order_versions WHERE order_id = ?",
            [$orderId]
        );
        $newVersionNumber = ($maxVersion ?? 0) + 1;

        $db->insert('order_versions', [
            'order_id' => $orderId,
            'version_number' => $newVersionNumber,
            'article_content' => $oldVersion['article_content'],
            'briefing_content' => $oldVersion['briefing_content'],
            'change_description' => "Wiederhergestellt von Version {$versionNumber}",
            'change_source' => 'rollback',
            'word_count' => $oldVersion['word_count'],
            'created_by' => Auth::id()
        ]);

        // Auftrag aktualisieren
        $db->update('orders', [
            'current_version' => $newVersionNumber,
            'article_content' => $oldVersion['article_content']
        ], 'id = ?', [$orderId]);

        Response::success([
            'version_number' => $newVersionNumber
        ], 'Version wiederhergestellt');
        break;

    default:
        Response::notFound('Aktion nicht gefunden');
}
