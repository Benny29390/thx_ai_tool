<?php
// no cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
get_header('userdash');

$hidepass = false;
// if current user is admin and userid is in client is same 
global $wpdb;

$times_id = (STRING)$_GET['id'];
$isadmin = current_user_can('administrator');

if (!$times_id && !$isadmin) {
	wp_redirect(home_url());
	exit;
}


// look for client in Tabelle: wpt_tallyr_clients in randomlink

$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE randomlink = '$times_id'");

if (!$client && !$isadmin) {
	wp_redirect(home_url());
	exit;
}

$nonce = wp_create_nonce('get_projects_and_times123');

$color = $client->hexcolor;
if (!$color) {
	$color = '#000';
}

if ($isadmin && $client->userid == get_current_user_id()) {
	$hidepass = true;
}

$selc = $client->title;

if ($isadmin) {
$sclients = $wpdb->get_results("SELECT * FROM wpt_tallyr_clients WHERE userid = " . get_current_user_id(). " ORDER BY title ASC");
	if ($sclients) {
		$selc = '<select id="choosaclient">';
		$selc .= '<option value="">Bitte wählen</option>';
			foreach ($sclients as $sclient) {
				if ($sclient->randomlink == $times_id) {
					$selected = ' selected';
				} else {
					$selected = '';
				}
				$selc .= '<option value="' . $sclient->randomlink . '"'.$selected.'>' . $sclient->title . '</option>';
			}
			$selc .= '</select>';

			$selc .= '<script>
			document.querySelector("#choosaclient").addEventListener("change", function() {
				window.location.href = "' . home_url() . '/times?id=" + this.value;
			});
		</script>';
	}
}


?>
<div data-times_id="<?php echo $times_id; ?>" data-client_id="<?php echo $client->id; ?>" data-nonce="<?php echo $nonce; ?>" id="timespage" style="--timescolor:<?php echo $color; ?>">
	<header id="timesheader">
		<div class="container flex">
			<div class="clientsel">
				<?php echo $selc; ?>
			</div>
			<div>
				<i class="bx bx-time"></i>  Zeiten
			</div>
			<div class="logout">
				<a href="<?php echo home_url(); ?>/logout"><i class='bx bx-log-out-circle'></i></a>
			</div>
		</div>
	</header>
	
		<div id="timescontent">
			<?php if (!$hidepass) { ?>
			<form id="passform" method="post">
				<input data-1p-ignore="true" data-lpignore="true" required type="password" name="password" placeholder="Passwort">
				<input type="submit" name="submit" value="Abschicken">
				<?php if (isset($error)) { ?>
					<div class="wrong"><?php echo $error; ?></div>
				<?php } ?>
			</form>
			<?php } else {
				?>
				<div id="loadadmintimessuhau28129"></div>
			<?php
			} ?>
		</div>
	<?php
		// start date get current month and first day of month
		$startdate_standard = date('Y-m-01');

		// end date get current month and last day of month
		$enddate_standard = date('Y-m-t');

		// check for cookies, if there set as standard
		// datepicker_start and datepicker_end
		/* setCookieTallyr('datepicker_start', input_date_start.val(), 365);
		setCookieTallyr('datepicker_end', input_date_end.val(), 365); */

		$datepicker_start_cookie = isset($_COOKIE['datepicker_start']) ? $_COOKIE['datepicker_start'] : '';
		$datepicker_end_cookie = isset($_COOKIE['datepicker_end']) ? $_COOKIE['datepicker_end'] : '';

		// set as standard if cookie is set
		if ($datepicker_start_cookie) {
			$startdate_standard = $datepicker_start_cookie;
		}
		if ($datepicker_end_cookie) {
			$enddate_standard = $datepicker_end_cookie;
		}

		?>

		<div data-times_id="<?php echo $times_id; ?>" data-client_id="<?php echo $client->id; ?>" data-nonce="<?php echo $nonce; ?>" id="times_datepicker" style="display:none">
			<div class="container flex">
				<div class="datepicker">
					Vom <input type="date" id="datepicker_start" name="datepicker_start" value="<?php echo $startdate_standard ; ?>">
					bis <input type="date" id="datepicker_end" name="datepicker_end" value="<?php echo $enddate_standard ; ?>">
				</div>
				<div class="export-buttons">
					<button type="button" id="export-detailed" title="Detaillierte Zeiteinträge exportieren"><i class='bx bx-spreadsheet'></i> Detail-Export</button>
					<button type="button" id="export-cumulated" title="Kumulierte Übersicht exportieren"><i class='bx bx-pie-chart-alt-2'></i> Kumuliert</button>
				</div>
				<div id="monthpickerwrap">
					<button id="monthpicker">Monat auswählen</button>
					<div id="coolpick" style="display:none">
						<div id="yearstopick">
							<?php // all years from 2024 to current year
							$year = date('Y');
							for ($i = 2024; $i <= $year; $i++) { 
								// mark current year as active
								if ($i == $year) {
									//$active = ' active';
								} else {
									$active = '';
								}
								?>
								<button class="year<?php echo $active; ?>" data-year="<?php echo $i; ?>"><?php echo $i; ?></button>
							<?php } ?>
						</div>
						<div id="monthstopick">
							<?php 
							// Set the locale to German
							setlocale(LC_TIME, 'de_DE.UTF-8');

							// All months, mark the current month as active, echo German month names
							$month = date('m');
							for ($i = 1; $i <= 12; $i++) { 
								if ($i == $month) {
									//$active = ' active';
								} else {
									$active = '';
								}
								?>
								<button class="month<?php echo $active; ?>" data-month="<?php echo sprintf('%02d', $i); ?>">
									<?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $i, 1))); ?>
								</button>
							<?php } ?>
						</div>

					</div>
				</div>		
			</div>
		</div>

		<div class="container clear">
			<div id="projects_and_times" class="clear"></div>
		</div>

</div>

<?php // echo Version CHILD_THEME_VERSION
echo '<div id="tvers">Version: ' . CHILD_THEME_VERSION . '</div>';
?>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<?php
get_footer(); ?>