<?php
/**
 * Knowledge Documents API — List/Get/Update/Delete
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once __DIR__ . '/_helpers.php';
require_once SERVICES_PATH . '/KnowledgeService.php';

$knowledgeService = new \Services\KnowledgeService($db);

$userId = Auth::id();
$isAdmin = Auth::isAdmin();
// Effektive Kunden-IDs (direkt + ueber Rolle) — Vergleich gegen die ganze Liste statt nur den aktiven Kunden
$allowedCustomerIds = array_map(fn($c) => (int)$c['id'], Auth::customers());

// Helper: Darf der User auf dieses Dokument zugreifen?
$canAccessDoc = function(array $doc) use ($isAdmin, $allowedCustomerIds): bool {
    if ($isAdmin) return true;
    $cid = $doc['customer_id'] !== null ? (int)$doc['customer_id'] : null;
    if ($cid === null) return false; // globales Wissen nur fuer Admin schreibbar; lesen tolerieren wir
    return in_array($cid, $allowedCustomerIds, true);
};

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$subAction = $_GET['sub_action'] ?? null;

switch ($method) {
    case 'GET':
        if ($id && $subAction === 'graph') {
            $doc = $knowledgeService->getDocument($id);
            if (!$doc) Response::notFound('Dokument nicht gefunden');
            if (!$canAccessDoc($doc)) Response::forbidden('Kein Zugriff');

            $entities = $knowledgeService->getEntitiesByDocument($id);
            $relations = $knowledgeService->getRelationsByDocument($id);

            Response::success([
                'document' => ['id' => $doc['id'], 'title' => $doc['title']],
                'entities' => $entities,
                'relations' => $relations,
            ]);
        }

        if ($id) {
            $doc = $knowledgeService->getDocument($id);
            if (!$doc) Response::notFound('Dokument nicht gefunden');
            if (!$canAccessDoc($doc)) Response::forbidden('Kein Zugriff');

            $doc['chunks'] = $knowledgeService->getChunksByDocument($id);
            $doc['entities'] = $knowledgeService->getEntitiesByDocument($id);
            $doc['relations'] = $knowledgeService->getRelationsByDocument($id);

            Response::success($doc);
        } else {
            // Liste — Admin: optional Filter, Andere: nur erlaubte Kunden
            // 'null' = explizit Docs ohne Kunde; sonst Integer-ID; sonst kein Filter.
            $rawCust = $_GET['customer_id'] ?? '';
            if ($rawCust === 'null') {
                $reqCustomerId = 'null';
            } elseif ($rawCust !== '' && ctype_digit((string) $rawCust)) {
                $reqCustomerId = (int) $rawCust;
                if (!$isAdmin && !in_array($reqCustomerId, $allowedCustomerIds, true)) $reqCustomerId = null;
            } else {
                $reqCustomerId = null;
            }
            $parseList = function($name) {
                $v = $_GET[$name] ?? null;
                if (is_array($v)) return array_values(array_filter(array_map('trim', $v)));
                if (is_string($v) && $v !== '') return array_values(array_filter(array_map('trim', explode(',', $v))));
                return [];
            };
            $tags = $parseList('tags');
            $customerTags = $parseList('customer_tags');

            $filters = [
                'customer_id' => $reqCustomerId,
                'category' => $_GET['category'] ?? null,
                'source_type' => $_GET['source_type'] ?? null,
                'ingest_mode' => $_GET['ingest_mode'] ?? null,
                'search' => $_GET['search'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'tags' => $tags,
                'customer_tags' => $customerTags,
                'customer_status' => $_GET['customer_status'] ?? null,
                'size_bucket' => $_GET['size_bucket'] ?? null,
                'status' => $_GET['status'] ?? null,
                'sort_by' => $_GET['sort_by'] ?? null,
                'sort_dir' => $_GET['sort_dir'] ?? null,
                'limit' => max(1, min(500, (int) ($_GET['limit'] ?? 100))),
                'offset' => max(0, (int) ($_GET['offset'] ?? 0)),
                'allowed_customer_ids' => $isAdmin ? null : $allowedCustomerIds,
            ];
            $docs = $knowledgeService->listDocuments($filters);
            $total = $knowledgeService->countDocuments($filters);
            $stats = $knowledgeService->getStats($isAdmin ? null : ($reqCustomerId ?? null));
            Response::success([
                'items' => $docs,
                'total' => $total,
                'stats' => $stats,
            ]);
        }
        break;

    case 'PUT':
        if (!$id) Response::error('ID erforderlich');
        $doc = $knowledgeService->getDocument($id);
        if (!$doc) Response::notFound('Dokument nicht gefunden');
        if (!$canAccessDoc($doc)) Response::forbidden('Kein Zugriff');

        // Falls customer_id geaendert wird: Ziel-Kunde muss auch erlaubt sein
        if (isset($input['customer_id']) && !$isAdmin) {
            $newCid = $input['customer_id'] !== '' ? (int)$input['customer_id'] : null;
            knowledgeAssertWriteAccess($newCid);
        }

        $knowledgeService->updateDocument($id, $input);
        $updated = $knowledgeService->getDocument($id);
        Response::success($updated, 'Dokument aktualisiert');
        break;

    case 'DELETE':
        if (!$id) Response::error('ID erforderlich');
        $doc = $knowledgeService->getDocument($id);
        if (!$doc) Response::notFound('Dokument nicht gefunden');
        if (!$canAccessDoc($doc)) Response::forbidden('Kein Zugriff');

        $knowledgeService->deleteDocument($id);
        Response::success(null, 'Dokument geloescht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
