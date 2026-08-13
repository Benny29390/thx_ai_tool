<?php
namespace Services;

use Core\Database;
use Core\Crypto;

/**
 * OAuth2 (Microsoft Entra ID) fuer Exchange Online / Microsoft 365.
 *
 * Warum das noetig ist: Microsoft hat die Anmeldung per Benutzername/Passwort fuer
 * IMAP und SMTP abgeschaltet. Exchange Online akzeptiert nur noch XOAUTH2 mit einem
 * Bearer-Token. IMAP selbst funktioniert weiterhin — es aendert sich nur die Anmeldung.
 * Deshalb bleibt die gesamte bestehende Abhol-Pipeline erhalten.
 *
 * Ablauf:
 *   1. authorizeUrl()  -> Nutzer meldet sich einmalig im Browser bei Microsoft an
 *   2. tauscheCode()   -> Code gegen Access- + Refresh-Token tauschen (Refresh-Token bleibt)
 *   3. accessToken()   -> liefert ein gueltiges Token, erneuert es bei Bedarf automatisch
 *
 * Secrets (Client-Secret, Tokens) liegen AES-256-GCM-verschluesselt in der DB.
 */
class MailOAuthService
{
    private Database $db;

    /**
     * Scopes fuer IMAP/SMTP-Zugriff auf Exchange Online.
     *
     * Achtung, haeufige Fehlerquelle: Es muessen die Outlook-Ressourcen-Scopes sein
     * (https://outlook.office365.com/...), NICHT die Graph-Scopes (Mail.Read o.ae.).
     * Graph-Scopes liefern zwar ein Token, aber IMAP lehnt es mit "AUTHENTICATE failed" ab.
     * In der Entra-Oberflaeche werden die Berechtigungen trotzdem unter "Microsoft Graph"
     * ausgewaehlt — das ist normal.
     */
    private const SCOPES = 'offline_access https://outlook.office365.com/IMAP.AccessAsUser.All https://outlook.office365.com/SMTP.Send';

    /** Token vorsorglich 5 Minuten vor Ablauf erneuern (Uhren laufen nie exakt synchron). */
    private const REFRESH_PUFFER_SEK = 300;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function redirectUri(): string
    {
        return \Core\Brand::url('/api/v1/mail/oauth-callback');
    }

    /** Ist das Konto fertig verbunden (Refresh-Token vorhanden)? */
    public function istVerbunden(int $kontoId): bool
    {
        $row = $this->db->queryOne("SELECT oauth_refresh_token_enc FROM mail_konten WHERE id = ?", [$kontoId]);
        return !empty($row['oauth_refresh_token_enc']);
    }

    /**
     * Schritt 1: Adresse, auf die der Nutzer geschickt wird, um sich bei Microsoft anzumelden.
     * `state` bindet die Antwort ans Konto und schuetzt gegen untergeschobene Rueckleitungen.
     */
    public function authorizeUrl(int $kontoId, string $state): string
    {
        $k = $this->konto($kontoId);
        if (empty($k['oauth_tenant_id']) || empty($k['oauth_client_id'])) {
            throw new \RuntimeException('Tenant-ID und Client-ID müssen zuerst gespeichert werden.');
        }
        return 'https://login.microsoftonline.com/' . rawurlencode($k['oauth_tenant_id'])
            . '/oauth2/v2.0/authorize?' . http_build_query([
                'client_id'     => $k['oauth_client_id'],
                'response_type' => 'code',
                'redirect_uri'  => $this->redirectUri(),
                'response_mode' => 'query',
                'scope'         => self::SCOPES,
                'state'         => $state,
                // Erzwingt die Zustimmung, damit offline_access sicher erteilt wird.
                'prompt'        => 'consent',
            ]);
    }

    /** Schritt 2: Code gegen Tokens tauschen und speichern. */
    public function tauscheCode(int $kontoId, string $code): void
    {
        $k = $this->konto($kontoId);
        $antwort = $this->tokenRequest($k, [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->redirectUri(),
            'scope'        => self::SCOPES,
        ]);
        $this->speichereTokens($kontoId, $antwort);
    }

    /**
     * Schritt 3: Gueltiges Access-Token liefern. Erneuert automatisch, wenn es
     * abgelaufen ist oder in Kuerze ablaeuft.
     */
    public function accessToken(int $kontoId): string
    {
        $k = $this->konto($kontoId);
        if (empty($k['oauth_refresh_token_enc'])) {
            throw new \RuntimeException('Konto ist nicht mit Microsoft verbunden.');
        }

        $token   = !empty($k['oauth_access_token_enc']) ? Crypto::decrypt($k['oauth_access_token_enc']) : '';
        $laeuftAb = !empty($k['oauth_token_expires']) ? strtotime($k['oauth_token_expires']) : 0;

        if ($token !== '' && $laeuftAb > time() + self::REFRESH_PUFFER_SEK) {
            return $token;
        }

        // Erneuern
        $antwort = $this->tokenRequest($k, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => Crypto::decrypt($k['oauth_refresh_token_enc']),
            'scope'         => self::SCOPES,
        ]);
        $this->speichereTokens($kontoId, $antwort);
        return $antwort['access_token'];
    }

    /** Verbindung trennen (Tokens verwerfen; die App-Registrierung bleibt bestehen). */
    public function trenne(int $kontoId): void
    {
        $this->db->execute(
            "UPDATE mail_konten
             SET oauth_refresh_token_enc = NULL, oauth_access_token_enc = NULL, oauth_token_expires = NULL
             WHERE id = ?",
            [$kontoId]
        );
    }

    // ------------------------------------------------------------------ intern

    private function konto(int $id): array
    {
        $row = $this->db->queryOne("SELECT * FROM mail_konten WHERE id = ?", [$id]);
        if (!$row) throw new \InvalidArgumentException('Konto nicht gefunden.');
        return $row;
    }

    private function tokenRequest(array $k, array $felder): array
    {
        if (empty($k['oauth_client_secret_enc'])) {
            throw new \RuntimeException('Client-Secret fehlt.');
        }
        $payload = array_merge([
            'client_id'     => $k['oauth_client_id'],
            'client_secret' => Crypto::decrypt($k['oauth_client_secret_enc']),
        ], $felder);

        $url = 'https://login.microsoftonline.com/' . rawurlencode($k['oauth_tenant_id']) . '/oauth2/v2.0/token';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err !== '') {
            throw new \RuntimeException('Microsoft nicht erreichbar: ' . $err);
        }
        $data = json_decode((string) $resp, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Antwort von Microsoft unlesbar.');
        }
        if ($code !== 200 || empty($data['access_token'])) {
            // Microsofts Fehlertexte sind lang; die Beschreibung ist das Brauchbare.
            $msg = $data['error_description'] ?? ($data['error'] ?? 'unbekannter Fehler');
            throw new \RuntimeException('Microsoft lehnt ab (' . $code . '): ' . mb_substr((string) $msg, 0, 300));
        }
        return $data;
    }

    private function speichereTokens(int $kontoId, array $antwort): void
    {
        $sets   = ['oauth_access_token_enc = ?', 'oauth_token_expires = ?'];
        $params = [
            Crypto::encrypt((string) $antwort['access_token']),
            date('Y-m-d H:i:s', time() + (int) ($antwort['expires_in'] ?? 3600)),
        ];

        // Microsoft liefert beim Erneuern nicht immer ein neues Refresh-Token.
        // Dann behalten wir das alte — sonst waere die Verbindung nach einem Refresh tot.
        if (!empty($antwort['refresh_token'])) {
            $sets[]   = 'oauth_refresh_token_enc = ?';
            $params[] = Crypto::encrypt((string) $antwort['refresh_token']);
        }

        $params[] = $kontoId;
        $this->db->execute("UPDATE mail_konten SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }
}
