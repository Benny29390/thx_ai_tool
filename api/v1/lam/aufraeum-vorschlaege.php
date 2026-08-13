<?php
/**
 * Liefert die offenen Aufraeum-Vorschlaege fuer einen Kunden.
 * GET /api/v1/lam/aufraeum-vorschlaege?customer_id=X[&auch_bestaetigt=1]
 *
 * Mit auch_bestaetigt=1 werden auch bereits bestaetigte Verlinkungen einbezogen
 * (Nachbearbeitungs-Modus — z.B. fuer Domains mit verbliebenen Dubletten oder
 * Empfehlung='unsicher').
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);

$auchBestaetigt = !empty($_GET['auch_bestaetigt']);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

Response::success($svc->getDomainVorschlaege($customerId, $auchBestaetigt));
