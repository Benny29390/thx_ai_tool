<?php
/** POST /mail/eml-upload (multipart) — Field: konto_id + eml[] (Multi-Datei) */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailImapService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$kontoId = (int)($_POST['konto_id'] ?? 0);
if ($kontoId <= 0) Response::error('konto_id erforderlich', 400);

if (empty($_FILES['eml'])) Response::error('Keine Dateien hochgeladen', 400);

require_once SERVICES_PATH . '/MailImapService.php';
$svc = new MailImapService(Database::getInstance());

// Multi-File-Handling (normalize)
$dateien = $_FILES['eml'];
if (!is_array($dateien['name'])) {
    $dateien = ['name' => [$dateien['name']], 'tmp_name' => [$dateien['tmp_name']], 'error' => [$dateien['error']], 'size' => [$dateien['size']]];
}

$ergebnisse = ['erfolg' => 0, 'dublette' => 0, 'fehler' => 0, 'details' => []];
foreach ($dateien['name'] as $i => $name) {
    if ($dateien['error'][$i] !== UPLOAD_ERR_OK) {
        $ergebnisse['fehler']++;
        $ergebnisse['details'][] = "$name: Upload-Fehler {$dateien['error'][$i]}";
        continue;
    }
    if ($dateien['size'][$i] > 20 * 1024 * 1024) {
        $ergebnisse['fehler']++;
        $ergebnisse['details'][] = "$name: zu groß (max 20 MB)";
        continue;
    }
    try {
        $roh = file_get_contents($dateien['tmp_name'][$i]);
        $r = $svc->verarbeiteEml($roh, $kontoId, 'eml_upload');
        $ergebnisse[$r['status']]++;
        $ergebnisse['details'][] = "$name: " . $r['status'];
    } catch (\Throwable $e) {
        $ergebnisse['fehler']++;
        $ergebnisse['details'][] = "$name: " . $e->getMessage();
    }
}
Response::success($ergebnisse);
