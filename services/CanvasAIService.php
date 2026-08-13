<?php
/**
 * Canvas AI Service — KI-Sparringspartner fuer Canvas-Projekte
 *
 * Baut Prompts auf, steuert Streaming, parst Actions aus LLM-Antworten
 */

namespace Services;

use Core\Database;

class CanvasAIService
{
    private Database $db;
    private CanvasService $canvasService;

    // Feld-spezifische Prompts (aus Briefing Kap. 6.2)
    private const FIELD_PROMPTS = [
        'problem' => 'Aktuelles Feld: PROBLEM
Fuehre ein Interview zum Problem. Frage zuerst: Was ist das konkrete Problem das geloest werden soll?
Dann vertiefe: Wen betrifft es? Wie gross ist es (Zahlen, Kosten, Zeitverlust)? Was passiert wenn nichts getan wird?
Achte darauf: Problem und Loesung duerfen NICHT vermischt werden. Wenn der User eine Loesung beschreibt, frage zurueck was das zugrunde liegende Problem ist.',

        'loesung' => 'Aktuelles Feld: LOESUNG
Fuehre ein Interview zur angestrebten Loesung. Frage: Was soll nach dem Projekt anders sein? Wie sieht der Zielzustand aus?
Vertiefe: Woran erkennt man dass das Problem geloest ist? Welche konkreten Metriken zeigen Erfolg?
Warne bei ueberdimensionierten Zielen. Die Loesung muss direkt auf das definierte Problem antworten.',

        'input' => 'Aktuelles Feld: INPUT (Daten, Quellen, Material)
Fuehre ein Interview zu den benoetigten Inputs. Frage: Welche Daten und Materialien braucht die KI um das Problem zu loesen?
Vertiefe pro Input: Format? Qualitaet? Verfuegbarkeit? Wer liefert die Daten?
Vergleiche mit Problem und Loesung — passen die Inputs dazu? Warne bei Scope Creep.',

        'magie' => 'Aktuelles Feld: MAGIE (KI-Verarbeitung, Kernlogik)
Fuehre ein Interview zur KI-Verarbeitung. Frage: Was genau soll die KI mit den Inputs machen?
Vertiefe: Welche KI-Modelle/Technologien kommen zum Einsatz? Was passiert mit jedem Input-Typ? Braucht es Training oder reicht Prompting?
Pruefe: Werden ALLE definierten Inputs adressiert?',

        'qualitaetssicherung' => 'Aktuelles Feld: QUALITAETSSICHERUNG
Fuehre ein Interview zur QS. Frage: Wie wird sichergestellt dass die KI-Ergebnisse korrekt sind?
Vertiefe: Welche Abnahmekriterien gibt es? Wer prueft? Automatisch oder manuell? Welche Schwellenwerte?
Pruefe: Gibt es QS-Kriterien fuer jeden Input-Typ und jeden Output?',

        'output' => 'Aktuelles Feld: OUTPUT (konkrete Ergebnisse, Deliverables)
Fuehre ein Interview zu den Outputs. Frage: Was genau soll die KI produzieren/liefern?
Vertiefe: In welchem Format? Fuer wen? Wie oft? MVP oder Endprodukt?
Pruefe Konsistenz: Passt der Output zur definierten Loesung?',

        'ergebnisse' => 'Aktuelles Feld: ERGEBNISSE (Business Impact)
Fuehre ein Interview zum erwarteten Nutzen. Frage: Welchen messbaren Nutzen bringt das Projekt?
Vertiefe: Zeitersparnis? Kostensenkung? Umsatzsteigerung? Qualitaetsverbesserung? Konkrete Zahlen!
Hinterfrage den Realismus. Vergleiche mit dem definierten Problem.',

        'risiken' => 'Aktuelles Feld: RISIKEN
Fuehre ein Interview zu Risiken. Frage: Was kann schiefgehen? Was sind die groessten Unsicherheiten?
Vertiefe pro Risiko: Eintrittswahrscheinlichkeit? Impact? Gegenmassnahme? Plan B?
Pruefe: Gibt es Risiken die durch besseren Input entschaerft werden koennten?',
    ];

    public function __construct(Database $db, CanvasService $canvasService)
    {
        $this->db = $db;
        $this->canvasService = $canvasService;
    }

    /**
     * Baut den System-Prompt fuer das KI-Sparring zusammen
     */
    public function buildSystemPrompt(int $canvasId, ?string $userRole = null): string
    {
        $parts = [];

        // 1. Basis-Rolle
        $parts[] = "Du bist KI-Sparringspartner im KI Kompass. Du fuehrst ein Interview zu einem KI-Projekt mit 8 Feldern: Problem, Loesung, Input, Magie, Qualitaetssicherung, Output, Ergebnisse, Risiken.

OBERSTE PRIORITAET — DAS HIER STEHT UEBER ALLEM:
Lies die letzte Nachricht des Users. Reagiere DIREKT darauf. Wenn der User sagt 'DSGVO bei Risiko' dann erstelle SOFORT eine Risiko-Karte zu DSGVO. Wenn der User sagt 'weiter' dann wechsle das Feld. Was der User sagt hat IMMER Vorrang vor deinem Interview-Plan.

REGELN:
- Reagiere auf den User, dann stelle eine Folgefrage. Nie umgekehrt.
- Wiederhole NIE eine Frage. Stelle jede Frage nur einmal. Wenn der User nicht darauf eingeht, akzeptiere das und mach weiter.
- Biete Antwort-Vorschlaege an: <<Vorschlag>> (jeder auf eigener Zeile).
- Antworte auf Deutsch, praegnant.
- Wechsle selbst zwischen Feldern. Die Reihenfolge ist flexibel.
- Erstelle Karten im richtigen Feld (field-Parameter bei create_card).
- Schau in den CANVAS-STAND bevor du Karten erstellst. Keine Duplikate — update bestehende Karten.";

        // 2. Action-Instruktionen
        $parts[] = "Du kannst Karten erstellen und bearbeiten. Nutze dieses Format:

[ACTION:create_card]{\"field\":\"problem\",\"title\":\"...\",\"content\":\"...\",\"status\":\"entwurf\"}[/ACTION]
[ACTION:update_card]{\"card_id\":42,\"content\":\"...neuer Inhalt...\"}[/ACTION]

WICHTIG: Das 'field' bei create_card bestimmt in welches Feld die Karte kommt. Waehle das passende Feld basierend auf dem Inhalt.

KEINE DUPLIKATE:
- Schau in den CANVAS-STAND — dort stehen alle Karten mit ID und Inhalt.
- Nur create_card wenn wirklich NEUER Aspekt. Sonst update_card mit existierender ID.
- Du darfst pro Antwort MEHRERE Actions ausfuehren (2-3 Karten gleichzeitig) wenn es inhaltlich sinnvoll ist. Lieber mehr Karten als zu wenige.

<<Vorschlag>> werden als klickbare Buttons dargestellt.";

        // 3. Feld-spezifische Leitfragen (alle zusammen als Referenz)
        $parts[] = "LEITFRAGEN PRO FELD (nutze als Orientierung):
- Problem: Quantifizierung, Betroffene, Kosten des Nichtstuns, Problem/Loesung nicht vermischen
- Loesung: Messbare Ziele, Metriken, direkte Antwort auf Problem, Realismus
- Input: Datenquellen, Format, Qualitaet, Verfuegbarkeit, Scope-Creep vermeiden
- Magie: KI-Technologie, Verarbeitung pro Input-Typ, Sicherheitsarchitektur
- QS: Abnahmekriterien, Schwellenwerte, Testszenarien, wer prueft
- Output: Konkrete Deliverables, Format, Reifegrad, Konsistenz mit Loesung
- Ergebnisse: ROI, Zeitersparnis, Kostensenkung, Realismus, messbare Zahlen
- Risiken: Eintrittswahrscheinlichkeit, Impact, Gegenmassnahmen, Plan B";

        // 4. Rollen-Kontext
        if ($userRole === 'auftraggeber') {
            $parts[] = "Der Gespraechspartner ist AUFTRAGGEBER (fachliche Perspektive). Stelle fachliche Fragen, klaere Anforderungen, hinterfrage Business-Logik.";
        } elseif ($userRole === 'entwickler') {
            $parts[] = "Der Gespraechspartner ist ENTWICKLER (technische Perspektive). Stelle technische Fragen, pruefe Machbarkeit, diskutiere Architektur.";
        }

        // 5. Voller Canvas-Kontext
        $canvasContext = $this->buildCanvasContext($canvasId);
        if ($canvasContext) {
            $parts[] = "--- AKTUELLER CANVAS-STAND ---\n" . $canvasContext . "\n--- ENDE CANVAS-STAND ---";
        }

        // 6. WIEDERHOLUNG am Ende (Recency Bias nutzen — das Letzte bleibt haengen)
        $parts[] = "ERINNERUNG:
1. Lies die LETZTE User-Nachricht. Reagiere darauf mit passenden Karten-Actions.
2. Stelle dann EINE Frage zu einem NEUEN Thema oder NEUEN Feld. NIEMALS eine Frage wiederholen die im Chatverlauf schon vorkam.
3. Wenn der User sagt 'weitere Risiken' oder aehnlich: Schlage selbst 2-3 Risiken vor und erstelle Karten dafuer. Frag nicht nach — handle!
4. Erstelle lieber MEHR Karten (2-3 pro Antwort) als zu wenige. Jeder neue Aspekt verdient eine eigene Karte.";

        // 7. Globale Guidelines (Tool-Kommunikation + Sprache/Format)
        try {
            $glService = new \Services\GuidelineService($this->db);
            $glBlock = $glService->buildPromptBlock(['tool_communication', 'internal']);
            if ($glBlock !== '') $parts[] = $glBlock;
        } catch (\Exception $e) {}

        return implode("\n\n", $parts);
    }

    /**
     * Baut den vollen Canvas-Kontext als strukturierten Text (max 30.000 Zeichen)
     */
    private function buildCanvasContext(int $canvasId): string
    {
        $project = $this->canvasService->getProject($canvasId);
        if (!$project) return '';

        $cards = $this->canvasService->getCardsByCanvas($canvasId);
        $completeness = $this->canvasService->calculateCompleteness($canvasId);

        $context = "Projekt: {$project['title']}\n";
        if ($project['description']) {
            $context .= "Beschreibung: {$project['description']}\n";
        }
        $context .= "Briefing-Reife: {$completeness['global']}%\n\n";

        $totalChars = mb_strlen($context);
        $maxChars = 30000;

        foreach (CanvasService::FIELDS as $field) {
            $label = CanvasService::FIELD_LABELS[$field];
            $fieldCards = $cards[$field] ?? [];
            $fieldInfo = $completeness['fields'][$field] ?? [];
            $percent = $fieldInfo['percent'] ?? 0;

            $fieldBlock = "### {$label} ({$percent}%)\n";

            if (empty($fieldCards)) {
                $fieldBlock .= "(keine Karten)\n";
            } else {
                foreach ($fieldCards as $card) {
                    $statusLabel = match($card['status']) {
                        'vollstaendig' => 'VOLLSTAENDIG',
                        'in_arbeit' => 'IN ARBEIT',
                        default => 'ENTWURF',
                    };
                    $fieldBlock .= "- [ID:{$card['id']}] [{$statusLabel}] {$card['title']}";
                    if ($card['content']) {
                        $excerpt = mb_substr($card['content'], 0, 500);
                        if (mb_strlen($card['content']) > 500) $excerpt .= '...';
                        $fieldBlock .= "\n  {$excerpt}";
                    }
                    $fieldBlock .= "\n";
                }
            }
            $fieldBlock .= "\n";

            if ($totalChars + mb_strlen($fieldBlock) > $maxChars) break;
            $context .= $fieldBlock;
            $totalChars += mb_strlen($fieldBlock);
        }

        return $context;
    }

    /**
     * Parst Actions aus der LLM-Antwort und fuehrt sie aus
     *
     * @return array{text: string, actions: array} Bereinigter Text + ausgefuehrte Actions
     */
    public function parseAndExecuteActions(string $content, int $canvasId, int $userId): array
    {
        $actions = [];
        $cleanText = $content;

        // [ACTION:type]{json}[/ACTION] Bloecke finden
        $pattern = '/\[ACTION:(\w+)\](.*?)\[\/ACTION\]/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $actionType = $match[1];
            $jsonStr = trim($match[2]);
            $data = json_decode($jsonStr, true);

            if (!$data) continue;

            $result = null;
            try {
                switch ($actionType) {
                    case 'create_card':
                        if (isset($data['field'], $data['title'])) {
                            // Duplikat-Check: Gibt es schon eine Karte mit aehnlichem Titel im selben Feld?
                            $existingCards = $this->canvasService->getCardsByCanvas($canvasId);
                            $fieldCards = $existingCards[$data['field']] ?? [];
                            $newTitleNorm = mb_strtolower(trim($data['title']));
                            $duplicate = null;

                            foreach ($fieldCards as $existing) {
                                $existTitleNorm = mb_strtolower(trim($existing['title']));
                                // Exakte Uebereinstimmung oder sehr aehnlich (ein Titel enthaelt den anderen)
                                if ($existTitleNorm === $newTitleNorm
                                    || mb_strpos($existTitleNorm, $newTitleNorm) !== false
                                    || mb_strpos($newTitleNorm, $existTitleNorm) !== false
                                    || similar_text($existTitleNorm, $newTitleNorm, $percent) && $percent > 70
                                ) {
                                    $duplicate = $existing;
                                    break;
                                }
                            }

                            if ($duplicate) {
                                // Duplikat gefunden → Update statt Create
                                $mergedContent = $duplicate['content'];
                                if (!empty($data['content'])) {
                                    // Neuen Inhalt anhaengen wenn er sich unterscheidet
                                    if (mb_strpos($mergedContent ?? '', $data['content']) === false) {
                                        $mergedContent = trim(($mergedContent ?? '') . "\n\n" . $data['content']);
                                    }
                                }
                                $this->canvasService->updateCard($duplicate['id'], [
                                    'content' => $mergedContent,
                                ], $userId);
                                $data['card_id'] = $duplicate['id'];
                                $data['deduplicated'] = true;
                                $result = ['action' => 'update_card', 'data' => $data, 'success' => true];
                            } else {
                                $cardId = $this->canvasService->createCard([
                                    'canvas_id' => $canvasId,
                                    'field' => $data['field'],
                                    'title' => $data['title'],
                                    'content' => $data['content'] ?? null,
                                    'status' => $data['status'] ?? 'entwurf',
                                ], $userId);
                                $data['created_id'] = $cardId;
                                $result = ['action' => 'create_card', 'data' => $data, 'success' => true];
                            }
                        }
                        break;

                    case 'update_card':
                        if (isset($data['card_id'])) {
                            $card = $this->canvasService->getCard((int)$data['card_id']);
                            if ($card && $card['canvas_id'] == $canvasId) {
                                $updateData = [];
                                if (isset($data['title'])) $updateData['title'] = $data['title'];
                                if (isset($data['content'])) $updateData['content'] = $data['content'];
                                if (isset($data['status'])) $updateData['status'] = $data['status'];
                                $this->canvasService->updateCard((int)$data['card_id'], $updateData, $userId);
                                $result = ['action' => 'update_card', 'data' => $data, 'success' => true];
                            }
                        }
                        break;

                    case 'create_reference':
                        if (isset($data['source_card_id'], $data['target_card_id'])) {
                            try {
                                $this->canvasService->createReference([
                                    'source_card_id' => (int) $data['source_card_id'],
                                    'target_card_id' => (int) $data['target_card_id'],
                                    'reference_type' => $data['reference_type'] ?? 'relates_to',
                                    'note' => $data['note'] ?? null,
                                ], $userId);
                                $result = ['action' => 'create_reference', 'data' => $data, 'success' => true];
                            } catch (\Exception $e) {
                                // Duplicate or invalid — skip
                            }
                        }
                        break;

                    case 'suggest_status':
                        if (isset($data['card_id'], $data['suggested_status'])) {
                            $result = ['action' => 'suggest_status', 'data' => $data, 'success' => true];
                        }
                        break;
                }
            } catch (\Exception $e) {
                error_log("Canvas AI action error: " . $e->getMessage());
            }

            if ($result) {
                $actions[] = $result;
            }

            // Action-Block aus dem Text entfernen
            $cleanText = str_replace($match[0], '', $cleanText);
        }

        // Bereinigter Text: leere Zeilen am Ende entfernen
        $cleanText = rtrim($cleanText);

        return [
            'text' => $cleanText,
            'actions' => $actions,
        ];
    }

    /**
     * Vollstaendigkeitscheck fuer ein Feld
     */
    public function checkFieldCompleteness(int $canvasId, string $field): string
    {
        $cards = $this->canvasService->getCardsByCanvas($canvasId);
        $fieldCards = $cards[$field] ?? [];
        $label = CanvasService::FIELD_LABELS[$field] ?? $field;

        if (empty($fieldCards)) {
            return "Das Feld \"{$label}\" hat noch keine Karten. Hier fehlt alles.";
        }

        $total = count($fieldCards);
        $complete = 0;
        $drafts = [];
        $inProgress = [];

        foreach ($fieldCards as $card) {
            if ($card['status'] === 'vollstaendig') {
                $complete++;
            } elseif ($card['status'] === 'in_arbeit') {
                $inProgress[] = $card['title'];
            } else {
                $drafts[] = $card['title'];
            }
        }

        $result = "Feld \"{$label}\": {$total} Karten, davon {$complete} vollstaendig.";
        if (!empty($drafts)) {
            $result .= "\nEntwuerfe: " . implode(', ', $drafts);
        }
        if (!empty($inProgress)) {
            $result .= "\nIn Arbeit: " . implode(', ', $inProgress);
        }

        return $result;
    }

    /**
     * Bestimmt die Rolle des Users im Canvas
     */
    public function getUserRole(int $canvasId, int $userId): ?string
    {
        $participant = $this->db->queryOne(
            "SELECT canvas_role FROM canvas_participants WHERE canvas_id = ? AND user_id = ?",
            [$canvasId, $userId]
        );
        return $participant ? $participant['canvas_role'] : null;
    }
}
