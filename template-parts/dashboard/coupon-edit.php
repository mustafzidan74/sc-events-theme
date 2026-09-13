<?php
/**
 * Coupon edit — the shared coupon form for one sc_coupon post.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$coupon_post = isset($_GET['id']) ? get_post(absint($_GET['id'])) : null;
if (!$coupon_post || $coupon_post->post_type !== 'sc_coupon') {
    wp_safe_redirect(home_url('/event-manager-dashboard/coupons'));
    exit;
}

global $load_wd_form;
$load_wd_form = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

include __DIR__ . '/partials/coupon-form.php';

get_template_part('template-parts/dashboard/components/dashboard', 'footer');
