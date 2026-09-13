<?php
/**
 * Session create — the shared session form, empty.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$session = null;
$preselect_event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/session-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
