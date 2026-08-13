<?php
/**
 * Startet die Microsoft-Anmeldung: leitet den Nutzer zu Microsoft weiter.
 * Der `state` bindet die Rueckleitung an dieses Konto UND an diese Session —
 * ohne ihn koennte jemand eine fremde Rueckleitung unterschieben.
 */

use Core\Auth;
use Core\Response;

global $db;

$kontoId = (int) ($_GET['konto_id'] ?? 0);
if (!$kontoId) {
    Response::error('konto_id fehlt');
}

require_once SERVICES_PATH . '/MailOAuthService.php';

try {
    $oauth = new \Services\MailOAuthService($db);

    $state = bin2hex(random_bytes(16));
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['mail_oauth_state'] = ['state' => $state, 'konto_id' => $kontoId, 'ts' => time()];

    header('Location: ' . $oauth->authorizeUrl($kontoId, $state));
    exit;
} catch (\Throwable $e) {
    Response::error($e->getMessage());
}
