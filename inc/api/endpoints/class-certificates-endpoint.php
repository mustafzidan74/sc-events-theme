<?php
/**
 * SC Events API - Certificates Endpoint
 *
 * Handles certificate verification and download for attendees
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Certificates_Endpoint extends SC_Base_Endpoint {

    private $certificates_table;
    private $templates_table;
    private $attendees_table;
    private $events_table;

    public function __construct() {
        global $wpdb;
        $this->certificates_table = $wpdb->prefix . 'sc_certificates';
        $this->templates_table = $wpdb->prefix . 'sc_certificate_templates';
        $this->attendees_table = $wpdb->prefix . 'sc_attendees';
        $this->events_table = $wpdb->prefix . 'sc_events';
    }

    /**
     * GET /certificates/verify/{code} - Verify certificate by code (public)
     */
    public function verify() {
        global $wpdb;

        $code = $this->param('code');

        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, a.name as attendee_name, a.email as attendee_email,
                    e.title as event_title, e.start_date, e.end_date, e.venue_name
             FROM {$this->certificates_table} c
             JOIN {$this->attendees_table} a ON c.attendee_id = a.id
             JOIN {$this->events_table} e ON c.event_id = e.id
             WHERE c.verification_code = %s OR c.certificate_number = %s",
            $code, $code
        ));

        if (!$cert) {
            SC_API_Response::success([
                'valid' => false,
                'message' => 'الشهادة غير موجودة'
            ]);
            return;
        }

        if ($cert->status === 'revoked') {
            SC_API_Response::success([
                'valid' => false,
                'message' => 'الشهادة ملغاة',
                'revoked_at' => $cert->revoked_at
            ]);
            return;
        }

        SC_API_Response::success([
            'valid' => true,
            'certificate_number' => $cert->certificate_number,
            'verification_code' => $cert->verification_code,
            'attendee_name' => $cert->attendee_name,
            'event' => [
                'title' => $cert->event_title,
                'start_date' => $cert->start_date,
                'end_date' => $cert->end_date,
                'venue_name' => $cert->venue_name
            ],
            'issued_at' => $cert->issued_at,
            'status' => $cert->status
        ], 'الشهادة صالحة');
    }

    /**
     * GET /certificates/check - Check if attendee can get certificate
     */
    public function check() {
        global $wpdb;

        $ticket_code = $this->query('ticket_code');
        $email = $this->query('email');
        $event_id = (int) $this->query('event_id');

        if (!$ticket_code && !$email) {
            SC_API_Response::error('يجب تقديم كود التذكرة أو البريد الإلكتروني', 400);
        }

        // Find the attendee
        $where = "a.status = 'active' AND a.payment_status = 'success'";
        $params = [];

        if ($ticket_code) {
            $where .= " AND a.ticket_code = %s";
            $params[] = $ticket_code;
        } elseif ($email && $event_id) {
            $where .= " AND a.email = %s AND a.event_id = %d";
            $params[] = sanitize_email($email);
            $params[] = $event_id;
        } else {
            SC_API_Response::error('يجب تقديم الفعالية مع البريد الإلكتروني', 400);
        }

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, e.title as event_title, e.enable_certificates,
                    e.certificate_require_checkin, e.certificate_require_event_ended,
                    e.end_date
             FROM {$this->attendees_table} a
             JOIN {$this->events_table} e ON a.event_id = e.id
             WHERE {$where}",
            ...$params
        ));

        if (!$attendee) {
            SC_API_Response::notFound('الحضور غير موجود أو غير مؤهل للشهادة');
        }

        // Check if event has certificates enabled
        if (!$attendee->enable_certificates) {
            SC_API_Response::success([
                'eligible' => false,
                'reason' => 'الفعالية لا تقدم شهادات'
            ]);
            return;
        }

        // Check if check-in is required
        if ($attendee->certificate_require_checkin && !$attendee->checked_in) {
            SC_API_Response::success([
                'eligible' => false,
                'reason' => 'يجب تسجيل الحضور أولاً للحصول على الشهادة'
            ]);
            return;
        }

        // Check if event must end first
        if ($attendee->certificate_require_event_ended) {
            $event_end = strtotime($attendee->end_date);
            if ($event_end > time()) {
                SC_API_Response::success([
                    'eligible' => false,
                    'reason' => 'الشهادة ستكون متاحة بعد انتهاء الفعالية'
                ]);
                return;
            }
        }

        // Check if certificate already issued
        $existing_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->certificates_table}
             WHERE attendee_id = %d AND event_id = %d AND status != 'revoked'",
            $attendee->id, $attendee->event_id
        ));

        if ($existing_cert) {
            SC_API_Response::success([
                'eligible' => true,
                'has_certificate' => true,
                'certificate' => [
                    'certificate_number' => $existing_cert->certificate_number,
                    'verification_code' => $existing_cert->verification_code,
                    'issued_at' => $existing_cert->issued_at,
                    'download_url' => home_url('/certificate/' . $existing_cert->verification_code . '/download')
                ]
            ]);
            return;
        }

        // Eligible but not yet issued
        SC_API_Response::success([
            'eligible' => true,
            'has_certificate' => false,
            'attendee' => [
                'id' => (int) $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'ticket_code' => $attendee->ticket_code
            ],
            'event' => [
                'id' => (int) $attendee->event_id,
                'title' => $attendee->event_title
            ]
        ]);
    }

    /**
     * POST /certificates/request - Request certificate issuance
     */
    public function request() {
        global $wpdb;

        $this->validate([
            'ticket_code' => 'required|string'
        ]);

        $ticket_code = sanitize_text_field($this->input('ticket_code'));

        // Find the attendee
        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, e.title as event_title, e.enable_certificates,
                    e.certificate_template_id, e.auto_issue_certificate,
                    e.certificate_require_checkin, e.certificate_require_event_ended,
                    e.end_date, e.start_date
             FROM {$this->attendees_table} a
             JOIN {$this->events_table} e ON a.event_id = e.id
             WHERE a.ticket_code = %s AND a.status = 'active' AND a.payment_status = 'success'",
            $ticket_code
        ));

        if (!$attendee) {
            SC_API_Response::notFound('التذكرة غير موجودة أو غير صالحة');
        }

        if (!$attendee->enable_certificates) {
            SC_API_Response::error('الفعالية لا تقدم شهادات', 400);
        }

        if ($attendee->certificate_require_checkin && !$attendee->checked_in) {
            SC_API_Response::error('يجب تسجيل الحضور أولاً', 400);
        }

        if ($attendee->certificate_require_event_ended && strtotime($attendee->end_date) > time()) {
            SC_API_Response::error('الشهادة ستكون متاحة بعد انتهاء الفعالية', 400);
        }

        // Check if already issued
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->certificates_table}
             WHERE attendee_id = %d AND event_id = %d AND status != 'revoked'",
            $attendee->id, $attendee->event_id
        ));

        if ($existing) {
            SC_API_Response::success([
                'certificate_number' => $existing->certificate_number,
                'verification_code' => $existing->verification_code,
                'issued_at' => $existing->issued_at,
                'download_url' => home_url('/certificate/' . $existing->verification_code . '/download'),
                'view_url' => home_url('/certificate/' . $existing->verification_code)
            ], 'الشهادة موجودة مسبقاً');
            return;
        }

        // Issue new certificate
        $cert_number = $this->generateCertificateNumber();
        $verification_code = $this->generateVerificationCode();

        $data = [
            'certificate_number' => $cert_number,
            'verification_code' => $verification_code,
            'template_id' => $attendee->certificate_template_id ?: 1,
            'attendee_id' => $attendee->id,
            'event_id' => $attendee->event_id,
            'attendee_name' => $attendee->name,
            'event_title' => $attendee->event_title,
            'event_date' => $attendee->start_date,
            'status' => 'issued',
            'issued_by' => $this->userId() ?: 0,
            'issued_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->certificates_table, $data);

        if ($result === false) {
            SC_API_Response::error('فشل في إصدار الشهادة', 500);
        }

        SC_API_Response::created([
            'id' => $wpdb->insert_id,
            'certificate_number' => $cert_number,
            'verification_code' => $verification_code,
            'attendee_name' => $attendee->name,
            'event_title' => $attendee->event_title,
            'issued_at' => current_time('mysql'),
            'download_url' => home_url('/certificate/' . $verification_code . '/download'),
            'view_url' => home_url('/certificate/' . $verification_code)
        ], 'تم إصدار الشهادة بنجاح');
    }

    /**
     * GET /certificates/my - Get user's certificates
     */
    public function myCertificates() {
        global $wpdb;

        $user_id = $this->userId();
        $email = $this->query('email');

        if (!$user_id && !$email) {
            SC_API_Response::error('يجب تسجيل الدخول أو إدخال البريد الإلكتروني', 400);
        }

        if ($user_id) {
            $where = $wpdb->prepare("a.user_id = %d", $user_id);
        } else {
            $where = $wpdb->prepare("a.email = %s", sanitize_email($email));
        }

        $certificates = $wpdb->get_results(
            "SELECT c.*, e.title as event_title, e.start_date, e.end_date, e.venue_name
             FROM {$this->certificates_table} c
             JOIN {$this->attendees_table} a ON c.attendee_id = a.id
             JOIN {$this->events_table} e ON c.event_id = e.id
             WHERE {$where} AND c.status != 'revoked'
             ORDER BY c.issued_at DESC"
        );

        $data = array_map(function($cert) {
            return [
                'id' => (int) $cert->id,
                'certificate_number' => $cert->certificate_number,
                'verification_code' => $cert->verification_code,
                'attendee_name' => $cert->attendee_name,
                'event' => [
                    'id' => (int) $cert->event_id,
                    'title' => $cert->event_title,
                    'start_date' => $cert->start_date,
                    'end_date' => $cert->end_date,
                    'venue_name' => $cert->venue_name
                ],
                'issued_at' => $cert->issued_at,
                'download_count' => (int) $cert->download_count,
                'download_url' => home_url('/certificate/' . $cert->verification_code . '/download'),
                'view_url' => home_url('/certificate/' . $cert->verification_code)
            ];
        }, $certificates);

        SC_API_Response::success($data);
    }

    /**
     * Generate unique certificate number
     */
    private function generateCertificateNumber() {
        $year = date('Y');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return "CERT-{$year}-{$random}";
    }

    /**
     * Generate verification code
     */
    private function generateVerificationCode() {
        return strtoupper(bin2hex(random_bytes(8)));
    }
}
