<?php
/**
 * POST /api/v1/lam/linkpool-remove
 * Body: { customer_id: int, domain_ids: [string,...] }
 * Entfernt Domains aus dem Linkpool eines Kunden (löscht lam_domain_customer-Einträge).
 * Die Domains selbst bleiben in lam_domains erhalten.
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
$in = implode(',', array_fill(0, count($domainIds), '?'));
$removed = $db->execute(
    "DELETE FROM lam_domain_customer WHERE customer_id = ? AND domain_id IN ($in)",
    array_merge([$customerId], $domainIds)
);

Response::success(['removed' => $removed], "$removed Domain(s) aus dem Linkpool entfernt");
