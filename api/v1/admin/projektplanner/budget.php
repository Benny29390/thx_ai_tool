<?php
/**
 * Projektplanner — Budget API
 *
 * Lesen
 *   GET  /admin/projektplanner/budget/{customer_id}/{year}    Kunden-Budget (Konfig + 12 Monate)
 *   GET  /admin/projektplanner/budget?action=overview&year=Y  Auslastungs-Cockpit (legacy)
 *   GET  /admin/projektplanner/budget?action=matrix&year=Y    Cross-Kunden-Matrix (ersetzt Excel)
 *
 * Schreiben (POST)
 *   /admin/projektplanner/budget/{customer_id}                Default: Monats-Soll (single/batch)
 *      Body: { year, month, soll_ts }  oder  { year, entries: [{month, soll_ts}, ...] }
 *   /admin/projektplanner/budget/{customer_id}?action=override
 *      Body: { year, month, ist_override, ist_note }
 *   /admin/projektplanner/budget/{customer_id}?action=uebertrag
 *      Body: { uebertrag_ts, uebertrag_notiz, abrechnungsmodus }
 *   /admin/projektplanner/budget/{customer_id}?action=config
 *      Body: { billing_model?, ts_per_month?, hours_per_ts?, hours_per_ts_max?, billing_notes?,
 *              uebertrag_ts?, uebertrag_notiz?, abrechnungsmodus? }
 *   /admin/projektplanner/budget/{customer_id}?action=abgerechnet
 *      Body: { year, month, abgerechnet_ts }   (null = zurueck zu offen)
 *   /admin/projektplanner/budget/{customer_id}?action=ist-ts
 *      Body: { year, month, ist_ts }            (null = aus h berechnen)
 *   /admin/projektplanner/budget/{customer_id}?action=bemerkung
 *      Body: { year, month, bemerkung }
 *   /admin/projektplanner/budget/{customer_id}?action=quarter-ist
 *      Body: { year, quarter, total_h?, total_ts? }
 *   /admin/projektplanner/budget/{customer_id}?action=apply-defaults
 *      Body: { year, force? }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\PpBudgetService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/PpBudgetService.php';
$svc = new PpBudgetService(Database::getInstance());

$method = $_SERVER['REQUEST_METHOD'];
$customerId = (int) ($_GET['customer_id'] ?? 0);
$year = (int) ($_GET['year'] ?? date('Y'));
$action = $_GET['action'] ?? '';

// === GET-Routen ===
if ($method === 'GET') {
    try {
        if ($action === 'overview') {
            Response::success($svc->getCustomersOverview($year));
        }
        if ($action === 'matrix') {
            Response::success($svc->getMatrix($year));
        }
        if (!$customerId) Response::error('customer_id fehlt');
        Response::success($svc->getCustomerBudget($customerId, $year));
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($method !== 'POST') Response::error('Methode nicht unterstützt', 405);
if (!$customerId) Response::error('customer_id fehlt');

$payload = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ($action) {
        case 'override':
            $svc->saveIstOverride(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['month'] ?? 0),
                isset($payload['ist_override']) ? (float) $payload['ist_override'] : null,
                $payload['ist_note'] ?? null
            );
            Response::success(['ok' => true], 'Ist-Override gespeichert');

        case 'uebertrag':
            $svc->saveUebertrag(
                $customerId,
                (float) ($payload['uebertrag_ts'] ?? 0),
                $payload['uebertrag_notiz'] ?? null,
                $payload['abrechnungsmodus'] ?? 'quarterly'
            );
            Response::success(['ok' => true], 'Übertrag gespeichert');

        case 'config':
            $svc->saveCustomerConfig($customerId, $payload);
            Response::success(['ok' => true], 'Konfiguration gespeichert');

        case 'abgerechnet':
            $svc->saveAbgerechnet(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['month'] ?? 0),
                array_key_exists('abgerechnet_ts', $payload) && $payload['abgerechnet_ts'] !== null && $payload['abgerechnet_ts'] !== ''
                    ? (float) $payload['abgerechnet_ts'] : null
            );
            Response::success(['ok' => true], 'Abgerechnet gespeichert');

        case 'ist-ts':
            $svc->saveIstTsOverride(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['month'] ?? 0),
                array_key_exists('ist_ts', $payload) && $payload['ist_ts'] !== null && $payload['ist_ts'] !== ''
                    ? (float) $payload['ist_ts'] : null
            );
            Response::success(['ok' => true], 'Ist-TS gespeichert');

        case 'bemerkung':
            $svc->saveBemerkung(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['month'] ?? 0),
                $payload['bemerkung'] ?? null
            );
            Response::success(['ok' => true], 'Bemerkung gespeichert');

        case 'quarter-ist':
            $svc->applyQuarterIst(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['quarter'] ?? 0),
                array_key_exists('total_h',  $payload) && $payload['total_h']  !== null && $payload['total_h']  !== '' ? (float) $payload['total_h']  : null,
                array_key_exists('total_ts', $payload) && $payload['total_ts'] !== null && $payload['total_ts'] !== '' ? (float) $payload['total_ts'] : null
            );
            Response::success(['ok' => true], 'Quartal eingetragen');

        case 'mode':
            $svc->saveQuarterMode(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                (int) ($payload['quarter'] ?? 0),
                (string) ($payload['mode'] ?? '')
            );
            Response::success(['ok' => true], 'Abrechnungs-Modus gespeichert');

        case 'apply-defaults':
            $n = $svc->applyDefaultsForYear(
                $customerId,
                (int) ($payload['year'] ?? date('Y')),
                !empty($payload['force'])
            );
            Response::success(['updated' => $n], "$n Monate gesetzt");
    }

    // Default: Monats-Soll (single oder batch)
    $year = (int) ($payload['year'] ?? date('Y'));
    if (!empty($payload['entries']) && is_array($payload['entries'])) {
        $count = $svc->saveCustomerMonthBatch($customerId, $year, $payload['entries']);
        Response::success(['saved' => $count], 'Batch gespeichert');
    }
    $month = (int) ($payload['month'] ?? 0);
    if ($month < 1 || $month > 12) Response::error('month fehlt oder ungültig');
    $svc->saveCustomerMonth($customerId, $year, $month, (float) ($payload['soll_ts'] ?? 0));
    Response::success(['ok' => true], 'Monats-Soll gespeichert');

} catch (\Throwable $e) { Response::error($e->getMessage()); }
