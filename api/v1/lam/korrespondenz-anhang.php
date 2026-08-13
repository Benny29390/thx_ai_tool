<?php
/**
 * Korrespondenz-Anhang-Download
 * GET /lam/korrespondenz-anhang?id=...
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$anhang = $svc->getKorrespondenzAnhang($id);
if (!$anhang || !$anhang['anhang_pfad']) {
    Response::error('Kein Anhang vorhanden', 404);
}

$pfad = $anhang['anhang_pfad'];
// Pfad-Traversal-Schutz: nur innerhalb /var/www/uploads erlauben
$realPfad = realpath($pfad);
$uploadDir = realpath(ROOT_PATH . '/uploads');
if (!$realPfad || !$uploadDir || strpos($realPfad, $uploadDir) !== 0 || !is_file($realPfad)) {
    Response::error('Anhang-Datei nicht gefunden', 404);
}

header('Content-Type: ' . ($anhang['anhang_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . addslashes($anhang['anhang_originalname'] ?: 'anhang') . '"');
header('Content-Length: ' . filesize($realPfad));
readfile($realPfad);
exit;
