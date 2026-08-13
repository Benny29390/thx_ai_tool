<?php
/**
 * Kunden-Kontext fuer KI-Klassifikation lesen/schreiben.
 *
 * GET  /api/v1/lam/kunden-kontext?customer_id=X
 *   → { lam_kontext: string }
 * POST /api/v1/lam/kunden-kontext
 *   Body: { customer_id, lam_kontext }
 *
 * Inhalt wird bei jedem Sonnet-Klassifikations-Prompt vorangestellt,
 * damit Kunden-spezifische Backlink-Muster beruecksichtigt werden.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\AuditLog;
use Core\Session;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

// Session-Lock freigeben — beim POST koennen automatische Re-Klassifikationen
// laufen (KI-Calls), die mehrere Sekunden dauern.
if ($_SERVER['REQUEST_METHOD'] === 'POST') Session::release();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    $row = $db->queryOne("SELECT lam_kontext FROM customers WHERE id = ?", [$customerId]);
    Response::success(['lam_kontext' => $row['lam_kontext'] ?? '']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    $customerId = (int)($json['customer_id'] ?? 0);
    $kontext    = (string)($json['lam_kontext'] ?? '');
    $autoRefresh= !isset($json['auto_refresh']) || !empty($json['auto_refresh']); // default true
    if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
    if (mb_strlen($kontext) > 8000) Response::error('Kontext zu lang (max 8000 Zeichen)', 400);

    // Welche Domains stehen schon im alten Kontext? Vergleichen, um nur die NEU
    // hinzugekommenen zu re-klassifizieren — sonst wuerden bei jedem Tippen
    // dieselben Domains erneut Tokens kosten.
    $alt = (string)($db->queryValue("SELECT lam_kontext FROM customers WHERE id = ?", [$customerId]) ?: '');

    $n = $db->execute("UPDATE customers SET lam_kontext = ? WHERE id = ?", [$kontext, $customerId]);
    AuditLog::record('customer', (string)$customerId, 'lam.kontext.aktualisiert', [
        'laenge' => mb_strlen($kontext),
    ]);

    // Domains in der NEUEN Notiz, die nicht in der alten standen → re-klassifizieren
    $refresh = ['domains' => [], 'ok' => 0, 'fehler' => 0];
    if ($autoRefresh) {
        require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
        $svc = new \Services\LinkprofilAufraeumService($db);
        $neueDomains = $svc->findeDomainsInNotiz($customerId, $kontext);
        $alteDomains = $svc->findeDomainsInNotiz($customerId, $alt);
        $zuRefresh = array_values(array_diff($neueDomains, $alteDomains));
        if ($zuRefresh) {
            try {
                $r = $svc->reklassifiziereDomains($customerId, $zuRefresh);
                $refresh = [
                    'domains' => $zuRefresh,
                    'ok'      => $r['ok'] ?? 0,
                    'fehler'  => $r['fehler'] ?? 0,
                ];
            } catch (\Throwable $e) {
                $refresh['fehler_text'] = $e->getMessage();
            }
        }
    }

    Response::success(['gespeichert' => $n > 0, 'refresh' => $refresh]);
}

Response::error('Nur GET oder POST', 405);
