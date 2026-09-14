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

// List, save and bulk actions shared with the other brand type.
require_once __DIR__ . '/brands-dashboard.php';
sc_brands_register('partner');
