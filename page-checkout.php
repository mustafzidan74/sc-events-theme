<?php
/**
 * Template Name: Event Checkout
 * Handles event ticket checkout and payment
 *
 * @package sc_events
 * @version 2.0.0
 */

// Get parameters
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

// Read session data from modal flow
if (!session_id()) {
    session_start();
}
$session_checkout = $_SESSION['sc_checkout'] ?? array();
$session_qty = intval($session_checkout['quantity'] ?? 1);
$session_coupon = sanitize_text_field($session_checkout['coupon_code'] ?? '');
$session_discount = floatval($session_checkout['discount'] ?? 0);
$session_total = floatval($session_checkout['total'] ?? 0);
$session_extra_fields = $session_checkout['extra_fields'] ?? array();

// Validate event
$event = null;
if (class_exists('SC_Event') && $event_id) {
    $event = SC_Event::get($event_id);
}

if (!$event) {
    wp_redirect(home_url('/'));
    exit;
}

// Get ticket
$ticket = null;
if (class_exists('SC_Ticket') && $ticket_id) {
    $ticket = SC_Ticket::get($ticket_id);
    // If the selected ticket is free, clear it (free tickets are handled in the modal)
    if ($ticket && floatval($ticket->price) <= 0) {
        $ticket = null;
    }
}

// Get all PAID tickets for the event (free tickets are handled in the modal)
$tickets = array();
if (class_exists('SC_Ticket')) {
    $all_tickets = SC_Ticket::get_by_event($event_id);
    $tickets = array_filter($all_tickets, function($t) {
        return floatval($t->price) > 0;
    });
}

// Platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'medium') : '';

// Get enabled payment gateways
$gateways = array();
$any_test_mode = false;
if (class_exists('SC_Payment_Gateway_Manager')) {
    $gateways = SC_Payment_Gateway_Manager::get_enabled_gateways();
    foreach ($gateways as $gw) {
        if ($gw->is_test_mode()) {
            $any_test_mode = true;
            break;
        }
    }
}

// Get event extra fields
$extra_fields = array();
if (!empty($event->extra_fields)) {
    $extra_fields = is_string($event->extra_fields) ? json_decode($event->extra_fields, true) : $event->extra_fields;
    if (!is_array($extra_fields)) {
        $extra_fields = array();
    }
}

// Currency settings
$currency = sc_get_currency_settings();

// Format dates
$start_date_formatted = $event->start_date ? date('d M Y', strtotime($event->start_date)) : '';
$start_time = $event->start_time ? date('H:i', strtotime($event->start_time)) : '';

// Pre-populate from logged-in user
$current_user = wp_get_current_user();
$user_name = $current_user->ID ? $current_user->display_name : '';
$user_email = $current_user->ID ? $current_user->user_email : '';
$user_phone = $current_user->ID ? get_user_meta($current_user->ID, 'phone', true) : '';

// Calculate prices
$ticket_price = $ticket ? floatval($ticket->price) : 0;
$quantity = max(1, $session_qty);
$subtotal = $ticket_price * $quantity;
$discount = min($session_discount, $subtotal);
$total = max(0, $subtotal - $discount);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($event->title); ?> - Checkout</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme CSS -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/variables.css">
    <?php require_once get_template_directory() . '/inc/public-frontend/frontend-dynamic-colors.php'; ?>
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/light-theme.css">

    <!-- Checkout Page Styles -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/checkout-page.css">

    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--sc-bg-primary, #0a0a0f); min-height: 100vh; padding: 40px 20px; margin: 0; }
        @keyframes sc-orb-float-1 { 0%,100% { transform: translate(0,0); } 33% { transform: translate(80px,50px); } 66% { transform: translate(-30px,80px); } }
        @keyframes sc-orb-float-2 { 0%,100% { transform: translate(0,0); } 33% { transform: translate(-60px,-40px); } 66% { transform: translate(40px,-60px); } }
    </style>
    <?php $default_theme = get_option('sc_default_theme', 'dark'); ?>
    <script>
    (function(){
        var saved = localStorage.getItem('sc_theme');
        var theme = saved || '<?php echo esc_js($default_theme); ?>';
        document.documentElement.className = 'sc-' + theme + '-theme';
    })();
    </script>
</head>
<body class="sc-<?php echo esc_attr($default_theme); ?>-theme">
    <div class="sc-orbs-container" aria-hidden="true" style="position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;">
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:600px;height:600px;top:-10%;left:-10%;background:var(--sc-primary);animation:sc-orb-float-1 20s ease-in-out infinite;"></div>
        <div style="position:absolute;border-radius:50%;filter:blur(80px);opacity:0.07;width:500px;height:500px;bottom:-10%;right:-10%;background:var(--sc-secondary);animation:sc-orb-float-2 25s ease-in-out infinite;"></div>
    </div>

    <div class="checkout-container">
        <?php if ($any_test_mode): ?>
        <div style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; text-align: center; padding: 8px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 600;">
            <i class="fas fa-flask"></i> <?php _e('TEST MODE — No real charges will be made', 'sc_events'); ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="checkout-header">
            <?php if ($platform_logo_url): ?>
                <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php endif; ?>
            <h1>Complete Your Registration</h1>
            <p>Secure checkout for <?php echo esc_html($event->title); ?></p>
        </div>

        <form id="checkout-form" method="post">
            <div class="checkout-grid">
                <!-- Left Column - Form -->
                <div class="checkout-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user me-2"></i> Your Information</h3>
                    </div>
                    <div class="card-body">
                        <div id="error-message" class="alert alert-danger" style="display: none;"></div>

                        <div class="form-group">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Enter your full name" value="<?php echo esc_attr($user_name); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="Enter your email" value="<?php echo esc_attr($user_email); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number" value="<?php echo esc_attr($user_phone); ?>">
                        </div>

                        <?php if (!empty($extra_fields) && empty($session_extra_fields)): ?>
                            <?php foreach ($extra_fields as $field): ?>
                                <?php if (!empty($field['label'])): ?>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <?php echo esc_html($field['label']); ?>
                                            <?php if (!empty($field['required'])): ?>
                                                <span class="required">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php
                                        $field_name = 'extra_fields[' . sanitize_title($field['label']) . ']';
                                        $field_type = $field['type'] ?? 'text';
                                        $is_required = !empty($field['required']);
                                        ?>
                                        <?php if ($field_type === 'select' && !empty($field['options'])): ?>
                                            <select name="<?php echo esc_attr($field_name); ?>" class="form-select" <?php echo $is_required ? 'required' : ''; ?>>
                                                <option value="">Select...</option>
                                                <?php foreach ((array)$field['options'] as $opt): ?>
                                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($field_type === 'textarea'): ?>
                                            <textarea name="<?php echo esc_attr($field_name); ?>" class="form-control" rows="3" <?php echo $is_required ? 'required' : ''; ?>></textarea>
                                        <?php else: ?>
                                            <input type="<?php echo esc_attr($field_type); ?>" name="<?php echo esc_attr($field_name); ?>" class="form-control" <?php echo $is_required ? 'required' : ''; ?>>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Ticket Selection -->
                    <?php if (count($tickets) > 1 || !$ticket): ?>
                    <div class="card-header">
                        <h3><i class="fas fa-ticket-alt me-2"></i> Select Ticket</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($tickets as $t): ?>
                            <?php
                            $is_sold_out = $t->quantity > 0 && $t->sold >= $t->quantity;
                            $is_active = $t->is_active;
                            $is_selected = $ticket && $ticket->id == $t->id;
                            ?>
                            <label class="ticket-option <?php echo $is_selected ? 'selected' : ''; ?> <?php echo $is_sold_out || !$is_active ? 'ticket-sold-out' : ''; ?>">
                                <input type="radio" name="ticket_id" value="<?php echo esc_attr($t->id); ?>"
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       <?php echo $is_sold_out || !$is_active ? 'disabled' : ''; ?>
                                       data-price="<?php echo esc_attr($t->price); ?>"
                                       data-name="<?php echo esc_attr($t->name); ?>">
                                <div class="ticket-info">
                                    <div class="ticket-name"><?php echo esc_html($t->name); ?></div>
                                    <?php if ($t->description): ?>
                                        <div class="text-muted small"><?php echo esc_html($t->description); ?></div>
                                    <?php endif; ?>
                                    <?php if ($is_sold_out): ?>
                                        <span class="badge bg-danger">Sold Out</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-price">
                                    <?php echo floatval($t->price) > 0 ? sc_format_price($t->price) : __('Free', 'sc_events'); ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>">
                    <?php endif; ?>

                    <!-- Payment Methods -->
                    <?php if (!empty($gateways)): ?>
                    <div class="card-header">
                        <h3><i class="fas fa-credit-card me-2"></i> Payment Method</h3>
                    </div>
                    <div class="card-body" id="payment-methods-section">
                        <?php $first = true; foreach ($gateways as $code => $gateway): ?>
                            <label class="payment-method <?php echo $first ? 'selected' : ''; ?>">
                                <input type="radio" name="gateway" value="<?php echo esc_attr($code); ?>" <?php echo $first ? 'checked' : ''; ?>>
                                <span class="payment-method-name"><?php echo esc_html($gateway->get_name()); ?></span>
                            </label>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <?php else: ?>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php _e('No payment methods available. Please contact the organizer.', 'sc_events'); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column - Order Summary -->
                <div class="checkout-card order-summary">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt me-2"></i> Order Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="event-info">
                            <?php
                            $event_image = $event->featured_image ? wp_get_attachment_image_url($event->featured_image, 'thumbnail') : '';
                            ?>
                            <?php if ($event_image): ?>
                                <img src="<?php echo esc_url($event_image); ?>" alt="" class="event-image">
                            <?php else: ?>
                                <div class="event-image" style="display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-alt fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="event-details">
                                <h4><?php echo esc_html($event->title); ?></h4>
                                <div class="event-meta">
                                    <div><i class="fas fa-calendar"></i> <?php echo esc_html($start_date_formatted); ?> <?php echo $start_time ? '@ ' . esc_html($start_time) : ''; ?></div>
                                    <?php if ($event->venue_name): ?>
                                        <div><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($event->venue_name); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="summary-row">
                            <span><?php _e('Ticket', 'sc_events'); ?></span>
                            <span id="summary-ticket-name"><?php echo $ticket ? esc_html($ticket->name) : '-'; ?></span>
                        </div>
                        <div class="summary-row">
                            <span><?php _e('Price', 'sc_events'); ?></span>
                            <span id="summary-price"><?php echo $ticket ? sc_format_price($ticket->price) : '-'; ?></span>
                        </div>
                        <?php if ($quantity > 1): ?>
                        <div class="summary-row">
                            <span><?php _e('Quantity', 'sc_events'); ?></span>
                            <span id="summary-qty">&times; <?php echo esc_html($quantity); ?></span>
                        </div>
                        <div class="summary-row">
                            <span><?php _e('Subtotal', 'sc_events'); ?></span>
                            <span id="summary-subtotal"><?php echo sc_format_price($subtotal); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                        <div class="summary-row" style="color: #22c55e;">
                            <span><?php _e('Discount', 'sc_events'); ?> <?php if ($session_coupon) echo '(' . esc_html($session_coupon) . ')'; ?></span>
                            <span id="summary-discount">-<?php echo sc_format_price($discount); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row total">
                            <span><?php _e('Total', 'sc_events'); ?></span>
                            <span id="summary-total"><?php echo sc_format_price($total); ?></span>
                        </div>

                        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                        <input type="hidden" name="quantity" value="<?php echo esc_attr($quantity); ?>">
                        <input type="hidden" name="coupon_code" value="<?php echo esc_attr($session_coupon); ?>">
                        <input type="hidden" name="discount" value="<?php echo esc_attr($discount); ?>">
                        <?php if (!empty($session_extra_fields)): ?>
                            <input type="hidden" name="session_extra_fields" value="<?php echo esc_attr(wp_json_encode($session_extra_fields)); ?>">
                        <?php endif; ?>
                        <?php wp_nonce_field('sc_checkout_nonce', 'nonce'); ?>

                        <button type="submit" class="btn-checkout" id="btn-checkout" <?php echo empty($gateways) && $total > 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-lock me-2"></i>
                            <span id="btn-text"><?php echo $total == 0 ? __('Register Now', 'sc_events') : __('Proceed to Payment', 'sc_events'); ?></span>
                        </button>

                        <div class="secure-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure 256-bit SSL Encryption</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sync body class with saved theme
        (function(){
            var saved = localStorage.getItem('sc_theme');
            if (saved) {
                document.body.className = document.body.className.replace(/sc-(dark|light)-theme/g, '');
                document.body.classList.add('sc-' + saved + '-theme');
            }
        })();
    </script>
    <script>
        (function() {
            const form = document.getElementById('checkout-form');
            const ticketInputs = document.querySelectorAll('input[name="ticket_id"]');
            const paymentInputs = document.querySelectorAll('input[name="gateway"]');
            const summaryTicketName = document.getElementById('summary-ticket-name');
            const summaryPrice = document.getElementById('summary-price');
            const summaryTotal = document.getElementById('summary-total');
            const btnCheckout = document.getElementById('btn-checkout');
            const btnText = document.getElementById('btn-text');
            const errorMessage = document.getElementById('error-message');
            const paymentSection = document.getElementById('payment-methods-section');

            const currencySettings = <?php echo json_encode($currency); ?>;
            const sessionQty = <?php echo intval($quantity); ?>;
            const sessionDiscount = <?php echo floatval($discount); ?>;

            function formatPrice(amount) {
                amount = parseFloat(amount);
                if (amount === 0) return '<?php echo __('Free', 'sc_events'); ?>';

                const formatted = amount.toFixed(currencySettings.decimal_places)
                    .replace('.', currencySettings.decimal_separator)
                    .replace(/\B(?=(\d{3})+(?!\d))/g, currencySettings.thousand_separator);

                if (currencySettings.position === 'before') {
                    return currencySettings.symbol + ' ' + formatted;
                }
                return formatted + ' ' + currencySettings.symbol;
            }

            // Update summary when ticket changes
            ticketInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Update selected class
                    document.querySelectorAll('.ticket-option').forEach(el => el.classList.remove('selected'));
                    this.closest('.ticket-option').classList.add('selected');

                    const price = parseFloat(this.dataset.price);
                    const name = this.dataset.name;
                    const subtotal = price * sessionQty;
                    const total = Math.max(0, subtotal - sessionDiscount);

                    summaryTicketName.textContent = name;
                    summaryPrice.textContent = formatPrice(price);
                    summaryTotal.textContent = formatPrice(total);

                    // Update button text and payment section visibility
                    if (total === 0) {
                        btnText.textContent = '<?php echo __('Register Now', 'sc_events'); ?>';
                        if (paymentSection) paymentSection.style.display = 'none';
                        btnCheckout.disabled = false;
                    } else {
                        btnText.textContent = '<?php echo __('Proceed to Payment', 'sc_events'); ?>';
                        if (paymentSection) paymentSection.style.display = '';
                        <?php if (empty($gateways)): ?>
                        btnCheckout.disabled = true;
                        <?php else: ?>
                        btnCheckout.disabled = false;
                        <?php endif; ?>
                    }
                });
            });

            // Update selected class for payment methods
            paymentInputs.forEach(input => {
                input.addEventListener('change', function() {
                    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
                    this.closest('.payment-method').classList.add('selected');
                });
            });

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                errorMessage.style.display = 'none';
                btnCheckout.disabled = true;
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

                const formData = new FormData(form);
                formData.append('action', 'sc_process_payment');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to payment gateway or success page
                        window.location.href = data.data.redirect_url;
                    } else {
                        errorMessage.textContent = data.data.message || '<?php echo __('An error occurred. Please try again.', 'sc_events'); ?>';
                        errorMessage.style.display = 'block';
                        btnCheckout.disabled = false;
                        btnText.innerHTML = '<?php echo __('Proceed to Payment', 'sc_events'); ?>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    errorMessage.textContent = '<?php echo __('An error occurred. Please try again.', 'sc_events'); ?>';
                    errorMessage.style.display = 'block';
                    btnCheckout.disabled = false;
                    btnText.innerHTML = '<?php echo __('Proceed to Payment', 'sc_events'); ?>';
                });
            });
        })();
    </script>
</body>
</html>
