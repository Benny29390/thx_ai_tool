<?php
/**
 * Querschnittsaufgaben: EINE Asana-Sammelaufgabe mit je einer Unteraufgabe pro ausgewaehltem Kunden.
 *
 * GET  /admin/querschnitt-task
 *      -> { default_project: {gid,name}, projects: [...] }  (fuer den Dialog)
 *
 * POST /admin/querschnitt-task
 *      Body: { customer_ids: [int], title, notes?, due_on?, project_gid?, section_gid? }
 *      -> legt Sammelaufgabe an + je Kunde eine Unteraufgabe (nur unter der Sammelaufgabe,
 *         NICHT auf den Kundenboards — bewusste Entscheidung).
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Settings;
use Services\AsanaService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

$pat = (string) Settings::get('asana_pat');
if ($pat === '') Response::error('Asana-Token nicht konfiguriert. Unter /admin/settings im Reiter Asana eintragen.');

require_once SERVICES_PATH . '/AsanaService.php';
$asana = new AsanaService($pat);

// ---------- Config fuer den Dialog ----------
if ($method === 'GET') {
    $defGid  = (string) Settings::get('asana_querschnitt_project_gid', '');
    $defName = (string) Settings::get('asana_querschnitt_project_name', '');

    $projects = [];
    try {
        $wsGid = (string) Settings::get('asana_workspace_gid', '');
        if ($wsGid === '') {
            $ws = $asana->listWorkspaces();
            $wsGid = $ws[0]['gid'] ?? '';
        }
        if ($wsGid !== '') $projects = $asana->listProjects($wsGid);
    } catch (\Throwable $e) {
        // Projektliste ist optional — der Default reicht zum Anlegen.
    }

    Response::success([
        'default_project' => ['gid' => $defGid, 'name' => $defName],
        'projects'        => $projects,
    ]);
}

// ---------- Anlegen ----------
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];

    $title = trim((string) ($body['title'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    $dueOn = trim((string) ($body['due_on'] ?? ''));
    $ids   = $body['customer_ids'] ?? [];

    if ($title === '') Response::error('Titel fehlt');
    if (!is_array($ids) || empty($ids)) Response::error('Keine Kunden ausgewählt');
    if ($dueOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn)) Response::error('Fälligkeit muss ein Datum sein');
    if ($dueOn === '') $dueOn = null;

    $projectGid = trim((string) ($body['project_gid'] ?? '')) ?: (string) Settings::get('asana_querschnitt_project_gid', '');
    $sectionGid = trim((string) ($body['section_gid'] ?? '')) ?: (string) Settings::get('asana_querschnitt_section_gid', '');
    if ($projectGid === '') {
        Response::error('Kein Ziel-Projekt für Sammelaufgaben hinterlegt. Bitte unter /admin/settings im Reiter Asana setzen.');
    }

    // Kunden laden — alphabetisch nach Kürzel (A→Z), das ist auch die Reihenfolge in Asana.
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $customers = $db->query(
        "SELECT id, name, abbreviation, website FROM customers WHERE id IN ($ph) ORDER BY abbreviation, name",
        $ids
    ) ?: [];
    if (empty($customers)) Response::error('Ausgewählte Kunden nicht gefunden');

    // Verantwortlicher = der angemeldete Nutzer. Massgeblich ist users.asana_user_email —
    // die Asana-Adresse weicht von der Tool-Adresse ab (z.B. @thoxan.de statt @thoxan.com).
    // Reihenfolge: Asana-Mail -> Tool-Mail -> Besitzer des Asana-Tokens.
    $assigneeGid = null;
    try {
        $wsGid  = (string) Settings::get('asana_workspace_gid', '');
        $userId = (int) (Auth::user()['id'] ?? 0);
        $me     = $userId ? $db->queryOne("SELECT email, asana_user_email FROM users WHERE id = ?", [$userId]) : null;

        $candidates = array_filter([
            trim((string) ($me['asana_user_email'] ?? '')),
            trim((string) ($me['email'] ?? '')),
        ]);
        if ($wsGid !== '') {
            foreach ($candidates as $mail) {
                $u = $asana->findUserByEmail($wsGid, $mail);
                if (!empty($u['gid'])) { $assigneeGid = (string) $u['gid']; break; }
            }
        }
        if ($assigneeGid === null) {
            $owner = $asana->getMe();
            if (!empty($owner['gid'])) $assigneeGid = (string) $owner['gid'];
        }
    } catch (\Throwable $e) {
        // Ohne Verantwortlichen anlegen ist besser als gar nicht anlegen.
    }

    // 1. Sammelaufgabe (mit Verantwortlichem + Faelligkeit)
    try {
        $parent = $asana->createTask($projectGid, $title, $sectionGid ?: null, $notes ?: null, $assigneeGid, null, $dueOn);
    } catch (\Throwable $e) {
        Response::error('Sammelaufgabe konnte nicht angelegt werden: ' . $e->getMessage(), 500);
    }
    $parentGid = (string) ($parent['gid'] ?? '');
    if ($parentGid === '') Response::error('Asana lieferte keine Task-ID für die Sammelaufgabe', 500);
    $parentUrl = $parent['permalink_url'] ?? ('https://app.asana.com/0/0/' . $parentGid);

    // 2. Je Kunde eine Unteraufgabe — Titel: "KÜRZEL — https://www.kunde.de"
    $created = [];
    $failed  = [];
    foreach ($customers as $c) {
        $abbr = trim((string) ($c['abbreviation'] ?? ''));
        $site = rtrim(trim((string) ($c['website'] ?? '')), '/');
        $label = $site !== '' ? $site : (string) $c['name'];
        $subName = ($abbr !== '' ? $abbr . ' — ' : '') . $label;
        try {
            // Kundenname in die Beschreibung, damit er trotz URL-Titel auffindbar bleibt.
            $sub = $asana->createSubtask($parentGid, $subName, (string) $c['name'], null, $dueOn);
            $gid = (string) ($sub['gid'] ?? '');
            $created[] = [
                'customer_id' => (int) $c['id'],
                'customer'    => $c['name'],
                'abbreviation'=> $abbr,
                'gid'         => $gid,
                'url'         => $sub['permalink_url'] ?? ('https://app.asana.com/0/0/' . $gid),
            ];
        } catch (\Throwable $e) {
            $failed[] = ['customer' => $c['name'], 'error' => $e->getMessage()];
        }
    }

    // 3. Reihenfolge erzwingen (A→Z). Asana haengt neue Unteraufgaben nicht zuverlaessig hinten an
    //    — deshalb positionieren wir jede explizit hinter ihrem Vorgaenger.
    $prevGid = null;
    foreach ($created as $item) {
        if (empty($item['gid'])) continue;
        try {
            $asana->moveSubtask($item['gid'], $parentGid, $prevGid); // null = an den Anfang
            $prevGid = $item['gid'];
        } catch (\Throwable $e) {
            // Sortierung ist Kosmetik — ein Fehler hier darf das Ergebnis nicht kippen.
        }
    }

    Response::success([
        'parent'   => ['gid' => $parentGid, 'name' => $title, 'url' => $parentUrl],
        'subtasks' => $created,
        'failed'   => $failed,
    ], count($created) . ' Unteraufgaben unter „' . $title . '" angelegt'
        . (count($failed) ? ' — ' . count($failed) . ' fehlgeschlagen' : ''));
}

Response::error('Methode nicht unterstützt', 405);
