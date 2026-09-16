<?php
/**
 * SC Events custom API entry point.
 *
 *   GET  /wp-content/themes/sc_events/api.php?route=/events
 *   POST /wp-content/themes/sc_events/api.php?route=/auth/login
 *
 * It used to boot WordPress with SHORTINIT, which skips themes — so the very first line of work,
 * get_template_directory(), was undefined and every request died with a 500. WordPress is loaded
 * normally now: the theme's own files (models, sc_get_client_ip(), the database time zone) come
 * with it, and the router is the same one the routes are registered on (inc/api/api-loader.php).
 *
 * @package sc_events
 * @version 1.1.0
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('SC_API_VERSION', '1.1.0');
define('SC_API_REQUEST', true);

/** json_encode with the same flags as the API, for the one error that can happen before WordPress. */
function sc_api_json_fallback($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

// wp-load.php is four levels up (wp-content/themes/sc_events/api.php); walk up in case the theme
// lives somewhere else, then fall back to the document root.
$sc_api_wp_load = '';
$sc_api_dir = __DIR__;
for ($i = 0; $i < 6 && $sc_api_dir && $sc_api_dir !== dirname($sc_api_dir); $i++) {
    $sc_api_dir = dirname($sc_api_dir);
    if (file_exists($sc_api_dir . '/wp-load.php')) {
        $sc_api_wp_load = $sc_api_dir . '/wp-load.php';
        break;
    }
}
if ($sc_api_wp_load === '' && !empty($_SERVER['DOCUMENT_ROOT']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/wp-load.php')) {
    $sc_api_wp_load = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/wp-load.php';
}
if ($sc_api_wp_load === '') {
    http_response_code(500);
    echo sc_api_json_fallback(array(
        'success'    => false,
        'message'    => 'WordPress installation not found',
        'error_code' => 'WP_NOT_FOUND',
    ));
    exit;
}

// Nothing may be printed before the JSON: a notice from a plugin would break every client.
ini_set('display_errors', '0');
ob_start();

require_once $sc_api_wp_load;

// The theme loads inc/api/api-loader.php itself; this covers a child theme or a partial load.
if (!class_exists('SC_API_Router')) {
    $sc_api_loader = get_template_directory() . '/inc/api/api-loader.php';
    if (!file_exists($sc_api_loader)) {
        ob_end_clean();
        http_response_code(500);
        echo wp_json_encode(array('success' => false, 'message' => 'API loader not found.', 'error_code' => 'API_LOADER_NOT_FOUND'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once $sc_api_loader;
}

// Drop anything WordPress or a plugin may have echoed while loading.
if (ob_get_length()) {
    ob_end_clean();
} else {
    ob_end_flush();
}

try {
    $sc_api = new SC_API_Router();
    $sc_api->handle_request();
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('SC API error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo wp_json_encode(array(
        'success'    => false,
        'message'    => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR',
        'debug'      => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}
