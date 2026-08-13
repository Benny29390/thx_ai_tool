<?php
/**
 * GET /lam/sitewide-cluster-detail?customer_id=X&domain=Y
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = (int)($_GET['customer_id'] ?? 0);
$domain = trim((string)($_GET['domain'] ?? ''));
if ($customerId <= 0 || $domain === '') Response::error('customer_id und domain erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->getClusterDetails($customerId, $domain));
