<?php
namespace Services;

use Core\Database;

/**
 * TranskriptionEditorService — Volltext-Editor, Sprecher-Benennung,
 * Korrektur-Dictionary.
 */
class TranskriptionEditorService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ====================================================================
       RESULT laden / speichern
       ==================================================================== */

    public function loadResult(int $jobId): ?array
    {
        $result = $this->db->queryOne(
            'SELECT id, job_id, transcript_text, segments_json, speaker_count,
                    language_detected, word_count, duration_sec, created_at
             FROM tr_results WHERE job_id=? LIMIT 1',
            [$jobId]
        );
        if (!$result) return null;

        $segments = json_decode((string)$result['segments_json'], true) ?: [];
        unset($result['segments_json']);

        $speakers = $this->db->query(
            'SELECT id, label_internal, name_custom FROM tr_speakers WHERE result_id=? ORDER BY label_internal',
            [(int)$result['id']]
        );

        return [
            'result'   => $result,
            'segments' => $segments,
            'speakers' => $speakers,
        ];
    }

    /**
     * Speichert manuelle Korrekturen am Transkript.
     * Erlaubt: transcript_text (Volltext), segments (Array mit text-Replacements).
     */
    public function updateResult(int $jobId, array $payload, int $userId): void
    {
        $result = $this->db->queryOne('SELECT id FROM tr_results WHERE job_id=? LIMIT 1', [$jobId]);
        if (!$result) throw new \RuntimeException('Kein Result fuer Job');
        $resultId = (int)$result['id'];

        $update = [];
        if (isset($payload['transcript_text'])) {
            $text = (string)$payload['transcript_text'];
            $update['transcript_text'] = $text;
            $update['word_count'] = str_word_count(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '');
        }
        if (isset($payload['segments']) && is_array($payload['segments'])) {
            // Bestehende Segmente laden und nur text-Felder updaten
            $current = $this->db->queryOne('SELECT segments_json FROM tr_results WHERE id=?', [$resultId]);
            $segments = json_decode((string)$current['segments_json'], true) ?: [];
            foreach ($payload['segments'] as $i => $patch) {
                $i = (int)$i;
                if (!isset($segments[$i])) continue;
                if (isset($patch['text']))    $segments[$i]['text'] = (string)$patch['text'];
                if (isset($patch['speaker'])) $segments[$i]['speaker'] = (string)$patch['speaker'];
            }
            $update['segments_json'] = json_encode($segments, JSON_UNESCAPED_UNICODE);
        }
        if (!$update) return;

        $sets = [];
        $params = [];
        foreach ($update as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $resultId;
        $this->db->execute("UPDATE tr_results SET " . implode(',', $sets) . " WHERE id=?", $params);
    }

    /**
     * Sprecher umbenennen.
     * @param array $speakers Liste von { id, name_custom }
     */
    public function renameSpeakers(int $jobId, array $speakers): void
    {
        if (!$speakers) return;
        $result = $this->db->queryOne('SELECT id FROM tr_results WHERE job_id=? LIMIT 1', [$jobId]);
        if (!$result) throw new \RuntimeException('Kein Result fuer Job');
        $resultId = (int)$result['id'];

        foreach ($speakers as $sp) {
            $id = (int)($sp['id'] ?? 0);
            if (!$id) continue;
            $this->db->execute(
                'UPDATE tr_speakers SET name_custom=? WHERE id=? AND result_id=?',
                [($sp['name_custom'] ?? null) ?: null, $id, $resultId]
            );
        }
    }

    /* ====================================================================
       Korrektur-Dictionary
       ==================================================================== */

    public function listCorrections(int $userId): array
    {
        return $this->db->query(
            "SELECT id, user_id, original, correction, scope, created_at
             FROM tr_corrections
             WHERE scope='global' OR user_id=?
             ORDER BY scope ASC, original ASC",
            [$userId]
        );
    }

    public function createCorrection(string $original, string $correction, string $scope, int $userId): int
    {
        return (int)$this->db->insert('tr_corrections', [
            'user_id'    => $scope === 'global' ? null : $userId,
            'original'   => $original,
            'correction' => $correction,
            'scope'      => $scope,
        ]);
    }

    public function deleteCorrection(int $id, int $userId, bool $isAdmin): bool
    {
        $row = $this->db->queryOne('SELECT user_id, scope FROM tr_corrections WHERE id=?', [$id]);
        if (!$row) return false;
        if ($row['scope'] === 'global' && !$isAdmin) return false;
        if ($row['scope'] === 'user' && (int)$row['user_id'] !== $userId && !$isAdmin) return false;
        return $this->db->execute('DELETE FROM tr_corrections WHERE id=?', [$id]) > 0;
    }

    /**
     * Wendet alle relevanten Korrekturen (global + user-eigene) auf den Volltext
     * und Segment-Texte an. Case-sensitiv, ganze Woerter (Wortgrenze).
     *
     * @return array { changes: int, transcript: string }
     */
    public function applyCorrections(int $jobId, int $userId): array
    {
        $dict = $this->listCorrections($userId);
        if (!$dict) return ['changes' => 0, 'transcript' => ''];

        $result = $this->db->queryOne(
            'SELECT id, transcript_text, segments_json FROM tr_results WHERE job_id=? LIMIT 1',
            [$jobId]
        );
        if (!$result) throw new \RuntimeException('Kein Result fuer Job');

        $apply = function (string $text) use ($dict, &$changes) {
            foreach ($dict as $row) {
                $orig = preg_quote((string)$row['original'], '/');
                $count = 0;
                $text = preg_replace(
                    '/\b' . $orig . '\b/u',
                    str_replace('$', '\\$', (string)$row['correction']),
                    $text,
                    -1,
                    $count
                );
                $changes += $count;
            }
            return $text;
        };

        $changes = 0;
        $newText = $apply((string)$result['transcript_text']);

        $segments = json_decode((string)$result['segments_json'], true) ?: [];
        foreach ($segments as &$seg) {
            $seg['text'] = $apply((string)($seg['text'] ?? ''));
        }
        unset($seg);

        $this->db->execute(
            'UPDATE tr_results SET transcript_text=?, segments_json=?,
                                  word_count=?
             WHERE id=?',
            [
                $newText,
                json_encode($segments, JSON_UNESCAPED_UNICODE),
                str_word_count(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $newText) ?? ''),
                (int)$result['id'],
            ]
        );

        return ['changes' => $changes, 'transcript' => $newText];
    }
}
