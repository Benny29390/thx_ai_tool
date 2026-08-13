<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ( ! defined( 'ABSPATH' ) ) exit;

$share_hash = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
if (!$share_hash) { wp_redirect(home_url()); exit; }

global $wpdb;
$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
$rows_table = $wpdb->prefix . 'tallyr_projektplanner_rows';
$clients_table = $wpdb->prefix . 'tallyr_clients';
$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';

$plan = $wpdb->get_row($wpdb->prepare(
	"SELECT p.*, c.title AS client_title, c.shortdesc AS client_short, c.url AS client_url
	 FROM $plans_table p LEFT JOIN $clients_table c ON c.id = p.client_id
	 WHERE p.share_hash = %s AND p.state = 1 AND p.share_hash != ''",
	$share_hash
));

if (!$plan) {
	get_header();
	echo '<div style="max-width:600px;margin:80px auto;text-align:center;padding:40px;"><h2>Plan nicht gefunden</h2><p>Dieser Link ist ungültig oder wurde deaktiviert.</p></div>';
	get_footer();
	exit;
}

// Password check
$needs_password = !empty($plan->share_password);
$authenticated = false;
if ($needs_password) {
	if (!session_id()) session_start();
	$session_key = 'pp_access_' . $plan->id;
	if (isset($_POST['pp_password'])) {
		if (wp_check_password($_POST['pp_password'], $plan->share_password)) {
			$_SESSION[$session_key] = true;
			$authenticated = true;
		} else {
			$password_error = true;
		}
	}
	if (isset($_SESSION[$session_key]) && $_SESSION[$session_key]) $authenticated = true;
} else {
	$authenticated = true;
}

get_header();

if (!$authenticated): ?>
<div style="max-width:400px;margin:80px auto;padding:30px;background:#fff;border-radius:10px;box-shadow:0 2px 20px rgba(0,0,0,.08);">
	<h2 style="margin:0 0 5px;font-size:20px;">Projektplan</h2>
	<p style="color:#888;font-size:14px;margin:0 0 20px;"><?php echo esc_html($plan->client_title); ?> · <?php echo esc_html($plan->title); ?></p>
	<?php if (isset($password_error)): ?><p style="color:#d32f2f;font-size:13px;margin:0 0 10px;">Falsches Passwort.</p><?php endif; ?>
	<form method="post">
		<input type="password" name="pp_password" placeholder="Passwort eingeben" autofocus style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;margin-bottom:10px;box-sizing:border-box;">
		<button type="submit" style="width:100%;padding:10px;background:#333;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">Anzeigen</button>
	</form>
</div>
<?php else:

$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $rows_table WHERE plan_id = %d ORDER BY position ASC", $plan->id));
$period = '';
if ($plan->period_from && $plan->period_to) $period = date('d.m.Y', strtotime($plan->period_from)) . ' – ' . date('d.m.Y', strtotime($plan->period_to));

// Kürzels
$kmap = array();
$all_u = get_users(array('fields' => array('ID', 'display_name')));
foreach ($all_u as $u) { $k = get_user_meta($u->ID, 'tallyr_kuerzel', true); if ($k) $kmap[$u->display_name] = $k; }
$ctags = get_user_meta($plan->userid, 'tallyr_pp_tags', true);
$ctags = $ctags ? json_decode($ctags, true) : array();
if (is_array($ctags)) foreach ($ctags as $t) $kmap[$t['name']] = $t['kuerzel'];

function ppk($name, $map) { $name = trim($name); return isset($map[$name]) ? $map[$name] : strtoupper(substr($name, 0, 3)); }

// Existing feedback for this plan
$all_feedback = $wpdb->get_results($wpdb->prepare("SELECT * FROM $feedback_table WHERE plan_id = %d ORDER BY created ASC", $plan->id));
$fb_by_row = array();
foreach ($all_feedback as $fb) {
	$rid = (int)$fb->row_id;
	if (!isset($fb_by_row[$rid])) $fb_by_row[$rid] = array();
	$fb_by_row[$rid][] = $fb;
}
?>

<style>
	#headerbar, #footer, .site-footer, footer { display:none !important; }
	body { background:#f8f9fa; }
	.pp-pub { max-width:900px; margin:0 auto; padding:30px 20px; font-family:Arial,sans-serif; }
	.pp-pub-header { margin-bottom:24px; padding:20px 24px; background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.06); }
	.pp-pub-header h1 { margin:0; font-size:24px; color:#222; font-weight:700; }
	.pp-pub-header .pp-pub-sub { color:#666; font-size:14px; margin-top:4px; }
	.pp-pub-header .pp-pub-url { font-size:11px; color:#aaa; margin-top:6px; line-height:1.5; }
	.pp-pub-header .pp-pub-url a { color:#888; text-decoration:none; }
	.pp-pub-header .pp-pub-url a:hover { text-decoration:underline; }
	.pp-pub table { width:100%; border-collapse:collapse; font-size:14px; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.04); }
	.pp-pub th { background:#f5f5f5; padding:6px 10px; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#aaa; border-bottom:2px solid #ddd; text-align:left; font-weight:500; }
	.pp-pub th.col-h { text-align:center; width:70px; }
	.pp-pub td { padding:6px 10px; border-bottom:1px solid #eee; vertical-align:top; }
	.pp-pub .sec-row td { background:#f0f4f8; font-weight:700; font-size:14px; border-top:3px solid #c0cad5; border-bottom:1px solid #dce3ea; padding:8px 10px; }
	.pp-pub .note-row td { font-size:11px; color:#999; font-style:italic; }
	.pp-pub .note-row a { color:#6688bb; }
	.pp-pub .item-done td { background:#f0faf0; }
	.pp-pub .td-hours { text-align:center; font-variant-numeric:tabular-nums; }
	.pp-pub .td-resp .kz { display:inline-block; padding:1px 5px; border-radius:3px; background:#e8edf2; font-size:10px; font-weight:600; color:#445; margin-right:2px; }
	.pp-pub .td-done { text-align:center; }
	.pp-pub .sub-row td { background:#f5f5f5; border-bottom:1px solid #ddd; }
	.pp-pub .total-row td { background:#e8f5e9; font-weight:700; border-top:2px solid #4caf50; border-bottom:2px solid #4caf50; }
	.pp-pub .ts-row td { background:#f5f5f5; }
	.pp-pub-footer { margin-top:24px; color:#ccc; font-size:11px; text-align:center; font-style:italic; }
	.pp-pub-footer a { color:#bbb; text-decoration:none; }
	/* Feedback – filigran */
	.pp-fb-icons { display:inline-flex; gap:1px; margin-left:4px; vertical-align:middle; opacity:0.4; transition:opacity .15s; }
	tr:hover .pp-fb-icons { opacity:1; }
	.pp-fb-icons button { border:none; background:transparent; cursor:pointer; font-size:12px; color:#ccc; padding:1px 2px; line-height:1; }
	.pp-fb-icons button:hover { color:#888; }
	.pp-fb-icons button.fb-liked { color:#4caf50; opacity:1; }
	.pp-fb-icons button.fb-disliked { color:#f44336; opacity:1; }
	.pp-fb-badge { font-size:8px; padding:0 3px; border-radius:6px; margin-left:1px; font-weight:700; }
	.pp-fb-badge-like { background:#e8f5e9; color:#2e7d32; }
	.pp-fb-badge-dislike { background:#fce4ec; color:#c62828; }
	.pp-fb-comments { margin-top:4px; }
	.pp-fb-comment { font-size:11px; color:#666; background:#f8f8f8; padding:4px 8px; border-radius:4px; margin-top:3px; border-left:2px solid #ddd; }
	.pp-fb-comment .fb-author { font-weight:600; color:#444; }
	.pp-fb-comment .fb-time { color:#bbb; font-size:9px; margin-left:4px; }
	.pp-fb-comment.fb-read { border-left-color:#4caf50; }
	.pp-fb-comment .fb-read-badge { color:#4caf50; font-weight:700; margin-right:4px; font-size:13px; }
	.pp-fb-comment .fb-actions { float:right; opacity:0; transition:opacity .15s; }
	.pp-fb-comment:hover .fb-actions { opacity:1; }
	.pp-fb-comment .fb-actions button { border:none; background:transparent; cursor:pointer; color:#ccc; font-size:14px; padding:0 2px; }
	.pp-fb-comment .fb-actions button:hover { color:#888; }
	.pp-fb-comment .fb-edit-input { width:100%; padding:4px 6px; border:1px solid #ddd; border-radius:3px; font-size:11px; margin-top:3px; }
	#importantstyle .pp-fb-input { display:none; margin-top:6px; }
	#importantstyle .pp-fb-input-row { display:flex; gap:4px; align-items:center; }
	#importantstyle .pp-fb-input-row input { flex:0 0 100px; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:12px; height:32px; box-sizing:border-box; }
	#importantstyle .pp-fb-input-row textarea { flex:1; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:12px; resize:none; height:32px; box-sizing:border-box; background:#fff; }
	#importantstyle .pp-fb-input-row button { width:32px; height:32px; background:#333; color:#fff; border:none; border-radius:4px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
	@media print { .pp-fb-icons, .pp-fb-input, .pp-fb-comments { display:none !important; } #headerbar, #footer { display:none !important; } }
</style>

<div id="importantstyle" class="pp-pub">
	<div class="pp-pub-header">
		<h1><?php echo esc_html($plan->client_title); ?></h1>
		<div class="pp-pub-sub">
			<?php echo esc_html($plan->title); ?>
			<?php if ($period): ?><span style="color:#aaa;margin:0 6px;">·</span><span style="color:#999;"><?php echo $period; ?></span><?php endif; ?>
		</div>
		<?php if ($plan->client_url): ?>
			<div class="pp-pub-url"><?php
				$urls = explode("\n", $plan->client_url);
				foreach ($urls as $u) {
					$u = trim($u);
					if ($u) echo '<a href="' . esc_url($u) . '" target="_blank">' . esc_html($u) . '</a><br>';
				}
			?></div>
		<?php endif; ?>
	</div>

	<table>
		<thead>
			<tr>
				<th>Beschreibung</th>
				<th class="col-h">Aufwand (h)</th>
				<th style="width:80px;">Umsetzung</th>
				<th style="width:30px;"></th>
				<th style="width:30px;"></th>
			</tr>
		</thead>
		<tbody>
		<?php
		$sec_ist = 0; $sec_soll = 0; $sec_open = false;
		$tot_ist = 0; $tot_soll = 0;

		// Pre-calculate which sections have visible items (skip empty sections)
		$section_has_items = array();
		$current_sec_idx = -1;
		foreach ($rows as $idx => $r) {
			if ($r->type === 'section') { $current_sec_idx = $idx; $section_has_items[$idx] = false; }
			elseif ($r->type === 'item' && !intval($r->is_placeholder) && $current_sec_idx >= 0) { $section_has_items[$current_sec_idx] = true; }
		}
		$in_empty_section = false;

		foreach ($rows as $idx => $row):
			// Skip spacers and placeholders
			if ($row->type === 'spacer') continue;
			if ($row->type === 'item' && intval($row->is_placeholder)) continue;

			// Skip empty sections and their notes
			if ($row->type === 'section' && isset($section_has_items[$idx]) && !$section_has_items[$idx]) {
				$in_empty_section = true;
				continue;
			}
			if ($row->type === 'section') { $in_empty_section = false; }
			if ($in_empty_section && $row->type === 'note') continue;

			$rid = (int)$row->id;
			$fb_list = isset($fb_by_row[$rid]) ? $fb_by_row[$rid] : array();
			$likes = 0; $dislikes = 0; $comments = array();
			foreach ($fb_list as $fb) {
				if ($fb->feedback_type === 'like') $likes++;
				elseif ($fb->feedback_type === 'dislike') $dislikes++;
				elseif ($fb->feedback_type === 'comment') $comments[] = $fb;
			}

			if ($row->type === 'section'):
				if ($sec_open): ?>
					<tr class="sub-row"><td style="text-align:right;">Aufwand ca. in Std.</td><td class="td-hours"><?php echo str_replace('.', ',', $sec_soll ?: 0); ?></td><td></td><td></td><td></td></tr>
				<?php endif;
				$sec_open = true; $sec_ist = 0; $sec_soll = 0;
				?>
				<tr class="sec-row"><td colspan="5"><?php echo esc_html($row->description); ?></td></tr>
			<?php elseif ($row->type === 'note'):
				$note = esc_html($row->description);
				$isLink = preg_match('/^https?:\/\//', $row->description);
				?>
				<tr class="note-row"><td colspan="5"><?php echo $isLink ? '<a href="' . esc_url($row->description) . '" target="_blank">' . $note . '</a>' : nl2br($note); ?></td></tr>
			<?php else:
				$ist = floatval($row->ist_hours); $soll = floatval($row->planned_hours);
				$sec_ist += $ist; $sec_soll += $soll; $tot_ist += $ist; $tot_soll += $soll;
				$done = intval($row->is_done);
				$cls = $done ? 'item-done' : '';

				// Description + suffix (Datum, Kürzel — lead first, then responsible, no duplicates)
				$desc = $row->description ?: '';
				$suffix = array();
				if ($row->timeframe) $suffix[] = $row->timeframe;
				$seen_kz = array();
				$lead = isset($row->lead_responsible) ? trim($row->lead_responsible) : '';
				if ($lead) {
					$kz = ppk($lead, $kmap);
					$seen_kz[$kz] = true;
					$suffix[] = $kz;
				}
				if ($row->responsible) {
					foreach (explode(',', $row->responsible) as $rn) {
						$rn = trim($rn);
						if (!$rn) continue;
						$kz = ppk($rn, $kmap);
						if (!isset($seen_kz[$kz])) {
							$seen_kz[$kz] = true;
							$suffix[] = $kz;
						}
					}
				}
				if ($suffix) $desc .= ' (' . implode(', ', $suffix) . ')';

				// Resp tags
				$resp_html = '';
				if ($row->responsible) {
					foreach (explode(',', $row->responsible) as $rn) {
						$rn = trim($rn);
						if ($rn) $resp_html .= '<span class="kz">' . esc_html(ppk($rn, $kmap)) . '</span>';
					}
				}
				?>
				<tr class="<?php echo $cls; ?>" data-rowid="<?php echo $rid; ?>">
					<td>
						<?php echo nl2br(esc_html($desc)); ?>
						<span class="pp-fb-icons">
							<button class="pp-fb-btn pp-fb-toggle-comment" title="Kommentar">&#x1F4AC;</button>
						</span>
						<?php if ($comments): ?>
						<div class="pp-fb-comments">
							<?php foreach ($comments as $c):
								$isRead = !empty($c->read_at);
							?>
								<div class="pp-fb-comment<?php echo $isRead ? ' fb-read' : ''; ?>" data-fbid="<?php echo $c->id; ?>">
									<?php if ($isRead): ?><span class="fb-read-badge" title="Gelesen & berücksichtigt">&#10003;</span><?php endif; ?>
									<span class="fb-author"><?php echo esc_html($c->author_name); ?></span><span class="fb-time"><?php echo date('d.m. H:i', strtotime($c->created)); ?></span>: <span class="fb-text"><?php echo nl2br(esc_html($c->message)); ?></span>
									<span class="fb-actions"><button class="fb-edit-btn" title="Bearbeiten">&#9998;</button><button class="fb-delete-btn" title="Löschen">&times;</button></span>
								</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
						<div class="pp-fb-input">
							<div class="pp-fb-input-row">
								<input type="text" class="pp-fb-name" placeholder="Dein Name">
								<textarea class="pp-fb-msg" rows="1" placeholder="Kommentar..."></textarea>
								<button class="pp-fb-send" title="Senden">&#10148;</button>
							</div>
						</div>
					</td>
					<td class="td-hours"><?php echo $soll ? str_replace('.', ',', $soll) : ''; ?></td>
					<td style="font-size:12px;color:#666;"><?php echo esc_html($row->deadline ?: ''); ?></td>
					<td class="td-done"><?php echo $done ? '<span style="color:#4caf50;">&#10003;</span>' : ''; ?></td>
					<td style="text-align:center;"><?php if ($row->asana_url): ?><a href="<?php echo esc_url($row->asana_url); ?>" target="_blank" style="color:#999;font-size:14px;" title="Asana"><i class="bx bx-link-external"></i></a><?php endif; ?></td>
				</tr>
			<?php endif;
		endforeach;

		if ($sec_open): ?>
			<tr class="sub-row"><td style="text-align:right;">Aufwand ca. in Std.</td><td class="td-hours"><?php echo str_replace('.', ',', $sec_soll ?: 0); ?></td><td></td><td></td><td></td></tr>
		<?php endif; ?>
		<tr><td colspan="5" style="height:8px;"></td></tr>
		<tr class="total-row"><td>Aufwand ca. insgesamt in Std.</td><td class="td-hours"><?php echo str_replace('.', ',', $tot_soll); ?></td><td></td><td></td><td></td></tr>
		<?php $ts = $tot_soll > 0 ? floor($tot_soll / 4) * 0.5 : 0; ?>
		<tr class="ts-row"><td>Aufwand ca. in Tagessätzen</td><td class="td-hours"><?php echo str_replace('.', ',', $ts); ?> TS</td><td></td></tr>
		</tbody>
	</table>

	<p class="pp-pub-footer">Noch Rückfragen? Kontakt zur Thoxan Communications GmbH unter <a href="https://www.thoxan.com/kontakt" style="color:#aaa;">thoxan.com/kontakt</a></p>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
var ppShareHash = '<?php echo esc_js($share_hash); ?>';
var ppAjaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';

// Like/Dislike
$(document).on('click', '.pp-fb-btn[data-type]', function() {
	var $btn = $(this);
	var $tr = $btn.closest('tr');
	var rowId = $tr.data('rowid');
	var type = $btn.data('type');
	var name = $tr.find('.pp-fb-name').val() || localStorage.getItem('pp_fb_name') || 'Anonym';

	$.post(ppAjaxUrl, { action: 'uf_pp_submit_feedback', share_hash: ppShareHash, row_id: rowId, feedback_type: type, author_name: name, message: '' }, function(res) {
		if (res.success) {
			$btn.addClass(type === 'like' ? 'fb-liked' : 'fb-disliked');
			var $badge = $btn.find('.pp-fb-badge');
			if ($badge.length) { $badge.text(parseInt($badge.text() || 0) + 1); }
			else { $btn.append('<span class="pp-fb-badge pp-fb-badge-' + type + '">1</span>'); }
		}
	});
});

// Toggle comment input
$(document).on('click', '.pp-fb-toggle-comment', function() {
	$(this).closest('td').find('.pp-fb-input').slideToggle(150);
});

// Send comment
$(document).on('click', '.pp-fb-send', function() {
	var $input = $(this).closest('.pp-fb-input');
	var $tr = $(this).closest('tr');
	var rowId = $tr.data('rowid');
	var name = $input.find('.pp-fb-name').val().trim();
	var msg = $input.find('.pp-fb-msg').val().trim();
	if (!msg) return;
	if (name) localStorage.setItem('pp_fb_name', name);
	if (!name) name = localStorage.getItem('pp_fb_name') || 'Anonym';

	$.post(ppAjaxUrl, { action: 'uf_pp_submit_feedback', share_hash: ppShareHash, row_id: rowId, feedback_type: 'comment', author_name: name, message: msg }, function(res) {
		if (res.success) {
			var $comments = $tr.find('.pp-fb-comments');
			if (!$comments.length) { $comments = $('<div class="pp-fb-comments"></div>'); $input.before($comments); }
			$comments.append('<div class="pp-fb-comment"><span class="fb-author">' + name + '</span>: ' + msg + '</div>');
			$input.find('.pp-fb-msg').val('');
		}
	});
});

// Restore name from localStorage
$(function() {
	var saved = localStorage.getItem('pp_fb_name');
	if (saved) $('.pp-fb-name').val(saved);
});

// Delete feedback (public)
$(document).on('click', '.fb-delete-btn', function() {
	var $comment = $(this).closest('.pp-fb-comment');
	var fbId = $comment.data('fbid');
	if (!fbId) return;
	$comment.fadeOut(150, function() { $comment.remove(); });
	$.post(ppAjaxUrl, { action: 'uf_pp_delete_feedback_public', share_hash: ppShareHash, feedback_id: fbId });
});

// Edit feedback (public)
$(document).on('click', '.fb-edit-btn', function() {
	var $comment = $(this).closest('.pp-fb-comment');
	var fbId = $comment.data('fbid');
	var $text = $comment.find('.fb-text');
	var currentText = $text.text().trim();

	if ($comment.find('.fb-edit-input').length) return;

	$text.hide();
	$comment.find('.fb-actions').hide();
	var $input = $('<input type="text" class="fb-edit-input" value="' + currentText.replace(/"/g, '&quot;') + '">');
	$text.after($input);
	$input.focus().select();

	$input.on('keydown', function(e) {
		if (e.key === 'Enter') {
			var newText = $input.val().trim();
			if (newText) {
				$text.html(newText).show();
				$.post(ppAjaxUrl, { action: 'uf_pp_edit_feedback_public', share_hash: ppShareHash, feedback_id: fbId, message: newText });
			}
			$input.remove();
			$comment.find('.fb-actions').show();
		}
		if (e.key === 'Escape') {
			$text.show();
			$input.remove();
			$comment.find('.fb-actions').show();
		}
	});
	$input.on('blur', function() {
		$text.show();
		$input.remove();
		$comment.find('.fb-actions').show();
	});
});
</script>

<?php endif; get_footer(); ?>
