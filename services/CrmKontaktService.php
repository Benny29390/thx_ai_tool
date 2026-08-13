<?php
namespace Services;

use Core\Database;
use Core\AuditLog;

/**
 * CrmKontaktService — CRUD + Filter + Aktivitäten-Log für Kontakte.
 *
 * Sichtbarkeitsmodell (Hybrid):
 *   - Admin/Manager: alles
 *   - andere mit CAP_CRM: eigene Kontakte ODER Kontakte ihrer Kunden
 *     ODER Kontakte mit Tags die fuer alle CRM-User freigeschaltet sind
 */
class CrmKontaktService
{
    public function __construct(private Database $db) {}

    // ───────────────────────────────────────────────────────────────────────
    // Liste / Filter
    // ───────────────────────────────────────────────────────────────────────

    public function liste(array $filter = []): array
    {
        $where = ['k.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            // Bewusst KEIN MySQL-FULLTEXT — der hat unerwartete Token/Fuzzy-Effekte bei kurzen Strings.
            // Stattdessen explizite LIKE-Suche über Vorname, Nachname, E-Mail, Funktion und Firma.
            $where[] = "(k.vorname LIKE ? OR k.nachname LIKE ? OR k.email_primaer LIKE ? OR k.email_zweit LIKE ? OR k.funktion LIKE ? OR f.firmenname LIKE ?)";
            $like = '%' . $filter['suche'] . '%';
            for ($i = 0; $i < 6; $i++) $params[] = $like;
        }
        // Multi-Filter: Strings ODER Arrays sind erlaubt — Arrays werden IN (...)
        $multiCols = ['kontakt_status' => 'k.kontakt_status', 'opt_in_status' => 'k.opt_in_status'];
        foreach ($multiCols as $key => $col) {
            if (!empty($filter[$key])) {
                $werte = is_array($filter[$key]) ? array_values(array_filter($filter[$key], fn($v) => $v !== '' && $v !== null)) : [$filter[$key]];
                if (count($werte) === 1) {
                    $where[] = "$col = ?"; $params[] = $werte[0];
                } elseif (count($werte) > 1) {
                    $placeholders = implode(',', array_fill(0, count($werte), '?'));
                    $where[] = "$col IN ($placeholders)";
                    foreach ($werte as $w) $params[] = $w;
                }
            }
        }
        if (!empty($filter['firma_id'])) {
            $where[] = 'k.firma_id = ?';
            $params[] = (int)$filter['firma_id'];
        }
        // Tags: Multi-Select. Modus 'und' = ALLE Tags, Modus 'oder' = MIND. 1 Tag (Default oder)
        if (!empty($filter['tag_ids'])) {
            $tagIds = is_array($filter['tag_ids']) ? array_map('intval', $filter['tag_ids']) : [(int)$filter['tag_ids']];
            $tagIds = array_filter($tagIds);
            $modus = ($filter['tag_modus'] ?? 'oder') === 'und' ? 'und' : 'oder';
            if ($tagIds) {
                if ($modus === 'und') {
                    foreach ($tagIds as $tid) {
                        $where[] = 'EXISTS (SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = k.id AND tag_id = ?)';
                        $params[] = $tid;
                    }
                } else {
                    $ph = implode(',', array_fill(0, count($tagIds), '?'));
                    $where[] = "EXISTS (SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = k.id AND tag_id IN ($ph))";
                    foreach ($tagIds as $tid) $params[] = $tid;
                }
            }
        }
        if (!empty($filter['ohne_tag_ids'])) {
            $ohne = is_array($filter['ohne_tag_ids']) ? array_map('intval', $filter['ohne_tag_ids']) : [(int)$filter['ohne_tag_ids']];
            $ohne = array_filter($ohne);
            foreach ($ohne as $tid) {
                $where[] = 'NOT EXISTS (SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = k.id AND tag_id = ?)';
                $params[] = $tid;
            }
        }
        // Listen: Multi-Select. ODER (mind. eine).
        if (!empty($filter['listen_ids'])) {
            $listenIds = is_array($filter['listen_ids']) ? array_map('intval', $filter['listen_ids']) : [(int)$filter['listen_ids']];
            $listenIds = array_filter($listenIds);
            if ($listenIds) {
                $ph = implode(',', array_fill(0, count($listenIds), '?'));
                $where[] = "EXISTS (SELECT 1 FROM crm_kontakt_listen WHERE kontakt_id = k.id AND listen_id IN ($ph) AND status = 'aktiv')";
                foreach ($listenIds as $lid) $params[] = $lid;
            }
        }
        // Legacy-Einzel-Felder weiter unterstützen
        if (!empty($filter['tag_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = k.id AND tag_id = ?)';
            $params[] = (int)$filter['tag_id'];
        }
        if (!empty($filter['ohne_tag_id'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = k.id AND tag_id = ?)';
            $params[] = (int)$filter['ohne_tag_id'];
        }
        if (!empty($filter['listen_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM crm_kontakt_listen WHERE kontakt_id = k.id AND listen_id = ? AND status = 'aktiv')";
            $params[] = (int)$filter['listen_id'];
        }
        if (!empty($filter['ohne_firma'])) {
            $where[] = 'k.firma_id IS NULL';
        }
        if (!empty($filter['mit_zoho_legacy'])) {
            $where[] = 'k.legacy_zoho_id IS NOT NULL';
        }

        $sortMap = [
            'name'         => 'k.nachname',
            'vorname'      => 'k.vorname',
            'email'        => 'k.email_primaer',
            'firma'        => 'f.firmenname',
            'erstellt_am'  => 'k.erstellt_am',
            'geaendert_am' => 'k.geaendert_am',
            'thx_score'    => 'k.thx_score',
            'tags'         => 'tags_joined',
        ];
        $sortKey = $filter['sort'] ?? 'nachname';
        $sortCol = $sortMap[$sortKey] ?? 'k.nachname';
        $sortDir = (isset($filter['order']) && strtolower($filter['order']) === 'desc') ? 'DESC' : 'ASC';
        // Leere Werte bei Tags / Score / Firma immer ans Ende
        $orderBy = (in_array($sortKey, ['tags','thx_score','firma','geaendert_am'], true))
            ? "($sortCol IS NULL OR $sortCol = '') ASC, $sortCol $sortDir"
            : "$sortCol $sortDir";

        $limit  = isset($filter['limit'])  ? max(1, min(500, (int)$filter['limit']))  : 50;
        $offset = isset($filter['offset']) ? max(0, (int)$filter['offset']) : 0;

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = $this->db->query(
            "SELECT k.id, k.anrede, k.titel, k.vorname, k.nachname, k.funktion, k.abteilung,
                    k.email_primaer, k.telefon, k.mobil, k.website, k.kontakt_status, k.opt_in_status,
                    k.thx_score, k.foto_path, k.erstellt_am, k.geaendert_am,
                    f.id AS firma_id, f.firmenname,
                    (SELECT GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR '|||') FROM crm_kontakt_tags kt JOIN crm_tags t ON t.id = kt.tag_id WHERE kt.kontakt_id = k.id) AS tags_joined
             FROM crm_kontakte k
             LEFT JOIN crm_firmen f ON f.id = k.firma_id AND f.geloescht_am IS NULL
             $whereSql
             ORDER BY $orderBy, k.id ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        foreach ($rows as &$r) {
            $r['tags'] = !empty($r['tags_joined']) ? explode('|||', $r['tags_joined']) : [];
            unset($r['tags_joined']);
        }

        $gesamt = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM crm_kontakte k
             LEFT JOIN crm_firmen f ON f.id = k.firma_id AND f.geloescht_am IS NULL
             $whereSql",
            $params
        );

        return ['eintraege' => $rows, 'gesamt' => $gesamt, 'limit' => $limit, 'offset' => $offset];
    }

    // ───────────────────────────────────────────────────────────────────────
    // Detail
    // ───────────────────────────────────────────────────────────────────────

    public function detail(int $id): ?array
    {
        $k = $this->db->queryOne(
            "SELECT k.*, f.firmenname
             FROM crm_kontakte k
             LEFT JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.id = ? AND k.geloescht_am IS NULL",
            [$id]
        );
        if (!$k) return null;

        $k['tags']     = $this->ladeTags($id);
        $k['listen']   = $this->ladeListen($id);
        $k['adressen'] = $this->db->query(
            "SELECT * FROM crm_adressen WHERE kontakt_id = ? ORDER BY ist_primaer DESC, typ ASC",
            [$id]
        );
        $k['social']   = $this->db->query(
            "SELECT plattform, url FROM crm_social_links WHERE kontakt_id = ?",
            [$id]
        );
        $k['kunden_zuordnung'] = $this->db->query(
            "SELECT kz.customer_id, kz.rolle, c.name AS customer_name
             FROM crm_kunden_zuordnung kz JOIN customers c ON c.id = kz.customer_id
             WHERE kz.kontakt_id = ?",
            [$id]
        );

        // Andere Kontakte derselben Firma (max 20)
        $k['firma_kontakte'] = [];
        if (!empty($k['firma_id'])) {
            $k['firma_kontakte'] = $this->db->query(
                "SELECT id, vorname, nachname, funktion, email_primaer, foto_path
                 FROM crm_kontakte
                 WHERE firma_id = ? AND id <> ? AND geloescht_am IS NULL
                 ORDER BY nachname ASC LIMIT 20",
                [(int)$k['firma_id'], $id]
            );
        }

        // Brevo-E-Mail-Events nach Kampagne gruppiert
        $k['mails'] = $this->db->query(
            "SELECT
                COALESCE(campaign_name, '(unbenannte Kampagne)') AS campaign_name,
                COALESCE(campaign_id, 0) AS campaign_id,
                MIN(empfangen_am) AS erster_event,
                MAX(empfangen_am) AS letzter_event,
                SUM(event_typ = 'sent') AS n_sent,
                SUM(event_typ = 'delivered') AS n_delivered,
                SUM(event_typ = 'open') AS n_open,
                SUM(event_typ = 'click') AS n_click,
                SUM(event_typ IN ('hard_bounce','soft_bounce')) AS n_bounce,
                SUM(event_typ IN ('unsubscribed','spam')) AS n_unsubscribe,
                COUNT(*) AS n_events
             FROM crm_brevo_events
             WHERE kontakt_id = ?
             GROUP BY campaign_id, campaign_name
             ORDER BY letzter_event DESC",
            [$id]
        );

        // Aktivitäts-Stats für Sidebar
        $k['stats'] = $this->db->queryOne(
            "SELECT
                COUNT(*) AS aktivitaeten_gesamt,
                MAX(CASE WHEN typ IN ('mail_open','mail_click') THEN erstellt_am END) AS letzter_mail_kontakt,
                SUM(CASE WHEN typ = 'mail_open' THEN 1 ELSE 0 END) AS mails_geoeffnet,
                SUM(CASE WHEN typ = 'mail_click' THEN 1 ELSE 0 END) AS mails_geklickt,
                SUM(CASE WHEN typ = 'telefonat' THEN 1 ELSE 0 END) AS anrufe,
                SUM(CASE WHEN typ = 'notiz' THEN 1 ELSE 0 END) AS notizen,
                MAX(erstellt_am) AS letzte_aktivitaet
             FROM crm_aktivitaeten WHERE kontakt_id = ?",
            [$id]
        );

        // Wissensdatenbank-Sync-Status
        $k['wissens_doc'] = $this->db->queryOne(
            "SELECT d.id, d.updated_at, d.is_active, d.customer_id,
                    (SELECT COUNT(*) FROM knowledge_chunks WHERE document_id = d.id) AS chunks,
                    (SELECT name FROM customers WHERE id = d.customer_id) AS customer_name
             FROM knowledge_documents d
             WHERE d.source_type = 'crm_kontakt' AND d.external_id = ? LIMIT 1",
            ['kontakt:' . $id]
        );
        $k['wissens_queued'] = (bool) $this->db->queryValue(
            "SELECT 1 FROM crm_sync_queue WHERE entity_typ = 'kontakt' AND entity_id = ? LIMIT 1",
            [$id]
        );

        return $k;
    }

    public function ladeTags(int $kontaktId): array
    {
        return $this->db->query(
            "SELECT t.id, t.name, t.slug, t.farbe
             FROM crm_kontakt_tags kt JOIN crm_tags t ON t.id = kt.tag_id
             WHERE kt.kontakt_id = ?
             ORDER BY t.name ASC",
            [$kontaktId]
        );
    }

    public function ladeListen(int $kontaktId): array
    {
        return $this->db->query(
            "SELECT l.id, l.name, l.brevo_list_id, kl.status, kl.beigetreten_am
             FROM crm_kontakt_listen kl JOIN crm_listen l ON l.id = kl.listen_id
             WHERE kl.kontakt_id = ?
             ORDER BY l.name ASC",
            [$kontaktId]
        );
    }

    public function ladeAktivitaeten(int $kontaktId, int $limit = 100): array
    {
        return $this->db->query(
            "SELECT * FROM crm_aktivitaeten
             WHERE kontakt_id = ?
             ORDER BY erstellt_am DESC, id DESC
             LIMIT ?",
            [$kontaktId, $limit]
        );
    }

    // ───────────────────────────────────────────────────────────────────────
    // CRUD
    // ───────────────────────────────────────────────────────────────────────

    public function anlegen(array $daten, ?int $actorUserId = null): int
    {
        $email = trim((string)($daten['email_primaer'] ?? ''));
        if ($email === '') {
            throw new \InvalidArgumentException('E-Mail (primaer) ist Pflicht');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-Mail ungueltig: ' . $email);
        }
        // Dublette?
        $existing = $this->db->queryValue("SELECT id FROM crm_kontakte WHERE email_primaer = ?", [$email]);
        if ($existing) {
            throw new \RuntimeException('Kontakt mit dieser E-Mail existiert bereits (ID ' . $existing . ')');
        }

        $felder = $this->extrahiereFelder($daten);
        $felder['email_primaer'] = $email;
        $felder['nachname'] = trim((string)($daten['nachname'] ?? '')) ?: '(unbekannt)';
        $felder['erstellt_durch'] = $actorUserId;

        $id = (int)$this->db->insert('crm_kontakte', $felder);
        $this->logAktivitaet($id, 'kontakt_angelegt', 'Kontakt angelegt', null, ['quelle' => $daten['quelle'] ?? 'manuell'], $actorUserId);
        AuditLog::record('crm_kontakt', (string)$id, 'angelegt', ['email' => $email]);
        CrmKnowledgeSyncQueue::enqueueKontakt($id);
        return $id;
    }

    public function aktualisieren(int $id, array $daten, ?int $actorUserId = null): bool
    {
        $bestehend = $this->db->queryOne("SELECT * FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$id]);
        if (!$bestehend) return false;

        $felder = $this->extrahiereFelder($daten);
        $felder['geaendert_durch'] = $actorUserId;
        $felder['geaendert_am']    = date('Y-m-d H:i:s');

        // Diff fuer Audit-Log
        $diff = [];
        foreach ($felder as $key => $value) {
            $alt = $bestehend[$key] ?? null;
            if ((string)$alt !== (string)$value) {
                $diff[$key] = ['alt' => $alt, 'neu' => $value];
            }
        }
        if (empty($diff)) return true; // nichts zu tun

        $this->db->update('crm_kontakte', $felder, 'id = ?', [$id]);
        $this->logAktivitaet($id, 'kontakt_geaendert', 'Kontakt geaendert', null, ['diff' => $diff], $actorUserId);
        AuditLog::record('crm_kontakt', (string)$id, 'geaendert', $diff);
        CrmKnowledgeSyncQueue::enqueueKontakt($id);
        // Wenn firma_id gewechselt hat: alte+neue Firma re-syncen (Kontakt-Liste ändert sich)
        if (isset($diff['firma_id'])) {
            if (!empty($diff['firma_id']['alt'])) CrmKnowledgeSyncQueue::enqueueFirma((int) $diff['firma_id']['alt']);
            if (!empty($diff['firma_id']['neu'])) CrmKnowledgeSyncQueue::enqueueFirma((int) $diff['firma_id']['neu']);
        }
        return true;
    }

    public function aktualisiereFeld(int $id, string $feld, $wert, ?int $actorUserId = null): bool
    {
        $erlaubt = [
            // Person + Kontaktdaten
            'anrede','titel','vorname','nachname','funktion','abteilung','geburtsdatum',
            'email_primaer','email_zweit','telefon','telefon_alt','mobil','fax','website',
            // Firma + Status
            'firma_id','firma_status','shared_email','kontakt_status','lead_quelle','opt_in_status','thx_score',
            'kontakt_besitzer_user_id','kuendigungsoption','stand_datensatz','layout_name',
            // Profil
            'interessen','merkmale','beschreibung','bevorzugtes_thema',
            // Verkauf
            'asana_task_gid','deal_wert','deal_stufe',
            // Lead-Magnet
            'lead_magnet_name','lead_magnet_url',
            // Podcast
            'podcast_titel','podcast_subtitel','podcast_release_datum','podcast_release_url','podcast_release_mail',
            // UTM / Herkunft
            'utm_source','utm_medium','utm_campaign','utm_content','utm_term','herkunft_referrer',
            // Trigger-Flags + Sync
            'trigger_kontaktformular','trigger_terminbuchung','trigger_strategie_check',
            'trigger_lead_magnet','trigger_test','ac_sync',
        ];
        if (!in_array($feld, $erlaubt, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt: ' . $feld);
        }
        return $this->aktualisieren($id, [$feld => $wert], $actorUserId);
    }

    public function softDelete(int $id, ?int $actorUserId = null): bool
    {
        $row = $this->db->queryOne("SELECT email_primaer FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$id]);
        if (!$row) return false;

        $this->db->update('crm_kontakte', [
            'geloescht_am' => date('Y-m-d H:i:s'),
            'geloescht_durch' => $actorUserId,
        ], 'id = ?', [$id]);

        // Tombstone
        $this->db->insert('crm_loesch_events', [
            'entity_typ' => 'kontakt',
            'entity_id' => $id,
            'geloescht_durch' => $actorUserId,
            'art' => 'soft',
            'grund' => 'manuell',
        ]);
        AuditLog::record('crm_kontakt', (string)$id, 'soft_deleted', ['email' => $row['email_primaer']]);
        CrmKnowledgeSyncQueue::enqueueKontakt($id); // Sync löscht den Doc, da Kontakt jetzt unsichtbar
        return true;
    }

    public function wiederherstellen(int $id, ?int $actorUserId = null): bool
    {
        $this->db->update('crm_kontakte', [
            'geloescht_am' => null,
            'geloescht_durch' => null,
        ], 'id = ?', [$id]);
        AuditLog::record('crm_kontakt', (string)$id, 'wiederhergestellt', []);
        CrmKnowledgeSyncQueue::enqueueKontakt($id);
        return true;
    }

    // ───────────────────────────────────────────────────────────────────────
    // Tag-Pflege
    // ───────────────────────────────────────────────────────────────────────

    public function setzeTag(int $kontaktId, int $tagId, ?int $actorUserId = null): void
    {
        $existiert = $this->db->queryValue(
            "SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = ? AND tag_id = ?",
            [$kontaktId, $tagId]
        );
        if ($existiert) return;

        $this->db->execute(
            "INSERT INTO crm_kontakt_tags (kontakt_id, tag_id, vergeben_durch) VALUES (?, ?, ?)",
            [$kontaktId, $tagId, $actorUserId]
        );
        $this->db->execute("UPDATE crm_tags SET anzahl_kontakte = anzahl_kontakte + 1 WHERE id = ?", [$tagId]);
        $tagName = $this->db->queryValue("SELECT name FROM crm_tags WHERE id = ?", [$tagId]);
        $this->logAktivitaet($kontaktId, 'tag_hinzugefuegt', "Tag „$tagName" . '"', null, ['tag_id' => $tagId], $actorUserId);
        CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
    }

    public function entferneTag(int $kontaktId, int $tagId, ?int $actorUserId = null): void
    {
        $deleted = $this->db->execute(
            "DELETE FROM crm_kontakt_tags WHERE kontakt_id = ? AND tag_id = ?",
            [$kontaktId, $tagId]
        );
        if ($deleted > 0) {
            $this->db->execute("UPDATE crm_tags SET anzahl_kontakte = GREATEST(anzahl_kontakte - 1, 0) WHERE id = ?", [$tagId]);
            $tagName = $this->db->queryValue("SELECT name FROM crm_tags WHERE id = ?", [$tagId]);
            $this->logAktivitaet($kontaktId, 'tag_entfernt', "Tag „$tagName" . '"', null, ['tag_id' => $tagId], $actorUserId);
            CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
        }
    }

    public function setzeListenMitgliedschaft(int $kontaktId, int $listenId, string $status, ?int $actorUserId = null): void
    {
        $existing = $this->db->queryOne(
            "SELECT status FROM crm_kontakt_listen WHERE kontakt_id = ? AND listen_id = ?",
            [$kontaktId, $listenId]
        );
        if ($existing) {
            if ($existing['status'] === $status) return;
            $this->db->execute(
                "UPDATE crm_kontakt_listen SET status = ?, verlassen_am = ? WHERE kontakt_id = ? AND listen_id = ?",
                [$status, $status === 'aktiv' ? null : date('Y-m-d H:i:s'), $kontaktId, $listenId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO crm_kontakt_listen (kontakt_id, listen_id, status) VALUES (?, ?, ?)",
                [$kontaktId, $listenId, $status]
            );
        }
        $listeName = $this->db->queryValue("SELECT name FROM crm_listen WHERE id = ?", [$listenId]);
        $this->logAktivitaet(
            $kontaktId,
            $status === 'aktiv' ? 'liste_beigetreten' : 'liste_verlassen',
            'Liste „' . $listeName . '" → ' . $status,
            null, ['listen_id' => $listenId, 'status' => $status], $actorUserId
        );
        CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
    }

    // ───────────────────────────────────────────────────────────────────────
    // Adressen
    // ───────────────────────────────────────────────────────────────────────

    public function speichereAdresse(int $kontaktId, array $daten): int
    {
        $felder = [
            'kontakt_id' => $kontaktId,
            'typ'        => $daten['typ'] ?? 'geschaeftlich',
            'ist_primaer' => !empty($daten['ist_primaer']) ? 1 : 0,
            'strasse'    => trim((string)($daten['strasse'] ?? '')),
            'plz'        => trim((string)($daten['plz'] ?? '')),
            'stadt'      => trim((string)($daten['stadt'] ?? '')),
            'bundesland' => trim((string)($daten['bundesland'] ?? '')),
            'land'       => trim((string)($daten['land'] ?? 'Deutschland')),
        ];
        if (!empty($daten['id'])) {
            $this->db->update('crm_adressen', $felder, 'id = ? AND kontakt_id = ?', [(int)$daten['id'], $kontaktId]);
            CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
            return (int)$daten['id'];
        }
        $newId = (int)$this->db->insert('crm_adressen', $felder);
        CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
        return $newId;
    }

    public function loescheAdresse(int $adresseId, int $kontaktId): bool
    {
        $ok = $this->db->execute("DELETE FROM crm_adressen WHERE id = ? AND kontakt_id = ?", [$adresseId, $kontaktId]) > 0;
        if ($ok) CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
        return $ok;
    }

    // ───────────────────────────────────────────────────────────────────────
    // Social-Links (Upsert / Loeschen pro Plattform)
    // ───────────────────────────────────────────────────────────────────────

    public function speichereSocial(int $kontaktId, string $plattform, ?string $url): void
    {
        $erlaubt = ['linkedin','xing','facebook','instagram','twitter_x','twitter','youtube','tiktok','website','sonstiges'];
        $plattform = strtolower(trim($plattform));
        // Alias normalisieren
        if ($plattform === 'twitter') $plattform = 'twitter_x';
        if (!in_array($plattform, $erlaubt, true)) {
            throw new \InvalidArgumentException('Plattform nicht erlaubt: ' . $plattform);
        }
        $url = $url !== null ? trim($url) : '';
        if ($url === '') {
            // leerer URL → bestehenden Eintrag löschen
            $this->db->execute(
                "DELETE FROM crm_social_links WHERE kontakt_id = ? AND plattform = ?",
                [$kontaktId, $plattform]
            );
        } else {
            // Upsert: existiert schon → update, sonst insert
            $existing = $this->db->queryValue(
                "SELECT id FROM crm_social_links WHERE kontakt_id = ? AND plattform = ?",
                [$kontaktId, $plattform]
            );
            if ($existing) {
                $this->db->execute(
                    "UPDATE crm_social_links SET url = ? WHERE id = ?",
                    [$url, $existing]
                );
            } else {
                $this->db->insert('crm_social_links', [
                    'kontakt_id' => $kontaktId,
                    'plattform' => $plattform,
                    'url' => $url,
                ]);
            }
        }
        CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
    }

    // ───────────────────────────────────────────────────────────────────────
    // Foto-Upload (Avatar)
    // ───────────────────────────────────────────────────────────────────────

    /** Lädt ein Bild von einer URL und speichert es als Avatar des Kontakts. */
    public function speichereFotoVonUrl(int $kontaktId, string $url, ?int $actorUserId = null): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Ungültige URL.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ThoxanCRM/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $httpCode >= 400 || strlen($body) === 0) {
            throw new \RuntimeException('Bild konnte nicht geladen werden (HTTP ' . $httpCode . ').');
        }
        if (strlen($body) > 8 * 1024 * 1024) {
            throw new \RuntimeException('Bild zu groß (max 8 MB).');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($body);
        $erlaubt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($erlaubt[$mime])) {
            throw new \RuntimeException('Nur JPEG, PNG, WebP, GIF erlaubt (erkannt: ' . $mime . ').');
        }
        $ext = $erlaubt[$mime];
        $verz = '/var/www/uploads/crm/avatar';
        if (!is_dir($verz)) @mkdir($verz, 0775, true);
        foreach (array_values($erlaubt) as $altExt) {
            $alt = $verz . '/' . $kontaktId . '.' . $altExt;
            if (file_exists($alt)) @unlink($alt);
        }
        $dateiname = $kontaktId . '.' . $ext;
        $ziel = $verz . '/' . $dateiname;
        if (file_put_contents($ziel, $body) === false) {
            throw new \RuntimeException('Speichern fehlgeschlagen.');
        }
        @chmod($ziel, 0664);
        $webPfad = '/uploads/crm/avatar/' . $dateiname;
        $this->db->update('crm_kontakte', ['foto_path' => $webPfad], 'id = ?', [$kontaktId]);
        $this->logAktivitaet($kontaktId, 'kontakt_geaendert', 'Foto aktualisiert (Web)', $url, ['feld' => 'foto_path', 'quelle' => $url], $actorUserId);
        return $webPfad;
    }

    /** Speichert Avatar-Datei unter /uploads/crm/avatar/{id}.ext und updated foto_path. */
    public function speichereFoto(int $kontaktId, array $datei, ?int $actorUserId = null): string
    {
        if (!isset($datei['tmp_name']) || !is_uploaded_file($datei['tmp_name'])) {
            throw new \RuntimeException('Keine gültige Datei hochgeladen.');
        }
        if (($datei['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Datei zu groß (max 5 MB).');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($datei['tmp_name']);
        $erlaubt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($erlaubt[$mime])) {
            throw new \RuntimeException('Nur JPEG, PNG, WebP oder GIF erlaubt (erkannt: ' . $mime . ')');
        }
        $ext = $erlaubt[$mime];
        $verz = '/var/www/uploads/crm/avatar';
        if (!is_dir($verz)) @mkdir($verz, 0775, true);
        // Alte Datei (andere Endung) entfernen, damit kein Karteileichen-Bild bleibt
        foreach (array_values($erlaubt) as $altExt) {
            $alt = $verz . '/' . $kontaktId . '.' . $altExt;
            if (file_exists($alt)) @unlink($alt);
        }
        $dateiname = $kontaktId . '.' . $ext;
        $ziel = $verz . '/' . $dateiname;
        if (!move_uploaded_file($datei['tmp_name'], $ziel)) {
            throw new \RuntimeException('Speichern der Datei fehlgeschlagen.');
        }
        @chmod($ziel, 0664);
        $webPfad = '/uploads/crm/avatar/' . $dateiname;
        $this->db->update('crm_kontakte', ['foto_path' => $webPfad], 'id = ?', [$kontaktId]);
        $this->logAktivitaet($kontaktId, 'kontakt_geaendert', 'Foto aktualisiert', null, ['feld' => 'foto_path'], $actorUserId);
        return $webPfad;
    }

    // ───────────────────────────────────────────────────────────────────────
    // Aktivitäten / Zeitlinie
    // ───────────────────────────────────────────────────────────────────────

    public function logAktivitaet(
        int $kontaktId,
        string $typ,
        ?string $titel = null,
        ?string $inhalt = null,
        ?array $metadata = null,
        ?int $actorUserId = null,
        string $quelle = 'manuell'
    ): int {
        $id = (int)$this->db->insert('crm_aktivitaeten', [
            'kontakt_id'    => $kontaktId,
            'typ'           => $typ,
            'titel'         => $titel,
            'inhalt'        => $inhalt,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'quelle'        => $quelle,
            'actor_user_id' => $actorUserId,
        ]);
        // Aktivitäten, die im Kontakt-Doc auftauchen (Notizen, Telefonate, Meetings,
        // Mail-Events, Opt-Ins, Listen-Events) → Doc neu rendern. Debounce im Worker
        // verhindert Spam bei Brevo-Bulk-Events.
        $relevant = ['notiz','telefonat','meeting','mail_open','mail_click','mail_bounce',
                     'mail_unsubscribe','opt_in_erfasst','doi_bestaetigt','lead_magnet',
                     'liste_beigetreten','liste_verlassen','tag_hinzugefuegt','tag_entfernt'];
        if (in_array($typ, $relevant, true)) {
            CrmKnowledgeSyncQueue::enqueueKontakt($kontaktId);
        }
        return $id;
    }

    // ───────────────────────────────────────────────────────────────────────
    // Helper
    // ───────────────────────────────────────────────────────────────────────

    /** Filtert nur erlaubte Spalten aus dem Input-Array für UPDATE/INSERT */
    private function extrahiereFelder(array $daten): array
    {
        $erlaubt = [
            'anrede','titel','vorname','nachname','funktion','abteilung','geburtsdatum',
            'email_primaer','email_zweit','shared_email','telefon','telefon_alt','mobil','fax','website',
            'firma_id','firma_status','interessen','merkmale','beschreibung','bevorzugtes_thema',
            'kontakt_status','lead_quelle','opt_in_status','thx_score',
            'asana_task_gid','deal_wert','deal_stufe','foto_path',
            'kontakt_besitzer_user_id','brevo_id','legacy_zoho_id','legacy_zoho_json',
            // Zoho-Felder (Migration 002)
            'utm_source','utm_medium','utm_campaign','utm_content','utm_term','herkunft_referrer',
            'lead_magnet_name','lead_magnet_url',
            'podcast_titel','podcast_subtitel','podcast_release_datum','podcast_release_url','podcast_release_mail',
            'ac_sync','kuendigungsoption','stand_datensatz','layout_name',
            'trigger_kontaktformular','trigger_terminbuchung','trigger_strategie_check','trigger_lead_magnet','trigger_test',
        ];
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
