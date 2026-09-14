<?php
/**
 * Reports (one event) and Analytics (all events) for template-parts/dashboard/reports.php and analytics.php.
 *
 * Definitions used everywhere:
 * - Registered: event-level rows (not workshop rows) that are active with a successful payment.
 * - Checked in: registered rows with checked_in = 1. Rate = checked in / registered.
 * - Workshop seats: active, paid rows with a workshop_id.
 * - Revenue: amount actually paid on active, paid rows (event + workshops).
 * - Tickets are grouped by the ticket's current name, falling back to the name stored on the
 *   registration (many registrations point at tickets that were deleted and re-created).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/** SQL for "registered" rows, aliased a. */
const SC_REPORT_PEOPLE = "a.status = 'active' AND a.payment_status = 'success' AND (a.workshop_id IS NULL OR a.workshop_id = 0)";

function sc_report_verify() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')), 403);
    }
}

/**
 * Everything the event report shows. Cached for five minutes.
 */
function sc_report_event_data($event_id, $fresh = false) {
    global $wpdb;
    $p = $wpdb->prefix;
    $key = 'sc_report_event_' . (int) $event_id;
    if (!$fresh && ($cached = get_transient($key))) {
        return $cached;
    }

    $event = $wpdb->get_row($wpdb->prepare("SELECT id, title, status, start_date, end_date, total_capacity FROM {$p}sc_events WHERE id = %d", $event_id));
    if (!$event) {
        return null;
    }
    $people = SC_REPORT_PEOPLE;

    $t = $wpdb->get_row($wpdb->prepare(
        "SELECT
            SUM($people) registered,
            SUM($people AND a.checked_in = 1) checked_in,
            SUM(a.status = 'active' AND a.payment_status <> 'success' AND (a.workshop_id IS NULL OR a.workshop_id = 0)) unpaid,
            SUM(a.status <> 'active' AND (a.workshop_id IS NULL OR a.workshop_id = 0)) cancelled,
            SUM(a.status = 'active' AND a.payment_status = 'success' AND a.workshop_id > 0) workshop_seats,
            SUM(a.status = 'active' AND a.payment_status = 'success' AND a.workshop_id > 0 AND a.checked_in = 1) workshop_checked_in,
            COALESCE(SUM(CASE WHEN a.status = 'active' AND a.payment_status = 'success' THEN a.amount_paid END), 0) revenue,
            COALESCE(SUM(CASE WHEN a.status = 'active' AND a.payment_status = 'success' THEN a.coupon_discount END), 0) discounts,
            SUM($people AND a.coupon_code IS NOT NULL AND a.coupon_code <> '') with_coupon,
            COUNT(DISTINCT CASE WHEN $people THEN LOWER(a.email) END) unique_emails,
            MIN(CASE WHEN $people THEN a.created_at END) first_registration,
            MAX(CASE WHEN $people THEN a.created_at END) last_registration
         FROM {$p}sc_attendees a WHERE a.event_id = %d",
        $event_id
    ));

    $tickets = $wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(t.name, NULLIF(a.ticket_name, ''), %s) name, (t.id IS NULL) missing,
                COUNT(*) registered, SUM(a.checked_in = 1) checked_in, COALESCE(SUM(a.amount_paid), 0) revenue
         FROM {$p}sc_attendees a LEFT JOIN {$p}sc_tickets t ON t.id = a.ticket_id
         WHERE a.event_id = %d AND $people
         GROUP BY name, missing ORDER BY registered DESC",
        __('Unknown ticket', 'sc_events'),
        $event_id
    ));
    // Merge the same name coming from live and deleted tickets.
    $by_ticket = array();
    foreach ($tickets as $row) {
        $k = $row->name;
        if (!isset($by_ticket[$k])) {
            $by_ticket[$k] = array('name' => $k, 'registered' => 0, 'checked_in' => 0, 'revenue' => 0.0, 'deleted_ticket' => 0);
        }
        $by_ticket[$k]['registered'] += (int) $row->registered;
        $by_ticket[$k]['checked_in'] += (int) $row->checked_in;
        $by_ticket[$k]['revenue'] += (float) $row->revenue;
        if ((int) $row->missing) {
            $by_ticket[$k]['deleted_ticket'] += (int) $row->registered;
        }
    }

    $daily = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(a.created_at) d, COUNT(*) n FROM {$p}sc_attendees a WHERE a.event_id = %d AND $people GROUP BY d ORDER BY d",
        $event_id
    ));
    $days = array();
    if ($daily) {
        $map = array();
        foreach ($daily as $row) {
            $map[$row->d] = (int) $row->n;
        }
        $start = new DateTime($daily[0]->d);
        $end = new DateTime(end($daily)->d);
        // Fill the quiet days in between, up to about 13 months; beyond that show days with registrations only.
        if ((int) $start->diff($end)->format('%a') <= 400) {
            for ($day = clone $start; $day <= $end; $day->modify('+1 day')) {
                $k = $day->format('Y-m-d');
                $days[] = array('d' => $k, 'n' => $map[$k] ?? 0);
            }
        } else {
            foreach ($map as $k => $n) {
                $days[] = array('d' => $k, 'n' => $n);
            }
        }
    }

    $hours = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(a.checked_in_at) d, HOUR(a.checked_in_at) h, COUNT(*) n
         FROM {$p}sc_attendees a WHERE a.event_id = %d AND $people AND a.checked_in = 1 AND a.checked_in_at IS NOT NULL
         GROUP BY d, h ORDER BY d, h",
        $event_id
    ));
    $checkin_days = array();
    foreach ($hours as $row) {
        $checkin_days[$row->d][(int) $row->h] = (int) $row->n;
    }
    // The busiest seven days (event days), in date order.
    uasort($checkin_days, function ($a, $b) { return array_sum($b) <=> array_sum($a); });
    $checkin_days = array_slice($checkin_days, 0, 7, true);
    ksort($checkin_days);

    $workshops = $wpdb->get_results($wpdb->prepare(
        "SELECT w.id, w.title, w.start_date, w.total_capacity capacity,
                SUM(a.status = 'active' AND a.payment_status = 'success') registered,
                SUM(a.status = 'active' AND a.payment_status = 'success' AND a.checked_in = 1) checked_in
         FROM {$p}sc_workshops w LEFT JOIN {$p}sc_attendees a ON a.workshop_id = w.id
         WHERE w.event_id = %d GROUP BY w.id ORDER BY w.start_date, w.title",
        $event_id
    ));

    $methods = $wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(NULLIF(a.payment_method, ''), 'unknown') method, COUNT(*) n FROM {$p}sc_attendees a WHERE a.event_id = %d AND $people GROUP BY method ORDER BY n DESC",
        $event_id
    ));

    $certs = array('issued' => 0, 'downloaded' => 0, 'revoked' => 0);
    $cert_table = $p . 'sc_certificates';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cert_table))) {
        foreach ($wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) n FROM $cert_table WHERE event_id = %d AND (workshop_id IS NULL OR workshop_id = 0) GROUP BY status", $event_id)) as $row) {
            $certs[$row->status] = (int) $row->n;
        }
    }

    $registered = (int) $t->registered;
    $data = array(
        'event'   => array('id' => (int) $event->id, 'title' => $event->title, 'status' => $event->status, 'start' => $event->start_date, 'end' => $event->end_date, 'capacity' => (int) $event->total_capacity),
        'totals'  => array(
            'registered'          => $registered,
            'checked_in'          => (int) $t->checked_in,
            'checkin_rate'        => $registered ? round((int) $t->checked_in / $registered * 100, 1) : 0,
            'unpaid'              => (int) $t->unpaid,
            'cancelled'           => (int) $t->cancelled,
            'duplicates'          => max(0, $registered - (int) $t->unique_emails),
            'workshop_seats'      => (int) $t->workshop_seats,
            'workshop_checked_in' => (int) $t->workshop_checked_in,
            'revenue'             => round((float) $t->revenue, 2),
            'discounts'           => round((float) $t->discounts, 2),
            'with_coupon'         => (int) $t->with_coupon,
            'first_registration'  => $t->first_registration,
            'last_registration'   => $t->last_registration,
            // Issued certificates still valid (a downloaded one was issued first).
            'certificates'        => $certs['issued'] + $certs['downloaded'],
            'certificates_downloaded' => $certs['downloaded'],
        ),
        'tickets'      => array_values($by_ticket),
        'days'         => $days,
        'checkin_days' => $checkin_days,
        'workshops'    => array_map(function ($w) {
            return array('id' => (int) $w->id, 'title' => $w->title, 'start' => $w->start_date, 'capacity' => (int) $w->capacity, 'registered' => (int) $w->registered, 'checked_in' => (int) $w->checked_in);
        }, $workshops),
        'methods'      => array_map(function ($m) { return array('method' => $m->method, 'n' => (int) $m->n); }, $methods),
        'generated'    => current_time('mysql'),
        'timezone'     => wp_timezone_string(),
    );
    set_transient($key, $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}

add_action('wp_ajax_sc_report_event', 'sc_report_event');
function sc_report_event() {
    sc_report_verify();
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $data = $event_id ? sc_report_event_data($event_id, !empty($_POST['fresh'])) : null;
    if (!$data) {
        wp_send_json_error(array('message' => __('Choose an event.', 'sc_events')));
    }
    wp_send_json_success($data);
}

/**
 * Every event side by side, plus registrations per month. Cached for five minutes.
 */
function sc_report_overview_data($fresh = false) {
    global $wpdb;
    $p = $wpdb->prefix;
    if (!$fresh && ($cached = get_transient('sc_report_overview'))) {
        return $cached;
    }
    $people = SC_REPORT_PEOPLE;
    $cert_table = $p . 'sc_certificates';
    $has_certs = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cert_table));

    $events = $wpdb->get_results(
        "SELECT e.id, e.title, e.status, e.start_date, e.end_date,
                COALESCE(s.registered, 0) registered, COALESCE(s.checked_in, 0) checked_in,
                COALESCE(s.workshop_seats, 0) workshop_seats, COALESCE(s.revenue, 0) revenue
         FROM {$p}sc_events e
         LEFT JOIN (
            SELECT a.event_id,
                   SUM($people) registered,
                   SUM($people AND a.checked_in = 1) checked_in,
                   SUM(a.status = 'active' AND a.payment_status = 'success' AND a.workshop_id > 0) workshop_seats,
                   SUM(CASE WHEN a.status = 'active' AND a.payment_status = 'success' THEN a.amount_paid ELSE 0 END) revenue
            FROM {$p}sc_attendees a GROUP BY a.event_id
         ) s ON s.event_id = e.id
         WHERE e.status IN ('publish', 'completed', 'draft')
         ORDER BY e.start_date DESC"
    );
    $certs = array();
    if ($has_certs) {
        foreach ($wpdb->get_results("SELECT event_id, SUM(status IN ('issued', 'downloaded')) valid, SUM(status = 'downloaded') downloaded FROM $cert_table GROUP BY event_id") as $row) {
            $certs[(int) $row->event_id] = array((int) $row->valid, (int) $row->downloaded);
        }
    }
    $months = $wpdb->get_results("SELECT DATE_FORMAT(a.created_at, '%Y-%m') m, COUNT(*) n FROM {$p}sc_attendees a WHERE $people GROUP BY m ORDER BY m");

    $rows = array_map(function ($e) use ($certs) {
        $registered = (int) $e->registered;
        return array(
            'id' => (int) $e->id, 'title' => $e->title, 'status' => $e->status, 'start' => $e->start_date, 'end' => $e->end_date,
            'registered' => $registered, 'checked_in' => (int) $e->checked_in,
            'checkin_rate' => $registered ? round((int) $e->checked_in / $registered * 100, 1) : null,
            'workshop_seats' => (int) $e->workshop_seats, 'revenue' => round((float) $e->revenue, 2),
            'certificates' => $certs[(int) $e->id][0] ?? 0, 'certificates_downloaded' => $certs[(int) $e->id][1] ?? 0,
        );
    }, $events);

    // Months in a row, empty ones included, from the first registration to now.
    $series = array();
    if ($months) {
        $map = array();
        foreach ($months as $m) {
            $map[$m->m] = (int) $m->n;
        }
        $cursor = new DateTime($months[0]->m . '-01');
        $stop = new DateTime(max(end($months)->m, current_time('Y-m')) . '-01');
        for (; $cursor <= $stop; $cursor->modify('+1 month')) {
            $k = $cursor->format('Y-m');
            $series[] = array('m' => $k, 'n' => $map[$k] ?? 0);
        }
    }

    $data = array('events' => $rows, 'months' => $series, 'generated' => current_time('mysql'));
    set_transient('sc_report_overview', $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}

add_action('wp_ajax_sc_report_overview', 'sc_report_overview');
function sc_report_overview() {
    sc_report_verify();
    wp_send_json_success(sc_report_overview_data(!empty($_POST['fresh'])));
}

/**
 * CSV of the event report (summary, tickets, days, workshops). GET, so the browser downloads it.
 */
add_action('wp_ajax_sc_report_export', 'sc_report_export');
function sc_report_export() {
    sc_report_verify();
    $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
    $data = $event_id ? sc_report_event_data($event_id, true) : null;
    if (!$data) {
        wp_die(esc_html__('Choose an event.', 'sc_events'));
    }
    $cell = function ($v) {
        $v = (string) $v;
        // Spreadsheet formulas in names (=, +, -, @) must not run.
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-' . sanitize_title($data['event']['title']) . '-' . current_time('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $t = $data['totals'];
    fputcsv($out, array(__('Event', 'sc_events'), $cell($data['event']['title'])));
    fputcsv($out, array(__('Generated', 'sc_events'), $data['generated'] . ' (' . $data['timezone'] . ')'));
    fputcsv($out, array());
    foreach (array(
        __('Registered', 'sc_events')          => $t['registered'],
        __('Checked in', 'sc_events')          => $t['checked_in'],
        __('Check-in rate %', 'sc_events')     => $t['checkin_rate'],
        __('Awaiting payment', 'sc_events')    => $t['unpaid'],
        __('Cancelled', 'sc_events')           => $t['cancelled'],
        __('Workshop seats', 'sc_events')      => $t['workshop_seats'],
        __('Workshop check-ins', 'sc_events')  => $t['workshop_checked_in'],
        __('Registered with a coupon', 'sc_events') => $t['with_coupon'],
        __('Revenue', 'sc_events')             => $t['revenue'],
        __('Certificates issued', 'sc_events') => $t['certificates'],
    ) as $label => $value) {
        fputcsv($out, array($label, $value));
    }
    fputcsv($out, array());
    fputcsv($out, array(__('Ticket', 'sc_events'), __('Registered', 'sc_events'), __('Checked in', 'sc_events'), __('Revenue', 'sc_events')));
    foreach ($data['tickets'] as $row) {
        fputcsv($out, array($cell($row['name']), $row['registered'], $row['checked_in'], $row['revenue']));
    }
    if ($data['workshops']) {
        fputcsv($out, array());
        fputcsv($out, array(__('Workshop', 'sc_events'), __('Seats', 'sc_events'), __('Registered', 'sc_events'), __('Checked in', 'sc_events')));
        foreach ($data['workshops'] as $row) {
            fputcsv($out, array($cell($row['title']), $row['capacity'], $row['registered'], $row['checked_in']));
        }
    }
    fputcsv($out, array());
    fputcsv($out, array(__('Date', 'sc_events'), __('Registrations', 'sc_events')));
    foreach ($data['days'] as $row) {
        fputcsv($out, array($row['d'], $row['n']));
    }
    fclose($out);
    exit;
}
