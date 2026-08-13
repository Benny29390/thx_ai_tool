<?php
/**
 * Öffentliche Multi-Plan-Übersicht (Sharelink) — kein Auth.
 *
 * GET  /public/projektplan-uebersicht/{hash}        → Übersicht (mit Snapshot- + Passwort-Logik)
 * POST /public/projektplan-uebersicht/{hash}/auth   → Passwort prüfen, Session-Flag setzen
 */

use Core\Database;
use Core\Response;
use Services\ProjektplannerService;

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$hash = trim((string) ($_GET['hash'] ?? ''));
$action = $_GET['action'] ?? '';
if ($hash === '') Response::notFound('Sharelink fehlt');

$svc = new ProjektplannerService($db);

// Passwort-Auth (Session-Flag pro Hash)
$sessKey = 'pp_multi_access_' . md5($hash);

if ($method === 'POST' && $action === 'auth') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $password = (string) ($payload['password'] ?? '');
    if ($svc->verifyMultiSharePassword($hash, $password)) {
        $_SESSION[$sessKey] = true;
        Response::success(['authorized' => true]);
    }
    Response::error('Falsches Passwort', 401);
}

$authenticated = !empty($_SESSION[$sessKey]);
$data = $svc->findMultiShareByHash($hash, $authenticated);
if (!$data) Response::notFound('Übersicht nicht gefunden');

if (!empty($data['expired'])) {
    Response::success([
        'expired'   => true,
        'title'     => $data['title'],
        'expires_at'=> $data['expires_at'],
    ]);
}
if (!empty($data['password_required'])) {
    Response::success([
        'password_required' => true,
        'title'             => $data['title'],
    ]);
}

// Schlanke Plan- + Row-Felder ausgeben
$plans = array_map(function ($p) {
    return [
        'id'              => (int) $p['id'],
        'title'           => $p['title'],
        'plan_status'     => $p['plan_status'],
        'plan_typ'        => $p['plan_typ'] ?? null,
        'period_from'     => $p['period_from'],
        'period_to'       => $p['period_to'],
        'customer_id'     => $p['customer_id'] ? (int) $p['customer_id'] : null,
        'customer_name'   => $p['customer_name'] ?? null,
        'customer_abbr'   => $p['customer_abbr'] ?? null,
        'rows' => array_map(function ($r) {
            return [
                'id'               => (int) $r['id'],
                'row_type'         => $r['row_type'],
                'description'      => $r['description'],
                'timeframe'        => $r['timeframe']        ?? null,
                'ist_hours'        => $r['ist_hours']        ?? null,
                'planned_hours'    => $r['planned_hours']    ?? null,
                'responsible'      => $r['responsible']      ?? null,
                'lead_responsible' => $r['lead_responsible'] ?? null,
                'deadline'         => $r['deadline']         ?? null,
                'is_done'          => (int) ($r['is_done']   ?? 0),
                'is_placeholder'   => (int) ($r['is_placeholder'] ?? 0),
                'no_ticket'        => (int) ($r['no_ticket'] ?? 0),
                'asana_gid'        => $r['asana_gid']        ?? null,
                'position'         => (int) ($r['position']  ?? 0),
            ];
        }, $p['rows'] ?? []),
    ];
}, $data['plans']);

// Team-Mitglieder als Kürzel-Map (für Personen-Anzeige)
$team = $db->query(
    "SELECT name, abbreviation, hex_color FROM pp_team_members WHERE is_active = 1"
) ?: [];

Response::success([
    'title'       => $data['title'],
    'filters'     => $data['filters'],
    'created_at'  => $data['created_at'],
    'is_snapshot' => !empty($data['is_snapshot']),
    'expires_at'  => $data['expires_at'] ?? null,
    'plans'       => $plans,
    'team'        => $team,
]);
