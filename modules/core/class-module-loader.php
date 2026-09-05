<?php
/**
 * SC Events Module Loader
 *
 * Central system for loading and managing all modules
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Loader {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Registered modules
     */
    private $modules = array();

    /**
     * Loaded modules
     */
    private $loaded_modules = array();

    /**
     * Module load order (for dependencies)
     */
    private $load_order = array();

    /**
     * Modules directory path
     */
    private $modules_path;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->modules_path = get_template_directory() . '/modules';

        // Auto-discover and register modules
        add_action('after_setup_theme', array($this, 'discover_modules'), 5);

        // Load modules
        add_action('after_setup_theme', array($this, 'load_modules'), 10);

        // Initialize modules
        add_action('init', array($this, 'init_modules'), 5);
    }

    /**
     * Discover all available modules
     */
    public function discover_modules() {
        $modules_dir = $this->modules_path;

        if (!is_dir($modules_dir)) {
            return;
        }

        $directories = glob($modules_dir . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $module_name = basename($dir);

            // Skip core directory
            if ($module_name === 'core') {
                continue;
            }

            $module_file = $dir . '/module.php';

            if (file_exists($module_file)) {
                $this->register_module($module_name, $module_file);
            }
        }
    }

    /**
     * Register a module
     *
     * @param string|object $name_or_instance Module name or module instance
     * @param string $file Module file path (optional if passing instance)
     */
    public function register_module($name_or_instance, $file = null) {
        // If passed a module instance directly
        if (is_object($name_or_instance) && $name_or_instance instanceof SC_Base_Module) {
            $instance = $name_or_instance;
            $name = $instance->get_id();

            // Store the instance (hooks already registered in constructor)
            $this->modules[$name] = array(
                'name' => $name,
                'file' => null,
                'loaded' => true,
                'instance' => $instance,
            );
            $this->loaded_modules[$name] = $instance;

            return;
        }

        // Traditional registration with name and file
        $this->modules[$name_or_instance] = array(
            'name' => $name_or_instance,
            'file' => $file,
            'loaded' => false,
            'instance' => null,
        );
    }

    /**
     * Load all registered modules
     */
    public function load_modules() {
        // Get enabled modules from settings
        $enabled_modules = $this->get_enabled_modules();

        // Sort by dependencies
        $sorted = $this->sort_by_dependencies($enabled_modules);

        foreach ($sorted as $module_name) {
            $this->load_module($module_name);
        }
    }

    /**
     * Load a single module
     *
     * @param string $name Module name
     * @return bool Success
     */
    public function load_module($name) {
        if (!isset($this->modules[$name])) {
            return false;
        }

        if ($this->modules[$name]['loaded']) {
            return true;
        }

        $module = $this->modules[$name];

        // Load module file - the file will call register_module() with the instance
        require_once $module['file'];

        // Check if module registered itself (via sc_modules()->register_module(new Module()))
        if ($this->modules[$name]['loaded']) {
            return true;
        }

        return false;
    }

    /**
     * Initialize all loaded modules
     */
    public function init_modules() {
        foreach ($this->loaded_modules as $name => $instance) {
            if (method_exists($instance, 'init')) {
                $instance->init();
            }
        }

        // Fire action for other code to hook into
        do_action('sc_modules_loaded', $this->loaded_modules);
    }

    /**
     * Get enabled modules
     *
     * @return array Module names
     */
    public function get_enabled_modules() {
        global $wpdb;

        // Get from database table
        $table = $wpdb->prefix . 'sc_module_settings';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

        if (!$table_exists) {
            // Table doesn't exist yet, return all modules as enabled
            return array_keys($this->modules);
        }

        // Get disabled modules from database
        $disabled_modules = $wpdb->get_col(
            "SELECT module_id FROM $table WHERE is_enabled = 0"
        );

        if (empty($disabled_modules)) {
            // No disabled modules, return all
            return array_keys($this->modules);
        }

        // Filter out disabled modules
        $enabled = array();
        foreach ($this->modules as $module_name => $module_data) {
            if (!in_array($module_name, $disabled_modules)) {
                $enabled[] = $module_name;
            }
        }

        return $enabled;
    }

    /**
     * Check if a specific module is enabled
     *
     * @param string $module_id Module ID
     * @return bool
     */
    public function is_module_enabled($module_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sc_module_settings';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

        if (!$table_exists) {
            // Table doesn't exist, module is enabled by default
            return true;
        }

        // Check if module is explicitly disabled
        $is_disabled = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $table WHERE module_id = %s AND is_enabled = 0",
            $module_id
        ));

        return !$is_disabled;
    }

    /**
     * Ensure module settings table exists
     */
    private function ensure_table_exists() {
        global $wpdb;

        $table = $wpdb->prefix . 'sc_module_settings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                module_id varchar(100) NOT NULL,
                is_enabled tinyint(1) DEFAULT 1,
                settings longtext,
                enabled_by bigint(20) UNSIGNED DEFAULT NULL,
                enabled_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY module_id (module_id),
                KEY is_enabled (is_enabled)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Enable a module
     *
     * @param string $module_id Module ID
     * @return bool Success
     */
    public function enable_module($module_id) {
        global $wpdb;

        // Ensure table exists
        $this->ensure_table_exists();

        $table = $wpdb->prefix . 'sc_module_settings';

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE module_id = %s",
            $module_id
        ));

        $current_user_id = get_current_user_id();

        if ($exists) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                array(
                    'is_enabled' => 1,
                    'enabled_by' => $current_user_id,
                    'enabled_at' => current_time('mysql'),
                ),
                array('module_id' => $module_id),
                array('%d', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table,
                array(
                    'module_id' => $module_id,
                    'is_enabled' => 1,
                    'enabled_by' => $current_user_id,
                    'enabled_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%d', '%s')
            );
        }

        // Clear any caches
        wp_cache_delete('sc_enabled_modules');

        do_action('sc_module_enabled', $module_id);

        return $result !== false;
    }

    /**
     * Disable a module
     *
     * @param string $module_id Module ID
     * @return bool Success
     */
    public function disable_module($module_id) {
        global $wpdb;

        // Ensure table exists
        $this->ensure_table_exists();

        $table = $wpdb->prefix . 'sc_module_settings';

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE module_id = %s",
            $module_id
        ));

        $current_user_id = get_current_user_id();

        if ($exists) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                array(
                    'is_enabled' => 0,
                    'enabled_by' => $current_user_id,
                    'enabled_at' => current_time('mysql'),
                ),
                array('module_id' => $module_id),
                array('%d', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new record as disabled
            $result = $wpdb->insert(
                $table,
                array(
                    'module_id' => $module_id,
                    'is_enabled' => 0,
                    'enabled_by' => $current_user_id,
                    'enabled_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%d', '%s')
            );
        }

        // Clear any caches
        wp_cache_delete('sc_enabled_modules');

        do_action('sc_module_disabled', $module_id);

        return $result !== false;
    }

    /**
     * Get module settings from database
     *
     * @param string $module_id Module ID
     * @return array Module settings
     */
    public function get_module_settings($module_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'sc_module_settings';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE module_id = %s",
            $module_id
        ));

        if (!$row) {
            return array(
                'is_enabled' => true,
                'settings' => array(),
            );
        }

        return array(
            'is_enabled' => (bool) $row->is_enabled,
            'settings' => $row->settings ? json_decode($row->settings, true) : array(),
            'enabled_by' => $row->enabled_by,
            'enabled_at' => $row->enabled_at,
        );
    }

    /**
     * Update module settings in database
     *
     * @param string $module_id Module ID
     * @param array $settings Settings array
     * @return bool Success
     */
    public function update_module_settings($module_id, $settings) {
        global $wpdb;

        $table = $wpdb->prefix . 'sc_module_settings';

        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE module_id = %s",
            $module_id
        ));

        $settings_json = json_encode($settings);

        if ($exists) {
            $result = $wpdb->update(
                $table,
                array('settings' => $settings_json),
                array('module_id' => $module_id),
                array('%s'),
                array('%s')
            );
        } else {
            $result = $wpdb->insert(
                $table,
                array(
                    'module_id' => $module_id,
                    'is_enabled' => 1,
                    'settings' => $settings_json,
                ),
                array('%s', '%d', '%s')
            );
        }

        return $result !== false;
    }

    /**
     * Get all modules with their status
     *
     * @return array Modules with status info
     */
    public function get_all_modules_status() {
        $all_modules = array();

        foreach ($this->modules as $module_name => $module_data) {
            $settings = $this->get_module_settings($module_name);

            $info = array(
                'id' => $module_name,
                'name' => ucfirst(str_replace(array('-', '_'), ' ', $module_name)),
                'description' => '',
                'is_enabled' => $settings['is_enabled'],
                'is_loaded' => isset($this->loaded_modules[$module_name]),
            );

            // Get info from loaded module instance
            if (isset($module_data['instance']) && method_exists($module_data['instance'], 'get_info')) {
                $module_info = $module_data['instance']->get_info();
                $info = array_merge($info, $module_info);
            }

            $all_modules[$module_name] = $info;
        }

        return $all_modules;
    }

    /**
     * Sort modules by dependencies
     *
     * @param array $modules Module names
     * @return array Sorted module names
     */
    private function sort_by_dependencies($modules) {
        // TODO: Implement dependency resolution
        // For now, return as-is
        return $modules;
    }

    /**
     * Get a loaded module instance
     *
     * @param string $name Module name
     * @return object|null Module instance
     */
    public function get_module($name) {
        return isset($this->loaded_modules[$name]) ? $this->loaded_modules[$name] : null;
    }

    /**
     * Check if a module is loaded
     *
     * @param string $name Module name
     * @return bool
     */
    public function is_module_loaded($name) {
        return isset($this->loaded_modules[$name]);
    }

    /**
     * Get all loaded modules
     *
     * @return array
     */
    public function get_loaded_modules() {
        return $this->loaded_modules;
    }

    /**
     * Get all registered modules
     *
     * @return array
     */
    public function get_registered_modules() {
        return $this->modules;
    }

    /**
     * Get modules path
     *
     * @return string
     */
    public function get_modules_path() {
        return $this->modules_path;
    }

    /**
     * Get module path
     *
     * @param string $name Module name
     * @return string
     */
    public function get_module_path($name) {
        return $this->modules_path . '/' . $name;
    }

    /**
     * Get module URL
     *
     * @param string $name Module name
     * @return string
     */
    public function get_module_url($name) {
        return get_template_directory_uri() . '/modules/' . $name;
    }
}

/**
 * Get module loader instance
 *
 * @return SC_Module_Loader
 */
function sc_modules() {
    return SC_Module_Loader::get_instance();
}

/**
 * Get a specific module
 *
 * @param string $name Module name
 * @return object|null
 */
function sc_module($name) {
    return sc_modules()->get_module($name);
}

/**
 * Check if a module is enabled
 *
 * @param string $module_id Module ID
 * @return bool
 */
function sc_is_module_enabled($module_id) {
    return sc_modules()->is_module_enabled($module_id);
}

/**
 * Enable a module
 *
 * @param string $module_id Module ID
 * @return bool
 */
function sc_enable_module($module_id) {
    return sc_modules()->enable_module($module_id);
}

/**
 * Disable a module
 *
 * @param string $module_id Module ID
 * @return bool
 */
function sc_disable_module($module_id) {
    return sc_modules()->disable_module($module_id);
}
