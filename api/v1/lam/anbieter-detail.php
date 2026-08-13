<?php
/**
 * LAM Anbieter-Detail API
 * GET /lam/anbieter-detail?id=...
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$id = trim($_GET['id'] ?? '');
if ($id === '') Response::error('id fehlt', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$detail = $svc->getAnbieterDetail($id);
if (!$detail) Response::error('Anbieter nicht gefunden', 404);

Response::success($detail);
