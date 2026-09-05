<?php
/**
 * Schedules AJAX Handlers
 * CRUD operations for Schedules using Custom Tables
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// MIGRATION: Add Registration & Break columns
// ==========================================
function sc_migrate_schedules_timing_fields() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;
    $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
    $new_cols = array(
        'registration_start' => "ALTER TABLE `{$table}` ADD COLUMN `registration_start` time DEFAULT NULL AFTER `end_time`",
        'registration_end'   => "ALTER TABLE `{$table}` ADD COLUMN `registration_end` time DEFAULT NULL AFTER `registration_start`",
        'break_start'        => "ALTER TABLE `{$table}` ADD COLUMN `break_start` time DEFAULT NULL AFTER `registration_end`",
        'break_end'          => "ALTER TABLE `{$table}` ADD COLUMN `break_end` time DEFAULT NULL AFTER `break_start`",
        'type'               => "ALTER TABLE `{$table}` ADD COLUMN `type` varchar(20) NOT NULL DEFAULT 'session' AFTER `event_id`",
    );
    foreach ($new_cols as $col => $sql) {
        if (!in_array($col, $cols)) {
            $wpdb->query($sql);
        }
    }
}
add_action('init', 'sc_migrate_schedules_timing_fields');

// ==========================================
// GET ALL SCHEDULES (active, for dropdowns)
// ==========================================
add_action('wp_ajax_sc_get_schedules', 'sc_get_schedules_handler');
function sc_get_schedules_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';
    $halls_table = $wpdb->prefix . 'sc_halls';
    $speakers_table = $wpdb->prefix . 'sc_speakers';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(array('schedules' => array()));
    }

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

    $where = "WHERE s.is_active = 1";
    $params = array();

    if ($event_id > 0) {
        $where .= " AND s.event_id = %d";
        $params[] = $event_id;
    }

    $halls_join = "";
    if ($wpdb->get_var("SHOW TABLES LIKE '$halls_table'") === $halls_table) {
        $halls_join = "LEFT JOIN $halls_table h ON s.hall_id = h.id";
    }

    $speakers_join = "";
    if ($wpdb->get_var("SHOW TABLES LIKE '$speakers_table'") === $speakers_table) {
        $speakers_join = "LEFT JOIN $speakers_table sp ON s.speaker_id = sp.id";
    }

    $sql = "SELECT s.*,
            " . ($halls_join ? "h.name as hall_name," : "NULL as hall_name,") . "
            " . ($speakers_join ? "sp.name as sp_name" : "NULL as sp_name") . "
            FROM $table s $halls_join $speakers_join $where
            ORDER BY s.schedule_date ASC, s.start_time ASC, s.sort_order ASC";

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $schedules = $wpdb->get_results($sql);

    $data = array();
    foreach ($schedules as $s) {
        $data[] = array(
            'id'            => $s->id,
            'title'         => $s->title,
            'schedule_date' => $s->schedule_date,
            'start_time'    => $s->start_time,
            'end_time'      => $s->end_time,
            'hall_name'     => $s->hall_name,
            'speaker_name'  => $s->sp_name ?: $s->speaker_name,
            'sort_order'    => $s->sort_order,
        );
    }

    wp_send_json_success(array('schedules' => $data));
}

// ==========================================
// GET SINGLE SCHEDULE
// ==========================================
add_action('wp_ajax_sc_get_schedule', 'sc_get_schedule_handler');
function sc_get_schedule_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';

    $schedule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $schedule_id
    ));

    if (!$schedule) {
        wp_send_json_error(array('message' => __('Schedule not found.', 'sc_events')));
    }

    // Get hall name if set
    $hall_name = null;
    if ($schedule->hall_id) {
        $halls_table = $wpdb->prefix . 'sc_halls';
        if ($wpdb->get_var("SHOW TABLES LIKE '$halls_table'") === $halls_table) {
            $hall_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $halls_table WHERE id = %d", $schedule->hall_id));
        }
    }

    // Get speaker name from speakers table if speaker_id set
    $resolved_speaker = $schedule->speaker_name;
    if ($schedule->speaker_id) {
        $speakers_table = $wpdb->prefix . 'sc_speakers';
        $sp_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $speakers_table WHERE id = %d", $schedule->speaker_id));
        if ($sp_name) $resolved_speaker = $sp_name;
    }

    $schedule_data = array(
        'id'                 => $schedule->id,
        'event_id'           => $schedule->event_id,
        'type'               => $schedule->type ?? 'session',
        'hall_id'            => $schedule->hall_id,
        'title'              => $schedule->title,
        'description'        => $schedule->description,
        'speaker_id'         => $schedule->speaker_id,
        'speaker_name'       => $schedule->speaker_name,
        'resolved_speaker'   => $resolved_speaker,
        'hall_name'          => $hall_name,
        'schedule_date'      => $schedule->schedule_date,
        'start_time'         => $schedule->start_time,
        'end_time'           => $schedule->end_time,
        'registration_start' => $schedule->registration_start ?? null,
        'registration_end'   => $schedule->registration_end   ?? null,
        'break_start'        => $schedule->break_start        ?? null,
        'break_end'          => $schedule->break_end          ?? null,
        'sort_order'         => $schedule->sort_order,
        'is_active'          => $schedule->is_active,
    );

    wp_send_json_success(array('schedule' => $schedule_data));
}

// ==========================================
// SAVE SCHEDULE (Create/Update)
// ==========================================
add_action('wp_ajax_sc_save_schedule', 'sc_save_schedule_handler');
function sc_save_schedule_handler() {
    $nonce_valid = false;
    if (isset($_POST['sc_schedule_nonce']) && wp_verify_nonce($_POST['sc_schedule_nonce'], 'sc_schedule_action')) {
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
    $table = $wpdb->prefix . 'sc_schedules';

    $schedule_id   = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $event_id      = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $type          = isset($_POST['type']) && in_array($_POST['type'], array('session', 'registration', 'break')) ? $_POST['type'] : 'session';
    $hall_id       = isset($_POST['hall_id']) && $_POST['hall_id'] !== '' ? intval($_POST['hall_id']) : null;
    $title         = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $description   = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
    $speaker_id    = isset($_POST['speaker_id']) && $_POST['speaker_id'] !== '' ? intval($_POST['speaker_id']) : null;
    $speaker_name  = isset($_POST['speaker_name']) ? sanitize_text_field($_POST['speaker_name']) : '';
    $schedule_date = isset($_POST['schedule_date']) ? sanitize_text_field($_POST['schedule_date']) : '';
    $start_time    = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
    $end_time      = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
    $sort_order    = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    // Validation
    if (empty($event_id)) {
        wp_send_json_error(array('message' => sc_t('validation.event_required', 'Event is required.')));
    }
    if (empty($title)) {
        wp_send_json_error(array('message' => sc_t('validation.title_required', 'Title is required.')));
    }
    if (empty($schedule_date)) {
        wp_send_json_error(array('message' => sc_t('validation.date_required', 'Date is required.')));
    }
    if (empty($start_time) || empty($end_time)) {
        wp_send_json_error(array('message' => sc_t('validation.time_required', 'Start time and end time are required.')));
    }

    // Get existing schedule if updating
    if ($schedule_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $schedule_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Schedule not found.', 'sc_events')));
        }
    }

    $registration_start = !empty($_POST['registration_start']) ? sanitize_text_field($_POST['registration_start']) : null;
    $registration_end   = !empty($_POST['registration_end'])   ? sanitize_text_field($_POST['registration_end'])   : null;
    $break_start        = !empty($_POST['break_start'])        ? sanitize_text_field($_POST['break_start'])        : null;
    $break_end          = !empty($_POST['break_end'])          ? sanitize_text_field($_POST['break_end'])          : null;

    $data = array(
        'event_id'           => $event_id,
        'type'               => $type,
        'hall_id'            => $hall_id,
        'title'              => $title,
        'description'        => $description,
        'speaker_id'         => $speaker_id,
        'speaker_name'       => $speaker_name,
        'schedule_date'      => $schedule_date,
        'start_time'         => $start_time,
        'end_time'           => $end_time,
        'registration_start' => $registration_start,
        'registration_end'   => $registration_end,
        'break_start'        => $break_start,
        'break_end'          => $break_end,
        'sort_order'         => $sort_order,
        'is_active'          => 1,
        'updated_at'         => current_time('mysql'),
    );

    if ($schedule_id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $schedule_id));
        $message = sc_t('schedule_updated', 'Schedule updated successfully.');
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $schedule_id = $wpdb->insert_id;
        $message = sc_t('schedule_created', 'Schedule created successfully.');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save schedule.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'schedule_id' => $schedule_id));
}

// ==========================================
// DELETE SCHEDULE
// ==========================================
add_action('wp_ajax_sc_delete_schedule', 'sc_delete_schedule_handler');
function sc_delete_schedule_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;

    if (!$schedule_id) {
        wp_send_json_error(array('message' => __('Invalid schedule ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';

    // Remove favorites for this schedule
    $fav_table = $wpdb->prefix . 'sc_schedule_favorites';
    if ($wpdb->get_var("SHOW TABLES LIKE '$fav_table'") === $fav_table) {
        $wpdb->delete($fav_table, array('schedule_id' => $schedule_id), array('%d'));
    }

    $result = $wpdb->delete($table, array('id' => $schedule_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete schedule.', 'sc_events')));
    }

    wp_send_json_success(array('message' => sc_t('schedule_deleted', 'Schedule deleted successfully.')));
}

// ==========================================
// GET SCHEDULES WITH PAGINATION
// ==========================================
add_action('wp_ajax_sc_get_schedules_paginated', 'sc_get_schedules_paginated_handler');
function sc_get_schedules_paginated_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_schedules';
    $events_table = $wpdb->prefix . 'sc_events';
    $halls_table = $wpdb->prefix . 'sc_halls';
    $speakers_table = $wpdb->prefix . 'sc_speakers';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(array('schedules' => array(), 'total' => 0, 'pages' => 0, 'current_page' => 1, 'per_page' => 50));
    }

    $page     = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $offset   = ($page - 1) * $per_page;

    // Build joins
    $joins = "LEFT JOIN $events_table e ON s.event_id = e.id";
    $select_extra = "e.title as event_title,";

    $has_halls = ($wpdb->get_var("SHOW TABLES LIKE '$halls_table'") === $halls_table);
    if ($has_halls) {
        $joins .= " LEFT JOIN $halls_table h ON s.hall_id = h.id";
        $select_extra .= " h.name as hall_name,";
    } else {
        $select_extra .= " NULL as hall_name,";
    }

    $has_speakers = ($wpdb->get_var("SHOW TABLES LIKE '$speakers_table'") === $speakers_table);
    if ($has_speakers) {
        $joins .= " LEFT JOIN $speakers_table sp ON s.speaker_id = sp.id";
        $select_extra .= " sp.name as sp_name";
    } else {
        $select_extra .= " NULL as sp_name";
    }

    $where = "WHERE 1=1";
    $count_where = "WHERE 1=1";
    $params = array();
    $count_params = array();

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (s.title LIKE %s OR s.speaker_name LIKE %s)", $search_like, $search_like);
        $count_where .= $wpdb->prepare(" AND (s.title LIKE %s OR s.speaker_name LIKE %s)", $search_like, $search_like);
    }

    if ($event_id > 0) {
        $where .= $wpdb->prepare(" AND s.event_id = %d", $event_id);
        $count_where .= $wpdb->prepare(" AND s.event_id = %d", $event_id);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table s $count_where");

    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, $select_extra FROM $table s $joins $where ORDER BY s.schedule_date ASC, s.start_time ASC, s.sort_order ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $schedules_data = array();
    foreach ($schedules as $s) {
        $schedules_data[] = array(
            'id'            => $s->id,
            'title'         => $s->title,
            'event_id'      => $s->event_id,
            'event_title'   => $s->event_title,
            'hall_name'     => $s->hall_name,
            'speaker_name'  => $s->sp_name ?: $s->speaker_name,
            'schedule_date' => $s->schedule_date,
            'start_time'    => substr($s->start_time, 0, 5),
            'end_time'      => substr($s->end_time, 0, 5),
            'sort_order'    => $s->sort_order,
            'is_active'     => $s->is_active,
        );
    }

    wp_send_json_success(array(
        'schedules'    => $schedules_data,
        'total'        => intval($total),
        'pages'        => ceil($total / $per_page),
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}
