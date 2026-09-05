<?php
/**
 * Reports Module
 *
 * Analytics and reporting for events, attendees, and revenue
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Reports_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'reports';

    /**
     * Module name
     */
    public $name = 'Reports';

    /**
     * Module description
     */
    public $description = 'Analytics and reporting for events, attendees, and revenue';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Dependencies
     */
    public $dependencies = array('events', 'tickets', 'attendees');

    /**
     * Priority (load late to ensure all data is available)
     */
    public $priority = 100;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // AJAX handlers for reports
        $this->register_ajax('sc_get_dashboard_stats', 'ajax_get_dashboard_stats');
        $this->register_ajax('sc_get_event_report', 'ajax_get_event_report');
        $this->register_ajax('sc_get_revenue_report', 'ajax_get_revenue_report');
        $this->register_ajax('sc_get_attendance_report', 'ajax_get_attendance_report');
        $this->register_ajax('sc_get_ticket_sales_report', 'ajax_get_ticket_sales_report');
        $this->register_ajax('sc_export_report', 'ajax_export_report');

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize module
     */
    public function init() {
        // Additional initialization
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sc-reports') === false && strpos($hook, 'sc-dashboard') === false) {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/reports';

        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        // CSS
        if (file_exists($this->get_path('assets/css/reports.css'))) {
            wp_enqueue_style(
                'sc-reports',
                $module_url . '/assets/css/reports.css',
                array(),
                $this->version
            );
        }

        // JS
        if (file_exists($this->get_path('assets/js/reports.js'))) {
            wp_enqueue_script(
                'sc-reports',
                $module_url . '/assets/js/reports.js',
                array('jquery', 'chartjs'),
                $this->version,
                true
            );

            wp_localize_script('sc-reports', 'scReportsConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_reports_nonce'),
                'currency' => sc_get_currency(),
                'currencySymbol' => sc_get_currency_symbol(),
                'i18n' => array(
                    'loading' => __('Loading...', 'sc_events'),
                    'noData' => __('No data available', 'sc_events'),
                    'error' => __('Failed to load report', 'sc_events'),
                    'export' => __('Export', 'sc_events'),
                    'attendees' => __('Attendees', 'sc_events'),
                    'revenue' => __('Revenue', 'sc_events'),
                    'tickets' => __('Tickets', 'sc_events'),
                    'checkedIn' => __('Checked In', 'sc_events'),
                    'pending' => __('Pending', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * AJAX: Get dashboard statistics
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $stats = $this->get_dashboard_stats();

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get event-specific report
     */
    public function ajax_get_event_report() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $report = $this->get_event_report($event_id);

        wp_send_json_success($report);
    }

    /**
     * AJAX: Get revenue report
     */
    public function ajax_get_revenue_report() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0) ?: null;
        $period = sanitize_text_field($_POST['period'] ?? 'month');

        $report = $this->get_revenue_report($event_id, $period);

        wp_send_json_success($report);
    }

    /**
     * AJAX: Get attendance report
     */
    public function ajax_get_attendance_report() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $report = $this->get_attendance_report($event_id);

        wp_send_json_success($report);
    }

    /**
     * AJAX: Get ticket sales report
     */
    public function ajax_get_ticket_sales_report() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0) ?: null;
        $period = sanitize_text_field($_POST['period'] ?? 'week');

        $report = $this->get_ticket_sales_report($event_id, $period);

        wp_send_json_success($report);
    }

    /**
     * AJAX: Export report to CSV
     */
    public function ajax_export_report() {
        check_ajax_referer('sc_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        $event_id = intval($_POST['event_id'] ?? 0) ?: null;

        $data = array();

        switch ($report_type) {
            case 'attendees':
                $data = $this->export_attendees($event_id);
                break;
            case 'tickets':
                $data = $this->export_tickets($event_id);
                break;
            case 'revenue':
                $data = $this->export_revenue($event_id);
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid report type', 'sc_events')));
        }

        wp_send_json_success(array('data' => $data));
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        global $wpdb;

        $stats = array();

        // Events
        $stats['events'] = array(
            'total' => wp_count_posts('etn')->publish ?? 0,
            'upcoming' => $this->count_upcoming_events(),
            'ongoing' => $this->count_ongoing_events(),
        );

        // Attendees
        if (class_exists('SC_Attendee')) {
            $attendee_table = SC_Attendee::get_table();
            $stats['attendees'] = array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $attendee_table WHERE status = 'active'"),
                'today' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $attendee_table WHERE status = 'active' AND DATE(created_at) = %s",
                    current_time('Y-m-d')
                )),
                'checked_in' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $attendee_table WHERE status = 'active' AND checked_in = 1"),
            );
        }

        // Tickets
        if (class_exists('SC_Ticket')) {
            $ticket_table = SC_Ticket::get_table();
            $stats['tickets'] = array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $ticket_table WHERE status = 'active'"),
                'sold' => (int) $wpdb->get_var("SELECT SUM(sold) FROM $ticket_table WHERE status = 'active'"),
            );
        }

        // Revenue
        if (class_exists('SC_Transaction')) {
            $tx_table = SC_Transaction::get_table();
            $stats['revenue'] = array(
                'total' => (float) $wpdb->get_var("SELECT SUM(total) FROM $tx_table WHERE status = 'success'"),
                'today' => (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(total) FROM $tx_table WHERE status = 'success' AND DATE(created_at) = %s",
                    current_time('Y-m-d')
                )),
                'this_month' => (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(total) FROM $tx_table WHERE status = 'success' AND MONTH(created_at) = %d AND YEAR(created_at) = %d",
                    current_time('n'),
                    current_time('Y')
                )),
            );
        }

        // Chat
        if (class_exists('SC_Chat')) {
            $chat = SC_Chat::get_instance();
            $chat_stats = $chat->get_chat_stats();
            $stats['chat'] = array(
                'total' => $chat_stats['total'] ?? 0,
                'active' => $chat_stats['active'] ?? 0,
                'unread' => $chat_stats['unread'] ?? 0,
            );
        }

        return $stats;
    }

    /**
     * Get comprehensive event report
     */
    public function get_event_report($event_id) {
        global $wpdb;

        $report = array(
            'event' => get_post($event_id),
            'tickets' => array(),
            'attendees' => array(),
            'revenue' => array(),
            'timeline' => array(),
        );

        // Ticket breakdown
        if (class_exists('SC_Ticket')) {
            $tickets = SC_Ticket::get_by_event($event_id);
            $report['tickets'] = array(
                'list' => $tickets,
                'total_capacity' => array_sum(array_column($tickets, 'quantity')),
                'total_sold' => array_sum(array_column($tickets, 'sold')),
                'by_type' => $this->group_tickets_by_type($tickets),
            );
        }

        // Attendee breakdown
        if (class_exists('SC_Attendee')) {
            $table = SC_Attendee::get_table();
            $report['attendees'] = array(
                'total' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active'",
                    $event_id
                )),
                'checked_in' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active' AND checked_in = 1",
                    $event_id
                )),
                'by_hour' => $this->get_checkin_by_hour($event_id),
            );
        }

        // Revenue breakdown
        if (class_exists('SC_Transaction')) {
            $table = SC_Transaction::get_table();
            $report['revenue'] = array(
                'total' => (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(total) FROM $table WHERE event_id = %d AND status = 'success'",
                    $event_id
                )),
                'by_payment_method' => $this->get_revenue_by_payment_method($event_id),
                'by_day' => $this->get_revenue_by_day($event_id),
            );
        }

        // Registration timeline
        $report['timeline'] = $this->get_registration_timeline($event_id);

        return $report;
    }

    /**
     * Get revenue report
     */
    public function get_revenue_report($event_id = null, $period = 'month') {
        global $wpdb;

        if (!class_exists('SC_Transaction')) {
            return array('data' => array(), 'total' => 0);
        }

        $table = SC_Transaction::get_table();

        switch ($period) {
            case 'week':
                $date_format = '%Y-%m-%d';
                $interval = '7 DAY';
                break;
            case 'year':
                $date_format = '%Y-%m';
                $interval = '1 YEAR';
                break;
            case 'month':
            default:
                $date_format = '%Y-%m-%d';
                $interval = '30 DAY';
                break;
        }

        $where = "status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
        $values = array();

        if ($event_id) {
            $where .= " AND event_id = %d";
            $values[] = $event_id;
        }

        $sql = "SELECT DATE_FORMAT(created_at, '$date_format') as date, SUM(total) as total, COUNT(*) as count
                FROM $table
                WHERE $where
                GROUP BY DATE_FORMAT(created_at, '$date_format')
                ORDER BY date ASC";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $data = $wpdb->get_results($sql);

        $total_sql = "SELECT SUM(total) FROM $table WHERE $where";
        if (!empty($values)) {
            $total_sql = $wpdb->prepare($total_sql, $values);
        }
        $total = (float) $wpdb->get_var($total_sql);

        return array(
            'data' => $data,
            'total' => $total,
            'period' => $period,
        );
    }

    /**
     * Get attendance report
     */
    public function get_attendance_report($event_id) {
        global $wpdb;

        if (!class_exists('SC_Attendee')) {
            return array();
        }

        $table = SC_Attendee::get_table();

        return array(
            'total' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active'",
                $event_id
            )),
            'checked_in' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active' AND checked_in = 1",
                $event_id
            )),
            'by_ticket' => $this->get_attendees_by_ticket($event_id),
            'by_hour' => $this->get_checkin_by_hour($event_id),
            'timeline' => $this->get_registration_timeline($event_id),
        );
    }

    /**
     * Get ticket sales report
     */
    public function get_ticket_sales_report($event_id = null, $period = 'week') {
        global $wpdb;

        if (!class_exists('SC_Attendee')) {
            return array('data' => array());
        }

        $table = SC_Attendee::get_table();

        switch ($period) {
            case 'month':
                $date_format = '%Y-%m-%d';
                $interval = '30 DAY';
                break;
            case 'week':
            default:
                $date_format = '%Y-%m-%d';
                $interval = '7 DAY';
                break;
        }

        $where = "status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
        $values = array();

        if ($event_id) {
            $where .= " AND event_id = %d";
            $values[] = $event_id;
        }

        $sql = "SELECT DATE_FORMAT(created_at, '$date_format') as date, COUNT(*) as count
                FROM $table
                WHERE $where
                GROUP BY DATE_FORMAT(created_at, '$date_format')
                ORDER BY date ASC";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $data = $wpdb->get_results($sql);

        return array(
            'data' => $data,
            'period' => $period,
        );
    }

    /**
     * Count upcoming events
     */
    private function count_upcoming_events() {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'etn' AND p.post_status = 'publish'
             AND pm.meta_key = 'etn_start_date' AND pm.meta_value > %s",
            current_time('Y-m-d')
        ));
    }

    /**
     * Count ongoing events
     */
    private function count_ongoing_events() {
        global $wpdb;
        $today = current_time('Y-m-d');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'etn_start_date'
             INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'etn_end_date'
             WHERE p.post_type = 'etn' AND p.post_status = 'publish'
             AND pm_start.meta_value <= %s AND pm_end.meta_value >= %s",
            $today,
            $today
        ));
    }

    /**
     * Group tickets by type
     */
    private function group_tickets_by_type($tickets) {
        $grouped = array();
        foreach ($tickets as $ticket) {
            $type = $ticket->ticket_type ?? 'standard';
            if (!isset($grouped[$type])) {
                $grouped[$type] = array(
                    'count' => 0,
                    'sold' => 0,
                    'revenue' => 0,
                );
            }
            $grouped[$type]['count']++;
            $grouped[$type]['sold'] += $ticket->sold;
            $grouped[$type]['revenue'] += $ticket->sold * $ticket->price;
        }
        return $grouped;
    }

    /**
     * Get check-ins by hour
     */
    private function get_checkin_by_hour($event_id) {
        global $wpdb;

        if (!class_exists('SC_Attendee')) {
            return array();
        }

        $table = SC_Attendee::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(checked_in_at) as hour, COUNT(*) as count
             FROM $table
             WHERE event_id = %d AND checked_in = 1 AND checked_in_at IS NOT NULL
             GROUP BY HOUR(checked_in_at)
             ORDER BY hour ASC",
            $event_id
        ));
    }

    /**
     * Get revenue by payment method
     */
    private function get_revenue_by_payment_method($event_id) {
        global $wpdb;

        if (!class_exists('SC_Transaction')) {
            return array();
        }

        $table = SC_Transaction::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT payment_method, SUM(total) as total, COUNT(*) as count
             FROM $table
             WHERE event_id = %d AND status = 'success'
             GROUP BY payment_method",
            $event_id
        ));
    }

    /**
     * Get revenue by day
     */
    private function get_revenue_by_day($event_id) {
        global $wpdb;

        if (!class_exists('SC_Transaction')) {
            return array();
        }

        $table = SC_Transaction::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, SUM(total) as total, COUNT(*) as count
             FROM $table
             WHERE event_id = %d AND status = 'success'
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $event_id
        ));
    }

    /**
     * Get attendees by ticket
     */
    private function get_attendees_by_ticket($event_id) {
        global $wpdb;

        if (!class_exists('SC_Attendee') || !class_exists('SC_Ticket')) {
            return array();
        }

        $attendee_table = SC_Attendee::get_table();
        $ticket_table = SC_Ticket::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.name as ticket_name, t.price, COUNT(a.id) as count
             FROM $attendee_table a
             INNER JOIN $ticket_table t ON a.ticket_id = t.id
             WHERE a.event_id = %d AND a.status = 'active'
             GROUP BY a.ticket_id
             ORDER BY count DESC",
            $event_id
        ));
    }

    /**
     * Get registration timeline
     */
    private function get_registration_timeline($event_id) {
        global $wpdb;

        if (!class_exists('SC_Attendee')) {
            return array();
        }

        $table = SC_Attendee::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM $table
             WHERE event_id = %d AND status = 'active'
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $event_id
        ));
    }

    /**
     * Export attendees to array for CSV
     */
    private function export_attendees($event_id = null) {
        if (!class_exists('SC_Attendee')) {
            return array();
        }

        $args = array('limit' => 10000, 'offset' => 0);
        if ($event_id) {
            $args['event_id'] = $event_id;
        }

        return SC_Attendee::export($event_id ?: 0, $args);
    }

    /**
     * Export tickets to array for CSV
     */
    private function export_tickets($event_id = null) {
        global $wpdb;

        if (!class_exists('SC_Ticket')) {
            return array();
        }

        $table = SC_Ticket::get_table();
        $where = $event_id ? $wpdb->prepare("WHERE event_id = %d", $event_id) : "";

        $tickets = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");

        $export = array();
        foreach ($tickets as $ticket) {
            $export[] = array(
                'ID' => $ticket->id,
                'Event' => get_the_title($ticket->event_id),
                'Name' => $ticket->name,
                'Price' => $ticket->price,
                'Quantity' => $ticket->quantity,
                'Sold' => $ticket->sold,
                'Status' => $ticket->status,
                'Created' => $ticket->created_at,
            );
        }

        return $export;
    }

    /**
     * Export revenue to array for CSV
     */
    private function export_revenue($event_id = null) {
        global $wpdb;

        if (!class_exists('SC_Transaction')) {
            return array();
        }

        $table = SC_Transaction::get_table();
        $where = "WHERE status = 'success'";
        if ($event_id) {
            $where .= $wpdb->prepare(" AND event_id = %d", $event_id);
        }

        $transactions = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");

        $export = array();
        foreach ($transactions as $tx) {
            $export[] = array(
                'Transaction ID' => $tx->transaction_id,
                'Event' => get_the_title($tx->event_id),
                'Amount' => $tx->total,
                'Payment Method' => $tx->payment_method,
                'Status' => $tx->status,
                'Date' => $tx->created_at,
            );
        }

        return $export;
    }
}

// Register the module
sc_modules()->register_module(new SC_Reports_Module());
