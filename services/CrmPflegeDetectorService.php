<?php
/**
 * CrmPflegeDetectorService — findet Datenpflege-Issues im CRM und legt sie
 * in der Queue ab.
 *
 * Architektur:
 * - run*() Methoden pro Detektor-Typ (jeder Typ = eigene SQL-/Logik-Strategie)
 * - upsertIssue() mit dedup_key verhindert doppelte Einträge bei mehrfachem Lauf
 * - Issues mit status='offen' werden in UI gezeigt; auf Tastendruck werden alle
 *   Detektoren erneut ausgeführt (existierende offene Issues werden geupdatet,
 *   obsolete bleiben mit status='obsolet')
 *
 * Konvention dedup_key:
 *   '{typ}:{sortierte-entity-keys}'
 *   Beispiele:
 *     'dublette_firma:14|231'         (Firmen 14 und 231)
 *     'dublette_kontakt:856|1230|3001' (3 Kontakte verschmolzen)
 *     'fehlt_branche:f12'             (Firma 12 ohne Branche)
 *     'tag_aehnlich:slug1|slug2'      (zwei Tags die fast gleich heißen)
 */

namespace Services;

use Core\Database;

class CrmPflegeDetectorService
{
    public function __construct(private Database $db) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Führt ALLE Detektoren aus. Returns: ['detector' => count, ...].
     * Aktiviert wird via CLI oder manuell aus dem UI.
     */
    public function runAll(): array
    {
        $stats = [];
        $stats['_cleanup_tote_aeste']    = $this->cleanupToteAeste();
        $stats['dublette_firma']         = $this->runDubletteFirma();
        $stats['dublette_kontakt']       = $this->runDubletteKontakt();
        $stats['fehlt_branche']          = $this->runFehltBranche();
        $stats['fehlt_firma']            = $this->runFehltFirma();
        $stats['firma_ohne_kontakte']    = $this->runFirmaOhneKontakte();
        $stats['pflege_backlog']         = $this->runPflegeBacklog();
        $stats['fehlt_email']            = $this->runFehltEmail();
        $stats['email_funktional']       = $this->runEmailFunktional();
        $stats['email_format_ungueltig'] = $this->runEmailFormatUngueltig();
        $stats['email_domain_mismatch']  = $this->runEmailDomainMismatch();
        $stats['fehlt_linkedin']         = $this->runFehltLinkedin();
        $stats['aktiv_unvollstaendig']   = $this->runAktivUnvollstaendig();
        $stats['tag_aehnlich']           = $this->runTagAehnlich();
        $stats['format_telefon']         = $this->runFormatTelefon();
        $stats['format_website']         = $this->runFormatWebsite();
        $stats['vorname_nachname_gleich']= $this->runVornameNachnameGleich();
        $stats['telefon_mobil_gleich']   = $this->runTelefonMobilGleich();
        $stats['plz_unplausibel']        = $this->runPlzUnplausibel();
        $stats['verwaiste_tags']         = $this->runVerwaisteTags();
        return $stats;
    }

    /** Markiert alle existierenden offenen Issues als 'obsolet' — vor einem Re-Scan. */
    public function markStaleAsObsolet(): int
    {
        return $this->db->execute(
            "UPDATE crm_pflege_issues SET status='obsolet' WHERE status='offen' AND aktualisiert_am < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
    }

    /**
     * Räumt „tote Äste" auf: offene/in_bearbeitung Issues, deren referenzierte
     * Entity (Firma/Kontakt) inzwischen soft-deleted ist. Wird vor jedem Scan
     * aufgerufen, damit Stale-Issues nicht im Wizard auftauchen und 404er auslösen.
     */
    public function cleanupToteAeste(): int
    {
        $bereinigt = 0;
        $rows = $this->db->query(
            "SELECT id, typ, entities_json FROM crm_pflege_issues
             WHERE status IN ('offen','in_bearbeitung')"
        );
        foreach ($rows as $r) {
            $ents = json_decode($r['entities_json'] ?? '[]', true) ?: [];
            if (empty($ents)) continue;
            $lebende = 0;
            foreach ($ents as $e) {
                $typ = $e['typ'] ?? '';
                $id  = (int)($e['id'] ?? 0);
                if (!$id) continue;
                $tabelle = ($typ === 'firma') ? 'crm_firmen'
                    : (($typ === 'kontakt') ? 'crm_kontakte' : null);
                if (!$tabelle) continue;
                if ($this->db->queryValue("SELECT id FROM {$tabelle} WHERE id = ? AND geloescht_am IS NULL", [$id])) {
                    $lebende++;
                }
            }
            $istDublette = in_array($r['typ'], ['dublette_firma', 'dublette_kontakt'], true);
            $obsolet = $istDublette ? ($lebende < 2) : ($lebende === 0);
            if ($obsolet) {
                $this->db->execute(
                    "UPDATE crm_pflege_issues SET status='obsolet', erledigt_aktion='entity_geloescht', erledigt_am=NOW() WHERE id = ?",
                    [$r['id']]
                );
                $bereinigt++;
            }
        }
        return $bereinigt;
    }

    public function listIssues(array $filter = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['status'])) {
            $where[] = 'status = ?'; $params[] = $filter['status'];
        } else {
            $where[] = "status IN ('offen','in_bearbeitung')";
        }
        if (!empty($filter['typ'])) {
            $where[] = 'typ = ?'; $params[] = $filter['typ'];
        }
        if (!empty($filter['schwere'])) {
            $where[] = 'schwere = ?'; $params[] = $filter['schwere'];
        }
        // Anzahl fehlender Felder als Filter (Score / 10) — Range "min..max"
        if (isset($filter['fehlt_min'])) {
            $where[] = 'COALESCE(match_score, 0) >= ?'; $params[] = ((int) $filter['fehlt_min']) * 10;
        }
        if (isset($filter['fehlt_max'])) {
            $where[] = 'COALESCE(match_score, 0) < ?'; $params[] = (((int) $filter['fehlt_max']) + 1) * 10;
        }
        // „Letzte Interaktion seit X Tagen" — wir lesen die Tage aus beschreibung_json
        // (gespeichert vom Detektor) und filtern in PHP nach Laden, da JSON-Subquery teuer.
        $maxTage = isset($filter['interaktion_tage']) ? (int) $filter['interaktion_tage'] : null;

        $limit = isset($filter['limit']) ? max(1, min(500, (int)$filter['limit'])) : 100;
        $offset = isset($filter['offset']) ? max(0, (int)$filter['offset']) : 0;
        $whereSql = implode(' AND ', $where);
        // Sortierung: höchste Schwere, höchster match_score (= viele fehlende Felder + frische Aktivität)
        // dann nach Datum der Entdeckung
        $rows = $this->db->query(
            "SELECT * FROM crm_pflege_issues WHERE $whereSql
             ORDER BY FIELD(schwere, 'hoch', 'mittel', 'niedrig'),
                      COALESCE(match_score, 0) DESC,
                      gefunden_am DESC
             LIMIT " . ($maxTage !== null ? 500 : $limit) . " OFFSET $offset",
            $params
        );
        foreach ($rows as &$r) {
            $r['entities'] = json_decode($r['entities_json'] ?? '[]', true) ?: [];
            $r['ai_empfehlung'] = json_decode($r['ai_empfehlung_json'] ?? 'null', true);
            // strukturierte Beschreibung parsen (für aktiv_unvollstaendig)
            $besch = $r['beschreibung'] ?? '';
            $r['beschreibung_struct'] = null;
            if ($besch !== '' && $besch[0] === '{') {
                $parsed = json_decode($besch, true);
                if (is_array($parsed)) $r['beschreibung_struct'] = $parsed;
            }
            unset($r['entities_json'], $r['ai_empfehlung_json']);
        }
        // Zeitraum-Filter post-hoc anwenden
        if ($maxTage !== null) {
            $rows = array_values(array_filter($rows, function ($r) use ($maxTage) {
                $tage = $r['beschreibung_struct']['tage_seit'] ?? null;
                return $tage !== null && $tage <= $maxTage;
            }));
            $rows = array_slice($rows, 0, $limit);
        }
        return $rows;
    }

    public function getStatsByTyp(): array
    {
        return $this->db->query(
            "SELECT typ, schwere, COUNT(*) AS anzahl
             FROM crm_pflege_issues
             WHERE status IN ('offen','in_bearbeitung')
             GROUP BY typ, schwere
             ORDER BY anzahl DESC"
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DETECTORS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Firmen-Dubletten — mehrstufig:
     * 1. Normalisierter Name (lowercase, GmbH/AG/&-trim) → exact match
     * 2. Website-Domain → exact match (z.B. mustermann.de = mehrere Firmen)
     * 3. Telefon normalisiert → exact match
     */
    public function runDubletteFirma(): int
    {
        $issues = 0;

        // (1) Normalisierter Name
        $alle = $this->db->query(
            "SELECT id, firmenname, website, telefon FROM crm_firmen WHERE geloescht_am IS NULL AND firmenname IS NOT NULL"
        );
        $byNorm = [];
        foreach ($alle as $f) {
            $norm = $this->normFirmenname($f['firmenname']);
            if ($norm === '') continue;
            $byNorm[$norm][] = $f;
        }
        foreach ($byNorm as $norm => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_map(fn($f) => (int)$f['id'], $gruppe);
            sort($ids);
            $titel = '„' . $gruppe[0]['firmenname'] . '" existiert ' . count($gruppe) . '× (Name)';
            $this->upsertDubletteFirma($ids, $titel, 'name', 'hoch', count($gruppe) >= 3 ? 1.0 : 0.95);
            $issues++;
        }

        // (2) Domain-Match
        $byDomain = [];
        foreach ($alle as $f) {
            $domain = $this->normDomain($f['website'] ?? '');
            if ($domain === '') continue;
            $byDomain[$domain][] = $f;
        }
        foreach ($byDomain as $domain => $gruppe) {
            if (count($gruppe) < 2) continue;
            // Wenn die Gruppe bereits durch Name-Match abgedeckt ist, skip
            $namensgruppen = [];
            foreach ($gruppe as $f) $namensgruppen[$this->normFirmenname($f['firmenname'])][] = $f;
            if (count($namensgruppen) === 1) continue; // gleicher Name → schon erfasst
            $ids = array_map(fn($f) => (int)$f['id'], $gruppe);
            sort($ids);
            $titel = count($gruppe) . ' Firmen mit Domain „' . $domain . '"';
            $this->upsertDubletteFirma($ids, $titel, 'domain', 'mittel', 0.75);
            $issues++;
        }

        // (3) Telefon-Match
        $byTel = [];
        foreach ($alle as $f) {
            $tel = $this->normTelefon($f['telefon'] ?? '');
            if ($tel === '' || mb_strlen($tel) < 8) continue;
            $byTel[$tel][] = $f;
        }
        foreach ($byTel as $tel => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_map(fn($f) => (int)$f['id'], $gruppe);
            sort($ids);
            $titel = count($gruppe) . ' Firmen mit Telefon ' . $tel;
            $this->upsertDubletteFirma($ids, $titel, 'telefon', 'mittel', 0.7);
            $issues++;
        }

        return $issues;
    }

    /**
     * Kontakt-Dubletten:
     * 1. E-Mail-Match (primär oder zweit) → exact match
     * 2. Vorname+Nachname+Firma → exact match
     * 3. Telefon (Mobil+Tel)
     */
    public function runDubletteKontakt(): int
    {
        $issues = 0;
        $alle = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer, email_zweit, telefon, mobil, firma_id, shared_email
             FROM crm_kontakte WHERE geloescht_am IS NULL"
        );

        // (1) E-Mail-Match (alle Mails normalisiert) — Kontakte die als shared_email
        // markiert sind werden hier IGNORIERT (geteilte Mailbox = bewusst keine Dublette).
        $byMail = [];
        foreach ($alle as $k) {
            if (!empty($k['shared_email'])) continue;
            foreach ([$k['email_primaer'] ?? '', $k['email_zweit'] ?? ''] as $m) {
                $m = strtolower(trim($m));
                if ($m === '' || !str_contains($m, '@')) continue;
                $byMail[$m][] = $k;
            }
        }
        $alreadyPaired = []; // Schutz vor doppelten Einträgen wenn email_primaer + email_zweit gleiche Kontakte koppeln
        foreach ($byMail as $mail => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_unique(array_map(fn($k) => (int)$k['id'], $gruppe));
            if (count($ids) < 2) continue;
            sort($ids);
            $key = implode('|', $ids);
            if (isset($alreadyPaired[$key])) continue;
            $alreadyPaired[$key] = true;
            $titel = 'E-Mail ' . $mail . ' bei ' . count($ids) . ' Kontakten';
            $this->upsertDubletteKontakt($ids, $titel, 'email', 'hoch', 1.0);
            $issues++;
        }

        // (2) Vorname+Nachname+Firma
        $byName = [];
        foreach ($alle as $k) {
            $vn = $this->normName($k['vorname'] ?? '');
            $nn = $this->normName($k['nachname'] ?? '');
            if ($vn === '' || $nn === '') continue;
            $key = $vn . '|' . $nn . '|' . (int)($k['firma_id'] ?? 0);
            $byName[$key][] = $k;
        }
        foreach ($byName as $key => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_map(fn($k) => (int)$k['id'], $gruppe);
            sort($ids);
            $pairKey = implode('|', $ids);
            if (isset($alreadyPaired[$pairKey])) continue;
            $alreadyPaired[$pairKey] = true;
            $nameAnzeige = trim(($gruppe[0]['vorname'] ?? '') . ' ' . ($gruppe[0]['nachname'] ?? ''));
            $titel = $nameAnzeige . ' existiert ' . count($gruppe) . '× (Name + Firma)';
            $this->upsertDubletteKontakt($ids, $titel, 'name_firma', 'mittel', 0.85);
            $issues++;
        }

        // (3) Telefonnummer (mobil oder telefon) — nur bei nicht-Leer und >=8 Ziffern
        $byTel = [];
        foreach ($alle as $k) {
            foreach ([$k['mobil'] ?? '', $k['telefon'] ?? ''] as $t) {
                $tel = $this->normTelefon($t);
                if ($tel === '' || mb_strlen($tel) < 9) continue;
                $byTel[$tel][] = $k;
            }
        }
        foreach ($byTel as $tel => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_unique(array_map(fn($k) => (int)$k['id'], $gruppe));
            if (count($ids) < 2) continue;
            sort($ids);
            $pairKey = implode('|', $ids);
            if (isset($alreadyPaired[$pairKey])) continue;
            $alreadyPaired[$pairKey] = true;
            $titel = count($ids) . ' Kontakte mit Telefon ' . $tel;
            $this->upsertDubletteKontakt($ids, $titel, 'telefon', 'mittel', 0.7);
            $issues++;
        }

        return $issues;
    }

    /** Firmen ohne Branche */
    public function runFehltBranche(): int
    {
        $rows = $this->db->query(
            "SELECT f.id, f.firmenname FROM crm_firmen f
             WHERE f.geloescht_am IS NULL
               AND (f.branche IS NULL OR f.branche = '')
               AND EXISTS (SELECT 1 FROM crm_kontakte k WHERE k.firma_id = f.id AND k.geloescht_am IS NULL)
             ORDER BY f.firmenname"
        );
        $issues = 0;
        foreach ($rows as $f) {
            $this->upsertIssue(
                'fehlt_branche:f' . $f['id'],
                [
                    'typ' => 'fehlt_branche',
                    'schwere' => 'niedrig',
                    'titel' => '„' . $f['firmenname'] . '" hat keine Branche',
                    'entities' => [['typ' => 'firma', 'id' => (int)$f['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /**
     * Kontakte ohne Firma — ABER nur wenn nicht bereits als „ohne_firmenbezug"
     * (privat) oder „pflege_offen" (Backlog) markiert. Markierte gehen nicht in
     * diese Kategorie, weil der User da bewusst entschieden hat.
     */
    public function runFehltFirma(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL AND firma_id IS NULL
               AND (firma_status IS NULL OR firma_status = 'verknuepft')
             ORDER BY nachname ASC"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: ($k['email_primaer'] ?? 'Kontakt #' . $k['id']);
            $this->upsertIssue(
                'fehlt_firma:k' . $k['id'],
                [
                    'typ' => 'fehlt_firma',
                    'schwere' => 'niedrig',
                    'titel' => $name . ' hat keine Firma',
                    'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /**
     * Pflege-Backlog: Kontakte die der User auf „später entscheiden" gelegt hat.
     * Eigener Detektor — getrennt von „fehlt_firma" damit der User priorisieren kann.
     */
    public function runPflegeBacklog(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL AND firma_status = 'pflege_offen'
             ORDER BY geaendert_am DESC"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: ($k['email_primaer'] ?? 'Kontakt #' . $k['id']);
            $this->upsertIssue(
                'pflege_backlog:k' . $k['id'],
                [
                    'typ' => 'pflege_backlog',
                    'schwere' => 'niedrig',
                    'titel' => $name . ' — Firma-Zuweisung später nachholen',
                    'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /** Kontakte mit leerer / ungültiger E-Mail (sollte eigentlich nicht vorkommen wg. NOT NULL) */
    public function runFehltEmail(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL AND (email_primaer IS NULL OR email_primaer = '' OR email_primaer NOT LIKE '%@%')"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $this->upsertIssue(
                'fehlt_email:k' . $k['id'],
                [
                    'typ' => 'fehlt_email',
                    'schwere' => 'hoch',
                    'titel' => $name . ' hat keine gültige E-Mail',
                    'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /**
     * Tags die sich nur in Groß-/Kleinschreibung, Bindestrich, Leerzeichen unterscheiden.
     * z.B. „Newsletter-Abonnent" vs „Newsletter Abonnent" vs „newsletter-abonnent"
     */
    public function runTagAehnlich(): int
    {
        $tags = $this->db->query("SELECT id, name, anzahl_kontakte FROM crm_tags ORDER BY name");
        $byNorm = [];
        foreach ($tags as $t) {
            $norm = $this->normTagName($t['name']);
            if ($norm === '') continue;
            $byNorm[$norm][] = $t;
        }
        $issues = 0;
        foreach ($byNorm as $norm => $gruppe) {
            if (count($gruppe) < 2) continue;
            $ids = array_map(fn($t) => (int)$t['id'], $gruppe);
            sort($ids);
            $names = array_map(fn($t) => $t['name'], $gruppe);
            $titel = count($gruppe) . ' ähnliche Tags: ' . implode(', ', array_map(fn($n) => '„' . $n . '"', array_slice($names, 0, 3)));
            $this->upsertIssue(
                'tag_aehnlich:' . implode('|', $ids),
                [
                    'typ' => 'tag_aehnlich',
                    'schwere' => 'mittel',
                    'titel' => $titel,
                    'entities' => array_map(fn($t) => ['typ' => 'tag', 'id' => (int)$t['id'], 'name' => $t['name'], 'anzahl' => (int)$t['anzahl_kontakte']], $gruppe),
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /** Telefonnummern mit verschiedensten Schreibweisen */
    public function runFormatTelefon(): int
    {
        $issues = 0;
        // Suche: Telefon ohne Vorwahl oder mit ungewöhnlichen Trennern
        $sql = "SELECT id, vorname, nachname, telefon, mobil, fax FROM crm_kontakte
                WHERE geloescht_am IS NULL
                  AND (telefon REGEXP '[^0-9 +/().-]' OR mobil REGEXP '[^0-9 +/().-]' OR fax REGEXP '[^0-9 +/().-]')";
        $rows = $this->db->query($sql);
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
            $felder = [];
            foreach (['telefon','mobil','fax'] as $f) {
                if (!empty($k[$f]) && preg_match('/[^0-9 +\/().-]/u', $k[$f])) {
                    $felder[$f] = $k[$f];
                }
            }
            if (!$felder) continue;
            $this->upsertIssue(
                'format_telefon:k' . $k['id'],
                [
                    'typ' => 'format_telefon',
                    'schwere' => 'niedrig',
                    'titel' => $name . ': ungewöhnliches Telefonformat',
                    'beschreibung' => 'Felder: ' . implode(', ', array_map(fn($f,$v) => $f . '=' . $v, array_keys($felder), $felder)),
                    'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /** Websites ohne http(s):// */
    public function runFormatWebsite(): int
    {
        $issues = 0;
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, website FROM crm_kontakte
             WHERE geloescht_am IS NULL AND website IS NOT NULL AND website != ''
               AND website NOT LIKE 'http%'"
        );
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
            $this->upsertIssue(
                'format_website:k' . $k['id'],
                [
                    'typ' => 'format_website',
                    'schwere' => 'niedrig',
                    'titel' => $name . ': Website ohne http(s)://',
                    'beschreibung' => 'Website: ' . $k['website'],
                    'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                ]
            );
            $issues++;
        }
        $rows = $this->db->query(
            "SELECT id, firmenname, website FROM crm_firmen
             WHERE geloescht_am IS NULL AND website IS NOT NULL AND website != ''
               AND website NOT LIKE 'http%'"
        );
        foreach ($rows as $f) {
            $this->upsertIssue(
                'format_website:f' . $f['id'],
                [
                    'typ' => 'format_website',
                    'schwere' => 'niedrig',
                    'titel' => '„' . $f['firmenname'] . '": Website ohne http(s)://',
                    'beschreibung' => 'Website: ' . $f['website'],
                    'entities' => [['typ' => 'firma', 'id' => (int)$f['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    /** Tags die von keinem Kontakt mehr verwendet werden */
    public function runVerwaisteTags(): int
    {
        $rows = $this->db->query(
            "SELECT t.id, t.name FROM crm_tags t
             LEFT JOIN crm_kontakt_tags kt ON kt.tag_id = t.id
             WHERE kt.tag_id IS NULL"
        );
        $issues = 0;
        foreach ($rows as $t) {
            $this->upsertIssue(
                'verwaister_tag:t' . $t['id'],
                [
                    'typ' => 'verwaister_tag',
                    'schwere' => 'niedrig',
                    'titel' => 'Tag „' . $t['name'] . '" wird von keinem Kontakt mehr verwendet',
                    'entities' => [['typ' => 'tag', 'id' => (int)$t['id']]],
                ]
            );
            $issues++;
        }
        return $issues;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NEUE DETEKTOREN (Beziehung, E-Mail-Qualität, Aktivität, Hygiene)
    // ═══════════════════════════════════════════════════════════════════════

    /** Firmen, die keinen einzigen aktiven Kontakt haben (Spiegel zu fehlt_firma). */
    public function runFirmaOhneKontakte(): int
    {
        $rows = $this->db->query(
            "SELECT f.id, f.firmenname FROM crm_firmen f
             WHERE f.geloescht_am IS NULL
               AND NOT EXISTS (SELECT 1 FROM crm_kontakte k WHERE k.firma_id = f.id AND k.geloescht_am IS NULL)
             ORDER BY f.firmenname"
        );
        $issues = 0;
        foreach ($rows as $f) {
            $this->upsertIssue('firma_ohne_kontakte:f' . $f['id'], [
                'typ' => 'firma_ohne_kontakte',
                'schwere' => 'niedrig',
                'titel' => '„' . $f['firmenname'] . '" hat keinen Ansprechpartner',
                'entities' => [['typ' => 'firma', 'id' => (int)$f['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Funktionale E-Mails (info@, kontakt@, office@, …) als email_primaer eines Personenkontakts. */
    public function runEmailFunktional(): int
    {
        $prefixes = ['info','kontakt','office','team','hello','contact','mail','sekretariat','empfang','vertrieb','sales','support','service','marketing','presse','jobs','karriere','bewerbung','admin','it','buchhaltung','accounting','rechnung','invoice','hr','personal','noreply','no-reply','newsletter'];
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL AND email_primaer LIKE '%@%'"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $local = strtolower(trim(substr($k['email_primaer'], 0, strpos($k['email_primaer'], '@'))));
            if ($local === '') continue;
            if (!in_array($local, $prefixes, true)) continue;
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $this->upsertIssue('email_funktional:k' . $k['id'], [
                'typ' => 'email_funktional',
                'schwere' => 'mittel',
                'titel' => $name . ' hat funktionale E-Mail (' . $k['email_primaer'] . ')',
                'beschreibung' => 'Funktions-Postfach „' . $local . '@" sollte nicht als persönliche Primär-Adresse stehen.',
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Syntaktisch ungültige E-Mail-Adressen (kein @, ungültige Domain, doppelte Punkte etc.). */
    public function runEmailFormatUngueltig(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL AND email_primaer IS NOT NULL AND email_primaer != ''
               AND email_primaer LIKE '%@%' AND email_primaer NOT LIKE '\\_\\_del\\_%'"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $mail = trim($k['email_primaer']);
            if (filter_var($mail, FILTER_VALIDATE_EMAIL) && !preg_match('/\.\./', $mail)) continue;
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $this->upsertIssue('email_format:k' . $k['id'], [
                'typ' => 'email_format_ungueltig',
                'schwere' => 'hoch',
                'titel' => $name . ': E-Mail-Format ungültig (' . $mail . ')',
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Kontakt-E-Mail-Domain weicht von Firma-Website-Domain ab (möglicher Job-Wechsel oder Fehlzuordnung). */
    public function runEmailDomainMismatch(): int
    {
        $rows = $this->db->query(
            "SELECT k.id, k.vorname, k.nachname, k.email_primaer, f.firmenname, f.website
             FROM crm_kontakte k
             JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.geloescht_am IS NULL AND f.geloescht_am IS NULL
               AND k.email_primaer LIKE '%@%' AND f.website IS NOT NULL AND f.website != ''"
        );
        // Generische E-Mail-Anbieter ausschließen — bei denen gibt es legitim keinen Match
        $generisch = ['gmail.com','googlemail.com','gmx.de','gmx.net','web.de','t-online.de','outlook.com','outlook.de','hotmail.com','hotmail.de','yahoo.com','yahoo.de','live.com','live.de','aol.com','icloud.com','me.com','mail.de','posteo.de','protonmail.com','proton.me','mailbox.org','freenet.de'];
        $issues = 0;
        foreach ($rows as $k) {
            $mailDom = strtolower(trim(substr($k['email_primaer'], strpos($k['email_primaer'], '@') + 1)));
            $webDom  = strtolower((string) parse_url(preg_match('#^https?://#i', $k['website']) ? $k['website'] : 'https://' . $k['website'], PHP_URL_HOST));
            $webDom  = preg_replace('/^www\./', '', $webDom);
            if ($mailDom === '' || $webDom === '') continue;
            if ($mailDom === $webDom) continue;
            if (in_array($mailDom, $generisch, true)) continue; // generische Mail → kein Mismatch-Indikator
            // Toleranz: same root domain (z.B. mail.example.de ↔ example.de)
            $rootMail = implode('.', array_slice(explode('.', $mailDom), -2));
            $rootWeb  = implode('.', array_slice(explode('.', $webDom), -2));
            if ($rootMail === $rootWeb) continue;
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $this->upsertIssue('email_domain_mismatch:k' . $k['id'], [
                'typ' => 'email_domain_mismatch',
                'schwere' => 'mittel',
                'titel' => $name . ': E-Mail @' . $mailDom . ' passt nicht zur Firma „' . $k['firmenname'] . '" (' . $webDom . ')',
                'beschreibung' => 'Möglicher Job-Wechsel oder falsche Firma-Zuordnung.',
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Kontakte mit Name + Firma, aber ohne LinkedIn-Profil (nur für echt aktive — Zoho/Brevo). */
    public function runFehltLinkedin(): int
    {
        // Verwaiste fehlt_linkedin-Issues vorab obsoletieren — gleiche Logik wie aktiv_unvollstaendig
        $this->db->execute("UPDATE crm_pflege_issues SET status='obsolet' WHERE typ='fehlt_linkedin' AND status='offen'");
        $rows = $this->db->query(
            "SELECT k.id, k.vorname, k.nachname, f.firmenname,
                    GREATEST(
                        COALESCE(k.zoho_modified_at, '1970-01-01'),
                        COALESCE(k.zoho_last_activity_at, '1970-01-01'),
                        COALESCE(k.brevo_modified_at, '1970-01-01'),
                        COALESCE((SELECT MAX(be.empfangen_am) FROM crm_brevo_events be
                                  WHERE be.kontakt_id = k.id AND be.event_typ IN ('open','click','sent','delivered')), '1970-01-01')
                    ) AS letzte_aktivitaet
             FROM crm_kontakte k
             LEFT JOIN crm_firmen f ON f.id = k.firma_id
             WHERE k.geloescht_am IS NULL
               AND k.vorname IS NOT NULL AND k.vorname != ''
               AND k.nachname IS NOT NULL AND k.nachname != ''
               AND NOT EXISTS (SELECT 1 FROM crm_social_links sl WHERE sl.kontakt_id = k.id AND sl.plattform = 'linkedin')
             HAVING letzte_aktivitaet > DATE_SUB(NOW(), INTERVAL 12 MONTH)"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
            $tage = max(0, (int) floor((time() - strtotime($k['letzte_aktivitaet'])) / 86400));
            $this->upsertIssue('fehlt_linkedin:k' . $k['id'], [
                'typ' => 'fehlt_linkedin',
                'schwere' => 'niedrig',
                'titel' => $name . ' hat kein LinkedIn-Profil hinterlegt',
                'beschreibung' => json_encode([
                    'fehlt' => ['LinkedIn'],
                    'firma' => $k['firmenname'] ?: null,
                    'letzte_interaktion' => $k['letzte_aktivitaet'],
                    'tage_seit' => $tage,
                ]),
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                // Aktualitätsbonus für Sortierung
                'match_score' => max(0, 365 - $tage) / 100,
            ]);
            $issues++;
        }
        return $issues;
    }

    /**
     * Aktive Kontakte mit fehlenden Pflichtfeldern.
     * „Aktiv" = mindestens eine Aktivität in den letzten 6 Monaten (höhere Schwere)
     *           oder 12 Monate (niedrigere Schwere).
     * Pflichtfelder: Funktion, mindestens Telefon ODER Mobil, mindestens ein Tag, Firma.
     */
    public function runAktivUnvollstaendig(): int
    {
        // „Aktiv" = echte Aktualität aus Zoho-Mod-Time, Zoho-Last-Activity oder Brevo-Touchpoint.
        // crm_aktivitaeten wird BEWUSST ausgelassen — die Tabelle ist nach Import zu rauschig
        // (alle `kontakt_angelegt`-Einträge haben das Import-Datum).

        // Erst: ALLE offenen Issues dieses Typs auf 'obsolet' setzen. Die noch passenden
        // werden gleich vom Upsert wieder auf 'offen' gesetzt — die übrigen bleiben obsolet.
        // Verhindert verwaiste Issues, wenn sich „aktiv"-Status eines Kontakts ändert.
        $this->db->execute("UPDATE crm_pflege_issues SET status='obsolet' WHERE typ='aktiv_unvollstaendig' AND status='offen'");

        $rows = $this->db->query(
            "SELECT k.id, k.vorname, k.nachname, k.funktion, k.telefon, k.mobil, k.firma_id,
                    k.beschreibung, k.foto_path,
                    GREATEST(
                        COALESCE(k.zoho_modified_at, '1970-01-01'),
                        COALESCE(k.zoho_last_activity_at, '1970-01-01'),
                        COALESCE(k.brevo_modified_at, '1970-01-01'),
                        COALESCE((SELECT MAX(be.empfangen_am) FROM crm_brevo_events be
                                  WHERE be.kontakt_id = k.id AND be.event_typ IN ('open','click','sent','delivered')), '1970-01-01')
                    ) AS letzte_aktivitaet,
                    (SELECT COUNT(*) FROM crm_kontakt_tags kt WHERE kt.kontakt_id = k.id) AS tag_count,
                    (SELECT COUNT(*) FROM crm_social_links sl WHERE sl.kontakt_id = k.id AND sl.plattform = 'linkedin') AS hat_linkedin
             FROM crm_kontakte k
             WHERE k.geloescht_am IS NULL
             HAVING letzte_aktivitaet > DATE_SUB(NOW(), INTERVAL 12 MONTH)"
        );
        $issues = 0;
        $sechsMonate = strtotime('-6 months');
        foreach ($rows as $k) {
            $fehlt = [];
            if (empty($k['funktion']))                          $fehlt[] = 'Funktion';
            if (empty($k['telefon']) && empty($k['mobil']))     $fehlt[] = 'Telefon';
            if ((int)$k['tag_count'] === 0)                     $fehlt[] = 'Tag';
            if (empty($k['firma_id']))                          $fehlt[] = 'Firma';
            if (empty($k['hat_linkedin']))                      $fehlt[] = 'LinkedIn';
            if (empty($k['beschreibung']))                      $fehlt[] = 'Beschreibung';
            if (empty($k['foto_path']))                         $fehlt[] = 'Foto';
            if (empty($fehlt)) continue;
            $letzte = $k['letzte_aktivitaet'] ? strtotime($k['letzte_aktivitaet']) : 0;
            // Schwere: viel fehlend ODER ganz frische Aktivität → hoch
            $istFrisch = $letzte > $sechsMonate;
            $schwere = (count($fehlt) >= 4 || ($istFrisch && count($fehlt) >= 2)) ? 'hoch' : (count($fehlt) >= 2 ? 'mittel' : 'niedrig');
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $tage = $letzte ? max(0, (int) floor((time() - $letzte) / 86400)) : 9999;
            // match_score = Anzahl fehlender Felder * 10 + Aktualitätsbonus (max 5 für taggleich)
            $score = count($fehlt) * 10 + max(0, 5 - intdiv($tage, 30));
            $this->upsertIssue('aktiv_unvollstaendig:k' . $k['id'], [
                'typ' => 'aktiv_unvollstaendig',
                'schwere' => $schwere,
                'titel' => $name . ' — ' . count($fehlt) . ' Feld' . (count($fehlt) === 1 ? '' : 'er') . ' fehlt: ' . implode(', ', $fehlt),
                'beschreibung' => json_encode([
                    'fehlt' => $fehlt,
                    'letzte_interaktion' => $k['letzte_aktivitaet'] ?? null,
                    'tage_seit' => $tage,
                ]),
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
                'match_score' => $score,
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Vorname == Nachname (Import-Müll-Indikator). */
    public function runVornameNachnameGleich(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, email_primaer FROM crm_kontakte
             WHERE geloescht_am IS NULL
               AND vorname IS NOT NULL AND vorname != ''
               AND nachname IS NOT NULL AND nachname != ''
               AND LOWER(TRIM(vorname)) = LOWER(TRIM(nachname))"
        );
        $issues = 0;
        foreach ($rows as $k) {
            $this->upsertIssue('name_gleich:k' . $k['id'], [
                'typ' => 'name_gleich',
                'schwere' => 'mittel',
                'titel' => 'Kontakt #' . $k['id'] . ': Vor- und Nachname identisch („' . $k['vorname'] . '")',
                'beschreibung' => 'Häufig Import-Artefakt, einer der beiden Werte ist meist falsch.',
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Telefon == Mobil (doppelte Eingabe in beiden Feldern). */
    public function runTelefonMobilGleich(): int
    {
        $rows = $this->db->query(
            "SELECT id, vorname, nachname, telefon, mobil FROM crm_kontakte
             WHERE geloescht_am IS NULL
               AND telefon IS NOT NULL AND mobil IS NOT NULL
               AND telefon != '' AND mobil != ''"
        );
        $issues = 0;
        foreach ($rows as $k) {
            if ($this->normTelefon($k['telefon']) !== $this->normTelefon($k['mobil'])) continue;
            $name = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')) ?: 'Kontakt #' . $k['id'];
            $this->upsertIssue('tel_mobil_gleich:k' . $k['id'], [
                'typ' => 'telefon_mobil_gleich',
                'schwere' => 'niedrig',
                'titel' => $name . ': Telefon und Mobil sind identisch (' . $k['telefon'] . ')',
                'entities' => [['typ' => 'kontakt', 'id' => (int)$k['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    /** Unplausible deutsche PLZ (alles in DE, das nicht aus 5 Ziffern besteht). */
    public function runPlzUnplausibel(): int
    {
        $rows = $this->db->query(
            "SELECT a.id, a.plz, a.stadt, a.land, a.kontakt_id, a.firma_id,
                    k.vorname, k.nachname, f.firmenname
             FROM crm_adressen a
             LEFT JOIN crm_kontakte k ON k.id = a.kontakt_id AND k.geloescht_am IS NULL
             LEFT JOIN crm_firmen f ON f.id = a.firma_id AND f.geloescht_am IS NULL
             WHERE a.plz IS NOT NULL AND a.plz != ''
               AND (a.land IS NULL OR a.land = '' OR a.land = 'Deutschland' OR a.land = 'DE')
               AND a.plz NOT REGEXP '^[0-9]{5}$'"
        );
        $issues = 0;
        foreach ($rows as $r) {
            $wer = $r['firmenname'] ?: trim(($r['vorname'] ?? '') . ' ' . ($r['nachname'] ?? '')) ?: 'Adresse #' . $r['id'];
            $typ = $r['firma_id'] ? 'firma' : 'kontakt';
            $entId = (int) ($r['firma_id'] ?: $r['kontakt_id']);
            if (!$entId) continue;
            $this->upsertIssue('plz_unplausibel:' . substr($typ,0,1) . $entId . ':a' . $r['id'], [
                'typ' => 'plz_unplausibel',
                'schwere' => 'niedrig',
                'titel' => $wer . ': PLZ „' . $r['plz'] . '" ist keine gültige DE-PLZ',
                // adresse_id in der Entity, damit single_preview die korrekte Adresse mit anzeigen kann
                'entities' => [['typ' => $typ, 'id' => $entId, 'adresse_id' => (int) $r['id']]],
            ]);
            $issues++;
        }
        return $issues;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ISSUE-UPSERT
    // ═══════════════════════════════════════════════════════════════════════

    private function upsertDubletteFirma(array $ids, string $titel, string $matchTyp, string $schwere, float $score): void
    {
        sort($ids);
        $this->upsertIssue(
            'dublette_firma:' . implode('|', $ids),
            [
                'typ' => 'dublette_firma',
                'schwere' => $schwere,
                'titel' => $titel,
                'beschreibung' => 'Match-Typ: ' . $matchTyp,
                'entities' => array_map(fn($id) => ['typ' => 'firma', 'id' => $id], $ids),
                'match_score' => $score,
            ]
        );
    }

    private function upsertDubletteKontakt(array $ids, string $titel, string $matchTyp, string $schwere, float $score): void
    {
        sort($ids);
        $this->upsertIssue(
            'dublette_kontakt:' . implode('|', $ids),
            [
                'typ' => 'dublette_kontakt',
                'schwere' => $schwere,
                'titel' => $titel,
                'beschreibung' => 'Match-Typ: ' . $matchTyp,
                'entities' => array_map(fn($id) => ['typ' => 'kontakt', 'id' => $id], $ids),
                'match_score' => $score,
            ]
        );
    }

    /**
     * UPSERT mit dedup_key — wenn Eintrag existiert wird er nur reaktiviert
     * (status: obsolet → offen), nicht überschrieben damit ai_empfehlung / user-Notiz nicht verloren gehen.
     */
    private function upsertIssue(string $dedupKey, array $data): void
    {
        $existing = $this->db->queryOne(
            "SELECT id, status FROM crm_pflege_issues WHERE dedup_key = ? LIMIT 1",
            [$dedupKey]
        );
        if ($existing) {
            // Wenn ignoriert oder erledigt → in Ruhe lassen (User hat entschieden)
            if (in_array($existing['status'], ['ignoriert', 'erledigt'], true)) return;
            // Sonst: aktualisieren (Titel könnte sich geändert haben)
            $this->db->update('crm_pflege_issues', [
                'titel' => $data['titel'],
                'beschreibung' => $data['beschreibung'] ?? null,
                'schwere' => $data['schwere'] ?? 'mittel',
                'entities_json' => json_encode($data['entities'] ?? []),
                'match_score' => $data['match_score'] ?? null,
                'status' => 'offen',
            ], 'id = ?', [(int)$existing['id']]);
        } else {
            $this->db->insert('crm_pflege_issues', [
                'typ' => $data['typ'],
                'schwere' => $data['schwere'] ?? 'mittel',
                'titel' => $data['titel'],
                'beschreibung' => $data['beschreibung'] ?? null,
                'entities_json' => json_encode($data['entities'] ?? []),
                'match_score' => $data['match_score'] ?? null,
                'dedup_key' => $dedupKey,
                'status' => 'offen',
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NORMALISIERUNGS-HELPER
    // ═══════════════════════════════════════════════════════════════════════

    /** Normalisiert Firmennamen für Vergleich: lowercase, GmbH/AG/&-trim, Umlaute. */
    private function normFirmenname(string $name): string
    {
        $n = mb_strtolower(trim($name));
        // Rechtsformen weg
        $rechtsformen = ['gmbh & co. kg','gmbh & co kg','gmbh','ag','ug','e.k.','ek','kg','gbr','ohg','se','ltd','llc','inc','e.v.','ev','co.','co'];
        foreach ($rechtsformen as $rf) {
            $n = preg_replace('/(^|\s)' . preg_quote($rf, '/') . '($|\s|,|\.)/', ' ', $n);
        }
        // Umlaute normalisieren
        $n = strtr($n, ['ä' => 'ae','ö' => 'oe','ü' => 'ue','ß' => 'ss']);
        // Alle Sonderzeichen außer Buchstaben/Zahlen weg
        $n = preg_replace('/[^a-z0-9]+/', '', $n);
        return $n;
    }

    private function normName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = strtr($n, ['ä' => 'ae','ö' => 'oe','ü' => 'ue','ß' => 'ss']);
        $n = preg_replace('/[^a-z0-9]+/', '', $n);
        return $n;
    }

    private function normDomain(string $url): string
    {
        if ($url === '') return '';
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://#', '', $url);
        $url = preg_replace('#^www\.#', '', $url);
        $url = preg_replace('#/.*$#', '', $url);
        $url = preg_replace('#:\d+$#', '', $url);
        return $url;
    }

    private function normTelefon(string $tel): string
    {
        if ($tel === '') return '';
        $digits = preg_replace('/\D+/', '', $tel);
        // Führende 0 oder 49 entfernen → vergleichbar
        $digits = preg_replace('/^0/', '49', $digits);
        $digits = preg_replace('/^4949/', '49', $digits);
        return $digits;
    }

    private function normTagName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = strtr($n, ['ä' => 'ae','ö' => 'oe','ü' => 'ue','ß' => 'ss']);
        $n = preg_replace('/[^a-z0-9]+/', '', $n);
        return $n;
    }
}
