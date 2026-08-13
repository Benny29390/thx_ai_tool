<?php
/**
 * System-Update-Steuerung (Admin).
 *
 * GET  /admin/update            — aktuellen Stand + Update-Status liefern
 * POST /admin/update            — { action: 'install' } Update anfordern
 *
 * Das eigentliche Update laeuft privilegiert per Cron (scripts/update.php).
 * Die Web-Seite schreibt nur eine Anforderungs-Markierung (storage/UPDATE_REQUESTED)
 * und liest die vom Cron gepflegte Statusdatei — so braucht der Webserver keinen
 * Schreibzugriff aufs Git-Repo.
 */
use Core\Auth;
use Core\Response;
use Core\Version;

if (!Auth::can(CAP_SETTINGS_MANAGE)) Response::forbidden('Keine Berechtigung');

global $method, $input;

$root        = ROOT_PATH;
$statusFile  = $root . '/storage/update-status.json';
$requestFile = $root . '/storage/UPDATE_REQUESTED';
$maintFile   = $root . '/storage/MAINTENANCE';
$lockFile    = $root . '/storage/UPDATE.lock';

if ($method === 'GET') {
    $cached = null;
    if (is_file($statusFile)) {
        $cached = json_decode((string) @file_get_contents($statusFile), true) ?: null;
    }
    Response::success([
        'live' => Version::status(),           // aktueller Stand (read-only, immer verfuegbar)
        'cached' => $cached,                    // vom Cron: behind/available/changes/checked_at
        'update_requested' => is_file($requestFile),
        'maintenance' => is_file($maintFile),
        'running' => is_file($lockFile),
    ]);
}

if ($method !== 'POST') Response::error('Method not allowed', 405);

$action = (string) ($input['action'] ?? '');

if ($action === 'install') {
    if (!Version::hasRemote()) {
        Response::error('Kein Update-Server (Remote) konfiguriert.');
    }
    if (is_file($lockFile)) {
        Response::error('Es laeuft bereits ein Update.');
    }
    // Nur anfordern, wenn ueberhaupt ein Update ansteht (laut Cron-Status).
    $behind = null;
    if (is_file($statusFile)) {
        $c = json_decode((string) @file_get_contents($statusFile), true);
        $behind = $c['behind'] ?? null;
    }
    if ($behind === 0) {
        Response::error('Es ist bereits die aktuelle Version installiert.');
    }
    if (@file_put_contents($requestFile, gmdate('c') . ' by user ' . (Auth::user()['id'] ?? '?') . "\n") === false) {
        Response::error('Anforderung konnte nicht gespeichert werden (Schreibrechte storage/).');
    }
    try {
        if (class_exists('\\Core\\AuditLog')) {
            \Core\AuditLog::record('system', 'update', 'angefordert', []);
        }
    } catch (\Throwable $e) {}
    Response::success(['requested' => true], 'Update angefordert — es wird in Kürze im Hintergrund installiert.');
}

Response::error('Unbekannte Aktion.');
