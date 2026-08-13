<?php
namespace Services;

use Core\Database;

/**
 * ProjektplannerService — Pläne und Plan-Zeilen.
 *
 * Pläne sind team-shared (alle Admins sehen alle). `created_by` ist nur Audit.
 * Soft-Delete via `state=2`.
 */
class ProjektplannerService
{
    public const ALLOWED_STATUS = ['entwurf', 'aktiv', 'einzelprojekt', 'reporting', 'abgeschlossen', 'archiviert'];
    public const ALLOWED_TYPEN  = ['quartalsprojekt', 'einzelprojekt'];
    public const ALLOWED_ROW_TYPES = ['item', 'section', 'note', 'spacer'];
    public const PERMISSIONS = ['read', 'edit', 'write'];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ===== Permissions =====

    /**
     * Effektive Permission eines Users an einem Plan.
     * Rückgabe: 'owner' (Plan-Ersteller oder Admin), 'write', 'edit', 'read' oder null (kein Zugriff).
     * Admin/Manager-Pro-Forma: Manager hat in der Übergangszeit Vollzugriff auf alles ("write").
     */
    public function getPermission(int $planId, int $userId, bool $isAdminOrManager = false): ?string
    {
        $plan = $this->db->queryOne(
            "SELECT id, created_by FROM pp_plans WHERE id = ?", [$planId]
        );
        if (!$plan) return null;
        if ($isAdminOrManager) return 'owner';
        if ((int) $plan['created_by'] === $userId) return 'owner';
        $share = $this->db->queryOne(
            "SELECT permission FROM pp_plan_shares WHERE plan_id = ? AND user_id = ?",
            [$planId, $userId]
        );
        return $share ? $share['permission'] : null;
    }

    public function canRead(int $planId, int $userId, bool $isAdminOrManager = false): bool
    {
        return $this->getPermission($planId, $userId, $isAdminOrManager) !== null;
    }
    public function canEdit(int $planId, int $userId, bool $isAdminOrManager = false): bool
    {
        $p = $this->getPermission($planId, $userId, $isAdminOrManager);
        return in_array($p, ['owner', 'write', 'edit'], true);
    }
    public function canWrite(int $planId, int $userId, bool $isAdminOrManager = false): bool
    {
        $p = $this->getPermission($planId, $userId, $isAdminOrManager);
        return in_array($p, ['owner', 'write'], true);
    }

    public function listShares(int $planId): array
    {
        return $this->db->query(
            "SELECT s.id, s.user_id, s.permission, u.name AS user_name, u.email AS user_email
             FROM pp_plan_shares s
             JOIN users u ON u.id = s.user_id
             WHERE s.plan_id = ?
             ORDER BY u.name ASC",
            [$planId]
        ) ?: [];
    }

    public function setShare(int $planId, int $userId, string $permission): void
    {
        if (!in_array($permission, self::PERMISSIONS, true)) {
            throw new \RuntimeException('Ungültige Permission');
        }
        $existing = $this->db->queryOne(
            "SELECT id FROM pp_plan_shares WHERE plan_id = ? AND user_id = ?",
            [$planId, $userId]
        );
        if ($existing) {
            $this->db->update('pp_plan_shares', ['permission' => $permission], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('pp_plan_shares', [
                'plan_id' => $planId, 'user_id' => $userId, 'permission' => $permission,
            ]);
        }
    }

    public function removeShare(int $planId, int $userId): void
    {
        $this->db->execute(
            "DELETE FROM pp_plan_shares WHERE plan_id = ? AND user_id = ?",
            [$planId, $userId]
        );
    }

    /**
     * Pläne, die mit einem User direkt geteilt sind (zusätzlich zu eigenen).
     * Liefert wie getPlans() angereicherte Plan-Objekte mit `permission` (read/edit/write).
     */
    public function getSharedPlans(int $userId): array
    {
        return $this->db->query(
            "SELECT p.*, s.permission AS shared_permission,
                    c.name AS customer_name, c.slug AS customer_slug, c.abbreviation AS customer_abbr,
                    c.hex_color AS customer_color, c.logo_path AS customer_logo, c.website AS customer_website,
                    c.billing_model AS customer_billing_model, c.ts_per_month AS customer_ts_per_month,
                    c.billing_notes AS customer_billing_notes, c.uebertrag_ts AS customer_uebertrag_ts,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].name'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_name,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].role'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_role,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].email'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_email,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].initials'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_initials,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM pp_plan_rows r WHERE r.plan_id = p.id AND r.row_type = 'item') AS row_count,
                    (SELECT COUNT(*) FROM pp_plan_feedback f WHERE f.plan_id = p.id AND f.read_at IS NULL) AS unread_feedback
             FROM pp_plan_shares s
             JOIN pp_plans p ON p.id = s.plan_id
             LEFT JOIN customers c ON c.id = p.customer_id
             LEFT JOIN users u ON u.id = p.created_by
             WHERE s.user_id = ? AND p.state = 1
             ORDER BY p.updated_at DESC, p.id DESC",
            [$userId]
        ) ?: [];
    }

    // ===== Pläne =====

    /**
     * @param array $filter ['customer_id'?, 'status'?, 'state'?=1]
     */
    public function getPlans(array $filter = []): array
    {
        $sql = "SELECT p.*,
                       c.name AS customer_name, c.slug AS customer_slug, c.abbreviation AS customer_abbr,
                       c.hex_color AS customer_color, c.logo_path AS customer_logo, c.website AS customer_website,
                    c.billing_model AS customer_billing_model, c.ts_per_month AS customer_ts_per_month,
                    c.billing_notes AS customer_billing_notes, c.uebertrag_ts AS customer_uebertrag_ts,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].name'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_name,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].role'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_role,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].email'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_email,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].initials'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_initials,
                       u.name AS created_by_name,
                       (SELECT COUNT(*) FROM pp_plan_rows r WHERE r.plan_id = p.id AND r.row_type = 'item') AS row_count,
                       (SELECT COUNT(*) FROM pp_plan_feedback f WHERE f.plan_id = p.id AND f.read_at IS NULL) AS unread_feedback
                FROM pp_plans p
                LEFT JOIN customers c ON c.id = p.customer_id
                LEFT JOIN users u ON u.id = p.created_by
                WHERE p.state = ?";
        $params = [(int) ($filter['state'] ?? 1)];
        if (!empty($filter['customer_id'])) { $sql .= " AND p.customer_id = ?"; $params[] = (int) $filter['customer_id']; }
        if (!empty($filter['status']))      { $sql .= " AND p.plan_status = ?"; $params[] = $filter['status']; }
        $sql .= " ORDER BY LOWER(p.title) ASC, p.id ASC";
        return $this->db->query($sql, $params) ?: [];
    }

    public function getPlan(int $id): ?array
    {
        $plan = $this->db->queryOne(
            "SELECT p.*,
                    c.name AS customer_name, c.slug AS customer_slug, c.abbreviation AS customer_abbr,
                    c.hex_color AS customer_color, c.logo_path AS customer_logo, c.website AS customer_website,
                    c.billing_model AS customer_billing_model, c.ts_per_month AS customer_ts_per_month,
                    c.billing_notes AS customer_billing_notes, c.uebertrag_ts AS customer_uebertrag_ts,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].name'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_name,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].role'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_role,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].email'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_email,
                    (SELECT JSON_UNQUOTE(JSON_EXTRACT(cc.body, '$.groups[0].people[0].initials'))
                       FROM customer_cards cc WHERE cc.customer_id = c.id AND cc.type = 'contacts'
                       ORDER BY cc.sort_order, cc.id LIMIT 1) AS customer_main_contact_initials,
                    u.name AS created_by_name
             FROM pp_plans p
             LEFT JOIN customers c ON c.id = p.customer_id
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ?",
            [$id]
        );
        return $plan ?: null;
    }

    public function getPlanWithRows(int $id): ?array
    {
        $plan = $this->getPlan($id);
        if (!$plan) return null;
        $plan['rows'] = $this->getRows($id);
        $plan['feedback'] = $this->db->query(
            "SELECT * FROM pp_plan_feedback WHERE plan_id = ? ORDER BY id DESC",
            [$id]
        ) ?: [];
        return $plan;
    }

    public function createPlan(array $data, int $userId): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new \RuntimeException('Titel erforderlich');
        $status = in_array($data['plan_status'] ?? '', self::ALLOWED_STATUS, true) ? $data['plan_status'] : 'entwurf';

        return (int) $this->db->insert('pp_plans', [
            'customer_id' => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'title' => $title,
            'period_from' => $this->normalizeDate($data['period_from'] ?? null),
            'period_to' => $this->normalizeDate($data['period_to'] ?? null),
            'quarter' => $data['quarter'] ?? null,
            'plan_status' => $status,
            'asana_project_gid' => $data['asana_project_gid'] ?? null,
            'asana_section_gid' => $data['asana_section_gid'] ?? null,
            'state' => 1,
            'created_by' => $userId,
        ]);
    }

    /**
     * Markiert einen Plan als „Knowledge-DB veraltet" — der Sync-Cron erkennt das,
     * wartet den Debounce ab und syncronisiert dann die Wissensdatenbank.
     * Failsafe: schlaegt nie fehl (Knowledge ist optional).
     */
    public function markKnowledgeDirty(int $planId): void
    {
        if ($planId <= 0) return;
        try {
            $this->db->execute(
                'UPDATE pp_plans SET knowledge_dirty = 1, knowledge_dirty_since = NOW() WHERE id = ?',
                [$planId]
            );
        } catch (\Throwable $_) { /* knowledge_dirty-Spalte fehlt evtl. — ignorieren */ }
    }

    /** Liefert plan_id zu einer row_id (fuer markKnowledgeDirty aus Row-Endpoints). */
    public function planIdOfRow(int $rowId): int
    {
        return (int) $this->db->queryValue('SELECT plan_id FROM pp_plan_rows WHERE id = ?', [$rowId]);
    }

    public function updatePlan(int $id, array $data): void
    {
        $allowed = ['customer_id', 'title', 'period_from', 'period_to', 'quarter', 'plan_status', 'plan_typ', 'offer_ts',
                    'abgerechnet_ts', 'abgerechnet_am', 'abrechnung_notiz',
                    'asana_project_gid', 'asana_section_gid', 'share_hash'];
        $update = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $data)) continue;
            if ($k === 'plan_status' && !in_array($data[$k], self::ALLOWED_STATUS, true)) continue;
            if ($k === 'plan_typ' && !in_array($data[$k], self::ALLOWED_TYPEN, true)) continue;
            if ($k === 'title' && trim((string) $data[$k]) === '') continue;
            if (in_array($k, ['period_from', 'period_to'], true)) {
                $update[$k] = $this->normalizeDate($data[$k]);
            } elseif ($k === 'customer_id') {
                $update[$k] = !empty($data[$k]) ? (int) $data[$k] : null;
            } else {
                $update[$k] = $data[$k];
            }
        }
        if (empty($update)) return;
        // Auto-Snapshot wenn strukturelle Felder geändert wurden und letzter > 1h alt
        $structural = array_intersect(array_keys($update), ['title', 'customer_id', 'period_from', 'period_to', 'plan_status']);
        if (!empty($structural)) $this->maybeAutoSnapshot($id);
        $this->db->update('pp_plans', $update, 'id = ?', [$id]);
        $this->markKnowledgeDirty($id);
    }

    /**
     * Erzeugt einen Auto-Snapshot wenn der letzte > 1 Std. alt ist (oder gar keiner existiert).
     */
    private function maybeAutoSnapshot(int $planId): void
    {
        $lastAt = $this->db->queryValue(
            "SELECT created_at FROM pp_plan_revisions WHERE plan_id = ? ORDER BY id DESC LIMIT 1",
            [$planId]
        );
        if ($lastAt && (time() - strtotime($lastAt)) < 3600) return;
        try { $this->createRevision($planId, null, 'Auto'); } catch (\Throwable $_) {}
    }

    public function softDeletePlan(int $id): void
    {
        $this->db->update('pp_plans', ['state' => 2], 'id = ?', [$id]);
        // Knowledge-Doc samt Chunks entfernen (Sync-Cron erkennt state=2 und ruft removePlan)
        $this->markKnowledgeDirty($id);
    }

    public function restorePlan(int $id): void
    {
        $this->db->update('pp_plans', ['state' => 1], 'id = ?', [$id]);
        $this->markKnowledgeDirty($id);
    }

    /**
     * Hart loeschen — entfernt Plan + alle Rows + alle Snapshots + Shares + Feedback
     * + zugehoeriges Knowledge-Document samt Chunks/Embeddings/Relations.
     * Nur fuer Plaene, die bereits im Papierkorb liegen (state=2).
     * Idempotent, transaktional.
     */
    public function hardDeletePlan(int $id): void
    {
        $plan = $this->db->queryOne('SELECT id, state FROM pp_plans WHERE id = ?', [$id]);
        if (!$plan) return;
        if ((int) $plan['state'] !== 2) {
            throw new \RuntimeException('Plan muss erst in den Papierkorb (state=2) verschoben werden.');
        }

        // Knowledge-Doc + Chunks vorab raus (eigenes Service, eigene Logik fuer Qdrant-Sync)
        try {
            require_once SERVICES_PATH . '/PpKnowledgeSyncService.php';
            $sync = \Services\PpKnowledgeSyncService::build($this->db);
            $sync->removePlan($id);
        } catch (\Throwable $e) {
            // Best-Effort: wenn der Sync-Service nicht laeuft (OpenAI-Key fehlt etc.),
            // loeschen wir direkt nur das Document — die Knowledge-Tabellen fangen den Rest ab.
            $docId = (int) $this->db->queryValue(
                "SELECT id FROM knowledge_documents WHERE source_type = 'projektplan' AND external_id = ?",
                ['plan:' . $id]
            );
            if ($docId) {
                $chunkIds = array_column($this->db->query('SELECT id FROM knowledge_chunks WHERE document_id = ?', [$docId]) ?: [], 'id');
                if ($chunkIds) {
                    $ph = implode(',', array_fill(0, count($chunkIds), '?'));
                    $this->db->execute("DELETE FROM knowledge_chunk_entities WHERE chunk_id IN ($ph)", $chunkIds);
                    $this->db->execute("DELETE FROM knowledge_embeddings    WHERE chunk_id IN ($ph)", $chunkIds);
                    $this->db->execute("DELETE FROM knowledge_chunks        WHERE id       IN ($ph)", $chunkIds);
                }
                $this->db->execute('DELETE FROM knowledge_relations WHERE source_document_id = ?', [$docId]);
                $this->db->execute('DELETE FROM knowledge_documents WHERE id = ?', [$docId]);
            }
        }

        // Plan-bezogene Tabellen aufraeumen
        $this->db->beginTransaction();
        try {
            // Reihenfolge: alles was auf pp_plans/pp_plan_rows verweist zuerst
            $this->db->execute('DELETE FROM pp_plan_rows      WHERE plan_id = ?', [$id]);
            $this->db->execute('DELETE FROM pp_plan_shares    WHERE plan_id = ?', [$id]);
            $this->db->execute('DELETE FROM pp_plan_feedback  WHERE plan_id = ?', [$id]);
            $this->db->execute('DELETE FROM pp_plan_revisions WHERE plan_id = ?', [$id]);
            $this->db->execute('DELETE FROM pp_plan_budget    WHERE plan_id = ?', [$id]);
            // Notification-Log enthaelt evtl. plan_id — best-effort
            try { $this->db->execute('DELETE FROM pp_notification_log WHERE plan_id = ?', [$id]); } catch (\Throwable $_) {}
            $this->db->execute('DELETE FROM pp_plans WHERE id = ?', [$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Dupliziert einen Plan inkl. aller Zeilen. Asana-Referenzen werden NICHT kopiert.
     */
    /**
     * Plan duplizieren. Optionen:
     *   title         — neuer Titel (default: Quell-Titel + „(Kopie)")
     *   period_from   — neuer Start (default: Quelle)
     *   period_to     — neues Ende (default: Quelle)
     *   shift_dates   — wenn true UND period_from gesetzt: Deadlines/Date-from/Date-to proportional verschieben
     *   reset_ist     — wenn true: ist_hours auf 0 (default: true)
     *   reset_done    — wenn true: is_done auf 0 (default: true)
     */
    public function duplicatePlan(int $sourceId, int $userId, array $opts = []): int
    {
        $source = $this->getPlan($sourceId);
        if (!$source) throw new \RuntimeException('Quell-Plan nicht gefunden');

        $title       = isset($opts['title'])       && trim((string)$opts['title']) !== '' ? trim((string)$opts['title']) : ($source['title'] . ' (Kopie)');
        $periodFrom  = $opts['period_from'] ?? $source['period_from'];
        $periodTo    = $opts['period_to']   ?? $source['period_to'];
        $shiftDates  = !empty($opts['shift_dates']) && $periodFrom && $source['period_from'];
        $resetIst    = !array_key_exists('reset_ist', $opts)   || !empty($opts['reset_ist']);
        $resetDone   = !array_key_exists('reset_done', $opts)  || !empty($opts['reset_done']);

        // Date-Shift berechnen (Tage zwischen alt period_from und neu period_from)
        $shiftDays = 0;
        if ($shiftDates) {
            try {
                $oldFrom = new \DateTime($source['period_from']);
                $newFrom = new \DateTime($periodFrom);
                $shiftDays = (int)$oldFrom->diff($newFrom)->format('%r%a');
            } catch (\Throwable $e) { $shiftDays = 0; }
        }
        $shift = function (?string $d) use ($shiftDays): ?string {
            if (!$d || !$shiftDays) return $d;
            try { return (new \DateTime($d))->modify(($shiftDays >= 0 ? '+' : '') . $shiftDays . ' days')->format('Y-m-d'); }
            catch (\Throwable $e) { return $d; }
        };

        $newId = (int) $this->db->insert('pp_plans', [
            'customer_id' => $source['customer_id'],
            'title'       => $title,
            'period_from' => $periodFrom,
            'period_to'   => $periodTo,
            'quarter'     => $source['quarter'],
            'plan_status' => 'entwurf',
            'state'       => 1,
            'created_by'  => $userId,
            // Plan-Ebene Asana-Projektverknüpfung mitnehmen (gleicher Kunde, neues Quartal),
            // damit die Zeilen-Verknüpfung im Duplikat weiter funktioniert. Die einzelnen
            // Zeilen-Task-Links (pp_plan_rows.asana_gid) bleiben bewusst leer.
            'asana_project_gid' => $source['asana_project_gid'] ?? null,
            'asana_section_gid' => $source['asana_section_gid'] ?? null,
        ]);

        $rows = $this->getRows($sourceId);
        foreach ($rows as $r) {
            $this->db->insert('pp_plan_rows', [
                'plan_id'          => $newId,
                'row_type'         => $r['row_type'],
                'description'      => $r['description'],
                'date_from'        => $shift($r['date_from']),
                'date_to'          => $shift($r['date_to']),
                'timeframe'        => $r['timeframe'],
                'ist_hours'        => $resetIst ? 0 : $r['ist_hours'],
                'planned_hours'    => $r['planned_hours'],
                'responsible'      => $r['responsible'],
                'lead_responsible' => $r['lead_responsible'],
                'deadline'         => $shift($r['deadline']),
                'is_done'          => $resetDone ? 0 : $r['is_done'],
                'is_placeholder'   => $r['is_placeholder'],
                'is_focus'         => $r['is_focus'],
                'no_ticket'        => $r['no_ticket'],
                'actual_hours'     => $resetIst ? null : $r['actual_hours'],
                'notes'            => $r['notes'],
                // Asana-Verknuepfungen der Zeilen mituebernehmen — der Nutzer will die Ticket-Links
                // behalten (gleicher Kunde/gleiches Board), statt sie einzeln neu zu verknuepfen.
                // Nur den Erledigt-Sync-Marker bei reset_done zuruecksetzen.
                'asana_gid'            => $r['asana_gid'] ?? null,
                'asana_url'            => $r['asana_url'] ?? null,
                'asana_task_name'      => $r['asana_task_name'] ?? null,
                'asana_last_completed' => $resetDone ? null : ($r['asana_last_completed'] ?? null),
                'position'         => $r['position'],
            ]);
        }
        return $newId;
    }

    public function generateShareHash(int $planId): string
    {
        $hash = bin2hex(random_bytes(16));
        $this->db->update('pp_plans', ['share_hash' => $hash], 'id = ?', [$planId]);
        return $hash;
    }

    public function findPlanByShareHash(string $hash): ?array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM pp_plans WHERE share_hash = ? AND state = 1",
            [$hash]
        );
        return $row ?: null;
    }

    /**
     * Erzeugt einen Share-Hash für eine kuratierte Mehr-Plan-Übersicht.
     * Optional: Passwort (Bcrypt), Ablaufdatum, Snapshot-Modus (eingefrorener Stand).
     */
    public function createMultiShare(array $planIds, array $filters, ?string $title, ?int $createdBy, array $options = []): string
    {
        $planIds = array_values(array_unique(array_map('intval', $planIds)));
        if (empty($planIds)) throw new \InvalidArgumentException('Keine Plan-IDs');
        // Plausi: alle Pläne existieren + state=1
        $in = implode(',', array_fill(0, count($planIds), '?'));
        $vorhanden = $this->db->query("SELECT id FROM pp_plans WHERE id IN ($in) AND state = 1", $planIds);
        $vorhandeneIds = array_map(fn($r) => (int) $r['id'], $vorhanden ?: []);
        if (empty($vorhandeneIds)) throw new \RuntimeException('Keiner der Pläne existiert');

        $hash = bin2hex(random_bytes(16));
        $data = [
            'share_hash'     => $hash,
            'title'          => $title ? mb_substr($title, 0, 255) : null,
            'plan_ids_json'  => json_encode(array_values($vorhandeneIds)),
            'filters_json'   => json_encode($filters ?: new \stdClass()),
            'created_by'     => $createdBy,
        ];
        // Passwort
        $password = trim((string) ($options['password'] ?? ''));
        if ($password !== '') {
            $data['share_password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        // Ablauf
        $expires = trim((string) ($options['expires_at'] ?? ''));
        if ($expires !== '') {
            $ts = strtotime($expires);
            if ($ts !== false) $data['expires_at'] = date('Y-m-d H:i:s', $ts);
        }
        // Snapshot
        $isSnapshot = !empty($options['is_snapshot']);
        if ($isSnapshot) {
            $data['is_snapshot'] = 1;
            $data['snapshot_data_json'] = json_encode($this->buildMultiShareSnapshotPayload($vorhandeneIds), JSON_UNESCAPED_UNICODE);
        }
        $this->db->insert('pp_multi_shares', $data);
        return $hash;
    }

    /**
     * Liest eine gemeinsame Übersicht via Hash. Markiert den Zugriff (für Statistik).
     * Berücksichtigt Ablauf und Snapshot-Modus.
     *
     * @return array|null mit Keys: title, plan_ids, filters, plans, created_at, is_snapshot,
     *                              expires_at, expired (bool), password_required (bool)
     */
    public function findMultiShareByHash(string $hash, bool $authenticated = false): ?array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM pp_multi_shares WHERE share_hash = ?",
            [$hash]
        );
        if (!$row) return null;
        $expired = $row['expires_at'] && strtotime($row['expires_at']) < time();
        $passwordRequired = !empty($row['share_password']) && !$authenticated;
        $base = [
            'id'                => (int) $row['id'],
            'title'             => $row['title'],
            'is_snapshot'       => (int) ($row['is_snapshot'] ?? 0) === 1,
            'expires_at'        => $row['expires_at'],
            'expired'           => $expired,
            'password_required' => $passwordRequired,
            'created_at'        => $row['created_at'],
            'filters'           => json_decode($row['filters_json'] ?? '{}', true) ?: [],
        ];
        if ($expired || $passwordRequired) {
            return $base + ['plan_ids' => [], 'plans' => []];
        }

        // Snapshot-Modus → eingefrorene Daten ausspielen
        if ($base['is_snapshot'] && !empty($row['snapshot_data_json'])) {
            $snap = json_decode($row['snapshot_data_json'], true) ?: [];
            $this->trackMultiShareAccess((int) $row['id']);
            return $base + [
                'plan_ids' => array_map('intval', $snap['plan_ids'] ?? []),
                'plans'    => $snap['plans'] ?? [],
            ];
        }

        // Live-Modus → aktuelle Daten frisch laden
        $planIds = json_decode($row['plan_ids_json'] ?? '[]', true) ?: [];
        if (empty($planIds)) {
            return $base + ['plan_ids' => [], 'plans' => []];
        }
        $plans = $this->loadMultiSharePlans($planIds);
        $this->trackMultiShareAccess((int) $row['id']);
        return $base + [
            'plan_ids' => array_map('intval', $planIds),
            'plans'    => $plans,
        ];
    }

    /** Verifiziert ein Passwort gegen den Hash eines Multi-Shares. */
    public function verifyMultiSharePassword(string $hash, string $password): bool
    {
        $row = $this->db->queryOne("SELECT share_password FROM pp_multi_shares WHERE share_hash = ?", [$hash]);
        if (!$row || empty($row['share_password'])) return false;
        return password_verify($password, $row['share_password']);
    }

    /** Listet alle Multi-Shares eines Users (für die Verwaltungssicht). */
    public function listMultiShares(int $userId, bool $adminAll = false): array
    {
        $sql = "SELECT id, share_hash, title, plan_ids_json, filters_json,
                       share_password IS NOT NULL AS has_password,
                       expires_at, is_snapshot, created_by, created_at, accessed_at, access_count
                FROM pp_multi_shares ";
        $params = [];
        if (!$adminAll) { $sql .= "WHERE created_by = ? "; $params[] = $userId; }
        $sql .= "ORDER BY created_at DESC LIMIT 200";
        $rows = $this->db->query($sql, $params) ?: [];
        foreach ($rows as &$r) {
            $r['has_password'] = (int) $r['has_password'] === 1;
            $r['is_snapshot']  = (int) $r['is_snapshot'] === 1;
            $r['plan_count']   = count(json_decode($r['plan_ids_json'] ?? '[]', true) ?: []);
            $r['filters']      = json_decode($r['filters_json'] ?? '{}', true) ?: [];
            $r['expired']      = $r['expires_at'] && strtotime($r['expires_at']) < time();
            $r['url']          = '/projektplan-uebersicht/' . $r['share_hash'];
            unset($r['plan_ids_json'], $r['filters_json']);
        }
        return $rows;
    }

    /** Aktualisiert Optionen eines Multi-Shares (Passwort / Ablauf / Snapshot / Titel). */
    public function updateMultiShare(int $id, int $userId, array $changes, bool $adminAll = false): void
    {
        $row = $this->db->queryOne("SELECT * FROM pp_multi_shares WHERE id = ?", [$id]);
        if (!$row) throw new \RuntimeException('Share nicht gefunden');
        if (!$adminAll && (int) $row['created_by'] !== $userId) throw new \RuntimeException('Nicht berechtigt');

        $update = [];
        if (array_key_exists('title', $changes)) {
            $t = trim((string) $changes['title']);
            $update['title'] = $t !== '' ? mb_substr($t, 0, 255) : null;
        }
        if (array_key_exists('password', $changes)) {
            $pw = trim((string) $changes['password']);
            $update['share_password'] = $pw !== '' ? password_hash($pw, PASSWORD_BCRYPT) : null;
        }
        if (array_key_exists('expires_at', $changes)) {
            $exp = trim((string) $changes['expires_at']);
            if ($exp === '') $update['expires_at'] = null;
            else {
                $ts = strtotime($exp);
                if ($ts !== false) $update['expires_at'] = date('Y-m-d H:i:s', $ts);
            }
        }
        if (array_key_exists('is_snapshot', $changes)) {
            $snap = !empty($changes['is_snapshot']);
            $update['is_snapshot'] = $snap ? 1 : 0;
            // Snapshot ein → Snapshot-Daten neu erzeugen aus aktuellen Plan-IDs
            if ($snap) {
                $planIds = json_decode($row['plan_ids_json'] ?? '[]', true) ?: [];
                $update['snapshot_data_json'] = json_encode($this->buildMultiShareSnapshotPayload($planIds), JSON_UNESCAPED_UNICODE);
            } else {
                $update['snapshot_data_json'] = null;
            }
        }
        if (empty($update)) return;
        $this->db->update('pp_multi_shares', $update, 'id = ?', [$id]);
    }

    /** Löscht einen Multi-Share — nur Owner oder Admin. */
    public function deleteMultiShare(int $id, int $userId, bool $adminAll = false): void
    {
        $row = $this->db->queryOne("SELECT created_by FROM pp_multi_shares WHERE id = ?", [$id]);
        if (!$row) return;
        if (!$adminAll && (int) $row['created_by'] !== $userId) throw new \RuntimeException('Nicht berechtigt');
        $this->db->execute("DELETE FROM pp_multi_shares WHERE id = ?", [$id]);
    }

    // ── Multi-Share-Interna ─────────────────────────────────────────────────

    private function loadMultiSharePlans(array $planIds): array
    {
        $planIds = array_values(array_filter(array_map('intval', $planIds)));
        if (empty($planIds)) return [];
        $in = implode(',', array_fill(0, count($planIds), '?'));
        // Alphabetisch nach Titel — konsistent mit der internen Plan-Liste im Projektplanner
        $plans = $this->db->query(
            "SELECT p.*, c.name AS customer_name, c.abbreviation AS customer_abbr
             FROM pp_plans p
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.id IN ($in) AND p.state = 1
             ORDER BY p.title ASC, p.id ASC",
            $planIds
        ) ?: [];
        foreach ($plans as &$p) {
            $p['rows'] = $this->getRows((int) $p['id']);
        }
        return $plans;
    }

    /** Erzeugt das Payload für einen eingefrorenen Snapshot — schlanke Felder, JSON-fähig. */
    private function buildMultiShareSnapshotPayload(array $planIds): array
    {
        $plans = $this->loadMultiSharePlans($planIds);
        $clean = [];
        foreach ($plans as $p) {
            $clean[] = [
                'id'            => (int) $p['id'],
                'title'         => $p['title'],
                'plan_status'   => $p['plan_status'],
                'plan_typ'      => $p['plan_typ'] ?? null,
                'period_from'   => $p['period_from'],
                'period_to'     => $p['period_to'],
                'customer_id'   => $p['customer_id'] ? (int) $p['customer_id'] : null,
                'customer_name' => $p['customer_name'] ?? null,
                'customer_abbr' => $p['customer_abbr'] ?? null,
                'rows' => array_map(function ($r) {
                    return [
                        'id'               => (int) $r['id'],
                        'row_type'         => $r['row_type'],
                        'description'      => $r['description'],
                        'timeframe'        => $r['timeframe']        ?? null,
                        'ist_hours'        => $r['ist_hours']        ?? null,
                        'planned_hours'    => $r['planned_hours']    ?? null,
                        'responsible'      => $r['responsible']      ?? null,
                        'lead_responsible' => $r['lead_responsible'] ?? null,
                        'deadline'         => $r['deadline']         ?? null,
                        'is_done'          => (int) ($r['is_done']   ?? 0),
                        'is_placeholder'   => (int) ($r['is_placeholder'] ?? 0),
                        'no_ticket'        => (int) ($r['no_ticket'] ?? 0),
                        'asana_gid'        => $r['asana_gid']        ?? null,
                        'position'         => (int) ($r['position']  ?? 0),
                    ];
                }, $p['rows'] ?? []),
            ];
        }
        return ['plan_ids' => array_map(fn($p) => (int) $p['id'], $clean), 'plans' => $clean, 'frozen_at' => date('Y-m-d H:i:s')];
    }

    private function trackMultiShareAccess(int $id): void
    {
        $this->db->execute(
            "UPDATE pp_multi_shares SET accessed_at = NOW(), access_count = access_count + 1 WHERE id = ?",
            [$id]
        );
    }

    // ===== Zeilen =====

    public function getRows(int $planId): array
    {
        return $this->db->query(
            "SELECT * FROM pp_plan_rows WHERE plan_id = ? ORDER BY position ASC, id ASC",
            [$planId]
        ) ?: [];
    }

    /** Stunden auf den Viertelstunden-Takt runden (0,25-Schritte). */
    private static function snapQuarter($h): float
    {
        return round(((float) $h) * 4) / 4;
    }

    public function addRow(int $planId, array $data): int
    {
        $maxPos = (int) ($this->db->queryValue(
            "SELECT COALESCE(MAX(position), -1) FROM pp_plan_rows WHERE plan_id = ?",
            [$planId]
        ) ?? -1);
        $type = in_array($data['row_type'] ?? '', self::ALLOWED_ROW_TYPES, true) ? $data['row_type'] : 'item';
        // Vollstaendige Feld-Aufnahme — vorher wurden timeframe/deadline/notes/is_focus/...
        // beim Duplizieren stillschweigend verloren.
        $insert = [
            'plan_id'          => $planId,
            'row_type'         => $type,
            'description'      => $data['description'] ?? '',
            'position'         => $data['position'] ?? ($maxPos + 1),
            'planned_hours'    => self::snapQuarter($data['planned_hours'] ?? 0),
            'ist_hours'        => self::snapQuarter($data['ist_hours'] ?? 0),
            'responsible'      => $this->normalizeNameField($data['responsible'] ?? ''),
            'lead_responsible' => $this->normalizeNameField($data['lead_responsible'] ?? ''),
        ];
        // Optionale Felder nur uebernehmen, wenn explizit mitgegeben — sonst Defaults aus DB.
        foreach (['date_from','date_to','timeframe','deadline','notes',
                  'actual_hours','asana_gid','asana_url','asana_task_name'] as $k) {
            if (array_key_exists($k, $data)) $insert[$k] = $data[$k];
        }
        foreach (['is_done','is_placeholder','is_focus','no_ticket'] as $k) {
            if (array_key_exists($k, $data)) $insert[$k] = (int) $data[$k] ? 1 : 0;
        }
        $newId = (int) $this->db->insert('pp_plan_rows', $insert);
        $this->markKnowledgeDirty($planId);
        return $newId;
    }

    /**
     * Asana-Unteraufgaben als neue Zeilen ins Board holen — direkt unter die Eltern-Zeile
     * (afterRowId). Jede neue Zeile wird mit ihrer Subtask-GID verknuepft. Der Asana-Erledigt-
     * Status wird uebernommen. Gibt die neuen Zeilen-IDs zurueck.
     *
     * @param array $subtasks Liste aus [{gid, name, url?, completed?}]
     */
    public function importAsanaSubtasks(int $planId, int $afterRowId, array $subtasks): array
    {
        $subtasks = array_values(array_filter($subtasks, fn($s) => !empty($s['gid'])));
        $n = count($subtasks);
        if ($n === 0) return [];

        // Einfuege-Position: direkt hinter der Eltern-Zeile. Nachfolgende Zeilen um n verschieben,
        // damit die Import-Zeilen zusammen unter dem Eltern-Ticket landen.
        $basePos = -1;
        if ($afterRowId > 0) {
            $basePos = $this->db->queryValue(
                "SELECT position FROM pp_plan_rows WHERE id = ? AND plan_id = ?",
                [$afterRowId, $planId]
            );
            $basePos = ($basePos === null) ? -1 : (int) $basePos;
        }
        if ($basePos >= 0) {
            $this->db->query(
                "UPDATE pp_plan_rows SET position = position + ? WHERE plan_id = ? AND position > ?",
                [$n, $planId, $basePos]
            );
        }

        $created = [];
        $i = 1;
        foreach ($subtasks as $s) {
            $url = $s['url'] ?? ('https://app.asana.com/0/0/' . $s['gid']);
            $data = [
                'row_type'        => 'item',
                'description'     => trim((string) ($s['name'] ?? '')),
                'asana_gid'       => (string) $s['gid'],
                'asana_url'       => $url,
                'asana_task_name' => trim((string) ($s['name'] ?? '')),
                'is_done'         => !empty($s['completed']) ? 1 : 0,
            ];
            if ($basePos >= 0) $data['position'] = $basePos + $i;
            $created[] = $this->addRow($planId, $data);
            $i++;
        }
        return $created;
    }

    /**
     * Auto-Save eines einzelnen Felds oder mehrerer Felder einer Zeile.
     */
    public function updateRow(int $rowId, array $data): void
    {
        $allowed = ['row_type', 'description', 'date_from', 'date_to', 'timeframe',
                    'ist_hours', 'planned_hours', 'responsible', 'lead_responsible',
                    'deadline', 'is_done', 'is_placeholder', 'is_focus', 'no_ticket',
                    'actual_hours', 'notes', 'asana_gid', 'asana_url', 'asana_task_name',
                    'position', 'review_flag', 'review_note'];
        $update = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) $update[$k] = $data[$k];
        }
        if (isset($update['row_type']) && !in_array($update['row_type'], self::ALLOWED_ROW_TYPES, true)) {
            unset($update['row_type']);
        }
        // Auto-Normalisierung: Kürzel/Schreibvarianten auf kanonische Team-Namen mappen
        if (array_key_exists('responsible', $update))      $update['responsible']      = $this->normalizeNameField((string)$update['responsible']);
        if (array_key_exists('lead_responsible', $update)) $update['lead_responsible'] = $this->normalizeNameField((string)$update['lead_responsible']);
        // Stunden immer auf den Viertelstunden-Takt snappen (0,25-Schritte) — keine 0,8/1,3-Werte.
        if (array_key_exists('planned_hours', $update)) $update['planned_hours'] = self::snapQuarter($update['planned_hours']);
        if (array_key_exists('ist_hours', $update))     $update['ist_hours']     = self::snapQuarter($update['ist_hours']);
        if (empty($update)) return;
        // Recovery: jedes Sach-Update auf einer Review-markierten Zeile entfernt das Flag automatisch.
        // (Wenn der User die Zeile editiert hat, hat er sie bewusst gesehen — er muss nicht extra „Passt" klicken.)
        $userFields = array_diff(array_keys($update), ['review_flag']);
        if (!empty($userFields)) $update['review_flag'] = 0;
        $this->db->update('pp_plan_rows', $update, 'id = ?', [$rowId]);
        $this->markKnowledgeDirty($this->planIdOfRow($rowId));
    }

    /** Verzögert PpTeamService nutzen (vermeidet zyklischen require). */
    private function normalizeNameField(string $raw): string
    {
        if ($raw === '') return '';
        if (!class_exists('\Services\PpTeamService')) {
            require_once SERVICES_PATH . '/PpTeamService.php';
        }
        static $teamSvc = null;
        if ($teamSvc === null) $teamSvc = new PpTeamService($this->db);
        return $teamSvc->normalizeRowName($raw);
    }

    public function deleteRow(int $rowId): void
    {
        $planId = $this->planIdOfRow($rowId);
        $this->db->execute("DELETE FROM pp_plan_rows WHERE id = ?", [$rowId]);
        $this->markKnowledgeDirty($planId);
    }

    /**
     * Schreibt die neue Reihenfolge — `$order` ist Array von Row-IDs in Zielreihenfolge.
     */
    public function reorderRows(int $planId, array $order): void
    {
        $this->maybeAutoSnapshot($planId);
        $this->markKnowledgeDirty($planId);
        $pos = 0;
        foreach ($order as $rowId) {
            $rowId = (int) $rowId;
            if (!$rowId) continue;
            $this->db->update('pp_plan_rows', ['position' => $pos], 'id = ? AND plan_id = ?', [$rowId, $planId]);
            $pos++;
        }
    }

    /**
     * Verschiebt eine Zeile in einen anderen Plan und an eine bestimmte Position.
     */
    public function moveRowToPlan(int $rowId, int $targetPlanId, int $targetPosition): void
    {
        // Position-Lücke schaffen
        $this->db->execute(
            "UPDATE pp_plan_rows SET position = position + 1 WHERE plan_id = ? AND position >= ?",
            [$targetPlanId, $targetPosition]
        );
        $this->db->update('pp_plan_rows',
            ['plan_id' => $targetPlanId, 'position' => $targetPosition],
            'id = ?', [$rowId]
        );
    }

    // ===== Revisionen / Snapshots =====

    public const MAX_REVISIONS_PER_PLAN = 50;

    /**
     * Snapshot des aktuellen Plan-Zustands (alle Rows + Plan-Header als JSON).
     * Auto-Purge ältester wenn MAX überschritten.
     */
    public function createRevision(int $planId, ?int $userId, ?string $label = null): int
    {
        $plan = $this->getPlan($planId);
        if (!$plan) throw new \RuntimeException('Plan nicht gefunden');
        $rows = $this->getRows($planId);
        $snapshot = json_encode([
            'plan' => [
                'title' => $plan['title'], 'customer_id' => $plan['customer_id'],
                'period_from' => $plan['period_from'], 'period_to' => $plan['period_to'],
                'plan_status' => $plan['plan_status'],
            ],
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE);

        $newId = (int) $this->db->insert('pp_plan_revisions', [
            'plan_id' => $planId, 'user_id' => $userId,
            'snapshot' => $snapshot, 'label' => $label ?: null,
        ]);

        // Auto-Purge älteste über MAX
        $excess = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM pp_plan_revisions WHERE plan_id = ?", [$planId]
        ) - self::MAX_REVISIONS_PER_PLAN;
        if ($excess > 0) {
            $this->db->execute(
                "DELETE FROM pp_plan_revisions WHERE plan_id = ?
                 ORDER BY id ASC LIMIT $excess",
                [$planId]
            );
        }
        return $newId;
    }

    public function listRevisions(int $planId): array
    {
        return $this->db->query(
            "SELECT r.id, r.plan_id, r.user_id, r.label, r.created_at,
                    u.name AS user_name,
                    JSON_LENGTH(JSON_EXTRACT(r.snapshot, '$.rows')) AS row_count
             FROM pp_plan_revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.plan_id = ?
             ORDER BY r.id DESC",
            [$planId]
        ) ?: [];
    }

    public function restoreRevision(int $revisionId, int $userId): void
    {
        $rev = $this->db->queryOne(
            "SELECT plan_id, snapshot FROM pp_plan_revisions WHERE id = ?",
            [$revisionId]
        );
        if (!$rev) throw new \RuntimeException('Revision nicht gefunden');
        $data = json_decode($rev['snapshot'], true);
        if (!is_array($data) || !isset($data['rows'])) {
            throw new \RuntimeException('Snapshot beschädigt');
        }
        // Aktuellen Stand als Sicherheits-Snapshot anlegen
        $this->createRevision((int) $rev['plan_id'], $userId, 'Auto vor Restore');

        // Plan-Header optional updaten
        if (isset($data['plan'])) {
            $this->updatePlan((int) $rev['plan_id'], $data['plan']);
        }
        // Alle Rows ersetzen
        $this->db->execute("DELETE FROM pp_plan_rows WHERE plan_id = ?", [(int) $rev['plan_id']]);
        foreach ($data['rows'] as $r) {
            $this->db->insert('pp_plan_rows', [
                'plan_id' => (int) $rev['plan_id'],
                'row_type' => $r['row_type'] ?? 'item',
                'description' => $r['description'] ?? '',
                'date_from' => $r['date_from'] ?? null,
                'date_to' => $r['date_to'] ?? null,
                'timeframe' => $r['timeframe'] ?? null,
                'ist_hours' => $r['ist_hours'] ?? 0,
                'planned_hours' => $r['planned_hours'] ?? 0,
                'responsible' => $r['responsible'] ?? '',
                'lead_responsible' => $r['lead_responsible'] ?? '',
                'deadline' => $r['deadline'] ?? null,
                'is_done' => $r['is_done'] ?? 0,
                'is_placeholder' => $r['is_placeholder'] ?? 0,
                'is_focus' => $r['is_focus'] ?? 0,
                'no_ticket' => $r['no_ticket'] ?? 0,
                'actual_hours' => $r['actual_hours'] ?? null,
                'notes' => $r['notes'] ?? null,
                'position' => $r['position'] ?? 0,
            ]);
        }
    }

    // ===== Helpers =====

    private function normalizeDate($value): ?string
    {
        if (empty($value)) return null;
        if ($value instanceof \DateTime) return $value->format('Y-m-d');
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
