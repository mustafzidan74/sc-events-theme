<?php
/**
 * Dashboard AJAX Handlers
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Event Manager Login
 */
add_action('wp_ajax_nopriv_event_manager_login', 'sc_event_manager_login_handler');
function sc_event_manager_login_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'event_manager_login')) {
        wp_send_json_error(array(
            'message' => __('Security check failed. Please refresh and try again.', 'sc_events')
        ));
    }

    $user_login = sanitize_text_field($_POST['user_login']);
    $user_password = $_POST['user_password'];
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === 'yes';

    // Attempt to login
    $credentials = array(
        'user_login'    => $user_login,
        'user_password' => $user_password,
        'remember'      => $remember_me
    );

    $user = wp_signon($credentials, is_ssl());

    if (is_wp_error($user)) {
        wp_send_json_error(array(
            'message' => __('Invalid username or password.', 'sc_events')
        ));
    }

    // Check if user has event_manager, administrator, or event_scanner role
    $is_event_manager = in_array('event_manager', $user->roles) || in_array('administrator', $user->roles);
    $is_event_scanner = in_array('event_scanner', $user->roles);

    if (!$is_event_manager && !$is_event_scanner) {
        wp_logout();
        wp_send_json_error(array(
            'message' => __('You do not have permission to access the Event Manager Dashboard.', 'sc_events')
        ));
    }

    // Determine redirect URL based on role
    $redirect_url = $is_event_scanner
        ? home_url('/event-manager-dashboard/scanner')
        : home_url('/event-manager-dashboard/home');

    wp_send_json_success(array(
        'message' => __('Login successful! Redirecting...', 'sc_events'),
        'redirect' => $redirect_url
    ));
}

/**
 * Handle Event Manager Logout
 */
add_action('wp_ajax_event_manager_logout', 'sc_event_manager_logout_handler');
function sc_event_manager_logout_handler() {
    // Verify nonce for CSRF protection - Accept both dashboard nonce and logout nonce
    $nonce_valid = false;

    if (isset($_POST['nonce'])) {
        if (wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce') ||
            wp_verify_nonce($_POST['nonce'], 'event_manager_logout')) {
            $nonce_valid = true;
        }
    }

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Clear user session and logout
    wp_clear_auth_cookie();
    wp_logout();

    wp_send_json_success(array(
        'message' => __('Logged out successfully.', 'sc_events'),
        'redirect' => home_url('/event-manager-dashboard/')
    ));
}

/**
 * Update Event Manager Settings
 */
add_action('wp_ajax_update_event_manager_settings', 'sc_update_event_manager_settings');
function sc_update_event_manager_settings() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $current_user_id = get_current_user_id();
    $settings_type = sanitize_text_field($_POST['settings_type']);

    if ($settings_type === 'platform') {
        // Update platform settings (allow event managers)
        update_option('sc_platform_name', sanitize_text_field($_POST['platform_name']));
        update_option('sc_platform_description', sanitize_textarea_field($_POST['platform_description']));
        update_option('sc_platform_facebook', esc_url($_POST['facebook']));
        update_option('sc_platform_twitter', esc_url($_POST['twitter']));
        update_option('sc_platform_instagram', esc_url($_POST['instagram']));
        update_option('sc_platform_linkedin', esc_url($_POST['linkedin']));

        // Save primary color
        if (isset($_POST['primary_color'])) {
            update_option('sc_primary_color', sanitize_hex_color($_POST['primary_color']));
        }

        // Save secondary color
        if (isset($_POST['secondary_color'])) {
            update_option('sc_secondary_color', sanitize_hex_color($_POST['secondary_color']));
        }

        // Handle logo upload
        if (!empty($_FILES['platform_logo']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $logo_id = media_handle_upload('platform_logo', 0);
            if (!is_wp_error($logo_id)) {
                update_option('sc_platform_logo', $logo_id);
            }
        }

        wp_send_json_success(array('message' => __('Platform settings updated successfully.', 'sc_events')));

    } elseif ($settings_type === 'account') {
        // Update account settings
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $new_password = $_POST['new_password'];

        // Update user meta
        update_user_meta($current_user_id, 'first_name', $first_name);
        update_user_meta($current_user_id, 'last_name', $last_name);

        // Update email if changed
        if ($email !== wp_get_current_user()->user_email) {
            wp_update_user(array(
                'ID' => $current_user_id,
                'user_email' => $email
            ));
        }

        // Update password if provided
        if (!empty($new_password)) {
            wp_set_password($new_password, $current_user_id);
        }

        wp_send_json_success(array('message' => __('Account settings updated successfully.', 'sc_events')));
    }

    wp_send_json_error(array('message' => __('Invalid settings type.', 'sc_events')));
}

/**
 * Get Dashboard Statistics
 * Optimized with caching for 70,000+ users scale
 */
add_action('wp_ajax_get_dashboard_stats', 'sc_get_dashboard_stats');
function sc_get_dashboard_stats() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'sc_events')]);
    }

    // Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(['message' => __('Permission denied.', 'sc_events')]);
    }

    $user_id = get_current_user_id();
    $cache_key = 'sc_dashboard_stats_' . $user_id;

    // Try to get cached stats (5 minutes cache)
    $cached_stats = get_transient($cache_key);
    if ($cached_stats !== false) {
        wp_send_json_success($cached_stats);
        return;
    }

    global $wpdb;

    // Get total events (using Eventin post type) - optimized with prepared statement
    $total_events = (int) wp_count_posts('etn')->publish;

    // Get total attendees - optimized with prepared statement
    $total_attendees = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'etn-attendee',
            'publish'
        )
    );

    // Get total tickets sold - optimized with prepared statement
    $total_tickets = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            'etn_ticket_status',
            'completed'
        )
    );

    // Get recent events (limited query)
    $recent_events = wp_get_recent_posts([
        'numberposts' => 5,
        'post_type' => 'etn',
        'post_status' => 'publish'
    ]);

    $stats = [
        'total_events' => $total_events,
        'total_attendees' => $total_attendees,
        'total_tickets' => $total_tickets,
        'recent_events' => $recent_events
    ];

    // Cache for 5 minutes
    set_transient($cache_key, $stats, 300);

    wp_send_json_success($stats);
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

    // CRITICAL: request_id is REQUIRED to prevent duplicates
    if (empty($request_id)) {
        wp_send_json_error(array('message' => __('Invalid request. Please refresh the page and try again.', 'sc_events')));
    }

    $request_key = 'coupon_request_' . $user_id . '_' . md5($request_id);

    // Check if this exact request was already processed
    if (get_transient($request_key)) {
        wp_send_json_error(array('message' => __('This request was already processed. Please refresh the page.', 'sc_events')));
    }

    // Mark this request as processed (expires in 5 minutes = 300 seconds)
    set_transient($request_key, true, 300);

    // Parse num_coupons (handle both array and scalar values)
    if (isset($_POST['num_coupons'])) {
        if (is_array($_POST['num_coupons'])) {
            $num_coupons = intval($_POST['num_coupons'][0]);
        } else {
            $num_coupons = intval($_POST['num_coupons']);
        }
    } else {
        $num_coupons = 1;
    }

    $coupon_prefix = isset($_POST['coupon_prefix']) ? sanitize_text_field($_POST['coupon_prefix']) : '';
    $discount_type = sanitize_text_field($_POST['discount_type']);
    $discount_value = floatval($_POST['discount_value']);
    $event_id = intval($_POST['event_id']);
    $usage_limit = intval($_POST['usage_limit']);
    $expiry_date = sanitize_text_field($_POST['expiry_date']);

    $allowed_types = array('percentage', 'fixed');
    if (!in_array($discount_type, $allowed_types, true)) {
        if (!empty($request_key)) {
            delete_transient($request_key);
        }
        wp_send_json_error(array('message' => __('Invalid discount type selected.', 'sc_events')));
    }

    // Limit bulk creation to 100000 coupons
    if ($num_coupons > 100000) {
        if (!empty($request_key)) {
            delete_transient($request_key);
        }
        wp_send_json_error(array('message' => __('Maximum 100000 coupons can be created at once.', 'sc_events')));
    }

    $created_coupons = array();

    // Generate coupons
    for ($i = 0; $i < $num_coupons; $i++) {
        // Generate unique code
        $unique_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $coupon_code = strtoupper($coupon_prefix) . $unique_code;

        // Create coupon post
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
        // Generate CSV data with BOM for Excel compatibility
        $csv_data = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $csv_data .= "Coupon Code,Discount Type,Discount Value,Event ID,Usage Limit,Expiry Date\n";

        foreach ($created_coupons as $coupon) {
            // Format discount value for display
            $discount_display = '';
            if ($coupon['discount_type'] === 'percentage') {
                $discount_display = $coupon['discount_value'] . '%';
            } else {
                $discount_display = '$' . $coupon['discount_value'];
            }

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

        // Clean up request tracking
        if (!empty($request_key)) {
            delete_transient($request_key);
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d coupon(s) created successfully! Downloading CSV file...', 'sc_events'), count($created_coupons)),
            'csv_data' => $csv_data,
            'count' => count($created_coupons),
            'auto_download' => true
        ));
    }

    // Clean up request tracking on error
    if (!empty($request_key)) {
        delete_transient($request_key);
    }

    wp_send_json_error(array('message' => __('Failed to create coupons.', 'sc_events')));
}

/**
 * Export Coupons to Excel/CSV
 */

// Export Coupons CSV
add_action( 'wp_ajax_export_coupons', 'sc_events_export_coupons' );

function sc_events_export_coupons() {
    // تحقق من الـ nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sc_dashboard_nonce' ) ) {
        wp_die( 'Invalid request (nonce).' );
    }

    // السماح بس للمسؤول أو event_manager
    if ( ! current_user_can( 'manage_options' ) && ! SC_Event_Manager_Dashboard::is_event_manager() ) {
        wp_die( 'You do not have permission to export coupons.' );
    }

    // فلتر الإيفنت
    $event_filter = isset( $_POST['export_event_id'] ) ? sanitize_text_field( $_POST['export_event_id'] ) : 'all';

    $args = array(
        'post_type'      => 'sc_coupon',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    // لو اختار إيفنت محدد
    if ( $event_filter !== 'all' ) {
        $event_id = absint( $event_filter );

        // 0 معناها "كل الإيفنتات" برضو، فنسيبها من غير meta_query
        if ( $event_id > 0 ) {
            $args['meta_query'] = array(
                array(
                    'key'   => 'event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            );
        }
    }

    $coupons = get_posts( $args );

    // إعداد الهيدر بتاع الـ CSV
    $filename = 'coupons-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';

    // عشان ما يبقاش في أي output قبله
    if ( ob_get_length() ) {
        ob_end_clean();
    }

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    // BOM عشان الإكسل يفتح UTF-8 صح
    echo "\xEF\xBB\xBF";

    $output = fopen( 'php://output', 'w' );

    // عنوان الأعمدة
    fputcsv( $output, array(
        'Coupon Code',
        'Discount',
        'Event',
        'Usage',
        'Expiry Date',
        'Status',
    ) );

    if ( $coupons ) {
        foreach ( $coupons as $coupon ) {
            $coupon_id      = $coupon->ID;
            $code           = $coupon->post_title;

            $discount_type  = get_post_meta( $coupon_id, 'discount_type', true );
            $discount_value = get_post_meta( $coupon_id, 'discount_value', true );
            $event_id       = get_post_meta( $coupon_id, 'event_id', true );
            $usage_limit    = get_post_meta( $coupon_id, 'usage_limit', true );
            $usage_count    = get_post_meta( $coupon_id, 'usage_count', true );
            $expiry_date    = get_post_meta( $coupon_id, 'expiry_date', true );

            // نص الخصم
            if ( $discount_type === 'percentage' ) {
                $discount_text = $discount_value . '%';
            } elseif ( $discount_type === 'fixed' ) {
                $discount_text = '$' . $discount_value;
            } else {
                $discount_text = '100% (Free)';
            }

            // عنوان الإيفنت
            if ( $event_id == 0 || $event_id === '0' || $event_id === '' ) {
                $event_label = 'All Events';
            } else {
                $event_post = get_post( $event_id );
                if ( $event_post ) {
                    $event_label = $event_post->post_title;
                } else {
                    $event_label = 'Event Deleted';
                }
            }

            // الاستخدام
            $usage_limit  = $usage_limit !== '' ? (int) $usage_limit : 0;
            $usage_count  = $usage_count !== '' ? (int) $usage_count : 0;
            $usage_text   = $usage_count . ' / ' . ( $usage_limit ? $usage_limit : '∞' );

            // التاريخ
            if ( $expiry_date ) {
                $expiry_text = date( 'Y-m-d', strtotime( $expiry_date ) );
            } else {
                $expiry_text = 'No Expiry';
            }

            // حالة الكوبون
            $is_expired        = $expiry_date && strtotime( $expiry_date ) < time();
            $is_limit_reached  = $usage_limit && $usage_count >= $usage_limit;

            if ( $is_expired ) {
                $status_text = 'Expired';
            } elseif ( $is_limit_reached ) {
                $status_text = 'Limit Reached';
            } else {
                $status_text = 'Active';
            }

            // صف الـ CSV
            fputcsv( $output, array(
                $code,
                $discount_text,
                $event_label,
                $usage_text,
                $expiry_text,
                $status_text,
            ) );
        }
    }

    fclose( $output );
    exit;
}

/**
 * Export Coupons via AJAX (returns JSON with CSV data)
 */
add_action('wp_ajax_export_coupons_ajax', 'sc_events_export_coupons_ajax');
function sc_events_export_coupons_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => 'Invalid request (nonce).'));
    }

    // Check permissions
    if (!current_user_can('manage_options') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'You do not have permission to export coupons.'));
    }

    // Get event filter
    $event_filter = isset($_POST['export_event_id']) ? sanitize_text_field($_POST['export_event_id']) : 'all';

    $args = array(
        'post_type'      => 'sc_coupon',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    // Filter by event if specified
    if ($event_filter !== 'all') {
        $event_id = absint($event_filter);
        if ($event_id > 0) {
            $args['meta_query'] = array(
                array(
                    'key'   => 'event_id',
                    'value' => $event_id,
                    'compare' => '=',
                ),
            );
        }
    }

    $coupons = get_posts($args);

    // Build CSV content
    $csv_data = "\xEF\xBB\xBF"; // BOM for UTF-8

    // Headers
    $headers = array('Coupon Code', 'Discount', 'Event', 'Usage', 'Expiry Date', 'Status');
    $csv_data .= '"' . implode('","', $headers) . '"' . "\n";

    // Data rows
    if ($coupons) {
        foreach ($coupons as $coupon) {
            $coupon_id      = $coupon->ID;
            $code           = $coupon->post_title;

            $discount_type  = get_post_meta($coupon_id, 'discount_type', true);
            $discount_value = get_post_meta($coupon_id, 'discount_value', true);
            $event_id       = get_post_meta($coupon_id, 'event_id', true);
            $usage_limit    = get_post_meta($coupon_id, 'usage_limit', true);
            $usage_count    = get_post_meta($coupon_id, 'usage_count', true);
            $expiry_date    = get_post_meta($coupon_id, 'expiry_date', true);

            // Discount text
            if ($discount_type === 'percentage') {
                $discount_text = $discount_value . '%';
            } elseif ($discount_type === 'fixed') {
                $discount_text = '$' . $discount_value;
            } else {
                $discount_text = '100% (Free)';
            }

            // Event label
            if ($event_id == 0 || $event_id === '0' || $event_id === '') {
                $event_label = 'All Events';
            } else {
                $event_post = get_post($event_id);
                $event_label = $event_post ? $event_post->post_title : 'Event Deleted';
            }

            // Usage
            $usage_limit  = $usage_limit !== '' ? (int) $usage_limit : 0;
            $usage_count  = $usage_count !== '' ? (int) $usage_count : 0;
            $usage_text   = $usage_count . ' / ' . ($usage_limit ? $usage_limit : '∞');

            // Expiry
            $expiry_text = $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : 'No Expiry';

            // Status
            $is_expired        = $expiry_date && strtotime($expiry_date) < time();
            $is_limit_reached  = $usage_limit && $usage_count >= $usage_limit;

            if ($is_expired) {
                $status_text = 'Expired';
            } elseif ($is_limit_reached) {
                $status_text = 'Limit Reached';
            } else {
                $status_text = 'Active';
            }

            // Add row
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
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
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
    // Verify nonce
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
 * Delete All Coupons (Background Process)
 */
add_action('wp_ajax_delete_all_coupons', 'sc_delete_all_coupons');
function sc_delete_all_coupons() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get all coupons
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

    // Delete in batches to avoid timeout
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
    } else {
        wp_send_json_error(array('message' => __('Failed to delete coupons.', 'sc_events')));
    }
}

/**
 * Get Coupons with Server-Side Pagination (Optimized for 20k+ coupons)
 */
add_action('wp_ajax_sc_get_coupons_paginated', 'sc_get_coupons_paginated');
function sc_get_coupons_paginated() {
    // Increase limits for large datasets
    @set_time_limit(60);
    @ini_set('memory_limit', '256M');

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    // Get parameters
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = min(200, max(10, intval($_POST['per_page'] ?? 50)));
    $search = sanitize_text_field($_POST['search'] ?? '');
    $event_id = sanitize_text_field($_POST['event_id'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');

    $offset = ($page - 1) * $per_page;
    $today = current_time('Y-m-d');

    // Build WHERE clause
    $where = "WHERE p.post_type = 'sc_coupon' AND p.post_status = 'publish'";
    $where_values = array();

    // Search filter
    if (!empty($search)) {
        $where .= " AND p.post_title LIKE %s";
        $where_values[] = '%' . $wpdb->esc_like($search) . '%';
    }

    // Event filter
    if (!empty($event_id)) {
        $where .= " AND pm_event.meta_value = %s";
        $where_values[] = $event_id;
    }

    // Status filter
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

    // Count total (optimized)
    $count_sql = "
        SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'event_id'
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

    // Get coupons with all meta in one query
    $sql = "
        SELECT
            p.ID as id,
            p.post_title as code,
            pm_type.meta_value as discount_type,
            pm_value.meta_value as discount_value,
            pm_event.meta_value as event_id,
            pm_limit.meta_value as usage_limit,
            COALESCE(pm_usage.meta_value, '0') as usage_count,
            pm_expiry.meta_value as expiry_date
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'discount_type'
        LEFT JOIN {$wpdb->postmeta} pm_value ON p.ID = pm_value.post_id AND pm_value.meta_key = 'discount_value'
        LEFT JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'event_id'
        LEFT JOIN {$wpdb->postmeta} pm_limit ON p.ID = pm_limit.post_id AND pm_limit.meta_key = 'usage_limit'
        LEFT JOIN {$wpdb->postmeta} pm_usage ON p.ID = pm_usage.post_id AND pm_usage.meta_key = 'usage_count'
        LEFT JOIN {$wpdb->postmeta} pm_expiry ON p.ID = pm_expiry.post_id AND pm_expiry.meta_key = 'expiry_date'
        $where
        ORDER BY p.ID DESC
        LIMIT %d OFFSET %d
    ";

    $query_values = array_merge($where_values, array($per_page, $offset));
    $coupons = $wpdb->get_results($wpdb->prepare($sql, $query_values));

    // Get event titles in bulk (for displayed coupons only)
    $event_ids = array_filter(array_unique(array_column($coupons, 'event_id')));
    $event_titles = array();
    if (!empty($event_ids)) {
        $event_ids_str = implode(',', array_map('intval', $event_ids));
        $events = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($event_ids_str)");
        foreach ($events as $event) {
            $event_titles[$event->ID] = $event->post_title;
        }
    }

    // Format output
    $result = array();
    foreach ($coupons as $coupon) {
        $is_expired = !empty($coupon->expiry_date) && $coupon->expiry_date < $today;
        $is_used = !empty($coupon->usage_limit) && $coupon->usage_limit != '0'
                   && intval($coupon->usage_count) >= intval($coupon->usage_limit);

        $status = 'active';
        if ($is_expired) $status = 'expired';
        elseif ($is_used) $status = 'used';

        $result[] = array(
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type ?: 'free',
            'discount_value' => $coupon->discount_value ?: '0',
            'event_id' => $coupon->event_id ?: 0,
            'event_title' => isset($event_titles[$coupon->event_id]) ? $event_titles[$coupon->event_id] : '',
            'usage_limit' => $coupon->usage_limit ?: 0,
            'usage_count' => intval($coupon->usage_count),
            'expiry_date' => $coupon->expiry_date ? date('M j, Y', strtotime($coupon->expiry_date)) : '',
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
 * Sync Coupon Usage - Recalculate usage_count from attendees data
 */
add_action('wp_ajax_sc_sync_coupon_usage', 'sc_sync_coupon_usage');
function sc_sync_coupon_usage() {
    // Increase limits for large datasets
    @set_time_limit(300); // 5 minutes
    @ini_set('memory_limit', '512M');

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    // Step 1: Get all coupon codes used by attendees with count
    $coupon_usage = $wpdb->get_results("
        SELECT pm.meta_value as coupon_code, COUNT(*) as usage_count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = 'etn_coupon_code'
        AND pm.meta_value IS NOT NULL
        AND pm.meta_value != ''
        AND p.post_type = 'etn-attendee'
        AND p.post_status = 'publish'
        GROUP BY pm.meta_value
    ");

    // Convert to associative array for quick lookup
    $usage_map = array();
    $total_usage = 0;
    foreach ($coupon_usage as $row) {
        $usage_map[$row->coupon_code] = (int) $row->usage_count;
        $total_usage += (int) $row->usage_count;
    }

    // Step 2: Get all coupons
    $coupons = $wpdb->get_results("
        SELECT ID, post_title as code
        FROM {$wpdb->posts}
        WHERE post_type = 'sc_coupon'
        AND post_status = 'publish'
    ");

    // Step 3: Update each coupon's usage_count
    $updated = 0;
    foreach ($coupons as $coupon) {
        $new_usage = isset($usage_map[$coupon->code]) ? $usage_map[$coupon->code] : 0;
        $current_usage = (int) get_post_meta($coupon->ID, 'usage_count', true);

        // Only update if different
        if ($new_usage !== $current_usage) {
            update_post_meta($coupon->ID, 'usage_count', $new_usage);
            $updated++;
        }
    }

    wp_send_json_success(array(
        'updated' => $updated,
        'total_coupons' => count($coupons),
        'total_usage' => $total_usage,
        'stats' => array(
            'total_usage' => $total_usage
        )
    ));
}

/**
 * Import Coupons from CSV (Optimized for speed)
 */
add_action('wp_ajax_import_coupons', 'sc_import_coupons');
function sc_import_coupons() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Get data
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $default_usage_limit = isset($_POST['default_usage_limit']) ? intval($_POST['default_usage_limit']) : 1;
    $coupons_json = isset($_POST['coupons']) ? $_POST['coupons'] : '';

    if (empty($coupons_json)) {
        wp_send_json_error(array('message' => __('No coupon data provided.', 'sc_events')));
    }

    // Decode JSON
    $coupons = json_decode(stripslashes($coupons_json), true);

    if (!is_array($coupons) || empty($coupons)) {
        wp_send_json_error(array('message' => __('Invalid coupon data format.', 'sc_events')));
    }

    // Limit import to 100000 coupons at once
    if (count($coupons) > 100000) {
        wp_send_json_error(array('message' => __('Maximum 100000 coupons can be imported at once.', 'sc_events')));
    }

    global $wpdb;

    // OPTIMIZATION: Get all existing coupon codes in one query
    $existing_coupons = $wpdb->get_col("
        SELECT post_title FROM {$wpdb->posts}
        WHERE post_type = 'sc_coupon' AND post_status IN ('publish', 'draft', 'pending')
    ");
    $existing_codes = array_map('strtoupper', $existing_coupons);

    $imported = 0;
    $skipped = 0;
    $errors = array();
    $current_user_id = get_current_user_id();

    // OPTIMIZATION: Disable autocommit for batch insert
    $wpdb->query('START TRANSACTION');

    foreach ($coupons as $index => $coupon) {
        $row_num = $index + 2; // +2 because index starts at 0 and row 1 is header

        // Get coupon code
        $code = isset($coupon['code']) ? strtoupper(sanitize_text_field(trim($coupon['code']))) : '';

        if (empty($code)) {
            $skipped++;
            $errors[] = sprintf(__('Row %d: Empty coupon code.', 'sc_events'), $row_num);
            continue;
        }

        // OPTIMIZATION: Check against pre-loaded existing codes
        if (in_array($code, $existing_codes)) {
            $skipped++;
            $errors[] = sprintf(__('Row %d: Coupon "%s" already exists.', 'sc_events'), $row_num, $code);
            continue;
        }

        // Add to existing codes to prevent duplicates in same import
        $existing_codes[] = $code;

        // Parse discount type and value
        $discount_type  = 'percentage';
        $discount_value = 0;

        // New: support separate discount_type + discount_value from CSV
        if (isset($coupon['discount_type']) || isset($coupon['discount_value'])) {

            // نوع الخصم
            $discount_type_raw = isset($coupon['discount_type']) ? strtolower(trim($coupon['discount_type'])) : '';
            if ($discount_type_raw === 'fixed' || $discount_type_raw === 'amount') {
                $discount_type = 'fixed';
            } else {
                // أي حاجة تانية نعتبرها percentage
                $discount_type = 'percentage';
            }

            // قيمة الخصم
            if (isset($coupon['discount_value']) && $coupon['discount_value'] !== '') {
                $value_str = sanitize_text_field(trim($coupon['discount_value']));
                // نسمح بصيغ زي "100" أو "100%" أو "$100"
                $value_str = preg_replace('/[^0-9.]/', '', $value_str);
                if ($value_str !== '' && is_numeric($value_str)) {
                    $discount_value = floatval($value_str);
                }
            }
        }

        // Backwards compatibility: لو جالك عمود واحد اسمه discount زي القديم
        if ($discount_value === 0 && isset($coupon['discount'])) {
            $discount_str = sanitize_text_field(trim($coupon['discount']));

            // Percentage مثل "10%"
            if (strpos($discount_str, '%') !== false) {
                $discount_type  = 'percentage';
                $discount_value = floatval(str_replace('%', '', $discount_str));
            }
            // Fixed amount مثل "$10" أو "10"
            elseif (strpos($discount_str, '$') !== false || is_numeric($discount_str)) {
                $discount_type  = 'fixed';
                $discount_value = floatval(preg_replace('/[^0-9.]/', '', $discount_str));
            }
            // لو فيها كلمة free
            elseif (stripos($discount_str, 'free') !== false) {
                $discount_type  = 'percentage';
                $discount_value = 100;
            }
        }

        // Validate discount value
        if ($discount_value <= 0) {
            $skipped++;
            $errors[] = sprintf(__('Row %d: Invalid discount value for coupon "%s".', 'sc_events'), $row_num, $code);
            continue;
        }

        // Percentage validation
        if ($discount_type === 'percentage' && $discount_value > 100) {
            $discount_value = 100;
        }

        // Parse usage limit - use default if not specified in CSV
        $usage_limit = $default_usage_limit;
        if (isset($coupon['usage_limit']) && $coupon['usage_limit'] !== '') {
            $limit_str = sanitize_text_field(trim($coupon['usage_limit']));
            if (is_numeric($limit_str)) {
                $usage_limit = intval($limit_str);
            }
            // "unlimited" means 0 (unlimited)
            elseif (stripos($limit_str, 'unlimit') !== false) {
                $usage_limit = 0;
            }
        }

        // Parse expiry date
        $expiry_date = '';
        if (isset($coupon['expiry_date']) && !empty($coupon['expiry_date'])) {
            $date_str = sanitize_text_field(trim($coupon['expiry_date']));

            // Skip "no expiry" type values
            if (stripos($date_str, 'no') === false && stripos($date_str, 'never') === false) {
                // Try to parse date
                $timestamp = strtotime($date_str);
                if ($timestamp !== false) {
                    $expiry_date = date('Y-m-d', $timestamp);
                }
            }
        }

        // OPTIMIZATION: Direct insert instead of wp_insert_post
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', 1);

        $result = $wpdb->insert(
            $wpdb->posts,
            array(
                'post_author' => $current_user_id,
                'post_date' => $now,
                'post_date_gmt' => $now_gmt,
                'post_title' => $code,
                'post_status' => 'publish',
                'post_type' => 'sc_coupon',
                'post_modified' => $now,
                'post_modified_gmt' => $now_gmt,
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_name' => sanitize_title($code),
                'post_content' => '',
                'post_excerpt' => '',
                'to_ping' => '',
                'pinged' => '',
                'post_content_filtered' => ''
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $coupon_id = $wpdb->insert_id;

            // OPTIMIZATION: Batch meta inserts
            $meta_values = array(
                array($coupon_id, 'discount_type', $discount_type),
                array($coupon_id, 'discount_value', $discount_value),
                array($coupon_id, 'event_id', $event_id),
                array($coupon_id, 'usage_limit', $usage_limit),
                array($coupon_id, 'usage_count', 0),
                array($coupon_id, 'expiry_date', $expiry_date)
            );

            foreach ($meta_values as $meta) {
                $wpdb->insert(
                    $wpdb->postmeta,
                    array('post_id' => $meta[0], 'meta_key' => $meta[1], 'meta_value' => $meta[2]),
                    array('%d', '%s', '%s')
                );
            }

            $imported++;
        } else {
            $skipped++;
            $errors[] = sprintf(__('Row %d: Failed to create coupon "%s".', 'sc_events'), $row_num, $code);
        }
    }

    // Commit transaction
    $wpdb->query('COMMIT');

    // Clear post cache
    wp_cache_flush();

    // Build response message
    if ($imported > 0) {
        $message = sprintf(__('%d coupon(s) imported successfully.', 'sc_events'), $imported);

        if ($skipped > 0) {
            $message .= ' ' . sprintf(__('%d row(s) skipped.', 'sc_events'), $skipped);
        }

        wp_send_json_success(array(
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10) // Return first 10 errors
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('No coupons were imported.', 'sc_events'),
            'imported' => 0,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10)
        ));
    }
}

/**
 * Update Platform Settings (New dedicated handler)
 */
add_action('wp_ajax_sc_update_platform_settings', 'sc_ajax_update_platform_settings');
function sc_ajax_update_platform_settings() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions - allow event managers and admins
    if (!current_user_can('event_manager') && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Update platform settings
    update_option('sc_platform_name', sanitize_text_field($_POST['platform_name']));
    update_option('sc_platform_description', sanitize_textarea_field($_POST['platform_description']));
    update_option('sc_platform_facebook', esc_url_raw($_POST['facebook']));
    update_option('sc_platform_twitter', esc_url_raw($_POST['twitter']));
    update_option('sc_platform_instagram', esc_url_raw($_POST['instagram']));
    update_option('sc_platform_linkedin', esc_url_raw($_POST['linkedin']));

    // Contact Information
    if (isset($_POST['platform_phone'])) {
        update_option('sc_platform_phone', sanitize_text_field($_POST['platform_phone']));
    }
    if (isset($_POST['platform_email'])) {
        update_option('sc_platform_email', sanitize_email($_POST['platform_email']));
    }
    if (isset($_POST['platform_website'])) {
        update_option('sc_platform_website', esc_url_raw($_POST['platform_website']));
    }
    if (isset($_POST['platform_whatsapp'])) {
        // Remove spaces and dashes, keep + and numbers only
        $whatsapp = preg_replace('/[^0-9+]/', '', $_POST['platform_whatsapp']);
        update_option('sc_platform_whatsapp', $whatsapp);
    }

    // Save primary color
    if (isset($_POST['primary_color'])) {
        update_option('sc_primary_color', sanitize_hex_color($_POST['primary_color']));
    }

    // Save secondary color
    if (isset($_POST['secondary_color'])) {
        update_option('sc_secondary_color', sanitize_hex_color($_POST['secondary_color']));
    }

    // Handle logo upload
    if (!empty($_FILES['platform_logo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $logo_id = media_handle_upload('platform_logo', 0);
        if (!is_wp_error($logo_id)) {
            update_option('sc_platform_logo', $logo_id);
        }
    }

    /** -----------------------
     *  WooCommerce Settings
     * ------------------------ */

    if (isset($_POST['wc_country'])) {
        update_option('woocommerce_default_country', sanitize_text_field($_POST['wc_country']));
        update_option('sc_wc_country', sanitize_text_field($_POST['wc_country']));
    }

    if (isset($_POST['wc_currency'])) {
        update_option('woocommerce_currency', sanitize_text_field($_POST['wc_currency']));
        update_option('sc_wc_currency', sanitize_text_field($_POST['wc_currency']));
    }


    wp_send_json_success(array('message' => __('Platform settings updated successfully! Colors will apply after page reload.', 'sc_events')));
}

/**
 * Update Account Settings (New dedicated handler)
 */
add_action('wp_ajax_sc_update_account_settings', 'sc_ajax_update_account_settings');
function sc_ajax_update_account_settings() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error(array('message' => __('User not logged in.', 'sc_events')));
    }

    // Update account settings
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    // Update user meta
    update_user_meta($current_user_id, 'first_name', $first_name);
    update_user_meta($current_user_id, 'last_name', $last_name);

    // Update display name
    wp_update_user(array(
        'ID' => $current_user_id,
        'display_name' => $first_name . ' ' . $last_name
    ));

    // Update email if changed
    $current_user = wp_get_current_user();
    if ($email !== $current_user->user_email) {
        $update_result = wp_update_user(array(
            'ID' => $current_user_id,
            'user_email' => $email
        ));

        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => __('Failed to update email address.', 'sc_events')));
        }
    }

    // Update password if provided
    if (!empty($new_password)) {
        wp_set_password($new_password, $current_user_id);

        // Re-authenticate user
        wp_set_auth_cookie($current_user_id);
    }

    wp_send_json_success(array('message' => __('Account settings updated successfully!', 'sc_events')));
}

/**
 * Email All Attendees for an Event
 */
add_action('wp_ajax_sc_email_attendees', 'sc_ajax_email_attendees');
function sc_ajax_email_attendees() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $event_id = intval($_POST['event_id']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = wp_kses_post($_POST['message']);

    if (!$event_id || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'sc_events')));
    }

    // Load reports functions to get attendees
    require_once get_template_directory() . '/inc/admin-dashboard/reports-functions.php';

    // Get event statistics (includes all attendees)
    $stats = sc_get_event_statistics($event_id);

    if (empty($stats['attendees'])) {
        wp_send_json_error(array('message' => __('No attendees found for this event.', 'sc_events')));
    }

    // Get event details for email template
    $event = get_post($event_id);
    $event_name = $event ? $event->post_title : __('Your Event', 'sc_events');
    $event_date = get_post_meta($event_id, 'etn_start_date', true);
    if ($event_date) {
        $event_date = date('F j, Y', strtotime($event_date));
    } else {
        $event_date = __('TBA', 'sc_events');
    }

    $sent_count = 0;
    $failed_emails = array();

    // Send email to each attendee
    foreach ($stats['attendees'] as $attendee) {
        $name = !empty($attendee['name']) ? $attendee['name'] : __('Attendee', 'sc_events');
        $email = !empty($attendee['email']) ? $attendee['email'] : '';
        $ticket_type = !empty($attendee['ticket']) ? $attendee['ticket'] : __('Standard Ticket', 'sc_events');

        // Skip if no email
        if (empty($email) || !is_email($email)) {
            $failed_emails[] = $name . ' (invalid email)';
            continue;
        }

        // Replace variables in message
        $personalized_message = str_replace(
            array('{name}', '{event_name}', '{ticket_type}', '{event_date}'),
            array($name, $event_name, $ticket_type, $event_date),
            $message
        );

        // Build HTML email
        $email_html = sc_build_email_template($name, $personalized_message, $event_name);

        // Set email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        );

        // Send email
        $sent = wp_mail($email, $subject, $email_html, $headers);

        if ($sent) {
            $sent_count++;
        } else {
            $failed_emails[] = $name . ' (' . $email . ')';
        }
    }

    // Return results
    if ($sent_count > 0) {
        $success_message = sprintf(
            __('Successfully sent %d email(s).', 'sc_events'),
            $sent_count
        );

        if (!empty($failed_emails)) {
            $success_message .= ' ' . sprintf(
                __('Failed to send to %d recipient(s).', 'sc_events'),
                count($failed_emails)
            );
        }

        wp_send_json_success(array(
            'message' => $success_message,
            'sent_count' => $sent_count,
            'failed_count' => count($failed_emails)
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Failed to send any emails. Please check your mail configuration.', 'sc_events')
        ));
    }
}

/**
 * Build Beautiful HTML Email Template
 */
function sc_build_email_template($name, $message, $event_name) {
    // Get platform colors
    $primary_color = get_option('sc_primary_color', '#17a2b8');
    $secondary_color = get_option('sc_secondary_color', '#6c757d');

    // Get platform logo
    $logo_id = get_option('sc_platform_logo');
    $logo_url = '';
    if ($logo_id) {
        $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
    }

    // Get platform name
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    // Build HTML template
    $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($event_name) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header with Gradient -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, ' . esc_attr($secondary_color) . ' 100%); padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($platform_name) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;">' : '') . '
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">' . esc_html($event_name) . '</h1>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 30px 40px 20px;">
                            <p style="margin: 0 0 20px; font-size: 16px; color: #333333; line-height: 1.6;">
                                <strong>Hello ' . esc_html($name) . ',</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Message Content -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <div style="font-size: 15px; color: #555555; line-height: 1.8;">
                                ' . nl2br(wp_kses_post($message)) . '
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0 0 10px; font-size: 14px; color: #777777; text-align: center;">
                                Best regards,<br>
                                <strong>' . esc_html($platform_name) . '</strong>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 12px; color: #999999; text-align: center;">
                                This email was sent to you because you registered for <strong>' . esc_html($event_name) . '</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Email Footer -->
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 20px;">
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                &copy; ' . date('Y') . ' ' . esc_html($platform_name) . '. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    return $html;
}

/**
 * =========================================
 * EVENTS MANAGEMENT AJAX HANDLERS
 * Custom implementation to replace [etn_pro_dashboard]
 * =========================================
 */

/**
 * Get Events List
 */
add_action('wp_ajax_sc_get_events_list', 'sc_get_events_list');
function sc_get_events_list() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';

    // Base query args
    $args = array(
        'post_type' => 'etn',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'orderby' => 'meta_value',
        'meta_key' => 'etn_start_date',
        'order' => 'DESC'
    );

    // Apply filters
    switch ($filter) {
        case 'upcoming':
            $args['meta_query'] = array(
                array(
                    'key' => 'etn_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            );
            break;

        case 'past':
            $args['meta_query'] = array(
                array(
                    'key' => 'etn_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                )
            );
            break;

        case 'draft':
            $args['post_status'] = 'draft';
            break;
    }

    $events = get_posts($args);
    $events_data = array();

    foreach ($events as $event) {
        // Get event meta
        $start_date = get_post_meta($event->ID, 'etn_start_date', true);
        $end_date = get_post_meta($event->ID, 'etn_end_date', true);
        $start_time = get_post_meta($event->ID, 'etn_start_time', true);
        $end_time = get_post_meta($event->ID, 'etn_end_time', true);
        $venue = get_post_meta($event->ID, 'etn_event_location', true);
        $capacity = get_post_meta($event->ID, 'etn_total_avaiilable_tickets', true);

        // Handle array venue
        if (is_array($venue)) {
            if (isset($venue['location'])) {
                $venue = $venue['location'];
            } elseif (isset($venue['address'])) {
                $venue = $venue['address'];
            } else {
                $venue = !empty($venue) ? implode(', ', array_filter($venue)) : '';
            }
        }

        // Count tickets sold
        global $wpdb;
        $tickets_sold = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            WHERE p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'etn_event_id'
            AND pm.meta_value = %d
        ", $event->ID));

        // Format date
        $date_formatted = $start_date ? date('M j, Y', strtotime($start_date)) : 'Not set';
        if ($end_date && $end_date != $start_date) {
            $date_formatted .= ' - ' . date('M j, Y', strtotime($end_date));
        }

        // Time
        $time_str = '';
        if ($start_time) {
            $time_str = date('g:i A', strtotime($start_time));
            if ($end_time) {
                $time_str .= ' - ' . date('g:i A', strtotime($end_time));
            }
        }

        // Status badge
        $status_badge = '';
        switch ($event->post_status) {
            case 'publish':
                $status_badge = '<span class="badge badge-success">Published</span>';
                break;
            case 'draft':
                $status_badge = '<span class="badge badge-warning">Draft</span>';
                break;
            case 'private':
                $status_badge = '<span class="badge badge-secondary">Private</span>';
                break;
            default:
                $status_badge = '<span class="badge badge-secondary">' . ucfirst($event->post_status) . '</span>';
        }

        // Featured image
        $image_url = get_the_post_thumbnail_url($event->ID, 'thumbnail');

        $events_data[] = array(
            'ID' => $event->ID,
            'title' => $event->post_title,
            'date_formatted' => $date_formatted,
            'time' => $time_str,
            'venue' => $venue,
            'capacity' => $capacity,
            'tickets_sold' => $tickets_sold ?: 0,
            'status' => $event->post_status,
            'status_badge' => $status_badge,
            'image' => $image_url ? true : false,
            'image_url' => $image_url
        );
    }

    wp_send_json_success(array(
        'events' => $events_data,
        'count' => count($events_data)
    ));
}

/**
 * Create Content Pages (Privacy Policy, Terms & Conditions)
 */
add_action('wp_ajax_sc_create_content_page', 'sc_create_content_page_handler');
function sc_create_content_page_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array(
            'message' => __('Security check failed.', 'sc_events')
        ));
    }

    // Check user permissions - Allow administrators and event managers
    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array(
            'message' => __('You do not have permission to create pages.', 'sc_events')
        ));
    }

    $page_type = sanitize_text_field($_POST['page_type']);

    if ($page_type === 'privacy') {
        // Check if Privacy Policy page already exists
        $privacy_page = get_page_by_path('privacy-policy');

        if ($privacy_page) {
            wp_send_json_error(array(
                'message' => __('Privacy Policy page already exists.', 'sc_events')
            ));
        }

        // Privacy Policy content
        $content = '<h2>Privacy Policy</h2>

<p>Your privacy is important to us. This privacy policy explains what personal data we collect and how we use it.</p>

<h3>Information We Collect</h3>
<p>When you use our event booking system, we may collect the following information:</p>
<ul>
    <li>Name and contact information (email address, phone number)</li>
    <li>Event registration details</li>
    <li>Payment information (processed securely through our payment gateway)</li>
    <li>Any additional information you provide during registration</li>
</ul>

<h3>How We Use Your Information</h3>
<p>We use your personal information to:</p>
<ul>
    <li>Process your event registrations and ticket purchases</li>
    <li>Send you event confirmations and updates</li>
    <li>Communicate with you about events and services</li>
    <li>Improve our services and user experience</li>
</ul>

<h3>Data Security</h3>
<p>We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction.</p>

<h3>Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access your personal data</li>
    <li>Request correction of your data</li>
    <li>Request deletion of your data</li>
    <li>Object to processing of your data</li>
</ul>

<h3>Contact Us</h3>
<p>If you have any questions about this privacy policy, please contact us.</p>';

        // Create page
        $page_data = array(
            'post_title'     => 'Privacy Policy',
            'post_content'   => $content,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id(),
            'post_name'      => 'privacy-policy',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        );

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(array(
                'message' => __('Failed to create Privacy Policy page.', 'sc_events')
            ));
        }

        // Set as WordPress privacy policy page
        update_option('wp_page_for_privacy_policy', $page_id);

        wp_send_json_success(array(
            'message' => __('Privacy Policy page created successfully!', 'sc_events'),
            'page_id' => $page_id
        ));

    } elseif ($page_type === 'terms') {
        // Check if Terms page already exists
        $terms_page = get_page_by_path('terms-and-conditions');

        if ($terms_page) {
            wp_send_json_error(array(
                'message' => __('Terms & Conditions page already exists.', 'sc_events')
            ));
        }

        // Terms & Conditions content
        $content = '<h2>Terms and Conditions</h2>

<p>Welcome to our event platform. By using our services, you agree to the following terms and conditions.</p>

<h3>Use of Service</h3>
<p>Our platform provides event registration and ticketing services. You agree to use our services only for lawful purposes and in accordance with these terms.</p>

<h3>Event Registration</h3>
<ul>
    <li>You must provide accurate and complete information when registering for events</li>
    <li>You are responsible for maintaining the confidentiality of your account</li>
    <li>One registration per person unless otherwise specified</li>
    <li>Registration confirmation will be sent to your email address</li>
</ul>

<h3>Ticket Purchases</h3>
<ul>
    <li>All ticket sales are final unless the event is cancelled</li>
    <li>Prices are displayed in the local currency</li>
    <li>Payment must be completed to secure your registration</li>
    <li>Tickets are non-transferable unless stated otherwise</li>
</ul>

<h3>Cancellations and Refunds</h3>
<p>If an event is cancelled by the organizer, you will be entitled to a full refund. Refunds for other reasons are subject to the event organizer\'s policy.</p>

<h3>Prohibited Activities</h3>
<p>You may not:</p>
<ul>
    <li>Resell tickets for profit without authorization</li>
    <li>Use our platform for fraudulent purposes</li>
    <li>Attempt to gain unauthorized access to our systems</li>
    <li>Violate any applicable laws or regulations</li>
</ul>

<h3>Limitation of Liability</h3>
<p>We provide our services "as is" and make no warranties about their availability or accuracy. We are not liable for any damages arising from your use of our platform.</p>

<h3>Changes to Terms</h3>
<p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting to the website.</p>

<h3>Contact</h3>
<p>If you have questions about these terms, please contact us.</p>';

        // Create page
        $page_data = array(
            'post_title'     => 'Terms and Conditions',
            'post_content'   => $content,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id(),
            'post_name'      => 'terms-and-conditions',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        );

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(array(
                'message' => __('Failed to create Terms & Conditions page.', 'sc_events')
            ));
        }

        wp_send_json_success(array(
            'message' => __('Terms & Conditions page created successfully!', 'sc_events'),
            'page_id' => $page_id
        ));

    } else {
        wp_send_json_error(array(
            'message' => __('Invalid page type.', 'sc_events')
        ));
    }
}

/**
 * Get Page Content for Editing
 */
add_action('wp_ajax_sc_get_page_content', 'sc_get_page_content_handler');
function sc_get_page_content_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check user permissions
    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;

    if (!$page_id) {
        wp_send_json_error(array('message' => __('Invalid page ID.', 'sc_events')));
    }

    $page = get_post($page_id);

    if (!$page || $page->post_type !== 'page') {
        wp_send_json_error(array('message' => __('Page not found.', 'sc_events')));
    }

    wp_send_json_success(array(
        'page_id' => $page_id,
        'title' => $page->post_title,
        'content' => $page->post_content
    ));
}

/**
 * Update Page Content
 */
add_action('wp_ajax_sc_update_page_content', 'sc_update_page_content_handler');
function sc_update_page_content_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check user permissions
    if (!current_user_can('administrator') && !SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

    if (!$page_id) {
        wp_send_json_error(array('message' => __('Invalid page ID.', 'sc_events')));
    }

    $page = get_post($page_id);

    if (!$page || $page->post_type !== 'page') {
        wp_send_json_error(array('message' => __('Page not found.', 'sc_events')));
    }

    // Update the page
    $result = wp_update_post(array(
        'ID' => $page_id,
        'post_title' => $title,
        'post_content' => $content
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => __('Failed to update page.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message' => __('Page updated successfully!', 'sc_events'),
        'page_id' => $page_id
    ));
}

/**
 * Update SEO Settings
 */
add_action('wp_ajax_sc_update_seo_settings', 'sc_update_seo_settings_handler');
function sc_update_seo_settings_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check if user is event manager or admin
    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Update Site Title
    if (isset($_POST['site_title'])) {
        $site_title = sanitize_text_field($_POST['site_title']);
        update_option('blogname', $site_title);
    }

    // Update Tagline
    if (isset($_POST['site_tagline'])) {
        $site_tagline = sanitize_text_field($_POST['site_tagline']);
        update_option('blogdescription', $site_tagline);
    }

    // Handle Site Icon upload
    if (isset($_FILES['site_icon']) && !empty($_FILES['site_icon']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('site_icon', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array(
                'message' => __('Failed to upload site icon: ', 'sc_events') . $attachment_id->get_error_message()
            ));
        }

        // Update site icon option
        update_option('site_icon', $attachment_id);
    }

    wp_send_json_success(array(
        'message' => __('SEO settings updated successfully!', 'sc_events')
    ));
}

/**
 * ===========================================
 * CUSTOMERS MANAGEMENT AJAX HANDLERS
 * ===========================================
 */

/**
 * Get Customers with Pagination
 */
add_action('wp_ajax_sc_get_customers_paginated', 'sc_get_customers_paginated');
function sc_get_customers_paginated() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $offset = ($page - 1) * $per_page;

    // Build user query args
    $user_args = array(
        'role__in' => array('etn-customer', 'subscriber', 'customer'),
        'number' => $per_page,
        'offset' => $offset,
        'orderby' => 'registered',
        'order' => 'DESC'
    );

    // Search filter
    if (!empty($search)) {
        $user_args['search'] = '*' . $search . '*';
        $user_args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
    }

    // Date filters
    if (!empty($date_from)) {
        $user_args['date_query'] = array(
            array(
                'after' => $date_from,
                'inclusive' => true
            )
        );
    }
    if (!empty($date_to)) {
        if (!isset($user_args['date_query'])) {
            $user_args['date_query'] = array();
        }
        $user_args['date_query'][] = array(
            'before' => $date_to . ' 23:59:59',
            'inclusive' => true
        );
    }

    // If filtering by event, get users who attended that event
    $user_ids_from_event = array();
    if ($event_id > 0) {
        // Get user IDs from _user_id meta
        $attendees_by_user_id = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_user_id'
            AND pm.meta_value != ''
            AND pm.meta_value != '0'
            AND p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = 'etn_event_id'
                AND pm2.meta_value = %d
            )
        ", $event_id));

        // Get user IDs by matching email
        $attendee_emails = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'etn_email'
            AND pm.meta_value != ''
            AND p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = 'etn_event_id'
                AND pm2.meta_value = %d
            )
        ", $event_id));

        // Find user IDs by email
        $user_ids_by_email = array();
        if (!empty($attendee_emails)) {
            $email_placeholders = implode(',', array_fill(0, count($attendee_emails), '%s'));
            $user_ids_by_email = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE user_email IN ($email_placeholders)",
                $attendee_emails
            ));
        }

        // Merge both arrays
        $all_user_ids = array_unique(array_merge(
            array_map('intval', $attendees_by_user_id),
            array_map('intval', $user_ids_by_email)
        ));

        if (!empty($all_user_ids)) {
            $user_ids_from_event = array_filter($all_user_ids);
            $user_args['include'] = $user_ids_from_event;
        } else {
            // No customers for this event
            wp_send_json_success(array(
                'customers' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => 1,
                'stats' => sc_get_customer_stats()
            ));
            return;
        }
    }

    // Get users
    $user_query = new WP_User_Query($user_args);
    $users = $user_query->get_results();
    $total = $user_query->get_total();
    $pages = ceil($total / $per_page);

    $customers = array();
    foreach ($users as $user) {
        // Get phone number
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user->ID, 'phone', true);
        }

        // Count events attended - search by _user_id OR email match
        $user_email = $user->user_email;
        $events_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT pm_event.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'etn_event_id'
            LEFT JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_user_id'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'etn_email'
            WHERE p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND (pm_user.meta_value = %d OR pm_email.meta_value = %s)
        ", $user->ID, $user_email));

        // Get last event - search by _user_id OR email match
        $last_event_id = $wpdb->get_var($wpdb->prepare("
            SELECT pm_event.meta_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = 'etn_event_id'
            LEFT JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_user_id'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'etn_email'
            WHERE p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND (pm_user.meta_value = %d OR pm_email.meta_value = %s)
            ORDER BY p.post_date DESC
            LIMIT 1
        ", $user->ID, $user_email));

        $last_event = '';
        if ($last_event_id) {
            $event_post = get_post($last_event_id);
            if ($event_post) {
                $last_event = $event_post->post_title;
            }
        }

        $customers[] = array(
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'events_count' => intval($events_count),
            'last_event' => $last_event,
            'registered' => date('M j, Y', strtotime($user->user_registered))
        );
    }

    wp_send_json_success(array(
        'customers' => $customers,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page,
        'stats' => sc_get_customer_stats()
    ));
}

/**
 * Get Customer Statistics
 */
function sc_get_customer_stats() {
    global $wpdb;

    // Total customers with etn-customer or subscriber role
    $total = count(get_users(array(
        'role__in' => array('etn-customer', 'subscriber', 'customer'),
        'fields' => 'ID'
    )));

    // Active customers (have at least one event registration)
    // Count by _user_id
    $active_by_user_id = $wpdb->get_var("
        SELECT COUNT(DISTINCT pm.meta_value)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_user_id'
        AND p.post_type = 'etn-attendee'
        AND p.post_status = 'publish'
        AND pm.meta_value != ''
        AND pm.meta_value != '0'
    ");

    // Count by email match
    $active_by_email = $wpdb->get_var("
        SELECT COUNT(DISTINCT u.ID)
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->postmeta} pm ON pm.meta_value = u.user_email AND pm.meta_key = 'etn_email'
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = 'etn-attendee'
        AND p.post_status = 'publish'
    ");

    $active = max(intval($active_by_user_id), intval($active_by_email));

    // Registered this month
    $this_month = count(get_users(array(
        'role__in' => array('etn-customer', 'subscriber', 'customer'),
        'fields' => 'ID',
        'date_query' => array(
            array(
                'after' => date('Y-m-01'),
                'inclusive' => true
            )
        )
    )));

    // Total registrations
    $total_registrations = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = 'etn-attendee'
        AND post_status = 'publish'
    ");

    return array(
        'total' => intval($total),
        'active' => intval($active),
        'this_month' => intval($this_month),
        'total_registrations' => intval($total_registrations)
    );
}

/**
 * Get Customer Details
 */
add_action('wp_ajax_sc_get_customer_details', 'sc_get_customer_details');
function sc_get_customer_details() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    // Get phone number
    $phone = get_user_meta($customer_id, 'billing_phone', true);
    if (empty($phone)) {
        $phone = get_user_meta($customer_id, 'phone', true);
    }

    // Get events attended - search by _user_id OR by email match
    $user_email = $user->user_email;

    $attendee_posts = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT p.ID, p.post_date,
               pm1.meta_value as event_id,
               pm2.meta_value as ticket_status,
               pm3.meta_value as status,
               pm4.meta_value as ticket_name
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'etn_event_id'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'etn_attendeee_ticket_status'
        LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'etn_status'
        LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'ticket_name'
        LEFT JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_user_id'
        LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'etn_email'
        WHERE p.post_type = 'etn-attendee'
        AND p.post_status = 'publish'
        AND (
            pm_user.meta_value = %d
            OR pm_email.meta_value = %s
        )
        ORDER BY p.post_date DESC
    ", $customer_id, $user_email));

    $events = array();
    $tickets_used = 0;
    $tickets_pending = 0;

    foreach ($attendee_posts as $attendee) {
        $event_post = get_post($attendee->event_id);
        $event_date = '';

        if ($event_post) {
            $start_date = get_post_meta($attendee->event_id, 'etn_start_date', true);
            $event_date = $start_date ? date('M j, Y', strtotime($start_date)) : '';
        }

        $events[] = array(
            'event_id' => $attendee->event_id,
            'event_title' => $event_post ? $event_post->post_title : 'Unknown Event',
            'event_date' => $event_date,
            'ticket_type' => $attendee->ticket_name ?: 'General',
            'ticket_status' => $attendee->ticket_status ?: 'unused',
            'status' => $attendee->status ?: 'pending',
            'registered_date' => date('M j, Y', strtotime($attendee->post_date))
        );

        if ($attendee->ticket_status === 'used') {
            $tickets_used++;
        } else {
            $tickets_pending++;
        }
    }

    $role_name = 'Customer';
    if (!empty($user->roles)) {
        $role_name = ucfirst(str_replace(array('-', '_'), ' ', $user->roles[0]));
    }

    $customer = array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $phone,
        'registered' => date('M j, Y g:i A', strtotime($user->user_registered)),
        'role' => $role_name,
        'events_count' => count(array_unique(array_column($events, 'event_id'))),
        'tickets_used' => $tickets_used,
        'tickets_pending' => $tickets_pending
    );

    wp_send_json_success(array(
        'customer' => $customer,
        'events' => $events
    ));
}

/**
 * Update Customer
 */
add_action('wp_ajax_sc_update_customer', 'sc_update_customer');
function sc_update_customer() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    // Prepare update data
    $user_data = array('ID' => $customer_id);

    if (isset($_POST['first_name'])) {
        $user_data['first_name'] = sanitize_text_field($_POST['first_name']);
    }

    if (isset($_POST['last_name'])) {
        $user_data['last_name'] = sanitize_text_field($_POST['last_name']);
    }

    if (isset($_POST['email'])) {
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'sc_events')));
        }

        // Check if email already exists for another user
        $existing_user = get_user_by('email', $email);
        if ($existing_user && $existing_user->ID !== $customer_id) {
            wp_send_json_error(array('message' => __('Email address already in use by another user.', 'sc_events')));
        }

        $user_data['user_email'] = $email;
    }

    // Update display name
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : $user->first_name;
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : $user->last_name;
    $user_data['display_name'] = trim($first_name . ' ' . $last_name);

    // Update password if provided
    if (!empty($_POST['new_password'])) {
        $new_password = $_POST['new_password'];
        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters.', 'sc_events')));
        }
        $user_data['user_pass'] = $new_password;
    }

    // Update user
    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    // Update phone number with country code
    if (isset($_POST['phone'])) {
        $phone_code = isset($_POST['phone_code']) ? sanitize_text_field($_POST['phone_code']) : '+20';
        $phone_number = sanitize_text_field($_POST['phone']);
        $phone = $phone_number ? $phone_code . $phone_number : '';
        update_user_meta($customer_id, 'billing_phone', $phone);
        update_user_meta($customer_id, 'phone', $phone);
    }

    wp_send_json_success(array(
        'message' => __('Customer updated successfully!', 'sc_events')
    ));
}

/**
 * Delete Customer
 */
add_action('wp_ajax_sc_delete_customer', 'sc_delete_customer');
function sc_delete_customer() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    if (!$customer_id) {
        wp_send_json_error(array('message' => __('Invalid customer ID.', 'sc_events')));
    }

    // Don't allow deleting administrators
    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    if (in_array('administrator', $user->roles) || in_array('event_manager', $user->roles)) {
        wp_send_json_error(array('message' => __('Cannot delete administrator or event manager accounts.', 'sc_events')));
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $result = wp_delete_user($customer_id);

    if (!$result) {
        wp_send_json_error(array('message' => __('Failed to delete customer.', 'sc_events')));
    }

    wp_send_json_success(array(
        'message' => __('Customer deleted successfully!', 'sc_events')
    ));
}

/**
 * Bulk Delete Customers
 */
add_action('wp_ajax_sc_bulk_delete_customers', 'sc_bulk_delete_customers');
function sc_bulk_delete_customers() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_ids = isset($_POST['customer_ids']) ? array_map('intval', $_POST['customer_ids']) : array();

    if (empty($customer_ids)) {
        wp_send_json_error(array('message' => __('No customers selected.', 'sc_events')));
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');

    $deleted = 0;
    $skipped = 0;

    foreach ($customer_ids as $customer_id) {
        $user = get_userdata($customer_id);
        if (!$user) {
            $skipped++;
            continue;
        }

        // Don't delete admins or event managers
        if (in_array('administrator', $user->roles) || in_array('event_manager', $user->roles)) {
            $skipped++;
            continue;
        }

        if (wp_delete_user($customer_id)) {
            $deleted++;
        } else {
            $skipped++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(__('%d customer(s) deleted. %d skipped.', 'sc_events'), $deleted, $skipped),
        'deleted' => $deleted,
        'skipped' => $skipped
    ));
}

/**
 * Bulk Email Customers
 */
add_action('wp_ajax_sc_bulk_email_customers', 'sc_bulk_email_customers');
function sc_bulk_email_customers() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_ids = isset($_POST['customer_ids']) ? array_map('intval', $_POST['customer_ids']) : array();
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

    if (empty($customer_ids)) {
        wp_send_json_error(array('message' => __('No customers selected.', 'sc_events')));
    }

    if (empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Subject and message are required.', 'sc_events')));
    }

    // Get platform name for sender
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    $sent = 0;
    $failed = 0;

    foreach ($customer_ids as $customer_id) {
        $user = get_userdata($customer_id);
        if (!$user || empty($user->user_email)) {
            $failed++;
            continue;
        }

        // Replace variables in message
        $personalized_message = str_replace('{name}', $user->display_name, $message);
        $personalized_message = str_replace('{email}', $user->user_email, $personalized_message);

        // HTML email template
        $html_message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                    <h1 style="margin: 0; font-size: 24px;">' . esc_html($platform_name) . '</h1>
                </div>
                <div style="padding: 40px 30px;">
                    <div style="color: #555; line-height: 1.6; font-size: 15px;">
                        ' . wpautop($personalized_message) . '
                    </div>
                </div>
                <div style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                    <p style="margin: 0; color: #999; font-size: 13px;">
                        &copy; ' . date('Y') . ' ' . esc_html($platform_name) . '. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>';

        // Send email with platform name as sender
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $platform_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );

        if (wp_mail($user->user_email, $subject, $html_message, $headers)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    wp_send_json_success(array(
        'message' => sprintf(__('Emails sent: %d, Failed: %d', 'sc_events'), $sent, $failed),
        'sent' => $sent,
        'failed' => $failed
    ));
}

/**
 * Send Email to Customer
 */
add_action('wp_ajax_sc_send_customer_email', 'sc_send_customer_email');
function sc_send_customer_email() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

    if (!$customer_id || empty($subject) || empty($message)) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'sc_events')));
    }

    $user = get_userdata($customer_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Customer not found.', 'sc_events')));
    }

    // Replace variables
    $message = str_replace('{name}', $user->display_name, $message);
    $message = str_replace('{email}', $user->user_email, $message);

    // Get platform name for sender
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));

    // HTML email template
    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 24px;">' . esc_html($platform_name) . '</h1>
            </div>
            <div style="padding: 40px 30px;">
                <div style="color: #555; line-height: 1.6; font-size: 15px;">
                    ' . wpautop($message) . '
                </div>
            </div>
            <div style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                <p style="margin: 0; color: #999; font-size: 13px;">
                    &copy; ' . date('Y') . ' ' . esc_html($platform_name) . '. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';

    // Send email with platform name as sender
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $platform_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    $sent = wp_mail($user->user_email, $subject, $html_message, $headers);

    if ($sent) {
        wp_send_json_success(array(
            'message' => __('Email sent successfully!', 'sc_events')
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to send email.', 'sc_events')));
    }
}

/**
 * Export Customers CSV
 */
add_action('wp_ajax_sc_export_customers_csv', 'sc_export_customers_csv');
function sc_export_customers_csv() {
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sc_dashboard_nonce')) {
        wp_die(__('Security check failed.', 'sc_events'));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die(__('Permission denied.', 'sc_events'));
    }

    global $wpdb;

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Build user query args
    $user_args = array(
        'role__in' => array('etn-customer', 'subscriber', 'customer'),
        'number' => -1,
        'orderby' => 'registered',
        'order' => 'DESC'
    );

    if (!empty($search)) {
        $user_args['search'] = '*' . $search . '*';
        $user_args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
    }

    // If filtering by event
    if ($event_id > 0) {
        $attendees = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'etn_attendee_user_id'
            AND p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = 'etn_event_id'
                AND pm2.meta_value = %d
            )
        ", $event_id));

        if (!empty($attendees)) {
            $user_args['include'] = array_map('intval', $attendees);
        } else {
            $user_args['include'] = array(0); // No results
        }
    }

    $user_query = new WP_User_Query($user_args);
    $users = $user_query->get_results();

    // Prepare CSV
    $filename = 'customers_export_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, array(
        'ID',
        'Username',
        'Display Name',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Events Attended',
        'Registered Date'
    ));

    foreach ($users as $user) {
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user->ID, 'phone', true);
        }

        // Count events
        $events_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT pm.meta_value)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID
            WHERE p.post_type = 'etn-attendee'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'etn_event_id'
            AND pm2.meta_key = 'etn_attendee_user_id'
            AND pm2.meta_value = %d
        ", $user->ID));

        fputcsv($output, array(
            $user->ID,
            $user->user_login,
            $user->display_name,
            $user->first_name,
            $user->last_name,
            $user->user_email,
            $phone,
            $events_count,
            date('Y-m-d H:i:s', strtotime($user->user_registered))
        ));
    }

    fclose($output);
    exit;
}

/**
 * Create Coupons
 */
add_action('wp_ajax_sc_create_coupons', 'sc_create_coupons_handler');
function sc_create_coupons_handler() {
    check_ajax_referer('sc_dashboard_nonce', 'nonce');

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }

    $prefix = sanitize_text_field($_POST['prefix']);
    $num_coupons = intval($_POST['num_coupons']);
    $discount_type = sanitize_text_field($_POST['discount_type']);
    $discount_value = floatval($_POST['discount_value']);
    $usage_limit = intval($_POST['usage_limit']);
    $event_id = intval($_POST['event_id']);
    $expiry_date = sanitize_text_field($_POST['expiry_date']);

    // Validate
    if ($num_coupons < 1 || $num_coupons > 100000) {
        wp_send_json_error(array('message' => 'Invalid number of coupons (1-100000)'));
    }

    if (!in_array($discount_type, ['percentage', 'fixed', 'free'])) {
        wp_send_json_error(array('message' => 'Invalid discount type'));
    }

    if ($discount_type !== 'free' && $discount_value <= 0) {
        wp_send_json_error(array('message' => 'Discount value must be greater than 0'));
    }

    $created = 0;
    $errors = array();

    for ($i = 1; $i <= $num_coupons; $i++) {
        // Generate unique code
        $code = $prefix . str_pad($i, 6, '0', STR_PAD_LEFT);

        // Check if code exists (using WP_Query since get_page_by_title is deprecated)
        $existing_query = new WP_Query(array(
            'post_type' => 'sc_coupon',
            'post_status' => 'any',
            'title' => $code,
            'posts_per_page' => 1
        ));
        if ($existing_query->have_posts()) {
            $errors[] = "Coupon $code already exists";
            wp_reset_postdata();
            continue;
        }
        wp_reset_postdata();

        // Create coupon post
        $coupon_data = array(
            'post_title' => $code,
            'post_type' => 'sc_coupon',
            'post_status' => 'publish'
        );

        $coupon_id = wp_insert_post($coupon_data);

        if (!$coupon_id || is_wp_error($coupon_id)) {
            $errors[] = "Failed to create coupon $code";
            continue;
        }

        // Save meta data (using sc_coupon meta keys)
        update_post_meta($coupon_id, 'discount_type', $discount_type);
        update_post_meta($coupon_id, 'discount_value', $discount_value);
        update_post_meta($coupon_id, 'usage_limit', $usage_limit);
        update_post_meta($coupon_id, 'usage_count', 0);

        if ($event_id > 0) {
            update_post_meta($coupon_id, 'event_id', $event_id);
        }

        if (!empty($expiry_date)) {
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
        }

        $created++;
    }

    if ($created > 0) {
        wp_send_json_success(array(
            'count' => $created,
            'message' => "Successfully created $created coupon(s)",
            'errors' => $errors
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Failed to create coupons',
            'errors' => $errors
        ));
    }
}
