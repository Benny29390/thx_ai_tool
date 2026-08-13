<?php
/**
 * HTTP-Check für eine Maßnahme ausführen (oder mehrere).
 *
 * POST /api/v1/lam/monitoring-check
 * Body: { massnahme_id }  oder  { ids: [...] } (Bulk)
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

if (!empty($input['ids']) && is_array($input['ids'])) {
    $ergebnisse = []; $ok = 0; $fehler = 0; $alerts = 0;
    foreach ($input['ids'] as $mid) {
        try {
            $r = $svc->fuehreMonitoringCheckAus(trim((string)$mid));
            $ergebnisse[] = $r + ['massnahme_id' => $mid];
            if (!empty($r['alert'])) $alerts++;
            $ok++;
        } catch (\Throwable $e) {
            $fehler++;
            $ergebnisse[] = ['massnahme_id' => $mid, 'fehler' => $e->getMessage()];
        }
    }
    Response::success(['ok' => $ok, 'fehler' => $fehler, 'alerts' => $alerts, 'ergebnisse' => $ergebnisse],
        "$ok geprüft, $fehler Fehler, $alerts neue Alerts");
}

$id = trim((string)($input['massnahme_id'] ?? ''));
if ($id === '') Response::error('massnahme_id oder ids erforderlich');
try {
    $r = $svc->fuehreMonitoringCheckAus($id);
    Response::success($r, 'Check ausgeführt');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
