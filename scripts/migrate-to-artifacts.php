<?php
/**
 * Migration Script: Bestehende Daten als Artefakte spiegeln
 *
 * Migriert: rules, knowledge_entries, author_profiles -> artifacts
 * Tokenisiert alle Texte in entities
 *
 * Ausfuehrung: php scripts/migrate-to-artifacts.php
 * Oder via API: POST /admin/artifacts/migrate
 */

require_once dirname(__DIR__) . '/config/constants.php';

// Autoloader
spl_autoload_register(function ($class) {
    $namespaces = [
        'Core\\' => 'core/',
        'Models\\' => 'models/',
        'Services\\' => 'services/'
    ];
    foreach ($namespaces as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
$db = \Core\Database::getInstance($config['db']);

require_once SERVICES_PATH . '/EntityService.php';
require_once SERVICES_PATH . '/ArtifactService.php';

$entityService = new \Services\EntityService($db);
$artifactService = new \Services\ArtifactService($db, $entityService);

$results = [];
$errors = [];

/**
 * Slug generieren
 */
function makeSlug(string $type, string $name): string
{
    $name = mb_strtolower(trim($name));
    $replacements = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];
    $name = str_replace(array_keys($replacements), array_values($replacements), $name);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    $name = trim($name, '-');
    if (empty($name)) $name = 'unbenannt';
    return $type . '-' . $name;
}

// ===== 1. Regeln migrieren =====
echo "=== Regeln migrieren ===\n";

$rules = $db->query(
    "SELECT r.*, c.name as customer_name,
            rt.name as type_name, rt.slug as type_slug,
            rc.name as category_name, rc.slug as category_slug
     FROM rules r
     LEFT JOIN customers c ON r.customer_id = c.id
     LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
     LEFT JOIN rule_categories rc ON r.category_id = rc.id"
);

$ruleCount = 0;
foreach ($rules as $rule) {
    // Pruefen ob schon migriert
    $existing = $db->queryOne(
        "SELECT id FROM artifacts WHERE source_table = 'rules' AND source_id = ?",
        [$rule['id']]
    );
    if ($existing) {
        continue;
    }

    try {
        $content = $rule['rule_content'] ?? '';
        $entityContent = !empty($content) ? $entityService->tokenize($content) : null;

        $meta = [
            'type' => 'Regel',
            'name' => $rule['name'],
            'description' => $rule['description'] ?? null,
            'category' => $rule['category_name'] ?? null,
            'category_slug' => $rule['category_slug'] ?? null,
            'rule_type' => $rule['type_name'] ?? null,
            'rule_type_slug' => $rule['type_slug'] ?? $rule['rule_type'] ?? null,
            'priority' => (int) ($rule['priority'] ?? 0),
            'scope' => [
                'customers' => $rule['customer_id'] ? [(int) $rule['customer_id']] : []
            ],
            'source' => ['table' => 'rules', 'id' => (int) $rule['id']]
        ];

        $slug = makeSlug('regel', $rule['name']);

        // Eindeutigkeit sicherstellen
        $originalSlug = $slug;
        $counter = 1;
        while ($db->queryOne("SELECT id FROM artifacts WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $db->insert('artifacts', [
            'slug' => $slug,
            'artifact_type' => 'regel',
            'entity_content' => $entityContent,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'source_table' => 'rules',
            'source_id' => $rule['id'],
            'is_active' => (int) $rule['is_active'],
            'customer_id' => $rule['customer_id'],
            'created_by' => null,
            'created_at' => $rule['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        $ruleCount++;
    } catch (\Exception $e) {
        $errors[] = "Regel #{$rule['id']}: " . $e->getMessage();
    }
}
$results[] = "$ruleCount Regeln migriert";
echo "$ruleCount Regeln migriert\n";

// ===== 2. Knowledge Entries migrieren =====
echo "=== Wissenseintraege migrieren ===\n";

$entries = $db->query(
    "SELECT ke.*, kb.name as base_name, kb.customer_id,
            kc.name as category_name
     FROM knowledge_entries ke
     JOIN knowledge_bases kb ON ke.knowledge_base_id = kb.id
     LEFT JOIN knowledge_categories kc ON ke.category_id = kc.id"
);

$knowledgeCount = 0;
foreach ($entries as $entry) {
    $existing = $db->queryOne(
        "SELECT id FROM artifacts WHERE source_table = 'knowledge_entries' AND source_id = ?",
        [$entry['id']]
    );
    if ($existing) {
        continue;
    }

    try {
        $content = $entry['content'] ?? '';
        $entityContent = !empty($content) ? $entityService->tokenize($content) : null;

        // Tags laden
        $tags = $db->query(
            "SELECT kt.name FROM knowledge_tags kt
             JOIN knowledge_entry_tags ket ON kt.id = ket.tag_id
             WHERE ket.knowledge_entry_id = ?",
            [$entry['id']]
        );
        $tagNames = array_column($tags, 'name');

        // Kunden die Zugriff haben
        $customerAccess = $db->query(
            "SELECT customer_id FROM customer_knowledge WHERE knowledge_entry_id = ? AND is_active = 1",
            [$entry['id']]
        );
        $customerIds = array_map('intval', array_column($customerAccess, 'customer_id'));
        if ($entry['customer_id']) {
            $customerIds[] = (int) $entry['customer_id'];
            $customerIds = array_unique($customerIds);
        }

        $meta = [
            'type' => 'Wissen',
            'title' => $entry['title'],
            'category' => $entry['category_name'] ?? null,
            'tags' => $tagNames,
            'source_type' => $entry['source_type'] ?? null,
            'source_url' => $entry['source_url'] ?? null,
            'scope' => [
                'customers' => $customerIds
            ],
            'source' => ['table' => 'knowledge_entries', 'id' => (int) $entry['id']]
        ];

        $slug = makeSlug('wissen', $entry['title']);
        $originalSlug = $slug;
        $counter = 1;
        while ($db->queryOne("SELECT id FROM artifacts WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $db->insert('artifacts', [
            'slug' => $slug,
            'artifact_type' => 'wissen',
            'entity_content' => $entityContent,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'source_table' => 'knowledge_entries',
            'source_id' => $entry['id'],
            'is_active' => 1,
            'customer_id' => $entry['customer_id'],
            'created_by' => null,
            'created_at' => $entry['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        $knowledgeCount++;
    } catch (\Exception $e) {
        $errors[] = "Wissen #{$entry['id']}: " . $e->getMessage();
    }
}
$results[] = "$knowledgeCount Wissenseintraege migriert";
echo "$knowledgeCount Wissenseintraege migriert\n";

// ===== 3. Autorenprofile migrieren =====
echo "=== Autorenprofile migrieren ===\n";

$authors = $db->query(
    "SELECT ap.*, c.name as customer_name
     FROM author_profiles ap
     LEFT JOIN customers c ON ap.customer_id = c.id"
);

$authorCount = 0;
foreach ($authors as $author) {
    $existing = $db->queryOne(
        "SELECT id FROM artifacts WHERE source_table = 'author_profiles' AND source_id = ?",
        [$author['id']]
    );
    if ($existing) {
        continue;
    }

    try {
        // Kombinierten Text aus allen Profilfeldern erstellen
        $contentParts = array_filter([
            $author['writing_style'] ?? '',
            $author['expertise'] ?? '',
            $author['example_text'] ?? '',
            $author['notes'] ?? ''
        ]);
        $content = implode(' ', $contentParts);
        $entityContent = !empty($content) ? $entityService->tokenize($content) : null;

        $meta = [
            'type' => 'Autor',
            'name' => $author['name'],
            'tone' => $author['tone'] ?? null,
            'perspective' => $author['perspective'] ?? null,
            'writing_style' => $author['writing_style'] ?? null,
            'expertise' => $author['expertise'] ?? null,
            'scope' => [
                'customers' => $author['customer_id'] ? [(int) $author['customer_id']] : []
            ],
            'source' => ['table' => 'author_profiles', 'id' => (int) $author['id']]
        ];

        $slug = makeSlug('autor', $author['name']);
        $originalSlug = $slug;
        $counter = 1;
        while ($db->queryOne("SELECT id FROM artifacts WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $db->insert('artifacts', [
            'slug' => $slug,
            'artifact_type' => 'autor',
            'entity_content' => $entityContent,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'source_table' => 'author_profiles',
            'source_id' => $author['id'],
            'is_active' => (int) ($author['is_active'] ?? 1),
            'customer_id' => $author['customer_id'],
            'created_by' => null,
            'created_at' => $author['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        $authorCount++;
    } catch (\Exception $e) {
        $errors[] = "Autor #{$author['id']}: " . $e->getMessage();
    }
}
$results[] = "$authorCount Autorenprofile migriert";
echo "$authorCount Autorenprofile migriert\n";

// ===== Zusammenfassung =====
$entityStats = $entityService->getStats();
$results[] = "Entities gesamt: " . $entityStats['total'];

echo "\n=== Zusammenfassung ===\n";
foreach ($results as $r) {
    echo "  $r\n";
}

if (!empty($errors)) {
    echo "\n=== Fehler ===\n";
    foreach ($errors as $e) {
        echo "  FEHLER: $e\n";
    }
}

// Wenn via API aufgerufen, JSON zurueckgeben
if (defined('API_CALL') && API_CALL) {
    return [
        'results' => $results,
        'errors' => $errors,
        'entity_stats' => $entityStats
    ];
}
