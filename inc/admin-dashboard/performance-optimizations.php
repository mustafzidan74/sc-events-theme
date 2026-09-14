<?php
/**
 * Performance Optimizations for Event Manager Dashboard
 *
 * Includes database index recommendations and query optimizations
 * for handling 50,000+ events and attendees
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add recommended database indexes for performance at scale
 * Run this once via WP-CLI or admin action
 *
 * Usage: wp eval "sc_add_performance_indexes();"
 * Or visit: /wp-admin/admin.php?action=sc_add_indexes (admin only)
 */
function sc_add_performance_indexes() {
    global $wpdb;

    // Only admins can run this
    if (!current_user_can('administrator')) {
        return false;
    }

    $indexes_added = [];
    $errors = [];

    // Define indexes with their safe configurations
    $indexes_config = [
        [
            'table' => $wpdb->postmeta,
            'name' => 'idx_sc_event_id',
            'columns' => 'meta_key(20), meta_value(40)',
            'description' => 'Index for sc_event_id lookups (attendees by event)'
        ],
        [
            'table' => $wpdb->posts,
            'name' => 'idx_post_author_type',
            'columns' => 'post_author, post_type(20), post_status(20)',
            'description' => 'Index for post_author (event ownership checks)'
        ],
        [
            'table' => $wpdb->postmeta,
            'name' => 'idx_sc_start_date',
            'columns' => 'meta_key(20), meta_value(20)',
            'description' => 'Index for date-based queries on events'
        ]
    ];

    foreach ($indexes_config as $index) {
        // Sanitize index name (alphanumeric and underscore only)
        $safe_index_name = preg_replace('/[^a-zA-Z0-9_]/', '', $index['name']);

        if (!sc_index_exists($index['table'], $safe_index_name)) {
            // Use esc_sql for additional safety (though we control these values)
            $safe_columns = esc_sql($index['columns']);
            $sql = "ALTER TABLE {$index['table']} ADD INDEX {$safe_index_name} ({$safe_columns})";

            $result = $wpdb->query($sql);
            if ($result !== false) {
                $indexes_added[] = $safe_index_name;
            } else {
                $errors[] = sprintf(__('Failed to add %s: %s', 'sc_events'), $safe_index_name, $wpdb->last_error);
            }
        }
    }

    return array(
        'success' => empty($errors),
        'indexes_added' => $indexes_added,
        'errors' => $errors
    );
}

/**
 * Check if an index exists on a table
 */
function sc_index_exists($table, $index_name) {
    global $wpdb;

    // Whitelist allowed tables for security
    $allowed_tables = array(
        $wpdb->posts,
        $wpdb->postmeta,
        $wpdb->users,
        $wpdb->usermeta,
        $wpdb->terms,
        $wpdb->term_taxonomy,
        $wpdb->term_relationships
    );

    if (!in_array($table, $allowed_tables, true)) {
        return false;
    }

    // Sanitize index name (alphanumeric and underscore only)
    $safe_index_name = preg_replace('/[^a-zA-Z0-9_]/', '', $index_name);

    $result = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW INDEX FROM {$table} WHERE Key_name = %s",
            $safe_index_name
        )
    );
    return !empty($result);
}

/**
 * Admin action to add indexes
 */
add_action('admin_action_sc_add_indexes', 'sc_handle_add_indexes_action');
function sc_handle_add_indexes_action() {
    if (!current_user_can('administrator')) {
        wp_die(__('Permission denied.', 'sc_events'));
    }

    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_add_indexes')) {
        wp_die(__('Security check failed.', 'sc_events'));
    }

    $result = sc_add_performance_indexes();

    $message = $result['success']
        ? sprintf(__('Successfully added %d indexes.', 'sc_events'), count($result['indexes_added']))
        : sprintf(__('Errors occurred: %s', 'sc_events'), implode(', ', $result['errors']));

    wp_redirect(admin_url('options-general.php?page=sc_events_settings&message=' . urlencode($message)));
    exit;
}

/**
 * Optimized count query helper
 * Uses SQL COUNT instead of fetching all posts
 */
function sc_count_posts_by_meta($post_type, $meta_key, $meta_value, $compare = '=') {
    global $wpdb;

    $compare_sql = $compare === 'LIKE' ? 'LIKE' : '=';
    $meta_value_sql = $compare === 'LIKE' ? '%' . $wpdb->esc_like($meta_value) . '%' : $meta_value;

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND pm.meta_key = %s
        AND pm.meta_value {$compare_sql} %s",
        $post_type,
        $meta_key,
        $meta_value_sql
    ));

    return (int) $count;
}

/**
 * Optimized count of attendees for an event
 */
function sc_count_event_attendees($event_id) {
    return sc_count_posts_by_meta('sc_attendee', 'sc_event_id', $event_id);
}

/**
 * Optimized count of events for a speaker
 */
function sc_count_speaker_events($speaker_id) {
    global $wpdb;

    // Query to find all events where this speaker is assigned
    // sc_event_speaker stores serialized array of speaker IDs
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT post_id)
        FROM {$wpdb->postmeta}
        WHERE meta_key = 'sc_event_speaker'
        AND (
            meta_value LIKE %s
            OR meta_value LIKE %s
            OR meta_value LIKE %s
        )",
        '%i:' . intval($speaker_id) . ';%',  // Serialized array with this ID
        '%s:' . strlen((string)$speaker_id) . ':"' . $speaker_id . '"%',  // Serialized string
        serialize(array(intval($speaker_id)))  // Exact match for single-item array
    ));

    return (int) $count;
}

/**
 * Optimized count of events for an organizer
 */
function sc_count_organizer_events($organizer_id) {
    global $wpdb;

    // Query to find all events where this organizer is assigned
    // sc_event_organizer stores serialized array of organizer IDs
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT post_id)
        FROM {$wpdb->postmeta}
        WHERE meta_key = 'sc_event_organizer'
        AND (
            meta_value LIKE %s
            OR meta_value LIKE %s
            OR meta_value LIKE %s
        )",
        '%i:' . intval($organizer_id) . ';%',  // Serialized array with this ID
        '%s:' . strlen((string)$organizer_id) . ':"' . $organizer_id . '"%',  // Serialized string
        serialize(array(intval($organizer_id)))  // Exact match for single-item array
    ));

    return (int) $count;
}

/**
 * Batch processing helper for large datasets
 * Processes records in chunks to avoid memory issues
 */
function sc_batch_process($callback, $args = array(), $batch_size = 100) {
    $page = 1;
    $processed = 0;

    do {
        $args['posts_per_page'] = $batch_size;
        $args['paged'] = $page;
        $args['fields'] = 'ids'; // Only get IDs for memory efficiency

        $posts = get_posts($args);

        if (empty($posts)) {
            break;
        }

        foreach ($posts as $post_id) {
            call_user_func($callback, $post_id);
            $processed++;
        }

        $page++;

        // Clear memory
        wp_cache_flush();

    } while (count($posts) === $batch_size);

    return $processed;
}

/**
 * Add object caching for frequently accessed data
 * Uses WordPress transients as a simple cache layer
 */
function sc_get_cached_count($cache_key, $callback, $expiration = 300) {
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $value = call_user_func($callback);
    set_transient($cache_key, $value, $expiration);

    return $value;
}

/**
 * Clear event-related caches when events are updated
 */
add_action('sc_event_saved', 'sc_clear_event_caches', 10, 1);
add_action('delete_post', 'sc_clear_event_caches', 10, 1);
function sc_clear_event_caches($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'sc_event') {
        return;
    }

    // Clear related caches
    delete_transient('sc_total_events_count');
    delete_transient('sc_upcoming_events_count');
    delete_transient('sc_past_events_count');
    delete_transient('sc_dashboard_home_stats'); // Clear dashboard home cache
    delete_transient('sc_events_page_stats'); // Clear events page cache
}

/**
 * Clear attendee-related caches when attendees are updated
 */
add_action('sc_attendee_saved', 'sc_clear_attendee_caches', 10, 1);
add_action('delete_post', 'sc_clear_attendee_caches_on_delete', 10, 1);
function sc_clear_attendee_caches($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'sc_attendee') {
        return;
    }

    // Clear event-specific attendee cache
    $event_id = get_post_meta($post_id, 'sc_event_id', true);
    if ($event_id) {
        delete_transient('sc_event_attendees_' . $event_id);
    }

    // Clear global attendee count
    delete_transient('sc_total_attendees_count');
    delete_transient('sc_dashboard_home_stats'); // Clear dashboard home cache
    delete_transient('sc_events_page_stats'); // Clear events page cache
}

function sc_clear_attendee_caches_on_delete($post_id) {
    $post = get_post($post_id);
    if ($post && $post->post_type === 'sc_attendee') {
        sc_clear_attendee_caches($post_id);
    }
}

/**
 * Get sold tickets count for an event - Optimized single query
 * Replaces N+1 query pattern with direct SQL for 70,000+ users scale
 *
 * @param int $event_id Event ID
 * @return int Total sold tickets
 */
function sc_get_event_sold_tickets($event_id) {
    global $wpdb;

    // Try cache first (5 minutes cache)
    $cache_key = 'sc_sold_tickets_' . intval($event_id);
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return (int) $cached;
    }

    // Single optimized query instead of N+1
    $sold_tickets = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(
            CASE
                WHEN pm_qty.meta_value > 0 THEN CAST(pm_qty.meta_value AS UNSIGNED)
                ELSE 1
            END
        ), 0) as total_sold
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id
            AND pm_event.meta_key = 'sc_event_id'
            AND pm_event.meta_value = %s
        LEFT JOIN {$wpdb->postmeta} pm_qty ON p.ID = pm_qty.post_id
            AND pm_qty.meta_key = 'ticket_qty'
        WHERE p.post_type = 'sc_attendee'
        AND p.post_status = 'publish'",
        $event_id
    ));

    $result = (int) $sold_tickets;

    // Cache for 5 minutes
    set_transient($cache_key, $result, 300);

    return $result;
}

/**
 * Clear sold tickets cache when attendee is updated
 */
add_action('sc_attendee_saved', 'sc_clear_sold_tickets_cache', 10, 1);
function sc_clear_sold_tickets_cache($post_id) {
    $event_id = get_post_meta($post_id, 'sc_event_id', true);
    if ($event_id) {
        delete_transient('sc_sold_tickets_' . intval($event_id));
    }
}

/**
 * AJAX handler to refresh all dashboard caches
 */
add_action('wp_ajax_sc_refresh_dashboard_cache', 'sc_refresh_dashboard_cache');
function sc_refresh_dashboard_cache() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
    if (!class_exists('SC_Event_Manager_Dashboard') || !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Clear all dashboard caches
    delete_transient('sc_dashboard_home_stats');
    delete_transient('sc_events_page_stats');
    delete_transient('sc_total_events_count');
    delete_transient('sc_upcoming_events_count');
    delete_transient('sc_past_events_count');
    delete_transient('sc_total_attendees_count');

    // Clear user-specific caches
    $user_id = get_current_user_id();
    delete_transient('sc_dashboard_stats_' . $user_id);

    wp_send_json_success(array(
        'message' => __('Cache refreshed successfully!', 'sc_events'),
        'cleared_at' => current_time('mysql')
    ));
}

/**
 * Prime post meta cache for multiple posts in a single query
 * This eliminates the N+1 query problem when fetching meta for lists
 *
 * @param array $post_ids Array of post IDs to prime cache for
 * @return void
 */
function sc_prime_post_meta_cache($post_ids) {
    if (empty($post_ids)) {
        return;
    }

    // WordPress will cache meta for these posts
    update_meta_cache('post', $post_ids);
}

/**
 * Get all meta for multiple attendees in optimized batches
 * Returns array keyed by attendee ID with their meta values
 *
 * @param array $attendee_ids Array of attendee post IDs
 * @param array $meta_keys Array of meta keys to retrieve
 * @return array Associative array [attendee_id => [meta_key => value]]
 */
function sc_get_batch_attendee_meta($attendee_ids, $meta_keys = array()) {
    global $wpdb;

    if (empty($attendee_ids)) {
        return array();
    }

    // Default meta keys for attendees
    if (empty($meta_keys)) {
        $meta_keys = array(
            'sc_event_id',
            'sc_unique_ticket_id',
            'sc_name',
            'sc_email',
            'sc_phone',
            'sc_ticket_type',
            'sc_ticket_slug',
            'sc_ticket_price',
            'sc_status',
            'sc_attendee_ticket_status',
            'scanner_update_time',
            'sc_coupon_code',
            'sc_payment_type',
            'sc_order_id',
            'sc_notes',
            'sc_attendee_extra_field',
            'sc_info_edit_token',
            'ticket_name',
            'ticket_slug'
        );
    }

    // Prime the cache first (WordPress handles this efficiently)
    sc_prime_post_meta_cache($attendee_ids);

    // Build result array using cached data
    $result = array();
    foreach ($attendee_ids as $attendee_id) {
        $result[$attendee_id] = array();
        foreach ($meta_keys as $key) {
            $result[$attendee_id][$key] = get_post_meta($attendee_id, $key, true);
        }
    }

    return $result;
}

/**
 * Get event titles for multiple events in a single query
 *
 * @param array $event_ids Array of event post IDs
 * @return array Associative array [event_id => title]
 */
function sc_get_batch_event_titles($event_ids) {
    global $wpdb;

    if (empty($event_ids)) {
        return array();
    }

    // Remove duplicates and filter
    $event_ids = array_unique(array_filter(array_map('intval', $event_ids)));

    if (empty($event_ids)) {
        return array();
    }

    // Build placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
            ...$event_ids
        ),
        OBJECT_K
    );

    $titles = array();
    foreach ($results as $id => $post) {
        $titles[$id] = $post->post_title;
    }

    return $titles;
}

/**
 * Optimized attendees list builder using batch queries
 * Replaces N+1 pattern with batch meta retrieval
 *
 * @param WP_Query $query The attendees query
 * @return array Array of formatted attendee data
 */
function sc_build_attendees_list_optimized($query) {
    if (!$query->have_posts()) {
        return array();
    }

    // Collect all attendee IDs first
    $attendee_ids = array();
    while ($query->have_posts()) {
        $query->the_post();
        $attendee_ids[] = get_the_ID();
    }
    $query->rewind_posts();

    // Batch fetch all meta
    $all_meta = sc_get_batch_attendee_meta($attendee_ids);

    // Collect unique event IDs and batch fetch titles
    $event_ids = array();
    foreach ($all_meta as $meta) {
        if (!empty($meta['sc_event_id'])) {
            $event_ids[] = $meta['sc_event_id'];
        }
    }
    $event_titles = sc_get_batch_event_titles($event_ids);

    // Build attendees array
    $attendees = array();
    while ($query->have_posts()) {
        $query->the_post();
        $attendee_id = get_the_ID();
        $meta = isset($all_meta[$attendee_id]) ? $all_meta[$attendee_id] : array();

        $event_id = isset($meta['sc_event_id']) ? $meta['sc_event_id'] : '';
        $event_title = isset($event_titles[$event_id]) ? $event_titles[$event_id] : '';

        $ticket_id = isset($meta['sc_unique_ticket_id']) ? $meta['sc_unique_ticket_id'] : '';
        if (empty($ticket_id)) {
            $ticket_id = '#' . strtolower(wp_generate_password(10, false));
        }

        $attendees[] = array(
            'id' => $attendee_id,
            'ticket_id' => $ticket_id,
            'event_id' => $event_id,
            'event_title' => $event_title,
            'name' => isset($meta['sc_name']) ? $meta['sc_name'] : '',
            'email' => isset($meta['sc_email']) ? $meta['sc_email'] : '',
            'phone' => isset($meta['sc_phone']) ? $meta['sc_phone'] : '',
            'ticket_type' => isset($meta['sc_ticket_type']) ? $meta['sc_ticket_type'] : '',
            'status' => !empty($meta['sc_status']) ? $meta['sc_status'] : 'success',
            'ticket_status' => !empty($meta['sc_attendee_ticket_status']) ? $meta['sc_attendee_ticket_status'] : 'unused',
            'checkin_time' => !empty($meta['scanner_update_time']) ? date('Y-m-d H:i', strtotime($meta['scanner_update_time'])) : '',
            'coupon_used' => isset($meta['sc_coupon_code']) ? $meta['sc_coupon_code'] : '',
            'payment_type' => !empty($meta['sc_payment_type']) ? $meta['sc_payment_type'] : 'free',
            'order_id' => isset($meta['sc_order_id']) ? $meta['sc_order_id'] : '',
            'created_at' => get_the_date('Y-m-d H:i')
        );
    }
    wp_reset_postdata();

    return $attendees;
}

/**
 * ============================================
 * RATE LIMITING SYSTEM
 * ============================================
 * Protects AJAX endpoints from abuse and DoS attacks
 */

/**
 * Rate limiting configuration
 * Adjust these values based on your needs
 */
function sc_get_rate_limit_config() {
    return apply_filters('sc_rate_limit_config', array(
        // General AJAX requests
        'default' => array(
            'requests' => 60,      // Max requests
            'window' => 60,        // Time window in seconds
        ),
        // More restrictive for write operations
        'write' => array(
            'requests' => 30,
            'window' => 60,
        ),
        // Very restrictive for sensitive operations
        'sensitive' => array(
            'requests' => 10,
            'window' => 60,
        ),
        // Less restrictive for read-only operations
        'read' => array(
            'requests' => 120,
            'window' => 60,
        ),
    ));
}

/**
 * Get the rate limit key for the current user/IP
 *
 * @param string $action The action being rate limited
 * @return string The cache key
 */
function sc_get_rate_limit_key($action) {
    $user_id = get_current_user_id();

    if ($user_id > 0) {
        // Use user ID for logged-in users
        return 'sc_rate_limit_' . $user_id . '_' . $action;
    }

    // Use IP for guests (with privacy consideration)
    $ip = sc_get_client_ip();
    $ip_hash = md5($ip . wp_salt('auth'));
    return 'sc_rate_limit_ip_' . $ip_hash . '_' . $action;
}

// Note: sc_get_client_ip() is now defined in security-utilities.php

/**
 * Check if the current request is rate limited
 *
 * @param string $action The action being checked
 * @param string $type The rate limit type (default, write, sensitive, read)
 * @return bool|array False if not limited, array with info if limited
 */
function sc_check_rate_limit($action, $type = 'default') {
    $config = sc_get_rate_limit_config();
    $limits = isset($config[$type]) ? $config[$type] : $config['default'];

    $key = sc_get_rate_limit_key($action);
    $data = get_transient($key);

    $current_time = time();

    if ($data === false) {
        // First request, initialize
        $data = array(
            'count' => 1,
            'window_start' => $current_time,
        );
        set_transient($key, $data, $limits['window']);
        return false; // Not limited
    }

    // Check if window has expired
    if (($current_time - $data['window_start']) >= $limits['window']) {
        // Reset window
        $data = array(
            'count' => 1,
            'window_start' => $current_time,
        );
        set_transient($key, $data, $limits['window']);
        return false; // Not limited
    }

    // Increment counter
    $data['count']++;
    set_transient($key, $data, $limits['window'] - ($current_time - $data['window_start']));

    // Check if over limit
    if ($data['count'] > $limits['requests']) {
        $retry_after = $limits['window'] - ($current_time - $data['window_start']);
        return array(
            'limited' => true,
            'requests_made' => $data['count'],
            'max_requests' => $limits['requests'],
            'retry_after' => $retry_after,
            'window' => $limits['window'],
        );
    }

    return false; // Not limited
}

/**
 * Apply rate limiting and return error if limited
 *
 * @param string $action The action being checked
 * @param string $type The rate limit type
 * @return void Dies with JSON error if rate limited
 */
function sc_enforce_rate_limit($action, $type = 'default') {
    $limited = sc_check_rate_limit($action, $type);

    if ($limited) {
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $limited['max_requests']);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . (time() + $limited['retry_after']));
        header('Retry-After: ' . $limited['retry_after']);

        wp_send_json_error(array(
            'message' => sprintf(
                __('Rate limit exceeded. Please wait %d seconds before trying again.', 'sc_events'),
                $limited['retry_after']
            ),
            'code' => 'rate_limit_exceeded',
            'retry_after' => $limited['retry_after'],
        ), 429);
    }
}

/**
 * Get remaining rate limit info for an action
 *
 * @param string $action The action to check
 * @param string $type The rate limit type
 * @return array Rate limit info
 */
function sc_get_rate_limit_info($action, $type = 'default') {
    $config = sc_get_rate_limit_config();
    $limits = isset($config[$type]) ? $config[$type] : $config['default'];

    $key = sc_get_rate_limit_key($action);
    $data = get_transient($key);

    if ($data === false) {
        return array(
            'remaining' => $limits['requests'],
            'limit' => $limits['requests'],
            'reset' => time() + $limits['window'],
        );
    }

    $remaining = max(0, $limits['requests'] - $data['count']);
    $reset = $data['window_start'] + $limits['window'];

    return array(
        'remaining' => $remaining,
        'limit' => $limits['requests'],
        'reset' => $reset,
    );
}

/**
 * Add rate limit headers to AJAX responses
 *
 * @param string $action The action
 * @param string $type The rate limit type
 */
function sc_add_rate_limit_headers($action, $type = 'default') {
    $info = sc_get_rate_limit_info($action, $type);

    header('X-RateLimit-Limit: ' . $info['limit']);
    header('X-RateLimit-Remaining: ' . $info['remaining']);
    header('X-RateLimit-Reset: ' . $info['reset']);
}

/**
 * Wrapper function for AJAX handlers with rate limiting
 *
 * Usage:
 * function my_ajax_handler() {
 *     sc_rate_limited_ajax('my_action', 'write', function() {
 *         // Your handler code here
 *     });
 * }
 *
 * @param string $action Action name for rate limiting
 * @param string $type Rate limit type
 * @param callable $callback The actual handler function
 */
function sc_rate_limited_ajax($action, $type, $callback) {
    // Check rate limit
    sc_enforce_rate_limit($action, $type);

    // Add rate limit headers
    sc_add_rate_limit_headers($action, $type);

    // Execute the callback
    call_user_func($callback);
}

/**
 * Map AJAX actions to their rate limit types
 */
function sc_get_action_rate_limit_type($action) {
    // Sensitive operations - very restrictive
    $sensitive_actions = array(
        'sc_delete_attendee',
        'sc_delete_event',
        'sc_delete_coupon',
        'sc_delete_speaker',
        'sc_bulk_delete',
    );

    // Write operations - moderately restrictive
    $write_actions = array(
        'sc_create_attendee',
        'sc_update_attendee',
        'sc_create_event',
        'sc_update_event',
        'sc_create_coupon',
        'sc_update_coupon',
        'sc_create_speaker',
        'sc_update_speaker',
        'sc_update_ticket_status',
        'sc_bulk_update',
        'sc_import_attendees',
    );

    // Read operations - less restrictive
    $read_actions = array(
        'sc_get_attendees',
        'sc_get_events',
        'sc_get_event_details',
        'sc_get_attendee_details',
        'sc_get_coupons',
        'sc_search_users',
        'sc_get_dashboard_stats',
        'sc_get_reports',
        'sc_export_attendees',
    );

    if (in_array($action, $sensitive_actions)) {
        return 'sensitive';
    }

    if (in_array($action, $write_actions)) {
        return 'write';
    }

    if (in_array($action, $read_actions)) {
        return 'read';
    }

    return 'default';
}

/**
 * Global rate limiting for all dashboard AJAX actions
 * Hook into admin-ajax.php early to check rate limits
 */
add_action('admin_init', 'sc_global_ajax_rate_limit', 1);
function sc_global_ajax_rate_limit() {
    // Only apply to AJAX requests
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        return;
    }

    // Get the action
    $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';

    // Only apply to our dashboard actions (sc_ prefix)
    if (empty($action) || strpos($action, 'sc_') !== 0) {
        return;
    }

    // Get the appropriate rate limit type
    $type = sc_get_action_rate_limit_type($action);

    // Enforce rate limit
    sc_enforce_rate_limit($action, $type);

    // Add rate limit headers for successful requests
    sc_add_rate_limit_headers($action, $type);
}

/**
 * Clear rate limit for a specific user (admin function)
 *
 * @param int $user_id User ID to clear rate limits for
 * @param string $action Specific action, or 'all' for all actions
 */
function sc_clear_user_rate_limit($user_id, $action = 'all') {
    if (!current_user_can('administrator')) {
        return false;
    }

    if ($action === 'all') {
        // Clear all known actions
        $actions = array(
            'sc_get_attendees', 'sc_create_attendee', 'sc_update_attendee', 'sc_delete_attendee',
            'sc_get_events', 'sc_create_event', 'sc_update_event', 'sc_delete_event',
            'sc_get_coupons', 'sc_create_coupon', 'sc_update_coupon', 'sc_delete_coupon',
            'sc_bulk_update', 'sc_bulk_delete', 'sc_import_attendees', 'sc_export_attendees',
        );

        foreach ($actions as $act) {
            delete_transient('sc_rate_limit_' . $user_id . '_' . $act);
        }
    } else {
        delete_transient('sc_rate_limit_' . $user_id . '_' . sanitize_key($action));
    }

    return true;
}

/**
 * ============================================
 * IMAGE OPTIMIZATION SYSTEM
 * ============================================
 * Automatically optimizes images on upload
 */

/**
 * Image optimization configuration
 */
function sc_get_image_optimization_config() {
    return apply_filters('sc_image_optimization_config', array(
        'enabled' => true,
        'max_width' => 1920,           // Max width in pixels
        'max_height' => 1920,          // Max height in pixels
        'jpeg_quality' => 82,          // JPEG quality (1-100)
        'png_compression' => 6,        // PNG compression level (0-9)
        'convert_to_webp' => false,    // Convert to WebP (requires GD with WebP support)
        'max_file_size' => 10 * 1024 * 1024, // 10MB max file size (optimizer resizes to 1920x1920 after upload)
        'optimize_thumbnails' => true, // Also optimize generated thumbnails
        'backup_original' => false,    // Keep backup of original (uses more storage)
    ));
}

/**
 * Check if image optimization is available
 *
 * @return bool|string True if available, error message if not
 */
function sc_check_image_optimization_support() {
    if (!extension_loaded('gd')) {
        return __('GD library is not installed.', 'sc_events');
    }

    $gd_info = gd_info();

    if (!isset($gd_info['JPEG Support']) || !$gd_info['JPEG Support']) {
        return __('JPEG support is not available in GD.', 'sc_events');
    }

    if (!isset($gd_info['PNG Support']) || !$gd_info['PNG Support']) {
        return __('PNG support is not available in GD.', 'sc_events');
    }

    return true;
}

/**
 * Optimize an uploaded image
 *
 * @param string $file_path Full path to the image file
 * @param string $mime_type MIME type of the image
 * @return array Result with success status and details
 */
function sc_optimize_image($file_path, $mime_type = null) {
    $config = sc_get_image_optimization_config();

    if (!$config['enabled']) {
        return array('success' => false, 'message' => 'Optimization disabled');
    }

    // Check support
    $support = sc_check_image_optimization_support();
    if ($support !== true) {
        return array('success' => false, 'message' => $support);
    }

    if (!file_exists($file_path)) {
        return array('success' => false, 'message' => 'File not found');
    }

    // Get image info
    $image_info = @getimagesize($file_path);
    if (!$image_info) {
        return array('success' => false, 'message' => 'Not a valid image');
    }

    $original_size = filesize($file_path);
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];

    // Determine MIME type
    if (!$mime_type) {
        $mime_type = $image_info['mime'];
    }

    // Only process JPEG and PNG
    if (!in_array($type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
        return array('success' => false, 'message' => 'Unsupported image type');
    }

    // Check if resizing is needed
    $needs_resize = ($width > $config['max_width'] || $height > $config['max_height']);

    // Calculate new dimensions
    $new_width = $width;
    $new_height = $height;

    if ($needs_resize) {
        $ratio = min($config['max_width'] / $width, $config['max_height'] / $height);
        $new_width = (int) round($width * $ratio);
        $new_height = (int) round($height * $ratio);
    }

    // Load image based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($file_path);
            break;
        default:
            return array('success' => false, 'message' => 'Unsupported image type');
    }

    if (!$source) {
        return array('success' => false, 'message' => 'Failed to load image');
    }

    // Create backup if configured
    if ($config['backup_original']) {
        $backup_path = $file_path . '.original';
        @copy($file_path, $backup_path);
    }

    // Create new image if resizing needed
    if ($needs_resize) {
        $destination = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
        }

        // Resample image
        imagecopyresampled(
            $destination, $source,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        );

        imagedestroy($source);
        $source = $destination;
    }

    // Save optimized image
    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($source, $file_path, $config['jpeg_quality']);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($source, $file_path, $config['png_compression']);
            break;
    }

    imagedestroy($source);

    if (!$success) {
        return array('success' => false, 'message' => 'Failed to save optimized image');
    }

    $new_size = filesize($file_path);
    $savings = $original_size - $new_size;
    $savings_percent = ($original_size > 0) ? round(($savings / $original_size) * 100, 1) : 0;

    return array(
        'success' => true,
        'original_size' => $original_size,
        'new_size' => $new_size,
        'savings' => $savings,
        'savings_percent' => $savings_percent,
        'resized' => $needs_resize,
        'dimensions' => array(
            'original' => array('width' => $width, 'height' => $height),
            'new' => array('width' => $new_width, 'height' => $new_height),
        ),
    );
}

/**
 * Hook into WordPress upload to optimize images automatically
 */
add_filter('wp_handle_upload', 'sc_handle_upload_optimization', 10, 2);
function sc_handle_upload_optimization($upload, $context) {
    // Only process images
    if (!isset($upload['type']) || strpos($upload['type'], 'image/') !== 0) {
        return $upload;
    }

    // Skip if not a supported type
    if (!in_array($upload['type'], array('image/jpeg', 'image/png', 'image/jpg'))) {
        return $upload;
    }

    $config = sc_get_image_optimization_config();
    if (!$config['enabled']) {
        return $upload;
    }

    // Optimize the uploaded image
    $result = sc_optimize_image($upload['file'], $upload['type']);

    // Log optimization results (optional)
    if ($result['success']) {
        // Store optimization stats for later analysis
        $stats = get_option('sc_image_optimization_stats', array(
            'total_optimized' => 0,
            'total_savings' => 0,
        ));

        $stats['total_optimized']++;
        $stats['total_savings'] += $result['savings'];
        $stats['last_optimization'] = current_time('mysql');

        update_option('sc_image_optimization_stats', $stats);
    }

    return $upload;
}

/**
 * Also optimize generated thumbnails
 */
add_filter('wp_generate_attachment_metadata', 'sc_optimize_thumbnails', 10, 2);
function sc_optimize_thumbnails($metadata, $attachment_id) {
    $config = sc_get_image_optimization_config();

    if (!$config['enabled'] || !$config['optimize_thumbnails']) {
        return $metadata;
    }

    if (empty($metadata['sizes'])) {
        return $metadata;
    }

    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']);

    // Get the directory of the original file
    if (!empty($metadata['file'])) {
        $file_dir = trailingslashit(dirname($metadata['file']));
    } else {
        return $metadata;
    }

    foreach ($metadata['sizes'] as $size => $size_info) {
        if (empty($size_info['file'])) {
            continue;
        }

        $file_path = $base_dir . $file_dir . $size_info['file'];

        if (file_exists($file_path)) {
            $mime_type = isset($size_info['mime-type']) ? $size_info['mime-type'] : null;
            sc_optimize_image($file_path, $mime_type);
        }
    }

    return $metadata;
}

/**
 * Get image optimization statistics
 *
 * @return array Optimization statistics
 */
function sc_get_image_optimization_stats() {
    $stats = get_option('sc_image_optimization_stats', array(
        'total_optimized' => 0,
        'total_savings' => 0,
        'last_optimization' => null,
    ));

    // Format savings for display
    $stats['total_savings_formatted'] = size_format($stats['total_savings']);

    return $stats;
}

/**
 * Reset image optimization statistics
 */
function sc_reset_image_optimization_stats() {
    delete_option('sc_image_optimization_stats');
}

/**
 * Validate image before upload
 * Checks file size and dimensions
 */
add_filter('wp_handle_upload_prefilter', 'sc_validate_image_upload');
function sc_validate_image_upload($file) {
    // Only check images
    if (strpos($file['type'], 'image/') !== 0) {
        return $file;
    }

    $config = sc_get_image_optimization_config();

    // Check file size
    if ($file['size'] > $config['max_file_size']) {
        $max_size_formatted = size_format($config['max_file_size']);
        $file['error'] = sprintf(
            __('Image file is too large. Maximum allowed size is %s.', 'sc_events'),
            $max_size_formatted
        );
        return $file;
    }

    return $file;
}

/**
 * AJAX handler to get optimization stats
 */
add_action('wp_ajax_sc_get_image_optimization_stats', 'sc_ajax_get_image_optimization_stats');
function sc_ajax_get_image_optimization_stats() {
    // Security: Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Security: Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $stats = sc_get_image_optimization_stats();
    $support = sc_check_image_optimization_support();

    wp_send_json_success(array(
        'stats' => $stats,
        'support' => $support === true ? true : false,
        'support_message' => $support === true ? '' : $support,
        'config' => sc_get_image_optimization_config(),
    ));
}

/**
 * ============================================
 * EMAIL QUEUE SYSTEM
 * ============================================
 * Background email sending to prevent page load delays
 */

/**
 * Email queue configuration
 */
function sc_get_email_queue_config() {
    return apply_filters('sc_email_queue_config', array(
        'enabled' => true,
        'batch_size' => 10,              // Emails to process per batch
        'retry_attempts' => 3,           // Max retry attempts for failed emails
        'retry_delay' => 300,            // Seconds to wait before retry (5 minutes)
        'cleanup_days' => 30,            // Days to keep sent emails in log
        'process_interval' => 60,        // Seconds between processing runs
    ));
}

/**
 * Create email queue table on theme activation
 */
function sc_create_email_queue_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sc_email_queue';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        to_email varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        message longtext NOT NULL,
        headers text,
        attachments text,
        status varchar(20) NOT NULL DEFAULT 'pending',
        attempts int(11) NOT NULL DEFAULT 0,
        last_attempt datetime DEFAULT NULL,
        error_message text,
        created_at datetime NOT NULL,
        sent_at datetime DEFAULT NULL,
        priority int(11) NOT NULL DEFAULT 10,
        meta longtext,
        PRIMARY KEY (id),
        KEY status (status),
        KEY created_at (created_at),
        KEY priority (priority)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Store table version
    update_option('sc_email_queue_table_version', '1.0');
}

// Create table on theme setup
add_action('after_switch_theme', 'sc_create_email_queue_table');

// Also check on init (for existing installations)
add_action('init', 'sc_maybe_create_email_queue_table');
function sc_maybe_create_email_queue_table() {
    $version = get_option('sc_email_queue_table_version', '0');
    if (version_compare($version, '1.0', '<')) {
        sc_create_email_queue_table();
    }
}

/**
 * Add email to queue instead of sending immediately
 *
 * @param string|array $to Email recipient(s)
 * @param string $subject Email subject
 * @param string $message Email body
 * @param string|array $headers Optional headers
 * @param string|array $attachments Optional attachments
 * @param int $priority Priority (lower = higher priority)
 * @param array $meta Additional metadata
 * @return int|false Queue ID or false on failure
 */
function sc_queue_email($to, $subject, $message, $headers = '', $attachments = array(), $priority = 10, $meta = array()) {
    global $wpdb;

    $config = sc_get_email_queue_config();

    // If queue is disabled, send immediately
    if (!$config['enabled']) {
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    $table_name = $wpdb->prefix . 'sc_email_queue';

    // Normalize to array if single email
    if (is_string($to)) {
        $to = array($to);
    }

    $inserted = 0;
    foreach ($to as $recipient) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'to_email' => sanitize_email($recipient),
                'subject' => sanitize_text_field($subject),
                'message' => $message,
                'headers' => is_array($headers) ? implode("\r\n", $headers) : $headers,
                'attachments' => maybe_serialize($attachments),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('mysql'),
                'priority' => intval($priority),
                'meta' => maybe_serialize($meta),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
        );

        if ($result) {
            $inserted++;
        }
    }

    // Schedule processing if not already scheduled
    if (!wp_next_scheduled('sc_process_email_queue')) {
        wp_schedule_single_event(time() + 30, 'sc_process_email_queue');
    }

    return $inserted > 0 ? $wpdb->insert_id : false;
}

/**
 * Process email queue
 * Called by cron or manually
 */
add_action('sc_process_email_queue', 'sc_process_email_queue');
function sc_process_email_queue() {
    global $wpdb;

    $config = sc_get_email_queue_config();
    $table_name = $wpdb->prefix . 'sc_email_queue';

    // Get pending emails
    $emails = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name}
        WHERE status = 'pending'
        AND (last_attempt IS NULL OR last_attempt < DATE_SUB(NOW(), INTERVAL %d SECOND))
        ORDER BY priority ASC, created_at ASC
        LIMIT %d",
        $config['retry_delay'],
        $config['batch_size']
    ));

    if (empty($emails)) {
        return;
    }

    $processed = 0;
    $failed = 0;

    foreach ($emails as $email) {
        // Update attempt count
        $wpdb->update(
            $table_name,
            array(
                'attempts' => $email->attempts + 1,
                'last_attempt' => current_time('mysql'),
                'status' => 'processing',
            ),
            array('id' => $email->id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        // Prepare attachments
        $attachments = maybe_unserialize($email->attachments);
        if (!is_array($attachments)) {
            $attachments = array();
        }

        // Send email
        $sent = wp_mail(
            $email->to_email,
            $email->subject,
            $email->message,
            $email->headers,
            $attachments
        );

        if ($sent) {
            // Mark as sent
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'sent',
                    'sent_at' => current_time('mysql'),
                    'error_message' => null,
                ),
                array('id' => $email->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            $processed++;
        } else {
            // Check if max attempts reached
            $new_status = ($email->attempts + 1 >= $config['retry_attempts']) ? 'failed' : 'pending';

            $wpdb->update(
                $table_name,
                array(
                    'status' => $new_status,
                    'error_message' => 'Failed to send email',
                ),
                array('id' => $email->id),
                array('%s', '%s'),
                array('%d')
            );
            $failed++;
        }
    }

    // Schedule next batch if there are more pending emails
    $pending_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'"
    );

    if ($pending_count > 0 && !wp_next_scheduled('sc_process_email_queue')) {
        wp_schedule_single_event(time() + $config['process_interval'], 'sc_process_email_queue');
    }

    // Log processing results
    if ($processed > 0 || $failed > 0) {
        error_log(sprintf(
            '[SC Email Queue] Processed: %d, Failed: %d, Pending: %d',
            $processed,
            $failed,
            $pending_count
        ));
    }

    return array(
        'processed' => $processed,
        'failed' => $failed,
        'pending' => $pending_count,
    );
}

/**
 * Get email queue statistics
 *
 * @return array Queue statistics
 */
function sc_get_email_queue_stats() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sc_email_queue';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        return array(
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'today_sent' => 0,
        );
    }

    $stats = $wpdb->get_results(
        "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status",
        OBJECT_K
    );

    $today_sent = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name}
        WHERE status = 'sent' AND DATE(sent_at) = %s",
        current_time('Y-m-d')
    ));

    return array(
        'pending' => isset($stats['pending']) ? intval($stats['pending']->count) : 0,
        'processing' => isset($stats['processing']) ? intval($stats['processing']->count) : 0,
        'sent' => isset($stats['sent']) ? intval($stats['sent']->count) : 0,
        'failed' => isset($stats['failed']) ? intval($stats['failed']->count) : 0,
        'total' => array_sum(array_column((array) $stats, 'count')),
        'today_sent' => intval($today_sent),
    );
}

/**
 * Cleanup old sent emails
 */
add_action('sc_cleanup_email_queue', 'sc_cleanup_email_queue');
function sc_cleanup_email_queue() {
    global $wpdb;

    $config = sc_get_email_queue_config();
    $table_name = $wpdb->prefix . 'sc_email_queue';

    // Delete sent emails older than cleanup_days
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name}
        WHERE status = 'sent'
        AND sent_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $config['cleanup_days']
    ));

    // Delete failed emails older than cleanup_days * 2
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name}
        WHERE status = 'failed'
        AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $config['cleanup_days'] * 2
    ));
}

// Schedule cleanup
add_action('init', 'sc_schedule_email_cleanup');
function sc_schedule_email_cleanup() {
    if (!wp_next_scheduled('sc_cleanup_email_queue')) {
        wp_schedule_event(time(), 'daily', 'sc_cleanup_email_queue');
    }
}

/**
 * Retry failed emails
 *
 * @param int $email_id Email queue ID
 * @return bool Success
 */
function sc_retry_failed_email($email_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sc_email_queue';

    return $wpdb->update(
        $table_name,
        array(
            'status' => 'pending',
            'attempts' => 0,
            'last_attempt' => null,
            'error_message' => null,
        ),
        array(
            'id' => $email_id,
            'status' => 'failed',
        ),
        array('%s', '%d', '%s', '%s'),
        array('%d', '%s')
    ) !== false;
}

/**
 * Retry all failed emails
 *
 * @return int Number of emails reset
 */
function sc_retry_all_failed_emails() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sc_email_queue';

    return $wpdb->query(
        "UPDATE {$table_name}
        SET status = 'pending', attempts = 0, last_attempt = NULL, error_message = NULL
        WHERE status = 'failed'"
    );
}

/**
 * AJAX handler to get email queue stats
 */
add_action('wp_ajax_sc_get_email_queue_stats', 'sc_ajax_get_email_queue_stats');
function sc_ajax_get_email_queue_stats() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    wp_send_json_success(array(
        'stats' => sc_get_email_queue_stats(),
        'config' => sc_get_email_queue_config(),
    ));
}

/**
 * AJAX handler to process email queue manually
 */
add_action('wp_ajax_sc_process_email_queue_manual', 'sc_ajax_process_email_queue_manual');
function sc_ajax_process_email_queue_manual() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $result = sc_process_email_queue();

    wp_send_json_success(array(
        'result' => $result,
        'stats' => sc_get_email_queue_stats(),
    ));
}

/**
 * AJAX handler to retry all failed emails
 */
add_action('wp_ajax_sc_retry_failed_emails', 'sc_ajax_retry_failed_emails');
function sc_ajax_retry_failed_emails() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $count = sc_retry_all_failed_emails();

    wp_send_json_success(array(
        'reset_count' => $count,
        'stats' => sc_get_email_queue_stats(),
    ));
}

/**
 * Helper function to queue notification emails
 * High priority for important notifications
 *
 * @param string $to Recipient email
 * @param string $subject Subject
 * @param string $message Message
 * @param string $type Notification type for tracking
 * @return int|false Queue ID or false
 */
function sc_queue_notification($to, $subject, $message, $type = 'general') {
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    );

    return sc_queue_email($to, $subject, $message, $headers, array(), 5, array(
        'type' => $type,
        'source' => 'notification',
    ));
}

/**
 * Helper function to queue bulk emails
 * Lower priority for bulk/marketing emails
 *
 * @param array $recipients Array of email addresses
 * @param string $subject Subject
 * @param string $message Message
 * @param string $type Email type for tracking
 * @return int Number of emails queued
 */
function sc_queue_bulk_emails($recipients, $subject, $message, $type = 'bulk') {
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    );

    $queued = 0;
    foreach ($recipients as $recipient) {
        $result = sc_queue_email($recipient, $subject, $message, $headers, array(), 20, array(
            'type' => $type,
            'source' => 'bulk',
        ));
        if ($result) {
            $queued++;
        }
    }

    return $queued;
}

/**
 * ============================================
 * LAZY LOADING SYSTEM
 * ============================================
 * Enhanced lazy loading for images and iframes
 */

/**
 * Lazy loading configuration
 */
function sc_get_lazy_loading_config() {
    return apply_filters('sc_lazy_loading_config', array(
        'enabled' => true,
        'images' => true,
        'iframes' => true,
        'threshold' => '200px',  // Load when within 200px of viewport
        'placeholder' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E',
        'fade_in' => true,
        'fade_duration' => 300,
    ));
}

/**
 * Add native lazy loading attributes to images
 * WordPress 5.5+ has this built-in, but we ensure it's enabled
 */
add_filter('wp_lazy_loading_enabled', '__return_true');

/**
 * Add lazy loading to content images
 */
add_filter('the_content', 'sc_add_lazy_loading_to_content', 99);
function sc_add_lazy_loading_to_content($content) {
    $config = sc_get_lazy_loading_config();

    if (!$config['enabled'] || !$config['images']) {
        return $content;
    }

    // Skip if already has loading attribute
    if (strpos($content, 'loading=') !== false) {
        return $content;
    }

    // Add loading="lazy" to images without it
    $content = preg_replace(
        '/<img(?![^>]*loading=)([^>]*)(src=["\'][^"\']+["\'])([^>]*)>/i',
        '<img$1$2$3 loading="lazy">',
        $content
    );

    return $content;
}

/**
 * Add lazy loading to avatars
 */
add_filter('get_avatar', 'sc_add_lazy_loading_to_avatar', 10, 1);
function sc_add_lazy_loading_to_avatar($avatar) {
    $config = sc_get_lazy_loading_config();

    if (!$config['enabled'] || !$config['images']) {
        return $avatar;
    }

    if (strpos($avatar, 'loading=') === false) {
        $avatar = str_replace('<img', '<img loading="lazy"', $avatar);
    }

    return $avatar;
}

/**
 * Add lazy loading to iframes
 */
add_filter('the_content', 'sc_add_lazy_loading_to_iframes', 100);
add_filter('embed_oembed_html', 'sc_add_lazy_loading_to_iframes', 10, 1);
function sc_add_lazy_loading_to_iframes($content) {
    $config = sc_get_lazy_loading_config();

    if (!$config['enabled'] || !$config['iframes']) {
        return $content;
    }

    // Add loading="lazy" to iframes without it
    $content = preg_replace(
        '/<iframe(?![^>]*loading=)([^>]*)>/i',
        '<iframe$1 loading="lazy">',
        $content
    );

    return $content;
}

/**
 * Add enhanced lazy loading script for older browsers and dynamic content
 */
add_action('wp_footer', 'sc_add_lazy_loading_script', 99);
function sc_add_lazy_loading_script() {
    $config = sc_get_lazy_loading_config();

    if (!$config['enabled']) {
        return;
    }

    ?>
    <script>
    (function() {
        // Check for native lazy loading support
        if ('loading' in HTMLImageElement.prototype) {
            return; // Browser supports native lazy loading
        }

        // Fallback for browsers without native support
        var lazyImages = document.querySelectorAll('img[loading="lazy"]');

        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                        }
                        img.removeAttribute('loading');
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '<?php echo esc_js($config['threshold']); ?>'
            });

            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        } else {
            // Fallback for older browsers - load all images
            lazyImages.forEach(function(img) {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                }
            });
        }
    })();
    </script>
    <?php

    // Add fade-in CSS if enabled
    if ($config['fade_in']) {
        ?>
        <style>
        img[loading="lazy"] {
            opacity: 0;
            transition: opacity <?php echo intval($config['fade_duration']); ?>ms ease-in;
        }
        img[loading="lazy"].loaded,
        img:not([loading="lazy"]) {
            opacity: 1;
        }
        </style>
        <script>
        (function () {
            // Images rendered later by JavaScript (dashboard lists, sliders) must fade in too;
            // the first version only looked once at DOMContentLoaded, so those stayed invisible.
            // "load" doesn't bubble, so listen in the capture phase; errors reveal the alt text.
            function reveal(e) {
                if (e.target && e.target.tagName === 'IMG') {
                    e.target.classList.add('loaded');
                }
            }
            document.addEventListener('load', reveal, true);
            document.addEventListener('error', reveal, true);
            function scan(root) {
                root.querySelectorAll('img[loading="lazy"]:not(.loaded)').forEach(function (img) {
                    if (img.complete) {
                        img.classList.add('loaded');
                    }
                });
            }
            document.addEventListener('DOMContentLoaded', function () {
                scan(document);
                new MutationObserver(function (mutations) {
                    mutations.forEach(function (m) {
                        m.addedNodes.forEach(function (node) {
                            if (node.nodeType !== 1) {
                                return;
                            }
                            if (node.tagName === 'IMG') {
                                if (node.complete) { node.classList.add('loaded'); }
                            } else {
                                scan(node);
                            }
                        });
                    });
                }).observe(document.body, { childList: true, subtree: true });
            });
        })();
        </script>
        <?php
    }
}

/**
 * Preload critical images (above the fold)
 */
add_action('wp_head', 'sc_preload_critical_images', 1);
function sc_preload_critical_images() {
    // Preload logo if exists
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        if ($logo_url) {
            echo '<link rel="preload" as="image" href="' . esc_url($logo_url) . '">' . "\n";
        }
    }

    // Preload banner image on single event pages
    if (is_singular('sc_event')) {
        $banner = get_post_meta(get_the_ID(), 'sc_event_banner', true);
        if ($banner) {
            echo '<link rel="preload" as="image" href="' . esc_url($banner) . '">' . "\n";
        }
    }
}

/**
 * Add fetchpriority to critical images
 */
add_filter('wp_get_attachment_image_attributes', 'sc_add_fetchpriority_to_images', 10, 3);
function sc_add_fetchpriority_to_images($attr, $attachment, $size) {
    // Add high priority to full-size images above the fold
    if ($size === 'full' || $size === 'large') {
        // Don't add fetchpriority if already has loading="lazy"
        if (!isset($attr['loading']) || $attr['loading'] !== 'lazy') {
            $attr['fetchpriority'] = 'high';
        }
    }

    return $attr;
}

/**
 * Disable lazy loading for specific images (e.g., logo, hero images)
 */
add_filter('wp_img_tag_add_loading_attr', 'sc_disable_lazy_loading_for_specific', 10, 3);
function sc_disable_lazy_loading_for_specific($value, $image, $context) {
    // Disable lazy loading for logo
    if (strpos($image, 'custom-logo') !== false) {
        return false;
    }

    // Disable for hero/banner images
    if (strpos($image, 'hero') !== false || strpos($image, 'banner') !== false) {
        return false;
    }

    return $value;
}
