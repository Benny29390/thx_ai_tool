<?php
namespace Services;

use Core\Auth;
use Core\Crypto;
use Core\Database;

/**
 * TranskriptionService — Upload, Loom-Import, Job-Verwaltung.
 *
 * Briefing: docs/Transkription_Briefing.md
 * Discovery: docs/transkription-discovery.md
 *
 * Roh-Uploads landen at-rest verschluesselt unter storage/transkription/uploads/.
 * Der Worker (Block 4) extrahiert daraus normalisiertes WAV nach audio/ und
 * loescht die Quelle nach erfolgreicher Transkription.
 */
class TranskriptionService
{
    public const MAX_UPLOAD_BYTES = 500 * 1024 * 1024;   // 500 MB
    public const ACCEPTED_EXT = ['mp3','m4a','wav','ogg','flac','aac','webm','mp4','mov','mkv'];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ====================================================================
       UPLOAD
       ==================================================================== */

    /**
     * Speichert eine hochgeladene Datei verschluesselt im Storage und legt
     * Upload- + Job-Datensatz an.
     *
     * @param string $tmpPath    Server-seitiger temporaerer Pfad (move_uploaded_file)
     * @param string $origName   Original-Dateiname
     * @param int    $userId     Eingeloggter User
     * @param array  $opts {
     *   customer_id?:   int|null
     *   model?:         string   (whisper-Modell: tiny|base|small|medium|large-v3)
     *   language?:      string|null
     *   speaker_mode?:  'single'|'multi'
     *   template_type?: string|null
     *   consent_ref?:   string|null
     * }
     * @return array { upload_id, job_id }
     */
    public function ingestUpload(string $tmpPath, string $origName, int $userId, array $opts = []): array
    {
        if (!is_file($tmpPath)) {
            throw new \RuntimeException('Upload-Datei nicht gefunden');
        }
        $size = filesize($tmpPath);
        if ($size <= 0) {
            throw new \RuntimeException('Upload ist leer');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new \RuntimeException('Datei zu gross (Limit: ' . round(self::MAX_UPLOAD_BYTES/1024/1024) . ' MB)');
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ACCEPTED_EXT, true)) {
            throw new \RuntimeException('Format nicht unterstuetzt (.' . $ext . '). Erlaubt: ' . implode(', ', self::ACCEPTED_EXT));
        }

        $customerId = $this->resolveCustomerId($opts['customer_id'] ?? null);

        $storagePath = $this->writeEncrypted($tmpPath, $ext);
        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';

        return $this->createJobFromSource([
            'user_id'      => $userId,
            'customer_id'  => $customerId,
            'filename'     => $origName,
            'storage_path' => $storagePath,
            'filesize'     => $size,
            'mime_type'    => $mime,
            'source'       => 'upload',
            'source_url'   => null,
            'encrypted'    => 1,
            'consent_ref'  => $opts['consent_ref'] ?? null,
        ], $opts);
    }

    /**
     * Loom-URL: nur Job einreihen. Der Worker-Cron laedt das Audio im
     * Hintergrund (vermeidet open_basedir-Probleme bei Web-Requests und
     * Apache-Timeouts bei langen Aufzeichnungen).
     */
    public function ingestLoomUrl(string $url, int $userId, array $opts = []): array
    {
        if (!preg_match('#^https?://(www\.)?loom\.com/(share|embed)/[a-z0-9]+#i', $url)) {
            throw new \RuntimeException('Keine gueltige Loom-URL');
        }
        // Duplikat-Schutz: gleiche Loom-URL nicht doppelt einreihen
        $existingJobId = (int)$this->db->queryValue(
            "SELECT j.id FROM tr_uploads u JOIN tr_jobs j ON j.upload_id=u.id
             WHERE u.source='loom' AND u.source_url=? ORDER BY j.id DESC LIMIT 1",
            [$url]
        );
        if ($existingJobId > 0) {
            throw new \RuntimeException('Diese Loom-URL ist bereits Job #' . $existingJobId . ' — Duplikat uebersprungen');
        }
        $customerId = $this->resolveCustomerId($opts['customer_id'] ?? null);

        $videoId = '';
        if (preg_match('#/(share|embed)/([a-z0-9]+)#i', $url, $m)) {
            $videoId = $m[2];
        }

        // Versuch: Loom-Titel schon beim Einreihen ziehen (Timeout 8s, falls Loom traege).
        // Wenn das fehlschlaegt, holt der Worker den Titel spaeter nach.
        $loomTitle = $this->fetchLoomTitle($url, 8);

        return $this->createJobFromSource([
            'user_id'      => $userId,
            'customer_id'  => $customerId,
            'filename'     => 'loom-' . ($videoId ?: 'video') . '.m4a',
            'title'        => $loomTitle ?: null,
            'storage_path' => null,            // Worker fuellt das nach dem Download
            'filesize'     => 0,
            'mime_type'    => 'audio/mp4',
            'source'       => 'loom',
            'source_url'   => $url,
            'encrypted'    => 1,
            'consent_ref'  => $opts['consent_ref'] ?? null,
        ], $opts);
    }

    /**
     * Liste der Uploads/Jobs fuer einen User (gefiltert ueber Kunden-Zuordnung).
     */
    public function listJobs(int $userId, array $filter = []): array
    {
        $customerIds = array_column(Auth::customers(), 'id');
        $isAdmin = Auth::isAdmin();

        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = '(u.user_id = ? OR ' . ($customerIds ? ('u.customer_id IN (' . implode(',', array_fill(0, count($customerIds), '?')) . ')') : '0') . ')';
            $params[] = $userId;
            foreach ($customerIds as $cid) $params[] = (int)$cid;
        }
        if (!empty($filter['status'])) {
            $where[] = 'j.status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['customer_id'])) {
            $where[] = 'u.customer_id = ?';
            $params[] = (int)$filter['customer_id'];
        }

        $sql = "SELECT j.id AS job_id, j.upload_id, j.status, j.model, j.language, j.speaker_mode,
                       j.template_type, j.progress_pct, j.progress_note,
                       j.retry_count, j.next_retry_at,
                       j.started_at, j.finished_at,
                       j.error_message, j.created_at,
                       u.filename, u.title, u.filesize, u.duration_sec, u.source, u.source_url,
                       u.customer_id, c.name AS customer_name,
                       us.name AS user_name,
                       r.id AS result_id, r.speaker_count, r.word_count, r.language_detected
                FROM tr_jobs j
                JOIN tr_uploads u ON u.id = j.upload_id
                LEFT JOIN customers c ON c.id = u.customer_id
                LEFT JOIN users us ON us.id = u.user_id
                LEFT JOIN tr_results r ON r.job_id = j.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY j.created_at DESC
                LIMIT 200";

        return $this->db->query($sql, $params);
    }

    /**
     * Job in die Queue zurueckschicken (manueller Retry).
     * Setzt retry_count auf 0 und next_retry_at auf NULL, damit der Worker sofort dran ist.
     */
    public function retryJob(int $jobId, int $userId): bool
    {
        $job = $this->getJob($jobId, $userId);
        if (!$job) return false;
        $this->db->execute(
            "UPDATE tr_jobs
             SET status='queued', retry_count=0, next_retry_at=NULL,
                 started_at=NULL, finished_at=NULL, progress_pct=0,
                 progress_note='Manueller Neustart', error_message=NULL
             WHERE id=?",
            [$jobId]
        );
        return true;
    }

    public function updateTitle(int $jobId, int $userId, string $title): bool
    {
        $job = $this->getJob($jobId, $userId);
        if (!$job) return false;
        $this->db->execute('UPDATE tr_uploads SET title=? WHERE id=?', [$title !== '' ? $title : null, (int)$job['upload_id']]);
        return true;
    }

    public function getJob(int $jobId, int $userId): ?array
    {
        $row = $this->db->queryOne(
            "SELECT j.*, u.filename, u.filesize, u.duration_sec, u.source, u.source_url,
                    u.customer_id, u.user_id AS uploader_id
             FROM tr_jobs j JOIN tr_uploads u ON u.id = j.upload_id
             WHERE j.id = ? LIMIT 1",
            [$jobId]
        );
        if (!$row) return null;
        if (!$this->canAccessJob($row, $userId)) return null;
        return $row;
    }

    /**
     * Job loeschen — inkl. Storage-Datei, Ergebnis, Outputs.
     */
    public function deleteJob(int $jobId, int $userId): bool
    {
        $job = $this->getJob($jobId, $userId);
        if (!$job) return false;

        // Storage-Datei der Upload-Quelle entfernen
        $upload = $this->db->queryOne('SELECT storage_path FROM tr_uploads WHERE id=?', [(int)$job['upload_id']]);
        if ($upload && !empty($upload['storage_path']) && is_file($upload['storage_path'])) {
            @unlink($upload['storage_path']);
        }
        // Audio-Derivat (WAV) loeschen
        $audioPath = STORAGE_PATH . '/transkription/audio/job-' . $jobId . '.wav';
        if (is_file($audioPath)) @unlink($audioPath);

        // DB cascaded via FK ON DELETE CASCADE
        $this->db->execute('DELETE FROM tr_jobs WHERE id=?', [$jobId]);
        $this->db->execute('DELETE FROM tr_uploads WHERE id=?', [(int)$job['upload_id']]);
        return true;
    }

    /* ====================================================================
       INTERN
       ==================================================================== */

    /**
     * Loest die zu verwendende customer_id auf.
     * - Nicht-Admins muessen einen ihnen zugewiesenen Kunden nehmen.
     * - Admins duerfen jede ID setzen (oder null lassen).
     */
    private function resolveCustomerId(?int $requested): ?int
    {
        if ($requested === null || $requested === 0) {
            // Default: aktiver Kunde aus Session
            $active = Auth::customerId();
            return $active ?: null;
        }
        if (Auth::isAdmin()) return $requested;
        $ids = array_map('intval', array_column(Auth::customers(), 'id'));
        if (!in_array($requested, $ids, true)) {
            throw new \RuntimeException('Kein Zugriff auf den gewaehlten Kunden');
        }
        return $requested;
    }

    /**
     * Schreibt $srcPath verschluesselt nach storage/transkription/uploads/.
     * Returnt absoluten Storage-Pfad.
     */
    private function writeEncrypted(string $srcPath, string $ext): string
    {
        $dir = STORAGE_PATH . '/transkription/uploads';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Storage-Verzeichnis konnte nicht angelegt werden');
            }
        }
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $ext . '.enc';
        $dst  = $dir . '/' . $name;

        // Streaming-Verschluesselung: Datei einlesen, mit Core\Crypto verschluesseln, schreiben.
        // Achtung: Crypto verschluesselt In-Memory — fuer 500 MB tragbar (16 GB RAM).
        // Spaeter koennte hier ein Chunked-Mode rein, aktuell pragmatisch.
        $plain = file_get_contents($srcPath);
        if ($plain === false) {
            throw new \RuntimeException('Quelldatei konnte nicht gelesen werden');
        }
        $cipher = Crypto::encrypt($plain);
        if (file_put_contents($dst, $cipher) === false) {
            throw new \RuntimeException('Verschluesselte Datei konnte nicht geschrieben werden');
        }
        @chmod($dst, 0640);
        return $dst;
    }

    /**
     * Hilfs-Setup: Upload-Datensatz + Job-Datensatz in einer Transaktion.
     */
    private function createJobFromSource(array $upload, array $opts): array
    {
        $this->db->beginTransaction();
        try {
            $uploadId = (int)$this->db->insert('tr_uploads', $upload);

            $model = $opts['model'] ?? 'medium';
            $allowedModels = ['tiny','base','small','medium','large-v3'];
            if (!in_array($model, $allowedModels, true)) $model = 'medium';

            $speakerMode = $opts['speaker_mode'] ?? 'multi';
            if (!in_array($speakerMode, ['single','multi'], true)) $speakerMode = 'multi';

            // Transkriptions-Backend pro Job: 'local' (Python-Runner, mit Sprecher-Trennung)
            // oder 'remote' (GPU-Whisper via HTTP, schneller, ohne Diarisierung).
            $backend = $opts['transcription_backend'] ?? 'local';
            if (!in_array($backend, ['local','remote'], true)) $backend = 'local';

            // Auto-Pipeline-Defaults aus Settings ziehen, falls nicht explizit gesetzt
            $autoTpls = $opts['auto_templates'] ?? null;
            if ($autoTpls === null) {
                $autoTpls = (string)\Core\Settings::get('transkription_default_templates');
            }
            if (is_array($autoTpls)) $autoTpls = implode(',', $autoTpls);
            $autoTpls = $autoTpls !== '' ? $autoTpls : null;

            $autoKnowledgeTpl = $opts['auto_knowledge_template'] ?? null;
            if ($autoKnowledgeTpl === null) {
                $v = (string)\Core\Settings::get('transkription_default_knowledge_template');
                $autoKnowledgeTpl = $v !== '' ? $v : null;
            }

            $jobId = (int)$this->db->insert('tr_jobs', [
                'upload_id'               => $uploadId,
                'status'                  => 'queued',
                'model'                   => $model,
                'transcription_backend'   => $backend,
                'language'                => $opts['language'] ?? null,
                'speaker_mode'            => $speakerMode,
                'template_type'           => $opts['template_type'] ?? null,
                'auto_templates'          => $autoTpls,
                'auto_knowledge_template' => $autoKnowledgeTpl,
                'auto_status'             => $autoTpls ? 'pending' : null,
                'progress_pct'            => 0,
            ]);

            $this->db->commit();
            return ['upload_id' => $uploadId, 'job_id' => $jobId];
        } catch (\Throwable $e) {
            $this->db->rollback();
            // Storage-Datei wieder loeschen, wenn DB fehlschlug
            if (!empty($upload['storage_path']) && is_file($upload['storage_path'])) {
                @unlink($upload['storage_path']);
            }
            throw $e;
        }
    }

    /**
     * Liest per yt-dlp den Loom-Videotitel. Returnt '' bei Fehler.
     * Kurzer Timeout, damit der Web-Request nicht haengt.
     */
    private function fetchLoomTitle(string $url, int $timeoutSeconds = 8): string
    {
        $ytdlp = '/opt/ki-tool-whisper/venv/bin/yt-dlp';
        // exec() selbst ist NICHT durch open_basedir limitiert — nur PHP-FS-Funktionen.
        // Daher direkt aufrufen, ohne is_file() davor.
        $cmd = sprintf(
            'timeout %d %s --no-warnings --skip-download --print "%%(title)s" %s 2>/dev/null',
            $timeoutSeconds,
            escapeshellarg($ytdlp),
            escapeshellarg($url)
        );
        $out = [];
        @exec($cmd, $out, $rc);
        if ($rc !== 0) return '';
        $title = trim(implode("\n", $out));
        if ($title === '' || $title === 'NA') return '';
        return substr($title, 0, 255);
    }

    private function canAccessJob(array $row, int $userId): bool
    {
        if (Auth::isAdmin()) return true;
        if ((int)($row['uploader_id'] ?? $row['user_id'] ?? 0) === $userId) return true;
        $customerIds = array_map('intval', array_column(Auth::customers(), 'id'));
        return in_array((int)($row['customer_id'] ?? 0), $customerIds, true);
    }
}
