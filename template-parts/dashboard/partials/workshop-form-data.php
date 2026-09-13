<?php
/**
 * Data the workshop form needs, loaded before the page header.
 *
 * In: $workshop (object|null). Out: $events, $cert_templates, $tickets, $stats.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$events = SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 1000,
    'orderby' => 'start_date',
    'order'   => 'DESC',
));

// Keep the current parent selectable even when it's cancelled, private or disabled;
// otherwise the select fell back to the first event and saving moved the workshop.
if (!empty($workshop) && !in_array((int) $workshop->event_id, array_map('intval', wp_list_pluck($events, 'id')), true)) {
    $parent = SC_Event::get((int) $workshop->event_id);
    if ($parent) {
        array_unshift($events, $parent);
    }
}

$cert_templates = class_exists('SC_Certificate_Template')
    ? SC_Certificate_Template::get_all(array('is_active' => 1, 'limit' => 200))
    : array();

$tickets = array();
$stats = (object) array('registered' => 0, 'checked_in' => 0, 'revenue' => 0);

if (!empty($workshop)) {
    $tickets = SC_Ticket::get_by_workshop((int) $workshop->id);
    // Live figures: the cached totals on the workshop row drift after deletes and check-ins.
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS registered, COALESCE(SUM(checked_in = 1), 0) AS checked_in, COALESCE(SUM(amount_paid), 0) AS revenue
         FROM {$wpdb->prefix}sc_attendees
         WHERE workshop_id = %d AND status = 'active' AND payment_status = 'success'",
        (int) $workshop->id
    ));
    if ($row) {
        $stats = $row;
    }
}
