<?php
/**
 * Apply Rule from Feedback - Erstellt eine Regel aus analysiertem Feedback
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$ruleName = trim($input['rule_name'] ?? '');
$ruleContent = trim($input['rule_content'] ?? '');
$ruleType = $input['rule_type'] ?? 'style';
$ruleTypeId = isset($input['rule_type_id']) && $input['rule_type_id'] ? (int)$input['rule_type_id'] : null;
$categoryId = isset($input['category_id']) && $input['category_id'] ? (int)$input['category_id'] : null;
$scope = $input['scope'] ?? 'customer'; // 'customer' oder 'global'
$customerId = $input['customer_id'] ?? null;
$feedbackId = $input['feedback_id'] ?? null;

if (!$ruleName || !$ruleContent) {
    Response::error('Regelname und Inhalt erforderlich');
}

// Regel-Typ validieren (aus DB oder Fallback auf alte Slugs)
if ($ruleTypeId) {
    $typeExists = $db->queryValue("SELECT id FROM rule_types WHERE id = ?", [$ruleTypeId]);
    if (!$typeExists) {
        $ruleTypeId = null;
    }
}

// Fallback: Typ-ID aus Slug ermitteln falls nicht gesetzt
if (!$ruleTypeId && $ruleType) {
    $typeFromSlug = $db->queryOne("SELECT id FROM rule_types WHERE slug = ?", [$ruleType]);
    if ($typeFromSlug) {
        $ruleTypeId = $typeFromSlug['id'];
    }
}

// Kategorie validieren falls gesetzt
if ($categoryId) {
    $catExists = $db->queryValue("SELECT id FROM rule_categories WHERE id = ?", [$categoryId]);
    if (!$catExists) {
        $categoryId = null;
    }
}

// Slug aus Name generieren
$slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ruleName));
$slug = trim($slug, '-');

// Prüfen ob Slug schon existiert
$existing = $db->queryOne("SELECT id FROM rules WHERE slug = ?", [$slug]);
if ($existing) {
    $slug .= '-' . time();
}

// Regel erstellen
$ruleData = [
    'name' => $ruleName,
    'slug' => $slug,
    'rule_content' => $ruleContent,
    'rule_type' => $ruleType,
    'rule_type_id' => $ruleTypeId,
    'category_id' => $categoryId,
    'is_active' => 1,
    'priority' => 50, // Mittlere Priorität
    'created_by' => Auth::id()
];

// Scope bestimmen
if ($scope === 'customer' && $customerId) {
    // Regel direkt dem Kunden zuordnen
    $ruleData['customer_id'] = $customerId;
} else {
    // Globale Regel (customer_id = NULL)
    $ruleData['customer_id'] = null;
}

try {
    $ruleId = $db->insert('rules', $ruleData);

    // Bei kundenspezifischer Regel auch in customer_rules eintragen
    if ($scope === 'customer' && $customerId) {
        $db->insert('customer_rules', [
            'customer_id' => $customerId,
            'rule_id' => $ruleId,
            'is_active' => 1
        ]);
    }

    // Feedback-Eintrag aktualisieren falls vorhanden
    if ($feedbackId) {
        $db->update('section_feedback', [
            'rule_created' => 1,
            'created_rule_id' => $ruleId
        ], 'id = ?', [$feedbackId]);
    }

    Response::success([
        'rule_id' => $ruleId,
        'rule' => [
            'id' => $ruleId,
            'name' => $ruleName,
            'rule_content' => $ruleContent,
            'rule_type' => $ruleType,
            'scope' => $scope
        ]
    ], 'Regel erstellt und ' . ($scope === 'global' ? 'global' : 'für diesen Kunden') . ' aktiviert');

} catch (\Exception $e) {
    Response::error('Fehler beim Erstellen der Regel: ' . $e->getMessage());
}
