<?php
/**
 * Guideline Service — kundenübergreifende Verhaltensvorgaben für die KI.
 *
 * Drei Kategorien:
 *  - tool_communication  : wie redet die KI mit dem Tool-Nutzer
 *  - content_output      : wie sind erzeugte Texte / Artefakte
 *  - internal            : Sprach- und Format-Standards (immer mitgeben)
 *
 * Wird deterministisch ins System-Prompt eingehängt (nicht via RAG).
 */

namespace Services;

use Core\Database;

class GuidelineService
{
    public const CATEGORIES = [
        'tool_communication' => 'Tool-Kommunikation',
        'content_output' => 'Content & Output',
        'internal' => 'Sprache & Format',
    ];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Liste aller Guidelines, optional gefiltert.
     */
    public function list(?string $category = null, bool $onlyActive = false): array
    {
        $sql = "SELECT g.*, u.name as creator_name
                FROM guidelines g
                LEFT JOIN users u ON u.id = g.created_by
                WHERE 1=1";
        $params = [];
        if ($category !== null) {
            if (!isset(self::CATEGORIES[$category])) return [];
            $sql .= " AND g.category = ?";
            $params[] = $category;
        }
        if ($onlyActive) {
            $sql .= " AND g.is_active = 1";
        }
        $sql .= " ORDER BY g.category, g.sort_order, g.id";
        return $this->db->query($sql, $params);
    }

    public function get(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM guidelines WHERE id = ?", [$id]);
    }

    public function create(array $data, int $userId): int
    {
        $category = $data['category'] ?? '';
        if (!isset(self::CATEGORIES[$category])) {
            throw new \InvalidArgumentException('Ungültige Kategorie');
        }
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        if ($title === '' || $content === '') {
            throw new \InvalidArgumentException('Titel und Inhalt erforderlich');
        }

        // sort_order: ans Ende der Kategorie
        $maxSort = (int) $this->db->queryValue(
            "SELECT COALESCE(MAX(sort_order), 0) FROM guidelines WHERE category = ?",
            [$category]
        );

        return $this->db->insert('guidelines', [
            'category' => $category,
            'title' => $title,
            'content' => $content,
            'is_active' => isset($data['is_active']) ? (int) !empty($data['is_active']) : 1,
            'sort_order' => $maxSort + 10,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function update(int $id, array $data, int $userId): void
    {
        $current = $this->get($id);
        if (!$current) throw new \RuntimeException('Guideline nicht gefunden');

        $update = ['updated_by' => $userId];

        if (isset($data['category'])) {
            if (!isset(self::CATEGORIES[$data['category']])) {
                throw new \InvalidArgumentException('Ungültige Kategorie');
            }
            $update['category'] = $data['category'];
        }
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if ($title === '') throw new \InvalidArgumentException('Titel darf nicht leer sein');
            $update['title'] = $title;
        }
        if (isset($data['content'])) {
            $content = trim($data['content']);
            if ($content === '') throw new \InvalidArgumentException('Inhalt darf nicht leer sein');
            $update['content'] = $content;
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) !empty($data['is_active']);
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }

        $this->db->update('guidelines', $update, 'id = ?', [$id]);
    }

    public function toggle(int $id, bool $active): void
    {
        $this->db->update('guidelines', ['is_active' => $active ? 1 : 0], 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('guidelines', 'id = ?', [$id]);
    }

    /**
     * Reorder innerhalb einer Kategorie.
     * @param array $idsInOrder Liste der IDs in gewünschter Reihenfolge
     */
    public function reorder(array $idsInOrder): void
    {
        $sort = 10;
        foreach ($idsInOrder as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $this->db->update('guidelines', ['sort_order' => $sort], 'id = ?', [$id]);
            $sort += 10;
        }
    }

    /**
     * Liefert formatierten Block für System-Prompt.
     * Aktive Guidelines der angegebenen Kategorien werden gruppiert ausgegeben.
     */
    public function buildPromptBlock(array $categories): string
    {
        $categories = array_filter($categories, fn($c) => isset(self::CATEGORIES[$c]));
        if (empty($categories)) return '';

        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $rows = $this->db->query(
            "SELECT category, title, content
             FROM guidelines
             WHERE is_active = 1 AND category IN ({$placeholders})
             ORDER BY FIELD(category, {$placeholders}), sort_order, id",
            array_merge(array_values($categories), array_values($categories))
        );

        if (empty($rows)) return '';

        // Gruppieren
        $byCategory = [];
        foreach ($rows as $r) {
            $byCategory[$r['category']][] = $r;
        }

        $out = "GUIDELINES — diese sind IMMER einzuhalten:\n";
        foreach ($categories as $cat) {
            if (empty($byCategory[$cat])) continue;
            $label = self::CATEGORIES[$cat] ?? $cat;
            $out .= "\n[{$label}]\n";
            foreach ($byCategory[$cat] as $g) {
                $out .= "- " . trim($g['content']) . "\n";
            }
        }
        return rtrim($out);
    }
}
