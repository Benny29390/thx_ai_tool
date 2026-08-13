<?php
/**
 * Wendet ein bestaetigtes Muster auf alle matchenden offenen Verlinkungen des
 * Kunden an. Setzt sie in die Wissensbasis (confidence='ki_bestaetigt').
 * Manuelle Eintraege bleiben unberuehrt.
 *
 * POST /api/v1/lam/aufraeum-muster-anwenden  Body: { muster_id, customer_id }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

Session::release();

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$musterId   = (int)($json['muster_id']   ?? 0);
$customerId = (int)($json['customer_id'] ?? 0);
if ($musterId <= 0 || $customerId <= 0) Response::error('muster_id + customer_id erforderlich', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    Response::success($svc->wendeMusterAn($musterId, $customerId));
} catch (\Throwable $e) {
    Response::error('Anwenden fehlgeschlagen: ' . $e->getMessage(), 500);
}
