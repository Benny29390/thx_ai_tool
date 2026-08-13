<?php
/**
 * Guidelines REST-API.
 *
 * GET    /guidelines                  → Liste, optional ?category=X
 * GET    /guidelines/{id}             → einzeln
 * POST   /guidelines                  → erstellen
 * PUT    /guidelines/{id}             → ändern
 * DELETE /guidelines/{id}             → löschen
 * PATCH  /guidelines/{id}/toggle      → aktiv ↔ inaktiv
 * POST   /guidelines/reorder          → {ids: [3,1,2]}
 *
 * Auth: Manager + Admin.
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();

require_once SERVICES_PATH . '/GuidelineService.php';
$service = new \Services\GuidelineService($db);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;

// Reorder
if ($action === 'reorder') {
    if ($method !== 'POST') Response::error('Method not allowed', 405);
    $ids = $input['ids'] ?? [];
    if (!is_array($ids)) Response::error('ids muss Array sein');
    $service->reorder($ids);
    Response::success(null, 'Reihenfolge gespeichert');
}

// Toggle
if ($action === 'toggle') {
    if (!$id) Response::error('ID erforderlich');
    if ($method !== 'PATCH' && $method !== 'POST') Response::error('Method not allowed', 405);
    $current = $service->get($id);
    if (!$current) Response::notFound('Guideline nicht gefunden');
    $newActive = !$current['is_active'];
    $service->toggle($id, $newActive);
    Response::success(['is_active' => $newActive ? 1 : 0]);
}

switch ($method) {
    case 'GET':
        if ($id) {
            $g = $service->get($id);
            if (!$g) Response::notFound('Guideline nicht gefunden');
            Response::success($g);
        }
        $category = $_GET['category'] ?? null;
        $onlyActive = !empty($_GET['active']);
        $items = $service->list($category, $onlyActive);

        // Counts pro Kategorie für UI-Tabs
        $counts = [];
        foreach (\Services\GuidelineService::CATEGORIES as $key => $label) {
            $counts[$key] = (int) $db->queryValue(
                "SELECT COUNT(*) FROM guidelines WHERE category = ?",
                [$key]
            );
        }

        Response::success([
            'items' => $items,
            'categories' => \Services\GuidelineService::CATEGORIES,
            'counts' => $counts,
        ]);

    case 'POST':
        try {
            $newId = $service->create($input, Auth::id());
            $created = $service->get($newId);
            Response::success($created, 'Guideline erstellt');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id) Response::error('ID erforderlich');
        try {
            $service->update($id, $input, Auth::id());
            Response::success($service->get($id), 'Aktualisiert');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage());
        } catch (\RuntimeException $e) {
            Response::notFound($e->getMessage());
        }
        break;

    case 'DELETE':
        if (!$id) Response::error('ID erforderlich');
        $service->delete($id);
        Response::success(null, 'Gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
