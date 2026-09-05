<?php
/**
 * SC User Points Model Class
 *
 * Data Access Layer for User Points/Wallet table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_User_Points {

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
            self::$table = $wpdb->prefix . 'sc_user_points';
        }
        return self::$table;
    }

    /**
     * Get user's current balance
     *
     * @param int $user_id User ID
     * @return float Current balance
     */
    public static function get_balance($user_id) {
        global $wpdb;
        $table = self::get_table();

        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE user_id = %d",
            $user_id
        ));

        return (float) $balance;
    }

    /**
     * Get user's transactions
     *
     * @param int $user_id User ID
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_transactions($user_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'type'      => null,
            'date_from' => null,
            'date_to'   => null,
            'orderby'   => 'created_at',
            'order'     => 'DESC',
            'limit'     => 50,
            'offset'    => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('user_id = %d');
        $values = array($user_id);

        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
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

        $allowed_orderby = array('id', 'created_at', 'amount', 'type');
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
     * Add points/credit to user
     *
     * @param int $user_id User ID
     * @param float $amount Amount to add (positive)
     * @param string $type Transaction type
     * @param string $description Description
     * @param int $reference_id Reference ID (optional)
     * @return int|false Transaction ID or false
     */
    public static function add($user_id, $amount, $type, $description = '', $reference_id = null) {
        if ($amount <= 0) {
            return false;
        }

        return self::create_transaction(array(
            'user_id'      => $user_id,
            'amount'       => abs($amount), // Always positive for add
            'type'         => $type,
            'description'  => $description,
            'reference_id' => $reference_id,
        ));
    }

    /**
     * Deduct points/credit from user
     *
     * @param int $user_id User ID
     * @param float $amount Amount to deduct (positive value, will be stored as negative)
     * @param string $type Transaction type
     * @param string $description Description
     * @param int $reference_id Reference ID (optional)
     * @return int|false Transaction ID or false
     */
    public static function deduct($user_id, $amount, $type, $description = '', $reference_id = null) {
        if ($amount <= 0) {
            return false;
        }

        // Check if user has enough balance
        $balance = self::get_balance($user_id);
        if ($balance < $amount) {
            return false;
        }

        return self::create_transaction(array(
            'user_id'      => $user_id,
            'amount'       => -abs($amount), // Always negative for deduct
            'type'         => $type,
            'description'  => $description,
            'reference_id' => $reference_id,
        ));
    }

    /**
     * Transfer points between users
     *
     * @param int $from_user_id Sender user ID
     * @param int $to_user_id Receiver user ID
     * @param float $amount Amount to transfer
     * @param string $description Description
     * @return bool Success
     */
    public static function transfer($from_user_id, $to_user_id, $amount, $description = '') {
        if ($amount <= 0 || $from_user_id === $to_user_id) {
            return false;
        }

        // Check sender's balance
        $balance = self::get_balance($from_user_id);
        if ($balance < $amount) {
            return false;
        }

        // Deduct from sender
        $deduct_result = self::deduct(
            $from_user_id,
            $amount,
            'transfer_out',
            $description ?: sprintf(__('Transfer to user #%d', 'sc_events'), $to_user_id),
            $to_user_id
        );

        if (!$deduct_result) {
            return false;
        }

        // Add to receiver
        $add_result = self::add(
            $to_user_id,
            $amount,
            'transfer_in',
            $description ?: sprintf(__('Transfer from user #%d', 'sc_events'), $from_user_id),
            $from_user_id
        );

        return $add_result !== false;
    }

    /**
     * Get user statistics
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_stats($user_id) {
        global $wpdb;
        $table = self::get_table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_spent,
                COUNT(*) as total_transactions
            FROM $table
            WHERE user_id = %d",
            $user_id
        ));

        return array(
            'balance'            => self::get_balance($user_id),
            'total_earned'       => (float) $stats->total_earned,
            'total_spent'        => (float) $stats->total_spent,
            'total_transactions' => (int) $stats->total_transactions,
        );
    }

    /**
     * Get earnings by type
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_earnings_by_type($user_id) {
        global $wpdb;
        $table = self::get_table();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COALESCE(SUM(amount), 0) as total
            FROM $table
            WHERE user_id = %d AND amount > 0
            GROUP BY type",
            $user_id
        ), OBJECT_K);

        return $results;
    }

    /**
     * Create transaction record
     *
     * @param array $data Transaction data
     * @return int|false Transaction ID or false
     */
    private static function create_transaction($data) {
        global $wpdb;
        $table = self::get_table();

        $insert_data = array(
            'user_id'      => intval($data['user_id']),
            'amount'       => floatval($data['amount']),
            'type'         => sanitize_text_field($data['type']),
            'description'  => sanitize_text_field($data['description'] ?? ''),
            'reference_id' => isset($data['reference_id']) ? intval($data['reference_id']) : null,
            'created_at'   => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result) {
            $transaction_id = $wpdb->insert_id;

            // Update user meta for quick balance access
            $balance = self::get_balance($data['user_id']);
            update_user_meta($data['user_id'], 'sc_points_balance', $balance);

            do_action('sc_user_points_changed', $data['user_id'], $data['amount'], $data['type'], $transaction_id);

            return $transaction_id;
        }

        return false;
    }

    /**
     * Hydrate transaction object
     *
     * @param object $transaction Raw transaction
     * @return object Hydrated transaction
     */
    private static function hydrate($transaction) {
        // Type labels
        $type_labels = array(
            'referral_commission' => __('Referral Commission', 'sc_events'),
            'ticket_refund'       => __('Ticket Refund', 'sc_events'),
            'admin_credit'        => __('Admin Credit', 'sc_events'),
            'admin_debit'         => __('Admin Debit', 'sc_events'),
            'transfer_in'         => __('Transfer Received', 'sc_events'),
            'transfer_out'        => __('Transfer Sent', 'sc_events'),
            'purchase'            => __('Purchase', 'sc_events'),
            'bonus'               => __('Bonus', 'sc_events'),
            'event_attendance'    => __('Event Attendance', 'sc_events'),
        );
        $transaction->type_label = isset($type_labels[$transaction->type]) ? $type_labels[$transaction->type] : $transaction->type;

        // Format amount using dynamic currency
        $prefix = $transaction->amount >= 0 ? '+' : '';
        $transaction->amount_formatted = $prefix . sc_format_price(abs($transaction->amount), false);

        // Is credit or debit
        $transaction->is_credit = $transaction->amount > 0;
        $transaction->is_debit = $transaction->amount < 0;

        // Format date
        $transaction->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at));

        return $transaction;
    }

    /**
     * Award points for event attendance
     *
     * @param int $user_id User ID
     * @param int $event_id Event ID
     * @param float $points Points to award
     * @return int|false Transaction ID or false
     */
    public static function award_attendance_points($user_id, $event_id, $points = null) {
        if ($points === null) {
            $points = floatval(get_option('sc_attendance_points', 10));
        }

        if ($points <= 0) {
            return false;
        }

        // Check if already awarded for this event
        global $wpdb;
        $table = self::get_table();

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND type = 'event_attendance' AND reference_id = %d",
            $user_id,
            $event_id
        ));

        if ($exists) {
            return false; // Already awarded
        }

        $event = SC_Event::get($event_id);
        $event_title = $event ? $event->title : __('Event', 'sc_events');

        return self::add(
            $user_id,
            $points,
            'event_attendance',
            sprintf(__('Points for attending: %s', 'sc_events'), $event_title),
            $event_id
        );
    }
}
