<?php
namespace Services;

use Core\Database;
use Core\AuditLog;

class CrmTagService
{
    public function __construct(private Database $db) {}

    public function liste(?string $suche = null): array
    {
        if ($suche !== null && $suche !== '') {
            return $this->db->query(
                "SELECT id, name, slug, farbe, beschreibung, anzahl_kontakte
                 FROM crm_tags WHERE name LIKE ? ORDER BY anzahl_kontakte DESC, name ASC LIMIT 50",
                ['%' . $suche . '%']
            );
        }
        return $this->db->query(
            "SELECT id, name, slug, farbe, beschreibung, anzahl_kontakte
             FROM crm_tags ORDER BY anzahl_kontakte DESC, name ASC"
        );
    }

    public function anlegen(string $name, ?string $farbe = null, ?string $beschreibung = null, ?int $actorUserId = null): int
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Tag-Name leer');
        if (mb_strlen($name) > 80) throw new \InvalidArgumentException('Tag-Name zu lang');

        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name));
        $slug = trim($slug, '-') ?: 'tag-' . substr(md5($name), 0, 6);

        $existing = $this->db->queryValue("SELECT id FROM crm_tags WHERE name = ? OR slug = ?", [$name, $slug]);
        if ($existing) {
            throw new \RuntimeException('Tag existiert bereits (ID ' . $existing . ')');
        }
        $id = (int)$this->db->insert('crm_tags', [
            'name' => $name,
            'slug' => $slug,
            'farbe' => $farbe,
            'beschreibung' => $beschreibung,
            'erstellt_durch' => $actorUserId,
        ]);
        AuditLog::record('crm_tag', (string)$id, 'angelegt', ['name' => $name]);
        return $id;
    }

    public function aktualisieren(int $id, array $daten, ?int $actorUserId = null): bool
    {
        $erlaubt = ['name','farbe','beschreibung'];
        $update = [];
        foreach ($erlaubt as $f) {
            if (array_key_exists($f, $daten)) $update[$f] = $daten[$f];
        }
        if (empty($update)) return true;
        if (isset($update['name'])) {
            $update['slug'] = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($update['name']));
            $update['slug'] = trim($update['slug'], '-');
        }
        $this->db->update('crm_tags', $update, 'id = ?', [$id]);
        AuditLog::record('crm_tag', (string)$id, 'geaendert', $update);
        return true;
    }

    public function loeschen(int $id, ?int $actorUserId = null): bool
    {
        $name = $this->db->queryValue("SELECT name FROM crm_tags WHERE id = ?", [$id]);
        if (!$name) return false;
        $this->db->execute("DELETE FROM crm_tags WHERE id = ?", [$id]);
        AuditLog::record('crm_tag', (string)$id, 'geloescht', ['name' => $name]);
        return true;
    }

    public function setzeSichtbarkeit(int $tagId, bool $fuerAlleCrmUser, ?string $beschreibung = null): void
    {
        $exists = $this->db->queryValue("SELECT 1 FROM crm_tag_sichtbarkeit WHERE tag_id = ?", [$tagId]);
        if ($exists) {
            $this->db->update('crm_tag_sichtbarkeit', [
                'fuer_alle_crm_user' => $fuerAlleCrmUser ? 1 : 0,
                'beschreibung' => $beschreibung,
            ], 'tag_id = ?', [$tagId]);
        } else {
            $this->db->insert('crm_tag_sichtbarkeit', [
                'tag_id' => $tagId,
                'fuer_alle_crm_user' => $fuerAlleCrmUser ? 1 : 0,
                'beschreibung' => $beschreibung,
            ]);
        }
    }
}
