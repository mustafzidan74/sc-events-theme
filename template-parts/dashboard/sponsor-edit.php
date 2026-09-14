<?php
/**
 * Sponsor edit — the shared brand form (partials/brand-form.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$brand_type = 'sponsor';
global $wpdb;
$brand = isset($_GET['id']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_sponsors WHERE id = %d", absint($_GET['id']))) : null;
if (!$brand) {
    wp_safe_redirect(home_url('/event-manager-dashboard/sponsors'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/brand-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
