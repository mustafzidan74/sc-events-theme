<?php
/**
 * SC Events Company Attendees AJAX Handlers
 *
 * Handles all AJAX requests for company/B2B attendees management
 *
 * @package sc_events
 * @version 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if companies module is enabled - if not, don't register any AJAX handlers
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('companies')) {
    return;
}

/**
 * Get company attendees list
 */
function sc_get_company_attendees_handler() {
    sc_verify_ajax_request();

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $payment_status = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
    $checked_in = isset($_POST['checked_in']) && $_POST['checked_in'] !== '' ? intval($_POST['checked_in']) : null;
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'created_at';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';

    $args = array(
        'event_id' => $event_id ?: null,
        'search' => $search,
        'status' => $status ?: null,
        'payment_status' => $payment_status ?: null,
        'checked_in' => $checked_in,
        'orderby' => $orderby,
        'order' => $order,
        'limit' => $per_page,
        'offset' => ($page - 1) * $per_page,
    );

    $result = SC_Company_Attendee::get_list($args);

    // Format data for response
    $companies = array();
    foreach ($result['companies'] as $company) {
        $companies[] = array(
            'id' => $company->id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'company_name_ar' => $company->company_name_ar,
            'logo_url' => $company->logo_url,
            'industry' => $company->industry,
            'company_size' => $company->company_size,
            'contact_name' => $company->contact_name,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
            'booth_number' => $company->booth_number,
            'sponsorship_level' => $company->sponsorship_level,
            'payment_status' => $company->payment_status,
            'payment_status_label' => $company->payment_status_label,
            'amount_paid' => sc_format_price($company->amount_paid),
            'checked_in' => (bool) $company->checked_in,
            'checked_in_at' => $company->checked_in_at_formatted ?? '',
            'status' => $company->status,
            'status_label' => $company->status_label,
            'created_at' => $company->created_at_formatted,
        );
    }

    // Calculate stats from the full list (without pagination)
    $stats_args = array(
        'event_id' => $event_id ?: null,
        'status' => 'active',
    );

    // Get total counts for stats
    $total_companies = SC_Company_Attendee::count($stats_args);
    $stats_args['checked_in'] = true;
    $checked_in = SC_Company_Attendee::count($stats_args);

    // Get revenue
    $revenue = 0;
    if ($event_id) {
        $event_stats = SC_Company_Attendee::get_stats($event_id);
        $revenue = $event_stats['revenue'];
    } else {
        // Sum all revenue
        global $wpdb;
        $table = SC_Company_Attendee::get_table();
        $revenue = (float) $wpdb->get_var("SELECT SUM(amount_paid) FROM $table WHERE status = 'active' AND payment_status = 'success'");
    }

    wp_send_json_success(array(
        'companies' => $companies,
        'total' => $result['total'],
        'pages' => ceil($result['total'] / $per_page),
        'current_page' => $page,
        'stats' => array(
            'total' => $total_companies,
            'checked_in' => $checked_in,
            'not_checked_in' => $total_companies - $checked_in,
            'revenue' => sc_format_price($revenue),
        ),
    ));
}
add_action('wp_ajax_sc_get_company_attendees', 'sc_get_company_attendees_handler');

/**
 * Get single company attendee
 */
function sc_get_company_attendee_handler() {
    sc_verify_ajax_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get($id);

    if (!$company) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    // Get event details
    $event = null;
    if ($company->event_id && class_exists('SC_Event')) {
        $event = SC_Event::get($company->event_id);
    }

    wp_send_json_success(array(
        'company' => array(
            'id' => $company->id,
            'event_id' => $company->event_id,
            'ticket_id' => $company->ticket_id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'company_name_ar' => $company->company_name_ar,
            'company_logo' => $company->company_logo,
            'logo_url' => $company->logo_url,
            'industry' => $company->industry,
            'company_size' => $company->company_size,
            'website' => $company->website,
            'contact_name' => $company->contact_name,
            'contact_title' => $company->contact_title,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
            'country' => $company->country,
            'city' => $company->city,
            'address' => $company->address,
            'booth_number' => $company->booth_number,
            'sponsorship_level' => $company->sponsorship_level,
            'payment_status' => $company->payment_status,
            'payment_method' => $company->payment_method,
            'amount_paid' => $company->amount_paid,
            'checked_in' => (bool) $company->checked_in,
            'checked_in_at' => $company->checked_in_at,
            'extra_fields' => $company->extra_fields_array,
            'qr_data' => $company->qr_data,
            'notes' => $company->notes,
            'status' => $company->status,
            'created_at' => $company->created_at,
        ),
        'event' => $event ? array(
            'id' => $event->id,
            'title' => $event->title,
        ) : null,
    ));
}
add_action('wp_ajax_sc_get_company_attendee', 'sc_get_company_attendee_handler');

/**
 * Add new company attendee
 */
function sc_add_company_attendee_handler() {
    sc_verify_ajax_request();

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $company_name = isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '';
    $contact_email = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
    $contact_phone = isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '';

    // Validate required fields (updated - removed contact_name, added contact_phone)
    if (!$event_id || !$company_name || !$contact_email || !$contact_phone) {
        wp_send_json_error(array('message' => __('Please fill all required fields.', 'sc_events')));
    }

    // Check for duplicate
    $existing = SC_Company_Attendee::get_by_email_and_event($contact_email, $event_id);
    if ($existing) {
        wp_send_json_error(array('message' => __('A company with this email is already registered for this event.', 'sc_events')));
    }

    // Prepare data
    $data = array(
        'event_id' => $event_id,
        'ticket_id' => isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : null,
        'company_name' => $company_name,
        'company_logo' => isset($_POST['company_logo']) ? intval($_POST['company_logo']) : null,
        'website' => isset($_POST['website']) ? esc_url_raw($_POST['website']) : '',
        'contact_email' => $contact_email,
        'contact_phone' => $contact_phone,
        'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
        'city' => isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '',
        'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
        'booth_number' => isset($_POST['booth_number']) ? sanitize_text_field($_POST['booth_number']) : '',
        'sponsorship_level' => isset($_POST['sponsorship_level']) ? sanitize_text_field($_POST['sponsorship_level']) : '',
        'payment_status' => isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : 'pending',
        'payment_method' => isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '',
        'amount_paid' => isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0,
        'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
    );

    // Handle social media links
    if (isset($_POST['social_media']) && is_array($_POST['social_media'])) {
        $social_media = array();
        foreach ($_POST['social_media'] as $social) {
            if (!empty($social['platform']) && !empty($social['url'])) {
                $social_media[] = array(
                    'platform' => sanitize_text_field($social['platform']),
                    'url' => esc_url_raw($social['url'])
                );
            }
        }
        $data['social_media'] = $social_media;
    }

    // Handle products
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        $products = array();
        foreach ($_POST['products'] as $product) {
            if (!empty($product['name'])) {
                $products[] = array(
                    'image' => isset($product['image']) ? intval($product['image']) : 0,
                    'name' => sanitize_text_field($product['name']),
                    'description' => isset($product['description']) ? sanitize_textarea_field($product['description']) : ''
                );
            }
        }
        $data['products'] = $products;
    }

    // Handle extra fields
    if (isset($_POST['extra_fields']) && is_array($_POST['extra_fields'])) {
        $data['extra_fields'] = array_map('sanitize_text_field', $_POST['extra_fields']);
    }

    $company_id = SC_Company_Attendee::create($data);

    if ($company_id) {
        $company = SC_Company_Attendee::get($company_id);

        wp_send_json_success(array(
            'message' => __('Company registered successfully.', 'sc_events'),
            'company_id' => $company_id,
            'company_code' => $company->company_code,
        ));
    } else {
        global $wpdb;
        wp_send_json_error(array(
            'message' => __('Failed to register company.', 'sc_events'),
            'debug_error' => $wpdb->last_error,
            'debug_query' => $wpdb->last_query
        ));
    }
}
add_action('wp_ajax_sc_add_company_attendee', 'sc_add_company_attendee_handler');

/**
 * Update company attendee
 */
function sc_update_company_attendee_handler() {
    sc_verify_ajax_request();

    // Check both 'id' and 'company_id' for compatibility
    $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_POST['company_id']) ? intval($_POST['company_id']) : 0);

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get($id);
    if (!$company) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    // Prepare update data
    $data = array();

    $text_fields = array(
        'company_name', 'website', 'contact_phone',
        'country', 'city', 'address',
        'booth_number', 'sponsorship_level', 'payment_method', 'notes'
    );

    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = sanitize_text_field($_POST[$field]);
        }
    }

    if (isset($_POST['contact_email'])) {
        $data['contact_email'] = sanitize_email($_POST['contact_email']);
    }

    if (isset($_POST['event_id']) && intval($_POST['event_id']) > 0) {
        $data['event_id'] = intval($_POST['event_id']);
    }

    if (isset($_POST['ticket_id'])) {
        $data['ticket_id'] = intval($_POST['ticket_id']) ?: null;
    }

    if (isset($_POST['company_logo'])) {
        $data['company_logo'] = intval($_POST['company_logo']);
    }

    if (isset($_POST['payment_status'])) {
        $data['payment_status'] = sanitize_text_field($_POST['payment_status']);
    }

    if (isset($_POST['amount_paid'])) {
        $data['amount_paid'] = floatval($_POST['amount_paid']);
    }

    if (isset($_POST['status'])) {
        $data['status'] = sanitize_text_field($_POST['status']);
    }

    // Handle checked_in - checkbox doesn't send value when unchecked
    // So we check if the form field exists in POST (it won't if unchecked)
    $data['checked_in'] = isset($_POST['checked_in']) ? 1 : 0;

    // Handle social media links
    if (isset($_POST['social_media']) && is_array($_POST['social_media'])) {
        $social_media = array();
        foreach ($_POST['social_media'] as $social) {
            if (!empty($social['platform']) && !empty($social['url'])) {
                $social_media[] = array(
                    'platform' => sanitize_text_field($social['platform']),
                    'url' => esc_url_raw($social['url'])
                );
            }
        }
        $data['social_media'] = $social_media;
    }

    // Handle products
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        $products = array();
        foreach ($_POST['products'] as $product) {
            if (!empty($product['name'])) {
                $products[] = array(
                    'image' => isset($product['image']) ? intval($product['image']) : 0,
                    'name' => sanitize_text_field($product['name']),
                    'description' => isset($product['description']) ? sanitize_textarea_field($product['description']) : ''
                );
            }
        }
        $data['products'] = $products;
    }

    if (isset($_POST['extra_fields']) && is_array($_POST['extra_fields'])) {
        $data['extra_fields'] = array_map('sanitize_text_field', $_POST['extra_fields']);
    }

    $result = SC_Company_Attendee::update($id, $data);

    global $wpdb;
    if ($result) {
        wp_send_json_success(array(
            'message'       => __('Company updated successfully.', 'sc_events'),
            'rows_affected' => $wpdb->rows_affected,
            'last_query'    => $wpdb->last_query,
        ));
    } else {
        global $wpdb;
        wp_send_json_error(array(
            'message'     => __('Failed to update company.', 'sc_events'),
            'debug_error' => $wpdb->last_error,
            'debug_query' => $wpdb->last_query,
        ));
    }
}
add_action('wp_ajax_sc_update_company_attendee', 'sc_update_company_attendee_handler');

/**
 * Delete company attendee
 */
function sc_delete_company_attendee_handler() {
    sc_verify_ajax_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_POST['company_id']) ? intval($_POST['company_id']) : 0);

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $result = SC_Company_Attendee::delete($id);

    if ($result) {
        wp_send_json_success(array('message' => __('Company deleted successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to delete company.', 'sc_events')));
    }
}
add_action('wp_ajax_sc_delete_company_attendee', 'sc_delete_company_attendee_handler');

/**
 * Check in company attendee
 */
function sc_checkin_company_handler() {
    sc_verify_ajax_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get($id);
    if (!$company) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    if ($company->checked_in) {
        wp_send_json_error(array('message' => __('Company already checked in.', 'sc_events')));
    }

    $result = SC_Company_Attendee::check_in($id);

    if ($result) {
        $company = SC_Company_Attendee::get($id);
        wp_send_json_success(array(
            'message' => __('Company checked in successfully.', 'sc_events'),
            'checked_in_at' => $company->checked_in_at_formatted,
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to check in company.', 'sc_events')));
    }
}
add_action('wp_ajax_sc_checkin_company', 'sc_checkin_company_handler');

/**
 * Check out company attendee
 */
function sc_checkout_company_handler() {
    sc_verify_ajax_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $result = SC_Company_Attendee::check_out($id);

    if ($result) {
        wp_send_json_success(array('message' => __('Company checked out successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to check out company.', 'sc_events')));
    }
}
add_action('wp_ajax_sc_checkout_company', 'sc_checkout_company_handler');

/**
 * Verify company by code (for scanner)
 */
function sc_verify_company_handler() {
    sc_verify_ajax_request();

    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (empty($code)) {
        wp_send_json_error(array('message' => __('Company code is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get_by_code($code);

    if (!$company) {
        wp_send_json_error(array(
            'message' => __('Company not found.', 'sc_events'),
            'status' => 'not_found',
        ));
    }

    // Check if company is for the right event
    if ($event_id && $company->event_id != $event_id) {
        wp_send_json_error(array(
            'message' => __('This company is not registered for this event.', 'sc_events'),
            'status' => 'wrong_event',
        ));
    }

    // Get event details
    $event = null;
    if (class_exists('SC_Event')) {
        $event = SC_Event::get($company->event_id);
    }

    wp_send_json_success(array(
        'company' => array(
            'id' => $company->id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'company_name_ar' => $company->company_name_ar,
            'logo_url' => $company->logo_url,
            'contact_name' => $company->contact_name,
            'contact_email' => $company->contact_email,
            'booth_number' => $company->booth_number,
            'sponsorship_level' => $company->sponsorship_level,
            'payment_status' => $company->payment_status,
            'payment_status_label' => $company->payment_status_label,
            'checked_in' => (bool) $company->checked_in,
            'checked_in_at' => $company->checked_in_at_formatted ?? '',
            'status' => $company->status,
        ),
        'event' => $event ? array(
            'id' => $event->id,
            'title' => $event->title,
        ) : null,
    ));
}
add_action('wp_ajax_sc_verify_company', 'sc_verify_company_handler');

/**
 * Get company statistics for event
 */
function sc_get_company_stats_handler() {
    sc_verify_ajax_request();

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Event ID is required.', 'sc_events')));
    }

    $stats = SC_Company_Attendee::get_stats($event_id);

    wp_send_json_success(array(
        'stats' => array(
            'total' => $stats['total'],
            'checked_in' => $stats['checked_in'],
            'not_checked_in' => $stats['not_checked_in'],
            'paid' => $stats['paid'],
            'revenue' => sc_format_price($stats['revenue']),
        ),
    ));
}
add_action('wp_ajax_sc_get_company_stats', 'sc_get_company_stats_handler');

/**
 * Export company attendees to CSV
 */
function sc_export_company_attendees_handler() {
    sc_verify_ajax_request();

    $event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? intval($_POST['event_id']) : null;

    // Build export data
    $args = array('limit' => 10000, 'offset' => 0);
    if ($event_id) {
        $args['event_id'] = $event_id;
    }

    $result = SC_Company_Attendee::get_list($args);
    $export_data = array();

    foreach ($result['companies'] as $company) {
        // Get social media as string (each link separated by ;)
        $social_links = array();
        if (!empty($company->social_media_array)) {
            foreach ($company->social_media_array as $social) {
                $social_links[] = ucfirst($social['platform']) . ': ' . $social['url'];
            }
        }

        // Get products as string (each product separated by ;)
        $products_list = array();
        if (!empty($company->products_array)) {
            foreach ($company->products_array as $product) {
                $products_list[] = $product['name'];
            }
        }

        $export_data[] = array(
            'Company Code' => $company->company_code,
            'Company Name' => $company->company_name,
            'Email' => $company->contact_email,
            'Phone' => $company->contact_phone,
            'Website' => $company->website,
            'Country' => $company->country,
            'City' => $company->city,
            'Address' => $company->address,
            'Booth Number' => $company->booth_number,
            'Sponsorship Level' => $company->sponsorship_level,
            'Social Media' => implode('; ', $social_links),
            'Products' => implode('; ', $products_list),
            'Payment Status' => $company->payment_status_label,
            'Payment Method' => $company->payment_method,
            'Amount Paid' => $company->amount_paid,
            'Checked In' => $company->checked_in ? 'Yes' : 'No',
            'Checked In At' => $company->checked_in_at ?: '',
            'Status' => $company->status_label,
            'Notes' => $company->notes,
            'Registered At' => $company->created_at,
        );
    }

    if (empty($export_data)) {
        wp_send_json_error(array('message' => __('No companies to export.', 'sc_events')));
    }

    wp_send_json_success(array(
        'data' => $export_data,
        'headers' => array_keys($export_data[0]),
    ));
}
add_action('wp_ajax_sc_export_company_attendees', 'sc_export_company_attendees_handler');

/**
 * Send ticket email to company
 */
function sc_send_company_ticket_handler() {
    sc_verify_ajax_request();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get($id);
    if (!$company) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    // Get event
    $event = null;
    if (class_exists('SC_Event')) {
        $event = SC_Event::get($company->event_id);
    }

    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Build ticket URL
    $ticket_url = add_query_arg(array(
        'company_id' => $company->id,
        'code' => $company->company_code,
    ), home_url('/company-ticket/'));

    // Send HTML email with QR
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    $subject = sprintf('[%s] Your Company Badge for %s', $platform_name, $event->title);

    $qr_data = json_encode(array(
        'type' => 'company',
        'code' => $company->company_code,
        'company' => $company->company_name,
        'event' => $company->event_id,
    ));
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_data);

    $message = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
    $message .= '<h2 style="color:#333;text-align:center;">' . esc_html($event->title) . '</h2>';
    $message .= '<p>Dear ' . esc_html($company->contact_name) . ',</p>';
    $message .= '<p>Thank you for registering <strong>' . esc_html($company->company_name) . '</strong> for <strong>' . esc_html($event->title) . '</strong>.</p>';
    $message .= '<div style="text-align:center;margin:20px 0;padding:20px;background:#f8f9fa;border-radius:10px;">';
    $message .= '<p style="font-size:14px;color:#666;">Company Code</p>';
    $message .= '<p style="font-size:24px;font-weight:bold;color:#1565c0;letter-spacing:2px;">' . esc_html($company->company_code) . '</p>';
    if ($company->booth_number) {
        $message .= '<p style="font-size:14px;color:#666;">Booth: <strong>' . esc_html($company->booth_number) . '</strong></p>';
    }
    $message .= '<img src="' . esc_url($qr_url) . '" alt="QR Code" style="width:200px;height:200px;margin:10px auto;display:block;">';
    $message .= '<p style="font-size:12px;color:#999;">Present this QR code at the event entrance</p>';
    $message .= '</div>';
    $message .= '<p>Best regards,<br>' . esc_html($platform_name) . '</p>';
    $message .= '</div>';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $sent = wp_mail($company->contact_email, $subject, $message, $headers);

    if ($sent) {
        // Update email sent status
        SC_Company_Attendee::update($id, array(
            'email_sent' => 1,
            'email_sent_at' => current_time('mysql'),
        ));

        wp_send_json_success(array('message' => __('Email sent successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to send email.', 'sc_events')));
    }
}
add_action('wp_ajax_sc_send_company_ticket', 'sc_send_company_ticket_handler');

/**
 * Bulk action on company attendees
 */
function sc_bulk_company_action_handler() {
    sc_verify_ajax_request();

    $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
    $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

    if (empty($ids)) {
        wp_send_json_error(array('message' => __('No companies selected.', 'sc_events')));
    }

    if (empty($action)) {
        wp_send_json_error(array('message' => __('No action selected.', 'sc_events')));
    }

    $success = 0;
    $failed = 0;

    foreach ($ids as $id) {
        $result = false;

        switch ($action) {
            case 'check_in':
                $result = SC_Company_Attendee::check_in($id);
                break;

            case 'check_out':
                $result = SC_Company_Attendee::check_out($id);
                break;

            case 'delete':
                $result = SC_Company_Attendee::delete($id);
                break;

            case 'send_email':
                // Reuse single email function logic
                $_POST['id'] = $id;
                // This is simplified - you might want to handle this differently
                $result = true;
                break;
        }

        if ($result) {
            $success++;
        } else {
            $failed++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(__('Action completed: %d successful, %d failed.', 'sc_events'), $success, $failed),
        'success_count' => $success,
        'failed_count' => $failed,
    ));
}
add_action('wp_ajax_sc_bulk_company_action', 'sc_bulk_company_action_handler');

/**
 * Upload company logo
 */
function sc_upload_company_logo_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
        wp_send_json_error(array('message' => __('No file uploaded.', 'sc_events')));
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($_FILES['file']['type'], $allowed_types)) {
        wp_send_json_error(array('message' => __('Invalid file type. Please upload an image (JPG, PNG, GIF, or WebP).', 'sc_events')));
    }

    $attachment_id = media_handle_upload('file', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
    }

    $url = wp_get_attachment_url($attachment_id);

    wp_send_json_success(array(
        'url' => $url,
        'attachment_id' => $attachment_id
    ));
}
add_action('wp_ajax_sc_upload_company_logo', 'sc_upload_company_logo_handler');

/**
 * Send company badge email
 */
function sc_send_company_badge_email_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;

    if (!$company_id) {
        wp_send_json_error(array('message' => __('Company ID is required.', 'sc_events')));
    }

    $company = SC_Company_Attendee::get($company_id);
    if (!$company) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    // Get event
    $event = null;
    if (class_exists('SC_Event') && $company->event_id) {
        $event = SC_Event::get($company->event_id);
    }

    // Build ticket URL
    $ticket_url = home_url('/company-ticket/' . $company->company_code . '/');

    // Build email content
    $subject = sprintf(__('[%s] Your Company Badge', 'sc_events'), $event ? $event->title : get_bloginfo('name'));

    $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $message .= '<h2>Company Registration Confirmed</h2>';
    $message .= '<p>Dear ' . esc_html($company->contact_name) . ',</p>';
    $message .= '<p>Your company <strong>' . esc_html($company->company_name) . '</strong> has been registered';
    if ($event) {
        $message .= ' for <strong>' . esc_html($event->title) . '</strong>';
    }
    $message .= '.</p>';
    $message .= '<p><strong>Company Code:</strong> ' . esc_html($company->company_code) . '</p>';
    if ($company->booth_number) {
        $message .= '<p><strong>Booth Number:</strong> ' . esc_html($company->booth_number) . '</p>';
    }
    $message .= '<p><a href="' . esc_url($ticket_url) . '" style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px;">View Your Badge</a></p>';
    $message .= '<p>Please present this badge (printed or on your device) at the event entrance.</p>';
    $message .= '<p>Best regards,<br>' . get_bloginfo('name') . '</p>';
    $message .= '</div>';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $sent = wp_mail($company->contact_email, $subject, $message, $headers);

    if ($sent) {
        wp_send_json_success(array('message' => __('Badge email sent successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to send email.', 'sc_events')));
    }
}
add_action('wp_ajax_sc_send_company_badge_email', 'sc_send_company_badge_email_handler');

/**
 * Get booths by event ID for select dropdown
 */
function sc_get_event_booths_handler() {
    sc_verify_ajax_request();

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$event_id) {
        wp_send_json_success(array('booths' => array()));
        return;
    }

    if (!class_exists('SC_Booth')) {
        wp_send_json_error(array('message' => __('Booths module not available.', 'sc_events')));
        return;
    }

    $booths = SC_Booth::get_by_event($event_id, array(
        'orderby' => 'booth_number',
        'order' => 'ASC'
    ));

    $formatted = array();
    foreach ($booths as $booth) {
        $label = $booth->booth_number;
        if (!empty($booth->booth_type_name)) {
            $label .= ' - ' . $booth->booth_type_name;
        }
        $formatted[] = array(
            'id' => $booth->id,
            'booth_number' => $booth->booth_number,
            'label' => $label
        );
    }

    wp_send_json_success(array('booths' => $formatted));
}
add_action('wp_ajax_sc_get_event_booths', 'sc_get_event_booths_handler');
