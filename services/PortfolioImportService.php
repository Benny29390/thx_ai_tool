<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * Importiert Anbieter-Portfolios aus E-Mail (EML) + Excel-Anhang.
 *
 * Phase 1: Extraktion. Liest Dateien, ruft KI, gibt strukturiertes Vorschlags-JSON
 * zurueck. Daten landen in lam_import_batches.mapping (JSON) bis zum Commit.
 *
 * Phase 2 (folgt): Review + Commit über LamService::importiere*().
 *
 * Erwartete Eingabe-Typen pro Upload:
 *  - EML/MSG : 1 Mail mit Signatur (Anbieter + Kontakt)
 *  - XLSX/CSV: Portfolio-Liste (Domains x Preise x Themen)
 *  - PDF/TXT : (zukunft) Vertraege/AGB
 *
 * Liefert ein Vorschlags-Objekt mit Status pro Item:
 *  status = 'neu' | 'update' | 'konflikt'
 *  match_id = bestehende ID falls 'update'
 *  diff = { feld: { alt, neu } } falls 'update'
 */
class PortfolioImportService
{
    private Database $db;
    private string $uploadDir;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->uploadDir = '/var/www/uploads/lam/portfolio-imports';
        if (!is_dir($this->uploadDir)) @mkdir($this->uploadDir, 0775, true);
    }

    /**
     * Speichert hochgeladene Dateien physisch ab und legt einen Batch an.
     * Liefert die Batch-ID. Die eigentliche Analyse läuft separat per analyse().
     *
     * @param array $files  Liste aus dem Upload (z.B. $_FILES['files'])
     * @param int   $userId
     */
    public function speichereDateien(array $files, int $userId): string
    {
        $batchId = $this->ulid();
        $dir = $this->uploadDir . '/' . $batchId;
        @mkdir($dir, 0775, true);
        $gespeichert = [];
        foreach ($files as $f) {
            if (empty($f['tmp_name']) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) continue;
            if (($f['size'] ?? 0) > 25 * 1024 * 1024) continue; // 25 MB pro File
            $safe = preg_replace('/[^\w.\-]/u', '_', $f['name'] ?? 'upload');
            $dest = $dir . '/' . $safe;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $gespeichert[] = ['name' => $f['name'], 'pfad' => $dest, 'typ' => $this->erkennetyp($safe)];
            }
        }
        if (empty($gespeichert)) {
            @rmdir($dir);
            throw new \RuntimeException('Keine Dateien konnten gespeichert werden.');
        }
        $namen = array_column($gespeichert, 'name');
        $this->db->insert('lam_import_batches', [
            'id' => $batchId,
            'dateiname' => implode(' + ', array_slice($namen, 0, 3)) . (count($namen) > 3 ? ' + ...' : ''),
            'datei_datum' => date('Y-m-d'),
            'importiert_am' => date('Y-m-d H:i:s'),
            'importiert_von_user_id' => $userId,
            'anzahl_neu' => 0,
            'anzahl_dublette' => 0,
            'anzahl_fehler' => 0,
            'status' => 'extrahiert_offen',
            'notizen' => 'Portfolio-Import (Phase 1: Extraktion)',
            'mapping' => json_encode(['files' => $gespeichert, 'extraction' => null], JSON_UNESCAPED_UNICODE),
            'erstellt_am' => date('Y-m-d H:i:s'),
        ]);
        return $batchId;
    }

    /**
     * Analysiert die zum Batch gehörigen Dateien (EML + XLSX + ...) und legt das
     * Vorschlags-JSON in lam_import_batches.mapping.extraction ab. Synchron, da
     * Datei-Mengen klein sind (1 Mail + 1 Excel). Kann später in Worker.
     */
    public function analysiere(string $batchId): array
    {
        $batch = $this->db->queryOne("SELECT * FROM lam_import_batches WHERE id = ?", [$batchId]);
        if (!$batch) throw new \RuntimeException('Batch nicht gefunden');
        $meta = json_decode($batch['mapping'] ?? '{}', true) ?: [];
        $files = $meta['files'] ?? [];
        if (empty($files)) throw new \RuntimeException('Keine Dateien im Batch');

        $rawMail = null;
        $rawXlsxRows = [];
        $xlsxName = null;
        $pdfText = null;
        $pdfName = null;

        foreach ($files as $f) {
            if (!is_file($f['pfad'])) continue;
            if ($f['typ'] === 'eml') {
                $rawMail = $this->parseEml($f['pfad']);
            } elseif ($f['typ'] === 'xlsx' || $f['typ'] === 'csv') {
                require_once SERVICES_PATH . '/XlsxReader.php';
                if ($f['typ'] === 'xlsx') {
                    $rawXlsxRows = XlsxReader::leseZeilen($f['pfad'], 5000);
                } else {
                    $rawXlsxRows = $this->parseCsv($f['pfad']);
                }
                $xlsxName = $f['name'];
            } elseif ($f['typ'] === 'pdf') {
                $pdfText = $this->extrahierePdfText($f['pfad']);
                $pdfName = $f['name'];
            }
        }

        // 1. Anbieter + Kontakte aus Mail-Signatur extrahieren (per KI)
        $anbieterVorschlag = $rawMail ? $this->extrahiereAnbieterAusMail($rawMail) : null;

        // 2. Domains + Konditionen aus Excel extrahieren
        $domainsVorschlag = !empty($rawXlsxRows) ? $this->extrahiereDomainsAusExcel($rawXlsxRows, $xlsxName) : ['domains' => [], 'linkart_mapping' => []];

        // 3. Sonderdeals aus Mailtext extrahieren (z.B. "techfacts.de 330 €")
        $sonderdeals = $rawMail ? $this->extrahiereSonderdealsAusMail($rawMail) : [];

        // 4. PDF (Mediadaten/Preisliste): Anbieter + Domains + Preise in einem KI-Call
        if ($pdfText) {
            $pdfData = $this->extrahierePortfolioAusPdf($pdfText, $pdfName);
            // Anbieter aus PDF nur nutzen, wenn aus Mail noch nichts da war
            if (empty($anbieterVorschlag) && !empty($pdfData['anbieter'])) {
                $anbieterVorschlag = [
                    'anbieter' => $pdfData['anbieter'],
                    'kontakte' => $pdfData['kontakte'] ?? [],
                ];
            } elseif (!empty($pdfData['kontakte'])) {
                // Ergänzende Kontakte aus PDF dranhängen, falls Mail-Anbieter schon da
                $anbieterVorschlag['kontakte'] = array_merge($anbieterVorschlag['kontakte'] ?? [], $pdfData['kontakte']);
            }
            // Domains aus PDF immer mit-mergen (dedup nach normalisierter URL geschieht im Match-Step)
            if (!empty($pdfData['domains'])) {
                $domainsVorschlag['domains'] = array_merge($domainsVorschlag['domains'] ?? [], $pdfData['domains']);
            }
        }

        // 4. Sonderdeal-Domains in die Domain-Liste mergen — wenn die KI eine Domain mit Preis
        // im Mailtext gefunden hat, soll sie auch als Domain-Vorschlag erscheinen (sonst hängt
        // ein Sonderdeal ohne erzeugte Linkquelle in der Luft).
        $bestehendeUrls = array_map(
            fn($d) => strtolower(preg_replace('#^https?://#i', '', preg_replace('#/+$#', '', (string) ($d['url'] ?? '')))),
            $domainsVorschlag['domains'] ?? []
        );
        foreach ($sonderdeals as $sd) {
            $rawUrl = trim((string) ($sd['domain'] ?? ''));
            if ($rawUrl === '') continue;
            $url = strtolower(preg_replace('#^https?://#i', '', preg_replace('#/+$#', '', $rawUrl)));
            if (in_array($url, $bestehendeUrls, true)) continue;
            $domainsVorschlag['domains'][] = [
                'url' => $url,
                'linkart' => $this->mappeBuchungstypAufLinkart($sd['buchungstyp'] ?? null),
                'preis' => isset($sd['preis_eur']) ? (float) $sd['preis_eur'] : null,
                'hinweis' => 'Aus Sonderdeal: ' . trim(($sd['notiz'] ?? '') . ' ' . ($sd['buchungstyp'] ?? '')),
            ];
            $bestehendeUrls[] = $url;
        }

        // 5. Matching gegen Bestand (Anbieter via Fuzzy, Domains via URL-Normalisierung, Kontakte via Mail)
        $extraction = $this->matcheBestaende([
            'anbieter' => $anbieterVorschlag,
            'domains' => $domainsVorschlag['domains'] ?? [],
            'linkart_mapping' => $domainsVorschlag['linkart_mapping'] ?? [],
            'sonderdeals' => $sonderdeals,
            'raw_mail_meta' => $rawMail ? [
                'from' => $rawMail['from'] ?? '',
                'subject' => $rawMail['subject'] ?? '',
                'date' => $rawMail['date'] ?? '',
            ] : null,
        ]);

        $meta['extraction'] = $extraction;
        $this->db->update('lam_import_batches',
            ['status' => 'extrahiert_geprueft', 'mapping' => json_encode($meta, JSON_UNESCAPED_UNICODE)],
            'id = ?', [$batchId]);

        return $extraction;
    }

    public function getExtraction(string $batchId): array
    {
        $batch = $this->db->queryOne("SELECT * FROM lam_import_batches WHERE id = ?", [$batchId]);
        if (!$batch) throw new \RuntimeException('Batch nicht gefunden');
        $meta = json_decode($batch['mapping'] ?? '{}', true) ?: [];
        return [
            'batch' => [
                'id' => $batch['id'],
                'dateiname' => $batch['dateiname'],
                'status' => $batch['status'],
                'importiert_am' => $batch['importiert_am'],
                'files' => $meta['files'] ?? [],
            ],
            'extraction' => $meta['extraction'] ?? null,
        ];
    }

    /**
     * COMMIT: Schreibt die ausgewählten/bearbeiteten Vorschläge transaktional in
     * die LAM-Tabellen und markiert den Batch als 'importiert'.
     *
     * $auswahl-Struktur (vom Frontend):
     *  {
     *    anbieter: { uebernehmen: bool, vorschlag: { ...bearbeitete Felder } },
     *    kontakte: [{ idx, uebernehmen, vorschlag: {...} }, ...],
     *    domains:  [{ idx, uebernehmen, vorschlag: {...} }, ...],
     *    sonderdeals: [{ idx, uebernehmen }, ...]
     *  }
     */
    public function commit(string $batchId, array $auswahl, int $userId): array
    {
        $batch = $this->db->queryOne("SELECT * FROM lam_import_batches WHERE id = ?", [$batchId]);
        if (!$batch) throw new \RuntimeException('Batch nicht gefunden');
        if ($batch['status'] === 'importiert') throw new \RuntimeException('Batch wurde bereits importiert');
        $meta = json_decode($batch['mapping'] ?? '{}', true) ?: [];
        $extraction = $meta['extraction'] ?? null;
        if (!$extraction) throw new \RuntimeException('Keine Extraktion vorhanden — bitte zuerst analysieren');

        $mandant = $this->db->queryValue("SELECT mandant_id FROM lam_anbieter LIMIT 1") ?: 'thoxan';
        $stats = ['anbieter' => 0, 'kontakte' => 0, 'domains_neu' => 0, 'domains_update' => 0, 'konditionen' => 0, 'sonderdeals' => 0, 'fehler' => []];

        $this->db->beginTransaction();
        try {
            // === Anbieter ===
            $anbieterId = null;
            $anbieterAuswahl = $auswahl['anbieter'] ?? null;
            if ($anbieterAuswahl && !empty($anbieterAuswahl['uebernehmen']) && $extraction['anbieter']) {
                $vorschlag = array_merge((array)($extraction['anbieter']['vorschlag'] ?? []), (array)($anbieterAuswahl['vorschlag'] ?? []));
                $match = $extraction['anbieter']['match'] ?? null;
                if ($match) {
                    $anbieterId = $match['id'];
                    // Update notizen ergänzen, restliche Felder nicht überschreiben
                    $altNotizen = (string)($this->db->queryValue("SELECT notizen FROM lam_anbieter WHERE id = ?", [$anbieterId]) ?? '');
                    $neuBlock = $this->bildeAnbieterNotizen($vorschlag);
                    if (!str_contains($altNotizen, '## Aus Portfolio-Import')) {
                        $this->db->update('lam_anbieter',
                            ['notizen' => trim($altNotizen . "\n\n" . $neuBlock)],
                            'id = ?', [$anbieterId]);
                    }
                } else {
                    $anbieterId = $this->ulid();
                    $this->db->insert('lam_anbieter', [
                        'id' => $anbieterId,
                        'name' => $vorschlag['name'] ?: ($vorschlag['firma'] ?: 'Unbekannt'),
                        'firma' => $vorschlag['firma'] ?? null,
                        'beziehungsstatus' => 'neu',
                        'ist_betreiber' => 1,
                        'ist_vermittler' => 1, // WakeUp-Typ: vermittelt eigenes Portfolio
                        'notizen' => $this->bildeAnbieterNotizen($vorschlag),
                        'erstellt_am' => date('Y-m-d H:i:s'),
                        'mandant_id' => $mandant,
                    ]);
                    $stats['anbieter']++;
                }
            }

            // === Kontakte ===
            $kontakteEingaben = $auswahl['kontakte'] ?? [];
            $kontakteVorschlaege = $extraction['kontakte'] ?? [];
            foreach ($kontakteVorschlaege as $idx => $k) {
                $a = $this->findeAuswahl($kontakteEingaben, $idx);
                if (!$a || empty($a['uebernehmen'])) continue;
                $v = array_merge((array)($k['vorschlag'] ?? []), (array)($a['vorschlag'] ?? []));
                if (empty($v['nachname']) && empty($v['email'])) continue;
                if ($k['match']) continue; // Vorhandene Kontakte nicht doppelt anlegen
                $this->db->insert('lam_kontakte', [
                    'id' => $this->ulid(),
                    'anbieter_id' => $anbieterId,
                    'vorname' => $v['vorname'] ?: null,
                    'nachname' => $v['nachname'] ?: '(unbekannt)',
                    'email' => $v['email'] ?: null,
                    'telefon' => $v['telefon'] ?: null,
                    'rolle' => $v['rolle'] ?: null,
                    'verifikation_status' => 'verifiziert',
                    'verifiziert_am' => date('Y-m-d'),
                    'verifiziert_von_user_id' => $userId,
                    'import_batch_id' => $batchId,
                    'quelle_anhang' => 'Portfolio-Import-Mail',
                    'prioritaet' => 0,
                    'erstellt_am' => date('Y-m-d H:i:s'),
                    'mandant_id' => $mandant,
                ]);
                $stats['kontakte']++;
            }

            // === Domains + Konditionen ===
            $buchTypMap = ['beitrag' => 'gastartikel', 'beitrag_special' => 'gastartikel_special', 'werbung' => 'advertorial', 'startseite' => 'homepage'];
            $domainsEingaben = $auswahl['domains'] ?? [];
            $domainsVorschlaege = $extraction['domains'] ?? [];
            $sonderdealsByDomain = [];
            foreach (($extraction['sonderdeals'] ?? []) as $sIdx => $sd) {
                $key = $this->normalisiereDomain($sd['domain'] ?? '');
                $sonderdealsByDomain[$key] = ['data' => $sd, 'idx' => $sIdx];
            }
            $sonderdealAuswahl = $auswahl['sonderdeals'] ?? [];

            foreach ($domainsVorschlaege as $idx => $d) {
                $a = $this->findeAuswahl($domainsEingaben, $idx);
                if (!$a || empty($a['uebernehmen'])) continue;
                $v = array_merge((array)($d['vorschlag'] ?? []), (array)($a['vorschlag'] ?? []));
                $url = $this->normalisiereDomain($v['url'] ?? '');
                if ($url === '') continue;

                $match = $d['match'] ?? null;
                $domainId = $match['id'] ?? null;
                $domainNotiz = $this->bildeDomainNotiz($v);
                $sd = $sonderdealsByDomain[$url] ?? null;
                $sdUebernehmen = $sd && !empty($this->findeAuswahl($sonderdealAuswahl, $sd['idx'])['uebernehmen'] ?? false);
                if ($sdUebernehmen) $domainNotiz = trim($domainNotiz . "\n\n" . $this->bildeSonderdealNotiz($sd['data']));

                if ($domainId) {
                    // Update: Anbieter eintragen, falls leer; Notizen anhängen wenn frisch.
                    // Wichtig: import_batch_id der Bestand-Domain NICHT überschreiben — ein späterer
                    // Cleanup per Batch-ID würde sonst die Bestand-Domain mitlöschen.
                    $altNotizen = (string)($this->db->queryValue("SELECT notizen FROM lam_domains WHERE id = ?", [$domainId]) ?? '');
                    $update = [];
                    if ($anbieterId && empty($match['anbieter_id'])) $update['anbieter_id'] = $anbieterId;
                    if (!str_contains($altNotizen, '## Aus Portfolio-Import')) {
                        $update['notizen'] = trim($altNotizen . "\n\n" . $domainNotiz);
                    }
                    if (!empty($update)) {
                        $this->db->update('lam_domains', $update, 'id = ?', [$domainId]);
                    }
                    $stats['domains_update']++;
                } else {
                    $domainId = $this->ulid();
                    $this->db->insert('lam_domains', [
                        'id' => $domainId,
                        'url' => $url,
                        'anbieter_id' => $anbieterId,
                        'quelle_recherche' => 'Portfolio-Import',
                        'buchbar_via' => 'anbieter',
                        'disqualifiziert' => 0,
                        'notizen' => $domainNotiz,
                        'verifikation_status' => 'unbestaetigt',
                        'import_batch_id' => $batchId,
                        'quelle_anhang' => 'Portfolio-Excel',
                        'linkart' => 'online_magazin',
                        'erstellt_am' => date('Y-m-d H:i:s'),
                        'mandant_id' => $mandant,
                    ]);
                    $stats['domains_neu']++;
                }

                // Konditionen pro Buchungstyp
                foreach (($v['preise'] ?? []) as $slug => $preis) {
                    if (!is_numeric($preis) || $preis <= 0) continue;
                    $btyp = $buchTypMap[$slug] ?? $slug;
                    // Dublette-Check: gleiche Domain + Buchungstyp aus diesem Anbieter
                    $existId = $this->db->queryValue(
                        "SELECT id FROM lam_konditionen
                         WHERE domain_id = ? AND buchungstyp = ? AND via_anbieter_id <=> ? AND geloescht_am IS NULL LIMIT 1",
                        [$domainId, $btyp, $anbieterId]
                    );
                    if ($existId) continue; // schon vorhanden, nicht doppelt anlegen
                    $this->db->insert('lam_konditionen', [
                        'id' => $this->ulid(),
                        'domain_id' => $domainId,
                        'buchungstyp' => $btyp,
                        'preis' => (float)$preis,
                        'laufzeit_monate' => 24, // aus Mail: 24 Mon. Standard
                        'gekennzeichnet' => 0,
                        'link_typ' => 'follow',
                        'inkl_text' => 1, // Texterstellung im Preis enthalten (laut Mail)
                        'wortzahl_min' => 400,
                        'themenausschluss' => $slug === 'beitrag_special' ? 'Special Interest (adult/drugs/gambling)' : null,
                        'verifikation_status' => 'unbestaetigt',
                        'import_batch_id' => $batchId,
                        'quelle_anhang' => 'Portfolio-Excel',
                        'via_anbieter_id' => $anbieterId,
                        'erstellt_am' => date('Y-m-d H:i:s'),
                        'mandant_id' => $mandant,
                    ]);
                    $stats['konditionen']++;
                }

                // Sonderdeal: verifizierte Mail-Zusage. Wenn schon eine "normale" Kondition
                // existiert (gleiche Domain × Buchungstyp × Preis) — upgrade sie zu "verifiziert"
                // und übernimm die Notiz. Sonst neue Kondition anlegen.
                if ($sdUebernehmen && $sd) {
                    $sdData = $sd['data'];
                    $btypSd = $this->mappeBuchungstypAusKi($sdData['buchungstyp'] ?? '');
                    $existId = $this->db->queryValue(
                        "SELECT id FROM lam_konditionen
                         WHERE domain_id = ? AND buchungstyp = ? AND via_anbieter_id <=> ?
                           AND ABS(preis - ?) < 0.01 AND geloescht_am IS NULL LIMIT 1",
                        [$domainId, $btypSd, $anbieterId, (float)($sdData['preis_eur'] ?? 0)]
                    );
                    if ($existId) {
                        // Upgrade auf verifiziert + Notiz aus Mail
                        $this->db->update('lam_konditionen', [
                            'verifikation_status' => 'verifiziert',
                            'verifiziert_am' => date('Y-m-d'),
                            'verifiziert_von_user_id' => $userId,
                            'themenausschluss' => $sdData['notiz'] ?? null,
                            'inkl_text' => !empty($sdData['inkl_text']) ? 1 : 0,
                            'quelle_anhang' => 'Portfolio-Mail (Sonderdeal-Bestätigung)',
                        ], 'id = ?', [$existId]);
                    } else {
                        $this->db->insert('lam_konditionen', [
                            'id' => $this->ulid(),
                            'domain_id' => $domainId,
                            'buchungstyp' => $btypSd,
                            'preis' => (float)($sdData['preis_eur'] ?? 0),
                            'laufzeit_monate' => (int)($sdData['laufzeit_monate'] ?? 24),
                            'gekennzeichnet' => 0,
                            'link_typ' => 'follow',
                            'inkl_text' => !empty($sdData['inkl_text']) ? 1 : 0,
                            'wortzahl_min' => 400,
                            'themenausschluss' => $sdData['notiz'] ?? null,
                            'verifikation_status' => 'verifiziert',
                            'verifiziert_am' => date('Y-m-d'),
                            'verifiziert_von_user_id' => $userId,
                            'import_batch_id' => $batchId,
                            'quelle_anhang' => 'Portfolio-Mail (Sonderdeal)',
                            'via_anbieter_id' => $anbieterId,
                            'erstellt_am' => date('Y-m-d H:i:s'),
                            'mandant_id' => $mandant,
                        ]);
                    }
                    $stats['sonderdeals']++;
                }
            }

            // Batch-Status finalisieren
            $meta['committed_at'] = date('Y-m-d H:i:s');
            $meta['committed_stats'] = $stats;
            $this->db->update('lam_import_batches', [
                'status' => 'importiert',
                'anzahl_neu' => $stats['domains_neu'] + $stats['kontakte'] + $stats['anbieter'],
                'anzahl_dublette' => $stats['domains_update'],
                'mapping' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ], 'id = ?', [$batchId]);

            $this->db->commit();
            return $stats;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function findeAuswahl(array $arr, int $idx): ?array
    {
        foreach ($arr as $a) {
            if ((int)($a['idx'] ?? -1) === $idx) return $a;
        }
        return null;
    }

    private function bildeAnbieterNotizen(array $v): string
    {
        $lines = ['## Aus Portfolio-Import (' . date('Y-m-d') . ')'];
        if (!empty($v['strasse']) || !empty($v['plz']) || !empty($v['ort'])) {
            $lines[] = 'Adresse: ' . trim(($v['strasse'] ?? '') . ', ' . ($v['plz'] ?? '') . ' ' . ($v['ort'] ?? ''), ' ,');
        }
        if (!empty($v['web'])) $lines[] = 'Web: ' . $v['web'];
        if (!empty($v['handelsregister'])) $lines[] = 'Handelsregister: ' . $v['handelsregister'];
        if (!empty($v['geschaeftsfuehrer'])) {
            $gf = is_array($v['geschaeftsfuehrer']) ? implode(', ', $v['geschaeftsfuehrer']) : $v['geschaeftsfuehrer'];
            $lines[] = 'Geschäftsführer: ' . $gf;
        }
        return implode("\n", $lines);
    }

    private function bildeDomainNotiz(array $v): string
    {
        $lines = ['## Aus Portfolio-Import (' . date('Y-m-d') . ')'];
        $thema = trim(($v['thema_block'] ?? '') . ($v['thema_inline'] ? ' · ' . $v['thema_inline'] : ''), ' ·');
        if ($thema !== '') $lines[] = 'Themen: ' . $thema;
        return implode("\n", $lines);
    }

    private function bildeSonderdealNotiz(array $sd): string
    {
        $lines = ['### Sonderdeal aus Mail'];
        $lines[] = sprintf('%s · %s €%s%s',
            $sd['buchungstyp'] ?? '?',
            $sd['preis_eur'] ?? '?',
            !empty($sd['laufzeit_monate']) ? ' · ' . $sd['laufzeit_monate'] . ' Mon.' : '',
            !empty($sd['inkl_text']) ? ' · inkl. Text' : ''
        );
        if (!empty($sd['notiz'])) $lines[] = 'Notiz: ' . $sd['notiz'];
        return implode("\n", $lines);
    }

    private function mappeBuchungstypAusKi(string $s): string
    {
        $m = strtolower(trim($s));
        return match (true) {
            str_contains($m, 'startseit') || str_contains($m, 'homepage') => 'homepage',
            str_contains($m, 'werb') || str_contains($m, 'advertorial') => 'advertorial',
            str_contains($m, 'special') => 'gastartikel_special',
            str_contains($m, 'beitrag') || str_contains($m, 'gast') || str_contains($m, 'blog') => 'gastartikel',
            default => 'gastartikel',
        };
    }

    // ===== EML-Parser =====

    private function parseEml(string $pfad): array
    {
        $raw = file_get_contents($pfad);
        if (!$raw) return [];
        // Symfony Mime hilft. Fallback: einfacher Parser.
        // Erst die Header lesen
        $boundary = '';
        $headers = [];
        $body = '';
        if (preg_match('/^(.*?)\r?\n\r?\n(.*)$/s', $raw, $m)) {
            $headerBlock = $m[1];
            $body = $m[2];
            foreach (preg_split('/\r?\n(?![ \t])/', $headerBlock) as $line) {
                if (preg_match('/^([\w-]+):\s*(.*)$/s', $line, $hm)) {
                    $headers[strtolower($hm[1])] = trim(preg_replace('/\s+/', ' ', $hm[2]));
                }
            }
        }
        $from = $headers['from'] ?? '';
        $subject = $this->decodeMimeHeader($headers['subject'] ?? '');
        $date = $headers['date'] ?? '';
        $cc = $this->decodeMimeHeader($headers['cc'] ?? '');

        // Plain-Text-Teil rausziehen
        $plain = $this->extrahierePlainText($body, $headers['content-type'] ?? '');
        // Beim ersten Quote-Block schneiden (>) wir kümmern uns um die neueste Nachricht
        $plainShort = preg_split('/\r?\n>\s/u', $plain, 2)[0];

        return [
            'from' => $this->decodeMimeHeader($from),
            'subject' => $subject,
            'date' => $date,
            'cc' => $cc,
            'plain' => trim($plainShort),
            'plain_full' => trim($plain),
        ];
    }

    private function decodeMimeHeader(string $s): string
    {
        if ($s === '') return '';
        $decoded = mb_decode_mimeheader($s);
        return trim($decoded);
    }

    private function extrahierePlainText(string $body, string $contentType): string
    {
        if (stripos($contentType, 'multipart/') === 0 && preg_match('/boundary="?([^";\s]+)"?/', $contentType, $bm)) {
            $boundary = $bm[1];
            $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?\r?\n/', $body);
            foreach ($parts as $part) {
                if (preg_match('/^(.*?)\r?\n\r?\n(.*)$/s', $part, $m)) {
                    $partHeaders = strtolower($m[1]);
                    if (str_contains($partHeaders, 'text/plain')) {
                        $partBody = $m[2];
                        // Decode quoted-printable / base64
                        if (str_contains($partHeaders, 'quoted-printable')) {
                            $partBody = quoted_printable_decode($partBody);
                        } elseif (str_contains($partHeaders, 'base64')) {
                            $partBody = base64_decode($partBody) ?: $partBody;
                        }
                        // Charset
                        if (preg_match('/charset="?([\w-]+)"?/i', $partHeaders, $cm)) {
                            $cs = strtoupper($cm[1]);
                            if ($cs !== 'UTF-8') $partBody = @mb_convert_encoding($partBody, 'UTF-8', $cs) ?: $partBody;
                        }
                        return $partBody;
                    }
                }
            }
            // Falls multipart aber kein text/plain — nimm ersten Part
            if (!empty($parts[1])) return $parts[1];
        }
        return $body;
    }

    private function parseCsv(string $pfad): array
    {
        $rows = [];
        if (($fh = fopen($pfad, 'r')) === false) return [];
        // Trennzeichen auto-detect: erste Zeile checken
        $first = fgets($fh);
        $sep = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
        rewind($fh);
        while (($r = fgetcsv($fh, 0, $sep)) !== false) {
            $rows[] = $r;
            if (count($rows) >= 5000) break;
        }
        fclose($fh);
        return $rows;
    }

    // ===== Anbieter aus Mail-Signatur (KI) =====

    private function extrahiereAnbieterAusMail(array $mail): ?array
    {
        $plain = trim($mail['plain'] ?? '');
        if ($plain === '') return null;
        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            return $this->heuristikAnbieterAusMail($mail);
        }
        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(1200);
        $ai->setTimeout(30);

        $system = 'Du extrahierst aus einer E-Mail-Signatur die Anbieter- und Kontaktdaten. '
            . 'Antworte AUSSCHLIESSLICH mit JSON in diesem Format:' . "\n"
            . '{"anbieter":{"name":"...","firma":"...","strasse":"...","plz":"...","ort":"...","web":"...","handelsregister":"...","geschaeftsfuehrer":["...","..."]},'
            . '"kontakte":[{"vorname":"...","nachname":"...","email":"...","telefon":"...","rolle":"..."}]}' . "\n"
            . 'Wenn ein Feld nicht in der Mail vorkommt: leerer String oder leeres Array. '
            . 'Erfinde nichts. Mehrere Kontakte sind moeglich (Absender, CC, in Signatur erwaehnte).';

        $userPrompt = "MAIL-FROM: " . ($mail['from'] ?? '') . "\n"
            . "CC: " . ($mail['cc'] ?? '') . "\n"
            . "SUBJECT: " . ($mail['subject'] ?? '') . "\n\n"
            . "MAIL-BODY:\n" . mb_substr($plain, 0, 4000);

        try {
            $antwort = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $system);
            $content = trim($antwort['content'] ?? '');
            if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
            $daten = json_decode($content, true);
            if (is_array($daten) && isset($daten['anbieter'])) return $daten;
        } catch (\Throwable $e) {
            // Fall-Through zur Heuristik
        }
        return $this->heuristikAnbieterAusMail($mail);
    }

    private function heuristikAnbieterAusMail(array $mail): array
    {
        $plain = $mail['plain'] ?? '';
        $from = $mail['from'] ?? '';
        $anb = ['name' => '', 'firma' => '', 'strasse' => '', 'plz' => '', 'ort' => '', 'web' => '', 'handelsregister' => '', 'geschaeftsfuehrer' => []];
        $kont = [];
        // From: "Name <email>"
        if (preg_match('/^(.*?)<([^>]+)>/', $from, $m)) {
            $nm = trim($m[1], "\" \t");
            $em = trim($m[2]);
            $parts = preg_split('/\s+/', $nm, 2);
            $kont[] = ['vorname' => $parts[0] ?? '', 'nachname' => $parts[1] ?? '', 'email' => $em, 'telefon' => '', 'rolle' => ''];
        }
        // Firma raten aus From
        if (preg_match('/<[^@>]+@([^>]+)>/', $from, $m)) {
            $anb['web'] = 'https://www.' . preg_replace('/^www\./', '', $m[1]);
            $anb['firma'] = ucwords(str_replace('-', ' ', explode('.', $m[1])[0]));
        }
        // PLZ + Ort
        if (preg_match('/\b(\d{5})\s+([A-ZÄÖÜ][\w\s.-]+)\b/u', $plain, $m)) {
            $anb['plz'] = $m[1]; $anb['ort'] = trim($m[2]);
        }
        return ['anbieter' => $anb, 'kontakte' => $kont];
    }

    // ===== Sonderdeals aus Mailtext =====

    private function extrahiereSonderdealsAusMail(array $mail): array
    {
        $plain = trim($mail['plain'] ?? '');
        if ($plain === '') return [];
        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) return [];
        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(800);
        $ai->setTimeout(25);

        $system = 'Du extrahierst aus E-Mail-Text konkret zugesagte Konditionen pro Domain. '
            . 'Antworte AUSSCHLIESSLICH mit JSON: {"deals":[{"domain":"...","preis_eur":0,"laufzeit_monate":0,"buchungstyp":"...","inkl_text":true,"notiz":"..."}]} . '
            . 'buchungstyp: "Beitrag" | "Werbung" | "Startseite" | "Sonstige". '
            . 'inkl_text=true wenn Texterstellung im Preis enthalten. Wenn nichts Konkretes zugesagt: deals leer.';
        $userPrompt = "MAIL-BODY:\n" . mb_substr($plain, 0, 3500);
        try {
            $antwort = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $system);
            $content = trim($antwort['content'] ?? '');
            if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
            $daten = json_decode($content, true);
            return is_array($daten) && isset($daten['deals']) ? array_values((array)$daten['deals']) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ===== Domains + Konditionen aus Excel =====

    private function extrahiereDomainsAusExcel(array $rows, ?string $dateiname): array
    {
        // Schritt 1: Spalten-Erkennung — finde Header-Zeile mit "Projekt" o.ä.
        $headerRowIdx = null;
        $headerCols = [];
        $domainCol = null;
        $themaCol = null;
        $priceCols = [];

        foreach ($rows as $idx => $r) {
            $flat = array_map(fn($v) => trim((string)($v ?? '')), $r);
            $joined = mb_strtolower(implode(' ', $flat));
            if (str_contains($joined, 'projekt') || str_contains($joined, 'project') || str_contains($joined, 'domain')) {
                $headerRowIdx = $idx;
                $headerCols = $flat;
                // Erkennung der Spalten in dieser Zeile
                foreach ($flat as $ci => $h) {
                    $hl = mb_strtolower($h);
                    if ($domainCol === null && (str_contains($hl, 'projekt') || str_contains($hl, 'project') || str_contains($hl, 'domain'))) {
                        $domainCol = $ci;
                    } elseif ($themaCol === null && (str_contains($hl, 'thema') || str_contains($hl, 'topic') || str_contains($hl, 'kategorie'))) {
                        $themaCol = $ci;
                    } elseif ($h !== '' && preg_match('/(blog post|special interest|advertorial|review|homepage|startseite|werbung|special|beitrag)/i', $h)) {
                        $priceCols[$ci] = $h;
                    }
                }
                break;
            }
        }
        if ($headerRowIdx === null) {
            // Fallback: keine Header-Zeile gefunden — Spalte B als Domain raten
            $headerRowIdx = 0;
            $domainCol = 1;
        }

        // KI-Mapping: Spaltennamen → Linkart-Slug
        $linkartMap = $this->kiLinkartMapping($priceCols);

        // Schritt 2: Themen-Block-Header erkennen (Zeilen mit nur einem Wert in Spalte B, alle Preisspalten leer)
        // — der String dient als Themen-Tag für die folgenden Domains.
        $aktuellesThema = '';
        $domains = [];
        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $flat = array_map(fn($v) => trim((string)($v ?? '')), $r);
            if ($domainCol === null) continue;
            $dom = $flat[$domainCol] ?? '';
            if ($dom === '') continue;
            $hatPreis = false;
            $preise = [];
            foreach ($priceCols as $ci => $bez) {
                $p = $flat[$ci] ?? '';
                if (is_numeric(str_replace(',', '.', $p))) {
                    $hatPreis = true;
                    $preise[$linkartMap[$ci] ?? $bez] = (float) str_replace(',', '.', $p);
                }
            }
            // Wenn nur ein Wert in Domain-Spalte und keine Preise: das ist ein Themen-Block
            $istBlockHeader = !$hatPreis && (mb_strpos($dom, '(Themen:') !== false || preg_match('/^[\p{L}\s\-\/]+\s*$/u', $dom)) && !str_contains($dom, '.');
            if ($istBlockHeader) {
                $aktuellesThema = preg_replace('/\s*\(Themen:.*?\)\s*$/u', '', $dom);
                continue;
            }
            // Echte Domain-Zeile?
            if (!preg_match('/[a-z0-9-]+\.[a-z]{2,}/i', $dom)) continue;

            $thema_inline = $themaCol !== null ? ($flat[$themaCol] ?? '') : '';
            $domains[] = [
                'url' => $this->normalisiereDomain($dom),
                'url_raw' => $dom,
                'thema_block' => $aktuellesThema,
                'thema_inline' => $thema_inline,
                'preise' => $preise,
            ];
        }
        return ['domains' => $domains, 'linkart_mapping' => $linkartMap, 'header_cols' => $headerCols, 'dateiname' => $dateiname];
    }

    private function kiLinkartMapping(array $priceCols): array
    {
        // Heuristik zuerst — die ist zuverlässig für die typischen Wörter
        $map = [];
        foreach ($priceCols as $ci => $bez) {
            $b = mb_strtolower($bez);
            if (str_contains($b, 'homepage') || str_contains($b, 'startseite')) $map[$ci] = 'startseite';
            elseif (str_contains($b, 'advertorial') || str_contains($b, 'review') || str_contains($b, 'werbung')) $map[$ci] = 'werbung';
            elseif (str_contains($b, 'special')) $map[$ci] = 'beitrag_special';
            elseif (str_contains($b, 'blog') || str_contains($b, 'beitrag') || str_contains($b, 'regular')) $map[$ci] = 'beitrag';
            else $map[$ci] = 'sonstige';
        }
        return $map;
    }

    private function normalisiereDomain(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('#^https?://#i', '', $s);
        $s = preg_replace('#^www\.#i', '', $s);
        $s = preg_replace('#/.*$#', '', $s);
        return strtolower($s);
    }

    // ===== Match gegen Bestand =====

    private function matcheBestaende(array $vor): array
    {
        $ergebnis = [
            'anbieter' => null,
            'kontakte' => [],
            'domains' => [],
            'sonderdeals' => $vor['sonderdeals'] ?? [],
            'raw_mail_meta' => $vor['raw_mail_meta'] ?? null,
            'linkart_mapping' => $vor['linkart_mapping'] ?? [],
        ];

        // Anbieter
        $anbieterVor = $vor['anbieter']['anbieter'] ?? null;
        if ($anbieterVor) {
            $match = $this->findeAnbieter($anbieterVor);
            $ergebnis['anbieter'] = [
                'vorschlag' => $anbieterVor,
                'match' => $match,
                'status' => $match ? 'update' : 'neu',
            ];
        }

        // Kontakte
        foreach ($vor['anbieter']['kontakte'] ?? [] as $k) {
            $match = !empty($k['email']) ? $this->db->queryOne(
                "SELECT id, vorname, nachname, email, telefon, rolle, anbieter_id FROM lam_kontakte WHERE email = ? AND geloescht_am IS NULL LIMIT 1",
                [$k['email']]
            ) : null;
            $ergebnis['kontakte'][] = [
                'vorschlag' => $k,
                'match' => $match ?: null,
                'status' => $match ? 'update' : 'neu',
            ];
        }

        // Domains
        foreach ($vor['domains'] as $d) {
            $match = $this->db->queryOne(
                "SELECT id, url, anbieter_id, notizen, linkart FROM lam_domains WHERE url = ? AND geloescht_am IS NULL LIMIT 1",
                [$d['url']]
            );
            $ergebnis['domains'][] = [
                'vorschlag' => $d,
                'match' => $match ?: null,
                'status' => $match ? 'update' : 'neu',
            ];
        }
        return $ergebnis;
    }

    private function findeAnbieter(array $a): ?array
    {
        $candidates = [];
        if (!empty($a['firma'])) {
            $candidates = $this->db->query(
                "SELECT id, name, firma, notizen FROM lam_anbieter
                 WHERE geloescht_am IS NULL AND (LOWER(firma) LIKE ? OR LOWER(name) LIKE ?) LIMIT 5",
                ['%' . mb_strtolower($a['firma']) . '%', '%' . mb_strtolower($a['firma']) . '%']
            ) ?: [];
        }
        if (empty($candidates) && !empty($a['web'])) {
            $host = $this->normalisiereDomain($a['web']);
            $candidates = $this->db->query(
                "SELECT id, name, firma, notizen FROM lam_anbieter
                 WHERE geloescht_am IS NULL AND (LOWER(notizen) LIKE ? OR LOWER(firma) LIKE ?) LIMIT 5",
                ['%' . $host . '%', '%' . $host . '%']
            ) ?: [];
        }
        return $candidates[0] ?? null;
    }

    // ===== PDF-Verarbeitung =====

    /** Liest Text aus einem PDF (nutzt DocumentProcessor → pdftotext). */
    private function extrahierePdfText(string $pfad): ?string
    {
        try {
            require_once SERVICES_PATH . '/DocumentProcessor.php';
            $dp = new DocumentProcessor();
            $r = $dp->processFile($pfad, 'application/pdf');
            return is_array($r) ? ($r['text'] ?? null) : null;
        } catch (\Throwable $e) {
            error_log('PortfolioImport: PDF-Extraktion fehlgeschlagen: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrahiert aus dem PDF-Text (typ. Mediadaten / Preisliste) Anbieter, Kontakte,
     * Domains und Preise per KI in einem Rutsch. Liefert dasselbe Schema wie die
     * Mail-Extraktion + die Domains für den Excel-Match-Path.
     */
    private function extrahierePortfolioAusPdf(string $text, ?string $dateiname): array
    {
        $text = trim($text);
        if ($text === '') return ['anbieter' => [], 'kontakte' => [], 'domains' => []];
        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            return ['anbieter' => [], 'kontakte' => [], 'domains' => []];
        }
        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(3500);
        $ai->setTimeout(60);

        $system = 'Du extrahierst aus deutschen Mediadaten- bzw. Preisliste-PDFs strukturierte Daten für ein LAM-Linkaufbau-CRM. '
            . 'Antworte AUSSCHLIESSLICH mit JSON in diesem Format:' . "\n"
            . '{' . "\n"
            . '  "anbieter": {"name":"...","firma":"...","strasse":"...","plz":"...","ort":"...","web":"...","handelsregister":"...","geschaeftsfuehrer":["..."]},' . "\n"
            . '  "kontakte": [{"vorname":"...","nachname":"...","email":"...","telefon":"...","rolle":"..."}],' . "\n"
            . '  "domains":  [{"url":"example.de","linkart":"gastbeitrag|ratgeber|redaktionsbeitrag|sponsored|listing|banner|newsletter|sonstiges","preis":123.45,"hinweis":"freie Notiz"}]' . "\n"
            . '}' . "\n"
            . 'Regeln: Domains immer ohne https:// und ohne trailing slash. Preise als Zahlen in Euro (kein Währungssymbol, kein Tausenderpunkt). '
            . 'Linkart bestmöglich klassifizieren — wenn unklar: "sonstiges". '
            . 'Wenn Felder nicht im PDF stehen: leerer String / leeres Array. Erfinde nichts.';

        $userPrompt = ($dateiname ? "DATEI: $dateiname\n\n" : '')
            . "PDF-TEXT:\n" . mb_substr($text, 0, 15000);

        try {
            $antwort = $ai->chat([['role' => 'user', 'content' => $userPrompt]], $system);
            $content = trim($antwort['content'] ?? '');
            // JSON aus eventuell vorhandenem Code-Block extrahieren
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) $content = $m[1];
            elseif (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
            $daten = json_decode($content, true);
            if (!is_array($daten)) return ['anbieter' => [], 'kontakte' => [], 'domains' => []];
            // Domains normalisieren (gleiche Norm wie speichereDomain)
            foreach (($daten['domains'] ?? []) as &$d) {
                if (!empty($d['url'])) {
                    $u = preg_replace('#^https?://#i', '', (string) $d['url']);
                    $u = preg_replace('#/+$#', '', $u);
                    $d['url'] = strtolower($u);
                }
            }
            return [
                'anbieter' => $daten['anbieter'] ?? [],
                'kontakte' => $daten['kontakte'] ?? [],
                'domains'  => $daten['domains']  ?? [],
            ];
        } catch (\Throwable $e) {
            error_log('PortfolioImport PDF-KI-Fehler: ' . $e->getMessage());
            return ['anbieter' => [], 'kontakte' => [], 'domains' => []];
        }
    }

    // ===== Helper =====

    /** Sonderdeal-Buchungstyp ("Beitrag", "Werbung", "Startseite") → Linkart-Wert (lam_kunde_linkart-Schema). */
    private function mappeBuchungstypAufLinkart(?string $typ): ?string
    {
        $t = mb_strtolower(trim((string) $typ));
        if ($t === '') return null;
        $map = [
            'beitrag'        => 'gastbeitrag',
            'gastbeitrag'    => 'gastbeitrag',
            'ratgeber'       => 'ratgeber',
            'redaktion'      => 'redaktionsbeitrag',
            'redaktionsbeitrag' => 'redaktionsbeitrag',
            'sponsored'      => 'sponsored',
            'werbung'        => 'banner',
            'banner'         => 'banner',
            'newsletter'     => 'newsletter',
            'social'         => 'social',
            'listing'        => 'listing',
            'startseite'     => 'banner',
        ];
        return $map[$t] ?? 'sonstiges';
    }

    private function erkennetyp(string $name): string
    {
        $n = strtolower($name);
        if (str_ends_with($n, '.eml')) return 'eml';
        if (str_ends_with($n, '.msg')) return 'eml';
        if (str_ends_with($n, '.xlsx') || str_ends_with($n, '.xlsm')) return 'xlsx';
        if (str_ends_with($n, '.csv')) return 'csv';
        if (str_ends_with($n, '.pdf')) return 'pdf';
        return 'sonstige';
    }

    private function ulid(): string
    {
        // ULID-light: 26 chars Crockford-Base32. Deterministisch-genug fuer DB-IDs.
        $time = (int) (microtime(true) * 1000);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $t = '';
        for ($i = 0; $i < 10; $i++) { $t = $alphabet[$time & 31] . $t; $time >>= 5; }
        $rand = '';
        for ($i = 0; $i < 16; $i++) $rand .= $alphabet[random_int(0, 31)];
        return $t . $rand;
    }
}
