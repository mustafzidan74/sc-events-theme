<?php
/**
 * SC Events - Utility Functions
 *
 * Common helper functions used throughout the theme
 * All functions are wrapped in function_exists() to prevent conflicts
 *
 * @package sc_events
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ===========================================
 * DATE & TIME UTILITIES
 * ===========================================
 */

if (!function_exists('sc_format_date')) {
    /**
     * Format date for display
     */
    function sc_format_date($date, $format = 'F d, Y') {
        if (empty($date)) {
            return '';
        }
        return date_i18n($format, strtotime($date));
    }
}

if (!function_exists('sc_format_time')) {
    /**
     * Format time for display
     */
    function sc_format_time($time, $format = 'g:i A') {
        if (empty($time)) {
            return '';
        }
        return date($format, strtotime($time));
    }
}

if (!function_exists('sc_format_date_range')) {
    /**
     * Format date range for display
     */
    function sc_format_date_range($start_date, $end_date) {
        if (empty($start_date)) {
            return '';
        }
        $start = sc_format_date($start_date);
        if (empty($end_date) || $start_date === $end_date) {
            return $start;
        }
        return $start . ' - ' . sc_format_date($end_date);
    }
}

if (!function_exists('sc_format_time_range')) {
    /**
     * Format time range for display
     */
    function sc_format_time_range($start_time, $end_time) {
        if (empty($start_time)) {
            return __('TBA', 'sc_events');
        }
        $start = sc_format_time($start_time);
        if (empty($end_time)) {
            return $start;
        }
        return $start . ' - ' . sc_format_time($end_time);
    }
}

if (!function_exists('sc_is_past_date')) {
    /**
     * Check if a date is in the past
     */
    function sc_is_past_date($date) {
        if (empty($date)) {
            return false;
        }
        return strtotime($date) < strtotime(date('Y-m-d'));
    }
}

if (!function_exists('sc_get_time_until')) {
    /**
     * Get time until event
     */
    function sc_get_time_until($date, $time = '00:00:00') {
        $datetime = strtotime($date . ' ' . $time);
        $now = current_time('timestamp');
        $diff = $datetime - $now;

        if ($diff < 0) {
            return false;
        }

        return array(
            'days'    => floor($diff / (60 * 60 * 24)),
            'hours'   => floor(($diff % (60 * 60 * 24)) / (60 * 60)),
            'minutes' => floor(($diff % (60 * 60)) / 60),
            'seconds' => $diff % 60,
        );
    }
}

/**
 * ===========================================
 * PRICE & CURRENCY UTILITIES
 * ===========================================
 */

if (!function_exists('sc_get_currencies')) {
    /**
     * Get list of supported currencies
     */
    function sc_get_currencies() {
        return array(
            'EGP' => array('name' => 'Egyptian Pound', 'symbol' => 'EGP', 'symbol_native' => 'ج.م'),
            'SAR' => array('name' => 'Saudi Riyal', 'symbol' => 'SAR', 'symbol_native' => 'ر.س'),
            'AED' => array('name' => 'UAE Dirham', 'symbol' => 'AED', 'symbol_native' => 'د.إ'),
            'KWD' => array('name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'symbol_native' => 'د.ك'),
            'QAR' => array('name' => 'Qatari Riyal', 'symbol' => 'QAR', 'symbol_native' => 'ر.ق'),
            'BHD' => array('name' => 'Bahraini Dinar', 'symbol' => 'BHD', 'symbol_native' => 'د.ب'),
            'OMR' => array('name' => 'Omani Rial', 'symbol' => 'OMR', 'symbol_native' => 'ر.ع'),
            'JOD' => array('name' => 'Jordanian Dinar', 'symbol' => 'JOD', 'symbol_native' => 'د.أ'),
            'LBP' => array('name' => 'Lebanese Pound', 'symbol' => 'LBP', 'symbol_native' => 'ل.ل'),
            'MAD' => array('name' => 'Moroccan Dirham', 'symbol' => 'MAD', 'symbol_native' => 'د.م'),
            'TND' => array('name' => 'Tunisian Dinar', 'symbol' => 'TND', 'symbol_native' => 'د.ت'),
            'USD' => array('name' => 'US Dollar', 'symbol' => '$', 'symbol_native' => '$'),
            'EUR' => array('name' => 'Euro', 'symbol' => '€', 'symbol_native' => '€'),
            'GBP' => array('name' => 'British Pound', 'symbol' => '£', 'symbol_native' => '£'),
            'CAD' => array('name' => 'Canadian Dollar', 'symbol' => 'C$', 'symbol_native' => '$'),
            'AUD' => array('name' => 'Australian Dollar', 'symbol' => 'A$', 'symbol_native' => '$'),
            'INR' => array('name' => 'Indian Rupee', 'symbol' => '₹', 'symbol_native' => '₹'),
            'PKR' => array('name' => 'Pakistani Rupee', 'symbol' => 'PKR', 'symbol_native' => 'Rs'),
            'TRY' => array('name' => 'Turkish Lira', 'symbol' => '₺', 'symbol_native' => '₺'),
            'MYR' => array('name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'symbol_native' => 'RM'),
            'SGD' => array('name' => 'Singapore Dollar', 'symbol' => 'S$', 'symbol_native' => '$'),
            'IDR' => array('name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'symbol_native' => 'Rp'),
            'PHP' => array('name' => 'Philippine Peso', 'symbol' => '₱', 'symbol_native' => '₱'),
            'THB' => array('name' => 'Thai Baht', 'symbol' => '฿', 'symbol_native' => '฿'),
            'CNY' => array('name' => 'Chinese Yuan', 'symbol' => '¥', 'symbol_native' => '¥'),
            'JPY' => array('name' => 'Japanese Yen', 'symbol' => '¥', 'symbol_native' => '¥'),
            'KRW' => array('name' => 'South Korean Won', 'symbol' => '₩', 'symbol_native' => '₩'),
            'ZAR' => array('name' => 'South African Rand', 'symbol' => 'R', 'symbol_native' => 'R'),
            'NGN' => array('name' => 'Nigerian Naira', 'symbol' => '₦', 'symbol_native' => '₦'),
            'KES' => array('name' => 'Kenyan Shilling', 'symbol' => 'KES', 'symbol_native' => 'KSh'),
            'GHS' => array('name' => 'Ghanaian Cedi', 'symbol' => 'GH₵', 'symbol_native' => 'GH₵'),
        );
    }
}

if (!function_exists('sc_get_currency')) {
    /**
     * Get current currency code
     */
    function sc_get_currency() {
        return get_option('sc_currency_code', 'EGP');
    }
}

if (!function_exists('sc_get_currency_symbol')) {
    /**
     * Get current currency symbol
     */
    function sc_get_currency_symbol() {
        $code = sc_get_currency();
        $currencies = sc_get_currencies();

        if (isset($currencies[$code])) {
            // Check for custom symbol override
            $custom_symbol = get_option('sc_currency_symbol', '');
            if (!empty($custom_symbol)) {
                return $custom_symbol;
            }
            return $currencies[$code]['symbol'];
        }
        return $code;
    }
}

if (!function_exists('sc_get_currency_settings')) {
    /**
     * Get all currency settings
     */
    function sc_get_currency_settings() {
        return array(
            'code' => get_option('sc_currency_code', 'EGP'),
            'symbol' => sc_get_currency_symbol(),
            'position' => get_option('sc_currency_position', 'after'), // before, after
            'thousand_separator' => get_option('sc_thousand_separator', ','),
            'decimal_separator' => get_option('sc_decimal_separator', '.'),
            'decimal_places' => intval(get_option('sc_decimal_places', 2)),
        );
    }
}

if (!function_exists('sc_format_price')) {
    /**
     * Format price for display using dynamic currency settings
     */
    function sc_format_price($price, $show_free = true, $override_currency = null) {
        $price = floatval($price);

        if ($price <= 0 && $show_free) {
            return __('Free', 'sc_events');
        }

        $settings = sc_get_currency_settings();

        // Allow currency override for specific cases
        $symbol = $override_currency ? $override_currency : $settings['symbol'];

        // Format the number
        $formatted = number_format(
            abs($price),
            $settings['decimal_places'],
            $settings['decimal_separator'],
            $settings['thousand_separator']
        );

        // Handle negative prices
        $prefix = $price < 0 ? '-' : '';

        // Apply position
        if ($settings['position'] === 'before') {
            return $prefix . $symbol . ' ' . $formatted;
        } else if ($settings['position'] === 'before_no_space') {
            return $prefix . $symbol . $formatted;
        } else if ($settings['position'] === 'after_no_space') {
            return $prefix . $formatted . $symbol;
        }

        // Default: after with space
        return $prefix . $formatted . ' ' . $symbol;
    }
}

if (!function_exists('sc_format_price_range')) {
    /**
     * Format price range for display
     */
    function sc_format_price_range($min_price, $max_price) {
        $min = floatval($min_price);
        $max = floatval($max_price);

        if ($min <= 0 && $max <= 0) {
            return __('Free', 'sc_events');
        }

        if ($min == $max || $max <= 0) {
            return sc_format_price($min, false);
        }

        if ($min <= 0) {
            return __('Free', 'sc_events') . ' - ' . sc_format_price($max, false);
        }

        return sc_format_price($min, false) . ' - ' . sc_format_price($max, false);
    }
}

if (!function_exists('sc_get_currency_for_js')) {
    /**
     * Get currency settings for JavaScript
     */
    function sc_get_currency_for_js() {
        $settings = sc_get_currency_settings();
        $settings['currencies'] = sc_get_currencies();
        return $settings;
    }
}

/**
 * ===========================================
 * STRING UTILITIES
 * ===========================================
 */

if (!function_exists('sc_truncate')) {
    /**
     * Truncate text to a specified length
     */
    function sc_truncate($text, $length = 100, $suffix = '...') {
        if (empty($text)) {
            return '';
        }
        $text = wp_strip_all_tags($text);
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - strlen($suffix)) . $suffix;
    }
}

if (!function_exists('sc_random_string')) {
    /**
     * Generate a random string
     */
    function sc_random_string($length = 16, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
        $string = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $string .= $chars[random_int(0, $max)];
        }
        return $string;
    }
}

if (!function_exists('sc_sanitize_slug')) {
    /**
     * Sanitize a slug
     */
    function sc_sanitize_slug($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}

/**
 * ===========================================
 * ARRAY UTILITIES
 * ===========================================
 */

if (!function_exists('sc_array_get')) {
    /**
     * Get value from array with default
     */
    function sc_array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

if (!function_exists('sc_array_pluck')) {
    /**
     * Pluck values from array of arrays/objects
     */
    function sc_array_pluck($array, $key) {
        $result = array();
        foreach ($array as $item) {
            if (is_object($item) && isset($item->$key)) {
                $result[] = $item->$key;
            } elseif (is_array($item) && isset($item[$key])) {
                $result[] = $item[$key];
            }
        }
        return $result;
    }
}

if (!function_exists('sc_array_group_by')) {
    /**
     * Group array by key
     */
    function sc_array_group_by($array, $key) {
        $result = array();
        foreach ($array as $item) {
            $group_key = is_object($item) ? $item->$key : $item[$key];
            if (!isset($result[$group_key])) {
                $result[$group_key] = array();
            }
            $result[$group_key][] = $item;
        }
        return $result;
    }
}

/**
 * ===========================================
 * VALIDATION UTILITIES
 * ===========================================
 */

if (!function_exists('sc_is_valid_email')) {
    /**
     * Validate email address
     */
    function sc_is_valid_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('sc_is_valid_phone')) {
    /**
     * Validate phone number (basic validation)
     */
    function sc_is_valid_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
}

if (!function_exists('sc_is_valid_url')) {
    /**
     * Validate URL
     */
    function sc_is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

/**
 * ===========================================
 * FILE UTILITIES
 * ===========================================
 */

if (!function_exists('sc_get_file_extension')) {
    /**
     * Get file extension
     */
    function sc_get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}

if (!function_exists('sc_is_image')) {
    /**
     * Check if file is an image
     */
    function sc_is_image($filename) {
        $extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');
        return in_array(sc_get_file_extension($filename), $extensions);
    }
}

if (!function_exists('sc_format_file_size')) {
    /**
     * Format file size for display
     */
    function sc_format_file_size($bytes, $decimals = 2) {
        $size = array('B', 'KB', 'MB', 'GB', 'TB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }
}

/**
 * ===========================================
 * AJAX UTILITIES
 * ===========================================
 */

if (!function_exists('sc_ajax_success')) {
    /**
     * Send JSON success response
     */
    function sc_ajax_success($data = null, $message = '') {
        $response = array('message' => $message);
        if ($data !== null) {
            if (is_array($data)) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }
        wp_send_json_success($response);
    }
}

if (!function_exists('sc_ajax_error')) {
    /**
     * Send JSON error response
     */
    function sc_ajax_error($message, $data = null) {
        $response = array('message' => $message);
        if ($data !== null) {
            $response = array_merge($response, (array) $data);
        }
        wp_send_json_error($response);
    }
}

if (!function_exists('sc_verify_ajax_nonce')) {
    /**
     * Verify AJAX nonce
     */
    function sc_verify_ajax_nonce($nonce = null, $action = 'sc_dashboard_nonce') {
        if ($nonce === null) {
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        }
        return wp_verify_nonce($nonce, $action);
    }
}

/**
 * ===========================================
 * USER UTILITIES
 * ===========================================
 */

if (!function_exists('sc_is_event_manager')) {
    /**
     * Check if current user has event manager role
     */
    function sc_is_event_manager() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('event_manager', $user->roles) || in_array('administrator', $user->roles);
    }
}

if (!function_exists('sc_is_event_scanner')) {
    /**
     * Check if current user has event scanner role
     */
    function sc_is_event_scanner() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('event_scanner', $user->roles);
    }
}

if (!function_exists('sc_get_user_display_name')) {
    /**
     * Get user display name
     */
    function sc_get_user_display_name($user = null) {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user || !$user->ID) {
            return __('Guest', 'sc_events');
        }

        if (!empty($user->display_name)) {
            return $user->display_name;
        }

        if (!empty($user->first_name)) {
            return $user->first_name . ($user->last_name ? ' ' . $user->last_name : '');
        }

        return $user->user_login;
    }
}

/**
 * ===========================================
 * LOCATION UTILITIES
 * ===========================================
 */

if (!function_exists('sc_build_location_string')) {
    /**
     * Build location string from components
     */
    function sc_build_location_string($venue_name = '', $address = '', $city = '', $country = '') {
        $parts = array();
        if (!empty($venue_name)) $parts[] = $venue_name;
        if (!empty($address)) $parts[] = $address;
        if (!empty($city)) $parts[] = $city;
        if (!empty($country)) $parts[] = $country;
        return implode(', ', $parts);
    }
}

if (!function_exists('sc_get_maps_url')) {
    /**
     * Get Google Maps URL for location
     */
    function sc_get_maps_url($location) {
        if (empty($location)) {
            return '';
        }
        return 'https://www.google.com/maps/search/' . urlencode($location);
    }
}

/**
 * ===========================================
 * QR CODE UTILITIES
 * ===========================================
 */

if (!function_exists('sc_generate_qr_data')) {
    /**
     * Generate QR code data for ticket
     */
    function sc_generate_qr_data($ticket_id, $ticket_code = '') {
        if (empty($ticket_code)) {
            return 'TICKET-' . $ticket_id;
        }
        return $ticket_code;
    }
}

/**
 * ===========================================
 * DEBUG UTILITIES
 * ===========================================
 */

if (!function_exists('sc_debug_log')) {
    /**
     * Log debug message (only in debug mode)
     */
    function sc_debug_log($message, $type = 'info') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        error_log('[SC Events ' . strtoupper($type) . '] ' . $message);
    }
}
