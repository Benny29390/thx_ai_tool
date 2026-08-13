<?php
/**
 * POST /api/v1/lam/verlinkungen-zu-linkquellen
 * Body: { ids: [verlinkungId, ...], customer_id: int }
 *
 * Übernimmt ausgewählte Verlinkungen aus dem Linkprofil als Linkquellen in den Pool:
 *  - Domain aus Verlinkungs-URL extrahieren
 *  - Falls die Domain noch nicht in lam_domains: neu anlegen (Status: neu)
 *  - In lam_domain_customer für den Kunden eintragen (Status auto auf in_arbeit)
 *  - Die Verlinkungs-URL als Beispiellink an die Linkquelle anhängen (lam_domain_links)
 *  - Notiz an der Linkquelle erweitern ("Aus Linkprofil von <Kunde> übertragen am <Datum>")
 */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ids = $input['ids'] ?? [];
$customerId = (int) ($input['customer_id'] ?? 0);
if (!is_array($ids) || empty($ids)) Response::error('ids erforderlich', 400);
if ($customerId <= 0) Response::error('customer_id erforderlich', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $stats = $svc->uebernehmeVerlinkungenInLinkquellen($ids, $customerId);
    Response::success($stats);
} catch (\Throwable $e) {
    Response::error('Übernahme fehlgeschlagen: ' . $e->getMessage(), 500);
}
