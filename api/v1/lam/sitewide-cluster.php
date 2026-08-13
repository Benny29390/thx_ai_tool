<?php
/**
 * GET /lam/sitewide-cluster?customer_id=X&schwelle=5
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
$schwelle = !empty($_GET['schwelle']) ? max(2, (int)$_GET['schwelle']) : 5;

if ($customerId && !Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$cluster = $svc->findeSitewideCluster($customerId, $schwelle);

// Auf erlaubte Kunden filtern (wenn kein customer_id-Filter)
if (!$customerId) {
    $allowed = array_map('intval', array_column(Auth::customers(), 'id'));
    $cluster = array_values(array_filter($cluster, fn($c) => in_array((int)$c['customer_id'], $allowed, true)));
}

Response::success($cluster);
