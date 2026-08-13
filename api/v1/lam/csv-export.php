<?php
/**
 * CSV-Export für mehrere LAM-Module — alle in einer Datei zur einfachen Wartung.
 *
 * GET /api/v1/lam/linkprofil-export?customer_id=X
 * GET /api/v1/lam/massnahmen-export
 * GET /api/v1/lam/auslagen-export?jahr=&quartal=
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

// Helper: CSV ausgeben mit BOM (Excel-freundlich)
$csvAusgeben = function (string $dateiname, array $kopf, iterable $zeilen): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('X-Accel-Buffering: no');
    while (ob_get_level()) ob_end_clean();
    echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $kopf, ';');
    foreach ($zeilen as $z) fputcsv($fp, $z, ';');
    fclose($fp);
    exit;
};

if ($uri === '/api/v1/lam/linkprofil-export') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich');
    $rows = $svc->listeVerlinkungen($customerId, ['limit' => 100000]);
    $list = $rows['rows'] ?? $rows;
    $kopf = ['Domain','Verlinkende URL','Linktext','Linkart','Empfehlung','Topp','Status','Follow','HTTP','Bemerkung','Quelle','Angelegt'];
    $g = function () use ($list) {
        foreach ($list as $v) {
            yield [
                $v['domain'],
                $v['verlinkende_url'],
                $v['linktext'],
                $v['linkart'],
                $v['empfehlung'],
                ((int)($v['ist_topp'] ?? 0) === 1) ? 'Topp' : '',
                $v['status'],
                $v['is_follow'] === null ? '' : ($v['is_follow'] ? 'follow' : 'nofollow'),
                $v['letzter_http_status'],
                $v['bemerkung'],
                $v['imported_from'],
                $v['erstellt_am'],
            ];
        }
    };
    $csvAusgeben('linkprofil_' . $customerId . '_' . date('Y-m-d') . '.csv', $kopf, $g());
}

if ($uri === '/api/v1/lam/massnahmen-export') {
    $rows = $svc->listeMassnahmen(['limit' => 500, 'offset' => 0])['rows'] ?? [];
    $kopf = ['Kunde','Domain','Status','Buchungstyp','Linktext','Geplant am','Veröffentlicht am','Veröffentlichungs-URL','Sonderstatus'];
    $g = function () use ($rows) {
        foreach ($rows as $m) {
            yield [
                $m['customer_kuerzel'],
                $m['domain_url'],
                $m['status'],
                $m['buchungstyp'],
                $m['linktext'],
                $m['geplant_am'],
                $m['veroeffentlicht_am'],
                $m['veroeffentlichungs_url'],
                $m['sonderstatus'],
            ];
        }
    };
    $csvAusgeben('massnahmen_' . date('Y-m-d') . '.csv', $kopf, $g());
}

if ($uri === '/api/v1/lam/auslagen-export') {
    $filter = [
        'jahr' => $_GET['jahr'] ?? null,
        'quartal' => $_GET['quartal'] ?? null,
        'sonderfall' => $_GET['sonderfall'] ?? null,
    ];
    $rows = $svc->listeAuslagen($filter);
    $kopf = ['Kunde','Domain','Externe Kosten','Weiterverrechnet','Marge','Marge-Grund','Rechnung-Nr','Rechnung-Datum','Sonderfall'];
    $g = function () use ($rows) {
        foreach ($rows as $a) {
            yield [
                $a['customer_kuerzel'],
                $a['domain_url'],
                $a['externe_kosten'],
                $a['weiterverrechnet'],
                $a['marge'],
                $a['marge_grund'] ?? '',
                $a['thoxan_rechnung_nr'],
                $a['thoxan_rechnung_datum'],
                $a['sonderfall'],
            ];
        }
    };
    $tag = ($filter['jahr'] ?? date('Y')) . ($filter['quartal'] ? '_Q' . $filter['quartal'] : '');
    $csvAusgeben('auslagen_' . $tag . '.csv', $kopf, $g());
}

Response::error('Unbekannter Export-Pfad', 404);
