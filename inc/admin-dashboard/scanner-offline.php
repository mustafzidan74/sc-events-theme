<?php
/**
 * The scanner keeps working when the venue's connection drops.
 *
 * Before the doors open the scanner downloads the event's list (code, name,
 * ticket, whether the person is already in today) and answers from it when
 * the server cannot be reached. Scans made that way wait on the phone and are
 * sent here when the connection is back, with the time they really happened.
 *
 * Every scan carries a reference made on the phone. It is stored with the
 * check-in row, so a scan that reached the server just before the connection
 * dropped is never recorded twice when the phone sends it again.
 *
 * Endpoints (dashboard nonce, manager or scanner, event scope):
 *   sc_scanner_offline_list  — the list, in pages, then only what changed
 *   sc_scanner_offline_sync  — scans made without a connection
 *   sc_scanner_nonce         — a fresh nonce for a page left open all day
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

const SC_SCANNER_OFFLINE_DB_VERSION = '1';

/** Rows per page of the downloaded list. */
const SC_SCANNER_OFFLINE_PAGE = 2000;

/** Scans accepted in one sync request. */
const SC_SCANNER_OFFLINE_BATCH = 100;

/** A scan older than this is refused rather than written into the past. */
const SC_SCANNER_OFFLINE_MAX_AGE = 1209600; // 14 days

function sc_scanner_offline_install() {
    if (get_option('sc_scanner_offline_db') === SC_SCANNER_OFFLINE_DB_VERSION) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_checkins';
    if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
        return;
    }
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
    if (!in_array('client_ref', $cols, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN client_ref varchar(40) DEFAULT NULL, ADD UNIQUE KEY client_ref (client_ref)");
    }
    update_option('sc_scanner_offline_db', SC_SCANNER_OFFLINE_DB_VERSION, false);
}
add_action('init', 'sc_scanner_offline_install', 6);

function sc_scanner_offline_ready() {
    return get_option('sc_scanner_offline_db') === SC_SCANNER_OFFLINE_DB_VERSION;
}

/** A reference made on the phone: letters, digits and dashes, or nothing. */
function sc_scanner_clean_ref($value) {
    $value = is_string($value) ? trim($value) : '';
    return preg_match('/^[A-Za-z0-9-]{8,40}$/', $value) ? $value : '';
}

/** Door and scanner staff only; answers and stops otherwise. */
function sc_scanner_offline_guard() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('code' => 'nonce', 'message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('code' => 'denied', 'message' => __('Permission denied.', 'sc_events')), 403);
    }
}

/* ==========================================================================
   One scan of a registered attendee — shared by the live scan and the sync
   ========================================================================== */

/**
 * Check a ticket against the door and record the scan.
 *
 * @param object $a    Attendee row joined with the event (event_title, attendance_tracking).
 * @param array  $opts workshop_id, event_id  the door being scanned at;
 *                     gate_id;
 *                     at          local time the scan happened ('' = now);
 *                     action      'check_in' | 'check_out' decided by an offline phone ('' = decide here);
 *                     client_ref  reference made on the phone;
 *                     method      'qr' | 'offline'.
 * @return array ok=false with title/message/code, or ok=true with the scan's outcome.
 */
function sc_scanner_record_attendee_scan($a, array $opts) {
    global $wpdb;
    $opts = wp_parse_args($opts, array(
        'workshop_id' => 0,
        'event_id'    => 0,
        'gate_id'     => 0,
        'at'          => '',
        'action'      => '',
        'client_ref'  => '',
        'method'      => 'qr',
    ));
    $attendees = $wpdb->prefix . 'sc_attendees';
    $events    = $wpdb->prefix . 'sc_events';
    $checkins  = $wpdb->prefix . 'sc_checkins';

    if ($a->status !== 'active') {
        return array('ok' => false, 'code' => 'inactive', 'title' => 'Ticket Inactive',
            'message' => __('This ticket has been cancelled or transferred.', 'sc_events'));
    }
    if ($a->payment_status !== 'success' && (float) $a->ticket_price > 0) {
        return array('ok' => false, 'code' => 'unpaid', 'title' => 'Payment Pending',
            'message' => __('Payment for this ticket has not been confirmed yet.', 'sc_events'));
    }
    // A workshop door takes only its own tickets; the event entrance takes only event tickets.
    if ($opts['workshop_id']) {
        if ((int) $a->workshop_id !== (int) $opts['workshop_id']) {
            $expected = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}sc_workshops WHERE id = %d", $opts['workshop_id']));
            return array('ok' => false, 'code' => 'wrong_workshop', 'title' => 'Wrong Workshop',
                'message' => sprintf(__('This ticket is not registered for this workshop. Expected: %s', 'sc_events'), $expected ?: 'Unknown'));
        }
    } elseif ($opts['event_id']) {
        if ((int) $a->event_id !== (int) $opts['event_id']) {
            $expected = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$events} WHERE id = %d", $opts['event_id']));
            return array('ok' => false, 'code' => 'wrong_event', 'title' => 'Wrong Event',
                'message' => sprintf(__('This ticket belongs to a different event. Expected: %s', 'sc_events'), $expected ?: 'Unknown'));
        }
        if (!empty($a->workshop_id)) {
            return array('ok' => false, 'code' => 'workshop_ticket', 'title' => 'Workshop Ticket',
                'message' => __('This is a workshop ticket. Choose its workshop in "Scanning at" and scan again.', 'sc_events'));
        }
    }

    $ref = sc_scanner_offline_ready() ? sc_scanner_clean_ref($opts['client_ref']) : '';
    if ($ref) {
        $seen = $wpdb->get_row($wpdb->prepare("SELECT action, created_at FROM {$checkins} WHERE client_ref = %s", $ref));
        if ($seen) {
            return array('ok' => true, 'replayed' => true, 'already_checked_in' => false,
                'action_type' => $seen->action === 'checkout' ? 'check_out' : 'check_in', 'at' => $seen->created_at);
        }
    }

    $real_now = current_time('mysql');
    $now      = $opts['at'] ?: $real_now;
    $ts       = strtotime($now);
    $day      = substr($now, 0, 10);
    $day_from = $day . ' 00:00:00';
    $day_to   = gmdate('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00';
    $tracking = (int) $a->attendance_tracking === 1;
    $user_id  = get_current_user_id();
    $action   = 'check_in';
    $duration = '';

    // With in/out tracking, a second scan the same day is a check-out. A phone
    // that decided while offline already told the door which one it was.
    if ($tracking) {
        $last = $wpdb->get_row($wpdb->prepare(
            "SELECT action, created_at FROM {$checkins}
             WHERE attendee_id = %d AND created_at >= %s AND created_at < %s AND created_at <= %s
             ORDER BY created_at DESC, id DESC LIMIT 1",
            $a->id, $day_from, $day_to, $now
        ));
        $last_in = $last && in_array($last->action, array('checkin', 'manual_checkin'), true);
        if (in_array($opts['action'], array('check_in', 'check_out'), true)) {
            $action = $opts['action'];
        } elseif ($last_in) {
            $action = 'check_out';
        }
        if ($action === 'check_out' && $last_in) {
            $seconds  = max(0, $ts - strtotime($last->created_at));
            $duration = sprintf('%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60));
        }
    }

    $row = array(
        'attendee_id' => (int) $a->id,
        'event_id'    => (int) $a->event_id,
        'workshop_id' => !empty($a->workshop_id) ? (int) $a->workshop_id : null,
        'action'      => $action === 'check_out' ? 'checkout' : 'checkin',
        'scanned_by'  => $user_id,
        'scan_method' => $opts['method'] === 'offline' ? 'offline' : 'qr',
        'device_info' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 250) : null,
        'created_at'  => $now,
    );
    if ($ref) {
        $row['client_ref'] = $ref;
    }
    if (!$wpdb->insert($checkins, $row)) {
        // Two sends of the same scan at once: the other one wrote it.
        if ($ref && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$checkins} WHERE client_ref = %s", $ref))) {
            return array('ok' => true, 'replayed' => true, 'already_checked_in' => false, 'action_type' => $action, 'at' => $now);
        }
        return array('ok' => false, 'code' => 'db', 'title' => 'Not Saved',
            'message' => __('The scan could not be saved. Scan again.', 'sc_events'));
    }
    $mine = (int) $wpdb->insert_id;

    // Without tracking, the earliest entry of the day is the real one. Reading it
    // after writing ours means two gates scanning the same ticket in the same
    // second cannot both show green.
    $first_in = null;
    if (!$tracking) {
        $earliest = $wpdb->get_row($wpdb->prepare(
            "SELECT id, created_at FROM {$checkins}
             WHERE attendee_id = %d AND action IN ('checkin', 'manual_checkin') AND created_at >= %s AND created_at < %s
             ORDER BY created_at ASC, id ASC LIMIT 1",
            $a->id, $day_from, $day_to
        ));
        if ($earliest && (int) $earliest->id !== $mine) {
            $first_in = $earliest->created_at;
        } elseif ((int) $a->checked_in === 1 && $a->checked_in_at && substr($a->checked_in_at, 0, 10) === $day && $a->checked_in_at < $now) {
            // Checked in the same day by a path that kept no log row.
            $first_in = $a->checked_in_at;
        }
    }

    // First check-in ever: flip the flag once, whichever gate gets there first.
    if ($action === 'check_in') {
        $flipped = $wpdb->query($wpdb->prepare(
            "UPDATE {$attendees} SET checked_in = 1, checked_in_at = %s, checked_in_by = %d, updated_at = %s WHERE id = %d AND checked_in = 0",
            $now, $user_id, $real_now, $a->id
        ));
        if ($flipped) {
            $wpdb->query($wpdb->prepare("UPDATE {$events} SET total_checked_in = total_checked_in + 1 WHERE id = %d", $a->event_id));
        } else {
            // A scan made offline can be earlier than the one that arrived first.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$attendees} SET checked_in_at = %s, updated_at = %s WHERE id = %d AND checked_in_at > %s",
                $now, $real_now, $a->id, $now
            ));
        }
    }

    $today_scans = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$checkins} WHERE attendee_id = %d AND created_at >= %s AND created_at < %s",
        $a->id, $day_from, $day_to
    ));

    $gate_info = null;
    if ($opts['gate_id'] && class_exists('SC_Gate')) {
        $gate = SC_Gate::get($opts['gate_id']);
        if ($gate) {
            $gate_info = array('id' => $gate->id, 'name' => $gate->name, 'zone_id' => $gate->zone_id);
            SC_Gate::log_entry($opts['gate_id'], 1, array(
                'attendee_id' => $a->id,
                'ticket_code' => $a->ticket_code,
                'scan_method' => $row['scan_method'],
                'scanned_by'  => $user_id,
                'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
                'is_valid'    => 1,
            ));
        }
    }

    return array(
        'ok'                 => true,
        'replayed'           => false,
        'action_type'        => $action,
        'already_checked_in' => (bool) $first_in,
        'first_checked_in_at'=> $first_in ? get_gmt_from_date($first_in, 'Y-m-d\TH:i:s\Z') : '',
        'first_in'           => $first_in ?: '',
        'tracking_enabled'   => $tracking,
        'total_scans'        => $today_scans,
        'duration'           => $duration,
        'gate'               => $gate_info,
        'at'                 => $now,
    );
}

/* ==========================================================================
   The list the phone keeps
   ========================================================================== */

/**
 * Compact rows for the phone: [code, name, ticket, workshop, state, phone end,
 * last action today, its time, first check-in today]. The phone gets no email
 * and only the last four digits of a phone number.
 */
function sc_scanner_offline_rows($rows, $event_id, $day) {
    global $wpdb;
    if (!$rows) {
        return array();
    }
    $ids = array_map('intval', wp_list_pluck($rows, 'id'));
    $today = array();
    $next_day = gmdate('Y-m-d', strtotime($day . ' +1 day'));
    foreach (array_chunk($ids, 1000) as $chunk) {
        $log = $wpdb->get_results($wpdb->prepare(
            "SELECT attendee_id, action, created_at FROM {$wpdb->prefix}sc_checkins
             WHERE event_id = %d AND created_at >= %s AND created_at < %s AND attendee_id IN (" . implode(',', $chunk) . ')
             ORDER BY created_at ASC, id ASC',
            $event_id, $day . ' 00:00:00', $next_day . ' 00:00:00'
        ));
        foreach ($log as $entry) {
            $id = (int) $entry->attendee_id;
            $is_in = in_array($entry->action, array('checkin', 'manual_checkin'), true);
            if (!isset($today[$id])) {
                $today[$id] = array('last' => '', 'at' => '', 'first' => '');
            }
            $today[$id]['last'] = $is_in ? 'i' : 'o';
            $today[$id]['at'] = $entry->created_at;
            if ($is_in && !$today[$id]['first']) {
                $today[$id]['first'] = $entry->created_at;
            }
        }
    }
    $iso = static function ($local) {
        return $local ? get_gmt_from_date($local, 'Y-m-d\TH:i:s\Z') : '';
    };

    $out = array();
    foreach ($rows as $r) {
        $state = 'ok';
        if ($r->status !== 'active') {
            $state = 'x';
        } elseif ($r->payment_status !== 'success' && (float) $r->ticket_price > 0) {
            $state = 'p';
        }
        $digits = preg_replace('/\D+/', '', (string) $r->phone);
        $t = isset($today[(int) $r->id]) ? $today[(int) $r->id] : null;
        // Checked in today by a path that kept no log row.
        if (!$t && (int) $r->checked_in === 1 && $r->checked_in_at && substr($r->checked_in_at, 0, 10) === $day) {
            $t = array('last' => 'i', 'at' => $r->checked_in_at, 'first' => $r->checked_in_at);
        }
        $out[] = array(
            (string) $r->ticket_code,
            (string) $r->name,
            (string) $r->ticket_name,
            (int) $r->workshop_id,
            $state,
            strlen($digits) >= 4 ? substr($digits, -4) : '',
            $t ? $t['last'] : '',
            $t ? $iso($t['at']) : '',
            $t ? $iso($t['first']) : '',
        );
    }
    return $out;
}

add_action('wp_ajax_sc_scanner_offline_list', 'sc_scanner_offline_list');
function sc_scanner_offline_list() {
    sc_scanner_offline_guard();
    global $wpdb;

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $after    = isset($_POST['after']) ? absint($_POST['after']) : 0;
    $since    = isset($_POST['since']) ? sanitize_text_field(wp_unslash($_POST['since'])) : '';
    $since_ck = isset($_POST['ck']) ? absint($_POST['ck']) : 0;
    if ($since && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
        $since = '';
    }

    $event = $event_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT id, end_date, attendance_tracking FROM {$wpdb->prefix}sc_events WHERE id = %d",
        $event_id
    )) : null;
    if (!$event || !sc_scanner_can_access_event($event_id)) {
        wp_send_json_error(array('code' => 'denied', 'message' => __('You are not assigned to scan tickets for this event.', 'sc_events')), 403);
    }

    $day = current_time('Y-m-d');
    // A day after the event the phone lets go of the list.
    if ($event->end_date && $event->end_date < gmdate('Y-m-d', strtotime($day . ' -1 day'))) {
        wp_send_json_success(array('expired' => true));
    }

    $table = $wpdb->prefix . 'sc_attendees';
    $cols  = 'id, ticket_code, name, ticket_name, workshop_id, status, payment_status, ticket_price, phone, checked_in, checked_in_at';
    // Taken before reading, so nothing that changes while the pages load is missed.
    $cursor = array(
        'since' => gmdate('Y-m-d H:i:s', current_time('timestamp') - 2),
        'ck'    => (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}sc_checkins"),
    );

    $more = false;
    if ($since) {
        $changed = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE event_id = %d AND updated_at >= %s
             UNION
             SELECT DISTINCT attendee_id FROM {$wpdb->prefix}sc_checkins WHERE event_id = %d AND id > %d
             LIMIT 5001",
            $event_id, $since, $event_id, $since_ck
        ));
        if (count($changed) > 5000) {
            // Too much changed to patch; start over.
            wp_send_json_success(array('reset' => true));
        }
        $rows = $changed ? $wpdb->get_results(
            "SELECT {$cols} FROM {$table} WHERE id IN (" . implode(',', array_map('intval', $changed)) . ')'
        ) : array();
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT {$cols} FROM {$table} WHERE event_id = %d AND id > %d AND ticket_code <> '' ORDER BY id ASC LIMIT %d",
            $event_id, $after, SC_SCANNER_OFFLINE_PAGE + 1
        ));
        $more = count($rows) > SC_SCANNER_OFFLINE_PAGE;
        $rows = array_slice($rows, 0, SC_SCANNER_OFFLINE_PAGE);
    }

    $companies = array();
    if (!$after) {
        $company_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT company_code, company_name, status, payment_status, checked_in, checked_in_at
             FROM {$wpdb->prefix}sc_company_attendees WHERE event_id = %d AND company_code <> ''",
            $event_id
        ));
        foreach ($company_rows as $c) {
            $companies[] = array(
                strtoupper($c->company_code),
                (string) $c->company_name,
                $c->status !== 'active' ? 'x' : ($c->payment_status !== 'success' ? 'p' : 'ok'),
                (int) $c->checked_in === 1 && $c->checked_in_at ? get_gmt_from_date($c->checked_in_at, 'Y-m-d\TH:i:s\Z') : '',
            );
        }
    }

    wp_send_json_success(array(
        'rows'      => sc_scanner_offline_rows($rows, $event_id, $day),
        'companies' => $companies,
        'more'      => $more,
        'after'     => $rows ? (int) end($rows)->id : $after,
        'cursor'    => $cursor,
        'delta'     => (bool) $since,
        'day'       => $day,
        'tracking'  => (int) $event->attendance_tracking === 1,
        'user'      => get_current_user_id(),
    ));
}

/* ==========================================================================
   Scans made without a connection
   ========================================================================== */

add_action('wp_ajax_sc_scanner_offline_sync', 'sc_scanner_offline_sync');
function sc_scanner_offline_sync() {
    sc_scanner_offline_guard();
    global $wpdb;

    $scans = isset($_POST['scans']) ? json_decode(wp_unslash($_POST['scans']), true) : null;
    if (!is_array($scans)) {
        wp_send_json_error(array('code' => 'bad_request', 'message' => __('Nothing to send.', 'sc_events')), 400);
    }
    $scans = array_slice($scans, 0, SC_SCANNER_OFFLINE_BATCH);

    $now_gmt = time();
    $results = array();
    foreach ($scans as $scan) {
        if (!is_array($scan)) {
            continue;
        }
        $ref  = sc_scanner_clean_ref(isset($scan['ref']) ? $scan['ref'] : '');
        $code = isset($scan['code']) ? sanitize_text_field((string) $scan['code']) : '';
        if (!$ref || $code === '') {
            continue;
        }
        $result = array('ref' => $ref, 'status' => 'done', 'name' => '', 'message' => '');

        // The phone's clock, in UTC; never in the future, never weeks old.
        $when = isset($scan['at']) ? strtotime((string) $scan['at']) : false;
        if (!$when || $when > $now_gmt) {
            $when = $now_gmt;
        }
        if ($when < $now_gmt - SC_SCANNER_OFFLINE_MAX_AGE) {
            $results[] = array_merge($result, array('status' => 'refused', 'message' => __('This scan is too old to record.', 'sc_events')));
            continue;
        }
        $at = get_date_from_gmt(gmdate('Y-m-d H:i:s', $when));

        $event_id    = isset($scan['event']) ? absint($scan['event']) : 0;
        $workshop_id = isset($scan['workshop']) ? absint($scan['workshop']) : 0;
        $gate_id     = isset($scan['gate']) ? absint($scan['gate']) : 0;
        $action      = isset($scan['action']) && $scan['action'] === 'check_out' ? 'check_out' : 'check_in';

        if (preg_match('/^COMP-[A-Z0-9]+-[A-Z0-9]+$/i', $code)) {
            $results[] = sc_scanner_offline_sync_company(strtoupper($code), $event_id, $result);
            continue;
        }

        $a = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, e.title AS event_title, e.attendance_tracking
             FROM {$wpdb->prefix}sc_attendees a
             LEFT JOIN {$wpdb->prefix}sc_events e ON a.event_id = e.id
             WHERE a.ticket_code = %s LIMIT 1",
            $code
        ));
        if (!$a) {
            $results[] = array_merge($result, array('status' => 'not_found', 'message' => __('Ticket not found in the system.', 'sc_events')));
            continue;
        }
        $result['name'] = (string) $a->name;
        if (!sc_scanner_can_access_event($a->event_id)) {
            $results[] = array_merge($result, array('status' => 'refused', 'message' => __('You are not assigned to scan tickets for this event.', 'sc_events')));
            continue;
        }

        $done = sc_scanner_record_attendee_scan($a, array(
            'workshop_id' => $workshop_id,
            'event_id'    => $event_id,
            'gate_id'     => $gate_id,
            'at'          => $at,
            'action'      => (int) $a->attendance_tracking === 1 ? $action : '',
            'client_ref'  => $ref,
            'method'      => 'offline',
        ));
        if (!$done['ok']) {
            $result['status']  = 'refused';
            $result['message'] = $done['message'];
        } elseif (!empty($done['already_checked_in'])) {
            $result['status']  = 'already';
            $result['message'] = sprintf(
                /* translators: %s: time of the first check-in */
                __('Was already checked in at %s.', 'sc_events'),
                mysql2date('H:i', $done['first_in'])
            );
        }
        $results[] = $result;
    }

    wp_send_json_success(array('results' => $results));
}

function sc_scanner_offline_sync_company($code, $event_id, array $result) {
    global $wpdb;
    $company = $wpdb->get_row($wpdb->prepare(
        "SELECT id, event_id, company_name, status, payment_status, checked_in FROM {$wpdb->prefix}sc_company_attendees WHERE company_code = %s LIMIT 1",
        $code
    ));
    if (!$company) {
        return array_merge($result, array('status' => 'not_found', 'message' => __('This company badge was not found.', 'sc_events')));
    }
    $result['name'] = (string) $company->company_name;
    if (!sc_scanner_can_access_event($company->event_id) || ($event_id && (int) $company->event_id !== $event_id)) {
        return array_merge($result, array('status' => 'refused', 'message' => __('This badge belongs to a different event.', 'sc_events')));
    }
    if ($company->status !== 'active' || $company->payment_status !== 'success') {
        return array_merge($result, array('status' => 'refused', 'message' => __('This company badge is not active.', 'sc_events')));
    }
    if ((int) $company->checked_in === 1) {
        return $result;
    }
    if (class_exists('SC_Company_Attendee')) {
        SC_Company_Attendee::check_in((int) $company->id, get_current_user_id());
    }
    return $result;
}

/* A page left open at the door outlives its nonce. */
add_action('wp_ajax_sc_scanner_nonce', 'sc_scanner_nonce');
function sc_scanner_nonce() {
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !SC_Event_Manager_Dashboard::is_event_scanner()) {
        wp_send_json_error(array('code' => 'denied'), 403);
    }
    nocache_headers();
    wp_send_json_success(array('nonce' => wp_create_nonce('sc_dashboard_nonce')));
}

/* ==========================================================================
   The scanner page opens again without a connection
   ========================================================================== */

/**
 * The service worker is served from the site root so it may control the
 * scanner page. It only ever answers for that page and what it loads, and
 * always asks the network first.
 */
add_action('init', 'sc_scanner_service_worker', 1);
function sc_scanner_service_worker() {
    if (!isset($_GET['sc_scanner_sw'])) {
        return;
    }
    $file = get_template_directory() . '/assets/dashboard/js/scanner-sw.js';
    if (!is_readable($file)) {
        status_header(404);
        exit;
    }
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

function sc_scanner_service_worker_url() {
    $file = get_template_directory() . '/assets/dashboard/js/scanner-sw.js';
    return add_query_arg('sc_scanner_sw', file_exists($file) ? filemtime($file) : '1', home_url('/'));
}
