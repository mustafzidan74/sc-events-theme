<?php
/**
 * SC Certificate Model Class
 *
 * Data Access Layer for Issued Certificates table
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Certificate {

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
            self::$table = $wpdb->prefix . 'sc_certificates';
        }
        return self::$table;
    }

    /**
     * Get single certificate by ID
     *
     * @param int $id Certificate ID
     * @return object|null
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificate;
    }

    /**
     * Get certificate by number
     *
     * @param string $certificate_number Certificate number
     * @return object|null
     */
    public static function get_by_number($certificate_number) {
        global $wpdb;
        $table = self::get_table();

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE certificate_number = %s",
            $certificate_number
        ));

        if ($certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificate;
    }

    /**
     * Get certificate by verification code
     *
     * @param string $code Verification code
     * @return object|null
     */
    public static function get_by_verification_code($code) {
        global $wpdb;
        $table = self::get_table();

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE verification_code = %s",
            $code
        ));

        if ($certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificate;
    }

    /**
     * Get certificate for attendee and event
     *
     * @param int $attendee_id Attendee ID
     * @param int $event_id Event ID
     * @return object|null
     */
    public static function get_by_attendee_event($attendee_id, $event_id) {
        global $wpdb;
        $table = self::get_table();

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE attendee_id = %d AND event_id = %d AND (workshop_id IS NULL OR workshop_id = 0)",
            $attendee_id,
            $event_id
        ));

        if ($certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificate;
    }

    /**
     * Get certificate for attendee and workshop
     *
     * @param int $attendee_id Attendee ID
     * @param int $workshop_id Workshop ID
     * @return object|null
     */
    public static function get_by_attendee_workshop($attendee_id, $workshop_id) {
        global $wpdb;
        $table = self::get_table();

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE attendee_id = %d AND workshop_id = %d",
            $attendee_id,
            $workshop_id
        ));

        if ($certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificate;
    }

    /**
     * Get certificates for an event
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array
     */
    public static function get_by_event($event_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'status'  => null,
            'orderby' => 'issued_at',
            'order'   => 'DESC',
            'limit'   => 100,
            'offset'  => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('event_id = %d');
        $values = array($event_id);

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'certificate_number', 'attendee_name', 'issued_at', 'downloaded_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'issued_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $certificates = $wpdb->get_results($sql);

        foreach ($certificates as &$certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificates;
    }

    /**
     * Get all certificates with filters
     *
     * @param array $args Filter arguments
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'    => null,
            'workshop_id' => null,
            'template_id' => null,
            'status'      => null,
            'search'      => null,
            'date_from'   => null,
            'date_to'     => null,
            'orderby'     => 'issued_at',
            'order'       => 'DESC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['workshop_id'] !== null) {
            if ((int) $args['workshop_id'] === 0) {
                $where[] = '(workshop_id IS NULL OR workshop_id = 0)';
            } else {
                $where[] = 'workshop_id = %d';
                $values[] = (int) $args['workshop_id'];
            }
        }

        if ($args['template_id']) {
            $where[] = 'template_id = %d';
            $values[] = $args['template_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(attendee_name LIKE %s OR certificate_number LIKE %s OR event_title LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        if ($args['date_from']) {
            $where[] = 'issued_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }

        if ($args['date_to']) {
            $where[] = 'issued_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = array('id', 'certificate_number', 'attendee_name', 'event_title', 'issued_at', 'downloaded_at', 'download_count');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'issued_at';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $sql = $wpdb->prepare($sql, $values);

        $certificates = $wpdb->get_results($sql);

        foreach ($certificates as &$certificate) {
            $certificate = self::hydrate($certificate);
        }

        return $certificates;
    }

    /**
     * Count certificates
     *
     * @param array $args Filter arguments
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'event_id'    => null,
            'template_id' => null,
            'status'      => null,
            'search'      => null,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['template_id']) {
            $where[] = 'template_id = %d';
            $values[] = $args['template_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(attendee_name LIKE %s OR certificate_number LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Issue certificate
     *
     * @param array $data Certificate data
     * @return int|WP_Error Certificate ID or error
     */
    public static function issue($data) {
        global $wpdb;
        $table = self::get_table();

        // Check if certificate already exists for this attendee/event
        $existing = self::get_by_attendee_event($data['attendee_id'], $data['event_id']);
        if ($existing) {
            return new WP_Error('certificate_exists', 'Certificate already issued for this attendee.');
        }

        // Generate unique certificate number
        $data['certificate_number'] = self::generate_certificate_number();

        // Generate verification code
        $data['verification_code'] = self::generate_verification_code();

        $data = self::prepare_data($data);
        $data['issued_at'] = current_time('mysql');
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        if (empty($data['issued_by'])) {
            $data['issued_by'] = get_current_user_id();
        }

        $result = $wpdb->insert($table, $data);

        if ($result) {
            $certificate_id = $wpdb->insert_id;
            do_action('sc_certificate_issued', $certificate_id, $data);
            return $certificate_id;
        }

        return new WP_Error('certificate_issue_failed', 'Failed to issue certificate.');
    }

    /**
     * Bulk issue certificates for an event
     *
     * @param int $event_id Event ID
     * @param int $template_id Template ID
     * @param array $attendee_ids Optional specific attendees
     * @return array Results with issued and failed counts
     */
    public static function bulk_issue($event_id, $template_id, $attendee_ids = array()) {
        global $wpdb;

        $results = array(
            'issued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'certificates' => array(),
        );

        // Get event
        $event = SC_Event::get($event_id);
        if (!$event) {
            return new WP_Error('event_not_found', 'Event not found.');
        }

        // Get template
        $template = SC_Certificate_Template::get($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', 'Certificate template not found.');
        }

        // Get attendees
        $attendees_table = $wpdb->prefix . 'sc_attendees';
        if (!empty($attendee_ids)) {
            $placeholders = implode(',', array_fill(0, count($attendee_ids), '%d'));
            $attendees = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $attendees_table WHERE event_id = %d AND id IN ($placeholders) AND payment_status = 'success'",
                array_merge(array($event_id), $attendee_ids)
            ));
        } else {
            $attendees = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $attendees_table WHERE event_id = %d AND payment_status = 'success'",
                $event_id
            ));
        }

        foreach ($attendees as $attendee) {
            // Check if already issued
            $existing = self::get_by_attendee_event($attendee->id, $event_id);
            if ($existing) {
                $results['skipped']++;
                continue;
            }

            $data = array(
                'template_id'   => $template_id,
                'attendee_id'   => $attendee->id,
                'event_id'      => $event_id,
                'attendee_name' => $attendee->name,
                'event_title'   => $event->title,
                'event_date'    => $event->start_date,
            );

            $certificate_id = self::issue($data);

            if (is_wp_error($certificate_id)) {
                $results['failed']++;
            } else {
                $results['issued']++;
                $results['certificates'][] = $certificate_id;
            }
        }

        return $results;
    }

    /**
     * Revoke certificate
     *
     * @param int $id Certificate ID
     * @param string $reason Revocation reason
     * @return bool Success
     */
    public static function revoke($id, $reason = '') {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->update(
            $table,
            array(
                'status'       => 'revoked',
                'revoked_at'   => current_time('mysql'),
                'revoke_reason' => $reason,
                'updated_at'   => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            do_action('sc_certificate_revoked', $id, $reason);
            return true;
        }

        return false;
    }

    /**
     * Record download
     *
     * @param int $id Certificate ID
     * @return bool Success
     */
    public static function record_download($id) {
        global $wpdb;
        $table = self::get_table();

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET
                status = 'downloaded',
                downloaded_at = COALESCE(downloaded_at, %s),
                download_count = download_count + 1,
                last_download_ip = %s,
                updated_at = %s
            WHERE id = %d",
            current_time('mysql'),
            $ip,
            current_time('mysql'),
            $id
        ));

        return $result !== false;
    }

    /**
     * Mark email sent
     *
     * @param int $id Certificate ID
     * @return bool Success
     */
    public static function mark_email_sent($id) {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->update(
            $table,
            array(
                'email_sent'    => 1,
                'email_sent_at' => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $id),
            array('%d', '%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Generate unique certificate number
     *
     * @return string
     */
    public static function generate_certificate_number() {
        $prefix = apply_filters('sc_certificate_number_prefix', 'CERT');
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $number = $prefix . '-' . $year . '-' . $random;

        // Ensure uniqueness
        global $wpdb;
        $table = self::get_table();
        $counter = 0;
        $original = $number;

        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE certificate_number = %s", $number))) {
            $counter++;
            $number = $original . '-' . $counter;
        }

        return $number;
    }

    /**
     * Generate verification code
     *
     * @return string
     */
    public static function generate_verification_code() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true) . time()), 0, 12));
    }

    /**
     * Get verification URL
     *
     * @param string $verification_code Verification code
     * @return string
     */
    public static function get_verification_url($verification_code) {
        return home_url('/certificate-verify/' . rawurlencode($verification_code) . '/');
    }

    /**
     * Get download URL
     *
     * @param int $certificate_id Certificate ID
     * @return string
     */
    public static function get_download_url($certificate_id) {
        $certificate = self::get($certificate_id);
        if (!$certificate) {
            return '';
        }

        // Generate secure token
        $token = wp_hash($certificate['verification_code'] . $certificate['certificate_number']);

        return add_query_arg(array(
            'action' => 'sc_download_certificate',
            'id'     => $certificate_id,
            'token'  => $token,
        ), admin_url('admin-ajax.php'));
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
        $int_fields = array('template_id', 'attendee_id', 'event_id', 'workshop_id', 'issued_by', 'download_count', 'email_sent');

        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = intval($data[$field]);
            }
        }

        // String fields
        $string_fields = array('certificate_number', 'verification_code', 'attendee_name', 'event_title', 'status', 'pdf_file', 'last_download_ip');

        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Date fields
        if (isset($data['event_date'])) {
            $prepared['event_date'] = sanitize_text_field($data['event_date']);
        }

        // Text fields
        if (isset($data['revoke_reason'])) {
            $prepared['revoke_reason'] = sanitize_textarea_field($data['revoke_reason']);
        }

        // JSON fields
        if (isset($data['custom_data'])) {
            if (is_array($data['custom_data'])) {
                $prepared['custom_data'] = wp_json_encode($data['custom_data']);
            } else {
                $prepared['custom_data'] = $data['custom_data'];
            }
        }

        return $prepared;
    }

    /**
     * Hydrate certificate object and convert to array
     *
     * @param object $certificate Raw certificate
     * @return array Hydrated certificate as array
     */
    private static function hydrate($certificate) {
        // Convert to array
        $cert = (array) $certificate;

        // Decode JSON fields
        if (!empty($cert['custom_data'])) {
            $decoded = json_decode($cert['custom_data'], true);
            $cert['custom_data'] = is_array($decoded) ? $decoded : array();
        } else {
            $cert['custom_data'] = array();
        }

        // Verification URL
        $cert['verification_url'] = self::get_verification_url($cert['verification_code']);

        // Download URL - generate token-based URL
        $token = wp_hash($cert['verification_code'] . $cert['certificate_number']);
        $cert['download_url'] = add_query_arg(array(
            'action' => 'sc_download_certificate',
            'id'     => $cert['id'],
            'token'  => $token,
        ), admin_url('admin-ajax.php'));

        // PDF URL
        if (!empty($cert['pdf_file'])) {
            $upload_dir = wp_upload_dir();
            $cert['pdf_url'] = $upload_dir['baseurl'] . '/certificates/' . $cert['pdf_file'];
        } else {
            $cert['pdf_url'] = null;
        }

        // Status label
        $status_labels = array(
            'issued'     => 'Issued',
            'downloaded' => 'Downloaded',
            'revoked'    => 'Revoked',
        );
        $cert['status_label'] = isset($status_labels[$cert['status']]) ? $status_labels[$cert['status']] : $cert['status'];

        // Status color
        $status_colors = array(
            'issued'     => 'info',
            'downloaded' => 'success',
            'revoked'    => 'danger',
        );
        $cert['status_color'] = isset($status_colors[$cert['status']]) ? $status_colors[$cert['status']] : 'secondary';

        return $cert;
    }

    /**
     * Get statistics for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public static function get_event_stats($event_id) {
        global $wpdb;
        $table = self::get_table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
                SUM(CASE WHEN status = 'downloaded' THEN 1 ELSE 0 END) as downloaded,
                SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked,
                SUM(download_count) as total_downloads
            FROM $table
            WHERE event_id = %d",
            $event_id
        ));

        return array(
            'total'           => (int) $stats->total,
            'issued'          => (int) $stats->issued,
            'downloaded'      => (int) $stats->downloaded,
            'revoked'         => (int) $stats->revoked,
            'total_downloads' => (int) $stats->total_downloads,
        );
    }

    /**
     * ===========================================
     * RELATIONSHIP METHODS
     * ===========================================
     */

    /**
     * Get the event for this certificate
     *
     * @param int $certificate_id Certificate ID
     * @return object|null Event object or null
     */
    public static function get_event($certificate_id) {
        $certificate = self::get($certificate_id);
        if (!$certificate || empty($certificate['event_id'])) {
            return null;
        }
        return SC_Event::get($certificate['event_id']);
    }

    /**
     * Get the attendee for this certificate
     *
     * @param int $certificate_id Certificate ID
     * @return object|null Attendee object or null
     */
    public static function get_attendee($certificate_id) {
        $certificate = self::get($certificate_id);
        if (!$certificate || empty($certificate['attendee_id'])) {
            return null;
        }
        return SC_Attendee::get($certificate['attendee_id']);
    }

    /**
     * Get the template used for this certificate
     *
     * @param int $certificate_id Certificate ID
     * @return object|null Template object or null
     */
    public static function get_template($certificate_id) {
        $certificate = self::get($certificate_id);
        if (!$certificate || empty($certificate['template_id'])) {
            return null;
        }
        return SC_Certificate_Template::get($certificate['template_id']);
    }

    /**
     * Get the user who issued this certificate
     *
     * @param int $certificate_id Certificate ID
     * @return WP_User|null User object or null
     */
    public static function get_issuer($certificate_id) {
        $certificate = self::get($certificate_id);
        if (!$certificate || empty($certificate['issued_by'])) {
            return null;
        }
        return get_user_by('id', $certificate['issued_by']);
    }
}
