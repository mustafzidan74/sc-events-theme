<?php
/**
 * SMTP Settings AJAX Handlers
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save SMTP settings
 */
add_action('wp_ajax_sc_save_smtp_settings', 'sc_save_smtp_settings_handler');
function sc_save_smtp_settings_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $enabled    = !empty($_POST['smtp_enabled']) ? 1 : 0;
    $host       = isset($_POST['smtp_host']) ? sanitize_text_field($_POST['smtp_host']) : '';
    $port       = isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 465;
    $secure     = isset($_POST['smtp_secure']) ? sanitize_text_field($_POST['smtp_secure']) : 'ssl';
    $username   = isset($_POST['smtp_username']) ? sanitize_text_field($_POST['smtp_username']) : '';
    $password   = isset($_POST['smtp_password']) ? $_POST['smtp_password'] : '';
    $from_email = isset($_POST['smtp_from_email']) ? sanitize_email($_POST['smtp_from_email']) : '';
    $from_name  = isset($_POST['smtp_from_name']) ? sanitize_text_field($_POST['smtp_from_name']) : '';

    if (!in_array($secure, array('ssl', 'tls', 'none'), true)) {
        $secure = 'ssl';
    }

    update_option('sc_smtp_enabled', $enabled);
    update_option('sc_smtp_host', $host);
    update_option('sc_smtp_port', $port);
    update_option('sc_smtp_secure', $secure);
    update_option('sc_smtp_username', $username);

    if (!empty($password) && $password !== '__KEEP_EXISTING__') {
        update_option('sc_smtp_password', sc_smtp_encrypt($password));
    }

    update_option('sc_smtp_from_email', $from_email);
    update_option('sc_smtp_from_name', $from_name);

    wp_send_json_success(array('message' => __('SMTP settings saved.', 'sc_events')));
}

/**
 * Send test email
 */
add_action('wp_ajax_sc_send_test_email', 'sc_send_test_email_handler');
function sc_send_test_email_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
    if (empty($to) || !is_email($to)) {
        wp_send_json_error(array('message' => __('Please provide a valid email address.', 'sc_events')));
    }

    delete_option('sc_smtp_last_error');

    $subject = sprintf('[%s] SMTP Test Email', get_bloginfo('name'));
    $message = "This is a test email from your SC Events platform.\r\n\r\n";
    $message .= "If you received this, your SMTP configuration is working correctly.\r\n\r\n";
    $message .= "Sent at: " . current_time('mysql') . "\r\n";
    $message .= "Site: " . home_url() . "\r\n";

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    $sent = wp_mail($to, $subject, $message, $headers);

    if ($sent) {
        wp_send_json_success(array(
            'message' => sprintf(__('Test email sent successfully to %s. Check your inbox (and spam folder).', 'sc_events'), $to),
        ));
    }

    $error = get_option('sc_smtp_last_error');
    $error_msg = is_array($error) && !empty($error['message']) ? $error['message'] : __('Unknown error. Check your SMTP settings.', 'sc_events');

    wp_send_json_error(array(
        'message' => __('Failed to send test email.', 'sc_events') . ' ' . $error_msg,
        'error_details' => $error,
    ));
}
