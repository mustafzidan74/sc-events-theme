<?php
/**
 * Event Manager Dashboard Initialization
 *
 * @package sc_events
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Event Manager Dashboard Class
 */
class SC_Event_Manager_Dashboard {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_dashboard'));
        add_action('template_redirect', array($this, 'dashboard_template_redirect'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('init', array($this, 'add_rewrite_rules'));
        // Prevent canonical redirects on dashboard pages
        add_filter('redirect_canonical', array($this, 'prevent_dashboard_redirect'), 10, 2);

        // Hide WP admin bar for scanner users
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_for_scanners'));

        // Block wp-admin access for scanner users
        add_action('admin_init', array($this, 'block_admin_for_scanners'));
    }

    /**
     * Hide WP admin bar for everyone except administrators.
     * Admins still see the bar so they can navigate to wp-admin quickly.
     */
    public function hide_admin_bar_for_scanners($show) {
        if (!is_user_logged_in()) {
            return $show;
        }
        $user = wp_get_current_user();
        // Only administrators see the WordPress top bar
        if (!in_array('administrator', (array) $user->roles, true)) {
            return false;
        }
        return $show;
    }

    /**
     * Block wp-admin access for event_scanner users
     */
    public function block_admin_for_scanners() {
        if (wp_doing_ajax()) return;

        $user = wp_get_current_user();
        if (in_array('event_scanner', $user->roles)) {
            wp_redirect(home_url('/event-manager-dashboard/scanner'));
            exit;
        }
    }

    /**
     * Initialize Dashboard
     */
    public function init_dashboard() {
        // Register custom user role for Event Managers
        $this->register_event_manager_role();
    }

    /**
     * Register Event Manager Role
     */
    private function register_event_manager_role() {
        if (!get_role('event_manager')) {
            add_role(
                'event_manager',
                __('Event Manager', 'sc_events'),
                array(
                    'read' => true,
                    'edit_posts' => true,
                    'delete_posts' => true,
                    'upload_files' => true,
                )
            );
        }

        // Ensure existing event_manager role has upload_files (for installs before this cap was added)
        $event_manager = get_role('event_manager');
        if ($event_manager && !$event_manager->has_cap('upload_files')) {
            $event_manager->add_cap('upload_files');
        }

        // Register Event Scanner Role (scanner access only)
        if (!get_role('event_scanner')) {
            add_role(
                'event_scanner',
                __('Event Scanner', 'sc_events'),
                array(
                    'read' => true,
                    'scan_tickets' => true,
                )
            );
        }
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'dashboard_page';
        return $vars;
    }

    /**
     * Add rewrite rules for dashboard
     */
    public function add_rewrite_rules() {
        // Add specific rule for events page first to avoid conflicts
        add_rewrite_rule(
            '^event-manager-dashboard/events/?$',
            'index.php?dashboard_page=events',
            'top'
        );

        add_rewrite_rule(
            '^event-manager-dashboard/?$',
            'index.php?dashboard_page=home',
            'top'
        );

        add_rewrite_rule(
            '^event-manager-dashboard/([^/]+)/?$',
            'index.php?dashboard_page=$matches[1]',
            'top'
        );

        // Force flush rewrite rules to fix redirect issues
        // Change version number to force re-flush when rules are updated
        $rules_version = 'v8-workshops';
        if (get_option('sc_events_dashboard_rules_version') != $rules_version) {
            flush_rewrite_rules();
            update_option('sc_events_dashboard_rules_version', $rules_version);
        }
    }

    /**
     * Dashboard template redirect
     */
    public function dashboard_template_redirect() {
        $dashboard_page = get_query_var('dashboard_page');

        // Also check if URL matches dashboard pattern (fallback)
        if (!$dashboard_page) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if (preg_match('#/event-manager-dashboard/([^/]+)/?#', $request_uri, $matches)) {
                $dashboard_page = $matches[1];
            } elseif (preg_match('#/event-manager-dashboard/?$#', $request_uri)) {
                $dashboard_page = 'home';
            }
        }

        if ($dashboard_page) {
            // Features page is public (for client presentations)
            if ($dashboard_page === 'features') {
                $this->load_dashboard_template('features');
                exit;
            }

            // Check if user is logged in and has permission
            if (!is_user_logged_in()) {
                $this->load_dashboard_template('login');
                exit;
            }

            $current_user = wp_get_current_user();

            // Check for event_scanner role - only allow scanner page
            if (in_array('event_scanner', $current_user->roles)) {
                if ($dashboard_page !== 'scanner') {
                    wp_redirect(home_url('/event-manager-dashboard/scanner'));
                    exit;
                }
                $this->load_dashboard_template($dashboard_page);
                exit;
            }

            if (!in_array('event_manager', $current_user->roles) && !in_array('administrator', $current_user->roles)) {
                wp_redirect(home_url());
                exit;
            }

            // Load appropriate dashboard page
            $this->load_dashboard_template($dashboard_page);
            exit;
        }
    }

    /**
     * Prevent canonical redirects on dashboard pages
     */
    public function prevent_dashboard_redirect($redirect_url, $requested_url) {
        // Check if this is a dashboard page
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, '/event-manager-dashboard/') !== false || 
            strpos($requested_url, '/event-manager-dashboard/') !== false) {
            return false; // Prevent redirect
        }
        return $redirect_url;
    }

    /**
     * Get allowed dashboard pages (whitelist for security)
     */
    private function get_allowed_pages() {
        return array(
            'home',
            'login',
            'events',
            'events-new',
            'event-create',
            'event-edit',
            'event-view',
            'attendees',
            'attendee-add',
            'attendee-edit',
            'scanner',
            'customers',
            'speakers',
            'speaker-create',
            'speaker-edit',
            'organizers',
            'organizer-create',
            'organizer-edit',
            'categories',
            'coupons',
            'coupon-create',
            'coupon-edit',
            'coupon-categories',
            'workshops',
            'workshop-create',
            'workshop-edit',
            'workshop-attendees',
            'workshop-scanner',
            'certificate-templates',
            'certificate-template-create',
            'certificate-template-edit',
            'certificate-visual-builder',
            'certificates',
            'certificate-issue',
            'certificate-rebuild',
            'company-attendees',
            'company-attendee-add',
            'company-attendee-edit',
            'company-scanner',
            'reports',
            'settings',
            'support',
            'chat',
            'features',
            'booths',
            'booth-create',
            'booth-edit',
            'booth-types',
            'booth-type-create',
            'booth-type-edit',
            'booth-bookings',
            'booth-booking-create',
            'booth-booking-edit',
            'booth-floor-plan',
            // Sessions
            'sessions',
            'session-create',
            'session-edit',
            'session-attendees',
            // Scanners
            'scanners',
            'scanner-create',
            'scanner-edit',
            // Badges
            'badges',
            // Sponsors
            'sponsors',
            'sponsor-create',
            'sponsor-edit',
            // Partners
            'partners',
            'partner-create',
            'partner-edit',
            // Halls
            'halls',
            'hall-create',
            'hall-edit',
            // Schedules
            'schedules',
            'schedule-create',
            'schedule-edit',
            // Analytics
            'analytics',
            // Notifications
            'notifications',
            // Module Manager
            'module-manager'
        );
    }

    /**
     * Load dashboard template with security whitelist and module check
     */
    private function load_dashboard_template($page) {
        // Sanitize page name - only allow alphanumeric and hyphens
        $page = preg_replace('/[^a-z0-9\-]/', '', strtolower($page));

        // Security: Only allow whitelisted pages
        $allowed_pages = $this->get_allowed_pages();

        if (!in_array($page, $allowed_pages)) {
            $page = 'home'; // Default to home for invalid pages
        }

        // Check if page requires a module that is disabled
        if (function_exists('sc_get_required_module_for_page')) {
            $required_module = sc_get_required_module_for_page($page);
            if ($required_module !== null && function_exists('sc_module_active')) {
                if (!sc_module_active($required_module)) {
                    // Module is disabled - show access denied page
                    $this->show_module_disabled_page($required_module);
                    return;
                }
            }
        }

        $template_path = get_template_directory() . '/template-parts/dashboard/';
        $template_file = $template_path . $page . '.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            include $template_path . 'home.php';
        }
    }

    /**
     * Show module disabled access denied page
     *
     * @param string $module_id The disabled module ID
     */
    private function show_module_disabled_page($module_id) {
        $is_rtl = function_exists('sc_is_rtl') ? sc_is_rtl() : is_rtl();
        $dashboard_url = home_url('/event-manager-dashboard/');

        // Get module name
        $module_names = array(
            'events' => $is_rtl ? 'الفعاليات' : 'Events',
            'speakers' => $is_rtl ? 'المتحدثين' : 'Speakers',
            'attendees' => $is_rtl ? 'الحضور' : 'Attendees',
            'companies' => $is_rtl ? 'الشركات' : 'Companies',
            'certificates' => $is_rtl ? 'الشهادات' : 'Certificates',
            'booths' => $is_rtl ? 'الأجنحة' : 'Booths',
            'chat' => $is_rtl ? 'المحادثات' : 'Chat',
            'coupons' => $is_rtl ? 'الكوبونات' : 'Coupons',
        );

        $module_name = isset($module_names[$module_id]) ? $module_names[$module_id] : ucfirst($module_id);

        $page_title = $is_rtl ? 'الوحدة معطلة' : 'Module Disabled';
        get_template_part('template-parts/dashboard/components/dashboard', 'header');
        get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
        ?>
        <div id="main-content">
            <div class="container-fluid">
                <div class="block-header">
                    <div class="row">
                        <div class="col-12">
                            <h2><?php echo esc_html($page_title); ?></h2>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="body text-center py-5">
                                <div class="mb-4">
                                    <i class="fa fa-ban fa-5x text-danger"></i>
                                </div>
                                <h3 class="text-danger mb-3">
                                    <?php echo $is_rtl ? 'هذه الوحدة معطلة' : 'This Module is Disabled'; ?>
                                </h3>
                                <p class="text-muted mb-4">
                                    <?php
                                    if ($is_rtl) {
                                        echo 'وحدة <strong>' . esc_html($module_name) . '</strong> معطلة حالياً. يرجى التواصل مع المسؤول لتفعيلها.';
                                    } else {
                                        echo 'The <strong>' . esc_html($module_name) . '</strong> module is currently disabled. Please contact the administrator to enable it.';
                                    }
                                    ?>
                                </p>
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="<?php echo esc_url($dashboard_url . 'home'); ?>" class="btn btn-primary">
                                        <i class="fa fa-home"></i>
                                        <?php echo $is_rtl ? 'العودة للرئيسية' : 'Back to Dashboard'; ?>
                                    </a>
                                    <?php if (current_user_can('administrator')): ?>
                                    <a href="<?php echo esc_url($dashboard_url . 'module-manager'); ?>" class="btn btn-outline-secondary">
                                        <i class="fa fa-cog"></i>
                                        <?php echo $is_rtl ? 'إدارة الوحدات' : 'Module Manager'; ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        get_template_part('template-parts/dashboard/components/dashboard', 'footer');
    }

    /**
     * Enqueue Dashboard Assets
     */
    public function enqueue_dashboard_assets() {
        $dashboard_page = get_query_var('dashboard_page');

        if ($dashboard_page || (isset($_GET['dashboard_page']))) {
            // Dequeue default theme styles for dashboard pages
            wp_dequeue_style('sc_events-style');

            $assets_url = get_template_directory_uri() . '/assets/admin-dashboard/';

            // Bootstrap CSS
            wp_enqueue_style(
                'bootstrap',
                $assets_url . 'vendor/bootstrap/css/bootstrap.min.css',
                array(),
                '1.0.0'
            );

            // Font Awesome
            wp_enqueue_style(
                'fontawesome',
                $assets_url . 'vendor/font-awesome/css/font-awesome.min.css',
                array(),
                '1.0.0'
            );

            // Linearicons (Material Icons alternative)
            wp_enqueue_style(
                'linearicons',
                $assets_url . 'vendor/linearicons/style.css',
                array(),
                '1.0.0'
            );

            // Toastr CSS
            wp_enqueue_style(
                'toastr',
                $assets_url . 'vendor/toastr/toastr.min.css',
                array(),
                '1.0.0'
            );

            // MetisMenu CSS (for sidebar)
            wp_enqueue_style(
                'metismenu',
                $assets_url . 'vendor/metisMenu/metisMenu.css',
                array(),
                '1.0.0'
            );

            // DataTables CSS
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css',
                array('bootstrap'),
                '1.11.5'
            );

            // Main Dashboard CSS is loaded via dashboard-header.php
            // No need to enqueue again to avoid duplication

            // Custom Dashboard Fixes CSS
            wp_enqueue_style(
                'dashboard-fixes',
                get_template_directory_uri() . '/assets/css/dashboard-fixes.css',
                array('bootstrap'),
                '1.0.0'
            );

            // RTL Support CSS
            if (is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl())) {
                wp_enqueue_style(
                    'bootstrap-rtl',
                    $assets_url . 'vendor/bootstrap/css/bootstrap.min.rtl.css',
                    array('bootstrap'),
                    '5.0.0'
                );

                wp_enqueue_style(
                    'dashboard-rtl',
                    $assets_url . 'css/dashboard-rtl.css',
                    array('bootstrap'),
                    '1.0.0'
                );
            }

            // jQuery (WordPress includes it)
            wp_enqueue_script('jquery');

            // WordPress Media Library for image uploads
            wp_enqueue_media();

            // jQuery Slimscroll (alternative to perfect-scrollbar)
            wp_enqueue_script(
                'jquery-slimscroll',
                $assets_url . 'vendor/jquery-slimscroll/jquery.slimscroll.min.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // MetisMenu (for sidebar)
            wp_enqueue_script(
                'metismenu',
                $assets_url . 'vendor/metisMenu/metisMenu.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // DataTables JS
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js',
                array('jquery'),
                '1.11.5',
                true
            );

            // DataTables Bootstrap 4 integration
            wp_enqueue_script(
                'datatables-bootstrap4',
                'https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js',
                array('jquery', 'datatables'),
                '1.11.5',
                true
            );

            // UX Enhancements (Loading States, Toast, Form Validation)
            wp_enqueue_style(
                'sc-ux-enhancements',
                $assets_url . 'css/ux-enhancements.css',
                array(),
                '2.3.0'
            );

            wp_enqueue_script(
                'sc-ux-enhancements',
                $assets_url . 'js/ux-enhancements.js',
                array('jquery'),
                '2.3.0',
                true
            );

            // Note: Dashboard custom JS files are now modular and loaded in dashboard-footer.php:
            // - dashboard-core.js (error handling, DOM fixes)
            // - dashboard-alerts.js (SweetAlert2 wrapper functions)
            // - dashboard-init.js (initialization, tooltips, logout handler)
            // This ensures proper loading order with vendor scripts.

            // The scDashboard object is also defined in dashboard-footer.php
            // with proper escaping for security.
        }
    }

    /**
     * Check if user is event manager
     */
    public static function is_event_manager() {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();
        return in_array('event_manager', $current_user->roles) || in_array('administrator', $current_user->roles);
    }

    /**
     * Check if user is event scanner only
     */
    public static function is_event_scanner() {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();
        return in_array('event_scanner', $current_user->roles);
    }
}

// Initialize Dashboard
new SC_Event_Manager_Dashboard();
