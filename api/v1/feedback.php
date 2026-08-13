<?php
/**
 * Feedback API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

switch ($method) {
    case 'POST':
        $sectionId = $input['section_id'] ?? null;
        $rating = $input['rating'] ?? null;
        $comment = trim($input['comment'] ?? '') ?: null;
        $improvementRequest = trim($input['improvement_request'] ?? '') ?: null;

        // Skeleton-IDs ablehnen (z.B. 'skeleton-1')
        if (!$sectionId || !is_numeric($sectionId) || (int)$sectionId <= 0) {
            Response::error('Gültige Section-ID erforderlich');
        }
        $sectionId = (int)$sectionId;

        if (!$rating) {
            Response::error('Rating erforderlich');
        }

        if (!in_array($rating, ['positive', 'negative', 'neutral'])) {
            Response::error('Ungültiges Rating');
        }

        // Section prüfen
        $section = $db->queryOne(
            "SELECT asec.*, p.customer_id
             FROM article_sections asec
             JOIN article_versions av ON asec.article_version_id = av.id
             JOIN projects p ON av.project_id = p.id
             WHERE asec.id = ?",
            [$sectionId]
        );

        if (!$section || (!Auth::isAdmin() && $section['customer_id'] != Auth::customerId())) {
            Response::forbidden();
        }

        $feedbackId = $db->insert('section_feedback', [
            'section_id' => $sectionId,
            'user_id' => Auth::id(),
            'rating' => $rating,
            'comment' => $comment,
            'improvement_request' => $improvementRequest
        ]);

        Response::success(['id' => $feedbackId], 'Feedback gespeichert');
        break;

    case 'GET':
        $sectionId = $_GET['section_id'] ?? null;

        if ($sectionId) {
            $feedback = $db->query(
                "SELECT sf.*, u.name as user_name
                 FROM section_feedback sf
                 JOIN users u ON sf.user_id = u.id
                 WHERE sf.section_id = ?
                 ORDER BY sf.created_at DESC",
                [$sectionId]
            );
            Response::success($feedback);
        } else {
            Response::error('Section-ID erforderlich');
        }
        break;

    default:
        Response::error('Method not allowed', 405);
}
