<?php
/**
 * GET /api/v1/lam/erreichbarkeit-queue?customer_id=X
 * Liefert die Anzahl noch ungeprüfter Verlinkungen (= warten auf Cron-Worker).
 * Optional pro Kunde gefiltert.
 *
 * Returns: { count: int, customer_id: int|null }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
if ($customerId === 0) $customerId = null;

Response::success([
    'count'       => $svc->zaehleUngepruefteVerlinkungen($customerId),
    'customer_id' => $customerId,
]);
