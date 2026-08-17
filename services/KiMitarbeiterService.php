<?php
/**
 * KiMitarbeiterService — Kern des KI-Mitarbeiter-Builders.
 *
 * Verwaltet ai_employees inkl. Lebenszyklus (Status-Maschine mit erlaubten
 * Übergängen), Profil (als JSON-Blob = lebende Quelle des Wizard-Entwurfs),
 * Versionierung (Snapshot-Muster wie CustomerCardService) und Vollständigkeit.
 *
 * Governance/Runtime liegen in eigenen Tabellen/Services:
 *  - Zugriffe: ai_tool_permissions (Antrag/Freigabe, Runtime-Guard)
 *  - Läufe: ai_runs / ai_run_messages   - Feedback: ai_feedback
 *  - Wizard-Verlauf: ai_wizard_messages  - Versionen: ai_employee_versions
 *  - Audit: permission_audit_log via \Core\AuditLog (KEINE eigene Audit-Tabelle)
 *
 * DATENSICHERHEIT: Status- und Rechteänderungen laufen NUR über die geprüften
 * Methoden hier, nie über einen Profil-Patch (siehe patchProfile-Whitelist).
 */

namespace Services;

use Core\AuditLog;

class KiMitarbeiterService
{
    private $db;

    /** Lebenszyklus-Status (Spec §5). */
    public const STATUSES = ['draft', 'review', 'onboarding', 'probation', 'active', 'paused', 'archived'];

    /** Erlaubte Übergänge. archived ist von überall (außer archived) erreichbar (Admin). */
    public const TRANSITIONS = [
        'draft'      => ['review', 'archived'],
        'review'     => ['onboarding', 'draft', 'archived'],
        'onboarding' => ['probation', 'review', 'archived'],
        'probation'  => ['active', 'onboarding', 'paused', 'archived'],
        'active'     => ['paused', 'archived'],
        'paused'     => ['active', 'archived'],
        'archived'   => [],
    ];

    /** Erlaubte Top-Level-Schlüssel im Profil-JSON (Whitelist für Patches). */
    public const PROFILE_KEYS = [
        'role_title', 'short_description', 'goals', 'tasks', 'non_tasks',
        'responsibilities', 'escalation_rules', 'workflows', 'skills',
        'knowledge_sources', 'positive_examples', 'negative_examples',
        'quality_rules', 'forbidden', 'personality', 'test_cases', 'department',
        'problem_statement', 'expected_benefit', 'need_classification',
    ];

    /** Pflicht-Sektionen fürs Einreichen zur Prüfung. */
    public const REQUIRED_SECTIONS = ['role_title', 'tasks', 'escalation_rules', 'test_cases'];

    public function __construct($db = null)
    {
        $this->db = $db ?: \Core\Database::getInstance();
    }

    // ---------------------------------------------------------------- Lesen

    /** Liste (optional gefiltert nach Status/Kunde), mit Owner-Name + offenen Freigaben. */
    public function liste(array $filter = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filter['status'])) { $where[] = 'e.status = ?'; $params[] = $filter['status']; }
        if (array_key_exists('customer_id', $filter)) {
            if ($filter['customer_id'] === null) { $where[] = 'e.customer_id IS NULL'; }
            else { $where[] = 'e.customer_id = ?'; $params[] = (int) $filter['customer_id']; }
        }
        if (!empty($filter['allowed_customer_ids']) && is_array($filter['allowed_customer_ids'])) {
            $in = implode(',', array_map('intval', $filter['allowed_customer_ids']));
            $where[] = "(e.customer_id IS NULL OR e.customer_id IN ($in))";
        }
        $sql = "SELECT e.*, u.name AS owner_name, c.name AS customer_name,
                    (SELECT COUNT(*) FROM ai_tool_permissions p WHERE p.ai_employee_id = e.id AND p.status = 'requested') AS open_permissions
                FROM ai_employees e
                LEFT JOIN users u ON u.id = e.owner_user_id
                LEFT JOIN customers c ON c.id = e.customer_id";
        if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY e.updated_at DESC';
        $rows = $this->db->query($sql, $params);
        foreach ($rows as &$r) { $r['profile'] = $this->decode($r['profile']); }
        return $rows;
    }

    /** Einzelner KI-Mitarbeiter mit dekodiertem Profil + Zusatzinfos. */
    public function get(int $id): ?array
    {
        $e = $this->db->queryOne(
            "SELECT e.*, u.name AS owner_name, c.name AS customer_name
             FROM ai_employees e
             LEFT JOIN users u ON u.id = e.owner_user_id
             LEFT JOIN customers c ON c.id = e.customer_id
             WHERE e.id = ?",
            [$id]
        );
        if (!$e) return null;
        $e['profile'] = $this->decode($e['profile']);
        $e['personality_config'] = $this->decode($e['personality_config']);
        $e['model_config'] = $this->decode($e['model_config']);
        $e['completeness'] = $this->completeness($e['profile']);
        return $e;
    }

    // ---------------------------------------------------------------- Schreiben

    /** Neuen Entwurf anlegen. Owner = Ersteller, sofern nicht anders gesetzt. */
    public function create(array $data, int $actorId): int
    {
        $profile = $this->sanitizeProfile($data['profile'] ?? []);
        $id = $this->db->insert('ai_employees', [
            'customer_id'    => isset($data['customer_id']) && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null,
            'name'           => trim((string) ($data['name'] ?? 'Neuer KI-Mitarbeiter')),
            'role_title'     => trim((string) ($data['role_title'] ?? ($profile['role_title'] ?? ''))),
            'short_description' => (string) ($data['short_description'] ?? ($profile['short_description'] ?? '')),
            'owner_user_id'  => isset($data['owner_user_id']) && $data['owner_user_id'] ? (int) $data['owner_user_id'] : $actorId,
            'status'         => 'draft',
            'problem_statement' => (string) ($data['problem_statement'] ?? ''),
            'expected_benefit'  => (string) ($data['expected_benefit'] ?? ''),
            'profile'        => json_encode($profile, JSON_UNESCAPED_UNICODE),
            'created_by'     => $actorId,
        ]);
        AuditLog::record('ai_employee', (string) $id, 'created', ['name' => $data['name'] ?? null], $actorId);
        return $id;
    }

    /** Skalarfelder + Kundenbindung aktualisieren (nicht Status, nicht Rechte). */
    public function updateMeta(int $id, array $data, int $actorId): void
    {
        $fields = [];
        foreach (['name', 'role_title', 'short_description', 'department', 'problem_statement', 'expected_benefit', 'avatar_url'] as $f) {
            if (array_key_exists($f, $data)) { $fields[$f] = (string) $data[$f]; }
        }
        if (array_key_exists('customer_id', $data)) {
            $fields['customer_id'] = ($data['customer_id'] === '' || $data['customer_id'] === null) ? null : (int) $data['customer_id'];
        }
        if (array_key_exists('owner_user_id', $data) && $data['owner_user_id']) {
            $fields['owner_user_id'] = (int) $data['owner_user_id'];
        }
        if (array_key_exists('model_config', $data)) {
            $fields['model_config'] = json_encode($data['model_config'], JSON_UNESCAPED_UNICODE);
        }
        if (!$fields) return;
        $this->db->update('ai_employees', $fields, 'id = ?', [$id]);
        AuditLog::record('ai_employee', (string) $id, 'meta_updated', array_keys($fields), $actorId);
    }

    /**
     * Profil-Patch anwenden (vom Wizard oder aus Tab-Formularen).
     * NUR erlaubte Profil-Schlüssel werden übernommen (Whitelist). Status- und
     * Rechte-Felder werden IGNORIERT — die dürfen nie über einen Patch geändert
     * werden. Gibt das neue, vollständige Profil zurück.
     */
    public function patchProfile(int $id, array $patch, int $actorId): array
    {
        $e = $this->db->queryOne("SELECT profile FROM ai_employees WHERE id = ?", [$id]);
        if (!$e) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');
        $profile = $this->decode($e['profile']);
        $clean = $this->sanitizeProfile($patch);
        foreach ($clean as $k => $v) {
            $profile[$k] = $v; // flach ersetzen pro Sektion (Patch liefert je Sektion den neuen Stand)
        }
        // Skalar-Spiegelung fuer Liste/Anzeige
        $mirror = [];
        if (isset($profile['role_title'])) $mirror['role_title'] = (string) $profile['role_title'];
        if (isset($profile['short_description'])) $mirror['short_description'] = (string) $profile['short_description'];
        $mirror['profile'] = json_encode($profile, JSON_UNESCAPED_UNICODE);
        $this->db->update('ai_employees', $mirror, 'id = ?', [$id]);
        return $profile;
    }

    /** Verwirft alle nicht erlaubten Schlüssel eines Profil-Arrays. */
    private function sanitizeProfile(array $profile): array
    {
        $out = [];
        foreach ($profile as $k => $v) {
            if (in_array($k, self::PROFILE_KEYS, true)) { $out[$k] = $v; }
        }
        return $out;
    }

    // ---------------------------------------------------------------- Status

    /** Prüft, ob ein Übergang grundsätzlich erlaubt ist. */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Statuswechsel mit Guard. Bei review werden die Pflicht-Sektionen geprüft.
     * @throws \RuntimeException bei unerlaubtem Übergang / fehlenden Pflichtangaben
     */
    public function transition(int $id, string $to, int $actorId): void
    {
        $e = $this->get($id);
        if (!$e) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');
        $from = $e['status'];
        if ($from === $to) return;
        if (!$this->canTransition($from, $to)) {
            throw new \RuntimeException("Übergang $from → $to ist nicht erlaubt.");
        }
        if ($to === 'review') {
            $missing = $this->missingForReview($e['profile']);
            if (!empty($e['owner_user_id']) === false) $missing[] = 'Verantwortlicher';
            if ($missing) {
                throw new \RuntimeException('Zur Prüfung fehlen noch: ' . implode(', ', $missing));
            }
        }
        $fields = ['status' => $to];
        if ($to === 'archived') $fields['archived_at'] = date('Y-m-d H:i:s');
        $this->db->update('ai_employees', $fields, 'id = ?', [$id]);
        AuditLog::record('ai_employee', (string) $id, 'status_changed', ['from' => $from, 'to' => $to], $actorId);
    }

    // ---------------------------------------------------------------- Vollständigkeit

    /** @return array{percentage:int,missing_sections:string[]} */
    public function completeness(array $profile): array
    {
        $sections = ['role_title', 'tasks', 'non_tasks', 'escalation_rules', 'workflows', 'knowledge_sources', 'personality', 'test_cases'];
        $have = 0;
        $missing = [];
        foreach ($sections as $s) {
            if (!empty($profile[$s])) { $have++; } else { $missing[] = $s; }
        }
        $pct = (int) round($have / count($sections) * 100);
        return ['percentage' => $pct, 'missing_sections' => $missing];
    }

    /** Fehlende Pflicht-Sektionen für die Einreichung (inkl. ≥3 Testfälle). */
    public function missingForReview(array $profile): array
    {
        $missing = [];
        foreach (self::REQUIRED_SECTIONS as $s) {
            if (empty($profile[$s])) { $missing[] = $s; }
        }
        $tc = is_array($profile['test_cases'] ?? null) ? count($profile['test_cases']) : 0;
        if ($tc < 3 && !in_array('test_cases', $missing, true)) {
            $missing[] = 'mindestens 3 Testfälle (' . $tc . ' vorhanden)';
        } elseif ($tc < 3 && in_array('test_cases', $missing, true)) {
            // test_cases fehlt komplett -> schon in $missing
        }
        return $missing;
    }

    // ---------------------------------------------------------------- Versionen

    /**
     * Aktuelles Profil als neue Version festschreiben. Gibt version_number zurück.
     * $approvedBy setzen, wenn die Version zugleich freigegeben wird.
     */
    public function publishVersion(int $id, string $changeSummary, int $actorId, ?int $approvedBy = null): int
    {
        $e = $this->db->queryOne("SELECT profile FROM ai_employees WHERE id = ?", [$id]);
        if (!$e) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');
        $next = (int) $this->db->queryValue(
            "SELECT COALESCE(MAX(version_number), 0) + 1 FROM ai_employee_versions WHERE ai_employee_id = ?",
            [$id]
        );
        $versionId = $this->db->insert('ai_employee_versions', [
            'ai_employee_id'   => $id,
            'version_number'   => $next,
            'profile_snapshot' => $e['profile'] ?: '{}',
            'change_summary'   => $changeSummary,
            'created_by'       => $actorId,
            'approved_by'      => $approvedBy,
            'approved_at'      => $approvedBy ? date('Y-m-d H:i:s') : null,
        ]);
        $this->db->update('ai_employees', ['current_version_id' => $versionId], 'id = ?', [$id]);
        // Retention: max 50 Versionen pro Mitarbeiter
        $this->db->execute(
            "DELETE FROM ai_employee_versions WHERE ai_employee_id = ? AND id NOT IN (
                SELECT id FROM (SELECT id FROM ai_employee_versions WHERE ai_employee_id = ? ORDER BY version_number DESC LIMIT 50) t
            )",
            [$id, $id]
        );
        AuditLog::record('ai_employee', (string) $id, 'version_published', ['version' => $next, 'summary' => $changeSummary], $actorId);
        return $next;
    }

    public function listVersions(int $id): array
    {
        return $this->db->query(
            "SELECT v.*, cu.name AS created_by_name, au.name AS approved_by_name
             FROM ai_employee_versions v
             LEFT JOIN users cu ON cu.id = v.created_by
             LEFT JOIN users au ON au.id = v.approved_by
             WHERE v.ai_employee_id = ? ORDER BY v.version_number DESC",
            [$id]
        );
    }

    /** Version wiederherstellen: aktuellen Stand erst als neue Version sichern, dann altes Profil zurückschreiben. */
    public function restoreVersion(int $id, int $versionNumber, int $actorId): void
    {
        $v = $this->db->queryOne(
            "SELECT profile_snapshot FROM ai_employee_versions WHERE ai_employee_id = ? AND version_number = ?",
            [$id, $versionNumber]
        );
        if (!$v) throw new \RuntimeException('Version nicht gefunden.');
        $this->publishVersion($id, "Vor Wiederherstellung von v$versionNumber", $actorId);
        $this->db->update('ai_employees', ['profile' => $v['profile_snapshot']], 'id = ?', [$id]);
        AuditLog::record('ai_employee', (string) $id, 'version_restored', ['version' => $versionNumber], $actorId);
    }

    // ---------------------------------------------------------------- intern

    private function decode($json): array
    {
        if (is_array($json)) return $json;
        if (!$json) return [];
        $d = json_decode((string) $json, true);
        return is_array($d) ? $d : [];
    }
}
