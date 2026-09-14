<?php
/**
 * Certificates AJAX Handlers
 *
 * Handles all AJAX operations for certificate templates and issued certificates
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Drop the cached PDF for every certificate that uses a given template.
 * Called after a template's design is updated so subsequent downloads regenerate.
 */
if (!function_exists('sc_invalidate_certificate_pdfs_for_template')) {
    function sc_invalidate_certificate_pdfs_for_template($template_id) {
        global $wpdb;
        if (!class_exists('SC_Certificate_PDF')) {
            return;
        }
        $table = $wpdb->prefix . 'sc_certificates';
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE template_id = %d",
            (int) $template_id
        ));
        if (!method_exists('SC_Certificate_PDF', 'invalidateCache')) {
            return;
        }
        foreach ($ids as $cid) {
            SC_Certificate_PDF::invalidateCache((int) $cid);
        }
    }
}

// Rebuild tool — admin-only diagnostic/repair handlers. Registered up here
// (before the module-active early return) so the tool stays usable even if
// the certificates module is toggled off temporarily.
add_action('wp_ajax_sc_diagnose_certificate',         'sc_diagnose_certificate_handler');
add_action('wp_ajax_sc_rebuild_event_summary',        'sc_rebuild_event_summary_handler');
add_action('wp_ajax_sc_rebuild_event_certificates',   'sc_rebuild_event_certificates_handler');

// Check if certificates module is enabled - if not, don't register any handlers
// Verification and download handlers are still allowed for existing certificates
if (function_exists('sc_module_active') && !sc_module_active('certificates')) {
    // Only register public handlers for existing certificates
    add_action('wp_ajax_sc_verify_certificate', 'sc_verify_certificate_public');
    add_action('wp_ajax_nopriv_sc_verify_certificate', 'sc_verify_certificate_public');
    add_action('wp_ajax_sc_download_certificate', 'sc_download_certificate_handler');
    add_action('wp_ajax_nopriv_sc_download_certificate', 'sc_download_certificate_handler');
    return; // Don't register admin handlers
}

// List, issuing, email, revoke and reinstate for the dashboard.
require_once __DIR__ . '/certificates-dashboard.php';

// ==========================================
// CERTIFICATE TEMPLATES HANDLERS
// ==========================================

/**
 * Get all certificate templates
 */
add_action('wp_ajax_sc_get_certificate_templates', 'sc_get_certificate_templates');
function sc_get_certificate_templates() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $templates = SC_Certificate_Template::get_all(array(
        'orderby' => 'name',
        'order' => 'ASC'
    ));

    wp_send_json_success(array('templates' => $templates));
}

/**
 * Get certificate templates with pagination and search
 */
add_action('wp_ajax_sc_get_certificate_templates_paginated', 'sc_get_certificate_templates_paginated');
function sc_get_certificate_templates_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $design_mode = isset($_POST['design_mode']) ? sanitize_text_field($_POST['design_mode']) : '';

    $args = array(
        'limit' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );

    if (!empty($search)) {
        $args['search'] = $search;
    }

    if ($status === 'active') {
        $args['is_active'] = 1;
    } elseif ($status === 'inactive') {
        $args['is_active'] = 0;
    }

    if (!empty($design_mode)) {
        $args['design_mode'] = $design_mode;
    }

    $templates = SC_Certificate_Template::get_all($args);
    $total = SC_Certificate_Template::count($args);
    $total_pages = ceil($total / $per_page);

    // Convert background_image attachment ID to URL for each template
    foreach ($templates as &$template) {
        if (!empty($template->background_image) && is_numeric($template->background_image)) {
            $template->background_image_url = wp_get_attachment_url($template->background_image);
        } else {
            $template->background_image_url = $template->background_image ?: '';
        }
    }
    unset($template);

    wp_send_json_success(array(
        'templates' => $templates,
        'total' => $total,
        'pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ));
}

/**
 * Get single certificate template
 */
add_action('wp_ajax_sc_get_certificate_template', 'sc_get_certificate_template');
function sc_get_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    $template = SC_Certificate_Template::get($template_id);

    if (!$template) {
        wp_send_json_error(array('message' => __('Template not found.', 'sc_events')));
    }

    wp_send_json_success(array('template' => $template));
}

/**
 * Save certificate template (Create/Update)
 */
add_action('wp_ajax_sc_save_certificate_template', 'sc_save_certificate_template');
function sc_save_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Template name is required.', 'sc_events')));
    }

    // Prepare data
    // Note: html_template needs full HTML support for certificates (doctype, html, head, body, style tags)
    // so we don't use wp_kses_post which strips these. The template is only used for PDF generation.
    // Security: Remove script tags and event handlers to prevent XSS if template is ever rendered in browser
    $html_template = isset($_POST['html_template']) ? $_POST['html_template'] : '';
    // Strip <script> tags and their contents
    $html_template = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html_template);
    // Strip event handlers (onclick, onerror, onload, etc.)
    $html_template = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/', '', $html_template);
    // Strip javascript: URLs
    $html_template = preg_replace('/javascript\s*:/i', '', $html_template);

    $data = array(
        'name' => $name,
        'html_template' => $html_template,
        'css_styles' => isset($_POST['css_styles']) ? $_POST['css_styles'] : '',
        'orientation' => isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : 'landscape',
        'paper_size' => isset($_POST['paper_size']) ? sanitize_text_field($_POST['paper_size']) : 'A4',
        'is_active' => isset($_POST['is_active']) ? intval($_POST['is_active']) : 1,
        'is_default' => isset($_POST['is_default']) ? intval($_POST['is_default']) : 0
    );

    // Handle custom dimensions (use width_mm and height_mm column names)
    if ($data['paper_size'] === 'Custom') {
        $width = isset($_POST['width']) ? floatval($_POST['width']) : 297;
        $height = isset($_POST['height']) ? floatval($_POST['height']) : 210;
        $data['width_mm'] = $width;
        $data['height_mm'] = $height;
    }

    // Handle background image upload
    if (isset($_FILES['background_image']) && !empty($_FILES['background_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('background_image', 0);
        if (!is_wp_error($attachment_id)) {
            // Store attachment ID, not URL
            $data['background_image'] = $attachment_id;
        }
    } elseif (isset($_POST['background_image_id']) && intval($_POST['background_image_id']) > 0) {
        // For edit page - preserve existing background image ID
        $data['background_image'] = intval($_POST['background_image_id']);
    }

    // If this is set as default, unset others first
    if (!empty($data['is_default'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_certificate_templates';
        $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
    }

    if ($template_id > 0) {
        // Update existing template
        $result = SC_Certificate_Template::update($template_id, $data);
        if ($result) {
            // Invalidate cached PDFs for every cert using this template — design changed
            sc_invalidate_certificate_pdfs_for_template($template_id);

            wp_send_json_success(array(
                'message' => __('Template updated successfully.', 'sc_events'),
                'template_id' => $template_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update template.', 'sc_events')));
        }
    } else {
        // Create new template
        $new_id = SC_Certificate_Template::create($data);
        if ($new_id) {
            wp_send_json_success(array(
                'message' => __('Template created successfully.', 'sc_events'),
                'template_id' => $new_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create template.', 'sc_events')));
        }
    }
}

/**
 * Delete certificate template
 */
add_action('wp_ajax_sc_delete_certificate_template', 'sc_delete_certificate_template');
function sc_delete_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    // Check if template is the default template
    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificate_templates';
    $is_default = $wpdb->get_var($wpdb->prepare(
        "SELECT is_default FROM $table WHERE id = %d",
        $template_id
    ));

    if ($is_default) {
        wp_send_json_error(array(
            'message' => __('Cannot delete the default template. Please set another template as default first.', 'sc_events')
        ));
    }

    // Check if template is in use by any events
    $events_table = $wpdb->prefix . 'sc_events';
    $in_use = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE certificate_template_id = %d",
        $template_id
    ));

    if ($in_use > 0) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('Cannot delete template. It is being used by %d event(s).', 'sc_events'),
                $in_use
            )
        ));
    }

    // Check if template has issued certificates
    $certs_count = SC_Certificate::count(array('template_id' => $template_id));
    if ($certs_count > 0) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('Cannot delete template. %d certificate(s) have been issued with this template.', 'sc_events'),
                $certs_count
            )
        ));
    }

    $result = SC_Certificate_Template::delete($template_id);

    if ($result) {
        wp_send_json_success(array('message' => __('Template deleted successfully.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to delete template.', 'sc_events')));
    }
}

/**
 * Duplicate certificate template
 */
add_action('wp_ajax_sc_duplicate_certificate_template', 'sc_duplicate_certificate_template');
function sc_duplicate_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    $new_id = SC_Certificate_Template::duplicate($template_id);

    if ($new_id) {
        wp_send_json_success(array(
            'message' => __('Template duplicated successfully.', 'sc_events'),
            'template_id' => $new_id
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to duplicate template.', 'sc_events')));
    }
}

/**
 * Set template as default
 */
add_action('wp_ajax_sc_set_default_certificate_template', 'sc_set_default_certificate_template');
function sc_set_default_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificate_templates';

    // Remove default from all templates
    $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));

    // Set this template as default
    $result = $wpdb->update($table, array('is_default' => 1), array('id' => $template_id));

    if ($result !== false) {
        wp_send_json_success(array('message' => __('Default template updated.', 'sc_events')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update default template.', 'sc_events')));
    }
}

/**
 * Toggle default certificate template
 */
add_action('wp_ajax_sc_toggle_default_certificate_template', 'sc_toggle_default_certificate_template');
function sc_toggle_default_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificate_templates';

    // Check if this template is currently the default
    $is_default = $wpdb->get_var($wpdb->prepare("SELECT is_default FROM $table WHERE id = %d", $template_id));

    if ($is_default) {
        // Remove default (no template will be default)
        $result = $wpdb->update($table, array('is_default' => 0), array('id' => $template_id));
        $message = __('Default removed.', 'sc_events');
    } else {
        // Remove default from all templates first
        $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
        // Set this template as default
        $result = $wpdb->update($table, array('is_default' => 1), array('id' => $template_id));
        $message = __('Set as default.', 'sc_events');
    }

    if ($result !== false) {
        wp_send_json_success(array('message' => $message));
    } else {
        wp_send_json_error(array('message' => __('Failed to update default template.', 'sc_events')));
    }
}

/**
 * Preview certificate template
 */
add_action('wp_ajax_sc_preview_certificate_template', 'sc_preview_certificate_template');
function sc_preview_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get template data from POST or from saved template
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if ($template_id) {
        $template = SC_Certificate_Template::get($template_id);
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'sc_events')));
        }
    } else {
        // Build template from POST data for live preview
        // Don't use wp_kses_post as it strips necessary HTML tags for certificates
        $template = array(
            'html_template' => isset($_POST['html_template']) ? $_POST['html_template'] : '',
            'css_styles' => isset($_POST['css_styles']) ? $_POST['css_styles'] : '',
            'background_image' => isset($_POST['background_image']) ? esc_url($_POST['background_image']) : '',
            'orientation' => isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : 'landscape',
            'paper_size' => isset($_POST['paper_size']) ? sanitize_text_field($_POST['paper_size']) : 'A4'
        );
    }

    // Sample QR code image (placeholder)
    $qr_placeholder = '<img src="data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect fill="#fff" width="100" height="100"/><rect fill="#000" x="10" y="10" width="20" height="20"/><rect fill="#000" x="70" y="10" width="20" height="20"/><rect fill="#000" x="10" y="70" width="20" height="20"/><rect fill="#000" x="40" y="40" width="20" height="20"/><text x="50" y="95" font-size="8" text-anchor="middle" fill="#666">QR Code</text></svg>') . '" class="qr-code" style="width: 100px; height: 100px;" alt="QR Code">';

    // Sample data for preview
    $sample_data = array(
        'attendee_name' => __('John Doe', 'sc_events'),
        'attendee_email' => 'john.doe@example.com',
        'event_title' => __('Annual Technology Conference 2025', 'sc_events'),
        'event_date' => date_i18n(get_option('date_format')),
        'event_start_date' => date_i18n(get_option('date_format')),
        'event_end_date' => date_i18n(get_option('date_format'), strtotime('+1 day')),
        'event_start_time' => '09:00 AM',
        'event_end_time' => '05:00 PM',
        'event_location' => __('Grand Convention Center, Main Hall', 'sc_events'),
        'certificate_number' => 'CERT-2025-SAMPLE',
        'verification_code' => 'ABC123XYZ789',
        'verification_url' => home_url('/verify-certificate/ABC123XYZ789'),
        'issue_date' => date_i18n(get_option('date_format')),
        'organizer_name' => __('Tech Events Inc.', 'sc_events'),
        'ticket_name' => __('Standard Ticket', 'sc_events'),
        'current_year' => date('Y'),
        'qr_code' => $qr_placeholder
    );

    $html = SC_Certificate_Template::render($template, $sample_data);

    wp_send_json_success(array('html' => $html));
}

/**
 * Get available placeholders for certificate templates
 */
add_action('wp_ajax_sc_get_certificate_placeholders', 'sc_get_certificate_placeholders');
function sc_get_certificate_placeholders() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $placeholders = SC_Certificate_Template::$placeholders;

    wp_send_json_success(array('placeholders' => $placeholders));
}

// ==========================================
// ISSUED CERTIFICATES HANDLERS
// ==========================================










// ==========================================
// PUBLIC CERTIFICATE HANDLERS (for frontend)
// ==========================================

/**
 * Verify certificate (public - no login required)
 */
add_action('wp_ajax_sc_verify_certificate', 'sc_verify_certificate_public');
add_action('wp_ajax_nopriv_sc_verify_certificate', 'sc_verify_certificate_public');
function sc_verify_certificate_public() {
    // Code only: certificate numbers are sequential, so looking them up publicly
    // would let anyone list attendee names.
    $code = isset($_POST['code']) ? preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_POST['code']))) : '';

    if ($code === '') {
        wp_send_json_error(array('message' => __('Enter the verification code.', 'sc_events')));
    }

    $certificate = SC_Certificate::get_by_verification_code($code);

    if (!$certificate) {
        wp_send_json_error(array(
            'message' => __('Certificate not found. Please check the code and try again.', 'sc_events'),
            'valid' => false
        ));
    }

    // Downloading a certificate changes its status to "downloaded"; it is still valid.
    $is_valid = $certificate['status'] !== 'revoked';

    $response = array(
        'valid' => $is_valid,
        'status' => $certificate['status'],
        'certificate_number' => $certificate['certificate_number'],
        'attendee_name' => $certificate['attendee_name'],
        'event_title' => $certificate['event_title'],
        'event_date' => $certificate['event_date'],
        'issued_at' => $certificate['issued_at']
    );

    if (!$is_valid && $certificate['status'] === 'revoked') {
        $response['message'] = __('This certificate has been revoked.', 'sc_events');
        if (!empty($certificate['revoke_reason'])) {
            $response['revoke_reason'] = $certificate['revoke_reason'];
        }
    } else {
        $response['message'] = __('Certificate is valid and authentic.', 'sc_events');
    }

    wp_send_json_success($response);
}

/**
 * Download certificate (public - requires login or verification)
 */
add_action('wp_ajax_sc_download_certificate', 'sc_download_certificate_handler');
add_action('wp_ajax_nopriv_sc_download_certificate', 'sc_download_certificate_handler');
function sc_download_certificate_handler() {
    $certificate_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $token = isset($_REQUEST['token']) ? sanitize_text_field($_REQUEST['token']) : '';

    if (!$certificate_id) {
        wp_die(__('Certificate ID is required.', 'sc_events'));
    }

    $certificate = SC_Certificate::get($certificate_id);

    if (!$certificate) {
        wp_die(__('Certificate not found.', 'sc_events'));
    }

    // Verify access
    $has_access = false;

    // Admin/Event manager always has access
    if (is_user_logged_in() && SC_Event_Manager_Dashboard::is_event_manager()) {
        $has_access = true;
    }

    // Token-based access (from email link)
    if (!$has_access && !empty($token)) {
        $expected_token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);
        if (hash_equals($expected_token, $token)) {
            $has_access = true;
        }
    }

    // Logged-in user who owns the certificate
    if (!$has_access && is_user_logged_in()) {
        $attendee = SC_Attendee::get($certificate['attendee_id']);
        if ($attendee && (int) $attendee->user_id === get_current_user_id()) {
            $has_access = true;
        }
    }

    if (!$has_access) {
        wp_die(__('You do not have permission to download this certificate.', 'sc_events'));
    }

    // Allow download for issued AND already-downloaded certs; only block revoked.
    if (!in_array($certificate['status'], array('issued', 'downloaded'), true)) {
        wp_die(__('This certificate is not valid for download.', 'sc_events'));
    }

    // Record download
    SC_Certificate::record_download($certificate_id);

    // Try to generate PDF
    $pdf_class = get_template_directory() . '/inc/certificates/class-sc-certificate-pdf.php';
    if (file_exists($pdf_class)) {
        require_once $pdf_class;

        try {
            SC_Certificate_PDF::download($certificate_id);
            exit;
        } catch (Exception $e) {
            // Log error and fallback to HTML
            error_log('Certificate PDF generation failed: ' . $e->getMessage());
        }
    }

    // Fallback: Generate HTML version
    $template = SC_Certificate_Template::get($certificate['template_id']);

    if (!$template) {
        wp_die(__('Certificate template not found.', 'sc_events'));
    }

    // Prepare certificate data
    $data = array(
        'attendee_name' => $certificate['attendee_name'],
        'event_title' => $certificate['event_title'],
        'event_date' => $certificate['event_date'],
        'certificate_number' => $certificate['certificate_number'],
        'verification_code' => $certificate['verification_code'],
        'issue_date' => date_i18n(get_option('date_format'), strtotime($certificate['issued_at'])),
        'qr_code' => ''
    );

    // Get event details
    $event = SC_Event::get($certificate['event_id']);
    if ($event) {
        $data['event_start_time'] = $event->start_time ?? '';
        $data['event_end_time'] = $event->end_time ?? '';
        $data['event_location'] = $event->venue_name ?? '';
    }

    $attendee = SC_Attendee::get($certificate['attendee_id']);
    if ($attendee) {
        $data['attendee_email'] = $attendee->email ?? '';
    }

    $html = SC_Certificate_Template::render($template, $data);

    // Output HTML with print button
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($certificate['certificate_number']); ?> - Certificate</title>
        <style>
            @media print {
                body { margin: 0; }
                .print-hide { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="print-hide" style="padding: 20px; text-align: center; background: #f5f5f5; border-bottom: 1px solid #ddd;">
            <button onclick="window.print()" style="padding: 10px 30px; background: var(--primary-color); color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                Print Certificate
            </button>
        </div>
        <?php echo $html; ?>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// VISUAL CERTIFICATE BUILDER HANDLERS
// ==========================================

/**
 * Upload certificate background image
 */
add_action('wp_ajax_sc_upload_certificate_background', 'sc_upload_certificate_background');
function sc_upload_certificate_background() {
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
    $file_type = wp_check_filetype($_FILES['file']['name']);

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

/**
 * Upload element image for visual certificate builder
 */
add_action('wp_ajax_sc_upload_certificate_element_image', 'sc_upload_certificate_element_image');
function sc_upload_certificate_element_image() {
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

/**
 * Save visual certificate template
 */
add_action('wp_ajax_sc_save_visual_certificate_template', 'sc_save_visual_certificate_template');
function sc_save_visual_certificate_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Template name is required.', 'sc_events')));
    }

    $elements_config = isset($_POST['elements_config']) ? $_POST['elements_config'] : '';

    // Validate JSON
    $config = json_decode(stripslashes($elements_config), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => __('Invalid elements configuration.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificate_templates';

    // Prepare data
    $data = array(
        'name' => $name,
        'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
        'design_mode' => 'visual',
        'elements_config' => stripslashes($elements_config),
        'orientation' => isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : 'landscape',
        'paper_size' => isset($_POST['paper_size']) ? sanitize_text_field($_POST['paper_size']) : 'A4',
        'is_active' => isset($_POST['is_active']) ? intval($_POST['is_active']) : 1,
        'is_default' => isset($_POST['is_default']) ? intval($_POST['is_default']) : 0,
        'updated_at' => current_time('mysql')
    );

    // Handle background image
    if (!empty($_POST['background_image'])) {
        // Check if it's a URL or attachment ID
        if (is_numeric($_POST['background_image'])) {
            $data['background_image'] = intval($_POST['background_image']);
        } else {
            // It's a URL, try to get attachment ID
            $attachment_id = attachment_url_to_postid($_POST['background_image']);
            if ($attachment_id) {
                $data['background_image'] = $attachment_id;
            }
        }
    }

    // If this is set as default, unset others
    if (!empty($data['is_default'])) {
        $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
    }

    if ($template_id > 0) {
        // Update existing template
        $result = $wpdb->update($table, $data, array('id' => $template_id));

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Template updated successfully.', 'sc_events'),
                'template_id' => $template_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update template.', 'sc_events')));
        }
    } else {
        // Create new template
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $data['slug'] = sanitize_title($name) . '-' . time();

        // Generate HTML template from visual config for backward compatibility
        $data['html_template'] = sc_generate_html_from_visual_config($config);

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $new_id = $wpdb->insert_id;
            wp_send_json_success(array(
                'message' => __('Template created successfully.', 'sc_events'),
                'template_id' => $new_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create template.', 'sc_events')));
        }
    }
}

/**
 * Generate HTML template from visual config
 *
 * @param array $config Visual config
 * @return string HTML template
 */
function sc_generate_html_from_visual_config($config) {
    $canvas_width = isset($config['canvas_width']) ? intval($config['canvas_width']) : 1123;
    $canvas_height = isset($config['canvas_height']) ? intval($config['canvas_height']) : 794;
    $elements = isset($config['elements']) ? $config['elements'] : array();

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .certificate {
            width: ' . $canvas_width . 'px;
            height: ' . $canvas_height . 'px;
            position: relative;
            font-family: Arial, sans-serif;
            background: #fff;
            overflow: hidden;
        }
        .certificate-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }
        .element {
            position: absolute;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="certificate">';

    // Background image placeholder
    $html .= '
        {{#background_image}}
        <img src="{{background_image}}" class="certificate-bg" alt="">
        {{/background_image}}';

    // Render elements
    foreach ($elements as $element) {
        $style = sprintf(
            'left: %dpx; top: %dpx; width: %dpx; height: %dpx;',
            intval($element['x']),
            intval($element['y']),
            intval($element['width']),
            intval($element['height'])
        );

        // Add text styles
        if (!empty($element['fontSize'])) {
            $style .= ' font-size: ' . intval($element['fontSize']) . 'px;';
        }
        if (!empty($element['fontFamily'])) {
            $style .= ' font-family: ' . esc_attr($element['fontFamily']) . ';';
        }
        if (!empty($element['fontWeight'])) {
            $style .= ' font-weight: ' . esc_attr($element['fontWeight']) . ';';
        }
        if (!empty($element['color'])) {
            $style .= ' color: ' . esc_attr($element['color']) . ';';
        }
        if (!empty($element['textAlign'])) {
            $style .= ' text-align: ' . esc_attr($element['textAlign']) . ';';
        }
        if (!empty($element['backgroundColor']) && $element['backgroundColor'] !== 'transparent') {
            $style .= ' background-color: ' . esc_attr($element['backgroundColor']) . ';';
        }

        $content = '';
        $type = isset($element['type']) ? $element['type'] : '';

        switch ($type) {
            case 'attendee_name':
                $content = '{attendee_name}';
                break;
            case 'attendee_email':
                $content = '{attendee_email}';
                break;
            case 'event_title':
                $content = '{event_title}';
                break;
            case 'event_date':
                $content = '{event_date}';
                break;
            case 'event_location':
                $content = '{event_location}';
                break;
            case 'certificate_number':
                $content = '{certificate_number}';
                break;
            case 'issue_date':
                $content = '{issue_date}';
                break;
            case 'verification_code':
                $content = '{verification_code}';
                break;
            case 'qr_code':
                $content = '{qr_code}';
                break;
            case 'organizer_name':
                $content = '{organizer_name}';
                break;
            case 'ticket_name':
                $content = '{ticket_name}';
                break;
            case 'custom_text':
                $content = esc_html($element['content'] ?? '');
                break;
            case 'custom_image':
                if (!empty($element['imageUrl'])) {
                    $content = '<img src="' . esc_url($element['imageUrl']) . '" style="max-width: 100%; max-height: 100%;" alt="">';
                }
                break;
            case 'line':
                $content = '';
                break;
        }

        $html .= sprintf(
            '
        <div class="element" style="%s">%s</div>',
            esc_attr($style),
            $content
        );
    }

    $html .= '
    </div>
</body>
</html>';

    return $html;
}

/**
 * Preview visual certificate with sample data
 */
add_action('wp_ajax_sc_preview_visual_certificate', 'sc_preview_visual_certificate');
function sc_preview_visual_certificate() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $elements_config = isset($_POST['elements_config']) ? $_POST['elements_config'] : '';

    // Validate JSON
    $config = json_decode(stripslashes($elements_config), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => __('Invalid elements configuration.', 'sc_events')));
    }

    $canvas_width = isset($config['canvas_width']) ? intval($config['canvas_width']) : 1123;
    $canvas_height = isset($config['canvas_height']) ? intval($config['canvas_height']) : 794;
    $background_image = isset($config['background_image']) ? esc_url($config['background_image']) : '';
    $elements = isset($config['elements']) ? $config['elements'] : array();

    // Sample data for preview
    $sample_data = array(
        'attendee_name' => 'John Doe',
        'attendee_email' => 'john.doe@example.com',
        'event_title' => 'Annual Technology Conference 2025',
        'event_date' => date_i18n(get_option('date_format')),
        'event_location' => 'Grand Convention Center',
        'certificate_number' => 'CERT-2025-SAMPLE',
        'verification_code' => 'ABC123XYZ789',
        'issue_date' => date_i18n(get_option('date_format')),
        'organizer_name' => 'Tech Events Inc.',
        'ticket_name' => 'VIP Pass',
        'qr_code' => '' // Will be rendered as placeholder
    );

    // Build HTML for preview
    $html = '<div style="position: relative; width: 100%; height: 100%; overflow: hidden;">';

    // Background
    if (!empty($background_image)) {
        $html .= '<img src="' . esc_url($background_image) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;" alt="">';
    }

    // Elements
    foreach ($elements as $element) {
        $style = sprintf(
            'position: absolute; left: %.1f%%; top: %.1f%%; width: %.1f%%; height: auto;',
            ($element['x'] / $canvas_width) * 100,
            ($element['y'] / $canvas_height) * 100,
            ($element['width'] / $canvas_width) * 100
        );

        // Text styles
        if (!empty($element['fontSize'])) {
            // Scale font size for preview (60% scale)
            $scaled_font = round($element['fontSize'] * 0.6);
            $style .= ' font-size: ' . $scaled_font . 'px;';
        }
        if (!empty($element['fontFamily'])) {
            $style .= ' font-family: ' . esc_attr($element['fontFamily']) . ';';
        }
        if (!empty($element['fontWeight'])) {
            $style .= ' font-weight: ' . esc_attr($element['fontWeight']) . ';';
        }
        if (!empty($element['color'])) {
            $style .= ' color: ' . esc_attr($element['color']) . ';';
        }
        if (!empty($element['textAlign'])) {
            $style .= ' text-align: ' . esc_attr($element['textAlign']) . ';';
        }
        if (!empty($element['backgroundColor']) && $element['backgroundColor'] !== 'transparent') {
            $style .= ' background-color: ' . esc_attr($element['backgroundColor']) . ';';
        }

        $content = '';
        $type = isset($element['type']) ? $element['type'] : '';

        switch ($type) {
            case 'attendee_name':
                $content = $sample_data['attendee_name'];
                break;
            case 'attendee_email':
                $content = $sample_data['attendee_email'];
                break;
            case 'event_title':
                $content = $sample_data['event_title'];
                break;
            case 'event_date':
                $content = $sample_data['event_date'];
                break;
            case 'event_location':
                $content = $sample_data['event_location'];
                break;
            case 'certificate_number':
                $content = $sample_data['certificate_number'];
                break;
            case 'issue_date':
                $content = $sample_data['issue_date'];
                break;
            case 'verification_code':
                $content = $sample_data['verification_code'];
                break;
            case 'qr_code':
                // QR placeholder in preview
                $qr_size = round(min($element['width'], $element['height']) * 0.6);
                $content = '<div style="width: ' . $qr_size . 'px; height: ' . $qr_size . 'px; background: #f0f0f0; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center;"><i class="fa fa-qrcode" style="font-size: ' . round($qr_size * 0.5) . 'px; color: #666;"></i></div>';
                break;
            case 'organizer_name':
                $content = $sample_data['organizer_name'];
                break;
            case 'ticket_name':
                $content = $sample_data['ticket_name'];
                break;
            case 'custom_text':
                $content = esc_html($element['content'] ?? 'Custom Text');
                break;
            case 'custom_image':
                if (!empty($element['imageUrl'])) {
                    $content = '<img src="' . esc_url($element['imageUrl']) . '" style="max-width: 100%; max-height: 100%;" alt="">';
                } else {
                    $content = '<div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;"><i class="fa fa-image" style="color: #999;"></i></div>';
                }
                break;
            case 'line':
                $style .= ' height: ' . round($element['height'] * 0.6) . 'px;';
                $content = '';
                break;
        }

        $html .= '<div style="' . esc_attr($style) . '">' . $content . '</div>';
    }

    $html .= '</div>';

    wp_send_json_success(array('html' => $html));
}

/**
 * Get template for visual builder (with elements config)
 */
add_action('wp_ajax_sc_get_visual_template', 'sc_get_visual_template');
function sc_get_visual_template() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        wp_send_json_error(array('message' => __('Template ID is required.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificate_templates';

    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $template_id
    ));

    if (!$template) {
        wp_send_json_error(array('message' => __('Template not found.', 'sc_events')));
    }

    // Get background image URL
    $background_image_url = null;
    if ($template->background_image) {
        $background_image_url = wp_get_attachment_url($template->background_image);
    }

    // Parse elements config
    $elements_config = null;
    if (!empty($template->elements_config)) {
        $elements_config = json_decode($template->elements_config, true);
    }

    wp_send_json_success(array(
        'template' => array(
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'design_mode' => $template->design_mode ?? 'classic',
            'paper_size' => $template->paper_size,
            'orientation' => $template->orientation,
            'background_image_url' => $background_image_url,
            'elements_config' => $elements_config,
            'is_active' => $template->is_active,
            'is_default' => $template->is_default
        )
    ));
}

// ==========================================
// SESSION CERTIFICATE HANDLERS
// ==========================================

/**
 * Issue session certificate to attendee
 * Checks CME/CPD attendance requirements
 */
add_action('wp_ajax_sc_issue_session_certificate', 'sc_issue_session_certificate');
function sc_issue_session_certificate() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$session_id || !$attendee_id) {
        wp_send_json_error(array('message' => __('Session ID and Attendee ID are required.', 'sc_events')));
    }

    // Get session data
    if (!class_exists('SC_Session')) {
        wp_send_json_error(array('message' => __('Sessions module not available.', 'sc_events')));
    }

    $session = SC_Session::get($session_id);
    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    // Get attendee data
    $attendee = SC_Attendee::get($attendee_id);
    if (!$attendee) {
        wp_send_json_error(array('message' => __('Attendee not found.', 'sc_events')));
    }

    // Check if session has certificate enabled
    if (empty($session['certificate_enabled'])) {
        wp_send_json_error(array('message' => __('Certificates are not enabled for this session.', 'sc_events')));
    }

    // Check attendance requirements if CME/CPD enabled
    if (!empty($session['cme_hours']) && $session['cme_hours'] > 0) {
        // Get attendance record
        if (class_exists('SC_Session_Attendance')) {
            $attendance = SC_Session_Attendance::get_by_attendee_session($attendee_id, $session_id);

            if (!$attendance || empty($attendance['check_in_time'])) {
                wp_send_json_error(array('message' => __('Attendee has not checked in to this session.', 'sc_events')));
            }

            // Check minimum attendance percentage
            $min_attendance = floatval($session['min_attendance_percentage'] ?? 80);
            $attendance_percentage = floatval($attendance['attendance_percentage'] ?? 0);

            if ($attendance_percentage < $min_attendance) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Attendee has only %d%% attendance. Minimum required: %d%%.', 'sc_events'),
                        round($attendance_percentage),
                        round($min_attendance)
                    )
                ));
            }
        }
    }

    // Get template
    if (!$template_id) {
        // Try to get session's template or event's template or default
        $template_id = $session['certificate_template_id'] ?? 0;
        if (!$template_id && !empty($session['event_id'])) {
            $event = SC_Event::get($session['event_id']);
            $template_id = $event['certificate_template_id'] ?? 0;
        }
        if (!$template_id) {
            $default_template = SC_Certificate_Template::get_default();
            $template_id = $default_template ? $default_template['id'] : 0;
        }
    }

    if (!$template_id) {
        wp_send_json_error(array('message' => __('No certificate template available.', 'sc_events')));
    }

    // Check if certificate already exists for this session
    global $wpdb;
    $certs_table = $wpdb->prefix . 'sc_certificates';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $certs_table WHERE attendee_id = %d AND session_id = %d AND status = 'issued'",
        $attendee_id,
        $session_id
    ));

    if ($existing) {
        wp_send_json_error(array(
            'message' => __('Certificate already issued for this session.', 'sc_events'),
            'certificate' => $existing
        ));
    }

    // Calculate earned CME hours
    $earned_cme = 0;
    if (!empty($session['cme_hours']) && class_exists('SC_Session_Attendance')) {
        $earned_cme = SC_Session_Attendance::calculate_cme_hours($attendee_id, $session_id);
    }

    // Prepare certificate data
    $data = array(
        'template_id' => $template_id,
        'attendee_id' => $attendee_id,
        'event_id' => $session['event_id'],
        'session_id' => $session_id,
        'attendee_name' => $attendee['name'],
        'event_title' => $session['title'], // Use session title for session certificates
        'event_date' => date('Y-m-d', strtotime($session['start_time'])),
        'cme_hours' => $earned_cme,
        'cpd_hours' => $earned_cme, // Same as CME for now
    );

    // Issue the certificate
    $certificate_id = SC_Certificate::issue($data, get_current_user_id());

    if ($certificate_id) {
        // Update session_id in certificate
        $wpdb->update(
            $certs_table,
            array('session_id' => $session_id),
            array('id' => $certificate_id)
        );

        $certificate = SC_Certificate::get($certificate_id);
        wp_send_json_success(array(
            'message' => __('Session certificate issued successfully.', 'sc_events'),
            'certificate' => $certificate,
            'cme_hours' => $earned_cme
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to issue certificate.', 'sc_events')));
    }
}

/**
 * Bulk issue session certificates
 */
add_action('wp_ajax_sc_bulk_issue_session_certificates', 'sc_bulk_issue_session_certificates');
function sc_bulk_issue_session_certificates() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $eligible_only = isset($_POST['eligible_only']) ? (bool) $_POST['eligible_only'] : true;

    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    // Get session
    if (!class_exists('SC_Session')) {
        wp_send_json_error(array('message' => __('Sessions module not available.', 'sc_events')));
    }

    $session = SC_Session::get($session_id);
    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    // Check if session has certificates enabled
    if (empty($session['certificate_enabled'])) {
        wp_send_json_error(array('message' => __('Certificates are not enabled for this session.', 'sc_events')));
    }

    // Get template
    if (!$template_id) {
        $template_id = $session['certificate_template_id'] ?? 0;
        if (!$template_id && !empty($session['event_id'])) {
            $event = SC_Event::get($session['event_id']);
            $template_id = $event['certificate_template_id'] ?? 0;
        }
        if (!$template_id) {
            $default_template = SC_Certificate_Template::get_default();
            $template_id = $default_template ? $default_template['id'] : 0;
        }
    }

    if (!$template_id) {
        wp_send_json_error(array('message' => __('No certificate template available.', 'sc_events')));
    }

    // Get all attendance records for this session
    if (!class_exists('SC_Session_Attendance')) {
        wp_send_json_error(array('message' => __('Session attendance module not available.', 'sc_events')));
    }

    global $wpdb;
    $attendance_table = $wpdb->prefix . 'sc_session_attendance';
    $certs_table = $wpdb->prefix . 'sc_certificates';

    $attendances = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $attendance_table WHERE session_id = %d AND check_in_time IS NOT NULL",
        $session_id
    ));

    if (empty($attendances)) {
        wp_send_json_error(array('message' => __('No attendees have checked in to this session.', 'sc_events')));
    }

    $min_attendance = floatval($session['min_attendance_percentage'] ?? 80);
    $issued = 0;
    $skipped = 0;
    $ineligible = 0;

    foreach ($attendances as $attendance) {
        $attendee_id = $attendance->attendee_id;

        // Check if certificate already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $certs_table WHERE attendee_id = %d AND session_id = %d AND status = 'issued'",
            $attendee_id,
            $session_id
        ));

        if ($existing) {
            $skipped++;
            continue;
        }

        // Check eligibility
        $attendance_percentage = floatval($attendance->attendance_percentage ?? 0);
        if ($eligible_only && $attendance_percentage < $min_attendance) {
            $ineligible++;
            continue;
        }

        // Get attendee
        $attendee = SC_Attendee::get($attendee_id);
        if (!$attendee) {
            $skipped++;
            continue;
        }

        // Calculate earned CME hours
        $earned_cme = SC_Session_Attendance::calculate_cme_hours($attendee_id, $session_id);

        // Prepare certificate data
        $data = array(
            'template_id' => $template_id,
            'attendee_id' => $attendee_id,
            'event_id' => $session['event_id'],
            'session_id' => $session_id,
            'attendee_name' => $attendee['name'],
            'event_title' => $session['title'],
            'event_date' => date('Y-m-d', strtotime($session['start_time'])),
            'cme_hours' => $earned_cme,
        );

        // Issue certificate
        $certificate_id = SC_Certificate::issue($data, get_current_user_id());

        if ($certificate_id) {
            // Update session_id
            $wpdb->update(
                $certs_table,
                array('session_id' => $session_id),
                array('id' => $certificate_id)
            );
            $issued++;
        } else {
            $skipped++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(
            __('Issued: %d. Skipped: %d. Ineligible: %d.', 'sc_events'),
            $issued,
            $skipped,
            $ineligible
        ),
        'issued' => $issued,
        'skipped' => $skipped,
        'ineligible' => $ineligible
    ));
}

/**
 * Get session certificate statistics
 */
add_action('wp_ajax_sc_get_session_certificate_stats', 'sc_get_session_certificate_stats');
function sc_get_session_certificate_stats() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;

    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $attendance_table = $wpdb->prefix . 'sc_session_attendance';
    $certs_table = $wpdb->prefix . 'sc_certificates';
    $sessions_table = $wpdb->prefix . 'sc_sessions';

    // Get session info
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE id = %d",
        $session_id
    ));

    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    $min_attendance = floatval($session->min_attendance_percentage ?? 80);

    // Total checked in
    $total_checked_in = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $attendance_table WHERE session_id = %d AND check_in_time IS NOT NULL",
        $session_id
    ));

    // Total eligible (meet min attendance)
    $total_eligible = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $attendance_table WHERE session_id = %d AND check_in_time IS NOT NULL AND attendance_percentage >= %f",
        $session_id,
        $min_attendance
    ));

    // Total certificates issued
    $total_issued = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $certs_table WHERE session_id = %d AND status = 'issued'",
        $session_id
    ));

    // Pending (eligible but not issued)
    $pending = max(0, $total_eligible - $total_issued);

    wp_send_json_success(array(
        'stats' => array(
            'total_checked_in' => (int) $total_checked_in,
            'total_eligible' => (int) $total_eligible,
            'total_issued' => (int) $total_issued,
            'pending' => (int) $pending,
            'min_attendance' => $min_attendance,
            'cme_hours' => floatval($session->cme_hours ?? 0)
        )
    ));
}

/**
 * Get eligible attendees for session certificate
 */
add_action('wp_ajax_sc_get_session_eligible_attendees', 'sc_get_session_eligible_attendees');
function sc_get_session_eligible_attendees() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;

    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $attendance_table = $wpdb->prefix . 'sc_session_attendance';
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $certs_table = $wpdb->prefix . 'sc_certificates';
    $sessions_table = $wpdb->prefix . 'sc_sessions';

    // Get session info
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE id = %d",
        $session_id
    ));

    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    $min_attendance = floatval($session->min_attendance_percentage ?? 80);

    // Get all attendees with their attendance and certificate status
    $attendees = $wpdb->get_results($wpdb->prepare(
        "SELECT
            a.id AS attendee_id,
            a.name,
            a.email,
            sa.check_in_time,
            sa.check_out_time,
            sa.attendance_percentage,
            sa.earned_cme_hours,
            c.id AS certificate_id,
            c.status AS certificate_status,
            c.certificate_number
        FROM $attendance_table sa
        JOIN $attendees_table a ON sa.attendee_id = a.id
        LEFT JOIN $certs_table c ON c.attendee_id = a.id AND c.session_id = %d
        WHERE sa.session_id = %d AND sa.check_in_time IS NOT NULL
        ORDER BY a.name ASC",
        $session_id,
        $session_id
    ));

    // Process each attendee
    $result = array();
    foreach ($attendees as $attendee) {
        $is_eligible = floatval($attendee->attendance_percentage) >= $min_attendance;
        $has_certificate = !empty($attendee->certificate_id);

        $result[] = array(
            'attendee_id' => (int) $attendee->attendee_id,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'check_in_time' => $attendee->check_in_time,
            'check_out_time' => $attendee->check_out_time,
            'attendance_percentage' => round(floatval($attendee->attendance_percentage), 1),
            'earned_cme_hours' => round(floatval($attendee->earned_cme_hours), 2),
            'is_eligible' => $is_eligible,
            'has_certificate' => $has_certificate,
            'certificate_id' => $attendee->certificate_id,
            'certificate_number' => $attendee->certificate_number,
            'certificate_status' => $attendee->certificate_status
        );
    }

    wp_send_json_success(array(
        'attendees' => $result,
        'min_attendance' => $min_attendance,
        'cme_hours' => floatval($session->cme_hours ?? 0)
    ));
}

// =============================================================================
// CERTIFICATE REBUILD TOOL — bulk-fix issued certs whose template assignment is
// wrong (e.g. an IDC 2026 cert that ended up bound to an IDC 2025 template).
// Admin-only. Re-points template_id and refreshes denormalized snapshot fields
// so the next download renders the correct event.
// =============================================================================

/**
 * Diagnose a single certificate by ID — returns the cert + its event/template
 * + a flag if the template's native event differs from the cert's event.
 * (Action registered at top of file.)
 */
function sc_diagnose_certificate_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Admin only.'));
    }

    global $wpdb;
    $cert_id = isset($_POST['cert_id']) ? (int) $_POST['cert_id'] : 0;
    if (!$cert_id) {
        wp_send_json_error(array('message' => 'Certificate ID required.'));
    }

    $certs_table     = $wpdb->prefix . 'sc_certificates';
    $events_table    = $wpdb->prefix . 'sc_events';
    $tpl_table       = $wpdb->prefix . 'sc_certificate_templates';
    $attendees_table = $wpdb->prefix . 'sc_attendees';

    $cert = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$certs_table} WHERE id = %d", $cert_id), ARRAY_A);
    if (!$cert) {
        wp_send_json_error(array('message' => 'Certificate not found.'));
    }

    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT title, start_date FROM {$events_table} WHERE id = %d",
        $cert['event_id']
    ), ARRAY_A);

    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$tpl_table} WHERE id = %d",
        $cert['template_id']
    ), ARRAY_A);

    $attendee = $wpdb->get_row($wpdb->prepare(
        "SELECT email FROM {$attendees_table} WHERE id = %d",
        $cert['attendee_id']
    ), ARRAY_A);

    // Templates aren't bound to events in the schema — admin chooses one per
    // issuance. Mismatch detection here just surfaces the values for the admin
    // to sanity-check by eye.
    wp_send_json_success(array(
        'cert'                    => $cert,
        'event_title'             => $event ? $event['title'] : null,
        'event_start'             => $event ? $event['start_date'] : null,
        'template_name'           => $template ? $template['name'] : null,
        'template_event_id'       => 0,
        'template_event_title'    => null,
        'template_event_mismatch' => false,
        'attendee_email'          => $attendee ? $attendee['email'] : null,
    ));
}

/**
 * Summary of templates currently used by certificates of a given event,
 * counts per template, and whether each template's native event matches.
 */
function sc_rebuild_event_summary_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Admin only.'));
    }

    global $wpdb;
    $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => 'Event ID required.'));
    }

    $certs_table  = $wpdb->prefix . 'sc_certificates';
    $events_table = $wpdb->prefix . 'sc_events';
    $tpl_table    = $wpdb->prefix . 'sc_certificate_templates';

    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title FROM {$events_table} WHERE id = %d",
        $event_id
    ), ARRAY_A);
    if (!$event) {
        wp_send_json_error(array('message' => 'Event not found.'));
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.template_id, COUNT(*) AS cert_count, t.name AS template_name
         FROM {$certs_table} c
         LEFT JOIN {$tpl_table} t ON t.id = c.template_id
         WHERE c.event_id = %d
         GROUP BY c.template_id, t.name
         ORDER BY cert_count DESC",
        $event_id
    ), ARRAY_A);

    foreach ($rows as &$r) {
        $r['cert_count']           = (int) $r['cert_count'];
        $r['template_id']          = (int) $r['template_id'];
        $r['template_event_id']    = 0;
        $r['template_event_title'] = null;
    }
    unset($r);

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$certs_table} WHERE event_id = %d",
        $event_id
    ));

    wp_send_json_success(array(
        'event_id'    => (int) $event['id'],
        'event_title' => $event['title'],
        'total'       => $total,
        'templates'   => $rows,
    ));
}

/**
 * Bulk-rebuild every certificate for an event:
 *   - (optional) reassign template_id to a new template
 *   - refresh denormalized snapshot fields (attendee_name/email, event_title,
 *     event_date) from the current source rows
 *   - invalidate any cached PDF for each affected cert
 *
 * Does NOT change certificate_number, verification_code, status, or issued_at.
 */
function sc_rebuild_event_certificates_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Admin only.'));
    }

    global $wpdb;
    $event_id    = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;

    if (!$event_id) {
        wp_send_json_error(array('message' => 'Event ID required.'));
    }

    $certs_table     = $wpdb->prefix . 'sc_certificates';
    $events_table    = $wpdb->prefix . 'sc_events';
    $tpl_table       = $wpdb->prefix . 'sc_certificate_templates';
    $attendees_table = $wpdb->prefix . 'sc_attendees';

    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title, start_date, end_date FROM {$events_table} WHERE id = %d",
        $event_id
    ), ARRAY_A);
    if (!$event) {
        wp_send_json_error(array('message' => 'Event not found.'));
    }

    if ($template_id > 0) {
        $tpl_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tpl_table} WHERE id = %d",
            $template_id
        ));
        if (!$tpl_exists) {
            wp_send_json_error(array('message' => 'Target template not found.'));
        }
    }

    // Bumping limits — bulk SQL is fast, but allow headroom for events with
    // tens of thousands of certs. Both calls are no-ops if disabled by host.
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    // Build refreshed event snapshot fields once.
    // event_date column is DATE — store only start_date.
    $event_title = (string) $event['title'];
    $event_date  = !empty($event['start_date']) ? $event['start_date'] : null;

    // ---------- 1) Bulk-update event snapshot + (optional) template_id ----------
    // One UPDATE for every cert of this event — way faster than per-row loop.
    $set_parts   = array();
    $set_values  = array();

    $set_parts[]  = 'event_title = %s';
    $set_values[] = $event_title;

    if ($event_date) {
        $set_parts[]  = 'event_date = %s';
        $set_values[] = $event_date;
    }
    if ($template_id > 0) {
        $set_parts[]  = 'template_id = %d';
        $set_values[] = $template_id;
    }
    $set_values[] = $event_id; // for WHERE

    $sql = "UPDATE {$certs_table} SET " . implode(', ', $set_parts) . " WHERE event_id = %d";
    $bulk_updated = $wpdb->query($wpdb->prepare($sql, $set_values));

    if ($bulk_updated === false) {
        wp_send_json_error(array(
            'message' => 'DB error during bulk update: ' . $wpdb->last_error,
        ));
    }

    // ---------- 2) Refresh attendee_name via JOINed UPDATE ----------
    // Pulls each cert's name from the current attendees row in one statement.
    $name_sql = "UPDATE {$certs_table} c
                 INNER JOIN {$attendees_table} a ON a.id = c.attendee_id
                 SET c.attendee_name = a.name
                 WHERE c.event_id = %d";
    $names_updated = $wpdb->query($wpdb->prepare($name_sql, $event_id));

    // ---------- 3) Cache invalidation (skip if cache dir doesn't exist) ----------
    $cache_cleared = 0;
    $upload = function_exists('wp_upload_dir') ? wp_upload_dir() : null;
    if ($upload && empty($upload['error'])) {
        $cache_dir = trailingslashit($upload['basedir']) . 'sc-certificates';
        if (is_dir($cache_dir)) {
            // Wipe everything in the cache folder for this event's certs by
            // matching the file pattern. Cheap — single glob, no per-cert query.
            $cert_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$certs_table} WHERE event_id = %d",
                $event_id
            ));
            $cert_id_set = array_flip(array_map('intval', $cert_ids));
            foreach (glob($cache_dir . '/cert-*.pdf') ?: array() as $file) {
                if (preg_match('/cert-(\d+)/', basename($file), $m)
                    && isset($cert_id_set[(int) $m[1]])) {
                    @unlink($file);
                    $cache_cleared++;
                }
            }
        }
    }

    wp_send_json_success(array(
        'updated'       => (int) $bulk_updated,
        'names_updated' => (int) ($names_updated !== false ? $names_updated : 0),
        'skipped'       => 0,
        'cache_cleared' => $cache_cleared,
        'event_id'      => $event_id,
        'template_id'   => $template_id,
    ));
}
