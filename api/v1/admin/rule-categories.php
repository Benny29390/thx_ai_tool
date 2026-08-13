<?php
/**
 * Rule Categories API (Admin only)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $category = $db->queryOne("SELECT * FROM rule_categories WHERE id = ?", [$id]);
            if (!$category) {
                Response::notFound('Kategorie nicht gefunden');
            }
            // Anzahl Regeln in Kategorie
            $category['rules_count'] = $db->queryValue(
                "SELECT COUNT(*) FROM rules WHERE category_id = ?",
                [$id]
            );
            Response::success($category);
        } else {
            $categories = $db->query(
                "SELECT rc.*, COUNT(r.id) as rules_count
                 FROM rule_categories rc
                 LEFT JOIN rules r ON r.category_id = rc.id
                 GROUP BY rc.id
                 ORDER BY rc.sort_order, rc.name"
            );
            Response::success($categories);
        }
        break;

    case 'POST':
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $description = trim($input['description'] ?? '');
        $color = trim($input['color'] ?? '#6b7280');
        $icon = trim($input['icon'] ?? 'folder');

        if (empty($name) || empty($slug)) {
            Response::error('Name und Slug erforderlich');
        }

        // Slug validieren
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Response::error('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
        }

        // Slug eindeutig?
        $existing = $db->queryOne("SELECT id FROM rule_categories WHERE slug = ?", [$slug]);
        if ($existing) {
            Response::error('Slug bereits vergeben');
        }

        // Farbe validieren
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        // Höchste sort_order ermitteln
        $maxOrder = $db->queryValue("SELECT MAX(sort_order) FROM rule_categories") ?? 0;

        $categoryId = $db->insert('rule_categories', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $maxOrder + 1,
            'is_active' => 1
        ]);

        Response::success(['id' => $categoryId], 'Kategorie erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $category = $db->queryOne("SELECT * FROM rule_categories WHERE id = ?", [$id]);
        if (!$category) {
            Response::notFound('Kategorie nicht gefunden');
        }

        $updates = [];
        if (isset($input['name'])) $updates['name'] = trim($input['name']);
        if (isset($input['description'])) $updates['description'] = trim($input['description']) ?: null;
        if (isset($input['color'])) {
            $color = trim($input['color']);
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $updates['color'] = $color;
            }
        }
        if (isset($input['icon'])) $updates['icon'] = trim($input['icon']);
        if (isset($input['is_active'])) $updates['is_active'] = (int) $input['is_active'];

        if (isset($input['slug'])) {
            $slug = trim($input['slug']);
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                Response::error('Ungültiger Slug');
            }
            $existing = $db->queryOne("SELECT id FROM rule_categories WHERE slug = ? AND id != ?", [$slug, $id]);
            if ($existing) {
                Response::error('Slug bereits vergeben');
            }
            $updates['slug'] = $slug;
        }

        if (!empty($updates)) {
            $db->update('rule_categories', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Kategorie aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $category = $db->queryOne("SELECT * FROM rule_categories WHERE id = ?", [$id]);
        if (!$category) {
            Response::notFound('Kategorie nicht gefunden');
        }

        // Prüfen ob Regeln diese Kategorie verwenden
        $rulesCount = $db->queryValue("SELECT COUNT(*) FROM rules WHERE category_id = ?", [$id]);
        if ($rulesCount > 0) {
            // Regeln auf NULL setzen (aus Kategorie entfernen)
            $db->execute("UPDATE rules SET category_id = NULL WHERE category_id = ?", [$id]);
        }

        $db->delete('rule_categories', 'id = ?', [$id]);
        Response::success(null, 'Kategorie gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
