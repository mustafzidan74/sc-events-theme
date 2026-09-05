<?php
/**
 * Speakers, Organizers & Categories AJAX Handlers
 * Using Custom Tables (sc_speakers, sc_organizers)
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if speakers module is enabled before registering AJAX handlers
$speakers_module_active = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('speakers');

// ==========================================
// SPEAKERS HANDLERS (Custom Tables)
// ==========================================

if ($speakers_module_active):

/**
 * Get all speakers from custom table
 */
add_action('wp_ajax_sc_get_speakers', 'sc_get_speakers');
function sc_get_speakers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';

    $speakers = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");

    $speakers_data = array();
    foreach ($speakers as $speaker) {
        $social_links = json_decode($speaker->social_links, true) ?: array();
        $photo_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : '';

        $speakers_data[] = array(
            'ID' => $speaker->id,
            'name' => $speaker->name,
            'title' => $speaker->title,
            'email' => $speaker->email,
            'phone' => $speaker->phone,
            'bio' => $speaker->bio,
            'company' => $speaker->company,
            'website' => $speaker->website,
            'image_url' => $photo_url,
            'social_links' => $social_links,
            'facebook' => $social_links['facebook'] ?? '',
            'twitter' => $social_links['twitter'] ?? '',
            'linkedin' => $social_links['linkedin'] ?? '',
            'instagram' => $social_links['instagram'] ?? '',
            'youtube' => $social_links['youtube'] ?? '',
            'github' => $social_links['github'] ?? '',
            'tiktok' => $social_links['tiktok'] ?? '',
            'events_count' => $speaker->events_count
        );
    }

    wp_send_json_success(array('speakers' => $speakers_data));
}

/**
 * Get single speaker from custom table
 */
add_action('wp_ajax_sc_get_speaker', 'sc_get_speaker');
function sc_get_speaker() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $speaker_id = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';

    $speaker = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $speaker_id
    ));

    if (!$speaker) {
        wp_send_json_error(array('message' => __('Speaker not found.', 'sc_events')));
    }

    $social_links = json_decode($speaker->social_links, true) ?: array();
    $photo_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : '';

    $speaker_data = array(
        'ID' => $speaker->id,
        'name' => $speaker->name,
        'title' => $speaker->title,
        'email' => $speaker->email,
        'phone' => $speaker->phone,
        'bio' => $speaker->bio,
        'company' => $speaker->company,
        'website' => $speaker->website,
        'image_url' => $photo_url,
        'social_links' => $social_links,
        'facebook' => $social_links['facebook'] ?? '',
        'twitter' => $social_links['twitter'] ?? '',
        'linkedin' => $social_links['linkedin'] ?? '',
        'instagram' => $social_links['instagram'] ?? '',
        'youtube' => $social_links['youtube'] ?? '',
        'github' => $social_links['github'] ?? '',
        'tiktok' => $social_links['tiktok'] ?? '',
        'whatsapp' => $social_links['whatsapp'] ?? '',
        'snapchat' => $social_links['snapchat'] ?? ''
    );

    wp_send_json_success(array('speaker' => $speaker_data));
}

/**
 * Save speaker to custom table
 */
add_action('wp_ajax_sc_save_speaker', 'sc_save_speaker');
function sc_save_speaker() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_speaker_nonce']) && wp_verify_nonce($_POST['sc_speaker_nonce'], 'sc_speaker_action')) {
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
    $table = $wpdb->prefix . 'sc_speakers';

    // Support both field naming conventions
    $speaker_id = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;
    $speaker_name = isset($_POST['speaker_name']) ? sanitize_text_field($_POST['speaker_name']) : (isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '');
    $speaker_email = isset($_POST['speaker_email']) ? sanitize_email($_POST['speaker_email']) : (isset($_POST['email']) ? sanitize_email($_POST['email']) : '');
    $speaker_phone = isset($_POST['speaker_phone']) ? sanitize_text_field($_POST['speaker_phone']) : (isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '');
    $speaker_title = isset($_POST['speaker_title']) ? sanitize_text_field($_POST['speaker_title']) : (isset($_POST['designation']) ? sanitize_text_field($_POST['designation']) : '');
    $speaker_company = isset($_POST['speaker_company']) ? sanitize_text_field($_POST['speaker_company']) : (isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '');
    $speaker_bio = isset($_POST['speaker_bio']) ? wp_kses_post($_POST['speaker_bio']) : (isset($_POST['bio']) ? wp_kses_post($_POST['bio']) : '');
    $speaker_website = isset($_POST['speaker_website']) ? esc_url_raw($_POST['speaker_website']) : (isset($_POST['website']) ? esc_url_raw($_POST['website']) : '');
    if (empty($speaker_name)) {
        wp_send_json_error(array('message' => __('Speaker name is required.', 'sc_events')));
    }

    // Check image - support both file upload and media library ID
    $has_image_file = isset($_FILES['speaker_image']) && !empty($_FILES['speaker_image']['name']);
    $has_image_id = isset($_POST['image']) && intval($_POST['image']) > 0;

    // Get existing speaker photo if updating
    $existing_photo = 0;
    if ($speaker_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT photo FROM $table WHERE id = %d", $speaker_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Speaker not found.', 'sc_events')));
        }
        $existing_photo = intval($existing->photo);
    }

    // Build social links array - support both naming conventions
    $social_links = array();
    $social_platforms = array('facebook', 'twitter', 'linkedin', 'instagram', 'youtube', 'github', 'tiktok', 'whatsapp', 'snapchat', 'pinterest', 'tumblr', 'reddit', 'medium', 'vimeo');
    foreach ($social_platforms as $platform) {
        $url = isset($_POST['speaker_' . $platform]) ? esc_url_raw($_POST['speaker_' . $platform]) : '';
        if (empty($url)) {
            $url = isset($_POST[$platform]) ? esc_url_raw($_POST[$platform]) : '';
        }
        if (!empty($url)) {
            $social_links[$platform] = $url;
        }
    }

    // Handle image - either file upload or media library ID
    $photo_id = 0;
    if ($has_image_file) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('speaker_image', 0);
        if (!is_wp_error($attachment_id)) {
            $photo_id = $attachment_id;
        }
    } elseif ($has_image_id) {
        $photo_id = intval($_POST['image']);
    }

    // Generate slug
    $slug = sanitize_title($speaker_name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $speaker_id))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $data = array(
        'name' => $speaker_name,
        'slug' => $slug,
        'title' => $speaker_title,
        'company' => $speaker_company,
        'email' => $speaker_email,
        'phone' => $speaker_phone,
        'bio' => $speaker_bio,
        'website' => $speaker_website,
        'social_links' => wp_json_encode($social_links),
        'is_active' => 1,
        'updated_at' => current_time('mysql')
    );

    if ($photo_id > 0) {
        $data['photo'] = $photo_id;
    }

    if ($speaker_id > 0) {
        // Update existing speaker
        $result = $wpdb->update($table, $data, array('id' => $speaker_id));
        $message = __('Speaker updated successfully.', 'sc_events');
    } else {
        // Create new speaker
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $speaker_id = $wpdb->insert_id;
        $message = __('Speaker created successfully.', 'sc_events');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save speaker.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'speaker_id' => $speaker_id));
}

/**
 * Delete speaker from custom table
 */
add_action('wp_ajax_sc_delete_speaker', 'sc_delete_speaker');
function sc_delete_speaker() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $speaker_id = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;

    if (!$speaker_id) {
        wp_send_json_error(array('message' => __('Invalid speaker ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';
    $pivot = $wpdb->prefix . 'sc_event_speakers';

    // Remove from all events first
    $wpdb->delete($pivot, array('speaker_id' => $speaker_id), array('%d'));

    // Delete speaker
    $result = $wpdb->delete($table, array('id' => $speaker_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete speaker.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Speaker deleted successfully.', 'sc_events')));
}

/**
 * Get speakers with pagination
 */
add_action('wp_ajax_sc_get_speakers_paginated', 'sc_get_speakers_paginated');
function sc_get_speakers_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_speakers';
    $pivot_table = $wpdb->prefix . 'sc_event_speakers';

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $offset = ($page - 1) * $per_page;

    $where = "WHERE s.is_active = 1";
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (s.name LIKE %s OR s.title LIKE %s OR s.company LIKE %s)", $search_like, $search_like, $search_like);
    }

    // Filter by event if specified
    $join = "";
    if ($event_id > 0) {
        $join = $wpdb->prepare(" INNER JOIN $pivot_table es ON s.id = es.speaker_id AND es.event_id = %d", $event_id);
    }

    $total = $wpdb->get_var("SELECT COUNT(DISTINCT s.id) FROM $table s $join $where");
    $speakers = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.* FROM $table s $join $where ORDER BY s.name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get events count for each speaker from the pivot table (only count existing events)
    $speaker_ids = array_column($speakers, 'id');
    $events_counts = array();
    if (!empty($speaker_ids)) {
        // Sanitize and prepare speaker IDs
        $speaker_ids = array_map('intval', $speaker_ids);
        $speaker_ids = array_filter($speaker_ids);

        if (!empty($speaker_ids)) {
            $events_table = $wpdb->prefix . 'sc_events';
            // Use prepared statement with placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($speaker_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT es.speaker_id, COUNT(DISTINCT es.event_id) as count
                FROM $pivot_table es
                INNER JOIN $events_table e ON es.event_id = e.id
                WHERE es.speaker_id IN ($placeholders)
                GROUP BY es.speaker_id
            ", $speaker_ids));
            foreach ($counts as $row) {
                $events_counts[$row->speaker_id] = intval($row->count);
            }
        }
    }

    $speakers_data = array();
    foreach ($speakers as $speaker) {
        $social_links = json_decode($speaker->social_links, true) ?: array();
        $photo_url = $speaker->photo ? wp_get_attachment_url($speaker->photo) : '';

        $speakers_data[] = array(
            'ID' => $speaker->id,
            'name' => $speaker->name,
            'title' => $speaker->title,
            'email' => $speaker->email,
            'phone' => $speaker->phone,
            'bio' => $speaker->bio,
            'company' => $speaker->company,
            'website' => $speaker->website,
            'image_url' => $photo_url,
            'facebook' => $social_links['facebook'] ?? '',
            'twitter' => $social_links['twitter'] ?? '',
            'linkedin' => $social_links['linkedin'] ?? '',
            'instagram' => $social_links['instagram'] ?? '',
            'youtube' => $social_links['youtube'] ?? '',
            'github' => $social_links['github'] ?? '',
            'tiktok' => $social_links['tiktok'] ?? '',
            'snapchat' => $social_links['snapchat'] ?? '',
            'whatsapp' => $social_links['whatsapp'] ?? '',
            'pinterest' => $social_links['pinterest'] ?? '',
            'tumblr' => $social_links['tumblr'] ?? '',
            'reddit' => $social_links['reddit'] ?? '',
            'medium' => $social_links['medium'] ?? '',
            'vimeo' => $social_links['vimeo'] ?? '',
            'events_count' => isset($events_counts[$speaker->id]) ? $events_counts[$speaker->id] : 0
        );
    }

    wp_send_json_success(array(
        'speakers' => $speakers_data,
        'total' => intval($total),
        'pages' => ceil($total / $per_page),
        'current_page' => $page
    ));
}

endif; // End speakers module check

// ==========================================
// ORGANIZERS HANDLERS (Custom Tables)
// Organizers are part of Events module, always available if events is enabled
// ==========================================

/**
 * Get all organizers from custom table
 */
add_action('wp_ajax_sc_get_organizers', 'sc_get_organizers');
function sc_get_organizers() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $organizers = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");

    $organizers_data = array();
    foreach ($organizers as $organizer) {
        $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';

        $organizers_data[] = array(
            'ID' => $organizer->id,
            'name' => $organizer->name,
            'email' => $organizer->email,
            'phone' => $organizer->phone,
            'website' => $organizer->website,
            'description' => $organizer->description,
            'address' => $organizer->address,
            'logo_url' => $logo_url,
            'events_count' => $organizer->events_count
        );
    }

    wp_send_json_success(array('organizers' => $organizers_data));
}

/**
 * Get single organizer from custom table
 */
add_action('wp_ajax_sc_get_organizer', 'sc_get_organizer');
function sc_get_organizer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $organizer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $organizer_id
    ));

    if (!$organizer) {
        wp_send_json_error(array('message' => __('Organizer not found.', 'sc_events')));
    }

    $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';
    $social_links = json_decode($organizer->social_links, true) ?: array();

    $organizer_data = array(
        'ID' => $organizer->id,
        'name' => $organizer->name,
        'email' => $organizer->email,
        'phone' => $organizer->phone,
        'website' => $organizer->website,
        'description' => $organizer->description,
        'address' => $organizer->address,
        'logo_url' => $logo_url,
        'social_links' => $social_links,
        'facebook' => $social_links['facebook'] ?? '',
        'twitter' => $social_links['twitter'] ?? '',
        'linkedin' => $social_links['linkedin'] ?? '',
        'instagram' => $social_links['instagram'] ?? ''
    );

    wp_send_json_success(array('organizer' => $organizer_data));
}

/**
 * Save organizer to custom table
 */
add_action('wp_ajax_sc_save_organizer', 'sc_save_organizer');
function sc_save_organizer() {
    // Support both nonce types
    $nonce_valid = false;
    if (isset($_POST['sc_organizer_nonce']) && wp_verify_nonce($_POST['sc_organizer_nonce'], 'sc_organizer_action')) {
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
    $table = $wpdb->prefix . 'sc_organizers';

    // Support both field naming conventions
    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;
    $organizer_name = isset($_POST['organizer_name']) ? sanitize_text_field($_POST['organizer_name']) : (isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '');
    $organizer_email = isset($_POST['organizer_email']) ? sanitize_email($_POST['organizer_email']) : (isset($_POST['email']) ? sanitize_email($_POST['email']) : '');
    $organizer_phone = isset($_POST['organizer_phone']) ? sanitize_text_field($_POST['organizer_phone']) : (isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '');
    $organizer_website = isset($_POST['organizer_website']) ? esc_url_raw($_POST['organizer_website']) : (isset($_POST['website']) ? esc_url_raw($_POST['website']) : '');
    $organizer_description = isset($_POST['organizer_description']) ? wp_kses_post($_POST['organizer_description']) : (isset($_POST['description']) ? wp_kses_post($_POST['description']) : '');
    $organizer_address = isset($_POST['organizer_address']) ? sanitize_textarea_field($_POST['organizer_address']) : (isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '');
    if (empty($organizer_name)) {
        wp_send_json_error(array('message' => __('Organizer name is required.', 'sc_events')));
    }

    // Check logo - support both file upload and media library ID
    $has_logo_file = isset($_FILES['organizer_logo']) && !empty($_FILES['organizer_logo']['name']);
    $has_logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0;
    if (!$has_logo_id) {
        $has_logo_id = isset($_POST['image']) && intval($_POST['image']) > 0;
    }

    // Get existing organizer logo if updating
    $existing_logo = 0;
    if ($organizer_id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT logo FROM $table WHERE id = %d", $organizer_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Organizer not found.', 'sc_events')));
        }
        $existing_logo = intval($existing->logo);
    }

    // Build social links array - support both naming conventions
    $social_links = array();
    $social_platforms = array('facebook', 'twitter', 'linkedin', 'instagram', 'youtube');
    foreach ($social_platforms as $platform) {
        $url = isset($_POST['organizer_' . $platform]) ? esc_url_raw($_POST['organizer_' . $platform]) : '';
        if (empty($url)) {
            $url = isset($_POST[$platform]) ? esc_url_raw($_POST[$platform]) : '';
        }
        if (!empty($url)) {
            $social_links[$platform] = $url;
        }
    }

    // Handle logo - either file upload or media library ID
    $logo_id = 0;
    if ($has_logo_file) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('organizer_logo', 0);
        if (!is_wp_error($attachment_id)) {
            $logo_id = $attachment_id;
        }
    } elseif ($has_logo_id) {
        $logo_id = isset($_POST['logo']) && intval($_POST['logo']) > 0 ? intval($_POST['logo']) : intval($_POST['image']);
    }

    // Generate slug
    $slug = sanitize_title($organizer_name);
    $original_slug = $slug;
    $counter = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s AND id != %d", $slug, $organizer_id))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $data = array(
        'name' => $organizer_name,
        'slug' => $slug,
        'email' => $organizer_email,
        'phone' => $organizer_phone,
        'website' => $organizer_website,
        'description' => $organizer_description,
        'address' => $organizer_address,
        'social_links' => wp_json_encode($social_links),
        'is_active' => 1,
        'updated_at' => current_time('mysql')
    );

    if ($logo_id > 0) {
        $data['logo'] = $logo_id;
    }

    if ($organizer_id > 0) {
        // Update existing organizer
        $result = $wpdb->update($table, $data, array('id' => $organizer_id));
        $message = __('Organizer updated successfully.', 'sc_events');
    } else {
        // Create new organizer
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        $organizer_id = $wpdb->insert_id;
        $message = __('Organizer created successfully.', 'sc_events');
    }

    if ($result === false) {
        wp_send_json_error(array('message' => __('Failed to save organizer.', 'sc_events')));
    }

    wp_send_json_success(array('message' => $message, 'organizer_id' => $organizer_id));
}

/**
 * Delete organizer from custom table
 */
add_action('wp_ajax_sc_delete_organizer', 'sc_delete_organizer');
function sc_delete_organizer() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;

    if (!$organizer_id) {
        wp_send_json_error(array('message' => __('Invalid organizer ID.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';
    $pivot = $wpdb->prefix . 'sc_event_organizers';

    // Remove from all events first
    $wpdb->delete($pivot, array('organizer_id' => $organizer_id), array('%d'));

    // Delete organizer
    $result = $wpdb->delete($table, array('id' => $organizer_id), array('%d'));

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete organizer.', 'sc_events')));
    }

    wp_send_json_success(array('message' => __('Organizer deleted successfully.', 'sc_events')));
}

/**
 * Get organizers with pagination
 */
add_action('wp_ajax_sc_get_organizers_paginated', 'sc_get_organizers_paginated');
function sc_get_organizers_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_organizers';

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $offset = ($page - 1) * $per_page;

    $where = "WHERE is_active = 1";
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)", $search_like, $search_like);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
    $organizers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table $where ORDER BY name ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Get events count for each organizer from the pivot table (only count existing events)
    $pivot_table = $wpdb->prefix . 'sc_event_organizers';
    $organizer_ids = array_column($organizers, 'id');
    $events_counts = array();
    if (!empty($organizer_ids)) {
        // Sanitize and prepare organizer IDs
        $organizer_ids = array_map('intval', $organizer_ids);
        $organizer_ids = array_filter($organizer_ids);

        if (!empty($organizer_ids)) {
            $events_table = $wpdb->prefix . 'sc_events';
            // Use prepared statement with placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($organizer_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT eo.organizer_id, COUNT(DISTINCT eo.event_id) as count
                FROM $pivot_table eo
                INNER JOIN $events_table e ON eo.event_id = e.id
                WHERE eo.organizer_id IN ($placeholders)
                GROUP BY eo.organizer_id
            ", $organizer_ids));
            foreach ($counts as $row) {
                $events_counts[$row->organizer_id] = intval($row->count);
            }
        }
    }

    $organizers_data = array();
    foreach ($organizers as $organizer) {
        $logo_url = $organizer->logo ? wp_get_attachment_url($organizer->logo) : '';

        $organizers_data[] = array(
            'ID' => $organizer->id,
            'name' => $organizer->name,
            'email' => $organizer->email,
            'phone' => $organizer->phone,
            'website' => $organizer->website,
            'description' => $organizer->description,
            'logo_url' => $logo_url,
            'events_count' => isset($events_counts[$organizer->id]) ? $events_counts[$organizer->id] : 0
        );
    }

    wp_send_json_success(array(
        'organizers' => $organizers_data,
        'total' => intval($total),
        'pages' => ceil($total / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    ));
}

// ==========================================
// CATEGORIES HANDLERS
// ==========================================

/**
 * Get all categories
 */
add_action('wp_ajax_sc_get_categories', 'sc_get_categories');
function sc_get_categories() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $categories = get_terms(array(
        'taxonomy' => 'sc_event_category',
        'hide_empty' => false
    ));

    if (is_wp_error($categories)) {
        wp_send_json_error(array('message' => $categories->get_error_message()));
    }

    $categories_with_color = array();
    foreach ($categories as $category) {
        $category_data = (array) $category;
        $color = get_term_meta($category->term_id, 'color', true);
        $category_data['color'] = $color ?: '#52C41A';
        $categories_with_color[] = $category_data;
    }

    wp_send_json_success(array('categories' => $categories_with_color));
}

/**
 * Get single category
 */
add_action('wp_ajax_sc_get_category', 'sc_get_category');
function sc_get_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category = get_term($category_id, 'sc_event_category');

    if (is_wp_error($category)) {
        wp_send_json_error(array('message' => __('Category not found.', 'sc_events')));
    }

    $color = get_term_meta($category_id, 'color', true);
    $category_data = (array) $category;
    $category_data['color'] = $color ?: '#52C41A';

    wp_send_json_success(array('category' => $category_data));
}

/**
 * Save category
 */
add_action('wp_ajax_sc_save_category', 'sc_save_category');
function sc_save_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    $category_slug = isset($_POST['category_slug']) ? sanitize_title($_POST['category_slug']) : '';
    $category_description = isset($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : '';
    $category_color = isset($_POST['category_color']) ? sanitize_hex_color($_POST['category_color']) : '';

    if (empty($category_name)) {
        wp_send_json_error(array('message' => __('Category name is required.', 'sc_events')));
    }

    $args = array('description' => $category_description);

    if (!empty($category_slug)) {
        $args['slug'] = $category_slug;
    }

    if ($category_id > 0) {
        $result = wp_update_term($category_id, 'sc_event_category', array_merge($args, array('name' => $category_name)));
        $message = __('Category updated successfully.', 'sc_events');

        if (!is_wp_error($result)) {
            $term_id = $result['term_id'];
            if (!empty($category_color)) {
                update_term_meta($term_id, 'color', $category_color);
            }
        }
    } else {
        $result = wp_insert_term($category_name, 'sc_event_category', $args);
        $message = __('Category created successfully.', 'sc_events');

        if (!is_wp_error($result)) {
            $term_id = $result['term_id'];
            if (!empty($category_color)) {
                update_term_meta($term_id, 'color', $category_color);
            }
        }
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('message' => $message));
}

/**
 * Delete category
 */
add_action('wp_ajax_sc_delete_category', 'sc_delete_category');
function sc_delete_category() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    if (!$category_id) {
        wp_send_json_error(array('message' => __('Invalid category ID.', 'sc_events')));
    }

    $result = wp_delete_term($category_id, 'sc_event_category');

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('message' => __('Category deleted successfully.', 'sc_events')));
}

/**
 * Get categories with pagination
 */
add_action('wp_ajax_sc_get_categories_paginated', 'sc_get_categories_paginated');
function sc_get_categories_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $args = array(
        'taxonomy' => 'sc_event_category',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page
    );

    if (!empty($search)) {
        $args['search'] = $search;
    }

    $count_args = $args;
    unset($count_args['number']);
    unset($count_args['offset']);
    $all_categories = get_terms($count_args);
    $total_categories = is_array($all_categories) ? count($all_categories) : 0;

    $categories = get_terms($args);

    if (is_wp_error($categories)) {
        wp_send_json_error(array('message' => $categories->get_error_message()));
    }

    // Get events count for each category from the pivot table (only count existing events)
    global $wpdb;
    $pivot_table = $wpdb->prefix . 'sc_event_categories';
    $events_table = $wpdb->prefix . 'sc_events';
    $category_ids = wp_list_pluck($categories, 'term_id');
    $events_counts = array();
    if (!empty($category_ids)) {
        // Sanitize and prepare category IDs
        $category_ids = array_map('intval', $category_ids);
        $category_ids = array_filter($category_ids);

        if (!empty($category_ids)) {
            // Use prepared statement with placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $counts = $wpdb->get_results($wpdb->prepare("
                SELECT ec.category_id, COUNT(DISTINCT ec.event_id) as count
                FROM $pivot_table ec
                INNER JOIN $events_table e ON ec.event_id = e.id
                WHERE ec.category_id IN ($placeholders)
                GROUP BY ec.category_id
            ", $category_ids));
            foreach ($counts as $row) {
                $events_counts[$row->category_id] = intval($row->count);
            }
        }
    }

    $categories_data = array();
    foreach ($categories as $category) {
        $category_data = array(
            'term_id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => isset($events_counts[$category->term_id]) ? $events_counts[$category->term_id] : 0
        );

        $color = get_term_meta($category->term_id, 'color', true);
        $category_data['color'] = $color ?: '#52C41A';

        $categories_data[] = $category_data;
    }

    $total_pages = ceil($total_categories / $per_page);

    wp_send_json_success(array(
        'categories' => $categories_data,
        'total' => $total_categories,
        'pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ));
}
