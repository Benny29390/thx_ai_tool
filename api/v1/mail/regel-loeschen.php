<?php
/** POST /mail/regel-loeschen Body: { id } */
use Core\Auth;
use Core\Database;
use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Nur POST', 405);
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) Response::error('id erforderlich', 400);
Database::getInstance()->execute("DELETE FROM mail_regeln WHERE id = ?", [$id]);
Response::success(['ok' => true]);
