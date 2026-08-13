<?php
/** GET /lam/asana-massnahme-sections?massnahme_id=X */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$id = trim((string)($_GET['massnahme_id'] ?? ''));
if ($id === '') Response::error('massnahme_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaSectionsFuerMassnahme($id)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Asana-Sections fehlgeschlagen: ' . $e->getMessage(), 500); }
