<?php // === Site-Monitor-Export v2 für KI-Tool — in functions.php pasten ===
// Eindeutiger Endpoint-Pfad damit's nicht mit alten Versionen kollidiert:
//   https://tallyr.de/?tallyr_monitor_export=v2&key=<SECRET>            (nur Monitors)
//   https://tallyr.de/?tallyr_monitor_export=v2&key=<SECRET>&history=1&days=30
add_action('init', function () {
    if (($_GET['tallyr_monitor_export'] ?? '') !== 'v2') return;
    header('X-Tallyr-Export-Version: v2');
    if (($_GET['key'] ?? '') !== get_option('tallyr_monitor_cron_key')) {
        status_header(403); echo 'Forbidden — Snippet v2 aktiv, aber Key falsch.'; exit;
    }
    @ini_set('memory_limit', '1024M'); @set_time_limit(180);
    global $wpdb; $p = $wpdb->prefix; $name = 'tallyr-monitors-' . date('Y-m-d');
    $data = [
        'export_version' => '2.0',
        'snippet_version' => 'v2',
        'exported_at'    => date('c'),
        'monitors'       => $wpdb->get_results("SELECT * FROM {$p}tallyr_monitors", ARRAY_A),
        'clients'        => $wpdb->get_results("SELECT id, title, shortdesc FROM {$p}tallyr_clients WHERE state = 1", ARRAY_A),
    ];
    if (!empty($_GET['history'])) {
        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
        $name .= "-history-{$days}d";
        $data['incidents'] = $wpdb->get_results("SELECT i.*, m.url AS monitor_url FROM {$p}tallyr_monitor_incidents i JOIN {$p}tallyr_monitors m ON m.id = i.monitor_id", ARRAY_A);
        $data['logs']      = $wpdb->get_results($wpdb->prepare("SELECT l.monitor_id, l.checked_url, l.status_code, l.response_time_ms, l.is_up, l.checked_at, m.url AS monitor_url FROM {$p}tallyr_monitor_log l JOIN {$p}tallyr_monitors m ON m.id = l.monitor_id WHERE l.checked_at >= DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY l.checked_at ASC", $days), ARRAY_A);
        $data['stats']     = ['log_count' => count($data['logs']), 'incident_count' => count($data['incidents'])];
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit;
}, 1); // Priority 1 = läuft VOR allen anderen init-Hooks
