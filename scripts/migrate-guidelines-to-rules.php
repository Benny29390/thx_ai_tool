<?php
/**
 * Einmal-Migration: Tabelle `guidelines` → `rules`.
 *
 * Mapping:
 *   - tool_communication → applies_to='tool',    rule_type='tone'      (Tonalitaet)
 *   - content_output     → applies_to='content', rule_type='style'     (Schreibstil)
 *   - internal           → applies_to='both',    rule_type='language'  -> via existierende Slugs
 *
 * Alle als enforcement='strict' (waren bislang implizit "MUSS").
 * Alle global (customer_id = NULL).
 *
 * Idempotent: prueft via guideline-source-Marker im Description-Feld, ob schon migriert wurde.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';
$config = require __DIR__ . '/../config/config.php';

\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$mapping = [
    'tool_communication' => ['applies_to' => 'tool',    'rule_type' => 'tone'],
    'content_output'     => ['applies_to' => 'content', 'rule_type' => 'style'],
    'internal'           => ['applies_to' => 'both',    'rule_type' => 'style'],
];

// rule_type-Slug → id
$typeMap = [];
foreach ($db->query("SELECT id, slug FROM rule_types") ?: [] as $rt) {
    $typeMap[$rt['slug']] = (int)$rt['id'];
}

$guidelines = $db->query("SELECT * FROM guidelines ORDER BY category, sort_order, id") ?: [];

$created = 0;
$skipped = 0;

foreach ($guidelines as $g) {
    $cat = $g['category'];
    if (!isset($mapping[$cat])) {
        echo "SKIP: Unbekannte Kategorie '{$cat}' fuer Guideline #{$g['id']}\n";
        $skipped++;
        continue;
    }

    // Schon migriert? — wir nutzen einen Marker in description
    $marker = "[migriert aus guidelines:{$g['id']}]";
    $existing = $db->queryOne(
        "SELECT id FROM rules WHERE description LIKE ? AND customer_id IS NULL",
        ['%' . $marker . '%']
    );
    if ($existing) {
        echo "SKIP: Guideline #{$g['id']} ('{$g['title']}') wurde bereits migriert als rule #{$existing['id']}\n";
        $skipped++;
        continue;
    }

    $map = $mapping[$cat];
    $ruleTypeSlug = $map['rule_type'];
    $ruleTypeId = $typeMap[$ruleTypeSlug] ?? null;

    $ruleData = [
        'customer_id' => null,
        'name' => $g['title'],
        'description' => $marker,
        'rule_type' => $ruleTypeSlug,
        'rule_type_id' => $ruleTypeId,
        'rule_content' => $g['content'],
        'source' => 'manual',
        'priority' => (int)($g['sort_order'] ?? 0),
        'is_active' => (int)($g['is_active'] ?? 1),
        'enforcement' => 'strict',
        'applies_to' => $map['applies_to'],
    ];

    $newId = $db->insert('rules', $ruleData);
    echo "OK:   Guideline #{$g['id']} '{$g['title']}' → rule #{$newId} (applies_to={$map['applies_to']}, type={$ruleTypeSlug})\n";
    $created++;
}

echo "\nFertig: {$created} migriert, {$skipped} uebersprungen.\n";
echo "Die guidelines-Tabelle bleibt als Backup bestehen — kann nach Verifikation manuell geleert/gedroppt werden.\n";
