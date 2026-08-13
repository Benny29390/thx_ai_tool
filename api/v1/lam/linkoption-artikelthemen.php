<?php
/** POST /lam/linkoption-artikelthemen  Body: { linkoption_id } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($body['linkoption_id'] ?? ''));
if ($id === '') Response::error('linkoption_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->schlageArtikelthemenVor($id)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('KI-Vorschläge fehlgeschlagen: ' . $e->getMessage(), 500); }
