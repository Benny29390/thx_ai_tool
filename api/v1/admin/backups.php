<?php
/**
 * Backup-Status API
 *
 * GET  /admin/backups        → Liste aus storage/backup-status.json (vom Cron geschrieben)
 * POST /admin/backups/run    → Manueller Trigger (nur wenn sudoers-Eintrag das erlaubt;
 *                              sonst läuft das Backup als www-data und scheitert an
 *                              fehlenden Rechten auf /var/backups)
 *
 * Apache PHP hat open_basedir = /var/www:/tmp:/usr/share/php — daher liegt der
 * Status als JSON-Datei in /var/www/storage/ und wird vom Backup-Script gepflegt.
 */

use Core\Auth;
use Core\Response;

if (!Auth::isAdmin()) Response::forbidden();

const BK_STATUS_FILE = '/var/www/storage/backup-status.json';
const BK_SCRIPT = '/var/www/cli/backup.sh';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($action === 'run' && $method === 'POST') {
    $output = [];
    $rc = 0;
    @exec('/bin/bash ' . escapeshellarg(BK_SCRIPT) . ' 2>&1', $output, $rc);
    Response::success([
        'exit_code' => $rc,
        'output' => implode("\n", array_slice($output, -50)),
    ], $rc === 0 ? 'Backup OK' : 'Backup mit Fehler — siehe Output');
}

// GET: Status aus JSON-Datei laden
$status = null;
$cronActive = false;
if (is_file(BK_STATUS_FILE)) {
    $raw = @file_get_contents(BK_STATUS_FILE);
    $status = $raw ? json_decode($raw, true) : null;
}

// /etc/cron.d/ki-tool-backup liegt außerhalb open_basedir — wir können das nicht
// direkt prüfen. Wenn ein Status-File da ist und last_run innerhalb der letzten
// 36h liegt, gilt Cron als "aktiv".
if ($status && !empty($status['last_run_iso'])) {
    $lastTs = @strtotime($status['last_run_iso']);
    if ($lastTs && (time() - $lastTs) < 36 * 3600) $cronActive = true;
}

Response::success([
    'has_status' => $status !== null,
    'cron_active' => $cronActive,
    'cron_schedule' => 'täglich 03:00 (Server-Zeit)',
    'status' => $status,
    'note' => $status === null
        ? 'Noch kein Backup gelaufen — Cron läuft erstmals heute Nacht 03:00, oder löse manuell aus.'
        : null,
]);
