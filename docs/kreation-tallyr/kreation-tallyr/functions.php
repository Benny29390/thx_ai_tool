<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


define( 'CHILD_THEME_VERSION', 6.7 );
define( 'CHILD_THEME_PATH', get_stylesheet_directory() );
define( 'CHILD_THEME_URL', get_stylesheet_directory_uri() );

// add Version number to body
function add_version_number() {
	echo '<div id="tversion">Version '.CHILD_THEME_VERSION.'</div>';
}
// add to footer
add_action('wp_footer', 'add_version_number');


/**
 *  Helper Functions
 */
/* require_once CHILD_THEME_PATH . '/inc/helper.php'; */

/**
 *  ACF Blocks
 */
$includeurl = CHILD_THEME_PATH . '/acfblocks/';
foreach (glob($includeurl."/*.php") as $filename) { 
	include $filename; 
}

/**
 * Enqueue scripts and styles.
 */
function child_scripts() {
	
	// Styles
	wp_enqueue_style( 'style_child', get_stylesheet_uri(), false, CHILD_THEME_VERSION );

	// Scripts
	wp_enqueue_script( 'scripts-footer_child', CHILD_THEME_URL . '/js/min/scripts-footer-min.js', array("jquery"), CHILD_THEME_VERSION, true );

	if (is_user_logged_in()) {
		wp_enqueue_script( 'userfunctions', CHILD_THEME_URL . '/js/users/userfunctions-min.js', array("jquery"), CHILD_THEME_VERSION, true );

		wp_localize_script( 'userfunctions', 'ajaxuser', array(
			'nonce' => wp_create_nonce('dhAu21hdaZA721!naj!hdf'),
			'url'       => admin_url( 'admin-ajax.php' ),
		)
	);
	}

}
add_action( 'wp_enqueue_scripts', 'child_scripts' );

function neuro_styles() {
	wp_enqueue_style( 'style_child', get_stylesheet_uri(), false, CHILD_THEME_VERSION );
}
add_action( 'enqueue_block_editor_assets', 'neuro_styles' );

/**
 *  Ajax
 */
if (is_user_logged_in()) {
	require_once CHILD_THEME_PATH . '/inc/asana-api.php';
	require_once CHILD_THEME_PATH . '/inc/ajaxuser.php';
}

require_once CHILD_THEME_PATH . '/inc/ajaxall.php';
require_once CHILD_THEME_PATH . '/inc/ajax-public-feedback.php';
require_once CHILD_THEME_PATH . '/inc/export-projektplanner.php';

/**
 *  Monitor Cron
 */
require_once CHILD_THEME_PATH . '/inc/monitor-cron.php';

/**
 *  SMV Files
 */
/* require_once CHILD_THEME_PATH . '/inc/files.php';
 */
/**
 *  CPTS
 */
/* $includeurl = CHILD_THEME_PATH . '/cpts/';
foreach (glob($includeurl."/*.php") as $filename) { 
    include $filename; 
} */

// change title tag if page id is 17
function change_title_tag($title) {
	if (is_page(17)) {
		global $wpdb;
		$times_id = (STRING)$_GET['id'];
		$client = $wpdb->get_row("SELECT * FROM wpt_tallyr_clients WHERE randomlink = '$times_id'");
		$userid = $client->userid;
		$kuerzel = '';

		if ($userid) {
			$kuerzel = get_user_meta($userid, 'tallyr_kuerzel', true);
		}
		$selc = $client->title;

		$title = $kuerzel.''.' » '.$selc;
	}
	return $title;
}
add_filter('pre_get_document_title', 'change_title_tag');
