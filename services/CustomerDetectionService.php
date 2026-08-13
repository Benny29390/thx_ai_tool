<?php
/**
 * Customer Detection Service
 *
 * Erkennt aus einer User-Nachricht, welcher Kunde gemeint ist.
 * Pre-Filter via Regex auf Name + Slug + Abbreviation.
 * Bei mehrdeutigem Match: gpt-4o-mini Klassifikation.
 */

namespace Services;

class CustomerDetectionService
{
    private \Core\Database $db;
    private ?AIService $ai;

    public function __construct(\Core\Database $db, ?AIService $ai = null)
    {
        $this->db = $db;
        $this->ai = $ai;
    }

    /**
     * @return array|null ['customer_id' => int, 'customer_name' => string, 'confidence' => float, 'method' => 'regex'|'llm']
     */
    public function detectCustomer(string $userMessage, ?array $customers = null): ?array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') return null;

        if ($customers === null) {
            $customers = $this->db->query(
                "SELECT id, name, slug, abbreviation FROM customers WHERE is_active = 1"
            ) ?: [];
        }
        if (empty($customers)) return null;

        // Pre-Filter: Regex-Match
        $matches = [];
        $msgLower = mb_strtolower($userMessage);
        foreach ($customers as $c) {
            $needles = array_filter([
                $c['name'] ?? null,
                $c['slug'] ?? null,
                $c['abbreviation'] ?? null,
            ]);
            foreach ($needles as $needle) {
                $needleLower = mb_strtolower(trim($needle));
                if ($needleLower === '' || mb_strlen($needleLower) < 2) continue;
                // Wortgrenze fuer Praezision
                $pattern = '/\b' . preg_quote($needleLower, '/') . '\b/u';
                if (preg_match($pattern, $msgLower)) {
                    $matches[$c['id']] = $c;
                    break;
                }
            }
        }

        if (count($matches) === 1) {
            $c = reset($matches);
            return [
                'customer_id' => (int) $c['id'],
                'customer_name' => $c['name'],
                'confidence' => 0.95,
                'method' => 'regex',
            ];
        }

        if (count($matches) === 0 || $this->ai === null) {
            return null;
        }

        // Mehrdeutig oder unklar: LLM-Klassifikation
        return $this->classifyWithLLM($userMessage, count($matches) > 1 ? array_values($matches) : $customers);
    }

    private function classifyWithLLM(string $message, array $candidates): ?array
    {
        $list = [];
        foreach ($candidates as $c) {
            $list[] = sprintf(
                '{"id": %d, "name": "%s", "slug": "%s", "abbreviation": "%s"}',
                (int) $c['id'],
                addslashes($c['name'] ?? ''),
                addslashes($c['slug'] ?? ''),
                addslashes($c['abbreviation'] ?? '')
            );
        }
        $candidateJson = "[\n  " . implode(",\n  ", $list) . "\n]";

        $systemPrompt = \Services\SystemPromptService::get('customer_detection');

        $userPrompt = <<<P
KUNDEN-LISTE:
{$candidateJson}

NACHRICHT DES NUTZERS:
{$message}

Welcher Kunde ist in der Nachricht gemeint? Wenn unsicher → null.

Antworte NUR mit JSON nach diesem Schema:
{"customer_id": <id_oder_null>, "confidence": <0.0_bis_1.0>}
P;

        try {
            $this->ai->setModel('gpt-4o-mini');
            $this->ai->setTimeout(15);
            $resp = $this->ai->chat(
                [['role' => 'user', 'content' => $userPrompt]],
                $systemPrompt
            );
            $content = $resp['content'] ?? '';
            if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
                $json = json_decode($m[0], true);
                if (is_array($json) && !empty($json['customer_id'])) {
                    $cid = (int) $json['customer_id'];
                    $confidence = (float) ($json['confidence'] ?? 0.5);
                    if ($confidence < 0.5) return null;

                    $found = null;
                    foreach ($candidates as $c) {
                        if ((int) $c['id'] === $cid) { $found = $c; break; }
                    }
                    if ($found) {
                        return [
                            'customer_id' => $cid,
                            'customer_name' => $found['name'],
                            'confidence' => $confidence,
                            'method' => 'llm',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('CustomerDetection LLM error: ' . $e->getMessage());
        }
        return null;
    }
}
