<?php

namespace Core;

/**
 * Version — ermittelt den Software-Stand ueber Git und vergleicht mit dem Remote.
 *
 * Anbieter-neutral: funktioniert mit GitHub, GitLab oder einem eigenen Git-Server
 * gleichermassen — es zaehlt nur das konfigurierte 'origin'-Remote.
 *
 * Alle Git-Aufrufe laufen mit '-c safe.directory=<root>', damit sie unabhaengig
 * vom ausfuehrenden Nutzer (www-data/root/deploy) nicht an der Ownership scheitern.
 */
class Version
{
    private static function root(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    }

    /** Fuehrt ein git-Kommando im Projektverzeichnis aus. */
    public static function git(string $args): array
    {
        $root = self::root();
        $cmd = 'git -c safe.directory=' . escapeshellarg($root)
            . ' -C ' . escapeshellarg($root) . ' ' . $args . ' 2>&1';
        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);
        return ['code' => $code, 'out' => trim(implode("\n", $output))];
    }

    public static function gitAvailable(): bool
    {
        $r = self::git('rev-parse --is-inside-work-tree');
        return $r['code'] === 0 && $r['out'] === 'true';
    }

    /** Aktuelle Version: git describe (Tag), Fallback APP_VERSION. */
    public static function current(): string
    {
        if (self::gitAvailable()) {
            $r = self::git('describe --tags --always --dirty');
            if ($r['code'] === 0 && $r['out'] !== '') {
                return $r['out'];
            }
        }
        return defined('APP_VERSION') ? APP_VERSION : 'unbekannt';
    }

    public static function currentCommit(): string
    {
        $r = self::git('rev-parse --short HEAD');
        return $r['code'] === 0 ? $r['out'] : '';
    }

    public static function branch(): string
    {
        $r = self::git('rev-parse --abbrev-ref HEAD');
        return $r['code'] === 0 ? $r['out'] : '';
    }

    public static function hasRemote(): bool
    {
        $r = self::git('remote');
        return $r['code'] === 0 && $r['out'] !== '';
    }

    /** Holt Remote-Stand (fetch). true bei Erfolg. */
    public static function fetch(): bool
    {
        if (!self::hasRemote()) {
            return false;
        }
        $r = self::git('fetch --tags --quiet');
        return $r['code'] === 0;
    }

    /**
     * Wie viele Commits liegt der lokale Stand HINTER origin/<branch>?
     * 0 = aktuell, >0 = Update verfuegbar, null = nicht ermittelbar.
     */
    public static function behindCount(?string $branch = null): ?int
    {
        if (!self::hasRemote()) {
            return null;
        }
        $branch = $branch ?: (self::branch() ?: 'stable');
        $r = self::git('rev-list --count HEAD..origin/' . escapeshellarg($branch));
        if ($r['code'] !== 0 || !is_numeric($r['out'])) {
            return null;
        }
        return (int) $r['out'];
    }

    /** Neuester Tag auf dem Remote-Branch (nach fetch). */
    public static function availableVersion(?string $branch = null): ?string
    {
        if (!self::hasRemote()) {
            return null;
        }
        $branch = $branch ?: (self::branch() ?: 'stable');
        $r = self::git('describe --tags --abbrev=0 origin/' . escapeshellarg($branch));
        if ($r['code'] === 0 && $r['out'] !== '') {
            return $r['out'];
        }
        // Kein Tag -> Kurz-Hash des Remote-HEAD.
        $r = self::git('rev-parse --short origin/' . escapeshellarg($branch));
        return $r['code'] === 0 ? $r['out'] : null;
    }

    /**
     * Changelog-Einträge zwischen lokalem HEAD und origin/<branch>.
     * @return string[] Commit-Betreffzeilen (neueste zuerst)
     */
    public static function changesSince(?string $branch = null, int $limit = 30): array
    {
        if (!self::hasRemote()) {
            return [];
        }
        $branch = $branch ?: (self::branch() ?: 'stable');
        $r = self::git('log --pretty=%s HEAD..origin/' . escapeshellarg($branch) . ' -n ' . (int) $limit);
        if ($r['code'] !== 0 || $r['out'] === '') {
            return [];
        }
        return array_values(array_filter(explode("\n", $r['out'])));
    }

    /** Kompakter Statusblock fuer die Admin-UI. */
    public static function status(?string $branch = null): array
    {
        $branch = $branch ?: (self::branch() ?: 'stable');
        $hasRemote = self::hasRemote();
        return [
            'git'        => self::gitAvailable(),
            'has_remote' => $hasRemote,
            'branch'     => $branch,
            'current'    => self::current(),
            'commit'     => self::currentCommit(),
        ];
    }
}
