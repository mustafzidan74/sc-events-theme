<?php
/**
 * Signing out on the public site.
 *
 * The links used to point at wp-login.php?action=logout, so people landed on a WordPress screen —
 * either its "Do you really want to log out?" page when the link's nonce had gone stale, or
 * wp-login.php?loggedout=true. sc_logout_url() keeps them on the site: /?sc_logout=1 signs them out
 * and returns them where they were. Anything else that still logs out through WordPress (the
 * toolbar, a bookmark) lands on the site too, through the logout_redirect filter.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sign-out link for the public site.
 *
 * @param string $redirect Where to land afterwards. Defaults to the home page.
 */
function sc_logout_url($redirect = '') {
    $redirect = $redirect ?: home_url('/');
    return wp_nonce_url(add_query_arg(array('sc_logout' => 1, 'redirect_to' => rawurlencode($redirect)), home_url('/')), 'sc_logout', 'sc_nonce');
}

add_action('init', function () {
    if (!isset($_GET['sc_logout'])) {
        return;
    }
    $redirect = isset($_GET['redirect_to']) ? rawurldecode(wp_unslash($_GET['redirect_to'])) : home_url('/');
    $redirect = wp_validate_redirect(esc_url_raw($redirect), home_url('/'));

    // A stale link should still sign the person out — being signed out is the worst it can do — but
    // a link from another site must not carry them into the dashboard afterwards.
    if (is_user_logged_in()) {
        wp_logout();
    }
    wp_safe_redirect($redirect ?: home_url('/'));
    exit;
}, 1);

/** Whatever route was used, come back to the site rather than to wp-login.php. */
add_filter('logout_redirect', function ($redirect_to, $requested, $user) {
    if ($requested) {
        return $requested;
    }
    $is_staff = $user instanceof WP_User && user_can($user, 'edit_posts');
    return $is_staff ? home_url('/event-manager-dashboard/') : home_url('/');
}, 10, 3);
