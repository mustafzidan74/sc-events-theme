<?php
/**
 * Customers — attendee accounts (WordPress subscribers) for the dashboard list.
 *
 * One query pages the accounts; one more aggregates the registrations of that
 * page (matched by user id or email), instead of three queries per row. Rows,
 * tab counts and the CSV export share sc_customers_where().
 *
 * Deleting is limited to accounts that never registered: removing an account
 * that holds registrations would leave tickets pointing at a missing user.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_customers_verify_request($src) {
    $nonce = isset($src['nonce']) ? sanitize_text_field(wp_unslash($src['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

function sc_customers_read_filters($src) {
    $get = function ($key) use ($src) {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
    };
    return array(
        'search'   => trim($get('search')),
        'event_id' => absint($get('event_id')),
        'view'     => in_array($get('view'), array('all', 'registered', 'never'), true) ? $get('view') : 'all',
        'orderby'  => in_array($get('orderby'), array('joined', 'name'), true) ? $get('orderby') : 'joined',
        'order'    => strtolower($get('order')) === 'asc' ? 'ASC' : 'DESC',
    );
}

/**
 * @return array [from_where_sql, values]
 */
function sc_customers_where($f, $with_view = true) {
    global $wpdb;
    $a = $wpdb->prefix . 'sc_attendees';
    $sql = "FROM {$wpdb->users} u
        JOIN {$wpdb->usermeta} caps ON caps.user_id = u.ID AND caps.meta_key = %s AND caps.meta_value LIKE %s
        WHERE 1=1";
    $values = array($wpdb->prefix . 'capabilities', '%"subscriber"%');
    $has = "(EXISTS (SELECT 1 FROM $a x WHERE x.user_id = u.ID) OR EXISTS (SELECT 1 FROM $a y WHERE y.email = u.user_email))";

    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $sql .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s
                  OR EXISTS (SELECT 1 FROM $a s WHERE (s.user_id = u.ID OR s.email = u.user_email) AND s.phone LIKE %s))";
        array_push($values, $like, $like, $like, $like);
    }
    if ($f['event_id']) {
        $sql .= " AND (EXISTS (SELECT 1 FROM $a e WHERE e.user_id = u.ID AND e.event_id = %d) OR EXISTS (SELECT 1 FROM $a e2 WHERE e2.email = u.user_email AND e2.event_id = %d))";
        array_push($values, $f['event_id'], $f['event_id']);
    }
    if ($with_view && $f['view'] === 'registered') {
        $sql .= " AND $has";
    } elseif ($with_view && $f['view'] === 'never') {
        $sql .= " AND NOT $has";
    }
    return array($sql, $values, $has);
}

/**
 * Registration summary per user for a page of accounts.
 *
 * @param array $users rows with ID and user_email
 * @return array user_id => summary
 */
function sc_customers_summaries($users) {
    global $wpdb;
    $out = array();
    if (!$users) {
        return $out;
    }
    $ids = array_map('intval', wp_list_pluck($users, 'ID'));
    $by_email = array();
    foreach ($users as $u) {
        $by_email[strtolower($u->user_email)] = (int) $u->ID;
        $out[(int) $u->ID] = array('registrations' => 0, 'events' => array(), 'checked_in' => 0, 'paid' => 0.0, 'last' => null, 'phone' => '');
    }
    $placeholders = implode(',', array_fill(0, count($by_email), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.user_id, a.email, a.phone, a.event_id, a.checked_in, a.amount_paid, a.payment_status, a.status, a.created_at, e.title AS event_title
         FROM {$wpdb->prefix}sc_attendees a LEFT JOIN {$wpdb->prefix}sc_events e ON e.id = a.event_id
         WHERE a.user_id IN (" . implode(',', $ids) . ") OR a.email IN ($placeholders)
         ORDER BY a.created_at ASC",
        array_keys($by_email)
    ));
    foreach ($rows as $r) {
        $uid = in_array((int) $r->user_id, $ids, true) ? (int) $r->user_id : ($by_email[strtolower((string) $r->email)] ?? 0);
        if (!$uid) {
            continue;
        }
        $s = &$out[$uid];
        $s['registrations']++;
        $s['events'][(int) $r->event_id] = true;
        if ((int) $r->checked_in) {
            $s['checked_in']++;
        }
        if ($r->payment_status === 'success' && $r->status === 'active') {
            $s['paid'] += (float) $r->amount_paid;
        }
        $s['last'] = array('event' => (string) $r->event_title, 'at' => $r->created_at);
        if ($r->phone) {
            $s['phone'] = $r->phone;
        }
        unset($s);
    }
    return $out;
}

add_action('wp_ajax_sc_get_customers_paginated', 'sc_get_customers_list');
function sc_get_customers_list() {
    sc_customers_verify_request($_POST);
    global $wpdb;

    $f = sc_customers_read_filters($_POST);
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 50)));
    list($from, $values, $has) = sc_customers_where($f);

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from", $values));
    $order = $f['orderby'] === 'name' ? 'u.display_name' : 'u.user_registered';
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email, u.user_registered $from ORDER BY $order {$f['order']}, u.ID DESC LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));
    $summaries = sc_customers_summaries($users);

    $rows = array();
    foreach ($users as $u) {
        $s = $summaries[(int) $u->ID];
        $phone = get_user_meta((int) $u->ID, 'billing_phone', true) ?: (get_user_meta((int) $u->ID, 'phone', true) ?: $s['phone']);
        $rows[] = array(
            'id'            => (int) $u->ID,
            'name'          => $u->display_name,
            'email'         => $u->user_email,
            'phone'         => (string) $phone,
            'joined'        => $u->user_registered,
            'registrations' => $s['registrations'],
            'events'        => count($s['events']),
            'checked_in'    => $s['checked_in'],
            'paid'          => $s['paid'],
            'last_event'    => $s['last'] ? $s['last']['event'] : '',
            'last_at'       => $s['last'] ? $s['last']['at'] : null,
        );
    }

    $response = array('rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        list($from_all, $values_all, $has_all) = sc_customers_where($f, false);
        $c = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS all_rows, COALESCE(SUM($has_all), 0) AS registered $from_all", $values_all));
        $response['counts'] = array('all' => (int) $c->all_rows, 'registered' => (int) $c->registered, 'never' => (int) $c->all_rows - (int) $c->registered);
    }
    wp_send_json_success($response);
}

/**
 * Delete accounts that never registered. Anything else is skipped with a reason.
 */
function sc_customers_delete_ids($ids) {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $deleted = 0;
    $has_registrations = 0;
    $not_customer = 0;
    foreach (array_unique(array_filter(array_map('absint', (array) $ids))) as $id) {
        $user = get_userdata($id);
        // Only plain subscriber accounts: never admins, managers, scanners or anyone with extra roles.
        if (!$user || array_values($user->roles) !== array('subscriber')) {
            $not_customer++;
            continue;
        }
        $registered = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}sc_attendees WHERE user_id = %d OR email = %s LIMIT 1",
            $id,
            $user->user_email
        ));
        if ($registered) {
            $has_registrations++;
            continue;
        }
        if (wp_delete_user($id)) {
            $deleted++;
        }
    }
    $message = sprintf(_n('%d account deleted.', '%d accounts deleted.', $deleted, 'sc_events'), $deleted);
    if ($has_registrations) {
        $message .= ' ' . sprintf(_n('%d kept because it has registrations.', '%d kept because they have registrations.', $has_registrations, 'sc_events'), $has_registrations);
    }
    if ($not_customer) {
        $message .= ' ' . sprintf(_n('%d skipped: not a customer account.', '%d skipped: not customer accounts.', $not_customer, 'sc_events'), $not_customer);
    }
    return array('deleted' => $deleted, 'kept' => $has_registrations + $not_customer, 'message' => $message);
}

add_action('wp_ajax_sc_delete_customer', 'sc_delete_customer_account');
function sc_delete_customer_account() {
    sc_customers_verify_request($_POST);
    $result = sc_customers_delete_ids(array($_POST['customer_id'] ?? 0));
    if ($result['deleted']) {
        wp_send_json_success($result);
    }
    wp_send_json_error($result);
}

add_action('wp_ajax_sc_bulk_delete_customers', 'sc_bulk_delete_customer_accounts');
function sc_bulk_delete_customer_accounts() {
    sc_customers_verify_request($_POST);
    wp_send_json_success(sc_customers_delete_ids($_POST['customer_ids'] ?? array()));
}

add_action('wp_ajax_sc_export_customers_csv', 'sc_export_customers_list_csv');
function sc_export_customers_list_csv() {
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce') || !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(esc_html__('Security check failed.', 'sc_events'));
    }
    @set_time_limit(300);
    global $wpdb;
    $f = sc_customers_read_filters($_GET);
    list($from, $values) = sc_customers_where($f);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers-' . current_time('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Name', 'Email', 'Phone', 'Joined', 'Registrations', 'Events', 'Checked in', 'Paid', 'Last event', 'Last registration'));
    $batch = 1000;
    for ($offset = 0; ; $offset += $batch) {
        $users = $wpdb->get_results($wpdb->prepare("SELECT u.ID, u.display_name, u.user_email, u.user_registered $from ORDER BY u.ID ASC LIMIT %d OFFSET %d", array_merge($values, array($batch, $offset))));
        if (!$users) {
            break;
        }
        $summaries = sc_customers_summaries($users);
        foreach ($users as $u) {
            $s = $summaries[(int) $u->ID];
            $phone = get_user_meta((int) $u->ID, 'billing_phone', true) ?: (get_user_meta((int) $u->ID, 'phone', true) ?: $s['phone']);
            $line = array($u->display_name, $u->user_email, $phone, $u->user_registered, $s['registrations'], count($s['events']), $s['checked_in'], $s['paid'], $s['last'] ? $s['last']['event'] : '', $s['last'] ? $s['last']['at'] : '');
            fputcsv($out, function_exists('sc_csv_cell') ? array_map('sc_csv_cell', $line) : $line);
        }
        if (count($users) < $batch) {
            break;
        }
    }
    fclose($out);
    exit;
}

/**
 * Edit a customer account: name, email, phone and optionally a new password.
 *
 * Only plain subscriber accounts. The old handler accepted any user id, so an
 * event manager could change an administrator's email or password.
 */
add_action('wp_ajax_sc_update_customer', 'sc_update_customer_account');
function sc_update_customer_account() {
    sc_customers_verify_request($_POST);
    $in = function ($key) {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    };
    $id = absint($in('customer_id'));
    $user = $id ? get_userdata($id) : null;
    if (!$user || array_values($user->roles) !== array('subscriber')) {
        wp_send_json_error(array('message' => __('Only customer accounts can be edited here.', 'sc_events')));
    }

    $first = sanitize_text_field($in('first_name'));
    $last = sanitize_text_field($in('last_name'));
    $email = sanitize_email($in('email'));
    $phone = sanitize_text_field($in('phone'));
    $password = (string) $in('new_password');

    $errors = array();
    if ($first === '' && $last === '') {
        $errors['first_name'] = __('Enter a name.', 'sc_events');
    }
    if (!is_email($email)) {
        $errors['email'] = __('Enter a valid email address.', 'sc_events');
    } elseif (($other = get_user_by('email', $email)) && (int) $other->ID !== $id) {
        $errors['email'] = __('Another account already uses this email.', 'sc_events');
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors['new_password'] = __('Use at least 8 characters.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $data = array('ID' => $id, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim($first . ' ' . $last), 'user_email' => $email);
    if ($password !== '') {
        $data['user_pass'] = $password;
    }
    $result = wp_update_user($data);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }
    update_user_meta($id, 'billing_phone', $phone);
    update_user_meta($id, 'phone', $phone);
    wp_send_json_success(array('message' => $password !== '' ? __('Account saved and password changed.', 'sc_events') : __('Account saved.', 'sc_events')));
}

add_action('wp_ajax_sc_get_customer_account', 'sc_get_customer_account');
function sc_get_customer_account() {
    sc_customers_verify_request($_POST);
    $user = get_userdata(absint($_POST['customer_id'] ?? 0));
    if (!$user || array_values($user->roles) !== array('subscriber')) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }
    wp_send_json_success(array(
        'first_name' => $user->first_name,
        'last_name'  => $user->last_name,
        'email'      => $user->user_email,
        'phone'      => get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true),
    ));
}
