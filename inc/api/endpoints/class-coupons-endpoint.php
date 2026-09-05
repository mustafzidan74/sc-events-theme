<?php
/**
 * SC Events API - Coupons Endpoint
 *
 * Handles coupon validation and application
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Coupons_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $events_table;
    private $tickets_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_coupons';
        $this->events_table = $wpdb->prefix . 'sc_events';
        $this->tickets_table = $wpdb->prefix . 'sc_tickets';
    }

    /**
     * POST /coupons/validate - Validate a coupon code
     */
    public function validateCoupon() {
        global $wpdb;

        $this->validate([
            'code' => 'required|string',
            'event_id' => 'required|integer'
        ]);

        $code = strtoupper(sanitize_text_field($this->input('code')));
        $event_id = (int) $this->input('event_id');
        $ticket_id = (int) $this->input('ticket_id');
        $amount = (float) $this->input('amount', 0);

        // Get coupon
        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE code = %s AND is_active = 1
             AND (event_id IS NULL OR event_id = %d)
             AND (start_date IS NULL OR start_date <= NOW())
             AND (expiry_date IS NULL OR expiry_date >= NOW())",
            $code, $event_id
        ));

        if (!$coupon) {
            SC_API_Response::success([
                'valid' => false,
                'message' => 'كود الخصم غير صالح أو منتهي الصلاحية'
            ]);
            return;
        }

        // Check usage limit
        if ($coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit) {
            SC_API_Response::success([
                'valid' => false,
                'message' => 'تم استنفاد عدد مرات استخدام الكوبون'
            ]);
            return;
        }

        // Check ticket restriction
        if ($coupon->ticket_ids && $ticket_id) {
            $allowed_tickets = json_decode($coupon->ticket_ids, true);
            if (is_array($allowed_tickets) && !in_array($ticket_id, $allowed_tickets)) {
                SC_API_Response::success([
                    'valid' => false,
                    'message' => 'الكوبون غير صالح لهذا النوع من التذاكر'
                ]);
                return;
            }
        }

        // Check minimum amount
        if ($coupon->min_amount > 0 && $amount < $coupon->min_amount) {
            SC_API_Response::success([
                'valid' => false,
                'message' => 'الحد الأدنى للطلب ' . number_format($coupon->min_amount, 2) . ' لاستخدام هذا الكوبون'
            ]);
            return;
        }

        // Calculate discount
        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = ($amount * $coupon->discount_value) / 100;
        } else {
            $discount = (float) $coupon->discount_value;
        }

        // Apply max discount cap
        if ($coupon->max_discount > 0 && $discount > $coupon->max_discount) {
            $discount = (float) $coupon->max_discount;
        }

        // Don't discount more than the price
        if ($discount > $amount) {
            $discount = $amount;
        }

        $final_amount = max(0, $amount - $discount);

        SC_API_Response::success([
            'valid' => true,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'discount_amount' => round($discount, 2),
            'original_amount' => round($amount, 2),
            'final_amount' => round($final_amount, 2),
            'message' => $final_amount > 0
                ? 'تم تطبيق خصم ' . number_format($discount, 2)
                : 'المبلغ المطلوب = 0 (تذكرة مجانية)'
        ], 'الكوبون صالح');
    }

    /**
     * POST /coupons/apply - Apply coupon (increment usage)
     */
    public function apply() {
        global $wpdb;

        $this->validate([
            'code' => 'required|string',
            'event_id' => 'required|integer'
        ]);

        $code = strtoupper(sanitize_text_field($this->input('code')));
        $event_id = (int) $this->input('event_id');

        // Verify coupon is valid
        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE code = %s AND is_active = 1
             AND (event_id IS NULL OR event_id = %d)
             AND (start_date IS NULL OR start_date <= NOW())
             AND (expiry_date IS NULL OR expiry_date >= NOW())
             AND (usage_limit IS NULL OR usage_count < usage_limit)",
            $code, $event_id
        ));

        if (!$coupon) {
            SC_API_Response::error('كود الخصم غير صالح', 400);
        }

        // Increment usage count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET usage_count = usage_count + 1 WHERE id = %d",
            $coupon->id
        ));

        SC_API_Response::success([
            'applied' => true,
            'code' => $coupon->code,
            'new_usage_count' => $coupon->usage_count + 1
        ], 'تم تطبيق الكوبون بنجاح');
    }

    /**
     * GET /coupons/check/{code} - Quick check if coupon exists and is active
     */
    public function check() {
        global $wpdb;

        $code = strtoupper($this->param('code'));

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT id, code, discount_type, discount_value, event_id, min_amount, max_discount,
                    usage_limit, usage_count, start_date, expiry_date
             FROM {$this->table}
             WHERE code = %s AND is_active = 1",
            $code
        ));

        if (!$coupon) {
            SC_API_Response::success([
                'exists' => false,
                'message' => 'الكوبون غير موجود'
            ]);
            return;
        }

        // Check dates
        $now = current_time('mysql');
        $is_started = !$coupon->start_date || $coupon->start_date <= $now;
        $is_expired = $coupon->expiry_date && $coupon->expiry_date < $now;
        $has_uses = !$coupon->usage_limit || $coupon->usage_count < $coupon->usage_limit;

        $is_valid = $is_started && !$is_expired && $has_uses;

        SC_API_Response::success([
            'exists' => true,
            'valid' => $is_valid,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'event_id' => $coupon->event_id ? (int) $coupon->event_id : null,
            'min_amount' => (float) $coupon->min_amount,
            'max_discount' => $coupon->max_discount ? (float) $coupon->max_discount : null,
            'usage_remaining' => $coupon->usage_limit ? ($coupon->usage_limit - $coupon->usage_count) : null,
            'status' => [
                'started' => $is_started,
                'expired' => $is_expired,
                'has_uses' => $has_uses
            ]
        ]);
    }

    /**
     * GET /coupons - List coupons for an event (public info only)
     */
    public function index() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if (!$event_id) {
            SC_API_Response::error('يجب تحديد الفعالية', 400);
        }

        // Get only active, non-expired coupons with remaining uses
        $coupons = $wpdb->get_results($wpdb->prepare(
            "SELECT code, discount_type, discount_value, min_amount, expiry_date
             FROM {$this->table}
             WHERE is_active = 1
             AND (event_id IS NULL OR event_id = %d)
             AND (start_date IS NULL OR start_date <= NOW())
             AND (expiry_date IS NULL OR expiry_date >= NOW())
             AND (usage_limit IS NULL OR usage_count < usage_limit)",
            $event_id
        ));

        // Only return limited public info
        $data = array_map(function($c) {
            return [
                'code' => $c->code,
                'discount_type' => $c->discount_type,
                'discount_value' => (float) $c->discount_value,
                'min_amount' => $c->min_amount ? (float) $c->min_amount : null,
                'expires' => $c->expiry_date
            ];
        }, $coupons);

        SC_API_Response::success($data);
    }
}
