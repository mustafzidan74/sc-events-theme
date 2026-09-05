<?php
/**
 * SC Events Payment Gateways Initialization
 *
 * Loads all payment gateway classes and registers REST API endpoints.
 *
 * @package sc_events
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load payment gateway classes
 */
function sc_load_payment_gateways() {
    $gateway_dir = get_template_directory() . '/inc/payment-gateways/';

    // Load base class first
    require_once $gateway_dir . 'class-sc-payment-gateway.php';

    // Load gateway implementations
    require_once $gateway_dir . 'class-sc-gateway-paymob.php';
    require_once $gateway_dir . 'class-sc-gateway-stripe.php';
    require_once $gateway_dir . 'class-sc-gateway-myfatoorah.php';
    require_once $gateway_dir . 'class-sc-gateway-kashier.php';
}
add_action('after_setup_theme', 'sc_load_payment_gateways', 5);

/**
 * Register payment REST API endpoints
 */
function sc_register_payment_rest_routes() {
    // Callback endpoint for each gateway
    register_rest_route('sc-events/v1', '/payment/callback/(?P<gateway>[a-z_-]+)', array(
        'methods' => array('GET', 'POST'),
        'callback' => 'sc_handle_payment_callback',
        'permission_callback' => '__return_true',
        'args' => array(
            'gateway' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));

    // Webhook endpoint for each gateway
    register_rest_route('sc-events/v1', '/payment/webhook/(?P<gateway>[a-z_-]+)', array(
        'methods' => 'POST',
        'callback' => 'sc_handle_payment_webhook',
        'permission_callback' => '__return_true',
        'args' => array(
            'gateway' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));

    // Verify payment endpoint
    register_rest_route('sc-events/v1', '/payment/verify', array(
        'methods' => 'POST',
        'callback' => 'sc_verify_payment_rest',
        'permission_callback' => '__return_true',
    ));

    // Get enabled gateways endpoint
    register_rest_route('sc-events/v1', '/payment/gateways', array(
        'methods' => 'GET',
        'callback' => 'sc_get_enabled_gateways_rest',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'sc_register_payment_rest_routes');

/**
 * Handle payment callback
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function sc_handle_payment_callback($request) {
    $gateway_code = $request->get_param('gateway');

    $gateway = SC_Payment_Gateway_Manager::get_gateway($gateway_code);

    if (!$gateway) {
        return new WP_REST_Response(array(
            'success' => false,
            'error' => __('Unknown payment gateway.', 'sc_events'),
        ), 400);
    }

    $result = $gateway->handle_callback($request);

    // If payment completed, redirect to success page
    if (!empty($result['success']) && $result['status'] === 'completed') {
        $payment = SC_Payment_Gateway::get_payment($result['payment_id']);
        if ($payment) {
            $redirect_url = add_query_arg(array(
                'payment_ref' => $payment->payment_ref,
                'status' => 'success',
            ), home_url('/payment-success/'));

            // For GET requests (user redirects), do actual redirect
            if ($request->get_method() === 'GET') {
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    // For GET requests (user redirects), always redirect to a human-readable page
    if ($request->get_method() === 'GET') {
        $payment_id = $result['payment_id'] ?? null;
        if ($payment_id) {
            $payment = SC_Payment_Gateway::get_payment($payment_id);
            if ($payment) {
                if ($payment->status === 'failed') {
                    $redirect_url = add_query_arg(array(
                        'payment_ref' => $payment->payment_ref,
                        'status' => 'failed',
                        'error' => urlencode($result['error'] ?? ''),
                    ), home_url('/payment-failed/'));
                } else {
                    // processing or other status — send to success page (it handles processing with auto-refresh)
                    $redirect_url = add_query_arg(array(
                        'payment_ref' => $payment->payment_ref,
                        'status' => $payment->status,
                    ), home_url('/payment-success/'));
                }
                wp_redirect($redirect_url);
                exit;
            }
        }

        // Fallback: no payment found, go home
        wp_redirect(home_url('/'));
        exit;
    }

    return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
}

/**
 * Handle payment webhook
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function sc_handle_payment_webhook($request) {
    $gateway_code = $request->get_param('gateway');

    $gateway = SC_Payment_Gateway_Manager::get_gateway($gateway_code);

    if (!$gateway) {
        return new WP_REST_Response(array(
            'success' => false,
            'error' => __('Unknown payment gateway.', 'sc_events'),
        ), 400);
    }

    $result = $gateway->handle_callback($request);

    // Always return 200 for webhooks to prevent retries
    return new WP_REST_Response($result, 200);
}

/**
 * Verify payment status
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function sc_verify_payment_rest($request) {
    $payment_ref = $request->get_param('payment_ref');

    if (empty($payment_ref)) {
        return new WP_REST_Response(array(
            'success' => false,
            'error' => __('Payment reference required.', 'sc_events'),
        ), 400);
    }

    $payment = SC_Payment_Gateway::get_payment_by_ref($payment_ref);

    if (!$payment) {
        return new WP_REST_Response(array(
            'success' => false,
            'error' => __('Payment not found.', 'sc_events'),
        ), 404);
    }

    // If already completed, return status
    if ($payment->status === 'completed') {
        return new WP_REST_Response(array(
            'success' => true,
            'status' => 'completed',
            'attendee_id' => $payment->attendee_id,
        ));
    }

    // Verify with gateway
    $gateway = SC_Payment_Gateway_Manager::get_gateway($payment->gateway_code);

    if (!$gateway) {
        return new WP_REST_Response(array(
            'success' => false,
            'status' => $payment->status,
        ));
    }

    $result = $gateway->verify_payment($payment_ref);

    // Update local status if changed
    if (!empty($result['status']) && $result['status'] !== $payment->status) {
        $gateway->update_payment_status($payment->id, $result['status']);

        // Create attendee if now completed
        if ($result['status'] === 'completed') {
            $payment = SC_Payment_Gateway::get_payment($payment->id);
            $attendee_id = $gateway->create_attendee_from_payment($payment);
            $result['attendee_id'] = $attendee_id;
        }
    }

    return new WP_REST_Response($result);
}

/**
 * Get enabled payment gateways
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response
 */
function sc_get_enabled_gateways_rest($request) {
    $enabled = SC_Payment_Gateway_Manager::get_enabled_gateways();

    $gateways = array();
    foreach ($enabled as $code => $gateway) {
        $gateways[] = array(
            'code' => $code,
            'name' => $gateway->get_name(),
            'icon' => $gateway->get_icon_url(),
        );
    }

    return new WP_REST_Response(array(
        'success' => true,
        'gateways' => $gateways,
    ));
}

/**
 * Process payment - AJAX handler for checkout page
 * Handles payment gateway integration with quantity, coupon, and extra fields support
 */
function sc_process_payment_ajax() {
    check_ajax_referer('sc_checkout_nonce', 'nonce');

    $gateway_code = sanitize_text_field($_POST['gateway'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $discount_amount = floatval($_POST['discount'] ?? 0);
    $payer_name = sanitize_text_field($_POST['name'] ?? '');
    $payer_email = sanitize_email($_POST['email'] ?? '');
    $payer_phone = sanitize_text_field($_POST['phone'] ?? '');

    // Extra fields: from form or from session
    $extra_fields = isset($_POST['extra_fields']) ? $_POST['extra_fields'] : array();
    if (empty($extra_fields) && !empty($_POST['session_extra_fields'])) {
        $extra_fields = json_decode(stripslashes($_POST['session_extra_fields']), true);
        if (!is_array($extra_fields)) {
            $extra_fields = array();
        }
    }

    // Also check session for extra fields
    if (empty($extra_fields)) {
        if (!session_id()) session_start();
        $session_checkout = $_SESSION['sc_checkout'] ?? array();
        if (!empty($session_checkout['extra_fields'])) {
            $extra_fields = $session_checkout['extra_fields'];
        }
    }

    // Validate required fields
    if (empty($event_id) || empty($ticket_id) || empty($payer_name) || empty($payer_email)) {
        wp_send_json_error(array('message' => __('Please fill all required fields.', 'sc_events')));
    }

    // Validate email
    if (!is_email($payer_email)) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'sc_events')));
    }

    // Server-side double-click protection: check for recent pending/processing payment
    // for the same email + event + ticket within the last 5 minutes
    global $wpdb;
    $tables = sc_get_table_names();
    $recent_payment = $wpdb->get_row($wpdb->prepare(
        "SELECT id, payment_ref, status FROM {$tables['payments']}
         WHERE payer_email = %s AND event_id = %d AND ticket_id = %d
           AND status IN ('pending', 'processing')
           AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY id DESC LIMIT 1",
        $payer_email, $event_id, $ticket_id
    ));

    if ($recent_payment) {
        wp_send_json_error(array(
            'message' => __('A payment is already being processed. Please wait for it to complete or try again in a few minutes.', 'sc_events'),
        ));
    }

    // Get ticket
    if (!class_exists('SC_Ticket')) {
        wp_send_json_error(array('message' => __('Unable to find ticket.', 'sc_events')));
    }
    $ticket = SC_Ticket::get($ticket_id);
    if (!$ticket) {
        wp_send_json_error(array('message' => __('Ticket not found.', 'sc_events')));
    }

    // Get event
    if (!class_exists('SC_Event')) {
        wp_send_json_error(array('message' => __('Unable to find event.', 'sc_events')));
    }
    $event = SC_Event::get($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Event not found.', 'sc_events')));
    }

    // Check ticket availability
    if ($ticket->quantity > 0) {
        $available = $ticket->quantity - $ticket->sold;
        if ($available < $quantity) {
            wp_send_json_error(array('message' => __('Sorry, not enough tickets available.', 'sc_events')));
        }
    }

    // Calculate total
    $ticket_price = floatval($ticket->price);
    $subtotal = $ticket_price * $quantity;

    // Re-validate coupon server-side
    $verified_discount = 0;
    if (!empty($coupon_code)) {
        $coupon = sc_find_coupon($coupon_code, $event_id);
        if ($coupon) {
            $discount_type = get_post_meta($coupon->ID, 'discount_type', true);
            $discount_value = floatval(get_post_meta($coupon->ID, 'discount_value', true));

            if ($discount_type === 'percentage') {
                $verified_discount = ($subtotal * $discount_value) / 100;
            } else {
                $verified_discount = $discount_value;
            }
            $verified_discount = min($verified_discount, $subtotal);
        }
    }

    $final_total = max(0, $subtotal - $verified_discount);

    // Handle free total (coupon made it free)
    if ($final_total == 0) {
        $created = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $attendee_data = array(
                'event_id'       => $event_id,
                'ticket_id'      => $ticket_id,
                'user_id'        => get_current_user_id() ?: null,
                'name'           => $payer_name,
                'email'          => $payer_email,
                'phone'          => $payer_phone,
                'payment_status' => 'success',
                'payment_method' => !empty($coupon_code) ? 'coupon' : 'free',
                'amount_paid'    => 0,
                'coupon_code'    => $coupon_code,
                'extra_fields'   => $extra_fields,
            );

            if (class_exists('SC_Attendee')) {
                $attendee_id = SC_Attendee::create($attendee_data);
                if ($attendee_id) {
                    sc_public_send_ticket_email($attendee_id);
                    $created++;
                }
            }
        }

        // Increment coupon usage
        if (!empty($coupon_code) && isset($coupon)) {
            $usage_count = intval(get_post_meta($coupon->ID, 'usage_count', true));
            update_post_meta($coupon->ID, 'usage_count', $usage_count + 1);
        }

        // Clear session
        unset($_SESSION['sc_checkout']);

        if ($created > 0) {
            wp_send_json_success(array(
                'redirect_url' => home_url('/my-account/'),
            ));
        }

        wp_send_json_error(array('message' => __('Failed to create registration.', 'sc_events')));
    }

    // Paid ticket — need a payment gateway
    if (empty($gateway_code)) {
        wp_send_json_error(array('message' => __('Please select a payment method.', 'sc_events')));
    }

    $gateway = SC_Payment_Gateway_Manager::get_gateway($gateway_code);
    if (!$gateway || !$gateway->is_enabled()) {
        wp_send_json_error(array('message' => __('Selected payment method is not available.', 'sc_events')));
    }

    // Prepare payment data
    $item_name = $event->title . ' - ' . $ticket->name;
    if ($quantity > 1) {
        $item_name .= ' (x' . $quantity . ')';
    }

    $payment_data = array(
        'event_id'    => $event_id,
        'ticket_id'   => $ticket_id,
        'amount'      => $final_total,
        'quantity'     => $quantity,
        'payer_name'  => $payer_name,
        'payer_email' => $payer_email,
        'payer_phone' => $payer_phone,
        'item_name'   => $item_name,
        'metadata'    => array(
            'event_title'  => $event->title,
            'ticket_name'  => $ticket->name,
            'quantity'     => $quantity,
            'ticket_price' => $ticket_price,
            'coupon_code'  => $coupon_code,
            'discount'     => $verified_discount,
            'extra_fields' => $extra_fields,
        ),
    );

    // Process payment
    $result = $gateway->process_payment($payment_data);

    if ($result['success']) {
        // Clear session after successful payment initiation
        unset($_SESSION['sc_checkout']);

        wp_send_json_success(array(
            'redirect_url' => $result['redirect_url'],
            'payment_ref' => $result['payment_ref'],
        ));
    } else {
        wp_send_json_error(array('message' => $result['error']));
    }
}
add_action('wp_ajax_sc_process_payment', 'sc_process_payment_ajax');
add_action('wp_ajax_nopriv_sc_process_payment', 'sc_process_payment_ajax');

/**
 * Get payment status - AJAX handler
 */
function sc_get_payment_status_ajax() {
    $payment_ref = sanitize_text_field($_GET['payment_ref'] ?? '');

    if (empty($payment_ref)) {
        wp_send_json_error(array('message' => __('Payment reference required.', 'sc_events')));
    }

    $payment = SC_Payment_Gateway::get_payment_by_ref($payment_ref);

    if (!$payment) {
        wp_send_json_error(array('message' => __('Payment not found.', 'sc_events')));
    }

    wp_send_json_success(array(
        'status' => $payment->status,
        'attendee_id' => $payment->attendee_id,
    ));
}
add_action('wp_ajax_sc_get_payment_status', 'sc_get_payment_status_ajax');
add_action('wp_ajax_nopriv_sc_get_payment_status', 'sc_get_payment_status_ajax');
