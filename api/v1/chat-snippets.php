<?php
/**
 * Chat Snippets API — Textbausteine CRUD
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$userId = Auth::id();
$snippetId = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        $search = $_GET['search'] ?? '';
        if (!empty($search)) {
            $like = '%' . $search . '%';
            $snippets = $db->query(
                "SELECT * FROM chat_snippets WHERE user_id = ? AND (title LIKE ? OR content LIKE ?) ORDER BY sort_order, title",
                [$userId, $like, $like]
            );
        } else {
            $snippets = $db->query(
                "SELECT * FROM chat_snippets WHERE user_id = ? ORDER BY sort_order, title",
                [$userId]
            );
        }
        Response::success($snippets);
        break;

    case 'POST':
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        if (empty($title) || empty($content)) {
            Response::error('Titel und Inhalt erforderlich');
        }
        $id = $db->insert('chat_snippets', [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);
        $snippet = $db->queryOne("SELECT * FROM chat_snippets WHERE id = ?", [$id]);
        Response::success($snippet, 'Textbaustein erstellt');
        break;

    case 'PUT':
        if (!$snippetId) Response::error('ID erforderlich');
        $snippet = $db->queryOne("SELECT * FROM chat_snippets WHERE id = ? AND user_id = ?", [$snippetId, $userId]);
        if (!$snippet) Response::notFound('Textbaustein nicht gefunden');

        $update = [];
        if (isset($input['title'])) $update['title'] = trim($input['title']);
        if (isset($input['content'])) $update['content'] = trim($input['content']);
        if (!empty($update)) {
            $db->update('chat_snippets', $update, 'id = ?', [$snippetId]);
        }
        $snippet = $db->queryOne("SELECT * FROM chat_snippets WHERE id = ?", [$snippetId]);
        Response::success($snippet, 'Textbaustein aktualisiert');
        break;

    case 'DELETE':
        if (!$snippetId) Response::error('ID erforderlich');
        $snippet = $db->queryOne("SELECT * FROM chat_snippets WHERE id = ? AND user_id = ?", [$snippetId, $userId]);
        if (!$snippet) Response::notFound('Textbaustein nicht gefunden');
        $db->delete('chat_snippets', 'id = ?', [$snippetId]);
        Response::success(null, 'Textbaustein geloescht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
