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
 * Delete Coupon
 */
add_action('wp_ajax_delete_coupon', 'sc_delete_coupon');
function sc_delete_coupon() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $coupon_id = intval($_POST['coupon_id']);

    if (wp_delete_post($coupon_id, true)) {
        wp_send_json_success(array('message' => __('Coupon deleted successfully.', 'sc_events')));
    }

    wp_send_json_error(array('message' => __('Failed to delete coupon.', 'sc_events')));
}

/**
 * Bulk Delete Coupons
 */
add_action('wp_ajax_bulk_delete_coupons', 'sc_bulk_delete_coupons');
function sc_bulk_delete_coupons() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (empty($_POST['coupon_ids']) || !is_array($_POST['coupon_ids'])) {
        wp_send_json_error(array('message' => __('No coupons selected.', 'sc_events')));
    }

    $deleted = 0;
    foreach ($_POST['coupon_ids'] as $coupon_id) {
        $coupon_id = intval($coupon_id);
        if ($coupon_id && wp_delete_post($coupon_id, true)) {
            $deleted++;
        }
    }

    if ($deleted > 0) {
        wp_send_json_success(array('message' => sprintf(__('%d coupon(s) deleted.', 'sc_events'), $deleted)));
    }

    wp_send_json_error(array('message' => __('Failed to delete selected coupons.', 'sc_events')));
}

/**
 * Delete All Coupons
 */
add_action('wp_ajax_delete_all_coupons', 'sc_delete_all_coupons');
function sc_delete_all_coupons() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $coupons = get_posts(array(
        'post_type' => 'sc_coupon',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));

    if (empty($coupons)) {
        wp_send_json_error(array('message' => __('No coupons found to delete.', 'sc_events')));
    }

    $total = count($coupons);
    $deleted = 0;

    foreach ($coupons as $coupon_id) {
        if (wp_delete_post($coupon_id, true)) {
            $deleted++;
        }
    }

    if ($deleted > 0) {
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully deleted %d out of %d coupons.', 'sc_events'), $deleted, $total),
            'deleted' => $deleted,
            'total' => $total
        ));
    }

    wp_send_json_error(array('message' => __('Failed to delete coupons.', 'sc_events')));
}

/**
 * Get Coupons with Server-Side Pagination
 */
add_action('wp_ajax_sc_get_coupons_paginated', 'sc_get_coupons_paginated');
function sc_get_coupons_paginated() {
    @set_time_limit(60);
    @ini_set('memory_limit', '256M');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = min(200, max(10, intval($_POST['per_page'] ?? 50)));
    $search = sanitize_text_field($_POST['search'] ?? '');
    $event_id = sanitize_text_field($_POST['event_id'] ?? '');
    $category_id = sanitize_text_field($_POST['category_id'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');

    $offset = ($page - 1) * $per_page;
    $today = current_time('Y-m-d');

    $where = "WHERE p.post_type = 'sc_coupon' AND p.post_status = 'publish'";
    $where_values = array();

    if (!empty($search)) {
        $where .= " AND p.post_title LIKE %s";
        $where_values[] = '%' . $wpdb->esc_like($search) . '%';
    }

    if (!empty($event_id)) {
        $where .= " AND pm_event.meta_value = %s";
        $where_values[] = $event_id;
    }

    if (!empty($category_id)) {
        // Treat coupons without category_id meta as belonging to General (id=1)
        if (intval($category_id) === SC_Coupon_Category::DEFAULT_ID) {
            $where .= " AND (pm_category.meta_value = %s OR pm_category.meta_value IS NULL OR pm_category.meta_value = '')";
        } else {
            $where .= " AND pm_category.meta_value = %s";
        }
        $where_values[] = $category_id;
    }

    if ($status === 'expired') {
        $where .= " AND pm_expiry.meta_value IS NOT NULL AND pm_expiry.meta_value != '' AND pm_expiry.meta_value < %s";
        $where_values[] = $today;
    } elseif ($status === 'active') {
        $where .= " AND (pm_expiry.meta_value IS NULL OR pm_expiry.meta_value = '' OR pm_expiry.meta_value >= %s)";
        $where_values[] = $today;
    } elseif ($status === 'used') {
        $where .= " AND pm_limit.meta_value IS NOT NULL AND pm_limit.meta_value != '' AND pm_limit.meta_value != '0'
                   AND CAST(COALESCE(pm_usage.meta_value, '0') AS UNSIGNED) >= CAST(pm_limit.meta_value AS UNSIGNED)";
    }

    $count_sql = "
        SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'event_id'
        LEFT JOIN {$wpdb->postmeta} pm_category ON p.ID = pm_category.post_id AND pm_category.meta_key = 'category_id'
        LEFT JOIN {$wpdb->postmeta} pm_expiry ON p.ID = pm_expiry.post_id AND pm_expiry.meta_key = 'expiry_date'
        LEFT JOIN {$wpdb->postmeta} pm_limit ON p.ID = pm_limit.post_id AND pm_limit.meta_key = 'usage_limit'
        LEFT JOIN {$wpdb->postmeta} pm_usage ON p.ID = pm_usage.post_id AND pm_usage.meta_key = 'usage_count'
        $where
    ";

    if (!empty($where_values)) {
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values));
    } else {
        $total = (int) $wpdb->get_var($count_sql);
    }

    $pages = ceil($total / $per_page);

    $sql = "
        SELECT
            p.ID as id,
            p.post_title as code,
            pm_type.meta_value as discount_type,
            pm_value.meta_value as discount_value,
            pm_event.meta_value as event_id,
            pm_category.meta_value as category_id,
            pm_limit.meta_value as usage_limit,
            COALESCE(pm_usage.meta_value, '0') as usage_count,
            pm_expiry.meta_value as expiry_date
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'discount_type'
        LEFT JOIN {$wpdb->postmeta} pm_value ON p.ID = pm_value.post_id AND pm_value.meta_key = 'discount_value'
        LEFT JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'event_id'
        LEFT JOIN {$wpdb->postmeta} pm_category ON p.ID = pm_category.post_id AND pm_category.meta_key = 'category_id'
        LEFT JOIN {$wpdb->postmeta} pm_limit ON p.ID = pm_limit.post_id AND pm_limit.meta_key = 'usage_limit'
        LEFT JOIN {$wpdb->postmeta} pm_usage ON p.ID = pm_usage.post_id AND pm_usage.meta_key = 'usage_count'
        LEFT JOIN {$wpdb->postmeta} pm_expiry ON p.ID = pm_expiry.post_id AND pm_expiry.meta_key = 'expiry_date'
        $where
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ";

    $query_values = array_merge($where_values, array($per_page, $offset));
    $coupons = $wpdb->get_results($wpdb->prepare($sql, $query_values));

    $event_ids = array_filter(array_unique(array_column($coupons, 'event_id')));
    $event_titles = array();
    if (!empty($event_ids)) {
        // Sanitize event IDs
        $event_ids = array_map('intval', $event_ids);
        $event_ids = array_filter($event_ids); // Remove zeros

        if (!empty($event_ids)) {
            // First try sc_events custom table - using prepared statement with placeholders
            $sc_events_table = $wpdb->prefix . 'sc_events';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sc_events_table)) === $sc_events_table) {
                $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
                $events = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, title FROM $sc_events_table WHERE id IN ($placeholders)",
                    $event_ids
                ));
                foreach ($events as $event) {
                    $event_titles[$event->id] = $event->title;
                }
            }

            // Fallback to wp_posts for sc_event post type
            $missing_ids = array_diff($event_ids, array_keys($event_titles));
            if (!empty($missing_ids)) {
                $missing_ids = array_values($missing_ids); // Re-index array
                $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
                $events = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type = 'sc_event'",
                    $missing_ids
                ));
                foreach ($events as $event) {
                    $event_titles[$event->ID] = $event->post_title;
                }
            }
        }
    }

    // Fetch all categories once for badge display
    $category_map = array();
    $cat_table = $wpdb->prefix . 'sc_coupon_categories';
    $all_cats = $wpdb->get_results("SELECT id, name, color FROM $cat_table");
    foreach ($all_cats as $c) {
        $category_map[(int) $c->id] = array('name' => $c->name, 'color' => $c->color);
    }

    $result = array();
    foreach ($coupons as $coupon) {
        $is_expired = !empty($coupon->expiry_date) && $coupon->expiry_date < $today;
        $is_used = !empty($coupon->usage_limit) && $coupon->usage_limit != '0'
                   && intval($coupon->usage_count) >= intval($coupon->usage_limit);

        $status = 'active';
        if ($is_expired) $status = 'expired';
        elseif ($is_used) $status = 'used';

        $cat_id = intval($coupon->category_id);
        if ($cat_id <= 0) { $cat_id = SC_Coupon_Category::DEFAULT_ID; }
        $cat_info = isset($category_map[$cat_id]) ? $category_map[$cat_id] : array('name' => 'General', 'color' => '#7c1314');

        $result[] = array(
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type ?: 'free',
            'discount_value' => $coupon->discount_value ?: '0',
            'event_id' => $coupon->event_id ?: 0,
            'event_title' => isset($event_titles[$coupon->event_id]) ? $event_titles[$coupon->event_id] : '',
            'category_id' => $cat_id,
            'category_name' => $cat_info['name'],
            'category_color' => $cat_info['color'],
            'usage_limit' => $coupon->usage_limit ?: 0,
            'usage_count' => intval($coupon->usage_count),
            'expiry_date' => (!empty($coupon->expiry_date) && strtotime($coupon->expiry_date) > 0) ? date('M j, Y', strtotime($coupon->expiry_date)) : '',
            'status' => $status
        );
    }

    wp_send_json_success(array(
        'coupons' => $result,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page,
        'per_page' => $per_page
    ));
}

/**
 * Sync Coupon Usage
 */
add_action('wp_ajax_sc_sync_coupon_usage', 'sc_sync_coupon_usage');
function sc_sync_coupon_usage() {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $coupon_usage = $wpdb->get_results("
        SELECT coupon_code, COUNT(*) as usage_count
        FROM {$wpdb->prefix}sc_attendees
        WHERE coupon_code IS NOT NULL
        AND coupon_code != ''
        AND status = 'active'
        GROUP BY coupon_code
    ");

    $usage_map = array();
    $total_usage = 0;
    foreach ($coupon_usage as $row) {
        $usage_map[$row->coupon_code] = (int) $row->usage_count;
        $total_usage += (int) $row->usage_count;
    }

    $coupons = $wpdb->get_results("
        SELECT ID, post_title as code
        FROM {$wpdb->posts}
        WHERE post_type = 'sc_coupon'
        AND post_status = 'publish'
    ");

    $updated = 0;
    foreach ($coupons as $coupon) {
        $new_usage = isset($usage_map[$coupon->code]) ? $usage_map[$coupon->code] : 0;
        $current_usage = (int) get_post_meta($coupon->ID, 'usage_count', true);

        if ($new_usage !== $current_usage) {
            update_post_meta($coupon->ID, 'usage_count', $new_usage);
            $updated++;
        }
    }

    wp_send_json_success(array(
        'updated' => $updated,
        'total_coupons' => count($coupons),
        'total_usage' => $total_usage,
        'stats' => array('total_usage' => $total_usage)
    ));
}

/**
 * Import Coupons from CSV
 */
add_action('wp_ajax_import_coupons', 'sc_import_coupons');
function sc_import_coupons() {
    // Increase limits for large imports
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $csv_data = isset($_POST['csv_data']) ? sanitize_textarea_field($_POST['csv_data']) : '';
    $target_event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $target_category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 1;
    if ($target_category_id <= 0) { $target_category_id = 1; }

    if (empty($csv_data)) {
        wp_send_json_error(array('message' => __('No CSV data provided.', 'sc_events')));
    }

    $lines = explode("\n", $csv_data);
    $imported = 0;
    $failed = 0;
    $skipped_header = false;

    // Pre-load ALL existing coupon codes in one query (avoids N+1)
    global $wpdb;
    $existing_codes = $wpdb->get_col("SELECT UPPER(post_title) FROM {$wpdb->posts} WHERE post_type = 'sc_coupon' AND post_status IN ('publish','draft')");
    $existing_codes_map = array_flip($existing_codes);

    // Expected CSV format: code,discount_type,discount_value,usage_limit,expiry_date
    $header_patterns = array('code', 'coupon', 'discount_type', 'type');

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = str_getcsv($line);
        if (count($parts) < 1) continue;

        $code = sanitize_text_field($parts[0]);
        if (empty($code)) continue;

        // Skip header row if detected
        if (!$skipped_header) {
            $first_col_lower = strtolower($code);
            foreach ($header_patterns as $pattern) {
                if (strpos($first_col_lower, $pattern) !== false) {
                    $skipped_header = true;
                    continue 2;
                }
            }
        }

        // Check if coupon already exists (from pre-loaded map)
        if (isset($existing_codes_map[strtoupper($code)])) {
            $failed++;
            continue;
        }
        // Add to map to catch duplicates within the CSV itself
        $existing_codes_map[strtoupper($code)] = true;

        $coupon_id = wp_insert_post(array(
            'post_title' => strtoupper($code),
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));

        if ($coupon_id) {
            // Get values from CSV (columns: code, discount_type, discount_value, usage_limit, expiry_date)
            $discount_type = isset($parts[1]) ? sanitize_text_field($parts[1]) : 'percentage';
            $discount_value = isset($parts[2]) ? floatval($parts[2]) : 0;
            $usage_limit = isset($parts[3]) ? intval($parts[3]) : 0;
            $expiry_date = isset($parts[4]) ? sanitize_text_field($parts[4]) : '';

            // Validate discount type
            if (!in_array($discount_type, array('percentage', 'fixed', 'free'))) {
                $discount_type = 'percentage';
            }

            update_post_meta($coupon_id, 'discount_type', $discount_type);
            update_post_meta($coupon_id, 'discount_value', $discount_value);
            update_post_meta($coupon_id, 'event_id', $target_event_id); // Use event_id from form
            update_post_meta($coupon_id, 'category_id', $target_category_id);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            update_post_meta($coupon_id, 'usage_count', 0);
            // Empty or invalid expiry = lifetime (no expiry)
            if (!empty($expiry_date) && strtotime($expiry_date) > 0) {
                update_post_meta($coupon_id, 'expiry_date', $expiry_date);
            }
            update_post_meta($coupon_id, 'is_active', 1);

            $imported++;
        } else {
            $failed++;
        }
    }

    $message = sprintf(__('%d coupons imported successfully.', 'sc_events'), $imported);
    if ($failed > 0) {
        $message .= ' ' . sprintf(__('%d failed (duplicates or errors).', 'sc_events'), $failed);
    }

    wp_send_json_success(array(
        'message' => $message,
        'imported' => $imported,
        'failed' => $failed
    ));
}

/**
 * Save Single Coupon (Create/Update)
 */
add_action('wp_ajax_sc_save_coupon', 'sc_save_coupon_handler');
function sc_save_coupon_handler() {
    // Verify nonce - support both nonce field names
    $nonce_valid = false;
    if (isset($_POST['sc_coupon_nonce']) && wp_verify_nonce($_POST['sc_coupon_nonce'], 'sc_coupon_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        $nonce_valid = true;
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get form data
    $coupon_id = isset($_POST['coupon_id']) ? intval($_POST['coupon_id']) : 0;
    $code = isset($_POST['code']) ? strtoupper(sanitize_text_field($_POST['code'])) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
    $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percentage';
    $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
    $usage_limit = isset($_POST['usage_limit']) ? intval($_POST['usage_limit']) : 0;
    $per_user_limit = isset($_POST['per_user_limit']) ? intval($_POST['per_user_limit']) : 0;
    $min_purchase = isset($_POST['min_purchase']) ? floatval($_POST['min_purchase']) : 0;
    $max_discount = isset($_POST['max_discount']) ? floatval($_POST['max_discount']) : 0;
    $expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 1;
    if ($category_id <= 0) { $category_id = 1; }
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Validate
    if (empty($code)) {
        wp_send_json_error(array('message' => __('Coupon code is required.', 'sc_events')));
    }

    // Check for duplicate code
    $existing = new WP_Query(array(
        'post_type' => 'sc_coupon',
        'title' => $code,
        'posts_per_page' => 1,
        'post__not_in' => $coupon_id ? array($coupon_id) : array()
    ));

    if ($existing->have_posts()) {
        wp_send_json_error(array('message' => __('Coupon code already exists.', 'sc_events')));
    }

    // Create or Update
    $post_data = array(
        'post_title' => $code,
        'post_content' => $description,
        'post_type' => 'sc_coupon',
        'post_status' => $is_active ? 'publish' : 'draft',
    );

    if ($coupon_id) {
        $post_data['ID'] = $coupon_id;
        $result = wp_update_post($post_data);
    } else {
        $post_data['post_author'] = get_current_user_id();
        $result = wp_insert_post($post_data);
        $coupon_id = $result;
    }

    if (is_wp_error($result) || !$result) {
        wp_send_json_error(array('message' => __('Failed to save coupon.', 'sc_events')));
    }

    // Save meta
    update_post_meta($coupon_id, 'coupon_name', $name);
    update_post_meta($coupon_id, 'discount_type', $discount_type);
    update_post_meta($coupon_id, 'discount_value', $discount_value);
    update_post_meta($coupon_id, 'usage_limit', $usage_limit);
    update_post_meta($coupon_id, 'per_user_limit', $per_user_limit);
    update_post_meta($coupon_id, 'min_purchase', $min_purchase);
    update_post_meta($coupon_id, 'max_discount', $max_discount);
    update_post_meta($coupon_id, 'expiry_date', $expiry_date);
    update_post_meta($coupon_id, 'start_date', $start_date);
    update_post_meta($coupon_id, 'end_date', $end_date);
    update_post_meta($coupon_id, 'event_id', $event_id);
    update_post_meta($coupon_id, 'category_id', $category_id);
    update_post_meta($coupon_id, 'is_active', $is_active);

    // Sync to custom table if it has a row for this coupon
    global $wpdb;
    $coupons_table = $wpdb->prefix . 'sc_coupons';
    $wpdb->update($coupons_table, array('category_id' => $category_id), array('wp_post_id' => $coupon_id), array('%d'), array('%d'));

    // Ticket type filter
    $ticket_type_filter = isset($_POST['ticket_type_filter']) && in_array($_POST['ticket_type_filter'], array('all', 'general', 'competitor'))
        ? $_POST['ticket_type_filter'] : 'all';
    update_post_meta($coupon_id, 'ticket_type_filter', $ticket_type_filter);

    wp_send_json_success(array(
        'message' => __('Coupon saved successfully!', 'sc_events'),
        'coupon_id' => $coupon_id,
        'redirect' => home_url('/event-manager-dashboard/coupons/')
    ));
}

/**
 * Bulk Create Coupons
 */
add_action('wp_ajax_sc_bulk_create_coupons', 'sc_bulk_create_coupons_handler');
function sc_bulk_create_coupons_handler() {
    // Verify nonce - support all nonce field names used in forms
    $nonce_valid = false;
    if (isset($_POST['sc_coupon_nonce']) && wp_verify_nonce($_POST['sc_coupon_nonce'], 'sc_coupon_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['sc_bulk_nonce']) && wp_verify_nonce($_POST['sc_bulk_nonce'], 'sc_coupon_action')) {
        $nonce_valid = true;
    } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        $nonce_valid = true;
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get form data
    $count = isset($_POST['count']) ? intval($_POST['count']) : 1;
    $prefix = isset($_POST['prefix']) ? strtoupper(sanitize_text_field($_POST['prefix'])) : '';
    $code_length = isset($_POST['code_length']) ? intval($_POST['code_length']) : 8;
    $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percentage';
    $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
    $usage_limit = isset($_POST['usage_limit']) ? intval($_POST['usage_limit']) : 0;
    $expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 1;
    if ($category_id <= 0) { $category_id = 1; }

    // Validate
    if ($count < 1 || $count > 10000) {
        wp_send_json_error(array('message' => __('Count must be between 1 and 10000.', 'sc_events')));
    }

    $created = 0;
    $coupons = array();

    for ($i = 0; $i < $count; $i++) {
        // Generate random code
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $code_length));
        $code = $prefix . $random;

        // Create coupon
        $coupon_id = wp_insert_post(array(
            'post_title' => $code,
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));

        if ($coupon_id && !is_wp_error($coupon_id)) {
            update_post_meta($coupon_id, 'discount_type', $discount_type);
            update_post_meta($coupon_id, 'discount_value', $discount_value);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            update_post_meta($coupon_id, 'usage_count', 0);
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
            update_post_meta($coupon_id, 'event_id', $event_id);
            update_post_meta($coupon_id, 'category_id', $category_id);
            update_post_meta($coupon_id, 'is_active', 1);

            $created++;
            // Return full coupon data for CSV download
            $coupons[] = array(
                'code' => $code,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'usage_limit' => $usage_limit,
                'expiry_date' => $expiry_date
            );
        }
    }

    if ($created > 0) {
        wp_send_json_success(array(
            'message' => sprintf(__('%d coupons created successfully!', 'sc_events'), $created),
            'created' => $created,
            'coupons' => $coupons
        ));
    }

    wp_send_json_error(array('message' => __('Failed to create coupons.', 'sc_events')));
}

/**
 * Create Coupons (Alternative handler)
 */
add_action('wp_ajax_sc_create_coupons', 'sc_create_coupons_handler');
function sc_create_coupons_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $count = isset($_POST['count']) ? intval($_POST['count']) : 1;
    $prefix = isset($_POST['prefix']) ? strtoupper(sanitize_text_field($_POST['prefix'])) : '';
    $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percentage';
    $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
    $usage_limit = isset($_POST['usage_limit']) ? intval($_POST['usage_limit']) : 0;
    $expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if ($count < 1 || $count > 10000) {
        wp_send_json_error(array('message' => __('Count must be between 1 and 10000.', 'sc_events')));
    }

    $created = 0;
    $codes = array();

    for ($i = 0; $i < $count; $i++) {
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $code = $prefix . $random;

        $coupon_id = wp_insert_post(array(
            'post_title' => $code,
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));

        if ($coupon_id && !is_wp_error($coupon_id)) {
            update_post_meta($coupon_id, 'discount_type', $discount_type);
            update_post_meta($coupon_id, 'discount_value', $discount_value);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            update_post_meta($coupon_id, 'usage_count', 0);
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
            update_post_meta($coupon_id, 'event_id', $event_id);

            $created++;
            $codes[] = $code;
        }
    }

    if ($created > 0) {
        wp_send_json_success(array(
            'message' => sprintf(__('%d coupons created successfully!', 'sc_events'), $created),
            'created' => $created,
            'codes' => $codes
        ));
    }

    wp_send_json_error(array('message' => __('Failed to create coupons.', 'sc_events')));
}
