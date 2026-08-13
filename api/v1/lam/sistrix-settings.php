<?php
/**
 * Sistrix-Settings speichern (API-Key, Wochenkontingent).
 * POST /lam/sistrix-settings
 * Body: { api_key?, wochenkontingent? }
 *
 * Wenn api_key leer ist und es einen alten gibt, wird der ALTE behalten
 * (Schutz vor versehentlichem Loeschen). Explizites Loeschen: api_key = '__clear__'.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Settings;

if (!Auth::isAdmin()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$db = Database::getInstance();

$apiKey = isset($input['api_key']) ? trim((string)$input['api_key']) : null;
$wochenKontingent = isset($input['wochenkontingent']) ? (int)$input['wochenkontingent'] : null;

if ($apiKey !== null) {
    if ($apiKey === '__clear__') {
        Settings::forget('sistrix_api_key');
    } elseif ($apiKey !== '') {
        // Settings::set verschluesselt automatisch (api_key matched isSecret)
        Settings::set('sistrix_api_key', $apiKey, 'string', 'Sistrix-API-Key für LAM-Linkquellen-Anreicherung');
    }
    // Wenn $apiKey === '' (leer) — nichts tun, alten Key behalten
}

if ($wochenKontingent !== null && $wochenKontingent > 0) {
    Settings::set('sistrix_wochenkontingent', (string)$wochenKontingent, 'int', 'Sistrix-API Wochenkontingent (Credits, default 20000)');
}

Response::success([
    'api_key_gesetzt' => Settings::get('sistrix_api_key') !== null,
    'wochenkontingent' => (int)(Settings::get('sistrix_wochenkontingent') ?: 20000),
]);
