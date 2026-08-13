<?php
/**
 * POST /lam/historien-import-ausfuehren
 * Body: { ziel: 'massnahmen'|'auslagen'|'korrespondenz'|'linkprofil',
 *         zeilen: [ { feld: wert, ... }, ... ],
 *         customer_id?: int }
 *
 * Pro Zeile wird ein Insert versucht. Dubletten beim Linkprofil werden übersprungen.
 * Rückgabe: { ok, fehler, fehler_liste }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) Response::error('JSON-Body erwartet', 400);

$ziel = trim((string)($input['ziel'] ?? ''));
$zeilen = $input['zeilen'] ?? [];
$customerId = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;

if (!in_array($ziel, ['massnahmen','auslagen','korrespondenz','linkprofil'], true)) {
    Response::error('Ungültiges Ziel', 400);
}
if (!is_array($zeilen) || empty($zeilen)) Response::error('zeilen erforderlich', 400);
if (count($zeilen) > 5000) Response::error('Max 5000 Zeilen pro Import', 400);

if ($customerId && !Auth::canAccessCustomer($customerId)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $r = $svc->importiereHistorie($ziel, $zeilen, $customerId);
    Response::success($r, "Import OK: {$r['ok']} angelegt, {$r['fehler']} Fehler");
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::error('Import fehlgeschlagen: ' . $e->getMessage(), 500);
}
