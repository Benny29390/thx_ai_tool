<?php
/**
 * Klassifiziert unbekannte Domains per Claude Sonnet 4.6 in einem Batch.
 * POST /api/v1/lam/aufraeum-klassifiziere-ki
 * Body: { domains: [{domain, anzahl, beispiel_urls[], linktexte[]}, ...] }
 *
 * Max. 50 Domains pro Aufruf (Frontend chunked falls mehr).
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben: dieser Request laeuft mehrere Sekunden bis Minuten.
// Solange die Session-Datei gesperrt ist, blockiert er alle anderen Requests
// desselben Users (z.B. Projektplanner). Nach release() kann der User parallel
// in anderen Tabs arbeiten, dieser Request laeuft im Hintergrund weiter.
Session::release();

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$domains      = $json['domains']    ?? [];
$customerId   = isset($json['customer_id']) ? (int)$json['customer_id'] : null;
$forceRefresh = !empty($json['force_refresh']);
if (!is_array($domains) || !$domains) Response::error('domains[] erforderlich', 400);
if (count($domains) > 50) Response::error('Max. 50 Domains pro Batch', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    Response::success($svc->klassifiziereUnbekannteBatch($domains, $customerId, $forceRefresh));
} catch (\Throwable $e) {
    Response::error('Klassifikation fehlgeschlagen: ' . $e->getMessage(), 500);
}
