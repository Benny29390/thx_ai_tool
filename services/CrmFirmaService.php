<?php
namespace Services;

use Core\Database;
use Core\AuditLog;

class CrmFirmaService
{
    public function __construct(private Database $db) {}

    public function liste(array $filter = []): array
    {
        $where = ['f.geloescht_am IS NULL'];
        $params = [];
        if (!empty($filter['suche'])) {
            $where[] = "(f.firmenname LIKE ? OR f.branche LIKE ? OR f.website LIKE ? OR f.email LIKE ?)";
            $like = '%' . $filter['suche'] . '%';
            for ($i = 0; $i < 4; $i++) $params[] = $like;
        }
        // Branche: Single (legacy) ODER Array
        if (!empty($filter['branche'])) {
            $werte = is_array($filter['branche']) ? array_values(array_filter($filter['branche'])) : [$filter['branche']];
            if (count($werte) === 1) {
                $where[] = 'f.branche = ?'; $params[] = $werte[0];
            } elseif (count($werte) > 1) {
                $ph = implode(',', array_fill(0, count($werte), '?'));
                $where[] = "f.branche IN ($ph)";
                foreach ($werte as $w) $params[] = $w;
            }
        }
        if (!empty($filter['mit_kontakten'])) {
            $where[] = "EXISTS (SELECT 1 FROM crm_kontakte k WHERE k.firma_id = f.id AND k.geloescht_am IS NULL)";
        }
        if (!empty($filter['ohne_kontakte'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM crm_kontakte k WHERE k.firma_id = f.id AND k.geloescht_am IS NULL)";
        }
        if (!empty($filter['mit_zoho_legacy'])) {
            $where[] = 'f.legacy_zoho_id IS NOT NULL';
        }

        $sortMap = [
            'firmenname'    => 'f.firmenname',
            'branche'       => 'f.branche',
            'kontakte'      => 'anzahl_kontakte',
            'erstellt_am'   => 'f.erstellt_am',
            'geaendert_am'  => 'f.geaendert_am',
            'beschaeftigte' => 'f.beschaeftigte',
        ];
        $sortKey = $filter['sort'] ?? 'firmenname';
        $sortCol = $sortMap[$sortKey] ?? 'f.firmenname';
        $sortDir = (isset($filter['order']) && strtolower($filter['order']) === 'desc') ? 'DESC' : 'ASC';

        $limit = isset($filter['limit']) ? max(1, min(500, (int)$filter['limit'])) : 50;
        $offset = isset($filter['offset']) ? max(0, (int)$filter['offset']) : 0;
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = $this->db->query(
            "SELECT f.id, f.firmenname, f.website, f.branche, f.firmen_typ, f.telefon, f.email, f.beschaeftigte,
                    (SELECT COUNT(*) FROM crm_kontakte k WHERE k.firma_id = f.id AND k.geloescht_am IS NULL) AS anzahl_kontakte,
                    f.erstellt_am, f.geaendert_am
             FROM crm_firmen f
             $whereSql
             ORDER BY $sortCol $sortDir, f.id ASC
             LIMIT $limit OFFSET $offset",
            $params
        );
        $gesamt = (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_firmen f $whereSql", $params);
        return ['eintraege' => $rows, 'gesamt' => $gesamt, 'limit' => $limit, 'offset' => $offset];
    }

    /** Branchen-Liste mit Anzahl (für Filter-Chips). */
    public function branchenMitAnzahl(): array
    {
        return $this->db->query(
            "SELECT branche, COUNT(*) AS anzahl FROM crm_firmen
             WHERE geloescht_am IS NULL AND branche IS NOT NULL AND branche <> ''
             GROUP BY branche ORDER BY anzahl DESC, branche ASC LIMIT 50"
        );
    }

    public function detail(int $id): ?array
    {
        $f = $this->db->queryOne("SELECT * FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$id]);
        if (!$f) return null;
        $f['adressen'] = $this->db->query("SELECT * FROM crm_adressen WHERE firma_id = ? ORDER BY typ", [$id]);
        $f['kontakte'] = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer, telefon, mobil, funktion, foto_path, kontakt_status, opt_in_status
             FROM crm_kontakte
             WHERE firma_id = ? AND geloescht_am IS NULL ORDER BY nachname ASC", [$id]
        );

        // Stats: Aktivitäten + Mails über alle Kontakte der Firma aggregiert
        $f['stats'] = $this->db->queryOne(
            "SELECT
                COUNT(*) AS aktivitaeten_gesamt,
                SUM(CASE WHEN typ = 'mail_open' THEN 1 ELSE 0 END) AS mails_geoeffnet,
                SUM(CASE WHEN typ = 'mail_click' THEN 1 ELSE 0 END) AS mails_geklickt,
                SUM(CASE WHEN typ = 'telefonat' THEN 1 ELSE 0 END) AS anrufe,
                SUM(CASE WHEN typ = 'notiz' THEN 1 ELSE 0 END) AS notizen,
                MAX(erstellt_am) AS letzte_aktivitaet
             FROM crm_aktivitaeten
             WHERE kontakt_id IN (SELECT id FROM crm_kontakte WHERE firma_id = ? AND geloescht_am IS NULL)",
            [$id]
        );

        // Parent-Firma (falls vorhanden)
        if (!empty($f['parent_firma_id'])) {
            $f['parent_firma'] = $this->db->queryOne(
                "SELECT id, firmenname FROM crm_firmen WHERE id = ?",
                [(int)$f['parent_firma_id']]
            );
        }

        // Tochter-Firmen
        $f['tochter_firmen'] = $this->db->query(
            "SELECT id, firmenname FROM crm_firmen WHERE parent_firma_id = ? AND geloescht_am IS NULL ORDER BY firmenname ASC",
            [$id]
        );

        // Wissensdatenbank-Sync-Status
        $f['wissens_doc'] = $this->db->queryOne(
            "SELECT d.id, d.updated_at, d.is_active, d.customer_id,
                    (SELECT COUNT(*) FROM knowledge_chunks WHERE document_id = d.id) AS chunks,
                    (SELECT name FROM customers WHERE id = d.customer_id) AS customer_name
             FROM knowledge_documents d
             WHERE d.source_type = 'crm_firma' AND d.external_id = ? LIMIT 1",
            ['firma:' . $id]
        );
        $f['wissens_queued'] = (bool) $this->db->queryValue(
            "SELECT 1 FROM crm_sync_queue WHERE entity_typ = 'firma' AND entity_id = ? LIMIT 1",
            [$id]
        );

        return $f;
    }

    public function anlegen(array $daten, ?int $actorUserId = null): int
    {
        $name = trim((string)($daten['firmenname'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Firmenname ist Pflicht');

        $felder = $this->extrahiereFelder($daten);
        $felder['firmenname'] = $name;
        $felder['erstellt_durch'] = $actorUserId;
        $id = (int)$this->db->insert('crm_firmen', $felder);

        // Branche-Counter pflegen
        if (!empty($felder['branche'])) {
            $this->db->execute(
                "INSERT INTO crm_branchen (name, anzahl_firmen) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE anzahl_firmen = anzahl_firmen + 1",
                [$felder['branche']]
            );
        }
        AuditLog::record('crm_firma', (string)$id, 'angelegt', ['name' => $name]);
        CrmKnowledgeSyncQueue::enqueueFirma($id);
        return $id;
    }

    public function aktualisieren(int $id, array $daten, ?int $actorUserId = null): bool
    {
        $bestehend = $this->db->queryOne("SELECT * FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$id]);
        if (!$bestehend) return false;
        $felder = $this->extrahiereFelder($daten);
        $felder['geaendert_durch'] = $actorUserId;
        $felder['geaendert_am']    = date('Y-m-d H:i:s');
        $diff = [];
        foreach ($felder as $k => $v) {
            $alt = $bestehend[$k] ?? null;
            if ((string)$alt !== (string)$v) $diff[$k] = ['alt' => $alt, 'neu' => $v];
        }
        if (empty($diff)) return true;
        $this->db->update('crm_firmen', $felder, 'id = ?', [$id]);
        AuditLog::record('crm_firma', (string)$id, 'geaendert', $diff);
        // Firma-Sync kaskadiert auf alle ihre Kontakte (Firma-Daten stehen als
        // Cross-Reference im Kontakt-Doc)
        CrmKnowledgeSyncQueue::enqueueFirma($id);
        return true;
    }

    public function softDelete(int $id, ?int $actorUserId = null): bool
    {
        $this->db->update('crm_firmen', [
            'geloescht_am' => date('Y-m-d H:i:s'),
            'geloescht_durch' => $actorUserId,
        ], 'id = ?', [$id]);
        $this->db->insert('crm_loesch_events', [
            'entity_typ' => 'firma',
            'entity_id' => $id,
            'geloescht_durch' => $actorUserId,
            'art' => 'soft',
        ]);
        AuditLog::record('crm_firma', (string)$id, 'soft_deleted', []);
        CrmKnowledgeSyncQueue::enqueueFirma($id);
        return true;
    }

    private function extrahiereFelder(array $daten): array
    {
        $erlaubt = ['firmenname','website','branche','parent_firma_id','firmen_typ','bewertung',
                    'beschaeftigte','jahreseinnahmen','telefon','fax','email','notizen',
                    'legacy_zoho_id','legacy_zoho_json'];
        $out = [];
        foreach ($erlaubt as $f) {
            if (array_key_exists($f, $daten)) {
                $v = $daten[$f];
                if ($v === '' || $v === false) $v = null;
                if ($f === 'legacy_zoho_json' && is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                $out[$f] = $v;
            }
        }
        return $out;
    }
}
