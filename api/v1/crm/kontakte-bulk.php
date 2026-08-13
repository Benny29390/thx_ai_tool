<?php
/**
 * POST /api/v1/crm/kontakte/bulk
 * Body: { ids: [int], aktion: 'status_setzen'|'optin_setzen'|'tag_setzen'|'tag_entfernen'|'liste_setzen'|'liste_entfernen'|'loeschen', wert? }
 */
use Core\Auth;
use Core\Database;
use Core\Response;

if (!Auth::can(CAP_CRM)) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = array_map('intval', $input['ids'] ?? []);
$ids = array_values(array_filter($ids));
$aktion = (string)($input['aktion'] ?? '');
$wert = $input['wert'] ?? null;

if (!$ids) Response::error('Keine IDs übergeben', 400);
if ($aktion === '') Response::error('Aktion fehlt', 400);

require_once SERVICES_PATH . '/CrmKontaktService.php';
$db = Database::getInstance();
$svc = new \Services\CrmKontaktService($db);
$actor = Auth::id();

$ok = 0; $fehler = 0;
foreach ($ids as $id) {
    try {
        switch ($aktion) {
            case 'status_setzen':
                $erlaubt = ['lead','interessent','kunde','ehemaliger_kunde','partner','wunschkunde','dienstleister','sonstiges'];
                if (!in_array($wert, $erlaubt, true)) throw new \RuntimeException('Status ungültig');
                $svc->aktualisiereFeld($id, 'kontakt_status', $wert, $actor);
                break;
            case 'optin_setzen':
                $erlaubt = ['pending','single_opted_in','double_opted_in','unsubscribed','hard_bounce','invalid'];
                if (!in_array($wert, $erlaubt, true)) throw new \RuntimeException('Opt-In ungültig');
                $svc->aktualisiereFeld($id, 'opt_in_status', $wert, $actor);
                break;
            case 'tag_setzen':
                $tagId = (int)$wert;
                if ($tagId <= 0) throw new \RuntimeException('tag_id fehlt');
                $svc->setzeTag($id, $tagId, $actor);
                break;
            case 'tag_entfernen':
                $tagId = (int)$wert;
                if ($tagId <= 0) throw new \RuntimeException('tag_id fehlt');
                $svc->entferneTag($id, $tagId, $actor);
                break;
            case 'liste_setzen':
                $listenId = (int)$wert;
                if ($listenId <= 0) throw new \RuntimeException('listen_id fehlt');
                $svc->setzeListenMitgliedschaft($id, $listenId, 'aktiv', $actor);
                break;
            case 'liste_entfernen':
                $listenId = (int)$wert;
                if ($listenId <= 0) throw new \RuntimeException('listen_id fehlt');
                $svc->setzeListenMitgliedschaft($id, $listenId, 'inaktiv', $actor);
                break;
            case 'loeschen':
                $svc->softDelete($id, $actor);
                break;
            default:
                throw new \RuntimeException('Unbekannte Aktion: ' . $aktion);
        }
        $ok++;
    } catch (\Throwable $e) {
        $fehler++;
    }
}

Response::success([
    'ok' => $ok, 'fehler' => $fehler, 'gesamt' => count($ids),
], "$ok von " . count($ids) . ' verarbeitet' . ($fehler ? ", $fehler Fehler" : ''));
