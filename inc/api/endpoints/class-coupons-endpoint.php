<?php
/**
 * SC Events API - Coupons Endpoint
 *
 * The coupons managed in the dashboard (sc_coupon posts), checked with the website's rules
 * (sc_coupon_for_ticket() in public-ajax-handlers.php): event, expiry, uses left, ticket type, and
 * workshop coupons for workshop seats only, every other coupon for congress tickets only.
 * The old sc_coupons table is a stale copy and is not read.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Coupons_Endpoint extends SC_Base_Endpoint {

    /** The ticket the coupon is for: ticket_id, else the workshop's or the congress ticket of the event. */
    private function ticketFromInput() {
        global $wpdb;
        $event_id = (int) $this->input('event_id', $this->query('event_id'));
        $ticket_id = (int) $this->input('ticket_id', $this->query('ticket_id'));
        $workshop_id = (int) $this->input('workshop_id', $this->query('workshop_id'));
        $t = $wpdb->prefix . 'sc_tickets';
        if ($ticket_id) {
            $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $ticket_id));
        } elseif ($workshop_id) {
            $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE workshop_id = %d AND is_active = 1 ORDER BY id LIMIT 1", $workshop_id));
        } else {
            $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE event_id = %d AND (workshop_id IS NULL OR workshop_id = 0) AND is_active = 1 ORDER BY id LIMIT 1", $event_id));
        }
        if (!$ticket || ($event_id && (int) $ticket->event_id !== $event_id)) {
            SC_API_Response::notFound('التذكرة غير موجودة');
        }
        return $ticket;
    }

    private function answer($check, $amount) {
        if (is_wp_error($check)) {
            SC_API_Response::success([
                'valid' => false,
                'reason' => strtoupper($check->get_error_code()),
                'message' => $check->get_error_message(),
            ]);
        }
        $discount = $check['discount_type'] === 'percentage' ? $amount * $check['discount_value'] / 100 : $check['discount_value'];
        $discount = min($amount, max(0, $discount));
        return [
            'valid' => true,
            'code' => $check['coupon']->post_title,
            'discount_type' => $check['discount_type'],
            'discount_value' => $check['discount_value'],
            'is_free' => $check['is_free'],
            'discount_amount' => round($discount, 2),
            'original_amount' => round($amount, 2),
            'final_amount' => round(max(0, $amount - $discount), 2),
        ];
    }

    /**
     * POST /coupons/validate
     * Body: code, event_id, and ticket_id or workshop_id (none = the congress ticket), amount.
     * Checks only; nothing is used up.
     */
    public function validateCoupon() {
        $this->validate(['code' => 'required|string', 'event_id' => 'required|integer']);
        $ticket = $this->ticketFromInput();
        $amount = (float) $this->input('amount', $ticket->price);
        SC_API_Response::success($this->answer(sc_coupon_for_ticket($this->input('code'), $ticket), $amount), 'الكوبون صالح');
    }

    /**
     * POST /coupons/apply (signed in)
     * Uses up one use of the coupon. Registering with /attendees/register already does this, so
     * apps only need it for flows that register some other way.
     */
    public function apply() {
        $this->validate(['code' => 'required|string', 'event_id' => 'required|integer']);
        $ticket = $this->ticketFromInput();
        $check = sc_coupon_for_ticket($this->input('code'), $ticket);
        if (is_wp_error($check)) {
            SC_API_Response::error($check->get_error_message(), 400, null, strtoupper($check->get_error_code()));
        }
        sc_coupon_use($check['coupon']->ID);
        SC_API_Response::success([
            'applied' => true,
            'code' => $check['coupon']->post_title,
            'new_usage_count' => (int) get_post_meta($check['coupon']->ID, 'usage_count', true),
        ], 'تم تطبيق الكوبون بنجاح');
    }

    /**
     * GET /coupons/check/{code}&event_id=…[&workshop_id=…|&ticket_id=…]
     */
    public function check() {
        $code = (string) $this->param('code');
        $coupon = get_posts(['post_type' => 'sc_coupon', 'title' => strtoupper($code), 'post_status' => 'publish', 'numberposts' => 1]);
        if (!$coupon) {
            SC_API_Response::success(['exists' => false, 'message' => 'الكوبون غير موجود']);
        }
        $c = $coupon[0];
        $event_id = (int) $this->query('event_id') ?: (int) get_post_meta($c->ID, 'event_id', true);
        if (!$event_id) {
            SC_API_Response::success(['exists' => true, 'valid' => false, 'message' => 'حدد الفعالية']);
        }
        $this->request['body']['event_id'] = $event_id;
        $ticket = $this->ticketFromInput();
        $answer = $this->answer(sc_coupon_for_ticket($code, $ticket), (float) $ticket->price);
        SC_API_Response::success(['exists' => true] + $answer + [
            'for_workshops' => function_exists('sc_coupon_is_workshop') && sc_coupon_is_workshop($c->ID),
        ]);
    }

    /**
     * GET /coupons (managers only) — the event's coupons. Codes are never listed publicly.
     */
    public function index() {
        global $wpdb;
        $event_id = (int) $this->query('event_id');
        if (!$event_id) {
            SC_API_Response::error('يجب تحديد الفعالية', 400);
        }
        $page = max(1, (int) $this->query('page', 1));
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'event_id' AND m.meta_value = %d
             WHERE p.post_type = 'sc_coupon' AND p.post_status = 'publish' ORDER BY p.ID DESC LIMIT 100 OFFSET %d",
            $event_id, ($page - 1) * 100
        ));
        $data = array_map(function ($id) {
            return [
                'code' => get_the_title($id),
                'discount_type' => get_post_meta($id, 'discount_type', true),
                'discount_value' => (float) get_post_meta($id, 'discount_value', true),
                'usage_limit' => (int) get_post_meta($id, 'usage_limit', true),
                'usage_count' => (int) get_post_meta($id, 'usage_count', true),
                'for_workshops' => function_exists('sc_coupon_is_workshop') && sc_coupon_is_workshop($id),
                'expires' => get_post_meta($id, 'expiry_date', true) ?: null,
            ];
        }, $ids);
        SC_API_Response::success($data);
    }
}
