<?php
namespace Core;

/**
 * Zentrale Settings-Zugriffsschicht. Verschluesselt Secrets beim Schreiben
 * und entschluesselt sie beim Lesen — fuer den Aufrufer transparent.
 *
 * Was als "Secret" gilt, ist anhand des Key-Namens festgelegt (siehe isSecret()).
 * Identische Heuristik wie im Admin-Settings-Endpoint, damit Schreiben und
 * Lesen konsistent sind.
 */
class Settings
{
    /**
     * Pattern, die einen Setting-Key als verschluesselungspflichtig markieren.
     * Treffer => Wert wird beim Schreiben verschluesselt und beim Lesen
     * entschluesselt.
     *
     * `_token` (Unterstrich davor) statt `token`, damit `max_tokens_per_request`
     * NICHT als Secret behandelt wird. Echte Token-Keys heissen z.B. `webhook_token`,
     * `auth_token` — der Unterstrich davor unterscheidet sie sauber.
     */
    /**
     * Pattern-Match per Wort-Grenze (Underscore oder String-Anfang/-Ende).
     * Dadurch matched 'pat' nur als ganzes Segment — 'github_pat' ja, 'loom_pull_videos_path' nein.
     */
    private const SECRET_PATTERNS = ['api_key', 'pat', 'password', 'secret', 'token'];

    /**
     * Ausnahmen — Keys, die zwar ein Pattern matchen, aber trotzdem KEIN Secret sind.
     * - max_tokens_per_request: Token-Limit, kein Auth-Token.
     * - loom_pull_videos_path: nur ein Filesystem-Pfad, matcht faelschlich via "_pat" in "_pa[th]".
     */
    private const SECRET_EXCEPTIONS = [
        'max_tokens_per_request',
        'loom_pull_videos_path',
    ];

    /** @var array<string,string>|null Cache fuer alle Settings (entschluesselt). */
    private static ?array $cache = null;

    public static function isSecret(string $key): bool
    {
        if (in_array($key, self::SECRET_EXCEPTIONS, true)) return false;
        foreach (self::SECRET_PATTERNS as $p) {
            // (^|_)pattern($|_) — verhindert Substring-False-Positives wie '_path' fuer 'pat'.
            if (preg_match('/(^|_)' . preg_quote($p, '/') . '($|_)/', $key)) return true;
        }
        return false;
    }

    /**
     * Einen Setting-Wert lesen. Liefert null, wenn der Key fehlt.
     * Secrets werden transparent entschluesselt.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $db = Database::getInstance();
        $value = $db->queryValue("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        if ($value === null || $value === false) return $default;
        $value = (string)$value;
        if ($value === '') return $value;
        return self::isSecret($key) ? Crypto::tryDecrypt($value) : $value;
    }

    /**
     * Setting schreiben (insert oder update). Secrets werden vor dem
     * Speichern verschluesselt. Leerer Wert bei Secrets wird ignoriert
     * (der alte Wert bleibt erhalten) — explizites Loeschen ueber forget().
     */
    public static function set(string $key, string $value, string $type = 'string', ?string $description = null): void
    {
        $db = Database::getInstance();
        $stored = self::isSecret($key) && $value !== ''
            ? Crypto::encrypt($value)
            : $value;

        $existing = $db->queryValue("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->execute(
                "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                [$stored, $key]
            );
        } else {
            $db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $stored,
                'setting_type' => $type,
                'description' => $description ?? $key,
            ]);
        }
        self::$cache = null;
    }

    /** Setting komplett loeschen. */
    public static function forget(string $key): void
    {
        $db = Database::getInstance();
        $db->execute("DELETE FROM settings WHERE setting_key = ?", [$key]);
        self::$cache = null;
    }

    /**
     * Alle Settings als assoziatives Array. Secrets werden entschluesselt.
     * Ergebnis wird pro Request gecached.
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $db = Database::getInstance();
        $rows = $db->query("SELECT setting_key, setting_value FROM settings");
        $out = [];
        foreach ($rows as $row) {
            $k = $row['setting_key'];
            $v = (string)($row['setting_value'] ?? '');
            $out[$k] = self::isSecret($k) ? Crypto::tryDecrypt($v) : $v;
        }
        return self::$cache = $out;
    }

    /**
     * Helfer fuer bestehende Code-Stellen, die ein eigenes settings-Array
     * via "SELECT setting_key, setting_value FROM settings" aufbauen.
     * Entschluesselt Secret-Werte im Array, laesst andere unberuehrt.
     *
     * Erwartet Eingabe wie:  ['openai_api_key' => 'enc:v1:...', 'app_name' => 'Tool', ...]
     * Liefert dasselbe Format zurueck, aber entschluesselt.
     *
     * @param array<string,string> $byKey
     * @return array<string,string>
     */
    public static function decryptMap(array $byKey): array
    {
        foreach ($byKey as $k => $v) {
            if (!is_string($k) || !is_string($v) || $v === '') continue;
            if (self::isSecret($k)) {
                $byKey[$k] = Crypto::tryDecrypt($v);
            }
        }
        return $byKey;
    }
}
