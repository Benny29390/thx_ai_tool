<?php
/** Kundenportal-Chat: Unterhaltungen auflisten / anlegen. */
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $svc, $customerId, $userId, $isCustomer
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    Response::success($svc->conversations($customerId));
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $title = trim((string)($input['title'] ?? ''));
    $id = $svc->createConversation($customerId, $userId, $title);
    Response::success(['id' => $id, 'title' => $title !== '' ? $title : 'Neue Unterhaltung'], 'Unterhaltung angelegt');
}

Response::error('Method not allowed', 405);
