<?php
/**
 * POST /lam/verlinkung-klassifizieren
 * Body: { id, mit_crawl?: bool }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$id = trim((string)($input['id'] ?? ''));
$mitCrawl = !empty($input['mit_crawl']);
if ($id === '') Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->klassifiziereVerlinkung($id, $mitCrawl));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('KI-Klassifikation fehlgeschlagen: ' . $e->getMessage(), 500);
}
