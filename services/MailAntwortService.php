<?php
namespace Services;

use Core\Database;

/**
 * Versendet Antwort-Mails via SMTP (Symfony Mailer).
 * Prüft Stop-Wörter VOR jedem Versand, schreibt Audit-Eintrag in mail_antworten.
 *
 * Sicherheits-Schutzregeln:
 * 1. Stop-Wort in der Eingangs-Mail → Versand verweigert (auch wenn manuell)
 *    [Diskussion: oder nur Warnung? Erstmal Warnung, Versand bleibt möglich für Edge-Cases]
 * 2. Rate-Limit: max 50 Versendungen pro Konto pro Stunde
 * 3. Mailinglisten-Header (List-Id, List-Unsubscribe) → Versand verweigert
 * 4. Auto-Versand-Master-Switch global muss aktiv sein für `auto_versendet=1`
 */
class MailAntwortService
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
     * Sendet eine Antwort. Setzt Eingangs-Mail-Status auf 'beantwortet'.
     *
     * @param int $eingangMailId Auf welche Mail wird geantwortet
     * @param string $finalerText Body der Antwort (Plain-Text)
     * @param string $betreff Betreff
     * @param array $opts ['empfaenger'?, 'cc'?, 'bcc'?, 'ki_vorschlag'?, 'vorlage_id'?, 'auto_versendet'?]
     */
    public function sendeAntwort(int $eingangMailId, string $finalerText, string $betreff, array $opts = []): array
    {
        $eingang = $this->db->queryOne(
            "SELECT * FROM mail_nachrichten WHERE id = ?",
            [$eingangMailId]
        );
        if (!$eingang) throw new \InvalidArgumentException('Eingangs-Mail nicht gefunden.');

        $konto = $this->konten->getZugangsdaten((int)$eingang['konto_id']);
        if (empty($konto['smtp_host']) || empty($konto['smtp_password'])) {
            throw new \RuntimeException('SMTP nicht konfiguriert.');
        }

        // Stop-Wort-Check (Warnung in Antwort, Versand wird aber durchgelassen, wenn manuell)
        $stopWortGefunden = $this->pruefeStopWoerter($eingang);
        $autoVersendet = !empty($opts['auto_versendet']);
        if ($autoVersendet) {
            // Master-Switch global muss aktiv sein
            if ((string)\Core\Settings::get('mail_auto_versand_global_aktiv', '0') !== '1') {
                throw new \RuntimeException('Auto-Versand ist global deaktiviert.');
            }
            // Konto-Schalter muss aktiv sein
            if (!$konto['auto_antwort_aktiv']) {
                throw new \RuntimeException('Auto-Versand ist für dieses Konto deaktiviert.');
            }
            // Stop-Wort → Auto-Versand NIE
            if ($stopWortGefunden) {
                throw new \RuntimeException('Stop-Wort gefunden — Auto-Versand verweigert: ' . $stopWortGefunden);
            }
            // Mailinglisten-Header → Auto-Versand NIE
            if ($this->istMailingliste($eingang)) {
                throw new \RuntimeException('Eingangs-Mail kommt von Mailingliste — Auto-Versand verweigert.');
            }
        }

        // Rate-Limit: max 50 Versendungen pro Konto pro Stunde
        $letztesStundeVersand = (int)$this->db->queryValue(
            "SELECT COUNT(*) FROM mail_antworten a
             JOIN mail_nachrichten n ON n.id = a.ausgang_mail_id
             WHERE n.konto_id = ? AND a.versendet_am > NOW() - INTERVAL 1 HOUR",
            [$konto['id']]
        );
        if ($letztesStundeVersand >= 50) {
            throw new \RuntimeException('Rate-Limit erreicht: 50 Versendungen/h pro Konto.');
        }

        $empfaenger = $opts['empfaenger'] ?? $eingang['absender_email'];
        $cc = $opts['cc'] ?? [];
        $bcc = $opts['bcc'] ?? [];

        // Signatur anhängen, falls vorhanden
        $bodyMitSignatur = trim($finalerText);
        if (!empty($konto['signatur'])) {
            $bodyMitSignatur .= "\n\n-- \n" . trim($konto['signatur']);
        }

        // Versenden via Symfony Mailer
        // Transport zentral bauen — deckt Passwort UND Microsoft 365 (XOAUTH2) ab.
        // Der frühere DSN-Weg konnte kein OAuth2 und haette bei Exchange Online versagt.
        $transport = $this->konten->smtpTransport($konto);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($konto['email_adresse'], $konto['name'] ?? ''))
            ->to($empfaenger)
            ->subject($betreff)
            ->text($bodyMitSignatur);

        foreach ($cc as $c) $email->addCc($c);
        foreach ($bcc as $b) $email->addBcc($b);

        // Anhänge dranhängen (Pfade aus mail-attachments/ oder upload)
        if (!empty($opts['anhaenge']) && is_array($opts['anhaenge'])) {
            foreach ($opts['anhaenge'] as $a) {
                if (empty($a['pfad']) || !is_readable($a['pfad'])) continue;
                $email->attachFromPath($a['pfad'], $a['name'] ?? basename($a['pfad']), $a['mime'] ?? null);
            }
        }

        // In-Reply-To für Threading
        if (!empty($eingang['message_id'])) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $eingang['message_id']);
            $email->getHeaders()->addTextHeader('References', '<' . $eingang['message_id'] . '>');
        }

        $mailer->send($email);

        // Eintrag in mail_nachrichten (richtung=ausgang)
        $this->db->execute(
            "INSERT INTO mail_nachrichten
                (konto_id, richtung, in_reply_to, absender_email, empfaenger_email,
                 cc_emails, betreff, body_plain, empfangen_am, quelle, status, gelesen)
             VALUES (?, 'ausgang', ?, ?, ?, ?, ?, ?, NOW(), 'versand', 'archiviert', 1)",
            [
                $konto['id'],
                $eingang['message_id'] ?: null,
                $konto['email_adresse'],
                $empfaenger,
                implode(',', $cc),
                mb_substr($betreff, 0, 500),
                $bodyMitSignatur,
            ]
        );
        $ausgangId = (int)$this->db->queryValue("SELECT LAST_INSERT_ID()");

        $wurdeEditiert = !empty($opts['ki_vorschlag']) && trim((string)$opts['ki_vorschlag']) !== trim($finalerText);

        $this->db->execute(
            "INSERT INTO mail_antworten
                (eingang_mail_id, ausgang_mail_id, vorlage_id, ki_vorschlag, finaler_text,
                 wurde_editiert, versendet_am, versendet_von_user_id, auto_versendet)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)",
            [
                $eingangMailId, $ausgangId,
                !empty($opts['vorlage_id']) ? (int)$opts['vorlage_id'] : null,
                $opts['ki_vorschlag'] ?? null,
                $finalerText,
                $wurdeEditiert ? 1 : 0,
                $opts['user_id'] ?? null,
                $autoVersendet ? 1 : 0,
            ]
        );

        // Eingangs-Mail als beantwortet markieren
        $this->db->execute(
            "UPDATE mail_nachrichten SET status = 'beantwortet' WHERE id = ?",
            [$eingangMailId]
        );

        // Wenn an Anbieter via Korrespondenz verknüpft: Eintrag mail_ausgang in lam_kommunikation
        $korr = $this->db->queryOne(
            "SELECT ziel_id FROM mail_lam_verknuepfung WHERE mail_id = ? AND typ = 'anbieter' LIMIT 1",
            [$eingangMailId]
        );
        if ($korr) {
            $this->legeKorrespondenzAusgang($eingangMailId, $ausgangId, $korr['ziel_id'], $betreff, $bodyMitSignatur, $empfaenger);
        }

        return [
            'ausgang_mail_id' => $ausgangId,
            'stop_wort_gefunden' => $stopWortGefunden,
            'rate_limit_count' => $letztesStundeVersand + 1,
        ];
    }

    /**
     * Neue Mail komplett ohne Bezug zu einer Eingangs-Mail versenden.
     * Wird vom LAM-Modul aus aufgerufen (Compose-Modal in Anbieter/Kontakt/Maßnahme).
     *
     * @param array $opts {
     *     konto_id: int (Pflicht),
     *     empfaenger: string|array,
     *     betreff: string,
     *     text: string,
     *     cc?: array,
     *     bcc?: array,
     *     anbieter_id?: string,        // LAM-Verknüpfung
     *     kontakt_id?: string,         // optional: speichert kontakt_id im lam_kommunikation
     *     massnahme_id?: string,       // optional
     *     vorschlagsliste_eintrag_id?: string, // optional (Linkoption)
     *     anhaenge?: array,
     *     user_id?: int,
     * }
     * @return array { ausgang_mail_id, kommunikation_id?: string }
     */
    public function sendeNeueMail(array $opts): array
    {
        $kontoId = (int)($opts['konto_id'] ?? 0);
        $empfaenger = $opts['empfaenger'] ?? '';
        $betreff = trim((string)($opts['betreff'] ?? ''));
        $text = trim((string)($opts['text'] ?? ''));
        if ($kontoId <= 0)        throw new \InvalidArgumentException('konto_id erforderlich.');
        if (empty($empfaenger))   throw new \InvalidArgumentException('Empfänger erforderlich.');
        if ($betreff === '')      throw new \InvalidArgumentException('Betreff erforderlich.');
        if ($text === '')         throw new \InvalidArgumentException('Mail-Text erforderlich.');

        $konto = $this->konten->getZugangsdaten($kontoId);
        if (empty($konto['smtp_host']) || empty($konto['smtp_password'])) {
            throw new \RuntimeException('SMTP nicht konfiguriert.');
        }

        $cc = $opts['cc'] ?? [];
        $bcc = $opts['bcc'] ?? [];

        $bodyMitSignatur = $text;
        if (!empty($konto['signatur'])) {
            $bodyMitSignatur .= "\n\n-- \n" . trim($konto['signatur']);
        }

        // SMTP-Versand
        // Transport zentral bauen — deckt Passwort UND Microsoft 365 (XOAUTH2) ab.
        // Der frühere DSN-Weg konnte kein OAuth2 und haette bei Exchange Online versagt.
        $transport = $this->konten->smtpTransport($konto);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($konto['email_adresse'], $konto['name'] ?? ''))
            ->subject($betreff)
            ->text($bodyMitSignatur);

        // Empfänger: String oder Array
        if (is_array($empfaenger)) {
            foreach ($empfaenger as $e) $email->addTo($e);
            $empfaengerStr = implode(', ', $empfaenger);
        } else {
            $email->to((string)$empfaenger);
            $empfaengerStr = (string)$empfaenger;
        }

        foreach ($cc as $c) $email->addCc($c);
        foreach ($bcc as $b) $email->addBcc($b);

        if (!empty($opts['anhaenge']) && is_array($opts['anhaenge'])) {
            foreach ($opts['anhaenge'] as $a) {
                if (empty($a['pfad']) || !is_readable($a['pfad'])) continue;
                $email->attachFromPath($a['pfad'], $a['name'] ?? basename($a['pfad']), $a['mime'] ?? null);
            }
        }

        $mailer->send($email);

        // In mail_nachrichten als Ausgang erfassen
        $this->db->execute(
            "INSERT INTO mail_nachrichten
                (konto_id, richtung, absender_email, empfaenger_email,
                 cc_emails, betreff, body_plain, empfangen_am, quelle, status, gelesen)
             VALUES (?, 'ausgang', ?, ?, ?, ?, ?, NOW(), 'versand', 'archiviert', 1)",
            [
                $konto['id'],
                $konto['email_adresse'],
                $empfaengerStr,
                implode(',', $cc),
                mb_substr($betreff, 0, 500),
                $bodyMitSignatur,
            ]
        );
        $ausgangId = (int)$this->db->queryValue("SELECT LAST_INSERT_ID()");

        // Verknüpfung anlegen
        $kommunikationId = null;
        $anbieterId = trim((string)($opts['anbieter_id'] ?? ''));
        if ($anbieterId !== '') {
            $this->db->execute(
                "INSERT INTO mail_lam_verknuepfung (mail_id, typ, ziel_id, automatisch) VALUES (?, 'anbieter', ?, 0)",
                [$ausgangId, $anbieterId]
            );

            require_once SERVICES_PATH . '/LamService.php';
            $svc = new \Services\LamService($this->db);
            $kommunikationId = $svc->ulid();
            $this->db->execute(
                "INSERT INTO lam_kommunikation
                    (id, anbieter_id, kontakt_id, massnahme_id, vorschlagsliste_eintrag_id,
                     typ, zeitpunkt, betreff, inhalt,
                     absender_mail, empfaenger_mail, mail_id_extern, user_id, status, erstellt_am)
                 VALUES (?, ?, ?, ?, ?, 'mail_ausgang', NOW(), ?, ?, ?, ?, ?, ?, 'gesendet', NOW())",
                [
                    $kommunikationId,
                    $anbieterId,
                    !empty($opts['kontakt_id']) ? $opts['kontakt_id'] : null,
                    !empty($opts['massnahme_id']) ? $opts['massnahme_id'] : null,
                    !empty($opts['vorschlagsliste_eintrag_id']) ? $opts['vorschlagsliste_eintrag_id'] : null,
                    mb_substr($betreff, 0, 255),
                    mb_substr($bodyMitSignatur, 0, 5000),
                    $konto['email_adresse'],
                    $empfaengerStr,
                    (string)$ausgangId,
                    !empty($opts['user_id']) ? (int)$opts['user_id'] : null,
                ]
            );
        }

        return [
            'ausgang_mail_id' => $ausgangId,
            'kommunikation_id' => $kommunikationId,
        ];
    }

    /**
     * Eine bestehende Mail weiterleiten: Begleittext + zitiertes Original,
     * optional mit den Original-Anhaengen.
     *
     * Baut den Weiterleitungs-Body (Begleittext + „Urspruengliche Nachricht"-Block) und
     * uebergibt ihn an sendeNeueMail(). Die Original-Anhaenge werden aus ihren gespeicherten
     * Pfaden uebernommen — es wird nichts neu hochgeladen.
     *
     * @param array $opts { konto_id, empfaenger, betreff, begleittext, cc?, bcc?,
     *                       anhang_ids?: int[] (welche Original-Anhaenge mit), user_id? }
     */
    public function sendeWeiterleitung(int $originalMailId, array $opts): array
    {
        $orig = $this->db->queryOne("SELECT * FROM mail_nachrichten WHERE id = ?", [$originalMailId]);
        if (!$orig) throw new \InvalidArgumentException('Original-Mail nicht gefunden.');

        $begleit = trim((string) ($opts['begleittext'] ?? ''));

        // Zitat-Block wie in Outlook — der Original-Text als klar abgesetzter Vorgaenger.
        $zitat = "\n\n---------- Ursprüngliche Nachricht ----------\n"
               . "Von: " . trim(($orig['absender_name'] ?? '') . ' <' . ($orig['absender_email'] ?? '') . '>') . "\n"
               . "Datum: " . (string) $orig['empfangen_am'] . "\n"
               . "Betreff: " . (string) $orig['betreff'] . "\n\n"
               . trim((string) $orig['body_plain']);

        // Nur die ausgewaehlten Original-Anhaenge uebernehmen (Default: alle).
        $anhaenge = [];
        $ausgewaehlt = $opts['anhang_ids'] ?? null;   // null => alle
        foreach ($this->db->query("SELECT * FROM mail_anhaenge WHERE mail_id = ?", [$originalMailId]) as $a) {
            if (is_array($ausgewaehlt) && !in_array((int) $a['id'], array_map('intval', $ausgewaehlt), true)) continue;
            $pfad = str_starts_with((string) $a['pfad'], '/') ? $a['pfad'] : ROOT_PATH . '/' . ltrim($a['pfad'], '/');
            if (is_readable($pfad)) {
                $anhaenge[] = ['pfad' => $pfad, 'name' => $a['dateiname'], 'mime' => $a['mime_typ']];
            }
        }

        $betreff = trim((string) ($opts['betreff'] ?? ''));
        if ($betreff === '') {
            $b = (string) $orig['betreff'];
            $betreff = preg_match('/^(fwd|wg):/i', $b) ? $b : 'Fwd: ' . $b;
        }

        return $this->sendeNeueMail([
            'konto_id'   => (int) ($opts['konto_id'] ?? $orig['konto_id']),
            'empfaenger' => $opts['empfaenger'] ?? '',
            'betreff'    => $betreff,
            'text'       => $begleit . $zitat,
            'cc'         => $opts['cc'] ?? [],
            'bcc'        => $opts['bcc'] ?? [],
            'anhaenge'   => $anhaenge,
            'user_id'    => $opts['user_id'] ?? null,
        ]);
    }

    private function bauDsn(array $konto): string
    {
        $user = urlencode($konto['smtp_username']);
        $pw = urlencode($konto['smtp_password']);
        $host = $konto['smtp_host'];
        $port = (int)$konto['smtp_port'];
        $enc = $konto['smtp_encryption'];
        // smtp:// vs smtps:// — symfony nutzt smtps für SSL, smtp für plain/STARTTLS
        $scheme = $enc === 'ssl' ? 'smtps' : 'smtp';
        return "{$scheme}://{$user}:{$pw}@{$host}:{$port}";
    }

    public function pruefeStopWoerter(array $eingang): ?string
    {
        $stop = (string)\Core\Settings::get('mail_stop_woerter', 'Anwalt,Klage,Datenschutz,GDPR,Abmahnung,Reklamation,Beschwerde,Inkasso');
        $worte = array_filter(array_map('trim', explode(',', $stop)));
        $haystack = mb_strtolower(($eingang['betreff'] ?? '') . ' ' . ($eingang['body_plain'] ?? ''));
        foreach ($worte as $w) {
            if (mb_strpos($haystack, mb_strtolower($w)) !== false) return $w;
        }
        return null;
    }

    public function istMailingliste(array $eingang): bool
    {
        // Prüfung im roh_eml_pfad (sofern abgelegt)
        if (!empty($eingang['roh_eml_pfad']) && is_readable($eingang['roh_eml_pfad'])) {
            $raw = file_get_contents($eingang['roh_eml_pfad'], false, null, 0, 5000);
            if ($raw && (stripos($raw, 'List-Id:') !== false || stripos($raw, 'List-Unsubscribe:') !== false)) {
                return true;
            }
        }
        return false;
    }

    private function legeKorrespondenzAusgang(int $eingangId, int $ausgangId, string $anbieterId, string $betreff, string $body, string $empfaenger): void
    {
        require_once SERVICES_PATH . '/LamService.php';
        $svc = new LamService($this->db);
        $this->db->execute(
            "INSERT INTO lam_kommunikation
                (id, anbieter_id, typ, zeitpunkt, betreff, inhalt,
                 absender_mail, empfaenger_mail, mail_id_extern, status, erstellt_am)
             VALUES (?, ?, 'mail_ausgang', NOW(), ?, ?, ?, ?, ?, 'gesendet', NOW())",
            [
                $svc->ulid(), $anbieterId,
                mb_substr($betreff, 0, 255), mb_substr($body, 0, 5000),
                $this->konten->getZugangsdaten((int)$this->db->queryValue("SELECT konto_id FROM mail_nachrichten WHERE id = ?", [$ausgangId]))['email_adresse'],
                $empfaenger,
                (string) $ausgangId,
            ]
        );
    }
}
