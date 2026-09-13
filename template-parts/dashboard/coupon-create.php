<?php
/**
 * Coupon create — one coupon (partials/coupon-form.php), or a batch of codes
 * with ?mode=generate (partials/coupon-generate.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$coupon_post = null;

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

if (isset($_GET['mode']) && $_GET['mode'] === 'generate') {
    include __DIR__ . '/partials/coupon-generate.php';
} else {
    include __DIR__ . '/partials/coupon-form.php';
}

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
