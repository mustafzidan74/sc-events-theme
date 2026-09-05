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
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'event_manager_login')) {
        wp_send_json_error(array('message' => __('Security check failed. Please refresh and try again.', 'sc_events')));
    }

    $user_login = sanitize_text_field($_POST['user_login']);

    // Check if the username belongs to an admin/event_manager user
    $target_user = get_user_by('login', $user_login);
    if (!$target_user) {
        $target_user = get_user_by('email', $user_login);
    }

    $is_admin_user = false;
    if ($target_user) {
        $admin_roles = array('administrator', 'event_manager');
        $is_admin_user = !empty(array_intersect($admin_roles, $target_user->roles));
    }

    // Check brute force lockout - only for admin users
    if ($is_admin_user && function_exists('sc_is_ip_locked_out')) {
        $lockout = sc_is_ip_locked_out();
        if ($lockout) {
            wp_send_json_error(array(
                'message' => sprintf(__('Too many failed login attempts. Please try again in %d minutes.', 'sc_events'), ceil($lockout['remaining'] / 60)),
                'locked_out' => true,
                'retry_after' => $lockout['remaining']
            ));
        }
    }
    $user_password = $_POST['user_password'];
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === 'yes';

    $credentials = array(
        'user_login' => $user_login,
        'user_password' => $user_password,
        'remember' => $remember_me
    );

    $user = wp_signon($credentials, is_ssl());

    if (is_wp_error($user)) {
        // Only record failed login attempts for admin users
        if ($is_admin_user && function_exists('sc_record_failed_login')) {
            $result = sc_record_failed_login(null, $user_login);

            if (isset($result['locked']) && $result['locked']) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Too many failed login attempts. Please try again in %d minutes.', 'sc_events'), ceil($result['duration'] / 60)),
                    'locked_out' => true,
                    'retry_after' => $result['duration']
                ));
            }

            if (isset($result['remaining_attempts']) && $result['remaining_attempts'] <= 2) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Invalid username or password. %d attempts remaining.', 'sc_events'), $result['remaining_attempts'])
                ));
            }
        }

        wp_send_json_error(array('message' => __('Invalid username or password.', 'sc_events')));
    }

    $is_event_manager = in_array('event_manager', $user->roles) || in_array('administrator', $user->roles);
    $is_event_scanner = in_array('event_scanner', $user->roles);

    if (!$is_event_manager && !$is_event_scanner) {
        wp_logout();
        wp_send_json_error(array('message' => __('You do not have permission to access the Event Manager Dashboard.', 'sc_events')));
    }

    if (function_exists('sc_record_successful_login')) {
        sc_record_successful_login();
    }

    $redirect_url = $is_event_scanner
        ? home_url('/event-manager-dashboard/scanner')
        : home_url('/event-manager-dashboard/home');

    wp_send_json_success(array(
        'message' => __('Login successful! Redirecting...', 'sc_events'),
        'redirect' => $redirect_url
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
