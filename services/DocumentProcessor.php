<?php
namespace Services;

/**
 * DocumentProcessor - Verarbeitet hochgeladene Dokumente
 *
 * Unterstützt: TXT, PDF, DOCX, MD, HTML
 */
class DocumentProcessor
{
    private $maxChunkSize = 1500; // Wörter pro Chunk
    private $chunkOverlap = 100;  // Überlappung für Kontext

    /**
     * Verarbeitet eine hochgeladene Datei
     * @param string $filePath Pfad zur Datei (temp oder permanent)
     * @param string $mimeType MIME-Type der Datei
     * @param string|null $originalFilename Original-Dateiname für Endungs-Erkennung
     */
    public function processFile(string $filePath, string $mimeType, ?string $originalFilename = null): array
    {
        // Extension aus Original-Dateiname oder Pfad ermitteln
        $extension = '';
        if ($originalFilename) {
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        }
        if (!$extension) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }
        // Fallback: Extension aus MIME-Type ableiten
        if (!$extension && $mimeType) {
            $mimeToExt = [
                'text/plain' => 'txt',
                'text/markdown' => 'md',
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'text/html' => 'html'
            ];
            $extension = $mimeToExt[$mimeType] ?? '';
        }

        // Text extrahieren basierend auf Dateityp
        switch ($extension) {
            case 'txt':
            case 'md':
                $text = file_get_contents($filePath);
                break;

            case 'pdf':
                $text = $this->extractFromPdf($filePath);
                break;

            case 'docx':
                $text = $this->extractFromDocx($filePath);
                break;

            case 'html':
            case 'htm':
                $text = $this->extractFromHtml($filePath);
                break;

            default:
                throw new \Exception("Dateityp '$extension' wird nicht unterstützt");
        }

        // Text bereinigen
        $text = $this->cleanText($text);

        if (empty(trim($text))) {
            throw new \Exception("Konnte keinen Text aus der Datei extrahieren");
        }

        return [
            'text' => $text,
            'word_count' => str_word_count($text),
            'extension' => $extension
        ];
    }

    /**
     * Text in Chunks aufteilen für bessere Verarbeitung
     */
    public function chunkText(string $text, ?string $title = null): array
    {
        $words = preg_split('/\s+/', $text);
        $totalWords = count($words);

        // Wenn kurz genug, als ein Chunk zurückgeben
        if ($totalWords <= $this->maxChunkSize) {
            return [[
                'content' => $text,
                'word_count' => $totalWords,
                'chunk_index' => 0,
                'total_chunks' => 1
            ]];
        }

        // In Chunks aufteilen
        $chunks = [];
        $currentPosition = 0;
        $chunkIndex = 0;

        while ($currentPosition < $totalWords) {
            $chunkWords = array_slice($words, $currentPosition, $this->maxChunkSize);
            $chunkText = implode(' ', $chunkWords);

            // Versuche am Satzende zu trennen
            $chunkText = $this->trimToSentence($chunkText);

            $chunks[] = [
                'content' => $chunkText,
                'word_count' => str_word_count($chunkText),
                'chunk_index' => $chunkIndex
            ];

            // Position vorrücken (minus Overlap für Kontext)
            $actualWords = str_word_count($chunkText);
            $currentPosition += max(1, $actualWords - $this->chunkOverlap);
            $chunkIndex++;

            // Sicherheitsabbruch
            if ($chunkIndex > 100) break;
        }

        // Total Chunks zu jedem Chunk hinzufügen
        $totalChunks = count($chunks);
        foreach ($chunks as &$chunk) {
            $chunk['total_chunks'] = $totalChunks;
        }

        return $chunks;
    }

    /**
     * Text am Satzende trimmen
     */
    private function trimToSentence(string $text): string
    {
        // Finde letzten Satzabschluss
        $lastPeriod = strrpos($text, '. ');
        $lastQuestion = strrpos($text, '? ');
        $lastExclaim = strrpos($text, '! ');

        $lastEnd = max($lastPeriod ?: 0, $lastQuestion ?: 0, $lastExclaim ?: 0);

        // Nur trimmen wenn wir mehr als 70% behalten
        if ($lastEnd > strlen($text) * 0.7) {
            return substr($text, 0, $lastEnd + 1);
        }

        return $text;
    }

    /**
     * Text extrahieren aus PDF
     */
    private function extractFromPdf(string $filePath): string
    {
        // Versuche pdftotext (poppler-utils)
        if (function_exists('exec')) {
            $output = [];
            $returnVar = 0;
            \exec("pdftotext -enc UTF-8 " . \escapeshellarg($filePath) . " - 2>/dev/null", $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                return implode("\n", $output);
            }
        }

        // Fallback: Einfache PDF-Text-Extraktion
        $content = file_get_contents($filePath);

        // Versuche Text zwischen stream/endstream zu finden
        $text = '';
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Versuche zu dekomprimieren
                $decoded = @gzuncompress($stream);
                if ($decoded) {
                    $stream = $decoded;
                }
                // Extrahiere lesbaren Text
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $textMatches)) {
                    foreach ($textMatches[1] as $textBlock) {
                        $textBlock = preg_replace('/\([^)]*\)/', '', $textBlock);
                        $text .= $textBlock . ' ';
                    }
                }
            }
        }

        // Falls nichts gefunden, nimm alle druckbaren Zeichen
        if (empty(trim($text))) {
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D\xC0-\xFF]/', ' ', $content);
        }

        return $text;
    }

    /**
     * Text extrahieren aus DOCX
     */
    private function extractFromDocx(string $filePath): string
    {
        $text = '';

        $zip = new \ZipArchive();
        if ($zip->open($filePath) === true) {
            // Hauptdokument
            $content = $zip->getFromName('word/document.xml');
            if ($content) {
                // XML parsen und Text extrahieren
                $content = str_replace('</w:p>', "\n", $content);
                $content = str_replace('</w:t>', ' ', $content);
                $text = strip_tags($content);
            }
            $zip->close();
        }

        return $text;
    }

    /**
     * Text extrahieren aus HTML
     */
    private function extractFromHtml(string $filePath): string
    {
        $html = file_get_contents($filePath);

        // Script und Style Tags entfernen
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Tags in Zeilenumbrüche konvertieren
        $html = preg_replace('/<(br|p|div|h[1-6]|li)[^>]*>/i', "\n", $html);

        // Alle Tags entfernen
        $text = strip_tags($html);

        // HTML-Entities dekodieren
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return $text;
    }

    /**
     * Text bereinigen
     */
    private function cleanText(string $text): string
    {
        // Mehrfache Leerzeichen/Zeilenumbrüche reduzieren
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Unterstützte Dateitypen
     */
    public static function getSupportedTypes(): array
    {
        return [
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html' => 'text/html',
            'htm' => 'text/html'
        ];
    }

    /**
     * Prüft ob Dateityp unterstützt wird
     */
    public static function isSupported(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return array_key_exists($ext, self::getSupportedTypes());
    }

    /**
     * URL crawlen und Hauptinhalt extrahieren (ohne Header, Footer, Nav, Sidebar)
     */
    public function extractFromUrl(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ThoxanBot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 400 || empty($html)) {
            throw new \Exception("URL konnte nicht geladen werden (HTTP {$httpCode})");
        }

        // Encoding erkennen und zu UTF-8 konvertieren
        if (preg_match('/charset=([^\s;]+)/i', $contentType, $m)) {
            $charset = strtolower(trim($m[1]));
            if ($charset !== 'utf-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            }
        }

        // Titel extrahieren
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        // Noise entfernen: script, style, nav, header, footer, aside, iframe
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $html);
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $html);
        // Cookie-Banner, Popups etc.
        $html = preg_replace('/<div[^>]*class="[^"]*(?:cookie|consent|popup|modal|banner|overlay)[^"]*"[^>]*>.*?<\/div>/is', '', $html);

        // Hauptinhalt finden: <main>, <article>, oder <div role="main">
        $mainContent = '';
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $mainContent = $m[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
            $mainContent = $m[1];
        } elseif (preg_match('/<div[^>]*role=["\']main["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
            $mainContent = $m[1];
        } elseif (preg_match('/<div[^>]*(?:id|class)=["\'][^"\']*(?:content|main|article|entry|post)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
            $mainContent = $m[1];
        }

        // Fallback: Body nehmen
        if (empty(trim(strip_tags($mainContent)))) {
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
                $mainContent = $m[1];
            } else {
                $mainContent = $html;
            }
        }

        // HTML zu Text
        $mainContent = preg_replace('/<(br|p|div|h[1-6]|li|tr)[^>]*>/i', "\n", $mainContent);
        $mainContent = preg_replace('/<(h[1-6])[^>]*>(.*?)<\/\1>/i', "\n## $2\n", $mainContent);
        $text = strip_tags($mainContent);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = $this->cleanText($text);

        $wordCount = str_word_count($text);

        if ($wordCount < 10) {
            throw new \Exception("Zu wenig Inhalt extrahiert ({$wordCount} Woerter). Die Seite hat moeglicherweise dynamisch geladenen Content.");
        }

        return [
            'text' => $text,
            'word_count' => $wordCount,
            'title' => $title,
            'url' => $url,
        ];
    }
}
