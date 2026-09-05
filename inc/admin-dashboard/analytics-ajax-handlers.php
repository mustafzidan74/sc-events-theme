<?php
/**
 * Advanced Analytics AJAX Handlers
 *
 * Backend data providers for the Advanced Analytics dashboard page.
 * Handles KPI overview, revenue charts, registration trends,
 * attendance analysis, ticket analytics, event comparison, and CSV export.
 *
 * @package sc_events
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper: Build date range from filter parameters.
 *
 * Returns an array of [$date_from, $date_to, $prev_from, $prev_to].
 * The "prev" range is the same length period immediately before $date_from,
 * used for calculating delta percentages.
 *
 * @return array [string $date_from, string $date_to, string $prev_from, string $prev_to]
 */
function sc_analytics_get_date_range() {
    $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : 'all';

    $today = current_time('Y-m-d');

    switch ($date_range) {
        case 'all':
            // All time — use very wide date range to include everything
            $date_from = '2000-01-01';
            $date_to   = '2099-12-31';
            break;

        case 'today':
            $date_from = $today;
            $date_to   = $today;
            break;

        case '7days':
            $date_from = date('Y-m-d', strtotime('-6 days', strtotime($today)));
            $date_to   = $today;
            break;

        case '90days':
            $date_from = date('Y-m-d', strtotime('-89 days', strtotime($today)));
            $date_to   = $today;
            break;

        case 'custom':
            $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-29 days', strtotime($today)));
            $date_to   = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : $today;
            break;

        case '30days':
        default:
            $date_from = date('Y-m-d', strtotime('-29 days', strtotime($today)));
            $date_to   = $today;
            break;
    }

    // Ensure valid dates
    if (!strtotime($date_from)) {
        $date_from = date('Y-m-d', strtotime('-29 days', strtotime($today)));
    }
    if (!strtotime($date_to)) {
        $date_to = $today;
    }

    // Calculate previous period of the same length
    $range_days = (strtotime($date_to) - strtotime($date_from)) / 86400;
    $prev_to    = date('Y-m-d', strtotime('-1 day', strtotime($date_from)));
    $prev_from  = date('Y-m-d', strtotime('-' . intval($range_days) . ' days', strtotime($prev_to)));

    return array($date_from, $date_to, $prev_from, $prev_to);
}

/**
 * Helper: Build common WHERE conditions and params for author/event filtering.
 *
 * @param string $attendee_alias  Alias for the attendees table (e.g. 'a').
 * @param string $event_alias     Alias for the events table (e.g. 'e').
 * @param string $date_column     Column for date range (e.g. 'a.created_at').
 * @param string $date_from       Start date.
 * @param string $date_to         End date.
 * @return array ['where' => string, 'params' => array]
 */
function sc_analytics_build_filters($attendee_alias, $event_alias, $date_column, $date_from, $date_to) {
    $where  = '';
    $params = array();

    // Date range
    if ($date_column) {
        $where .= " AND {$date_column} BETWEEN %s AND %s";
        $params[] = $date_from . ' 00:00:00';
        $params[] = $date_to . ' 23:59:59';
    }

    // Author filter for non-admin users
    if (!current_user_can('manage_options') && $event_alias) {
        $where .= " AND {$event_alias}.author_id = %d";
        $params[] = get_current_user_id();
    }

    // Event filter
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if ($event_id > 0 && $event_alias) {
        $where .= " AND {$event_alias}.id = %d";
        $params[] = $event_id;
    }

    return array('where' => $where, 'params' => $params);
}

/**
 * Helper: Calculate delta percentage between two values.
 *
 * @param float $current  Current period value.
 * @param float $previous Previous period value.
 * @return float Percentage change, rounded to 1 decimal.
 */
function sc_analytics_delta_pct($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

// ============================================================================
// 1. sc_analytics_overview - KPI Cards
// ============================================================================
add_action('wp_ajax_sc_analytics_overview', 'sc_ajax_analytics_overview');
function sc_ajax_analytics_overview() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Check transient cache
    $cache_params = array(
        'date_range' => isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '30days',
        'date_from'  => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
        'date_to'    => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
        'event_id'   => isset($_POST['event_id']) ? intval($_POST['event_id']) : 0,
        'user_id'    => get_current_user_id(),
        'is_admin'   => current_user_can('manage_options') ? 1 : 0,
    );
    $cache_key = 'sc_analytics_overview_' . md5(serialize($cache_params));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        wp_send_json_success($cached);
    }

    global $wpdb;
    $attendees_table    = $wpdb->prefix . 'sc_attendees';
    $events_table       = $wpdb->prefix . 'sc_events';
    $transactions_table = $wpdb->prefix . 'sc_transactions';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();

    // ---- Current period ----
    $filters_current = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);

    // Total revenue
    $sql = "SELECT COALESCE(SUM(a.amount_paid), 0)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters_current['where']}";
    $total_revenue = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params']));

    // Total registrations
    $sql = "SELECT COUNT(*)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters_current['where']}";
    $total_registrations = (int) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params']));

    // Check-in rate (uses ALL active attendees as denominator, not just paid)
    $sql = "SELECT
                COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in,
                COUNT(*) as total
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active'
            {$filters_current['where']}";
    $checkin_data = $wpdb->get_row($wpdb->prepare($sql, $filters_current['params']));
    $checkin_rate = ($checkin_data && $checkin_data->total > 0)
        ? round(($checkin_data->checked_in / $checkin_data->total) * 100, 1)
        : 0.0;

    // Average order value (only paid tickets)
    $sql = "SELECT COALESCE(AVG(a.amount_paid), 0)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success' AND a.amount_paid > 0
            {$filters_current['where']}";
    $avg_order_value = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params']));
    $avg_order_value = round($avg_order_value, 2);

    // Total refunds from transactions table
    $filters_refunds = sc_analytics_build_filters('t', 'e', 't.refunded_at', $date_from, $date_to);
    $sql = "SELECT COALESCE(SUM(t.refund_amount), 0)
            FROM {$transactions_table} t
            INNER JOIN {$events_table} e ON t.event_id = e.id
            WHERE t.status = 'refunded'
            {$filters_refunds['where']}";
    $total_refunds = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_refunds['params']));

    // ---- Previous period ----
    $filters_prev = sc_analytics_build_filters('a', 'e', 'a.created_at', $prev_from, $prev_to);

    // Previous revenue
    $sql = "SELECT COALESCE(SUM(a.amount_paid), 0)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters_prev['where']}";
    $prev_revenue = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params']));

    // Previous registrations
    $sql = "SELECT COUNT(*)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters_prev['where']}";
    $prev_registrations = (int) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params']));

    // Previous check-in rate (ALL active attendees)
    $sql = "SELECT
                COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in,
                COUNT(*) as total
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active'
            {$filters_prev['where']}";
    $prev_checkin_data = $wpdb->get_row($wpdb->prepare($sql, $filters_prev['params']));
    $prev_checkin_rate = ($prev_checkin_data && $prev_checkin_data->total > 0)
        ? round(($prev_checkin_data->checked_in / $prev_checkin_data->total) * 100, 1)
        : 0.0;

    // Previous average order value
    $sql = "SELECT COALESCE(AVG(a.amount_paid), 0)
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success' AND a.amount_paid > 0
            {$filters_prev['where']}";
    $prev_avg_order_value = round((float) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params'])), 2);

    // Previous refunds
    $filters_prev_refunds = sc_analytics_build_filters('t', 'e', 't.refunded_at', $prev_from, $prev_to);
    $sql = "SELECT COALESCE(SUM(t.refund_amount), 0)
            FROM {$transactions_table} t
            INNER JOIN {$events_table} e ON t.event_id = e.id
            WHERE t.status = 'refunded'
            {$filters_prev_refunds['where']}";
    $prev_refunds = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_prev_refunds['params']));

    // Build response
    $data = array(
        'total_revenue' => array(
            'value'     => $total_revenue,
            'prev'      => $prev_revenue,
            'delta_pct' => sc_analytics_delta_pct($total_revenue, $prev_revenue),
        ),
        'total_registrations' => array(
            'value'     => $total_registrations,
            'prev'      => $prev_registrations,
            'delta_pct' => sc_analytics_delta_pct($total_registrations, $prev_registrations),
        ),
        'checkin_rate' => array(
            'value'     => $checkin_rate,
            'prev'      => $prev_checkin_rate,
            'delta_pct' => sc_analytics_delta_pct($checkin_rate, $prev_checkin_rate),
        ),
        'avg_order_value' => array(
            'value'     => $avg_order_value,
            'prev'      => $prev_avg_order_value,
            'delta_pct' => sc_analytics_delta_pct($avg_order_value, $prev_avg_order_value),
        ),
        'total_refunds' => array(
            'value'     => $total_refunds,
            'prev'      => $prev_refunds,
            'delta_pct' => sc_analytics_delta_pct($total_refunds, $prev_refunds),
        ),
        'date_from' => $date_from,
        'date_to'   => $date_to,
    );

    // Cache for 5 minutes
    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);

    wp_send_json_success($data);
}

// ============================================================================
// 2. sc_analytics_revenue - Revenue Charts
// ============================================================================
add_action('wp_ajax_sc_analytics_revenue', 'sc_ajax_analytics_revenue');
function sc_ajax_analytics_revenue() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table    = $wpdb->prefix . 'sc_attendees';
    $events_table       = $wpdb->prefix . 'sc_events';
    $transactions_table = $wpdb->prefix . 'sc_transactions';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();
    $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);

    // --- Revenue over time ---
    $sql = "SELECT DATE(a.created_at) as date, COALESCE(SUM(a.amount_paid), 0) as revenue
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY DATE(a.created_at)
            ORDER BY date ASC";
    $over_time_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    // Fill in missing dates with zero
    $over_time = array();
    $date_cursor = strtotime($date_from);
    $date_end    = strtotime($date_to);
    $indexed     = array();
    foreach ($over_time_rows as $row) {
        $indexed[$row->date] = (float) $row->revenue;
    }
    while ($date_cursor <= $date_end) {
        $d = date('Y-m-d', $date_cursor);
        $over_time[] = array(
            'date'    => $d,
            'revenue' => isset($indexed[$d]) ? $indexed[$d] : 0,
        );
        $date_cursor = strtotime('+1 day', $date_cursor);
    }

    // --- Revenue by payment method ---
    $sql = "SELECT a.payment_method as method,
                   COALESCE(SUM(a.amount_paid), 0) as total,
                   COUNT(*) as count
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY a.payment_method
            ORDER BY total DESC";
    $by_payment_method = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    $by_payment_method_arr = array();
    foreach ($by_payment_method as $row) {
        $by_payment_method_arr[] = array(
            'method' => $row->method ? $row->method : 'unknown',
            'total'  => (float) $row->total,
            'count'  => (int) $row->count,
        );
    }

    // --- Revenue by event (top 10) ---
    $sql = "SELECT e.id as event_id, e.title,
                   COALESCE(SUM(a.amount_paid), 0) as revenue,
                   COUNT(a.id) as count
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY e.id
            ORDER BY revenue DESC
            LIMIT 10";
    $by_event_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    $by_event = array();
    foreach ($by_event_rows as $row) {
        $by_event[] = array(
            'event_id' => (int) $row->event_id,
            'title'    => $row->title,
            'revenue'  => (float) $row->revenue,
            'count'    => (int) $row->count,
        );
    }

    // --- Refunds over time ---
    $filters_refunds = sc_analytics_build_filters('t', 'e', 't.refunded_at', $date_from, $date_to);
    $sql = "SELECT DATE(t.refunded_at) as date, COALESCE(SUM(t.refund_amount), 0) as amount
            FROM {$transactions_table} t
            INNER JOIN {$events_table} e ON t.event_id = e.id
            WHERE t.status = 'refunded'
            {$filters_refunds['where']}
            GROUP BY DATE(t.refunded_at)
            ORDER BY date ASC";
    $refund_rows = $wpdb->get_results($wpdb->prepare($sql, $filters_refunds['params']));

    $refunds = array();
    foreach ($refund_rows as $row) {
        $refunds[] = array(
            'date'   => $row->date,
            'amount' => (float) $row->amount,
        );
    }

    wp_send_json_success(array(
        'over_time'         => $over_time,
        'by_payment_method' => $by_payment_method_arr,
        'by_event'          => $by_event,
        'refunds'           => $refunds,
    ));
}

// ============================================================================
// 3. sc_analytics_registrations - Registration Charts
// ============================================================================
add_action('wp_ajax_sc_analytics_registrations', 'sc_ajax_analytics_registrations');
function sc_ajax_analytics_registrations() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $events_table    = $wpdb->prefix . 'sc_events';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();
    $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);

    // --- Registrations over time ---
    $sql = "SELECT DATE(a.created_at) as date, COUNT(*) as count
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY DATE(a.created_at)
            ORDER BY date ASC";
    $over_time_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    // Fill in missing dates
    $over_time = array();
    $date_cursor = strtotime($date_from);
    $date_end    = strtotime($date_to);
    $indexed     = array();
    foreach ($over_time_rows as $row) {
        $indexed[$row->date] = (int) $row->count;
    }
    while ($date_cursor <= $date_end) {
        $d = date('Y-m-d', $date_cursor);
        $over_time[] = array(
            'date'  => $d,
            'count' => isset($indexed[$d]) ? $indexed[$d] : 0,
        );
        $date_cursor = strtotime('+1 day', $date_cursor);
    }

    // --- Peak hours (registrations by hour of day) ---
    $sql = "SELECT HOUR(a.created_at) as hour, COUNT(*) as count
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY HOUR(a.created_at)
            ORDER BY hour ASC";
    $by_hour_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    // Fill all 24 hours
    $by_hour = array();
    $hour_indexed = array();
    foreach ($by_hour_rows as $row) {
        $hour_indexed[(int) $row->hour] = (int) $row->count;
    }
    for ($h = 0; $h < 24; $h++) {
        $by_hour[] = array(
            'hour'  => $h,
            'count' => isset($hour_indexed[$h]) ? $hour_indexed[$h] : 0,
        );
    }

    // --- Peak days of week (1=Sunday through 7=Saturday per MySQL DAYOFWEEK) ---
    $sql = "SELECT DAYOFWEEK(a.created_at) as day, COUNT(*) as count
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY DAYOFWEEK(a.created_at)
            ORDER BY day ASC";
    $by_day_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    // Fill all 7 days
    $by_day_of_week = array();
    $day_indexed = array();
    foreach ($by_day_rows as $row) {
        $day_indexed[(int) $row->day] = (int) $row->count;
    }
    for ($d = 1; $d <= 7; $d++) {
        $by_day_of_week[] = array(
            'day'   => $d,
            'count' => isset($day_indexed[$d]) ? $day_indexed[$d] : 0,
        );
    }

    wp_send_json_success(array(
        'over_time'      => $over_time,
        'by_hour'        => $by_hour,
        'by_day_of_week' => $by_day_of_week,
    ));
}

// ============================================================================
// 4. sc_analytics_attendance - Attendance Charts
// ============================================================================
add_action('wp_ajax_sc_analytics_attendance', 'sc_ajax_analytics_attendance');
function sc_ajax_analytics_attendance() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table          = $wpdb->prefix . 'sc_attendees';
    $events_table             = $wpdb->prefix . 'sc_events';
    $checkins_table           = $wpdb->prefix . 'sc_checkins';
    $session_attendance_table = $wpdb->prefix . 'sc_session_attendance';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();

    // --- Attendance by event (ALL active attendees for accurate check-in rate) ---
    $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
    $sql = "SELECT e.id as event_id, e.title,
                   COUNT(a.id) as total,
                   COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active'
            {$filters['where']}
            GROUP BY e.id
            ORDER BY total DESC";
    $by_event_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    $by_event = array();
    foreach ($by_event_rows as $row) {
        $total = (int) $row->total;
        $checked = (int) $row->checked_in;
        $by_event[] = array(
            'event_id'   => (int) $row->event_id,
            'title'      => $row->title,
            'total'      => $total,
            'checked_in' => $checked,
            'rate'       => $total > 0 ? round(($checked / $total) * 100, 1) : 0,
        );
    }

    // --- Check-in time distribution (from sc_checkins table) ---
    $filters_checkins = sc_analytics_build_filters('c', 'e', 'c.created_at', $date_from, $date_to);
    $sql = "SELECT HOUR(c.created_at) as hour, COUNT(*) as count
            FROM {$checkins_table} c
            INNER JOIN {$events_table} e ON c.event_id = e.id
            WHERE 1=1
            {$filters_checkins['where']}
            GROUP BY HOUR(c.created_at)
            ORDER BY hour ASC";
    $time_dist_rows = $wpdb->get_results($wpdb->prepare($sql, $filters_checkins['params']));

    // Fill all 24 hours
    $time_distribution = array();
    $hour_indexed = array();
    foreach ($time_dist_rows as $row) {
        $hour_indexed[(int) $row->hour] = (int) $row->count;
    }
    for ($h = 0; $h < 24; $h++) {
        $time_distribution[] = array(
            'hour'  => $h,
            'count' => isset($hour_indexed[$h]) ? $hour_indexed[$h] : 0,
        );
    }

    // --- Session attendance (only if sessions module is enabled) ---
    $session_attendance = array();
    if (function_exists('sc_is_module_enabled') && sc_is_module_enabled('sessions')) {
        $sessions_table = $wpdb->prefix . 'sc_sessions';

        // Check if the session_attendance table exists before querying
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $session_attendance_table
            )
        );

        if ($table_exists) {
            $filters_sess = sc_analytics_build_filters('sa', 'e', 'sa.check_in_time', $date_from, $date_to);

            // We need to join through sessions to get to events for author filtering
            $sql = "SELECT s.title as session_name,
                           ROUND(AVG(sa.attendance_percentage), 1) as avg_attendance_pct
                    FROM {$session_attendance_table} sa
                    INNER JOIN {$wpdb->prefix}sc_sessions s ON sa.session_id = s.id
                    INNER JOIN {$events_table} e ON s.event_id = e.id
                    WHERE 1=1
                    {$filters_sess['where']}
                    GROUP BY sa.session_id
                    ORDER BY avg_attendance_pct DESC";

            if (!empty($filters_sess['params'])) {
                $session_rows = $wpdb->get_results($wpdb->prepare($sql, $filters_sess['params']));
            } else {
                $session_rows = $wpdb->get_results($sql);
            }

            foreach ($session_rows as $row) {
                $session_attendance[] = array(
                    'session_name'       => $row->session_name,
                    'avg_attendance_pct' => (float) $row->avg_attendance_pct,
                );
            }
        }
    }

    wp_send_json_success(array(
        'by_event'           => $by_event,
        'time_distribution'  => $time_distribution,
        'session_attendance' => $session_attendance,
    ));
}

// ============================================================================
// 5. sc_analytics_tickets - Ticket Analytics
// ============================================================================
add_action('wp_ajax_sc_analytics_tickets', 'sc_ajax_analytics_tickets');
function sc_ajax_analytics_tickets() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $events_table    = $wpdb->prefix . 'sc_events';
    $tickets_table   = $wpdb->prefix . 'sc_tickets';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();
    $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);

    // --- Tickets by type ---
    $sql = "SELECT t.name as ticket_name,
                   COUNT(a.id) as sold,
                   COALESCE(SUM(a.amount_paid), 0) as revenue,
                   ROUND(COALESCE(AVG(a.amount_paid), 0), 2) as avg_price
            FROM {$attendees_table} a
            INNER JOIN {$tickets_table} t ON a.ticket_id = t.id
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            {$filters['where']}
            GROUP BY t.name
            ORDER BY sold DESC";
    $by_type_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    $by_type = array();
    foreach ($by_type_rows as $row) {
        $by_type[] = array(
            'ticket_name' => $row->ticket_name,
            'sold'        => (int) $row->sold,
            'revenue'     => (float) $row->revenue,
            'avg_price'   => (float) $row->avg_price,
        );
    }

    // --- Coupon effectiveness ---
    $sql = "SELECT a.coupon_code as code,
                   COUNT(a.id) as uses,
                   COALESCE(SUM(a.coupon_discount), 0) as total_discount,
                   COALESCE(SUM(a.amount_paid), 0) as revenue_with_coupon
            FROM {$attendees_table} a
            INNER JOIN {$events_table} e ON a.event_id = e.id
            WHERE a.status = 'active' AND a.payment_status = 'success'
            AND a.coupon_code IS NOT NULL AND a.coupon_code != ''
            {$filters['where']}
            GROUP BY a.coupon_code
            ORDER BY uses DESC";
    $coupon_rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

    $coupon_effectiveness = array();
    foreach ($coupon_rows as $row) {
        $sanitized_code = function_exists('sc_sanitize_coupon_display') ? sc_sanitize_coupon_display($row->code) : $row->code;
        if (empty($sanitized_code)) continue;
        $coupon_effectiveness[] = array(
            'code'                => $sanitized_code,
            'uses'                => (int) $row->uses,
            'total_discount'      => (float) $row->total_discount,
            'revenue_with_coupon' => (float) $row->revenue_with_coupon,
        );
    }

    wp_send_json_success(array(
        'by_type'              => $by_type,
        'coupon_effectiveness' => $coupon_effectiveness,
    ));
}

// ============================================================================
// 6. sc_analytics_comparison - Event Comparison
// ============================================================================
add_action('wp_ajax_sc_analytics_comparison', 'sc_ajax_analytics_comparison');
function sc_ajax_analytics_comparison() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_ids_raw = isset($_POST['event_ids']) ? sanitize_text_field($_POST['event_ids']) : '';
    if (empty($event_ids_raw)) {
        wp_send_json_error(array('message' => __('No events selected for comparison.', 'sc_events')));
    }

    // Parse and validate event IDs (max 5)
    $event_ids = array_map('intval', explode(',', $event_ids_raw));
    $event_ids = array_filter($event_ids, function($id) { return $id > 0; });
    $event_ids = array_slice($event_ids, 0, 5);

    if (empty($event_ids)) {
        wp_send_json_error(array('message' => __('Invalid event IDs.', 'sc_events')));
    }

    global $wpdb;
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $events_table    = $wpdb->prefix . 'sc_events';
    $tickets_table   = $wpdb->prefix . 'sc_tickets';

    $is_admin = current_user_can('manage_options');
    $current_user_id = get_current_user_id();

    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

    // Author filter
    $author_filter = '';
    $author_params = array();
    if (!$is_admin) {
        $author_filter = 'AND e.author_id = %d';
        $author_params[] = $current_user_id;
    }

    $comparison = array();

    foreach ($event_ids as $eid) {
        $params = array($eid);
        $params = array_merge($params, $author_params);

        // Get event details
        $sql = "SELECT e.id, e.title
                FROM {$events_table} e
                WHERE e.id = %d {$author_filter}";
        $event = $wpdb->get_row($wpdb->prepare($sql, $params));

        if (!$event) {
            continue; // Skip if event not found or not owned by user
        }

        // Revenue and registrations
        $sql = "SELECT COALESCE(SUM(a.amount_paid), 0) as revenue,
                       COUNT(a.id) as registrations,
                       COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in,
                       COUNT(*) as total_for_rate
                FROM {$attendees_table} a
                WHERE a.event_id = %d AND a.status = 'active' AND a.payment_status = 'success'";
        $stats = $wpdb->get_row($wpdb->prepare($sql, array($eid)));

        $registrations = (int) $stats->registrations;
        $revenue       = (float) $stats->revenue;

        // Check-in rate based on ALL active attendees (not just paid)
        $checkin_sql = "SELECT COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in,
                               COUNT(*) as total
                        FROM {$attendees_table} a
                        WHERE a.event_id = %d AND a.status = 'active'";
        $checkin_stats = $wpdb->get_row($wpdb->prepare($checkin_sql, array($eid)));
        $checked_in   = (int) ($checkin_stats ? $checkin_stats->checked_in : 0);
        $checkin_total = (int) ($checkin_stats ? $checkin_stats->total : 0);
        $checkin_rate  = $checkin_total > 0 ? round(($checked_in / $checkin_total) * 100, 1) : 0;

        // Average ticket price
        $sql = "SELECT ROUND(COALESCE(AVG(a.amount_paid), 0), 2) as avg_price
                FROM {$attendees_table} a
                WHERE a.event_id = %d AND a.status = 'active' AND a.payment_status = 'success' AND a.amount_paid > 0";
        $avg_ticket_price = (float) $wpdb->get_var($wpdb->prepare($sql, array($eid)));

        // Top ticket type
        $sql = "SELECT t.name
                FROM {$attendees_table} a
                INNER JOIN {$tickets_table} t ON a.ticket_id = t.id
                WHERE a.event_id = %d AND a.status = 'active' AND a.payment_status = 'success'
                GROUP BY t.id
                ORDER BY COUNT(a.id) DESC
                LIMIT 1";
        $top_ticket = $wpdb->get_var($wpdb->prepare($sql, array($eid)));

        $comparison[] = array(
            'id'               => (int) $event->id,
            'title'            => $event->title,
            'revenue'          => $revenue,
            'registrations'    => $registrations,
            'checkin_rate'     => $checkin_rate,
            'avg_ticket_price' => $avg_ticket_price,
            'top_ticket'       => $top_ticket ? $top_ticket : __('N/A', 'sc_events'),
        );
    }

    wp_send_json_success($comparison);
}

// ============================================================================
// 7. sc_analytics_export - CSV Export
// ============================================================================
add_action('wp_ajax_sc_analytics_export', 'sc_ajax_analytics_export');
function sc_ajax_analytics_export() {
    // Support both GET and POST for export
    $nonce   = isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '';
    $section = isset($_REQUEST['section']) ? sanitize_text_field($_REQUEST['section']) : '';

    // Security check
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_die(__('Security check failed.', 'sc_events'));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(__('Permission denied.', 'sc_events'));
    }

    // Copy REQUEST params to POST for the helper functions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_POST['date_range'] = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
        $_POST['date_from']  = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $_POST['date_to']    = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $_POST['event_id']   = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    }

    if (empty($section)) {
        wp_die(__('No export section specified.', 'sc_events'));
    }

    global $wpdb;
    $attendees_table    = $wpdb->prefix . 'sc_attendees';
    $events_table       = $wpdb->prefix . 'sc_events';
    $tickets_table      = $wpdb->prefix . 'sc_tickets';
    $transactions_table = $wpdb->prefix . 'sc_transactions';
    $checkins_table     = $wpdb->prefix . 'sc_checkins';

    list($date_from, $date_to, $prev_from, $prev_to) = sc_analytics_get_date_range();

    // Set headers for CSV download
    $filename = 'analytics-' . $section . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    switch ($section) {

        case 'overview':
            fputcsv($output, array('Metric', 'Current Period', 'Previous Period', 'Change %'));

            $filters_current = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
            $filters_prev    = sc_analytics_build_filters('a', 'e', 'a.created_at', $prev_from, $prev_to);

            // Revenue
            $sql = "SELECT COALESCE(SUM(a.amount_paid), 0) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' {$filters_current['where']}";
            $cur_rev = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params']));
            $sql = "SELECT COALESCE(SUM(a.amount_paid), 0) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' {$filters_prev['where']}";
            $prev_rev = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params']));
            fputcsv($output, array('Total Revenue', $cur_rev, $prev_rev, sc_analytics_delta_pct($cur_rev, $prev_rev) . '%'));

            // Registrations
            $sql = "SELECT COUNT(*) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' {$filters_current['where']}";
            $cur_reg = (int) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params']));
            $sql = "SELECT COUNT(*) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' {$filters_prev['where']}";
            $prev_reg = (int) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params']));
            fputcsv($output, array('Total Registrations', $cur_reg, $prev_reg, sc_analytics_delta_pct($cur_reg, $prev_reg) . '%'));

            // Check-in rate (ALL active attendees)
            $sql = "SELECT COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as ci, COUNT(*) as t FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' {$filters_current['where']}";
            $ci = $wpdb->get_row($wpdb->prepare($sql, $filters_current['params']));
            $cur_ci = ($ci && $ci->t > 0) ? round(($ci->ci / $ci->t) * 100, 1) : 0;
            $sql = "SELECT COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as ci, COUNT(*) as t FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' {$filters_prev['where']}";
            $ci_p = $wpdb->get_row($wpdb->prepare($sql, $filters_prev['params']));
            $prev_ci = ($ci_p && $ci_p->t > 0) ? round(($ci_p->ci / $ci_p->t) * 100, 1) : 0;
            fputcsv($output, array('Check-in Rate', $cur_ci . '%', $prev_ci . '%', sc_analytics_delta_pct($cur_ci, $prev_ci) . '%'));

            // AVG Order Value
            $sql = "SELECT COALESCE(AVG(a.amount_paid), 0) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' AND a.amount_paid > 0 {$filters_current['where']}";
            $cur_aov = round((float) $wpdb->get_var($wpdb->prepare($sql, $filters_current['params'])), 2);
            $sql = "SELECT COALESCE(AVG(a.amount_paid), 0) FROM {$attendees_table} a INNER JOIN {$events_table} e ON a.event_id = e.id WHERE a.status = 'active' AND a.payment_status = 'success' AND a.amount_paid > 0 {$filters_prev['where']}";
            $prev_aov = round((float) $wpdb->get_var($wpdb->prepare($sql, $filters_prev['params'])), 2);
            fputcsv($output, array('Avg Order Value', $cur_aov, $prev_aov, sc_analytics_delta_pct($cur_aov, $prev_aov) . '%'));

            // Refunds
            $filters_ref_c = sc_analytics_build_filters('t', 'e', 't.refunded_at', $date_from, $date_to);
            $filters_ref_p = sc_analytics_build_filters('t', 'e', 't.refunded_at', $prev_from, $prev_to);
            $sql = "SELECT COALESCE(SUM(t.refund_amount), 0) FROM {$transactions_table} t INNER JOIN {$events_table} e ON t.event_id = e.id WHERE t.status = 'refunded' {$filters_ref_c['where']}";
            $cur_ref = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_ref_c['params']));
            $sql = "SELECT COALESCE(SUM(t.refund_amount), 0) FROM {$transactions_table} t INNER JOIN {$events_table} e ON t.event_id = e.id WHERE t.status = 'refunded' {$filters_ref_p['where']}";
            $prev_ref = (float) $wpdb->get_var($wpdb->prepare($sql, $filters_ref_p['params']));
            fputcsv($output, array('Total Refunds', $cur_ref, $prev_ref, sc_analytics_delta_pct($cur_ref, $prev_ref) . '%'));

            break;

        case 'revenue':
            fputcsv($output, array('Date', 'Revenue'));

            $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
            $sql = "SELECT DATE(a.created_at) as date, COALESCE(SUM(a.amount_paid), 0) as revenue
                    FROM {$attendees_table} a
                    INNER JOIN {$events_table} e ON a.event_id = e.id
                    WHERE a.status = 'active' AND a.payment_status = 'success'
                    {$filters['where']}
                    GROUP BY DATE(a.created_at)
                    ORDER BY date ASC";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

            // Fill missing dates
            $indexed = array();
            foreach ($rows as $row) {
                $indexed[$row->date] = (float) $row->revenue;
            }
            $cursor = strtotime($date_from);
            $end    = strtotime($date_to);
            while ($cursor <= $end) {
                $d = date('Y-m-d', $cursor);
                fputcsv($output, array($d, isset($indexed[$d]) ? $indexed[$d] : 0));
                $cursor = strtotime('+1 day', $cursor);
            }
            break;

        case 'registrations':
            fputcsv($output, array('Date', 'Registrations'));

            $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
            $sql = "SELECT DATE(a.created_at) as date, COUNT(*) as count
                    FROM {$attendees_table} a
                    INNER JOIN {$events_table} e ON a.event_id = e.id
                    WHERE a.status = 'active' AND a.payment_status = 'success'
                    {$filters['where']}
                    GROUP BY DATE(a.created_at)
                    ORDER BY date ASC";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

            $indexed = array();
            foreach ($rows as $row) {
                $indexed[$row->date] = (int) $row->count;
            }
            $cursor = strtotime($date_from);
            $end    = strtotime($date_to);
            while ($cursor <= $end) {
                $d = date('Y-m-d', $cursor);
                fputcsv($output, array($d, isset($indexed[$d]) ? $indexed[$d] : 0));
                $cursor = strtotime('+1 day', $cursor);
            }
            break;

        case 'attendance':
            fputcsv($output, array('Event', 'Total Attendees', 'Checked In', 'Check-in Rate %'));

            $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
            $sql = "SELECT e.title,
                           COUNT(a.id) as total,
                           COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in
                    FROM {$attendees_table} a
                    INNER JOIN {$events_table} e ON a.event_id = e.id
                    WHERE a.status = 'active'
                    {$filters['where']}
                    GROUP BY e.id
                    ORDER BY total DESC";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

            foreach ($rows as $row) {
                $total   = (int) $row->total;
                $checked = (int) $row->checked_in;
                $rate    = $total > 0 ? round(($checked / $total) * 100, 1) : 0;
                fputcsv($output, array($row->title, $total, $checked, $rate . '%'));
            }
            break;

        case 'tickets':
            fputcsv($output, array('Ticket Type', 'Sold', 'Revenue', 'Avg Price'));

            $filters = sc_analytics_build_filters('a', 'e', 'a.created_at', $date_from, $date_to);
            $sql = "SELECT t.name as ticket_name,
                           COUNT(a.id) as sold,
                           COALESCE(SUM(a.amount_paid), 0) as revenue,
                           ROUND(COALESCE(AVG(a.amount_paid), 0), 2) as avg_price
                    FROM {$attendees_table} a
                    INNER JOIN {$tickets_table} t ON a.ticket_id = t.id
                    INNER JOIN {$events_table} e ON a.event_id = e.id
                    WHERE a.status = 'active' AND a.payment_status = 'success'
                    {$filters['where']}
                    GROUP BY t.name
                    ORDER BY sold DESC";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $filters['params']));

            foreach ($rows as $row) {
                fputcsv($output, array(
                    $row->ticket_name,
                    (int) $row->sold,
                    (float) $row->revenue,
                    (float) $row->avg_price,
                ));
            }
            break;

        default:
            fputcsv($output, array('Error: Unknown section "' . $section . '"'));
            break;
    }

    fclose($output);
    exit;
}
