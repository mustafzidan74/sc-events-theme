<?php
/**
 * SC Events API - Attendees Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Attendees_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $events_table;
    private $tickets_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_attendees';
        $this->events_table = $wpdb->prefix . 'sc_events';
        $this->tickets_table = $wpdb->prefix . 'sc_tickets';
    }

    /**
     * POST /attendees/register - Register as attendee for an event
     */
    public function register() {
        global $wpdb;

        $this->validate([
            'event_id' => 'required|integer',
            'ticket_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'string|max:50'
        ]);

        $event_id = (int) $this->input('event_id');
        $ticket_id = (int) $this->input('ticket_id');

        // Check event exists and is published
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->events_table} WHERE id = %d AND status = 'publish'",
            $event_id
        ));

        if (!$event) {
            SC_API_Response::notFound('الفعالية غير موجودة أو غير متاحة');
        }

        // Check registration deadline
        if ($event->registration_deadline && current_time('mysql') > $event->registration_deadline) {
            SC_API_Response::error('انتهى موعد التسجيل لهذه الفعالية', 400);
        }

        // Check ticket exists and is available
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tickets_table} WHERE id = %d AND event_id = %d AND is_active = 1",
            $ticket_id, $event_id
        ));

        if (!$ticket) {
            SC_API_Response::notFound('التذكرة غير موجودة');
        }

        // Check ticket availability
        $available = (int) $ticket->quantity - (int) $ticket->sold;
        if ($available <= 0) {
            SC_API_Response::error('نفدت التذاكر المتاحة', 400);
        }

        // Check for duplicate registration
        $email = sanitize_email($this->input('email'));
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE event_id = %d AND email = %s AND status != 'cancelled'",
            $event_id, $email
        ));

        if ($existing) {
            SC_API_Response::error('هذا البريد الإلكتروني مسجل مسبقاً في هذه الفعالية', 400);
        }

        // Get extra fields from event
        $extra_fields = json_decode($event->extra_fields, true) ?: [];
        $extra_values = [];

        // Collect extra field values
        $input_extra = $this->input('extra_fields');
        if (is_array($input_extra)) {
            foreach ($extra_fields as $field) {
                $field_name = $field['name'] ?? '';
                if (isset($input_extra[$field_name])) {
                    $extra_values[$field_name] = sanitize_text_field($input_extra[$field_name]);
                }
            }
        }

        // Generate ticket code
        $ticket_code = $this->generateTicketCode();

        // Prepare attendee data
        $data = [
            'event_id' => $event_id,
            'ticket_id' => $ticket_id,
            'user_id' => $this->userId() ?: null,
            'name' => sanitize_text_field($this->input('name')),
            'email' => $email,
            'phone' => sanitize_text_field($this->input('phone')),
            'ticket_code' => $ticket_code,
            'ticket_name' => $ticket->name,
            'ticket_price' => $ticket->price,
            'payment_status' => $ticket->price > 0 ? 'pending' : 'success',
            'payment_method' => $ticket->price > 0 ? null : 'free',
            'amount_paid' => $ticket->price > 0 ? 0 : $ticket->price,
            'extra_fields' => !empty($extra_values) ? json_encode($extra_values) : null,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];

        // Apply coupon if provided
        $coupon_code = $this->input('coupon_code');
        if ($coupon_code && $ticket->price > 0) {
            $coupon_result = $this->applyCoupon($coupon_code, $ticket->price, $event_id, $ticket_id);
            if ($coupon_result['valid']) {
                $data['coupon_code'] = $coupon_code;
                $data['coupon_discount'] = $coupon_result['discount'];
                $data['ticket_price'] = $ticket->price - $coupon_result['discount'];

                if ($data['ticket_price'] <= 0) {
                    $data['ticket_price'] = 0;
                    $data['payment_status'] = 'success';
                    $data['payment_method'] = 'coupon';
                    $data['amount_paid'] = 0;
                }
            }
        }

        // Insert attendee
        $result = $wpdb->insert($this->table, $data);

        if ($result === false) {
            SC_API_Response::error('فشل في التسجيل، حاول مرة أخرى', 500);
        }

        $attendee_id = $wpdb->insert_id;

        // Update ticket sold count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tickets_table} SET sold = sold + 1 WHERE id = %d",
            $ticket_id
        ));

        // Update event sold count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->events_table} SET total_sold = total_sold + 1 WHERE id = %d",
            $event_id
        ));

        // Get the created attendee
        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $attendee_id
        ));

        SC_API_Response::created([
            'id' => (int) $attendee->id,
            'ticket_code' => $attendee->ticket_code,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'event_id' => (int) $attendee->event_id,
            'ticket_name' => $attendee->ticket_name,
            'ticket_price' => (float) $attendee->ticket_price,
            'payment_status' => $attendee->payment_status,
            'coupon_code' => $attendee->coupon_code,
            'coupon_discount' => (float) ($attendee->coupon_discount ?? 0),
            'status' => $attendee->status,
            'extra_fields' => json_decode($attendee->extra_fields, true) ?: [],
            'requires_payment' => $attendee->payment_status === 'pending' && $attendee->ticket_price > 0
        ], 'تم التسجيل بنجاح');
    }

    /**
     * GET /attendees/{ticket_code} - Get attendee by ticket code
     */
    public function show() {
        global $wpdb;

        $ticket_code = $this->param('ticket_code');

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, e.title as event_title, e.start_date, e.end_date, e.venue_name
             FROM {$this->table} a
             JOIN {$this->events_table} e ON a.event_id = e.id
             WHERE a.ticket_code = %s",
            $ticket_code
        ));

        if (!$attendee) {
            SC_API_Response::notFound('التذكرة غير موجودة');
        }

        SC_API_Response::success([
            'id' => (int) $attendee->id,
            'ticket_code' => $attendee->ticket_code,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'status' => $attendee->status,
            'payment_status' => $attendee->payment_status,
            'checked_in' => (bool) $attendee->checked_in,
            'checked_in_at' => $attendee->checked_in_at,
            'event' => [
                'id' => (int) $attendee->event_id,
                'title' => $attendee->event_title,
                'start_date' => $attendee->start_date,
                'end_date' => $attendee->end_date,
                'venue_name' => $attendee->venue_name
            ],
            'ticket' => [
                'name' => $attendee->ticket_name,
                'price' => (float) $attendee->ticket_price
            ],
            'extra_fields' => json_decode($attendee->extra_fields, true) ?: []
        ]);
    }

    /**
     * GET /attendees/my-tickets - Get logged in user's tickets
     */
    public function myTickets() {
        global $wpdb;

        $user_id = $this->userId();
        $email = $this->query('email');

        if (!$user_id && !$email) {
            SC_API_Response::error('يجب تسجيل الدخول أو إدخال البريد الإلكتروني', 400);
        }

        $where = $user_id
            ? $wpdb->prepare("a.user_id = %d", $user_id)
            : $wpdb->prepare("a.email = %s", sanitize_email($email));

        $attendees = $wpdb->get_results(
            "SELECT a.*, e.title as event_title, e.start_date, e.end_date, e.venue_name,
                    e.enable_certificates
             FROM {$this->table} a
             JOIN {$this->events_table} e ON a.event_id = e.id
             WHERE {$where} AND a.status = 'active'
             ORDER BY e.start_date DESC"
        );

        $data = array_map(function($a) {
            return [
                'id' => (int) $a->id,
                'ticket_code' => $a->ticket_code,
                'name' => $a->name,
                'email' => $a->email,
                'status' => $a->status,
                'payment_status' => $a->payment_status,
                'checked_in' => (bool) $a->checked_in,
                'event' => [
                    'id' => (int) $a->event_id,
                    'title' => $a->event_title,
                    'start_date' => $a->start_date,
                    'end_date' => $a->end_date,
                    'venue_name' => $a->venue_name,
                    'enable_certificates' => (bool) $a->enable_certificates
                ],
                'ticket' => [
                    'name' => $a->ticket_name,
                    'price' => (float) $a->ticket_price
                ]
            ];
        }, $attendees);

        SC_API_Response::success($data);
    }

    /**
     * Apply coupon to calculate discount
     */
    private function applyCoupon($code, $price, $event_id, $ticket_id) {
        global $wpdb;

        $coupons_table = $wpdb->prefix . 'sc_coupons';

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$coupons_table}
             WHERE code = %s AND is_active = 1
             AND (event_id IS NULL OR event_id = %d)
             AND (start_date IS NULL OR start_date <= NOW())
             AND (expiry_date IS NULL OR expiry_date >= NOW())
             AND (usage_limit IS NULL OR usage_count < usage_limit)",
            $code, $event_id
        ));

        if (!$coupon) {
            return ['valid' => false, 'discount' => 0];
        }

        // Check ticket restriction
        if ($coupon->ticket_ids) {
            $allowed_tickets = json_decode($coupon->ticket_ids, true);
            if (is_array($allowed_tickets) && !in_array($ticket_id, $allowed_tickets)) {
                return ['valid' => false, 'discount' => 0];
            }
        }

        // Check minimum amount
        if ($coupon->min_amount > 0 && $price < $coupon->min_amount) {
            return ['valid' => false, 'discount' => 0];
        }

        // Calculate discount
        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = ($price * $coupon->discount_value) / 100;
        } else {
            $discount = $coupon->discount_value;
        }

        // Apply max discount cap
        if ($coupon->max_discount && $discount > $coupon->max_discount) {
            $discount = $coupon->max_discount;
        }

        // Don't discount more than the price
        if ($discount > $price) {
            $discount = $price;
        }

        return ['valid' => true, 'discount' => $discount];
    }

    /**
     * Generate unique ticket code
     */
    private function generateTicketCode() {
        return strtoupper('TKT-' . bin2hex(random_bytes(4)));
    }
}
