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

    /** Favicon: eigenes Favicon -> sonst das Symbol -> sonst Standard. */
    public static function favicon(): string
    {
        $v = self::setting('brand_favicon') ?? self::setting('brand_logo_icon_path');
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
     * Inline-CSS, das die KOMPLETTE --thoxan-*-Farbrampe (50..950) mit einer aus
     * der Kundenfarbe generierten Rampe ueberschreibt. Wird im <head> NACH
     * thx-tokens.css ausgegeben, sodass alle .thx-*-Klassen die Kundenfarbe erben
     * — durchgaengig, nicht nur bei Buttons.
     *
     * Semantische Farben (emerald/amber/rose = Erfolg/Warnung/Fehler) bleiben
     * bewusst unveraendert; nur die Markenfarbe wird getauscht.
     *
     * Ohne gesetzte brand_primary_color: leerer String (identisches Thoxan).
     */
    public static function cssVars(): string
    {
        $ramp = self::primaryRamp();
        $accent = self::accentRamp();
        if ($ramp === null && $accent === null) {
            return '';
        }
        $css = ':root{';
        if ($ramp !== null) {
            foreach ($ramp as $stop => $hex) {
                $css .= '--thoxan-' . $stop . ':' . $hex . ';';
            }
        }
        if ($accent !== null) {
            // Zweite Akzentfarbe -> Sekundaerpalette (indigo). Bewusst NICHT die
            // semantischen Farben (emerald/amber/rose = Erfolg/Warnung/Fehler).
            foreach ($accent as $stop => $hex) {
                $css .= '--indigo-' . $stop . ':' . $hex . ';';
            }
        }
        $css .= '}';
        return $css;
    }

    /**
     * Rampe fuer die zweite Akzentfarbe (brand_accent_color).
     * Deckt die in thx-tokens.css definierten indigo-Stops ab.
     * @return array<int,string>|null
     */
    public static function accentRamp(): ?array
    {
        $accent = self::setting('brand_accent_color');
        if ($accent === null || $accent === '' || !self::isHexColor($accent)) {
            return null;
        }
        return [
            50  => self::mixWhite($accent, 0.90),
            100 => self::mixWhite($accent, 0.80),
            200 => self::mixWhite($accent, 0.60),
            500 => self::normalizeHex($accent),
            600 => self::mixBlack($accent, 0.10),
            700 => self::mixBlack($accent, 0.30),
        ];
    }

    /**
     * Erzeugt die volle 50..950-Rampe aus brand_primary_color (= Stop 500).
     * Helle Stops mischen zu Weiss, dunkle zu Schwarz — die Mischungsverhaeltnisse
     * sind an der Original-Thoxan-Rampe kalibriert.
     * @return array<int,string>|null  stop => Hex, oder null ohne/ungueltige Farbe
     */
    public static function primaryRamp(): ?array
    {
        $primary = self::setting('brand_primary_color');
        if ($primary === null || $primary === '' || !self::isHexColor($primary)) {
            return null;
        }
        // Mischung zu Weiss (Anteil Weiss) fuer die hellen Stops.
        $light = [50 => 0.90, 100 => 0.80, 200 => 0.60, 300 => 0.40, 400 => 0.20];
        // Abdunkeln (Anteil Schwarz) fuer die dunklen Stops.
        $dark  = [600 => 0.10, 700 => 0.30, 800 => 0.47, 900 => 0.65, 950 => 0.80];

        $ramp = [];
        foreach ($light as $stop => $r) {
            $ramp[$stop] = self::mixWhite($primary, $r);
        }
        $ramp[500] = self::normalizeHex($primary);
        foreach ($dark as $stop => $r) {
            $ramp[$stop] = self::mixBlack($primary, $r);
        }
        ksort($ramp);
        return $ramp;
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

    /** @return array{0:int,1:int,2:int} RGB einer #rgb/#rrggbb-Farbe */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function normalizeHex(string $hex): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Farbe um $ratio (0..1) zu Weiss mischen (heller). */
    private static function mixWhite(string $hex, float $ratio): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        $r = (int) round($r + (255 - $r) * $ratio);
        $g = (int) round($g + (255 - $g) * $ratio);
        $b = (int) round($b + (255 - $b) * $ratio);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Farbe um $ratio (0..1) zu Schwarz mischen (dunkler). */
    private static function mixBlack(string $hex, float $ratio): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        $f = 1 - $ratio;
        return sprintf('#%02x%02x%02x', (int) round($r * $f), (int) round($g * $f), (int) round($b * $f));
    }
}
