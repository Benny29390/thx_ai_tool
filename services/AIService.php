<?php
/**
 * AI Service - OpenAI und Anthropic Integration
 */

namespace Services;

class AIService
{
    private string $apiKey;
    private string $provider;
    private string $model;
    private int $maxTokens;
    private ?int $timeoutOverride = null;
    private ?string $openaiBaseUrl = null; // Custom Base-URL fuer OpenAI-kompatible Server (lokal)
    private array $extraBody = [];          // Zusaetzliche Body-Parameter (z.B. Ollama options.num_ctx)

    // API URLs
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const GOOGLE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(string $apiKey, string $provider = 'openai')
    {
        $this->apiKey = $apiKey;
        $this->provider = $provider;
        $this->model = $provider === 'anthropic' ? 'claude-3-sonnet-20240229' : 'gpt-4';
        $this->maxTokens = DEFAULT_MAX_TOKENS;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;

        // Provider 'local' explizit gesetzt -> NICHT per Namen ueberschreiben
        // (sonst landen 'gpt-oss:20b'/'llama3.1:8b' faelschlich bei OpenAI)
        if ($this->provider === 'local') {
            return;
        }

        // Provider anhand des Modells bestimmen
        if (strpos($model, 'claude') !== false) {
            $this->provider = 'anthropic';
        } elseif (strpos($model, 'gemini') !== false) {
            $this->provider = 'google';
        } else {
            $this->provider = 'openai';
        }
    }

    /** Aktuell gesetztes Modell — fuer ehrliche Protokollierung (welches Modell lief wirklich). */
    public function getModel(): string
    {
        return $this->model;
    }

    /** Provider dieses Service-Objekts ('local'|'anthropic'|'openai'|'google'). */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Lokalen OpenAI-kompatiblen Server konfigurieren.
     * Base-URL ohne abschliessendes /chat/completions (z.B. https://ki.thoxan.com/llm/v1).
     */
    public function configureLocal(string $baseUrl): void
    {
        $this->provider = 'local';
        $this->openaiBaseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Zusaetzliche Body-Parameter setzen (werden in die OpenAI-kompatible Payload gemischt).
     */
    public function setExtraBody(array $extra): void
    {
        $this->extraBody = $extra;
    }

    /**
     * Ollama-Context-Window pro Request setzen (nur lokaler Server).
     * Qwen 2.5 kann bis 128k — ACHTUNG: hoeherer num_ctx = mehr VRAM = langsamer.
     * Nur dort einsetzen, wo wirklich noetig (z.B. lange Dokumente zusammenfassen).
     */
    public function setNumCtx(int $numCtx): void
    {
        $this->extraBody['options'] = array_merge(
            $this->extraBody['options'] ?? [],
            ['num_ctx' => $numCtx]
        );
    }

    /**
     * Effektive Chat-Completions-URL fuer den OpenAI-kompatiblen Pfad.
     */
    private function openAiChatUrl(): string
    {
        if ($this->openaiBaseUrl !== null) {
            return $this->openaiBaseUrl . '/chat/completions';
        }
        return self::OPENAI_URL;
    }

    public function setMaxTokens(int $tokens): void
    {
        $this->maxTokens = $tokens;
    }

    public function setTimeout(int $seconds): void
    {
        $this->timeoutOverride = $seconds;
    }

    /**
     * Chat-Anfrage senden
     */
    public function chat(array $messages, string $systemPrompt = ''): array
    {
        if ($this->provider === 'anthropic') {
            return $this->chatAnthropic($messages, $systemPrompt);
        }
        if ($this->provider === 'google') {
            return $this->chatGoogle($messages, $systemPrompt);
        }
        return $this->chatOpenAI($messages, $systemPrompt);
    }

    /**
     * Chat-Anfrage mit Streaming senden
     * $onToken(string $text) wird fuer jedes Token aufgerufen
     */
    public function chatStream(array $messages, string $systemPrompt, callable $onToken): array
    {
        if ($this->provider === 'anthropic') {
            return $this->chatStreamAnthropic($messages, $systemPrompt, $onToken);
        }
        if ($this->provider === 'google') {
            return $this->chatStreamGoogle($messages, $systemPrompt, $onToken);
        }
        return $this->chatStreamOpenAI($messages, $systemPrompt, $onToken);
    }

    /**
     * Prueft ob das Modell die neue API (max_completion_tokens) benoetigt
     */
    private function usesNewOpenAIApi(): bool
    {
        // Neuere Modelle (GPT-5, o1, o3, etc.) benoetigen max_completion_tokens
        $newModels = ['gpt-5', 'gpt-4.5', 'o1', 'o3'];
        foreach ($newModels as $prefix) {
            if (strpos($this->model, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prueft ob das Modell ein Reasoning-Modell ist (braucht mehr Tokens)
     */
    private function isReasoningModel(): bool
    {
        $reasoningModels = ['gpt-5-nano', 'gpt-5-mini', 'o1', 'o3'];
        foreach ($reasoningModels as $prefix) {
            if (strpos($this->model, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hartes Output-Limit (max_tokens) je Modell — das, was die API maximal akzeptiert.
     *
     * Schutzschicht: In `ai_models.max_tokens` stehen historisch teils Kontextfenster
     * (z.B. 200000 bei Claude) statt echter Output-Limits. Wuerde man das ungeprueft an
     * die API schicken, gaebe es einen 400er und der Chat waere tot. Deshalb wird der
     * konfigurierte Wert immer gegen diese Obergrenze geklemmt.
     */
    public static function outputTokenCap(string $provider, string $model): int
    {
        $m = strtolower($model);

        if ($provider === 'anthropic') {
            foreach (['claude-opus-4-8' => 128000, 'claude-opus-4-7' => 128000, 'claude-opus-4-6' => 128000,
                      'claude-sonnet-4-6' => 128000, 'claude-fable-5' => 128000, 'claude-sonnet-5' => 128000,
                      'claude-sonnet-4-5' => 64000, 'claude-haiku-4-5' => 64000] as $prefix => $cap) {
                if (strpos($m, $prefix) === 0) return $cap;
            }
            return 4096; // Claude 3 und Unbekanntes: konservativ
        }

        if ($provider === 'openai') {
            if (strpos($m, 'gpt-5') === 0) return 128000;
            if (strpos($m, 'gpt-4-turbo') === 0) return 4096;
            if (strpos($m, 'gpt-4') === 0) return 8192;
            return 4096; // gpt-3.5 und Unbekanntes
        }

        if ($provider === 'google') {
            return 65536;
        }

        return 4096; // local: Server-Context 16384, Completion gedeckelt
    }

    /**
     * Gibt das effektive Token-Limit zurueck (hoeher fuer Reasoning-Modelle)
     */
    private function getEffectiveMaxTokens(): int
    {
        if ($this->isReasoningModel()) {
            // Reasoning-Modelle brauchen viel mehr Tokens (Reasoning + Output)
            return max($this->maxTokens * 4, 16000);
        }
        return $this->maxTokens;
    }

    /**
     * Gibt das effektive Timeout zurueck (hoeher fuer Reasoning-Modelle)
     */
    private function getEffectiveTimeout(): int
    {
        if ($this->timeoutOverride !== null) {
            return $this->timeoutOverride;
        }
        if ($this->isReasoningModel()) {
            return 300;
        }
        // Lokaler Server: erste Anfrage laedt das Modell in VRAM (~10s) -> 60s Puffer
        if ($this->provider === 'local') {
            return 60;
        }
        return 120;
    }

    /**
     * OpenAI Chat
     */
    private function chatOpenAI(array $messages, string $systemPrompt): array
    {
        $allMessages = [];

        if ($systemPrompt) {
            $allMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $allMessages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => $allMessages
        ];

        // Neuere Modelle (GPT-5, o1, o3) unterstuetzen keine Temperature-Einstellung
        // und benoetigen max_completion_tokens statt max_tokens
        if ($this->usesNewOpenAIApi()) {
            $payload['max_completion_tokens'] = $this->getEffectiveMaxTokens();
            // Temperature nicht setzen - nur default (1) wird unterstuetzt
        } else {
            $payload['max_tokens'] = $this->maxTokens;
            $payload['temperature'] = 0.7;
        }

        if (!empty($this->extraBody)) {
            $payload = array_merge($payload, $this->extraBody);
        }

        $response = $this->request($this->openAiChatUrl(), $payload, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ], $this->getEffectiveTimeout());

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Ungueltige OpenAI-Antwort');
        }

        return [
            'content' => $response['choices'][0]['message']['content'],
            'tokens' => [
                'input' => $response['usage']['prompt_tokens'] ?? 0,
                'output' => $response['usage']['completion_tokens'] ?? 0,
                'total' => $response['usage']['total_tokens'] ?? 0
            ],
            'model' => $response['model'] ?? $this->model
        ];
    }

    /**
     * Anthropic Chat
     */
    private function chatAnthropic(array $messages, string $systemPrompt): array
    {
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $formattedMessages
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = $this->request(self::ANTHROPIC_URL, $payload, [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ], $this->getEffectiveTimeout());

        if (!isset($response['content'][0]['text'])) {
            throw new \Exception('Ungueltige Anthropic-Antwort: ' . json_encode($response));
        }

        return [
            'content' => $response['content'][0]['text'],
            'tokens' => [
                'input' => $response['usage']['input_tokens'] ?? 0,
                'output' => $response['usage']['output_tokens'] ?? 0,
                'total' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0)
            ],
            'model' => $response['model'] ?? $this->model
        ];
    }

    /**
     * Anthropic Tool-Use — Multi-Turn-Loop bis "done" oder maxIterations
     *
     * @param string $systemPrompt
     * @param array $tools  Tool-Definitionen (Anthropic-Format)
     * @param callable $toolDispatcher  fn(string $toolName, array $args): array  → tool_result content (array für Multi-Block)
     * @param callable|null $onEvent  fn(string $type, array $data) — für SSE-Live-Updates
     * @param int $maxIterations
     * @return array {summary, iterations, tokens_input, tokens_output}
     */
    public function chatWithTools(
        string $systemPrompt,
        $userPromptOrMessages,
        array $tools,
        callable $toolDispatcher,
        ?callable $onEvent = null,
        int $maxIterations = 30,
        ?callable $onMessage = null,
        ?callable $shouldStop = null,
        ?callable $onUsage = null
    ): array {
        if ($this->provider !== 'anthropic') {
            throw new \Exception('chatWithTools nur für Anthropic verfügbar');
        }

        // Backward-compat: String → wrap; Array = vollständige Message-History
        if (is_string($userPromptOrMessages)) {
            $messages = [['role' => 'user', 'content' => $userPromptOrMessages]];
        } else {
            $messages = $userPromptOrMessages;
        }

        $tokensIn = 0;
        $tokensOut = 0;
        $summary = '';
        $isDone = false;

        for ($iter = 0; $iter < $maxIterations; $iter++) {
            if ($shouldStop && $shouldStop()) {
                return [
                    'summary' => '',
                    'iterations' => $iter,
                    'tokens_input' => $tokensIn,
                    'tokens_output' => $tokensOut,
                    'done' => false,
                    'stopped' => true,
                ];
            }

            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'tools' => $tools,
                'messages' => $messages,
            ];

            $response = $this->request(self::ANTHROPIC_URL, $payload, [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ], $this->getEffectiveTimeout());

            if (isset($response['error'])) {
                throw new \Exception('Anthropic-Error: ' . ($response['error']['message'] ?? json_encode($response['error'])));
            }
            if (!isset($response['content']) || !is_array($response['content'])) {
                throw new \Exception('Unerwartete Anthropic-Antwort: ' . substr(json_encode($response), 0, 500));
            }

            $tokensIn  += (int) ($response['usage']['input_tokens']  ?? 0);
            $tokensOut += (int) ($response['usage']['output_tokens'] ?? 0);

            // Live-Usage-Callback — der Caller kann hier ein Cost-Cap durchsetzen,
            // indem er $tokens in DB schreibt und shouldStop dort hineinguckt.
            if ($onUsage) $onUsage($tokensIn, $tokensOut);

            // Assistant-Message zurück in den Verlauf
            $assistantMsg = ['role' => 'assistant', 'content' => $response['content']];
            $messages[] = $assistantMsg;
            if ($onMessage) $onMessage($assistantMsg);

            // Verarbeite content-Blöcke: text → SSE, tool_use → ausführen
            $toolResults = [];
            $askUserPending = null;   // bei ask_user-Tool: Frage zwischenspeichern
            foreach ($response['content'] as $block) {
                $blockType = $block['type'] ?? '';
                if ($blockType === 'text') {
                    $text = trim((string) ($block['text'] ?? ''));
                    if ($text !== '' && $onEvent) {
                        $onEvent('assistant_message', ['text' => $text]);
                    }
                } elseif ($blockType === 'tool_use') {
                    $toolName = $block['name'] ?? '';
                    $toolUseId = $block['id'] ?? '';
                    $toolInput = $block['input'] ?? [];

                    if ($onEvent) $onEvent('tool_call', ['tool' => $toolName, 'args' => $toolInput]);

                    try {
                        $result = $toolDispatcher($toolName, is_array($toolInput) ? $toolInput : []);
                        $isError = !empty($result['__error']);
                        $contentText = $result['__text'] ?? (is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE));
                        if ($onEvent) $onEvent('tool_result', ['tool' => $toolName, 'ok' => !$isError, 'snippet' => mb_substr((string)$contentText, 0, 600)]);

                        // 'done'-Tool → Summary einfangen und Loop beenden
                        if ($toolName === 'done') {
                            $summary = (string) ($toolInput['summary'] ?? '');
                            $isDone = true;
                        }
                        // 'ask_user'-Tool → Frage zwischenspeichern, Loop danach pausieren
                        if (!empty($result['__ask_user']) || $toolName === 'ask_user') {
                            $askUserPending = (string) ($result['__ask_user_question'] ?? $toolInput['question'] ?? '');
                        }

                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolUseId,
                            'content' => (string) $contentText,
                            'is_error' => $isError,
                        ];
                    } catch (\Throwable $e) {
                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolUseId,
                            'content' => 'EXCEPTION: ' . $e->getMessage(),
                            'is_error' => true,
                        ];
                        if ($onEvent) $onEvent('tool_result', ['tool' => $toolName, 'ok' => false, 'snippet' => $e->getMessage()]);
                    }
                }
            }

            // Wenn keine Tool-Calls kamen → Modell will nicht weiter arbeiten, Loop verlassen
            if (empty($toolResults)) break;

            // Tool-Results in jedem Fall persistieren — auch wenn done — sonst bleibt
            // ein Assistant-Message mit tool_use-Block ohne danebenliegenden tool_result
            // in der DB, was Folge-Turns kaputt macht (Anthropic erwartet Paare).
            $toolResultMsg = ['role' => 'user', 'content' => $toolResults];
            $messages[] = $toolResultMsg;
            if ($onMessage) $onMessage($toolResultMsg);

            if ($isDone) break;

            // Wenn ask_user aufgerufen wurde → Loop pausieren (Frage wartet auf User-Antwort)
            if ($askUserPending !== null) {
                return [
                    'summary' => '',
                    'iterations' => $iter + 1,
                    'tokens_input' => $tokensIn,
                    'tokens_output' => $tokensOut,
                    'done' => false,
                    'awaiting_user' => true,
                    'question' => $askUserPending,
                ];
            }

            $stopReason = $response['stop_reason'] ?? '';
            if ($stopReason === 'end_turn' && empty($toolResults)) break;
        }

        return [
            'summary' => $summary,
            'iterations' => $iter + 1,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'done' => $isDone,
        ];
    }

    /**
     * OpenAI Streaming Chat
     */
    private function chatStreamOpenAI(array $messages, string $systemPrompt, callable $onToken): array
    {
        $allMessages = [];
        if ($systemPrompt) {
            $allMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($messages as $msg) {
            $allMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $payload = [
            'model' => $this->model,
            'messages' => $allMessages,
            'stream' => true,
            'stream_options' => ['include_usage' => true]
        ];

        if ($this->usesNewOpenAIApi()) {
            $payload['max_completion_tokens'] = $this->getEffectiveMaxTokens();
        } else {
            $payload['max_tokens'] = $this->maxTokens;
            $payload['temperature'] = 0.7;
        }

        if (!empty($this->extraBody)) {
            $payload = array_merge($payload, $this->extraBody);
        }

        $fullContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $sseBuffer = '';
        $stopReason = '';

        $ch = curl_init($this->openAiChatUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => $this->getEffectiveTimeout(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$sseBuffer, &$fullContent, &$tokensInput, &$tokensOutput, &$stopReason, $onToken) {
                $sseBuffer .= $data;

                while (($nlPos = strpos($sseBuffer, "\n")) !== false) {
                    $line = substr($sseBuffer, 0, $nlPos);
                    $sseBuffer = substr($sseBuffer, $nlPos + 1);
                    $line = trim($line);

                    if ($line === '' || strpos($line, 'data: ') !== 0) continue;
                    $json = substr($line, 6);
                    if ($json === '[DONE]') continue;

                    $parsed = json_decode($json, true);
                    if (!$parsed) continue;

                    if (isset($parsed['usage'])) {
                        $tokensInput = $parsed['usage']['prompt_tokens'] ?? $tokensInput;
                        $tokensOutput = $parsed['usage']['completion_tokens'] ?? $tokensOutput;
                    }

                    // finish_reason='length' => Antwort wurde am Token-Limit abgeschnitten
                    $fr = $parsed['choices'][0]['finish_reason'] ?? null;
                    if (!empty($fr)) {
                        $stopReason = $fr;
                    }

                    $delta = $parsed['choices'][0]['delta']['content'] ?? '';
                    if ($delta !== '') {
                        $fullContent .= $delta;
                        $onToken($delta);
                    }
                }

                return strlen($data);
            }
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL-Fehler: ' . $error);
        }
        if ($httpCode >= 400) {
            $errDetail = $fullContent ?: $sseBuffer;
            throw new \Exception('API-Fehler (' . $httpCode . '): ' . mb_substr($errDetail, 0, 500));
        }
        if (empty($fullContent)) {
            throw new \Exception('Leere Antwort vom Modell (' . $this->model . ')');
        }

        return [
            'content' => $fullContent,
            'tokens' => [
                'input' => $tokensInput,
                'output' => $tokensOutput,
                'total' => $tokensInput + $tokensOutput
            ],
            'model' => $this->model,
            'stop_reason' => $stopReason,
            'truncated' => $stopReason === 'length',
        ];
    }

    /**
     * Anthropic Streaming Chat
     */
    private function chatStreamAnthropic(array $messages, string $systemPrompt, callable $onToken): array
    {
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $formattedMessages,
            'stream' => true
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $fullContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $sseBuffer = '';
        $stopReason = '';

        $ch = curl_init(self::ANTHROPIC_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => $this->getEffectiveTimeout(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$sseBuffer, &$fullContent, &$tokensInput, &$tokensOutput, &$stopReason, $onToken) {
                $sseBuffer .= $data;

                while (($nlPos = strpos($sseBuffer, "\n")) !== false) {
                    $line = substr($sseBuffer, 0, $nlPos);
                    $sseBuffer = substr($sseBuffer, $nlPos + 1);
                    $line = trim($line);

                    if ($line === '' || strpos($line, 'data: ') !== 0) continue;
                    $json = substr($line, 6);

                    $parsed = json_decode($json, true);
                    if (!$parsed) continue;

                    $type = $parsed['type'] ?? '';

                    if ($type === 'message_start') {
                        $tokensInput = $parsed['message']['usage']['input_tokens'] ?? 0;
                    } elseif ($type === 'content_block_delta') {
                        $text = $parsed['delta']['text'] ?? '';
                        if ($text !== '') {
                            $fullContent .= $text;
                            $onToken($text);
                        }
                    } elseif ($type === 'message_delta') {
                        $tokensOutput = $parsed['usage']['output_tokens'] ?? 0;
                        // stop_reason='max_tokens' => Antwort wurde am Limit abgeschnitten
                        $stopReason = $parsed['delta']['stop_reason'] ?? $stopReason;
                    }
                }

                return strlen($data);
            }
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL-Fehler: ' . $error);
        }
        if ($httpCode >= 400) {
            $errDetail = $fullContent ?: $sseBuffer;
            throw new \Exception('API-Fehler (' . $httpCode . '): ' . mb_substr($errDetail, 0, 500));
        }

        return [
            'content' => $fullContent,
            'tokens' => [
                'input' => $tokensInput,
                'output' => $tokensOutput,
                'total' => $tokensInput + $tokensOutput
            ],
            'model' => $this->model,
            'stop_reason' => $stopReason,
            'truncated' => $stopReason === 'max_tokens',
        ];
    }

    /**
     * Google Gemini Chat
     */
    private function chatGoogle(array $messages, string $systemPrompt): array
    {
        $contents = $this->buildGoogleContents($messages);
        $payload = ['contents' => $contents];

        if ($systemPrompt) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $payload['generationConfig'] = [
            'maxOutputTokens' => $this->maxTokens,
            'temperature' => 0.7,
        ];

        $url = self::GOOGLE_URL . $this->model . ':generateContent?key=' . $this->apiKey;

        $response = $this->request($url, $payload, [
            'Content-Type: application/json'
        ], $this->getEffectiveTimeout());

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            throw new \Exception('Ungueltige Google-Antwort: ' . json_encode($response));
        }

        return [
            'content' => $text,
            'tokens' => [
                'input' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total' => $response['usageMetadata']['totalTokenCount'] ?? 0,
            ],
            'model' => $this->model
        ];
    }

    /**
     * Google Gemini Streaming Chat
     */
    private function chatStreamGoogle(array $messages, string $systemPrompt, callable $onToken): array
    {
        $contents = $this->buildGoogleContents($messages);
        $payload = ['contents' => $contents];

        if ($systemPrompt) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $payload['generationConfig'] = [
            'maxOutputTokens' => $this->maxTokens,
            'temperature' => 0.7,
        ];

        $url = self::GOOGLE_URL . $this->model . ':streamGenerateContent?alt=sse&key=' . $this->apiKey;

        $fullContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $sseBuffer = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->getEffectiveTimeout(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$sseBuffer, &$fullContent, &$tokensInput, &$tokensOutput, $onToken) {
                $sseBuffer .= $data;

                while (($nlPos = strpos($sseBuffer, "\n")) !== false) {
                    $line = substr($sseBuffer, 0, $nlPos);
                    $sseBuffer = substr($sseBuffer, $nlPos + 1);
                    $line = trim($line);

                    if ($line === '' || strpos($line, 'data: ') !== 0) continue;
                    $json = substr($line, 6);

                    $parsed = json_decode($json, true);
                    if (!$parsed) continue;

                    // Token-Text extrahieren
                    $text = $parsed['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($text !== '') {
                        $fullContent .= $text;
                        $onToken($text);
                    }

                    // Usage-Daten (kommen im letzten Chunk)
                    if (isset($parsed['usageMetadata'])) {
                        $tokensInput = $parsed['usageMetadata']['promptTokenCount'] ?? $tokensInput;
                        $tokensOutput = $parsed['usageMetadata']['candidatesTokenCount'] ?? $tokensOutput;
                    }
                }

                return strlen($data);
            }
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL-Fehler: ' . $error);
        }
        if ($httpCode >= 400) {
            $errMsg = 'Streaming fehlgeschlagen';
            $errParsed = json_decode($sseBuffer, true);
            if (isset($errParsed['error']['message'])) {
                $errMsg = $errParsed['error']['message'];
            }
            throw new \Exception('Google API (' . $httpCode . '): ' . $errMsg);
        }

        return [
            'content' => $fullContent,
            'tokens' => [
                'input' => $tokensInput,
                'output' => $tokensOutput,
                'total' => $tokensInput + $tokensOutput
            ],
            'model' => $this->model
        ];
    }

    /**
     * Messages in Google-Format konvertieren
     */
    private function buildGoogleContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            // System-Messages als User-Message behandeln (systemInstruction ist separat)
            if ($msg['role'] === 'system') $role = 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }
        return $contents;
    }

    /**
     * Embeddings erstellen (nur OpenAI)
     */
    public function createEmbedding(string $text): array
    {
        $payload = [
            'model' => DEFAULT_EMBEDDING_MODEL,
            'input' => $text
        ];

        $response = $this->request('https://api.openai.com/v1/embeddings', $payload, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        if (!isset($response['data'][0]['embedding'])) {
            throw new \Exception('Ungueltige Embedding-Antwort');
        }

        return [
            'embedding' => $response['data'][0]['embedding'],
            'tokens' => $response['usage']['total_tokens'] ?? 0
        ];
    }

    /**
     * HTTP-Anfrage senden
     */
    private function request(string $url, array $payload, array $headers, int $timeout = 120): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL-Fehler: ' . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $data['error']['message'] ?? $data['error'] ?? 'Unbekannter Fehler';
            throw new \Exception('API-Fehler (' . $httpCode . '): ' . $errorMsg);
        }

        return $data;
    }

    /**
     * Woerter zaehlen
     */
    public static function countWords(string $text): int
    {
        if (empty($text)) return 0;
        return count(preg_split('/\s+/', trim($text)));
    }

    /**
     * API-Key testen
     */
    public function testConnection(): array
    {
        try {
            if ($this->provider === 'anthropic') {
                $this->chat([['role' => 'user', 'content' => 'Hi']], '');
            } else {
                $this->chat([['role' => 'user', 'content' => 'Hi']], '');
            }
            return ['success' => true, 'message' => 'Verbindung erfolgreich'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
