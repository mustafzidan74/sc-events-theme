<?php
/**
 * SC Payments Module
 *
 * Payment gateways and transaction management
 *
 * @package sc_events
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Module_Payments extends SC_Base_Module {

    protected $id = 'payments';
    protected $name = 'Payments';
    protected $description = 'Payment gateway integration and transaction management';
    protected $version = '2.0.0';
    protected $dependencies = array('events', 'tickets');
    protected $priority = 4;

    private $gateways = array();

    public function register_hooks() {
        add_action('init', array($this, 'load_gateways'));
        add_action('init', array($this, 'register_ajax_handlers'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Payment webhooks
        add_action('wp_ajax_nopriv_sc_payment_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_sc_payment_webhook', array($this, 'handle_webhook'));
    }

    public function init() {
        $this->log('Payments module initialized');
    }

    public function load_gateways() {
        // Load gateway classes from payment-gateways directory
        $gateways_path = get_template_directory() . '/inc/payment-gateways';

        $gateway_files = array(
            'paymob' => 'class-sc-gateway-paymob.php',
            'stripe' => 'class-sc-gateway-stripe.php',
            'myfatoorah' => 'class-sc-gateway-myfatoorah.php',
            'kashier' => 'class-sc-gateway-kashier.php',
        );

        foreach ($gateway_files as $id => $file) {
            $path = $gateways_path . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    public function register_ajax_handlers() {
        // Gateway management
        $this->register_ajax('get_gateways', array($this, 'ajax_get_gateways'));
        $this->register_ajax('update_gateway', array($this, 'ajax_update_gateway'));
        $this->register_ajax('test_gateway', array($this, 'ajax_test_gateway'));

        // Payments
        $this->register_ajax('initiate', array($this, 'ajax_initiate_payment'), true);
        $this->register_ajax('verify', array($this, 'ajax_verify_payment'), true);
        $this->register_ajax('refund', array($this, 'ajax_refund_payment'));

        // Transactions
        $this->register_ajax('get_transactions', array($this, 'ajax_get_transactions'));
        $this->register_ajax('get_transaction', array($this, 'ajax_get_transaction'));
    }

    public function register_rest_routes() {
        // Initiate payment
        register_rest_route('sc-events/v1', '/payments/initiate', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_initiate_payment'),
            'permission_callback' => '__return_true',
        ));

        // Payment callback/webhook
        register_rest_route('sc-events/v1', '/payments/callback/(?P<gateway>[a-z]+)', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'rest_payment_callback'),
            'permission_callback' => '__return_true',
        ));

        // Get available gateways (public)
        register_rest_route('sc-events/v1', '/payments/gateways', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_gateways'),
            'permission_callback' => '__return_true',
        ));
    }

    // REST Handlers
    public function rest_initiate_payment($request) {
        $event_id = $request->get_param('event_id');
        $ticket_id = $request->get_param('ticket_id');
        $quantity = $request->get_param('quantity') ?: 1;
        $gateway_code = $request->get_param('gateway');

        $payer = array(
            'name' => sanitize_text_field($request->get_param('name')),
            'email' => sanitize_email($request->get_param('email')),
            'phone' => sanitize_text_field($request->get_param('phone')),
        );

        $result = $this->initiate_payment($event_id, $ticket_id, $quantity, $gateway_code, $payer);

        if (is_wp_error($result)) {
            return new WP_Error('payment_failed', $result->get_error_message(), array('status' => 400));
        }

        return new WP_REST_Response($result, 200);
    }

    public function rest_payment_callback($request) {
        $gateway_code = $request->get_param('gateway');

        // Get gateway handler
        $gateway = $this->get_gateway($gateway_code);

        if (!$gateway) {
            return new WP_Error('invalid_gateway', 'Invalid gateway', array('status' => 400));
        }

        // Handle callback
        $result = $gateway->handle_callback($request->get_params());

        if (is_wp_error($result)) {
            return new WP_Error('callback_failed', $result->get_error_message(), array('status' => 400));
        }

        // Redirect or return response based on gateway
        if (isset($result['redirect_url'])) {
            wp_redirect($result['redirect_url']);
            exit;
        }

        return new WP_REST_Response($result, 200);
    }

    public function rest_get_gateways($request) {
        $gateways = $this->get_enabled_gateways();

        $public_gateways = array_map(function($gateway) {
            return array(
                'code' => $gateway->gateway_code,
                'name' => $gateway->gateway_name,
            );
        }, $gateways);

        return new WP_REST_Response(array('gateways' => $public_gateways), 200);
    }

    // AJAX Handlers
    public function ajax_get_gateways() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sc_payment_gateways';
        $gateways = $wpdb->get_results("SELECT * FROM $table ORDER BY display_order ASC");

        // Decrypt sensitive settings for display
        foreach ($gateways as &$gateway) {
            $settings = json_decode($gateway->settings, true);
            // Mask sensitive data
            foreach ($settings as $key => &$value) {
                if (strpos($key, 'key') !== false || strpos($key, 'secret') !== false) {
                    $value = $value ? '••••••••' . substr($value, -4) : '';
                }
            }
            $gateway->settings = $settings;
        }

        wp_send_json_success(array('gateways' => $gateways));
    }

    public function ajax_update_gateway() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $gateway_code = sanitize_text_field($_POST['gateway_code'] ?? '');
        $is_enabled = intval($_POST['is_enabled'] ?? 0);
        $is_test_mode = intval($_POST['is_test_mode'] ?? 1);
        $settings = $_POST['settings'] ?? array();

        global $wpdb;
        $table = $wpdb->prefix . 'sc_payment_gateways';

        // Get existing settings to preserve masked values
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT settings FROM $table WHERE gateway_code = %s",
            $gateway_code
        ));

        if ($existing) {
            $existing_settings = json_decode($existing->settings, true);

            // Don't update masked values
            foreach ($settings as $key => $value) {
                if (strpos($value, '••••') === 0 && isset($existing_settings[$key])) {
                    $settings[$key] = $existing_settings[$key];
                }
            }
        }

        $result = $wpdb->update(
            $table,
            array(
                'is_enabled' => $is_enabled,
                'is_test_mode' => $is_test_mode,
                'settings' => json_encode($settings),
            ),
            array('gateway_code' => $gateway_code)
        );

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Gateway updated successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update gateway'));
        }
    }

    public function ajax_initiate_payment() {
        $event_id = intval($_POST['event_id'] ?? 0);
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $gateway_code = sanitize_text_field($_POST['gateway'] ?? '');

        $payer = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        );

        $result = $this->initiate_payment($event_id, $ticket_id, $quantity, $gateway_code, $payer);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_refund_payment() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        $result = $this->process_refund($payment_id, $amount, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Refund processed successfully'));
    }

    public function ajax_get_transactions() {
        check_ajax_referer('sc_dashboard_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $event_id = intval($_POST['event_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        global $wpdb;
        $table = $wpdb->prefix . 'sc_payments';

        $where = array('1=1');
        $values = array();

        if ($event_id) {
            $where[] = 'event_id = %d';
            $values[] = $event_id;
        }

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $transactions = $wpdb->get_results($wpdb->prepare($query, $values));

        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = $wpdb->get_var($wpdb->prepare($count_query, array_slice($values, 0, -2)));

        wp_send_json_success(array(
            'transactions' => $transactions,
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ));
    }

    // Core Methods
    public function initiate_payment($event_id, $ticket_id, $quantity, $gateway_code, $payer) {
        // Validate inputs
        $event = SC_Event::get($event_id);
        if (!$event) {
            return new WP_Error('invalid_event', 'Event not found');
        }

        $ticket = SC_Ticket::get($ticket_id);
        if (!$ticket) {
            return new WP_Error('invalid_ticket', 'Ticket not found');
        }

        // Check availability
        $available = $ticket->quantity - $ticket->sold;
        if ($available < $quantity) {
            return new WP_Error('not_available', 'Not enough tickets available');
        }

        // Get gateway
        $gateway = $this->get_gateway($gateway_code);
        if (!$gateway) {
            return new WP_Error('invalid_gateway', 'Payment gateway not available');
        }

        // Calculate amount
        $amount = $ticket->price * $quantity;

        // Apply coupon if provided
        if (isset($payer['coupon_code']) && !empty($payer['coupon_code'])) {
            $coupon = SC_Coupon::get_by_code($payer['coupon_code']);
            if ($coupon && SC_Coupon::is_valid($coupon, $event_id, $ticket_id)) {
                $discount = SC_Coupon::calculate_discount($coupon, $amount);
                $amount -= $discount;
            }
        }

        // Create payment record
        $payment_ref = $this->generate_payment_ref();

        global $wpdb;
        $table = $wpdb->prefix . 'sc_payments';

        $wpdb->insert($table, array(
            'payment_ref' => $payment_ref,
            'gateway_code' => $gateway_code,
            'event_id' => $event_id,
            'ticket_id' => $ticket_id,
            'amount' => $amount,
            'currency' => 'SAR',
            'status' => 'pending',
            'payer_name' => $payer['name'],
            'payer_email' => $payer['email'],
            'payer_phone' => $payer['phone'] ?? '',
            'metadata' => json_encode(array(
                'quantity' => $quantity,
                'original_amount' => $ticket->price * $quantity,
                'coupon_code' => $payer['coupon_code'] ?? null,
            )),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => current_time('mysql'),
        ));

        $payment_id = $wpdb->insert_id;

        // Initiate gateway payment
        $result = $gateway->create_payment(array(
            'payment_id' => $payment_id,
            'payment_ref' => $payment_ref,
            'amount' => $amount,
            'currency' => 'SAR',
            'description' => sprintf('%s - %s x %d', $event->title, $ticket->name, $quantity),
            'payer' => $payer,
            'callback_url' => rest_url('sc-events/v1/payments/callback/' . $gateway_code),
        ));

        if (is_wp_error($result)) {
            // Update payment status
            $wpdb->update($table, array('status' => 'failed'), array('id' => $payment_id));
            return $result;
        }

        // Update with gateway info
        $wpdb->update($table, array(
            'gateway_order_id' => $result['order_id'] ?? null,
        ), array('id' => $payment_id));

        return array(
            'payment_id' => $payment_id,
            'payment_ref' => $payment_ref,
            'redirect_url' => $result['redirect_url'] ?? null,
            'iframe_url' => $result['iframe_url'] ?? null,
        );
    }

    public function process_refund($payment_id, $amount, $reason) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_payments';

        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $payment_id
        ));

        if (!$payment) {
            return new WP_Error('not_found', 'Payment not found');
        }

        if ($payment->status !== 'completed') {
            return new WP_Error('invalid_status', 'Can only refund completed payments');
        }

        $gateway = $this->get_gateway($payment->gateway_code);

        if ($gateway && method_exists($gateway, 'refund')) {
            $result = $gateway->refund($payment, $amount);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update payment record
        $wpdb->update($table, array(
            'status' => 'refunded',
            'refund_amount' => $amount,
            'refunded_at' => current_time('mysql'),
            'refund_reason' => $reason,
        ), array('id' => $payment_id));

        // Update attendee if exists
        if ($payment->attendee_id) {
            SC_Attendee::update($payment->attendee_id, array(
                'payment_status' => 'refunded',
            ));
        }

        return true;
    }

    // Helpers
    private function get_gateway($code) {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_payment_gateways';

        $gateway = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE gateway_code = %s AND is_enabled = 1",
            $code
        ));

        if (!$gateway) {
            return null;
        }

        $class_name = 'SC_Gateway_' . ucfirst($code);

        if (class_exists($class_name)) {
            $settings = json_decode($gateway->settings, true);
            return new $class_name($settings, $gateway->is_test_mode);
        }

        return null;
    }

    private function get_enabled_gateways() {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_payment_gateways';

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_enabled = 1 ORDER BY display_order ASC"
        );
    }

    private function generate_payment_ref() {
        return 'PAY-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
    }

    public function handle_webhook() {
        $gateway_code = sanitize_text_field($_GET['gateway'] ?? '');

        $gateway = $this->get_gateway($gateway_code);

        if ($gateway && method_exists($gateway, 'handle_webhook')) {
            $gateway->handle_webhook();
        }

        wp_die('OK', '', array('response' => 200));
    }
}

// Register the module
sc_modules()->register_module(new SC_Module_Payments());
