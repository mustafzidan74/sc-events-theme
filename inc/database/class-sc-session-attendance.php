<?php
/**
 * SC Session Attendance Helper
 *
 * Provides helper methods for querying session registrations and attendance data.
 * Referenced by SC_Attendee::get_session_attendance().
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Session_Attendance {

    /**
     * Get attendee's session summary for an event.
     * Returns all registered sessions with attendance data.
     *
     * @param int $attendee_id
     * @param int $event_id
     * @return array Array of session objects
     */
    public static function get_attendee_summary($attendee_id, $event_id) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'sc_sessions';
        $reg_table      = $wpdb->prefix . 'sc_session_registrations';
        $att_table      = $wpdb->prefix . 'sc_session_attendance';

        // Check tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$reg_table'") !== $reg_table) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id as session_id, s.title, s.description, s.session_type, s.track,
                    s.session_date, s.start_time, s.end_time, s.duration_minutes,
                    s.hall_name, s.cme_hours, s.cme_category, s.enable_certificate,
                    s.capacity, s.registered_count, s.min_attendance_percentage,
                    sr.id as registration_id, sr.status as registration_status,
                    sr.registration_code, sr.qr_code,
                    sa.check_in_time, sa.check_out_time,
                    sa.attendance_minutes, sa.attendance_percentage,
                    sa.earned_cme_hours, sa.certificate_eligible, sa.certificate_issued
             FROM {$reg_table} sr
             JOIN {$sessions_table} s ON sr.session_id = s.id
             LEFT JOIN {$att_table} sa ON sa.session_id = sr.session_id AND sa.attendee_id = sr.attendee_id
             WHERE sr.attendee_id = %d AND sr.event_id = %d AND s.is_published = 1
             ORDER BY s.session_date ASC, s.start_time ASC, s.sort_order ASC",
            $attendee_id, $event_id
        ));
    }

    /**
     * Batch-load session counts for multiple events.
     * Used by event card listing to avoid N+1 queries.
     *
     * @param array $event_ids Array of event IDs
     * @return array [event_id => session_count]
     */
    public static function get_session_counts_for_events($event_ids) {
        if (empty($event_ids)) return array();

        global $wpdb;
        $table = $wpdb->prefix . 'sc_sessions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }

        $ids = array_map('intval', $event_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT event_id, COUNT(*) as session_count
             FROM {$table}
             WHERE event_id IN ({$placeholders}) AND is_published = 1 AND status IN ('published','live')
             GROUP BY event_id",
            $ids
        ));

        $map = array();
        foreach ($results as $row) {
            $map[(int)$row->event_id] = (int)$row->session_count;
        }
        return $map;
    }

    /**
     * Get sessions for an event (used by schedule template).
     *
     * @param int $event_id
     * @return array Array of session objects
     */
    public static function get_event_sessions($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_sessions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE event_id = %d AND is_published = 1 AND status IN ('published','live')
             ORDER BY session_date ASC, start_time ASC, sort_order ASC",
            $event_id
        ));
    }

    /**
     * Get speakers for multiple sessions in one query.
     *
     * @param array $session_ids
     * @return array [session_id => [speaker objects]]
     */
    public static function get_speakers_for_sessions($session_ids) {
        if (empty($session_ids)) return array();

        global $wpdb;
        $ss_table = $wpdb->prefix . 'sc_session_speakers';
        $sp_table = $wpdb->prefix . 'sc_speakers';

        $ids = array_map('intval', $session_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ss.session_id, sp.id as speaker_id, sp.name, sp.title as speaker_title,
                    sp.photo as photo_id, ss.role, ss.presentation_title
             FROM {$ss_table} ss
             JOIN {$sp_table} sp ON ss.speaker_id = sp.id
             WHERE ss.session_id IN ({$placeholders})
             ORDER BY ss.sort_order ASC",
            $ids
        ));

        $map = array();
        foreach ($results as $sp) {
            $sp->photo_url = $sp->photo_id ? wp_get_attachment_image_url($sp->photo_id, 'thumbnail') : '';
            $map[(int)$sp->session_id][] = $sp;
        }
        return $map;
    }
}
