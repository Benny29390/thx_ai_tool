<?php
/**
 * LlmRequestLog — Per-Request-Performance-Log fuer alle LLM-Calls (Claude / OpenAI / Lokal).
 *
 * Erfasst Geschwindigkeit (TTFT, Total, tokens/s), Tokens und Erfolg/Fehler,
 * damit lokale Modelle gegen Cloud-Modelle verglichen werden koennen.
 * Schreibt nie Exceptions nach aussen — Logging darf den eigentlichen Call nie kippen.
 */

namespace Services;

use Core\Database;

class LlmRequestLog
{
    /** Detail-Daten (Prompt/Chunks/Antwort) werden nach so vielen Tagen automatisch geloescht. */
    const DETAIL_RETENTION_DAYS = 90;

    /**
     * Einen LLM-Request protokollieren.
     *
     * Erwartete Felder in $data:
     *   provider, model, use_case, user_id, customer_id,
     *   tokens_input, tokens_output, ttft_ms, total_ms, success (bool), error_message
     *
     * Optional 'detail' => [ conversation_id, message_id, system_prompt, user_message,
     *   response_text, rag_chunks (array|string) ] — wird in die separate, rotierende
     *   Tabelle llm_request_detail geschrieben (volle Nachvollziehbarkeit pro Transaktion).
     *
     * @return int|null Die ID der erzeugten llm_request_log-Zeile (oder null bei Fehler).
     */
    public static function record(array $data): ?int
    {
        try {
            $db = Database::getInstance();

            $tokensInput  = (int)($data['tokens_input'] ?? 0);
            $tokensOutput = (int)($data['tokens_output'] ?? 0);
            $ttftMs       = isset($data['ttft_ms']) ? (int)$data['ttft_ms'] : null;
            $totalMs      = isset($data['total_ms']) ? (int)$data['total_ms'] : null;

            // tokens/s = Output-Tokens / Generierungszeit (Total minus TTFT)
            $tps = $data['tokens_per_second'] ?? null;
            if ($tps === null && $tokensOutput > 0 && $totalMs !== null) {
                $genMs = $ttftMs !== null ? max(1, $totalMs - $ttftMs) : max(1, $totalMs);
                $tps = round($tokensOutput / ($genMs / 1000), 2);
            }

            $logId = $db->insert('llm_request_log', [
                'provider'          => (string)($data['provider'] ?? ''),
                'model'             => (string)($data['model'] ?? ''),
                'use_case'          => $data['use_case'] ?? null,
                'user_id'           => $data['user_id'] ?? null,
                'customer_id'       => $data['customer_id'] ?? null,
                'tokens_input'      => $tokensInput,
                'tokens_output'     => $tokensOutput,
                'tokens_total'      => $tokensInput + $tokensOutput,
                'ttft_ms'           => $ttftMs,
                'total_ms'          => $totalMs,
                'tokens_per_second' => $tps,
                'success'           => !empty($data['success']) ? 1 : 0,
                'error_message'     => $data['error_message'] ?? null,
            ]);

            if (!empty($data['detail']) && is_array($data['detail'])) {
                self::recordDetail($db, $logId, $data['detail']);
            }

            return $logId;
        } catch (\Throwable $e) {
            // Logging-Fehler nie nach aussen geben
            error_log('LlmRequestLog Fehler: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Detail-Zeile (System-Prompt, User-Prompt, RAG-Chunks, Antwort) schreiben.
     * Eigener try/catch — fehlt die Detail-Tabelle (Migration noch nicht gelaufen),
     * darf das den Haupt-Log und den Chat nie kippen.
     */
    private static function recordDetail(Database $db, int $logId, array $detail): void
    {
        try {
            $chunks = $detail['rag_chunks'] ?? null;
            if (is_array($chunks)) {
                $chunks = json_encode($chunks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $rerankMeta = $detail['rerank_meta'] ?? null;
            if (is_array($rerankMeta)) {
                $rerankMeta = json_encode($rerankMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $db->insert('llm_request_detail', [
                'log_id'          => $logId,
                'conversation_id' => $detail['conversation_id'] ?? null,
                'message_id'      => $detail['message_id'] ?? null,
                'system_prompt'   => $detail['system_prompt'] ?? null,
                'user_message'    => $detail['user_message'] ?? null,
                'response_text'   => $detail['response_text'] ?? null,
                'rag_chunks'      => $chunks ?: null,
                'rerank_meta'     => $rerankMeta ?: null,
            ]);

            // Opportunistische Rotation: ~1 % der Schreibvorgaenge raeumen alte Details ab.
            // Kein Cron noetig — die Detail-Daten loeschen sich so von selbst nach 90 Tagen.
            if (random_int(1, 100) === 1) {
                $db->execute(
                    "DELETE FROM llm_request_detail WHERE created_at < (NOW() - INTERVAL ? DAY)",
                    [self::DETAIL_RETENTION_DAYS]
                );
            }
        } catch (\Throwable $e) {
            error_log('LlmRequestLog Detail-Fehler: ' . $e->getMessage());
        }
    }
}
