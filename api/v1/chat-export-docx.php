<?php
/**
 * Chat Message DOCX Export — Einzelne Nachricht als Word-Dokument (THX-Formatierung)
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$userId = Auth::id();
$messageId = (int) ($input['message_id'] ?? 0);

if (!$messageId) {
    Response::error('Message-ID erforderlich');
}

// Message + Conversation laden
$msg = $db->queryOne(
    "SELECT m.*, c.title as conv_title, c.user_id as conv_owner
     FROM chat_conversation_messages m
     JOIN chat_conversations c ON m.conversation_id = c.id
     WHERE m.id = ?",
    [$messageId]
);

if (!$msg) {
    Response::notFound('Nachricht nicht gefunden');
}

// Zugriffspruefung
$canAccess = ($msg['conv_owner'] == $userId);
if (!$canAccess) {
    $share = $db->queryOne(
        "SELECT id FROM chat_shares WHERE shared_with = ? AND share_type = 'conversation' AND target_id = ?",
        [$userId, $msg['conversation_id']]
    );
    if (!$share && !Auth::isAdmin()) {
        Response::forbidden('Kein Zugriff');
    }
}

require_once SERVICES_PATH . '/DocxGenerator.php';
$docx = new \Services\DocxGenerator();

$content = $msg['content'] ?? '';
$convTitle = $msg['conv_title'] ?? 'Chat';

// Titel
$docx->addHeading($convTitle, 1);
$docx->addParagraph('Erstellt am ' . date('d.m.Y', strtotime($msg['created_at'])), false, 10);
$docx->addHorizontalRule();
$docx->addSpacer();

// Content als Markdown parsen und in DOCX rendern
$lines = explode("\n", $content);
$inList = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    // Leerzeile
    if ($trimmed === '') {
        if ($inList) $inList = false;
        $docx->addSpacer();
        continue;
    }

    // Heading: # ## ### ####
    if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $m)) {
        $level = strlen($m[1]);
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $m[2]);
        $docx->addHeading($text, min($level, 3));
        continue;
    }

    // Bullet: - text oder * text
    if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $m[1]);
        $docx->addBullet($text);
        $inList = true;
        continue;
    }

    // Nummerierte Liste: 1. text
    if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $m[1]);
        $docx->addBullet($text);
        continue;
    }

    // Bold-Zeile: **Label:** Wert
    if (preg_match('/^\*\*(.+?)\*\*:?\s*(.*)$/', $trimmed, $m)) {
        $docx->addParagraph($m[1] . ($m[2] ? ': ' . $m[2] : ''), true, 11);
        continue;
    }

    // Horizontale Linie: --- oder ***
    if (preg_match('/^[-*_]{3,}$/', $trimmed)) {
        $docx->addHorizontalRule();
        continue;
    }

    // Normaler Text — Markdown-Bold entfernen
    $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $trimmed);
    $docx->addParagraph($text);
}

// Wortanzahl am Ende
$wordCount = str_word_count(strip_tags($content));
$docx->addSpacer();
$docx->addHorizontalRule();
$docx->addParagraph('Wortanzahl: ' . number_format($wordCount, 0, ',', '.'), false, 9);

// DOCX generieren und senden
$docxData = $docx->generate();
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($convTitle));
$filename = $slug . '-' . date('Y-m-d') . '.docx';

// Session freigeben
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($docxData));
echo $docxData;
exit;
