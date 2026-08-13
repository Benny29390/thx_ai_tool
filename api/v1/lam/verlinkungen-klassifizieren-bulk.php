<?php
/**
 * POST /lam/verlinkungen-klassifizieren-bulk
 * Body: { ids: [string], mit_crawl?: bool }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer Parallel-Arbeit waehrend KI-Klassifikation.
Session::release();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$ids = $input['ids'] ?? [];
if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich', 400);
if (count($ids) > 200) Response::error('Max 200 pro Bulk-Aufruf', 400);

$mitCrawl = !empty($input['mit_crawl']);

// Plausibilitätscheck: bei mit_crawl > 50 IDs warnen
if ($mitCrawl && count($ids) > 50) Response::error('Mit Crawl max 50 pro Aufruf', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    Response::success($svc->klassifiziereVerlinkungenBulk($ids, $mitCrawl));
} catch (\Throwable $e) {
    Response::error('Bulk-Klassifikation fehlgeschlagen: ' . $e->getMessage(), 500);
}
