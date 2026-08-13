<?php
namespace Services;

/**
 * Prompt-Optimierung mit Feedback-Loop
 *
 * Speichert Prompt-Versionen, nimmt Feedback entgegen,
 * und optimiert den Prompt iterativ via LLM.
 */
class PromptOptimizer
{
    private $db;
    private string $promptKey;

    public function __construct(\Core\Database $db, string $promptKey = 'artifact_analysis')
    {
        $this->db = $db;
        $this->promptKey = $promptKey;
    }

    /**
     * Aktuelle Prompt-Version laden (hoechste Version)
     */
    public function getCurrentPrompt(): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM prompt_iterations WHERE prompt_key = ? ORDER BY version DESC LIMIT 1",
            [$this->promptKey]
        );
    }

    /**
     * Aktuelle Versionsnummer
     */
    public function getCurrentVersion(): int
    {
        $v = $this->db->queryValue(
            "SELECT MAX(version) FROM prompt_iterations WHERE prompt_key = ?",
            [$this->promptKey]
        );
        return (int)($v ?? 0);
    }

    /**
     * Prompt-Text der aktuellen Version holen (oder null wenn keine existiert)
     */
    public function getPromptText(): ?string
    {
        $current = $this->getCurrentPrompt();
        return $current ? $current['prompt_text'] : null;
    }

    /**
     * Neue Prompt-Version speichern
     */
    public function saveVersion(string $promptText, ?string $reasoning = null, ?int $userId = null, ?int $importId = null): int
    {
        $version = $this->getCurrentVersion() + 1;
        return (int)$this->db->insert('prompt_iterations', [
            'prompt_key' => $this->promptKey,
            'version' => $version,
            'prompt_text' => $promptText,
            'optimization_reasoning' => $reasoning,
            'created_by' => $userId,
            'import_id' => $importId,
        ]);
    }

    /**
     * Feedback zu einer Version speichern
     */
    public function saveFeedback(int $iterationId, string $positive, string $negative, ?int $score = null): void
    {
        $this->db->update('prompt_iterations', [
            'feedback_positive' => $positive,
            'feedback_negative' => $negative,
            'performance_score' => $score,
            'feedback' => trim($positive . "\n---\n" . $negative),
        ], 'id = ?', [$iterationId]);
    }

    /**
     * Prompt optimieren basierend auf Feedback-Historie
     */
    public function optimize(AIService $ai, ?string $additionalFeedback = null): array
    {
        $current = $this->getCurrentPrompt();
        if (!$current) {
            throw new \Exception('Kein aktueller Prompt vorhanden');
        }

        // Letzte 5 Versionen mit Feedback laden
        $history = $this->db->query(
            "SELECT version, prompt_text, feedback_positive, feedback_negative, performance_score, optimization_reasoning
             FROM prompt_iterations WHERE prompt_key = ? AND feedback IS NOT NULL
             ORDER BY version DESC LIMIT 5",
            [$this->promptKey]
        );

        // Feedback-Block bauen
        $feedbackBlock = '';
        foreach ($history as $h) {
            $feedbackBlock .= "\n--- Version {$h['version']} ---\n";
            if ($h['feedback_positive']) $feedbackBlock .= "GUT: {$h['feedback_positive']}\n";
            if ($h['feedback_negative']) $feedbackBlock .= "SCHLECHT: {$h['feedback_negative']}\n";
            if ($h['performance_score']) $feedbackBlock .= "Score: {$h['performance_score']}/10\n";
        }

        if ($additionalFeedback) {
            $feedbackBlock .= "\n--- Aktuelles Feedback ---\n{$additionalFeedback}\n";
        }

        $optimizationPrompt = <<<PROMPT
Du bist ein Prompt-Engineer. Deine Aufgabe ist es, einen System-Prompt fuer einen Content-Extraktor zu optimieren.

=== AKTUELLER PROMPT (Version {$current['version']}) ===
{$current['prompt_text']}

=== FEEDBACK-HISTORIE ===
{$feedbackBlock}

=== DEINE AUFGABE ===
1. Analysiere das Feedback: Was hat gut funktioniert? Was nicht?
2. Optimiere den Prompt so, dass die negativen Punkte behoben werden, OHNE die positiven Punkte zu verschlechtern.
3. Behalte die Grundstruktur bei — aendere nur was noetig ist.
4. Sei konkret in deinen Aenderungen. Keine vagen Anweisungen.

Antworte mit GENAU diesem Format:

=== REASONING ===
(Was du geaendert hast und warum, 2-3 Saetze)

=== OPTIMIZED PROMPT ===
(Der vollstaendige optimierte Prompt-Text)
PROMPT;

        $result = $ai->chat(
            [['role' => 'user', 'content' => $optimizationPrompt]],
            'Du bist ein erfahrener Prompt-Engineer. Optimiere den Prompt basierend auf dem Feedback.'
        );

        $response = $result['content'] ?? '';

        // Reasoning und Prompt extrahieren
        $reasoning = '';
        $newPrompt = '';

        if (preg_match('/===\s*REASONING\s*===\s*\n(.*?)\n\s*===\s*OPTIMIZED PROMPT\s*===/s', $response, $m)) {
            $reasoning = trim($m[1]);
        }
        if (preg_match('/===\s*OPTIMIZED PROMPT\s*===\s*\n(.*)/s', $response, $m)) {
            $newPrompt = trim($m[1]);
        }

        if (empty($newPrompt)) {
            throw new \Exception('KI konnte keinen optimierten Prompt generieren');
        }

        return [
            'prompt_text' => $newPrompt,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Alle Versionen laden (fuer Historie)
     */
    public function getHistory(int $limit = 20): array
    {
        return $this->db->query(
            "SELECT pi.*, u.name as user_name
             FROM prompt_iterations pi
             LEFT JOIN users u ON u.id = pi.created_by
             WHERE pi.prompt_key = ?
             ORDER BY pi.version DESC LIMIT ?",
            [$this->promptKey, $limit]
        );
    }

    /**
     * Bestimmte Version laden
     */
    public function getVersion(int $version): ?array
    {
        return $this->db->queryOne(
            "SELECT * FROM prompt_iterations WHERE prompt_key = ? AND version = ?",
            [$this->promptKey, $version]
        );
    }

    /**
     * Zu einer bestimmten Version zurueckkehren (erstellt neue Version mit dem alten Text)
     */
    public function rollback(int $toVersion, ?int $userId = null): int
    {
        $old = $this->getVersion($toVersion);
        if (!$old) throw new \Exception("Version {$toVersion} nicht gefunden");

        return $this->saveVersion(
            $old['prompt_text'],
            "Rollback zu Version {$toVersion}",
            $userId
        );
    }
}
