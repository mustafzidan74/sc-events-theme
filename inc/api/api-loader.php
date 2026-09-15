<?php
/**
 * SC Events API Loader
 *
 * Loads all API classes and initializes the API system
 *
 * @package sc_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define API directory
define('SC_API_DIR', dirname(__FILE__) . '/');

require_once dirname(__DIR__) . '/sc-client-ip.php';
require_once dirname(__DIR__) . '/sc-db-timezone.php';

// Load core classes
require_once SC_API_DIR . 'class-api-response.php';
require_once SC_API_DIR . 'class-api-auth.php';
require_once SC_API_DIR . 'class-api-validator.php';
require_once SC_API_DIR . 'class-api-rate-limiter.php';
require_once SC_API_DIR . 'class-api-router.php';

// Load middleware
require_once SC_API_DIR . 'middleware/class-cors-middleware.php';
require_once SC_API_DIR . 'middleware/class-auth-middleware.php';
require_once SC_API_DIR . 'middleware/class-log-middleware.php';

// Load base endpoint class
require_once SC_API_DIR . 'endpoints/class-base-endpoint.php';

// Load all endpoints
$endpoint_files = glob(SC_API_DIR . 'endpoints/class-*-endpoint.php');
foreach ($endpoint_files as $endpoint_file) {
    require_once $endpoint_file;
}
