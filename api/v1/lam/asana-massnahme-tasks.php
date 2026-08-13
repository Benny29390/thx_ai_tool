<?php
/** GET /lam/asana-massnahme-tasks?massnahme_id=X&suche=… */
use Core\Auth; use Core\Database; use Core\Response; use Services\LamService;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$id = trim((string)($_GET['massnahme_id'] ?? ''));
$suche = trim((string)($_GET['suche'] ?? ''));
$sectionGid = trim((string)($_GET['section_gid'] ?? '')) ?: null;
if ($id === '') Response::error('massnahme_id erforderlich', 400);
require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());
try { Response::success($svc->asanaTasksFuerMassnahme($id, $suche, $sectionGid)); }
catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 400); }
catch (\Throwable $e) { Response::error('Asana-Lookup fehlgeschlagen: ' . $e->getMessage(), 500); }
