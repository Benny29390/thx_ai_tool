<?php
/**
 * Tallyr Site-Monitor Export — zur einmaligen Migration ins KI-Tool.
 *
 * ▸ DIESE DATEI INS LAUFENDE TALLYR-WORDPRESS-CHILDTHEME UNTER
 *   `kreation-tallyr/inc/export-monitor.php` LEGEN
 *   und in `functions.php` einbinden:
 *
 *     require_once get_stylesheet_directory() . '/inc/export-monitor.php';
 *
 * ▸ Aufruf im Browser:
 *     https://<dein-tallyr-host>/?tallyr_export=monitor&key=<SECRET>
 *
 * ▸ Secret-Key wird beim ersten Aufruf auto-generiert (siehe inc/monitor-cron.php
 *   `tallyr_handle_monitor_cron` — wir nutzen den gleichen Mechanismus).
 *
 * ▸ Die heruntergeladene JSON-Datei dann im KI-Tool unter
 *   `/admin/site-monitor` → Button „Tallyr-JSON" hochladen.
 */

if (!defined('ABSPATH')) exit;

add_action('template_redirect', 'tallyr_site_monitor_export_handler');

function tallyr_site_monitor_export_handler() {
    if (!isset($_GET['tallyr_export']) || $_GET['tallyr_export'] !== 'monitor') return;

    // Selber Secret-Key wie monitor-cron.php
    $stored = get_option('tallyr_monitor_cron_key');
    if (!$stored) {
        $stored = wp_generate_password(32, false);
        update_option('tallyr_monitor_cron_key', $stored);
    }
    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    if ($key !== $stored) {
        status_header(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    global $wpdb;
    $monitors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tallyr_monitors", ARRAY_A);
    $clients = $wpdb->get_results("SELECT id, title, shortdesc FROM {$wpdb->prefix}tallyr_clients WHERE state = 1", ARRAY_A);

    // sub_urls als String (JSON) lassen — Importer akzeptiert beides
    foreach ($monitors as &$m) {
        if (!empty($m['sub_urls'])) {
            $decoded = json_decode($m['sub_urls'], true);
            if (is_array($decoded)) $m['sub_urls'] = $decoded;
        }
    }

    $export = [
        'export_version' => '1.0',
        'exported_at' => date('c'),
        'source' => 'tallyr',
        'monitors' => $monitors,
        'clients' => $clients,
    ];

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tallyr-site-monitor-' . date('Y-m-d_His') . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
