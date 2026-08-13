<?php
/** GET /mail/nachricht-detail?id=X */
use Core\Auth;
use Core\Database;
use Core\Response;
use Services\MailService;

if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);

require_once SERVICES_PATH . '/MailService.php';
$svc = new MailService(Database::getInstance());
$detail = $svc->getDetail($id);
if (!$detail) Response::notFound('Mail nicht gefunden');

// Auto-„gelesen" bei Detail-Aufruf
if (!$detail['gelesen']) $svc->setzeGelesen($id, true);
Response::success($detail);
