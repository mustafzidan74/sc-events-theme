<?php
/**
 * Authentication AJAX Handlers
 *
 * Login, Logout, and authentication-related handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Event Manager Login
 */
add_action('wp_ajax_nopriv_event_manager_login', 'sc_event_manager_login_handler');
function sc_event_manager_login_handler() {
    $locked_message = function ($seconds) {
        return sprintf(__('Too many failed sign-in attempts. Please try again in %d minutes.', 'sc_events'), max(1, ceil($seconds / 60)));
    };

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'event_manager_login')) {
        wp_send_json_error(array('message' => __('This page has expired. Refresh it and sign in again.', 'sc_events')));
    }

    $user_login = isset($_POST['user_login']) ? trim(sanitize_text_field(wp_unslash($_POST['user_login']))) : '';
    // Passwords are passed on as sent, the way wp-login.php does.
    $user_password = isset($_POST['user_password']) ? (string) $_POST['user_password'] : '';
    if ($user_login === '' || $user_password === '') {
        wp_send_json_error(array('message' => __('Enter your email or username and your password.', 'sc_events')));
    }

    // Lockout is per account (sc_login_lock_key), so staff sharing the venue's Wi-Fi don't lock
    // each other out. Unknown usernames are counted the same way, so the reply doesn't reveal
    // which ones exist. Failed attempts are counted once, by the wp_login_failed hook in
    // security-utilities.php.
    $lock_key = sc_login_lock_key($user_login);
    $lockout = sc_is_ip_locked_out($lock_key);
    if ($lockout) {
        wp_send_json_error(array('message' => $locked_message($lockout['remaining']), 'locked_out' => true, 'retry_after' => $lockout['remaining']));
    }

    $user = wp_signon(array(
        'user_login'    => $user_login,
        'user_password' => $user_password,
        'remember'      => isset($_POST['remember_me']) && $_POST['remember_me'] === 'yes',
    ), is_ssl());

    if (is_wp_error($user)) {
        $lockout = sc_is_ip_locked_out($lock_key);
        if ($lockout) {
            wp_send_json_error(array('message' => $locked_message($lockout['remaining']), 'locked_out' => true, 'retry_after' => $lockout['remaining']));
        }
        $config = sc_get_brute_force_config();
        $attempts = get_transient('sc_attempts_' . md5($lock_key));
        $left = $config['max_attempts'] - (is_array($attempts) ? (int) $attempts['count'] : 0);
        $message = __('Wrong email, username or password.', 'sc_events');
        if ($left > 0 && $left <= 2) {
            $message .= ' ' . sprintf(_n('%d attempt left before sign-in is paused.', '%d attempts left before sign-in is paused.', $left, 'sc_events'), $left);
        }
        wp_send_json_error(array('message' => $message));
    }

    $is_event_manager = in_array('event_manager', $user->roles, true) || in_array('administrator', $user->roles, true);
    $is_event_scanner = in_array('event_scanner', $user->roles, true);

    if (!$is_event_manager && !$is_event_scanner) {
        wp_logout();
        wp_send_json_error(array('message' => __('This account has no dashboard access. The dashboard is for event managers and scanner staff.', 'sc_events')));
    }

    wp_send_json_success(array(
        'redirect' => home_url($is_event_manager ? '/event-manager-dashboard/home' : '/event-manager-dashboard/scanner'),
    ));
}

/**
 * Handle Event Manager Logout
 */
add_action('wp_ajax_event_manager_logout', 'sc_event_manager_logout_handler');
function sc_event_manager_logout_handler() {
    $nonce_valid = false;

    if (isset($_POST['nonce'])) {
        if (wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce') ||
            wp_verify_nonce($_POST['nonce'], 'event_manager_logout')) {
            $nonce_valid = true;
        }
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    wp_clear_auth_cookie();
    wp_logout();

    wp_send_json_success(array(
        'message' => __('Logged out successfully.', 'sc_events'),
        'redirect' => home_url('/event-manager-dashboard/')
    ));
}
