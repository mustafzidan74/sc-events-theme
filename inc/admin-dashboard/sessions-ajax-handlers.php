<?php
/**
 * Sessions AJAX Handlers
 * Using Custom Tables (sc_sessions, sc_session_speakers, sc_session_registrations, sc_session_attendance)
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$sessions_module_active = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions');

if ($sessions_module_active):

// ==========================================
// GET SESSIONS LIST
// ==========================================
add_action('wp_ajax_sc_get_sessions', 'sc_get_sessions');
function sc_get_sessions() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $events_table = $wpdb->prefix . 'sc_events';

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $where = array('1=1');
    $params = array();

    if ($event_id) {
        $where[] = 's.event_id = %d';
        $params[] = $event_id;
    }

    if ($status && $status !== 'all') {
        $where[] = 's.status = %s';
        $params[] = $status;
    }

    if ($date) {
        $where[] = 's.session_date = %s';
        $params[] = $date;
    }

    if ($search) {
        $where[] = '(s.title LIKE %s OR s.track LIKE %s OR s.hall_name LIKE %s)';
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where_clause = implode(' AND ', $where);

    $sql = "SELECT s.*, e.title as event_title
            FROM $sessions_table s
            LEFT JOIN $events_table e ON s.event_id = e.id
            WHERE $where_clause
            ORDER BY s.session_date ASC, s.start_time ASC";

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $sessions = $wpdb->get_results($sql);

    $data = array();
    foreach ($sessions as $session) {
        $data[] = array(
            'id' => (int) $session->id,
            'event_id' => (int) $session->event_id,
            'event_title' => $session->event_title ?: '',
            'title' => $session->title,
            'description' => $session->description,
            'session_type' => $session->session_type,
            'track' => $session->track,
            'session_date' => $session->session_date,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'duration_minutes' => (int) $session->duration_minutes,
            'hall_name' => $session->hall_name,
            'capacity' => (int) $session->capacity,
            'registered_count' => (int) $session->registered_count,
            'attended_count' => (int) $session->attended_count,
            'cme_hours' => (float) $session->cme_hours,
            'status' => $session->status,
            'is_published' => (bool) $session->is_published,
            'sort_order' => (int) $session->sort_order,
        );
    }

    wp_send_json_success(array('sessions' => $data));
}

// ==========================================
// GET EVENT SPEAKERS (lightweight, for session form)
// ==========================================
add_action('wp_ajax_sc_get_event_speakers_list', 'sc_get_event_speakers_list');
function sc_get_event_speakers_list() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    if (!$event_id) {
        wp_send_json_success(array('speakers' => array()));
        return;
    }

    global $wpdb;
    $speakers = $wpdb->get_results($wpdb->prepare(
        "SELECT sp.id, sp.name, sp.title
         FROM {$wpdb->prefix}sc_speakers sp
         JOIN {$wpdb->prefix}sc_event_speakers es ON sp.id = es.speaker_id
         WHERE es.event_id = %d
         ORDER BY es.sort_order ASC, sp.name ASC",
        $event_id
    ));

    wp_send_json_success(array('speakers' => $speakers));
}

// ==========================================
// GET SESSIONS BY EVENT (for scanner)
// ==========================================
add_action('wp_ajax_sc_sessions_get_by_event', 'sc_sessions_get_by_event');
function sc_sessions_get_by_event() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Event ID is required.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sessions';

    $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'published';

    $where = 'event_id = %d';
    $params = array($event_id);

    if ($status_filter !== 'all') {
        $where .= ' AND is_published = 1';
    }

    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE $where ORDER BY session_date ASC, start_time ASC",
        $params
    ));

    $data = array();
    foreach ($sessions as $s) {
        $data[] = array(
            'id' => (int) $s->id,
            'title' => $s->title,
            'session_type' => $s->session_type,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'hall' => $s->hall_name,
            'cme_hours' => (float) $s->cme_hours,
            'capacity' => (int) $s->capacity,
            'registered_count' => (int) $s->registered_count,
            'attended_count' => (int) $s->attended_count,
            'status' => $s->status,
        );
    }

    wp_send_json_success(array('sessions' => $data));
}

// ==========================================
// GET SINGLE SESSION
// ==========================================
add_action('wp_ajax_sc_get_session', 'sc_get_session');
function sc_get_session() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sessions';
    $ss_table = $wpdb->prefix . 'sc_session_speakers';
    $speakers_table = $wpdb->prefix . 'sc_speakers';

    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $session_id));
    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    // Get speakers
    $speakers = $wpdb->get_results($wpdb->prepare(
        "SELECT sp.id, sp.name, sp.title, sp.photo, ss.role, ss.presentation_title, ss.sort_order
         FROM $speakers_table sp
         JOIN $ss_table ss ON sp.id = ss.speaker_id
         WHERE ss.session_id = %d
         ORDER BY ss.sort_order ASC",
        $session_id
    ));

    $speakers_data = array();
    foreach ($speakers as $sp) {
        $speakers_data[] = array(
            'id' => (int) $sp->id,
            'name' => $sp->name,
            'title' => $sp->title,
            'photo_url' => $sp->photo ? wp_get_attachment_url($sp->photo) : '',
            'role' => $sp->role,
            'presentation_title' => $sp->presentation_title,
            'sort_order' => (int) $sp->sort_order,
        );
    }

    wp_send_json_success(array(
        'session' => array(
            'id' => (int) $session->id,
            'event_id' => (int) $session->event_id,
            'title' => $session->title,
            'description' => $session->description,
            'session_type' => $session->session_type,
            'track' => $session->track,
            'session_date' => $session->session_date,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'duration_minutes' => (int) $session->duration_minutes,
            'hall_name' => $session->hall_name,
            'capacity' => (int) $session->capacity,
            'registered_count' => (int) $session->registered_count,
            'attended_count' => (int) $session->attended_count,
            'cme_hours' => (float) $session->cme_hours,
            'cme_category' => $session->cme_category,
            'enable_certificate' => (bool) $session->enable_certificate,
            'certificate_template_id' => (int) $session->certificate_template_id,
            'min_attendance_percentage' => (float) $session->min_attendance_percentage,
            'status' => $session->status,
            'is_published' => (bool) $session->is_published,
            'sort_order' => (int) $session->sort_order,
        ),
        'speakers' => $speakers_data,
    ));
}

// ==========================================
// SAVE SESSION (CREATE / UPDATE)
// ==========================================
add_action('wp_ajax_sc_save_session', 'sc_save_session');
function sc_save_session() {
    $nonce_valid = false;
    if (isset($_POST['sc_session_nonce']) && wp_verify_nonce($_POST['sc_session_nonce'], 'sc_session_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        $nonce_valid = true;
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sessions';

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $title = sanitize_text_field($_POST['title'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $session_date = sanitize_text_field($_POST['session_date'] ?? '');
    $start_time = sanitize_text_field($_POST['start_time'] ?? '');

    if (empty($title)) {
        wp_send_json_error(array('message' => __('Session title is required.', 'sc_events')));
    }
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Event is required.', 'sc_events')));
    }
    if (empty($session_date)) {
        wp_send_json_error(array('message' => __('Session date is required.', 'sc_events')));
    }
    if (empty($start_time)) {
        wp_send_json_error(array('message' => __('Start time is required.', 'sc_events')));
    }

    // Build start/end datetime
    $start_datetime = $session_date . ' ' . $start_time . ':00';
    $end_time_input = sanitize_text_field($_POST['end_time'] ?? '');
    $end_datetime = $end_time_input ? ($session_date . ' ' . $end_time_input . ':00') : null;

    // Calculate duration
    $duration_minutes = null;
    if ($end_datetime) {
        $start_ts = strtotime($start_datetime);
        $end_ts = strtotime($end_datetime);
        if ($end_ts > $start_ts) {
            $duration_minutes = ($end_ts - $start_ts) / 60;
        }
    }

    $status = sanitize_text_field($_POST['status'] ?? 'draft');
    $is_published = in_array($status, array('published', 'live')) ? 1 : 0;

    // Generate unique slug for the session within the event
    $base_slug = sanitize_title($title);
    if (empty($base_slug)) {
        $base_slug = 'session';
    }
    $slug = $base_slug;
    $slug_counter = 1;
    $slug_check_exclude = $session_id > 0 ? $wpdb->prepare(" AND id != %d", $session_id) : '';
    while ($wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE event_id = %d AND slug = %s" . $slug_check_exclude,
        $event_id, $slug
    ))) {
        $slug = $base_slug . '-' . (++$slug_counter);
    }

    $data = array(
        'event_id' => $event_id,
        'title' => $title,
        'slug' => $slug,
        'description' => wp_kses_post($_POST['description'] ?? ''),
        'session_type' => sanitize_text_field($_POST['session_type'] ?? 'lecture'),
        'track' => sanitize_text_field($_POST['track'] ?? ''),
        'session_date' => $session_date,
        'start_time' => $start_datetime,
        'end_time' => $end_datetime,
        'duration_minutes' => $duration_minutes,
        'hall_name' => sanitize_text_field($_POST['hall_name'] ?? ''),
        'capacity' => intval($_POST['capacity'] ?? 0),
        'cme_hours' => floatval($_POST['cme_hours'] ?? 0),
        'cme_category' => sanitize_text_field($_POST['cme_category'] ?? ''),
        'enable_certificate' => intval($_POST['enable_certificate'] ?? 0),
        'certificate_template_id' => intval($_POST['certificate_template_id'] ?? 0) ?: null,
        'min_attendance_percentage' => floatval($_POST['min_attendance_percentage'] ?? 80),
        'status' => $status,
        'is_published' => $is_published,
        'sort_order' => intval($_POST['sort_order'] ?? 0),
        'updated_at' => current_time('mysql'),
    );

    if ($session_id > 0) {
        // Update
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $session_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
        }
        $wpdb->update($table, $data, array('id' => $session_id));
        $message = __('Session updated successfully.', 'sc_events');
    } else {
        // Create
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        $session_id = $wpdb->insert_id;

        if (!$session_id) {
            wp_send_json_error(array('message' => __('Failed to create session.', 'sc_events')));
        }
        $message = __('Session created successfully.', 'sc_events');
    }

    // Handle speakers
    if (isset($_POST['speakers']) && is_array($_POST['speakers'])) {
        sc_save_session_speakers_data($session_id, $_POST['speakers']);
    }

    wp_send_json_success(array('message' => $message, 'session_id' => $session_id));
}

/**
 * Save session speakers pivot data
 */
function sc_save_session_speakers_data($session_id, $speakers) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_session_speakers';
    $sessions_table = $wpdb->prefix . 'sc_sessions';

    // Remove existing speakers
    $wpdb->delete($table, array('session_id' => $session_id), array('%d'));

    $count = 0;
    foreach ($speakers as $index => $speaker) {
        $speaker_id = intval($speaker['id'] ?? 0);
        $custom_name = sanitize_text_field($speaker['custom_name'] ?? '');

        // If no speaker_id but has custom name, create a new speaker
        if (!$speaker_id && $custom_name) {
            $speakers_table = $wpdb->prefix . 'sc_speakers';
            $wpdb->insert($speakers_table, array(
                'name' => $custom_name,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ));
            $speaker_id = $wpdb->insert_id;
        }

        if (!$speaker_id) continue;

        $wpdb->insert($table, array(
            'session_id' => $session_id,
            'speaker_id' => $speaker_id,
            'role' => sanitize_text_field($speaker['role'] ?? 'speaker'),
            'presentation_title' => sanitize_text_field($speaker['presentation_title'] ?? ''),
            'sort_order' => intval($speaker['sort_order'] ?? $index),
        ));
        $count++;
    }

    // Update cached count
    $wpdb->update($sessions_table, array('speakers_count' => $count), array('id' => $session_id));
}

// ==========================================
// DELETE SESSION
// ==========================================
add_action('wp_ajax_sc_delete_session', 'sc_delete_session');
function sc_delete_session() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $ss_table = $wpdb->prefix . 'sc_session_speakers';
    $sr_table = $wpdb->prefix . 'sc_session_registrations';
    $sa_table = $wpdb->prefix . 'sc_session_attendance';

    // Delete related data
    $wpdb->delete($ss_table, array('session_id' => $session_id), array('%d'));
    $wpdb->delete($sr_table, array('session_id' => $session_id), array('%d'));
    $wpdb->delete($sa_table, array('session_id' => $session_id), array('%d'));

    // Delete session
    $result = $wpdb->delete($sessions_table, array('id' => $session_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete session.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Session deleted successfully.', 'sc_events')));
}

// ==========================================
// REGISTER ATTENDEE IN SESSION
// ==========================================
add_action('wp_ajax_sc_register_attendee_sessions', 'sc_register_attendee_sessions');
function sc_register_attendee_sessions() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $attendee_id = intval($_POST['attendee_id'] ?? 0);
    $event_id = intval($_POST['event_id'] ?? 0);

    if (!$attendee_id || !$event_id) {
        wp_send_json_error(array('message' => __('Attendee ID and Event ID are required.', 'sc_events')));
    }

    $result = sc_auto_register_attendee_in_sessions($attendee_id, $event_id);
    wp_send_json_success($result);
}

/**
 * Auto-register an attendee in all published sessions for an event
 */
function sc_auto_register_attendee_in_sessions($attendee_id, $event_id) {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $reg_table = $wpdb->prefix . 'sc_session_registrations';

    // Get all published sessions for this event
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, capacity, registered_count FROM $sessions_table WHERE event_id = %d AND is_published = 1 ORDER BY session_date ASC, start_time ASC",
        $event_id
    ));

    $registered = 0;
    $waitlisted = 0;
    $skipped = 0;

    foreach ($sessions as $session) {
        // Check if already registered
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $reg_table WHERE session_id = %d AND attendee_id = %d",
            $session->id, $attendee_id
        ));

        if ($exists) {
            $skipped++;
            continue;
        }

        // Check capacity
        $is_waitlisted = false;
        if ($session->capacity > 0 && $session->registered_count >= $session->capacity) {
            $is_waitlisted = true;
            $waitlist_pos = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(waitlist_position), 0) + 1 FROM $reg_table WHERE session_id = %d AND status = 'waitlisted'",
                $session->id
            ));
        }

        // Generate registration code
        $code = 'SES-' . $session->id . '-' . strtoupper(wp_generate_password(8, false));

        // Generate QR data
        $qr_data = wp_json_encode(array(
            'type' => 'session',
            'session_id' => (int) $session->id,
            'attendee_id' => $attendee_id,
            'code' => $code,
        ));

        $wpdb->insert($reg_table, array(
            'session_id' => $session->id,
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'registration_code' => $code,
            'qr_code' => $qr_data,
            'status' => $is_waitlisted ? 'waitlisted' : 'registered',
            'waitlist_position' => $is_waitlisted ? $waitlist_pos : null,
            'registered_at' => current_time('mysql'),
        ));

        if (!$is_waitlisted) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $sessions_table SET registered_count = registered_count + 1 WHERE id = %d",
                $session->id
            ));
            $registered++;
        } else {
            $waitlisted++;
        }
    }

    return array(
        'message' => sprintf(__('Registered: %d, Waitlisted: %d, Skipped: %d', 'sc_events'), $registered, $waitlisted, $skipped),
        'registered' => $registered,
        'waitlisted' => $waitlisted,
        'skipped' => $skipped,
    );
}

// ==========================================
// SESSION CHECK-IN
// ==========================================
add_action('wp_ajax_sc_session_checkin', 'sc_session_checkin');
function sc_session_checkin() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $reg_table = $wpdb->prefix . 'sc_session_registrations';
    $att_table = $wpdb->prefix . 'sc_session_attendance';
    $attendees_table = $wpdb->prefix . 'sc_attendees';

    $session_id = intval($_POST['session_id'] ?? 0);
    $registration_code = sanitize_text_field($_POST['registration_code'] ?? '');
    $attendee_id = intval($_POST['attendee_id'] ?? 0);
    $scan_method = sanitize_text_field($_POST['scan_method'] ?? 'qr');

    // Find the registration
    $registration = null;
    if ($registration_code) {
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reg_table WHERE registration_code = %s",
            $registration_code
        ));
    } elseif ($session_id && $attendee_id) {
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reg_table WHERE session_id = %d AND attendee_id = %d",
            $session_id, $attendee_id
        ));
    }

    if (!$registration) {
        wp_send_json_error(array('message' => __('Session registration not found.', 'sc_events')));
    }

    $session_id = (int) $registration->session_id;
    $attendee_id = (int) $registration->attendee_id;
    $event_id = (int) $registration->event_id;

    // Get session info
    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id = %d", $session_id));
    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    // Get attendee info
    $attendee = $wpdb->get_row($wpdb->prepare("SELECT name, email FROM $attendees_table WHERE id = %d", $attendee_id));

    // Check if already checked in
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $att_table WHERE session_id = %d AND attendee_id = %d",
        $session_id, $attendee_id
    ));

    if ($existing && $existing->check_in_time) {
        wp_send_json_success(array(
            'already_checked_in' => true,
            'attendee_name' => $attendee ? $attendee->name : '',
            'attendee_email' => $attendee ? $attendee->email : '',
            'session_title' => $session->title,
            'check_in_time' => $existing->check_in_time,
            'message' => __('Already checked in to this session.', 'sc_events'),
        ));
        return;
    }

    $now = current_time('mysql');
    $current_user_id = get_current_user_id();

    if ($existing) {
        // Update existing record
        $wpdb->update($att_table, array(
            'check_in_time' => $now,
            'checked_in_by' => $current_user_id,
            'scan_method' => $scan_method,
            'updated_at' => $now,
        ), array('id' => $existing->id));
    } else {
        // Insert new
        $wpdb->insert($att_table, array(
            'session_id' => $session_id,
            'attendee_id' => $attendee_id,
            'event_id' => $event_id,
            'check_in_time' => $now,
            'checked_in_by' => $current_user_id,
            'scan_method' => $scan_method,
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    // Update session attended count
    $wpdb->query($wpdb->prepare(
        "UPDATE $sessions_table SET attended_count = attended_count + 1 WHERE id = %d",
        $session_id
    ));

    wp_send_json_success(array(
        'already_checked_in' => false,
        'attendee_name' => $attendee ? $attendee->name : '',
        'attendee_email' => $attendee ? $attendee->email : '',
        'session_title' => $session->title,
        'check_in_time' => $now,
        'message' => sprintf(__('%s checked in to %s', 'sc_events'), $attendee ? $attendee->name : '', $session->title),
    ));
}

// ==========================================
// SESSION CHECK-OUT
// ==========================================
add_action('wp_ajax_sc_session_checkout', 'sc_session_checkout');
function sc_session_checkout() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $att_table = $wpdb->prefix . 'sc_session_attendance';

    $session_id = intval($_POST['session_id'] ?? 0);
    $attendee_id = intval($_POST['attendee_id'] ?? 0);

    if (!$session_id || !$attendee_id) {
        wp_send_json_error(array('message' => __('Session ID and Attendee ID are required.', 'sc_events')));
    }

    // Get attendance record
    $attendance = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $att_table WHERE session_id = %d AND attendee_id = %d",
        $session_id, $attendee_id
    ));

    if (!$attendance || !$attendance->check_in_time) {
        wp_send_json_error(array('message' => __('Attendee has not checked in to this session.', 'sc_events')));
    }

    if ($attendance->check_out_time) {
        wp_send_json_error(array('message' => __('Attendee has already checked out.', 'sc_events')));
    }

    // Get session info for calculations
    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id = %d", $session_id));

    $now = current_time('mysql');

    // Calculate attendance
    $check_in_ts = strtotime($attendance->check_in_time);
    $check_out_ts = strtotime($now);
    $attendance_minutes = max(0, ($check_out_ts - $check_in_ts) / 60);

    $attendance_percentage = 0;
    if ($session->duration_minutes > 0) {
        $attendance_percentage = min(100, ($attendance_minutes / $session->duration_minutes) * 100);
    } elseif ($session->end_time && $session->start_time) {
        $session_duration = (strtotime($session->end_time) - strtotime($session->start_time)) / 60;
        if ($session_duration > 0) {
            $attendance_percentage = min(100, ($attendance_minutes / $session_duration) * 100);
        }
    }

    // Calculate CME hours
    $earned_cme = 0;
    if ($session->cme_hours > 0) {
        $earned_cme = round($session->cme_hours * ($attendance_percentage / 100), 2);
    }

    // Check certificate eligibility
    $certificate_eligible = $attendance_percentage >= (float) $session->min_attendance_percentage ? 1 : 0;

    $wpdb->update($att_table, array(
        'check_out_time' => $now,
        'attendance_minutes' => round($attendance_minutes),
        'attendance_percentage' => round($attendance_percentage, 2),
        'earned_cme_hours' => $earned_cme,
        'certificate_eligible' => $certificate_eligible,
        'updated_at' => $now,
    ), array('id' => $attendance->id));

    wp_send_json_success(array(
        'message' => __('Check-out recorded successfully.', 'sc_events'),
        'check_out_time' => $now,
        'attendance_minutes' => round($attendance_minutes),
        'attendance_percentage' => round($attendance_percentage, 1),
        'earned_cme_hours' => $earned_cme,
        'certificate_eligible' => (bool) $certificate_eligible,
    ));
}

// ==========================================
// GET SESSION REGISTRATIONS (attendees list)
// ==========================================
add_action('wp_ajax_sc_get_session_registrations', 'sc_get_session_registrations');
function sc_get_session_registrations() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $session_id = intval($_POST['session_id'] ?? 0);
    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $reg_table = $wpdb->prefix . 'sc_session_registrations';
    $att_table = $wpdb->prefix . 'sc_session_attendance';
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $certs_table = $wpdb->prefix . 'sc_certificates';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            sr.id as registration_id,
            sr.registration_code,
            sr.status as reg_status,
            sr.registered_at,
            a.id as attendee_id,
            a.name,
            a.email,
            a.phone,
            a.ticket_name,
            sa.check_in_time,
            sa.check_out_time,
            sa.attendance_minutes,
            sa.attendance_percentage,
            sa.earned_cme_hours,
            sa.certificate_eligible,
            sa.certificate_issued,
            c.id as certificate_id,
            c.certificate_number,
            c.status as cert_status
         FROM $reg_table sr
         JOIN $attendees_table a ON sr.attendee_id = a.id
         LEFT JOIN $att_table sa ON sr.session_id = sa.session_id AND sr.attendee_id = sa.attendee_id
         LEFT JOIN $certs_table c ON c.attendee_id = sr.attendee_id AND c.session_id = sr.session_id AND c.status = 'issued'
         WHERE sr.session_id = %d
         ORDER BY a.name ASC",
        $session_id
    ));

    $data = array();
    foreach ($rows as $row) {
        $data[] = array(
            'registration_id' => (int) $row->registration_id,
            'registration_code' => $row->registration_code,
            'reg_status' => $row->reg_status,
            'registered_at' => $row->registered_at,
            'attendee_id' => (int) $row->attendee_id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'ticket_name' => $row->ticket_name,
            'check_in_time' => $row->check_in_time,
            'check_out_time' => $row->check_out_time,
            'attendance_minutes' => (int) ($row->attendance_minutes ?? 0),
            'attendance_percentage' => (float) ($row->attendance_percentage ?? 0),
            'earned_cme_hours' => (float) ($row->earned_cme_hours ?? 0),
            'certificate_eligible' => (bool) ($row->certificate_eligible ?? false),
            'certificate_issued' => (bool) ($row->certificate_issued ?? false),
            'certificate_id' => $row->certificate_id ? (int) $row->certificate_id : null,
            'certificate_number' => $row->certificate_number,
        );
    }

    // Get stats
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_sessions WHERE id = %d", $session_id
    ));

    $total_registered = count($data);
    $total_checked_in = count(array_filter($data, fn($r) => $r['check_in_time']));
    $total_checked_out = count(array_filter($data, fn($r) => $r['check_out_time']));
    $total_eligible = count(array_filter($data, fn($r) => $r['certificate_eligible']));
    $avg_attendance = $total_checked_out > 0
        ? round(array_sum(array_column(array_filter($data, fn($r) => $r['check_out_time']), 'attendance_percentage')) / $total_checked_out, 1)
        : 0;

    wp_send_json_success(array(
        'attendees' => $data,
        'stats' => array(
            'total_registered' => $total_registered,
            'total_checked_in' => $total_checked_in,
            'total_checked_out' => $total_checked_out,
            'total_eligible' => $total_eligible,
            'avg_attendance' => $avg_attendance,
            'cme_hours' => $session ? (float) $session->cme_hours : 0,
            'min_attendance' => $session ? (float) $session->min_attendance_percentage : 80,
        ),
    ));
}

// ==========================================
// GET SESSION ATTENDANCE STATS
// ==========================================
add_action('wp_ajax_sc_get_session_attendance_stats', 'sc_get_session_attendance_stats');
function sc_get_session_attendance_stats() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $session_id = intval($_POST['session_id'] ?? 0);
    if (!$session_id) {
        wp_send_json_error(array('message' => __('Session ID is required.', 'sc_events')));
    }

    global $wpdb;
    $sessions_table = $wpdb->prefix . 'sc_sessions';
    $att_table = $wpdb->prefix . 'sc_session_attendance';
    $reg_table = $wpdb->prefix . 'sc_session_registrations';

    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id = %d", $session_id));
    if (!$session) {
        wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
    }

    $total_registered = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $reg_table WHERE session_id = %d AND status = 'registered'", $session_id
    ));

    $total_checked_in = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $att_table WHERE session_id = %d AND check_in_time IS NOT NULL", $session_id
    ));

    $total_checked_out = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $att_table WHERE session_id = %d AND check_out_time IS NOT NULL", $session_id
    ));

    $total_eligible = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $att_table WHERE session_id = %d AND certificate_eligible = 1", $session_id
    ));

    wp_send_json_success(array(
        'stats' => array(
            'capacity' => (int) $session->capacity,
            'total_registered' => $total_registered,
            'total_checked_in' => $total_checked_in,
            'total_checked_out' => $total_checked_out,
            'total_eligible' => $total_eligible,
            'cme_hours' => (float) $session->cme_hours,
            'min_attendance' => (float) $session->min_attendance_percentage,
        ),
    ));
}

endif; // if $sessions_module_active
