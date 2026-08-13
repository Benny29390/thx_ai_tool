<?php
namespace Services;

use Core\Database;

/**
 * Stil-Ernte: Lernt aus Thomas' EIGENEN Mails, wie er schreibt.
 *
 * Warum so: Thomas hat keinen gefuellten "Gesendet"-Ordner — er sortiert seine Antworten
 * in Themenordner, zwischen die eingegangenen Mails. Seine geschriebenen Mails sind also
 * ueber das ganze Postfach verstreut. Der gemeinsame Nenner ist der ABSENDER: Alles, was
 * von ihm kommt, hat er geschrieben.
 *
 * Deshalb wird nicht heruntergeladen und danach gefiltert, sondern der IMAP-Server sucht
 * selbst ("SEARCH FROM ..."). Das ist der Unterschied zwischen einem Suchbefehl und dem
 * Herunterladen von 2000 Ordnern.
 *
 * Die geernteten Mails landen NICHT im Posteingang. Sie dienen ausschliesslich als
 * Stilvorbild fuer die Antwort-Vorschlaege.
 */
class MailStilService
{
    private Database $db;
    private MailKontoService $konten;

    /**
     * Harte Ansage an jedes Modell: echte Umlaute.
     *
     * Grund: Die erste Fassung dieser Prompts war selbst in ae/oe/ue geschrieben (aus
     * uebertriebener Encoding-Vorsicht). Das Modell hat den Stil gespiegelt und alle Regeln
     * in ae/oe/ue ausgegeben — direkt gegen die Projektregel „echte Umlaute". Diese Zeile
     * steht deshalb in JEDEM Lern-Prompt.
     */
    public const UMLAUT_REGEL =
        "WICHTIG: Schreibe alle Ausgaben in korrektem Deutsch mit echten Umlauten (ä, ö, ü, Ä, Ö, Ü) "
        . "und ß. Verwende NIEMALS die Ersatzschreibweisen ae, oe, ue oder ss. "
        . "Höflichkeitsformen (Du, Dir, Dein, Sie, Ihnen) werden großgeschrieben.";

    /** So viele eigene Mails werden hoechstens ausgewertet (Kosten + Prompt-Groesse). */
    private const MAX_MAILS = 120;

    /** Je Ordner und Adresse hoechstens so viele holen — die Bibliothek laedt beim
     *  Abruf komplette Mail-Inhalte in den Speicher; 50 grosse HTML-Mails auf einmal
     *  sprengen bereits 256 MB. Lieber viele Ordner flach abgrasen als einen tief. */
    private const JE_ORDNER = 8;

    /** Hoechstens so viele Ordner besuchen — die groessten zuerst. Schuetzt vor Laeufen,
     *  die sich durch hunderte Unterordner fressen (genau daran ist der erste Versuch
     *  in den Timeout gelaufen). */
    private const MAX_ORDNER = 40;

    /** So viele davon gehen als Textproben ins Modell. */
    private const MAX_PROBEN = 40;

    /** Zeichen je Probe — mehr bringt fuer den Stil nichts, kostet aber. */
    private const PROBE_ZEICHEN = 1200;

    public function __construct(Database $db)
    {
        $this->db = $db;
        require_once SERVICES_PATH . '/MailKontoService.php';
        $this->konten = new MailKontoService($db);
    }

    /**
     * Eigene Mails aus den zum Lernen freigegebenen Ordnern ernten.
     * @return array<int,array{ordner:string,betreff:string,text:string,datum:?string}>
     */
    public function ernte(int $kontoId): array
    {
        $cfg = $this->konten->getZugangsdaten($kontoId);

        // Mehrere eigene Absender-Adressen sind der Normalfall, nicht die Ausnahme:
        // Thomas verschickt aus demselben Postfach unter thomas.kilian@ UND info@.
        // Wer nur die Konto-Adresse durchsucht, findet die Haelfte seiner Mails nicht.
        $adressen = $this->eigeneAdressen($cfg);
        if (empty($adressen)) {
            throw new \RuntimeException('Konto hat keine E-Mail-Adresse.');
        }

        // Ordnerliste kommt aus dem KATALOG, nicht live vom Server. Vorher lief die Ernte
        // in den Timeout, weil sie alle Unterordner einzeln beim Server erfragte — inklusive
        // hunderter leerer. Der Katalog kennt die Mail-Anzahl und wir besuchen nur Ordner,
        // in denen ueberhaupt etwas liegt — die groessten zuerst.
        $zuScannen = $this->zuScannendeOrdner($kontoId);
        if (empty($zuScannen)) {
            throw new \RuntimeException(
                'Kein Ordner zum Stil-Lernen freigegeben (oder die freigegebenen sind leer). '
                . 'Bitte in der Ordner-Auswahl „Stil lernen" ankreuzen.'
            );
        }

        $manager = new \Webklex\PHPIMAP\ClientManager();
        $client = $manager->make($this->konten->imapVerbindung($cfg));
        $client->connect();

        $funde = [];
        foreach ($zuScannen as $pfad) {
            if (count($funde) >= self::MAX_MAILS) break;
            foreach ($adressen as $adresse) {
                if (count($funde) >= self::MAX_MAILS) break;
                try {
                    $folder = $client->getFolderByPath($pfad);
                    if (!$folder) continue;

                    // Der Server sucht — wir laden nur die Treffer.
                    $treffer = $folder->messages()
                        ->from($adresse)
                        ->leaveUnread()                 // nichts als gelesen markieren
                        ->setFetchOrder('desc')         // die neuesten zuerst: aktueller Stil
                        ->limit(self::JE_ORDNER)
                        ->get();

                    foreach ($treffer as $m) {
                        if (count($funde) >= self::MAX_MAILS) break;

                        // Outlook verschickt REINES HTML — getTextBody() liefert bei Thomas'
                        // Mails ausnahmslos 0 Zeichen. Wer nur den Text-Teil liest, wirft
                        // praktisch das gesamte Lernmaterial weg (erster Lauf: 9 von 640).
                        $roh = (string) $m->getTextBody();
                        if (mb_strlen(trim($roh)) < 40) {
                            $roh = $this->htmlZuText((string) $m->getHTMLBody());
                        }

                        $text = $this->nurEigenerText($roh);
                        if (mb_strlen($text) < 80) continue;               // "Danke!" lehrt nichts
                        if (!$this->wirkichEigen($text, $adressen)) continue;  // Zitat der Gegenseite
                        $funde[] = [
                            'ordner'  => $pfad,
                            // Betreff kommt MIME-kodiert (=?utf-8?Q?…?=) — lesbar machen.
                            'betreff' => mb_decode_mimeheader((string) $m->getSubject()),
                            'text'    => $text,
                            'datum'   => $m->getDate() ? (string) $m->getDate() : null,
                        ];
                    }
                    unset($treffer);   // Speicher sofort freigeben (grosse HTML-Mails!)
                } catch (\Throwable $e) {
                    continue;   // ein einzelner Ordner darf den Lauf nicht killen
                }
            }
        }

        try { $client->disconnect(); } catch (\Throwable $e) {}
        return $funde;
    }

    /** Eigene Absender-Adressen: gepflegte Liste, sonst die Konto-Adresse. */
    private function eigeneAdressen(array $cfg): array
    {
        $roh = trim((string) ($cfg['eigene_adressen'] ?? ''));
        if ($roh === '') {
            $a = trim((string) ($cfg['email_adresse'] ?? ''));
            return $a !== '' ? [$a] : [];
        }
        $out = [];
        foreach (preg_split('/[,;\s]+/', $roh) as $a) {
            $a = trim($a);
            if ($a !== '') $out[] = $a;
        }
        return array_values(array_unique($out));
    }

    /**
     * Stilprofil erzeugen: Textproben ans Modell, heraus kommt eine Beschreibung,
     * die spaeter jedem Antwort-Prompt vorangestellt wird.
     */
    public function erzeugeProfil(int $kontoId, array $settings, ?array $funde = null): array
    {
        // Bereits geerntete Mails wiederverwenden — die IMAP-Suche ueber dutzende Ordner
        // ist der teuerste Teil des Laufs und darf nicht zweimal passieren.
        $funde = $funde ?? $this->ernte($kontoId);
        if (count($funde) < 5) {
            throw new \RuntimeException('Zu wenige eigene Mails gefunden (' . count($funde) . '). Bitte mehr Ordner zum Stil-Lernen freigeben.');
        }

        $proben = array_slice($funde, 0, self::MAX_PROBEN);
        $block = '';
        foreach ($proben as $i => $p) {
            $block .= "--- Beispiel " . ($i + 1) . " (Betreff: " . mb_substr($p['betreff'], 0, 90) . ")\n"
                    . mb_substr($p['text'], 0, self::PROBE_ZEICHEN) . "\n\n";
        }

        [$apiKey, $provider, $modell] = $this->waehleModell($settings);
        if (!$apiKey) {
            throw new \RuntimeException('Kein API-Key (Anthropic/OpenAI) konfiguriert.');
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, $provider);
        $ai->setModel($modell);
        $ai->setMaxTokens(2000);

        $system = "Du analysierst echte E-Mails EINER Person und beschreibst deren Schreibstil so genau, "
            . "dass eine KI künftig in ihrem Namen Antworten entwerfen kann.\n\n"
            . self::UMLAUT_REGEL . "\n\n"
            . "Beschreibe konkret und belegbar, nicht allgemein:\n"
            . "1. ANREDE: Duzt oder siezt sie? Wovon hängt es ab? Wie lautet die übliche Anrede?\n"
            . "2. TONALITÄT: knapp/ausführlich, sachlich/herzlich, direkt/vorsichtig?\n"
            . "3. AUFBAU: Wie beginnt sie? Wie kommt sie zum Punkt? Wie schließt sie?\n"
            . "4. TYPISCHE FORMULIERUNGEN: wörtliche Wendungen, die immer wiederkehren.\n"
            . "5. GRUSSFORMEL + SIGNATUR: wörtlich.\n"
            . "6. WAS SIE NIE TUT: Floskeln, Muster, die in den Beispielen auffällig fehlen.\n\n"
            . "Schreibe die Beschreibung als Anweisung an eine KI ('Schreibe wie folgt: ...'), "
            . "in Du-Form an die KI gerichtet. Keine Einleitung, keine Zusammenfassung am Ende. "
            . "Belege die wichtigsten Punkte mit kurzen Zitaten aus den Beispielen.";

        $antwort = $ai->chat(
            [['role' => 'user', 'content' => "Hier sind " . count($proben) . " echte Mails dieser Person:\n\n" . $block]],
            $system
        );
        $profil = trim((string) ($antwort['content'] ?? ''));
        if ($profil === '') {
            throw new \RuntimeException('Modell lieferte kein Stilprofil.');
        }

        // Nur ein aktives Profil je Konto — das alte bleibt als Historie erhalten.
        $this->db->execute("UPDATE mail_stilprofil SET aktiv = 0 WHERE konto_id = ?", [$kontoId]);
        $this->db->execute(
            "INSERT INTO mail_stilprofil (konto_id, profil_text, basis_anzahl, beispiele, aktiv)
             VALUES (?, ?, ?, ?, 1)",
            [
                $kontoId,
                $profil,
                count($funde),
                json_encode(array_map(fn($p) => [
                    'betreff' => $p['betreff'],
                    'text'    => mb_substr($p['text'], 0, 600),
                ], array_slice($proben, 0, 8)), JSON_UNESCAPED_UNICODE),
            ]
        );

        return [
            'profil'   => $profil,
            'gefunden' => count($funde),
            'genutzt'  => count($proben),
            'modell'   => $provider . '/' . $modell,
        ];
    }

    /** Aktives Stilprofil eines Kontos (fuer den Antwort-Prompt). */
    public function aktivesProfil(int $kontoId): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM mail_stilprofil WHERE konto_id = ? AND aktiv = 1 ORDER BY id DESC LIMIT 1",
            [$kontoId]
        ) ?: null;
    }

    // ------------------------------------------------------------------ intern

    /**
     * Welche Ordner werden nach eigenen Mails durchsucht?
     * Aus dem Katalog: nur freigegebene (inkl. Unterordner bei "rekursiv"), nur solche mit
     * Mails, keine Outlook-Systemordner — und die groessten zuerst, weil dort die meisten
     * Gespraeche liegen. Begrenzt, damit der Lauf nicht ausufert.
     *
     * @return string[] rohe IMAP-Pfade
     */
    private function zuScannendeOrdner(int $kontoId): array
    {
        $frei = $this->db->query(
            "SELECT ordner_pfad, rekursiv FROM mail_konten_ordner WHERE konto_id = ? AND stil_lernen = 1",
            [$kontoId]
        );
        if (empty($frei)) return [];

        $katalog = $this->db->query(
            "SELECT pfad, anzahl_mails FROM mail_ordner_cache
             WHERE konto_id = ? AND anzahl_mails > 0 AND ist_system = 0
             ORDER BY anzahl_mails DESC",
            [$kontoId]
        );
        if (empty($katalog)) return [];   // ohne Katalog kein Lauf — vorher Ordner einlesen

        // Trennzeichen vom Server (nicht raten — Ordnernamen enthalten Punkte!)
        $trenner = (string) ($this->db->queryValue(
            "SELECT ordner_trenner FROM mail_konten WHERE id = ?", [$kontoId]
        ) ?: '/');

        $out = [];
        foreach ($katalog as $k) {
            $pfad = (string) $k['pfad'];
            foreach ($frei as $f) {
                $basis = (string) $f['ordner_pfad'];
                if ($pfad === $basis) { $out[] = $pfad; break; }
                if (empty($f['rekursiv'])) continue;
                if (strpos($pfad, $basis) !== 0) continue;
                if (substr($pfad, strlen($basis), 1) === $trenner) { $out[] = $pfad; break; }
            }
            if (count($out) >= self::MAX_ORDNER) break;
        }
        return $out;
    }

    /**
     * Nur den selbst geschriebenen Teil behalten: zitierte Vorgaenger-Mail und Signatur raus.
     * Ohne das wuerde die KI den Stil der ANDEREN Person mitlernen — der zitierte Text
     * macht in einer Antwortmail oft 80 Prozent aus.
     */
    /**
     * HTML in lesbaren Text ueberfuehren.
     *
     * Script und Style muessen RAUS, sonst lernt die KI CSS-Regeln als Schreibstil.
     * Die Zitat-Erkennung arbeitet danach auf dem Text weiter — Outlook schreibt den
     * "Von: … Gesendet: … An:"-Block auch im HTML als lesbaren Text.
     */
    private function htmlZuText(string $html): string
    {
        if (trim($html) === '') return '';
        $t = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $t = preg_replace('#<br\s*/?>#i', "\n", $t) ?? $t;
        $t = preg_replace('#</(p|div|tr|h[1-6]|li)>#i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t) ?? $t;   // geschuetzte Leerzeichen mit
        $t = preg_replace("/\n[ \t]+/", "\n", $t) ?? $t;
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
        return trim($t);
    }

    private function nurEigenerText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);

        // Ab dem FRUEHESTEN Zitat-Marker abschneiden.
        //
        // Wichtig: frueher wurde je Marker einzeln geschnitten — dabei gewann der ZULETZT
        // gefundene, nicht der frueheste. Ergebnis: der zitierte Text der anderen Person
        // blieb stehen, und die KI haette deren Stil gelernt statt Thomas'.
        $marker = [
            '/^-{2,}\s*Urspr(ü|ue)ngliche Nachricht/mi',
            '/^-{2,}\s*Original Message/mi',
            '/^_{5,}\s*$/m',                       // Outlook-Trennlinie vor dem Zitat
            '/^\s*Von:\s.+$/mi',
            '/^\s*From:\s.+$/mi',
            '/^\s*Gesendet:\s.+$/mi',
            '/^\s*Sent:\s.+$/mi',
            '/^Am .+ schrieb .+:/mi',
            '/^On .+ wrote:/mi',
            '/^Gesendet von /mi',
            '/^Sent from /mi',
        ];
        $schnitt = mb_strlen($text);
        foreach ($marker as $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) {
                $pos = mb_strlen(substr($text, 0, $m[0][1]));
                if ($pos < $schnitt) $schnitt = $pos;   // der frueheste Marker gewinnt
            }
        }
        $text = mb_substr($text, 0, $schnitt);

        // Zitat-Zeilen (">") raus
        $text = implode("\n", array_filter(
            explode("\n", $text),
            fn($z) => !preg_match('/^\s*>/', $z)
        ));

        // Signatur ab "-- " abschneiden
        $text = preg_split('/^--\s*$/m', $text)[0] ?? $text;

        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Plausibilitaets-Pruefung: Ist der Text wirklich von Thomas — oder doch das Zitat
     * der Gegenseite? Eine Antwort, die mit "Hallo Thomas," beginnt, hat er nicht geschrieben.
     */
    private function wirkichEigen(string $text, array $adressen): bool
    {
        $anfang = mb_strtolower(mb_substr(trim($text), 0, 120));
        foreach (['thomas', 'herr kilian', 'hallo tk'] as $wort) {
            if (preg_match('/(hallo|hi|guten (tag|morgen)|lieber|sehr geehrter?)[^\n]{0,20}' . preg_quote($wort, '/') . '/u', $anfang)) {
                return false;   // jemand spricht THOMAS an => nicht von ihm
            }
        }
        return true;
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
