<?php
/**
 * SC Booth Visit Model Class
 *
 * Data Access Layer for Booth Visits table (Visitor statistics)
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booth_Visit {

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
            self::$table = $wpdb->prefix . 'sc_booth_visits';
        }
        return self::$table;
    }

    /**
     * Get single visit by ID
     *
     * @param int $id Visit ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    /**
     * Get visits by booth
     *
     * @param int $booth_id Booth ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_booth($booth_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'date'       => null,
            'date_from'  => null,
            'date_to'    => null,
            'limit'      => 100,
            'offset'     => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('booth_id = %d');
        $values = array($booth_id);

        if ($args['date']) {
            $where[] = 'visit_date = %s';
            $values[] = $args['date'];
        }

        if ($args['date_from']) {
            $where[] = 'visit_date >= %s';
            $values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'visit_date <= %s';
            $values[] = $args['date_to'];
        }

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY check_in_time DESC LIMIT %d OFFSET %d";
        $query = $wpdb->prepare($query, $values);

        return $wpdb->get_results($query);
    }

    /**
     * Record booth visit (check in)
     *
     * @param array $data Visit data
     * @return int|false New visit ID or false
     */
    public static function check_in($data) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'         => 0,
            'booth_id'         => 0,
            'booking_id'       => null,
            'attendee_id'      => null,
            'visitor_type'     => 'attendee',
            'visit_date'       => current_time('Y-m-d'),
            'check_in_time'    => current_time('mysql'),
            'interaction_type' => 'browse',
            'interest_level'   => 'medium',
            'scan_method'      => 'qr',
            'scanned_by'       => get_current_user_id() ?: null,
        );

        $data = wp_parse_args($data, $defaults);

        // Handle lead data
        if (isset($data['lead_data']) && is_array($data['lead_data'])) {
            $data['lead_data'] = wp_json_encode($data['lead_data']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Record check out
     *
     * @param int $id Visit ID
     * @return bool
     */
    public static function check_out($id) {
        global $wpdb;
        $table = self::get_table();

        $visit = self::get($id);
        if (!$visit) {
            return false;
        }

        // Calculate duration
        $check_in = strtotime($visit->check_in_time);
        $check_out = current_time('timestamp');
        $duration_minutes = round(($check_out - $check_in) / 60);

        return $wpdb->update(
            $table,
            array(
                'check_out_time'    => current_time('mysql'),
                'duration_minutes'  => max(1, $duration_minutes),
            ),
            array('id' => $id)
        ) !== false;
    }

    /**
     * Update visit interaction
     *
     * @param int $id Visit ID
     * @param string $interaction_type Interaction type
     * @param string $interest_level Interest level
     * @param string|null $notes Notes
     * @return bool
     */
    public static function update_interaction($id, $interaction_type, $interest_level, $notes = null) {
        global $wpdb;
        $table = self::get_table();

        $data = array(
            'interaction_type' => $interaction_type,
            'interest_level'   => $interest_level,
        );

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $wpdb->update($table, $data, array('id' => $id)) !== false;
    }

    /**
     * Capture lead from visit
     *
     * @param int $id Visit ID
     * @param array $lead_data Lead information
     * @return bool
     */
    public static function capture_lead($id, $lead_data) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->update(
            $table,
            array(
                'lead_captured' => 1,
                'lead_data'     => wp_json_encode($lead_data),
                'interest_level' => 'hot_lead',
            ),
            array('id' => $id)
        ) !== false;
    }

    /**
     * Get statistics for a booth
     *
     * @param int $booth_id Booth ID
     * @param string|null $date Optional specific date
     * @return array
     */
    public static function get_booth_stats($booth_id, $date = null) {
        global $wpdb;
        $table = self::get_table();

        $where = 'booth_id = %d';
        $values = array($booth_id);

        if ($date) {
            $where .= ' AND visit_date = %s';
            $values[] = $date;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_visits,
                COUNT(DISTINCT attendee_id) as unique_visitors,
                AVG(duration_minutes) as avg_duration,
                SUM(CASE WHEN interaction_type = 'purchase' THEN 1 ELSE 0 END) as purchases,
                SUM(CASE WHEN interaction_type = 'inquiry' THEN 1 ELSE 0 END) as inquiries,
                SUM(CASE WHEN interaction_type = 'demo' THEN 1 ELSE 0 END) as demos,
                SUM(CASE WHEN interest_level = 'hot_lead' THEN 1 ELSE 0 END) as hot_leads,
                SUM(lead_captured) as leads_captured
             FROM $table
             WHERE $where",
            $values
        ));

        return array(
            'total_visits'     => intval($stats->total_visits ?? 0),
            'unique_visitors'  => intval($stats->unique_visitors ?? 0),
            'avg_duration'     => round(floatval($stats->avg_duration ?? 0), 1),
            'purchases'        => intval($stats->purchases ?? 0),
            'inquiries'        => intval($stats->inquiries ?? 0),
            'demos'            => intval($stats->demos ?? 0),
            'hot_leads'        => intval($stats->hot_leads ?? 0),
            'leads_captured'   => intval($stats->leads_captured ?? 0),
        );
    }

    /**
     * Get hourly traffic for a booth
     *
     * @param int $booth_id Booth ID
     * @param string $date Date (Y-m-d)
     * @return array
     */
    public static function get_hourly_traffic($booth_id, $date) {
        global $wpdb;
        $table = self::get_table();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                HOUR(check_in_time) as hour,
                COUNT(*) as visits
             FROM $table
             WHERE booth_id = %d AND visit_date = %s
             GROUP BY HOUR(check_in_time)
             ORDER BY hour",
            $booth_id,
            $date
        ));

        $hourly = array_fill(0, 24, 0);
        foreach ($results as $row) {
            $hourly[intval($row->hour)] = intval($row->visits);
        }

        return $hourly;
    }

    /**
     * Get daily traffic for a booth
     *
     * @param int $booth_id Booth ID
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array
     */
    public static function get_daily_traffic($booth_id, $date_from, $date_to) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                visit_date,
                COUNT(*) as visits,
                COUNT(DISTINCT attendee_id) as unique_visitors,
                AVG(duration_minutes) as avg_duration
             FROM $table
             WHERE booth_id = %d AND visit_date BETWEEN %s AND %s
             GROUP BY visit_date
             ORDER BY visit_date",
            $booth_id,
            $date_from,
            $date_to
        ));
    }

    /**
     * Get top booths by visits for an event
     *
     * @param int $event_id Event ID
     * @param int $limit Number of results
     * @param string|null $date Optional specific date
     * @return array
     */
    public static function get_top_booths($event_id, $limit = 10, $date = null) {
        global $wpdb;
        $table = self::get_table();
        $booths_table = $wpdb->prefix . 'sc_booths';
        $companies_table = $wpdb->prefix . 'sc_company_attendees';

        $where = 'v.event_id = %d';
        $values = array($event_id);

        if ($date) {
            $where .= ' AND v.visit_date = %s';
            $values[] = $date;
        }

        $values[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                v.booth_id,
                b.booth_number,
                c.company_name,
                COUNT(*) as total_visits,
                COUNT(DISTINCT v.attendee_id) as unique_visitors,
                AVG(v.duration_minutes) as avg_duration,
                SUM(CASE WHEN v.interest_level = 'hot_lead' THEN 1 ELSE 0 END) as hot_leads
             FROM $table v
             LEFT JOIN $booths_table b ON v.booth_id = b.id
             LEFT JOIN $companies_table c ON b.current_company_id = c.id
             WHERE $where
             GROUP BY v.booth_id
             ORDER BY total_visits DESC
             LIMIT %d",
            $values
        ));
    }

    /**
     * Get visitor types breakdown
     *
     * @param int $booth_id Booth ID
     * @param string|null $date Optional specific date
     * @return array
     */
    public static function get_visitor_types($booth_id, $date = null) {
        global $wpdb;
        $table = self::get_table();

        $where = 'booth_id = %d';
        $values = array($booth_id);

        if ($date) {
            $where .= ' AND visit_date = %s';
            $values[] = $date;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                visitor_type,
                COUNT(*) as count
             FROM $table
             WHERE $where
             GROUP BY visitor_type
             ORDER BY count DESC",
            $values
        ));
    }

    /**
     * Get interaction types breakdown
     *
     * @param int $booth_id Booth ID
     * @param string|null $date Optional specific date
     * @return array
     */
    public static function get_interaction_types($booth_id, $date = null) {
        global $wpdb;
        $table = self::get_table();

        $where = 'booth_id = %d';
        $values = array($booth_id);

        if ($date) {
            $where .= ' AND visit_date = %s';
            $values[] = $date;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                interaction_type,
                COUNT(*) as count
             FROM $table
             WHERE $where
             GROUP BY interaction_type
             ORDER BY count DESC",
            $values
        ));
    }

    /**
     * Get current visitors in booth (checked in but not out)
     *
     * @param int $booth_id Booth ID
     * @return array
     */
    public static function get_current_visitors($booth_id) {
        global $wpdb;
        $table = self::get_table();
        $attendees_table = $wpdb->prefix . 'sc_attendees';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, a.name as attendee_name, a.email as attendee_email
             FROM $table v
             LEFT JOIN $attendees_table a ON v.attendee_id = a.id
             WHERE v.booth_id = %d
               AND v.check_out_time IS NULL
               AND v.visit_date = CURDATE()
             ORDER BY v.check_in_time DESC",
            $booth_id
        ));
    }

    /**
     * Get event-wide statistics
     *
     * @param int $event_id Event ID
     * @param string|null $date Optional specific date
     * @return array
     */
    public static function get_event_stats($event_id, $date = null) {
        global $wpdb;
        $table = self::get_table();

        $where = 'event_id = %d';
        $values = array($event_id);

        if ($date) {
            $where .= ' AND visit_date = %s';
            $values[] = $date;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_visits,
                COUNT(DISTINCT booth_id) as booths_visited,
                COUNT(DISTINCT attendee_id) as unique_visitors,
                AVG(duration_minutes) as avg_duration,
                SUM(lead_captured) as total_leads
             FROM $table
             WHERE $where",
            $values
        ));

        return array(
            'total_visits'    => intval($stats->total_visits ?? 0),
            'booths_visited'  => intval($stats->booths_visited ?? 0),
            'unique_visitors' => intval($stats->unique_visitors ?? 0),
            'avg_duration'    => round(floatval($stats->avg_duration ?? 0), 1),
            'total_leads'     => intval($stats->total_leads ?? 0),
        );
    }

    /**
     * Get visitor types
     *
     * @return array
     */
    public static function get_visitor_type_options() {
        return array(
            'attendee'  => array(
                'label'    => __('Attendee', 'sc_events'),
                'label_ar' => 'حاضر',
            ),
            'anonymous' => array(
                'label'    => __('Anonymous', 'sc_events'),
                'label_ar' => 'مجهول',
            ),
            'vip'       => array(
                'label'    => __('VIP', 'sc_events'),
                'label_ar' => 'شخصية مهمة',
            ),
            'buyer'     => array(
                'label'    => __('Buyer', 'sc_events'),
                'label_ar' => 'مشتري',
            ),
            'press'     => array(
                'label'    => __('Press', 'sc_events'),
                'label_ar' => 'صحافة',
            ),
        );
    }

    /**
     * Get interaction types
     *
     * @return array
     */
    public static function get_interaction_type_options() {
        return array(
            'browse'   => array(
                'label'    => __('Browse', 'sc_events'),
                'label_ar' => 'تصفح',
                'icon'     => 'fa-eye',
            ),
            'inquiry'  => array(
                'label'    => __('Inquiry', 'sc_events'),
                'label_ar' => 'استفسار',
                'icon'     => 'fa-question-circle',
            ),
            'meeting'  => array(
                'label'    => __('Meeting', 'sc_events'),
                'label_ar' => 'اجتماع',
                'icon'     => 'fa-handshake',
            ),
            'demo'     => array(
                'label'    => __('Demo', 'sc_events'),
                'label_ar' => 'عرض توضيحي',
                'icon'     => 'fa-desktop',
            ),
            'purchase' => array(
                'label'    => __('Purchase', 'sc_events'),
                'label_ar' => 'شراء',
                'icon'     => 'fa-shopping-cart',
            ),
        );
    }

    /**
     * Get interest levels
     *
     * @return array
     */
    public static function get_interest_level_options() {
        return array(
            'low'      => array(
                'label'    => __('Low', 'sc_events'),
                'label_ar' => 'منخفض',
                'color'    => '#9ca3af',
            ),
            'medium'   => array(
                'label'    => __('Medium', 'sc_events'),
                'label_ar' => 'متوسط',
                'color'    => '#f59e0b',
            ),
            'high'     => array(
                'label'    => __('High', 'sc_events'),
                'label_ar' => 'عالي',
                'color'    => '#22c55e',
            ),
            'hot_lead' => array(
                'label'    => __('Hot Lead', 'sc_events'),
                'label_ar' => 'عميل محتمل مهم',
                'color'    => '#ef4444',
            ),
        );
    }
}
