<?php
/**
 * Coupons Module
 *
 * Discount codes and promotional offers management
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Coupons_Module extends SC_Base_Module {

    /**
     * Module ID
     */
    public $id = 'coupons';

    /**
     * Module name
     */
    public $name = 'Coupons';

    /**
     * Module description
     */
    public $description = 'Discount codes and promotional offers management';

    /**
     * Module version
     */
    public $version = '1.0.0';

    /**
     * Dependencies
     */
    public $dependencies = array('tickets');

    /**
     * Priority
     */
    public $priority = 40;

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Load the Coupon class
        add_action('init', array($this, 'load_classes'), 5);

        // AJAX handlers
        $this->register_ajax('sc_get_coupons', 'ajax_get_coupons');
        $this->register_ajax('sc_get_coupon', 'ajax_get_coupon');
        $this->register_ajax('sc_save_coupon', 'ajax_save_coupon');
        $this->register_ajax('sc_delete_coupon', 'ajax_delete_coupon');
        $this->register_ajax('sc_generate_coupon_code', 'ajax_generate_code');

        // Public AJAX for coupon validation
        $this->register_ajax('sc_validate_coupon', 'ajax_validate_coupon', true);

        // Dashboard hooks
        add_filter('sc_dashboard_stats', array($this, 'add_coupon_stats'));

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
        if (!class_exists('SC_Coupon')) {
            $this->load_file('../../inc/database/class-sc-coupon.php');
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sc-coupons') === false) {
            return;
        }

        $module_url = get_template_directory_uri() . '/modules/coupons';

        // CSS
        if (file_exists($this->get_path('assets/css/coupons-admin.css'))) {
            wp_enqueue_style(
                'sc-coupons-admin',
                $module_url . '/assets/css/coupons-admin.css',
                array(),
                $this->version
            );
        }

        // JS
        if (file_exists($this->get_path('assets/js/coupons-admin.js'))) {
            wp_enqueue_script(
                'sc-coupons-admin',
                $module_url . '/assets/js/coupons-admin.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('sc-coupons-admin', 'scCouponsConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sc_coupons_nonce'),
                'currency' => sc_get_currency(),
                'i18n' => array(
                    'confirmDelete' => __('Are you sure you want to delete this coupon?', 'sc_events'),
                    'codeGenerated' => __('Code generated', 'sc_events'),
                    'saving' => __('Saving...', 'sc_events'),
                    'saved' => __('Saved', 'sc_events'),
                    'error' => __('An error occurred', 'sc_events'),
                ),
            ));
        }
    }

    /**
     * Add coupon stats to dashboard
     */
    public function add_coupon_stats($stats) {
        global $wpdb;
        $table = SC_Coupon::get_table();

        $stats['coupons'] = array(
            'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'"),
            'total_usage' => (int) $wpdb->get_var("SELECT SUM(usage_count) FROM $table"),
        );

        return $stats;
    }

    /**
     * AJAX: Get all coupons
     */
    public function ajax_get_coupons() {
        check_ajax_referer('sc_coupons_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $args = array(
            'event_id' => intval($_POST['event_id'] ?? 0) ?: null,
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0),
        );

        $coupons = SC_Coupon::get_all($args);

        wp_send_json_success(array('coupons' => $coupons));
    }

    /**
     * AJAX: Get single coupon
     */
    public function ajax_get_coupon() {
        check_ajax_referer('sc_coupons_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid coupon ID', 'sc_events')));
        }

        $coupon = SC_Coupon::get($id);

        if (!$coupon) {
            wp_send_json_error(array('message' => __('Coupon not found', 'sc_events')));
        }

        wp_send_json_success(array('coupon' => $coupon));
    }

    /**
     * AJAX: Save coupon
     */
    public function ajax_save_coupon() {
        check_ajax_referer('sc_coupons_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);
        $data = array(
            'code' => sanitize_text_field($_POST['code'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'discount_type' => sanitize_text_field($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => floatval($_POST['discount_value'] ?? 0),
            'min_purchase_amount' => floatval($_POST['min_purchase_amount'] ?? 0),
            'max_discount_amount' => floatval($_POST['max_discount_amount'] ?? 0),
            'usage_limit' => intval($_POST['usage_limit'] ?? 0),
            'usage_limit_per_user' => intval($_POST['usage_limit_per_user'] ?? 0),
            'expires_at' => sanitize_text_field($_POST['expires_at'] ?? ''),
            'event_id' => intval($_POST['event_id'] ?? 0) ?: null,
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
        );

        // Handle allowed tickets
        if (isset($_POST['allowed_tickets']) && is_array($_POST['allowed_tickets'])) {
            $data['allowed_tickets'] = array_map('intval', $_POST['allowed_tickets']);
        }

        // Validate required fields
        if (empty($data['code'])) {
            wp_send_json_error(array('message' => __('Coupon code is required', 'sc_events')));
        }

        if ($data['discount_value'] <= 0) {
            wp_send_json_error(array('message' => __('Discount value must be greater than 0', 'sc_events')));
        }

        // Check for duplicate code
        $existing = SC_Coupon::get_by_code($data['code']);
        if ($existing && (!$id || $existing->id != $id)) {
            wp_send_json_error(array('message' => __('A coupon with this code already exists', 'sc_events')));
        }

        if ($id) {
            $result = SC_Coupon::update($id, $data);
            $message = __('Coupon updated', 'sc_events');
        } else {
            $id = SC_Coupon::create($data);
            $result = $id !== false;
            $message = __('Coupon created', 'sc_events');
        }

        if ($result) {
            $coupon = SC_Coupon::get($id);
            wp_send_json_success(array(
                'message' => $message,
                'coupon' => $coupon,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save coupon', 'sc_events')));
        }
    }

    /**
     * AJAX: Delete coupon
     */
    public function ajax_delete_coupon() {
        check_ajax_referer('sc_coupons_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid coupon ID', 'sc_events')));
        }

        $result = SC_Coupon::delete($id);

        if ($result) {
            wp_send_json_success(array('message' => __('Coupon deleted', 'sc_events')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete coupon', 'sc_events')));
        }
    }

    /**
     * AJAX: Generate coupon code
     */
    public function ajax_generate_code() {
        check_ajax_referer('sc_coupons_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'sc_events')));
        }

        $length = intval($_POST['length'] ?? 8);
        $length = max(4, min(16, $length)); // Limit between 4-16

        $code = SC_Coupon::generate_code($length);

        wp_send_json_success(array('code' => $code));
    }

    /**
     * AJAX: Validate coupon (public)
     */
    public function ajax_validate_coupon() {
        check_ajax_referer('sc_checkout_nonce', 'nonce');

        $code = sanitize_text_field($_POST['code'] ?? '');
        $event_id = intval($_POST['event_id'] ?? 0);
        $cart_total = floatval($_POST['cart_total'] ?? 0);
        $user_id = get_current_user_id();

        if (empty($code)) {
            wp_send_json_error(array('message' => __('Please enter a coupon code', 'sc_events')));
        }

        $validation = SC_Coupon::validate($code, $event_id, $cart_total, $user_id);

        if ($validation['valid']) {
            $discount = SC_Coupon::calculate_discount($validation['coupon'], $cart_total);

            wp_send_json_success(array(
                'coupon' => array(
                    'id' => $validation['coupon']->id,
                    'code' => $validation['coupon']->code,
                    'discount_type' => $validation['coupon']->discount_type,
                    'discount_value' => $validation['coupon']->discount_value,
                    'discount_formatted' => $validation['coupon']->discount_formatted,
                ),
                'discount' => $discount,
                'discount_formatted' => sc_format_price($discount, false),
                'new_total' => $cart_total - $discount,
                'new_total_formatted' => sc_format_price($cart_total - $discount, false),
            ));
        } else {
            wp_send_json_error(array('message' => $validation['error']));
        }
    }

    /**
     * Get coupon by code
     */
    public function get_by_code($code) {
        return SC_Coupon::get_by_code($code);
    }

    /**
     * Validate coupon
     */
    public function validate($code, $event_id, $cart_total = 0, $user_id = null) {
        return SC_Coupon::validate($code, $event_id, $cart_total, $user_id);
    }

    /**
     * Calculate discount
     */
    public function calculate_discount($coupon, $amount) {
        return SC_Coupon::calculate_discount($coupon, $amount);
    }

    /**
     * Apply coupon (increment usage)
     */
    public function apply_coupon($coupon_id, $user_id = null) {
        return SC_Coupon::increment_usage($coupon_id, $user_id);
    }

    /**
     * Generate unique code
     */
    public function generate_code($length = 8) {
        return SC_Coupon::generate_code($length);
    }
}

// Register the module
sc_modules()->register_module(new SC_Coupons_Module());
