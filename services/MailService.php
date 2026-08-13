<?php
namespace Services;

use Core\Database;

/**
 * Geschäftslogik für die Inbox-UI: Listen, Detail, Aktionen.
 * Klassifikation, Antwort-Generierung, SMTP-Versand und LAM-Hooks
 * sind in eigenen Service-Klassen (MailKlassifikationService,
 * MailAntwortService, MailLamAdapter), die hierüber bedient werden.
 */
class MailService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function listeMails(array $filter = []): array
    {
        // System-Ordner-Mapping: gibt eigene WHERE-Bedingungen
        $sysOrdner = $filter['system_ordner'] ?? null;
        $where = [];
        $params = [];

        if ($sysOrdner === 'papierkorb') {
            $where[] = 'n.geloescht_am IS NOT NULL';
        } else {
            $where[] = 'n.geloescht_am IS NULL';
        }

        if ($sysOrdner === 'posteingang') {
            $where[] = "n.richtung = 'eingang'";
            $where[] = "n.status NOT IN ('archiviert','ignoriert')";
            $where[] = 'n.ordner_id IS NULL';
        } elseif ($sysOrdner === 'gesendet') {
            $where[] = "n.richtung = 'ausgang'";
        } elseif ($sysOrdner === 'archiv') {
            $where[] = "n.status = 'archiviert'";
        } elseif ($sysOrdner === 'spam') {
            $where[] = "n.status = 'ignoriert'";
        } elseif ($sysOrdner === 'markiert') {
            $where[] = 'n.markiert = 1';
        } elseif (!empty($filter['ordner_id'])) {
            $where[] = 'n.ordner_id = ?';
            $params[] = (int)$filter['ordner_id'];
        }

        if (!empty($filter['konto_id'])) {
            $where[] = 'n.konto_id = ?';
            $params[] = (int)$filter['konto_id'];
        }
        if (!empty($filter['richtung']) && !$sysOrdner) {
            $where[] = 'n.richtung = ?';
            $params[] = $filter['richtung'];
        }
        if (isset($filter['nur_ungelesen']) && $filter['nur_ungelesen']) {
            $where[] = 'n.gelesen = 0';
        }
        if (!empty($filter['status']) && !$sysOrdner) {
            $where[] = 'n.status = ?';
            $params[] = $filter['status'];
        }
        if (isset($filter['nur_markiert']) && $filter['nur_markiert'] && $sysOrdner !== 'markiert') {
            $where[] = 'n.markiert = 1';
        }
        if (!empty($filter['suche'])) {
            $where[] = '(n.absender_email LIKE ? OR n.absender_name LIKE ? OR n.betreff LIKE ? OR n.body_plain LIKE ?)';
            $s = '%' . $filter['suche'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($filter['lam_anbieter_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM mail_lam_verknuepfung lvf WHERE lvf.mail_id = n.id AND lvf.typ = "anbieter" AND lvf.ziel_id = ?)';
            $params[] = (string)$filter['lam_anbieter_id'];
        }
        if (!empty($filter['lam_massnahme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM mail_lam_verknuepfung lvf WHERE lvf.mail_id = n.id AND lvf.typ = "massnahme" AND lvf.ziel_id = ?)';
            $params[] = (string)$filter['lam_massnahme_id'];
        }
        if (!empty($filter['absender'])) {
            $where[] = 'LOWER(n.absender_email) = LOWER(?)';
            $params[] = (string)$filter['absender'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        return $this->db->query(
            "SELECT n.id, n.konto_id, n.richtung, n.absender_email, n.absender_name,
                    n.empfaenger_email, n.betreff, n.empfangen_am, n.anhaenge_anzahl,
                    n.status, n.gelesen, n.markiert, n.message_id, n.in_reply_to,
                    SUBSTRING(n.body_plain, 1, 200) AS snippet,
                    k.kategorie, k.kategorie_konfidenz, k.folgeaktion,
                    (SELECT COUNT(*) FROM mail_lam_verknuepfung lv WHERE lv.mail_id = n.id) AS lam_verknuepft
             FROM mail_nachrichten n
             LEFT JOIN mail_klassifikationen k ON k.mail_id = n.id
             {$whereSql}
             ORDER BY n.empfangen_am DESC
             LIMIT 200",
            $params
        );
    }

    public function getDetail(int $mailId): ?array
    {
        $m = $this->db->queryOne(
            "SELECT * FROM mail_nachrichten WHERE id = ? AND geloescht_am IS NULL",
            [$mailId]
        );
        if (!$m) return null;
        $m['anhaenge'] = $this->db->query(
            "SELECT id, dateiname, mime_typ, groesse_bytes FROM mail_anhaenge WHERE mail_id = ?",
            [$mailId]
        );
        $m['klassifikation'] = $this->db->queryOne(
            "SELECT * FROM mail_klassifikationen WHERE mail_id = ?",
            [$mailId]
        );
        $m['lam_verknuepfungen'] = $this->db->query(
            "SELECT typ, ziel_id, automatisch, erstellt_am FROM mail_lam_verknuepfung WHERE mail_id = ?",
            [$mailId]
        );
        $m['antworten'] = $this->db->query(
            "SELECT id, finaler_text, versendet_am, wurde_editiert, auto_versendet
             FROM mail_antworten WHERE eingang_mail_id = ? ORDER BY id DESC LIMIT 10",
            [$mailId]
        );
        return $m;
    }

    public function setzeStatus(int $mailId, string $status): void
    {
        $erlaubt = ['eingang','klassifiziert','beantwortet','archiviert','ignoriert','fehler'];
        if (!in_array($status, $erlaubt, true)) throw new \InvalidArgumentException('Status ungültig.');
        $this->db->execute(
            "UPDATE mail_nachrichten SET status = ? WHERE id = ?",
            [$status, $mailId]
        );
    }

    public function setzeGelesen(int $mailId, bool $gelesen = true): void
    {
        $this->db->execute(
            "UPDATE mail_nachrichten SET gelesen = ? WHERE id = ?",
            [$gelesen ? 1 : 0, $mailId]
        );
    }

    public function toggleMarkiert(int $mailId): bool
    {
        $aktuell = (int)$this->db->queryValue("SELECT markiert FROM mail_nachrichten WHERE id = ?", [$mailId]);
        $neu = $aktuell ? 0 : 1;
        $this->db->execute("UPDATE mail_nachrichten SET markiert = ? WHERE id = ?", [$neu, $mailId]);
        return (bool)$neu;
    }

    public function loescheMail(int $mailId): void
    {
        $this->db->execute(
            "UPDATE mail_nachrichten SET geloescht_am = NOW(), status = 'archiviert' WHERE id = ?",
            [$mailId]
        );
    }

    public function markiereAlsSpam(int $mailId): void
    {
        // Klassifikation auf spam (überschreibt KI), Status auf ignoriert
        $vorh = $this->db->queryValue("SELECT mail_id FROM mail_klassifikationen WHERE mail_id = ?", [$mailId]);
        if ($vorh) {
            $this->db->execute(
                "UPDATE mail_klassifikationen SET kategorie = 'spam', kategorie_konfidenz = 1.00, folgeaktion = 'ignorieren', ki_modell = 'manuell' WHERE mail_id = ?",
                [$mailId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO mail_klassifikationen (mail_id, kategorie, kategorie_konfidenz, folgeaktion, ki_modell) VALUES (?, 'spam', 1.00, 'ignorieren', 'manuell')",
                [$mailId]
            );
        }
        $this->db->execute(
            "UPDATE mail_nachrichten SET status = 'ignoriert' WHERE id = ?",
            [$mailId]
        );
    }

    /**
     * Markiert eine Mail als "kein Spam":
     * - Klassifikation auf 'kommunikation' (Default-Kategorie, weg von spam)
     * - Status zurueck auf 'klassifiziert'
     * - ordner_id NULL (zurueck in Posteingang)
     */
    public function markiereAlsKeinSpam(int $mailId): void
    {
        $vorh = $this->db->queryValue("SELECT mail_id FROM mail_klassifikationen WHERE mail_id = ?", [$mailId]);
        if ($vorh) {
            $this->db->execute(
                "UPDATE mail_klassifikationen SET kategorie = 'kommunikation', kategorie_konfidenz = 1.00, folgeaktion = 'antworten', ki_modell = 'manuell' WHERE mail_id = ?",
                [$mailId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO mail_klassifikationen (mail_id, kategorie, kategorie_konfidenz, folgeaktion, ki_modell) VALUES (?, 'kommunikation', 1.00, 'antworten', 'manuell')",
                [$mailId]
            );
        }
        $this->db->execute(
            "UPDATE mail_nachrichten SET status = 'klassifiziert', ordner_id = NULL, geloescht_am = NULL WHERE id = ?",
            [$mailId]
        );
    }

    /**
     * Liefert die Sidebar-Sichten: nach Anbieter, Maßnahme und Absender gruppiert.
     * Nur Mails mit richtung='eingang' und nicht gelöscht.
     */
    public function personenSicht(?int $kontoId = null): array
    {
        $kontoWhere = '';
        $params = [];
        if ($kontoId) {
            $kontoWhere = ' AND n.konto_id = ?';
            $params[] = $kontoId;
        }

        // Anbieter-Sicht: aus mail_lam_verknuepfung typ='anbieter' joinen
        $anbieter = $this->db->query(
            "SELECT a.id, a.name, a.firma,
                    COUNT(DISTINCT n.id) AS anzahl,
                    SUM(CASE WHEN n.gelesen = 0 THEN 1 ELSE 0 END) AS ungelesen
             FROM mail_lam_verknuepfung lv
             JOIN mail_nachrichten n ON n.id = lv.mail_id AND n.geloescht_am IS NULL AND n.richtung = 'eingang'
             JOIN lam_anbieter a ON a.id = lv.ziel_id AND a.geloescht_am IS NULL
             WHERE lv.typ = 'anbieter' {$kontoWhere}
             GROUP BY a.id, a.name, a.firma
             ORDER BY anzahl DESC, a.name ASC
             LIMIT 50",
            $params
        );

        // Maßnahmen-Sicht: aus mail_lam_verknuepfung typ='massnahme', join domain für Anzeige-Label
        $massnahmen = $this->db->query(
            "SELECT m.id, d.url AS domain, m.linktext, m.status,
                    COUNT(DISTINCT n.id) AS anzahl,
                    SUM(CASE WHEN n.gelesen = 0 THEN 1 ELSE 0 END) AS ungelesen
             FROM mail_lam_verknuepfung lv
             JOIN mail_nachrichten n ON n.id = lv.mail_id AND n.geloescht_am IS NULL AND n.richtung = 'eingang'
             JOIN lam_massnahmen m ON m.id = lv.ziel_id AND m.geloescht_am IS NULL
             LEFT JOIN lam_domains d ON d.id = m.domain_id
             WHERE lv.typ = 'massnahme' {$kontoWhere}
             GROUP BY m.id, d.url, m.linktext, m.status
             ORDER BY anzahl DESC
             LIMIT 50",
            $params
        );

        // Absender-Sicht: Top-Absender (sortiert nach Anzahl), inkl. ob ein Anbieter verknüpft ist
        $absender = $this->db->query(
            "SELECT LOWER(n.absender_email) AS email,
                    MAX(n.absender_name) AS name,
                    COUNT(*) AS anzahl,
                    SUM(CASE WHEN n.gelesen = 0 THEN 1 ELSE 0 END) AS ungelesen,
                    (SELECT a2.name FROM mail_lam_verknuepfung lv2
                       JOIN lam_anbieter a2 ON a2.id = lv2.ziel_id AND a2.geloescht_am IS NULL
                      WHERE lv2.mail_id = MAX(n.id) AND lv2.typ = 'anbieter' LIMIT 1) AS anbieter_name
             FROM mail_nachrichten n
             WHERE n.geloescht_am IS NULL AND n.richtung = 'eingang' {$kontoWhere}
             GROUP BY LOWER(n.absender_email)
             ORDER BY anzahl DESC, email ASC
             LIMIT 30",
            $params
        );

        return [
            'anbieter' => $anbieter,
            'massnahmen' => $massnahmen,
            'absender' => $absender,
        ];
    }

    /**
     * Ordner-Baum + System-Ordner mit Counts.
     * System-Ordner sind virtuell (basieren auf status/richtung/geloescht_am),
     * manuelle Ordner liegen in mail_ordner.
     */
    public function ordnerBaum(?int $kontoId = null): array
    {
        $kontoWhere = '';
        $params = [];
        if ($kontoId) {
            $kontoWhere = ' AND konto_id = ?';
            $params[] = $kontoId;
        }

        // System-Ordner berechnen
        $zaehle = function(string $where, array $par) {
            $row = $this->db->queryOne(
                "SELECT COUNT(*) AS anzahl, SUM(CASE WHEN gelesen = 0 THEN 1 ELSE 0 END) AS ungelesen
                 FROM mail_nachrichten WHERE $where",
                $par
            );
            return [
                'anzahl' => (int)($row['anzahl'] ?? 0),
                'ungelesen' => (int)($row['ungelesen'] ?? 0),
            ];
        };

        $system = [];
        $system['posteingang'] = array_merge(
            ['id' => 'posteingang', 'name' => 'Posteingang', 'icon' => '📥'],
            $zaehle("richtung='eingang' AND geloescht_am IS NULL AND status NOT IN ('archiviert','ignoriert') AND ordner_id IS NULL" . $kontoWhere, $params)
        );
        $system['markiert'] = array_merge(
            ['id' => 'markiert', 'name' => 'Markiert', 'icon' => '★'],
            $zaehle("markiert = 1 AND geloescht_am IS NULL" . $kontoWhere, $params)
        );
        $system['gesendet'] = array_merge(
            ['id' => 'gesendet', 'name' => 'Gesendet', 'icon' => '📤'],
            $zaehle("richtung='ausgang' AND geloescht_am IS NULL" . $kontoWhere, $params)
        );
        $system['archiv'] = array_merge(
            ['id' => 'archiv', 'name' => 'Archiv', 'icon' => '🗄'],
            $zaehle("status='archiviert' AND geloescht_am IS NULL" . $kontoWhere, $params)
        );
        $system['spam'] = array_merge(
            ['id' => 'spam', 'name' => 'Spam', 'icon' => '🚫'],
            $zaehle("status='ignoriert' AND geloescht_am IS NULL" . $kontoWhere, $params)
        );
        $system['papierkorb'] = array_merge(
            ['id' => 'papierkorb', 'name' => 'Papierkorb', 'icon' => '🗑'],
            $zaehle("geloescht_am IS NOT NULL" . $kontoWhere, $params)
        );

        // Manuelle Ordner mit Counts
        $manuell = $this->db->query(
            "SELECT o.id, o.name, o.parent_id, o.farbe, o.sortierung,
                    (SELECT COUNT(*) FROM mail_nachrichten n WHERE n.ordner_id = o.id AND n.geloescht_am IS NULL) AS anzahl,
                    (SELECT COUNT(*) FROM mail_nachrichten n WHERE n.ordner_id = o.id AND n.geloescht_am IS NULL AND n.gelesen = 0) AS ungelesen
             FROM mail_ordner o
             WHERE 1=1" . ($kontoId ? ' AND (o.konto_id = ? OR o.konto_id IS NULL)' : '') . "
             ORDER BY o.sortierung ASC, o.name ASC",
            $kontoId ? [$kontoId] : []
        );

        return [
            'system' => array_values($system),
            'ordner' => $manuell,
        ];
    }

    public function speichereOrdner(?int $id, string $name, ?int $parentId, ?string $farbe, ?int $kontoId, ?int $userId): int
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name erforderlich.');
        if (strlen($name) > 255) throw new \InvalidArgumentException('Name zu lang.');
        if ($id) {
            $this->db->execute(
                "UPDATE mail_ordner SET name = ?, parent_id = ?, farbe = ?, konto_id = ? WHERE id = ?",
                [$name, $parentId ?: null, $farbe ?: null, $kontoId ?: null, $id]
            );
            return $id;
        }
        $this->db->execute(
            "INSERT INTO mail_ordner (konto_id, name, parent_id, farbe, erstellt_von_user_id) VALUES (?, ?, ?, ?, ?)",
            [$kontoId ?: null, $name, $parentId ?: null, $farbe ?: null, $userId]
        );
        return (int)$this->db->lastInsertId();
    }

    public function loescheOrdner(int $id): void
    {
        // Mails aus dem Ordner verlieren ordner_id (FK ON DELETE SET NULL)
        $this->db->execute("DELETE FROM mail_ordner WHERE id = ?", [$id]);
    }

    public function verschiebeMail(int $mailId, $ziel): void
    {
        // $ziel: int (Ordner-ID) oder string ('posteingang','archiv','spam','papierkorb')
        if (is_numeric($ziel)) {
            $this->db->execute(
                "UPDATE mail_nachrichten SET ordner_id = ?, status = CASE WHEN status IN ('archiviert','ignoriert') THEN 'klassifiziert' ELSE status END, geloescht_am = NULL WHERE id = ?",
                [(int)$ziel, $mailId]
            );
            return;
        }
        switch ($ziel) {
            case 'posteingang':
                $this->db->execute("UPDATE mail_nachrichten SET ordner_id = NULL, status = 'klassifiziert', geloescht_am = NULL WHERE id = ?", [$mailId]);
                break;
            case 'archiv':
                $this->db->execute("UPDATE mail_nachrichten SET status = 'archiviert', geloescht_am = NULL WHERE id = ?", [$mailId]);
                break;
            case 'spam':
                $this->markiereAlsSpam($mailId);
                break;
            case 'papierkorb':
                $this->loescheMail($mailId);
                break;
            default:
                throw new \InvalidArgumentException('Unbekanntes Verschiebe-Ziel.');
        }
    }

    public function ungelesenZaehler(?int $kontoId = null): array
    {
        $where = ['geloescht_am IS NULL', "richtung = 'eingang'", 'gelesen = 0'];
        $params = [];
        if ($kontoId) {
            $where[] = 'konto_id = ?';
            $params[] = $kontoId;
        }
        $zahl = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM mail_nachrichten WHERE " . implode(' AND ', $where),
            $params
        );
        return ['ungelesen' => $zahl];
    }
}
