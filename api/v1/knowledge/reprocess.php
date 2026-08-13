<?php
/**
 * Knowledge Reprocess API — Wissens-Eintrag aktualisieren + neu verarbeiten
 *
 * POST /knowledge/documents/{id}/reprocess
 * Body: { content?, title?, description?, customer_id?, category?, tags? }
 * Wenn content geaendert → volle Neu-Verarbeitung (Chunks, Embeddings, Entities, Relations)
 * Wenn nur Metadata → nur Update
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') Response::error('Method not allowed', 405);

require_once __DIR__ . '/_helpers.php';
require_once SERVICES_PATH . '/KnowledgeService.php';

$userId = Auth::id();
$isAdmin = Auth::isAdmin();
$userCustomerId = Auth::customerId();

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) Response::error('Dokument-ID erforderlich');

$knowledgeService = new \Services\KnowledgeService($db);
$doc = $knowledgeService->getDocument($docId);
if (!$doc) Response::notFound('Dokument nicht gefunden');

if (!$isAdmin && $doc['customer_id'] !== null && $doc['customer_id'] != $userCustomerId) {
    Response::forbidden('Kein Zugriff');
}

$newContent = isset($input['content']) ? trim($input['content']) : null;
$reanalyze = false;

// Content geaendert? Dann komplett neu verarbeiten
if ($newContent !== null && mb_strlen($newContent) >= 20) {
    $newHash = hash('sha256', $newContent);
    if ($newHash !== $doc['content_hash']) {
        $reanalyze = true;
    }
}

$customerId = null;
if (isset($input['customer_id'])) {
    $customerId = $input['customer_id'] === '' ? null : (int) $input['customer_id'];
} else {
    $customerId = $doc['customer_id'];
}

$customerName = null;
if ($customerId) {
    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
    $customerName = $c['name'] ?? null;
}

$overrides = [
    'title' => $input['title'] ?? $doc['title'],
    'description' => $input['description'] ?? $doc['description'],
    'customer_id' => $customerId,
    'category' => $input['category'] ?? $doc['category'],
    'tags' => is_array($input['tags'] ?? null) ? $input['tags'] : ($doc['tags'] ?: []),
];

// Session freigeben fuer Performance (LLM-Call dauert)
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

try {
    $services = knowledgeBuildServices($db);

    if ($reanalyze) {
        // Volle Re-Verarbeitung mit LLM-Call fuer Extraktion
        $services['ingestService']->reprocess(
            $docId,
            $newContent,
            $overrides,
            ['customer_name' => $customerName],
            $userId,
            true
        );
    } else {
        // Nur Metadata-Update
        $services['ingestService']->reprocess(
            $docId,
            '',
            $overrides,
            [],
            $userId,
            false
        );
    }

    $updated = $knowledgeService->getDocument($docId);
    Response::success([
        'document' => $updated,
        'reanalyzed' => $reanalyze,
    ], $reanalyze ? 'Neu verarbeitet' : 'Metadata aktualisiert');
} catch (\Exception $e) {
    error_log('Knowledge reprocess error: ' . $e->getMessage());
    Response::error('Update fehlgeschlagen: ' . $e->getMessage());
}
