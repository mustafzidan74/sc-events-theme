<?php
/**
 * SC Events API - Booths Endpoint
 *
 * Handles booths, booth types, and booth bookings
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Booths_Endpoint extends SC_Base_Endpoint {

    private $booths_table;
    private $booth_types_table;
    private $booth_bookings_table;
    private $events_table;
    private $companies_table;

    public function __construct() {
        global $wpdb;
        $this->booths_table = $wpdb->prefix . 'sc_booths';
        $this->booth_types_table = $wpdb->prefix . 'sc_booth_types';
        $this->booth_bookings_table = $wpdb->prefix . 'sc_booth_bookings';
        $this->events_table = $wpdb->prefix . 'sc_events';
        $this->companies_table = $wpdb->prefix . 'sc_company_attendees';
    }

    // =============================================
    // BOOTHS
    // =============================================

    /**
     * GET /booths - List all booths
     */
    public function index() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if (!$event_id) {
            SC_API_Response::error('يجب تحديد الفعالية', 400);
        }

        $status = $this->query('status'); // available, reserved, booked

        $where = $wpdb->prepare("b.event_id = %d", $event_id);

        if ($status) {
            $where .= $wpdb->prepare(" AND b.status = %s", $status);
        }

        $booths = $wpdb->get_results(
            "SELECT b.*, bt.name as type_name, bt.base_price, bt.size_code,
                    c.company_name, c.company_code
             FROM {$this->booths_table} b
             LEFT JOIN {$this->booth_types_table} bt ON b.booth_type_id = bt.id
             LEFT JOIN {$this->companies_table} c ON b.current_company_id = c.id
             WHERE {$where}
             ORDER BY b.booth_number ASC"
        );

        $data = array_map(function($booth) {
            return [
                'id' => (int) $booth->id,
                'booth_number' => $booth->booth_number,
                'booth_name' => $booth->booth_name,
                'booth_name_ar' => $booth->booth_name_ar,
                'status' => $booth->status,
                'is_featured' => (bool) $booth->is_featured,

                // Position
                'position_x' => (int) $booth->position_x,
                'position_y' => (int) $booth->position_y,
                'floor_level' => (int) $booth->floor_level,

                // Type
                'type' => [
                    'id' => (int) $booth->booth_type_id,
                    'name' => $booth->type_name,
                    'size_code' => $booth->size_code,
                    'base_price' => (float) $booth->base_price
                ],

                // Price (custom or from type)
                'price' => $booth->custom_price ? (float) $booth->custom_price : (float) $booth->base_price,

                // Dimensions
                'width' => $booth->custom_width ? (float) $booth->custom_width : null,
                'depth' => $booth->custom_depth ? (float) $booth->custom_depth : null,

                // Amenities
                'has_electricity' => (bool) $booth->has_electricity,
                'has_water' => (bool) $booth->has_water,
                'has_wifi' => (bool) $booth->has_wifi,
                'power_outlets' => (int) $booth->power_outlets,

                // Current occupant
                'current_company' => $booth->current_company_id ? [
                    'id' => (int) $booth->current_company_id,
                    'name' => $booth->company_name,
                    'code' => $booth->company_code
                ] : null
            ];
        }, $booths);

        SC_API_Response::success($data);
    }

    /**
     * GET /booths/{id} - Get single booth
     */
    public function show() {
        global $wpdb;

        $id = (int) $this->param('id');

        $booth = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, bt.name as type_name, bt.name_ar as type_name_ar,
                    bt.base_price, bt.size_code, bt.description as type_description,
                    bt.inclusions, bt.width_meters, bt.depth_meters,
                    e.title as event_title
             FROM {$this->booths_table} b
             LEFT JOIN {$this->booth_types_table} bt ON b.booth_type_id = bt.id
             LEFT JOIN {$this->events_table} e ON b.event_id = e.id
             WHERE b.id = %d",
            $id
        ));

        if (!$booth) {
            SC_API_Response::notFound('البوث غير موجود');
        }

        SC_API_Response::success([
            'id' => (int) $booth->id,
            'booth_number' => $booth->booth_number,
            'booth_name' => $booth->booth_name,
            'booth_name_ar' => $booth->booth_name_ar,
            'status' => $booth->status,
            'is_featured' => (bool) $booth->is_featured,

            // Event
            'event_id' => (int) $booth->event_id,
            'event_title' => $booth->event_title,

            // Position
            'position_x' => (int) $booth->position_x,
            'position_y' => (int) $booth->position_y,
            'rotation' => (int) $booth->rotation,
            'floor_level' => (int) $booth->floor_level,

            // Type
            'type' => [
                'id' => (int) $booth->booth_type_id,
                'name' => $booth->type_name,
                'name_ar' => $booth->type_name_ar,
                'description' => $booth->type_description,
                'size_code' => $booth->size_code,
                'base_price' => (float) $booth->base_price,
                'width_meters' => (float) $booth->width_meters,
                'depth_meters' => (float) $booth->depth_meters,
                'inclusions' => json_decode($booth->inclusions, true) ?: []
            ],

            // Dimensions
            'width' => $booth->custom_width ? (float) $booth->custom_width : (float) $booth->width_meters,
            'depth' => $booth->custom_depth ? (float) $booth->custom_depth : (float) $booth->depth_meters,
            'price' => $booth->custom_price ? (float) $booth->custom_price : (float) $booth->base_price,

            // Amenities
            'has_electricity' => (bool) $booth->has_electricity,
            'has_water' => (bool) $booth->has_water,
            'has_wifi' => (bool) $booth->has_wifi,
            'power_outlets' => (int) $booth->power_outlets,
            'max_power_kw' => (float) $booth->max_power_kw,
            'special_features' => json_decode($booth->special_features, true) ?: [],

            'notes' => $booth->notes
        ]);
    }

    // =============================================
    // BOOTH TYPES
    // =============================================

    /**
     * GET /booth-types - List booth types for an event
     */
    public function types() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if (!$event_id) {
            SC_API_Response::error('يجب تحديد الفعالية', 400);
        }

        $types = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->booth_types_table}
             WHERE event_id = %d AND is_active = 1
             ORDER BY sort_order ASC",
            $event_id
        ));

        $data = array_map(function($type) {
            return [
                'id' => (int) $type->id,
                'name' => $type->name,
                'name_ar' => $type->name_ar,
                'slug' => $type->slug,
                'description' => $type->description,
                'description_ar' => $type->description_ar,

                // Dimensions
                'size_code' => $type->size_code,
                'width_meters' => (float) $type->width_meters,
                'depth_meters' => (float) $type->depth_meters,
                'area_sqm' => (float) $type->area_sqm,

                // Category
                'booth_category' => $type->booth_category,

                // Pricing
                'base_price' => (float) $type->base_price,
                'deposit_amount' => (float) $type->deposit_amount,
                'deposit_percentage' => (float) $type->deposit_percentage,

                // Package
                'inclusions' => json_decode($type->inclusions, true) ?: [],

                // Availability
                'total_quantity' => (int) $type->total_quantity,
                'available_quantity' => (int) $type->available_quantity,

                // Visual
                'color' => $type->color,
                'icon' => $type->icon,
                'image' => $type->image ? wp_get_attachment_url($type->image) : null
            ];
        }, $types);

        SC_API_Response::success($data);
    }

    /**
     * GET /booth-types/{id} - Get single booth type
     */
    public function showType() {
        global $wpdb;

        $id = (int) $this->param('id');

        $type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->booth_types_table} WHERE id = %d",
            $id
        ));

        if (!$type) {
            SC_API_Response::notFound('نوع البوث غير موجود');
        }

        SC_API_Response::success([
            'id' => (int) $type->id,
            'event_id' => (int) $type->event_id,
            'name' => $type->name,
            'name_ar' => $type->name_ar,
            'slug' => $type->slug,
            'description' => $type->description,
            'description_ar' => $type->description_ar,
            'size_code' => $type->size_code,
            'width_meters' => (float) $type->width_meters,
            'depth_meters' => (float) $type->depth_meters,
            'area_sqm' => (float) $type->area_sqm,
            'booth_category' => $type->booth_category,
            'base_price' => (float) $type->base_price,
            'deposit_amount' => (float) $type->deposit_amount,
            'deposit_percentage' => (float) $type->deposit_percentage,
            'price_per_sqm' => (float) $type->price_per_sqm,
            'inclusions' => json_decode($type->inclusions, true) ?: [],
            'total_quantity' => (int) $type->total_quantity,
            'available_quantity' => (int) $type->available_quantity,
            'color' => $type->color,
            'icon' => $type->icon,
            'image' => $type->image ? wp_get_attachment_url($type->image) : null,
            'is_active' => (bool) $type->is_active
        ]);
    }

    // =============================================
    // BOOTH BOOKINGS
    // =============================================

    /**
     * GET /booth-bookings - List bookings
     */
    public function bookings() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');
        $company_code = $this->query('company_code');

        $where = "1=1";
        $params = [];

        if ($event_id) {
            $where .= " AND bb.event_id = %d";
            $params[] = $event_id;
        }

        if ($company_code) {
            $where .= " AND c.company_code = %s";
            $params[] = $company_code;
        }

        $sql = "SELECT bb.*, b.booth_number, b.booth_name, c.company_name, c.company_code
                FROM {$this->booth_bookings_table} bb
                LEFT JOIN {$this->booths_table} b ON bb.booth_id = b.id
                LEFT JOIN {$this->companies_table} c ON bb.company_attendee_id = c.id
                WHERE {$where}
                ORDER BY bb.booking_date DESC";

        if (!empty($params)) {
            $bookings = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $bookings = $wpdb->get_results($sql);
        }

        $data = array_map(function($booking) {
            return [
                'id' => (int) $booking->id,
                'booking_ref' => $booking->booking_ref,

                // Booth
                'booth' => [
                    'id' => (int) $booking->booth_id,
                    'number' => $booking->booth_number,
                    'name' => $booking->booth_name
                ],

                // Company
                'company' => [
                    'id' => (int) $booking->company_attendee_id,
                    'name' => $booking->company_name,
                    'code' => $booking->company_code
                ],

                // Dates
                'booking_date' => $booking->booking_date,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,

                // Pricing
                'base_price' => (float) $booking->base_price,
                'extras_price' => (float) $booking->extras_price,
                'discount_amount' => (float) $booking->discount_amount,
                'tax_amount' => (float) $booking->tax_amount,
                'total_amount' => (float) $booking->total_amount,

                // Payment
                'deposit_required' => (float) $booking->deposit_required,
                'deposit_paid' => (float) $booking->deposit_paid,
                'balance_due' => (float) $booking->balance_due,
                'payment_status' => $booking->payment_status,

                // Status
                'status' => $booking->status,
                'contract_signed' => (bool) $booking->contract_signed,
                'checked_in' => (bool) $booking->checked_in
            ];
        }, $bookings);

        SC_API_Response::success($data);
    }

    /**
     * GET /booth-bookings/{id} - Get single booking
     */
    public function showBooking() {
        global $wpdb;

        $id = (int) $this->param('id');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT bb.*, b.booth_number, b.booth_name, bt.name as booth_type_name,
                    c.company_name, c.company_code, c.contact_name, c.contact_email,
                    e.title as event_title
             FROM {$this->booth_bookings_table} bb
             LEFT JOIN {$this->booths_table} b ON bb.booth_id = b.id
             LEFT JOIN {$this->booth_types_table} bt ON b.booth_type_id = bt.id
             LEFT JOIN {$this->companies_table} c ON bb.company_attendee_id = c.id
             LEFT JOIN {$this->events_table} e ON bb.event_id = e.id
             WHERE bb.id = %d",
            $id
        ));

        if (!$booking) {
            SC_API_Response::notFound('الحجز غير موجود');
        }

        SC_API_Response::success([
            'id' => (int) $booking->id,
            'booking_ref' => $booking->booking_ref,
            'event_id' => (int) $booking->event_id,
            'event_title' => $booking->event_title,

            // Booth
            'booth' => [
                'id' => (int) $booking->booth_id,
                'number' => $booking->booth_number,
                'name' => $booking->booth_name,
                'type' => $booking->booth_type_name
            ],

            // Company
            'company' => [
                'id' => (int) $booking->company_attendee_id,
                'name' => $booking->company_name,
                'code' => $booking->company_code,
                'contact_name' => $booking->contact_name,
                'contact_email' => $booking->contact_email
            ],

            // Dates
            'booking_date' => $booking->booking_date,
            'start_date' => $booking->start_date,
            'end_date' => $booking->end_date,
            'setup_date' => $booking->setup_date,
            'teardown_date' => $booking->teardown_date,

            // Pricing
            'base_price' => (float) $booking->base_price,
            'extras_price' => (float) $booking->extras_price,
            'discount_amount' => (float) $booking->discount_amount,
            'tax_amount' => (float) $booking->tax_amount,
            'total_amount' => (float) $booking->total_amount,

            // Payment
            'deposit_required' => (float) $booking->deposit_required,
            'deposit_paid' => (float) $booking->deposit_paid,
            'deposit_paid_at' => $booking->deposit_paid_at,
            'balance_due' => (float) $booking->balance_due,
            'balance_paid' => (float) $booking->balance_paid,
            'balance_due_date' => $booking->balance_due_date,
            'payment_status' => $booking->payment_status,

            // Contract
            'contract_signed' => (bool) $booking->contract_signed,
            'contract_signed_at' => $booking->contract_signed_at,
            'contract_signer_name' => $booking->contract_signer_name,
            'terms_accepted' => (bool) $booking->terms_accepted,

            // Saudi info
            'commercial_registry_no' => $booking->commercial_registry_no,
            'vat_number' => $booking->vat_number,

            // Extras
            'selected_extras' => json_decode($booking->selected_extras, true) ?: [],
            'special_requests' => $booking->special_requests,

            // Status
            'status' => $booking->status,
            'checked_in' => (bool) $booking->checked_in,
            'checked_in_at' => $booking->checked_in_at,
            'checked_out' => (bool) $booking->checked_out,
            'checked_out_at' => $booking->checked_out_at
        ]);
    }

    /**
     * POST /booth-bookings - Create a new booking
     */
    public function createBooking() {
        global $wpdb;

        $this->validate([
            'event_id' => 'required|integer',
            'booth_id' => 'required|integer',
            'company_code' => 'required|string'
        ]);

        $event_id = (int) $this->input('event_id');
        $booth_id = (int) $this->input('booth_id');
        $company_code = sanitize_text_field($this->input('company_code'));

        // Verify event exists
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->events_table} WHERE id = %d AND status = 'publish'",
            $event_id
        ));

        if (!$event) {
            SC_API_Response::notFound('الفعالية غير موجودة');
        }

        // Verify booth exists and is available
        $booth = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, bt.base_price, bt.deposit_amount, bt.deposit_percentage
             FROM {$this->booths_table} b
             LEFT JOIN {$this->booth_types_table} bt ON b.booth_type_id = bt.id
             WHERE b.id = %d AND b.event_id = %d",
            $booth_id, $event_id
        ));

        if (!$booth) {
            SC_API_Response::notFound('البوث غير موجود');
        }

        if ($booth->status !== 'available') {
            SC_API_Response::error('البوث غير متاح للحجز', 400);
        }

        // Verify company exists
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->companies_table} WHERE company_code = %s AND event_id = %d",
            $company_code, $event_id
        ));

        if (!$company) {
            SC_API_Response::notFound('الشركة غير مسجلة في هذه الفعالية');
        }

        // Calculate pricing
        $base_price = $booth->custom_price ?: $booth->base_price;
        $deposit_amount = $booth->deposit_amount ?: ($base_price * ($booth->deposit_percentage / 100));
        $tax_amount = $base_price * 0.15; // 15% VAT
        $total_amount = $base_price + $tax_amount;

        // Generate booking reference
        $booking_ref = 'BK-' . strtoupper(bin2hex(random_bytes(6)));

        // Create booking
        $data = [
            'event_id' => $event_id,
            'booth_id' => $booth_id,
            'company_attendee_id' => $company->id,
            'booking_ref' => $booking_ref,
            'booking_date' => current_time('mysql'),
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'base_price' => $base_price,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'deposit_required' => $deposit_amount,
            'balance_due' => $total_amount,
            'payment_status' => 'pending',
            'status' => 'pending',
            'special_requests' => sanitize_textarea_field($this->input('special_requests')),
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->booth_bookings_table, $data);

        if ($result === false) {
            SC_API_Response::error('فشل في إنشاء الحجز', 500);
        }

        $booking_id = $wpdb->insert_id;

        // Update booth status to reserved
        $wpdb->update(
            $this->booths_table,
            [
                'status' => 'reserved',
                'current_company_id' => $company->id,
                'current_booking_id' => $booking_id
            ],
            ['id' => $booth_id]
        );

        // Update company with booth number
        $wpdb->update(
            $this->companies_table,
            ['booth_number' => $booth->booth_number],
            ['id' => $company->id]
        );

        SC_API_Response::created([
            'id' => $booking_id,
            'booking_ref' => $booking_ref,
            'booth_number' => $booth->booth_number,
            'company_name' => $company->company_name,
            'total_amount' => $total_amount,
            'deposit_required' => $deposit_amount,
            'status' => 'pending',
            'payment_status' => 'pending'
        ], 'تم إنشاء الحجز بنجاح');
    }
}
