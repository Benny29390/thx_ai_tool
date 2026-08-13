<?php
/**
 * Rückmeldung zu einem Linkoption-Eintrag erfassen.
 *
 * POST /api/v1/lam/linkoption-rueckmeldung
 * Body: {
 *   id,
 *   rueckmeldung_am, rueckmeldung_typ,
 *   naechste_aktion_am?, naechste_aktion_notiz?,
 *   preis_kunde? (wenn Preisangebot kam)
 * }
 */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::hasRole(ROLE_MANAGER)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = trim((string)($input['id'] ?? ''));
if ($id === '') Response::error('id erforderlich');

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    if (!empty($input['rueckmeldung_am'])) {
        $svc->aktualisiereLinkoptionFeld($id, 'letzte_rueckmeldung_am', $input['rueckmeldung_am']);
    }
    $rueckmeldungTyp = trim((string) ($input['rueckmeldung_typ'] ?? ''));
    if ($rueckmeldungTyp !== '') {
        $svc->aktualisiereLinkoptionFeld($id, 'letzte_rueckmeldung_typ', $rueckmeldungTyp);
    }
    if (array_key_exists('naechste_aktion_am', $input)) {
        $svc->aktualisiereLinkoptionFeld($id, 'naechste_aktion_am', $input['naechste_aktion_am'] ?: null);
    }
    if (array_key_exists('naechste_aktion_notiz', $input)) {
        $svc->aktualisiereLinkoptionFeld($id, 'naechste_aktion_notiz', $input['naechste_aktion_notiz'] ?: null);
    }
    if (array_key_exists('preis_kunde', $input) && $input['preis_kunde'] !== '') {
        $svc->aktualisiereLinkoptionFeld($id, 'preis_kunde', $input['preis_kunde']);
    }

    // Auto-Status-Pipeline: der Status wandert je nach Rückmeldungs-Typ automatisch weiter.
    // Override via {neuer_status: 'X'} oder {auto_status_aus: true}.
    $neuerStatus = null;
    if (!empty($input['neuer_status'])) {
        $neuerStatus = trim((string) $input['neuer_status']);
    } elseif (empty($input['auto_status_aus']) && $rueckmeldungTyp !== '') {
        $map = [
            'absage'         => 'abgelehnt',
            'spam'           => 'abgelehnt',
            'keine_reaktion' => 'ohne_antwort',
            'preisangebot'   => 'bestaetigt',
            'interesse'      => 'bestaetigt',
            // rueckfrage → bleibt in_akquise (wir warten auf weitere Klärung)
        ];
        $neuerStatus = $map[$rueckmeldungTyp] ?? null;
    }
    if ($neuerStatus !== null) {
        $svc->aktualisiereLinkoptionStatus($id, $neuerStatus);
    }
    Response::success(['neuer_status' => $neuerStatus], 'Rückmeldung gespeichert');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 400);
}
