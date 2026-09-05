<?php
/**
 * Event Manager Permissions & Access Control
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================
 * SECURITY HELPER FUNCTIONS
 * =========================================
 */

// Note: sc_verify_ajax_request() is now defined in security-utilities.php
// with enhanced parameters: sc_verify_ajax_request($nonce_action, $capability, $nonce_key)

/**
 * Check if current user can access/modify an event
 * Admins and Event Managers can access all events
 *
 * @param int $event_id The event ID to check
 * @return bool True if user can access, false otherwise
 */
function sc_user_can_access_event($event_id) {
    if (!$event_id) {
        return false;
    }

    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'sc_event') {
        return false;
    }

    // Admins can access all events
    if (current_user_can('administrator')) {
        return true;
    }

    // Event Managers can access all events (they manage the platform)
    if (current_user_can('event_manager') || SC_Event_Manager_Dashboard::is_event_manager()) {
        return true;
    }

    // Fallback: check if user is the author
    return (int) $event->post_author === get_current_user_id();
}

/**
 * Check if current user can access/modify an attendee
 *
 * @param int $attendee_id The attendee ID to check
 * @return bool True if user can access, false otherwise
 */
function sc_user_can_access_attendee($attendee_id) {
    if (!$attendee_id) {
        return false;
    }

    $attendee = get_post($attendee_id);
    if (!$attendee || $attendee->post_type !== 'sc_attendee') {
        return false;
    }

    // Admins can access all attendees
    if (current_user_can('administrator')) {
        return true;
    }

    // Get the event associated with this attendee
    $event_id = get_post_meta($attendee_id, 'sc_event_id', true);
    if (!$event_id) {
        return false;
    }

    // Check if user owns the event
    return sc_user_can_access_event($event_id);
}

/**
 * Verify event ownership and return error if not authorized
 *
 * @param int $event_id The event ID to check
 * @return void Dies with JSON error if not authorized
 */
function sc_verify_event_ownership($event_id) {
    if (!sc_user_can_access_event($event_id)) {
        wp_send_json_error(array('message' => __('You do not have permission to access this event.', 'sc_events')));
    }
}

/**
 * Verify attendee ownership and return error if not authorized
 *
 * @param int $attendee_id The attendee ID to check
 * @return void Dies with JSON error if not authorized
 */
function sc_verify_attendee_ownership($attendee_id) {
    if (!sc_user_can_access_attendee($attendee_id)) {
        wp_send_json_error(array('message' => __('You do not have permission to access this attendee.', 'sc_events')));
    }
}

/**
 * Get events that current user can manage
 *
 * @param array $args Additional WP_Query args
 * @return array Array of events
 */
function sc_get_user_events($args = array()) {
    $default_args = array(
        'post_type' => 'sc_event',
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => -1,
    );

    // Admins and Event Managers can see all events
    // Other users can only see their own events
    if (!current_user_can('administrator') && !current_user_can('event_manager')) {
        $default_args['author'] = get_current_user_id();
    }

    $args = wp_parse_args($args, $default_args);
    return get_posts($args);
}

/**
 * Grant Event Manager access to SC Events features
 */
add_action('admin_init', 'sc_grant_event_manager_eventin_access');
function sc_grant_event_manager_eventin_access() {
    $role = get_role('event_manager');

    if ($role) {
        // Get all capabilities from editor role
        $editor = get_role('editor');
        if ($editor) {
            foreach ($editor->capabilities as $cap => $granted) {
                $role->add_cap($cap);
            }
        }

        // Add SC Events management capability
        $role->add_cap('manage_sc_event');
        $role->add_cap('manage_sc_speaker');
        $role->add_cap('manage_sc_schedule');
        $role->add_cap('manage_sc_attendee');

        // Grant ALL SC Events capabilities
        $eventin_caps = array(
            'edit_sc_event', 'read_sc_event', 'delete_sc_event', 'edit_sc_events', 'edit_others_sc_events',
            'publish_sc_events', 'read_private_sc_events', 'delete_sc_events', 'delete_private_sc_events',
            'delete_published_sc_events', 'delete_others_sc_events', 'edit_private_sc_events', 'edit_published_sc_events',

            'edit_sc_attendee', 'read_sc_attendee', 'delete_sc_attendee', 'edit_sc_attendees',
            'edit_others_sc_attendees', 'publish_sc_attendees', 'read_private_sc_attendees',
            'delete_sc_attendees', 'delete_private_sc_attendees', 'delete_published_sc_attendees',

            'edit_sc_speaker', 'read_sc_speaker', 'delete_sc_speaker', 'edit_sc_speakers',
            'edit_others_sc_speakers', 'publish_sc_speakers', 'delete_sc_speakers',

            'edit_sc_schedule', 'read_sc_schedule', 'delete_sc_schedule', 'edit_sc_schedules',
            'publish_sc_schedules', 'delete_sc_schedules',

            'edit_sc_zoom', 'manage_sc_settings', 'view_sc_reports',
            
            // Additional capabilities required by sc_pro_dashboard shortcode
            // Note: These are also granted via user_has_cap filters for dynamic access
            'sc_manage_attendee', 'seller', 'author'
        );

        foreach ($eventin_caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

/**
 * Hide Admin Bar for Event Managers
 */
add_action('after_setup_theme', 'sc_hide_admin_bar_for_event_managers');
function sc_hide_admin_bar_for_event_managers() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Hide admin bar for event managers (not for admins)
        if (in_array('event_manager', $current_user->roles) && !in_array('administrator', $current_user->roles)) {
            show_admin_bar(false);
        }
    }
}

/**
 * Redirect Event Managers away from wp-admin (except for specific pages)
 * NOTE: This only runs in wp-admin, not on frontend dashboard pages
 */
add_action('admin_init', 'sc_redirect_event_managers_from_admin');
function sc_redirect_event_managers_from_admin() {
    // Only run in wp-admin, not on frontend
    if (!is_admin()) {
        return;
    }
    
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();

    // Only affect event managers (not admins)
    if (!in_array('event_manager', $current_user->roles) || in_array('administrator', $current_user->roles)) {
        return;
    }

    // Allow AJAX requests
    if (wp_doing_ajax()) {
        return;
    }

    // Get current page
    global $pagenow;

    // Allowed pages for event managers
    $allowed_pages = array(
        'admin-ajax.php',
        'async-upload.php', // For media uploads
        'media-upload.php',
        'post.php',         // Edit event
        'post-new.php',     // Create new event
        'edit.php',         // Events list
        'profile.php',      // User profile
        'user-edit.php',    // Edit user
    );

    // Check if current page is allowed
    if (in_array($pagenow, $allowed_pages)) {
        // For post pages, only allow if it's etn post type
        if (in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))) {
            $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';

            // If editing existing post, get post type
            if (isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
                $post = get_post($post_id);
                if ($post) {
                    $post_type = $post->post_type;
                }
            }

            // Only allow etn post type and related
            $allowed_post_types = array('sc_event', 'sc_speaker', 'sc_schedule', 'sc_attendee', 'sc_coupon');
            if (!in_array($post_type, $allowed_post_types)) {
                wp_redirect(home_url('/event-manager-dashboard/home'));
                exit;
            }
        }

        return; // Allow access
    }

    // Check if accessing SC Events pages
    if (isset($_GET['page']) && strpos(['page'], 'sc_event') === 0) {
        return; // Allow SC Events pages
    }

    // Redirect to dashboard for all other admin pages
    wp_redirect(home_url('/event-manager-dashboard/home'));
    exit;
}

/**
 * Customize admin menu for Event Managers
 */
add_action('admin_menu', 'sc_customize_event_manager_menu', 999);
function sc_customize_event_manager_menu() {
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();

    // Only affect event managers (not admins)
    if (!in_array('event_manager', $current_user->roles) || in_array('administrator', $current_user->roles)) {
        return;
    }

    // Remove unnecessary menu items for event managers
    remove_menu_page('index.php');                  // Dashboard
    remove_menu_page('edit.php');                   // Posts
    remove_menu_page('upload.php');                 // Media (keep if needed)
    remove_menu_page('edit.php?post_type=page');    // Pages
    remove_menu_page('edit-comments.php');          // Comments
    remove_menu_page('themes.php');                 // Appearance
    remove_menu_page('plugins.php');                // Plugins
    remove_menu_page('users.php');                  // Users
    remove_menu_page('tools.php');                  // Tools
    remove_menu_page('options-general.php');        // Settings

    // Add custom dashboard link
    add_menu_page(
        'Event Dashboard',
        'Event Dashboard',
        'read',
        'sc-event-dashboard',
        'sc_redirect_to_custom_dashboard',
        'dashicons-calendar-alt',
        2
    );
}

/**
 * Redirect to custom dashboard
 */
function sc_redirect_to_custom_dashboard() {
    wp_redirect(home_url('/event-manager-dashboard/home'));
    exit;
}

/**
 * Redirect Event Managers to custom dashboard after login
 */
add_filter('login_redirect', 'sc_event_manager_login_redirect', 10, 3);
function sc_event_manager_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        // Redirect event managers to custom dashboard
        if (in_array('event_manager', $user->roles) && !in_array('administrator', $user->roles)) {
            return home_url('/event-manager-dashboard/home');
        }
    }
    return $redirect_to;
}

/**
 * Filter SC Events dashboard access
 */
add_filter('eventin_user_can_access_dashboard', 'sc_allow_event_managers_eventin_access', 10, 2);
function sc_allow_event_managers_eventin_access($can_access, $user_id) {
    $user = get_userdata($user_id);

    if ($user && in_array('event_manager', $user->roles)) {
        return true;
    }

    return $can_access;
}

/**
 * Allow Event Managers to access SC Events shortcodes
 */
add_filter('eventin_dashboard_user_role', 'sc_add_event_manager_to_eventin_roles');
function sc_add_event_manager_to_eventin_roles($roles) {
    if (!in_array('event_manager', $roles)) {
        $roles[] = 'event_manager';
    }
    return $roles;
}

/**
 * Override SC Events permission check for Event Managers
 */
add_filter('user_has_cap', 'sc_event_manager_eventin_caps', 10, 4);
function sc_event_manager_eventin_caps($allcaps, $caps, $args, $user) {
    // Check if user has event_manager role
    if (isset($user->roles) && in_array('event_manager', (array)$user->roles)) {
        // Grant all SC Events-related capabilities
        if (isset($caps[0])) {
            $cap = $caps[0];

            // Check if it's an SC Events capability
            if (strpos($cap, 'sc_event') !== false || strpos($cap, 'manage_sc') !== false) {
                $allcaps[$cap] = true;
            }

            // Specific SC Events caps
            $eventin_caps = array('manage_sc_event', 'manage_sc_speaker', 'manage_sc_schedule', 'view_sc_reports');
            if (in_array($cap, $eventin_caps)) {
                $allcaps[$cap] = true;
            }
        }
    }

    return $allcaps;
}

/**
 * Allow Event Manager to bypass SC Events role checks
 */
add_filter('eventin_pro_dashboard_user_roles', 'sc_add_event_manager_to_pro_dashboard', 999);
function sc_add_event_manager_to_pro_dashboard($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = array();
    }

    if (!in_array('event_manager', $allowed_roles)) {
        $allowed_roles[] = 'event_manager';
    }

    return $allowed_roles;
}

/**
 * Force allow Event Manager for SC Events checks
 */
add_filter('eventin_check_user_role', 'sc_force_allow_event_manager', 999, 2);
function sc_force_allow_event_manager($allowed, $user_id) {
    $user = get_userdata($user_id);

    if ($user && in_array('event_manager', (array)$user->roles)) {
        return true;
    }

    return $allowed;
}

/**
 * Grant sc_manage_attendee capability to Event Managers
 * This is required for sc_pro_dashboard shortcode to work
 */
add_filter('user_has_cap', 'sc_grant_sc_manage_attendee_to_event_manager', 10, 4);
function sc_grant_sc_manage_attendee_to_event_manager($allcaps, $caps, $args, $user) {
    // Check if user has event_manager role
    if (isset($user->roles) && in_array('event_manager', (array)$user->roles)) {
        // Grant sc_manage_attendee capability (required by sc_pro_dashboard shortcode)
        $allcaps['sc_manage_attendee'] = true;
    }

    return $allcaps;
}

/**
 * Override SC Events dashboard permission check to allow Event Managers
 * This filter hooks into the shortcode permission check
 */
add_filter('sc_pro_dashboard_can_access', 'sc_allow_event_manager_dashboard_access', 10, 1);
function sc_allow_event_manager_dashboard_access($can_access) {
    if (!is_user_logged_in()) {
        return $can_access;
    }

    $current_user = wp_get_current_user();
    
    // Allow Event Managers to access dashboard
    if (in_array('event_manager', (array)$current_user->roles)) {
        return true;
    }

    return $can_access;
}

/**
 * Add temporary author capability to Event Managers for sc_pro_dashboard shortcode
 * The shortcode checks for: sc_manage_attendee OR seller OR author
 */
add_filter('user_has_cap', 'sc_grant_author_cap_to_event_manager_for_dashboard', 10, 4);
function sc_grant_author_cap_to_event_manager_for_dashboard($allcaps, $caps, $args, $user) {
    // Only apply on frontend dashboard pages
    if (!is_admin() && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'event-manager-dashboard') !== false) {
        if (isset($user->roles) && in_array('event_manager', (array)$user->roles)) {
            // Grant author capability temporarily for dashboard access
            $allcaps['author'] = true;
        }
    }
    
    return $allcaps;
}
