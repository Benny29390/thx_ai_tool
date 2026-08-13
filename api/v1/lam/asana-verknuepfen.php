<?php
/** POST /lam/asana-verknuepfen Body: { massnahme_id, task_gid_oder_url } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($input['massnahme_id'] ?? ''));
$eingabe = trim((string)($input['task_gid_oder_url'] ?? ''));
if ($id === '' || $eingabe === '') Response::error('massnahme_id + task_gid_oder_url erforderlich', 400);

require_once SERVICES_PATH . '/AsanaService.php';
$gid = \Services\AsanaService::extrahiereTaskGid($eingabe);
if (!$gid) Response::error('Konnte keine Task-GID aus der Eingabe extrahieren.', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaVerknuepfeMassnahme($id, $gid)); }
catch (\Throwable $e) { Response::error('Verknüpfen fehlgeschlagen: ' . $e->getMessage(), 500); }
