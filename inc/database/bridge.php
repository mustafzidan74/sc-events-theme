<?php
/**
 * SC Events Database Bridge
 *
 * Provides helper functions for custom database tables.
 * This is the primary data access layer - no WordPress post dependencies.
 * 100% reliance on custom tables (sc_events, sc_attendees, sc_tickets, etc.)
 *
 * All function names prefixed with scdb_ to avoid conflicts
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================
 * EVENT FUNCTIONS
 * =========================================
 */

/**
 * Get event by ID
 *
 * @param int $id Event ID from sc_events table
 * @return object|null Event object
 */
function scdb_get_event($id) {
    if (!class_exists('SC_Event')) {
        return null;
    }
    return SC_Event::get($id);
}

/**
 * Get all events
 *
 * @param array $args Query arguments
 * @return array
 */
function scdb_get_events($args = array()) {
    if (!class_exists('SC_Event')) {
        return array();
    }
    return SC_Event::get_all($args);
}

/**
 * =========================================
 * TICKET FUNCTIONS
 * =========================================
 */

/**
 * Get tickets for an event
 *
 * @param int $event_id Event ID from sc_events table
 * @return array
 */
function scdb_get_event_tickets($event_id) {
    if (!class_exists('SC_Ticket')) {
        return array();
    }
    return SC_Ticket::get_by_event($event_id);
}

/**
 * Get ticket by ID
 *
 * @param int $ticket_id Ticket ID
 * @return object|null
 */
function scdb_get_ticket($ticket_id) {
    if (!class_exists('SC_Ticket')) {
        return null;
    }
    return SC_Ticket::get($ticket_id);
}

/**
 * =========================================
 * ATTENDEE FUNCTIONS
 * =========================================
 */

/**
 * Get attendee by ID
 *
 * @param int $id Attendee ID from sc_attendees table
 * @return object|null
 */
function scdb_get_attendee($id) {
    if (!class_exists('SC_Attendee')) {
        return null;
    }
    return SC_Attendee::get($id);
}

/**
 * Get attendee by ticket code
 *
 * @param string $ticket_code Unique ticket code
 * @return object|null
 */
function scdb_get_attendee_by_ticket_code($ticket_code) {
    if (!class_exists('SC_Attendee')) {
        return null;
    }
    return SC_Attendee::get_by_ticket_code($ticket_code);
}

/**
 * Get attendees by event
 *
 * @param int $event_id Event ID
 * @param array $args Filter arguments
 * @return array
 */
function scdb_get_attendees_by_event($event_id, $args = array()) {
    if (!class_exists('SC_Attendee')) {
        return array();
    }
    return SC_Attendee::get_by_event($event_id, $args);
}

/**
 * Count attendees for an event
 *
 * @param int $event_id Event ID
 * @param array $args Filter arguments
 * @return int
 */
function scdb_count_attendees($event_id, $args = array()) {
    if (!class_exists('SC_Attendee')) {
        return 0;
    }
    $args['event_id'] = $event_id;
    return SC_Attendee::count($args);
}

/**
 * Create a new attendee
 *
 * @param array $data Attendee data
 * @return int|false Attendee ID or false on failure
 */
function scdb_create_attendee($data) {
    if (!class_exists('SC_Attendee')) {
        return false;
    }
    return SC_Attendee::create($data);
}

/**
 * Update an attendee
 *
 * @param int $id Attendee ID
 * @param array $data Attendee data
 * @return bool
 */
function scdb_update_attendee($id, $data) {
    if (!class_exists('SC_Attendee')) {
        return false;
    }
    return SC_Attendee::update($id, $data);
}

/**
 * Delete an attendee
 *
 * @param int $id Attendee ID
 * @return bool
 */
function scdb_delete_attendee($id) {
    if (!class_exists('SC_Attendee')) {
        return false;
    }
    return SC_Attendee::delete($id);
}

/**
 * =========================================
 * STATISTICS FUNCTIONS
 * =========================================
 */

/**
 * Get event statistics
 *
 * @param int $event_id Event ID
 * @return array
 */
function scdb_get_event_stats($event_id) {
    if (!class_exists('SC_Attendee')) {
        return array(
            'total_attendees' => 0,
            'paid_attendees'  => 0,
            'checked_in'      => 0,
            'total_revenue'   => 0.0,
        );
    }

    global $wpdb;
    $attendees_table = SC_Attendee::get_table();

    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) as total_attendees,
            SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as paid_attendees,
            SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as checked_in,
            COALESCE(SUM(amount_paid), 0) as total_revenue
        FROM $attendees_table
        WHERE event_id = %d AND status = 'active'",
        $event_id
    ));

    return array(
        'total_attendees' => (int) ($stats->total_attendees ?? 0),
        'paid_attendees'  => (int) ($stats->paid_attendees ?? 0),
        'checked_in'      => (int) ($stats->checked_in ?? 0),
        'total_revenue'   => (float) ($stats->total_revenue ?? 0),
    );
}

/**
 * Get global dashboard statistics
 *
 * @param int|null $author_id Optional author ID to filter by
 * @return array
 */
function scdb_get_dashboard_stats($author_id = null) {
    global $wpdb;

    $stats = array(
        'total_events'     => 0,
        'upcoming_events'  => 0,
        'total_attendees'  => 0,
        'checked_in'       => 0,
        'total_revenue'    => 0.0,
    );

    if (!class_exists('SC_Event') || !class_exists('SC_Attendee')) {
        return $stats;
    }

    $events_table = SC_Event::get_table();
    $attendees_table = SC_Attendee::get_table();

    // Events stats
    $where_author = $author_id ? $wpdb->prepare(" AND author_id = %d", $author_id) : "";

    $event_stats = $wpdb->get_row(
        "SELECT
            COUNT(*) as total_events,
            SUM(CASE WHEN start_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events
        FROM $events_table
        WHERE status = 'publish'" . $where_author
    );

    $stats['total_events'] = (int) ($event_stats->total_events ?? 0);
    $stats['upcoming_events'] = (int) ($event_stats->upcoming_events ?? 0);

    // Attendees stats
    if ($author_id) {
        $attendee_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_attendees,
                SUM(CASE WHEN a.checked_in = 1 THEN 1 ELSE 0 END) as checked_in,
                COALESCE(SUM(a.amount_paid), 0) as total_revenue
            FROM $attendees_table a
            INNER JOIN $events_table e ON a.event_id = e.id
            WHERE a.status = 'active' AND e.author_id = %d",
            $author_id
        ));
    } else {
        $attendee_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_attendees,
                SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as checked_in,
                COALESCE(SUM(amount_paid), 0) as total_revenue
            FROM $attendees_table
            WHERE status = 'active'"
        );
    }

    $stats['total_attendees'] = (int) ($attendee_stats->total_attendees ?? 0);
    $stats['checked_in'] = (int) ($attendee_stats->checked_in ?? 0);
    $stats['total_revenue'] = (float) ($attendee_stats->total_revenue ?? 0);

    return $stats;
}

/**
 * =========================================
 * HELPER FUNCTIONS
 * =========================================
 */

/**
 * Generate unique ticket code
 *
 * @return string
 */
function scdb_generate_ticket_code() {
    $prefix = 'SC';
    $timestamp = substr(time(), -6);
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    return $prefix . $timestamp . $random;
}

/**
 * Format currency amount using dynamic currency settings
 *
 * @param float $amount Amount
 * @param string $currency Currency code (deprecated, uses settings)
 * @return string
 */
function scdb_format_currency($amount, $currency = null) {
    // Use the centralized sc_format_price function
    return sc_format_price($amount);
}

/**
 * Check if custom tables are available and have data
 *
 * @return bool
 */
function scdb_tables_ready() {
    global $wpdb;

    if (!class_exists('SC_Event')) {
        return false;
    }

    $table = SC_Event::get_table();
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

    return !empty($exists);
}
