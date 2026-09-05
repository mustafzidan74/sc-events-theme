<?php
/**
 * SC Events Scanner REST API
 *
 * Independent Scanner API - No Eventin dependency
 * Provides REST endpoints for QR code scanning and check-in functionality
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Scanner REST API routes
 */
add_action('rest_api_init', 'sc_register_scanner_api_routes');

function sc_register_scanner_api_routes() {
    $namespace = 'sc-events/v1';

    // Verify ticket/QR code
    register_rest_route($namespace, '/scanner/verify', array(
        'methods'             => 'POST',
        'callback'            => 'sc_scanner_verify_ticket',
        'permission_callback' => 'sc_scanner_permission_check',
        'args'                => array(
            'ticket_code' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'event_id' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Check-in attendee
    register_rest_route($namespace, '/scanner/checkin', array(
        'methods'             => 'POST',
        'callback'            => 'sc_scanner_checkin_attendee',
        'permission_callback' => 'sc_scanner_permission_check',
        'args'                => array(
            'attendee_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Undo check-in
    register_rest_route($namespace, '/scanner/undo-checkin', array(
        'methods'             => 'POST',
        'callback'            => 'sc_scanner_undo_checkin',
        'permission_callback' => 'sc_scanner_permission_check',
        'args'                => array(
            'attendee_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Get event attendees list
    register_rest_route($namespace, '/scanner/attendees/(?P<event_id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'sc_scanner_get_attendees',
        'permission_callback' => 'sc_scanner_permission_check',
        'args'                => array(
            'event_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'search' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'status' => array(
                'required'          => false,
                'type'              => 'string',
                'enum'              => array('all', 'checked_in', 'not_checked_in'),
                'default'           => 'all',
            ),
        ),
    ));

    // Get event statistics
    register_rest_route($namespace, '/scanner/stats/(?P<event_id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'sc_scanner_get_stats',
        'permission_callback' => 'sc_scanner_permission_check',
        'args'                => array(
            'event_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Get events list for scanner
    register_rest_route($namespace, '/scanner/events', array(
        'methods'             => 'GET',
        'callback'            => 'sc_scanner_get_events',
        'permission_callback' => 'sc_scanner_permission_check',
    ));

    // Authentication endpoint
    register_rest_route($namespace, '/scanner/auth', array(
        'methods'             => 'POST',
        'callback'            => 'sc_scanner_authenticate',
        'permission_callback' => '__return_true',
        'args'                => array(
            'username' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_user',
            ),
            'password' => array(
                'required'          => true,
                'type'              => 'string',
            ),
        ),
    ));
}

/**
 * Permission check for scanner API
 */
function sc_scanner_permission_check($request) {
    // Check for Bearer token in Authorization header
    $auth_header = $request->get_header('Authorization');

    if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
        // Fallback to logged in user check
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required.', 'sc_events'),
                array('status' => 401)
            );
        }

        // Check user capability
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions.', 'sc_events'),
                array('status' => 403)
            );
        }

        return true;
    }

    // Validate Bearer token
    $token = substr($auth_header, 7);
    $valid_token = sc_validate_scanner_token($token);

    if (!$valid_token) {
        return new WP_Error(
            'rest_forbidden',
            __('Invalid or expired token.', 'sc_events'),
            array('status' => 401)
        );
    }

    return true;
}

/**
 * Authenticate and get token
 */
function sc_scanner_authenticate($request) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');

    // Authenticate user
    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        return new WP_Error(
            'authentication_failed',
            __('Invalid username or password.', 'sc_events'),
            array('status' => 401)
        );
    }

    // Check if user has permission
    if (!user_can($user->ID, 'edit_posts')) {
        return new WP_Error(
            'insufficient_permissions',
            __('User does not have scanner permissions.', 'sc_events'),
            array('status' => 403)
        );
    }

    // Generate token
    $token = sc_generate_scanner_token($user->ID);

    return array(
        'success' => true,
        'token'   => $token,
        'user'    => array(
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
        ),
        'expires' => time() + (DAY_IN_SECONDS * 7), // Token valid for 7 days
    );
}

/**
 * Generate scanner token
 */
function sc_generate_scanner_token($user_id) {
    $token_data = array(
        'user_id'    => $user_id,
        'created_at' => time(),
        'expires_at' => time() + (DAY_IN_SECONDS * 7),
        'random'     => wp_generate_password(32, false),
    );

    $token = base64_encode(json_encode($token_data));
    $signature = hash_hmac('sha256', $token, wp_salt('auth'));
    $full_token = $token . '.' . $signature;

    // Store token hash for validation
    update_user_meta($user_id, '_sc_scanner_token_hash', hash('sha256', $full_token));
    update_user_meta($user_id, '_sc_scanner_token_expires', $token_data['expires_at']);

    return $full_token;
}

/**
 * Validate scanner token
 */
function sc_validate_scanner_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }

    list($token_data, $signature) = $parts;

    // Verify signature
    $expected_signature = hash_hmac('sha256', $token_data, wp_salt('auth'));
    if (!hash_equals($expected_signature, $signature)) {
        return false;
    }

    // Decode token data
    $data = json_decode(base64_decode($token_data), true);
    if (!$data || !isset($data['user_id']) || !isset($data['expires_at'])) {
        return false;
    }

    // Check expiration
    if ($data['expires_at'] < time()) {
        return false;
    }

    // Verify token hash matches stored hash
    $stored_hash = get_user_meta($data['user_id'], '_sc_scanner_token_hash', true);
    if (!$stored_hash || !hash_equals($stored_hash, hash('sha256', $token))) {
        return false;
    }

    return $data['user_id'];
}

/**
 * Verify ticket/QR code
 */
function sc_scanner_verify_ticket($request) {
    $ticket_code = $request->get_param('ticket_code');
    $event_id = $request->get_param('event_id');

    if (!class_exists('SC_Attendee')) {
        return new WP_Error(
            'system_error',
            __('Attendee system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Find attendee by ticket code
    $attendee = SC_Attendee::get_by_ticket_code($ticket_code);

    if (!$attendee) {
        return array(
            'success' => false,
            'status'  => 'invalid',
            'message' => __('Invalid ticket code. No attendee found.', 'sc_events'),
        );
    }

    // Check if event matches (if event_id provided)
    if ($event_id && $attendee->event_id != $event_id) {
        return array(
            'success' => false,
            'status'  => 'wrong_event',
            'message' => __('This ticket is for a different event.', 'sc_events'),
        );
    }

    // Get event details
    $event = null;
    if (class_exists('SC_Event')) {
        $event = SC_Event::get($attendee->event_id);
    }

    // Build response
    $response = array(
        'success'   => true,
        'status'    => $attendee->checked_in ? 'already_checked_in' : 'valid',
        'message'   => $attendee->checked_in
            ? __('Already checked in', 'sc_events')
            : __('Valid ticket - Ready to check in', 'sc_events'),
        'attendee'  => array(
            'id'             => $attendee->id,
            'name'           => $attendee->name,
            'email'          => $attendee->email,
            'phone'          => $attendee->phone,
            'ticket_code'    => $attendee->ticket_code,
            'ticket_name'    => $attendee->ticket_name,
            'checked_in'     => (bool) $attendee->checked_in,
            'checked_in_at'  => $attendee->checked_in_at,
            'payment_status' => $attendee->payment_status,
            'extra_fields'   => $attendee->extra_fields ? json_decode($attendee->extra_fields, true) : null,
        ),
        'event'     => $event ? array(
            'id'         => $event->id,
            'title'      => $event->title,
            'start_date' => $event->start_date,
            'start_time' => $event->start_time,
            'location'   => $event->venue_name,
        ) : null,
    );

    return $response;
}

/**
 * Check-in attendee
 */
function sc_scanner_checkin_attendee($request) {
    $attendee_id = $request->get_param('attendee_id');

    if (!class_exists('SC_Attendee')) {
        return new WP_Error(
            'system_error',
            __('Attendee system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Get attendee
    $attendee = SC_Attendee::get($attendee_id);

    if (!$attendee) {
        return new WP_Error(
            'not_found',
            __('Attendee not found.', 'sc_events'),
            array('status' => 404)
        );
    }

    // Check if already checked in
    if ($attendee->checked_in) {
        return array(
            'success' => false,
            'status'  => 'already_checked_in',
            'message' => sprintf(
                __('Already checked in at %s', 'sc_events'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($attendee->checked_in_at))
            ),
            'attendee' => array(
                'id'            => $attendee->id,
                'name'          => $attendee->name,
                'checked_in'    => true,
                'checked_in_at' => $attendee->checked_in_at,
            ),
        );
    }

    // Perform check-in
    $updated = SC_Attendee::update($attendee_id, array(
        'checked_in'    => 1,
        'checked_in_at' => current_time('mysql'),
    ));

    if (!$updated) {
        return new WP_Error(
            'update_failed',
            __('Failed to check in attendee.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Get updated attendee
    $attendee = SC_Attendee::get($attendee_id);

    return array(
        'success'  => true,
        'status'   => 'checked_in',
        'message'  => __('Successfully checked in!', 'sc_events'),
        'attendee' => array(
            'id'            => $attendee->id,
            'name'          => $attendee->name,
            'email'         => $attendee->email,
            'ticket_name'   => $attendee->ticket_name,
            'checked_in'    => true,
            'checked_in_at' => $attendee->checked_in_at,
        ),
    );
}

/**
 * Undo check-in
 */
function sc_scanner_undo_checkin($request) {
    $attendee_id = $request->get_param('attendee_id');

    if (!class_exists('SC_Attendee')) {
        return new WP_Error(
            'system_error',
            __('Attendee system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Get attendee
    $attendee = SC_Attendee::get($attendee_id);

    if (!$attendee) {
        return new WP_Error(
            'not_found',
            __('Attendee not found.', 'sc_events'),
            array('status' => 404)
        );
    }

    // Undo check-in
    $updated = SC_Attendee::update($attendee_id, array(
        'checked_in'    => 0,
        'checked_in_at' => null,
    ));

    if (!$updated) {
        return new WP_Error(
            'update_failed',
            __('Failed to undo check-in.', 'sc_events'),
            array('status' => 500)
        );
    }

    return array(
        'success' => true,
        'status'  => 'unchecked',
        'message' => __('Check-in undone successfully.', 'sc_events'),
        'attendee' => array(
            'id'         => $attendee->id,
            'name'       => $attendee->name,
            'checked_in' => false,
        ),
    );
}

/**
 * Get event attendees
 */
function sc_scanner_get_attendees($request) {
    $event_id = $request->get_param('event_id');
    $search = $request->get_param('search');
    $status = $request->get_param('status');

    if (!class_exists('SC_Attendee')) {
        return new WP_Error(
            'system_error',
            __('Attendee system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Build query args
    $args = array(
        'event_id' => $event_id,
        'status'   => 'active',
    );

    if ($search) {
        $args['search'] = $search;
    }

    if ($status === 'checked_in') {
        $args['checked_in'] = 1;
    } elseif ($status === 'not_checked_in') {
        $args['checked_in'] = 0;
    }

    // Get attendees
    $attendees = SC_Attendee::get_by_event($event_id, $args);

    // Format response
    $formatted = array();
    foreach ($attendees as $attendee) {
        $formatted[] = array(
            'id'             => $attendee->id,
            'name'           => $attendee->name,
            'email'          => $attendee->email,
            'phone'          => $attendee->phone,
            'ticket_code'    => $attendee->ticket_code,
            'ticket_name'    => $attendee->ticket_name,
            'checked_in'     => (bool) $attendee->checked_in,
            'checked_in_at'  => $attendee->checked_in_at,
            'payment_status' => $attendee->payment_status,
        );
    }

    // Get stats
    $total = SC_Attendee::count(array('event_id' => $event_id, 'status' => 'active'));
    $checked_in = SC_Attendee::count(array('event_id' => $event_id, 'status' => 'active', 'checked_in' => 1));

    return array(
        'success'   => true,
        'attendees' => $formatted,
        'stats'     => array(
            'total'      => $total,
            'checked_in' => $checked_in,
            'remaining'  => $total - $checked_in,
        ),
    );
}

/**
 * Get event statistics
 */
function sc_scanner_get_stats($request) {
    $event_id = $request->get_param('event_id');

    if (!class_exists('SC_Attendee')) {
        return new WP_Error(
            'system_error',
            __('Attendee system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    $total = SC_Attendee::count(array('event_id' => $event_id, 'status' => 'active'));
    $checked_in = SC_Attendee::count(array('event_id' => $event_id, 'status' => 'active', 'checked_in' => 1));

    // Get event details
    $event = null;
    if (class_exists('SC_Event')) {
        $event = SC_Event::get($event_id);
    }

    return array(
        'success' => true,
        'event'   => $event ? array(
            'id'         => $event->id,
            'title'      => $event->title,
            'start_date' => $event->start_date,
            'start_time' => $event->start_time,
        ) : null,
        'stats'   => array(
            'total_attendees' => $total,
            'checked_in'      => $checked_in,
            'not_checked_in'  => $total - $checked_in,
            'percentage'      => $total > 0 ? round(($checked_in / $total) * 100, 1) : 0,
        ),
    );
}

/**
 * Get events list for scanner
 */
function sc_scanner_get_events($request) {
    if (!class_exists('SC_Event')) {
        return new WP_Error(
            'system_error',
            __('Event system not available.', 'sc_events'),
            array('status' => 500)
        );
    }

    // Get upcoming and recent events
    $events = SC_Event::get_all(array(
        'status'  => 'publish',
        'orderby' => 'start_date',
        'order'   => 'DESC',
        'limit'   => 50,
    ));

    $formatted = array();
    foreach ($events as $event) {
        // Get attendee counts
        $total = 0;
        $checked_in = 0;
        if (class_exists('SC_Attendee')) {
            $total = SC_Attendee::count(array('event_id' => $event->id, 'status' => 'active'));
            $checked_in = SC_Attendee::count(array('event_id' => $event->id, 'status' => 'active', 'checked_in' => 1));
        }

        $formatted[] = array(
            'id'           => $event->id,
            'title'        => $event->title,
            'start_date'   => $event->start_date,
            'start_time'   => $event->start_time,
            'end_date'     => $event->end_date,
            'location'     => $event->venue_name,
            'attendees'    => array(
                'total'      => $total,
                'checked_in' => $checked_in,
            ),
        );
    }

    return array(
        'success' => true,
        'events'  => $formatted,
    );
}
