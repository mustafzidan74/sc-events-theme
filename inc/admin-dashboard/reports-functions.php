<?php
/**
 * Reports Helper Functions - Enhanced Revenue Calculation
 * Uses Custom Tables (sc_attendees) for data
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get accurate event statistics using Custom Tables
 *
 * Priority:
 * 1. Custom Tables (sc_attendees) - Primary source
 * 2. WooCommerce Orders (for additional payment data)
 *
 * @param int $event_id Event ID (from Custom Tables)
 * @return array Statistics array
 */
function sc_get_event_statistics($event_id) {
    global $wpdb;

    $stats = array(
        'tickets_sold'        => 0,
        'revenue'             => 0,
        'daily_revenue'       => array(),
        'ticket_breakdown'    => array(),
        'attendees'           => array(),
        'order_status_counts' => array(
            'confirmed' => 0,
            'pending'   => 0,
            'cancelled' => 0,
        ),
    );

    // Get from Custom Tables (sc_attendees)
    $stats = sc_get_stats_from_custom_tables($event_id, $stats);

    return $stats;
}

/**
 * Get statistics from Custom Tables (sc_attendees)
 * This is the primary method
 */
function sc_get_stats_from_custom_tables($event_id, $stats) {
    global $wpdb;

    // Get all attendees for this event from Custom Tables
    $attendees = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}sc_attendees
        WHERE event_id = %d
        AND status = 'active'
        ORDER BY created_at DESC
    ", $event_id));

    foreach ($attendees as $attendee) {
        // Get ticket info
        $ticket_name = $attendee->ticket_name ?: __('Standard Ticket', 'sc_events');
        $ticket_price = (float) $attendee->amount_paid;
        $ticket_qty = 1; // Each attendee record = 1 ticket

        // Get payment status
        $payment_status = $attendee->payment_status;

        // Count by status
        if ($payment_status === 'success' || $payment_status === 'completed') {
            $stats['order_status_counts']['confirmed'] += $ticket_qty;
        } elseif ($payment_status === 'cancelled' || $payment_status === 'refunded') {
            $stats['order_status_counts']['cancelled'] += $ticket_qty;
            continue; // Don't count cancelled
        } else {
            $stats['order_status_counts']['pending'] += $ticket_qty;
        }

        $stats['tickets_sold'] += $ticket_qty;
        $stats['revenue'] += $ticket_price;

        // Get date
        $date_key = date('Y-m-d', strtotime($attendee->created_at));
        $date_str = date('M j, Y', strtotime($attendee->created_at));

        // Daily revenue
        if ($date_key) {
            if (!isset($stats['daily_revenue'][$date_key])) {
                $stats['daily_revenue'][$date_key] = 0;
            }
            $stats['daily_revenue'][$date_key] += $ticket_price;
        }

        // Ticket breakdown
        if (!isset($stats['ticket_breakdown'][$ticket_name])) {
            $stats['ticket_breakdown'][$ticket_name] = 0;
        }
        $stats['ticket_breakdown'][$ticket_name] += $ticket_qty;

        // Attendee info - use dynamic currency formatting
        $stats['attendees'][] = array(
            'name'            => $attendee->name,
            'email'           => $attendee->email,
            'date'            => $date_str,
            'ticket'          => $ticket_name,
            'status'          => $payment_status ?: 'success',
            'total'           => $ticket_price,
            'total_formatted' => sc_format_price($ticket_price, false),
            'order_id'        => $attendee->order_id,
        );
    }

    return $stats;
}

/**
 * Get event capacity from Custom Tables
 */
function sc_get_event_capacity($event_id) {
    global $wpdb;

    // Get capacity from sc_tickets table
    $capacity = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(quantity)
        FROM {$wpdb->prefix}sc_tickets
        WHERE event_id = %d
        AND is_active = 1
    ", $event_id));

    return $capacity > 0 ? (int) $capacity : 0;
}

/**
 * Get all event meta data from Custom Tables
 */
function sc_get_event_meta($event_id) {
    // Get event from Custom Tables
    $event = class_exists('SC_Event') ? SC_Event::get($event_id) : null;

    if (!$event) {
        return array(
            'start_date'  => '',
            'end_date'    => '',
            'location'    => '',
            'capacity'    => 0,
            'organizer'   => '',
            'category'    => array(),
        );
    }

    return array(
        'start_date'  => $event->start_date,
        'end_date'    => $event->end_date,
        'location'    => $event->location,
        'capacity'    => sc_get_event_capacity($event_id),
        'organizer'   => '', // Can be fetched from organizers if needed
        'category'    => get_the_terms($event_id, 'sc_event_category'),
    );
}

/**
 * Calculate attendance percentage
 */
function sc_calculate_attendance_rate($tickets_sold, $capacity) {
    if ($capacity <= 0) {
        return 0;
    }
    return round(($tickets_sold / $capacity) * 100, 2);
}

// Note: sc_get_currency_symbol() and sc_format_price() are now defined in utilities.php
// with the dynamic currency system

/**
 * Get total platform revenue (all events)
 * Uses Custom Tables (sc_attendees)
 */
function sc_get_total_platform_revenue() {
    global $wpdb;

    // Try cache first (10 minutes)
    $cache_key = 'sc_total_platform_revenue';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return (float) $cached;
    }

    // Get from Custom Tables (sc_attendees)
    $total_revenue = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(amount_paid), 0)
        FROM {$wpdb->prefix}sc_attendees
        WHERE status = 'active'
        AND payment_status IN ('success', 'completed')"
    );

    // Cache for 10 minutes
    set_transient($cache_key, $total_revenue, 600);

    return $total_revenue;
}

/**
 * Get total platform tickets sold
 * Uses Custom Tables (sc_attendees)
 */
function sc_get_total_platform_tickets() {
    global $wpdb;

    // Try cache first (10 minutes)
    $cache_key = 'sc_total_platform_tickets';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return (int) $cached;
    }

    // Get from Custom Tables
    $total = $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$wpdb->prefix}sc_attendees
        WHERE status = 'active'"
    );

    $total = (int) $total;

    // Cache for 10 minutes
    set_transient($cache_key, $total, 600);

    return $total;
}

/**
 * Get reports dashboard statistics with caching
 * OPTIMIZED: Uses Custom Tables (sc_events, sc_attendees)
 */
function sc_get_reports_dashboard_stats() {
    global $wpdb;

    // Try cache first (5 minutes)
    $cache_key = 'sc_reports_dashboard_stats';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $stats = array(
        'total_events' => 0,
        'total_attendees' => 0,
        'total_revenue' => 0,
        'total_tickets' => 0,
        'events_this_month' => 0,
        'revenue_this_month' => 0,
        'top_events' => array(),
    );

    // Get total events count from Custom Tables
    $stats['total_events'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events
        WHERE status = 'publish'"
    );

    // Get total attendees and revenue from Custom Tables (sc_attendees)
    $attendee_stats = $wpdb->get_row(
        "SELECT
            COUNT(*) as total_attendees,
            SUM(amount_paid) as total_revenue
        FROM {$wpdb->prefix}sc_attendees
        WHERE status = 'active'
        AND payment_status IN ('success', 'completed')"
    );

    if ($attendee_stats) {
        $stats['total_attendees'] = (int) $attendee_stats->total_attendees;
        $stats['total_tickets'] = (int) $attendee_stats->total_attendees; // Each attendee = 1 ticket
        $stats['total_revenue'] = (float) $attendee_stats->total_revenue;
    }

    // Get events this month from Custom Tables
    $stats['events_this_month'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events
        WHERE status = 'publish'
        AND start_date >= %s
        AND start_date < %s",
        date('Y-m-01'),
        date('Y-m-01', strtotime('+1 month'))
    ));

    // Get revenue this month from Custom Tables (sc_attendees)
    $stats['revenue_this_month'] = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount_paid)
        FROM {$wpdb->prefix}sc_attendees
        WHERE status = 'active'
        AND payment_status IN ('success', 'completed')
        AND created_at >= %s",
        date('Y-m-01 00:00:00')
    ));

    // Get top 5 events by ticket sales from Custom Tables
    $top_events = $wpdb->get_results(
        "SELECT
            a.event_id,
            COUNT(*) as attendee_count,
            COUNT(*) as tickets_sold,
            SUM(a.amount_paid) as revenue
        FROM {$wpdb->prefix}sc_attendees a
        WHERE a.status = 'active'
        AND a.payment_status IN ('success', 'completed')
        GROUP BY a.event_id
        ORDER BY tickets_sold DESC
        LIMIT 5"
    );

    if ($top_events) {
        // Get event titles from Custom Tables
        $event_ids = wp_list_pluck($top_events, 'event_id');
        $event_ids = array_filter(array_map('intval', $event_ids));

        if (!empty($event_ids)) {
            $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
            $event_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title FROM {$wpdb->prefix}sc_events WHERE id IN ($placeholders)",
                    ...$event_ids
                ),
                OBJECT_K
            );

            foreach ($top_events as $event) {
                $event_id = (int) $event->event_id;
                $stats['top_events'][] = array(
                    'id' => $event_id,
                    'title' => isset($event_data[$event_id]) ? $event_data[$event_id]->title : __('Unknown Event', 'sc_events'),
                    'tickets_sold' => (int) $event->tickets_sold,
                    'revenue' => (float) $event->revenue,
                );
            }
        }
    }

    // Cache for 5 minutes
    set_transient($cache_key, $stats, 300);

    return $stats;
}

/**
 * Get daily revenue for date range (optimized)
 * Uses Custom Tables (sc_attendees)
 *
 * @param string $start_date Start date (Y-m-d)
 * @param string $end_date End date (Y-m-d)
 * @param int|null $event_id Optional event ID filter
 * @return array Daily revenue data
 */
function sc_get_daily_revenue($start_date, $end_date, $event_id = null) {
    global $wpdb;

    $cache_key = 'sc_daily_revenue_' . md5($start_date . $end_date . ($event_id ?: 'all'));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $where_event = '';
    $prepare_args = array($start_date . ' 00:00:00', $end_date . ' 23:59:59');

    if ($event_id) {
        $where_event = "AND event_id = %d";
        $prepare_args[] = $event_id;
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT
            DATE(created_at) as date,
            SUM(amount_paid) as revenue,
            COUNT(*) as tickets
        FROM {$wpdb->prefix}sc_attendees
        WHERE status = 'active'
        AND payment_status IN ('success', 'completed')
        AND created_at BETWEEN %s AND %s
        {$where_event}
        GROUP BY DATE(created_at)
        ORDER BY date ASC",
        ...$prepare_args
    ));

    $data = array();
    foreach ($results as $row) {
        $data[$row->date] = array(
            'revenue' => (float) $row->revenue,
            'tickets' => (int) $row->tickets,
        );
    }

    // Cache for 5 minutes
    set_transient($cache_key, $data, 300);

    return $data;
}

/**
 * Clear reports cache when data changes
 * Hooks into Custom Tables actions
 */
add_action('sc_attendee_created', 'sc_clear_reports_cache');
add_action('sc_attendee_updated', 'sc_clear_reports_cache');
add_action('sc_attendee_deleted', 'sc_clear_reports_cache');
add_action('sc_event_created', 'sc_clear_reports_cache', 10, 1);
add_action('sc_event_updated', 'sc_clear_reports_cache');
add_action('sc_event_deleted', 'sc_clear_reports_cache');

function sc_clear_reports_cache($id = null) {
    delete_transient('sc_total_platform_revenue');
    delete_transient('sc_total_platform_tickets');
    delete_transient('sc_reports_dashboard_stats');

    // Clear daily revenue caches (pattern-based would need wp_cache)
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_sc_daily_revenue_%'
        OR option_name LIKE '_transient_timeout_sc_daily_revenue_%'"
    );
}
