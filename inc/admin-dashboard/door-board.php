<?php
/**
 * Door board — the entrance on one screen while the doors are open
 * (template-parts/dashboard/door-board.php).
 *
 * How many people are in, how fast they are arriving, what each door is
 * doing and which door has gone quiet. Built from the check-in log, so scans
 * made offline count as soon as their phone sends them, at the time they
 * happened. Event managers only; a board open on several screens is served
 * from a three-second cache.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Arrivals are grouped in slots of this many minutes. */
const SC_DOOR_BOARD_SLOT = 5;

/** A door that scanned in the last hour and has been silent this long is flagged. */
const SC_DOOR_BOARD_QUIET_MINUTES = 10;

/** The event the board opens on: the featured event, else the one on now or next. */
function sc_door_board_default_event() {
    global $wpdb;
    $featured = (int) get_option('sc_featured_event_id', 0);
    if ($featured && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $featured))) {
        return $featured;
    }
    $today = current_time('Y-m-d');
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') AND COALESCE(end_date, start_date) >= %s ORDER BY start_date ASC LIMIT 1",
        $today
    ));
    if (!$id) {
        $id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') ORDER BY start_date DESC LIMIT 1");
    }
    return (int) $id;
}

/** The days of an event, and the one to open on: today while it runs, else its first day. */
function sc_door_board_days($event) {
    $start = $event->start_date ?: current_time('Y-m-d');
    $end   = $event->end_date && $event->end_date >= $start ? $event->end_date : $start;
    $days  = array();
    for ($d = strtotime($start), $last = strtotime($end); $d <= $last && count($days) < 14; $d = strtotime('+1 day', $d)) {
        $days[] = gmdate('Y-m-d', $d);
    }
    $today = current_time('Y-m-d');
    return array('days' => $days, 'default' => in_array($today, $days, true) ? $today : $days[0]);
}

/**
 * Everything the board shows for one event and day.
 *
 * @return array|WP_Error
 */
function sc_door_board_data($event_id, $day) {
    global $wpdb;
    $p = $wpdb->prefix;
    $event = $wpdb->get_row($wpdb->prepare("SELECT id, title, start_date, end_date, attendance_tracking FROM {$p}sc_events WHERE id = %d", $event_id));
    if (!$event) {
        return new WP_Error('event', __('Event not found.', 'sc_events'));
    }
    $days = sc_door_board_days($event);
    if (!in_array($day, $days['days'], true)) {
        $day = $days['default'];
    }

    $key = 'sc_door_board_' . $event_id . '_' . $day;
    $cached = get_transient($key);
    if ($cached !== false) {
        return $cached;
    }

    $from     = $day . ' 00:00:00';
    $to       = gmdate('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00';
    $now_ts   = current_time('timestamp');
    $now      = current_time('mysql');
    $is_today = $day === current_time('Y-m-d');
    $tracking = (int) $event->attendance_tracking === 1;
    $in       = "('checkin', 'manual_checkin')";

    // Tickets for the event entrance: active, and paid unless free.
    $registered = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}sc_attendees
         WHERE event_id = %d AND workshop_id IS NULL AND status = 'active' AND (payment_status = 'success' OR ticket_price <= 0)",
        $event_id
    ));

    // Each person's first entry of the day, and whether their last scan left them inside.
    $people = $wpdb->get_results($wpdb->prepare(
        "SELECT attendee_id,
                MIN(CASE WHEN action IN $in THEN created_at END) AS first_in,
                SUBSTRING_INDEX(GROUP_CONCAT(action ORDER BY created_at, id SEPARATOR ','), ',', -1) AS last_action
         FROM {$p}sc_checkins
         WHERE event_id = %d AND workshop_id IS NULL AND created_at >= %s AND created_at < %s
         GROUP BY attendee_id",
        $event_id, $from, $to
    ));
    $arrived = 0;
    $inside = 0;
    $firsts = array();
    foreach ($people as $row) {
        if ($row->first_in) {
            $arrived++;
            $firsts[] = strtotime($row->first_in);
        }
        if (in_array($row->last_action, array('checkin', 'manual_checkin'), true)) {
            $inside++;
        }
    }
    sort($firsts);

    // Arrivals per slot, from the first arrival (or 08:00) to now — at most the last six hours.
    $slot = SC_DOOR_BOARD_SLOT * 60;
    $end = $is_today ? $now_ts : ($firsts ? end($firsts) : strtotime($day . ' 18:00:00'));
    $start = $firsts ? $firsts[0] : strtotime($day . ' 08:00:00');
    $start = max($start, $end - 6 * HOUR_IN_SECONDS);
    $start -= $start % $slot;
    $end = max($end, $start + $slot);
    $bars = array();
    for ($t = $start; $t < $end; $t += $slot) {
        $bars[$t] = 0;
    }
    foreach ($firsts as $t) {
        $b = $t - $t % $slot;
        if (isset($bars[$b])) {
            $bars[$b]++;
        }
    }
    $series = array();
    foreach ($bars as $t => $n) {
        $series[] = array('at' => gmdate('H:i', $t), 'n' => $n);
    }

    $last10 = 0;
    foreach ($firsts as $t) {
        if ($t >= $now_ts - 600) {
            $last10++;
        }
    }
    $peak = $bars ? max($bars) : 0;

    // Doors: one per scanning account, since that is how the teams are set up.
    $doors = array();
    $door_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT scanned_by, COUNT(*) AS scans,
                SUM(created_at >= %s) AS recent,
                SUM(scan_method = 'offline') AS offline,
                MAX(created_at) AS last_at
         FROM {$p}sc_checkins
         WHERE event_id = %d AND created_at >= %s AND created_at < %s
         GROUP BY scanned_by
         ORDER BY scans DESC",
        date('Y-m-d H:i:s', $now_ts - 15 * MINUTE_IN_SECONDS), $event_id, $from, $to
    ));
    $quiet = array();
    foreach ($door_rows as $d) {
        $user = $d->scanned_by ? get_userdata((int) $d->scanned_by) : null;
        $silent = (int) floor(($now_ts - strtotime($d->last_at)) / 60);
        $is_quiet = $is_today && $silent >= SC_DOOR_BOARD_QUIET_MINUTES && $silent <= 60;
        $doors[] = array(
            'name'    => $user ? $user->display_name : __('Unknown account', 'sc_events'),
            'scans'   => (int) $d->scans,
            'recent'  => (int) $d->recent,
            'offline' => (int) $d->offline,
            'last'    => mysql2date('H:i', $d->last_at),
            'silent'  => $is_today ? $silent : null,
            'quiet'   => $is_quiet,
        );
        if ($is_quiet) {
            $quiet[] = $user ? $user->display_name : __('Unknown account', 'sc_events');
        }
    }

    // Workshops running that day.
    $workshops = array();
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT w.id, w.title, w.start_time,
                (SELECT COUNT(*) FROM {$p}sc_attendees a WHERE a.workshop_id = w.id AND a.status = 'active') AS registered,
                (SELECT COUNT(DISTINCT k.attendee_id) FROM {$p}sc_checkins k WHERE k.workshop_id = w.id AND k.action IN $in AND k.created_at >= %s AND k.created_at < %s) AS arrived
         FROM {$p}sc_workshops w
         WHERE w.event_id = %d AND w.status <> 'cancelled' AND (w.start_date = %s OR w.start_date IS NULL)
         ORDER BY w.start_time, w.title",
        $from, $to, $event_id, $day
    )) as $w) {
        $workshops[] = array(
            'title'      => $w->title,
            'time'       => $w->start_time ? mysql2date('H:i', '2000-01-01 ' . $w->start_time) : '',
            'registered' => (int) $w->registered,
            'arrived'    => (int) $w->arrived,
        );
    }

    // Exhibitors.
    $companies = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS total, SUM(checked_in = 1) AS arrived FROM {$p}sc_company_attendees WHERE event_id = %d AND status = 'active'",
        $event_id
    ));

    // The latest scans.
    $feed = array();
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT k.action, k.scan_method, k.scanned_by, k.created_at, k.workshop_id, a.name, a.ticket_name
         FROM {$p}sc_checkins k
         LEFT JOIN {$p}sc_attendees a ON a.id = k.attendee_id
         WHERE k.event_id = %d AND k.created_at >= %s AND k.created_at < %s
         ORDER BY k.created_at DESC, k.id DESC
         LIMIT 12",
        $event_id, $from, $to
    )) as $f) {
        $user = $f->scanned_by ? get_userdata((int) $f->scanned_by) : null;
        $feed[] = array(
            'at'       => mysql2date('H:i', $f->created_at),
            'name'     => (string) $f->name,
            'ticket'   => (string) $f->ticket_name,
            'out'      => in_array($f->action, array('checkout', 'manual_checkout'), true),
            'offline'  => $f->scan_method === 'offline',
            'workshop' => !empty($f->workshop_id),
            'door'     => $user ? $user->display_name : '',
        );
    }

    $data = array(
        'event'      => array('id' => (int) $event->id, 'title' => $event->title, 'tracking' => $tracking),
        'day'        => $day,
        'days'       => $days['days'],
        'is_today'   => $is_today,
        'registered' => $registered,
        'arrived'    => $arrived,
        'inside'     => $inside,
        'to_come'    => max(0, $registered - $arrived),
        'share'      => $registered ? round($arrived / $registered * 100) : 0,
        'per_minute' => round($last10 / 10, 1),
        'last10'     => $last10,
        'peak'       => $peak,
        'slot'       => SC_DOOR_BOARD_SLOT,
        'series'     => $series,
        'doors'      => $doors,
        'quiet'      => $quiet,
        'workshops'  => $workshops,
        'companies'  => array('total' => (int) ($companies->total ?? 0), 'arrived' => (int) ($companies->arrived ?? 0)),
        'feed'       => $feed,
        'updated'    => mysql2date('H:i:s', $now),
    );
    set_transient($key, $data, 3);
    return $data;
}

add_action('wp_ajax_sc_door_board', 'sc_door_board_ajax');
function sc_door_board_ajax() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('code' => 'nonce', 'message' => __('This page has expired. Refresh it.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $day = isset($_POST['day']) ? sanitize_text_field(wp_unslash($_POST['day'])) : '';
    $data = sc_door_board_data($event_id ?: sc_door_board_default_event(), $day);
    if (is_wp_error($data)) {
        wp_send_json_error(array('message' => $data->get_error_message()));
    }
    wp_send_json_success($data);
}
