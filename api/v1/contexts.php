<?php
/**
 * Contexts API - Kontext-Verwaltung
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

// Service laden
require_once SERVICES_PATH . '/ContextService.php';
$contextService = new \Services\ContextService($db);

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelnen Kontext mit Items laden
            $context = $contextService->getContextWithItems((int) $id);

            if (!$context) {
                Response::notFound('Kontext nicht gefunden');
            }

            // Zugriff prüfen (Kontexte ohne Kunde sind für alle zugänglich)
            if ($context['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($context['customer_id'])) {
                Response::forbidden();
            }

            Response::success($context);
        } else {
            // Liste aller Kontexte
            $customerId = Auth::isAdmin() ? ($_GET['customer_id'] ?? null) : Auth::customerId();

            $where = ['c.is_active = 1'];
            $params = [];

            if ($customerId) {
                $where[] = 'c.customer_id = ?';
                $params[] = (int) $customerId;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $contexts = $db->query(
                "SELECT c.*, cu.name as customer_name, u.name as creator_name,
                        (SELECT COUNT(*) FROM context_items ci WHERE ci.context_id = c.id) as items_count,
                        (SELECT COUNT(*) FROM orders o WHERE o.context_id = c.id) as orders_count
                 FROM contexts c
                 LEFT JOIN customers cu ON c.customer_id = cu.id
                 JOIN users u ON c.created_by = u.id
                 $whereClause
                 ORDER BY c.updated_at DESC",
                $params
            );

            Response::success($contexts);
        }
        break;

    case 'POST':
        // Manager+ erforderlich
        if (!Auth::hasRole(ROLE_MANAGER)) {
            Response::forbidden();
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            Response::error('Name erforderlich');
        }

        $customerId = $input['customer_id'] ?? null;
        if ($customerId) {
            $customerId = (int) $customerId;
        }

        $items = $input['items'] ?? [];

        $contextId = $contextService->createContext([
            'customer_id' => $customerId,
            'created_by' => Auth::id(),
            'name' => $name,
            'description' => trim($input['description'] ?? '') ?: null
        ], $items);

        Response::success(['id' => $contextId], 'Kontext erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $context = $db->queryOne("SELECT * FROM contexts WHERE id = ?", [$id]);
        if (!$context) {
            Response::notFound('Kontext nicht gefunden');
        }

        if ($context['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($context['customer_id'])) {
            Response::forbidden();
        }

        $items = isset($input['items']) ? $input['items'] : null;

        $contextService->updateContext((int) $id, $input, $items);

        Response::success(null, 'Kontext aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $context = $db->queryOne("SELECT * FROM contexts WHERE id = ?", [$id]);
        if (!$context) {
            Response::notFound('Kontext nicht gefunden');
        }

        if ($context['customer_id'] && !Auth::isAdmin() && !Auth::canAccessCustomer($context['customer_id'])) {
            Response::forbidden();
        }

        // Soft-Delete
        $db->update('contexts', ['is_active' => 0], 'id = ?', [$id]);

        Response::success(null, 'Kontext deaktiviert');
        break;

    default:
        Response::error('Method not allowed', 405);
}
