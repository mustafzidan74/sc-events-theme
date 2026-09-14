<?php
/**
 * Booth booking edit — the shared booking form.
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
$booking = isset($_GET['id']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sc_booth_bookings WHERE id = %d", absint($_GET['id']))) : null;
if (!$booking) {
    wp_safe_redirect(home_url('/event-manager-dashboard/booth-bookings'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/booth-booking-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
