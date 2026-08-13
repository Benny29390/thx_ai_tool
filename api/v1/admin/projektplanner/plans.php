<?php
/**
 * Projektplanner — Pläne API
 *
 * GET    /admin/projektplanner/plans                       Liste (eigene + freigegebene)
 * POST   /admin/projektplanner/plans                       Anlegen (jeder Manager/Admin)
 * GET    /admin/projektplanner/plans/{id}                  Detail mit Rows + Feedback (read)
 * PUT    /admin/projektplanner/plans/{id}                  Felder aktualisieren (write)
 * DELETE /admin/projektplanner/plans/{id}                  Soft-Delete (owner)
 * POST   /admin/projektplanner/plans/{id}/duplicate        Duplizieren (read genügt)
 * POST   /admin/projektplanner/plans/{id}/share            Share-Hash (re)generieren (write)
 * GET    /admin/projektplanner/plans/{id}/budget-soll      Budget-Soll (read)
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\ProjektplannerService;
use Services\PpBudgetService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/ProjektplannerService.php';
require_once SERVICES_PATH . '/PpBudgetService.php';
require __DIR__ . '/_pp_perm.php';

$svc = new ProjektplannerService(Database::getInstance());
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$planId = (int) ($_GET['plan_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($planId > 0 && $action === 'duplicate' && $method === 'POST') {
    pp_require($planId, 'read');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $newId = $svc->duplicatePlan($planId, $userId, [
            'title'       => $body['title']       ?? null,
            'period_from' => $body['period_from'] ?? null,
            'period_to'   => $body['period_to']   ?? null,
            'shift_dates' => !empty($body['shift_dates']),
            'reset_ist'   => array_key_exists('reset_ist', $body)  ? !empty($body['reset_ist'])  : true,
            'reset_done'  => array_key_exists('reset_done', $body) ? !empty($body['reset_done']) : true,
        ]);
        Response::success(['id' => $newId], 'Plan dupliziert');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($planId > 0 && $action === 'ai-enrich' && $method === 'POST') {
    pp_require($planId, 'write'); // veraendert Zeilen
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    require_once SERVICES_PATH . '/PpPlanEnrichService.php';
    try {
        $enrich = new \Services\PpPlanEnrichService(Database::getInstance());
        $result = $enrich->enrichDuplicatedPlan($planId, [
            'briefing'   => trim((string) ($body['briefing'] ?? '')),
            'link_asana' => array_key_exists('link_asana', $body) ? !empty($body['link_asana']) : true,
            'user_id'    => $userId,
        ]);
        Response::success($result, 'KI-Anreicherung abgeschlossen');
    } catch (\Throwable $e) { Response::error('Anreicherung fehlgeschlagen: ' . $e->getMessage(), 500); }
}

if ($planId > 0 && $action === 'share' && $method === 'POST') {
    pp_require($planId, 'write');
    $hash = $svc->generateShareHash($planId);
    Response::success(['share_hash' => $hash], 'Share-Link erzeugt');
}

/* Multi-Plan-Share — gemeinsame Übersicht über mehrere Pläne mit Filter-Kontext */
if ($planId === 0 && (($_GET['action'] ?? '') === 'multi-share')) {
    if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $planIds = $body['plan_ids'] ?? [];
        $filters = $body['filters'] ?? [];
        $title   = isset($body['title']) ? trim((string) $body['title']) : null;
        $options = [
            'password'    => $body['password']    ?? null,
            'expires_at'  => $body['expires_at']  ?? null,
            'is_snapshot' => !empty($body['is_snapshot']),
        ];
        try {
            $hash = $svc->createMultiShare(
                is_array($planIds) ? $planIds : [],
                is_array($filters) ? $filters : [],
                $title, Auth::id(), $options
            );
            Response::success(['share_hash' => $hash, 'url' => '/projektplan-uebersicht/' . $hash], 'Share-Link erzeugt');
        } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
    }
    if ($method === 'GET') {
        $list = $svc->listMultiShares(Auth::id(), Auth::isAdmin());
        Response::success(['shares' => $list]);
    }
    Response::error('Methode nicht unterstützt', 405);
}

/* Multi-Share Detail-Operationen: PATCH (Optionen ändern) + DELETE (löschen) */
if ($planId === 0 && (($_GET['action'] ?? '') === 'multi-share-detail')) {
    if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
    $shareId = (int) ($_GET['share_id'] ?? 0);
    if ($shareId <= 0) Response::error('share_id fehlt', 400);
    try {
        if ($method === 'PATCH' || $method === 'PUT') {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $svc->updateMultiShare($shareId, Auth::id(), $body, Auth::isAdmin());
            Response::success(['id' => $shareId], 'Aktualisiert');
        }
        if ($method === 'DELETE') {
            $svc->deleteMultiShare($shareId, Auth::id(), Auth::isAdmin());
            Response::success(['id' => $shareId], 'Gelöscht');
        }
    } catch (\Throwable $e) { Response::error($e->getMessage(), 400); }
    Response::error('Methode nicht unterstützt', 405);
}

if ($planId > 0 && $action === 'budget-soll' && $method === 'GET') {
    pp_require($planId, 'read');
    $budgetSvc = new PpBudgetService(Database::getInstance());
    Response::success($budgetSvc->getPlanBudgetSoll($planId));
}

if ($planId > 0 && $action === 'abrechnung-einzel' && $method === 'GET') {
    pp_require($planId, 'read');
    $budgetSvc = new PpBudgetService(Database::getInstance());
    Response::success($budgetSvc->getEinzelprojektAbrechnung($planId));
}

if ($planId > 0 && $action === 'restore' && $method === 'POST') {
    if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
    $svc->restorePlan($planId);
    Response::success(['id' => $planId], 'Plan wiederhergestellt');
}

if ($planId > 0 && $action === 'hard' && $method === 'DELETE') {
    if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
    pp_require($planId, 'write');
    try {
        $svc->hardDeletePlan($planId);
        Response::success(['id' => $planId], 'Plan endgültig gelöscht');
    } catch (\Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
}

if ($planId > 0 && $action === 'sync-knowledge' && $method === 'POST') {
    if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
    pp_require($planId, 'read');
    require_once SERVICES_PATH . '/PpKnowledgeSyncService.php';
    try {
        $sync = \Services\PpKnowledgeSyncService::build(Database::getInstance());
        $r = $sync->syncPlan($planId, (int) (Auth::user()['id'] ?? 0));
        Response::success($r, 'Sync ausgeführt: ' . $r['action']);
    } catch (\Throwable $e) {
        Response::error('Sync fehlgeschlagen: ' . $e->getMessage(), 500);
    }
}

if ($planId > 0 && $action === 'share-password' && $method === 'POST') {
    pp_require($planId, 'write');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $pw = trim((string) ($payload['password'] ?? ''));
    $db = Database::getInstance();
    if ($pw === '') {
        $db->update('pp_plans', ['share_password' => null], 'id = ?', [$planId]);
        Response::success(['ok' => true], 'Passwort entfernt');
    }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $db->update('pp_plans', ['share_password' => $hash], 'id = ?', [$planId]);
    Response::success(['ok' => true], 'Passwort gesetzt');
}

if ($planId > 0 && $method === 'GET') {
    $perm = pp_require($planId, 'read');
    $plan = $svc->getPlanWithRows($planId);
    if (!$plan) Response::notFound('Plan nicht gefunden');
    $plan['_permission'] = $perm; // damit Frontend pp-perm-* Klassen setzen kann
    Response::success($plan);
}

if ($planId > 0 && $method === 'PUT') {
    $perm = pp_require($planId, 'edit');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    // edit-Permission darf nur Plan-Header-Felder, die nicht-strukturell sind, NICHT ändern.
    // Plan-Update ist 'write'-pflichtig; 'edit' bekommt 403.
    if ($perm === 'edit') Response::forbidden('Plan-Header ist nur mit Vollzugriff bearbeitbar');
    try {
        $svc->updatePlan($planId, $payload);
        Response::success(['id' => $planId], 'Gespeichert');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

if ($planId > 0 && $method === 'DELETE') {
    // Nur Owner darf löschen (oder Admin/Manager als Pseudo-Owner)
    $perm = pp_require($planId, 'read');
    if ($perm !== 'owner') Response::forbidden('Nur der Plan-Owner darf löschen');
    $svc->softDeletePlan($planId);
    Response::success(['id' => $planId], 'Plan gelöscht');
}

if ($method === 'GET') {
    $filter = [];
    if (!empty($_GET['customer_id'])) $filter['customer_id'] = (int) $_GET['customer_id'];
    if (!empty($_GET['status'])) $filter['status'] = $_GET['status'];
    if (isset($_GET['state'])) $filter['state'] = (int) $_GET['state'];
    $plans = $svc->getPlans($filter);
    // Mit shared_permission aus getSharedPlans anreichern, falls nicht-Manager
    if (!(Auth::isAdmin() || Auth::isManager())) {
        $shared = $svc->getSharedPlans($userId);
        $sharedById = [];
        foreach ($shared as $sp) $sharedById[$sp['id']] = $sp['shared_permission'] ?? null;
        foreach ($plans as &$p) {
            $p['_permission'] = ((int)$p['created_by'] === $userId) ? 'owner' : ($sharedById[$p['id']] ?? null);
        }
    } else {
        foreach ($plans as &$p) {
            $p['_permission'] = ((int)$p['created_by'] === $userId) ? 'owner' : 'owner';
        }
    }
    Response::success(['plans' => $plans]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $newId = $svc->createPlan($payload, $userId);
        Response::success(['id' => $newId], 'Plan erstellt');
    } catch (\Throwable $e) { Response::error($e->getMessage()); }
}

Response::error('Methode nicht unterstützt', 405);
