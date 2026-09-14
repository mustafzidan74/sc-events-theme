<?php
/**
 * Company scanner — company badges are checked in by the attendance scanner.
 *
 * The separate company scanner only worked for event managers (scanner accounts
 * were redirected away and its handlers refused them). Old links land here.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
wp_safe_redirect(home_url('/event-manager-dashboard/scanner' . ($event_id ? '?event_id=' . $event_id : '')));
exit;
