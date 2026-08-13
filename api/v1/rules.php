<?php
/**
 * Rules API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;
$customerId = Auth::isAdmin() ? ($input['customer_id'] ?? $_GET['customer_id'] ?? null) : Auth::customerId();

// Service laden
require_once SERVICES_PATH . '/RuleEngine.php';
$ruleEngine = new \Services\RuleEngine($db);

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelne Regel mit Typ- und Kategorie-Infos
            $rule = $db->queryOne(
                "SELECT r.*, rt.name as type_name, rt.slug as type_slug, rt.color as type_color,
                        rc.name as category_name, rc.slug as category_slug, rc.color as category_color
                 FROM rules r
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 LEFT JOIN rule_categories rc ON r.category_id = rc.id
                 WHERE r.id = ?",
                [$id]
            );

            if (!$rule) {
                Response::notFound('Regel nicht gefunden');
            }

            // Zugriff prüfen (globale Regeln oder eigene)
            if (!Auth::isAdmin() && $rule['customer_id'] && $rule['customer_id'] != Auth::customerId()) {
                Response::forbidden();
            }

            Response::success($rule);

        } else {
            // Liste
            $where = ['1=1'];
            $params = [];

            if ($customerId) {
                $where[] = '(r.customer_id = ? OR r.customer_id IS NULL)';
                $params[] = $customerId;
            }

            // ?scope=global → nur globale Regeln (customer_id IS NULL)
            // ?scope=<int>  → nur dieser Kunde (customer_id = X)
            // ?scope=all    → alle, kein Filter (Admin only)
            if (isset($_GET['scope'])) {
                $scope = $_GET['scope'];
                if ($scope === 'global') {
                    $where[] = 'r.customer_id IS NULL';
                } elseif ($scope === 'all') {
                    // kein zusaetzlicher Filter
                } elseif (ctype_digit((string)$scope)) {
                    $where[] = 'r.customer_id = ?';
                    $params[] = (int)$scope;
                }
            }

            // Filter nach rule_type (Slug) oder rule_type_id
            if (isset($_GET['type'])) {
                $where[] = '(r.rule_type = ? OR rt.slug = ?)';
                $params[] = $_GET['type'];
                $params[] = $_GET['type'];
            }

            if (isset($_GET['type_id'])) {
                $where[] = 'r.rule_type_id = ?';
                $params[] = (int) $_GET['type_id'];
            }

            // Filter nach Kategorie
            if (isset($_GET['category_id'])) {
                if ($_GET['category_id'] === 'null' || $_GET['category_id'] === '') {
                    $where[] = 'r.category_id IS NULL';
                } else {
                    $where[] = 'r.category_id = ?';
                    $params[] = (int) $_GET['category_id'];
                }
            }

            if (isset($_GET['active']) && $_GET['active'] !== 'all') {
                $where[] = 'r.is_active = ?';
                $params[] = (int) $_GET['active'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $rules = $db->query(
                "SELECT r.*, c.name as customer_name,
                        rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                        rc.name as category_name, rc.slug as category_slug, rc.color as category_color, rc.icon as category_icon
                 FROM rules r
                 LEFT JOIN customers c ON r.customer_id = c.id
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 LEFT JOIN rule_categories rc ON r.category_id = rc.id
                 $whereClause
                 ORDER BY r.priority DESC, rt.sort_order, r.name",
                $params
            );

            // Geltungsbereich (Projekt-Arten) pro Regel anreichern — eine Query, dann mappen
            if (!empty($rules)) {
                $ruleIds = array_column($rules, 'id');
                $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
                $scopeRows = $db->query(
                    "SELECT rpt.rule_id, rpt.project_type_id, pt.slug, pt.name, pt.color, pt.icon
                     FROM rule_project_types rpt JOIN project_types pt ON pt.id = rpt.project_type_id
                     WHERE rpt.rule_id IN ($placeholders)",
                    $ruleIds
                ) ?: [];
                $scopeByRule = [];
                foreach ($scopeRows as $s) {
                    $scopeByRule[(int)$s['rule_id']][] = [
                        'id' => (int)$s['project_type_id'],
                        'slug' => $s['slug'],
                        'name' => $s['name'],
                        'color' => $s['color'],
                        'icon' => $s['icon'],
                    ];
                }
                foreach ($rules as &$r) {
                    $r['project_types'] = $scopeByRule[(int)$r['id']] ?? [];
                    $r['project_type_ids'] = array_map(fn($p) => $p['id'], $r['project_types']);
                }
                unset($r);
            }

            // Typen und Kategorien aus DB laden
            $types = $ruleEngine->getTypes();
            $categories = $ruleEngine->getCategories();

            Response::success([
                'rules' => $rules,
                'types' => $types,
                'types_map' => $ruleEngine->getTypesMap(), // Für Abwärtskompatibilität
                'categories' => $categories,
                'stats' => $ruleEngine->getStats($customerId)
            ]);
        }
        break;

    case 'POST':
        // Manager+ erforderlich
        if (!Auth::hasRole(ROLE_MANAGER)) {
            Response::forbidden();
        }

        $name = trim($input['name'] ?? '');
        $ruleContent = trim($input['rule_content'] ?? '');
        $ruleType = $input['rule_type'] ?? null;
        $ruleTypeId = isset($input['rule_type_id']) ? (int) $input['rule_type_id'] : null;
        $categoryId = isset($input['category_id']) ? ($input['category_id'] ? (int) $input['category_id'] : null) : null;
        $description = trim($input['description'] ?? '') ?: null;
        $priority = (int) ($input['priority'] ?? 0);

        if (empty($name) || empty($ruleContent)) {
            Response::error('Name und Regelinhalt erforderlich');
        }

        // Regel-Typ validieren (entweder rule_type oder rule_type_id muss gültig sein)
        if ($ruleTypeId) {
            $typeExists = $db->queryValue("SELECT id FROM rule_types WHERE id = ?", [$ruleTypeId]);
            if (!$typeExists) {
                Response::error('Ungültiger Regeltyp');
            }
        } elseif ($ruleType) {
            $typesMap = $ruleEngine->getTypesMap();
            if (!isset($typesMap[$ruleType])) {
                Response::error('Ungültiger Regeltyp');
            }
        } else {
            // Standard-Typ setzen
            $ruleType = 'style';
        }

        // Kategorie validieren (optional)
        if ($categoryId) {
            $catExists = $db->queryValue("SELECT id FROM rule_categories WHERE id = ?", [$categoryId]);
            if (!$catExists) {
                Response::error('Ungültige Kategorie');
            }
        }

        // Globale Regeln nur für Admins
        $ruleCustomerId = Auth::isAdmin() ? ($input['customer_id'] ?? null) : Auth::customerId();

        $ruleId = $ruleEngine->createRule([
            'customer_id' => $ruleCustomerId,
            'name' => $name,
            'description' => $description,
            'rule_type' => $ruleType,
            'rule_type_id' => $ruleTypeId,
            'category_id' => $categoryId,
            'rule_content' => $ruleContent,
            'priority' => $priority,
            'enforcement' => in_array(($input['enforcement'] ?? 'strict'), ['strict', 'soft'], true) ? $input['enforcement'] : 'strict',
            'applies_to' => in_array(($input['applies_to'] ?? 'both'), ['content', 'tool', 'both'], true) ? $input['applies_to'] : 'both',
            'source' => 'manual'
        ]);

        Response::success(['id' => $ruleId], 'Regel erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $rule = $db->queryOne("SELECT * FROM rules WHERE id = ?", [$id]);
        if (!$rule) {
            Response::notFound('Regel nicht gefunden');
        }

        // Zugriff prüfen
        if (!Auth::isAdmin()) {
            if ($rule['customer_id'] === null || $rule['customer_id'] != Auth::customerId()) {
                Response::forbidden('Keine Berechtigung für diese Regel');
            }
        }

        // Regel-Typ validieren falls geändert
        if (isset($input['rule_type_id'])) {
            $typeExists = $db->queryValue("SELECT id FROM rule_types WHERE id = ?", [(int) $input['rule_type_id']]);
            if (!$typeExists) {
                Response::error('Ungültiger Regeltyp');
            }
        }

        // Kategorie validieren falls geändert
        if (isset($input['category_id']) && $input['category_id']) {
            $catExists = $db->queryValue("SELECT id FROM rule_categories WHERE id = ?", [(int) $input['category_id']]);
            if (!$catExists) {
                Response::error('Ungültige Kategorie');
            }
        }

        // customer_id darf nur Admin aendern (Scope-Wechsel global ↔ Kunde)
        if (array_key_exists('customer_id', $input) && !Auth::isAdmin()) {
            unset($input['customer_id']);
        } elseif (array_key_exists('customer_id', $input) && $input['customer_id']) {
            $custExists = $db->queryValue("SELECT id FROM customers WHERE id = ?", [(int) $input['customer_id']]);
            if (!$custExists) Response::error('Kunde nicht gefunden');
        }

        $ruleEngine->updateRule($id, $input);
        Response::success(null, 'Regel aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $rule = $db->queryOne("SELECT * FROM rules WHERE id = ?", [$id]);
        if (!$rule) {
            Response::notFound('Regel nicht gefunden');
        }

        // Zugriff prüfen
        if (!Auth::isAdmin()) {
            if ($rule['customer_id'] === null || $rule['customer_id'] != Auth::customerId()) {
                Response::forbidden('Keine Berechtigung für diese Regel');
            }
        }

        $ruleEngine->deleteRule($id);
        Response::success(null, 'Regel gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
