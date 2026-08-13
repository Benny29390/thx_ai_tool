<?php
/**
 * Projektplanner Full JSON Export — ALL users, ALL data
 *
 * URL: https://example.com/?tallyr_export=projektplanner&key=YOUR_SECRET_KEY
 *
 * Returns complete JSON with all users, plans, rows, clients, budgets,
 * shares, feedback, revisions (incl. snapshots), person shares, team tags,
 * WP users, times, projects — everything.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'tallyr_projektplanner_export_handler' );

function tallyr_projektplanner_export_handler() {
	if ( ! isset( $_GET['tallyr_export'] ) || $_GET['tallyr_export'] !== 'projektplanner' ) {
		return;
	}

	$secret_key = 'tllr-exp-2025-xK9mPqW3vL7n';
	if ( ! isset( $_GET['key'] ) || $_GET['key'] !== $secret_key ) {
		status_header( 403 );
		echo json_encode( array( 'error' => 'Unauthorized.' ) );
		exit;
	}

	global $wpdb;
	$prefix = $wpdb->prefix;

	// Table names
	$t = array(
		'clients'        => $prefix . 'tallyr_clients',
		'projects'       => $prefix . 'tallyr_projects',
		'times'          => $prefix . 'tallyr_times',
		'plans'          => $prefix . 'tallyr_projektplanner',
		'rows'           => $prefix . 'tallyr_projektplanner_rows',
		'shares'         => $prefix . 'tallyr_projektplanner_shares',
		'revisions'      => $prefix . 'tallyr_projektplanner_revisions',
		'feedback'       => $prefix . 'tallyr_projektplanner_feedback',
		'plan_budget'    => $prefix . 'tallyr_projektplanner_budget',
		'client_budget'  => $prefix . 'tallyr_client_budget',
		'person_shares'  => $prefix . 'tallyr_person_shares',
	);

	// ─── WordPress Users ───
	$wp_users = array();
	$users = get_users( array( 'fields' => array( 'ID', 'user_login', 'user_email', 'display_name' ) ) );
	foreach ( $users as $u ) {
		$uid = (int) $u->ID;
		$tags = get_user_meta( $uid, 'tallyr_pp_tags', true );
		if ( is_string( $tags ) && ! empty( $tags ) ) {
			$tags = json_decode( $tags, true );
		}
		if ( ! is_array( $tags ) ) $tags = array();

		$colwidths = get_user_meta( $uid, 'tallyr_pp_colwidths', true );
		if ( is_string( $colwidths ) && ! empty( $colwidths ) ) {
			$decoded = json_decode( $colwidths, true );
			if ( is_array( $decoded ) ) $colwidths = $decoded;
		}

		$wp_users[] = array(
			'id'           => $uid,
			'user_login'   => $u->user_login,
			'user_email'   => $u->user_email,
			'display_name' => $u->display_name,
			'meta'         => array(
				'kuerzel'    => get_user_meta( $uid, 'tallyr_kuerzel', true ),
				'zeit'       => get_user_meta( $uid, 'tallyr_zeit', true ),
				'capacity'   => get_user_meta( $uid, 'tallyr_capacity', true ),
				'fontsize'   => get_user_meta( $uid, 'tallyr_pp_fontsize', true ),
				'colwidths'  => $colwidths,
				'team_tags'  => $tags,
			),
		);
	}

	// ─── Clients (ALL, all states — Plans reference them) ───
	$clients = $wpdb->get_results( "SELECT * FROM {$t['clients']} ORDER BY id", ARRAY_A );

	// ─── Plans (ALL users, ALL states) ───
	$plans = $wpdb->get_results( "SELECT * FROM {$t['plans']} ORDER BY id", ARRAY_A );
	$plan_ids = array_column( $plans, 'id' );

	// ─── Rows ───
	$rows_all = $wpdb->get_results( "SELECT * FROM {$t['rows']} ORDER BY plan_id, position", ARRAY_A );
	$rows_by_plan = array();
	foreach ( $rows_all as $row ) {
		$rows_by_plan[ $row['plan_id'] ][] = $row;
	}

	// ─── Shares ───
	$shares_all = $wpdb->get_results(
		"SELECT s.*, u.display_name FROM {$t['shares']} s
		 LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
		 ORDER BY s.plan_id",
		ARRAY_A
	);
	$shares_by_plan = array();
	foreach ( $shares_all as $s ) {
		$shares_by_plan[ $s['plan_id'] ][] = $s;
	}

	// ─── Feedback ───
	$feedback_all = $wpdb->get_results( "SELECT * FROM {$t['feedback']} ORDER BY plan_id, created", ARRAY_A );
	$feedback_by_plan = array();
	foreach ( $feedback_all as $fb ) {
		$feedback_by_plan[ $fb['plan_id'] ][] = $fb;
	}

	// ─── Revisions (WITH snapshots) ───
	$revisions_all = $wpdb->get_results( "SELECT * FROM {$t['revisions']} ORDER BY plan_id, created DESC", ARRAY_A );
	$revisions_by_plan = array();
	foreach ( $revisions_all as $rev ) {
		$revisions_by_plan[ $rev['plan_id'] ][] = $rev;
	}

	// ─── Plan budget overrides ───
	$plan_budgets_all = $wpdb->get_results( "SELECT * FROM {$t['plan_budget']} ORDER BY plan_id, year, month", ARRAY_A );
	$budget_by_plan = array();
	foreach ( $plan_budgets_all as $b ) {
		$budget_by_plan[ $b['plan_id'] ][] = $b;
	}

	// ─── Client budgets ───
	$client_budgets = $wpdb->get_results( "SELECT * FROM {$t['client_budget']} ORDER BY client_id, year, month", ARRAY_A );

	// ─── Person shares ───
	$person_shares = $wpdb->get_results( "SELECT * FROM {$t['person_shares']} ORDER BY id", ARRAY_A );


	// ─── Assemble plans with nested data ───
	$plans_export = array();
	foreach ( $plans as $plan ) {
		$pid = $plan['id'];
		$plans_export[] = array(
			'plan'             => $plan,
			'rows'             => isset( $rows_by_plan[ $pid ] ) ? $rows_by_plan[ $pid ] : array(),
			'shares'           => isset( $shares_by_plan[ $pid ] ) ? $shares_by_plan[ $pid ] : array(),
			'feedback'         => isset( $feedback_by_plan[ $pid ] ) ? $feedback_by_plan[ $pid ] : array(),
			'revisions'        => isset( $revisions_by_plan[ $pid ] ) ? $revisions_by_plan[ $pid ] : array(),
			'budget_overrides' => isset( $budget_by_plan[ $pid ] ) ? $budget_by_plan[ $pid ] : array(),
		);
	}

	// ─── Summary ───
	$total_items = 0;
	$total_sections = 0;
	$total_done = 0;
	foreach ( $rows_all as $r ) {
		if ( $r['type'] === 'item' ) {
			$total_items++;
			if ( (int) $r['is_done'] === 1 ) $total_done++;
		}
		if ( $r['type'] === 'section' ) $total_sections++;
	}

	$export = array(
		'export_version' => '1.0',
		'exported_at'    => current_time( 'c' ),
		'hours_per_ts'   => 8,
		'summary'        => array(
			'total_wp_users'  => count( $wp_users ),
			'total_clients'   => count( $clients ),
			'total_plans'     => count( $plans ),
			'total_rows'      => count( $rows_all ),
			'total_items'     => $total_items,
			'total_sections'  => $total_sections,
			'total_done'      => $total_done,
			'total_feedback'  => count( $feedback_all ),
			'total_revisions' => count( $revisions_all ),
		),
		'wp_users'              => $wp_users,
		'clients'               => $clients,
		'client_budgets'        => $client_budgets,
		'person_shares'         => $person_shares,
		'plans'                 => $plans_export,
	);

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	echo json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}
