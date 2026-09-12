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
    $today = date('Y-m-d');
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
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

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

    $today = date('Y-m-d');
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

    $today = date('Y-m-d');
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
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events'), 'title' => 'Security Error'));
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

    // ─────────────────────────────────────────────────────────────
    // NEW SYSTEM: Lookup in scev_sc_attendees by ticket_code
    // ─────────────────────────────────────────────────────────────
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $events_table    = $wpdb->prefix . 'sc_events';
    $checkins_table  = $wpdb->prefix . 'sc_checkins';

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

        // Validate active status
        if ($sc_attendee->status !== 'active') {
            wp_send_json_error(array(
                'message' => __('This ticket has been cancelled or transferred.', 'sc_events'),
                'title'   => 'Ticket Inactive',
            ));
        }

        // Validate payment confirmed
        if ($sc_attendee->payment_status !== 'success' && (float) $sc_attendee->ticket_price > 0) {
            wp_send_json_error(array(
                'message' => __('Payment for this ticket has not been confirmed yet.', 'sc_events'),
                'title'   => 'Payment Pending',
            ));
        }

        // Filter by selected workshop if any (must come BEFORE event filter)
        if ($filter_workshop_id) {
            if ((int) $sc_attendee->workshop_id !== $filter_workshop_id) {
                $workshops_table = $wpdb->prefix . 'sc_workshops';
                $expected_workshop = $wpdb->get_var($wpdb->prepare(
                    "SELECT title FROM {$workshops_table} WHERE id = %d",
                    $filter_workshop_id
                ));
                wp_send_json_error(array(
                    'message' => sprintf(__('This ticket is not registered for this workshop. Expected: %s', 'sc_events'), $expected_workshop ?: 'Unknown'),
                    'title'   => 'Wrong Workshop',
                ));
            }
        } elseif ($filter_event_id) {
            // When scanning at event-level, only accept event-only attendees (workshop_id IS NULL)
            // OR all attendees of that event including those of its workshops?
            // Plan: event scanner accepts ONLY event-only attendees; workshop scanner accepts ONLY workshop attendees of that workshop.
            if ((int) $sc_attendee->event_id !== $filter_event_id) {
                $expected = $wpdb->get_var($wpdb->prepare(
                    "SELECT title FROM {$events_table} WHERE id = %d",
                    $filter_event_id
                ));
                wp_send_json_error(array(
                    'message' => sprintf(__('This ticket belongs to a different event. Expected: %s', 'sc_events'), $expected ?: 'Unknown'),
                    'title'   => 'Wrong Event',
                ));
            }
            if (!empty($sc_attendee->workshop_id)) {
                wp_send_json_error(array(
                    'message' => __('This is a workshop ticket. Use the workshop scanner instead.', 'sc_events'),
                    'title'   => 'Workshop Ticket',
                ));
            }
        }

        $now              = current_time('mysql');
        $current_time     = current_time('timestamp');
        $tracking_enabled = (int) $sc_attendee->attendance_tracking === 1;
        $action_type      = 'check_in';
        $duration         = '';
        $gate_info        = null;

        // If tracking enabled, decide check_in vs check_out from the last log entry today
        if ($tracking_enabled) {
            $today = date('Y-m-d');
            $last = $wpdb->get_row($wpdb->prepare(
                "SELECT action, created_at FROM {$checkins_table}
                 WHERE attendee_id = %d AND DATE(created_at) = %s
                 ORDER BY created_at DESC LIMIT 1",
                $sc_attendee->id, $today
            ));
            if ($last && in_array($last->action, array('checkin', 'manual_checkin'), true)) {
                $action_type = 'check_out';
                $duration_seconds = $current_time - strtotime($last->created_at);
                $hours   = floor($duration_seconds / 3600);
                $minutes = floor(($duration_seconds % 3600) / 60);
                $duration = sprintf('%02d:%02d', $hours, $minutes);
            }
        }

        // Insert check-in log row
        $wpdb->insert($checkins_table, array(
            'attendee_id'  => (int) $sc_attendee->id,
            'event_id'     => (int) $sc_attendee->event_id,
            'workshop_id'  => !empty($sc_attendee->workshop_id) ? (int) $sc_attendee->workshop_id : null,
            'action'       => $action_type === 'check_out' ? 'checkout' : 'checkin',
            'scanned_by'   => get_current_user_id(),
            'scan_method'  => 'qr',
            'device_info'  => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
            'created_at'   => $now,
        ));

        // First-time check-in: flip the cached flag and bump event counter
        if ($action_type === 'check_in' && !(int) $sc_attendee->checked_in) {
            $wpdb->update($attendees_table, array(
                'checked_in'    => 1,
                'checked_in_at' => $now,
                'checked_in_by' => get_current_user_id(),
                'updated_at'    => $now,
            ), array('id' => $sc_attendee->id));

            $wpdb->query($wpdb->prepare(
                "UPDATE {$events_table} SET total_checked_in = total_checked_in + 1 WHERE id = %d",
                $sc_attendee->event_id
            ));
        }

        // Count today's scans for this attendee
        $today_scans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$checkins_table}
             WHERE attendee_id = %d AND DATE(created_at) = %s",
            $sc_attendee->id, date('Y-m-d')
        ));

        // Decode extra_fields JSON for display
        $extra_fields = array();
        if (!empty($sc_attendee->extra_fields)) {
            $decoded = json_decode($sc_attendee->extra_fields, true);
            if (is_array($decoded)) {
                $extra_fields = $decoded;
            }
        }

        // Optional gate logging (legacy compatibility)
        if ($gate_id && class_exists('SC_Gate')) {
            $gate = SC_Gate::get($gate_id);
            if ($gate) {
                $gate_info = array(
                    'id'      => $gate->id,
                    'name'    => $gate->name,
                    'zone_id' => $gate->zone_id,
                );
                SC_Gate::log_entry($gate_id, 1, array(
                    'attendee_id' => $sc_attendee->id,
                    'ticket_code' => $ticket_id,
                    'scan_method' => 'qr',
                    'scanned_by'  => get_current_user_id(),
                    'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                    'is_valid'    => 1,
                ));
            }
        }

        wp_send_json_success(array(
            'action_type'      => $action_type,
            'scan_time'        => date('h:i A', $current_time),
            'scan_date'        => date('M d, Y', $current_time),
            'tracking_enabled' => $tracking_enabled,
            'total_scans'      => $today_scans,
            'duration'         => $duration,
            'gate'             => $gate_info,
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
    $today = date('Y-m-d');
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
function sc_get_attendance_details() {
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

    // Get attendee info
    $attendee = get_post($attendee_id);
    if (!$attendee || $attendee->post_type !== 'sc_attendee') {
        wp_send_json_error(array('message' => __('Attendee not found.', 'sc_events')));
    }

    $attendee_name = get_post_meta($attendee_id, 'sc_name', true);
    $event_id = get_post_meta($attendee_id, 'sc_event_id', true);
    $event = get_post($event_id);

    // Get event dates and times
    $event_start_date = get_post_meta($event_id, 'sc_start_date', true);
    $event_end_date = get_post_meta($event_id, 'sc_end_date', true);
    $event_end_time = get_post_meta($event_id, 'sc_end_time', true);

    // If no end date, use start date (single day event)
    if (empty($event_end_date)) {
        $event_end_date = $event_start_date;
    }

    // Default end time if not set
    if (empty($event_end_time)) {
        $event_end_time = '23:59:59';
    }

    // Get attendance tracking status
    $tracking_enabled = get_post_meta($event_id, 'sc_attendance_tracking', true) === 'yes';

    // Get attendance log
    $attendance_log = get_post_meta($attendee_id, 'sc_attendance_log', true);
    if (!is_array($attendance_log)) {
        $attendance_log = array();
    }

    // Calculate event days
    $start = new DateTime($event_start_date);
    $end = new DateTime($event_end_date);
    $end->modify('+1 day'); // Include end date

    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start, $interval, $end);

    $event_days = array();
    foreach ($date_range as $date) {
        $event_days[] = $date->format('Y-m-d');
    }

    // Group attendance log entries by date
    $entries_by_date = array();
    foreach ($attendance_log as $entry) {
        if (isset($entry['date'])) {
            if (!isset($entries_by_date[$entry['date']])) {
                $entries_by_date[$entry['date']] = array();
            }
            $entries_by_date[$entry['date']][] = $entry;
        }
    }

    // Build per-day attendance data
    $daily_attendance = array();
    $total_duration_seconds = 0;
    $days_without_checkout = 0;

    foreach ($event_days as $day) {
        $day_entries = isset($entries_by_date[$day]) ? $entries_by_date[$day] : array();

        // Sort entries by timestamp
        usort($day_entries, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        $day_data = array(
            'date' => $day,
            'date_formatted' => date('D, M d, Y', strtotime($day)),
            'sessions' => array(),
            'total_duration' => 0,
            'total_duration_formatted' => '00:00',
            'missing_checkout' => false,
            'has_attendance' => count($day_entries) > 0
        );

        // Process sessions (pair check-ins with check-outs)
        $sessions = array();
        $current_session = null;

        foreach ($day_entries as $entry) {
            if ($entry['type'] === 'check_in') {
                // Start new session
                $current_session = array(
                    'check_in_time' => date('h:i A', $entry['timestamp']),
                    'check_in_timestamp' => $entry['timestamp'],
                    'check_out_time' => null,
                    'check_out_timestamp' => null,
                    'duration_seconds' => 0,
                    'duration_formatted' => '-',
                    'auto_checkout' => false
                );
            } elseif ($entry['type'] === 'check_out' && $current_session !== null) {
                // Complete session with check-out
                $current_session['check_out_time'] = date('h:i A', $entry['timestamp']);
                $current_session['check_out_timestamp'] = $entry['timestamp'];
                $duration = $entry['timestamp'] - $current_session['check_in_timestamp'];
                $current_session['duration_seconds'] = $duration;
                $current_session['duration_formatted'] = sprintf('%02d:%02d', floor($duration / 3600), floor(($duration % 3600) / 60));

                $sessions[] = $current_session;
                $current_session = null;
            }
        }

        // Handle missing checkout - auto calculate using event end time
        if ($current_session !== null) {
            $day_end_time = strtotime($day . ' ' . $event_end_time);
            $now = current_time('timestamp');

            // Use the lesser of event end time or current time (for today)
            $auto_checkout_time = ($day === date('Y-m-d')) ? min($day_end_time, $now) : $day_end_time;

            // Only apply if this day has passed or it's event end time
            if ($now > $day_end_time || $day !== date('Y-m-d')) {
                $current_session['check_out_time'] = date('h:i A', $auto_checkout_time) . ' (Auto)';
                $current_session['check_out_timestamp'] = $auto_checkout_time;
                $duration = $auto_checkout_time - $current_session['check_in_timestamp'];
                $current_session['duration_seconds'] = $duration;
                $current_session['duration_formatted'] = sprintf('%02d:%02d', floor($duration / 3600), floor(($duration % 3600) / 60));
                $current_session['auto_checkout'] = true;

                $day_data['missing_checkout'] = true;
                $days_without_checkout++;
            } else {
                // Currently checked in today
                $current_session['check_out_time'] = __('Still checked in', 'sc_events');
                $current_session['duration_formatted'] = __('In progress', 'sc_events');
            }

            $sessions[] = $current_session;
        }

        // Calculate total duration for the day
        $day_total_duration = 0;
        foreach ($sessions as $session) {
            $day_total_duration += $session['duration_seconds'];
        }

        $day_data['sessions'] = $sessions;
        $day_data['total_duration'] = $day_total_duration;
        $day_data['total_duration_formatted'] = sprintf('%02d:%02d', floor($day_total_duration / 3600), floor(($day_total_duration % 3600) / 60));

        $total_duration_seconds += $day_total_duration;
        $daily_attendance[] = $day_data;
    }

    // Calculate total duration
    $total_hours = floor($total_duration_seconds / 3600);
    $total_minutes = floor(($total_duration_seconds % 3600) / 60);
    $total_duration_formatted = sprintf('%02d:%02d', $total_hours, $total_minutes);

    // Count days with attendance
    $days_attended = count(array_filter($daily_attendance, function($d) { return $d['has_attendance']; }));

    wp_send_json_success(array(
        'attendee' => array(
            'id' => $attendee_id,
            'name' => $attendee_name,
            'event_id' => $event_id,
            'event_name' => $event ? $event->post_title : ''
        ),
        'event' => array(
            'start_date' => $event_start_date,
            'end_date' => $event_end_date,
            'end_time' => $event_end_time,
            'total_days' => count($event_days),
            'tracking_enabled' => $tracking_enabled
        ),
        'daily_attendance' => $daily_attendance,
        'summary' => array(
            'total_duration_seconds' => $total_duration_seconds,
            'total_duration_formatted' => $total_duration_formatted,
            'days_attended' => $days_attended,
            'days_without_checkout' => $days_without_checkout,
            'total_event_days' => count($event_days)
        )
    ));
}
