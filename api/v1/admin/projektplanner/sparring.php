<?php
/**
 * Projektplanner — KI-Sparring pro Plan.
 *
 * GET    /admin/projektplanner/plans/{id}/sparring              Conversation + Nachrichten
 * POST   /admin/projektplanner/plans/{id}/sparring/stream       SSE-Dialog  { message }
 * POST   /admin/projektplanner/plans/{id}/sparring/materialize  Vorschlags-Karten
 * POST   /admin/projektplanner/plans/{id}/sparring/apply        { suggestion } → Zeile
 * POST   /admin/projektplanner/plans/{id}/sparring/rule         { text } → Regel-Vorschlag
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Settings;
use Services\ProjektplannerService;
use Services\PpSparringService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

require_once SERVICES_PATH . '/ProjektplannerService.php';
require_once SERVICES_PATH . '/PpSparringService.php';

$db = Database::getInstance();
$planId = (int) ($_GET['plan_id'] ?? 0);
$sub = $_GET['sub'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$pp = new ProjektplannerService($db);
$svc = new PpSparringService($db);
$plan = $pp->getPlanWithRows($planId);
if (!$plan) Response::error('Plan nicht gefunden', 404);

$allowedCustomerIds = Auth::isAdmin() ? null : array_map(fn($c) => (int) $c['id'], Auth::customers());

// ---------- GET: Conversation + Nachrichten ----------
if ($sub === '' && $method === 'GET') {
    $conv = $svc->getOrCreateConversation($planId, $userId);
    Response::success(['conversation_id' => (int) $conv['id'], 'messages' => $svc->listMessages((int) $conv['id'])]);
}

// ---------- POST materialize ----------
if ($sub === 'materialize' && $method === 'POST') {
    $conv = $svc->getOrCreateConversation($planId, $userId);
    try {
        Response::success($svc->materializeSuggestions((int) $conv['id'], $plan));
    } catch (\Throwable $e) { Response::error($e->getMessage(), 500); }
}

// ---------- POST apply (Vorschlag → Zeile) ----------
if ($sub === 'apply' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $s = $body['suggestion'] ?? [];
    // Entfernen einer BESTEHENDEN Zeile
    if (($s['action'] ?? 'add') === 'remove') {
        $rid = (int) ($s['row_id'] ?? 0);
        $belongs = $db->queryValue("SELECT id FROM pp_plan_rows WHERE id = ? AND plan_id = ?", [$rid, $planId]);
        if (!$belongs) Response::error('Zeile gehört nicht zu diesem Plan', 400);
        $pp->deleteRow($rid);
        Response::success(['removed' => $rid], 'Zeile entfernt');
    }
    $desc = trim((string) ($s['description'] ?? ''));
    if ($desc === '') Response::error('Beschreibung fehlt');
    $sectionName = trim((string) ($s['section'] ?? ''));

    $rows = $pp->getRows($planId);
    // Ziel-Sektion suchen; sonst am Ende neu anlegen.
    $secIdx = -1;
    foreach ($rows as $i => $r) {
        if ($r['row_type'] === 'section' && trim((string) $r['description']) === $sectionName && $sectionName !== '') { $secIdx = $i; break; }
    }
    $team = $s['team'] ?? '';
    if (is_array($team)) $team = implode(', ', $team);
    $itemData = [
        'row_type' => 'item',
        'description' => $desc,
        'timeframe' => trim((string) ($s['timeframe'] ?? '')) ?: null,
        'deadline' => trim((string) ($s['deadline'] ?? '')) ?: null,
        'lead_responsible' => trim((string) ($s['lead'] ?? '')) ?: null,
        'responsible' => trim((string) $team) ?: null,
        'planned_hours' => (float) ($s['planned_hours'] ?? 0),
        'review_flag' => 1,
    ];

    if ($secIdx < 0) {
        // Neue Sektion + Item ans Ende
        if ($sectionName !== '') $pp->addRow($planId, ['row_type' => 'section', 'description' => $sectionName]);
        $newId = $pp->addRow($planId, $itemData);
    } else {
        // Item ans Ende der Ziel-Sektion einfügen (vor der nächsten Sektion).
        $ids = array_map(fn($r) => (int) $r['id'], $rows);
        $insertAt = count($ids);
        for ($j = $secIdx + 1; $j < count($rows); $j++) { if ($rows[$j]['row_type'] === 'section') { $insertAt = $j; break; } }
        $newId = $pp->addRow($planId, $itemData); // hängt zunächst ans Ende
        $order = [];
        for ($k = 0; $k <= count($ids); $k++) { if ($k === $insertAt) $order[] = $newId; if ($k < count($ids)) $order[] = $ids[$k]; }
        $pp->reorderRows($planId, $order);
    }
    Response::success(['row_id' => $newId], 'In den Plan übernommen');
}

// ---------- POST rule (Lernschleife) ----------
if ($sub === 'rule' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $text = trim((string) ($body['text'] ?? ''));
    if ($text === '') Response::error('Regeltext fehlt');
    require_once SERVICES_PATH . '/PpAiRulesService.php';
    $rid = (new \Services\PpAiRulesService($db))->add((int) ($plan['customer_id'] ?? 0) ?: null, $text, 'vorschlag', 'feedback', $userId);
    Response::success(['rule_id' => $rid], 'Als Regel-Vorschlag gespeichert');
}

// ---------- POST stream (SSE) ----------
if ($sub === 'stream' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $message = trim((string) ($body['message'] ?? ''));
    if ($message === '') Response::error('Nachricht fehlt');

    $key = (string) Settings::get('anthropic_api_key');
    if ($key === '') Response::error('Anthropic API-Key nicht konfiguriert', 500);

    $conv = $svc->getOrCreateConversation($planId, $userId);
    $convId = (int) $conv['id'];
    $svc->addMessage($convId, 'user', $message);

    // SSE-Setup (Muster aus chat-stream.php)
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: identity');
    if (function_exists('apache_setenv')) apache_setenv('no-gzip', '1');
    ignore_user_abort(true);
    set_time_limit(300);
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    ob_implicit_flush(true);
    $send = function (string $type, array $data = []) { $data['type'] = $type; echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n"; @flush(); };
    echo ': ' . str_repeat(' ', 2048) . "\n\n";
    echo ": stream-ready\n\n";
    @flush();

    try {
        $systemPrompt = $svc->buildSystemPrompt($plan);
        // Impulse aus anderen Projekten
        $imp = $svc->retrieveImpulses($message, isset($plan['knowledge_doc_id']) ? (int) $plan['knowledge_doc_id'] : null, $allowedCustomerIds);
        if ($imp['block'] !== '') {
            $systemPrompt .= "\n\n" . $imp['block'];
            $send('sources', ['sources' => $imp['sources']]);
        }
        // Verlauf als Anthropic-Messages
        $messages = [];
        foreach ($svc->listMessages($convId) as $m) {
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($key, 'anthropic');
        $ai->setModel('claude-opus-4-7');
        $result = $ai->chatStream($messages, $systemPrompt, function ($tok) use ($send) { $send('token', ['content' => $tok]); });

        $content = (string) ($result['content'] ?? '');
        $tin = (int) ($result['tokens']['input'] ?? 0);
        $tout = (int) ($result['tokens']['output'] ?? 0);
        $svc->addMessage($convId, 'assistant', $content, 'claude-opus-4-7', $tin, $tout);
        $send('done', ['tokens_input' => $tin, 'tokens_output' => $tout]);
    } catch (\Throwable $e) {
        $send('error', ['message' => $e->getMessage()]);
    }
    exit;
}

Response::error('Nicht unterstützte Anfrage', 405);
