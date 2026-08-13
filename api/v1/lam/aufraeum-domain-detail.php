<?php
/**
 * Liefert pro Domain alle offenen Verlinkungen mit Score + behalten_ids-Vorschlag.
 * GET /api/v1/lam/aufraeum-domain-detail?customer_id=X&domain=Y&strategie=Z
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$customerId     = (int)($_GET['customer_id'] ?? 0);
$domain         = trim((string)($_GET['domain'] ?? ''));
$strategie      = $_GET['strategie'] ?? null;
$urlStrat       = $_GET['url_strategie'] ?? null;
$auchBestaetigt = !empty($_GET['auch_bestaetigt']);
if ($customerId <= 0 || $domain === '') Response::error('customer_id und domain erforderlich', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

Response::success($svc->getDomainDetail($customerId, $domain, $strategie, $urlStrat, $auchBestaetigt));
