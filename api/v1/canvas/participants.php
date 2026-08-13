<?php
/**
 * Canvas Participants API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/CanvasService.php';
$canvasService = new \Services\CanvasService($db);

$userId = Auth::id();
$canvasId = isset($_GET['canvas_id']) ? (int) $_GET['canvas_id'] : null;
$participantId = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if (!$canvasId) {
            Response::error('Canvas-ID erforderlich');
        }
        if (!$canvasService->canAccess($canvasId, $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }
        $participants = $canvasService->getParticipants($canvasId);
        Response::success($participants);
        break;

    case 'POST':
        if (!$canvasId) {
            Response::error('Canvas-ID erforderlich');
        }
        if (!$canvasService->canAccess($canvasId, $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }

        $targetUserId = (int) ($input['user_id'] ?? 0);
        $role = $input['canvas_role'] ?? 'entwickler';

        if (!$targetUserId) {
            Response::error('User-ID erforderlich');
        }
        if (!in_array($role, ['auftraggeber', 'entwickler'])) {
            Response::error('Ungueltige Rolle');
        }

        // Pruefen ob User existiert
        $user = $db->queryOne("SELECT id, name FROM users WHERE id = ?", [$targetUserId]);
        if (!$user) {
            Response::notFound('Benutzer nicht gefunden');
        }

        try {
            $id = $canvasService->addParticipant($canvasId, $targetUserId, $role);
            Response::success(['id' => $id, 'user_id' => $targetUserId, 'name' => $user['name'], 'canvas_role' => $role], 'Teilnehmer hinzugefuegt');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                Response::error('Benutzer ist bereits Teilnehmer');
            }
            throw $e;
        }
        break;

    case 'DELETE':
        if (!$participantId) {
            Response::error('Teilnehmer-ID erforderlich');
        }
        // Pruefen ob der aktuelle User Zugriff hat
        $participant = $db->queryOne("SELECT * FROM canvas_participants WHERE id = ?", [$participantId]);
        if (!$participant) {
            Response::notFound('Teilnehmer nicht gefunden');
        }
        if (!$canvasService->canAccess($participant['canvas_id'], $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }
        $canvasService->removeParticipant($participantId);
        Response::success(null, 'Teilnehmer entfernt');
        break;

    default:
        Response::error('Method not allowed', 405);
}
