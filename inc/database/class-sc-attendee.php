<?php
/**
 * SC Attendee Model Class
 *
 * Data Access Layer for Attendees table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Attendee {

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
            self::$table = $wpdb->prefix . 'sc_attendees';
        }
        return self::$table;
    }

    /**
     * Get single attendee by ID
     *
     * @param int $id Attendee ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendee;
    }

    /**
     * Get attendee by ticket code
     *
     * @param string $ticket_code Unique ticket code
     * @return object|null
     */
    public static function get_by_ticket_code($ticket_code) {
        global $wpdb;
        $table = self::get_table();

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_code = %s",
            $ticket_code
        ));

        if ($attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendee;
    }

    /**
     * Get attendee by email and event
     *
     * @param string $email Email address
     * @param int $event_id Event ID
     * @return object|null
     */
    public static function get_by_email_and_event($email, $event_id) {
        global $wpdb;
        $table = self::get_table();

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND event_id = %d AND status = 'active' LIMIT 1",
            $email,
            $event_id
        ));

        if ($attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendee;
    }

    /**
     * Get attendees by event
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
            'ticket_id'      => null,
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

        if ($args['ticket_id']) {
            $where[] = 'ticket_id = %d';
            $values[] = $args['ticket_id'];
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR ticket_code LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = array('id', 'name', 'email', 'created_at', 'checked_in_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $attendees = $wpdb->get_results($sql);

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * Get attendees by transaction
     *
     * @param int $transaction_id Transaction ID
     * @return array
     */
    public static function get_by_transaction($transaction_id) {
        global $wpdb;
        $table = self::get_table();

        $attendees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE transaction_id = %d ORDER BY id ASC",
            $transaction_id
        ));

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * Get attendees by WooCommerce order ID
     *
     * @param int $order_id WooCommerce Order ID
     * @return array
     */
    public static function get_by_order($order_id) {
        global $wpdb;
        $table = self::get_table();

        $attendees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d ORDER BY id ASC",
            $order_id
        ));

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * Get attendees by user
     *
     * @param int $user_id User ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_user($user_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'         => 'active',
            'payment_status' => 'success',
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('user_id = %d');
        $values = array($user_id);

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['payment_status']) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'created_at', 'event_id');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $attendees = $wpdb->get_results($sql);

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * Get all attendees with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'       => null,
            'workshop_id'    => null,
            'status'         => null,
            'payment_status' => null,
            'ticket_id'      => null,
            'checked_in'     => null,
            'search'         => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['workshop_id'] !== null) {
            if ((int) $args['workshop_id'] === 0) {
                // Filter for "no workshop" (event-only attendees)
                $where[] = '(workshop_id IS NULL OR workshop_id = 0)';
            } else {
                $where[] = 'workshop_id = %d';
                $values[] = (int) $args['workshop_id'];
            }
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['payment_status']) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        if ($args['ticket_id']) {
            $where[] = 'ticket_id = %d';
            $values[] = $args['ticket_id'];
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR ticket_code LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = array('id', 'name', 'email', 'created_at', 'checked_in_at', 'event_id');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
                array_merge($values, array($args['limit'], $args['offset']))
            );
        } else {
            $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT {$args['limit']} OFFSET {$args['offset']}";
        }

        $attendees = $wpdb->get_results($sql);

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * Get attendees list with pagination and total count
     * Comprehensive method for dashboard listings
     *
     * @param array $args Filter arguments
     * @return array ['attendees' => array, 'total' => int]
     */
    public static function get_list($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'       => null,
            'status'         => null,
            'payment_status' => null,
            'payment_method' => null,
            'ticket_id'      => null,
            'checked_in'     => null,
            'coupon_code'    => null,
            'search'         => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
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

        if ($args['payment_method']) {
            $where[] = 'payment_method = %s';
            $values[] = $args['payment_method'];
        }

        if ($args['ticket_id']) {
            $where[] = 'ticket_id = %d';
            $values[] = $args['ticket_id'];
        }

        if ($args['checked_in'] !== null) {
            $where[] = 'checked_in = %d';
            $values[] = $args['checked_in'] ? 1 : 0;
        }

        if ($args['coupon_code']) {
            $where[] = 'coupon_code LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['coupon_code']) . '%';
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR ticket_code LIKE %s)';
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
        $allowed_orderby = array('id', 'name', 'email', 'created_at', 'checked_in_at', 'event_id');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get attendees
        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
                array_merge($values, array($args['limit'], $args['offset']))
            );
        } else {
            $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT {$args['limit']} OFFSET {$args['offset']}";
        }

        $attendees = $wpdb->get_results($sql);

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return array(
            'attendees' => $attendees,
            'total' => $total
        );
    }

    /**
     * Count attendees
     *
     * @param array $args Filter arguments
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'       => null,
            'workshop_id'    => null,
            'status'         => null,
            'payment_status' => null,
            'checked_in'     => null,
            'ticket_id'      => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['workshop_id'] !== null) {
            if ((int) $args['workshop_id'] === 0) {
                $where[] = '(workshop_id IS NULL OR workshop_id = 0)';
            } else {
                $where[] = 'workshop_id = %d';
                $values[] = (int) $args['workshop_id'];
            }
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

        if ($args['ticket_id']) {
            $where[] = 'ticket_id = %d';
            $values[] = $args['ticket_id'];
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
     * Create new attendee
     *
     * @param array $data Attendee data
     * @return int|false Attendee ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate unique ticket code
        if (empty($data['ticket_code'])) {
            $data['ticket_code'] = self::generate_ticket_code();
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $attendee_id = $wpdb->insert_id;

            // Update ticket sold count
            if (!empty($data['ticket_id'])) {
                SC_Ticket::increment_sold($data['ticket_id']);
            }

            // Update event stats
            if (!empty($data['event_id'])) {
                SC_Event::update_stats($data['event_id']);
            }

            do_action('sc_attendee_created', $attendee_id, $data);

            return $attendee_id;
        }

        return false;
    }

    /**
     * Update attendee
     *
     * @param int $id Attendee ID
     * @param array $data Attendee data
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
            do_action('sc_attendee_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete attendee
     *
     * @param int $id Attendee ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        $attendee = self::get($id);
        if (!$attendee) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            // Update ticket sold count
            if ($attendee->ticket_id && $attendee->payment_status === 'success') {
                SC_Ticket::decrement_sold($attendee->ticket_id);
            }

            // Update event stats
            if ($attendee->event_id) {
                SC_Event::update_stats($attendee->event_id);
            }

            do_action('sc_attendee_deleted', $id, $attendee);
            return true;
        }

        return false;
    }

    /**
     * Delete all attendees for an event
     *
     * @param int $event_id Event ID
     * @return int Number deleted
     */
    public static function delete_by_event($event_id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->delete($table, array('event_id' => $event_id), array('%d'));
    }

    /**
     * Get attendees of a workshop
     */
    public static function get_by_workshop($workshop_id, $args = array()) {
        $args['workshop_id'] = (int) $workshop_id;
        return self::get_all($args);
    }

    /**
     * Delete all attendees for a workshop
     */
    public static function delete_by_workshop($workshop_id) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->delete($table, array('workshop_id' => (int) $workshop_id), array('%d'));
    }

    /**
     * Check in attendee
     *
     * @param int $id Attendee ID
     * @return bool Success
     */
    public static function check_in($id) {
        global $wpdb;
        $table = self::get_table();

        $attendee = self::get($id);
        if (!$attendee) {
            return false;
        }

        if ($attendee->checked_in) {
            return true; // Already checked in
        }

        $result = $wpdb->update(
            $table,
            array(
                'checked_in'    => 1,
                'checked_in_at' => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log check-in
            SC_Checkin::log($id, get_current_user_id(), 'check_in');

            // Update event stats
            SC_Event::update_stats($attendee->event_id);

            do_action('sc_attendee_checked_in', $id, $attendee);
            return true;
        }

        return false;
    }

    /**
     * Undo check in
     *
     * @param int $id Attendee ID
     * @return bool Success
     */
    public static function undo_check_in($id) {
        global $wpdb;
        $table = self::get_table();

        $attendee = self::get($id);
        if (!$attendee || !$attendee->checked_in) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array(
                'checked_in'    => 0,
                'checked_in_at' => null,
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', null, '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log undo
            SC_Checkin::log($id, get_current_user_id(), 'undo_check_in');

            // Update event stats
            SC_Event::update_stats($attendee->event_id);

            do_action('sc_attendee_check_in_undone', $id, $attendee);
            return true;
        }

        return false;
    }

    /**
     * Generate unique ticket code
     *
     * @return string
     */
    public static function generate_ticket_code() {
        global $wpdb;
        $table = self::get_table();

        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
            // Format: XXXX-XXXX-XXXX
            $code = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
        } while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE ticket_code = %s", $code)));

        return $code;
    }

    /**
     * Update payment status
     *
     * @param int $id Attendee ID
     * @param string $status Payment status
     * @return bool Success
     */
    public static function update_payment_status($id, $status) {
        $attendee = self::get($id);
        if (!$attendee) {
            return false;
        }

        $old_status = $attendee->payment_status;

        $result = self::update($id, array('payment_status' => $status));

        if ($result) {
            // Handle ticket count changes
            if ($old_status === 'success' && $status !== 'success') {
                SC_Ticket::decrement_sold($attendee->ticket_id);
            } elseif ($old_status !== 'success' && $status === 'success') {
                SC_Ticket::increment_sold($attendee->ticket_id);
            }

            // Update event stats
            SC_Event::update_stats($attendee->event_id);
        }

        return $result;
    }

    /**
     * Prepare data for insert/update
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private static function prepare_data($data) {
        $prepared = array();

        // Integer fields
        $int_fields = array(
            'event_id', 'workshop_id', 'ticket_id', 'transaction_id', 'user_id', 'checked_in'
        );

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // Float fields
        $float_fields = array('amount_paid');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // String fields
        $string_fields = array(
            'ticket_code', 'name', 'email', 'phone', 'ticket_name',
            'status', 'payment_status', 'payment_method', 'coupon_code', 'notes'
        );

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Email field
        if (isset($data['email'])) {
            $prepared['email'] = sanitize_email($data['email']);
        }

        // Datetime fields
        if (isset($data['checked_in_at'])) {
            $prepared['checked_in_at'] = $data['checked_in_at'] ? date('Y-m-d H:i:s', strtotime($data['checked_in_at'])) : null;
        }

        // JSON fields
        if (isset($data['extra_fields'])) {
            if (is_array($data['extra_fields'])) {
                $prepared['extra_fields'] = wp_json_encode($data['extra_fields']);
            } else {
                $prepared['extra_fields'] = $data['extra_fields'];
            }
        }

        return $prepared;
    }

    /**
     * Hydrate attendee object
     *
     * @param object $attendee Raw attendee
     * @return object Hydrated attendee
     */
    private static function hydrate($attendee) {
        // Decode JSON fields
        if (!empty($attendee->extra_fields)) {
            $decoded = json_decode($attendee->extra_fields, true);
            $attendee->extra_fields = is_array($decoded) ? $decoded : array();
        } else {
            $attendee->extra_fields = array();
        }

        // Status labels
        $status_labels = array(
            'active'    => __('Active', 'sc_events'),
            'cancelled' => __('Cancelled', 'sc_events'),
            'transferred' => __('Transferred', 'sc_events'),
        );
        $attendee->status_label = isset($status_labels[$attendee->status]) ? $status_labels[$attendee->status] : $attendee->status;

        // Payment status labels
        $payment_labels = array(
            'pending'   => __('Pending', 'sc_events'),
            'success'   => __('Paid', 'sc_events'),
            'failed'    => __('Failed', 'sc_events'),
            'refunded'  => __('Refunded', 'sc_events'),
            'cancelled' => __('Cancelled', 'sc_events'),
        );
        $attendee->payment_status_label = isset($payment_labels[$attendee->payment_status]) ? $payment_labels[$attendee->payment_status] : $attendee->payment_status;

        // Format amount using dynamic currency
        $attendee->amount_paid_formatted = sc_format_price($attendee->amount_paid, false);

        // Checked in info
        if ($attendee->checked_in && $attendee->checked_in_at) {
            $attendee->checked_in_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($attendee->checked_in_at));
        }

        return $attendee;
    }

    /**
     * Count attendees by event
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return int
     */
    public static function count_by_event($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        return self::count($args);
    }

    /**
     * Search attendees by email
     *
     * @param string $email Email to search
     * @param int $event_id Optional event ID filter
     * @return array
     */
    public static function search_by_email($email, $event_id = null) {
        global $wpdb;
        $table = self::get_table();

        $where = array('email = %s');
        $values = array($email);

        if ($event_id) {
            $where[] = 'event_id = %d';
            $values[] = $event_id;
        }

        $where_clause = implode(' AND ', $where);

        $attendees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC",
            $values
        ));

        foreach ($attendees as &$attendee) {
            $attendee = self::hydrate($attendee);
        }

        return $attendees;
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get the event for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @return object|null SC_Event object or null
     */
    public static function get_event($attendee_id) {
        $attendee = self::get($attendee_id);
        if (!$attendee || !$attendee->event_id) {
            return null;
        }
        return SC_Event::get($attendee->event_id);
    }

    /**
     * Get the ticket for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @return object|null SC_Ticket object or null
     */
    public static function get_ticket($attendee_id) {
        $attendee = self::get($attendee_id);
        if (!$attendee || !$attendee->ticket_id) {
            return null;
        }
        return SC_Ticket::get($attendee->ticket_id);
    }

    /**
     * Get the transaction for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @return object|null SC_Transaction object or null
     */
    public static function get_transaction($attendee_id) {
        $attendee = self::get($attendee_id);
        if (!$attendee || !$attendee->transaction_id) {
            return null;
        }
        return SC_Transaction::get($attendee->transaction_id);
    }

    /**
     * Get all checkins for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @return array Array of SC_Checkin objects
     */
    public static function get_checkins($attendee_id) {
        return SC_Checkin::get_by_attendee($attendee_id);
    }

    /**
     * Get the certificate for this attendee and event
     *
     * @param int $attendee_id Attendee ID
     * @param int $event_id Optional Event ID (uses attendee's event if not provided)
     * @return object|null SC_Certificate object or null
     */
    public static function get_certificate($attendee_id, $event_id = null) {
        $attendee = self::get($attendee_id);
        if (!$attendee) {
            return null;
        }
        $event_id = $event_id ?: $attendee->event_id;
        return SC_Certificate::get_by_attendee_event($attendee_id, $event_id);
    }

    /**
     * Get the WordPress user for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @return WP_User|null WordPress user or null
     */
    public static function get_user($attendee_id) {
        $attendee = self::get($attendee_id);
        if (!$attendee || !$attendee->user_id) {
            return null;
        }
        return get_user_by('id', $attendee->user_id);
    }

    /**
     * Get session attendance for this attendee
     *
     * @param int $attendee_id Attendee ID
     * @param int $event_id Optional Event ID
     * @return array Array of session attendance records
     */
    public static function get_session_attendance($attendee_id, $event_id = null) {
        $attendee = self::get($attendee_id);
        if (!$attendee) {
            return array();
        }
        $event_id = $event_id ?: $attendee->event_id;
        return SC_Session_Attendance::get_attendee_summary($attendee_id, $event_id);
    }
}
