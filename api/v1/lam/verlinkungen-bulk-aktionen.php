<?php
/**
 * Sammel-Endpoint fuer Linkprofil-Bulk-Aktionen, die ueber die einfache
 * Setz-/Loesch-Bulk hinausgehen.
 *
 * POST /api/v1/lam/verlinkungen-bulk-aktionen
 * Body JSON:
 *   { aktion: 'erreichbarkeit' | 'linktext' | 'linkart_aus_wissen' |
 *             'empfehlung_aus_wissen' | 'ki_empfehlung' |
 *             'sistrix_si' | 'sistrix_dp',
 *     ids: [int, ...],
 *     // Optional je nach Aktion:
 *     force?: bool   // bei 'linktext' — auch bei bereits vorhandenem Text holen
 *   }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LamService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer Parallel-Arbeit bei langen Bulks (Sistrix, KI, ...).
Session::release();

$raw = file_get_contents('php://input');
$json = $raw ? json_decode($raw, true) : null;
if (!is_array($json)) Response::error('Body muss JSON sein', 400);

$aktion = (string)($json['aktion'] ?? '');
$ids    = $json['ids'] ?? [];
if (!is_array($ids) || !$ids) Response::error('ids fehlt oder leer', 400);

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

try {
    switch ($aktion) {
        case 'erreichbarkeit':
            Response::success($svc->pruefeErreichbarkeitVerlinkungenBulk($ids));
            break;
        case 'linktext':
            Response::success($svc->holeLinktextVerlinkungenBulk($ids, !empty($json['force'])));
            break;
        case 'linkart_aus_wissen':
            Response::success($svc->wendeWissenAufVerlinkungenAn($ids, 'linkart'));
            break;
        case 'empfehlung_aus_wissen':
            Response::success($svc->wendeWissenAufVerlinkungenAn($ids, 'empfehlung'));
            break;
        case 'ki_empfehlung':
            Response::success($svc->bewerteEmpfehlungVerlinkungenBulk($ids));
            break;
        case 'sistrix_si':
            Response::success($svc->holeSistrixVerlinkungenBulk($ids, ['si']));
            break;
        case 'sistrix_dp':
            Response::success($svc->holeSistrixVerlinkungenBulk($ids, ['dp']));
            break;
        default:
            Response::error('Unbekannte Aktion: ' . $aktion, 400);
    }
} catch (\Throwable $e) {
    Response::error('Bulk-Aktion fehlgeschlagen: ' . $e->getMessage(), 500);
}
