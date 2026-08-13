<?php
/**
 * Usage Tracker - Verbrauchstracking
 */

namespace Services;

use Core\Database;

class UsageTracker
{
    private Database $db;

    // Kosten pro 1000 Tokens (Schaetzwerte)
    private const COSTS = [
        'gpt-5' => ['input' => 0.005, 'output' => 0.015],
        'gpt-5-mini' => ['input' => 0.0003, 'output' => 0.0012],
        'gpt-5-nano' => ['input' => 0.0001, 'output' => 0.0004],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
        'text-embedding-3-small' => ['input' => 0.00002, 'output' => 0],
        'text-embedding-3-large' => ['input' => 0.00013, 'output' => 0]
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Verbrauch loggen
     */
    public function log(
        ?int $customerId,
        int $userId,
        string $actionType,
        string $model,
        string $provider,
        int $tokensInput,
        int $tokensOutput,
        int $wordsGenerated = 0,
        array $metadata = []
    ): int {
        $cost = $this->calculateCost($model, $tokensInput, $tokensOutput);

        $logId = $this->db->insert('usage_logs', [
            'customer_id' => $customerId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'model_used' => $model,
            'api_provider' => $provider,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'words_generated' => $wordsGenerated,
            'cost_estimate' => $cost,
            'metadata' => json_encode($metadata)
        ]);

        // Monatliche Zusammenfassung aktualisieren
        if ($customerId) {
            $this->updateSummary($customerId, $tokensInput, $tokensOutput, $wordsGenerated, $cost);
        }

        return $logId;
    }

    /**
     * Kosten berechnen
     */
    private function calculateCost(string $model, int $tokensInput, int $tokensOutput): float
    {
        // Modell normalisieren
        $normalizedModel = $model;
        foreach (array_keys(self::COSTS) as $key) {
            if (strpos($model, $key) !== false) {
                $normalizedModel = $key;
                break;
            }
        }

        $costs = self::COSTS[$normalizedModel] ?? ['input' => 0.01, 'output' => 0.03];

        $inputCost = ($tokensInput / 1000) * $costs['input'];
        $outputCost = ($tokensOutput / 1000) * $costs['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Monatliche Zusammenfassung aktualisieren
     */
    private function updateSummary(
        int $customerId,
        int $tokensInput,
        int $tokensOutput,
        int $wordsGenerated,
        float $cost
    ): void {
        $yearMonth = date('Y-m');

        $existing = $this->db->queryOne(
            "SELECT id FROM usage_summary WHERE customer_id = ? AND `year_month` = ?",
            [$customerId, $yearMonth]
        );

        if ($existing) {
            $this->db->execute(
                "UPDATE usage_summary SET
                    total_api_calls = total_api_calls + 1,
                    total_tokens_input = total_tokens_input + ?,
                    total_tokens_output = total_tokens_output + ?,
                    total_words_generated = total_words_generated + ?,
                    total_cost_estimate = total_cost_estimate + ?
                 WHERE id = ?",
                [$tokensInput, $tokensOutput, $wordsGenerated, $cost, $existing['id']]
            );
        } else {
            $this->db->insert('usage_summary', [
                'customer_id' => $customerId,
                '`year_month`' => $yearMonth,
                'total_api_calls' => 1,
                'total_tokens_input' => $tokensInput,
                'total_tokens_output' => $tokensOutput,
                'total_words_generated' => $wordsGenerated,
                'total_cost_estimate' => $cost
            ]);
        }
    }

    /**
     * Statistiken fuer Kunden abrufen
     */
    public function getCustomerStats(int $customerId, ?string $yearMonth = null): array
    {
        $yearMonth = $yearMonth ?: date('Y-m');

        $summary = $this->db->queryOne(
            "SELECT * FROM usage_summary WHERE customer_id = ? AND `year_month` = ?",
            [$customerId, $yearMonth]
        );

        $daily = $this->db->query(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as calls,
                    SUM(tokens_input + tokens_output) as tokens,
                    SUM(words_generated) as words,
                    SUM(cost_estimate) as cost
             FROM usage_logs
             WHERE customer_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            [$customerId, $yearMonth]
        );

        $byModel = $this->db->query(
            "SELECT model_used,
                    COUNT(*) as calls,
                    SUM(tokens_input + tokens_output) as tokens,
                    SUM(cost_estimate) as cost
             FROM usage_logs
             WHERE customer_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?
             GROUP BY model_used
             ORDER BY calls DESC",
            [$customerId, $yearMonth]
        );

        return [
            'summary' => $summary ?: [
                'total_api_calls' => 0,
                'total_tokens_input' => 0,
                'total_tokens_output' => 0,
                'total_words_generated' => 0,
                'total_cost_estimate' => 0
            ],
            'daily' => $daily,
            'by_model' => $byModel
        ];
    }

    /**
     * Globale Statistiken (Admin)
     */
    public function getGlobalStats(?string $yearMonth = null): array
    {
        $yearMonth = $yearMonth ?: date('Y-m');

        $totals = $this->db->queryOne(
            "SELECT
                SUM(total_api_calls) as total_calls,
                SUM(total_tokens_input) as total_input,
                SUM(total_tokens_output) as total_output,
                SUM(total_words_generated) as total_words,
                SUM(total_cost_estimate) as total_cost,
                COUNT(DISTINCT customer_id) as active_customers
             FROM usage_summary
             WHERE `year_month` = ?",
            [$yearMonth]
        );

        $byCustomer = $this->db->query(
            "SELECT us.*, c.name as customer_name
             FROM usage_summary us
             JOIN customers c ON us.customer_id = c.id
             WHERE us.`year_month` = ?
             ORDER BY us.total_cost_estimate DESC",
            [$yearMonth]
        );

        $byModel = $this->db->query(
            "SELECT model_used,
                    COUNT(*) as total_calls,
                    SUM(tokens_input) as total_tokens_input,
                    SUM(tokens_output) as total_tokens_output,
                    SUM(words_generated) as total_words_generated,
                    SUM(cost_estimate) as total_cost_estimate
             FROM usage_logs
             WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
             GROUP BY model_used
             ORDER BY total_calls DESC",
            [$yearMonth]
        );

        $modelRatings = $this->db->query(
            "SELECT
                asec.model_used,
                COUNT(sf.id) as total_ratings,
                SUM(CASE WHEN sf.rating = 'positive' THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN sf.rating = 'negative' THEN 1 ELSE 0 END) as negative,
                SUM(CASE WHEN sf.rating = 'neutral' THEN 1 ELSE 0 END) as neutral,
                ROUND(SUM(CASE WHEN sf.rating = 'positive' THEN 1 ELSE 0 END) * 100.0 / COUNT(sf.id), 1) as positive_rate
             FROM section_feedback sf
             JOIN article_sections asec ON sf.section_id = asec.id
             WHERE asec.model_used IS NOT NULL
             GROUP BY asec.model_used
             ORDER BY positive_rate DESC"
        );

        return [
            'totals' => $totals,
            'by_customer' => $byCustomer,
            'by_model' => $byModel,
            'model_ratings' => $modelRatings,
            'year_month' => $yearMonth
        ];
    }
}
