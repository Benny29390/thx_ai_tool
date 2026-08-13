<?php
/**
 * POST /lam/sitewide-cluster-aktion
 * Body: { customer_id, domain, empfehlung }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$customerId = (int)($input['customer_id'] ?? 0);
$domain = trim((string)($input['domain'] ?? ''));
$empfehlung = trim((string)($input['empfehlung'] ?? ''));
if ($customerId <= 0 || $domain === '' || $empfehlung === '') Response::error('customer_id, domain, empfehlung erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $aktualisiert = $svc->setzeClusterEmpfehlung($customerId, $domain, $empfehlung);
    Response::success(['aktualisiert' => $aktualisiert]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
}
