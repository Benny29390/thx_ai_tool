<?php
/** POST /lam/ki-spalten-mapping Body: { ziel, spalten: [{name, beispiel}] } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ziel = trim((string)($input['ziel'] ?? ''));
$spalten = $input['spalten'] ?? [];
if ($ziel === '' || !is_array($spalten)) Response::error('ziel + spalten erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->kiSpaltenMapping($ziel, $spalten)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('KI-Mapping fehlgeschlagen: ' . $e->getMessage(), 500); }
