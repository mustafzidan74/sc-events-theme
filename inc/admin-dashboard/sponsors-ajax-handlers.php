<?php
/**
 * Sponsors AJAX Handlers
 * CRUD operations for Sponsors using Custom Tables
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if sponsors module is enabled before registering AJAX handlers
$sponsors_module_active = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sponsors');

if (!$sponsors_module_active) return;

// ==========================================
// GET ALL SPONSORS (active, for dropdowns)
// ==========================================
add_action('wp_ajax_sc_get_sponsors', 'sc_get_sponsors_handler');
function sc_get_sponsors_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sponsors';

    $sponsors = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY FIELD(tier, 'platinum', 'gold', 'silver', 'bronze'), sort_order ASC, name ASC");

    $sponsors_data = array();
    foreach ($sponsors as $sponsor) {
        $logo_url = $sponsor->logo ? wp_get_attachment_url($sponsor->logo) : '';

        $sponsors_data[] = array(
            'id'          => $sponsor->id,
            'name'        => $sponsor->name,
            'email'       => $sponsor->email,
            'phone'       => $sponsor->phone,
            'website'     => $sponsor->website,
            'description' => $sponsor->description,
            'tier'        => $sponsor->tier,
            'tier_label'  => SC_Sponsor::get_tier_label($sponsor->tier),
            'logo_url'    => $logo_url,
            'sort_order'  => $sponsor->sort_order,
        );
    }

    wp_send_json_success(array('sponsors' => $sponsors_data));
}

// ==========================================
// GET SINGLE SPONSOR
// ==========================================
add_action('wp_ajax_sc_get_sponsor', 'sc_get_sponsor_handler');
function sc_get_sponsor_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $sponsor_id = isset($_POST['sponsor_id']) ? intval($_POST['sponsor_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sponsors';

    $sponsor = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $sponsor_id
    ));

    if (!$sponsor) {
        wp_send_json_error(array('message' => __('Sponsor not found.', 'sc_events')));
    }

    $logo_url = $sponsor->logo ? wp_get_attachment_url($sponsor->logo) : '';

    // Get assigned events
    $pivot = $wpdb->prefix . 'sc_event_sponsors';
    $events_table = $wpdb->prefix . 'sc_events';
    $assigned_events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.title, es.tier_override, es.sort_order
        FROM $pivot es
        INNER JOIN $events_table e ON es.event_id = e.id
        WHERE es.sponsor_id = %d
        ORDER BY e.start_date DESC",
        $sponsor_id
    ));

    $sponsor_data = array(
        'id'          => $sponsor->id,
        'name'        => $sponsor->name,
        'slug'        => $sponsor->slug,
        'email'       => $sponsor->email,
        'phone'       => $sponsor->phone,
        'website'     => $sponsor->website,
        'description' => $sponsor->description,
        'tier'        => $sponsor->tier,
        'tier_label'  => SC_Sponsor::get_tier_label($sponsor->tier),
        'logo'        => $sponsor->logo,
        'logo_url'    => $logo_url,
        'sort_order'  => $sponsor->sort_order,
        'is_active'   => $sponsor->is_active,
        'events'      => $assigned_events,
    );

    wp_send_json_success(array('sponsor' => $sponsor_data));
}

// ==========================================
// SAVE SPONSOR (Create/Update)
// ==========================================
add_action('wp_ajax_sc_save_sponsor', 'sc_save_sponsor_handler');
function sc_save_sponsor_handler() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_sponsor_nonce']) && wp_verify_nonce($_POST['sc_sponsor_nonce'], 'sc_sponsor_action')) {
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
    $table = $wpdb->prefix . 'sc_sponsors';

    $sponsor_id  = isset($_POST['sponsor_id']) ? intval($_POST['sponsor_id']) : 0;
    $name        = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email       = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone       = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $website     = isset($_POST['website']) ? esc_url_raw($_POST['website']) : '';
    $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
    $tier        = isset($_POST['tier']) ? sanitize_text_field($_POST['tier']) : 'bronze';
    $sort_order  = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Sponsor name is required.', 'sc_events')));
    }

    // Validate tier
    $allowed_tiers = array('platinum', 'gold', 'silver', 'bronze');
    if (!in_array($tier, $allowed_tiers)) {
        $tier = 'bronze';
    }

    // Handle logo - media library ID
    $has_logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0;

    // Get existing sponsor if updating
    if ($sponsor_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $sponsor_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Sponsor not found.', 'sc_events')));
        }
    }

    // Generate slug
    $slug = sanitize_title($name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $sponsor_id))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $data = array(
        'name'        => $name,
        'slug'        => $slug,
        'email'       => $email,
        'phone'       => $phone,
        'website'     => $website,
        'description' => $description,
        'tier'        => $tier,
        'sort_order'  => $sort_order,
        'is_active'   => 1,
        'updated_at'  => current_time('mysql'),
    );

    if ($has_logo_id) {
        $data['logo'] = intval($_POST['logo']);
    }

    if ($sponsor_id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $sponsor_id));
        $message = __('Sponsor updated successfully.', 'sc_events');
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $sponsor_id = $wpdb->insert_id;
        $message = __('Sponsor created successfully.', 'sc_events');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save sponsor.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'sponsor_id' => $sponsor_id));
}

// ==========================================
// DELETE SPONSOR
// ==========================================
add_action('wp_ajax_sc_delete_sponsor', 'sc_delete_sponsor_handler');
function sc_delete_sponsor_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $sponsor_id = isset($_POST['sponsor_id']) ? intval($_POST['sponsor_id']) : 0;

    if (!$sponsor_id) {
        wp_send_json_error(array('message' => __('Invalid sponsor ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sponsors';
    $pivot = $wpdb->prefix . 'sc_event_sponsors';

    // Remove from all events first
    $wpdb->delete($pivot, array('sponsor_id' => $sponsor_id), array('%d'));

    // Delete sponsor
    $result = $wpdb->delete($table, array('id' => $sponsor_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete sponsor.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Sponsor deleted successfully.', 'sc_events')));
}

// ==========================================
// GET SPONSORS WITH PAGINATION
// ==========================================
add_action('wp_ajax_sc_get_sponsors_paginated', 'sc_get_sponsors_paginated_handler');
function sc_get_sponsors_paginated_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_sponsors';
    $pivot_table = $wpdb->prefix . 'sc_event_sponsors';

    $page     = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $tier     = isset($_POST['tier']) ? sanitize_text_field($_POST['tier']) : '';
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE s.is_active = 1";
    $join = "";

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (s.name LIKE %s OR s.email LIKE %s OR s.website LIKE %s)", $search_like, $search_like, $search_like);
    }

    if (!empty($tier) && in_array($tier, array('platinum', 'gold', 'silver', 'bronze'))) {
        $where .= $wpdb->prepare(" AND s.tier = %s", $tier);
    }

    if ($event_id > 0) {
        $join = $wpdb->prepare(" INNER JOIN $pivot_table es ON s.id = es.sponsor_id AND es.event_id = %d", $event_id);
    }

    $total = $wpdb->get_var("SELECT COUNT(DISTINCT s.id) FROM $table s $join $where");
    $sponsors = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.* FROM $table s $join $where ORDER BY FIELD(s.tier, 'platinum', 'gold', 'silver', 'bronze'), s.sort_order ASC, s.name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get events count for each sponsor
    $sponsor_ids = array_column($sponsors, 'id');
    $events_counts = array();
    if (!empty($sponsor_ids)) {
        $sponsor_ids = array_map('intval', $sponsor_ids);
        $sponsor_ids = array_filter($sponsor_ids);

        if (!empty($sponsor_ids)) {
            $events_table = $wpdb->prefix . 'sc_events';
            $placeholders = implode(',', array_fill(0, count($sponsor_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT es.sponsor_id, COUNT(DISTINCT es.event_id) as count
                FROM $pivot_table es
                INNER JOIN $events_table e ON es.event_id = e.id
                WHERE es.sponsor_id IN ($placeholders)
                GROUP BY es.sponsor_id
            ", $sponsor_ids));
            foreach ($counts as $row) {
                $events_counts[$row->sponsor_id] = intval($row->count);
            }
        }
    }

    $sponsors_data = array();
    foreach ($sponsors as $sponsor) {
        $logo_url = $sponsor->logo ? wp_get_attachment_url($sponsor->logo) : '';

        $sponsors_data[] = array(
            'id'           => $sponsor->id,
            'name'         => $sponsor->name,
            'email'        => $sponsor->email,
            'phone'        => $sponsor->phone,
            'website'      => $sponsor->website,
            'description'  => $sponsor->description,
            'tier'         => $sponsor->tier,
            'tier_label'   => SC_Sponsor::get_tier_label($sponsor->tier),
            'logo_url'     => $logo_url,
            'sort_order'   => $sponsor->sort_order,
            'events_count' => isset($events_counts[$sponsor->id]) ? $events_counts[$sponsor->id] : 0,
        );
    }

    wp_send_json_success(array(
        'sponsors'     => $sponsors_data,
        'total'        => intval($total),
        'pages'        => ceil($total / $per_page),
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}
