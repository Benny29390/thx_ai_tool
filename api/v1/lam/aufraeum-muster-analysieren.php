<?php
/**
 * KI-gestuetzte Muster-Extraktion aus einer Domain-Notiz.
 * POST /api/v1/lam/aufraeum-muster-analysieren  Body: { customer_id, domain, notiz }
 *
 * Antwort: Array von Muster-Kandidaten (nicht gespeichert), die der User
 * im UI bestaetigen oder verwerfen muss.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// KI-Aufruf laeuft bis zu 30 Sekunden — Session freigeben damit der User
// parallel weiterarbeiten kann.
Session::release();

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$customerId = (int)($json['customer_id'] ?? 0);
$domain     = trim((string)($json['domain'] ?? ''));
$notiz      = trim((string)($json['notiz'] ?? ''));
if ($customerId <= 0 || $domain === '' || $notiz === '') {
    Response::error('customer_id + domain + notiz erforderlich', 400);
}

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    $vorschlaege = $svc->analysiereDomainNotiz($customerId, $domain, $notiz);
    Response::success(['vorschlaege' => $vorschlaege]);
} catch (\Throwable $e) {
    Response::error('Muster-Analyse fehlgeschlagen: ' . $e->getMessage(), 500);
}
