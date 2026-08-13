<?php
/**
 * Canvas Cards API — CRUD fuer Canvas-Karten
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/CanvasService.php';
$canvasService = new \Services\CanvasService($db);

$userId = Auth::id();
$cardId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$canvasId = isset($_GET['canvas_id']) ? (int) $_GET['canvas_id'] : null;
$subAction = $_GET['sub_action'] ?? null;

// Zugriffspruefung
if ($canvasId) {
    if (!$canvasService->canAccess($canvasId, $userId) && !Auth::isAdmin()) {
        Response::forbidden('Kein Zugriff');
    }
}
if ($cardId) {
    $card = $canvasService->getCard($cardId);
    if (!$card) {
        Response::notFound('Karte nicht gefunden');
    }
    if (!$canvasService->canAccess($card['canvas_id'], $userId) && !Auth::isAdmin()) {
        Response::forbidden('Kein Zugriff');
    }
}

switch ($method) {
    case 'POST':
        // Karte erstellen (POST /canvas/projects/{id}/cards)
        if (!$canvasId) {
            Response::error('Canvas-ID erforderlich');
        }
        $field = $input['field'] ?? '';
        if (!in_array($field, \Services\CanvasService::FIELDS)) {
            Response::error('Ungueltiges Feld: ' . $field);
        }
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            Response::error('Titel erforderlich');
        }

        $newId = $canvasService->createCard([
            'canvas_id' => $canvasId,
            'field' => $field,
            'title' => $title,
            'content' => $input['content'] ?? null,
            'status' => $input['status'] ?? 'entwurf',
        ], $userId);

        $newCard = $canvasService->getCard($newId);
        Response::success($newCard, 'Karte erstellt');
        break;

    case 'PUT':
        if (!$cardId) {
            Response::error('Karten-ID erforderlich');
        }

        if ($subAction === '/status') {
            // Status aendern (PUT /canvas/cards/{id}/status)
            $status = $input['status'] ?? '';
            if (!in_array($status, ['entwurf', 'in_arbeit', 'vollstaendig'])) {
                Response::error('Ungueltiger Status');
            }
            $canvasService->updateCardStatus($cardId, $status, $userId);
        } else {
            // Karte bearbeiten (PUT /canvas/cards/{id})
            $canvasService->updateCard($cardId, $input, $userId);
        }

        $updated = $canvasService->getCard($cardId);
        // Completeness mit zurueckgeben
        $completeness = $canvasService->calculateCompleteness($updated['canvas_id']);
        Response::success(['card' => $updated, 'completeness' => $completeness], 'Karte aktualisiert');
        break;

    case 'DELETE':
        if (!$cardId) {
            Response::error('Karten-ID erforderlich');
        }
        $canvasIdForCompleteness = $card['canvas_id'];
        $canvasService->deleteCard($cardId);
        $completeness = $canvasService->calculateCompleteness($canvasIdForCompleteness);
        Response::success(['completeness' => $completeness], 'Karte geloescht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
