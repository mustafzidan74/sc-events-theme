<?php
/**
 * SC Events Stripe Payment Gateway
 *
 * Integration with Stripe for international payments.
 * Supports card payments, Apple Pay, Google Pay.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Gateway_Stripe extends SC_Payment_Gateway {

    /**
     * API Base URL
     */
    const API_URL = 'https://api.stripe.com/v1';

    /**
     * Constructor
     */
    public function __construct() {
        $this->code = 'stripe';
        $this->name = 'Stripe';
        parent::__construct();
    }

    /**
     * Get the secret key based on mode
     *
     * @return string
     */
    private function get_secret_key() {
        return $this->get_setting('secret_key');
    }

    /**
     * Get the publishable key based on mode
     *
     * @return string
     */
    public function get_publishable_key() {
        return $this->get_setting('publishable_key');
    }

    /**
     * Get settings fields for admin configuration
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'publishable_key' => array(
                'title' => __('Publishable Key', 'sc_events'),
                'type' => 'text',
                'description' => __('Your Stripe publishable key (pk_test_... or pk_live_...)', 'sc_events'),
                'required' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'sc_events'),
                'type' => 'password',
                'description' => __('Your Stripe secret key (sk_test_... or sk_live_...)', 'sc_events'),
                'required' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret', 'sc_events'),
                'type' => 'password',
                'description' => __('Webhook signing secret for verifying callbacks (whsec_...)', 'sc_events'),
                'required' => false,
            ),
        );
    }

    /**
     * Make API request to Stripe
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function api_request($endpoint, $data = array(), $method = 'POST') {
        $secret_key = $this->get_secret_key();

        if (empty($secret_key)) {
            return new WP_Error('no_api_key', __('Stripe API key not configured.', 'sc_events'));
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 30,
        );

        if (!empty($data) && $method !== 'GET') {
            $args['body'] = http_build_query($data);
        }

        $response = wp_remote_request(self::API_URL . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Stripe API error.', 'sc_events');
            return new WP_Error('stripe_error', $error_message, $body);
        }

        return $body;
    }

    /**
     * Create a Checkout Session
     *
     * @param array $data Session data
     * @return array|WP_Error
     */
    private function create_checkout_session($data) {
        return $this->api_request('/checkout/sessions', $data);
    }

    /**
     * Retrieve a Checkout Session
     *
     * @param string $session_id Session ID
     * @return array|WP_Error
     */
    private function get_checkout_session($session_id) {
        return $this->api_request('/checkout/sessions/' . $session_id, array(), 'GET');
    }

    /**
     * Process payment - initiate Stripe Checkout Session
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

        // Build success and cancel URLs
        $success_url = add_query_arg(array(
            'payment_ref' => $payment->payment_ref,
            'status' => 'success',
        ), home_url('/payment-success/'));

        $cancel_url = add_query_arg(array(
            'payment_ref' => $payment->payment_ref,
            'status' => 'cancelled',
        ), home_url('/payment-failed/'));

        // Create Stripe Checkout Session
        $session_data = array(
            'payment_method_types' => array('card'),
            'mode' => 'payment',
            'success_url' => $success_url . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancel_url,
            'client_reference_id' => $payment->payment_ref,
            'customer_email' => $payment->payer_email,
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => strtolower($payment->currency),
                        'product_data' => array(
                            'name' => $payment_data['item_name'] ?? __('Event Ticket', 'sc_events'),
                            'description' => $payment_data['item_description'] ?? '',
                        ),
                        'unit_amount' => intval($payment->amount * 100),
                    ),
                    'quantity' => 1,
                ),
            ),
            'metadata' => array(
                'payment_ref' => $payment->payment_ref,
                'payment_id' => $payment_id,
                'event_id' => $payment->event_id,
                'ticket_id' => $payment->ticket_id,
            ),
        );

        // Format line_items for Stripe API
        $formatted_data = array(
            'payment_method_types[0]' => 'card',
            'mode' => 'payment',
            'success_url' => $success_url . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancel_url,
            'client_reference_id' => $payment->payment_ref,
            'customer_email' => $payment->payer_email,
            'line_items[0][price_data][currency]' => strtolower($payment->currency),
            'line_items[0][price_data][product_data][name]' => $payment_data['item_name'] ?? __('Event Ticket', 'sc_events'),
            'line_items[0][price_data][unit_amount]' => intval($payment->amount * 100),
            'line_items[0][quantity]' => 1,
            'metadata[payment_ref]' => $payment->payment_ref,
            'metadata[payment_id]' => $payment_id,
            'metadata[event_id]' => $payment->event_id,
            'metadata[ticket_id]' => $payment->ticket_id,
        );

        $session = $this->api_request('/checkout/sessions', $formatted_data);

        if (is_wp_error($session)) {
            $this->update_payment_status($payment_id, 'failed');
            return array(
                'success' => false,
                'error' => $session->get_error_message(),
            );
        }

        // Update payment with session ID
        global $wpdb;
        $tables = sc_get_table_names();
        $wpdb->update(
            $tables['payments'],
            array('gateway_order_id' => $session['id']),
            array('id' => $payment_id)
        );

        // Update status to processing
        $this->update_payment_status($payment_id, 'processing');

        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'payment_ref' => $payment->payment_ref,
            'redirect_url' => $session['url'],
            'session_id' => $session['id'],
        );
    }

    /**
     * Handle callback/webhook from Stripe
     *
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function handle_callback($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('Stripe-Signature');
        $webhook_secret = $this->get_setting('webhook_secret');

        // Verify webhook signature if secret is configured
        if (!empty($webhook_secret) && !empty($sig_header)) {
            if (!$this->verify_webhook_signature($payload, $sig_header, $webhook_secret)) {
                return array(
                    'success' => false,
                    'error' => __('Invalid webhook signature.', 'sc_events'),
                );
            }
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            return array(
                'success' => false,
                'error' => __('Invalid webhook payload.', 'sc_events'),
            );
        }

        // Handle different event types
        switch ($event['type']) {
            case 'checkout.session.completed':
                return $this->handle_checkout_completed($event['data']['object']);

            case 'payment_intent.succeeded':
                // Usually handled by checkout.session.completed
                return array('success' => true, 'message' => 'Acknowledged');

            case 'payment_intent.payment_failed':
                return $this->handle_payment_failed($event['data']['object']);

            default:
                return array('success' => true, 'message' => 'Event type not handled');
        }
    }

    /**
     * Handle checkout session completed
     *
     * @param array $session Session data
     * @return array
     */
    private function handle_checkout_completed($session) {
        $payment_ref = $session['client_reference_id'] ?? ($session['metadata']['payment_ref'] ?? null);

        if (!$payment_ref) {
            return array(
                'success' => false,
                'error' => __('Missing payment reference.', 'sc_events'),
            );
        }

        $payment = self::get_payment_by_ref($payment_ref);

        if (!$payment) {
            return array(
                'success' => false,
                'error' => __('Payment not found.', 'sc_events'),
            );
        }

        // Log the response
        $this->log_gateway_response($payment->id, $session);

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
            array('gateway_transaction_id' => $session['payment_intent'] ?? $session['id']),
            array('id' => $payment->id)
        );

        if ($session['payment_status'] === 'paid') {
            $this->update_payment_status($payment->id, 'completed');

            // Reload payment to get updated data
            $payment = self::get_payment($payment->id);

            // Create attendee
            $attendee_id = $this->create_attendee_from_payment($payment);

            return array(
                'success' => true,
                'payment_id' => $payment->id,
                'attendee_id' => $attendee_id,
                'status' => 'completed',
            );
        }

        return array(
            'success' => true,
            'payment_id' => $payment->id,
            'status' => 'processing',
        );
    }

    /**
     * Handle payment failed
     *
     * @param array $intent Payment intent data
     * @return array
     */
    private function handle_payment_failed($intent) {
        $payment_ref = $intent['metadata']['payment_ref'] ?? null;

        if (!$payment_ref) {
            return array('success' => true, 'message' => 'No payment reference');
        }

        $payment = self::get_payment_by_ref($payment_ref);

        if ($payment) {
            $this->update_payment_status($payment->id, 'failed');
            $this->log_gateway_response($payment->id, $intent);
        }

        return array(
            'success' => true,
            'status' => 'failed',
        );
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw payload
     * @param string $sig_header Signature header
     * @param string $secret Webhook secret
     * @return bool
     */
    private function verify_webhook_signature($payload, $sig_header, $secret) {
        $elements = explode(',', $sig_header);
        $timestamp = null;
        $signatures = array();

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected_sig = hash_hmac('sha256', $signed_payload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected_sig, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify payment status with Stripe
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

        if (empty($payment->gateway_order_id)) {
            return array(
                'success' => false,
                'status' => $payment->status,
                'error' => __('No session ID available.', 'sc_events'),
            );
        }

        $session = $this->get_checkout_session($payment->gateway_order_id);

        if (is_wp_error($session)) {
            return array(
                'success' => false,
                'error' => $session->get_error_message(),
            );
        }

        $status = 'pending';
        if ($session['payment_status'] === 'paid') {
            $status = 'completed';
        } elseif ($session['status'] === 'expired') {
            $status = 'failed';
        }

        return array(
            'success' => true,
            'status' => $status,
            'session_id' => $session['id'],
            'amount' => $session['amount_total'] / 100,
        );
    }

    /**
     * Test gateway connection
     *
     * @return array
     */
    public function test_connection() {
        $result = $this->api_request('/balance', array(), 'GET');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __('Successfully connected to Stripe.', 'sc_events'),
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

        $refund_data = array(
            'payment_intent' => $payment->gateway_transaction_id,
        );

        if ($amount !== null) {
            $refund_data['amount'] = intval($amount * 100);
        }

        if (!empty($reason)) {
            $refund_data['reason'] = 'requested_by_customer';
            $refund_data['metadata[reason]'] = $reason;
        }

        $result = $this->api_request('/refunds', $refund_data);

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }

        $refund_amount = $amount ?? $payment->amount;

        $this->update_payment_status($payment_id, 'refunded', array(
            'refund_amount' => $refund_amount,
            'refunded_at' => current_time('mysql'),
            'refund_reason' => $reason,
        ));

        return array(
            'success' => true,
            'message' => __('Refund processed successfully.', 'sc_events'),
            'refund_id' => $result['id'],
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
            'subscriptions' => true,
            'saved_cards' => true,
            'inline_form' => true,
        );

        return isset($supported[$feature]) ? $supported[$feature] : false;
    }

    /**
     * Get gateway icon URL
     *
     * @return string
     */
    public function get_icon_url() {
        return get_template_directory_uri() . '/assets/images/payment/stripe.png';
    }
}

// Register the gateway
SC_Payment_Gateway_Manager::register('stripe', 'SC_Gateway_Stripe');
