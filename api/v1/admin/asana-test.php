<?php
/**
 * POST /admin/asana-test
 * Body: {pat?: string} — wenn nicht gesendet, wird der gespeicherte PAT genutzt
 *
 * Liefert: {ok, user, workspaces}
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if (!Auth::isAdmin()) Response::forbidden();
if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once SERVICES_PATH . '/AsanaService.php';

$pat = $input['pat'] ?? null;
if (!$pat) {
    $pat = \Core\Settings::get('asana_pat');
}

if (empty($pat)) Response::error('Asana PAT nicht konfiguriert');

$asana = new \Services\AsanaService($pat);
$test = $asana->testConnection();

if (!$test['ok']) {
    Response::error('Asana-Verbindung fehlgeschlagen: ' . $test['error']);
}

try {
    $workspaces = $asana->listWorkspaces();
} catch (\Exception $e) {
    $workspaces = [];
}

Response::success([
    'ok' => true,
    'user' => $test['user'],
    'workspaces' => $workspaces,
], 'Verbindung OK');
