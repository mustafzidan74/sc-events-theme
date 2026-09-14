<?php
/**
 * Settings page (template-parts/dashboard/settings.php): one save per section, each with its
 * own permission. Payment gateways and legal pages keep their handlers in
 * settings-ajax-handlers.php and pages-ajax-handlers.php.
 *
 * Event managers: brand, contact, homepage, currency, legal pages, their account.
 * Administrators also: site language, site identity (title/tagline/icon), payments, email test.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_settings_sections() {
    return array(
        'brand'    => 'manager',
        'contact'  => 'manager',
        'homepage' => 'manager',
        'currency' => 'manager',
        'language' => 'admin',
        'identity' => 'admin',
        'account'  => 'self',
    );
}

function sc_settings_can($level) {
    if ($level === 'admin') {
        return current_user_can('manage_options');
    }
    if ($level === 'manager') {
        return current_user_can('manage_options') || SC_Event_Manager_Dashboard::is_event_manager();
    }
    return is_user_logged_in();
}

add_action('wp_ajax_sc_settings_save', 'sc_settings_save');
function sc_settings_save() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : '';
    $sections = sc_settings_sections();
    if (!isset($sections[$section])) {
        wp_send_json_error(array('message' => __('Unknown settings section.', 'sc_events')));
    }
    if (!sc_settings_can($sections[$section])) {
        wp_send_json_error(array('message' => $sections[$section] === 'admin' ? __('Only site administrators can change this section.', 'sc_events') : __('Permission denied.', 'sc_events')), 403);
    }

    $in = function ($key) {
        return isset($_POST[$key]) ? trim(sanitize_text_field(wp_unslash($_POST[$key]))) : '';
    };
    $errors = array();
    $updates = array();

    switch ($section) {
        case 'brand':
            $name = $in('platform_name');
            if ($name === '') {
                $errors['platform_name'] = __('Enter the platform name.', 'sc_events');
            }
            $updates['sc_platform_name'] = $name;
            $updates['sc_platform_description'] = isset($_POST['platform_description']) ? sanitize_textarea_field(wp_unslash($_POST['platform_description'])) : '';
            foreach (array('platform_logo' => 'sc_platform_logo', 'platform_logo_light' => 'sc_platform_logo_light') as $field => $option) {
                $id = isset($_POST[$field]) ? absint($_POST[$field]) : 0;
                if ($id && !wp_attachment_is_image($id)) {
                    $errors[$field] = __('Choose an image file.', 'sc_events');
                }
                $updates[$option] = $id ?: '';
            }
            foreach (array('primary_color' => 'sc_primary_color', 'secondary_color' => 'sc_secondary_color') as $field => $option) {
                $color = sanitize_hex_color($in($field));
                if (!$color) {
                    $errors[$field] = __('Choose a colour.', 'sc_events');
                }
                $updates[$option] = $color;
            }
            $updates['sc_default_theme'] = $in('default_theme') === 'light' ? 'light' : 'dark';
            break;

        case 'contact':
            $email = sanitize_email(wp_unslash($_POST['platform_email'] ?? ''));
            if (!empty($_POST['platform_email']) && !is_email($email)) {
                $errors['platform_email'] = __('Enter a valid email address.', 'sc_events');
            }
            $updates['sc_platform_email'] = $email;
            $updates['sc_platform_phone'] = $in('platform_phone');
            $updates['sc_platform_whatsapp'] = preg_replace('/[^0-9+]/', '', $in('platform_whatsapp'));
            foreach (array('facebook', 'twitter', 'instagram', 'linkedin') as $net) {
                $url = $in('platform_' . $net);
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $errors['platform_' . $net] = __('Enter a full link starting with https://', 'sc_events');
                }
                $updates['sc_platform_' . $net] = $url !== '' ? esc_url_raw($url) : '';
            }
            break;

        case 'homepage':
            $updates['sc_homepage_mode'] = $in('homepage_mode') === 'single_event' ? 'single_event' : 'normal';
            $event_id = absint($_POST['featured_event_id'] ?? 0);
            global $wpdb;
            if ($event_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sc_events WHERE id = %d", $event_id))) {
                $errors['featured_event_id'] = __('That event no longer exists.', 'sc_events');
            }
            if ($updates['sc_homepage_mode'] === 'single_event' && !$event_id) {
                $errors['featured_event_id'] = __('Choose the event the homepage shows.', 'sc_events');
            }
            $updates['sc_featured_event_id'] = $event_id;
            break;

        case 'currency':
            $code = strtoupper($in('currency_code'));
            if (!array_key_exists($code, sc_get_currencies())) {
                $errors['currency_code'] = __('Choose a currency.', 'sc_events');
            }
            $position = $in('currency_position');
            $thousand = isset($_POST['thousand_separator']) ? (string) wp_unslash($_POST['thousand_separator']) : ',';
            $decimal = isset($_POST['decimal_separator']) ? (string) wp_unslash($_POST['decimal_separator']) : '.';
            $places = isset($_POST['decimal_places']) ? (int) $_POST['decimal_places'] : 2;
            if (!in_array($thousand, array(',', '.', ' ', ''), true)) {
                $errors['thousand_separator'] = __('Choose a thousands separator.', 'sc_events');
            }
            if (!in_array($decimal, array('.', ','), true)) {
                $errors['decimal_separator'] = __('Choose a decimal separator.', 'sc_events');
            } elseif ($decimal === $thousand) {
                $errors['decimal_separator'] = __('The decimal and thousands separators must differ.', 'sc_events');
            }
            if ($places < 0 || $places > 3) {
                $errors['decimal_places'] = __('Use between 0 and 3 decimal places.', 'sc_events');
            }
            $updates['sc_currency_code'] = $code;
            $updates['sc_currency_position'] = in_array($position, array('before', 'before_no_space', 'after', 'after_no_space'), true) ? $position : 'after';
            $updates['sc_thousand_separator'] = $thousand;
            $updates['sc_decimal_separator'] = $decimal;
            $updates['sc_decimal_places'] = $places;
            break;

        case 'language':
            $lang = $in('site_language');
            // '' lets each visitor pick (the switch shows); en/ar pin the whole site.
            $updates['sc_site_language'] = in_array($lang, array('en', 'ar'), true) ? $lang : '';
            break;

        case 'identity':
            $title = $in('site_title');
            if ($title === '') {
                $errors['site_title'] = __('Enter the site title.', 'sc_events');
            }
            $icon = absint($_POST['site_icon'] ?? 0);
            if ($icon && !wp_attachment_is_image($icon)) {
                $errors['site_icon'] = __('Choose an image file.', 'sc_events');
            }
            $updates['blogname'] = $title;
            $updates['blogdescription'] = $in('site_tagline');
            $updates['site_icon'] = $icon;
            break;

        case 'account':
            $user = wp_get_current_user();
            $current = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
            $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            $new = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
            if (!is_email($email)) {
                $errors['email'] = __('Enter a valid email address.', 'sc_events');
            } elseif (strcasecmp($email, $user->user_email) !== 0 && email_exists($email)) {
                $errors['email'] = __('Another account already uses this email.', 'sc_events');
            }
            $sensitive = strcasecmp($email, $user->user_email) !== 0 || $new !== '';
            if ($new !== '' && strlen($new) < 8) {
                $errors['new_password'] = __('Use at least 8 characters.', 'sc_events');
            }
            if ($sensitive && ($current === '' || !wp_check_password($current, $user->user_pass, $user->ID))) {
                $errors['current_password'] = __('Enter your current password to change your email or password.', 'sc_events');
            }
            if ($errors) {
                break;
            }
            $first = $in('first_name');
            $last = $in('last_name');
            $display = trim($first . ' ' . $last) ?: $user->display_name;
            update_user_meta($user->ID, 'first_name', $first);
            update_user_meta($user->ID, 'last_name', $last);
            $result = wp_update_user(array('ID' => $user->ID, 'display_name' => $display, 'user_email' => $email));
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            if ($new !== '') {
                wp_set_password($new, $user->ID);
                // wp_set_password signs every session out, this one included.
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true, is_ssl());
            }
            wp_send_json_success(array('message' => $new !== '' ? __('Account saved. Your password was changed and other sessions were signed out.', 'sc_events') : __('Account saved.', 'sc_events')));
    }

    if ($errors) {
        wp_send_json_error(array('message' => __('Please fix the highlighted fields.', 'sc_events'), 'errors' => $errors));
    }
    foreach ($updates as $option => $value) {
        update_option($option, $value);
    }
    wp_send_json_success(array('message' => __('Settings saved.', 'sc_events')));
}

add_action('wp_ajax_sc_settings_test_email', 'sc_settings_test_email');
function sc_settings_test_email() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sc_dashboard_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')), 403);
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Only site administrators can send a test email.', 'sc_events')), 403);
    }
    $to = wp_get_current_user()->user_email;
    $error = '';
    $catch = function ($wp_error) use (&$error) {
        $error = $wp_error->get_error_message();
    };
    add_action('wp_mail_failed', $catch);
    $sent = wp_mail(
        $to,
        sprintf(__('[%s] Test email', 'sc_events'), get_option('sc_platform_name', get_bloginfo('name'))),
        __("This is a test from the dashboard settings page. If you can read it, the site can send email.", 'sc_events')
    );
    remove_action('wp_mail_failed', $catch);
    if (!$sent) {
        /* translators: %s: error from the mail server */
        wp_send_json_error(array('message' => sprintf(__('The email was not sent: %s', 'sc_events'), $error ?: __('no reason given', 'sc_events'))));
    }
    /* translators: %s: email address */
    wp_send_json_success(array('message' => sprintf(__('Test email handed to the mail server for %s. Check the inbox (and spam).', 'sc_events'), $to)));
}
