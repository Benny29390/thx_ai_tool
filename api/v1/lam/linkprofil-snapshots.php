<?php
/**
 * GET /lam/linkprofil-snapshots?customer_id=X
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = trim((string)($_GET['customer_id'] ?? ''));
if ($customerId === '') Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer((int)$customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->listeSnapshots($customerId));
