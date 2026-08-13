<?php
namespace Services;

use Core\Database;

/**
 * PpTeamService — Verwaltung der Team-Mitglieder im Projektplanner.
 *
 * Hybrid-Modell: ein Team-Member kann an einen `users`-Eintrag gebunden sein
 * (user_id), oder eine freie Person (Freelancer, externer Dienstleister).
 * Beim Erst-Aufruf werden alle aktiven Users automatisch als Team-Member angelegt.
 */
class PpTeamService
{
    public const DEFAULT_CAPACITY = 160;
    private const PALETTE = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#a855f7',
        '#0ea5e9', '#14b8a6', '#f97316', '#ec4899', '#8b5cf6',
    ];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Synchronisiert die `users`-Tabelle nach `pp_team_members`:
     * - Pro aktivem User ohne Team-Member-Eintrag: einen anlegen.
     * - Bestehende Einträge bleiben unverändert (Capacity etc. behalten).
     * Wird beim ersten Aufruf von /admin/projektplanner getriggert.
     */
    public function syncFromUsers(): int
    {
        $created = 0;
        $users = $this->db->query(
            "SELECT u.id, u.name, u.abbreviation, u.email
             FROM users u
             LEFT JOIN pp_team_members t ON t.user_id = u.id
             WHERE u.is_active = 1 AND t.id IS NULL"
        ) ?: [];

        $maxOrder = (int) ($this->db->queryValue("SELECT COALESCE(MAX(sort_order), 0) FROM pp_team_members") ?? 0);

        foreach ($users as $u) {
            $abbr = trim((string) ($u['abbreviation'] ?? '')) ?: $this->initials($u['name'] ?? '?');
            $color = $this->autoColor((int) $u['id']);
            $this->db->insert('pp_team_members', [
                'user_id' => (int) $u['id'],
                'name' => $u['name'],
                'abbreviation' => mb_substr($abbr, 0, 10),
                'capacity_hours' => self::DEFAULT_CAPACITY,
                'hex_color' => $color,
                'sort_order' => ++$maxOrder,
                'is_active' => 1,
            ]);
            $created++;
        }
        return $created;
    }

    /**
     * Benennt eine Person in allen Plan-Zeilen um (lead_responsible + responsible-Liste)
     * UND aktualisiert den Team-Mitglieder-Eintrag.
     * Returns Anzahl betroffener Plan-Zeilen.
     */
    public function renamePerson(string $oldName, string $newName, ?int $memberId = null): int
    {
        $oldName = trim($oldName);
        $newName = trim($newName);
        if ($oldName === '' || $newName === '' || $oldName === $newName) return 0;
        $count = 0;

        $count += $this->db->execute(
            "UPDATE pp_plan_rows SET lead_responsible = ? WHERE lead_responsible = ?",
            [$newName, $oldName]
        );

        $rows = $this->db->query(
            "SELECT id, responsible FROM pp_plan_rows WHERE responsible LIKE ?",
            ['%' . $oldName . '%']
        ) ?: [];
        foreach ($rows as $r) {
            $names = array_map('trim', explode(',', (string) $r['responsible']));
            $changed = false;
            foreach ($names as $k => $n) {
                if (mb_strtolower($n) === mb_strtolower($oldName)) {
                    $names[$k] = $newName;
                    $changed = true;
                }
            }
            if ($changed) {
                $newResp = implode(', ', array_filter($names));
                $this->db->update('pp_plan_rows', ['responsible' => $newResp], 'id = ?', [(int) $r['id']]);
                $count++;
            }
        }

        if ($memberId) {
            $this->db->update('pp_team_members', ['name' => $newName], 'id = ?', [$memberId]);
        } else {
            $this->db->execute(
                "UPDATE pp_team_members SET name = ? WHERE LOWER(name) = LOWER(?)",
                [$newName, $oldName]
            );
        }
        return $count;
    }

    public function getAll(bool $activeOnly = true): array
    {
        // Alphabetisch nach Kürzel, dann nach Name (Kürzel-Doppel z.B. „EXT" werden per Name aufgelöst).
        // Leere Abbreviation rutscht ans Ende. nickname kommt aus users.
        // Customer-Rolle taucht NIE im Projektplanner-Team auf (manuelle Mitglieder ohne User bleiben).
        $conds = ["(u.id IS NULL OR u.role <> 'customer')"];
        if ($activeOnly) $conds[] = 't.is_active = 1';
        $sql = "SELECT t.*, u.email, u.nickname
                FROM pp_team_members t
                LEFT JOIN users u ON u.id = t.user_id
                WHERE " . implode(' AND ', $conds)
             . " ORDER BY (t.abbreviation IS NULL OR t.abbreviation = '') ASC, t.abbreviation ASC, t.name ASC";
        return $this->db->query($sql) ?: [];
    }

    /**
     * Normalisiert einen Klartext-Wert für lead_responsible/responsible:
     *   - Trennzeichen vereinheitlichen ( „ / ", „;" → „,")
     *   - Tokens trimmen, leere raus
     *   - Pro Token: case-insensitive Lookup in pp_team_members.abbreviation ODER .name → ersetzen durch kanonischen .name
     *   - Unbekannte Tokens unverändert lassen (Platzhalter, Externe, unbekannte Kürzel)
     *   - Dedupe (case-insensitive auf den kanonischen Wert)
     *   - Rejoin mit „, "
     *
     * Beispiele bei Team {BJU→Benjamin Juling, JST→Jonas Stock, JDR→Jasper Drury, TKI→Thomas Kilian}:
     *   „JST, jDR, Thomas Kilian"       → „Jonas Stock, Jasper Drury, Thomas Kilian"
     *   „Ralf / Thomas / Benny"          → „Ralf / Thomas / Benny" (kein Lookup-Match auf Vornamen — bleibt)
     *   „jst jdr tki, jdr, Thomas Kilian" → „jst jdr tki, Jasper Drury, Thomas Kilian"
     */
    public function normalizeRowName(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        // Lookup-Map aufbauen (lazy-cached pro Instanz).
        // Schlüssel: name, abbreviation, nickname (case-insensitive). Wert: kanonischer name.
        // Bei Kürzel-Doppel: Test-Accounts (Name enthält „Test") werden übersprungen, damit echte Personen gewinnen.
        static $lookup = null;
        if ($lookup === null) {
            $lookup = [];
            $members = $this->db->query(
                "SELECT t.name, t.abbreviation, u.nickname
                 FROM pp_team_members t LEFT JOIN users u ON u.id = t.user_id
                 WHERE t.is_active = 1 ORDER BY t.id ASC"
            ) ?: [];
            foreach ($members as $m) {
                $name = (string) $m['name'];
                $abbr = (string) ($m['abbreviation'] ?? '');
                $nick = (string) ($m['nickname'] ?? '');
                $isTest = (stripos($name, 'test') !== false);
                if ($name !== '' && !isset($lookup[mb_strtolower($name)])) {
                    $lookup[mb_strtolower($name)] = $name;
                }
                foreach ([$abbr, $nick] as $alias) {
                    if ($alias === '') continue;
                    $key = mb_strtolower($alias);
                    if (!isset($lookup[$key])) {
                        $lookup[$key] = $name;
                    } elseif (!$isTest && stripos($lookup[$key], 'test') !== false) {
                        // existierender Eintrag ist Test-Account → überschreibe mit echtem
                        $lookup[$key] = $name;
                    }
                }
            }
        }
        // Trennzeichen vereinheitlichen
        $raw = preg_replace('#\s*[/;]\s*#u', ', ', $raw);
        $tokens = preg_split('#\s*,\s*#u', $raw);
        $out = [];
        $seen = [];  // dedupe (case-insensitive)
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if ($tok === '') continue;
            $key = mb_strtolower($tok);
            $resolved = $lookup[$key] ?? $tok;
            $dkey = mb_strtolower($resolved);
            if (isset($seen[$dkey])) continue;
            $seen[$dkey] = true;
            $out[] = $resolved;
        }
        return implode(', ', $out);
    }

    /**
     * Wendet normalizeRowName auf alle Item-Zeilen an. Returns [updated_lead, updated_resp, sample_changes].
     */
    public function normalizeAllRows(bool $dryRun = false): array
    {
        $rows = $this->db->query(
            "SELECT id, lead_responsible, responsible
             FROM pp_plan_rows
             WHERE row_type = 'item'
               AND (
                    (lead_responsible IS NOT NULL AND lead_responsible <> '')
                 OR (responsible IS NOT NULL AND responsible <> '')
               )"
        ) ?: [];
        $updLead = 0; $updResp = 0; $samples = [];
        foreach ($rows as $r) {
            $newLead = $this->normalizeRowName((string)($r['lead_responsible'] ?? ''));
            $newResp = $this->normalizeRowName((string)($r['responsible'] ?? ''));
            $up = [];
            if (trim((string)$r['lead_responsible']) !== $newLead) {
                $up['lead_responsible'] = $newLead;
                $updLead++;
                if (count($samples) < 30) $samples[] = ['field' => 'lead', 'from' => $r['lead_responsible'], 'to' => $newLead];
            }
            if (trim((string)$r['responsible']) !== $newResp) {
                $up['responsible'] = $newResp;
                $updResp++;
                if (count($samples) < 30) $samples[] = ['field' => 'resp', 'from' => $r['responsible'], 'to' => $newResp];
            }
            if ($up && !$dryRun) {
                $this->db->update('pp_plan_rows', $up, 'id = ?', [(int)$r['id']]);
            }
        }
        return ['updated_lead' => $updLead, 'updated_responsible' => $updResp, 'sample_changes' => $samples];
    }

    public function getById(int $id): ?array
    {
        $row = $this->db->queryOne(
            "SELECT t.*, u.email FROM pp_team_members t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Neues Team-Mitglied (User oder freie Person).
     * Wenn user_id gesetzt: existiert er schon → werfe Exception.
     */
    public function create(array $data): int
    {
        $userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;
        if ($userId) {
            $existing = $this->db->queryOne("SELECT id FROM pp_team_members WHERE user_id = ?", [$userId]);
            if ($existing) throw new \RuntimeException('Für diesen User existiert bereits ein Team-Eintrag');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('Name erforderlich');

        $abbr = mb_substr(trim((string) ($data['abbreviation'] ?? '')) ?: $this->initials($name), 0, 10);
        $capacity = (int) ($data['capacity_hours'] ?? self::DEFAULT_CAPACITY);
        $color = $data['hex_color'] ?? $this->autoColor($userId ?? crc32($name));
        $maxOrder = (int) ($this->db->queryValue("SELECT COALESCE(MAX(sort_order), 0) FROM pp_team_members") ?? 0);

        return (int) $this->db->insert('pp_team_members', [
            'user_id' => $userId,
            'name' => $name,
            'abbreviation' => $abbr,
            'capacity_hours' => $capacity,
            'hex_color' => $color,
            'sort_order' => $maxOrder + 1,
            'is_active' => 1,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['name', 'abbreviation', 'capacity_hours', 'hex_color', 'sort_order', 'is_active'];
        $update = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) $update[$k] = $data[$k];
        }
        if (isset($update['name'])) $update['name'] = trim((string) $update['name']);
        if (isset($update['abbreviation'])) $update['abbreviation'] = mb_substr(trim((string) $update['abbreviation']), 0, 10);
        if (empty($update)) return;
        $this->db->update('pp_team_members', $update, 'id = ?', [$id]);
    }

    public function deactivate(int $id): void
    {
        $this->db->update('pp_team_members', ['is_active' => 0], 'id = ?', [$id]);
    }

    /**
     * Findet ein Team-Mitglied per Name (case-insensitive). Für den Import.
     */
    public function findByName(string $name): ?array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM pp_team_members WHERE LOWER(name) = LOWER(?) LIMIT 1",
            [trim($name)]
        );
        return $row ?: null;
    }

    // ===== Helpers =====

    private function initials(string $name): string
    {
        $clean = preg_replace('/[^A-Za-zÄÖÜäöüß0-9\s]/u', '', $name);
        $parts = preg_split('/\s+/', trim((string) $clean));
        if (!$parts) return '?';
        if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }

    private function autoColor(int $seed): string
    {
        return self::PALETTE[abs($seed) % count(self::PALETTE)];
    }
}
