<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ( ! defined( 'ABSPATH' ) ) exit;

$share_hash = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
if (!$share_hash) { wp_redirect(home_url()); exit; }

global $wpdb;
$ps_table = $wpdb->prefix . 'tallyr_person_shares';
$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
$clients_table = $wpdb->prefix . 'tallyr_clients';

$share = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ps_table WHERE share_hash = %s", $share_hash));

if (!$share) {
	get_header();
	echo '<div style="max-width:600px;margin:80px auto;text-align:center;padding:40px;"><h2>Link ungültig</h2><p>Dieser Link ist ungültig oder wurde deaktiviert.</p></div>';
	get_footer();
	exit;
}

$person = $share->person_name;
$owner_id = $share->userid;

// Get kürzel
$kmap = array();
$all_u = get_users(array('fields' => array('ID', 'display_name')));
foreach ($all_u as $u) { $k = get_user_meta($u->ID, 'tallyr_kuerzel', true); if ($k) $kmap[$u->display_name] = $k; }
$ctags = get_user_meta($owner_id, 'tallyr_pp_tags', true);
$ctags = $ctags ? json_decode($ctags, true) : array();
if (is_array($ctags)) foreach ($ctags as $t) $kmap[$t['name']] = $t['kuerzel'];
$person_kuerzel = isset($kmap[$person]) ? $kmap[$person] : strtoupper(substr($person, 0, 3));

// Get all active plans
$plans = $wpdb->get_results($wpdb->prepare(
	"SELECT p.*, c.title AS client_title, c.shortdesc AS client_short, c.hexcolor AS client_color
	 FROM $plans_table p LEFT JOIN $clients_table c ON c.id = p.client_id
	 WHERE p.userid = %d AND p.state = 1
	 ORDER BY c.shortdesc ASC, p.title ASC", $owner_id
));

// Collect tasks for this person
$tasks_by_client = array();
$total_soll = 0;
$total_ist = 0;
$total_done = 0;
$total_open = 0;

foreach ($plans as $plan) {
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $rows_table WHERE plan_id = %d AND type = 'item' AND is_placeholder = 0 ORDER BY position ASC",
		$plan->id
	));
	foreach ($rows as $row) {
		$isLead = trim($row->lead_responsible ?? '') === $person;
		$isResp = false;
		if ($row->responsible) {
			$names = array_map('trim', explode(',', $row->responsible));
			$isResp = in_array($person, $names);
		}
		if (!$isLead && !$isResp) continue;

		$client_key = $plan->client_short ?: $plan->client_title ?: 'Ohne Kunde';
		if (!isset($tasks_by_client[$client_key])) {
			$tasks_by_client[$client_key] = array('color' => $plan->client_color ?: '#999', 'tasks' => array());
		}

		$soll = floatval($row->planned_hours);
		$ist = floatval($row->ist_hours);
		$done = intval($row->is_done);
		$total_soll += $soll;
		$total_ist += $ist;
		if ($done) $total_done++; else $total_open++;

		$tasks_by_client[$client_key]['tasks'][] = array(
			'plan' => $plan->title,
			'desc' => $row->description ?: '',
			'soll' => $soll,
			'ist' => $ist,
			'done' => $done,
			'lead' => $isLead,
			'deadline' => $row->deadline ?: '',
			'timeframe' => $row->timeframe ?: '',
			'responsible' => $row->responsible ?: '',
		);
	}
}

get_header();
?>

<style>
	#headerbar, #footer, .site-footer, footer { display:none !important; }
	body { background:#f8f9fa; }
	.pp-person { max-width:900px; margin:0 auto; padding:30px 20px; font-family:Arial,sans-serif; }
	.pp-person-header { margin-bottom:24px; padding:20px 24px; background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.06); }
	.pp-person-header h1 { margin:0; font-size:22px; color:#222; display:flex; align-items:center; gap:10px; }
	.pp-person-avatar { width:36px; height:36px; border-radius:8px; background:#e8edf2; color:#445; font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; }
	.pp-person-kpis { display:flex; gap:12px; margin-top:12px; flex-wrap:wrap; }
	.pp-person-kpi { padding:8px 14px; background:#f5f5f5; border-radius:6px; font-size:13px; color:#666; }
	.pp-person-kpi strong { color:#333; }
	.pp-person-client { margin-bottom:20px; }
	.pp-person-client-label { font-size:14px; font-weight:700; color:#333; padding:8px 0 6px; border-bottom:2px solid #e0e0e0; margin-bottom:0; display:flex; align-items:center; gap:6px; }
	.pp-person-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
	.pp-person table { width:100%; border-collapse:collapse; font-size:13px; background:#fff; }
	.pp-person th { padding:5px 8px; font-size:10px; text-transform:uppercase; letter-spacing:0.4px; color:#aaa; border-bottom:1px solid #eee; text-align:left; font-weight:500; }
	.pp-person td { padding:6px 8px; border-bottom:1px solid #f5f5f5; vertical-align:top; }
	.pp-person tr:hover td { background:#fafbfc; }
	.pp-person .done td { background:#f0faf0; color:#888; }
	.pp-person .done .td-desc { text-decoration:line-through; color:#aaa; }
	.pp-person .td-h { text-align:center; font-variant-numeric:tabular-nums; width:60px; }
	.pp-person .td-status { text-align:center; width:30px; }
	.pp-person .td-lead { font-size:10px; color:#e65100; font-weight:600; }
	.pp-person .td-plan { font-size:11px; color:#999; }
	.pp-person-footer { margin-top:24px; color:#ccc; font-size:11px; text-align:center; }
	@media print { #headerbar, #footer { display:none !important; } .pp-person-header { box-shadow:none; border:1px solid #ddd; } }
</style>

<div class="pp-person">
	<div class="pp-person-header">
		<h1><span class="pp-person-avatar"><?php echo esc_html($person_kuerzel); ?></span> <?php echo esc_html($person); ?></h1>
		<div class="pp-person-kpis">
			<div class="pp-person-kpi"><strong><?php echo str_replace('.', ',', $total_soll); ?> h</strong> Soll</div>
			<div class="pp-person-kpi"><strong><?php echo str_replace('.', ',', $total_ist); ?> h</strong> Ist</div>
			<div class="pp-person-kpi"><strong><?php echo $total_done; ?></strong> erledigt</div>
			<div class="pp-person-kpi"><strong><?php echo $total_open; ?></strong> offen</div>
			<div class="pp-person-kpi"><strong><?php echo $total_done + $total_open; ?></strong> gesamt</div>
		</div>
	</div>

	<?php foreach ($tasks_by_client as $client_name => $client_data): ?>
	<div class="pp-person-client">
		<div class="pp-person-client-label"><span class="pp-person-dot" style="background:<?php echo esc_attr($client_data['color']); ?>;"></span> <?php echo esc_html($client_name); ?></div>
		<table>
			<thead><tr><th>Plan</th><th>Aufgabe</th><th class="td-h">Soll (h)</th><th class="td-h">Ist (h)</th><th>Zeitraum</th><th>Deadline</th><th class="td-status"></th><th></th></tr></thead>
			<tbody>
			<?php
			$c_soll = 0; $c_ist = 0;
			foreach ($client_data['tasks'] as $t):
				$c_soll += $t['soll'];
				$c_ist += $t['ist'];
				$cls = $t['done'] ? 'done' : '';
			?>
				<tr class="<?php echo $cls; ?>">
					<td class="td-plan"><?php echo esc_html($t['plan']); ?></td>
					<td class="td-desc"><?php echo esc_html($t['desc']); ?></td>
					<td class="td-h"><?php echo $t['soll'] ? str_replace('.', ',', $t['soll']) : ''; ?></td>
					<td class="td-h"><?php echo $t['ist'] ? str_replace('.', ',', $t['ist']) : ''; ?></td>
					<td style="font-size:11px;color:#888;"><?php echo esc_html($t['timeframe']); ?></td>
					<td style="font-size:11px;color:#888;"><?php echo esc_html($t['deadline']); ?></td>
					<td class="td-status"><?php echo $t['done'] ? '<span style="color:#4caf50;">&#10003;</span>' : ''; ?></td>
					<td class="td-lead"><?php echo $t['lead'] ? 'HV' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
				<tr style="background:#f5f5f5;font-weight:600;"><td></td><td style="text-align:right;">Summe</td><td class="td-h"><?php echo str_replace('.', ',', $c_soll); ?></td><td class="td-h"><?php echo str_replace('.', ',', $c_ist); ?></td><td colspan="4"></td></tr>
			</tbody>
		</table>
	</div>
	<?php endforeach; ?>

	<?php if (empty($tasks_by_client)): ?>
		<div style="text-align:center;padding:40px;color:#999;">Keine Aufgaben für diese Person gefunden.</div>
	<?php endif; ?>

	<p class="pp-person-footer">Stand: <?php echo date('d.m.Y H:i'); ?> Uhr</p>
</div>

<?php get_footer(); ?>
