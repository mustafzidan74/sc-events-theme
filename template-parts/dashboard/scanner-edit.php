<?php
/**
 * Scanner edit — the shared scanner form for one scanner account.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$scanner = isset($_GET['id']) ? get_user_by('ID', absint($_GET['id'])) : false;
if (!$scanner || !in_array('event_scanner', (array) $scanner->roles, true)) {
    wp_safe_redirect(home_url('/event-manager-dashboard/scanners'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/scanner-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
