<?php
/**
 * SC Referral Model Class
 *
 * Data Access Layer for Referrals table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Referral {

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
            self::$table = $wpdb->prefix . 'sc_referrals';
        }
        return self::$table;
    }

    /**
     * Get single referral by ID
     *
     * @param int $id Referral ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($referral) {
            $referral = self::hydrate($referral);
        }

        return $referral;
    }

    /**
     * Get referral by code
     *
     * @param string $code Referral code
     * @return object|null
     */
    public static function get_by_code($code) {
        global $wpdb;
        $table = self::get_table();

        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE referral_code = %s",
            strtoupper(trim($code))
        ));

        if ($referral) {
            $referral = self::hydrate($referral);
        }

        return $referral;
    }

    /**
     * Get referrals by referrer
     *
     * @param int $user_id Referrer user ID
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_by_referrer($user_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'   => null,
            'event_id' => null,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'limit'    => 50,
            'offset'   => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('referrer_id = %d');
        $values = array($user_id);

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'created_at', 'commission_amount', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $referrals = $wpdb->get_results($sql);

        foreach ($referrals as &$referral) {
            $referral = self::hydrate($referral);
        }

        return $referrals;
    }

    /**
     * Get referral statistics for a user
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_stats($user_id) {
        global $wpdb;
        $table = self::get_table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_referrals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_referrals,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending_earnings
            FROM $table
            WHERE referrer_id = %d",
            $user_id
        ));

        return array(
            'total_referrals'     => (int) $stats->total_referrals,
            'completed_referrals' => (int) $stats->completed_referrals,
            'pending_referrals'   => (int) $stats->pending_referrals,
            'total_earned'        => (float) $stats->total_earned,
            'pending_earnings'    => (float) $stats->pending_earnings,
        );
    }

    /**
     * Create new referral
     *
     * @param array $data Referral data
     * @return int|false Referral ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Generate unique code if not provided
        if (empty($data['referral_code'])) {
            $data['referral_code'] = self::generate_code($data['referrer_id']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $referral_id = $wpdb->insert_id;
            do_action('sc_referral_created', $referral_id, $data);
            return $referral_id;
        }

        return false;
    }

    /**
     * Update referral
     *
     * @param int $id Referral ID
     * @param array $data Referral data
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
            do_action('sc_referral_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Mark referral as completed
     *
     * @param int $id Referral ID
     * @return bool Success
     */
    public static function complete($id) {
        $referral = self::get($id);
        if (!$referral) {
            return false;
        }

        $result = self::update($id, array(
            'status'       => 'completed',
            'completed_at' => current_time('mysql'),
        ));

        if ($result) {
            // Add commission to user points/wallet
            SC_User_Points::add(
                $referral->referrer_id,
                $referral->commission_amount,
                'referral_commission',
                sprintf(__('Commission from referral #%d', 'sc_events'), $id),
                $id
            );

            do_action('sc_referral_completed', $id, $referral);
        }

        return $result;
    }

    /**
     * Generate unique referral code for user
     *
     * @param int $user_id User ID
     * @return string
     */
    public static function generate_code($user_id) {
        global $wpdb;
        $table = self::get_table();

        // Try user-based code first
        $user = get_userdata($user_id);
        if ($user) {
            $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $user->user_login), 0, 6));
        } else {
            $base = 'REF';
        }

        $code = $base . wp_rand(1000, 9999);

        // Ensure uniqueness
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE referral_code = %s", $code))) {
            $code = $base . wp_rand(1000, 9999);
        }

        return $code;
    }

    /**
     * Get or create referral code for user
     *
     * @param int $user_id User ID
     * @param int $event_id Event ID (optional)
     * @return string Referral code
     */
    public static function get_user_code($user_id, $event_id = null) {
        global $wpdb;
        $table = self::get_table();

        // Check for existing active code
        $where = array('referrer_id = %d', "status IN ('active', 'pending')");
        $values = array($user_id);

        if ($event_id) {
            $where[] = 'event_id = %d';
            $values[] = $event_id;
        } else {
            $where[] = 'event_id IS NULL';
        }

        $code = $wpdb->get_var($wpdb->prepare(
            "SELECT referral_code FROM $table WHERE " . implode(' AND ', $where) . " LIMIT 1",
            $values
        ));

        if ($code) {
            return $code;
        }

        // Create new referral entry
        $referral_id = self::create(array(
            'referrer_id' => $user_id,
            'event_id'    => $event_id,
            'status'      => 'active',
        ));

        if ($referral_id) {
            $referral = self::get($referral_id);
            return $referral->referral_code;
        }

        return self::generate_code($user_id);
    }

    /**
     * Track referral usage
     *
     * @param string $code Referral code
     * @param int $referred_id Referred user ID
     * @param int $transaction_id Transaction ID
     * @param float $commission_amount Commission amount
     * @return int|false Referral ID or false
     */
    public static function track_usage($code, $referred_id, $transaction_id, $commission_amount = 0) {
        $referral = self::get_by_code($code);

        if (!$referral) {
            return false;
        }

        // Update existing or create new record
        return self::create(array(
            'referrer_id'       => $referral->referrer_id,
            'referred_id'       => $referred_id,
            'event_id'          => $referral->event_id,
            'transaction_id'    => $transaction_id,
            'referral_code'     => $code,
            'commission_amount' => $commission_amount,
            'status'            => 'pending',
        ));
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
        $int_fields = array('referrer_id', 'referred_id', 'event_id', 'transaction_id');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = $data[$field] ? intval($data[$field]) : null;
            }
        }

        // Float fields
        $float_fields = array('commission_amount');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('referral_code', 'status');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Uppercase code
        if (isset($prepared['referral_code'])) {
            $prepared['referral_code'] = strtoupper($prepared['referral_code']);
        }

        // Datetime fields
        if (isset($data['completed_at'])) {
            $prepared['completed_at'] = $data['completed_at'] ? date('Y-m-d H:i:s', strtotime($data['completed_at'])) : null;
        }

        return $prepared;
    }

    /**
     * Hydrate referral object
     *
     * @param object $referral Raw referral
     * @return object Hydrated referral
     */
    private static function hydrate($referral) {
        // Status labels
        $status_labels = array(
            'active'    => __('Active', 'sc_events'),
            'pending'   => __('Pending', 'sc_events'),
            'completed' => __('Completed', 'sc_events'),
            'cancelled' => __('Cancelled', 'sc_events'),
        );
        $referral->status_label = isset($status_labels[$referral->status]) ? $status_labels[$referral->status] : $referral->status;

        // Get referrer info
        if ($referral->referrer_id) {
            $referrer = get_userdata($referral->referrer_id);
            $referral->referrer_name = $referrer ? $referrer->display_name : __('Unknown', 'sc_events');
            $referral->referrer_email = $referrer ? $referrer->user_email : '';
        }

        // Get referred info
        if ($referral->referred_id) {
            $referred = get_userdata($referral->referred_id);
            $referral->referred_name = $referred ? $referred->display_name : __('Unknown', 'sc_events');
            $referral->referred_email = $referred ? $referred->user_email : '';
        }

        // Format commission using dynamic currency
        $referral->commission_formatted = sc_format_price($referral->commission_amount, false);

        // Format dates
        $referral->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($referral->created_at));

        if ($referral->completed_at) {
            $referral->completed_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($referral->completed_at));
        }

        return $referral;
    }
}
