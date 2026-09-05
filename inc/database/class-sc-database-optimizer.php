<?php
/**
 * SC Database Optimizer
 *
 * Performance optimization layer for SC Events database
 * - Additional indexes for common queries
 * - Query caching with transients
 * - Optimized query methods
 * - Database maintenance utilities
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Database_Optimizer {

    /**
     * Cache group for transients
     */
    const CACHE_GROUP = 'sc_events_cache';

    /**
     * Default cache expiration (1 hour)
     */
    const CACHE_EXPIRY = 3600;

    /**
     * Initialize optimizer
     */
    public static function init() {
        // Run optimization on theme activation or version update
        add_action('after_switch_theme', array(__CLASS__, 'run_optimizations'));
        add_action('sc_db_upgrade', array(__CLASS__, 'run_optimizations'));

        // Schedule daily maintenance
        if (!wp_next_scheduled('sc_db_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'sc_db_daily_maintenance');
        }
        add_action('sc_db_daily_maintenance', array(__CLASS__, 'daily_maintenance'));

        // Clear cache on data changes
        add_action('sc_event_saved', array(__CLASS__, 'clear_event_cache'));
        add_action('sc_attendee_saved', array(__CLASS__, 'clear_attendee_cache'));
        add_action('sc_ticket_saved', array(__CLASS__, 'clear_ticket_cache'));
    }

    /**
     * Run all optimizations
     */
    public static function run_optimizations() {
        self::add_missing_indexes();
        self::optimize_tables();
    }

    /**
     * Add missing indexes for performance
     */
    public static function add_missing_indexes() {
        global $wpdb;

        $indexes = array(
            // Events table - composite indexes for common queries
            array(
                'table' => $wpdb->prefix . 'sc_events',
                'index' => 'idx_events_status_author',
                'columns' => 'status, author_id'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_events',
                'index' => 'idx_events_date_range',
                'columns' => 'start_date, end_date, status'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_events',
                'index' => 'idx_events_search',
                'columns' => 'title(100), status'
            ),

            // Attendees table - composite indexes
            array(
                'table' => $wpdb->prefix . 'sc_attendees',
                'index' => 'idx_attendees_event_checkin',
                'columns' => 'event_id, checked_in, status'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_attendees',
                'index' => 'idx_attendees_email_event',
                'columns' => 'email, event_id'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_attendees',
                'index' => 'idx_attendees_created',
                'columns' => 'created_at'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_attendees',
                'index' => 'idx_attendees_payment_event',
                'columns' => 'event_id, payment_status, status'
            ),

            // Tickets table
            array(
                'table' => $wpdb->prefix . 'sc_tickets',
                'index' => 'idx_tickets_event_active_price',
                'columns' => 'event_id, is_active, price'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_tickets',
                'index' => 'idx_tickets_availability',
                'columns' => 'event_id, is_active, sale_start, sale_end'
            ),

            // Sessions table
            array(
                'table' => $wpdb->prefix . 'sc_sessions',
                'index' => 'idx_sessions_event_date',
                'columns' => 'event_id, session_date, is_published'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_sessions',
                'index' => 'idx_sessions_track',
                'columns' => 'event_id, track, is_published'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_sessions',
                'index' => 'idx_sessions_time',
                'columns' => 'event_id, session_date, start_time'
            ),

            // Transactions table
            array(
                'table' => $wpdb->prefix . 'sc_transactions',
                'index' => 'idx_transactions_event_status',
                'columns' => 'event_id, status'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_transactions',
                'index' => 'idx_transactions_date_status',
                'columns' => 'created_at, status'
            ),

            // Checkins table
            array(
                'table' => $wpdb->prefix . 'sc_checkins',
                'index' => 'idx_checkins_attendee',
                'columns' => 'attendee_id, checked_in_at'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_checkins',
                'index' => 'idx_checkins_event_date',
                'columns' => 'event_id, checked_in_at'
            ),

            // Session attendance
            array(
                'table' => $wpdb->prefix . 'sc_session_attendance',
                'index' => 'idx_session_att_session',
                'columns' => 'session_id, check_in_time'
            ),
            array(
                'table' => $wpdb->prefix . 'sc_session_attendance',
                'index' => 'idx_session_att_attendee',
                'columns' => 'attendee_id, session_id'
            ),

            // Coupons table
            array(
                'table' => $wpdb->prefix . 'sc_coupons',
                'index' => 'idx_coupons_valid',
                'columns' => 'is_active, start_date, expiry_date'
            ),

            // Crowd logs
            array(
                'table' => $wpdb->prefix . 'sc_crowd_logs',
                'index' => 'idx_crowd_zone_time',
                'columns' => 'zone_id, logged_at'
            ),

            // Gate logs
            array(
                'table' => $wpdb->prefix . 'sc_gate_logs',
                'index' => 'idx_gate_logs_time',
                'columns' => 'gate_id, scanned_at'
            ),
        );

        foreach ($indexes as $index) {
            self::add_index_if_not_exists(
                $index['table'],
                $index['index'],
                $index['columns']
            );
        }

        // Log optimization
        error_log('[SC Events] Database indexes optimized');
    }

    /**
     * Add index if it doesn't exist
     */
    private static function add_index_if_not_exists($table, $index_name, $columns) {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            return false;
        }

        // Check if index exists
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE table_schema = DATABASE()
             AND table_name = %s
             AND index_name = %s",
            $table,
            $index_name
        ));

        if (!$index_exists) {
            $wpdb->query("ALTER TABLE `$table` ADD INDEX `$index_name` ($columns)");
            return true;
        }

        return false;
    }

    /**
     * Optimize all SC tables
     */
    public static function optimize_tables() {
        global $wpdb;

        $tables = sc_get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE `$table`");
        }
    }

    /**
     * Daily maintenance tasks
     */
    public static function daily_maintenance() {
        // Clear expired transients
        self::clear_expired_cache();

        // Update table statistics
        self::analyze_tables();

        // Log maintenance
        error_log('[SC Events] Daily maintenance completed at ' . current_time('mysql'));
    }

    /**
     * Analyze tables for query optimizer
     */
    public static function analyze_tables() {
        global $wpdb;

        $tables = sc_get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("ANALYZE TABLE `$table`");
        }
    }

    /**
     * Clear expired cache entries
     */
    public static function clear_expired_cache() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_sc_cache_%'
             AND option_value < " . time()
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_sc_cache_%'
             AND option_value < " . time()
        );
    }

    // ========================================
    // CACHING METHODS
    // ========================================

    /**
     * Get cached value or execute callback
     */
    public static function remember($key, $callback, $expiry = null) {
        $cache_key = 'sc_cache_' . md5($key);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $value = call_user_func($callback);

        set_transient(
            $cache_key,
            $value,
            $expiry ?: self::CACHE_EXPIRY
        );

        return $value;
    }

    /**
     * Invalidate cache by key pattern
     */
    public static function forget($pattern) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            '%' . $wpdb->esc_like('_transient_sc_cache_' . $pattern) . '%'
        ));
    }

    /**
     * Clear all SC Events cache
     */
    public static function flush() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '%_transient_sc_cache_%'"
        );
    }

    /**
     * Clear event-related cache
     */
    public static function clear_event_cache($event_id = null) {
        if ($event_id) {
            self::forget('event_' . $event_id);
            self::forget('events_');
        } else {
            self::forget('event');
        }
    }

    /**
     * Clear attendee-related cache
     */
    public static function clear_attendee_cache($attendee_id = null) {
        if ($attendee_id) {
            self::forget('attendee_' . $attendee_id);
        }
        self::forget('attendees_');
        self::forget('stats_');
    }

    /**
     * Clear ticket-related cache
     */
    public static function clear_ticket_cache($ticket_id = null) {
        self::forget('tickets_');
        self::forget('event_');
    }

    // ========================================
    // OPTIMIZED QUERY METHODS
    // ========================================

    /**
     * Get event stats (cached)
     */
    public static function get_event_stats($event_id) {
        return self::remember("stats_event_{$event_id}", function() use ($event_id) {
            global $wpdb;
            $attendees_table = $wpdb->prefix . 'sc_attendees';

            return $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as total_registrations,
                    SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as paid_registrations,
                    SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as checked_in,
                    SUM(CASE WHEN status = 'active' AND payment_status = 'success' THEN amount_paid ELSE 0 END) as total_revenue
                 FROM $attendees_table
                 WHERE event_id = %d AND status = 'active'",
                $event_id
            ));
        }, 300); // Cache for 5 minutes
    }

    /**
     * Get dashboard summary stats (cached)
     */
    public static function get_dashboard_stats($author_id = null) {
        $cache_key = "stats_dashboard" . ($author_id ? "_$author_id" : "");

        return self::remember($cache_key, function() use ($author_id) {
            global $wpdb;
            $events_table = $wpdb->prefix . 'sc_events';
            $attendees_table = $wpdb->prefix . 'sc_attendees';

            $where = $author_id ? $wpdb->prepare("WHERE e.author_id = %d", $author_id) : "";

            return $wpdb->get_row(
                "SELECT
                    (SELECT COUNT(*) FROM $events_table e $where) as total_events,
                    (SELECT COUNT(*) FROM $events_table e $where AND e.status = 'publish' AND e.start_date >= CURDATE()) as upcoming_events,
                    (SELECT COUNT(*) FROM $attendees_table a
                        INNER JOIN $events_table e ON a.event_id = e.id
                        " . ($author_id ? "WHERE e.author_id = $author_id" : "") . "
                    ) as total_attendees,
                    (SELECT COALESCE(SUM(a.amount_paid), 0) FROM $attendees_table a
                        INNER JOIN $events_table e ON a.event_id = e.id
                        WHERE a.payment_status = 'success' AND a.status = 'active'
                        " . ($author_id ? "AND e.author_id = $author_id" : "") . "
                    ) as total_revenue"
            );
        }, 600); // Cache for 10 minutes
    }

    /**
     * Get upcoming events (cached)
     */
    public static function get_upcoming_events($limit = 5, $author_id = null) {
        $cache_key = "events_upcoming_{$limit}" . ($author_id ? "_$author_id" : "");

        return self::remember($cache_key, function() use ($limit, $author_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'sc_events';

            $where = "WHERE status = 'publish' AND start_date >= CURDATE()";
            if ($author_id) {
                $where .= $wpdb->prepare(" AND author_id = %d", $author_id);
            }

            return $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, slug, start_date, end_date, venue_name, total_sold, total_capacity
                 FROM $table
                 $where
                 ORDER BY start_date ASC
                 LIMIT %d",
                $limit
            ));
        }, 300);
    }

    /**
     * Count attendees by status (optimized)
     */
    public static function count_attendees_by_status($event_id) {
        return self::remember("attendees_count_{$event_id}", function() use ($event_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'sc_attendees';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT
                    status,
                    payment_status,
                    COUNT(*) as count
                 FROM $table
                 WHERE event_id = %d
                 GROUP BY status, payment_status",
                $event_id
            ), OBJECT_K);
        }, 180);
    }

    /**
     * Get revenue by date range (optimized)
     */
    public static function get_revenue_by_date($start_date, $end_date, $author_id = null) {
        global $wpdb;
        $attendees_table = $wpdb->prefix . 'sc_attendees';
        $events_table = $wpdb->prefix . 'sc_events';

        $where = "WHERE a.payment_status = 'success'
                  AND a.status = 'active'
                  AND a.created_at BETWEEN %s AND %s";
        $params = array($start_date, $end_date);

        if ($author_id) {
            $where .= " AND e.author_id = %d";
            $params[] = $author_id;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(a.created_at) as date,
                COUNT(*) as orders,
                SUM(a.amount_paid) as revenue
             FROM $attendees_table a
             INNER JOIN $events_table e ON a.event_id = e.id
             $where
             GROUP BY DATE(a.created_at)
             ORDER BY date ASC",
            $params
        ));
    }

    /**
     * Search events (optimized with FULLTEXT-like behavior)
     */
    public static function search_events($query, $args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_events';

        $defaults = array(
            'status' => 'publish',
            'limit' => 10,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $search = '%' . $wpdb->esc_like($query) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, slug, start_date, venue_name, status,
                    CASE
                        WHEN title LIKE %s THEN 3
                        WHEN venue_name LIKE %s THEN 2
                        ELSE 1
                    END as relevance
             FROM $table
             WHERE status = %s
               AND (title LIKE %s OR venue_name LIKE %s OR description LIKE %s)
             ORDER BY relevance DESC, start_date ASC
             LIMIT %d OFFSET %d",
            $query . '%',
            $query . '%',
            $args['status'],
            $search,
            $search,
            $search,
            $args['limit'],
            $args['offset']
        ));
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    /**
     * Get slow query log (for debugging)
     */
    public static function get_slow_queries() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return array('message' => 'SAVEQUERIES is not enabled');
        }

        $slow_queries = array();
        foreach ($wpdb->queries as $query) {
            if ($query[1] > 0.05) { // Queries taking more than 50ms
                $slow_queries[] = array(
                    'query' => $query[0],
                    'time' => round($query[1] * 1000, 2) . 'ms',
                    'caller' => $query[2]
                );
            }
        }

        return $slow_queries;
    }

    /**
     * Get table sizes
     */
    public static function get_table_sizes() {
        global $wpdb;

        $tables = sc_get_table_names();
        $sizes = array();

        foreach ($tables as $name => $table) {
            $size = $wpdb->get_row(
                "SELECT
                    table_rows as rows,
                    ROUND(data_length / 1024 / 1024, 2) as data_mb,
                    ROUND(index_length / 1024 / 1024, 2) as index_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = DATABASE()
                   AND table_name = '$table'"
            );

            if ($size) {
                $sizes[$name] = $size;
            }
        }

        return $sizes;
    }

    /**
     * Check database health
     */
    public static function health_check() {
        global $wpdb;

        $issues = array();
        $tables = sc_get_table_names();

        // Check if all tables exist
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$exists) {
                $issues[] = "Missing table: $name ($table)";
            }
        }

        // Check for orphaned records
        $orphaned_attendees = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees a
             LEFT JOIN {$wpdb->prefix}sc_events e ON a.event_id = e.id
             WHERE e.id IS NULL"
        );

        if ($orphaned_attendees > 0) {
            $issues[] = "Found $orphaned_attendees orphaned attendee records";
        }

        return array(
            'status' => empty($issues) ? 'healthy' : 'issues_found',
            'issues' => $issues,
            'checked_at' => current_time('mysql')
        );
    }
}

// Initialize the optimizer
SC_Database_Optimizer::init();
