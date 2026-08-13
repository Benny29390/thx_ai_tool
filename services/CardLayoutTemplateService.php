<?php

namespace Services;

use Core\Database;

/**
 * Karten-Layout-Templates: Snapshot eines Steckbrief-Layouts (welche Karten,
 * wo, in welcher Reihenfolge, Spalte, Spalten-Span) — anwendbar auf andere
 * Kunden, wobei vorhandene Daten erhalten bleiben.
 */
class CardLayoutTemplateService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Liste aller Templates (fuer Picker).
     */
    public function list(): array
    {
        return $this->db->query(
            "SELECT t.*, c.name AS source_customer_name,
                    (SELECT COUNT(*) FROM card_layout_template_items WHERE template_id = t.id) AS item_count
             FROM card_layout_templates t
             LEFT JOIN customers c ON c.id = t.source_customer_id
             ORDER BY t.updated_at DESC"
        ) ?: [];
    }

    public function get(int $id): ?array
    {
        $t = $this->db->queryOne("SELECT * FROM card_layout_templates WHERE id = ?", [$id]);
        if (!$t) return null;
        $t['items'] = $this->db->query(
            "SELECT * FROM card_layout_template_items WHERE template_id = ? ORDER BY position_order ASC, id ASC",
            [$id]
        ) ?: [];
        return $t;
    }

    /**
     * Snapshot der aktuellen Layout-Konfiguration eines Kunden als Template.
     */
    public function createFromCustomer(int $customerId, string $name, ?string $description, int $userId): int
    {
        $templateId = $this->db->insert('card_layout_templates', [
            'name' => trim($name) !== '' ? $name : 'Layout',
            'description' => $description,
            'source_customer_id' => $customerId,
            'created_by' => $userId,
        ]);

        $cards = $this->db->query(
            "SELECT id, type, system_key, title, target_tab, column_idx, size_w
             FROM customer_cards
             WHERE customer_id = ?
             ORDER BY target_tab, column_idx, sort_order, id",
            [$customerId]
        ) ?: [];

        $order = 10;
        foreach ($cards as $c) {
            $this->db->insert('card_layout_template_items', [
                'template_id' => $templateId,
                'card_type' => $c['type'],
                'system_key' => $c['system_key'] ?: null,
                'title_hint' => (string) ($c['title'] ?? ''),
                'target_tab' => $c['target_tab'],
                'column_idx' => max(1, min(3, (int) ($c['column_idx'] ?? 2))),
                'size_w' => max(1, min(3, (int) ($c['size_w'] ?? 1))),
                'position_order' => $order,
            ]);
            $order += 10;
        }

        return $templateId;
    }

    /**
     * Wendet ein Template auf einen Kunden an. Strategie:
     * - System-Cards werden ueber system_key gematched (per Kunde existiert
     *   jede System-Card genau einmal).
     * - User-Cards werden ueber type+title gematched (greedy, in Reihenfolge
     *   der Template-Items).
     * - Bereits zugeordnete Cards des Kunden bekommen target_tab/column/size
     *   aus dem Template, sort_order wird aus position_order vergeben.
     * - Template-Items, fuer die der Kunde keine Card hat → neue leere Card.
     * - Cards des Kunden, die KEIN Template-Item zugeordnet werden konnte,
     *   wandern nach target_tab='sonstiges'.
     */
    public function applyToCustomer(int $customerId, int $templateId): array
    {
        $template = $this->get($templateId);
        if (!$template) throw new \RuntimeException('Template nicht gefunden');

        // Bestand des Kunden in matchable-Pools sortieren
        $cards = $this->db->query(
            "SELECT * FROM customer_cards WHERE customer_id = ?",
            [$customerId]
        ) ?: [];

        $systemByKey = [];          // system_key => row
        $userByType = [];           // type => array of rows
        foreach ($cards as $c) {
            if (!empty($c['is_system']) && !empty($c['system_key'])) {
                $systemByKey[$c['system_key']] = $c;
            } elseif (empty($c['is_system'])) {
                $userByType[$c['type']] = $userByType[$c['type']] ?? [];
                $userByType[$c['type']][] = $c;
            }
        }

        $assigned = [];
        $stats = ['matched_system' => 0, 'matched_user' => 0, 'created' => 0, 'orphaned' => 0];

        $sortOrder = 10;
        foreach ($template['items'] as $item) {
            $matchedCard = null;
            if (!empty($item['system_key'])) {
                if (isset($systemByKey[$item['system_key']])) {
                    $matchedCard = $systemByKey[$item['system_key']];
                    unset($systemByKey[$item['system_key']]);
                    $stats['matched_system']++;
                }
            } else {
                $pool = $userByType[$item['card_type']] ?? [];
                // Prefer Title-Match
                $bestIdx = null;
                foreach ($pool as $idx => $cand) {
                    if (trim($cand['title'] ?? '') === trim($item['title_hint'])) {
                        $bestIdx = $idx;
                        break;
                    }
                }
                if ($bestIdx === null && count($pool) > 0) $bestIdx = 0;
                if ($bestIdx !== null) {
                    $matchedCard = $pool[$bestIdx];
                    array_splice($userByType[$item['card_type']], $bestIdx, 1);
                    $stats['matched_user']++;
                }
            }

            if ($matchedCard) {
                // Bestehende Card: Layout aktualisieren, Daten bleiben.
                // Aber: Karten mit angehaengten Files bleiben IMMER im Dateien-Tab,
                // damit die Files findbar bleiben (auch wenn das Template die
                // Karte sonst woanders haette platziert).
                $targetTab = $item['target_tab'];
                if (in_array($matchedCard['type'], ['documents','images'], true)) {
                    $fileCount = (int) $this->db->queryValue(
                        "SELECT COUNT(*) FROM customer_card_files WHERE card_id = ?",
                        [(int) $matchedCard['id']]
                    );
                    if ($fileCount > 0 && $targetTab !== 'dateien') {
                        $targetTab = 'dateien';
                    }
                }
                $this->db->update('customer_cards', [
                    'target_tab' => $targetTab,
                    'column_idx' => $item['column_idx'],
                    'size_w' => $item['size_w'],
                    'sort_order' => $sortOrder,
                ], 'id = ?', [(int) $matchedCard['id']]);
                $assigned[] = (int) $matchedCard['id'];
            } else {
                // Neue leere Card anlegen
                if (!empty($item['system_key'])) {
                    // System-Cards werden separat erzeugt (ensureSystemCards)
                    // Falls noch nicht vorhanden, wird das vom CustomerCardService gemacht
                    continue;
                }
                $newId = $this->db->insert('customer_cards', [
                    'customer_id' => $customerId,
                    'type' => $item['card_type'],
                    'title' => $item['title_hint'] ?: $this->defaultTitle($item['card_type']),
                    'body' => json_encode($this->defaultBody($item['card_type'])),
                    'sort_order' => $sortOrder,
                    'is_collapsed' => 0,
                    'size_w' => $item['size_w'],
                    'target_tab' => $item['target_tab'],
                    'column_idx' => $item['column_idx'],
                    'is_system' => 0,
                ]);
                $assigned[] = $newId;
                $stats['created']++;
            }
            $sortOrder += 10;
        }

        // Nicht-assignierte Karten des Kunden → Sonstiges-Tab
        foreach ($userByType as $type => $pool) {
            foreach ($pool as $c) {
                $this->db->update('customer_cards', [
                    'target_tab' => 'sonstiges',
                    'column_idx' => 1,
                ], 'id = ?', [(int) $c['id']]);
                $stats['orphaned']++;
            }
        }
        // Verbleibende System-Cards bleiben in ihrem aktuellen Tab — System-Karten
        // sollen nicht verloren gehen, der User entscheidet selbst.

        return $stats;
    }

    public function delete(int $templateId): void
    {
        $this->db->delete('card_layout_templates', 'id = ?', [$templateId]);
    }

    private function defaultBody(string $type): array
    {
        return match ($type) {
            'links' => ['items' => []],
            'richtext' => ['html' => ''],
            'documents' => ['note' => ''],
            'images' => ['note' => ''],
            'brand' => ['colors' => [], 'fonts' => [], 'note' => ''],
            'contacts' => ['groups' => []],
            'kpi' => ['items' => []],
            'tracking_status' => ['items' => []],
            default => [],
        };
    }

    private function defaultTitle(string $type): string
    {
        return match ($type) {
            'links' => 'Links',
            'richtext' => 'Notiz',
            'documents' => 'Dokumente',
            'images' => 'Bilder',
            'brand' => 'Markenidentität',
            'contacts' => 'Ansprechpartner',
            'kpi' => 'Kennzahlen',
            'tracking_status' => 'Tracking-Status',
            default => 'Karte',
        };
    }
}
