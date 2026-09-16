<?php
/**
 * Attendance Tracking AJAX Handlers
 * Handles Check-in/Check-out functionality for events
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if attendees module is enabled - if not, don't register any AJAX handlers
// Attendance tracking is part of the attendees module
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('attendees')) {
    return;
}

/**
 * Process Attendance Scan (Check-in / Check-out)
 * First scan = Check-in, Second scan = Check-out
 */
add_action('wp_ajax_sc_process_attendance_scan', 'sc_process_attendance_scan');
function sc_process_attendance_scan() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions - allow event_manager OR event_scanner
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Invalid attendee ID.', 'sc_events')));
    }

    // Get attendee
    $attendee = get_post($attendee_id);
    if (!$attendee || $attendee->post_type !== 'sc_attendee') {
        wp_send_json_error(array('message' => __('Attendee not found.', 'sc_events')));
    }

    // Get event ID from attendee if not provided
    if (!$event_id) {
        $event_id = get_post_meta($attendee_id, 'sc_event_id', true);
    }

    // Check if event has attendance tracking enabled
    $attendance_enabled = get_post_meta($event_id, 'sc_attendance_tracking', true);
    if ($attendance_enabled !== 'yes') {
        wp_send_json_error(array('message' => __('Attendance tracking is not enabled for this event.', 'sc_events')));
    }

    // Get current attendance log
    $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
    if (!is_array($attendance_log)) {
        $attendance_log = array();
    }

    // Get today's date
    $today = current_time('Y-m-d');
    $current_time = current_time('timestamp');

    // Find today's entries
    $today_entries = array_filter($attendance_log, function($entry) use ($today) {
        return isset($entry['date']) && $entry['date'] === $today;
    });

    // Determine if this is check-in or check-out
    $last_today_entry = !empty($today_entries) ? end($today_entries) : null;
    $scan_type = 'check_in';

    if ($last_today_entry && isset($last_today_entry['type'])) {
        // If last entry was check-in, this is check-out
        if ($last_today_entry['type'] === 'check_in') {
            $scan_type = 'check_out';
        }
    }

    // Add new entry
    $new_entry = array(
        'type' => $scan_type,
        'timestamp' => $current_time,
        'date' => $today,
        'time' => date('H:i:s', $current_time),
        'event_id' => $event_id
    );

    // Calculate duration if this is check-out
    $duration_formatted = '';
    if ($scan_type === 'check_out' && $last_today_entry) {
        $check_in_time = $last_today_entry['timestamp'];
        $duration = $current_time - $check_in_time;
        $new_entry['duration_seconds'] = $duration;

        // Format duration
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $duration_formatted = sprintf('%02d:%02d', $hours, $minutes);
        $new_entry['duration_formatted'] = $duration_formatted;
    }

    $attendance_log[] = $new_entry;
    update_post_meta($attendee_id, 'sc_attendance_log', $attendance_log);

    // Update last scan info
    update_post_meta($attendee_id, 'sc_last_scan_type', $scan_type);
    update_post_meta($attendee_id, 'sc_last_scan_time', $current_time);

    // Get attendee info for response
    $attendee_name = get_post_meta($attendee_id, 'sc_name', true);
    $ticket_name = get_post_meta($attendee_id, 'sc_ticket_name', true);
    $event = get_post($event_id);

    // Build response
    $response = array(
        'success' => true,
        'scan_type' => $scan_type,
        'message' => $scan_type === 'check_in'
            ? sprintf(__('%s checked in successfully!', 'sc_events'), $attendee_name)
            : sprintf(__('%s checked out successfully!', 'sc_events'), $attendee_name),
        'attendee' => array(
            'id' => $attendee_id,
            'name' => $attendee_name,
            'ticket' => $ticket_name,
            'event' => $event ? $event->post_title : '',
        ),
        'scan_time' => date('h:i A', $current_time),
        'scan_date' => date('M d, Y', $current_time),
    );

    if ($scan_type === 'check_out' && $duration_formatted) {
        $response['duration'] = $duration_formatted;
        $response['message'] .= sprintf(__(' Duration: %s', 'sc_events'), $duration_formatted);
    }

    // Count today's scans
    $today_check_ins = count(array_filter($attendance_log, function($e) use ($today) {
        return isset($e['date']) && $e['date'] === $today && $e['type'] === 'check_in';
    }));
    $today_check_outs = count(array_filter($attendance_log, function($e) use ($today) {
        return isset($e['date']) && $e['date'] === $today && $e['type'] === 'check_out';
    }));

    $response['today_stats'] = array(
        'check_ins' => $today_check_ins,
        'check_outs' => $today_check_outs
    );

    wp_send_json_success($response);
}

/**
 * Get Attendee Attendance Log
 */
add_action('wp_ajax_sc_get_attendance_log', 'sc_get_attendance_log');
function sc_get_attendance_log() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;

    if (!$attendee_id) {
        wp_send_json_error(array('message' => __('Invalid attendee ID.', 'sc_events')));
    }

    // Get attendance log
    $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
    if (!is_array($attendance_log)) {
        $attendance_log = array();
    }

    // Format log for display
    $formatted_log = array();
    foreach ($attendance_log as $entry) {
        $formatted_log[] = array(
            'type' => $entry['type'],
            'type_label' => $entry['type'] === 'check_in' ? __('Check-in', 'sc_events') : __('Check-out', 'sc_events'),
            'date' => date('M d, Y', strtotime($entry['date'])),
            'time' => date('h:i A', $entry['timestamp']),
            'duration' => isset($entry['duration_formatted']) ? $entry['duration_formatted'] : '-'
        );
    }

    // Calculate total attendance time
    $total_duration = 0;
    foreach ($attendance_log as $entry) {
        if (isset($entry['duration_seconds'])) {
            $total_duration += $entry['duration_seconds'];
        }
    }

    $total_hours = floor($total_duration / 3600);
    $total_minutes = floor(($total_duration % 3600) / 60);
    $total_formatted = sprintf('%02d:%02d', $total_hours, $total_minutes);

    wp_send_json_success(array(
        'log' => $formatted_log,
        'total_duration' => $total_formatted,
        'total_check_ins' => count(array_filter($attendance_log, function($e) { return $e['type'] === 'check_in'; })),
        'total_check_outs' => count(array_filter($attendance_log, function($e) { return $e['type'] === 'check_out'; }))
    ));
}

/**
 * Get Event Attendance Summary
 */
add_action('wp_ajax_sc_get_event_attendance_summary', 'sc_get_event_attendance_summary');
function sc_get_event_attendance_summary() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');

    if (!$event_id) {
        wp_send_json_error(array('message' => __('Invalid event ID.', 'sc_events')));
    }

    global $wpdb;

    // Get all attendees for this event
    $attendees = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'sc_attendee'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'sc_event_id'
        AND pm.meta_value = %d
    ", $event_id));

    $summary = array(
        'total_attendees' => count($attendees),
        'checked_in_today' => 0,
        'checked_out_today' => 0,
        'currently_present' => 0,
        'not_arrived' => 0,
        'attendees_list' => array()
    );

    // Performance optimization: Prime meta cache for all attendees at once
    if (!empty($attendees)) {
        $attendee_ids = wp_list_pluck($attendees, 'ID');
        update_meta_cache('post', $attendee_ids);
    }

    foreach ($attendees as $attendee) {
        $attendance_log = get_post_meta($attendee->ID, 'sc_attendance_log', true);
        if (!is_array($attendance_log)) {
            $attendance_log = array();
        }

        $name = get_post_meta($attendee->ID, 'sc_name', true);
        $ticket = get_post_meta($attendee->ID, 'sc_ticket_name', true);

        // Get today's entries
        $today_entries = array_filter($attendance_log, function($entry) use ($date) {
            return isset($entry['date']) && $entry['date'] === $date;
        });

        $today_check_ins = array_filter($today_entries, function($e) { return $e['type'] === 'check_in'; });
        $today_check_outs = array_filter($today_entries, function($e) { return $e['type'] === 'check_out'; });

        $last_entry = !empty($today_entries) ? end($today_entries) : null;
        $status = 'not_arrived';
        $last_action_time = '';

        if ($last_entry) {
            if ($last_entry['type'] === 'check_in') {
                $status = 'present';
                $summary['currently_present']++;
            } else {
                $status = 'left';
            }
            $last_action_time = date('h:i A', $last_entry['timestamp']);
        } else {
            $summary['not_arrived']++;
        }

        if (count($today_check_ins) > 0) {
            $summary['checked_in_today']++;
        }
        if (count($today_check_outs) > 0) {
            $summary['checked_out_today']++;
        }

        // Calculate total duration for today
        $total_duration = 0;
        foreach ($today_entries as $entry) {
            if (isset($entry['duration_seconds'])) {
                $total_duration += $entry['duration_seconds'];
            }
        }

        $summary['attendees_list'][] = array(
            'id' => $attendee->ID,
            'name' => $name,
            'ticket' => $ticket,
            'status' => $status,
            'check_ins' => count($today_check_ins),
            'check_outs' => count($today_check_outs),
            'last_action' => $last_action_time,
            'total_duration' => $total_duration > 0 ? sprintf('%02d:%02d', floor($total_duration / 3600), floor(($total_duration % 3600) / 60)) : '-'
        );
    }

    wp_send_json_success($summary);
}

/**
 * Verify Ticket for Scanner (returns attendee info from ticket ID)
 */
add_action('wp_ajax_sc_verify_ticket_for_scan', 'sc_verify_ticket_for_scan');
function sc_verify_ticket_for_scan() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions - allow event_manager OR event_scanner
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $ticket_id = isset($_POST['ticket_id']) ? sanitize_text_field($_POST['ticket_id']) : '';
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (empty($ticket_id)) {
        wp_send_json_error(array('message' => __('Invalid ticket ID.', 'sc_events')));
    }

    global $wpdb;

    // Find attendee by ticket ID
    $attendee_id = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'sc_attendee'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'sc_unique_ticket_id'
        AND pm.meta_value = %s
    ", $ticket_id));

    if (!$attendee_id) {
        wp_send_json_error(array(
            'message' => __('Ticket not found!', 'sc_events'),
            'status' => 'invalid'
        ));
    }

    // Get attendee's event
    $attendee_event_id = get_post_meta($attendee_id, 'sc_event_id', true);

    // If event_id filter is set, check if ticket belongs to that event
    if ($event_id && $attendee_event_id != $event_id) {
        wp_send_json_error(array(
            'message' => __('This ticket is for a different event!', 'sc_events'),
            'status' => 'wrong_event'
        ));
    }

    // Check if event has attendance tracking enabled
    $attendance_enabled = get_post_meta($attendee_event_id, 'sc_attendance_tracking', true);
    if ($attendance_enabled !== 'yes') {
        wp_send_json_error(array(
            'message' => __('Attendance tracking is not enabled for this event.', 'sc_events'),
            'status' => 'tracking_disabled'
        ));
    }

    // Get attendee info
    $attendee_name = get_post_meta($attendee_id, 'sc_name', true);
    $ticket_name = get_post_meta($attendee_id, 'sc_ticket_name', true);
    $event = get_post($attendee_event_id);

    // Get current attendance status
    $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
    if (!is_array($attendance_log)) {
        $attendance_log = array();
    }

    $today = current_time('Y-m-d');
    $today_entries = array_filter($attendance_log, function($entry) use ($today) {
        return isset($entry['date']) && $entry['date'] === $today;
    });

    $last_entry = !empty($today_entries) ? end($today_entries) : null;
    $next_action = 'check_in';
    $current_status = 'not_arrived';

    if ($last_entry) {
        if ($last_entry['type'] === 'check_in') {
            $next_action = 'check_out';
            $current_status = 'present';
        } else {
            $next_action = 'check_in';
            $current_status = 'left';
        }
    }

    wp_send_json_success(array(
        'status' => 'valid',
        'attendee_id' => $attendee_id,
        'attendee_name' => $attendee_name,
        'ticket_name' => $ticket_name,
        'event_id' => $attendee_event_id,
        'event_name' => $event ? $event->post_title : '',
        'current_status' => $current_status,
        'next_action' => $next_action,
        'today_scans' => count($today_entries),
        'total_scans' => count($attendance_log)
    ));
}

/**
 * Manual Check-in/Check-out (without QR scan)
 */
add_action('wp_ajax_sc_manual_attendance', 'sc_manual_attendance');
function sc_manual_attendance() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $attendee_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

    if (!$attendee_id || !in_array($action_type, array('check_in', 'check_out'))) {
        wp_send_json_error(array('message' => __('Invalid parameters.', 'sc_events')));
    }

    // Get event ID
    $event_id = get_post_meta($attendee_id, 'sc_event_id', true);

    // Check if event has attendance tracking enabled
    $attendance_enabled = get_post_meta($event_id, 'sc_attendance_tracking', true);
    if ($attendance_enabled !== 'yes') {
        wp_send_json_error(array('message' => __('Attendance tracking is not enabled for this event.', 'sc_events')));
    }

    // Get current attendance log
    $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
    if (!is_array($attendance_log)) {
        $attendance_log = array();
    }

    $today = current_time('Y-m-d');
    $current_time = current_time('timestamp');

    // Find last entry for today
    $today_entries = array_filter($attendance_log, function($entry) use ($today) {
        return isset($entry['date']) && $entry['date'] === $today;
    });
    $last_entry = !empty($today_entries) ? end($today_entries) : null;

    // Add new entry
    $new_entry = array(
        'type' => $action_type,
        'timestamp' => $current_time,
        'date' => $today,
        'time' => date('H:i:s', $current_time),
        'event_id' => $event_id,
        'manual' => true
    );

    // Calculate duration if check-out
    if ($action_type === 'check_out' && $last_entry && $last_entry['type'] === 'check_in') {
        $duration = $current_time - $last_entry['timestamp'];
        $new_entry['duration_seconds'] = $duration;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $new_entry['duration_formatted'] = sprintf('%02d:%02d', $hours, $minutes);
    }

    $attendance_log[] = $new_entry;
    update_post_meta($attendee_id, 'sc_attendance_log', $attendance_log);

    // Update last scan info
    update_post_meta($attendee_id, 'sc_last_scan_type', $action_type);
    update_post_meta($attendee_id, 'sc_last_scan_time', $current_time);

    $attendee_name = get_post_meta($attendee_id, 'sc_name', true);

    wp_send_json_success(array(
        'message' => $action_type === 'check_in'
            ? sprintf(__('%s manually checked in.', 'sc_events'), $attendee_name)
            : sprintf(__('%s manually checked out.', 'sc_events'), $attendee_name),
        'action_type' => $action_type,
        'time' => date('h:i A', $current_time)
    ));
}

/**
 * Scan and Auto Check-in (Combined action)
 * - Works for ALL events
 * - Marks ticket as "used"
 * - Returns all attendee data + extra fields
 * - Only tracks time if attendance tracking is enabled
 */
add_action('wp_ajax_sc_scan_and_checkin', 'sc_scan_and_checkin');
function sc_scan_and_checkin() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events'), 'title' => 'Security Error', 'code' => 'nonce'));
    }

    // Check permissions - allow event_manager OR event_scanner
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events'), 'title' => 'Access Denied'));
    }

    $ticket_id = isset($_POST['ticket_id']) ? sanitize_text_field($_POST['ticket_id']) : '';
    $filter_event_id = isset($_POST['event_id']) && !empty($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $filter_workshop_id = isset($_POST['workshop_id']) && !empty($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;
    $gate_id = isset($_POST['gate_id']) && !empty($_POST['gate_id']) ? intval($_POST['gate_id']) : 0;

    if (empty($ticket_id)) {
        wp_send_json_error(array('message' => __('No ticket ID provided.', 'sc_events'), 'title' => 'Invalid Input'));
    }

    global $wpdb;

    // Company (exhibitor) badges carry COMP-XXXX-XXXX; staff scan them with the same scanner.
    if (preg_match('/^COMP-[A-Z0-9]+-[A-Z0-9]+$/i', $ticket_id)) {
        sc_scan_company_badge(strtoupper($ticket_id), $filter_event_id);
    }

    // ─────────────────────────────────────────────────────────────
    // NEW SYSTEM: Lookup in scev_sc_attendees by ticket_code
    // ─────────────────────────────────────────────────────────────
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $events_table    = $wpdb->prefix . 'sc_events';
    $sc_attendee = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, e.title AS event_title, e.attendance_tracking
         FROM {$attendees_table} a
         LEFT JOIN {$events_table} e ON a.event_id = e.id
         WHERE a.ticket_code = %s
         LIMIT 1",
        $ticket_id
    ));

    if ($sc_attendee) {
        if (!sc_scanner_can_access_event($sc_attendee->event_id)) {
            wp_send_json_error(array(
                'message' => __('You are not assigned to scan tickets for this event.', 'sc_events'),
                'title'   => 'Not Allowed',
            ));
        }

        $client_ref = isset($_POST['client_ref']) && function_exists('sc_scanner_clean_ref') ? sc_scanner_clean_ref(wp_unslash($_POST['client_ref'])) : '';
        $scan = sc_scanner_record_attendee_scan($sc_attendee, array(
            'workshop_id' => $filter_workshop_id,
            'event_id'    => $filter_event_id,
            'gate_id'     => $gate_id,
            'client_ref'  => $client_ref,
        ));
        if (!$scan['ok']) {
            wp_send_json_error(array('message' => $scan['message'], 'title' => $scan['title'], 'code' => $scan['code']));
        }
        $current_time = current_time('timestamp');

        // Decode extra_fields JSON for display
        $extra_fields = array();
        if (!empty($sc_attendee->extra_fields)) {
            $decoded = json_decode($sc_attendee->extra_fields, true);
            if (is_array($decoded)) {
                $extra_fields = $decoded;
            }
        }

        wp_send_json_success(array(
            'action_type'      => $scan['action_type'],
            'already_checked_in' => !empty($scan['already_checked_in']),
            // UTC ISO time; the scanner shows it in the device's own time zone.
            'first_checked_in_at' => isset($scan['first_checked_in_at']) ? $scan['first_checked_in_at'] : '',
            'scan_time'        => date('h:i A', $current_time),
            'scan_date'        => date('M d, Y', $current_time),
            'tracking_enabled' => (int) $sc_attendee->attendance_tracking === 1,
            'total_scans'      => isset($scan['total_scans']) ? $scan['total_scans'] : 1,
            'duration'         => isset($scan['duration']) ? $scan['duration'] : '',
            'gate'             => isset($scan['gate']) ? $scan['gate'] : null,
            'attendee' => array(
                'id'          => (int) $sc_attendee->id,
                'name'        => $sc_attendee->name,
                'email'       => $sc_attendee->email,
                'phone'       => $sc_attendee->phone,
                'ticket_type' => $sc_attendee->ticket_name,
                'event_id'    => (int) $sc_attendee->event_id,
                'event_name'  => $sc_attendee->event_title,
            ),
            'extra_fields' => $extra_fields,
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // LEGACY FALLBACK: Lookup in old wp_posts (sc_attendee post type)
    // ─────────────────────────────────────────────────────────────
    $attendee_id = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'sc_attendee'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'sc_unique_ticket_id'
        AND pm.meta_value = %s
    ", $ticket_id));

    if (!$attendee_id) {
        wp_send_json_error(array(
            'message' => __('Ticket not found in the system.', 'sc_events'),
            'title' => 'Invalid Ticket'
        ));
    }

    // Get attendee's event
    $event_id = get_post_meta($attendee_id, 'sc_event_id', true);

    if (!sc_scanner_can_access_event($event_id)) {
        wp_send_json_error(array(
            'message' => __('You are not assigned to scan tickets for this event.', 'sc_events'),
            'title' => 'Not Allowed'
        ));
    }

    // If filtering by event, check if ticket belongs to that event
    if ($filter_event_id && $event_id != $filter_event_id) {
        $filter_event = get_post($filter_event_id);
        wp_send_json_error(array(
            'message' => sprintf(__('This ticket belongs to a different event. Expected: %s', 'sc_events'), $filter_event ? $filter_event->post_title : 'Unknown'),
            'title' => 'Wrong Event'
        ));
    }

    // Get event info
    $event = get_post($event_id);
    $tracking_enabled = get_post_meta($event_id, 'sc_attendance_tracking', true) === 'yes';

    // Get all attendee meta
    $attendee_name = get_post_meta($attendee_id, 'sc_name', true);
    $attendee_email = get_post_meta($attendee_id, 'sc_email', true);
    $attendee_phone = get_post_meta($attendee_id, 'sc_phone', true);
    $ticket_type = get_post_meta($attendee_id, 'sc_ticket_name', true);

    // Mark ticket as USED (Eventin format)
    update_post_meta($attendee_id, 'sc_attendee_ticket_status', 'used');
    update_post_meta($attendee_id, 'scanner_update_time', current_time('mysql'));

    $current_time = current_time('timestamp');
    $today = current_time('Y-m-d');
    $action_type = 'check_in';
    $duration = '';
    $total_scans = 0;
    $gate_info = null;

    // Log through gate if gate_id is provided
    if ($gate_id && class_exists('SC_Gate')) {
        $gate = SC_Gate::get($gate_id);
        if ($gate) {
            $gate_info = array(
                'id' => $gate->id,
                'name' => $gate->name,
                'zone_id' => $gate->zone_id,
            );

            // Log entry through gate (this also updates zone count automatically)
            SC_Gate::log_entry($gate_id, 1, array(
                'attendee_id' => $attendee_id,
                'ticket_code' => $ticket_id,
                'scan_method' => 'qr',
                'scanned_by' => get_current_user_id(),
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'is_valid' => 1,
            ));
        }
    }

    // If tracking is enabled, handle check-in/check-out logic
    if ($tracking_enabled) {
        $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
        if (!is_array($attendance_log)) {
            $attendance_log = array();
        }

        // Get today's entries
        $today_entries = array_filter($attendance_log, function($entry) use ($today) {
            return isset($entry['date']) && $entry['date'] === $today;
        });

        // Determine action type
        $last_today_entry = !empty($today_entries) ? end($today_entries) : null;
        if ($last_today_entry && isset($last_today_entry['type']) && $last_today_entry['type'] === 'check_in') {
            $action_type = 'check_out';
        }

        // Create new entry
        $new_entry = array(
            'type' => $action_type,
            'timestamp' => $current_time,
            'date' => $today,
            'time' => date('H:i:s', $current_time),
            'event_id' => $event_id
        );

        // Calculate duration if check-out
        if ($action_type === 'check_out' && $last_today_entry) {
            $duration_seconds = $current_time - $last_today_entry['timestamp'];
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $duration = sprintf('%02d:%02d', $hours, $minutes);
            $new_entry['duration_seconds'] = $duration_seconds;
            $new_entry['duration_formatted'] = $duration;
        }

        $attendance_log[] = $new_entry;
        update_post_meta($attendee_id, 'sc_attendance_log', $attendance_log);
        update_post_meta($attendee_id, 'sc_last_scan_type', $action_type);
        update_post_meta($attendee_id, 'sc_last_scan_time', $current_time);

        // Count today's scans
        $total_scans = count(array_filter($attendance_log, function($e) use ($today) {
            return isset($e['date']) && $e['date'] === $today;
        }));
    }

    // Get extra fields for this event and attendee
    $extra_fields = array();
    $event_extra_fields = get_post_meta($event_id, 'attendee_extra_fields', true);
    if (is_array($event_extra_fields) && !empty($event_extra_fields)) {
        foreach ($event_extra_fields as $field) {
            $field_name = isset($field['sc_field_label']) ? $field['sc_field_label'] : (isset($field['label']) ? $field['label'] : '');
            $field_key = isset($field['sc_field_slug']) ? $field['sc_field_slug'] : (isset($field['name']) ? $field['name'] : sanitize_title($field_name));

            if (!empty($field_name)) {
                // Try different meta key formats
                $value = get_post_meta($attendee_id, $field_key, true);
                if (empty($value)) {
                    $value = get_post_meta($attendee_id, 'sc_' . $field_key, true);
                }
                if (empty($value)) {
                    $value = get_post_meta($attendee_id, 'extra_field_' . $field_key, true);
                }
                $extra_fields[$field_name] = $value;
            }
        }
    }

    // Also check for common extra fields stored by Eventin (note: triple 'e' in attendeee)
    $eventin_extra = get_post_meta($attendee_id, 'sc_attendee_extra_field', true);
    if (is_array($eventin_extra)) {
        foreach ($eventin_extra as $key => $value) {
            if (!empty($value) && !isset($extra_fields[$key])) {
                $extra_fields[$key] = $value;
            }
        }
    }

    // Also check single 'e' version for backwards compatibility
    $eventin_extra_alt = get_post_meta($attendee_id, 'sc_attendee_extra_field', true);
    if (is_array($eventin_extra_alt)) {
        foreach ($eventin_extra_alt as $key => $value) {
            if (!empty($value) && !isset($extra_fields[$key])) {
                $extra_fields[$key] = $value;
            }
        }
    }

    // Build response
    wp_send_json_success(array(
        'action_type' => $action_type,
        'scan_time' => date('h:i A', $current_time),
        'scan_date' => date('M d, Y', $current_time),
        'tracking_enabled' => $tracking_enabled,
        'total_scans' => $total_scans,
        'duration' => $duration,
        'gate' => $gate_info,
        'attendee' => array(
            'id' => $attendee_id,
            'name' => $attendee_name,
            'email' => $attendee_email,
            'phone' => $attendee_phone,
            'ticket_type' => $ticket_type,
            'event_id' => $event_id,
            'event_name' => $event ? $event->post_title : ''
        ),
        'extra_fields' => $extra_fields
    ));
}

/**
 * Get Detailed Attendance Per Day for an Attendee
 * Shows each day with check-in/check-out times, duration, and handles missing checkouts
 */
add_action('wp_ajax_sc_get_attendance_details', 'sc_get_attendance_details');
/**
 * Per-day attendance for one attendee, from the check-in log.
 *
 * Used to read legacy sc_attendee posts, so it failed for every attendee in the
 * custom tables. Scanner rows are "checkin"/"checkout"; SC_Checkin::log writes
 * "check_in"/"check_out". A day left open gets an automatic check-out at the
 * event's end time once that time has passed.
 */
function sc_get_attendance_details() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $attendee_id = isset($_POST['attendee_id']) ? absint($_POST['attendee_id']) : 0;
    $attendee = $attendee_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT a.id, a.name, a.event_id, a.checked_in_at, e.title, e.start_date, e.end_date, e.end_time, e.attendance_tracking
         FROM {$wpdb->prefix}sc_attendees a
         LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = a.event_id
         WHERE a.id = %d",
        $attendee_id
    )) : null;
    if (!$attendee) {
        wp_send_json_error(array('message' => __('Attendee not found.', 'sc_events')));
    }

    $start_date = $attendee->start_date ?: current_time('Y-m-d');
    $end_date   = $attendee->end_date ?: $start_date;
    $end_time   = $attendee->end_time ?: '23:59:59';
    $tracking   = (int) $attendee->attendance_tracking === 1;

    $event_days = array();
    $period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
    foreach ($period as $date) {
        $event_days[] = $date->format('Y-m-d');
    }

    $log = $wpdb->get_results($wpdb->prepare(
        "SELECT action, created_at FROM {$wpdb->prefix}sc_checkins WHERE attendee_id = %d ORDER BY created_at ASC, id ASC",
        $attendee_id
    ));
    $by_day = array();
    foreach ($log as $row) {
        $by_day[substr($row->created_at, 0, 10)][] = $row;
    }
    // Attendees checked in before the log existed only have checked_in_at.
    if (!$log && $attendee->checked_in_at) {
        $by_day[substr($attendee->checked_in_at, 0, 10)][] = (object) array('action' => 'checkin', 'created_at' => $attendee->checked_in_at);
    }

    $fmt = function ($seconds) {
        return sprintf('%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60));
    };
    $now = current_time('timestamp');
    $today = current_time('Y-m-d');
    $daily = array();
    $total_seconds = 0;
    $days_without_checkout = 0;

    foreach ($event_days as $day) {
        $sessions = array();
        $open = null;
        foreach (isset($by_day[$day]) ? $by_day[$day] : array() as $entry) {
            $ts = strtotime($entry->created_at);
            if (in_array($entry->action, array('checkin', 'check_in', 'manual_checkin'), true)) {
                if ($open === null) {
                    $open = $ts;
                }
            } elseif (in_array($entry->action, array('checkout', 'check_out'), true) && $open !== null) {
                $sessions[] = array('in' => $open, 'out' => $ts, 'auto' => false);
                $open = null;
            }
        }

        $missing = false;
        $still_in = false;
        if ($open !== null) {
            $day_end = strtotime($day . ' ' . $end_time);
            if ($day < $today || $now > $day_end) {
                $sessions[] = array('in' => $open, 'out' => max($open, $day_end), 'auto' => true);
                $missing = true;
                $days_without_checkout++;
            } else {
                $sessions[] = array('in' => $open, 'out' => null, 'auto' => false);
                $still_in = true;
            }
        }

        $day_seconds = 0;
        $out_sessions = array();
        foreach ($sessions as $sess) {
            $duration = $sess['out'] ? $sess['out'] - $sess['in'] : 0;
            $day_seconds += $duration;
            $out_sessions[] = array(
                'check_in_time'      => date('h:i A', $sess['in']),
                'check_out_time'     => $sess['out'] ? date('h:i A', $sess['out']) . ($sess['auto'] ? ' (Auto)' : '') : __('Still checked in', 'sc_events'),
                'duration_seconds'   => $duration,
                'duration_formatted' => $sess['out'] ? $fmt($duration) : __('In progress', 'sc_events'),
                'auto_checkout'      => $sess['auto'],
            );
        }
        $total_seconds += $day_seconds;

        $daily[] = array(
            'date'                     => $day,
            'date_formatted'           => date('D, M d, Y', strtotime($day)),
            'sessions'                 => $out_sessions,
            'total_duration'           => $day_seconds,
            'total_duration_formatted' => $fmt($day_seconds),
            'missing_checkout'         => $missing,
            'still_checked_in'         => $still_in,
            'has_attendance'           => !empty($out_sessions),
        );
    }

    wp_send_json_success(array(
        'attendee' => array(
            'id'         => (int) $attendee->id,
            'name'       => $attendee->name,
            'event_id'   => (int) $attendee->event_id,
            'event_name' => $attendee->title ?: '',
        ),
        'event' => array(
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'end_time'         => $end_time,
            'total_days'       => count($event_days),
            'tracking_enabled' => $tracking,
        ),
        'daily_attendance' => $daily,
        'summary' => array(
            'total_duration_seconds'   => $total_seconds,
            'total_duration_formatted' => $fmt($total_seconds),
            'days_attended'            => count(array_filter($daily, function ($d) { return $d['has_attendance']; })),
            'days_without_checkout'    => $days_without_checkout,
            'total_event_days'         => count($event_days),
        ),
    ));
}

/**
 * Check in a company (exhibitor) badge from the attendance scanner. Sends the
 * same response shape as an attendee scan so the scanner's result screen works.
 *
 * @param string $code            Company code (COMP-XXXX-XXXX)
 * @param int    $filter_event_id Event chosen in the scanner, 0 for any
 */
function sc_scan_company_badge($code, $filter_event_id) {
    global $wpdb;
    $company = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, e.title AS event_title FROM {$wpdb->prefix}sc_company_attendees c LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = c.event_id WHERE c.company_code = %s LIMIT 1",
        $code
    ));
    if (!$company) {
        wp_send_json_error(array('message' => __('This company badge was not found.', 'sc_events'), 'title' => 'Invalid Badge'));
    }
    if (!sc_scanner_can_access_event($company->event_id)) {
        wp_send_json_error(array('message' => __('You are not assigned to scan tickets for this event.', 'sc_events'), 'title' => 'Not Allowed'));
    }
    if ($filter_event_id && (int) $company->event_id !== (int) $filter_event_id) {
        $expected = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}sc_events WHERE id = %d", $filter_event_id));
        wp_send_json_error(array('message' => sprintf(__('This badge belongs to a different event. Expected: %s', 'sc_events'), $expected ?: 'Unknown'), 'title' => 'Wrong Event'));
    }
    if ($company->status !== 'active') {
        wp_send_json_error(array('message' => __('This company registration has been cancelled.', 'sc_events'), 'title' => 'Badge Inactive'));
    }
    if ($company->payment_status !== 'success') {
        wp_send_json_error(array('message' => __('Payment for this company is not confirmed yet.', 'sc_events'), 'title' => 'Not Paid'));
    }

    $already = (int) $company->checked_in === 1;
    if (!$already && class_exists('SC_Company_Attendee')) {
        SC_Company_Attendee::check_in((int) $company->id, get_current_user_id());
    }
    $now = current_time('timestamp');
    $details = array_filter(array(
        'Contact'     => trim($company->contact_name . ($company->contact_title ? ' — ' . $company->contact_title : '')),
        'Booth'       => $company->booth_number,
        'Sponsorship' => $company->sponsorship_level,
        'Company code'=> $company->company_code,
    ));
    wp_send_json_success(array(
        'action_type'        => 'check_in',
        'already_checked_in' => $already,
        'is_company'         => true,
        'scan_time'          => date('h:i A', $now),
        'scan_date'          => date('M d, Y', $now),
        'tracking_enabled'   => false,
        'total_scans'        => 0,
        'duration'           => '',
        'gate'               => null,
        'attendee'           => array(
            'id'          => (int) $company->id,
            'name'        => $company->company_name,
            'email'       => $company->contact_email,
            'phone'       => $company->contact_phone,
            'ticket_type' => __('Company / exhibitor', 'sc_events'),
            'event_id'    => (int) $company->event_id,
            'event_name'  => $company->event_title,
        ),
        'extra_fields'       => $details,
    ));
}
