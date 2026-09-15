<?php
/**
 * Company (exhibitor / B2B) registrations — dashboard list, save, bulk actions,
 * badge email and export.
 *
 * Replaces the older list / add / update / email / export handlers: they saved
 * input with slashes, stripped line breaks from addresses and notes, could not
 * set contact name, booth or sponsorship from the dashboard, emailed links to a
 * badge page that didn't exist, and exported CSV cells unescaped.
 * Company badges are checked in by the attendance scanner (sc_scan_company_badge).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_companies_verify_request($src = null) {
    $src = $src ?? $_POST;
    $nonce = isset($src['nonce']) ? sanitize_text_field(wp_unslash($src['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

function sc_companies_read_filters($src) {
    $get = function ($key) use ($src) {
        return isset($src[$key]) ? sanitize_text_field(wp_unslash($src[$key])) : '';
    };
    return array(
        'search'   => trim($get('search')),
        'event_id' => absint($get('event_id')),
        'view'     => in_array($get('view'), array('all', 'in', 'out', 'unpaid', 'cancelled'), true) ? $get('view') : 'all',
        'orderby'  => in_array($get('orderby'), array('name', 'created', 'checked_in_at'), true) ? $get('orderby') : 'created',
        'order'    => strtolower($get('order')) === 'asc' ? 'ASC' : 'DESC',
    );
}

/**
 * @return array [where_sql, values]
 */
function sc_companies_where($f, $with_view = true) {
    global $wpdb;
    $where = '1=1';
    $values = array();
    if ($f['search'] !== '') {
        $like = '%' . $wpdb->esc_like($f['search']) . '%';
        $where .= ' AND (c.company_name LIKE %s OR c.company_name_ar LIKE %s OR c.contact_name LIKE %s OR c.contact_email LIKE %s OR c.contact_phone LIKE %s OR c.company_code LIKE %s OR c.booth_number LIKE %s)';
        array_push($values, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($f['event_id']) {
        $where .= ' AND c.event_id = %d';
        $values[] = $f['event_id'];
    }
    if ($with_view && $f['view'] !== 'all') {
        $where .= ' AND ' . sc_companies_view_condition($f['view']);
    }
    return array($where, $values);
}

function sc_companies_view_condition($view) {
    $views = array(
        'in'        => "(c.status = 'active' AND c.checked_in = 1)",
        'out'       => "(c.status = 'active' AND c.checked_in = 0)",
        'unpaid'    => "(c.status = 'active' AND c.payment_status <> 'success')",
        'cancelled' => "c.status = 'cancelled'",
    );
    return $views[$view] ?? '1=1';
}

function sc_companies_row($c) {
    return array(
        'id'            => (int) $c->id,
        'code'          => $c->company_code,
        'name'          => $c->company_name,
        'name_ar'       => (string) $c->company_name_ar,
        'logo'          => $c->company_logo ? (wp_get_attachment_image_url((int) $c->company_logo, 'thumbnail') ?: '') : '',
        'contact_name'  => (string) $c->contact_name,
        'email'         => (string) $c->contact_email,
        'phone'         => (string) $c->contact_phone,
        'booth'         => (string) $c->booth_number,
        'sponsorship'   => (string) $c->sponsorship_level,
        'event_id'      => (int) $c->event_id,
        'event_title'   => (string) $c->event_title,
        'payment'       => $c->payment_status,
        'amount'        => (float) $c->amount_paid,
        'status'        => $c->status,
        'checked_in'    => (bool) $c->checked_in,
        'checked_in_at' => $c->checked_in_at,
        'email_sent_at' => $c->email_sent ? $c->email_sent_at : null,
        'created'       => $c->created_at,
        'badge_url'     => home_url('/company-ticket/' . rawurlencode($c->company_code) . '/'),
    );
}

add_action('wp_ajax_sc_get_company_attendees', 'sc_get_companies_list');
function sc_get_companies_list() {
    sc_companies_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $f = sc_companies_read_filters($_POST);
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 50)));
    list($where, $values) = sc_companies_where($f);
    $prep = function ($sql, $vals) use ($wpdb) {
        return $vals ? $wpdb->prepare($sql, $vals) : $sql;
    };

    $total = (int) $wpdb->get_var($prep("SELECT COUNT(*) FROM {$p}sc_company_attendees c WHERE $where", $values));
    $order = array('name' => 'c.company_name', 'created' => 'c.created_at', 'checked_in_at' => 'c.checked_in_at')[$f['orderby']];
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, e.title AS event_title FROM {$p}sc_company_attendees c LEFT JOIN {$p}sc_events e ON e.id = c.event_id WHERE $where ORDER BY $order {$f['order']}, c.id DESC LIMIT %d OFFSET %d",
        array_merge($values, array($per_page, ($page - 1) * $per_page))
    ));

    $response = array('rows' => array_map('sc_companies_row', $rows), 'total' => $total, 'page' => $page, 'per_page' => $per_page);
    if (!empty($_POST['with_counts'])) {
        list($where_all, $values_all) = sc_companies_where($f, false);
        $c = $wpdb->get_row($prep("SELECT COUNT(*) AS all_rows,
            COALESCE(SUM(" . sc_companies_view_condition('in') . "), 0) AS in_rows,
            COALESCE(SUM(" . sc_companies_view_condition('out') . "), 0) AS out_rows,
            COALESCE(SUM(" . sc_companies_view_condition('unpaid') . "), 0) AS unpaid,
            COALESCE(SUM(" . sc_companies_view_condition('cancelled') . "), 0) AS cancelled
            FROM {$p}sc_company_attendees c WHERE $where_all", $values_all));
        $response['counts'] = array('all' => (int) $c->all_rows, 'in' => (int) $c->in_rows, 'out' => (int) $c->out_rows, 'unpaid' => (int) $c->unpaid, 'cancelled' => (int) $c->cancelled);
    }
    wp_send_json_success($response);
}

add_action('wp_ajax_sc_save_company_attendee', 'sc_save_company_attendee');
function sc_save_company_attendee() {
    sc_companies_verify_request();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_company_attendees';
    $in = function ($key, $default = '') {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    };

    $id = absint($in('id'));
    $existing = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)) : null;
    if ($id && !$existing) {
        wp_send_json_error(array('message' => __('Company not found.', 'sc_events')));
    }

    $event_id = absint($in('event_id'));
    $name = sanitize_text_field($in('company_name'));
    $email = trim((string) $in('contact_email'));
    $website = trim((string) $in('website'));
    $errors = array();
    if (!$event_id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id))) {
        $errors['event_id'] = __('Choose the event.', 'sc_events');
    }
    if ($name === '') {
        $errors['company_name'] = __('Enter the company name.', 'sc_events');
    }
    if (!is_email($email)) {
        $errors['contact_email'] = __('Enter a valid email address.', 'sc_events');
    } elseif ($event_id && $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_id = %d AND contact_email = %s AND id <> %d LIMIT 1", $event_id, $email, $id))) {
        $errors['contact_email'] = __('Another company already uses this email for this event.', 'sc_events');
    }
    if ($website !== '' && !preg_match('#^https?://\S+\.\S+#i', $website)) {
        $errors['website'] = __('Enter a full link starting with https://', 'sc_events');
    }
    $ticket_id = absint($in('ticket_id'));
    if ($ticket_id && $event_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_tickets WHERE id = %d AND event_id = %d", $ticket_id, $event_id))) {
        $errors['ticket_id'] = __('That ticket isn’t part of this event.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $social = array();
    foreach ((array) $in('social_media', array()) as $row) {
        $url = esc_url_raw(trim((string) ($row['url'] ?? '')));
        if ($url !== '') {
            $social[] = array('platform' => sanitize_key($row['platform'] ?? 'website') ?: 'website', 'url' => $url);
        }
    }
    $products = array();
    foreach ((array) $in('products', array()) as $row) {
        $pname = sanitize_text_field($row['name'] ?? '');
        if ($pname !== '') {
            $products[] = array('image' => absint($row['image'] ?? 0), 'name' => $pname, 'description' => sanitize_textarea_field($row['description'] ?? ''));
        }
    }
    $sizes = array('1-10', '11-50', '51-200', '201-500', '500+');

    $data = array(
        'event_id'          => $event_id,
        'ticket_id'         => $ticket_id ?: null,
        'company_name'      => $name,
        'company_name_ar'   => sanitize_text_field($in('company_name_ar')),
        'company_logo'      => absint($in('company_logo')) ?: null,
        'industry'          => sanitize_text_field($in('industry')),
        'company_size'      => in_array($in('company_size'), $sizes, true) ? $in('company_size') : null,
        'website'           => esc_url_raw($website),
        'contact_name'      => sanitize_text_field($in('contact_name')),
        'contact_title'     => sanitize_text_field($in('contact_title')),
        'contact_email'     => sanitize_email($email),
        'contact_phone'     => sanitize_text_field($in('contact_phone')),
        'country'           => sanitize_text_field($in('country')),
        'city'              => sanitize_text_field($in('city')),
        'address'           => sanitize_textarea_field($in('address')),
        'booth_number'      => sanitize_text_field($in('booth_number')),
        'sponsorship_level' => sanitize_text_field($in('sponsorship_level')),
        'payment_status'    => in_array($in('payment_status'), array('pending', 'success', 'failed', 'refunded'), true) ? $in('payment_status') : 'success',
        'payment_method'    => sanitize_text_field($in('payment_method')),
        'amount_paid'       => max(0, (float) $in('amount_paid', 0)),
        'status'            => $in('status') === 'cancelled' ? 'cancelled' : 'active',
        'notes'             => sanitize_textarea_field($in('notes')),
        'social_media'      => wp_json_encode($social),
        'products'          => wp_json_encode($products),
        'updated_at'        => current_time('mysql'),
    );

    if ($existing) {
        $code = $existing->company_code;
        $ok = $wpdb->update($table, $data, array('id' => $id)) !== false;
    } else {
        $code = class_exists('SC_Company_Attendee') ? SC_Company_Attendee::generate_company_code() : 'COMP-' . strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $data['company_code'] = $code;
        $data['created_at'] = current_time('mysql');
        $ok = (bool) $wpdb->insert($table, $data);
        $id = (int) $wpdb->insert_id;
    }
    if (!$ok) {
        wp_send_json_error(array('message' => __('Could not save the company.', 'sc_events')));
    }
    // The QR stays in step with the name and event.
    $wpdb->update($table, array('qr_data' => wp_json_encode(array('type' => 'company', 'code' => $code, 'company' => $name, 'event' => $event_id))), array('id' => $id));

    $emailed = false;
    if (!$existing && !empty($in('send_email'))) {
        $emailed = sc_companies_send_badge_email((int) $id);
    }
    wp_send_json_success(array(
        'message'  => $existing ? __('Company saved.', 'sc_events') : ($emailed ? __('Company registered and badge sent.', 'sc_events') : __('Company registered.', 'sc_events')),
        'id'       => $id,
        'code'     => $code,
        'redirect' => $existing ? '' : home_url('/event-manager-dashboard/company-attendee-edit?id=' . $id . '&created=1'),
    ));
}

/**
 * Send the company its badge: WhatsApp with the QR image, and email when email is on.
 * In a batch every message takes its turn in the shared slow lane.
 *
 * @return bool True when something was sent or queued.
 */
function sc_companies_send_badge_email($id, $in_batch = false) {
    if (!function_exists('sc_notify_company_badge')) {
        return false;
    }
    $result = sc_notify_company_badge((int) $id, true, array(
        'email_now'     => !$in_batch,
        'delay_seconds' => $in_batch ? sc_wabot_next_delay(sc_notify_settings()['interval']) : 0,
    ));
    return (bool) ($result['whatsapp'] || $result['email']);
}

add_action('wp_ajax_sc_company_bulk', 'sc_company_bulk');
function sc_company_bulk() {
    sc_companies_verify_request();
    global $wpdb;
    $p = $wpdb->prefix;
    $op = sanitize_key($_POST['op'] ?? '');
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? array()))));
    if (!$ids) {
        wp_send_json_error(array('message' => __('Nothing selected.', 'sc_events')));
    }
    $done = 0;
    $failed = 0;
    switch ($op) {
        case 'check_in':
            foreach ($ids as $id) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT status, checked_in FROM {$p}sc_company_attendees WHERE id = %d", $id));
                if ($row && $row->status === 'active' && !(int) $row->checked_in && SC_Company_Attendee::check_in($id, get_current_user_id())) {
                    $done++;
                }
            }
            $message = sprintf(_n('%d company checked in.', '%d companies checked in.', $done, 'sc_events'), $done);
            break;
        case 'undo':
            foreach ($ids as $id) {
                if (SC_Company_Attendee::check_out($id, get_current_user_id())) {
                    $wpdb->update("{$p}sc_company_attendees", array('checked_in_at' => null, 'checked_in_by' => null), array('id' => $id));
                    $done++;
                }
            }
            $message = sprintf(_n('Check-in undone for %d company.', 'Check-in undone for %d companies.', $done, 'sc_events'), $done);
            break;
        case 'email':
            if (count($ids) > 50) {
                wp_send_json_error(array('message' => __('Send at most 50 badges at a time.', 'sc_events')));
            }
            foreach ($ids as $id) {
                sc_companies_send_badge_email($id, count($ids) > 1) ? $done++ : $failed++;
            }
            $message = sprintf(_n('%d badge sent.', '%d badges sent.', $done, 'sc_events'), $done)
                . ($done > 1 ? ' ' . __('WhatsApp messages go out one by one, about a minute apart.', 'sc_events') : '')
                . ($failed ? ' ' . sprintf(_n('%d could not be sent — cancelled, or no valid WhatsApp number or email.', '%d could not be sent — cancelled, or no valid WhatsApp number or email.', $failed, 'sc_events'), $failed) : '');
            break;
        case 'delete':
            $list = implode(',', $ids);
            $wpdb->query("DELETE FROM {$p}sc_company_checkins WHERE company_attendee_id IN ($list)");
            $done = (int) $wpdb->query("DELETE FROM {$p}sc_company_attendees WHERE id IN ($list)");
            $message = sprintf(_n('%d company deleted.', '%d companies deleted.', $done, 'sc_events'), $done);
            break;
        default:
            wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
    }
    wp_send_json_success(array('message' => $message, 'done' => $done, 'failed' => $failed));
}

add_action('wp_ajax_sc_export_company_csv', 'sc_export_company_csv');
function sc_export_company_csv() {
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce') || !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(esc_html__('Security check failed.', 'sc_events'));
    }
    global $wpdb;
    $p = $wpdb->prefix;
    $f = sc_companies_read_filters($_GET);
    list($where, $values) = sc_companies_where($f);
    $sql = "SELECT c.*, e.title AS event_title FROM {$p}sc_company_attendees c LEFT JOIN {$p}sc_events e ON e.id = c.event_id WHERE $where ORDER BY c.company_name ASC";
    $rows = $wpdb->get_results($values ? $wpdb->prepare($sql, $values) : $sql);

    $cell = function ($v) {
        $v = (string) $v;
        return $v !== '' && preg_match('/^[=+\-@\t\r]/', $v) && !preg_match('/^[+\-][\d\s().]*$/', $v) ? "'" . $v : $v;
    };
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="companies-' . current_time('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Company', 'Company (Arabic)', 'Code', 'Event', 'Contact', 'Title', 'Email', 'Phone', 'Booth', 'Sponsorship', 'Industry', 'Country', 'City', 'Payment', 'Amount', 'Status', 'Checked in', 'Checked in at', 'Registered'));
    foreach ($rows as $c) {
        fputcsv($out, array_map($cell, array(
            $c->company_name, $c->company_name_ar, $c->company_code, $c->event_title, $c->contact_name, $c->contact_title, $c->contact_email, $c->contact_phone,
            $c->booth_number, $c->sponsorship_level, $c->industry, $c->country, $c->city, $c->payment_status, $c->amount_paid, $c->status,
            (int) $c->checked_in ? 'yes' : 'no', $c->checked_in_at, $c->created_at,
        )));
    }
    fclose($out);
    exit;
}
