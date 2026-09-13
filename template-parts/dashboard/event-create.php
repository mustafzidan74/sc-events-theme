<?php
/**
 * Event create — the shared event form, empty. Saving continues on the edit page.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$event = null;

require __DIR__ . '/partials/event-form-data.php';

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/event-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
