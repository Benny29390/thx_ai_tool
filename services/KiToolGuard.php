<?php
/**
 * KiToolGuard — deterministische, serverseitige Werkzeug-Absicherung (Spec §11).
 *
 * Zentrale Regel: Ein Werkzeug-Aufruf wird IMMER hier gegen ai_tool_permissions
 * geprüft — niemals durch Modell- oder Prompt-Ausgabe autorisiert. Hoch-Risiko-
 * Aktionen (versenden/löschen/verwalten) werden im MVP grundsätzlich geblockt
 * (menschliche Einzel-Freigabe erforderlich). Nur lesen/Entwurf laufen durch,
 * sofern eine freigegebene Berechtigung ausreicht.
 *
 * Enthält zugleich die Werkzeug-Registry (Definitionen im Anthropic-Tool-Format)
 * und die Ausführung — alle Datenzugriffe serverseitig und mandantengefiltert.
 */

namespace Services;

class KiToolGuard
{
    private $db;

    /** Rangfolge der Zugriffsstufen. */
    private const LEVELS = ['none' => 0, 'read' => 1, 'draft' => 2, 'write' => 3, 'execute' => 4, 'delete' => 5, 'admin' => 6];

    /** Werkzeug -> [tool_key, benoetigte Stufe, high_risk, beschreibung, schema]. */
    private const TOOLS = [
        'kunde_suchen' => [
            'tool_key' => 'customers', 'level' => 'read', 'high_risk' => false,
            'desc' => 'Sucht Kunden (Klienten) nach Name. Nur lesen.',
            'props' => ['query' => ['type' => 'string', 'description' => 'Suchbegriff (Name)']],
            'required' => ['query'],
        ],
        'projekt_suchen' => [
            'tool_key' => 'projects', 'level' => 'read', 'high_risk' => false,
            'desc' => 'Sucht Projekte nach Titel. Nur lesen.',
            'props' => ['query' => ['type' => 'string', 'description' => 'Suchbegriff (Titel)']],
            'required' => ['query'],
        ],
        'aufgabe_entwurf' => [
            'tool_key' => 'tasks', 'level' => 'draft', 'high_risk' => false,
            'desc' => 'Erstellt den ENTWURF einer Aufgabe (kein echtes Anlegen) und gibt ihn zurück.',
            'props' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'customer' => ['type' => 'string']],
            'required' => ['title'],
        ],
        'email_entwurf' => [
            'tool_key' => 'email', 'level' => 'draft', 'high_risk' => false,
            'desc' => 'Formuliert den ENTWURF einer E-Mail (Betreff + Text). Versendet NICHT.',
            'props' => ['empfaenger' => ['type' => 'string'], 'anliegen' => ['type' => 'string']],
            'required' => ['anliegen'],
        ],
        'email_senden' => [
            'tool_key' => 'email', 'level' => 'execute', 'high_risk' => true,
            'desc' => 'Versendet eine E-Mail. HOCH-RISIKO — benötigt menschliche Einzel-Freigabe.',
            'props' => ['empfaenger' => ['type' => 'string'], 'betreff' => ['type' => 'string'], 'text' => ['type' => 'string']],
            'required' => ['empfaenger', 'betreff', 'text'],
        ],
    ];

    public function __construct($db = null)
    {
        $this->db = $db ?: \Core\Database::getInstance();
    }

    /** Freigegebene Zugriffsstufe eines Mitarbeiters für ein tool_key (höchste). */
    public function grantedLevel(int $employeeId, string $toolKey): string
    {
        $rows = $this->db->query(
            "SELECT permission_level FROM ai_tool_permissions
             WHERE ai_employee_id = ? AND tool_key = ? AND status = 'approved'
               AND (expires_at IS NULL OR expires_at > NOW())",
            [$employeeId, $toolKey]
        );
        $best = 'none';
        foreach ($rows as $r) {
            if ((self::LEVELS[$r['permission_level']] ?? 0) > (self::LEVELS[$best] ?? 0)) {
                $best = $r['permission_level'];
            }
        }
        return $best;
    }

    /**
     * Deterministische Entscheidung: darf $toolName laufen?
     * @return array{allowed:bool, reason:string, high_risk:bool}
     */
    public function check(int $employeeId, string $toolName): array
    {
        $def = self::TOOLS[$toolName] ?? null;
        if (!$def) return ['allowed' => false, 'reason' => 'Unbekanntes Werkzeug.', 'high_risk' => false];
        if (!empty($def['high_risk'])) {
            // Hoch-Risiko im MVP immer sperren (menschliche Einzel-Freigabe erforderlich).
            return ['allowed' => false, 'reason' => 'Hoch-Risiko-Aktion — benötigt menschliche Einzel-Freigabe.', 'high_risk' => true];
        }
        $granted = $this->grantedLevel($employeeId, $def['tool_key']);
        $ok = (self::LEVELS[$granted] ?? 0) >= (self::LEVELS[$def['level']] ?? 99);
        return [
            'allowed' => $ok,
            'reason'  => $ok ? 'ok' : ("Kein freigegebener Zugriff '{$def['level']}' auf '{$def['tool_key']}' (aktuell: $granted)."),
            'high_risk' => false,
        ];
    }

    /** Werkzeug-Definitionen im Anthropic-Format (nur die, die grundsaetzlich erlaubt sind). */
    public function toolDefs(): array
    {
        $defs = [];
        foreach (self::TOOLS as $name => $d) {
            $defs[] = [
                'name' => $name,
                'description' => $d['desc'],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => $d['props'],
                    'required' => $d['required'] ?? [],
                ],
            ];
        }
        return $defs;
    }

    /**
     * Führt ein Werkzeug aus, NACHDEM check() erlaubt hat. Datenzugriffe sind
     * serverseitig mandantengefiltert (nur erlaubte Kunden).
     * @return array Ergebnis (an das Modell als tool_result zurueckgegeben)
     */
    public function execute(string $toolName, array $args, int $employeeId): array
    {
        $allowedCustomers = \Core\Auth::isAdmin() ? null : \Core\Auth::customers();
        $custFilter = function (string $alias = 'id') use ($allowedCustomers) {
            if ($allowedCustomers === null) return '';
            if (empty($allowedCustomers)) return " AND 1=0";
            return " AND $alias IN (" . implode(',', array_map('intval', $allowedCustomers)) . ')';
        };

        switch ($toolName) {
            case 'kunde_suchen':
                $q = '%' . ($args['query'] ?? '') . '%';
                $rows = $this->db->query("SELECT id, name FROM customers WHERE name LIKE ?" . $custFilter('id') . " LIMIT 10", [$q]);
                return ['treffer' => $rows];
            case 'projekt_suchen':
                $q = '%' . ($args['query'] ?? '') . '%';
                $rows = $this->db->query("SELECT id, title, customer_id, status FROM projects WHERE title LIKE ?" . $custFilter('customer_id') . " LIMIT 10", [$q]);
                return ['treffer' => $rows];
            case 'aufgabe_entwurf':
                return ['entwurf' => [
                    'typ' => 'aufgabe', 'title' => $args['title'] ?? '', 'description' => $args['description'] ?? '',
                    'customer' => $args['customer'] ?? null, 'hinweis' => 'Entwurf — noch nicht angelegt.',
                ]];
            case 'email_entwurf':
                return ['entwurf' => [
                    'typ' => 'email', 'empfaenger' => $args['empfaenger'] ?? '', 'anliegen' => $args['anliegen'] ?? '',
                    'hinweis' => 'Entwurf — noch nicht versendet.',
                ]];
            default:
                return ['fehler' => 'Werkzeug nicht ausführbar.'];
        }
    }

    public function isHighRisk(string $toolName): bool
    {
        return !empty(self::TOOLS[$toolName]['high_risk']);
    }
}
