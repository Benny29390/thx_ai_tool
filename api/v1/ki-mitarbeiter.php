<?php
/**
 * KI-Mitarbeiter-Builder — API-Dispatcher.
 * Cap-Gate (CAP_KI_MITARBEITER) ist bereits über capRules erledigt.
 * Verteilt anhand von $uri + $method auf die einzelnen Aktionen.
 *
 * Sicherheit: kundengebundene Mitarbeiter nur für berechtigte Nutzer
 * (Auth::canAccessCustomer); kritische Aktionen (Zugriffs-Freigabe, Not-Aus,
 * Aktivierung) nur Admin. Status/Rechte werden NIE aus Modell-/Profil-Patch gesetzt.
 */

use Core\Auth;
use Core\Response;
use Core\AuditLog;
use Services\KiMitarbeiterService;

require_once SERVICES_PATH . '/KiMitarbeiterService.php';

global $db, $method, $input, $uri;

$svc = new KiMitarbeiterService($db);
$actor = (int) Auth::id();

/** Zugriffskontrolle auf einen konkreten Mitarbeiter (Kundenbindung). */
$loadEmployee = function (int $id) use ($svc) {
    $e = $svc->get($id);
    if (!$e) Response::error('KI-Mitarbeiter nicht gefunden', 404);
    if (!empty($e['customer_id']) && !Auth::canAccessCustomer((int) $e['customer_id'])) {
        Response::forbidden('Kein Zugriff auf diesen Kunden.');
    }
    return $e;
};

// ---- Kollektion: /ki-mitarbeiter ----
if ($uri === '/ki-mitarbeiter') {
    if ($method === 'GET') {
        $filter = [];
        if (!empty($_GET['status'])) $filter['status'] = $_GET['status'];
        if (!Auth::isAdmin()) {
            $filter['allowed_customer_ids'] = Auth::customers();
        }
        Response::success(['employees' => $svc->liste($filter)]);
    }
    if ($method === 'POST') {
        $id = $svc->create($input ?? [], $actor);
        Response::success(['id' => $id], 'Entwurf angelegt');
    }
    Response::error('Method not allowed', 405);
}

// ---- Zugriffs-Freigabe (Admin): /ai-permissions/{id}/approve|reject ----
if (preg_match('#^/ai-permissions/(\d+)/(approve|reject)$#', $uri, $m)) {
    if ($method !== 'POST') Response::error('Method not allowed', 405);
    if (!Auth::isAdmin()) Response::forbidden('Nur Admins können Zugriffe freigeben.');
    $permId = (int) $m[1];
    $perm = $db->queryOne("SELECT * FROM ai_tool_permissions WHERE id = ?", [$permId]);
    if (!$perm) Response::error('Antrag nicht gefunden', 404);
    $newStatus = $m[2] === 'approve' ? 'approved' : 'rejected';
    $db->update('ai_tool_permissions', [
        'status' => $newStatus,
        'approved_by' => $actor,
        'approved_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$permId]);
    AuditLog::record('ai_employee', (string) $perm['ai_employee_id'], 'permission_' . $newStatus,
        ['tool' => $perm['tool_key'], 'level' => $perm['permission_level']], $actor);
    Response::success(['status' => $newStatus], $m[2] === 'approve' ? 'Zugriff freigegeben' : 'Antrag abgelehnt');
}

// ---- Ab hier: /ki-mitarbeiter/{id}/... ----
if (preg_match('#^/ki-mitarbeiter/(\d+)(/.*)?$#', $uri, $m)) {
    $id = (int) $m[1];
    $sub = $m[2] ?? '';

    // Einzelabruf / Meta-Update
    if ($sub === '' || $sub === '/') {
        $e = $loadEmployee($id);
        if ($method === 'GET') Response::success($e);
        if ($method === 'PATCH' || $method === 'PUT') {
            $svc->updateMeta($id, $input ?? [], $actor);
            Response::success($svc->get($id), 'Gespeichert');
        }
        if ($method === 'DELETE') {
            if (!Auth::isAdmin() && (int) $e['created_by'] !== $actor) Response::forbidden();
            $db->execute("DELETE FROM ai_employees WHERE id = ?", [$id]);
            AuditLog::record('ai_employee', (string) $id, 'deleted', null, $actor);
            Response::success(null, 'Gelöscht');
        }
        Response::error('Method not allowed', 405);
    }

    $loadEmployee($id); // Zugriffscheck fuer alle Unterrouten

    // Profil-Patch (Tab-Formulare)
    if ($sub === '/profile' && $method === 'POST') {
        $profile = $svc->patchProfile($id, $input['patch'] ?? $input ?? [], $actor);
        Response::success(['profile' => $profile, 'completeness' => $svc->completeness($profile)], 'Gespeichert');
    }

    // Lebenszyklus
    if (($sub === '/transition') && $method === 'POST') {
        $to = (string) ($input['to'] ?? '');
        // Aktivierung ist eine kritische Aktion -> nur Admin
        if ($to === 'active' && !Auth::isAdmin()) Response::forbidden('Nur Admins können aktivieren.');
        try { $svc->transition($id, $to, $actor); }
        catch (\Throwable $ex) { Response::error($ex->getMessage()); }
        Response::success($svc->get($id), 'Status geändert');
    }
    if ($sub === '/submit-review' && $method === 'POST') {
        try { $svc->transition($id, 'review', $actor); }
        catch (\Throwable $ex) { Response::error($ex->getMessage()); }
        Response::success($svc->get($id), 'Zur Prüfung eingereicht');
    }
    if ($sub === '/pause' && $method === 'POST') {
        try { $svc->transition($id, 'paused', $actor); }
        catch (\Throwable $ex) { Response::error($ex->getMessage()); }
        Response::success($svc->get($id), 'Pausiert');
    }
    if ($sub === '/archive' && $method === 'POST') {
        try { $svc->transition($id, 'archived', $actor); }
        catch (\Throwable $ex) { Response::error($ex->getMessage()); }
        Response::success($svc->get($id), 'Archiviert');
    }

    // Zugriffs-Antrag stellen
    if ($sub === '/permissions/request' && $method === 'POST') {
        $permId = $db->insert('ai_tool_permissions', [
            'ai_employee_id'   => $id,
            'tool_key'         => (string) ($input['tool_key'] ?? ''),
            'resource_scope'   => json_encode($input['resource_scope'] ?? [], JSON_UNESCAPED_UNICODE),
            'permission_level' => (string) ($input['permission_level'] ?? 'read'),
            'status'           => 'requested',
            'justification'    => (string) ($input['justification'] ?? ''),
            'requested_by'     => $actor,
        ]);
        AuditLog::record('ai_employee', (string) $id, 'permission_requested',
            ['tool' => $input['tool_key'] ?? '', 'level' => $input['permission_level'] ?? ''], $actor);
        Response::success(['id' => $permId], 'Zugriff beantragt');
    }
    if ($sub === '/permissions' && $method === 'GET') {
        Response::success(['permissions' => $db->query(
            "SELECT p.*, ru.name AS requested_by_name, au.name AS approved_by_name
             FROM ai_tool_permissions p
             LEFT JOIN users ru ON ru.id = p.requested_by
             LEFT JOIN users au ON au.id = p.approved_by
             WHERE p.ai_employee_id = ? ORDER BY p.requested_at DESC", [$id]
        )]);
    }

    // Versionen
    if ($sub === '/versions' && $method === 'GET') {
        Response::success(['versions' => $svc->listVersions($id)]);
    }
    if ($sub === '/versions' && $method === 'POST') {
        $v = $svc->publishVersion($id, (string) ($input['change_summary'] ?? ''), $actor);
        Response::success(['version' => $v], 'Version gespeichert');
    }
    if (preg_match('#^/versions/(\d+)/restore$#', $sub, $mm) && $method === 'POST') {
        try { $svc->restoreVersion($id, (int) $mm[1], $actor); }
        catch (\Throwable $ex) { Response::error($ex->getMessage()); }
        Response::success($svc->get($id), 'Version wiederhergestellt');
    }

    // Audit-Log (Aktivität)
    if ($sub === '/audit-log' && $method === 'GET') {
        Response::success(['events' => $db->query(
            "SELECT a.*, u.name AS actor_name FROM permission_audit_log a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.target_type = 'ai_employee' AND a.target_key = ?
             ORDER BY a.occurred_at DESC LIMIT 200", [(string) $id]
        )]);
    }

    // Wizard + Läufe: in eigenen (flach benannten) Dateien — KEIN Ordner
    // "ki-mitarbeiter/", der die API-URL /api/v1/ki-mitarbeiter beschatten wuerde.
    if (strpos($sub, '/wizard') === 0 && is_file(API_PATH . '/v1/ki-mitarbeiter-wizard.php')) {
        $employeeId = $id; $wizardSub = $sub;
        require API_PATH . '/v1/ki-mitarbeiter-wizard.php';
        return;
    }
    if ((strpos($sub, '/runs') === 0 || strpos($sub, '/test-runs') === 0) && is_file(API_PATH . '/v1/ki-mitarbeiter-runs.php')) {
        $employeeId = $id; $runSub = $sub;
        require API_PATH . '/v1/ki-mitarbeiter-runs.php';
        return;
    }

    Response::error('Unbekannte Route: ' . $sub, 404);
}

// Läufe direkt: /ai-runs/{id}...
if (preg_match('#^/ai-runs/(\d+)#', $uri) && is_file(API_PATH . '/v1/ki-mitarbeiter-runs.php')) {
    require API_PATH . '/v1/ki-mitarbeiter-runs.php';
    return;
}

Response::error('Unbekannte Route', 404);
