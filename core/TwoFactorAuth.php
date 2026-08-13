<?php
/**
 * Zwei-Faktor-Authentifizierung (TOTP)
 */

namespace Core;

class TwoFactorAuth
{
    private const SECRET_LENGTH = 16;
    private const CODE_LENGTH = 6;
    private const TIME_STEP = 30;
    private const BACKUP_CODE_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;

    /**
     * Base32 Alphabet
     */
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generiert ein neues TOTP-Secret
     */
    public static function generateSecret(): string
    {
        $secret = '';
        $randomBytes = random_bytes(self::SECRET_LENGTH);

        for ($i = 0; $i < self::SECRET_LENGTH; $i++) {
            $secret .= self::$base32Chars[ord($randomBytes[$i]) % 32];
        }

        return $secret;
    }

    /**
     * Generiert die otpauth:// URL fuer QR-Code
     */
    public static function getQRCodeUrl(string $secret, string $email, ?string $issuer = null): string
    {
        $issuer = $issuer ?? APP_NAME;
        $label = urlencode($issuer . ':' . $email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::CODE_LENGTH,
            'period' => self::TIME_STEP
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Generiert URL fuer QR-Code Image ueber QR Server API
     */
    public static function getQRCodeImageUrl(string $otpauthUrl, int $size = 200): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . urlencode($otpauthUrl);
    }

    /**
     * Generiert QR-Code als SVG (ohne externe API)
     */
    public static function getQRCodeSvg(string $data, int $size = 200): string
    {
        // Einfache QR-Code Generierung als Data-URI mit externer API
        // Fuer Produktion: phpqrcode Library oder eigene Implementierung
        return self::getQRCodeImageUrl($data, $size);
    }

    /**
     * Verifiziert einen 6-stelligen TOTP-Code
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        // Code normalisieren (nur Ziffern, genau 6 Zeichen)
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== self::CODE_LENGTH) {
            return false;
        }

        $currentTime = floor(time() / self::TIME_STEP);

        // Pruefe aktuellen und benachbarte Zeitfenster
        for ($i = -$window; $i <= $window; $i++) {
            $expectedCode = self::generateTOTP($secret, $currentTime + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generiert TOTP-Code fuer einen Zeitpunkt
     */
    private static function generateTOTP(string $secret, int $counter): string
    {
        // Secret von Base32 dekodieren
        $secretBytes = self::base32Decode($secret);

        // Counter als 8-Byte Big-Endian
        $counterBytes = pack('N*', 0, $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);

        // Dynamic Truncation
        $offset = ord($hash[19]) & 0x0f;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, self::CODE_LENGTH);

        return str_pad((string) $code, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 dekodieren
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper($input);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $pos = strpos(self::$base32Chars, $input[$i]);
            if ($pos === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $output;
    }

    /**
     * Generiert Backup-Codes
     */
    public static function generateBackupCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            // Generiere Code aus Grossbuchstaben und Zahlen
            $code = '';
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Ohne I, O, 0, 1 zur Vermeidung von Verwechslungen
            for ($j = 0; $j < self::BACKUP_CODE_LENGTH; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            // Format: XXXX-XXXX
            $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
        }

        return $codes;
    }

    /**
     * Hasht einen Backup-Code
     */
    public static function hashBackupCode(string $code): string
    {
        // Code normalisieren (Bindestrich entfernen, Grossbuchstaben)
        $code = strtoupper(str_replace('-', '', $code));
        return password_hash($code, PASSWORD_DEFAULT);
    }

    /**
     * Verifiziert einen Backup-Code gegen gehashte Codes
     * Gibt den Index des verbrauchten Codes zurueck oder -1
     */
    public static function verifyBackupCode(string $code, array $hashedCodes): int
    {
        // Code normalisieren
        $code = strtoupper(str_replace('-', '', $code));

        foreach ($hashedCodes as $index => $hashedCode) {
            if ($hashedCode && password_verify($code, $hashedCode)) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Hasht alle Backup-Codes fuer Speicherung
     */
    public static function hashBackupCodes(array $codes): array
    {
        return array_map([self::class, 'hashBackupCode'], $codes);
    }
}
