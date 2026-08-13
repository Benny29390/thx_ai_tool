<?php
/**
 * POST /api/v1/lam/portfolio-import/{batch_id}/commit
 * Body: { auswahl: { anbieter, kontakte[], domains[], sonderdeals[] } }
 *
 * Schreibt die geprueften/bearbeiteten Daten transaktional in die LAM-Tabellen.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PortfolioImportService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$batchId = trim((string)($_GET['batch_id'] ?? ''));
if ($batchId === '') Response::error('batch_id erforderlich');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$auswahl = is_array($body['auswahl'] ?? null) ? $body['auswahl'] : [];

require_once SERVICES_PATH . '/PortfolioImportService.php';
$svc = new PortfolioImportService(Database::getInstance());
$user = Auth::user();

@set_time_limit(120);
try {
    $stats = $svc->commit($batchId, $auswahl, (int)($user['id'] ?? 0));
    $msg = sprintf('%d Anbieter, %d Kontakte, %d neue Domains, %d Updates, %d Konditionen, %d Sonderdeals importiert',
        $stats['anbieter'], $stats['kontakte'], $stats['domains_neu'], $stats['domains_update'], $stats['konditionen'], $stats['sonderdeals']);
    Response::success(['stats' => $stats], $msg);
} catch (\Throwable $e) {
    Response::error('Commit fehlgeschlagen: ' . $e->getMessage(), 500);
}
