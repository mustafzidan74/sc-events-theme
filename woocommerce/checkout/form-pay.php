<?php
/**
 * Pay for order form
 *
 * Custom template for order payment page
 *
 * @package SC_Events
 */

defined('ABSPATH') || exit;


$totals = $order->get_order_item_totals(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
?>

<div class="sc-payment-page">
    <div class="sc-payment-container">
        <div class="sc-payment-card">
            <!-- Header -->
            <div class="sc-payment-header">
                <h1><i class="fa-solid fa-credit-card"></i> Complete Your Payment</h1>
                <p>Secure payment for Order #<?php echo esc_html($order->get_order_number()); ?></p>
            </div>

            <!-- Body -->
            <div class="sc-payment-body">
                <!-- Order Info -->
                <div class="sc-order-info">
                    <div class="sc-order-info-item">
                        <div class="sc-order-info-label">Order Number</div>
                        <div class="sc-order-info-value">#<?php echo esc_html($order->get_order_number()); ?></div>
                    </div>
                    <div class="sc-order-info-item">
                        <div class="sc-order-info-label">Order Date</div>
                        <div class="sc-order-info-value"><?php echo esc_html($order->get_date_created()->date_i18n('M d, Y')); ?></div>
                    </div>
                    <div class="sc-order-info-item">
                        <div class="sc-order-info-label">Total Amount</div>
                        <div class="sc-order-info-value"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></div>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="sc-order-details">
                    <h2><i class="fa-solid fa-list"></i> Order Details</h2>

                    <table class="sc-order-table">
                        <?php if (!empty($totals)) : ?>
                            <?php foreach ($totals as $total_key => $total) : ?>
                                <tr class="<?php echo esc_attr($total_key === 'order_total' ? 'sc-order-total' : ''); ?>">
                                    <th><?php echo esc_html($total['label']); ?></th>
                                    <td><?php echo wp_kses_post($total['value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Payment Methods -->
                <div class="sc-payment-methods">
                    <h2><i class="fa-solid fa-wallet"></i> Select Payment Method</h2>

                    <form id="order_review" method="post">
                        <?php if ($order->needs_payment()) : ?>
                            <div id="payment" class="woocommerce-checkout-payment">
                                <?php woocommerce_checkout_payment(); ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="sc-pay-button" id="place_order" value="<?php esc_attr_e('Pay for order', 'woocommerce'); ?>" data-value="<?php esc_attr_e('Pay for order', 'woocommerce'); ?>">
                            <i class="fa-solid fa-lock"></i> <?php esc_html_e('Pay Securely Now', 'woocommerce'); ?>
                        </button>

                        <input type="hidden" name="woocommerce_pay" value="1" />
                        <?php wp_nonce_field('woocommerce-pay', 'woocommerce-pay-nonce'); ?>
                    </form>
                </div>

                <!-- Secure Payment Badge -->
                <div class="sc-secure-payment">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Your payment is secured with SSL encryption</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Update button text on processing
    $('#order_review').on('submit', function() {
        var $btn = $('#place_order');
        $btn.prop('disabled', true);
        $btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Processing Payment...');
    });

    // Highlight selected payment method
    $('.wc_payment_method input[type="radio"]').on('change', function() {
        $('.wc_payment_method').removeClass('selected');
        $(this).closest('.wc_payment_method').addClass('selected');
    });
});
</script>

