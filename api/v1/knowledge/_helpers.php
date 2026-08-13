<?php
/**
 * Knowledge API Helpers — geteilte Logik fuer Prepare/Commit
 */

use Core\Auth;
use Core\Response;
use Services\AIService;
use Services\KnowledgeEmbeddingService;
use Services\KnowledgeExtractionService;
use Services\KnowledgeIngestService;
use Services\KnowledgeService;

/**
 * Erstellt die Knowledge-Services-Instanz (inkl. Settings-Check)
 */
function knowledgeBuildServices($db): array
{
    $settings = [];
    foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $settings = \Core\Settings::decryptMap($settings);

    $openaiKey = $settings['openai_api_key'] ?? '';
    if (empty($openaiKey)) {
        Response::error('OpenAI API-Key nicht konfiguriert (wird noch fuer Metadaten-Extraktion benoetigt)');
    }

    require_once SERVICES_PATH . '/AIService.php';
    require_once SERVICES_PATH . '/KnowledgeExtractionService.php';
    require_once SERVICES_PATH . '/KnowledgeIngestService.php';
    require_once SERVICES_PATH . '/KnowledgeService.php';

    // Embeddings macht der qdrant_sync-Worker (bge-m3 lokal via ki.thoxan.com) —
    // kein OpenAI-Embed-Call mehr. embeddingService bleibt im Rueckgabewert als
    // null fuer Backwards-Compatibility.
    $embeddingService = null;

    $extractAI = new AIService($openaiKey, 'openai');
    $extractionService = new KnowledgeExtractionService($extractAI);

    $ingestService = new KnowledgeIngestService($db, null, $extractionService);
    $knowledgeService = new KnowledgeService($db);

    return compact('knowledgeService', 'ingestService', 'embeddingService', 'extractionService', 'settings');
}

/**
 * Speichert prepared-Daten temporaer in Storage (zur User-Bestaetigung)
 * Nur fuer den aktuellen User zugaenglich.
 */
function knowledgeSavePrepared(array $prepared, array $extra = []): string
{
    $userId = Auth::id();
    $preparedId = bin2hex(random_bytes(16));
    $path = ROOT_PATH . '/storage/knowledge_prepared/' . $userId . '_' . $preparedId . '.json';

    $data = [
        'user_id' => $userId,
        'created_at' => time(),
        'prepared' => $prepared,
        'extra' => $extra,
    ];

    // Vektoren sind float[] — json_encode kompakt
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    return $preparedId;
}

/**
 * Laedt prepared-Daten zurueck
 */
function knowledgeLoadPrepared(string $preparedId): ?array
{
    $userId = Auth::id();
    $path = ROOT_PATH . '/storage/knowledge_prepared/' . $userId . '_' . $preparedId . '.json';
    if (!is_file($path)) return null;

    // Auto-cleanup: Dateien aelter als 24h loeschen
    if (filemtime($path) < time() - 86400) {
        @unlink($path);
        return null;
    }

    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['user_id'] ?? 0) != $userId) return null;
    return $data;
}

/**
 * Loescht prepared-Daten
 */
function knowledgeDeletePrepared(string $preparedId): void
{
    $userId = Auth::id();
    $path = ROOT_PATH . '/storage/knowledge_prepared/' . $userId . '_' . $preparedId . '.json';
    @unlink($path);
}

/**
 * Sicherheits-Check: Darf der aktuelle User auf diesen Kunden schreiben?
 * Admin = ja. Andere: nur wenn Kunde in der effektiven Liste ist.
 *
 * NULL als customer_id = globales Wissen — derzeit nur Admin erlaubt
 * (kann jederzeit auf Manager/Cap erweitert werden, falls gewuenscht).
 *
 * Bricht mit 403 ab, falls nicht erlaubt.
 */
function knowledgeAssertWriteAccess(?int $customerId): void
{
    if (\Core\Auth::isAdmin()) return;

    if ($customerId === null) {
        Response::forbidden('Globales Wissen (ohne Kundenzuordnung) darf nur ein Admin anlegen.');
    }
    $allowed = array_map(fn($c) => (int)$c['id'], \Core\Auth::customers());
    if (!in_array($customerId, $allowed, true)) {
        Response::forbidden('Du hast keinen Schreibzugriff auf diesen Kunden.');
    }
}

/**
 * Bereitet Standard-Response nach Prepare vor
 */
function knowledgePreparedResponse(string $preparedId, array $prepared, array $context = []): void
{
    // Top-Entities fuer Preview (max 10)
    $topEntities = array_slice($prepared['metadata']['entities'] ?? [], 0, 10);

    Response::success([
        'prepared_id' => $preparedId,
        'content_hash' => $prepared['content_hash'],
        'suggestion' => [
            'title' => $prepared['metadata']['title'],
            'description' => $prepared['metadata']['description'],
            'customer_suggestion' => $prepared['metadata']['customer_suggestion'],
            'category' => $prepared['metadata']['category'],
            'tags' => $prepared['metadata']['tags'],
        ],
        'stats' => [
            'chunks' => count($prepared['chunks']),
            'entities' => count($prepared['metadata']['entities']),
            'relations' => count($prepared['metadata']['relations']),
        ],
        'top_entities' => $topEntities,
        'context' => $context,
    ], 'Analyse abgeschlossen');
}
