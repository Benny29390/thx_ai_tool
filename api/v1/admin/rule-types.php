<?php
/**
 * Rule Types API (Admin only)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $type = $db->queryOne("SELECT * FROM rule_types WHERE id = ?", [$id]);
            if (!$type) {
                Response::notFound('Regeltyp nicht gefunden');
            }
            Response::success($type);
        } else {
            $types = $db->query("SELECT * FROM rule_types ORDER BY sort_order, name");
            Response::success($types);
        }
        break;

    case 'POST':
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $description = trim($input['description'] ?? '');
        $color = trim($input['color'] ?? '#6b7280');
        $icon = trim($input['icon'] ?? 'rule');

        if (empty($name) || empty($slug)) {
            Response::error('Name und Slug erforderlich');
        }

        // Slug validieren
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Response::error('Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten');
        }

        // Slug eindeutig?
        $existing = $db->queryOne("SELECT id FROM rule_types WHERE slug = ?", [$slug]);
        if ($existing) {
            Response::error('Slug bereits vergeben');
        }

        // Farbe validieren
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        // Höchste sort_order ermitteln
        $maxOrder = $db->queryValue("SELECT MAX(sort_order) FROM rule_types") ?? 0;

        $typeId = $db->insert('rule_types', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $maxOrder + 1,
            'is_active' => 1,
            'is_system' => 0
        ]);

        Response::success(['id' => $typeId], 'Regeltyp erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $type = $db->queryOne("SELECT * FROM rule_types WHERE id = ?", [$id]);
        if (!$type) {
            Response::notFound('Regeltyp nicht gefunden');
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
            $existing = $db->queryOne("SELECT id FROM rule_types WHERE slug = ? AND id != ?", [$slug, $id]);
            if ($existing) {
                Response::error('Slug bereits vergeben');
            }
            // System-Typen: Slug nicht ändern
            if ($type['is_system'] && $slug !== $type['slug']) {
                Response::error('Slug von System-Typen kann nicht geändert werden');
            }
            $updates['slug'] = $slug;
        }

        if (!empty($updates)) {
            $db->update('rule_types', $updates, 'id = ?', [$id]);
        }

        Response::success(null, 'Regeltyp aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $type = $db->queryOne("SELECT * FROM rule_types WHERE id = ?", [$id]);
        if (!$type) {
            Response::notFound('Regeltyp nicht gefunden');
        }

        // System-Typen nicht löschbar
        if ($type['is_system']) {
            Response::error('System-Typen können nicht gelöscht werden');
        }

        // Prüfen ob Regeln diesen Typ verwenden
        $rulesCount = $db->queryValue("SELECT COUNT(*) FROM rules WHERE rule_type_id = ?", [$id]);
        if ($rulesCount > 0) {
            Response::error("Dieser Typ wird von $rulesCount Regeln verwendet und kann nicht gelöscht werden");
        }

        $db->delete('rule_types', 'id = ?', [$id]);
        Response::success(null, 'Regeltyp gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
