<?php
/**
 * Macht die letzte Aufraeum-Aktion fuer eine Domain rueckgaengig.
 *
 * POST /api/v1/lam/aufraeum-rueckgaengig
 * Body: { customer_id: int, domain: string }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$json = json_decode(file_get_contents('php://input'), true);
$customerId = (int)($json['customer_id'] ?? 0);
$domain     = trim((string)($json['domain'] ?? ''));
if ($customerId <= 0 || $domain === '') Response::error('customer_id und domain erforderlich', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try { Response::success($svc->macheRueckgaengig($customerId, $domain)); }
catch (\Throwable $e) { Response::error('Undo fehlgeschlagen: ' . $e->getMessage(), 500); }
