<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currentuserid = get_current_user_id();
if (!$currentuserid) {
	echo '<p>Bitte einloggen.</p>';
	return;
}

global $wpdb;
$clients_table = $wpdb->prefix . 'tallyr_clients';
$clients = $wpdb->get_results($wpdb->prepare(
	"SELECT id, title, hexcolor FROM $clients_table WHERE userid = %d AND state = 1 ORDER BY title ASC",
	$currentuserid
));

// Get cron key
$cron_key = get_option('tallyr_monitor_cron_key');
if (!$cron_key) {
	$cron_key = wp_generate_password(32, false);
	update_option('tallyr_monitor_cron_key', $cron_key);
}
$cron_url = home_url('/?tallyr_cron=monitor&key=' . $cron_key);
?>

<div id="monitor-dashboard">

	<div class="monitor-header">
		<h2><i class='bx bx-globe'></i> Website Monitor</h2>
		<div class="header-actions">
			<?php if (current_user_can('manage_options')): ?>
				<button class="btn-secondary" id="monitor-show-cron"><i class='bx bx-link'></i> Cron-URL</button>
				<button class="btn-secondary" id="monitor-test-report"><i class='bx bx-envelope'></i> Report testen</button>
			<?php endif; ?>
			<button class="btn-secondary" id="monitor-batch-btn"><i class='bx bx-import'></i> Batch-Import</button>
			<button class="btn-primary" id="monitor-add-btn"><i class='bx bx-plus'></i> Website hinzufügen</button>
		</div>
	</div>

	<div class="monitor-info-box">
		<i class='bx bx-info-circle'></i>
		<span>Alle Websites werden alle 2 Minuten per Cronjob geprüft. Bei 2 fehlgeschlagenen Checks in Folge wird eine Alert-Mail gesendet. Sobald die Website wieder erreichbar ist, kommt eine Recovery-Mail.</span>
	</div>

	<div class="monitor-toolbar">
		<div class="monitor-search">
			<i class='bx bx-search'></i>
			<input type="text" id="monitor-search" placeholder="Suchen...">
		</div>
		<div class="monitor-toolbar-right">
			<button class="monitor-font-toggle" id="monitor-font-toggle" title="Schriftgröße umschalten"><i class='bx bx-font-size'></i></button>
			<div class="monitor-view-toggle">
				<button class="monitor-view-btn active" data-view="grid" title="Kachelansicht"><i class='bx bx-grid-alt'></i></button>
				<button class="monitor-view-btn" data-view="list" title="Listenansicht"><i class='bx bx-list-ul'></i></button>
			</div>
		</div>
	</div>

	<div id="monitor-filters" class="monitor-filters" style="display:none;"></div>

	<?php if (current_user_can('manage_options')): ?>
	<div id="monitor-cron-info" style="display:none;">
		<div class="cron-box">
			<label>Cron-URL (alle 2 Minuten per Cronjob aufrufen):</label>
			<div class="cron-url-row">
				<input type="text" value="<?php echo esc_attr($cron_url); ?>" readonly id="monitor-cron-url">
				<button class="btn-secondary" id="monitor-copy-cron"><i class='bx bx-copy'></i></button>
			</div>
			<small>Beispiel: <code>*/2 * * * * curl -s "<?php echo esc_html($cron_url); ?>" > /dev/null</code></small>
		</div>
		<div class="cron-log-toggle">
			<button class="btn-secondary" id="monitor-show-cron-log"><i class='bx bx-history'></i> Cron-Log</button>
			<button class="btn-secondary" id="monitor-show-email-log"><i class='bx bx-envelope'></i> E-Mail-Log</button>
		</div>
		<div id="monitor-cron-log" style="display:none;"></div>
		<div id="monitor-email-log" style="display:none;"></div>
	</div>
	<?php endif; ?>

	<div id="monitor-bulk-bar" class="monitor-bulk-bar" style="display:none;">
		<span><strong id="monitor-bulk-count">0</strong> ausgewählt</span>
		<button class="btn-secondary" id="monitor-bulk-select-all"><i class='bx bx-select-multiple'></i> Alle auswählen</button>
		<button class="btn-primary" id="monitor-bulk-categorize"><i class='bx bx-category'></i> Kategorie zuweisen</button>
	</div>

	<div id="monitor-loading">
		<i class='bx bx-loader-alt bx-spin'></i> Lade Monitors...
	</div>

	<div id="monitor-list" style="display:none;"></div>

	<!-- Add/Edit Modal -->
	<div id="monitor-modal" class="monitor-modal" style="display:none;">
		<div class="modal-overlay"></div>
		<div class="modal-content">
			<div class="modal-header">
				<h3><i class='bx bx-globe'></i> <span id="monitor-modal-title">Website hinzufügen</span></h3>
				<button class="modal-close"><i class='bx bx-x'></i></button>
			</div>
			<form id="monitor-form">
				<input type="hidden" id="monitor-edit-id" value="0">
				<div class="form-row">
					<label>URL *</label>
					<div class="input-with-btn">
						<input type="url" id="monitor-url" placeholder="https://example.com" required>
						<button type="button" id="monitor-fetch-title" title="Seitentitel laden"><i class='bx bx-search-alt'></i></button>
					</div>
				</div>
				<div class="form-row">
					<label>Label * <span id="monitor-label-loading" style="display:none;color:#999;font-weight:400;"><i class='bx bx-loader-alt bx-spin' style="font-size:13px;"></i> Lade...</span></label>
					<input type="text" id="monitor-label" placeholder="Wird automatisch geladen..." required>
				</div>
				<div class="form-row">
					<label>Kunde</label>
					<select id="monitor-client">
						<option value="0">– Kein Kunde –</option>
						<?php foreach ($clients as $c): ?>
							<option value="<?php echo $c->id; ?>" data-color="<?php echo esc_attr($c->hexcolor); ?>">
								<?php echo esc_html($c->title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-row">
					<label>Kategorie</label>
					<div class="input-with-datalist">
						<input type="text" id="monitor-category" placeholder="Auswählen oder neu eingeben..." list="monitor-category-list">
						<datalist id="monitor-category-list"></datalist>
						<i class='bx bx-chevron-down datalist-arrow'></i>
					</div>
				</div>
				<div class="form-row">
					<label>Unterseiten (optional, eine URL pro Zeile)</label>
					<textarea id="monitor-sub-urls" rows="3" placeholder="https://example.com/kontakt&#10;https://example.com/blog&#10;https://example.com/shop"></textarea>
				</div>
				<div class="form-row">
					<label>Alert E-Mail (optional, sonst deine Account-E-Mail)</label>
					<input type="email" id="monitor-alert-email" placeholder="alert@example.com">
				</div>
				<div class="form-row">
					<label>Report</label>
					<select id="monitor-report">
						<option value="both">Wöchentlich + Monatlich</option>
						<option value="weekly">Wöchentlich (Mo)</option>
						<option value="monthly">Monatlich (1.)</option>
						<option value="none">Kein Report</option>
					</select>
				</div>
				<div class="form-actions">
					<button type="button" class="btn-secondary modal-close">Abbrechen</button>
					<button type="submit" class="btn-primary"><i class='bx bx-check'></i> Speichern</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Detail Modal -->
	<div id="monitor-detail-modal" class="monitor-modal" style="display:none;">
		<div class="modal-overlay"></div>
		<div class="modal-content" style="max-width:800px;">
			<div class="modal-header">
				<h3><i class='bx bx-bar-chart-alt-2'></i> <span id="monitor-detail-title">Details</span></h3>
				<button class="modal-close"><i class='bx bx-x'></i></button>
			</div>
			<div class="modal-body">
				<div id="monitor-detail-stats"></div>
				<div id="monitor-detail-urls"></div>
				<div id="monitor-detail-chart"></div>
				<div id="monitor-detail-timeline">
					<div class="timeline-header">
						<h4>Verlauf</h4>
						<div class="timeline-periods">
							<button class="timeline-period active" data-period="30d">30 Tage</button>
							<button class="timeline-period" data-period="90d">Quartal</button>
							<button class="timeline-period" data-period="365d">Jahr</button>
							<button class="timeline-period" data-period="all">Gesamt</button>
						</div>
					</div>
					<div id="timeline-summary"></div>
					<div id="timeline-uptime-bar"></div>
					<div id="timeline-response-chart">
						<canvas id="timeline-canvas" height="160"></canvas>
					</div>
				</div>
				<div id="monitor-detail-incidents"></div>
				<div id="monitor-detail-log"></div>
			</div>
		</div>
	</div>

	<!-- Batch Import Modal -->
	<div id="monitor-batch-modal" class="monitor-modal" style="display:none;">
		<div class="modal-overlay"></div>
		<div class="modal-content">
			<div class="modal-header">
				<h3><i class='bx bx-import'></i> Batch-Import</h3>
				<button class="modal-close"><i class='bx bx-x'></i></button>
			</div>
			<form id="monitor-batch-form">
				<div class="form-row">
					<label>URLs (eine pro Zeile)</label>
					<textarea id="monitor-batch-urls" rows="8" placeholder="https://example.com&#10;https://example.org&#10;https://example.net"></textarea>
				</div>
				<div class="form-row">
					<label>Kunde (für alle)</label>
					<select id="monitor-batch-client">
						<option value="0">– Kein Kunde –</option>
						<?php foreach ($clients as $c): ?>
							<option value="<?php echo $c->id; ?>">
								<?php echo esc_html($c->title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-row">
					<label>Kategorie (für alle)</label>
					<div class="input-with-datalist">
						<input type="text" id="monitor-batch-category" placeholder="Auswählen oder neu eingeben..." list="monitor-category-list">
						<i class='bx bx-chevron-down datalist-arrow'></i>
					</div>
				</div>
				<div class="form-row">
					<label>Report (für alle)</label>
					<select id="monitor-batch-report">
						<option value="both">Wöchentlich + Monatlich</option>
						<option value="weekly">Wöchentlich (Mo)</option>
						<option value="monthly">Monatlich (1.)</option>
						<option value="none">Kein Report</option>
					</select>
				</div>
				<p class="batch-hint">Seitentitel werden automatisch von jeder URL geladen.</p>
				<div class="form-actions">
					<button type="button" class="btn-secondary modal-close">Abbrechen</button>
					<button type="submit" class="btn-primary"><i class='bx bx-import'></i> Importieren</button>
				</div>
			</form>
		</div>
	</div>

</div>