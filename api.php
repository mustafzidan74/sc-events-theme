<?php
/**
 * SC Events Custom REST API Entry Point
 *
 * This is the main entry point for the custom REST API.
 * It provides a faster, more secure alternative to WordPress REST API.
 *
 * Usage:
 *   GET  /wp-content/themes/sc_events/api.php?route=/events
 *   POST /wp-content/themes/sc_events/api.php?route=/auth/login
 *
 * Or with URL rewriting:
 *   GET  /api/v1/events
 *   POST /api/v1/auth/login
 *
 * @package sc_events
 * @version 1.0.0
 */

// Set error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON content type header early
header('Content-Type: application/json; charset=utf-8');

// Define API constants
define('SC_API_VERSION', '1.0.0');
define('SC_API_REQUEST', true);

// Minimal WordPress load for better performance
// This loads WordPress core without themes and plugins overhead
define('SHORTINIT', true);

// Find WordPress
$wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

if (!file_exists($wp_load_path)) {
    // Try alternative path
    $wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
}

if (!file_exists($wp_load_path)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'WordPress installation not found',
        'error_code' => 'WP_NOT_FOUND'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load WordPress core
require_once $wp_load_path;

// Since we're using SHORTINIT, we need to load some additional WordPress functions
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/capabilities.php';
require_once ABSPATH . WPINC . '/user.php';
require_once ABSPATH . WPINC . '/meta.php';
require_once ABSPATH . WPINC . '/pluggable.php';

// Load the theme's API files
$theme_dir = get_template_directory();

// Ensure theme directory is correct
if (!$theme_dir || !file_exists($theme_dir)) {
    $theme_dir = dirname(__FILE__);
}

// Load database models
$models_dir = $theme_dir . '/inc/database/';
if (file_exists($models_dir . 'class-base-model.php')) {
    require_once $models_dir . 'class-base-model.php';

    // Load all model files
    $model_files = glob($models_dir . 'class-*.php');
    foreach ($model_files as $model_file) {
        if (basename($model_file) !== 'class-base-model.php') {
            require_once $model_file;
        }
    }
}

// Load API core files
$api_dir = $theme_dir . '/inc/api/';

// Check if API files exist
if (!file_exists($api_dir . 'api-loader.php')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API loader not found. Please ensure API files are properly installed.',
        'error_code' => 'API_LOADER_NOT_FOUND'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load the API
require_once $api_dir . 'api-loader.php';

// Initialize and handle the request
try {
    $api = new SC_API_Router();
    $api->handle_request();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR',
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
