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
 * Get Customers with Pagination
 */
add_action('wp_ajax_sc_get_customers_paginated', 'sc_get_customers_paginated');
function sc_get_customers_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $offset = ($page - 1) * $per_page;

    $user_args = array(
        'role__in' => array('subscriber'),
        'number' => $per_page,
        'offset' => $offset,
        'orderby' => 'registered',
        'order' => 'DESC'
    );

    if (!empty($search)) {
        $user_args['search'] = '*' . $search . '*';
        $user_args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
    }

    if (!empty($date_from)) {
        $user_args['date_query'] = array(
            array('after' => $date_from, 'inclusive' => true)
        );
    }
    if (!empty($date_to)) {
        if (!isset($user_args['date_query'])) {
            $user_args['date_query'] = array();
        }
        $user_args['date_query'][] = array('before' => $date_to . ' 23:59:59', 'inclusive' => true);
    }

    // Filter by event
    if ($event_id > 0) {
        $attendees_by_user_id = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT user_id FROM {$wpdb->prefix}sc_attendees
            WHERE event_id = %d AND user_id IS NOT NULL AND user_id > 0 AND status = 'active'
        ", $event_id));

        $attendee_emails = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT email FROM {$wpdb->prefix}sc_attendees
            WHERE event_id = %d AND email IS NOT NULL AND email != '' AND status = 'active'
        ", $event_id));

        $user_ids_by_email = array();
        if (!empty($attendee_emails)) {
            $email_placeholders = implode(',', array_fill(0, count($attendee_emails), '%s'));
            $user_ids_by_email = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE user_email IN ($email_placeholders)",
                $attendee_emails
            ));
        }

        $all_user_ids = array_unique(array_merge(
            array_map('intval', $attendees_by_user_id),
            array_map('intval', $user_ids_by_email)
        ));

        if (!empty($all_user_ids)) {
            $user_args['include'] = array_filter($all_user_ids);
        } else {
            wp_send_json_success(array(
                'customers' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => 1,
                'stats' => sc_get_customer_stats()
            ));
            return;
        }
    }

    $user_query = new WP_User_Query($user_args);
    $users = $user_query->get_results();
    $total = $user_query->get_total();
    $pages = ceil($total / $per_page);

    $customers = array();
    foreach ($users as $user) {
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user->ID, 'phone', true);
        }

        $user_email = $user->user_email;
        $events_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT event_id) FROM {$wpdb->prefix}sc_attendees
            WHERE (user_id = %d OR email = %s) AND status = 'active'
        ", $user->ID, $user_email));

        $last_event_id = $wpdb->get_var($wpdb->prepare("
            SELECT event_id FROM {$wpdb->prefix}sc_attendees
            WHERE (user_id = %d OR email = %s) AND status = 'active'
            ORDER BY created_at DESC LIMIT 1
        ", $user->ID, $user_email));

        $last_event = '';
        if ($last_event_id && class_exists('SC_Event')) {
            $event_obj = SC_Event::get($last_event_id);
            if ($event_obj) {
                $last_event = $event_obj->title;
            }
        }

        $customers[] = array(
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'events_count' => intval($events_count),
            'last_event' => $last_event,
            'registered' => date('M j, Y', strtotime($user->user_registered))
        );
    }

    wp_send_json_success(array(
        'customers' => $customers,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page,
        'stats' => sc_get_customer_stats()
    ));
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
            array('after' => date('Y-m-01'), 'inclusive' => true)
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
 * Update Customer
 */
add_action('wp_ajax_sc_update_customer', 'sc_update_customer');
function sc_update_customer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    $user_data = array('ID' => $customer_id);

    if (isset($_POST['first_name'])) {
        $user_data['first_name'] = sanitize_text_field($_POST['first_name']);
    }

    if (isset($_POST['last_name'])) {
        $user_data['last_name'] = sanitize_text_field($_POST['last_name']);
    }

    if (isset($_POST['email'])) {
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'sc_events')));
        }

        $existing_user = get_user_by('email', $email);
        if ($existing_user && $existing_user->ID !== $customer_id) {
            wp_send_json_error(array('message' => __('Email address already in use.', 'sc_events')));
        }

        $user_data['user_email'] = $email;
    }

    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : $user->first_name;
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : $user->last_name;
    $user_data['display_name'] = trim($first_name . ' ' . $last_name);

    if (!empty($_POST['new_password'])) {
        $new_password = $_POST['new_password'];
        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters.', 'sc_events')));
        }
        $user_data['user_pass'] = $new_password;
    }

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    if (isset($_POST['phone'])) {
        $phone_code = isset($_POST['phone_code']) ? sanitize_text_field($_POST['phone_code']) : '+20';
        $phone_number = sanitize_text_field($_POST['phone']);
        $phone = $phone_number ? $phone_code . $phone_number : '';
        update_user_meta($customer_id, 'billing_phone', $phone);
        update_user_meta($customer_id, 'phone', $phone);
    }

    wp_send_json_success(array('message' => __('Customer updated successfully!', 'sc_events')));
}

/**
 * Delete Customer
 */
add_action('wp_ajax_sc_delete_customer', 'sc_delete_customer');
function sc_delete_customer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    if (in_array('administrator', $user->roles) || in_array('event_manager', $user->roles)) {
        wp_send_json_error(array('message' => __('Cannot delete admin or event manager accounts.', 'sc_events')));
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $result = wp_delete_user($customer_id);

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete customer.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Customer deleted successfully!', 'sc_events')));
}

/**
 * Bulk Delete Customers
 */
add_action('wp_ajax_sc_bulk_delete_customers', 'sc_bulk_delete_customers');
function sc_bulk_delete_customers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_ids = isset($_POST['customer_ids']) ? array_map('intval', $_POST['customer_ids']) : array();

    if (empty($customer_ids)) {
        wp_send_json_error(array('message' => __('No customers selected.', 'sc_events')));
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');

    $deleted = 0;
    $skipped = 0;

    foreach ($customer_ids as $customer_id) {
        $user = get_userdata($customer_id);
        if (!$user) {
            $skipped++;
            continue;
        }

        if (in_array('administrator', $user->roles) || in_array('event_manager', $user->roles)) {
            $skipped++;
            continue;
        }

        if (wp_delete_user($customer_id)) {
            $deleted++;
        } else {
            $skipped++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(__('%d customer(s) deleted. %d skipped.', 'sc_events'), $deleted, $skipped),
        'deleted' => $deleted,
        'skipped' => $skipped
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
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

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
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

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
 * Export Customers to CSV
 */
add_action('wp_ajax_sc_export_customers_csv', 'sc_export_customers_csv');
function sc_export_customers_csv() {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sc_dashboard_nonce')) {
        wp_die(__('Security check failed.', 'sc_events'));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(__('Permission denied.', 'sc_events'));
    }

    global $wpdb;

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $user_args = array(
        'role__in' => array('subscriber'),
        'number' => -1,
        'orderby' => 'registered',
        'order' => 'DESC'
    );

    if (!empty($search)) {
        $user_args['search'] = '*' . $search . '*';
        $user_args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
    }

    if ($event_id > 0) {
        $attendees = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT user_id FROM {$wpdb->prefix}sc_attendees
            WHERE event_id = %d AND user_id IS NOT NULL AND user_id > 0 AND status = 'active'
        ", $event_id));

        if (!empty($attendees)) {
            $user_args['include'] = array_map('intval', $attendees);
        } else {
            $user_args['include'] = array(0);
        }
    }

    $user_query = new WP_User_Query($user_args);
    $users = $user_query->get_results();

    $filename = 'customers_export_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, array('ID', 'Username', 'Display Name', 'First Name', 'Last Name', 'Email', 'Phone', 'Events Attended', 'Registered Date'));

    foreach ($users as $user) {
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user->ID, 'phone', true);
        }

        $events_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT event_id) FROM {$wpdb->prefix}sc_attendees
            WHERE (user_id = %d OR email = %s) AND status = 'active'
        ", $user->ID, $user->user_email));

        fputcsv($output, array(
            $user->ID,
            $user->user_login,
            $user->display_name,
            $user->first_name,
            $user->last_name,
            $user->user_email,
            $phone,
            $events_count,
            date('Y-m-d H:i:s', strtotime($user->user_registered))
        ));
    }

    fclose($output);
    exit;
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
