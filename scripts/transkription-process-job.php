<?php
/**
 * transkription-process-job.php — verarbeitet einen einzelnen Transkriptions-Job.
 *
 * Aufruf: php transkription-process-job.php <job_id>
 *
 * Wird vom Worker-Cron (transkription-worker.php) als Hintergrund-Subprozess
 * gestartet. Erwartet, dass der Job bereits auf status='running' gelockt wurde.
 *
 * Pipeline pro Job:
 *   1) Upload entschluesseln in temporaere Datei
 *   2) ffmpeg → WAV mono 16 kHz (Whisper-optimal)
 *   3) Python-Skript whisper-runner.py aufrufen, JSON-Output parsen
 *   4) tr_results + tr_speakers schreiben, Job auf 'done'
 *   5) Cleanup: Tempfiles loeschen
 *
 * Fehler werden in tr_jobs.error_message vermerkt, status='failed'.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Crypto.php';

$config = require __DIR__ . '/../config/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$jobId = (int)($argv[1] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Usage: transkription-process-job.php <job_id>\n");
    exit(2);
}

$log = function(string $msg) use ($jobId) {
    $line = '[' . date('Y-m-d H:i:s') . "] [job $jobId] $msg\n";
    fwrite(STDERR, $line);
    @file_put_contents(STORAGE_PATH . '/logs/transkription.log', $line, FILE_APPEND);
};

$job = $db->queryOne(
    'SELECT j.*, u.storage_path, u.filename, u.encrypted, u.source, u.user_id AS uploader_id
     FROM tr_jobs j JOIN tr_uploads u ON u.id = j.upload_id
     WHERE j.id = ? LIMIT 1',
    [$jobId]
);
if (!$job) {
    $log("Job nicht gefunden");
    exit(3);
}

$tmpDir = sys_get_temp_dir() . '/tr-job-' . $jobId . '-' . bin2hex(random_bytes(4));
if (!mkdir($tmpDir, 0700) && !is_dir($tmpDir)) {
    failJob($db, $jobId, 'Konnte Temp-Verzeichnis nicht anlegen');
    exit(4);
}

$cleanup = function() use ($tmpDir) {
    foreach (glob($tmpDir . '/*') ?: [] as $f) @unlink($f);
    @rmdir($tmpDir);
};

try {
    // ---- 0) Loom-Download (falls noch kein storage_path) ----
    if ($job['source'] === 'loom' && empty($job['storage_path'])) {
        $log('loom-download startet …');
        updateProgress($db, $jobId, 2);
        $sourceUrl = (string)$db->queryValue('SELECT source_url FROM tr_uploads WHERE id=?', [(int)$job['upload_id']]);
        if (!$sourceUrl) throw new \RuntimeException('Loom-URL fehlt');

        $ytdlp = '/opt/ki-tool-whisper/venv/bin/yt-dlp';
        if (!is_file($ytdlp)) $ytdlp = 'yt-dlp';

        // Zuerst: Metadaten holen (Titel), bevor wir das Audio laden.
        // Hat eigener Job noch keinen User-gesetzten Titel, uebernehmen wir den Loom-Titel.
        $existingTitle = (string)$db->queryValue('SELECT title FROM tr_uploads WHERE id=?', [(int)$job['upload_id']]);
        if ($existingTitle === '') {
            $metaCmd = sprintf(
                '%s --no-warnings --skip-download --print "%%(title)s" %s 2>/dev/null',
                escapeshellarg($ytdlp),
                escapeshellarg($sourceUrl)
            );
            $metaOut = [];
            exec($metaCmd, $metaOut, $metaRc);
            $loomTitle = trim(implode("\n", $metaOut));
            if ($metaRc === 0 && $loomTitle !== '' && $loomTitle !== 'NA') {
                $db->execute('UPDATE tr_uploads SET title=? WHERE id=?',
                    [substr($loomTitle, 0, 255), (int)$job['upload_id']]);
                $log('loom-titel: ' . $loomTitle);
            }
        }

        // Das ganze Output-Template als ein Argument quoten — sonst frisst die
        // Shell die Klammern in %(ext)s.
        $outputTpl = $tmpDir . '/audio.%(ext)s';
        // -f "ba/b": bestes Audio, sonst bester verfuegbarer Stream (auch video-only HLS).
        // Bei Loom-Aufnahmen gibt es haeufig kein dediziertes Audio-Format — ffmpeg
        // extrahiert dann den Audio-Track via -x --audio-format m4a.
        $cmd = sprintf(
            '%s --no-warnings --no-progress -f "ba/b" -x --audio-format m4a -o %s %s 2>&1',
            escapeshellarg($ytdlp),
            escapeshellarg($outputTpl),
            escapeshellarg($sourceUrl)
        );
        exec($cmd, $ytOut, $ytRc);
        if ($ytRc !== 0) {
            $errLines = array_filter($ytOut, fn($l) => stripos($l, 'ERROR') !== false || stripos($l, 'WARNING') !== false);
            $msg = $errLines ? implode(' | ', array_slice($errLines, -3))
                             : implode(' | ', array_slice($ytOut, -3));
            $msg = trim($msg);

            // Loom braucht nach Upload ein paar Minuten zum Transcoden — bestimmte
            // Fehlersignaturen heissen „nochmal spaeter probieren", nicht „endgueltig kaputt".
            $retryable = (
                stripos($msg, 'Requested format is not available') !== false ||
                stripos($msg, 'processing') !== false ||
                stripos($msg, 'GraphQL') !== false ||
                stripos($msg, 'HTTP Error 5') !== false ||
                stripos($msg, 'unable to download') !== false
            );
            if ($retryable) {
                scheduleRetry($db, $jobId, 'yt-dlp: ' . $msg);
                $cleanup();
                exit(0);
            }
            throw new \RuntimeException('yt-dlp Exit ' . $ytRc . ': ' . $msg);
        }
        $glob = glob($tmpDir . '/audio.*');
        if (!$glob) throw new \RuntimeException('yt-dlp lieferte keine Audiodatei');
        $downloaded = $glob[0];
        $ext = strtolower(pathinfo($downloaded, PATHINFO_EXTENSION)) ?: 'm4a';

        // Verschluesselt nach Storage
        $uploadsDir = STORAGE_PATH . '/transkription/uploads';
        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
        $name = date('Ymd-His') . '-loom-' . bin2hex(random_bytes(6)) . '.' . $ext . '.enc';
        $dst = $uploadsDir . '/' . $name;
        $plain = file_get_contents($downloaded);
        if ($plain === false) throw new \RuntimeException('Loom-Download nicht lesbar');
        $cipher = \Core\Crypto::encrypt($plain);
        if (file_put_contents($dst, $cipher) === false) {
            throw new \RuntimeException('Verschluesselte Loom-Datei konnte nicht geschrieben werden');
        }
        unset($plain, $cipher);
        @chmod($dst, 0640);
        @unlink($downloaded);

        $size = filesize($dst) ?: 0;
        $db->execute('UPDATE tr_uploads SET storage_path=?, filesize=?, mime_type=? WHERE id=?',
            [$dst, $size, 'audio/mp4', (int)$job['upload_id']]);
        $job['storage_path'] = $dst;
        $log('loom-download fertig (' . round($size/1024/1024, 1) . ' MB)');
    }

    // ---- 1) Entschluesseln ----
    $storagePath = (string)$job['storage_path'];
    if (!is_file($storagePath)) {
        throw new \RuntimeException('Upload-Datei fehlt: ' . $storagePath);
    }
    $ext = strtolower(pathinfo(rtrim($storagePath, '.enc'), PATHINFO_EXTENSION)) ?: 'bin';
    $decryptedPath = $tmpDir . '/source.' . $ext;

    if ((int)$job['encrypted'] === 1) {
        $cipher = file_get_contents($storagePath);
        if ($cipher === false) throw new \RuntimeException('Kann Storage-Datei nicht lesen');
        $plain = \Core\Crypto::decrypt($cipher);
        if (file_put_contents($decryptedPath, $plain) === false) {
            throw new \RuntimeException('Konnte entschluesselte Datei nicht schreiben');
        }
        unset($cipher, $plain);
    } else {
        if (!copy($storagePath, $decryptedPath)) {
            throw new \RuntimeException('Kopie der Quelldatei fehlgeschlagen');
        }
    }
    $log('entschluesselt → ' . filesize($decryptedPath) . ' bytes');

    // Progress: 10%
    updateProgress($db, $jobId, 10);

    // ---- 2) ffmpeg → WAV mono 16 kHz ----
    $wavPath = STORAGE_PATH . '/transkription/audio/job-' . $jobId . '.wav';
    @unlink($wavPath); // vorhandenes Derivat ueberschreiben
    $cmd = sprintf(
        'ffmpeg -nostdin -y -i %s -vn -ac 1 -ar 16000 -c:a pcm_s16le %s 2>&1',
        escapeshellarg($decryptedPath),
        escapeshellarg($wavPath)
    );
    exec($cmd, $out, $rc);
    if ($rc !== 0 || !is_file($wavPath)) {
        throw new \RuntimeException('ffmpeg-Normalisierung fehlgeschlagen: ' . trim(implode("\n", array_slice($out, -10))));
    }
    @chmod($wavPath, 0640);
    // Source-Datei kann ab hier weg (Klartext nicht laenger als noetig halten)
    @unlink($decryptedPath);

    // Dauer aus WAV-Header ermitteln (ffprobe-light)
    $durationSec = null;
    $probe = [];
    exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($wavPath) . ' 2>&1', $probe, $rcP);
    if ($rcP === 0 && !empty($probe[0])) {
        $durationSec = (float)$probe[0];
        $db->execute('UPDATE tr_uploads SET duration_sec=? WHERE id=?', [$durationSec, (int)$job['upload_id']]);
    }
    $log('normalisiert → WAV ' . ($durationSec ? round($durationSec, 1) . 's' : '?s'));

    updateProgress($db, $jobId, 25);

    // ---- 3) Whisper ----
    $model    = (string)$job['model'];
    $language = $job['language'] ?: null;
    $diarize  = ($job['speaker_mode'] === 'multi');
    $backend  = (string)($job['transcription_backend'] ?? 'local');

    if ($backend === 'remote') {
        // ---- Remote-GPU-Whisper (OpenAI-kompatibler HTTP-Endpoint, ohne Sprecher-Trennung) ----
        $log('whisper start (remote GPU): lang=' . ($language ?: 'auto'));
        $db->execute(
            'UPDATE tr_jobs SET progress_pct=?, progress_note=? WHERE id=?',
            [40, 'Remote-Whisper (GPU) laeuft … erster Call kann 2–5 Min dauern', $jobId]
        );
        $result = transcribeRemoteWhisper($wavPath, $language, $log);
        $log('whisper done (remote): ' . count($result['segments']) . ' Segmente');
        updateProgress($db, $jobId, 90);
    } else {

    $pythonBin = '/opt/ki-tool-whisper/venv/bin/python';
    $runner    = '/opt/ki-tool-whisper/whisper-runner.py';
    $args = [
        escapeshellarg($pythonBin),
        escapeshellarg($runner),
        escapeshellarg($wavPath),
        escapeshellarg($model),
    ];
    if ($language) { $args[] = '--language ' . escapeshellarg($language); }
    if ($diarize)  { $args[] = '--diarize'; }
    $whisperCmd = implode(' ', $args);

    $log('whisper start: model=' . $model . ' lang=' . ($language ?: 'auto') . ' diarize=' . ($diarize ? '1' : '0'));

    $descSpec = [
        1 => ['pipe', 'w'],   // stdout
        2 => ['pipe', 'w'],   // stderr
    ];
    $progressFile = $tmpDir . '/progress.json';
    // Minimal-Env an Subprozess: nur was Python wirklich braucht.
    $env = [
        'PATH'             => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        'HOME'             => getenv('HOME') ?: '/var/www',
        'HF_HOME'          => '/opt/ki-tool-whisper/models',
        'TR_PROGRESS_FILE' => $progressFile,
    ];
    try {
        require_once CORE_PATH . '/Settings.php';
        $hfToken = (string)\Core\Settings::get('huggingface_token');
        if ($hfToken !== '') $env['HF_TOKEN'] = $hfToken;
    } catch (\Throwable $e) { /* Settings-Klasse evtl. nicht geladen */ }

    $proc = proc_open($whisperCmd, $descSpec, $pipes, null, $env);
    if (!is_resource($proc)) {
        throw new \RuntimeException('Whisper-Prozess konnte nicht gestartet werden');
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $startedAt = microtime(true);
    $lastDbWrite = 0.0;

    while (true) {
        $status = proc_get_status($proc);
        $chunk1 = stream_get_contents($pipes[1]); if ($chunk1 !== false) $stdout .= $chunk1;
        $chunk2 = stream_get_contents($pipes[2]); if ($chunk2 !== false) $stderr .= $chunk2;
        if (!$status['running']) break;

        $now = microtime(true);
        if (($now - $lastDbWrite) > 2.0) {
            $lastDbWrite = $now;
            $pct = 25; // Start nach ffmpeg
            $note = null;
            if (is_file($progressFile)) {
                $hb = json_decode((string)file_get_contents($progressFile), true);
                if (is_array($hb)) {
                    // 0..95 (von Python) auf 25..90 (DB-Spanne fuer „Whisper laeuft")
                    $rawPct = max(0, min(95, (int)($hb['pct'] ?? 0)));
                    $pct = 25 + (int)round(($rawPct / 95) * 65);
                    $processed = (float)($hb['processed'] ?? 0);
                    $total = (float)($hb['total'] ?? 0);
                    if ($total > 0) {
                        $eta = $processed > 0
                            ? (($now - $startedAt) / $processed) * ($total - $processed)
                            : 0;
                        $note = fmtTime($processed) . ' / ' . fmtTime($total)
                              . ' · ETA ~' . fmtTime($eta);
                    }
                }
            }
            if (!$note) $note = 'Whisper laeuft (' . fmtTime((int)($now - $startedAt)) . ')';
            $db->execute('UPDATE tr_jobs SET progress_pct=?, progress_note=? WHERE id=?', [$pct, $note, $jobId]);
        }
        usleep(250000); // 250ms
    }

    // Rest aus den Pipes (nach Prozess-Ende)
    $rest1 = stream_get_contents($pipes[1]); if ($rest1 !== false) $stdout .= $rest1;
    $rest2 = stream_get_contents($pipes[2]); if ($rest2 !== false) $stderr .= $rest2;
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    if ($exit !== 0) {
        throw new \RuntimeException('Whisper-Exit ' . $exit . ': ' . trim($stderr));
    }
    $result = json_decode($stdout, true);
    if (!is_array($result) || !isset($result['segments'])) {
        throw new \RuntimeException('Whisper-Output unparsbar: ' . substr($stdout, 0, 300));
    }
    $log('whisper done: ' . count($result['segments']) . ' Segmente, ' . count($result['speakers'] ?? []) . ' Sprecher');
    updateProgress($db, $jobId, 90);
    } // end lokales Backend

    // ---- 4) Ergebnis in DB ----
    $transcriptText = (string)($result['text'] ?? '');
    $wordCount = str_word_count(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $transcriptText) ?? '');
    $resultId = (int)$db->insert('tr_results', [
        'job_id'             => $jobId,
        'transcript_text'    => $transcriptText,
        'segments_json'      => json_encode($result['segments'], JSON_UNESCAPED_UNICODE),
        'speaker_count'      => count($result['speakers'] ?? []),
        'language_detected'  => $result['language'] ?? null,
        'word_count'         => $wordCount,
        'duration_sec'       => isset($result['duration']) ? (float)$result['duration'] : ($durationSec ?? 0),
    ]);
    foreach (($result['speakers'] ?? []) as $sp) {
        $db->insert('tr_speakers', [
            'result_id'      => $resultId,
            'label_internal' => $sp,
            'name_custom'    => null,
        ]);
    }
    $db->execute(
        "UPDATE tr_jobs SET status='done', finished_at=NOW(), progress_pct=100, progress_note=NULL, error_message=NULL WHERE id=?",
        [$jobId]
    );

    // ---- 5) Auto-Pipeline: konfigurierte Outputs erzeugen + ggf. ins Wissen ----
    $autoTemplates = trim((string)($job['auto_templates'] ?? ''));
    if ($autoTemplates !== '') {
        $log('auto-pipeline: ' . $autoTemplates);
        $db->execute("UPDATE tr_jobs SET auto_status='running' WHERE id=?", [$jobId]);
        require_once SERVICES_PATH . '/TranskriptionOutputService.php';
        $outSvc = new \Services\TranskriptionOutputService($db);

        $ok = 0; $fail = 0; $autoErrors = [];
        $outputIdByType = [];
        foreach (array_filter(array_map('trim', explode(',', $autoTemplates))) as $tpl) {
            try {
                $res = $outSvc->generate($jobId, $tpl, (int)($job['uploader_id'] ?? 0));
                $outputIdByType[$tpl] = $res['output_id'];
                $ok++;
                $log("  ✓ output: $tpl (#{$res['output_id']})");
            } catch (\Throwable $e) {
                $fail++;
                $autoErrors[] = "$tpl: " . $e->getMessage();
                $log("  ✗ output $tpl: " . $e->getMessage());
            }
        }

        // Optional: einen Output ins Wissen einspeisen
        $kTpl = (string)($job['auto_knowledge_template'] ?? '');
        if ($kTpl !== '' && isset($outputIdByType[$kTpl])) {
            try {
                $docId = $outSvc->sendToKnowledge($jobId, $outputIdByType[$kTpl], (int)($job['uploader_id'] ?? 0));
                $log("  ✓ wissen: doc #$docId aus $kTpl");
            } catch (\Throwable $e) {
                $autoErrors[] = "wissen($kTpl): " . $e->getMessage();
                $log("  ✗ wissen: " . $e->getMessage());
            }
        }

        $autoStatus = $fail === 0 ? 'done' : ($ok > 0 ? 'partial' : 'failed');
        $errMsg = $autoErrors ? substr(implode(' | ', $autoErrors), 0, 1000) : null;
        $db->execute(
            "UPDATE tr_jobs SET auto_status=?, error_message=COALESCE(error_message, ?) WHERE id=?",
            [$autoStatus, $errMsg, $jobId]
        );
    }

    // WAV-Derivat behalten — Editor laed evtl. Audio. Source-Datei (encrypted)
    // bleibt erstmal liegen, kann ueber „Job loeschen" weg.

    $log('done ✓');
    $cleanup();
    exit(0);

} catch (\Throwable $e) {
    $cleanup();
    failJob($db, $jobId, $e->getMessage());
    $log('FAILED: ' . $e->getMessage());
    exit(1);
}

function updateProgress(\Core\Database $db, int $jobId, int $pct): void
{
    $db->execute('UPDATE tr_jobs SET progress_pct=? WHERE id=?', [$pct, $jobId]);
}

/**
 * Transkribiert eine WAV-Datei ueber den OpenAI-kompatiblen Remote-Whisper (GPU).
 * Liefert dasselbe Format wie der lokale Runner, aber OHNE Sprecher-Trennung.
 */
function transcribeRemoteWhisper(string $wavPath, ?string $language, callable $log): array
{
    require_once __DIR__ . '/../core/Settings.php';
    $url   = (string)\Core\Settings::get('whisper_remote_url', 'https://ki.thoxan.com/whisper/v1/audio/transcriptions');
    $model = (string)\Core\Settings::get('whisper_remote_model', 'Systran/faster-whisper-large-v3');
    $key   = (string)\Core\Settings::get('local_api_key');
    if ($url === '') {
        throw new \RuntimeException('whisper_remote_url ist nicht konfiguriert');
    }

    $post = [
        'model'           => $model,
        'response_format' => 'verbose_json', // liefert Segmente mit Zeitstempeln
        'file'            => new \CURLFile($wavPath, 'audio/wav', basename($wavPath)),
    ];
    if ($language) $post['language'] = $language;

    $headers = ['Accept: application/json'];
    if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 900, // erster Call zieht Modell aus HuggingFace (2–5 Min)
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $err !== '') {
        throw new \RuntimeException('Remote-Whisper cURL-Fehler: ' . $err);
    }
    if ($code >= 400) {
        throw new \RuntimeException('Remote-Whisper HTTP ' . $code . ': ' . mb_substr((string)$body, 0, 300));
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data) || !isset($data['text'])) {
        throw new \RuntimeException('Remote-Whisper-Output unparsbar: ' . mb_substr((string)$body, 0, 300));
    }

    // Segmente auf {start,end,text} normalisieren (Downstream defaultet Sprecher zu "Sprecher")
    $segments = [];
    foreach (($data['segments'] ?? []) as $s) {
        $segments[] = [
            'start' => (float)($s['start'] ?? 0),
            'end'   => (float)($s['end'] ?? 0),
            'text'  => trim((string)($s['text'] ?? '')),
        ];
    }
    // Fallback: keine Segmente geliefert -> ein Segment mit dem Volltext
    if (!$segments && trim((string)$data['text']) !== '') {
        $segments[] = ['start' => 0.0, 'end' => (float)($data['duration'] ?? 0), 'text' => trim((string)$data['text'])];
    }

    $out = [
        'text'     => (string)$data['text'],
        'segments' => $segments,
        'speakers' => [], // Remote-Whisper macht keine Diarisierung
        'language' => $data['language'] ?? $language,
    ];
    if (isset($data['duration'])) $out['duration'] = (float)$data['duration'];
    return $out;
}

/** mm:ss oder h:mm:ss */
function fmtTime(float $sec): string
{
    if ($sec < 0) $sec = 0;
    $s = (int)round($sec);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $r = $s % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $r)
        : sprintf('%d:%02d', $m, $r);
}

function failJob(\Core\Database $db, int $jobId, string $msg): void
{
    $db->execute(
        "UPDATE tr_jobs SET status='failed', finished_at=NOW(), error_message=? WHERE id=?",
        [substr($msg, 0, 1000), $jobId]
    );
}

/**
 * Job zurueck in die Queue, mit exponentiellem Backoff.
 * Nach 6 erfolglosen Versuchen wird er final auf 'failed' gesetzt.
 */
function scheduleRetry(\Core\Database $db, int $jobId, string $reason): void
{
    $row = $db->queryOne('SELECT retry_count FROM tr_jobs WHERE id=?', [$jobId]);
    $count = (int)($row['retry_count'] ?? 0) + 1;
    $maxRetries = 6;
    // Backoff in Minuten: 2, 5, 15, 30, 60, 120
    $delays = [2, 5, 15, 30, 60, 120];

    if ($count > $maxRetries) {
        $db->execute(
            "UPDATE tr_jobs SET status='failed', finished_at=NOW(),
                                error_message=CONCAT('Auch nach ', ?, ' Versuchen nicht erfolgreich: ', ?),
                                retry_count=?, next_retry_at=NULL,
                                started_at=NULL, progress_pct=0, progress_note=NULL
             WHERE id=?",
            [$maxRetries, substr($reason, 0, 800), $count, $jobId]
        );
        return;
    }
    $minutes = $delays[$count - 1] ?? 120;
    $db->execute(
        "UPDATE tr_jobs
         SET status='queued',
             next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
             retry_count=?,
             started_at=NULL, finished_at=NULL, progress_pct=0,
             progress_note=?,
             error_message=NULL
         WHERE id=?",
        [
            $minutes,
            $count,
            // Nur den eigentlichen Grund — Wiederholungs-Prefix baut die UI.
            substr($reason, 0, 240),
            $jobId,
        ]
    );
}
