<?php
/**
 * Attendees AJAX Handlers
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if attendees module is enabled - if not, don't register any AJAX handlers
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('attendees')) {
    return;
}

require_once __DIR__ . '/attendees-query.php';

/**
 * Filter out payment type labels accidentally stored as coupon codes.
 * Returns the actual coupon code or empty string if it's a payment type label.
 */
if (!function_exists('sc_sanitize_coupon_display')) {
    function sc_sanitize_coupon_display($coupon_code) {
        if (empty($coupon_code)) return '';
        $invalid_codes = array('coupon', 'free', 'paid', 'cash', 'card', 'bank', 'transfer', 'كوبون', 'مجاني');
        if (in_array(strtolower(trim($coupon_code)), $invalid_codes)) {
            return '';
        }
        return $coupon_code;
    }
}

/**
 * Normalize Egyptian phone number to 11 digits starting with 01
 * Handles various formats: +20, 20, 201, 1xxx, 01xxx
 *
 * @param string $phone The phone number to normalize
 * @return string Normalized 11-digit phone number starting with 01
 */
function sc_normalize_egyptian_phone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Skip if empty
    if (empty($phone)) {
        return '';
    }

    // Handle different formats:
    // +201234567890 → 201234567890 → 01234567890
    // 201234567890 → 01234567890
    // 01234567890 → 01234567890 (already correct)
    // 1234567890 → 01234567890

    // If starts with 20 (country code), remove it
    if (strlen($phone) >= 12 && substr($phone, 0, 2) === '20') {
        $phone = substr($phone, 2);
    }

    // If starts with 1 (without leading 0), add the 0
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '1') {
        $phone = '0' . $phone;
    }

    // If still doesn't start with 0, add it
    if (substr($phone, 0, 1) !== '0') {
        $phone = '0' . $phone;
    }

    // Ensure it's exactly 11 digits
    if (strlen($phone) > 11) {
        // Take last 11 digits
        $phone = substr($phone, -11);
    }

    // Final validation: should be 11 digits starting with 01
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '01') {
        return $phone;
    }

    // If we can't normalize properly, return original cleaned version
    return $phone;
}

/**
 * Search WordPress users and attendees for attendee selection
 */
add_action('wp_ajax_sc_search_users', 'sc_search_users_handler');
function sc_search_users_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $search = sanitize_text_field($_POST['search'] ?? '');

    if (strlen($search) < 2) {
        wp_send_json_success(array('results' => array()));
    }

    global $wpdb;
    $result = array();
    $seen_emails = array();

    // 1. Search WordPress users using WP_User_Query
    $user_args = array(
        'role' => 'subscriber',
        'search' => '*' . $search . '*',
        'search_columns' => array('user_login', 'user_email', 'user_nicename', 'display_name'),
        'number' => 15,
        'orderby' => 'display_name',
        'order' => 'ASC'
    );

    $user_query = new WP_User_Query($user_args);
    $users = $user_query->get_results();

    foreach ($users as $user) {
        $phone = get_user_meta($user->ID, 'phone', true) ?: get_user_meta($user->ID, 'billing_phone', true);
        $company = get_user_meta($user->ID, 'company', true) ?: get_user_meta($user->ID, 'billing_company', true);

        $result[] = array(
            'ID' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'phone' => $phone ?: '',
            'company' => $company ?: ''
        );
        $seen_emails[strtolower($user->user_email)] = true;
    }

    // 2. Search in attendees table (for users who may not have WP accounts)
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $attendees = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT name, email, phone
        FROM {$attendees_table}
        WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)
        AND email IS NOT NULL AND email != ''
        ORDER BY name ASC
        LIMIT 15
    ", $search_like, $search_like, $search_like));

    foreach ($attendees as $attendee) {
        // Skip if we already have this email from WP users
        if (isset($seen_emails[strtolower($attendee->email)])) {
            continue;
        }

        $result[] = array(
            'ID' => 0, // No WP user ID
            'display_name' => $attendee->name ?: $attendee->email,
            'user_email' => $attendee->email,
            'phone' => $attendee->phone ?: '',
            'company' => ''
        );
        $seen_emails[strtolower($attendee->email)] = true;
    }

    // Sort by display_name and limit to 20
    usort($result, function($a, $b) {
        return strcasecmp($a['display_name'], $b['display_name']);
    });
    $result = array_slice($result, 0, 20);

    wp_send_json_success($result);
}

/**
 * Get single attendee (for attendees management page)
 * Uses custom tables only - no WP_Query fallback
 */
function sc_get_single_attendee() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id || !class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => 'Invalid attendee'));
    }

    // Get from custom table
    $sc_attendee = SC_Attendee::get($attendee_id);

    if (!$sc_attendee) {
        wp_send_json_error(array('message' => 'Attendee not found'));
    }

    // Get event info
    $sc_event = class_exists('SC_Event') ? SC_Event::get($sc_attendee->event_id) : null;
    $event_title = $sc_event ? $sc_event->title : '';

    $attendee = array(
        'id' => $sc_attendee->id,
        'ticket_id' => $sc_attendee->ticket_code,
        'event_id' => $sc_attendee->event_id,
        'event_title' => $event_title,
        'name' => $sc_attendee->name,
        'email' => $sc_attendee->email,
        'phone' => $sc_attendee->phone,
        'ticket_name' => $sc_attendee->ticket_name,
        'ticket_slug' => sanitize_title($sc_attendee->ticket_name),
        'ticket_price' => $sc_attendee->amount_paid,
        'status' => $sc_attendee->payment_status,
        'ticket_status' => $sc_attendee->checked_in ? 'used' : 'unused',
        'checkin_time' => $sc_attendee->checked_in_at,
        'coupon_used' => sc_sanitize_coupon_display($sc_attendee->coupon_code),
        'payment_type' => $sc_attendee->payment_method ?: 'free',
        'order_id' => $sc_attendee->order_id,
        'notes' => $sc_attendee->notes ?? '',
        'extra_fields' => $sc_attendee->extra_fields ?: array(),
        'created_at' => date('Y-m-d H:i', strtotime($sc_attendee->created_at)),
        'token' => ''
    );

    wp_send_json_success(array('attendee' => $attendee));
}
add_action('wp_ajax_sc_get_attendee', 'sc_get_single_attendee');

/**
 * Send ticket email to attendee
 * Uses Custom Tables only
 */
function sc_send_ticket_email($attendee_id, $event_id, $name, $email, $ticket_id) {
    // Get event details from custom table
    if (!class_exists('SC_Event')) {
        return false;
    }

    $sc_event = SC_Event::get($event_id);
    if (!$sc_event) {
        return false;
    }

    $event_title = $sc_event->title;
    $event_date = $sc_event->start_date;
    $event_time = $sc_event->start_time;
    $event_location = $sc_event->location;

    // Format event date/time
    $event_datetime = '';
    if ($event_date) {
        $event_datetime = date('F j, Y', strtotime($event_date));
        if ($event_time) {
            $event_datetime .= ' at ' . date('g:i A', strtotime($event_time));
        }
    }

    // Generate ticket download URL
    $ticket_url = add_query_arg(array(
        'sc_action' => 'download_ticket',
        'attendee_id' => $attendee_id,
        'code' => $ticket_id
    ), home_url('/'));

    // Email subject
    $subject = sprintf('Your Ticket for %s', $event_title);

    // Get site name
    $site_name = get_bloginfo('name');

    // Email body (HTML)
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .ticket-info { background-color: white; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; }
            .ticket-info h3 { margin-top: 0; color: #4CAF50; }
            .info-row { margin: 10px 0; }
            .info-label { font-weight: bold; color: #666; }
            .download-button { display: inline-block; background-color: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
            .download-button:hover { background-color: #45a049; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎟️ Your Event Ticket</h1>
            </div>

            <div class="content">
                <p>Dear ' . esc_html($name) . ',</p>

                <p>Thank you for registering! Your ticket for <strong>' . esc_html($event_title) . '</strong> has been confirmed.</p>

                <div class="ticket-info">
                    <h3>Event Details</h3>
                    <div class="info-row">
                        <span class="info-label">Event:</span> ' . esc_html($event_title) . '
                    </div>
                    ' . ($event_datetime ? '<div class="info-row"><span class="info-label">Date & Time:</span> ' . esc_html($event_datetime) . '</div>' : '') . '
                    ' . ($event_location ? '<div class="info-row"><span class="info-label">Location:</span> ' . esc_html($event_location) . '</div>' : '') . '
                    <div class="info-row">
                        <span class="info-label">Ticket ID:</span> <code>' . esc_html($ticket_id) . '</code>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Attendee:</span> ' . esc_html($name) . '
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span> ' . esc_html($email) . '
                    </div>
                </div>

                <div style="text-align: center;">
                    <a href="' . esc_url($ticket_url) . '" class="download-button">📥 Download Your Ticket</a>
                </div>

                <p><strong>Important:</strong> Please bring this ticket (printed or on your mobile device) to the event. You will need to show it for entry.</p>

                <p>If you have any questions, please contact us.</p>

                <p>We look forward to seeing you at the event!</p>

                <p>Best regards,<br>' . esc_html($site_name) . '</p>
            </div>

            <div class="footer">
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>&copy; ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    );

    // Send email
    $sent = wp_mail($email, $subject, $message, $headers);

    // Log the email attempt in custom table
    if ($sent && class_exists('SC_Attendee')) {
        SC_Attendee::update($attendee_id, array(
            'email_sent_at' => current_time('mysql'),
            'email_status' => 'sent'
        ));
    } elseif (class_exists('SC_Attendee')) {
        SC_Attendee::update($attendee_id, array(
            'email_status' => 'failed'
        ));
    }

    return $sent;
}

/**
 * Save attendee (create or update) - for attendees management page
 * Uses Custom Tables only - no WP Posts fallback
 */
function sc_save_single_attendee() {
    // Accept both nonce formats for compatibility
    $nonce_valid = false;
    if (isset($_POST['sc_attendee_nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['sc_attendee_nonce'], 'sc_attendee_action');
    } elseif (isset($_POST['nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce');
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Verify custom tables are available
    if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;
    $user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : 'new';
    $selected_user_id = isset($_POST['existing_user_id']) ? intval($_POST['existing_user_id']) : 0;

    // Accept both field name formats (name or attendee_name)
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : (isset($_POST['attendee_name']) ? sanitize_text_field($_POST['attendee_name']) : '');
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : (isset($_POST['attendee_email']) ? sanitize_email($_POST['attendee_email']) : '');

    // Handle phone
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    if (empty($phone) && isset($_POST['attendee_phone'])) {
        $phone_code = isset($_POST['attendee_phone_code']) ? sanitize_text_field($_POST['attendee_phone_code']) : '+20';
        $phone_number = sanitize_text_field($_POST['attendee_phone']);
        $phone = $phone_number ? $phone_code . $phone_number : '';
    }

    // Accept both field name formats for event_id
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : (isset($_POST['attendee_event']) ? intval($_POST['attendee_event']) : 0);

    // Get ticket_id directly or by name/slug
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $ticket_name = isset($_POST['attendee_ticket_name']) ? sanitize_text_field($_POST['attendee_ticket_name']) : '';
    $ticket_slug = isset($_POST['attendee_ticket_slug']) ? sanitize_text_field($_POST['attendee_ticket_slug']) : sanitize_title($ticket_name);
    $ticket_price = isset($_POST['ticket_price']) ? floatval($_POST['ticket_price']) : (isset($_POST['attendee_ticket_price']) ? floatval($_POST['attendee_ticket_price']) : 0);

    $payment_status = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : (isset($_POST['attendee_payment_status']) ? sanitize_text_field($_POST['attendee_payment_status']) : 'success');
    $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : (isset($_POST['attendee_payment_type']) ? sanitize_text_field($_POST['attendee_payment_type']) : 'free');
    $coupon = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : (isset($_POST['attendee_coupon']) ? sanitize_text_field($_POST['attendee_coupon']) : '');
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : (isset($_POST['attendee_notes']) ? sanitize_textarea_field($_POST['attendee_notes']) : '');

    // Auto-set payment type to 'coupon' when coupon code is provided
    if (!empty($coupon) && in_array($payment_type, array('free', 'paid'))) {
        $payment_type = 'coupon';
    }

    // Auto-set payment type to 'coupon' when coupon code is provided
    if (!empty($coupon) && in_array($payment_type, array('free', 'paid'))) {
        $payment_type = 'coupon';
    }
    $checkin = isset($_POST['attendee_checkin']) ? true : false;

    if (empty($name) || empty($email) || !$event_id) {
        wp_send_json_error(array('message' => 'Required fields missing'));
    }

    // Handle user creation/linking for new attendees
    $user_id = null;
    if ($attendee_id == 0) {
        if ($user_type === 'existing' && $selected_user_id > 0) {
            $user_id = $selected_user_id;
        } elseif ($user_type === 'new') {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                $user_id = $existing_user->ID;
            } else {
                $password = preg_replace('/[^0-9]/', '', $phone);
                if (empty($password)) {
                    $password = wp_generate_password(8, false);
                }

                $user_data = array(
                    'user_login' => $email,
                    'user_email' => $email,
                    'user_pass' => $password,
                    'display_name' => $name,
                    'first_name' => $name,
                    'role' => 'subscriber'
                );

                $new_user_id = wp_insert_user($user_data);

                if (!is_wp_error($new_user_id)) {
                    $user_id = $new_user_id;
                    update_user_meta($user_id, 'phone', $phone);
                    update_user_meta($user_id, 'billing_phone', $phone);
                }
            }
        }
    }

    // Get SC event - event_id IS the custom table ID now
    $sc_event = SC_Event::get($event_id);
    if (!$sc_event) {
        wp_send_json_error(array('message' => 'Event not found'));
    }

    // Get or find ticket ID
    $sc_ticket_id = null;
    $final_ticket_name = '';
    $final_ticket_price = $ticket_price;

    if (class_exists('SC_Ticket')) {
        $tickets = SC_Ticket::get_by_event($sc_event->id);
        if (!empty($tickets)) {
            // First try to find by ticket_id if provided
            if ($ticket_id > 0) {
                foreach ($tickets as $t) {
                    if ($t->id == $ticket_id) {
                        $sc_ticket_id = $t->id;
                        $final_ticket_name = $t->name;
                        $final_ticket_price = floatval($t->price);
                        break;
                    }
                }
            }
            // Then try by name/slug
            if (!$sc_ticket_id && ($ticket_name || $ticket_slug)) {
                foreach ($tickets as $t) {
                    if ($t->name === $ticket_name || $t->slug === $ticket_slug) {
                        $sc_ticket_id = $t->id;
                        $final_ticket_name = $t->name;
                        $final_ticket_price = floatval($t->price);
                        break;
                    }
                }
            }
            // Default to first ticket
            if (!$sc_ticket_id) {
                $sc_ticket_id = $tickets[0]->id;
                $final_ticket_name = $tickets[0]->name;
                $final_ticket_price = floatval($tickets[0]->price);
            }
        }
    }

    // Use found ticket name and price if not explicitly set
    if (empty($ticket_name)) {
        $ticket_name = $final_ticket_name;
    }
    if ($ticket_price == 0 && $final_ticket_price > 0) {
        $ticket_price = $final_ticket_price;
    }

    // Prepare extra fields
    $extra_fields = array();
    if (isset($_POST['extra_fields']) && is_array($_POST['extra_fields'])) {
        foreach ($_POST['extra_fields'] as $key => $value) {
            if (is_array($value)) {
                $extra_fields[sanitize_text_field($key)] = implode(', ', array_map('sanitize_text_field', $value));
            } else {
                $extra_fields[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }
    }

    $attendee_data = array(
        'event_id'       => $sc_event->id,
        'ticket_id'      => $sc_ticket_id,
        'user_id'        => $user_id,
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'ticket_name'    => $ticket_name ?: 'General',
        'ticket_price'   => $ticket_price,
        'payment_status' => $payment_status,
        'payment_method' => $payment_type,
        'coupon_code'    => $coupon,
        'notes'          => $notes,
        'checked_in'     => $checkin ? 1 : 0,
        'checked_in_at'  => $checkin ? current_time('mysql') : null,
        'extra_fields'   => $extra_fields,
        'status'         => 'active',
    );

    $is_new = false;

    if ($attendee_id > 0) {
        // Update existing attendee
        $sc_attendee = SC_Attendee::get($attendee_id);
        if ($sc_attendee) {
            SC_Attendee::update($attendee_id, $attendee_data);
            $sc_id = $attendee_id;
        } else {
            wp_send_json_error(array('message' => 'Attendee not found'));
        }
    } else {
        // Create new attendee
        $sc_id = SC_Attendee::create($attendee_data);
        $is_new = true;
    }

    if (!$sc_id) {
        wp_send_json_error(array('message' => 'Failed to save attendee'));
    }

    // Get the attendee to get ticket code
    $saved_attendee = SC_Attendee::get($sc_id);

    // Send email for new attendees
    if ($is_new && $saved_attendee) {
        sc_send_ticket_email_custom($saved_attendee, $sc_event);
    }

    // Auto-register in event sessions (if sessions module is active)
    if ($is_new && function_exists('sc_auto_register_attendee_in_sessions')) {
        sc_auto_register_attendee_in_sessions($sc_id, $sc_event->id);
    }

    wp_send_json_success(array(
        'message' => 'Attendee saved successfully',
        'attendee_id' => $sc_id,
        'ticket_code' => $saved_attendee ? $saved_attendee->ticket_code : ''
    ));
}
add_action('wp_ajax_sc_save_attendee', 'sc_save_single_attendee');

/**
 * Send ticket email for custom table attendee
 */
function sc_send_ticket_email_custom($attendee, $event) {
    if (!$attendee || !$event) {
        return false;
    }

    $ticket_url = add_query_arg(array(
        'sc_action' => 'download_ticket',
        'attendee_id' => $attendee->id,
        'code' => $attendee->ticket_code
    ), home_url('/'));

    $event_datetime = '';
    if ($event->start_date) {
        $event_datetime = date('F j, Y', strtotime($event->start_date));
        if ($event->start_time) {
            $event_datetime .= ' at ' . date('g:i A', strtotime($event->start_time));
        }
    }

    $site_name = get_bloginfo('name');
    $subject = sprintf('Your Ticket for %s', $event->title);

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .ticket-info { background-color: white; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; }
            .download-button { display: inline-block; background-color: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h1>Your Event Ticket</h1></div>
            <div class="content">
                <p>Dear ' . esc_html($attendee->name) . ',</p>
                <p>Your ticket for <strong>' . esc_html($event->title) . '</strong> has been confirmed.</p>
                <div class="ticket-info">
                    <p><strong>Event:</strong> ' . esc_html($event->title) . '</p>
                    ' . ($event_datetime ? '<p><strong>Date:</strong> ' . esc_html($event_datetime) . '</p>' : '') . '
                    <p><strong>Ticket Code:</strong> ' . esc_html($attendee->ticket_code) . '</p>
                </div>
                <div style="text-align: center;">
                    <a href="' . esc_url($ticket_url) . '" class="download-button">Download Your Ticket</a>
                </div>
            </div>
        </div>
    </body>
    </html>';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    );

    return wp_mail($attendee->email, $subject, $message, $headers);
}

/**
 * Delete single attendee - for attendees management page
 * Uses Custom Tables only
 */
function sc_delete_single_attendee() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => 'Invalid attendee'));
    }

    // Delete from custom table
    $sc_attendee = SC_Attendee::get($attendee_id);
    if (!$sc_attendee) {
        wp_send_json_error(array('message' => 'Attendee not found'));
    }

    $result = SC_Attendee::delete($attendee_id);
    if ($result) {
        wp_send_json_success(array('message' => 'Attendee deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete attendee'));
    }
}
add_action('wp_ajax_sc_delete_attendee', 'sc_delete_single_attendee');

/**
 * Bulk delete attendees
 * Uses Custom Tables only
 */
function sc_bulk_delete_attendees() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    $attendee_ids = isset($_POST['attendee_ids']) ? array_map('intval', $_POST['attendee_ids']) : array();

    if (empty($attendee_ids)) {
        wp_send_json_error(array('message' => 'No attendees selected'));
    }

    $deleted = 0;

    foreach ($attendee_ids as $attendee_id) {
        if (SC_Attendee::delete($attendee_id)) {
            $deleted++;
        }
    }

    wp_send_json_success(array('message' => "$deleted attendee(s) deleted", 'deleted_count' => $deleted));
}
add_action('wp_ajax_sc_bulk_delete_attendees', 'sc_bulk_delete_attendees');

/**
 * Check-in attendee
 * Uses Custom Tables only
 */
function sc_checkin_attendee() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    // Allow event_manager OR event_scanner
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => 'Invalid attendee'));
    }

    $result = SC_Attendee::check_in($attendee_id);
    if ($result) {
        wp_send_json_success(array('message' => 'Attendee checked-in successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to check-in attendee'));
    }
}
add_action('wp_ajax_sc_checkin_attendee', 'sc_checkin_attendee');

/**
 * Bulk check-in attendees
 * Uses Custom Tables only
 */
function sc_bulk_checkin_attendees() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!class_exists('SC_Attendee')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    $attendee_ids = isset($_POST['attendee_ids']) ? array_map('intval', $_POST['attendee_ids']) : array();

    if (empty($attendee_ids)) {
        wp_send_json_error(array('message' => 'No attendees selected'));
    }

    $checkedin = 0;

    foreach ($attendee_ids as $attendee_id) {
        if (SC_Attendee::check_in($attendee_id)) {
            $checkedin++;
        }
    }

    wp_send_json_success(array('message' => "$checkedin attendee(s) checked-in", 'checkedin_count' => $checkedin));
}
add_action('wp_ajax_sc_bulk_checkin_attendees', 'sc_bulk_checkin_attendees');

/**
 * Get tickets by event
 * Uses Custom Tables only
 */
function sc_get_tickets_by_event() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => 'Invalid event'));
    }

    $tickets = array();

    // Get tickets from custom tables
    if (class_exists('SC_Ticket') && class_exists('SC_Event')) {
        $sc_event = SC_Event::get($event_id);
        if ($sc_event) {
            $sc_tickets = SC_Ticket::get_by_event($sc_event->id);
            foreach ($sc_tickets as $ticket) {
                $tickets[] = array(
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'slug' => $ticket->slug,
                    'price' => floatval($ticket->price),
                    'price_formatted' => sc_format_price($ticket->price)
                );
            }
        }
    }

    // If no tickets found, add default
    if (empty($tickets)) {
        $tickets[] = array(
            'id' => 'general',
            'name' => 'General Admission',
            'slug' => 'general',
            'price' => 0,
            'price_formatted' => sc_format_price(0)
        );
    }

    wp_send_json_success(array('tickets' => $tickets));
}
add_action('wp_ajax_sc_get_tickets_by_event', 'sc_get_tickets_by_event');

/**
 * Get Event Extra Fields
 * Uses Custom Tables
 */
function sc_get_event_extra_fields() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => 'Invalid event'));
    }

    // Get extra fields from custom table
    $extra_fields = array();
    if (class_exists('SC_Event')) {
        $sc_event = SC_Event::get($event_id);
        if ($sc_event && !empty($sc_event->extra_fields)) {
            $extra_fields = is_string($sc_event->extra_fields)
                ? json_decode($sc_event->extra_fields, true)
                : $sc_event->extra_fields;
        }
    }

    if (!$extra_fields || !is_array($extra_fields)) {
        wp_send_json_success(array('fields' => array()));
        return;
    }

    wp_send_json_success(array('fields' => $extra_fields));
}
add_action('wp_ajax_sc_get_event_extra_fields', 'sc_get_event_extra_fields');

/**
 * Neutralise spreadsheet formulas in a CSV cell. Names and registration answers
 * come from the public form, so "=HYPERLINK(…)" must not run in Excel. Plain
 * signed numbers such as phone "+20100…" are left alone.
 */
function sc_csv_cell($value) {
    if (is_array($value)) {
        $value = implode(', ', $value);
    }
    $value = (string) $value;
    if ($value !== '' && (preg_match('/^[=@\t\r]/', $value) || preg_match('/^[+\-](?![\d\s().]*$)/', $value))) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Export attendees to CSV — exactly the rows the list shows for the same filters.
 *
 * Streams in batches so a full export never holds every attendee in memory.
 */
function sc_export_attendees_csv() {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sc_dashboard_nonce')) {
        wp_die('Security check failed');
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    @set_time_limit(300);
    $f = sc_attendees_read_filters($_GET);
    $batch = 1000;

    // The rows to export, as an id list when extra-field filters narrowed them in PHP.
    $ids = null;
    if ($f['extra_filters']) {
        $ids = array_map('intval', wp_list_pluck(sc_attendees_extra_filtered_rows($f), 'id'));
    }
    list($where, $values) = sc_attendees_where($f);

    $fetch = function ($offset, $columns) use ($wpdb, $f, $ids, $where, $values, $batch) {
        if ($ids !== null) {
            $chunk = array_slice($ids, $offset, $batch);
            if (!$chunk) {
                return array();
            }
            $rows = $wpdb->get_results($columns . ' WHERE a.id IN (' . implode(',', $chunk) . ')');
            $order = array_flip($chunk);
            usort($rows, function ($x, $y) use ($order) {
                return $order[(int) $x->id] - $order[(int) $y->id];
            });
            return $rows;
        }
        $sql = $columns . " WHERE {$where} ORDER BY a.{$f['orderby']} {$f['order']}, a.id DESC LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, array_merge($values, array($batch, $offset))));
    };

    // Pass 1: every extra-field label present, first spelling wins (labels vary in case).
    $labels = array();
    for ($offset = 0; ; $offset += $batch) {
        $rows = $fetch($offset, "SELECT a.id, a.extra_fields FROM {$wpdb->prefix}sc_attendees a");
        foreach ($rows as $row) {
            foreach (array_keys(sc_normalize_attendee_extra_fields($row->extra_fields)) as $label) {
                $key = mb_strtolower($label);
                if (!isset($labels[$key])) {
                    $labels[$key] = $label;
                }
            }
        }
        if (count($rows) < $batch) {
            break;
        }
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendees-' . gmdate('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fprintf($out, "sep=,
");

    fputcsv($out, array_map('sc_csv_cell', array_merge(array(
        'Attendee ID', 'Ticket Code', 'Event', 'Workshop', 'Name', 'Email', 'Phone', 'Ticket',
        'Ticket Price', 'Amount Paid', 'Payment Status', 'Payment Method', 'Coupon',
        'Checked In', 'Check-in Time', 'Certificate', 'Transaction ID', 'Registered At',
    ), array_values($labels))));

    // Pass 2: the rows.
    for ($offset = 0; ; $offset += $batch) {
        $rows = $fetch($offset, sc_attendees_select_sql());
        foreach ($rows as $att) {
            $extra = array_change_key_case(sc_normalize_attendee_extra_fields($att->extra_fields), CASE_LOWER);
            $line = array(
                $att->id,
                $att->ticket_code,
                $att->event_title,
                $att->workshop_title,
                $att->name,
                $att->email,
                $att->phone,
                $att->ticket_name,
                $att->ticket_price,
                $att->amount_paid,
                $att->payment_status,
                $att->payment_method,
                sc_sanitize_coupon_display($att->coupon_code),
                $att->checked_in ? 'yes' : 'no',
                $att->checked_in_at,
                (int) $att->has_certificate ? 'yes' : 'no',
                $att->transaction_id,
                $att->created_at,
            );
            foreach (array_keys($labels) as $key) {
                $line[] = isset($extra[$key]) ? $extra[$key] : '';
            }
            fputcsv($out, array_map('sc_csv_cell', $line));
        }
        if (count($rows) < $batch) {
            break;
        }
        flush();
    }

    fclose($out);
    exit;
}
add_action('wp_ajax_sc_export_attendees_csv', 'sc_export_attendees_csv');

/**
 * Import attendees from CSV
 * Uses Custom Tables only
 */
function sc_import_attendees_csv() {
    // Increase limits for large imports
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => 'No file uploaded or upload error'));
    }

    // Get event ID from POST (this is now the custom table ID)
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => 'Event ID is required'));
    }

    // Verify event exists
    $sc_event = SC_Event::get($event_id);
    if (!$sc_event) {
        wp_send_json_error(array('message' => 'Event not found'));
    }

    // Check if we should create users
    $create_users = isset($_POST['create_users']) && $_POST['create_users'] === '1';

    // Get event extra fields from custom table
    $extra_fields_config = array();
    if (!empty($sc_event->extra_fields)) {
        $extra_fields_config = is_string($sc_event->extra_fields)
            ? json_decode($sc_event->extra_fields, true)
            : $sc_event->extra_fields;
    }
    if (!is_array($extra_fields_config)) {
        $extra_fields_config = array();
    }

    // SECURITY: Validate file type
    $file_name = $_FILES['csv_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($file_ext !== 'csv') {
        wp_send_json_error(array('message' => __('Invalid file type. Only CSV files are allowed.', 'sc_events')));
    }

    // Validate MIME type
    $allowed_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
    $file_type = mime_content_type($_FILES['csv_file']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        wp_send_json_error(array('message' => __('Invalid file content. Please upload a valid CSV file.', 'sc_events')));
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['csv_file']['size'] > $max_size) {
        wp_send_json_error(array('message' => __('File too large. Maximum size is 5MB.', 'sc_events')));
    }

    // Validate it's an uploaded file
    $file = $_FILES['csv_file']['tmp_name'];
    if (!is_uploaded_file($file)) {
        wp_send_json_error(array('message' => __('Invalid file upload.', 'sc_events')));
    }

    // Read file content and convert encoding if needed
    $file_content = file_get_contents($file);

    // Remove BOM FIRST (before encoding detection) - handles both raw and double-encoded BOM
    $file_content = preg_replace('/^\xEF\xBB\xBF/', '', $file_content); // Raw UTF-8 BOM
    $file_content = preg_replace('/^ï»¿/', '', $file_content); // Double-encoded BOM (ï»¿)

    // Detect and convert encoding to UTF-8
    $encoding = mb_detect_encoding($file_content, ['UTF-8', 'ISO-8859-1', 'ISO-8859-6', 'ASCII'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $file_content = mb_convert_encoding($file_content, 'UTF-8', $encoding);
    }

    // Write converted content to temp file
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($temp_file, $file_content);

    // Open converted file for reading
    $handle = fopen($temp_file, 'r');
    if (!$handle) {
        wp_send_json_error(array('message' => 'Failed to open CSV file'));
    }

    $success_count = 0;
    $failed_count = 0;
    $updated_count = 0;
    $users_created = 0;
    $users_updated = 0;
    $errors = array();
    $row_num = 0;

    // Read header row to map columns
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        @unlink($temp_file);
        wp_send_json_error(array('message' => 'Invalid CSV format - no headers found'));
    }

    // Skip Excel "sep=" directive if present (fgetcsv parses "sep=," as multiple columns)
    // Also handle BOM prefix: EF BB BF
    $first_header = trim($headers[0]);
    $first_header = preg_replace('/^\xEF\xBB\xBF/', '', $first_header); // Remove BOM

    if (strpos(strtolower($first_header), 'sep=') === 0) {
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            @unlink($temp_file);
            wp_send_json_error(array('message' => 'Invalid CSV format - no headers found after sep= directive'));
        }
    }

    // Build column index map (case-insensitive)
    $column_map = array();
    foreach ($headers as $index => $header) {
        // Clean header from any remaining BOM or whitespace
        $clean_header = $header;
        $clean_header = preg_replace('/^\xEF\xBB\xBF/', '', $clean_header); // Remove UTF-8 BOM
        $clean_header = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', $clean_header);
        $clean_header = trim($clean_header);
        $column_map[strtolower($clean_header)] = $index;
    }

    // Process data rows
    while (($data = fgetcsv($handle)) !== false) {
        $row_num++;

        // Skip empty rows
        if (empty($data) || (count($data) === 1 && empty($data[0]))) {
            continue;
        }

        // Get values by column name safely using isset checks
        $name_idx = isset($column_map['name']) ? $column_map['name'] : -1;
        $email_idx = isset($column_map['email']) ? $column_map['email'] : -1;
        $phone_idx = isset($column_map['phone']) ? $column_map['phone'] : -1;
        $ticket_name_idx = isset($column_map['ticket name']) ? $column_map['ticket name'] : -1;
        $payment_status_idx = isset($column_map['payment status']) ? $column_map['payment status'] : -1;
        $status_idx = isset($column_map['status']) ? $column_map['status'] : -1;
        $ticket_status_idx = isset($column_map['ticket status']) ? $column_map['ticket status'] : -1;
        $payment_type_idx = isset($column_map['payment type']) ? $column_map['payment type'] : -1;
        $coupon_idx = isset($column_map['coupon code']) ? $column_map['coupon code'] : -1;

        $name = ($name_idx >= 0 && isset($data[$name_idx])) ? wp_kses_post(trim($data[$name_idx])) : '';
        $email = ($email_idx >= 0 && isset($data[$email_idx])) ? sanitize_email(trim($data[$email_idx])) : '';
        $phone_raw = ($phone_idx >= 0 && isset($data[$phone_idx])) ? sanitize_text_field(trim($data[$phone_idx])) : '';
        $ticket_name = ($ticket_name_idx >= 0 && isset($data[$ticket_name_idx])) ? wp_kses_post(trim($data[$ticket_name_idx])) : 'General';

        // Support both "Payment Status" (new) and "Status" (legacy) column names
        $status = '';
        if ($payment_status_idx >= 0 && isset($data[$payment_status_idx])) {
            $status = trim($data[$payment_status_idx]);
        }
        if (empty($status) && $status_idx >= 0 && isset($data[$status_idx])) {
            $status = trim($data[$status_idx]);
        }
        if (empty($status)) {
            $status = 'success';
        }
        $status = sanitize_text_field($status);

        $ticket_status = ($ticket_status_idx >= 0 && isset($data[$ticket_status_idx])) ? sanitize_text_field(trim($data[$ticket_status_idx])) : 'unused';
        $payment_type = ($payment_type_idx >= 0 && isset($data[$payment_type_idx])) ? sanitize_text_field(trim($data[$payment_type_idx])) : 'free';
        $coupon = ($coupon_idx >= 0 && isset($data[$coupon_idx])) ? sanitize_text_field(trim($data[$coupon_idx])) : '';

        // Normalize phone number for Egyptian format
        $phone = sc_normalize_egyptian_phone($phone_raw);

        // Validate required fields
        if (empty($name) || empty($email)) {
            $errors[] = "Row $row_num: Missing required fields (Name, Email)";
            $failed_count++;
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row $row_num: Invalid email address";
            $failed_count++;
            continue;
        }

        // Collect extra fields
        $extra_fields_data = array();
        foreach ($extra_fields_config as $field) {
            // Include all extra fields (show_attendee_form defaults to true if not set)
            if (!isset($field['show_attendee_form']) || $field['show_attendee_form'] !== false) {
                $field_label = isset($field['label']) ? $field['label'] : '';
                if (empty($field_label)) continue;

                $field_key = strtolower($field_label);

                // Get extra field value using isset checks
                $extra_idx = isset($column_map[$field_key]) ? $column_map[$field_key] : -1;
                $value = ($extra_idx >= 0 && isset($data[$extra_idx])) ? trim($data[$extra_idx]) : '';
                if (!empty($value)) {
                    $extra_fields_data[$field_label] = sanitize_text_field($value);
                }
            }
        }

        // Create or skip WordPress user if option is enabled
        $user_id = null;
        if ($create_users) {
            // Check if user already exists
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                // Skip existing user - just get their ID for attendee linking
                $user_id = $existing_user->ID;
                $users_updated++; // Count as "skipped" (reusing the counter)
            } else {
                // Create new user with email as username and normalized phone as password
                $password = !empty($phone) ? $phone : wp_generate_password(12, false);

                $user_data = array(
                    'user_login' => $email,
                    'user_email' => $email,
                    'user_pass' => $password,
                    'display_name' => $name,
                    'first_name' => $name,
                    'role' => 'subscriber'
                );

                $user_id = wp_insert_user($user_data);

                if (!is_wp_error($user_id)) {
                    // Save phone to user meta
                    update_user_meta($user_id, 'phone', $phone);
                    update_user_meta($user_id, 'billing_phone', $phone);
                    $users_created++;
                } else {
                    $errors[] = "Row $row_num: Failed to create user - " . $user_id->get_error_message();
                    $user_id = null;
                }
            }
        }

        // Check if attendee already exists by coupon code in custom table (for updates/re-imports)
        $existing_attendee_id = null;
        $is_update = false;

        if (!empty($coupon)) {
            global $wpdb;
            $table = SC_Attendee::get_table();
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE event_id = %d AND coupon_code = %s LIMIT 1",
                $event_id, $coupon
            ));
            if ($existing) {
                $existing_attendee_id = $existing->id;
                $is_update = true;
            }
        }

        // Prepare attendee data for custom table
        $attendee_data = array(
            'event_id'       => $event_id,
            'user_id'        => $user_id,
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'ticket_name'    => $ticket_name,
            'payment_status' => $status,
            'payment_method' => $payment_type,
            'coupon_code'    => $coupon,
            'checked_in'     => ($ticket_status === 'used') ? 1 : 0,
            'extra_fields'   => $extra_fields_data,
            'status'         => 'active',
        );

        // Create or update attendee in custom table
        if ($is_update && $existing_attendee_id) {
            // Update existing attendee
            SC_Attendee::update($existing_attendee_id, $attendee_data);
            $updated_count++;
        } else {
            // Create new attendee
            $new_id = SC_Attendee::create($attendee_data);
            if ($new_id) {
                $success_count++;

                // Update coupon usage count if coupon is used
                if (!empty($coupon) && class_exists('SC_Coupon')) {
                    SC_Coupon::increment_usage($coupon);
                }
            } else {
                $errors[] = "Row $row_num: Failed to create attendee";
                $failed_count++;
            }
        }
    }

    fclose($handle);
    @unlink($temp_file); // Clean up temp file

    $response = array(
        'success_count' => $success_count,
        'updated_count' => $updated_count,
        'failed_count' => $failed_count,
        'errors' => array_slice($errors, 0, 10) // Limit to first 10 errors
    );

    // Add user creation stats if enabled
    if ($create_users) {
        $response['users_created'] = $users_created;
        $response['users_skipped'] = $users_updated; // Renamed: users with existing emails are skipped
    }

    wp_send_json_success($response);
}
add_action('wp_ajax_sc_import_attendees_csv', 'sc_import_attendees_csv');

/**
 * Get attendees list with pagination
 * Uses Custom Tables only
 */
/**
 * Safety cap for how many attendees are loaded into PHP memory when applying
 * extra-field filters. Extra filters always require a single selected event,
 * so a normal event stays well below this.
 */
if (!defined('SC_EXTRA_FILTER_MAX')) {
    define('SC_EXTRA_FILTER_MAX', 20000);
}

/**
 * Normalize an attendee's stored extra_fields JSON into a flat map of
 * label => value. Handles both storage shapes used across the codebase:
 *   - indexed:     [{"label":"...","value":"..."}, ...]  (frontend registration)
 *   - associative: {"label":"value", ...}                (admin / payment path)
 *
 * @param mixed $raw JSON string or already-decoded array.
 * @return array Map of label => value.
 */
function sc_normalize_attendee_extra_fields($raw) {
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw) || empty($raw)) {
        return array();
    }

    // Associative {label: value} — use as-is.
    $keys = array_keys($raw);
    $is_indexed = ($keys === range(0, count($raw) - 1));
    if (!$is_indexed) {
        return $raw;
    }

    // Indexed [{label, value}, ...] — flatten to {label: value}.
    $map = array();
    foreach ($raw as $item) {
        if (is_array($item) && isset($item['label'])) {
            $map[$item['label']] = isset($item['value']) ? $item['value'] : '';
        }
    }
    return $map;
}

/**
 * Check whether an attendee's extra-field values match ALL requested filters.
 *
 * @param array $map     label => value map (from sc_normalize_attendee_extra_fields()).
 * @param array $filters List of {label, type, value}. `value` is a string, or an
 *                       array for a checkbox with options (contains-all semantics).
 * @return bool True only if every filter condition is satisfied (AND).
 */
function sc_attendee_matches_extra_filters($map, $filters) {
    // Labels are matched case-insensitively: stored rows carry both
    // "Academic level" and "Academic Level" for the same field.
    $map = array_change_key_case((array) $map, CASE_LOWER);

    foreach ($filters as $f) {
        $label = isset($f['label']) ? mb_strtolower($f['label']) : '';
        $type  = isset($f['type']) ? $f['type'] : 'text';
        $value = isset($f['value']) ? $f['value'] : '';

        $stored = '';
        if ($label !== '' && isset($map[$label])) {
            $stored = is_array($map[$label]) ? implode(', ', $map[$label]) : (string) $map[$label];
        }

        switch ($type) {
            case 'select':
            case 'radio':
                if ($value === '') {
                    break; // "All" — no constraint.
                }
                if (strcasecmp(trim($stored), trim((string) $value)) !== 0) {
                    return false;
                }
                break;

            case 'checkbox':
                if (is_array($value)) {
                    // Checkbox with options: stored value must contain every chosen option.
                    $parts = array_map('trim', preg_split('/[،,]/u', $stored));
                    foreach ($value as $opt) {
                        $found = false;
                        foreach ($parts as $p) {
                            if (strcasecmp($p, trim($opt)) === 0) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            return false;
                        }
                    }
                } else {
                    // Simple checkbox: checked vs not-checked.
                    $is_checked   = !in_array(strtolower(trim($stored)), array('', '0', 'no', 'off', 'false'), true);
                    $want_checked = ($value === 'checked');
                    if ($is_checked !== $want_checked) {
                        return false;
                    }
                }
                break;

            default: // text, email, number, textarea — case-insensitive "contains".
                if ($value === '') {
                    break;
                }
                if (stripos($stored, (string) $value) === false) {
                    return false;
                }
        }
    }
    return true;
}

add_action('wp_ajax_sc_get_attendees_paginated', 'sc_get_attendees_paginated');
function sc_get_attendees_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page = max(1, absint($_POST['page'] ?? 1));
    // certificate-issue asks for up to 1000 at once; the list uses 25–100.
    $per_page = min(max(1, absint($_POST['per_page'] ?? 20)), 1000);
    $filters = sc_attendees_read_filters($_POST);

    $result = sc_attendees_page($filters, $page, $per_page);
    $scans = sc_attendees_scan_summary(wp_list_pluck($result['rows'], 'id'));
    $visible_event_statuses = array('publish', 'draft', 'completed');

    $attendees = array();
    foreach ($result['rows'] as $att) {
        $scan = isset($scans[(int) $att->id]) ? $scans[(int) $att->id] : null;
        $tracking = (int) $att->attendance_tracking === 1;

        $attendees[] = array(
            'id'               => (int) $att->id,
            'ticket_id'        => $att->ticket_code ?: 'SC' . str_pad($att->id, 6, '0', STR_PAD_LEFT),
            'event_id'         => (int) $att->event_id,
            'event_title'      => $att->event_title ?: __('Unknown Event', 'sc_events'),
            'event_deleted'    => !$att->event_title || !in_array($att->event_status, $visible_event_statuses, true),
            'workshop_id'      => $att->workshop_id ? (int) $att->workshop_id : null,
            'workshop_title'   => $att->workshop_title ?: '',
            'name'             => $att->name,
            'email'            => $att->email,
            'phone'            => $att->phone,
            'ticket_name'      => $att->ticket_name,
            'ticket_type'      => $att->ticket_name,
            'status'           => $att->payment_status ?: 'success',
            'attendee_status'  => $att->status,
            'ticket_status'    => $att->checked_in ? 'used' : 'unused',
            'checked_in'       => (bool) $att->checked_in,
            'checkin_time'     => $att->checked_in_at ? mysql2date('Y-m-d H:i', $att->checked_in_at) : '',
            'coupon_used'      => sc_sanitize_coupon_display($att->coupon_code),
            'payment_type'     => $att->payment_method ?: 'free',
            'amount_paid'      => (float) $att->amount_paid,
            'order_id'         => $att->order_id,
            'created_at'       => $att->created_at ? mysql2date('Y-m-d H:i', $att->created_at) : '',
            'tracking_enabled' => $tracking,
            'scan_count'       => $scan ? (int) $scan->scans : 0,
            'last_checkin'     => $scan && $scan->last_in ? mysql2date('Y-m-d H:i', $scan->last_in) : '',
            'last_checkout'    => $scan && $scan->last_out ? mysql2date('Y-m-d H:i', $scan->last_out) : '',
            'has_certificate'  => (bool) (int) $att->has_certificate,
        );
    }

    $response = array(
        'attendees'    => $attendees,
        'total'        => $result['total'],
        'pages'        => (int) ceil($result['total'] / $per_page),
        'current_page' => $page,
        'per_page'     => $per_page,
    );
    if (!empty($_POST['with_counts'])) {
        $response['counts'] = sc_attendees_counts($filters);
    }

    wp_send_json_success($response);
}

/**
 * Get attendees for bulk email
 * Uses Custom Tables only
 */
add_action('wp_ajax_sc_get_bulk_email_attendees', 'sc_get_bulk_email_attendees');
function sc_get_bulk_email_attendees() {
	check_ajax_referer('sc_dashboard_nonce', 'nonce');

	if (!SC_Event_Manager_Dashboard::is_event_manager()) {
		wp_send_json_error(array('message' => 'Unauthorized'));
	}

	if (!class_exists('SC_Attendee')) {
		wp_send_json_error(array('message' => 'Custom tables not available'));
	}

	$target = sanitize_text_field($_POST['target'] ?? 'all');
	$event_id = intval($_POST['event_id'] ?? 0);

	// Build query args for custom tables
	$args = array(
		'limit' => 100000,
		'payment_status' => array('success', 'completed')
	);

	// Filter by event if specified
	if ($target === 'event' && $event_id > 0) {
		$args['event_id'] = $event_id;
	}

	// Get attendees from custom table
	$result = SC_Attendee::get_list($args);
	$sc_attendees = $result['attendees'];

	$attendees = array();
	foreach ($sc_attendees as $att) {
		$attendees[] = array(
			'id' => $att->id,
			'name' => $att->name,
			'email' => $att->email
		);
	}

	wp_send_json_success(array('attendees' => $attendees));
}

/**
 * Send email to single attendee
 * Uses Custom Tables only
 */
add_action('wp_ajax_sc_send_attendee_email', 'sc_send_attendee_email');
function sc_send_attendee_email() {
	check_ajax_referer('sc_dashboard_nonce', 'nonce');

	if (!SC_Event_Manager_Dashboard::is_event_manager()) {
		wp_send_json_error(array('message' => 'Unauthorized'));
	}

	if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
		wp_send_json_error(array('message' => 'Custom tables not available'));
	}

	$attendee_id = intval($_POST['attendee_id'] ?? 0);
	$subject = sanitize_text_field($_POST['subject'] ?? '');
	$message = wp_kses_post($_POST['message'] ?? '');

	if (!$attendee_id || !$subject || !$message) {
		wp_send_json_error(array('message' => 'Missing required fields'));
	}

	// Get attendee details from custom table
	$sc_attendee = SC_Attendee::get($attendee_id);
	if (!$sc_attendee) {
		wp_send_json_error(array('message' => 'Attendee not found'));
	}

	$name = $sc_attendee->name;
	$email = $sc_attendee->email;
	$ticket_id = $sc_attendee->ticket_code;

	if (!$email) {
		wp_send_json_error(array('message' => 'Attendee email not found'));
	}

	// Get event details from custom table
	$sc_event = SC_Event::get($sc_attendee->event_id);
	$event_title = $sc_event ? $sc_event->title : '';
	$event_date = $sc_event ? $sc_event->start_date : '';
	$event_location = $sc_event ? $sc_event->location : '';

	// Format event date
	$event_date_formatted = $event_date ? date_i18n('F j, Y', strtotime($event_date)) : '';

	// Generate secure verification URL with domain (same as ticket view page)
	$ticket_view_url = home_url('/ticket-view/?attendee_id=' . $attendee_id . '&ticket_code=' . urlencode($ticket_id));

	// Generate QR code with secure verification URL
	$qr_data_encoded = urlencode($ticket_view_url);
	$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . $qr_data_encoded;
	$qr_image_html = '<div style="text-align: center; margin: 20px 0;">
		<img src="' . esc_url($qr_url) . '" alt="QR Code" style="max-width: 200px; display: block; margin: 0 auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
		<p style="margin-top: 10px; color: #666; font-size: 12px;"><strong>Ticket ID:</strong> ' . esc_html($ticket_id) . '</p>
	</div>';
	$my_account_url = home_url('/my-account/');

	// Create download link HTML
	$download_link_html = '<div style="text-align: center; margin: 20px 0;">
		<a href="' . esc_url($ticket_view_url) . '" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">
			<span style="margin-right: 8px;">🎫</span> View & Download Ticket
		</a>
	</div>';

	// Create my account link HTML
	$my_account_link_html = '<div style="text-align: center; margin: 20px 0;">
		<a href="' . esc_url($my_account_url) . '" style="display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">
			<span style="margin-right: 8px;">👤</span> Go to My Account
		</a>
	</div>';

	// Replace variables in message
	$message = str_replace(
		array('{name}', '{email}', '{ticket_id}', '{event_title}', '{event_date}', '{event_location}', '{qr}', '{download_link}', '{my_account}'),
		array($name, $email, $ticket_id, $event_title, $event_date_formatted, $event_location, $qr_image_html, $download_link_html, $my_account_link_html),
		$message
	);

	// Replace variables in subject
	$subject = str_replace(
		array('{name}', '{email}', '{ticket_id}', '{event_title}', '{event_date}', '{event_location}'),
		array($name, $email, $ticket_id, $event_title, $event_date_formatted, $event_location),
		$subject
	);

	// Prepare email
	$platform_name = get_option('sc_platform_name', get_bloginfo('name'));

	// HTML email headers
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $platform_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
	);

	// HTML email template
	$html_message = '
	<!DOCTYPE html>
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
				<h2 style="color: #333; margin-top: 0;">' . esc_html($subject) . '</h2>
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

	// Send email
	$sent = wp_mail($email, $subject, $html_message, $headers);

	if ($sent) {
		wp_send_json_success(array('message' => 'Email sent successfully'));
	} else {
		wp_send_json_error(array('message' => 'Failed to send email'));
	}
}

/**
 * Sync attendees to users - create user accounts for attendees without one
 * Uses Custom Tables only
 */
add_action('wp_ajax_sc_sync_attendees_users', 'sc_sync_attendees_users');
function sc_sync_attendees_users() {
    // Increase limits for large operations
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    // Security check
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
        wp_send_json_error(array('message' => 'Custom tables not available'));
    }

    global $wpdb;
    $table = SC_Attendee::get_table();

    // Find all attendees without a linked user account
    $attendees_without_users = $wpdb->get_results(
        "SELECT * FROM $table WHERE (user_id IS NULL OR user_id = 0) AND status = 'active'"
    );

    $created_users = array();
    $skipped = 0;
    $errors = array();

    foreach ($attendees_without_users as $att) {
        $attendee_id = $att->id;
        $name = $att->name;
        $email = $att->email;
        $phone = $att->phone;
        $event_id = $att->event_id;

        // Get event title
        $sc_event = SC_Event::get($event_id);
        $event_title = $sc_event ? $sc_event->title : '';

        // Skip if no email
        if (empty($email)) {
            $skipped++;
            continue;
        }

        // Check if user with this email already exists
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // Link attendee to existing user
            SC_Attendee::update($attendee_id, array('user_id' => $existing_user->ID));
            $skipped++;
            continue;
        }

        // Clean phone number for password (remove non-digits)
        $password = preg_replace('/[^0-9]/', '', $phone);
        if (empty($password) || strlen($password) < 6) {
            $password = wp_generate_password(8, false);
        }

        // Create new user
        $user_data = array(
            'user_login' => $email,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name,
            'first_name' => $name,
            'role' => 'subscriber'
        );

        $new_user_id = wp_insert_user($user_data);

        if (is_wp_error($new_user_id)) {
            $errors[] = "Failed to create user for {$email}: " . $new_user_id->get_error_message();
            continue;
        }

        // Save phone to user meta
        update_user_meta($new_user_id, 'phone', $phone);
        update_user_meta($new_user_id, 'billing_phone', $phone);

        // Link attendee to new user in custom table
        SC_Attendee::update($attendee_id, array('user_id' => $new_user_id));

        // Store created user info for export
        $created_users[] = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'event' => $event_title,
            'attendee_id' => $attendee_id,
            'user_id' => $new_user_id
        );
    }

    wp_send_json_success(array(
        'created_count' => count($created_users),
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'users' => $created_users,
        'errors' => array_slice($errors, 0, 10)
    ));
}

/**
 * Search attendee by phone number
 * Uses Custom Tables only
 */
add_action('wp_ajax_sc_search_attendee_by_phone', 'sc_search_attendee_by_phone');
function sc_search_attendee_by_phone() {
	check_ajax_referer('sc_dashboard_nonce', 'nonce');

	// Allow event_scanner role to use this
	if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('scan_tickets')) {
		wp_send_json_error(array('message' => 'Unauthorized'));
	}

	if (!class_exists('SC_Attendee') || !class_exists('SC_Event')) {
		wp_send_json_error(array('message' => 'Custom tables not available'));
	}

	$phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
	$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

	if (empty($phone)) {
		wp_send_json_error(array('message' => 'Phone number is required'));
	}

	// Clean phone number - remove spaces and non-numeric chars except +
	$phone_clean = preg_replace('/[^0-9+]/', '', $phone);

	// Search in custom table
	global $wpdb;
	$table = SC_Attendee::get_table();

	$sql = "SELECT * FROM $table WHERE phone LIKE %s";
	$values = array('%' . $wpdb->esc_like($phone_clean) . '%');

	if ($event_id > 0) {
		$sql .= " AND event_id = %d";
		$values[] = $event_id;
	}

	// Scanners limited to some events must not look up other events' attendees.
	$scope = sc_get_scanner_scope();
	if (!$scope['full']) {
		if (empty($scope['events'])) {
			wp_send_json_success(array('attendees' => array()));
		}
		$sql .= ' AND event_id IN (' . implode(',', array_fill(0, count($scope['events']), '%d')) . ')';
		$values = array_merge($values, $scope['events']);
	}

	$sql .= " LIMIT 20";

	$sc_attendees = $wpdb->get_results($wpdb->prepare($sql, $values));
	$attendees = array();

	foreach ($sc_attendees as $att) {
		// Get event title
		$sc_event = SC_Event::get($att->event_id);
		$event_title = $sc_event ? $sc_event->title : '';

		// Get extra fields
		$extra_fields = array();
		if (!empty($att->extra_fields)) {
			$extra_fields = is_string($att->extra_fields) ? json_decode($att->extra_fields, true) : $att->extra_fields;
		}
		if (!is_array($extra_fields)) {
			$extra_fields = array();
		}

		$attendees[] = array(
			'id' => $att->id,
			'name' => $att->name,
			'email' => $att->email,
			'phone' => $att->phone,
			'ticket_id' => $att->ticket_code,
			'ticket_status' => $att->checked_in ? 'used' : 'unused',
			'event_id' => $att->event_id,
			'event_title' => $event_title,
			'extra_fields' => $extra_fields
		);
	}

	wp_send_json_success(array('attendees' => $attendees));
}

/**
 * Email All Attendees for an Event
 */
add_action('wp_ajax_sc_email_attendees', 'sc_ajax_email_attendees');
function sc_ajax_email_attendees() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = wp_kses_post($_POST['message']);

    if (!$event_id || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'sc_events')));
    }

    // Load reports functions to get attendees
    require_once get_template_directory() . '/inc/admin-dashboard/reports-functions.php';

    // Get event statistics (includes all attendees)
    $stats = sc_get_event_statistics($event_id);

    if (empty($stats['attendees'])) {
        wp_send_json_error(array('message' => __('No attendees found for this event.', 'sc_events')));
    }

    // Get event details for email template from Custom Tables
    $event = class_exists('SC_Event') ? SC_Event::get($event_id) : null;
    $event_name = $event ? $event->title : __('Your Event', 'sc_events');
    $event_date = $event ? $event->start_date : null;
    if ($event_date) {
        $event_date = date('F j, Y', strtotime($event_date));
    } else {
        $event_date = __('TBA', 'sc_events');
    }

    $sent_count = 0;
    $failed_emails = array();

    // Send email to each attendee
    foreach ($stats['attendees'] as $attendee) {
        $name = !empty($attendee['name']) ? $attendee['name'] : __('Attendee', 'sc_events');
        $email = !empty($attendee['email']) ? $attendee['email'] : '';
        $ticket_type = !empty($attendee['ticket']) ? $attendee['ticket'] : __('Standard Ticket', 'sc_events');

        // Skip if no email
        if (empty($email) || !is_email($email)) {
            $failed_emails[] = $name . ' (invalid email)';
            continue;
        }

        // Replace variables in message
        $personalized_message = str_replace(
            array('{name}', '{event_name}', '{ticket_type}', '{event_date}'),
            array($name, $event_name, $ticket_type, $event_date),
            $message
        );

        // Build HTML email
        $email_html = sc_build_email_template($name, $personalized_message, $event_name);

        // Set email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        );

        // Send email
        $sent = wp_mail($email, $subject, $email_html, $headers);

        if ($sent) {
            $sent_count++;
        } else {
            $failed_emails[] = $name . ' (' . $email . ')';
        }
    }

    // Return results
    if ($sent_count > 0) {
        $success_message = sprintf(
            __('Successfully sent %d email(s).', 'sc_events'),
            $sent_count
        );

        if (!empty($failed_emails)) {
            $success_message .= ' ' . sprintf(
                __('Failed to send to %d recipient(s).', 'sc_events'),
                count($failed_emails)
            );
        }

        wp_send_json_success(array(
            'message' => $success_message,
            'sent_count' => $sent_count,
            'failed_count' => count($failed_emails)
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Failed to send any emails. Please check your mail configuration.', 'sc_events')
        ));
    }
}

/**
 * Build Beautiful HTML Email Template
 */
function sc_build_email_template($name, $message, $event_name) {
    // Get platform colors
    $primary_color = get_option('sc_primary_color', '#17a2b8');
    $secondary_color = get_option('sc_secondary_color', '#6c757d');

    // Get platform logo
    $logo_id = get_option('sc_platform_logo');
    $logo_url = '';
    if ($logo_id) {
        $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
    }

    // Get platform name
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    // Build HTML template
    $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($event_name) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header with Gradient -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, ' . esc_attr($secondary_color) . ' 100%); padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($platform_name) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;">' : '') . '
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">' . esc_html($event_name) . '</h1>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 30px 40px 20px;">
                            <p style="margin: 0 0 20px; font-size: 16px; color: #333333; line-height: 1.6;">
                                <strong>Hello ' . esc_html($name) . ',</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Message Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <div style="font-size: 15px; color: #555555; line-height: 1.8;">
                                ' . nl2br(wp_kses_post($message)) . '
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0 0 10px; font-size: 14px; color: #777777; text-align: center;">
                                Best regards,<br>
                                <strong>' . esc_html($platform_name) . '</strong>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 12px; color: #999999; text-align: center;">
                                This email was sent to you because you registered for <strong>' . esc_html($event_name) . '</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Email Footer -->
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 20px;">
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                &copy; ' . date('Y') . ' ' . esc_html($platform_name) . '. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    return $html;
}
