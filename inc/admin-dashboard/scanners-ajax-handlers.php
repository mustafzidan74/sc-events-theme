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

/**
 * What the current user may scan, following the same rule as the scanner page:
 * managers, and scanners with no rows or a single "full" row, may scan anything.
 *
 * @return array{full: bool, events: int[], event_wide: int[], sessions: int[]}
 *               events = every event the user touches (event or session rows),
 *               event_wide = events granted as a whole (every session inside them).
 */
function sc_get_scanner_scope() {
    static $scope = null;
    if ($scope !== null) {
        return $scope;
    }

    $scope = array('full' => true, 'events' => array(), 'event_wide' => array(), 'sessions' => array());

    if (!SC_Event_Manager_Dashboard::is_event_scanner() || !sc_scanner_perms_table_exists()) {
        return $scope;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT access_type, event_id, session_id FROM {$wpdb->prefix}sc_scanner_permissions WHERE user_id = %d",
        get_current_user_id()
    ));

    if (empty($rows) || (count($rows) === 1 && $rows[0]->access_type === 'full')) {
        return $scope;
    }

    $scope['full'] = false;
    foreach ($rows as $row) {
        if ($row->event_id) {
            $scope['events'][] = (int) $row->event_id;
            if ($row->access_type === 'event') {
                $scope['event_wide'][] = (int) $row->event_id;
            }
        }
        if ($row->session_id) {
            $scope['sessions'][] = (int) $row->session_id;
        }
    }
    $scope['events']     = array_values(array_unique($scope['events']));
    $scope['event_wide'] = array_values(array_unique($scope['event_wide']));
    $scope['sessions']   = array_values(array_unique($scope['sessions']));

    return $scope;
}

function sc_scanner_can_access_event($event_id) {
    $scope = sc_get_scanner_scope();
    return $scope['full'] || in_array((int) $event_id, $scope['events'], true);
}

function sc_scanner_can_access_session($session_id, $event_id) {
    $scope = sc_get_scanner_scope();
    return $scope['full']
        || in_array((int) $session_id, $scope['sessions'], true)
        || in_array((int) $event_id, $scope['event_wide'], true);
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

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $access_type = isset($_POST['access_type']) ? sanitize_key(wp_unslash($_POST['access_type'])) : 'full';
    sc_scanner_validate_access($access_type);

    // Validate
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Name is required.', 'sc_events'), 'errors' => array('name' => __('Name is required.', 'sc_events'))));
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => __('Valid email is required.', 'sc_events'), 'errors' => array('email' => __('Valid email is required.', 'sc_events'))));
    }
    if (email_exists($email)) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events'), 'errors' => array('email' => __('This email is already registered.', 'sc_events'))));
    }
    if (empty($password) || strlen($password) < 8) {
        wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'sc_events'), 'errors' => array('password' => __('Password must be at least 8 characters.', 'sc_events'))));
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

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $access_type = isset($_POST['access_type']) ? sanitize_key(wp_unslash($_POST['access_type'])) : 'full';
    sc_scanner_validate_access($access_type);

    // Validate
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Name is required.', 'sc_events'), 'errors' => array('name' => __('Name is required.', 'sc_events'))));
    }
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => __('Valid email is required.', 'sc_events'), 'errors' => array('email' => __('Valid email is required.', 'sc_events'))));
    }
    // Check email uniqueness (exclude current user)
    $existing = email_exists($email);
    if ($existing && (int) $existing !== $user_id) {
        wp_send_json_error(array('message' => __('This email is already registered.', 'sc_events'), 'errors' => array('email' => __('This email is already registered.', 'sc_events'))));
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
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'sc_events'), 'errors' => array('password' => __('Password must be at least 8 characters.', 'sc_events'))));
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

/**
 * Refuse a limited scanner with nothing to scan: no permission rows means full
 * access on the scanner page, so an empty selection silently unlocked everything.
 */
function sc_scanner_validate_access($access_type) {
    if ($access_type === 'event') {
        $ids = array_filter(array_map('intval', (array) wp_unslash($_POST['event_ids'] ?? array())));
        if (!$ids) {
            wp_send_json_error(array('message' => __('Choose at least one event.', 'sc_events'), 'errors' => array('event_ids[]' => __('Choose at least one event.', 'sc_events'))));
        }
    } elseif ($access_type === 'session') {
        $ok = false;
        foreach ((array) wp_unslash($_POST['session_permissions'] ?? array()) as $perm) {
            if (is_array($perm) && intval($perm['event_id'] ?? 0) > 0 && intval($perm['session_id'] ?? 0) > 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            wp_send_json_error(array('message' => __('Choose at least one session.', 'sc_events'), 'errors' => array('session_ids[]' => __('Choose at least one session.', 'sc_events'))));
        }
    }
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

// ==========================================
// LIST (list pattern) + BULK ACCESS
// ==========================================

/**
 * Every scanner's access rows resolved to events, with whether each event is over.
 *
 * @param int[] $user_ids
 * @return array user_id => array{full: bool, events: array<int, array{id:int,title:string,ended:bool,sessions:string[]}>}
 */
function sc_scanner_access_map($user_ids) {
    global $wpdb;
    $map = array();
    if (!$user_ids || !sc_scanner_perms_table_exists()) {
        return $map;
    }
    $today = current_time('Y-m-d');
    $in = implode(',', array_map('intval', $user_ids));
    $rows = $wpdb->get_results(
        "SELECT sp.user_id, sp.access_type, sp.event_id, sp.session_id, e.title AS event_title,
                COALESCE(e.end_date, e.start_date) AS last_day, s.title AS session_title
         FROM {$wpdb->prefix}sc_scanner_permissions sp
         LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = sp.event_id
         LEFT JOIN {$wpdb->prefix}sc_sessions s ON s.id = sp.session_id
         WHERE sp.user_id IN ({$in})
         ORDER BY e.start_date DESC, s.session_date, s.start_time"
    );
    foreach ($rows as $r) {
        $uid = (int) $r->user_id;
        if (!isset($map[$uid])) {
            $map[$uid] = array('full' => false, 'events' => array());
        }
        if ($r->access_type === 'full') {
            $map[$uid]['full'] = true;
            continue;
        }
        $eid = (int) $r->event_id;
        if (!$eid) {
            continue;
        }
        if (!isset($map[$uid]['events'][$eid])) {
            $map[$uid]['events'][$eid] = array(
                'id'       => $eid,
                'title'    => $r->event_title !== null ? $r->event_title : sprintf(__('Deleted event #%d', 'sc_events'), $eid),
                'ended'    => !$r->last_day || $r->last_day < $today,
                'sessions' => array(),
            );
        }
        if ($r->access_type === 'session' && $r->session_title !== null) {
            $map[$uid]['events'][$eid]['sessions'][] = $r->session_title;
        }
    }
    return $map;
}

add_action('wp_ajax_sc_get_scanners_paginated', 'sc_get_scanners_paginated');
function sc_get_scanners_paginated() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
    global $wpdb;

    $page     = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
    $per_page = min(200, max(10, absint(wp_unslash($_POST['per_page'] ?? 25))));
    $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
    $view     = sanitize_key(wp_unslash($_POST['view'] ?? 'all'));
    $orderby  = sanitize_key(wp_unslash($_POST['orderby'] ?? 'registered'));
    $order    = sanitize_key(wp_unslash($_POST['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    // A few dozen accounts: load them all, classify, then page in PHP.
    $users = get_users(array('role' => 'event_scanner', 'fields' => array('ID', 'display_name', 'user_email', 'user_registered')));
    $ids = array_map('intval', wp_list_pluck($users, 'ID'));
    $access = sc_scanner_access_map($ids);

    $checkins = array();
    if ($ids) {
        foreach ($wpdb->get_results(
            "SELECT checked_in_by, COUNT(*) AS n, MAX(checked_in_at) AS last_at FROM {$wpdb->prefix}sc_attendees
             WHERE checked_in = 1 AND checked_in_by IN (" . implode(',', $ids) . ") GROUP BY checked_in_by"
        ) as $c) {
            $checkins[(int) $c->checked_in_by] = $c;
        }
    }

    $rows = array();
    $counts = array('all' => 0, 'ready' => 0, 'ended' => 0, 'full' => 0);
    foreach ($users as $u) {
        $uid = (int) $u->ID;
        $a = $access[$uid] ?? array('full' => true, 'events' => array());
        // No rows at all means unrestricted, same as the scanner page.
        $full = $a['full'] || !$a['events'];
        $events = array_values($a['events']);
        $has_current = $full || (bool) array_filter($events, function ($e) { return !$e['ended']; });
        $state = $full ? 'full' : ($has_current ? 'ready' : 'ended');

        $counts['all']++;
        $counts[$state]++;
        if ($state === 'full') {
            $counts['ready']++;
        }

        if ($search !== '') {
            $hay = mb_strtolower($u->display_name . ' ' . $u->user_email . ' ' . get_user_meta($uid, 'phone', true));
            if (mb_strpos($hay, mb_strtolower($search)) === false) {
                continue;
            }
        }
        if (($view === 'ready' && !$has_current) || ($view === 'ended' && $state !== 'ended') || ($view === 'full' && $state !== 'full')) {
            continue;
        }

        $rows[] = array(
            'id'          => $uid,
            'name'        => $u->display_name,
            'email'       => $u->user_email,
            'phone'       => (string) get_user_meta($uid, 'phone', true),
            'registered'  => $u->user_registered,
            'full'        => $full,
            'state'       => $state,
            'events'      => $events,
            'checkins'    => isset($checkins[$uid]) ? (int) $checkins[$uid]->n : 0,
            'last_checkin'=> isset($checkins[$uid]) ? $checkins[$uid]->last_at : null,
        );
    }

    $sorters = array(
        'name'       => function ($a, $b) { return strcasecmp($a['name'], $b['name']); },
        'checkins'   => function ($a, $b) { return $a['checkins'] <=> $b['checkins']; },
        'registered' => function ($a, $b) { return strcmp($a['registered'], $b['registered']); },
    );
    $sorter = $sorters[$orderby] ?? $sorters['registered'];
    usort($rows, function ($a, $b) use ($sorter, $order) {
        $r = $sorter($a, $b);
        return $order === 'ASC' ? $r : -$r;
    });

    $total = count($rows);
    $response = array(
        'scanners' => array_slice($rows, ($page - 1) * $per_page, $per_page),
        'total'    => $total,
    );
    if (!empty($_POST['with_counts'])) {
        $response['counts'] = $counts;
    }
    wp_send_json_success($response);
}

/**
 * Give many scanners access to events at once: add the events to what they have,
 * or replace what they have. Accounts with full access are left alone when adding.
 */
add_action('wp_ajax_sc_bulk_scanner_access', 'sc_bulk_scanner_access');
function sc_bulk_scanner_access() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
    if (!sc_scanner_perms_table_exists()) {
        wp_send_json_error(array('message' => __('Scanner permissions are not available.', 'sc_events')));
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_scanner_permissions';

    $user_ids  = array_filter(array_map('absint', (array) wp_unslash($_POST['user_ids'] ?? array())));
    $event_ids = array_filter(array_map('absint', (array) wp_unslash($_POST['event_ids'] ?? array())));
    $mode      = sanitize_key(wp_unslash($_POST['mode'] ?? 'add')) === 'replace' ? 'replace' : 'add';

    if (!$user_ids) {
        wp_send_json_error(array('message' => __('Choose at least one scanner.', 'sc_events')));
    }
    if (!$event_ids) {
        wp_send_json_error(array('message' => __('Choose at least one event.', 'sc_events'), 'errors' => array('event_ids' => __('Choose at least one event.', 'sc_events'))));
    }
    $known = array_map('intval', $wpdb->get_col("SELECT id FROM {$wpdb->prefix}sc_events WHERE id IN (" . implode(',', $event_ids) . ')'));
    $event_ids = array_values(array_intersect($event_ids, $known));
    if (!$event_ids) {
        wp_send_json_error(array('message' => __('Those events no longer exist.', 'sc_events')));
    }

    $changed = 0;
    $skipped = 0;
    $me = get_current_user_id();
    foreach ($user_ids as $uid) {
        $user = get_user_by('ID', $uid);
        if (!$user || !in_array('event_scanner', (array) $user->roles, true)) {
            $skipped++;
            continue;
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT access_type, event_id, session_id FROM {$table} WHERE user_id = %d", $uid));
        $is_full = !$rows || (bool) array_filter($rows, function ($r) { return $r->access_type === 'full'; });
        if ($mode === 'add' && $is_full) {
            $skipped++;
            continue;
        }
        if ($mode === 'replace') {
            $wpdb->delete($table, array('user_id' => $uid), array('%d'));
            $have = array();
        } else {
            $have = array_map('intval', wp_list_pluck(array_filter($rows, function ($r) { return $r->access_type === 'event'; }), 'event_id'));
        }
        foreach ($event_ids as $eid) {
            if (in_array($eid, $have, true)) {
                continue;
            }
            $wpdb->insert($table, array('user_id' => $uid, 'access_type' => 'event', 'event_id' => $eid, 'session_id' => null, 'created_by' => $me));
        }
        $changed++;
    }

    wp_send_json_success(array(
        'message' => sprintf(
            /* translators: 1: scanners updated, 2: scanners left unchanged */
            __('%1$d scanners updated, %2$d left as they were.', 'sc_events'),
            $changed,
            $skipped
        ),
        'changed' => $changed,
        'skipped' => $skipped,
    ));
}
