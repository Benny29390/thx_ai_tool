<?php
/**
 * Projektplanner — Tägliche Mail-Notifications
 *
 *  1. Deadline-Reminder: Zeilen mit deadline in den nächsten 24/48h, nicht erledigt
 *     → Mail an Lead (oder ersten Responsible) mit Link zum Plan
 *  2. Feedback-Notification: ungelesenes Feedback auf Plänen
 *     → Mail an Plan-Owner (created_by)
 *
 *  Cron: 1× pro Tag morgens 08:00 (siehe /etc/cron.d/ki-tool-pp-notifications)
 *  Idempotenz: pro Zeile/Feedback wird nur EINMAL pro Tag gemailt (Tabelle pp_notification_log).
 *
 *  Usage: php /var/www/scripts/pp-notifications.php [--dry-run] [--verbose]
 */

define('BASE_PATH', '/var/www');
define('CONFIG_PATH', BASE_PATH . '/config');
define('SERVICES_PATH', BASE_PATH . '/services');
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Crypto.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/services/EmailService.php';

$dryRun  = in_array('--dry-run', $argv ?? [], true);
$verbose = in_array('--verbose', $argv ?? [], true) || in_array('-v', $argv ?? [], true);
$log = fn($m) => $verbose ? print("[" . date('H:i:s') . "] $m\n") : null;

$cfg = require CONFIG_PATH . '/config.php';
\Core\Database::getInstance($cfg['db']);
$db = \Core\Database::getInstance();

// Log-Tabelle anlegen (idempotent)
$db->execute("CREATE TABLE IF NOT EXISTS pp_notification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kind VARCHAR(40) NOT NULL,        -- 'deadline' | 'feedback'
    ref_key VARCHAR(120) NOT NULL,    -- z.B. 'row#123' oder 'fb#45'
    sent_to VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kind_ref_day (kind, ref_key, sent_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mailer = \Services\EmailService::fromSettings($db);
if (!$mailer->isConfigured() && !$dryRun) {
    echo "EmailService nicht konfiguriert — Abbruch.\n";
    exit(1);
}
$appUrl = \Core\Brand::url();

// Hilfsfunktion: User-Mail für eine Person finden (nach Name)
$mailFor = function (string $name) use ($db): ?array {
    if ($name === '') return null;
    $u = $db->queryOne(
        "SELECT email, name FROM users
         WHERE is_active = 1 AND email NOT LIKE '%@projektplanner.local'
           AND (LOWER(name) = LOWER(?) OR LOWER(nickname) = LOWER(?) OR LOWER(abbreviation) = LOWER(?))
         LIMIT 1",
        [$name, $name, $name]
    );
    return $u && filter_var($u['email'], FILTER_VALIDATE_EMAIL) ? $u : null;
};

$alreadySent = function (string $kind, string $ref, string $to) use ($db): bool {
    return (bool)$db->queryValue(
        "SELECT 1 FROM pp_notification_log WHERE kind = ? AND ref_key = ? AND sent_to = ? AND DATE(sent_at) = CURDATE()",
        [$kind, $ref, $to]
    );
};
$logSent = function (string $kind, string $ref, string $to) use ($db, $dryRun) {
    if ($dryRun) return;
    try { $db->insert('pp_notification_log', ['kind' => $kind, 'ref_key' => $ref, 'sent_to' => $to]); }
    catch (\Throwable $e) { /* dup ok */ }
};

// === 1. Deadline-Reminder (Deadline in 0/1/2 Tagen, nicht erledigt) ===
$sentDeadline = 0; $skippedDeadline = 0;
$deadlineRows = $db->query(
    "SELECT r.id, r.description, r.deadline, r.lead_responsible, r.responsible,
            p.id AS plan_id, p.title AS plan_title,
            c.name AS customer_name
     FROM pp_plan_rows r
     JOIN pp_plans p ON p.id = r.plan_id AND p.state = 1 AND p.plan_status IN ('aktiv','einzelprojekt','reporting')
     LEFT JOIN customers c ON c.id = p.customer_id
     WHERE r.row_type = 'item' AND r.is_done = 0 AND r.no_ticket = 0
       AND r.deadline IS NOT NULL AND r.deadline <> ''
       AND r.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)"
) ?: [];
$log("Deadline-Kandidaten: " . count($deadlineRows));
foreach ($deadlineRows as $r) {
    // Lead bevorzugt, sonst erster Responsible
    $person = trim((string)($r['lead_responsible'] ?? ''));
    if ($person === '') {
        $resp = array_map('trim', explode(',', (string)($r['responsible'] ?? '')));
        $person = $resp[0] ?? '';
    }
    if ($person === '') { $skippedDeadline++; continue; }
    $u = $mailFor($person);
    if (!$u) { $skippedDeadline++; $log("  Kein User für '$person' (Zeile #{$r['id']})"); continue; }
    $ref = 'row#' . $r['id'];
    if ($alreadySent('deadline', $ref, $u['email'])) { $skippedDeadline++; continue; }
    $daysLeft = (int)((strtotime($r['deadline']) - strtotime(date('Y-m-d'))) / 86400);
    $dayLabel = $daysLeft <= 0 ? 'HEUTE' : ($daysLeft === 1 ? 'morgen' : 'in ' . $daysLeft . ' Tagen');
    $link = $appUrl . '/admin/projektplanner';
    $html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.5;color:#1e293b;max-width:560px;">'
          . '<p>Hallo ' . htmlspecialchars($u['name']) . ',</p>'
          . '<p>Erinnerung: eine Aufgabe in Deinem Projektplaner ist <strong>' . htmlspecialchars($dayLabel) . '</strong> fällig.</p>'
          . '<div style="background:#f1f5f9;border-left:4px solid #2563eb;padding:12px 16px;margin:16px 0;border-radius:4px;">'
          . '<div style="font-weight:600;color:#0f172a;">' . htmlspecialchars($r['description']) . '</div>'
          . '<div style="color:#64748b;font-size:12px;margin-top:4px;">'
          .   'Plan: ' . htmlspecialchars($r['plan_title']) . ' · Kunde: ' . htmlspecialchars($r['customer_name'] ?? '—')
          .   ' · Deadline: <strong>' . htmlspecialchars($r['deadline']) . '</strong>'
          . '</div></div>'
          . '<p><a href="' . $link . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;display:inline-block;">Zum Projektplaner →</a></p>'
          . '<p style="color:#94a3b8;font-size:11px;margin-top:30px;">Diese Mail wird einmal pro Aufgabe und Tag versendet. Aufgabe als erledigt markieren, dann kommt sie nicht mehr.</p>'
          . '</div>';
    if ($dryRun) { echo "[DRY] → {$u['email']} · Deadline {$r['deadline']} · {$r['description']}\n"; }
    else if ($mailer->send($u['email'], "⏰ Deadline $dayLabel: " . mb_substr($r['description'], 0, 60), $html)) {
        $logSent('deadline', $ref, $u['email']);
        $sentDeadline++;
        $log("  ✓ Deadline → {$u['email']} · Zeile #{$r['id']}");
    }
}

// === 2. Feedback-Notification (ungelesenes Feedback auf einem Plan) ===
$sentFeedback = 0; $skippedFeedback = 0;
$feedbackRows = $db->query(
    "SELECT f.id, f.plan_id, f.row_id, f.message, f.author_name, f.created_at,
            p.title AS plan_title, p.created_by,
            u.email AS owner_email, u.name AS owner_name
     FROM pp_plan_feedback f
     JOIN pp_plans p ON p.id = f.plan_id AND p.state = 1
     JOIN users u ON u.id = p.created_by AND u.is_active = 1
     WHERE f.read_at IS NULL
       AND u.email NOT LIKE '%@projektplanner.local'"
) ?: [];
$log("Feedback-Kandidaten: " . count($feedbackRows));
foreach ($feedbackRows as $f) {
    $ref = 'fb#' . $f['id'];
    if ($alreadySent('feedback', $ref, $f['owner_email'])) { $skippedFeedback++; continue; }
    $link = $appUrl . '/admin/projektplanner';
    $html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.5;color:#1e293b;max-width:560px;">'
          . '<p>Hallo ' . htmlspecialchars($f['owner_name']) . ',</p>'
          . '<p>Du hast neues Feedback auf einem Deiner Projektpläne erhalten.</p>'
          . '<div style="background:#f1f5f9;border-left:4px solid #10b981;padding:12px 16px;margin:16px 0;border-radius:4px;">'
          . '<div style="font-weight:600;color:#0f172a;">' . htmlspecialchars($f['plan_title']) . '</div>'
          . '<div style="font-style:italic;color:#475569;margin-top:8px;">„' . nl2br(htmlspecialchars(mb_substr($f['message'], 0, 500))) . '"</div>'
          . '<div style="color:#64748b;font-size:11px;margin-top:8px;">von ' . htmlspecialchars($f['author_name'] ?? 'Anonym')
          .   ' · ' . htmlspecialchars($f['created_at']) . '</div>'
          . '</div>'
          . '<p><a href="' . $link . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;display:inline-block;">Im Projektplaner ansehen →</a></p>'
          . '</div>';
    if ($dryRun) { echo "[DRY] → {$f['owner_email']} · Feedback auf {$f['plan_title']}\n"; }
    else if ($mailer->send($f['owner_email'], '💬 Neues Feedback: ' . mb_substr($f['plan_title'], 0, 60), $html)) {
        $logSent('feedback', $ref, $f['owner_email']);
        $sentFeedback++;
        $log("  ✓ Feedback → {$f['owner_email']} · FB #{$f['id']}");
    }
}

echo "\nFertig: Deadlines $sentDeadline gesendet ($skippedDeadline übersprungen) · Feedback $sentFeedback gesendet ($skippedFeedback übersprungen)\n";
