<?php
/**
 * Kundenportal-Verwaltung (Team): Modul-Freischaltung, Kachel-Sichtbarkeit, Customer-User anlegen.
 * Zugriff bereits im Handler geprueft (Admin oder Kundenzugriff). Aktion in $_GET['portal_action'].
 */
use Core\Auth;
use Core\Database;
use Core\Response;

require_once SERVICES_PATH . '/CustomerPortalService.php';

$db     = Database::getInstance();
$cid    = (int) ($_GET['customer_id'] ?? 0);
$action = (string) ($_GET['portal_action'] ?? '');
$userId = (int) Auth::id();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if (!$db->queryOne("SELECT id FROM customers WHERE id = ?", [$cid])) Response::notFound('Kunde nicht gefunden');

// ── Modul freischalten / sperren ────────────────────────────────────────────
if ($action === 'module') {
    $mk = trim((string) ($input['module_key'] ?? ''));
    if (!in_array($mk, \Services\CustomerPortalService::MODULES, true)) Response::error('Unbekanntes Modul');
    $enabled = !empty($input['enabled']) ? 1 : 0;
    $ex = $db->queryOne("SELECT id FROM customer_portal_permissions WHERE customer_id = ? AND module_key = ?", [$cid, $mk]);
    if ($ex) {
        $db->update('customer_portal_permissions', ['enabled' => $enabled, 'updated_by' => $userId], 'id = ?', [$ex['id']]);
    } else {
        $db->insert('customer_portal_permissions', [
            'customer_id' => $cid, 'module_key' => $mk, 'kind' => 'module',
            'enabled' => $enabled, 'created_by' => $userId, 'updated_by' => $userId,
        ]);
    }
    Response::success(['module_key' => $mk, 'enabled' => (bool) $enabled], 'Gespeichert');
}

// ── Kachel fuer Kunden sichtbar / unsichtbar (jeder Typ, Team entscheidet) ───
if ($action === 'card-visible') {
    $cardId  = (int) ($input['card_id'] ?? 0);
    $visible = !empty($input['visible']) ? 1 : 0;
    $card = $db->queryOne("SELECT id FROM customer_cards WHERE id = ? AND customer_id = ?", [$cardId, $cid]);
    if (!$card) Response::notFound('Karte nicht gefunden');
    $db->update('customer_cards', ['customer_visible' => $visible], 'id = ?', [$cardId]);
    Response::success(['card_id' => $cardId, 'visible' => (bool) $visible], 'Gespeichert');
}

// ── KI-Auto-Antwort an/aus (Team-Uebernahme) — pro Unterhaltung ─────────────
if ($action === 'ki-toggle') {
    $on = !empty($input['enabled']);
    $convId = (int) ($input['conversation_id'] ?? 0);
    $svc = new \Services\CustomerPortalService($db);
    $conv = $convId ? $svc->conversation($convId) : null;
    if (!$conv || (int)$conv['customer_id'] !== $cid) Response::error('Unterhaltung nicht gefunden');
    $svc->setKiActive($convId, $on, $userId);
    Response::success(['ki_active' => $on], $on ? 'KI antwortet wieder automatisch' : 'KI pausiert — Du übernimmst');
}

// ── Spezial-Kachel (z.B. Projektplanner-Dashboard) fuer Kunden freischalten ──
if ($action === 'shell-visible') {
    $key = trim((string) ($input['key'] ?? ''));
    if ($key !== 'projektplanner') Response::error('Unbekannte Kachel');
    $on = !empty($input['visible']) ? 1 : 0;
    $mk = 'shell_' . $key;
    $ex = $db->queryOne("SELECT id FROM customer_portal_permissions WHERE customer_id = ? AND module_key = ?", [$cid, $mk]);
    if ($ex) $db->update('customer_portal_permissions', ['enabled' => $on, 'kind' => 'setting', 'updated_by' => $userId], 'id = ?', [$ex['id']]);
    else $db->insert('customer_portal_permissions', ['customer_id' => $cid, 'module_key' => $mk, 'kind' => 'setting', 'enabled' => $on, 'created_by' => $userId, 'updated_by' => $userId]);
    Response::success(['key' => $key, 'visible' => (bool) $on], 'Gespeichert');
}

// ── Customer-User anlegen (an genau diesen Kunden gebunden) ──────────────────
if ($action === 'user') {
    $name  = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('Name und gültige E-Mail erforderlich');
    if ($db->queryOne("SELECT id FROM users WHERE email = ?", [$email])) Response::error('Diese E-Mail ist bereits vergeben');

    $pw  = bin2hex(random_bytes(5)); // 10-stelliges Einmal-Passwort
    $uid = (int) $db->insert('users', [
        'email' => $email, 'name' => $name, 'role' => 'customer',
        'customer_id' => $cid, 'is_active' => 1,
        'password_hash' => password_hash($pw, PASSWORD_BCRYPT),
    ]);
    $db->insert('user_customers', ['user_id' => $uid, 'customer_id' => $cid, 'is_default' => 1]);
    try { \Core\AuditLog::record('customer_portal_user', (string) $uid, 'create', ['customer_id' => $cid, 'email' => $email]); } catch (\Throwable $e) {}

    Response::success([
        'id' => $uid, 'name' => $name, 'email' => $email,
        'temp_password' => $pw, // Einmal-Passwort: dem Kunden mitteilen, er kann es spaeter aendern
    ], 'Customer-User angelegt');
}

Response::error('Unbekannte Aktion');
