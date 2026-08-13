<?php
/**
 * GET    /admin/project-types          → Liste aller Projekt-Arten + Counts (Kunden, Regeln)
 * POST   /admin/project-types          → Anlegen { slug?, name, color, icon }
 * PUT    /admin/project-types?id=X     → Update
 * DELETE /admin/project-types?id=X     → Löschen
 *
 * Projekt-Arten sind die Master-Liste der "Arten von Projekten" — wird von Kunden
 * und Regeln gemeinsam genutzt. Verknuepfungen ueber customer_project_types und
 * rule_project_types.
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    if ($id > 0) {
        $row = $db->queryOne("SELECT * FROM project_types WHERE id = ?", [$id]);
        if (!$row) Response::notFound();
        $row['customer_count'] = (int) $db->queryValue("SELECT COUNT(*) FROM customer_project_types WHERE project_type_id = ?", [$id]);
        $row['rule_count'] = (int) $db->queryValue("SELECT COUNT(*) FROM rule_project_types WHERE project_type_id = ?", [$id]);
        Response::success($row);
    } else {
        $rows = $db->query(
            "SELECT pt.*,
                    (SELECT COUNT(*) FROM customer_project_types WHERE project_type_id = pt.id) AS customer_count,
                    (SELECT COUNT(*) FROM rule_project_types WHERE project_type_id = pt.id) AS rule_count
             FROM project_types pt WHERE pt.is_active = 1 ORDER BY pt.sort_order, pt.name"
        ) ?: [];
        Response::success(['project_types' => $rows]);
    }
}

if ($method === 'POST') {
    if (!Auth::isAdmin()) Response::forbidden();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') Response::error('Name fehlt');
    $slug = trim((string)($in['slug'] ?? ''));
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', preg_replace('/[äöüß]/u', function ($m) {
            return ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'][$m[0]];
        }, mb_strtolower($name))));
        $slug = trim($slug, '-');
    }
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) Response::error('Slug ungültig (nur a-z, 0-9, -)');
    if ($db->queryValue("SELECT id FROM project_types WHERE slug = ?", [$slug])) Response::error('Slug bereits vergeben');
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'] ?? '') ? $in['color'] : '#9ca3af';
    $icon = trim((string)($in['icon'] ?? 'category')) ?: 'category';
    $maxSort = (int) $db->queryValue("SELECT MAX(sort_order) FROM project_types");
    $newId = $db->insert('project_types', [
        'slug' => $slug, 'name' => $name, 'color' => $color, 'icon' => $icon,
        'sort_order' => $maxSort + 10, 'is_active' => 1,
    ]);
    Response::success(['id' => $newId], 'Projekt-Art angelegt');
}

if ($method === 'PUT' && $id > 0) {
    if (!Auth::isAdmin()) Response::forbidden();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $upd = [];
    if (isset($in['name'])) $upd['name'] = trim((string)$in['name']);
    if (isset($in['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $in['color'])) $upd['color'] = $in['color'];
    if (isset($in['icon'])) $upd['icon'] = trim((string)$in['icon']) ?: 'category';
    if (isset($in['sort_order'])) $upd['sort_order'] = (int)$in['sort_order'];
    if (!empty($upd)) $db->update('project_types', $upd, 'id = ?', [$id]);
    Response::success(null, 'Aktualisiert');
}

if ($method === 'DELETE' && $id > 0) {
    if (!Auth::isAdmin()) Response::forbidden();
    $db->execute("UPDATE project_types SET is_active = 0 WHERE id = ?", [$id]);
    Response::success(null, 'Deaktiviert');
}

Response::error('Methode nicht unterstuetzt', 405);
