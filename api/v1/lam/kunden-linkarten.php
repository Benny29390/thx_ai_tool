<?php
/**
 * CRUD fuer kundenspezifische Linkarten (z.B. "cookie_banner" nur fuer STEINMANN).
 *
 * GET    /api/v1/lam/kunden-linkarten?customer_id=X
 * POST   /api/v1/lam/kunden-linkarten   Body: { customer_id, linkart_key?, label, default_strategie?, beschreibung? }
 * DELETE /api/v1/lam/kunden-linkarten   Body: { id }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    Response::success(['linkarten' => $svc->listeKundenLinkarten($customerId)]);
}

$json = json_decode(file_get_contents('php://input'), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($json['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    try {
        $r = $svc->speichereKundenLinkart($customerId, $json);
        Response::success($r);
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = trim((string)($json['id'] ?? ''));
    if ($id === '') Response::error('id erforderlich', 400);
    Response::success(['geloescht' => $svc->loescheKundenLinkart($id)]);
}

Response::error('Nur GET, POST oder DELETE', 405);
