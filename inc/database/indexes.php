<?php
/**
 * Database Indexes Optimization
 *
 * Adds additional indexes for better query performance on Custom Tables
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add performance indexes to custom SC tables
 * Run once on theme activation or via admin
 */
function sc_add_custom_table_indexes() {
    global $wpdb;

    $indexes_added = get_option('sc_performance_indexes_added', '0');
    if ($indexes_added === '1.1') {
        return; // Already added
    }

    $tables = sc_get_table_names();

    // Suppress errors for duplicate index attempts
    $wpdb->suppress_errors(true);

    // Events table - additional indexes
    $wpdb->query("ALTER TABLE {$tables['events']} ADD INDEX idx_events_status_start (status, start_date, end_date)");
    $wpdb->query("ALTER TABLE {$tables['events']} ADD INDEX idx_events_author_status (author_id, status)");
    $wpdb->query("ALTER TABLE {$tables['events']} ADD INDEX idx_events_created (created_at)");
    $wpdb->query("ALTER TABLE {$tables['events']} ADD FULLTEXT INDEX ft_events_search (title, description)");

    // Attendees table - additional indexes
    $wpdb->query("ALTER TABLE {$tables['attendees']} ADD INDEX idx_attendees_event_payment (event_id, payment_status, status)");
    $wpdb->query("ALTER TABLE {$tables['attendees']} ADD INDEX idx_attendees_created (created_at)");
    $wpdb->query("ALTER TABLE {$tables['attendees']} ADD INDEX idx_attendees_email_event (email, event_id)");

    // Tickets table - additional indexes
    $wpdb->query("ALTER TABLE {$tables['tickets']} ADD INDEX idx_tickets_event_price (event_id, price)");

    // Transactions table - additional indexes
    $wpdb->query("ALTER TABLE {$tables['transactions']} ADD INDEX idx_trans_event_status (event_id, status)");
    $wpdb->query("ALTER TABLE {$tables['transactions']} ADD INDEX idx_trans_user_status (user_id, status)");

    // Check-ins table - additional indexes
    $wpdb->query("ALTER TABLE {$tables['checkins']} ADD INDEX idx_checkins_attendee_action (attendee_id, action)");

    $wpdb->suppress_errors(false);

    update_option('sc_performance_indexes_added', '1.1');
}

/**
 * Run indexes on admin init (once)
 */
add_action('admin_init', 'sc_add_custom_table_indexes');

/**
 * Analyze tables for query optimization
 */
function sc_analyze_tables() {
    global $wpdb;

    $tables = sc_get_table_names();

    foreach ($tables as $name => $table) {
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("ANALYZE TABLE $table");
        }
    }
}

/**
 * Optimize tables (defragment)
 * Should be run periodically via cron
 */
function sc_optimize_tables() {
    global $wpdb;

    $tables = sc_get_table_names();

    foreach ($tables as $name => $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
}
