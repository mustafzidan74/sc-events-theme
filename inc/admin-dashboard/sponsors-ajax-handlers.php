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

// List, save and bulk actions shared with the other brand type.
require_once __DIR__ . '/brands-dashboard.php';
sc_brands_register('sponsor');
