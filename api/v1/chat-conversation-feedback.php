<?php
/**
 * Chat Conversation Feedback API — Bewertung pro Konversation
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$conversationId = (int) ($_GET['id'] ?? 0);
$userId = Auth::id();

// Conversation laden
$conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$conversationId]);
if (!$conv) {
    Response::notFound('Konversation nicht gefunden');
}

// Zugriffspruefung: Owner oder Share
$hasAccess = $conv['user_id'] === $userId;
if (!$hasAccess) {
    $share = $db->queryOne(
        "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
        [$userId, $conversationId]
    );
    if ($share) {
        $hasAccess = true;
    } elseif ($conv['project_id']) {
        $projShare = $db->queryOne(
            "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ?",
            [$userId, $conv['project_id']]
        );
        if ($projShare) $hasAccess = true;
    }
}
if (!$hasAccess) {
    Response::forbidden('Kein Zugriff');
}

// GET — Feedback laden
if ($method === 'GET') {
    $feedback = $db->queryOne(
        "SELECT id, rating, comment, created_at FROM chat_conversation_feedback WHERE conversation_id = ? AND user_id = ?",
        [$conversationId, $userId]
    );
    Response::success($feedback);
}

// POST — Feedback erstellen/aktualisieren
if ($method === 'POST') {
    $rating = $input['rating'] ?? '';
    if (!in_array($rating, ['positive', 'negative'])) {
        Response::error('Bewertung muss positive oder negative sein');
    }
    $comment = trim($input['comment'] ?? '') ?: null;

    $existing = $db->queryOne(
        "SELECT id FROM chat_conversation_feedback WHERE conversation_id = ? AND user_id = ?",
        [$conversationId, $userId]
    );

    if ($existing) {
        $db->update('chat_conversation_feedback', [
            'rating' => $rating,
            'comment' => $comment,
        ], 'id = ?', [$existing['id']]);
        $feedbackId = $existing['id'];
    } else {
        $feedbackId = $db->insert('chat_conversation_feedback', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    // Bei negativem Feedback: Verwendete Artefakte mit Review-Event markieren
    if ($rating === 'negative') {
        try {
            require_once SERVICES_PATH . '/ArtifactEventService.php';
            $eventSvc = new \Services\ArtifactEventService($db);

            // Alle artifacts_used aus Messages dieser Konversation sammeln
            $messages = $db->query(
                "SELECT artifacts_used FROM chat_conversation_messages
                 WHERE conversation_id = ? AND role = 'assistant' AND artifacts_used IS NOT NULL",
                [$conversationId]
            );
            $affectedIds = [];
            foreach ($messages as $msg) {
                $artifacts = json_decode($msg['artifacts_used'], true) ?? [];
                foreach ($artifacts as $a) {
                    $affectedIds[(int)$a['id']] = $a['name'] ?? 'Unbekannt';
                }
            }
            foreach ($affectedIds as $artId => $artName) {
                $eventSvc->createEvent($artId, 'review_needed',
                    "Negatives Chat-Feedback — Artefakt \"{$artName}\" war im Kontext",
                    ['conversation_id' => $conversationId, 'comment' => $comment],
                    $userId
                );
            }
        } catch (\Exception $e) {
            error_log("Feedback artifact event failed: " . $e->getMessage());
        }
    }

    $feedback = $db->queryOne(
        "SELECT id, rating, comment, created_at FROM chat_conversation_feedback WHERE id = ?",
        [$feedbackId]
    );
    Response::success($feedback, 'Feedback gespeichert');
}

// DELETE — Feedback loeschen
if ($method === 'DELETE') {
    $db->execute(
        "DELETE FROM chat_conversation_feedback WHERE conversation_id = ? AND user_id = ?",
        [$conversationId, $userId]
    );
    Response::success(null, 'Feedback geloescht');
}

Response::error('Method not allowed', 405);
