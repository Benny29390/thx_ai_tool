<?php
/**
 * GET /api/v1/crm/dashboard
 * Liefert KPI-Zahlen für das CRM-Dashboard.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_CRM)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$db = Database::getInstance();
Response::success([
    'kontakte' => (int)$db->queryValue("SELECT COUNT(*) FROM crm_kontakte WHERE geloescht_am IS NULL"),
    'firmen'   => (int)$db->queryValue("SELECT COUNT(*) FROM crm_firmen WHERE geloescht_am IS NULL"),
    'listen'   => (int)$db->queryValue("SELECT COUNT(*) FROM crm_listen WHERE archiviert = 0"),
    'tags'     => (int)$db->queryValue("SELECT COUNT(*) FROM crm_tags"),
]);
