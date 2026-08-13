<?php
/**
 * Canvas Card References API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/CanvasService.php';
$canvasService = new \Services\CanvasService($db);

$userId = Auth::id();
$refId = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'POST':
        $sourceId = (int) ($input['source_card_id'] ?? 0);
        $targetId = (int) ($input['target_card_id'] ?? 0);

        if (!$sourceId || !$targetId) {
            Response::error('source_card_id und target_card_id erforderlich');
        }
        if ($sourceId === $targetId) {
            Response::error('Karte kann nicht auf sich selbst verweisen');
        }

        // Zugriff pruefen
        $sourceCard = $canvasService->getCard($sourceId);
        $targetCard = $canvasService->getCard($targetId);
        if (!$sourceCard || !$targetCard) {
            Response::notFound('Karte nicht gefunden');
        }
        if (!$canvasService->canAccess($sourceCard['canvas_id'], $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }

        try {
            $id = $canvasService->createReference([
                'source_card_id' => $sourceId,
                'target_card_id' => $targetId,
                'reference_type' => $input['reference_type'] ?? 'relates_to',
                'note' => $input['note'] ?? null,
            ], $userId);
            Response::success(['id' => $id], 'Referenz erstellt');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                Response::error('Referenz existiert bereits');
            }
            throw $e;
        }
        break;

    case 'DELETE':
        if (!$refId) {
            Response::error('Referenz-ID erforderlich');
        }
        $ref = $db->queryOne("SELECT r.*, c.canvas_id FROM canvas_card_references r JOIN canvas_cards c ON r.source_card_id = c.id WHERE r.id = ?", [$refId]);
        if (!$ref) {
            Response::notFound('Referenz nicht gefunden');
        }
        if (!$canvasService->canAccess($ref['canvas_id'], $userId) && !Auth::isAdmin()) {
            Response::forbidden('Kein Zugriff');
        }
        $canvasService->deleteReference($refId);
        Response::success(null, 'Referenz geloescht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
