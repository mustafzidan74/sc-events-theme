<?php
/**
 * Session edit — the shared session form for one session.
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
$session = isset($_GET['id']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_sessions WHERE id = %d", absint($_GET['id']))) : null;
if (!$session) {
    wp_safe_redirect(home_url('/event-manager-dashboard/sessions'));
    exit;
}
$preselect_event_id = 0;

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/session-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
