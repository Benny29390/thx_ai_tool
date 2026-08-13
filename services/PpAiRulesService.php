<?php
namespace Services;

use Core\Database;

/**
 * PpAiRulesService — Adapter fuer die Projektplanner-Regeln der KI-Anreicherung.
 *
 * Die Regeln leben im GETEILTEN rules-System (Typ-Slug „projektplanner"),
 * kundengescoped ueber rules.customer_id (NULL = global). Damit sind sie unter
 * /rules?scope={Kunde} sichtbar, editier- und erweiterbar. Dieser Service kapselt
 * nur die Projektplanner-spezifischen Zugriffe (Lesen fuer den Prompt, Anlegen aus
 * „passt nicht", Aktivieren/Bearbeiten/Loeschen im in-Planner-Modal).
 *
 * Status-Mapping fuer das Modal:
 *   is_active=1                          -> „aktiv"
 *   is_active=0 & source ai_suggested    -> „vorschlag" (review-to-activate)
 *   is_active=0 & manuell                -> „aktiv" (nur inaktiv geschaltet)
 */
class PpAiRulesService
{
    private const TYPE_SLUG = 'projektplanner';

    private Database $db;
    private ?int $typeId = null;

    public function __construct(Database $db) { $this->db = $db; }

    private function typeId(): int
    {
        if ($this->typeId === null) {
            $this->typeId = (int) $this->db->queryValue("SELECT id FROM rule_types WHERE slug = ?", [self::TYPE_SLUG]);
        }
        return $this->typeId;
    }

    /** WHERE-Fragment, das nur Projektplanner-Regeln trifft. */
    private function typeWhere(): string
    {
        return "(r.rule_type = '" . self::TYPE_SLUG . "' OR r.rule_type_id = " . $this->typeId() . ")";
    }

    /** Liste fuer den Kunden (inkl. global) — aufs Modal-Format gemappt. */
    public function listForCustomer(int $customerId): array
    {
        $rows = $this->db->query(
            "SELECT r.id, r.customer_id, r.rule_content, r.is_active, r.source
             FROM rules r
             WHERE " . $this->typeWhere() . "
               AND (r.customer_id = ? OR r.customer_id IS NULL)
             ORDER BY (r.is_active = 0 AND r.source IN ('ai_suggested','ai_learning')) ASC,
                      (r.customer_id IS NULL) ASC, r.priority DESC, r.id DESC",
            [$customerId]
        ) ?: [];
        return array_map(function ($r) {
            $isSug = ((int) $r['is_active'] === 0) && in_array($r['source'], ['ai_suggested', 'ai_learning'], true);
            return [
                'id'          => (int) $r['id'],
                'customer_id' => $r['customer_id'] !== null ? (int) $r['customer_id'] : null,
                'rule_text'   => (string) $r['rule_content'],
                'is_active'   => (int) $r['is_active'],
                'status'      => $isSug ? 'vorschlag' : 'aktiv',
                'source'      => (strpos((string) $r['source'], 'ai_') === 0) ? 'feedback' : 'manuell',
            ];
        }, $rows);
    }

    /** Formatierter Text der AKTIVEN Regeln fuer den Prompt (leer, wenn keine). */
    public function activeRulesText(int $customerId): string
    {
        $rows = $this->db->query(
            "SELECT r.rule_content FROM rules r
             WHERE " . $this->typeWhere() . "
               AND r.is_active = 1
               AND (r.customer_id = ? OR r.customer_id IS NULL)
             ORDER BY (r.customer_id IS NULL) ASC, r.priority DESC, r.id ASC",
            [$customerId]
        ) ?: [];
        if (!$rows) return '';
        return implode("\n", array_map(fn($r) => '- ' . trim((string) $r['rule_content']), $rows));
    }

    public function add(?int $customerId, string $ruleText, string $status, string $source, int $userId): int
    {
        $ruleText = trim($ruleText);
        if ($ruleText === '') throw new \RuntimeException('Regeltext ist leer.');
        $isSuggestion = ($status === 'vorschlag');
        return (int) $this->db->insert('rules', [
            'customer_id'  => $customerId ?: null,
            'name'         => mb_substr($ruleText, 0, 60),
            'rule_type'    => self::TYPE_SLUG,
            'rule_type_id' => $this->typeId() ?: null,
            'rule_content' => mb_substr($ruleText, 0, 500),
            'source'       => $isSuggestion ? 'ai_suggested' : ($source === 'feedback' ? 'ai_approved' : 'manual'),
            'is_active'    => $isSuggestion ? 0 : 1,
            'priority'     => 0,
            'enforcement'  => 'soft',
            'applies_to'   => 'tool',
            'created_by'   => $userId ?: null,
        ]);
    }

    public function update(int $id, array $fields): void
    {
        $row = $this->db->queryOne("SELECT id, rule_type, rule_type_id, source FROM rules WHERE id = ?", [$id]);
        if (!$row) return;
        // Schutz: nur Projektplanner-Regeln ueber diesen Service aendern.
        if ($row['rule_type'] !== self::TYPE_SLUG && (int) $row['rule_type_id'] !== $this->typeId()) {
            throw new \RuntimeException('Regel gehört nicht zum Projektplanner.');
        }
        $update = [];
        if (array_key_exists('rule_text', $fields)) {
            $t = trim((string) $fields['rule_text']);
            if ($t !== '') { $update['rule_content'] = mb_substr($t, 0, 500); $update['name'] = mb_substr($t, 0, 60); }
        }
        if (array_key_exists('is_active', $fields)) $update['is_active'] = !empty($fields['is_active']) ? 1 : 0;
        if (($fields['status'] ?? '') === 'aktiv') {
            $update['is_active'] = 1;
            if ($row['source'] === 'ai_suggested') $update['source'] = 'ai_approved';
        }
        if (empty($update)) return;
        $this->db->update('rules', $update, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $row = $this->db->queryOne("SELECT id, rule_type, rule_type_id FROM rules WHERE id = ?", [$id]);
        if (!$row) return;
        if ($row['rule_type'] !== self::TYPE_SLUG && (int) $row['rule_type_id'] !== $this->typeId()) {
            throw new \RuntimeException('Regel gehört nicht zum Projektplanner.');
        }
        $this->db->execute('DELETE FROM rules WHERE id = ?', [$id]);
    }
}
