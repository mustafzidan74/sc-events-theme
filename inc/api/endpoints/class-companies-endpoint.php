<?php
/**
 * SC Events API - Companies Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Companies_Endpoint extends SC_Base_Endpoint {

    private $table;
    private $events_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sc_company_attendees';
        $this->events_table = $wpdb->prefix . 'sc_events';
    }

    /**
     * POST /companies/register - Register as company attendee
     */
    public function register() {
        global $wpdb;

        $this->validate([
            'event_id' => 'required|integer',
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string|max:50'
        ]);

        $event_id = (int) $this->input('event_id');

        // Check event exists and is published
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->events_table} WHERE id = %d AND status = 'publish'",
            $event_id
        ));

        if (!$event) {
            SC_API_Response::notFound('الفعالية غير موجودة أو غير متاحة');
        }

        // Check for duplicate registration
        $email = sanitize_email($this->input('contact_email'));
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE event_id = %d AND contact_email = %s AND status != 'cancelled'",
            $event_id, $email
        ));

        if ($existing) {
            SC_API_Response::error('هذه الشركة مسجلة مسبقاً في هذه الفعالية', 400);
        }

        // Generate company code
        $company_code = $this->generateCompanyCode();

        // Prepare company data
        $data = [
            'event_id' => $event_id,
            'ticket_id' => $this->input('ticket_id') ? (int) $this->input('ticket_id') : null,

            // Company Information
            'company_name' => sanitize_text_field($this->input('company_name')),
            'company_name_ar' => sanitize_text_field($this->input('company_name_ar')),
            'industry' => sanitize_text_field($this->input('industry')),
            'company_size' => $this->input('company_size'),
            'website' => esc_url_raw($this->input('website')),

            // Contact Person
            'contact_name' => sanitize_text_field($this->input('contact_name')),
            'contact_title' => sanitize_text_field($this->input('contact_title')),
            'contact_email' => $email,
            'contact_phone' => sanitize_text_field($this->input('contact_phone')),

            // Address
            'country' => sanitize_text_field($this->input('country')),
            'city' => sanitize_text_field($this->input('city')),
            'address' => sanitize_textarea_field($this->input('address')),

            // Booth/Sponsorship
            'booth_number' => sanitize_text_field($this->input('booth_number')),
            'sponsorship_level' => sanitize_text_field($this->input('sponsorship_level')),

            // Registration
            'company_code' => $company_code,
            'payment_status' => 'pending',
            'status' => 'active',

            // Extra fields
            'extra_fields' => $this->input('extra_fields') ? json_encode($this->input('extra_fields')) : null,
            'social_media' => $this->input('social_media') ? json_encode($this->input('social_media')) : null,
            'products' => $this->input('products') ? json_encode($this->input('products')) : null,

            'created_at' => current_time('mysql')
        ];

        // Insert company
        $result = $wpdb->insert($this->table, $data);

        if ($result === false) {
            SC_API_Response::error('فشل في التسجيل، حاول مرة أخرى', 500);
        }

        $company_id = $wpdb->insert_id;

        // Get the created company
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $company_id
        ));

        SC_API_Response::created([
            'id' => (int) $company->id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'company_name_ar' => $company->company_name_ar,
            'contact_name' => $company->contact_name,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
            'event_id' => (int) $company->event_id,
            'status' => $company->status,
            'payment_status' => $company->payment_status
        ], 'تم تسجيل الشركة بنجاح');
    }

    /**
     * GET /companies/{company_code} - Get company by code
     */
    public function show() {
        global $wpdb;

        $company_code = $this->param('company_code');

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, e.title as event_title, e.start_date, e.end_date, e.venue_name
             FROM {$this->table} c
             JOIN {$this->events_table} e ON c.event_id = e.id
             WHERE c.company_code = %s",
            $company_code
        ));

        if (!$company) {
            SC_API_Response::notFound('الشركة غير موجودة');
        }

        SC_API_Response::success([
            'id' => (int) $company->id,
            'company_code' => $company->company_code,

            // Company Info
            'company_name' => $company->company_name,
            'company_name_ar' => $company->company_name_ar,
            'company_logo' => $company->company_logo ? wp_get_attachment_url($company->company_logo) : null,
            'industry' => $company->industry,
            'company_size' => $company->company_size,
            'website' => $company->website,

            // Contact
            'contact_name' => $company->contact_name,
            'contact_title' => $company->contact_title,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,

            // Address
            'country' => $company->country,
            'city' => $company->city,
            'address' => $company->address,

            // Booth
            'booth_number' => $company->booth_number,
            'sponsorship_level' => $company->sponsorship_level,

            // Status
            'status' => $company->status,
            'payment_status' => $company->payment_status,
            'checked_in' => (bool) $company->checked_in,
            'checked_in_at' => $company->checked_in_at,

            // Event
            'event' => [
                'id' => (int) $company->event_id,
                'title' => $company->event_title,
                'start_date' => $company->start_date,
                'end_date' => $company->end_date,
                'venue_name' => $company->venue_name
            ],

            // Extra
            'social_media' => json_decode($company->social_media, true) ?: [],
            'products' => json_decode($company->products, true) ?: [],
            'extra_fields' => json_decode($company->extra_fields, true) ?: []
        ]);
    }

    /**
     * GET /companies - List companies (for an event)
     */
    public function index() {
        global $wpdb;

        $event_id = (int) $this->query('event_id');

        if (!$event_id) {
            SC_API_Response::error('يجب تحديد الفعالية', 400);
        }

        $page = (int) ($this->query('page') ?: 1);
        $per_page = min((int) ($this->query('per_page') ?: 20), 100);
        $offset = ($page - 1) * $per_page;

        // Count total
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE event_id = %d AND status = 'active'",
            $event_id
        ));

        // Get companies
        $companies = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE event_id = %d AND status = 'active'
             ORDER BY company_name ASC
             LIMIT %d OFFSET %d",
            $event_id, $per_page, $offset
        ));

        $data = array_map(function($c) {
            return [
                'id' => (int) $c->id,
                'company_code' => $c->company_code,
                'company_name' => $c->company_name,
                'company_name_ar' => $c->company_name_ar,
                'company_logo' => $c->company_logo ? wp_get_attachment_url($c->company_logo) : null,
                'industry' => $c->industry,
                'contact_name' => $c->contact_name,
                'contact_email' => $c->contact_email,
                'booth_number' => $c->booth_number,
                'sponsorship_level' => $c->sponsorship_level,
                'checked_in' => (bool) $c->checked_in
            ];
        }, $companies);

        SC_API_Response::paginated($data, $total, $page, $per_page);
    }

    /**
     * PUT /companies/{company_code} - Update company details
     */
    public function update() {
        global $wpdb;

        $company_code = $this->param('company_code');

        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE company_code = %s",
            $company_code
        ));

        if (!$company) {
            SC_API_Response::notFound('الشركة غير موجودة');
        }

        // Prepare update data
        $update_data = [];
        $allowed_fields = [
            'company_name', 'company_name_ar', 'industry', 'company_size', 'website',
            'contact_name', 'contact_title', 'contact_email', 'contact_phone',
            'country', 'city', 'address', 'booth_number', 'sponsorship_level'
        ];

        foreach ($allowed_fields as $field) {
            $value = $this->input($field);
            if ($value !== null) {
                if ($field === 'contact_email') {
                    $update_data[$field] = sanitize_email($value);
                } elseif ($field === 'website') {
                    $update_data[$field] = esc_url_raw($value);
                } elseif ($field === 'address') {
                    $update_data[$field] = sanitize_textarea_field($value);
                } else {
                    $update_data[$field] = sanitize_text_field($value);
                }
            }
        }

        // Handle JSON fields
        if ($this->input('social_media') !== null) {
            $update_data['social_media'] = json_encode($this->input('social_media'));
        }
        if ($this->input('products') !== null) {
            $update_data['products'] = json_encode($this->input('products'));
        }
        if ($this->input('extra_fields') !== null) {
            $update_data['extra_fields'] = json_encode($this->input('extra_fields'));
        }

        if (empty($update_data)) {
            SC_API_Response::error('لا توجد بيانات للتحديث', 400);
        }

        $update_data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $company->id]
        );

        if ($result === false) {
            SC_API_Response::error('فشل في التحديث', 500);
        }

        // Get updated company
        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $company->id
        ));

        SC_API_Response::success([
            'id' => (int) $updated->id,
            'company_code' => $updated->company_code,
            'company_name' => $updated->company_name,
            'contact_email' => $updated->contact_email,
            'status' => $updated->status
        ], 'تم تحديث بيانات الشركة');
    }

    /**
     * Generate unique company code
     */
    private function generateCompanyCode() {
        return strtoupper('COMP-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
    }
}
