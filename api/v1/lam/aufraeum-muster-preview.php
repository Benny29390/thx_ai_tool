<?php
/**
 * Live-Preview: wieviele Domains matchen ein Muster (ohne zu speichern).
 * POST /api/v1/lam/aufraeum-muster-preview  Body: { customer_id, muster_typ, muster_value }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$customerId = (int)($json['customer_id'] ?? 0);
$typ        = trim((string)($json['muster_typ'] ?? ''));
$wert       = trim((string)($json['muster_value'] ?? ''));
if ($customerId <= 0 || $typ === '' || $wert === '') {
    Response::error('customer_id + muster_typ + muster_value erforderlich', 400);
}

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    $matches = $svc->findePassendeDomains($customerId, $typ, $wert, 500);
    $anzahlDomains = count($matches);
    $anzahlVerlinkungen = array_sum(array_column($matches, 'anzahl'));
    Response::success([
        'anzahl_domains'      => $anzahlDomains,
        'anzahl_verlinkungen' => $anzahlVerlinkungen,
        'beispiele'           => array_slice(array_map(fn($r) => [
            'domain' => $r['domain'],
            'anzahl' => (int)$r['anzahl'],
        ], $matches), 0, 15),
    ]);
} catch (\Throwable $e) {
    Response::error('Preview fehlgeschlagen: ' . $e->getMessage(), 500);
}
