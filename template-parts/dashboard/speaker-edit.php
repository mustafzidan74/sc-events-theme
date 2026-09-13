<?php
/**
 * Speaker edit — the shared speaker form.
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
$speaker = isset($_GET['id']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_speakers WHERE id = %d", absint($_GET['id']))) : null;
if (!$speaker) {
    wp_safe_redirect(home_url('/event-manager-dashboard/speakers'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/speaker-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
