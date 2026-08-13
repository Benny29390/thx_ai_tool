<?php
/**
 * Styles API (Admin only)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $style = $db->queryOne("SELECT * FROM styles WHERE id = ?", [$id]);
            if (!$style) {
                Response::notFound('Stil nicht gefunden');
            }
            Response::success($style);
        } else {
            $styles = $db->query("SELECT * FROM styles ORDER BY sort_order, name");
            Response::success($styles);
        }
        break;

    case 'POST':
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $description = trim($input['description'] ?? '');
        $promptAddition = trim($input['prompt_addition'] ?? '');

        if (empty($name) || empty($slug) || empty($promptAddition)) {
            Response::error('Name, Slug und Stil-Anweisung erforderlich');
        }

        // Slug validieren
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Response::error('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
        }

        // Slug eindeutig?
        $existing = $db->queryOne("SELECT id FROM styles WHERE slug = ?", [$slug]);
        if ($existing) {
            Response::error('Slug bereits vergeben');
        }

        // Höchste sort_order ermitteln
        $maxOrder = $db->queryValue("SELECT MAX(sort_order) FROM styles") ?? 0;

        $styleId = $db->insert('styles', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'prompt_addition' => $promptAddition,
            'sort_order' => $maxOrder + 1,
            'is_active' => 1
        ]);

        Response::success(['id' => $styleId], 'Stil erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $updates = [];
        if (isset($input['name'])) $updates['name'] = trim($input['name']);
        if (isset($input['description'])) $updates['description'] = trim($input['description']);
        if (isset($input['prompt_addition'])) $updates['prompt_addition'] = trim($input['prompt_addition']);
        if (isset($input['is_active'])) $updates['is_active'] = (int) $input['is_active'];

        if (isset($input['slug'])) {
            $slug = trim($input['slug']);
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                Response::error('Ungültiger Slug');
            }
            $existing = $db->queryOne("SELECT id FROM styles WHERE slug = ? AND id != ?", [$slug, $id]);
            if ($existing) {
                Response::error('Slug bereits vergeben');
            }
            $updates['slug'] = $slug;
        }

        if (!empty($updates)) {
            $db->update('styles', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Stil aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $db->delete('styles', 'id = ?', [$id]);
        Response::success(null, 'Stil gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
