<?php
/**
 * Customer Cards — REST-Endpoints für Steckbrief-Widgets
 *
 * GET    /admin/customers/{id}/cards
 * POST   /admin/customers/{id}/cards                   { type, title }
 * POST   /admin/customers/{id}/cards/reorder           { ids:[] }
 * PUT    /admin/customers/{id}/cards/{cardId}          { title?, body?, is_collapsed? }
 * DELETE /admin/customers/{id}/cards/{cardId}
 * POST   /admin/customers/{id}/cards/{cardId}/files    multipart (file, title?)
 * DELETE /admin/customers/{id}/cards/{cardId}/files/{fileId}
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\CustomerCardService;

require_once SERVICES_PATH . '/CustomerCardService.php';
require_once SERVICES_PATH . '/DocumentProcessor.php';
require_once API_PATH . '/v1/knowledge/_helpers.php';

$db = Database::getInstance();
$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if (!Auth::isAdmin() && !Auth::isManager()) {
    Response::forbidden();
}

$customerId = (int) ($_GET['customer_id'] ?? 0);
$cardId = (int) ($_GET['card_id'] ?? 0);
$fileId = (int) ($_GET['file_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($customerId <= 0) {
    Response::error('Customer-ID erforderlich');
}
$customer = $db->queryOne("SELECT id, name FROM customers WHERE id = ?", [$customerId]);
if (!$customer) Response::notFound('Kunde nicht gefunden');

// Service mit Knowledge-Sync (außer für reines Listing — spart OpenAI-Init)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$needsIngest = !($method === 'GET' || (($action === 'reorder' || $action === 'kanban') && $method === 'POST'));

$ingest = null;
if ($needsIngest) {
    try {
        $services = knowledgeBuildServices($db);
        $ingest = $services['ingestService'];
    } catch (\Throwable $e) {
        // Wenn OpenAI nicht da ist: Cards funktionieren weiterhin, nur Knowledge-Sync entfällt
        $ingest = null;
    }
}
$service = new CustomerCardService($db, $ingest);

try {
    // ===== Versions (List / Restore) =====
    if ($cardId > 0 && $action === 'versions') {
        $card = $service->get($cardId);
        if (!$card || (int) $card['customer_id'] !== $customerId) Response::notFound('Card nicht gefunden');

        $versionId = (int) ($_GET['version_id'] ?? 0);
        if ($method === 'GET') {
            if ($versionId > 0) {
                $v = $service->getVersion($versionId);
                if (!$v || (int) $v['card_id'] !== $cardId) Response::notFound('Version nicht gefunden');
                Response::success($v);
            }
            Response::success(['versions' => $service->listVersions($cardId)]);
        }
        if ($method === 'POST' && $versionId > 0) {
            $service->restoreVersion($cardId, $versionId, $userId);
            Response::success($service->get($cardId), 'Version wiederhergestellt');
        }
        Response::error('Methode nicht unterstützt');
    }

    // ===== File-Upload / -Delete =====
    if ($cardId > 0 && $action === 'files') {
        $card = $service->get($cardId);
        if (!$card || (int) $card['customer_id'] !== $customerId) {
            Response::notFound('Card nicht gefunden');
        }

        if ($method === 'POST') {
            if (empty($_FILES['file'])) Response::error('Keine Datei hochgeladen');
            $title = isset($_POST['title']) ? trim((string) $_POST['title']) : null;
            $row = $service->addFile($cardId, $_FILES['file'], $title, $userId);
            Response::success($row, 'Datei hochgeladen');
        }
        if ($method === 'DELETE' && $fileId > 0) {
            $service->deleteFile($fileId);
            Response::success(null, 'Datei gelöscht');
        }
        Response::error('Methode nicht unterstützt');
    }

    // ===== KI-Suche: durchsucht alle Cards + Profil + Chats des Kunden =====
    if ($action === 'ai-search' && $method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $query = trim((string) ($payload['query'] ?? ''));
        if ($query === '') Response::error('Suchanfrage erforderlich');

        // Settings + AI
        $settingsRows = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($settingsRows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $openaiKey = $settings['openai_api_key'] ?? '';
        if (empty($openaiKey)) Response::error('OpenAI API-Key nicht konfiguriert');

        // Kontext sammeln
        $customerRow = $db->queryOne(
            "SELECT name, slug, abbreviation, industry, description, target_audience, products_services, unique_selling_points, tone_of_voice, brand_values, website FROM customers WHERE id = ?",
            [$customerId]
        ) ?: [];

        $allCards = $service->listForCustomer($customerId);
        $cardsContext = [];
        foreach ($allCards as $c) {
            $body = $c['body_decoded'] ?? [];
            $snippet = '';
            switch ($c['type']) {
                case 'richtext':
                    $snippet = trim(strip_tags($body['html'] ?? ''));
                    break;
                case 'links':
                    $items = $body['items'] ?? [];
                    $snippet = implode("\n", array_map(fn($it) => ($it['title'] ?? '') . ': ' . ($it['url'] ?? ''), $items));
                    break;
                case 'brand':
                    $colors = array_map(fn($x) => ($x['name'] ?? '') . ' ' . ($x['value'] ?? ''), $body['colors'] ?? []);
                    $fonts = array_map(fn($x) => ($x['name'] ?? '') . (!empty($x['note']) ? ' — ' . $x['note'] : ''), $body['fonts'] ?? []);
                    $snippet = 'Farben: ' . implode(', ', $colors) . "\nSchriften: " . implode(', ', $fonts);
                    if (!empty($body['note'])) $snippet .= "\n" . $body['note'];
                    break;
                case 'contacts':
                    $lines = [];
                    foreach (($body['groups'] ?? []) as $g) {
                        $lines[] = '## ' . ($g['title'] ?? '');
                        foreach (($g['people'] ?? []) as $p) {
                            $lines[] = '- ' . trim(($p['role'] ?? '') . ': ' . ($p['name'] ?? '') . (!empty($p['initials']) ? ' (' . $p['initials'] . ')' : '') . (!empty($p['email']) ? ' · ' . $p['email'] : ''));
                        }
                    }
                    $snippet = implode("\n", $lines);
                    break;
                case 'documents':
                case 'images':
                    $files = $c['files'] ?? [];
                    $snippet = 'Dateien: ' . implode(', ', array_map(fn($f) => $f['file_name'] ?? '', $files));
                    break;
                case 'kpi':
                    $kpiLines = [];
                    foreach (($body['items'] ?? []) as $it) {
                        $kpiLines[] = trim(($it['label'] ?? '') . ': ' . ($it['value'] ?? '')
                                       . (!empty($it['period']) ? ' (' . $it['period'] . ')' : '')
                                       . (!empty($it['target']) ? ' — Ziel: ' . $it['target'] : ''));
                    }
                    $snippet = implode("\n", $kpiLines);
                    break;
                case 'tracking_status':
                    $trkLines = [];
                    $stMap = ['ok' => 'aktiv', 'fehlt' => 'fehlt', 'tbd' => 'offen', 'na' => 'n/a'];
                    foreach (($body['items'] ?? []) as $it) {
                        $st = $stMap[$it['status'] ?? 'tbd'] ?? 'offen';
                        $trkLines[] = '[' . $st . '] ' . ($it['label'] ?? '') . (!empty($it['note']) ? ' — ' . $it['note'] : '');
                    }
                    $snippet = implode("\n", $trkLines);
                    break;
            }
            if (!empty($c['is_system']) && $c['system_key'] === 'profile') continue; // Profil getrennt
            if (!empty($c['is_system']) && $c['system_key'] === 'knowledge') continue; // Wissens-Liste übergehen wir
            if (!empty($c['is_system']) && $c['system_key'] === 'asana') continue;
            if (trim($snippet) === '') continue;
            $cardsContext[] = [
                'id' => (int) $c['id'],
                'title' => $c['title'],
                'type' => $c['type'],
                'snippet' => mb_substr($snippet, 0, 1500),
            ];
        }

        // Letzte 10 Chats des Kunden
        $chatRows = [];
        try {
            $chatRows = $db->query(
                "SELECT c.id, c.title, c.created_at FROM chat_conversations c
                 WHERE c.customer_id = ? AND c.archived_at IS NULL
                 ORDER BY c.updated_at DESC LIMIT 10",
                [$customerId]
            ) ?: [];
        } catch (\Throwable $e) { /* table-name evtl. anders */ }
        $chatContext = [];
        foreach ($chatRows as $cv) {
            try {
                $messages = $db->query(
                    "SELECT role, content FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 20",
                    [(int) $cv['id']]
                ) ?: [];
                $text = '';
                foreach ($messages as $m) {
                    $text .= "[" . $m['role'] . "] " . trim(strip_tags((string) $m['content'])) . "\n";
                }
                $chatContext[] = [
                    'id' => (int) $cv['id'],
                    'title' => $cv['title'] ?: 'Chat #' . $cv['id'],
                    'created_at' => $cv['created_at'],
                    'snippet' => mb_substr(trim($text), 0, 800),
                ];
            } catch (\Throwable $e) { continue; }
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($openaiKey, 'openai');
        $ai->setModel('gpt-4o-mini');
        $ai->setMaxTokens(1200);

        $contextJson = json_encode([
            'customer' => $customerRow,
            'cards' => $cardsContext,
            'chats' => $chatContext,
        ], JSON_UNESCAPED_UNICODE);

        $sys = "Du bist semantische Suche für ein Kundensteckbrief-Tool. Der User stellt eine Frage zu einem Kunden. "
             . "Du bekommst das Profil, alle Steckbrief-Cards und die letzten Chats. "
             . "Antworte präzise auf Deutsch in maximal 6 Sätzen. "
             . "Verweise auf die relevante Quelle (Card-Titel oder Chat-Titel). "
             . "Wenn keine Information zur Frage vorliegt, sag das ehrlich. "
             . "Wichtig: echte Umlaute (ä ö ü ß).";

        $userMsg = "Frage: " . $query . "\n\nKontext:\n" . $contextJson;

        try {
            $resp = $ai->chat([['role' => 'user', 'content' => $userMsg]], $sys);
            $answer = $resp['content'] ?? '';
        } catch (\Throwable $e) {
            Response::error('KI-Suche fehlgeschlagen: ' . $e->getMessage());
        }

        // Lokale Match-Liste: Cards + Chats, deren Snippet das Query enthält (case-insensitive)
        $q = mb_strtolower($query);
        $matches = [];
        foreach ($cardsContext as $c) {
            if (mb_strpos(mb_strtolower($c['title'] . ' ' . $c['snippet']), $q) !== false) {
                $matches[] = ['type' => 'card', 'id' => $c['id'], 'title' => $c['title'], 'snippet' => mb_substr($c['snippet'], 0, 200)];
            }
        }
        foreach ($chatContext as $ch) {
            if (mb_strpos(mb_strtolower($ch['title'] . ' ' . $ch['snippet']), $q) !== false) {
                $matches[] = ['type' => 'chat', 'id' => $ch['id'], 'title' => $ch['title'], 'snippet' => mb_substr($ch['snippet'], 0, 200)];
            }
        }

        Response::success([
            'query' => $query,
            'answer' => $answer,
            'matches' => array_slice($matches, 0, 12),
            'context' => ['cards_count' => count($cardsContext), 'chats_count' => count($chatContext)],
        ]);
    }

    // ===== Auto-Import (Word/PDF/TXT → Cards + Profil) =====
    if ($action === 'auto-import' && $method === 'POST') {
        if (empty($_FILES['file'])) Response::error('Keine Datei hochgeladen');
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) Response::error('Upload-Fehler');
        if ($file['size'] > 50 * 1024 * 1024) Response::error('Datei zu groß (max 50 MB)');

        // Settings + AI-Service
        $settingsRows = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($settingsRows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $openaiKey = $settings['openai_api_key'] ?? '';
        if (empty($openaiKey)) Response::error('OpenAI API-Key nicht konfiguriert');

        // Text aus Datei extrahieren
        require_once SERVICES_PATH . '/DocumentProcessor.php';
        $proc = new \Services\DocumentProcessor();
        $mime = function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : 'application/octet-stream';
        try {
            $extracted = $proc->processFile($file['tmp_name'], $mime, $file['name']);
            $text = trim($extracted['text'] ?? '');
        } catch (\Throwable $e) {
            Response::error('Textextraktion fehlgeschlagen: ' . $e->getMessage());
        }
        if (mb_strlen($text) < 100) Response::error('Aus dieser Datei konnte kein verwertbarer Text extrahiert werden');
        // Sicherheitsbegrenzung
        if (mb_strlen($text) > 30000) $text = mb_substr($text, 0, 30000) . "\n[...]";

        // Aktuelle Cards für Merge-Vorschläge
        $existingCards = $service->listForCustomer($customerId);
        $cardOverview = array_values(array_filter(array_map(function ($c) {
            return [
                'id' => (int) $c['id'],
                'title' => $c['title'],
                'type' => $c['type'],
                'is_system' => !empty($c['is_system']),
                'system_key' => $c['system_key'],
            ];
        }, $existingCards), function ($c) { return !$c['is_system']; }));

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($openaiKey, 'openai');
        $ai->setModel('gpt-4o-mini');
        $ai->setMaxTokens(4000);

        $sys = "Du bist Steckbrief-Importer für ein Kunden-Dashboard. Du bekommst extrahierten Text aus einem "
             . "Word/PDF-Steckbrief und die Liste der bereits vorhandenen User-Cards. Deine Aufgabe: den Inhalt "
             . "sinnvoll auf Cards verteilen.\n\n"
             . "Card-Typen:\n"
             . "- richtext: formatierter Text mit <h2><h3><p><ul><ol><li><strong><a> — für Notizen, Sektionen, Beschreibungen\n"
             . "- links: URL-Sammlung mit Titel — IMMER nutzen wenn eine Sektion eine LISTE benannter Quick-Links/Zugänge enthält (z.B. Looker Studio, Asana-Board, Google Ads), auch wenn die URL als [???] oder noch offen markiert ist\n"
             . "- contacts: Personen-/Ansprechpartner-Listen mit Rolle, Name, Kürzel, E-Mail, Telefon — IMMER nutzen für Sektionen wie Ansprechpartner, Team, Kontakte, auch mit Gruppen wie Intern und Kundenseitig\n"
             . "- brand: Markenidentität mit Farben (hex) und Schriftarten\n\n"
             . "Profil-Felder (kommen NICHT als Cards, sondern in profile_updates):\n"
             . "- description (Kurzbeschreibung des Kunden)\n"
             . "- target_audience (Zielgruppe)\n"
             . "- products_services (Produkte/Services)\n"
             . "- unique_selling_points (USPs)\n"
             . "- tone_of_voice (Tonalität — wie wird kommuniziert)\n"
             . "- brand_values (Markenwerte)\n"
             . "- website (URL)\n"
             . "- industry (Branche)\n\n"
             . "Regeln:\n"
             . "- Wenn ein bestehender Card-Titel zu einer Steckbrief-Sektion passt (Levenshtein/Bedeutung), nutze action=merge mit target_card_id und additional_html (nur für richtext)\n"
             . "- Bei links- oder brand-Cards: bei Match append items/colors/fonts via action=merge_links bzw merge_brand mit target_card_id\n"
             . "- Sonst action=create mit type, title, body_html (richtext) ODER links (Array für links) ODER colors/fonts (Brand)\n"
             . "- Halte body_html sauber: nutze <h2> für Sektion-Untertitel, <ul><li> für Listen, <p> für Absätze, <strong> für Hervorhebung\n"
             . "- Markiere offene Felder im Original ([???]) mit <em>noch offen</em>\n"
             . "- Profil-Daten gehen IMMER in profile_updates, nie in eine Card\n\n"
             . "Antworte AUSSCHLIESSLICH mit JSON, ohne Markdown-Block, ohne Erklärung:\n"
             . "{\n  \"profile_updates\": {\"description\":\"...\", ...},\n"
             . "  \"cards\": [\n"
             . "    {\"action\":\"create\",\"type\":\"richtext\",\"title\":\"...\",\"body_html\":\"...\"},\n"
             . "    {\"action\":\"create\",\"type\":\"links\",\"title\":\"...\",\"links\":[{\"title\":\"Looker Studio\",\"url\":\"\"}]},\n"
             . "    {\"action\":\"create\",\"type\":\"contacts\",\"title\":\"Ansprechpartner\",\"groups\":[{\"title\":\"Intern\",\"people\":[{\"role\":\"Projektleitung\",\"name\":\"Thomas Kilian\",\"initials\":\"TKI\",\"email\":\"\",\"phone\":\"\"}]}]},\n"
             . "    {\"action\":\"create\",\"type\":\"brand\",\"title\":\"...\",\"colors\":[{\"name\":\"\",\"value\":\"#...\"}],\"fonts\":[{\"name\":\"\",\"note\":\"\"}]},\n"
             . "    {\"action\":\"merge\",\"target_card_id\":123,\"additional_html\":\"<p>...</p>\"}\n"
             . "  ]\n}";

        $userMsg = "Bestehende Cards:\n" . json_encode($cardOverview, JSON_UNESCAPED_UNICODE)
                 . "\n\nSteckbrief-Text:\n" . $text;

        try {
            $resp = $ai->chat([['role' => 'user', 'content' => $userMsg]], $sys);
            $raw = $resp['content'] ?? '';
            // JSON extrahieren
            if (preg_match('/\{[\s\S]*\}/', $raw, $m)) $raw = $m[0];
            $plan = json_decode($raw, true);
            if (!is_array($plan)) throw new \RuntimeException('LLM-Antwort konnte nicht geparsed werden');
        } catch (\Throwable $e) {
            Response::error('KI-Analyse fehlgeschlagen: ' . $e->getMessage());
        }

        $applied = ['profile_fields' => 0, 'created' => 0, 'merged' => 0];

        // 1. Profile-Updates
        $profileUpdates = $plan['profile_updates'] ?? [];
        $allowedProfileFields = ['description','target_audience','products_services','unique_selling_points','tone_of_voice','brand_values','website','industry'];
        $profilePatch = [];
        foreach ($profileUpdates as $k => $v) {
            if (in_array($k, $allowedProfileFields, true) && is_string($v) && trim($v) !== '') {
                $profilePatch[$k] = trim($v);
            }
        }
        if (!empty($profilePatch)) {
            $db->update('customers', $profilePatch, 'id = ?', [$customerId]);
            $applied['profile_fields'] = count($profilePatch);
        }

        // 2. Card-Operationen
        foreach ($plan['cards'] ?? [] as $op) {
            $opAction = $op['action'] ?? 'create';
            try {
                if ($opAction === 'create') {
                    $type = $op['type'] ?? 'richtext';
                    if (!in_array($type, ['richtext', 'links', 'brand', 'contacts'], true)) continue;
                    $title = trim((string) ($op['title'] ?? 'Importiert'));
                    $newId = $service->create($customerId, $type, $title, $userId);

                    // Body je Typ
                    $body = [];
                    if ($type === 'richtext') {
                        $body = ['html' => $op['body_html'] ?? ''];
                    } elseif ($type === 'links') {
                        $items = [];
                        foreach (($op['links'] ?? []) as $l) {
                            $items[] = ['title' => trim((string) ($l['title'] ?? '')), 'url' => trim((string) ($l['url'] ?? '')), 'note' => ''];
                        }
                        $body = ['items' => $items];
                    } elseif ($type === 'brand') {
                        $body = [
                            'colors' => array_map(fn($c) => ['name' => (string) ($c['name'] ?? ''), 'value' => (string) ($c['value'] ?? '')], $op['colors'] ?? []),
                            'fonts' => array_map(fn($f) => ['name' => (string) ($f['name'] ?? ''), 'note' => (string) ($f['note'] ?? '')], $op['fonts'] ?? []),
                            'note' => '',
                        ];
                    } elseif ($type === 'contacts') {
                        $groups = [];
                        foreach (($op['groups'] ?? []) as $g) {
                            $people = [];
                            foreach (($g['people'] ?? []) as $p) {
                                $people[] = [
                                    'role' => (string) ($p['role'] ?? ''),
                                    'name' => (string) ($p['name'] ?? ''),
                                    'initials' => (string) ($p['initials'] ?? ''),
                                    'email' => (string) ($p['email'] ?? ''),
                                    'phone' => (string) ($p['phone'] ?? ''),
                                ];
                            }
                            $groups[] = ['title' => (string) ($g['title'] ?? ''), 'people' => $people];
                        }
                        $body = ['groups' => $groups];
                    }
                    $service->update($newId, ['body' => $body], $userId);
                    $applied['created']++;
                } elseif ($opAction === 'merge') {
                    $targetId = (int) ($op['target_card_id'] ?? 0);
                    if ($targetId <= 0) continue;
                    $target = $service->get($targetId);
                    if (!$target || $target['customer_id'] != $customerId || !empty($target['is_system'])) continue;
                    if ($target['type'] !== 'richtext') continue;
                    $existingHtml = (string) ($target['body_decoded']['html'] ?? '');
                    $additional = (string) ($op['additional_html'] ?? '');
                    $merged = trim($existingHtml) . ($existingHtml ? "\n<hr>\n" : '') . $additional;
                    $service->update($targetId, ['body' => ['html' => $merged]], $userId);
                    $applied['merged']++;
                } elseif ($opAction === 'merge_links') {
                    $targetId = (int) ($op['target_card_id'] ?? 0);
                    $target = $service->get($targetId);
                    if (!$target || $target['type'] !== 'links') continue;
                    $items = $target['body_decoded']['items'] ?? [];
                    $existingUrls = array_map(fn($i) => $i['url'] ?? '', $items);
                    foreach (($op['links'] ?? []) as $l) {
                        $url = trim((string) ($l['url'] ?? ''));
                        if ($url === '' || in_array($url, $existingUrls, true)) continue;
                        $items[] = ['title' => trim((string) ($l['title'] ?? '')), 'url' => $url, 'note' => ''];
                    }
                    $service->update($targetId, ['body' => ['items' => $items]], $userId);
                    $applied['merged']++;
                } elseif ($opAction === 'merge_brand') {
                    $targetId = (int) ($op['target_card_id'] ?? 0);
                    $target = $service->get($targetId);
                    if (!$target || $target['type'] !== 'brand') continue;
                    $colors = $target['body_decoded']['colors'] ?? [];
                    $fonts = $target['body_decoded']['fonts'] ?? [];
                    foreach (($op['colors'] ?? []) as $c) $colors[] = ['name' => (string) ($c['name'] ?? ''), 'value' => (string) ($c['value'] ?? '')];
                    foreach (($op['fonts'] ?? []) as $f) $fonts[] = ['name' => (string) ($f['name'] ?? ''), 'note' => (string) ($f['note'] ?? '')];
                    $service->update($targetId, ['body' => ['colors' => $colors, 'fonts' => $fonts, 'note' => $target['body_decoded']['note'] ?? '']], $userId);
                    $applied['merged']++;
                }
            } catch (\Throwable $e) {
                error_log('auto-import card op failed: ' . $e->getMessage());
            }
        }

        Response::success([
            'cards' => $service->listForCustomer($customerId),
            'applied' => $applied,
        ], 'Steckbrief importiert: ' . $applied['created'] . ' neu, ' . $applied['merged'] . ' ergänzt, ' . $applied['profile_fields'] . ' Profil-Felder');
    }

    // ===== Auto-Arrange (LLM) =====
    if ($action === 'auto-arrange' && $method === 'POST') {
        $cards = $service->listForCustomer($customerId);
        if (count($cards) < 2) Response::error('Mindestens zwei Cards nötig');

        // Settings für OpenAI-Key
        $settingsRows = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($settingsRows ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];
        $settings = \Core\Settings::decryptMap($settings);
        $openaiKey = $settings['openai_api_key'] ?? '';
        if (empty($openaiKey)) Response::error('OpenAI API-Key nicht konfiguriert');

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($openaiKey, 'openai');
        $ai->setModel('gpt-4o-mini');

        // Card-Übersicht für LLM
        $summary = array_map(function ($c) {
            $body = $c['body_decoded'] ?? [];
            $hint = '';
            if (!empty($c['is_system'])) {
                $hint = match ($c['system_key']) {
                    'profile' => 'Stammdaten des Kunden — Website, Beschreibung, Zielgruppe, Tonalität',
                    'asana' => 'Asana-Verbindung und Sync-Status',
                    'knowledge' => 'Wissens-Liste (Dokumente, URLs, Texte)',
                    default => '',
                };
            } else {
                if ($c['type'] === 'links') $hint = count($body['items'] ?? []) . ' Links';
                elseif ($c['type'] === 'richtext') $hint = mb_strimwidth(strip_tags($body['html'] ?? ''), 0, 80, '…');
                elseif ($c['type'] === 'documents') $hint = (count($c['files'] ?? [])) . ' Dateien';
                elseif ($c['type'] === 'images') $hint = (count($c['files'] ?? [])) . ' Bilder';
                elseif ($c['type'] === 'brand') $hint = (count($body['colors'] ?? [])) . ' Farben, ' . (count($body['fonts'] ?? [])) . ' Schriften';
                elseif ($c['type'] === 'contacts') $hint = array_sum(array_map(fn($g) => count($g['people'] ?? []), $body['groups'] ?? [])) . ' Personen';
                elseif ($c['type'] === 'kpi') $hint = (count($body['items'] ?? [])) . ' Kennzahlen';
                elseif ($c['type'] === 'tracking_status') $hint = (count($body['items'] ?? [])) . ' Punkte';
            }
            return [
                'id' => (int) $c['id'],
                'kind' => !empty($c['is_system']) ? 'system:' . $c['system_key'] : $c['type'],
                'title' => $c['title'],
                'hint' => $hint,
            ];
        }, $cards);

        $sys = "Du bist Layout-Assistent für ein Kunden-Steckbrief-Dashboard mit einem Tile-Grid (4 Spalten breit, "
             . "feste Zeilenhöhe). Du sortierst Cards und vergibst Größen so, dass die wichtigsten oben stehen "
             . "und das Layout ausgewogen wirkt. "
             . "\n\nGrößen-Regeln:"
             . "\n- size_w (Breite) = 2 oder 3 (Cards sind IMMER mindestens 2 Spalten breit)"
             . "\n- size_h (Höhe) = 1, 2 oder 3"
             . "\n- Card mit viel Inhalt (Listen, Texte, viele Items): 2x2, 2x3 oder 3x2"
             . "\n- Knappe Cards (Status, Brand-Farben, kurze Notiz): 2x1"
             . "\n- 'system:profile' = 2x2 (zentral, mittlere Höhe)"
             . "\n- 'system:knowledge' = 3x1 oder 3x2 (breit)"
             . "\n- 'system:asana' = 2x2 oder 2x1"
             . "\n\nAntworte AUSSCHLIESSLICH mit einem JSON-Array, ohne Markdown-Codeblock, ohne Erklärung. "
             . "Format: [{\"id\":<int>,\"sort_order\":<int>,\"size_w\":<1-3>,\"size_h\":<1-3>}, ...]";

        $userMsg = "Cards:\n" . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                 . "\n\nVergib für jede Card sort_order (1..n), size_w (1-3) und size_h (1-3).";

        try {
            $resp = $ai->chat([['role' => 'user', 'content' => $userMsg]], $sys);
            $text = $resp['content'] ?? '';
            // JSON extrahieren — manchmal wickelt das Modell in Codeblöcke
            if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
                $text = $m[0];
            }
            $arrangement = json_decode($text, true);
            if (!is_array($arrangement)) throw new \RuntimeException('LLM-Antwort konnte nicht geparsed werden');

            // Anwenden
            foreach ($arrangement as $entry) {
                $cardId = (int) ($entry['id'] ?? 0);
                if ($cardId <= 0) continue;
                $matchingCard = null;
                foreach ($cards as $c) { if ((int) $c['id'] === $cardId) { $matchingCard = $c; break; } }
                if (!$matchingCard) continue;
                $patch = [];
                if (isset($entry['sort_order'])) $patch['sort_order'] = max(1, (int) $entry['sort_order']);
                if (isset($entry['size_w'])) $patch['size_w'] = max(\Services\CustomerCardService::MIN_WIDTH, min(3, (int) $entry['size_w']));
                if (isset($entry['size_h'])) $patch['size_h'] = max(1, min(3, (int) $entry['size_h']));
                if (!empty($patch)) {
                    $patch['updated_by'] = $userId;
                    $db->update('customer_cards', $patch, 'id = ? AND customer_id = ?', [$cardId, $customerId]);
                }
            }

            Response::success([
                'cards' => $service->listForCustomer($customerId),
                'applied' => count($arrangement),
            ], 'Mit KI neu angeordnet');
        } catch (\Throwable $e) {
            Response::error('KI-Anordnung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // ===== Reorder =====
    if ($action === 'reorder' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) Response::error('ids: Array erwartet');
        $service->reorder($customerId, array_map('intval', $ids));
        Response::success(null, 'Reihenfolge gespeichert');
    }

    // ===== Kanban-Layout (column_idx + target_tab + sort_order in einem Aufwasch) =====
    if ($action === 'kanban' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $cards = $input['cards'] ?? [];
        if (!is_array($cards)) Response::error('cards: Array erwartet');
        $sort = 10;
        foreach ($cards as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid <= 0) continue;
            $col = max(0, min(3, (int) ($c['column_idx'] ?? 2))); // 0 = Hero, 1-3 = Spalten
            $tab = in_array(($c['target_tab'] ?? 'inhalte'), ['uebersicht','inhalte','personen','dateien','marke','sonstiges','websites'], true) ? $c['target_tab'] : 'inhalte';
            $db->update('customer_cards',
                ['sort_order' => $sort, 'column_idx' => $col, 'target_tab' => $tab, 'updated_by' => $userId],
                'id = ? AND customer_id = ?', [$cid, $customerId]);
            $sort += 10;
        }
        Response::success(null, 'Kanban-Layout gespeichert');
    }

    // ===== Card-CRUD =====
    if ($cardId > 0) {
        $card = $service->get($cardId);
        if (!$card || (int) $card['customer_id'] !== $customerId) {
            Response::notFound('Card nicht gefunden');
        }

        if ($method === 'GET') {
            Response::success($card);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $service->update($cardId, $input, $userId);
            Response::success($service->get($cardId), 'Gespeichert');
        }
        if ($method === 'DELETE') {
            $service->delete($cardId);
            Response::success(null, 'Card gelöscht');
        }
        Response::error('Methode nicht unterstützt');
    }

    // ===== Liste / Erstellen =====
    if ($method === 'GET') {
        Response::success([
            'cards' => $service->listForCustomer($customerId),
            'types' => CustomerCardService::TYPES,
        ]);
    }
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $type = (string) ($input['type'] ?? '');
        $title = (string) ($input['title'] ?? '');
        $newId = $service->create($customerId, $type, $title, $userId);
        Response::success($service->get($newId), 'Card erstellt');
    }

    Response::error('Methode nicht unterstützt');
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage());
} catch (\RuntimeException $e) {
    Response::error($e->getMessage());
} catch (\Throwable $e) {
    error_log('customer-cards API error: ' . $e->getMessage());
    Response::error('Fehler: ' . $e->getMessage());
}
