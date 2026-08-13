<?php
use Core\Auth; use Core\Database; use Core\Response;
if (!Auth::can(CAP_CRM_MIGRATION)) Response::forbidden();
require_once SERVICES_PATH . '/CrmBrevoService.php';
require_once SERVICES_PATH . '/CrmKontaktService.php';
require_once SERVICES_PATH . '/CrmMigrationService.php';

$db = Database::getInstance();
require_once SERVICES_PATH . '/CrmMigrationService.php';

$runId = (int)($_GET['run_id'] ?? 0);
$svc = new \Services\CrmMigrationService($db, new \Services\CrmBrevoService(''), new \Services\CrmKontaktService($db));

if ($runId > 0) {
    Response::success(['run' => $svc->ladeLauf($runId)]);
}
Response::success(['runs' => $svc->laufHistorie(20), 'letzter_erfolg' => $svc->letzterLauf()]);
