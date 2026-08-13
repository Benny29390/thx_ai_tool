<?php

namespace Core;

/**
 * License — Lizenz-Ebene der Modul-Steuerung (Ebene 1).
 *
 * Der Hersteller legt zentral fest, welche Module eine Installation nutzen DARF.
 * Umgesetzt als signierte Datei config/license.json (Ed25519): der Hersteller
 * signiert mit seinem PRIVATEN Schluessel, jede Installation prueft mit dem hier
 * hinterlegten OEFFENTLICHEN Schluessel. Kunden koennen also pruefen, aber NICHT
 * faelschen. Kein Lizenzserver noetig.
 *
 * Format config/license.json:
 * {
 *   "data": {
 *     "installation_id": "kunde-mueller-01",
 *     "customer": "Mueller GmbH",
 *     "modules": ["chat","knowledge","crm"],   // oder ["*"] fuer alle
 *     "issued_at": "2026-08-13",
 *     "expires_at": "2027-08-13"                // optional; ohne = unbefristet
 *   },
 *   "sig": "<hex Ed25519-Signatur ueber kanonisches JSON von data>"
 * }
 *
 * FAIL-VERHALTEN (bewusst differenziert, nie Admin aussperren — core-Module
 * werden ohnehin schon in Modules::licensed() vor dem Aufruf kurzgeschlossen):
 *  - KEINE Datei        -> fail-open: alles erlaubt (Bestand/Thoxan unveraendert).
 *  - Datei ungueltig    -> fail-closed auf Basis (chat, knowledge). Banner.
 *  - abgelaufen (Grace) -> 14 Tage lang weiter alles erlaubt, danach nur Basis.
 */
class License
{
    /** Oeffentlicher Ed25519-Schluessel des Herstellers (hex, 32 Byte). */
    private const PUBLIC_KEY_HEX = '9ed037b3da2fbbbdf891a9e9a9bd0b80135dea052d00d564f8bed777029b851d';

    /** Module, die bei ungueltiger/abgelaufener Lizenz als Minimum nutzbar bleiben. */
    private const BASIS_MODULE = ['chat', 'knowledge'];

    /** Grace-Zeit nach Ablauf, in Tagen. */
    private const GRACE_DAYS = 14;

    /** @var array|null|false  null=nicht geladen, false=keine Datei, array=Inhalt */
    private static $cache = null;

    private static function file(): string
    {
        // Optionaler alternativer Pfad (z.B. abweichender Deploy-Ort oder Tests).
        $env = getenv('KI_LICENSE_FILE');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return (defined('CONFIG_PATH') ? CONFIG_PATH : __DIR__ . '/../config') . '/license.json';
    }

    /** Liest die Lizenzdatei (roh, unverifiziert). false = keine Datei. */
    public static function load()
    {
        if (self::$cache === null) {
            $f = self::file();
            if (!is_file($f)) {
                self::$cache = false;
            } else {
                $raw = @file_get_contents($f);
                $json = $raw !== false ? json_decode($raw, true) : null;
                self::$cache = is_array($json) ? $json : ['__broken__' => true];
            }
        }
        return self::$cache;
    }

    /**
     * Aktueller Zustand: 'none' | 'valid' | 'grace' | 'expired' | 'invalid'.
     */
    public static function status(): string
    {
        try {
            $lic = self::load();
            if ($lic === false) {
                return 'none';
            }
            if (!empty($lic['__broken__']) || !self::verify($lic)) {
                return 'invalid';
            }
            $exp = $lic['data']['expires_at'] ?? null;
            if ($exp === null || $exp === '') {
                return 'valid'; // unbefristet
            }
            $expTs = self::endOfDayUtc($exp);
            if ($expTs === null) {
                return 'invalid';
            }
            $now = time();
            if ($now <= $expTs) {
                return 'valid';
            }
            if ($now <= $expTs + self::GRACE_DAYS * 86400) {
                return 'grace';
            }
            return 'expired';
        } catch (\Throwable $e) {
            // Unerwarteter Fehler: nicht haerter als noetig — wie "keine Datei"
            // behandeln, damit ein Defekt nie den Zugang kappt.
            return 'none';
        }
    }

    /** Signatur + Grundstruktur pruefen (Ed25519). */
    public static function verify(array $lic): bool
    {
        try {
            if (!isset($lic['data']) || !is_array($lic['data']) || empty($lic['sig'])) {
                return false;
            }
            $msg = self::canonical($lic['data']);
            $sig = @hex2bin((string) $lic['sig']);
            $pub = @hex2bin(self::PUBLIC_KEY_HEX);
            if ($sig === false || $pub === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
                return false;
            }
            return sodium_crypto_sign_verify_detached($sig, $msg, $pub);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Darf dieses (Nicht-Kern-)Modul auf dieser Installation genutzt werden?
     */
    public static function allows(string $moduleKey): bool
    {
        $status = self::status();

        // Ohne Lizenzdatei: unlimitiert (Bestand/Thoxan).
        if ($status === 'none') {
            return true;
        }

        // Gueltig oder in Grace: die in der Lizenz gelisteten Module (oder '*').
        if ($status === 'valid' || $status === 'grace') {
            $lic = self::load();
            $mods = $lic['data']['modules'] ?? [];
            if (!is_array($mods)) {
                return false;
            }
            if (in_array('*', $mods, true)) {
                return true;
            }
            return in_array($moduleKey, $mods, true);
        }

        // Ungueltig oder abgelaufen: nur Basis-Module.
        return in_array($moduleKey, self::BASIS_MODULE, true);
    }

    public static function isValid(): bool
    {
        $s = self::status();
        return $s === 'valid' || $s === 'grace' || $s === 'none';
    }

    /** Anzeige-Infos fuer die Admin-UI. */
    public static function info(): array
    {
        $status = self::status();
        $lic = self::load();
        $data = is_array($lic) ? ($lic['data'] ?? []) : [];
        return [
            'status'          => $status,
            'unlimited'       => $status === 'none',
            'customer'        => $data['customer'] ?? null,
            'installation_id' => $data['installation_id'] ?? null,
            'modules'         => $data['modules'] ?? null,
            'issued_at'       => $data['issued_at'] ?? null,
            'expires_at'      => $data['expires_at'] ?? null,
        ];
    }

    // ---- intern -----------------------------------------------------------

    /**
     * Kanonische JSON-Form von data: rekursiv nach Schluessel sortiert, ohne
     * Escapes. MUSS identisch zum Signier-Skript (scripts/license-sign.php) sein.
     */
    public static function canonical(array $data): string
    {
        self::ksortRecursive($data);
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function ksortRecursive(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                self::ksortRecursive($v);
            }
        }
    }

    /** 'YYYY-MM-DD' -> Unix-Ende-des-Tages in UTC. null bei Formatfehler. */
    private static function endOfDayUtc(string $date): ?int
    {
        try {
            $dt = \DateTime::createFromFormat('Y-m-d', $date, new \DateTimeZone('UTC'));
            if ($dt === false) {
                return null;
            }
            $dt->setTime(23, 59, 59);
            return $dt->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
