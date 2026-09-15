<?php
/**
 * One-time codes sent on WhatsApp: sign in, create an account, reset a password, change the phone.
 *
 * - 6 digits, stored only as an HMAC, valid 5 minutes, 5 wrong tries per code.
 * - Sending: 60 s between codes to the same number, 3 per 15 minutes per number, 10 per hour per IP.
 * - Checking: 20 wrong codes per hour from one IP locks that IP out for an hour.
 * - A code is bound to its purpose and number; a correct code is used up at once.
 * - The outbox keeps the text masked (wabot.php, sc_wabot_is_secret_context()).
 *
 * Page handlers: inc/auth/sc-otp-handlers.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

const SC_OTP_TTL = 300;
const SC_OTP_MAX_TRIES = 5;
const SC_OTP_RESEND_AFTER = 60;

function sc_otp_purposes() {
    return array(
        'login'    => 'تسجيل الدخول',
        'register' => 'إنشاء الحساب',
        'reset'    => 'تغيير كلمة السر',
        'phone'    => 'تأكيد رقم الموبايل',
    );
}

/** A number that can send codes right now (enabled, allowed for codes, connected). */
function sc_otp_available() {
    if (!function_exists('sc_wabot_candidates')) {
        return false;
    }
    $numbers = sc_wabot_settings()['numbers'];
    foreach (sc_wabot_candidates(null, 'otp_login') as $key) {
        if (($numbers[$key]['status'] ?? '') === 'connected') {
            return true;
        }
    }
    return false;
}

/** Password reset is open again once codes can be sent (it was paused in dee3bad). */
function sc_password_reset_available() {
    return (bool) apply_filters('sc_password_reset_available', sc_otp_available());
}

function sc_otp_key($purpose, $phone) {
    return 'sc_otp_' . substr(hash_hmac('sha256', $purpose . '|' . $phone, wp_salt('auth')), 0, 32);
}

function sc_otp_hash($code, $purpose, $phone) {
    return hash_hmac('sha256', $purpose . '|' . $phone . '|' . $code, wp_salt('secure_auth'));
}

/** "+20 10•••••45" */
function sc_otp_mask_phone($phone) {
    $phone = (string) $phone;
    if (strlen($phone) < 6) {
        return '';
    }
    $cc = strpos($phone, '20') === 0 ? '20' : substr($phone, 0, strlen($phone) > 11 ? 3 : 2);
    $rest = substr($phone, strlen($cc));
    return '+' . $cc . ' ' . substr($rest, 0, 2) . str_repeat('•', max(0, strlen($rest) - 4)) . substr($rest, -2);
}

/** Counter in a transient that keeps its first expiry. Returns the new count. */
function sc_otp_bump($key, $window) {
    $data = get_transient($key);
    if (!is_array($data) || ($data['until'] ?? 0) < time()) {
        $data = array('n' => 0, 'until' => time() + $window);
    }
    $data['n']++;
    set_transient($key, $data, max(1, $data['until'] - time()));
    return $data['n'];
}

function sc_otp_count($key) {
    $data = get_transient($key);
    return is_array($data) && ($data['until'] ?? 0) >= time() ? (int) $data['n'] : 0;
}

function sc_otp_ip_key($what) {
    return 'sc_otp_ip_' . $what . '_' . substr(md5((string) sc_get_client_ip()), 0, 20);
}

/**
 * Send a code. $phone must already be normalised (sc_wabot_phone()).
 *
 * @param array $args user_id (who the code is for), silent (count limits but don't send: used when
 *                    no account matches, so the answer looks the same).
 * @return array|WP_Error resend_in, expires_in.
 */
function sc_otp_send($purpose, $phone, $args = array()) {
    $purposes = sc_otp_purposes();
    if (!isset($purposes[$purpose]) || $phone === '') {
        return new WP_Error('sc_otp_phone', __('Enter a valid WhatsApp number.', 'sc_events'));
    }
    if (!sc_otp_available()) {
        return new WP_Error('sc_otp_unavailable', __('WhatsApp codes are not available right now. Please try again later or contact us.', 'sc_events'));
    }
    $key = sc_otp_key($purpose, $phone);
    $existing = get_transient($key);
    if (is_array($existing) && ($existing['sent_at'] + SC_OTP_RESEND_AFTER) > time()) {
        $wait = $existing['sent_at'] + SC_OTP_RESEND_AFTER - time();
        return new WP_Error('sc_otp_wait', sprintf(_n('Wait %d second before asking for a new code.', 'Wait %d seconds before asking for a new code.', $wait, 'sc_events'), $wait), array('resend_in' => $wait));
    }
    if (sc_otp_count('sc_otp_ph_' . md5($phone)) >= 3) {
        return new WP_Error('sc_otp_limit', __('Too many codes for this number. Try again in 15 minutes.', 'sc_events'));
    }
    if (sc_otp_count(sc_otp_ip_key('send')) >= 10) {
        return new WP_Error('sc_otp_limit', __('Too many codes requested. Try again in an hour.', 'sc_events'));
    }
    sc_otp_bump('sc_otp_ph_' . md5($phone), 15 * MINUTE_IN_SECONDS);
    sc_otp_bump(sc_otp_ip_key('send'), HOUR_IN_SECONDS);

    if (empty($args['silent'])) {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        set_transient($key, array(
            'hash'    => sc_otp_hash($code, $purpose, $phone),
            'tries'   => 0,
            'sent_at' => time(),
            'expires' => time() + SC_OTP_TTL,
            'user_id' => (int) ($args['user_id'] ?? 0),
        ), SC_OTP_TTL + 60);
        $site = wp_specialchars_decode(get_option('sc_platform_name', get_bloginfo('name')), ENT_QUOTES);
        $text = sprintf("كود %s في %s:\n*%s*\n\nصالح 5 دقايق. متديش الكود لأي حد، حتى لو قال إنه من %s.", $purposes[$purpose], $site, $code, $site);
        $number = sc_wabot_candidates(null, 'otp_' . $purpose)[0] ?? null;
        if (!$number || !sc_wabot_enqueue($number, $phone, $text, 'otp_' . $purpose, $args['user_id'] ?? null, 10)) {
            delete_transient($key);
            return new WP_Error('sc_otp_unavailable', __('The code could not be sent. Please try again.', 'sc_events'));
        }
    } else {
        // Same cooldown as a real send, so the two can't be told apart.
        set_transient($key, array('hash' => '', 'tries' => SC_OTP_MAX_TRIES, 'sent_at' => time(), 'expires' => time() + SC_OTP_TTL, 'user_id' => 0), SC_OTP_TTL + 60);
    }
    return array('resend_in' => SC_OTP_RESEND_AFTER, 'expires_in' => SC_OTP_TTL);
}

/**
 * Check a code. A correct code is used up.
 *
 * @return array|WP_Error user_id stored with the code.
 */
function sc_otp_verify($purpose, $phone, $code) {
    $ip_key = sc_otp_ip_key('fail');
    if (sc_otp_count($ip_key) >= 20) {
        return new WP_Error('sc_otp_locked', __('Too many wrong codes. Try again in an hour.', 'sc_events'));
    }
    $code = preg_replace('/\D/', '', (string) $code);
    $key = sc_otp_key($purpose, $phone);
    $data = $phone !== '' ? get_transient($key) : false;
    if (!is_array($data) || $data['expires'] < time()) {
        return new WP_Error('sc_otp_expired', __('This code has expired. Ask for a new one.', 'sc_events'));
    }
    if ($data['tries'] >= SC_OTP_MAX_TRIES || $data['hash'] === '') {
        sc_otp_bump($ip_key, HOUR_IN_SECONDS);
        return new WP_Error('sc_otp_expired', __('This code can no longer be used. Ask for a new one.', 'sc_events'));
    }
    if (strlen($code) !== 6 || !hash_equals($data['hash'], sc_otp_hash($code, $purpose, $phone))) {
        $data['tries']++;
        set_transient($key, $data, max(1, $data['expires'] - time() + 60));
        sc_otp_bump($ip_key, HOUR_IN_SECONDS);
        $left = SC_OTP_MAX_TRIES - $data['tries'];
        return new WP_Error('sc_otp_wrong', $left > 0
            ? sprintf(_n('Wrong code. %d try left.', 'Wrong code. %d tries left.', $left, 'sc_events'), $left)
            : __('Wrong code. Ask for a new one.', 'sc_events'));
    }
    delete_transient($key);
    return array('user_id' => (int) $data['user_id']);
}

/** A short-lived proof that a number was verified, for the next step of a flow. */
function sc_otp_issue_proof($purpose, $phone, $extra = array()) {
    $token = wp_generate_password(40, false);
    set_transient('sc_otp_proof_' . hash('sha256', $token), array('purpose' => $purpose, 'phone' => $phone) + $extra, 15 * MINUTE_IN_SECONDS);
    return $token;
}

/** @return array|false The proof data; used up when $consume. */
function sc_otp_read_proof($token, $purpose, $consume = true) {
    $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $token);
    if (strlen($token) !== 40) {
        return false;
    }
    $key = 'sc_otp_proof_' . hash('sha256', $token);
    $data = get_transient($key);
    if (!is_array($data) || $data['purpose'] !== $purpose) {
        return false;
    }
    if ($consume) {
        delete_transient($key);
    }
    return $data;
}

/* ==========================================================================
   Phones on accounts
   ========================================================================== */

/**
 * Accounts whose phone is this number, however it was typed ("+2001…", "010…", "+20 10…").
 *
 * @return int[] User ids.
 */
function sc_users_by_phone($phone) {
    global $wpdb;
    if (strlen($phone) < 8) {
        return array();
    }
    // Narrow by the last four digits, then compare the normalised numbers.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('phone', 'billing_phone', 'sc_phone_e164') AND meta_value LIKE %s LIMIT 1000",
        '%' . $wpdb->esc_like(substr($phone, -4)) . '%'
    ));
    $ids = array();
    foreach ($rows as $r) {
        if (sc_wabot_phone($r->meta_value) === $phone) {
            $ids[(int) $r->user_id] = true;
        }
    }
    return array_keys($ids);
}

/** Save a phone on an account in one format, and whether it was confirmed by a code. */
function sc_set_user_phone($user_id, $phone, $verified = false) {
    update_user_meta($user_id, 'phone', $phone !== '' ? '+' . $phone : '');
    update_user_meta($user_id, 'sc_phone_e164', $phone);
    if ($verified) {
        update_user_meta($user_id, 'sc_phone_verified_at', current_time('mysql'));
    } else {
        delete_user_meta($user_id, 'sc_phone_verified_at');
    }
}

/** An account that already proved it owns this number, other than $except. */
function sc_verified_owner_of_phone($phone, $except = 0) {
    foreach (sc_users_by_phone($phone) as $id) {
        if ($id !== (int) $except && get_user_meta($id, 'sc_phone_verified_at', true) && get_user_meta($id, 'sc_phone_e164', true) === $phone) {
            return $id;
        }
    }
    return 0;
}

/** "a•••@clinic.com" */
function sc_mask_email($email) {
    $parts = explode('@', (string) $email);
    if (count($parts) !== 2) {
        return '';
    }
    return mb_substr($parts[0], 0, 1) . '•••@' . $parts[1];
}

/**
 * Some imported accounts got their phone number as the password. True when this password is the
 * account's phone in any usual form.
 */
function sc_password_is_phone($user_id, $password) {
    $digits = preg_replace('/\D/', '', (string) $password);
    if ($digits === '' || $digits !== trim((string) $password, " +")) {
        return false;
    }
    $phone = sc_wabot_phone(get_user_meta($user_id, 'phone', true)) ?: sc_wabot_phone(get_user_meta($user_id, 'billing_phone', true));
    return $phone !== '' && sc_wabot_phone($digits) === $phone;
}
