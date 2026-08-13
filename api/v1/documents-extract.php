<?php
/**
 * Document Text Extraction API - Extrahiert Text aus PDF, DOCX und TXT
 */

use Core\Response;

global $db;

// Datei-Upload prüfen
if (empty($_FILES['file'])) {
    Response::error('Keine Datei hochgeladen');
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Datei zu gross',
        UPLOAD_ERR_FORM_SIZE => 'Datei zu gross',
        UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Server-Fehler',
        UPLOAD_ERR_CANT_WRITE => 'Server-Fehler',
    ];
    Response::error($errors[$file['error']] ?? 'Upload-Fehler');
}

// Max 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    Response::error('Datei zu gross (max. 10MB)');
}

$filename = $file['name'];
$tmpPath = $file['tmp_name'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$text = '';

try {
    switch ($extension) {
        case 'txt':
            $text = extractFromTxt($tmpPath);
            break;

        case 'pdf':
            $text = extractFromPdf($tmpPath);
            break;

        case 'docx':
            $text = extractFromDocx($tmpPath);
            break;

        case 'doc':
            Response::error('Altes .doc Format nicht unterstützt. Bitte als .docx speichern.');
            break;

        default:
            Response::error('Nicht unterstütztes Dateiformat. Erlaubt: PDF, DOCX, TXT');
    }

    // Text bereinigen
    $text = cleanText($text);

    if (empty($text)) {
        Response::error('Kein Text in der Datei gefunden');
    }

    // Text kürzen falls zu lang (max 10.000 Zeichen fürs Topic-Feld)
    if (mb_strlen($text) > 10000) {
        $text = mb_substr($text, 0, 10000) . "\n\n[... Text gekürzt ...]";
    }

    Response::success([
        'filename' => $filename,
        'text' => $text,
        'length' => mb_strlen($text)
    ]);

} catch (\Exception $e) {
    Response::error('Fehler beim Extrahieren: ' . $e->getMessage());
}

/**
 * Text aus TXT-Datei extrahieren
 */
function extractFromTxt(string $path): string
{
    $content = file_get_contents($path);

    // Encoding erkennen und zu UTF-8 konvertieren
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    return $content;
}

/**
 * Text aus PDF extrahieren
 */
function extractFromPdf(string $path): string
{
    // Versuche pdftotext (poppler-utils)
    if (shell_exec('which pdftotext')) {
        $output = [];
        $tmpTxt = tempnam(sys_get_temp_dir(), 'pdf_');
        exec("pdftotext -enc UTF-8 " . escapeshellarg($path) . " " . escapeshellarg($tmpTxt) . " 2>/dev/null", $output, $returnCode);

        if ($returnCode === 0 && file_exists($tmpTxt)) {
            $text = file_get_contents($tmpTxt);
            unlink($tmpTxt);
            return $text;
        }
        if (file_exists($tmpTxt)) {
            unlink($tmpTxt);
        }
    }

    // Fallback: Einfache PHP-Extraktion (funktioniert nur bei einfachen PDFs)
    $content = file_get_contents($path);

    // Text-Streams finden
    $text = '';
    if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
        foreach ($matches[1] as $stream) {
            // Versuche zlib-dekomprimierung
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if ($decoded !== false) {
                // Text-Operatoren extrahieren
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]);
                }
            }
        }
    }

    if (empty($text)) {
        throw new \Exception('PDF konnte nicht gelesen werden. Bitte pdftotext installieren: apt install poppler-utils');
    }

    return $text;
}

/**
 * Text aus DOCX extrahieren
 */
function extractFromDocx(string $path): string
{
    $zip = new \ZipArchive();

    if ($zip->open($path) !== true) {
        throw new \Exception('DOCX konnte nicht geöffnet werden');
    }

    // document.xml enthaelt den Haupttext
    $content = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($content === false) {
        throw new \Exception('DOCX-Inhalt konnte nicht gelesen werden');
    }

    // XML parsen
    $xml = new \DOMDocument();
    @$xml->loadXML($content);

    // Alle Text-Elemente finden
    $text = '';
    $paragraphs = $xml->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');

    foreach ($paragraphs as $paragraph) {
        $texts = $paragraph->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
        $paragraphText = '';
        foreach ($texts as $t) {
            $paragraphText .= $t->textContent;
        }
        if (!empty($paragraphText)) {
            $text .= $paragraphText . "\n";
        }
    }

    return $text;
}

/**
 * Text bereinigen
 */
function cleanText(string $text): string
{
    // Mehrfache Leerzeichen/Zeilenumbrueche normalisieren
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    // Trim
    $text = trim($text);

    return $text;
}
