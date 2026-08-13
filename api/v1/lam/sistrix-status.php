<?php
/**
 * Sistrix-Wochenstatus (Credits, Kontingent, konfiguriert).
 * GET /lam/sistrix-status
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\SistrixService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

require_once SERVICES_PATH . '/SistrixService.php';
$svc = new SistrixService(Database::getInstance());
Response::success($svc->wochenStatus());
