<?php
/**
 * Orders API - Auftrags-Verwaltung
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelnen Auftrag laden
            $order = $db->queryOne(
                "SELECT o.*, c.name as customer_name, u.name as creator_name,
                        ctx.name as context_name
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 JOIN users u ON o.created_by = u.id
                 LEFT JOIN contexts ctx ON o.context_id = ctx.id
                 WHERE o.id = ?",
                [$id]
            );

            if (!$order) {
                Response::notFound('Auftrag nicht gefunden');
            }

            if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
                Response::forbidden();
            }

            // Chat-Nachrichten laden
            $order['chat_messages'] = $db->query(
                "SELECT * FROM order_chat_messages WHERE order_id = ? ORDER BY created_at ASC",
                [$id]
            );

            // Aktuelle Versions-Info
            $order['versions_count'] = $db->queryValue(
                "SELECT COUNT(*) FROM order_versions WHERE order_id = ?",
                [$id]
            );

            // Pending rule suggestions
            $order['pending_suggestions'] = $db->query(
                "SELECT * FROM order_rule_suggestions WHERE order_id = ? AND status = 'pending'",
                [$id]
            );

            Response::success($order);
        } else {
            // Liste aller Aufträge
            $customerId = Auth::isAdmin() ? ($_GET['customer_id'] ?? null) : Auth::customerId();

            $where = ['1=1'];
            $params = [];

            if ($customerId) {
                $where[] = 'o.customer_id = ?';
                $params[] = (int) $customerId;
            }

            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $where[] = 'o.status = ?';
                $params[] = $_GET['status'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $orders = $db->query(
                "SELECT o.*, c.name as customer_name, u.name as creator_name,
                        ctx.name as context_name
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 JOIN users u ON o.created_by = u.id
                 LEFT JOIN contexts ctx ON o.context_id = ctx.id
                 $whereClause
                 ORDER BY o.updated_at DESC",
                $params
            );

            Response::success($orders);
        }
        break;

    case 'POST':
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            Response::error('Titel erforderlich');
        }

        $customerId = $input['customer_id'] ?? Auth::customerId();
        $contextId = null;

        // Backward-Compat: context_id optional
        if (!empty($input['context_id'])) {
            $context = $db->queryOne("SELECT * FROM contexts WHERE id = ? AND is_active = 1", [(int)$input['context_id']]);
            if ($context) {
                $contextId = $context['id'];
                $customerId = $context['customer_id'] ?: $customerId;
            }
        }

        if ($customerId && !Auth::isAdmin() && !Auth::canAccessCustomer($customerId)) {
            Response::forbidden();
        }

        $model = $input['model'] ?? null;

        $orderId = $db->insert('orders', [
            'context_id' => $contextId,
            'customer_id' => $customerId ?: null,
            'created_by' => Auth::id(),
            'title' => $title,
            'model' => $model,
            'metadata' => json_encode($input['metadata'] ?? [])
        ]);

        Response::success(['id' => $orderId], 'Auftrag erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$id]);
        if (!$order) {
            Response::notFound('Auftrag nicht gefunden');
        }

        if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
            Response::forbidden();
        }

        $updates = [];
        if (isset($input['title'])) $updates['title'] = trim($input['title']);
        if (isset($input['status'])) $updates['status'] = $input['status'];
        if (isset($input['model'])) $updates['model'] = $input['model'];
        if (isset($input['metadata'])) $updates['metadata'] = json_encode($input['metadata']);

        if (!empty($updates)) {
            $db->update('orders', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Auftrag aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $order = $db->queryOne("SELECT * FROM orders WHERE id = ?", [$id]);
        if (!$order) {
            Response::notFound('Auftrag nicht gefunden');
        }

        if ($order['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($order['customer_id'])) {
            Response::forbidden();
        }

        $db->delete('orders', 'id = ?', [$id]);

        Response::success(null, 'Auftrag gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
