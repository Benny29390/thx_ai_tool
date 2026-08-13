<?php
namespace Services;

use Core\Crypto;
use Core\Database;
use Core\Settings;

/**
 * KI-Pre-Analyse für Planner-Tasks.
 *
 * Liefert pro Task in EINEM LLM-Call:
 *   - effort_minutes (15..480, Bucket-quantisiert)
 *   - confidence (low/medium/high) für den Aufwand
 *   - reasoning (kurz, max 80 Zeichen)
 *   - summary (1-2 Sätze: worum geht's konkret?)
 *   - significance (1-10: wie wichtig wirkt das semantisch, unabhängig von Deadline?)
 *   - recommended_when ('asap' | 'this_week' | 'when_possible')
 *   - complexity ('low' | 'medium' | 'high' — brauche frischen Kopf?)
 *
 * Batch-Strategie: alle Tasks ohne ai_summary (=ohne Pre-Analyse) in einem Call.
 */
class PlannerEffortAiService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function estimateMissingForUser(int $userId, int $maxBatch = 30): array
    {
        // "Missing" = noch keine ai_summary (das ist die neue, vollwertige Analyse).
        // Alte Tasks mit nur ai_effort_estimate werden nachgezogen.
        $tasks = $this->db->query(
            "SELECT id, asana_task_gid, name, notes, asana_project_name, due_on, postpone_count, last_activity,
                    daily_slot, manual_priority, ai_effort_estimate, ai_recommended_when
             FROM planner_tasks
             WHERE user_id = ? AND completed_at_local IS NULL AND completed_at_asana IS NULL
               AND planner_ignored = 0
               AND (ai_summary IS NULL OR ai_summary = '')
             ORDER BY created_at DESC
             LIMIT ?",
            [$userId, $maxBatch]
        ) ?: [];
        if (empty($tasks)) {
            return ['estimated' => 0, 'message' => 'Alle Tasks bereits analysiert'];
        }

        $apiKey = Settings::get('anthropic_api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic-API-Key nicht konfiguriert');
        }

        // Pro Task die letzten Asana-Kommentare ziehen — das ist oft der ausschlaggebende Kontext
        // ("kann ich kurze Rückmeldung haben?" → 2-Min-Quick-Task statt 1h-Konzeption).
        $userRow = $this->db->queryOne("SELECT asana_user_pat FROM users WHERE id = ?", [$userId]);
        $patEnc = $userRow['asana_user_pat'] ?? '';
        if ($patEnc !== '') {
            try {
                $pat = Crypto::decrypt($patEnc);
                require_once SERVICES_PATH . '/AsanaService.php';
                $asana = new AsanaService($pat);
                foreach ($tasks as &$t) {
                    // Nur ziehen, wenn noch nichts gespeichert ist — spart API-Calls bei wiederholten Laufen
                    if (!empty($t['last_activity'])) continue;
                    try {
                        $stories = $asana->getTaskStories($t['asana_task_gid'], true);
                        // Letzte 2 Kommentare (chronologisch) — Asana sortiert nach created_at ASC
                        $recent = array_slice($stories, -2);
                        $lines = [];
                        foreach ($recent as $s) {
                            $who = $s['created_by']['name'] ?? '?';
                            $when = isset($s['created_at']) ? substr($s['created_at'], 0, 10) : '';
                            $text = trim((string)($s['text'] ?? ''));
                            if ($text === '') continue;
                            $lines[] = "[{$when} {$who}] " . mb_substr($text, 0, 240);
                        }
                        $activity = implode("\n", $lines);
                        $t['last_activity'] = $activity;
                        $this->db->update('planner_tasks', ['last_activity' => $activity], 'id = ?', [(int)$t['id']]);
                    } catch (\Throwable $e) {
                        // Stille ignorieren — KI bekommt halt nur Title + Notes
                    }
                }
                unset($t);
            } catch (\Throwable $e) {
                // Crypto/PAT-Probleme leise — Stories sind optional
            }
        }

        $systemPrompt = <<<SYS
Du analysierst Aufgaben aus einem Marketing-/Agentur-Kontext (Inhaber: Texter/Berater).

Liefere pro Task ein JSON-Objekt mit:
- gid: das übergebene asana_task_gid
- minutes: RESTAUFWAND in Minuten, gewählt aus {5, 10, 15, 30, 45, 60, 90, 120, 180, 240, 360, 480}.
  Sei VORSICHTIG mit niedrigen Schätzungen. Im Zweifel eher zu hoch als zu niedrig.
  WICHTIG: Wenn last_activity zeigt, dass der User bereits einen TEIL der Task erledigt hat
  ("erste Hälfte geschafft", "20 von 30 Mails raus", "Rohfassung fertig, fehlt nur noch X"),
  dann gib NUR den verbleibenden Aufwand zurück, NICHT den Gesamt.
  Bei einer noch unangetasteten Task ist minutes = Gesamtaufwand.

  TYPISCHE FALLEN (NICHT als 5/10-Min-Quick missinterpretieren):
  * Linkbuilding-Tasks "KUNDE Domain.de / X.XXX Euro (Monat Jahr)" — z.B.
    "BKK gründer.de / 1.550 Euro (Juli 2026)". Format ist KUNDE Domain / Budget (Monat).
    Das ist KEIN Quick-Task. Erfordert: Thema planen, mit Partner abstimmen, Text
    schreiben (~500 Worte), Freigabe einholen, veröffentlichen — typisch 90-240 Min.
    Das Datum im Titel ist der ZIELMONAT, nicht das Erledigt-Datum!
  * "Konkrete To Do's" als Asana-Projekt → meist Content/Linkbuilding → mehrere Stunden
  * Gastartikel/Texte schreiben → mindestens 60-180 Min
  * Konzept/Recherche → mindestens 60 Min
  * "Prüfen + ggf. anpassen" → 30-60 Min, kein Quick-Task

- progress_pct: 0..100 — Wieviel hat der User schon erledigt? STRIKT aus last_activity ableiten,
  NIEMALS aus Titel oder Datum.
  Setze > 0 NUR wenn last_activity explizit Fortschritt erwähnt:
    "erste Hälfte geschafft" → 50
    "fast fertig" / "kurz vor Abschluss" → 80
    "Rohfassung fertig, fehlt noch X" → 60
    "ein paar Punkte abgehakt" → 20
    "Entwurf steht" → 40
  WICHTIG: Datumsangaben im TITEL (z.B. "(Juli 2026)", "(Mai 2026)") sind NIE Fortschrittsanzeiger,
  sondern Zielmonate. Bei brandneuen Tasks ohne Kommentare → IMMER progress_pct = 0.
  Im Zweifel → 0.
- user_scheduled_slot: WICHTIG. Wenn der User SELBST in last_activity geschrieben hat,
  WANN er die Task (oder den Rest) machen will, gib das hier zurück.
  Mappings (8-Stufen-Zeitraum):
    "heute / heute abend / heute noch" → "today"
    "morgen / morgen früh als Erstes" → "tomorrow"
    "übermorgen" → "day_after"
    "diese Woche / Donnerstag / Freitag / Wochenende" → "rest_week"
    "nächste Woche / Mo bis Fr nächste Woche" → "next_week"
    "später im Monat / Ende des Monats" → "this_month"
    "nächsten Monat / in einigen Wochen / langfristig" → "later"
    "irgendwann / wenn Zeit ist / bei Gelegenheit" → "occasion"
    Kein zeitlicher Selbst-Commitment vom User → null
  Setze NUR wenn der USER (nicht der Kunde, nicht ein Kollege) ein klares Timing genannt hat.
- is_quick_task: STRIKT true NUR wenn die Task in 2-10 Minuten erledigbar ist.
  ECHTE Quick-Tasks:
    * Kurze Rückmeldung/OK auf Asana-Frage
    * Eine einzelne Mail mit konkretem Inhalt
    * Approve klicken
    * Datei rauslegen / hochladen
    * Kurze Prüfung (Status-Check)
  KEIN Quick-Task (auch wenn das Titel kurz aussieht):
    * Linkbuilding-Tasks (Format "KUNDE Domain.de / X Euro (Monat)") — NIE
    * Tasks mit Eurobetrag im Titel (Gastartikel, Linkplatzierung) — NIE
    * Texte schreiben, Konzepte entwickeln — NIE
    * Recherche, Vergleich, Auswertung — NIE
    * "Prüfen ob...", wenn substantielle Prüfung nötig
  Stütz Dich auf last_activity — wenn dort steht "kannst Du dazu kurz Rückmeldung geben?"
  oder "ich brauche nur Dein OK", ist das ein Quick-Task.
- confidence: "low" | "medium" | "high"
- reasoning: max 80 Zeichen, warum dieser Aufwand
- summary: 1-2 kurze Sätze, was die Task konkret bedeutet — kein Bullshit, kein Marketing-Speak. Bei Quick-Tasks knapp: "Mail an X mit Y bestätigen". Bei begonnenen Tasks: "Halb fertig, fehlt noch X" o. ae.
- significance: 1..10
- recommended_when: "asap" | "this_week" | "when_possible"
- complexity: "low" | "medium" | "high"
- is_recurring: true wenn diese Task wiederkehrend ist (z.B. monatliches Reminder, wöchentliche Pflege,
  zweiwöchiger Check). Erkennung im Titel oder in den Notes:
    "(monatlich)" / "(mtl.)" / "(monthly)" / "(jeden Monat)"
    "(wöchentlich)" / "(wt.)" / "(jede Woche)"
    "(alle 2 Wochen)" / "(2 Wochen)" / "(zweiwöchig)" / "(2wt)"
    "(quartalsweise)" / "(Q1)"
    "(jährlich)" / "(jhrl.)" / "(yearly)"
    "regelmäßig" / "in regelmäßigen Abständen"
  Default false.
- recurring_pattern: wenn is_recurring=true, gib den erkannten Pattern als Kurztext zurück
  ("monatlich" | "wöchentlich" | "2-wöchentlich" | "quartalsweise" | "jährlich" | freier Text).
- recurring_interval_days: wenn is_recurring=true UND erkennbar, das Intervall in Tagen
  (wöchentlich=7, 2-wöchentlich=14, monatlich=30, quartalsweise=90, jährlich=365). Sonst null.
- activity_type: EINES aus diesem festen Set — beschreibt WAS für eine Aktivität das ist (damit gleichartige Tasks gebündelt werden können):
  * "meeting"       — Termin/Teamgespräch/Workshop/Call planen oder führen
  * "approval"      — Freigabe geben, Korrektur abnehmen, OK-Klick
  * "communication" — Mail/Telefonat/Nachricht an eine konkrete Person verfassen
  * "writing"       — Text/Konzept/Vorlage SCHREIBEN (mehrere Absätze, kreativ)
  * "review"        — Etwas prüfen/durchsehen/Feedback geben (ohne Schreiben)
  * "research"      — Recherche, Quellen lesen, vergleichen
  * "admin"         — Verwaltung, Buchhaltung, Abrechnung, Tool-Setup, Listen pflegen
  * "planning"      — Strategie/Roadmap/Plan/Zeitplan/Sequenz erarbeiten
  * "execution"     — Hands-on Umsetzung (Code, Setup, Konfiguration, Implementierung)
  * "creative"      — Design/Visual/Layout/Branding-Entscheidung
  * "other"         — passt in keine Kategorie

WICHTIG:
- last_activity ist die LETZTE Asana-Aktivität (Kommentare). Wenn dort jemand explizit auf eine Rückmeldung wartet ("brauche nur dein OK", "kurze Rückfrage", "Bescheid geben"), ist das fast immer ein Quick-Task und auch dringend — recommended_when='asap', is_quick_task=true, minutes=5 oder 10.
- last_activity LEER (kein Kommentar) → es ist eine NEUE Task, die noch nicht angefangen wurde.
  → progress_pct = 0, minutes = Gesamtaufwand (KEINE Restschätzung).
  → is_quick_task NUR true, wenn die Task wirklich in 2-10 Min vollständig erledigt wäre.
- postpone_count > 2 → skeptischer bewerten.
- Vage Aufgaben ("Mal überlegen ob X") sind kaum "asap".
- Überlange Notes ohne klare Aktion → eher Sammelbecken.
- Im Zweifel: zu HOHEM Aufwand und zu NIEDRIGEM Fortschritt tendieren. Es ist besser, eine
  Task zu hoch zu schätzen (User korrigiert manuell), als sie als 5-Min-Quick zu unterschätzen.

Antworte AUSSCHLIESSLICH als gültiges JSON-Array:
[{"gid":"...","minutes":10,"progress_pct":50,"user_scheduled_slot":"rest_week","is_quick_task":true,"is_recurring":true,"recurring_pattern":"monatlich","recurring_interval_days":30,"confidence":"high","reasoning":"...","summary":"...","significance":6,"recommended_when":"asap","complexity":"low","activity_type":"communication"}]
SYS;

        // Lernschleife: vom Inhaber freigeschaltete Regeln in den Prompt injizieren, damit die KI
        // aus seinen frueheren Korrekturen lernt (sie schlagen die allgemeinen Faustregeln oben).
        try {
            require_once SERVICES_PATH . '/PlannerLearningService.php';
            $rulesBlock = (new PlannerLearningService($this->db))->activeRulesBlock($userId);
            if ($rulesBlock !== '') {
                $systemPrompt .= "\n\n" . $rulesBlock;
            }
        } catch (\Throwable $e) {
            // Lernschleife ist optional — bei Problemen laeuft die Analyse ohne gelernte Regeln weiter.
        }

        $tasksPayload = array_map(fn($t) => [
            'gid'             => $t['asana_task_gid'],
            'name'            => $t['name'],
            'project'         => $t['asana_project_name'] ?? '',
            'notes'           => mb_substr(trim((string)($t['notes'] ?? '')), 0, 500),
            'last_activity'   => mb_substr(trim((string)($t['last_activity'] ?? '')), 0, 500),
            'due_on'          => $t['due_on'],
            'postpone_count'  => (int)($t['postpone_count'] ?? 0),
        ], $tasks);

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(6000);
        $ai->setTimeout(90);

        $rawText = '';
        $parsed = null;
        $apiError = null;
        try {
            $resp = $ai->chat(
                [['role' => 'user', 'content' => "Tasks:\n" . json_encode($tasksPayload, JSON_UNESCAPED_UNICODE)]],
                $systemPrompt
            );
            $rawText = trim($resp['content'] ?? '');
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $rawText);
            $parsed = json_decode($clean, true);
        } catch (\Throwable $e) {
            $apiError = $e->getMessage();
        }
        if (!is_array($parsed)) $parsed = [];

        $byGid = [];
        foreach ($tasks as $t) $byGid[$t['asana_task_gid']] = (int)$t['id'];

        // Welche Tasks hat die KI tatsächlich beantwortet?
        $answeredGids = [];
        $updated = 0;
        foreach ($parsed as $e) {
            $gid = (string)($e['gid'] ?? '');
            $tid = $byGid[$gid] ?? null;
            if (!$tid) continue;
            $answeredGids[$gid] = true;

            $minutes = (int)($e['minutes'] ?? 0);
            if ($minutes <= 0 || $minutes > 1440) $minutes = 60;
            $conf = in_array($e['confidence'] ?? 'medium', ['low','medium','high'], true) ? $e['confidence'] : 'medium';
            $reason = mb_substr((string)($e['reasoning'] ?? ''), 0, 500);
            $summary = mb_substr((string)($e['summary'] ?? ''), 0, 300);
            // KI liefert manchmal '' zurück — dann darf nicht ein leerer String in die DB landen,
            // sonst gilt die Task nach dem WHERE-Filter als noch nicht analysiert.
            if (trim($summary) === '') $summary = '(KI lieferte keine Zusammenfassung)';
            $sig = (int)($e['significance'] ?? 5);
            if ($sig < 1) $sig = 1;
            if ($sig > 10) $sig = 10;
            $when = in_array($e['recommended_when'] ?? '', ['asap','this_week','when_possible'], true) ? $e['recommended_when'] : 'when_possible';
            $complex = in_array($e['complexity'] ?? '', ['low','medium','high'], true) ? $e['complexity'] : 'medium';

            $isQuick = !empty($e['is_quick_task']) ? 1 : 0;
            // Sicherheits-Heuristik: minutes <= 10 → automatisch Quick-Task
            if ($minutes > 0 && $minutes <= 10) $isQuick = 1;

            $allowedActivities = ['meeting','approval','communication','writing','review','research','admin','planning','execution','creative','other'];
            $activity = in_array($e['activity_type'] ?? '', $allowedActivities, true) ? $e['activity_type'] : 'other';

            // Fortschritt aus Kommentaren (0..100) + User-Self-Scheduling
            $progress = (int)($e['progress_pct'] ?? 0);
            if ($progress < 0) $progress = 0;
            if ($progress > 100) $progress = 100;

            // SERVER-SIDE OVERRIDE: KI hat manchmal Halluzinationen ("Task heisst '(Juni 2026)',
            // muss also schon laufen → progress=100"). Wir trauen progress_pct > 0 NUR, wenn
            // in last_activity ein klares Fortschritts-Keyword steht.
            // Beispiel-Fallen, die KI sonst falsch interpretiert:
            //  - "BKK X / 1.000 Euro (Juli 2026)" → KI denkt 100%, in Wahrheit ist es brandneu
            $taskRow = null;
            foreach ($tasks as $row) { if ($row['asana_task_gid'] === $gid) { $taskRow = $row; break; } }
            $lastAct = mb_strtolower((string)($taskRow['last_activity'] ?? ''));
            if ($progress > 0) {
                $progressKeywords = ['fertig', 'erledigt', 'geschafft', 'hälfte', 'hälfte', 'rohfassung',
                    'entwurf', 'rohling', 'first draft', 'abgehakt', 'durch', 'in arbeit', 'angefangen',
                    'kurz vor', 'beinahe fertig', 'gleich fertig', 'läuft', 'läuft'];
                $hasKeyword = false;
                foreach ($progressKeywords as $kw) {
                    if (mb_strpos($lastAct, $kw) !== false) { $hasKeyword = true; break; }
                }
                if (!$hasKeyword) {
                    $progress = 0;  // KI darf nicht ohne Beleg behaupten, da sei Fortschritt
                }
            }
            $allowedSlots = ['today','tomorrow','day_after','rest_week','next_week','this_month','later','occasion'];
            $userSlot = in_array($e['user_scheduled_slot'] ?? '', $allowedSlots, true) ? $e['user_scheduled_slot'] : null;

            // Wiederkehrende Tasks (heuristisch aus Titel/Notes)
            $isRecurring = !empty($e['is_recurring']) ? 1 : 0;
            $recurringPattern = $isRecurring && !empty($e['recurring_pattern']) ? mb_substr((string)$e['recurring_pattern'], 0, 60) : null;
            $recurringInterval = $isRecurring && isset($e['recurring_interval_days']) ? (int)$e['recurring_interval_days'] : null;
            if ($recurringInterval !== null && ($recurringInterval < 1 || $recurringInterval > 730)) $recurringInterval = null;

            // Original-Task laden, um Veränderungen zu erkennen + Slot-Auto-Update zu entscheiden
            $orig = null;
            foreach ($tasks as $row) { if ((int)$row['id'] === $tid) { $orig = $row; break; } }
            $prevSlot = $orig['daily_slot'] ?? 'pool';
            $prevEffort = (int)($orig['ai_effort_estimate'] ?? 0);
            $hasManualPriority = !empty($orig['manual_priority']);

            $update = [
                'ai_effort_estimate'    => $minutes,
                'ai_effort_confidence'  => $conf,
                'ai_effort_reasoning'   => $reason,
                'ai_summary'            => $summary,
                'ai_significance'       => $sig,
                'ai_recommended_when'   => $when,
                'ai_complexity'         => $complex,
                'is_quick_task'         => $isQuick,
                'ai_is_quick'           => $isQuick,  // KI-Originalvorhersage festhalten (User-Korrektur erkennbar machen)
                'ai_activity_type'      => $activity,
                'ai_progress_pct'       => $progress,
                'ai_user_scheduled_slot' => $userSlot,
                'is_recurring'          => $isRecurring,
                'recurring_pattern'     => $recurringPattern,
                'recurring_interval_days' => $recurringInterval,
            ];

            // Auto-Slot-Adjustment: NUR wenn User in Kommentar selbst gesagt hat wann er das macht.
            // Sicherheitsbremsen:
            //  - manual_priority gesetzt → User hat in unserem Tool eine harte Prio, nicht überschreiben
            //  - userSlot identisch mit aktuellem Slot → nichts zu tun
            $slotChanged = false;
            if ($userSlot && !$hasManualPriority && $userSlot !== $prevSlot) {
                $update['daily_slot'] = $userSlot;
                $slotChanged = true;
            }

            // Signal-Bedingungen: relevant für den User ist
            //  - eine Slot-Verschiebung
            //  - signifikanter Aufwands-Wechsel (>=30% rauf oder runter, mind 15 Min Differenz)
            //  - oder klar erkannter Fortschritt (>=30%)
            $effortDiff = $prevEffort > 0 ? abs($minutes - $prevEffort) : 0;
            $effortChangedSignificantly = $prevEffort > 0
                && $effortDiff >= 15
                && ($effortDiff / max($prevEffort, 1)) >= 0.3;
            $progressSignal = $progress >= 30;

            if ($slotChanged || $effortChangedSignificantly || $progressSignal) {
                $parts = [];
                if ($slotChanged) $parts[] = 'Slot → ' . $userSlot;
                if ($effortChangedSignificantly) {
                    $delta = $minutes - $prevEffort;
                    $parts[] = ($delta < 0 ? 'Aufwand -' : 'Aufwand +') . abs($delta) . ' Min';
                }
                if ($progressSignal) $parts[] = 'Fortschritt ' . $progress . '%';
                $update['ai_re_analyzed_signal'] = 1;
                $update['ai_re_analyzed_summary'] = mb_substr(implode(' · ', $parts), 0, 300);
            }

            $this->db->update('planner_tasks', $update, 'id = ?', [$tid]);
            $updated++;
        }

        // Tasks, die die KI im Output übersprungen hat ODER die wegen einem Update-Fehler
        // weiterhin ohne ai_summary in der DB stehen — mit Fallback-Defaults bestücken.
        // Doppel-Check direkt aus der DB, damit hier nichts hängen bleiben kann.
        $stillUnanalyzed = [];
        if (!empty($tasks)) {
            $taskIds = array_map(fn($t) => (int)$t['id'], $tasks);
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $stillUnanalyzed = $this->db->query(
                "SELECT id, name, asana_task_gid FROM planner_tasks
                 WHERE id IN ($placeholders) AND (ai_summary IS NULL OR ai_summary = '')",
                $taskIds
            ) ?: [];
        }
        $failed = [];
        foreach ($stillUnanalyzed as $t) {
            $failed[] = ['id' => (int)$t['id'], 'name' => $t['name']];
            $reason = $apiError
                ? 'KI-Aufruf fehlgeschlagen: ' . mb_substr($apiError, 0, 200)
                : 'KI hat diese Task im Output übersprungen';
            $this->db->update('planner_tasks', [
                'ai_effort_estimate'    => 60,
                'ai_effort_confidence'  => 'low',
                'ai_effort_reasoning'   => $reason,
                'ai_summary'            => '(KI-Analyse fehlgeschlagen — bitte Aufwand manuell setzen)',
                'ai_significance'       => 5,
                'ai_recommended_when'   => 'when_possible',
                'ai_complexity'         => 'medium',
            ], 'id = ?', [(int)$t['id']]);
        }

        return [
            'estimated' => $updated,
            'failed'    => count($failed),
            'failed_names' => array_map(fn($f) => $f['name'], array_slice($failed, 0, 10)),
            'batch_size' => count($tasks),
            'api_error' => $apiError,
        ];
    }

    /**
     * Backfill: klassifiziert ai_activity_type für Tasks, die schon eine ai_summary haben
     * (also bereits analysiert wurden), aber noch keinen Aktivitätstyp tragen.
     *
     * Schlanker Prompt — nur Titel + Summary, kein Asana-Stories-Round-Trip, kein Re-Estimate.
     * Pro Lauf max $limit Tasks (Default 60), damit der Call in das Token-Budget passt.
     */
    public function classifyActivitiesForUser(int $userId, int $limit = 60): array
    {
        $tasks = $this->db->query(
            "SELECT id, name, ai_summary
             FROM planner_tasks
             WHERE user_id = ?
               AND completed_at_local IS NULL AND completed_at_asana IS NULL
               AND planner_ignored = 0
               AND ai_summary IS NOT NULL AND ai_summary <> ''
               AND ai_activity_type IS NULL
             ORDER BY id DESC
             LIMIT $limit",
            [$userId]
        ) ?: [];
        if (!$tasks) return ['classified' => 0, 'batch_size' => 0, 'api_error' => null];

        $apiKey = \Core\Settings::get('anthropic_api_key');
        if (!$apiKey) return ['classified' => 0, 'batch_size' => count($tasks), 'api_error' => 'kein Anthropic-Key'];

        $systemPrompt = <<<SYS
Klassifiziere jede Task nach Aktivitätstyp. Antworte ausschließlich als JSON-Array.
Pro Task: {"id":N,"activity_type":"..."} mit activity_type EXAKT aus:
- "meeting"       — Termin/Teamgespräch/Workshop/Call planen oder führen
- "approval"      — Freigabe geben, Korrektur abnehmen, OK-Klick
- "communication" — Mail/Telefonat/Nachricht an konkrete Person verfassen
- "writing"       — Text/Konzept/Vorlage schreiben (mehrere Absätze)
- "review"        — Prüfen/durchsehen/Feedback geben (ohne Schreiben)
- "research"      — Recherche, Quellen lesen, vergleichen
- "admin"         — Verwaltung, Buchhaltung, Abrechnung, Tool-Setup
- "planning"      — Strategie/Roadmap/Plan/Sequenz erarbeiten
- "execution"     — Hands-on Umsetzung (Code, Setup, Konfiguration)
- "creative"      — Design/Visual/Layout/Branding
- "other"         — passt in keine Kategorie
Format: [{"id":1,"activity_type":"communication"},{"id":2,"activity_type":"meeting"}]
SYS;

        $payload = array_map(fn($t) => [
            'id'      => (int)$t['id'],
            'name'    => $t['name'],
            'summary' => mb_substr((string)$t['ai_summary'], 0, 240),
        ], $tasks);

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($apiKey, 'anthropic');
        $ai->setModel('claude-haiku-4-5-20251001');
        $ai->setMaxTokens(2500);
        $ai->setTimeout(60);

        $apiError = null;
        $parsed = null;
        try {
            $resp = $ai->chat(
                [['role' => 'user', 'content' => "Tasks:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                $systemPrompt
            );
            $raw = trim($resp['content'] ?? '');
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw);
            $parsed = json_decode($clean, true);
        } catch (\Throwable $e) {
            $apiError = $e->getMessage();
        }
        if (!is_array($parsed)) $parsed = [];

        $allowed = ['meeting','approval','communication','writing','review','research','admin','planning','execution','creative','other'];
        $classified = 0;
        $byId = array_flip(array_map(fn($t) => (int)$t['id'], $tasks));
        foreach ($parsed as $e) {
            $id = (int)($e['id'] ?? 0);
            if (!isset($byId[$id])) continue;
            $type = in_array($e['activity_type'] ?? '', $allowed, true) ? $e['activity_type'] : 'other';
            $this->db->update('planner_tasks', ['ai_activity_type' => $type], 'id = ?', [$id]);
            $classified++;
        }
        // Tasks, die nicht geantwortet wurden, bekommen 'other' damit sie nicht im nächsten Lauf nochmal kommen.
        $unanswered = array_diff(array_keys($byId), array_map(fn($e) => (int)($e['id'] ?? 0), $parsed));
        foreach ($unanswered as $id) {
            $this->db->update('planner_tasks', ['ai_activity_type' => 'other'], 'id = ?', [$id]);
        }

        return ['classified' => $classified, 'batch_size' => count($tasks), 'api_error' => $apiError];
    }
}
