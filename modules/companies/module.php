<?php
/**
 * Companies Module
 *
 * B2B company attendee management for exhibitions and trade shows
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Companies_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'companies';

    /**
     * Module name
     */
    public $name = 'Companies';

    /**
     * Module description
     */
    public $description = 'B2B company attendee management for exhibitions and trade shows';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Dependencies
     */
    public $dependencies = array('events', 'tickets');

    /**
     * Priority
     */
    public $priority = 45;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load the Company Attendee class
        add_action('init', array($this, 'load_classes'), 5);

        // AJAX handlers
        $this->register_ajax('sc_get_companies', 'ajax_get_companies');
        $this->register_ajax('sc_get_company', 'ajax_get_company');
        $this->register_ajax('sc_save_company', 'ajax_save_company');
        $this->register_ajax('sc_delete_company', 'ajax_delete_company');
        $this->register_ajax('sc_company_check_in', 'ajax_check_in');
        $this->register_ajax('sc_company_check_out', 'ajax_check_out');
        $this->register_ajax('sc_export_companies', 'ajax_export_companies');
        $this->register_ajax('sc_company_stats', 'ajax_get_stats');

        // Dashboard hooks
        add_filter('sc_dashboard_stats', array($this, 'add_company_stats'));

        // Event meta box
        add_action('sc_event_meta_box', array($this, 'render_companies_summary'), 50);

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize module
     */
    public function init() {
        // Additional initialization
    }

    /**
     * Load model classes
     */
    public function load_classes() {
        if (!class_exists('SC_Company_Attendee')) {
            $this->load_file('../../inc/database/class-sc-company-attendee.php');
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sc-companies') === false && strpos($hook, 'post') === false) {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/companies';

        // CSS
        if (file_exists($this->get_path('assets/css/companies-admin.css'))) {
            wp_enqueue_style(
                'sc-companies-admin',
                $module_url . '/assets/css/companies-admin.css',
                array(),
                $this->version
            );
        }

        // JS
        if (file_exists($this->get_path('assets/js/companies-admin.js'))) {
            wp_enqueue_script(
                'sc-companies-admin',
                $module_url . '/assets/js/companies-admin.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('sc-companies-admin', 'scCompaniesConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_companies_nonce'),
                'i18n' => array(
                    'confirmDelete' => __('Are you sure you want to delete this company?', 'sc_events'),
                    'confirmCheckIn' => __('Check in this company?', 'sc_events'),
                    'confirmCheckOut' => __('Check out this company?', 'sc_events'),
                    'checkedIn' => __('Checked In', 'sc_events'),
                    'checkedOut' => __('Checked Out', 'sc_events'),
                    'saving' => __('Saving...', 'sc_events'),
                    'saved' => __('Saved', 'sc_events'),
                    'error' => __('An error occurred', 'sc_events'),
                    'exporting' => __('Exporting...', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * Add company stats to dashboard
     */
    public function add_company_stats($stats) {
        global $wpdb;
        $table = SC_Company_Attendee::get_table();

        $stats['companies'] = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'"),
            'checked_in' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active' AND checked_in = 1"),
            'revenue' => (float) $wpdb->get_var("SELECT SUM(amount_paid) FROM $table WHERE status = 'active' AND payment_status = 'success'"),
        );

        return $stats;
    }

    /**
     * Render companies summary in event meta box
     */
    public function render_companies_summary($post) {
        $event_id = $post->ID;
        $stats = SC_Company_Attendee::get_stats($event_id);

        if ($stats['total'] === 0) {
            return;
        }
        ?>
        <div class="sc-meta-section">
            <h3><?php _e('Companies', 'sc_events'); ?></h3>
            <div class="sc-stats-grid">
                <div class="sc-stat">
                    <span class="sc-stat-value"><?php echo esc_html($stats['total']); ?></span>
                    <span class="sc-stat-label"><?php _e('Registered', 'sc_events'); ?></span>
                </div>
                <div class="sc-stat">
                    <span class="sc-stat-value"><?php echo esc_html($stats['checked_in']); ?></span>
                    <span class="sc-stat-label"><?php _e('Checked In', 'sc_events'); ?></span>
                </div>
                <div class="sc-stat">
                    <span class="sc-stat-value"><?php echo sc_format_price($stats['revenue'], false); ?></span>
                    <span class="sc-stat-label"><?php _e('Revenue', 'sc_events'); ?></span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=sc-companies&event_id=' . $event_id); ?>" class="button">
                <?php _e('Manage Companies', 'sc_events'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * AJAX: Get companies list
     */
    public function ajax_get_companies() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $args = array(
            'event_id' => intval($_POST['event_id'] ?? 0) ?: null,
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'payment_status' => sanitize_text_field($_POST['payment_status'] ?? ''),
            'checked_in' => isset($_POST['checked_in']) && $_POST['checked_in'] !== '' ? (bool) $_POST['checked_in'] : null,
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0),
        );

        $result = SC_Company_Attendee::get_list($args);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get single company
     */
    public function ajax_get_company() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid company ID', 'sc_events')));
        }

        $company = SC_Company_Attendee::get($id);

        if (!$company) {
            wp_send_json_error(array('message' => __('Company not found', 'sc_events')));
        }

        // Get check-in history
        $history = SC_Company_Attendee::get_checkin_history($id);

        wp_send_json_success(array(
            'company' => $company,
            'history' => $history,
        ));
    }

    /**
     * AJAX: Save company
     */
    public function ajax_save_company() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);
        $data = array(
            'event_id' => intval($_POST['event_id'] ?? 0),
            'ticket_id' => intval($_POST['ticket_id'] ?? 0),
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'company_name_ar' => sanitize_text_field($_POST['company_name_ar'] ?? ''),
            'industry' => sanitize_text_field($_POST['industry'] ?? ''),
            'company_size' => sanitize_text_field($_POST['company_size'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'contact_title' => sanitize_text_field($_POST['contact_title'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'booth_number' => sanitize_text_field($_POST['booth_number'] ?? ''),
            'sponsorship_level' => sanitize_text_field($_POST['sponsorship_level'] ?? ''),
            'company_logo' => intval($_POST['company_logo'] ?? 0),
            'payment_status' => sanitize_text_field($_POST['payment_status'] ?? 'pending'),
            'amount_paid' => floatval($_POST['amount_paid'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        // Handle extra fields
        if (isset($_POST['extra_fields']) && is_array($_POST['extra_fields'])) {
            $data['extra_fields'] = array_map('sanitize_text_field', $_POST['extra_fields']);
        }

        // Handle social media
        if (isset($_POST['social_media']) && is_array($_POST['social_media'])) {
            $social_media = array();
            foreach ($_POST['social_media'] as $key => $value) {
                $social_media[sanitize_key($key)] = esc_url_raw($value);
            }
            $data['social_media'] = $social_media;
        }

        // Validate required fields
        if (empty($data['company_name'])) {
            wp_send_json_error(array('message' => __('Company name is required', 'sc_events')));
        }

        if (empty($data['contact_email'])) {
            wp_send_json_error(array('message' => __('Contact email is required', 'sc_events')));
        }

        if (empty($data['event_id'])) {
            wp_send_json_error(array('message' => __('Event is required', 'sc_events')));
        }

        if ($id) {
            $result = SC_Company_Attendee::update($id, $data);
            $message = __('Company updated', 'sc_events');
        } else {
            $id = SC_Company_Attendee::create($data);
            $result = $id !== false;
            $message = __('Company registered', 'sc_events');
        }

        if ($result) {
            $company = SC_Company_Attendee::get($id);
            wp_send_json_success(array(
                'message' => $message,
                'company' => $company,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save company', 'sc_events')));
        }
    }

    /**
     * AJAX: Delete company
     */
    public function ajax_delete_company() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid company ID', 'sc_events')));
        }

        $result = SC_Company_Attendee::delete($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Company deleted', 'sc_events')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete company', 'sc_events')));
        }
    }

    /**
     * AJAX: Check in company
     */
    public function ajax_check_in() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid company ID', 'sc_events')));
        }

        $result = SC_Company_Attendee::check_in($id);

        if ($result) {
            $company = SC_Company_Attendee::get($id);
            wp_send_json_success(array(
                'message' => __('Company checked in', 'sc_events'),
                'company' => $company,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to check in company', 'sc_events')));
        }
    }

    /**
     * AJAX: Check out company
     */
    public function ajax_check_out() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid company ID', 'sc_events')));
        }

        $result = SC_Company_Attendee::check_out($id);

        if ($result) {
            $company = SC_Company_Attendee::get($id);
            wp_send_json_success(array(
                'message' => __('Company checked out', 'sc_events'),
                'company' => $company,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to check out company', 'sc_events')));
        }
    }

    /**
     * AJAX: Export companies
     */
    public function ajax_export_companies() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $args = array(
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'payment_status' => sanitize_text_field($_POST['payment_status'] ?? ''),
        );

        $data = SC_Company_Attendee::export($event_id, $args);

        wp_send_json_success(array('data' => $data));
    }

    /**
     * AJAX: Get company stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('sc_companies_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Event ID is required', 'sc_events')));
        }

        $stats = SC_Company_Attendee::get_stats($event_id);

        wp_send_json_success(array('stats' => $stats));
    }

    /**
     * Get companies for an event
     */
    public function get_event_companies($event_id, $args = array()) {
        $args['event_id'] = $event_id;
        return SC_Company_Attendee::get_list($args);
    }

    /**
     * Get company by ID
     */
    public function get_company($id) {
        return SC_Company_Attendee::get($id);
    }

    /**
     * Get company by code
     */
    public function get_by_code($code) {
        return SC_Company_Attendee::get_by_code($code);
    }

    /**
     * Get statistics for event
     */
    public function get_stats($event_id) {
        return SC_Company_Attendee::get_stats($event_id);
    }
}

// Register the module
sc_modules()->register_module(new SC_Companies_Module());
