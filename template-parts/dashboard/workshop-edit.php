<?php
/**
 * Workshop edit — the shared workshop form with the workshop loaded.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$workshop_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$workshop = $workshop_id ? SC_Workshop::get($workshop_id) : null;
if (!$workshop) {
    wp_safe_redirect(home_url('/event-manager-dashboard/workshops'));
    exit;
}

require __DIR__ . '/partials/workshop-form-data.php';

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/workshop-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
