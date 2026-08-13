<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$massnahmeId = trim((string)($input['massnahme_id'] ?? ''));
if ($massnahmeId === '') Response::error('massnahme_id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
$svc->loescheAuslage($massnahmeId);
Response::success(['ok' => true]);
