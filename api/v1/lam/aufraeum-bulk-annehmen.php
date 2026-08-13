<?php
/**
 * Bulk-Annahme aller "klaren" Aufraeum-Vorschlaege in einem Chunk.
 *
 * POST /api/v1/lam/aufraeum-bulk-annehmen
 * Body: {
 *   customer_id: int,
 *   domains: [{ domain: string, linkart: string, strategie: string }, ...]
 * }
 *
 * Frontend ruft chunked (z.B. 20er) fuer das Fortschritts-Modal.
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Services\LinkprofilAufraeumService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);

// Session-Lock freigeben fuer parallel-Arbeit (siehe Hinweis in
// aufraeum-klassifiziere-ki.php).
Session::release();

$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) Response::error('JSON-Body erforderlich', 400);

$customerId = (int)($json['customer_id'] ?? 0);
$domains    = $json['domains'] ?? [];

if ($customerId <= 0) Response::error('customer_id erforderlich', 400);
if (!is_array($domains) || !$domains) Response::error('domains[] erforderlich', 400);

require_once SERVICES_PATH . '/LinkprofilAufraeumService.php';
$svc = new LinkprofilAufraeumService(Database::getInstance());

try {
    Response::success($svc->nehmeBulkAn($customerId, $domains, Auth::id()));
} catch (\Throwable $e) {
    Response::error('Bulk fehlgeschlagen: ' . $e->getMessage(), 500);
}
