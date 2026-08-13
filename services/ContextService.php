<?php
/**
 * Context Service - Kontext-Verwaltung und -Zusammenstellung fuer AI-Prompts
 */

namespace Services;

use Core\Database;

class ContextService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Alle Kontext-Items zu einem Text fuer den AI-Prompt zusammenbauen
     */
    public function assembleContextText(int $contextId): string
    {
        $items = $this->db->query(
            "SELECT * FROM context_items WHERE context_id = ? ORDER BY sort_order, id",
            [$contextId]
        );

        if (empty($items)) {
            return '';
        }

        $sections = [];

        foreach ($items as $item) {
            switch ($item['item_type']) {
                case 'customer':
                    $text = $this->buildCustomerSection($item);
                    if ($text) $sections[] = $text;
                    break;

                case 'knowledge':
                    $text = $this->buildKnowledgeSection($item);
                    if ($text) $sections[] = $text;
                    break;

                case 'url':
                    $title = $item['title'] ?: 'URL-Inhalt';
                    if ($item['content']) {
                        $sections[] = "## {$title}\n" . $item['content'];
                    }
                    break;

                case 'pdf':
                    $title = $item['title'] ?: 'Dokument';
                    if ($item['content']) {
                        $sections[] = "## {$title}\n" . $item['content'];
                    }
                    break;

                case 'text':
                    $title = $item['title'] ?: 'Zusaetzliche Information';
                    if ($item['content']) {
                        $sections[] = "## {$title}\n" . $item['content'];
                    }
                    break;

                case 'rule':
                case 'rule_category':
                    // Regeln werden separat behandelt in assembleRulesText()
                    break;

                case 'knowledge_category':
                    $text = $this->buildKnowledgeCategorySection($item);
                    if ($text) $sections[] = $text;
                    break;

                case 'author':
                    $text = $this->buildAuthorSection($item);
                    if ($text) $sections[] = $text;
                    break;
            }
        }

        if (empty($sections)) {
            return '';
        }

        return "# Kontext-Informationen\n\n" . implode("\n\n", $sections);
    }

    /**
     * Kundenprofil-Sektion zusammenbauen
     */
    private function buildCustomerSection(array $item): string
    {
        if (!$item['reference_id']) {
            return '';
        }

        $customer = $this->db->queryOne(
            "SELECT * FROM customers WHERE id = ?",
            [$item['reference_id']]
        );

        if (!$customer) {
            return '';
        }

        $parts = ["## Kundenprofil: {$customer['name']}"];

        if (!empty($customer['description'])) {
            $parts[] = "Beschreibung: {$customer['description']}";
        }
        if (!empty($customer['industry'])) {
            $parts[] = "Branche: {$customer['industry']}";
        }
        if (!empty($customer['target_audience'])) {
            $parts[] = "Zielgruppe: {$customer['target_audience']}";
        }
        if (!empty($customer['tone_of_voice'])) {
            $parts[] = "Tonalitaet: {$customer['tone_of_voice']}";
        }
        if (!empty($customer['products_services'])) {
            $parts[] = "Produkte/Services: {$customer['products_services']}";
        }
        if (!empty($customer['unique_selling_points'])) {
            $parts[] = "USPs: {$customer['unique_selling_points']}";
        }
        if (!empty($customer['brand_values'])) {
            $parts[] = "Markenwerte: {$customer['brand_values']}";
        }
        if (!empty($customer['website'])) {
            $parts[] = "Website: {$customer['website']}";
        }

        return implode("\n", $parts);
    }

    /**
     * Wissens-Sektion zusammenbauen
     */
    private function buildKnowledgeSection(array $item): string
    {
        if (!$item['reference_id']) {
            return '';
        }

        $entry = $this->db->queryOne(
            "SELECT ke.*, kb.name as base_name FROM knowledge_entries ke
             JOIN knowledge_bases kb ON ke.knowledge_base_id = kb.id
             WHERE ke.id = ?",
            [$item['reference_id']]
        );

        if (!$entry) {
            return '';
        }

        $title = $item['title'] ?: $entry['title'] ?: 'Wissenseintrag';
        return "## {$title}\n" . ($entry['content'] ?? '');
    }

    /**
     * Wissenskategorie-Sektion zusammenbauen (alle Eintraege einer Kategorie)
     */
    private function buildKnowledgeCategorySection(array $item): string
    {
        if (!$item['reference_id']) {
            return '';
        }

        $category = $this->db->queryOne(
            "SELECT id, name FROM knowledge_categories WHERE id = ?",
            [$item['reference_id']]
        );

        if (!$category) {
            return '';
        }

        $entries = $this->db->query(
            "SELECT ke.title, ke.content FROM knowledge_entries ke
             WHERE ke.category_id = ?
             ORDER BY ke.title",
            [$item['reference_id']]
        );

        if (empty($entries)) {
            return '';
        }

        $parts = ["## Wissenskategorie: {$category['name']}"];
        foreach ($entries as $entry) {
            $title = $entry['title'] ?: 'Eintrag';
            $parts[] = "### {$title}\n" . ($entry['content'] ?? '');
        }

        return implode("\n\n", $parts);
    }

    /**
     * Autorenprofil-Sektion zusammenbauen
     */
    private function buildAuthorSection(array $item): string
    {
        if (!$item['reference_id']) {
            return '';
        }

        $author = $this->db->queryOne(
            "SELECT * FROM author_profiles WHERE id = ?",
            [$item['reference_id']]
        );

        if (!$author) {
            return '';
        }

        $parts = ["## Autorenprofil: {$author['name']}"];

        if (!empty($author['writing_style'])) {
            $parts[] = "Schreibstil: {$author['writing_style']}";
        }
        if (!empty($author['expertise'])) {
            $parts[] = "Fachgebiete: {$author['expertise']}";
        }
        if (!empty($author['tone'])) {
            $parts[] = "Tonalitaet: {$author['tone']}";
        }
        if (!empty($author['perspective'])) {
            $parts[] = "Perspektive: {$author['perspective']}";
        }
        if (!empty($author['notes'])) {
            $parts[] = "Hinweise: {$author['notes']}";
        }
        if (!empty($author['example_text'])) {
            $parts[] = "Beispieltext:\n{$author['example_text']}";
        }

        return implode("\n", $parts);
    }

    /**
     * Artefakte als Kontext-Text zusammenbauen (nach Typ gruppiert)
     */
    public function assembleArtifactsText(?int $customerId = null): string
    {
        $entityService = new \Services\EntityService($this->db);
        $artifactService = new \Services\ArtifactService($this->db, $entityService);

        $artifacts = $artifactService->findByScope($customerId);

        if (empty($artifacts)) {
            return '';
        }

        // Nach Typ gruppieren
        $grouped = [];
        foreach ($artifacts as $a) {
            $meta = json_decode($a['meta'], true) ?? [];
            $type = $meta['type'] ?? 'Sonstiges';
            $grouped[$type][] = $a;
        }

        // Typ-Prioritaet: Profil zuerst, dann Autor, Regel, Wissen, Rest
        $typePriority = ['Profil', 'Autor', 'Regel', 'Wissen', 'Namespace', 'Sonstiges'];
        uksort($grouped, function ($a, $b) use ($typePriority) {
            $ia = array_search($a, $typePriority);
            $ib = array_search($b, $typePriority);
            $ia = $ia === false ? 99 : $ia;
            $ib = $ib === false ? 99 : $ib;
            return $ia - $ib;
        });

        // Typ-spezifische Header
        $typeHeaders = [
            'Profil' => '## Profil (Leitbild)',
        ];

        $parts = ["# Artefakte"];
        foreach ($grouped as $type => $items) {
            $header = $typeHeaders[$type] ?? "## {$type}";
            $parts[] = "\n{$header}";
            foreach ($items as $a) {
                $meta = json_decode($a['meta'], true) ?? [];
                $name = $meta['name'] ?? $meta['title'] ?? $a['slug'];
                $resolved = $entityService->resolve($a['content'] ?? '');
                if ($resolved) {
                    $parts[] = "### {$name}\n{$resolved}";
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Regeln-Text zusammenbauen (aus Kontext-Items und Kunden-Regeln)
     */
    public function assembleRulesText(int $contextId, ?int $customerId = null): string
    {
        $ruleIds = $this->getContextRuleIds($contextId);

        // Regeln aus dem Kontext laden (einzelne + via Kategorie)
        $rules = [];
        if (!empty($ruleIds)) {
            $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
            $rules = $this->db->query(
                "SELECT r.*, rt.name as type_name, rt.slug as type_slug
                 FROM rules r
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 WHERE r.id IN ($placeholders) AND r.is_active = 1
                 ORDER BY r.priority DESC",
                $ruleIds
            );
        }

        // Regeln aus Regel-Kategorien laden
        $categoryRules = $this->getContextRuleCategoryRules($contextId);
        $existingIds = array_column($rules, 'id');
        foreach ($categoryRules as $cr) {
            if (!in_array($cr['id'], $existingIds)) {
                $rules[] = $cr;
                $existingIds[] = $cr['id'];
            }
        }

        // Globale + eigene Regeln des Kunden — abzgl. kundenspezifischer Overrides (deaktiviert)
        if ($customerId) {
            $customerRules = $this->db->query(
                "SELECT r.*, rt.name as type_name, rt.slug as type_slug
                 FROM rules r
                 LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
                 WHERE (r.customer_id = ? OR r.customer_id IS NULL)
                   AND r.is_active = 1
                   AND r.id NOT IN (SELECT rule_id FROM customer_rules WHERE customer_id = ? AND is_active = 0)
                 ORDER BY r.priority DESC",
                [$customerId, $customerId]
            );

            // Hinzufuegen ohne Duplikate
            foreach ($customerRules as $cr) {
                if (!in_array($cr['id'], $existingIds)) {
                    $rules[] = $cr;
                    $existingIds[] = $cr['id'];
                }
            }
        }

        if (empty($rules)) {
            return '';
        }

        // Nach Typ gruppieren
        $grouped = [];
        foreach ($rules as $rule) {
            $type = $rule['type_slug'] ?? $rule['rule_type'] ?? 'content';
            $grouped[$type][] = $rule;
        }

        $typeLabels = [
            'style' => 'Schreibstil',
            'format' => 'Formatierung',
            'content' => 'Inhaltliche Vorgaben',
            'link' => 'Link-Regeln',
            'tone' => 'Tonalitaet'
        ];

        $parts = ["# Regeln und Vorgaben"];
        foreach ($grouped as $type => $typeRules) {
            $label = $typeLabels[$type] ?? ucfirst($type);
            $parts[] = "\n## {$label}";
            foreach ($typeRules as $rule) {
                $parts[] = "- " . $rule['rule_content'];
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Regel-IDs aus dem Kontext extrahieren
     */
    public function getContextRuleIds(int $contextId): array
    {
        $items = $this->db->query(
            "SELECT reference_id FROM context_items
             WHERE context_id = ? AND item_type = 'rule' AND reference_id IS NOT NULL",
            [$contextId]
        );

        return array_map('intval', array_column($items, 'reference_id'));
    }

    /**
     * Regeln aus Regel-Kategorien im Kontext laden
     */
    private function getContextRuleCategoryRules(int $contextId): array
    {
        $catItems = $this->db->query(
            "SELECT reference_id FROM context_items
             WHERE context_id = ? AND item_type = 'rule_category' AND reference_id IS NOT NULL",
            [$contextId]
        );

        if (empty($catItems)) {
            return [];
        }

        $catIds = array_map('intval', array_column($catItems, 'reference_id'));
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));

        return $this->db->query(
            "SELECT r.*, rt.name as type_name, rt.slug as type_slug
             FROM rules r
             LEFT JOIN rule_types rt ON r.rule_type_id = rt.id
             WHERE r.category_id IN ($placeholders) AND r.is_active = 1
             ORDER BY r.priority DESC",
            $catIds
        );
    }

    /**
     * Kontext mit allen Items laden
     */
    public function getContextWithItems(int $contextId): ?array
    {
        $context = $this->db->queryOne(
            "SELECT c.*, cu.name as customer_name, u.name as creator_name
             FROM contexts c
             LEFT JOIN customers cu ON c.customer_id = cu.id
             JOIN users u ON c.created_by = u.id
             WHERE c.id = ?",
            [$contextId]
        );

        if (!$context) {
            return null;
        }

        $context['items'] = $this->db->query(
            "SELECT * FROM context_items WHERE context_id = ? ORDER BY sort_order, id",
            [$contextId]
        );

        // Item-Details anreichern
        foreach ($context['items'] as &$item) {
            switch ($item['item_type']) {
                case 'customer':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, name FROM customers WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['name'] ?? null;
                    }
                    break;
                case 'knowledge':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, title FROM knowledge_entries WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['title'] ?? null;
                    }
                    break;
                case 'rule':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, name FROM rules WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['name'] ?? null;
                    }
                    break;
                case 'rule_category':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, name FROM rule_categories WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['name'] ?? null;
                        $item['rules_count'] = (int) $this->db->queryValue(
                            "SELECT COUNT(*) FROM rules WHERE category_id = ? AND is_active = 1",
                            [$item['reference_id']]
                        );
                    }
                    break;
                case 'knowledge_category':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, name FROM knowledge_categories WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['name'] ?? null;
                        $item['entries_count'] = (int) $this->db->queryValue(
                            "SELECT COUNT(*) FROM knowledge_entries WHERE category_id = ? ",
                            [$item['reference_id']]
                        );
                    }
                    break;
                case 'author':
                    if ($item['reference_id']) {
                        $ref = $this->db->queryOne("SELECT id, name FROM author_profiles WHERE id = ?", [$item['reference_id']]);
                        $item['reference_name'] = $ref['name'] ?? null;
                    }
                    break;
            }
        }

        return $context;
    }

    /**
     * Kontext erstellen mit Items
     */
    public function createContext(array $data, array $items = []): int
    {
        $this->db->beginTransaction();

        try {
            $contextId = $this->db->insert('contexts', [
                'customer_id' => $data['customer_id'] ?? null,
                'created_by' => $data['created_by'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null
            ]);

            foreach ($items as $index => $item) {
                $this->db->insert('context_items', [
                    'context_id' => $contextId,
                    'item_type' => $item['item_type'],
                    'reference_id' => $item['reference_id'] ?? null,
                    'content' => $item['content'] ?? null,
                    'title' => $item['title'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index
                ]);
            }

            $this->db->commit();
            return $contextId;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Kontext aktualisieren mit Items
     */
    public function updateContext(int $contextId, array $data, ?array $items = null): void
    {
        $this->db->beginTransaction();

        try {
            $updates = [];
            if (isset($data['name'])) $updates['name'] = $data['name'];
            if (isset($data['description'])) $updates['description'] = $data['description'];
            if (isset($data['is_active'])) $updates['is_active'] = (int) $data['is_active'];

            if (!empty($updates)) {
                $this->db->update('contexts', $updates, 'id = ?', [$contextId]);
            }

            // Items ersetzen wenn angegeben
            if ($items !== null) {
                $this->db->delete('context_items', 'context_id = ?', [$contextId]);

                foreach ($items as $index => $item) {
                    $this->db->insert('context_items', [
                        'context_id' => $contextId,
                        'item_type' => $item['item_type'],
                        'reference_id' => $item['reference_id'] ?? null,
                        'content' => $item['content'] ?? null,
                        'title' => $item['title'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $index
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
