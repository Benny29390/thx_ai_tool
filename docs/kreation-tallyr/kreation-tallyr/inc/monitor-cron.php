<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ========================================
// DATABASE TABLES
// ========================================

function createtable_monitors() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		userid bigint(20) NOT NULL,
		client_id bigint(20) DEFAULT 0,
		url varchar(500) NOT NULL,
		label varchar(255) NOT NULL,
		check_interval int NOT NULL DEFAULT 2,
		alert_email varchar(255) DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'up',
		last_check datetime DEFAULT NULL,
		last_status_code int DEFAULT 0,
		last_response_time int DEFAULT 0,
		sub_urls text DEFAULT NULL,
		category varchar(100) DEFAULT '',
		report_schedule varchar(20) NOT NULL DEFAULT 'both',
		created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
createtable_monitors();

// Ensure new columns exist (dbDelta doesn't add columns to existing tables reliably)
function tallyr_ensure_monitors_columns() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitors';
	$row = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'sub_urls'");
	if (!$row) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN sub_urls text DEFAULT NULL AFTER last_response_time");
	}
	$row2 = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'category'");
	if (!$row2) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN category varchar(100) DEFAULT '' AFTER sub_urls");
	}
}
tallyr_ensure_monitors_columns();

function createtable_monitor_log() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitor_log';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		monitor_id bigint(20) NOT NULL,
		checked_url varchar(500) DEFAULT NULL,
		status_code int NOT NULL DEFAULT 0,
		response_time_ms int NOT NULL DEFAULT 0,
		is_up tinyint(1) NOT NULL DEFAULT 1,
		checked_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY (id),
		KEY monitor_id (monitor_id),
		KEY checked_at (checked_at),
		KEY idx_monitor_time (monitor_id, checked_at, is_up)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	// Add composite index if not exists
	$idx = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'idx_monitor_time'");
	if (empty($idx)) {
		$wpdb->query("ALTER TABLE $table_name ADD INDEX idx_monitor_time (monitor_id, checked_at, is_up)");
	}
}
createtable_monitor_log();

// Ensure checked_url column exists
function tallyr_ensure_log_columns() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitor_log';
	$row = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'checked_url'");
	if (!$row) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN checked_url varchar(500) DEFAULT NULL AFTER monitor_id");
	}
}
tallyr_ensure_log_columns();

function createtable_monitor_incidents() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'tallyr_monitor_incidents';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		monitor_id bigint(20) NOT NULL,
		started_at datetime NOT NULL,
		ended_at datetime DEFAULT NULL,
		duration_minutes int DEFAULT 0,
		notified tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY monitor_id (monitor_id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
createtable_monitor_incidents();

// ========================================
// CRON CHECK LOGIC
// ========================================

function tallyr_run_monitor_checks() {
	// Prevent double execution (cron called twice in quick succession)
	$lock = get_transient('tallyr_monitor_cron_lock');
	if ($lock) return;
	set_transient('tallyr_monitor_cron_lock', 1, 90); // 90s lock

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$monitors = $wpdb->get_results("SELECT * FROM $monitors_table WHERE status != 'paused'");

	if (!$monitors) {
		delete_transient('tallyr_monitor_cron_lock');
		return;
	}

	foreach ($monitors as $monitor) {
		// Build list of all URLs to check (main + sub-URLs)
		$urls_to_check = array($monitor->url);
		if (!empty($monitor->sub_urls)) {
			$sub = json_decode($monitor->sub_urls, true);
			if (is_array($sub)) {
				$urls_to_check = array_merge($urls_to_check, $sub);
			}
		}

		$all_up = true;
		$main_status_code = 0;
		$main_response_time = 0;
		$now = current_time('mysql', 1);

		foreach ($urls_to_check as $i => $check_url) {
			$check_url = trim($check_url);
			if (empty($check_url)) continue;

			$start = microtime(true);

			$response = wp_remote_get($check_url, array(
				'timeout' => 15,
				'redirection' => 5,
				'sslverify' => false,
				'user-agent' => 'TallyrMonitor/1.0',
			));

			$response_time_ms = round((microtime(true) - $start) * 1000);

			if (is_wp_error($response)) {
				$status_code = 0;
				$is_up = 0;
			} else {
				$status_code = wp_remote_retrieve_response_code($response);
				$is_up = ($status_code >= 200 && $status_code < 400) ? 1 : 0;

				// WordPress-spezifisch: Body auf kritische Fehler prüfen (Status 200 aber Seite kaputt)
				if ($is_up) {
					$body = wp_remote_retrieve_body($response);
					$wp_errors = array(
						'Error establishing a database connection',
						'Fehler beim Aufbau einer Datenbankverbindung',
						'Briefly unavailable for scheduled maintenance',
						'Wegen geplanter Wartungsarbeiten kurzzeitig nicht verfügbar',
					);
					foreach ($wp_errors as $err) {
						if (stripos($body, $err) !== false) {
							$is_up = 0;
							$status_code = 503; // Als 503 loggen damit es im Log erkennbar ist
							break;
						}
					}
				}
			}

			// Log every URL check
			$wpdb->insert($log_table, array(
				'monitor_id' => $monitor->id,
				'checked_url' => $check_url,
				'status_code' => $status_code,
				'response_time_ms' => $response_time_ms,
				'is_up' => $is_up,
				'checked_at' => $now,
			));

			if (!$is_up) $all_up = false;

			// Store main URL results for monitor update
			if ($i === 0) {
				$main_status_code = $status_code;
				$main_response_time = $response_time_ms;
			}
		}

		$new_status = $all_up ? 'up' : 'down';
		$old_status = $monitor->status;

		// Update monitor with main URL stats
		$wpdb->update($monitors_table, array(
			'status' => $new_status,
			'last_check' => $now,
			'last_status_code' => $main_status_code,
			'last_response_time' => $main_response_time,
		), array('id' => $monitor->id));

		// Count consecutive failures (main URL only)
		$consecutive_fails = 0;
		if (!$all_up) {
			$recent_checks = $wpdb->get_col($wpdb->prepare(
				"SELECT is_up FROM $log_table WHERE monitor_id = %d AND checked_url = %s ORDER BY checked_at DESC LIMIT 10",
				$monitor->id, $monitor->url
			));
			foreach ($recent_checks as $check) {
				if ($check == 0) {
					$consecutive_fails++;
				} else {
					break;
				}
			}
		}

		// First failure: create incident, no mail yet
		if ($old_status !== 'down' && $new_status === 'down') {
			$wpdb->insert($incidents_table, array(
				'monitor_id' => $monitor->id,
				'started_at' => current_time('mysql', 1),
				'notified' => 0,
			));
		}

		// 2nd consecutive failure: send alert (only once, no reminders)
		if ($new_status === 'down' && $consecutive_fails == 2) {
			$open_incident = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $incidents_table WHERE monitor_id = %d AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1",
				$monitor->id
			));
			if ($open_incident && !$open_incident->notified) {
				$wpdb->update($incidents_table, array('notified' => 1), array('id' => $open_incident->id));
			}
			tallyr_send_monitor_alert($monitor, 'down');
		}

		// Recovery: down -> up
		if ($old_status === 'down' && $new_status === 'up') {
			$open_incident = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $incidents_table WHERE monitor_id = %d AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1",
				$monitor->id
			));
			$was_notified = false;
			if ($open_incident) {
				$was_notified = ($open_incident->notified == 1);
				$now = current_time('mysql', 1);
				$started = strtotime($open_incident->started_at);
				$ended = strtotime($now);
				$duration = round(($ended - $started) / 60);
				$wpdb->update($incidents_table, array(
					'ended_at' => $now,
					'duration_minutes' => $duration,
				), array('id' => $open_incident->id));
			}
			// Only send recovery mail if a down alert was actually sent
			if ($was_notified) {
				tallyr_send_monitor_alert($monitor, 'recovery');
			}
		}
	}

	// Log cron execution
	$cron_log = get_option('tallyr_monitor_cron_log', array());
	$cron_log[] = array(
		'time' => current_time('mysql', 1),
		'count' => count($monitors),
	);
	// Keep last 100 entries
	if (count($cron_log) > 100) {
		$cron_log = array_slice($cron_log, -100);
	}
	update_option('tallyr_monitor_cron_log', $cron_log, false);

	// Check if reports are due
	tallyr_send_monitor_reports();

	// Cleanup old log entries (older than 90 days)
	$wpdb->query("DELETE FROM $log_table WHERE checked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");

	delete_transient('tallyr_monitor_cron_lock');
}

// ========================================
// ALERT E-MAILS
// ========================================

function tallyr_send_monitor_alert($monitor, $type) {
	$user = get_user_by('id', $monitor->userid);
	$to = !empty($monitor->alert_email) ? $monitor->alert_email : $user->user_email;

	if ($type === 'down') {
		$subject = 'DOWN: ' . $monitor->label . ' (' . $monitor->url . ')';
		$body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
		$body .= '<div style="background-color:#d32f2f;padding:20px;border-radius:8px 8px 0 0;">';
		$body .= '<h2 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">Website ist nicht erreichbar</h2></div>';
		$body .= '<div style="padding:20px;background-color:#ffffff;border:1px solid #eeeeee;border-top:none;border-radius:0 0 8px 8px;">';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>Website:</strong> ' . esc_html($monitor->label) . '</p>';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>URL:</strong> ' . esc_html($monitor->url) . '</p>';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>Status-Code:</strong> ' . intval($monitor->last_status_code) . '</p>';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>Zeitpunkt:</strong> ' . current_time('d.m.Y H:i') . ' Uhr</p>';
		$body .= '<p style="margin:15px 0 0;color:#d32f2f;font-weight:bold;font-size:14px;">2 Checks fehlgeschlagen. Du bekommst eine Nachricht, sobald die Website wieder erreichbar ist.</p>';
		$body .= '</div></div>';
	} else {
		$subject = 'RECOVERY: ' . $monitor->label . ' ist wieder online';
		$body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
		$body .= '<div style="background-color:#388e3c;padding:20px;border-radius:8px 8px 0 0;">';
		$body .= '<h2 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">Website ist wieder erreichbar</h2></div>';
		$body .= '<div style="padding:20px;background-color:#ffffff;border:1px solid #eeeeee;border-top:none;border-radius:0 0 8px 8px;">';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>Website:</strong> ' . esc_html($monitor->label) . '</p>';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>URL:</strong> ' . esc_html($monitor->url) . '</p>';
		$body .= '<p style="margin:0 0 10px;color:#333333;font-size:15px;"><strong>Zeitpunkt:</strong> ' . current_time('d.m.Y H:i') . ' Uhr</p>';
		$body .= '<p style="margin:15px 0 0;color:#388e3c;font-weight:bold;font-size:14px;">Alles wieder normal.</p>';
		$body .= '</div></div>';
	}

	$headers = array('Content-Type: text/html; charset=UTF-8');
	$sent = wp_mail($to, $subject, $body, $headers);

	// Log the email
	tallyr_log_email($type, $to, $subject, $monitor->label, $sent);
}

function tallyr_log_email($type, $to, $subject, $label, $sent) {
	$log = get_option('tallyr_monitor_email_log', array());
	$log[] = array(
		'time'    => current_time('mysql', 1),
		'type'    => $type,
		'to'      => $to,
		'subject' => $subject,
		'label'   => $label,
		'sent'    => $sent ? 1 : 0,
	);
	if (count($log) > 200) {
		$log = array_slice($log, -200);
	}
	update_option('tallyr_monitor_email_log', $log, false);
}

// ========================================
// UPTIME REPORTS
// ========================================

function tallyr_send_monitor_reports() {
	$day_of_week = date('N'); // 1=Monday
	$day_of_month = date('j');

	$send_weekly = ($day_of_week == 1);
	$send_monthly = ($day_of_month == 1);

	if (!$send_weekly && !$send_monthly) return;

	// Prevent sending multiple times per day
	$today = date('Y-m-d');
	$last_sent = get_option('tallyr_monitor_report_last_sent', '');
	if ($last_sent === $today) return;
	update_option('tallyr_monitor_report_last_sent', $today, false);

	global $wpdb;
	$monitors_table = $wpdb->prefix . 'tallyr_monitors';
	$log_table = $wpdb->prefix . 'tallyr_monitor_log';
	$incidents_table = $wpdb->prefix . 'tallyr_monitor_incidents';

	$users = $wpdb->get_col("SELECT DISTINCT userid FROM $monitors_table WHERE report_schedule != 'none'");

	// German month names
	$months_de = array(1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember');

	foreach ($users as $userid) {
		$user = get_user_by('id', $userid);
		if (!$user) continue;

		$where_schedule = array();
		if ($send_weekly) $where_schedule[] = "report_schedule IN ('weekly','both')";
		if ($send_monthly) $where_schedule[] = "report_schedule IN ('monthly','both')";

		$monitors = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $monitors_table WHERE userid = %d AND (" . implode(' OR ', $where_schedule) . ") ORDER BY label ASC",
			$userid
		));

		if (!$monitors) continue;

		$period_label = $send_monthly ? 'Monatlicher' : 'Wöchentlicher';
		$period_days = $send_monthly ? 30 : 7;

		$from_ts = strtotime('-' . $period_days . ' days midnight');
		$to_ts = strtotime('today midnight');
		$date_from_short = date('d.m.Y', $from_ts);
		$date_to_short = date('d.m.Y', $to_ts);
		$date_from_long = date('d. ', $from_ts) . $months_de[(int)date('n', $from_ts)] . date(' Y', $from_ts);
		$date_to_long = date('d. ', $to_ts) . $months_de[(int)date('n', $to_ts)] . date(' Y', $to_ts);
		$date_from_sql = date('Y-m-d 00:00:00', $from_ts);

		$subject = $period_label . ' Uptime-Report (' . $date_from_short . ' – ' . $date_to_short . ') – Tallyr Monitor';

		// --- Collect per-monitor data ---
		$total_checks_all = 0;
		$total_up_all = 0;
		$total_outages_all = 0;
		$total_downtime_min_all = 0;
		$total_response_all = 0;
		$total_response_count = 0;
		$monitor_rows = array();

		foreach ($monitors as $m) {
			// All URLs for this monitor
			$all_urls = array($m->url);
			if (!empty($m->sub_urls)) {
				$subs = json_decode($m->sub_urls, true);
				if (is_array($subs)) $all_urls = array_merge($all_urls, $subs);
			}

			$url_rows = array();

			foreach ($all_urls as $url) {
				$total = (int)$wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND checked_at >= %s",
					$m->id, $url, $date_from_sql
				));
				$up = (int)$wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND is_up = 1 AND checked_at >= %s",
					$m->id, $url, $date_from_sql
				));
				$avg = $wpdb->get_var($wpdb->prepare(
					"SELECT AVG(response_time_ms) FROM $log_table WHERE monitor_id = %d AND checked_url = %s AND is_up = 1 AND checked_at >= %s",
					$m->id, $url, $date_from_sql
				));

				$uptime = $total > 0 ? round(($up / $total) * 100, 2) : 100;
				$avg_ms = $avg ? round($avg, 2) : 0;

				$url_rows[] = array(
					'url' => $url,
					'checks' => $total,
					'uptime' => $uptime,
					'avg_ms' => $avg_ms,
				);

				$total_checks_all += $total;
				$total_up_all += $up;
				if ($avg) {
					$total_response_all += $avg * $total;
					$total_response_count += $total;
				}
			}

			// Incidents for this monitor in period
			$outages = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
				$m->id, $date_from_sql
			));
			$downtime_min = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COALESCE(SUM(duration_minutes), 0) FROM $incidents_table WHERE monitor_id = %d AND started_at >= %s",
				$m->id, $date_from_sql
			));

			$total_outages_all += $outages;
			$total_downtime_min_all += $downtime_min;

			// Main URL uptime for display
			$main_uptime = $url_rows[0]['uptime'];

			$monitor_rows[] = array(
				'label' => $m->label,
				'urls' => $url_rows,
				'outages' => $outages,
				'downtime_min' => $downtime_min,
				'uptime' => $main_uptime,
			);
		}

		// --- Summary ---
		$summary_uptime = $total_checks_all > 0 ? round(($total_up_all / $total_checks_all) * 100, 2) : 100;
		$summary_response = $total_response_count > 0 ? round($total_response_all / $total_response_count, 2) : 0;

		// Helper: format downtime
		$fmt_downtime = function($mins) {
			if ($mins <= 0) return '–';
			if ($mins < 60) return $mins . ' Min';
			$h = floor($mins / 60);
			$m = $mins % 60;
			return $h . ' Std' . ($m > 0 ? ' ' . $m . ' Min' : '');
		};

		// --- Build Email ---
		$s = ''; // styles shorthand
		$body = '<div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;background-color:#f5f5f5;padding:20px;">';

		// Header
		$body .= '<div style="background-color:#ffffff;padding:24px 20px;border-radius:8px 8px 0 0;border:1px solid #eeeeee;border-bottom:none;">';
		$body .= '<h2 style="margin:0;color:#333333;font-size:20px;font-weight:700;">' . $period_label . ' Uptime-Report</h2>';
		$body .= '<p style="margin:6px 0 0;color:#999999;font-size:13px;">' . $date_from_long . ' 00:00 Uhr – ' . $date_to_long . ' 00:00 Uhr</p>';
		$body .= '</div>';

		// Summary box
		$body .= '<div style="background-color:#ffffff;padding:20px;border-left:1px solid #eeeeee;border-right:1px solid #eeeeee;">';
		$body .= '<h3 style="margin:0 0 15px;color:#333333;font-size:16px;font-weight:700;">Zusammenfassung ' . $date_from_long . ' – ' . $date_to_long . '</h3>';

		// Summary table
		$th = 'style="padding:10px 12px;text-align:center;font-size:12px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;"';
		$td = 'style="padding:14px 12px;text-align:center;font-size:15px;color:#333333;font-weight:700;"';
		$body .= '<table style="width:100%;border-collapse:collapse;" cellpadding="0" cellspacing="0">';
		$body .= '<tr>';
		$body .= '<td ' . $th . '>Checks</td>';
		$body .= '<td ' . $th . '>Uptime</td>';
		$body .= '<td ' . $th . '>Ausfälle</td>';
		$body .= '<td ' . $th . '>Ausfallzeit</td>';
		$body .= '<td ' . $th . '>Response-Time</td>';
		$body .= '</tr><tr>';
		$body .= '<td ' . $td . '>' . number_format($total_checks_all, 0, ',', '.') . '</td>';
		$ucolor = $summary_uptime >= 99 ? '#388e3c' : ($summary_uptime >= 95 ? '#f57c00' : '#d32f2f');
		$body .= '<td style="padding:14px 12px;text-align:center;font-size:15px;color:' . $ucolor . ';font-weight:700;">' . $summary_uptime . '%</td>';
		$body .= '<td ' . $td . '>' . $total_outages_all . '</td>';
		$body .= '<td ' . $td . '>' . $fmt_downtime($total_downtime_min_all) . '</td>';
		$body .= '<td ' . $td . '>' . $summary_response . ' ms</td>';
		$body .= '</tr></table>';
		$body .= '</div>';

		// Divider
		$body .= '<div style="height:1px;background-color:#eeeeee;"></div>';

		// Detail section
		$body .= '<div style="background-color:#ffffff;padding:20px;border-left:1px solid #eeeeee;border-right:1px solid #eeeeee;border-radius:0 0 8px 8px;border-bottom:1px solid #eeeeee;">';
		$body .= '<h3 style="margin:0 0 15px;color:#333333;font-size:16px;font-weight:700;">Details pro Website</h3>';

		// Detail table header
		$body .= '<table style="width:100%;border-collapse:collapse;" cellpadding="0" cellspacing="0">';
		$dth = 'style="padding:8px 10px;font-size:11px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;text-align:left;"';
		$dthc = 'style="padding:8px 10px;font-size:11px;color:#888888;font-weight:600;text-transform:uppercase;border-bottom:2px solid #eeeeee;text-align:center;"';
		$body .= '<tr>';
		$body .= '<td ' . $dth . '>Website</td>';
		$body .= '<td ' . $dthc . '>Uptime</td>';
		$body .= '<td ' . $dthc . '>Ausfälle</td>';
		$body .= '<td ' . $dthc . '>Ausfallzeit</td>';
		$body .= '<td ' . $dthc . '>Response</td>';
		$body .= '</tr>';

		foreach ($monitor_rows as $row) {
			// Main URL row
			$main = $row['urls'][0];
			$mc = $main['uptime'] >= 99 ? '#388e3c' : ($main['uptime'] >= 95 ? '#f57c00' : '#d32f2f');

			$body .= '<tr style="border-bottom:1px solid #f0f0f0;">';
			$body .= '<td style="padding:12px 10px;">';
			$body .= '<strong style="color:#333333;font-size:14px;">' . esc_html($row['label']) . '</strong><br>';
			$body .= '<span style="color:#999999;font-size:12px;">' . esc_html($main['url']) . '</span>';
			$body .= '</td>';
			$body .= '<td style="padding:12px 10px;text-align:center;color:' . $mc . ';font-weight:700;font-size:14px;">' . $main['uptime'] . '%</td>';
			$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $row['outages'] . '</td>';
			$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $fmt_downtime($row['downtime_min']) . '</td>';
			$body .= '<td style="padding:12px 10px;text-align:center;color:#333333;font-size:14px;">' . $main['avg_ms'] . ' ms</td>';
			$body .= '</tr>';

			// Sub-URL rows
			if (count($row['urls']) > 1) {
				for ($i = 1; $i < count($row['urls']); $i++) {
					$sub = $row['urls'][$i];
					$sc = $sub['uptime'] >= 99 ? '#388e3c' : ($sub['uptime'] >= 95 ? '#f57c00' : '#d32f2f');

					$body .= '<tr style="border-bottom:1px solid #f5f5f5;background-color:#fafafa;">';
					$body .= '<td style="padding:8px 10px 8px 30px;">';
					$body .= '<span style="color:#999999;font-size:12px;">↳ ' . esc_html($sub['url']) . '</span>';
					$body .= '</td>';
					$body .= '<td style="padding:8px 10px;text-align:center;color:' . $sc . ';font-weight:600;font-size:13px;">' . $sub['uptime'] . '%</td>';
					$body .= '<td style="padding:8px 10px;text-align:center;color:#999999;font-size:13px;">–</td>';
					$body .= '<td style="padding:8px 10px;text-align:center;color:#999999;font-size:13px;">–</td>';
					$body .= '<td style="padding:8px 10px;text-align:center;color:#333333;font-size:13px;">' . $sub['avg_ms'] . ' ms</td>';
					$body .= '</tr>';
				}
			}
		}

		$body .= '</table>';
		$body .= '</div>';

		// Footer
		$body .= '<div style="padding:15px 20px;text-align:center;">';
		$body .= '<p style="margin:0;color:#999999;font-size:12px;">Tallyr Monitor – Automatischer ' . $period_label . ' Report</p>';
		$body .= '</div>';

		$body .= '</div>';

		$headers = array('Content-Type: text/html; charset=UTF-8');
		$sent = wp_mail($user->user_email, $subject, $body, $headers);
		tallyr_log_email('report', $user->user_email, $subject, $period_label . ' Report', $sent);
	}
}

// ========================================
// CRON ENDPOINT HANDLER
// ========================================

function tallyr_handle_monitor_cron() {
	if (!isset($_GET['tallyr_cron']) || $_GET['tallyr_cron'] !== 'monitor') {
		return;
	}

	$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
	$stored_key = get_option('tallyr_monitor_cron_key');

	// Auto-generate key on first use
	if (!$stored_key) {
		$stored_key = wp_generate_password(32, false);
		update_option('tallyr_monitor_cron_key', $stored_key);
	}

	if ($key !== $stored_key) {
		status_header(403);
		echo 'Forbidden';
		exit;
	}

	// Run checks
	tallyr_run_monitor_checks();

	status_header(200);
	echo 'OK – ' . date('Y-m-d H:i:s');
	exit;
}
add_action('init', 'tallyr_handle_monitor_cron', 1);
