<?php
/**
 * Scanner Users AJAX Handlers
 * CRUD operations for scanner user management
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if scanner_permissions table exists
 */
function sc_scanner_perms_table_exists() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_scanner_permissions';
    return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
}

// ==========================================
// GET ALL SCANNERS
// ==========================================
add_action('wp_ajax_sc_get_scanners', 'sc_get_scanners');
function sc_get_scanners() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get all users with event_scanner role
    $scanner_users = get_users(array(
        'role' => 'event_scanner',
        'orderby' => 'registered',
        'order' => 'DESC',
    ));

    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';
    $events_table = $wpdb->prefix . 'sc_events';
    $sessions_table = $wpdb->prefix . 'sc_sessions';

    $perms_exists = sc_scanner_perms_table_exists();

    $scanners = array();
    foreach ($scanner_users as $user) {
        // Get permissions
        $permissions = array();
        if ($perms_exists) {
            $permissions = $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, e.title as event_title, s.title as session_title
                 FROM $perms_table sp
                 LEFT JOIN $events_table e ON sp.event_id = e.id
                 LEFT JOIN $sessions_table s ON sp.session_id = s.id
                 WHERE sp.user_id = %d
                 ORDER BY sp.access_type, e.title, s.title",
                $user->ID
            ));
        }

        // Determine access type
        $access_type = 'full';
        $access_details = array();
        if (!empty($permissions)) {
            $access_type = $permissions[0]->access_type;
            foreach ($permissions as $perm) {
                if ($perm->access_type === 'event' && $perm->event_title) {
                    $access_details[] = $perm->event_title;
                } elseif ($perm->access_type === 'session' && $perm->session_title) {
                    $access_details[] = ($perm->event_title ? $perm->event_title . ' → ' : '') . $perm->session_title;
                }
            }
        }

        $phone = get_user_meta($user->ID, 'phone', true);

        $scanners[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone ?: '',
            'access_type' => $access_type,
            'access_details' => $access_details,
            'registered' => $user->user_registered,
        );
    }

    // Stats
    $total = count($scanners);
    $full_count = 0;
    $event_count = 0;
    $session_count = 0;
    foreach ($scanners as $s) {
        if ($s['access_type'] === 'full') $full_count++;
        elseif ($s['access_type'] === 'event') $event_count++;
        elseif ($s['access_type'] === 'session') $session_count++;
    }

    wp_send_json_success(array(
        'scanners' => $scanners,
        'stats' => array(
            'total' => $total,
            'full' => $full_count,
            'event' => $event_count,
            'session' => $session_count,
        ),
    ));
}

// ==========================================
// GET SINGLE SCANNER
// ==========================================
add_action('wp_ajax_sc_get_scanner', 'sc_get_scanner_handler');
function sc_get_scanner_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    $user = get_user_by('ID', $user_id);
    if (!$user || !in_array('event_scanner', $user->roles)) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';

    $permissions = array();
    if (sc_scanner_perms_table_exists()) {
        $permissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $perms_table WHERE user_id = %d",
            $user_id
        ));
    }

    $access_type = 'full';
    $event_ids = array();
    $session_data = array(); // [{event_id, session_id}]

    if (!empty($permissions)) {
        $access_type = $permissions[0]->access_type;
        foreach ($permissions as $perm) {
            if ($perm->access_type === 'event' && $perm->event_id) {
                $event_ids[] = (int) $perm->event_id;
            } elseif ($perm->access_type === 'session' && $perm->session_id) {
                $session_data[] = array(
                    'event_id' => (int) $perm->event_id,
                    'session_id' => (int) $perm->session_id,
                );
            }
        }
    }

    $phone = get_user_meta($user->ID, 'phone', true);

    wp_send_json_success(array(
        'scanner' => array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone ?: '',
            'access_type' => $access_type,
            'event_ids' => $event_ids,
            'session_data' => $session_data,
        ),
    ));
}

// ==========================================
// SAVE (CREATE) SCANNER
// ==========================================
add_action('wp_ajax_sc_save_scanner', 'sc_save_scanner');
function sc_save_scanner() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $access_type = isset($_POST['access_type']) ? sanitize_text_field($_POST['access_type']) : 'full';

    // Validate
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Name is required.', 'sc_events')));
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => __('Valid email is required.', 'sc_events')));
    }
    if (email_exists($email)) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events')));
    }
    if (empty($password) || strlen($password) < 6) {
        wp_send_json_error(array('message' => __('Password must be at least 6 characters.', 'sc_events')));
    }
    if (!in_array($access_type, array('full', 'event', 'session'))) {
        $access_type = 'full';
    }

    // Create WordPress user
    $user_id = wp_insert_user(array(
        'user_login' => $email,
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'first_name' => $name,
        'role' => 'event_scanner',
    ));

    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
    }

    // Set user meta
    if ($phone) {
        update_user_meta($user_id, 'phone', $phone);
    }
    update_user_meta($user_id, 'sc_scanner_type', 'attendee');
    update_user_meta($user_id, 'sc_role', 'scanner');

    // Insert permissions
    sc_insert_scanner_permissions($user_id, $access_type);

    wp_send_json_success(array(
        'message' => __('Scanner created successfully.', 'sc_events'),
        'user_id' => $user_id,
    ));
}

// ==========================================
// UPDATE SCANNER
// ==========================================
add_action('wp_ajax_sc_update_scanner', 'sc_update_scanner');
function sc_update_scanner() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    $user = get_user_by('ID', $user_id);
    if (!$user || !in_array('event_scanner', $user->roles)) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $access_type = isset($_POST['access_type']) ? sanitize_text_field($_POST['access_type']) : 'full';

    // Validate
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Name is required.', 'sc_events')));
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => __('Valid email is required.', 'sc_events')));
    }
    // Check email uniqueness (exclude current user)
    $existing = email_exists($email);
    if ($existing && $existing !== $user_id) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events')));
    }

    // Update user
    $update_data = array(
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name,
        'user_email' => $email,
    );

    // Update password only if provided
    if (!empty($password)) {
        if (strlen($password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters.', 'sc_events')));
        }
        $update_data['user_pass'] = $password;
    }

    $result = wp_update_user($update_data);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    // Update phone
    update_user_meta($user_id, 'phone', $phone);

    // Re-insert permissions (delete old, insert new)
    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';
    $wpdb->delete($perms_table, array('user_id' => $user_id), array('%d'));

    sc_insert_scanner_permissions($user_id, $access_type);

    wp_send_json_success(array(
        'message' => __('Scanner updated successfully.', 'sc_events'),
    ));
}

// ==========================================
// DELETE SCANNER
// ==========================================
add_action('wp_ajax_sc_delete_scanner', 'sc_delete_scanner');
function sc_delete_scanner() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    $user = get_user_by('ID', $user_id);
    if (!$user || !in_array('event_scanner', $user->roles)) {
        wp_send_json_error(array('message' => __('Scanner not found.', 'sc_events')));
    }

    // Delete permissions
    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';
    $wpdb->delete($perms_table, array('user_id' => $user_id), array('%d'));

    // Change role to subscriber
    $user_obj = new WP_User($user_id);
    $user_obj->set_role('subscriber');

    // Remove scanner-specific meta
    delete_user_meta($user_id, 'sc_scanner_type');
    delete_user_meta($user_id, 'sc_role');

    wp_send_json_success(array(
        'message' => __('Scanner deleted successfully.', 'sc_events'),
    ));
}

// ==========================================
// HELPER: INSERT SCANNER PERMISSIONS
// ==========================================
function sc_insert_scanner_permissions($user_id, $access_type) {
    if (!sc_scanner_perms_table_exists()) return;

    global $wpdb;
    $perms_table = $wpdb->prefix . 'sc_scanner_permissions';
    $current_user_id = get_current_user_id();

    if ($access_type === 'full') {
        $wpdb->insert($perms_table, array(
            'user_id' => $user_id,
            'access_type' => 'full',
            'event_id' => null,
            'session_id' => null,
            'created_by' => $current_user_id,
        ));
    } elseif ($access_type === 'event') {
        $event_ids = isset($_POST['event_ids']) ? (array) $_POST['event_ids'] : array();
        foreach ($event_ids as $event_id) {
            $event_id = intval($event_id);
            if ($event_id > 0) {
                $wpdb->insert($perms_table, array(
                    'user_id' => $user_id,
                    'access_type' => 'event',
                    'event_id' => $event_id,
                    'session_id' => null,
                    'created_by' => $current_user_id,
                ));
            }
        }
    } elseif ($access_type === 'session') {
        $session_permissions = isset($_POST['session_permissions']) ? (array) $_POST['session_permissions'] : array();
        foreach ($session_permissions as $perm) {
            $event_id = intval($perm['event_id'] ?? 0);
            $session_id = intval($perm['session_id'] ?? 0);
            if ($event_id > 0 && $session_id > 0) {
                $wpdb->insert($perms_table, array(
                    'user_id' => $user_id,
                    'access_type' => 'session',
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'created_by' => $current_user_id,
                ));
            }
        }
    }
}
