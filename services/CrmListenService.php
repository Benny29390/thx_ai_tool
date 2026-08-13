<?php
namespace Services;

use Core\Database;
use Core\AuditLog;

class CrmListenService
{
    public function __construct(private Database $db) {}

    public function liste(bool $inklArchiviert = false): array
    {
        $where = $inklArchiviert ? '' : 'WHERE archiviert = 0';
        return $this->db->query(
            "SELECT id, name, brevo_list_id, beschreibung, anzahl_aktive, archiviert, erstellt_am
             FROM crm_listen $where ORDER BY name ASC"
        );
    }

    public function detail(int $id): ?array
    {
        $l = $this->db->queryOne("SELECT * FROM crm_listen WHERE id = ?", [$id]);
        if (!$l) return null;
        $l['anzahl_aktive_real'] = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM crm_kontakt_listen WHERE listen_id = ? AND status = 'aktiv'", [$id]
        );
        return $l;
    }

    public function anlegen(string $name, ?int $brevoListId = null, ?string $beschreibung = null, ?int $actorUserId = null): int
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Listen-Name leer');
        $existing = $this->db->queryValue("SELECT id FROM crm_listen WHERE name = ? OR brevo_list_id = ?", [$name, $brevoListId]);
        if ($existing) throw new \RuntimeException('Liste existiert bereits (ID ' . $existing . ')');
        $id = (int)$this->db->insert('crm_listen', [
            'name' => $name,
            'brevo_list_id' => $brevoListId,
            'beschreibung' => $beschreibung,
        ]);
        AuditLog::record('crm_liste', (string)$id, 'angelegt', ['name' => $name, 'brevo_list_id' => $brevoListId]);
        return $id;
    }

    public function aktualisieren(int $id, array $daten, ?int $actorUserId = null): bool
    {
        $erlaubt = ['name','beschreibung','archiviert','brevo_list_id'];
        $update = [];
        foreach ($erlaubt as $f) if (array_key_exists($f, $daten)) $update[$f] = $daten[$f];
        if (empty($update)) return true;
        $this->db->update('crm_listen', $update, 'id = ?', [$id]);
        AuditLog::record('crm_liste', (string)$id, 'geaendert', $update);
        return true;
    }
}
