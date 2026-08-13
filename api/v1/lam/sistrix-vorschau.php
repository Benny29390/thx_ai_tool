<?php
/**
 * Sistrix-Bulk-Vorschau: zeigt fuer die gegebenen Verlinkungs-IDs
 * die UNIQUE Domains, Cache-Hits, Maximalkosten und den Wochenstatus.
 *
 * POST /api/v1/lam/sistrix-vorschau
 * Body: { ids: [int,...], teile: ['si']|['dp']|['si','alter','dp'] }
 *
 * Antwort:
 *   {
 *     vorschau: { verlinkungen, unique_domains, cache_hits, neu_abzurufen,
 *                 credits_pro_domain, kosten_max, teile },
 *     status:   { credits_verbleibend, wochenkontingent, konfiguriert, ... },
 *     budget_reicht: bool
 *   }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;
use Services\SistrixService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
if (!is_array($json)) Response::error('Body muss JSON sein', 400);

$ids    = $json['ids']    ?? [];
$teile  = $json['teile']  ?? ['si'];
$quelle = $json['quelle'] ?? 'verlinkung'; // 'verlinkung' | 'domain'

if (!is_array($ids)) Response::error('ids fehlt', 400);
if (!is_array($teile) || !$teile) Response::error('teile fehlt', 400);

$erlaubt = ['si', 'dp', 'alter'];
foreach ($teile as $t) {
    if (!in_array($t, $erlaubt, true)) Response::error('Unbekannter Teil: ' . $t, 400);
}
if (!in_array($quelle, ['verlinkung', 'domain'], true)) {
    Response::error('Unbekannte quelle: ' . $quelle, 400);
}

require_once SERVICES_PATH . '/LamService.php';
require_once SERVICES_PATH . '/SistrixService.php';

$svc     = new LamService(Database::getInstance());
$sistrix = new SistrixService(Database::getInstance());

$vorschau = $quelle === 'domain'
    ? $svc->sistrixVorschauDomains($ids, $teile)
    : $svc->sistrixVorschauVerlinkungen($ids, $teile);
$status = $sistrix->wochenStatus();

Response::success([
    'vorschau' => $vorschau,
    'status'   => $status,
    'budget_reicht' => ($vorschau['kosten_max'] ?? 0) <= ($status['credits_verbleibend'] ?? 0),
]);
