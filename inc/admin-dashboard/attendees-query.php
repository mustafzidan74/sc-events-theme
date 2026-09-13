<?php
/**
 * Attendee list queries — one filter set shared by the dashboard list, its
 * tab counts and the CSV export, so all three always agree.
 *
 * Replaces the per-row lookups the list used to do (an event query and a
 * certificate query for every row) with joins, and adds the filters the list
 * was missing: workshop, certificate, and a working "paid" payment type.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SC_EXTRA_FILTER_MAX')) {
    // Extra-field filters run in PHP over the matching rows; this caps that scan.
    define('SC_EXTRA_FILTER_MAX', 20000);
}

/**
 * Read list filters from a request array ($_POST for the list, $_GET for export).
 *
 * Keys keep the names the existing callers already send (status, ticket_status,
 * payment_type …) so certificate-issue and workshop-attendees work unchanged.
 *
 * @return array Normalised filters.
 */
function sc_attendees_read_filters($src) {
    $get = function ($key) use ($src) {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
    };

    $workshop = $get('workshop_id');
    $f = array(
        'event_id'      => absint($get('event_id')),
        'workshop_id'   => $workshop === 'none' ? 'none' : absint($workshop),
        'status'        => in_array($get('status'), array('success', 'pending', 'failed', 'refunded', 'cancelled'), true) ? $get('status') : '',
        'ticket_status' => in_array($get('ticket_status'), array('used', 'unused'), true) ? $get('ticket_status') : '',
        'certificate'   => in_array($get('certificate'), array('yes', 'no'), true) ? $get('certificate') : '',
        'payment_type'  => in_array($get('payment_type'), array('free', 'coupon', 'paid'), true) ? $get('payment_type') : '',
        'coupon_code'   => $get('coupon_code'),
        'search'        => trim($get('search')),
        'orderby'       => in_array($get('orderby'), array('created_at', 'name', 'checked_in_at'), true) ? $get('orderby') : 'created_at',
        'order'         => strtolower($get('order')) === 'asc' ? 'ASC' : 'DESC',
        'extra_filters' => array(),
    );

    if (!empty($src['extra_filters'])) {
        $decoded = json_decode(wp_unslash($src['extra_filters']), true);
        foreach ((array) $decoded as $row) {
            if (!is_array($row) || !isset($row['label'])) {
                continue;
            }
            $value = isset($row['value']) ? $row['value'] : '';
            $value = is_array($value)
                ? array_values(array_filter(array_map('sanitize_text_field', $value), 'strlen'))
                : sanitize_text_field($value);
            if ($value === '' || $value === array()) {
                continue;
            }
            $f['extra_filters'][] = array(
                'label' => sanitize_text_field($row['label']),
                'type'  => isset($row['type']) ? sanitize_key($row['type']) : 'text',
                'value' => $value,
            );
        }
    }

    return $f;
}

/**
 * SQL fragment that is true when the attendee holds a certificate that isn't revoked.
 */
function sc_attendees_certificate_exists_sql() {
    global $wpdb;
    return "EXISTS (SELECT 1 FROM {$wpdb->prefix}sc_certificates c WHERE c.attendee_id = a.id AND (c.status IS NULL OR c.status <> 'revoked'))";
}

/**
 * WHERE clause for the filters. `$skip` leaves out dimensions — the tab counts
 * need every filter except check-in and certificate, which the tabs vary.
 *
 * @return array{0: string, 1: array} SQL with placeholders, and their values.
 */
function sc_attendees_where($f, $skip = array()) {
    global $wpdb;
    $where = array('1=1');
    $values = array();

    if ($f['event_id']) {
        $where[] = 'a.event_id = %d';
        $values[] = $f['event_id'];
    }
    if ($f['workshop_id'] === 'none') {
        $where[] = '(a.workshop_id IS NULL OR a.workshop_id = 0)';
    } elseif ($f['workshop_id']) {
        $where[] = 'a.workshop_id = %d';
        $values[] = $f['workshop_id'];
    }
    if ($f['status']) {
        $where[] = 'a.payment_status = %s';
        $values[] = $f['status'];
    }
    if ($f['ticket_status'] && !in_array('ticket_status', $skip, true)) {
        $where[] = 'a.checked_in = %d';
        $values[] = $f['ticket_status'] === 'used' ? 1 : 0;
    }
    if ($f['certificate'] && !in_array('certificate', $skip, true)) {
        $where[] = ($f['certificate'] === 'no' ? 'NOT ' : '') . sc_attendees_certificate_exists_sql();
    }
    if ($f['payment_type'] === 'paid') {
        // Stored methods include paymob / kashier / woocommerce …; "paid" is anything not free or coupon.
        $where[] = "(a.payment_method IS NOT NULL AND a.payment_method NOT IN ('', 'free', 'coupon'))";
    } elseif ($f['payment_type']) {
        $where[] = 'a.payment_method = %s';
        $values[] = $f['payment_type'];
    }
    if ($f['coupon_code'] !== '') {
        $where[] = 'a.coupon_code LIKE %s';
        $values[] = '%' . $wpdb->esc_like($f['coupon_code']) . '%';
    }
    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $where[] = '(a.name LIKE %s OR a.email LIKE %s OR a.phone LIKE %s OR a.ticket_code LIKE %s)';
        array_push($values, $like, $like, $like, $like);
    }

    return array(implode(' AND ', $where), $values);
}

function sc_attendees_prepare($sql, $values) {
    global $wpdb;
    return $values ? $wpdb->prepare($sql, $values) : $sql;
}

/**
 * Ids (and extra fields) of every row matching the SQL filters, narrowed in PHP
 * by the extra-field filters. Only used when extra-field filters are active.
 */
function sc_attendees_extra_filtered_rows($f, $skip = array()) {
    global $wpdb;
    list($where, $values) = sc_attendees_where($f, $skip);
    $cert = sc_attendees_certificate_exists_sql();
    $sql = "SELECT a.id, a.checked_in, a.extra_fields, {$cert} AS has_certificate
            FROM {$wpdb->prefix}sc_attendees a
            WHERE {$where}
            ORDER BY a.{$f['orderby']} {$f['order']}, a.id DESC
            LIMIT " . (int) SC_EXTRA_FILTER_MAX;
    $rows = $wpdb->get_results(sc_attendees_prepare($sql, $values));

    if (count($rows) >= SC_EXTRA_FILTER_MAX) {
        error_log('[sc_events] Extra-field filter hit the ' . SC_EXTRA_FILTER_MAX . ' attendee cap; results may be incomplete.');
    }

    return array_values(array_filter($rows, function ($row) use ($f) {
        return sc_attendee_matches_extra_filters(sc_normalize_attendee_extra_fields($row->extra_fields), $f['extra_filters']);
    }));
}

/**
 * SELECT … FROM … with the event, workshop and certificate joins, no WHERE.
 */
function sc_attendees_select_sql() {
    global $wpdb;
    $cert = sc_attendees_certificate_exists_sql();
    return "SELECT a.id, a.event_id, a.workshop_id, a.ticket_id, a.name, a.email, a.phone, a.ticket_code,
                   a.ticket_name, a.ticket_price, a.amount_paid, a.payment_status, a.payment_method,
                   a.coupon_code, a.checked_in, a.checked_in_at, a.status, a.order_id, a.transaction_id,
                   a.created_at, a.extra_fields,
                   e.title AS event_title, e.status AS event_status, e.attendance_tracking,
                   w.title AS workshop_title,
                   {$cert} AS has_certificate
            FROM {$wpdb->prefix}sc_attendees a
            LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = a.event_id
            LEFT JOIN {$wpdb->prefix}sc_workshops w ON w.id = a.workshop_id";
}

/**
 * One page of attendees with event, workshop and certificate joined in.
 *
 * @return array{rows: object[], total: int}
 */
function sc_attendees_page($f, $page, $per_page) {
    global $wpdb;
    $offset = ($page - 1) * $per_page;
    $select = sc_attendees_select_sql();

    if ($f['extra_filters']) {
        $matched = sc_attendees_extra_filtered_rows($f);
        $ids = array_map('intval', wp_list_pluck(array_slice($matched, $offset, $per_page), 'id'));
        if (!$ids) {
            return array('rows' => array(), 'total' => count($matched));
        }
        $rows = $wpdb->get_results($select . ' WHERE a.id IN (' . implode(',', $ids) . ')');
        $order = array_flip($ids);
        usort($rows, function ($x, $y) use ($order) {
            return $order[(int) $x->id] - $order[(int) $y->id];
        });
        return array('rows' => $rows, 'total' => count($matched));
    }

    list($where, $values) = sc_attendees_where($f);
    $total = (int) $wpdb->get_var(sc_attendees_prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees a WHERE {$where}", $values));
    $sql = $select . " WHERE {$where} ORDER BY a.{$f['orderby']} {$f['order']}, a.id DESC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($values, array($per_page, $offset))));

    return array('rows' => $rows, 'total' => $total);
}

/**
 * Counts for the list tabs: every filter applies except check-in and certificate.
 *
 * @return array{all: int, checked_in: int, not_checked_in: int, checked_in_no_certificate: int}
 */
function sc_attendees_counts($f) {
    global $wpdb;
    $skip = array('ticket_status', 'certificate');

    if ($f['extra_filters']) {
        $rows = sc_attendees_extra_filtered_rows($f, $skip);
        $in = 0;
        $in_nocert = 0;
        foreach ($rows as $row) {
            if ((int) $row->checked_in) {
                $in++;
                if (!(int) $row->has_certificate) {
                    $in_nocert++;
                }
            }
        }
        $all = count($rows);
    } else {
        list($where, $values) = sc_attendees_where($f, $skip);
        $cert = sc_attendees_certificate_exists_sql();
        $row = $wpdb->get_row(sc_attendees_prepare(
            "SELECT COUNT(*) AS all_rows,
                    COALESCE(SUM(a.checked_in = 1), 0) AS checked_in,
                    COALESCE(SUM(a.checked_in = 1 AND NOT {$cert}), 0) AS in_nocert
             FROM {$wpdb->prefix}sc_attendees a WHERE {$where}",
            $values
        ));
        $all = (int) $row->all_rows;
        $in = (int) $row->checked_in;
        $in_nocert = (int) $row->in_nocert;
    }

    return array(
        'all'                       => $all,
        'checked_in'                => $in,
        'not_checked_in'            => $all - $in,
        'checked_in_no_certificate' => $in_nocert,
    );
}

/**
 * Scan counts and last check-in / check-out for a set of attendees, from the
 * check-in log. The scanner writes checkin/checkout; SC_Checkin::log writes
 * check_in/check_out — both count.
 *
 * @return array attendee_id => {scans, last_in, last_out}
 */
function sc_attendees_scan_summary($ids) {
    global $wpdb;
    $ids = array_filter(array_map('intval', (array) $ids));
    if (!$ids) {
        return array();
    }
    $rows = $wpdb->get_results(
        "SELECT attendee_id,
                COUNT(*) AS scans,
                MAX(CASE WHEN action IN ('checkin', 'check_in', 'manual_checkin') THEN created_at END) AS last_in,
                MAX(CASE WHEN action IN ('checkout', 'check_out') THEN created_at END) AS last_out
         FROM {$wpdb->prefix}sc_checkins
         WHERE attendee_id IN (" . implode(',', $ids) . ')
         GROUP BY attendee_id'
    );
    $out = array();
    foreach ($rows as $row) {
        $out[(int) $row->attendee_id] = $row;
    }
    return $out;
}
