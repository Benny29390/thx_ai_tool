<?php
/**
 * GET  /lam/asana-kunde-konfig?customer_id=X  → aktuelle Konfig
 * POST /lam/asana-kunde-konfig
 *   Body: { customer_id, asana_projekt_gid?, asana_projekt_name?, asana_section_gid?, asana_section_name? }
 */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();

if ($method === 'GET') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    if (!Auth::canAccessCustomer($customerId)) Response::forbidden();
    $row = $db->queryOne(
        "SELECT asana_projekt_gid, asana_projekt_name, asana_section_gid, asana_section_name
         FROM customers WHERE id = ?",
        [$customerId]
    );
    Response::success($row ?: []);
}

if ($method !== 'POST') Response::error('Nur GET oder POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$customerId = (int)($input['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$svc->setzeAsanaKundenKonfig(
    $customerId,
    $input['asana_projekt_gid'] ?? null,
    $input['asana_projekt_name'] ?? null,
    $input['asana_section_gid'] ?? null,
    $input['asana_section_name'] ?? null
);
Response::success(['ok' => true]);
