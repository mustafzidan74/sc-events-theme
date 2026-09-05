<?php
/**
 * SC Transaction Model Class
 *
 * Data Access Layer for Transactions table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Transaction {

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
            self::$table = $wpdb->prefix . 'sc_transactions';
        }
        return self::$table;
    }

    /**
     * Get single transaction by ID
     *
     * @param int $id Transaction ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($transaction) {
            $transaction = self::hydrate($transaction);
        }

        return $transaction;
    }

    /**
     * Get transaction by reference
     *
     * @param string $reference Transaction reference
     * @return object|null
     */
    public static function get_by_reference($reference) {
        global $wpdb;
        $table = self::get_table();

        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE transaction_ref = %s",
            $reference
        ));

        if ($transaction) {
            $transaction = self::hydrate($transaction);
        }

        return $transaction;
    }

    /**
     * Get transaction by gateway reference
     *
     * @param string $gateway_ref Gateway reference
     * @return object|null
     */
    public static function get_by_gateway_reference($gateway_ref) {
        global $wpdb;
        $table = self::get_table();

        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE gateway_ref = %s",
            $gateway_ref
        ));

        if ($transaction) {
            $transaction = self::hydrate($transaction);
        }

        return $transaction;
    }

    /**
     * Get transactions with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'       => null,
            'user_id'        => null,
            'status'         => null,
            'payment_method' => null,
            'date_from'      => null,
            'date_to'        => null,
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

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if ($args['payment_method']) {
            $where[] = 'payment_method = %s';
            $values[] = $args['payment_method'];
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(transaction_ref LIKE %s OR gateway_ref LIKE %s OR customer_email LIKE %s OR customer_name LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'created_at', 'total_amount', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $transactions = $wpdb->get_results($sql);

        foreach ($transactions as &$transaction) {
            $transaction = self::hydrate($transaction);
        }

        return $transactions;
    }

    /**
     * Count transactions
     *
     * @param array $args Filter arguments
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id' => null,
            'user_id'  => null,
            'status'   => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
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
     * Get total revenue
     *
     * @param array $args Filter arguments
     * @return float
     */
    public static function get_total_revenue($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'  => null,
            'date_from' => null,
            'date_to'   => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('status = %s');
        $values = array('success');

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
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

        $sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) FROM $table WHERE $where_clause",
            $values
        );

        return (float) $wpdb->get_var($sql);
    }

    /**
     * Create new transaction
     *
     * @param array $data Transaction data
     * @return int|false Transaction ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate unique reference
        if (empty($data['transaction_ref'])) {
            $data['transaction_ref'] = self::generate_reference();
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $transaction_id = $wpdb->insert_id;
            do_action('sc_transaction_created', $transaction_id, $data);
            return $transaction_id;
        }

        return false;
    }

    /**
     * Update transaction
     *
     * @param int $id Transaction ID
     * @param array $data Transaction data
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
            do_action('sc_transaction_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Update transaction status
     *
     * @param int $id Transaction ID
     * @param string $status New status
     * @param string $gateway_ref Gateway reference (optional)
     * @return bool Success
     */
    public static function update_status($id, $status, $gateway_ref = null) {
        $data = array('status' => $status);

        if ($gateway_ref) {
            $data['gateway_ref'] = $gateway_ref;
        }

        $result = self::update($id, $data);

        if ($result) {
            $transaction = self::get($id);

            // Update attendees payment status
            $attendees = SC_Attendee::get_by_transaction($id);
            foreach ($attendees as $attendee) {
                $payment_status = $status === 'success' ? 'success' : ($status === 'refunded' ? 'refunded' : 'pending');
                SC_Attendee::update($attendee->id, array('payment_status' => $payment_status));
            }

            // Update event stats
            if ($transaction && $transaction->event_id) {
                SC_Event::update_stats($transaction->event_id);
            }

            do_action('sc_transaction_status_changed', $id, $status, $transaction);
        }

        return $result;
    }

    /**
     * Process refund
     *
     * @param int $id Transaction ID
     * @param float $amount Refund amount (null for full refund)
     * @param string $reason Refund reason
     * @return bool Success
     */
    public static function refund($id, $amount = null, $reason = '') {
        $transaction = self::get($id);

        if (!$transaction) {
            return false;
        }

        if ($amount === null) {
            $amount = $transaction->total_amount;
        }

        $refund_amount = min($amount, $transaction->total_amount - $transaction->refunded_amount);

        $result = self::update($id, array(
            'status'          => $refund_amount >= $transaction->total_amount ? 'refunded' : 'partial_refund',
            'refunded_amount' => $transaction->refunded_amount + $refund_amount,
        ));

        if ($result) {
            // Update attendees
            $attendees = SC_Attendee::get_by_transaction($id);
            foreach ($attendees as $attendee) {
                SC_Attendee::update($attendee->id, array(
                    'status'         => 'refunded',
                    'payment_status' => 'refunded',
                ));
            }

            // Update event stats
            if ($transaction->event_id) {
                SC_Event::update_stats($transaction->event_id);
            }

            do_action('sc_transaction_refunded', $id, $refund_amount, $reason, $transaction);
        }

        return $result;
    }

    /**
     * Generate unique transaction reference
     *
     * @return string
     */
    public static function generate_reference() {
        global $wpdb;
        $table = self::get_table();

        $prefix = 'TXN';
        $date = date('Ymd');

        do {
            $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $reference = $prefix . $date . $random;
        } while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE transaction_ref = %s", $reference)));

        return $reference;
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
        $int_fields = array('event_id', 'user_id', 'coupon_id', 'ticket_count');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // Float fields
        $float_fields = array('subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'refunded_amount');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // String fields
        $string_fields = array(
            'transaction_ref', 'gateway_ref', 'payment_method', 'status',
            'currency', 'customer_name', 'customer_email', 'customer_phone',
            'billing_address', 'notes'
        );

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Email field
        if (isset($data['customer_email'])) {
            $prepared['customer_email'] = sanitize_email($data['customer_email']);
        }

        // JSON fields
        if (isset($data['items'])) {
            if (is_array($data['items'])) {
                $prepared['items'] = wp_json_encode($data['items']);
            } else {
                $prepared['items'] = $data['items'];
            }
        }

        if (isset($data['gateway_response'])) {
            if (is_array($data['gateway_response'])) {
                $prepared['gateway_response'] = wp_json_encode($data['gateway_response']);
            } else {
                $prepared['gateway_response'] = $data['gateway_response'];
            }
        }

        return $prepared;
    }

    /**
     * Hydrate transaction object
     *
     * @param object $transaction Raw transaction
     * @return object Hydrated transaction
     */
    private static function hydrate($transaction) {
        // Decode JSON fields
        if (!empty($transaction->items)) {
            $decoded = json_decode($transaction->items, true);
            $transaction->items = is_array($decoded) ? $decoded : array();
        } else {
            $transaction->items = array();
        }

        if (!empty($transaction->gateway_response)) {
            $decoded = json_decode($transaction->gateway_response, true);
            $transaction->gateway_response = is_array($decoded) ? $decoded : array();
        } else {
            $transaction->gateway_response = array();
        }

        // Status labels
        $status_labels = array(
            'pending'        => __('Pending', 'sc_events'),
            'processing'     => __('Processing', 'sc_events'),
            'success'        => __('Success', 'sc_events'),
            'failed'         => __('Failed', 'sc_events'),
            'cancelled'      => __('Cancelled', 'sc_events'),
            'refunded'       => __('Refunded', 'sc_events'),
            'partial_refund' => __('Partial Refund', 'sc_events'),
        );
        $transaction->status_label = isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status;

        // Payment method labels
        $method_labels = array(
            'paymob'       => 'Paymob',
            'fawry'        => 'Fawry',
            'bank_card'    => __('Bank Card', 'sc_events'),
            'mobile_wallet' => __('Mobile Wallet', 'sc_events'),
            'cash'         => __('Cash', 'sc_events'),
            'free'         => __('Free', 'sc_events'),
        );
        $transaction->payment_method_label = isset($method_labels[$transaction->payment_method]) ? $method_labels[$transaction->payment_method] : $transaction->payment_method;

        // Formatted amounts using dynamic currency
        $transaction->subtotal_formatted = sc_format_price($transaction->subtotal, false);
        $transaction->discount_formatted = sc_format_price($transaction->discount_amount, false);
        $transaction->tax_formatted = sc_format_price($transaction->tax_amount, false);
        $transaction->total_formatted = sc_format_price($transaction->total_amount, false);
        $transaction->refunded_formatted = sc_format_price($transaction->refunded_amount, false);

        // Date formatting
        $transaction->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at));

        return $transaction;
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get the event for this transaction
     *
     * @param int $transaction_id Transaction ID
     * @return object|null SC_Event object or null
     */
    public static function get_event($transaction_id) {
        $transaction = self::get($transaction_id);
        if (!$transaction || !$transaction->event_id) {
            return null;
        }
        return SC_Event::get($transaction->event_id);
    }

    /**
     * Get all attendees for this transaction
     *
     * @param int $transaction_id Transaction ID
     * @return array Array of SC_Attendee objects
     */
    public static function get_attendees($transaction_id) {
        return SC_Attendee::get_by_transaction($transaction_id);
    }

    /**
     * Get the WordPress user for this transaction
     *
     * @param int $transaction_id Transaction ID
     * @return WP_User|null WordPress user or null
     */
    public static function get_user($transaction_id) {
        $transaction = self::get($transaction_id);
        if (!$transaction || !$transaction->user_id) {
            return null;
        }
        return get_user_by('id', $transaction->user_id);
    }

    /**
     * Get transactions by event
     *
     * @param int $event_id Event ID
     * @param array $args Optional arguments
     * @return array Array of SC_Transaction objects
     */
    public static function get_by_event($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        return self::get_all($args);
    }

    /**
     * Get transactions by attendee
     *
     * @param int $attendee_id Attendee ID
     * @return object|null SC_Transaction object or null
     */
    public static function get_by_attendee($attendee_id) {
        $attendee = SC_Attendee::get($attendee_id);
        if (!$attendee || !$attendee->transaction_id) {
            return null;
        }
        return self::get($attendee->transaction_id);
    }

    /**
     * Get transactions by user
     *
     * @param int $user_id User ID
     * @param array $args Optional arguments
     * @return array Array of SC_Transaction objects
     */
    public static function get_by_user($user_id, $args = array()) {
        $args['user_id'] = $user_id;
        return self::get_all($args);
    }
}
