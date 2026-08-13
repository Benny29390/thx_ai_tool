<?php
namespace Services;

use Core\Database;

/**
 * Lernsystem für die Mail-Antworten.
 *
 * Es wird KEIN Modell trainiert (kein Fine-Tuning). Stattdessen wird der KI bei jeder
 * Antwort das richtige Material vorgelegt, und sie lernt aus Thomas' Korrekturen:
 *
 *   1. STIL      — wie schreibt Thomas? (MailStilService, aus seinen echten Mails)
 *   2. REGELN    — was gilt immer? (hier: aus der Stilanalyse UND aus Korrekturen)
 *   3. KORREKTUR — jede Änderung an einem KI-Entwurf ist ein Lehrstück
 *
 * Kernprinzip: REVIEW-TO-ACTIVATE. Jede abgeleitete Regel ist zuerst nur ein VORSCHLAG.
 * Erst wenn Thomas sie freigibt, fließt sie in künftige Antworten ein. Ohne diese Bremse
 * würde ein einziger Ausreißer (eine untypische Korrektur) das System dauerhaft verbiegen.
 *
 * Dasselbe Muster läuft bereits im Tagesplaner (`planner_learned_rules`) — bewährt, nicht neu erfunden.
 */
class MailLernService
{
    private Database $db;

    /** So viele Regeln fließen höchstens in einen Antwort-Prompt (sonst wird er unbrauchbar lang). */
    private const MAX_REGELN_IM_PROMPT = 25;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ================================================================ Regeln lesen

    /** Freigegebene Regeln — das, was tatsächlich wirkt. */
    public function aktiveRegeln(int $kontoId): array
    {
        return $this->db->query(
            "SELECT * FROM mail_gelernte_regeln
             WHERE konto_id = ? AND status = 'aktiv'
             ORDER BY kategorie, id",
            [$kontoId]
        );
    }

    public function vorschlaege(int $kontoId): array
    {
        return $this->db->query(
            "SELECT * FROM mail_gelernte_regeln
             WHERE konto_id = ? AND status = 'vorschlag'
             ORDER BY quelle, id DESC",
            [$kontoId]
        );
    }

    /**
     * Der Block, der jedem Antwort-Prompt vorangestellt wird: Stilprofil + aktive Regeln.
     * Ist nichts gelernt, kommt ein leerer String zurück — die KI arbeitet dann wie bisher.
     */
    public function promptBlock(int $kontoId): string
    {
        require_once SERVICES_PATH . '/MailStilService.php';
        $stil = (new MailStilService($this->db))->aktivesProfil($kontoId);

        $regeln = array_slice($this->aktiveRegeln($kontoId), 0, self::MAX_REGELN_IM_PROMPT);

        $block = '';
        if ($stil && trim((string) $stil['profil_text']) !== '') {
            $block .= "\n\n=== SO SCHREIBT THOMAS (aus seinen echten Mails gelernt) ===\n"
                    . trim((string) $stil['profil_text']) . "\n";
        }
        if ($regeln) {
            $block .= "\n=== VERBINDLICHE REGELN (von Thomas freigegeben) ===\n";
            foreach ($regeln as $r) {
                $block .= '- ' . trim((string) $r['regel_text']) . "\n";
            }
            $block .= "Diese Regeln haben Vorrang vor Deinen eigenen Konventionen.\n";
        }
        return $block;
    }

    // ============================================================ Regeln entscheiden

    public function freigeben(int $regelId, ?int $userId): void
    {
        $this->db->execute(
            "UPDATE mail_gelernte_regeln SET status='aktiv', entschieden_am=NOW(), entschieden_von=? WHERE id=?",
            [$userId, $regelId]
        );
    }

    public function verwerfen(int $regelId, ?int $userId): void
    {
        $this->db->execute(
            "UPDATE mail_gelernte_regeln SET status='verworfen', entschieden_am=NOW(), entschieden_von=? WHERE id=?",
            [$userId, $regelId]
        );
    }

    public function bearbeiten(int $regelId, string $text): void
    {
        $t = trim($text);
        if ($t === '') throw new \InvalidArgumentException('Regeltext darf nicht leer sein.');
        $this->db->execute(
            "UPDATE mail_gelernte_regeln SET regel_text=? WHERE id=?",
            [mb_substr($t, 0, 500), $regelId]
        );
    }

    // ==================================================== Regeln aus der Stilanalyse

    /**
     * Aus den geernteten Eigen-Mails harte, überprüfbare Regeln ableiten.
     * Das Stilprofil beschreibt („Thomas schreibt knapp"), die Regeln schreiben VOR
     * („Halte Antworten unter 150 Wörtern"). Beides braucht es.
     */
    public function regelnAusStil(int $kontoId, array $proben, array $settings): array
    {
        if (count($proben) < 5) {
            throw new \RuntimeException('Zu wenige eigene Mails für eine Regel-Ableitung.');
        }

        // Bewusst knapper als beim Stilprofil: 30 Beispiele à 1000 Zeichen erzeugen eine so
        // lange JSON-Antwort, dass sie am Token-Limit ABGESCHNITTEN wird — und abgeschnittenes
        // JSON ist unlesbar. Genau daran ist der erste Lauf gescheitert (0 Regeln).
        $block = '';
        foreach (array_slice($proben, 0, 20) as $i => $p) {
            $block .= '--- Beispiel ' . ($i + 1) . ' (Betreff: ' . mb_substr((string) $p['betreff'], 0, 60) . ")\n"
                    . mb_substr((string) $p['text'], 0, 700) . "\n\n";
        }

        $system = "Du bekommst echte E-Mails, die EINE Person geschrieben hat. Leite daraus "
            . "verbindliche REGELN ab, an die sich eine KI halten muss, wenn sie künftig in "
            . "ihrem Namen Antworten entwirft.\n\n"
            . \Services\MailStilService::UMLAUT_REGEL . "\n\n"
            . "Eine gute Regel ist:\n"
            . "- KONKRET und überprüfbar ('Beginne mit \"Hallo <Vorname>,\"'), nicht vage ('sei freundlich')\n"
            . "- aus den Beispielen BELEGBAR, nicht geraten\n"
            . "- eine Regel pro Eintrag, keine Sammelregeln\n\n"
            . "Ignoriere Zufälligkeiten. Wenn etwas nur in einer von 20 Mails vorkommt, ist es keine Regel.\n\n"
            . "HÖCHSTENS 12 REGELN. Halte jedes Feld kurz — eine abgeschnittene Antwort ist wertlos.\n\n"
            . "Antworte AUSSCHLIESSLICH mit JSON:\n"
            . "{\n  \"regeln\": [\n    {\n"
            . "      \"regel\": \"die Regel als Anweisung an die KI (max 160 Zeichen)\",\n"
            . "      \"kategorie\": \"Anrede|Tonalität|Aufbau|Formulierung|Gruß|Inhalt|Tabu\",\n"
            . "      \"begruendung\": \"max 100 Zeichen: woran sieht man das?\",\n"
            . "      \"beispiel\": \"kurzes wörtliches Zitat (max 80 Zeichen)\"\n"
            . "    }\n  ]\n}";

        $json = $this->frageModell(
            $settings,
            $system,
            "Hier sind " . min(20, count($proben)) . " echte Mails dieser Person:\n\n" . $block,
            8000   // grosszuegig: lieber Luft als abgeschnittenes JSON
        );

        $neu = [];
        foreach (($json['regeln'] ?? []) as $r) {
            $text = trim((string) ($r['regel'] ?? ''));
            if ($text === '') continue;
            $id = $this->db->insert('mail_gelernte_regeln', [
                'konto_id'    => $kontoId,
                'regel_text'  => mb_substr($text, 0, 500),
                'begruendung' => $r['begruendung'] ?? null,
                'beispiel'    => $r['beispiel'] ?? null,
                'kategorie'   => mb_substr((string) ($r['kategorie'] ?? ''), 0, 60) ?: null,
                'quelle'      => 'stilanalyse',
                'status'      => 'vorschlag',   // NICHTS wirkt ohne Freigabe
            ]);
            $neu[] = $id;
        }
        return $neu;
    }

    // ================================================== Regeln aus Korrekturen lernen

    /**
     * Die eigentliche Lernschleife.
     *
     * `mail_antworten` haelt bereits BEIDES: den KI-Entwurf (`ki_vorschlag`) und das,
     * was Thomas tatsaechlich abgeschickt hat (`finaler_text`). Der Unterschied zwischen
     * beiden ist die wertvollste Information im ganzen System — er sagt woertlich, was die
     * KI falsch gemacht hat.
     *
     * Wichtig: Nur auswerten, wenn wirklich editiert wurde. Wer den Entwurf unveraendert
     * abschickt, bestaetigt ihn — daraus gibt es nichts zu lernen.
     */
    public function lerneAusKorrekturen(int $kontoId, array $settings, int $maxAntworten = 20): array
    {
        $offene = $this->db->query(
            "SELECT a.id, a.ki_vorschlag, a.finaler_text, m.betreff, m.body_plain
             FROM mail_antworten a
             JOIN mail_nachrichten m ON m.id = a.eingang_mail_id
             WHERE m.konto_id = ?
               AND a.wurde_editiert = 1
               AND a.gelernt_am IS NULL
               AND a.ki_vorschlag IS NOT NULL AND a.ki_vorschlag <> ''
             ORDER BY a.id DESC
             LIMIT ?",
            [$kontoId, $maxAntworten]
        );
        if (empty($offene)) {
            return ['ausgewertet' => 0, 'regeln' => 0, 'meldung' => 'Keine neuen Korrekturen.'];
        }

        $neu = 0;
        foreach ($offene as $a) {
            // Unveraendert? Dann war es keine Korrektur, nur ein anderer Zeilenumbruch.
            if ($this->normiere((string) $a['ki_vorschlag']) === $this->normiere((string) $a['finaler_text'])) {
                $this->db->execute("UPDATE mail_antworten SET gelernt_am=NOW() WHERE id=?", [$a['id']]);
                continue;
            }

            $system = "Eine KI hat einen E-Mail-Antwortentwurf geschrieben. Ein Mensch (Thomas) hat ihn "
                . "vor dem Versand überarbeitet. Vergleiche beide Fassungen und leite daraus ab, was die "
                . "KI künftig anders machen muss.\n\n"
                . \Services\MailStilService::UMLAUT_REGEL . "\n\n"
                . "Nur ECHTE Muster melden. Reine Umformulierungen ohne erkennbare Absicht sind KEINE Regel. "
                . "Wenn Du nichts Verwertbares findest, gib eine leere Liste zurück — das ist ein gültiges Ergebnis.\n\n"
                . "Antworte AUSSCHLIESSLICH mit JSON:\n"
                . "{\n  \"aenderung\": \"1 Satz: was hat Thomas geändert?\",\n"
                . "  \"regeln\": [\n    {\n"
                . "      \"regel\": \"Anweisung an die KI (max 200 Zeichen)\",\n"
                . "      \"kategorie\": \"Anrede|Tonalität|Aufbau|Formulierung|Gruß|Inhalt|Tabu\",\n"
                . "      \"begruendung\": \"1 Satz: warum?\"\n"
                . "    }\n  ]\n}";

            $user = "URSPRUENGLICHE MAIL (Betreff: " . mb_substr((string) $a['betreff'], 0, 100) . "):\n"
                  . mb_substr((string) $a['body_plain'], 0, 1500) . "\n\n"
                  . "KI-ENTWURF:\n" . mb_substr((string) $a['ki_vorschlag'], 0, 2000) . "\n\n"
                  . "WAS THOMAS TATSAECHLICH GESCHICKT HAT:\n" . mb_substr((string) $a['finaler_text'], 0, 2000);

            try {
                $json = $this->frageModell($settings, $system, $user, 1500);
            } catch (\Throwable $e) {
                continue;   // eine misslungene Auswertung darf den Lauf nicht stoppen
            }

            foreach (($json['regeln'] ?? []) as $r) {
                $text = trim((string) ($r['regel'] ?? ''));
                if ($text === '') continue;
                if ($this->regelExistiertAehnlich($kontoId, $text)) continue;   // keine Dubletten
                $this->db->insert('mail_gelernte_regeln', [
                    'konto_id'         => $kontoId,
                    'regel_text'       => mb_substr($text, 0, 500),
                    'begruendung'      => $r['begruendung'] ?? ($json['aenderung'] ?? null),
                    'kategorie'        => mb_substr((string) ($r['kategorie'] ?? ''), 0, 60) ?: null,
                    'quelle'           => 'korrektur',
                    'status'           => 'vorschlag',
                    'basis_antwort_id' => $a['id'],
                ]);
                $neu++;
            }

            $this->db->execute("UPDATE mail_antworten SET gelernt_am=NOW() WHERE id=?", [$a['id']]);
        }

        return [
            'ausgewertet' => count($offene),
            'regeln'      => $neu,
            'meldung'     => sprintf('%d Korrektur(en) ausgewertet, %d neue Regel-Vorschläge.', count($offene), $neu),
        ];
    }

    // ------------------------------------------------------------------ intern

    /** Grobe Dubletten-Erkennung: gleiche Aussage nicht zweimal vorschlagen. */
    private function regelExistiertAehnlich(int $kontoId, string $text): bool
    {
        $neu = $this->normiere($text);
        foreach ($this->db->query(
            "SELECT regel_text FROM mail_gelernte_regeln WHERE konto_id = ? AND status <> 'verworfen'",
            [$kontoId]
        ) as $r) {
            $alt = $this->normiere((string) $r['regel_text']);
            if ($alt === $neu) return true;
            similar_text($alt, $neu, $prozent);
            if ($prozent > 85) return true;
        }
        return false;
    }

    private function normiere(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s, " \t\n\r.;:!?-");
    }

    /** Ein LLM-Aufruf, der zwingend JSON zurückliefern muss. */
    private function frageModell(array $settings, string $system, string $user, int $maxTokens): array
    {
        [$key, $provider, $modell] = $this->waehleModell($settings);
        if (!$key) throw new \RuntimeException('Kein API-Key (Anthropic/OpenAI) konfiguriert.');

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($key, $provider);
        $ai->setModel($modell);
        $ai->setMaxTokens($maxTokens);

        $antwort = $ai->chat([['role' => 'user', 'content' => $user]], $system);
        $inhalt = (string) ($antwort['content'] ?? '');

        if (!preg_match('/\{[\s\S]*\}/m', $inhalt, $m)) {
            // Kein schliessendes } => vermutlich am Token-Limit abgeschnitten.
            // Statt alles wegzuwerfen: die vollstaendigen Eintraege retten.
            $gerettet = $this->rettAusAbgeschnittenem($inhalt);
            if ($gerettet !== null) return $gerettet;
            throw new \RuntimeException('Modell lieferte kein JSON (Antwort evtl. abgeschnitten).');
        }

        $json = json_decode($m[0], true);
        if (is_array($json)) return $json;

        $gerettet = $this->rettAusAbgeschnittenem($inhalt);
        if ($gerettet !== null) return $gerettet;

        throw new \RuntimeException('JSON unlesbar.');
    }

    /**
     * Rettet vollstaendige Regel-Objekte aus einer abgeschnittenen JSON-Antwort.
     *
     * Wenn das Modell am Token-Limit mitten im letzten Eintrag abbricht, ist das GESAMTE
     * JSON unlesbar — obwohl die ersten zehn Regeln tadellos dastehen. Sie alle wegzuwerfen
     * waere Verschwendung. Also die kompletten {...}-Bloecke einzeln herausschneiden.
     *
     * @return array|null null, wenn sich nichts retten laesst
     */
    private function rettAusAbgeschnittenem(string $inhalt): ?array
    {
        if (!preg_match_all('/\{[^{}]*\}/s', $inhalt, $m)) return null;

        $regeln = [];
        foreach ($m[0] as $stueck) {
            $o = json_decode($stueck, true);
            if (is_array($o) && !empty($o['regel'])) $regeln[] = $o;
        }
        if (empty($regeln)) return null;

        error_log('MailLernService: JSON war abgeschnitten — ' . count($regeln) . ' vollständige Regeln gerettet.');
        return ['regeln' => $regeln];
    }

    private function waehleModell(array $settings): array
    {
        if (!empty($settings['anthropic_api_key'])) {
            return [$settings['anthropic_api_key'], 'anthropic', 'claude-opus-4-7'];
        }
        if (!empty($settings['openai_api_key'])) {
            return [$settings['openai_api_key'], 'openai', 'gpt-5.2'];
        }
        return [null, null, null];
    }
}
