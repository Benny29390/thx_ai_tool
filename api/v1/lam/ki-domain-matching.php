<?php
/** GET /lam/ki-domain-matching?customer_id=X&anzahl=10 */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$customerId = (int)($_GET['customer_id'] ?? 0);
$anzahl = max(1, min(50, (int)($_GET['anzahl'] ?? 10)));
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!Auth::canAccessCustomer($customerId)) Response::forbidden();
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->kiSchlageDomainsVor($customerId, $anzahl)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('KI-Matching fehlgeschlagen: ' . $e->getMessage(), 500); }
