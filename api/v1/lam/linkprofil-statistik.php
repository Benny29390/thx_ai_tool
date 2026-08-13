<?php
/**
 * GET /lam/linkprofil-statistik?customer_id=X
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->getLinkprofilStatistik($customerId));
