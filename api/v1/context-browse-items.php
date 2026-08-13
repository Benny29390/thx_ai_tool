<?php
/**
 * Context Browse Items API - Regeln und Wissen zum Browsen laden
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$customerId = Auth::isAdmin() ? ($_GET['customer_id'] ?? null) : Auth::customerId();

// ===== Regeln laden =====
$ruleWhere = ['r.is_active = 1'];
$ruleParams = [];

if ($customerId) {
    $ruleWhere[] = '(r.customer_id = ? OR r.customer_id IS NULL)';
    $ruleParams[] = (int) $customerId;
}

$ruleWhereClause = 'WHERE ' . implode(' AND ', $ruleWhere);

$rules = $db->query(
    "SELECT r.id, r.name, r.rule_content, r.category_id,
            rc.name as category_name
     FROM rules r
     LEFT JOIN rule_categories rc ON r.category_id = rc.id
     $ruleWhereClause
     ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name",
    $ruleParams
);

// Regeln nach Kategorien gruppieren
$ruleCategories = [];
$ruleCategoryMap = [];
$uncategorizedRules = [];

foreach ($rules as $rule) {
    $ruleData = [
        'id' => (int) $rule['id'],
        'name' => $rule['name'],
        'rule_content' => $rule['rule_content']
    ];

    if ($rule['category_id']) {
        $catId = (int) $rule['category_id'];
        if (!isset($ruleCategoryMap[$catId])) {
            $ruleCategoryMap[$catId] = [
                'id' => $catId,
                'name' => $rule['category_name'],
                'rules' => []
            ];
        }
        $ruleCategoryMap[$catId]['rules'][] = $ruleData;
    } else {
        $uncategorizedRules[] = $ruleData;
    }
}

// ===== Wissen laden =====
$knowledgeWhere = ['1=1'];
$knowledgeParams = [];

if ($customerId) {
    $knowledgeWhere[] = 'kb.customer_id = ?';
    $knowledgeParams[] = (int) $customerId;
}

$knowledgeWhereClause = 'WHERE ' . implode(' AND ', $knowledgeWhere);

$entries = $db->query(
    "SELECT ke.id, ke.title, ke.content, ke.category_id,
            kb.name as base_name,
            kc.name as category_name
     FROM knowledge_entries ke
     JOIN knowledge_bases kb ON ke.knowledge_base_id = kb.id
     LEFT JOIN knowledge_categories kc ON ke.category_id = kc.id
     $knowledgeWhereClause
     ORDER BY kc.sort_order, kc.name, ke.title",
    $knowledgeParams
);

// Wissen nach Kategorien gruppieren
$knowledgeCategories = [];
$knowledgeCategoryMap = [];
$uncategorizedKnowledge = [];

foreach ($entries as $entry) {
    $entryData = [
        'id' => (int) $entry['id'],
        'title' => $entry['title'] ?: $entry['base_name'],
        'content_preview' => mb_substr(strip_tags($entry['content'] ?? ''), 0, 200)
    ];

    if ($entry['category_id']) {
        $catId = (int) $entry['category_id'];
        if (!isset($knowledgeCategoryMap[$catId])) {
            $knowledgeCategoryMap[$catId] = [
                'id' => $catId,
                'name' => $entry['category_name'],
                'entries' => []
            ];
        }
        $knowledgeCategoryMap[$catId]['entries'][] = $entryData;
    } else {
        $uncategorizedKnowledge[] = $entryData;
    }
}

// ===== Autoren laden =====
$authorWhere = ['ap.is_active = 1'];
$authorParams = [];

if ($customerId) {
    $authorWhere[] = '(ap.customer_id = ? OR ap.customer_id IS NULL)';
    $authorParams[] = (int) $customerId;
}

$authorWhereClause = 'WHERE ' . implode(' AND ', $authorWhere);

$authors = $db->query(
    "SELECT ap.id, ap.name, ap.writing_style, ap.expertise, ap.tone, ap.perspective, ap.customer_id,
            c.name as customer_name
     FROM author_profiles ap
     LEFT JOIN customers c ON ap.customer_id = c.id
     $authorWhereClause
     ORDER BY ap.name",
    $authorParams
);

$authorList = [];
foreach ($authors as $author) {
    $subtitle = [];
    if ($author['tone']) $subtitle[] = $author['tone'];
    if ($author['perspective']) $subtitle[] = $author['perspective'];

    $authorList[] = [
        'id' => (int) $author['id'],
        'name' => $author['name'],
        'subtitle' => implode(' / ', $subtitle),
        'customer_name' => $author['customer_name']
    ];
}

Response::success([
    'rules' => [
        'categories' => array_values($ruleCategoryMap),
        'uncategorized' => $uncategorizedRules
    ],
    'knowledge' => [
        'categories' => array_values($knowledgeCategoryMap),
        'uncategorized' => $uncategorizedKnowledge
    ],
    'authors' => $authorList
]);
