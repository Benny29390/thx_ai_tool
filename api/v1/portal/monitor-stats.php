<?php
/** Kundenportal: Monitoring-Stats (dieselben Daten wie im Steckbrief, tenant-gescoped). */
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $customerId

// Nur wenn die Monitoring-Kachel fuer den Kunden freigegeben ist
$vis = $db->queryOne("SELECT customer_visible FROM customer_cards WHERE customer_id = ? AND system_key = 'site_monitor' AND customer_visible = 1 LIMIT 1", [$customerId]);
if (!$vis) Response::forbidden('Nicht freigegeben');

require_once SERVICES_PATH . '/PageMonitorService.php';
$svc = new \Services\PageMonitorService($db);
Response::success($svc->getStatsForCustomer((int)$customerId, 30));
