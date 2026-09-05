<?php
/**
 * Halls AJAX Handlers
 * CRUD operations for Halls using Custom Tables
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// GET ALL HALLS (active, for dropdowns)
// ==========================================
add_action('wp_ajax_sc_get_halls', 'sc_get_halls_handler');
function sc_get_halls_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_halls';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(array('halls' => array()));
    }

    $halls = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

    $halls_data = array();
    foreach ($halls as $hall) {
        $image_url = $hall->image ? wp_get_attachment_url($hall->image) : '';

        $halls_data[] = array(
            'id'         => $hall->id,
            'name'       => $hall->name,
            'slug'       => $hall->slug,
            'capacity'   => $hall->capacity,
            'location'   => $hall->location,
            'image_url'  => $image_url,
            'sort_order' => $hall->sort_order,
        );
    }

    wp_send_json_success(array('halls' => $halls_data));
}

// ==========================================
// GET SINGLE HALL
// ==========================================
add_action('wp_ajax_sc_get_hall', 'sc_get_hall_handler');
function sc_get_hall_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $hall_id = isset($_POST['hall_id']) ? intval($_POST['hall_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_halls';

    $hall = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $hall_id
    ));

    if (!$hall) {
        wp_send_json_error(array('message' => __('Hall not found.', 'sc_events')));
    }

    $image_url = $hall->image ? wp_get_attachment_url($hall->image) : '';

    // Get schedules count in this hall
    $schedules_table = $wpdb->prefix . 'sc_schedules';
    $schedules_count = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table) {
        $schedules_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $schedules_table WHERE hall_id = %d",
            $hall_id
        )));
    }

    $hall_data = array(
        'id'              => $hall->id,
        'name'            => $hall->name,
        'slug'            => $hall->slug,
        'description'     => $hall->description,
        'image'           => $hall->image,
        'image_url'       => $image_url,
        'capacity'        => $hall->capacity,
        'location'        => $hall->location,
        'is_active'       => $hall->is_active,
        'sort_order'      => $hall->sort_order,
        'schedules_count' => $schedules_count,
    );

    wp_send_json_success(array('hall' => $hall_data));
}

// ==========================================
// SAVE HALL (Create/Update)
// ==========================================
add_action('wp_ajax_sc_save_hall', 'sc_save_hall_handler');
function sc_save_hall_handler() {
    $nonce_valid = false;
    if (isset($_POST['sc_hall_nonce']) && wp_verify_nonce($_POST['sc_hall_nonce'], 'sc_hall_action')) {
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
    $table = $wpdb->prefix . 'sc_halls';

    $hall_id     = isset($_POST['hall_id']) ? intval($_POST['hall_id']) : 0;
    $name        = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
    $capacity    = isset($_POST['capacity']) ? intval($_POST['capacity']) : null;
    $location    = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
    $sort_order  = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Hall name is required.', 'sc_events')));
    }

    // Handle image - media library ID
    $has_image_id = isset($_POST['image']) && intval($_POST['image']) > 0;

    // Get existing hall if updating
    if ($hall_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $hall_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Hall not found.', 'sc_events')));
        }
    }

    // Generate slug
    $slug = sanitize_title($name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $hall_id))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $data = array(
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
        'capacity'    => $capacity,
        'location'    => $location,
        'sort_order'  => $sort_order,
        'is_active'   => 1,
        'updated_at'  => current_time('mysql'),
    );

    if ($has_image_id) {
        $data['image'] = intval($_POST['image']);
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $data['image'] = null;
    }

    if ($hall_id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $hall_id));
        $message = sc_t('hall_updated', 'Hall updated successfully.');
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $hall_id = $wpdb->insert_id;
        $message = sc_t('hall_created', 'Hall created successfully.');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save hall.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'hall_id' => $hall_id));
}

// ==========================================
// DELETE HALL
// ==========================================
add_action('wp_ajax_sc_delete_hall', 'sc_delete_hall_handler');
function sc_delete_hall_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $hall_id = isset($_POST['hall_id']) ? intval($_POST['hall_id']) : 0;

    if (!$hall_id) {
        wp_send_json_error(array('message' => __('Invalid hall ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_halls';

    // Check if hall has schedules
    $schedules_table = $wpdb->prefix . 'sc_schedules';
    if ($wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table) {
        $schedule_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $schedules_table WHERE hall_id = %d",
            $hall_id
        )));
        if ($schedule_count > 0) {
            // Nullify hall_id in schedules instead of blocking delete
            $wpdb->update($schedules_table, array('hall_id' => null), array('hall_id' => $hall_id));
        }
    }

    $result = $wpdb->delete($table, array('id' => $hall_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete hall.', 'sc_events')));
    }

    wp_send_json_success(array('message' => sc_t('hall_deleted', 'Hall deleted successfully.')));
}

// ==========================================
// GET HALLS WITH PAGINATION
// ==========================================
add_action('wp_ajax_sc_get_halls_paginated', 'sc_get_halls_paginated_handler');
function sc_get_halls_paginated_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_halls';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(array('halls' => array(), 'total' => 0, 'pages' => 0, 'current_page' => 1, 'per_page' => 50));
    }

    $page     = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (name LIKE %s OR location LIKE %s)", $search_like, $search_like);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
    $halls = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table $where ORDER BY sort_order ASC, name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get schedules count per hall
    $schedules_table = $wpdb->prefix . 'sc_schedules';
    $schedule_counts = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$schedules_table'") === $schedules_table) {
        $hall_ids = array_column($halls, 'id');
        if (!empty($hall_ids)) {
            $hall_ids = array_map('intval', $hall_ids);
            $placeholders = implode(',', array_fill(0, count($hall_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare(
                "SELECT hall_id, COUNT(*) as count FROM $schedules_table WHERE hall_id IN ($placeholders) GROUP BY hall_id",
                $hall_ids
            ));
            foreach ($counts as $row) {
                $schedule_counts[$row->hall_id] = intval($row->count);
            }
        }
    }

    $halls_data = array();
    foreach ($halls as $hall) {
        $image_url = $hall->image ? wp_get_attachment_url($hall->image) : '';

        $halls_data[] = array(
            'id'              => $hall->id,
            'name'            => $hall->name,
            'capacity'        => $hall->capacity !== null ? (int) $hall->capacity : null,
            'location'        => $hall->location,
            'image_url'       => $image_url,
            'sort_order'      => $hall->sort_order,
            'is_active'       => $hall->is_active,
            'schedules_count' => isset($schedule_counts[$hall->id]) ? $schedule_counts[$hall->id] : 0,
        );
    }

    wp_send_json_success(array(
        'halls'        => $halls_data,
        'total'        => intval($total),
        'pages'        => ceil($total / $per_page),
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}
