<?php

/**
 * Wissens-Dashboard: Heatmap Kunde x Schluesselthema + globale Stats.
 *
 * GET /knowledge/dashboard
 */

use Core\Auth;
use Core\Response;

global $db;

require_once SERVICES_PATH . '/KnowledgeService.php';

$isAdmin = Auth::isAdmin();
$allowedCustomerIds = $isAdmin ? null : array_map(fn($c) => (int)$c['id'], Auth::customers());

$svc = new \Services\KnowledgeService($db);

$customerTagsParam = $_GET['customer_tags'] ?? null;
$customerTags = [];
if (is_array($customerTagsParam)) $customerTags = array_values(array_filter(array_map('trim', $customerTagsParam)));
elseif (is_string($customerTagsParam) && $customerTagsParam !== '') $customerTags = array_values(array_filter(array_map('trim', explode(',', $customerTagsParam))));

$dashFilters = [
    'customer_status' => $_GET['customer_status'] ?? '',
    'customer_tags' => $customerTags,
];

$dashboard = $svc->getDashboard($allowedCustomerIds, $dashFilters);
$stats = $svc->getGlobalStats($allowedCustomerIds);

Response::success([
    'themes' => $dashboard['themes'],
    'customers' => $dashboard['customers'],
    'stats' => $stats,
]);
