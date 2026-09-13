<?php
/**
 * schedule-create — kept as an address for old links and bookmarks. Items are now
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

$args = array('add' => 1);
if (!empty($_GET['event_id'])) {
    $args['event_id'] = absint($_GET['event_id']);
}
wp_safe_redirect(add_query_arg($args, home_url('/event-manager-dashboard/schedules')));
exit;
