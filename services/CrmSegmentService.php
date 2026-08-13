<?php
namespace Services;

use Core\Database;
use Core\AuditLog;

class CrmSegmentService
{
    public function __construct(private Database $db) {}

    public function liste(int $userId, bool $admin = false): array
    {
        if ($admin) {
            return $this->db->query("SELECT * FROM crm_segmente ORDER BY name ASC");
        }
        return $this->db->query(
            "SELECT * FROM crm_segmente
             WHERE sichtbarkeit = 'global' OR sichtbarkeit = 'team' OR (sichtbarkeit = 'privat' AND erstellt_durch = ?)
             ORDER BY name ASC",
            [$userId]
        );
    }

    public function speichern(array $daten, ?int $actorUserId = null): int
    {
        $name = trim((string)($daten['name'] ?? ''));
        $filterJson = $daten['filter_json'] ?? null;
        if ($name === '') throw new \InvalidArgumentException('Name leer');
        if (!$filterJson) throw new \InvalidArgumentException('Filter-Definition fehlt');
        if (is_array($filterJson)) $filterJson = json_encode($filterJson, JSON_UNESCAPED_UNICODE);

        $row = [
            'name' => $name,
            'beschreibung' => $daten['beschreibung'] ?? null,
            'filter_json' => $filterJson,
            'sichtbarkeit' => $daten['sichtbarkeit'] ?? 'privat',
        ];
        if (!empty($daten['id'])) {
            $row['geaendert_am'] = date('Y-m-d H:i:s');
            $this->db->update('crm_segmente', $row, 'id = ?', [(int)$daten['id']]);
            AuditLog::record('crm_segment', (string)$daten['id'], 'geaendert', $row);
            return (int)$daten['id'];
        }
        $row['erstellt_durch'] = $actorUserId;
        $id = (int)$this->db->insert('crm_segmente', $row);
        AuditLog::record('crm_segment', (string)$id, 'angelegt', ['name' => $name]);
        return $id;
    }

    public function loeschen(int $id): bool
    {
        $this->db->execute("DELETE FROM crm_segmente WHERE id = ?", [$id]);
        AuditLog::record('crm_segment', (string)$id, 'geloescht', []);
        return true;
    }
}
