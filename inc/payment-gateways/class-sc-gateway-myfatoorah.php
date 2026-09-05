<?php
/**
 * SC Events MyFatoorah Payment Gateway
 *
 * Integration with MyFatoorah for GCC countries.
 * Supports multiple payment methods per country.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Gateway_MyFatoorah extends SC_Payment_Gateway {

    /**
     * API Base URLs
     */
    const API_URL_TEST = 'https://apitest.myfatoorah.com';
    const API_URL_LIVE = 'https://api.myfatoorah.com';

    /**
     * Constructor
     */
    public function __construct() {
        $this->code = 'myfatoorah';
        $this->name = 'MyFatoorah';
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
     * Get settings fields for admin configuration
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'api_key' => array(
                'title' => __('API Key', 'sc_events'),
                'type' => 'password',
                'description' => __('Your MyFatoorah API Key', 'sc_events'),
                'required' => true,
            ),
            'country_iso' => array(
                'title' => __('Country', 'sc_events'),
                'type' => 'select',
                'description' => __('Select your MyFatoorah account country', 'sc_events'),
                'options' => array(
                    'KWT' => 'Kuwait',
                    'SAU' => 'Saudi Arabia',
                    'BHR' => 'Bahrain',
                    'ARE' => 'UAE',
                    'QAT' => 'Qatar',
                    'OMN' => 'Oman',
                    'JOD' => 'Jordan',
                    'EGY' => 'Egypt',
                ),
                'required' => true,
            ),
        );
    }

    /**
     * Make API request to MyFatoorah
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function api_request($endpoint, $data = array(), $method = 'POST') {
        $api_key = $this->get_setting('api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('MyFatoorah API key not configured.', 'sc_events'));
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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

        if (!isset($body['IsSuccess']) || !$body['IsSuccess']) {
            $error_message = isset($body['Message']) ? $body['Message'] : __('MyFatoorah API error.', 'sc_events');
            if (isset($body['ValidationErrors']) && !empty($body['ValidationErrors'])) {
                $error_message .= ' ' . implode(', ', array_column($body['ValidationErrors'], 'Error'));
            }
            return new WP_Error('myfatoorah_error', $error_message, $body);
        }

        return $body['Data'];
    }

    /**
     * Process payment - initiate MyFatoorah payment
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

        // Build callback URLs — use REST endpoint so callback is properly processed
        $callback_url = $this->get_callback_url();

        $error_url = add_query_arg(array(
            'payment_ref' => $payment->payment_ref,
            'status' => 'failed',
        ), home_url('/payment-failed/'));

        // Prepare invoice data
        $invoice_data = array(
            'CustomerName' => $payment->payer_name,
            'NotificationOption' => 'LNK',
            'InvoiceValue' => floatval($payment->amount),
            'DisplayCurrencyIso' => $payment->currency,
            'CustomerEmail' => $payment->payer_email,
            'CallBackUrl' => $callback_url,
            'ErrorUrl' => $error_url,
            'MobileCountryCode' => '+20',
            'CustomerMobile' => preg_replace('/[^0-9]/', '', $payment->payer_phone ?: '01000000000'),
            'Language' => 'en',
            'CustomerReference' => $payment->payment_ref,
            'InvoiceItems' => array(
                array(
                    'ItemName' => $payment_data['item_name'] ?? __('Event Ticket', 'sc_events'),
                    'Quantity' => 1,
                    'UnitPrice' => floatval($payment->amount),
                ),
            ),
        );

        $result = $this->api_request('/v2/SendPayment', $invoice_data);

        if (is_wp_error($result)) {
            $this->update_payment_status($payment_id, 'failed');
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
            );
        }

        // Update payment with invoice ID
        global $wpdb;
        $tables = sc_get_table_names();
        $wpdb->update(
            $tables['payments'],
            array('gateway_order_id' => $result['InvoiceId']),
            array('id' => $payment_id)
        );

        // Update status to processing
        $this->update_payment_status($payment_id, 'processing');

        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'payment_ref' => $payment->payment_ref,
            'redirect_url' => $result['InvoiceURL'],
            'invoice_id' => $result['InvoiceId'],
        );
    }

    /**
     * Handle callback from MyFatoorah
     *
     * @param WP_REST_Request $request Request object
     * @return array
     */
    public function handle_callback($request) {
        $payment_id = $request->get_param('paymentId');

        if (empty($payment_id)) {
            return array(
                'success' => false,
                'error' => __('Missing payment ID.', 'sc_events'),
            );
        }

        // Get payment status from MyFatoorah
        $result = $this->api_request('/v2/GetPaymentStatus', array(
            'Key' => $payment_id,
            'KeyType' => 'PaymentId',
        ));

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
            );
        }

        $customer_reference = $result['CustomerReference'] ?? null;

        if (!$customer_reference) {
            return array(
                'success' => false,
                'error' => __('Missing customer reference.', 'sc_events'),
            );
        }

        // Find payment by reference
        $payment = self::get_payment_by_ref($customer_reference);

        if (!$payment) {
            return array(
                'success' => false,
                'error' => __('Payment not found.', 'sc_events'),
            );
        }

        // Log the response
        $this->log_gateway_response($payment->id, $result);

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
            array('gateway_transaction_id' => $payment_id),
            array('id' => $payment->id)
        );

        $invoice_status = $result['InvoiceStatus'] ?? '';

        if ($invoice_status === 'Paid') {
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
        } elseif ($invoice_status === 'Pending') {
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
                'error' => $result['InvoiceError'] ?? __('Payment failed.', 'sc_events'),
            );
        }
    }

    /**
     * Verify payment status with MyFatoorah
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
                'error' => __('No invoice ID available.', 'sc_events'),
            );
        }

        $result = $this->api_request('/v2/GetPaymentStatus', array(
            'Key' => $payment->gateway_order_id,
            'KeyType' => 'InvoiceId',
        ));

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
            );
        }

        $status = 'pending';
        $invoice_status = $result['InvoiceStatus'] ?? '';

        if ($invoice_status === 'Paid') {
            $status = 'completed';
        } elseif (in_array($invoice_status, array('Expired', 'Canceled'))) {
            $status = 'failed';
        }

        return array(
            'success' => true,
            'status' => $status,
            'invoice_id' => $result['InvoiceId'],
            'amount' => $result['InvoiceValue'],
        );
    }

    /**
     * Test gateway connection
     *
     * @return array
     */
    public function test_connection() {
        $result = $this->api_request('/v2/InitiatePayment', array(
            'InvoiceAmount' => 1,
            'CurrencyIso' => 'KWD',
        ));

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __('Successfully connected to MyFatoorah.', 'sc_events'),
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

        if (!$payment || empty($payment->gateway_order_id)) {
            return array(
                'success' => false,
                'message' => __('Invalid payment or no invoice ID.', 'sc_events'),
            );
        }

        $refund_amount = $amount ?? $payment->amount;

        $result = $this->api_request('/v2/MakeRefund', array(
            'Key' => $payment->gateway_order_id,
            'KeyType' => 'InvoiceId',
            'RefundChargeOnCustomer' => false,
            'ServiceChargeOnCustomer' => false,
            'Amount' => $refund_amount,
            'Comment' => $reason ?: 'Refund requested',
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
            'refund_id' => $result['RefundId'] ?? null,
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
        return get_template_directory_uri() . '/assets/images/payment/myfatoorah.png';
    }
}

// Register the gateway
SC_Payment_Gateway_Manager::register('myfatoorah', 'SC_Gateway_MyFatoorah');
