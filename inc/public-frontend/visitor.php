<?php
/**
 * What the signed-in visitor already has, so the public pages can speak to them differently:
 * somebody holding an IDC ticket should be offered their QR, not a Register button.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The visitor's own registration for an event — the main one, not a workshop seat.
 *
 * Matched on the account and on the email, because tickets bought before the account existed
 * carry the email only.
 *
 * @return object|null id, ticket_code, ticket_name.
 */
function sc_visitor_event_ticket($event_id) {
    static $cache = array();
    $event_id = (int) $event_id;
    if (!$event_id || !is_user_logged_in()) {
        return null;
    }
    if (array_key_exists($event_id, $cache)) {
        return $cache[$event_id];
    }
    global $wpdb;
    $user = wp_get_current_user();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, ticket_code, ticket_name FROM {$wpdb->prefix}sc_attendees
         WHERE event_id = %d AND status = 'active' AND payment_status IN ('success', 'completed')
           AND (workshop_id IS NULL OR workshop_id = 0)
           AND (user_id = %d OR email = %s)
         ORDER BY id DESC LIMIT 1",
        $event_id, (int) $user->ID, $user->user_email
    ));
    $cache[$event_id] = $row ?: null;
    return $cache[$event_id];
}

/** The page that shows a ticket and its QR. */
function sc_ticket_view_url($ticket) {
    if (!$ticket || empty($ticket->ticket_code)) {
        return home_url('/my-account/');
    }
    return home_url('/ticket-view/?attendee_id=' . (int) $ticket->id . '&ticket_code=' . rawurlencode($ticket->ticket_code));
}

/** Sign-in link that returns the visitor to where they were. */
function sc_login_url($redirect = '') {
    $redirect = $redirect ?: home_url('/my-account/');
    return add_query_arg('redirect', rawurlencode($redirect), home_url('/login/'));
}
