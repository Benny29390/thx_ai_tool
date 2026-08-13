<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id   = trim((string)($input['id']   ?? ''));
$feld = trim((string)($input['feld'] ?? ''));
$wert = $input['wert'] ?? null;

if ($id === '' || $feld === '') Response::error('id und feld erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $svc->aktualisiereDomainFeld($id, $feld, $wert);
    Response::success(['ok' => true]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Exception $e) {
    Response::error('Update fehlgeschlagen', 500);
}
