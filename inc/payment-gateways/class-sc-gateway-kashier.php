<?php
/**
 * SC Events Kashier Payment Gateway
 *
 * Integration with Kashier payment gateway for Egypt.
 * Supports card payments.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Gateway_Kashier extends SC_Payment_Gateway {

    /**
     * API Base URLs
     */
    const API_URL_TEST = 'https://test-api.kashier.io';
    const API_URL_LIVE = 'https://api.kashier.io';
    const CHECKOUT_URL_TEST = 'https://test-checkout.kashier.io';
    const CHECKOUT_URL_LIVE = 'https://checkout.kashier.io';

    /**
     * Constructor
     */
    public function __construct() {
        $this->code = 'kashier';
        $this->name = 'Kashier';
        parent::__construct();
    }

    /**
     * Get API base URL
     *
     * @return string
     */
    private function get_api_url() {
        return $this->is_test_mode() ? self::API_URL_TEST : self::API_URL_LIVE;
    }

    /**
     * Get checkout URL
     *
     * @return string
     */
    private function get_checkout_url() {
        return $this->is_test_mode() ? self::CHECKOUT_URL_TEST : self::CHECKOUT_URL_LIVE;
    }

    /**
     * Get settings fields for admin configuration
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'merchant_id' => array(
                'title' => __('Merchant ID', 'sc_events'),
                'type' => 'text',
                'description' => __('Your Kashier Merchant ID', 'sc_events'),
                'required' => true,
            ),
            'api_key' => array(
                'title' => __('API Key', 'sc_events'),
                'type' => 'password',
                'description' => __('Your Kashier API Key', 'sc_events'),
                'required' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'sc_events'),
                'type' => 'password',
                'description' => __('Your Kashier Secret Key for signature verification', 'sc_events'),
                'required' => true,
            ),
        );
    }

    /**
     * Generate signature hash
     *
     * @param array $data Data to sign
     * @return string
     */
    private function generate_signature($data) {
        $secret_key = $this->get_setting('secret_key');
        $sign_string = '';

        // Sort keys alphabetically
        ksort($data);

        foreach ($data as $key => $value) {
            $sign_string .= $value;
        }

        return hash_hmac('sha256', $sign_string, $secret_key);
    }

    /**
     * Verify callback signature
     *
     * @param array $data Callback data
     * @param string $received_hash Received hash
     * @return bool
     */
    private function verify_signature($data, $received_hash) {
        $secret_key = $this->get_setting('secret_key');

        // Build signature string based on Kashier's format
        $sign_data = array(
            'orderId' => $data['orderId'] ?? '',
            'orderReference' => $data['orderReference'] ?? '',
            'transactionId' => $data['transactionId'] ?? '',
            'status' => $data['status'] ?? '',
        );

        $sign_string = implode('', array_values($sign_data));
        $calculated_hash = hash_hmac('sha256', $sign_string, $secret_key);

        return hash_equals($calculated_hash, $received_hash);
    }

    /**
     * Make API request to Kashier
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function api_request($endpoint, $data = array(), $method = 'POST') {
        $api_key = $this->get_setting('api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Kashier API key not configured.', 'sc_events'));
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        if (!empty($data) && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($this->get_api_url() . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400 || (isset($body['status']) && $body['status'] === 'FAILURE')) {
            $error_message = $body['error']['message'] ?? $body['message'] ?? __('Kashier API error.', 'sc_events');
            return new WP_Error('kashier_error', $error_message, $body);
        }

        return $body;
    }

    /**
     * Process payment - initiate Kashier checkout
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
        $merchant_id = $this->get_setting('merchant_id');

        // Build redirect URLs
        $success_url = add_query_arg(array(
            'payment_ref' => $payment->payment_ref,
            'status' => 'success',
        ), home_url('/payment-success/'));

        $failure_url = add_query_arg(array(
            'payment_ref' => $payment->payment_ref,
            'status' => 'failed',
        ), home_url('/payment-failed/'));

        // Build checkout URL with parameters
        $checkout_params = array(
            'merchantId' => $merchant_id,
            'orderId' => $payment->payment_ref,
            'amount' => number_format($payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'hash' => $this->generate_signature(array(
                'amount' => number_format($payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
                'merchantId' => $merchant_id,
                'orderId' => $payment->payment_ref,
            )),
            'mode' => $this->is_test_mode() ? 'test' : 'live',
            'merchantRedirect' => $success_url,
            'failureRedirect' => $failure_url,
            'serverWebhook' => $this->get_callback_url(),
            'display' => 'en',
            'customer[email]' => $payment->payer_email,
            'customer[name]' => $payment->payer_name,
            'customer[phone]' => $payment->payer_phone ?: '',
            'metadata[payment_id]' => $payment_id,
            'metadata[event_id]' => $payment->event_id,
        );

        // Build checkout URL
        $checkout_url = $this->get_checkout_url() . '?' . http_build_query($checkout_params);

        // Update status to processing
        $this->update_payment_status($payment_id, 'processing');

        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'payment_ref' => $payment->payment_ref,
            'redirect_url' => $checkout_url,
        );
    }

    /**
     * Handle callback from Kashier
     *
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function handle_callback($request) {
        $params = $request->get_params();

        // Get signature from header or params
        $signature = $request->get_header('x-kashier-signature') ?: ($params['signature'] ?? '');

        // Verify signature
        if (!empty($signature) && !$this->verify_signature($params, $signature)) {
            return array(
                'success' => false,
                'error' => __('Invalid signature.', 'sc_events'),
            );
        }

        $order_id = $params['orderId'] ?? $params['orderReference'] ?? null;
        $transaction_id = $params['transactionId'] ?? null;
        $status = $params['status'] ?? $params['paymentStatus'] ?? null;

        if (!$order_id) {
            return array(
                'success' => false,
                'error' => __('Missing order reference.', 'sc_events'),
            );
        }

        // Find payment by reference
        $payment = self::get_payment_by_ref($order_id);

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
        if ($transaction_id) {
            global $wpdb;
            $tables = sc_get_table_names();
            $wpdb->update(
                $tables['payments'],
                array('gateway_transaction_id' => $transaction_id),
                array('id' => $payment->id)
            );
        }

        // Determine status
        $status_lower = strtolower($status);

        if (in_array($status_lower, array('success', 'captured', 'paid'))) {
            $this->update_payment_status($payment->id, 'completed');

            // Reload payment
            $payment = self::get_payment($payment->id);

            // Create attendee
            $attendee_id = $this->create_attendee_from_payment($payment);

            return array(
                'success' => true,
                'payment_id' => $payment->id,
                'attendee_id' => $attendee_id,
                'status' => 'completed',
            );
        } elseif (in_array($status_lower, array('pending', 'processing'))) {
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
                'error' => $params['error']['message'] ?? __('Payment failed.', 'sc_events'),
            );
        }
    }

    /**
     * Verify payment status with Kashier
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

        $merchant_id = $this->get_setting('merchant_id');

        $result = $this->api_request('/payments/' . $merchant_id . '/orders/' . $payment_ref, array(), 'GET');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
            );
        }

        $status = 'pending';
        $payment_status = strtolower($result['status'] ?? '');

        if (in_array($payment_status, array('success', 'captured', 'paid'))) {
            $status = 'completed';
        } elseif (in_array($payment_status, array('failed', 'declined', 'expired'))) {
            $status = 'failed';
        }

        return array(
            'success' => true,
            'status' => $status,
            'order_id' => $result['orderId'] ?? $payment_ref,
            'amount' => $result['amount'] ?? $payment->amount,
        );
    }

    /**
     * Test gateway connection
     *
     * @return array
     */
    public function test_connection() {
        $merchant_id = $this->get_setting('merchant_id');
        $api_key = $this->get_setting('api_key');

        if (empty($merchant_id) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('Please configure Merchant ID and API Key.', 'sc_events'),
            );
        }

        // Try to get merchant info
        $result = $this->api_request('/merchants/' . $merchant_id, array(), 'GET');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __('Successfully connected to Kashier.', 'sc_events'),
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

        $refund_amount = $amount ?? $payment->amount;
        $merchant_id = $this->get_setting('merchant_id');

        $result = $this->api_request('/payments/' . $merchant_id . '/refund', array(
            'transactionId' => $payment->gateway_transaction_id,
            'amount' => number_format($refund_amount, 2, '.', ''),
        ));

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }

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
        return get_template_directory_uri() . '/assets/images/payment/kashier.png';
    }
}

// Register the gateway
SC_Payment_Gateway_Manager::register('kashier', 'SC_Gateway_Kashier');
