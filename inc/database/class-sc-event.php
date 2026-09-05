<?php
/**
 * SC Event Model Class
 *
 * Data Access Layer for Events table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Event {

    /**
     * Table name
     */
    private static $table;

    /**
     * Get table name
     */
    public static function get_table() {
        global $wpdb;
        if (!self::$table) {
            self::$table = $wpdb->prefix . 'sc_events';
        }
        return self::$table;
    }

    /**
     * Get single event by ID
     *
     * @param int $id Event ID
     * @return object|null Event object or null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($event) {
            $event = self::hydrate($event);
        }

        return $event;
    }

    /**
     * Get event by slug
     *
     * @param string $slug Event slug
     * @return object|null
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = self::get_table();

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($event) {
            $event = self::hydrate($event);
        }

        return $event;
    }

    /**
     * Get event by WordPress post ID (for backward compatibility)
     *
     * @param int $post_id WordPress post ID
     * @return object|null
     */
    public static function get_by_post_id($post_id) {
        global $wpdb;
        $table = self::get_table();

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE wp_post_id = %d",
            $post_id
        ));

        if ($event) {
            $event = self::hydrate($event);
        }

        return $event;
    }

    /**
     * Get all events with filtering
     *
     * @param array $args Query arguments
     * @return array Array of event objects
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'        => 'publish',
            'author_id'     => null,
            'location_type' => null,
            'start_date'    => null,
            'end_date'      => null,
            'search'        => null,
            'orderby'       => 'start_date',
            'order'         => 'ASC',
            'limit'         => 20,
            'offset'        => 0,
            'upcoming_only' => false,
            'past_only'     => false,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        // Status filter
        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        // Author filter
        if ($args['author_id']) {
            $where[] = 'author_id = %d';
            $values[] = $args['author_id'];
        }

        // Location type filter
        if ($args['location_type']) {
            $where[] = 'location_type = %s';
            $values[] = $args['location_type'];
        }

        // Date range filter
        if ($args['start_date']) {
            $where[] = 'start_date >= %s';
            $values[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where[] = 'end_date <= %s';
            $values[] = $args['end_date'];
        }

        // Upcoming events only
        if ($args['upcoming_only']) {
            $where[] = 'start_date >= %s';
            $values[] = current_time('Y-m-d');
        }

        // Past events only
        if ($args['past_only']) {
            $where[] = 'end_date < %s';
            $values[] = current_time('Y-m-d');
        }

        // Search
        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s OR venue_name LIKE %s OR venue_address LIKE %s OR venue_city LIKE %s OR venue_country LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Filter by wp_post_ids (for category filtering via WP posts)
        if (!empty($args['wp_post_ids']) && is_array($args['wp_post_ids'])) {
            $id_placeholders = implode(',', array_fill(0, count($args['wp_post_ids']), '%d'));
            $where[] = "wp_post_id IN ($id_placeholders)";
            $values = array_merge($values, array_map('intval', $args['wp_post_ids']));
        }

        // Filter by category_id (via sc_event_categories junction table)
        if (!empty($args['category_id'])) {
            $cat_table = $wpdb->prefix . 'sc_event_categories';
            $where[] = "id IN (SELECT event_id FROM $cat_table WHERE category_id = %d)";
            $values[] = intval($args['category_id']);
        }

        // Build WHERE clause
        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = array('id', 'title', 'start_date', 'end_date', 'created_at', 'total_sold');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'start_date';

        // Sanitize order
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Build and execute query
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $events = $wpdb->get_results($sql);

        // Hydrate each event
        foreach ($events as &$event) {
            $event = self::hydrate($event);
        }

        return $events;
    }

    /**
     * Count events
     *
     * @param array $args Filter arguments
     * @return int Count
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'    => null,
            'author_id' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['author_id']) {
            $where[] = 'author_id = %d';
            $values[] = $args['author_id'];
        }

        // Filter by wp_post_ids (for category filtering via WP posts)
        if (!empty($args['wp_post_ids']) && is_array($args['wp_post_ids'])) {
            $id_placeholders = implode(',', array_fill(0, count($args['wp_post_ids']), '%d'));
            $where[] = "wp_post_id IN ($id_placeholders)";
            $values = array_merge($values, array_map('intval', $args['wp_post_ids']));
        }

        // Filter by category_id (via sc_event_categories junction table)
        if (!empty($args['category_id'])) {
            $cat_table = $wpdb->prefix . 'sc_event_categories';
            $where[] = "id IN (SELECT event_id FROM $cat_table WHERE category_id = %d)";
            $values[] = intval($args['category_id']);
        }

        // Upcoming events only
        if (!empty($args['upcoming_only'])) {
            $where[] = 'start_date >= %s';
            $values[] = current_time('Y-m-d');
        }

        // Past events only
        if (!empty($args['past_only'])) {
            $where[] = 'end_date < %s';
            $values[] = current_time('Y-m-d');
        }

        // Search
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s OR venue_name LIKE %s OR venue_address LIKE %s OR venue_city LIKE %s OR venue_country LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_clause", $values);
        } else {
            $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create new event
     *
     * @param array $data Event data
     * @return int|false Event ID or false on failure
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        // Prepare data
        $data = self::prepare_data($data);

        // Set defaults
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        if (empty($data['author_id'])) {
            $data['author_id'] = get_current_user_id();
        }

        // Generate or ensure unique slug
        if (empty($data['slug'])) {
            $data['slug'] = self::generate_unique_slug($data['title']);
        } else {
            // Even if slug is provided, ensure it's unique for new events
            $data['slug'] = self::generate_unique_slug($data['slug']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $event_id = $wpdb->insert_id;

            // Clear cache
            wp_cache_delete('sc_events_count', 'sc_events');

            do_action('sc_event_created', $event_id, $data);

            return $event_id;
        }

        // Log database error for debugging
        if ($wpdb->last_error) {
            error_log('SC_Event::create() failed: ' . $wpdb->last_error);
            error_log('Query: ' . $wpdb->last_query);
        }

        return false;
    }

    /**
     * Update event
     *
     * @param int $id Event ID
     * @param array $data Event data
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        // Prepare data
        $data = self::prepare_data($data);
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result !== false) {
            // Clear cache
            wp_cache_delete('sc_event_' . $id, 'sc_events');

            do_action('sc_event_updated', $id, $data);

            return true;
        }

        return false;
    }

    /**
     * Delete event
     *
     * @param int $id Event ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        // Get event first for action hook
        $event = self::get($id);

        if (!$event) {
            return false;
        }

        // Delete related data first
        SC_Ticket::delete_by_event($id);
        SC_Attendee::delete_by_event($id);

        // Delete the event and check result
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        // $wpdb->delete() returns number of rows deleted or false on error
        if ($result !== false) {
            // Clear cache
            wp_cache_delete('sc_event_' . $id, 'sc_events');
            wp_cache_delete('sc_events_count', 'sc_events');

            do_action('sc_event_deleted', $id, $event);

            return true;
        }

        return false;
    }

    /**
     * Update event stats (sold tickets, revenue, etc.)
     *
     * @param int $id Event ID
     * @return bool Success
     */
    public static function update_stats($id) {
        global $wpdb;
        $table = self::get_table();
        $attendees_table = $wpdb->prefix . 'sc_attendees';

        // Get stats from attendees
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sold,
                COALESCE(SUM(amount_paid), 0) as total_revenue,
                SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as total_checked_in
            FROM $attendees_table
            WHERE event_id = %d AND payment_status = 'success' AND status = 'active'",
            $id
        ));

        if ($stats) {
            return self::update($id, array(
                'total_sold'       => (int) $stats->total_sold,
                'total_revenue'    => (float) $stats->total_revenue,
                'total_checked_in' => (int) $stats->total_checked_in,
            ));
        }

        return false;
    }

    /**
     * Get upcoming events
     *
     * @param int $limit Number of events
     * @return array
     */
    public static function get_upcoming($limit = 10) {
        return self::get_all(array(
            'status'        => 'publish',
            'upcoming_only' => true,
            'orderby'       => 'start_date',
            'order'         => 'ASC',
            'limit'         => $limit,
        ));
    }

    /**
     * Get past events
     *
     * @param int $limit Number of events
     * @return array
     */
    public static function get_past($limit = 10) {
        return self::get_all(array(
            'status'    => 'publish',
            'past_only' => true,
            'orderby'   => 'end_date',
            'order'     => 'DESC',
            'limit'     => $limit,
        ));
    }

    /**
     * Generate unique slug
     *
     * @param string $title Event title
     * @return string Unique slug
     */
    public static function generate_unique_slug($title) {
        global $wpdb;
        $table = self::get_table();

        $slug = sanitize_title($title);
        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s", $slug))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Prepare data for insert/update
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private static function prepare_data($data) {
        $prepared = array();

        // String fields
        $string_fields = array(
            'title', 'slug', 'description', 'excerpt', 'timezone',
            'venue_name', 'venue_address', 'venue_city', 'venue_country',
            'meeting_link', 'calendar_bg_color', 'calendar_text_color',
            'location_type', 'status'
        );

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // URL fields (preserve full URL with query string, don't strip)
        if (isset($data['google_maps_url'])) {
            $prepared['google_maps_url'] = esc_url_raw(trim($data['google_maps_url']));
        }

        // Text fields (allow more content)
        if (isset($data['description'])) {
            $prepared['description'] = wp_kses_post($data['description']);
        }

        // Integer fields
        $int_fields = array(
            'wp_post_id', 'featured_image', 'logo_image', 'banner_image', 'venue_image', 'schedules_file',
            'total_capacity', 'min_tickets_per_order', 'max_tickets_per_order',
            'attendance_tracking', 'all_day_event', 'author_id',
            'total_sold', 'total_checked_in',
            'enable_certificates', 'certificate_template_id', 'auto_issue_certificate',
            'certificate_require_checkin', 'certificate_require_checkout', 'certificate_require_event_ended'
        );

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                // Allow null for nullable fields like schedules_file
                if ($data[$field] === null) {
                    $prepared[$field] = null;
                } else {
                    $prepared[$field] = absint($data[$field]);
                }
            }
        }

        // Float fields
        $float_fields = array('venue_lat', 'venue_lng', 'total_revenue');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // Date fields
        $date_fields = array('start_date', 'end_date');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = date('Y-m-d', strtotime($data[$field]));
            }
        }

        // Time fields
        $time_fields = array('start_time', 'end_time');
        foreach ($time_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = date('H:i:s', strtotime($data[$field]));
            }
        }

        // Datetime fields
        if (isset($data['registration_deadline'])) {
            $prepared['registration_deadline'] = date('Y-m-d H:i:s', strtotime($data['registration_deadline']));
        }

        // JSON fields
        $json_fields = array('faq', 'schedule', 'additional_sections', 'social_links', 'extra_fields', 'organizing_company');

        foreach ($json_fields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field])) {
                    $prepared[$field] = wp_json_encode($data[$field]);
                } else {
                    $prepared[$field] = $data[$field];
                }
            }
        }

        return $prepared;
    }

    /**
     * Hydrate event object (decode JSON fields, etc.)
     *
     * @param object $event Raw event object
     * @return object Hydrated event
     */
    private static function hydrate($event) {
        // Decode JSON fields
        $json_fields = array('faq', 'schedule', 'additional_sections', 'social_links', 'extra_fields', 'organizing_company');

        foreach ($json_fields as $field) {
            if (!empty($event->$field)) {
                $decoded = json_decode($event->$field, true);
                $event->$field = is_array($decoded) ? $decoded : array();
            } else {
                $event->$field = array();
            }
        }

        // Add computed properties
        $event->is_upcoming = strtotime($event->start_date) >= strtotime(current_time('Y-m-d'));
        $event->is_past = strtotime($event->end_date) < strtotime(current_time('Y-m-d'));
        $event->is_ongoing = strtotime($event->start_date) <= strtotime(current_time('Y-m-d'))
                          && strtotime($event->end_date) >= strtotime(current_time('Y-m-d'));

        // Format dates for display
        $event->start_date_formatted = date_i18n(get_option('date_format'), strtotime($event->start_date));
        $event->end_date_formatted = date_i18n(get_option('date_format'), strtotime($event->end_date));

        if ($event->start_time) {
            $event->start_time_formatted = date_i18n(get_option('time_format'), strtotime($event->start_time));
        }

        if ($event->end_time) {
            $event->end_time_formatted = date_i18n(get_option('time_format'), strtotime($event->end_time));
        }

        // Capacity info
        $event->available_tickets = max(0, $event->total_capacity - $event->total_sold);
        $event->is_sold_out = $event->total_capacity > 0 && $event->available_tickets <= 0;

        return $event;
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get all tickets for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Ticket objects
     */
    public static function get_tickets($event_id, $args = array()) {
        return SC_Ticket::get_by_event($event_id, $args);
    }

    /**
     * Get all attendees for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Attendee objects
     */
    public static function get_attendees($event_id, $args = array()) {
        return SC_Attendee::get_by_event($event_id, $args);
    }

    /**
     * Get all speakers for this event
     *
     * @param int $event_id Event ID
     * @return array Array of SC_Speaker objects
     */
    public static function get_speakers($event_id) {
        return SC_Speaker::get_by_event($event_id);
    }

    /**
     * Get all organizers for this event
     *
     * @param int $event_id Event ID
     * @return array Array of SC_Organizer objects
     */
    public static function get_organizers($event_id) {
        return SC_Organizer::get_by_event($event_id);
    }

    /**
     * Get all sessions for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Session objects
     */
    public static function get_sessions($event_id, $args = array()) {
        return SC_Session::get_by_event($event_id, null, $args);
    }

    /**
     * Get all certificates for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Certificate objects
     */
    public static function get_certificates($event_id, $args = array()) {
        return SC_Certificate::get_by_event($event_id, $args);
    }

    /**
     * Get all checkins for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Checkin objects
     */
    public static function get_checkins($event_id, $args = array()) {
        return SC_Checkin::get_by_event($event_id, $args);
    }

    /**
     * Get all transactions for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Transaction objects
     */
    public static function get_transactions($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        return SC_Transaction::get_all($args);
    }

    /**
     * Get all coupons for this event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Coupon objects
     */
    public static function get_coupons($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        return SC_Coupon::get_all($args);
    }
}
