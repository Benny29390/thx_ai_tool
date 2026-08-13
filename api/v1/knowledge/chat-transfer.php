<?php
/**
 * Knowledge Chat-Transfer — kompletten Chat-Verlauf direkt ins Wissen übernehmen.
 *
 * POST /knowledge/chat-transfer
 * Body: { conversation_id, customer_id (Pflicht), title?, context? }
 *
 * Quelle = "chat", source_ref = "conv:{id}", external_id = "chat:{convId}",
 * created_by = aktueller User. Beim erneuten Transfer wird der bestehende
 * Eintrag aktualisiert (reprocess), nicht dupliziert.
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';

$convId = (int) ($input['conversation_id'] ?? 0);
$customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
$titleHint = trim($input['title'] ?? '');
$contextHint = trim($input['context'] ?? '');

if (!$convId) Response::error('conversation_id erforderlich');
if (!$customerId) Response::error('Bitte einen Kunden wählen — Wissen muss einem Kunden zugeordnet sein.');

$userId = Auth::id();

// Conv + Zugriffsprüfung
$conv = $db->queryOne(
    "SELECT id, title, user_id, customer_id, is_private FROM chat_conversations WHERE id = ?",
    [$convId]
);
if (!$conv) Response::notFound('Chat nicht gefunden');

if (!empty($conv['is_private']) && (int) $conv['user_id'] !== $userId) {
    Response::forbidden('Privater Chat — kein Zugriff');
}

// Customer existiert?
$customer = $db->queryOne("SELECT id, name FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::error('Kunde nicht gefunden');

// Creator-Daten für Quelle
$creator = $db->queryOne("SELECT name, email FROM users WHERE id = ?", [(int) $conv['user_id']]);
$creatorLabel = $creator
    ? trim(($creator['name'] ?? '') . ' <' . ($creator['email'] ?? '') . '>')
    : 'User #' . (int) $conv['user_id'];

$transferredBy = Auth::user()['name'] ?? 'Unbekannt';

// Alle Messages laden (chronologisch)
$messages = $db->query(
    "SELECT role, content, model_used, created_at
     FROM chat_conversation_messages
     WHERE conversation_id = ?
       AND role IN ('user','assistant')
     ORDER BY created_at ASC, id ASC",
    [$convId]
);

if (empty($messages)) Response::error('Chat enthält keine Nachrichten');

// Chat-Verlauf als Wissens-Text aufbauen
$lines = [];
$lines[] = "CHAT-VERLAUF: " . ($conv['title'] ?: 'Ohne Titel');
$lines[] = "Erstellt von: " . $creatorLabel;
$lines[] = "Kunde: " . $customer['name'];
$lines[] = "Übernommen am: " . date('d.m.Y H:i') . " von " . $transferredBy;
if ($contextHint !== '') {
    $lines[] = "";
    $lines[] = "KONTEXT/HINWEIS:";
    $lines[] = $contextHint;
}
$lines[] = "";
$lines[] = "--- VERLAUF ---";

foreach ($messages as $m) {
    $when = !empty($m['created_at']) ? date('d.m.Y H:i', strtotime($m['created_at'])) : '';
    $role = ($m['role'] === 'user')
        ? ($creator['name'] ?? 'User')
        : ('KI' . (!empty($m['model_used']) ? ' (' . $m['model_used'] . ')' : ''));
    $lines[] = "";
    $lines[] = "## " . $role . ($when ? ' — ' . $when : '');
    $lines[] = trim($m['content'] ?? '');
}

$text = implode("\n", $lines);
if (mb_strlen(trim($text)) < 50) Response::error('Chat enthält zu wenig Text');

$services = knowledgeBuildServices($db);

// External-ID — eindeutig pro Chat. Erlaubt späteres Update bei wiederholtem Transfer.
$externalId = 'chat:' . $convId;
$existing = $services['knowledgeService']->findByExternalId('chat', $externalId);

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$extractContext = ['customer_name' => $customer['name']];
if ($contextHint !== '') $extractContext['user_context'] = $contextHint;

// Bei bestehendem Eintrag: reprocess (Update). Sonst: commit (neu).
if ($existing) {
    $newHash = hash('sha256', trim($text));
    if ($existing['content_hash'] === $newHash && (int) $existing['customer_id'] === $customerId) {
        Response::success([
            'document_id' => (int) $existing['id'],
            'title' => $existing['title'],
            'unchanged' => true,
        ], 'Inhalt ist unverändert — bestehender Eintrag wurde nicht überschrieben');
    }

    try {
        $overrides = [
            'title' => $titleHint !== '' ? $titleHint : ($existing['title'] ?: ('Chat — ' . ($conv['title'] ?: 'Ohne Titel'))),
            'customer_id' => $customerId,
            'category' => $existing['category'] ?? 'Chat',
            'tags' => array_unique(array_merge(['chat-transfer'], json_decode($existing['tags'] ?? '[]', true) ?: [])),
        ];
        $services['ingestService']->reprocess(
            (int) $existing['id'],
            $text,
            $overrides,
            $extractContext,
            $userId,
            true
        );
        // source_type / source_ref / external_id sicherstellen
        $db->update('knowledge_documents', [
            'source_type' => 'chat',
            'source_ref' => 'conv:' . $convId,
            'external_id' => $externalId,
            'updated_by' => $userId,
        ], 'id = ?', [(int) $existing['id']]);

        Response::success([
            'document_id' => (int) $existing['id'],
            'title' => $overrides['title'],
            'updated' => true,
        ], 'Wissens-Eintrag aktualisiert');
    } catch (\Exception $e) {
        Response::error('Update fehlgeschlagen: ' . $e->getMessage());
    }
}

// Neu anlegen
try {
    $prepared = $services['ingestService']->prepare($text, $extractContext);
} catch (\Exception $e) {
    Response::error('Analyse fehlgeschlagen: ' . $e->getMessage());
}

$overrides = [
    'title' => $titleHint !== '' ? $titleHint : ($prepared['metadata']['title'] ?: ('Chat — ' . ($conv['title'] ?: 'Ohne Titel'))),
    'description' => $prepared['metadata']['description'],
    'customer_id' => $customerId,
    'category' => $prepared['metadata']['category'] ?? 'Chat',
    'tags' => array_unique(array_merge(['chat-transfer'], $prepared['metadata']['tags'] ?? [])),
];
$meta = [
    'source_type' => 'chat',
    'source_ref' => 'conv:' . $convId,
    'external_id' => $externalId,
    'created_by' => $userId,
];

try {
    $docId = $services['ingestService']->commit($prepared, $overrides, $meta);
} catch (\Exception $e) {
    Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
}

Response::success([
    'document_id' => $docId,
    'title' => $overrides['title'],
    'category' => $overrides['category'],
    'chunks' => count($prepared['chunks']),
    'created' => true,
], 'Chat ins Wissen übertragen');
