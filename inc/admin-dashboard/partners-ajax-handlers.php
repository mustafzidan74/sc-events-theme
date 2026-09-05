<?php
/**
 * Partners AJAX Handlers
 * CRUD operations for Partners using Custom Tables
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if partners module is enabled before registering AJAX handlers
$partners_module_active = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('partners');

if (!$partners_module_active) return;

// ==========================================
// GET ALL PARTNERS (active, for dropdowns)
// ==========================================
add_action('wp_ajax_sc_get_partners', 'sc_get_partners_handler');
function sc_get_partners_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_partners';

    $partners = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY FIELD(tier, 'platinum', 'gold', 'silver', 'bronze'), sort_order ASC, name ASC");

    $partners_data = array();
    foreach ($partners as $partner) {
        $logo_url = $partner->logo ? wp_get_attachment_url($partner->logo) : '';

        $partners_data[] = array(
            'id'          => $partner->id,
            'name'        => $partner->name,
            'email'       => $partner->email,
            'phone'       => $partner->phone,
            'website'     => $partner->website,
            'description' => $partner->description,
            'tier'        => $partner->tier,
            'tier_label'  => SC_Partner::get_tier_label($partner->tier),
            'logo_url'    => $logo_url,
            'sort_order'  => $partner->sort_order,
        );
    }

    wp_send_json_success(array('partners' => $partners_data));
}

// ==========================================
// GET SINGLE PARTNER
// ==========================================
add_action('wp_ajax_sc_get_partner', 'sc_get_partner_handler');
function sc_get_partner_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $partner_id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_partners';

    $partner = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $partner_id
    ));

    if (!$partner) {
        wp_send_json_error(array('message' => __('Partner not found.', 'sc_events')));
    }

    $logo_url = $partner->logo ? wp_get_attachment_url($partner->logo) : '';

    // Get assigned events
    $pivot = $wpdb->prefix . 'sc_event_partners';
    $events_table = $wpdb->prefix . 'sc_events';
    $assigned_events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.title, ep.tier_override, ep.sort_order
        FROM $pivot ep
        INNER JOIN $events_table e ON ep.event_id = e.id
        WHERE ep.partner_id = %d
        ORDER BY e.start_date DESC",
        $partner_id
    ));

    $partner_data = array(
        'id'          => $partner->id,
        'name'        => $partner->name,
        'slug'        => $partner->slug,
        'email'       => $partner->email,
        'phone'       => $partner->phone,
        'website'     => $partner->website,
        'description' => $partner->description,
        'tier'        => $partner->tier,
        'tier_label'  => SC_Partner::get_tier_label($partner->tier),
        'logo'        => $partner->logo,
        'logo_url'    => $logo_url,
        'sort_order'  => $partner->sort_order,
        'is_active'   => $partner->is_active,
        'events'      => $assigned_events,
    );

    wp_send_json_success(array('partner' => $partner_data));
}

// ==========================================
// SAVE PARTNER (Create/Update)
// ==========================================
add_action('wp_ajax_sc_save_partner', 'sc_save_partner_handler');
function sc_save_partner_handler() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_partner_nonce']) && wp_verify_nonce($_POST['sc_partner_nonce'], 'sc_partner_action')) {
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
    $table = $wpdb->prefix . 'sc_partners';

    $partner_id  = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
    $name        = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email       = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone       = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $website     = isset($_POST['website']) ? esc_url_raw($_POST['website']) : '';
    $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
    $tier        = isset($_POST['tier']) ? sanitize_text_field($_POST['tier']) : 'bronze';
    $sort_order  = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    if (empty($name)) {
        wp_send_json_error(array('message' => __('Partner name is required.', 'sc_events')));
    }

    // Validate tier
    $allowed_tiers = array('platinum', 'gold', 'silver', 'bronze');
    if (!in_array($tier, $allowed_tiers)) {
        $tier = 'bronze';
    }

    // Handle logo - media library ID
    $has_logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0;

    // Get existing partner if updating
    if ($partner_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $partner_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Partner not found.', 'sc_events')));
        }
    }

    // Generate slug
    $slug = sanitize_title($name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $partner_id))) {
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

    if ($partner_id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $partner_id));
        $message = __('Partner updated successfully.', 'sc_events');
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $partner_id = $wpdb->insert_id;
        $message = __('Partner created successfully.', 'sc_events');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save partner.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'partner_id' => $partner_id));
}

// ==========================================
// DELETE PARTNER
// ==========================================
add_action('wp_ajax_sc_delete_partner', 'sc_delete_partner_handler');
function sc_delete_partner_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $partner_id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;

    if (!$partner_id) {
        wp_send_json_error(array('message' => __('Invalid partner ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_partners';
    $pivot = $wpdb->prefix . 'sc_event_partners';

    // Remove from all events first
    $wpdb->delete($pivot, array('partner_id' => $partner_id), array('%d'));

    // Delete partner
    $result = $wpdb->delete($table, array('id' => $partner_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete partner.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Partner deleted successfully.', 'sc_events')));
}

// ==========================================
// GET PARTNERS WITH PAGINATION
// ==========================================
add_action('wp_ajax_sc_get_partners_paginated', 'sc_get_partners_paginated_handler');
function sc_get_partners_paginated_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_partners';
    $pivot_table = $wpdb->prefix . 'sc_event_partners';

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
        $join = $wpdb->prepare(" INNER JOIN $pivot_table ep ON s.id = ep.partner_id AND ep.event_id = %d", $event_id);
    }

    $total = $wpdb->get_var("SELECT COUNT(DISTINCT s.id) FROM $table s $join $where");
    $partners = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.* FROM $table s $join $where ORDER BY FIELD(s.tier, 'platinum', 'gold', 'silver', 'bronze'), s.sort_order ASC, s.name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get events count for each partner
    $partner_ids = array_column($partners, 'id');
    $events_counts = array();
    if (!empty($partner_ids)) {
        $partner_ids = array_map('intval', $partner_ids);
        $partner_ids = array_filter($partner_ids);

        if (!empty($partner_ids)) {
            $events_table = $wpdb->prefix . 'sc_events';
            $placeholders = implode(',', array_fill(0, count($partner_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT ep.partner_id, COUNT(DISTINCT ep.event_id) as count
                FROM $pivot_table ep
                INNER JOIN $events_table e ON ep.event_id = e.id
                WHERE ep.partner_id IN ($placeholders)
                GROUP BY ep.partner_id
            ", $partner_ids));
            foreach ($counts as $row) {
                $events_counts[$row->partner_id] = intval($row->count);
            }
        }
    }

    $partners_data = array();
    foreach ($partners as $partner) {
        $logo_url = $partner->logo ? wp_get_attachment_url($partner->logo) : '';

        $partners_data[] = array(
            'id'           => $partner->id,
            'name'         => $partner->name,
            'email'        => $partner->email,
            'phone'        => $partner->phone,
            'website'      => $partner->website,
            'description'  => $partner->description,
            'tier'         => $partner->tier,
            'tier_label'   => SC_Partner::get_tier_label($partner->tier),
            'logo_url'     => $logo_url,
            'sort_order'   => $partner->sort_order,
            'events_count' => isset($events_counts[$partner->id]) ? $events_counts[$partner->id] : 0,
        );
    }

    wp_send_json_success(array(
        'partners'     => $partners_data,
        'total'        => intval($total),
        'pages'        => ceil($total / $per_page),
        'current_page' => $page,
        'per_page'     => $per_page,
    ));
}
