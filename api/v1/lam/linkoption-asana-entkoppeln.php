<?php
/** POST /lam/linkoption-asana-entkoppeln Body: { linkoption_id } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($input['linkoption_id'] ?? ''));
if ($id === '') Response::error('linkoption_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaEntkoppleLinkoption($id)); }
catch (\Throwable $e) { Response::error('Entkoppeln fehlgeschlagen: ' . $e->getMessage(), 500); }
