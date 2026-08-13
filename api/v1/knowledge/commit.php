<?php
/**
 * Knowledge Commit — Prepared-Daten final in DB schreiben
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';

$preparedId = trim($input['prepared_id'] ?? '');
if (empty($preparedId)) Response::error('prepared_id erforderlich');

$data = knowledgeLoadPrepared($preparedId);
if (!$data) Response::error('Prepared-Daten nicht gefunden (abgelaufen?)');

$services = knowledgeBuildServices($db);

// User-Overrides akzeptieren
$overrides = [
    'title' => trim($input['title'] ?? $data['prepared']['metadata']['title']),
    'description' => trim($input['description'] ?? $data['prepared']['metadata']['description']),
    'customer_id' => isset($input['customer_id']) && $input['customer_id'] !== '' ? (int) $input['customer_id'] : null,
    'category' => trim($input['category'] ?? $data['prepared']['metadata']['category']),
    'tags' => is_array($input['tags'] ?? null) ? $input['tags'] : $data['prepared']['metadata']['tags'],
];
// Hard-Constraint: ohne Kunde landet nichts in der Wissensbasis.
// Wenn kein customer_id mitkam: Auto-Detect aus dem Inhalt versuchen.
if (empty($overrides['customer_id']) || $overrides['customer_id'] <= 0) {
    require_once SERVICES_PATH . '/CustomerDetector.php';
    $detector = new \Services\CustomerDetector($db);
    $text = (string) ($data['prepared']['original_content'] ?? '');
    $context = [
        'title' => $overrides['title'] ?? '',
        'source_ref' => $data['extra']['source_ref'] ?? '',
    ];
    $detected = $detector->detectFromText($text, $context);
    if ($detected !== null) {
        $overrides['customer_id'] = $detected;
    } else {
        Response::error('Kunde konnte nicht eindeutig erkannt werden — bitte manuell auswählen.');
    }
}
// Sicherheits-Check: User darf nur auf erlaubte Kunden schreiben
knowledgeAssertWriteAccess($overrides['customer_id']);

$meta = [
    'source_type' => $data['extra']['source_type'] ?? 'text',
    'source_ref' => $data['extra']['source_ref'] ?? null,
    'created_by' => Auth::id(),
];

try {
    $docId = $services['ingestService']->commit($data['prepared'], $overrides, $meta);
    knowledgeDeletePrepared($preparedId);

    $doc = $services['knowledgeService']->getDocument($docId);
    Response::success(['document_id' => $docId, 'document' => $doc], 'Wissens-Eintrag gespeichert');
} catch (\Exception $e) {
    error_log('Knowledge commit error: ' . $e->getMessage());
    Response::error('Speichern fehlgeschlagen: ' . $e->getMessage());
}
