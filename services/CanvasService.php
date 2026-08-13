<?php
/**
 * Canvas Service — CRUD fuer Projekte, Karten, Teilnehmer, Referenzen, Versionen
 */

namespace Services;

use Core\Database;

class CanvasService
{
    private Database $db;

    // Die 8 Canvas-Felder
    public const FIELDS = [
        'problem', 'loesung', 'input', 'magie',
        'qualitaetssicherung', 'output', 'ergebnisse', 'risiken'
    ];

    public const FIELD_LABELS = [
        'problem' => 'Problem',
        'loesung' => 'Loesung',
        'input' => 'Input',
        'magie' => 'Magie',
        'qualitaetssicherung' => 'Qualitaetssicherung',
        'output' => 'Output',
        'ergebnisse' => 'Ergebnisse',
        'risiken' => 'Risiken',
    ];

    public const FIELD_ICONS = [
        'problem' => 'report_problem',
        'loesung' => 'lightbulb',
        'input' => 'input',
        'magie' => 'auto_awesome',
        'qualitaetssicherung' => 'verified',
        'output' => 'output',
        'ergebnisse' => 'emoji_events',
        'risiken' => 'warning',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ===== PROJEKTE =====

    public function listProjects(int $userId, ?int $customerId = null, bool $includeArchived = false): array
    {
        $sql = "SELECT DISTINCT p.*, u.name as creator_name,
                    (SELECT COUNT(*) FROM canvas_cards WHERE canvas_id = p.id) as card_count
                FROM canvas_projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN canvas_participants cp ON cp.canvas_id = p.id
                WHERE (p.created_by = ? OR cp.user_id = ?)";
        $params = [$userId, $userId];

        if (!$includeArchived) {
            $sql .= " AND p.status != 'archived'";
        }

        if ($customerId) {
            $sql .= " AND p.customer_id = ?";
            $params[] = $customerId;
        }

        $sql .= " ORDER BY p.updated_at DESC";

        return $this->db->query($sql, $params);
    }

    public function getProject(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM canvas_projects WHERE id = ?", [$id]);
    }

    public function getProjectWithDetails(int $id): ?array
    {
        $project = $this->getProject($id);
        if (!$project) return null;

        $project['cards'] = $this->getCardsByCanvas($id);
        $project['participants'] = $this->getParticipants($id);
        $project['completeness'] = $this->calculateCompleteness($id);

        return $project;
    }

    public function createProject(array $data, int $userId): int
    {
        $id = $this->db->insert('canvas_projects', [
            'customer_id' => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        // Creator als Auftraggeber hinzufuegen
        $this->db->insert('canvas_participants', [
            'canvas_id' => $id,
            'user_id' => $userId,
            'canvas_role' => 'auftraggeber',
        ]);

        return $id;
    }

    public function updateProject(int $id, array $data, int $userId): void
    {
        $update = ['updated_by' => $userId];
        if (isset($data['title'])) $update['title'] = $data['title'];
        if (isset($data['description'])) $update['description'] = $data['description'];
        if (isset($data['status'])) $update['status'] = $data['status'];

        $this->db->update('canvas_projects', $update, 'id = ?', [$id]);
    }

    public function archiveProject(int $id, int $userId): void
    {
        $this->db->update('canvas_projects', [
            'status' => 'archived',
            'updated_by' => $userId,
        ], 'id = ?', [$id]);
    }

    public function unarchiveProject(int $id, int $userId): void
    {
        $this->db->update('canvas_projects', [
            'status' => 'active',
            'updated_by' => $userId,
        ], 'id = ?', [$id]);
    }

    public function deleteProject(int $id): void
    {
        // Harte Loeschung — manueller Cleanup, da keine FK-Constraints auf canvas_projects gesetzt
        try {
            // Card-abhaengige Tabellen ueber JOIN ableiten
            $this->db->execute(
                "DELETE cv FROM canvas_card_versions cv
                 JOIN canvas_cards c ON cv.card_id = c.id
                 WHERE c.canvas_id = ?",
                [$id]
            );
            $this->db->execute(
                "DELETE cr FROM canvas_card_references cr
                 JOIN canvas_cards c ON cr.source_card_id = c.id OR cr.target_card_id = c.id
                 WHERE c.canvas_id = ?",
                [$id]
            );
        } catch (\Exception $e) {
            error_log('Canvas delete card-deps: ' . $e->getMessage());
        }

        $simpleTables = [
            'canvas_cards' => 'canvas_id',
            'canvas_participants' => 'canvas_id',
            'canvas_ai_messages' => 'canvas_id',
        ];
        foreach ($simpleTables as $table => $col) {
            try {
                $this->db->execute("DELETE FROM {$table} WHERE {$col} = ?", [$id]);
            } catch (\Exception $e) {
                error_log("Canvas delete: skip {$table}: " . $e->getMessage());
            }
        }
        $this->db->delete('canvas_projects', 'id = ?', [$id]);
    }

    public function canAccess(int $canvasId, int $userId): bool
    {
        $project = $this->getProject($canvasId);
        if (!$project) return false;
        if ($project['created_by'] === $userId) return true;

        $participant = $this->db->queryOne(
            "SELECT id FROM canvas_participants WHERE canvas_id = ? AND user_id = ?",
            [$canvasId, $userId]
        );
        return $participant !== null;
    }

    // ===== KARTEN =====

    public function getCardsByCanvas(int $canvasId): array
    {
        $cards = $this->db->query(
            "SELECT c.*, u.name as creator_name
             FROM canvas_cards c
             LEFT JOIN users u ON c.created_by = u.id
             WHERE c.canvas_id = ?
             ORDER BY c.field, c.sort_order, c.id",
            [$canvasId]
        );

        // Nach Feld gruppieren
        $grouped = [];
        foreach (self::FIELDS as $field) {
            $grouped[$field] = [];
        }
        foreach ($cards as $card) {
            $grouped[$card['field']][] = $card;
        }

        return $grouped;
    }

    public function getCard(int $id): ?array
    {
        return $this->db->queryOne(
            "SELECT c.*, u.name as creator_name
             FROM canvas_cards c
             LEFT JOIN users u ON c.created_by = u.id
             WHERE c.id = ?",
            [$id]
        );
    }

    public function createCard(array $data, int $userId): int
    {
        // Naechste sort_order
        $maxSort = $this->db->queryOne(
            "SELECT MAX(sort_order) as max_sort FROM canvas_cards WHERE canvas_id = ? AND field = ?",
            [$data['canvas_id'], $data['field']]
        );

        $id = $this->db->insert('canvas_cards', [
            'canvas_id' => (int) $data['canvas_id'],
            'field' => $data['field'],
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'status' => $data['status'] ?? 'entwurf',
            'sort_order' => ($maxSort['max_sort'] ?? 0) + 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        // Erste Version anlegen
        $this->createCardVersion($id, $data['title'], $data['content'] ?? null, $data['status'] ?? 'entwurf', $userId);

        // Completeness aktualisieren
        $this->updateBriefingReadiness((int) $data['canvas_id']);

        return $id;
    }

    public function updateCard(int $id, array $data, int $userId): void
    {
        $card = $this->getCard($id);
        if (!$card) return;

        $update = ['updated_by' => $userId];
        if (isset($data['title'])) $update['title'] = $data['title'];
        if (isset($data['content'])) $update['content'] = $data['content'];
        if (isset($data['status'])) $update['status'] = $data['status'];

        $this->db->update('canvas_cards', $update, 'id = ?', [$id]);

        // Version anlegen
        $this->createCardVersion(
            $id,
            $data['title'] ?? $card['title'],
            $data['content'] ?? $card['content'],
            $data['status'] ?? $card['status'],
            $userId
        );

        // Completeness aktualisieren
        $this->updateBriefingReadiness($card['canvas_id']);
    }

    public function updateCardStatus(int $id, string $status, int $userId): void
    {
        $card = $this->getCard($id);
        if (!$card) return;

        $this->db->update('canvas_cards', [
            'status' => $status,
            'updated_by' => $userId,
        ], 'id = ?', [$id]);

        $this->createCardVersion($id, $card['title'], $card['content'], $status, $userId);
        $this->updateBriefingReadiness($card['canvas_id']);
    }

    public function deleteCard(int $id): void
    {
        $card = $this->getCard($id);
        if (!$card) return;

        $this->db->delete('canvas_cards', 'id = ?', [$id]);
        $this->db->delete('canvas_card_versions', 'card_id = ?', [$id]);
        $this->db->delete('canvas_card_references', 'source_card_id = ? OR target_card_id = ?', [$id, $id]);

        $this->updateBriefingReadiness($card['canvas_id']);
    }

    // ===== VERSIONEN =====

    private function createCardVersion(int $cardId, string $title, ?string $content, string $status, int $userId): void
    {
        $this->db->insert('canvas_card_versions', [
            'card_id' => $cardId,
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'changed_by' => $userId,
        ]);
    }

    public function getCardVersions(int $cardId): array
    {
        return $this->db->query(
            "SELECT v.*, u.name as changed_by_name
             FROM canvas_card_versions v
             LEFT JOIN users u ON v.changed_by = u.id
             WHERE v.card_id = ?
             ORDER BY v.created_at DESC",
            [$cardId]
        );
    }

    // ===== TEILNEHMER =====

    public function getParticipants(int $canvasId): array
    {
        return $this->db->query(
            "SELECT cp.*, u.name, u.email
             FROM canvas_participants cp
             JOIN users u ON cp.user_id = u.id
             WHERE cp.canvas_id = ?
             ORDER BY cp.joined_at",
            [$canvasId]
        );
    }

    public function addParticipant(int $canvasId, int $userId, string $role): int
    {
        return $this->db->insert('canvas_participants', [
            'canvas_id' => $canvasId,
            'user_id' => $userId,
            'canvas_role' => $role,
        ]);
    }

    public function removeParticipant(int $id): void
    {
        $this->db->delete('canvas_participants', 'id = ?', [$id]);
    }

    // ===== REFERENZEN =====

    public function getReferences(int $canvasId): array
    {
        return $this->db->query(
            "SELECT r.*, sc.title as source_title, sc.field as source_field,
                    tc.title as target_title, tc.field as target_field
             FROM canvas_card_references r
             JOIN canvas_cards sc ON r.source_card_id = sc.id
             JOIN canvas_cards tc ON r.target_card_id = tc.id
             WHERE sc.canvas_id = ? OR tc.canvas_id = ?
             ORDER BY r.created_at DESC",
            [$canvasId, $canvasId]
        );
    }

    public function createReference(array $data, int $userId): int
    {
        return $this->db->insert('canvas_card_references', [
            'source_card_id' => (int) $data['source_card_id'],
            'target_card_id' => (int) $data['target_card_id'],
            'reference_type' => $data['reference_type'] ?? 'relates_to',
            'note' => $data['note'] ?? null,
            'created_by' => $userId,
        ]);
    }

    public function deleteReference(int $id): void
    {
        $this->db->delete('canvas_card_references', 'id = ?', [$id]);
    }

    public function getCardReferences(int $cardId): array
    {
        return $this->db->query(
            "SELECT r.*, sc.title as source_title, sc.field as source_field,
                    tc.title as target_title, tc.field as target_field
             FROM canvas_card_references r
             JOIN canvas_cards sc ON r.source_card_id = sc.id
             JOIN canvas_cards tc ON r.target_card_id = tc.id
             WHERE r.source_card_id = ? OR r.target_card_id = ?",
            [$cardId, $cardId]
        );
    }

    // ===== VOLLSTAENDIGKEIT =====

    public function calculateCompleteness(int $canvasId): array
    {
        $cards = $this->db->query(
            "SELECT field, status FROM canvas_cards WHERE canvas_id = ?",
            [$canvasId]
        );

        $fieldScores = [];
        foreach (self::FIELDS as $field) {
            $fieldScores[$field] = ['score' => 0, 'cards' => 0, 'status' => 'empty'];
        }

        foreach ($cards as $card) {
            $weight = match($card['status']) {
                'entwurf' => 0,
                'in_arbeit' => 0.5,
                'vollstaendig' => 1.0,
                default => 0,
            };
            $fieldScores[$card['field']]['cards']++;
            $fieldScores[$card['field']]['score'] += $weight;
        }

        $totalScore = 0;
        $filledFields = 0;

        foreach ($fieldScores as $field => &$info) {
            if ($info['cards'] > 0) {
                $info['percent'] = round(($info['score'] / $info['cards']) * 100);
                $filledFields++;
            } else {
                $info['percent'] = 0;
            }

            if ($info['percent'] >= 100) {
                $info['status'] = 'complete';
            } elseif ($info['percent'] > 0) {
                $info['status'] = 'partial';
            } else {
                $info['status'] = 'empty';
            }

            $totalScore += $info['percent'];
        }
        unset($info);

        $globalScore = count(self::FIELDS) > 0 ? round($totalScore / count(self::FIELDS)) : 0;

        // Export-Berechtigung pruefen
        $canExport = true;
        foreach (self::FIELDS as $field) {
            if ($fieldScores[$field]['cards'] === 0 || $fieldScores[$field]['percent'] === 0) {
                $canExport = false;
                break;
            }
        }
        // Problem, Loesung, Input muessen vollstaendig sein
        foreach (['problem', 'loesung', 'input'] as $required) {
            if (($fieldScores[$required]['percent'] ?? 0) < 100) {
                $canExport = false;
            }
        }

        return [
            'fields' => $fieldScores,
            'global' => $globalScore,
            'can_export' => $canExport,
        ];
    }

    private function updateBriefingReadiness(int $canvasId): void
    {
        $completeness = $this->calculateCompleteness($canvasId);
        $this->db->update('canvas_projects', [
            'briefing_readiness' => $completeness['global'],
        ], 'id = ?', [$canvasId]);
    }

    // ===== AI MESSAGES =====

    public function getAIMessages(int $canvasId, string $field, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT * FROM canvas_ai_messages
             WHERE canvas_id = ? AND field = ?
             ORDER BY created_at ASC
             LIMIT ?",
            [$canvasId, $field, $limit]
        );
    }

    public function getAllAIMessages(int $canvasId, int $limit = 50): array
    {
        return $this->db->query(
            "SELECT * FROM canvas_ai_messages
             WHERE canvas_id = ?
             ORDER BY created_at ASC
             LIMIT ?",
            [$canvasId, $limit]
        );
    }

    public function saveAIMessage(int $canvasId, string $field, string $role, string $content, ?int $userId = null, ?string $model = null, int $tokensIn = 0, int $tokensOut = 0): int
    {
        return $this->db->insert('canvas_ai_messages', [
            'canvas_id' => $canvasId,
            'field' => $field,
            'role' => $role,
            'content' => $content,
            'model_used' => $model,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'created_by' => $userId,
        ]);
    }

    // ===== EXPORT =====

    public function exportAsArray(int $canvasId): ?array
    {
        $project = $this->getProjectWithDetails($canvasId);
        if (!$project) return null;

        $references = $this->getReferences($canvasId);

        return [
            'project' => [
                'id' => $project['id'],
                'title' => $project['title'],
                'description' => $project['description'],
                'status' => $project['status'],
                'briefing_readiness' => $project['briefing_readiness'],
                'created_at' => $project['created_at'],
                'updated_at' => $project['updated_at'],
            ],
            'fields' => $project['cards'],
            'participants' => array_map(fn($p) => [
                'name' => $p['name'],
                'role' => $p['canvas_role'],
            ], $project['participants']),
            'references' => $references,
            'completeness' => $project['completeness'],
        ];
    }

    public function exportAsMarkdown(int $canvasId): ?string
    {
        $data = $this->exportAsArray($canvasId);
        if (!$data) return null;

        $md = "# KI Kompass: {$data['project']['title']}\n\n";

        if ($data['project']['description']) {
            $md .= "> {$data['project']['description']}\n\n";
        }

        $md .= "**Status:** {$data['project']['status']} | **Briefing-Reife:** {$data['project']['briefing_readiness']}%\n\n";

        if (!empty($data['participants'])) {
            $md .= "**Teilnehmer:** " . implode(', ', array_map(fn($p) => "{$p['name']} ({$p['role']})", $data['participants'])) . "\n\n";
        }

        $md .= "---\n\n";

        foreach (self::FIELDS as $field) {
            $label = self::FIELD_LABELS[$field];
            $cards = $data['fields'][$field] ?? [];
            $fieldInfo = $data['completeness']['fields'][$field] ?? [];
            $percent = $fieldInfo['percent'] ?? 0;

            $md .= "## {$label} ({$percent}%)\n\n";

            if (empty($cards)) {
                $md .= "*Keine Karten*\n\n";
            } else {
                foreach ($cards as $card) {
                    $statusIcon = match($card['status']) {
                        'vollstaendig' => '✅',
                        'in_arbeit' => '🔶',
                        default => '⬜',
                    };
                    $md .= "### {$statusIcon} {$card['title']}\n\n";
                    if ($card['content']) {
                        $md .= "{$card['content']}\n\n";
                    }
                }
            }
        }

        if (!empty($data['references'])) {
            $md .= "---\n\n## Querverweise\n\n";
            foreach ($data['references'] as $ref) {
                $md .= "- **{$ref['source_title']}** ({$ref['source_field']}) → **{$ref['target_title']}** ({$ref['target_field']})";
                if ($ref['note']) $md .= " — {$ref['note']}";
                $md .= "\n";
            }
        }

        return $md;
    }
}
