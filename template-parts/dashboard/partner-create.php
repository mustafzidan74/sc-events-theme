<?php
/**
 * Partner create — the shared brand form (partials/brand-form.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$brand_type = 'partner';
$brand = null;

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/brand-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
