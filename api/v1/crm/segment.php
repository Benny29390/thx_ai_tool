<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmSegmentService.php';
$svc = new \Services\CrmSegmentService($db);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id fehlt', 400);
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { $svc->loeschen($id); Response::success(['ok' => true]); }
Response::error('Methode nicht erlaubt', 405);
