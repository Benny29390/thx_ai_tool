<?php
/**
 * POST /admin/projektplanner/generate-plan
 *
 * Body: { customer_id, period_from, period_to, briefing }
 *
 * Erzeugt einen Plan-Entwurf mit Claude und gibt die neue plan_id zurueck.
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('Nur POST', 405);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$customerId = (int) ($body['customer_id'] ?? 0);
$periodFrom = trim((string) ($body['period_from'] ?? ''));
$periodTo   = trim((string) ($body['period_to'] ?? ''));
$briefing   = trim((string) ($body['briefing'] ?? ''));

if ($customerId <= 0) Response::error('customer_id erforderlich');

require_once SERVICES_PATH . '/PpTaxonomyService.php';
require_once SERVICES_PATH . '/PpPlanGeneratorService.php';

try {
    $svc = new \Services\PpPlanGeneratorService(Database::getInstance());
    $user = Auth::user();
    $result = $svc->generateDraft(
        $customerId,
        $periodFrom ?: null,
        $periodTo ?: null,
        $briefing,
        (int) ($user['id'] ?? 0)
    );
    Response::success($result, 'Plan-Entwurf erstellt');
} catch (\Throwable $e) {
    Response::error('Generierung fehlgeschlagen: ' . $e->getMessage(), 500);
}
