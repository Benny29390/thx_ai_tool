<?php
namespace Core;

/**
 * App-weite Verschluesselung fuer at-rest-Secrets (API-Keys, Passwoerter, PATs).
 *
 * Verfahren: AES-256-GCM mit zufaelligem 12-Byte-IV pro Wert; Authentication-Tag
 * wird Base64-encoded zusammen mit IV + Chiffretext gespeichert.
 *
 * Format:  enc:v1:<base64( iv || tag || ciphertext )>
 *  - iv:  12 Byte (GCM-Standard)
 *  - tag: 16 Byte (AEAD-Auth)
 *  - rest: Chiffretext
 *
 * Der App-Schluessel kommt aus config.app.encryption_key (Hex-String, 64 Zeichen = 32 Byte).
 * Aenderung des Schluessels macht alle bestehenden Werte unleserlich — vorher migrieren!
 */
class Crypto
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN  = 12;
    private const TAG_LEN = 16;

    /** @var string|null 32-Byte Binary-Schluessel, lazy geladen. */
    private static ?string $key = null;

    /**
     * Holt den Schluessel aus config.app.encryption_key (hex). Cached.
     * @throws \RuntimeException wenn nicht konfiguriert oder ungueltig.
     */
    public static function key(): string
    {
        if (self::$key !== null) return self::$key;

        $configFile = CONFIG_PATH . '/config.php';
        if (!file_exists($configFile)) {
            throw new \RuntimeException('Crypto: config.php nicht gefunden');
        }
        $config = require $configFile;
        $hex = $config['app']['encryption_key'] ?? '';
        if (!is_string($hex) || strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new \RuntimeException('Crypto: app.encryption_key muss 64 hex-Zeichen (32 Byte) sein');
        }
        self::$key = hex2bin($hex);
        return self::$key;
    }

    /**
     * Verschluesselt einen Klartext-String. Leerstrings werden unveraendert
     * zurueckgegeben (kein Grund, "nichts" zu verschluesseln).
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($cipher === false) {
            throw new \RuntimeException('Crypto: openssl_encrypt fehlgeschlagen');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Entschluesselt einen Wert.
     *  - Beginnt der Wert mit `enc:v1:` → wird entschluesselt.
     *  - Andernfalls (Klartext-Legacy) → wird unveraendert zurueckgegeben.
     *
     * Bei kaputtem Chiffretext (z.B. falscher App-Key) wird eine RuntimeException geworfen.
     */
    public static function decrypt(string $value): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }
        $blob = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($blob === false || strlen($blob) < self::IV_LEN + self::TAG_LEN + 1) {
            throw new \RuntimeException('Crypto: ungueltiges Chiffretext-Format');
        }
        $iv     = substr($blob, 0, self::IV_LEN);
        $tag    = substr($blob, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($blob, self::IV_LEN + self::TAG_LEN);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Crypto: Entschluesselung fehlgeschlagen (App-Key falsch oder Daten beschaedigt)');
        }
        return $plain;
    }

    /** Pruefen, ob ein Wert in unserem Encrypted-Format vorliegt. */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Versucht zu entschluesseln, faengt Fehler ab und liefert
     * den Originalwert zurueck. Fuer "weiches" Decrypt in Listen-Views.
     */
    public static function tryDecrypt(string $value): string
    {
        if (!self::isEncrypted($value)) return $value;
        try {
            return self::decrypt($value);
        } catch (\Throwable $e) {
            error_log('[Crypto] tryDecrypt fehlgeschlagen: ' . $e->getMessage());
            return $value;
        }
    }
}
