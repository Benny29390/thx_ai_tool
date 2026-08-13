<?php
/**
 * Knowledge Graph Global — Aggregierter Wissensgraph aller Dokumente.
 *
 * Daten-Aggregation:
 *  - Entities: Top-N nach mention_count
 *  - Relations: pro (from, to, type) zusammengefasst (Weight summiert) → eindeutige Kanten
 *  - Top-1500 staerkste Kanten (sortiert nach Gewicht) — visuell unlesbar darueber
 *  - Pro Knoten-Paar max 1 Kante (staerkster Typ) — verhindert Multi-Edges-Suppe
 *  - Dokumente pro Entity nur die Top-20 nach updated_at (sonst 6000+ Eintraege)
 */

use Core\Auth;
use Core\Response;

global $db, $method;

if ($method !== 'GET') Response::error('Method not allowed', 405);

$userId = Auth::id();
$isAdmin = Auth::isAdmin();
$userCustomerId = Auth::customerId();

// Filter
$customerFilter = $_GET['customer_id'] ?? null;
$typeFilter = $_GET['type'] ?? null;
$limit = (int) ($_GET['limit'] ?? 200);
$limit = max(10, min(1000, $limit));
$maxEdges = (int) ($_GET['max_edges'] ?? 1500);
$maxEdges = max(100, min(5000, $maxEdges));

$effectiveCustomer = null;
if ($isAdmin) {
    if ($customerFilter === 'null') $effectiveCustomer = 'null';
    elseif ($customerFilter !== '' && $customerFilter !== null) $effectiveCustomer = (int) $customerFilter;
} else {
    $effectiveCustomer = $userCustomerId;
}

$where = ['1=1'];
$params = [];
if ($effectiveCustomer === 'null') {
    $where[] = 'e.customer_id IS NULL';
} elseif (is_int($effectiveCustomer)) {
    $where[] = '(e.customer_id = ? OR e.customer_id IS NULL)';
    $params[] = $effectiveCustomer;
}
if (!empty($typeFilter)) {
    $where[] = 'e.type = ?';
    $params[] = $typeFilter;
}

$params[] = $limit;
$entities = $db->query(
    "SELECT e.id, e.name, e.type, e.customer_id, e.mention_count,
            c.name AS customer_name,
            COUNT(DISTINCT ce.chunk_id) AS chunk_count,
            COUNT(DISTINCT ch.document_id) AS doc_count
     FROM knowledge_entities e
     LEFT JOIN customers c ON e.customer_id = c.id
     LEFT JOIN knowledge_chunk_entities ce ON ce.entity_id = e.id
     LEFT JOIN knowledge_chunks ch ON ch.id = ce.chunk_id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY e.id
     ORDER BY e.mention_count DESC, chunk_count DESC
     LIMIT ?",
    $params
);

if (empty($entities)) {
    Response::success([
        'entities' => [], 'relations' => [], 'documents' => [],
        'stats' => ['entity_count' => 0, 'relation_count' => 0, 'document_count' => 0, 'relations_raw' => 0],
    ]);
}

$entityIds = array_column($entities, 'id');
$entityPlaceholders = implode(',', array_fill(0, count($entityIds), '?'));

// Relations aggregiert: pro (from, to, type) summiertes Gewicht + Anzahl Quellen.
// SQL macht das in einem Pass — 9000 Rohzeilen werden in <500 unique Kanten.
$relationParams = array_merge($entityIds, $entityIds);
$relationsRaw = $db->query(
    "SELECT r.from_entity_id AS from_id,
            r.to_entity_id   AS to_id,
            r.type           AS type,
            SUM(r.weight)    AS weight_sum,
            COUNT(*)         AS source_count,
            ANY_VALUE(d.title) AS sample_doc_title
     FROM knowledge_relations r
     LEFT JOIN knowledge_documents d ON r.source_document_id = d.id
     WHERE r.from_entity_id IN ({$entityPlaceholders})
       AND r.to_entity_id   IN ({$entityPlaceholders})
     GROUP BY r.from_entity_id, r.to_entity_id, r.type
     ORDER BY weight_sum DESC",
    $relationParams
);
$relationsRawCount = count($relationsRaw);

// Pro Knoten-Paar nur die staerkste Kante (egal welcher Typ) — verhindert Multi-Edge-Hairball.
$bestEdgePerPair = [];
foreach ($relationsRaw as $r) {
    $key = $r['from_id'] . '-' . $r['to_id'];
    $reverseKey = $r['to_id'] . '-' . $r['from_id'];
    if (isset($bestEdgePerPair[$reverseKey])) continue; // gerichtete Variante schon vorhanden
    if (!isset($bestEdgePerPair[$key]) || (float)$r['weight_sum'] > (float)$bestEdgePerPair[$key]['weight_sum']) {
        $bestEdgePerPair[$key] = $r;
    }
}

// Top-N nach Gewicht
$relations = array_values($bestEdgePerPair);
usort($relations, fn($a, $b) => (float)$b['weight_sum'] <=> (float)$a['weight_sum']);
$relations = array_slice($relations, 0, $maxEdges);

// Auf einheitliche IDs umformatieren (Frontend erwartet from_entity_id/to_entity_id/weight/id)
$relations = array_map(function ($r, $i) {
    return [
        'id'                => 'r' . $i,
        'from_entity_id'    => (int) $r['from_id'],
        'to_entity_id'      => (int) $r['to_id'],
        'type'              => $r['type'],
        'weight'            => (float) $r['weight_sum'],
        'source_count'      => (int) $r['source_count'],
        'document_title'    => $r['sample_doc_title'],
    ];
}, $relations, array_keys($relations));

// Nur die fuer den Klick-Seitenpanel benoetigten Dokumente — max 50 pro Entity
// (bei 200 Entities x 50 Docs = 10000 Eintraege, aber in DEDUPE-MAP reduzieren wir).
// Private Dokumente (z.B. Mail-Postfach) gehoeren nicht in den Graph anderer Nutzer.
$documents = $db->query(
    "SELECT d.id, d.title, d.category, d.customer_id, d.source_type, d.updated_at,
            GROUP_CONCAT(DISTINCT ce.entity_id) AS entity_ids
     FROM knowledge_documents d
     JOIN knowledge_chunks c ON c.document_id = d.id
     JOIN knowledge_chunk_entities ce ON ce.chunk_id = c.id
     WHERE ce.entity_id IN ({$entityPlaceholders})
       AND d.is_active = 1
       AND (d.visibility <> 'privat' OR d.owner_user_id = " . (int) (\Core\Auth::id() ?: 0) . ")
     GROUP BY d.id
     ORDER BY d.updated_at DESC
     LIMIT 2000",
    $entityIds
);
foreach ($documents as &$d) {
    $d['entity_ids'] = $d['entity_ids'] ? array_map('intval', explode(',', $d['entity_ids'])) : [];
    unset($d['updated_at']);
}
unset($d);

Response::success([
    'entities' => $entities,
    'relations' => $relations,
    'documents' => $documents,
    'stats' => [
        'entity_count'   => count($entities),
        'relation_count' => count($relations),
        'relations_raw'  => $relationsRawCount,
        'document_count' => count($documents),
    ],
]);
