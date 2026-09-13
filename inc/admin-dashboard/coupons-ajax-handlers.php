<?php
/**
 * Coupons AJAX Handlers
 *
 * All coupon-related AJAX handlers extracted from ajax-handlers.php
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if coupons module is enabled - if not, don't register any AJAX handlers
if (function_exists('sc_is_module_enabled') && !sc_is_module_enabled('coupons')) {
    return;
}

require_once __DIR__ . '/coupons-query.php';

/**
 * Create Discount Coupon (supports bulk generation)
 */
add_action('wp_ajax_create_discount_coupon', 'sc_create_discount_coupon');
function sc_create_discount_coupon() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Prevent duplicate submissions using unique request ID
    $request_id = isset($_POST['request_id']) ? sanitize_text_field($_POST['request_id']) : '';
    $user_id = get_current_user_id();

    if (empty($request_id)) {
        wp_send_json_error(array('message' => __('Invalid request. Please refresh the page and try again.', 'sc_events')));
    }

    $request_key = 'coupon_request_' . $user_id . '_' . md5($request_id);

    if (get_transient($request_key)) {
        wp_send_json_error(array('message' => __('This request was already processed. Please refresh the page.', 'sc_events')));
    }

    set_transient($request_key, true, 300);

    // Parse num_coupons
    if (isset($_POST['num_coupons'])) {
        $num_coupons = is_array($_POST['num_coupons']) ? intval($_POST['num_coupons'][0]) : intval($_POST['num_coupons']);
    } else {
        $num_coupons = 1;
    }

    $coupon_prefix = isset($_POST['coupon_prefix']) ? sanitize_text_field($_POST['coupon_prefix']) : '';
    $discount_type = sanitize_text_field($_POST['discount_type']);
    $discount_value = floatval($_POST['discount_value']);
    $event_id = intval($_POST['event_id']);
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 1;
    if ($category_id <= 0) { $category_id = 1; }
    $usage_limit = intval($_POST['usage_limit']);
    $expiry_date = sanitize_text_field($_POST['expiry_date']);

    $allowed_types = array('percentage', 'fixed');
    if (!in_array($discount_type, $allowed_types, true)) {
        delete_transient($request_key);
        wp_send_json_error(array('message' => __('Invalid discount type selected.', 'sc_events')));
    }

    if ($num_coupons > 100000) {
        delete_transient($request_key);
        wp_send_json_error(array('message' => __('Maximum 100000 coupons can be created at once.', 'sc_events')));
    }

    $created_coupons = array();

    for ($i = 0; $i < $num_coupons; $i++) {
        $unique_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $coupon_code = strtoupper($coupon_prefix) . $unique_code;

        $coupon_id = wp_insert_post(array(
            'post_title' => $coupon_code,
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));

        if ($coupon_id) {
            update_post_meta($coupon_id, 'discount_type', $discount_type);
            update_post_meta($coupon_id, 'discount_value', $discount_value);
            update_post_meta($coupon_id, 'event_id', $event_id);
            update_post_meta($coupon_id, 'category_id', $category_id);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            update_post_meta($coupon_id, 'usage_count', 0);
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);

            $created_coupons[] = array(
                'code' => $coupon_code,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'event_id' => $event_id,
                'usage_limit' => $usage_limit,
                'expiry_date' => $expiry_date
            );
        }
    }

    if (!empty($created_coupons)) {
        $csv_data = "\xEF\xBB\xBF";
        $csv_data .= "Coupon Code,Discount Type,Discount Value,Event ID,Usage Limit,Expiry Date\n";

        foreach ($created_coupons as $coupon) {
            $discount_display = $coupon['discount_type'] === 'percentage'
                ? $coupon['discount_value'] . '%'
                : $coupon['discount_value'] . ' ' . sc_get_currency_symbol();

            $csv_data .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $coupon['code'],
                ucfirst($coupon['discount_type']),
                $discount_display,
                $coupon['event_id'],
                $coupon['usage_limit'] ?: 'Unlimited',
                $coupon['expiry_date'] ?: 'No Expiry'
            );
        }

        delete_transient($request_key);

        wp_send_json_success(array(
            'message' => sprintf(__('%d coupon(s) created successfully! Downloading CSV file...', 'sc_events'), count($created_coupons)),
            'csv_data' => $csv_data,
            'count' => count($created_coupons),
            'auto_download' => true
        ));
    }

    delete_transient($request_key);
    wp_send_json_error(array('message' => __('Failed to create coupons.', 'sc_events')));
}

/**
 * Export Coupons to CSV
 */
add_action('wp_ajax_export_coupons', 'sc_events_export_coupons');
function sc_events_export_coupons() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_die('Invalid request (nonce).');
    }

    if (!current_user_can('manage_options') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die('You do not have permission to export coupons.');
    }

    $event_filter = isset($_POST['export_event_id']) ? sanitize_text_field($_POST['export_event_id']) : 'all';

    $args = array(
        'post_type' => 'sc_coupon',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    );

    if ($event_filter !== 'all') {
        $event_id = absint($event_filter);
        if ($event_id > 0) {
            $args['meta_query'] = array(
                array(
                    'key' => 'event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            );
        }
    }

    $coupons = get_posts($args);

    $filename = 'coupons-export-' . date('Y-m-d-H-i-s') . '.csv';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    fputcsv($output, array('Coupon Code', 'Discount', 'Event', 'Usage', 'Expiry Date', 'Status'));

    if ($coupons) {
        foreach ($coupons as $coupon) {
            $coupon_id = $coupon->ID;
            $code = $coupon->post_title;

            $discount_type = get_post_meta($coupon_id, 'discount_type', true);
            $discount_value = get_post_meta($coupon_id, 'discount_value', true);
            $event_id = get_post_meta($coupon_id, 'event_id', true);
            $usage_limit = get_post_meta($coupon_id, 'usage_limit', true);
            $usage_count = get_post_meta($coupon_id, 'usage_count', true);
            $expiry_date = get_post_meta($coupon_id, 'expiry_date', true);

            if ($discount_type === 'percentage') {
                $discount_text = $discount_value . '%';
            } elseif ($discount_type === 'fixed') {
                $discount_text = $discount_value . ' ' . sc_get_currency_symbol();
            } else {
                $discount_text = '100% (Free)';
            }

            if ($event_id == 0 || $event_id === '0' || $event_id === '') {
                $event_label = 'All Events';
            } else {
                $event_post = get_post($event_id);
                $event_label = $event_post ? $event_post->post_title : 'Event Deleted';
            }

            $usage_limit = $usage_limit !== '' ? (int) $usage_limit : 0;
            $usage_count = $usage_count !== '' ? (int) $usage_count : 0;
            $usage_text = $usage_count . ' / ' . ($usage_limit ? $usage_limit : '∞');

            $expiry_text = (!empty($expiry_date) && strtotime($expiry_date) > 0) ? date('Y-m-d', strtotime($expiry_date)) : 'No Expiry';

            $is_expired = $expiry_date && strtotime($expiry_date) < time();
            $is_limit_reached = $usage_limit && $usage_count >= $usage_limit;

            if ($is_expired) {
                $status_text = 'Expired';
            } elseif ($is_limit_reached) {
                $status_text = 'Limit Reached';
            } else {
                $status_text = 'Active';
            }

            fputcsv($output, array($code, $discount_text, $event_label, $usage_text, $expiry_text, $status_text));
        }
    }

    fclose($output);
    exit;
}

/**
 * Export Coupons via AJAX (returns JSON with CSV data)
 */
add_action('wp_ajax_export_coupons_ajax', 'sc_events_export_coupons_ajax');
function sc_events_export_coupons_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Invalid request (nonce).'));
    }

    if (!current_user_can('manage_options') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'You do not have permission to export coupons.'));
    }

    $event_filter = isset($_POST['export_event_id']) ? sanitize_text_field($_POST['export_event_id']) : 'all';
    $status_filter = isset($_POST['export_status']) ? sanitize_text_field($_POST['export_status']) : 'all';

    $args = array(
        'post_type' => 'sc_coupon',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    );

    $meta_query = array();

    if ($event_filter !== 'all') {
        $event_id = absint($event_filter);
        if ($event_id > 0) {
            $meta_query[] = array(
                'key' => 'event_id',
                'value' => $event_id,
                'compare' => '=',
            );
        }
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $coupons = get_posts($args);

    // Filter by status in PHP (since status is computed)
    $today = current_time('Y-m-d');
    if ($status_filter !== 'all') {
        $coupons = array_filter($coupons, function($coupon) use ($status_filter, $today) {
            $expiry_date = get_post_meta($coupon->ID, 'expiry_date', true);
            $usage_limit = get_post_meta($coupon->ID, 'usage_limit', true);
            $usage_count = get_post_meta($coupon->ID, 'usage_count', true);

            $is_expired = $expiry_date && strtotime($expiry_date) < strtotime($today);
            $is_used = $usage_limit && $usage_count >= $usage_limit;

            if ($status_filter === 'expired') return $is_expired;
            if ($status_filter === 'used') return $is_used && !$is_expired;
            if ($status_filter === 'active') return !$is_expired && !$is_used;

            return true;
        });
    }

    $csv_data = "\xEF\xBB\xBF";
    $headers = array('Coupon Code', 'Discount', 'Event', 'Usage', 'Expiry Date', 'Status');
    $csv_data .= '"' . implode('","', $headers) . '"' . "\n";

    if ($coupons) {
        foreach ($coupons as $coupon) {
            $coupon_id = $coupon->ID;
            $code = $coupon->post_title;

            $discount_type = get_post_meta($coupon_id, 'discount_type', true);
            $discount_value = get_post_meta($coupon_id, 'discount_value', true);
            $event_id = get_post_meta($coupon_id, 'event_id', true);
            $usage_limit = get_post_meta($coupon_id, 'usage_limit', true);
            $usage_count = get_post_meta($coupon_id, 'usage_count', true);
            $expiry_date = get_post_meta($coupon_id, 'expiry_date', true);

            if ($discount_type === 'percentage') {
                $discount_text = $discount_value . '%';
            } elseif ($discount_type === 'fixed') {
                $discount_text = $discount_value . ' ' . sc_get_currency_symbol();
            } else {
                $discount_text = '100% (Free)';
            }

            if ($event_id == 0 || $event_id === '0' || $event_id === '') {
                $event_label = 'All Events';
            } else {
                $event_post = get_post($event_id);
                $event_label = $event_post ? $event_post->post_title : 'Event Deleted';
            }

            $usage_limit = $usage_limit !== '' ? (int) $usage_limit : 0;
            $usage_count = $usage_count !== '' ? (int) $usage_count : 0;
            $usage_text = $usage_count . ' / ' . ($usage_limit ? $usage_limit : '∞');

            $expiry_text = (!empty($expiry_date) && strtotime($expiry_date) > 0) ? date('Y-m-d', strtotime($expiry_date)) : 'No Expiry';

            $is_expired = $expiry_date && strtotime($expiry_date) < time();
            $is_limit_reached = $usage_limit && $usage_count >= $usage_limit;

            if ($is_expired) {
                $status_text = 'Expired';
            } elseif ($is_limit_reached) {
                $status_text = 'Limit Reached';
            } else {
                $status_text = 'Active';
            }

            $row = array($code, $discount_text, $event_label, $usage_text, $expiry_text, $status_text);
            $csv_data .= '"' . implode('","', array_map(function($val) {
                return str_replace('"', '""', $val);
            }, $row)) . '"' . "\n";
        }
    }

    $filename = 'coupons-export-' . date('Y-m-d-H-i-s') . '.csv';

    wp_send_json_success(array(
        'csv' => $csv_data,
        'filename' => $filename,
        'count' => count($coupons)
    ));
}

/**
 * Shared request check for the coupon handlers below.
 */
function sc_coupons_verify_request() {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }
}

/**
 * Keep only ids that really are coupons — the delete handlers used to pass any
 * post id straight to wp_delete_post().
 */
function sc_coupons_only_ids($ids) {
    global $wpdb;
    $ids = array_filter(array_map('absint', (array) $ids));
    if (!$ids) {
        return array();
    }
    return array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND ID IN (" . implode(',', $ids) . ')'));
}

/**
 * Delete Coupon
 */
add_action('wp_ajax_delete_coupon', 'sc_delete_coupon');
function sc_delete_coupon() {
    sc_coupons_verify_request();

    $ids = sc_coupons_only_ids(array($_POST['coupon_id'] ?? 0));
    if ($ids && wp_delete_post($ids[0], true)) {
        sc_coupons_bump_cache();
        wp_send_json_success(array('message' => __('Coupon deleted.', 'sc_events')));
    }
    wp_send_json_error(array('message' => __('Failed to delete coupon.', 'sc_events')));
}

/**
 * Bulk Delete Coupons
 */
add_action('wp_ajax_bulk_delete_coupons', 'sc_bulk_delete_coupons');
function sc_bulk_delete_coupons() {
    sc_coupons_verify_request();

    $deleted = 0;
    foreach (sc_coupons_only_ids($_POST['coupon_ids'] ?? array()) as $coupon_id) {
        if (wp_delete_post($coupon_id, true)) {
            $deleted++;
        }
    }
    sc_coupons_bump_cache();
    if ($deleted > 0) {
        wp_send_json_success(array('message' => sprintf(__('%d coupon(s) deleted.', 'sc_events'), $deleted)));
    }
    wp_send_json_error(array('message' => __('No coupons selected.', 'sc_events')));
}

// "Delete all coupons" (every code on the site in one click) is no longer offered.
// sc_delete_all_coupons() stays defined for reference but is not hooked.
function sc_delete_all_coupons() {
    wp_send_json_error(array('message' => __('Not available.', 'sc_events')));
}

/**
 * Coupons list: rows for one page, plus tab counts when asked.
 */
add_action('wp_ajax_sc_get_coupons_paginated', 'sc_get_coupons_paginated');
function sc_get_coupons_paginated() {
    sc_coupons_verify_request();

    $f = sc_coupons_read_filters($_POST);
    $page = max(1, absint($_POST['page'] ?? 1));
    $per_page = min(200, max(10, absint($_POST['per_page'] ?? 25)));

    $counts = sc_coupons_counts($f);
    $total = $counts[$f['view']];
    $rows = sc_coupons_rows(sc_coupons_page_ids($f, $per_page, ($page - 1) * $per_page));

    $response = array(
        'rows'     => $rows,
        'coupons'  => $rows,
        'total'    => $total,
        'pages'    => (int) ceil($total / $per_page),
        'page'     => $page,
        'per_page' => $per_page,
    );
    if (!empty($_POST['with_counts'])) {
        $response['counts'] = $counts;
    }
    wp_send_json_success($response);
}

/**
 * Export the coupons matching the list filters as CSV (streamed in batches).
 */
add_action('wp_ajax_sc_export_coupons_csv', 'sc_export_coupons_csv');
function sc_export_coupons_csv() {
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce') || !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(esc_html__('Security check failed.', 'sc_events'));
    }
    @set_time_limit(300);
    $f = sc_coupons_read_filters($_GET);
    $f['orderby'] = 'created';
    $f['order'] = 'ASC';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="coupons-' . current_time('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Code', 'Status', 'Discount type', 'Discount', 'Event', 'Category', 'Ticket type', 'Uses', 'Usage limit', 'Expires', 'Used by', 'Note', 'Created'));
    $batch = 2000;
    for ($offset = 0; ; $offset += $batch) {
        $ids = sc_coupons_page_ids($f, $batch, $offset);
        if (!$ids) {
            break;
        }
        foreach (sc_coupons_rows($ids, true) as $r) {
            fputcsv($out, array_map('sc_csv_cell', array(
                $r['code'], $r['state'], $r['discount_type'], $r['discount_value'], $r['event_title'] ?: 'All events', $r['category'],
                $r['ticket_filter'], $r['usage_count'], $r['usage_limit'] ?: 'Unlimited', $r['expiry_date'],
                implode('; ', wp_list_pluck($r['used_by'], 'name')) . ($r['used_by_total'] > 3 ? ' +' . ($r['used_by_total'] - 3) : ''),
                $r['note'], $r['created'],
            )));
        }
        if (count($ids) < $batch) {
            break;
        }
    }
    fclose($out);
    exit;
}

/**
 * Bulk actions from the list: activate, deactivate, move to category, delete.
 */
add_action('wp_ajax_sc_bulk_coupons', 'sc_bulk_coupons');
function sc_bulk_coupons() {
    sc_coupons_verify_request();
    global $wpdb;

    $op = sanitize_key($_POST['op'] ?? '');
    $ids = sc_coupons_only_ids($_POST['ids'] ?? array());
    if (!$ids) {
        wp_send_json_error(array('message' => __('No coupons selected.', 'sc_events')));
    }
    $in = implode(',', $ids);

    switch ($op) {
        case 'activate':
        case 'deactivate':
            $status = $op === 'activate' ? 'publish' : 'draft';
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_status = %s WHERE ID IN ($in)", $status));
            foreach ($ids as $id) {
                update_post_meta($id, 'is_active', $op === 'activate' ? 1 : 0);
                clean_post_cache($id);
            }
            $message = $op === 'activate'
                ? sprintf(_n('%d coupon activated.', '%d coupons activated.', count($ids), 'sc_events'), count($ids))
                : sprintf(_n('%d coupon deactivated — it can no longer be used.', '%d coupons deactivated — they can no longer be used.', count($ids), 'sc_events'), count($ids));
            break;
        case 'category':
            $category = SC_Coupon_Category::get(absint($_POST['category_id'] ?? 0));
            if (!$category) {
                wp_send_json_error(array('message' => __('Choose a category.', 'sc_events')));
            }
            foreach ($ids as $id) {
                update_post_meta($id, 'category_id', (int) $category->id);
            }
            $message = sprintf(_n('%1$d coupon moved to %2$s.', '%1$d coupons moved to %2$s.', count($ids), 'sc_events'), count($ids), $category->name);
            break;
        case 'delete':
            $deleted = 0;
            foreach ($ids as $id) {
                if (wp_delete_post($id, true)) {
                    $deleted++;
                }
            }
            $message = sprintf(_n('%d coupon deleted.', '%d coupons deleted.', $deleted, 'sc_events'), $deleted);
            break;
        default:
            wp_send_json_error(array('message' => __('Unknown action.', 'sc_events')));
    }
    sc_coupons_bump_cache();
    wp_send_json_success(array('message' => $message));
}

/**
 * Recount uses: set each coupon's usage_count to the active attendees holding its code.
 */
add_action('wp_ajax_sc_sync_coupon_usage', 'sc_sync_coupon_usage');
function sc_sync_coupon_usage() {
    @set_time_limit(300);
    sc_coupons_verify_request();
    global $wpdb;

    $usage = array();
    foreach ($wpdb->get_results("SELECT UPPER(coupon_code) AS code, COUNT(*) AS n FROM {$wpdb->prefix}sc_attendees WHERE coupon_code IS NOT NULL AND coupon_code <> '' AND status = 'active' GROUP BY UPPER(coupon_code)") as $row) {
        $usage[$row->code] = (int) $row->n;
    }
    // One query for every coupon's current count instead of a get_post_meta() per coupon.
    $coupons = $wpdb->get_results("SELECT p.ID, UPPER(p.post_title) AS code, m.meta_value AS uses FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'usage_count'
        WHERE p.post_type = 'sc_coupon' AND p.post_status IN ('publish', 'draft')");

    $updated = 0;
    foreach ($coupons as $c) {
        $new = $usage[$c->code] ?? 0;
        if ($c->uses === null || (int) $c->uses !== $new) {
            update_post_meta((int) $c->ID, 'usage_count', $new);
            $updated++;
        }
    }
    sc_coupons_bump_cache();
    wp_send_json_success(array(
        'message'       => $updated
            ? sprintf(_n('Uses recounted: %d coupon corrected.', 'Uses recounted: %d coupons corrected.', $updated, 'sc_events'), $updated)
            : __('Uses recounted: every coupon was already right.', 'sc_events'),
        'updated'       => $updated,
        'total_coupons' => count($coupons),
        'total_usage'   => array_sum($usage),
    ));
}

/**
 * Discount settings shared by save, generate and import.
 * "free" is stored as 100% — registration treats any non-percentage type as a fixed amount.
 */
function sc_coupon_read_discount($type, $value) {
    $type = sanitize_key($type);
    if ($type === 'free') {
        return array('percentage', 100.0);
    }
    $type = $type === 'fixed' ? 'fixed' : 'percentage';
    $value = max(0, (float) $value);
    return array($type, $type === 'percentage' ? min(100, $value) : $value);
}

/**
 * Is a code already taken (active or inactive)?
 */
function sc_coupon_code_taken($code, $exclude_id = 0) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND post_status IN ('publish', 'draft') AND post_title = %s AND ID <> %d LIMIT 1",
        $code,
        (int) $exclude_id
    ));
}

/**
 * Import Coupons from CSV — columns: code, discount_type, discount_value, usage_limit, expiry_date
 */
add_action('wp_ajax_import_coupons', 'sc_import_coupons');
function sc_import_coupons() {
    @set_time_limit(300);
    sc_coupons_verify_request();
    global $wpdb;

    $csv_data = isset($_POST['csv_data']) ? sanitize_textarea_field(wp_unslash($_POST['csv_data'])) : '';
    $event_id = absint($_POST['event_id'] ?? 0);
    $category_id = absint($_POST['category_id'] ?? 0) ?: SC_Coupon_Category::DEFAULT_ID;
    if ($csv_data === '') {
        wp_send_json_error(array('message' => __('Choose a CSV file first.', 'sc_events'), 'errors' => array('csv_file' => __('Choose a CSV file first.', 'sc_events'))));
    }

    $taken = array_flip($wpdb->get_col("SELECT UPPER(post_title) FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND post_status IN ('publish', 'draft')"));
    $imported = 0;
    $duplicates = 0;
    $invalid = 0;
    wp_defer_term_counting(true);

    foreach (preg_split('/\r\n|\r|\n/', $csv_data) as $i => $line) {
        $parts = str_getcsv(trim($line));
        $code = strtoupper(preg_replace('/\s+/', '', (string) ($parts[0] ?? '')));
        if ($code === '') {
            continue;
        }
        if ($i === 0 && preg_match('/code|coupon/i', $code)) {
            continue; // header row
        }
        if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            $invalid++;
            continue;
        }
        if (isset($taken[$code])) {
            $duplicates++;
            continue;
        }
        $taken[$code] = true;

        $coupon_id = wp_insert_post(array('post_title' => $code, 'post_type' => 'sc_coupon', 'post_status' => 'publish', 'post_author' => get_current_user_id()));
        if (!$coupon_id || is_wp_error($coupon_id)) {
            $invalid++;
            continue;
        }
        list($type, $value) = sc_coupon_read_discount($parts[1] ?? 'free', $parts[2] ?? 100);
        $expiry = sanitize_text_field($parts[4] ?? '');
        update_post_meta($coupon_id, 'discount_type', $type);
        update_post_meta($coupon_id, 'discount_value', $value);
        update_post_meta($coupon_id, 'event_id', $event_id);
        update_post_meta($coupon_id, 'category_id', $category_id);
        update_post_meta($coupon_id, 'usage_limit', isset($parts[3]) && $parts[3] !== '' ? absint($parts[3]) : 1);
        update_post_meta($coupon_id, 'usage_count', 0);
        update_post_meta($coupon_id, 'ticket_type_filter', 'all');
        update_post_meta($coupon_id, 'is_active', 1);
        if ($expiry !== '' && strtotime($expiry)) {
            update_post_meta($coupon_id, 'expiry_date', gmdate('Y-m-d', strtotime($expiry)));
        }
        $imported++;
    }
    wp_defer_term_counting(false);
    sc_coupons_bump_cache();

    $message = sprintf(_n('%d coupon imported.', '%d coupons imported.', $imported, 'sc_events'), $imported);
    if ($duplicates) {
        $message .= ' ' . sprintf(_n('%d skipped because the code already exists.', '%d skipped because the codes already exist.', $duplicates, 'sc_events'), $duplicates);
    }
    if ($invalid) {
        $message .= ' ' . sprintf(_n('%d line had an invalid code.', '%d lines had invalid codes.', $invalid, 'sc_events'), $invalid);
    }
    wp_send_json_success(array('message' => $message, 'imported' => $imported, 'failed' => $duplicates + $invalid));
}

/**
 * Save Single Coupon (create / update).
 *
 * Writes only the settings registration enforces (code, active, discount, event,
 * ticket type, usage limit, expiry) plus category and note. Older keys such as
 * per_user_limit or min_purchase are never checked anywhere and are left as they are.
 */
add_action('wp_ajax_sc_save_coupon', 'sc_save_coupon_handler');
function sc_save_coupon_handler() {
    sc_coupons_verify_request();
    global $wpdb;

    $in = function ($key, $default = '') {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    };
    $coupon_id = absint($in('coupon_id', 0));
    $existing = $coupon_id ? get_post($coupon_id) : null;
    if ($coupon_id && (!$existing || $existing->post_type !== 'sc_coupon')) {
        wp_send_json_error(array('message' => __('Coupon not found.', 'sc_events')));
    }

    $code = strtoupper(preg_replace('/\s+/', '', sanitize_text_field($in('code'))));
    list($type, $value) = sc_coupon_read_discount($in('discount_type', 'percentage'), $in('discount_value', 0));
    $event_id = absint($in('event_id', 0));
    $category_id = absint($in('category_id', 0)) ?: SC_Coupon_Category::DEFAULT_ID;
    $ticket_filter = in_array($in('ticket_type_filter'), array('general', 'competitor'), true) ? $in('ticket_type_filter') : 'all';
    $usage_limit = absint($in('usage_limit', 0));
    $expiry = sanitize_text_field($in('expiry_date'));
    $is_active = !empty($in('is_active', '1'));
    $uses = $existing ? (int) get_post_meta($coupon_id, 'usage_count', true) : 0;

    $errors = array();
    if ($code === '') {
        $errors['code'] = __('Enter a code.', 'sc_events');
    } elseif (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
        $errors['code'] = __('Use 3–50 letters, numbers, dashes or underscores.', 'sc_events');
    } elseif (sc_coupon_code_taken($code, $coupon_id)) {
        $errors['code'] = __('Another coupon already uses this code.', 'sc_events');
    } elseif ($existing && $uses > 0 && $code !== $existing->post_title) {
        $errors['code'] = __('This code has already been used, so it can’t be renamed — registrations keep the old code.', 'sc_events');
    }
    if (sanitize_key($in('discount_type')) !== 'free' && $value <= 0) {
        $errors['discount_value'] = __('Enter the discount.', 'sc_events');
    }
    if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
        $errors['expiry_date'] = __('Enter a valid date.', 'sc_events');
    }
    if ($event_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id))) {
        $errors['event_id'] = __('That event no longer exists.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    $post = array(
        'post_title'   => $code,
        'post_content' => sanitize_textarea_field($in('note')),
        'post_type'    => 'sc_coupon',
        'post_status'  => $is_active ? 'publish' : 'draft',
    );
    if ($existing) {
        $post['ID'] = $coupon_id;
        $result = wp_update_post($post, true);
    } else {
        $post['post_author'] = get_current_user_id();
        $result = wp_insert_post($post, true);
        $coupon_id = is_wp_error($result) ? 0 : (int) $result;
    }
    if (is_wp_error($result) || !$result) {
        wp_send_json_error(array('message' => __('Failed to save coupon.', 'sc_events')));
    }

    update_post_meta($coupon_id, 'discount_type', $type);
    update_post_meta($coupon_id, 'discount_value', $value);
    update_post_meta($coupon_id, 'event_id', $event_id);
    update_post_meta($coupon_id, 'category_id', $category_id);
    update_post_meta($coupon_id, 'ticket_type_filter', $ticket_filter);
    update_post_meta($coupon_id, 'usage_limit', $usage_limit);
    update_post_meta($coupon_id, 'is_active', $is_active ? 1 : 0);
    if (!$existing) {
        update_post_meta($coupon_id, 'usage_count', 0);
    }
    if ($expiry !== '') {
        update_post_meta($coupon_id, 'expiry_date', $expiry);
    } else {
        delete_post_meta($coupon_id, 'expiry_date');
    }
    sc_coupons_bump_cache();

    wp_send_json_success(array(
        'message'   => $existing ? __('Coupon saved.', 'sc_events') : __('Coupon created.', 'sc_events'),
        'coupon_id' => $coupon_id,
        'code'      => $code,
        'redirect'  => $existing ? '' : home_url('/event-manager-dashboard/coupon-edit?id=' . $coupon_id . '&created=1'),
    ));
}

/**
 * Generate a batch of random codes with the same settings.
 */
add_action('wp_ajax_sc_bulk_create_coupons', 'sc_bulk_create_coupons_handler');
function sc_bulk_create_coupons_handler() {
    @set_time_limit(600);
    sc_coupons_verify_request();
    global $wpdb;

    $in = function ($key, $default = '') {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    };
    $count = absint($in('count', 0));
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $in('prefix')));
    $length = absint($in('code_length', 8));
    list($type, $value) = sc_coupon_read_discount($in('discount_type', 'free'), $in('discount_value', 100));
    $event_id = absint($in('event_id', 0));
    $category_id = absint($in('category_id', 0)) ?: SC_Coupon_Category::DEFAULT_ID;
    $ticket_filter = in_array($in('ticket_type_filter'), array('general', 'competitor'), true) ? $in('ticket_type_filter') : 'all';
    $usage_limit = absint($in('usage_limit', 1));
    $expiry = sanitize_text_field($in('expiry_date'));

    $errors = array();
    if ($count < 1 || $count > 10000) {
        $errors['count'] = __('Generate between 1 and 10,000 codes at a time.', 'sc_events');
    }
    if (strlen($prefix) > 20) {
        $errors['prefix'] = __('Keep the prefix to 20 characters.', 'sc_events');
    }
    if ($length < 4 || $length > 20) {
        $errors['code_length'] = __('Random part: 4–20 characters.', 'sc_events');
    }
    if (sanitize_key($in('discount_type')) !== 'free' && $value <= 0) {
        $errors['discount_value'] = __('Enter the discount.', 'sc_events');
    }
    if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
        $errors['expiry_date'] = __('Enter a valid date.', 'sc_events');
    }
    if ($errors) {
        wp_send_json_error(array('message' => reset($errors), 'errors' => $errors));
    }

    // Letters and digits that can't be confused when read aloud or typed from paper.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $taken = array_flip($wpdb->get_col($wpdb->prepare(
        "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND post_title LIKE %s",
        $wpdb->esc_like($prefix) . '%'
    )));

    wp_defer_term_counting(true);
    wp_suspend_cache_addition(true);
    $codes = array();
    $attempts = 0;
    while (count($codes) < $count && $attempts < $count * 20) {
        $attempts++;
        $code = $prefix;
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        if (isset($taken[$code])) {
            continue;
        }
        $taken[$code] = true;

        $coupon_id = wp_insert_post(array('post_title' => $code, 'post_type' => 'sc_coupon', 'post_status' => 'publish', 'post_author' => get_current_user_id()));
        if (!$coupon_id || is_wp_error($coupon_id)) {
            continue;
        }
        update_post_meta($coupon_id, 'discount_type', $type);
        update_post_meta($coupon_id, 'discount_value', $value);
        update_post_meta($coupon_id, 'usage_limit', $usage_limit);
        update_post_meta($coupon_id, 'usage_count', 0);
        update_post_meta($coupon_id, 'event_id', $event_id);
        update_post_meta($coupon_id, 'category_id', $category_id);
        update_post_meta($coupon_id, 'ticket_type_filter', $ticket_filter);
        update_post_meta($coupon_id, 'is_active', 1);
        if ($expiry !== '') {
            update_post_meta($coupon_id, 'expiry_date', $expiry);
        }
        $codes[] = $code;
    }
    wp_suspend_cache_addition(false);
    wp_defer_term_counting(false);
    sc_coupons_bump_cache();

    if (!$codes) {
        wp_send_json_error(array('message' => __('No codes could be created. Try a longer random part.', 'sc_events')));
    }
    wp_send_json_success(array(
        'message' => sprintf(_n('%d code generated.', '%d codes generated.', count($codes), 'sc_events'), count($codes)),
        'created' => count($codes),
        'codes'   => $codes,
    ));
}

/**
 * Create Coupons (older alternative entry point) — same as generating codes.
 */
add_action('wp_ajax_sc_create_coupons', 'sc_create_coupons_handler');
function sc_create_coupons_handler() {
    sc_bulk_create_coupons_handler();
}
