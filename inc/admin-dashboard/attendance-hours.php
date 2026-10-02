<?php
/**
 * Attendance hours — credit hours earned at the congress entrance, for the certificate.
 *
 * The day is cut into clock hours (09:00–10:00, 10:00–11:00, … up to the day's end). A scan at the
 * congress entrance counts that whole hour; everyone is timed out when the next hour starts, so
 * people scan again each hour. A second scan in an hour already counted is not counted again, and
 * there is no manual time-out. A scan before the day starts counts the first hour; a scan after
 * it ends counts nothing. Workshop doors never count.
 *
 * Credits (e.g. Wisdom Education 24, Health Council 17) are worked out from the hours counted:
 *   credit = round(hours × credit max ÷ all hours of the congress), at most the max,
 *   and at least the minimum for anyone with one counted hour or more.
 *
 * Settings per event in the option sc_hours_{event_id}; page: template-parts/dashboard/attendance-hours.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_hours_defaults() {
    return array(
        'enabled'   => 0,
        'day_start' => 9,
        'day_end'   => 17,
        'credits'   => array(
            array('label' => 'Wisdom Education', 'max' => 24, 'min' => 6),
            array('label' => 'Health Council', 'max' => 17, 'min' => 4),
        ),
    );
}

function sc_hours_settings($event_id) {
    $saved = get_option('sc_hours_' . (int) $event_id, array());
    $s = wp_parse_args(is_array($saved) ? $saved : array(), sc_hours_defaults());
    $s['enabled']   = (int) !empty($s['enabled']);
    $s['day_start'] = max(0, min(23, (int) $s['day_start']));
    $s['day_end']   = max($s['day_start'] + 1, min(24, (int) $s['day_end']));
    $credits = array();
    foreach ((array) $s['credits'] as $c) {
        $label = trim((string) ($c['label'] ?? ''));
        $max   = (float) ($c['max'] ?? 0);
        if ($label !== '' && $max > 0) {
            $credits[] = array('label' => $label, 'max' => $max, 'min' => max(0, (float) ($c['min'] ?? 0)));
        }
    }
    $s['credits'] = $credits;
    return $s;
}

function sc_hours_enabled($event_id) {
    return (bool) sc_hours_settings($event_id)['enabled'];
}

/** The days of the event, Y-m-d. */
function sc_hours_event_days($event_id) {
    global $wpdb;
    $e = $wpdb->get_row($wpdb->prepare("SELECT start_date, end_date FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id));
    if (!$e || !$e->start_date) {
        return array();
    }
    $end = $e->end_date && $e->end_date >= $e->start_date ? $e->end_date : $e->start_date;
    $days = array();
    for ($d = strtotime($e->start_date); $d <= strtotime($end) && count($days) < 31; $d = strtotime('+1 day', $d)) {
        $days[] = gmdate('Y-m-d', $d);
    }
    return $days;
}

/** All the hours the congress has: days × hours a day. */
function sc_hours_total($event_id) {
    $s = sc_hours_settings($event_id);
    return count(sc_hours_event_days($event_id)) * ($s['day_end'] - $s['day_start']);
}

/**
 * The hour a scan counts for (its starting hour, e.g. 10 for 10:00–11:00), or null outside the day.
 *
 * @param string $local Site time, 'Y-m-d H:i:s'.
 */
function sc_hours_slot($local, $s) {
    $h = (int) substr((string) $local, 11, 2);
    if ($h >= $s['day_end']) {
        return null;
    }
    return max($h, $s['day_start']);
}

function sc_hours_label($slot) {
    return sprintf('%02d:00–%02d:00', $slot, $slot + 1);
}

/** Credits for a number of counted hours: [ ['label' => …, 'value' => int], … ]. */
function sc_hours_credits($hours, $event_id) {
    $s = sc_hours_settings($event_id);
    $total = max(1, sc_hours_total($event_id));
    $out = array();
    foreach ($s['credits'] as $c) {
        $v = $hours > 0 ? min($c['max'], round($hours * $c['max'] / $total)) : 0;
        if ($hours > 0) {
            $v = max($v, $c['min']);
        }
        $out[] = array('label' => $c['label'], 'value' => $v + 0);
    }
    return $out;
}

/** Hours counted per attendee: [attendee_id => [day => [slot, …]]]. Congress entrance only. */
function sc_hours_counted_slots($event_id, $attendee_id = 0) {
    global $wpdb;
    $s = sc_hours_settings($event_id);
    $days = sc_hours_event_days($event_id);
    if (!$days) {
        return array();
    }
    $sql = "SELECT k.attendee_id, k.created_at FROM {$wpdb->prefix}sc_checkins k
            WHERE k.event_id = %d AND (k.workshop_id IS NULL OR k.workshop_id = 0)
              AND k.action IN ('checkin', 'manual_checkin')
              AND k.created_at >= %s AND k.created_at < %s";
    $args = array($event_id, $days[0] . ' 00:00:00', gmdate('Y-m-d', strtotime(end($days) . ' +1 day')) . ' 00:00:00');
    if ($attendee_id) {
        $sql .= ' AND k.attendee_id = %d';
        $args[] = $attendee_id;
    }
    $out = array();
    foreach ($wpdb->get_results($wpdb->prepare($sql, $args)) as $row) {
        $slot = sc_hours_slot($row->created_at, $s);
        if ($slot === null) {
            continue;
        }
        $out[(int) $row->attendee_id][substr($row->created_at, 0, 10)][$slot] = $slot;
    }
    return $out;
}

/** Number of hours in a [day => slots] map. */
function sc_hours_count($by_day) {
    $n = 0;
    foreach ((array) $by_day as $slots) {
        $n += count($slots);
    }
    return $n;
}

/** Hours and credits of one attendee, for the scanner and the certificate. */
function sc_hours_for_attendee($attendee_id, $event_id) {
    $map = sc_hours_counted_slots($event_id, $attendee_id);
    $by_day = $map[$attendee_id] ?? array();
    $hours = sc_hours_count($by_day);
    return array('hours' => $hours, 'by_day' => $by_day, 'credits' => sc_hours_credits($hours, $event_id));
}

/**
 * The scanner's answer about the hour, used by sc_scanner_record_attendee_scan().
 *
 * @return array state (counted | already | outside), label, next ('11:00'), today, total.
 */
function sc_hours_scan_state($attendee_id, $event_id, $local) {
    $s = sc_hours_settings($event_id);
    $slot = sc_hours_slot($local, $s);
    if ($slot === null) {
        return array('state' => 'outside', 'label' => sprintf('%02d:00–%02d:00', $s['day_start'], $s['day_end']), 'next' => '', 'slot' => null);
    }
    // Today's entrance scans, whatever the event's dates (a rehearsal day dedupes the same way).
    global $wpdb;
    $day = substr($local, 0, 10);
    $times = $wpdb->get_col($wpdb->prepare(
        "SELECT created_at FROM {$wpdb->prefix}sc_checkins
         WHERE attendee_id = %d AND event_id = %d AND (workshop_id IS NULL OR workshop_id = 0)
           AND action IN ('checkin', 'manual_checkin') AND created_at >= %s AND created_at < %s",
        $attendee_id, $event_id, $day . ' 00:00:00', gmdate('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00'
    ));
    $counted = false;
    foreach ($times as $t) {
        if (sc_hours_slot($t, $s) === $slot) {
            $counted = true;
            break;
        }
    }
    return array(
        'state' => $counted ? 'already' : 'counted',
        'label' => sc_hours_label($slot),
        'next'  => sprintf('%02d:00', $slot + 1),
        'slot'  => $slot,
    );
}

/* ==========================================================================
   Dashboard: settings and the hours list
   ========================================================================== */

function sc_hours_guard() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('This page has expired. Refresh it.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
}

add_action('wp_ajax_sc_hours_save', 'sc_hours_save');
function sc_hours_save() {
    sc_hours_guard();
    $event_id = absint($_POST['event_id'] ?? 0);
    if (!$event_id) {
        wp_send_json_error(array('message' => __('Choose the event.', 'sc_events')));
    }
    $start = absint($_POST['day_start'] ?? 9);
    $end = absint($_POST['day_end'] ?? 17);
    if ($end <= $start || $end > 24) {
        wp_send_json_error(array('message' => __('The day must end after it starts.', 'sc_events')));
    }
    $credits = array();
    $posted = isset($_POST['credits']) && is_array($_POST['credits']) ? wp_unslash($_POST['credits']) : array();
    foreach ($posted as $c) {
        $label = sanitize_text_field((string) ($c['label'] ?? ''));
        $max = (float) ($c['max'] ?? 0);
        if ($label === '' || $max <= 0) {
            continue;
        }
        $credits[] = array('label' => $label, 'max' => $max, 'min' => max(0, (float) ($c['min'] ?? 0)));
    }
    update_option('sc_hours_' . $event_id, array(
        'enabled'   => !empty($_POST['enabled']) ? 1 : 0,
        'day_start' => $start,
        'day_end'   => $end,
        'credits'   => $credits,
    ), false);
    wp_send_json_success(array('message' => __('Saved.', 'sc_events'), 'settings' => sc_hours_settings($event_id), 'total' => sc_hours_total($event_id)));
}

/** Everyone with a congress ticket and their hours. */
function sc_hours_rows($event_id) {
    global $wpdb;
    $people = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, email, phone, ticket_code FROM {$wpdb->prefix}sc_attendees
         WHERE event_id = %d AND (workshop_id IS NULL OR workshop_id = 0) AND status = 'active'
         ORDER BY name",
        $event_id
    ));
    $slots = sc_hours_counted_slots($event_id);
    $days = sc_hours_event_days($event_id);
    $rows = array();
    foreach ($people as $p) {
        $by_day = $slots[(int) $p->id] ?? array();
        $per_day = array();
        foreach ($days as $d) {
            $per_day[] = isset($by_day[$d]) ? count($by_day[$d]) : 0;
        }
        $hours = array_sum($per_day);
        $rows[] = array(
            'id'      => (int) $p->id,
            'name'    => (string) $p->name,
            'email'   => (string) $p->email,
            'phone'   => (string) $p->phone,
            'code'    => (string) $p->ticket_code,
            'days'    => $per_day,
            'hours'   => $hours,
            'credits' => array_column(sc_hours_credits($hours, $event_id), 'value'),
        );
    }
    return $rows;
}

add_action('wp_ajax_sc_hours_list', 'sc_hours_list');
function sc_hours_list() {
    sc_hours_guard();
    $event_id = absint($_POST['event_id'] ?? 0);
    $s = sc_hours_settings($event_id);
    wp_send_json_success(array(
        'days'    => sc_hours_event_days($event_id),
        'credits' => array_column($s['credits'], 'label'),
        'rows'    => sc_hours_rows($event_id),
    ));
}

add_action('wp_ajax_sc_hours_export', 'sc_hours_export');
function sc_hours_export() {
    sc_hours_guard();
    $event_id = absint($_GET['event_id'] ?? 0);
    $s = sc_hours_settings($event_id);
    $days = sc_hours_event_days($event_id);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-hours-' . $event_id . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    // Cells starting with = + - @ would run as formulas in a spreadsheet.
    $cell = static function ($v) {
        $v = (string) $v;
        if (preg_match('/^\+[\d\s]+$/', $v)) {
            return $v; // a phone number, not a formula
        }
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    $head = array('Name', 'Email', 'Phone', 'Ticket code');
    foreach ($days as $d) {
        $head[] = mysql2date('j M', $d);
    }
    $head[] = 'Hours';
    foreach ($s['credits'] as $c) {
        $head[] = $c['label'];
    }
    fputcsv($out, $head);
    foreach (sc_hours_rows($event_id) as $r) {
        fputcsv($out, array_merge(array($cell($r['name']), $cell($r['email']), $cell($r['phone']), $cell($r['code'])), $r['days'], array($r['hours']), $r['credits']));
    }
    fclose($out);
    exit;
}
