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

        // Same rules as registering on the website (public-ajax-handlers.php): a coupon-only ticket
        // needs a 100% coupon; workshop seats take workshop coupons, congress tickets the others;
        // a workshop ticket makes a workshop seat; the ticket is sent on WhatsApp/email.
        $this->validate([
            'event_id' => 'required|integer',
            'ticket_id' => 'required|integer',
            'name' => 'string|max:255',
            'email' => 'email'
            // phone: no max rule, the validator reads a numeric phone as a number (01012345678 > 50).
        ]);

        $event_id = (int) $this->input('event_id');
        $ticket_id = (int) $this->input('ticket_id');
        $user = $this->userId() ? get_userdata($this->userId()) : null;

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->events_table} WHERE id = %d AND status = 'publish'",
            $event_id
        ));
        if (!$event) {
            SC_API_Response::notFound('الفعالية غير موجودة أو غير متاحة');
        }
        if ($event->registration_deadline && current_time('mysql') > $event->registration_deadline) {
            SC_API_Response::error('انتهى موعد التسجيل لهذه الفعالية', 400);
        }

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tickets_table} WHERE id = %d AND event_id = %d AND is_active = 1",
            $ticket_id, $event_id
        ));
        if (!$ticket) {
            SC_API_Response::notFound('التذكرة غير موجودة');
        }
        // A quantity of 0 means no limit.
        if ((int) $ticket->quantity > 0 && (int) $ticket->sold >= (int) $ticket->quantity) {
            SC_API_Response::error('نفدت التذاكر المتاحة', 400, null, 'SOLD_OUT');
        }
        $workshop_id = (int) $ticket->workshop_id;

        $name  = sanitize_text_field((string) ($this->input('name') ?: ($user ? $user->display_name : '')));
        $email = sanitize_email((string) ($this->input('email') ?: ($user ? $user->user_email : '')));
        $phone = mb_substr(sanitize_text_field((string) ($this->input('phone') ?: ($user ? get_user_meta($user->ID, 'phone', true) : ''))), 0, 50);
        if ($name === '' || !is_email($email)) {
            SC_API_Response::validationError(['name' => ['الاسم والبريد الإلكتروني مطلوبين']]);
        }

        // One congress registration per email, and one seat per workshop.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE event_id = %d AND email = %s AND status != 'cancelled' AND "
            . ($workshop_id ? 'workshop_id = %d' : '(workshop_id IS NULL OR workshop_id = 0) AND %d = 0'),
            $event_id, $email, $workshop_id
        ));
        if ($existing) {
            SC_API_Response::error($workshop_id ? 'مسجل مسبقاً في هذه الورشة' : 'هذا البريد الإلكتروني مسجل مسبقاً في هذه الفعالية', 400, null, 'ALREADY_REGISTERED');
        }

        // Coupons: required on coupon-only tickets (price 0 + coupons on), optional discount otherwise.
        $price = (float) $ticket->price;
        $coupon_code = trim((string) $this->input('coupon_code', ''));
        $coupon = null;
        $coupon_only = $price <= 0 && !empty($ticket->enable_coupons);
        if ($coupon_only || $coupon_code !== '') {
            $check = sc_coupon_for_ticket($coupon_code, $ticket);
            if (is_wp_error($check)) {
                SC_API_Response::error($check->get_error_message(), 400, null, strtoupper($check->get_error_code()));
            }
            if ($coupon_only && !$check['is_free']) {
                SC_API_Response::error('هذه التذكرة تحتاج كوبون خصم 100%', 400, null, 'COUPON_NOT_FULL');
            }
            $coupon = $check;
        }
        $discount = 0;
        if ($coupon && $price > 0) {
            $discount = $coupon['discount_type'] === 'percentage' ? $price * $coupon['discount_value'] / 100 : $coupon['discount_value'];
            $discount = min($price, max(0, $discount));
        }
        $to_pay = max(0, $price - $discount);
        $paid = $to_pay <= 0;

        $extra = [];
        $input_extra = $this->input('extra_fields');
        if (is_array($input_extra)) {
            foreach ($input_extra as $label => $value) {
                if (!is_array($value)) {
                    $extra[] = ['label' => (string) $label, 'value' => (string) $value];
                }
            }
        }

        $attendee_id = sc_create_attendee([
            'event_id'       => $event_id,
            'workshop_id'    => $workshop_id,
            'ticket_id'      => $ticket_id,
            'ticket_name'    => $ticket->name,
            'ticket_type'    => !empty($ticket->ticket_type) ? $ticket->ticket_type : 'general',
            'user_id'        => $user ? $user->ID : 0,
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'payment_status' => $paid ? 'success' : 'pending',
            'payment_method' => $coupon ? 'coupon' : ($price > 0 ? 'online' : 'free'),
            'amount_paid'    => 0,
            'coupon_code'    => $coupon ? $coupon['coupon']->post_title : '',
            'extra_fields'   => $extra,
        ]);
        if (!$attendee_id) {
            SC_API_Response::error('فشل في التسجيل، حاول مرة أخرى', 500);
        }
        if ($coupon) {
            sc_coupon_use($coupon['coupon']->ID);
        }
        if ($paid && function_exists('sc_public_send_ticket_email')) {
            sc_public_send_ticket_email($attendee_id);
        }

        $attendee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $attendee_id));
        SC_API_Response::created([
            'id' => (int) $attendee->id,
            'ticket_code' => $attendee->ticket_code,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'event_id' => (int) $attendee->event_id,
            'workshop_id' => $workshop_id ?: null,
            'ticket_name' => $attendee->ticket_name,
            'ticket_price' => $price,
            'amount_to_pay' => round($to_pay, 2),
            'payment_status' => $attendee->payment_status,
            'coupon_code' => $attendee->coupon_code,
            'coupon_discount' => round($discount, 2),
            'status' => $attendee->status,
            'extra_fields' => json_decode((string) $attendee->extra_fields, true) ?: [],
            'requires_payment' => !$paid
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
     * Generate unique ticket code
     */
    private function generateTicketCode() {
        return strtoupper('TKT-' . bin2hex(random_bytes(4)));
    }
}
