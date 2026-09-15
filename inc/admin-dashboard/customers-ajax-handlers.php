<?php
/**
 * Customers AJAX Handlers
 *
 * All customer-related AJAX handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if attendees module is enabled - if not, don't register any AJAX handlers
// Customers are part of the attendees module (users who have registered/attended events)
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('attendees')) {
    return;
}


/**
 * Get Customer Statistics
 */
function sc_get_customer_stats() {
    global $wpdb;

    $total = count(get_users(array(
        'role__in' => array('subscriber'),
        'fields' => 'ID'
    )));

    $active_by_user_id = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}sc_attendees
        WHERE user_id IS NOT NULL AND user_id > 0 AND status = 'active'
    ");

    $active_by_email = $wpdb->get_var("
        SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
        INNER JOIN {$wpdb->prefix}sc_attendees a ON a.email = u.user_email
        WHERE a.status = 'active'
    ");

    $active = max(intval($active_by_user_id), intval($active_by_email));

    $this_month = count(get_users(array(
        'role__in' => array('subscriber'),
        'fields' => 'ID',
        'date_query' => array(
            array('after' => current_time('Y-m-01'), 'inclusive' => true)
        )
    )));

    $total_registrations = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active'
    ");

    return array(
        'total' => intval($total),
        'active' => intval($active),
        'this_month' => intval($this_month),
        'total_registrations' => intval($total_registrations)
    );
}

// List, delete and export live in customers-dashboard.php.
require_once __DIR__ . '/customers-dashboard.php';

/**
 * Get Customer Details
 */
add_action('wp_ajax_sc_get_customer_details', 'sc_get_customer_details');
function sc_get_customer_details() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    $phone = get_user_meta($customer_id, 'billing_phone', true);
    if (empty($phone)) {
        $phone = get_user_meta($customer_id, 'phone', true);
    }

    $user_email = $user->user_email;
    $attendee_posts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}sc_attendees
        WHERE (user_id = %d OR email = %s) AND status = 'active'
        ORDER BY created_at DESC
    ", $customer_id, $user_email));

    $events = array();
    $tickets_used = 0;
    $tickets_pending = 0;

    foreach ($attendee_posts as $attendee) {
        $event_obj = class_exists('SC_Event') ? SC_Event::get($attendee->event_id) : null;
        $event_date = '';
        $event_title = 'Unknown Event';

        if ($event_obj) {
            $event_title = $event_obj->title;
            $event_date = $event_obj->start_date ? date('M j, Y', strtotime($event_obj->start_date)) : '';
        }

        $events[] = array(
            'event_id' => $attendee->event_id,
            'event_title' => $event_title,
            'event_date' => $event_date,
            'ticket_type' => $attendee->ticket_name ?: 'General',
            'ticket_status' => $attendee->checked_in_at ? 'used' : 'unused',
            'status' => $attendee->payment_status ?: 'pending',
            'registered_date' => date('M j, Y', strtotime($attendee->created_at))
        );

        if ($attendee->checked_in_at) {
            $tickets_used++;
        } else {
            $tickets_pending++;
        }
    }

    $role_name = 'Customer';
    if (!empty($user->roles)) {
        $role_name = ucfirst(str_replace(array('-', '_'), ' ', $user->roles[0]));
    }

    $customer = array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $phone,
        'registered' => date('M j, Y g:i A', strtotime($user->user_registered)),
        'role' => $role_name,
        'events_count' => count(array_unique(array_column($events, 'event_id'))),
        'tickets_used' => $tickets_used,
        'tickets_pending' => $tickets_pending
    );

    wp_send_json_success(array(
        'customer' => $customer,
        'events' => $events
    ));
}


/**
 * Bulk Email Customers
 */
add_action('wp_ajax_sc_bulk_email_customers', 'sc_bulk_email_customers');
function sc_bulk_email_customers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_ids = isset($_POST['customer_ids']) ? array_map('intval', $_POST['customer_ids']) : array();
    $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

    if (empty($customer_ids)) {
        wp_send_json_error(array('message' => __('No customers selected.', 'sc_events')));
    }

    if (empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Subject and message are required.', 'sc_events')));
    }

    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    $sent = 0;
    $failed = 0;

    foreach ($customer_ids as $customer_id) {
        $user = get_userdata($customer_id);
        if (!$user || empty($user->user_email)) {
            $failed++;
            continue;
        }

        $personalized_message = str_replace('{name}', $user->display_name, $message);
        $personalized_message = str_replace('{email}', $user->user_email, $personalized_message);

        $html_message = sc_get_email_template($platform_name, $personalized_message);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $platform_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );

        if (wp_mail($user->user_email, $subject, $html_message, $headers)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(__('Emails sent: %d, Failed: %d', 'sc_events'), $sent, $failed),
        'sent' => $sent,
        'failed' => $failed
    ));
}

/**
 * Send Email to Single Customer
 */
add_action('wp_ajax_sc_send_customer_email', 'sc_send_customer_email');
function sc_send_customer_email() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

    if (!$customer_id || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    $message = str_replace('{name}', $user->display_name, $message);
    $message = str_replace('{email}', $user->user_email, $message);

    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));
    $html_message = sc_get_email_template($platform_name, $message);

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $platform_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );

    if (wp_mail($user->user_email, $subject, $html_message, $headers)) {
        wp_send_json_success(array('message' => __('Email sent successfully!', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to send email.', 'sc_events')));
    }
}


/**
 * Get Email Template
 */
function sc_get_email_template($platform_name, $message) {
    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%); padding: 30px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 24px;">' . esc_html($platform_name) . '</h1>
            </div>
            <div style="padding: 40px 30px;">
                <div style="color: #555; line-height: 1.6; font-size: 15px;">
                    ' . wpautop($message) . '
                </div>
            </div>
            <div style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                <p style="margin: 0; color: #999; font-size: 13px;">
                    &copy; ' . date('Y') . ' ' . esc_html($platform_name) . '. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';
}
