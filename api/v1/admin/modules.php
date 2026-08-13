<?php
/**
 * Modul-Verwaltung (installationsweit).
 *
 * GET  /admin/modules   — alle Module mit Status (licensed/enabled/active/core)
 * POST /admin/modules   — Modul ein-/ausschalten (Aktiv-Ebene)
 *   Body: { module_key: 'lam', enabled: true|false }
 *
 * Nur fuer Einstellungs-Verwalter. core-Module lassen sich nicht abschalten;
 * nicht-lizenzierte Module lassen sich nicht einschalten.
 */
use Core\Auth;
use Core\Response;
use Core\Modules;

if (!Auth::can(CAP_SETTINGS_MANAGE)) Response::forbidden('Keine Berechtigung');

global $method, $input;

if ($method === 'GET') {
    Response::success([
        'module'    => Modules::withState(),
        'selfcheck' => Modules::selfCheck(),
    ]);
}

if ($method !== 'POST') Response::error('Method not allowed', 405);

$key = trim((string) ($input['module_key'] ?? ''));
$on  = !empty($input['enabled']);

$mod = Modules::get($key);
if ($mod === null) {
    Response::error('Unbekanntes Modul: ' . $key);
}
if (!empty($mod['core'])) {
    Response::error('Kernmodule koennen nicht abgeschaltet werden.');
}
if ($on && !Modules::licensed($key)) {
    Response::error('Dieses Modul ist auf dieser Installation nicht freigeschaltet (Lizenz).');
}

$vorher = Modules::enabled($key);
if (!Modules::setEnabled($key, $on)) {
    Response::error('Aenderung nicht moeglich.');
}

// Governance-Aktion protokollieren (fehlertolerant).
try {
    if (class_exists('\\Core\\AuditLog')) {
        \Core\AuditLog::record('module', $key, $on ? 'aktiviert' : 'deaktiviert', [
            'von' => $vorher ? 'an' : 'aus',
            'nach' => $on ? 'an' : 'aus',
        ]);
    }
} catch (\Throwable $e) {
    // Audit-Fehler darf die Aktion nicht scheitern lassen.
}

Response::success([
    'module_key' => $key,
    'enabled'    => $on,
    'active'     => Modules::active($key),
], $on ? 'Modul aktiviert' : 'Modul deaktiviert');
