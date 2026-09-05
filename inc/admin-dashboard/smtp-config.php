<?php
/**
 * SMTP Configuration
 *
 * Hooks into phpmailer_init to route wp_mail() through SMTP
 * instead of PHP's mail() function.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get SMTP settings (with sane defaults for Hostinger)
 */
function sc_get_smtp_settings() {
    return array(
        'enabled'    => (bool) get_option('sc_smtp_enabled', false),
        'host'       => get_option('sc_smtp_host', 'smtp.hostinger.com'),
        'port'       => (int) get_option('sc_smtp_port', 465),
        'secure'     => get_option('sc_smtp_secure', 'ssl'),
        'username'   => get_option('sc_smtp_username', ''),
        'password'   => sc_smtp_decrypt(get_option('sc_smtp_password', '')),
        'from_email' => get_option('sc_smtp_from_email', ''),
        'from_name'  => get_option('sc_smtp_from_name', get_bloginfo('name')),
    );
}

/**
 * Encrypt password for storage (basic obfuscation, not strong crypto)
 */
function sc_smtp_encrypt($plaintext) {
    if (empty($plaintext)) return '';
    $key = wp_salt('auth');
    $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
    if (function_exists('openssl_encrypt')) {
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return 'enc:' . $encrypted;
    }
    return base64_encode($plaintext);
}

function sc_smtp_decrypt($value) {
    if (empty($value)) return '';
    if (strpos($value, 'enc:') === 0 && function_exists('openssl_decrypt')) {
        $key = wp_salt('auth');
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $decrypted = openssl_decrypt(substr($value, 4), 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }
    $decoded = base64_decode($value, true);
    return $decoded !== false ? $decoded : $value;
}

/**
 * Configure PHPMailer to use SMTP
 */
add_action('phpmailer_init', 'sc_configure_phpmailer_smtp');
function sc_configure_phpmailer_smtp($phpmailer) {
    $smtp = sc_get_smtp_settings();

    if (!$smtp['enabled'] || empty($smtp['host']) || empty($smtp['username'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp['host'];
    $phpmailer->Port       = $smtp['port'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = $smtp['username'];
    $phpmailer->Password   = $smtp['password'];

    if ($smtp['secure'] === 'ssl') {
        $phpmailer->SMTPSecure = 'ssl';
    } elseif ($smtp['secure'] === 'tls') {
        $phpmailer->SMTPSecure = 'tls';
    } else {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }

    if (!empty($smtp['from_email'])) {
        $phpmailer->setFrom($smtp['from_email'], $smtp['from_name'], false);
    }

    $phpmailer->SMTPOptions = array(
        'ssl' => array(
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ),
    );

    $phpmailer->Timeout = 30;
}

/**
 * Force WP From address to match SMTP From
 */
add_filter('wp_mail_from', 'sc_smtp_force_from_email', 99);
function sc_smtp_force_from_email($email) {
    $smtp = sc_get_smtp_settings();
    if ($smtp['enabled'] && !empty($smtp['from_email'])) {
        return $smtp['from_email'];
    }
    return $email;
}

add_filter('wp_mail_from_name', 'sc_smtp_force_from_name', 99);
function sc_smtp_force_from_name($name) {
    $smtp = sc_get_smtp_settings();
    if ($smtp['enabled'] && !empty($smtp['from_name'])) {
        return $smtp['from_name'];
    }
    return $name;
}

/**
 * Capture SMTP errors for debugging
 */
add_action('wp_mail_failed', 'sc_log_mail_failure');
function sc_log_mail_failure($wp_error) {
    if (is_wp_error($wp_error)) {
        $error_data = $wp_error->get_error_data('wp_mail_failed');
        update_option('sc_smtp_last_error', array(
            'message' => $wp_error->get_error_message(),
            'data'    => is_array($error_data) ? $error_data : array(),
            'time'    => current_time('mysql'),
        ));
    }
}
