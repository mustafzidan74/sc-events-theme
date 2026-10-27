<?php
/**
 * Which certificate a ticket gets: its workshop's for a workshop ticket, else its event's,
 * with that scope's rules and template. Loaded whether or not the certificates module is
 * on, since My Account, the claim handler and the API depend on it.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve "event:5" / "workshop:12" to the scope, its rules and its template.
 *
 * @return array|null
 */
function sc_certs_scope($raw) {
    global $wpdb;
    $p = $wpdb->prefix;
    if (!preg_match('/^(event|workshop):(\d+)$/', (string) $raw, $m)) {
        return null;
    }
    $today = current_time('Y-m-d');
    if ($m[1] === 'event') {
        $e = $wpdb->get_row($wpdb->prepare("SELECT id, title, start_date, end_date, enable_certificates, certificate_template_id, certificate_require_checkin, certificate_require_checkout, certificate_require_event_ended FROM {$p}sc_events WHERE id = %d", (int) $m[2]));
        if (!$e) {
            return null;
        }
        $end = $e->end_date ?: $e->start_date;
        return array(
            'key' => 'event:' . $e->id, 'type' => 'event', 'event_id' => (int) $e->id, 'workshop_id' => 0,
            'event_title' => $e->title, 'title' => $e->title, 'event_date' => $e->start_date,
            'enabled' => (bool) $e->enable_certificates, 'template_id' => (int) $e->certificate_template_id,
            'require_checkin' => (bool) $e->certificate_require_checkin, 'require_checkout' => (bool) $e->certificate_require_checkout,
            'require_ended' => (bool) $e->certificate_require_event_ended, 'ended' => substr((string) $end, 0, 10) < $today, 'end_date' => $end,
        );
    }
    $w = $wpdb->get_row($wpdb->prepare("SELECT w.id, w.title, w.event_id, w.start_date, w.end_date, w.enable_certificates, w.certificate_template_id, w.certificate_require_checkin, e.title AS event_title, e.certificate_template_id AS event_template_id FROM {$p}sc_workshops w JOIN {$p}sc_events e ON e.id = w.event_id WHERE w.id = %d", (int) $m[2]));
    if (!$w) {
        return null;
    }
    $end = $w->end_date ?: $w->start_date;
    return array(
        'key' => 'workshop:' . $w->id, 'type' => 'workshop', 'event_id' => (int) $w->event_id, 'workshop_id' => (int) $w->id,
        'event_title' => $w->event_title, 'title' => $w->title, 'event_date' => $w->start_date,
        'enabled' => (bool) $w->enable_certificates, 'template_id' => (int) $w->certificate_template_id ?: (int) $w->event_template_id,
        'require_checkin' => (bool) $w->certificate_require_checkin, 'require_checkout' => false,
        'require_ended' => false, 'ended' => substr((string) $end, 0, 10) < $today, 'end_date' => $end,
    );
}

/**
 * The scope a ticket's certificate comes from: its workshop for a workshop ticket, else its event.
 *
 * @return array|null
 */
function sc_certs_scope_for_ticket($attendee) {
    $workshop_id = (int) ($attendee->workshop_id ?? 0);
    return sc_certs_scope($workshop_id ? 'workshop:' . $workshop_id : 'event:' . (int) $attendee->event_id);
}

/**
 * Why a ticket can't get its certificate yet, under its scope's rules.
 *
 * @return string '' when it can, else 'disabled', 'checkin', 'checkout' or 'not_ended'.
 */
function sc_certs_ticket_problem($attendee, $scope) {
    global $wpdb;
    if (!$scope || !$scope['enabled']) {
        return 'disabled';
    }
    if ($scope['require_checkin'] && empty($attendee->checked_in)) {
        return 'checkin';
    }
    if ($scope['require_checkout'] && !$wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$wpdb->prefix}sc_checkins WHERE attendee_id = %d AND action IN ('checkout', 'manual_checkout', 'check_out') LIMIT 1",
        (int) $attendee->id
    ))) {
        return 'checkout';
    }
    if ($scope['require_ended'] && !$scope['ended']) {
        return 'not_ended';
    }
    return '';
}

/**
 * Issue a ticket's certificate on its scope's template (the default template when the scope has none).
 * Sends nothing; the caller decides whether to notify.
 *
 * @return int|WP_Error Certificate ID
 */
function sc_certs_issue_for_ticket($attendee, $scope, $issued_by = 0) {
    $template_id = (int) $scope['template_id'];
    if (!$template_id && class_exists('SC_Certificate_Template')) {
        $default = SC_Certificate_Template::get_default();
        $template_id = $default ? (int) (is_object($default) ? $default->id : ($default['id'] ?? 0)) : 0;
    }
    if (!$template_id) {
        return new WP_Error('no_template', __('No certificate template configured.', 'sc_events'));
    }
    return SC_Certificate::issue(array(
        'template_id'   => $template_id,
        'attendee_id'   => (int) $attendee->id,
        'event_id'      => (int) $attendee->event_id, // the ticket's own event, which the lookups use
        'workshop_id'   => $scope['workshop_id'] ?: null,
        'attendee_name' => $attendee->name,
        'event_title'   => $scope['event_title'],
        'event_date'    => substr((string) $scope['event_date'], 0, 10),
        'issued_by'     => (int) $issued_by,
    ));
}
