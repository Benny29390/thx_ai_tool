<?php
namespace Services;

use Core\Database;

/**
 * IMAP-Pull-Pipeline:
 * - Pollt das Postfach eines Konts via webklex/php-imap
 * - Parst MIME → strukturierte Mail-Daten
 * - Schreibt in mail_nachrichten + mail_anhaenge
 * - Dubletten-Schutz via Message-ID
 * - Verschiebt verarbeitete Mails in Verarbeitet- bzw. Fehler-Ordner
 *
 * Wird sowohl vom Cron als auch vom Manuell-Pull-Button getriggert.
 * EML-Upload nutzt dieselbe verarbeiteEml()-Methode, andere Eintrittsstelle.
 */
class MailImapService
{
    private Database $db;
    private MailKontoService $konten;

    public function __construct(Database $db)
    {
        $this->db = $db;
        require_once SERVICES_PATH . '/MailKontoService.php';
        $this->konten = new MailKontoService($db);
    }

    /**
     * Pull für alle aktiven Konten. Beachtet pro Konto das Pull-Intervall.
     * Trigger: 'cron'|'manuell'
     */
    public function pullAlle(string $trigger = 'cron', ?int $nurKontoId = null): array
    {
        $intervall = max(1, (int)\Core\Settings::get('mail_pull_intervall_minuten', 10));
        // OAuth2-Konten haben kein imap_password_enc — sie sind ueber den Refresh-Token
        // verbunden. Ohne diese Bedingung waere Thomas' Exchange-Konto vom Cron ausgeschlossen.
        $where = [
            'aktiv = 1',
            'imap_host IS NOT NULL',
            "((auth_typ = 'passwort' AND imap_password_enc IS NOT NULL)
              OR (auth_typ = 'oauth2' AND oauth_refresh_token_enc IS NOT NULL))",
        ];
        $params = [];
        if ($nurKontoId !== null) {
            $where[] = 'id = ?';
            $params[] = $nurKontoId;
        }
        $konten = $this->db->query(
            "SELECT id, name, email_adresse FROM mail_konten WHERE " . implode(' AND ', $where),
            $params
        );

        $gesamt = ['konten_geprueft' => 0, 'erfolg' => 0, 'dublette' => 0, 'fehler' => 0, 'uebersprungen' => 0, 'details' => []];
        foreach ($konten as $k) {
            // Pro-Konto-Intervall prüfen (manuell überschreibt)
            if ($trigger === 'cron' && $nurKontoId === null) {
                $letzterErfolg = $this->db->queryValue(
                    "SELECT MAX(gestartet_am) FROM mail_pull_logs WHERE konto_id = ? AND verbindungs_fehler IS NULL",
                    [$k['id']]
                );
                if ($letzterErfolg) {
                    $ts = strtotime($letzterErfolg);
                    if ($ts && (time() - $ts) < $intervall * 60) {
                        $gesamt['details'][] = 'Konto „' . $k['name'] . '": noch nicht dran (letzter Lauf vor ' . ceil((time() - $ts) / 60) . ' Min)';
                        continue;
                    }
                }
            }
            $gesamt['konten_geprueft']++;
            $r = $this->pullKonto((int)$k['id'], $trigger);
            $gesamt['erfolg'] += $r['erfolg'];
            $gesamt['dublette'] += $r['dublette'];
            $gesamt['fehler'] += $r['fehler'];
            $gesamt['uebersprungen'] += $r['uebersprungen'];
            $gesamt['details'][] = 'Konto „' . $k['name'] . '": ' . $r['kurz'];
        }
        return $gesamt;
    }

    public function pullKonto(int $kontoId, string $trigger = 'manuell'): array
    {
        $gestartet = microtime(true);
        $log = ['erfolg' => 0, 'dublette' => 0, 'fehler' => 0, 'uebersprungen' => 0, 'kurz' => '', 'verbindungs_fehler' => null, 'eintraege' => []];

        try {
            $cfg = $this->konten->getZugangsdaten($kontoId);
        } catch (\Throwable $e) {
            $log['verbindungs_fehler'] = $e->getMessage();
            $this->schreibeLog($kontoId, $gestartet, $trigger, $log);
            return $log;
        }

        if (!class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
            $log['verbindungs_fehler'] = 'webklex/php-imap fehlt.';
            $this->schreibeLog($kontoId, $gestartet, $trigger, $log);
            return $log;
        }

        $nurLesen = !$this->darfSchreiben($cfg);   // harte Sperre: Exchange = immer nur lesen

        try {
            $manager = new \Webklex\PHPIMAP\ClientManager();
            $client = $manager->make($this->konten->imapVerbindung($cfg));
            $client->connect();
        } catch (\Throwable $e) {
            $log['verbindungs_fehler'] = $e->getMessage();
            $this->schreibeLog($kontoId, $gestartet, $trigger, $log);
            return $log;
        }

        try {
            // Ordnerliste bei JEDEM Abruf abgleichen. Das Auflisten von 2000 Ordnern dauert
            // ~2 Sekunden (das Zaehlen der Mails dagegen Minuten — das macht der naechtliche
            // Cron). So werden neue, umbenannte und geloeschte Ordner sofort erkannt,
            // ohne dass Du irgendwo "neu einlesen" druecken musst.
            $this->synchronisiereOrdnerListe($client, $kontoId);

            // Verarbeitet-/Fehler-Ordner nur anlegen, wenn wir auch verschieben duerfen.
            if (!$nurLesen) {
                $this->sicherstelleOrdner($client, $cfg['imap_folder_verarbeitet']);
                $this->sicherstelleOrdner($client, $cfg['imap_folder_fehler']);
            }

            $ordnerListe = $this->zuHolendeOrdner($client, $kontoId, (string) $cfg['imap_folder_inbox']);
            if (empty($ordnerListe)) {
                // Kein Fehler, sondern der ausdrueckliche Wunsch des Nutzers.
                $log['kurz'] = 'Kein Ordner ausgewählt — nichts abgeholt.';
                $this->schreibeLog($kontoId, $gestartet, $trigger, $log);
                $client->disconnect();
                return $log;
            }

            foreach ($ordnerListe as $pfad => $letzterPull) {
                $folder = $client->getFolderByPath($pfad);
                if (!$folder) {
                    $log['eintraege'][] = ['betreff' => $pfad, 'status' => 'Ordner nicht gefunden'];
                    continue;
                }

                $query = $folder->messages();
                // Im Nur-Lesen-Modus darf das Abrufen die Mail NICHT als gelesen markieren.
                // Ohne leaveUnread() setzt der Server beim Body-Abruf das \Seen-Flag — Thomas
                // saehe in Outlook lauter "gelesene" Mails, die er nie geoeffnet hat.
                if ($nurLesen) {
                    $query = $query->leaveUnread();
                }
                // Ohne Verschieben brauchen wir eine Eingrenzung, sonst laufen wir jedes Mal
                // durchs ganze Postfach. Seit dem letzten Lauf (mit Puffer) reicht.
                if ($nurLesen && $letzterPull) {
                    $query = $query->since(date('d M Y', strtotime($letzterPull . ' -2 days')));
                } else {
                    $query = $query->all();
                }

                $messages = $query->setFetchOrder('asc')->limit(200)->get();

                foreach ($messages as $message) {
                    // Betreff kommt MIME-kodiert (=?utf-8?B?…?=) — fuers Protokoll dekodieren,
                    // sonst steht dort Zeichensalat statt der Betreffzeile.
                    $betreff = $this->dekodiereHeader((string) $message->getSubject());
                    try {
                        $rohEml = $this->bauRawEml($message);
                        $ergebnis = $this->verarbeiteEml($rohEml, $kontoId, 'imap');

                        if (!empty($ergebnis['mail_id'])) {
                            $this->db->execute(
                                "UPDATE mail_nachrichten SET imap_ordner = ?, imap_uid = ? WHERE id = ?",
                                [$pfad, (int) $message->getUid(), $ergebnis['mail_id']]
                            );
                        }

                        if ($ergebnis['status'] === 'erfolg') {
                            $log['erfolg']++;
                            $log['eintraege'][] = ['betreff' => $betreff, 'status' => 'übernommen (' . $pfad . ')'];
                            if (!$nurLesen) $this->verschiebe($message, $cfg['imap_folder_verarbeitet']);
                        } elseif ($ergebnis['status'] === 'dublette') {
                            $log['dublette']++;
                            if (!$nurLesen) $this->verschiebe($message, $cfg['imap_folder_verarbeitet']);
                        } else {
                            $log['fehler']++;
                            $log['eintraege'][] = ['betreff' => $betreff, 'status' => 'fehler: ' . ($ergebnis['fehler'] ?? '?')];
                            if (!$nurLesen) $this->verschiebe($message, $cfg['imap_folder_fehler']);
                        }
                    } catch (\Throwable $e) {
                        $log['fehler']++;
                        $log['eintraege'][] = ['betreff' => $betreff, 'status' => 'Exception: ' . $e->getMessage()];
                        if (!$nurLesen) $this->verschiebe($message, $cfg['imap_folder_fehler']);
                    }
                }

                $this->abgleichGeloeschte($client, $kontoId, $pfad, $log);

                $this->db->execute(
                    "INSERT INTO mail_ordner_pull (konto_id, ordner_pfad, letzter_pull)
                     VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE letzter_pull = NOW()",
                    [$kontoId, $pfad]
                );
            }
        } finally {
            try { $client->disconnect(); } catch (\Throwable $e) {}
        }

        $log['kurz'] = sprintf('%d übernommen / %d Dubletten / %d Fehler', $log['erfolg'], $log['dublette'], $log['fehler']);
        $this->schreibeLog($kontoId, $gestartet, $trigger, $log);
        return $log;
    }

    /**
     * Loesch-Abgleich je Ordner: Was bei uns in diesem Ordner liegt, auf dem Server aber
     * nicht mehr existiert, wurde geloescht oder wegbewegt.
     *
     * Wichtig — Verschieben vs. Loeschen: Zieht Thomas eine Mail in einen ANDEREN abgeholten
     * Ordner, findet der Abruf sie dort per Message-ID wieder, aktualisiert `imap_ordner`
     * und der Eintrag hier greift nicht mehr (die UID passt dann nicht mehr zu diesem Ordner,
     * aber der Ordner-Eintrag stimmt). Nur was wirklich verschwindet, wird als geloescht
     * markiert — und zwar weich (`geloescht_am`), nie hart. Ein Fehlalarm kostet dann keine Daten.
     */
    private function abgleichGeloeschte($client, int $kontoId, string $pfad, array &$log): void
    {
        try {
            $unsere = $this->db->query(
                "SELECT id, imap_uid FROM mail_nachrichten
                 WHERE konto_id = ? AND imap_ordner = ? AND imap_uid IS NOT NULL AND geloescht_am IS NULL",
                [$kontoId, $pfad]
            );
            if (empty($unsere)) return;

            // UID-Liste direkt vom Server — nur Nummern, keine Inhalte.
            $folder = $client->getFolderByPath($pfad);
            if (!$folder) return;
            $folder->select();
            $serverUids = $client->getConnection()
                ->search(['ALL'], \Webklex\PHPIMAP\IMAP::ST_UID)
                ->validatedData();
            if (!is_array($serverUids)) return;
            $vorhanden = array_flip(array_map('intval', $serverUids));

            foreach ($unsere as $m) {
                if (isset($vorhanden[(int) $m['imap_uid']])) continue;
                $this->db->execute(
                    "UPDATE mail_nachrichten SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL",
                    [$m['id']]
                );
                $log['eintraege'][] = ['betreff' => '(im Postfach entfernt)', 'status' => 'gelöscht markiert (' . $pfad . ')'];
            }
        } catch (\Throwable $e) {
            // Nicht fatal: lieber keinen Abgleich als einen Abruf, der daran scheitert.
            error_log('Mail-Loeschabgleich (' . $pfad . '): ' . $e->getMessage());
        }
    }

    /**
     * Ordnerliste mit dem Server abgleichen (Struktur, nicht Mailzahlen).
     *
     * Warum bei jedem Abruf: Das reine Auflisten von 2000 Ordnern kostet ~2 Sekunden.
     * Teuer ist nur das Zaehlen der Mails je Ordner (Minuten) — das macht der naechtliche
     * Cron. So sind neue und umbenannte Ordner sofort da, ohne Zutun des Nutzers.
     */
    private function synchronisiereOrdnerListe($client, int $kontoId): void
    {
        try {
            $trenner = '/';
            $server = [];
            foreach ($client->getFolders(false) as $f) {
                if (!empty($f->delimiter)) $trenner = (string) $f->delimiter;
                $server[(string) $f->path] = (string) ($f->full_name ?: $f->path);
            }
            if (empty($server)) return;

            $this->db->execute("UPDATE mail_konten SET ordner_trenner = ? WHERE id = ?", [$trenner, $kontoId]);

            $bekannt = [];
            foreach ($this->db->query("SELECT pfad FROM mail_ordner_cache WHERE konto_id = ?", [$kontoId]) as $r) {
                $bekannt[$r['pfad']] = true;
            }

            // Neue Ordner aufnehmen. Mailzahl bleibt 0 bis zum naechsten Zaehl-Lauf —
            // sie ist nur Anzeige-Komfort, fuer die Auswahl reicht die Struktur.
            foreach ($server as $pfad => $lesbar) {
                if (isset($bekannt[$pfad])) continue;
                $teile = explode($trenner, $lesbar);
                $rohTeile = explode($trenner, $pfad);
                $eltern = count($rohTeile) > 1 ? implode($trenner, array_slice($rohTeile, 0, -1)) : null;
                $this->db->execute(
                    "INSERT IGNORE INTO mail_ordner_cache
                       (konto_id, pfad, name_lesbar, name_kurz, eltern_pfad, tiefe, anzahl_mails, ist_system)
                     VALUES (?, ?, ?, ?, ?, ?, 0, 0)",
                    [$kontoId, $pfad, $lesbar, (string) end($teile), $eltern, max(0, count($teile) - 1)]
                );
            }

            // Verschwundene Ordner (geloescht/umbenannt) aus dem Katalog werfen.
            foreach (array_keys($bekannt) as $pfad) {
                if (isset($server[$pfad])) continue;
                $this->db->execute("DELETE FROM mail_ordner_cache WHERE konto_id = ? AND pfad = ?", [$kontoId, $pfad]);
                $this->db->execute("DELETE FROM mail_ordner_pull   WHERE konto_id = ? AND ordner_pfad = ?", [$kontoId, $pfad]);
                // Die AUSWAHL bleibt bewusst stehen: Ein umbenannter Ordner soll nicht
                // stillschweigend die Konfiguration des Nutzers loeschen.
            }
        } catch (\Throwable $e) {
            error_log('Mail-Ordnerliste-Sync: ' . $e->getMessage());   // nicht fatal
        }
    }

    /**
     * Welche Ordner werden abgeholt? Loest die gespeicherte Auswahl auf, inkl. "inkl.
     * Unterordner". Ist nichts ausgewaehlt, bleibt es beim bisherigen Verhalten (nur INBOX)
     * — Bestandskonten wie pr@thoxan.com aendern sich dadurch nicht.
     *
     * @return array<string,?string> ordner_pfad => letzter_pull (oder null)
     */
    private function zuHolendeOrdner($client, int $kontoId, string $fallbackInbox): array
    {
        $auswahl = $this->db->query(
            "SELECT ordner_pfad, rekursiv FROM mail_konten_ordner
             WHERE konto_id = ? AND abholen = 1",
            [$kontoId]
        );

        if (empty($auswahl)) {
            // FALLE, die zugeschlagen hat: Frueher hiess "kein Ordner ausgewaehlt"
            // automatisch "dann eben INBOX". Wer seine Auswahl bewusst leert, um NICHTS
            // mehr zu holen, bekam so den kompletten Posteingang. Genau das darf nicht sein.
            //
            // Der INBOX-Rueckfall gilt jetzt nur noch fuer Konten, die nie eine Ordner-
            // Auswahl hatten (Altbestand wie pr@thoxan.com — dort ist er das erwartete
            // Verhalten). Wer einmal konfiguriert hat, bekommt bei leerer Auswahl: nichts.
            $konfiguriert = (int) $this->db->queryValue(
                "SELECT ordner_konfiguriert FROM mail_konten WHERE id = ?", [$kontoId]
            );
            return $konfiguriert ? [] : [$fallbackInbox => null];
        }

        // Trennzeichen kommt vom Server (im Katalog hinterlegt), es wird nicht geraten.
        $trenner = (string) ($this->db->queryValue(
            "SELECT ordner_trenner FROM mail_konten WHERE id = ?", [$kontoId]
        ) ?: '/');

        // Ordnerliste aus dem Katalog — spart das erneute Abfragen von 2000 Ordnern.
        // Kategorien/Kontaktlisten (ist_mailordner=0) sind hier ausgeschlossen: Aus ihnen
        // laesst sich keine Mail holen, ein Abrufversuch wuerde nur Fehler produzieren.
        $alle = $this->db->query(
            "SELECT pfad FROM mail_ordner_cache WHERE konto_id = ? AND ist_mailordner = 1", [$kontoId]
        );
        if (empty($alle)) {
            foreach ($client->getFolders(false) as $f) $alle[] = ['pfad' => (string) $f->path];
        }

        $pfade = [];
        foreach ($auswahl as $a) {
            $basis = (string) $a['ordner_pfad'];
            $pfade[$basis] = true;
            if (empty($a['rekursiv'])) continue;
            foreach ($alle as $k) {
                $kandidat = (string) $k['pfad'];
                if ($kandidat === $basis || strpos($kandidat, $basis) !== 0) continue;
                if (substr($kandidat, strlen($basis), 1) === $trenner) {
                    $pfade[$kandidat] = true;
                }
            }
        }

        // Abhol-Stand je Ordner aus der EIGENEN Zustandstabelle lesen. Frueher habe ich
        // dafuer Zeilen in mail_konten_ordner angelegt — dadurch sah es aus, als haette
        // der Nutzer 248 Ordner ausgewaehlt, obwohl er einen einzigen angehakt hatte.
        $stand = [];
        foreach ($this->db->query(
            "SELECT ordner_pfad, letzter_pull FROM mail_ordner_pull WHERE konto_id = ?", [$kontoId]
        ) as $s) {
            $stand[$s['ordner_pfad']] = $s['letzter_pull'];
        }

        $ergebnis = [];
        foreach (array_keys($pfade) as $p) {
            $ergebnis[$p] = $stand[$p] ?? null;
        }
        return $ergebnis;
    }

    /**
     * Verarbeitet eine rohe RFC-2822-Mail (von IMAP oder EML-Upload).
     * Schreibt in mail_nachrichten + mail_anhaenge + ggf. .eml-Datei in Storage.
     * Returnt: ['status' => 'erfolg'|'dublette'|'fehler', 'mail_id' => int, 'fehler' => string|null]
     */
    public function verarbeiteEml(string $rohEml, int $kontoId, string $quelle): array
    {
        try {
            $parser = \Symfony\Component\Mime\MimeMessageParser\Parser::class ?? null;
            // Symfony Mime hat keinen direkten "Parser" — wir parsen pragmatisch via Webklex's Message-Klassen.
            // Fallback: einfacher Header+Body-Splitter
            $parsed = $this->parseRohEml($rohEml);
        } catch (\Throwable $e) {
            return ['status' => 'fehler', 'fehler' => 'Parse-Fehler: ' . $e->getMessage(), 'mail_id' => null];
        }

        $messageId = $parsed['message_id'] ?: ('eml-' . sha1($rohEml));

        // Dubletten-Check
        $bestehend = $this->db->queryValue(
            "SELECT id FROM mail_nachrichten WHERE konto_id = ? AND message_id = ? AND richtung = 'eingang' AND geloescht_am IS NULL",
            [$kontoId, $messageId]
        );
        if ($bestehend) {
            return ['status' => 'dublette', 'mail_id' => (int)$bestehend, 'fehler' => null];
        }

        // EML-Datei in Storage ablegen
        $rohPfad = null;
        try {
            $unterordner = '/var/www/storage/mail/eml/' . date('Y-m');
            if (!is_dir($unterordner)) { @mkdir($unterordner, 0775, true); }
            $dateiName = date('Y-m-d-His') . '-' . sha1($messageId) . '.eml';
            $rohPfad = $unterordner . '/' . $dateiName;
            file_put_contents($rohPfad, $rohEml);
            @chmod($rohPfad, 0664);
        } catch (\Throwable $e) {
            $rohPfad = null;
        }

        // Konto-E-Mail für empfaenger_email
        $kontoEmail = $this->db->queryValue("SELECT email_adresse FROM mail_konten WHERE id = ?", [$kontoId]);

        $this->db->execute(
            "INSERT INTO mail_nachrichten
                (konto_id, richtung, message_id, in_reply_to, absender_email, absender_name,
                 empfaenger_email, cc_emails, betreff, body_plain, body_html, empfangen_am,
                 roh_eml_pfad, quelle, anhaenge_anzahl, status, gelesen)
             VALUES (?, 'eingang', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'eingang', 0)",
            [
                $kontoId,
                $messageId,
                $parsed['in_reply_to'] ?: null,
                $parsed['absender_email'],
                $parsed['absender_name'],
                $kontoEmail,
                implode(',', $parsed['cc']),
                mb_substr($parsed['betreff'] ?? '', 0, 500),
                $parsed['body_plain'],
                $parsed['body_html'],
                $parsed['empfangen_am'] ?: date('Y-m-d H:i:s'),
                $rohPfad,
                $quelle,
                count($parsed['anhaenge']),
            ]
        );
        $mailId = (int)$this->db->queryValue("SELECT LAST_INSERT_ID()");

        // Anhänge speichern
        foreach ($parsed['anhaenge'] as $anh) {
            try {
                $unterordner = '/var/www/storage/mail/anhaenge/' . date('Y-m') . '/' . $mailId;
                if (!is_dir($unterordner)) { @mkdir($unterordner, 0775, true); }
                $sicherName = preg_replace('/[^A-Za-z0-9._-]/', '_', $anh['name']);
                $pfad = $unterordner . '/' . $sicherName;
                file_put_contents($pfad, $anh['data']);
                @chmod($pfad, 0664);
                $this->db->execute(
                    "INSERT INTO mail_anhaenge (mail_id, dateiname, mime_typ, groesse_bytes, pfad)
                     VALUES (?, ?, ?, ?, ?)",
                    [$mailId, $anh['name'], $anh['mime'], strlen($anh['data']), $pfad]
                );
            } catch (\Throwable $e) { /* fail-safe */ }
        }

        return ['status' => 'erfolg', 'mail_id' => $mailId, 'fehler' => null];
    }

    /**
     * Pragmatischer MIME-Parser über Symfony Mime.
     * Fällt auf einfaches Header/Body-Parsing zurück, wenn was schief geht.
     */
    private function parseRohEml(string $rohEml): array
    {
        $result = [
            'message_id' => null, 'in_reply_to' => null,
            'absender_email' => '', 'absender_name' => '',
            'cc' => [], 'betreff' => '', 'empfangen_am' => null,
            'body_plain' => '', 'body_html' => '', 'anhaenge' => [],
        ];

        // Header parsen (einfache Variante)
        $teile = preg_split("/\r?\n\r?\n/", $rohEml, 2);
        $headerRoh = $teile[0] ?? '';
        $bodyRoh = $teile[1] ?? '';

        // Header-Zeilen mit Multi-Line-Support
        $headerZeilen = preg_split("/\r?\n(?![ \t])/", $headerRoh);
        $headers = [];
        foreach ($headerZeilen as $z) {
            if (preg_match('/^([^:]+):\s*(.*)$/s', $z, $m)) {
                $headers[strtolower(trim($m[1]))] = trim(preg_replace("/\r?\n[ \t]+/", ' ', $m[2]));
            }
        }

        $result['message_id'] = trim($headers['message-id'] ?? '', '<> ');
        $result['in_reply_to'] = trim($headers['in-reply-to'] ?? '', '<> ');
        $result['betreff'] = $this->dekodiereHeader($headers['subject'] ?? '');

        // From
        $fromRaw = $headers['from'] ?? '';
        if (preg_match('/^"?(.*?)"?\s*<([^>]+)>$/', $fromRaw, $m)) {
            $result['absender_name'] = $this->dekodiereHeader(trim($m[1]));
            $result['absender_email'] = trim($m[2]);
        } else {
            $result['absender_email'] = trim($fromRaw, ' <>');
        }

        // CC
        if (!empty($headers['cc'])) {
            $cc = explode(',', $headers['cc']);
            foreach ($cc as $a) {
                $a = trim($a);
                if (preg_match('/<([^>]+)>/', $a, $mm)) $result['cc'][] = $mm[1];
                elseif (filter_var($a, FILTER_VALIDATE_EMAIL)) $result['cc'][] = $a;
            }
        }

        // Date
        if (!empty($headers['date'])) {
            $ts = strtotime($headers['date']);
            if ($ts) $result['empfangen_am'] = date('Y-m-d H:i:s', $ts);
        }

        // Body parsen — Multipart oder Single-Part
        $contentType = $headers['content-type'] ?? 'text/plain';
        if (stripos($contentType, 'multipart/') !== false) {
            if (preg_match('/boundary\s*=\s*"?([^";\s]+)"?/i', $contentType, $bm)) {
                $boundary = $bm[1];
                $parts = explode('--' . $boundary, $bodyRoh);
                foreach ($parts as $p) {
                    if (trim($p) === '' || trim($p) === '--') continue;
                    $this->verarbeitePart($p, $result);
                }
            }
        } else {
            // Einteilige Mail. Frueher landete der Inhalt hier IMMER in body_plain — auch
            // wenn im Kopf "Content-Type: text/html" stand. Genau so verschickt Exchange
            // (und z.B. Asana): ein einziger HTML-Teil, base64-kodiert. Ergebnis war
            // HTML-Quelltext im Text-Feld und eine leere HTML-Anzeige.
            $decoded = $this->dekodiereBody($bodyRoh, $headers);
            if (stripos($contentType, 'text/html') !== false) {
                $result['body_html']  = $decoded;
                $result['body_plain'] = $this->htmlZuText($decoded);
            } else {
                $result['body_plain'] = $decoded;
            }
        }

        // Kein Text, aber HTML? Dann Text aus dem HTML ableiten — sonst haette die
        // Text-Ansicht (und spaeter die Wissensdatenbank) nichts Brauchbares.
        if (trim($result['body_plain']) === '' && $result['body_html'] !== '') {
            $result['body_plain'] = $this->htmlZuText($result['body_html']);
        }

        // Wenn kein body_html, aber body_plain → einfacher HTML-Render für die Anzeige
        if ($result['body_html'] === '' && $result['body_plain'] !== '') {
            $result['body_html'] = '<pre style="white-space:pre-wrap;font-family:inherit;">' . htmlspecialchars($result['body_plain']) . '</pre>';
        }

        return $result;
    }

    /**
     * HTML in lesbaren Text ueberfuehren (fuer die Text-Ansicht und spaeter das Wissen).
     * Script/Style muessen RAUS, sonst landet CSS-Code im Text.
     */
    private function htmlZuText(string $html): string
    {
        $t = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $t = preg_replace('#<br\s*/?>#i', "\n", $t) ?? $t;
        $t = preg_replace('#</(p|div|tr|h[1-6]|li)>#i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t) ?? $t;      // NBSP mit einfangen
        $t = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $t) ?? $t;
        return trim($t);
    }

    private function verarbeitePart(string $part, array &$result): void
    {
        $teile = preg_split("/\r?\n\r?\n/", ltrim($part), 2);
        if (count($teile) < 2) return;
        $partHeaderRoh = $teile[0];
        $partBody = $teile[1];

        $partHeaderZeilen = preg_split("/\r?\n(?![ \t])/", $partHeaderRoh);
        $h = [];
        foreach ($partHeaderZeilen as $z) {
            if (preg_match('/^([^:]+):\s*(.*)$/s', $z, $m)) {
                $h[strtolower(trim($m[1]))] = trim(preg_replace("/\r?\n[ \t]+/", ' ', $m[2]));
            }
        }
        $contentType = $h['content-type'] ?? '';
        $disposition = $h['content-disposition'] ?? '';

        // Verschachteltes Multipart? Rekursion
        if (stripos($contentType, 'multipart/') !== false) {
            if (preg_match('/boundary\s*=\s*"?([^";\s]+)"?/i', $contentType, $bm)) {
                $boundary = $bm[1];
                $parts = explode('--' . $boundary, $partBody);
                foreach ($parts as $p) {
                    if (trim($p) === '' || trim($p) === '--') continue;
                    $this->verarbeitePart($p, $result);
                }
            }
            return;
        }

        $isAttachment = stripos($disposition, 'attachment') !== false
            || (stripos($disposition, 'inline') !== false && preg_match('/name\s*=\s*"?([^";\s]+)"?/i', $contentType));
        if ($isAttachment) {
            $dateiName = null;
            if (preg_match('/filename\s*=\s*"?([^";\s]+)"?/i', $disposition, $fn)) $dateiName = $fn[1];
            elseif (preg_match('/name\s*=\s*"?([^";\s]+)"?/i', $contentType, $fn)) $dateiName = $fn[1];
            $dateiName = $dateiName ?: ('anhang_' . count($result['anhaenge']) + 1);
            $mime = preg_replace('/;.*$/', '', $contentType) ?: 'application/octet-stream';
            $rawData = $this->dekodiereBody($partBody, $h);
            $result['anhaenge'][] = ['name' => $dateiName, 'mime' => $mime, 'data' => $rawData];
            return;
        }

        $decoded = $this->dekodiereBody($partBody, $h);
        if (stripos($contentType, 'text/html') !== false) {
            $result['body_html'] = $decoded;
        } elseif (stripos($contentType, 'text/plain') !== false || $result['body_plain'] === '') {
            $result['body_plain'] = $decoded;
        }
    }

    private function dekodiereBody(string $body, array $headers): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '7bit');
        $charset = 'UTF-8';
        $contentType = $headers['content-type'] ?? '';
        if (preg_match('/charset\s*=\s*"?([^";\s]+)"?/i', $contentType, $cm)) {
            $charset = strtoupper($cm[1]);
        }

        switch ($encoding) {
            case 'base64':
                $data = base64_decode($body, true) ?: $body;
                break;
            case 'quoted-printable':
                $data = quoted_printable_decode($body);
                break;
            default:
                $data = $body;
        }

        if ($charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $data = @mb_convert_encoding($data, 'UTF-8', $charset);
        }
        return $data;
    }

    private function dekodiereHeader(string $value): string
    {
        if (function_exists('iconv_mime_decode')) {
            return @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value;
        }
        return $value;
    }

    private function bauRawEml(\Webklex\PHPIMAP\Message $message): string
    {
        $raw = (string)$message->getHeader()->raw;
        if (!str_ends_with($raw, "\r\n")) $raw .= "\r\n";
        $raw .= "\r\n";
        $raw .= (string)$message->getRawBody();
        return $raw;
    }

    /**
     * HARTE SPERRE: Darf an diesem Postfach ueberhaupt etwas veraendert werden?
     *
     * Thomas' Vorgabe ist unmissverstaendlich: Am Exchange-Postfach wird NIE etwas
     * geloescht, verschoben oder angelegt — sortiert wird ausschliesslich in Outlook.
     *
     * Deshalb reicht das `nur_lesen`-Haekchen nicht: Ein Haekchen kann man versehentlich
     * umlegen. Fuer OAuth2-Konten (= Microsoft 365) ist Schreiben hier GRUNDSAETZLICH
     * verboten, unabhaengig von jeder Einstellung. Die Sperre sitzt an der einzigen Stelle,
     * durch die jeder schreibende Zugriff muss.
     */
    private function darfSchreiben(array $cfg): bool
    {
        if (($cfg['auth_typ'] ?? 'passwort') === 'oauth2') return false;   // Exchange: NIE
        return empty($cfg['nur_lesen']);
    }

    private function verschiebe(\Webklex\PHPIMAP\Message $message, string $zielOrdner): void
    {
        try {
            $message->move($zielOrdner);
        } catch (\Throwable $e) {
            // Best-effort, nicht fatal
        }
    }

    private function sicherstelleOrdner(\Webklex\PHPIMAP\Client $client, string $pfad): void
    {
        try {
            foreach ($client->getFolders() as $folder) {
                if ($folder->path === $pfad) return;
            }
            $client->createFolder($pfad, true);
        } catch (\Throwable $e) { /* IONOS-Quirk: legt trotzdem an */ }
    }

    private function schreibeLog(int $kontoId, float $gestartet, string $trigger, array $log): void
    {
        $dauerMs = (int)round((microtime(true) - $gestartet) * 1000);
        $this->db->execute(
            "INSERT INTO mail_pull_logs
                (konto_id, gestartet_am, dauer_ms, trigger_typ,
                 erfolg_count, dublette_count, fehler_count, uebersprungen_count,
                 verbindungs_fehler, details_json)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $kontoId, $dauerMs, $trigger,
                $log['erfolg'], $log['dublette'], $log['fehler'], $log['uebersprungen'] ?? 0,
                $log['verbindungs_fehler'],
                !empty($log['eintraege']) ? json_encode($log['eintraege'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
        // Rotate: max 200 Einträge pro Konto
        $idsAlt = $this->db->query(
            "SELECT id FROM mail_pull_logs WHERE konto_id = ? ORDER BY id DESC LIMIT 1000 OFFSET 200",
            [$kontoId]
        );
        foreach ($idsAlt as $r) {
            $this->db->execute("DELETE FROM mail_pull_logs WHERE id = ?", [$r['id']]);
        }
    }
}
