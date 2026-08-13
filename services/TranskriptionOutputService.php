<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * TranskriptionOutputService — wandelt Transkripte ueber LLM in strukturierte
 * Ausgaben (Memo, Workshop-Protokoll, Call-Notiz, Tutorial, Raw-Text) und
 * speist sie optional in die Wissens-DB.
 */
class TranskriptionOutputService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ====================================================================
       Vorlagen-Verwaltung
       ==================================================================== */

    public function listTemplates(): array
    {
        return $this->db->query(
            'SELECT id, template_type, label, prompt_text, output_format, is_active
             FROM tr_templates ORDER BY id'
        );
    }

    public function getTemplate(string $type): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM tr_templates WHERE template_type=? AND is_active=1 LIMIT 1',
            [$type]
        );
    }

    public function updateTemplate(int $id, array $patch): void
    {
        $allowed = ['label','prompt_text','output_format','is_active'];
        $update = array_intersect_key($patch, array_flip($allowed));
        if (!$update) return;
        $sets = [];
        $params = [];
        foreach ($update as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
        $params[] = $id;
        $this->db->execute('UPDATE tr_templates SET ' . implode(',', $sets) . ' WHERE id=?', $params);
    }

    /* ====================================================================
       Output erzeugen
       ==================================================================== */

    /**
     * Erzeugt aus einem Transkript einen strukturierten Output via LLM.
     * Bei output_format='docx' wird zusaetzlich eine .docx-Datei erzeugt
     * (Markdown -> docx via pandoc, sofern installiert; fallback: HTML-Datei).
     */
    public function generate(int $jobId, string $templateType, int $userId): array
    {
        $result = $this->db->queryOne(
            'SELECT id, transcript_text, segments_json, language_detected, duration_sec
             FROM tr_results WHERE job_id=? LIMIT 1',
            [$jobId]
        );
        if (!$result) throw new \RuntimeException('Kein Transkript fuer Job');

        $template = $this->getTemplate($templateType);
        if (!$template) throw new \RuntimeException('Vorlage nicht gefunden: ' . $templateType);

        $transcript = $this->buildPrompText($result);

        // Raw-Output braucht kein LLM
        $outputText = '';
        if ($templateType === 'raw') {
            $outputText = $transcript;
        } else {
            $outputText = $this->callLLM($template['prompt_text'], $transcript);
        }

        $docxPath = null;
        if (($template['output_format'] ?? 'markdown') === 'docx') {
            $docxPath = $this->renderDocx($jobId, $templateType, $outputText);
        }

        // Bestehenden Output ggf. ueberschreiben (UNIQUE auf (result_id, template_type) ist nicht gesetzt
        // — wir loeschen explizit, damit ein Job pro Typ genau einen aktuellen Output hat)
        $this->db->execute(
            'DELETE FROM tr_outputs WHERE result_id=? AND template_type=?',
            [(int)$result['id'], $templateType]
        );

        $outputId = (int)$this->db->insert('tr_outputs', [
            'result_id'        => (int)$result['id'],
            'template_type'    => $templateType,
            'output_text'      => $outputText,
            'output_format'    => $template['output_format'],
            'docx_path'        => $docxPath,
            'knowledge_doc_id' => null,
        ]);

        return [
            'output_id'     => $outputId,
            'template_type' => $templateType,
            'output_text'   => $outputText,
            'output_format' => $template['output_format'],
            'docx_path'     => $docxPath,
        ];
    }

    public function deleteOutput(int $outputId, int $userId, bool $isAdmin): bool
    {
        $row = $this->db->queryOne(
            'SELECT o.id, o.docx_path, u.user_id AS uploader_id, u.customer_id
             FROM tr_outputs o
             JOIN tr_results r ON r.id = o.result_id
             JOIN tr_jobs j ON j.id = r.job_id
             JOIN tr_uploads u ON u.id = j.upload_id
             WHERE o.id=?',
            [$outputId]
        );
        if (!$row) return false;
        if (!$isAdmin && (int)$row['uploader_id'] !== $userId) {
            // Kunden-basierter Zugriff
            $custIds = array_map('intval', array_column(\Core\Auth::customers(), 'id'));
            if (!in_array((int)$row['customer_id'], $custIds, true)) return false;
        }
        if ($row['docx_path'] && is_file($row['docx_path'])) {
            @unlink($row['docx_path']);
        }
        $this->db->execute('DELETE FROM tr_outputs WHERE id=?', [$outputId]);
        return true;
    }

    public function listOutputsForJob(int $jobId): array
    {
        return $this->db->query(
            'SELECT o.id, o.template_type, o.output_text, o.output_format, o.docx_path,
                    o.knowledge_doc_id, o.created_at, t.label
             FROM tr_outputs o
             JOIN tr_results r ON r.id = o.result_id
             LEFT JOIN tr_templates t ON t.template_type = o.template_type
             WHERE r.job_id=?
             ORDER BY o.created_at DESC',
            [$jobId]
        );
    }

    public function downloadDocx(int $outputId, int $userId, bool $isAdmin): ?array
    {
        $row = $this->db->queryOne(
            'SELECT o.docx_path, o.template_type, r.job_id, u.user_id AS uploader_id, u.customer_id
             FROM tr_outputs o
             JOIN tr_results r ON r.id = o.result_id
             JOIN tr_jobs j ON j.id = r.job_id
             JOIN tr_uploads u ON u.id = j.upload_id
             WHERE o.id=?',
            [$outputId]
        );
        if (!$row || !$row['docx_path'] || !is_file($row['docx_path'])) return null;

        // Pfad-Traversal-Schutz: muss unter STORAGE_PATH liegen
        $real = realpath($row['docx_path']);
        if (!$real || strpos($real, STORAGE_PATH . '/transkription/exports') !== 0) return null;

        return [
            'path'     => $real,
            'filename' => 'transkription-' . $row['template_type'] . '-' . $row['job_id'] . '.docx',
        ];
    }

    /* ====================================================================
       Wissens-DB-Integration
       ==================================================================== */

    /**
     * Speist den Output (oder bei null den Rohtranskript) als Knowledge-Doc ein.
     * Returnt die knowledge_documents.id.
     */
    public function sendToKnowledge(int $jobId, ?int $outputId, int $userId): int
    {
        require_once SERVICES_PATH . '/AIService.php';
        require_once SERVICES_PATH . '/KnowledgeEmbeddingService.php';
        require_once SERVICES_PATH . '/KnowledgeExtractionService.php';
        require_once SERVICES_PATH . '/KnowledgeIngestService.php';

        $job = $this->db->queryOne(
            'SELECT j.id, j.upload_id, u.customer_id, u.filename, u.source, u.source_url,
                    r.transcript_text, r.duration_sec, r.language_detected, r.id AS result_id
             FROM tr_jobs j
             JOIN tr_uploads u ON u.id = j.upload_id
             JOIN tr_results r ON r.job_id = j.id
             WHERE j.id=?',
            [$jobId]
        );
        if (!$job) throw new \RuntimeException('Job nicht gefunden oder noch ohne Result');

        $title = 'Transkript: ' . preg_replace('/\.[a-z0-9]+\.enc$/i', '', $job['filename']);
        if ($outputId) {
            $out = $this->db->queryOne('SELECT template_type, output_text FROM tr_outputs WHERE id=?', [$outputId]);
            if (!$out) throw new \RuntimeException('Output nicht gefunden');
            $text = (string)$out['output_text'];
            $title .= ' (' . $out['template_type'] . ')';
        } else {
            $text = (string)$job['transcript_text'];
        }
        if (trim($text) === '') throw new \RuntimeException('Leerer Text — nichts zum Einspeisen');

        $openaiKey = (string)Settings::get('openai_api_key');
        if ($openaiKey === '') throw new \RuntimeException('OpenAI-Key nicht konfiguriert (fuer LLM-Extraktion)');

        // Embeddings macht der qdrant_sync-Worker via bge-m3 (lokal) — kein OpenAI-Embed-Call mehr.
        $extractAI  = new AIService($openaiKey, 'openai');
        $extraction = new KnowledgeExtractionService($extractAI);
        $ingest     = new KnowledgeIngestService($this->db, null, $extraction);

        $customerName = null;
        if ($job['customer_id']) {
            $row = $this->db->queryOne('SELECT name FROM customers WHERE id=?', [(int)$job['customer_id']]);
            $customerName = $row['name'] ?? null;
        }

        $prepared = $ingest->prepare($text, ['customer_name' => $customerName]);
        $docId = $ingest->commit(
            $prepared,
            [
                'title'       => $title,
                'description' => $prepared['metadata']['description'] ?? null,
                'customer_id' => $job['customer_id'] ? (int)$job['customer_id'] : null,
                'category'    => $prepared['metadata']['category'] ?? 'transcript',
                'tags'        => array_merge(
                    (array)($prepared['metadata']['tags'] ?? []),
                    ['transkript']
                ),
            ],
            [
                'source_type' => 'transcript',
                'source_ref'  => $job['source'] === 'loom' ? (string)$job['source_url'] : ('tr_job:' . $jobId),
                'external_id' => $outputId ? ('tr_output:' . $outputId) : ('tr_job:' . $jobId),
                'created_by'  => $userId,
            ]
        );

        // Output ggf. mit knowledge_doc_id verknuepfen
        if ($outputId) {
            $this->db->execute('UPDATE tr_outputs SET knowledge_doc_id=? WHERE id=?', [$docId, $outputId]);
        }

        return $docId;
    }

    /* ====================================================================
       Intern
       ==================================================================== */

    private function buildPrompText(array $result): string
    {
        $segments = json_decode((string)$result['segments_json'], true) ?: [];
        if (!$segments) return (string)$result['transcript_text'];

        // Sprecher-Mapping aus tr_speakers ziehen
        $speakers = $this->db->query(
            'SELECT label_internal, name_custom FROM tr_speakers WHERE result_id=?',
            [(int)$result['id']]
        );
        $nameMap = [];
        foreach ($speakers as $s) {
            $nameMap[(string)$s['label_internal']] = (string)($s['name_custom'] ?: $s['label_internal']);
        }

        $lines = [];
        foreach ($segments as $seg) {
            $sp  = $nameMap[$seg['speaker'] ?? 'SPEAKER_00'] ?? ($seg['speaker'] ?? 'Sprecher');
            $ts  = sprintf('[%s]', self::fmtTime((float)($seg['start'] ?? 0)));
            $txt = trim((string)($seg['text'] ?? ''));
            if ($txt !== '') $lines[] = "$ts $sp: $txt";
        }
        return implode("\n", $lines);
    }

    private static function fmtTime(float $sec): string
    {
        $s = (int)round($sec);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $r = $s % 60;
        return $h > 0
            ? sprintf('%02d:%02d:%02d', $h, $m, $r)
            : sprintf('%02d:%02d', $m, $r);
    }

    private function callLLM(string $systemPrompt, string $transcript): string
    {
        $openaiKey = (string)Settings::get('openai_api_key');
        if ($openaiKey === '') throw new \RuntimeException('OpenAI-Key nicht konfiguriert');

        require_once SERVICES_PATH . '/AIService.php';
        $ai = new AIService($openaiKey, 'openai');
        $ai->setModel((string)(Settings::get('default_model') ?: 'gpt-4o-mini'));
        $ai->setMaxTokens(4000);

        $response = $ai->chat(
            [['role' => 'user', 'content' => "Hier ist das Transkript:\n\n" . $transcript]],
            $systemPrompt
        );
        return (string)($response['content'] ?? $response['text'] ?? '');
    }

    private function renderDocx(int $jobId, string $type, string $markdown): string
    {
        $dir = STORAGE_PATH . '/transkription/exports';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $base = $dir . '/job-' . $jobId . '-' . $type . '-' . date('Ymd-His');

        // Pandoc direkt versuchen — is_executable() laeuft hier gegen
        // open_basedir-Restriction. exec() selbst ist nicht limitiert.
        foreach (['/usr/bin/pandoc', '/usr/local/bin/pandoc', 'pandoc'] as $pandoc) {
            $mdFile  = $base . '.md';
            $docxFile = $base . '.docx';
            file_put_contents($mdFile, $markdown);
            $cmd = sprintf(
                '%s -f markdown -t docx -o %s %s 2>&1',
                escapeshellarg($pandoc),
                escapeshellarg($docxFile),
                escapeshellarg($mdFile)
            );
            $out = [];
            exec($cmd, $out, $rc);
            @unlink($mdFile);
            if ($rc === 0 && is_file($docxFile) && filesize($docxFile) > 100) {
                @chmod($docxFile, 0640);
                return $docxFile;
            }
            // Fehlversuch wegraeumen, naechste Variante probieren
            @unlink($docxFile);
        }

        // Fallback: HTML-Datei mit .docx-Endung (Word oeffnet das)
        $htmlFile = $base . '.docx';
        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body>"
              . '<pre style="font-family:Frutiger,Calibri,sans-serif;white-space:pre-wrap;">'
              . htmlspecialchars($markdown)
              . '</pre></body></html>';
        file_put_contents($htmlFile, $html);
        @chmod($htmlFile, 0640);
        return $htmlFile;
    }
}
