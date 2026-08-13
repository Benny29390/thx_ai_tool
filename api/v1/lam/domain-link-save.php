<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = trim((string)($input['id'] ?? '')) ?: null;
$domainId = trim((string)($input['domain_id'] ?? ''));
if (!$id && $domainId === '') Response::error('domain_id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try {
    $resultId = $svc->speichereDomainLink($id, $domainId, $input);
    Response::success(['id' => $resultId, 'neu' => $id === null]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
}
