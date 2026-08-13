<?php
/**
 * Rule Engine - Regel-Verarbeitung fuer KI-Prompts
 */

namespace Services;

use Core\Database;

class RuleEngine
{
    private Database $db;
    private ?array $typesCache = null;
    private ?array $categoriesCache = null;

    // Fallback Regel-Typen (falls DB nicht verfuegbar)
    public const TYPES = [
        'style' => 'Schreibstil',
        'format' => 'Formatierung',
        'content' => 'Inhalt',
        'link' => 'Links',
        'tone' => 'Tonalitaet'
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Regel-Typen aus der Datenbank laden
     */
    public function getTypes(bool $activeOnly = true): array
    {
        if ($this->typesCache !== null && $activeOnly) {
            return $this->typesCache;
        }

        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $types = $this->db->query(
            "SELECT * FROM rule_types $where ORDER BY sort_order, name"
        );

        if ($activeOnly) {
            $this->typesCache = $types;
        }

        return $types;
    }

    /**
     * Typen als assoziatives Array (slug => name) fuer Abwaertskompatibilitaet
     */
    public function getTypesMap(): array
    {
        $types = $this->getTypes();
        $map = [];
        foreach ($types as $type) {
            $map[$type['slug']] = $type['name'];
        }
        // Fallback auf Konstante wenn DB leer
        return !empty($map) ? $map : self::TYPES;
    }

    /**
     * Einzelnen Typ nach Slug laden
     */
    public function getTypeBySlug(string $slug): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM rule_types WHERE slug = ?",
            [$slug]
        );
    }

    /**
     * Regel-Kategorien aus der Datenbank laden
     */
    public function getCategories(bool $activeOnly = true): array
    {
        if ($this->categoriesCache !== null && $activeOnly) {
            return $this->categoriesCache;
        }

        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $categories = $this->db->query(
            "SELECT * FROM rule_categories $where ORDER BY sort_order, name"
        );

        if ($activeOnly) {
            $this->categoriesCache = $categories;
        }

        return $categories;
    }

    /**
     * Einzelne Kategorie nach ID laden
     */
    public function getCategoryById(int $id): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM rule_categories WHERE id = ?",
            [$id]
        );
    }

    /**
     * Regeln nach Kategorien gruppiert laden
     */
    public function getRulesGroupedByCategory(?int $customerId = null): array
    {
        $where = 'WHERE r.is_active = 1';
        $params = [];

        if ($customerId !== null) {
            $where .= ' AND (r.customer_id = ? OR r.customer_id IS NULL)';
            $params[] = $customerId;
        }

        $rules = $this->db->query(
            "SELECT r.*, rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                    rc.name as category_name, rc.slug as category_slug, rc.color as category_color, rc.icon as category_icon
             FROM rules r
             LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
             LEFT JOIN rule_categories rc ON r.category_id = rc.id
             $where
             ORDER BY rc.sort_order, rc.name, r.priority DESC, r.name",
            $params
        );

        // Gruppieren nach Kategorie
        $grouped = [];
        $uncategorized = [];

        foreach ($rules as $rule) {
            if ($rule['category_id']) {
                $catKey = $rule['category_id'];
                if (!isset($grouped[$catKey])) {
                    $grouped[$catKey] = [
                        'id' => $rule['category_id'],
                        'name' => $rule['category_name'],
                        'slug' => $rule['category_slug'],
                        'color' => $rule['category_color'],
                        'icon' => $rule['category_icon'],
                        'rules' => []
                    ];
                }
                $grouped[$catKey]['rules'][] = $rule;
            } else {
                $uncategorized[] = $rule;
            }
        }

        // Unkategorisierte ans Ende
        if (!empty($uncategorized)) {
            $grouped['uncategorized'] = [
                'id' => null,
                'name' => 'Ohne Kategorie',
                'slug' => 'uncategorized',
                'color' => '#9ca3af',
                'icon' => 'help',
                'rules' => $uncategorized
            ];
        }

        return array_values($grouped);
    }

    /**
     * Alle Regeln mit Typ- und Kategorie-Infos laden
     */
    public function getAllRulesWithDetails(?int $customerId = null): array
    {
        $where = [];
        $params = [];

        if ($customerId !== null) {
            $where[] = '(r.customer_id = ? OR r.customer_id IS NULL)';
            $params[] = $customerId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->query(
            "SELECT r.*, rt.name as type_name, rt.slug as type_slug, rt.color as type_color, rt.icon as type_icon,
                    rc.name as category_name, rc.slug as category_slug, rc.color as category_color, rc.icon as category_icon,
                    c.name as customer_name
             FROM rules r
             LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
             LEFT JOIN rule_categories rc ON r.category_id = rc.id
             LEFT JOIN customers c ON r.customer_id = c.id
             $whereClause
             ORDER BY r.priority DESC, rt.sort_order, r.name",
            $params
        );
    }

    /**
     * Alle aktiven Regeln fuer einen Kunden laden.
     *
     * Modell:
     *  - Eigene Regeln des Kunden (rules.customer_id = X) gelten immer.
     *  - Globale Regeln (rules.customer_id IS NULL) gelten:
     *      a) wenn KEIN Geltungsbereich-Eintrag (rule_project_types) gesetzt ist (= "fuer alle")
     *      b) ODER mind. eine Projekt-Art der Regel ueberschneidet sich mit den Tags des Kunden
     *      c) ODER es liegt ein positiver Override vor (customer_rules.is_active = 1)
     *  - Minus Overrides: Eintrag in customer_rules mit is_active=0 = "fuer diesen Kunden aus".
     */
    public function getActiveRules(int $customerId): array
    {
        return $this->db->query(
            "SELECT * FROM rules r
             WHERE r.is_active = 1
               AND (
                   r.customer_id = ?
                   OR (
                       r.customer_id IS NULL
                       AND (
                           NOT EXISTS (SELECT 1 FROM rule_project_types rpt WHERE rpt.rule_id = r.id)
                           OR EXISTS (
                               SELECT 1 FROM rule_project_types rpt
                               JOIN customer_project_types cpt ON cpt.project_type_id = rpt.project_type_id
                               WHERE rpt.rule_id = r.id AND cpt.customer_id = ?
                           )
                           OR EXISTS (
                               SELECT 1 FROM customer_rules cr2
                               WHERE cr2.customer_id = ? AND cr2.rule_id = r.id AND cr2.is_active = 1
                           )
                       )
                   )
               )
               AND r.id NOT IN (SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 0)
             ORDER BY r.priority DESC, r.rule_type, r.name",
            [$customerId, $customerId, $customerId, $customerId]
        );
    }

    /**
     * Regeln nach Typ laden — respektiert kunden-spezifische Overrides (siehe getActiveRules).
     */
    public function getRulesByType(int $customerId, string $type): array
    {
        return $this->db->query(
            "SELECT * FROM rules
             WHERE (customer_id = ? OR customer_id IS NULL)
               AND rule_type = ?
               AND is_active = 1
               AND id NOT IN (SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 0)
             ORDER BY priority DESC",
            [$customerId, $type, $customerId]
        );
    }

    /**
     * System-Prompt mit Regeln erstellen
     */
    public function buildSystemPrompt(int $customerId, array $options = []): string
    {
        $prompt = "Du bist ein professioneller Content-Autor. ";
        $prompt .= "Schreibe auf Deutsch in hoher Qualitaet.\n\n";

        // Regeln laden
        $rules = $this->getActiveRules($customerId);

        if (empty($rules)) {
            return $prompt;
        }

        // Regeln nach Typ gruppieren
        $grouped = [];
        foreach ($rules as $rule) {
            $grouped[$rule['rule_type']][] = $rule;
        }

        // Stil-Regeln
        if (!empty($grouped['style'])) {
            $prompt .= "## Schreibstil\n";
            foreach ($grouped['style'] as $rule) {
                $prompt .= "- " . $rule['rule_content'] . "\n";
            }
            $prompt .= "\n";
        }

        // Tonalitaet
        if (!empty($grouped['tone'])) {
            $prompt .= "## Tonalitaet\n";
            foreach ($grouped['tone'] as $rule) {
                $prompt .= "- " . $rule['rule_content'] . "\n";
            }
            $prompt .= "\n";
        }

        // Formatierung
        if (!empty($grouped['format'])) {
            $prompt .= "## Formatierung\n";
            foreach ($grouped['format'] as $rule) {
                $prompt .= "- " . $rule['rule_content'] . "\n";
            }
            $prompt .= "\n";
        }

        // Inhalt
        if (!empty($grouped['content'])) {
            $prompt .= "## Inhaltliche Vorgaben\n";
            foreach ($grouped['content'] as $rule) {
                $prompt .= "- " . $rule['rule_content'] . "\n";
            }
            $prompt .= "\n";
        }

        // Link-Regeln
        if (!empty($grouped['link'])) {
            $prompt .= "## Link-Regeln\n";
            foreach ($grouped['link'] as $rule) {
                $prompt .= "- " . $rule['rule_content'] . "\n";
            }
            $prompt .= "\n";
        }

        return $prompt;
    }

    /**
     * Regel erstellen
     */
    public function createRule(array $data): int
    {
        $insertData = [
            'customer_id' => $data['customer_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rule_type' => $data['rule_type'] ?? null,
            'rule_content' => $data['rule_content'],
            'source' => $data['source'] ?? 'manual',
            'priority' => $data['priority'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'enforcement' => in_array(($data['enforcement'] ?? 'strict'), ['strict', 'soft'], true) ? $data['enforcement'] : 'strict',
            'applies_to' => in_array(($data['applies_to'] ?? 'both'), ['content', 'tool', 'both'], true) ? $data['applies_to'] : 'both',
        ];

        // Neue Felder
        if (isset($data['rule_type_id'])) {
            $insertData['rule_type_id'] = (int) $data['rule_type_id'];
        } elseif (isset($data['rule_type'])) {
            // Automatisch rule_type_id anhand von rule_type setzen
            $type = $this->getTypeBySlug($data['rule_type']);
            if ($type) {
                $insertData['rule_type_id'] = $type['id'];
            }
        }

        if (isset($data['category_id'])) {
            $insertData['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        }

        return $this->db->insert('rules', $insertData);
    }

    /**
     * Regel aktualisieren
     */
    public function updateRule(int $ruleId, array $data): void
    {
        $updates = [];
        if (isset($data['name'])) $updates['name'] = $data['name'];
        if (isset($data['description'])) $updates['description'] = $data['description'];
        if (isset($data['rule_content'])) $updates['rule_content'] = $data['rule_content'];
        if (isset($data['priority'])) $updates['priority'] = (int) $data['priority'];
        if (isset($data['is_active'])) $updates['is_active'] = (int) $data['is_active'];
        if (isset($data['enforcement']) && in_array($data['enforcement'], ['strict', 'soft'], true)) {
            $updates['enforcement'] = $data['enforcement'];
        }
        if (isset($data['applies_to']) && in_array($data['applies_to'], ['content', 'tool', 'both'], true)) {
            $updates['applies_to'] = $data['applies_to'];
        }

        // rule_type und rule_type_id
        if (isset($data['rule_type_id'])) {
            $updates['rule_type_id'] = (int) $data['rule_type_id'];
            // Auch alten rule_type Wert aktualisieren fuer Abwaertskompatibilitaet
            $type = $this->db->queryOne("SELECT slug FROM rule_types WHERE id = ?", [$data['rule_type_id']]);
            if ($type) {
                $updates['rule_type'] = $type['slug'];
            }
        } elseif (isset($data['rule_type'])) {
            $updates['rule_type'] = $data['rule_type'];
            // Automatisch rule_type_id anhand von rule_type setzen
            $type = $this->getTypeBySlug($data['rule_type']);
            if ($type) {
                $updates['rule_type_id'] = $type['id'];
            }
        }

        // category_id
        if (array_key_exists('category_id', $data)) {
            $updates['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        }

        // customer_id: erlaubt Scope-Wechsel (Kunde → global oder umgekehrt). Validierung erfolgt im API-Endpoint.
        if (array_key_exists('customer_id', $data)) {
            $updates['customer_id'] = $data['customer_id'] ? (int) $data['customer_id'] : null;
        }

        if (!empty($updates)) {
            $this->db->update('rules', $updates, 'id = ?', [$ruleId]);
        }
    }

    /**
     * Regel loeschen
     */
    public function deleteRule(int $ruleId): void
    {
        $this->db->delete('rules', 'id = ?', [$ruleId]);
    }

    /**
     * Regel aktivieren/deaktivieren
     */
    public function toggleRule(int $ruleId, bool $active): void
    {
        $this->db->update('rules', ['is_active' => (int) $active], 'id = ?', [$ruleId]);
    }

    /**
     * Regelvorschlag freigeben
     */
    public function approveSuggestion(int $suggestionId, int $approvedBy, ?array $modifications = null): int
    {
        $suggestion = $this->db->queryOne(
            "SELECT * FROM rule_suggestions WHERE id = ? AND status = 'pending'",
            [$suggestionId]
        );

        if (!$suggestion) {
            throw new \Exception('Regelvorschlag nicht gefunden oder bereits bearbeitet');
        }

        // Regel erstellen
        $ruleContent = $modifications['rule_content'] ?? $suggestion['suggested_rule'];
        $ruleType = $modifications['rule_type'] ?? $suggestion['rule_type'];

        $ruleId = $this->createRule([
            'customer_id' => $suggestion['customer_id'],
            'name' => 'KI-generiert: ' . substr($ruleContent, 0, 50),
            'rule_type' => $ruleType,
            'rule_content' => $ruleContent,
            'source' => 'ai_approved'
        ]);

        // Regel mit Freigabe-Info aktualisieren
        $this->db->update('rules', [
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$ruleId]);

        // Vorschlag als freigegeben markieren
        $this->db->update('rule_suggestions', [
            'status' => 'approved',
            'reviewed_by' => $approvedBy,
            'reviewed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$suggestionId]);

        return $ruleId;
    }

    /**
     * Regelvorschlag ablehnen
     */
    public function rejectSuggestion(int $suggestionId, int $reviewedBy): void
    {
        $this->db->update('rule_suggestions', [
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$suggestionId]);
    }

    /**
     * Statistiken
     */
    public function getStats(?int $customerId = null): array
    {
        $where = $customerId ? 'WHERE customer_id = ? OR customer_id IS NULL' : '';
        $params = $customerId ? [$customerId] : [];

        return [
            'total' => $this->db->queryValue(
                "SELECT COUNT(*) FROM rules $where",
                $params
            ),
            'active' => $this->db->queryValue(
                "SELECT COUNT(*) FROM rules $where " . ($where ? 'AND' : 'WHERE') . " is_active = 1",
                $params
            ),
            'by_type' => $this->db->query(
                "SELECT rule_type, COUNT(*) as count FROM rules $where GROUP BY rule_type",
                $params
            ),
            'pending_suggestions' => $this->db->queryValue(
                "SELECT COUNT(*) FROM rule_suggestions WHERE status = 'pending'" .
                ($customerId ? " AND customer_id = ?" : ""),
                $customerId ? [$customerId] : []
            )
        ];
    }
}
