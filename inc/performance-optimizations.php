<?php
/**
 * Performance & Security Optimizations
 * Optimized for 2000+ concurrent users
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =====================================================
 * 1. RATE LIMITING - Prevent AJAX Abuse
 * =====================================================
 */
class SC_Rate_Limiter {
    private static $limits = array(
        'sc_scan_and_checkin' => array('requests' => 30, 'window' => 60),  // 30 scans per minute
        'sc_get_attendees_paginated' => array('requests' => 20, 'window' => 60),
        'sc_send_attendee_email' => array('requests' => 10, 'window' => 60),
        'default' => array('requests' => 60, 'window' => 60)  // 60 requests per minute default
    );

    public static function check($action) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $key = 'sc_rate_' . $action . '_' . $user_id;
        $limit = isset(self::$limits[$action]) ? self::$limits[$action] : self::$limits['default'];

        $data = get_transient($key);
        if ($data === false) {
            $data = array('count' => 1, 'start' => time());
            set_transient($key, $data, $limit['window']);
            return true;
        }

        if ($data['count'] >= $limit['requests']) {
            return false; // Rate limit exceeded
        }

        $data['count']++;
        set_transient($key, $data, $limit['window'] - (time() - $data['start']));
        return true;
    }

    public static function apply($action) {
        if (!self::check($action)) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment.', 'sc_events'),
                'code' => 'rate_limited'
            ), 429);
        }
    }
}

/**
 * =====================================================
 * 2. QUERY CACHE - Cache Expensive Queries
 * =====================================================
 */
class SC_Query_Cache {
    private static $cache_group = 'sc_events';
    private static $cache_times = array(
        'events_list' => 300,      // 5 minutes
        'event_stats' => 120,      // 2 minutes
        'attendee_count' => 60,    // 1 minute
        'tracking_status' => 600,  // 10 minutes
    );

    public static function get($key, $group = 'default') {
        $cache_key = self::$cache_group . '_' . $group . '_' . $key;

        // Try object cache first (Redis/Memcached if available)
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($cache_key, self::$cache_group);
        }

        // Fall back to transients
        return get_transient($cache_key);
    }

    public static function set($key, $value, $group = 'default') {
        $cache_key = self::$cache_group . '_' . $group . '_' . $key;
        $ttl = isset(self::$cache_times[$group]) ? self::$cache_times[$group] : 300;

        if (wp_using_ext_object_cache()) {
            wp_cache_set($cache_key, $value, self::$cache_group, $ttl);
        } else {
            set_transient($cache_key, $value, $ttl);
        }
    }

    public static function delete($key, $group = 'default') {
        $cache_key = self::$cache_group . '_' . $group . '_' . $key;

        if (wp_using_ext_object_cache()) {
            wp_cache_delete($cache_key, self::$cache_group);
        } else {
            delete_transient($cache_key);
        }
    }

    public static function flush_group($group) {
        // For transients, we need to flush individually
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::$cache_group . '_' . $group . '_%'
        ));
    }
}

/**
 * =====================================================
 * 3. OPTIMIZED ATTENDEES QUERY
 * =====================================================
 */
function sc_get_attendees_optimized($args = array()) {
    global $wpdb;

    $defaults = array(
        'event_id' => 0,
        'status' => '',
        'ticket_status' => '',
        'payment_type' => '',
        'search' => '',
        'page' => 1,
        'per_page' => 50,
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $args = wp_parse_args($args, $defaults);
    $offset = ($args['page'] - 1) * $args['per_page'];

    // Build optimized query with JOINs instead of multiple get_post_meta calls
    $select = "SELECT DISTINCT p.ID as attendee_id, p.post_date";
    $from = " FROM {$wpdb->posts} p";
    $joins = "";
    $where = " WHERE p.post_type = 'sc_attendee' AND p.post_status = 'publish'";
    $params = array();

    // Join for basic meta fields (always needed)
    $meta_fields = array(
        'sc_event_id', 'sc_name', 'sc_email', 'sc_phone',
        'sc_unique_ticket_id', 'sc_ticket_type', 'sc_status',
        'sc_attendee_ticket_status', 'sc_payment_type', 'sc_coupon_code',
        'scanner_update_time', 'sc_attendance_log'
    );

    foreach ($meta_fields as $index => $meta_key) {
        $alias = "m{$index}";
        $joins .= " LEFT JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = '{$meta_key}'";
        $select .= ", {$alias}.meta_value as " . str_replace(array('sc_', 'sc_'), '', $meta_key);
    }

    // Event filter
    if (!empty($args['event_id'])) {
        $where .= " AND m0.meta_value = %d";
        $params[] = $args['event_id'];
    }

    // Status filter
    if (!empty($args['status'])) {
        $where .= " AND m6.meta_value = %s";
        $params[] = $args['status'];
    }

    // Ticket status filter
    if (!empty($args['ticket_status'])) {
        $where .= " AND m7.meta_value = %s";
        $params[] = $args['ticket_status'];
    }

    // Payment type filter
    if (!empty($args['payment_type'])) {
        $where .= " AND m8.meta_value = %s";
        $params[] = $args['payment_type'];
    }

    // Search filter
    if (!empty($args['search'])) {
        $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where .= " AND (m1.meta_value LIKE %s OR m2.meta_value LIKE %s OR m3.meta_value LIKE %s OR m4.meta_value LIKE %s)";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }

    // Count query
    $count_sql = "SELECT COUNT(DISTINCT p.ID)" . $from . $joins . $where;
    if (!empty($params)) {
        $count_sql = $wpdb->prepare($count_sql, $params);
    }
    $total = (int) $wpdb->get_var($count_sql);

    // Main query with pagination
    $order_col = $args['orderby'] === 'name' ? 'm1.meta_value' : 'p.post_date';
    $sql = $select . $from . $joins . $where . " ORDER BY {$order_col} {$args['order']} LIMIT %d OFFSET %d";
    $params[] = $args['per_page'];
    $params[] = $offset;

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $results = $wpdb->get_results($sql, ARRAY_A);

    // Cache event tracking status to avoid repeated queries
    $event_tracking_cache = array();

    // Process results
    $attendees = array();
    foreach ($results as $row) {
        $event_id = (int) $row['event_id'];

        // Check tracking status (cached)
        if (!isset($event_tracking_cache[$event_id])) {
            $event_tracking_cache[$event_id] = get_post_meta($event_id, 'sc_attendance_tracking', true) === 'yes';
        }
        $tracking_enabled = $event_tracking_cache[$event_id];

        // Parse attendance log
        $scan_count = 0;
        $last_checkin = '';
        $last_checkout = '';

        if ($tracking_enabled && !empty($row['attendance_log'])) {
            $log = maybe_unserialize($row['attendance_log']);
            if (is_array($log)) {
                $scan_count = count($log);
                foreach (array_reverse($log) as $entry) {
                    if (empty($last_checkin) && isset($entry['type']) && $entry['type'] === 'check_in') {
                        $last_checkin = isset($entry['timestamp']) ? date('Y-m-d H:i', $entry['timestamp']) : '';
                    }
                    if (empty($last_checkout) && isset($entry['type']) && $entry['type'] === 'check_out') {
                        $last_checkout = isset($entry['timestamp']) ? date('Y-m-d H:i', $entry['timestamp']) : '';
                    }
                    if (!empty($last_checkin) && !empty($last_checkout)) break;
                }
            }
        }

        $attendees[] = array(
            'id' => (int) $row['attendee_id'],
            'ticket_id' => $row['unique_ticket_id'] ?: '#' . strtolower(wp_generate_password(10, false)),
            'event_id' => $event_id,
            'event_title' => get_the_title($event_id),
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'ticket_type' => $row['ticket_type'],
            'status' => $row['status'] ?: 'success',
            'ticket_status' => $row['attendeee_ticket_status'] ?: 'unused',
            'checkin_time' => $row['scanner_update_time'] ? date('Y-m-d H:i', strtotime($row['scanner_update_time'])) : '',
            'coupon_used' => $row['coupon_code'],
            'payment_type' => $row['payment_type'] ?: 'free',
            'created_at' => date('Y-m-d H:i', strtotime($row['post_date'])),
            'tracking_enabled' => $tracking_enabled,
            'scan_count' => $scan_count,
            'last_checkin' => $last_checkin,
            'last_checkout' => $last_checkout
        );
    }

    return array(
        'attendees' => $attendees,
        'total' => $total,
        'pages' => ceil($total / $args['per_page']),
        'current_page' => $args['page']
    );
}

/**
 * =====================================================
 * 4. DATABASE INDEXES - Add on theme activation
 * =====================================================
 */
function sc_add_database_indexes() {
    global $wpdb;

    // Check if indexes already exist
    $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}", ARRAY_A);
    $index_names = array_column($existing_indexes, 'Key_name');

    // Add index for meta_key + meta_value lookups (most common query pattern)
    if (!in_array('sc_meta_key_value', $index_names)) {
        $wpdb->query("CREATE INDEX sc_meta_key_value ON {$wpdb->postmeta} (meta_key(50), meta_value(50))");
    }

    // Add index for post_id + meta_key (for get_post_meta)
    if (!in_array('sc_post_meta_key', $index_names)) {
        $wpdb->query("CREATE INDEX sc_post_meta_key ON {$wpdb->postmeta} (post_id, meta_key(50))");
    }

    update_option('sc_indexes_added', true);
}

// Run on theme setup if not done
add_action('after_setup_theme', function() {
    if (!get_option('sc_indexes_added')) {
        sc_add_database_indexes();
    }
});

/**
 * =====================================================
 * 5. OPTIMIZED SCANNER CHECK-IN
 * =====================================================
 */
function sc_optimized_scan_checkin($ticket_id, $event_id = 0) {
    global $wpdb;

    // Apply rate limiting
    SC_Rate_Limiter::apply('sc_scan_and_checkin');

    // Single optimized query to find attendee
    $sql = $wpdb->prepare("
        SELECT p.ID, pm_event.meta_value as event_id
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_ticket ON p.ID = pm_ticket.post_id
        LEFT JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'sc_event_id'
        WHERE p.post_type = 'sc_attendee'
        AND p.post_status = 'publish'
        AND pm_ticket.meta_key = 'sc_unique_ticket_id'
        AND pm_ticket.meta_value = %s
        LIMIT 1
    ", $ticket_id);

    $attendee = $wpdb->get_row($sql);

    if (!$attendee) {
        return new WP_Error('not_found', __('Ticket not found.', 'sc_events'));
    }

    // Check event match if filtering
    if ($event_id && (int) $attendee->event_id !== (int) $event_id) {
        return new WP_Error('wrong_event', __('Ticket belongs to a different event.', 'sc_events'));
    }

    return $attendee;
}

/**
 * =====================================================
 * 6. SECURITY HEADERS
 * =====================================================
 */
add_action('send_headers', function() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
});

/**
 * =====================================================
 * 7. INPUT SANITIZATION HELPER
 * =====================================================
 */
function sc_sanitize_input($input, $type = 'text') {
    switch ($type) {
        case 'int':
            return absint($input);
        case 'email':
            return sanitize_email($input);
        case 'html':
            return wp_kses_post($input);
        case 'textarea':
            return sanitize_textarea_field($input);
        case 'array':
            return is_array($input) ? array_map('sanitize_text_field', $input) : array();
        case 'text':
        default:
            return sanitize_text_field($input);
    }
}

/**
 * =====================================================
 * 8. CLEANUP OLD TRANSIENTS
 * =====================================================
 */
add_action('sc_daily_cleanup', 'sc_cleanup_old_data');
function sc_cleanup_old_data() {
    global $wpdb;

    // Delete expired transients
    $wpdb->query("
        DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
        WHERE a.option_name LIKE '_transient_%'
        AND a.option_name NOT LIKE '_transient_timeout_%'
        AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
        AND b.option_value < UNIX_TIMESTAMP()
    ");

    // Clear old rate limit transients
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_sc_rate_%'
        OR option_name LIKE '_transient_timeout_sc_rate_%'
    ");
}

// Schedule daily cleanup
if (!wp_next_scheduled('sc_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'sc_daily_cleanup');
}

/**
 * =====================================================
 * 9. AJAX REQUEST VALIDATOR
 * =====================================================
 */
function sc_validate_ajax_request($action = '') {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }

    // Check user permission
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }

    // Apply rate limiting if action specified
    if ($action) {
        SC_Rate_Limiter::apply($action);
    }

    return true;
}

/**
 * =====================================================
 * 10. PRELOAD CRITICAL DATA
 * =====================================================
 */
function sc_preload_dashboard_data() {
    if (!is_page() || strpos($_SERVER['REQUEST_URI'], 'event-manager-dashboard') === false) {
        return;
    }

    // Preload events list
    $cache_key = 'all_events_list';
    if (SC_Query_Cache::get($cache_key, 'events_list') === false) {
        $events = get_posts(array(
            'post_type' => 'sc_event',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids'
        ));
        SC_Query_Cache::set($cache_key, $events, 'events_list');
    }
}
add_action('template_redirect', 'sc_preload_dashboard_data');

/**
 * =====================================================
 * 11. DISABLE UNNECESSARY FEATURES FOR PERFORMANCE
 * =====================================================
 */
add_action('init', function() {
    // Disable emojis on dashboard
    if (strpos($_SERVER['REQUEST_URI'], 'event-manager-dashboard') !== false) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }
});

/**
 * =====================================================
 * 12. GZIP COMPRESSION CHECK
 * =====================================================
 */
function sc_check_gzip_compression() {
    if (!extension_loaded('zlib')) {
        return false;
    }

    if (ini_get('zlib.output_compression')) {
        return true;
    }

    return false;
}

// Add compression hint in admin
add_action('admin_notices', function() {
    if (!current_user_can('administrator')) return;

    if (!sc_check_gzip_compression() && isset($_GET['page']) && strpos($_GET['page'], 'sc_') === 0) {
        echo '<div class="notice notice-warning"><p>';
        echo __('<strong>Performance Tip:</strong> Enable GZIP compression in your server configuration for better performance.', 'sc_events');
        echo '</p></div>';
    }
});
