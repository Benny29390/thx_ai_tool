<?php
/**
 * Routine: buendelt offene, noch nicht verarbeitete Feedbacks per KI zu
 * Maßnahmen-Vorschlaegen (feedback_measures, source='ki', status='offen') und
 * pingt die Admins per E-Mail an, wenn neue Vorschlaege entstanden sind.
 *
 * Aufruf (Cron, woechentlich):
 *   0 8 * * 1 root /usr/bin/php /var/www/scripts/feedback-analyze.php >> /var/log/ki-tool-feedback-analyze.log 2>&1
 *
 * Optionen:
 *   --dry-run   nur zeigen, wie viele offene Feedbacks anstehen (kein LLM, kein Schreiben)
 *   --quiet     keine E-Mail senden (nur Maßnahmen anlegen)
 */

require_once __DIR__ . '/../config/constants.php';
spl_autoload_register(function ($class) {
    $namespaces = ['Core\\' => 'core/', 'Models\\' => 'models/', 'Services\\' => 'services/'];
    foreach ($namespaces as $ns => $dir) {
        if (strpos($class, $ns) === 0) {
            $file = ROOT_PATH . '/' . $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$config = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($config['db']);
$db = \Core\Database::getInstance();

$args    = $argv ?? [];
$dryRun  = in_array('--dry-run', $args, true);
$quiet   = in_array('--quiet', $args, true);

$stamp = date('Y-m-d H:i:s');
echo "=== Feedback-Analyse-Routine ($stamp) ===\n";

$svc = new \Services\FeedbackMeasureService($db);

$pending = $svc->unprocessedFeedback();
echo "Offene, unverarbeitete Feedbacks: " . count($pending) . "\n";

if ($dryRun) {
    echo "[DRY-RUN] Keine Analyse, kein Schreiben.\n";
    exit(0);
}

if (empty($pending)) {
    echo "Nichts zu tun.\n";
    exit(0);
}

// Settings (API-Keys) laden + entschluesseln
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

try {
    $res = $svc->analyze($settings, null); // created_by = null (System)
} catch (\Throwable $e) {
    echo "FEHLER bei der Analyse: " . $e->getMessage() . "\n";
    exit(1);
}

echo sprintf("Erstellt: %d Maßnahme(n) aus %d Feedback(s) (%s)\n",
    $res['created'], $res['analyzed'], $res['model'] ?? '-');

foreach ($res['measures'] as $m) {
    echo sprintf("  - [%s/%s] %s\n", $m['priority'], $m['area'] ?? '-', $m['title']);
}

if ($res['created'] === 0) {
    echo "Keine neuen Maßnahmen — kein Ping.\n";
    exit(0);
}

// ---- E-Mail-Ping an die Admins ----
if ($quiet) {
    echo "[--quiet] Kein Ping.\n";
    exit(0);
}

try {
    $mailer = \Services\EmailService::fromSettings($db);
    if (!$mailer->isConfigured()) {
        echo "SMTP nicht konfiguriert — kein Ping (Maßnahmen sind trotzdem angelegt).\n";
        exit(0);
    }

    $appUrl = \Core\Brand::url();
    $rows = '';
    foreach ($res['measures'] as $m) {
        $rows .= '<tr>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;"><strong>' . htmlspecialchars($m['title']) . '</strong>'
            . (!empty($m['description']) ? '<br><span style="color:#555;font-size:13px;">' . htmlspecialchars(mb_substr($m['description'], 0, 200)) . '</span>' : '')
            . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;color:#666;">' . htmlspecialchars($m['area'] ?? '-') . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . htmlspecialchars(ucfirst($m['priority'])) . '</td>'
            . '</tr>';
    }

    $subject = sprintf('%d neue Maßnahmen-Vorschläge aus Feedback', $res['created']);
    $html = '<div style="font-family:Arial,sans-serif;color:#222;max-width:640px;">'
        . '<h2 style="color:#005da8;">Neue Maßnahmen aus dem Feedback</h2>'
        . '<p>Ich habe ' . $res['analyzed'] . ' offene Feedback(s) gesichtet und daraus '
        . '<strong>' . $res['created'] . ' Maßnahme(n)</strong> vorgeschlagen. Schau drüber und entscheide, '
        . 'was als Nächstes drankommt:</p>'
        . '<table style="border-collapse:collapse;width:100%;font-size:14px;margin:14px 0;">'
        . '<tr style="text-align:left;color:#888;font-size:12px;"><th style="padding:6px 10px;">Maßnahme</th><th style="padding:6px 10px;">Bereich</th><th style="padding:6px 10px;">Priorität</th></tr>'
        . $rows
        . '</table>'
        . '<p><a href="' . $appUrl . '/admin/feedback?ms=offen" '
        . 'style="display:inline-block;background:#005da8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">'
        . 'Maßnahmen öffnen</a></p>'
        . '<p style="color:#999;font-size:12px;">Automatische Routine · ' . $stamp . '</p>'
        . '</div>';

    $admins = $db->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 AND email <> ''");
    $sent = 0;
    foreach ($admins as $a) {
        if ($mailer->send($a['email'], $subject, $html)) {
            $sent++;
        }
    }
    echo "Ping gesendet an $sent Admin(s).\n";
} catch (\Throwable $e) {
    echo "Ping fehlgeschlagen: " . $e->getMessage() . " (Maßnahmen sind angelegt).\n";
}
