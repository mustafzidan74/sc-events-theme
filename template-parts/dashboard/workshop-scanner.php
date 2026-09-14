<?php
/**
 * Workshop scanner — the main scanner, opened on this workshop.
 *
 * Scanning moved into scanner.php ("Scanning at" picker), so staff use one tool
 * for the entrance and every workshop door. Old links land here.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb;
$workshop_id = isset($_GET['workshop_id']) ? absint($_GET['workshop_id']) : 0;
$event_id = $workshop_id ? (int) $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}sc_workshops WHERE id = %d", $workshop_id)) : 0;

wp_safe_redirect(home_url('/event-manager-dashboard/scanner' . ($event_id ? '?event_id=' . $event_id . '&workshop_id=' . $workshop_id : '')));
exit;
