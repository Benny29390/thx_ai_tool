<?php
/**
 * Public (nopriv) AJAX endpoints for Projektplan feedback + person tasks.
 * Loaded outside is_user_logged_in() so they work for non-logged-in visitors.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Submit feedback (like/dislike/comment)
if ( ! function_exists('uf_pp_submit_feedback') ) {
	add_action('wp_ajax_nopriv_uf_pp_submit_feedback', 'uf_pp_submit_feedback');
	add_action('wp_ajax_uf_pp_submit_feedback', 'uf_pp_submit_feedback');
	function uf_pp_submit_feedback() {
		$share_hash = sanitize_text_field($_POST['share_hash']);
		$row_id = (int)$_POST['row_id'];
		$author = sanitize_text_field(wp_unslash($_POST['author_name']));
		$type = sanitize_text_field($_POST['feedback_type']);
		$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

		if (!in_array($type, array('like', 'dislike', 'comment'))) $type = 'comment';

		global $wpdb;
		$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
		$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';

		$plan = $wpdb->get_row($wpdb->prepare("SELECT id FROM $plans_table WHERE share_hash = %s AND state = 1 AND share_hash != ''", $share_hash));
		if (!$plan) { wp_send_json_error('Plan nicht gefunden.'); exit(); }

		$wpdb->insert($feedback_table, array(
			'plan_id' => $plan->id,
			'row_id' => $row_id,
			'author_name' => $author ?: 'Anonym',
			'feedback_type' => $type,
			'message' => $message,
		));

		wp_send_json_success();
		exit();
	}
}

// Delete feedback (public, via share hash)
if ( ! function_exists('uf_pp_delete_feedback_public') ) {
	add_action('wp_ajax_nopriv_uf_pp_delete_feedback_public', 'uf_pp_delete_feedback_public');
	add_action('wp_ajax_uf_pp_delete_feedback_public', 'uf_pp_delete_feedback_public');
	function uf_pp_delete_feedback_public() {
		$feedback_id = (int)$_POST['feedback_id'];
		$share_hash = sanitize_text_field($_POST['share_hash']);
		global $wpdb;
		$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
		$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
		$fb = $wpdb->get_row($wpdb->prepare("SELECT f.*, p.share_hash FROM $feedback_table f JOIN $plans_table p ON p.id = f.plan_id WHERE f.id = %d", $feedback_id));
		if (!$fb || $fb->share_hash !== $share_hash) { wp_send_json_error('Nicht gefunden.'); exit(); }
		$wpdb->delete($feedback_table, array('id' => $feedback_id));
		wp_send_json_success();
		exit();
	}
}

// Edit feedback (public, via share hash)
if ( ! function_exists('uf_pp_edit_feedback_public') ) {
	add_action('wp_ajax_nopriv_uf_pp_edit_feedback_public', 'uf_pp_edit_feedback_public');
	add_action('wp_ajax_uf_pp_edit_feedback_public', 'uf_pp_edit_feedback_public');
	function uf_pp_edit_feedback_public() {
		$feedback_id = (int)$_POST['feedback_id'];
		$share_hash = sanitize_text_field($_POST['share_hash']);
		$message = sanitize_textarea_field(wp_unslash($_POST['message']));
		global $wpdb;
		$feedback_table = $wpdb->prefix . 'tallyr_projektplanner_feedback';
		$plans_table = $wpdb->prefix . 'tallyr_projektplanner';
		$fb = $wpdb->get_row($wpdb->prepare("SELECT f.*, p.share_hash FROM $feedback_table f JOIN $plans_table p ON p.id = f.plan_id WHERE f.id = %d", $feedback_id));
		if (!$fb || $fb->share_hash !== $share_hash) { wp_send_json_error('Nicht gefunden.'); exit(); }
		$wpdb->update($feedback_table, array('message' => $message), array('id' => $feedback_id));
		wp_send_json_success();
		exit();
	}
}
