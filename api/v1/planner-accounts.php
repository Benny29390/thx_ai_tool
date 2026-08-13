<?php
/**
 * API für Tagesplaner-Asana-Accounts.
 * Routen (werden in api/handler.php verdrahtet):
 *   POST /planner/accounts                     — neuen Account anlegen
 *   POST /planner/accounts/{id}                — Label / Farbe bearbeiten
 *   POST /planner/accounts/{id}/delete         — Account + alle seine Tasks löschen
 */
use Core\Auth;
use Core\Response;
use Core\Crypto;

$db = \Core\Database::getInstance();
$userId = Auth::id();
if (!$userId) Response::unauthorized();

$accountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === '' && $method === 'POST' && $accountId === 0) {
    // Neuen Account anlegen
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $label = mb_substr(trim($payload['account_label'] ?? ''), 0, 60);
    $color = trim($payload['color_hex'] ?? '#0050a0');
    $patPlain = trim($payload['pat'] ?? '');
    if ($label === '') Response::error('Label fehlt');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0050a0';
    if ($patPlain === '') Response::error('PAT fehlt');

    // PAT testen
    require_once SERVICES_PATH . '/AsanaService.php';
    try {
        $asana = new \Services\AsanaService($patPlain);
        $me = $asana->getMe();
    } catch (\Throwable $e) { Response::error('PAT ungültig: ' . $e->getMessage()); }
    if (!$me) Response::error('PAT konnte Asana-Identität nicht laden');

    $encPat = Crypto::encrypt($patPlain);
    $sort = (int)$db->queryValue("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM user_asana_accounts WHERE user_id = ?", [$userId]);
    $defaultCustomerId = null;
    if (!empty($payload['default_customer_id'])) {
        $cid = (int)$payload['default_customer_id'];
        if ($db->queryValue("SELECT id FROM customers WHERE id = ?", [$cid])) $defaultCustomerId = $cid;
    }
    $id = $db->insert('user_asana_accounts', [
        'user_id' => $userId,
        'account_label' => $label,
        'asana_user_pat' => $encPat,
        'asana_user_gid' => $me['gid'] ?? null,
        'asana_user_email' => $me['email'] ?? null,
        'asana_user_name' => $me['name'] ?? null,
        'color_hex' => $color,
        'is_default' => 0,
        'is_active' => 1,
        'sort_order' => $sort,
        'default_customer_id' => $defaultCustomerId,
    ]);
    Response::success(['id' => $id, 'account_label' => $label]);
}

if ($action === '' && $method === 'POST' && $accountId > 0) {
    // Edit (Label + Farbe). PAT-Edit braucht separaten Endpoint.
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $acc = $db->queryOne("SELECT id FROM user_asana_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);
    if (!$acc) Response::notFound();
    $update = [];
    if (isset($payload['account_label'])) {
        $label = mb_substr(trim($payload['account_label']), 0, 60);
        if ($label !== '') $update['account_label'] = $label;
    }
    if (isset($payload['color_hex'])) {
        $c = trim($payload['color_hex']);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) $update['color_hex'] = $c;
    }
    if (array_key_exists('default_customer_id', $payload)) {
        $cid = $payload['default_customer_id'];
        if ($cid === null || $cid === '' || $cid === 0) {
            $update['default_customer_id'] = null;
        } else {
            $cid = (int)$cid;
            $exists = $db->queryValue("SELECT id FROM customers WHERE id = ?", [$cid]);
            if ($exists) $update['default_customer_id'] = $cid;
        }
    }
    if (!empty($payload['pat'])) {
        $patPlain = trim($payload['pat']);
        require_once SERVICES_PATH . '/AsanaService.php';
        try {
            $asana = new \Services\AsanaService($patPlain);
            $me = $asana->getMe();
            if (!$me) throw new \RuntimeException('Asana-Antwort leer');
        } catch (\Throwable $e) { Response::error('PAT ungültig: ' . $e->getMessage()); }
        $update['asana_user_pat'] = Crypto::encrypt($patPlain);
        $update['asana_user_gid'] = $me['gid'] ?? null;
        $update['asana_user_email'] = $me['email'] ?? null;
        $update['asana_user_name'] = $me['name'] ?? null;
    }
    if (empty($update)) Response::error('Nichts zu ändern');
    $db->update('user_asana_accounts', $update, 'id = ?', [$accountId]);
    Response::success(['id' => $accountId]);
}

if ($action === 'delete' && $method === 'POST' && $accountId > 0) {
    $acc = $db->queryOne("SELECT id, is_default FROM user_asana_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);
    if (!$acc) Response::notFound();
    if (!empty($acc['is_default'])) Response::error('Default-Account kann nicht gelöscht werden — erst einen anderen als Default setzen');
    // Tasks dieses Accounts löschen, dann Account selbst
    $db->execute("DELETE FROM planner_tasks WHERE user_id = ? AND asana_account_id = ?", [$userId, $accountId]);
    $db->execute("DELETE FROM user_asana_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);
    Response::success(['deleted' => $accountId]);
}

Response::notFound();
