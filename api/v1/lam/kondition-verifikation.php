<?php
/**
 * POST /lam/kondition-verifikation
 * Body: { id, status }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id = trim((string)($input['id'] ?? ''));
$status = trim((string)($input['status'] ?? ''));
if ($id === '' || $status === '') Response::error('id und status erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $svc->aktualisiereKonditionVerifikation($id, $status, Auth::user()['id'] ?? null);
    Response::success(['ok' => true]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
}
