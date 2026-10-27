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
    private $workshops_table;

    public function __construct() {
        global $wpdb;
        $this->certificates_table = $wpdb->prefix . 'sc_certificates';
        $this->templates_table = $wpdb->prefix . 'sc_certificate_templates';
        $this->attendees_table = $wpdb->prefix . 'sc_attendees';
        $this->events_table = $wpdb->prefix . 'sc_events';
        $this->workshops_table = $wpdb->prefix . 'sc_workshops';
    }

    /**
     * GET /certificates/verify/{code} - Verify certificate by code (public)
     */
    public function verify() {
        global $wpdb;

        $code = $this->param('code');

        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, a.name as attendee_name, a.email as attendee_email,
                    e.title as event_title, e.start_date, e.end_date, e.venue_name, w.title as workshop_title
             FROM {$this->certificates_table} c
             JOIN {$this->attendees_table} a ON c.attendee_id = a.id
             JOIN {$this->events_table} e ON c.event_id = e.id
             LEFT JOIN {$this->workshops_table} w ON w.id = c.workshop_id
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
            'workshop' => $this->certificateWorkshop($cert),
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
        $workshop_id = (int) $this->query('workshop_id');

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
            // By email: the congress ticket, or the ticket for workshop_id.
            $where .= " AND a.email = %s AND a.event_id = %d AND " . ($workshop_id ? 'a.workshop_id = %d' : '(a.workshop_id IS NULL OR a.workshop_id = 0)');
            $params[] = sanitize_email($email);
            $params[] = $event_id;
            if ($workshop_id) {
                $params[] = $workshop_id;
            }
        } else {
            SC_API_Response::error('يجب تقديم الفعالية مع البريد الإلكتروني', 400);
        }

        $attendee = $wpdb->get_row($wpdb->prepare(
            "SELECT a.* FROM {$this->attendees_table} a WHERE {$where} LIMIT 1",
            ...$params
        ));

        if (!$attendee) {
            SC_API_Response::notFound('الحضور غير موجود أو غير مؤهل للشهادة');
        }

        // A workshop ticket gets its workshop's certificate, under the workshop's rules.
        $scope = sc_certs_scope_for_ticket($attendee);
        $problem = sc_certs_ticket_problem($attendee, $scope);
        if ($problem) {
            SC_API_Response::success([
                'eligible' => false,
                'reason' => $this->problemMessage($problem, $scope),
                'workshop' => $this->workshopInfo($scope)
            ]);
            return;
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
                ],
                'workshop' => $this->workshopInfo($scope)
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
                'title' => $scope['event_title']
            ],
            'workshop' => $this->workshopInfo($scope)
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
            "SELECT a.* FROM {$this->attendees_table} a
             WHERE a.ticket_code = %s AND a.status = 'active' AND a.payment_status = 'success'",
            $ticket_code
        ));

        if (!$attendee) {
            SC_API_Response::notFound('التذكرة غير موجودة أو غير صالحة');
        }

        // A workshop ticket gets its workshop's certificate, under the workshop's rules and template.
        $scope = sc_certs_scope_for_ticket($attendee);
        $problem = sc_certs_ticket_problem($attendee, $scope);
        if ($problem) {
            SC_API_Response::error($this->problemMessage($problem, $scope), 400);
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
                'view_url' => home_url('/certificate/' . $existing->verification_code),
                'workshop' => $this->workshopInfo($scope)
            ], 'الشهادة موجودة مسبقاً');
            return;
        }

        $certificate_id = sc_certs_issue_for_ticket($attendee, $scope, $this->userId() ?: 0);
        $certificate = is_wp_error($certificate_id) ? null : SC_Certificate::get($certificate_id);
        if (!$certificate) {
            SC_API_Response::error('فشل في إصدار الشهادة', 500);
        }

        SC_API_Response::created([
            'id' => (int) $certificate['id'],
            'certificate_number' => $certificate['certificate_number'],
            'verification_code' => $certificate['verification_code'],
            'attendee_name' => $attendee->name,
            'event_title' => $scope['event_title'],
            'workshop' => $this->workshopInfo($scope),
            'issued_at' => $certificate['issued_at'],
            'download_url' => home_url('/certificate/' . $certificate['verification_code'] . '/download'),
            'view_url' => home_url('/certificate/' . $certificate['verification_code'])
        ], 'تم إصدار الشهادة بنجاح');
    }

    /**
     * Why a ticket can't get its certificate, worded for the app.
     */
    private function problemMessage($problem, $scope) {
        $messages = [
            'disabled'  => ($scope && $scope['type'] === 'workshop') ? 'الورشة لا تقدم شهادات' : 'الفعالية لا تقدم شهادات',
            'checkin'   => 'يجب تسجيل الحضور أولاً للحصول على الشهادة',
            'checkout'  => 'يجب تسجيل الانصراف أولاً للحصول على الشهادة',
            'not_ended' => 'الشهادة ستكون متاحة بعد انتهاء الفعالية',
        ];
        return $messages[$problem];
    }

    /**
     * The workshop a certificate belongs to, or null for a congress certificate.
     */
    private function workshopInfo($scope) {
        return $scope && $scope['workshop_id'] ? ['id' => $scope['workshop_id'], 'title' => $scope['title']] : null;
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
            "SELECT c.*, e.title as event_title, e.start_date, e.end_date, e.venue_name, w.title as workshop_title
             FROM {$this->certificates_table} c
             JOIN {$this->attendees_table} a ON c.attendee_id = a.id
             JOIN {$this->events_table} e ON c.event_id = e.id
             LEFT JOIN {$this->workshops_table} w ON w.id = c.workshop_id
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
                'workshop' => $this->certificateWorkshop($cert),
                'issued_at' => $cert->issued_at,
                'download_count' => (int) $cert->download_count,
                'download_url' => home_url('/certificate/' . $cert->verification_code . '/download'),
                'view_url' => home_url('/certificate/' . $cert->verification_code)
            ];
        }, $certificates);

        SC_API_Response::success($data);
    }

    /**
     * The workshop a stored certificate belongs to, or null for a congress certificate.
     */
    private function certificateWorkshop($cert) {
        return !empty($cert->workshop_id) ? ['id' => (int) $cert->workshop_id, 'title' => $cert->workshop_title] : null;
    }
}
