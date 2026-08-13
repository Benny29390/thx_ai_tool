<?php
/**
 * Sistrix-Kennzahlen manuell eintragen (ohne API-Call).
 * POST /lam/sistrix-manuell
 * Body: { domain_id, si?, dp?, domain_alter?, erfasst_am? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\SistrixService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$domainId = trim((string)($input['domain_id'] ?? ''));
if ($domainId === '') Response::error('domain_id erforderlich', 400);

require_once SERVICES_PATH . '/SistrixService.php';
$svc = new SistrixService(Database::getInstance());
$id = $svc->speichereManuell($domainId, $input);
Response::success(['snapshot_id' => $id]);
