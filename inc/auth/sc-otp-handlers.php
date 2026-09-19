<?php
/**
 * Public AJAX handlers for WhatsApp codes (service: inc/auth/sc-otp.php).
 *
 * - Sign in with a code:   sc_otp_login_send → sc_otp_login_verify (→ sc_otp_login_choose when one
 *                          number has several accounts).
 * - Create an account:     sc_public_register (public-ajax-handlers.php) sends the code →
 *                          sc_register_verify / sc_register_resend.
 * - Reset a password:      sc_reset_send → sc_reset_verify → sc_reset_set_password.
 * - Change the phone:      sc_update_profile asks for a code sent to the new number.
 *
 * Staff accounts (anyone who can edit posts) keep signing in with their password.
 * Answers to "send a code" never say whether an account exists.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The number typed on a form (country code select + number) as international digits. */
function sc_otp_phone_input($code_field = 'phone_code', $number_field = 'phone') {
    return sc_otp_normalize_phone(wp_unslash($_POST[$code_field] ?? '+20'), wp_unslash($_POST[$number_field] ?? ''));
}

/** A country code and a number as typed ("010…", "+20 10…", "10…") as international digits. */
function sc_otp_normalize_phone($country_code, $number) {
    $cc = preg_replace('/\D/', '', sanitize_text_field((string) $country_code)) ?: '20';
    $raw = sanitize_text_field((string) $number);
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
        return '';
    }
    if (strpos(trim($raw), '+') === 0 || strpos($digits, '00') === 0) {
        return sc_wabot_phone($digits);            // typed with its own country code
    }
    if ($cc === '20') {
        return sc_wabot_phone($digits);            // 010…, 10…, 2010…
    }
    if (strpos($digits, $cc) === 0 && strlen($digits) > strlen($cc) + 7) {
        return sc_wabot_phone($digits);
    }
    return sc_wabot_phone($cc . ltrim($digits, '0'));
}

function sc_otp_is_staff($user_id) {
    return user_can((int) $user_id, 'edit_posts');
}

/** Accounts on this number that may use codes. */
function sc_otp_member_accounts($phone) {
    return array_values(array_filter(sc_users_by_phone($phone), function ($id) {
        return !sc_otp_is_staff($id);
    }));
}

function sc_otp_error($error) {
    $data = array('message' => $error->get_error_message(), 'code' => $error->get_error_code());
    $extra = $error->get_error_data();
    if (is_array($extra)) {
        $data += $extra;
    }
    wp_send_json_error($data);
}

function sc_otp_safe_redirect($url) {
    $url = wp_validate_redirect(esc_url_raw((string) $url), home_url('/my-account/'));
    return $url ?: home_url('/my-account/');
}

/** Sign a member in after a code, and remember the number was confirmed. */
function sc_otp_sign_in($user_id, $phone, $remember) {
    sc_set_user_phone($user_id, $phone, true);
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, $remember, is_ssl());
    do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
}

/* ==========================================================================
   Sign in with a code
   ========================================================================== */

add_action('wp_ajax_nopriv_sc_otp_login_send', 'sc_otp_login_send');
function sc_otp_login_send() {
    sc_verify_public_nonce();
    $phone = sc_otp_phone_input();
    if ($phone === '') {
        wp_send_json_error(array('message' => __('Enter the WhatsApp number on your account.', 'sc_events')));
    }
    $accounts = sc_otp_member_accounts($phone);
    $sent = sc_otp_send('login', $phone, array('silent' => !$accounts, 'user_id' => count($accounts) === 1 ? $accounts[0] : 0));
    if (is_wp_error($sent)) {
        sc_otp_error($sent);
    }
    wp_send_json_success($sent + array(
        'phone'   => sc_otp_mask_phone($phone),
        'message' => __('If an account uses this number, a 6-digit code is on its way on WhatsApp.', 'sc_events'),
    ));
}

add_action('wp_ajax_nopriv_sc_otp_login_verify', 'sc_otp_login_verify');
function sc_otp_login_verify() {
    sc_verify_public_nonce();
    $phone = sc_otp_phone_input();
    $ok = sc_otp_verify('login', $phone, sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    if (is_wp_error($ok)) {
        sc_otp_error($ok);
    }
    $accounts = sc_otp_member_accounts($phone);
    if (!$accounts) {
        wp_send_json_error(array('message' => __('No account uses this number. Create one, or sign in with your email.', 'sc_events')));
    }
    $remember = !empty($_POST['remember']);
    $redirect = sc_otp_safe_redirect(wp_unslash($_POST['redirect'] ?? ''));
    if (count($accounts) === 1) {
        sc_otp_sign_in($accounts[0], $phone, $remember);
        wp_send_json_success(array('message' => __('Signed in.', 'sc_events'), 'redirect' => $redirect));
    }
    wp_send_json_success(array(
        'choose'   => sc_otp_account_choices($accounts),
        'proof'    => sc_otp_issue_proof('login', $phone, array('users' => $accounts, 'remember' => $remember)),
        'redirect' => $redirect,
        'message'  => __('This number is on more than one account. Which one?', 'sc_events'),
    ));
}

add_action('wp_ajax_nopriv_sc_otp_login_choose', 'sc_otp_login_choose');
function sc_otp_login_choose() {
    sc_verify_public_nonce();
    $proof = sc_otp_read_proof(wp_unslash($_POST['proof'] ?? ''), 'login');
    $user_id = absint($_POST['user_id'] ?? 0);
    if (!$proof || !in_array($user_id, $proof['users'], true)) {
        wp_send_json_error(array('message' => __('This step has expired. Ask for a new code.', 'sc_events')));
    }
    sc_otp_sign_in($user_id, $proof['phone'], !empty($proof['remember']));
    wp_send_json_success(array('message' => __('Signed in.', 'sc_events'), 'redirect' => sc_otp_safe_redirect(wp_unslash($_POST['redirect'] ?? ''))));
}

/* ==========================================================================
   Create an account: the code step (the form itself is sc_public_register)
   ========================================================================== */

function sc_register_pending_key($token) {
    return 'sc_reg_pending_' . hash('sha256', preg_replace('/[^A-Za-z0-9]/', '', (string) $token));
}

/**
 * Keep a registration until its number is confirmed, and send the code.
 *
 * @return array|WP_Error Response data for the page.
 */
function sc_register_start_verification($name, $email, $phone, $password) {
    $token = wp_generate_password(40, false);
    set_transient(sc_register_pending_key($token), array(
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'hash'  => wp_hash_password($password),
    ), 20 * MINUTE_IN_SECONDS);
    $sent = sc_otp_send('register', $phone);
    if (is_wp_error($sent)) {
        delete_transient(sc_register_pending_key($token));
        return $sent;
    }
    return $sent + array(
        'verify'  => true,
        'token'   => $token,
        'phone'   => sc_otp_mask_phone($phone),
        'message' => __('We sent a 6-digit code to your WhatsApp. Enter it to finish.', 'sc_events'),
    );
}

/** Create the member account. $password_hash is used as is; $password when there is no hash. */
function sc_register_create_account($name, $email, $phone, $verified, $password = '', $password_hash = '') {
    global $wpdb;
    $user_id = wp_insert_user(array(
        'user_login'   => $email,
        'user_email'   => $email,
        'user_pass'    => $password !== '' ? $password : wp_generate_password(32),
        'display_name' => $name,
        'first_name'   => $name,
        'role'         => 'subscriber',
    ));
    if (is_wp_error($user_id)) {
        return $user_id;
    }
    if ($password_hash !== '') {
        $wpdb->update($wpdb->users, array('user_pass' => $password_hash, 'user_activation_key' => ''), array('ID' => $user_id));
        clean_user_cache($user_id);
    }
    if ($phone !== '') {
        sc_set_user_phone($user_id, $phone, $verified);
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    return $user_id;
}

add_action('wp_ajax_nopriv_sc_register_verify', 'sc_register_verify');
function sc_register_verify() {
    sc_verify_public_nonce();
    $key = sc_register_pending_key(wp_unslash($_POST['token'] ?? ''));
    $pending = get_transient($key);
    if (!is_array($pending)) {
        wp_send_json_error(array('message' => __('This sign-up has expired. Please fill in the form again.', 'sc_events'), 'restart' => true));
    }
    $ok = sc_otp_verify('register', $pending['phone'], sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    if (is_wp_error($ok)) {
        sc_otp_error($ok);
    }
    delete_transient($key);
    if (email_exists($pending['email'])) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events'), 'restart' => true));
    }
    if (sc_verified_owner_of_phone($pending['phone'])) {
        wp_send_json_error(array('message' => __('This number already belongs to an account. Sign in with a WhatsApp code instead.', 'sc_events'), 'restart' => true));
    }
    $user_id = sc_register_create_account($pending['name'], $pending['email'], $pending['phone'], true, '', $pending['hash']);
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message(), 'restart' => true));
    }
    wp_send_json_success(array('message' => __('Your account is ready.', 'sc_events'), 'redirect' => home_url('/my-account/')));
}

add_action('wp_ajax_nopriv_sc_register_resend', 'sc_register_resend');
function sc_register_resend() {
    sc_verify_public_nonce();
    $pending = get_transient(sc_register_pending_key(wp_unslash($_POST['token'] ?? '')));
    if (!is_array($pending)) {
        wp_send_json_error(array('message' => __('This sign-up has expired. Please fill in the form again.', 'sc_events'), 'restart' => true));
    }
    $sent = sc_otp_send('register', $pending['phone']);
    if (is_wp_error($sent)) {
        sc_otp_error($sent);
    }
    wp_send_json_success($sent + array('message' => __('A new code is on its way.', 'sc_events')));
}

/* ==========================================================================
   Reset a password
   ========================================================================== */

/**
 * The account and number a reset is about. By email: that account's number. By number: the
 * accounts on it.
 *
 * @return array phone, users (member ids).
 */
function sc_reset_target() {
    $by = sanitize_key(wp_unslash($_POST['by'] ?? 'phone'));
    return $by === 'email'
        ? sc_reset_target_by_email(wp_unslash($_POST['email'] ?? ''))
        : sc_reset_target_by_phone(sc_otp_phone_input());
}

function sc_reset_target_by_email($email) {
    $user = get_user_by('email', sanitize_email((string) $email));
    if (!$user || sc_otp_is_staff($user->ID)) {
        return array('phone' => '', 'users' => array());
    }
    $phone = sc_wabot_phone(get_user_meta($user->ID, 'phone', true)) ?: sc_wabot_phone(get_user_meta($user->ID, 'billing_phone', true));
    return array('phone' => $phone, 'users' => $phone !== '' ? array((int) $user->ID) : array());
}

function sc_reset_target_by_phone($phone) {
    return array('phone' => $phone, 'users' => $phone !== '' ? sc_otp_member_accounts($phone) : array());
}

/** Masked name and email of each account, for "which account?" */
function sc_otp_account_choices($user_ids) {
    $list = array();
    foreach ($user_ids as $id) {
        $u = get_userdata($id);
        $list[] = array('id' => (int) $id, 'name' => $u->display_name, 'email' => sc_mask_email($u->user_email));
    }
    return $list;
}

/** Password rules for a reset: at least 8 characters and not the phone number. Empty = fine. */
function sc_reset_password_problem($password, $phone) {
    if (strlen($password) < 8) {
        return __('Use at least 8 characters.', 'sc_events');
    }
    if (sc_wabot_phone($password) === $phone) {
        return __('Don’t use your phone number as the password.', 'sc_events');
    }
    return '';
}

add_action('wp_ajax_nopriv_sc_reset_send', 'sc_reset_send');
function sc_reset_send() {
    sc_verify_public_nonce();
    if (!sc_password_reset_available()) {
        wp_send_json_error(array('message' => __('Password reset is temporarily unavailable. Please contact us and we will help you sign in.', 'sc_events'), 'paused' => true), 403);
    }
    $by = sanitize_key(wp_unslash($_POST['by'] ?? 'phone'));
    $target = sc_reset_target();
    if ($by === 'email' && !is_email(sanitize_email(wp_unslash($_POST['email'] ?? '')))) {
        wp_send_json_error(array('message' => __('Enter the email on your account.', 'sc_events')));
    }
    if ($by !== 'email' && $target['phone'] === '') {
        wp_send_json_error(array('message' => __('Enter the WhatsApp number on your account.', 'sc_events')));
    }
    if ($target['phone'] === '') {
        // Email with no account or no number on it: look the same as a real send.
        wp_send_json_success(array('resend_in' => SC_OTP_RESEND_AFTER, 'expires_in' => SC_OTP_TTL,
            'message' => __('If an account matches and has a WhatsApp number, a 6-digit code is on its way to that number.', 'sc_events')));
    }
    $sent = sc_otp_send('reset', $target['phone'], array('silent' => !$target['users'], 'user_id' => count($target['users']) === 1 ? $target['users'][0] : 0));
    if (is_wp_error($sent)) {
        sc_otp_error($sent);
    }
    wp_send_json_success($sent + array('message' => __('If an account matches and has a WhatsApp number, a 6-digit code is on its way to that number.', 'sc_events')));
}

add_action('wp_ajax_nopriv_sc_reset_verify', 'sc_reset_verify');
function sc_reset_verify() {
    sc_verify_public_nonce();
    $target = sc_reset_target();
    $ok = sc_otp_verify('reset', $target['phone'], sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    if (is_wp_error($ok)) {
        sc_otp_error($ok);
    }
    if (!$target['users']) {
        wp_send_json_error(array('message' => __('No account matches. Create one, or contact us.', 'sc_events')));
    }
    wp_send_json_success(array(
        'proof'  => sc_otp_issue_proof('reset', $target['phone'], array('users' => $target['users'])),
        'choose' => count($target['users']) > 1 ? sc_otp_account_choices($target['users']) : array(),
    ));
}

add_action('wp_ajax_nopriv_sc_reset_set_password', 'sc_reset_set_password');
function sc_reset_set_password() {
    sc_verify_public_nonce();
    $password = (string) wp_unslash($_POST['password'] ?? '');
    $token = wp_unslash($_POST['proof'] ?? '');
    $proof = sc_otp_read_proof($token, 'reset', false);
    if (!$proof) {
        wp_send_json_error(array('message' => __('This step has expired. Ask for a new code.', 'sc_events'), 'restart' => true));
    }
    $user_id = count($proof['users']) === 1 ? $proof['users'][0] : absint($_POST['user_id'] ?? 0);
    if (!in_array($user_id, $proof['users'], true)) {
        wp_send_json_error(array('message' => __('Choose the account.', 'sc_events')));
    }
    $problem = sc_reset_password_problem($password, $proof['phone']);
    if ($problem !== '') {
        wp_send_json_error(array('message' => $problem));
    }
    sc_otp_read_proof($token, 'reset');
    wp_set_password($password, $user_id);
    delete_user_meta($user_id, 'sc_must_change_password');
    sc_otp_sign_in($user_id, $proof['phone'], true);
    if (function_exists('sc_notify_password_changed')) {
        sc_notify_password_changed($user_id);
    }
    wp_send_json_success(array('message' => __('Your password is changed and you are signed in.', 'sc_events'), 'redirect' => home_url('/my-account/')));
}
