<?php
/**
 * Attendee edit — the shared attendee form for one registration.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$attendee = isset($_GET['id']) && class_exists('SC_Attendee') ? SC_Attendee::get(absint($_GET['id'])) : null;
if (!$attendee) {
    wp_safe_redirect(home_url('/event-manager-dashboard/attendees'));
    exit;
}
$preselect_event_id = 0;

global $load_wd_form, $load_wd_overview;
$load_wd_form = true;
$load_wd_overview = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/attendee-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
