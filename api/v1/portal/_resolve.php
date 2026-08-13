<?php
/**
 * Gemeinsamer Resolver fuer Portal-Chat-Endpunkte. Tenant-Isolation:
 * Customer-User -> fester Kunde (aus Auth); Team -> ?customer mit Zugriffspruefung.
 * Liefert [$db, $svc, $customerId, $userId, $isCustomer].
 */
use Core\Auth;
use Core\Database;
use Core\Response;

require_once SERVICES_PATH . '/CustomerPortalService.php';

$db     = Database::getInstance();
$userId = (int) Auth::id();
$isCustomer = Auth::isCustomer();

$customerId = null;
if ($isCustomer) {
    $customerId = Auth::portalCustomerId();
} else {
    $req = isset($_GET['customer']) ? (int) $_GET['customer'] : 0;
    if ($req && (Auth::isAdmin() || Auth::canAccessCustomer($req))) $customerId = $req;
}
if (!$customerId) Response::forbidden('Kein Kundenkontext');

$svc = new \Services\CustomerPortalService($db);

/** Conversation-ID pruefen: muss zum aufgeloesten Kunden gehoeren. Sonst 403. */
$resolveConversation = function (int $convId) use ($svc, $customerId): array {
    $conv = $svc->conversation($convId);
    if (!$conv || (int)$conv['customer_id'] !== (int)$customerId) Response::forbidden('Unterhaltung nicht zugänglich');
    return $conv;
};
