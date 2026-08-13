<?php
namespace Services;

use Core\Database;
use Core\Crypto;

/**
 * Tagesplaner — Sync von Asana-Tasks pro User.
 *
 * Modell:
 *  - Jeder User hat einen eigenen Asana-PAT (users.asana_user_pat, AES-256-GCM verschlüsselt).
 *  - Sync lädt alle Tasks, die in Asana dem User zugewiesen sind, über alle Workspaces hinweg,
 *    in die lokale Tabelle planner_tasks.
 *  - Kunden-Resolution geschieht via customers.asana_projekt_gid → planner_tasks.customer_id.
 *  - Bereits in Asana abgeschlossene Tasks (completed=1) werden lokal als
 *    completed_at_asana gespeichert und im Standard-View ausgeblendet.
 *  - Lokale Markierung "erledigt" (completed_at_local) wird beim Sync nicht überschrieben —
 *    der User kann eine Task lokal abhaken, ohne dass Asana angetastet wird.
 *
 * STATUS-REGEL (kanonisch, nicht ändern ohne Rücksprache):
 *  - Asana führt für den Erledigt-Status: completed_at_asana spiegelt 1:1 t.completed.
 *  - completed_at_local ist die LOKALE Abhakmarkierung. Sie wird NIE durch den Sync verändert,
 *    auch wenn die Asana-Task neue Kommentare bekommt (asana_modified_at advanced).
 *  - "Open" im Tagesplan = !completed_at_local && !completed_at_asana — eines reicht, um die
 *    Task als erledigt auszublenden. Damit bleibt eine lokal abgehakte Task selbst dann
 *    zu, wenn jemand in Asana noch Kommentare nachreicht.
 *  - Re-Aktivieren: nur über Phase 7 (Archiv) → POST /complete {completed:false} → setzt
 *    completed_at_local=null. Falls completed_at_asana auch gesetzt ist (Asana ist done),
 *    bleibt die Task trotzdem zu (Asana führt) — dann muss zuerst in Asana reopened werden.
 */
class PlannerSyncService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Sync über ALLE aktiven Asana-Accounts eines Users.
     * Backwards-compatible Wrapper: ein User kann mehrere PATs hinterlegen
     * (z.B. Thoxan + Hills & Valleys ehrenamtlich), jeder Account wird einzeln gesynct.
     */
    public function syncForUser(int $userId): array
    {
        $user = $this->db->queryOne("SELECT id FROM users WHERE id = ?", [$userId]);
        if (!$user) throw new \RuntimeException('User nicht gefunden');

        $accounts = $this->db->query(
            "SELECT id, account_label, asana_user_pat, asana_user_gid, default_customer_id
             FROM user_asana_accounts
             WHERE user_id = ? AND is_active = 1
             ORDER BY sort_order ASC, id ASC",
            [$userId]
        ) ?: [];
        if (empty($accounts)) {
            throw new \RuntimeException('Du hast noch keinen Asana-PAT hinterlegt. Bitte unter Mein Asana eintragen.');
        }

        $totalStats = ['accounts_checked' => 0, 'tasks_seen' => 0, 'tasks_created' => 0, 'tasks_updated' => 0, 'tasks_completed_in_asana' => 0];
        $errors = [];
        foreach ($accounts as $account) {
            try {
                $s = $this->syncOneAccount($userId, $account);
                foreach ($s as $k => $v) {
                    if (is_numeric($v)) $totalStats[$k] = ($totalStats[$k] ?? 0) + $v;
                    else $totalStats[$k] = $v;
                }
                $totalStats['accounts_checked']++;
            } catch (\Throwable $e) {
                $errors[] = "Account '{$account['account_label']}': " . $e->getMessage();
                error_log("PlannerSync account #{$account['id']} ({$account['account_label']}) fehlgeschlagen: " . $e->getMessage());
            }
        }
        if (!empty($errors)) $totalStats['errors'] = $errors;
        return $totalStats;
    }

    /**
     * Sync für EINEN Account (= ein PAT). Das ist die Logik, die früher syncForUser war.
     */
    private function syncOneAccount(int $userId, array $account): array
    {
        $accountId = (int)$account['id'];
        $patEnc = $account['asana_user_pat'] ?? '';
        if ($patEnc === '') {
            throw new \RuntimeException("Kein PAT für Account '{$account['account_label']}'");
        }
        $pat = Crypto::decrypt($patEnc);
        $asana = new AsanaService($pat);

        // Asana-User-GID resolven, falls noch nicht im Account hinterlegt
        $assigneeGid = $account['asana_user_gid'] ?? '';
        $me = $asana->getMe();
        if (!$me) {
            throw new \RuntimeException("Konnte Asana-Identität nicht laden (Account '{$account['account_label']}'). Ist der PAT noch gültig?");
        }
        if (empty($assigneeGid)) {
            $assigneeGid = (string)($me['gid'] ?? '');
            if ($assigneeGid !== '') {
                $this->db->update('user_asana_accounts', [
                    'asana_user_gid'   => $assigneeGid,
                    'asana_user_email' => $me['email'] ?? null,
                    'asana_user_name'  => $me['name'] ?? null,
                ], 'id = ?', [$accountId]);
            }
        }
        if ($assigneeGid === '') {
            throw new \RuntimeException('Konnte asana_user_gid nicht ermitteln');
        }

        // Customer-Lookup: asana_projekt_gid → customer_id und abbreviation → customer_id
        $custMap = [];
        $abbrMap = [];
        foreach ($this->db->query("SELECT id, abbreviation, asana_projekt_gid FROM customers WHERE is_active = 1") ?: [] as $c) {
            if (!empty($c['asana_projekt_gid'])) {
                $custMap[$c['asana_projekt_gid']] = (int)$c['id'];
            }
            if (!empty($c['abbreviation'])) {
                $abbrMap[mb_strtoupper(trim($c['abbreviation']))] = (int)$c['id'];
            }
        }

        $workspaces = $me['workspaces'] ?? [];
        $stats = [
            'workspaces_checked' => 0,
            'tasks_seen'         => 0,
            'tasks_created'      => 0,
            'tasks_updated'      => 0,
            'tasks_completed_in_asana' => 0,
        ];
        $seenGids = [];

        foreach ($workspaces as $ws) {
            $wsGid = $ws['gid'] ?? null;
            if (!$wsGid) continue;
            $stats['workspaces_checked']++;
            try {
                $tasks = $asana->getAssignedTasks($wsGid, $assigneeGid);
            } catch (\Throwable $e) {
                error_log("PlannerSync: workspace {$wsGid} fehlgeschlagen: " . $e->getMessage());
                continue;
            }
            foreach ($tasks as $t) {
                $gid = (string)($t['gid'] ?? '');
                if ($gid === '') continue;
                $seenGids[$gid] = true;
                $stats['tasks_seen']++;

                // Kunde resolven — drei Strategien hintereinander:
                //   1. Asana-Projekt-Mapping (customers.asana_projekt_gid)
                //   2. Titel-Präfix-Match auf customers.abbreviation (z.B. "WIT Neue Kampagne")
                //   3. Fallback: category_hint = 'private' (PRIV-Prefix) oder 'unclear'
                $projGid = null;
                $projName = null;
                $customerId = null;
                foreach (($t['projects'] ?? []) as $p) {
                    if (!empty($p['gid'])) {
                        $projGid = $p['gid'];
                        $projName = $p['name'] ?? null;
                        if (isset($custMap[$projGid])) {
                            $customerId = $custMap[$projGid];
                        }
                        break;
                    }
                }
                $categoryHint = null;
                if (!$customerId) {
                    [$customerId, $categoryHint] = self::resolveFromTitle($t['name'] ?? '', $abbrMap);
                }
                // 3. Letzter Fallback: Account-Default-Kunde (z.B. Hills & Valleys-Account → alle
                //    Tasks ohne klare Zuordnung gehen automatisch an den H&V-Kunden).
                if (!$customerId && !empty($account['default_customer_id'])) {
                    $customerId = (int)$account['default_customer_id'];
                    $categoryHint = null;
                }

                $completedAtAsana = null;
                if (!empty($t['completed'])) {
                    $completedAtAsana = self::toMysqlDatetime($t['completed_at'] ?? null) ?: date('Y-m-d H:i:s');
                    $stats['tasks_completed_in_asana']++;
                }

                $data = [
                    'user_id' => $userId,
                    'asana_account_id' => $accountId,
                    'asana_task_gid' => $gid,
                    'name' => mb_substr((string)($t['name'] ?? ''), 0, 500),
                    'notes' => $t['notes'] ?? null,
                    'due_on' => $t['due_on'] ?? null,
                    'due_at' => self::toMysqlDatetime($t['due_at'] ?? null),
                    'asana_project_gid' => $projGid,
                    'asana_project_name' => $projName ? mb_substr($projName, 0, 255) : null,
                    'customer_id' => $customerId,
                    'category_hint' => $customerId ? null : $categoryHint,
                    'completed_at_asana' => $completedAtAsana,
                    'asana_modified_at' => self::toMysqlDatetime($t['modified_at'] ?? null),
                    'asana_permalink_url' => $t['permalink_url'] ?? null,
                ];

                // Default-Bucket aus der Frist (nur für NEUE Tasks gesetzt; bestehende behalten ihren
                // Wert — slot_pinned + recomputeBuckets pflegen den Rest, siehe Update-Logik unten).
                $data['daily_slot'] = self::fristBucket($t['due_on'] ?? null);

                $existing = $this->db->queryOne(
                    "SELECT id, customer_id, category_hint, asana_modified_at, last_activity, is_waiting,
                            due_on, due_locally_set, completed_at_asana, name, notes
                     FROM planner_tasks WHERE user_id = ? AND asana_account_id = ? AND asana_task_gid = ?",
                    [$userId, $accountId, $gid]
                );
                if ($existing) {
                    // Manuelle/vorhandene Zuordnung respektieren.
                    if ($existing['customer_id'] !== null || $existing['category_hint'] === 'private') {
                        unset($data['customer_id']);
                        unset($data['category_hint']);
                    }
                    // daily_slot NIE per Auto überschreiben (wird aus due_on materialisiert).
                    unset($data['daily_slot']);

                    // Asana-Originalfrist immer mitschreiben (fuer den Abweichungs-Hinweis Tool vs. Asana).
                    $asanaDue = $data['due_on'] ?? null;
                    $data['asana_due_on'] = $asanaDue;
                    if (!empty($existing['due_locally_set'])) {
                        if ((string)$asanaDue === (string)($existing['due_on'] ?? '')) {
                            // Asana wurde an die lokale Planung angeglichen → wieder synchron, Hinweis verschwindet.
                            $data['due_locally_set'] = 0;
                        } else {
                            // Weiterhin Abweichung → lokal angepasste Frist behalten, Asana-Wert nicht uebernehmen.
                            unset($data['due_on']);
                        }
                    }

                    // WICHTIG: hat sich die Task in Asana seit unserem letzten Sync geändert?
                    // Falls ja → ai_summary auf NULL, damit die nächste KI-Analyse sie neu durchgeht
                    // (mit den dann frischen Asana-Kommentaren). Plus last_activity nullen, damit
                    // die KI-Analyse die Stories erneut zieht.
                    $oldMod = $existing['asana_modified_at'] ?? null;
                    $newMod = $data['asana_modified_at'] ?? null;
                    if ($oldMod && $newMod && strtotime($newMod) > strtotime($oldMod)) {
                        // Prüfen, was sich konkret geändert hat — eine reine Terminverschiebung
                        // soll keine Re-Analyse + Auto-Wake triggern (Bsp. Zahnarzt-Termin wird im
                        // Asana auf den 15.7. verschoben, User hat manuell auf 'Warten' gestellt:
                        // bleibt 'Warten').
                        $onlyScheduleChanged = (
                            ($existing['due_on'] ?? null) !== ($data['due_on'] ?? null)
                            && (string)($existing['name'] ?? '') === (string)($data['name'] ?? '')
                            && (string)($existing['notes'] ?? '') === (string)($data['notes'] ?? '')
                            && ($existing['completed_at_asana'] ?? null) === $completedAtAsana
                        );
                        if ($onlyScheduleChanged) {
                            $stats['tasks_schedule_only_changed'] = ($stats['tasks_schedule_only_changed'] ?? 0) + 1;
                            // KEIN Re-Analyse-Reset, KEIN Wake — nur due_on geändert
                        } else {
                            $data['ai_summary'] = null;
                            $data['last_activity'] = null;
                            $stats['tasks_changed_in_asana'] = ($stats['tasks_changed_in_asana'] ?? 0) + 1;

                            // Auto-Wake aus 'Warten': nur wenn etwas Inhaltliches passiert ist
                            // (neue Kommentare → last_activity wird neu gezogen und differieren,
                            //  oder Name/Notes/Completion-Änderung).
                            if (!empty($existing['is_waiting'])) {
                                $data['is_waiting'] = 0;
                                $data['waiting_signal'] = 1;
                                $stats['tasks_woken_from_waiting'] = ($stats['tasks_woken_from_waiting'] ?? 0) + 1;
                            }
                        }
                    }

                    $this->db->update('planner_tasks', $data, 'id = ?', [(int)$existing['id']]);
                    $stats['tasks_updated']++;
                } else {
                    $this->db->insert('planner_tasks', $data);
                    $stats['tasks_created']++;
                }
            }
        }

        // ORPHAN-HANDLING (Asana-Assignee wurde gewechselt):
        // Tasks, die im /tasks?assignee=Du-Aufruf nicht mehr zurückkommen, sind in Asana
        // an jemand anders zugewiesen worden. Das ist NICHT dasselbe wie 'erledigt' — der
        // User koordiniert / wartet ja möglicherweise weiter ("Ball bei Michi"). Wir setzen
        // deshalb nur ein Orphan-Flag, NICHT completed_at_asana. Die Task bleibt in Phase 5
        // "Beobachten" sichtbar.
        //
        // Damit Felder wie due_on, modified_at, completed weiterhin aktuell bleiben, holen
        // wir Orphan-Tasks anschließend einzeln via /tasks/{gid} ab.
        $rows = $this->db->query(
            "SELECT id, asana_task_gid, asana_modified_at, last_activity, is_waiting, is_orphaned_from_asana
             FROM planner_tasks
             WHERE user_id = ? AND asana_account_id = ? AND completed_at_asana IS NULL AND completed_at_local IS NULL",
            [$userId, $accountId]
        ) ?: [];
        $newOrphans = 0;
        $orphanRows = [];
        foreach ($rows as $r) {
            if (isset($seenGids[$r['asana_task_gid']])) {
                // Falls Task wieder zugewiesen wurde, Flag zurücksetzen
                if (!empty($r['is_orphaned_from_asana'])) {
                    $this->db->update('planner_tasks', ['is_orphaned_from_asana' => 0], 'id = ?', [(int)$r['id']]);
                }
                continue;
            }
            // Nicht mehr in der Assignee-Liste — Orphan
            if (empty($r['is_orphaned_from_asana'])) $newOrphans++;
            $orphanRows[] = $r;
        }
        $stats['tasks_orphaned_new'] = $newOrphans;
        $stats['tasks_orphaned_total'] = count($orphanRows);

        // Orphan-Tasks individuell aus Asana frisch holen. Damit bleiben due_on, completed-Status,
        // assignee, neue Kommentare etc. aktuell, obwohl wir nicht mehr Assignee sind.
        // Rate-Limit: Asana 150 req/min Free — bei <100 Orphans pro User unkritisch.
        $refreshed = 0;
        $orphanCompleted = 0;
        foreach ($orphanRows as $r) {
            try {
                $t = $asana->getTask((string)$r['asana_task_gid']);
            } catch (\Throwable $e) {
                error_log("PlannerSync: orphan fetch {$r['asana_task_gid']} fehlgeschlagen: " . $e->getMessage());
                continue;
            }
            if (!$t) continue;

            $completedAtAsana = null;
            if (!empty($t['completed'])) {
                $completedAtAsana = self::toMysqlDatetime($t['completed_at'] ?? null) ?: date('Y-m-d H:i:s');
                $orphanCompleted++;
            }
            $newMod = self::toMysqlDatetime($t['modified_at'] ?? null);
            $upd = [
                'is_orphaned_from_asana' => 1,
                'name'                   => mb_substr((string)($t['name'] ?? ''), 0, 500),
                'notes'                  => $t['notes'] ?? null,
                'due_on'                 => $t['due_on'] ?? null,
                'due_at'                 => self::toMysqlDatetime($t['due_at'] ?? null),
                'asana_modified_at'      => $newMod,
                'asana_permalink_url'    => $t['permalink_url'] ?? null,
                'completed_at_asana'     => $completedAtAsana,
            ];
            // Re-Analyse-Trigger bei Asana-Änderung — wie im Haupt-Loop, aber kein 'Ball zurück'-
            // Signal bei Orphans. Begründung: die Aktivität kommt vom neuen Assignee (z.B. Michaela
            // schreibt einen Kommentar), nicht von Dir. Ball ist immer noch beim anderen.
            // Wartend bleibt wartend. Erst wenn Assignee zurück zu Dir wechselt (was den Haupt-
            // Loop trifft, nicht diesen Orphan-Branch), wird ein Signal getriggert.
            $oldMod = $r['asana_modified_at'] ?? null;
            if ($oldMod && $newMod && strtotime($newMod) > strtotime($oldMod)) {
                $upd['ai_summary'] = null;
                $upd['last_activity'] = null;
            }
            $this->db->update('planner_tasks', $upd, 'id = ?', [(int)$r['id']]);
            $refreshed++;
        }
        $stats['tasks_orphaned_refreshed'] = $refreshed;
        $stats['tasks_orphaned_completed'] = $orphanCompleted;
        $stats['synced_at'] = date('Y-m-d H:i:s');

        return $stats;
    }

    /**
     * PAT verschlüsselt in users.asana_user_pat ablegen + Verbindung testen.
     * Schreibt nebenbei asana_user_gid/email/name aus /users/me in den User-Record.
     */
    public function setUserPat(int $userId, string $patPlain): array
    {
        $pat = trim($patPlain);
        if ($pat === '') {
            // Löschen: PAT-Feld leer + Asana-Identität leeren? Identität behalten, damit Historie nachvollziehbar bleibt.
            $this->db->update('users', ['asana_user_pat' => null], 'id = ?', [$userId]);
            return ['removed' => true];
        }
        $asana = new AsanaService($pat);
        $me = $asana->getMe();
        if (!$me) {
            throw new \RuntimeException('PAT abgelehnt: Konnte Asana-Identität nicht laden.');
        }
        $enc = Crypto::encrypt($pat);
        $this->db->update('users', [
            'asana_user_pat'   => $enc,
            'asana_user_gid'   => $me['gid'] ?? null,
            'asana_user_email' => $me['email'] ?? null,
            'asana_user_name'  => $me['name'] ?? null,
        ], 'id = ?', [$userId]);
        return [
            'gid'        => $me['gid'] ?? null,
            'email'      => $me['email'] ?? null,
            'name'       => $me['name'] ?? null,
            'workspaces' => array_map(fn($w) => $w['name'] ?? '', $me['workspaces'] ?? []),
        ];
    }

    public function hasUserPat(int $userId): bool
    {
        $row = $this->db->queryOne("SELECT asana_user_pat FROM users WHERE id = ?", [$userId]);
        return !empty($row['asana_user_pat']);
    }

    /**
     * Re-resolved customer_id + category_hint für ALLE Tasks des Users
     * (ohne Asana-Call) — nützlich nach Änderung der abbreviation oder asana_projekt_gid.
     * Liefert Stats { updated, by_customer, by_category }.
     */
    public function resolveCustomersForUser(int $userId): array
    {
        $custMap = [];
        $abbrMap = [];
        foreach ($this->db->query("SELECT id, abbreviation, asana_projekt_gid FROM customers WHERE is_active = 1") ?: [] as $c) {
            if (!empty($c['asana_projekt_gid'])) $custMap[$c['asana_projekt_gid']] = (int)$c['id'];
            if (!empty($c['abbreviation']))     $abbrMap[mb_strtoupper(trim($c['abbreviation']))] = (int)$c['id'];
        }
        // Nur offene Tasks re-resolven — abgeschlossene/ignorierte sind history und sollen die
        // KPIs nicht aufblähen.
        $openWhere = "user_id = ? AND completed_at_asana IS NULL AND completed_at_local IS NULL AND planner_ignored = 0";
        $tasks = $this->db->query(
            "SELECT id, name, asana_project_gid, customer_id, category_hint
             FROM planner_tasks WHERE $openWhere",
            [$userId]
        ) ?: [];
        $stats = ['checked' => 0, 'updated' => 0, 'with_customer' => 0, 'private' => 0, 'unclear' => 0];
        foreach ($tasks as $t) {
            $stats['checked']++;
            $newCustomerId = null;
            $newHint = null;
            if (!empty($t['asana_project_gid']) && isset($custMap[$t['asana_project_gid']])) {
                $newCustomerId = $custMap[$t['asana_project_gid']];
            }
            if (!$newCustomerId) {
                [$newCustomerId, $newHint] = self::resolveFromTitle($t['name'] ?? '', $abbrMap);
            }
            $effHint = $newCustomerId ? null : $newHint;
            $curCustomerId = $t['customer_id'] ? (int)$t['customer_id'] : null;
            if ($newCustomerId === $curCustomerId && $effHint === $t['category_hint']) continue;
            $this->db->update('planner_tasks', [
                'customer_id'   => $newCustomerId,
                'category_hint' => $effHint,
            ], 'id = ?', [(int)$t['id']]);
            $stats['updated']++;
        }
        // Verteilung nach Update — wieder nur offene Tasks
        $rows = $this->db->query(
            "SELECT customer_id, category_hint, COUNT(*) AS c FROM planner_tasks WHERE $openWhere GROUP BY customer_id, category_hint",
            [$userId]
        ) ?: [];
        foreach ($rows as $r) {
            if ($r['customer_id']) $stats['with_customer'] += (int)$r['c'];
            elseif ($r['category_hint'] === 'private') $stats['private'] += (int)$r['c'];
            else $stats['unclear'] += (int)$r['c'];
        }
        return $stats;
    }

    /**
     * Versucht aus dem Task-Titel einen Kunden zu erkennen.
     * Strategie:
     *   - Erstes "Wort" (bis Leerzeichen, Slash, Doppelpunkt, Bindestrich) in UPPERCASE
     *   - Wenn es ein bekanntes Kürzel ist (customers.abbreviation) → customer_id
     *   - "PRIV" am Anfang → category_hint = 'private' (kein Kunde, eigene Kategorie)
     *   - Sonst → category_hint = 'unclear'
     *
     * Liefert [customer_id|null, category_hint|null].
     */
    public static function resolveFromTitle(string $title, array $abbrMap): array
    {
        $t = trim($title);
        if ($t === '') return [null, 'unclear'];
        // Erstes Token, max 10 Zeichen (Abbreviations sind kurz)
        if (!preg_match('/^([A-ZÄÖÜa-zäöü0-9]{1,10})/u', $t, $m)) return [null, 'unclear'];
        $first = mb_strtoupper($m[1]);
        if (isset($abbrMap[$first])) {
            return [$abbrMap[$first], null];
        }
        if ($first === 'PRIV' || $first === 'PRIVAT' || $first === 'PRIVATE') {
            return [null, 'private'];
        }
        return [null, 'unclear'];
    }

    /**
     * Einheitliches 8-Stufen-Zeitraum-Modell: leitet den Default-Bucket aus der Asana-Frist ab.
     *   ueberfaellig/heute → today · +1 → tomorrow · +2 → day_after · +3..7 → rest_week
     *   +8..14 → next_week · bis Monatsende → this_month · danach → later · keine Frist → occasion
     */
    public static function fristBucket(?string $dueOn): string
    {
        if (!$dueOn) return 'occasion';
        try {
            $due = new \DateTime($dueOn);
            $due->setTime(0, 0, 0);
        } catch (\Throwable $e) { return 'occasion'; }
        $today = new \DateTime('today');
        $diff = (int)$today->diff($due)->format('%r%a'); // negativ = ueberfaellig
        if ($diff <= 0)  return 'today';
        if ($diff === 1) return 'tomorrow';
        if ($diff === 2) return 'day_after';
        if ($diff <= 7)  return 'rest_week';
        if ($diff <= 14) return 'next_week';
        $endOfMonth = new \DateTime(date('Y-m-t'));
        $endOfMonth->setTime(0, 0, 0);
        if ($due <= $endOfMonth) return 'this_month';
        return 'later';
    }

    /**
     * Repraesentatives Frist-Datum fuer einen Bucket — fuers Re-Planen (Ziehen/Bulk setzt die Faelligkeit).
     * occasion → null (keine Frist).
     */
    public static function bucketToDate(string $bucket): ?string
    {
        switch ($bucket) {
            case 'today':      return date('Y-m-d');
            case 'tomorrow':   return date('Y-m-d', strtotime('+1 day'));
            case 'day_after':  return date('Y-m-d', strtotime('+2 day'));
            case 'rest_week':  return date('Y-m-d', strtotime('+5 day'));
            case 'next_week':  return date('Y-m-d', strtotime('+10 day'));
            case 'this_month': // ein Tag innerhalb des aktuellen Monats, aber > 14 Tage entfernt
                $eom = date('Y-m-t');
                $plus20 = date('Y-m-d', strtotime('+20 day'));
                return ($plus20 <= $eom) ? $plus20 : $eom;
            case 'later':      return date('Y-m-d', strtotime('+45 day'));
            case 'occasion':   return null;
            default:           return null;
        }
    }

    /**
     * Materialisiert den effektiven Zeitraum-Bucket: setzt daily_slot = fristBucket(due_on) fuer alle
     * NICHT gepinnten Tasks (slot_pinned=0). So wandert der Bucket mit, wenn sich Asana-Fristen aendern,
     * waehrend manuell verschobene Tasks (slot_pinned=1) ihren Wert behalten.
     * Wird am Ende jedes Syncs sowie beim Re-Resolve aufgerufen. Liefert die Bucket-Verteilung.
     */
    public function recomputeBuckets(int $userId): array
    {
        $rows = $this->db->query(
            "SELECT id, due_on, daily_slot FROM planner_tasks
             WHERE user_id = ?
               AND completed_at_asana IS NULL AND completed_at_local IS NULL
               AND planner_ignored = 0",
            [$userId]
        ) ?: [];
        $dist = [];
        foreach ($rows as $r) {
            $bucket = self::fristBucket($r['due_on']);
            if ($bucket !== $r['daily_slot']) {
                $this->db->update('planner_tasks', ['daily_slot' => $bucket], 'id = ?', [(int)$r['id']]);
            }
            $dist[$bucket] = ($dist[$bucket] ?? 0) + 1;
        }
        return $dist;
    }

    /**
     * Asana liefert Zeitstempel als ISO-8601 mit Millisekunden und Z-Suffix
     * (z.B. "2023-03-13T17:15:45.153Z"). MariaDB-TIMESTAMP/DATETIME wollen "Y-m-d H:i:s".
     * Konvertiert in UTC-Lokalzeit-String.
     */
    private static function toMysqlDatetime(?string $iso): ?string
    {
        if (!$iso) return null;
        try {
            $dt = new \DateTime($iso);
            $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
