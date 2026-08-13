<?php
/**
 * GET /lam/tags
 * Liste aller Tags (Stammdaten) mit Verwendungszahl.
 * Query: suche, nur_unbenutzt
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$filter = [
    'suche' => trim((string)($_GET['suche'] ?? '')),
    'nur_unbenutzt' => !empty($_GET['nur_unbenutzt']),
];

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
Response::success($svc->listeTags($filter));
