<?php
/**
 * Usage API
 */

use Core\Auth;
use Core\Response;

global $db, $method;

require_once SERVICES_PATH . '/UsageTracker.php';

$tracker = new \Services\UsageTracker($db);

switch ($method) {
    case 'GET':
        $month = $_GET['month'] ?? date('Y-m');

        if (Auth::isAdmin()) {
            // Globale Stats
            $stats = $tracker->getGlobalStats($month);
        } else {
            // Kunden-Stats
            $customerId = Auth::customerId();
            if (!$customerId) {
                Response::error('Kein Kunde zugeordnet');
            }
            $stats = $tracker->getCustomerStats($customerId, $month);
        }

        Response::success($stats);
        break;

    default:
        Response::error('Method not allowed', 405);
}
