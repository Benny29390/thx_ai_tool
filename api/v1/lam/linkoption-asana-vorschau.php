<?php
/** GET /lam/linkoption-asana-vorschau?linkoption_id=X */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$id = trim((string)($_GET['linkoption_id'] ?? ''));
if ($id === '') Response::error('linkoption_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaVorschauFuerLinkoption($id)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Vorschau fehlgeschlagen: ' . $e->getMessage(), 500); }
