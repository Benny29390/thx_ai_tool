<?php
/**
 * POST /api/v1/lam/linkquellen-import-commit
 * Body: { token, urls?: [string,...], herkunft?: string }
 * Importiert die übergebenen URLs als neue Linkquellen. Wenn urls nicht gesetzt,
 * werden alle Kandidaten aus der zum Token gehörenden Datei genommen, außer Dubletten.
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$token = trim((string) ($input['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{16}$/', $token)) {
    Response::error('Ungültiges Token', 400);
}

$dir = sys_get_temp_dir() . '/lam_lq_import';
$dateien = glob($dir . '/' . $token . '.*') ?: [];
if (empty($dateien)) Response::error('Datei nicht (mehr) vorhanden — Preview neu starten', 410);
$pfad = $dateien[0];

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    $preview = $svc->leseLinkquellenKandidaten($pfad);
    $kandidaten = $preview['kandidaten'] ?? [];

    // Filter: nur die im Body angeforderten URLs übernehmen (falls Subset gewählt)
    if (!empty($input['urls']) && is_array($input['urls'])) {
        $whitelist = array_flip(array_map('strtolower', $input['urls']));
        $kandidaten = array_values(array_filter($kandidaten, fn($k) => isset($whitelist[strtolower($k['url'])])));
    }

    $herkunft = trim((string) ($input['herkunft'] ?? ''));
    if ($herkunft === '') $herkunft = 'Excel-Import ' . date('Y-m-d') . ' (' . basename($pfad) . ')';

    $stats = $svc->importiereLinkquellen($kandidaten, ['herkunft' => $herkunft]);

    // Optional: alle erkannten URLs (auch Dubletten!) direkt zum Linkpool eines Kunden hinzufügen
    $customerId = (int) ($input['linkpool_customer_id'] ?? 0);
    if ($customerId > 0 && !empty($kandidaten)) {
        $db = Database::getInstance();
        $urls = array_unique(array_column($kandidaten, 'url'));
        if (!empty($urls)) {
            $in = implode(',', array_fill(0, count($urls), 'LOWER(?)'));
            $domainIds = array_column($db->query(
                "SELECT id FROM lam_domains WHERE LOWER(url) IN ($in) AND geloescht_am IS NULL",
                $urls
            ) ?: [], 'id');
            $stats['linkpool_added'] = 0; $stats['linkpool_existed'] = 0;
            foreach ($domainIds as $did) {
                $vorhanden = $db->queryValue(
                    "SELECT 1 FROM lam_domain_customer WHERE domain_id = ? AND customer_id = ?",
                    [$did, $customerId]
                );
                if ($vorhanden) { $stats['linkpool_existed']++; continue; }
                $db->execute(
                    "INSERT INTO lam_domain_customer (domain_id, customer_id, erstellt_am) VALUES (?, ?, NOW())",
                    [$did, $customerId]
                );
                $stats['linkpool_added']++;
            }
        }
    }

    @unlink($pfad);
    $msg = $stats['neu'] . ' neu, ' . $stats['dubletten'] . ' Dubletten';
    if (isset($stats['linkpool_added'])) {
        $msg .= ' · Linkpool: ' . $stats['linkpool_added'] . ' hinzugefügt';
        if ($stats['linkpool_existed'] > 0) $msg .= ', ' . $stats['linkpool_existed'] . ' bereits drin';
    }
    Response::success($stats, $msg);
} catch (\Throwable $e) {
    Response::error('Import fehlgeschlagen: ' . $e->getMessage(), 500);
}
