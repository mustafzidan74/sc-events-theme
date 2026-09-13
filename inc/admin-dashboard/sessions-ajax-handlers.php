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
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
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

    // Scanners need this too: the scanner page offers session mode from it.
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Event ID is required.', 'sc_events')));
    }
    if (!sc_scanner_can_access_event($event_id)) {
        wp_send_json_success(array('sessions' => array()));
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
        if (!sc_scanner_can_access_session($s->id, $event_id)) {
            continue;
        }
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
/**
 * Create or update a session.
 *
 * Text is unslashed before sanitising. On update, CME, certificate and
 * registration settings change only when the request carries them (the old
 * forms had no CME fields, so every save wrote 0 hours). duration_minutes is
 * left to the database where it is a generated column.
 */
function sc_save_session() {
    $nonce_valid = false;
    if (isset($_POST['sc_session_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_session_nonce'])), 'sc_session_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
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
    $in = function ($key, $default = '') {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    };
    $time = function ($value) {
        return preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : '';
    };

    $session_id   = absint($in('session_id', 0));
    $title        = sanitize_text_field($in('title'));
    $event_id     = absint($in('event_id', 0));
    $session_date = sanitize_text_field($in('session_date'));
    $start_time   = $time($in('start_time'));
    $end_time     = $time($in('end_time'));

    $errors = array();
    if ($title === '') {
        $errors['title'] = __('Session title is required.', 'sc_events');
    }
    if (!$event_id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id))) {
        $errors['event_id'] = __('Event is required.', 'sc_events');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $session_date)) {
        $errors['session_date'] = __('Session date is required.', 'sc_events');
    }
    if ($start_time === '') {
        $errors['start_time'] = __('Start time is required.', 'sc_events');
    }
    if ($end_time !== '' && $start_time !== '' && $end_time <= $start_time) {
        $errors['end_time'] = __('Ends before it starts.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $existing = null;
    if ($session_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $session_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Session not found.', 'sc_events')));
        }
    }

    $types = array('lecture', 'workshop', 'panel', 'keynote', 'break', 'networking', 'exhibition', 'poster', 'symposium', 'hands_on', 'other');
    $statuses = array('draft', 'published', 'live', 'ended', 'cancelled');
    $status = in_array($in('status'), $statuses, true) ? $in('status') : ($existing ? $existing->status : 'draft');
    $type = in_array($in('session_type'), $types, true) ? $in('session_type') : ($existing ? $existing->session_type : 'lecture');

    // Unique slug within the event; an existing session keeps its slug unless the title changes.
    $base_slug = sanitize_title($title) ?: 'session';
    $slug = $base_slug;
    $n = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE event_id = %d AND slug = %s AND id != %d", $event_id, $slug, $session_id))) {
        $slug = $base_slug . '-' . (++$n);
    }

    $data = array(
        'event_id'     => $event_id,
        'title'        => $title,
        'slug'         => $slug,
        'description'  => wp_kses_post($in('description')),
        'session_type' => $type,
        'track'        => sanitize_text_field($in('track')),
        'session_date' => $session_date,
        'start_time'   => $session_date . ' ' . $start_time . ':00',
        'end_time'     => $end_time !== '' ? $session_date . ' ' . $end_time . ':00' : null,
        'hall_name'    => sanitize_text_field($in('hall_name')),
        'capacity'     => max(0, intval($in('capacity', 0))),
        'status'       => $status,
        'is_published' => in_array($status, array('published', 'live'), true) ? 1 : 0,
        'sort_order'   => intval($in('sort_order', 0)),
        'updated_at'   => current_time('mysql'),
    );

    // Settings the older forms didn't send: only touch them when posted (defaults on create).
    $optional = array(
        'cme_hours'                 => array('floatval', 0),
        'cme_category'              => array('sanitize_text_field', ''),
        'enable_certificate'        => array(function ($v) { return !empty($v) ? 1 : 0; }, 0),
        'certificate_template_id'   => array(function ($v) { return absint($v) ?: null; }, null),
        'min_attendance_percentage' => array(function ($v) { return min(100, max(0, floatval($v))); }, 80),
        'require_registration'      => array(function ($v) { return !empty($v) ? 1 : 0; }, 0),
    );
    foreach ($optional as $column => $rule) {
        if (isset($_POST[$column])) {
            $data[$column] = call_user_func($rule[0], $in($column));
        } elseif (!$existing) {
            $data[$column] = $rule[1];
        }
    }

    // duration_minutes is generated on the production database; write it only where it is a plain column.
    $duration_extra = $wpdb->get_var($wpdb->prepare(
        'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
        $table,
        'duration_minutes'
    ));
    if ($duration_extra !== null && stripos((string) $duration_extra, 'generated') === false) {
        $data['duration_minutes'] = $end_time !== '' ? (int) ((strtotime($data['end_time']) - strtotime($data['start_time'])) / 60) : null;
    }

    if ($existing) {
        $result = $wpdb->update($table, $data, array('id' => $session_id));
        $message = __('Session updated successfully.', 'sc_events');
    } else {
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $result = $wpdb->insert($table, $data);
        $session_id = (int) $wpdb->insert_id;
        $message = __('Session created successfully.', 'sc_events');
    }
    if ($result === false || !$session_id) {
        wp_send_json_error(array('message' => __('Failed to save session.', 'sc_events')));
    }

    // Speakers: the form marks that it sent the list, so an empty list clears it.
    if (isset($_POST['speakers_present']) || (isset($_POST['speakers']) && is_array($_POST['speakers']))) {
        $speakers = isset($_POST['speakers']) && is_array($_POST['speakers']) ? wp_unslash($_POST['speakers']) : array();
        sc_save_session_speakers_data($session_id, $speakers);
    }

    wp_send_json_success(array(
        'message'    => $message,
        'session_id' => $session_id,
        'redirect'   => home_url('/event-manager-dashboard/session-edit?id=' . $session_id . '&created=1'),
    ));
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

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
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

    // The scanner page sends the attendee's ticket code from the QR.
    $ticket_code = sanitize_text_field($_POST['ticket_id'] ?? '');
    if (!$attendee_id && $ticket_code) {
        $attendee_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $attendees_table WHERE ticket_code = %s LIMIT 1",
            $ticket_code
        ));
        if (!$attendee_id) {
            wp_send_json_error(array('message' => __('Ticket not found in the system.', 'sc_events'), 'title' => 'Invalid Ticket'));
        }
    }

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

    // Attendees are only registered into the sessions that exist when they sign up. For a
    // session added later that doesn't need its own registration, anyone holding a valid
    // ticket for the event may attend: register them on the spot.
    if (!$registration && $session_id && $attendee_id) {
        $open_session = $wpdb->get_row($wpdb->prepare(
            "SELECT id, event_id FROM $sessions_table WHERE id = %d AND is_published = 1 AND (require_registration IS NULL OR require_registration = 0)",
            $session_id
        ));
        $holder = $open_session ? $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $attendees_table WHERE id = %d AND event_id = %d AND status = 'active' AND payment_status = 'success'",
            $attendee_id,
            (int) $open_session->event_id
        )) : null;
        if ($open_session && $holder && sc_scanner_can_access_session($session_id, (int) $open_session->event_id)) {
            $code = 'SES-' . $session_id . '-' . strtoupper(wp_generate_password(8, false));
            $wpdb->insert($reg_table, array(
                'session_id'        => $session_id,
                'attendee_id'       => $attendee_id,
                'event_id'          => (int) $open_session->event_id,
                'registration_code' => $code,
                'qr_code'           => wp_json_encode(array('type' => 'session', 'session_id' => $session_id, 'attendee_id' => $attendee_id, 'code' => $code)),
                'status'            => 'registered',
                'registered_at'     => current_time('mysql'),
            ));
            $wpdb->query($wpdb->prepare("UPDATE $sessions_table SET registered_count = registered_count + 1 WHERE id = %d", $session_id));
            $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $reg_table WHERE session_id = %d AND attendee_id = %d", $session_id, $attendee_id));
        } elseif ($open_session && !$holder) {
            wp_send_json_error(array('message' => __('This ticket is not for the event this session belongs to.', 'sc_events'), 'title' => 'Wrong Event'));
        }
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

    if (!sc_scanner_can_access_session($session_id, $event_id)) {
        wp_send_json_error(array('message' => __('You are not assigned to scan this session.', 'sc_events'), 'title' => 'Not Allowed'));
    }

    // Get attendee info
    $attendee = $wpdb->get_row($wpdb->prepare("SELECT name, email, phone, ticket_name FROM $attendees_table WHERE id = %d", $attendee_id));

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
            'attendee_phone' => $attendee ? $attendee->phone : '',
            'ticket_name' => $attendee ? $attendee->ticket_name : '',
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
        'attendee_phone' => $attendee ? $attendee->phone : '',
        'ticket_name' => $attendee ? $attendee->ticket_name : '',
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


// ==========================================
// SESSIONS LIST (list pattern)
// ==========================================
add_action('wp_ajax_sc_get_sessions_paginated', 'sc_get_sessions_paginated');
function sc_get_sessions_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
    global $wpdb;
    $p = $wpdb->prefix;

    $page     = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
    $per_page = min(200, max(10, absint(wp_unslash($_POST['per_page'] ?? 25))));
    $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $event_id = absint(wp_unslash($_POST['event_id'] ?? 0));
    $date     = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
    $status   = sanitize_key(wp_unslash($_POST['status'] ?? ''));

    $where = array('1=1');
    $values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(s.title LIKE %s OR s.track LIKE %s OR s.hall_name LIKE %s)';
        array_push($values, $like, $like, $like);
    }
    if ($event_id) {
        $where[] = 's.event_id = %d';
        $values[] = $event_id;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where[] = 's.session_date = %s';
        $values[] = $date;
    }
    $base = implode(' AND ', $where);
    $statuses = array('draft', 'published', 'live', 'ended', 'cancelled');
    $where_sql = $base . (in_array($status, $statuses, true) ? $wpdb->prepare(' AND s.status = %s', $status) : '');

    $sortable = array('title' => 's.title', 'session_date' => 's.session_date', 'registered' => 'registered', 'attended' => 'attended');
    $orderby = sanitize_key(wp_unslash($_POST['orderby'] ?? 'session_date'));
    $orderby = isset($sortable[$orderby]) ? $orderby : 'session_date';
    $order = sanitize_key(wp_unslash($_POST['order'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $order_sql = $sortable[$orderby] . ' ' . $order . ($orderby === 'session_date' ? ', s.start_time ' . $order : '') . ', s.sort_order, s.id';

    $prepare = function ($sql, $args) use ($wpdb) {
        return $args ? $wpdb->prepare($sql, $args) : $sql;
    };
    $total = (int) $wpdb->get_var($prepare("SELECT COUNT(*) FROM {$p}sc_sessions s WHERE {$where_sql}", $values));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.event_id, s.title, s.track, s.session_type, s.session_date, s.start_time, s.end_time, s.hall_name, s.capacity,
                s.status, s.cme_hours, s.enable_certificate, e.title AS event_title,
                (SELECT COUNT(*) FROM {$p}sc_session_registrations r WHERE r.session_id = s.id AND r.status <> 'cancelled') AS registered,
                (SELECT COUNT(*) FROM {$p}sc_session_attendance a WHERE a.session_id = s.id AND a.check_in_time IS NOT NULL) AS attended,
                (SELECT COUNT(*) FROM {$p}sc_session_speakers ss WHERE ss.session_id = s.id) AS speakers
         FROM {$p}sc_sessions s LEFT JOIN {$p}sc_events e ON e.id = s.event_id
         WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    foreach ($rows as $r) {
        foreach (array('id', 'event_id', 'capacity', 'registered', 'attended', 'speakers', 'enable_certificate') as $k) {
            $r->$k = (int) $r->$k;
        }
        $r->cme_hours = (float) $r->cme_hours;
        $r->start = $r->start_time ? substr($r->start_time, -8, 5) : '';
        $r->end = $r->end_time ? substr($r->end_time, -8, 5) : '';
    }

    $response = array('sessions' => $rows, 'total' => $total);
    if (!empty($_POST['with_counts'])) {
        $counts = $wpdb->get_row($prepare(
            "SELECT COUNT(*) AS `all`, " . implode(', ', array_map(function ($s) { return "COALESCE(SUM(s.status = '{$s}'), 0) AS `{$s}`"; }, $statuses)) . " FROM {$p}sc_sessions s WHERE {$base}",
            $values
        ), ARRAY_A);
        $response['counts'] = array_map('intval', (array) $counts);
    }
    wp_send_json_success($response);
}

endif; // if $sessions_module_active
