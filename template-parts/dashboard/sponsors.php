<?php
/**
 * Sponsors — the shared brand list (partials/brand-list.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list;
$load_wd_list = true;
$brand_type = 'sponsor';

include __DIR__ . '/partials/brand-list.php';
