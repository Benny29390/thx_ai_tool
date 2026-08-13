<?php
/** POST /lam/asana-uebernehmen Body: { massnahme_id, vorschlaege: {…} } */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = trim((string)($input['massnahme_id'] ?? ''));
$vor = $input['vorschlaege'] ?? [];
if ($id === '' || !is_array($vor)) Response::error('massnahme_id + vorschlaege erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaUebernehmeFelder($id, $vor)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Übernahme fehlgeschlagen: ' . $e->getMessage(), 500); }
