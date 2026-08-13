<?php
/**
 * GET  /admin/rules/{id}/scope         → { project_type_ids: [int,...] }  (leer = "alle")
 * POST /admin/rules/{id}/scope         → setzt: { project_type_ids: [int,...] }
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$ruleId = (int)($_GET['rule_id'] ?? 0);
if ($ruleId <= 0) Response::error('rule_id fehlt');

$db = Database::getInstance();
$rule = $db->queryOne("SELECT id FROM rules WHERE id = ?", [$ruleId]);
if (!$rule) Response::notFound();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ids = array_map('intval', array_column(
        $db->query("SELECT project_type_id FROM rule_project_types WHERE rule_id = ?", [$ruleId]) ?: [],
        'project_type_id'
    ));
    Response::success(['project_type_ids' => $ids]);
}

if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['project_type_ids'] ?? [])), fn($i) => $i > 0)));
    // Validieren
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valid = $db->query("SELECT id FROM project_types WHERE id IN ($placeholders) AND is_active = 1", $ids) ?: [];
        $ids = array_map(fn($r) => (int)$r['id'], $valid);
    }
    $db->beginTransaction();
    try {
        $db->delete('rule_project_types', 'rule_id = ?', [$ruleId]);
        foreach ($ids as $ptid) {
            $db->insert('rule_project_types', ['rule_id' => $ruleId, 'project_type_id' => $ptid]);
        }
        $db->commit();
        Response::success(['project_type_ids' => $ids]);
    } catch (\Throwable $e) {
        $db->rollBack();
        Response::error($e->getMessage(), 500);
    }
}

Response::error('Methode nicht unterstuetzt', 405);
