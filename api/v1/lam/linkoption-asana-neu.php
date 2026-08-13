<?php
/** POST /lam/linkoption-asana-neu Body: { linkoption_id } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($input['linkoption_id'] ?? ''));
if ($id === '') Response::error('linkoption_id erforderlich', 400);
$titel = isset($input['titel']) ? (string)$input['titel'] : null;
$beschreibung = isset($input['beschreibung']) ? (string)$input['beschreibung'] : null;
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaErstelleTaskFuerLinkoption($id, $titel, $beschreibung)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Anlegen fehlgeschlagen: ' . $e->getMessage(), 500); }
