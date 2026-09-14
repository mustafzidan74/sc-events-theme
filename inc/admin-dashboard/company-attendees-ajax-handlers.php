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

// List, save, bulk actions, badge email and export.
require_once __DIR__ . '/company-dashboard.php';
