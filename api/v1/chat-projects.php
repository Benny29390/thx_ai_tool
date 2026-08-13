<?php
/**
 * Chat Projects API — CRUD + Sharing + KI-Cluster
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$subAction = $_GET['sub_action'] ?? null;
$shareUserId = isset($_GET['share_user_id']) ? (int) $_GET['share_user_id'] : null;
$action = $_GET['action'] ?? null;
$userId = Auth::id();

// ===== KI-Cluster: Themen-Vorschläge für Chats eines Kontexts =====
if ($action === 'suggest' && $method === 'POST') {
    $contextScope = $input['context_scope'] ?? '';
    $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
    $apply = !empty($input['apply']);          // false = nur Vorschläge zurückgeben, true = direkt anwenden
    $approved = $input['approved'] ?? null;    // bei apply: Array der akzeptierten Cluster

    // Chats des Kontexts laden
    $sql = "SELECT c.id, c.title, c.project_id,
                (SELECT content FROM chat_conversation_messages
                 WHERE conversation_id = c.id AND role = 'user'
                 ORDER BY id ASC LIMIT 1) AS first_user_msg
            FROM chat_conversations c WHERE c.deleted_at IS NULL";
    $params = [];
    if ($contextScope === 'private')      { $sql .= " AND c.is_private = 1 AND c.user_id = ?"; $params[] = $userId; }
    elseif ($contextScope === 'projectwide') { $sql .= " AND c.is_private = 0 AND c.customer_id IS NULL"; }
    elseif ($contextScope === 'customer' && $customerId) { $sql .= " AND c.is_private = 0 AND c.customer_id = ?"; $params[] = $customerId; }
    else Response::error('Ungültiger context_scope');
    $sql .= " ORDER BY c.id DESC LIMIT 300";
    $chats = $db->query($sql, $params) ?: [];

    if (empty($chats)) Response::error('Keine Chats im Kontext gefunden');

    // ===== APPLY: Cluster aus dem Frontend → Ordner anlegen und Chats zuweisen =====
    if ($apply) {
        if (!is_array($approved) || empty($approved)) Response::error('Keine Cluster übergeben');
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#a78bfa', '#14b8a6', '#f97316', '#06b6d4'];
        $created = 0; $assigned = 0;
        foreach ($approved as $idx => $cluster) {
            $name = trim((string) ($cluster['name'] ?? ''));
            $chatIds = array_filter(array_map('intval', $cluster['chat_ids'] ?? []));
            if ($name === '' || empty($chatIds)) continue;

            $folderId = $db->insert('chat_projects', [
                'user_id' => $userId,
                'context_scope' => $contextScope,
                'customer_id' => ($contextScope === 'customer') ? $customerId : null,
                'name' => $name,
                'color' => $colors[$idx % count($colors)],
                'sort_order' => 10 + $idx,
            ]);
            $created++;

            $ph = implode(',', array_fill(0, count($chatIds), '?'));
            $db->execute("UPDATE chat_conversations SET project_id = ? WHERE id IN ($ph)", array_merge([$folderId], $chatIds));
            $assigned += count($chatIds);
        }
        Response::success(['folders_created' => $created, 'chats_assigned' => $assigned], 'KI-Vorschläge übernommen');
    }

    // ===== SUGGEST: LLM clustert die Chats =====
    $settingsRows = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings = []; foreach ($settingsRows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
    $settings = \Core\Settings::decryptMap($settings);
    $openaiKey = $settings['openai_api_key'] ?? '';
    if (empty($openaiKey)) Response::error('OpenAI API-Key nicht konfiguriert');

    // Kompaktes Input für das LLM
    $summary = array_map(function ($c) {
        $hint = trim((string) ($c['first_user_msg'] ?? ''));
        $hint = preg_replace('/\s+/', ' ', $hint);
        return [
            'id' => (int) $c['id'],
            'title' => $c['title'] ?: 'Ohne Titel',
            'preview' => mb_strimwidth($hint, 0, 140, '…'),
            'in_folder' => !empty($c['project_id']),
        ];
    }, $chats);

    require_once SERVICES_PATH . '/AIService.php';
    $ai = new \Services\AIService($openaiKey, 'openai');
    $ai->setModel('gpt-4o-mini');
    $ai->setMaxTokens(2500);

    $sys = "Du bist ein Cluster-Assistent für Chat-Übersichten. Du bekommst eine Liste von Chats "
         . "(Titel + Auszug der ersten User-Nachricht) und gruppierst sie in 3 bis 8 semantische "
         . "Themen-Cluster. Jedes Cluster bekommt einen prägnanten Namen (2 bis 4 Wörter, deutsch, "
         . "ohne Anführungszeichen). Jeder Chat sollte zu maximal einem Cluster gehören. Chats, die zu "
         . "niemandem passen, lass weg.\n\n"
         . "Antworte AUSSCHLIESSLICH mit JSON ohne Markdown:\n"
         . "{\"clusters\":[{\"name\":\"Newsletter\",\"chat_ids\":[1,2,3]}, ...]}";

    $userMsg = "Chats:\n" . json_encode($summary, JSON_UNESCAPED_UNICODE);

    try {
        $resp = $ai->chat([['role' => 'user', 'content' => $userMsg]], $sys);
        $raw = $resp['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) $raw = $m[0];
        $parsed = json_decode($raw, true);
        if (!is_array($parsed) || empty($parsed['clusters'])) throw new \RuntimeException('Antwort konnte nicht geparsed werden');

        // Cluster mit existierenden Chat-Daten anreichern
        $byId = [];
        foreach ($chats as $c) $byId[$c['id']] = $c;
        $clusters = [];
        foreach ($parsed['clusters'] as $cl) {
            $name = trim((string) ($cl['name'] ?? ''));
            $ids = array_values(array_unique(array_map('intval', $cl['chat_ids'] ?? [])));
            $valid = array_values(array_filter($ids, fn($i) => isset($byId[$i])));
            if ($name === '' || empty($valid)) continue;
            $clusters[] = [
                'name' => $name,
                'chat_ids' => $valid,
                'chats' => array_map(fn($i) => [
                    'id' => $i,
                    'title' => $byId[$i]['title'] ?: 'Ohne Titel',
                ], $valid),
            ];
        }
        Response::success(['clusters' => $clusters, 'total_chats' => count($chats)]);
    } catch (\Throwable $e) {
        Response::error('KI-Cluster fehlgeschlagen: ' . $e->getMessage());
    }
}

// ===== Share sub-actions =====
if ($id && $subAction === '/share') {
    $project = $db->queryOne("SELECT * FROM chat_projects WHERE id = ?", [$id]);
    if (!$project || $project['user_id'] !== $userId) {
        Response::forbidden('Kein Zugriff auf dieses Projekt');
    }

    // GET shares
    if ($method === 'GET') {
        $shares = $db->query(
            "SELECT cs.*, u.name as user_name, u.email as user_email
             FROM chat_shares cs
             JOIN users u ON u.id = cs.shared_with
             WHERE cs.share_type = 'project' AND cs.target_id = ?",
            [$id]
        );
        Response::success($shares);
    }

    // POST add share
    if ($method === 'POST') {
        $targetUserId = (int) ($input['user_id'] ?? 0);
        $permission = in_array($input['permission'] ?? '', ['read', 'write']) ? $input['permission'] : 'read';

        if (!$targetUserId || $targetUserId === $userId) {
            Response::error('Ungueltige Benutzer-ID');
        }

        $existing = $db->queryOne(
            "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ?",
            [$targetUserId, $id]
        );
        if ($existing) {
            $db->update('chat_shares', ['permission' => $permission], 'id = ?', [$existing['id']]);
        } else {
            $db->insert('chat_shares', [
                'shared_by' => $userId,
                'shared_with' => $targetUserId,
                'share_type' => 'project',
                'target_id' => $id,
                'permission' => $permission,
            ]);
        }
        Response::success(null, 'Freigabe gespeichert');
    }

    // DELETE remove share
    if ($method === 'DELETE' && $shareUserId) {
        $db->query(
            "DELETE FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ?",
            [$shareUserId, $id]
        );
        Response::success(null, 'Freigabe entfernt');
    }

    Response::error('Method not allowed', 405);
}

// ===== Single project by ID =====
if ($id) {
    $project = $db->queryOne("SELECT * FROM chat_projects WHERE id = ?", [$id]);
    if (!$project) {
        Response::notFound('Projekt nicht gefunden');
    }

    // Check access: owner or shared
    $hasAccess = $project['user_id'] === $userId;
    if (!$hasAccess) {
        $share = $db->queryOne(
            "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'project' AND target_id = ?",
            [$userId, $id]
        );
        $hasAccess = !!$share;
    }
    if (!$hasAccess) {
        Response::forbidden('Kein Zugriff');
    }

    // PUT update
    if ($method === 'PUT') {
        if ($project['user_id'] !== $userId) {
            Response::forbidden('Nur der Ersteller kann bearbeiten');
        }
        $data = [];
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (array_key_exists('description', $input)) $data['description'] = $input['description'];
        if (array_key_exists('color', $input)) $data['color'] = $input['color'];
        if (isset($input['sort_order'])) $data['sort_order'] = (int) $input['sort_order'];

        // parent_id mit Zirkel-Check
        if (array_key_exists('parent_id', $input)) {
            $newParentId = $input['parent_id'] ? (int) $input['parent_id'] : null;
            if ($newParentId == $id) {
                Response::error('Ordner kann nicht in sich selbst verschoben werden');
            }
            if ($newParentId) {
                $parent = $db->queryOne("SELECT id FROM chat_projects WHERE id = ? AND user_id = ?", [$newParentId, $userId]);
                if (!$parent) Response::error('Ungueltiger uebergeordneter Ordner');
                // Walk-up Check fuer Zyklen
                $check = $newParentId;
                while ($check) {
                    if ($check == $id) Response::error('Zirkulaere Referenz');
                    $anc = $db->queryOne("SELECT parent_id FROM chat_projects WHERE id = ?", [$check]);
                    $check = $anc && $anc['parent_id'] ? (int) $anc['parent_id'] : null;
                }
            }
            $data['parent_id'] = $newParentId;
        }

        if (!empty($data)) {
            $db->update('chat_projects', $data, 'id = ?', [$id]);
        }
        Response::success(null, 'Projekt aktualisiert');
    }

    // DELETE
    if ($method === 'DELETE') {
        if ($project['user_id'] !== $userId) {
            Response::forbidden('Nur der Ersteller kann loeschen');
        }
        // Alle Nachkommen sammeln (CASCADE loescht Kinder, aber nicht deren Shares)
        $descIds = [];
        $queue = [$id];
        while (!empty($queue)) {
            $cur = array_shift($queue);
            foreach ($db->query("SELECT id FROM chat_projects WHERE parent_id = ?", [$cur]) as $ch) {
                $descIds[] = $ch['id'];
                $queue[] = $ch['id'];
            }
        }
        $allIds = array_merge([$id], $descIds);
        $ph = implode(',', array_fill(0, count($allIds), '?'));
        $db->query("DELETE FROM chat_shares WHERE share_type = 'project' AND target_id IN ($ph)", $allIds);
        // CASCADE loescht Kinder automatisch, Conversations bekommen project_id = NULL via ON DELETE SET NULL
        $db->query("DELETE FROM chat_projects WHERE id = ?", [$id]);
        Response::success(null, 'Projekt geloescht');
    }

    // GET single
    if ($method === 'GET') {
        $project['conversation_count'] = (int) $db->queryValue(
            "SELECT COUNT(*) FROM chat_conversations WHERE project_id = ?", [$id]
        );
        Response::success($project);
    }

    Response::error('Method not allowed', 405);
}

// ===== Collection =====

// GET list
if ($method === 'GET') {
    $contextScope = $_GET['context_scope'] ?? null;
    $filterCustomerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

    $sql = "SELECT p.*,
            (SELECT COUNT(*) FROM chat_conversations WHERE project_id = p.id AND deleted_at IS NULL) as conversation_count,
            cs.permission as shared_permission,
            CASE WHEN p.user_id = ? THEN NULL ELSE u.name END as shared_by_name
         FROM chat_projects p
         LEFT JOIN chat_shares cs ON cs.share_type = 'project' AND cs.target_id = p.id AND cs.shared_with = ?
         LEFT JOIN users u ON u.id = p.user_id
         WHERE 1=1";
    $params = [$userId, $userId];

    if ($contextScope === 'customer' && $filterCustomerId) {
        $sql .= " AND p.context_scope = 'customer' AND p.customer_id = ?";
        $params[] = $filterCustomerId;
    } elseif ($contextScope === 'projectwide') {
        $sql .= " AND p.context_scope = 'projectwide'";
    } elseif ($contextScope === 'private') {
        $sql .= " AND p.context_scope = 'private' AND (p.user_id = ? OR cs.id IS NOT NULL)";
        $params[] = $userId;
    } else {
        // Fallback: alle eigenen oder gesharten (Backward-Compat)
        $sql .= " AND (p.user_id = ? OR cs.id IS NOT NULL)";
        $params[] = $userId;
    }
    $sql .= " ORDER BY p.sort_order ASC, p.name ASC";
    $projects = $db->query($sql, $params);

    // Rekursive Zaehlung: total_conversation_count inkl. Unterordner
    $byId = [];
    foreach ($projects as &$p) {
        $p['total_conversation_count'] = (int) ($p['conversation_count'] ?? 0);
        $byId[$p['id']] = &$p;
    }
    unset($p);
    $counted = [];
    $countFn = function ($pid) use (&$byId, &$projects, &$counted, &$countFn) {
        if (isset($counted[$pid])) return $counted[$pid];
        $c = (int) ($byId[$pid]['conversation_count'] ?? 0);
        foreach ($projects as $p) {
            if (($p['parent_id'] ?? null) == $pid) $c += $countFn($p['id']);
        }
        return $counted[$pid] = $c;
    };
    foreach ($projects as &$p) {
        $p['total_conversation_count'] = $countFn($p['id']);
    }
    unset($p);

    Response::success($projects);
}

// POST create
if ($method === 'POST') {
    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        Response::error('Name erforderlich');
    }

    $parentId = isset($input['parent_id']) ? (int) $input['parent_id'] : null;
    if ($parentId) {
        $parent = $db->queryOne("SELECT id FROM chat_projects WHERE id = ? AND user_id = ?", [$parentId, $userId]);
        if (!$parent) Response::error('Ungueltiger uebergeordneter Ordner');
    }

    $contextScope = in_array($input['context_scope'] ?? '', ['private','projectwide','customer'], true)
        ? $input['context_scope'] : 'private';
    $customerId = ($contextScope === 'customer' && !empty($input['customer_id']))
        ? (int) $input['customer_id'] : null;
    if ($contextScope === 'customer' && !$customerId) {
        Response::error('customer_id erforderlich bei context_scope=customer');
    }

    $projectId = $db->insert('chat_projects', [
        'user_id' => $userId,
        'context_scope' => $contextScope,
        'customer_id' => $customerId,
        'parent_id' => $parentId,
        'name' => $name,
        'description' => $input['description'] ?? null,
        'color' => $input['color'] ?? null,
        'sort_order' => (int) ($input['sort_order'] ?? 0),
    ]);

    Response::success(['id' => $projectId], 'Projekt erstellt');
}

Response::error('Method not allowed', 405);
