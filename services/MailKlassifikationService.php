<?php
namespace Services;

use Core\Database;

/**
 * Klassifiziert eingehende Mails:
 * 1. Manuelle Regeln zuerst (mail_regeln, priorität-sortiert)
 * 2. Wenn keine Regel matcht → KI (Claude Haiku)
 *
 * Schreibt Ergebnis in mail_klassifikationen + setzt Status auf 'klassifiziert'.
 * Triggert ggf. LAM-Hooks (Anbieter-Match) wenn folgeaktion das vorsieht.
 */
class MailKlassifikationService
{
    private Database $db;

    public const KATEGORIEN = [
        'anbieter_antwort', 'standardfrage', 'kundenanfrage',
        'presseanfrage', 'spam', 'info', 'sonstiges',
    ];
    public const FOLGEAKTIONEN = [
        'auto_antworten', 'vorlage_vorschlagen',
        'lam_korrespondenz_anlegen', 'lam_anbieter_zuordnen',
        'manuell_pruefen', 'ignorieren',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Baut das Modell fuer den Erstentwurf (Klassifikation + Antwortvorschlag).
     *
     * Einstellung `mail_entwurf_modell`:
     *   'local:<modell>'  -> lokaler Server (Datensouveraen, Standard)
     *   'anthropic'       -> Claude Haiku (schneller, aber Mail geht in die Cloud)
     * Leer/unbekannt -> lokal, falls konfiguriert, sonst Anthropic, sonst null (Heuristik).
     */
    private function baueEntwurfsModell(): ?\Services\AIService
    {
        $wahl   = trim((string) \Core\Settings::get('mail_entwurf_modell', ''));
        $loKey  = (string) \Core\Settings::get('local_api_key', '');
        $loUrl  = (string) \Core\Settings::get('local_base_url', 'https://ki.thoxan.com/llm/v1');
        $anKey  = (string) \Core\Settings::get('anthropic_api_key', '');

        // Explizit lokal gewaehlt
        if (str_starts_with($wahl, 'local:') && $loKey !== '') {
            return $this->lokalesModell(substr($wahl, 6) ?: 'gpt-oss:20b', $loKey, $loUrl);
        }
        // Explizit Anthropic gewaehlt
        if ($wahl === 'anthropic' && $anKey !== '') {
            $ai = new \Services\AIService($anKey, 'anthropic');
            $ai->setModel('claude-haiku-4-5-20251001');
            return $ai;
        }
        // Kein explizites Setting: lokal bevorzugen (datensouveraen), sonst Anthropic
        if ($loKey !== '') {
            return $this->lokalesModell('gpt-oss:20b', $loKey, $loUrl);
        }
        if ($anKey !== '') {
            $ai = new \Services\AIService($anKey, 'anthropic');
            $ai->setModel('claude-haiku-4-5-20251001');
            return $ai;
        }
        return null;
    }

    private function lokalesModell(string $modell, string $key, string $url): \Services\AIService
    {
        $ai = new \Services\AIService($key, 'local');
        $ai->configureLocal($url);
        $ai->setModel($modell);
        $ai->setNumCtx(16384);   // Mail + Stilprofil + Regeln passen so hinein
        return $ai;
    }

    /**
     * „Mit Claude verfeinern": nimmt eine bestehende (lokale oder editierte) Antwort und
     * laesst sie von Claude gezielt verbessern — bewusst in der Cloud, auf Knopfdruck.
     *
     * Genau der Hybrid-Gedanke: Datensouveraen als Standard, Cloud-Qualitaet dort, wo der
     * Nutzer sie ausdruecklich will. Nutzt Stilprofil + freigegebene Regeln.
     *
     * @param string $auftrag optionaler Zusatz („kuerzer", „foermlicher" …)
     */
    public function verfeinereAntwort(int $mailId, string $entwurf, string $auftrag = '', ?int $kontoId = null): array
    {
        // mailId > 0: Feinschliff einer ANTWORT (Original-Mail als Kontext).
        // mailId = 0: Feinschliff eines FREIEN Texts (neue Mail) — kein Original, nur Stil.
        $mail = $mailId > 0 ? $this->db->queryOne("SELECT * FROM mail_nachrichten WHERE id = ?", [$mailId]) : null;
        $kontoId = $mail ? (int) $mail['konto_id'] : (int) $kontoId;

        $anKey = (string) \Core\Settings::get('anthropic_api_key', '');
        if ($anKey === '') throw new \RuntimeException('Kein Anthropic-Key konfiguriert.');

        require_once SERVICES_PATH . '/MailLernService.php';
        $gelernt = $kontoId ? (new \Services\MailLernService($this->db))->promptBlock($kontoId) : '';

        $system = "Du verbesserst einen E-Mail-Text von Thomas Kilian. Behalte den Inhalt "
                . "und die Aussagen bei, mache ihn aber natuerlicher und treffender.\n\n"
                . \Services\MailStilService::UMLAUT_REGEL . $gelernt
                . "\n\nGib NUR den verbesserten Text zurueck, ohne Vorspann.";

        $user = ($mail ? "URSPRUENGLICHE MAIL:\n" . mb_substr((string) $mail['body_plain'], 0, 3000) . "\n\n" : '')
              . "BISHERIGER ENTWURF:\n" . $entwurf
              . ($auftrag !== '' ? "\n\nZUSAETZLICHER WUNSCH: " . $auftrag : '');

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($anKey, 'anthropic');
        $ai->setModel('claude-opus-4-7');
        $ai->setMaxTokens(1500);
        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);

        return ['text' => trim((string) ($antwort['content'] ?? '')), 'modell' => 'claude-opus-4-7'];
    }

    /**
     * KI-Entwurf fuer eine NEUE Mail aus Stichworten — im gelernten Stil, lokal.
     *
     * @return array{betreff:string, text:string}
     */
    public function entwerfeNeueMail(int $kontoId, string $stichworte, string $empfaenger = ''): array
    {
        $stichworte = trim($stichworte);
        if ($stichworte === '') throw new \InvalidArgumentException('Bitte ein paar Stichworte angeben.');

        require_once SERVICES_PATH . '/MailLernService.php';
        $gelernt = (new \Services\MailLernService($this->db))->promptBlock($kontoId);

        $ai = $this->baueEntwurfsModell();
        if ($ai === null) throw new \RuntimeException('Kein Modell verfügbar.');
        $ai->setMaxTokens(1200);
        $ai->setTimeout(120);

        $system = "Du schreibst eine NEUE E-Mail im Namen von Thomas Kilian anhand seiner Stichworte.\n\n"
                . \Services\MailStilService::UMLAUT_REGEL . $gelernt . "\n\n"
                . "Antworte AUSSCHLIESSLICH mit JSON: {\"betreff\": \"...\", \"text\": \"...\"}. "
                . "Der Text ist die fertige Mail ohne Grussformel-Doppelung (die Signatur haengt das System an). "
                . "Erfinde keine Fakten, die nicht in den Stichworten stehen.";

        $user = ($empfaenger !== '' ? "Empfänger: $empfaenger\n\n" : '')
              . "Stichworte für die Mail:\n" . $stichworte;

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $json = $this->ziehJson((string) ($antwort['content'] ?? ''));

        return [
            'betreff' => trim((string) ($json['betreff'] ?? '')),
            'text'    => trim((string) ($json['text'] ?? '')),
        ];
    }

    /**
     * KI-Begleittext fuer eine WEITERLEITUNG — kurz, im Stil, lokal.
     * Der zitierte Original-Text wird NICHT vom Modell erzeugt, sondern spaeter angehaengt.
     */
    public function entwerfeWeiterleitung(int $mailId, string $stichworte): array
    {
        $mail = $this->db->queryOne("SELECT * FROM mail_nachrichten WHERE id = ?", [$mailId]);
        if (!$mail) throw new \RuntimeException('Mail nicht gefunden.');

        require_once SERVICES_PATH . '/MailLernService.php';
        $gelernt = (new \Services\MailLernService($this->db))->promptBlock((int) $mail['konto_id']);

        $ai = $this->baueEntwurfsModell();
        if ($ai === null) throw new \RuntimeException('Kein Modell verfügbar.');
        $ai->setMaxTokens(700);
        $ai->setTimeout(120);

        $system = "Du schreibst einen KURZEN Begleittext, mit dem Thomas Kilian eine E-Mail an eine dritte "
                . "Person weiterleitet. Nur der Begleittext (2-4 Sätze), nicht die Original-Mail wiederholen.\n\n"
                . \Services\MailStilService::UMLAUT_REGEL . $gelernt . "\n\n"
                . "Gib NUR den Begleittext zurück, ohne Betreff, ohne Zitat der Original-Mail.";

        $user = "WEITERZULEITENDE MAIL (Betreff: " . mb_substr((string) $mail['betreff'], 0, 120) . ", "
              . "von " . (string) $mail['absender_email'] . "):\n"
              . mb_substr((string) $mail['body_plain'], 0, 1500) . "\n\n"
              . ($stichworte !== '' ? "Worum es Thomas beim Weiterleiten geht:\n" . $stichworte
                                    : "Schreibe einen neutralen, freundlichen Weiterleitungs-Begleittext.");

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        return ['text' => trim((string) ($antwort['content'] ?? ''))];
    }

    /** JSON aus einer Modellantwort ziehen (auch wenn Text drumherum steht). */
    private function ziehJson(string $inhalt): array
    {
        if (preg_match('/\{[\s\S]*\}/m', $inhalt, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) return $j;
        }
        // Kein JSON: als reiner Text behandeln (Modell hat die Anweisung ignoriert)
        return ['betreff' => '', 'text' => trim($inhalt)];
    }

    public function klassifiziereMail(int $mailId): array
    {
        $mail = $this->db->queryOne(
            "SELECT * FROM mail_nachrichten WHERE id = ? AND geloescht_am IS NULL",
            [$mailId]
        );
        if (!$mail) throw new \InvalidArgumentException('Mail nicht gefunden.');

        // 1) Regeln durchprobieren (höchste Priorität zuerst)
        $regelResult = $this->wendeRegelnAn($mail);
        if ($regelResult) {
            $this->speichereKlassifikation($mailId, $regelResult);
            $this->lamHookAusfuehren($mailId, $regelResult);
            $this->db->execute("UPDATE mail_nachrichten SET status = 'klassifiziert' WHERE id = ?", [$mailId]);
            return $regelResult;
        }

        // 2) KI
        $kiResult = $this->kiKlassifizieren($mail);
        $this->speichereKlassifikation($mailId, $kiResult);
        $this->lamHookAusfuehren($mailId, $kiResult);
        $this->db->execute("UPDATE mail_nachrichten SET status = 'klassifiziert' WHERE id = ?", [$mailId]);
        return $kiResult;
    }

    private function wendeRegelnAn(array $mail): ?array
    {
        $regeln = $this->db->query(
            "SELECT * FROM mail_regeln WHERE aktiv = 1 ORDER BY prioritaet ASC, id ASC"
        );
        foreach ($regeln as $r) {
            $match = true;
            if (!empty($r['absender_pattern']) && !@preg_match('/' . str_replace('/', '\/', $r['absender_pattern']) . '/i', $mail['absender_email'])) {
                $match = false;
            }
            if ($match && !empty($r['betreff_pattern']) && !@preg_match('/' . str_replace('/', '\/', $r['betreff_pattern']) . '/i', $mail['betreff'] ?? '')) {
                $match = false;
            }
            if ($match && !empty($r['body_pattern']) && !@preg_match('/' . str_replace('/', '\/', $r['body_pattern']) . '/i', $mail['body_plain'] ?? '')) {
                $match = false;
            }
            if ($match) {
                return [
                    'kategorie' => $r['kategorie'],
                    'kategorie_konfidenz' => 0.99,
                    'absicht' => 'Regel-Match: ' . $r['name'],
                    'sprache' => null,
                    'dringlichkeit' => null,
                    'folgeaktion' => $r['folgeaktion'],
                    'folgeaktion_konfidenz' => 0.99,
                    'vorlage_id' => $r['vorlage_id'],
                    'vorgeschlagene_antwort' => null,
                    'ki_modell' => 'regel:' . $r['id'],
                    'meta' => ['regel_name' => $r['name']],
                ];
            }
        }
        return null;
    }

    private function kiKlassifizieren(array $mail): array
    {
        require_once SERVICES_PATH . '/AIService.php';

        // Hybrid-Modell-Wahl: Der Erstentwurf laeuft standardmaessig LOKAL — so verlaesst
        // der Mail-Inhalt den Server nicht. Fuer den bewussten Feinschliff gibt es im
        // Antwort-Editor den Knopf „Mit Claude verfeinern" (verfeinereAntwort()).
        // Einstellbar unter /admin/settings?tab=mail.
        $ai = $this->baueEntwurfsModell();
        if ($ai === null) {
            return $this->heuristikFallback($mail);   // kein Modell erreichbar -> Heuristik
        }
        $ai->setMaxTokens(1200);
        $ai->setTimeout(120);   // lokale Modelle brauchen laenger als Haiku
        // Ehrliches Label: das TATSAECHLICH gewaehlte Modell, nicht ein hart verdrahtetes.
        // Sonst stand in der DB immer "claude-haiku-4-5", auch wenn lokal qwen lief — und man
        // konnte nicht mehr sehen, ob der Mail-Inhalt den Server verlassen hat.
        $modellLabel = $ai->getProvider() . '/' . $ai->getModel();

        $system = "Du klassifizierst eingehende E-Mails für ein PR- und Linkaufbau-Tool (Thoxan Communications). "
                . "Antworte AUSSCHLIESSLICH mit JSON, keine Erklärungen drumherum. Schema:\n"
                . "{\n"
                . "  \"kategorie\": \"" . implode('|', self::KATEGORIEN) . "\",\n"
                . "  \"kategorie_konfidenz\": 0.0-1.0,\n"
                . "  \"absicht\": \"max 200 Zeichen, was will der Absender\",\n"
                . "  \"sprache\": \"de|en\",\n"
                . "  \"dringlichkeit\": \"niedrig|mittel|hoch\",\n"
                . "  \"folgeaktion\": \"" . implode('|', self::FOLGEAKTIONEN) . "\",\n"
                . "  \"folgeaktion_konfidenz\": 0.0-1.0,\n"
                . "  \"vorgeschlagene_antwort\": \"freundliche, knappe Antwort auf Deutsch — leer wenn ignorieren\",\n"
                . "  \"anbieter_kandidat\": \"Firmenname falls aus Domain ableitbar, sonst null\",\n"
                . "  \"extrahierte_felder\": {\n"
                . "    \"preis\": null, \"linktext\": null, \"veroeffentlichungs_url\": null,\n"
                . "    \"linkziel\": null, \"anbieter_kontakt_name\": null\n"
                . "  }\n"
                . "}\n\n"
                . "WICHTIG zu weitergeleiteten Mails (Betreff WG:/Fwd:/AW:):\n"
                . "- Im Inhalt steht der eigentliche Absender (Original-Mail). Klassifiziere nach DEM, nicht nach dem Weiterleiter.\n"
                . "- Setze 'anbieter_kandidat' auf den Original-Absender, nicht auf den Weiterleiter.\n"
                . "- Im Antwort-Text höflich an den Original-Absender adressieren, nicht an den Weiterleiter.\n\n"
                . "Hinweise:\n"
                . "- 'anbieter_antwort' = Antwort eines Linkanbieter-Kontakts auf eine Akquise-Anfrage, oft mit Preisen\n"
                . "- 'standardfrage' = wiederkehrende Anfrage (z.B. zu Lieferzeit)\n"
                . "- 'kundenanfrage' = Kunde fragt etwas Spezifisches, braucht individuelle Antwort\n"
                . "- 'presseanfrage' = Journalist/Blogger fragt nach Material\n"
                . "- 'spam' = unaufgeforderte Linkbuilder-Mail (high DA backlinks, guest post opportunities, SEO-Angebote)\n"
                . "- 'info' = Newsletter, Statusmail, Systembestätigung, kein Handlungsbedarf\n"
                . "- 'sonstiges' = nur wenn nichts anderes passt\n\n"
                . "Folgeaktionen:\n"
                . "- 'auto_antworten' nur bei sehr klaren Standardfragen mit hoher Konfidenz\n"
                . "- 'vorlage_vorschlagen' = Mensch wählt Vorlage, KI füllt Platzhalter\n"
                . "- 'lam_korrespondenz_anlegen' bei anbieter_antwort\n"
                . "- 'lam_anbieter_zuordnen' wenn anbieter_kandidat erkannt, aber noch nicht im System\n"
                . "- 'manuell_pruefen' bei Unsicherheit\n"
                . "- 'ignorieren' bei Spam";

        /**
         * HIER wird das Gelernte wirksam.
         *
         * Ohne diesen Block waere das ganze Lernsystem ein Museum: Es wuerde Stilprofile und
         * Regeln sammeln, die nie jemand liest. Der Block enthaelt Thomas' Schreibstil (aus
         * seinen echten Mails gelernt) und die von ihm FREIGEGEBENEN Regeln.
         *
         * Ist noch nichts gelernt oder freigegeben, kommt ein leerer String zurueck — die
         * Klassifikation arbeitet dann exakt wie bisher. Kein Risiko fuer Bestandskonten.
         */
        require_once SERVICES_PATH . '/MailLernService.php';
        $gelernt = (new MailLernService($this->db))->promptBlock((int) $mail['konto_id']);
        if ($gelernt !== '') {
            $system .= "\n\n" . $gelernt
                . "\nDie vorgeschlagene_antwort MUSS in diesem Stil und nach diesen Regeln geschrieben sein.";
        }

        $user = "VON: " . $mail['absender_name'] . " <" . $mail['absender_email'] . ">\n"
              . "BETREFF: " . ($mail['betreff'] ?? '') . "\n"
              . "DATUM: " . $mail['empfangen_am'] . "\n\n"
              . "INHALT:\n" . mb_substr($mail['body_plain'] ?? '', 0, 5000);

        try {
            $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        } catch (\Throwable $e) {
            return $this->heuristikFallback($mail, 'KI-Fehler: ' . $e->getMessage());
        }
        $content = trim($antwort['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
        $daten = json_decode($content, true);
        if (!is_array($daten)) {
            return $this->heuristikFallback($mail, 'KI-Antwort kein JSON');
        }

        // Whitelist-Check
        if (!in_array($daten['kategorie'] ?? '', self::KATEGORIEN, true)) $daten['kategorie'] = 'sonstiges';
        if (!in_array($daten['folgeaktion'] ?? '', self::FOLGEAKTIONEN, true)) $daten['folgeaktion'] = 'manuell_pruefen';

        return [
            'kategorie' => $daten['kategorie'],
            'kategorie_konfidenz' => (float)($daten['kategorie_konfidenz'] ?? 0.5),
            'absicht' => mb_substr((string)($daten['absicht'] ?? ''), 0, 200),
            'sprache' => (string)($daten['sprache'] ?? 'de'),
            'dringlichkeit' => in_array($daten['dringlichkeit'] ?? '', ['niedrig','mittel','hoch'], true) ? $daten['dringlichkeit'] : 'mittel',
            'folgeaktion' => $daten['folgeaktion'],
            'folgeaktion_konfidenz' => (float)($daten['folgeaktion_konfidenz'] ?? 0.5),
            'vorgeschlagene_antwort' => (string)($daten['vorgeschlagene_antwort'] ?? ''),
            'vorlage_id' => null,
            'ki_modell' => $modellLabel,
            'meta' => [
                'anbieter_kandidat' => $daten['anbieter_kandidat'] ?? null,
                'extrahierte_felder' => $daten['extrahierte_felder'] ?? [],
            ],
        ];
    }

    private function heuristikFallback(array $mail, string $grund = 'Keine KI verfügbar'): array
    {
        $absender = mb_strtolower($mail['absender_email']);
        $betreff = mb_strtolower($mail['betreff'] ?? '');

        // Spam-Heuristik
        $spamWords = ['guest post', 'high da', 'increase your', 'seo services', 'cheap backlinks'];
        foreach ($spamWords as $sw) {
            if (mb_strpos($betreff, $sw) !== false || mb_strpos(mb_strtolower($mail['body_plain'] ?? ''), $sw) !== false) {
                return [
                    'kategorie' => 'spam', 'kategorie_konfidenz' => 0.8,
                    'absicht' => 'Vermutlich unaufgeforderte Linkbuilder-Mail',
                    'sprache' => 'en', 'dringlichkeit' => 'niedrig',
                    'folgeaktion' => 'ignorieren', 'folgeaktion_konfidenz' => 0.8,
                    'vorgeschlagene_antwort' => '', 'vorlage_id' => null,
                    'ki_modell' => 'heuristik', 'meta' => ['grund' => $grund],
                ];
            }
        }

        return [
            'kategorie' => 'sonstiges', 'kategorie_konfidenz' => 0.3,
            'absicht' => '(Heuristik-Fallback)',
            'sprache' => 'de', 'dringlichkeit' => 'mittel',
            'folgeaktion' => 'manuell_pruefen', 'folgeaktion_konfidenz' => 0.3,
            'vorgeschlagene_antwort' => '', 'vorlage_id' => null,
            'ki_modell' => 'heuristik', 'meta' => ['grund' => $grund],
        ];
    }

    private function speichereKlassifikation(int $mailId, array $r): void
    {
        // Bestehende Klassifikation überschreiben
        $exists = $this->db->queryValue("SELECT 1 FROM mail_klassifikationen WHERE mail_id = ?", [$mailId]);
        if ($exists) {
            $this->db->execute(
                "UPDATE mail_klassifikationen
                 SET kategorie = ?, kategorie_konfidenz = ?, absicht = ?, sprache = ?, dringlichkeit = ?,
                     folgeaktion = ?, folgeaktion_konfidenz = ?, vorgeschlagene_antwort = ?,
                     vorlage_id = ?, ki_meta = ?, klassifiziert_am = NOW(), ki_modell = ?
                 WHERE mail_id = ?",
                [
                    $r['kategorie'], $r['kategorie_konfidenz'], $r['absicht'], $r['sprache'], $r['dringlichkeit'],
                    $r['folgeaktion'], $r['folgeaktion_konfidenz'], $r['vorgeschlagene_antwort'],
                    $r['vorlage_id'], json_encode($r['meta'], JSON_UNESCAPED_UNICODE), $r['ki_modell'],
                    $mailId,
                ]
            );
        } else {
            $this->db->execute(
                "INSERT INTO mail_klassifikationen
                    (mail_id, kategorie, kategorie_konfidenz, absicht, sprache, dringlichkeit,
                     folgeaktion, folgeaktion_konfidenz, vorgeschlagene_antwort, vorlage_id, ki_meta, ki_modell)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $mailId, $r['kategorie'], $r['kategorie_konfidenz'], $r['absicht'], $r['sprache'], $r['dringlichkeit'],
                    $r['folgeaktion'], $r['folgeaktion_konfidenz'], $r['vorgeschlagene_antwort'],
                    $r['vorlage_id'], json_encode($r['meta'], JSON_UNESCAPED_UNICODE), $r['ki_modell'],
                ]
            );
        }
    }

    private function lamHookAusfuehren(int $mailId, array $klassifikation): void
    {
        if (!in_array($klassifikation['folgeaktion'], ['lam_korrespondenz_anlegen', 'lam_anbieter_zuordnen'], true)) {
            return;
        }
        require_once SERVICES_PATH . '/MailLamAdapter.php';
        try {
            $adapter = new MailLamAdapter($this->db);
            $adapter->verarbeite($mailId, $klassifikation);
        } catch (\Throwable $e) { /* fail-safe — UI zeigt es trotzdem */ }
    }
}
