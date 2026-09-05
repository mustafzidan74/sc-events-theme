<?php
/**
 * SC Attendees Module
 *
 * Attendee management, check-in, and tracking
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Attendees extends SC_Base_Module {

    protected $id = 'attendees';
    protected $name = 'Attendees';
    protected $description = 'Manage event attendees, check-in, and tracking';
    protected $version = '2.0.0';
    protected $dependencies = array('events', 'tickets');
    protected $priority = 3;

    public function register_hooks() {
        add_action('init', array($this, 'register_ajax_handlers'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function init() {
        $this->log('Attendees module initialized');
    }

    public function register_ajax_handlers() {
        // CRUD
        $this->register_ajax('create', array($this, 'ajax_create_attendee'));
        $this->register_ajax('update', array($this, 'ajax_update_attendee'));
        $this->register_ajax('delete', array($this, 'ajax_delete_attendee'));
        $this->register_ajax('get', array($this, 'ajax_get_attendee'));
        $this->register_ajax('list', array($this, 'ajax_list_attendees'));

        // Check-in
        $this->register_ajax('checkin', array($this, 'ajax_checkin'));
        $this->register_ajax('checkout', array($this, 'ajax_checkout'));
        $this->register_ajax('verify_ticket', array($this, 'ajax_verify_ticket'));

        // Export
        $this->register_ajax('export', array($this, 'ajax_export_attendees'));

        // Bulk actions
        $this->register_ajax('bulk_checkin', array($this, 'ajax_bulk_checkin'));
        $this->register_ajax('resend_ticket', array($this, 'ajax_resend_ticket'));
    }

    public function register_rest_routes() {
        // Get attendees by event
        register_rest_route('sc-events/v1', '/events/(?P<event_id>\d+)/attendees', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_attendees'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));

        // Verify ticket (for scanner)
        register_rest_route('sc-events/v1', '/attendees/verify/(?P<ticket_code>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_verify_ticket'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));

        // Check-in (for scanner)
        register_rest_route('sc-events/v1', '/attendees/(?P<id>\d+)/checkin', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_checkin'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));

        // Stats
        register_rest_route('sc-events/v1', '/events/(?P<event_id>\d+)/attendees/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_stats'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }

    public function rest_permission_check() {
        return current_user_can('edit_posts');
    }

    // REST Handlers
    public function rest_get_attendees($request) {
        $event_id = $request->get_param('event_id');
        $args = array(
            'event_id' => $event_id,
            'status' => $request->get_param('status') ?: 'active',
            'limit' => $request->get_param('per_page') ?: 50,
            'offset' => $request->get_param('offset') ?: 0,
        );

        $attendees = SC_Attendee::get_all($args);
        $total = SC_Attendee::count(array('event_id' => $event_id, 'status' => $args['status']));

        return new WP_REST_Response(array(
            'attendees' => $attendees,
            'total' => $total,
        ), 200);
    }

    public function rest_verify_ticket($request) {
        $ticket_code = $request->get_param('ticket_code');
        $attendee = SC_Attendee::get_by_ticket_code($ticket_code);

        if (!$attendee) {
            return new WP_Error('not_found', 'Invalid ticket code', array('status' => 404));
        }

        $event = SC_Event::get($attendee->event_id);

        return new WP_REST_Response(array(
            'valid' => true,
            'attendee' => array(
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'ticket_name' => $attendee->ticket_name,
                'checked_in' => (bool) $attendee->checked_in,
                'checked_in_at' => $attendee->checked_in_at,
            ),
            'event' => array(
                'id' => $event->id,
                'title' => $event->title,
            ),
        ), 200);
    }

    public function rest_checkin($request) {
        $attendee_id = $request->get_param('id');
        $result = SC_Attendee::check_in($attendee_id, get_current_user_id());

        if ($result) {
            $attendee = SC_Attendee::get($attendee_id);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Check-in successful',
                'attendee' => $attendee,
            ), 200);
        }

        return new WP_Error('checkin_failed', 'Check-in failed', array('status' => 500));
    }

    public function rest_get_stats($request) {
        $event_id = $request->get_param('event_id');
        $stats = $this->get_event_stats($event_id);
        return new WP_REST_Response($stats, 200);
    }

    // AJAX Handlers
    public function ajax_create_attendee() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $data = array(
            'event_id' => intval($_POST['event_id'] ?? 0),
            'ticket_id' => intval($_POST['ticket_id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'payment_status' => sanitize_text_field($_POST['payment_status'] ?? 'pending'),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'manual'),
        );

        if (empty($data['name']) || empty($data['email']) || empty($data['event_id'])) {
            wp_send_json_error(array('message' => 'Name, Email, and Event are required'));
        }

        // Get ticket info
        $ticket = SC_Ticket::get($data['ticket_id']);
        if ($ticket) {
            $data['ticket_name'] = $ticket->name;
            $data['ticket_price'] = $ticket->price;
        }

        // Generate ticket code
        $data['ticket_code'] = $this->generate_ticket_code();

        $attendee_id = SC_Attendee::create($data);

        if ($attendee_id) {
            wp_send_json_success(array(
                'message' => 'Attendee created successfully',
                'attendee_id' => $attendee_id,
                'attendee' => SC_Attendee::get($attendee_id),
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create attendee'));
        }
    }

    public function ajax_checkin() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $attendee_id = intval($_POST['attendee_id'] ?? 0);

        if (!$attendee_id) {
            wp_send_json_error(array('message' => 'Attendee ID required'));
        }

        $attendee = SC_Attendee::get($attendee_id);

        if (!$attendee) {
            wp_send_json_error(array('message' => 'Attendee not found'));
        }

        if ($attendee->checked_in) {
            wp_send_json_error(array(
                'message' => 'Already checked in',
                'checked_in_at' => $attendee->checked_in_at,
            ));
        }

        $result = SC_Attendee::check_in($attendee_id, get_current_user_id());

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Check-in successful',
                'attendee' => SC_Attendee::get($attendee_id),
            ));
        } else {
            wp_send_json_error(array('message' => 'Check-in failed'));
        }
    }

    public function ajax_checkout() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $attendee_id = intval($_POST['attendee_id'] ?? 0);

        if (SC_Attendee::check_out($attendee_id, get_current_user_id())) {
            wp_send_json_success(array(
                'message' => 'Check-out successful',
                'attendee' => SC_Attendee::get($attendee_id),
            ));
        } else {
            wp_send_json_error(array('message' => 'Check-out failed'));
        }
    }

    public function ajax_verify_ticket() {
        $ticket_code = sanitize_text_field($_POST['ticket_code'] ?? '');

        if (empty($ticket_code)) {
            wp_send_json_error(array('message' => 'Ticket code required'));
        }

        $attendee = SC_Attendee::get_by_ticket_code($ticket_code);

        if (!$attendee) {
            wp_send_json_error(array('message' => 'Invalid ticket code'));
        }

        $event = SC_Event::get($attendee->event_id);

        wp_send_json_success(array(
            'valid' => true,
            'attendee' => $attendee,
            'event' => $event,
            'can_checkin' => !$attendee->checked_in && $attendee->payment_status === 'success',
        ));
    }

    public function ajax_list_attendees() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        $event_id = intval($_POST['event_id'] ?? 0);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $checked_in = isset($_POST['checked_in']) ? intval($_POST['checked_in']) : null;
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        $args = array(
            'event_id' => $event_id,
            'search' => $search,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        );

        if ($status) {
            $args['status'] = $status;
        }

        if ($checked_in !== null) {
            $args['checked_in'] = $checked_in;
        }

        $attendees = SC_Attendee::get_all($args);
        $total = SC_Attendee::count(array('event_id' => $event_id));

        wp_send_json_success(array(
            'attendees' => $attendees,
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ));
    }

    public function ajax_export_attendees() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $event_id = intval($_POST['event_id'] ?? 0);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        $attendees = SC_Attendee::get_all(array(
            'event_id' => $event_id,
            'limit' => 10000,
        ));

        // Generate export file
        $filename = 'attendees-' . $event_id . '-' . date('Y-m-d') . '.' . $format;

        wp_send_json_success(array(
            'download_url' => $this->generate_export_file($attendees, $format, $filename),
            'filename' => $filename,
        ));
    }

    public function ajax_resend_ticket() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $attendee_id = intval($_POST['attendee_id'] ?? 0);
        $attendee = SC_Attendee::get($attendee_id);

        if (!$attendee) {
            wp_send_json_error(array('message' => 'Attendee not found'));
        }

        // Send ticket email
        $result = sc_public_send_ticket_email($attendee_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Ticket email sent successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send email'));
        }
    }

    // Helper Methods
    private function generate_ticket_code() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    public function get_event_stats($event_id) {
        global $wpdb;
        $table = SC_Attendee::get_table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as checked_in,
                SUM(amount_paid) as total_revenue
             FROM $table
             WHERE event_id = %d AND status = 'active'",
            $event_id
        ));

        return array(
            'total' => (int) $stats->total,
            'confirmed' => (int) $stats->confirmed,
            'checked_in' => (int) $stats->checked_in,
            'pending' => (int) $stats->total - (int) $stats->confirmed,
            'checkin_rate' => $stats->confirmed > 0
                ? round(($stats->checked_in / $stats->confirmed) * 100, 1)
                : 0,
            'total_revenue' => (float) $stats->total_revenue,
        );
    }

    private function generate_export_file($attendees, $format, $filename) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/sc-exports';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $file_path = $export_dir . '/' . $filename;

        if ($format === 'csv') {
            $fp = fopen($file_path, 'w');

            // Header
            fputcsv($fp, array('Name', 'Email', 'Phone', 'Ticket', 'Status', 'Checked In', 'Check-in Time'));

            foreach ($attendees as $attendee) {
                fputcsv($fp, array(
                    $attendee->name,
                    $attendee->email,
                    $attendee->phone,
                    $attendee->ticket_name,
                    $attendee->payment_status,
                    $attendee->checked_in ? 'Yes' : 'No',
                    $attendee->checked_in_at ?: '',
                ));
            }

            fclose($fp);
        }

        return $upload_dir['baseurl'] . '/sc-exports/' . $filename;
    }
}

// Register the module
sc_modules()->register_module(new SC_Module_Attendees());
