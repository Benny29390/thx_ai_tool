<?php
/**
 * Author Profiles API (Admin only)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $author = $db->queryOne(
                "SELECT ap.*, c.name as customer_name
                 FROM author_profiles ap
                 LEFT JOIN customers c ON ap.customer_id = c.id
                 WHERE ap.id = ?",
                [$id]
            );
            if (!$author) {
                Response::notFound('Autorenprofil nicht gefunden');
            }
            Response::success($author);
        } else {
            $authors = $db->query(
                "SELECT ap.*, c.name as customer_name
                 FROM author_profiles ap
                 LEFT JOIN customers c ON ap.customer_id = c.id
                 ORDER BY ap.name"
            );
            Response::success($authors);
        }
        break;

    case 'POST':
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            Response::error('Name erforderlich');
        }

        $authorId = $db->insert('author_profiles', [
            'name' => $name,
            'customer_id' => !empty($input['customer_id']) ? (int) $input['customer_id'] : null,
            'writing_style' => trim($input['writing_style'] ?? '') ?: null,
            'expertise' => trim($input['expertise'] ?? '') ?: null,
            'tone' => trim($input['tone'] ?? '') ?: null,
            'perspective' => trim($input['perspective'] ?? '') ?: null,
            'example_text' => trim($input['example_text'] ?? '') ?: null,
            'notes' => trim($input['notes'] ?? '') ?: null,
            'is_active' => isset($input['is_active']) ? (int) $input['is_active'] : 1
        ]);

        Response::success(['id' => $authorId], 'Autorenprofil erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $existing = $db->queryOne("SELECT id FROM author_profiles WHERE id = ?", [$id]);
        if (!$existing) {
            Response::notFound('Autorenprofil nicht gefunden');
        }

        $updates = [];
        if (isset($input['name'])) $updates['name'] = trim($input['name']);
        if (isset($input['customer_id'])) $updates['customer_id'] = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
        if (isset($input['writing_style'])) $updates['writing_style'] = trim($input['writing_style']) ?: null;
        if (isset($input['expertise'])) $updates['expertise'] = trim($input['expertise']) ?: null;
        if (isset($input['tone'])) $updates['tone'] = trim($input['tone']) ?: null;
        if (isset($input['perspective'])) $updates['perspective'] = trim($input['perspective']) ?: null;
        if (isset($input['example_text'])) $updates['example_text'] = trim($input['example_text']) ?: null;
        if (isset($input['notes'])) $updates['notes'] = trim($input['notes']) ?: null;
        if (isset($input['is_active'])) $updates['is_active'] = (int) $input['is_active'];

        if (!empty($updates)) {
            $db->update('author_profiles', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Autorenprofil aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $existing = $db->queryOne("SELECT name FROM author_profiles WHERE id = ?", [$id]);
        if (!$existing) {
            Response::notFound('Autorenprofil nicht gefunden');
        }

        // Clean up context_items references
        $db->execute(
            "UPDATE context_items SET reference_id = NULL WHERE item_type = 'author' AND reference_id = ?",
            [$id]
        );

        $db->delete('author_profiles', 'id = ?', [$id]);
        Response::success(null, 'Autorenprofil gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
