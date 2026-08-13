<?php
/**
 * Sistrix-Bulk-Abruf für mehrere Domains.
 *
 * POST /api/v1/lam/sistrix-bulk
 * Body: { ids: [domain_id, ...], teil: 'si'|'alter'|'dp'|'alles' }
 *
 * Wendet sich pro Domain an SistrixService::holeKennzahlen — Caching greift
 * automatisch (eine Abfrage pro Domain+Teil+Tag).
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\SistrixService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer Parallel-Arbeit waehrend Sistrix-Bulk.
Session::release();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ids = $input['ids'] ?? [];
$teil = $input['teil'] ?? 'si';

if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich');
$ids = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $ids))));
if (empty($ids)) Response::error('Keine gueltigen IDs');

$teile = $teil === 'alles' ? ['si','alter','dp'] : [$teil];

require_once SERVICES_PATH . '/SistrixService.php';
$svc = new SistrixService(Database::getInstance());
if (!$svc->istKonfiguriert()) {
    Response::error('Sistrix-API-Key nicht gesetzt — bitte unter /admin/settings?tab=sistrix eintragen.', 412);
}

$status = $svc->wochenStatus();
$verbleibend = (int)($status['credits_verbleibend'] ?? 0);
$kostenProDomain = SistrixService::creditsFuer($teile);
$gesamtKosten = $kostenProDomain * count($ids);
if ($gesamtKosten > $verbleibend) {
    Response::error(
        sprintf('Wochenkontingent reicht nicht: %d Credits benötigt, nur %d übrig. Domains reduzieren oder Wochenkontingent erhöhen.',
            $gesamtKosten, $verbleibend),
        412
    );
}

$ok = 0; $fehler = []; $creditsVerbraucht = 0; $cacheHits = 0;
foreach ($ids as $domainId) {
    try {
        $r = $svc->holeKennzahlen($domainId, $teile, false);
        if (!empty($r['fehler'])) {
            $fehler[] = ['id' => $domainId, 'fehler' => $r['fehler']];
        } else {
            $ok++;
        }
        $creditsVerbraucht += (int)($r['credits_verbraucht'] ?? 0);
        if (!empty($r['cached'])) $cacheHits++;
    } catch (\Throwable $e) {
        $fehler[] = ['id' => $domainId, 'fehler' => $e->getMessage()];
    }
}

Response::success([
    'ok' => $ok,
    'fehler' => $fehler,
    'credits_verbraucht' => $creditsVerbraucht,
    'cache_hits' => $cacheHits,
    'total' => count($ids),
], "Sistrix-Bulk: $ok von " . count($ids) . " Domains erfolgreich. Credits verbraucht: $creditsVerbraucht (Cache-Treffer: $cacheHits)");
