<?php
/**
 * Ermittelt das Linkziel einer Verlinkung per HTTP-Crawl der Quell-URL.
 * POST /api/v1/lam/verlinkung-ziel-ermitteln  Body: { id, customer_id }
 *
 * Speichert NICHT — der User bekommt einen Vorschlag und entscheidet selbst.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Crawl kann bis 12 Sekunden dauern — Session freigeben.
Session::release();

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$id         = trim((string)($json['id'] ?? ''));
$customerId = (int)($json['customer_id'] ?? 0);
if ($id === '' || $customerId <= 0) Response::error('id + customer_id erforderlich', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    Response::success($svc->ermittleLinkziel($id, $customerId));
} catch (\Throwable $e) {
    Response::error('Ermittlung fehlgeschlagen: ' . $e->getMessage(), 500);
}
