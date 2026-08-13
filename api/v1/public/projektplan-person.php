<?php
/**
 * Öffentliche Personen-Aufgabenliste — kein Auth.
 *
 * GET /public/personen-aufgaben/{hash}  → Alle aktiven Plan-Zeilen wo die Person
 *                                          lead_responsible ist ODER in responsible-Liste vorkommt.
 *                                          Gruppiert nach Kunde.
 */

use Core\Database;
use Core\Response;

$db = Database::getInstance();
$hash = trim((string) ($_GET['hash'] ?? ''));
if ($hash === '') Response::notFound('Sharelink fehlt');

$share = $db->queryOne("SELECT person_name FROM pp_person_shares WHERE share_hash = ?", [$hash]);
if (!$share) Response::notFound('Sharelink ungültig');

$personName = $share['person_name'];
$lower = mb_strtolower($personName);

// === POST: Erledigt-Toggle ohne Login ===
// Erlaubt nur Zeilen togglen, in denen die Person als Lead ODER Resp eingetragen ist.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle-done') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $rowId = (int)($payload['row_id'] ?? 0);
    $isDone = !empty($payload['is_done']) ? 1 : 0;
    if (!$rowId) Response::error('row_id fehlt');
    // Validieren: Zeile muss existieren UND Person muss Lead oder in resp sein
    $row = $db->queryOne(
        "SELECT id, lead_responsible, responsible, is_done FROM pp_plan_rows
         WHERE id = ? AND row_type = 'item' LIMIT 1",
        [$rowId]
    );
    if (!$row) Response::notFound('Zeile nicht gefunden');
    $isLead = mb_strtolower((string)$row['lead_responsible']) === $lower;
    $names = array_map(fn($s) => mb_strtolower(trim($s)), explode(',', (string)$row['responsible']));
    $isResp = in_array($lower, $names, true);
    if (!$isLead && !$isResp) Response::forbidden('Du bist dieser Aufgabe nicht zugeordnet');
    $db->update('pp_plan_rows', ['is_done' => $isDone], 'id = ?', [$rowId]);
    Response::success(['id' => $rowId, 'is_done' => $isDone]);
}

// Lade alle Plan-Zeilen wo die Person beteiligt ist
$rows = $db->query(
    "SELECT r.id, r.description, r.timeframe, r.ist_hours, r.planned_hours,
            r.lead_responsible, r.responsible, r.deadline, r.is_done, r.is_placeholder,
            r.notes, r.position,
            p.id AS plan_id, p.title AS plan_title, p.period_from, p.period_to,
            c.id AS customer_id, c.name AS customer_name, c.abbreviation AS customer_abbr,
            c.hex_color AS customer_color, c.logo_path AS customer_logo
     FROM pp_plan_rows r
     JOIN pp_plans p ON p.id = r.plan_id AND p.state = 1
     LEFT JOIN customers c ON c.id = p.customer_id
     WHERE r.row_type = 'item'
       AND (
         LOWER(r.lead_responsible) = ?
         OR LOWER(r.responsible) LIKE ?
       )
     ORDER BY (c.name IS NULL), c.name ASC, p.id ASC, r.position ASC",
    [$lower, '%' . $lower . '%']
) ?: [];

// Filtere nochmal exakt: responsible ist Komma-Liste, also Wort-Match
$filtered = [];
foreach ($rows as $r) {
    $isLead = mb_strtolower((string) $r['lead_responsible']) === $lower;
    $names = array_map(fn($s) => mb_strtolower(trim($s)), explode(',', (string) $r['responsible']));
    $isResp = in_array($lower, $names, true);
    if (!$isLead && !$isResp) continue;
    $r['role'] = $isLead ? 'lead' : 'resp';
    $filtered[] = $r;
}

// Gruppiere nach Kunde
$byCustomer = [];
$totals = ['soll' => 0, 'ist' => 0, 'done' => 0, 'open' => 0, 'total' => 0];
foreach ($filtered as $r) {
    $key = $r['customer_id'] ? 'c' . $r['customer_id'] : 'none';
    if (!isset($byCustomer[$key])) {
        $byCustomer[$key] = [
            'customer_id' => $r['customer_id'],
            'customer_name' => $r['customer_name'] ?: '— Kein Kunde —',
            'customer_abbr' => $r['customer_abbr'],
            'customer_color' => $r['customer_color'] ?: '#94a3b8',
            'customer_logo' => $r['customer_logo'],
            'tasks' => [],
            'sum_soll' => 0,
            'sum_ist' => 0,
        ];
    }
    $byCustomer[$key]['tasks'][] = $r;
    $byCustomer[$key]['sum_soll'] += (float) $r['planned_hours'];
    $byCustomer[$key]['sum_ist'] += (float) $r['ist_hours'];
    $totals['soll'] += (float) $r['planned_hours'];
    $totals['ist'] += (float) $r['ist_hours'];
    $totals['total']++;
    if ((int) $r['is_done']) $totals['done']++;
    else $totals['open']++;
}

Response::success([
    'person_name' => $personName,
    'customers' => array_values($byCustomer),
    'totals' => $totals,
]);
