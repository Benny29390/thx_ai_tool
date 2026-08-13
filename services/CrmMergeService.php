<?php
/**
 * CrmMergeService — Side-by-side Merge von 2+ Kontakten oder Firmen.
 *
 * Workflow:
 *  1. mergePreviewKontakt(ids) / mergePreviewFirma(ids)
 *     → liefert alle Felder + Sub-Daten jedes Records (für UI-Vergleich)
 *  2. UI zeigt diese als Tabelle, User markiert pro Feld welcher Wert bleibt
 *  3. mergeKontakte(masterId, [loserIds], fieldChoices)
 *     bzw. mergeFirmen(masterId, [loserIds], fieldChoices)
 *     → wendet die Auswahl an, hängt Sub-Daten um, soft-deletet die Loser
 *
 * Sub-Daten-Strategie:
 *  - Verhalten "merge" (Default): alle Sub-Daten beider Records in den Master
 *    übernehmen, Duplikate vermeiden (UNION). Verschiedene Typen (Adresse/Geschäftlich,
 *    Adresse/Privat) bleiben nebeneinander.
 *  - Bei Konflikten (z.B. zwei "Geschäftlich"-Adressen): nimm die aus dem Master,
 *    die andere wird verworfen.
 *
 * Atomisch via Transaktion. Audit-Log + Knowledge-Sync werden mit ausgelöst.
 */

namespace Services;

use Core\Database;
use Core\AuditLog;

class CrmMergeService
{
    public function __construct(private Database $db) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PREVIEW (für UI)
    // ═══════════════════════════════════════════════════════════════════════

    public function mergePreviewKontakt(array $kontaktIds): array
    {
        $kontaktIds = array_values(array_unique(array_map('intval', $kontaktIds)));
        if (count($kontaktIds) < 2) throw new \InvalidArgumentException('Mindestens 2 IDs nötig');

        $records = [];
        foreach ($kontaktIds as $id) {
            $k = $this->db->queryOne(
                "SELECT k.*, f.firmenname AS firma_name
                 FROM crm_kontakte k LEFT JOIN crm_firmen f ON f.id = k.firma_id
                 WHERE k.id = ? AND k.geloescht_am IS NULL",
                [$id]
            );
            if (!$k) continue;
            $k['_subdata'] = [
                'adressen'   => $this->db->query("SELECT * FROM crm_adressen WHERE kontakt_id = ? ORDER BY typ", [$id]),
                'tags'       => $this->db->query(
                    "SELECT t.id, t.name, t.farbe, kt.vergeben_am FROM crm_kontakt_tags kt
                     JOIN crm_tags t ON t.id = kt.tag_id WHERE kt.kontakt_id = ?", [$id]
                ),
                'listen'     => $this->db->query(
                    "SELECT l.id, l.name, kl.status, kl.beigetreten_am FROM crm_kontakt_listen kl
                     JOIN crm_listen l ON l.id = kl.listen_id WHERE kl.kontakt_id = ?", [$id]
                ),
                'social'     => $this->db->query("SELECT plattform, url FROM crm_social_links WHERE kontakt_id = ?", [$id]),
                'aktivitaeten_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_aktivitaeten WHERE kontakt_id = ?", [$id]),
                'mails_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_brevo_events WHERE kontakt_id = ?", [$id]),
                'opt_in_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_opt_in_events WHERE kontakt_id = ?", [$id]),
                'lead_magnet_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_lead_magnet_events WHERE kontakt_id = ?", [$id]),
                'kunden_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_kunden_zuordnung WHERE kontakt_id = ?", [$id]),
            ];
            $records[] = $k;
        }

        return [
            'typ' => 'kontakt',
            'records' => $records,
            'felder' => $this->kontaktFelderFuerVergleich(),
            'master_vorschlag' => $this->vorschlagMaster($records, 'kontakt'),
            'subdaten_zusammenfassung' => $this->fasseSubDatenKontaktZusammen($records),
        ];
    }

    /**
     * Klare Zusammenfassung was beim Merge mit Sub-Daten passiert.
     * Additive Daten (Notizen, Mails, Aktivitäten, Tags, Listen, Kunden-Zuordnungen)
     * werden ZUSAMMENGEFÜHRT — kein Datenverlust.
     * Konflikte (Adress-Typ doppelt, Social-Plattform doppelt) werden EXPLIZIT
     * aufgelistet — der User sieht was bei aktuellem Master-Stand verloren ginge.
     */
    private function fasseSubDatenKontaktZusammen(array $records): array
    {
        $additiv = [
            'tags'              => 0,
            'notizen'           => 0,
            'aktivitaeten'      => 0,
            'mail_events'       => 0,
            'opt_in_events'     => 0,
            'lead_magnet_events'=> 0,
            'listen'            => 0,
            'kunden_zuordnungen'=> 0,
        ];
        // Konflikte: pro adress-typ + social-plattform alle unterschiedlichen Werte sammeln
        $adressenProTyp = [];
        $socialProPlattform = [];

        foreach ($records as $r) {
            $sd = $r['_subdata'] ?? [];
            $additiv['tags']               += count($sd['tags'] ?? []);
            $additiv['listen']             += count($sd['listen'] ?? []);
            $additiv['aktivitaeten']       += $sd['aktivitaeten_count'] ?? 0;
            $additiv['mail_events']        += $sd['mails_count'] ?? 0;
            $additiv['opt_in_events']      += $sd['opt_in_count'] ?? 0;
            $additiv['lead_magnet_events'] += $sd['lead_magnet_count'] ?? 0;
            $additiv['kunden_zuordnungen'] += $sd['kunden_count'] ?? 0;

            foreach ($sd['adressen'] ?? [] as $a) {
                $typ = $a['typ'];
                $hash = md5(($a['strasse'] ?? '') . '|' . ($a['plz'] ?? '') . '|' . ($a['stadt'] ?? ''));
                $adressenProTyp[$typ][$hash] = [
                    'kontakt_id' => (int)$r['id'],
                    'adresse' => $a,
                ];
            }
            foreach ($sd['social'] ?? [] as $s) {
                $plattform = $s['plattform'];
                $url = trim($s['url'] ?? '');
                if ($url === '') continue;
                $socialProPlattform[$plattform][$url] = [
                    'kontakt_id' => (int)$r['id'],
                    'url' => $url,
                ];
            }
        }

        $konflikte = [];
        foreach ($adressenProTyp as $typ => $varianten) {
            if (count($varianten) > 1) {
                $konflikte[] = [
                    'typ' => 'adresse',
                    'label' => 'Adresse „' . ucfirst($typ) . '"',
                    'beschreibung' => count($varianten) . ' unterschiedliche Adressen vorhanden',
                    'varianten' => array_values($varianten),
                ];
            }
        }
        foreach ($socialProPlattform as $plattform => $varianten) {
            if (count($varianten) > 1) {
                $konflikte[] = [
                    'typ' => 'social',
                    'label' => 'Social: ' . ucfirst($plattform),
                    'beschreibung' => count($varianten) . ' unterschiedliche URLs',
                    'varianten' => array_values($varianten),
                ];
            }
        }

        return [
            'additiv' => $additiv,
            'konflikte' => $konflikte,
            'hat_konflikte' => !empty($konflikte),
        ];
    }

    public function mergePreviewFirma(array $firmaIds): array
    {
        $firmaIds = array_values(array_unique(array_map('intval', $firmaIds)));
        if (count($firmaIds) < 2) throw new \InvalidArgumentException('Mindestens 2 IDs nötig');

        $records = [];
        foreach ($firmaIds as $id) {
            $f = $this->db->queryOne(
                "SELECT * FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$id]
            );
            if (!$f) continue;
            $f['_subdata'] = [
                'adressen'      => $this->db->query("SELECT * FROM crm_adressen WHERE firma_id = ? ORDER BY typ", [$id]),
                'kontakte'      => $this->db->query(
                    "SELECT id, vorname, nachname, funktion, email_primaer FROM crm_kontakte
                     WHERE firma_id = ? AND geloescht_am IS NULL ORDER BY nachname", [$id]
                ),
                'kontakte_count' => (int)$this->db->queryValue("SELECT COUNT(*) FROM crm_kontakte WHERE firma_id = ? AND geloescht_am IS NULL", [$id]),
            ];
            $records[] = $f;
        }

        return [
            'typ' => 'firma',
            'records' => $records,
            'felder' => $this->firmaFelderFuerVergleich(),
            'master_vorschlag' => $this->vorschlagMaster($records, 'firma'),
            'subdaten_zusammenfassung' => $this->fasseSubDatenFirmaZusammen($records),
        ];
    }

    /**
     * Sub-Daten-Zusammenfassung für Firma-Merge.
     * Wichtig: Verknüpfte Kontakte werden NAMENTLICH gezeigt + Adressen
     * mit Konflikt-Erkennung. So sieht der User vor dem Merge/Löschen
     * was wirklich mit den abhängigen Daten passiert.
     */
    private function fasseSubDatenFirmaZusammen(array $records): array
    {
        // Kontakte aller Firmen sammeln (mit Quell-Firma)
        $kontakteListe = [];
        foreach ($records as $r) {
            foreach (($r['_subdata']['kontakte'] ?? []) as $k) {
                $kontakteListe[] = [
                    'kontakt_id' => (int) $k['id'],
                    'firma_id' => (int) $r['id'],
                    'name' => trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? '')),
                    'funktion' => $k['funktion'] ?? '',
                    'email' => $k['email_primaer'] ?? '',
                ];
            }
        }

        // Adress-Konflikt-Erkennung pro Typ
        $adressenProTyp = [];
        foreach ($records as $r) {
            foreach (($r['_subdata']['adressen'] ?? []) as $a) {
                $hash = md5(($a['strasse'] ?? '') . '|' . ($a['plz'] ?? '') . '|' . ($a['stadt'] ?? ''));
                $adressenProTyp[$a['typ']][$hash] = [
                    'firma_id' => (int) $r['id'],
                    'adresse' => $a,
                ];
            }
        }
        $konflikte = [];
        foreach ($adressenProTyp as $typ => $varianten) {
            if (count($varianten) > 1) {
                $konflikte[] = [
                    'typ' => 'adresse',
                    'label' => 'Adresse „' . ucfirst($typ) . '"',
                    'beschreibung' => count($varianten) . ' unterschiedliche Adressen vorhanden',
                    'varianten' => array_values($varianten),
                ];
            }
        }

        return [
            'kontakte' => $kontakteListe,
            'kontakte_count' => count($kontakteListe),
            'adressen_count' => array_sum(array_map('count', $adressenProTyp)),
            'konflikte' => $konflikte,
            'hat_konflikte' => !empty($konflikte),
            // Für UI: gleiche Struktur wie Kontakt-Subdaten (additiv-Block)
            'additiv' => [
                'kontakte' => count($kontakteListe),
                'adressen' => array_sum(array_map('count', $adressenProTyp)),
            ],
        ];
    }

    /** Heuristische Empfehlung: nimm den Record mit den meisten Daten */
    private function vorschlagMaster(array $records, string $typ): ?int
    {
        if (empty($records)) return null;
        $scores = [];
        foreach ($records as $r) {
            $score = 0;
            foreach ($r as $k => $v) {
                if ($k === '_subdata' || $k === 'id') continue;
                if (is_scalar($v) && !empty($v)) $score++;
            }
            // Sub-Daten zählen (jeder Eintrag bringt Punkte)
            if (isset($r['_subdata'])) {
                $sd = $r['_subdata'];
                $score += count($sd['adressen'] ?? []) * 2;
                $score += count($sd['tags'] ?? []);
                $score += count($sd['listen'] ?? []);
                $score += count($sd['social'] ?? []);
                $score += ($sd['aktivitaeten_count'] ?? 0) * 0.5;
                $score += ($sd['mails_count'] ?? 0) * 0.1;
                if ($typ === 'firma') $score += ($sd['kontakte_count'] ?? 0) * 3;
            }
            $scores[(int)$r['id']] = $score;
        }
        arsort($scores);
        return (int)array_key_first($scores);
    }

    public function kontaktFelderFuerVergleichPublic(): array { return $this->kontaktFelderFuerVergleich(); }
    public function firmaFelderFuerVergleichPublic(): array { return $this->firmaFelderFuerVergleich(); }

    private function kontaktFelderFuerVergleich(): array
    {
        return [
            ['key' => 'anrede',                    'label' => 'Anrede'],
            ['key' => 'titel',                     'label' => 'Titel'],
            ['key' => 'vorname',                   'label' => 'Vorname'],
            ['key' => 'nachname',                  'label' => 'Nachname'],
            ['key' => 'funktion',                  'label' => 'Funktion'],
            ['key' => 'abteilung',                 'label' => 'Abteilung'],
            ['key' => 'geburtsdatum',              'label' => 'Geburtsdatum'],
            ['key' => 'email_primaer',             'label' => 'E-Mail primär'],
            ['key' => 'email_zweit',               'label' => 'E-Mail Zweit'],
            ['key' => 'telefon',                   'label' => 'Telefon'],
            ['key' => 'mobil',                     'label' => 'Mobil'],
            ['key' => 'website',                   'label' => 'Website'],
            ['key' => 'linkedin',                  'label' => 'LinkedIn', 'virtual' => true],
            ['key' => 'xing',                      'label' => 'XING', 'virtual' => true],
            ['key' => 'firma_id',                  'label' => 'Firma', 'transform' => 'firma'],
            ['key' => 'kontakt_status',            'label' => 'Status'],
            ['key' => 'opt_in_status',             'label' => 'Opt-In'],
            ['key' => 'thx_score',                 'label' => 'THX-Score'],
            ['key' => 'lead_quelle',               'label' => 'Lead-Quelle'],
            ['key' => 'bevorzugtes_thema',         'label' => 'Bevorzugtes Thema'],
            ['key' => 'interessen',                'label' => 'Interessen', 'long' => true],
            ['key' => 'merkmale',                  'label' => 'Merkmale', 'long' => true],
            ['key' => 'beschreibung',              'label' => 'Beschreibung', 'long' => true],
            ['key' => 'deal_wert',                 'label' => 'Deal-Wert'],
            ['key' => 'deal_stufe',                'label' => 'Deal-Stufe'],
            ['key' => 'foto_path',                 'label' => 'Foto'],
            ['key' => 'kontakt_besitzer_user_id',  'label' => 'Besitzer'],
            ['key' => 'brevo_id',                  'label' => 'Brevo-ID'],
            ['key' => 'legacy_zoho_id',            'label' => 'Zoho-ID'],
            ['key' => 'erstellt_am',               'label' => 'Angelegt'],
        ];
    }

    private function firmaFelderFuerVergleich(): array
    {
        return [
            ['key' => 'firmenname',     'label' => 'Firmenname'],
            ['key' => 'website',        'label' => 'Website'],
            ['key' => 'branche',        'label' => 'Branche'],
            ['key' => 'firmen_typ',     'label' => 'Firmen-Typ'],
            ['key' => 'parent_firma_id','label' => 'Mutter-Firma', 'transform' => 'firma'],
            ['key' => 'bewertung',      'label' => 'Bewertung'],
            ['key' => 'beschaeftigte',  'label' => 'Beschäftigte'],
            ['key' => 'jahreseinnahmen','label' => 'Jahreseinnahmen'],
            ['key' => 'telefon',        'label' => 'Telefon'],
            ['key' => 'fax',            'label' => 'Fax'],
            ['key' => 'email',          'label' => 'E-Mail'],
            ['key' => 'logo_path',      'label' => 'Logo'],
            ['key' => 'notizen',        'label' => 'Notizen', 'long' => true],
            ['key' => 'legacy_zoho_id', 'label' => 'Zoho-ID'],
            ['key' => 'erstellt_am',    'label' => 'Angelegt'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MERGE-AUSFÜHRUNG
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Führt einen Kontakt-Merge durch.
     *
     * @param int   $masterId   Master-Kontakt-ID (bleibt erhalten)
     * @param int[] $loserIds   IDs der Kontakte, die in Master verschmolzen + danach soft-gelöscht werden
     * @param array $fieldValues  ['feldkey' => 'neuer Wert', ...] — wenn nicht gesetzt: master-Wert bleibt
     * @param int|null $actorUserId
     * @return int Master-ID
     */
    public function mergeKontakte(int $masterId, array $loserIds, array $fieldValues, ?int $actorUserId = null): int
    {
        $loserIds = array_values(array_filter(array_unique(array_map('intval', $loserIds)), fn($id) => $id !== $masterId));
        if (empty($loserIds)) throw new \InvalidArgumentException('Keine Loser-IDs');

        $master = $this->db->queryOne("SELECT * FROM crm_kontakte WHERE id = ? AND geloescht_am IS NULL", [$masterId]);
        if (!$master) throw new \RuntimeException('Master-Kontakt nicht gefunden');

        $this->db->beginTransaction();
        try {
            // (0) Loser-E-Mails preemptiv „verbrennen": crm_kontakte hat einen
            // UNIQUE-Index auf email_primaer, der auch geloeschte Datensätze umfasst.
            // Ohne diesen Schritt schlägt jedes Master-UPDATE fehl, das eine
            // E-Mail übernimmt, die noch bei einem Loser steht (1062 Duplicate).
            // Da die Loser im selben Transaktions-Schritt soft-deleted werden,
            // ist das verlustfrei — bei Wiederherstellung muss der Prefix
            // entfernt werden.
            foreach ($loserIds as $loserId) {
                $this->db->execute(
                    "UPDATE crm_kontakte
                        SET email_primaer = CONCAT('__del_', id, '__', email_primaer),
                            email_zweit   = CASE WHEN email_zweit IS NOT NULL AND email_zweit != ''
                                                 THEN CONCAT('__del_', id, '__', email_zweit)
                                                 ELSE email_zweit END
                      WHERE id = ? AND email_primaer NOT LIKE '\\_\\_del\\_%'",
                    [$loserId]
                );
            }

            // (1) Felder am Master aktualisieren (nur die explizit gesetzten)
            $erlaubteFelder = array_column($this->kontaktFelderFuerVergleich(), 'key');
            $updates = [];
            foreach ($fieldValues as $key => $val) {
                if (!in_array($key, $erlaubteFelder, true)) continue;
                if ((string)($master[$key] ?? '') === (string)$val) continue;
                $updates[$key] = ($val === '' || $val === null) ? null : $val;
            }
            if ($updates) {
                $updates['geaendert_am'] = date('Y-m-d H:i:s');
                $updates['geaendert_durch'] = $actorUserId;
                $this->db->update('crm_kontakte', $updates, 'id = ?', [$masterId]);
            }

            // (2) Sub-Daten der Loser ans Master umhängen / mergen
            foreach ($loserIds as $loserId) {
                $this->mergeKontaktSubData($masterId, $loserId);
            }

            // (3) Loser soft-deleten
            foreach ($loserIds as $loserId) {
                $this->db->update('crm_kontakte', [
                    'geloescht_am' => date('Y-m-d H:i:s'),
                    'geloescht_durch' => $actorUserId,
                ], 'id = ?', [$loserId]);
                $this->db->insert('crm_loesch_events', [
                    'entity_typ' => 'kontakt',
                    'entity_id' => $loserId,
                    'geloescht_durch' => $actorUserId,
                    'art' => 'soft',
                    'grund' => 'merged_into_' . $masterId,
                ]);
                AuditLog::record('crm_kontakt', (string)$loserId, 'merged_into', ['master_id' => $masterId]);
            }
            AuditLog::record('crm_kontakt', (string)$masterId, 'merged_from', ['loser_ids' => $loserIds, 'felder' => array_keys($updates ?? [])]);

            // (4) Knowledge-Sync für Master + Loser (Loser-Docs werden deaktiviert)
            CrmKnowledgeSyncQueue::enqueueKontakt($masterId);
            foreach ($loserIds as $loserId) CrmKnowledgeSyncQueue::enqueueKontakt($loserId);

            $this->db->commit();
            return $masterId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function mergeFirmen(int $masterId, array $loserIds, array $fieldValues, ?int $actorUserId = null): int
    {
        $loserIds = array_values(array_filter(array_unique(array_map('intval', $loserIds)), fn($id) => $id !== $masterId));
        if (empty($loserIds)) throw new \InvalidArgumentException('Keine Loser-IDs');

        $master = $this->db->queryOne("SELECT * FROM crm_firmen WHERE id = ? AND geloescht_am IS NULL", [$masterId]);
        if (!$master) throw new \RuntimeException('Master-Firma nicht gefunden');

        $this->db->beginTransaction();
        try {
            // (1) Felder am Master aktualisieren
            $erlaubteFelder = array_column($this->firmaFelderFuerVergleich(), 'key');
            $updates = [];
            foreach ($fieldValues as $key => $val) {
                if (!in_array($key, $erlaubteFelder, true)) continue;
                if ((string)($master[$key] ?? '') === (string)$val) continue;
                $updates[$key] = ($val === '' || $val === null) ? null : $val;
            }
            if ($updates) {
                $updates['geaendert_am'] = date('Y-m-d H:i:s');
                $updates['geaendert_durch'] = $actorUserId;
                $this->db->update('crm_firmen', $updates, 'id = ?', [$masterId]);
            }

            // (2) Sub-Daten umhängen: Kontakte + Adressen
            foreach ($loserIds as $loserId) {
                // Alle Kontakte der Loser-Firma → Master-Firma
                $this->db->execute(
                    "UPDATE crm_kontakte SET firma_id = ? WHERE firma_id = ? AND geloescht_am IS NULL",
                    [$masterId, $loserId]
                );
                // Adressen umhängen (wenn Master für den typ noch keine hat)
                $loserAddrs = $this->db->query("SELECT * FROM crm_adressen WHERE firma_id = ?", [$loserId]);
                foreach ($loserAddrs as $a) {
                    $masterHasTyp = $this->db->queryValue(
                        "SELECT id FROM crm_adressen WHERE firma_id = ? AND typ = ?",
                        [$masterId, $a['typ']]
                    );
                    if (!$masterHasTyp) {
                        $this->db->execute(
                            "UPDATE crm_adressen SET firma_id = ? WHERE id = ?",
                            [$masterId, $a['id']]
                        );
                    } else {
                        // Master hat schon einen Eintrag dieses Typs → Loser-Adresse löschen
                        $this->db->delete('crm_adressen', 'id = ?', [$a['id']]);
                    }
                }
                // customers.crm_firma_id umhängen falls die Loser-Firma mit einem Kunden verknüpft war
                $this->db->execute(
                    "UPDATE customers SET crm_firma_id = ? WHERE crm_firma_id = ?",
                    [$masterId, $loserId]
                );
                // parent_firma_id-Referenzen umhängen (wenn die Loser-Firma als Mutter genutzt wurde)
                $this->db->execute(
                    "UPDATE crm_firmen SET parent_firma_id = ? WHERE parent_firma_id = ?",
                    [$masterId, $loserId]
                );
            }

            // (3) Loser-Firmen soft-deleten
            foreach ($loserIds as $loserId) {
                $this->db->update('crm_firmen', [
                    'geloescht_am' => date('Y-m-d H:i:s'),
                    'geloescht_durch' => $actorUserId,
                ], 'id = ?', [$loserId]);
                $this->db->insert('crm_loesch_events', [
                    'entity_typ' => 'firma',
                    'entity_id' => $loserId,
                    'geloescht_durch' => $actorUserId,
                    'art' => 'soft',
                    'grund' => 'merged_into_' . $masterId,
                ]);
                AuditLog::record('crm_firma', (string)$loserId, 'merged_into', ['master_id' => $masterId]);
            }
            AuditLog::record('crm_firma', (string)$masterId, 'merged_from', ['loser_ids' => $loserIds]);

            // (4) Knowledge-Sync
            CrmKnowledgeSyncQueue::enqueueFirma($masterId);
            foreach ($loserIds as $loserId) CrmKnowledgeSyncQueue::enqueueFirma($loserId);

            $this->db->commit();
            return $masterId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Verschiebt alle Sub-Daten eines Loser-Kontakts an den Master-Kontakt.
     * Bei Konflikten (gleicher Typ/Plattform) wird dedupliziert.
     */
    private function mergeKontaktSubData(int $masterId, int $loserId): void
    {
        // Aktivitäten — alle umhängen (kein Konflikt möglich, IDs sind unique)
        $this->db->execute("UPDATE crm_aktivitaeten SET kontakt_id = ? WHERE kontakt_id = ?", [$masterId, $loserId]);

        // Brevo-Events — alle umhängen
        $this->db->execute("UPDATE crm_brevo_events SET kontakt_id = ? WHERE kontakt_id = ?", [$masterId, $loserId]);

        // Opt-In-Events — alle umhängen
        $this->db->execute("UPDATE crm_opt_in_events SET kontakt_id = ? WHERE kontakt_id = ?", [$masterId, $loserId]);

        // Lead-Magnet-Events — alle umhängen
        $this->db->execute("UPDATE crm_lead_magnet_events SET kontakt_id = ? WHERE kontakt_id = ?", [$masterId, $loserId]);

        // Adressen — typ-basiert: wenn Master schon Typ X hat, Loser-Adresse löschen, sonst umhängen
        $loserAddrs = $this->db->query("SELECT * FROM crm_adressen WHERE kontakt_id = ?", [$loserId]);
        foreach ($loserAddrs as $a) {
            $masterHasTyp = $this->db->queryValue(
                "SELECT id FROM crm_adressen WHERE kontakt_id = ? AND typ = ?",
                [$masterId, $a['typ']]
            );
            if ($masterHasTyp) {
                $this->db->delete('crm_adressen', 'id = ?', [$a['id']]);
            } else {
                $this->db->execute("UPDATE crm_adressen SET kontakt_id = ? WHERE id = ?", [$masterId, $a['id']]);
            }
        }

        // Tags — UNION, dedupliziert
        $loserTags = $this->db->query("SELECT tag_id FROM crm_kontakt_tags WHERE kontakt_id = ?", [$loserId]);
        foreach ($loserTags as $t) {
            $existsAtMaster = $this->db->queryValue(
                "SELECT 1 FROM crm_kontakt_tags WHERE kontakt_id = ? AND tag_id = ?",
                [$masterId, $t['tag_id']]
            );
            if ($existsAtMaster) {
                $this->db->execute("DELETE FROM crm_kontakt_tags WHERE kontakt_id = ? AND tag_id = ?", [$loserId, $t['tag_id']]);
            } else {
                $this->db->execute("UPDATE crm_kontakt_tags SET kontakt_id = ? WHERE kontakt_id = ? AND tag_id = ?", [$masterId, $loserId, $t['tag_id']]);
            }
        }

        // Listen — UNION nach listen_id, bevorzuge "aktiv"
        $loserListen = $this->db->query("SELECT listen_id, status FROM crm_kontakt_listen WHERE kontakt_id = ?", [$loserId]);
        foreach ($loserListen as $l) {
            $existing = $this->db->queryOne(
                "SELECT status FROM crm_kontakt_listen WHERE kontakt_id = ? AND listen_id = ?",
                [$masterId, $l['listen_id']]
            );
            if ($existing) {
                if ($existing['status'] !== 'aktiv' && $l['status'] === 'aktiv') {
                    $this->db->execute(
                        "UPDATE crm_kontakt_listen SET status = 'aktiv' WHERE kontakt_id = ? AND listen_id = ?",
                        [$masterId, $l['listen_id']]
                    );
                }
                $this->db->execute("DELETE FROM crm_kontakt_listen WHERE kontakt_id = ? AND listen_id = ?", [$loserId, $l['listen_id']]);
            } else {
                $this->db->execute("UPDATE crm_kontakt_listen SET kontakt_id = ? WHERE kontakt_id = ? AND listen_id = ?", [$masterId, $loserId, $l['listen_id']]);
            }
        }

        // Social-Links — UNION nach plattform; bei Konflikt nur eine behalten
        $loserSocial = $this->db->query("SELECT id, plattform, url FROM crm_social_links WHERE kontakt_id = ?", [$loserId]);
        foreach ($loserSocial as $s) {
            $existing = $this->db->queryValue(
                "SELECT id FROM crm_social_links WHERE kontakt_id = ? AND plattform = ?",
                [$masterId, $s['plattform']]
            );
            if ($existing) {
                $this->db->delete('crm_social_links', 'id = ?', [$s['id']]);
            } else {
                $this->db->execute("UPDATE crm_social_links SET kontakt_id = ? WHERE id = ?", [$masterId, $s['id']]);
            }
        }

        // Kunden-Zuordnung — UNION (PK ist (kontakt_id, customer_id))
        $loserZuord = $this->db->query("SELECT customer_id, rolle FROM crm_kunden_zuordnung WHERE kontakt_id = ?", [$loserId]);
        foreach ($loserZuord as $z) {
            $existsAtMaster = $this->db->queryValue(
                "SELECT 1 FROM crm_kunden_zuordnung WHERE kontakt_id = ? AND customer_id = ?",
                [$masterId, $z['customer_id']]
            );
            if ($existsAtMaster) {
                $this->db->execute("DELETE FROM crm_kunden_zuordnung WHERE kontakt_id = ? AND customer_id = ?", [$loserId, $z['customer_id']]);
            } else {
                $this->db->execute("UPDATE crm_kunden_zuordnung SET kontakt_id = ? WHERE kontakt_id = ? AND customer_id = ?", [$masterId, $loserId, $z['customer_id']]);
            }
        }
    }
}
