<?php
/**
 * Certificates — dashboard list, bulk actions and issuing.
 *
 * Replaces the older list / issue / email / revoke handlers, which ignored
 * pagination (every page showed the first 50), crashed on object/array mix-ups
 * and treated downloaded certificates as no longer valid.
 *
 * Status meanings: issued = not downloaded yet, downloaded = the attendee has
 * the PDF, revoked = no longer valid. Only revoked is "not valid".
 *
 * Eligibility follows the same rules as the attendee's own claim in My Account
 * (sc_request_certificate_public): an active, paid registration, plus the
 * event's or workshop's check-in / check-out / event-ended requirements.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_certs_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

/* ---------------------------------------------------------------- issued list */

function sc_certs_read_filters($src) {
    $get = function ($key) use ($src) {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
    };
    return array(
        'search'   => trim($get('search')),
        'event_id' => absint($get('event_id')),
        'view'     => in_array($get('view'), array('all', 'issued', 'downloaded', 'revoked', 'unsent'), true) ? $get('view') : 'all',
        'orderby'  => in_array($get('orderby'), array('issued', 'name', 'downloads'), true) ? $get('orderby') : 'issued',
        'order'    => strtolower($get('order')) === 'asc' ? 'ASC' : 'DESC',
    );
}

/**
 * @return array [from_where_sql, values]
 */
function sc_certs_from_where($f, $with_view = true) {
    global $wpdb;
    $p = $wpdb->prefix;
    $sql = "FROM {$p}sc_certificates c
        LEFT JOIN {$p}sc_attendees a ON a.id = c.attendee_id
        WHERE 1=1";
    $values = array();
    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $sql .= ' AND (c.attendee_name LIKE %s OR a.email LIKE %s OR c.certificate_number LIKE %s OR c.verification_code = %s)';
        array_push($values, $like, $like, $like, $f['search']);
    }
    if ($f['event_id']) {
        $sql .= ' AND c.event_id = %d';
        $values[] = $f['event_id'];
    }
    if ($with_view && $f['view'] !== 'all') {
        $sql .= ' AND ' . sc_certs_view_condition($f['view']);
    }
    return array($sql, $values);
}

function sc_certs_view_condition($view) {
    switch ($view) {
        case 'issued':
            return "c.status = 'issued'";
        case 'downloaded':
            return "c.status = 'downloaded'";
        case 'revoked':
            return "c.status = 'revoked'";
        case 'unsent':
            return "(c.status <> 'revoked' AND COALESCE(c.email_sent, 0) = 0 AND c.download_count = 0)";
    }
    return '1=1';
}

function sc_certs_row($c, $templates, $workshops) {
    $token = wp_hash($c->verification_code . $c->certificate_number);
    return array(
        'id'            => (int) $c->id,
        'number'        => $c->certificate_number,
        'code'          => $c->verification_code,
        'name'          => $c->attendee_name,
        'email'         => (string) $c->email,
        'attendee_id'   => (int) $c->attendee_id,
        'attendee_live' => (bool) $c->attendee_exists,
        'event_id'      => (int) $c->event_id,
        'event_title'   => $c->event_title,
        'workshop'      => $c->workshop_id && isset($workshops[(int) $c->workshop_id]) ? $workshops[(int) $c->workshop_id] : '',
        'template'      => isset($templates[(int) $c->template_id]) ? $templates[(int) $c->template_id] : '',
        'status'        => $c->status,
        'issued_at'     => $c->issued_at,
        'self_claimed'  => $c->user_id && (int) $c->issued_by === (int) $c->user_id,
        'downloads'     => (int) $c->download_count,
        'downloaded_at' => $c->downloaded_at,
        'email_sent_at' => $c->email_sent ? $c->email_sent_at : null,
        'revoked_at'    => $c->revoked_at,
        'revoke_reason' => (string) $c->revoke_reason,
        'download_url'  => add_query_arg(array('action' => 'sc_download_certificate', 'id' => (int) $c->id, 'token' => $token), admin_url('admin-ajax.php')),
        'verify_url'    => SC_Certificate::get_verification_url($c->verification_code),
    );
}

function sc_certs_lookup_maps() {
    global $wpdb;
    $templates = array();
    foreach ($wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sc_certificate_templates") as $t) {
        $templates[(int) $t->id] = $t->name;
    }
    $workshops = array();
    foreach ($wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_workshops") as $w) {
        $workshops[(int) $w->id] = $w->title;
    }
    return array($templates, $workshops);
}

add_action('wp_ajax_sc_get_certificates_paginated', 'sc_get_certificates_list');
function sc_get_certificates_list() {
    sc_certs_verify_request();
    global $wpdb;

    $f = sc_certs_read_filters($_POST);
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 25)));

    list($from, $values) = sc_certs_from_where($f);
    $prepare = function ($sql, $vals) use ($wpdb) {
        return $vals ? $wpdb->prepare($sql, $vals) : $sql;
    };
    $total = (int) $wpdb->get_var($prepare("SELECT COUNT(*) $from", $values));
    $order = array('issued' => 'c.issued_at', 'name' => 'c.attendee_name', 'downloads' => 'c.download_count')[$f['orderby']];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, a.email, a.user_id, a.id IS NOT NULL AS attendee_exists $from ORDER BY $order {$f['order']}, c.id DESC LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    list($templates, $workshops) = sc_certs_lookup_maps();
    $out = array_map(function ($c) use ($templates, $workshops) {
        return sc_certs_row($c, $templates, $workshops);
    }, $rows);

    $response = array('rows' => $out, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        list($from_all, $values_all) = sc_certs_from_where($f, false);
        $counts = $wpdb->get_row($prepare("SELECT COUNT(*) AS all_rows,
            COALESCE(SUM(" . sc_certs_view_condition('issued') . "), 0) AS issued,
            COALESCE(SUM(" . sc_certs_view_condition('downloaded') . "), 0) AS downloaded,
            COALESCE(SUM(" . sc_certs_view_condition('revoked') . "), 0) AS revoked,
            COALESCE(SUM(" . sc_certs_view_condition('unsent') . "), 0) AS unsent
            $from_all", $values_all));
        $response['counts'] = array(
            'all'        => (int) $counts->all_rows,
            'issued'     => (int) $counts->issued,
            'downloaded' => (int) $counts->downloaded,
            'revoked'    => (int) $counts->revoked,
            'unsent'     => (int) $counts->unsent,
        );
    }
    wp_send_json_success($response);
}

/**
 * Send a certificate: WhatsApp with the PDF, and email when email is on (Automatic messages).
 * One certificate goes at once; in a batch every message takes its turn in the shared slow lane.
 *
 * @return bool True when something was sent or queued.
 */
function sc_certs_send_email($c, $in_batch = false) {
    if (!function_exists('sc_notify_certificate')) {
        return false;
    }
    $interval = function_exists('sc_notify_settings') ? sc_notify_settings()['interval'] : 60;
    $result = sc_notify_certificate((int) $c->id, true, array(
        'email_now'     => !$in_batch,
        'delay_seconds' => $in_batch ? sc_wabot_next_delay($interval) : 0,
    ));
    if ($result['whatsapp']) {
        SC_Certificate::mark_email_sent((int) $c->id);
    }
    return (bool) ($result['whatsapp'] || $result['email']);
}

/**
 * Bulk actions: send (op "email"), revoke, reinstate. At most 50 per request.
 */
add_action('wp_ajax_sc_certificates_bulk', 'sc_certificates_bulk');
function sc_certificates_bulk() {
    sc_certs_verify_request();
    global $wpdb;
    $t = $wpdb->prefix . 'sc_certificates';

    $op = sanitize_key($_POST['op'] ?? '');
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? array()))));
    if (!$ids) {
        wp_send_json_error(array('message' => __('No certificates selected.', 'sc_events')));
    }
    $in = implode(',', $ids);
    $now = current_time('mysql');

    switch ($op) {
        case 'revoke':
            $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
            $n = (int) $wpdb->query($wpdb->prepare("UPDATE $t SET status = 'revoked', revoked_at = %s, revoke_reason = %s, updated_at = %s WHERE id IN ($in) AND status <> 'revoked'", $now, $reason, $now));
            wp_send_json_success(array('message' => sprintf(_n('%d certificate revoked. Its verification page now says it is no longer valid.', '%d certificates revoked. Their verification pages now say they are no longer valid.', $n, 'sc_events'), $n)));
            break;
        case 'reinstate':
            $n = (int) $wpdb->query($wpdb->prepare("UPDATE $t SET status = IF(download_count > 0, 'downloaded', 'issued'), revoked_at = NULL, revoke_reason = NULL, updated_at = %s WHERE id IN ($in) AND status = 'revoked'", $now));
            wp_send_json_success(array('message' => sprintf(_n('%d certificate reinstated.', '%d certificates reinstated.', $n, 'sc_events'), $n)));
            break;
        case 'email':
            if (count($ids) > 50) {
                wp_send_json_error(array('message' => __('Send at most 50 certificates at a time.', 'sc_events')));
            }
            $rows = $wpdb->get_results("SELECT c.*, a.email FROM $t c LEFT JOIN {$wpdb->prefix}sc_attendees a ON a.id = c.attendee_id WHERE c.id IN ($in)");
            $sent = $failed = $skipped = 0;
            // A batch (also one arriving in chunks from the issue page) goes through the slow lane.
            $in_batch = count($rows) > 1 || !empty($_POST['continuing']);
            foreach ($rows as $c) {
                if ($c->status === 'revoked') {
                    $skipped++;
                } elseif (sc_certs_send_email($c, $in_batch)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            $message = sprintf(_n('%d certificate sent.', '%d certificates sent.', $sent, 'sc_events'), $sent);
            if ($sent > 1) {
                $message .= ' ' . __('WhatsApp messages go out one by one, about a minute apart.', 'sc_events');
            }
            if ($failed) {
                $message .= ' ' . sprintf(_n('%d could not be sent — no valid WhatsApp number, and email is off or failed.', '%d could not be sent — no valid WhatsApp number, and email is off or failed.', $failed, 'sc_events'), $failed);
            }
            if ($skipped) {
                $message .= ' ' . sprintf(_n('%d revoked certificate skipped.', '%d revoked certificates skipped.', $skipped, 'sc_events'), $skipped);
            }
            wp_send_json_success(array('message' => $message, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped));
            break;
        default:
            wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
    }
}

/* ------------------------------------------------------------------- issuing */

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
 * SQL pieces for the attendees of a scope with their certificate state.
 *
 * @return array [from_where_sql, values, eligible_condition, rules_condition]
 */
function sc_certs_candidate_sql($scope) {
    global $wpdb;
    $p = $wpdb->prefix;
    $sql = "FROM {$p}sc_attendees a
        LEFT JOIN {$p}sc_certificates c ON c.attendee_id = a.id AND c.event_id = a.event_id
        WHERE a.event_id = %d AND " . ($scope['workshop_id'] ? 'a.workshop_id = %d' : '(a.workshop_id IS NULL OR a.workshop_id = 0)');
    $values = array($scope['event_id']);
    if ($scope['workshop_id']) {
        $values[] = $scope['workshop_id'];
    }
    $checked_out = "EXISTS (SELECT 1 FROM {$p}sc_checkins k WHERE k.attendee_id = a.id AND k.action IN ('checkout', 'manual_checkout'))";
    // Requirements the manager may choose to override; payment and cancellation are never overridden.
    $rules = array('1=1');
    if ($scope['require_checkin']) {
        $rules[] = 'a.checked_in = 1';
    }
    if ($scope['require_checkout']) {
        $rules[] = $checked_out;
    }
    if ($scope['require_ended'] && !$scope['ended']) {
        $rules[] = '0=1';
    }
    $base_ok = "a.status = 'active' AND a.payment_status = 'success'";
    $rules_sql = '(' . implode(' AND ', $rules) . ')';
    $eligible = "(c.id IS NULL AND $base_ok AND $rules_sql)";
    return array($sql, $values, $eligible, $rules_sql, $base_ok, $checked_out);
}

add_action('wp_ajax_sc_certificate_candidates', 'sc_certificate_candidates');
function sc_certificate_candidates() {
    sc_certs_verify_request();
    global $wpdb;

    $scope = sc_certs_scope(sanitize_text_field(wp_unslash($_POST['scope'] ?? '')));
    if (!$scope) {
        wp_send_json_error(array('message' => __('Choose an event or workshop.', 'sc_events')));
    }
    $view = in_array($_POST['view'] ?? '', array('eligible', 'waiting', 'has', 'all'), true) ? sanitize_key($_POST['view']) : 'eligible';
    $search = trim(sanitize_text_field(wp_unslash($_POST['search'] ?? '')));
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 50)));

    list($from, $values, $eligible, $rules, $base_ok, $checked_out) = sc_certs_candidate_sql($scope);
    $conditions = array(
        'eligible' => $eligible,
        'waiting'  => "(c.id IS NULL AND NOT $eligible)",
        'has'      => 'c.id IS NOT NULL',
        'all'      => '1=1',
    );
    $where_search = '';
    $search_values = array();
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_search = ' AND (a.name LIKE %s OR a.email LIKE %s OR a.phone LIKE %s OR a.ticket_code LIKE %s)';
        $search_values = array($like, $like, $like, $like);
    }

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from AND {$conditions[$view]}$where_search", array_merge($values, $search_values)));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.name, a.email, a.ticket_name, a.checked_in, a.checked_in_at, a.status, a.payment_status,
                c.id AS cert_id, c.status AS cert_status, c.certificate_number, c.issued_at,
                $checked_out AS checked_out, ($eligible) AS is_eligible
         $from AND {$conditions[$view]}$where_search ORDER BY a.name ASC, a.id ASC LIMIT %d OFFSET %d",
        array_merge($values, $search_values, array($per_page, ($page - 1) * $per_page))
    ));

    $out = array();
    foreach ($rows as $r) {
        $why = array();
        if (!$r->cert_id) {
            if ($r->status !== 'active') {
                $why[] = 'cancelled';
            }
            if ($r->payment_status !== 'success') {
                $why[] = 'unpaid';
            }
            if ($scope['require_checkin'] && !(int) $r->checked_in) {
                $why[] = 'not_checked_in';
            }
            if ($scope['require_checkout'] && !(int) $r->checked_out) {
                $why[] = 'not_checked_out';
            }
            if ($scope['require_ended'] && !$scope['ended']) {
                $why[] = 'not_ended';
            }
        }
        $out[] = array(
            'id'          => (int) $r->id,
            'name'        => $r->name,
            'email'       => $r->email,
            'ticket'      => $r->ticket_name,
            'checked_in'  => (bool) $r->checked_in,
            'eligible'    => (bool) $r->is_eligible,
            'overridable' => !$r->cert_id && $r->status === 'active' && $r->payment_status === 'success',
            'why'         => $why,
            'cert'        => $r->cert_id ? array('id' => (int) $r->cert_id, 'status' => $r->cert_status, 'number' => $r->certificate_number, 'issued_at' => $r->issued_at) : null,
        );
    }

    $response = array('rows' => $out, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        $counts = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS all_rows,
            COALESCE(SUM($eligible), 0) AS eligible,
            COALESCE(SUM(c.id IS NULL AND NOT $eligible), 0) AS waiting,
            COALESCE(SUM(c.id IS NOT NULL), 0) AS has_cert
            $from", $values));
        $response['counts'] = array('eligible' => (int) $counts->eligible, 'waiting' => (int) $counts->waiting, 'has' => (int) $counts->has_cert, 'all' => (int) $counts->all_rows);
        $template = $scope['template_id'] ? $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$wpdb->prefix}sc_certificate_templates WHERE id = %d", $scope['template_id'])) : null;
        $response['scope'] = array(
            'title' => $scope['title'], 'type' => $scope['type'], 'enabled' => $scope['enabled'],
            'require_checkin' => $scope['require_checkin'], 'require_checkout' => $scope['require_checkout'],
            'require_ended' => $scope['require_ended'], 'ended' => $scope['ended'], 'end_date' => $scope['end_date'],
            'template_id' => $template ? (int) $template->id : 0, 'template_name' => $template ? $template->name : '',
        );
    }
    wp_send_json_success($response);
}

/**
 * Issue certificates for a scope: to the selected attendees, or to everyone eligible
 * in batches of 200 (the page calls again while "remaining" is above zero).
 */
add_action('wp_ajax_sc_issue_certificates', 'sc_issue_certificates');
function sc_issue_certificates() {
    @set_time_limit(300);
    sc_certs_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;

    $scope = sc_certs_scope(sanitize_text_field(wp_unslash($_POST['scope'] ?? '')));
    if (!$scope) {
        wp_send_json_error(array('message' => __('Choose an event or workshop.', 'sc_events')));
    }
    $template_id = absint($_POST['template_id'] ?? 0);
    if (!$template_id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}sc_certificate_templates WHERE id = %d", $template_id))) {
        wp_send_json_error(array('message' => __('Choose a certificate template.', 'sc_events'), 'errors' => array('template_id' => __('Choose a certificate template.', 'sc_events'))));
    }
    $override = !empty($_POST['override']);
    $all = !empty($_POST['all_eligible']);
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? array()))));
    if (!$all && !$ids) {
        wp_send_json_error(array('message' => __('Choose who gets a certificate.', 'sc_events')));
    }

    list($from, $values, $eligible, $rules, $base_ok) = sc_certs_candidate_sql($scope);
    $batch = 200;
    if ($all) {
        $condition = $eligible;
        $limit = $wpdb->prepare(' LIMIT %d', $batch);
    } else {
        $ids = array_slice($ids, 0, $batch);
        // Selected people: never without an active paid registration; the scope's rules only when not overridden.
        $condition = '(c.id IS NULL AND ' . $base_ok . ($override ? '' : ' AND ' . $rules) . ') AND a.id IN (' . implode(',', $ids) . ')';
        $limit = '';
    }
    $attendees = $wpdb->get_results($wpdb->prepare("SELECT a.id, a.name $from AND $condition ORDER BY a.id$limit", $values));

    $issued = 0;
    $failed = 0;
    $new_ids = array();
    $now = current_time('mysql');
    $year = current_time('Y');
    foreach ($attendees as $a) {
        $row = array(
            'certificate_number' => 'CERT-' . $year . '-' . strtoupper(bin2hex(random_bytes(3))),
            'verification_code'  => strtoupper(bin2hex(random_bytes(6))),
            'template_id'        => $template_id,
            'attendee_id'        => (int) $a->id,
            'event_id'           => $scope['event_id'],
            'workshop_id'        => $scope['workshop_id'] ?: null,
            'attendee_name'      => $a->name,
            'event_title'        => $scope['event_title'],
            'event_date'         => substr((string) $scope['event_date'], 0, 10),
            'status'             => 'issued',
            'issued_by'          => get_current_user_id(),
            'issued_at'          => $now,
            'created_at'         => $now,
            'updated_at'         => $now,
        );
        // A clash on the random number or code is retried once; a clash on attendee+event means it already exists.
        $ok = $wpdb->insert($p . 'sc_certificates', $row);
        if (!$ok && strpos((string) $wpdb->last_error, 'attendee_event') === false) {
            $row['certificate_number'] = 'CERT-' . $year . '-' . strtoupper(bin2hex(random_bytes(4)));
            $row['verification_code'] = strtoupper(bin2hex(random_bytes(6)));
            $ok = $wpdb->insert($p . 'sc_certificates', $row);
        }
        if ($ok) {
            $issued++;
            $new_ids[] = (int) $wpdb->insert_id;
            do_action('sc_certificate_issued', (int) $wpdb->insert_id, $row);
        } else {
            $failed++;
        }
    }

    $skipped = $all ? 0 : count($ids) - count($attendees);
    $remaining = $all ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from AND $eligible", $values)) : 0;
    wp_send_json_success(array(
        'issued'    => $issued,
        'failed'    => $failed,
        'skipped'   => $skipped,
        'remaining' => $failed && $remaining ? 0 : $remaining, // stop looping if inserts keep failing
        'ids'       => $new_ids,
    ));
}

/* ----------------------------------------------------------------- templates */

/**
 * Open a template as a sample PDF in the browser (GET, from the templates page).
 */
add_action('wp_ajax_sc_certificate_template_preview', 'sc_certificate_template_preview');
function sc_certificate_template_preview() {
    sc_certs_verify_request();
    $pdf_class = get_template_directory() . '/inc/certificates/class-sc-certificate-pdf.php';
    if (!file_exists($pdf_class)) {
        wp_die(esc_html__('PDF generation is not available.', 'sc_events'));
    }
    require_once $pdf_class;
    SC_Certificate_PDF::preview_template(absint($_GET['template_id'] ?? 0));
}
