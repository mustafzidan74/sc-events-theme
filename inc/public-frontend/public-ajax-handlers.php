<?php
/**
 * Public Frontend AJAX Handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verify public nonce
 */
function sc_verify_public_nonce() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_public_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed. Please refresh the page.', 'sc_events')));
        exit;
    }
}

/**
 * Rate Limiting for authentication - Brute Force Protection
 * Limits login attempts to prevent password guessing attacks
 *
 * @param string $identifier Email or IP address
 * @param string $type Type of rate limit ('login', 'register', 'otp')
 * @return bool|array True if allowed, array with error if blocked
 */
function sc_check_auth_rate_limit($identifier, $type = 'login') {
    $identifier_hash = md5($identifier . $type);
    $transient_key = 'sc_rate_' . $identifier_hash;
    $lockout_key = 'sc_lockout_' . $identifier_hash;

    // Check if currently locked out
    $lockout = get_transient($lockout_key);
    if ($lockout !== false) {
        $remaining = $lockout - time();
        if ($remaining > 0) {
            return [
                'blocked' => true,
                'message' => sprintf(
                    __('Too many attempts. Please try again in %d minutes.', 'sc_events'),
                    ceil($remaining / 60)
                )
            ];
        }
    }

    // Get current attempts
    $attempts = get_transient($transient_key);
    if ($attempts === false) {
        $attempts = 0;
    }

    // Rate limit settings per type
    $limits = [
        'login' => ['max' => 5, 'window' => 900, 'lockout' => 1800],      // 5 attempts per 15 min, 30 min lockout
        'register' => ['max' => 3, 'window' => 3600, 'lockout' => 3600], // 3 per hour, 1 hour lockout
        'otp' => ['max' => 5, 'window' => 300, 'lockout' => 900],        // 5 per 5 min, 15 min lockout
        'password_reset' => ['max' => 3, 'window' => 3600, 'lockout' => 3600] // 3 per hour
    ];

    $limit = $limits[$type] ?? $limits['login'];

    if ($attempts >= $limit['max']) {
        // Set lockout
        set_transient($lockout_key, time() + $limit['lockout'], $limit['lockout']);
        delete_transient($transient_key);

        return [
            'blocked' => true,
            'message' => sprintf(
                __('Too many attempts. Please try again in %d minutes.', 'sc_events'),
                ceil($limit['lockout'] / 60)
            )
        ];
    }

    return true;
}

/**
 * Increment rate limit counter
 *
 * @param string $identifier Email or IP address
 * @param string $type Type of rate limit
 */
function sc_increment_auth_rate_limit($identifier, $type = 'login') {
    $identifier_hash = md5($identifier . $type);
    $transient_key = 'sc_rate_' . $identifier_hash;

    $limits = [
        'login' => 900,
        'register' => 3600,
        'otp' => 300,
        'password_reset' => 3600
    ];

    $window = $limits[$type] ?? 900;

    $attempts = get_transient($transient_key);
    if ($attempts === false) {
        $attempts = 0;
    }

    set_transient($transient_key, $attempts + 1, $window);
}

/**
 * Clear rate limit on successful authentication
 *
 * @param string $identifier Email or IP address
 * @param string $type Type of rate limit
 */
function sc_clear_auth_rate_limit($identifier, $type = 'login') {
    $identifier_hash = md5($identifier . $type);
    delete_transient('sc_rate_' . $identifier_hash);
    delete_transient('sc_lockout_' . $identifier_hash);
}

// Note: sc_get_client_ip() is defined in security-utilities.php

/**
 * Login Handler
 */
add_action('wp_ajax_nopriv_sc_public_login', 'sc_public_login_handler');
function sc_public_login_handler() {
    sc_verify_public_nonce();

    $email = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] == '1';

    if (empty($email) || empty($password)) {
        wp_send_json_error(array('message' => __('Please enter email and password.', 'sc_events')));
    }

    // Security: Check rate limit (by email and IP)
    $client_ip = sc_get_client_ip();
    $rate_check_email = sc_check_auth_rate_limit($email, 'login');
    $rate_check_ip = sc_check_auth_rate_limit($client_ip, 'login');

    if (is_array($rate_check_email) && $rate_check_email['blocked']) {
        wp_send_json_error(array('message' => $rate_check_email['message']));
    }
    if (is_array($rate_check_ip) && $rate_check_ip['blocked']) {
        wp_send_json_error(array('message' => $rate_check_ip['message']));
    }

    // Get user by email
    $user = get_user_by('email', $email);

    if (!$user) {
        // Increment rate limit on failed attempt
        sc_increment_auth_rate_limit($email, 'login');
        sc_increment_auth_rate_limit($client_ip, 'login');
        wp_send_json_error(array('message' => __('Invalid email or password.', 'sc_events')));
    }

    // Check password
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        // Increment rate limit on failed attempt
        sc_increment_auth_rate_limit($email, 'login');
        sc_increment_auth_rate_limit($client_ip, 'login');
        wp_send_json_error(array('message' => __('Invalid email or password.', 'sc_events')));
    }

    // Clear rate limit on successful login
    sc_clear_auth_rate_limit($email, 'login');
    sc_clear_auth_rate_limit($client_ip, 'login');

    // Login user
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember, is_ssl());

    $redirect = isset($_POST['redirect']) ? esc_url($_POST['redirect']) : home_url('/');

    // Some imported accounts were given their phone number as the password: ask for a new one.
    if (function_exists('sc_password_is_phone') && sc_password_is_phone($user->ID, $password)) {
        update_user_meta($user->ID, 'sc_must_change_password', 1);
        $redirect = home_url('/my-account/#profile');
    }

    wp_send_json_success(array(
        'message' => __('Login successful! Redirecting...', 'sc_events'),
        'redirect' => $redirect
    ));
}

/**
 * Register Handler
 */
add_action('wp_ajax_nopriv_sc_public_register', 'sc_public_register_handler');
function sc_public_register_handler() {
    sc_verify_public_nonce();

    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');

    // The WhatsApp number, with the country code chosen on the form.
    $phone = function_exists('sc_otp_phone_input') ? sc_otp_phone_input() : '';

    $password = (string) wp_unslash($_POST['password'] ?? '');

    // Security: Check rate limit (by IP for registration)
    $client_ip = sc_get_client_ip();
    $rate_check = sc_check_auth_rate_limit($client_ip, 'register');

    if (is_array($rate_check) && $rate_check['blocked']) {
        wp_send_json_error(array('message' => $rate_check['message']));
    }

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'sc_events')));
    }

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'sc_events')));
    }

    if (strlen($password) < 8) {
        wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'sc_events')));
    }

    if (email_exists($email)) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events')));
    }

    if ($phone === '') {
        wp_send_json_error(array('message' => __('Enter your WhatsApp number: your ticket and codes are sent there.', 'sc_events'), 'field' => 'phone'));
    }

    if (sc_verified_owner_of_phone($phone)) {
        wp_send_json_error(array('message' => __('This number already belongs to an account. Sign in with a WhatsApp code instead.', 'sc_events'), 'field' => 'phone'));
    }

    // Confirm the number with a code first. If WhatsApp can't send codes right now, don't turn
    // people away: create the account with the number marked unconfirmed.
    if (sc_otp_available()) {
        $started = sc_register_start_verification($name, $email, $phone, $password);
        if (is_wp_error($started)) {
            sc_otp_error($started);
        }
        wp_send_json_success($started);
    }

    $user_id = sc_register_create_account($name, $email, $phone, false, $password);
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
    }
    sc_increment_auth_rate_limit($client_ip, 'register');

    wp_send_json_success(array(
        'message' => __('Registration successful! Redirecting...', 'sc_events'),
        'redirect' => home_url('/')
    ));
}

/**
 * Logout Handler
 */
add_action('wp_ajax_sc_public_logout', 'sc_public_logout_handler');
function sc_public_logout_handler() {
    sc_verify_public_nonce();

    wp_logout();

    wp_send_json_success(array(
        'message' => __('Logged out successfully.', 'sc_events'),
        'redirect' => home_url('/')
    ));
}

/**
 * Update Profile Handler
 */
add_action('wp_ajax_sc_update_profile', 'sc_update_profile_handler');
function sc_update_profile_handler() {
    sc_verify_public_nonce();

    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(array('message' => __('Please login to continue.', 'sc_events')));
    }

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = isset($_POST['phone_code']) ? sc_otp_phone_input() : sc_wabot_phone(sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
    $current_password = (string) wp_unslash($_POST['current_password'] ?? '');
    $new_password = (string) wp_unslash($_POST['new_password'] ?? '');

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Name is required.', 'sc_events')));
    }
    if (trim((string) wp_unslash($_POST['phone'] ?? '')) !== '' && $phone === '') {
        wp_send_json_error(array('message' => __('Enter a valid mobile number.', 'sc_events')));
    }
    if (!empty($new_password)) {
        $user = get_user_by('ID', $user_id);
        if (empty($current_password) || !wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => empty($current_password) ? __('Please enter your current password.', 'sc_events') : __('Current password is incorrect.', 'sc_events')));
        }
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => __('Use at least 8 characters for the new password.', 'sc_events')));
        }
        if ($phone !== '' && sc_wabot_phone($new_password) === $phone) {
            wp_send_json_error(array('message' => __('Don’t use your phone number as the password.', 'sc_events')));
        }
    }

    // A different number is confirmed with a code sent to it before anything is saved.
    $current_phone = sc_wabot_phone(get_user_meta($user_id, 'phone', true));
    $phone_changed = $phone !== '' && $phone !== $current_phone;
    $phone_verified = false;
    if ($phone_changed && sc_otp_available()) {
        if (sc_verified_owner_of_phone($phone, $user_id)) {
            wp_send_json_error(array('message' => __('This number already belongs to another account.', 'sc_events')));
        }
        $code = sanitize_text_field(wp_unslash($_POST['otp_code'] ?? ''));
        if ($code === '') {
            $sent = sc_otp_send('phone', $phone, array('user_id' => $user_id));
            if (is_wp_error($sent)) {
                sc_otp_error($sent);
            }
            wp_send_json_success($sent + array(
                'verify_phone' => true,
                'phone'        => sc_otp_mask_phone($phone),
                'message'      => __('We sent a 6-digit code to the new number on WhatsApp. Enter it to save.', 'sc_events'),
            ));
        }
        $ok = sc_otp_verify('phone', $phone, $code);
        if (is_wp_error($ok) || (int) $ok['user_id'] !== (int) $user_id) {
            sc_otp_error(is_wp_error($ok) ? $ok : new WP_Error('sc_otp_wrong', __('Wrong code.', 'sc_events')));
        }
        $phone_verified = true;
    }

    // Update basic info
    wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name
    ));

    if ($phone_changed) {
        sc_set_user_phone($user_id, $phone, $phone_verified);
    } elseif ($phone === '' && $current_phone !== '') {
        sc_set_user_phone($user_id, '', false);
    }

    // Update password if provided
    if (!empty($new_password)) {
        if (empty($current_password)) {
            wp_send_json_error(array('message' => __('Please enter your current password.', 'sc_events')));
        }

        $user = get_user_by('ID', $user_id);

        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => __('Current password is incorrect.', 'sc_events')));
        }

        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => __('New password must be at least 6 characters.', 'sc_events')));
        }

        wp_set_password($new_password, $user_id);
        delete_user_meta($user_id, 'sc_must_change_password');

        // Re-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        if (function_exists('sc_notify_password_changed')) {
            sc_notify_password_changed($user_id);
        }
    }

    // A new password re-issues the session, so the page's security token must be reloaded.
    wp_send_json_success(array('message' => __('Profile updated successfully.', 'sc_events'), 'reload' => !empty($new_password)));
}

/**
 * Register for Free Ticket
 */
add_action('wp_ajax_sc_register_free_ticket', 'sc_register_free_ticket_handler');
function sc_register_free_ticket_handler() {
    sc_verify_public_nonce();

    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(array('message' => __('Please login to continue.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    $workshop_id = intval($_POST['workshop_id'] ?? 0);
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $extra_fields = isset($_POST['extra_fields']) ? $_POST['extra_fields'] : array();

    // If a workshop_id is provided, derive the parent event_id from it
    if ($workshop_id && class_exists('SC_Workshop')) {
        $workshop = SC_Workshop::get($workshop_id);
        if (!$workshop) {
            wp_send_json_error(array('message' => __('Invalid workshop.', 'sc_events')));
        }
        $event_id = (int) $workshop->event_id;
    }

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'sc_events')));
    }

    // Check if already registered (per workshop if workshop, else per event)
    if ($workshop_id) {
        global $wpdb;
        $att_table = $wpdb->prefix . 'sc_attendees';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$att_table} WHERE workshop_id = %d AND user_id = %d AND status != 'cancelled' LIMIT 1",
            $workshop_id, $user_id
        ));
        if ($exists) {
            wp_send_json_error(array('message' => __('You are already registered for this workshop.', 'sc_events')));
        }
    } else {
        $existing = sc_check_user_registration($user_id, $event_id);
        if ($existing) {
            wp_send_json_error(array('message' => __('You are already registered for this event.', 'sc_events')));
        }
    }

    // Get ticket details
    $ticket_name = 'General';
    $ticket_price = 0;
    if ($ticket_id && class_exists('SC_Ticket')) {
        $ticket = SC_Ticket::get($ticket_id);
        if ($ticket) {
            $ticket_name = $ticket->name;
            $ticket_price = floatval($ticket->price);
        }
    }
    if (!$ticket_id) {
        // Fallback: find first free ticket for the event
        $ticket_details = sc_get_ticket_details($event_id, '');
        $ticket_name = $ticket_details['name'] ?? 'General';
        $ticket_price = $ticket_details['price'] ?? 0;
    }

    // Get user info
    $user = get_user_by('ID', $user_id);
    $phone = get_user_meta($user_id, 'phone', true);

    // Extra fields: pass raw array to sc_create_attendee which handles sanitization
    // JS sends [{label: '...', value: '...'}, ...] format
    if (!is_array($extra_fields)) {
        $extra_fields = array();
    }

    // Create attendee in custom table
    $attendee_data = array(
        'event_id'       => $event_id,
        'ticket_id'      => $ticket_id,
        'ticket_name'    => $ticket_name,
        'user_id'        => $user_id,
        'name'           => $user->display_name,
        'email'          => $user->user_email,
        'phone'          => $phone,
        'payment_method' => 'free',
        'payment_status' => 'success',
        'amount_paid'    => $ticket_price,
        'extra_fields'   => $extra_fields
    );
    if ($workshop_id) {
        $attendee_data['workshop_id'] = $workshop_id;
    }
    $attendee_id = sc_create_attendee($attendee_data);

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Failed to create registration. Please try again.', 'sc_events')));
    }

    // Send confirmation email
    sc_public_send_ticket_email($attendee_id);

    // Get session count for confirmation message
    $session_count = 0;
    if (class_exists('SC_Session_Attendance')) {
        $sess = SC_Session_Attendance::get_attendee_summary($attendee_id, $event_id);
        $session_count = count($sess);
    }

    wp_send_json_success(array(
        'message' => __('Registration successful! Redirecting to your account...', 'sc_events'),
        'redirect' => home_url('/my-account/'),
        'session_count' => $session_count
    ));
}

/**
 * Validate Coupon
 */
add_action('wp_ajax_sc_validate_coupon', 'sc_validate_coupon_handler');
function sc_validate_coupon_handler() {
    sc_verify_public_nonce();

    $event_id = intval($_POST['event_id'] ?? 0);
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');

    if (empty($coupon_code)) {
        wp_send_json_error(array('message' => __('Please enter a coupon code.', 'sc_events')));
    }

    // Find coupon
    $coupon = sc_find_coupon($coupon_code, $event_id);

    if (!$coupon) {
        wp_send_json_error(array('message' => __('Invalid or expired coupon code.', 'sc_events')));
    }

    // Check usage limit (meta keys without underscore prefix - as saved by dashboard)
    $usage_count = intval(get_post_meta($coupon->ID, 'usage_count', true));
    $usage_limit = intval(get_post_meta($coupon->ID, 'usage_limit', true));

    if ($usage_limit > 0 && $usage_count >= $usage_limit) {
        wp_send_json_error(array('message' => __('This coupon has reached its usage limit.', 'sc_events')));
    }

    $discount_type = get_post_meta($coupon->ID, 'discount_type', true);
    $discount_value = floatval(get_post_meta($coupon->ID, 'discount_value', true));

    // Check if it's a 100% discount (free ticket)
    $is_free = ($discount_type === 'percentage' && $discount_value >= 100);

    wp_send_json_success(array(
        'coupon_id' => $coupon->ID,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'discount_text' => $discount_type === 'percentage' ? $discount_value . '%' : $discount_value . ' EGP',
        'is_free' => $is_free
    ));
}

/**
 * Register with Coupon (Free)
 */
add_action('wp_ajax_sc_register_with_coupon', 'sc_register_with_coupon_handler');
function sc_register_with_coupon_handler() {
    sc_verify_public_nonce();

    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(array('message' => __('Please login to continue.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    $workshop_id = intval($_POST['workshop_id'] ?? 0);
    $ticket_id_post = intval($_POST['ticket_id'] ?? 0);
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $extra_fields = isset($_POST['extra_fields']) ? $_POST['extra_fields'] : array();

    if ($workshop_id && class_exists('SC_Workshop')) {
        $workshop = SC_Workshop::get($workshop_id);
        if (!$workshop) {
            wp_send_json_error(array('message' => __('Invalid workshop.', 'sc_events')));
        }
        $event_id = (int) $workshop->event_id;
    }

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'sc_events')));
    }

    if (empty($coupon_code)) {
        wp_send_json_error(array('message' => __('Please enter a coupon or serial number.', 'sc_events')));
    }

    // Validate coupon again
    $coupon = sc_find_coupon($coupon_code, $event_id);

    if (!$coupon) {
        wp_send_json_error(array('message' => __('Invalid or expired coupon code.', 'sc_events')));
    }

    // Check usage limit
    $usage_count = intval(get_post_meta($coupon->ID, 'usage_count', true));
    $usage_limit = intval(get_post_meta($coupon->ID, 'usage_limit', true));
    if ($usage_limit > 0 && $usage_count >= $usage_limit) {
        wp_send_json_error(array('message' => __('This coupon has reached its usage limit.', 'sc_events')));
    }

    // Must be 100% discount for coupon-based registration
    $discount_type = get_post_meta($coupon->ID, 'discount_type', true);
    $discount_value = floatval(get_post_meta($coupon->ID, 'discount_value', true));
    if (!($discount_type === 'percentage' && $discount_value >= 100)) {
        wp_send_json_error(array('message' => __('This coupon does not provide full access. A 100% discount coupon is required.', 'sc_events')));
    }

    // Check if already registered
    if ($workshop_id) {
        global $wpdb;
        $att_table = $wpdb->prefix . 'sc_attendees';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$att_table} WHERE workshop_id = %d AND user_id = %d AND status != 'cancelled' LIMIT 1",
            $workshop_id, $user_id
        ));
        if ($exists) {
            wp_send_json_error(array('message' => __('You are already registered for this workshop.', 'sc_events')));
        }
    } else {
        $existing = sc_check_user_registration($user_id, $event_id);
        if ($existing) {
            wp_send_json_error(array('message' => __('You are already registered for this event.', 'sc_events')));
        }
    }

    // Get user info
    $user = get_user_by('ID', $user_id);
    $phone = get_user_meta($user_id, 'phone', true);

    // Get ticket details - use posted ticket_id if available
    $ticket_id = 0;
    $ticket_name = 'General';
    $ticket_price = 0;
    if ($ticket_id_post && class_exists('SC_Ticket')) {
        $ticket_obj = SC_Ticket::get($ticket_id_post);
        if ($ticket_obj) {
            $ticket_id = $ticket_obj->id;
            $ticket_name = $ticket_obj->name;
            $ticket_price = floatval($ticket_obj->price);
        }
    }
    if (!$ticket_id) {
        $ticket_details = sc_get_ticket_details($event_id, '');
        $ticket_id = intval($ticket_details['id'] ?? 0);
        $ticket_name = $ticket_details['name'] ?? 'General';
        $ticket_price = $ticket_details['price'] ?? 0;
    }

    // Extra fields: pass raw array to sc_create_attendee which handles sanitization
    if (!is_array($extra_fields)) {
        $extra_fields = array();
    }

    // Create attendee in custom table
    $coupon_attendee_data = array(
        'event_id'       => $event_id,
        'ticket_id'      => $ticket_id,
        'ticket_name'    => $ticket_name,
        'user_id'        => $user_id,
        'name'           => $user->display_name,
        'email'          => $user->user_email,
        'phone'          => $phone,
        'payment_method' => 'coupon',
        'payment_status' => 'success',
        'amount_paid'    => $ticket_price,
        'coupon_code'    => $coupon_code,
        'extra_fields'   => $extra_fields
    );
    if ($workshop_id) {
        $coupon_attendee_data['workshop_id'] = $workshop_id;
    }
    $attendee_id = sc_create_attendee($coupon_attendee_data);

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Failed to create registration. Please try again.', 'sc_events')));
    }

    // Increment coupon usage (meta key without underscore - as saved by dashboard)
    $usage_count = intval(get_post_meta($coupon->ID, 'usage_count', true));
    update_post_meta($coupon->ID, 'usage_count', $usage_count + 1);

    // Send confirmation email
    sc_public_send_ticket_email($attendee_id);

    // Get session count for confirmation message
    $session_count = 0;
    if (class_exists('SC_Session_Attendance')) {
        $sess = SC_Session_Attendance::get_attendee_summary($attendee_id, $event_id);
        $session_count = count($sess);
    }

    wp_send_json_success(array(
        'message' => __('Registration successful! Redirecting to your account...', 'sc_events'),
        'redirect' => home_url('/my-account/'),
        'session_count' => $session_count
    ));
}

/**
 * Process Checkout (Paid Ticket)
 */
add_action('wp_ajax_sc_process_checkout', 'sc_process_checkout_handler');
function sc_process_checkout_handler() {
    sc_verify_public_nonce();

    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(array('message' => __('Please login to continue.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    $workshop_id = intval($_POST['workshop_id'] ?? 0);
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $extra_fields = $_POST['extra_fields'] ?? array();

    if ($workshop_id && class_exists('SC_Workshop')) {
        $workshop = SC_Workshop::get($workshop_id);
        if (!$workshop) {
            wp_send_json_error(array('message' => __('Invalid workshop.', 'sc_events')));
        }
        $event_id = (int) $workshop->event_id;
    }

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'sc_events')));
    }

    // Get ticket price from Custom Tables
    $ticket_price = 0;
    $ticket_name = '';
    $ticket_type = 'general';

    if ($ticket_id && class_exists('SC_Ticket')) {
        $ticket_obj = SC_Ticket::get($ticket_id);
        if ($ticket_obj) {
            // Validate ticket belongs to the right context
            if ($workshop_id && intval($ticket_obj->workshop_id) !== $workshop_id) {
                wp_send_json_error(array('message' => __('Ticket does not match workshop.', 'sc_events')));
            }
            if (!$workshop_id && intval($ticket_obj->event_id) !== $event_id) {
                wp_send_json_error(array('message' => __('Ticket does not match event.', 'sc_events')));
            }
            $ticket_price = floatval($ticket_obj->price);
            $ticket_type = $ticket_obj->ticket_type ?? 'general';
            $ticket_name = $ticket_obj->name;
        }
    }

    $total = $ticket_price * $quantity;
    $discount = 0;

    // Apply coupon if provided
    if (!empty($coupon_code)) {
        $coupon = sc_find_coupon($coupon_code, $event_id, $ticket_type);
        if ($coupon) {
            // Meta keys without underscore - as saved by dashboard
            $discount_type = get_post_meta($coupon->ID, 'discount_type', true);
            $discount_value = floatval(get_post_meta($coupon->ID, 'discount_value', true));

            if ($discount_type === 'percentage') {
                $discount = ($total * $discount_value) / 100;
            } else {
                $discount = $discount_value;
            }

            // Increment coupon usage (meta key without underscore)
            $usage_count = intval(get_post_meta($coupon->ID, 'usage_count', true));
            update_post_meta($coupon->ID, 'usage_count', $usage_count + 1);
        }
    }

    $final_total = max(0, $total - $discount);

    // Get user info
    $user = get_user_by('ID', $user_id);
    $phone = get_user_meta($user_id, 'phone', true);

    // Sanitize extra fields
    $sanitized_extra_fields = array();
    if (is_array($extra_fields)) {
        foreach ($extra_fields as $field) {
            if (isset($field['label']) && isset($field['value'])) {
                $sanitized_extra_fields[] = array(
                    'label' => sanitize_text_field($field['label']),
                    'value' => sanitize_text_field($field['value'])
                );
            }
        }
    }

    // For free tickets (after discount), create attendee immediately
    if ($final_total == 0) {
        for ($i = 0; $i < $quantity; $i++) {
            $checkout_attendee_data = array(
                'event_id'       => $event_id,
                'ticket_id'      => $ticket_id,
                'ticket_name'    => $ticket_name,
                'user_id'        => $user_id,
                'name'           => $user->display_name,
                'email'          => $user->user_email,
                'phone'          => $phone,
                'payment_method' => !empty($coupon_code) ? 'coupon' : 'free',
                'payment_status' => 'success',
                'amount_paid'    => $ticket_price,
                'coupon_code'    => $coupon_code,
                'extra_fields'   => $sanitized_extra_fields,
                'ticket_type'    => $ticket_type
            );
            if ($workshop_id) {
                $checkout_attendee_data['workshop_id'] = $workshop_id;
            }
            $attendee_id = sc_create_attendee($checkout_attendee_data);

            if ($attendee_id) {
                sc_public_send_ticket_email($attendee_id);
            }
        }

        wp_send_json_success(array(
            'message' => __('Registration successful! Check your email for the ticket.', 'sc_events'),
            'redirect' => home_url('/my-account/')
        ));
    }

    // For paid tickets, store checkout data in session AND redirect to checkout page
    if (!session_id()) {
        session_start();
    }

    $_SESSION['sc_checkout'] = array(
        'event_id'          => $event_id,
        'workshop_id'       => $workshop_id,
        'ticket_id'         => $ticket_id,
        'ticket_name'       => $ticket_name,
        'ticket_price'      => $ticket_price,
        'quantity'          => $quantity,
        'coupon_code'       => $coupon_code,
        'discount'          => $discount,
        'total'             => $final_total,
        'extra_fields'      => $sanitized_extra_fields,
        'user_id'           => $user_id
    );

    // Build checkout URL with query params
    $checkout_url = add_query_arg(array(
        'event_id'  => $event_id,
        'ticket_id' => $ticket_id,
    ), home_url('/checkout/'));

    wp_send_json_success(array(
        'message' => __('Redirecting to payment...', 'sc_events'),
        'redirect' => $checkout_url
    ));
}

/**
 * Contact Form Handler
 * Sends email and stores in dashboard
 */
add_action('wp_ajax_sc_submit_contact_form', 'sc_submit_contact_form_handler');
add_action('wp_ajax_nopriv_sc_submit_contact_form', 'sc_submit_contact_form_handler');
function sc_submit_contact_form_handler() {
    sc_verify_public_nonce();

    $name = sanitize_text_field($_POST['contact_name'] ?? '');
    $email = sanitize_email($_POST['contact_email'] ?? '');
    $phone = sanitize_text_field($_POST['contact_phone'] ?? '');
    $subject = sanitize_text_field($_POST['contact_subject'] ?? '');
    $message = sanitize_textarea_field($_POST['contact_message'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Please fill in all required fields.', 'sc_events')));
    }

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'sc_events')));
    }

    // Get platform email
    $platform_email = get_option('sc_platform_email', get_option('admin_email'));
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    // Store in database as custom post type
    $contact_id = wp_insert_post(array(
        'post_title' => $subject,
        'post_content' => $message,
        'post_type' => 'sc_contact',
        'post_status' => 'publish'
    ));

    if ($contact_id) {
        update_post_meta($contact_id, '_contact_name', $name);
        update_post_meta($contact_id, '_contact_email', $email);
        update_post_meta($contact_id, '_contact_phone', $phone);
        update_post_meta($contact_id, '_contact_subject', $subject);
        update_post_meta($contact_id, '_contact_date', current_time('mysql'));
        update_post_meta($contact_id, '_contact_status', 'unread');
    }

    // Send email to platform admin
    $email_subject = sprintf(__('[%s] New Contact: %s', 'sc_events'), $platform_name, $subject);

    $email_message = sprintf(__('New contact form submission:', 'sc_events')) . "\n\n";
    $email_message .= sprintf(__('Name: %s', 'sc_events'), $name) . "\n";
    $email_message .= sprintf(__('Email: %s', 'sc_events'), $email) . "\n";
    if ($phone) {
        $email_message .= sprintf(__('Phone: %s', 'sc_events'), $phone) . "\n";
    }
    $email_message .= sprintf(__('Subject: %s', 'sc_events'), $subject) . "\n\n";
    $email_message .= sprintf(__('Message:', 'sc_events')) . "\n";
    $email_message .= $message . "\n\n";
    $email_message .= "---\n";
    $email_message .= sprintf(__('Sent from: %s', 'sc_events'), home_url('/'));

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>'
    );

    $mail_sent = wp_mail($platform_email, $email_subject, $email_message, $headers);

    // Send confirmation to user
    $user_subject = sprintf(__('[%s] We received your message', 'sc_events'), $platform_name);
    $user_message = sprintf(__('Dear %s,', 'sc_events'), $name) . "\n\n";
    $user_message .= __('Thank you for contacting us. We have received your message and will get back to you soon.', 'sc_events') . "\n\n";
    $user_message .= __('Your message:', 'sc_events') . "\n";
    $user_message .= $message . "\n\n";
    $user_message .= __('Best regards,', 'sc_events') . "\n";
    $user_message .= $platform_name;

    wp_mail($email, $user_subject, $user_message, array('Content-Type: text/plain; charset=UTF-8'));

    wp_send_json_success(array(
        'message' => __('Thank you for your message! We will get back to you soon.', 'sc_events')
    ));
}

/**
 * Newsletter Subscription
 */
add_action('wp_ajax_sc_newsletter_subscribe', 'sc_newsletter_subscribe_handler');
add_action('wp_ajax_nopriv_sc_newsletter_subscribe', 'sc_newsletter_subscribe_handler');
function sc_newsletter_subscribe_handler() {
    sc_verify_public_nonce();

    $email = sanitize_email($_POST['email'] ?? '');

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'sc_events')));
    }

    // Store in options (simple implementation)
    $subscribers = get_option('sc_newsletter_subscribers', array());

    if (in_array($email, $subscribers)) {
        wp_send_json_error(array('message' => __('This email is already subscribed.', 'sc_events')));
    }

    $subscribers[] = $email;
    update_option('sc_newsletter_subscribers', $subscribers);

    wp_send_json_success(array('message' => __('Thank you for subscribing!', 'sc_events')));
}

/**
 * Check if email is registered for event
 */
add_action('wp_ajax_sc_check_registration', 'sc_check_registration_handler');
add_action('wp_ajax_nopriv_sc_check_registration', 'sc_check_registration_handler');
function sc_check_registration_handler() {
    sc_verify_public_nonce();

    $email = sanitize_email($_POST['email'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);

    if (!is_email($email)) {
        wp_send_json_error(array('message' => __('Invalid email address.', 'sc_events')));
    }

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'sc_events')));
    }

    // Check if email is already registered for this event using Custom Tables
    if (class_exists('SC_Attendee')) {
        $existing = SC_Attendee::get_by_email_and_event($email, $event_id);

        if ($existing) {
            wp_send_json_success(array(
                'is_registered' => true,
                'ticket_id' => $existing->ticket_code,
                'ticket_name' => $existing->ticket_name,
                'registration_date' => date_i18n('F j, Y', strtotime($existing->created_at))
            ));
        }
    }

    wp_send_json_success(array('is_registered' => false));
}

// ===========================================
// HELPER FUNCTIONS
// ===========================================

/**
 * Get ticket details from event by ticket slug - Uses Custom Tables
 */
function sc_get_ticket_details($event_id, $ticket_slug) {
    // Use Custom Tables
    if (class_exists('SC_Ticket')) {
        $tickets = SC_Ticket::get_by_event($event_id, array('is_active' => true));

        if (!empty($tickets)) {
            // If ticket_slug is provided, find specific ticket
            if (!empty($ticket_slug)) {
                foreach ($tickets as $ticket) {
                    if ($ticket->id == $ticket_slug || sanitize_title($ticket->name) === $ticket_slug) {
                        return array(
                            'id'    => $ticket->id,
                            'name'  => $ticket->name,
                            'slug'  => sanitize_title($ticket->name),
                            'price' => floatval($ticket->current_price)
                        );
                    }
                }
            }

            // If no slug provided or not found, return first available ticket
            $first_ticket = reset($tickets);
            return array(
                'id'    => $first_ticket->id,
                'name'  => $first_ticket->name,
                'slug'  => sanitize_title($first_ticket->name),
                'price' => floatval($first_ticket->current_price)
            );
        }
    }

    // Default fallback
    return array(
        'name' => 'General',
        'slug' => $ticket_slug,
        'price' => 0
    );
}

/**
 * Check if user is already registered for event - Uses Custom Tables Only
 */
function sc_check_user_registration($user_id, $event_id) {
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        return false;
    }

    // Use Custom Tables
    if (class_exists('SC_Attendee')) {
        $existing = SC_Attendee::get_by_email_and_event($user->user_email, $event_id);
        if ($existing) {
            return true;
        }
    }

    return false;
}

/**
 * Find coupon by code
 */
function sc_find_coupon($code, $event_id = 0, $ticket_type = 'general') {
    // Search by post_title (coupon code is stored as post_title)
    $args = array(
        'post_type' => 'sc_coupon',
        'post_status' => 'publish',
        'title' => strtoupper($code),
        'posts_per_page' => 1
    );

    $query = new WP_Query($args);

    // If not found by exact title, try searching
    if (!$query->have_posts()) {
        $args = array(
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            's' => $code,
            'posts_per_page' => 1
        );
        $query = new WP_Query($args);
    }

    if ($query->have_posts()) {
        $coupon = $query->posts[0];

        // Verify code matches (case-insensitive)
        if (strtoupper($coupon->post_title) !== strtoupper($code)) {
            // Not an exact match, continue searching
        } else {
            // Check expiry (meta key without underscore prefix)
            // A date-only expiry is valid through the end of that day (site time).
            $expiry = trim((string) get_post_meta($coupon->ID, 'expiry_date', true));
            if ($expiry !== '' && (strlen($expiry) <= 10 ? $expiry < current_time('Y-m-d') : $expiry < current_time('mysql'))) {
                return false;
            }

            // Check if coupon is for specific event
            $coupon_event_id = get_post_meta($coupon->ID, 'event_id', true);
            if ($event_id > 0 && !empty($coupon_event_id) && intval($coupon_event_id) != $event_id) {
                return false; // Coupon is for a different event
            }

            // Check ticket type filter
            $filter = get_post_meta($coupon->ID, 'ticket_type_filter', true) ?: 'all';
            if ($filter !== 'all' && $filter !== $ticket_type) {
                return false; // Coupon not for this ticket type
            }

            return $coupon;
        }
    }

    return false;
}

/**
 * Create Attendee - Uses Custom Tables Only
 * No WordPress Post dependency - 100% custom database
 *
 * @param array $data Attendee data
 * @return int|false Attendee ID or false on failure
 */
function sc_create_attendee($data) {
    if (!class_exists('SC_Attendee')) {
        return false;
    }

    // Generate unique ticket code
    $ticket_type = sanitize_text_field($data['ticket_type'] ?? 'general');
    $qr_type = ($ticket_type === 'competitor') ? 'competitor' : 'attendee';
    $ticket_code = 'SC' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));

    // Extract and sanitize data
    $event_id = intval($data['event_id'] ?? 0);
    $workshop_id = intval($data['workshop_id'] ?? 0);
    $ticket_id = intval($data['ticket_id'] ?? 0);
    $ticket_name = sanitize_text_field($data['ticket_name'] ?? '');
    $amount_paid = floatval($data['amount_paid'] ?? $data['amount'] ?? 0);
    $payment_status = sanitize_text_field($data['payment_status'] ?? 'success');
    $payment_method = sanitize_text_field($data['payment_method'] ?? 'free');

    // Prepare extra fields
    $extra_fields_json = null;
    if (!empty($data['extra_fields']) && is_array($data['extra_fields'])) {
        $extra_fields_assoc = array();
        foreach ($data['extra_fields'] as $field) {
            if (isset($field['label']) && isset($field['value'])) {
                $extra_fields_assoc[sanitize_text_field($field['label'])] = sanitize_text_field($field['value']);
            }
        }
        if (!empty($extra_fields_assoc)) {
            $extra_fields_json = json_encode($extra_fields_assoc);
        }
    }

    // QR data — include workshop_id when set (so scanner can validate workshop scope)
    $qr_payload = array('type' => $qr_type, 'code' => $ticket_code, 'event' => $event_id);
    if ($workshop_id) {
        $qr_payload['workshop'] = $workshop_id;
    }

    // Create attendee in custom table
    $attendee_create_data = array(
        'event_id'       => $event_id,
        'ticket_id'      => $ticket_id ?: null,
        'user_id'        => intval($data['user_id'] ?? 0),
        'name'           => sanitize_text_field($data['name'] ?? ''),
        'email'          => sanitize_email($data['email'] ?? ''),
        'phone'          => sanitize_text_field($data['phone'] ?? ''),
        'ticket_code'    => $ticket_code,
        'ticket_name'    => $ticket_name,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method,
        'amount_paid'    => $amount_paid,
        'coupon_code'    => sanitize_text_field($data['coupon_code'] ?? ''),
        'order_id'       => intval($data['order_id'] ?? 0),
        'extra_fields'   => $extra_fields_json,
        'qr_data'        => json_encode($qr_payload),
        'checked_in'     => 0,
        'status'         => 'active'
    );
    if ($workshop_id) {
        $attendee_create_data['workshop_id'] = $workshop_id;
    }
    $attendee_id = SC_Attendee::create($attendee_create_data);

    // Update workshop stats if applicable
    if ($attendee_id && $workshop_id && class_exists('SC_Workshop')) {
        SC_Workshop::update_stats($workshop_id);
    }

    // Auto-register in sessions (if sessions module is active) — skip for workshop attendees (they're not part of event sessions)
    if ($attendee_id && $event_id && !$workshop_id && function_exists('sc_auto_register_attendee_in_sessions')) {
        sc_auto_register_attendee_in_sessions($attendee_id, $event_id);
    }

    return $attendee_id;
}

/**
 * Legacy function name for backward compatibility
 * @deprecated Use sc_create_attendee() instead
 */
function sc_create_eventin_attendee($data) {
    return sc_create_attendee($data);
}

/**
 * Send the ticket after registering or paying: WhatsApp with the QR image, and email when email is on
 * (inc/whatsapp/wabot-notify.php). Returns true when something was queued.
 */
function sc_public_send_ticket_email($attendee_id) {
    if (!function_exists('sc_notify_ticket')) {
        return false;
    }
    $result = sc_notify_ticket((int) $attendee_id);
    return (bool) ($result['whatsapp'] || $result['email']);
}

// ============================================================
// FAVORITES
// ============================================================

add_action('wp_ajax_sc_toggle_favorite', 'sc_toggle_favorite_handler');
function sc_toggle_favorite_handler() {
    sc_verify_public_nonce();

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Please login first.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_favorites';
    $user_id = get_current_user_id();

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_error(array('message' => __('Favorites not available.', 'sc_events')));
    }

    // Check if already favorited
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND event_id = %d",
        $user_id, $event_id
    ));

    if ($exists) {
        $wpdb->delete($table, array('user_id' => $user_id, 'event_id' => $event_id), array('%d', '%d'));
        $favorited = false;
    } else {
        $wpdb->insert($table, array(
            'user_id'    => $user_id,
            'event_id'   => $event_id,
            'created_at' => current_time('mysql')
        ), array('%d', '%d', '%s'));
        $favorited = true;
    }

    // Get total count for this user
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d",
        $user_id
    ));

    wp_send_json_success(array(
        'favorited' => $favorited,
        'count'     => $count
    ));
}

add_action('wp_ajax_sc_get_favorites', 'sc_get_favorites_handler');
add_action('wp_ajax_nopriv_sc_get_favorites', 'sc_get_favorites_handler');
/**
 * Public certificate request - user requests their own certificate
 */
add_action('wp_ajax_sc_request_certificate_public', 'sc_request_certificate_public_handler');
function sc_request_certificate_public_handler() {
    sc_verify_public_nonce();

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Please log in.', 'sc_events')));
    }

    $ticket_code = sanitize_text_field($_POST['ticket_code'] ?? '');
    if (empty($ticket_code)) {
        wp_send_json_error(array('message' => __('Invalid ticket.', 'sc_events')));
    }

    // Get attendee by ticket code
    if (!class_exists('SC_Attendee') || !class_exists('SC_Event') || !class_exists('SC_Certificate')) {
        wp_send_json_error(array('message' => __('Certificate system not available.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $current_user    = wp_get_current_user();
    // Match by user_id when the user registered themselves, else fall back to email
    // (tickets registered by an organizer from the dashboard often have no user_id).
    $attendee = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $attendees_table
         WHERE ticket_code = %s
           AND payment_status = 'success'
           AND (user_id = %d OR email = %s)
         LIMIT 1",
        $ticket_code, $current_user->ID, $current_user->user_email
    ));

    if (!$attendee) {
        wp_send_json_error(array('message' => __('Ticket not found.', 'sc_events')));
    }

    $event = SC_Event::get($attendee->event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Check if certificates are enabled
    if (empty($event->enable_certificates)) {
        wp_send_json_error(array('message' => __('Certificates are not available for this event.', 'sc_events')));
    }

    // Check if already issued — SC_Certificate::get_by_attendee_event() returns an ARRAY (hydrated)
    $existing = SC_Certificate::get_by_attendee_event($attendee->id, $event->id);
    if (is_array($existing) && ($existing['status'] ?? '') !== 'revoked') {
        $token = wp_hash($existing['verification_code'] . $existing['certificate_number']);
        $url = add_query_arg(array(
            'action' => 'sc_download_certificate',
            'id'     => $existing['id'],
            'token'  => $token
        ), admin_url('admin-ajax.php'));
        wp_send_json_success(array('download_url' => $url, 'message' => __('Certificate ready.', 'sc_events')));
    }

    // Check requirements
    $checked_in = !empty($attendee->checked_in);
    if (!empty($event->certificate_require_checkin) && !$checked_in) {
        wp_send_json_error(array('message' => __('You need to check in to the event first.', 'sc_events')));
    }

    if (!empty($event->certificate_require_checkout)) {
        $has_checkout = false;
        if (class_exists('SC_Checkin')) {
            $logs = SC_Checkin::get_by_attendee($attendee->id);
            foreach ($logs as $log) {
                if (in_array($log->action, array('checkout', 'manual_checkout', 'check_out'))) {
                    $has_checkout = true;
                    break;
                }
            }
        }
        if (!$has_checkout) {
            wp_send_json_error(array('message' => __('You need to check out from the event first.', 'sc_events')));
        }
    }

    $event_end = $event->end_date ?? $event->start_date;
    if (!empty($event->certificate_require_event_ended) && strtotime($event_end) >= strtotime(current_time('Y-m-d'))) {
        wp_send_json_error(array('message' => __('Certificate will be available after the event ends.', 'sc_events')));
    }

    // Get template
    $template_id = $event->certificate_template_id ?? 0;
    if (!$template_id && class_exists('SC_Certificate_Template')) {
        $default = SC_Certificate_Template::get_default();
        $template_id = $default ? ($default->id ?? ($default['id'] ?? 0)) : 0;
    }

    if (!$template_id) {
        wp_send_json_error(array('message' => __('No certificate template configured.', 'sc_events')));
    }

    // Issue certificate
    $cert_data = array(
        'template_id'   => $template_id,
        'attendee_id'   => $attendee->id,
        'event_id'      => $event->id,
        'attendee_name' => $attendee->name,
        'event_title'   => $event->title,
        'event_date'    => $event->start_date,
        'issued_by'     => get_current_user_id()
    );

    $cert_id = SC_Certificate::issue($cert_data);

    if (is_wp_error($cert_id)) {
        wp_send_json_error(array('message' => $cert_id->get_error_message()));
    }

    $certificate = SC_Certificate::get($cert_id);
    if (is_array($certificate)) {
        $token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);
        $url = add_query_arg(array(
            'action' => 'sc_download_certificate',
            'id'     => $certificate['id'],
            'token'  => $token
        ), admin_url('admin-ajax.php'));
        wp_send_json_success(array(
            'download_url' => $url,
            'message'      => __('Certificate issued successfully!', 'sc_events')
        ));
    }

    wp_send_json_error(array('message' => __('Failed to issue certificate.', 'sc_events')));
}

function sc_get_favorites_handler() {
    if (!is_user_logged_in()) {
        wp_send_json_success(array('favorites' => array(), 'count' => 0));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_favorites';
    $user_id = get_current_user_id();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(array('favorites' => array(), 'count' => 0));
    }

    $favorites = $wpdb->get_col($wpdb->prepare(
        "SELECT event_id FROM $table WHERE user_id = %d",
        $user_id
    ));

    wp_send_json_success(array(
        'favorites' => array_map('intval', $favorites),
        'count'     => count($favorites)
    ));
}
