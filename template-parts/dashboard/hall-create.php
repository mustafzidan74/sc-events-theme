<?php
/**
 * hall-create — kept as an address for old links and bookmarks. Items are now
 * added and edited in a dialog on the halls page.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

wp_safe_redirect(add_query_arg('add', 1, home_url('/event-manager-dashboard/halls')));
exit;
