<?php
/**
 * SC Company Attendee Model Class
 *
 * Data Access Layer for Company Attendees table (B2B)
 *
 * @package sc_events
 * @version 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Company_Attendee {

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
            self::$table = $wpdb->prefix . 'sc_company_attendees';
        }
        return self::$table;
    }

    /**
     * Get single company attendee by ID
     *
     * @param int $id Company Attendee ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($company) {
            $company = self::hydrate($company);
        }

        return $company;
    }

    /**
     * Get company attendee by company code
     *
     * @param string $company_code Unique company code
     * @return object|null
     */
    public static function get_by_code($company_code) {
        global $wpdb;
        $table = self::get_table();

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE company_code = %s",
            $company_code
        ));

        if ($company) {
            $company = self::hydrate($company);
        }

        return $company;
    }

    /**
     * Get company attendee by email and event
     *
     * @param string $email Contact email
     * @param int $event_id Event ID
     * @return object|null
     */
    public static function get_by_email_and_event($email, $event_id) {
        global $wpdb;
        $table = self::get_table();

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE contact_email = %s AND event_id = %d LIMIT 1",
            $email,
            $event_id
        ));

        if ($company) {
            $company = self::hydrate($company);
        }

        return $company;
    }

    /**
     * Get company attendees by event
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'         => null,
            'payment_status' => null,
            'checked_in'     => null,
            'search'         => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('event_id = %d');
        $values = array($event_id);

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['payment_status']) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(company_name LIKE %s OR company_name_ar LIKE %s OR contact_name LIKE %s OR contact_email LIKE %s OR company_code LIKE %s OR booth_number LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'company_name', 'contact_name', 'created_at', 'checked_in_at', 'booth_number');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $companies = $wpdb->get_results($sql);

        foreach ($companies as &$company) {
            $company = self::hydrate($company);
        }

        return $companies;
    }

    /**
     * Get company attendees list with pagination and total count
     *
     * @param array $args Filter arguments
     * @return array ['companies' => array, 'total' => int]
     */
    public static function get_list($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'           => null,
            'status'             => null,
            'payment_status'     => null,
            'checked_in'         => null,
            'sponsorship_level'  => null,
            'industry'           => null,
            'search'             => null,
            'orderby'            => 'created_at',
            'order'              => 'DESC',
            'limit'              => 50,
            'offset'             => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['payment_status']) {
            if (is_array($args['payment_status'])) {
                $placeholders = implode(',', array_fill(0, count($args['payment_status']), '%s'));
                $where[] = "payment_status IN ($placeholders)";
                $values = array_merge($values, $args['payment_status']);
            } else {
                $where[] = 'payment_status = %s';
                $values[] = $args['payment_status'];
            }
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        if ($args['sponsorship_level']) {
            $where[] = 'sponsorship_level = %s';
            $values[] = $args['sponsorship_level'];
        }

        if ($args['industry']) {
            $where[] = 'industry = %s';
            $values[] = $args['industry'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(company_name LIKE %s OR company_name_ar LIKE %s OR contact_name LIKE %s OR contact_email LIKE %s OR company_code LIKE %s OR booth_number LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        // Count total
        if (!empty($values)) {
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE $where_clause",
                $values
            );
        } else {
            $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Sanitize orderby
        $allowed_orderby = array('id', 'company_name', 'contact_name', 'created_at', 'checked_in_at', 'booth_number', 'sponsorship_level');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get companies
        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
                array_merge($values, array($args['limit'], $args['offset']))
            );
        } else {
            $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT {$args['limit']} OFFSET {$args['offset']}";
        }

        $companies = $wpdb->get_results($sql);

        foreach ($companies as &$company) {
            $company = self::hydrate($company);
        }

        return array(
            'companies' => $companies,
            'total' => $total
        );
    }

    /**
     * Count company attendees
     *
     * @param array $args Filter arguments
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'       => null,
            'status'         => null,
            'payment_status' => null,
            'checked_in'     => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['payment_status']) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_clause", $values);
        } else {
            $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create new company attendee
     *
     * @param array $data Company attendee data
     * @return int|false Company Attendee ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate unique company code
        if (empty($data['company_code'])) {
            $data['company_code'] = self::generate_company_code();
        }

        // Generate QR data
        if (empty($data['qr_data'])) {
            $data['qr_data'] = json_encode(array(
                'type' => 'company',
                'code' => $data['company_code'],
                'company' => $data['company_name'] ?? '',
                'event' => $data['event_id'] ?? 0,
            ));
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $company_id = $wpdb->insert_id;

            do_action('sc_company_attendee_created', $company_id, $data);

            return $company_id;
        }

        return false;
    }

    /**
     * Update company attendee
     *
     * @param int $id Company Attendee ID
     * @param array $data Company attendee data
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result !== false) {
            do_action('sc_company_attendee_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete company attendee
     *
     * @param int $id Company Attendee ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        $company = self::get($id);
        if (!$company) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_company_attendee_deleted', $id, $company);
            return true;
        }

        return false;
    }

    /**
     * Check in company attendee
     *
     * @param int $id Company Attendee ID
     * @param int|null $checked_in_by User ID who checked in
     * @return bool Success
     */
    public static function check_in($id, $checked_in_by = null) {
        global $wpdb;
        $table = self::get_table();

        $company = self::get($id);
        if (!$company) {
            return false;
        }

        if ($company->checked_in) {
            return true; // Already checked in
        }

        $result = $wpdb->update(
            $table,
            array(
                'checked_in'    => 1,
                'checked_in_at' => current_time('mysql'),
                'checked_in_by' => $checked_in_by ?: get_current_user_id(),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log check-in
            self::log_checkin($id, $company->event_id, 'check_in', $checked_in_by);

            do_action('sc_company_attendee_checked_in', $id);
            return true;
        }

        return false;
    }

    /**
     * Check out company attendee
     *
     * @param int $id Company Attendee ID
     * @param int|null $checked_out_by User ID
     * @return bool Success
     */
    public static function check_out($id, $checked_out_by = null) {
        global $wpdb;
        $table = self::get_table();

        $company = self::get($id);
        if (!$company) {
            return false;
        }

        if (!$company->checked_in) {
            return true; // Not checked in
        }

        $result = $wpdb->update(
            $table,
            array(
                'checked_in' => 0,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log check-out
            self::log_checkin($id, $company->event_id, 'check_out', $checked_out_by);

            do_action('sc_company_attendee_checked_out', $id);
            return true;
        }

        return false;
    }

    /**
     * Log check-in/check-out
     *
     * @param int $company_id Company attendee ID
     * @param int $event_id Event ID
     * @param string $action check_in or check_out
     * @param int|null $scanned_by User ID
     */
    private static function log_checkin($company_id, $event_id, $action, $scanned_by = null) {
        global $wpdb;
        $tables = sc_get_table_names();

        $wpdb->insert(
            $tables['company_checkins'],
            array(
                'company_attendee_id' => $company_id,
                'event_id'            => $event_id,
                'action'              => $action,
                'method'              => 'manual',
                'scanned_by'          => $scanned_by ?: get_current_user_id(),
                'ip_address'          => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
            )
        );
    }

    /**
     * Get check-in history for company
     *
     * @param int $company_id Company attendee ID
     * @return array
     */
    public static function get_checkin_history($company_id) {
        global $wpdb;
        $tables = sc_get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['company_checkins']} WHERE company_attendee_id = %d ORDER BY created_at DESC",
            $company_id
        ));
    }

    /**
     * Generate unique company code
     *
     * @return string Format: COMP-XXXX-XXXX
     */
    public static function generate_company_code() {
        global $wpdb;
        $table = self::get_table();

        do {
            $code = 'COMP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE company_code = %s",
                $code
            ));
        } while ($exists > 0);

        return $code;
    }

    /**
     * Prepare data for database
     *
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private static function prepare_data($data) {
        $prepared = array();

        $text_fields = array(
            'company_name', 'company_name_ar', 'industry', 'website',
            'contact_name', 'contact_title', 'contact_email', 'contact_phone',
            'country', 'city', 'address',
            'booth_number', 'sponsorship_level', 'company_code',
            'payment_method', 'notes'
        );

        $int_fields = array(
            'event_id', 'ticket_id', 'company_logo', 'payment_id', 'checked_in_by'
        );

        $float_fields = array('amount_paid');

        $bool_fields = array('checked_in', 'email_sent');

        $json_fields = array('extra_fields', 'qr_data', 'social_media', 'products');

        $enum_fields = array(
            'company_size' => array('1-10', '11-50', '51-200', '201-500', '500+'),
            'payment_status' => array('pending', 'success', 'failed', 'refunded'),
            'status' => array('active', 'cancelled'),
        );

        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        foreach ($bool_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = $data[$field] ? 1 : 0;
            }
        }

        foreach ($json_fields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field])) {
                    $prepared[$field] = json_encode($data[$field]);
                } else {
                    $prepared[$field] = $data[$field];
                }
            }
        }

        foreach ($enum_fields as $field => $allowed) {
            if (isset($data[$field]) && in_array($data[$field], $allowed)) {
                $prepared[$field] = $data[$field];
            }
        }

        // Special handling for dates
        if (isset($data['checked_in_at'])) {
            $prepared['checked_in_at'] = sanitize_text_field($data['checked_in_at']);
        }
        if (isset($data['email_sent_at'])) {
            $prepared['email_sent_at'] = sanitize_text_field($data['email_sent_at']);
        }

        return $prepared;
    }

    /**
     * Hydrate model with computed properties
     *
     * @param object $company Raw database object
     * @return object Hydrated object
     */
    private static function hydrate($company) {
        // Parse JSON fields
        if (!empty($company->extra_fields)) {
            $company->extra_fields_array = json_decode($company->extra_fields, true);
        } else {
            $company->extra_fields_array = array();
        }

        if (!empty($company->qr_data)) {
            $company->qr_data_array = json_decode($company->qr_data, true);
        } else {
            $company->qr_data_array = array();
        }

        // Parse social media JSON
        if (!empty($company->social_media)) {
            $company->social_media_array = json_decode($company->social_media, true);
        } else {
            $company->social_media_array = array();
        }

        // Parse products JSON
        if (!empty($company->products)) {
            $company->products_array = json_decode($company->products, true);
        } else {
            $company->products_array = array();
        }

        // Get logo URL
        if ($company->company_logo) {
            $company->logo_url = wp_get_attachment_image_url($company->company_logo, 'medium');
        } else {
            $company->logo_url = null;
        }

        // Format dates
        if ($company->created_at) {
            $company->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($company->created_at));
        }

        if ($company->checked_in_at) {
            $company->checked_in_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($company->checked_in_at));
        }

        // Status label
        $status_labels = array(
            'active' => __('Active', 'sc_events'),
            'cancelled' => __('Cancelled', 'sc_events'),
        );
        $company->status_label = isset($status_labels[$company->status]) ? $status_labels[$company->status] : $company->status;

        // Payment status label
        $payment_labels = array(
            'pending' => __('Pending', 'sc_events'),
            'success' => __('Paid', 'sc_events'),
            'failed' => __('Failed', 'sc_events'),
            'refunded' => __('Refunded', 'sc_events'),
        );
        $company->payment_status_label = isset($payment_labels[$company->payment_status]) ? $payment_labels[$company->payment_status] : $company->payment_status;

        return $company;
    }

    /**
     * Get statistics for event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_stats($event_id) {
        global $wpdb;
        $table = self::get_table();

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active'",
            $event_id
        ));

        $checked_in = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active' AND checked_in = 1",
            $event_id
        ));

        $paid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = 'active' AND payment_status = 'success'",
            $event_id
        ));

        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount_paid) FROM $table WHERE event_id = %d AND status = 'active' AND payment_status = 'success'",
            $event_id
        ));

        return array(
            'total' => intval($total),
            'checked_in' => intval($checked_in),
            'not_checked_in' => intval($total) - intval($checked_in),
            'paid' => intval($paid),
            'revenue' => floatval($revenue ?: 0),
        );
    }

    /**
     * Export company attendees to array for CSV
     *
     * @param int $event_id Event ID
     * @param array $args Filter arguments
     * @return array
     */
    public static function export($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        $args['limit'] = 10000; // High limit for export
        $args['offset'] = 0;

        $result = self::get_list($args);
        $export = array();

        foreach ($result['companies'] as $company) {
            $export[] = array(
                'Company Code' => $company->company_code,
                'Company Name' => $company->company_name,
                'Company Name (AR)' => $company->company_name_ar,
                'Industry' => $company->industry,
                'Company Size' => $company->company_size,
                'Website' => $company->website,
                'Contact Name' => $company->contact_name,
                'Contact Title' => $company->contact_title,
                'Contact Email' => $company->contact_email,
                'Contact Phone' => $company->contact_phone,
                'Country' => $company->country,
                'City' => $company->city,
                'Booth Number' => $company->booth_number,
                'Sponsorship Level' => $company->sponsorship_level,
                'Payment Status' => $company->payment_status_label,
                'Amount Paid' => $company->amount_paid,
                'Checked In' => $company->checked_in ? 'Yes' : 'No',
                'Checked In At' => $company->checked_in_at ?: '',
                'Status' => $company->status_label,
                'Registered At' => $company->created_at,
            );
        }

        return $export;
    }
}
