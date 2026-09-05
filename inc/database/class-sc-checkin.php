<?php
/**
 * SC Checkin Model Class
 *
 * Data Access Layer for Check-in logs table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Checkin {

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
            self::$table = $wpdb->prefix . 'sc_checkins';
        }
        return self::$table;
    }

    /**
     * Log a check-in action
     *
     * @param int $attendee_id Attendee ID
     * @param int $checked_by User ID who performed the action
     * @param string $action Action type (check_in, undo_check_in)
     * @param string $method Method used (qr_scan, manual, app)
     * @param string $notes Additional notes
     * @return int|false Log ID or false
     */
    public static function log($attendee_id, $checked_by, $action = 'check_in', $method = 'manual', $notes = '', $workshop_id = null) {
        global $wpdb;
        $table = self::get_table();

        // Get attendee's event
        $attendee = SC_Attendee::get($attendee_id);
        if (!$attendee) {
            return false;
        }

        // Auto-derive workshop_id from attendee if not explicitly provided
        if ($workshop_id === null && !empty($attendee->workshop_id)) {
            $workshop_id = (int) $attendee->workshop_id;
        }

        $data = array(
            'attendee_id' => $attendee_id,
            'event_id'    => $attendee->event_id,
            'workshop_id' => $workshop_id ? (int) $workshop_id : null,
            'checked_by'  => $checked_by,
            'action'      => $action,
            'method'      => $method,
            'ip_address'  => self::get_client_ip(),
            'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
            'notes'       => sanitize_text_field($notes),
            'created_at'  => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get check-in history for an attendee
     *
     * @param int $attendee_id Attendee ID
     * @return array
     */
    public static function get_by_attendee($attendee_id) {
        global $wpdb;
        $table = self::get_table();

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE attendee_id = %d ORDER BY created_at DESC",
            $attendee_id
        ));

        foreach ($logs as &$log) {
            $log = self::hydrate($log);
        }

        return $logs;
    }

    /**
     * Get check-in logs for an event
     *
     * @param int $event_id Event ID
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'action'    => null,
            'method'    => null,
            'date_from' => null,
            'date_to'   => null,
            'orderby'   => 'created_at',
            'order'     => 'DESC',
            'limit'     => 100,
            'offset'    => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('event_id = %d');
        $values = array($event_id);

        if ($args['action']) {
            $where[] = 'action = %s';
            $values[] = $args['action'];
        }

        if ($args['method']) {
            $where[] = 'method = %s';
            $values[] = $args['method'];
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'created_at', 'action', 'method');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $logs = $wpdb->get_results($sql);

        foreach ($logs as &$log) {
            $log = self::hydrate($log);
        }

        return $logs;
    }

    /**
     * Get check-in statistics for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_event_stats($event_id) {
        global $wpdb;
        $table = self::get_table();

        // Total check-ins
        $total_checkins = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND action = 'check_in'",
            $event_id
        ));

        // Check-ins by method
        $by_method = $wpdb->get_results($wpdb->prepare(
            "SELECT method, COUNT(*) as count FROM $table
            WHERE event_id = %d AND action = 'check_in'
            GROUP BY method",
            $event_id
        ), OBJECT_K);

        // Check-ins by hour
        $by_hour = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count FROM $table
            WHERE event_id = %d AND action = 'check_in'
            GROUP BY HOUR(created_at)
            ORDER BY hour",
            $event_id
        ));

        // Unique staff who checked in
        $staff_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT checked_by) FROM $table WHERE event_id = %d AND action = 'check_in'",
            $event_id
        ));

        return array(
            'total_checkins' => (int) $total_checkins,
            'by_method'      => $by_method,
            'by_hour'        => $by_hour,
            'staff_count'    => (int) $staff_count,
        );
    }

    /**
     * Get recent check-ins for an event (for live feed)
     *
     * @param int $event_id Event ID
     * @param int $limit Number of records
     * @param int $since_id Get records after this ID
     * @return array
     */
    public static function get_recent($event_id, $limit = 10, $since_id = 0) {
        global $wpdb;
        $table = self::get_table();
        $attendees_table = SC_Attendee::get_table();

        $sql = $wpdb->prepare(
            "SELECT c.*, a.first_name, a.last_name, a.email, a.ticket_code
            FROM $table c
            LEFT JOIN $attendees_table a ON c.attendee_id = a.id
            WHERE c.event_id = %d AND c.action = 'check_in' AND c.id > %d
            ORDER BY c.created_at DESC
            LIMIT %d",
            $event_id,
            $since_id,
            $limit
        );

        $logs = $wpdb->get_results($sql);

        foreach ($logs as &$log) {
            $log = self::hydrate($log);
            $log->attendee_name = trim($log->first_name . ' ' . $log->last_name);
        }

        return $logs;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Hydrate log object
     *
     * @param object $log Raw log
     * @return object Hydrated log
     */
    private static function hydrate($log) {
        // Action labels
        $action_labels = array(
            'check_in'      => __('Check In', 'sc_events'),
            'undo_check_in' => __('Undo Check In', 'sc_events'),
        );
        $log->action_label = isset($action_labels[$log->action]) ? $action_labels[$log->action] : $log->action;

        // Method labels
        $method_labels = array(
            'qr_scan' => __('QR Scan', 'sc_events'),
            'manual'  => __('Manual', 'sc_events'),
            'app'     => __('Mobile App', 'sc_events'),
            'import'  => __('Import', 'sc_events'),
        );
        $log->method_label = isset($method_labels[$log->method]) ? $method_labels[$log->method] : $log->method;

        // Get staff name
        if ($log->checked_by) {
            $user = get_userdata($log->checked_by);
            $log->checked_by_name = $user ? $user->display_name : __('Unknown', 'sc_events');
        } else {
            $log->checked_by_name = __('System', 'sc_events');
        }

        // Format date
        $log->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at));
        $log->time_ago = human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ' . __('ago', 'sc_events');

        return $log;
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get a single check-in log by ID
     *
     * @param int $id Check-in log ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($log) {
            $log = self::hydrate($log);
        }

        return $log;
    }

    /**
     * Get the event for this check-in
     *
     * @param int $checkin_id Check-in ID
     * @return object|null Event object or null
     */
    public static function get_event($checkin_id) {
        $checkin = self::get($checkin_id);
        if (!$checkin || !$checkin->event_id) {
            return null;
        }
        return SC_Event::get($checkin->event_id);
    }

    /**
     * Get the attendee for this check-in
     *
     * @param int $checkin_id Check-in ID
     * @return object|null Attendee object or null
     */
    public static function get_attendee($checkin_id) {
        $checkin = self::get($checkin_id);
        if (!$checkin || !$checkin->attendee_id) {
            return null;
        }
        return SC_Attendee::get($checkin->attendee_id);
    }

    /**
     * Get the user who performed this check-in
     *
     * @param int $checkin_id Check-in ID
     * @return WP_User|null User object or null
     */
    public static function get_checked_by_user($checkin_id) {
        $checkin = self::get($checkin_id);
        if (!$checkin || !$checkin->checked_by) {
            return null;
        }
        return get_user_by('id', $checkin->checked_by);
    }
}
