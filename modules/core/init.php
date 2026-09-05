<?php
/**
 * SC Events Modules Core Initialization
 *
 * Loads the core module system
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define modules constants
define('SC_MODULES_VERSION', '2.0.0');
define('SC_MODULES_PATH', get_template_directory() . '/modules');
define('SC_MODULES_URL', get_template_directory_uri() . '/modules');

// Load core classes
require_once SC_MODULES_PATH . '/core/class-base-module.php';
require_once SC_MODULES_PATH . '/core/class-module-loader.php';

// Initialize module loader
sc_modules();

/**
 * Helper function to check if a module exists and is loaded
 *
 * @param string $module_id Module ID
 * @return bool
 */
function sc_has_module($module_id) {
    return sc_modules()->is_module_loaded($module_id);
}

/**
 * Helper function to get module path
 *
 * @param string $module_id Module ID
 * @return string
 */
function sc_module_path($module_id) {
    return SC_MODULES_PATH . '/' . $module_id;
}

/**
 * Helper function to get module URL
 *
 * @param string $module_id Module ID
 * @return string
 */
function sc_module_url($module_id) {
    return SC_MODULES_URL . '/' . $module_id;
}

/**
 * Get all available modules for settings page
 *
 * @return array
 */
function sc_get_available_modules() {
    $modules = sc_modules()->get_registered_modules();
    $available = array();

    foreach ($modules as $id => $module) {
        if (isset($module['instance'])) {
            $info = $module['instance']->get_info();
            $available[$id] = $info;
        } else {
            $available[$id] = array(
                'id' => $id,
                'name' => ucfirst(str_replace('-', ' ', $id)),
                'description' => '',
                'enabled' => false,
            );
        }
    }

    return $available;
}
