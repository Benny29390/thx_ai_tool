<?php
/**
 * Chat Conversations API — CRUD + Upload + Sharing + User Search
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input, $uri;

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$subAction = $_GET['sub_action'] ?? null;
$shareUserId = isset($_GET['share_user_id']) ? (int) $_GET['share_user_id'] : null;
$userId = Auth::id();

/**
 * Check if user can access a conversation (read)
 */
function canAccessConversation($db, int $convId, int $userId): ?array {
    $conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$convId]);
    if (!$conv) return null;

    // Gelöschte (im Papierkorb): nur Owner oder Admin
    if (!empty($conv['deleted_at'])) {
        if ($conv['user_id'] === $userId) return $conv;
        if (\Core\Auth::isAdmin()) return $conv;
        return null;
    }

    // Owner immer
    if ($conv['user_id'] === $userId) return $conv;

    // Privat-Chats: nur fuer Ersteller ODER wer eine Freigabe hat (read/write)
    if (!empty($conv['is_private'])) {
        return chatShareLevel($db, $convId, (int)$conv['user_id'], (int)($conv['project_id'] ?? 0), $userId) ? $conv : null;
    }

    // Alle nicht-privaten Chats sind fuer alle authentifizierten User lesbar
    return $conv;
}

/**
 * Liefert das hoechste Freigabe-Level eines Users fuer eine Konversation:
 * 'write' > 'read' > null. Beruecksichtigt direkte Conversation-Shares und
 * Projekt-Shares. Owner wird hier NICHT geprueft (das macht der Aufrufer).
 */
function chatShareLevel($db, int $convId, int $ownerId, int $projectId, int $userId): ?string {
    // Modell ist write-only: JEDE Freigabe (Conversation- oder Projekt-Share) bedeutet
    // Schreibzugriff. (Legacy- oder fehlerhafte 'read'/leere Werte zaehlen ebenfalls als write.)
    $has = $db->queryValue(
        "SELECT 1 FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ? LIMIT 1",
        [$userId, $convId]
    );
    if (!$has && $projectId) {
        $has = $db->queryValue(
            "SELECT 1 FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ? LIMIT 1",
            [$userId, $projectId]
        );
    }
    return $has ? 'write' : null;
}

/**
 * Check if user can write to a conversation.
 * Owner ODER explizite Schreib-Freigabe (Conversation- oder Projekt-Share).
 * Identische Policy wie api/v1/chat-stream.php.
 */
function canWriteConversation($db, int $convId, int $userId): bool {
    $conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$convId]);
    if (!$conv) return false;

    // Owner immer
    if ($conv['user_id'] === $userId) return true;

    // „Fuer alle freigegeben": jeder, der den Chat sehen darf, darf schreiben —
    // aber nur, wenn der Kunde des Chats fuer den User freigeschaltet ist
    // (Chats ohne Kunde = projektuebergreifend, sichtbar fuer alle).
    if (!empty($conv['write_open']) && empty($conv['is_private'])) {
        $custId = (int)($conv['customer_id'] ?? 0);
        if ($custId === 0 || \Core\Auth::canAccessCustomer($custId)) return true;
    }

    // Sonst nur mit expliziter Schreib-Freigabe
    return chatShareLevel($db, $convId, (int)$conv['user_id'], (int)($conv['project_id'] ?? 0), $userId) === 'write';
}

// ===== Folder counts fuer Sidebar =====
if (preg_match('#^/chat/folders$#', $uri)) {
    // Privat-Count (nur eigene, ohne Papierkorb)
    $privateCount = (int) $db->queryValue(
        "SELECT COUNT(*) FROM chat_conversations WHERE is_private = 1 AND user_id = ? AND deleted_at IS NULL",
        [$userId]
    );

    // Projektuebergreifend (alle, customer NULL, nicht privat, ohne Papierkorb)
    $projectwideCount = (int) $db->queryValue(
        "SELECT COUNT(*) FROM chat_conversations WHERE is_private = 0 AND customer_id IS NULL AND deleted_at IS NULL"
    );

    // Pro Kunde — ohne Papierkorb. Non-Admin sieht nur seine effektive Liste
    // (direkt zugewiesen + ueber Rolle freigeschaltet — siehe Auth::loadUserCustomers).
    // Wir nehmen jetzt zusaetzlich `settings` mit, um die `tags` pro Kunde
    // im Frontend als Filter (Kunde / Eigenprojekt / Portal / Pro Bono / E-Commerce /
    // Affiliate) nutzen zu koennen — analog der Admin-Kundenliste.
    if (\Core\Auth::isAdmin()) {
        $perCustomer = $db->query(
            "SELECT cust.id, cust.name, cust.slug, cust.abbreviation, cust.settings,
                    COUNT(c.id) as chat_count
             FROM customers cust
             LEFT JOIN chat_conversations c ON c.customer_id = cust.id AND c.is_private = 0 AND c.deleted_at IS NULL
             WHERE cust.is_active = 1
             GROUP BY cust.id, cust.name, cust.slug, cust.abbreviation, cust.settings
             ORDER BY (cust.abbreviation IS NULL OR cust.abbreviation = ''), cust.abbreviation, cust.name"
        );
    } else {
        $myIds = array_map(fn($c) => (int)$c['id'], \Core\Auth::customers());
        if (empty($myIds)) {
            $perCustomer = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($myIds), '?'));
            $perCustomer = $db->query(
                "SELECT cust.id, cust.name, cust.slug, cust.abbreviation, cust.settings,
                        COUNT(c.id) as chat_count
                 FROM customers cust
                 LEFT JOIN chat_conversations c ON c.customer_id = cust.id AND c.is_private = 0 AND c.deleted_at IS NULL
                 WHERE cust.is_active = 1 AND cust.id IN ($placeholders)
                 GROUP BY cust.id, cust.name, cust.slug, cust.abbreviation, cust.settings
                 ORDER BY (cust.abbreviation IS NULL OR cust.abbreviation = ''), cust.abbreviation, cust.name",
                $myIds
            );
        }
    }

    // Tags aus settings auspacken, settings danach entfernen
    $allTagCounts = [];
    foreach ($perCustomer as &$c) {
        $s = json_decode($c['settings'] ?? '{}', true) ?: [];
        $tags = $s['tags'] ?? [];
        $c['tags'] = is_array($tags) ? array_values(array_filter($tags, fn($t) => is_string($t) && $t !== '')) : [];
        unset($c['settings']);
        foreach ($c['tags'] as $t) {
            $allTagCounts[$t] = ($allTagCounts[$t] ?? 0) + 1;
        }
    }
    unset($c);
    arsort($allTagCounts);

    Response::success([
        'private_count' => $privateCount,
        'projectwide_count' => $projectwideCount,
        'customers' => $perCustomer,
        'tag_counts' => $allTagCounts,
    ]);
}

// ===== User Search (for share dialog) =====
if (preg_match('#^/chat/users$#', $uri)) {
    $search = trim($_GET['search'] ?? '');
    if (strlen($search) < 2) {
        Response::success([]);
    }
    $users = $db->query(
        "SELECT id, name, email FROM users WHERE is_active = 1 AND id != ? AND (name LIKE ? OR email LIKE ?) LIMIT 10",
        [$userId, "%{$search}%", "%{$search}%"]
    );
    Response::success($users);
}

// ===== Zugriffsanfragen (Schreibzugriff fuer fremde Chats) =====
// POST /chat/conversations/{id}/access-request — Anfragender stellt eine Anfrage
if ($id && $subAction === '/access-request') {
    if ($method !== 'POST') Response::error('Method not allowed', 405);
    $conv = canAccessConversation($db, $id, $userId);
    if (!$conv) Response::forbidden('Kein Zugriff');
    if ((int)$conv['user_id'] === $userId) Response::error('Das ist Dein eigener Chat');
    if (canWriteConversation($db, $id, $userId)) Response::error('Du hast bereits Schreibzugriff');

    // Schon eine offene Anfrage?
    $existing = $db->queryValue(
        "SELECT id FROM chat_access_requests WHERE conversation_id = ? AND requester_id = ? AND status = 'pending' LIMIT 1",
        [$id, $userId]
    );
    if (!$existing) {
        $msg = trim((string)($input['message'] ?? ''));
        $db->insert('chat_access_requests', [
            'conversation_id' => $id,
            'requester_id'    => $userId,
            'owner_id'        => (int)$conv['user_id'],
            'status'          => 'pending',
            'message'         => $msg !== '' ? mb_substr($msg, 0, 500) : null,
        ]);
        // E-Mail an den Owner
        try {
            $owner = $db->queryOne("SELECT name, email FROM users WHERE id = ?", [(int)$conv['user_id']]);
            $me    = $db->queryOne("SELECT name FROM users WHERE id = ?", [$userId]);
            if (!empty($owner['email'])) {
                require_once SERVICES_PATH . '/EmailService.php';
                $appUrl = \Core\Brand::url();
                $link = $appUrl . '/chat/' . $id;
                $title = htmlspecialchars($conv['title'] ?: 'Chat #' . $id);
                $reqName = htmlspecialchars($me['name'] ?? 'Ein Teammitglied');
                $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:560px;">'
                    . '<h2 style="color:#005da8;">Zugriffsanfrage für Deinen Chat</h2>'
                    . '<p><strong>' . $reqName . '</strong> möchte Schreibzugriff auf Deinen Chat <strong>„' . $title . '"</strong>.</p>'
                    . ($msg !== '' ? '<p style="color:#555;border-left:3px solid #ddd;padding-left:10px;">' . htmlspecialchars($msg) . '</p>' : '')
                    . '<p>Öffne den Chat und gib über <em>Teilen</em> frei (oder lehne ab):</p>'
                    . '<p><a href="' . $link . '" style="display:inline-block;background:#005da8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">Chat öffnen</a></p>'
                    . '</div>';
                \Services\EmailService::fromSettings($db)->send($owner['email'], 'Zugriffsanfrage: ' . ($conv['title'] ?: 'Chat'), $html);
            }
        } catch (\Throwable $e) { /* Mail ist Bonus, kein Showstopper */ }
    }
    Response::success(['pending' => true], 'Anfrage gesendet');
}

// POST /chat/conversations/{id}/take-over — Admin verschafft sich sofort Schreibzugriff (ohne Freigabe)
if ($id && $subAction === '/take-over') {
    if ($method !== 'POST') Response::error('Method not allowed', 405);
    if (!Auth::isAdmin()) Response::forbidden('Nur Admins können einen Chat übernehmen');
    $conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$id]);
    if (!$conv) Response::notFound('Konversation nicht gefunden');
    if ((int)$conv['user_id'] === $userId) Response::error('Das ist Dein eigener Chat');

    // Schreib-Freigabe anlegen oder hochstufen
    $exists = $db->queryOne(
        "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
        [$userId, $id]
    );
    if ($exists) {
        $db->update('chat_shares', ['permission' => 'write'], 'id = ?', [$exists['id']]);
    } else {
        $db->insert('chat_shares', [
            'shared_by' => $userId, 'shared_with' => $userId,
            'share_type' => 'conversation', 'target_id' => $id, 'permission' => 'write',
        ]);
    }
    // Eine evtl. eigene offene Anfrage gilt damit als erledigt
    $db->execute(
        "UPDATE chat_access_requests SET status = 'approved', resolved_at = NOW(), resolved_by = ? WHERE conversation_id = ? AND requester_id = ? AND status = 'pending'",
        [$userId, $id, $userId]
    );
    // Audit-Log: Admin greift auf fremden Chat zu
    try {
        \Core\AuditLog::record('chat_conversation', (string)$id, 'takeover', [
            'owner_id' => (int)$conv['user_id'], 'title' => $conv['title'] ?? '',
        ]);
    } catch (\Throwable $e) { /* Audit ist Bonus */ }

    Response::success(['can_write' => true], 'Schreibzugriff übernommen');
}

// /chat/conversations/{id}/access-requests — Owner: auflisten / genehmigen / ablehnen
if ($id && $subAction === '/access-requests') {
    $conv = $db->queryOne("SELECT * FROM chat_conversations WHERE id = ?", [$id]);
    if (!$conv) Response::notFound('Konversation nicht gefunden');
    if ((int)$conv['user_id'] !== $userId) Response::forbidden('Nur der Ersteller kann Anfragen verwalten');

    // GET: offene Anfragen
    if ($method === 'GET') {
        $reqs = $db->query(
            "SELECT r.id, r.requester_id, r.message, r.created_at, u.name AS requester_name, u.email AS requester_email, u.abbreviation AS requester_abbreviation
             FROM chat_access_requests r JOIN users u ON u.id = r.requester_id
             WHERE r.conversation_id = ? AND r.status = 'pending' ORDER BY r.created_at ASC",
            [$id]
        );
        Response::success($reqs);
    }

    // POST {id}/access-requests/{rid} mit action approve|deny
    if ($method === 'POST') {
        $rid = isset($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0;
        $action = $input['action'] ?? '';
        if (!$rid || !in_array($action, ['approve', 'deny'], true)) Response::error('rid + action (approve|deny) erforderlich');
        $req = $db->queryOne("SELECT * FROM chat_access_requests WHERE id = ? AND conversation_id = ? AND status = 'pending'", [$rid, $id]);
        if (!$req) Response::notFound('Anfrage nicht gefunden');

        if ($action === 'approve') {
            // Freigabe = Schreibzugriff (Lesen kann ohnehin jeder, der den Chat sieht)
            $perm = 'write';
            $exists = $db->queryOne(
                "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
                [(int)$req['requester_id'], $id]
            );
            if ($exists) {
                $db->update('chat_shares', ['permission' => $perm], 'id = ?', [$exists['id']]);
            } else {
                $db->insert('chat_shares', [
                    'shared_by' => $userId, 'shared_with' => (int)$req['requester_id'],
                    'share_type' => 'conversation', 'target_id' => $id, 'permission' => $perm,
                ]);
            }
        }
        $db->update('chat_access_requests',
            ['status' => $action === 'approve' ? 'approved' : 'denied', 'resolved_at' => date('Y-m-d H:i:s'), 'resolved_by' => $userId],
            'id = ?', [$rid]
        );

        // E-Mail an den Anfragenden
        try {
            $requester = $db->queryOne("SELECT name, email FROM users WHERE id = ?", [(int)$req['requester_id']]);
            if (!empty($requester['email'])) {
                require_once SERVICES_PATH . '/EmailService.php';
                $appUrl = \Core\Brand::url();
                $link = $appUrl . '/chat/' . $id;
                $title = htmlspecialchars($conv['title'] ?: 'Chat #' . $id);
                $ok = $action === 'approve';
                $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:560px;">'
                    . '<h2 style="color:' . ($ok ? '#0a7a3b' : '#a33') . ';">Zugriffsanfrage ' . ($ok ? 'freigegeben' : 'abgelehnt') . '</h2>'
                    . '<p>Deine Anfrage zum Chat <strong>„' . $title . '"</strong> wurde ' . ($ok ? '<strong>freigegeben</strong> — Du kannst jetzt mitschreiben.' : 'abgelehnt.') . '</p>'
                    . ($ok ? '<p><a href="' . $link . '" style="display:inline-block;background:#005da8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">Chat öffnen</a></p>' : '')
                    . '</div>';
                \Services\EmailService::fromSettings($db)->send($requester['email'], 'Zugriffsanfrage ' . ($ok ? 'freigegeben' : 'abgelehnt'), $html);
            }
        } catch (\Throwable $e) { /* Bonus */ }

        Response::success(null, $action === 'approve' ? 'Freigegeben' : 'Abgelehnt');
    }
    Response::error('Method not allowed', 405);
}

// ===== Share sub-actions =====
if ($id && $subAction === '/share') {
    $conv = canAccessConversation($db, $id, $userId);
    if (!$conv) Response::forbidden('Kein Zugriff');
    // Wer den Chat sehen darf, darf auch sehen, WER schreiben darf (Transparenz).
    // Aendern (hinzufuegen/entfernen/„fuer alle freigeben") nur Ersteller oder Admin.
    $canManageShare = ($conv['user_id'] === $userId) || Auth::isAdmin();

    // GET: Schreibberechtigte + Owner-Info + write_open
    if ($method === 'GET') {
        $shares = $db->query(
            "SELECT cs.shared_with, cs.permission, u.name as user_name, u.email as user_email, u.abbreviation as user_abbreviation
             FROM chat_shares cs
             JOIN users u ON u.id = cs.shared_with
             WHERE cs.share_type = 'conversation' AND cs.target_id = ?
             ORDER BY u.name",
            [$id]
        );
        $owner = $db->queryOne("SELECT id, name, abbreviation FROM users WHERE id = ?", [(int)$conv['user_id']]);
        Response::success([
            'shares'      => $shares,
            'owner'       => $owner,
            'write_open'  => (int)($conv['write_open'] ?? 0) === 1,
            'can_manage'  => $canManageShare,
            'total_users' => (int) $db->queryValue("SELECT COUNT(*) FROM users WHERE is_active = 1"),
        ]);
    }

    if (!$canManageShare) Response::forbidden('Nur der Ersteller oder ein Admin kann Freigaben ändern');

    // POST: Schreib-Freigabe hinzufuegen ODER „fuer alle freigeben"-Schalter
    if ($method === 'POST') {
        // Toggle „alle duerfen schreiben"
        if (array_key_exists('write_open', $input)) {
            $db->update('chat_conversations', ['write_open' => $input['write_open'] ? 1 : 0], 'id = ?', [$id]);
            Response::success(['write_open' => (bool)$input['write_open']], 'Einstellung gespeichert');
        }

        $targetUserId = (int) ($input['user_id'] ?? 0);
        if (!$targetUserId || $targetUserId === (int)$conv['user_id']) {
            Response::error('Ungueltige Benutzer-ID');
        }
        // Freigabe = Schreibzugriff (Lesen kann ohnehin jeder, der den Chat sieht)
        $existing = $db->queryOne(
            "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
            [$targetUserId, $id]
        );
        if ($existing) {
            $db->update('chat_shares', ['permission' => 'write'], 'id = ?', [$existing['id']]);
        } else {
            $db->insert('chat_shares', [
                'shared_by' => $userId,
                'shared_with' => $targetUserId,
                'share_type' => 'conversation',
                'target_id' => $id,
                'permission' => 'write',
            ]);
        }
        Response::success(null, 'Schreibzugriff erteilt');
    }

    // DELETE remove share
    if ($method === 'DELETE' && $shareUserId) {
        $db->query(
            "DELETE FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
            [$shareUserId, $id]
        );
        Response::success(null, 'Freigabe entfernt');
    }

    Response::error('Method not allowed', 405);
}

// ===== Upload sub-action =====
if ($id && $subAction === '/upload') {
    if ($method !== 'POST') Response::error('Method not allowed', 405);

    if (!canWriteConversation($db, $id, $userId)) {
        Response::forbidden('Kein Schreibzugriff');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        Response::error('Keine Datei hochgeladen');
    }

    $file = $_FILES['file'];

    // Upload-Fehler pruefen
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMsg = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu gross (max. ' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_PARTIAL => 'Upload wurde unterbrochen',
            UPLOAD_ERR_NO_TMP_DIR => 'Server-Fehler: Temp-Verzeichnis fehlt',
            default => 'Upload-Fehler (Code: ' . $file['error'] . ')',
        };
        Response::error($errMsg);
    }
    require_once SERVICES_PATH . '/DocumentProcessor.php';
    $processor = new \Services\DocumentProcessor();

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $textExtractable = ['txt', 'md', 'pdf', 'docx', 'html', 'htm', 'csv'];

    // Maximale Dateigroesse: 20MB
    if ($file['size'] > 20 * 1024 * 1024) {
        Response::error('Datei zu gross (max. 20 MB)');
    }

    try {
        if (in_array($ext, $textExtractable)) {
            if ($ext === 'csv') {
                // CSV direkt als Text lesen
                $text = file_get_contents($file['tmp_name']);
                $wordCount = str_word_count($text);
            } else {
                $result = $processor->processFile($file['tmp_name'], $file['type'], $file['name']);
                $text = $result['text'];
                $wordCount = $result['word_count'];
            }
        } else {
            // Nicht-extrahierbare Dateien: Dateiname + Metadaten als Kontext
            $sizeKb = round($file['size'] / 1024);
            $text = "[Angehaengte Datei: {$file['name']} ({$sizeKb} KB, Typ: {$ext})]";
            $wordCount = 0;
        }

        // Conv-Daten für Knowledge-Übertragung
        $conv = $db->queryOne(
            "SELECT id, customer_id, title, is_private FROM chat_conversations WHERE id = ?",
            [$id]
        );

        // Datei automatisch ins Wissen ingesten — wenn extrahierbarer Text und nicht privat
        $knowledgeDocId = null;
        $kbStatus = null;
        if (!empty($conv) && empty($conv['is_private']) && mb_strlen(trim($text)) >= 100) {
            try {
                require_once API_PATH . '/v1/knowledge/_helpers.php';
                $services = knowledgeBuildServices($db);

                $contentHash = hash('sha256', trim($text));
                $existing = $services['knowledgeService']->findByContentHash($contentHash);

                if ($existing) {
                    $knowledgeDocId = (int) $existing['id'];
                    $kbStatus = 'duplicate';
                } else {
                    // Customer-Name für Extraction-Kontext
                    $customerName = null;
                    if (!empty($conv['customer_id'])) {
                        $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$conv['customer_id']]);
                        $customerName = $c['name'] ?? null;
                    }

                    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

                    $prepared = $services['ingestService']->prepare($text, [
                        'customer_name' => $customerName,
                    ]);

                    $extId = 'chat-upload:' . $id . ':' . substr($contentHash, 0, 12);
                    $title = $file['name'] . ' — Chat: ' . ($conv['title'] ?: 'Ohne Titel');
                    $overrides = [
                        'title' => $title,
                        'description' => $prepared['metadata']['description'],
                        'customer_id' => $conv['customer_id'] ?? null,
                        'category' => $prepared['metadata']['category'] ?? 'Chat-Upload',
                        'tags' => array_unique(array_merge(['chat-upload'], $prepared['metadata']['tags'] ?? [])),
                    ];
                    $meta = [
                        'source_type' => 'chat',
                        'source_ref' => 'chat-upload:' . $id . '/' . $file['name'],
                        'external_id' => $extId,
                        'created_by' => $userId,
                    ];
                    $knowledgeDocId = $services['ingestService']->commit($prepared, $overrides, $meta);
                    $kbStatus = 'created';
                }
            } catch (\Exception $e) {
                error_log('Chat-Upload Knowledge-Ingest fehlgeschlagen: ' . $e->getMessage());
                $kbStatus = 'failed';
            }
        }

        Response::success([
            'filename' => $file['name'],
            'extracted_text' => $text,
            'word_count' => $wordCount,
            'knowledge_document_id' => $knowledgeDocId,
            'knowledge_status' => $kbStatus,
        ]);
    } catch (\Exception $e) {
        Response::error('Fehler beim Verarbeiten: ' . $e->getMessage());
    }
}

// ===== Single conversation by ID =====
if ($id) {
    $conv = canAccessConversation($db, $id, $userId);
    if (!$conv) {
        Response::notFound('Konversation nicht gefunden');
    }

    // GET with messages (inkl. Sender-Daten für User-Messages)
    if ($method === 'GET') {
        $messages = $db->query(
            "SELECT m.*, u.name AS sender_name, u.abbreviation AS sender_abbreviation
             FROM chat_conversation_messages m
             LEFT JOIN users u ON u.id = m.sender_user_id
             WHERE m.conversation_id = ? ORDER BY m.created_at ASC",
            [$id]
        );
        foreach ($messages as &$msg) {
            if ($msg['attachments']) {
                $msg['attachments'] = json_decode($msg['attachments'], true);
            }
            if (!empty($msg['artifacts_used'])) {
                $msg['artifacts_used'] = json_decode($msg['artifacts_used'], true);
            }
        }
        unset($msg);

        // Share info
        $shareCount = (int) $db->queryValue(
            "SELECT COUNT(*) FROM chat_shares WHERE share_type = 'conversation' AND target_id = ?",
            [$id]
        );

        $conv['messages'] = $messages;
        $conv['share_count'] = $shareCount;
        $conv['is_owner'] = $conv['user_id'] === $userId;
        $conv['can_write'] = canWriteConversation($db, $id, $userId);
        $conv['write_open'] = (int)($conv['write_open'] ?? 0) === 1;
        $conv['can_manage_share'] = ($conv['user_id'] === $userId) || \Core\Auth::isAdmin();

        // Eigentuemer-Infos fuers „Chat von [Name]"-Banner
        $owner = $db->queryOne("SELECT name, abbreviation, email FROM users WHERE id = ?", [(int)$conv['user_id']]);
        $conv['owner_name'] = $owner['name'] ?? 'Unbekannt';
        $conv['owner_abbreviation'] = $owner['abbreviation'] ?? '';

        if (!$conv['is_owner']) {
            // Habe ich schon eine offene Zugriffsanfrage gestellt?
            $conv['access_request_pending'] = (bool) $db->queryValue(
                "SELECT 1 FROM chat_access_requests WHERE conversation_id = ? AND requester_id = ? AND status = 'pending' LIMIT 1",
                [$id, $userId]
            );
        } else {
            // Als Owner: Anzahl offener eingehender Anfragen fuer DIESEN Chat
            $conv['pending_access_requests'] = (int) $db->queryValue(
                "SELECT COUNT(*) FROM chat_access_requests WHERE conversation_id = ? AND status = 'pending'",
                [$id]
            );
        }
        Response::success($conv);
    }

    // PUT update
    if ($method === 'PUT') {
        if (!canWriteConversation($db, $id, $userId)) {
            Response::forbidden('Kein Schreibzugriff');
        }
        $data = [];
        if (isset($input['title'])) $data['title'] = trim($input['title']);
        if (array_key_exists('model', $input)) $data['model'] = $input['model'];
        if (array_key_exists('project_id', $input)) $data['project_id'] = $input['project_id'] ? (int) $input['project_id'] : null;
        if (array_key_exists('customer_id', $input)) {
            $newCustomerId = $input['customer_id'] ? (int) $input['customer_id'] : null;
            $data['customer_id'] = $newCustomerId;
            // Wenn ein Kunde zugewiesen wird → automatisch nicht-privat (sonst wäre er nicht sichtbar im Kunden-Kontext)
            if ($newCustomerId && !empty($conv['is_private'])) {
                $data['is_private'] = 0;
            }
            // Beim Kunden-Wechsel: ggf. den im Chat-Kontext liegenden Ordner zurücksetzen,
            // weil Ordner pro Kunden-Kontext gespeichert sind
            $data['project_id'] = null;

            // Verknüpftes Wissens-Dokument (Chat-Transfer) mitziehen, wenn customer_id wechselt
            if ((int) ($conv['customer_id'] ?? 0) !== (int) ($newCustomerId ?? 0)) {
                try {
                    $kbId = $db->queryValue(
                        "SELECT id FROM knowledge_documents WHERE source_type = 'chat' AND external_id = ?",
                        ['chat:' . $id]
                    );
                    if ($kbId) {
                        $db->update('knowledge_documents', ['customer_id' => $newCustomerId], 'id = ?', [(int) $kbId]);
                        // Chunks/Embeddings auch nachziehen
                        $db->execute("UPDATE knowledge_chunks SET customer_id = ? WHERE document_id = ?", [$newCustomerId, (int) $kbId]);
                        $db->execute("UPDATE knowledge_embeddings SET customer_id = ? WHERE chunk_id IN (SELECT id FROM knowledge_chunks WHERE document_id = ?)", [$newCustomerId, (int) $kbId]);
                    }
                } catch (\Throwable $e) {
                    error_log('Chat→Wissen customer_id sync failed: ' . $e->getMessage());
                }
            }
        }
        if (array_key_exists('is_private', $input)) $data['is_private'] = $input['is_private'] ? 1 : 0;
        if (array_key_exists('system_prompt', $input)) $data['system_prompt'] = $input['system_prompt'];
        if (isset($input['is_pinned'])) $data['is_pinned'] = (int) $input['is_pinned'];

        if (!empty($data)) {
            $db->update('chat_conversations', $data, 'id = ?', [$id]);
        }
        Response::success(null, 'Konversation aktualisiert');
    }

    // POST /chat/conversations/{id}/restore — aus Papierkorb wiederherstellen
    if ($method === 'POST' && ($subAction ?? '') === '/restore') {
        $isAdmin = \Core\Auth::isAdmin();
        if ($conv['user_id'] !== $userId && !$isAdmin) {
            Response::forbidden('Nur der Ersteller oder ein Admin kann wiederherstellen');
        }
        if (empty($conv['deleted_at'])) {
            Response::error('Chat ist nicht im Papierkorb');
        }
        $db->execute("UPDATE chat_conversations SET deleted_at = NULL, deleted_by = NULL WHERE id = ?", [$id]);
        Response::success(null, 'Chat wiederhergestellt');
    }

    // DELETE — Soft-Delete (Papierkorb) ODER endgültig (?force=1)
    if ($method === 'DELETE') {
        $isAdmin = \Core\Auth::isAdmin();
        if ($conv['user_id'] !== $userId && !$isAdmin) {
            Response::forbidden('Nur der Ersteller oder ein Admin kann löschen');
        }
        $force = !empty($_GET['force']);

        // Verknüpftes Wissens-Dokument entfernen (gilt für beide Modi)
        $db->query("DELETE FROM knowledge_documents WHERE source_type = 'chat' AND external_id = ?", ['chat:' . $id]);

        if ($force || !empty($conv['deleted_at'])) {
            // Endgültig löschen — entweder ?force=1 vom Admin oder Chat war schon im Papierkorb
            $db->query("DELETE FROM chat_shares WHERE share_type = 'conversation' AND target_id = ?", [$id]);
            $db->query("DELETE FROM chat_conversations WHERE id = ?", [$id]);
            Response::success(null, 'Chat endgültig gelöscht');
        }

        // Soft-Delete: in Papierkorb
        $db->execute("UPDATE chat_conversations SET deleted_at = NOW(), deleted_by = ? WHERE id = ?", [$userId, $id]);
        Response::success(null, 'Chat in Papierkorb verschoben');
    }

    Response::error('Method not allowed', 405);
}

// ===== Collection =====

// GET list
if ($method === 'GET') {
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
    $search = trim($_GET['search'] ?? '');

    // Neue Logik: alle Chats fuer alle User sichtbar — bis auf privat (nur eigene)
    // Filter: ?visibility=private|projectwide|customer|all  + ?customer_id  + ?creator_id
    $visibility = $_GET['visibility'] ?? 'all';
    $filterCustomerId = $_GET['customer_id'] ?? null;
    $creatorId = isset($_GET['creator_id']) ? (int) $_GET['creator_id'] : null;

    // Auto-Cleanup: Trash älter als 1 Jahr endgültig entfernen (Best-Effort, kein Fehler-Blocker)
    try {
        $db->execute("DELETE FROM chat_conversations WHERE deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL 1 YEAR) LIMIT 50");
    } catch (\Throwable $e) { /* ignore */ }

    $isAdmin = \Core\Auth::isAdmin();
    $trashMode = ($visibility === 'trash');

    $sql = "SELECT c.*,
                (SELECT content FROM chat_conversation_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_preview,
                (SELECT COUNT(*) FROM chat_conversation_messages WHERE conversation_id = c.id) as message_count,
                (SELECT COUNT(*) FROM chat_shares WHERE share_type = 'conversation' AND target_id = c.id) as share_count,
                (SELECT COUNT(*) FROM chat_conversation_messages WHERE conversation_id = c.id AND attachments IS NOT NULL AND attachments != 'null') as attachment_count,
                CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_owner,
                u.name as creator_name,
                u.email as creator_email,
                u.abbreviation as creator_abbreviation,
                du.name as deleted_by_name,
                cust.name as customer_name,
                cust.slug as customer_slug,
                cust.abbreviation as customer_abbreviation
            FROM chat_conversations c
            LEFT JOIN users u ON u.id = c.user_id
            LEFT JOIN users du ON du.id = c.deleted_by
            LEFT JOIN customers cust ON cust.id = c.customer_id
            WHERE 1=1";

    $params = [$userId];

    // Soft-Delete-Filter
    if ($trashMode) {
        $sql .= " AND c.deleted_at IS NOT NULL";
        // Nicht-Admins sehen im Papierkorb nur eigene Chats
        if (!$isAdmin) {
            $sql .= " AND c.user_id = ?";
            $params[] = $userId;
        }
    } else {
        $sql .= " AND c.deleted_at IS NULL";
    }

    // Privat-Regel: private Chats nur fuer Ersteller sichtbar
    $sql .= " AND (c.is_private = 0 OR c.user_id = ?)";
    $params[] = $userId;

    // Visibility-Filter
    if ($visibility === 'private') {
        $sql .= " AND c.is_private = 1 AND c.user_id = ?";
        $params[] = $userId;
    } elseif ($visibility === 'projectwide') {
        $sql .= " AND c.is_private = 0 AND c.customer_id IS NULL";
    } elseif ($visibility === 'customer' && $filterCustomerId !== null && $filterCustomerId !== '') {
        $sql .= " AND c.is_private = 0 AND c.customer_id = ?";
        $params[] = (int) $filterCustomerId;
    }

    if ($creatorId) {
        $sql .= " AND c.user_id = ?";
        $params[] = $creatorId;
    }

    if ($projectId !== null) {
        $sql .= " AND c.project_id = ?";
        $params[] = $projectId;
    }

    if ($search) {
        $sql .= " AND c.title LIKE ?";
        $params[] = "%{$search}%";
    }

    $sql .= " ORDER BY c.is_pinned DESC, c.updated_at DESC";

    $conversations = $db->query($sql, $params);

    // Truncate preview
    foreach ($conversations as &$c) {
        if ($c['last_message_preview'] && mb_strlen($c['last_message_preview']) > 100) {
            $c['last_message_preview'] = mb_substr($c['last_message_preview'], 0, 100) . '...';
        }
    }
    unset($c);

    Response::success($conversations);
}

// POST create
if ($method === 'POST') {
    $isPrivate = !empty($input['is_private']) ? 1 : 0;
    $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
    $isProjectwide = array_key_exists('projectwide', $input) ? !empty($input['projectwide']) : false;

    // Pflicht: ENTWEDER customer_id ODER is_private ODER projectwide
    if (!$customerId && !$isPrivate && !$isProjectwide) {
        Response::error('Bitte einen Kunden waehlen, "Privat" oder "Projektuebergreifend" als Kontext angeben');
    }

    $convId = $db->insert('chat_conversations', [
        'user_id' => $userId,
        'title' => trim($input['title'] ?? 'Neuer Chat'),
        'model' => $input['model'] ?? null,
        'project_id' => !empty($input['project_id']) ? (int) $input['project_id'] : null,
        'customer_id' => $customerId,
        'is_private' => $isPrivate,
        'system_prompt' => $input['system_prompt'] ?? null,
    ]);

    Response::success(['id' => $convId], 'Chat erstellt');
}

Response::error('Method not allowed', 405);
