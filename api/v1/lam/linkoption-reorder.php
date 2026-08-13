<?php
/** POST /lam/linkoption-reorder  Body: { liste_id, ids: [string, string, ...] } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$listenId = trim((string)($body['liste_id'] ?? ''));
$ids = $body['ids'] ?? [];
if ($listenId === '') Response::error('liste_id erforderlich', 400);
if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich (Array)', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { $svc->aktualisierePositionen($listenId, $ids); Response::success(['ok' => true, 'count' => count($ids)]); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Reorder fehlgeschlagen: ' . $e->getMessage(), 500); }
