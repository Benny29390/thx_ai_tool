<?php
/**
 * Feedback Analyzer - Feedback analysieren und Regelvorschlaege generieren
 */

namespace Services;

use Core\Database;

class FeedbackAnalyzer
{
    private Database $db;
    private ?AIService $ai = null;

    // Mindestanzahl negatives Feedback fuer Regelvorschlag
    private const MIN_NEGATIVE_FEEDBACK = 3;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * AI Service setzen
     */
    public function setAIService(AIService $ai): void
    {
        $this->ai = $ai;
    }

    /**
     * Negatives Feedback analysieren und Muster erkennen
     */
    public function analyzeNegativeFeedback(int $customerId): array
    {
        // Negatives Feedback der letzten 30 Tage laden
        $feedback = $this->db->query(
            "SELECT sf.*, asec.heading_text, asec.content as section_content
             FROM section_feedback sf
             JOIN article_sections asec ON sf.section_id = asec.id
             JOIN article_versions av ON asec.article_version_id = av.id
             JOIN projects p ON av.project_id = p.id
             WHERE p.customer_id = ?
             AND sf.rating = 'negative'
             AND sf.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY sf.created_at DESC",
            [$customerId]
        );

        if (count($feedback) < self::MIN_NEGATIVE_FEEDBACK) {
            return [
                'patterns' => [],
                'message' => 'Nicht genuegend Feedback fuer Analyse (mindestens ' . self::MIN_NEGATIVE_FEEDBACK . ' benoetigt)'
            ];
        }

        // Kommentare sammeln
        $comments = array_filter(array_column($feedback, 'comment'));
        $improvementRequests = array_filter(array_column($feedback, 'improvement_request'));

        // Muster erkennen (einfache Keyword-Analyse)
        $patterns = $this->findPatterns(array_merge($comments, $improvementRequests));

        return [
            'patterns' => $patterns,
            'total_feedback' => count($feedback),
            'feedback_with_comments' => count($comments)
        ];
    }

    /**
     * Muster in Feedback-Texten finden
     */
    private function findPatterns(array $texts): array
    {
        $keywords = [];

        // Haeufige Woerter zaehlen
        foreach ($texts as $text) {
            $words = preg_split('/\s+/', strtolower($text));
            foreach ($words as $word) {
                $word = trim($word, '.,!?;:()[]{}');
                if (strlen($word) > 3) {
                    $keywords[$word] = ($keywords[$word] ?? 0) + 1;
                }
            }
        }

        // Nach Haeufigkeit sortieren
        arsort($keywords);

        // Top-Keywords als Muster
        $patterns = [];
        $i = 0;
        foreach ($keywords as $word => $count) {
            if ($count >= 2 && $i < 10) {
                $patterns[] = [
                    'keyword' => $word,
                    'count' => $count,
                    'percentage' => round(($count / count($texts)) * 100)
                ];
                $i++;
            }
        }

        return $patterns;
    }

    /**
     * Regelvorschlag mit KI generieren
     */
    public function generateRuleSuggestion(int $customerId): ?array
    {
        if (!$this->ai) {
            throw new \Exception('AIService nicht konfiguriert');
        }

        // Negatives Feedback laden
        $feedback = $this->db->query(
            "SELECT sf.comment, sf.improvement_request
             FROM section_feedback sf
             JOIN article_sections asec ON sf.section_id = asec.id
             JOIN article_versions av ON asec.article_version_id = av.id
             JOIN projects p ON av.project_id = p.id
             WHERE p.customer_id = ?
             AND sf.rating = 'negative'
             AND (sf.comment IS NOT NULL OR sf.improvement_request IS NOT NULL)
             AND sf.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY sf.created_at DESC
             LIMIT 20",
            [$customerId]
        );

        if (count($feedback) < self::MIN_NEGATIVE_FEEDBACK) {
            return null;
        }

        // Feedback-Texte sammeln
        $feedbackTexts = [];
        foreach ($feedback as $fb) {
            if ($fb['comment']) $feedbackTexts[] = "Kritik: " . $fb['comment'];
            if ($fb['improvement_request']) $feedbackTexts[] = "Verbesserungswunsch: " . $fb['improvement_request'];
        }

        // KI-Prompt
        $prompt = "Analysiere das folgende Feedback zu KI-generierten Texten und schlage eine konkrete Regel vor, die zukuenftig beachtet werden soll.\n\n";
        $prompt .= "Feedback:\n" . implode("\n", $feedbackTexts) . "\n\n";
        $prompt .= "Erstelle EINE konkrete Regel als kurzen, klaren Satz. Beispiel: 'Verwende keine Gedankenstriche im Text.'\n";
        $prompt .= "Antworte NUR mit der Regel, ohne Erklaerung. Bestimme auch den Regeltyp (style, format, content, link, tone).";
        $prompt .= "\n\nFormat: REGEL: [Die Regel]\nTYP: [style/format/content/link/tone]\nKONFIDENZ: [0-100]";

        try {
            $response = $this->ai->chat([
                ['role' => 'user', 'content' => $prompt]
            ], 'Du bist ein Experte fuer Textqualitaet und Stilregeln.');

            // Antwort parsen
            $content = $response['content'];
            $rule = null;
            $type = 'style';
            $confidence = 0.5;

            if (preg_match('/REGEL:\s*(.+)/i', $content, $matches)) {
                $rule = trim($matches[1]);
            }
            if (preg_match('/TYP:\s*(\w+)/i', $content, $matches)) {
                $type = strtolower(trim($matches[1]));
                if (!in_array($type, ['style', 'format', 'content', 'link', 'tone'])) {
                    $type = 'style';
                }
            }
            if (preg_match('/KONFIDENZ:\s*(\d+)/i', $content, $matches)) {
                $confidence = min(100, max(0, (int) $matches[1])) / 100;
            }

            if (!$rule) {
                // Fallback: gesamte Antwort als Regel
                $rule = trim(preg_replace('/^(REGEL|TYP|KONFIDENZ):.*/m', '', $content));
            }

            if (empty($rule)) {
                return null;
            }

            // Vorschlag in DB speichern
            $suggestionId = $this->db->insert('rule_suggestions', [
                'customer_id' => $customerId,
                'suggested_rule' => $rule,
                'rule_type' => $type,
                'confidence_score' => $confidence,
                'status' => 'pending'
            ]);

            return [
                'id' => $suggestionId,
                'suggested_rule' => $rule,
                'rule_type' => $type,
                'confidence_score' => $confidence,
                'based_on_feedback' => count($feedback)
            ];

        } catch (\Exception $e) {
            error_log("Regel-Generierung fehlgeschlagen: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Feedback-Statistiken
     */
    public function getStats(int $customerId): array
    {
        return [
            'total' => $this->db->queryValue(
                "SELECT COUNT(*) FROM section_feedback sf
                 JOIN article_sections asec ON sf.section_id = asec.id
                 JOIN article_versions av ON asec.article_version_id = av.id
                 JOIN projects p ON av.project_id = p.id
                 WHERE p.customer_id = ?",
                [$customerId]
            ),
            'positive' => $this->db->queryValue(
                "SELECT COUNT(*) FROM section_feedback sf
                 JOIN article_sections asec ON sf.section_id = asec.id
                 JOIN article_versions av ON asec.article_version_id = av.id
                 JOIN projects p ON av.project_id = p.id
                 WHERE p.customer_id = ? AND sf.rating = 'positive'",
                [$customerId]
            ),
            'negative' => $this->db->queryValue(
                "SELECT COUNT(*) FROM section_feedback sf
                 JOIN article_sections asec ON sf.section_id = asec.id
                 JOIN article_versions av ON asec.article_version_id = av.id
                 JOIN projects p ON av.project_id = p.id
                 WHERE p.customer_id = ? AND sf.rating = 'negative'",
                [$customerId]
            ),
            'pending_suggestions' => $this->db->queryValue(
                "SELECT COUNT(*) FROM rule_suggestions WHERE customer_id = ? AND status = 'pending'",
                [$customerId]
            )
        ];
    }
}
