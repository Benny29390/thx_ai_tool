<?php

/**
 * Quick-Create eines Kunden aus Inline-Pickern (Chat-Save, Wissen-Edit,
 * Transkript-Upload, Steckbrief-Import).
 *
 * POST /customers/quick-create
 * Body: { name (Pflicht), abbreviation?, industry? }
 * Slug wird automatisch aus dem Namen generiert.
 *
 * Response: { id, name, slug, abbreviation }
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$db = Database::getInstance();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$name = trim((string) ($input['name'] ?? ''));
$abbr = trim((string) ($input['abbreviation'] ?? ''));
$industry = trim((string) ($input['industry'] ?? ''));

if ($name === '') Response::error('Name erforderlich');

// Slug aus Namen generieren — lowercase, Umlaute weg, Sonderzeichen weg
$slugBase = $name;
$slugBase = strtr($slugBase, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']);
$slugBase = mb_strtolower($slugBase);
$slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase);
$slugBase = trim($slugBase, '-');
if ($slugBase === '') Response::error('Name enthaelt keine gueltigen Zeichen fuer einen Slug');

// Eindeutig machen
$slug = $slugBase;
$i = 2;
while ($db->queryOne("SELECT id FROM customers WHERE slug = ?", [$slug])) {
    $slug = $slugBase . '-' . $i;
    if (++$i > 20) Response::error('Slug-Generierung fehlgeschlagen');
}

// Abbreviation: wenn nicht gesetzt, aus Name ableiten (erste Buchstaben max 3 Worte)
if ($abbr === '') {
    $parts = preg_split('/\s+/', trim($name));
    $abbr = '';
    foreach ($parts as $p) {
        if ($abbr !== '' && mb_strlen($abbr) >= 3) break;
        $abbr .= mb_strtoupper(mb_substr($p, 0, 1));
    }
} else {
    $abbr = mb_strtoupper($abbr);
}
$abbr = mb_substr($abbr, 0, 10);

if ($abbr && !preg_match('/^[\p{Lu}\p{N}&·\.\-\+\@\/\s]+$/u', $abbr)) {
    Response::error('Kürzel ungueltig — nur Grossbuchstaben, Zahlen und gaengige Sonderzeichen');
}

try {
    $id = $db->insert('customers', [
        'name' => $name,
        'slug' => $slug,
        'abbreviation' => $abbr ?: null,
        'industry' => $industry ?: null,
        'is_active' => 1,
    ]);
    Response::success([
        'id' => (int) $id,
        'name' => $name,
        'slug' => $slug,
        'abbreviation' => $abbr,
    ], 'Kunde angelegt');
} catch (\Throwable $e) {
    Response::error('Kunde konnte nicht angelegt werden: ' . $e->getMessage());
}
