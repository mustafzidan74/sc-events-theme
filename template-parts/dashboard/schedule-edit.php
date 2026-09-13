<?php
/**
 * schedule-edit — kept as an address for old links and bookmarks. Items are now
 * added and edited in a dialog on the schedules page.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb;
$item_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$event_id = $item_id ? (int) $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}sc_schedules WHERE id = %d", $item_id)) : 0;
wp_safe_redirect(add_query_arg(array_filter(array('event_id' => $event_id, 'edit' => $item_id)), home_url('/event-manager-dashboard/schedules')));
exit;
