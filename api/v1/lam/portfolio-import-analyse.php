<?php
/**
 * GET /api/v1/lam/portfolio-import/{batch_id}/analyse
 * Triggert die KI-Analyse fuer einen Batch und gibt das Vorschlags-JSON zurueck.
 * Synchron — kann je nach KI-Latenz 5-30s dauern.
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

@set_time_limit(120);
try {
    $extraction = $svc->analysiere($batchId);
    Response::success(['extraction' => $extraction]);
} catch (\Throwable $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage(), 500);
}
