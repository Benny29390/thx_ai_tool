<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmBrevoService.php';
require_once SERVICES_PATH . '/CrmKontaktService.php';
require_once SERVICES_PATH . '/CrmMigrationService.php';
$svc = new \Services\CrmMigrationService($db, new \Services\CrmBrevoService(''), new \Services\CrmKontaktService($db));
Response::success(['runs' => $svc->laufHistorie(50)]);
