<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$domainId = trim((string)($input['domain_id'] ?? ''));
$kundeId = (int)($input['kunde_id'] ?? 0);
if ($domainId === '' || $kundeId <= 0) Response::error('domain_id und kunde_id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->toggleKundeFuerDomain($domainId, $kundeId));
