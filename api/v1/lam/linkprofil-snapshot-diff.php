<?php
/**
 * GET /lam/linkprofil-snapshot-diff?id=X
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try {
    $data = $svc->getSnapshotDiff($id);
    if (!Auth::canAccessCustomer((int)$data['snapshot']['customer_id'])) Response::forbidden();
    Response::success($data);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 404);
}
