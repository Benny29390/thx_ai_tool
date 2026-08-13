<?php

namespace Services;

use Core\Database;

/**
 * Matcht von der KI extrahierte Personen-Namen gegen die users-Tabelle und
 * fuellt E-Mail / Kuerzel / Initialen aus den Stammdaten an, statt sie die KI
 * halluzinieren zu lassen. Wird sowohl von SteckbriefImportService (Stufe A)
 * als auch SteckbriefSuggestionService (Stufe B) genutzt.
 */
class UserDirectoryMatcher
{
    private Database $db;
    private ?array $index = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Reichert ein People-Array (wie in contacts-Karten-Body) an.
     *
     * @param array $people  Liste mit Eintraegen {name, role?, initials?, email?, phone?}
     * @param bool  $internal  true = Thoxan-Team-Gruppe, da werden Stammdaten ueberschrieben
     */
    public function enrichPeople(array $people, bool $internal = false): array
    {
        $this->loadIndex();
        $out = [];
        foreach ($people as $p) {
            if (empty($p['name']) && empty($p['role'])) continue;
            $matched = $this->matchUser((string) ($p['name'] ?? ''));
            if ($matched) {
                $p['name']     = $matched['name'];
                $p['email']    = $matched['email'] ?? '';
                $p['initials'] = $matched['abbreviation'] ?: $this->makeInitials($matched['name']);
                $p['user_id']  = (int) $matched['id'];
            } else {
                // Halluzinierte Initialen ersetzen
                $supplied = (string) ($p['initials'] ?? '');
                if ($supplied === '' || mb_strlen($supplied) > 4 || !$this->matchesNameInitials($supplied, (string) ($p['name'] ?? ''))) {
                    $p['initials'] = $this->makeInitials((string) ($p['name'] ?? ''));
                }
                // Externe Mail: nur uebernehmen wenn syntaktisch ok
                if (!empty($p['email']) && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
                    $p['email'] = '';
                }
            }
            $out[] = $p;
        }
        return $out;
    }

    /**
     * Pruefen ob eine ganze Gruppen-Liste angereichert werden soll: interne
     * Gruppen ("Intern", "Thoxan", "Team") werden hart ueberschrieben.
     */
    public function enrichGroups(array $groups): array
    {
        $out = [];
        foreach ($groups as $g) {
            $title = (string) ($g['title'] ?? '');
            $isInternal = $this->looksInternal($title);
            $people = $this->enrichPeople($g['people'] ?? [], $isInternal);
            $out[] = ['title' => $title, 'people' => $people];
        }
        return $out;
    }

    public function matchUser(string $name): ?array
    {
        $this->loadIndex();
        $name = trim($name);
        if ($name === '') return null;
        $norm = $this->normalize($name);
        if (isset($this->index[$norm]) && is_array($this->index[$norm])) return $this->index[$norm];

        // Nachname-Fallback (eindeutiger Nachname)
        $parts = preg_split('/\s+/', $name);
        if (count($parts) >= 2) {
            $last = $this->normalize(end($parts));
            $key = '__last:' . $last;
            if (isset($this->index[$key]) && is_array($this->index[$key])) return $this->index[$key];
        } else {
            $single = $this->normalize($name);
            $key = '__last:' . $single;
            if (isset($this->index[$key]) && is_array($this->index[$key])) return $this->index[$key];
        }
        return null;
    }

    private function loadIndex(): void
    {
        if ($this->index !== null) return;
        $rows = $this->db->query(
            "SELECT id, name, email, abbreviation, nickname FROM users WHERE is_active = 1"
        ) ?: [];
        $index = [];
        foreach ($rows as $u) {
            $name = trim((string) $u['name']);
            if ($name === '') continue;
            $full = $this->normalize($name);
            $index[$full] = $u;
            $parts = preg_split('/\s+/', $name);
            if (count($parts) >= 2) {
                $last = $this->normalize(end($parts));
                $key = '__last:' . $last;
                if (!isset($index[$key])) $index[$key] = $u;
                else $index[$key] = false; // mehrdeutig
            }
            $nick = trim((string) ($u['nickname'] ?? ''));
            if ($nick !== '') {
                $nickKey = $this->normalize($nick);
                if (!isset($index[$nickKey])) $index[$nickKey] = $u;
            }
        }
        $this->index = $index;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        $s = preg_replace('/[^a-z0-9 ]+/', '', $s);
        return preg_replace('/\s+/', ' ', (string) $s);
    }

    /**
     * Thoxan-Initialen: 3 Zeichen = 1 Buchstabe Vorname + 2 Buchstaben Nachname.
     * Beispiele: Thomas Kilian -> TKI, Milena Schürmeyer -> MSC, Laura Märk -> LMA.
     * Umlaute werden transliteriert (Ä -> A, Ö -> O, Ü -> U, ß -> SS).
     */
    private function makeInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $parts = preg_split('/\s+/', $name);
        $translit = fn(string $s) => strtr(mb_strtoupper($s), [
            'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'ß' => 'SS', 'ẞ' => 'SS',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U',
        ]);
        if (count($parts) >= 2) {
            $first = $translit(mb_substr($parts[0], 0, 1));
            $last = $translit(mb_substr(end($parts), 0, 2));
            return $first . $last;
        }
        // Nur ein Wort: erste 3 Buchstaben
        return $translit(mb_substr($parts[0], 0, 3));
    }

    private function matchesNameInitials(string $initials, string $name): bool
    {
        $exp = $this->makeInitials($name);
        return mb_strtoupper($initials) === $exp;
    }

    private function looksInternal(string $title): bool
    {
        $t = mb_strtolower($title);
        return str_contains($t, 'intern') || str_contains($t, 'thoxan') || str_contains($t, 'team');
    }

    /**
     * Bestimmt anhand des Karten-Titels, ob die Karte fuer Thoxan-Mitarbeitende
     * (internal) oder fuer Kunden-Personen (external) gedacht ist.
     * Default ist 'external' — eine Karte „Ansprechpartner" meint die Kundenseite.
     */
    public function cardAudience(string $cardTitle): string
    {
        $t = mb_strtolower(trim($cardTitle));
        if ($t === '') return 'unknown';
        $internalHints = ['thoxan', 'team thoxan', 'unser team', 'agentur', 'intern'];
        foreach ($internalHints as $hint) {
            if (str_contains($t, $hint)) return 'internal';
        }
        // 'kunde' im Titel ist starker Hinweis auf externe Karte
        $externalHints = ['kunde', 'kundenseite', 'extern', 'ansprechpartner'];
        foreach ($externalHints as $hint) {
            if (str_contains($t, $hint)) return 'external';
        }
        return 'unknown';
    }

    /**
     * Kompakte Klartext-Liste aller aktiven Thoxan-User fuer den LLM-Prompt.
     * Format: "Name (Kuerzel) - email" pro Zeile.
     */
    public function rosterForPrompt(int $limit = 50): string
    {
        $this->loadIndex();
        $rows = $this->db->query(
            "SELECT name, email, abbreviation FROM users WHERE is_active = 1 ORDER BY name LIMIT ?",
            [$limit]
        ) ?: [];
        $lines = [];
        foreach ($rows as $u) {
            $line = $u['name'];
            if (!empty($u['abbreviation'])) $line .= ' (' . $u['abbreviation'] . ')';
            if (!empty($u['email'])) $line .= ' - ' . $u['email'];
            $lines[] = '- ' . $line;
        }
        return implode("\n", $lines);
    }

    /**
     * Filtert eine People-Liste danach, ob die Personen zur Audience passen.
     * - internal: nur User mit Match in der users-Tabelle
     * - external: nur Personen OHNE Match (echte Kunden-Kontakte)
     * - unknown: keine Filterung
     */
    public function filterPeopleByAudience(array $people, string $audience): array
    {
        if ($audience === 'unknown') return $people;
        $out = [];
        foreach ($people as $p) {
            $isUser = !empty($p['user_id']) || $this->matchUser((string) ($p['name'] ?? '')) !== null;
            if ($audience === 'internal' && $isUser) $out[] = $p;
            elseif ($audience === 'external' && !$isUser) $out[] = $p;
        }
        return $out;
    }
}
