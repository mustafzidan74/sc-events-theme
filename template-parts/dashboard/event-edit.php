<?php
/**
 * Event edit — the shared event form, filled in.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$event_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$event = $event_id && class_exists('SC_Event') ? SC_Event::get($event_id) : null;

if (!$event) {
    wp_safe_redirect(home_url('/event-manager-dashboard/events'));
    exit;
}

require __DIR__ . '/partials/event-form-data.php';

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/event-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
