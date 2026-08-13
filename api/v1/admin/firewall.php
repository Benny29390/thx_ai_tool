<?php
/**
 * /api/v1/admin/firewall       (GET)  — Ueberblick aus dem Snapshot + eigene IP + Queue
 * /api/v1/admin/firewall/unban (POST) — Entsperr-Auftrag in die Warteschlange legen  Body: { ip }
 *
 * Cap-Schutz im Router (api/handler.php → CAP_FIREWALL).
 * Die Web-App fuehrt KEINE System-Befehle aus (disable_functions). Anzeige kommt
 * aus dem Snapshot, den der Cron-Worker schreibt; Entsperren laeuft ueber die
 * Warteschlange (firewall_unban_queue), die der Cron-Worker abarbeitet.
 * Geroutet mit $uri, $method, $input aus dem Handler-Scope.
 */

use Core\Auth;
use Core\Response;
use Services\FirewallService;

$fw = new FirewallService();

if ($uri === '/admin/firewall' && $method === 'GET') {
    $snap = $fw->readSnapshot();
    $myIp = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($snap === null) {
        // Noch kein Snapshot — der Cron-Worker hat vermutlich noch nicht gelaufen
        Response::success([
            'available'    => false,
            'snapshot_age' => null,
            'my_ip'        => $myIp,
            'jails'        => [],
            'banned'       => [],
            'ufw'          => [],
            'pending'      => [],
        ]);
        return;
    }

    $banned = $snap['banned'] ?? [];
    $myIpBanned = false;
    foreach ($banned as $b) {
        if (($b['ip'] ?? null) === $myIp) { $myIpBanned = true; break; }
    }

    Response::success([
        'available'    => (bool) ($snap['available'] ?? false),
        'snapshot_age' => isset($snap['generated_at']) ? max(0, time() - (int) $snap['generated_at']) : null,
        'my_ip'        => $myIp,
        'my_ip_banned' => $myIpBanned,
        'jails'        => $snap['jails'] ?? [],
        'banned'       => $banned,
        'ufw'          => $snap['ufw'] ?? [],
        'pending'      => $fw->pendingIps(),
    ]);
    return;
}

if ($uri === '/admin/firewall/unban' && $method === 'POST') {
    $ip = trim((string) ($input['ip'] ?? ''));
    if ($ip === '' || !$fw->isValidIp($ip)) {
        Response::error('Bitte eine gueltige IP-Adresse angeben.', 422);
        return;
    }

    $userId = Auth::user()['id'] ?? null;
    $result = $fw->enqueueUnban($ip, $userId ? (int) $userId : null);
    if (!$result['ok']) {
        Response::error($result['message'], 400);
        return;
    }

    Response::success(['ip' => $ip], $result['message']);
    return;
}

Response::error('Unbekannter Firewall-Endpunkt oder falsche Methode.', 404);
