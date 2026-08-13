<?php
/**
 * POST /lam/linkziel-save
 * Body: { id?, customer_id, url, thema, bevorzugter_linktext?, status? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

// Kunden-Zugriff pruefen
$customerId = (int)($input['customer_id'] ?? 0);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->speichereLinkziel($input));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Speichern fehlgeschlagen', 500);
}
