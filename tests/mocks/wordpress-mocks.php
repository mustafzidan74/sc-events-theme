<?php
/**
 * WordPress Mock Functions
 *
 * Provides mock implementations of WordPress functions for standalone testing
 *
 * @package sc_events
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(__FILE__))) . '/');
}

/**
 * Mock options storage
 */
global $mock_options, $mock_user_meta, $mock_transients;
$mock_options = array();
$mock_user_meta = array();
$mock_transients = array();

/**
 * Mock get_option
 */
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $mock_options;
        return isset($mock_options[$option]) ? $mock_options[$option] : $default;
    }
}

/**
 * Mock update_option
 */
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $mock_options;
        $mock_options[$option] = $value;
        return true;
    }
}

/**
 * Mock delete_option
 */
if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $mock_options;
        unset($mock_options[$option]);
        return true;
    }
}

/**
 * Mock get_user_meta
 */
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        global $mock_user_meta;
        if (empty($key)) {
            return isset($mock_user_meta[$user_id]) ? $mock_user_meta[$user_id] : array();
        }
        if (!isset($mock_user_meta[$user_id][$key])) {
            return $single ? '' : array();
        }
        return $single ? $mock_user_meta[$user_id][$key] : array($mock_user_meta[$user_id][$key]);
    }
}

/**
 * Mock update_user_meta
 */
if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value, $prev_value = '') {
        global $mock_user_meta;
        if (!isset($mock_user_meta[$user_id])) {
            $mock_user_meta[$user_id] = array();
        }
        $mock_user_meta[$user_id][$key] = $value;
        return true;
    }
}

/**
 * Mock delete_user_meta
 */
if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key, $value = '') {
        global $mock_user_meta;
        if (isset($mock_user_meta[$user_id][$key])) {
            unset($mock_user_meta[$user_id][$key]);
        }
        return true;
    }
}

/**
 * Mock set_transient
 */
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$key] = array(
            'value' => $value,
            'expiry' => $expiration > 0 ? time() + $expiration : 0
        );
        return true;
    }
}

/**
 * Mock get_transient
 */
if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $mock_transients;
        if (!isset($mock_transients[$key])) {
            return false;
        }
        if ($mock_transients[$key]['expiry'] > 0 && $mock_transients[$key]['expiry'] < time()) {
            unset($mock_transients[$key]);
            return false;
        }
        return $mock_transients[$key]['value'];
    }
}

/**
 * Mock delete_transient
 */
if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $mock_transients;
        unset($mock_transients[$key]);
        return true;
    }
}

/**
 * Mock wp_json_encode
 */
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

/**
 * Mock sanitize_text_field
 */
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Mock sanitize_email
 */
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

/**
 * Mock absint
 */
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

/**
 * Mock wp_parse_args
 */
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $parsed = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed = $args;
        } else {
            parse_str($args, $parsed);
        }
        return array_merge($defaults, $parsed);
    }
}

/**
 * Mock current_time
 */
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'timestamp') {
            return time();
        }
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return date($type);
    }
}

/**
 * Mock get_current_user_id
 */
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Default test user
    }
}

/**
 * Mock wp_get_current_user
 */
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $user = new stdClass();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->user_email = 'test@example.com';
        $user->display_name = 'Test User';
        return $user;
    }
}

/**
 * Mock __ (translation)
 */
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

/**
 * Mock _e (echo translation)
 */
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

/**
 * Mock esc_html
 */
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Mock esc_attr
 */
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Mock wp_hash_password
 */
if (!function_exists('wp_hash_password')) {
    function wp_hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

/**
 * Mock wp_check_password
 */
if (!function_exists('wp_check_password')) {
    function wp_check_password($password, $hash, $user_id = '') {
        return password_verify($password, $hash);
    }
}

/**
 * Mock add_action
 */
if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

/**
 * Mock add_filter
 */
if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

/**
 * Mock do_action
 */
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        return true;
    }
}

/**
 * Mock apply_filters
 */
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

/**
 * Mock get_template_directory
 */
if (!function_exists('get_template_directory')) {
    function get_template_directory() {
        return dirname(dirname(__DIR__));
    }
}

/**
 * Mock is_rtl
 */
if (!function_exists('is_rtl')) {
    function is_rtl() {
        return false;
    }
}

/**
 * Mock check_ajax_referer
 */
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return true;
    }
}

/**
 * Mock wp_send_json_success
 */
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo json_encode(array('success' => true, 'data' => $data));
    }
}

/**
 * Mock wp_send_json_error
 */
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo json_encode(array('success' => false, 'data' => $data));
    }
}

echo "WordPress Mocks Loaded\n";
