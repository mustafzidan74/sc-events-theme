<?php
/**
 * Booths Module
 *
 * Exhibition booth management for trade shows and exhibitions
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booths_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'booths';

    /**
     * Module name
     */
    public $name = 'Booths';

    /**
     * Module description
     */
    public $description = 'Exhibition booth management for trade shows and exhibitions';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Dependencies
     */
    public $dependencies = array('events', 'companies', 'venues');

    /**
     * Priority
     */
    public $priority = 46;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load model classes
        add_action('init', array($this, 'load_classes'), 5);

        // Booth Type AJAX handlers
        $this->register_ajax('sc_booth_types_get_all', 'ajax_get_booth_types');
        $this->register_ajax('sc_booth_types_get', 'ajax_get_booth_type');
        $this->register_ajax('sc_booth_types_create', 'ajax_create_booth_type');
        $this->register_ajax('sc_booth_types_update', 'ajax_update_booth_type');
        $this->register_ajax('sc_booth_types_delete', 'ajax_delete_booth_type');

        // Booth AJAX handlers
        $this->register_ajax('sc_booths_get_all', 'ajax_get_booths');
        $this->register_ajax('sc_booths_get', 'ajax_get_booth');
        $this->register_ajax('sc_booths_create', 'ajax_create_booth');
        $this->register_ajax('sc_booths_update', 'ajax_update_booth');
        $this->register_ajax('sc_booths_delete', 'ajax_delete_booth');
        $this->register_ajax('sc_booths_bulk_create', 'ajax_bulk_create_booths');
        $this->register_ajax('sc_booths_get_available', 'ajax_get_available_booths');
        $this->register_ajax('sc_booths_get_floor_plan', 'ajax_get_floor_plan');
        $this->register_ajax('sc_booths_update_position', 'ajax_update_booth_position');

        // Booking AJAX handlers
        $this->register_ajax('sc_booth_bookings_get_all', 'ajax_get_bookings');
        $this->register_ajax('sc_booth_bookings_get', 'ajax_get_booking');
        $this->register_ajax('sc_booth_bookings_create', 'ajax_create_booking');
        $this->register_ajax('sc_booth_bookings_update', 'ajax_update_booking');
        $this->register_ajax('sc_booth_bookings_cancel', 'ajax_cancel_booking');
        $this->register_ajax('sc_booth_bookings_confirm', 'ajax_confirm_booking');
        $this->register_ajax('sc_booth_bookings_check_in', 'ajax_check_in_booking');
        $this->register_ajax('sc_booth_bookings_check_out', 'ajax_check_out_booking');
        $this->register_ajax('sc_booth_bookings_record_deposit', 'ajax_record_deposit');
        $this->register_ajax('sc_booth_bookings_record_payment', 'ajax_record_payment');

        // Statistics
        $this->register_ajax('sc_booths_get_stats', 'ajax_get_stats');

        // Get company's booked booths
        $this->register_ajax('sc_get_company_booked_booths', 'ajax_get_company_booked_booths');

        // Visitor Tracking AJAX handlers
        $this->register_ajax('sc_booth_visits_check_in', 'ajax_visitor_check_in');
        $this->register_ajax('sc_booth_visits_check_out', 'ajax_visitor_check_out');
        $this->register_ajax('sc_booth_visits_get_stats', 'ajax_get_visitor_stats');
        $this->register_ajax('sc_booth_visits_get_traffic', 'ajax_get_traffic_data');
        $this->register_ajax('sc_booth_visits_capture_lead', 'ajax_capture_lead');
        $this->register_ajax('sc_booth_visits_get_current', 'ajax_get_current_visitors');
        $this->register_ajax('sc_booth_visits_get_top_booths', 'ajax_get_top_booths');

        // Dashboard hooks
        add_filter('sc_dashboard_stats', array($this, 'add_booth_stats'));
    }

    /**
     * Every booth action changes or reads commercial data: event managers only.
     * (A nonce alone let any dashboard account — e.g. scanner staff — edit booths and payments.)
     */
    private function verify_manager() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');
        if (!SC_Event_Manager_Dashboard::is_event_manager()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
        }
    }

    /**
     * Initialize module
     */
    public function init() {
        // Additional initialization
    }

    /**
     * Load model classes
     */
    public function load_classes() {
        $classes = array(
            'SC_Booth_Type'    => 'class-sc-booth-type.php',
            'SC_Booth'         => 'class-sc-booth.php',
            'SC_Booth_Booking' => 'class-sc-booth-booking.php',
            'SC_Booth_Visit'   => 'class-sc-booth-visit.php',
        );

        foreach ($classes as $class => $file) {
            if (!class_exists($class)) {
                $path = get_template_directory() . '/inc/database/' . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        }
    }

    /**
     * Add booth stats to dashboard
     */
    public function add_booth_stats($stats) {
        global $wpdb;

        $booths_table = SC_Booth::get_table();
        $bookings_table = SC_Booth_Booking::get_table();

        $stats['booths'] = array(
            'total'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM $booths_table"),
            'available'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM $booths_table WHERE status = 'available'"),
            'booked'         => (int) $wpdb->get_var("SELECT COUNT(*) FROM $booths_table WHERE status IN ('booked', 'occupied')"),
            'total_bookings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status != 'cancelled'"),
            'revenue'        => (float) $wpdb->get_var("SELECT SUM(deposit_paid + balance_paid) FROM $bookings_table WHERE status != 'cancelled'"),
        );

        return $stats;
    }

    // =========================================
    // BOOTH TYPE AJAX HANDLERS
    // =========================================

    /**
     * Get all booth types
     */
    public function ajax_get_booth_types() {
        $this->verify_manager();

        $args = array(
            'event_id'  => intval($_POST['event_id'] ?? 0) ?: null,
            'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : null,
            'category'  => sanitize_text_field($_POST['category'] ?? ''),
            'search'    => sanitize_text_field($_POST['search'] ?? ''),
            'limit'     => intval($_POST['limit'] ?? 50),
            'offset'    => intval($_POST['offset'] ?? 0),
        );

        $result = SC_Booth_Type::get_list($args);

        wp_send_json_success(array(
            'booth_types' => $result['items'],
            'total'       => $result['total'],
            'categories'  => SC_Booth_Type::get_booth_categories(),
            'sizes'       => SC_Booth_Type::get_common_sizes(),
        ));
    }

    /**
     * Get single booth type
     */
    public function ajax_get_booth_type() {
        $this->verify_manager();

        $id = intval($_POST['booth_type_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth type ID is required', 'sc_events')));
        }

        $type = SC_Booth_Type::get($id);

        if (!$type) {
            wp_send_json_error(array('message' => __('Booth type not found', 'sc_events')));
        }

        wp_send_json_success(array('booth_type' => $type));
    }

    /**
     * Create booth type
     */
    public function ajax_create_booth_type() {
        $this->verify_manager();

        $data = array(
            'event_id'           => intval($_POST['event_id'] ?? 0),
            'name'               => sanitize_text_field($_POST['name'] ?? ''),
            'name_ar'            => sanitize_text_field($_POST['name_ar'] ?? ''),
            'description'        => sanitize_textarea_field($_POST['description'] ?? ''),
            'description_ar'     => sanitize_textarea_field($_POST['description_ar'] ?? ''),
            'size_code'          => sanitize_text_field($_POST['size_code'] ?? '3x3'),
            'width_meters'       => floatval($_POST['width_meters'] ?? 3),
            'depth_meters'       => floatval($_POST['depth_meters'] ?? 3),
            'booth_category'     => sanitize_text_field($_POST['booth_category'] ?? 'standard'),
            'base_price'         => floatval($_POST['base_price'] ?? 0),
            'deposit_amount'     => floatval($_POST['deposit_amount'] ?? 0),
            'deposit_percentage' => floatval($_POST['deposit_percentage'] ?? 0),
            'price_per_sqm'      => floatval($_POST['price_per_sqm'] ?? 0),
            'total_quantity'     => intval($_POST['total_quantity'] ?? 0),
            'is_active'          => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
            'color'              => sanitize_hex_color($_POST['color'] ?? '#3B82F6'),
            'icon'               => sanitize_text_field($_POST['icon'] ?? 'fa-store'),
            'sort_order'         => intval($_POST['sort_order'] ?? 0),
        );

        // Handle inclusions
        if (isset($_POST['inclusions']) && is_array($_POST['inclusions'])) {
            $data['inclusions'] = array_map('sanitize_text_field', $_POST['inclusions']);
        }

        if (!$data['event_id'] || !$data['name']) {
            wp_send_json_error(array('message' => __('Event and name are required', 'sc_events')));
        }

        $id = SC_Booth_Type::create($data);

        if (!$id) {
            wp_send_json_error(array('message' => __('Failed to create booth type', 'sc_events')));
        }

        $type = SC_Booth_Type::get($id);

        wp_send_json_success(array(
            'message'    => __('Booth type created successfully', 'sc_events'),
            'booth_type' => $type,
        ));
    }

    /**
     * Update booth type
     */
    public function ajax_update_booth_type() {
        $this->verify_manager();

        $id = intval($_POST['booth_type_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth type ID is required', 'sc_events')));
        }

        $data = array();

        $fields = array(
            'name', 'name_ar', 'description', 'description_ar', 'size_code',
            'booth_category', 'color', 'icon',
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        $number_fields = array(
            'width_meters', 'depth_meters', 'base_price', 'deposit_amount',
            'deposit_percentage', 'price_per_sqm', 'total_quantity', 'sort_order',
        );

        foreach ($number_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = floatval($_POST[$field]);
            }
        }

        if (isset($_POST['is_active'])) {
            $data['is_active'] = (int) $_POST['is_active'];
        }

        if (isset($_POST['inclusions']) && is_array($_POST['inclusions'])) {
            $data['inclusions'] = array_map('sanitize_text_field', $_POST['inclusions']);
        }

        $result = SC_Booth_Type::update($id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update booth type', 'sc_events')));
        }

        $type = SC_Booth_Type::get($id);

        wp_send_json_success(array(
            'message'    => __('Booth type updated successfully', 'sc_events'),
            'booth_type' => $type,
        ));
    }

    /**
     * Delete booth type
     */
    public function ajax_delete_booth_type() {
        $this->verify_manager();

        $id = intval($_POST['booth_type_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth type ID is required', 'sc_events')));
        }

        $result = SC_Booth_Type::delete($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Cannot delete booth type. It may be in use by existing booths.', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Booth type deleted successfully', 'sc_events')));
    }

    // =========================================
    // BOOTH AJAX HANDLERS
    // =========================================

    /**
     * Get all booths
     */
    public function ajax_get_booths() {
        $this->verify_manager();

        $args = array(
            'event_id'      => intval($_POST['event_id'] ?? 0) ?: null,
            'status'        => sanitize_text_field($_POST['status'] ?? ''),
            'booth_type_id' => intval($_POST['booth_type_id'] ?? 0) ?: null,
            'zone_id'       => intval($_POST['zone_id'] ?? 0) ?: null,
            'floor_level'   => isset($_POST['floor_level']) ? intval($_POST['floor_level']) : null,
            'search'        => sanitize_text_field($_POST['search'] ?? ''),
            'limit'         => intval($_POST['limit'] ?? 50),
            'offset'        => intval($_POST['offset'] ?? 0),
        );

        $result = SC_Booth::get_list($args);

        wp_send_json_success(array(
            'booths'   => $result['items'],
            'total'    => $result['total'],
            'statuses' => SC_Booth::get_statuses(),
        ));
    }

    /**
     * Get single booth
     */
    public function ajax_get_booth() {
        $this->verify_manager();

        $id = intval($_POST['id'] ?? $_POST['booth_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $booth = SC_Booth::get($id);

        if (!$booth) {
            wp_send_json_error(array('message' => __('Booth not found', 'sc_events')));
        }

        // Get booth type details
        $type = SC_Booth_Type::get($booth->booth_type_id);

        // Get current booking if any
        $booking = null;
        if ($booth->current_booking_id) {
            $booking = SC_Booth_Booking::get($booth->current_booking_id);
        }

        wp_send_json_success(array(
            'booth'      => $booth,
            'booth_type' => $type,
            'booking'    => $booking,
        ));
    }

    /**
     * Create booth
     */
    public function ajax_create_booth() {
        $this->verify_manager();

        $data = array(
            'event_id'        => intval($_POST['event_id'] ?? 0),
            'venue_id'        => intval($_POST['venue_id'] ?? 0) ?: null,
            'zone_id'         => intval($_POST['zone_id'] ?? 0) ?: null,
            'booth_type_id'   => intval($_POST['booth_type_id'] ?? 0),
            'booth_number'    => sanitize_text_field($_POST['booth_number'] ?? ''),
            'booth_name'      => sanitize_text_field($_POST['booth_name'] ?? ''),
            'booth_name_ar'   => sanitize_text_field($_POST['booth_name_ar'] ?? ''),
            'position_x'      => intval($_POST['position_x'] ?? 0),
            'position_y'      => intval($_POST['position_y'] ?? 0),
            'rotation'        => intval($_POST['rotation'] ?? 0),
            'floor_level'     => intval($_POST['floor_level'] ?? 1),
            'custom_width'    => isset($_POST['custom_width']) ? floatval($_POST['custom_width']) : null,
            'custom_depth'    => isset($_POST['custom_depth']) ? floatval($_POST['custom_depth']) : null,
            'custom_price'    => isset($_POST['custom_price']) ? floatval($_POST['custom_price']) : null,
            'status'          => sanitize_text_field($_POST['status'] ?? 'available'),
            'is_featured'     => isset($_POST['is_featured']) ? (int) $_POST['is_featured'] : 0,
            'has_electricity' => isset($_POST['has_electricity']) ? (int) $_POST['has_electricity'] : 1,
            'has_water'       => isset($_POST['has_water']) ? (int) $_POST['has_water'] : 0,
            'has_wifi'        => isset($_POST['has_wifi']) ? (int) $_POST['has_wifi'] : 1,
            'power_outlets'   => intval($_POST['power_outlets'] ?? 2),
            'max_power_kw'    => floatval($_POST['max_power_kw'] ?? 3.00),
            'notes'           => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        if (isset($_POST['special_features']) && is_array($_POST['special_features'])) {
            $data['special_features'] = array_map('sanitize_text_field', $_POST['special_features']);
        }

        if (!$data['event_id'] || !$data['booth_type_id'] || !$data['booth_number']) {
            wp_send_json_error(array('message' => __('Event, booth type, and booth number are required', 'sc_events')));
        }

        $id = SC_Booth::create($data);

        if (!$id) {
            wp_send_json_error(array('message' => __('Failed to create booth. Booth number may already exist.', 'sc_events')));
        }

        $booth = SC_Booth::get($id);

        wp_send_json_success(array(
            'message' => __('Booth created successfully', 'sc_events'),
            'booth'   => $booth,
        ));
    }

    /**
     * Update booth
     */
    public function ajax_update_booth() {
        $this->verify_manager();

        $id = intval($_POST['booth_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $data = array();

        $text_fields = array(
            'booth_number', 'booth_name', 'booth_name_ar', 'status', 'notes',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        $int_fields = array(
            'venue_id', 'zone_id', 'booth_type_id', 'position_x', 'position_y',
            'rotation', 'floor_level', 'is_featured', 'has_electricity',
            'has_water', 'has_wifi', 'power_outlets',
        );

        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = intval($_POST[$field]);
            }
        }

        $float_fields = array('custom_width', 'custom_depth', 'custom_price', 'max_power_kw');

        foreach ($float_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field] !== '' ? floatval($_POST[$field]) : null;
            }
        }

        if (isset($_POST['special_features']) && is_array($_POST['special_features'])) {
            $data['special_features'] = array_map('sanitize_text_field', $_POST['special_features']);
        }

        $result = SC_Booth::update($id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update booth', 'sc_events')));
        }

        $booth = SC_Booth::get($id);

        wp_send_json_success(array(
            'message' => __('Booth updated successfully', 'sc_events'),
            'booth'   => $booth,
        ));
    }

    /**
     * Delete booth
     */
    public function ajax_delete_booth() {
        $this->verify_manager();

        $id = intval($_POST['booth_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $result = SC_Booth::delete($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Cannot delete booth. It may have active bookings.', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Booth deleted successfully', 'sc_events')));
    }

    /**
     * Bulk create booths
     */
    public function ajax_bulk_create_booths() {
        $this->verify_manager();

        $event_id = intval($_POST['event_id'] ?? 0);
        $booth_type_id = intval($_POST['booth_type_id'] ?? 0);
        $prefix = sanitize_text_field($_POST['prefix'] ?? 'A');
        $start = intval($_POST['start'] ?? 1);
        $count = intval($_POST['count'] ?? 10);

        if (!$event_id || !$booth_type_id || !$prefix || $count < 1) {
            wp_send_json_error(array('message' => __('Event, booth type, prefix, and count are required', 'sc_events')));
        }

        if ($count > 100) {
            wp_send_json_error(array('message' => __('Cannot create more than 100 booths at once', 'sc_events')));
        }

        $common_data = array(
            'venue_id'   => intval($_POST['venue_id'] ?? 0) ?: null,
            'zone_id'    => intval($_POST['zone_id'] ?? 0) ?: null,
            'floor_level' => intval($_POST['floor_level'] ?? 1),
        );

        $created_ids = SC_Booth::bulk_create($event_id, $booth_type_id, $prefix, $start, $count, $common_data);

        wp_send_json_success(array(
            'message' => sprintf(__('%d booths created successfully', 'sc_events'), count($created_ids)),
            'count'   => count($created_ids),
            'ids'     => $created_ids,
        ));
    }

    /**
     * Get available booths
     */
    public function ajax_get_available_booths() {
        $this->verify_manager();

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $args = array(
            'booth_type_id' => intval($_POST['booth_type_id'] ?? 0) ?: null,
            'zone_id'       => intval($_POST['zone_id'] ?? 0) ?: null,
            'floor_level'   => isset($_POST['floor_level']) ? intval($_POST['floor_level']) : null,
        );

        $booths = SC_Booth::get_available($event_id, $args);

        wp_send_json_success(array('booths' => $booths));
    }

    /**
     * Get floor plan data
     */
    public function ajax_get_floor_plan() {
        $this->verify_manager();

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $floor_level = !empty($_POST['floor_level']) ? intval($_POST['floor_level']) : null;

        $booths = SC_Booth::get_floor_plan_data($event_id, $floor_level);
        $stats = SC_Booth::get_stats($event_id);
        $statuses = SC_Booth::get_statuses();

        wp_send_json_success(array(
            'booths'   => $booths,
            'stats'    => $stats,
            'statuses' => $statuses,
        ));
    }

    /**
     * Update booth position (for drag-drop on floor plan)
     */
    public function ajax_update_booth_position() {
        $this->verify_manager();

        $id = intval($_POST['id'] ?? $_POST['booth_id'] ?? 0);
        $position_x = intval($_POST['position_x'] ?? 0);
        $position_y = intval($_POST['position_y'] ?? 0);
        $rotation = intval($_POST['rotation'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $result = SC_Booth::update($id, array(
            'position_x' => $position_x,
            'position_y' => $position_y,
            'rotation'   => $rotation,
        ));

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update booth position', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Position updated', 'sc_events')));
    }

    // =========================================
    // BOOKING AJAX HANDLERS
    // =========================================

    /**
     * Get all bookings
     */
    public function ajax_get_bookings() {
        $this->verify_manager();

        $args = array(
            'event_id'       => intval($_POST['event_id'] ?? 0) ?: null,
            'booth_id'       => intval($_POST['booth_id'] ?? 0) ?: null,
            'company_id'     => intval($_POST['company_id'] ?? 0) ?: null,
            'status'         => sanitize_text_field($_POST['status'] ?? ''),
            'payment_status' => sanitize_text_field($_POST['payment_status'] ?? ''),
            'search'         => sanitize_text_field($_POST['search'] ?? ''),
            'date_from'      => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to'        => sanitize_text_field($_POST['date_to'] ?? ''),
            'limit'          => intval($_POST['limit'] ?? 50),
            'offset'         => intval($_POST['offset'] ?? 0),
        );

        $result = SC_Booth_Booking::get_list($args);

        wp_send_json_success(array(
            'bookings'         => $result['items'],
            'total'            => $result['total'],
            'statuses'         => SC_Booth_Booking::get_statuses(),
            'payment_statuses' => SC_Booth_Booking::get_payment_statuses(),
        ));
    }

    /**
     * Get single booking
     */
    public function ajax_get_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);
        $ref = sanitize_text_field($_POST['booking_ref'] ?? '');

        if (!$id && !$ref) {
            wp_send_json_error(array('message' => __('Booking ID or reference is required', 'sc_events')));
        }

        $booking = $id ? SC_Booth_Booking::get($id) : SC_Booth_Booking::get_by_ref($ref);

        if (!$booking) {
            wp_send_json_error(array('message' => __('Booking not found', 'sc_events')));
        }

        // Get related data
        $booth = SC_Booth::get($booking->booth_id);

        wp_send_json_success(array(
            'booking' => $booking,
            'booth'   => $booth,
        ));
    }

    /**
     * Create booking
     */
    public function ajax_create_booking() {
        $this->verify_manager();

        $data = array(
            'event_id'              => intval($_POST['event_id'] ?? 0),
            'booth_id'              => intval($_POST['booth_id'] ?? 0),
            'company_attendee_id'   => intval($_POST['company_attendee_id'] ?? 0),
            'start_date'            => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'              => sanitize_text_field($_POST['end_date'] ?? ''),
            'base_price'            => floatval($_POST['base_price'] ?? 0),
            'extras_price'          => floatval($_POST['extras_price'] ?? 0),
            'discount_amount'       => floatval($_POST['discount_amount'] ?? 0),
            'tax_amount'            => floatval($_POST['tax_amount'] ?? 0),
            'total_amount'          => floatval($_POST['total_amount'] ?? 0),
            'deposit_required'      => floatval($_POST['deposit_required'] ?? 0),
            'balance_due_date'      => sanitize_text_field($_POST['balance_due_date'] ?? ''),
            'commercial_registry_no' => sanitize_text_field($_POST['commercial_registry_no'] ?? ''),
            'vat_number'            => sanitize_text_field($_POST['vat_number'] ?? ''),
            'company_legal_name'    => sanitize_text_field($_POST['company_legal_name'] ?? ''),
            'setup_date'            => sanitize_text_field($_POST['setup_date'] ?? ''),
            'teardown_date'         => sanitize_text_field($_POST['teardown_date'] ?? ''),
            'special_requests'      => sanitize_textarea_field($_POST['special_requests'] ?? ''),
            'notes'                 => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        // Optional status fields
        if (!empty($_POST['status'])) {
            $allowed_statuses = array('pending', 'confirmed');
            if (in_array($_POST['status'], $allowed_statuses)) {
                $data['status'] = sanitize_text_field($_POST['status']);
            }
        }

        if (!empty($_POST['payment_status'])) {
            $allowed_payment = array('pending', 'deposit_paid', 'fully_paid');
            if (in_array($_POST['payment_status'], $allowed_payment)) {
                $data['payment_status'] = sanitize_text_field($_POST['payment_status']);
            }
        }

        if (isset($_POST['selected_extras']) && is_array($_POST['selected_extras'])) {
            $data['selected_extras'] = array_map('sanitize_text_field', $_POST['selected_extras']);
        }

        if (!$data['event_id'] || !$data['booth_id'] || !$data['company_attendee_id']) {
            wp_send_json_error(array('message' => __('Event, booth, and company are required', 'sc_events')));
        }

        // Check booth availability
        $booth = SC_Booth::get($data['booth_id']);
        if (!$booth || $booth->status !== 'available') {
            wp_send_json_error(array('message' => __('This booth is not available for booking', 'sc_events')));
        }

        $id = SC_Booth_Booking::create($data);

        if (!$id) {
            wp_send_json_error(array('message' => __('Failed to create booking', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Booking created successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Update booking
     */
    public function ajax_update_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booking ID is required', 'sc_events')));
        }

        $data = array();

        $text_fields = array(
            'start_date', 'end_date', 'balance_due_date', 'setup_date', 'teardown_date',
            'commercial_registry_no', 'vat_number', 'company_legal_name',
            'special_requests', 'notes', 'internal_notes',
            'status', 'payment_status',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Integer fields
        $int_fields = array('company_attendee_id');
        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = intval($_POST[$field]);
            }
        }

        $float_fields = array(
            'base_price', 'extras_price', 'discount_amount', 'tax_amount',
            'total_amount', 'deposit_required', 'deposit_paid', 'balance_paid',
        );

        foreach ($float_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = floatval($_POST[$field]);
            }
        }

        if (isset($_POST['selected_extras']) && is_array($_POST['selected_extras'])) {
            $data['selected_extras'] = array_map('sanitize_text_field', $_POST['selected_extras']);
        }

        $result = SC_Booth_Booking::update($id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update booking', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Booking updated successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Cancel booking
     */
    public function ajax_cancel_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$id) {
            wp_send_json_error(array('message' => __('Booking ID is required', 'sc_events')));
        }

        $result = SC_Booth_Booking::cancel($id, $reason);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to cancel booking', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Booking cancelled successfully', 'sc_events')));
    }

    /**
     * Confirm booking
     */
    public function ajax_confirm_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booking ID is required', 'sc_events')));
        }

        $result = SC_Booth_Booking::confirm($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to confirm booking', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Booking confirmed successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Check in exhibitor
     */
    public function ajax_check_in_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booking ID is required', 'sc_events')));
        }

        $result = SC_Booth_Booking::check_in($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to check in', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Exhibitor checked in successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Check out exhibitor
     */
    public function ajax_check_out_booking() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Booking ID is required', 'sc_events')));
        }

        $result = SC_Booth_Booking::check_out($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to check out', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Exhibitor checked out successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Record deposit payment
     */
    public function ajax_record_deposit() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0) ?: null;

        if (!$id || $amount <= 0) {
            wp_send_json_error(array('message' => __('Booking ID and amount are required', 'sc_events')));
        }

        $result = SC_Booth_Booking::record_deposit($id, $amount, $payment_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to record deposit', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Deposit recorded successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    /**
     * Record balance payment
     */
    public function ajax_record_payment() {
        $this->verify_manager();

        $id = intval($_POST['booking_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0) ?: null;

        if (!$id || $amount <= 0) {
            wp_send_json_error(array('message' => __('Booking ID and amount are required', 'sc_events')));
        }

        $result = SC_Booth_Booking::record_balance_payment($id, $amount, $payment_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to record payment', 'sc_events')));
        }

        $booking = SC_Booth_Booking::get($id);

        wp_send_json_success(array(
            'message' => __('Payment recorded successfully', 'sc_events'),
            'booking' => $booking,
        ));
    }

    // =========================================
    // STATISTICS
    // =========================================

    /**
     * Get booth statistics
     */
    public function ajax_get_stats() {
        $this->verify_manager();

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $booth_stats = SC_Booth::get_stats($event_id);
        $type_stats = SC_Booth_Type::get_stats($event_id);
        $booking_stats = SC_Booth_Booking::get_stats($event_id);

        wp_send_json_success(array(
            'booths'   => $booth_stats,
            'types'    => $type_stats,
            'bookings' => $booking_stats,
        ));
    }

    /**
     * Get booths booked by a specific company attendee for an event
     */
    public function ajax_get_company_booked_booths() {
        $this->verify_manager();

        global $wpdb;

        $event_id = intval($_POST['event_id'] ?? 0);
        $company_attendee_id = intval($_POST['company_attendee_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        if (!$company_attendee_id) {
            wp_send_json_success(array(
                'booths' => array(),
                'count'  => 0,
                'message' => __('No company selected', 'sc_events'),
            ));
            return;
        }

        $bookings_table = $wpdb->prefix . 'sc_booth_bookings';
        $booths_table = $wpdb->prefix . 'sc_booths';
        $types_table = $wpdb->prefix . 'sc_booth_types';

        $query = $wpdb->prepare(
            "SELECT bb.id as booking_id, bb.status as booking_status, bb.payment_status,
                    b.id as booth_id, b.booth_number, b.booth_name,
                    bt.name as type_name
             FROM {$bookings_table} bb
             INNER JOIN {$booths_table} b ON bb.booth_id = b.id
             LEFT JOIN {$types_table} bt ON b.booth_type_id = bt.id
             WHERE bb.event_id = %d
             AND bb.company_attendee_id = %d
             AND bb.status NOT IN ('cancelled', 'rejected', 'no_show')
             ORDER BY b.booth_number ASC",
            $event_id,
            $company_attendee_id
        );

        $booked_booths = $wpdb->get_results($query);

        $booths = array();
        foreach ($booked_booths as $b) {
            $label = $b->booth_number;
            if ($b->type_name) {
                $label .= ' - ' . $b->type_name;
            }
            $booths[] = array(
                'booking_id'     => $b->booking_id,
                'booth_id'       => $b->booth_id,
                'booth_number'   => $b->booth_number,
                'booth_name'     => $b->booth_name,
                'type_name'      => $b->type_name,
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
                'label'          => $label,
            );
        }

        wp_send_json_success(array(
            'booths' => $booths,
            'count'  => count($booths),
        ));
    }

    // =========================================
    // VISITOR TRACKING AJAX HANDLERS
    // =========================================

    /**
     * Check in visitor to booth
     */
    public function ajax_visitor_check_in() {
        $this->verify_manager();

        $data = array(
            'event_id'         => intval($_POST['event_id'] ?? 0),
            'booth_id'         => intval($_POST['booth_id'] ?? 0),
            'attendee_id'      => intval($_POST['attendee_id'] ?? 0) ?: null,
            'visitor_type'     => sanitize_text_field($_POST['visitor_type'] ?? 'attendee'),
            'interaction_type' => sanitize_text_field($_POST['interaction_type'] ?? 'browse'),
            'interest_level'   => sanitize_text_field($_POST['interest_level'] ?? 'medium'),
            'scan_method'      => sanitize_text_field($_POST['scan_method'] ?? 'qr'),
            'notes'            => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        // Get booking_id from booth if exists
        $booth = SC_Booth::get($data['booth_id']);
        if ($booth && $booth->current_booking_id) {
            $data['booking_id'] = $booth->current_booking_id;
        }

        if (!$data['event_id'] || !$data['booth_id']) {
            wp_send_json_error(array('message' => __('Event and booth are required', 'sc_events')));
        }

        $id = SC_Booth_Visit::check_in($data);

        if (!$id) {
            wp_send_json_error(array('message' => __('Failed to record visit', 'sc_events')));
        }

        wp_send_json_success(array(
            'message'  => __('Visitor checked in', 'sc_events'),
            'visit_id' => $id,
        ));
    }

    /**
     * Check out visitor from booth
     */
    public function ajax_visitor_check_out() {
        $this->verify_manager();

        $id = intval($_POST['visit_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Visit ID is required', 'sc_events')));
        }

        // Update interaction if provided
        $interaction_type = sanitize_text_field($_POST['interaction_type'] ?? '');
        $interest_level = sanitize_text_field($_POST['interest_level'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if ($interaction_type || $interest_level) {
            SC_Booth_Visit::update_interaction($id, $interaction_type, $interest_level, $notes);
        }

        $result = SC_Booth_Visit::check_out($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to check out', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Visitor checked out', 'sc_events')));
    }

    /**
     * Get visitor statistics for booth
     */
    public function ajax_get_visitor_stats() {
        $this->verify_manager();

        $booth_id = intval($_POST['booth_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');

        if (!$booth_id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $stats = SC_Booth_Visit::get_booth_stats($booth_id, $date ?: null);
        $visitor_types = SC_Booth_Visit::get_visitor_types($booth_id, $date ?: null);
        $interaction_types = SC_Booth_Visit::get_interaction_types($booth_id, $date ?: null);

        wp_send_json_success(array(
            'stats'             => $stats,
            'visitor_types'     => $visitor_types,
            'interaction_types' => $interaction_types,
        ));
    }

    /**
     * Get traffic data for booth
     */
    public function ajax_get_traffic_data() {
        $this->verify_manager();

        $booth_id = intval($_POST['booth_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        if (!$booth_id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $hourly = SC_Booth_Visit::get_hourly_traffic($booth_id, $date);

        $daily = array();
        if ($date_from && $date_to) {
            $daily = SC_Booth_Visit::get_daily_traffic($booth_id, $date_from, $date_to);
        }

        wp_send_json_success(array(
            'hourly' => $hourly,
            'daily'  => $daily,
        ));
    }

    /**
     * Capture lead from visit
     */
    public function ajax_capture_lead() {
        $this->verify_manager();

        $visit_id = intval($_POST['visit_id'] ?? 0);

        if (!$visit_id) {
            wp_send_json_error(array('message' => __('Visit ID is required', 'sc_events')));
        }

        $lead_data = array(
            'name'    => sanitize_text_field($_POST['lead_name'] ?? ''),
            'email'   => sanitize_email($_POST['lead_email'] ?? ''),
            'phone'   => sanitize_text_field($_POST['lead_phone'] ?? ''),
            'company' => sanitize_text_field($_POST['lead_company'] ?? ''),
            'notes'   => sanitize_textarea_field($_POST['lead_notes'] ?? ''),
        );

        $result = SC_Booth_Visit::capture_lead($visit_id, $lead_data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to capture lead', 'sc_events')));
        }

        wp_send_json_success(array('message' => __('Lead captured successfully', 'sc_events')));
    }

    /**
     * Get current visitors in booth
     */
    public function ajax_get_current_visitors() {
        $this->verify_manager();

        $booth_id = intval($_POST['booth_id'] ?? 0);

        if (!$booth_id) {
            wp_send_json_error(array('message' => __('Booth ID is required', 'sc_events')));
        }

        $visitors = SC_Booth_Visit::get_current_visitors($booth_id);

        wp_send_json_success(array('visitors' => $visitors));
    }

    /**
     * Get top booths by visits
     */
    public function ajax_get_top_booths() {
        $this->verify_manager();

        $event_id = intval($_POST['event_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 10);
        $date = sanitize_text_field($_POST['date'] ?? '');

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $top_booths = SC_Booth_Visit::get_top_booths($event_id, $limit, $date ?: null);
        $event_stats = SC_Booth_Visit::get_event_stats($event_id, $date ?: null);

        wp_send_json_success(array(
            'top_booths'  => $top_booths,
            'event_stats' => $event_stats,
        ));
    }
}

// Register the module
sc_modules()->register_module(new SC_Booths_Module());
