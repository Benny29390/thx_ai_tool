<?php
/**
 * Monitoring-Bulk-Aktionen: alerts quittieren
 * POST /lam/monitoring-aktion
 * Body: { ids: [...], aktion: 'alerts_quittieren' }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$ids    = $input['ids'] ?? [];
$aktion = trim((string)($input['aktion'] ?? ''));

if (!is_array($ids) || count($ids) === 0) Response::error('ids erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

if ($aktion === 'alerts_quittieren') {
    Response::success($svc->bulkQuittiereAlerts($ids));
} else {
    Response::error('Unbekannte Aktion', 400);
}
