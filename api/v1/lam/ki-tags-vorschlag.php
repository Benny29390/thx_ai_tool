<?php
/** POST /lam/ki-tags-vorschlag Body: { domain_id } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($input['domain_id'] ?? ''));
if ($id === '') Response::error('domain_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->kiSchlageTagsVor($id)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('KI-Vorschlag fehlgeschlagen: ' . $e->getMessage(), 500); }
