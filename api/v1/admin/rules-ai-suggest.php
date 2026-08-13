<?php
/**
 * POST /admin/rules/ai-suggest
 *   Body: { text: string, max: int = 5 }
 *
 * Aus User-Gestammel (freier Text) generiert die KI 1..N strukturierte Regel-
 * Vorschlaege im Format des rules-Schemas. Echte Umlaute (ä ö ü ß) werden
 * erzwungen.
 *
 * Response:
 * {
 *   suggestions: [
 *     { name, rule_content, description, rule_type, category_id, category_name }
 *   ]
 * }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Settings;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$text = trim((string)($payload['text'] ?? ''));
$max = max(1, min(10, (int)($payload['max'] ?? 5)));
if (mb_strlen($text) < 8) Response::error('Bitte mindestens 8 Zeichen Text');

$apiKey = Settings::get('anthropic_api_key');
if (empty($apiKey)) Response::error('Anthropic-API-Key nicht konfiguriert');

$db = Database::getInstance();
$categories = $db->query("SELECT id, name FROM rule_categories WHERE is_active = 1 ORDER BY sort_order, name") ?: [];
$catList = array_map(fn($c) => "{$c['id']}={$c['name']}", $categories);

require_once SERVICES_PATH . '/AIService.php';
$ai = new \Services\AIService($apiKey, 'anthropic');
$ai->setModel('claude-haiku-4-5-20251001');
$ai->setMaxTokens(1800);
$ai->setTimeout(30);

$system = "Du erstellst aus einer informellen Beschreibung KI-Schreibregeln in strukturierter Form. "
    . "Antworte AUSSCHLIESSLICH mit JSON in diesem Format:\n"
    . '{"suggestions":[{"name":"...","rule_content":"...","description":"...","rule_type":"...","category_id":null}]}' . "\n\n"
    . "Pro Regel:\n"
    . "- name: kurzer Titel (max 60 Zeichen), aussagekraeftig\n"
    . "- rule_content: KLARE Anweisung an die KI in der Du-Form, 1-3 Saetze (z.B. 'Verwende konsequent die Du-Anrede.')\n"
    . "- description: 1-Zeiler, warum die Regel sinnvoll ist\n"
    . "- rule_type: einer von 'style' | 'format' | 'content' | 'tone' | 'link' | 'seo' | 'language'\n"
    . "- category_id: passende Kategorie aus der Liste oder null. Verfuegbare Kategorien: " . (empty($catList) ? '(keine)' : implode(', ', $catList)) . "\n\n"
    . "WICHTIG: Verwende echte deutsche Umlaute (ä ö ü ß) — NIEMALS ae oe ue ss als Ersatz. Wenn der User mehrere Themen anspricht, "
    . "schlage entsprechend mehrere Regeln vor (max $max). Wenn der Text nur eine Idee enthaelt, eine einzige Regel.";

$user = "Beschreibung:\n" . $text;

try {
    $resp = $ai->chat([['role' => 'user', 'content' => $user]], $system);
    $content = trim($resp['content'] ?? '');
    if (preg_match('/\{.*\}/s', $content, $m)) $content = $m[0];
    $data = json_decode($content, true);
    if (!is_array($data) || empty($data['suggestions'])) throw new \RuntimeException('KI-Antwort unverstaendlich');

    $allowedTypes = ['style', 'format', 'content', 'tone', 'link', 'seo', 'language'];
    $validCatIds = array_column($categories, 'id');
    $catNames = [];
    foreach ($categories as $c) $catNames[(int)$c['id']] = $c['name'];

    $clean = [];
    foreach ((array)$data['suggestions'] as $s) {
        $name = trim((string)($s['name'] ?? ''));
        $content = trim((string)($s['rule_content'] ?? ''));
        if ($name === '' || $content === '') continue;
        $type = in_array($s['rule_type'] ?? '', $allowedTypes, true) ? $s['rule_type'] : 'style';
        $catId = isset($s['category_id']) && $s['category_id'] && in_array((int)$s['category_id'], $validCatIds, true) ? (int)$s['category_id'] : null;
        $clean[] = [
            'name' => mb_substr($name, 0, 250),
            'rule_content' => $content,
            'description' => trim((string)($s['description'] ?? '')),
            'rule_type' => $type,
            'category_id' => $catId,
            'category_name' => $catId ? ($catNames[$catId] ?? null) : null,
        ];
        if (count($clean) >= $max) break;
    }
    if (empty($clean)) Response::error('Keine verwertbaren Vorschlaege erhalten');
    Response::success(['suggestions' => $clean]);
} catch (\Throwable $e) {
    Response::error('KI-Vorschlag fehlgeschlagen: ' . $e->getMessage(), 500);
}
