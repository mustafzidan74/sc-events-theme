<?php
/**
 * SC Asset Manager
 *
 * Handles CSS/JS minification, bundling, and caching
 * - Automatic minification in production
 * - Smart cache busting with version hashing
 * - Conditional loading based on page type
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Asset_Manager {

    /**
     * Whether to use minified assets
     */
    private static $use_minified = null;

    /**
     * Theme version for cache busting
     */
    private static $version = null;

    /**
     * Assets directory path
     */
    private static $assets_path = null;

    /**
     * Assets directory URL
     */
    private static $assets_url = null;

    /**
     * Initialize the asset manager
     */
    public static function init() {
        self::$assets_path = get_template_directory() . '/assets';
        self::$assets_url = get_template_directory_uri() . '/assets';
        self::$version = wp_get_theme()->get('Version');

        // Determine if we should use minified assets
        self::$use_minified = self::should_use_minified();

        // Register admin actions
        add_action('admin_init', array(__CLASS__, 'register_admin_actions'));
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));

        // Add cache busting to scripts and styles
        add_filter('script_loader_src', array(__CLASS__, 'add_cache_buster'), 10, 2);
        add_filter('style_loader_src', array(__CLASS__, 'add_cache_buster'), 10, 2);
    }

    /**
     * Check if we should use minified assets
     */
    private static function should_use_minified() {
        // Check for explicit setting
        $setting = get_option('sc_use_minified_assets', 'auto');

        if ($setting === 'yes') {
            return true;
        }

        if ($setting === 'no') {
            return false;
        }

        // Auto mode: use minified in production
        return !defined('WP_DEBUG') || !WP_DEBUG;
    }

    /**
     * Get the appropriate asset file (minified or regular)
     */
    public static function get_asset_url($file, $type = 'css') {
        if (!self::$use_minified) {
            return self::$assets_url . '/' . $file;
        }

        // Check for .min version
        $min_file = self::get_minified_path($file);
        $min_path = self::$assets_path . '/' . $min_file;

        if (file_exists($min_path)) {
            return self::$assets_url . '/' . $min_file;
        }

        return self::$assets_url . '/' . $file;
    }

    /**
     * Get minified file path from original
     */
    private static function get_minified_path($file) {
        $info = pathinfo($file);
        return $info['dirname'] . '/' . $info['filename'] . '.min.' . $info['extension'];
    }

    /**
     * Add cache buster to asset URLs
     */
    public static function add_cache_buster($src, $handle) {
        // Only process our theme assets
        if (strpos($src, get_template_directory_uri()) === false) {
            return $src;
        }

        // Add version based on file modification time for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $file_path = str_replace(
                get_template_directory_uri(),
                get_template_directory(),
                parse_url($src, PHP_URL_PATH)
            );

            if (file_exists($file_path)) {
                $version = filemtime($file_path);
                return add_query_arg('ver', $version, $src);
            }
        }

        return $src;
    }

    /**
     * Minify CSS content
     */
    public static function minify_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around special characters
        $css = preg_replace('/\s*([\{\}\:\;\,\>\+\~])\s*/', '$1', $css);

        // Remove trailing semicolons before closing braces
        $css = preg_replace('/;}/', '}', $css);

        // Remove leading/trailing whitespace
        $css = trim($css);

        return $css;
    }

    /**
     * Minify JS content (basic minification)
     */
    public static function minify_js($js) {
        // Remove single-line comments (but not URLs)
        $js = preg_replace('#(?<!:)//[^\r\n]*#', '', $js);

        // Remove multi-line comments
        $js = preg_replace('#/\*.*?\*/#s', '', $js);

        // Remove excess whitespace
        $js = preg_replace('/\s+/', ' ', $js);

        // Remove spaces around operators (careful version)
        $js = preg_replace('/\s*([{};,:])\s*/', '$1', $js);

        // Remove leading/trailing whitespace
        $js = trim($js);

        return $js;
    }

    /**
     * Minify a single file
     */
    public static function minify_file($source_path, $output_path = null) {
        if (!file_exists($source_path)) {
            return false;
        }

        $content = file_get_contents($source_path);
        $ext = pathinfo($source_path, PATHINFO_EXTENSION);

        if ($ext === 'css') {
            $minified = self::minify_css($content);
        } elseif ($ext === 'js') {
            $minified = self::minify_js($content);
        } else {
            return false;
        }

        if (!$output_path) {
            $output_path = self::get_minified_path($source_path);
        }

        $result = file_put_contents($output_path, $minified);

        return $result !== false;
    }

    /**
     * Minify all custom assets (not vendor files)
     */
    public static function minify_all_assets() {
        $results = array(
            'success' => array(),
            'failed' => array(),
            'skipped' => array()
        );

        // CSS files to minify
        $css_files = array(
            'admin-dashboard/css/certificate-visual-builder.css',
            'admin-dashboard/css/custom-enhancements.css',
            'admin-dashboard/css/dashboard-improvements.css',
            'admin-dashboard/css/dashboard-modern.css',
            'admin-dashboard/css/dashboard-pages.css',
            'admin-dashboard/css/dashboard-rtl.css',
            'admin-dashboard/css/main.css',
            'frontend/css/archive-events.css',
        );

        // JS files to minify
        $js_files = array(
            'admin-dashboard/js/certificate-visual-builder.js',
            'admin-dashboard/js/dashboard-core.js',
            'admin-dashboard/js/dashboard-init.js',
            'frontend/js/event-countdown.js',
        );

        // Process CSS files
        foreach ($css_files as $file) {
            $source = self::$assets_path . '/' . $file;
            if (!file_exists($source)) {
                $results['skipped'][] = $file;
                continue;
            }

            $min_file = self::get_minified_path($file);
            $output = self::$assets_path . '/' . $min_file;

            if (self::minify_file($source, $output)) {
                $results['success'][] = $min_file;
            } else {
                $results['failed'][] = $file;
            }
        }

        // Process JS files
        foreach ($js_files as $file) {
            $source = self::$assets_path . '/' . $file;
            if (!file_exists($source)) {
                $results['skipped'][] = $file;
                continue;
            }

            $min_file = self::get_minified_path($file);
            $output = self::$assets_path . '/' . $min_file;

            if (self::minify_file($source, $output)) {
                $results['success'][] = $min_file;
            } else {
                $results['failed'][] = $file;
            }
        }

        return $results;
    }

    /**
     * Create combined bundle files
     */
    public static function create_bundles() {
        $bundles = array(
            'dashboard-styles' => array(
                'type' => 'css',
                'files' => array(
                    'admin-dashboard/css/main.css',
                    'admin-dashboard/css/dashboard-pages.css',
                    'admin-dashboard/css/dashboard-modern.css',
                    'admin-dashboard/css/custom-enhancements.css',
                    'admin-dashboard/css/dashboard-improvements.css',
                ),
                'output' => 'admin-dashboard/bundles/dashboard.bundle.css'
            ),
            'dashboard-scripts' => array(
                'type' => 'js',
                'files' => array(
                    'admin-dashboard/js/dashboard-core.js',
                    'admin-dashboard/js/dashboard-init.js',
                ),
                'output' => 'admin-dashboard/bundles/dashboard-custom.bundle.js'
            ),
        );

        $results = array();

        foreach ($bundles as $name => $bundle) {
            $combined = '';

            foreach ($bundle['files'] as $file) {
                $path = self::$assets_path . '/' . $file;
                if (file_exists($path)) {
                    $content = file_get_contents($path);
                    $combined .= "/* Source: $file */\n" . $content . "\n\n";
                }
            }

            // Minify the combined content
            if ($bundle['type'] === 'css') {
                $combined = self::minify_css($combined);
            } else {
                $combined = self::minify_js($combined);
            }

            $output_path = self::$assets_path . '/' . $bundle['output'];

            // Ensure directory exists
            wp_mkdir_p(dirname($output_path));

            if (file_put_contents($output_path, $combined)) {
                $results[$name] = 'success';
            } else {
                $results[$name] = 'failed';
            }
        }

        return $results;
    }

    /**
     * Register admin actions
     */
    public static function register_admin_actions() {
        add_action('admin_post_sc_minify_assets', array(__CLASS__, 'handle_minify_action'));
        add_action('admin_post_sc_create_bundles', array(__CLASS__, 'handle_bundle_action'));
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            null, // Hidden from menu
            __('Asset Manager', 'sc_events'),
            __('Asset Manager', 'sc_events'),
            'manage_options',
            'sc-asset-manager',
            array(__CLASS__, 'render_admin_page')
        );
    }

    /**
     * Handle minify action
     */
    public static function handle_minify_action() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'sc_events'));
        }

        check_admin_referer('sc_minify_assets');

        $results = self::minify_all_assets();

        set_transient('sc_minify_results', $results, 60);

        wp_redirect(admin_url('admin.php?page=sc-asset-manager&minified=1'));
        exit;
    }

    /**
     * Handle bundle action
     */
    public static function handle_bundle_action() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'sc_events'));
        }

        check_admin_referer('sc_create_bundles');

        $results = self::create_bundles();

        set_transient('sc_bundle_results', $results, 60);

        wp_redirect(admin_url('admin.php?page=sc-asset-manager&bundled=1'));
        exit;
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $minify_results = get_transient('sc_minify_results');
        $bundle_results = get_transient('sc_bundle_results');
        delete_transient('sc_minify_results');
        delete_transient('sc_bundle_results');

        $current_setting = get_option('sc_use_minified_assets', 'auto');
        ?>
        <div class="wrap">
            <h1><?php _e('SC Events Asset Manager', 'sc_events'); ?></h1>

            <?php if ($minify_results): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php _e('Minification Complete!', 'sc_events'); ?></strong><br>
                        <?php printf(__('Success: %d files | Failed: %d | Skipped: %d', 'sc_events'),
                            count($minify_results['success']),
                            count($minify_results['failed']),
                            count($minify_results['skipped'])
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($bundle_results): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php _e('Bundles Created!', 'sc_events'); ?></strong><br>
                        <?php foreach ($bundle_results as $name => $status): ?>
                            <?php echo esc_html($name); ?>: <?php echo $status === 'success' ? '&#10004;' : '&#10008;'; ?><br>
                        <?php endforeach; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2><?php _e('Asset Settings', 'sc_events'); ?></h2>

                <form method="post" action="options.php">
                    <?php settings_fields('sc_asset_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Use Minified Assets', 'sc_events'); ?></th>
                            <td>
                                <select name="sc_use_minified_assets">
                                    <option value="auto" <?php selected($current_setting, 'auto'); ?>>
                                        <?php _e('Auto (Production only)', 'sc_events'); ?>
                                    </option>
                                    <option value="yes" <?php selected($current_setting, 'yes'); ?>>
                                        <?php _e('Always', 'sc_events'); ?>
                                    </option>
                                    <option value="no" <?php selected($current_setting, 'no'); ?>>
                                        <?php _e('Never', 'sc_events'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Save Settings', 'sc_events')); ?>
                </form>
            </div>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2><?php _e('Asset Actions', 'sc_events'); ?></h2>

                <p><?php _e('Use these tools to optimize your assets.', 'sc_events'); ?></p>

                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=sc_minify_assets'), 'sc_minify_assets'); ?>"
                       class="button button-primary">
                        <?php _e('Minify All Assets', 'sc_events'); ?>
                    </a>

                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=sc_create_bundles'), 'sc_create_bundles'); ?>"
                       class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Create Bundles', 'sc_events'); ?>
                    </a>
                </p>

                <p class="description">
                    <?php _e('Minification creates .min.css and .min.js versions of your custom assets.', 'sc_events'); ?><br>
                    <?php _e('Bundles combine multiple files into single files for faster loading.', 'sc_events'); ?>
                </p>
            </div>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2><?php _e('Current Status', 'sc_events'); ?></h2>

                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Mode', 'sc_events'); ?></strong></td>
                        <td><?php echo self::$use_minified ? __('Minified', 'sc_events') : __('Development', 'sc_events'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Theme Version', 'sc_events'); ?></strong></td>
                        <td><?php echo esc_html(self::$version); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WP_DEBUG', 'sc_events'); ?></strong></td>
                        <td><?php echo defined('WP_DEBUG') && WP_DEBUG ? __('Enabled', 'sc_events') : __('Disabled', 'sc_events'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('sc_asset_settings', 'sc_use_minified_assets');
    }
}

// Initialize
add_action('init', array('SC_Asset_Manager', 'init'));
add_action('admin_init', array('SC_Asset_Manager', 'register_settings'));
