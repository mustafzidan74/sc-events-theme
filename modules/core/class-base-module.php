<?php
/**
 * SC Events Base Module
 *
 * Abstract base class that all modules must extend
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SC_Base_Module {

    /**
     * Module ID (unique identifier)
     * @var string
     */
    protected $id;

    /**
     * Module name (display name)
     * @var string
     */
    protected $name;

    /**
     * Module description
     * @var string
     */
    protected $description = '';

    /**
     * Module version
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Module dependencies (other module IDs)
     * @var array
     */
    protected $dependencies = array();

    /**
     * Module priority (lower = loaded earlier)
     * @var int
     */
    protected $priority = 10;

    /**
     * Is module enabled
     * @var bool
     */
    protected $enabled = true;

    /**
     * Module path
     * @var string
     */
    protected $path;

    /**
     * Module URL
     * @var string
     */
    protected $url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->path = sc_modules()->get_module_path($this->id);
        $this->url = sc_modules()->get_module_url($this->id);

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     * Override in child class
     */
    public function register_hooks() {
        // Override in child class
    }

    /**
     * Initialize module
     * Called after all modules are loaded
     */
    public function init() {
        // Override in child class
    }

    /**
     * Get module info
     *
     * @return array
     */
    public function get_info() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'dependencies' => $this->dependencies,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
        );
    }

    /**
     * Get module ID
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get module name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get module path
     *
     * @param string $relative Optional relative path within module
     * @return string
     */
    public function get_path($relative = '') {
        if ($relative) {
            return $this->path . '/' . ltrim($relative, '/');
        }
        return $this->path;
    }

    /**
     * Get module URL
     *
     * @return string
     */
    public function get_url() {
        return $this->url;
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Check if dependencies are met
     *
     * @return bool
     */
    public function dependencies_met() {
        foreach ($this->dependencies as $dep) {
            if (!sc_modules()->is_module_loaded($dep)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Load a file from module directory
     *
     * @param string $file Relative file path
     * @return bool
     */
    protected function load_file($file) {
        $path = $this->path . '/' . $file;

        if (file_exists($path)) {
            require_once $path;
            return true;
        }

        return false;
    }

    /**
     * Load model class
     *
     * @param string $name Model name (e.g., 'event' loads class-event.php)
     */
    protected function load_model($name) {
        $this->load_file('class-' . $name . '.php');
    }

    /**
     * Load AJAX handlers
     */
    protected function load_ajax_handlers() {
        $this->load_file('ajax-handlers.php');
    }

    /**
     * Load REST API
     */
    protected function load_rest_api() {
        $this->load_file('rest-api.php');
    }

    /**
     * Enqueue module assets
     *
     * @param string $context 'admin' or 'frontend'
     */
    protected function enqueue_assets($context = 'admin') {
        $css_path = $this->path . '/assets/css/' . $context . '.css';
        $js_path = $this->path . '/assets/js/' . $context . '.js';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'sc-module-' . $this->id . '-' . $context,
                $this->url . '/assets/css/' . $context . '.css',
                array(),
                $this->version
            );
        }

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'sc-module-' . $this->id . '-' . $context,
                $this->url . '/assets/js/' . $context . '.js',
                array('jquery'),
                $this->version,
                true
            );
        }
    }

    /**
     * Get template path
     *
     * @param string $template Template name
     * @return string
     */
    protected function get_template_path($template) {
        return $this->path . '/templates/' . $template . '.php';
    }

    /**
     * Load template
     *
     * @param string $template Template name
     * @param array $args Variables to pass to template
     */
    protected function load_template($template, $args = array()) {
        $path = $this->get_template_path($template);

        if (file_exists($path)) {
            extract($args);
            include $path;
        }
    }

    /**
     * Render template and return as string
     *
     * @param string $template Template name
     * @param array $args Variables to pass to template
     * @return string
     */
    protected function render_template($template, $args = array()) {
        ob_start();
        $this->load_template($template, $args);
        return ob_get_clean();
    }

    /**
     * Add admin menu item for this module
     *
     * @param string $title Menu title
     * @param string $capability Required capability
     * @param string $callback Callback function
     * @param string $icon Menu icon
     * @param int $position Menu position
     */
    protected function add_menu_page($title, $capability = 'manage_options', $callback = '', $icon = '', $position = null) {
        add_menu_page(
            $title,
            $title,
            $capability,
            'sc-' . $this->id,
            $callback,
            $icon,
            $position
        );
    }

    /**
     * Add submenu item
     *
     * @param string $parent_slug Parent menu slug
     * @param string $title Menu title
     * @param string $capability Required capability
     * @param string $callback Callback function
     */
    protected function add_submenu_page($parent_slug, $title, $capability = 'manage_options', $callback = '') {
        add_submenu_page(
            $parent_slug,
            $title,
            $title,
            $capability,
            'sc-' . $this->id,
            $callback
        );
    }

    /**
     * Register AJAX action with automatic module check
     *
     * @param string $action Full action name (e.g., 'sc_get_speakers')
     * @param string|callable $callback Callback method name or callable
     * @param bool $nopriv Allow non-logged-in users
     * @param bool $skip_module_check Skip module enabled check (for core modules)
     */
    protected function register_ajax($action, $callback, $nopriv = false, $skip_module_check = false) {
        // Convert string callback to array with $this
        if (is_string($callback) && method_exists($this, $callback)) {
            $callback = array($this, $callback);
        }

        // Store module ID for the wrapper
        $module_id = $this->id;

        // Wrap callback with module check
        $wrapped_callback = function() use ($callback, $module_id, $skip_module_check) {
            // Check if module is enabled (skip for core modules)
            if (!$skip_module_check && function_exists('sc_module_active')) {
                if (!sc_module_active($module_id)) {
                    wp_send_json_error([
                        'message' => __('This feature is not available.', 'sc_events'),
                        'message_ar' => 'هذه الميزة غير متاحة.',
                        'code' => 'module_disabled',
                        'module' => $module_id
                    ]);
                    return;
                }
            }

            // Execute the original callback
            call_user_func($callback);
        };

        add_action('wp_ajax_' . $action, $wrapped_callback);

        if ($nopriv) {
            add_action('wp_ajax_nopriv_' . $action, $wrapped_callback);
        }
    }

    /**
     * Get module option
     *
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_option($key, $default = null) {
        $options = get_option('sc_module_' . $this->id . '_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Set module option
     *
     * @param string $key Option key
     * @param mixed $value Option value
     */
    protected function set_option($key, $value) {
        $options = get_option('sc_module_' . $this->id . '_options', array());
        $options[$key] = $value;
        update_option('sc_module_' . $this->id . '_options', $options);
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    protected function log($message, $level = 'info') {
        // Only log warnings and errors, skip info level
        if (WP_DEBUG && $level !== 'info') {
            error_log(sprintf('[SC %s] [%s] %s', strtoupper($this->id), strtoupper($level), $message));
        }
    }
}
