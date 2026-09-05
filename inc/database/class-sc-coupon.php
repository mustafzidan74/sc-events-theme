<?php
/**
 * SC Coupon Model Class
 *
 * Data Access Layer for Coupons table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Coupon {

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
            self::$table = $wpdb->prefix . 'sc_coupons';
        }
        return self::$table;
    }

    /**
     * Get single coupon by ID
     *
     * @param int $id Coupon ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($coupon) {
            $coupon = self::hydrate($coupon);
        }

        return $coupon;
    }

    /**
     * Get coupon by code
     *
     * @param string $code Coupon code
     * @return object|null
     */
    public static function get_by_code($code) {
        global $wpdb;
        $table = self::get_table();

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE code = %s",
            strtoupper(trim($code))
        ));

        if ($coupon) {
            $coupon = self::hydrate($coupon);
        }

        return $coupon;
    }

    /**
     * Get coupons with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'    => null,
            'category_id' => null,
            'status'      => null,
            'type'        => null,
            'search'      => null,
            'orderby'     => 'created_at',
            'order'       => 'DESC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = '(event_id = %d OR event_id IS NULL)';
            $values[] = $args['event_id'];
        }

        if ($args['category_id']) {
            $where[] = 'category_id = %d';
            $values[] = $args['category_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'discount_type = %s';
            $values[] = $args['type'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(code LIKE %s OR description LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'code', 'discount_value', 'usage_count', 'created_at', 'expires_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $coupons = $wpdb->get_results($sql);

        foreach ($coupons as &$coupon) {
            $coupon = self::hydrate($coupon);
        }

        return $coupons;
    }

    /**
     * Create new coupon
     *
     * @param array $data Coupon data
     * @return int|false Coupon ID or false
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Default category to General (id=1) if not provided
        if (empty($data['category_id'])) {
            $data['category_id'] = 1;
        }

        // Uppercase code
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $coupon_id = $wpdb->insert_id;
            do_action('sc_coupon_created', $coupon_id, $data);
            return $coupon_id;
        }

        return false;
    }

    /**
     * Update coupon
     *
     * @param int $id Coupon ID
     * @param array $data Coupon data
     * @return bool Success
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $data = self::prepare_data($data);
        $data['updated_at'] = current_time('mysql');

        // Uppercase code
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result !== false) {
            do_action('sc_coupon_updated', $id, $data);
            return true;
        }

        return false;
    }

    /**
     * Delete coupon
     *
     * @param int $id Coupon ID
     * @return bool Success
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        $coupon = self::get($id);
        if (!$coupon) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result) {
            do_action('sc_coupon_deleted', $id, $coupon);
            return true;
        }

        return false;
    }

    /**
     * Validate coupon for use
     *
     * @param string $code Coupon code
     * @param int $event_id Event ID
     * @param float $cart_total Cart total
     * @param int $user_id User ID (optional)
     * @return array ['valid' => bool, 'coupon' => object|null, 'error' => string|null]
     */
    public static function validate($code, $event_id, $cart_total = 0, $user_id = null) {
        $coupon = self::get_by_code($code);

        if (!$coupon) {
            return array(
                'valid'  => false,
                'coupon' => null,
                'error'  => __('Invalid coupon code.', 'sc_events'),
            );
        }

        // Check status
        if ($coupon->status !== 'active') {
            return array(
                'valid'  => false,
                'coupon' => $coupon,
                'error'  => __('This coupon is not active.', 'sc_events'),
            );
        }

        // Check event restriction
        if ($coupon->event_id && $coupon->event_id != $event_id) {
            return array(
                'valid'  => false,
                'coupon' => $coupon,
                'error'  => __('This coupon is not valid for this event.', 'sc_events'),
            );
        }

        // Check expiration
        if ($coupon->expires_at && strtotime($coupon->expires_at) < strtotime(current_time('mysql'))) {
            return array(
                'valid'  => false,
                'coupon' => $coupon,
                'error'  => __('This coupon has expired.', 'sc_events'),
            );
        }

        // Check usage limit
        if ($coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit) {
            return array(
                'valid'  => false,
                'coupon' => $coupon,
                'error'  => __('This coupon has reached its usage limit.', 'sc_events'),
            );
        }

        // Check per-user limit
        if ($user_id && $coupon->usage_limit_per_user > 0) {
            $user_usage = self::get_user_usage($coupon->id, $user_id);
            if ($user_usage >= $coupon->usage_limit_per_user) {
                return array(
                    'valid'  => false,
                    'coupon' => $coupon,
                    'error'  => __('You have already used this coupon the maximum number of times.', 'sc_events'),
                );
            }
        }

        // Check minimum amount
        if ($coupon->min_purchase_amount > 0 && $cart_total < $coupon->min_purchase_amount) {
            return array(
                'valid'  => false,
                'coupon' => $coupon,
                'error'  => sprintf(
                    __('Minimum purchase amount for this coupon is %s.', 'sc_events'),
                    sc_format_price($coupon->min_purchase_amount, false)
                ),
            );
        }

        return array(
            'valid'  => true,
            'coupon' => $coupon,
            'error'  => null,
        );
    }

    /**
     * Calculate discount amount
     *
     * @param object $coupon Coupon object
     * @param float $amount Amount to discount
     * @return float Discount amount
     */
    public static function calculate_discount($coupon, $amount) {
        if ($coupon->discount_type === 'percentage') {
            $discount = ($amount * $coupon->discount_value) / 100;

            // Apply max discount if set
            if ($coupon->max_discount_amount > 0) {
                $discount = min($discount, $coupon->max_discount_amount);
            }
        } else {
            $discount = min($coupon->discount_value, $amount);
        }

        return round($discount, 2);
    }

    /**
     * Increment usage count
     *
     * @param int $id Coupon ID
     * @param int $user_id User ID (optional)
     * @return bool Success
     */
    public static function increment_usage($id, $user_id = null) {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET usage_count = usage_count + 1, updated_at = %s WHERE id = %d",
            current_time('mysql'),
            $id
        ));

        // Track per-user usage
        if ($result !== false && $user_id) {
            self::track_user_usage($id, $user_id);
        }

        return $result !== false;
    }

    /**
     * Get user usage count
     *
     * @param int $coupon_id Coupon ID
     * @param int $user_id User ID
     * @return int Usage count
     */
    public static function get_user_usage($coupon_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_transactions';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE coupon_id = %d AND user_id = %d AND status = 'success'",
            $coupon_id,
            $user_id
        ));
    }

    /**
     * Track user usage (for per-user limits)
     *
     * @param int $coupon_id Coupon ID
     * @param int $user_id User ID
     */
    private static function track_user_usage($coupon_id, $user_id) {
        // Usage is tracked through transactions table
        // This is just a hook for additional tracking if needed
        do_action('sc_coupon_used', $coupon_id, $user_id);
    }

    /**
     * Generate unique coupon code
     *
     * @param int $length Code length
     * @return string
     */
    public static function generate_code($length = 8) {
        global $wpdb;
        $table = self::get_table();

        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[wp_rand(0, strlen($chars) - 1)];
            }
        } while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE code = %s", $code)));

        return $code;
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
        $int_fields = array('event_id', 'category_id', 'usage_limit', 'usage_limit_per_user', 'usage_count');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // Float fields
        $float_fields = array('discount_value', 'min_purchase_amount', 'max_discount_amount');

        foreach ($float_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = floatval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('code', 'description', 'discount_type', 'status');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Datetime fields
        if (isset($data['expires_at'])) {
            $prepared['expires_at'] = $data['expires_at'] ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : null;
        }

        // JSON fields
        if (isset($data['allowed_tickets'])) {
            if (is_array($data['allowed_tickets'])) {
                $prepared['allowed_tickets'] = wp_json_encode($data['allowed_tickets']);
            } else {
                $prepared['allowed_tickets'] = $data['allowed_tickets'];
            }
        }

        return $prepared;
    }

    /**
     * Hydrate coupon object
     *
     * @param object $coupon Raw coupon
     * @return object Hydrated coupon
     */
    private static function hydrate($coupon) {
        // Decode JSON fields
        if (!empty($coupon->allowed_tickets)) {
            $decoded = json_decode($coupon->allowed_tickets, true);
            $coupon->allowed_tickets = is_array($decoded) ? $decoded : array();
        } else {
            $coupon->allowed_tickets = array();
        }

        // Status labels
        $status_labels = array(
            'active'   => __('Active', 'sc_events'),
            'inactive' => __('Inactive', 'sc_events'),
            'expired'  => __('Expired', 'sc_events'),
        );
        $coupon->status_label = isset($status_labels[$coupon->status]) ? $status_labels[$coupon->status] : $coupon->status;

        // Type labels
        $type_labels = array(
            'percentage' => __('Percentage', 'sc_events'),
            'fixed'      => __('Fixed Amount', 'sc_events'),
        );
        $coupon->type_label = isset($type_labels[$coupon->discount_type]) ? $type_labels[$coupon->discount_type] : $coupon->discount_type;

        // Formatted discount
        if ($coupon->discount_type === 'percentage') {
            $coupon->discount_formatted = $coupon->discount_value . '%';
        } else {
            $coupon->discount_formatted = sc_format_price($coupon->discount_value, false);
        }

        // Check if expired
        $coupon->is_expired = $coupon->expires_at && strtotime($coupon->expires_at) < strtotime(current_time('mysql'));

        // Check if usage limit reached
        $coupon->is_limit_reached = $coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit;

        // Remaining uses
        $coupon->remaining_uses = $coupon->usage_limit > 0 ? max(0, $coupon->usage_limit - $coupon->usage_count) : -1;

        return $coupon;
    }
}
