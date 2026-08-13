<?php
/**
 * Canvas Export API
 * GET  ?format=json|markdown|docx  — Sofort-Download
 * POST {action:'ai-summary'}       — KI-Zusammenfassung generieren (async-faehig)
 * POST {action:'docx', ai_summary:'...'} — DOCX mit vorher generierter Zusammenfassung
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

require_once SERVICES_PATH . '/CanvasService.php';
$canvasService = new \Services\CanvasService($db);

$userId = Auth::id();
$canvasId = (int) ($_GET['canvas_id'] ?? 0);

if (!$canvasId) {
    Response::error('Canvas-ID erforderlich');
}
if (!$canvasService->canAccess($canvasId, $userId) && !Auth::isAdmin()) {
    Response::forbidden('Kein Zugriff');
}

// ===== POST: Zweistufiger Export =====
if ($method === 'POST') {
    $action = $input['action'] ?? '';

    // Schritt 1: KI-Zusammenfassung generieren
    if ($action === 'ai-summary') {
        set_time_limit(120);

        // Session-Lock freigeben damit andere Requests nicht blockiert werden
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $settings = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $settings = \Core\Settings::decryptMap($settings);

        $model = $settings['default_model'] ?? 'gpt-4';
        if (strpos($model, 'claude') !== false) {
            $provider = 'anthropic';
        } elseif (strpos($model, 'gemini') !== false) {
            $provider = 'google';
        } else {
            $provider = 'openai';
        }
        $providerKeys = [
            'openai' => $settings['openai_api_key'] ?? '',
            'anthropic' => $settings['anthropic_api_key'] ?? '',
            'google' => $settings['google_api_key'] ?? '',
        ];
        $apiKey = $providerKeys[$provider] ?? '';

        if (empty($apiKey)) {
            Response::error('Kein API-Key konfiguriert');
        }

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new \Services\AIService($apiKey, $provider);
        $ai->setModel($model);

        $md = $canvasService->exportAsMarkdown($canvasId);

        try {
            $result = $ai->chat([
                ['role' => 'user', 'content' => $md]
            ], "Du erhaeltst ein KI-Kompass-Projekt mit 8 Feldern (Problem, Loesung, Input, Magie, QS, Output, Ergebnisse, Risiken). Erstelle daraus ein professionelles, gut lesbares Briefing-Dokument auf Deutsch.

Regeln:
- Schreibe eine kurze Management Summary (3-5 Saetze)
- Dann pro Feld eine praegnante Zusammenfassung (formuliere die Inhalte zu fliessendem Text, nicht einfach kopieren)
- Nutze klare Struktur: Ueberschrift pro Feld, dann Fliesstext
- Markiere offene Punkte oder Luecken explizit
- Am Ende: Gesamtbewertung der Briefing-Reife und empfohlene naechste Schritte
- Antworte NUR mit dem Briefing-Text, keine Meta-Kommentare");

            $summary = $result['content'] ?? '';
            Response::success(['summary' => $summary], 'Zusammenfassung erstellt');
        } catch (\Exception $e) {
            Response::error('KI-Fehler: ' . $e->getMessage());
        }
    }

    // Schritt 2: DOCX generieren (mit optionaler Zusammenfassung)
    if ($action === 'docx') {
        $aiSummary = $input['ai_summary'] ?? null;
        generateDocx($canvasService, $canvasId, $aiSummary);
    }

    Response::error('Ungueltige Action');
}

// ===== GET: Direkter Download =====
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$format = $_GET['format'] ?? 'json';

if ($format === 'docx') {
    generateDocx($canvasService, $canvasId, null);
}

if ($format === 'markdown') {
    $md = $canvasService->exportAsMarkdown($canvasId);
    if (!$md) Response::notFound('Canvas nicht gefunden');

    $project = $canvasService->getProject($canvasId);
    $filename = 'canvas-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($project['title'] ?? 'export')) . '.md';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $md;
    exit;
}

// JSON
$data = $canvasService->exportAsArray($canvasId);
if (!$data) Response::notFound('Canvas nicht gefunden');
Response::success($data);


// ===== DOCX Generator Funktion =====
function generateDocx(\Services\CanvasService $canvasService, int $canvasId, ?string $aiSummary): void
{
    require_once SERVICES_PATH . '/DocxGenerator.php';
    $docx = new \Services\DocxGenerator();

    $data = $canvasService->exportAsArray($canvasId);
    if (!$data) {
        \Core\Response::notFound('Canvas nicht gefunden');
    }

    $project = $data['project'];
    $docx->addHeading('KI Kompass: ' . $project['title'], 1);

    if ($project['description']) {
        $docx->addParagraph($project['description'], false, 12);
    }

    $docx->addSpacer();
    $statusLabel = ['active' => 'Aktiv', 'completed' => 'Abgeschlossen', 'archived' => 'Archiviert'][$project['status']] ?? $project['status'];
    $docx->addParagraph('Status: ' . $statusLabel . '  |  Briefing-Reife: ' . $project['briefing_readiness'] . '%  |  Erstellt: ' . date('d.m.Y', strtotime($project['created_at'])), false, 10);

    if (!empty($data['participants'])) {
        $names = array_map(fn($p) => $p['name'] . ' (' . $p['role'] . ')', $data['participants']);
        $docx->addParagraph('Teilnehmer: ' . implode(', ', $names), false, 10);
    }

    $docx->addHorizontalRule();

    // KI-Zusammenfassung wenn vorhanden
    if ($aiSummary) {
        $docx->addHeading('Management Briefing (KI-optimiert)', 2);
        $lines = explode("\n", $aiSummary);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
                $level = substr_count($line, '#');
                $docx->addHeading(trim($m[1], '# '), min($level + 1, 3));
            } elseif (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                $docx->addBullet($m[1]);
            } elseif (preg_match('/^\*\*(.+?)\*\*:?\s*(.*)$/', $line, $m)) {
                $docx->addParagraph($m[1] . ($m[2] ? ': ' . $m[2] : ''), true, 11);
            } else {
                // Strip remaining markdown bold markers
                $line = preg_replace('/\*\*(.+?)\*\*/', '$1', $line);
                $docx->addParagraph($line);
            }
        }

        $docx->addHorizontalRule();
        $docx->addHeading('Detaildaten pro Feld', 2);
    }

    // Felder mit Karten
    $fieldLabels = \Services\CanvasService::FIELD_LABELS;

    foreach (\Services\CanvasService::FIELDS as $field) {
        $label = $fieldLabels[$field] ?? $field;
        $cards = $data['fields'][$field] ?? [];
        $fieldInfo = $data['completeness']['fields'][$field] ?? [];
        $percent = $fieldInfo['percent'] ?? 0;

        $docx->addHeading($label, 2);

        if (empty($cards)) {
            $docx->addParagraph('Noch keine Inhalte erfasst.', true, 10);
        } else {
            foreach ($cards as $card) {
                $docx->addHeading($card['title'], 3);
                if ($card['content']) {
                    renderContentToDocx($docx, $card['content']);
                }
            }
        }

        $docx->addSpacer();
    }

    // Querverweise
    if (!empty($data['references'])) {
        $docx->addHorizontalRule();
        $docx->addHeading('Querverweise', 2);
        foreach ($data['references'] as $ref) {
            $note = $ref['note'] ? ' — ' . $ref['note'] : '';
            $docx->addBullet($ref['source_title'] . ' (' . $ref['source_field'] . ') -> ' . $ref['target_title'] . ' (' . $ref['target_field'] . ')' . $note);
        }
    }

    $docxData = $docx->generate();
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($project['title'] ?? 'export'));
    $filename = $slug . '-briefing-' . date('Y-m-d') . '.docx';

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($docxData));
    echo $docxData;
    exit;
}

/**
 * Rendert Markdown-formatierten Card-Content in DOCX-Elemente
 */
function renderContentToDocx(\Services\DocxGenerator $docx, string $content): void
{
    $lines = explode("\n", $content);
    $paragraph = '';

    $flushParagraph = function() use ($docx, &$paragraph) {
        if (trim($paragraph) !== '') {
            // Markdown bold markers entfernen fuer DOCX
            $clean = preg_replace('/\*\*(.+?)\*\*/', '$1', $paragraph);
            $docx->addParagraph(trim($clean));
        }
        $paragraph = '';
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Leerzeile = Absatzende
        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }

        // Bullet: - **text** oder - text
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            $flushParagraph();
            $bulletText = preg_replace('/\*\*(.+?)\*\*/', '$1', $m[1]);
            $docx->addBullet($bulletText);
            continue;
        }

        // Bold-Zeile am Anfang: **Label:** Wert
        if (preg_match('/^\*\*(.+?)\*\*:?\s*(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $docx->addParagraph($m[1] . ($m[2] ? ': ' . $m[2] : ''), true, 11);
            continue;
        }

        // Sub-Heading: ### text
        if (preg_match('/^#{1,4}\s+(.+)$/', $trimmed, $m)) {
            $flushParagraph();
            $docx->addParagraph(preg_replace('/\*\*(.+?)\*\*/', '$1', $m[1]), true, 11);
            continue;
        }

        // Normaler Text — zum Absatz hinzufuegen
        $paragraph .= ($paragraph ? "\n" : '') . $trimmed;
    }

    $flushParagraph();
}
