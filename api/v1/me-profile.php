<?php
/**
 * Eigenes Profil — Name + Kuerzel.
 *
 * PUT /me/profile  Body: {name?, abbreviation?}
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$user = Auth::user();
if (!$user) Response::unauthorized();
if ($method !== 'PUT') Response::error('Method not allowed', 405);

$updates = [];

if (isset($input['name'])) {
    $name = trim($input['name']);
    if ($name === '') Response::error('Name darf nicht leer sein');
    if (mb_strlen($name) > 255) Response::error('Name zu lang');
    $updates['name'] = $name;
}

// Kuerzel darf nur der Admin selbst aendern — fuer alle anderen wird
// das Feld serverseitig stillschweigend ignoriert (Backend-Schutz zur UI).
if (array_key_exists('abbreviation', $input) && Auth::isAdmin()) {
    $abbr = mb_strtoupper(trim((string) $input['abbreviation']));
    if ($abbr !== '' && !preg_match('/^[A-Z0-9]+$/', $abbr)) {
        Response::error('Kürzel: nur Buchstaben und Zahlen');
    }
    if (mb_strlen($abbr) > 5) Response::error('Kürzel max. 5 Zeichen');
    $updates['abbreviation'] = $abbr ?: null;
}

if (empty($updates)) Response::error('Nichts zu aendern');

$db->update('users', $updates, 'id = ?', [(int) $user['id']]);

$fresh = $db->queryOne(
    "SELECT id, name, abbreviation, email, role FROM users WHERE id = ?",
    [(int) $user['id']]
);

Response::success($fresh, 'Profil aktualisiert');
