<?php
/** POST /lam/domain-erreichbarkeit-bulk Body: { ids: [string] } */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = $input['ids'] ?? [];
if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich', 400);
if (count($ids) > 500) Response::error('Max 500 pro Bulk', 400);

// max_execution_time hochsetzen für viele Domains (200ms × n)
@set_time_limit(max(60, count($ids) * 3));

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->pruefeDomainErreichbarkeitBulk($ids));
} catch (\Throwable $e) {
    Response::error('Bulk-Erreichbarkeit fehlgeschlagen: ' . $e->getMessage(), 500);
}
