<?php
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$filter = [];
if (!empty($_GET['nur_alerts'])) $filter['nur_alerts'] = true;
if (!empty($_GET['nur_unmuted'])) $filter['nur_unmuted'] = true;
if (!empty($_GET['customer_id'])) $filter['customer_id'] = $_GET['customer_id'];

Response::success($svc->listeMonitoring($filter));
