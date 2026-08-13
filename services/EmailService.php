<?php
/**
 * Email Service — Sendet HTML-Mails ueber SMTP (direkt, ohne externe Library)
 */

namespace Services;

use Core\Database;

class EmailService
{
    private string $fromEmail;
    private string $fromName;
    private string $appUrl;
    private array $smtp;

    public function __construct(string $appUrl, array $smtpConfig = [], string $fromEmail = '', string $fromName = '')
    {
        $this->appUrl = rtrim($appUrl, '/');
        $this->fromEmail = $fromEmail ?: ($smtpConfig['from_email'] ?? 'noreply@' . parse_url($appUrl, PHP_URL_HOST));
        $this->fromName = $fromName ?: ($smtpConfig['from_name'] ?? APP_NAME);
        $this->smtp = $smtpConfig;
    }

    /**
     * Laedt SMTP-Config aus der Settings-Tabelle
     */
    public static function fromSettings(Database $db): self
    {
        $settings = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'app_url'") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        // Secrets (z.B. smtp_password) liegen verschluesselt — transparent dekryptieren
        $settings = \Core\Settings::decryptMap($settings);

        $appUrl = $settings['app_url'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $smtpConfig = [
            'host' => $settings['smtp_host'] ?? '',
            'port' => (int) ($settings['smtp_port'] ?? 587),
            'username' => $settings['smtp_username'] ?? '',
            'password' => $settings['smtp_password'] ?? '',
            'encryption' => $settings['smtp_encryption'] ?? 'tls',
            'from_email' => $settings['smtp_from_email'] ?? '',
            'from_name' => $settings['smtp_from_name'] ?? APP_NAME,
        ];

        return new self($appUrl, $smtpConfig);
    }

    /**
     * Pruefen ob SMTP konfiguriert ist
     */
    public function isConfigured(): bool
    {
        return !empty($this->smtp['host']) && !empty($this->smtp['username']);
    }

    /**
     * Sendet eine Einladungs-Email an einen neuen User
     */
    public function sendInvitation(string $toEmail, string $userName, string $inviteToken, ?string $invitedBy = null): bool
    {
        $setPasswordUrl = $this->appUrl . '/set-password?token=' . urlencode($inviteToken);

        $subject = 'Einladung zu ' . $this->fromName;

        $body = $this->renderInvitation([
            'userName' => $userName,
            'invitedBy' => $invitedBy,
            'setPasswordUrl' => $setPasswordUrl,
            'appName' => $this->fromName,
            'appUrl' => $this->appUrl,
        ]);

        return $this->send($toEmail, $subject, $body);
    }

    /**
     * Sendet eine HTML-Email ueber SMTP
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception("SMTP nicht konfiguriert (Host: {$this->smtp['host']}, User: {$this->smtp['username']})");
        }

        try {
            $host = $this->smtp['host'];
            $port = $this->smtp['port'];
            $username = $this->smtp['username'];
            $password = $this->smtp['password'];
            $encryption = $this->smtp['encryption'] ?? 'tls';

            // Verbindung aufbauen
            $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

            if ($encryption === 'ssl') {
                $socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
            } else {
                $socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
            }

            if (!$socket) {
                throw new \Exception("SMTP-Verbindung fehlgeschlagen: {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, 15);

            // Greeting lesen
            $this->smtpRead($socket);

            // EHLO
            $this->smtpWrite($socket, "EHLO " . gethostname());
            $ehloResp = $this->smtpRead($socket);

            // STARTTLS wenn noetig
            if ($encryption === 'tls') {
                $this->smtpWrite($socket, "STARTTLS");
                $this->smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->smtpWrite($socket, "EHLO " . gethostname());
                $this->smtpRead($socket);
            }

            // AUTH LOGIN
            $this->smtpWrite($socket, "AUTH LOGIN");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($username));
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($password));
            $authResp = $this->smtpRead($socket);
            if (strpos($authResp, '235') === false) {
                throw new \Exception("SMTP-Auth fehlgeschlagen: {$authResp}");
            }

            // MAIL FROM
            $this->smtpWrite($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->smtpRead($socket);

            // RCPT TO
            $this->smtpWrite($socket, "RCPT TO:<{$to}>");
            $this->smtpRead($socket);

            // DATA
            $this->smtpWrite($socket, "DATA");
            $this->smtpRead($socket);

            // Email-Header + Body
            $message = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "\r\n";
            $message .= chunk_split(base64_encode($htmlBody));
            $message .= "\r\n.";

            $this->smtpWrite($socket, $message);
            $dataResp = $this->smtpRead($socket);

            // QUIT
            $this->smtpWrite($socket, "QUIT");
            fclose($socket);

            if (strpos($dataResp, '250') !== false) {
                return true;
            }

            error_log("SMTP unerwartete Antwort: {$dataResp}");
            return false;

        } catch (\Exception $e) {
            error_log("SMTP-Fehler: " . $e->getMessage());
            return false;
        }
    }

    private function smtpWrite($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Multiline: "250-..." vs letzte Zeile "250 ..."
            if (isset($line[3]) && $line[3] !== '-') break;
        }
        return trim($response);
    }

    private function renderInvitation(array $vars): string
    {
        $firstName = explode(' ', $vars['userName'])[0];

        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8fafc;">
<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
    <div style="background:linear-gradient(135deg,#004c9b,#1976d2);padding:32px;text-align:center;">
        <div style="font-size:36px;margin-bottom:8px;">&#x1F680;</div>
        <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;">' . htmlspecialchars($vars['appName']) . '</h1>
    </div>
    <div style="padding:32px 32px 24px;">
        <h2 style="margin:0 0 16px;font-size:20px;color:#1e293b;">Hey ' . htmlspecialchars($firstName) . '!</h2>
        <p style="color:#475569;line-height:1.7;margin:0 0 8px;font-size:15px;">'
            . ($vars['invitedBy'] ? htmlspecialchars($vars['invitedBy']) . ' hat dich eingeladen, ' : 'Du wurdest eingeladen, ')
            . '<strong>' . htmlspecialchars($vars['appName']) . '</strong> zu nutzen — unser KI-gestuetztes Textwerkzeug.</p>
        <p style="color:#475569;line-height:1.7;margin:0 0 28px;font-size:15px;">Leg dir einfach ein Passwort fest und du kannst direkt loslegen:</p>
        <div style="text-align:center;margin:0 0 28px;">
            <a href="' . htmlspecialchars($vars['setPasswordUrl']) . '" style="display:inline-block;background:linear-gradient(135deg,#004c9b,#1976d2);color:#fff;text-decoration:none;padding:14px 40px;border-radius:10px;font-weight:700;font-size:16px;letter-spacing:0.3px;">Los geht\'s &#x2192;</a>
        </div>
        <p style="color:#94a3b8;font-size:12px;line-height:1.5;margin:0;">Link geht nicht? Kopier ihn direkt:<br>
        <a href="' . htmlspecialchars($vars['setPasswordUrl']) . '" style="color:#004c9b;word-break:break-all;text-decoration:none;">' . htmlspecialchars($vars['setPasswordUrl']) . '</a></p>
    </div>
    <div style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;text-align:center;">
        <p style="margin:0;color:#94a3b8;font-size:11px;">Der Link ist 7 Tage gueltig. Danach einfach beim Admin melden.</p>
    </div>
</div>
</body></html>';
    }
}
