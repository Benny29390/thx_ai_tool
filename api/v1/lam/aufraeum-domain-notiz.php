<?php
/**
 * Per-Domain-Notiz: Lesen + Schreiben.
 * GET  /api/v1/lam/aufraeum-domain-notiz?customer_id=X&domain=Y
 * POST /api/v1/lam/aufraeum-domain-notiz  Body: { customer_id, domain, notiz }
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
    $domain     = trim((string)($_GET['domain'] ?? ''));
    if ($customerId <= 0 || $domain === '') Response::error('customer_id + domain erforderlich', 400);
    Response::success(['notiz' => $svc->getDomainNotiz($customerId, $domain)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);
    $customerId = (int)($json['customer_id'] ?? 0);
    $domain     = trim((string)($json['domain'] ?? ''));
    $notiz      = isset($json['notiz']) ? (string)$json['notiz'] : null;
    if ($customerId <= 0 || $domain === '') Response::error('customer_id + domain erforderlich', 400);
    try {
        $svc->setDomainNotiz($customerId, $domain, $notiz);
        Response::success(['gespeichert' => true]);
    } catch (\Throwable $e) {
        Response::error('Speichern fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

Response::error('Nur GET oder POST', 405);
