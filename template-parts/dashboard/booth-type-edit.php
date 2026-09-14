<?php
/**
 * Booth type edit — the shared booth type form.
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
$booth_type = isset($_GET['id']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_booth_types WHERE id = %d", absint($_GET['id']))) : null;
if (!$booth_type) {
    wp_safe_redirect(home_url('/event-manager-dashboard/booth-types'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/booth-type-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
