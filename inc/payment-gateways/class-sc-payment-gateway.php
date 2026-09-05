<?php
/**
 * SC Events Base Payment Gateway Class
 *
 * Abstract base class for all payment gateway implementations.
 * Each gateway must extend this class and implement the required methods.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SC_Payment_Gateway {

    /**
     * Gateway code (e.g., 'paymob', 'stripe')
     * @var string
     */
    protected $code;

    /**
     * Gateway display name
     * @var string
     */
    protected $name;

    /**
     * Gateway settings from database
     * @var array
     */
    protected $settings = array();

    /**
     * Whether gateway is in test mode
     * @var bool
     */
    protected $test_mode = true;

    /**
     * Whether gateway is enabled
     * @var bool
     */
    protected $enabled = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load gateway settings from WordPress options.
     * Dashboard saves to get_option('sc_gateway_{code}').
     */
    protected function load_settings() {
        $option = get_option('sc_gateway_' . $this->code, array());

        if (!empty($option)) {
            $this->enabled = !empty($option['enabled']);
            $this->test_mode = !empty($option['test_mode']);
            // All keys except 'enabled' and 'test_mode' are API settings
            $this->settings = array_diff_key($option, array('enabled' => 1, 'test_mode' => 1));
        }
    }

    /**
     * Get gateway code
     * @return string
     */
    public function get_code() {
        return $this->code;
    }

    /**
     * Get gateway name
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Check if gateway is enabled
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Check if gateway is in test mode
     * @return bool
     */
    public function is_test_mode() {
        return $this->test_mode;
    }

    /**
     * Get a specific setting
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Get all settings
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get the callback URL for this gateway
     * @return string
     */
    public function get_callback_url() {
        return rest_url('sc-events/v1/payment/callback/' . $this->code);
    }

    /**
     * Get the webhook URL for this gateway
     * @return string
     */
    public function get_webhook_url() {
        return rest_url('sc-events/v1/payment/webhook/' . $this->code);
    }

    /**
     * Generate a unique payment reference using cryptographically secure random bytes
     * @return string
     */
    public static function generate_payment_ref() {
        // Use cryptographically secure random bytes instead of md5
        $random_bytes = function_exists('random_bytes')
            ? random_bytes(8)
            : openssl_random_pseudo_bytes(8);
        $random_string = strtoupper(bin2hex($random_bytes));
        return 'PAY-' . substr($random_string, 0, 8) . '-' . time();
    }

    /**
     * Create a pending payment record
     *
     * @param array $data Payment data
     * @return int|false Payment ID or false on failure
     */
    public function create_payment($data) {
        global $wpdb;
        $tables = sc_get_table_names();

        $payment_data = array(
            'payment_ref' => self::generate_payment_ref(),
            'gateway_code' => $this->code,
            'event_id' => $data['event_id'],
            'ticket_id' => $data['ticket_id'],
            'user_id' => get_current_user_id() ?: null,
            'amount' => $data['amount'],
            'currency' => sc_get_currency(),
            'status' => 'pending',
            'payer_name' => $data['payer_name'] ?? '',
            'payer_email' => $data['payer_email'] ?? '',
            'payer_phone' => $data['payer_phone'] ?? '',
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        );

        $result = $wpdb->insert($tables['payments'], $payment_data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update payment status
     *
     * @param int $payment_id Payment ID
     * @param string $status New status
     * @param array $extra Extra data to update
     * @return bool
     */
    public function update_payment_status($payment_id, $status, $extra = array()) {
        global $wpdb;
        $tables = sc_get_table_names();

        $update_data = array_merge(array('status' => $status), $extra);

        if ($status === 'completed') {
            $update_data['paid_at'] = current_time('mysql');
        }

        return $wpdb->update(
            $tables['payments'],
            $update_data,
            array('id' => $payment_id)
        ) !== false;
    }

    /**
     * Get payment by reference
     *
     * @param string $payment_ref Payment reference
     * @return object|null
     */
    public static function get_payment_by_ref($payment_ref) {
        global $wpdb;
        $tables = sc_get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payments']} WHERE payment_ref = %s",
            $payment_ref
        ));
    }

    /**
     * Get payment by ID
     *
     * @param int $payment_id Payment ID
     * @return object|null
     */
    public static function get_payment($payment_id) {
        global $wpdb;
        $tables = sc_get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payments']} WHERE id = %d",
            $payment_id
        ));
    }

    /**
     * Get payment by gateway transaction ID
     *
     * @param string $transaction_id Gateway transaction ID
     * @return object|null
     */
    public function get_payment_by_transaction($transaction_id) {
        global $wpdb;
        $tables = sc_get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payments']} WHERE gateway_transaction_id = %s AND gateway_code = %s",
            $transaction_id,
            $this->code
        ));
    }

    /**
     * Log gateway response
     *
     * @param int $payment_id Payment ID
     * @param array $response Gateway response
     */
    protected function log_gateway_response($payment_id, $response) {
        global $wpdb;
        $tables = sc_get_table_names();

        $wpdb->update(
            $tables['payments'],
            array('gateway_response' => json_encode($response)),
            array('id' => $payment_id)
        );
    }

    /**
     * Create attendee after successful payment
     *
     * @param object $payment Payment object
     * @return int|false Attendee ID or false on failure
     */
    public function create_attendee_from_payment($payment) {
        // Guard: if attendee already created for this payment, return existing
        if (!empty($payment->attendee_id)) {
            return (int) $payment->attendee_id;
        }

        $metadata = json_decode($payment->metadata, true) ?: array();
        $quantity = max(1, intval($metadata['quantity'] ?? 1));
        $extra_fields = isset($metadata['extra_fields']) ? $metadata['extra_fields'] : array();
        $coupon_code = $metadata['coupon_code'] ?? '';
        $amount_per_ticket = $quantity > 1 ? ($payment->amount / $quantity) : $payment->amount;

        if (!class_exists('SC_Attendee')) {
            return false;
        }

        $first_attendee_id = null;

        for ($i = 0; $i < $quantity; $i++) {
            $attendee_data = array(
                'event_id'       => $payment->event_id,
                'ticket_id'      => $payment->ticket_id,
                'user_id'        => $payment->user_id,
                'name'           => $payment->payer_name,
                'email'          => $payment->payer_email,
                'phone'          => $payment->payer_phone,
                'payment_status' => 'success',
                'payment_method' => $this->code,
                'amount_paid'    => $amount_per_ticket,
                'coupon_code'    => $coupon_code,
                'extra_fields'   => $extra_fields,
            );

            $attendee_id = SC_Attendee::create($attendee_data);

            if ($attendee_id) {
                if (function_exists('sc_public_send_ticket_email')) {
                    sc_public_send_ticket_email($attendee_id);
                }

                if ($first_attendee_id === null) {
                    $first_attendee_id = $attendee_id;
                }
            }
        }

        // Link first attendee to payment record
        if ($first_attendee_id) {
            global $wpdb;
            $tables = sc_get_table_names();
            $wpdb->update(
                $tables['payments'],
                array('attendee_id' => $first_attendee_id),
                array('id' => $payment->id)
            );
        }

        return $first_attendee_id;
    }

    // =========================================
    // ABSTRACT METHODS - Must be implemented
    // =========================================

    /**
     * Get settings fields for admin configuration
     *
     * @return array Array of field definitions
     */
    abstract public function get_settings_fields();

    /**
     * Process payment - initiate payment with gateway
     *
     * @param array $payment_data Payment data
     * @return array Result with 'success', 'redirect_url' or 'error'
     */
    abstract public function process_payment($payment_data);

    /**
     * Handle callback from payment gateway
     *
     * @param WP_REST_Request $request Request object
     * @return array Result with 'success', 'payment_id', 'status'
     */
    abstract public function handle_callback($request);

    /**
     * Verify payment status with gateway
     *
     * @param string $payment_ref Payment reference
     * @return array Payment status information
     */
    abstract public function verify_payment($payment_ref);

    /**
     * Test gateway connection
     *
     * @return array Result with 'success' and 'message'
     */
    abstract public function test_connection();

    // =========================================
    // OPTIONAL METHODS - Can be overridden
    // =========================================

    /**
     * Process refund
     *
     * @param int $payment_id Payment ID
     * @param float $amount Refund amount (null for full refund)
     * @param string $reason Refund reason
     * @return array Result with 'success' and 'message'
     */
    public function refund_payment($payment_id, $amount = null, $reason = '') {
        return array(
            'success' => false,
            'message' => __('Refunds are not supported by this gateway.', 'sc_events'),
        );
    }

    /**
     * Get payment methods available for this gateway
     *
     * @return array
     */
    public function get_payment_methods() {
        return array();
    }

    /**
     * Render payment form (for inline forms like Stripe Elements)
     *
     * @param array $payment_data Payment data
     * @return string HTML
     */
    public function render_payment_form($payment_data) {
        return '';
    }

    /**
     * Get gateway icon URL
     *
     * @return string
     */
    public function get_icon_url() {
        return '';
    }

    /**
     * Check if gateway supports a specific feature
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function supports($feature) {
        $supported = array(
            'refunds' => false,
            'subscriptions' => false,
            'saved_cards' => false,
            'inline_form' => false,
        );

        return isset($supported[$feature]) ? $supported[$feature] : false;
    }
}

/**
 * Payment Gateway Manager
 *
 * Handles loading and managing all payment gateways
 */
class SC_Payment_Gateway_Manager {

    /**
     * Registered gateways
     * @var array
     */
    private static $gateways = array();

    /**
     * Register a payment gateway
     *
     * @param string $code Gateway code
     * @param string $class Gateway class name
     */
    public static function register($code, $class) {
        self::$gateways[$code] = $class;
    }

    /**
     * Get a gateway instance
     *
     * @param string $code Gateway code
     * @return SC_Payment_Gateway|null
     */
    public static function get_gateway($code) {
        if (!isset(self::$gateways[$code])) {
            return null;
        }

        $class = self::$gateways[$code];
        return new $class();
    }

    /**
     * Get all registered gateways
     *
     * @return array
     */
    public static function get_all_gateways() {
        $instances = array();
        foreach (self::$gateways as $code => $class) {
            $instances[$code] = new $class();
        }
        return $instances;
    }

    /**
     * Get all enabled gateways
     *
     * @return array
     */
    public static function get_enabled_gateways() {
        $enabled = array();
        foreach (self::get_all_gateways() as $code => $gateway) {
            if ($gateway->is_enabled()) {
                $enabled[$code] = $gateway;
            }
        }
        return $enabled;
    }

    /**
     * Check if any gateway is enabled
     *
     * @return bool
     */
    public static function has_enabled_gateway() {
        return !empty(self::get_enabled_gateways());
    }
}
