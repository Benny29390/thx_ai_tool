<?php
/**
 * POST /admin/brevo-test
 * Body: { api_key?: string } — wenn leer, wird gespeicherter Key genutzt
 * Liefert: { ok, account, lists }
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if (!Auth::isAdmin()) Response::forbidden();
if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once SERVICES_PATH . '/CrmBrevoService.php';

$apiKey = trim((string)($input['api_key'] ?? ''));
if ($apiKey === '') {
    $apiKey = (string)\Core\Settings::get('brevo_api_key', '');
}
if ($apiKey === '') {
    Response::error('Brevo-API-Key nicht gesetzt');
}

$brevo = new \Services\CrmBrevoService($apiKey);
$test = $brevo->testConnection();
if (!$test['ok']) {
    Response::error('Brevo-Verbindung fehlgeschlagen: ' . ($test['error'] ?? 'unbekannt'));
}

$lists = [];
try {
    $lists = $brevo->listLists(50);
} catch (\Throwable $e) {
    // Listen-Abfrage ist optional fuer den Connection-Test
}

Response::success([
    'ok' => true,
    'account' => $test['account'],
    'lists' => $lists,
], 'Verbindung OK');
