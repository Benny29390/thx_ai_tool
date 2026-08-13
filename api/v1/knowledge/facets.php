<?php

/**
 * Facets fuer die Sidebar-Navigation: pro Quelle/Kategorie/Kunde/Tag die
 * Counts unter Beruecksichtigung der bereits aktiven Filter.
 *
 * GET /knowledge/facets?customer_id=&source_type=&category=&search=
 */

use Core\Auth;
use Core\Response;

global $db;

require_once SERVICES_PATH . '/KnowledgeService.php';

$isAdmin = Auth::isAdmin();
$allowedCustomerIds = array_map(fn($c) => (int)$c['id'], Auth::customers());

$reqCustomerId = isset($_GET['customer_id']) && $_GET['customer_id'] !== ''
    ? ($_GET['customer_id'] === 'null' ? 'null' : (int) $_GET['customer_id'])
    : null;
if (!$isAdmin && is_int($reqCustomerId) && !in_array($reqCustomerId, $allowedCustomerIds, true)) {
    $reqCustomerId = null;
}

$parseList = function($name) {
    $v = $_GET[$name] ?? null;
    if (is_array($v)) return array_values(array_filter(array_map('trim', $v)));
    if (is_string($v) && $v !== '') return array_values(array_filter(array_map('trim', explode(',', $v))));
    return [];
};
$tags = $parseList('tags');
$customerTags = $parseList('customer_tags');

$filters = [
    'customer_id' => $reqCustomerId,
    'category' => $_GET['category'] ?? null,
    'source_type' => $_GET['source_type'] ?? null,
    'ingest_mode' => $_GET['ingest_mode'] ?? null,
    'search' => $_GET['search'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'tags' => $tags,
    'customer_tags' => $customerTags,
    'customer_status' => $_GET['customer_status'] ?? null,
    'size_bucket' => $_GET['size_bucket'] ?? null,
    'status' => $_GET['status'] ?? null,
    'allowed_customer_ids' => $isAdmin ? null : $allowedCustomerIds,
];

$svc = new \Services\KnowledgeService($db);
Response::success($svc->getFacets($filters));
