<?php
namespace Services;

/**
 * AdminCodeToolsTrait
 *
 * Gemeinsame Tool-Implementierungen für KI-gesteuertes Bearbeiten von Code im
 * /var/www-Tree. Wird von AdminTaskService (Auftrags-Modus) und CoworkerService
 * (Chat-Modus) benutzt.
 *
 * Verantwortlichkeiten dieses Traits:
 *   - read_file / list_files / search_code / write_file
 *   - Pfad-Whitelist (Scope + FORBIDDEN_ALWAYS via abstract getForbiddenAlways)
 *   - Anti-Empty-Write-Schutz und PHP-Tag-Check
 *   - validateAfterWrite (php -l, JS-Klammer-Smoke-Test)
 *
 * Was NICHT hier ist (klassenspezifisch):
 *   - Snapshot-Persistierung — die jeweilige Klasse implementiert
 *     persistWriteSnapshot() (andere Tabellen, andere Felder).
 *   - System-Prompt, Tool-Dispatcher-Wiring, execute()-Loop.
 */
trait AdminCodeToolsTrait
{
    public const TRAIT_ROOT = '/var/www';
    public const TRAIT_MAX_FILE_BYTES = 500 * 1024;

    /** Pfade, die je Scope erlaubt sind (Präfixe relativ zu /var/www) */
    protected const TRAIT_SCOPE_ALLOW = [
        'frontend' => [
            'views/',
            'assets/css/',
            'assets/js/',
        ],
        'frontend_backend' => [
            'views/',
            'assets/css/',
            'assets/js/',
            'api/v1/',
            'services/',
        ],
        'all' => [
            '', // Alle Dateien unter /var/www erlaubt (außer FORBIDDEN_ALWAYS)
        ],
    ];

    /** @var array Tracker für während eines Turns bereits gesnapshottete Pfade */
    protected array $snapshottedThisRun = [];

    /**
     * Jede Klasse liefert ihre eigene FORBIDDEN-Liste. Beide Services dürfen
     * gemeinsame Einträge teilen, aber CoworkerService schützt zusätzlich sich
     * selbst + diesen Trait.
     */
    abstract protected function getForbiddenAlways(): array;

    /**
     * Snapshot-Persistierung in der jeweiligen Tabelle.
     * Returnt die neue Snapshot-ID, damit der Trait sie bei fehlgeschlagenem
     * Schreiben wieder als rolled_back markieren kann.
     */
    abstract protected function persistWriteSnapshot(string $absPath, ?string $originalContent, string $newContent, bool $existed): int;

    /**
     * Markiert einen frisch erstellten Snapshot als rolled_back — wird aufgerufen
     * wenn das anschließende file_put_contents oder die Validierung fehlschlägt.
     */
    abstract protected function markSnapshotRolledBack(int $snapshotId): void;

    // ====== Tools ======

    public function toolListFiles(string $scope, string $dir): array
    {
        $abs = $this->resolvePath($dir);
        if (!$abs || !is_dir($abs)) return ['__error' => true, '__text' => "Verzeichnis nicht gefunden: $dir"];
        if (!$this->isPathAllowed($abs, $scope)) return ['__error' => true, '__text' => "Verzeichnis liegt außerhalb des Scopes: $dir"];

        $items = @scandir($abs);
        if ($items === false) return ['__error' => true, '__text' => "Verzeichnis nicht lesbar: $dir"];

        $files = []; $dirs = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;
            $full = $abs . '/' . $name;
            if (is_dir($full)) $dirs[] = $name . '/';
            else $files[] = $name . ' (' . $this->humanSize((int) filesize($full)) . ')';
        }
        sort($dirs); sort($files);
        $text = "Inhalt von $dir:\n" . implode("\n", $dirs) . (empty($dirs) ? '' : "\n") . implode("\n", $files);
        return ['__text' => $text];
    }

    public function toolReadFile(string $scope, string $path, ?int $fromLine = null, ?int $toLine = null): array
    {
        $abs = $this->resolvePath($path);
        if (!$abs || !is_file($abs)) return ['__error' => true, '__text' => "Datei nicht gefunden: $path"];
        if (!$this->isPathAllowed($abs, $scope)) return ['__error' => true, '__text' => "Datei liegt außerhalb des Scopes: $path"];
        if (filesize($abs) > self::TRAIT_MAX_FILE_BYTES) return ['__error' => true, '__text' => "Datei zu groß (>500KB): $path"];
        $content = @file_get_contents($abs);
        if ($content === false) return ['__error' => true, '__text' => "Datei nicht lesbar: $path"];
        $lines = explode("\n", $content);
        $totalLines = count($lines);

        // Range-Lesen: from_line/to_line sind 1-basiert; out-of-range wird tolerant geclampt
        if ($fromLine !== null || $toLine !== null) {
            $from = max(1, (int) ($fromLine ?? 1));
            $to = min($totalLines, (int) ($toLine ?? $totalLines));
            if ($to < $from) return ['__error' => true, '__text' => "Ungültige Range: from=$from > to=$to"];
            $slice = array_slice($lines, $from - 1, $to - $from + 1);
            $numbered = [];
            foreach ($slice as $i => $line) $numbered[] = sprintf('%5d  %s', $from + $i, $line);
            return ['__text' => "Datei $path (Zeilen $from-$to von $totalLines):\n" . implode("\n", $numbered)];
        }

        // Volltext: bei großen Dateien (>500 Zeilen) Warnung mitschicken
        $numbered = [];
        foreach ($lines as $i => $line) $numbered[] = sprintf('%5d  %s', $i + 1, $line);
        $body = implode("\n", $numbered);
        $hint = '';
        if ($totalLines > 500) {
            $hint = "\n\n⚠ HINWEIS: Diese Datei hat $totalLines Zeilen und kostet bei jeder Folge-Iteration "
                  . "weitere ~" . (int) (strlen($content) / 4) . " Tokens an Input. "
                  . "Wenn du nur eine kleine Stelle ändern willst, nutze beim nächsten Mal "
                  . "search_code mit Pattern oder read_file mit from_line/to_line.";
        }
        return ['__text' => "Datei $path ($totalLines Zeilen):\n" . $body . $hint];
    }

    public function toolSearchCode(string $scope, string $query, string $dir = ''): array
    {
        if ($query === '' || mb_strlen($query) < 2) return ['__error' => true, '__text' => 'Suchbegriff zu kurz'];
        $baseDir = $dir !== '' ? $this->resolvePath($dir) : self::TRAIT_ROOT;
        if (!$baseDir || !is_dir($baseDir)) return ['__error' => true, '__text' => "Verzeichnis nicht gefunden: $dir"];
        if (!$this->isPathAllowed($baseDir, $scope)) return ['__error' => true, '__text' => "Verzeichnis außerhalb des Scopes: $dir"];

        $cmd = 'grep -rn --include="*.php" --include="*.js" --include="*.css" --include="*.html" --binary-files=without-match '
             . '--max-count=40 -F ' . escapeshellarg($query) . ' ' . escapeshellarg($baseDir) . ' 2>/dev/null | head -n 40';
        $lines = [];
        $rc = 0;
        @exec($cmd, $lines, $rc);
        if (empty($lines)) return ['__text' => 'Keine Treffer.'];
        $lines = array_slice($lines, 0, 40);
        $filtered = [];
        foreach ($lines as $line) {
            if (!preg_match('#^(.+?):(\d+):(.*)$#', $line, $m)) continue;
            $filePath = $m[1];
            if (!$this->isPathAllowed($filePath, $scope)) continue;
            $rel = str_replace(self::TRAIT_ROOT . '/', '', $filePath);
            $filtered[] = $rel . ':' . $m[2] . ' ' . mb_substr(trim($m[3]), 0, 200);
        }
        return ['__text' => empty($filtered) ? 'Keine Treffer im erlaubten Scope.' : implode("\n", $filtered)];
    }

    public function toolWriteFile(string $scope, string $path, string $content): array
    {
        if ($path === '') return ['__error' => true, '__text' => 'Pfad fehlt'];
        if (strlen($content) > self::TRAIT_MAX_FILE_BYTES) return ['__error' => true, '__text' => 'Inhalt zu groß (>500KB)'];

        $clean = ltrim($path, '/');
        if (strpos($clean, '..') !== false) return ['__error' => true, '__text' => 'Path-Traversal verboten'];
        $abs = self::TRAIT_ROOT . '/' . $clean;

        if (!$this->isPathAllowed($abs, $scope)) {
            return ['__error' => true, '__text' => "Pfad nicht im erlaubten Scope (" . $scope . "): $path"];
        }

        $existed = is_file($abs);
        $originalContent = $existed ? @file_get_contents($abs) : null;

        // Anti-Empty-Write-Schutz: KI darf eine Datei nicht versehentlich
        // drastisch verkürzen (z.B. wenn der Content beim Streaming abgeschnitten wurde).
        if ($existed && $originalContent !== false && strlen($originalContent) > 200) {
            $newLen = strlen($content);
            $oldLen = strlen($originalContent);
            if ($newLen === 0) {
                // Wahrscheinlichste Ursache: max_tokens überschritten, content-Parameter fehlt komplett.
                $lineCount = substr_count($originalContent, "\n") + 1;
                return ['__error' => true, '__text' =>
                    "ABGELEHNT: write_file ohne content aufgerufen (oder content leer). " .
                    "Die Datei hat $oldLen Bytes / $lineCount Zeilen. " .
                    "Vermutlich ist deine Antwort am max_tokens-Limit abgeschnitten worden. " .
                    "NICHT weiter probieren — die Datei ist zu groß für einen Turn. " .
                    "Stoppe hier mit done() und sag dem Admin: \"Datei zu groß für write_file in einem Schritt. " .
                    "Vorschlag: kleinere Datei nehmen oder Aufgabe aufteilen.\""
                ];
            }
            if ($newLen < $oldLen * 0.05) {
                return ['__error' => true, '__text' =>
                    "ABGELEHNT: Du wolltest eine Datei mit $oldLen Bytes auf $newLen Bytes verkleinern (<5%). " .
                    "Das ist fast immer ein Truncation-Fehler. " .
                    "Lies die Datei erneut mit read_file und sende den VOLLSTÄNDIGEN Inhalt — " .
                    "wenn die Datei zu groß für einen Turn ist, gib via done() Bescheid statt erneut zu probieren."
                ];
            }
            if (str_ends_with(strtolower($abs), '.php') && strpos($originalContent, '<?php') !== false && strpos($content, '<?php') === false) {
                return ['__error' => true, '__text' =>
                    "ABGELEHNT: PHP-Datei hatte <?php-Tag, neuer Inhalt nicht. Vermutlich abgeschnitten. " .
                    "Lies die Datei erneut und sende den vollständigen Inhalt — oder gib via done() auf wenn sie zu groß ist."];
            }
        }

        $dir = dirname($abs);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return ['__error' => true, '__text' => "Verzeichnis konnte nicht erstellt werden: " . dirname($path)];
            }
        }

        $snapshotId = null;
        if (!isset($this->snapshottedThisRun[$abs])) {
            $snapshotId = $this->persistWriteSnapshot($abs, $originalContent === false ? null : $originalContent, $content, $existed);
            $this->snapshottedThisRun[$abs] = $snapshotId;
        } else {
            $snapshotId = $this->snapshottedThisRun[$abs];
        }

        if (@file_put_contents($abs, $content) === false) {
            // Datei konnte nicht geschrieben werden → Geister-Snapshot rückwirkend
            // als rolled_back markieren, damit die History sauber bleibt.
            if ($snapshotId) $this->markSnapshotRolledBack((int) $snapshotId);
            return ['__error' => true, '__text' => "Datei konnte nicht geschrieben werden: $path (vermutlich fehlende Berechtigung)"];
        }

        $validation = $this->validateAfterWrite($abs);
        if (!$validation['ok']) {
            if ($existed) {
                @file_put_contents($abs, $originalContent);
            } else {
                @unlink($abs);
            }
            if ($snapshotId) $this->markSnapshotRolledBack((int) $snapshotId);
            return ['__error' => true, '__text' => "Syntax-Fehler nach Schreiben — Rollback durchgeführt:\n" . $validation['error']];
        }

        return ['__text' => "Datei geschrieben: $path (" . strlen($content) . " Bytes)" . ($existed ? '' : ' [NEU]')];
    }

    // ====== Validierung ======

    protected function validateAfterWrite(string $absPath): array
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $output = [];
            $rc = 0;
            exec('php -l ' . escapeshellarg($absPath) . ' 2>&1', $output, $rc);
            if ($rc !== 0) {
                return ['ok' => false, 'error' => implode("\n", $output)];
            }
        }
        if ($ext === 'js') {
            $content = @file_get_contents($absPath);
            if ($content !== false) {
                $open = substr_count($content, '{');
                $close = substr_count($content, '}');
                if (abs($open - $close) > 1) {
                    return ['ok' => false, 'error' => 'Ungleiche Klammeranzahl im JS (auf/zu: ' . $open . '/' . $close . ')'];
                }
            }
        }
        return ['ok' => true];
    }

    // ====== Pfad-Whitelist ======

    public function isPathAllowed(string $absPath, string $scope): bool
    {
        $clean = $this->normalizePath($absPath);
        if (strpos($clean, self::TRAIT_ROOT . '/') !== 0 && $clean !== self::TRAIT_ROOT) return false;
        $rel = ltrim(substr($clean, strlen(self::TRAIT_ROOT)), '/');

        foreach ($this->getForbiddenAlways() as $forbid) {
            if (str_starts_with($rel . '/', $forbid . '/') || $rel === rtrim($forbid, '/') || str_starts_with($rel, $forbid)) return false;
        }

        $allow = self::TRAIT_SCOPE_ALLOW[$scope] ?? [];
        foreach ($allow as $prefix) {
            if ($prefix === '') return true;
            if (str_starts_with($rel, $prefix) || str_starts_with($rel . '/', $prefix)) return true;
        }
        return false;
    }

    protected function resolvePath(string $rel): ?string
    {
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) return null;
        $abs = self::TRAIT_ROOT . '/' . $rel;
        $real = realpath($abs);
        return $real ?: $abs;
    }

    protected function normalizePath(string $path): string
    {
        $real = realpath($path);
        return $real ?: $path;
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . 'B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . 'K';
        return round($bytes / 1024 / 1024, 1) . 'M';
    }
}
