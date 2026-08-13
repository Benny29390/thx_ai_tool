<?php

namespace Core;

/**
 * Modules — Runtime-Fassade + zweistufiges Modul-Gate.
 *
 * EBENE 1 (Lizenz):   Darf dieses Modul auf DIESER Installation ueberhaupt?
 *                     -> delegiert an License::allows() (Phase 3). Fehlt die
 *                        License-Klasse/Datei, ist alles erlaubt (fail-open).
 * EBENE 2 (Aktiv):    Hat der Kunden-Admin es eingeschaltet?
 *                     -> Tabelle module_state, Default AKTIV (fehlende Zeile = an).
 *
 * Nutzbar (active) = core ODER (licensed UND enabled).
 *
 * SICHERHEITSNETZ (oberste Regel, damit sich niemand aussperrt):
 *  - core-Module (Kunden/Benutzer/Einstellungen) sind IMMER aktiv.
 *  - Jeder DB-/Lizenz-Zugriff ist in try/catch; im Fehlerfall -> true (fail-open).
 *  - Ein Cap ohne zugehoeriges Modul gilt als aktiv (fail-open).
 * So kann weder ein DB-Ausfall noch eine kaputte Registry den Zugang zur
 * Verwaltung blockieren.
 */
class Modules
{
    /** @var array<string,array>|null geladene Registry */
    private static $registry = null;
    /** @var array<string,string>|null Cap -> Modul-Key */
    private static $capIndex = null;
    /** @var array<string,bool>|null module_state (key -> enabled) */
    private static $stateCache = null;

    // ---- Registry ---------------------------------------------------------

    public static function all(): array
    {
        if (self::$registry === null) {
            $file = (defined('CONFIG_PATH') ? CONFIG_PATH : __DIR__ . '/../config') . '/modules.php';
            try {
                $reg = is_file($file) ? require $file : [];
                self::$registry = is_array($reg) ? $reg : [];
            } catch (\Throwable $e) {
                self::$registry = [];
            }
        }
        return self::$registry;
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /** Cap -> Modul-Key (invertierter Index, einmalig aufgebaut). */
    public static function moduleForCap(string $cap): ?string
    {
        if (self::$capIndex === null) {
            $idx = [];
            foreach (self::all() as $key => $mod) {
                foreach (($mod['caps'] ?? []) as $c) {
                    // Erster Gewinner bei versehentlicher Doppelbelegung.
                    if (!isset($idx[$c])) {
                        $idx[$c] = $key;
                    }
                }
            }
            self::$capIndex = $idx;
        }
        return self::$capIndex[$cap] ?? null;
    }

    public static function isCore(string $key): bool
    {
        $mod = self::get($key);
        return !empty($mod['core']);
    }

    // ---- Gate-Ebenen ------------------------------------------------------

    /** EBENE 1: Lizenz. Ohne License-Klasse/Datei -> true (fail-open). */
    public static function licensed(string $key): bool
    {
        if (self::isCore($key)) {
            return true;
        }
        try {
            if (class_exists('\\Core\\License')) {
                return License::allows($key);
            }
        } catch (\Throwable $e) {
            // fail-open
        }
        return true;
    }

    /** EBENE 2: Aktiv-Schalter des Kunden-Admins. Fehlende Zeile -> aktiv. */
    public static function enabled(string $key): bool
    {
        if (self::isCore($key)) {
            return true;
        }
        try {
            $state = self::state();
            // Nur ein expliziter 0-Eintrag deaktiviert. Alles andere = aktiv.
            return !array_key_exists($key, $state) || $state[$key] === true;
        } catch (\Throwable $e) {
            return true; // fail-open
        }
    }

    /** Kombiniert: nutzbar? core ODER (licensed UND enabled). */
    public static function active(string $key): bool
    {
        try {
            if (self::isCore($key)) {
                return true;
            }
            // Unbekanntes Modul -> fail-open (nicht ueberraschend sperren).
            if (self::get($key) === null) {
                return true;
            }
            return self::licensed($key) && self::enabled($key);
        } catch (\Throwable $e) {
            return true;
        }
    }

    // ---- Cap-/URI-Gates (die zwei Einhaeng-Punkte nutzen diese) -----------

    /**
     * Ist das Modul hinter diesem Cap aktiv?
     * core-Cap oder Cap ohne Modul -> true (fail-open, nie Verwaltung sperren).
     */
    public static function capActive(string $cap): bool
    {
        try {
            $key = self::moduleForCap($cap);
            if ($key === null) {
                return true; // Cap gehoert zu keinem Modul -> nicht gaten
            }
            return self::active($key);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Sidebar-Sichtbarkeit: Recht UND Modul aktiv. */
    public static function navVisible(string $cap): bool
    {
        try {
            if (!Auth::can($cap)) {
                return false;
            }
        } catch (\Throwable $e) {
            // Wenn Auth nicht bewertbar ist, lieber nicht anzeigen.
            return false;
        }
        return self::capActive($cap);
    }

    // ---- Admin-/Schreibseite ---------------------------------------------

    /**
     * Schaltet ein Modul an/aus (Aktiv-Ebene). core-Module lassen sich nicht
     * deaktivieren. Gibt false zurueck, wenn die Aktion abgelehnt wurde.
     */
    public static function setEnabled(string $key, bool $on): bool
    {
        $mod = self::get($key);
        if ($mod === null) {
            return false;
        }
        if (!empty($mod['core'])) {
            return false; // core nie abschaltbar
        }
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO module_state (module_key, enabled) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)",
            [$key, $on ? 1 : 0]
        );
        self::$stateCache = null; // Cache invalidieren
        return true;
    }

    /**
     * Alle Module mit ihrem Status fuer die Admin-UI.
     * @return array<int,array> je: key,label,beschreibung,gruppe,icon,externals,core,licensed,enabled,active
     */
    public static function withState(): array
    {
        $out = [];
        foreach (self::all() as $key => $mod) {
            $out[] = [
                'key' => $key,
                'label' => $mod['label'] ?? $key,
                'beschreibung' => $mod['beschreibung'] ?? '',
                'gruppe' => $mod['gruppe'] ?? 'Sonstige',
                'icon' => $mod['icon'] ?? 'widgets',
                'externals' => $mod['externals'] ?? [],
                'core' => !empty($mod['core']),
                'licensed' => self::licensed($key),
                'enabled' => self::enabled($key),
                'active' => self::active($key),
            ];
        }
        return $out;
    }

    // ---- Selbsttest -------------------------------------------------------

    /**
     * Prueft Registry-Konsistenz. @return string[] gefundene Probleme (leer = ok).
     * Wird von doctor.php und der Admin-UI genutzt.
     */
    public static function selfCheck(): array
    {
        $probleme = [];
        $seenCaps = [];
        foreach (self::all() as $key => $mod) {
            foreach (($mod['caps'] ?? []) as $c) {
                if (isset($seenCaps[$c])) {
                    $probleme[] = "Cap '$c' ist mehreren Modulen zugeordnet ('{$seenCaps[$c]}' und '$key').";
                }
                $seenCaps[$c] = $key;
            }
        }
        // Kern-Caps duerfen niemals gated sein.
        foreach ([CAP_SETTINGS_MANAGE, CAP_USERS_MANAGE, CAP_CUSTOMERS_VIEW, CAP_CUSTOMERS_MANAGE] as $coreCap) {
            if (!self::capActive($coreCap)) {
                $probleme[] = "KRITISCH: Kern-Cap '$coreCap' wird gegated — Aussperr-Gefahr.";
            }
        }
        return $probleme;
    }

    // ---- intern -----------------------------------------------------------

    /** @return array<string,bool> module_state: key -> enabled(bool) */
    private static function state(): array
    {
        if (self::$stateCache === null) {
            $map = [];
            try {
                $rows = Database::getInstance()->query("SELECT module_key, enabled FROM module_state");
                foreach ($rows as $r) {
                    $map[$r['module_key']] = ((int) $r['enabled']) === 1;
                }
            } catch (\Throwable $e) {
                // Tabelle fehlt/DB-Fehler -> leere Map => alles aktiv (fail-open).
                $map = [];
            }
            self::$stateCache = $map;
        }
        return self::$stateCache;
    }
}
