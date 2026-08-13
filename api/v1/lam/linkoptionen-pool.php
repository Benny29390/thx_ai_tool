<?php
/**
 * GET /api/v1/lam/linkoptionen-pool
 * Linkquellen-Pool für die Linkoptionen-Vorauswahl-Sicht.
 * Wie /lam/linkquellen, aber zusätzlich:
 *   - Filter `customer_id`: nur Domains die diesem Kunden zugeordnet sind
 *   - Filter `status_auswahl`: nur Domains die einen Vorschlagslisten-Eintrag mit diesem Status haben
 *                              (oder `ohne` = ohne aktive Auswahl)
 *   - Pro Domain: Array `auswahlen` mit aktiven Listen-Einträgen pro Kunde (für „In aktiver Auswahl"-Spalte)
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Nur GET', 405);

$db = Database::getInstance();
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService($db);

$filter = [];
foreach (['suche', 'sort', 'order'] as $k) {
    if (!empty($_GET[$k])) $filter[$k] = $_GET[$k];
}
if (!empty($_GET['verifikation_status'])) {
    $filter['verifikation_status'] = (array) $_GET['verifikation_status'];
}
if (!empty($_GET['linkart'])) $filter['linkart'] = (array) $_GET['linkart'];
if (!empty($_GET['bewertung'])) $filter['bewertung'] = (array) $_GET['bewertung'];
foreach (['si_min', 'si_max', 'preis_min', 'preis_max'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $filter[$k] = $_GET[$k];
}
$filter['limit']  = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
$filter['offset'] = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

// Kunden-Filter: nur Domains dieses Kunden zeigen
$customerId = !empty($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;
$statusAuswahl = trim((string) ($_GET['status_auswahl'] ?? ''));

// WICHTIG: Der Linkpool ist immer KUNDEN-SPEZIFISCH.
// Ohne customer_id liefern wir nichts (= leerer Pool) — die globale
// Linkquellen-Liste hat ihre eigene Sicht unter /lam/linkquellen.
if (!$customerId) {
    Response::success([
        'rows'    => [],
        'total'   => 0,
        'hinweis' => 'Linkpool-Sicht braucht einen Kunden. Bitte einen Kunden im Filter wählen.',
    ]);
}

// Linkpool-Domain-IDs holen — alles was via lam_domain_customer für diesen Kunden vorausgewählt ist
$poolIds = array_column($db->query(
    "SELECT domain_id FROM lam_domain_customer WHERE customer_id = ?",
    [$customerId]
) ?: [], 'domain_id');

if (empty($poolIds)) {
    Response::success([
        'rows'    => [],
        'total'   => 0,
        'hinweis' => 'Im Linkpool dieses Kunden ist noch keine Domain. Domains aus den Linkquellen über „Zum Linkpool hinzufügen" einpflegen.',
    ]);
}

// Linkquellen-Query strikt auf die Pool-IDs einschränken (kein Verlust durch Pagination)
$filter['domain_ids'] = $poolIds;
$filter['limit'] = max(500, count($poolIds));
$result = $svc->listeLinkquellen($filter);
$rows = $result['rows'] ?? [];

/**
 * Host aus einer LAM-URL normalisieren: Pfad weg, "www." weg, klein.
 * Beide Seiten (lam_domains.url und lam_verlinkungen.domain) speichern reine Hosts,
 * mal mit, mal ohne www./Pfad — deshalb identisch normalisieren, sonst matcht nichts.
 */
$hostOf = function (?string $u): string {
    $s = strtolower(trim((string) $u));
    $s = preg_replace('#^https?://#', '', $s);
    $s = explode('/', $s)[0];
    return preg_replace('#^www\.#', '', $s);
};

// Auswahlen pro Domain anreichern
if (!empty($rows)) {
    $domainIds = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($domainIds), '?'));

    // --- Linkprofil-Abgleich: verlinkt diese Quelle den Kunden bereits? ---
    // Gegen lam_verlinkungen des Kunden: so sieht man im Pool sofort, was eine
    // Neubuchung waere und was eine bewusste Wiederholung.
    $verlinktMap = [];
    foreach ($db->query(
        "SELECT v.domain, COUNT(*) AS anzahl, MAX(v.erstellt_am) AS letzte
         FROM lam_verlinkungen v
         WHERE v.customer_id = ? AND v.geloescht_am IS NULL AND v.domain IS NOT NULL AND v.domain <> ''
         GROUP BY v.domain",
        [$customerId]
    ) ?: [] as $v) {
        $h = $hostOf($v['domain']);
        if ($h === '') continue;
        if (!isset($verlinktMap[$h])) $verlinktMap[$h] = ['anzahl' => 0, 'letzte' => null];
        $verlinktMap[$h]['anzahl'] += (int) $v['anzahl'];
        if ($v['letzte'] > $verlinktMap[$h]['letzte']) $verlinktMap[$h]['letzte'] = $v['letzte'];
    }

    // --- KI-Kurzbeschreibung (Fallback, wenn kein Import-Cluster in der Notiz steht) ---
    $kiMap = [];
    foreach ($db->query(
        "SELECT id, ki_kurzbeschreibung FROM lam_domains
         WHERE id IN ($in) AND ki_kurzbeschreibung IS NOT NULL AND ki_kurzbeschreibung <> ''",
        $domainIds
    ) ?: [] as $k) {
        $kiMap[$k['id']] = $k['ki_kurzbeschreibung'];
    }

    // --- Konditionen pro Domain (fuer den Preis-Inline-Edit) ---
    $kondMap = [];
    foreach ($db->query(
        "SELECT domain_id, COUNT(*) AS anzahl, MIN(id) AS erste_id
         FROM lam_konditionen WHERE domain_id IN ($in) AND geloescht_am IS NULL
         GROUP BY domain_id",
        $domainIds
    ) ?: [] as $k) {
        $kondMap[$k['domain_id']] = $k;
    }

    foreach ($rows as &$d) {
        $h = $hostOf($d['url']);
        $v = $verlinktMap[$h] ?? null;
        $d['verlinkt_anzahl'] = $v ? (int) $v['anzahl'] : 0;
        $d['verlinkt_letzte'] = $v['letzte'] ?? null;

        // Kurzbeschreibung: Der Import legt den Themen-Cluster als "[Cluster: …]" in die Notiz —
        // das ist die aussagekraeftigste Info zur Quelle ("SHK-/TGA-Leitportal", "Branchenbuch"),
        // war bisher aber im Freitext vergraben. Hier als eigenes Feld herausziehen.
        // Reihenfolge: die ausfuehrliche Begruendung aus der Recherche-Datei gewinnt,
        // dann der Import-Cluster, dann eine KI-Kurzbeschreibung.
        $besch = trim((string) ($d['beschreibung'] ?? ''));
        if ($besch === '' && !empty($d['notiz_kurz']) && preg_match('/\[Cluster:\s*([^\]]+)\]/u', (string) $d['notiz_kurz'], $m)) {
            $besch = trim($m[1]);
        }
        if ($besch === '' && !empty($kiMap[$d['id']])) {
            $besch = trim((string) $kiMap[$d['id']]);
        }
        $d['beschreibung'] = $besch;

        $k = $kondMap[$d['id']] ?? null;
        $d['kondition_anzahl'] = $k ? (int) $k['anzahl'] : 0;
        // Nur bei genau EINER Kondition ist der Preis eindeutig inline editierbar.
        $d['kondition_id'] = ($k && (int) $k['anzahl'] === 1) ? $k['erste_id'] : null;
    }
    unset($d);
    $auswahlenRaw = $db->query(
        "SELECT e.id AS eintrag_id, e.domain_id, e.status, e.artikelthema,
                v.id AS liste_id, v.name AS liste_name,
                c.id AS customer_id, c.abbreviation AS customer_kuerzel, c.name AS customer_name
         FROM lam_vorschlagsliste_eintraege e
         JOIN lam_vorschlagslisten v ON v.id = e.vorschlagsliste_id AND v.geloescht_am IS NULL
         JOIN customers c ON c.id = v.customer_id
         WHERE e.domain_id IN ($in)
         ORDER BY e.id DESC",
        $domainIds
    ) ?: [];
    $auswahlMap = [];
    foreach ($auswahlenRaw as $a) {
        $auswahlMap[$a['domain_id']][] = $a;
    }
    foreach ($rows as &$d) {
        $d['auswahlen'] = $auswahlMap[$d['id']] ?? [];
    }
    unset($d);
}

// Verlinkt-Filter (post-hoc, weil der Abgleich oben angereichert wird):
//   'ja'   = Quelle verlinkt den Kunden bereits (Wiederholung waere bewusste Entscheidung)
//   'nein' = noch nie verlinkt (echte Neuoption)
$verlinktFilter = trim((string) ($_GET['verlinkt'] ?? ''));
if ($verlinktFilter === 'ja' || $verlinktFilter === 'nein') {
    $rows = array_values(array_filter($rows, function ($d) use ($verlinktFilter) {
        $hat = (int) ($d['verlinkt_anzahl'] ?? 0) > 0;
        return $verlinktFilter === 'ja' ? $hat : !$hat;
    }));
}

// status_auswahl-Filter (post-hoc, da JSON-abhängig)
if ($statusAuswahl !== '') {
    $rows = array_values(array_filter($rows, function ($d) use ($statusAuswahl) {
        $hat = !empty($d['auswahlen']);
        if ($statusAuswahl === 'ohne') return !$hat;
        if (!$hat) return false;
        foreach ($d['auswahlen'] as $a) {
            if (($a['status'] ?? '') === $statusAuswahl) return true;
        }
        return false;
    }));
}

Response::success([
    'rows'  => $rows,
    'total' => count($rows),
]);
