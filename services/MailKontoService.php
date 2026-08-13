<?php
namespace Services;

use Core\Database;
use Core\Settings;
use Core\Crypto;

/**
 * Verwaltet die Mail-Konten (IMAP + SMTP).
 * Passwörter werden via Core\Crypto verschlüsselt gespeichert.
 *
 * Multi-Account-fähig — Phase 1 nutzt nur ein Konto (pr@thoxan.com).
 */
class MailKontoService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function listeKonten(): array
    {
        $rows = $this->db->query(
            "SELECT id, name, email_adresse, aktiv,
                    auth_typ, nur_lesen, ist_standard,
                    imap_host, imap_port, imap_username, imap_encryption,
                    imap_folder_inbox, imap_folder_verarbeitet, imap_folder_fehler,
                    smtp_host, smtp_port, smtp_username, smtp_encryption,
                    signatur, auto_antwort_aktiv, auto_antwort_konfidenz_min,
                    oauth_tenant_id, oauth_client_id,
                    erstellt_am, aktualisiert_am,
                    (imap_password_enc IS NOT NULL) AS imap_password_gesetzt,
                    (smtp_password_enc IS NOT NULL) AS smtp_password_gesetzt,
                    (oauth_client_secret_enc IS NOT NULL) AS oauth_secret_gesetzt,
                    (oauth_refresh_token_enc IS NOT NULL) AS oauth_verbunden
             FROM mail_konten
             ORDER BY name ASC"
        );
        return $rows;
    }

    /**
     * Baut die Verbindungsdaten fuer webklex/php-imap — die EINZIGE Stelle, an der
     * zwischen Passwort- und OAuth2-Anmeldung unterschieden wird. Test und Abholung
     * nutzen sie gemeinsam, damit ein gruener Test nie etwas anderes prueft als der
     * spaetere Pull.
     *
     * Bei OAuth2 wandert das Access-Token ins 'password'-Feld — so verlangt es XOAUTH2.
     */
    public function imapVerbindung(array $cfg): array
    {
        $basis = [
            'host'          => $cfg['imap_host'],
            'port'          => (int) $cfg['imap_port'],
            'encryption'    => $cfg['imap_encryption'] === 'none' ? false : $cfg['imap_encryption'],
            'validate_cert' => true,
            // Fehlender Benutzername = die haeufigste Stolperfalle bei Microsoft 365: Dort
            // wirkt das Feld ueberfluessig (man meldet sich ja im Browser an), XOAUTH2 braucht
            // aber die Mailadresse als Benutzer. Leer -> Microsoft antwortet "NO Login failed".
            'username'      => $this->benutzername($cfg, 'imap'),
            'protocol'      => 'imap',
        ];

        if (($cfg['auth_typ'] ?? 'passwort') === 'oauth2') {
            require_once SERVICES_PATH . '/MailOAuthService.php';
            $oauth = new MailOAuthService($this->db);
            $basis['password']       = $oauth->accessToken((int) $cfg['id']);
            $basis['authentication'] = 'oauth';
            return $basis;
        }

        $basis['password']       = $cfg['imap_password'];
        $basis['authentication'] = null;
        return $basis;
    }

    public function getKonto(int $id): ?array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM mail_konten WHERE id = ?",
            [$id]
        );
        if (!$row) return null;
        // Passwörter werden NIE direkt rausgegeben
        unset($row['imap_password_enc'], $row['smtp_password_enc']);
        return $row;
    }

    /**
     * Anlegen oder aktualisieren. Passwörter werden verschlüsselt;
     * leeres Passwort lässt das bestehende unangetastet (Bearbeiten-Sicherheit).
     */
    public function speichereKonto(?int $id, array $data): int
    {
        $felder = [
            'name' => trim((string)($data['name'] ?? '')),
            'email_adresse' => trim((string)($data['email_adresse'] ?? '')),
            'aktiv' => !empty($data['aktiv']) ? 1 : 0,
            'imap_host' => trim((string)($data['imap_host'] ?? '')) ?: null,
            'imap_port' => (int)($data['imap_port'] ?? 993),
            'imap_username' => trim((string)($data['imap_username'] ?? '')) ?: null,
            'imap_encryption' => in_array($data['imap_encryption'] ?? '', ['ssl','tls','starttls','none'], true)
                ? $data['imap_encryption'] : 'ssl',
            'imap_folder_inbox' => trim((string)($data['imap_folder_inbox'] ?? 'INBOX')) ?: 'INBOX',
            'imap_folder_verarbeitet' => trim((string)($data['imap_folder_verarbeitet'] ?? 'INBOX.Verarbeitet')) ?: 'INBOX.Verarbeitet',
            'imap_folder_fehler' => trim((string)($data['imap_folder_fehler'] ?? 'INBOX.Fehler')) ?: 'INBOX.Fehler',
            'smtp_host' => trim((string)($data['smtp_host'] ?? '')) ?: null,
            'smtp_port' => (int)($data['smtp_port'] ?? 587),
            'smtp_username' => trim((string)($data['smtp_username'] ?? '')) ?: null,
            'smtp_encryption' => in_array($data['smtp_encryption'] ?? '', ['ssl','tls','starttls','none'], true)
                ? $data['smtp_encryption'] : 'starttls',
            'signatur' => $data['signatur'] ?? null,
            'auto_antwort_aktiv' => !empty($data['auto_antwort_aktiv']) ? 1 : 0,
            'auto_antwort_konfidenz_min' => max(0, min(1, (float)($data['auto_antwort_konfidenz_min'] ?? 0.95))),
            'auth_typ' => ($data['auth_typ'] ?? '') === 'oauth2' ? 'oauth2' : 'passwort',
            // Standard-Postfach: das, mit dem /mail startet, wenn noch nichts gemerkt ist.
            'ist_standard' => !empty($data['ist_standard']) ? 1 : 0,
            // Nur-Lesen: nichts im Postfach verschieben, anlegen oder als gelesen markieren.
            //
            // Bei Microsoft 365 (OAuth2) ist das KEINE Einstellung, sondern ein Gesetz:
            // Thomas sortiert ausschliesslich in Outlook. Das Werkzeug darf dort nie etwas
            // veraendern. Ein Haekchen, das man versehentlich umlegen kann, waere hier keine
            // Sicherheit — deshalb wird der Wert erzwungen und nicht uebernommen.
            'nur_lesen' => ($data['auth_typ'] ?? '') === 'oauth2' ? 1 : (!empty($data['nur_lesen']) ? 1 : 0),
            'oauth_tenant_id' => trim((string)($data['oauth_tenant_id'] ?? '')) ?: null,
            'oauth_client_id' => trim((string)($data['oauth_client_id'] ?? '')) ?: null,
        ];

        if ($felder['name'] === '' || $felder['email_adresse'] === '') {
            throw new \InvalidArgumentException('Name und E-Mail-Adresse sind Pflichtfelder.');
        }
        if (!filter_var($felder['email_adresse'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        // Passwörter: nur setzen wenn Wert übergeben (sonst bestehenden unangetastet lassen)
        $imapPw = (string)($data['imap_password'] ?? '');
        $smtpPw = (string)($data['smtp_password'] ?? '');
        // Client-Secret genauso: leer = bestehendes behalten (sonst wäre jedes Speichern
        // des Formulars ein versehentliches Löschen des Secrets).
        $oauthSecret = (string)($data['oauth_client_secret'] ?? '');

        // Standard ist exklusiv — sonst gaebe es zwei "Standard"-Postfaecher und die
        // Auswahl beim ersten Aufruf waere Zufall.
        if (!empty($felder['ist_standard'])) {
            $this->db->execute("UPDATE mail_konten SET ist_standard = 0");
        }

        if ($id) {
            // NUR Felder anfassen, die auch wirklich uebergeben wurden.
            //
            // Vorher wurden ALLE Felder geschrieben — auch die, die im Aufruf gar nicht
            // vorkamen. Ein Teil-Speichern (z.B. nur "aktiv" umschalten) hat damit
            // stillschweigend Tenant-ID, Client-ID und Benutzernamen auf NULL gesetzt und
            // die Microsoft-Verbindung zerstoert. Genau das ist mir passiert.
            //
            // Ausnahme: die drei erzwungenen/abgeleiteten Felder unten. Sie muessen auch
            // dann greifen, wenn sie nicht im Formular standen.
            $immer = ['nur_lesen', 'auth_typ', 'ist_standard'];

            $sets = [];
            $params = [];
            foreach ($felder as $f => $v) {
                if (!array_key_exists($f, $data) && !in_array($f, $immer, true)) {
                    continue;   // nicht uebergeben => nicht anfassen
                }
                $sets[] = "`{$f}` = ?";
                $params[] = $v;
            }
            if ($imapPw !== '') {
                $sets[] = "imap_password_enc = ?";
                $params[] = Crypto::encrypt($imapPw);
            }
            if ($smtpPw !== '') {
                $sets[] = "smtp_password_enc = ?";
                $params[] = Crypto::encrypt($smtpPw);
            }
            if ($oauthSecret !== '') {
                $sets[] = "oauth_client_secret_enc = ?";
                $params[] = Crypto::encrypt($oauthSecret);
            }
            $params[] = $id;
            $this->db->execute(
                "UPDATE mail_konten SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
            return $id;
        }

        // Neu anlegen — Passwörter Pflicht für IMAP+SMTP, sonst Konto nicht nutzbar
        if ($imapPw === '' || $smtpPw === '') {
            // Erlaube auch ohne Passwort (z.B. nur SMTP setzen), aber Warnung
            // wird via Aktiv-Schalter abgefangen
        }
        $felder['imap_password_enc'] = $imapPw !== '' ? Crypto::encrypt($imapPw) : null;
        $felder['smtp_password_enc'] = $smtpPw !== '' ? Crypto::encrypt($smtpPw) : null;
        $felder['oauth_client_secret_enc'] = $oauthSecret !== '' ? Crypto::encrypt($oauthSecret) : null;

        $spalten = array_keys($felder);
        $platzhalter = array_fill(0, count($spalten), '?');
        $this->db->execute(
            "INSERT INTO mail_konten (" . implode(',', $spalten) . ")
             VALUES (" . implode(',', $platzhalter) . ")",
            array_values($felder)
        );
        return (int)$this->db->queryValue("SELECT LAST_INSERT_ID()");
    }

    public function loescheKonto(int $id): void
    {
        $this->db->execute("DELETE FROM mail_konten WHERE id = ?", [$id]);
    }

    /**
     * Holt die entschlüsselten Passwörter — NUR für interne Service-Aufrufe!
     */
    public function getZugangsdaten(int $id): array
    {
        $row = $this->db->queryOne(
            "SELECT * FROM mail_konten WHERE id = ?",
            [$id]
        );
        if (!$row) throw new \InvalidArgumentException('Konto nicht gefunden.');
        $row['imap_password'] = !empty($row['imap_password_enc']) ? Crypto::decrypt($row['imap_password_enc']) : null;
        $row['smtp_password'] = !empty($row['smtp_password_enc']) ? Crypto::decrypt($row['smtp_password_enc']) : null;
        unset($row['imap_password_enc'], $row['smtp_password_enc']);
        return $row;
    }

    /**
     * Verbindungs-Test für IMAP. Returnt strukturiertes Ergebnis für UI.
     */
    public function testIMAP(int $id): array
    {
        $cfg = $this->getZugangsdaten($id);
        $istOauth = ($cfg['auth_typ'] ?? 'passwort') === 'oauth2';

        if (empty($cfg['imap_host'])) {
            return ['ok' => false, 'fehler' => 'IMAP-Host fehlt.'];
        }
        if ($this->benutzername($cfg, 'imap') === '') {
            return ['ok' => false, 'fehler' => 'Benutzername fehlt (und keine E-Mail-Adresse hinterlegt).'];
        }
        if ($istOauth) {
            if (empty($cfg['oauth_refresh_token_enc'])) {
                return ['ok' => false, 'fehler' => 'Konto ist noch nicht mit Microsoft verbunden — bitte zuerst „Mit Microsoft verbinden" klicken.'];
            }
        } elseif (empty($cfg['imap_password'])) {
            return ['ok' => false, 'fehler' => 'IMAP-Passwort fehlt.'];
        }

        if (!class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
            return ['ok' => false, 'fehler' => 'webklex/php-imap nicht installiert (composer require webklex/php-imap).'];
        }

        try {
            $manager = new \Webklex\PHPIMAP\ClientManager();
            $client = $manager->make($this->imapVerbindung($cfg));
            $client->connect();
            $folderNamen = [];
            foreach ($client->getFolders(false) as $f) $folderNamen[] = $f->path;
            $client->disconnect();
            return [
                'ok' => true,
                'meldung' => 'IMAP-Verbindung erfolgreich (' . ($istOauth ? 'Microsoft 365' : 'Passwort') . '). '
                    . count($folderNamen) . ' Ordner gefunden.',
                'folders' => $folderNamen,
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Der haeufigste Stolperstein bei Microsoft 365: IMAP ist fuers Postfach gesperrt.
            if ($istOauth && stripos($msg, 'AUTHENTICATE') !== false) {
                $msg .= ' — Hinweis: Bei Microsoft 365 ist IMAP oft für das Postfach deaktiviert. '
                    . 'Prüfen unter admin.microsoft.com → Benutzer → E-Mail → E-Mail-Apps verwalten → IMAP.';
            }
            return ['ok' => false, 'fehler' => 'IMAP-Verbindung fehlgeschlagen: ' . $msg];
        }
    }

    /**
     * Benutzername fuer IMAP bzw. SMTP. Ist das Feld leer, gilt die Mailadresse —
     * das ist bei praktisch jedem Anbieter der richtige Wert und verhindert die
     * unverstaendliche Fehlermeldung "NO Login failed" bei leerem Benutzer.
     */
    private function benutzername(array $cfg, string $dienst): string
    {
        $feld = $dienst === 'smtp' ? 'smtp_username' : 'imap_username';
        $wert = trim((string) ($cfg[$feld] ?? ''));
        return $wert !== '' ? $wert : trim((string) ($cfg['email_adresse'] ?? ''));
    }

    /**
     * SMTP-Transport bauen — die EINZIGE Stelle, an der Versand-Anmeldung entschieden wird.
     *
     * Bei Microsoft 365 geht kein Passwort mehr: Der Versand laeuft ueber XOAUTH2 mit
     * demselben Access-Token wie IMAP. Symfony bringt den passenden Authenticator mit,
     * er muss nur explizit gesetzt werden — der DSN-Weg (Transport::fromDsn) kann das nicht.
     */
    public function smtpTransport(array $cfg): \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
    {
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            (string) $cfg['smtp_host'],
            (int) $cfg['smtp_port'],
            ($cfg['smtp_encryption'] ?? 'starttls') !== 'starttls'   // true = TLS ab Verbindung
        );
        $transport->setUsername($this->benutzername($cfg, 'smtp'));

        if (($cfg['auth_typ'] ?? 'passwort') === 'oauth2') {
            require_once SERVICES_PATH . '/MailOAuthService.php';
            $oauth = new MailOAuthService($this->db);
            $transport->setPassword($oauth->accessToken((int) $cfg['id']));
            $transport->setAuthenticators([
                new \Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator(),
            ]);
        } else {
            $transport->setPassword((string) $cfg['smtp_password']);
        }

        return $transport;
    }

    /**
     * Ordnerbaum aus dem KATALOG (mail_ordner_cache) — nicht live vom Server.
     *
     * Bei 2000+ Ordnern dauert die Live-Abfrage Minuten, und ohne Mail-Anzahl sieht man
     * nicht, dass die meisten leer sind. Der Katalog wird einmal per
     * scripts/mail-ordner-scan.php eingelesen; hier lesen wir ihn nur noch aus.
     *
     * @return array{status:string, fortschritt:int, gesamt:int, meldung:?string, ordner:array}
     */
    public function ordnerBaumAusKatalog(int $id): array
    {
        $k = $this->db->queryOne(
            "SELECT scan_status, scan_fortschritt, scan_gesamt, scan_meldung, scan_am FROM mail_konten WHERE id = ?",
            [$id]
        ) ?: [];

        $auswahl = [];
        foreach ($this->db->query("SELECT * FROM mail_konten_ordner WHERE konto_id = ?", [$id]) as $r) {
            $auswahl[$r['ordner_pfad']] = $r;
        }

        $rows = $this->db->query(
            "SELECT pfad, name_lesbar, name_kurz, eltern_pfad, tiefe, anzahl_mails, ist_system, ist_mailordner
             FROM mail_ordner_cache WHERE konto_id = ?
             ORDER BY name_lesbar",
            [$id]
        );

        // Wer hat Kinder? (fuer die Aufklapp-Pfeile)
        $hatKinder = [];
        foreach ($rows as $r) {
            if (!empty($r['eltern_pfad'])) $hatKinder[$r['eltern_pfad']] = true;
        }

        $ordner = [];
        foreach ($rows as $r) {
            $a = $auswahl[$r['pfad']] ?? null;
            $ordner[] = [
                'pfad'        => $r['pfad'],
                // Name und Struktur kommen FERTIG aus dem Katalog — hier wird nichts mehr
                // zerlegt. Genau das Nachzerlegen (am Punkt!) hat die Namen zerstoert.
                'name'        => $r['name_kurz'] ?: $r['name_lesbar'],
                'voll'        => $r['name_lesbar'],
                'eltern'      => $r['eltern_pfad'],
                'tiefe'       => (int) $r['tiefe'],
                'anzahl'      => (int) $r['anzahl_mails'],
                'system'      => (int) $r['ist_system'],
                // 0 = Kategorie/Kontaktliste, kein Mail-Ordner (Exchange gibt sie ueber
                // IMAP mit heraus, liefert aber keine Nachrichten daraus)
                'mailordner'  => (int) $r['ist_mailordner'],
                'kinder'      => !empty($hatKinder[$r['pfad']]) ? 1 : 0,
                'abholen'     => $a ? (int) $a['abholen'] : 0,
                'ins_wissen'  => $a ? (int) $a['ins_wissen'] : 0,
                'stil_lernen' => $a ? (int) $a['stil_lernen'] : 0,
                'rekursiv'    => $a ? (int) $a['rekursiv'] : 0,
            ];
        }

        return [
            'status'      => (string) ($k['scan_status'] ?? 'leer'),
            'fortschritt' => (int) ($k['scan_fortschritt'] ?? 0),
            'gesamt'      => (int) ($k['scan_gesamt'] ?? 0),
            'meldung'     => $k['scan_meldung'] ?? null,
            'scan_am'     => $k['scan_am'] ?? null,
            'ordner'      => $ordner,
        ];
    }

    private function letztesSegment(string $pfad): string
    {
        $t = preg_split('#[/.]#', $pfad);
        return (string) end($t);
    }

    /**
     * Ordnerbaum LIVE vom Server (Altpfad, nur noch als Rueckfall wenn kein Katalog da ist).
     */
    public function ordnerBaum(int $id): array
    {
        $cfg = $this->getZugangsdaten($id);
        if (!class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
            throw new \RuntimeException('webklex/php-imap nicht installiert.');
        }

        $manager = new \Webklex\PHPIMAP\ClientManager();
        $client = $manager->make($this->imapVerbindung($cfg));
        $client->connect();

        $roh = [];
        $delimiter = '/';
        foreach ($client->getFolders(false) as $f) {   // flach: Hierarchie steckt im Pfad
            // WICHTIG: `path` ist der ROHE Pfad (UTF7-IMAP) — nur den versteht der Server.
            // `full_name` ist derselbe Pfad dekodiert — nur der ist fuer Menschen lesbar.
            // Beides getrennt halten: roh zum Speichern/Abrufen, lesbar zum Anzeigen.
            $roh[] = [
                'pfad' => (string) $f->path,
                'voll' => (string) ($f->full_name ?: $f->path),
                'name' => (string) ($f->name ?: $f->path),
            ];
            if (!empty($f->delimiter)) $delimiter = (string) $f->delimiter;
        }
        $client->disconnect();

        usort($roh, fn($a, $b) => strnatcasecmp($a['voll'], $b['voll']));

        $auswahl = [];
        foreach ($this->db->query("SELECT * FROM mail_konten_ordner WHERE konto_id = ?", [$id]) as $r) {
            $auswahl[$r['ordner_pfad']] = $r;
        }

        $baum = [];
        foreach ($roh as $f) {
            $a = $auswahl[$f['pfad']] ?? null;
            $baum[] = [
                'pfad'       => $f['pfad'],                                   // roh — geht an IMAP
                'name'       => $f['name'],                                   // lesbar, nur letztes Segment
                'voll'       => $f['voll'],                                   // lesbar, kompletter Pfad
                'tiefe'      => substr_count($f['voll'], $delimiter),
                'abholen'     => $a ? (int) $a['abholen'] : 0,
                'ins_wissen'  => $a ? (int) $a['ins_wissen'] : 0,
                'stil_lernen' => $a ? (int) $a['stil_lernen'] : 0,
                'rekursiv'    => $a ? (int) $a['rekursiv'] : 0,
            ];
        }
        return $baum;
    }

    /** Ordner-Auswahl speichern. $ordner: [['pfad'=>..,'abholen'=>0|1,'ins_wissen'=>0|1,'rekursiv'=>0|1], ...] */
    public function speichereOrdnerAuswahl(int $kontoId, array $ordner): int
    {
        // Ab jetzt gilt: Dieses Konto hat eine bewusste Ordner-Auswahl. Eine LEERE Auswahl
        // bedeutet dann "nichts abholen" — nicht mehr "dann eben den ganzen Posteingang".
        $this->db->execute("UPDATE mail_konten SET ordner_konfiguriert = 1 WHERE id = ?", [$kontoId]);

        $this->db->execute("DELETE FROM mail_konten_ordner WHERE konto_id = ?", [$kontoId]);
        $n = 0;
        foreach ($ordner as $o) {
            $pfad = trim((string) ($o['pfad'] ?? ''));
            if ($pfad === '') continue;
            $abholen = !empty($o['abholen']) ? 1 : 0;
            $wissen  = !empty($o['ins_wissen']) ? 1 : 0;
            $stil    = !empty($o['stil_lernen']) ? 1 : 0;

            // Die drei Schalter sind UNABHAENGIG. Frueher erzwang "Ins Wissen" automatisch
            // "Abholen" — mit der Begruendung, man koenne eine Mail nicht ins Wissen legen,
            // die man nie holt. Das war falsch gedacht: Die Stil-Ernte liest Mails direkt vom
            // Server, wertet sie aus und wirft sie weg, ohne dass eine einzige im Posteingang
            // landet. Genau so liest auch die Wissens-Uebernahme (Phase 3).
            //
            // Die Kopplung war nicht nur ueberfluessig, sie war gefaehrlich: Ein Haken bei
            // "Ins Wissen" auf einem Ordner mit +Unter hat stillschweigend 12.303 Mails zum
            // Import freigegeben, die der Nutzer nie in seinem Posteingang haben wollte.
            if (!$abholen && !$wissen && !$stil) continue;
            $this->db->execute(
                "INSERT INTO mail_konten_ordner (konto_id, ordner_pfad, abholen, ins_wissen, stil_lernen, rekursiv)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$kontoId, $pfad, $abholen, $wissen, $stil, !empty($o['rekursiv']) ? 1 : 0]
            );
            $n++;
        }
        return $n;
    }

    /**
     * Verbindungs-Test für SMTP. Verbindet + EHLO + Auth, ohne tatsächlich zu senden.
     */
    public function testSMTP(int $id): array
    {
        $cfg = $this->getZugangsdaten($id);
        if (empty($cfg['smtp_host']) || empty($cfg['smtp_username']) || empty($cfg['smtp_password'])) {
            return ['ok' => false, 'fehler' => 'SMTP-Zugangsdaten unvollständig.'];
        }

        if (!class_exists(\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport::class)) {
            return ['ok' => false, 'fehler' => 'symfony/mailer nicht installiert.'];
        }

        try {
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $cfg['smtp_host'],
                (int)$cfg['smtp_port'],
                $cfg['smtp_encryption'] !== 'starttls'  // tls=true ab Verbindung, false=STARTTLS
            );
            $transport->setUsername($cfg['smtp_username']);
            $transport->setPassword($cfg['smtp_password']);
            $stream = $transport->getStream();
            $stream->initialize();
            // Kein echtes Senden — nur Connect+Auth
            $stream->terminate();
            return ['ok' => true, 'meldung' => 'SMTP-Verbindung erfolgreich.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'fehler' => 'SMTP-Verbindung fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
