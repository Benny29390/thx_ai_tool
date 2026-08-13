<?php
/**
 * Canvas Projects API — CRUD fuer Canvas-Projekte
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/CanvasService.php';
$canvasService = new \Services\CanvasService($db);

$userId = Auth::id();
$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelnes Projekt mit Details
            $project = $canvasService->getProjectWithDetails($id);
            if (!$project) {
                Response::notFound('Canvas nicht gefunden');
            }
            if (!$canvasService->canAccess($id, $userId) && !Auth::isAdmin()) {
                Response::forbidden('Kein Zugriff');
            }
            Response::success($project);
        } else {
            // Liste eigener + geteilter Projekte
            $customerId = Auth::isAdmin() ? ($_GET['customer_id'] ?? null) : Auth::customerId();
            $includeArchived = !empty($_GET['include_archived']);
            $projects = $canvasService->listProjects($userId, $customerId ? (int) $customerId : null, $includeArchived);
            Response::success(['items' => $projects]);
        }
        break;

    case 'POST':
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            Response::error('Titel erforderlich');
        }
        $customerId = (int) ($input['customer_id'] ?? Auth::customerId() ?? 0) ?: null;

        $id = $canvasService->createProject([
            'customer_id' => $customerId,
            'title' => $title,
            'description' => $input['description'] ?? null,
        ], $userId);

        $project = $canvasService->getProjectWithDetails($id);
        Response::success($project, 'Canvas erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }
        if (!$canvasService->canAccess($id, $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }
        // Action-Shortcut: {action: 'archive'|'unarchive'}
        $action = $input['action'] ?? null;
        if ($action === 'archive') {
            $canvasService->archiveProject($id, $userId);
            Response::success(null, 'Canvas archiviert');
        } elseif ($action === 'unarchive') {
            $canvasService->unarchiveProject($id, $userId);
            Response::success(null, 'Canvas wiederhergestellt');
        }
        $canvasService->updateProject($id, $input, $userId);
        $project = $canvasService->getProjectWithDetails($id);
        Response::success($project, 'Canvas aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }
        if (!$canvasService->canAccess($id, $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }
        // Hard delete — entfernt Projekt inkl. Cards, Versionen, Chat-History
        $canvasService->deleteProject($id);
        Response::success(null, 'Canvas geloescht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
