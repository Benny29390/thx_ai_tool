<?php
namespace Services;

use Core\Database;
use Core\Settings;

/**
 * LoomPullService — pollt die Loom-Workspace-API auf neue Aufnahmen und
 * reiht jede unbekannte URL als Transkriptions-Job ein.
 *
 * Loom dokumentiert keinen festen „list videos"-Endpoint fuer alle Tarife.
 * Daher ist der Aufruf konfigurierbar (Endpoint, Token, JSON-Pfad zur Video-
 * Liste, Feldname mit der Share-URL). Defaults zielen auf api.loom.com/v1/videos.
 *
 * Konfiguration in Settings:
 *   loom_pull_enabled        '1'/'0'
 *   loom_pull_api_token      <token>   (Authorization: Bearer)
 *   loom_pull_endpoint       URL       (GET)
 *   loom_pull_videos_path    JSON-Pfad zur Videos-Liste, z.B. „videos" oder „data.items"
 *   loom_pull_url_field      Feldname mit der Share-URL pro Item (Default: share_url)
 *   loom_pull_assign_user_id User-ID, dem die Jobs zugeordnet werden (Default: erster Admin)
 */
class LoomPullService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Einmal pullen. Returnt Reportierungsdaten.
     */
    public function pullOnce(): array
    {
        $cfg = $this->loadConfig();
        if ($cfg['enabled'] !== '1') {
            return ['ok' => true, 'skipped' => 'disabled'];
        }
        if ($cfg['token'] === '') {
            throw new \RuntimeException('loom_pull_api_token fehlt');
        }
        if ($cfg['endpoint'] === '') {
            throw new \RuntimeException('loom_pull_endpoint fehlt');
        }

        $json = $this->httpGet($cfg['endpoint'], $cfg['token']);
        $videos = $this->extractByPath($json, $cfg['videos_path']);
        if (!is_array($videos)) {
            throw new \RuntimeException('Pfad „' . $cfg['videos_path'] . '" lieferte keine Liste');
        }

        $userId = (int)$cfg['assign_user_id'];
        if ($userId <= 0) {
            $userId = (int)$this->db->queryValue("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1");
        }
        if ($userId <= 0) {
            throw new \RuntimeException('Kein Ziel-User (loom_pull_assign_user_id leer und kein Admin gefunden)');
        }

        // Auth-Context fuer Service setzen (Caps/Defaults)
        \Core\Auth::initFromUserId($userId);

        require_once SERVICES_PATH . '/TranskriptionService.php';
        $tr = new TranskriptionService($this->db);

        $created = 0;
        $skipped = 0;
        $errors  = [];
        foreach ($videos as $v) {
            $url = (string)($v[$cfg['url_field']] ?? '');
            if ($url === '' || !preg_match('#^https?://(www\.)?loom\.com/(share|embed)/[a-z0-9]+#i', $url)) {
                continue;
            }
            // Duplikat-Check ueber source_url
            $exists = (int)$this->db->queryValue(
                'SELECT COUNT(*) FROM tr_uploads WHERE source=\'loom\' AND source_url=?',
                [$url]
            );
            if ($exists > 0) { $skipped++; continue; }

            try {
                $tr->ingestLoomUrl($url, $userId, []);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = $url . ': ' . $e->getMessage();
            }
        }

        $report = [
            'ok'         => true,
            'fetched'    => count($videos),
            'created'    => $created,
            'skipped'    => $skipped,
            'errors'     => $errors,
            'pulled_at'  => date('c'),
        ];

        Settings::set('loom_pull_last_run', json_encode($report));
        Settings::set('loom_pull_last_error', $errors ? implode(' | ', array_slice($errors, 0, 3)) : '');
        return $report;
    }

    /* ============ intern ============ */

    private function loadConfig(): array
    {
        return [
            'enabled'         => (string)Settings::get('loom_pull_enabled'),
            'token'           => (string)Settings::get('loom_pull_api_token'),
            'endpoint'        => (string)Settings::get('loom_pull_endpoint'),
            'videos_path'     => (string)Settings::get('loom_pull_videos_path'),
            'url_field'       => (string)Settings::get('loom_pull_url_field') ?: 'share_url',
            'assign_user_id'  => (string)Settings::get('loom_pull_assign_user_id'),
        ];
    }

    private function httpGet(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException('curl: ' . $err);
        }
        if ($http >= 400) {
            throw new \RuntimeException('HTTP ' . $http . ' von Loom: ' . substr((string)$body, 0, 300));
        }
        $j = json_decode((string)$body, true);
        if (!is_array($j)) {
            throw new \RuntimeException('Loom-Response nicht JSON: ' . substr((string)$body, 0, 200));
        }
        return $j;
    }

    /**
     * Holt eine Sub-Struktur aus einem geschachtelten Array via Dot-Notation.
     * Beispiel: $path = 'data.items' → $arr['data']['items']
     * Leerer Pfad gibt $arr selbst zurueck.
     */
    private function extractByPath(array $arr, string $path)
    {
        $path = trim($path);
        if ($path === '') return $arr;
        $cur = $arr;
        foreach (explode('.', $path) as $key) {
            if (!is_array($cur) || !array_key_exists($key, $cur)) return null;
            $cur = $cur[$key];
        }
        return $cur;
    }
}
