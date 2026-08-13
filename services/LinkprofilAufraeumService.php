<?php

namespace Services;

use Core\Database;
use Core\Settings;
use Core\AuditLog;
use Core\Auth;

/**
 * Aufraeum-Modus fuer das Linkprofil: ermittelt pro Domain die beste Deep-URL,
 * klassifiziert per KI (Claude Sonnet 4.6) und lernt aus User-Entscheidungen.
 *
 * Dreiteilig:
 *   1. Sitewide-Cluster (laeuft schon ueber LamService::findeSitewideCluster)
 *   2. Domain-Vorschlaege mit Klassifikation (Wissensbasis oder KI)
 *   3. Pro Domain: intelligentes URL-Scoring fuer "beste behalten"
 *
 * Score-Gewichte sind in der Settings-Map `lam_aufraeum_gewichte` konfigurierbar
 * (default + pro Linkart). Manuelle Korrekturen schreiben in die Wissensbasis
 * mit confidence='manuell' und ueberschreiben KI-Vorschlaege.
 */
class LinkprofilAufraeumService
{
    private const KI_MODELL = 'claude-sonnet-4-6';
    private const KI_BATCH_GROESSE = 50;
    private const KI_CONFIDENCE_KLAR = 0.80;
    private const KI_CONFIDENCE_UNSICHER = 0.50;

    /** Linkarten, die typischerweise reduziert werden (sonst alle behalten) */
    private const STRATEGIE_DEFAULTS = [
        'spam'                => 'reduktion_auf_1',
        'branchenverzeichnis' => 'reduktion_auf_2',
        'fachverzeichnis'     => 'reduktion_auf_2',
        'presseportal'        => 'reduktion_auf_2',
        'stellenboerse'       => 'reduktion_auf_2',
        'veranstaltung'       => 'reduktion_auf_2',
        'sonstiges'           => 'reduktion_auf_2',
        'referenzprojekt'     => 'reduktion_auf_1',
        'partner'             => 'reduktion_auf_1',
        'sponsoring'          => 'reduktion_auf_1',
        'weiterleitung'       => 'reduktion_auf_1',
        'online_magazin'      => 'alle_behalten',
        'portal'              => 'alle_behalten',
        'blog'                => 'alle_behalten',
        'forum'               => 'alle_behalten',
        'podcast'             => 'alle_behalten',
        'kommentarlink'       => 'alle_behalten',
        'social_media'        => 'alle_behalten',
    ];

    public function __construct(private Database $db) {}

    /* ============================================================
     *  KUNDEN-SPEZIFISCHE LINKARTEN
     * ============================================================ */

    /**
     * Alle (aktiven) kundenspezifischen Linkarten. Wird im Wizard-Dropdown
     * gruppiert dargestellt und in den KI-Prompt eingebaut.
     */
    public function listeKundenLinkarten(int $customerId): array
    {
        return $this->db->query(
            "SELECT id, linkart_key, label, default_strategie, beschreibung, farbe
             FROM lam_kunde_linkart
             WHERE customer_id = ? AND geloescht_am IS NULL
             ORDER BY label ASC",
            [$customerId]
        );
    }

    /**
     * Anlegen oder aktualisieren einer kundenspezifischen Linkart.
     * $key wird auto-slugged falls leer.
     */
    public function speichereKundenLinkart(int $customerId, array $daten): array
    {
        $label = trim((string)($daten['label'] ?? ''));
        if ($label === '') throw new \InvalidArgumentException('Label erforderlich');
        $key   = trim((string)($daten['linkart_key'] ?? '')) ?: $this->slugify($label);
        $strat = (string)($daten['default_strategie'] ?? 'alle_behalten');
        if (!in_array($strat, ['alle_behalten', 'reduktion_auf_1', 'reduktion_auf_2'], true)) {
            $strat = 'alle_behalten';
        }
        $beschreibung = trim((string)($daten['beschreibung'] ?? ''));
        $farbe        = trim((string)($daten['farbe'] ?? '')) ?: null;

        $bestehend = $this->db->queryOne(
            "SELECT id FROM lam_kunde_linkart
             WHERE customer_id = ? AND linkart_key = ? AND geloescht_am IS NULL",
            [$customerId, $key]
        );
        if ($bestehend) {
            $this->db->execute(
                "UPDATE lam_kunde_linkart
                 SET label = ?, default_strategie = ?, beschreibung = ?, farbe = ?
                 WHERE id = ?",
                [$label, $strat, $beschreibung, $farbe, $bestehend['id']]
            );
            AuditLog::record('lam_kunde_linkart', $bestehend['id'], 'aktualisiert',
                ['customer' => $customerId, 'key' => $key, 'label' => $label]);
            return ['id' => $bestehend['id'], 'aktion' => 'aktualisiert'];
        }
        $id = $this->generiereUlid();
        $this->db->execute(
            "INSERT INTO lam_kunde_linkart
                (id, customer_id, linkart_key, label, default_strategie, beschreibung, farbe)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id, $customerId, $key, $label, $strat, $beschreibung, $farbe]
        );
        AuditLog::record('lam_kunde_linkart', $id, 'angelegt',
            ['customer' => $customerId, 'key' => $key, 'label' => $label]);
        return ['id' => $id, 'aktion' => 'angelegt'];
    }

    public function loescheKundenLinkart(string $id): bool
    {
        $row = $this->db->queryOne("SELECT customer_id, linkart_key FROM lam_kunde_linkart WHERE id = ?", [$id]);
        if (!$row) return false;
        $this->db->execute("UPDATE lam_kunde_linkart SET geloescht_am = NOW() WHERE id = ?", [$id]);
        AuditLog::record('lam_kunde_linkart', $id, 'geloescht',
            ['customer' => $row['customer_id'], 'key' => $row['linkart_key']]);
        return true;
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_') ?: 'linkart_' . substr(md5((string)microtime(true)), 0, 6);
    }

    /* ============================================================
     *  PUBLIC API
     * ============================================================ */

    /**
     * Liefert alle offenen Domain-Vorschlaege fuer einen Kunden in drei Kategorien:
     *   klar       - aus Wissensbasis ODER KI-Confidence >= 0.80
     *   unsicher   - KI-Confidence 0.50..0.79
     *   unbestimmt - kein Wissensbasis-Eintrag UND keine KI-Klassifikation vorhanden
     *
     * Wird zusammen mit Sitewide-Clustern in einem Roundtrip ausgeliefert,
     * damit das Frontend den Wizard mit einem Request rendert.
     */
    public function getDomainVorschlaege(int $customerId, bool $auchBestaetigt = false): array
    {
        // Default: nur offene Verlinkungen. Mit $auchBestaetigt=true (Nachbearbeitung):
        // alles, was noch aktiv ist (geloescht_am IS NULL), egal mit welcher Empfehlung.
        // Selbst 'lassen', 'disavow', 'loeschen' sollen sichtbar sein — der User will
        // ja Dubletten reduzieren oder Empfehlungen aendern koennen.
        // Filterung nach Status (mit Dubletten / unsicher / etc.) macht das Frontend
        // ueber die Sonst.-Filter-Chips.
        $statusFilter = $auchBestaetigt
            ? "(v.aufraeum_status IN ('offen','bestaetigt') OR v.aufraeum_status IS NULL)"
            : "(v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)";
        $havingExtra = "";

        $rows = $this->db->query(
            "SELECT v.domain,
                    COUNT(*) AS anzahl,
                    SUM(CASE WHEN v.linktext IS NULL OR TRIM(v.linktext) = '' THEN 1 ELSE 0 END) AS anzahl_ohne_linktext,
                    SUM(CASE WHEN v.linktext IS NOT NULL AND TRIM(v.linktext) <> '' THEN 1 ELSE 0 END) AS anzahl_mit_linktext,
                    SUM(CASE WHEN v.empfehlung = 'unsicher' THEN 1 ELSE 0 END) AS anzahl_unsicher,
                    SUM(CASE WHEN v.empfehlung = 'aendern'  THEN 1 ELSE 0 END) AS anzahl_aendern,
                    SUM(CASE WHEN v.aufraeum_status = 'bestaetigt' THEN 1 ELSE 0 END) AS anzahl_bestaetigt,
                    GROUP_CONCAT(DISTINCT v.linktext ORDER BY LENGTH(v.linktext) DESC SEPARATOR '||') AS linktexte,
                    GROUP_CONCAT(DISTINCT v.verlinkende_url ORDER BY LENGTH(v.verlinkende_url) ASC SEPARATOR '||') AS beispiel_urls,
                    GROUP_CONCAT(DISTINCT v.ziel_url SEPARATOR '||') AS ziel_urls
             FROM lam_verlinkungen v
             WHERE v.customer_id = ?
               AND v.geloescht_am IS NULL
               AND $statusFilter
             GROUP BY v.domain
             HAVING anzahl >= 1 $havingExtra
             ORDER BY anzahl DESC, v.domain ASC",
            [$customerId]
        );

        // SI-Werte pro Domain als Map (latest snapshot). Wird fuer den SI-Filter
        // in der Aufraeum-UI gebraucht — sonst muesste das Frontend pro Domain
        // einen Detail-Call machen, was die Liste unbrauchbar machen wuerde.
        $siMap = [];
        if ($rows) {
            $domainList = array_values(array_unique(array_column($rows, 'domain')));
            if ($domainList) {
                $placeholders = implode(',', array_fill(0, count($domainList), '?'));
                $siRows = $this->db->query(
                    "SELECT s1.domain_url, s1.si
                     FROM lam_kennzahl_snapshots s1
                     INNER JOIN (
                         SELECT domain_url, MAX(erfasst_am) AS latest
                         FROM lam_kennzahl_snapshots
                         WHERE domain_url IN ($placeholders) AND si IS NOT NULL
                         GROUP BY domain_url
                     ) s2 ON s1.domain_url = s2.domain_url AND s1.erfasst_am = s2.latest",
                    $domainList
                );
                foreach ($siRows as $sr) {
                    $siMap[$sr['domain_url']] = (float)$sr['si'];
                }
            }
        }

        $klar = []; $unsicher = []; $unbestimmt = [];
        foreach ($rows as $r) {
            // Kundenspezifischer Eintrag hat Vorrang vor globalem.
            $wissen = $this->db->queryOne(
                "SELECT linkart, reduktionsstrategie, empfehlung_default, confidence,
                        manuell_gepflegt, ki_begruendung, notiz
                 FROM lam_domain_wissen
                 WHERE domain = ? AND (customer_id = ? OR customer_id IS NULL)
                 ORDER BY customer_id IS NULL ASC
                 LIMIT 1",
                [$r['domain'], $customerId]
            );

            $linktexte    = array_slice(array_filter(explode('||', (string)($r['linktexte'] ?? ''))), 0, 5);
            $beispielUrls = array_slice(array_filter(explode('||', (string)($r['beispiel_urls'] ?? ''))), 0, 5);
            $zielUrls     = array_values(array_filter(explode('||', (string)($r['ziel_urls'] ?? ''))));
            $haeufigstesZiel = $zielUrls[0] ?? null; // erstes nicht-leeres reicht als Vorschlag

            $mitLt   = (int)($r['anzahl_mit_linktext'] ?? 0);
            $ohneLt  = (int)($r['anzahl_ohne_linktext'] ?? 0);
            $siVal   = $siMap[$r['domain']] ?? null;
            $anzUnsicher   = (int)($r['anzahl_unsicher']   ?? 0);
            $anzBestaetigt = (int)($r['anzahl_bestaetigt'] ?? 0);
            $eintrag = [
                'domain'                  => $r['domain'],
                'anzahl'                  => (int)$r['anzahl'],
                'anzahl_mit_linktext'     => $mitLt,
                'anzahl_ohne_linktext'    => $ohneLt,
                'anzahl_unsicher'         => $anzUnsicher,
                'anzahl_bestaetigt'       => $anzBestaetigt,
                'hat_unsichere_empfehlung'=> $anzUnsicher > 0,
                'ist_nachbearbeitung'     => $anzBestaetigt > 0,
                'linktext_status'         => $ohneLt === 0 ? 'alle' : ($mitLt === 0 ? 'keine' : 'gemischt'),
                'sistrix_si'              => $siVal,
                'beispiel_urls'           => $beispielUrls,
                'linktexte'               => $linktexte,
                'vorschlag_ziel_url'      => $haeufigstesZiel,
                'notiz'                   => self::filtereNotiz($wissen['notiz'] ?? null),
                'wissen'                  => $wissen,
            ];

            if ($wissen && in_array($wissen['confidence'], ['manuell', 'ki_bestaetigt'], true)) {
                $eintrag['kategorie'] = 'klar';
                $eintrag['quelle']    = 'wissensbasis';
                $eintrag['vorschlag_linkart']    = $wissen['linkart'];
                $eintrag['vorschlag_strategie']  = $wissen['reduktionsstrategie'] ?: $this->defaultStrategie($wissen['linkart']);
                $eintrag['vorschlag_empfehlung'] = $wissen['empfehlung_default'] ?: $this->defaultEmpfehlung($wissen['linkart']);
                $klar[] = $eintrag;
            } else {
                $eintrag['kategorie'] = 'unbestimmt';
                $eintrag['quelle']    = $wissen ? 'ki_vorgeschlagen' : 'unbekannt';
                $eintrag['vorschlag_linkart']    = $wissen['linkart'] ?? null;
                $eintrag['vorschlag_strategie']  = $wissen['reduktionsstrategie'] ?? null;
                $eintrag['vorschlag_empfehlung'] = $wissen['empfehlung_default'] ?? null;
                $unbestimmt[] = $eintrag;
            }
        }

        // Hilfsdaten fuer Frontend: kundenspezifische Linkarten + Linkziele + Empfehlungen
        require_once SERVICES_PATH . '/LamService.php';
        $linkziele = [];
        try {
            $linkziele = $this->db->query(
                "SELECT id, url, thema, bevorzugter_linktext
                 FROM lam_linkziele
                 WHERE customer_id = ? AND status = 'aktiv'
                 ORDER BY thema ASC, url ASC",
                [$customerId]
            );
        } catch (\Throwable $e) { /* Tabelle evtl. noch leer */ }

        return [
            'klar'              => $klar,
            'unsicher'          => $unsicher,
            'unbestimmt'        => $unbestimmt,
            'gesamt'            => count($klar) + count($unsicher) + count($unbestimmt),
            'kunden_linkarten'  => $this->listeKundenLinkarten($customerId),
            'linkziele'         => $linkziele,
            'empfehlungen'      => \Services\LamService::VERLINKUNG_EMPFEHLUNG,
        ];
    }

    /**
     * Default-Empfehlung je nach Linkart. Spam → 'loeschen', Verzeichnisse → 'unsicher',
     * editorial → 'lassen'.
     */
    private function defaultEmpfehlung(?string $linkart): ?string
    {
        return match ($linkart) {
            'spam' => 'loeschen',
            'branchenverzeichnis', 'fachverzeichnis', 'presseportal',
            'stellenboerse', 'veranstaltung', 'sonstiges', 'weiterleitung' => 'unsicher',
            'blog', 'online_magazin', 'portal', 'podcast', 'forum',
            'referenzprojekt', 'partner', 'sponsoring', 'kommentarlink', 'social_media' => 'lassen',
            default => null,
        };
    }

    /**
     * Liefert pro Verlinkung der Domain die Score-Daten + initiale "behalten"-Auswahl.
     * Wird vom Detail-Drawer aufgerufen. Score wird im Backend berechnet, damit
     * die Default-Auswahl serverseitig und konsistent ist; das Frontend zeigt sie an.
     */
    /**
     * URL-Praeferenz-Strategien fuer die "beste URL"-Auswahl pro Domain.
     *   auto      - Score-basiert (SI + HTTP + Tiefe + Linktext + Neuheit)
     *   kuerzeste - immer kuerzeste URL bevorzugen (klassisch Footer-Sitewide)
     *   tiefste   - immer Deeplinks bevorzugen (mehr Kontext)
     *   deeplink_aber_score - bei Gleichstand Deeplink bevorzugen
     */
    public const URL_STRATEGIEN = ['auto', 'kuerzeste', 'tiefste', 'deeplink_aber_score'];

    public function getDomainDetail(int $customerId, string $domain, ?string $strategie = null, ?string $urlStratOverride = null, bool $auchBestaetigt = false): array
    {
        // Im Nachbearbeitungs-Modus auch schon bestaetigte Verlinkungen mitnehmen.
        // Empfehlung ist Metadata, kein Loesch-Filter — der User soll alle aktiven
        // Verlinkungen sehen und ggf. neu entscheiden koennen.
        $statusFilter = $auchBestaetigt
            ? "(v.aufraeum_status IN ('offen','bestaetigt') OR v.aufraeum_status IS NULL)"
            : "(v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)";

        $rows = $this->db->query(
            "SELECT v.id, v.verlinkende_url, v.linktext, v.ziel_url,
                    v.letzter_http_status, v.letzter_http_erreichbar, v.erstellt_am,
                    latest_si.si AS sistrix_index
             FROM lam_verlinkungen v
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
             WHERE v.customer_id = ? AND v.domain = ?
               AND v.geloescht_am IS NULL
               AND $statusFilter",
            [$customerId, $domain]
        );

        if (!$rows) return ['verlinkungen' => [], 'behalten_ids' => []];

        $wissen = $this->db->queryOne(
            "SELECT linkart, reduktionsstrategie, url_score_strategie
             FROM lam_domain_wissen
             WHERE domain = ? AND (customer_id = ? OR customer_id IS NULL)
             ORDER BY customer_id IS NULL ASC
             LIMIT 1",
            [$domain, $customerId]
        );
        $linkart   = $wissen['linkart'] ?? null;
        $urlStrat  = $urlStratOverride ?: ($wissen['url_score_strategie'] ?? 'auto');
        $gewichte  = $this->gewichteFuerLinkart($linkart);

        // Score pro Zeile berechnen
        $maxSi = 0;
        foreach ($rows as $r) $maxSi = max($maxSi, (float)($r['sistrix_index'] ?? 0));
        foreach ($rows as &$r) {
            $r['url_tiefe']    = $this->urlTiefe($r['verlinkende_url']);
            $r['hat_linktext'] = trim((string)$r['linktext']) !== '' ? 1 : 0;
            $r['score']        = $this->berechneScore($r, $gewichte, $maxSi);
        }
        unset($r);

        // Sortier-Reihenfolge je nach URL-Strategie
        usort($rows, $this->vergleichsFunktion($urlStrat));

        // Behalten-IDs nach Reduktions-Strategie
        $strategie ??= $wissen['reduktionsstrategie'] ?? $this->defaultStrategie($linkart);
        $behaltenIds = $this->bestimmeBehaltenIds($rows, $strategie);

        return [
            'verlinkungen' => $rows,
            'behalten_ids' => $behaltenIds,
            'strategie'    => $strategie,
            'url_strategie'=> $urlStrat,
            'linkart'      => $linkart,
            'gewichte'     => $gewichte,
        ];
    }

    /**
     * Liefert eine usort-Funktion passend zur URL-Strategie.
     * Sortierung absteigend (beste zuerst).
     */
    private function vergleichsFunktion(string $urlStrat): \Closure
    {
        return match ($urlStrat) {
            'kuerzeste' => fn($a, $b) =>
                  $a['url_tiefe'] <=> $b['url_tiefe']                     // weniger Tiefe zuerst
                ?: $b['score']     <=> $a['score']                        // dann nach Score
                ?: strcmp($a['verlinkende_url'], $b['verlinkende_url']),
            'tiefste' => fn($a, $b) =>
                  $b['url_tiefe'] <=> $a['url_tiefe']                     // mehr Tiefe zuerst
                ?: $b['score']     <=> $a['score']
                ?: strcmp($a['verlinkende_url'], $b['verlinkende_url']),
            'deeplink_aber_score' => fn($a, $b) =>
                  $b['score']     <=> $a['score']                         // primaer Score
                ?: $b['url_tiefe'] <=> $a['url_tiefe']                    // bei Gleichstand Deeplink
                ?: strcmp($a['verlinkende_url'], $b['verlinkende_url']),
            default /* auto */ => fn($a, $b) =>
                  $b['score']     <=> $a['score']
                ?: $a['url_tiefe'] <=> $b['url_tiefe']
                ?: strcmp($a['verlinkende_url'], $b['verlinkende_url']),
        };
    }

    /**
     * Klassifiziert unbekannte Domains in Batches per Claude Sonnet 4.6.
     * Schreibt Ergebnisse in lam_domain_wissen mit confidence='ki_vorgeschlagen'.
     * Returns Statistik fuer das Fortschritts-Modal.
     */
    public function klassifiziereUnbekannteBatch(array $domains, ?int $customerId = null, bool $forceRefresh = false): array
    {
        if (!$domains) return ['ok' => 0, 'fehler' => 0, 'fehler_liste' => []];

        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key nicht gesetzt.');

        // Kunden-Kontext + globaler Kontext + Lernbeispiele
        $kundenKontext = '';
        if ($customerId) {
            $row = $this->db->queryOne("SELECT lam_kontext, name FROM customers WHERE id = ?", [$customerId]);
            if ($row && !empty($row['lam_kontext'])) {
                $kundenKontext = "\n\nKUNDEN-KONTEXT für " . ($row['name'] ?? 'Kunde') . ":\n"
                    . trim($row['lam_kontext'])
                    . "\nBerücksichtige diesen Kontext bei der Klassifikation. Wenn ein Backlink-Muster im Kontext "
                    . "beschrieben ist, weise die dort genannte Linkart zu (falls in der Liste vorhanden).";
            }
        }
        $globKontext = Settings::get('lam_globaler_kontext');
        if (!empty($globKontext)) {
            $kundenKontext .= "\n\nGLOBALER KONTEXT (gilt für alle Kunden):\n" . trim($globKontext);
        }

        // Letzte 10 manuelle Korrekturen als Lern-Beispiele — die KI soll aus
        // den Aenderungen lernen, die der User selbst getroffen hat. Wirkt
        // kundenuebergreifend, weil Wissensbasis-Eintraege global sind.
        $beispiele = $this->db->query(
            "SELECT domain, linkart, reduktionsstrategie, COALESCE(ki_begruendung, '') AS notiz
             FROM lam_domain_wissen
             WHERE manuell_gepflegt = 1
             ORDER BY letzte_korrektur_am DESC
             LIMIT 10"
        );
        if ($beispiele) {
            $kundenKontext .= "\n\nLERN-BEISPIELE (manuell vom User korrigiert, nutze sie als Muster):";
            foreach ($beispiele as $b) {
                $kundenKontext .= "\n- {$b['domain']} → linkart={$b['linkart']}, strategie={$b['reduktionsstrategie']}"
                    . ($b['notiz'] !== '' ? " (Notiz: {$b['notiz']})" : '');
            }
        }

        // Aktive (bestaetigte) Muster — als Regeln, denen die KI folgen soll.
        if ($customerId) {
            $aktiveMuster = $this->listeMuster($customerId, true);
            if ($aktiveMuster) {
                $kundenKontext .= "\n\nAKTIVE REGELN (bestaetigte Muster — strikt anwenden):";
                foreach ($aktiveMuster as $m) {
                    $regel = match ($m['muster_typ']) {
                        'domain_suffix'    => "Quell-Domain endet auf '{$m['muster_value']}'",
                        'domain_pattern'   => "Quell-Domain enthaelt '{$m['muster_value']}'",
                        'linktext_pattern' => "Linktext enthaelt '{$m['muster_value']}'",
                        'ziel_url_pattern' => "Ziel-URL enthaelt '{$m['muster_value']}'",
                        'keyword', 'url_pattern' => "Quell-URL enthaelt '{$m['muster_value']}'",
                        default            => "{$m['muster_typ']}='{$m['muster_value']}'",
                    };
                    $kundenKontext .= "\n- WENN $regel → linkart={$m['aktion_linkart']}"
                        . ($m['aktion_strategie'] ? ", strategie={$m['aktion_strategie']}" : '')
                        . ($m['aktion_empfehlung'] ? ", empfehlung={$m['aktion_empfehlung']}" : '')
                        . (!empty($m['beschreibung']) ? " ({$m['beschreibung']})" : '');
                }
            }
        }

        // Per-Domain-Notizen fuer die aktuell zu klassifizierenden Domains.
        // Wenn eine Domain im Batch eine Notiz hat, wird sie direkt mitgegeben.
        if ($customerId) {
            $domainNames = array_column($domains, 'domain');
            if ($domainNames) {
                $placeholders = implode(',', array_fill(0, count($domainNames), '?'));
                $notizen = $this->db->query(
                    "SELECT domain, notiz FROM lam_domain_wissen
                     WHERE domain IN ($placeholders)
                       AND (customer_id = ? OR customer_id IS NULL)
                       AND notiz IS NOT NULL AND TRIM(notiz) <> ''
                     ORDER BY customer_id IS NULL ASC",
                    array_merge($domainNames, [$customerId])
                );
                $notizMap = [];
                foreach ($notizen as $n) {
                    $clean = self::filtereNotiz($n['notiz']);
                    if ($clean !== null && !isset($notizMap[$n['domain']])) $notizMap[$n['domain']] = $clean;
                }
                if ($notizMap) {
                    // GANZ AN DEN ANFANG des Kontexts: User-Notizen schlagen ALLES.
                    $notizBlock = "DOMAIN-SPEZIFISCHE NOTIZEN — VOM USER VORGEGEBEN, ZWINGEND BEACHTEN "
                        . "(diese Regel ueberschreibt alle anderen Heuristiken):";
                    foreach ($notizMap as $dom => $notiz) {
                        $notizBlock .= "\n- $dom: " . mb_substr(trim($notiz), 0, 600);
                    }
                    $kundenKontext = "\n\n" . $notizBlock . $kundenKontext;
                }
            }
        }

        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel(self::KI_MODELL);
        $ai->setMaxTokens(4000);
        $ai->setTimeout(60);

        $linkartListe = array_keys(self::STRATEGIE_DEFAULTS);
        $strategieListe = ['alle_behalten', 'reduktion_auf_1', 'reduktion_auf_2'];

        // Kundenspezifische Linkarten in die erlaubte Liste mit aufnehmen.
        $kundenLinkartLabels = []; // fuer Prompt-Beschreibung
        if ($customerId) {
            $kl = $this->listeKundenLinkarten($customerId);
            foreach ($kl as $entry) {
                $linkartListe[] = $entry['linkart_key'];
                $kundenLinkartLabels[] = "{$entry['linkart_key']} (\"{$entry['label']}\""
                    . (!empty($entry['beschreibung']) ? ' — ' . trim($entry['beschreibung']) : '')
                    . ', default: ' . $entry['default_strategie'] . ')';
            }
        }

        $system = "Du klassifizierst Backlink-Quell-Domains aus dem Linkaufbau einer SEO-Agentur.\n"
            . "Antworte AUSSCHLIESSLICH mit einem JSON-Array, ein Eintrag pro Domain.\n"
            . "Format pro Eintrag: {\"domain\":\"...\",\"linkart\":\"...\",\"reduktionsstrategie\":\"...\","
            . "\"confidence\":0.0-1.0,\"begruendung\":\"max 200 Zeichen\"}\n"
            . "linkart: " . implode(', ', $linkartListe) . "\n"
            . (count($kundenLinkartLabels)
                ? "  Davon kundenspezifisch (nur fuer diesen Kunden waehlbar):\n  - " . implode("\n  - ", $kundenLinkartLabels) . "\n"
                : '')
            . "reduktionsstrategie: " . implode(', ', $strategieListe) . "\n"
            . "Strategien-Faustregel: spam/branchenverzeichnis/presseportal -> reduzieren; "
            . "blog/online_magazin/forum/podcast -> alle_behalten (jeder Backlink ist Inhalt). "
            . "Wenn ein Backlink-Muster zu einer kundenspezifischen Linkart passt, bevorzuge diese vor 'sonstiges'. "
            . "Confidence 0.0..1.0: 1.0 = sicher, 0.5 = unklar, 0.0 = keine Ahnung.";

        $payload = array_map(fn($d) => [
            'domain'        => $d['domain'],
            'anzahl'        => $d['anzahl'] ?? 1,
            'beispiel_urls' => array_slice($d['beispiel_urls'] ?? [], 0, 5),
            'linktexte'     => array_slice($d['linktexte'] ?? [], 0, 5),
        ], $domains);

        $userMsg = "Klassifiziere folgende Domains:" . $kundenKontext . "\n\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $antwort = $ai->chat([['role' => 'user', 'content' => $userMsg]], $system);
        } catch (\Throwable $e) {
            return ['ok' => 0, 'fehler' => count($domains),
                    'fehler_liste' => ['KI-Aufruf fehlgeschlagen: ' . $e->getMessage()]];
        }

        $text = $antwort['content'] ?? ($antwort['text'] ?? '');
        // JSON-Block extrahieren (KI antwortet manchmal mit ```json … ```)
        if (preg_match('/\[[\s\S]+\]/', $text, $m)) $text = $m[0];
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            return ['ok' => 0, 'fehler' => count($domains),
                    'fehler_liste' => ['KI-Antwort nicht parsebar: ' . substr($text, 0, 200)]];
        }

        $ok = 0; $fehler = 0; $fehlerListe = [];
        foreach ($parsed as $r) {
            $domain      = trim((string)($r['domain'] ?? ''));
            $linkart     = (string)($r['linkart'] ?? '');
            $strategie   = (string)($r['reduktionsstrategie'] ?? '');
            $confidence  = (float)($r['confidence'] ?? 0);
            $begruendung = mb_substr((string)($r['begruendung'] ?? ''), 0, 200);

            if ($domain === '' || !in_array($linkart, $linkartListe, true)) {
                $fehler++;
                $fehlerListe[] = ($domain ?: 'unbekannt') . ': ungueltige KI-Antwort';
                continue;
            }
            if (!in_array($strategie, $strategieListe, true)) {
                $strategie = $this->defaultStrategie($linkart);
            }

            $confidenceLabel = $confidence >= self::KI_CONFIDENCE_KLAR ? 'ki_bestaetigt' : 'ki_vorgeschlagen';
            // Kundenspezifische Linkart -> Eintrag pro Kunde, sonst global.
            $istKundenLinkart = $customerId && $this->istKundenspezifisch($customerId, $linkart);
            $this->speichereWissen($domain, $linkart, $strategie, $confidenceLabel, $begruendung,
                false, null, $forceRefresh, $istKundenLinkart ? $customerId : null);
            $ok++;
        }

        return [
            'ok' => $ok, 'erfolge' => $ok,
            'fehler' => $fehler, 'fehler_liste' => array_slice($fehlerListe, 0, 50),
            'modell' => self::KI_MODELL,
        ];
    }

    /**
     * User nimmt einen Vorschlag fuer eine Domain an: markiert behalten_ids als
     * bestaetigt, alle anderen werden soft-geloescht. Audit + Wissensbasis lernen.
     *
     * $auswahlGeaendert = true: User hat die KI-Vorauswahl per Klick veraendert
     *                          -> confidence wird auf 'manuell' gesetzt.
     */
    public function nehmeVorschlagAn(
        int $customerId,
        string $domain,
        string $linkart,
        string $strategie,
        array $behaltenIds,
        bool $auswahlGeaendert,
        ?int $actorUserId = null,
        ?string $urlStrategie = null,
        bool $mergeLinktext = true,
        ?string $empfehlung = null,
        ?string $zielUrl = null,
        bool $auchBestaetigt = false
    ): array {
        // Im Nachbearbeitungs-Modus auch bereits bestaetigte aktive Verlinkungen
        // mitnehmen — die hat der User im Wizard ja gerade neu entschieden.
        // Alle aktiven Verlinkungen einbeziehen (geloescht_am IS NULL reicht),
        // damit der User auch Domains mit 'disavow'/'loeschen'-Empfehlung noch
        // reduzieren oder umentscheiden kann.
        $statusFilter = $auchBestaetigt
            ? "(aufraeum_status IN ('offen','bestaetigt') OR aufraeum_status IS NULL)"
            : "(aufraeum_status = 'offen' OR aufraeum_status IS NULL)";

        $alleRows = $this->db->query(
            "SELECT id, linktext, ziel_url FROM lam_verlinkungen
             WHERE customer_id = ? AND domain = ?
               AND geloescht_am IS NULL
               AND $statusFilter",
            [$customerId, $domain]
        );
        if (!$alleRows) return ['behalten' => 0, 'geloescht' => 0];

        $alleIds = array_map(fn($r) => (string)$r['id'], $alleRows);
        $behaltenIds = array_map('strval', $behaltenIds);
        $loeschIds   = array_values(array_diff($alleIds, $behaltenIds));

        // Linktext-Merge: aus den zu loeschenden URLs den ersten nicht-leeren Linktext
        // ziehen — und ihn jeder behaltenen URL geben, die selbst keinen hat.
        // Gleiches fuer ziel_url. Damit gehen keine vom Crawler/Import erkannten
        // Linktexte verloren, nur weil eine bessere URL behalten wird.
        $mergeLinktextWert = null;
        $mergeZielUrlWert  = null;
        if ($mergeLinktext) {
            foreach ($alleRows as $r) {
                if (in_array((string)$r['id'], $loeschIds, true)) {
                    $lt = trim((string)($r['linktext'] ?? ''));
                    $zu = trim((string)($r['ziel_url'] ?? ''));
                    if ($mergeLinktextWert === null && $lt !== '') $mergeLinktextWert = $lt;
                    if ($mergeZielUrlWert  === null && $zu !== '') $mergeZielUrlWert  = $zu;
                    if ($mergeLinktextWert !== null && $mergeZielUrlWert !== null) break;
                }
            }
        }
        $mergeApplied = ['linktext' => 0, 'ziel_url' => 0];

        // Behalten: aufraeum_status='bestaetigt', linkart + Empfehlung + (optional) Linktext/ziel_url
        // Sonderfall: empfehlung='geloescht' bedeutet "ist weg" → auch soft-deleten,
        // damit die Verlinkung nicht weiter in der aktiven Liste auftaucht (sonst
        // sammeln sich inkonsistente Datensaetze: Empfehlung sagt "geloescht", aber
        // geloescht_am ist NULL → erscheinen weiter mit anzahl>=2).
        if ($behaltenIds) {
            $ph = implode(',', array_fill(0, count($behaltenIds), '?'));
            $istGeloescht = ($empfehlung === 'geloescht');
            $sets = ["aufraeum_status = 'bestaetigt'", "linkart = ?"];
            $params = [$linkart];
            if ($empfehlung !== null && $empfehlung !== '') {
                $sets[]   = "empfehlung = ?";
                $params[] = $empfehlung;
            }
            if ($istGeloescht) {
                $sets[] = "geloescht_am = NOW()";
            }
            if ($zielUrl !== null && $zielUrl !== '') {
                // Explizit gesetztes Linkziel ueberschreibt auch vorhandene Werte
                $sets[]   = "ziel_url = ?";
                $params[] = $zielUrl;
            } elseif ($mergeZielUrlWert !== null) {
                $sets[]   = "ziel_url = COALESCE(NULLIF(ziel_url,''), ?)";
                $params[] = $mergeZielUrlWert;
            }
            if ($mergeLinktextWert !== null) {
                $sets[]   = "linktext = COALESCE(NULLIF(linktext,''), ?)";
                $params[] = $mergeLinktextWert;
            }
            $this->db->execute(
                "UPDATE lam_verlinkungen SET " . implode(', ', $sets) . " WHERE id IN ($ph)",
                array_merge($params, $behaltenIds)
            );
            if ($mergeLinktextWert !== null) $mergeApplied['linktext'] = 1;
            if ($mergeZielUrlWert  !== null) $mergeApplied['ziel_url'] = 1;
            $this->protokolliereEntscheidung($customerId, $domain, $behaltenIds, true, 'behalten', $actorUserId);
        }

        // Loeschen: soft delete
        if ($loeschIds) {
            $ph = implode(',', array_fill(0, count($loeschIds), '?'));
            $this->db->execute(
                "UPDATE lam_verlinkungen
                 SET geloescht_am = NOW(),
                     aufraeum_status = 'bestaetigt'
                 WHERE id IN ($ph)",
                $loeschIds
            );
            $this->protokolliereEntscheidung($customerId, $domain, $loeschIds, false, 'duplikat', $actorUserId);
        }

        // Wissensbasis lernen.
        // Wenn der User die Auswahl manuell veraendert hat, schauen wir uns die
        // behaltenen IDs an und leiten daraus eine URL-Praeferenz ab.
        // Bei kundenspezifischer Linkart wird der Wissens-Eintrag auch nur fuer
        // diesen Kunden gespeichert (customer_id gesetzt).
        $confidence = $auswahlGeaendert ? 'manuell' : 'ki_bestaetigt';
        if ($urlStrategie === null && $auswahlGeaendert && count($behaltenIds) > 0) {
            $urlStrategie = $this->leiteUrlStrategieAb($alleIds, $behaltenIds);
        }
        $istKundenLinkart = $this->istKundenspezifisch($customerId, $linkart);
        $this->speichereWissen($domain, $linkart, $strategie, $confidence, null, true,
            $urlStrategie, false, $istKundenLinkart ? $customerId : null, $empfehlung);

        AuditLog::record('lam_verlinkung', $domain, 'aufraeum.angenommen', [
            'kunde'   => $customerId,
            'linkart' => $linkart,
            'strategie' => $strategie,
            'behalten'  => count($behaltenIds),
            'geloescht' => count($loeschIds),
            'manuell' => $auswahlGeaendert,
        ]);

        return [
            'domain'    => $domain,
            'behalten'  => count($behaltenIds),
            'geloescht' => count($loeschIds),
            'linkart'   => $linkart,
            'strategie' => $strategie,
            'confidence' => $confidence,
            'merge'     => $mergeApplied, // welche Felder gemerged wurden
        ];
    }

    /**
     * Extrahiert Domain-Namen aus einem Freitext (Kunden-Kontext) und filtert
     * auf die, die im Linkprofil dieses Kunden tatsaechlich vorkommen.
     * Wird beim Speichern der Notiz aufgerufen, um gezielt nur die erwaehnten
     * Domains zu re-klassifizieren — token-sparsam.
     */
    public function findeDomainsInNotiz(int $customerId, string $notiz): array
    {
        if (trim($notiz) === '') return [];
        // Eine recht weite Regex: TLDs 2..24 Zeichen, Hostnamen mit Subdomain
        preg_match_all('/\b([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)+)\b/i', $notiz, $m);
        $kandidaten = array_unique(array_map('strtolower', $m[1] ?? []));
        if (!$kandidaten) return [];

        $ph = implode(',', array_fill(0, count($kandidaten), '?'));
        $rows = $this->db->query(
            "SELECT DISTINCT domain FROM lam_verlinkungen
             WHERE customer_id = ?
               AND geloescht_am IS NULL
               AND LOWER(domain) IN ($ph)",
            array_merge([$customerId], $kandidaten)
        );
        return array_column($rows, 'domain');
    }

    /**
     * Re-klassifiziert eine konkrete Liste von Domain-Namen (z.B. die in der
     * Kunden-Notiz erwaehnten) per KI mit force_refresh — auch wenn bereits
     * 'ki_bestaetigt' in der Wissensbasis steht. 'manuell' bleibt unberuehrt.
     */
    public function reklassifiziereDomains(int $customerId, array $domainNamen): array
    {
        if (!$domainNamen) return ['ok' => 0, 'fehler' => 0];
        // Pro Domain Samples bauen — gleiches Schema wie in getDomainVorschlaege
        $payload = [];
        foreach ($domainNamen as $name) {
            $r = $this->db->queryOne(
                "SELECT COUNT(*) AS anzahl,
                        GROUP_CONCAT(DISTINCT linktext ORDER BY LENGTH(linktext) DESC SEPARATOR '||') AS lt,
                        GROUP_CONCAT(DISTINCT verlinkende_url ORDER BY LENGTH(verlinkende_url) ASC SEPARATOR '||') AS urls
                 FROM lam_verlinkungen
                 WHERE customer_id = ? AND domain = ? AND geloescht_am IS NULL",
                [$customerId, $name]
            );
            $payload[] = [
                'domain'        => $name,
                'anzahl'        => (int)($r['anzahl'] ?? 0),
                'beispiel_urls' => array_slice(array_filter(explode('||', (string)($r['urls'] ?? ''))), 0, 5),
                'linktexte'     => array_slice(array_filter(explode('||', (string)($r['lt']   ?? ''))), 0, 5),
            ];
        }
        return $this->klassifiziereUnbekannteBatch($payload, $customerId, true);
    }

    /**
     * Macht die letzte Aufraeum-Aktion fuer eine Domain rueckgaengig:
     * setzt aufraeum_status zurueck auf 'offen', stellt geloescht_am=NULL.
     * Findet die zugehoerigen verlinkung_ids ueber die zuletzt geschriebenen
     * Eintraege in lam_aufraeum_entscheidung. Lookup-Fenster: 1 Stunde.
     */
    public function macheRueckgaengig(int $customerId, string $domain): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT verlinkung_id
             FROM lam_aufraeum_entscheidung
             WHERE customer_id = ? AND domain = ?
               AND erstellt_am > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$customerId, $domain]
        );
        if (!$rows) return ['wiederhergestellt' => 0];

        $ids = array_map(fn($r) => (int)$r['verlinkung_id'], $rows);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $n = $this->db->execute(
            "UPDATE lam_verlinkungen
             SET geloescht_am = NULL,
                 aufraeum_status = 'offen'
             WHERE id IN ($ph)",
            $ids
        );
        // Audit der Undo-Aktion: alte Eintraege loeschen, damit erneutes Uebernehmen sauber zaehlt
        $this->db->execute(
            "DELETE FROM lam_aufraeum_entscheidung
             WHERE customer_id = ? AND domain = ?
               AND erstellt_am > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$customerId, $domain]
        );
        AuditLog::record('lam_verlinkung', $domain, 'aufraeum.rueckgaengig', [
            'kunde' => $customerId, 'wiederhergestellt' => $n,
        ]);
        return ['wiederhergestellt' => $n, 'domain' => $domain];
    }

    /**
     * Bulk-Annahme aller "klaren" Domains in einem Rutsch.
     * Wird chunked vom Frontend aufgerufen, damit ein Fortschritts-Modal Sinn macht.
     */
    public function nehmeBulkAn(int $customerId, array $domainSpecs, ?int $actorUserId = null): array
    {
        $ok = 0; $behalten = 0; $geloescht = 0; $fehler = []; $domainsFertig = [];
        foreach ($domainSpecs as $s) {
            try {
                $detail = $this->getDomainDetail($customerId, $s['domain'], $s['strategie'] ?? null);
                if (!$detail['verlinkungen']) continue; // schon abgehandelt
                $r = $this->nehmeVorschlagAn(
                    $customerId,
                    $s['domain'],
                    $s['linkart'],
                    $s['strategie'] ?? $detail['strategie'],
                    $detail['behalten_ids'],
                    false,
                    $actorUserId
                );
                $ok++;
                $behalten += $r['behalten'];
                $geloescht += $r['geloescht'];
                $domainsFertig[] = $s['domain'];
            } catch (\Throwable $e) {
                $fehler[] = ($s['domain'] ?? 'unbekannt') . ': ' . $e->getMessage();
            }
        }
        return [
            'ok' => $ok, 'erfolge' => $ok,
            'behalten' => $behalten, 'geloescht' => $geloescht,
            'fehler' => count($fehler), 'fehler_liste' => $fehler,
            'domains_fertig' => $domainsFertig,
        ];
    }

    /* ============================================================
     *  HELPER (private)
     * ============================================================ */

    /**
     * Versucht aus der User-Auswahl abzuleiten, ob er Deeplinks oder die Root-URL
     * bevorzugt. Vergleicht die durchschnittliche Tiefe der "behalten"-URLs
     * mit der durchschnittlichen Tiefe aller IDs.
     */
    private function leiteUrlStrategieAb(array $alleIds, array $behaltenIds): ?string
    {
        $alleIds      = array_map('strval', $alleIds);
        $behaltenIds  = array_map('strval', $behaltenIds);
        if (!$behaltenIds || count($behaltenIds) === count($alleIds)) return null;

        $ph = implode(',', array_fill(0, count($alleIds), '?'));
        $rows = $this->db->query(
            "SELECT id, verlinkende_url FROM lam_verlinkungen WHERE id IN ($ph)",
            $alleIds
        );
        if (!$rows) return null;

        $tiefen = []; $behaltenTiefen = [];
        foreach ($rows as $r) {
            $t = $this->urlTiefe($r['verlinkende_url']);
            $tiefen[] = $t;
            if (in_array((string)$r['id'], $behaltenIds, true)) $behaltenTiefen[] = $t;
        }
        if (!$behaltenTiefen) return null;

        $avgAlle     = array_sum($tiefen) / count($tiefen);
        $avgBehalten = array_sum($behaltenTiefen) / count($behaltenTiefen);

        // Schwelle: Differenz von >= 1 Pfad-Segment im Durchschnitt
        if ($avgBehalten >= $avgAlle + 1) return 'tiefste';
        if ($avgBehalten <= $avgAlle - 1) return 'kuerzeste';
        return null; // ambivalent -> nicht aendern
    }

    private function istKundenspezifisch(int $customerId, string $linkartKey): bool
    {
        $n = $this->db->queryValue(
            "SELECT COUNT(*) FROM lam_kunde_linkart
             WHERE customer_id = ? AND linkart_key = ? AND geloescht_am IS NULL",
            [$customerId, $linkartKey]
        );
        return (int)$n > 0;
    }

    private function speichereWissen(
        string $domain,
        string $linkart,
        string $strategie,
        string $confidence,
        ?string $begruendung,
        bool $istManuell = false,
        ?string $urlStrategie = null,
        bool $forceRefresh = false,
        ?int $customerId = null,
        ?string $empfehlung = null
    ): void {
        // confidence-Hierarchie: manuell > ki_bestaetigt > ki_vorgeschlagen.
        // forceRefresh erlaubt KI, ki_bestaetigt zu ueberschreiben — aber NICHT manuell.
        // Kundenspezifischer Eintrag (customer_id gesetzt) wird separat vom globalen gehalten.
        if ($customerId !== null) {
            $bestehend = $this->db->queryOne(
                "SELECT id, confidence FROM lam_domain_wissen WHERE domain = ? AND customer_id = ?",
                [$domain, $customerId]
            );
        } else {
            $bestehend = $this->db->queryOne(
                "SELECT id, confidence FROM lam_domain_wissen WHERE domain = ? AND customer_id IS NULL",
                [$domain]
            );
        }
        if ($bestehend && $bestehend['confidence'] === 'manuell' && !$istManuell) {
            return; // manuelle Pflege schlaegt KI immer
        }
        // ki_vorgeschlagen darf von neuen KI-Vorschlaegen ueberschrieben werden.
        // ki_bestaetigt nur wenn forceRefresh oder neuer Wert ebenfalls bestaetigt ist.
        if ($bestehend && $bestehend['confidence'] === 'ki_bestaetigt' && !$istManuell && !$forceRefresh && $confidence === 'ki_vorgeschlagen') {
            return;
        }

        if ($bestehend) {
            $extra = [];
            $eparams = [];
            if ($urlStrategie !== null) { $extra[] = 'url_score_strategie = ?'; $eparams[] = $urlStrategie; }
            if ($empfehlung   !== null) { $extra[] = 'empfehlung_default = ?'; $eparams[] = $empfehlung; }
            $extraSql = $extra ? ', ' . implode(', ', $extra) : '';
            $params = array_merge(
                [$linkart, $strategie, $confidence, $begruendung, $istManuell ? 1 : 0],
                $eparams,
                [$bestehend['id']]
            );
            $this->db->execute(
                "UPDATE lam_domain_wissen
                 SET linkart = ?, reduktionsstrategie = ?, confidence = ?,
                     ki_begruendung = COALESCE(?, ki_begruendung),
                     letzte_korrektur_am = " . ($istManuell ? 'NOW()' : 'letzte_korrektur_am') . ",
                     manuell_gepflegt = ?
                     {$extraSql},
                     anzahl_klassifikationen = COALESCE(anzahl_klassifikationen,0) + 1
                 WHERE id = ?",
                $params
            );
        } else {
            $this->db->execute(
                "INSERT INTO lam_domain_wissen
                    (domain, customer_id, linkart, reduktionsstrategie, empfehlung_default,
                     confidence, ki_begruendung, manuell_gepflegt, url_score_strategie,
                     anzahl_klassifikationen, letzte_korrektur_am, erstellt_am)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, " . ($istManuell ? 'NOW()' : 'NULL') . ", NOW())",
                [$domain, $customerId, $linkart, $strategie, $empfehlung, $confidence, $begruendung,
                 $istManuell ? 1 : 0, $urlStrategie ?? 'auto']
            );
        }
    }

    private function protokolliereEntscheidung(
        int $customerId, string $domain, array $verlinkungIds,
        bool $behalten, string $grund, ?int $actorUserId
    ): void {
        foreach ($verlinkungIds as $vid) {
            $this->db->execute(
                "INSERT INTO lam_aufraeum_entscheidung
                    (id, verlinkung_id, domain, customer_id, behalten, score_grund, actor_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$this->generiereUlid(), (string)$vid, $domain, $customerId,
                 $behalten ? 1 : 0, $grund, $actorUserId]
            );
        }
    }

    private function berechneScore(array $row, array $gewichte, float $maxSi): float
    {
        $si       = (float)($row['sistrix_index'] ?? 0);
        $http     = (int)($row['letzter_http_erreichbar'] ?? 0);
        $tiefe    = $this->urlTiefe($row['verlinkende_url'] ?? '');
        $linktext = trim((string)($row['linktext'] ?? '')) !== '' ? 1 : 0;
        $alterTage = $this->alterInTagen($row['erstellt_am'] ?? null);

        $siNorm   = $maxSi > 0 ? min(1.0, $si / $maxSi) : 0;
        $tiefeNorm = 1 / (1 + max(0, $tiefe));
        $neuheitNorm = max(0, 1 - ($alterTage / 365));

        $score =
              ($gewichte['si']       ?? 0.4) * $siNorm
            + ($gewichte['http']     ?? 0.25) * $http
            + ($gewichte['tiefe']    ?? 0.15) * $tiefeNorm
            + ($gewichte['linktext'] ?? 0.15) * $linktext
            + ($gewichte['neuheit']  ?? 0.05) * $neuheitNorm;

        return round($score, 4);
    }

    private function urlTiefe(string $url): int
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');
        if ($path === '') return 0;
        return substr_count($path, '/') + 1;
    }

    private function alterInTagen(?string $dt): int
    {
        if (!$dt) return 9999;
        try { $d = new \DateTime($dt); }
        catch (\Throwable $e) { return 9999; }
        return (int)((time() - $d->getTimestamp()) / 86400);
    }

    private function bestimmeBehaltenIds(array $sortierteRows, string $strategie): array
    {
        // IDs sind ULIDs (VARCHAR(26)) — NICHT zu int casten, sonst wird '01KSM...' zu 1!
        if ($strategie === 'alle_behalten' || !$sortierteRows) {
            return array_map(fn($r) => (string)$r['id'], $sortierteRows);
        }
        $n = $strategie === 'reduktion_auf_1' ? 1 : ($strategie === 'reduktion_auf_2' ? 2 : count($sortierteRows));
        $top = array_slice($sortierteRows, 0, $n);
        return array_map(fn($r) => (string)$r['id'], $top);
    }

    private function defaultStrategie(?string $linkart): string
    {
        return self::STRATEGIE_DEFAULTS[$linkart] ?? 'alle_behalten';
    }

    public function gewichteFuerLinkart(?string $linkart): array
    {
        static $cache = null;
        if ($cache === null) {
            $json = Settings::get('lam_aufraeum_gewichte');
            $cache = $json ? (json_decode($json, true) ?: []) : [];
        }
        $default = $cache['default'] ?? ['si' => 0.4, 'http' => 0.25, 'tiefe' => 0.15, 'linktext' => 0.15, 'neuheit' => 0.05];
        return ($linkart && isset($cache[$linkart])) ? array_merge($default, $cache[$linkart]) : $default;
    }

    /**
     * Ermittelt das Linkziel einer Verlinkung durch Crawl der Quell-URL.
     * Faellt durch alle Anker, sucht den ersten der auf die Kunden-Domain zeigt.
     * Returns null wenn Quelle nicht erreichbar, kein passender Anker gefunden,
     * oder Kunden-Domain nicht hinterlegt.
     */
    public function ermittleLinkziel(string $verlinkungId, int $customerId): array
    {
        $row = $this->db->queryOne(
            "SELECT v.verlinkende_url, v.ziel_url AS aktuelles_ziel, c.website AS kunden_website
             FROM lam_verlinkungen v
             LEFT JOIN customers c ON c.id = v.customer_id
             WHERE v.id = ? AND v.customer_id = ?",
            [$verlinkungId, $customerId]
        );
        if (!$row) return ['erfolg' => false, 'fehler' => 'Verlinkung nicht gefunden'];

        $sourceUrl = trim((string)($row['verlinkende_url'] ?? ''));
        $kundenWebsite = trim((string)($row['kunden_website'] ?? ''));
        if ($sourceUrl === '') return ['erfolg' => false, 'fehler' => 'Keine Quell-URL hinterlegt'];
        if ($kundenWebsite === '') return ['erfolg' => false, 'fehler' => 'Kunden-Website fehlt (Kundenstamm pflegen)'];

        $kundenHost = parse_url($kundenWebsite, PHP_URL_HOST) ?: '';
        // 'www.fryka.de' -> 'fryka.de' fuer flexibleres Matching
        $kundenHostStripped = preg_replace('/^www\./i', '', $kundenHost);
        if ($kundenHostStripped === '') return ['erfolg' => false, 'fehler' => 'Kunden-Host nicht parsebar'];

        // HTML der Quell-URL abrufen — als realistischer Browser maskiert,
        // weil viele Sites (z.B. Verlage mit WAF) Bot-UAs mit 403 blocken.
        $fetch = $this->fetchHtml($sourceUrl);
        if ($fetch['fehler']) {
            return ['erfolg' => false, 'fehler' => $fetch['fehler']];
        }
        $html = $fetch['html'];

        // Alle <a href="..."> herausfischen
        if (!preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return ['erfolg' => false, 'fehler' => 'Keine Anker im HTML gefunden'];
        }

        $kandidaten = [];
        foreach ($matches[1] as $href) {
            $href = trim($href);
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) continue;
            // Relative URLs zur Quelle aufloesen
            if (!preg_match('/^https?:\/\//i', $href)) {
                $href = $this->resolveUrl($sourceUrl, $href);
                if (!$href) continue;
            }
            $hrefHost = parse_url($href, PHP_URL_HOST) ?: '';
            $hrefHostStripped = preg_replace('/^www\./i', '', $hrefHost);
            if ($hrefHostStripped === '') continue;
            // Match wenn Kunden-Domain im Host (auch Subdomains)
            if (stripos($hrefHostStripped, $kundenHostStripped) !== false
                || stripos($kundenHostStripped, $hrefHostStripped) !== false) {
                $kandidaten[] = $href;
            }
        }

        $kandidaten = array_values(array_unique($kandidaten));
        if (!$kandidaten) {
            return ['erfolg' => false, 'fehler' => "Kein Link zu $kundenHostStripped auf der Quell-Seite gefunden"];
        }

        return [
            'erfolg' => true,
            'vorschlag' => $kandidaten[0],
            'alle_treffer' => $kandidaten,
            'anzahl' => count($kandidaten),
            'kunden_host' => $kundenHostStripped,
        ];
    }

    /**
     * Holt HTML einer URL mit Browser-Maskierung. Versucht bei 403 zusaetzlich
     * eine zweite Runde mit anderer UA — manche WAFs sind sehr UA-spezifisch.
     * Returns ['html' => string|null, 'fehler' => string|null]
     */
    private function fetchHtml(string $url): array
    {
        $browserUAs = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        ];
        $browserHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];

        $lastError = '';
        $lastCode = 0;
        foreach ($browserUAs as $ua) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL             => $url,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_FOLLOWLOCATION  => true,
                CURLOPT_MAXREDIRS       => 5,
                CURLOPT_TIMEOUT         => 12,
                CURLOPT_CONNECTTIMEOUT  => 5,
                CURLOPT_USERAGENT       => $ua,
                CURLOPT_HTTPHEADER      => $browserHeaders,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => 0,
                CURLOPT_ACCEPT_ENCODING => '', // alle vom System unterstuetzten Encodings
                CURLOPT_REFERER         => 'https://www.google.com/',
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $lastError = curl_error($ch);
            $lastCode = $httpCode;
            curl_close($ch);

            if ($html !== false && $html !== '' && $httpCode < 400) {
                return ['html' => $html, 'fehler' => null];
            }
            // Bei 403/429 noch zweite UA versuchen, sonst gleich abbrechen
            if (!in_array($httpCode, [403, 429, 0], true)) break;
        }

        if ($lastCode >= 400) {
            return ['html' => null, 'fehler' => "Quell-URL antwortet mit HTTP $lastCode (vermutlich Bot-Block)"];
        }
        return ['html' => null, 'fehler' => 'Quell-URL nicht erreichbar' . ($lastError ? " ($lastError)" : '')];
    }

    /** Loest eine relative URL gegen eine Basis-URL auf. */
    private function resolveUrl(string $base, string $rel): ?string
    {
        $p = parse_url($base);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return null;
        if (str_starts_with($rel, '//')) return $p['scheme'] . ':' . $rel;
        if (str_starts_with($rel, '/'))  return $p['scheme'] . '://' . $p['host'] . $rel;
        // Sonst relativ zum aktuellen Pfad
        $basePath = $p['path'] ?? '/';
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
        return $p['scheme'] . '://' . $p['host'] . $dir . $rel;
    }

    private function generiereUlid(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time = (int)(microtime(true) * 1000);
        $t = '';
        for ($i = 0; $i < 10; $i++) { $t = $alphabet[$time % 32] . $t; $time = (int)($time / 32); }
        $r = '';
        for ($i = 0; $i < 16; $i++) $r .= $alphabet[random_int(0, 31)];
        return $t . $r;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Per-Domain-Notiz + Muster-Erkennung
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Liest die Per-Domain-Notiz. Bevorzugt kundenspezifischen Eintrag, faellt
     * auf globalen Eintrag zurueck.
     */
    public function getDomainNotiz(int $customerId, string $domain): ?string
    {
        $row = $this->db->queryOne(
            "SELECT notiz FROM lam_domain_wissen
             WHERE domain = ? AND (customer_id = ? OR customer_id IS NULL)
             ORDER BY customer_id IS NULL ASC LIMIT 1",
            [$domain, $customerId]
        );
        return self::filtereNotiz($row['notiz'] ?? null);
    }

    /**
     * Filtert Legacy-Auto-Texte aus, die der alte Laravel-Prototyp in notiz
     * geschrieben hat. Diese Texte sind keine echten User-Notizen.
     */
    private static function filtereNotiz(?string $notiz): ?string
    {
        if ($notiz === null) return null;
        $trim = trim($notiz);
        if ($trim === '') return null;
        if (str_contains($trim, 'Automatisch beim Linkart-Bulk angelegt')) return null;
        return $notiz;
    }

    /**
     * Setzt die Per-Domain-Notiz. Legt den Wissens-Eintrag bei Bedarf an, wenn
     * die Domain noch unklassifiziert ist (mit Defaults), damit die Notiz
     * persistiert werden kann.
     */
    public function setDomainNotiz(int $customerId, string $domain, ?string $notiz): void
    {
        $notiz = $notiz !== null ? mb_substr(trim($notiz), 0, 4000) : null;

        $bestehend = $this->db->queryOne(
            "SELECT id FROM lam_domain_wissen WHERE domain = ? AND customer_id = ?",
            [$domain, $customerId]
        );

        if ($bestehend) {
            $this->db->execute(
                "UPDATE lam_domain_wissen SET notiz = ?, aktualisiert_am = NOW() WHERE id = ?",
                [$notiz, $bestehend['id']]
            );
            return;
        }

        // Wenn noch kein kundenspezifischer Eintrag existiert, lege einen schlanken an.
        // Globale Eintraege werden nicht ueberschrieben — die Notiz ist kundenspezifisch.
        $globalRef = $this->db->queryOne(
            "SELECT linkart, reduktionsstrategie, empfehlung_default
             FROM lam_domain_wissen WHERE domain = ? AND customer_id IS NULL LIMIT 1",
            [$domain]
        );

        $this->db->execute(
            "INSERT INTO lam_domain_wissen
                (domain, customer_id, linkart, reduktionsstrategie, empfehlung_default,
                 confidence, notiz, manuell_gepflegt, anzahl_klassifikationen, erstellt_am)
             VALUES (?, ?, ?, ?, ?, 'manuell', ?, 0, 1, NOW())",
            [
                $domain, $customerId,
                $globalRef['linkart'] ?? 'sonstiges',
                $globalRef['reduktionsstrategie'] ?? 'alle_behalten',
                $globalRef['empfehlung_default'] ?? null,
                $notiz,
            ]
        );
    }

    /**
     * Liste der Muster fuer einen Kunden. Standardmaessig sowohl bestaetigte
     * als auch Vorschlaege, optional gefiltert.
     */
    public function listeMuster(int $customerId, ?bool $nurBestaetigt = null): array
    {
        $sql = "SELECT * FROM lam_aufraeum_muster
                WHERE (customer_id = ? OR customer_id IS NULL)";
        $params = [$customerId];
        if ($nurBestaetigt === true)  { $sql .= " AND bestaetigt = 1"; }
        if ($nurBestaetigt === false) { $sql .= " AND bestaetigt = 0"; }
        $sql .= " ORDER BY bestaetigt ASC, erstellt_am DESC LIMIT 200";
        return $this->db->query($sql, $params);
    }

    /**
     * KI extrahiert aus der Notiz ein oder mehrere Muster-Kandidaten. Wird
     * im UI angezeigt; der User bestaetigt jedes einzeln.
     *
     * Returns Array von Vorschlaegen: [{muster_typ, muster_value, aktion_linkart,
     * aktion_strategie, aktion_empfehlung, beschreibung}, ...]
     */
    public function analysiereDomainNotiz(int $customerId, string $domain, string $notiz): array
    {
        require_once SERVICES_PATH . '/AIService.php';
        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) throw new \RuntimeException('Anthropic-API-Key nicht gesetzt.');

        // Aktuelle Wissensbasis-Eintraege fuer diese Domain liefern Kontext.
        $aktuellesWissen = $this->db->queryOne(
            "SELECT linkart, reduktionsstrategie, empfehlung_default, ki_begruendung
             FROM lam_domain_wissen
             WHERE domain = ? AND (customer_id = ? OR customer_id IS NULL)
             ORDER BY customer_id IS NULL ASC LIMIT 1",
            [$domain, $customerId]
        );

        $linkartListe = array_keys(self::STRATEGIE_DEFAULTS);
        foreach ($this->listeKundenLinkarten($customerId) as $kl) {
            $linkartListe[] = $kl['linkart_key'];
        }

        $system = "Du analysierst Notizen einer SEO-Agentur zu einer Backlink-Quell-Domain "
            . "und extrahierst daraus generalisierbare Muster, die auf aehnliche Verlinkungen "
            . "uebertragen werden koennen.\n\n"
            . "Antworte AUSSCHLIESSLICH als JSON-Array (max 3 Eintraege, oder leer wenn nichts generalisierbar). "
            . "Pro Eintrag: {\"muster_typ\":\"domain_suffix|domain_pattern|linktext_pattern|ziel_url_pattern|keyword\","
            . "\"muster_value\":\"...\",\"aktion_linkart\":\"...|null\","
            . "\"aktion_strategie\":\"alle_behalten|reduktion_auf_1|reduktion_auf_2|null\","
            . "\"aktion_empfehlung\":\"lassen|aendern|loeschen|disavow|unsicher|null\","
            . "\"beschreibung\":\"max 120 Zeichen — warum dieses Muster\"}\n\n"
            . "muster_typ-Erklaerung (KONTEXT: 'Quell-URL' = die Seite auf der der Backlink steht, "
            . "'Ziel-URL' = die Kunden-URL, zu der der Link FUEHRT):\n"
            . "- domain_suffix: Quell-Domain endet auf X, z.B. '.blogspot.com' oder 'wordpress.com'\n"
            . "- domain_pattern: Quell-Domain enthaelt X, z.B. 'blog' oder 'nabu'\n"
            . "- linktext_pattern: Linktext enthaelt X, z.B. 'cookie-banner' oder 'impressum'\n"
            . "- ziel_url_pattern: Ziel-URL (auf die der Link zeigt) enthaelt X, z.B. '/datenschutz' "
            . "oder 'video-stream-hosting.com/datenschutz' oder '/impressum.html'. "
            . "DIESEN TYP NUTZEN wenn die Notiz beschreibt, wohin die Links gehen.\n"
            . "- keyword: Quell-URL-Pfad enthaelt X, z.B. '/forum/' oder '/karriere/'\n\n"
            . "linkart-WERTE (genau einer dieser Strings als aktion_linkart, sonst null):\n  "
            . implode(', ', $linkartListe) . "\n\n"
            . "REGELN:\n"
            . "1. Wenn die Notiz Woerter wie 'alle', 'jede', 'jeder' enthaelt → ZWINGEND ein Muster extrahieren.\n"
            . "2. Wenn die Notiz eine URL/Domain mit '/' nennt → ziel_url_pattern oder keyword nutzen.\n"
            . "3. Wenn die Notiz eine Aktion vorgibt ('sind X', 'auf Y stellen', 'als Z klassifizieren') → "
            . "aktion_linkart/aktion_empfehlung entsprechend setzen.\n"
            . "4. NUR LEERES ARRAY [] zurueckgeben, wenn die Notiz keinerlei Generalisierungsabsicht hat "
            . "(z.B. 'diese Domain hier ist seit 2020 inaktiv').\n\n"
            . "BEISPIEL 1 (PFLICHT-Extraktion):\n"
            . "Notiz: 'Alle Verlinkungen auf video-stream-hosting.com/datenschutz sind Datenschutz-Verweis und koennen wir auf lassen stellen'\n"
            . "Antwort: [{\"muster_typ\":\"ziel_url_pattern\",\"muster_value\":\"video-stream-hosting.com/datenschutz\","
            . "\"aktion_linkart\":\"datenschutz_verweis\",\"aktion_strategie\":\"alle_behalten\",\"aktion_empfehlung\":\"lassen\","
            . "\"beschreibung\":\"Alle Links zur Datenschutz-Seite sind Datenschutz-Verweise\"}]\n\n"
            . "BEISPIEL 2 (Einzelfall, leeres Array):\n"
            . "Notiz: 'Mit Webmaster gesprochen, Link bleibt bis Q3'\n"
            . "Antwort: []";

        $user = "Domain: $domain\n";
        if ($aktuellesWissen) {
            $user .= "Aktuelle Klassifikation: linkart={$aktuellesWissen['linkart']}, "
                . "strategie={$aktuellesWissen['reduktionsstrategie']}\n";
        }
        $user .= "\nNotiz des Users:\n" . trim($notiz);

        $ai = new \Services\AIService($apiKey, 'anthropic');
        $ai->setModel(self::KI_MODELL);
        $ai->setMaxTokens(1500);
        $ai->setTimeout(30);

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $text = trim($antwort['content'] ?? ($antwort['text'] ?? ''));
        if (preg_match('/\[[\s\S]*\]/', $text, $m)) $text = $m[0];
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) return [];

        $erlaubteTypen = ['domain_suffix','domain_pattern','linktext_pattern','ziel_url_pattern','keyword','url_pattern'];
        $erlaubteEmpf  = ['lassen','aendern','loeschen','disavow','unsicher'];
        $erlaubteStrat = ['alle_behalten','reduktion_auf_1','reduktion_auf_2'];
        $out = [];
        foreach ($parsed as $v) {
            $typ = (string)($v['muster_typ'] ?? '');
            $val = trim((string)($v['muster_value'] ?? ''));
            if (!in_array($typ, $erlaubteTypen, true) || $val === '') continue;
            $linkart = $v['aktion_linkart'] ?? null;
            if ($linkart !== null && !in_array($linkart, $linkartListe, true)) $linkart = null;
            $strat = $v['aktion_strategie'] ?? null;
            if ($strat !== null && !in_array($strat, $erlaubteStrat, true)) $strat = null;
            $empf  = $v['aktion_empfehlung'] ?? null;
            if ($empf !== null && !in_array($empf, $erlaubteEmpf, true)) $empf = null;
            $out[] = [
                'muster_typ'        => $typ,
                'muster_value'      => mb_substr($val, 0, 500),
                'aktion_linkart'    => $linkart,
                'aktion_strategie'  => $strat,
                'aktion_empfehlung' => $empf,
                'beschreibung'      => mb_substr((string)($v['beschreibung'] ?? ''), 0, 500),
                'treffer_count'     => $this->zaehlePassendeDomains($customerId, $typ, $val),
            ];
        }
        return $out;
    }

    /**
     * Speichert ein vom User bestaetigtes Muster.
     */
    public function speichereMuster(int $customerId, array $daten, ?int $actorUserId = null,
                                    string $herkunft = 'manuell', ?string $ursprungsDomain = null,
                                    ?string $ursprungsNotiz = null): int
    {
        return $this->db->insert('lam_aufraeum_muster', [
            'customer_id'       => $customerId,
            'muster_typ'        => $daten['muster_typ'],
            'muster_value'      => mb_substr($daten['muster_value'], 0, 500),
            'aktion_linkart'    => $daten['aktion_linkart']    ?? null,
            'aktion_strategie'  => $daten['aktion_strategie']  ?? null,
            'aktion_empfehlung' => $daten['aktion_empfehlung'] ?? null,
            'beschreibung'      => mb_substr((string)($daten['beschreibung'] ?? ''), 0, 500),
            'herkunft'          => $herkunft,
            'bestaetigt'        => 1,
            'ursprungs_domain'  => $ursprungsDomain,
            'ursprungs_notiz'   => $ursprungsNotiz,
            'erstellt_von'      => $actorUserId,
            'bestaetigt_am'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function loescheMuster(int $musterId, int $customerId): bool
    {
        $r = $this->db->execute(
            "DELETE FROM lam_aufraeum_muster
             WHERE id = ? AND (customer_id = ? OR customer_id IS NULL)",
            [$musterId, $customerId]
        );
        return $r > 0;
    }

    /**
     * Schnell-Zaehler: wieviele unklassifizierte/unbestimmte Domains des Kunden
     * matchen das Muster. Wird neben dem KI-Vorschlag angezeigt.
     */
    public function zaehlePassendeDomains(int $customerId, string $musterTyp, string $musterValue): int
    {
        $matches = $this->findePassendeDomains($customerId, $musterTyp, $musterValue, 500);
        return count($matches);
    }

    /**
     * Findet Domains des Kunden, die dieses Muster matchen. Geht durch alle
     * offenen Verlinkungen + ggf. Linktexte (je nach muster_typ).
     */
    public function findePassendeDomains(int $customerId, string $musterTyp, string $musterValue, int $limit = 200): array
    {
        $musterValue = trim($musterValue);
        if ($musterValue === '') return [];

        if ($musterTyp === 'domain_suffix') {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], ltrim($musterValue, '.')) ;
            $rows = $this->db->query(
                "SELECT v.domain, COUNT(*) AS anzahl
                 FROM lam_verlinkungen v
                 WHERE v.customer_id = ? AND v.geloescht_am IS NULL
                   AND (v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)
                   AND v.domain LIKE ?
                 GROUP BY v.domain LIMIT ?",
                [$customerId, $likeVal, $limit]
            );
            return $rows;
        }

        if ($musterTyp === 'domain_pattern') {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $musterValue) . '%';
            $rows = $this->db->query(
                "SELECT v.domain, COUNT(*) AS anzahl
                 FROM lam_verlinkungen v
                 WHERE v.customer_id = ? AND v.geloescht_am IS NULL
                   AND (v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)
                   AND v.domain LIKE ?
                 GROUP BY v.domain LIMIT ?",
                [$customerId, $likeVal, $limit]
            );
            return $rows;
        }

        if ($musterTyp === 'linktext_pattern') {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $musterValue) . '%';
            $rows = $this->db->query(
                "SELECT v.domain, COUNT(*) AS anzahl
                 FROM lam_verlinkungen v
                 WHERE v.customer_id = ? AND v.geloescht_am IS NULL
                   AND (v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)
                   AND v.linktext LIKE ?
                 GROUP BY v.domain LIMIT ?",
                [$customerId, $likeVal, $limit]
            );
            return $rows;
        }

        if ($musterTyp === 'ziel_url_pattern') {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $musterValue) . '%';
            $rows = $this->db->query(
                "SELECT v.domain, COUNT(*) AS anzahl
                 FROM lam_verlinkungen v
                 WHERE v.customer_id = ? AND v.geloescht_am IS NULL
                   AND (v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)
                   AND v.ziel_url LIKE ?
                 GROUP BY v.domain LIMIT ?",
                [$customerId, $likeVal, $limit]
            );
            return $rows;
        }

        if ($musterTyp === 'keyword' || $musterTyp === 'url_pattern') {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $musterValue) . '%';
            $rows = $this->db->query(
                "SELECT v.domain, COUNT(*) AS anzahl
                 FROM lam_verlinkungen v
                 WHERE v.customer_id = ? AND v.geloescht_am IS NULL
                   AND (v.aufraeum_status = 'offen' OR v.aufraeum_status IS NULL)
                   AND (v.verlinkende_url LIKE ? OR v.ziel_url LIKE ?)
                 GROUP BY v.domain LIMIT ?",
                [$customerId, $likeVal, $likeVal, $limit]
            );
            return $rows;
        }

        return [];
    }

    /**
     * Wendet ein Muster an: alle matchenden Domains, die aktuell ki_vorgeschlagen
     * oder unklassifiziert sind, werden mit der Muster-Aktion in die Wissensbasis
     * gesetzt (confidence='ki_bestaetigt', damit naechste KI-Runde sie nicht
     * mehr veraendert). Manuelle Eintraege bleiben unangetastet.
     */
    public function wendeMusterAn(int $musterId, int $customerId): array
    {
        $m = $this->db->queryOne(
            "SELECT * FROM lam_aufraeum_muster
             WHERE id = ? AND (customer_id = ? OR customer_id IS NULL)",
            [$musterId, $customerId]
        );
        if (!$m) return ['erfolg' => 0, 'fehler' => 'Muster nicht gefunden'];

        $matches = $this->findePassendeDomains($customerId, $m['muster_typ'], $m['muster_value'], 500);
        if (!$matches) return ['erfolg' => 0, 'angewendet' => 0];

        $linkart  = $m['aktion_linkart'];
        $strategie = $m['aktion_strategie'] ?: ($linkart ? $this->defaultStrategie($linkart) : 'alle_behalten');
        $empf     = $m['aktion_empfehlung'];

        if (!$linkart) return ['erfolg' => 0, 'fehler' => 'Muster ohne Linkart-Aktion'];

        $angewendet = 0;
        foreach ($matches as $row) {
            // Muster vom User selbst bestaetigt → als manuelle Entscheidung speichern,
            // ueberschreibt auch frueher manuell vergebene Werte (sonst wuerde die aktuelle
            // Quelldomain, deren Notiz das Muster erzeugt hat, ihre eigene Regel ignorieren).
            $this->speichereWissen(
                $row['domain'], $linkart, $strategie, 'manuell',
                'Muster #' . $musterId . ' angewendet',
                true, null, true, $customerId, $empf
            );
            $angewendet++;
        }

        $this->db->execute(
            "UPDATE lam_aufraeum_muster
             SET letzte_anwendung_am = NOW(), anzahl_anwendungen = anzahl_anwendungen + 1
             WHERE id = ?",
            [$musterId]
        );

        return ['erfolg' => 1, 'angewendet' => $angewendet, 'kandidaten' => count($matches)];
    }
}
