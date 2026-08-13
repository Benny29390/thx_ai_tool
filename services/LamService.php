<?php
namespace Services;

use Core\Database;

/**
 * LamService — Linkaufbau-Management-System.
 *
 * Liefert Dashboard-KPIs, Linkquellen-Pool-Daten und Linkprofil-Daten
 * fuer das LAM-Modul. Greift ausschliesslich auf die `lam_*`-Tabellen
 * sowie die KI-Tool-`customers` zu.
 */
class LamService
{
    // Zentrale Vokabulare — Single Source of Truth für alle Whitelists.
    // Quelle: docs/lam-prototyp/lam-prototyp/docs/lam-spezifikation.md +
    // lam-briefing-01b-pipeline-status.md + Briefing_Linkprofil-Analyse.

    public const MASSNAHME_STATUS = [
        'idee', 'akquise', 'bei_kunde', 'beauftragt', 'bei_anbieter', 'live', 'archiv',
    ];
    public const MASSNAHME_STATUS_LABELS = [
        'idee'         => 'Idee',
        'akquise'      => 'Akquise',
        'bei_kunde'    => 'Beim Kunden',
        'beauftragt'   => 'Beauftragt',
        'bei_anbieter' => 'Beim Anbieter',
        'live'         => 'Live',
        'archiv'       => 'Archiv',
    ];
    public const MASSNAHME_SONDERSTATUS = [
        'normal', 'storniert', 'intern', 'plan_b', 'sammelposten',
    ];
    public const MASSNAHME_SONDERSTATUS_LABELS = [
        'normal'       => 'Normal',
        'storniert'    => 'Storniert',
        'intern'       => 'Intern',
        'plan_b'       => 'Plan B',
        'sammelposten' => 'Sammelposten',
    ];
    public const MASSNAHME_VORGANGSTYP = [
        'erstveroeffentlichung', 're_veroeffentlichung', 'sammelbuchung', 'nachbuchung',
    ];
    public const MASSNAHME_VORGANGSTYP_LABELS = [
        'erstveroeffentlichung' => 'Erstveröffentlichung',
        're_veroeffentlichung'  => 'Re-Veröffentlichung',
        'sammelbuchung'         => 'Sammelbuchung',
        'nachbuchung'           => 'Nachbuchung',
    ];
    public const KONDITION_BUCHUNGSTYP = [
        'gastartikel', 'advertorial', 'pressemitteilung', 'interview', 'verzeichnis', 'startseite',
    ];
    // Backend akzeptiert beide Vokabulare: alte Werte (verifiziert/verworfen) und neue (geprueft/geloescht).
    // Frontend nutzt überall die neuen Werte; alte Daten in der DB bleiben unangetastet.
    public const VERIFIKATION_STATUS = [
        'neu', 'in_arbeit', 'verifiziert', 'veraltet', 'verworfen',
        'geprueft', 'geloescht',
    ];
    public const AUSLAGE_SONDERFALL = [
        'normal', 'storno_mit_weiterberechnung', 'intern', 'sammelposten', 'jahresueberhang',
    ];
    public const ANBIETER_BEZIEHUNG = [
        'neu', 'etabliert', 'vertrauensvoll', 'abgekuehlt',
    ];
    // 17 Spec-Werte + social_media (im Prototyp-Linkprofil-Briefing als 18. Wert ergänzt).
    // Reihenfolge alphabetisch nach Slug (entspricht Label).
    public const VERLINKUNG_LINKART = [
        'blog', 'branchenverzeichnis', 'fachverzeichnis', 'forum', 'kommentarlink',
        'online_magazin', 'partner', 'podcast', 'portal', 'presseportal',
        'referenzprojekt', 'social_media', 'sonstiges', 'spam', 'sponsoring',
        'stellenboerse', 'veranstaltung', 'weiterleitung',
    ];
    // Vokabular für Linkquellen (lam_domains.linkart) — gleiche Werte wie Linkprofil
    // OHNE 'spam' (Spam-Domain würde man nicht als Buchungsquelle pflegen).
    // Reihenfolge alphabetisch nach UI-Label.
    public const DOMAIN_LINKART_LABELS = [
        'blog'                => 'Blog',
        'branchenverzeichnis' => 'Branchenverzeichnis',
        'fachverzeichnis'     => 'Fachverzeichnis',
        'forum'               => 'Forum',
        'kommentarlink'       => 'Kommentarlink',
        'online_magazin'      => 'Online-Magazin',
        'partner'             => 'Partner',
        'podcast'             => 'Podcast',
        'portal'              => 'Portal',
        'presseportal'        => 'Presseportal',
        'referenzprojekt'     => 'Referenzprojekt',
        'social_media'        => 'Social Media',
        'sonstiges'           => 'Sonstiges',
        'sponsoring'          => 'Sponsoring',
        'stellenboerse'       => 'Stellenbörse',
        'veranstaltung'       => 'Veranstaltung',
        'weiterleitung'       => 'Weiterleitung',
    ];
    public const DOMAIN_LINKART = [
        'blog', 'branchenverzeichnis', 'fachverzeichnis', 'forum', 'kommentarlink',
        'online_magazin', 'partner', 'podcast', 'portal', 'presseportal',
        'referenzprojekt', 'social_media', 'sonstiges', 'sponsoring', 'stellenboerse',
        'veranstaltung', 'weiterleitung',
    ];
    public const VERLINKUNG_EMPFEHLUNG = [
        'lassen', 'aendern', 'loeschen', 'disavow', 'geloescht', 'unsicher',
    ];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ---------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------

    /**
     * Erweiterte Dashboard-Widgets — Listen fuer die einzelnen Schnellzugriffe.
     */
    public function getDashboardWidgets(): array
    {
        return [
            'anstehende_massnahmen' => $this->db->query(
                "SELECT m.id, m.status, m.geplant_am, m.veroeffentlicht_am,
                        d.url AS domain_url, c.abbreviation AS customer_kuerzel
                 FROM lam_massnahmen m
                 LEFT JOIN lam_domains d ON d.id = m.domain_id
                 LEFT JOIN customers c ON c.id = m.customer_id
                 WHERE m.geloescht_am IS NULL
                   AND m.status IN ('vorgeschlagen','geplant','beauftragt')
                 ORDER BY m.geplant_am ASC, m.erstellt_am DESC
                 LIMIT 8"
            ),
            'monitoring_alerts' => $this->db->query(
                "SELECT mc.id, mc.zeitpunkt, mc.http_status, mc.link_vorhanden,
                        d.url AS domain_url, c.abbreviation AS customer_kuerzel
                 FROM lam_monitoring_checks mc
                 JOIN lam_massnahmen m ON m.id = mc.massnahme_id
                 JOIN lam_domains d ON d.id = m.domain_id
                 LEFT JOIN customers c ON c.id = m.customer_id
                 WHERE mc.alert_ausgeloest = 1 AND m.geloescht_am IS NULL AND m.monitoring_muted = 0
                 ORDER BY mc.zeitpunkt DESC
                 LIMIT 8"
            ),
            'linkakquise_offen' => $this->db->query(
                "SELECT e.id, e.status, e.kontakt_am, e.naechste_aktion_am,
                        d.url AS domain_url, c.abbreviation AS customer_kuerzel
                 FROM lam_vorschlagsliste_eintraege e
                 JOIN lam_domains d ON d.id = e.domain_id
                 JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
                 LEFT JOIN customers c ON c.id = v.customer_id
                 WHERE e.status = 'in_akquise'
                 ORDER BY e.naechste_aktion_am ASC, e.kontakt_am ASC
                 LIMIT 8"
            ),
            'auslagen_monat' => $this->db->queryOne(
                "SELECT
                    COALESCE(SUM(externe_kosten), 0) AS gesamt_kosten,
                    COALESCE(SUM(weiterverrechnet), 0) AS gesamt_weiterverr,
                    COALESCE(SUM(marge), 0) AS gesamt_marge,
                    COUNT(*) AS anzahl
                 FROM lam_auslagen
                 WHERE geloescht_am IS NULL
                   AND YEAR(thoxan_rechnung_datum) = YEAR(NOW())
                   AND MONTH(thoxan_rechnung_datum) = MONTH(NOW())"
            ),
            'sistrix' => $this->sistrixStatus(),
            'letzte_aktivitaeten' => $this->letzteAktivitaeten(8),
            'marge_trend' => $this->db->query(
                "SELECT MONTH(thoxan_rechnung_datum) AS monat,
                        YEAR(thoxan_rechnung_datum) AS jahr,
                        COALESCE(SUM(marge), 0) AS marge_summe
                 FROM lam_auslagen
                 WHERE geloescht_am IS NULL
                   AND thoxan_rechnung_datum >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY YEAR(thoxan_rechnung_datum), MONTH(thoxan_rechnung_datum)
                 ORDER BY jahr, monat"
            ),
            'sammelposten' => $this->db->query(
                "SELECT m.id, m.status, m.geplant_am, m.veroeffentlicht_am,
                        d.url AS domain_url, c.abbreviation AS customer_kuerzel,
                        a.externe_kosten, a.weiterverrechnet
                 FROM lam_massnahmen m
                 LEFT JOIN lam_domains d ON d.id = m.domain_id
                 LEFT JOIN customers c ON c.id = m.customer_id
                 LEFT JOIN lam_auslagen a ON a.massnahme_id = m.id AND a.geloescht_am IS NULL
                 WHERE m.geloescht_am IS NULL
                   AND (m.sonderstatus = 'sammelposten' OR m.vorgangstyp = 'sammelbuchung')
                 ORDER BY m.geplant_am DESC, m.erstellt_am DESC
                 LIMIT 8"
            ),
        ];
    }

    /**
     * Sistrix-Wochenstatus fuer Dashboard-Widget — fail-safe.
     */
    private function sistrixStatus(): array
    {
        try {
            require_once SERVICES_PATH . '/SistrixService.php';
            $svc = new \Services\SistrixService($this->db);
            return $svc->wochenStatus();
        } catch (\Throwable $e) {
            return ['konfiguriert' => false];
        }
    }

    /**
     * Letzte Audit-Eintraege im LAM-Bereich fuer das Dashboard.
     * Greift auf das globale permission_audit_log zurueck UND auf
     * Erstell-/Aenderungszeitpunkte der LAM-Tabellen (fall-back, da der
     * LAM-Bereich noch kein eigenes Audit-Log hat).
     */
    private function letzteAktivitaeten(int $limit = 8): array
    {
        // Bevorzugt: echtes Audit-Log. Fallback: Union über LAM-Tabellen.
        $audit = $this->db->query("
            SELECT al.aktion, al.entity_typ, al.entity_id, al.zeitpunkt, al.ist_bulk, al.anzahl_betroffen,
                   u.name AS user_name
            FROM lam_audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.zeitpunkt DESC
            LIMIT " . ($limit * 2)
        ) ?: [];
        if (!empty($audit)) {
            $aktivitaeten = [];
            foreach ($audit as $a) {
                $text = $a['ist_bulk']
                    ? sprintf('%s (%d ×) — %s', $a['aktion'], (int)$a['anzahl_betroffen'], $a['user_name'] ?: 'System')
                    : sprintf('%s — %s', $a['aktion'], $a['user_name'] ?: 'System');
                $basepath = null;
                if (!$a['ist_bulk'] && !empty($a['entity_id'])) {
                    $basepath = [
                        'domain' => '/lam/linkquellen/',
                        'massnahme' => '/lam/massnahmen/',
                        'anbieter' => '/lam/anbieter/',
                        'kontakt' => null,
                        'kondition' => null,
                        'linkoption' => '/lam/linkoptionen/',
                    ][$a['entity_typ']] ?? null;
                }
                $aktivitaeten[] = [
                    'typ' => $a['entity_typ'],
                    'ref_id' => $a['entity_id'],
                    'zeitpunkt' => $a['zeitpunkt'],
                    'text' => $text,
                    'basepath' => $basepath,
                ];
            }
            return array_slice($aktivitaeten, 0, $limit);
        }
        // Fallback (wenn Audit-Log noch leer ist)
        $rows = $this->db->query("
            SELECT 'massnahme' AS typ, m.id AS ref_id, m.erstellt_am AS zeitpunkt,
                   CONCAT('Neue Maßnahme: ', d.url) AS text,
                   '/lam/massnahmen/' AS basepath
            FROM lam_massnahmen m
            JOIN lam_domains d ON d.id = m.domain_id
            WHERE m.geloescht_am IS NULL
            ORDER BY m.erstellt_am DESC LIMIT 5
        ") ?: [];
        $rows2 = $this->db->query("
            SELECT 'linkoption' AS typ, e.id AS ref_id, e.erstellt_am AS zeitpunkt,
                   CONCAT('Neuer Akquise-Eintrag: ', d.url) AS text,
                   '/lam/linkoptionen/' AS basepath
            FROM lam_vorschlagsliste_eintraege e
            JOIN lam_domains d ON d.id = e.domain_id
            ORDER BY e.erstellt_am DESC LIMIT 5
        ") ?: [];
        $rows3 = $this->db->query("
            SELECT 'kommunikation' AS typ, k.id AS ref_id, k.zeitpunkt,
                   CONCAT(k.typ, ': ', COALESCE(k.betreff, SUBSTRING(k.inhalt, 1, 60))) AS text,
                   NULL AS basepath
            FROM lam_kommunikation k
            WHERE k.geloescht_am IS NULL
            ORDER BY k.zeitpunkt DESC LIMIT 5
        ") ?: [];
        $alle = array_merge($rows, $rows2, $rows3);
        usort($alle, fn($a, $b) => strcmp($b['zeitpunkt'] ?? '', $a['zeitpunkt'] ?? ''));
        return array_slice($alle, 0, $limit);
    }

    public function getDashboardStats(): array
    {
        $kennzahlen = [
            'domains_gesamt'         => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_domains WHERE geloescht_am IS NULL"
            ),
            'domains_verifiziert'    => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_domains WHERE geloescht_am IS NULL AND verifikation_status = 'verifiziert'"
            ),
            'domains_disqualifiziert'=> $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_domains WHERE geloescht_am IS NULL AND disqualifiziert = 1"
            ),
            'anbieter_gesamt'        => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_anbieter WHERE geloescht_am IS NULL"
            ),
            'kontakte_gesamt'        => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_kontakte WHERE geloescht_am IS NULL"
            ),
            'konditionen_gesamt'     => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_konditionen WHERE geloescht_am IS NULL"
            ),
            'verlinkungen_gesamt'    => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_verlinkungen WHERE geloescht_am IS NULL"
            ),
            'domain_wissen_gesamt'   => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_domain_wissen"
            ),
            'kunden_aktiv'           => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_kunden_config"
            ),
            'massnahmen_offen'       => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_massnahmen
                 WHERE geloescht_am IS NULL
                   AND status NOT IN ('abgeschlossen', 'storniert')"
            ),
            'massnahmen_live'        => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_massnahmen
                 WHERE geloescht_am IS NULL AND status = 'live'"
            ),
            'monitoring_alerts'      => $this->db->queryValue(
                "SELECT COUNT(*) FROM lam_monitoring_checks WHERE alert_ausgeloest = 1"
            ),
        ];

        $proKunde = $this->db->query(
            "SELECT c.id, c.abbreviation, c.name,
                    COUNT(v.id) AS verlinkungen,
                    SUM(CASE WHEN v.empfehlung = 'disavow' THEN 1 ELSE 0 END) AS disavow,
                    SUM(CASE WHEN v.empfehlung = 'unsicher' THEN 1 ELSE 0 END) AS unsicher
             FROM customers c
             JOIN lam_verlinkungen v ON v.customer_id = c.id AND v.geloescht_am IS NULL
             GROUP BY c.id
             ORDER BY verlinkungen DESC"
        );

        $topAnbieter = $this->db->query(
            "SELECT a.id, a.name, COUNT(d.id) AS domain_anzahl
             FROM lam_anbieter a
             LEFT JOIN lam_domains d ON d.anbieter_id = a.id AND d.geloescht_am IS NULL
             WHERE a.geloescht_am IS NULL
             GROUP BY a.id
             HAVING domain_anzahl > 0
             ORDER BY domain_anzahl DESC
             LIMIT 10"
        );

        return [
            'kennzahlen'    => $kennzahlen,
            'pro_kunde'     => $proKunde,
            'top_anbieter'  => $topAnbieter,
        ];
    }

    // ---------------------------------------------------------------------
    // Linkquellen-Pool
    // ---------------------------------------------------------------------

    public function listeLinkquellen(array $filter = []): array
    {
        $where  = ['d.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(d.url LIKE ? OR d.notizen LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['verifikation_status'])) {
            $statusListe = is_array($filter['verifikation_status'])
                ? $filter['verifikation_status']
                : [$filter['verifikation_status']];
            $platzhalter = implode(',', array_fill(0, count($statusListe), '?'));
            $where[] = "d.verifikation_status IN ({$platzhalter})";
            foreach ($statusListe as $s) {
                $params[] = $s;
            }
        }
        if (isset($filter['nur_disqualifiziert']) && $filter['nur_disqualifiziert']) {
            $where[] = 'd.disqualifiziert = 1';
        }
        if (!empty($filter['anbieter_id'])) {
            $where[] = 'd.anbieter_id = ?';
            $params[] = $filter['anbieter_id'];
        }
        if (!empty($filter['ohne_anbieter'])) {
            $where[] = 'd.anbieter_id IS NULL';
        }
        if (!empty($filter['nur_nicht_erreichbar'])) {
            $where[] = '(d.letzter_http_erreichbar = 0)';
        }
        if (!empty($filter['nur_ungeprueft'])) {
            $where[] = 'd.letzter_check_am IS NULL';
        }
        if (!empty($filter['nur_in_wartezeit'])) {
            $where[] = '(d.wartezeit_bis IS NOT NULL AND d.wartezeit_bis >= CURDATE())';
        }
        if (!empty($filter['nur_verfuegbar'])) {
            $where[] = '(d.wartezeit_bis IS NULL OR d.wartezeit_bis < CURDATE())';
        }
        // Whitelist auf konkrete Domain-IDs (für Linkpool-Sichten)
        if (!empty($filter['domain_ids']) && is_array($filter['domain_ids'])) {
            $ids = array_values(array_filter(array_map('strval', $filter['domain_ids'])));
            if (!empty($ids)) {
                $platz = implode(',', array_fill(0, count($ids), '?'));
                $where[] = "d.id IN ({$platz})";
                foreach ($ids as $i) { $params[] = $i; }
            }
        }
        // Linkart-Multi
        if (!empty($filter['linkart'])) {
            $linkarten = is_array($filter['linkart']) ? $filter['linkart'] : [$filter['linkart']];
            $linkarten = array_values(array_filter(array_map('strval', $linkarten), 'strlen'));
            if (!empty($linkarten)) {
                $platz = implode(',', array_fill(0, count($linkarten), '?'));
                $where[] = "d.linkart IN ({$platz})";
                foreach ($linkarten as $la) { $params[] = $la; }
            }
        }
        // Tag-Filter (AND-Logik: Domain muss alle gewaehlten Tags haben)
        if (!empty($filter['tag_ids'])) {
            $tagIds = is_array($filter['tag_ids']) ? $filter['tag_ids'] : [$filter['tag_ids']];
            $tagIds = array_values(array_filter(array_map('intval', $tagIds), fn($v) => $v > 0));
            foreach ($tagIds as $tid) {
                $where[] = "EXISTS (SELECT 1 FROM lam_domain_tag dt WHERE dt.domain_id = d.id AND dt.tag_id = ?)";
                $params[] = $tid;
            }
        }
        // Kunden-Filter (Linkpool): nur Domains, die im Linkpool dieses Kunden liegen.
        if (!empty($filter['customer_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM lam_domain_customer dc WHERE dc.domain_id = d.id AND dc.customer_id = ?)";
            $params[] = (int) $filter['customer_id'];
        }
        if (!empty($filter['ohne_kunde'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM lam_domain_customer dc WHERE dc.domain_id = d.id)";
        }
        // Redaktionelle Bewertung aus der Recherche-Datei (top | bedingt | ablehnen | offen)
        if (!empty($filter['bewertung'])) {
            $bw = is_array($filter['bewertung']) ? $filter['bewertung'] : [$filter['bewertung']];
            $bw = array_values(array_intersect($bw, self::BEWERTUNGEN));
            if ($bw) {
                $ph = implode(',', array_fill(0, count($bw), '?'));
                $where[] = "d.bewertung IN ($ph)";
                foreach ($bw as $b) $params[] = $b;
            }
        }
        // Noch nicht per Sistrix erfasst — bewusst gegen den NEUESTEN Snapshot geprueft, also
        // exakt das, was in der SI/DP-Spalte als "—" steht. So crawlt man nur, was wirklich fehlt.
        // Getrennt nach SI und DP, weil es Teilfaelle gibt (SI vorhanden, DP fehlt).
        if (!empty($filter['ohne_si'])) {
            $where[] = "(SELECT si FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) IS NULL";
        }
        if (!empty($filter['ohne_dp'])) {
            $where[] = "(SELECT dp FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) IS NULL";
        }
        // SI-Range (über aktuellsten Snapshot)
        if (isset($filter['si_min']) && $filter['si_min'] !== '') {
            $where[] = "(SELECT si FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) >= ?";
            $params[] = (float)$filter['si_min'];
        }
        if (isset($filter['si_max']) && $filter['si_max'] !== '') {
            $where[] = "(SELECT si FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) <= ?";
            $params[] = (float)$filter['si_max'];
        }
        // Preis-Range (min-Preis-Kondition)
        if (isset($filter['preis_min']) && $filter['preis_min'] !== '') {
            $where[] = "(SELECT MIN(preis) FROM lam_konditionen k WHERE k.domain_id = d.id AND k.geloescht_am IS NULL) >= ?";
            $params[] = (float)$filter['preis_min'];
        }
        if (isset($filter['preis_max']) && $filter['preis_max'] !== '') {
            $where[] = "(SELECT MIN(preis) FROM lam_konditionen k WHERE k.domain_id = d.id AND k.geloescht_am IS NULL) <= ?";
            $params[] = (float)$filter['preis_max'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $limit  = isset($filter['limit'])  ? max(1, min(500, (int) $filter['limit']))  : 50;
        $offset = isset($filter['offset']) ? max(0, (int) $filter['offset']) : 0;

        $sortMap = [
            'url'                  => 'd.url',
            'verifikation_status'  => 'd.verifikation_status',
            'letzter_check_am'     => 'd.letzter_check_am',
            'erstellt_am'          => 'd.erstellt_am',
            'anbieter'             => 'a.name',
        ];
        $sort  = $sortMap[$filter['sort'] ?? 'erstellt_am'] ?? 'd.erstellt_am';
        $order = (strtolower($filter['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

        $rows = $this->db->query(
            "SELECT d.id, d.url, d.verifikation_status, d.disqualifiziert,
                    d.buchbar_via, d.letzter_check_am, d.sistrix_sichtbar_seit,
                    d.letzter_http_erreichbar, d.letzter_http_status, d.linkart,
                    d.herkunft, d.herkunft_customer_id, d.erstellt_am,
                    -- Notiz-Indikator: TRIMmed Länge > 0 für Tooltip + Icon in Tabelle
                    NULLIF(TRIM(COALESCE(d.notizen, '')), '') AS notiz_kurz,
                    d.beschreibung, d.bewertung,
                    -- Anbieter-Anzeige: Vermittler (performanceliebe/RRDS/eology) wird durch echten
                    --   Anbieter aus Konditionen ersetzt, sofern vorhanden. Sonst bleibt der Vermittler stehen.
                    COALESCE((SELECT ka.id FROM lam_konditionen k JOIN lam_anbieter ka ON ka.id = k.via_anbieter_id
                              WHERE k.domain_id = d.id AND ka.ist_vermittler = 0 AND k.geloescht_am IS NULL
                              ORDER BY k.erstellt_am ASC LIMIT 1),
                             a.id) AS anbieter_id,
                    COALESCE((SELECT ka.name FROM lam_konditionen k JOIN lam_anbieter ka ON ka.id = k.via_anbieter_id
                              WHERE k.domain_id = d.id AND ka.ist_vermittler = 0 AND k.geloescht_am IS NULL
                              ORDER BY k.erstellt_am ASC LIMIT 1),
                             a.name) AS anbieter_name,
                    -- Vermittler-Flag des ANGEZEIGTEN Anbieters (also der nach dem COALESCE oben)
                    COALESCE((SELECT ka.ist_vermittler FROM lam_konditionen k JOIN lam_anbieter ka ON ka.id = k.via_anbieter_id
                              WHERE k.domain_id = d.id AND ka.ist_vermittler = 0 AND k.geloescht_am IS NULL
                              ORDER BY k.erstellt_am ASC LIMIT 1),
                             a.ist_vermittler) AS anbieter_ist_vermittler,
                    (SELECT MIN(preis) FROM lam_konditionen k
                        WHERE k.domain_id = d.id AND k.geloescht_am IS NULL) AS preis_min,
                    (SELECT MAX(preis) FROM lam_konditionen k
                        WHERE k.domain_id = d.id AND k.geloescht_am IS NULL) AS preis_max,
                    (SELECT si FROM lam_kennzahl_snapshots ks
                        WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS si_aktuell,
                    (SELECT dp FROM lam_kennzahl_snapshots ks
                        WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS dp_aktuell,
                    (SELECT erfasst_am FROM lam_kennzahl_snapshots ks
                        WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS si_aktuell_am,
                    (SELECT GROUP_CONCAT(t.name ORDER BY dt.primaer DESC, t.name SEPARATOR '|')
                        FROM lam_domain_tag dt
                        JOIN lam_tags t ON t.id = dt.tag_id
                        WHERE dt.domain_id = d.id AND t.geloescht_am IS NULL) AS tags,
                    (SELECT GROUP_CONCAT(t.id ORDER BY dt.primaer DESC, t.name)
                        FROM lam_domain_tag dt
                        JOIN lam_tags t ON t.id = dt.tag_id
                        WHERE dt.domain_id = d.id AND t.geloescht_am IS NULL) AS tag_ids,
                    (SELECT GROUP_CONCAT(DISTINCT c.abbreviation SEPARATOR '|')
                        FROM lam_domain_customer dc
                        JOIN customers c ON c.id = dc.customer_id
                        WHERE dc.domain_id = d.id) AS kunden,
                    -- Vermittler: Anbieter aus Junction die NICHT der Haupt-Anbieter sind und
                    -- den minimalsten Preis bringen (= günstigster Vermittler). Mehrere Vermittler
                    -- als Pipe-Liste mit Pseudokürzel.
                    (SELECT GROUP_CONCAT(DISTINCT av.name ORDER BY av.name SEPARATOR '|')
                        FROM lam_domain_anbieter da
                        JOIN lam_anbieter av ON av.id = da.anbieter_id AND av.geloescht_am IS NULL
                        WHERE da.domain_id = d.id
                          AND da.rolle = 'vermittler'
                          AND (a.id IS NULL OR da.anbieter_id <> a.id)
                    ) AS vermittler_namen,
                    (SELECT MIN(k2.preis) FROM lam_konditionen k2
                        WHERE k2.domain_id = d.id
                          AND k2.geloescht_am IS NULL
                          AND k2.via_anbieter_id IS NOT NULL
                          AND (a.id IS NULL OR k2.via_anbieter_id <> a.id)
                    ) AS vermittler_preis_min
             FROM lam_domains d
             LEFT JOIN lam_anbieter a ON a.id = d.anbieter_id
             {$whereSql}
             ORDER BY {$sort} {$order}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $gesamt = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_domains d {$whereSql}",
            $params
        );

        return [
            'gesamt' => $gesamt,
            'limit'  => $limit,
            'offset' => $offset,
            'rows'   => $rows,
        ];
    }

    /**
     * Inline-Update fuer Domain-Felder (verifikation_status, disqualifiziert, notizen, anbieter_id, linkart).
     */
    public function aktualisiereDomainFeld(string $id, string $feld, $wert): void
    {
        $erlaubteFelder = ['verifikation_status', 'disqualifiziert', 'notizen', 'anbieter_id', 'linkart', 'buchbar_via', 'herkunft', 'ki_kurzbeschreibung', 'impressum_url', 'wartezeit_bis', 'beschreibung', 'bewertung'];
        // Domain hatte historisch 'geprueft' statt Spec-'verifiziert' — beides erlauben
        $erlaubteVerifikation = ['neu', 'in_arbeit', 'verifiziert', 'geprueft', 'veraltet', 'verworfen', 'geloescht'];

        $vorhanden = $this->db->queryValue(
            "SELECT id FROM lam_domains WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
        if (!$vorhanden) throw new \InvalidArgumentException('Domain nicht gefunden');

        if (!in_array($feld, $erlaubteFelder, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt: ' . $feld);
        }
        if ($feld === 'verifikation_status' && !in_array($wert, $erlaubteVerifikation, true)) {
            throw new \InvalidArgumentException('Ungueltiger Verifikations-Status');
        }
        // Bewertung nur aus dem festen, projektuebergreifenden Vokabular (leer = zuruecksetzen)
        if ($feld === 'bewertung' && $wert !== '' && $wert !== null && !in_array($wert, self::BEWERTUNGEN, true)) {
            throw new \InvalidArgumentException('Ungueltige Bewertung: ' . $wert);
        }
        if ($feld === 'disqualifiziert') $wert = $wert ? 1 : 0;

        $sicheresWert = ($wert === '' || $wert === null) ? null : $wert;
        if (is_string($sicheresWert)) $sicheresWert = trim($sicheresWert);

        $this->db->execute(
            "UPDATE lam_domains SET `{$feld}` = ? WHERE id = ?",
            [$sicheresWert, $id]
        );

        // Audit
        $this->audit('domain.update', 'domain', $id, ['feld' => $feld, 'wert' => $sicheresWert]);

        // Hook: „Veraltet markieren" → Aufgabe „Sistrix/Werte aktualisieren" anlegen
        if ($feld === 'verifikation_status' && $sicheresWert === 'veraltet') {
            try {
                $domain = $this->db->queryOne("SELECT url FROM lam_domains WHERE id = ?", [$id]);
                $offenSchon = $this->db->queryValue(
                    "SELECT id FROM lam_aufgaben
                     WHERE bezug_typ = 'domain' AND bezug_id = ? AND typ = 'update_pruefung' AND status = 'offen'",
                    [$id]
                );
                if (!$offenSchon) {
                    $this->legeAufgabeAn(
                        'update_pruefung',
                        'domain',
                        $id,
                        'Domain neu prüfen: ' . ($domain['url'] ?? $id),
                        'Domain wurde als veraltet markiert. Sistrix-Werte aktualisieren, Erreichbarkeit prüfen, neu klassifizieren.',
                        date('Y-m-d', strtotime('+7 days'))
                    );
                }
            } catch (\Throwable $e) { /* fail-safe: Aufgabe ist Bonus, kein Showstopper */ }
        }
    }

    /**
     * Domain anlegen oder aktualisieren.
     */
    /**
     * Liest eine XLSX/CSV-Datei und extrahiert Linkquellen-Kandidaten daraus.
     * Erkennt heuristisch die URL-Spalte (Header „URL"/„Domain"/„Website" oder Spalte mit
     * URL-Pattern in der ersten Datenzeile). Plus optionale Spalten für Themen/Tags, Notiz,
     * SI, DP. Liefert ein Preview-Array — Aufrufer entscheidet was importiert wird.
     */
    public function leseLinkquellenKandidaten(string $pfad): array
    {
        require_once SERVICES_PATH . '/XlsxReader.php';
        $ext = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            $rows = XlsxReader::leseZeilen($pfad, 5000);
        } elseif ($ext === 'csv') {
            $rows = $this->csvZuArray($pfad);
        } else {
            throw new \InvalidArgumentException('Nur XLSX/CSV unterstützt');
        }
        if (empty($rows)) return ['kandidaten' => [], 'spalten' => null];

        // === Header-Zeile finden + Spalten erkennen ===
        $headerIdx = null;
        $cols = [];
        for ($i = 0; $i < min(count($rows), 5); $i++) {
            $r = $rows[$i];
            $hatUrlSpalte = false;
            $cols = [
                'url' => null, 'thema' => null, 'notiz' => null, 'si' => null, 'dp' => null,
                'preis_min' => null, 'preis_max' => null,
                // Redaktionelle Bewertung aus den Recherche-Dateien. Ohne diese Spalten ging
                // frueher die komplette Einschaetzung (Begruendung, Prio/Urteil, Betreiber,
                // Veroeffentlichungsweg, Kosten, Link-Situation, Risiko) beim Import verloren.
                'beschreibung' => null, 'bewertung' => null, 'betreiber' => null,
                'weg' => null, 'kosten' => null, 'linksituation' => null, 'risiko' => null,
            ];
            // Zwei Durchläufe: spezifische Pattern zuerst, generische als Fallback —
            // sonst gewinnt der falsche Header-Kandidat einfach weil er weiter links steht
            // (z.B. „Cluster" wäre vor „Themengebiet" geprüft).
            foreach ($r as $ci => $h) {
                $hl = mb_strtolower(trim((string) $h));
                if ($cols['url'] === null && preg_match('/\b(url|website|web|domain|projekt|link)\b/u', $hl)) {
                    $cols['url'] = $ci; $hatUrlSpalte = true;
                }
                if ($cols['thema'] === null && preg_match('/\b(themengebiet|themengebiete|kategorie|category|topic|thema)\b/u', $hl)) {
                    $cols['thema'] = $ci;
                }
                if ($cols['notiz'] === null && preg_match('/\b(anmerkung|notiz|bemerkung|kommentar|note)\b/u', $hl)) {
                    $cols['notiz'] = $ci;
                }
                if ($cols['si'] === null && preg_match('/^si\s*$|sichtbarkeit/u', $hl)) {
                    $cols['si'] = $ci;
                }
                if ($cols['dp'] === null && preg_match('/^dp\s*$|domainpopularit/u', $hl)) {
                    $cols['dp'] = $ci;
                }
                if ($cols['preis_min'] === null && preg_match('/\bpreis\s*min\b/u', $hl)) {
                    $cols['preis_min'] = $ci;
                }
                if ($cols['preis_max'] === null && preg_match('/\bpreis\s*max\b/u', $hl)) {
                    $cols['preis_max'] = $ci;
                }
                // --- Redaktionelle Bewertung ---
                if ($cols['beschreibung'] === null && preg_match('/begr(ü|ue)ndung|beschreibung|einsch(ä|ae)tzung|bewertungstext/u', $hl)) {
                    $cols['beschreibung'] = $ci;
                }
                // "Prio", "URTEIL", "Empfehlung", "Auswahl" -> die Entscheidungsspalte.
                // Achtung: "Themenpassung" ist KEIN Urteil (hoch/mittel/gering) -> nicht hier.
                if ($cols['bewertung'] === null && preg_match('/^(prio|priorit(ä|ae)t|urteil|empfehlung|entscheidung|auswahl)\b/u', $hl)) {
                    $cols['bewertung'] = $ci;
                }
                if ($cols['betreiber'] === null && preg_match('/betreiber|inhaber|herausgeber/u', $hl)) {
                    $cols['betreiber'] = $ci;
                }
                if ($cols['weg'] === null && preg_match('/ver(ö|oe)ffentlichungsweg|publikationsweg|publikation|einreichung/u', $hl)) {
                    $cols['weg'] = $ci;
                }
                if ($cols['kosten'] === null && preg_match('/^kosten|geb(ü|ue)hr/u', $hl)) {
                    $cols['kosten'] = $ci;
                }
                if ($cols['linksituation'] === null && preg_match('/link-?situation|linkart-?situation|dofollow/u', $hl)) {
                    $cols['linksituation'] = $ci;
                }
                if ($cols['risiko'] === null && preg_match('/risiko|qualit(ä|ae)t/u', $hl)) {
                    $cols['risiko'] = $ci;
                }
            }
            // Fallback: Wenn kein spezifisches Themengebiet gefunden wurde, „Cluster"-Spalte nutzen
            if ($cols['thema'] === null) {
                foreach ($r as $ci => $h) {
                    $hl = mb_strtolower(trim((string) $h));
                    if (preg_match('/\bcluster\b/u', $hl)) { $cols['thema'] = $ci; break; }
                }
            }
            if ($hatUrlSpalte) { $headerIdx = $i; break; }
        }

        // Fallback: keine Header gefunden → erste Spalte mit URL-Pattern in einer Datenzeile suchen
        if ($headerIdx === null) {
            foreach ($rows as $i => $r) {
                foreach ($r as $ci => $v) {
                    if (preg_match('#^https?://|^[a-z0-9-]+\.[a-z]{2,}#i', trim((string) $v))) {
                        $cols['url'] = $ci;
                        $headerIdx = $i - 1;
                        break 2;
                    }
                }
            }
        }
        if ($cols['url'] === null) {
            return ['kandidaten' => [], 'spalten' => null, 'fehler' => 'Keine URL-Spalte erkannt'];
        }

        // === Daten-Zeilen lesen ===
        $kandidaten = [];
        $start = ($headerIdx ?? -1) + 1;
        for ($i = $start; $i < count($rows); $i++) {
            $r = $rows[$i];
            $rawUrl = trim((string) ($r[$cols['url']] ?? ''));
            if ($rawUrl === '') continue;
            // Pattern-Check
            if (!preg_match('#[a-z0-9-]+\.[a-z]{2,}#i', $rawUrl)) continue;
            $url = $this->normalisiereDomain($rawUrl);
            if ($url === '') continue;

            $kand = ['url' => $url, 'url_raw' => $rawUrl, 'zeile' => $i + 1];
            if ($cols['thema'] !== null) {
                $t = trim((string) ($r[$cols['thema']] ?? ''));
                if ($t !== '') $kand['thema'] = $t;
            }
            if ($cols['notiz'] !== null) {
                $n = trim((string) ($r[$cols['notiz']] ?? ''));
                if ($n !== '') $kand['notiz'] = $n;
            }
            foreach (['si', 'dp', 'preis_min', 'preis_max'] as $k) {
                if ($cols[$k] !== null) {
                    $v = trim((string) ($r[$cols[$k]] ?? ''));
                    if ($v !== '' && is_numeric(str_replace(',', '.', $v))) {
                        $kand[$k] = (float) str_replace(',', '.', $v);
                    }
                }
            }
            // Redaktionelle Bewertung uebernehmen
            foreach (['beschreibung', 'betreiber', 'weg', 'kosten', 'linksituation', 'risiko'] as $k) {
                if ($cols[$k] !== null) {
                    $v = trim((string) ($r[$cols[$k]] ?? ''));
                    if ($v !== '') $kand[$k] = $v;
                }
            }
            if ($cols['bewertung'] !== null) {
                $roh = trim((string) ($r[$cols['bewertung']] ?? ''));
                if ($roh !== '') {
                    $kand['bewertung_roh'] = $roh;
                    $kand['bewertung'] = self::normalisiereBewertung($roh);
                }
            }
            $kandidaten[] = $kand;
        }

        // === Dubletten-Markierung ===
        if (!empty($kandidaten)) {
            $urls = array_column($kandidaten, 'url');
            $in = implode(',', array_fill(0, count($urls), '?'));
            $existierend = $this->db->query(
                "SELECT url FROM lam_domains WHERE LOWER(url) IN (
                    " . implode(',', array_fill(0, count($urls), 'LOWER(?)')) . "
                 ) AND geloescht_am IS NULL",
                $urls
            ) ?: [];
            $existSet = array_flip(array_map(fn($r) => strtolower($r['url']), $existierend));
            foreach ($kandidaten as &$k) {
                $k['existiert'] = isset($existSet[strtolower($k['url'])]);
            }
            unset($k);
        }
        return ['kandidaten' => $kandidaten, 'spalten' => $cols];
    }

    /**
     * Importiert eine geprüfte Kandidaten-Liste als neue Linkquellen.
     * Dubletten (existierende URLs) werden übersprungen. Liefert Statistik.
     */
    public function importiereLinkquellen(array $kandidaten, array $optionen = []): array
    {
        $stats = ['neu' => 0, 'angereichert' => 0, 'unverändert' => 0, 'fehler' => [], 'anreicherungs_details' => []];
        if (empty($kandidaten)) return $stats;

        $defaultHerkunft = $optionen['herkunft'] ?? 'Excel-Import ' . date('Y-m-d');
        $tagsCache = [];

        foreach ($kandidaten as $k) {
            $url = $k['url'] ?? '';
            if ($url === '') { $stats['fehler'][] = 'Zeile leer'; continue; }
            $exists = $this->db->queryOne(
                "SELECT id, notizen FROM lam_domains WHERE LOWER(url) = LOWER(?) AND geloescht_am IS NULL",
                [$url]
            );

            if ($exists) {
                // DUPLIKAT → ANREICHERN statt überspringen
                try {
                    $domainId = $exists['id'];
                    $details = [];

                    // Notizen anhängen (mit Trennlinie + Datum), nur wenn neue Info vorhanden
                    $neueNotiz = trim((string)($k['notiz'] ?? ''));
                    $themaSnippet = !empty($k['thema']) ? '[Cluster: ' . $k['thema'] . ']' : '';
                    $anhang = trim(($themaSnippet ? $themaSnippet : '') . ($neueNotiz ? ($themaSnippet ? ' ' : '') . $neueNotiz : ''));
                    if ($anhang !== '') {
                        $alteNotizen = (string)($exists['notizen'] ?? '');
                        $bereitsDrin = stripos($alteNotizen, $anhang) !== false;
                        if (!$bereitsDrin) {
                            $trenner = "\n\n--- " . $defaultHerkunft . " ---\n";
                            $this->db->execute(
                                "UPDATE lam_domains SET notizen = CONCAT(COALESCE(notizen, ''), ?, ?) WHERE id = ?",
                                [$trenner, $anhang, $domainId]
                            );
                            $details[] = 'Notiz';
                        }
                    }

                    // Redaktionelle Bewertung nachtragen — genau das ging bisher verloren.
                    // Beschreibung/Bewertung werden gesetzt, wenn sie in der Datei stehen; eine
                    // bereits vorhandene Beschreibung wird nur ersetzt, wenn die neue laenger ist
                    // (der ausfuehrlichere Text gewinnt).
                    if (!empty($k['beschreibung'])) {
                        $alt = (string) $this->db->queryValue("SELECT COALESCE(beschreibung, '') FROM lam_domains WHERE id = ?", [$domainId]);
                        if (mb_strlen(trim($k['beschreibung'])) > mb_strlen(trim($alt))) {
                            $this->db->execute("UPDATE lam_domains SET beschreibung = ? WHERE id = ?", [trim($k['beschreibung']), $domainId]);
                            $details[] = 'Beschreibung';
                        }
                    }
                    if (!empty($k['bewertung'])) {
                        $this->db->execute("UPDATE lam_domains SET bewertung = ? WHERE id = ?", [$k['bewertung'], $domainId]);
                        $details[] = 'Bewertung (' . $k['bewertung'] . ')';
                    }
                    $block = $this->bewertungsBlock($k);
                    if ($block !== '') {
                        $alteNotizen2 = (string) $this->db->queryValue("SELECT COALESCE(notizen, '') FROM lam_domains WHERE id = ?", [$domainId]);
                        if (stripos($alteNotizen2, $block) === false) {
                            $this->db->execute(
                                "UPDATE lam_domains SET notizen = CONCAT(COALESCE(notizen, ''), ?, ?) WHERE id = ?",
                                ["\n\n--- " . $defaultHerkunft . " ---\n", $block, $domainId]
                            );
                            $details[] = 'Details (Betreiber/Weg/Kosten)';
                        }
                    }

                    // Tag anhängen (Union)
                    if (!empty($k['thema'])) {
                        $tagName = trim($k['thema']);
                        if (!isset($tagsCache[$tagName])) {
                            $tagsCache[$tagName] = $this->holeOderErstelleTagId($tagName);
                        }
                        $vorher = (int) $this->db->queryValue(
                            "SELECT COUNT(*) FROM lam_domain_tag WHERE domain_id = ? AND tag_id = ?",
                            [$domainId, $tagsCache[$tagName]]
                        );
                        $this->db->execute(
                            "INSERT IGNORE INTO lam_domain_tag (domain_id, tag_id, primaer) VALUES (?, ?, 0)",
                            [$domainId, $tagsCache[$tagName]]
                        );
                        if ($vorher === 0) $details[] = 'Tag „' . $tagName . '"';
                    }

                    // Neuer SI/DP-Snapshot (immer wenn Werte im Import sind — zeigt Verlauf über die Zeit)
                    if (isset($k['si']) || isset($k['dp'])) {
                        $this->db->execute(
                            "INSERT INTO lam_kennzahl_snapshots (domain_id, si, dp, erfasst_am)
                             VALUES (?, ?, ?, NOW())",
                            [$domainId, $k['si'] ?? null, $k['dp'] ?? null]
                        );
                        $details[] = 'Kennzahl-Snapshot';
                    }

                    if (!empty($details)) {
                        $stats['angereichert']++;
                        $stats['anreicherungs_details'][] = [
                            'url' => $url,
                            'felder' => $details,
                        ];
                    } else {
                        $stats['unverändert']++;
                    }
                } catch (\Throwable $e) {
                    $stats['fehler'][] = $url . ' (anreichern): ' . $e->getMessage();
                }
                continue;
            }

            // Tag-ID VOR der Transaktion aufloesen: lam_tags ist eine gemeinsame Nachschlage-
            // tabelle. Laege das Anlegen in der Transaktion, wuerde ein Rollback den Tag wieder
            // entfernen — der tagsCache zeigte dann auf eine ID, die es nicht mehr gibt.
            $tagId = null;
            if (!empty($k['thema'])) {
                $tagName = trim($k['thema']);
                try {
                    if (!isset($tagsCache[$tagName])) {
                        $tagsCache[$tagName] = $this->holeOderErstelleTagId($tagName);
                    }
                    $tagId = $tagsCache[$tagName];
                } catch (\Throwable $e) {
                    $stats['fehler'][] = $url . ': Tag „' . $tagName . '" — ' . $e->getMessage();
                    continue;
                }
            }

            // Domain + Tag + Snapshot atomar: schlaegt ein Schritt fehl, bleibt KEINE halbe
            // Zeile zurueck (frueher wurde die Domain angelegt, der Tag scheiterte, und die
            // Statistik meldete trotzdem "0 neu" — die Domain lag aber schon in der DB).
            $this->db->beginTransaction();
            try {
                $domainId = $this->generiereUlid();
                $notiz = $k['notiz'] ?? '';
                if (!empty($k['thema']) && $notiz !== '' && stripos($notiz, $k['thema']) === false) {
                    $notiz = '[Cluster: ' . $k['thema'] . '] ' . $notiz;
                } elseif (!empty($k['thema']) && $notiz === '') {
                    $notiz = '[Cluster: ' . $k['thema'] . ']';
                }
                $block = $this->bewertungsBlock($k);
                $notiz = trim(($notiz ? $notiz . "\n\n" : '') . '--- ' . $defaultHerkunft . ' ---'
                    . ($block !== '' ? "\n" . $block : ''));
                $this->db->execute(
                    "INSERT INTO lam_domains (id, url, verifikation_status, buchbar_via, notizen, beschreibung, bewertung, erstellt_am)
                     VALUES (?, ?, 'neu', 'unbekannt', ?, ?, ?, NOW())",
                    [
                        $domainId, $url, $notiz,
                        $k['beschreibung'] ?? null,
                        $k['bewertung'] ?? null,
                    ]
                );
                if ($tagId !== null) {
                    $this->db->execute(
                        "INSERT IGNORE INTO lam_domain_tag (domain_id, tag_id, primaer) VALUES (?, ?, 1)",
                        [$domainId, $tagId]
                    );
                }
                if (isset($k['si']) || isset($k['dp'])) {
                    $this->db->execute(
                        "INSERT INTO lam_kennzahl_snapshots (domain_id, si, dp, erfasst_am)
                         VALUES (?, ?, ?, NOW())",
                        [$domainId, $k['si'] ?? null, $k['dp'] ?? null]
                    );
                }
                $this->db->commit();
                $stats['neu']++;
            } catch (\Throwable $e) {
                $this->db->rollback();
                $stats['fehler'][] = $url . ': ' . $e->getMessage();
            }
        }
        return $stats;
    }

    private function csvZuArray(string $pfad): array
    {
        $rows = [];
        if (($h = fopen($pfad, 'r')) !== false) {
            while (($r = fgetcsv($h, 0, ';')) !== false) $rows[] = $r;
            fclose($h);
            if (count($rows) <= 1 && ($h2 = fopen($pfad, 'r')) !== false) {
                $rows = [];
                while (($r = fgetcsv($h2, 0, ',')) !== false) $rows[] = $r;
                fclose($h2);
            }
        }
        return $rows;
    }

    private function normalisiereDomain(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('#^https?://#i', '', $url);
        $url = preg_replace('#/+$#', '', $url);
        if (preg_match('#^([^/]+)(/.*)?$#', $url, $m)) {
            $url = strtolower($m[1]) . ($m[2] ?? '');
        }
        return $url;
    }

    /**
     * Übernimmt Verlinkungen aus dem Linkprofil als Linkquellen in den Pool.
     * Pro Verlinkung:
     *  - Host aus verlinkende_url extrahieren
     *  - Falls Host noch nicht in lam_domains → neu anlegen (Status: neu)
     *  - In lam_domain_customer für Kunden eintragen (Status springt auto auf in_arbeit
     *    wenn vorher neu/veraltet — über toggleKundeFuerDomain)
     *  - Originale verlinkende_url als Beispiellink in lam_domain_links speichern
     *  - Notiz an Linkquelle anhängen
     *
     * Return: { erfolge, schon_im_pool, fehler, neue_linkquellen, fehler_liste }
     */
    public function uebernehmeVerlinkungenInLinkquellen(array $verlinkungIds, int $customerId): array
    {
        $stats = [
            'erfolge' => 0,
            'schon_im_pool' => 0,
            'fehler' => 0,
            'neue_linkquellen' => 0,
            'fehler_liste' => [],
        ];
        if (empty($verlinkungIds) || $customerId <= 0) return $stats;

        $kundeName = (string) $this->db->queryValue(
            "SELECT COALESCE(NULLIF(abbreviation, ''), name) FROM customers WHERE id = ?",
            [$customerId]
        );

        $platzhalter = implode(',', array_fill(0, count($verlinkungIds), '?'));
        $verlinkungen = $this->db->query(
            "SELECT id, verlinkende_url, ziel_url, linktext, linkart, empfehlung
             FROM lam_verlinkungen
             WHERE id IN ($platzhalter) AND geloescht_am IS NULL",
            $verlinkungIds
        );

        foreach ($verlinkungen as $v) {
            try {
                $vUrl = (string) $v['verlinkende_url'];
                $host = parse_url(strpos($vUrl, '://') === false ? 'https://' . $vUrl : $vUrl, PHP_URL_HOST);
                if (!$host) {
                    $stats['fehler']++;
                    $stats['fehler_liste'][] = $vUrl . ': Host nicht extrahierbar';
                    continue;
                }
                $host = preg_replace('#^www\.#i', '', $host);

                // Linkquelle suchen oder anlegen
                $domain = $this->db->queryOne(
                    "SELECT id FROM lam_domains WHERE LOWER(url) = LOWER(?) AND geloescht_am IS NULL",
                    [$host]
                );
                $domainId = $domain['id'] ?? null;
                if (!$domainId) {
                    $domainId = $this->generiereUlid();
                    $notiz = '--- Aus Linkprofil von ' . $kundeName . ' übertragen am ' . date('d.m.Y') . ' ---';
                    $this->db->execute(
                        "INSERT INTO lam_domains (id, url, verifikation_status, buchbar_via, notizen, linkart, erstellt_am)
                         VALUES (?, ?, 'neu', 'unbekannt', ?, ?, NOW())",
                        [$domainId, $host, $notiz, $v['linkart'] ?: null]
                    );
                    $stats['neue_linkquellen']++;
                }

                // Pool-Verknüpfung (idempotent)
                $existiert = $this->db->queryValue(
                    "SELECT 1 FROM lam_domain_customer WHERE domain_id = ? AND customer_id = ? LIMIT 1",
                    [$domainId, $customerId]
                );
                if ($existiert) {
                    $stats['schon_im_pool']++;
                } else {
                    $this->db->execute(
                        "INSERT INTO lam_domain_customer (domain_id, customer_id, erstellt_am) VALUES (?, ?, NOW())",
                        [$domainId, $customerId]
                    );
                    // Status auto auf in_arbeit wenn neu/veraltet
                    $aktStatus = $this->db->queryValue(
                        "SELECT verifikation_status FROM lam_domains WHERE id = ?", [$domainId]
                    );
                    if (in_array($aktStatus, ['neu', 'veraltet'], true)) {
                        $this->db->execute(
                            "UPDATE lam_domains SET verifikation_status = 'in_arbeit' WHERE id = ?",
                            [$domainId]
                        );
                    }
                }

                // Original-Verlinkungs-URL als Beispiellink an die Linkquelle hängen
                // (Deeplink-Erhalt — der Backlink-Ort bleibt nachvollziehbar)
                $existL = $this->db->queryValue(
                    "SELECT 1 FROM lam_domain_links
                     WHERE domain_id = ? AND url = ? AND geloescht_am IS NULL LIMIT 1",
                    [$domainId, $vUrl]
                );
                if (!$existL && $vUrl !== '' && $vUrl !== $host) {
                    $label = trim((string) ($v['linktext'] ?? '')) ?: 'Aus Linkprofil ' . $kundeName;
                    $this->db->execute(
                        "INSERT INTO lam_domain_links (id, domain_id, typ, label, url, erstellt_am)
                         VALUES (?, ?, 'beispiellink', ?, ?, NOW())",
                        [$this->generiereUlid(), $domainId, $label, $vUrl]
                    );
                }

                $stats['erfolge']++;
            } catch (\Throwable $e) {
                $stats['fehler']++;
                $stats['fehler_liste'][] = ($v['verlinkende_url'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return $stats;
    }

    public function speichereDomain(?string $id, array $data): string
    {
        $url = trim((string)($data['url'] ?? ''));
        if ($url === '') throw new \InvalidArgumentException('URL ist erforderlich');
        // Normalisierung: Schema, Trailing-Slash, lowercase Host (Pfad case-sensitiv lassen)
        $url = preg_replace('#^https?://#i', '', $url);
        $url = preg_replace('#/+$#', '', $url);
        if (preg_match('#^([^/]+)(/.*)?$#', $url, $m)) {
            $url = strtolower($m[1]) . ($m[2] ?? '');
        }

        // Dubletten-Check (nur beim INSERT — beim UPDATE ändert sich die URL selten,
        // und wenn doch, fängt sie der gleiche Check). Vergleicht auf Domain-Ebene:
        // www. wird ignoriert, sodass www.example.de und example.de als gleich gelten.
        $hostOhneWww = preg_replace('#^www\.#i', '', $url);
        $vorhanden = $this->db->queryOne(
            "SELECT id, url FROM lam_domains
             WHERE geloescht_am IS NULL
               AND LOWER(REGEXP_REPLACE(url, '^www\\\\.', '')) = LOWER(?)
               AND (? IS NULL OR id != ?)
             LIMIT 1",
            [$hostOhneWww, $id, $id]
        );
        if ($vorhanden) {
            throw new \InvalidArgumentException(
                'Diese Linkquelle existiert bereits: „' . $vorhanden['url'] . '" (ID ' . $vorhanden['id'] . '). '
                . 'Wenn das Portal verschiedene Subseiten betreibt, kannst Du diese später als zusätzliche URLs an der bestehenden Linkquelle pflegen.'
            );
        }

        $anbieterId = isset($data['anbieter_id']) && trim((string)$data['anbieter_id']) !== '' ? trim((string)$data['anbieter_id']) : null;
        $verifikation = $data['verifikation_status'] ?? 'neu';
        $linkart = isset($data['linkart']) && trim((string)$data['linkart']) !== '' ? trim((string)$data['linkart']) : null;
        // buchbar_via ist NOT NULL mit DB-Default 'unbekannt' — bei leerem Frontend-Wert
        // muss der Default explizit gesetzt werden (sonst SQL-Constraint-Violation beim INSERT).
        $buchbarVia = isset($data['buchbar_via']) && trim((string)$data['buchbar_via']) !== '' ? trim((string)$data['buchbar_via']) : 'unbekannt';
        $notizen = isset($data['notizen']) && trim((string)$data['notizen']) !== '' ? trim((string)$data['notizen']) : null;
        $disqualifiziert = !empty($data['disqualifiziert']) ? 1 : 0;

        if ($id) {
            $this->db->execute(
                "UPDATE lam_domains
                 SET url = ?, anbieter_id = ?, verifikation_status = ?, linkart = ?,
                     buchbar_via = ?, notizen = ?, disqualifiziert = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$url, $anbieterId, $verifikation, $linkart, $buchbarVia, $notizen, $disqualifiziert, $id]
            );
            return $id;
        }

        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_domains (id, url, anbieter_id, verifikation_status, linkart,
                                      buchbar_via, notizen, disqualifiziert, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$neueId, $url, $anbieterId, $verifikation, $linkart, $buchbarVia, $notizen, $disqualifiziert]
        );
        return $neueId;
    }

    public function loescheDomain(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_domains SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    /**
     * Toggle: Kunde aktivieren/deaktivieren fuer eine Domain.
     * Wenn aktivieren und Domain ist 'neu'/'veraltet' → auf 'in_arbeit' setzen.
     */
    public function toggleKundeFuerDomain(string $domainId, int $kundeId): array
    {
        // lam_domain_customer hat Composite-PK (domain_id + customer_id), keine id-Spalte
        $existiert = $this->db->queryValue(
            "SELECT 1 FROM lam_domain_customer WHERE domain_id = ? AND customer_id = ? LIMIT 1",
            [$domainId, $kundeId]
        );
        if ($existiert) {
            $this->db->execute(
                "DELETE FROM lam_domain_customer WHERE domain_id = ? AND customer_id = ?",
                [$domainId, $kundeId]
            );
            return ['aktion' => 'entfernt'];
        }
        $this->db->execute(
            "INSERT INTO lam_domain_customer (domain_id, customer_id, erstellt_am) VALUES (?, ?, NOW())",
            [$domainId, $kundeId]
        );
        // Wenn Status 'neu'/'veraltet' → auf 'in_arbeit'
        $aktStatus = $this->db->queryValue(
            "SELECT verifikation_status FROM lam_domains WHERE id = ?",
            [$domainId]
        );
        if (in_array($aktStatus, ['neu', 'veraltet'], true)) {
            $this->db->execute(
                "UPDATE lam_domains SET verifikation_status = 'in_arbeit' WHERE id = ?",
                [$domainId]
            );
        }
        return ['aktion' => 'hinzugefuegt'];
    }

    /**
     * Konditionen-CRUD pro Domain.
     */
    public function speichereKondition(?string $id, string $domainId, array $data): string
    {
        $buchungstyp = trim((string)($data['buchungstyp'] ?? '')) ?: null;
        $preis = $data['preis'] !== null && $data['preis'] !== '' ? (float)$data['preis'] : null;
        $viaAnbieterId = !empty($data['via_anbieter_id']) ? trim((string)$data['via_anbieter_id']) : null;
        $notiz = isset($data['notiz']) && trim((string)$data['notiz']) !== '' ? trim((string)$data['notiz']) : null;

        if ($id) {
            $this->db->execute(
                "UPDATE lam_konditionen
                 SET buchungstyp = ?, preis = ?, via_anbieter_id = ?, notiz = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$buchungstyp, $preis, $viaAnbieterId, $notiz, $id]
            );
            return $id;
        }
        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_konditionen (id, domain_id, buchungstyp, preis, via_anbieter_id, notiz, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$neueId, $domainId, $buchungstyp, $preis, $viaAnbieterId, $notiz]
        );
        return $neueId;
    }

    public function loescheKondition(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_konditionen SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    /**
     * Externe Links pro Domain (Impressum, Mediadaten, etc.).
     */
    public function speichereDomainLink(?string $id, string $domainId, array $data): string
    {
        $typ = trim((string)($data['typ'] ?? 'sonstiges'));
        $label = trim((string)($data['label'] ?? '')) ?: null;
        $url = trim((string)($data['url'] ?? ''));
        if ($url === '') throw new \InvalidArgumentException('URL erforderlich');

        if ($id) {
            $this->db->execute(
                "UPDATE lam_domain_links SET typ = ?, label = ?, url = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$typ, $label, $url, $id]
            );
            return $id;
        }
        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_domain_links (id, domain_id, typ, label, url, position, erstellt_am)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$neueId, $domainId, $typ, $label, $url]
        );
        return $neueId;
    }

    public function loescheDomainLink(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_domain_links SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    public function bulkAktualisiereDomains(array $ids, string $aktion, $wert): array
    {
        $erfolge = 0;
        $fehler = [];
        foreach ($ids as $id) {
            try {
                if ($aktion === 'verifikation_setzen') {
                    $this->aktualisiereDomainFeld($id, 'verifikation_status', $wert);
                } elseif ($aktion === 'disqualifizieren') {
                    $this->aktualisiereDomainFeld($id, 'disqualifiziert', 1);
                } elseif ($aktion === 'rehabilitieren') {
                    $this->aktualisiereDomainFeld($id, 'disqualifiziert', 0);
                } elseif ($aktion === 'anbieter_setzen') {
                    $this->aktualisiereDomainFeld($id, 'anbieter_id', $wert ?: null);
                } elseif ($aktion === 'bewertung_setzen') {
                    $this->aktualisiereDomainFeld($id, 'bewertung', $wert);
                } elseif ($aktion === 'tag_setzen') {
                    // Tag ergaenzen (kein Toggle!) — bei gemischter Auswahl wuerde ein Toggle
                    // sonst bei manchen Domains genau das Gegenteil bewirken.
                    $tagId = (int) $wert;
                    if ($tagId <= 0) throw new \InvalidArgumentException('Kein Tag gewaehlt');
                    $this->db->execute(
                        "INSERT IGNORE INTO lam_domain_tag (domain_id, tag_id, primaer) VALUES (?, ?, 0)",
                        [$id, $tagId]
                    );
                } elseif ($aktion === 'tag_entfernen') {
                    $tagId = (int) $wert;
                    if ($tagId <= 0) throw new \InvalidArgumentException('Kein Tag gewaehlt');
                    $this->db->execute(
                        "DELETE FROM lam_domain_tag WHERE domain_id = ? AND tag_id = ?",
                        [$id, $tagId]
                    );
                } elseif ($aktion === 'loeschen') {
                    $this->loescheDomain($id);
                } else {
                    throw new \InvalidArgumentException('Unbekannte Aktion');
                }
                $erfolge++;
            } catch (\Exception $e) {
                $fehler[] = $id . ': ' . $e->getMessage();
            }
        }
        $this->auditBulk('domain.bulk_' . $aktion, 'domain', $erfolge, ['wert' => $wert, 'fehler' => count($fehler)]);
        return ['erfolge' => $erfolge, 'fehler' => $fehler];
    }

    /**
     * Alle Tags (für Tag-Toggle-Chips).
     */
    public function listeTagsKurz(): array
    {
        return $this->db->query(
            "SELECT id, slug, name
             FROM lam_tags
             WHERE geloescht_am IS NULL
             ORDER BY name ASC"
        );
    }

    /**
     * Toggle: Tag aktivieren/deaktivieren für eine Domain.
     */
    public function toggleTagFuerDomain(string $domainId, int $tagId): array
    {
        $existiert = $this->db->queryValue(
            "SELECT 1 FROM lam_domain_tag WHERE domain_id = ? AND tag_id = ?",
            [$domainId, $tagId]
        );
        if ($existiert) {
            $this->db->execute(
                "DELETE FROM lam_domain_tag WHERE domain_id = ? AND tag_id = ?",
                [$domainId, $tagId]
            );
            return ['aktion' => 'entfernt'];
        }
        $this->db->execute(
            "INSERT INTO lam_domain_tag (domain_id, tag_id, primaer) VALUES (?, ?, 0)",
            [$domainId, $tagId]
        );
        return ['aktion' => 'hinzugefuegt'];
    }

    /**
     * Alle LAM-aktiven Kunden (= solche mit Eintrag in lam_kunden_config).
     * Fallback: wenn lam_kunden_config leer, alle aktiven Customers.
     */
    public function listeKundenKurz(): array
    {
        $rows = $this->db->query(
            "SELECT c.id, c.name, c.abbreviation
             FROM customers c
             JOIN lam_kunden_config k ON k.customer_id = c.id
             WHERE c.is_active = 1
             ORDER BY c.abbreviation ASC"
        );
        if (!empty($rows)) return $rows;
        // Fallback: keine LAM-Config gepflegt → alle aktiven
        return $this->db->query(
            "SELECT id, name, abbreviation
             FROM customers
             WHERE is_active = 1
             ORDER BY abbreviation ASC"
        );
    }

    public function listeAnbieterKurz(?string $suche = null): array
    {
        if ($suche !== null && trim($suche) !== '') {
            $like = '%' . trim($suche) . '%';
            return $this->db->query(
                "SELECT a.id, a.name, a.firma, a.ist_betreiber, a.ist_vermittler
                 FROM lam_anbieter a
                 WHERE a.geloescht_am IS NULL
                   AND (a.name LIKE ? OR a.firma LIKE ?
                        OR EXISTS (SELECT 1 FROM lam_kontakte k
                                   WHERE k.anbieter_id = a.id AND k.geloescht_am IS NULL
                                     AND (k.email LIKE ? OR k.nachname LIKE ? OR k.vorname LIKE ?)))
                 ORDER BY (a.ist_betreiber = 1) DESC, a.name ASC
                 LIMIT 50",
                [$like, $like, $like, $like, $like]
            );
        }
        return $this->db->query(
            "SELECT id, name, firma, ist_betreiber, ist_vermittler
             FROM lam_anbieter
             WHERE geloescht_am IS NULL
             ORDER BY name ASC"
        );
    }

    // ---------------------------------------------------------------------
    // Anbieter (Liste mit Filter)
    // ---------------------------------------------------------------------

    public function listeAnbieter(array $filter = []): array
    {
        $where  = ['a.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(a.name LIKE ? OR a.firma LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['rolle'])) {
            switch ($filter['rolle']) {
                case 'betreiber':
                    $where[] = 'a.ist_betreiber = 1 AND a.ist_vermittler = 0';
                    break;
                case 'vermittler':
                    $where[] = 'a.ist_vermittler = 1 AND a.ist_betreiber = 0';
                    break;
                case 'beides':
                    $where[] = 'a.ist_betreiber = 1 AND a.ist_vermittler = 1';
                    break;
            }
        }
        if (!empty($filter['beziehung']) && in_array($filter['beziehung'], ['neu', 'etabliert', 'vertrauensvoll', 'abgekuehlt'], true)) {
            $where[] = 'a.beziehungsstatus = ?';
            $params[] = $filter['beziehung'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $orderMap = [
            'name_asc'        => 'LOWER(a.name) ASC',
            'name_desc'       => 'LOWER(a.name) DESC',
            'firma_asc'       => "LOWER(COALESCE(a.firma,'')) ASC, LOWER(a.name) ASC",
            'firma_desc'      => "LOWER(COALESCE(a.firma,'')) DESC, LOWER(a.name) ASC",
            'beziehung_asc'   => "FIELD(a.beziehungsstatus,'neu','etabliert','vertrauensvoll','abgekuehlt') ASC, LOWER(a.name) ASC",
            'beziehung_desc'  => "FIELD(a.beziehungsstatus,'neu','etabliert','vertrauensvoll','abgekuehlt') DESC, LOWER(a.name) ASC",
            'domains_asc'     => 'domains_count ASC, LOWER(a.name) ASC',
            'domains_desc'    => 'domains_count DESC, LOWER(a.name) ASC',
            'kontakte_asc'    => 'kontakte_count ASC, LOWER(a.name) ASC',
            'kontakte_desc'   => 'kontakte_count DESC, LOWER(a.name) ASC',
        ];
        $orderBy = $orderMap[$filter['sort'] ?? 'name_asc'] ?? 'LOWER(a.name) ASC';

        $limit  = (int) ($filter['limit']  ?? 50);
        $offset = (int) ($filter['offset'] ?? 0);
        if ($limit < 1 || $limit > 500) $limit = 50;
        if ($offset < 0) $offset = 0;

        $total = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_anbieter a {$whereSql}",
            $params
        );

        $rows = $this->db->query(
            "SELECT a.id, a.name, a.firma, a.beziehungsstatus,
                    a.ist_betreiber, a.ist_vermittler, a.notizen,
                    (SELECT COUNT(*) FROM lam_domains d
                        WHERE d.anbieter_id = a.id AND d.geloescht_am IS NULL) AS domains_count,
                    (SELECT COUNT(*) FROM lam_kontakte k
                        WHERE k.anbieter_id = a.id AND k.geloescht_am IS NULL) AS kontakte_count
             FROM lam_anbieter a
             {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        foreach ($rows as &$r) {
            if ($r['ist_betreiber'] && $r['ist_vermittler']) {
                $r['rollen_label'] = 'Betreiber + Vermittler';
            } elseif ($r['ist_vermittler']) {
                $r['rollen_label'] = 'Vermittler';
            } else {
                $r['rollen_label'] = 'Betreiber';
            }
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Inline-Update eines einzelnen Anbieter-Feldes.
     * Erlaubt: name, firma, rolle (= ist_betreiber/ist_vermittler), beziehungsstatus, notizen.
     * Wirft Exception bei unzulaessigen Feldern oder Werten.
     */
    public function aktualisiereAnbieterFeld(string $id, string $feld, $wert): void
    {
        $erlaubteFelder = ['name', 'firma', 'beziehungsstatus', 'notizen'];
        $erlaubteBeziehung = ['neu', 'etabliert', 'vertrauensvoll', 'abgekuehlt'];
        $erlaubteRollen = ['betreiber', 'vermittler', 'beides'];

        // Existenz pruefen
        $vorhanden = $this->db->queryValue(
            "SELECT id FROM lam_anbieter WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
        if (!$vorhanden) {
            throw new \InvalidArgumentException('Anbieter nicht gefunden');
        }

        if ($feld === 'rolle') {
            if (!in_array($wert, $erlaubteRollen, true)) {
                throw new \InvalidArgumentException('Ungueltige Rolle');
            }
            $istBetreiber = ($wert === 'betreiber' || $wert === 'beides') ? 1 : 0;
            $istVermittler = ($wert === 'vermittler' || $wert === 'beides') ? 1 : 0;
            $this->db->execute(
                "UPDATE lam_anbieter SET ist_betreiber = ?, ist_vermittler = ? WHERE id = ?",
                [$istBetreiber, $istVermittler, $id]
            );
            return;
        }

        if ($feld === 'beziehungsstatus' && !in_array($wert, $erlaubteBeziehung, true)) {
            throw new \InvalidArgumentException('Ungueltiger Beziehungsstatus');
        }
        if (!in_array($feld, $erlaubteFelder, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt: ' . $feld);
        }
        if ($feld === 'name' && trim((string) $wert) === '') {
            throw new \InvalidArgumentException('Name darf nicht leer sein');
        }

        $sicheresWert = ($wert === '' || $wert === null) ? null : $wert;
        if (is_string($sicheresWert)) $sicheresWert = trim($sicheresWert);

        $this->db->execute(
            "UPDATE lam_anbieter SET `{$feld}` = ? WHERE id = ?",
            [$sicheresWert, $id]
        );
    }

    /**
     * Anbieter anlegen oder aktualisieren (alle Felder auf einmal).
     * Wenn $id leer → neuer Anbieter mit ULID.
     */
    public function speichereAnbieter(?string $id, array $data): string
    {
        $erlaubteBeziehung = ['neu', 'etabliert', 'vertrauensvoll', 'abgekuehlt'];
        $erlaubteRollen = ['betreiber', 'vermittler', 'beides'];

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Name ist erforderlich');

        $firma = isset($data['firma']) && trim((string)$data['firma']) !== '' ? trim((string)$data['firma']) : null;
        $notizen = isset($data['notizen']) && trim((string)$data['notizen']) !== '' ? trim((string)$data['notizen']) : null;

        $rolle = $data['rolle'] ?? 'betreiber';
        if (!in_array($rolle, $erlaubteRollen, true)) $rolle = 'betreiber';
        $istBetreiber = ($rolle === 'betreiber' || $rolle === 'beides') ? 1 : 0;
        $istVermittler = ($rolle === 'vermittler' || $rolle === 'beides') ? 1 : 0;

        $beziehung = $data['beziehungsstatus'] ?? 'neu';
        if (!in_array($beziehung, $erlaubteBeziehung, true)) $beziehung = 'neu';

        if ($id) {
            $this->db->execute(
                "UPDATE lam_anbieter
                 SET name = ?, firma = ?, ist_betreiber = ?, ist_vermittler = ?,
                     beziehungsstatus = ?, notizen = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$name, $firma, $istBetreiber, $istVermittler, $beziehung, $notizen, $id]
            );
            return $id;
        }

        // Dublettenpruefung beim Anlegen — gleicher Name (case-insensitive)
        // oder gleiche Firma. Wirft eine erkennbare Exception zurück, die der
        // API-Layer als spezifischen Hinweis behandeln kann.
        $dublette = $this->db->queryOne(
            "SELECT id, name, firma FROM lam_anbieter
             WHERE geloescht_am IS NULL
               AND (LOWER(name) = LOWER(?) OR (firma IS NOT NULL AND firma <> '' AND LOWER(firma) = LOWER(?)))
             LIMIT 1",
            [$name, $firma ?: '']
        );
        if ($dublette && empty($data['dublette_ignorieren'])) {
            throw new \RuntimeException(
                'Anbieter existiert vermutlich schon: ' . $dublette['name']
                . ($dublette['firma'] ? ' (' . $dublette['firma'] . ')' : '')
                . ' — ID ' . $dublette['id'] . '. Beim erneuten Speichern mit dublette_ignorieren=1 erzwingen.'
            );
        }

        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_anbieter (id, name, firma, ist_betreiber, ist_vermittler,
                                       beziehungsstatus, notizen, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$neueId, $name, $firma, $istBetreiber, $istVermittler, $beziehung, $notizen]
        );
        return $neueId;
    }

    /**
     * Einzelnen Anbieter loeschen (Soft-Delete).
     */
    public function loescheAnbieter(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_anbieter SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    /**
     * Generiert eine ULID-aehnliche ID (26 Zeichen). Fuer den Pool reicht uns
     * eine Crockford-Base32-Variante mit Zeitprefix.
     */
    private function generiereUlid(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time = (int) (microtime(true) * 1000);
        $timeChars = '';
        for ($i = 0; $i < 10; $i++) {
            $timeChars = $alphabet[$time % 32] . $timeChars;
            $time = (int) ($time / 32);
        }
        $randChars = '';
        for ($i = 0; $i < 16; $i++) {
            $randChars .= $alphabet[random_int(0, 31)];
        }
        return $timeChars . $randChars;
    }

    // ---------------------------------------------------------------------
    // Kontakte (zu Anbietern)
    // ---------------------------------------------------------------------

    public function speichereKontakt(?string $id, string $anbieterId, array $data): string
    {
        $vorname  = trim((string)($data['vorname']  ?? ''));
        $nachname = trim((string)($data['nachname'] ?? ''));
        if ($nachname === '') {
            throw new \InvalidArgumentException('Nachname ist erforderlich');
        }
        $email   = trim((string)($data['email']   ?? '')) ?: null;
        $telefon = trim((string)($data['telefon'] ?? '')) ?: null;
        $rolle   = trim((string)($data['rolle']   ?? '')) ?: null;

        if ($id) {
            $this->db->execute(
                "UPDATE lam_kontakte
                 SET vorname = ?, nachname = ?, email = ?, telefon = ?, rolle = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$vorname ?: null, $nachname, $email, $telefon, $rolle, $id]
            );
            return $id;
        }

        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_kontakte (id, anbieter_id, vorname, nachname, email, telefon, rolle,
                                       verifikation_status, prioritaet, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'neu', 2, NOW())",
            [$neueId, $anbieterId, $vorname ?: null, $nachname, $email, $telefon, $rolle]
        );
        // Tom-Regel: Anbieter = Mensch, Firma nur Subzeile.
        //   Wenn der Anbieter-Name aktuell firmenartig ist (UG/GmbH/AG/…) oder identisch zur firma,
        //   ziehen wir den Namen auf diesen neuen Kontakt nach. Firma wandert ggf. ins firma-Feld.
        $this->ziehePersonAnbieterNamenNach($anbieterId);
        return $neueId;
    }

    /**
     * Wenn der Anbieter aktuell einen firmenartigen Namen trägt aber mind. einen Kontakt mit Nachname hat,
     * wird der Anbieter-Name auf den primären Kontakt umbenannt und der bisherige Name in `firma` gesichert
     * (sofern dort noch nichts steht).
     */
    private function ziehePersonAnbieterNamenNach(string $anbieterId): void
    {
        $a = $this->db->queryOne(
            "SELECT name, firma FROM lam_anbieter WHERE id = ? AND geloescht_am IS NULL",
            [$anbieterId]
        );
        if (!$a) return;
        $name = trim((string)$a['name']);
        $firma = trim((string)($a['firma'] ?? ''));
        if ($name === '') return;

        $firmenartig = (
            ($firma !== '' && strcasecmp($name, $firma) === 0)
            || preg_match('/\b(UG|GmbH|AG|KG|GbR|OHG|mbH|Ltd|Inc|S\.A\.|S\.L\.|Holding|Group|Verlag|Magazin)\b/u', $name)
            || preg_match('/\(haftungsbeschränkt\)/iu', $name)
        );
        if (!$firmenartig) return;

        $kontakt = $this->db->queryOne(
            "SELECT vorname, nachname FROM lam_kontakte
             WHERE anbieter_id = ? AND geloescht_am IS NULL AND nachname IS NOT NULL AND nachname <> ''
             ORDER BY prioritaet ASC, erstellt_am ASC LIMIT 1",
            [$anbieterId]
        );
        if (!$kontakt) return;
        $personName = trim((string)($kontakt['vorname'] ?? '') . ' ' . (string)$kontakt['nachname']);
        if ($personName === '') return;

        $neueFirma = $firma !== '' ? $firma : $name; // alten Anbieter-Namen als Firma sichern
        $this->db->execute(
            "UPDATE lam_anbieter SET name = ?, firma = ? WHERE id = ?",
            [$personName, $neueFirma, $anbieterId]
        );
        $this->audit('anbieter.umbenannt_auf_person', 'anbieter', $anbieterId, [
            'alter_name' => $name, 'neuer_name' => $personName, 'firma' => $neueFirma,
        ]);
    }

    public function loescheKontakt(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_kontakte SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    /**
     * Setzt einen Kontakt als Primär (Priorität 1), alle anderen beim gleichen Anbieter auf 2.
     */
    public function setzePrimaerKontakt(string $id): void
    {
        $kontakt = $this->db->queryOne(
            "SELECT anbieter_id, vorname, nachname FROM lam_kontakte WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
        if (!$kontakt) return;
        $anbieterId = $kontakt['anbieter_id'];

        $this->db->execute(
            "UPDATE lam_kontakte SET prioritaet = 2 WHERE anbieter_id = ? AND geloescht_am IS NULL",
            [$anbieterId]
        );
        $this->db->execute(
            "UPDATE lam_kontakte SET prioritaet = 1 WHERE id = ?",
            [$id]
        );

        // Anbieter-Namen propagieren: Wenn der bestehende Anbieter-Name die Firma ist
        // (kein/anderer Mensch dahinter), auf die neue Hauptkontakt-Person umstellen.
        // Firma bleibt als Firmierung erhalten.
        $personName = trim(($kontakt['vorname'] ?? '') . ' ' . ($kontakt['nachname'] ?? ''));
        if ($personName === '') return;
        $aktuell = $this->db->queryOne(
            "SELECT name, firma FROM lam_anbieter WHERE id = ? AND geloescht_am IS NULL",
            [$anbieterId]
        );
        if (!$aktuell || $aktuell['name'] === $personName) return;
        $aktuellerName = (string)($aktuell['name'] ?? '');
        $aktuelleFirma = (string)($aktuell['firma'] ?? '');
        $nameIstFirma = $aktuellerName === '' || strcasecmp($aktuellerName, $aktuelleFirma) === 0;
        if ($nameIstFirma) {
            $this->db->execute(
                "UPDATE lam_anbieter SET name = ? WHERE id = ?",
                [$personName, $anbieterId]
            );
        }
    }

    /**
     * Bulk-Aktion ueber mehrere Anbieter-IDs.
     * Aktion: 'beziehung_setzen' | 'rolle_setzen' | 'loeschen'.
     * Return: ['erfolge' => N, 'fehler' => [meldung,...]]
     */
    public function bulkAktualisiereAnbieter(array $ids, string $aktion, $wert): array
    {
        $erfolge = 0;
        $fehler = [];
        foreach ($ids as $id) {
            try {
                if ($aktion === 'beziehung_setzen') {
                    $this->aktualisiereAnbieterFeld($id, 'beziehungsstatus', $wert);
                } elseif ($aktion === 'rolle_setzen') {
                    $this->aktualisiereAnbieterFeld($id, 'rolle', $wert);
                } elseif ($aktion === 'loeschen') {
                    $this->db->execute(
                        "UPDATE lam_anbieter SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
                        [$id]
                    );
                } else {
                    throw new \InvalidArgumentException('Unbekannte Aktion');
                }
                $erfolge++;
            } catch (\Exception $e) {
                $fehler[] = $id . ': ' . $e->getMessage();
            }
        }
        $this->auditBulk('anbieter.bulk_' . $aktion, 'anbieter', $erfolge, ['wert' => $wert, 'fehler' => count($fehler)]);
        return ['erfolge' => $erfolge, 'fehler' => $fehler];
    }

    public function getAnbieterDetail(string $id): ?array
    {
        $anbieter = $this->db->queryOne(
            "SELECT a.* FROM lam_anbieter a WHERE a.id = ? AND a.geloescht_am IS NULL",
            [$id]
        );
        if (!$anbieter) {
            return null;
        }
        // Kontakte mit Telefon/Email/Rolle/Prioritaet
        $anbieter['kontakte'] = $this->db->query(
            "SELECT id, vorname, nachname,
                    TRIM(CONCAT(COALESCE(vorname, ''), ' ', COALESCE(nachname, ''))) AS name,
                    email, telefon, rolle, verifikation_status, prioritaet, erstellt_am
             FROM lam_kontakte
             WHERE anbieter_id = ? AND geloescht_am IS NULL
             ORDER BY prioritaet ASC, nachname ASC",
            [$id]
        );

        // Domains nach Rolle getrennt (ueber Pivot-Tabelle)
        $anbieter['betreibt_domains_anzahl'] = (int) $this->db->queryValue(
            "SELECT COUNT(DISTINCT d.id)
             FROM lam_domains d
             JOIN lam_domain_anbieter da ON da.domain_id = d.id
             WHERE da.anbieter_id = ? AND da.rolle = 'betreiber' AND d.geloescht_am IS NULL",
            [$id]
        );
        $anbieter['vermittelt_domains_anzahl'] = (int) $this->db->queryValue(
            "SELECT COUNT(DISTINCT d.id)
             FROM lam_domains d
             JOIN lam_domain_anbieter da ON da.domain_id = d.id
             WHERE da.anbieter_id = ? AND da.rolle = 'vermittler' AND d.geloescht_am IS NULL",
            [$id]
        );
        // Bei >50 Domains nicht laden, Link auf Pool-Filter anzeigen
        $anbieter['betreibt_domains'] = $anbieter['betreibt_domains_anzahl'] <= 50 ? $this->db->query(
            "SELECT DISTINCT d.id, d.url, d.verifikation_status, d.disqualifiziert, d.letzter_check_am
             FROM lam_domains d
             JOIN lam_domain_anbieter da ON da.domain_id = d.id
             WHERE da.anbieter_id = ? AND da.rolle = 'betreiber' AND d.geloescht_am IS NULL
             ORDER BY d.url ASC",
            [$id]
        ) : [];
        $anbieter['vermittelt_domains'] = $anbieter['vermittelt_domains_anzahl'] <= 50 ? $this->db->query(
            "SELECT DISTINCT d.id, d.url, d.verifikation_status, d.disqualifiziert, d.letzter_check_am
             FROM lam_domains d
             JOIN lam_domain_anbieter da ON da.domain_id = d.id
             WHERE da.anbieter_id = ? AND da.rolle = 'vermittler' AND d.geloescht_am IS NULL
             ORDER BY d.url ASC",
            [$id]
        ) : [];

        // Rollen-Label
        if ($anbieter['ist_betreiber'] && $anbieter['ist_vermittler']) {
            $anbieter['rollen_label'] = 'Betreiber + Vermittler';
        } elseif ($anbieter['ist_vermittler']) {
            $anbieter['rollen_label'] = 'Vermittler';
        } else {
            $anbieter['rollen_label'] = 'Betreiber';
        }

        // Korrespondenz aggregiert (alle Vorgaenge mit diesem Anbieter)
        $anbieter['korrespondenz'] = $this->db->query(
            "SELECT k.id, k.typ, k.zeitpunkt, k.inhalt, k.betreff,
                    k.absender_mail, k.empfaenger_mail, k.status,
                    k.anhang_originalname, k.anhang_pfad,
                    ko.id AS kontakt_id, ko.nachname AS kontakt_nachname, ko.vorname AS kontakt_vorname
             FROM lam_kommunikation k
             LEFT JOIN lam_kontakte ko ON ko.id = k.kontakt_id
             WHERE k.anbieter_id = ? AND k.geloescht_am IS NULL
             ORDER BY k.zeitpunkt DESC
             LIMIT 200",
            [$id]
        );

        return $anbieter;
    }

    public function getDomainDetail(string $id): ?array
    {
        $domain = $this->db->queryOne(
            "SELECT d.*, a.name AS anbieter_name, a.firma AS anbieter_firma,
                    a.beziehungsstatus AS anbieter_beziehung, a.ist_betreiber, a.ist_vermittler
             FROM lam_domains d
             LEFT JOIN lam_anbieter a ON a.id = d.anbieter_id
             WHERE d.id = ?",
            [$id]
        );
        if (!$domain) {
            return null;
        }

        // Primärkontakt des Anbieters
        $domain['anbieter_primaerkontakt'] = null;
        if (!empty($domain['anbieter_id'])) {
            $domain['anbieter_primaerkontakt'] = $this->db->queryOne(
                "SELECT id,
                        TRIM(CONCAT(COALESCE(vorname, ''), ' ', COALESCE(nachname, ''))) AS name,
                        email, telefon, rolle
                 FROM lam_kontakte
                 WHERE anbieter_id = ? AND geloescht_am IS NULL
                 ORDER BY prioritaet ASC, nachname ASC
                 LIMIT 1",
                [$domain['anbieter_id']]
            );
        }

        // Alle verknüpften Anbieter — sortiert nach manueller Position (Default: Betreiber zuerst)
        $domain['anbieter_liste'] = $this->db->query(
            "SELECT a.id, a.name, a.firma, a.beziehungsstatus,
                    da.ist_betreiber AS dom_betreiber, da.ist_vermittler AS dom_vermittler,
                    da.position, da.id AS junction_id,
                    (SELECT TRIM(CONCAT(COALESCE(k.vorname, ''), ' ', COALESCE(k.nachname, '')))
                       FROM lam_kontakte k
                       WHERE k.anbieter_id = a.id AND k.geloescht_am IS NULL
                       ORDER BY k.prioritaet ASC LIMIT 1) AS hauptkontakt_name,
                    (SELECT k.email FROM lam_kontakte k
                       WHERE k.anbieter_id = a.id AND k.geloescht_am IS NULL AND k.email IS NOT NULL
                       ORDER BY k.prioritaet ASC LIMIT 1) AS hauptkontakt_email,
                    (SELECT k.telefon FROM lam_kontakte k
                       WHERE k.anbieter_id = a.id AND k.geloescht_am IS NULL AND k.telefon IS NOT NULL
                       ORDER BY k.prioritaet ASC LIMIT 1) AS hauptkontakt_telefon
             FROM lam_domain_anbieter da
             JOIN lam_anbieter a ON a.id = da.anbieter_id AND a.geloescht_am IS NULL
             WHERE da.domain_id = ?
             ORDER BY da.position ASC, da.erstellt_am ASC",
            [$id]
        );

        $domain['konditionen'] = $this->db->query(
            "SELECT k.*, a.name AS via_anbieter_name
             FROM lam_konditionen k
             LEFT JOIN lam_anbieter a ON a.id = k.via_anbieter_id
             WHERE k.domain_id = ? AND k.geloescht_am IS NULL
             ORDER BY k.buchungstyp, k.preis ASC",
            [$id]
        );
        $domain['kennzahlen'] = $this->db->query(
            "SELECT erfasst_am, si, dp, domain_alter, quelle
             FROM lam_kennzahl_snapshots
             WHERE domain_id = ?
             ORDER BY erfasst_am DESC
             LIMIT 10",
            [$id]
        );
        $domain['tags'] = $this->db->query(
            "SELECT t.id, t.slug, t.name, dt.primaer
             FROM lam_tags t
             JOIN lam_domain_tag dt ON dt.tag_id = t.id
             WHERE dt.domain_id = ? AND t.geloescht_am IS NULL
             ORDER BY dt.primaer DESC, t.name ASC",
            [$id]
        );
        $domain['kunden'] = $this->db->query(
            "SELECT c.id, c.name, c.abbreviation
             FROM customers c
             JOIN lam_domain_customer dc ON dc.customer_id = c.id
             WHERE dc.domain_id = ?
             ORDER BY c.name ASC",
            [$id]
        );

        // Linkprofil-Verlinkungen pro Kunde (Backlinks von dieser Domain)
        $domain['verlinkungen_pro_kunde'] = $this->db->query(
            "SELECT c.id AS customer_id, c.abbreviation, c.name AS customer_name,
                    COUNT(*) AS anzahl_gesamt,
                    SUM(CASE WHEN v.ist_neu = 1 THEN 1 ELSE 0 END) AS anzahl_neu,
                    SUM(CASE WHEN v.empfehlung = 'disavow' THEN 1 ELSE 0 END) AS anzahl_disavow,
                    SUM(CASE WHEN v.empfehlung = 'lassen' THEN 1 ELSE 0 END) AS anzahl_lassen,
                    SUM(CASE WHEN v.empfehlung IS NULL THEN 1 ELSE 0 END) AS anzahl_ohne_empfehlung
             FROM lam_verlinkungen v
             JOIN customers c ON c.id = v.customer_id
             WHERE v.domain = ? AND v.geloescht_am IS NULL
             GROUP BY c.id
             ORDER BY anzahl_gesamt DESC",
            [$domain['url']]
        );

        // Maßnahmen mit dieser Domain
        $domain['massnahmen'] = $this->db->query(
            "SELECT m.id, m.status, m.vorgangstyp, m.geplant_am, m.veroeffentlicht_am,
                    m.veroeffentlichungs_url, m.linktext,
                    c.abbreviation AS customer_kuerzel, c.name AS customer_name
             FROM lam_massnahmen m
             LEFT JOIN customers c ON c.id = m.customer_id
             WHERE m.domain_id = ? AND m.geloescht_am IS NULL
             ORDER BY m.erstellt_am DESC",
            [$id]
        );

        // Linkoptionen-Einträge mit dieser Domain
        $domain['linkoptionen'] = $this->db->query(
            "SELECT e.id, e.status, e.kontakt_am, e.preis_kunde, e.artikelthema,
                    v.name AS liste_titel,
                    c.abbreviation AS customer_kuerzel
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             LEFT JOIN customers c ON c.id = v.customer_id
             WHERE e.domain_id = ?
             ORDER BY e.position ASC",
            [$id]
        );

        // Domain-Wissen (übergreifende Klassifikation)
        $domain['domain_wissen'] = $this->db->queryOne(
            "SELECT linkart, reduktionsstrategie, confidence, anzahl_klassifikationen,
                    notiz, empfehlung_default
             FROM lam_domain_wissen
             WHERE domain = ?",
            [$domain['url']]
        );

        // Externe Links (Impressum, weitere Quellen)
        $domain['externe_links'] = $this->db->query(
            "SELECT id, typ, label, url
             FROM lam_domain_links
             WHERE domain_id = ? AND geloescht_am IS NULL
             ORDER BY position ASC",
            [$id]
        );

        // Audit-Log
        $domain['audit_log'] = $this->db->query(
            "SELECT al.id, al.aktion, al.payload, al.ist_bulk, al.anzahl_betroffen,
                    al.zeitpunkt, u.name AS user_name
             FROM lam_audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_typ = 'domain' AND al.entity_id = ?
             ORDER BY al.zeitpunkt DESC
             LIMIT 20",
            [$id]
        );

        return $domain;
    }

    // ---------------------------------------------------------------------
    // Linkprofil
    // ---------------------------------------------------------------------

    public function listeLinkprofilKunden(): array
    {
        return $this->db->query(
            "SELECT c.id, c.abbreviation, c.name,
                    COUNT(v.id) AS verlinkungen_gesamt,
                    SUM(CASE WHEN v.geloescht_am IS NULL THEN 1 ELSE 0 END) AS verlinkungen_aktiv,
                    SUM(CASE WHEN v.ist_neu = 1 AND v.geloescht_am IS NULL THEN 1 ELSE 0 END) AS verlinkungen_neu,
                    SUM(CASE WHEN v.empfehlung = 'disavow' AND v.geloescht_am IS NULL THEN 1 ELSE 0 END) AS disavow,
                    SUM(CASE WHEN v.empfehlung = 'unsicher' AND v.geloescht_am IS NULL THEN 1 ELSE 0 END) AS unsicher,
                    SUM(CASE WHEN v.linkart IS NULL AND v.geloescht_am IS NULL THEN 1 ELSE 0 END) AS ohne_linkart
             FROM customers c
             JOIN lam_verlinkungen v ON v.customer_id = c.id
             GROUP BY c.id
             -- Alphabetisch nach Kuerzel (Abbreviation), bei leerem Kuerzel nach Name als Fallback
             ORDER BY COALESCE(NULLIF(c.abbreviation, ''), c.name) ASC"
        );
    }

    public function listeVerlinkungen(int $customerId, array $filter = []): array
    {
        $where  = ['v.customer_id = ?', 'v.geloescht_am IS NULL'];
        $params = [$customerId];

        if (!empty($filter['suche'])) {
            $where[] = '(v.verlinkende_url LIKE ? OR v.domain LIKE ? OR v.linktext LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        // Multi-Select: linkart, empfehlung, importquelle koennen Arrays sein
        if (!empty($filter['linkart'])) {
            $liste = is_array($filter['linkart']) ? $filter['linkart'] : [$filter['linkart']];
            $platzhalter = implode(',', array_fill(0, count($liste), '?'));
            $where[] = "v.linkart IN ({$platzhalter})";
            foreach ($liste as $l) $params[] = $l;
        }
        if (!empty($filter['empfehlung'])) {
            $liste = is_array($filter['empfehlung']) ? $filter['empfehlung'] : [$filter['empfehlung']];
            $platzhalter = implode(',', array_fill(0, count($liste), '?'));
            $where[] = "v.empfehlung IN ({$platzhalter})";
            foreach ($liste as $e) $params[] = $e;
        }
        if (!empty($filter['importquelle'])) {
            $liste = is_array($filter['importquelle']) ? $filter['importquelle'] : [$filter['importquelle']];
            $platzhalter = implode(',', array_fill(0, count($liste), '?'));
            $where[] = "v.imported_from IN ({$platzhalter})";
            foreach ($liste as $q) $params[] = $q;
        }
        if (!empty($filter['nur_neu'])) {
            $where[] = 'v.ist_neu = 1';
        }
        if (!empty($filter['nur_topp'])) {
            $where[] = 'v.ist_topp = 1';
        }
        if (!empty($filter['nur_ohne_linkart'])) {
            $where[] = 'v.linkart IS NULL';
        }
        if (!empty($filter['ohne_empfehlung'])) {
            $where[] = 'v.empfehlung IS NULL';
        }
        if (!empty($filter['ohne_linktext'])) {
            $where[] = '(v.linktext IS NULL OR v.linktext = "")';
        }
        if (!empty($filter['ohne_ziel_url'])) {
            $where[] = '(v.ziel_url IS NULL OR v.ziel_url = \'\')';
        }
        if (!empty($filter['ohne_bemerkung'])) {
            $where[] = '(v.bemerkung IS NULL OR v.bemerkung = \'\')';
        }
        if (!empty($filter['nur_link_verloren'])) {
            $where[] = '(v.letzter_http_erreichbar = 1 AND v.linkziel_gefunden = 0)';
        }
        if (!empty($filter['nicht_erreichbar'])) {
            // Nur explizit als nicht erreichbar markierte (NULL = noch nicht geprueft, wird ausgeblendet)
            $where[] = 'v.letzter_http_erreichbar = 0';
        }
        // SI/DP-Filter haengen vom jeweils letzten Snapshot mit SI bzw. DP ab
        if (!empty($filter['ohne_si'])) {
            $where[] = 'latest_si.si IS NULL';
        }
        if (!empty($filter['ohne_dp'])) {
            $where[] = 'latest_dp.dp IS NULL';
        }
        if (!empty($filter['follow'])) {
            if ($filter['follow'] === 'follow')      $where[] = 'v.is_follow = 1';
            elseif ($filter['follow'] === 'nofollow') $where[] = 'v.is_follow = 0';
            elseif ($filter['follow'] === 'unbekannt') $where[] = 'v.is_follow IS NULL';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $limit  = isset($filter['limit'])  ? max(1, min(500, (int) $filter['limit']))  : 50;
        $offset = isset($filter['offset']) ? max(0, (int) $filter['offset']) : 0;

        // Sortierung: Frontend schickt sort/order. Whitelist gegen SQL-Injection.
        $sortMap = [
            'domain'       => 'v.domain',
            'url'          => 'v.verlinkende_url',
            'linktext'     => 'v.linktext',
            'ziel_url'     => 'v.ziel_url',
            'linkart'      => 'v.linkart',
            'empfehlung'   => 'v.empfehlung',
            'http'         => 'v.letzter_http_status',
            'bemerkung'    => 'v.bemerkung',
            'neu'          => 'v.ist_neu',
            'topp'         => 'v.ist_topp',
            'quelle'       => 'v.imported_from',
            'haeufigkeit'  => 'haeufigkeit_domain',
            'sistrix'      => 'sistrix_index',
            'popularitaet' => 'domain_popularitaet',
            'erstellt_am'  => 'v.erstellt_am',
        ];
        $sortKey = $filter['sort'] ?? 'erstellt_am';
        $sortCol = $sortMap[$sortKey] ?? 'v.erstellt_am';
        $sortDir = (isset($filter['order']) && strtolower($filter['order']) === 'asc') ? 'ASC' : 'DESC';
        // NULL-Werte ans Ende egal welche Richtung
        $orderBy = "{$sortCol} IS NULL, {$sortCol} {$sortDir}";

        // Latest Sistrix/DP-Snapshot pro Domain — Sub-Query per LEFT JOIN
        // (eindeutig durch (domain_id, MAX(erfasst_am)))
        $rows = $this->db->query(
            "SELECT v.id, v.verlinkende_url, v.domain, v.linktext, v.linkart,
                    v.empfehlung, v.status, v.bemerkung, v.ist_neu, v.ist_topp, v.imported_from,
                    v.aufraeum_status, v.ziel_url, v.letzter_http_status,
                    v.letzter_http_erreichbar, v.linkziel_gefunden, v.is_follow,
                    v.erstellt_am,
                    -- 'Wie oft': Anzahl Verlinkungen von dieser Domain fuer denselben Kunden
                    (SELECT COUNT(*) FROM lam_verlinkungen v2
                       WHERE v2.customer_id = v.customer_id AND v2.domain = v.domain
                         AND v2.geloescht_am IS NULL) AS haeufigkeit_domain,
                    latest_si.si AS sistrix_index,
                    latest_dp.dp AS domain_popularitaet
             FROM lam_verlinkungen v
             LEFT JOIN lam_domains d ON d.url = v.domain AND d.geloescht_am IS NULL
             LEFT JOIN (
                 SELECT s1.domain_url, s1.si
                 FROM lam_kennzahl_snapshots s1
                 INNER JOIN (
                     SELECT domain_url, MAX(erfasst_am) AS latest
                     FROM lam_kennzahl_snapshots
                     WHERE si IS NOT NULL
                     GROUP BY domain_url
                 ) s2 ON s1.domain_url = s2.domain_url AND s1.erfasst_am = s2.latest
             ) latest_si ON latest_si.domain_url = v.domain
             LEFT JOIN (
                 SELECT s3.domain_url, s3.dp
                 FROM lam_kennzahl_snapshots s3
                 INNER JOIN (
                     SELECT domain_url, MAX(erfasst_am) AS latest
                     FROM lam_kennzahl_snapshots
                     WHERE dp IS NOT NULL
                     GROUP BY domain_url
                 ) s4 ON s3.domain_url = s4.domain_url AND s3.erfasst_am = s4.latest
             ) latest_dp ON latest_dp.domain_url = v.domain
             {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        // Count-Query muss dieselben JOINs haben wie die Daten-Query, weil die WHERE
        // ggf. auf latest_si.si / latest_dp.dp filtert.
        $gesamt = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_verlinkungen v
             LEFT JOIN lam_domains d ON d.url = v.domain AND d.geloescht_am IS NULL
             LEFT JOIN (
                 SELECT s1.domain_url, s1.si
                 FROM lam_kennzahl_snapshots s1
                 INNER JOIN (
                     SELECT domain_url, MAX(erfasst_am) AS latest
                     FROM lam_kennzahl_snapshots
                     WHERE si IS NOT NULL
                     GROUP BY domain_url
                 ) s2 ON s1.domain_url = s2.domain_url AND s1.erfasst_am = s2.latest
             ) latest_si ON latest_si.domain_url = v.domain
             LEFT JOIN (
                 SELECT s3.domain_url, s3.dp
                 FROM lam_kennzahl_snapshots s3
                 INNER JOIN (
                     SELECT domain_url, MAX(erfasst_am) AS latest
                     FROM lam_kennzahl_snapshots
                     WHERE dp IS NOT NULL
                     GROUP BY domain_url
                 ) s4 ON s3.domain_url = s4.domain_url AND s3.erfasst_am = s4.latest
             ) latest_dp ON latest_dp.domain_url = v.domain
             {$whereSql}",
            $params
        );

        $statistik = $this->db->queryOne(
            "SELECT
                COUNT(*) AS gesamt,
                SUM(CASE WHEN ist_neu = 1 THEN 1 ELSE 0 END) AS neu,
                SUM(CASE WHEN empfehlung = 'lassen' THEN 1 ELSE 0 END) AS lassen,
                SUM(CASE WHEN empfehlung = 'aendern' THEN 1 ELSE 0 END) AS aendern,
                SUM(CASE WHEN empfehlung = 'loeschen' THEN 1 ELSE 0 END) AS loeschen,
                SUM(CASE WHEN empfehlung = 'disavow' THEN 1 ELSE 0 END) AS disavow,
                SUM(CASE WHEN empfehlung = 'unsicher' THEN 1 ELSE 0 END) AS unsicher,
                SUM(CASE WHEN empfehlung = 'geloescht' THEN 1 ELSE 0 END) AS geloescht,
                SUM(CASE WHEN empfehlung IS NULL THEN 1 ELSE 0 END) AS ohne_empfehlung,
                SUM(CASE WHEN linkart IS NULL THEN 1 ELSE 0 END) AS ohne_linkart
             FROM lam_verlinkungen
             WHERE customer_id = ? AND geloescht_am IS NULL",
            [$customerId]
        );

        return [
            'gesamt'    => $gesamt,
            'limit'     => $limit,
            'offset'    => $offset,
            'statistik' => $statistik,
            'rows'      => $rows,
        ];
    }

    /**
     * Inline-Update fuer Verlinkungs-Felder (linkart, empfehlung, bemerkung, status).
     */
    public function aktualisiereVerlinkungFeld(string $id, string $feld, $wert): void
    {
        $erlaubteFelder = ['linkart', 'empfehlung', 'bemerkung', 'status', 'ziel_url', 'linktext', 'ist_topp'];
        $erlaubteEmpfehlung = ['lassen', 'aendern', 'loeschen', 'disavow', 'geloescht', 'unsicher'];

        if (!in_array($feld, $erlaubteFelder, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt: ' . $feld);
        }
        if ($feld === 'empfehlung' && $wert !== null && $wert !== '' && !in_array($wert, $erlaubteEmpfehlung, true)) {
            throw new \InvalidArgumentException('Ungueltige Empfehlung');
        }
        // Topp ist ein Ja/Nein-Kennzeichen (0/1)
        if ($feld === 'ist_topp') {
            $this->db->execute(
                "UPDATE lam_verlinkungen SET ist_topp = ? WHERE id = ?",
                [filter_var($wert, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $id]
            );
            return;
        }

        $sicheresWert = ($wert === '' || $wert === null) ? null : $wert;
        if (is_string($sicheresWert)) $sicheresWert = trim($sicheresWert);

        $this->db->execute(
            "UPDATE lam_verlinkungen SET `{$feld}` = ? WHERE id = ?",
            [$sicheresWert, $id]
        );
    }

    /**
     * CSV-Import fuer Linkprofil-Verlinkungen.
     *
     * Erkennt automatisch das Format anhand der Header-Zeile. Aktuell unterstuetzt:
     *  - Sistrix-Backlinks (CSV/TSV mit Spalten: Verlinkende URL, Linktext, Linkziel, ggf. weitere)
     *  - AHREFs (URL, Anchor, Target URL, Follow/Nofollow)
     *  - Generisch (verlinkende_url, domain, linktext, ziel_url, linkart)
     *
     * @return array{neu:int, doppelt:int, gesamt:int, format:string, fehler:array}
     */
    public function importiereLinkprofilCsv(int $customerId, string $csvContent, string $quelleHinweis = ''): array
    {
        // Erkennung Trenner
        $erste = strtok($csvContent, "\n");
        $sep = (substr_count($erste, "\t") >= 2) ? "\t" : ((substr_count($erste, ';') >= 2) ? ';' : ',');

        // BOM entfernen
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);

        $zeilen = preg_split('/\r?\n/', $csvContent);
        if (count($zeilen) < 2) {
            throw new \InvalidArgumentException('CSV enthält keine Daten');
        }
        $header = str_getcsv((string)array_shift($zeilen), $sep);
        $header = array_map(fn($h) => mb_strtolower(trim((string)$h)), $header);

        // Spalten-Mapping je Format. Reihenfolge wichtig: spezifischere Formate zuerst.
        // Bei Formaten ohne Ziel-URL (z.B. GSC) reicht „Quell-URL gefunden" als Match.
        $mappings = [
            'sistrix' => [
                // ECHTE Sistrix-Backlink-CSV (semikolon-separiert, englische snake_case Header)
                'verlinkende_url' => ['from_url', 'verlinkende url', 'quellseite', 'source url'],
                'linktext'        => ['text', 'linktext', 'anchor', 'anchor text'],
                'ziel_url'        => ['to_url', 'linkziel', 'ziel-url', 'target url'],
                'domain'          => ['from_domain', 'domain'],
                'follow'          => ['follow', 'rel'],
            ],
            'ahrefs' => [
                'verlinkende_url' => ['referring page url', 'url from'],
                'linktext'        => ['anchor', 'text'],
                'ziel_url'        => ['target url', 'link to'],
                'follow'          => ['nofollow'],
            ],
            'xovi' => [
                // XOVI „neu"-Export (Tab-separiert, gequotete Header mit Komma im Namen)
                'verlinkende_url' => ['linkende website, linkziel', 'linkende website'],
                'linktext'        => ['ankertext', 'anker', 'anchor'],
                'ziel_url'        => ['linkziel (url)', 'linkziel'],
            ],
            'gsc' => [
                // Google Search Console „Latest links" / „More sample links"
                // Nur Quell-URL, keine Ziel-URL (alles auf Kunden-Domain implizit)
                'verlinkende_url' => ['verweisende seite', 'verweisende seiten', 'top-verweisende seiten', 'referring page'],
            ],
            'generisch' => [
                'verlinkende_url' => ['verlinkende_url', 'url', 'verlinkende url'],
                'linktext'        => ['linktext', 'anchor', 'anker'],
                'ziel_url'        => ['ziel_url', 'ziel-url', 'linkziel'],
                'linkart'         => ['linkart'],
                'domain'          => ['domain'],
            ],
        ];

        // Hartes „kein Linkprofil-Import"-Format: GSC „Top target pages" ist eine
        // Aggregat-Statistik pro Landingpage, keine Einzel-Links. Frueh und klar abfangen.
        if (in_array('landingpage', $header, true) && in_array('eingehende links', $header, true)) {
            throw new \InvalidArgumentException(
                'GSC „Top target pages" enthaelt keine einzelnen Quell-URLs (nur Statistik pro Landingpage). '
                . 'Bitte stattdessen „Latest links" oder „More sample links" aus GSC exportieren.'
            );
        }

        $format = 'generisch';
        foreach ($mappings as $name => $felder) {
            $hasUrl = false;
            foreach ($felder['verlinkende_url'] as $kandidat) {
                if (in_array($kandidat, $header, true)) { $hasUrl = true; break; }
            }
            if (!$hasUrl) continue;
            // Wenn Format eine ziel_url-Spalte erwartet, muss sie da sein
            if (!empty($felder['ziel_url'])) {
                $hasZiel = false;
                foreach ($felder['ziel_url'] as $kandidat) {
                    if (in_array($kandidat, $header, true)) { $hasZiel = true; break; }
                }
                if (!$hasZiel) continue;
            }
            $format = $name;
            break;
        }

        $felder = $mappings[$format];
        $idxOf = function(array $kandidaten) use ($header): ?int {
            foreach ($kandidaten as $k) {
                $i = array_search($k, $header, true);
                if ($i !== false) return $i;
            }
            return null;
        };
        $idxUrl  = $idxOf($felder['verlinkende_url']);
        $idxText = $idxOf($felder['linktext'] ?? []);
        $idxZiel = $idxOf($felder['ziel_url'] ?? []);
        $idxFollow = $idxOf($felder['follow'] ?? []);
        $idxLinkart = $idxOf($felder['linkart'] ?? []);
        $idxDomain = $idxOf($felder['domain'] ?? []);

        if ($idxUrl === null) throw new \InvalidArgumentException('Spalte "Verlinkende URL" nicht gefunden');

        $neu = 0; $doppelt = 0; $gesamt = 0; $fehler = [];
        $quelle = $quelleHinweis ?: $format;

        foreach ($zeilen as $nr => $rohZeile) {
            $rohZeile = trim($rohZeile);
            if ($rohZeile === '') continue;
            $cols = str_getcsv($rohZeile, $sep);
            $gesamt++;
            $url = trim((string)($cols[$idxUrl] ?? ''));
            if ($url === '') continue;

            $domain = $idxDomain !== null ? trim((string)$cols[$idxDomain]) : (parse_url($url, PHP_URL_HOST) ?: '');
            $domain = preg_replace('/^www\./', '', $domain);
            $linktext = $idxText !== null ? trim((string)$cols[$idxText]) : null;
            $zielUrl  = $idxZiel !== null ? trim((string)$cols[$idxZiel]) : null;
            $isFollow = null;
            if ($idxFollow !== null) {
                $v = mb_strtolower(trim((string)$cols[$idxFollow]));
                if ($v === 'follow' || $v === 'true' || $v === '1' || $v === 'ja') $isFollow = 1;
                elseif ($v === 'nofollow' || $v === 'false' || $v === '0' || $v === 'nein') $isFollow = 0;
            }
            $linkart = $idxLinkart !== null ? (trim((string)$cols[$idxLinkart]) ?: null) : null;

            $urlHash = sha1($url);
            // Duplikat-Check: gleiche customer_id + url_hash
            $existId = $this->db->queryValue(
                "SELECT id FROM lam_verlinkungen WHERE customer_id = ? AND url_hash = ? AND geloescht_am IS NULL",
                [$customerId, $urlHash]
            );
            if ($existId) {
                $doppelt++;
                continue;
            }
            try {
                $this->db->insert('lam_verlinkungen', [
                    'id' => $this->generiereUlid(),
                    'customer_id' => $customerId,
                    'verlinkende_url' => $url,
                    'url_hash' => $urlHash,
                    'domain' => $domain,
                    'linktext' => $linktext,
                    'ziel_url' => $zielUrl,
                    'linkart' => $linkart,
                    'is_follow' => $isFollow,
                    'ist_neu' => 1,
                    'imported_from' => $quelle,
                ]);
                $neu++;
            } catch (\Throwable $e) {
                // DB-UNIQUE-Constraint-Verletzungen sind auch Duplikate (Pre-Check
                // erwischt sie nicht immer wenn URL leicht normalisiert wurde)
                if (str_contains($e->getMessage(), '1062')) {
                    $doppelt++;
                } else {
                    $fehler[] = "Zeile " . ($nr + 2) . ": " . $e->getMessage();
                }
            }
        }

        return [
            'format' => $format,
            'gesamt' => $gesamt,
            'neu' => $neu,
            'doppelt' => $doppelt,
            'fehler' => $fehler,
        ];
    }

    public function bulkAktualisiereVerlinkungen(array $ids, string $aktion, $wert): array
    {
        $erfolge = 0;
        $fehler = [];
        foreach ($ids as $id) {
            try {
                if ($aktion === 'linkart_setzen') {
                    $this->aktualisiereVerlinkungFeld($id, 'linkart', $wert);
                } elseif ($aktion === 'empfehlung_setzen') {
                    $this->aktualisiereVerlinkungFeld($id, 'empfehlung', $wert);
                } elseif ($aktion === 'topp_setzen') {
                    $this->db->execute(
                        "UPDATE lam_verlinkungen SET ist_topp = ? WHERE id = ?",
                        [filter_var($wert, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $id]
                    );
                } elseif ($aktion === 'loeschen') {
                    $this->db->execute(
                        "UPDATE lam_verlinkungen SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
                        [$id]
                    );
                } else {
                    throw new \InvalidArgumentException('Unbekannte Aktion');
                }
                $erfolge++;
            } catch (\Exception $e) {
                $fehler[] = $id . ': ' . $e->getMessage();
            }
        }
        $this->auditBulk('verlinkung.bulk_' . $aktion, 'verlinkung', $erfolge, ['wert' => $wert, 'fehler' => count($fehler)]);
        return ['erfolge' => $erfolge, 'fehler' => $fehler];
    }

    // ---------------------------------------------------------------------
    // Linkoptionen (alle Vorschlagslisten-Eintraege)
    // ---------------------------------------------------------------------

    public function listeLinkoptionen(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(d.url LIKE ? OR e.notiz LIKE ? OR e.artikelthema LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['customer_id'])) {
            $where[] = 'v.customer_id = ?';
            $params[] = (int) $filter['customer_id'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->query(
            "SELECT e.id, e.status, e.kontakt_am, e.letzte_rueckmeldung_am,
                    e.letzte_rueckmeldung_typ, e.naechste_aktion_am,
                    e.preis_kunde, e.preis_anbieter, e.beispielartikel_url, e.artikelthema, e.kontext_einbau, e.mit_anbieternennung,
                    e.ziel_url, e.notiz, e.vorgeschlagener_linktext, e.position, e.massnahme_id,
                    d.id AS domain_id, d.url AS domain_url,
                    v.id AS liste_id, v.name AS liste_titel,
                    c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name,
                    -- Anbieter + Primaerkontakt-Email (falls vorhanden) fuer Quick-Kontakt
                    -- Auswahl: Nicht-Vermittler ZUERST (Tom-Regel: performanceliebe/RRDS/eology = Vermittler,
                    --   echter Anbieter hat IMMER Vorrang), dann betreiber-Rolle, dann älteste Verbindung.
                    (SELECT a.id FROM lam_domain_anbieter da
                       JOIN lam_anbieter a ON a.id = da.anbieter_id
                       WHERE da.domain_id = d.id
                       ORDER BY a.ist_vermittler ASC, (da.rolle = 'betreiber') DESC, da.erstellt_am ASC LIMIT 1) AS anbieter_id,
                    (SELECT a.name FROM lam_domain_anbieter da
                       JOIN lam_anbieter a ON a.id = da.anbieter_id
                       WHERE da.domain_id = d.id
                       ORDER BY a.ist_vermittler ASC, (da.rolle = 'betreiber') DESC, da.erstellt_am ASC LIMIT 1) AS anbieter_name,
                    (SELECT a.ist_vermittler FROM lam_domain_anbieter da
                       JOIN lam_anbieter a ON a.id = da.anbieter_id
                       WHERE da.domain_id = d.id
                       ORDER BY a.ist_vermittler ASC, (da.rolle = 'betreiber') DESC, da.erstellt_am ASC LIMIT 1) AS anbieter_ist_vermittler,
                    (SELECT k.email FROM lam_domain_anbieter da
                       JOIN lam_kontakte k ON k.anbieter_id = da.anbieter_id
                       WHERE da.domain_id = d.id AND k.email IS NOT NULL AND k.geloescht_am IS NULL
                       ORDER BY k.prioritaet ASC, k.id ASC LIMIT 1) AS primaer_email
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             JOIN lam_domains d ON d.id = e.domain_id
             LEFT JOIN customers c ON c.id = v.customer_id
             {$whereSql}
             ORDER BY e.position ASC, e.id ASC
             LIMIT 500",
            $params
        );
    }

    /**
     * Detail eines einzelnen Linkoption-Eintrags (Vorschlagsliste-Eintrag).
     */
    public function getLinkoptionDetail(string $id): ?array
    {
        $e = $this->db->queryOne(
            "SELECT e.*,
                    d.id AS domain_id, d.url AS domain_url, d.verifikation_status AS domain_status,
                    d.anbieter_id, a.name AS anbieter_name,
                    v.id AS liste_id, v.name AS liste_name, v.zielzahl AS liste_zielzahl,
                    v.status AS liste_status, v.notiz AS liste_notiz,
                    c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name,
                    m.id AS massnahme_id, m.status AS massnahme_status
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             JOIN lam_domains d ON d.id = e.domain_id
             LEFT JOIN lam_anbieter a ON a.id = d.anbieter_id
             LEFT JOIN customers c ON c.id = v.customer_id
             LEFT JOIN lam_massnahmen m ON m.id = e.massnahme_id AND m.geloescht_am IS NULL
             WHERE e.id = ?",
            [$id]
        );
        if (!$e) return null;

        // Korrespondenz zu diesem Eintrag
        $e['kommunikation'] = $this->db->query(
            "SELECT k.id, k.typ, k.zeitpunkt, k.betreff, k.inhalt,
                    k.anhang_originalname, k.anhang_pfad,
                    ko.nachname AS kontakt_nachname, ko.vorname AS kontakt_vorname
             FROM lam_kommunikation k
             LEFT JOIN lam_kontakte ko ON ko.id = k.kontakt_id
             WHERE k.vorschlagsliste_eintrag_id = ? AND k.geloescht_am IS NULL
             ORDER BY k.zeitpunkt DESC
             LIMIT 20",
            [$id]
        );

        // Weitere Einträge in derselben Vorschlagsliste (Kontext)
        $e['liste_geschwister'] = $this->db->query(
            "SELECT e2.id, e2.status, e2.position, e2.preis_kunde,
                    d2.url AS domain_url
             FROM lam_vorschlagsliste_eintraege e2
             JOIN lam_domains d2 ON d2.id = e2.domain_id
             WHERE e2.vorschlagsliste_id = ? AND e2.id != ?
             ORDER BY e2.position ASC
             LIMIT 50",
            [$e['vorschlagsliste_id'], $id]
        );

        return $e;
    }

    /**
     * Status eines Linkoption-Eintrags (Vorschlagslisten-Eintrag) wechseln.
     * Status-Pipeline: vorgeschlagen → in_akquise → bestaetigt/abgelehnt/ohne_antwort
     *                  → kunde_freigegeben/kunde_abgelehnt → abgeschlossen
     */
    public function aktualisiereLinkoptionStatus(string $id, string $neuerStatus): void
    {
        $erlaubt = ['in_planung','vorgeschlagen','in_akquise','bestaetigt','abgelehnt','ohne_antwort','kunde_freigegeben','kunde_abgelehnt','abgeschlossen'];
        if (!in_array($neuerStatus, $erlaubt, true)) {
            throw new \InvalidArgumentException('Ungueltiger Status');
        }
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege SET status = ? WHERE id = ?",
            [$neuerStatus, $id]
        );
    }

    /**
     * Inline-Update fuer beliebige Linkoption-Felder.
     */
    public function aktualisiereLinkoptionFeld(string $id, string $feld, $wert): void
    {
        $erlaubt = ['notiz','vorgeschlagener_linktext','preis_kunde','preis_anbieter','beispielartikel_url','ziel_url','artikelthema','kontext_einbau','mit_anbieternennung','kontakt_am','letzte_rueckmeldung_am','letzte_rueckmeldung_typ','naechste_aktion_am','naechste_aktion_notiz'];
        if (!in_array($feld, $erlaubt, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt');
        }
        $sicheresWert = ($wert === '' || $wert === null) ? null : $wert;
        if (is_string($sicheresWert)) $sicheresWert = trim($sicheresWert);
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege SET `{$feld}` = ? WHERE id = ?",
            [$sicheresWert, $id]
        );
    }

    public function bulkAktualisiereLinkoptionen(array $ids, string $aktion, $wert): array
    {
        $erfolge = 0;
        $fehler = [];
        foreach ($ids as $id) {
            try {
                if ($aktion === 'status_setzen') {
                    $this->aktualisiereLinkoptionStatus($id, (string)$wert);
                } elseif ($aktion === 'loeschen') {
                    $this->db->execute(
                        "DELETE FROM lam_vorschlagsliste_eintraege WHERE id = ?",
                        [$id]
                    );
                } else {
                    throw new \InvalidArgumentException('Unbekannte Aktion');
                }
                $erfolge++;
            } catch (\Exception $e) {
                $fehler[] = $id . ': ' . $e->getMessage();
            }
        }
        $this->auditBulk('linkoption.bulk_' . $aktion, 'linkoption', $erfolge, ['wert' => $wert, 'fehler' => count($fehler)]);
        return ['erfolge' => $erfolge, 'fehler' => $fehler];
    }

    // ---------------------------------------------------------------------
    // Maßnahmen — CRUD
    // ---------------------------------------------------------------------

    /**
     * Auslage anlegen oder aktualisieren (1:1-Beziehung zu Massnahme).
     * Berechnet die Marge automatisch wenn externe_kosten + weiterverrechnet gesetzt.
     */
    public function speichereAuslage(string $massnahmeId, array $data): string
    {
        $externeKosten = ($data['externe_kosten'] ?? '') !== '' ? (float)$data['externe_kosten'] : null;
        $weiterverrechnet = ($data['weiterverrechnet'] ?? '') !== '' ? (float)$data['weiterverrechnet'] : null;
        $marge = ($externeKosten !== null && $weiterverrechnet !== null)
            ? round($weiterverrechnet - $externeKosten, 2) : null;
        $margeGrund = trim((string)($data['marge_grund'] ?? '')) ?: null;
        $rechnungEingang = ($data['rechnung_eingang'] ?? '') !== '' ? $data['rechnung_eingang'] : null;
        $thoxanRechnungNr = trim((string)($data['thoxan_rechnung_nr'] ?? '')) ?: null;
        $thoxanRechnungDatum = ($data['thoxan_rechnung_datum'] ?? '') !== '' ? $data['thoxan_rechnung_datum'] : null;
        $sonderfall = $data['sonderfall'] ?? 'normal';
        if (!in_array($sonderfall, self::AUSLAGE_SONDERFALL, true)) {
            throw new \InvalidArgumentException('Ungültiger Sonderfall.');
        }
        $abgerechnetFuer = trim((string)($data['abgerechnet_fuer'] ?? '')) ?: null;

        // Existiert schon eine Auslage? (1:1)
        $existId = $this->db->queryValue(
            "SELECT id FROM lam_auslagen WHERE massnahme_id = ? AND geloescht_am IS NULL",
            [$massnahmeId]
        );
        if ($existId) {
            $this->db->execute(
                "UPDATE lam_auslagen
                 SET externe_kosten = ?, weiterverrechnet = ?, marge = ?, marge_grund = ?,
                     rechnung_eingang = ?, thoxan_rechnung_nr = ?, thoxan_rechnung_datum = ?,
                     sonderfall = ?, abgerechnet_fuer = ?, aktualisiert_am = NOW()
                 WHERE id = ?",
                [$externeKosten, $weiterverrechnet, $marge, $margeGrund,
                 $rechnungEingang, $thoxanRechnungNr, $thoxanRechnungDatum,
                 $sonderfall, $abgerechnetFuer, $existId]
            );
            return $existId;
        }
        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_auslagen
                (id, massnahme_id, externe_kosten, weiterverrechnet, marge, marge_grund,
                 rechnung_eingang, thoxan_rechnung_nr, thoxan_rechnung_datum,
                 sonderfall, abgerechnet_fuer, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$neueId, $massnahmeId, $externeKosten, $weiterverrechnet, $marge, $margeGrund,
             $rechnungEingang, $thoxanRechnungNr, $thoxanRechnungDatum, $sonderfall, $abgerechnetFuer]
        );
        return $neueId;
    }

    public function loescheAuslage(string $massnahmeId): void
    {
        $this->db->execute(
            "UPDATE lam_auslagen SET geloescht_am = NOW() WHERE massnahme_id = ? AND geloescht_am IS NULL",
            [$massnahmeId]
        );
    }

    public function aktualisiereMassnahmeFeld(string $id, string $feld, $wert): void
    {
        $erlaubt = ['status','vorgangstyp','buchungstyp','linktext','brand_integration',
                    'geplant_am','veroeffentlicht_am','veroeffentlichungs_url','sonderstatus',
                    'monitoring_muted','monitoring_stumm_bis'];
        if (!in_array($feld, $erlaubt, true)) {
            throw new \InvalidArgumentException('Feld nicht erlaubt');
        }
        if ($feld === 'status' && !in_array($wert, self::MASSNAHME_STATUS, true)) {
            throw new \InvalidArgumentException('Ungültiger Status');
        }
        if ($feld === 'vorgangstyp' && $wert !== null && $wert !== ''
            && !in_array($wert, self::MASSNAHME_VORGANGSTYP, true)) {
            throw new \InvalidArgumentException('Ungültiger Vorgangstyp');
        }
        if ($feld === 'buchungstyp' && $wert !== null && $wert !== ''
            && !in_array($wert, self::KONDITION_BUCHUNGSTYP, true)) {
            throw new \InvalidArgumentException('Ungültiger Buchungstyp');
        }
        if ($feld === 'sonderstatus' && $wert !== null && $wert !== ''
            && !in_array($wert, self::MASSNAHME_SONDERSTATUS, true)) {
            throw new \InvalidArgumentException('Ungültiger Sonderstatus');
        }
        $sicheresWert = ($wert === '' || $wert === null) ? null : $wert;
        if (is_string($sicheresWert)) $sicheresWert = trim($sicheresWert);
        $this->db->execute(
            "UPDATE lam_massnahmen SET `{$feld}` = ? WHERE id = ? AND geloescht_am IS NULL",
            [$sicheresWert, $id]
        );
        $this->audit('massnahme.update', 'massnahme', $id, ['feld' => $feld, 'wert' => $sicheresWert]);
    }

    public function loescheMassnahme(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_massnahmen SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
        $this->audit('massnahme.delete', 'massnahme', $id);
    }

    /**
     * Massnahme anlegen oder vorhandene bearbeiten.
     *
     * @param string|null $id  null = anlegen
     * @param array $data
     *   - customer_id (int, beim Anlegen Pflicht)
     *   - domain_id   (string, beim Anlegen Pflicht)
     *   - status      (enum)
     *   - vorgangstyp (enum)
     *   - buchungstyp, linktext, brand_integration
     *   - geplant_am, veroeffentlicht_am (Date)
     *   - veroeffentlichungs_url
     *   - sonderstatus
     *   - linkziel_id, verantwortlich_user_id (optional)
     *   - plan_a_massnahme_id (optional, fuer Plan-B-Verknuepfung)
     */
    // ========================================================================
    // Vorschlagslisten (lam_vorschlagslisten)
    // ========================================================================

    /**
     * @return array Listen mit Eintrag-Count + Customer-Name.
     */
    public function listeVorschlagslisten(array $filter = []): array
    {
        $where = ['v.geloescht_am IS NULL'];
        $params = [];
        if (!empty($filter['customer_id'])) {
            $where[] = 'v.customer_id = ?';
            $params[] = (int)$filter['customer_id'];
        }
        if (!empty($filter['status'])) {
            $where[] = 'v.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['suche'])) {
            $where[] = '(v.name LIKE ? OR v.notiz LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        $sql = "SELECT v.id, v.name, v.status, v.zielzahl, v.notiz, v.erstellt_am,
                       v.customer_id, c.name AS customer_name, c.abbreviation AS customer_kuerzel,
                       (SELECT COUNT(*) FROM lam_vorschlagsliste_eintraege e WHERE e.vorschlagsliste_id = v.id) AS eintrag_count,
                       (SELECT COUNT(*) FROM lam_vorschlagsliste_eintraege e WHERE e.vorschlagsliste_id = v.id AND e.massnahme_id IS NOT NULL) AS massnahme_count
                FROM lam_vorschlagslisten v
                JOIN customers c ON c.id = v.customer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY v.erstellt_am DESC";
        return $this->db->query($sql, $params) ?: [];
    }

    public function getVorschlagsliste(string $id): ?array
    {
        $liste = $this->db->queryOne(
            "SELECT v.*, c.name AS customer_name, c.abbreviation AS customer_kuerzel
             FROM lam_vorschlagslisten v
             JOIN customers c ON c.id = v.customer_id
             WHERE v.id = ? AND v.geloescht_am IS NULL",
            [$id]
        );
        if (!$liste) return null;
        $liste['eintraege'] = $this->db->query(
            "SELECT e.*,
                    d.url AS domain_url, d.verifikation_status AS domain_status, d.linkart AS domain_linkart,
                    m.status AS massnahme_status,
                    (SELECT a.id FROM lam_domain_anbieter da
                       JOIN lam_anbieter a ON a.id = da.anbieter_id
                       WHERE da.domain_id = d.id
                       ORDER BY (da.rolle = 'betreiber') DESC, da.erstellt_am ASC LIMIT 1) AS anbieter_id,
                    (SELECT a.name FROM lam_domain_anbieter da
                       JOIN lam_anbieter a ON a.id = da.anbieter_id
                       WHERE da.domain_id = d.id
                       ORDER BY (da.rolle = 'betreiber') DESC, da.erstellt_am ASC LIMIT 1) AS anbieter_name,
                    (SELECT si FROM lam_kennzahl_snapshots ks
                       WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS si_aktuell,
                    (SELECT dp FROM lam_kennzahl_snapshots ks
                       WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS dp_aktuell,
                    (SELECT GROUP_CONCAT(t.name ORDER BY dt.primaer DESC, t.name SEPARATOR '|')
                       FROM lam_domain_tag dt
                       JOIN lam_tags t ON t.id = dt.tag_id
                       WHERE dt.domain_id = d.id AND t.geloescht_am IS NULL) AS tags,
                    (SELECT COUNT(*) FROM lam_kommunikation kom
                       WHERE kom.vorschlagsliste_eintrag_id = e.id) AS korr_count
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_domains d ON d.id = e.domain_id
             LEFT JOIN lam_massnahmen m ON m.id = e.massnahme_id
             WHERE e.vorschlagsliste_id = ?
             ORDER BY e.position ASC, e.erstellt_am DESC",
            [$id]
        ) ?: [];
        return $liste;
    }

    /**
     * Fügt Domain(s) aus dem Pool zu einer Vorschlagsliste hinzu.
     * Dubletten-sicher: Domain die bereits auf der Liste ist, wird übersprungen (zurückgeliefert in 'skipped').
     * Liefert: ['added' => N, 'skipped' => [domain_id,...], 'eintrag_ids' => [...]].
     */
    public function fuegeDomainsZuVorschlagslisteHinzu(string $vorschlagslisteId, array $domainIds, array $standardwerte = []): array
    {
        $liste = $this->db->queryOne(
            "SELECT id, customer_id FROM lam_vorschlagslisten WHERE id = ? AND geloescht_am IS NULL",
            [$vorschlagslisteId]
        );
        if (!$liste) throw new \InvalidArgumentException('Vorschlagsliste nicht gefunden');

        $domainIds = array_values(array_unique(array_filter(array_map('strval', $domainIds))));
        if (empty($domainIds)) return ['added' => 0, 'skipped' => [], 'eintrag_ids' => []];

        // Existierende Einträge auf dieser Liste — dedup
        $platz = implode(',', array_fill(0, count($domainIds), '?'));
        $existierend = $this->db->query(
            "SELECT domain_id FROM lam_vorschlagsliste_eintraege
             WHERE vorschlagsliste_id = ? AND domain_id IN ($platz)",
            array_merge([$vorschlagslisteId], $domainIds)
        ) ?: [];
        $existSet = array_flip(array_map(fn($r) => $r['domain_id'], $existierend));

        // Auch Domain-Existenz checken
        $gueltigeDomains = $this->db->query(
            "SELECT id FROM lam_domains WHERE id IN ($platz) AND geloescht_am IS NULL",
            $domainIds
        ) ?: [];
        $gueltigSet = array_flip(array_map(fn($r) => $r['id'], $gueltigeDomains));

        // Höchste position auf Liste — neue Einträge ans Ende hängen
        $maxPos = (int) $this->db->queryValue(
            "SELECT COALESCE(MAX(position), 0) FROM lam_vorschlagsliste_eintraege WHERE vorschlagsliste_id = ?",
            [$vorschlagslisteId]
        );

        $statusDefault = $standardwerte['status'] ?? 'in_planung';
        $erlaubteStatus = ['in_planung','vorgeschlagen','in_akquise','bestaetigt','abgelehnt','ohne_antwort','kunde_freigegeben','kunde_abgelehnt','abgeschlossen'];
        if (!in_array($statusDefault, $erlaubteStatus, true)) $statusDefault = 'in_planung';
        $notizDefault = isset($standardwerte['notiz']) ? (trim((string) $standardwerte['notiz']) ?: null) : null;
        $artikelthemaDefault = isset($standardwerte['artikelthema']) ? (trim((string) $standardwerte['artikelthema']) ?: null) : null;

        $added = 0; $skipped = []; $neueIds = [];
        foreach ($domainIds as $did) {
            if (!isset($gueltigSet[$did])) { $skipped[] = $did; continue; }
            if (isset($existSet[$did]))   { $skipped[] = $did; continue; }
            $newId = $this->generiereUlid();
            $maxPos++;
            $this->db->execute(
                "INSERT INTO lam_vorschlagsliste_eintraege
                    (id, vorschlagsliste_id, domain_id, status, position, notiz, artikelthema)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$newId, $vorschlagslisteId, $did, $statusDefault, $maxPos, $notizDefault, $artikelthemaDefault]
            );
            // Konsistenz: Vorschlagslisten-Eintrag impliziert Linkpool-Mitgliedschaft
            $this->db->execute(
                "INSERT IGNORE INTO lam_domain_customer (domain_id, customer_id, erstellt_am)
                 VALUES (?, ?, NOW())",
                [$did, $liste['customer_id']]
            );
            $added++;
            $neueIds[] = $newId;
        }
        return ['added' => $added, 'skipped' => $skipped, 'eintrag_ids' => $neueIds];
    }

    public function speichereVorschlagsliste(?string $id, array $data): string
    {
        $erlaubteStatus = ['entwurf','aktiv','abgeschlossen','archiviert'];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Name erforderlich');
        $customerId = (int)($data['customer_id'] ?? 0);
        $status = $data['status'] ?? 'entwurf';
        if (!in_array($status, $erlaubteStatus, true)) $status = 'entwurf';
        $zielzahl = !empty($data['zielzahl']) ? (int)$data['zielzahl'] : null;
        $notiz = isset($data['notiz']) ? (trim((string)$data['notiz']) ?: null) : null;

        if ($id) {
            $this->db->execute(
                "UPDATE lam_vorschlagslisten SET name = ?, status = ?, zielzahl = ?, notiz = ?
                 WHERE id = ? AND geloescht_am IS NULL",
                [$name, $status, $zielzahl, $notiz, $id]
            );
            return $id;
        }
        if ($customerId <= 0) throw new \InvalidArgumentException('customer_id erforderlich');
        $neueId = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_vorschlagslisten (id, customer_id, name, status, zielzahl, notiz, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$neueId, $customerId, $name, $status, $zielzahl, $notiz]
        );
        return $neueId;
    }

    public function loescheVorschlagsliste(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_vorschlagslisten SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    /**
     * Aus einem Linkoptionen-Eintrag eine Maßnahme erzeugen + verknüpfen.
     * Status der neuen Maßnahme = 'geplant' (User kann später anpassen).
     */
    public function konvertiereLinkoptionZuMassnahme(string $eintragId): string
    {
        $eintrag = $this->db->queryOne(
            "SELECT e.id, e.domain_id, e.vorgeschlagener_linktext, e.artikelthema, e.massnahme_id,
                    v.customer_id
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             WHERE e.id = ?",
            [$eintragId]
        );
        if (!$eintrag) {
            throw new \InvalidArgumentException('Eintrag nicht gefunden');
        }
        if (!empty($eintrag['massnahme_id'])) {
            return (string)$eintrag['massnahme_id']; // bereits konvertiert
        }

        $massnahmeId = $this->speichereMassnahme(null, [
            'customer_id' => (int)$eintrag['customer_id'],
            'domain_id'   => $eintrag['domain_id'],
            'status'      => 'geplant',
            'vorgangstyp' => 'erstveroeffentlichung',
            'linktext'    => $eintrag['vorgeschlagener_linktext'] ?: null,
        ]);

        // Rück-Verknüpfung im Eintrag setzen
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege SET massnahme_id = ? WHERE id = ?",
            [$massnahmeId, $eintragId]
        );

        return $massnahmeId;
    }

    public function speichereMassnahme(?string $id, array $data): string
    {
        $status = $data['status'] ?? 'idee';
        if (!in_array($status, self::MASSNAHME_STATUS, true)) {
            throw new \InvalidArgumentException('Ungültiger Status');
        }
        $vorgangstyp = $data['vorgangstyp'] ?? 'erstveroeffentlichung';
        if (!in_array($vorgangstyp, self::MASSNAHME_VORGANGSTYP, true)) {
            $vorgangstyp = 'erstveroeffentlichung';
        }
        $sonderstatus = $data['sonderstatus'] ?? 'normal';
        if (!in_array($sonderstatus, self::MASSNAHME_SONDERSTATUS, true)) {
            $sonderstatus = 'normal';
        }
        // Wenn Plan-A-Referenz vorhanden, sonderstatus automatisch auf plan_b
        if (!empty($data['plan_a_massnahme_id']) && $sonderstatus === 'normal') {
            $sonderstatus = 'plan_b';
        }

        $felder = [
            'status' => $status,
            'vorgangstyp' => $vorgangstyp,
            'sonderstatus' => $sonderstatus,
            'buchungstyp' => isset($data['buchungstyp']) ? (trim((string)$data['buchungstyp']) ?: null) : null,
            'linktext' => isset($data['linktext']) ? (trim((string)$data['linktext']) ?: null) : null,
            'brand_integration' => isset($data['brand_integration']) ? (trim((string)$data['brand_integration']) ?: null) : null,
            'geplant_am' => !empty($data['geplant_am']) ? $data['geplant_am'] : null,
            'veroeffentlicht_am' => !empty($data['veroeffentlicht_am']) ? $data['veroeffentlicht_am'] : null,
            'veroeffentlichungs_url' => isset($data['veroeffentlichungs_url']) ? (trim((string)$data['veroeffentlichungs_url']) ?: null) : null,
            'linkziel_id' => !empty($data['linkziel_id']) ? $data['linkziel_id'] : null,
            'verantwortlich_user_id' => !empty($data['verantwortlich_user_id']) ? (int)$data['verantwortlich_user_id'] : null,
            'plan_a_massnahme_id' => !empty($data['plan_a_massnahme_id']) ? $data['plan_a_massnahme_id'] : null,
            'buchungsweg_kondition_id' => !empty($data['buchungsweg_kondition_id']) ? $data['buchungsweg_kondition_id'] : null,
        ];

        if ($id) {
            $set = []; $vals = [];
            foreach ($felder as $k => $v) {
                $set[] = "`$k` = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->execute(
                "UPDATE lam_massnahmen SET " . implode(', ', $set) . " WHERE id = ? AND geloescht_am IS NULL",
                $vals
            );
            return $id;
        }

        // Anlegen: Pflichtfelder pruefen
        $customerId = (int)($data['customer_id'] ?? 0);
        $domainId = trim((string)($data['domain_id'] ?? ''));
        if ($customerId <= 0) throw new \InvalidArgumentException('customer_id erforderlich');
        if ($domainId === '') throw new \InvalidArgumentException('domain_id erforderlich');

        $neueId = $this->generiereUlid();
        $cols = array_merge(['id', 'customer_id', 'domain_id', 'erstellt_am'], array_keys($felder));
        $placeholders = array_fill(0, count($cols), '?');
        $values = array_merge([$neueId, $customerId, $domainId, date('Y-m-d H:i:s')], array_values($felder));
        $this->db->execute(
            "INSERT INTO lam_massnahmen (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")",
            $values
        );
        return $neueId;
    }

    public function bulkAktualisiereMassnahmen(array $ids, string $aktion, $wert): array
    {
        $erfolge = 0;
        $fehler = [];
        foreach ($ids as $id) {
            try {
                if ($aktion === 'status_setzen') {
                    $this->aktualisiereMassnahmeFeld($id, 'status', $wert);
                } elseif ($aktion === 'loeschen') {
                    $this->loescheMassnahme($id);
                } else {
                    throw new \InvalidArgumentException('Unbekannte Aktion');
                }
                $erfolge++;
            } catch (\Exception $e) {
                $fehler[] = $id . ': ' . $e->getMessage();
            }
        }
        $this->auditBulk('massnahme.bulk_' . $aktion, 'massnahme', $erfolge, ['wert' => $wert, 'fehler' => count($fehler)]);
        return ['erfolge' => $erfolge, 'fehler' => $fehler];
    }

    // ---------------------------------------------------------------------
    // Monitoring — Bulk-Aktionen
    // ---------------------------------------------------------------------

    /**
     * Markiert Alert als "akzeptiert" (alert_ausgeloest = 0). Einzeln pro Check-ID.
     * Echtes HTTP-Pruefen passiert nicht (das brauchte einen Crawler-Backend-Job).
     */
    /**
     * Einen HTTP-Check fuer eine Maßnahme ausfuehren und im Monitoring eintragen.
     * Pruefen: erreichbar (HTTP 2xx) und Linktext/Ziel-URL auf Seite vorhanden.
     */
    public function fuehreMonitoringCheckAus(string $massnahmeId): array
    {
        $m = $this->db->queryOne(
            "SELECT m.id, m.linktext, m.veroeffentlichungs_url,
                    lz.url AS linkziel_url,
                    d.url AS domain_url
             FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN lam_linkziele lz ON lz.id = m.linkziel_id
             WHERE m.id = ? AND m.geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m) throw new \InvalidArgumentException('Maßnahme nicht gefunden');

        $url = $m['veroeffentlichungs_url'];
        if (!$url) {
            // Kein Ziel-URL hinterlegt → kein Check möglich
            return ['skipped' => true, 'grund' => 'keine veroeffentlichungs_url'];
        }

        $httpStatus = null; $linkVorhanden = 0; $linkTyp = null; $fehlermeldung = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Thoxan-LAM-Monitoring/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($html === false || $httpStatus === 0) {
            $fehlermeldung = 'Nicht erreichbar' . ($curlError ? ': ' . $curlError : '');
        } elseif ($httpStatus >= 200 && $httpStatus < 400) {
            // Linktext-Suche in HTML
            $linktext = $m['linktext'];
            $linkziel = $m['linkziel_url'];
            if ($linkziel && stripos($html, $linkziel) !== false) {
                $linkVorhanden = 1;
                // follow/nofollow erkennen
                if (preg_match('/<a[^>]+href=["\']' . preg_quote($linkziel, '/') . '["\'][^>]*>/i', $html, $tag)) {
                    $linkTyp = (stripos($tag[0], 'nofollow') !== false) ? 'nofollow' : 'follow';
                }
            } elseif ($linktext && stripos($html, $linktext) !== false) {
                $linkVorhanden = 1;
                $linkTyp = 'unbekannt'; // Linktext gefunden, aber Ziel-URL nicht
            }
        } else {
            $fehlermeldung = 'HTTP ' . $httpStatus;
        }

        $alert = ($httpStatus < 200 || $httpStatus >= 400 || $linkVorhanden === 0) ? 1 : 0;
        $id = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_monitoring_checks
             (id, massnahme_id, zeitpunkt, http_status, link_vorhanden, link_typ, alert_ausgeloest, fehlermeldung, erstellt_am)
             VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, NOW())",
            [$id, $massnahmeId, $httpStatus, $linkVorhanden, $linkTyp, $alert, $fehlermeldung]
        );
        return [
            'check_id' => $id,
            'http_status' => $httpStatus,
            'link_vorhanden' => (bool)$linkVorhanden,
            'link_typ' => $linkTyp,
            'alert' => (bool)$alert,
            'fehler' => $fehlermeldung,
        ];
    }

    public function quittiereMonitoringAlert(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_monitoring_checks SET alert_ausgeloest = 0 WHERE id = ?",
            [$id]
        );
    }

    public function bulkQuittiereAlerts(array $ids): array
    {
        $erfolge = 0;
        foreach ($ids as $id) {
            try { $this->quittiereMonitoringAlert($id); $erfolge++; } catch (\Exception $e) {}
        }
        return ['erfolge' => $erfolge];
    }

    // ---------------------------------------------------------------------
    // Korrespondenz — Anhang-Pfad
    // ---------------------------------------------------------------------

    /**
     * Neuen Korrespondenz-Eintrag anlegen (z.B. Notiz, Anruf, manuelle E-Mail).
     *
     * @param array $data {
     *   typ          (anruf|email|notiz|sms),
     *   inhalt       text,
     *   zeitpunkt    optional, default jetzt,
     *   anbieter_id  (Pflicht),
     *   kontakt_id   optional,
     *   massnahme_id optional,
     *   vorschlagsliste_eintrag_id optional,
     *   betreff      optional,
     *   anhang_pfad/originalname/mime/groesse  optional (vom Upload-Handler)
     * }
     */
    public function speichereKorrespondenz(array $data): string
    {
        $erlaubteTypen = ['anruf','email','notiz','sms','mail_eingang','mail_ausgang'];
        $typ = trim((string)($data['typ'] ?? 'notiz'));
        if (!in_array($typ, $erlaubteTypen, true)) $typ = 'notiz';

        $anbieterId = trim((string)($data['anbieter_id'] ?? ''));
        if ($anbieterId === '') throw new \InvalidArgumentException('anbieter_id erforderlich');

        $id = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_kommunikation
             (id, typ, zeitpunkt, inhalt, anbieter_id, kontakt_id, vorschlagsliste_eintrag_id, massnahme_id,
              user_id, anhang_pfad, anhang_originalname, anhang_mime, anhang_groesse,
              absender_mail, empfaenger_mail, betreff, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $id,
                $typ,
                $data['zeitpunkt'] ?? date('Y-m-d H:i:s'),
                isset($data['inhalt']) ? (trim((string)$data['inhalt']) ?: null) : null,
                $anbieterId,
                !empty($data['kontakt_id']) ? trim((string)$data['kontakt_id']) : null,
                !empty($data['vorschlagsliste_eintrag_id']) ? trim((string)$data['vorschlagsliste_eintrag_id']) : null,
                !empty($data['massnahme_id']) ? trim((string)$data['massnahme_id']) : null,
                !empty($data['user_id']) ? (int)$data['user_id'] : null,
                !empty($data['anhang_pfad']) ? $data['anhang_pfad'] : null,
                !empty($data['anhang_originalname']) ? $data['anhang_originalname'] : null,
                !empty($data['anhang_mime']) ? $data['anhang_mime'] : null,
                !empty($data['anhang_groesse']) ? (int)$data['anhang_groesse'] : null,
                isset($data['absender_mail']) ? (trim((string)$data['absender_mail']) ?: null) : null,
                isset($data['empfaenger_mail']) ? (trim((string)$data['empfaenger_mail']) ?: null) : null,
                isset($data['betreff']) ? (trim((string)$data['betreff']) ?: null) : null,
            ]
        );
        return $id;
    }

    public function loescheKorrespondenz(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_kommunikation SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
            [$id]
        );
    }

    public function getKorrespondenzAnhang(string $id): ?array
    {
        return $this->db->queryOne(
            "SELECT anhang_pfad, anhang_originalname, anhang_mime
             FROM lam_kommunikation
             WHERE id = ? AND geloescht_am IS NULL AND anhang_pfad IS NOT NULL",
            [$id]
        );
    }

    // ---------------------------------------------------------------------
    // Linkakquise (Einträge mit status='in_akquise')
    // ---------------------------------------------------------------------

    public function listeLinkakquise(array $filter = []): array
    {
        $f = $filter;
        $f['status'] = 'in_akquise';
        return $this->listeLinkoptionen($f);
    }

    // ---------------------------------------------------------------------
    // Maßnahmen
    // ---------------------------------------------------------------------

    public function listeMassnahmen(array $filter = []): array
    {
        $where  = ['m.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(d.url LIKE ? OR m.linktext LIKE ? OR m.veroeffentlichungs_url LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['status'])) {
            $where[] = 'm.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['customer_id'])) {
            $where[] = 'm.customer_id = ?';
            $params[] = (int) $filter['customer_id'];
        }
        if (!empty($filter['domain_id'])) {
            $where[] = 'm.domain_id = ?';
            $params[] = (string) $filter['domain_id'];
        }
        if (!empty($filter['sonderstatus'])) {
            $where[] = 'm.sonderstatus = ?';
            $params[] = $filter['sonderstatus'];
        }
        if (!empty($filter['vorgangstyp'])) {
            $where[] = 'm.vorgangstyp = ?';
            $params[] = $filter['vorgangstyp'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $orderMap = [
            'kunde_asc'            => 'LOWER(c.abbreviation) ASC',
            'kunde_desc'           => 'LOWER(c.abbreviation) DESC',
            'domain_asc'           => 'LOWER(d.url) ASC',
            'domain_desc'          => 'LOWER(d.url) DESC',
            'status_asc'           => "FIELD(m.status,'idee','akquise','bei_kunde','beauftragt','bei_anbieter','live','archiv') ASC, LOWER(d.url) ASC",
            'status_desc'          => "FIELD(m.status,'idee','akquise','bei_kunde','beauftragt','bei_anbieter','live','archiv') DESC, LOWER(d.url) ASC",
            'veroeffentlicht_asc'  => 'm.veroeffentlicht_am ASC',
            'veroeffentlicht_desc' => 'm.veroeffentlicht_am DESC, m.erstellt_am DESC',
        ];
        $orderBy = $orderMap[$filter['sort'] ?? 'veroeffentlicht_desc'] ?? 'm.erstellt_am DESC';

        $limit  = (int) ($filter['limit']  ?? 50);
        $offset = (int) ($filter['offset'] ?? 0);
        if ($limit < 1 || $limit > 500) $limit = 50;
        if ($offset < 0) $offset = 0;

        $total = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN customers c ON c.id = m.customer_id
             {$whereSql}",
            $params
        );

        $rows = $this->db->query(
            "SELECT m.id, m.status, m.vorgangstyp, m.buchungstyp, m.linktext,
                    m.brand_integration, m.geplant_am, m.veroeffentlicht_am,
                    m.veroeffentlichungs_url, m.sonderstatus, m.erstellt_am,
                    d.id AS domain_id, d.url AS domain_url,
                    c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name
             FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN customers c ON c.id = m.customer_id
             {$whereSql}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function getMassnahmeDetail(string $id): ?array
    {
        $m = $this->db->queryOne(
            "SELECT m.*,
                    d.id AS domain_id, d.url AS domain_url,
                    d.anbieter_id, a.name AS anbieter_name,
                    c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name,
                    u.name AS verantwortlicher_name,
                    lz.thema AS linkziel_thema, lz.url AS linkziel_url, lz.bevorzugter_linktext AS linkziel_linktext
             FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN lam_anbieter a ON a.id = d.anbieter_id
             LEFT JOIN customers c ON c.id = m.customer_id
             LEFT JOIN users u ON u.id = m.verantwortlich_user_id
             LEFT JOIN lam_linkziele lz ON lz.id = m.linkziel_id AND lz.geloescht_am IS NULL
             WHERE m.id = ? AND m.geloescht_am IS NULL",
            [$id]
        );
        if (!$m) return null;

        $m['auslage'] = $this->db->queryOne(
            "SELECT externe_kosten, weiterverrechnet, marge, marge_grund,
                    thoxan_rechnung_nr, thoxan_rechnung_datum, rechnung_eingang,
                    sonderfall, abgerechnet_fuer
             FROM lam_auslagen
             WHERE massnahme_id = ? AND geloescht_am IS NULL",
            [$id]
        );
        $m['monitoring'] = $this->db->query(
            "SELECT id, zeitpunkt, http_status, link_vorhanden, link_typ,
                    alert_ausgeloest, fehlermeldung
             FROM lam_monitoring_checks
             WHERE massnahme_id = ?
             ORDER BY zeitpunkt DESC
             LIMIT 10",
            [$id]
        );
        $m['kommunikation'] = $this->db->query(
            "SELECT k.id, k.typ, k.zeitpunkt, k.betreff, k.inhalt,
                    k.anhang_originalname, k.anhang_pfad,
                    ko.id AS kontakt_id, ko.nachname AS kontakt_nachname, ko.vorname AS kontakt_vorname
             FROM lam_kommunikation k
             LEFT JOIN lam_kontakte ko ON ko.id = k.kontakt_id
             WHERE k.massnahme_id = ? AND k.geloescht_am IS NULL
             ORDER BY k.zeitpunkt DESC
             LIMIT 20",
            [$id]
        );

        // Plan A (falls diese Maßnahme ein Plan B ist)
        $m['plan_a'] = null;
        if (!empty($m['plan_a_massnahme_id'])) {
            $m['plan_a'] = $this->db->queryOne(
                "SELECT m.id, m.status, m.veroeffentlichungs_url, d.url AS domain_url
                 FROM lam_massnahmen m
                 JOIN lam_domains d ON d.id = m.domain_id
                 WHERE m.id = ?",
                [$m['plan_a_massnahme_id']]
            );
        }
        return $m;
    }

    // ---------------------------------------------------------------------
    // Auslagen
    // ---------------------------------------------------------------------

    public function listeAuslagen(array $filter = []): array
    {
        $where  = ['a.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['sonderfall'])) {
            if ($filter['sonderfall'] === 'negative_marge') {
                $where[] = 'a.marge < 0';
            } else {
                $where[] = 'a.sonderfall = ?';
                $params[] = $filter['sonderfall'];
            }
        }
        if (!empty($filter['customer_id'])) {
            $where[] = 'm.customer_id = ?';
            $params[] = (int) $filter['customer_id'];
        }
        if (!empty($filter['jahr'])) {
            $where[] = 'YEAR(a.thoxan_rechnung_datum) = ?';
            $params[] = (int) $filter['jahr'];
        }
        if (!empty($filter['monat'])) {
            $where[] = 'MONTH(a.thoxan_rechnung_datum) = ?';
            $params[] = (int) $filter['monat'];
        }
        // Backward-Compat: quartal-Filter wird noch akzeptiert falls externer Code ihn schickt
        if (!empty($filter['quartal'])) {
            $where[] = 'QUARTER(a.thoxan_rechnung_datum) = ?';
            $params[] = (int) $filter['quartal'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "SELECT a.id, a.externe_kosten, a.weiterverrechnet, a.marge, a.marge_grund,
                    a.thoxan_rechnung_nr, a.thoxan_rechnung_datum, a.rechnung_eingang,
                    a.sonderfall, a.abgerechnet_fuer,
                    m.id AS massnahme_id, m.status AS massnahme_status,
                    d.url AS domain_url,
                    c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name
             FROM lam_auslagen a
             JOIN lam_massnahmen m ON m.id = a.massnahme_id
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN customers c ON c.id = m.customer_id
             {$whereSql}
             ORDER BY a.thoxan_rechnung_datum DESC, a.erstellt_am DESC
             LIMIT 500",
            $params
        );
    }

    // ---------------------------------------------------------------------
    // Monitoring (HTTP-Checks)
    // ---------------------------------------------------------------------

    public function listeMonitoring(array $filter = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filter['nur_alerts'])) {
            $where[] = 'mc.alert_ausgeloest = 1';
        }
        if (!empty($filter['customer_id'])) {
            $where[] = 'm.customer_id = ?';
            $params[] = (int) $filter['customer_id'];
        }
        if (!empty($filter['nur_unmuted'])) {
            $where[] = 'm.monitoring_muted = 0';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "SELECT mc.id, mc.zeitpunkt, mc.http_status, mc.link_vorhanden,
                    mc.link_typ, mc.alert_ausgeloest, mc.fehlermeldung,
                    m.id AS massnahme_id, m.status AS massnahme_status,
                    m.veroeffentlichungs_url, m.monitoring_muted,
                    d.url AS domain_url,
                    c.abbreviation AS customer_kuerzel, c.name AS customer_name
             FROM lam_monitoring_checks mc
             JOIN lam_massnahmen m ON m.id = mc.massnahme_id
             JOIN lam_domains d ON d.id = m.domain_id
             LEFT JOIN customers c ON c.id = m.customer_id
             {$whereSql}
             ORDER BY mc.zeitpunkt DESC
             LIMIT 500",
            $params
        );
    }

    // ---------------------------------------------------------------------
    // Korrespondenz
    // ---------------------------------------------------------------------

    public function listeKorrespondenz(array $filter = []): array
    {
        $where  = ['k.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(k.inhalt LIKE ? OR k.betreff LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['typ'])) {
            $where[] = 'k.typ = ?';
            $params[] = $filter['typ'];
        }
        if (!empty($filter['anbieter_id'])) {
            $where[] = 'k.anbieter_id = ?';
            $params[] = $filter['anbieter_id'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "SELECT k.id, k.typ, k.zeitpunkt, k.inhalt, k.betreff,
                    k.absender_mail, k.empfaenger_mail, k.status, k.mail_id_extern,
                    k.anhang_originalname, k.massnahme_id, k.vorschlagsliste_eintrag_id,
                    a.id AS anbieter_id, a.name AS anbieter_name,
                    ko.id AS kontakt_id, TRIM(CONCAT(COALESCE(ko.vorname, ''), ' ', COALESCE(ko.nachname, ''))) AS kontakt_name
             FROM lam_kommunikation k
             LEFT JOIN lam_anbieter a ON a.id = k.anbieter_id
             LEFT JOIN lam_kontakte ko ON ko.id = k.kontakt_id
             {$whereSql}
             ORDER BY k.zeitpunkt DESC
             LIMIT 500",
            $params
        );
    }

    // ---------------------------------------------------------------------
    // Excel-Exports (BKK/SMV-Layout + Quartals-Auslagen)
    // ---------------------------------------------------------------------

    /**
     * Erzeugt die Linkprofil-Excel im historischen BKK/SMV-Layout pro Kunde.
     * Speichert in $pfad und liefert Statistik.
     */
    /**
     * Linkprofil-Excel im historischen Thoxan-Layout (siehe
     * docs/lam-prototyp/FRY_Linkprofil-Analyse_*.xlsx):
     *   - 9 Datenspalten A..I: Projekt, Verlinkende URL, Domain, Wie oft?,
     *     Linktext, Linkart, Empfehlung, Bemerkung, Status
     *   - Spalte J leer (Trenner)
     *   - Statistik-Block K..L mit 3 Sektionen (Linkart, Empfehlung, Status)
     *   - Header grau (#D9D9D9) + bold, AutoFilter A1:I<last>, Freeze A2
     *   - Sheet-Name: „<KUERZEL> Linkprofil <YYYY-MM>"
     */
    public function exportiereLinkprofilExcel(int $customerId, string $pfad): array
    {
        // Composer-Autoload absichern (CLI-Pfad / Direkt-Aufruf)
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)
            && file_exists(ROOT_PATH . '/vendor/autoload.php')) {
            require_once ROOT_PATH . '/vendor/autoload.php';
        }

        $kunde = $this->db->queryOne(
            "SELECT name, abbreviation FROM customers WHERE id = ?",
            [$customerId]
        );
        $kuerzel = $kunde['abbreviation'] ?: ($kunde['name'] ?: ('K' . $customerId));

        // Labels fuer Linkart-Werte (DB → Anzeige)
        $linkartLabels = [
            'spam' => 'Spam', 'branchenverzeichnis' => 'Branchenverzeichnis',
            'fachverzeichnis' => 'Fachverzeichnis', 'online_magazin' => 'Online-Magazin',
            'portal' => 'Portal', 'blog' => 'Blog', 'presseportal' => 'Presseportal',
            'forum' => 'Forum', 'social_media' => 'Social Media',
            'referenzprojekt' => 'Referenzprojekt', 'partner' => 'Partner',
            'sponsoring' => 'Sponsoring', 'stellenboerse' => 'Stellenbörse',
            'veranstaltung' => 'Veranstaltung', 'kommentarlink' => 'Kommentarlink',
            'podcast' => 'Podcast', 'weiterleitung' => 'Weiterleitung',
            'sonstiges' => 'Sonstiges',
        ];
        $empfehlungLabels = [
            'lassen' => 'lassen', 'aendern' => 'ändern', 'loeschen' => 'löschen',
            'disavow' => 'disavow', 'geloescht' => 'gelöscht',
            'unsicher' => 'unsicher (klären)',
        ];
        $statusLabels = [
            'erledigt' => 'Erledigt', 'kunde' => 'Kunde',
            'offen' => 'Offen', 'thoxan' => 'Thoxan',
        ];

        $zeilen = $this->db->query(
            "SELECT v.domain, v.verlinkende_url, v.linktext, v.linkart, v.empfehlung,
                    v.ist_topp, v.bemerkung, v.status,
                    (SELECT COUNT(*) FROM lam_verlinkungen v2
                       WHERE v2.customer_id = v.customer_id AND v2.domain = v.domain
                         AND v2.geloescht_am IS NULL) AS haeufigkeit_domain
             FROM lam_verlinkungen v
             WHERE v.customer_id = ? AND v.geloescht_am IS NULL
             ORDER BY v.domain ASC, v.verlinkende_url ASC",
            [$customerId]
        );

        // --- Spreadsheet bauen ---
        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle(substr($kuerzel . ' Linkprofil ' . date('Y-m'), 0, 31));

        // Header
        $headers = ['Projekt', 'Verlinkende URL', 'Domain', 'Wie oft?', 'Linktext',
                    'Linkart', 'Empfehlung', 'Topp', 'Bemerkung', 'Status'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(ord('A') + $i) . '1', $h);
        }

        // Datenzeilen
        $linkartZaehler = array_fill_keys(array_values($linkartLabels), 0);
        $linkartZaehler['Ohne Linkart'] = 0;
        $empfehlungZaehler = array_fill_keys(array_values($empfehlungLabels), 0);
        $empfehlungZaehler['Unbearbeitet'] = 0;
        $empfehlungZaehler['Ohne Empfehlung'] = 0;
        $statusZaehler = array_fill_keys(array_values($statusLabels), 0);
        $statusZaehler['Ohne Status'] = 0;

        // Werte die im Statistik-Block rot bzw. gruen erscheinen (FRYKA-Style)
        $rotLabels  = ['Spam', 'Unbearbeitet', 'disavow', 'löschen', 'unsicher (klären)', 'ändern'];
        $gruenLabels = ['lassen'];

        $row = 2;
        foreach ($zeilen as $z) {
            $linkartLabel    = $z['linkart']    ? ($linkartLabels[$z['linkart']]    ?? $z['linkart'])    : '';
            $empfehlungLabel = $z['empfehlung'] ? ($empfehlungLabels[$z['empfehlung']] ?? $z['empfehlung']) : '';
            $statusLabel     = $z['status']     ? ($statusLabels[$z['status']]     ?? $z['status'])     : '';

            $sheet->setCellValue("A{$row}", $kuerzel);

            // URL als Text. Hyperlink nur fuer SAUBERE URLs — Sistrix-Backlinks
            // enthalten oft Leerzeichen, Anfuehrungszeichen, etc., die Excel
            // als „nicht-lesbar" markieren und das ganze Styling der Datei verwerfen.
            $url = (string)$z['verlinkende_url'];
            $sheet->setCellValueExplicit("B{$row}", $url, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // Konservativ: nur ASCII-URL ohne Whitespace, Quotes, spitze Klammern,
            // Steuerzeichen UND ohne unkodiertes "&" (Excel wird bei vielen Hyperlinks
            // mit &-Trennern nervoes — wir verkleinern damit das Risiko).
            $istValideUrl = preg_match('#^https?://#i', $url)
                && !preg_match('/[\s"<>\x00-\x1F&]/', $url)
                && strlen($url) <= 2000
                && filter_var($url, FILTER_VALIDATE_URL) !== false;
            if ($istValideUrl) {
                $sheet->getCell("B{$row}")->getHyperlink()->setUrl($url);
                $sheet->getStyle("B{$row}")->getFont()
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000FF'))
                    ->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
            }

            $sheet->setCellValue("C{$row}", $z['domain']);
            $sheet->setCellValue("D{$row}", (int)$z['haeufigkeit_domain']);
            $sheet->setCellValue("E{$row}", $z['linktext'] ?? '');
            $sheet->setCellValue("F{$row}", $linkartLabel);
            $sheet->setCellValue("G{$row}", $empfehlungLabel);
            $sheet->setCellValue("H{$row}", ((int)($z['ist_topp'] ?? 0) === 1) ? 'Topp' : '');
            $sheet->setCellValue("I{$row}", $z['bemerkung'] ?? '');
            $sheet->setCellValue("J{$row}", $statusLabel);
            // Topp-Zelle hervorheben (amber, fett) wie der Stern im Tool
            if ((int)($z['ist_topp'] ?? 0) === 1) {
                $sheet->getStyle("H{$row}")->getFont()->setBold(true)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFB45309'));
            }

            $linkartZaehler[$linkartLabel ?: 'Ohne Linkart']++;
            $empfehlungZaehler[$empfehlungLabel ?: 'Ohne Empfehlung']++;
            $statusZaehler[$statusLabel ?: 'Ohne Status']++;

            $row++;
        }
        $letzteDatenZeile = $row - 1;
        $gesamt = count($zeilen);

        /* === Statistik-Block K..L — FRYKA-Style ===
           Zellen-Fuellung: hellblau #DBE5F2 mit thin border-top.
           Kategorie-Header (L3/L26/L38): weiss + bold, ohne border.
           Summen-Zellen (KxRow): weiss + bold + border-top.
           Rote Labels fuer kritische Werte (Spam/disavow/loeschen/unsicher/ändern),
           gruen fuer „lassen". */
        $bgBlue = 'DBE5F2';

        $sheet->setCellValue('L1', 'Auswertung & Statistik');
        $sheet->getStyle('L1')->getFont()->setBold(true)->setSize(11);

        $schreibeBlock = function (array $zaehler, string $headerLabel, int $startRow) use ($sheet, $gesamt, $bgBlue, $rotLabels, $gruenLabels): array {
            // Header (z.B. „Linkart") in Spalte M, weiss + bold — NICHT im Rahmen
            $sheet->setCellValue("M{$startRow}", $headerLabel);
            $sheet->getStyle("M{$startRow}")->getFont()->setBold(true);

            $werteStartRow = $startRow + 1;
            $row = $werteStartRow;
            foreach ($zaehler as $label => $n) {
                $sheet->setCellValue("L{$row}", $n);
                $sheet->setCellValue("M{$row}", $label);
                // N: Prozent relativ zur Gesamtzahl, als echter Excel-Prozentwert mit 2 Nachkommastellen
                $anteil = $gesamt > 0 ? ($n / $gesamt) : 0;
                $sheet->setCellValue("N{$row}", $anteil);
                $sheet->getStyle("N{$row}")->getNumberFormat()
                    ->setFormatCode('0.00%');

                $alleDrei = $sheet->getStyle("L{$row}:N{$row}");
                $alleDrei->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($bgBlue);
                if (in_array($label, $rotLabels, true)) {
                    $alleDrei->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF0000'));
                } elseif (in_array($label, $gruenLabels, true)) {
                    $alleDrei->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF00B050'));
                }
                $row++;
            }
            $werteEndeRow = $row - 1;

            // Grid (alle Borders pro Zelle) NUR im Werte-Block (ohne Header, ohne Summe)
            $sheet->getStyle("L{$werteStartRow}:N{$werteEndeRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF000000'));

            // Summen-Zelle (nur L, M+N bleiben leer) — ausserhalb des Rahmens, bold
            $sheet->setCellValue("L{$row}", $gesamt);
            $sheet->getStyle("L{$row}")->getFont()->setBold(true);

            return ['next' => $row + 3, 'last' => $row]; // 3 Leerzeilen Abstand zum naechsten Block
        };

        $b1 = $schreibeBlock($linkartZaehler,    'Linkart',    3);
        $b2 = $schreibeBlock($empfehlungZaehler, 'Empfehlung', $b1['next']);
        $b3 = $schreibeBlock($statusZaehler,     'Status',     $b2['next']);

        // --- Header-Styling A1:J1 — grau + bold (passend zum FRYKA-Original) ---
        $headerRange = 'A1:J1';
        $headerStyle = $sheet->getStyle($headerRange);
        $headerStyle->getFont()->setBold(true)->setSize(10);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        $headerStyle->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setIndent(1);
        $headerStyle->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF000000'));

        // --- Datenzeilen A2:J<last> — Grid + Padding (FRYKA-Style) ---
        if ($letzteDatenZeile >= 2) {
            $datenRange = "A2:J{$letzteDatenZeile}";
            $datenStyle = $sheet->getStyle($datenRange);
            $datenStyle->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                ->setIndent(1);
            $datenStyle->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF000000'));
        }

        // Zeilenhoehe 23 fuer Header + alle Datenzeilen (FRYKA-Original)
        $sheet->getRowDimension(1)->setRowHeight(23);
        for ($r = 2; $r <= $letzteDatenZeile; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(23);
        }

        // Globale Font: Arial 10pt (Thoxan-Standard, passend zur FRYKA-Vorlage).
        // setDefaultStyle() reicht nicht — meine Cell-Styles (Header, Daten, Statistik)
        // wuerden ihn ueberschreiben. Daher zusaetzlich explizit auf den benutzten Bereich.
        $sp->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getStyle("A1:N" . max($letzteDatenZeile, $b3['last']))
            ->getFont()->setName('Arial')->setSize(10);

        // Statistik-Spalten L/M/N: vertikal mittig.
        // M (Labels) linksbuendig mit Indent 1.
        // L (Zahlen) + N (Prozent) rechtsbuendig mit Indent 1.
        // L1 (Ueberschrift „Auswertung & Statistik") ist Sonderfall: linksbuendig.
        $sheet->getStyle("M1:M" . $b3['last'])->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setIndent(1);
        $sheet->getStyle("L1:L" . $b3['last'])->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
            ->setIndent(1);
        $sheet->getStyle("N1:N" . $b3['last'])->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
            ->setIndent(1);
        $sheet->getStyle("L1")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setIndent(0);

        // Spaltenbreiten (laut FRY-Beispiel)
        $widths = ['A' => 11, 'B' => 47, 'C' => 31, 'D' => 12, 'E' => 19,
                   'F' => 19, 'G' => 19, 'H' => 9, 'I' => 40, 'J' => 19,
                   'K' => 5, 'L' => 11, 'M' => 21, 'N' => 11];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // AutoFilter + Freeze Pane
        if ($letzteDatenZeile >= 2) {
            $sheet->setAutoFilter('A1:J' . $letzteDatenZeile);
        }
        $sheet->freezePane('A2');

        // Schreiben
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp);
        $writer->save($pfad);

        return [
            'kunde'  => $kunde['name'] ?? '',
            'gesamt' => $gesamt,
            'datei'  => basename($pfad),
        ];
    }

    /**
     * Quartals-Auslagen-Excel pro Kunde. Sonderfälle in eigener Spalte.
     */
    public function exportiereQuartalsAuslagenExcel(int $customerId, int $jahr, ?int $quartal, string $pfad): array
    {
        require_once SERVICES_PATH . '/XlsxWriter.php';

        $kunde = $this->db->queryOne(
            "SELECT name, abbreviation FROM customers WHERE id = ?",
            [$customerId]
        );

        $where = ['m.customer_id = ?', 'a.geloescht_am IS NULL', 'YEAR(a.thoxan_rechnung_datum) = ?'];
        $params = [$customerId, $jahr];
        if ($quartal !== null) {
            $where[] = 'QUARTER(a.thoxan_rechnung_datum) = ?';
            $params[] = $quartal;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $header = [
            'Datum (Rechnung)', 'Rechnung-Nr', 'Domain', 'Externe Kosten',
            'Weiterverrechnet', 'Marge', 'Sonderfall', 'Marge-Grund',
        ];
        $zeilen = $this->db->query(
            "SELECT a.thoxan_rechnung_datum, a.thoxan_rechnung_nr,
                    d.url AS domain, a.externe_kosten, a.weiterverrechnet, a.marge,
                    a.sonderfall, a.marge_grund
             FROM lam_auslagen a
             JOIN lam_massnahmen m ON m.id = a.massnahme_id
             JOIN lam_domains d ON d.id = m.domain_id
             {$whereSql}
             ORDER BY a.thoxan_rechnung_datum ASC",
            $params
        );

        $datenZeilen = [];
        $summeExtern = 0; $summeWeiterv = 0; $summeMarge = 0;
        foreach ($zeilen as $z) {
            $datenZeilen[] = [
                $z['thoxan_rechnung_datum'] ?? '',
                $z['thoxan_rechnung_nr'] ?? '',
                $z['domain'],
                number_format((float)($z['externe_kosten'] ?? 0), 2, ',', '.'),
                number_format((float)($z['weiterverrechnet'] ?? 0), 2, ',', '.'),
                number_format((float)($z['marge'] ?? 0), 2, ',', '.'),
                $z['sonderfall'] ?? 'normal',
                $z['marge_grund'] ?? '',
            ];
            $summeExtern += (float)($z['externe_kosten'] ?? 0);
            $summeWeiterv += (float)($z['weiterverrechnet'] ?? 0);
            $summeMarge += (float)($z['marge'] ?? 0);
        }

        $statistik = [
            ['label' => 'Zeitraum', 'wert' => $jahr . ($quartal ? ' Q' . $quartal : '')],
            ['label' => 'Anzahl Posten', 'wert' => count($zeilen)],
            ['label' => 'Σ Externe Kosten', 'wert' => number_format($summeExtern, 2, ',', '.') . ' €'],
            ['label' => 'Σ Weiterverrechnet', 'wert' => number_format($summeWeiterv, 2, ',', '.') . ' €'],
            ['label' => 'Σ Marge', 'wert' => number_format($summeMarge, 2, ',', '.') . ' €'],
        ];

        XlsxWriter::schreibe($pfad, $header, $datenZeilen, $statistik);
        return [
            'kunde' => $kunde['name'] ?? '',
            'gesamt' => count($zeilen),
            'datei' => basename($pfad),
        ];
    }

    // ---------------------------------------------------------------------
    // Aufgaben-System (Update-Aufgaben aus „Veraltet markieren" etc.)
    // ---------------------------------------------------------------------

    public function legeAufgabeAn(string $typ, string $bezugTyp, string $bezugId, string $titel,
                                  ?string $beschreibung = null, ?string $faelligAm = null,
                                  ?int $zustaendigUserId = null, ?int $erstelltVon = null): string
    {
        $id = $this->ulid();
        $this->db->execute(
            "INSERT INTO lam_aufgaben (id, typ, bezug_typ, bezug_id, titel, beschreibung,
                                       faellig_am, zustaendig_user_id, status, erstellt_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'offen', ?)",
            [$id, $typ, $bezugTyp, $bezugId, $titel, $beschreibung,
             $faelligAm, $zustaendigUserId, $erstelltVon]
        );
        return $id;
    }

    public function listeAufgaben(array $filter = []): array
    {
        $where = ['a.geloescht_am IS NULL'];
        $params = [];
        if (!empty($filter['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['typ'])) {
            $where[] = 'a.typ = ?';
            $params[] = $filter['typ'];
        }
        if (!empty($filter['bezug_typ'])) {
            $where[] = 'a.bezug_typ = ?';
            $params[] = $filter['bezug_typ'];
        }
        if (!empty($filter['bezug_id'])) {
            $where[] = 'a.bezug_id = ?';
            $params[] = $filter['bezug_id'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        return $this->db->query(
            "SELECT a.id, a.typ, a.bezug_typ, a.bezug_id, a.titel, a.beschreibung,
                    a.faellig_am, a.zustaendig_user_id, a.status, a.erledigt_am, a.erstellt_am,
                    u.name AS zustaendig_name
             FROM lam_aufgaben a
             LEFT JOIN users u ON u.id = a.zustaendig_user_id
             {$whereSql}
             ORDER BY a.status = 'offen' DESC, a.faellig_am ASC, a.erstellt_am DESC
             LIMIT 500",
            $params
        );
    }

    public function aktualisiereAufgabe(string $id, array $daten): void
    {
        $felder = [];
        $params = [];
        foreach (['titel', 'beschreibung', 'status', 'faellig_am', 'zustaendig_user_id'] as $f) {
            if (array_key_exists($f, $daten)) {
                $felder[] = "`{$f}` = ?";
                $params[] = ($daten[$f] === '' ? null : $daten[$f]);
            }
        }
        if (!empty($daten['status']) && $daten['status'] === 'erledigt') {
            $felder[] = "erledigt_am = NOW()";
        }
        if (empty($felder)) return;
        $params[] = $id;
        $this->db->execute(
            "UPDATE lam_aufgaben SET " . implode(', ', $felder) . " WHERE id = ?",
            $params
        );
    }

    public function loescheAufgabe(string $id): void
    {
        $this->db->execute(
            "UPDATE lam_aufgaben SET geloescht_am = NOW() WHERE id = ?",
            [$id]
        );
    }

    // ---------------------------------------------------------------------
    // Verifikations-Workflow für Kontakte + Konditionen
    // ---------------------------------------------------------------------

    public function aktualisiereKontaktVerifikation(string $kontaktId, string $neuerStatus, ?int $userId = null): void
    {
        if (!in_array($neuerStatus, self::VERIFIKATION_STATUS, true)) {
            throw new \InvalidArgumentException('Ungültiger Verifikations-Status.');
        }
        $this->db->execute(
            "UPDATE lam_kontakte
             SET verifikation_status = ?,
                 verifiziert_am = CASE WHEN ? IN ('verifiziert','veraltet','verworfen') THEN NOW() ELSE verifiziert_am END,
                 verifiziert_von_user_id = CASE WHEN ? IN ('verifiziert','veraltet','verworfen') THEN ? ELSE verifiziert_von_user_id END
             WHERE id = ?",
            [$neuerStatus, $neuerStatus, $neuerStatus, $userId, $kontaktId]
        );
        $this->audit('kontakt.verifikation', 'kontakt', $kontaktId, ['status' => $neuerStatus]);
    }

    public function aktualisiereKonditionVerifikation(string $konditionId, string $neuerStatus, ?int $userId = null): void
    {
        if (!in_array($neuerStatus, self::VERIFIKATION_STATUS, true)) {
            throw new \InvalidArgumentException('Ungültiger Verifikations-Status.');
        }
        $this->db->execute(
            "UPDATE lam_konditionen
             SET verifikation_status = ?,
                 verifiziert_am = CASE WHEN ? IN ('verifiziert','veraltet','verworfen') THEN NOW() ELSE verifiziert_am END,
                 verifiziert_von_user_id = CASE WHEN ? IN ('verifiziert','veraltet','verworfen') THEN ? ELSE verifiziert_von_user_id END
             WHERE id = ?",
            [$neuerStatus, $neuerStatus, $neuerStatus, $userId, $konditionId]
        );
        $this->audit('kondition.verifikation', 'kondition', $konditionId, ['status' => $neuerStatus]);
    }

    // ---------------------------------------------------------------------
    // Tags-Stammdaten
    // ---------------------------------------------------------------------

    /**
     * Tags-Liste mit Verwendungszahl (live aus lam_domain_tag berechnet,
     * NICHT aus verwendungs_zahl-Spalte, da die schnell veraltet).
     */
    public function listeTags(array $filter = []): array
    {
        $where = ['t.geloescht_am IS NULL'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR t.beschreibung LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['nur_unbenutzt'])) {
            $where[] = '(SELECT COUNT(*) FROM lam_domain_tag dt WHERE dt.tag_id = t.id) = 0';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return $this->db->query(
            "SELECT t.id, t.slug, t.name, t.beschreibung, t.erstellt_am,
                    (SELECT COUNT(*) FROM lam_domain_tag dt WHERE dt.tag_id = t.id) AS verwendungs_zahl
             FROM lam_tags t
             {$whereSql}
             ORDER BY t.name ASC",
            $params
        );
    }

    /**
     * Tag anlegen oder bearbeiten. Slug wird ggf. automatisch generiert.
     */
    public function speichereTag(array $daten): array
    {
        $name = trim((string)($daten['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Name erforderlich.');

        $slug = trim((string)($daten['slug'] ?? ''));
        if ($slug === '') $slug = $this->slugify($name);

        $beschreibung = trim((string)($daten['beschreibung'] ?? ''));
        $beschreibung = $beschreibung === '' ? null : $beschreibung;

        $id = (int)($daten['id'] ?? 0);

        // Slug-Konflikt prüfen
        $konflikt = $this->db->queryValue(
            "SELECT id FROM lam_tags WHERE slug = ? AND id <> ? AND geloescht_am IS NULL",
            [$slug, $id]
        );
        if ($konflikt) throw new \InvalidArgumentException('Slug "' . $slug . '" wird bereits verwendet.');

        if ($id > 0) {
            $this->db->execute(
                "UPDATE lam_tags SET name = ?, slug = ?, beschreibung = ? WHERE id = ?",
                [$name, $slug, $beschreibung, $id]
            );
            return ['id' => $id, 'aktion' => 'aktualisiert'];
        }
        $newId = $this->db->insert('lam_tags', [
            'slug' => $slug,
            'name' => $name,
            'beschreibung' => $beschreibung,
        ]);
        return ['id' => $newId, 'aktion' => 'erstellt'];
    }

    public function loescheTag(int $id): array
    {
        if ($id <= 0) throw new \InvalidArgumentException('Tag-ID erforderlich.');
        $this->db->execute(
            "UPDATE lam_tags SET geloescht_am = NOW() WHERE id = ?",
            [$id]
        );
        $this->db->execute("DELETE FROM lam_domain_tag WHERE tag_id = ?", [$id]);
        return ['id' => $id, 'aktion' => 'geloescht'];
    }

    /**
     * Zwei Tags zusammenfuehren: alle Domain-Zuweisungen vom source-Tag
     * zum target-Tag uebernehmen, source-Tag dann soft-loeschen.
     */
    public function mergeTag(int $sourceId, int $targetId): array
    {
        if ($sourceId <= 0 || $targetId <= 0) throw new \InvalidArgumentException('Beide Tag-IDs erforderlich.');
        if ($sourceId === $targetId) throw new \InvalidArgumentException('Source und Target identisch.');

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "INSERT IGNORE INTO lam_domain_tag (domain_id, tag_id, primaer)
                 SELECT domain_id, ?, primaer
                 FROM lam_domain_tag WHERE tag_id = ?",
                [$targetId, $sourceId]
            );
            $this->db->execute("DELETE FROM lam_domain_tag WHERE tag_id = ?", [$sourceId]);
            $this->db->execute(
                "UPDATE lam_tags SET geloescht_am = NOW() WHERE id = ?",
                [$sourceId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return ['source_id' => $sourceId, 'target_id' => $targetId, 'aktion' => 'merged'];
    }

    /**
     * Tags einer Domain in einem Rutsch setzen (replace-Semantik).
     * Erwartet Array von tag_ids (ints), leeres Array = alle entfernen.
     */
    public function setzeDomainTags(string $domainId, array $tagIds): array
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        $tagIds = array_filter($tagIds, fn($v) => $v > 0);

        $this->db->beginTransaction();
        try {
            $this->db->execute("DELETE FROM lam_domain_tag WHERE domain_id = ?", [$domainId]);
            foreach ($tagIds as $tagId) {
                $this->db->execute(
                    "INSERT IGNORE INTO lam_domain_tag (domain_id, tag_id, primaer) VALUES (?, ?, 0)",
                    [$domainId, $tagId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return ['domain_id' => $domainId, 'tag_count' => count($tagIds)];
    }

    /**
     * Tag-ID zu einem Namen holen — legt den Tag bei Bedarf an.
     *
     * lam_tags.slug ist NOT NULL und UNIQUE. Frueher hat der Import den Slug schlicht
     * weggelassen ("Field 'slug' doesn't have a default value") und ist an jeder Zeile
     * mit Thema/Cluster gescheitert. Hier wird der Slug erzeugt und dabei sauber behandelt:
     *  - Treffer per Name -> nehmen
     *  - Slug schon vergeben (z.B. "Kälte/Klima" und "Kaelte Klima" ergeben denselben Slug,
     *    oder der Tag ist nur soft-geloescht) -> bestehenden Tag wiederverwenden bzw. reaktivieren
     *  - sonst neu anlegen
     */
    private function holeOderErstelleTagId(string $name): int
    {
        $name = trim($name);
        $treffer = $this->db->queryValue(
            "SELECT id FROM lam_tags WHERE name = ? AND geloescht_am IS NULL LIMIT 1",
            [$name]
        );
        if ($treffer) return (int) $treffer;

        $slug = $this->slugify($name);

        // Slug ist UNIQUE — auch soft-geloeschte Zeilen belegen ihn weiterhin.
        $bestehend = $this->db->queryOne("SELECT id, geloescht_am FROM lam_tags WHERE slug = ? LIMIT 1", [$slug]);
        if ($bestehend) {
            if (!empty($bestehend['geloescht_am'])) {
                $this->db->execute(
                    "UPDATE lam_tags SET geloescht_am = NULL, name = ? WHERE id = ?",
                    [$name, (int) $bestehend['id']]
                );
            }
            return (int) $bestehend['id'];
        }

        return (int) $this->db->insert('lam_tags', ['slug' => $slug, 'name' => $name]);
    }

    /** Projektuebergreifende Bewertungsstufen (Ergebnis der Recherche-Dateien). */
    public const BEWERTUNGEN = ['top', 'bedingt', 'ablehnen', 'offen'];
    public const BEWERTUNG_LABELS = [
        'top'      => 'TOP',
        'bedingt'  => 'Bedingt',
        'ablehnen' => 'Ablehnen',
        'offen'    => 'Offen',
    ];

    /**
     * Bewertung aus der Recherche-Datei auf feste Stufen bringen.
     *
     * Die Dateien nutzen unterschiedliche Vokabulare:
     *   Datei A: Prio  = TOP-10 | B | C
     *   Datei B: URTEIL = AUFNEHMEN | BEDINGT | ABLEHNEN | ANDERER ZWECK
     * Der Rohwert bleibt zusaetzlich in der Notiz erhalten — hier geht also nichts verloren,
     * es wird nur zusaetzlich eine gemeinsame, filterbare Stufe abgeleitet.
     */
    /**
     * Die uebrigen Recherche-Spalten als lesbaren Block fuer die Notiz.
     * Bewusst inkl. Roh-Urteil ("TOP-10", "AUFNEHMEN") — die normalisierte Stufe ist nur
     * die filterbare Ableitung, das Original bleibt nachvollziehbar erhalten.
     */
    private function bewertungsBlock(array $k): string
    {
        $t = [];
        if (!empty($k['bewertung_roh']))  $t[] = 'Urteil/Prio: ' . $k['bewertung_roh'];
        if (!empty($k['betreiber']))      $t[] = 'Betreiber: ' . $k['betreiber'];
        if (!empty($k['weg']))            $t[] = 'Veröffentlichungsweg: ' . $k['weg'];
        if (!empty($k['kosten']))         $t[] = 'Kosten: ' . $k['kosten'];
        if (!empty($k['linksituation']))  $t[] = 'Link-Situation: ' . $k['linksituation'];
        if (!empty($k['risiko']))         $t[] = 'Qualitätsrisiko: ' . $k['risiko'];
        return $t ? implode("\n", $t) : '';
    }

    public static function normalisiereBewertung(string $roh): string
    {
        $s = mb_strtolower(trim($roh));
        if ($s === '') return 'offen';
        // Klare Zusagen
        if (preg_match('/aufnehmen|^top|prio\s*a\b|^a$|^1$|^x$|^ja$/u', $s)) return 'top';
        // Klare Absagen (vor "bedingt" pruefen: "bedingt – vorsicht" darf nicht hier landen)
        if (preg_match('/ablehnen|nicht geeignet|kein fachbezug|^c$|^nein$/u', $s)) return 'ablehnen';
        // Mittelfeld
        if (preg_match('/bedingt|vorbehalt|^b$|^2$|pr(ü|ue)fen/u', $s)) return 'bedingt';
        // z.B. "ANDERER ZWECK (HR)" — kein Urteil fuer den Linkaufbau
        return 'offen';
    }

    /** Festes, projektuebergreifendes Linkart-Vokabular — bewusst KEINE neuen Kategorien. */
    public const LINKARTEN = [
        'blog', 'branchenverzeichnis', 'fachverzeichnis', 'forum', 'kommentarlink',
        'online_magazin', 'partner', 'podcast', 'portal', 'presseportal',
        'referenzprojekt', 'social_media', 'sonstiges', 'sponsoring',
        'stellenboerse', 'veranstaltung', 'weiterleitung',
    ];

    /**
     * Linkart per KI bestimmen — fuellt das BESTEHENDE Vokabular, legt keine neuen Kategorien an.
     * Damit wird die vorhandene Linkart-Filterleiste zum Subfilter, ohne die Tag-Taxonomie
     * (die projektuebergreifend gilt) mit Dutzenden Einmal-Kategorien zu verwaessern.
     *
     * Kein Crawl noetig: URL + Import-Notiz ("[Cluster: SHK-/TGA-Leitportal]") reichen zur
     * Einordnung — das haelt es schnell und guenstig. Ein Batch pro Aufruf.
     *
     * @param string[] $domainIds
     * @param bool $ueberschreiben Auch Domains mit bereits gesetzter Linkart neu einordnen
     */
    public function kiKlassifiziereLinkart(array $domainIds, bool $ueberschreiben = false): array
    {
        $domainIds = array_values(array_filter(array_map('strval', $domainIds)));
        if (empty($domainIds)) return ['gesetzt' => 0, 'uebersprungen' => 0, 'fehler' => []];

        $ph = implode(',', array_fill(0, count($domainIds), '?'));
        $rows = $this->db->query(
            "SELECT id, url, linkart, notizen FROM lam_domains
             WHERE id IN ($ph) AND geloescht_am IS NULL",
            $domainIds
        ) ?: [];

        $offen = [];
        $uebersprungen = 0;
        foreach ($rows as $r) {
            if (!$ueberschreiben && !empty($r['linkart'])) { $uebersprungen++; continue; }
            $besch = '';
            if (!empty($r['notizen']) && preg_match('/\[Cluster:\s*([^\]]+)\]/u', (string) $r['notizen'], $m)) {
                $besch = trim($m[1]);
            }
            $offen[] = ['id' => $r['id'], 'url' => $r['url'], 'beschreibung' => $besch];
        }
        if (empty($offen)) return ['gesetzt' => 0, 'uebersprungen' => $uebersprungen, 'fehler' => []];

        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key fehlt.');
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(2000);
        $ai->setTimeout(60);

        $vok = implode(', ', self::LINKARTEN);
        $system = "Du ordnest Websites für ein Linkaufbau-Tool genau EINER Linkart zu.\n"
            . "Erlaubte Werte (NUR diese, exakt so geschrieben): {$vok}\n"
            . "Bedeutung: branchenverzeichnis/fachverzeichnis = Eintragsverzeichnisse; "
            . "online_magazin = redaktionelles Fachmedium/Magazin; presseportal = Portal zum Einstellen von Pressemitteilungen; "
            . "portal = allgemeines Themenportal; stellenboerse = Jobbörse; veranstaltung = Messe/Event; "
            . "sonstiges nur, wenn wirklich nichts passt.\n"
            . "Antworte ausschließlich mit JSON: {\"zuordnungen\":[{\"id\":\"…\",\"linkart\":\"…\"}]}";

        $liste = '';
        foreach ($offen as $o) {
            $liste .= "- id: {$o['id']} | url: {$o['url']}"
                . ($o['beschreibung'] !== '' ? " | beschreibung: {$o['beschreibung']}" : '') . "\n";
        }
        $user = "Ordne jeder dieser Quellen genau eine Linkart zu:\n\n" . $liste;

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $text = (string) ($antwort['content'] ?? '');
        $json = json_decode($text, true);
        if (!$json && preg_match('/\{.*\}/s', $text, $m)) $json = json_decode($m[0], true);
        if (!is_array($json) || empty($json['zuordnungen'])) {
            throw new \RuntimeException('KI lieferte keine verwertbare Zuordnung.');
        }

        $gesetzt = 0; $fehler = [];
        $erlaubt = array_flip(self::LINKARTEN);
        $bekannt = array_flip(array_column($offen, 'id'));
        foreach ($json['zuordnungen'] as $z) {
            $id = trim((string) ($z['id'] ?? ''));
            $la = trim((string) ($z['linkart'] ?? ''));
            if ($id === '' || !isset($bekannt[$id])) continue;          // keine erfundenen IDs
            if (!isset($erlaubt[$la])) { $fehler[] = "$id: unbekannte Linkart „$la\""; continue; }
            $this->db->execute("UPDATE lam_domains SET linkart = ? WHERE id = ?", [$la, $id]);
            $gesetzt++;
        }
        return ['gesetzt' => $gesetzt, 'uebersprungen' => $uebersprungen, 'fehler' => $fehler];
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string)$value, '-');
        if ($value === '') $value = 'tag-' . substr((string)time(), -6);
        return substr($value, 0, 150);
    }

    // ---------------------------------------------------------------------
    // Linkziele-Stammdaten (eigene Sicht pro Kunde)
    // ---------------------------------------------------------------------

    public function listeLinkziele(array $filter = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filter['customer_id'])) {
            $where[] = 'lz.customer_id = ?';
            $params[] = $filter['customer_id'];
        }
        if (!empty($filter['status'])) {
            $where[] = 'lz.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['suche'])) {
            $where[] = '(lz.url LIKE ? OR lz.thema LIKE ? OR lz.bevorzugter_linktext LIKE ?)';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
            $params[] = '%' . $filter['suche'] . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->query(
            "SELECT lz.id, lz.customer_id, lz.url, lz.thema, lz.bevorzugter_linktext, lz.status,
                    lz.erstellt_am,
                    c.name AS customer_name, c.abbreviation AS customer_kuerzel
             FROM lam_linkziele lz
             LEFT JOIN customers c ON c.id = lz.customer_id
             {$whereSql}
             ORDER BY c.abbreviation ASC, lz.thema ASC, lz.url ASC
             LIMIT 1000",
            $params
        );
    }

    public function speichereLinkziel(array $daten): array
    {
        $customerId = trim((string)($daten['customer_id'] ?? ''));
        $url = trim((string)($daten['url'] ?? ''));
        $thema = trim((string)($daten['thema'] ?? ''));
        $bevorzugterLinktext = trim((string)($daten['bevorzugter_linktext'] ?? ''));
        $status = trim((string)($daten['status'] ?? 'aktiv'));

        if ($customerId === '') throw new \InvalidArgumentException('Kunde erforderlich.');
        if ($url === '') throw new \InvalidArgumentException('URL erforderlich.');
        if ($thema === '') throw new \InvalidArgumentException('Thema erforderlich.');

        $id = trim((string)($daten['id'] ?? ''));

        if ($id !== '') {
            $this->db->execute(
                "UPDATE lam_linkziele SET customer_id = ?, url = ?, thema = ?, bevorzugter_linktext = ?, status = ?
                 WHERE id = ?",
                [$customerId, $url, $thema, $bevorzugterLinktext, $status, $id]
            );
            return ['id' => $id, 'aktion' => 'aktualisiert'];
        }
        $newId = $this->ulid();
        $this->db->execute(
            "INSERT INTO lam_linkziele (id, customer_id, url, thema, bevorzugter_linktext, status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$newId, $customerId, $url, $thema, $bevorzugterLinktext, $status]
        );
        return ['id' => $newId, 'aktion' => 'erstellt'];
    }

    public function loescheLinkziel(string $id): array
    {
        if ($id === '') throw new \InvalidArgumentException('Linkziel-ID erforderlich.');
        $this->db->execute("DELETE FROM lam_linkziele WHERE id = ?", [$id]);
        return ['id' => $id, 'aktion' => 'geloescht'];
    }

    /**
     * Kurz-Liste der Linkziele eines Kunden — fuer Quick-Add im Maßnahmen-Drawer.
     */
    public function listeLinkzieleFuerKunde(string $customerId): array
    {
        return $this->db->query(
            "SELECT id, url, thema, bevorzugter_linktext
             FROM lam_linkziele
             WHERE customer_id = ? AND status = 'aktiv'
             ORDER BY thema ASC, url ASC",
            [$customerId]
        );
    }

    public function ulid(): string
    {
        $time = (int)(microtime(true) * 1000);
        $timeChars = '';
        $encoding = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        for ($i = 9; $i >= 0; $i--) {
            $mod = $time % 32;
            $timeChars = $encoding[$mod] . $timeChars;
            $time = (int)($time / 32);
        }
        $rand = '';
        for ($i = 0; $i < 16; $i++) {
            $rand .= $encoding[random_int(0, 31)];
        }
        return $timeChars . $rand;
    }

    // ---------------------------------------------------------------------
    // Domain-Wissen (manuell pflegbar)
    // ---------------------------------------------------------------------

    public function getDomainWissen(string $domain): ?array
    {
        return $this->db->queryOne(
            "SELECT id, domain, linkart, reduktionsstrategie, confidence, anzahl_klassifikationen,
                    letzter_customer_id, notiz, empfehlung_default,
                    branche, thema, tonalitaet, risikofaktoren, manuell_gepflegt, erstellt_am, aktualisiert_am
             FROM lam_domain_wissen WHERE domain = ?",
            [$domain]
        );
    }

    public function speichereDomainWissen(array $daten): array
    {
        $domain = trim((string)($daten['domain'] ?? ''));
        if ($domain === '') throw new \InvalidArgumentException('Domain erforderlich.');

        $felder = ['linkart', 'reduktionsstrategie', 'notiz', 'empfehlung_default',
                   'branche', 'thema', 'tonalitaet', 'risikofaktoren'];
        $werte = [];
        foreach ($felder as $f) {
            $v = isset($daten[$f]) ? trim((string)$daten[$f]) : null;
            $werte[$f] = ($v === '' ? null : $v);
        }

        $existiert = $this->db->queryValue(
            "SELECT id FROM lam_domain_wissen WHERE domain = ?",
            [$domain]
        );
        if ($existiert) {
            $this->db->execute(
                "UPDATE lam_domain_wissen
                 SET linkart = ?, reduktionsstrategie = ?, notiz = ?, empfehlung_default = ?,
                     branche = ?, thema = ?, tonalitaet = ?, risikofaktoren = ?,
                     manuell_gepflegt = 1, aktualisiert_am = NOW()
                 WHERE domain = ?",
                [
                    $werte['linkart'], $werte['reduktionsstrategie'], $werte['notiz'], $werte['empfehlung_default'],
                    $werte['branche'], $werte['thema'], $werte['tonalitaet'], $werte['risikofaktoren'],
                    $domain
                ]
            );
            return ['domain' => $domain, 'aktion' => 'aktualisiert'];
        }
        $this->db->execute(
            "INSERT INTO lam_domain_wissen (domain, linkart, reduktionsstrategie, notiz, empfehlung_default,
                                             branche, thema, tonalitaet, risikofaktoren, manuell_gepflegt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $domain, $werte['linkart'], $werte['reduktionsstrategie'], $werte['notiz'], $werte['empfehlung_default'],
                $werte['branche'], $werte['thema'], $werte['tonalitaet'], $werte['risikofaktoren']
            ]
        );
        return ['domain' => $domain, 'aktion' => 'erstellt'];
    }

    /**
     * Wendet das Domain-Wissen (linkart, empfehlung_default) auf alle Verlinkungen
     * dieser Domain an — kundenuebergreifend. Manuell gepflegte Wissens-Eintraege
     * haben Vorrang: $force=true ueberschreibt auch bestehende empfehlung-Werte.
     */
    public function wendeDomainWissenAn(string $domain, bool $force = false): array
    {
        $wissen = $this->db->queryOne(
            "SELECT linkart, empfehlung_default, manuell_gepflegt FROM lam_domain_wissen WHERE domain = ?",
            [$domain]
        );
        if (!$wissen) throw new \InvalidArgumentException('Kein Domain-Wissen vorhanden.');

        // Manuell gepflegte Eintraege ueberschreiben grundsaetzlich (Spec).
        $alwaysOverwrite = $force || (int)($wissen['manuell_gepflegt'] ?? 0) === 1;

        $linkartGesetzt = 0;
        $empfehlungGesetzt = 0;

        if (!empty($wissen['linkart'])) {
            $linkartGesetzt = $this->db->execute(
                "UPDATE lam_verlinkungen SET linkart = ?, aktualisiert_am = NOW()
                 WHERE domain = ? AND geloescht_am IS NULL",
                [$wissen['linkart'], $domain]
            );
        }
        if (!empty($wissen['empfehlung_default'])) {
            if ($alwaysOverwrite) {
                $empfehlungGesetzt = $this->db->execute(
                    "UPDATE lam_verlinkungen SET empfehlung = ?, aktualisiert_am = NOW()
                     WHERE domain = ? AND geloescht_am IS NULL",
                    [$wissen['empfehlung_default'], $domain]
                );
            } else {
                $empfehlungGesetzt = $this->db->execute(
                    "UPDATE lam_verlinkungen SET empfehlung = ?, aktualisiert_am = NOW()
                     WHERE domain = ? AND geloescht_am IS NULL
                       AND (empfehlung IS NULL OR empfehlung = '' OR empfehlung = 'unsicher')",
                    [$wissen['empfehlung_default'], $domain]
                );
            }
        }
        return [
            'domain' => $domain,
            'linkart_aktualisiert' => $linkartGesetzt,
            'empfehlung_aktualisiert' => $empfehlungGesetzt,
            'force' => $alwaysOverwrite,
        ];
    }

    /**
     * Listet alle Domain-Wissens-Eintraege mit Bestand (= aktuelle Verlinkungen)
     * und letztem Kunden. Filter: Suche, Linkart, Confidence, „nur Konflikte".
     *
     * Ein „Konflikt" ist hier definiert als:
     *   - Bestand > Anzahl Klassifikationen — Klassifikation noch nicht auf alle ausgerollt
     *   - ODER mehrfach klassifiziert (anzahl_klassifikationen > 1)
     */
    public function listeDomainWissen(array $filter = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filter['suche'])) {
            $where[] = 'w.domain LIKE ?';
            $params[] = '%' . $filter['suche'] . '%';
        }
        if (!empty($filter['linkart'])) {
            $liste = is_array($filter['linkart']) ? $filter['linkart'] : [$filter['linkart']];
            $platzhalter = implode(',', array_fill(0, count($liste), '?'));
            $where[] = "w.linkart IN ({$platzhalter})";
            foreach ($liste as $l) $params[] = $l;
        }
        if (!empty($filter['confidence'])) {
            $liste = is_array($filter['confidence']) ? $filter['confidence'] : [$filter['confidence']];
            $platzhalter = implode(',', array_fill(0, count($liste), '?'));
            $where[] = "w.confidence IN ({$platzhalter})";
            foreach ($liste as $c) $params[] = $c;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $limit  = isset($filter['limit'])  ? max(1, min(500, (int) $filter['limit']))  : 100;
        $offset = isset($filter['offset']) ? max(0, (int) $filter['offset']) : 0;

        $rows = $this->db->query(
            "SELECT w.id, w.domain, w.linkart, w.reduktionsstrategie, w.confidence,
                    w.anzahl_klassifikationen, w.notiz, w.empfehlung_default,
                    w.manuell_gepflegt, w.aktualisiert_am, w.erstellt_am,
                    c.name AS letzter_customer_name, c.abbreviation AS letzter_customer_kuerzel,
                    -- 'Bestand': Anzahl aktuelle Verlinkungen mit dieser Domain (kundenuebergreifend)
                    (SELECT COUNT(*) FROM lam_verlinkungen v
                       WHERE v.domain = w.domain AND v.geloescht_am IS NULL) AS bestand
             FROM lam_domain_wissen w
             LEFT JOIN customers c ON c.id = w.letzter_customer_id
             {$whereSql}
             ORDER BY anzahl_klassifikationen DESC, bestand DESC, w.domain ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        // Konflikt-Filter clientseitig (HAVING ginge nicht ohne Bestand zu re-evaluieren)
        if (!empty($filter['nur_konflikte'])) {
            $rows = array_values(array_filter($rows, function ($r) {
                $bestand = (int)$r['bestand'];
                $klass   = (int)$r['anzahl_klassifikationen'];
                return ($bestand > $klass) || ($klass > 1);
            }));
        }

        $gesamt = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM lam_domain_wissen w {$whereSql}",
            $params
        );

        return ['rows' => $rows, 'gesamt' => $gesamt];
    }

    /**
     * Loescht einen Domain-Wissens-Eintrag (ohne die Verlinkungen anzufassen).
     */
    public function loescheDomainWissen(int $id): bool
    {
        return $this->db->execute('DELETE FROM lam_domain_wissen WHERE id = ?', [$id]) > 0;
    }

    // ---------------------------------------------------------------------
    // Linkprofil-Snapshots + Diff
    // ---------------------------------------------------------------------

    /**
     * Beim CSV-Import: legt einen Snapshot an und berechnet Diff zum vorigen.
     * Returnt {snapshot_id, neu_count, verschwunden_count, neu_list, verschwunden_list}.
     */
    public function erzeugeLinkprofilSnapshot(string $customerId, ?int $importId = null): array
    {
        $snapshotId = $this->ulid();

        $anzahl = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM lam_verlinkungen WHERE customer_id = ? AND geloescht_am IS NULL",
            [$customerId]
        );

        // Aktuelle Linkprofil-Eintraege als Snapshot festhalten
        $this->db->execute(
            "INSERT INTO lam_linkprofil_snapshots (id, customer_id, import_id, snapshot_datum, anzahl_verlinkungen, erstellt_am)
             VALUES (?, ?, ?, CURDATE(), ?, NOW())",
            [$snapshotId, $customerId, $importId, $anzahl]
        );

        // Eintraege festschreiben (Quell-URL + Ziel-URL)
        $this->db->execute(
            "INSERT INTO lam_linkprofil_snapshot_verlinkungen
                (snapshot_id, verlinkung_id, linkart_at_snapshot, status_at_snapshot, quell_url, ziel_url)
             SELECT ?, v.id, v.linkart, v.status, v.quell_url, v.ziel_url
             FROM lam_verlinkungen v
             WHERE v.customer_id = ? AND v.geloescht_am IS NULL",
            [$snapshotId, $customerId]
        );

        // Vorigen Snapshot finden
        $prev = $this->db->queryValue(
            "SELECT id FROM lam_linkprofil_snapshots
             WHERE customer_id = ? AND id <> ?
             ORDER BY erstellt_am DESC LIMIT 1",
            [$customerId, $snapshotId]
        );

        $neuCount = 0;
        $weggCount = 0;
        $neuList = [];
        $weggList = [];

        if ($prev) {
            $neuList = $this->db->query(
                "SELECT cur.quell_url, cur.ziel_url, cur.linkart
                 FROM lam_linkprofil_snapshot_verlinkungen cur
                 LEFT JOIN lam_linkprofil_snapshot_verlinkungen prev
                   ON prev.snapshot_id = ? AND prev.quell_url = cur.quell_url AND prev.ziel_url = cur.ziel_url
                 WHERE cur.snapshot_id = ? AND prev.snapshot_id IS NULL
                 LIMIT 500",
                [$prev, $snapshotId]
            );
            $weggList = $this->db->query(
                "SELECT prev.quell_url, prev.ziel_url, prev.linkart
                 FROM lam_linkprofil_snapshot_verlinkungen prev
                 LEFT JOIN lam_linkprofil_snapshot_verlinkungen cur
                   ON cur.snapshot_id = ? AND cur.quell_url = prev.quell_url AND cur.ziel_url = prev.ziel_url
                 WHERE prev.snapshot_id = ? AND cur.snapshot_id IS NULL
                 LIMIT 500",
                [$snapshotId, $prev]
            );
            $neuCount = count($neuList);
            $weggCount = count($weggList);

            $this->db->execute(
                "UPDATE lam_linkprofil_snapshots
                 SET diff_neu_count = ?, diff_weggefallen_count = ?, vorgaenger_id = ?
                 WHERE id = ?",
                [$neuCount, $weggCount, $prev, $snapshotId]
            );
        }

        return [
            'snapshot_id' => $snapshotId,
            'neu_count' => $neuCount,
            'verschwunden_count' => $weggCount,
            'neu_list' => $neuList,
            'verschwunden_list' => $weggList,
        ];
    }

    public function listeSnapshots(string $customerId): array
    {
        return $this->db->query(
            "SELECT id, customer_id, import_id, snapshot_datum, erstellt_am,
                    anzahl_verlinkungen AS eintraege_count,
                    diff_neu_count, diff_weggefallen_count, vorgaenger_id, notiz
             FROM lam_linkprofil_snapshots
             WHERE customer_id = ?
             ORDER BY erstellt_am DESC LIMIT 50",
            [$customerId]
        );
    }

    /**
     * Statistik fuer die Linkprofil-Statistik-Seite eines Kunden.
     * - top_domains: Domains mit den meisten Verlinkungen
     * - linkart_verteilung: wie viele Verlinkungen pro Linkart
     * - follow_anteil: f vs. nf vs. unknown
     * - empfehlungs_verteilung: pro Empfehlungswert
     */
    public function getLinkprofilStatistik(int $customerId): array
    {
        $topDomains = $this->db->query(
            "SELECT domain, COUNT(*) AS anzahl,
                    SUM(CASE WHEN is_follow = 1 THEN 1 ELSE 0 END) AS follow_anzahl
             FROM lam_verlinkungen
             WHERE customer_id = ? AND geloescht_am IS NULL
             GROUP BY domain
             ORDER BY anzahl DESC, domain ASC
             LIMIT 30",
            [$customerId]
        );
        $linkartVerteilung = $this->db->query(
            "SELECT COALESCE(NULLIF(linkart, ''), '(leer)') AS linkart, COUNT(*) AS anzahl
             FROM lam_verlinkungen
             WHERE customer_id = ? AND geloescht_am IS NULL
             GROUP BY linkart
             ORDER BY anzahl DESC",
            [$customerId]
        );
        $followAnteil = $this->db->queryOne(
            "SELECT
                SUM(CASE WHEN is_follow = 1 THEN 1 ELSE 0 END) AS follow,
                SUM(CASE WHEN is_follow = 0 THEN 1 ELSE 0 END) AS nofollow,
                SUM(CASE WHEN is_follow IS NULL THEN 1 ELSE 0 END) AS unbekannt,
                COUNT(*) AS gesamt
             FROM lam_verlinkungen
             WHERE customer_id = ? AND geloescht_am IS NULL",
            [$customerId]
        );
        $empfehlungsVerteilung = $this->db->query(
            "SELECT COALESCE(NULLIF(empfehlung, ''), '(offen)') AS empfehlung, COUNT(*) AS anzahl
             FROM lam_verlinkungen
             WHERE customer_id = ? AND geloescht_am IS NULL
             GROUP BY empfehlung
             ORDER BY anzahl DESC",
            [$customerId]
        );
        return [
            'top_domains' => $topDomains,
            'linkart_verteilung' => $linkartVerteilung,
            'follow_anteil' => $followAnteil,
            'empfehlungs_verteilung' => $empfehlungsVerteilung,
        ];
    }

    // ---------------------------------------------------------------------
    // KI-Klassifikation Linkprofil
    // ---------------------------------------------------------------------

    /**
     * Klassifiziert eine einzelne Verlinkung via Claude Haiku.
     * Wenn $mitCrawl=true, wird die Quellseite gecrawlt (erste ~5KB Haupttext),
     * sonst nur URL+Linktext+Ziel-URL betrachtet (schnell, günstig).
     */
    public function klassifiziereVerlinkung(string $verlinkungId, bool $mitCrawl = false): array
    {
        $v = $this->db->queryOne(
            "SELECT id, verlinkende_url, domain, linktext, ziel_url, linkart, empfehlung
             FROM lam_verlinkungen WHERE id = ? AND geloescht_am IS NULL",
            [$verlinkungId]
        );
        if (!$v) throw new \InvalidArgumentException('Verlinkung nicht gefunden.');

        $kontext = $mitCrawl ? $this->crawleSeitenAusschnitt($v['verlinkende_url']) : '';
        $ergebnis = $this->fragKiNachKlassifikation($v, $kontext);

        // KI-Vorschlag in Datenbank schreiben (linkart + empfehlung), Confidence in ki_meta-Spalte
        $this->db->execute(
            "UPDATE lam_verlinkungen
             SET linkart = COALESCE(NULLIF(?, ''), linkart),
                 empfehlung = COALESCE(NULLIF(?, ''), empfehlung),
                 ki_klassifiziert_am = NOW(),
                 ki_confidence = ?,
                 ki_mit_crawl = ?,
                 ki_begruendung = ?
             WHERE id = ?",
            [
                $ergebnis['linkart'] ?? '',
                $ergebnis['empfehlung'] ?? '',
                $ergebnis['confidence'] ?? 'mittel',
                $mitCrawl ? 1 : 0,
                $ergebnis['begruendung'] ?? null,
                $verlinkungId,
            ]
        );
        $ergebnis['mit_crawl'] = $mitCrawl;
        return $ergebnis;
    }

    /**
     * Bulk-Klassifikation für mehrere Verlinkungen.
     * Standard ohne Crawl (schnell+günstig). Rate-Limit: 300ms zwischen Anfragen.
     */
    public function klassifiziereVerlinkungenBulk(array $verlinkungIds, bool $mitCrawl = false): array
    {
        $ok = 0; $fehler = 0; $fehlerListe = [];
        foreach ($verlinkungIds as $id) {
            try {
                $this->klassifiziereVerlinkung((string)$id, $mitCrawl);
                $ok++;
            } catch (\Throwable $e) {
                $fehler++;
                $fehlerListe[] = $id . ': ' . $e->getMessage();
            }
            usleep(300_000); // 300ms Rate-Limit
        }
        $this->auditBulk('ki.klassifikation_bulk', 'verlinkung', $ok, ['mit_crawl' => $mitCrawl, 'fehler' => $fehler]);
        return ['ok' => $ok, 'fehler' => $fehler, 'fehler_liste' => $fehlerListe];
    }

    private function crawleSeitenAusschnitt(string $url): string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'user_agent' => 'Mozilla/5.0 LAM-KI-Klassifikator',
                'follow_location' => 1,
                'max_redirects' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $html = @file_get_contents($url, false, $ctx, 0, 400_000);
        if (!$html) return '';
        // Rauschen entfernen: <head>, <script>, <style>, <noscript>, <svg>, HTML-Kommentare
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/is', '', $html);
        $html = preg_replace('/<svg[\s\S]*?<\/svg>/i', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return mb_substr($text, 0, 15000);
    }

    private function fragKiNachKlassifikation(array $v, string $seitenAuszug = ''): array
    {
        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key nicht konfiguriert (Settings → API-Schlüssel).');

        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(500);
        $ai->setTimeout(20);

        $linkartListe = self::VERLINKUNG_LINKART;
        $empfehlungListe = self::VERLINKUNG_EMPFEHLUNG;

        $system = "Du klassifizierst Backlinks aus dem Linkaufbau einer SEO-Agentur (Thoxan).
Antworte AUSSCHLIESSLICH mit gültigem JSON, ohne Markdown-Codeblock-Wrapper, ohne Text drumherum.
Schema:
{
  \"linkart\": \"<einer dieser Werte: " . implode(', ', $linkartListe) . ">\",
  \"empfehlung\": \"<einer dieser Werte: " . implode(', ', $empfehlungListe) . ">\",
  \"confidence\": \"<hoch|mittel|niedrig>\",
  \"begruendung\": \"<max 200 Zeichen, knapp auf Deutsch>\"
}

Linkart (17 Werte + social_media):
- 'spam' = Linkfarm, Klickvieh, keine Redaktion
- 'branchenverzeichnis' = allgemeines Branchen-/Firmenverzeichnis
- 'fachverzeichnis' = branchenspezifisches Verzeichnis
- 'online_magazin' = redaktionell betreutes Magazin
- 'portal' = Themen- oder Branchenportal
- 'blog' = gepflegtes redaktionelles Blog
- 'presseportal' = Pressemitteilungs-Plattform (OpenPR, Trendkraft, …)
- 'forum' = Community-Forum
- 'referenzprojekt' = Kunde wird als Referenz auf Partner-Website genannt
- 'partner' = Händler, Lieferant, Kooperationspartner
- 'sponsoring' = Sponsored Listing, Sponsoring-Verlinkung
- 'stellenboerse' = Job-/Karriereplattform
- 'veranstaltung' = Eventseite, Messe, Sponsoren-Seite
- 'kommentarlink' = Backlink aus Blog-/Forum-Kommentar
- 'podcast' = Podcast-Plattform oder Episodenseite
- 'weiterleitung' = 301 oder andere Weiterleitung
- 'social_media' = Social-Media-Profil/Posting (LinkedIn, XING, Facebook …)
- 'sonstiges' nur wenn keine andere Kategorie passt

Empfehlung (5 Spec-Werte + 'unsicher' bei niedriger KI-Confidence):
- 'lassen' = Link ist okay, kein Handlungsbedarf
- 'aendern' = Link ist da, Inhalt/Linktext sollte korrigiert werden
- 'loeschen' = Link soll entfernt werden
- 'disavow' = nicht erreichbar oder Anbieter reagiert nicht, Disavow-Empfehlung
- 'geloescht' = Link ist mittlerweile entfernt
- 'unsicher' = bei eigener niedriger Confidence (statt zu raten)

Hard-Rule: linkart='spam' → empfehlung='disavow'.";

        $user = "Verlinkende URL: {$v['verlinkende_url']}\n"
              . "Quell-Domain: {$v['domain']}\n"
              . "Linktext: " . ($v['linktext'] ?: '(kein Linktext)') . "\n"
              . "Ziel-URL: " . ($v['ziel_url'] ?: '(keine Ziel-URL gespeichert)') . "\n";
        if ($seitenAuszug !== '') {
            $user .= "\nAuszug aus der Quellseite (Klartext, max 5000 Zeichen):\n" . $seitenAuszug;
        }

        // Retry mit Backoff bei 529/Rate-Limit (Anthropic kann sporadisch überlastet sein)
        $antwort = null;
        $versuche = 0;
        $maxVersuche = 3;
        while ($versuche < $maxVersuche) {
            try {
                $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
                break;
            } catch (\Throwable $e) {
                $versuche++;
                if ($versuche >= $maxVersuche) throw $e;
                // Bei 529/429 mit exponentiellem Backoff retry
                if (preg_match('/\b(529|429|503)\b/', $e->getMessage())) {
                    sleep($versuche * 2);
                    continue;
                }
                throw $e;
            }
        }
        $content = trim($antwort['content'] ?? '');

        // JSON aus möglichem Codeblock extrahieren
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];

        $daten = json_decode($content, true);
        if (!is_array($daten)) {
            throw new \RuntimeException('KI-Antwort war kein JSON: ' . mb_substr($content, 0, 200));
        }

        // Whitelist-Check
        if (isset($daten['linkart']) && !in_array($daten['linkart'], $linkartListe, true)) {
            $daten['linkart'] = 'sonstiges';
        }
        if (isset($daten['empfehlung']) && !in_array($daten['empfehlung'], $empfehlungListe, true)) {
            $daten['empfehlung'] = 'unsicher';
        }
        // Hard-Rule: spam → disavow
        if (($daten['linkart'] ?? '') === 'spam') {
            $daten['empfehlung'] = 'disavow';
        }
        $daten['confidence'] = in_array($daten['confidence'] ?? '', ['hoch','mittel','niedrig'], true)
            ? $daten['confidence'] : 'mittel';
        return $daten;
    }

    // ---------------------------------------------------------------------
    // Asana-Integration (Phase 1: Lese-Sync, Phase 1b: KI-Felder-Extraktion)
    // ---------------------------------------------------------------------

    private function asanaService(): ?\Services\AsanaService
    {
        require_once SERVICES_PATH . '/AsanaService.php';
        $pat = \Core\Settings::get('asana_pat');
        if (empty($pat)) return null;
        return new \Services\AsanaService($pat);
    }

    /**
     * Sucht Asana-Tasks für eine Maßnahme: in der Section des zugehörigen Kunden.
     * Optionaler Suchbegriff filtert clientseitig.
     */
    public function asanaTasksFuerMassnahme(string $massnahmeId, string $suche = '', ?string $sectionGid = null): array
    {
        $m = $this->db->queryOne(
            "SELECT m.id, c.asana_section_gid, c.asana_projekt_gid
             FROM lam_massnahmen m
             LEFT JOIN customers c ON c.id = m.customer_id
             WHERE m.id = ? AND m.geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m) throw new \InvalidArgumentException('Maßnahme nicht gefunden.');
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        // Explizit gewählte Section hat Vorrang (z.B. „Erledigt"-Spalte)
        $gid = $sectionGid !== null && $sectionGid !== '' ? $sectionGid : ($m['asana_section_gid'] ?? '');
        if ($gid !== '') {
            $tasks = $svc->listTasksInSection($gid, 100);
        } elseif (!empty($m['asana_projekt_gid'])) {
            $tasks = $svc->searchTasks($m['asana_projekt_gid'], $suche, 50);
        } else {
            throw new \RuntimeException('Für den Kunden ist kein Asana-Projekt + Section konfiguriert. /admin/customers → Kunde bearbeiten.');
        }
        if ($suche !== '') {
            $q = mb_strtolower(trim($suche));
            $tasks = array_values(array_filter($tasks, fn($t) => mb_strpos(mb_strtolower($t['name'] ?? ''), $q) !== false));
        }
        return array_slice($tasks, 0, 50);
    }

    /**
     * Listet alle Sections des für die Maßnahme zuständigen Asana-Projekts.
     * Markiert die konfigurierte Default-Section (= Linkoptionen-Spalte).
     */
    public function asanaSectionsFuerMassnahme(string $massnahmeId): array
    {
        $m = $this->db->queryOne(
            "SELECT m.id, c.asana_section_gid, c.asana_projekt_gid
             FROM lam_massnahmen m
             LEFT JOIN customers c ON c.id = m.customer_id
             WHERE m.id = ? AND m.geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m) throw new \InvalidArgumentException('Maßnahme nicht gefunden.');
        if (empty($m['asana_projekt_gid'])) return [];
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        $sections = $svc->listSections($m['asana_projekt_gid']);
        $defaultGid = $m['asana_section_gid'] ?? '';
        foreach ($sections as &$s) {
            $s['ist_default'] = ((string)$s['gid'] === (string)$defaultGid);
        }
        return $sections;
    }

    /**
     * Verknüpft eine Maßnahme mit einer Asana-Task und cacht die Task-Details.
     */
    /**
     * Schreibt die Position-Spalte aller übergebenen Einträge entsprechend ihrer Reihenfolge im Array neu (1..n).
     * Validiert, dass alle IDs zur angegebenen Liste gehören.
     */
    public function aktualisierePositionen(string $listenId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('strval', $ids), fn($s) => $s !== ''));
        if (empty($ids)) throw new \InvalidArgumentException('Keine IDs übergeben.');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$listenId], $ids);
        $count = (int) $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_vorschlagsliste_eintraege WHERE vorschlagsliste_id = ? AND id IN ($placeholders)",
            $params
        );
        if ($count !== count($ids)) {
            throw new \InvalidArgumentException('Mindestens eine ID gehört nicht zur Liste oder ist gelöscht.');
        }
        $this->db->beginTransaction();
        try {
            $pos = 1;
            foreach ($ids as $id) {
                $this->db->execute(
                    "UPDATE lam_vorschlagsliste_eintraege SET position = ? WHERE id = ? AND vorschlagsliste_id = ?",
                    [$pos, $id, $listenId]
                );
                $pos++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ───────── Linkoption ↔ Asana (Briefing 03 ▸ 13: vorhandenes Ticket verbinden) ─────────

    /**
     * Listet Asana-Tasks aus der Section des Kunden, in der die Linkoption liegt.
     * Analog zu asanaTasksFuerMassnahme.
     */
    public function asanaTasksFuerLinkoption(string $linkoptionId, string $suche = '', ?string $sectionGid = null): array
    {
        $e = $this->db->queryOne(
            "SELECT v.customer_id, c.asana_section_gid, c.asana_projekt_gid
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             JOIN customers c ON c.id = v.customer_id
             WHERE e.id = ?",
            [$linkoptionId]
        );
        if (!$e) throw new \InvalidArgumentException('Linkoption nicht gefunden.');
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        $gid = $sectionGid !== null && $sectionGid !== '' ? $sectionGid : ($e['asana_section_gid'] ?? '');
        if ($gid !== '') {
            $tasks = $svc->listTasksInSection($gid, 100);
        } elseif (!empty($e['asana_projekt_gid'])) {
            $tasks = $svc->searchTasks($e['asana_projekt_gid'], $suche, 50);
        } else {
            throw new \RuntimeException('Für den Kunden ist kein Asana-Projekt + Section konfiguriert. /admin/customers → Kunde bearbeiten.');
        }
        if ($suche !== '') {
            $q = mb_strtolower(trim($suche));
            $tasks = array_values(array_filter($tasks, fn($t) => mb_strpos(mb_strtolower($t['name'] ?? ''), $q) !== false));
        }
        return array_slice($tasks, 0, 50);
    }

    /**
     * Listet alle Sections des Asana-Projekts der Linkoption, mit Default-Flag.
     */
    public function asanaSectionsFuerLinkoption(string $linkoptionId): array
    {
        $e = $this->db->queryOne(
            "SELECT v.customer_id, c.asana_section_gid, c.asana_projekt_gid
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             JOIN customers c ON c.id = v.customer_id
             WHERE e.id = ?",
            [$linkoptionId]
        );
        if (!$e) throw new \InvalidArgumentException('Linkoption nicht gefunden.');
        if (empty($e['asana_projekt_gid'])) return [];
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        $sections = $svc->listSections($e['asana_projekt_gid']);
        $defaultGid = $e['asana_section_gid'] ?? '';
        foreach ($sections as &$s) {
            $s['ist_default'] = ((string)$s['gid'] === (string)$defaultGid);
        }
        return $sections;
    }


    /**
     * Erstellt einen neuen Asana-Task für eine Linkoption und verknüpft ihn.
     * Task-Titel: „<Kunden-Kürzel> <Domain> / <Preis> Euro"
     * Task-Beschreibung enthält strukturiert: URL · SI/DP · Preis · Linkziel · Linktext · Kontext · Artikelthema
     * Section: asana_section_gid des Kunden.
     */
    /**
     * Vorschau für den Asana-Task: gibt Titel + Beschreibung zurück OHNE den Task anzulegen.
     * Frontend zeigt das als Modal, User kann Titel/Beschreibung editieren, dann anlegen.
     */
    public function asanaVorschauFuerLinkoption(string $linkoptionId): array
    {
        $kontext = $this->bereiteAsanaLinkoptionVor($linkoptionId);
        return [
            'titel'           => $kontext['name'],
            'beschreibung'    => $kontext['notes'],
            'projekt_gid'     => $kontext['projekt_gid'],
            'section_gid'     => $kontext['section_gid'],
            'kunden_kuerzel'  => $kontext['customer_kuerzel'],
            'domain'          => $kontext['domain_url'],
        ];
    }

    public function asanaErstelleTaskFuerLinkoption(string $linkoptionId, ?string $titelOverride = null, ?string $beschreibungOverride = null): array
    {
        $kontext = $this->bereiteAsanaLinkoptionVor($linkoptionId);
        $name  = $titelOverride !== null && trim($titelOverride) !== '' ? trim($titelOverride) : $kontext['name'];
        // Wenn Tom den Beschreibungstext in der Vorschau bearbeitet hat → plain notes (Override).
        // Sonst: html_notes mit Linktext-Unterstreichung + klickbaren URLs nutzen.
        $useOverride = ($beschreibungOverride !== null);
        $notes      = $useOverride ? (string) $beschreibungOverride : $kontext['notes'];
        $htmlNotes  = $useOverride ? null : ($kontext['html_notes'] ?? null);

        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        // Task in Asana anlegen — html_notes bevorzugt (mit <u>Linktext</u> + <a href>)
        $task = $svc->createTask($kontext['projekt_gid'], $name, $kontext['section_gid'], $notes, null, $htmlNotes);

        // Linkoption verknüpfen
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege
             SET asana_task_gid = ?, asana_task_cache = ?, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [$task['gid'], json_encode($task, JSON_UNESCAPED_UNICODE), $linkoptionId]
        );
        $this->audit('linkoption.asana_neu', 'linkoption', $linkoptionId, ['task_gid' => $task['gid'], 'task_name' => $name]);

        return ['task_gid' => $task['gid'], 'task_name' => $name, 'permalink_url' => $task['permalink_url'] ?? null];
    }

    /**
     * Bereitet Titel + Beschreibung für den Asana-Task vor, ohne anzulegen.
     * Wird sowohl von der Vorschau als auch der Anlage genutzt.
     */
    private function bereiteAsanaLinkoptionVor(string $linkoptionId): array
    {
        // ⚠ HINWEIS: e.preis_anbieter wird BEWUSST NICHT geladen — interner Einkaufspreis
        //   darf nie ins Asana-Ticket (Kunde sieht das Ticket potenziell mit).
        $e = $this->db->queryOne(
            "SELECT e.id, e.preis_kunde, e.beispielartikel_url, e.artikelthema, e.kontext_einbau, e.mit_anbieternennung, e.notiz,
                    e.ziel_url AS linkziel_url, e.vorgeschlagener_linktext AS linktext,
                    d.url AS domain_url, d.id AS domain_id, d.ki_kurzbeschreibung,
                    (SELECT si FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS si,
                    (SELECT dp FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id ORDER BY ks.erfasst_am DESC LIMIT 1) AS dp,
                    c.abbreviation AS customer_kuerzel, c.asana_projekt_gid, c.asana_section_gid
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_domains d ON d.id = e.domain_id
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             JOIN customers c ON c.id = v.customer_id
             WHERE e.id = ?",
            [$linkoptionId]
        );
        if (!$e) throw new \InvalidArgumentException('Linkoption nicht gefunden.');
        if (empty($e['asana_projekt_gid']) || empty($e['asana_section_gid'])) {
            throw new \RuntimeException('Für den Kunden ist kein Asana-Projekt + Linkoptionen-Section konfiguriert. /admin/customers → Kunde bearbeiten.');
        }

        $preisStr = $e['preis_kunde'] !== null ? number_format((float)$e['preis_kunde'], 0, ',', '.') . ' Euro' : '?';
        $name = sprintf('%s %s / %s',
            $e['customer_kuerzel'] ?? '?',
            $e['domain_url'] ?? '?',
            $preisStr
        );

        // Kanonische URL ermitteln: folge HTTPS-Redirects damit aus „infopoint-security.de"
        // die echte „https://www.infopoint-security.de/" wird (URL aus der DB ist normalisiert ohne www.)
        $domainUrl = $e['domain_url'] ? $this->ermittleKanonischeDomainUrl($e['domain_url']) : '';
        $zeilen = [];
        if ($domainUrl) $zeilen[] = $domainUrl;
        // Beispiel-Format: „SI 0,358, DP 1.344" — Tausender-Punkt bei DP, beide Werte immer, fehlende mit „—"
        $siStr = $e['si'] !== null ? number_format((float)$e['si'], 3, ',', '.') : '—';
        $dpStr = $e['dp'] !== null ? number_format((int)$e['dp'],    0, ',', '.') : '—';
        $zeilen[] = 'SI ' . $siStr . ', DP ' . $dpStr;
        $zeilen[] = $preisStr;
        $zeilen[] = '';
        // Beispielartikel/Kategorie — NUR wenn URL gefüllt (Tom: „falls nicht ausgefüllt, in Asana weglassen")
        if (!empty($e['beispielartikel_url'])) {
            $zeilen[] = 'Beispielartikel / Kategorie:';
            $zeilen[] = $e['beispielartikel_url'];
            $zeilen[] = '';
        }
        if (!empty($e['linkziel_url'])) {
            $zeilen[] = 'Linkziel:';
            $zeilen[] = $e['linkziel_url'];
            $zeilen[] = '';
        }
        if (!empty($e['linktext'])) {
            $zeilen[] = 'Linktext:';
            $zeilen[] = $e['linktext'];
            $zeilen[] = '';
        }
        // Tom-Tausch: Kontext für Linkeinbau ZUERST, Artikelthema danach
        if (!empty($e['kontext_einbau'])) {
            $zeilen[] = 'Kontext für Linkeinbau:';
            $zeilen[] = $e['kontext_einbau'];
            $zeilen[] = '';
        } elseif (!empty($e['ki_kurzbeschreibung'])) {
            // Fallback: alte automatische Domain-Kurzbeschreibung, solange noch kein Kontext gepflegt ist
            $zeilen[] = 'Domain-Kontext (automatisch):';
            $zeilen[] = $e['ki_kurzbeschreibung'];
            $zeilen[] = '';
        }
        if (!empty($e['artikelthema'])) {
            $zeilen[] = 'Artikelthema:';
            $zeilen[] = $e['artikelthema'];
            $zeilen[] = '';
        }
        // Bemerkungen IMMER schreiben — auch wenn leer, damit Tom oder Umsetzer dort später ergänzen kann
        $zeilen[] = 'Bemerkungen:';
        if (!empty($e['notiz'])) {
            $zeilen[] = $e['notiz'];
        }
        // trailing leere Zeilen entfernen
        while (!empty($zeilen) && end($zeilen) === '') array_pop($zeilen);

        $plainNotes = implode("\n", $zeilen);
        $htmlNotes  = $this->renderAsanaHtmlNotes($zeilen, $e['linktext'] ?? '');

        return [
            'name'             => $name,
            'notes'            => $plainNotes,
            'html_notes'       => $htmlNotes,
            'projekt_gid'      => $e['asana_projekt_gid'],
            'section_gid'      => $e['asana_section_gid'],
            'customer_kuerzel' => $e['customer_kuerzel'] ?? '',
            'domain_url'       => $e['domain_url'] ?? '',
        ];
    }

    /**
     * Baut aus den Beschreibungs-Zeilen die formatierte html_notes für Asana.
     * - URLs werden als <a>-Tags klickbar
     * - Linktext wird im gesamten Text unterstrichen (mit Wortgrenzen, case-sensitive Begin)
     */
    private function renderAsanaHtmlNotes(array $zeilen, string $linktext): string
    {
        // Zeilen in HTML schieben: Block-Labels (enden auf „:") fett, Rest normal
        $labels = ['Linkziel:', 'Linktext:', 'Beispielartikel / Kategorie:', 'Kontext für Linkeinbau:',
                   'Domain-Kontext (automatisch):', 'Artikelthema:', 'Bemerkungen:'];
        $linktextEsc = trim($linktext);

        $htmlLines = [];
        foreach ($zeilen as $z) {
            $zTrim = trim($z);
            if ($zTrim === '') { $htmlLines[] = ''; continue; }

            if (in_array($zTrim, $labels, true)) {
                $htmlLines[] = '<strong>' . htmlspecialchars($zTrim, ENT_QUOTES | ENT_HTML5) . '</strong>';
                continue;
            }
            // Normale Zeile escapen
            $line = htmlspecialchars($z, ENT_QUOTES | ENT_HTML5);
            // URLs verlinken — http(s)://… bis Whitespace
            $line = preg_replace_callback(
                '#(https?://[^\s<>"]+)#u',
                fn($m) => '<a href="' . $m[1] . '">' . $m[1] . '</a>',
                $line
            );
            // Linktext unterstreichen (case-insensitive, Wortgrenzen — vermeidet false positives wie „abet" in „kabel")
            if ($linktextEsc !== '' && mb_strlen($linktextEsc) >= 3) {
                $linktextHtml = htmlspecialchars($linktextEsc, ENT_QUOTES | ENT_HTML5);
                $line = preg_replace(
                    '/(?<![\p{L}\p{N}])(' . preg_quote($linktextHtml, '/') . ')(?![\p{L}\p{N}])/iu',
                    '<u>$1</u>',
                    $line
                );
            }
            $htmlLines[] = $line;
        }
        // Asana html_notes erwartet <body>…</body>, Zeilenumbruch via \n; \n wird gerendert
        return '<body>' . implode("\n", $htmlLines) . '</body>';
    }

    /**
     * Ermittelt die „kanonische" URL einer Domain (für Asana-Ticket-Beschreibung etc.).
     *
     * Strategie:
     *  1. GET auf https://<domain>/ mit Redirect-Folgen
     *  2. Aus dem HTML-Head <link rel="canonical" href="…"> auslesen — das ist die vom
     *     Seitenbetreiber selbst gesetzte offizielle Variante (z. B. „https://www.infopoint-security.de/"
     *     auch wenn beide Versionen antworten)
     *  3. Wenn keine canonical-Angabe → die finale URL nach Redirects
     *  4. Fallback bei Fehler: „https://<domain>/"
     */
    /**
     * KI-Vorschläge für Artikelthema + Kontext einer Linkoption.
     * Fetcht Linkziel-Meta + Domain-Home, lässt das LLM 5 Vorschläge bauen.
     */
    public function schlageArtikelthemenVor(string $linkoptionId): array
    {
        $eintrag = $this->db->queryOne(
            "SELECT e.ziel_url, e.vorgeschlagener_linktext, e.artikelthema, e.mit_anbieternennung,
                    d.url AS domain_url, d.ki_kurzbeschreibung,
                    c.name AS customer_name, c.abbreviation AS customer_kuerzel,
                    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS tags
             FROM lam_vorschlagsliste_eintraege e
             JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id
             LEFT JOIN customers c ON c.id = v.customer_id
             LEFT JOIN lam_domains d ON d.id = e.domain_id
             LEFT JOIN lam_domain_tag dt ON dt.domain_id = d.id
             LEFT JOIN lam_tags t ON t.id = dt.tag_id
             WHERE e.id = ?
             GROUP BY e.id",
            [$linkoptionId]
        );
        if (!$eintrag) throw new \InvalidArgumentException('Linkoption nicht gefunden.');

        $linkziel  = trim((string)($eintrag['ziel_url'] ?? ''));
        $linktext  = trim((string)($eintrag['vorgeschlagener_linktext'] ?? ''));
        $domainUrl = trim((string)($eintrag['domain_url'] ?? ''));
        $kundeName = trim((string)($eintrag['customer_name'] ?? ''));
        $mitAnbieternennung = (int)($eintrag['mit_anbieternennung'] ?? 0) === 1;
        if ($linkziel === '') throw new \RuntimeException('Linkziel fehlt — bitte erst eintragen.');

        $linkzielMeta  = $this->ladeSeitenMeta($linkziel);
        $domainHomeMeta = $domainUrl !== '' ? $this->ladeSeitenMeta('https://' . preg_replace('#^https?://#', '', $domainUrl) . '/') : ['title' => '', 'description' => ''];

        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (!$apiKey) throw new \RuntimeException('Anthropic-API-Key fehlt — bitte in /admin/settings eintragen.');

        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-sonnet-4-6');
        $ai->setMaxTokens(2200);
        $ai->setTimeout(45);

        $anbieterRegel = $mitAnbieternennung && $kundeName !== ''
            ? "- ANBIETERNENNUNG ERLAUBT: Du DARFST und SOLLST den Kundennamen \"{$kundeName}\" im Kontext-Absatz natürlich erwähnen, z.B. \"Anbieter wie {$kundeName}\" oder \"Lösungen von {$kundeName}\". Linktext wird typischerweise direkt danach gesetzt. Beispiel: „Ein individueller Videoplayer von spezialisierten Anbietern wie {$kundeName} lässt sich direkt einbinden.\""
            : "- NEUTRAL HALTEN: Du darfst den Kundennamen NICHT erwähnen. Formuliere generisch (\"spezialisierte Lösungen\", \"professionelle Anbieter\", \"moderne Plattformen\") und baue den Linktext in einen sachlichen Erklär-Absatz ein. Manche Veröffentlichungs-Plattformen verbieten Markennennung explizit.";

        $system = "Du bist erfahrener Linkbuilding-Stratege bei einer SEO-Agentur. Du planst Gastartikel auf Drittwebsites, in denen ein Kunden-Link möglichst natürlich eingebettet werden soll.\n\n"
                . "Du bekommst:\n"
                . "1. Die LINKZIEL-URL des Kunden (worauf verlinkt wird) + Meta-Infos der Seite\n"
                . "2. Den geplanten LINKTEXT (Ankertext)\n"
                . "3. Die ZIELWEBSITE (auf der der Artikel veröffentlicht wird) + deren thematische Ausrichtung\n"
                . "4. Ggf. den Kundennamen + die Regel, ob er im Kontext genannt werden darf\n\n"
                . "Schlage 3 konkrete Kombinationen aus ARTIKELTHEMA und KONTEXT vor. Beachte:\n"
                . "- 'thema' ist ein KURZER Themen-Brief in 1-2 Sätzen, der Wortzahl + ggf. Beispiele/Aufbau nennt. Beispiel: \"Anwendungsfälle für innovative, B2B-lastige Apps im Business-Kontext, mind. 1.000 Wörter mit 2-3 praktischen Beispielen\"\n"
                . "- 'kontext' ist ein konkreter VORSCHLAG für den Absatz, in dem der Link erscheinen soll: entweder ein direktes Zitat-/Erklär-Beispiel mit eingebautem Linktext, oder ein knapper Aufhänger-Absatz. Verlinkungslogisch sinnvoll, natürlich klingend.\n"
                . "- Themen müssen thematisch zur ZIELWEBSITE passen (nicht zur Kundenseite!) und gleichzeitig eine plausible Brücke zum Linkziel ergeben.\n"
                . "- Schreibstil: sachlich, deutsch, Du-Form bei Kontext-Zitaten falls passend. KEINE Anglizismen (statt 'Process'/'Campaign'/'Implementation' bitte 'Vorgang'/'Maßnahme'/'Umsetzung').\n"
                . "- KEINE Em-Dashes (—), stattdessen Doppelpunkt, Klammer oder Bindestrich.\n"
                . $anbieterRegel . "\n\n"
                . "Antworte AUSSCHLIESSLICH mit JSON: {\"vorschlaege\":[{\"thema\":\"...\",\"kontext\":\"...\"}, ...]}. Keine Einleitung, kein Kommentar.";

        $user = "## Linkziel (Kunde)\n"
              . "URL: {$linkziel}\n"
              . "Seitentitel: " . ($linkzielMeta['title'] ?: '(unbekannt)') . "\n"
              . "Meta-Beschreibung: " . ($linkzielMeta['description'] ?: '(keine)') . "\n"
              . "Kundenname (Marke): " . ($kundeName !== '' ? $kundeName : '(unbekannt)') . "\n"
              . "Anbieternennung: " . ($mitAnbieternennung ? 'JA — Kunde darf im Kontext-Absatz natürlich genannt werden' : 'NEIN — Markennennung verboten, neutral formulieren') . "\n"
              . "Linktext: " . ($linktext !== '' ? $linktext : '(noch nicht festgelegt — schlage einen sinnvollen Linktext mit vor und baue den Vorschlag darum herum)') . "\n\n"
              . "## Zielwebsite (Veröffentlichungsort)\n"
              . "Domain: " . ($domainUrl ?: '(unbekannt)') . "\n"
              . "Startseite-Titel: " . ($domainHomeMeta['title'] ?: '(unbekannt)') . "\n"
              . "Startseite-Beschreibung: " . ($domainHomeMeta['description'] ?: '(keine)') . "\n"
              . "Tags/Themen: " . (!empty($eintrag['tags']) ? $eintrag['tags'] : '(keine)') . "\n"
              . "Kurzbeschreibung intern: " . (!empty($eintrag['ki_kurzbeschreibung']) ? mb_substr($eintrag['ki_kurzbeschreibung'], 0, 600) : '(keine)') . "\n";

        $response = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $text = trim((string)($response['content'] ?? ''));
        // JSON extrahieren (auch wenn in ```json eingebettet)
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) $text = $m[0];
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['vorschlaege']) || !is_array($parsed['vorschlaege'])) {
            throw new \RuntimeException('KI-Antwort konnte nicht ausgewertet werden.');
        }
        $clean = [];
        foreach ($parsed['vorschlaege'] as $v) {
            $thema = trim((string)($v['thema'] ?? ''));
            $kontext = trim((string)($v['kontext'] ?? ''));
            if ($thema === '' && $kontext === '') continue;
            $clean[] = ['thema' => $thema, 'kontext' => $kontext];
        }
        return ['vorschlaege' => $clean, 'kontext_quelle' => [
            'linkziel_titel'    => $linkzielMeta['title'],
            'linkziel_meta'     => $linkzielMeta['description'],
            'domain_titel'      => $domainHomeMeta['title'],
            'domain_meta'       => $domainHomeMeta['description'],
        ]];
    }

    /**
     * Lädt Title + Meta-Description einer Seite (8 KB, 5 Sek Timeout).
     */
    private function ladeSeitenMeta(string $url): array
    {
        $out = ['title' => '', 'description' => ''];
        if (!function_exists('curl_init') || !preg_match('#^https?://#', $url)) return $out;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KI-Tool LAM Artikelthemen-Helfer)',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_RANGE          => '0-16384',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!is_string($body) || $body === '') return $out;
        if (preg_match('#<title[^>]*>(.+?)</title>#is', $body, $m)) {
            $out['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('#<meta[^>]+name=[\"\']description[\"\'][^>]+content=[\"\']([^\"\']+)[\"\']#i', $body, $m)
            || preg_match('#<meta[^>]+content=[\"\']([^\"\']+)[\"\'][^>]+name=[\"\']description[\"\']#i', $body, $m)
            || preg_match('#<meta[^>]+property=[\"\']og:description[\"\'][^>]+content=[\"\']([^\"\']+)[\"\']#i', $body, $m)) {
            $out['description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return $out;
    }

    private function ermittleKanonischeDomainUrl(string $domain): string
    {
        $startUrl = 'https://' . $domain . '/';
        if (!function_exists('curl_init')) return $startUrl;

        $ch = curl_init($startUrl);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KI-Tool LAM)',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_RANGE          => '0-8192', // nur die ersten 8 KB für <head>-Lookup
        ]);
        $body = curl_exec($ch);
        $endUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $http   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$endUrl || $http < 200 || $http >= 400) return $startUrl;

        // <link rel="canonical" href="..."> aus dem Body extrahieren
        if (is_string($body) && preg_match('#<link[^>]+rel=[\"\']?canonical[\"\']?[^>]+href=[\"\']([^\"\']+)[\"\']#i', $body, $m)) {
            $canonical = trim($m[1]);
            if ($canonical !== '' && preg_match('#^https?://#', $canonical)) {
                // Wenn canonical nur auf Root zeigt → Trailing-Slash sicherstellen
                if (preg_match('#^https?://[^/]+$#', $canonical)) $canonical .= '/';
                return $canonical;
            }
        }

        // Fallback: finale URL nach Redirect
        if (preg_match('#^https?://[^/]+$#', $endUrl)) $endUrl .= '/';
        return $endUrl;
    }

    public function asanaVerknuepfeLinkoption(string $linkoptionId, string $taskGid): array
    {
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');
        $task = $svc->getTask($taskGid);
        if (!$task) throw new \RuntimeException('Asana-Task nicht gefunden.');

        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege
             SET asana_task_gid = ?, asana_task_cache = ?, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [$taskGid, json_encode($task, JSON_UNESCAPED_UNICODE), $linkoptionId]
        );
        $this->audit('linkoption.asana_verknuepft', 'linkoption', $linkoptionId, ['task_gid' => $taskGid, 'task_name' => $task['name'] ?? '']);

        // Felder aus Task-Beschreibung importieren — nur in leere Linkoption-Felder, niemals überschreiben
        $importiert = $this->importiereLinkoptionFelderAusAsana($linkoptionId, $task);
        $task['_importiert'] = $importiert; // Feldname → übernommener Wert (für UI-Feedback)
        return $task;
    }

    /**
     * Parst eine Asana-Task-Beschreibung und befüllt LEERE Linkoption-Felder.
     * Erkennt Block-Marker (Label gefolgt von Doppelpunkt, dann Inhalt bis zum nächsten Marker).
     * Schreibt NIE über bestehende Werte — Tom soll nichts ungewollt verlieren.
     */
    private function importiereLinkoptionFelderAusAsana(string $linkoptionId, array $task): array
    {
        $notes = (string)($task['notes'] ?? '');
        if ($notes === '') return [];
        $parsed = $this->parseAsanaTicketBeschreibung($notes);
        if (empty($parsed)) return [];

        // Aktuelle Werte holen — wir wollen nur LEERE Felder befüllen
        $aktuell = $this->db->queryOne(
            "SELECT preis_kunde, beispielartikel_url, ziel_url, vorgeschlagener_linktext, artikelthema, kontext_einbau, notiz
             FROM lam_vorschlagsliste_eintraege WHERE id = ?",
            [$linkoptionId]
        );
        if (!$aktuell) return [];

        // Mapping Parser-Key → DB-Spalte
        $mapping = [
            'preis_kunde'              => 'preis_kunde',
            'beispielartikel_url'      => 'beispielartikel_url',
            'ziel_url'                 => 'ziel_url',
            'vorgeschlagener_linktext' => 'vorgeschlagener_linktext',
            'kontext_einbau'           => 'kontext_einbau',
            'artikelthema'             => 'artikelthema',
            'notiz'                    => 'notiz',
        ];
        $uebernommen = [];
        foreach ($mapping as $parserKey => $dbKey) {
            if (empty($parsed[$parserKey])) continue;
            // Bestehender Wert vorhanden? Dann nicht überschreiben.
            $vorhandenerWert = trim((string)($aktuell[$dbKey] ?? ''));
            if ($vorhandenerWert !== '' && $vorhandenerWert !== '0' && $vorhandenerWert !== '0.00') continue;
            $neuerWert = $parsed[$parserKey];
            $this->db->execute("UPDATE lam_vorschlagsliste_eintraege SET {$dbKey} = ? WHERE id = ?", [$neuerWert, $linkoptionId]);
            $uebernommen[$dbKey] = $neuerWert;
        }
        if (!empty($uebernommen)) {
            $this->audit('linkoption.felder_aus_asana_importiert', 'linkoption', $linkoptionId, ['felder' => array_keys($uebernommen)]);
        }
        return $uebernommen;
    }

    /**
     * Liest aus einer Asana-Task-Beschreibung die typischen Linkoption-Blöcke.
     * Toleriert deutsche + englische Marker, Reihenfolge egal.
     * Liefert Array mit den Keys: preis_kunde, beispielartikel_url, ziel_url,
     *   vorgeschlagener_linktext, kontext_einbau, artikelthema, notiz.
     * Fehlende Blöcke → Key fehlt.
     */
    private function parseAsanaTicketBeschreibung(string $notes): array
    {
        // Marker → interner Key. Reihenfolge egal. Aliasse für ältere Tickets/handgeschriebene.
        $marker = [
            'beispielartikel_url'      => ['Beispielartikel / Kategorie', 'Beispielartikel/Kategorie', 'Beispielartikel', 'Beispiel-URL'],
            'ziel_url'                 => ['Linkziel', 'Ziel-URL', 'Target URL', 'Linkziel-URL'],
            'vorgeschlagener_linktext' => ['Linktext', 'Anchor', 'Ankertext'],
            'kontext_einbau'           => ['Kontext für Linkeinbau', 'Kontext für Linkeinbindung', 'Kontext', 'Domain-Kontext (automatisch)', 'Domain-Kontext'],
            'artikelthema'             => ['Artikelthema', 'Thema', 'Artikel-Thema'],
            'notiz'                    => ['Bemerkungen', 'Bemerkung', 'Notizen', 'Notiz', 'Hinweise'],
        ];

        // Alle Marker-Pattern für Block-Grenze (OR-Liste)
        $alleAliasse = [];
        foreach ($marker as $aliasse) foreach ($aliasse as $a) $alleAliasse[] = preg_quote($a, '/');
        // Block-Pattern: Label am Zeilenanfang + Doppelpunkt + Inhalt bis vor den nächsten Label-Anfang ODER Dokumentende
        $grenze = '(?=^(?:' . implode('|', $alleAliasse) . ')\s*:|\z)';

        $ergebnis = [];
        foreach ($marker as $key => $aliasse) {
            $aliasPattern = implode('|', array_map(fn($a) => preg_quote($a, '/'), $aliasse));
            $regex = '/^(?:' . $aliasPattern . ')\s*:\s*\n?([\s\S]*?)' . $grenze . '/mi';
            if (preg_match($regex, $notes, $m)) {
                $val = trim($m[1]);
                if ($val !== '') $ergebnis[$key] = $val;
            }
        }

        // Preis aus dem Header (z.B. „400 Euro" oder „400 €" in der dritten Zeile)
        if (preg_match('/(?:^|\n)\s*(\d+(?:[.,]\d+)?)\s*(?:Euro|EUR|€)\s*(?:\n|$)/i', $notes, $m)) {
            $preisStr = str_replace(['.', ','], ['', '.'], $m[1]); // „1.234,50" → „1234.50"
            $preis = (float) $preisStr;
            if ($preis > 0) $ergebnis['preis_kunde'] = $preis;
        }

        return $ergebnis;
    }

    public function asanaEntkoppleLinkoption(string $linkoptionId): void
    {
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege
             SET asana_task_gid = NULL, asana_task_cache = NULL, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [$linkoptionId]
        );
        $this->audit('linkoption.asana_entkoppelt', 'linkoption', $linkoptionId);
    }

    public function asanaAktualisiereLinkoption(string $linkoptionId): array
    {
        $e = $this->db->queryOne(
            "SELECT asana_task_gid FROM lam_vorschlagsliste_eintraege WHERE id = ?",
            [$linkoptionId]
        );
        if (!$e || empty($e['asana_task_gid'])) throw new \InvalidArgumentException('Keine Asana-Task verknüpft.');
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');
        $task = $svc->getTask($e['asana_task_gid']);
        if (!$task) throw new \RuntimeException('Asana-Task nicht gefunden.');
        $this->db->execute(
            "UPDATE lam_vorschlagsliste_eintraege
             SET asana_task_cache = ?, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [json_encode($task, JSON_UNESCAPED_UNICODE), $linkoptionId]
        );
        return $task;
    }

    public function asanaVerknuepfeMassnahme(string $massnahmeId, string $taskGid): array
    {
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        $task = $svc->getTask($taskGid);
        if (!$task) throw new \RuntimeException('Asana-Task nicht gefunden.');

        $this->db->execute(
            "UPDATE lam_massnahmen
             SET asana_task_gid = ?, asana_task_cache = ?, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [$taskGid, json_encode($task, JSON_UNESCAPED_UNICODE), $massnahmeId]
        );
        $this->audit('massnahme.asana_verknuepft', 'massnahme', $massnahmeId, ['task_gid' => $taskGid, 'task_name' => $task['name'] ?? '']);
        return $task;
    }

    public function asanaEntkoppleMassnahme(string $massnahmeId): void
    {
        $this->db->execute(
            "UPDATE lam_massnahmen
             SET asana_task_gid = NULL, asana_task_cache = NULL, asana_zuletzt_synchronisiert_am = NOW()
             WHERE id = ?",
            [$massnahmeId]
        );
        $this->audit('massnahme.asana_entkoppelt', 'massnahme', $massnahmeId);
    }

    public function asanaAktualisiereMassnahme(string $massnahmeId): array
    {
        $m = $this->db->queryOne(
            "SELECT asana_task_gid FROM lam_massnahmen WHERE id = ? AND geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m || empty($m['asana_task_gid'])) throw new \InvalidArgumentException('Keine Asana-Task verknüpft.');
        $svc = $this->asanaService();
        if (!$svc) throw new \RuntimeException('Asana ist nicht konfiguriert.');

        $task = $svc->getTask($m['asana_task_gid']);
        if (!$task) throw new \RuntimeException('Asana-Task nicht (mehr) auffindbar.');
        $this->db->execute(
            "UPDATE lam_massnahmen SET asana_task_cache = ?, asana_zuletzt_synchronisiert_am = NOW() WHERE id = ?",
            [json_encode($task, JSON_UNESCAPED_UNICODE), $massnahmeId]
        );
        return $task;
    }

    /**
     * Phase 1b: KI extrahiert aus der Asana-Task strukturierte LAM-Felder.
     * Returnt Vorschläge — die Übernahme passiert separat per asanaUebernehmeFelder().
     */
    public function asanaExtrahiereFelder(string $massnahmeId): array
    {
        $m = $this->db->queryOne(
            "SELECT asana_task_gid, asana_task_cache FROM lam_massnahmen WHERE id = ?",
            [$massnahmeId]
        );
        if (!$m || empty($m['asana_task_gid'])) throw new \InvalidArgumentException('Keine Asana-Task verknüpft.');

        // Frisch holen für aktuelle Daten
        $task = $this->asanaAktualisiereMassnahme($massnahmeId);

        $name = (string)($task['name'] ?? '');
        $notes = (string)($task['notes'] ?? '');
        $taskText = $name . "\n\n" . $notes;

        // Heuristik-Fallback ohne API-Key
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            return $this->asanaHeuristikExtraktion($taskText);
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(700);
        $ai->setTimeout(20);

        $system = "Du extrahierst aus einer deutschsprachigen Asana-Task strukturierte Daten für ein Linkaufbau-Tool.\n"
                . "Antworte ausschließlich mit JSON:\n"
                . "{\"linkquelle_url\":\"…\", \"anbieter_name\":\"…\", \"preis\":123.45, "
                . "\"linkziel_url\":\"…\", \"linktext\":\"…\", \"thema\":\"…\", "
                . "\"buchungstyp\":\"gastartikel|advertorial|pressemitteilung|interview|verzeichnis|startseite|null\", "
                . "\"geplant_am\":\"YYYY-MM-DD oder null\", \"notiz\":\"…\"}\n"
                . "Nicht erkennbare Felder leer/null. Keine Erfindungen.";
        $user = "ASANA-TASK:\n" . mb_substr($taskText, 0, 4000);

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $mm)) $content = $mm[0];
        $daten = json_decode($content, true);
        if (!is_array($daten)) throw new \RuntimeException('KI-Antwort ungültig.');
        return ['quelle' => 'ki', 'vorschlaege' => $daten];
    }

    private function asanaHeuristikExtraktion(string $text): array
    {
        $daten = [];
        // URLs
        if (preg_match_all('#https?://[^\s<>"\']+#i', $text, $m)) {
            $daten['linkquelle_url'] = $m[0][0] ?? null;
            $daten['linkziel_url'] = $m[0][1] ?? null;
        }
        // Preis
        if (preg_match('/(\d{1,5}(?:[.,]\d{2})?)\s*€/', $text, $m)) {
            $daten['preis'] = (float)str_replace(',', '.', $m[1]);
        }
        // Datum (TT.MM.JJJJ oder YYYY-MM-DD)
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
            $daten['geplant_am'] = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } elseif (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $text, $m)) {
            $j = (int)$m[3]; if ($j < 100) $j += 2000;
            $daten['geplant_am'] = sprintf('%04d-%02d-%02d', $j, $m[2], $m[1]);
        }
        return ['quelle' => 'heuristik', 'vorschlaege' => $daten];
    }

    /**
     * Phase 1b: KI-Vorschläge (oder vom User editiert) ins LAM übernehmen.
     * Nicht-leere Felder werden auf der Maßnahme gesetzt; existierende Werte
     * werden NICHT überschrieben (Schutzregel).
     */
    public function asanaUebernehmeFelder(string $massnahmeId, array $vorschlaege): array
    {
        $m = $this->db->queryOne(
            "SELECT id, linktext, geplant_am, veroeffentlichungs_url, buchungstyp
             FROM lam_massnahmen WHERE id = ? AND geloescht_am IS NULL",
            [$massnahmeId]
        );
        if (!$m) throw new \InvalidArgumentException('Maßnahme nicht gefunden.');

        $updates = [];
        $audit = [];
        $mapping = [
            'linktext' => 'linktext',
            'geplant_am' => 'geplant_am',
            'veroeffentlichungs_url' => 'linkquelle_url',  // Asana-Linkquelle = unsere Veröffentlichungs-URL
            'buchungstyp' => 'buchungstyp',
        ];
        foreach ($mapping as $dbFeld => $vorschlagFeld) {
            $wert = trim((string)($vorschlaege[$vorschlagFeld] ?? ''));
            if ($wert === '' || $wert === 'null') continue;
            if (!empty($m[$dbFeld])) continue; // bestehende Werte schützen
            if ($dbFeld === 'buchungstyp' && !in_array($wert, self::KONDITION_BUCHUNGSTYP, true)) continue;
            $updates[$dbFeld] = $wert;
            $audit[$dbFeld] = $wert;
        }
        if (!empty($updates)) {
            $sets = [];
            $params = [];
            foreach ($updates as $f => $w) { $sets[] = "`{$f}` = ?"; $params[] = $w; }
            $params[] = $massnahmeId;
            $this->db->execute(
                "UPDATE lam_massnahmen SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
        }
        $this->audit('massnahme.asana_uebernommen', 'massnahme', $massnahmeId, $audit);
        return ['uebernommen' => $updates, 'anzahl' => count($updates)];
    }

    /**
     * Asana-Konfiguration für einen Kunden setzen (Projekt + Section).
     */
    public function setzeAsanaKundenKonfig(int $customerId, ?string $projektGid, ?string $projektName, ?string $sectionGid, ?string $sectionName): void
    {
        $this->db->execute(
            "UPDATE customers
             SET asana_projekt_gid = ?, asana_projekt_name = ?,
                 asana_section_gid = ?, asana_section_name = ?
             WHERE id = ?",
            [$projektGid ?: null, $projektName ?: null, $sectionGid ?: null, $sectionName ?: null, $customerId]
        );
        $this->audit('kunde.asana_konfiguriert', 'kunde', (string)$customerId, [
            'projekt' => $projektName, 'section' => $sectionName,
        ]);
    }

    // ---------------------------------------------------------------------
    // Bulk-Erreichbarkeitsprüfen für Linkquellen
    // ---------------------------------------------------------------------

    /**
     * HTTP-Check für eine einzelne Domain. Schreibt letzter_http_status,
     * letzter_http_erreichbar, letzter_check_am.
     */
    public function pruefeDomainErreichbarkeit(string $domainId): array
    {
        $d = $this->db->queryOne(
            "SELECT id, url FROM lam_domains WHERE id = ? AND geloescht_am IS NULL",
            [$domainId]
        );
        if (!$d) throw new \InvalidArgumentException('Domain nicht gefunden.');

        $url = $d['url'];
        if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Thoxan-LAM-Reachability/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        @curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $erreichbar = ($status >= 200 && $status < 400) ? 1 : 0;
        $this->db->execute(
            "UPDATE lam_domains
             SET letzter_http_status = ?, letzter_http_erreichbar = ?, letzter_check_am = NOW()
             WHERE id = ?",
            [$status, $erreichbar, $domainId]
        );
        return [
            'domain_id' => $domainId,
            'http_status' => $status,
            'erreichbar' => (bool)$erreichbar,
            'fehler' => $err ?: null,
        ];
    }

    /**
     * Bulk-Erreichbarkeitsprüfung mit Rate-Limit (200ms zwischen Domains).
     */
    public function pruefeDomainErreichbarkeitBulk(array $domainIds): array
    {
        $ok = 0; $fehler = 0; $erreichbar = 0; $alerts = 0;
        foreach ($domainIds as $id) {
            try {
                $r = $this->pruefeDomainErreichbarkeit((string)$id);
                $ok++;
                if ($r['erreichbar']) $erreichbar++; else $alerts++;
            } catch (\Throwable $e) {
                $fehler++;
            }
            usleep(200_000);
        }
        $this->auditBulk('domain.erreichbarkeit_bulk', 'domain', $ok, [
            'erreichbar' => $erreichbar, 'nicht_erreichbar' => $alerts, 'fehler' => $fehler,
        ]);
        return ['ok' => $ok, 'erreichbar' => $erreichbar, 'nicht_erreichbar' => $alerts, 'fehler' => $fehler];
    }

    // ---------------------------------------------------------------------
    // Audit-Log (LAM-spezifisch)
    // ---------------------------------------------------------------------

    /**
     * Schreibt einen Audit-Eintrag. Fail-safe: Audit-Fehler werfen nichts.
     */
    public function audit(string $aktion, string $entityTyp, ?string $entityId = null, array $payload = []): void
    {
        try {
            $userId = $this->aktuellerUserId();
            $this->db->execute(
                "INSERT INTO lam_audit_logs (user_id, aktion, entity_typ, entity_id, payload, ist_bulk, anzahl_betroffen)
                 VALUES (?, ?, ?, ?, ?, 0, NULL)",
                [$userId, $aktion, $entityTyp, $entityId, !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]
            );
        } catch (\Throwable $e) { /* Audit-Log darf nie blockieren */ }
    }

    /**
     * Konsolidierter Audit-Eintrag für Bulk-Aktionen (1 Eintrag, nicht N).
     */
    public function auditBulk(string $aktion, string $entityTyp, int $anzahl, array $payload = []): void
    {
        try {
            $userId = $this->aktuellerUserId();
            $this->db->execute(
                "INSERT INTO lam_audit_logs (user_id, aktion, entity_typ, entity_id, payload, ist_bulk, anzahl_betroffen)
                 VALUES (?, ?, ?, NULL, ?, 1, ?)",
                [$userId, $aktion, $entityTyp, !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null, $anzahl]
            );
        } catch (\Throwable $e) { /* fail-safe */ }
    }

    private function aktuellerUserId(): ?int
    {
        try {
            $u = \Core\Auth::user();
            return $u['id'] ?? null;
        } catch (\Throwable $e) { return null; }
    }

    /**
     * Mandant des aktuell eingeloggten Users. Aktuell fix 'thoxan' (Single-Tenant),
     * vorbereitet für späteres Multi-Tenancy. Sobald ein zweiter Mandant existiert,
     * kann hier die Logik aus Session/User-Setting kommen.
     */
    public function aktuellerMandant(): string
    {
        return 'thoxan';
    }

    public function listeAuditEintraege(array $filter = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['entity_typ'])) {
            $where[] = 'al.entity_typ = ?';
            $params[] = $filter['entity_typ'];
        }
        if (!empty($filter['aktion'])) {
            $where[] = 'al.aktion LIKE ?';
            $params[] = '%' . $filter['aktion'] . '%';
        }
        if (!empty($filter['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int)$filter['user_id'];
        }
        if (!empty($filter['ab_datum'])) {
            $where[] = 'al.zeitpunkt >= ?';
            $params[] = $filter['ab_datum'];
        }
        if (!empty($filter['nur_bulk'])) {
            $where[] = 'al.ist_bulk = 1';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        return $this->db->query(
            "SELECT al.id, al.user_id, al.aktion, al.entity_typ, al.entity_id,
                    al.payload, al.ist_bulk, al.anzahl_betroffen, al.zeitpunkt,
                    u.name AS user_name, u.email AS user_email
             FROM lam_audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$whereSql}
             ORDER BY al.zeitpunkt DESC
             LIMIT 500",
            $params
        );
    }

    // ---------------------------------------------------------------------
    // KI: Cluster-Vorschlag, Recherche, Spalten-Mapping, Domain-Matching
    // ---------------------------------------------------------------------

    /**
     * KI schlägt passende Tags für eine Domain vor. Nutzt vorhandene Domain-URL +
     * existierende Wissensbasis-Notiz. Returnt eine Liste von Tag-Slugs, die im
     * System bereits existieren (KI darf keine neuen Tags erfinden).
     */
    public function kiSchlageTagsVor(string $domainId): array
    {
        $domain = $this->db->queryOne(
            "SELECT d.url, d.notizen, d.ki_kurzbeschreibung, dw.linkart, dw.thema, dw.notiz AS wissen_notiz
             FROM lam_domains d
             LEFT JOIN lam_domain_wissen dw ON dw.domain = SUBSTRING_INDEX(SUBSTRING_INDEX(d.url, '://', -1), '/', 1)
             WHERE d.id = ? AND d.geloescht_am IS NULL",
            [$domainId]
        );
        if (!$domain) throw new \InvalidArgumentException('Domain nicht gefunden.');

        $alleTags = $this->db->query(
            "SELECT slug, name, beschreibung FROM lam_tags WHERE geloescht_am IS NULL ORDER BY name"
        );
        if (empty($alleTags)) throw new \RuntimeException('Keine Tags angelegt — bitte zuerst Tags unter /lam/tags pflegen.');

        $tagListe = array_map(fn($t) => $t['slug'] . ' (' . $t['name'] . ($t['beschreibung'] ? ': ' . $t['beschreibung'] : '') . ')', $alleTags);

        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key fehlt.');
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(300);
        $ai->setTimeout(20);

        $system = "Du klassifizierst eine Backlink-Quell-Domain in vorhandene Themen-Tags.\n"
                . "Antworte AUSSCHLIESSLICH mit gültigem JSON: {\"tags\":[\"slug1\",\"slug2\"], \"begruendung\":\"max 200 Z.\"}.\n"
                . "Verfügbare Tag-Slugs (NUR diese sind erlaubt): " . implode(', ', array_map(fn($t) => $t['slug'], $alleTags)) . "\n"
                . "Wähle 0–3 passende Tags. Bei Unsicherheit lieber wenig als zu viel.";

        $user = "Domain: {$domain['url']}\n";
        if (!empty($domain['notizen'])) $user .= "Notiz: " . mb_substr($domain['notizen'], 0, 500) . "\n";
        if (!empty($domain['ki_kurzbeschreibung'])) $user .= "KI-Kurzbeschreibung: " . mb_substr($domain['ki_kurzbeschreibung'], 0, 500) . "\n";
        if (!empty($domain['thema'])) $user .= "Bekanntes Thema: " . $domain['thema'] . "\n";
        if (!empty($domain['wissen_notiz'])) $user .= "Wissensbasis: " . mb_substr($domain['wissen_notiz'], 0, 300) . "\n";
        $user .= "\nÜbersicht der Tags zur Auswahl:\n" . implode("\n", array_slice($tagListe, 0, 80));

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten) || !isset($daten['tags'])) {
            throw new \RuntimeException('KI-Antwort ungültig: ' . mb_substr($content, 0, 200));
        }

        $erlaubt = array_column($alleTags, 'slug');
        $vorschlaege = array_values(array_filter((array)$daten['tags'], fn($t) => in_array($t, $erlaubt, true)));
        return [
            'tag_slugs' => $vorschlaege,
            'begruendung' => $daten['begruendung'] ?? '',
        ];
    }

    /**
     * KI-Recherche zu einer Domain: Eigentümer/Impressum/Themenschwerpunkt erschließen.
     * Crawlt die Domain-Startseite + Impressum (best-effort) und schickt das an Claude.
     */
    public function kiRecherchierDomain(string $domainId): array
    {
        $domain = $this->db->queryOne(
            "SELECT id, url, impressum_url FROM lam_domains WHERE id = ? AND geloescht_am IS NULL",
            [$domainId]
        );
        if (!$domain) throw new \InvalidArgumentException('Domain nicht gefunden.');

        $url = $domain['url'];
        if (!preg_match('#^https?://#', $url)) $url = 'https://' . $url;
        $startseite = $this->crawleSeitenAusschnitt($url);

        // Versuche Impressum
        $impressum = '';
        $impressumUrl = $domain['impressum_url'] ?: null;
        if (!$impressumUrl) {
            foreach (['/impressum', '/impressum.html', '/impressum/', '/imprint'] as $pfad) {
                $kandidat = rtrim($url, '/') . $pfad;
                $test = $this->crawleSeitenAusschnitt($kandidat);
                if ($test) { $impressum = $test; $impressumUrl = $kandidat; break; }
            }
        } else {
            $impressum = $this->crawleSeitenAusschnitt($impressumUrl);
        }

        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key fehlt.');
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(700);
        $ai->setTimeout(30);

        $system = "Du recherchierst Hintergrundinformationen zu einer Website für ein Linkaufbau-Tool.\n"
                . "Antworte ausschließlich mit JSON:\n"
                . "{\"betreiber\":\"…\", \"rechtsform\":\"GmbH|UG|Einzelperson|unklar\", "
                . "\"themenschwerpunkt\":\"…\", \"zielgruppe\":\"…\", \"redaktionell\":true/false, "
                . "\"kommerziell\":true/false, \"kurzbeschreibung\":\"max 300 Z. deutsch\"}";
        $user = "URL: {$domain['url']}\n\n";
        if ($startseite) $user .= "STARTSEITE (Klartext, gekürzt):\n" . mb_substr($startseite, 0, 3000) . "\n\n";
        if ($impressum)  $user .= "IMPRESSUM (Klartext, gekürzt):\n" . mb_substr($impressum, 0, 2000) . "\n";

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten)) throw new \RuntimeException('KI-Antwort ungültig.');

        // Notiz + Kurzbeschreibung in Domain speichern (Verkettung mit „[KI-Recherche YYYY-MM-DD]…")
        $marker = '[KI-Recherche ' . date('Y-m-d') . ']';
        $neueBeschreibung = ($daten['kurzbeschreibung'] ?? '');
        if ($neueBeschreibung) {
            $this->db->execute(
                "UPDATE lam_domains
                 SET ki_kurzbeschreibung = ?, ki_kurzbeschreibung_generiert_am = NOW()
                 WHERE id = ?",
                [$neueBeschreibung, $domainId]
            );
        }
        if ($impressumUrl && empty($domain['impressum_url'])) {
            $this->db->execute(
                "UPDATE lam_domains SET impressum_url = ? WHERE id = ?",
                [$impressumUrl, $domainId]
            );
        }
        $daten['marker'] = $marker;
        $daten['impressum_url'] = $impressumUrl;
        return $daten;
    }

    /**
     * KI-Spalten-Mapping für Excel-/CSV-Import. Nimmt die ersten Header + Beispielwerte
     * und schlägt ein Mapping auf Ziel-Felder vor.
     */
    public function kiSpaltenMapping(string $ziel, array $spalten): array
    {
        $zielFelder = [
            'massnahmen' => ['customer','domain','vorgangstyp','status','geplant_am','veroeffentlicht_am','veroeffentlichungs_url','linktext'],
            'auslagen' => ['massnahme','externe_kosten','weiterverrechnet','rechnung_nr','rechnung_datum','sonderfall'],
            'korrespondenz' => ['anbieter','zeitpunkt','typ','betreff','inhalt'],
            'linkprofil' => ['customer','verlinkende_url','ziel_url','linktext','linkart','empfehlung'],
        ];
        if (!isset($zielFelder[$ziel])) throw new \InvalidArgumentException('Unbekanntes Ziel.');
        $felder = $zielFelder[$ziel];

        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            // Heuristik-Fallback ohne API-Key
            $mapping = [];
            foreach ($spalten as $idx => $sp) {
                $name = mb_strtolower($sp['name'] ?? '');
                foreach ($felder as $f) {
                    if (str_contains($name, $f) || str_contains($f, $name)) {
                        $mapping[$idx] = $f;
                        break;
                    }
                }
            }
            return ['mapping' => $mapping, 'quelle' => 'heuristik'];
        }
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(800);
        $ai->setTimeout(20);

        $spaltenBeschr = [];
        foreach ($spalten as $idx => $sp) {
            $spaltenBeschr[] = "Spalte {$idx} \"{$sp['name']}\" (Beispiel: " . mb_substr($sp['beispiel'] ?? '', 0, 60) . ")";
        }
        $system = "Du mappst CSV-Spalten auf vorgegebene Ziel-Felder.\n"
                . "Antworte AUSSCHLIESSLICH mit JSON: {\"mapping\":{\"0\":\"feld1\",\"1\":\"feld2\"}}.\n"
                . "Erlaubte Ziel-Feld-Slugs: " . implode(', ', $felder) . ".\n"
                . "Nutze leeren String für Spalten, die NICHT gemappt werden sollen. Keine Erfindungen.";
        $user = "Ziel-Tabelle: {$ziel}\n\nVerfügbare Spalten:\n" . implode("\n", $spaltenBeschr);

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten) || !isset($daten['mapping'])) {
            throw new \RuntimeException('KI-Antwort ungültig.');
        }
        $bereinigt = [];
        foreach ((array)$daten['mapping'] as $idx => $feld) {
            if (in_array($feld, $felder, true)) $bereinigt[(int)$idx] = $feld;
        }
        return ['mapping' => $bereinigt, 'quelle' => 'ki'];
    }

    /**
     * KI-Domain-Matching: Schlägt aus dem Linkquellen-Pool die Top-N Domains für einen Kunden vor.
     * Basis: Kunden-Tags + Domain-Tags + (falls vorhanden) Linkziele/Themen.
     */
    public function kiSchlageDomainsVor(int $customerId, int $anzahl = 10): array
    {
        $kunde = $this->db->queryOne(
            "SELECT id, name, abbreviation FROM customers WHERE id = ?",
            [$customerId]
        );
        if (!$kunde) throw new \InvalidArgumentException('Kunde nicht gefunden.');

        // Linkziele + Themen des Kunden
        $linkziele = $this->db->query(
            "SELECT thema, url FROM lam_linkziele WHERE customer_id = ? AND status = 'aktiv'
             ORDER BY thema LIMIT 30",
            [$customerId]
        );

        // Kandidaten-Domains: in Pool aktiv, ohne Maßnahme für diesen Kunden
        $kandidaten = $this->db->query(
            "SELECT d.id, d.url,
                    (SELECT GROUP_CONCAT(t.slug SEPARATOR ',')
                       FROM lam_domain_tag dt JOIN lam_tags t ON t.id = dt.tag_id
                       WHERE dt.domain_id = d.id AND t.geloescht_am IS NULL) AS tags,
                    (SELECT si FROM lam_kennzahl_snapshots ks WHERE ks.domain_id = d.id
                       ORDER BY ks.erfasst_am DESC LIMIT 1) AS si
             FROM lam_domains d
             WHERE d.geloescht_am IS NULL
               AND d.disqualifiziert = 0
               AND d.verifikation_status IN ('verifiziert','geprueft')
               AND NOT EXISTS (
                   SELECT 1 FROM lam_massnahmen m
                   WHERE m.domain_id = d.id AND m.customer_id = ? AND m.geloescht_am IS NULL
               )
             ORDER BY d.erstellt_am DESC
             LIMIT 200",
            [$customerId]
        );
        if (empty($kandidaten)) return ['vorschlaege' => [], 'hinweis' => 'Keine offenen Kandidaten gefunden.'];

        // Wenn Pool zu groß: rein heuristisch filtern (Tag-Match) — KI nur für Final-Sortierung
        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            // Fallback: nach SI sortieren
            usort($kandidaten, fn($a, $b) => (int)($b['si'] ?? 0) - (int)($a['si'] ?? 0));
            return [
                'vorschlaege' => array_slice($kandidaten, 0, $anzahl),
                'quelle' => 'heuristik',
            ];
        }
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(800);
        $ai->setTimeout(30);

        $kundenProfil = "Kunde: {$kunde['name']} ({$kunde['abbreviation']})\n";
        $kundenProfil .= "Linkziele:\n";
        foreach ($linkziele as $lz) $kundenProfil .= "- {$lz['thema']}: {$lz['url']}\n";

        $domainListe = [];
        foreach (array_slice($kandidaten, 0, 80) as $k) {
            $domainListe[] = "{$k['id']} | {$k['url']} | tags=" . ($k['tags'] ?? '—') . " | SI=" . ($k['si'] ?? '—');
        }

        $system = "Du schlägst aus einem Linkquellen-Pool Domains für einen Kunden vor.\n"
                . "Bewerte thematische Nähe und Linkstärke. Antworte JSON:\n"
                . "{\"ranking\":[{\"id\":\"…\",\"score\":0-100,\"grund\":\"…\"}]}";
        $user = $kundenProfil . "\nKandidaten:\n" . implode("\n", $domainListe)
              . "\n\nTop {$anzahl} ranken.";

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten) || !isset($daten['ranking'])) {
            throw new \RuntimeException('KI-Antwort ungültig.');
        }
        // Mit Detail-Daten anreichern
        $byId = [];
        foreach ($kandidaten as $k) $byId[$k['id']] = $k;
        $vorschlaege = [];
        foreach ((array)$daten['ranking'] as $r) {
            $id = $r['id'] ?? null;
            if (!$id || !isset($byId[$id])) continue;
            $vorschlaege[] = array_merge($byId[$id], [
                'score' => (int)($r['score'] ?? 0),
                'grund' => (string)($r['grund'] ?? ''),
            ]);
        }
        return ['vorschlaege' => array_slice($vorschlaege, 0, $anzahl), 'quelle' => 'ki'];
    }

    /**
     * Anbieter-aus-Impressum-Crawler.
     * Crawlt das Impressum der Domain, lässt die KI strukturierte Anbieter-Daten
     * extrahieren (Firma, Adresse, E-Mail, Telefon, Geschäftsführer), legt einen
     * Anbieter-Datensatz an (oder hängt an einen vorhandenen) und verknüpft die Domain.
     * Returnt: { anbieter_id, anbieter_neu, daten }
     */
    public function crawleAnbieterAusImpressum(string $domainId): array
    {
        $d = $this->db->queryOne(
            "SELECT id, url, impressum_url, anbieter_id FROM lam_domains WHERE id = ? AND geloescht_am IS NULL",
            [$domainId]
        );
        if (!$d) throw new \InvalidArgumentException('Domain nicht gefunden.');

        // Impressum crawlen (probiert mehrere Pfade, falls keiner gespeichert)
        $impressumUrl = $d['impressum_url'];
        $impressum = '';
        if ($impressumUrl) {
            $impressum = $this->crawleSeitenAusschnitt($impressumUrl);
        }
        if (!$impressum) {
            $basis = preg_match('#^https?://#', $d['url']) ? $d['url'] : 'https://' . $d['url'];
            foreach (['/impressum', '/impressum/', '/impressum.html', '/imprint', '/about-us'] as $pfad) {
                $k = rtrim($basis, '/') . $pfad;
                $t = $this->crawleSeitenAusschnitt($k);
                if ($t && stripos($t, 'impressum') !== false || stripos($t, 'imprint') !== false) {
                    $impressum = $t;
                    $impressumUrl = $k;
                    break;
                }
            }
        }
        if (!$impressum) throw new \RuntimeException('Kein Impressum gefunden.');

        // KI strukturieren
        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key fehlt.');
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(600);
        $ai->setTimeout(25);

        $system = "Du extrahierst aus einem deutschsprachigen Impressums-Text die Stammdaten des Betreibers.\n"
                . "Wichtig: identifiziere den primären menschlichen Ansprechpartner (Geschäftsführer, Inhaber, "
                . "Verantwortlich nach §55 RStV/§18 MStV, oder eindeutig benannter Hauptkontakt) — Vorname und Nachname separat.\n"
                . "Antworte ausschließlich mit JSON:\n"
                . "{\"firma\":\"…\", \"rechtsform\":\"GmbH|UG|GbR|Einzelperson|…\", "
                . "\"ansprechpartner_vorname\":\"…\", \"ansprechpartner_nachname\":\"…\", \"ansprechpartner_rolle\":\"Geschäftsführer|Inhaber|Redaktion|…\", "
                . "\"strasse\":\"…\", \"plz\":\"…\", \"ort\":\"…\", \"land\":\"DE\","
                . "\"email\":\"…\", \"telefon\":\"…\", "
                . "\"geschaeftsfuehrer\":\"…\", \"ust_id\":\"…\","
                . "\"konfidenz\":\"hoch|mittel|niedrig\"}\n"
                . "Felder, die nicht erkennbar sind, leer lassen. Keine Erfindungen.";
        $user = "URL: {$d['url']}\nIMPRESSUM (gekürzt):\n" . mb_substr($impressum, 0, 5000);

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten) || empty($daten['firma'])) {
            throw new \RuntimeException('KI konnte keine Firma extrahieren.');
        }

        // Adress-String zusammenbauen
        $adresse = trim(implode(', ', array_filter([
            $daten['strasse'] ?? '',
            trim(($daten['plz'] ?? '') . ' ' . ($daten['ort'] ?? '')),
            $daten['land'] ?? '',
        ])));

        $notiz = '[KI-Impressum ' . date('Y-m-d') . '] ' . trim(implode("\n", array_filter([
            $adresse,
            !empty($daten['email']) ? 'Mail: ' . $daten['email'] : '',
            !empty($daten['telefon']) ? 'Tel: ' . $daten['telefon'] : '',
            !empty($daten['geschaeftsfuehrer']) ? 'GF: ' . $daten['geschaeftsfuehrer'] : '',
            !empty($daten['ust_id']) ? 'USt-ID: ' . $daten['ust_id'] : '',
        ])));

        // Ansprechpartner aus KI-Antwort priorisieren — fallback auf geschaeftsfuehrer-Feld
        $vorname  = trim((string) ($daten['ansprechpartner_vorname'] ?? ''));
        $nachname = trim((string) ($daten['ansprechpartner_nachname'] ?? ''));
        if ($nachname === '' && !empty($daten['geschaeftsfuehrer'])) {
            $gf = trim((string) $daten['geschaeftsfuehrer']);
            if (strpos($gf, ' ') !== false) { [$vorname, $nachname] = explode(' ', $gf, 2); }
            else { $nachname = $gf; }
        }
        $ansprechpartnerVoll = trim($vorname . ' ' . $nachname);

        // Anbieter-Name = Person (Hauptansprechpartner), Firma = Firmierung (separat)
        $anbieterName = $ansprechpartnerVoll !== '' ? $ansprechpartnerVoll : $daten['firma'];

        // Dublette? Gleiche Firma ODER gleiche E-Mail-Domain als Identifikation
        $bestehender = null;
        if ($ansprechpartnerVoll !== '') {
            $bestehender = $this->db->queryValue(
                "SELECT a.id FROM lam_anbieter a
                 LEFT JOIN lam_kontakte k ON k.anbieter_id = a.id AND k.geloescht_am IS NULL
                 WHERE a.geloescht_am IS NULL
                   AND (LOWER(a.firma) = LOWER(?) OR (LOWER(TRIM(CONCAT(COALESCE(k.vorname,''),' ',COALESCE(k.nachname,'')))) = LOWER(?)))
                 LIMIT 1",
                [$daten['firma'], $ansprechpartnerVoll]
            );
        }
        if (!$bestehender) {
            $bestehender = $this->db->queryValue(
                "SELECT id FROM lam_anbieter WHERE LOWER(firma) = LOWER(?) AND geloescht_am IS NULL LIMIT 1",
                [$daten['firma']]
            );
        }

        $anbieterNeu = false;
        if ($bestehender) {
            $anbieterId = $bestehender;
            // Wenn wir eine Person identifiziert haben UND der bestehende Anbieter-Name
            // aktuell „nur" die Firma ist (oder leer), dann auf die Person umstellen:
            // Anbieter heißt nach Mensch, Firma bleibt als Firmierung erhalten.
            $aktuellerName = (string) $this->db->queryValue(
                "SELECT name FROM lam_anbieter WHERE id = ?", [$anbieterId]
            );
            $aktuelleFirma = (string) $this->db->queryValue(
                "SELECT firma FROM lam_anbieter WHERE id = ?", [$anbieterId]
            );
            if ($ansprechpartnerVoll !== '' && $aktuellerName !== $ansprechpartnerVoll) {
                $nameIstFirma = $aktuellerName === '' || strcasecmp($aktuellerName, $aktuelleFirma) === 0 || strcasecmp($aktuellerName, $daten['firma'] ?? '') === 0;
                if ($nameIstFirma) {
                    $this->db->execute(
                        "UPDATE lam_anbieter SET name = ?, firma = COALESCE(NULLIF(firma, ''), ?) WHERE id = ?",
                        [$ansprechpartnerVoll, $daten['firma'] ?? null, $anbieterId]
                    );
                }
            }
            // Firma nachziehen falls bisher leer
            if (empty($aktuelleFirma) && !empty($daten['firma'])) {
                $this->db->execute("UPDATE lam_anbieter SET firma = ? WHERE id = ?", [$daten['firma'], $anbieterId]);
            }
            $this->db->execute(
                "UPDATE lam_anbieter SET notizen = CONCAT(COALESCE(notizen, ''), '\n\n', ?),
                                         ist_betreiber = 1
                 WHERE id = ?",
                [$notiz, $anbieterId]
            );
        } else {
            $anbieterId = $this->ulid();
            $this->db->execute(
                "INSERT INTO lam_anbieter (id, name, firma, beziehungsstatus, ist_betreiber, ist_vermittler, notizen)
                 VALUES (?, ?, ?, 'neu', 1, 0, ?)",
                [$anbieterId, $anbieterName, $daten['firma'] ?? null, $notiz]
            );
            $anbieterNeu = true;
        }

        // Kontakt anlegen falls (a) Person aus Impressum + (b) noch nicht da
        if ($nachname !== '') {
            $vorhanden = $this->db->queryValue(
                "SELECT id FROM lam_kontakte
                 WHERE anbieter_id = ? AND geloescht_am IS NULL
                   AND LOWER(COALESCE(nachname, '')) = LOWER(?) LIMIT 1",
                [$anbieterId, $nachname]
            );
            if (!$vorhanden) {
                $this->db->execute(
                    "INSERT INTO lam_kontakte
                        (id, anbieter_id, vorname, nachname, email, telefon, rolle, verifikation_status, prioritaet)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'neu', 1)",
                    [$this->ulid(), $anbieterId, $vorname ?: null, $nachname,
                     $daten['email'] ?? null, $daten['telefon'] ?? null,
                     $daten['ansprechpartner_rolle'] ?? null]
                );
            }
            // Tom-Regel: Anbieter = Mensch. Falls Anbieter-Name nach KI-Antwort firmenartig
            //   (oder identisch zur Firma) ist, Namen jetzt auf den Person-Kontakt ziehen.
            $this->ziehePersonAnbieterNamenNach($anbieterId);
        }

        // Junction-Eintrag: Impressum-Anbieter ist immer Betreiber
        $junctionVorhanden = $this->db->queryValue(
            "SELECT id FROM lam_domain_anbieter WHERE domain_id = ? AND anbieter_id = ?",
            [$domainId, $anbieterId]
        );
        if (!$junctionVorhanden) {
            $this->db->execute(
                "INSERT INTO lam_domain_anbieter (id, domain_id, anbieter_id, rolle)
                 VALUES (?, ?, ?, 'betreiber')",
                [$this->ulid(), $domainId, $anbieterId]
            );
        } else {
            $this->db->execute(
                "UPDATE lam_domain_anbieter SET rolle = 'betreiber' WHERE id = ?",
                [$junctionVorhanden]
            );
        }

        // An Domain auch direkt anhängen (Convenience für die Übersicht)
        if (empty($d['anbieter_id'])) {
            $this->db->execute(
                "UPDATE lam_domains SET anbieter_id = ?, impressum_url = COALESCE(impressum_url, ?) WHERE id = ?",
                [$anbieterId, $impressumUrl, $domainId]
            );
        } else {
            $this->db->execute(
                "UPDATE lam_domains SET impressum_url = COALESCE(impressum_url, ?) WHERE id = ?",
                [$impressumUrl, $domainId]
            );
        }

        $this->audit('domain.impressum_crawl', 'domain', $domainId, [
            'anbieter_id' => $anbieterId,
            'anbieter_neu' => $anbieterNeu,
            'firma' => $daten['firma'],
        ]);

        return [
            'anbieter_id' => $anbieterId,
            'anbieter_neu' => $anbieterNeu,
            'impressum_url' => $impressumUrl,
            'daten' => $daten,
        ];
    }

    // ---------------------------------------------------------------------
    // Sitewide-Cluster-Detection (Aufräum-Modus)
    // ---------------------------------------------------------------------

    /**
     * Findet Cluster: gleiche Quell-Domain + gleicher Kunde mit ≥ Schwelle Verlinkungen.
     * Default-Schwelle: 5.
     */
    public function findeSitewideCluster(?int $customerId = null, int $schwelle = 5): array
    {
        $where = ['v.geloescht_am IS NULL'];
        $params = [];
        if ($customerId) {
            $where[] = 'v.customer_id = ?';
            $params[] = $customerId;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $params[] = $schwelle;

        return $this->db->query(
            "SELECT v.customer_id, v.domain,
                    COUNT(*) AS anzahl,
                    SUM(CASE WHEN v.is_follow = 1 THEN 1 ELSE 0 END) AS follow_anzahl,
                    SUM(CASE WHEN v.empfehlung IN ('abbauen','nofollow_setzen','disavow','loeschen') THEN 1 ELSE 0 END) AS bereits_markiert,
                    SUM(CASE WHEN v.letzter_http_erreichbar = 1 THEN 1 ELSE 0 END) AS erreichbar_anzahl,
                    SUM(CASE WHEN v.letzter_http_erreichbar = 0 THEN 1 ELSE 0 END) AS tot_anzahl,
                    SUM(CASE WHEN v.letzter_http_erreichbar IS NULL THEN 1 ELSE 0 END) AS ungeprueft_anzahl,
                    MIN(v.erstellt_am) AS erstmals_gesehen,
                    MAX(v.erstellt_am) AS letztmals_gesehen,
                    c.name AS customer_name, c.abbreviation AS customer_kuerzel
             FROM lam_verlinkungen v
             LEFT JOIN customers c ON c.id = v.customer_id
             {$whereSql}
             GROUP BY v.customer_id, v.domain
             HAVING COUNT(*) >= ?
             ORDER BY anzahl DESC, v.domain ASC
             LIMIT 200",
            $params
        );
    }

    /**
     * Liefert die einzelnen Verlinkungen eines Clusters.
     * Sortier-Priorisierung: erreichbar (1) zuerst, dann ungeprueft (NULL), dann tot (0).
     */
    public function getClusterDetails(int $customerId, string $domain): array
    {
        return $this->db->query(
            "SELECT id, verlinkende_url, linktext, linkart, empfehlung, is_follow, status,
                    letzter_http_erreichbar, letzter_http_status, letzter_check_am,
                    ki_confidence, ki_klassifiziert_am
             FROM lam_verlinkungen
             WHERE customer_id = ? AND domain = ? AND geloescht_am IS NULL
             -- Erreichbare zuerst, ungeprueft in der Mitte, tote zuletzt
             ORDER BY (letzter_http_erreichbar = 1) DESC,
                      (letzter_http_erreichbar IS NULL) DESC,
                      verlinkende_url ASC
             LIMIT 500",
            [$customerId, $domain]
        );
    }

    /**
     * Setzt eine Empfehlung selektiv auf alle Verlinkungen eines Clusters,
     * gefiltert nach Erreichbarkeits-Status. Erlaubte $filter-Werte:
     *   'tot'        — nur Verlinkungen mit letzter_http_erreichbar = 0
     *   'erreichbar' — nur 1
     *   'ungeprueft' — nur NULL
     *   'alle'       — wie setzeClusterEmpfehlung()
     */
    public function setzeClusterEmpfehlungSelektiv(int $customerId, string $domain, string $empfehlung, string $filter = 'alle'): int
    {
        if (!in_array($empfehlung, self::VERLINKUNG_EMPFEHLUNG, true)) {
            throw new \InvalidArgumentException('Ungültige Empfehlung.');
        }
        $whereExtra = '';
        switch ($filter) {
            case 'tot':        $whereExtra = ' AND letzter_http_erreichbar = 0'; break;
            case 'erreichbar': $whereExtra = ' AND letzter_http_erreichbar = 1'; break;
            case 'ungeprueft': $whereExtra = ' AND letzter_http_erreichbar IS NULL'; break;
            case 'alle':       $whereExtra = ''; break;
            default: throw new \InvalidArgumentException('Ungültiger Filter.');
        }
        $betroffen = $this->db->execute(
            "UPDATE lam_verlinkungen SET empfehlung = ?, aktualisiert_am = NOW()
             WHERE customer_id = ? AND domain = ? AND geloescht_am IS NULL{$whereExtra}",
            [$empfehlung, $customerId, $domain]
        );
        $this->auditBulk('cluster.empfehlung_selektiv', 'verlinkung', $betroffen, [
            'customer_id' => $customerId, 'domain' => $domain,
            'empfehlung'  => $empfehlung, 'filter' => $filter,
        ]);
        return $betroffen;
    }

    /**
     * Bulk-Setzen einer Empfehlung auf alle Verlinkungen eines Clusters.
     */
    public function setzeClusterEmpfehlung(int $customerId, string $domain, string $empfehlung): int
    {
        if (!in_array($empfehlung, self::VERLINKUNG_EMPFEHLUNG, true)) {
            throw new \InvalidArgumentException('Ungültige Empfehlung.');
        }
        $betroffen = $this->db->execute(
            "UPDATE lam_verlinkungen SET empfehlung = ?, aktualisiert_am = NOW()
             WHERE customer_id = ? AND domain = ? AND geloescht_am IS NULL",
            [$empfehlung, $customerId, $domain]
        );
        $this->auditBulk('cluster.empfehlung_setzen', 'verlinkung', $betroffen, [
            'customer_id' => $customerId, 'domain' => $domain, 'empfehlung' => $empfehlung,
        ]);
        return $betroffen;
    }

    // ---------------------------------------------------------------------
    // Historien-Import (Schreib-Pfade für alle vier Ziele)
    // ---------------------------------------------------------------------

    /**
     * Schreibt eine via Mapping-UI vorbereitete Zeilenliste in die jeweilige Ziel-Tabelle.
     * $ziel: 'massnahmen'|'auslagen'|'korrespondenz'|'linkprofil'
     * $zeilen: array von assoz. Arrays mit Zielfeld-Keys (wie von der UI berechnet)
     * Returns: { ok, fehler, fehler_liste }
     */
    public function importiereHistorie(string $ziel, array $zeilen, ?int $customerId = null): array
    {
        $methode = 'importiereHistorie' . ucfirst($ziel);
        if (!method_exists($this, $methode)) {
            throw new \InvalidArgumentException("Unbekanntes Ziel: {$ziel}");
        }
        $ok = 0; $fehler = 0; $fehlerListe = [];
        foreach ($zeilen as $i => $z) {
            try {
                $this->$methode($z, $customerId);
                $ok++;
            } catch (\Throwable $e) {
                $fehler++;
                $fehlerListe[] = "Zeile " . ($i + 1) . ": " . $e->getMessage();
            }
        }
        return ['ok' => $ok, 'fehler' => $fehler, 'fehler_liste' => array_slice($fehlerListe, 0, 50)];
    }

    private function importiereHistorieMassnahmen(array $z, ?int $customerId): void
    {
        $custId = $customerId ?? $this->customerIdAusKuerzel((string)($z['customer'] ?? ''));
        if (!$custId) throw new \RuntimeException('Kunde nicht erkannt: ' . ($z['customer'] ?? ''));
        $domainId = $this->domainIdAusUrl((string)($z['domain'] ?? ''));
        if (!$domainId) throw new \RuntimeException('Domain nicht gefunden: ' . ($z['domain'] ?? ''));

        $status = in_array($z['status'] ?? '', self::MASSNAHME_STATUS, true) ? $z['status'] : 'live';

        $this->speichereMassnahme(null, [
            'customer_id' => $custId,
            'domain_id' => $domainId,
            'vorgangstyp' => $z['vorgangstyp'] ?? 'erstveroeffentlichung',
            'status' => $status,
            'geplant_am' => $this->parseDate($z['geplant_am'] ?? ''),
            'veroeffentlicht_am' => $this->parseDate($z['veroeffentlicht_am'] ?? ''),
            'veroeffentlichungs_url' => $z['veroeffentlichungs_url'] ?? null,
            'linktext' => $z['linktext'] ?? null,
        ]);
    }

    private function importiereHistorieAuslagen(array $z, ?int $customerId): void
    {
        // Massnahme via Domain finden (oder neu erkennbar machen)
        $massnahmeId = $this->db->queryValue(
            "SELECT m.id FROM lam_massnahmen m
             JOIN lam_domains d ON d.id = m.domain_id
             WHERE d.url LIKE ? AND m.geloescht_am IS NULL
             ORDER BY m.erstellt_am DESC LIMIT 1",
            ['%' . ($z['massnahme'] ?? '') . '%']
        );
        if (!$massnahmeId) throw new \RuntimeException('Maßnahme nicht gefunden: ' . ($z['massnahme'] ?? ''));

        $externeKosten = $this->parseFloat($z['externe_kosten'] ?? '');
        if ($externeKosten === null) throw new \RuntimeException('Externe Kosten ungültig: ' . ($z['externe_kosten'] ?? ''));

        $weiterverr = $this->parseFloat($z['weiterverrechnet'] ?? '') ?? 0;
        $marge = $weiterverr - $externeKosten;

        $this->db->execute(
            "INSERT INTO lam_auslagen
                (id, massnahme_id, externe_kosten, weiterverrechnet, marge,
                 thoxan_rechnung_nr, thoxan_rechnung_datum, sonderfall, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $this->ulid(), $massnahmeId, $externeKosten, $weiterverr, $marge,
                $z['rechnung_nr'] ?? null,
                $this->parseDate($z['rechnung_datum'] ?? ''),
                $z['sonderfall'] ?? 'normal',
            ]
        );
    }

    private function importiereHistorieKorrespondenz(array $z, ?int $customerId): void
    {
        $anbieterId = null;
        if (!empty($z['anbieter'])) {
            $anbieterId = $this->db->queryValue(
                "SELECT id FROM lam_anbieter WHERE (name LIKE ? OR firma LIKE ?) AND geloescht_am IS NULL LIMIT 1",
                ['%' . $z['anbieter'] . '%', '%' . $z['anbieter'] . '%']
            );
        }

        $typ = in_array($z['typ'] ?? '', ['mail_eingang','mail_ausgang','telefon','notiz','sonstiges'], true)
             ? $z['typ'] : 'notiz';

        $this->db->execute(
            "INSERT INTO lam_kommunikation
                (id, anbieter_id, typ, zeitpunkt, betreff, inhalt, status, erstellt_am)
             VALUES (?, ?, ?, ?, ?, ?, 'historisch', NOW())",
            [
                $this->ulid(), $anbieterId, $typ,
                $this->parseDateTime($z['zeitpunkt'] ?? '') ?: date('Y-m-d H:i:s'),
                $z['betreff'] ?? null,
                $z['inhalt'] ?? null,
            ]
        );
    }

    private function importiereHistorieLinkprofil(array $z, ?int $customerId): void
    {
        $custId = $customerId ?? $this->customerIdAusKuerzel((string)($z['customer'] ?? ''));
        if (!$custId) throw new \RuntimeException('Kunde nicht erkannt: ' . ($z['customer'] ?? ''));
        $verlinkendeUrl = trim((string)($z['verlinkende_url'] ?? ''));
        if ($verlinkendeUrl === '') throw new \RuntimeException('Verlinkende URL fehlt.');
        $domain = parse_url($verlinkendeUrl, PHP_URL_HOST) ?: '';
        $hash = sha1($verlinkendeUrl . '|' . ($z['ziel_url'] ?? ''));

        // Altes Vokabular → neues Vokabular mappen
        $linkart    = $this->mappeAltesLinkartLabel((string)($z['linkart'] ?? ''));
        $empfehlung = $this->mappeAlteEmpfehlung((string)($z['empfehlung'] ?? ''));

        $existiert = $this->db->queryValue(
            "SELECT id FROM lam_verlinkungen WHERE customer_id = ? AND url_hash = ? LIMIT 1",
            [$custId, $hash]
        );
        if (!$existiert) {
            $this->db->execute(
                "INSERT INTO lam_verlinkungen
                    (id, customer_id, verlinkende_url, url_hash, domain, linktext, linkart, empfehlung, ziel_url,
                     ist_neu, imported_from, erstellt_am)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'historien_import', NOW())",
                [
                    $this->ulid(), $custId, $verlinkendeUrl, $hash, $domain,
                    $z['linktext'] ?? null, $linkart, $empfehlung, $z['ziel_url'] ?? null,
                ]
            );
        }

        // Domain-Wissen aus historischer Entscheidung lernen (Toms „KI-Trainings-Material")
        // Nur wenn Linkart UND Empfehlung erkannt sind — sonst keine sinnvolle Lernquelle.
        if ($domain !== '' && $linkart !== null && $empfehlung !== null) {
            $this->lerneDomainWissen($domain, $linkart, $empfehlung);
        }
    }

    /**
     * Mappe altes deutschsprachiges Linkart-Label auf neuen Slug (DOMAIN_LINKART).
     * Gibt null zurück bei unbekannten Werten.
     */
    private function mappeAltesLinkartLabel(string $alt): ?string
    {
        $alt = trim($alt);
        if ($alt === '') return null;
        // Wenn schon ein gültiger Slug → direkt durchreichen
        if (in_array($alt, self::DOMAIN_LINKART, true)) return $alt;
        // Falls schon im neuen Linkprofil-Vokabular → durchreichen
        if (in_array($alt, self::VERLINKUNG_LINKART, true)) return $alt;

        $norm = mb_strtolower(strtr($alt, ['/' => ' ', '-' => ' ', '_' => ' ']));
        $norm = preg_replace('/\s+/', ' ', trim($norm));

        $map = [
            'händler partner'      => 'partner',
            'haendler partner'     => 'partner',
            'haendler   partner'   => 'partner',
            'haendler'             => 'partner',
            'partner'              => 'partner',
            'branchenverzeichnis'  => 'branchenverzeichnis',
            'fachverzeichnis'      => 'fachverzeichnis',
            'verzeichnis'          => 'branchenverzeichnis',
            'online magazin'       => 'online_magazin',
            'online-magazin'       => 'online_magazin',
            'magazin'              => 'online_magazin',
            'portal'               => 'portal',
            'blog'                 => 'blog',
            'forum'                => 'forum',
            'presseportal'         => 'presseportal',
            'presse'               => 'presseportal',
            'referenzprojekt'      => 'referenzprojekt',
            'referenz'             => 'referenzprojekt',
            'sponsoring'           => 'sponsoring',
            'stellenbörse'         => 'stellenboerse',
            'stellenboerse'        => 'stellenboerse',
            'stellen'              => 'stellenboerse',
            'veranstaltung'        => 'veranstaltung',
            'event'                => 'veranstaltung',
            'kommentarlink'        => 'kommentarlink',
            'kommentar'            => 'kommentarlink',
            'podcast'              => 'podcast',
            'social media'         => 'social_media',
            'social'               => 'social_media',
            'xing'                 => 'social_media',
            'linkedin'             => 'social_media',
            'weiterleitung'        => 'weiterleitung',
            'redirect'             => 'weiterleitung',
            'spam'                 => 'spam',
            'sonstiges'            => 'sonstiges',
            'sonstige'             => 'sonstiges',
        ];
        return $map[$norm] ?? null;
    }

    /**
     * Mappe alte deutsche Empfehlung auf neuen Slug (VERLINKUNG_EMPFEHLUNG).
     */
    private function mappeAlteEmpfehlung(string $alt): ?string
    {
        $alt = trim($alt);
        if ($alt === '') return null;
        if (in_array($alt, self::VERLINKUNG_EMPFEHLUNG, true)) return $alt;
        $norm = mb_strtolower($alt);
        $map = [
            'lassen'    => 'lassen',
            'behalten'  => 'lassen',
            'ändern'    => 'aendern',
            'aendern'   => 'aendern',
            'aktualisieren' => 'aendern',
            'löschen'   => 'loeschen',
            'loeschen'  => 'loeschen',
            'gelöscht'  => 'geloescht',
            'geloescht' => 'geloescht',
            'erledigt'  => 'geloescht',
            'disavow'   => 'disavow',
            'unsicher'  => 'unsicher',
            'klären'    => 'unsicher',
            'klaeren'   => 'unsicher',
        ];
        return $map[$norm] ?? null;
    }

    /**
     * Lernt aus historischer Entscheidung: Speichert linkart + empfehlung_default
     * in lam_domain_wissen für die Domain. Vorhandene manuell gepflegte Einträge
     * bleiben unangetastet — Historie überschreibt keine manuell gesetzte Klassifikation.
     */
    private function lerneDomainWissen(string $domain, string $linkart, string $empfehlung): void
    {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') return;

        $vorhanden = $this->db->queryOne(
            "SELECT linkart, empfehlung_default, manuell_gepflegt, anzahl_klassifikationen
             FROM lam_domain_wissen WHERE domain = ?",
            [$domain]
        );

        if (!$vorhanden) {
            // Neu anlegen aus Historie
            $this->db->execute(
                "INSERT INTO lam_domain_wissen
                    (domain, linkart, reduktionsstrategie, empfehlung_default,
                     manuell_gepflegt, anzahl_klassifikationen,
                     confidence, notiz, erstellt_am, aktualisiert_am)
                 VALUES (?, ?, 'reduktion_auf_1', ?, 0, 1, 'hoch', ?, NOW(), NOW())",
                [$domain, $linkart, $empfehlung, 'Aus historischem Linkprofil-Import gelernt']
            );
            return;
        }

        // Manuell gepflegt: nichts überschreiben
        if (!empty($vorhanden['manuell_gepflegt'])) return;

        // Linkart/Empfehlung nur setzen wenn vorher leer (Historie als sanfte Anreicherung)
        $linkartNeu    = empty($vorhanden['linkart'])            ? $linkart    : $vorhanden['linkart'];
        $empfNeu       = empty($vorhanden['empfehlung_default']) ? $empfehlung : $vorhanden['empfehlung_default'];
        $anzahlNeu     = ((int)($vorhanden['anzahl_klassifikationen'] ?? 0)) + 1;

        $this->db->execute(
            "UPDATE lam_domain_wissen
             SET linkart = ?, empfehlung_default = ?, anzahl_klassifikationen = ?, aktualisiert_am = NOW()
             WHERE domain = ?",
            [$linkartNeu, $empfNeu, $anzahlNeu, $domain]
        );
    }

    private function customerIdAusKuerzel(string $eingabe): ?int
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '') return null;
        $id = $this->db->queryValue(
            "SELECT id FROM customers WHERE abbreviation = ? OR name = ? OR LOWER(name) = LOWER(?) LIMIT 1",
            [$eingabe, $eingabe, $eingabe]
        );
        return $id ? (int)$id : null;
    }

    private function domainIdAusUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        $id = $this->db->queryValue(
            "SELECT id FROM lam_domains WHERE url = ? OR url LIKE ? LIMIT 1",
            [$url, '%' . $url . '%']
        );
        return $id ?: null;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        // ISO YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        // DE TT.MM.JJJJ
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $raw, $m)) {
            $j = (int)$m[3];
            if ($j < 100) $j += 2000;
            return sprintf('%04d-%02d-%02d', $j, $m[2], $m[1]);
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function parseDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function parseFloat(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $raw = str_replace(['€', ' ', "\xc2\xa0"], '', $raw);
        // Deutsche Notation: 1.234,56 → 1234.56
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^-?\d+,\d+$/', $raw)) {
            $raw = str_replace(',', '.', $raw);
        }
        if (!is_numeric($raw)) return null;
        return (float)$raw;
    }

    public function getSnapshotDiff(string $snapshotId): array
    {
        $snapshot = $this->db->queryOne(
            "SELECT * FROM lam_linkprofil_snapshots WHERE id = ?",
            [$snapshotId]
        );
        if (!$snapshot) throw new \InvalidArgumentException('Snapshot nicht gefunden.');

        $prev = $snapshot['vorgaenger_id'];
        $neu = [];
        $weg = [];
        if ($prev) {
            $neu = $this->db->query(
                "SELECT cur.quell_url, cur.ziel_url, cur.linkart
                 FROM lam_linkprofil_snapshot_verlinkungen cur
                 LEFT JOIN lam_linkprofil_snapshot_verlinkungen prev
                   ON prev.snapshot_id = ? AND prev.quell_url = cur.quell_url AND prev.ziel_url = cur.ziel_url
                 WHERE cur.snapshot_id = ? AND prev.snapshot_id IS NULL
                 LIMIT 1000",
                [$prev, $snapshotId]
            );
            $weg = $this->db->query(
                "SELECT prev.quell_url, prev.ziel_url, prev.linkart
                 FROM lam_linkprofil_snapshot_verlinkungen prev
                 LEFT JOIN lam_linkprofil_snapshot_verlinkungen cur
                   ON cur.snapshot_id = ? AND cur.quell_url = prev.quell_url AND cur.ziel_url = prev.ziel_url
                 WHERE prev.snapshot_id = ? AND cur.snapshot_id IS NULL
                 LIMIT 1000",
                [$snapshotId, $prev]
            );
        }
        return ['snapshot' => $snapshot, 'neu' => $neu, 'weggefallen' => $weg];
    }

    /* =========================================================================
       BULK-AKTIONEN AUF VERLINKUNGEN (Linkprofil-Bulk-Toolbar)
       ========================================================================= */

    /**
     * Hintergrund-Worker: pruefe alle Verlinkungen, die noch nie geprueft wurden
     * (letzter_http_erreichbar IS NULL). Maximal $batchSize Eintraege pro Aufruf.
     * Optional: pro Kunde filtern.
     */
    public function pruefeUngepruefteVerlinkungen(int $batchSize = 250, ?int $customerId = null): array
    {
        $where = ['v.letzter_http_erreichbar IS NULL', 'v.geloescht_am IS NULL'];
        $params = [];
        if ($customerId !== null) {
            $where[] = 'v.customer_id = ?';
            $params[] = $customerId;
        }
        $whereSql = implode(' AND ', $where);

        // Pro UNIQUE URL pruefen (gleiche URL koennte mehrfach im DB stehen mit verschiedenen Kunden)
        $urls = $this->db->query(
            "SELECT DISTINCT v.verlinkende_url
             FROM lam_verlinkungen v
             WHERE {$whereSql}
             LIMIT {$batchSize}",
            $params
        );
        if (!$urls) return ['geprueft' => 0, 'erreichbar' => 0, 'tot' => 0, 'fehler' => 0];

        $erreichbar = 0; $tot = 0; $fehler = 0;
        foreach ($urls as $u) {
            $url = (string)$u['verlinkende_url'];
            try {
                $status = $this->httpCheck($url);
                $this->db->execute(
                    "UPDATE lam_verlinkungen
                     SET letzter_http_status = ?, letzter_http_erreichbar = ?, letzter_check_am = NOW()
                     WHERE verlinkende_url = ? AND geloescht_am IS NULL",
                    [$status['code'], $status['erreichbar'] ? 1 : 0, $url]
                );
                if ($status['erreichbar']) $erreichbar++; else $tot++;
            } catch (\Throwable $e) {
                $fehler++;
            }
            usleep(200_000); // 200ms Schonzeit
        }
        return [
            'geprueft' => count($urls),
            'erreichbar' => $erreichbar,
            'tot' => $tot,
            'fehler' => $fehler,
        ];
    }

    /**
     * Wieviele Verlinkungen warten noch auf Erreichbarkeitspruefung?
     */
    public function zaehleUngepruefteVerlinkungen(?int $customerId = null): int
    {
        $where = ['letzter_http_erreichbar IS NULL', 'geloescht_am IS NULL'];
        $params = [];
        if ($customerId !== null) {
            $where[] = 'customer_id = ?';
            $params[] = $customerId;
        }
        return (int)$this->db->queryValue(
            'SELECT COUNT(*) FROM lam_verlinkungen WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    /**
     * Erreichbarkeit (HTTP-Status) fuer alle UNIQUE Domains der gegebenen Verlinkungs-IDs pruefen.
     * Aktualisiert v.letzter_http_status, v.letzter_http_erreichbar, v.letzter_check_am.
     */
    public function pruefeErreichbarkeitVerlinkungenBulk(array $ids): array
    {
        if (!$ids) return ['ok' => 0, 'fehler' => 0, 'geprueft_domains' => 0];

        // UNIQUE Domains aus den Verlinkungen
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "SELECT DISTINCT verlinkende_url, domain FROM lam_verlinkungen
             WHERE id IN ({$placeholders}) AND geloescht_am IS NULL",
            array_map('strval', $ids)
        );

        $ok = 0; $fehler = 0;
        $perUrlStatus = [];
        foreach ($rows as $r) {
            $url = (string)$r['verlinkende_url'];
            try {
                $status = $this->httpCheck($url);
                $perUrlStatus[$url] = $status;
                $this->db->execute(
                    "UPDATE lam_verlinkungen
                     SET letzter_http_status = ?, letzter_http_erreichbar = ?, letzter_check_am = NOW()
                     WHERE verlinkende_url = ? AND geloescht_am IS NULL",
                    [$status['code'], $status['erreichbar'] ? 1 : 0, $url]
                );
                $ok++;
            } catch (\Throwable $e) {
                $fehler++;
            }
        }
        $this->auditBulk('verlinkung.erreichbarkeit', 'verlinkung', count($ids), ['unique_urls' => count($rows)]);
        return ['ok' => $ok, 'fehler' => $fehler, 'geprueft_urls' => count($rows)];
    }

    /**
     * Holt fuer jede Verlinkung den Linktext aus der Quellseite (HTTP + Parse der <a>-Tags).
     * Nur fuer erreichbare URLs (letzter_http_erreichbar = 1).
     * Setzt v.linktext nur wenn aktuell leer ODER force=true.
     */
    public function holeLinktextVerlinkungenBulk(array $ids, bool $force = false): array
    {
        if (!$ids) return ['ok' => 0, 'fehler' => 0];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->query(
            "SELECT id, verlinkende_url, ziel_url, linktext, letzter_http_erreichbar
             FROM lam_verlinkungen
             WHERE id IN ({$placeholders}) AND geloescht_am IS NULL",
            array_map('strval', $ids)
        );

        $ok = 0; $fehler = 0; $skipped = 0;
        foreach ($rows as $v) {
            // Skip wenn nicht erreichbar
            if ((int)$v['letzter_http_erreichbar'] !== 1) { $skipped++; continue; }
            // Skip wenn Linktext bereits da und nicht force
            if (!$force && trim((string)$v['linktext']) !== '') { $skipped++; continue; }

            try {
                $linktext = $this->extrahiereLinktextAusUrl(
                    (string)$v['verlinkende_url'],
                    (string)$v['ziel_url']
                );
                if ($linktext !== '') {
                    $this->db->execute(
                        "UPDATE lam_verlinkungen SET linktext = ?, aktualisiert_am = NOW() WHERE id = ?",
                        [$linktext, $v['id']]
                    );
                    $ok++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $fehler++;
            }
            usleep(200_000); // 200ms Schonzeit
        }
        $this->auditBulk('verlinkung.linktext', 'verlinkung', count($ids), ['ok' => $ok, 'skipped' => $skipped, 'fehler' => $fehler]);
        return ['ok' => $ok, 'fehler' => $fehler, 'uebersprungen' => $skipped];
    }

    /**
     * Rollt fuer alle Verlinkungen das Domain-Wissen (linkart und/oder empfehlung_default)
     * auf die Verlinkungen aus. $feld = 'linkart' | 'empfehlung' | 'beides'.
     */
    public function wendeWissenAufVerlinkungenAn(array $ids, string $feld = 'beides'): array
    {
        if (!$ids) return ['ok' => 0, 'fehler' => 0];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = $this->db->query(
            "SELECT v.id, v.domain, w.linkart AS w_linkart, w.empfehlung_default AS w_empfehlung
             FROM lam_verlinkungen v
             LEFT JOIN lam_domain_wissen w ON w.domain = v.domain
             WHERE v.id IN ({$placeholders}) AND v.geloescht_am IS NULL",
            array_map('strval', $ids)
        );

        $ok = 0; $kein_wissen = 0;
        foreach ($rows as $r) {
            $sets = []; $params = [];
            if ($feld !== 'empfehlung' && !empty($r['w_linkart'])) {
                $sets[] = 'linkart = ?'; $params[] = $r['w_linkart'];
            }
            if ($feld !== 'linkart' && !empty($r['w_empfehlung'])) {
                $sets[] = 'empfehlung = ?'; $params[] = $r['w_empfehlung'];
            }
            if (!$sets) { $kein_wissen++; continue; }
            $sets[] = 'aktualisiert_am = NOW()';
            $params[] = $r['id'];
            $this->db->execute(
                "UPDATE lam_verlinkungen SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
            $ok++;
        }
        $this->auditBulk('verlinkung.wissen_anwenden', 'verlinkung', count($ids), ['feld' => $feld, 'ok' => $ok, 'kein_wissen' => $kein_wissen]);
        return ['ok' => $ok, 'kein_wissen' => $kein_wissen, 'feld' => $feld];
    }

    /**
     * KI-basierte Empfehlung (lassen/aendern/loeschen/disavow/unsicher) fuer jede
     * Verlinkung ableiten. Nutzt Claude Haiku mit fokussiertem Prompt (URL + Linktext
     * + bekannte Linkart). 300ms Rate-Limit.
     */
    public function bewerteEmpfehlungVerlinkungenBulk(array $ids): array
    {
        if (!$ids) return ['ok' => 0, 'fehler' => 0];
        $ok = 0; $fehler = 0; $fehlerListe = [];
        foreach ($ids as $id) {
            try {
                $this->bewerteEmpfehlungEinzeln((string)$id);
                $ok++;
            } catch (\Throwable $e) {
                $fehler++;
                $fehlerListe[] = $id . ': ' . $e->getMessage();
            }
            usleep(300_000);
        }
        $this->auditBulk('ki.empfehlung_bulk', 'verlinkung', $ok, ['fehler' => $fehler]);
        return ['ok' => $ok, 'fehler' => $fehler, 'fehler_liste' => array_slice($fehlerListe, 0, 20)];
    }

    /**
     * Sistrix-Kennzahlen (si und/oder dp) fuer alle UNIQUE Domains der gegebenen
     * Verlinkungen abrufen. Achtung: kostet Credits (1 fuer si, 25 fuer dp pro Domain).
     */
    public function holeSistrixVerlinkungenBulk(array $ids, array $teile): array
    {
        if (!$ids || !$teile) return ['ok' => 0, 'fehler' => 0, 'erfolge' => 0];

        require_once SERVICES_PATH . '/SistrixService.php';
        $sistrix = new SistrixService($this->db);

        $creditsProDomain = SistrixService::creditsFuer($teile);
        $status = $sistrix->istKonfiguriert() ? $sistrix->wochenStatus() : null;
        $verbleibend = $status['credits_verbleibend'] ?? PHP_INT_MAX;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $domains = $this->db->query(
            "SELECT DISTINCT v.domain
             FROM lam_verlinkungen v
             WHERE v.id IN ({$placeholders})
               AND v.geloescht_am IS NULL
               AND v.domain IS NOT NULL
               AND v.domain != ''",
            array_map('strval', $ids)
        );

        $ok = 0; $fehler = 0; $cacheHits = 0; $creditsVerbraucht = 0;
        $fehlerListe = [];
        $abgebrochen = false;
        foreach ($domains as $d) {
            $domainName = trim((string)($d['domain'] ?? ''));
            if ($domainName === '') continue;
            if ($verbleibend - $creditsVerbraucht < $creditsProDomain) {
                $abgebrochen = true;
                $fehlerListe[] = "Wochenkontingent erschoepft, weitere Domains uebersprungen.";
                break;
            }
            // SistrixService akzeptiert seit der domain_url-Migration eine Domain
            // direkt — kein Pool-Eintrag noetig. Snapshots werden ueber domain_url
            // referenziert. Damit bleibt der Linkquellen-Pool kuratiert.
            try {
                $res = $sistrix->holeKennzahlen($domainName, $teile, false);
                $hatWerte = false;
                foreach ($teile as $t) {
                    $feld = $t === 'alter' ? 'domain_alter' : $t;
                    if (isset($res['werte'][$feld]) && $res['werte'][$feld] !== null) {
                        $hatWerte = true; break;
                    }
                }
                if (!empty($res['fehler']) && !$hatWerte) {
                    $fehler++;
                    $fehlerListe[] = $domainName . ': ' . implode('; ', (array)$res['fehler']);
                } else {
                    $ok++;
                    if (!empty($res['cached'])) {
                        $cacheHits++;
                    } else {
                        $creditsVerbraucht += (int)($res['credits_verbraucht'] ?? $creditsProDomain);
                    }
                }
            } catch (\Throwable $e) {
                $fehler++;
                $fehlerListe[] = $domainName . ': ' . $e->getMessage();
            }
            usleep(150_000);
        }
        $this->auditBulk('sistrix.bulk', 'domain', $ok, ['teile' => $teile, 'fehler' => $fehler]);
        return [
            'ok' => $ok,
            'erfolge' => $ok,
            'fehler' => $fehler,
            'fehler_liste' => array_slice($fehlerListe, 0, 50),
            'cache_hits' => $cacheHits,
            'credits_verbraucht' => $creditsVerbraucht,
            'credits_pro_domain' => $creditsProDomain,
            'teile' => $teile,
            'abgebrochen' => $abgebrochen,
        ];
    }

    /**
     * Vorschau fuer Sistrix-Bulk auf Domain-IDs (Linkquellen-Liste).
     * Liefert direkt UNIQUE-Domain-Counts, Cache-Hits, Maximalkosten.
     */
    public function sistrixVorschauDomains(array $domainIds, array $teile): array
    {
        if (!$domainIds || !$teile) {
            return [
                'verlinkungen' => 0, // hier 0, weil nur Domains
                'unique_domains' => 0,
                'cache_hits' => 0,
                'neu_abzurufen' => 0,
                'credits_pro_domain' => 0,
                'kosten_max' => 0,
                'teile' => $teile,
            ];
        }
        require_once SERVICES_PATH . '/SistrixService.php';
        $creditsProDomain = SistrixService::creditsFuer($teile);

        $domainIds = array_values(array_unique(array_map('strval', $domainIds)));
        $heute = date('Y-m-d');
        $cacheHits = 0; $neuAbzurufen = 0;
        foreach ($domainIds as $domainId) {
            $snap = $this->db->queryOne(
                "SELECT si, dp, domain_alter FROM lam_kennzahl_snapshots
                 WHERE domain_id = ? AND erfasst_am = ? AND quelle = 'sistrix_api' LIMIT 1",
                [$domainId, $heute]
            );
            $hatAlle = $snap !== null;
            if ($hatAlle) {
                foreach ($teile as $t) {
                    $feld = $t === 'alter' ? 'domain_alter' : $t;
                    if ($snap[$feld] === null || $snap[$feld] === '') { $hatAlle = false; break; }
                }
            }
            if ($hatAlle) { $cacheHits++; } else { $neuAbzurufen++; }
        }
        return [
            'verlinkungen' => 0,
            'unique_domains' => count($domainIds),
            'cache_hits' => $cacheHits,
            'neu_abzurufen' => $neuAbzurufen,
            'credits_pro_domain' => $creditsProDomain,
            'kosten_max' => $neuAbzurufen * $creditsProDomain,
            'teile' => $teile,
        ];
    }

    /**
     * Vorschau fuer Sistrix-Bulk: UNIQUE Domains + Cache-Hits + Maximalkosten.
     * Wird vom Pre-Confirm-Modal aufgerufen, damit der User sieht was reinkommt
     * ohne dass schon Credits verbraucht werden.
     */
    public function sistrixVorschauVerlinkungen(array $ids, array $teile): array
    {
        if (!$ids || !$teile) {
            return [
                'verlinkungen' => 0,
                'unique_domains' => 0,
                'cache_hits' => 0,
                'neu_abzurufen' => 0,
                'credits_pro_domain' => 0,
                'kosten_max' => 0,
                'teile' => $teile,
            ];
        }
        require_once SERVICES_PATH . '/SistrixService.php';
        $creditsProDomain = SistrixService::creditsFuer($teile);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $domains = $this->db->query(
            "SELECT DISTINCT v.domain
             FROM lam_verlinkungen v
             WHERE v.id IN ({$placeholders}) AND v.geloescht_am IS NULL AND v.domain IS NOT NULL",
            array_map('strval', $ids)
        );

        $heute = date('Y-m-d');
        $cacheHits = 0; $neuAbzurufen = 0;
        foreach ($domains as $d) {
            // Snapshot-Suche jetzt direkt ueber domain_url, kein Pool-Eintrag noetig.
            $snap = $this->db->queryOne(
                "SELECT si, dp, domain_alter FROM lam_kennzahl_snapshots
                 WHERE domain_url = ? AND erfasst_am = ? AND quelle = 'sistrix_api' LIMIT 1",
                [$d['domain'], $heute]
            );
            $hatAlle = $snap !== null;
            if ($hatAlle) {
                foreach ($teile as $t) {
                    $feld = $t === 'alter' ? 'domain_alter' : $t;
                    if ($snap[$feld] === null || $snap[$feld] === '') { $hatAlle = false; break; }
                }
            }
            if ($hatAlle) { $cacheHits++; } else { $neuAbzurufen++; }
        }

        $uniqueDomains = count($domains);
        $kostenMax = $neuAbzurufen * $creditsProDomain;

        return [
            'verlinkungen' => count($ids),
            'unique_domains' => $uniqueDomains,
            'cache_hits' => $cacheHits,
            'neu_abzurufen' => $neuAbzurufen,
            'kein_pool_eintrag' => 0, // historisch, wird nicht mehr verwendet
            'credits_pro_domain' => $creditsProDomain,
            'kosten_max' => $kostenMax,
            'teile' => $teile,
        ];
    }

    /* ----- Helper fuer Bulk-Aktionen ----- */

    /**
     * Minimaler HTTP-HEAD/GET-Check ohne Body-Download.
     */
    private function httpCheck(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 LAM-Erreichbarkeitspruefung',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'erreichbar' => ($code >= 200 && $code < 400)];
    }

    /**
     * Holt eine URL, sucht das <a>-Tag mit der Ziel-URL und liefert dessen Linktext.
     * Wenn keine Ziel-URL angegeben: erstes <a>-Tag mit Inhalt.
     */
    private function extrahiereLinktextAusUrl(string $quellUrl, string $zielUrl = ''): string
    {
        $ch = curl_init($quellUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 LAM-Linktext-Crawler',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $html = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!$html || $code < 200 || $code >= 400) return '';

        // Alle <a href="...">text</a> sammeln
        if (!preg_match_all('#<a\s+[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            return '';
        }

        // Normalize Ziel zur Vergleichbarkeit
        $zielNorm = $zielUrl !== '' ? rtrim(strtolower($zielUrl), '/') : '';

        foreach ($m as $match) {
            $href = strtolower($match[1]);
            if ($zielNorm !== '' && rtrim($href, '/') !== $zielNorm) continue;
            $text = trim(strip_tags($match[2]));
            $text = preg_replace('/\s+/', ' ', $text);
            if ($text !== '') return mb_substr($text, 0, 500);
        }
        return '';
    }

    /**
     * KI-Empfehlung fuer eine einzelne Verlinkung — nutzt Claude Haiku.
     * Wenn die Settings keinen Claude-Key haben, faellt es auf Heuristik zurueck.
     */
    private function bewerteEmpfehlungEinzeln(string $verlinkungId): array
    {
        $v = $this->db->queryOne(
            "SELECT id, verlinkende_url, domain, linktext, linkart, empfehlung
             FROM lam_verlinkungen WHERE id = ? AND geloescht_am IS NULL",
            [$verlinkungId]
        );
        if (!$v) throw new \InvalidArgumentException('Verlinkung nicht gefunden.');

        $apiKey = (string)\Core\Settings::get('anthropic_api_key');
        if ($apiKey === '') {
            // Heuristik-Fallback ohne API
            $emp = $this->empfehlungHeuristik($v);
        } else {
            $emp = $this->empfehlungViaClaude($v, $apiKey);
        }

        if (!empty($emp['empfehlung'])) {
            $this->db->execute(
                "UPDATE lam_verlinkungen
                 SET empfehlung = ?, ki_begruendung = COALESCE(?, ki_begruendung), aktualisiert_am = NOW()
                 WHERE id = ?",
                [$emp['empfehlung'], $emp['begruendung'] ?? null, $verlinkungId]
            );
        }
        return $emp;
    }

    private function empfehlungHeuristik(array $v): array
    {
        $la = (string)($v['linkart'] ?? '');
        if ($la === 'spam')               return ['empfehlung' => 'disavow', 'begruendung' => 'Heuristik: Spam → disavow'];
        if ($la === 'branchenverzeichnis')return ['empfehlung' => 'lassen',  'begruendung' => 'Heuristik: Branchenverzeichnis → lassen'];
        if ($la === 'partner' || $la === 'referenzprojekt' || $la === 'sponsoring')
            return ['empfehlung' => 'lassen', 'begruendung' => 'Heuristik: vertrauenswuerdige Linkart → lassen'];
        return ['empfehlung' => 'unsicher', 'begruendung' => 'Heuristik: keine klare Regel'];
    }

    private function empfehlungViaClaude(array $v, string $apiKey): array
    {
        $prompt = "Verlinkung:\n"
            . "- URL: " . ($v['verlinkende_url'] ?? '') . "\n"
            . "- Domain: " . ($v['domain'] ?? '') . "\n"
            . "- Linktext: " . ($v['linktext'] ?? '(leer)') . "\n"
            . "- Linkart: " . ($v['linkart'] ?? '(unbekannt)') . "\n\n"
            . "Welche Empfehlung im SEO-Linkaufbau-Kontext? Antworte NUR mit JSON:\n"
            . '{"empfehlung":"lassen|aendern|loeschen|disavow|unsicher","begruendung":"<kurz>"}';

        $body = json_encode([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 200,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code !== 200 || !$resp) {
            throw new \RuntimeException('Claude HTTP ' . $code);
        }
        $data = json_decode($resp, true);
        $text = $data['content'][0]['text'] ?? '';
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed) && !empty($parsed['empfehlung'])) {
                $erlaubt = ['lassen', 'aendern', 'loeschen', 'disavow', 'unsicher'];
                if (in_array($parsed['empfehlung'], $erlaubt, true)) return $parsed;
            }
        }
        throw new \RuntimeException('Claude-Antwort unparsbar');
    }
}
