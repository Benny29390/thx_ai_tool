<?php
/**
 * GET /api/v1/lam/portfolio-import/{batch_id}/preview
 * Liefert das gespeicherte Extraction-JSON eines Batches (idempotent).
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PortfolioImportService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$batchId = trim((string)($_GET['batch_id'] ?? ''));
if ($batchId === '') Response::error('batch_id erforderlich');

require_once SERVICES_PATH . '/PortfolioImportService.php';
$svc = new PortfolioImportService(Database::getInstance());

try {
    Response::success($svc->getExtraction($batchId));
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 404);
}
