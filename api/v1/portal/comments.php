<?php
/**
 * Kundenportal-Chat: Nachrichten einer Unterhaltung lesen / senden.
 * Tenant-Isolation via _resolve.php. Kunde fragt + KI aktiv -> automatische
 * KI-Antwort aus kuratiertem Portal-Kontext (+ Text angehaengter Dateien).
 */
use Core\Auth;
use Core\Response;

require __DIR__ . '/_resolve.php'; // $db, $svc, $customerId, $userId, $isCustomer, $resolveConversation
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $convId = (int) ($_GET['conversation'] ?? 0);
    if (!$convId) Response::error('Unterhaltung fehlt');
    $resolveConversation($convId);
    Response::success($svc->comments($convId));
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $convId = (int) ($input['conversation_id'] ?? 0);
    if (!$convId) Response::error('Unterhaltung fehlt');
    $resolveConversation($convId);

    $body   = trim((string) ($input['body'] ?? ''));
    $attIds = array_values(array_filter(array_map('intval', (array)($input['attachment_ids'] ?? []))));
    if ($body === '' && !$attIds) Response::error('Nachricht darf nicht leer sein');
    if ($body === '') $body = '(Datei angehängt)';

    $role = $isCustomer ? 'customer' : 'team';
    $id = $svc->addComment($convId, $customerId, $userId, $role, $body);
    if ($attIds) $svc->linkAttachments($attIds, $id, $convId, $customerId);

    // Team antwortet -> KI in dieser Unterhaltung pausieren
    if ($role === 'team') $svc->setKiActive($convId, false, $userId);

    // Kunde fragt + KI aktiv -> automatische KI-Antwort
    $kiReplied = false;
    if ($role === 'customer' && $svc->kiActive($convId)) {
        try {
            $model    = (string) (\Core\Settings::get('default_model') ?: 'gpt-4');
            $provider = strpos($model, 'claude') !== false ? 'anthropic' : 'openai';
            $apiKey   = $provider === 'anthropic' ? (string) \Core\Settings::get('anthropic_api_key') : (string) \Core\Settings::get('openai_api_key');
            if ($apiKey !== '') {
                require_once SERVICES_PATH . '/AIService.php';
                $ai = new \Services\AIService($apiKey, $provider);
                $ai->setModel($model);
                $ai->setMaxTokens(800);

                $fileText = $svc->attachmentTextForKi($attIds, $customerId);
                $sys = "Du bist der digitale Projekt-Assistent von Thoxan Communications und beantwortest Fragen eines Kunden in dessen Kundenportal. "
                    . "Antworte freundlich, professionell und knapp auf Deutsch in der Sie-Form.\n\n"
                    . "Regeln:\n"
                    . "- Stütze Dich AUSSCHLIESSLICH auf die unten stehenden Projektinformationen und ggf. den Inhalt angehängter Dateien. Erfinde nichts.\n"
                    . "- Ist die Frage damit nicht beantwortbar, sage höflich, dass Du das an das Thoxan-Team weiterleitest, und bitte um etwas Geduld.\n"
                    . "- Gib niemals interne Daten, Zugangsdaten, Tool-Accounts oder Dokumente heraus.\n"
                    . "- Keine Zusagen zu Terminen oder Ergebnissen, die nicht in den Informationen stehen.\n\n"
                    . "Projektinformationen:\n" . $svc->aiContext($customerId)
                    . ($fileText !== '' ? "\n\nInhalt der vom Kunden angehängten Dateien:" . $fileText : '');

                $messages = [];
                foreach ($svc->comments($convId, 20) as $c) {
                    $messages[] = ['role' => $c['author_role'] === 'customer' ? 'user' : 'assistant', 'content' => (string) $c['body']];
                }
                $res = $ai->chat($messages, $sys);
                $reply = trim((string) ($res['content'] ?? ''));
                if ($reply !== '') { $svc->addComment($convId, $customerId, 0, 'ki', $reply); $kiReplied = true; }
            }
        } catch (\Throwable $e) { /* KI optional */ }
    }

    // Kundennachricht -> Team benachrichtigen
    if ($role === 'customer') {
        try {
            $recipients = $db->query(
                "SELECT DISTINCT u.email FROM users u JOIN user_customers uc ON uc.user_id = u.id
                 WHERE uc.customer_id = ? AND u.role <> 'customer' AND u.is_active = 1 AND u.email <> ''",
                [$customerId]
            ) ?: [];
            if (empty($recipients)) $recipients = $db->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 AND email <> ''") ?: [];
            if (!empty($recipients)) {
                require_once SERVICES_PATH . '/EmailService.php';
                $cust   = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
                $author = $db->queryOne("SELECT name FROM users WHERE id = ?", [$userId]);
                $appUrl = \Core\Brand::url();
                $link   = $appUrl . '/admin/customers/' . $customerId . '/portal';
                $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:560px;">'
                    . '<h2 style="color:#005da8;">Neue Rückfrage im Kundenportal</h2>'
                    . '<p><strong>' . htmlspecialchars($author['name'] ?? 'Ein Kunde') . '</strong> (' . htmlspecialchars($cust['name'] ?? ('Kunde #' . $customerId)) . ') schreibt:</p>'
                    . '<p style="border-left:3px solid #ddd;padding-left:10px;color:#555;">' . nl2br(htmlspecialchars(mb_substr($body, 0, 600))) . '</p>'
                    . '<p><a href="' . $link . '" style="display:inline-block;background:#005da8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">Im Kundenportal öffnen</a></p></div>';
                $mailer = \Services\EmailService::fromSettings($db);
                foreach ($recipients as $r) { $mailer->send($r['email'], 'Neue Rückfrage: ' . ($cust['name'] ?? 'Kunde'), $html); }
            }
        } catch (\Throwable $e) { /* Mail ist Bonus */ }
    }

    Response::success(['id' => $id, 'ki' => $kiReplied], 'Gesendet');
}

Response::error('Method not allowed', 405);
