<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for SC Events theme
 *
 * @package sc_events
 * @subpackage Tests
 */

// Define WordPress test environment constants
define('WP_DEBUG', true);
define('SC_EVENTS_TESTING', true);

// Load WordPress test environment if available
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (file_exists($wp_tests_dir . '/includes/functions.php')) {
    // WordPress test library is available
    require_once $wp_tests_dir . '/includes/functions.php';

    /**
     * Load the theme during tests
     */
    function _manually_load_theme() {
        switch_theme('sc_events');
    }
    tests_add_filter('muplugins_loaded', '_manually_load_theme');

    require $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Standalone mode - Mock WordPress functions
    require_once __DIR__ . '/mocks/wordpress-mocks.php';
}

// Load test base classes
require_once __DIR__ . '/class-sc-test-case.php';

echo "SC Events Test Suite Initialized\n";
