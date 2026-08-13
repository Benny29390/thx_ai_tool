<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id = trim((string)($input['id'] ?? '')) ?: null;

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $resultId = $svc->speichereDomain($id, $input);
    Response::success(['id' => $resultId, 'neu' => $id === null]);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    // Die echte Ursache an den Client weitergeben — vorher wurde alles verschluckt
    // und der User sah nur "Speichern fehlgeschlagen" ohne Erklärung.
    error_log('LAM domain-save Fehler: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Speichern fehlgeschlagen: ' . $e->getMessage(), 500);
}
