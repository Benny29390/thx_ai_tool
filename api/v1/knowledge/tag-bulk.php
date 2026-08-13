<?php

/**
 * Tag-Bulk-Aktionen aus den Rechtsklick-Menues.
 *
 * POST /knowledge/tag-bulk
 * Body: { action: 'rename'|'merge'|'remove', from: 'tag' | ['tag','tag2'], to?: 'neu' }
 *
 * Wirkt auf knowledge_documents.tags (JSON-Array).
 */

use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed', 405);

$db = Database::getInstance();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$action = (string) ($input['action'] ?? '');
$from = $input['from'] ?? null;
$to = trim((string) ($input['to'] ?? ''));

if (!in_array($action, ['rename', 'merge', 'remove'], true)) Response::error('action: rename|merge|remove');
if ($from === null) Response::error('from fehlt');
$fromList = is_array($from) ? array_values(array_filter(array_map('trim', $from), 'strlen')) : [trim((string) $from)];
$fromList = array_map('mb_strtolower', $fromList);
if (empty($fromList)) Response::error('from leer');

if (in_array($action, ['rename', 'merge'], true) && $to === '') Response::error('to fehlt');
$to = mb_strtolower($to);

// Alle Docs holen, die mindestens einen der from-Tags haben
$where = [];
$params = [];
foreach ($fromList as $t) {
    $where[] = "JSON_SEARCH(tags, 'one', ?) IS NOT NULL";
    $params[] = $t;
}
$rows = $db->query(
    "SELECT id, tags FROM knowledge_documents WHERE is_active = 1 AND (" . implode(' OR ', $where) . ")",
    $params
) ?: [];

$touched = 0;
foreach ($rows as $r) {
    $tags = json_decode($r['tags'] ?? '[]', true) ?: [];
    $orig = $tags;
    $tags = array_map(fn($t) => mb_strtolower(trim((string) $t)), $tags);

    if ($action === 'remove') {
        $new = array_values(array_filter($tags, fn($t) => !in_array($t, $fromList, true)));
    } elseif ($action === 'rename' || $action === 'merge') {
        // jedes from-Tag wird zu to; mehrfache from werden zu einem to (Merge)
        $new = [];
        foreach ($tags as $t) $new[] = in_array($t, $fromList, true) ? $to : $t;
        $new = array_values(array_unique($new));
    }
    if ($new === $orig) continue;
    $db->update('knowledge_documents', ['tags' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], 'id = ?', [(int) $r['id']]);
    $touched++;
}

Response::success([
    'action' => $action,
    'from' => $fromList,
    'to' => $to !== '' ? $to : null,
    'docs_touched' => $touched,
], "$touched Docs aktualisiert");
