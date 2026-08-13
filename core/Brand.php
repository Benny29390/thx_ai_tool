<?php

namespace Core;

/**
 * Brand — zentraler White-Label-Helper.
 *
 * Buendelt Produktname, Logo, Farbe, Favicon und die Basis-URL an EINER Stelle,
 * damit jede Installation ihr eigenes Erscheinungsbild bekommen kann, ohne dass
 * irgendwo im Code eine Domain oder ein Markenname fest verdrahtet ist.
 *
 * Rueckwaertskompatibilitaet: Alle Fallbacks entsprechen den heutigen
 * Thoxan-Werten bzw. der bestehenden `app_name`/`app_logo`/`app_url`-Logik.
 * Ohne gesetzte brand_*-Settings verhaelt sich die bestehende Installation
 * exakt wie vorher (No-Op).
 *
 * CLI-sicher: liest Werte aus der settings-Tabelle (DB), funktioniert also auch
 * in Cron-Skripten ohne Web-Kontext.
 */
class Brand
{
    /** Produktname. Fallback: Setting app_name → Konstante APP_NAME. */
    public static function name(): string
    {
        $v = self::setting('brand_name') ?? self::setting('app_name');
        if ($v !== null && $v !== '') {
            return $v;
        }
        return defined('APP_NAME') ? APP_NAME : 'KI Text Tool';
    }

    /**
     * Vollflaechiges Logo fuer die Sidebar.
     * Rueckgabe ist der rohe Wert des Settings app_logo (heute inline-SVG/HTML)
     * bzw. brand_logo_path. Null → Aufrufer zeigt stattdessen den Namen als Text
     * (bestehendes Verhalten in main.php).
     */
    public static function logo(): ?string
    {
        $v = self::setting('brand_logo_path') ?? self::setting('app_logo');
        return ($v !== null && $v !== '') ? $v : null;
    }

    /** Kompaktes Icon-Logo (eingeklappte Sidebar). Fallback: Thoxan-X. */
    public static function logoIcon(): string
    {
        $v = self::setting('brand_logo_icon_path');
        return ($v !== null && $v !== '') ? $v : '/assets/images/thoxan-x.svg';
    }

    /** Primaerfarbe (Hex). Fallback: Thoxan-Blau (--thoxan-500). */
    public static function primary(): string
    {
        $v = self::setting('brand_primary_color');
        return ($v !== null && $v !== '') ? $v : '#006fb9';
    }

    /** Favicon-Pfad. Fallback: bestehendes Favicon. */
    public static function favicon(): string
    {
        $v = self::setting('brand_favicon');
        return ($v !== null && $v !== '') ? $v : '/assets/images/thoxan-x.svg';
    }

    /**
     * Basis-URL der Installation, optional mit angehaengtem Pfad.
     * EINZIGE Quelle fuer absolute Links (E-Mails, Webhooks, Portal-Links).
     *
     * Fallback-Kette: Setting app_url → config app.url → leer (relativ).
     * Bewusst KEIN hartes Domain-Literal mehr, damit bei White-Label nie eine
     * fremde Thoxan-Domain durchsickert.
     */
    public static function url(string $path = ''): string
    {
        $base = self::setting('app_url');
        if ($base === null || $base === '') {
            $base = self::configUrl();
        }
        $base = rtrim((string) $base, '/');
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Inline-CSS, das die --thoxan-*-Custom-Properties mit der Kundenfarbe
     * ueberschreibt. Wird im <head> NACH thx-tokens.css ausgegeben, sodass alle
     * bestehenden .thx-*-Klassen automatisch die Kundenfarbe erben — ohne dass
     * eine CSS-Datei angefasst werden muss.
     *
     * Ist keine brand_primary_color gesetzt, wird ein leerer String geliefert
     * (kein Override → identisches Thoxan-Aussehen).
     */
    public static function cssVars(): string
    {
        $primary = self::setting('brand_primary_color');
        if ($primary === null || $primary === '' || !self::isHexColor($primary)) {
            return '';
        }
        [$c600, $c700] = self::darken($primary);
        return ':root{'
            . '--thoxan-500:' . $primary . ';'
            . '--thoxan-600:' . $c600 . ';'
            . '--thoxan-700:' . $c700 . ';'
            . '}';
    }

    // ---------------------------------------------------------------------

    /** Setting lesen, fehlertolerant (DB evtl. noch nicht da). */
    private static function setting(string $key): ?string
    {
        try {
            $v = Settings::get($key);
            return ($v === null || $v === '') ? null : $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** app.url aus config.php, fehlertolerant (auch ohne laufende App-Instanz). */
    private static function configUrl(): string
    {
        try {
            if (class_exists('\\Core\\App')) {
                $app = App::getInstance();
                if (method_exists($app, 'getConfig')) {
                    $u = $app->getConfig('app.url');
                    if (is_string($u) && $u !== '') {
                        return $u;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignorieren
        }
        // Letzter Ausweg: config.php direkt lesen.
        try {
            $file = (defined('CONFIG_PATH') ? CONFIG_PATH : __DIR__ . '/../config') . '/config.php';
            if (is_file($file)) {
                $cfg = require $file;
                if (isset($cfg['app']['url']) && is_string($cfg['app']['url'])) {
                    return $cfg['app']['url'];
                }
            }
        } catch (\Throwable $e) {
            // ignorieren
        }
        return '';
    }

    private static function isHexColor(string $v): bool
    {
        return (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v);
    }

    /**
     * Erzeugt zwei dunklere Abstufungen einer Hex-Farbe (Hover-Stufen 600/700),
     * analog zum Thoxan-Verlauf (500 → 600 → 700).
     * @return array{0:string,1:string}
     */
    private static function darken(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $mk = function (float $factor) use ($r, $g, $b): string {
            $rr = max(0, (int) round($r * $factor));
            $gg = max(0, (int) round($g * $factor));
            $bb = max(0, (int) round($b * $factor));
            return sprintf('#%02x%02x%02x', $rr, $gg, $bb);
        };
        return [$mk(0.86), $mk(0.72)];
    }
}
