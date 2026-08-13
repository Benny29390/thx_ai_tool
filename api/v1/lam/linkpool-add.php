<?php
/**
 * POST /api/v1/lam/linkpool-add
 * Body: { customer_id: int, domain_ids: [string,...] }
 * Fügt eine oder mehrere Domains aus der globalen Linkquellen-Liste
 * zum Linkpool eines Kunden hinzu (lam_domain_customer).
 * Dubletten werden übersprungen (Composite-PK domain_id + customer_id).
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$customerId = (int) ($input['customer_id'] ?? 0);
$domainIds = $input['domain_ids'] ?? [];
if ($customerId <= 0 || empty($domainIds) || !is_array($domainIds)) {
    Response::error('customer_id + domain_ids erforderlich', 400);
}

$db = Database::getInstance();
$domainIds = array_values(array_unique(array_filter(array_map('strval', $domainIds))));

// Existierende Pool-Einträge dieses Kunden — dedup
$in = implode(',', array_fill(0, count($domainIds), '?'));
$existierend = $db->query(
    "SELECT domain_id FROM lam_domain_customer WHERE customer_id = ? AND domain_id IN ($in)",
    array_merge([$customerId], $domainIds)
) ?: [];
$existSet = array_flip(array_map(fn($r) => $r['domain_id'], $existierend));

// Gültige Domains (nicht soft-deleted)
$gueltige = $db->query(
    "SELECT id FROM lam_domains WHERE id IN ($in) AND geloescht_am IS NULL",
    $domainIds
) ?: [];
$gueltigSet = array_flip(array_map(fn($r) => $r['id'], $gueltige));

$added = 0; $skipped = 0;
foreach ($domainIds as $did) {
    if (!isset($gueltigSet[$did])) { $skipped++; continue; }
    if (isset($existSet[$did]))   { $skipped++; continue; }
    $db->execute(
        "INSERT INTO lam_domain_customer (domain_id, customer_id, erstellt_am) VALUES (?, ?, NOW())",
        [$did, $customerId]
    );
    $added++;
}

Response::success(
    ['added' => $added, 'skipped' => $skipped],
    "$added Domain(s) zum Linkpool hinzugefügt" . ($skipped > 0 ? ", $skipped übersprungen" : '')
);
