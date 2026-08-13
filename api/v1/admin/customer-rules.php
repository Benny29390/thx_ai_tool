<?php
/**
 * GET  /admin/customers/{id}/rules
 *   → liefert kundenspezifische Regeln + globale Regeln + Disabled-Flags + Kundenname.
 *
 * POST /admin/customers/{id}/rules
 *   → setzt das Disabled-Flag fuer eine globale Regel beim Kunden:
 *     Body: { rule_id: int, disabled: bool }
 *
 * Die Disabled-Flags werden in der Tabelle customer_rules persistiert:
 *   - Eintrag mit is_active=0 = "diese globale Regel ist fuer diesen Kunden deaktiviert"
 *   - kein Eintrag             = Regel gilt normal (Default)
 *
 * Kundenspezifische Regeln (rules.customer_id = X) werden hier zusaetzlich
 * geladen, weil die Steckbrief-Karte sie zeigt. Anlegen/Bearbeiten geht ueber
 * /api/v1/rules (POST/PUT mit customer_id).
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) Response::error('customer_id erforderlich');

$db = Database::getInstance();
$customer = $db->queryOne("SELECT id, name FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Kundenspezifische Regeln (rules.customer_id = X) — gruppiert nach Kategorie
    $specific = $db->query(
        "SELECT r.id, r.name, r.description, r.rule_type, r.rule_content, r.priority, r.is_active, r.customer_id, r.enforcement, r.applies_to,
                rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                rc.id as category_id, rc.name as category_name, rc.slug as category_slug,
                rc.color as category_color, rc.icon as category_icon, rc.sort_order as category_order
         FROM rules r
         LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
         LEFT JOIN rule_categories rc ON r.category_id = rc.id
         WHERE r.customer_id = ?
         ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name",
        [$customerId]
    ) ?: [];

    $grouped = [];
    foreach ($specific as $r) {
        $cat = $r['category_id'] ? (int)$r['category_id'] : 0;
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [
                'id' => $cat ?: null,
                'name' => $r['category_name'] ?: 'Ohne Kategorie',
                'slug' => $r['category_slug'] ?: 'uncategorized',
                'color' => $r['category_color'] ?: '#9ca3af',
                'icon' => $r['category_icon'] ?: 'help',
                'sort_order' => (int)($r['category_order'] ?? 999),
                'rules' => [],
            ];
        }
        $grouped[$cat]['rules'][] = $r;
    }
    usort($grouped, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

    // Globale Regeln (customer_id IS NULL) — alle, mit disabled-Flag pro Kunde
    $globals = $db->query(
        "SELECT r.id, r.name, r.description, r.rule_type, r.rule_content, r.priority, r.is_active, r.customer_id, r.enforcement, r.applies_to,
                rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                rc.id as category_id, rc.name as category_name, rc.slug as category_slug,
                rc.color as category_color, rc.icon as category_icon
         FROM rules r
         LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
         LEFT JOIN rule_categories rc ON r.category_id = rc.id
         WHERE r.customer_id IS NULL
         ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name"
    ) ?: [];

    $overrides = $db->query(
        "SELECT rule_id, is_active FROM customer_rules WHERE customer_id = ?",
        [$customerId]
    ) ?: [];
    $disabledIds = [];
    $forceEnabledIds = [];
    foreach ($overrides as $o) {
        $rid = (int)$o['rule_id'];
        if ((int)$o['is_active'] === 1) $forceEnabledIds[] = $rid;
        else $disabledIds[] = $rid;
    }
    foreach ($globals as &$g) {
        $g['disabled'] = in_array((int)$g['id'], $disabledIds, true);
        $g['force_enabled'] = in_array((int)$g['id'], $forceEnabledIds, true);
    }
    unset($g);

    // Projekt-Arten (Geltungsbereich) pro Regel anreichern — fuer specific UND globals
    $allRuleIds = array_merge(array_column($specific, 'id'), array_column($globals, 'id'));
    if (!empty($allRuleIds)) {
        $placeholders = implode(',', array_fill(0, count($allRuleIds), '?'));
        $scopeRows = $db->query(
            "SELECT rpt.rule_id, rpt.project_type_id, pt.slug, pt.name, pt.color, pt.icon
             FROM rule_project_types rpt JOIN project_types pt ON pt.id = rpt.project_type_id
             WHERE rpt.rule_id IN ($placeholders)",
            $allRuleIds
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
        $enrich = function (&$rule) use ($scopeByRule) {
            $rule['project_types'] = $scopeByRule[(int)$rule['id']] ?? [];
            $rule['project_type_ids'] = array_map(fn($p) => $p['id'], $rule['project_types']);
        };
        foreach ($specific as &$s) $enrich($s);
        unset($s);
        foreach ($globals as &$g) $enrich($g);
        unset($g);
    }

    // Kundens Projekt-Arten ziehen — entscheidet, welche globalen Regeln greifen
    $customerPtRows = $db->query(
        "SELECT cpt.project_type_id, pt.slug, pt.name, pt.color, pt.icon
         FROM customer_project_types cpt JOIN project_types pt ON pt.id = cpt.project_type_id
         WHERE cpt.customer_id = ?",
        [$customerId]
    ) ?: [];
    $customerPtIds = array_map(fn($r) => (int)$r['project_type_id'], $customerPtRows);

    // Globals aufteilen: greift (universell ODER mind. eine Projekt-Art-Ueberlappung ODER manuell aktiviert) vs. greift nicht
    $globalsApplicable = [];
    $globalsOther = [];
    foreach ($globals as $g) {
        $rulePtIds = $g['project_type_ids'] ?? [];
        $isUniversal = empty($rulePtIds);
        $overlap = !empty(array_intersect($rulePtIds, $customerPtIds));
        $forceOn = !empty($g['force_enabled']);
        if ($isUniversal || $overlap || $forceOn) {
            $globalsApplicable[] = $g;
        } else {
            $globalsOther[] = $g;
        }
    }

    Response::success([
        'customer_name' => $customer['name'],
        'customer_project_types' => $customerPtRows,
        'customer_project_type_ids' => $customerPtIds,
        'grouped' => array_values($grouped),
        'specific' => $specific, // flach — Frontend nutzt das direkt, robuster als grouped[].flatMap
        'specific_count' => count($specific),
        'specific_active' => count(array_filter($specific, fn($r) => (int)$r['is_active'] === 1)),
        'globals' => $globals, // alle (legacy/Kompat)
        'globals_applicable' => $globalsApplicable,
        'globals_other' => $globalsOther,
        'disabled_ids' => $disabledIds,
        'global_count' => count($globals),
        'global_applicable_count' => count($globalsApplicable),
        'global_other_count' => count($globalsOther),
    ]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $ruleId = (int)($payload['rule_id'] ?? 0);
    if ($ruleId <= 0) Response::error('rule_id fehlt');

    $rule = $db->queryOne(
        "SELECT id FROM rules WHERE id = ? AND customer_id IS NULL AND is_active = 1",
        [$ruleId]
    );
    if (!$rule) Response::error('Globale Regel nicht gefunden');

    $existing = $db->queryOne(
        "SELECT id FROM customer_rules WHERE customer_id = ? AND rule_id = ?",
        [$customerId, $ruleId]
    );

    // Zwei Pfade:
    //   disabled = true → negativer Override (Regel fuer diesen Kunden AUS, obwohl sie greifen wuerde)
    //   enabled  = true → positiver Override (Regel fuer diesen Kunden AN, obwohl Scope nicht passt)
    //   beides false/fehlt → Override loeschen, Regel bestimmt sich allein per Scope
    if (array_key_exists('disabled', $payload)) {
        $disabled = (bool)$payload['disabled'];
        if ($disabled) {
            if ($existing) $db->update('customer_rules', ['is_active' => 0], 'id = ?', [(int)$existing['id']]);
            else $db->insert('customer_rules', ['customer_id' => $customerId, 'rule_id' => $ruleId, 'is_active' => 0]);
        } else {
            if ($existing) $db->delete('customer_rules', 'id = ?', [(int)$existing['id']]);
        }
        Response::success(['rule_id' => $ruleId, 'disabled' => $disabled]);
    } elseif (array_key_exists('enabled', $payload)) {
        $enabled = (bool)$payload['enabled'];
        if ($enabled) {
            if ($existing) $db->update('customer_rules', ['is_active' => 1], 'id = ?', [(int)$existing['id']]);
            else $db->insert('customer_rules', ['customer_id' => $customerId, 'rule_id' => $ruleId, 'is_active' => 1]);
        } else {
            if ($existing) $db->delete('customer_rules', 'id = ?', [(int)$existing['id']]);
        }
        Response::success(['rule_id' => $ruleId, 'enabled' => $enabled]);
    } else {
        Response::error('disabled oder enabled erforderlich');
    }
}

Response::error('Nur GET / POST', 405);
