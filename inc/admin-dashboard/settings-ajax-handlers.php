<?php
/**
 * Settings AJAX Handlers
 *
 * Platform settings, account settings, and SEO handlers
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update Event Manager Settings (General)
 */
add_action('wp_ajax_update_event_manager_settings', 'sc_update_event_manager_settings');
function sc_update_event_manager_settings() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Update settings from POST data
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            update_option('sc_' . sanitize_key($key), sanitize_text_field($value));
        }
    }

    wp_send_json_success(array('message' => __('Settings updated successfully!', 'sc_events')));
}

/**
 * Update Platform Settings
 */
add_action('wp_ajax_sc_update_platform_settings', 'sc_ajax_update_platform_settings');
function sc_ajax_update_platform_settings() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!current_user_can('event_manager') && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Platform Info
    update_option('sc_platform_name', sanitize_text_field($_POST['platform_name']));
    update_option('sc_platform_description', sanitize_textarea_field($_POST['platform_description']));

    // Social Links
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
        $whatsapp = preg_replace('/[^0-9+]/', '', $_POST['platform_whatsapp']);
        update_option('sc_platform_whatsapp', $whatsapp);
    }

    // Colors
    if (isset($_POST['primary_color'])) {
        update_option('sc_primary_color', sanitize_hex_color($_POST['primary_color']));
    }
    if (isset($_POST['secondary_color'])) {
        update_option('sc_secondary_color', sanitize_hex_color($_POST['secondary_color']));
    }

    // Default Theme
    if (isset($_POST['default_theme'])) {
        $theme = sanitize_text_field($_POST['default_theme']);
        if (in_array($theme, array('dark', 'light'))) {
            update_option('sc_default_theme', $theme);
        }
    }

    // Site Language
    if (isset($_POST['site_language'])) {
        $lang = sanitize_text_field($_POST['site_language']);
        if (in_array($lang, array('en', 'ar'))) {
            update_option('sc_site_language', $lang);
            // Sync: update current user's preference to match site language
            $uid = get_current_user_id();
            if ($uid) {
                update_user_meta($uid, 'sc_language', $lang);
                setcookie('sc_language', $lang, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    // Homepage Settings
    if (isset($_POST['homepage_mode'])) {
        $mode = in_array($_POST['homepage_mode'], array('normal', 'single_event')) ? $_POST['homepage_mode'] : 'normal';
        update_option('sc_homepage_mode', $mode);
    }
    if (isset($_POST['featured_event_id'])) {
        update_option('sc_featured_event_id', intval($_POST['featured_event_id']));
    }

    // Logo Upload
    if (!empty($_FILES['platform_logo']['name']) || !empty($_FILES['platform_logo_light']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        if (!empty($_FILES['platform_logo']['name'])) {
            $logo_id = media_handle_upload('platform_logo', 0);
            if (!is_wp_error($logo_id)) {
                update_option('sc_platform_logo', $logo_id);
            }
        }

        if (!empty($_FILES['platform_logo_light']['name'])) {
            $logo_light_id = media_handle_upload('platform_logo_light', 0);
            if (!is_wp_error($logo_light_id)) {
                update_option('sc_platform_logo_light', $logo_light_id);
            }
        }
    }

    wp_send_json_success(array('message' => __('Platform settings updated successfully!', 'sc_events')));
}

/**
 * Update Account Settings
 */
add_action('wp_ajax_sc_update_account_settings', 'sc_ajax_update_account_settings');
function sc_ajax_update_account_settings() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error(array('message' => __('User not logged in.', 'sc_events')));
    }

    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    update_user_meta($current_user_id, 'first_name', $first_name);
    update_user_meta($current_user_id, 'last_name', $last_name);

    wp_update_user(array(
        'ID' => $current_user_id,
        'display_name' => $first_name . ' ' . $last_name
    ));

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

    // The current password is required first: without it, anyone who reaches an
    // open session — a borrowed laptop, a stolen cookie — can take the account
    // over silently. The public account page already asks; this one did not.
    if (!empty($new_password)) {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $user = get_user_by('ID', $current_user_id);

        if (empty($current_password) || !$user || !wp_check_password($current_password, $user->user_pass, $current_user_id)) {
            wp_send_json_error(array('message' => __('Current password is incorrect.', 'sc_events')));
        }

        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => __('New password must be at least 6 characters.', 'sc_events')));
        }

        wp_set_password($new_password, $current_user_id);

        // wp_set_password logs every session out, this one included.
        wp_set_current_user($current_user_id);
        wp_set_auth_cookie($current_user_id, true, is_ssl());
    }

    wp_send_json_success(array('message' => __('Account settings updated successfully!', 'sc_events')));
}

/**
 * Update SEO Settings
 */
add_action('wp_ajax_sc_update_seo_settings', 'sc_update_seo_settings_handler');
function sc_update_seo_settings_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    if (isset($_POST['site_title'])) {
        update_option('blogname', sanitize_text_field($_POST['site_title']));
    }

    if (isset($_POST['site_tagline'])) {
        update_option('blogdescription', sanitize_text_field($_POST['site_tagline']));
    }

    if (isset($_FILES['site_icon']) && !empty($_FILES['site_icon']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('site_icon', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => __('Failed to upload site icon: ', 'sc_events') . $attachment_id->get_error_message()));
        }

        update_option('site_icon', $attachment_id);
    }

    wp_send_json_success(array('message' => __('SEO settings updated successfully!', 'sc_events')));
}

/**
 * Get Dashboard Stats
 */
add_action('wp_ajax_get_dashboard_stats', 'sc_get_dashboard_stats');
function sc_get_dashboard_stats() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    global $wpdb;

    // Get stats from custom tables
    $total_events = class_exists('SC_Event') ? SC_Event::count() : 0;
    $total_attendees = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active'");
    $total_speakers = class_exists('SC_Speaker') ? count(SC_Speaker::get_all(array('is_active' => null, 'limit' => 100000))) : 0;

    // Revenue
    $total_revenue = $wpdb->get_var("
        SELECT SUM(total_amount) FROM {$wpdb->prefix}sc_attendees
        WHERE payment_status IN ('success', 'completed')
    ");

    wp_send_json_success(array(
        'total_events' => intval($total_events),
        'total_attendees' => intval($total_attendees),
        'total_speakers' => intval($total_speakers),
        'total_revenue' => floatval($total_revenue ?: 0)
    ));
}

/**
 * Get Events List for Dropdowns
 */
add_action('wp_ajax_sc_get_events_list', 'sc_get_events_list');
function sc_get_events_list() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $events = array();

    if (class_exists('SC_Event')) {
        $all_events = SC_Event::get_all(array('status' => null, 'limit' => 1000));
        foreach ($all_events as $event) {
            $events[] = array(
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->start_date ? date('M j, Y', strtotime($event->start_date)) : ''
            );
        }
    }

    wp_send_json_success(array('events' => $events));
}

/**
 * ================================================
 * CURRENCY SETTINGS HANDLERS
 * ================================================
 */

/**
 * Update Currency Settings
 */
add_action('wp_ajax_sc_update_currency_settings', 'sc_update_currency_settings_handler');
function sc_update_currency_settings_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    // Validate currency code
    $currency_code = sanitize_text_field($_POST['currency_code'] ?? 'EGP');
    $currencies = sc_get_currencies();
    if (!isset($currencies[$currency_code])) {
        wp_send_json_error(array('message' => __('Invalid currency selected.', 'sc_events')));
    }

    // Save currency settings
    update_option('sc_currency_code', $currency_code);
    update_option('sc_currency_position', sanitize_text_field($_POST['currency_position'] ?? 'after'));
    update_option('sc_thousand_separator', sanitize_text_field($_POST['thousand_separator'] ?? ','));
    update_option('sc_decimal_separator', sanitize_text_field($_POST['decimal_separator'] ?? '.'));
    update_option('sc_decimal_places', intval($_POST['decimal_places'] ?? 2));

    wp_send_json_success(array('message' => __('Currency settings updated successfully!', 'sc_events')));
}

/**
 * ================================================
 * PAYMENT GATEWAY HANDLERS
 * ================================================
 */

/**
 * Toggle Payment Gateway (Enable/Disable)
 */
add_action('wp_ajax_sc_toggle_payment_gateway', 'sc_toggle_payment_gateway_handler');
function sc_toggle_payment_gateway_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $gateway = sanitize_text_field($_POST['gateway'] ?? '');
    $enabled = intval($_POST['enabled'] ?? 0);

    $valid_gateways = array('paymob', 'stripe', 'myfatoorah', 'kashier');
    if (!in_array($gateway, $valid_gateways)) {
        wp_send_json_error(array('message' => __('Invalid gateway.', 'sc_events')));
    }

    // Get existing settings
    $settings = get_option('sc_gateway_' . $gateway, array());
    $settings['enabled'] = $enabled ? true : false;
    update_option('sc_gateway_' . $gateway, $settings);

    $message = $enabled
        ? sprintf(__('%s gateway enabled.', 'sc_events'), ucfirst($gateway))
        : sprintf(__('%s gateway disabled.', 'sc_events'), ucfirst($gateway));

    wp_send_json_success(array('message' => $message));
}

/**
 * Save Payment Gateway Settings
 */
add_action('wp_ajax_sc_save_gateway_settings', 'sc_save_gateway_settings_handler');
function sc_save_gateway_settings_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $gateway = sanitize_text_field($_POST['gateway_code'] ?? '');

    $valid_gateways = array('paymob', 'stripe', 'myfatoorah', 'kashier');
    if (!in_array($gateway, $valid_gateways)) {
        wp_send_json_error(array('message' => __('Invalid gateway.', 'sc_events')));
    }

    // Get existing settings to preserve enabled state
    $settings = get_option('sc_gateway_' . $gateway, array());

    // Update test mode
    $settings['test_mode'] = isset($_POST['test_mode']) ? true : false;

    // Gateway-specific fields
    $gateway_fields = array(
        'paymob' => array('api_key', 'integration_id', 'iframe_id', 'hmac_secret'),
        'stripe' => array('publishable_key', 'secret_key', 'webhook_secret'),
        'myfatoorah' => array('api_key', 'country_iso'),
        'kashier' => array('merchant_id', 'api_key', 'secret_key'),
    );

    if (isset($gateway_fields[$gateway])) {
        foreach ($gateway_fields[$gateway] as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }
    }

    update_option('sc_gateway_' . $gateway, $settings);

    wp_send_json_success(array(
        'message' => sprintf(__('%s settings saved successfully!', 'sc_events'), ucfirst($gateway))
    ));
}

/**
 * Test Payment Gateway Connection
 */
add_action('wp_ajax_sc_test_gateway_connection', 'sc_test_gateway_connection_handler');
function sc_test_gateway_connection_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    if (!SC_Event_Manager_Dashboard::is_event_manager() && !current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $gateway = sanitize_text_field($_POST['gateway'] ?? '');
    $settings = get_option('sc_gateway_' . $gateway, array());

    switch ($gateway) {
        case 'paymob':
            if (empty($settings['api_key'])) {
                wp_send_json_error(array('message' => __('API Key is required.', 'sc_events')));
            }
            // Test Paymob authentication
            $response = wp_remote_post('https://accept.paymob.com/api/auth/tokens', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array('api_key' => $settings['api_key'])),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['token'])) {
                wp_send_json_success(array('message' => __('Paymob connection successful!', 'sc_events')));
            } else {
                wp_send_json_error(array('message' => $body['message'] ?? __('Authentication failed.', 'sc_events')));
            }
            break;

        case 'stripe':
            if (empty($settings['secret_key'])) {
                wp_send_json_error(array('message' => __('Secret Key is required.', 'sc_events')));
            }
            // Test Stripe connection
            $response = wp_remote_get('https://api.stripe.com/v1/balance', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['secret_key']
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                wp_send_json_success(array('message' => __('Stripe connection successful!', 'sc_events')));
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                wp_send_json_error(array('message' => $body['error']['message'] ?? __('Authentication failed.', 'sc_events')));
            }
            break;

        case 'myfatoorah':
            if (empty($settings['api_key'])) {
                wp_send_json_error(array('message' => __('API Key is required.', 'sc_events')));
            }
            $is_test = !empty($settings['test_mode']);
            $base_url = $is_test ? 'https://apitest.myfatoorah.com' : 'https://api.myfatoorah.com';

            $response = wp_remote_post($base_url . '/v2/InitiatePayment', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['api_key'],
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'InvoiceAmount' => 1,
                    'CurrencyIso' => 'KWD'
                )),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['IsSuccess']) && $body['IsSuccess']) {
                wp_send_json_success(array('message' => __('MyFatoorah connection successful!', 'sc_events')));
            } else {
                wp_send_json_error(array('message' => $body['Message'] ?? __('Authentication failed.', 'sc_events')));
            }
            break;

        case 'kashier':
            if (empty($settings['merchant_id']) || empty($settings['api_key'])) {
                wp_send_json_error(array('message' => __('Merchant ID and API Key are required.', 'sc_events')));
            }
            // Kashier doesn't have a simple test endpoint, so we just validate the format
            wp_send_json_success(array('message' => __('Kashier credentials saved. Connection will be verified on first transaction.', 'sc_events')));
            break;

        default:
            wp_send_json_error(array('message' => __('Invalid gateway.', 'sc_events')));
    }
}

/**
 * Get enabled payment gateways for checkout
 */
function sc_get_enabled_payment_gateways() {
    $gateways = array();
    $available = array('paymob', 'stripe', 'myfatoorah', 'kashier');

    foreach ($available as $code) {
        $settings = get_option('sc_gateway_' . $code, array());
        if (!empty($settings['enabled'])) {
            $gateways[$code] = array(
                'code' => $code,
                'name' => ucfirst($code),
                'test_mode' => !empty($settings['test_mode']),
                'settings' => $settings
            );
        }
    }

    return $gateways;
}

/**
 * ================================================
 * MODULE MANAGER HANDLERS
 * ================================================
 */

/**
 * Update Module Settings (Enable/Disable)
 */
add_action('wp_ajax_sc_update_modules', 'sc_update_modules_handler');
function sc_update_modules_handler() {
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sc_module_manager')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions - admin only
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied. Administrator access required.', 'sc_events')));
    }

    // Get changes from POST
    $changes_json = isset($_POST['changes']) ? stripslashes($_POST['changes']) : '';
    $changes = json_decode($changes_json, true);

    if (!is_array($changes) || empty($changes)) {
        wp_send_json_error(array('message' => __('No changes provided.', 'sc_events')));
    }

    $success_count = 0;
    $errors = array();

    foreach ($changes as $change) {
        if (!isset($change['module_id']) || !isset($change['is_enabled'])) {
            continue;
        }

        $module_id = sanitize_key($change['module_id']);
        $is_enabled = (bool) $change['is_enabled'];

        if ($is_enabled) {
            if (function_exists('sc_enable_module')) {
                $result = sc_enable_module($module_id);
            } else {
                $result = false;
            }
        } else {
            if (function_exists('sc_disable_module')) {
                $result = sc_disable_module($module_id);
            } else {
                $result = false;
            }
        }

        if ($result) {
            $success_count++;
        } else {
            $errors[] = $module_id;
        }
    }

    if ($success_count > 0 && empty($errors)) {
        wp_send_json_success(array(
            'message' => sprintf(__('%d module(s) updated successfully.', 'sc_events'), $success_count)
        ));
    } elseif ($success_count > 0) {
        wp_send_json_success(array(
            'message' => sprintf(__('%d module(s) updated. Some modules could not be updated: %s', 'sc_events'), $success_count, implode(', ', $errors))
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to update modules.', 'sc_events')));
    }
}

/**
 * Get Module Status
 */
add_action('wp_ajax_sc_get_module_status', 'sc_get_module_status_handler');
function sc_get_module_status_handler() {
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sc_module_manager')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $module_id = isset($_POST['module_id']) ? sanitize_key($_POST['module_id']) : '';

    if (empty($module_id)) {
        // Return all modules
        if (function_exists('sc_modules')) {
            $modules = sc_modules()->get_all_modules_status();
            wp_send_json_success(array('modules' => $modules));
        } else {
            wp_send_json_error(array('message' => __('Module system not available.', 'sc_events')));
        }
    } else {
        // Return specific module
        if (function_exists('sc_modules')) {
            $settings = sc_modules()->get_module_settings($module_id);
            wp_send_json_success(array('module' => $settings));
        } else {
            wp_send_json_error(array('message' => __('Module system not available.', 'sc_events')));
        }
    }
}

/**
 * Toggle Single Module (Enable/Disable)
 */
add_action('wp_ajax_sc_toggle_module', 'sc_toggle_module_handler');
function sc_toggle_module_handler() {
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'sc_module_manager')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }

    // Check permissions
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'sc_events')));
    }

    $module_id = isset($_POST['module_id']) ? sanitize_key($_POST['module_id']) : '';
    $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

    if (empty($module_id)) {
        wp_send_json_error(array('message' => __('Module ID is required.', 'sc_events')));
    }

    if ($action === 'enable') {
        if (function_exists('sc_enable_module')) {
            $result = sc_enable_module($module_id);
            if ($result) {
                wp_send_json_success(array('message' => sprintf(__('Module "%s" enabled successfully.', 'sc_events'), $module_id)));
            } else {
                wp_send_json_error(array('message' => __('Failed to enable module.', 'sc_events')));
            }
        }
    } elseif ($action === 'disable') {
        if (function_exists('sc_disable_module')) {
            $result = sc_disable_module($module_id);
            if ($result) {
                wp_send_json_success(array('message' => sprintf(__('Module "%s" disabled successfully.', 'sc_events'), $module_id)));
            } else {
                wp_send_json_error(array('message' => __('Failed to disable module.', 'sc_events')));
            }
        }
    } else {
        wp_send_json_error(array('message' => __('Invalid action. Use "enable" or "disable".', 'sc_events')));
    }
}
