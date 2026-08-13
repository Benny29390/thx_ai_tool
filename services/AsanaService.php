<?php
/**
 * Asana API Wrapper — READ-ONLY.
 *
 * Diese Klasse enthaelt AUSSCHLIESSLICH GET-Endpoints. Niemals POST/PUT/DELETE
 * gegen Asana ausfuehren — Asana ist die Source of Truth.
 *
 * API-Doku: https://developers.asana.com/reference
 */

namespace Services;

class AsanaService
{
    private const API_BASE = 'https://app.asana.com/api/1.0';
    private string $pat;
    private int $timeout;
    private array $rateLimitTimestamps = [];

    public function __construct(string $pat, int $timeout = 15)
    {
        $this->pat = $pat;
        $this->timeout = $timeout;
    }

    public function isConfigured(): bool
    {
        return !empty($this->pat);
    }

    /**
     * Extrahiert die Task-GID aus
     *   - einer reinen GID („1234567890123456")
     *   - einer Asana-Task-URL („https://app.asana.com/0/projekt/12345" oder „…/0/0/12345/f")
     *   - einem URL-Permalink mit beliebigem Suffix
     * Gibt null zurück wenn sich keine plausible GID extrahieren lässt.
     */
    public static function extrahiereTaskGid(string $eingabe): ?string
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '') return null;

        // Reine numerische GID (10–25 Stellen wie Asana es vergibt)
        if (preg_match('/^\d{6,25}$/', $eingabe)) return $eingabe;

        // Asana-URL: nehme das letzte numerische Segment im Pfad
        // Typische Formate:
        //   https://app.asana.com/0/<projekt-gid>/<task-gid>
        //   https://app.asana.com/0/<projekt-gid>/<task-gid>/f
        //   https://app.asana.com/0/0/<task-gid>
        //   https://app.asana.com/1/<workspace>/projects/<projekt>/task/<task-gid>
        if (preg_match_all('#(\d{6,25})#', $eingabe, $treffer) && !empty($treffer[1])) {
            $kandidaten = $treffer[1];
            // Beim klassischen /0/<projekt>/<task>-Format ist die Task-GID das letzte Segment.
            // Wir nehmen die letzte gefundene Zahl als beste Heuristik.
            return end($kandidaten);
        }

        return null;
    }

    /**
     * Verbindungstest — gibt true zurueck wenn der PAT gueltig ist.
     */
    public function testConnection(): array
    {
        try {
            $me = $this->get('/users/me');
            return ['ok' => true, 'user' => $me];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function listWorkspaces(): array
    {
        $data = $this->get('/workspaces', ['opt_fields' => 'name,gid,is_organization']);
        return $data ?? [];
    }

    /**
     * Liste aller Projekte eines Workspaces (paginiert komplett durch).
     */
    public function listProjects(string $workspaceGid, bool $archived = false): array
    {
        $all = [];
        $offset = null;
        do {
            $params = [
                'workspace' => $workspaceGid,
                'archived' => $archived ? 'true' : 'false',
                'limit' => 100,
                'opt_fields' => 'name,gid,archived,modified_at,team.name,owner.name',
            ];
            if ($offset) $params['offset'] = $offset;
            $resp = $this->getRaw('/projects', $params);
            $data = $resp['data'] ?? [];
            $all = array_merge($all, $data);
            $offset = $resp['next_page']['offset'] ?? null;
        } while ($offset);
        return $all;
    }

    public function getProject(string $projectGid): array
    {
        return $this->get('/projects/' . $projectGid, [
            'opt_fields' => 'name,gid,notes,html_notes,owner.name,team.name,created_at,modified_at,public,archived,members.name,members.gid,custom_field_settings.custom_field.name',
        ]) ?? [];
    }

    /**
     * Tasks eines Projekts mit allen relevanten Feldern.
     * @param string $projectGid
     * @param ?\DateTime $modifiedSince Wenn gesetzt: nur Tasks mit modified_at > X
     */
    public function getTasks(string $projectGid, ?\DateTime $modifiedSince = null): array
    {
        $all = [];
        $offset = null;
        $params = [
            'project' => $projectGid,
            'limit' => 100,
            'opt_fields' => 'name,gid,notes,html_notes,completed,completed_at,due_on,due_at,start_on,assignee.name,assignee.gid,assignee.email,created_at,modified_at,parent.gid,custom_fields.name,custom_fields.display_value,custom_fields.text_value,custom_fields.number_value,custom_fields.enum_value.name,tags.name,num_subtasks,permalink_url',
        ];
        if ($modifiedSince) {
            $params['modified_since'] = $modifiedSince->format(\DateTime::ATOM);
        }
        do {
            if ($offset) $params['offset'] = $offset;
            $resp = $this->getRaw('/tasks', $params);
            $data = $resp['data'] ?? [];
            $all = array_merge($all, $data);
            $offset = $resp['next_page']['offset'] ?? null;
        } while ($offset);
        return $all;
    }

    /**
     * Stories (Comments + History) eines Tasks. Default: nur Kommentare.
     */
    public function getTaskStories(string $taskGid, bool $commentsOnly = true): array
    {
        $stories = $this->get('/tasks/' . $taskGid . '/stories', [
            'opt_fields' => 'gid,created_at,created_by.name,created_by.email,text,html_text,type,resource_subtype',
        ]) ?? [];
        if ($commentsOnly) {
            $stories = array_values(array_filter($stories, fn($s) => ($s['type'] ?? '') === 'comment'));
        }
        return $stories;
    }

    public function getTaskAttachments(string $taskGid): array
    {
        return $this->get('/tasks/' . $taskGid . '/attachments', [
            'opt_fields' => 'gid,name,download_url,view_url,host,size,created_at,resource_type',
        ]) ?? [];
    }

    /**
     * Laedt eine Attachment-Datei runter und liefert den temporaeren Pfad zurueck.
     * Gibt null zurueck bei Fehler.
     */
    public function downloadAttachment(string $downloadUrl, ?string $suggestedName = null): ?string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'asana_att_');
        if ($suggestedName) {
            $ext = pathinfo($suggestedName, PATHINFO_EXTENSION);
            if ($ext) {
                @rename($tmpFile, $tmpFile . '.' . $ext);
                $tmpFile .= '.' . $ext;
            }
        }
        $fp = fopen($tmpFile, 'wb');
        if (!$fp) return null;

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->pat],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_MAXFILESIZE => 50 * 1024 * 1024,
        ]);
        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode !== 200) {
            @unlink($tmpFile);
            return null;
        }
        return $tmpFile;
    }

    public function findUserByEmail(string $workspaceGid, string $email): ?array
    {
        $users = $this->listUsers($workspaceGid);
        $email = mb_strtolower(trim($email));
        foreach ($users as $u) {
            if (mb_strtolower($u['email'] ?? '') === $email) return $u;
        }
        return null;
    }

    /**
     * Liste aller User in einem Workspace.
     */
    public function listUsers(string $workspaceGid): array
    {
        return $this->get('/users', [
            'workspace' => $workspaceGid,
            'opt_fields' => 'name,gid,email',
        ]) ?? [];
    }

    /**
     * Sections (Spalten) eines Projekts.
     */
    public function listSections(string $projectGid): array
    {
        return $this->get("/projects/$projectGid/sections", [
            'opt_fields' => 'name,gid',
        ]) ?? [];
    }

    /**
     * Tasks in einer Section (max 100). Sliding-Window-Pagination nicht nötig
     * für die LAM-UI-Anbindung (Kunden-Linkoptionen-Section bleibt kompakt).
     */
    public function listTasksInSection(string $sectionGid, int $limit = 50): array
    {
        return $this->get("/sections/$sectionGid/tasks", [
            'opt_fields' => 'name,gid,completed,due_on,assignee.name,permalink_url',
            'limit' => $limit,
        ]) ?? [];
    }

    /**
     * Detail einer einzelnen Task.
     */
    public function getTask(string $taskGid): ?array
    {
        return $this->get("/tasks/$taskGid", [
            'opt_fields' => 'name,gid,notes,completed,completed_at,assignee.name,assignee.gid,due_on,due_at,modified_at,permalink_url,projects.name,projects.gid',
        ]);
    }

    /**
     * Tasks holen, die einer Person zugewiesen sind. Fuer den Tagesplaner.
     * Asana erlaubt assignee + workspace als kombiniertes Filter — pro Workspace ein Aufruf.
     * Default: nur nicht-erledigte Tasks (completed_since=now bedeutet "completed seit jetzt" = quasi nichts → ungewollt; daher kein completed_since).
     * Filterung "open only" muss client-seitig per `completed`-Flag erfolgen.
     */
    public function getAssignedTasks(string $workspaceGid, string $assigneeGid, ?\DateTime $modifiedSince = null): array
    {
        // WICHTIG: Asana liefert auf /tasks?assignee=X&workspace=Y OHNE completed_since
        // sowohl offene als auch ALLE jemals abgeschlossenen Tasks (Historie ueber Jahre).
        // Mit completed_since='now' werden nur Tasks zurueckgegeben, die NACH 'now' completed wurden
        // — also faktisch nur die offenen plus ggf. ganz frisch erledigte. Genau was wir wollen.
        $all = [];
        $offset = null;
        $params = [
            'assignee' => $assigneeGid,
            'workspace' => $workspaceGid,
            'completed_since' => 'now',
            'limit' => 100,
            'opt_fields' => 'name,gid,notes,completed,completed_at,due_on,due_at,assignee.gid,modified_at,permalink_url,projects.gid,projects.name',
        ];
        if ($modifiedSince) {
            $params['modified_since'] = $modifiedSince->format(\DateTime::ATOM);
        }
        do {
            if ($offset) $params['offset'] = $offset;
            $resp = $this->getRaw('/tasks', $params);
            $all = array_merge($all, $resp['data'] ?? []);
            $offset = $resp['next_page']['offset'] ?? null;
        } while ($offset);
        return $all;
    }

    /**
     * "Wer bin ich?" — Asana-Identitaet zum gegebenen PAT. Wird beim Setup gebraucht,
     * um den asana_user_gid des Users automatisch zu holen.
     */
    public function getMe(): ?array
    {
        return $this->get('/users/me', [
            'opt_fields' => 'gid,name,email,workspaces.gid,workspaces.name',
        ]);
    }

    /**
     * Neue Task anlegen. Returnt das gid des angelegten Tasks.
     */
    public function createTask(string $projectGid, string $name, ?string $sectionGid = null, ?string $notes = null, ?string $assigneeGid = null, ?string $htmlNotes = null, ?string $dueOn = null): array
    {
        $body = ['data' => [
            'name' => $name,
            'projects' => [$projectGid],
        ]];
        // html_notes hat Vorrang (für formatierte Beschreibung mit <u>Linktext</u>)
        if ($htmlNotes !== null && $htmlNotes !== '') $body['data']['html_notes'] = $htmlNotes;
        elseif ($notes !== null && $notes !== '') $body['data']['notes'] = $notes;
        if ($assigneeGid) $body['data']['assignee'] = $assigneeGid;
        if ($dueOn) $body['data']['due_on'] = $dueOn;   // YYYY-MM-DD

        $result = $this->post('/tasks', $body);
        if (!$result) throw new \RuntimeException('Task konnte nicht erstellt werden');

        // Section setzen (separater Call, weil Asana das nicht beim Create unterstützt)
        if ($sectionGid && !empty($result['gid'])) {
            try {
                $this->post("/sections/$sectionGid/addTask", ['data' => ['task' => $result['gid']]]);
            } catch (\Throwable $e) {
                // Nicht fatal — Task ist im Projekt, nur ohne Section
            }
        }
        return $result;
    }

    /**
     * Tasks suchen — über getTasks(projectGid) + Client-side Filter.
     * Server-side Asana-Search via /workspaces/{gid}/tasks/search wäre besser, ist aber Premium.
     */
    public function searchTasks(string $projectGid, string $query, int $limit = 30, ?string $workspaceGid = null): array
    {
        $q = trim($query);

        // Mit Suchbegriff: Asana-Volltextsuche nutzen. Sie findet auch UNTERAUFGABEN
        // (z.B. einzelne Blogartikel als Subtask), die in der reinen Projekt-Taskliste
        // NICHT auftauchen. Projektweit eingegrenzt via projects.any.
        if ($q !== '') {
            $ws = $workspaceGid ?: $this->getProjectWorkspaceGid($projectGid);
            if ($ws) {
                try {
                    $res = $this->get("/workspaces/$ws/tasks/search", [
                        'text'         => $q,
                        'projects.any' => $projectGid,
                        'opt_fields'   => 'name,gid,completed,permalink_url,parent.name',
                        'limit'        => min(100, max(1, $limit)),
                    ]);
                    if (is_array($res)) return array_slice($res, 0, $limit);
                } catch (\Throwable $e) {
                    // Fallback unten (z.B. wenn Such-API im Asana-Plan nicht verfuegbar)
                }
            }
        }

        // Fallback / leere Suche: Top-Level-Tasks des Projekts, Namensfilter.
        $all = $this->get("/projects/$projectGid/tasks", [
            'opt_fields' => 'name,gid,completed,permalink_url',
            'limit' => 100,
        ]) ?? [];
        $ql = mb_strtolower($q);
        if ($ql === '') return array_slice($all, 0, $limit);
        $hits = [];
        foreach ($all as $t) {
            if (mb_strpos(mb_strtolower($t['name'] ?? ''), $ql) !== false) {
                $hits[] = $t;
                if (count($hits) >= $limit) break;
            }
        }
        return $hits;
    }

    /** Workspace-GID zu einem Projekt ermitteln (fuer die Volltextsuche). */
    private function getProjectWorkspaceGid(string $projectGid): ?string
    {
        try {
            $p = $this->get("/projects/$projectGid", ['opt_fields' => 'workspace.gid']);
            return $p['workspace']['gid'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Unteraufgabe (Subtask) unter einer Eltern-Task anlegen.
     */
    public function createSubtask(string $parentGid, string $name, ?string $notes = null, ?string $htmlNotes = null, ?string $dueOn = null): array
    {
        $body = ['data' => ['name' => $name]];
        if ($htmlNotes !== null && $htmlNotes !== '') $body['data']['html_notes'] = $htmlNotes;
        elseif ($notes !== null && $notes !== '') $body['data']['notes'] = $notes;
        if ($dueOn) $body['data']['due_on'] = $dueOn;   // YYYY-MM-DD
        $result = $this->post("/tasks/$parentGid/subtasks", $body);
        if (!$result) throw new \RuntimeException('Subtask konnte nicht erstellt werden');
        return $result;
    }

    /**
     * Unteraufgabe innerhalb der Eltern-Task umsortieren.
     * $insertAfterGid = null -> an den Anfang. Sonst hinter die angegebene Unteraufgabe.
     */
    public function moveSubtask(string $subtaskGid, string $parentGid, ?string $insertAfterGid): array
    {
        $data = ['parent' => $parentGid];
        if ($insertAfterGid === null) $data['insert_after'] = null; // an den Anfang
        else $data['insert_after'] = $insertAfterGid;
        $result = $this->post("/tasks/$subtaskGid/setParent", ['data' => $data]);
        return $result ?? [];
    }

    /**
     * Felder einer Task aktualisieren (z.B. notes/name).
     */
    public function updateTask(string $taskGid, array $fields): array
    {
        return $this->request('PUT', "/tasks/$taskGid", ['data' => $fields]) ?? [];
    }

    /**
     * Task (z.B. Unteraufgabe) loeschen.
     */
    public function deleteTask(string $taskGid): void
    {
        $this->request('DELETE', "/tasks/$taskGid", null);
    }

    /**
     * Generischer Request (PUT/DELETE/POST) mit PAT.
     */
    private function request(string $method, string $path, ?array $body): ?array
    {
        if (empty($this->pat)) throw new \RuntimeException('Asana PAT nicht konfiguriert');
        $this->throttle();
        $ch = curl_init(self::API_BASE . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("Asana $method $path failed: HTTP $code — " . substr((string) $resp, 0, 200));
        }
        $decoded = json_decode((string) $resp, true);
        return $decoded['data'] ?? null;
    }

    /**
     * Unteraufgaben einer Task auflisten.
     */
    public function getSubtasks(string $parentGid): array
    {
        return $this->get("/tasks/$parentGid/subtasks", [
            'opt_fields' => 'name,gid,completed,permalink_url',
            'limit' => 100,
        ]) ?? [];
    }

    /**
     * Generic POST.
     */
    private function post(string $path, array $body): ?array
    {
        if (empty($this->pat)) {
            throw new \RuntimeException('Asana PAT nicht konfiguriert');
        }
        $this->throttle();

        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Asana POST ' . $path . ' failed: HTTP ' . $code . ' — ' . substr((string) $body, 0, 200));
        }
        $decoded = json_decode((string) $body, true);
        return $decoded['data'] ?? null;
    }

    /**
     * Generic GET — gibt nur den 'data'-Inhalt zurueck.
     */
    private function get(string $path, array $params = []): mixed
    {
        $resp = $this->getRaw($path, $params);
        return $resp['data'] ?? null;
    }

    /**
     * Generic GET — gibt das gesamte Response-Object inkl. next_page zurueck.
     * Throws on non-2xx.
     */
    private function getRaw(string $path, array $params = []): array
    {
        if (empty($this->pat)) {
            throw new \RuntimeException('Asana PAT nicht konfiguriert');
        }

        $this->throttle();

        $url = self::API_BASE . $path;
        if (!empty($params)) $url .= '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->pat,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException('Asana-Anfrage fehlgeschlagen: ' . $err);

        $json = json_decode($body, true);

        if ($httpCode === 401) {
            throw new \RuntimeException('Asana PAT abgelaufen oder ungueltig (401)');
        }
        if ($httpCode === 403) {
            throw new \RuntimeException('Asana: kein Zugriff auf diese Ressource (403)');
        }
        if ($httpCode === 429) {
            throw new \RuntimeException('Asana Rate-Limit erreicht (429), kurz warten');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $json['errors'][0]['message'] ?? 'HTTP ' . $httpCode;
            throw new \RuntimeException('Asana-Fehler: ' . $msg);
        }

        return is_array($json) ? $json : [];
    }

    /**
     * Einfaches Sliding-Window-Throttling: max 150 req/min.
     */
    private function throttle(): void
    {
        $now = microtime(true);
        $oneMinAgo = $now - 60;
        $this->rateLimitTimestamps = array_filter($this->rateLimitTimestamps, fn($t) => $t > $oneMinAgo);
        if (count($this->rateLimitTimestamps) >= 145) {
            $sleepFor = 60 - ($now - reset($this->rateLimitTimestamps));
            if ($sleepFor > 0) usleep((int)($sleepFor * 1_000_000));
        }
        $this->rateLimitTimestamps[] = $now;
    }
}
