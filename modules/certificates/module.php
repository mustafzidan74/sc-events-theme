<?php
/**
 * SC Certificates Module
 *
 * Certificate templates and issuance
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Certificates extends SC_Base_Module {

    protected $id = 'certificates';
    protected $name = 'Certificates';
    protected $description = 'Create and issue event certificates to attendees';
    protected $version = '2.0.0';
    protected $dependencies = array('events', 'attendees');
    protected $priority = 5;

    public function register_hooks() {
        add_action('init', array($this, 'register_ajax_handlers'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Auto-issue certificate after check-in (if enabled)
        add_action('sc_attendee_checked_in', array($this, 'maybe_auto_issue'), 10, 2);
    }

    public function init() {
        $this->log('Certificates module initialized');
    }

    public function register_ajax_handlers() {
        // Templates
        $this->register_ajax('create_template', array($this, 'ajax_create_template'));
        $this->register_ajax('update_template', array($this, 'ajax_update_template'));
        $this->register_ajax('delete_template', array($this, 'ajax_delete_template'));
        $this->register_ajax('get_templates', array($this, 'ajax_get_templates'));
        $this->register_ajax('preview_template', array($this, 'ajax_preview_template'));

        // Certificates
        $this->register_ajax('issue', array($this, 'ajax_issue_certificate'));
        $this->register_ajax('bulk_issue', array($this, 'ajax_bulk_issue'));
        $this->register_ajax('revoke', array($this, 'ajax_revoke_certificate'));
        $this->register_ajax('resend', array($this, 'ajax_resend_certificate'));
        $this->register_ajax('download', array($this, 'ajax_download_certificate'));
        $this->register_ajax('verify', array($this, 'ajax_verify_certificate'), true);
    }

    public function register_rest_routes() {
        // Verify certificate (public)
        register_rest_route('sc-events/v1', '/certificates/verify/(?P<code>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_verify_certificate'),
            'permission_callback' => '__return_true',
        ));

        // Get certificate by ID
        register_rest_route('sc-events/v1', '/certificates/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_certificate'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }

    public function rest_permission_check() {
        return current_user_can('edit_posts');
    }

    // REST Handlers
    public function rest_verify_certificate($request) {
        $code = $request->get_param('code');
        $certificate = SC_Certificate::get_by_verification_code($code);

        if (!$certificate) {
            return new WP_REST_Response(array(
                'valid' => false,
                'message' => 'Certificate not found',
            ), 404);
        }

        if ($certificate->status === 'revoked') {
            return new WP_REST_Response(array(
                'valid' => false,
                'message' => 'Certificate has been revoked',
            ), 200);
        }

        return new WP_REST_Response(array(
            'valid' => true,
            'certificate' => array(
                'number' => $certificate->certificate_number,
                'attendee_name' => $certificate->attendee_name,
                'event_title' => $certificate->event_title,
                'event_date' => $certificate->event_date,
                'issued_at' => $certificate->issued_at,
            ),
        ), 200);
    }

    public function rest_get_certificate($request) {
        $certificate = SC_Certificate::get($request->get_param('id'));

        if (!$certificate) {
            return new WP_Error('not_found', 'Certificate not found', array('status' => 404));
        }

        return new WP_REST_Response($certificate, 200);
    }

    // AJAX Handlers - Templates
    public function ajax_create_template() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'slug' => sanitize_title($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'design_mode' => sanitize_text_field($_POST['design_mode'] ?? 'classic'),
            'orientation' => sanitize_text_field($_POST['orientation'] ?? 'landscape'),
            'paper_size' => sanitize_text_field($_POST['paper_size'] ?? 'A4'),
            'background_image' => intval($_POST['background_image'] ?? 0),
            'html_template' => wp_kses_post($_POST['html_template'] ?? ''),
            'css_styles' => sanitize_textarea_field($_POST['css_styles'] ?? ''),
            'created_by' => get_current_user_id(),
        );

        $template_id = SC_Certificate_Template::create($data);

        if ($template_id) {
            wp_send_json_success(array(
                'message' => 'Template created successfully',
                'template_id' => $template_id,
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create template'));
        }
    }

    public function ajax_get_templates() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        $templates = SC_Certificate_Template::get_all();
        wp_send_json_success(array('templates' => $templates));
    }

    public function ajax_preview_template() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        $template_id = intval($_POST['template_id'] ?? 0);
        $template = SC_Certificate_Template::get($template_id);

        if (!$template) {
            wp_send_json_error(array('message' => 'Template not found'));
        }

        // Generate preview with sample data
        $preview_data = array(
            'attendee_name' => 'John Doe',
            'event_title' => 'Sample Event Title',
            'event_date' => current_time('F j, Y'),
            'certificate_number' => 'CERT-SAMPLE-001',
        );

        $html = $this->render_certificate_html($template, $preview_data);

        wp_send_json_success(array('html' => $html));
    }

    // AJAX Handlers - Certificates
    public function ajax_issue_certificate() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $attendee_id = intval($_POST['attendee_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);

        if (!$attendee_id) {
            wp_send_json_error(array('message' => 'Attendee ID required'));
        }

        $result = $this->issue_certificate($attendee_id, $template_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Certificate issued successfully',
            'certificate' => SC_Certificate::get($result),
        ));
    }

    public function ajax_bulk_issue() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $event_id = intval($_POST['event_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);
        $only_checked_in = isset($_POST['only_checked_in']) ? (bool) $_POST['only_checked_in'] : true;

        if (!$event_id) {
            wp_send_json_error(array('message' => 'Event ID required'));
        }

        $args = array('event_id' => $event_id, 'payment_status' => 'success');
        if ($only_checked_in) {
            $args['checked_in'] = 1;
        }

        $attendees = SC_Attendee::get_all($args);

        $issued = 0;
        $skipped = 0;
        $errors = array();

        foreach ($attendees as $attendee) {
            // Check if already has certificate
            $existing = SC_Certificate::get_by_attendee($attendee->id);
            if ($existing) {
                $skipped++;
                continue;
            }

            $result = $this->issue_certificate($attendee->id, $template_id);

            if (is_wp_error($result)) {
                $errors[] = $attendee->name . ': ' . $result->get_error_message();
            } else {
                $issued++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('Issued: %d, Skipped: %d', $issued, $skipped),
            'issued' => $issued,
            'skipped' => $skipped,
            'errors' => $errors,
        ));
    }

    public function ajax_verify_certificate() {
        $code = sanitize_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error(array('message' => 'Verification code required'));
        }

        $certificate = SC_Certificate::get_by_verification_code($code);

        if (!$certificate) {
            wp_send_json_error(array('message' => 'Certificate not found'));
        }

        if ($certificate->status === 'revoked') {
            wp_send_json_error(array('message' => 'Certificate has been revoked'));
        }

        wp_send_json_success(array(
            'valid' => true,
            'certificate' => array(
                'number' => $certificate->certificate_number,
                'attendee_name' => $certificate->attendee_name,
                'event_title' => $certificate->event_title,
                'event_date' => $certificate->event_date,
                'issued_at' => $certificate->issued_at,
            ),
        ));
    }

    public function ajax_download_certificate() {
        $certificate_id = intval($_GET['id'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        $certificate = SC_Certificate::get($certificate_id);

        if (!$certificate) {
            wp_die('Certificate not found');
        }

        // Verify token or user permission
        if (!current_user_can('edit_posts')) {
            // Check if token matches
            $expected_token = md5($certificate->verification_code . $certificate->attendee_id);
            if ($token !== $expected_token) {
                wp_die('Unauthorized');
            }
        }

        // Generate or get existing PDF
        $pdf_url = $this->generate_pdf($certificate);

        if ($pdf_url) {
            // Update download count
            SC_Certificate::increment_download_count($certificate_id);
            wp_redirect($pdf_url);
            exit;
        }

        wp_die('Failed to generate certificate');
    }

    // Certificate Issuance
    public function issue_certificate($attendee_id, $template_id = 0) {
        $attendee = SC_Attendee::get($attendee_id);

        if (!$attendee) {
            return new WP_Error('not_found', 'Attendee not found');
        }

        $event = SC_Event::get($attendee->event_id);

        if (!$event) {
            return new WP_Error('not_found', 'Event not found');
        }

        // Get template
        if (!$template_id && $event->certificate_template_id) {
            $template_id = $event->certificate_template_id;
        }

        if (!$template_id) {
            // Get default template
            $template = SC_Certificate_Template::get_default();
            if ($template) {
                $template_id = $template->id;
            }
        }

        if (!$template_id) {
            return new WP_Error('no_template', 'No certificate template available');
        }

        // Check if already issued
        $existing = SC_Certificate::get_by_attendee($attendee_id);
        if ($existing) {
            return new WP_Error('already_issued', 'Certificate already issued');
        }

        // Generate certificate number
        $cert_number = $this->generate_certificate_number($event->id);
        $verification_code = $this->generate_verification_code();

        $data = array(
            'certificate_number' => $cert_number,
            'verification_code' => $verification_code,
            'template_id' => $template_id,
            'attendee_id' => $attendee_id,
            'event_id' => $event->id,
            'attendee_name' => $attendee->name,
            'event_title' => $event->title,
            'event_date' => $event->start_date,
            'issued_by' => get_current_user_id(),
            'issued_at' => current_time('mysql'),
        );

        $certificate_id = SC_Certificate::create($data);

        if (!$certificate_id) {
            return new WP_Error('create_failed', 'Failed to create certificate');
        }

        // Generate PDF
        $this->generate_pdf(SC_Certificate::get($certificate_id));

        // Send email if enabled
        $this->send_certificate_email($certificate_id);

        return $certificate_id;
    }

    public function maybe_auto_issue($attendee_id, $checked_in_by) {
        // This runs inside a check-in: nothing here may stop the check-in itself.
        // (It used to call SC_Certificate::get_by_attendee()/create(), which don't
        // exist, so every dashboard check-in on an auto-issue event ended in a fatal error.)
        try {
            $attendee = SC_Attendee::get($attendee_id);
            if (!$attendee || !empty($attendee->workshop_id)) {
                return;
            }
            $event = SC_Event::get($attendee->event_id);
            if (!$event || empty($event->enable_certificates) || empty($event->auto_issue_certificate)) {
                return;
            }
            // Conditions a check-in can't satisfy yet are left to the normal certificate flow.
            if (!empty($event->certificate_require_checkout) || !empty($event->certificate_require_event_ended)) {
                return;
            }
            if (SC_Certificate::get_by_attendee_event($attendee_id, $event->id)) {
                return;
            }
            $template_id = (int) $event->certificate_template_id;
            if (!$template_id && class_exists('SC_Certificate_Template')) {
                $default = SC_Certificate_Template::get_default();
                $template_id = $default ? (int) (is_object($default) ? $default->id : ($default['id'] ?? 0)) : 0;
            }
            if (!$template_id) {
                return;
            }
            SC_Certificate::issue(array(
                'template_id'   => $template_id,
                'attendee_id'   => (int) $attendee->id,
                'event_id'      => (int) $event->id,
                'attendee_name' => $attendee->name,
                'event_title'   => $event->title,
                'event_date'    => $event->start_date,
                'issued_by'     => get_current_user_id(),
            ));
        } catch (Throwable $e) {
            error_log('SC certificates auto-issue skipped: ' . $e->getMessage());
        }
    }

    // Helpers
    private function generate_certificate_number($event_id) {
        $prefix = 'CERT';
        $year = date('Y');
        $count = SC_Certificate::count(array('event_id' => $event_id)) + 1;
        return sprintf('%s-%d-%d-%04d', $prefix, $event_id, $year, $count);
    }

    private function generate_verification_code() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
    }

    private function render_certificate_html($template, $data) {
        $html = $template->html_template;

        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
            $html = str_replace('{' . $key . '}', $value, $html);
        }

        return $html;
    }

    private function generate_pdf($certificate) {
        // Use existing PDF generator
        if (class_exists('SC_Certificate_PDF')) {
            return SC_Certificate_PDF::generate_pdf($certificate->id);
        }
        return null;
    }

    private function send_certificate_email($certificate_id) {
        $certificate = SC_Certificate::get($certificate_id);
        $attendee = SC_Attendee::get($certificate->attendee_id);

        if (!$attendee || !$attendee->email) {
            return false;
        }

        $download_token = md5($certificate->verification_code . $certificate->attendee_id);
        $download_url = add_query_arg(array(
            'action' => 'sc_certificates_download',
            'id' => $certificate_id,
            'token' => $download_token,
        ), admin_url('admin-ajax.php'));

        $subject = sprintf(__('Your Certificate for %s', 'sc_events'), $certificate->event_title);

        $message = sprintf(
            __("Dear %s,\n\nYour certificate for %s is ready!\n\nCertificate Number: %s\nVerification Code: %s\n\nDownload your certificate: %s\n\nThank you!", 'sc_events'),
            $certificate->attendee_name,
            $certificate->event_title,
            $certificate->certificate_number,
            $certificate->verification_code,
            $download_url
        );

        $result = wp_mail($attendee->email, $subject, $message);

        if ($result) {
            SC_Certificate::update($certificate_id, array(
                'email_sent' => 1,
                'email_sent_at' => current_time('mysql'),
            ));
        }

        return $result;
    }
}

// Register the module
sc_modules()->register_module(new SC_Module_Certificates());
