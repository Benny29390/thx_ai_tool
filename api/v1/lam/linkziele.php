<?php
/**
 * GET /lam/linkziele
 * Liste Linkziele (Stammdaten pro Kunde).
 * Query: customer_id, status, suche
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$filter = [
    'customer_id' => trim((string)($_GET['customer_id'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'suche' => trim((string)($_GET['suche'] ?? '')),
];

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$rows = $svc->listeLinkziele($filter);

// Auf erlaubte Kunden filtern
$allowed = Auth::customers();
$allowedIds = array_column($allowed, 'id');
$rows = array_values(array_filter($rows, fn($r) => in_array((int)$r['customer_id'], array_map('intval', $allowedIds), true)));

Response::success($rows);
