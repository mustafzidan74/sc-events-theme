<?php
/**
 * SC Events Paymob Payment Gateway
 *
 * Integration with Paymob payment gateway for Egypt and MENA region.
 * Supports card payments, mobile wallets, and installments.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Gateway_Paymob extends SC_Payment_Gateway {

    /**
     * API Base URL
     */
    const API_URL = 'https://accept.paymob.com/api';

    /**
     * Constructor
     */
    public function __construct() {
        $this->code = 'paymob';
        $this->name = 'Paymob';
        parent::__construct();
    }

    /**
     * Get settings fields for admin configuration
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'api_key' => array(
                'title' => __('API Key', 'sc_events'),
                'type' => 'password',
                'description' => __('Your Paymob API Key from dashboard', 'sc_events'),
                'required' => true,
            ),
            'integration_id' => array(
                'title' => __('Integration ID', 'sc_events'),
                'type' => 'text',
                'description' => __('Card payment integration ID', 'sc_events'),
                'required' => true,
            ),
            'iframe_id' => array(
                'title' => __('Iframe ID', 'sc_events'),
                'type' => 'text',
                'description' => __('Payment iframe ID', 'sc_events'),
                'required' => true,
            ),
            'hmac_secret' => array(
                'title' => __('HMAC Secret', 'sc_events'),
                'type' => 'password',
                'description' => __('HMAC secret for callback verification', 'sc_events'),
                'required' => true,
            ),
        );
    }

    /**
     * Get authentication token from Paymob
     *
     * @return string|false
     */
    private function get_auth_token() {
        $api_key = $this->get_setting('api_key');

        if (empty($api_key)) {
            return false;
        }

        $response = wp_remote_post(self::API_URL . '/auth/tokens', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array('api_key' => $api_key)),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return isset($body['token']) ? $body['token'] : false;
    }

    /**
     * Create order in Paymob
     *
     * @param string $auth_token Authentication token
     * @param array $data Order data
     * @return int|false Order ID or false
     */
    private function create_order($auth_token, $data) {
        $response = wp_remote_post(self::API_URL . '/ecommerce/orders', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'auth_token' => $auth_token,
                'delivery_needed' => false,
                'amount_cents' => intval($data['amount'] * 100),
                'currency' => $data['currency'],
                'merchant_order_id' => $data['payment_ref'],
                'items' => array(
                    array(
                        'name' => $data['item_name'],
                        'amount_cents' => intval($data['amount'] * 100),
                        'quantity' => 1,
                    ),
                ),
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return isset($body['id']) ? $body['id'] : false;
    }

    /**
     * Get payment key from Paymob
     *
     * @param string $auth_token Authentication token
     * @param int $order_id Paymob order ID
     * @param array $data Payment data
     * @return string|false Payment key or false
     */
    private function get_payment_key($auth_token, $order_id, $data) {
        $integration_id = $this->get_setting('integration_id');

        $billing_data = array(
            'apartment' => 'NA',
            'email' => $data['email'],
            'floor' => 'NA',
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?: $data['first_name'],
            'street' => 'NA',
            'building' => 'NA',
            'phone_number' => $data['phone'] ?: '+201000000000',
            'shipping_method' => 'NA',
            'postal_code' => 'NA',
            'city' => 'NA',
            'country' => 'EG',
            'state' => 'NA',
        );

        $response = wp_remote_post(self::API_URL . '/acceptance/payment_keys', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'auth_token' => $auth_token,
                'amount_cents' => intval($data['amount'] * 100),
                'expiration' => 3600,
                'order_id' => $order_id,
                'billing_data' => $billing_data,
                'currency' => $data['currency'],
                'integration_id' => intval($integration_id),
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return isset($body['token']) ? $body['token'] : false;
    }

    /**
     * Process payment - initiate payment with Paymob
     *
     * @param array $payment_data Payment data
     * @return array
     */
    public function process_payment($payment_data) {
        // Create local payment record
        $payment_id = $this->create_payment($payment_data);

        if (!$payment_id) {
            return array(
                'success' => false,
                'error' => __('Failed to create payment record.', 'sc_events'),
            );
        }

        $payment = self::get_payment($payment_id);

        // Step 1: Get auth token
        $auth_token = $this->get_auth_token();

        if (!$auth_token) {
            $this->update_payment_status($payment_id, 'failed');
            return array(
                'success' => false,
                'error' => __('Failed to authenticate with Paymob.', 'sc_events'),
            );
        }

        // Step 2: Create order
        $order_id = $this->create_order($auth_token, array(
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payment_ref' => $payment->payment_ref,
            'item_name' => $payment_data['item_name'] ?? __('Event Ticket', 'sc_events'),
        ));

        if (!$order_id) {
            $this->update_payment_status($payment_id, 'failed');
            return array(
                'success' => false,
                'error' => __('Failed to create order with Paymob.', 'sc_events'),
            );
        }

        // Update payment with gateway order ID
        global $wpdb;
        $tables = sc_get_table_names();
        $wpdb->update(
            $tables['payments'],
            array('gateway_order_id' => $order_id),
            array('id' => $payment_id)
        );

        // Step 3: Get payment key
        $name_parts = explode(' ', $payment->payer_name, 2);
        $payment_key = $this->get_payment_key($auth_token, $order_id, array(
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'email' => $payment->payer_email,
            'first_name' => $name_parts[0],
            'last_name' => $name_parts[1] ?? '',
            'phone' => $payment->payer_phone,
        ));

        if (!$payment_key) {
            $this->update_payment_status($payment_id, 'failed');
            return array(
                'success' => false,
                'error' => __('Failed to get payment key from Paymob.', 'sc_events'),
            );
        }

        // Update status to processing
        $this->update_payment_status($payment_id, 'processing');

        // Build iframe URL
        $iframe_id = $this->get_setting('iframe_id');
        $iframe_url = "https://accept.paymob.com/api/acceptance/iframes/{$iframe_id}?payment_token={$payment_key}";

        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'payment_ref' => $payment->payment_ref,
            'redirect_url' => $iframe_url,
        );
    }

    /**
     * Handle callback from Paymob
     *
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function handle_callback($request) {
        $params = $request->get_params();

        // Verify HMAC
        if (!$this->verify_hmac($params)) {
            return array(
                'success' => false,
                'error' => __('Invalid signature.', 'sc_events'),
            );
        }

        $transaction_id = $params['obj']['id'] ?? null;
        $order_id = $params['obj']['order']['id'] ?? null;
        $merchant_order_id = $params['obj']['order']['merchant_order_id'] ?? null;
        $success = $params['obj']['success'] ?? false;
        $is_pending = $params['obj']['pending'] ?? false;

        if (!$merchant_order_id) {
            return array(
                'success' => false,
                'error' => __('Missing order reference.', 'sc_events'),
            );
        }

        // Find payment by reference
        $payment = self::get_payment_by_ref($merchant_order_id);

        if (!$payment) {
            return array(
                'success' => false,
                'error' => __('Payment not found.', 'sc_events'),
            );
        }

        // Log the response
        $this->log_gateway_response($payment->id, $params);

        // Guard: if already completed, return existing result
        if ($payment->status === 'completed') {
            return array(
                'success' => true,
                'payment_id' => $payment->id,
                'attendee_id' => $payment->attendee_id,
                'status' => 'completed',
            );
        }

        // Update transaction ID
        global $wpdb;
        $tables = sc_get_table_names();
        $wpdb->update(
            $tables['payments'],
            array('gateway_transaction_id' => $transaction_id),
            array('id' => $payment->id)
        );

        // Determine status
        if ($success && !$is_pending) {
            $this->update_payment_status($payment->id, 'completed');

            // Reload payment to get updated status
            $payment = self::get_payment($payment->id);

            // Create attendee
            $attendee_id = $this->create_attendee_from_payment($payment);

            return array(
                'success' => true,
                'payment_id' => $payment->id,
                'attendee_id' => $attendee_id,
                'status' => 'completed',
            );
        } elseif ($is_pending) {
            $this->update_payment_status($payment->id, 'processing');

            return array(
                'success' => true,
                'payment_id' => $payment->id,
                'status' => 'processing',
            );
        } else {
            $this->update_payment_status($payment->id, 'failed');

            return array(
                'success' => false,
                'payment_id' => $payment->id,
                'status' => 'failed',
                'error' => $params['obj']['data']['message'] ?? __('Payment failed.', 'sc_events'),
            );
        }
    }

    /**
     * Verify HMAC signature
     *
     * @param array $params Callback parameters
     * @return bool
     */
    private function verify_hmac($params) {
        $hmac_secret = $this->get_setting('hmac_secret');

        if (empty($hmac_secret)) {
            // HMAC secret should always be configured in production
            if (function_exists('sc_debug_log')) {
                sc_debug_log('Paymob HMAC secret not configured - webhook signature cannot be verified', 'warning');
            }
            return false; // Reject unverified webhooks when HMAC is not configured
        }

        $received_hmac = $params['hmac'] ?? '';
        $obj = $params['obj'] ?? array();

        // Build HMAC string
        $hmac_string = implode('', array(
            $obj['amount_cents'] ?? '',
            $obj['created_at'] ?? '',
            $obj['currency'] ?? '',
            $obj['error_occured'] ?? 'false',
            $obj['has_parent_transaction'] ?? 'false',
            $obj['id'] ?? '',
            $obj['integration_id'] ?? '',
            $obj['is_3d_secure'] ?? 'false',
            $obj['is_auth'] ?? 'false',
            $obj['is_capture'] ?? 'false',
            $obj['is_refunded'] ?? 'false',
            $obj['is_standalone_payment'] ?? 'true',
            $obj['is_voided'] ?? 'false',
            $obj['order']['id'] ?? '',
            $obj['owner'] ?? '',
            $obj['pending'] ?? 'false',
            $obj['source_data']['pan'] ?? '',
            $obj['source_data']['sub_type'] ?? '',
            $obj['source_data']['type'] ?? '',
            $obj['success'] ?? 'false',
        ));

        $calculated_hmac = hash_hmac('sha512', $hmac_string, $hmac_secret);

        return hash_equals($calculated_hmac, $received_hmac);
    }

    /**
     * Verify payment status with Paymob
     *
     * @param string $payment_ref Payment reference
     * @return array
     */
    public function verify_payment($payment_ref) {
        $payment = self::get_payment_by_ref($payment_ref);

        if (!$payment) {
            return array(
                'success' => false,
                'error' => __('Payment not found.', 'sc_events'),
            );
        }

        if (empty($payment->gateway_transaction_id)) {
            return array(
                'success' => false,
                'status' => $payment->status,
                'error' => __('No transaction ID available.', 'sc_events'),
            );
        }

        $auth_token = $this->get_auth_token();

        if (!$auth_token) {
            return array(
                'success' => false,
                'error' => __('Failed to authenticate with Paymob.', 'sc_events'),
            );
        }

        $response = wp_remote_get(
            self::API_URL . '/acceptance/transactions/' . $payment->gateway_transaction_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $auth_token,
                ),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'success' => true,
            'status' => $body['success'] ? 'completed' : ($body['pending'] ? 'processing' : 'failed'),
            'transaction_id' => $body['id'],
            'amount' => $body['amount_cents'] / 100,
        );
    }

    /**
     * Test gateway connection
     *
     * @return array
     */
    public function test_connection() {
        $auth_token = $this->get_auth_token();

        if ($auth_token) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to Paymob.', 'sc_events'),
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to connect to Paymob. Please check your API key.', 'sc_events'),
        );
    }

    /**
     * Process refund
     *
     * @param int $payment_id Payment ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array
     */
    public function refund_payment($payment_id, $amount = null, $reason = '') {
        $payment = self::get_payment($payment_id);

        if (!$payment || empty($payment->gateway_transaction_id)) {
            return array(
                'success' => false,
                'message' => __('Invalid payment or no transaction ID.', 'sc_events'),
            );
        }

        $auth_token = $this->get_auth_token();

        if (!$auth_token) {
            return array(
                'success' => false,
                'message' => __('Failed to authenticate with Paymob.', 'sc_events'),
            );
        }

        $refund_amount = $amount ?? $payment->amount;

        $response = wp_remote_post(self::API_URL . '/acceptance/void_refund/refund', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'auth_token' => $auth_token,
                'transaction_id' => $payment->gateway_transaction_id,
                'amount_cents' => intval($refund_amount * 100),
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['success']) && $body['success']) {
            $this->update_payment_status($payment_id, 'refunded', array(
                'refund_amount' => $refund_amount,
                'refunded_at' => current_time('mysql'),
                'refund_reason' => $reason,
            ));

            return array(
                'success' => true,
                'message' => __('Refund processed successfully.', 'sc_events'),
            );
        }

        return array(
            'success' => false,
            'message' => $body['message'] ?? __('Refund failed.', 'sc_events'),
        );
    }

    /**
     * Check if gateway supports a feature
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function supports($feature) {
        $supported = array(
            'refunds' => true,
            'subscriptions' => false,
            'saved_cards' => false,
            'inline_form' => false,
        );

        return isset($supported[$feature]) ? $supported[$feature] : false;
    }

    /**
     * Get gateway icon URL
     *
     * @return string
     */
    public function get_icon_url() {
        return get_template_directory_uri() . '/assets/images/payment/paymob.png';
    }
}

// Register the gateway
SC_Payment_Gateway_Manager::register('paymob', 'SC_Gateway_Paymob');
