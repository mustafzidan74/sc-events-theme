<?php
/**
 * Event Checkout Modal Template Part
 * Dark Premium Glass Card Design
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$event = $args['event'] ?? null;
$event_id = $args['event_id'] ?? 0;
$tickets = $args['tickets'] ?? array();
$is_registered = $args['is_registered'] ?? false;
$assets_url = $args['assets_url'] ?? '';

if (!$event) return;

$event_extra_fields_raw = $event->extra_fields ?? array();
if (is_string($event_extra_fields_raw)) {
    $event_extra_fields_raw = json_decode($event_extra_fields_raw, true) ?: array();
}

// Normalize extra fields for JS consumption
// Admin saves: {label, type, required, options (string), default}
// JS expects: {id, label, field_type, required, show_attendee_form, options (array), placeholder}
$event_extra_fields = array();
foreach ($event_extra_fields_raw as $idx => $field) {
    $options_raw = $field['options'] ?? '';
    $options_arr = array();
    if (is_array($options_raw)) {
        $options_arr = $options_raw;
    } elseif (is_string($options_raw) && !empty($options_raw)) {
        // Split by newline or comma
        $options_arr = preg_split('/[\r\n,]+/', $options_raw);
        $options_arr = array_map('trim', $options_arr);
        $options_arr = array_filter($options_arr);
        $options_arr = array_values($options_arr);
    }

    $event_extra_fields[] = array(
        'id'                 => $field['id'] ?? ('field_' . $idx),
        'label'              => $field['label'] ?? '',
        'field_type'         => $field['field_type'] ?? $field['type'] ?? 'text',
        'required'           => !empty($field['required']),
        'show_attendee_form' => true, // Always show event-level extra fields
        'options'            => $options_arr,
        'placeholder'        => $field['placeholder'] ?? $field['default'] ?? '',
    );
}

// Build tickets data for JS
$tickets_js = array();
foreach ($tickets as $t) {
    if (!$t->is_active) continue;
    $tickets_js[] = array(
        'id'            => $t->id,
        'name'          => $t->name,
        'price'         => floatval($t->price),
        'quantity'      => intval($t->quantity),
        'sold'          => intval($t->sold),
        'min_per_order' => intval($t->min_per_order ?? 1),
        'max_per_order' => intval($t->max_per_order ?? 10),
        'description'   => $t->description ?? '',
    );
}
?>

<!-- Checkout Modal -->
<div class="modal fade sc-checkout-modal" id="checkout-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header">
                <div class="sc-checkout-header-inner">
                    <i class="fa-solid fa-ticket"></i>
                    <h5 class="modal-title" id="checkout-modal-title"><?php esc_html_e('Complete Your Registration', 'sc_events'); ?></h5>
                </div>
                <button type="button" class="sc-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="checkout-form" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" id="checkout-event-id" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                    <input type="hidden" id="checkout-ticket-id" name="ticket_id">
                    <input type="hidden" id="applied-coupon" name="coupon_code">
                    <input type="hidden" id="checkout-mode" value="paid">

                    <!-- Extra Fields Section -->
                    <div id="checkout-extra-fields-container" class="sc-checkout-extra-fields"></div>

                    <!-- Ticket Summary -->
                    <div class="sc-checkout-summary">
                        <div class="sc-checkout-summary-row">
                            <span class="sc-checkout-label"><i class="fa-solid fa-ticket"></i> <?php esc_html_e('Ticket', 'sc_events'); ?></span>
                            <span id="checkout-ticket-name" class="sc-checkout-value"></span>
                        </div>
                        <div class="sc-checkout-summary-row">
                            <span class="sc-checkout-label"><?php esc_html_e('Price', 'sc_events'); ?></span>
                            <span id="checkout-ticket-price" class="sc-checkout-value" data-price="0"></span>
                        </div>
                    </div>

                    <!-- Quantity Selector -->
                    <div class="sc-checkout-qty-section" id="checkout-qty-section">
                        <label class="sc-checkout-field-label"><?php esc_html_e('Quantity', 'sc_events'); ?></label>
                        <div class="sc-checkout-qty">
                            <button type="button" class="sc-qty-btn qty-btn-minus" aria-label="Decrease">
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <input type="number" class="sc-qty-input qty-input" name="quantity" value="1" min="1" max="10" readonly>
                            <button type="button" class="sc-qty-btn qty-btn-plus" aria-label="Increase">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Coupon Section -->
                    <div class="sc-checkout-coupon-section" id="checkout-coupon-section">
                        <label class="sc-checkout-field-label">
                            <i class="fa-solid fa-tag"></i> <?php esc_html_e('Have a coupon?', 'sc_events'); ?>
                        </label>
                        <div class="sc-checkout-coupon-input">
                            <input type="text" class="sc-input" id="modal-coupon-code" placeholder="<?php esc_attr_e('Enter coupon code', 'sc_events'); ?>">
                            <button type="button" class="sc-btn sc-btn-outline sc-btn-sm btn-apply-modal-coupon">
                                <?php esc_html_e('Apply', 'sc_events'); ?>
                            </button>
                        </div>
                        <div id="coupon-result" class="sc-checkout-coupon-result" style="display: none;"></div>
                    </div>

                    <!-- Total -->
                    <div class="sc-checkout-total">
                        <div class="sc-checkout-total-row" id="checkout-discount-row" style="display: none;">
                            <span><?php esc_html_e('Discount', 'sc_events'); ?></span>
                            <span id="checkout-discount" class="sc-checkout-discount">-0</span>
                        </div>
                        <div class="sc-checkout-total-row sc-checkout-total-final">
                            <span><?php esc_html_e('Total', 'sc_events'); ?></span>
                            <span id="final-price" class="sc-checkout-total-amount">0</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="sc-btn sc-btn-ghost" data-bs-dismiss="modal">
                        <?php esc_html_e('Cancel', 'sc_events'); ?>
                    </button>
                    <button type="submit" class="sc-btn sc-btn-gold" id="checkout-submit-btn">
                        <span id="checkout-btn-text"><?php esc_html_e('Proceed to Payment', 'sc_events'); ?></span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>

            <!-- Secure Badge -->
            <div class="sc-checkout-secure">
                <i class="fa-solid fa-shield-halved"></i>
                <?php esc_html_e('Secure checkout', 'sc_events'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Extra Fields Modal (for free tickets without coupon) -->
<div class="modal fade sc-checkout-modal" id="extraFieldsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sc-checkout-header-inner">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <h5 class="modal-title"><?php esc_html_e('Complete Your Details', 'sc_events'); ?></h5>
                </div>
                <button type="button" class="sc-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="extra-fields-form">
                <div class="modal-body">
                    <div id="extra-fields-container" class="sc-checkout-extra-fields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn sc-btn-ghost" data-bs-dismiss="modal">
                        <?php esc_html_e('Cancel', 'sc_events'); ?>
                    </button>
                    <button type="submit" class="sc-btn sc-btn-gold" id="extra-fields-submit-btn">
                        <span><?php esc_html_e('Register Now', 'sc_events'); ?></span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </form>
            <div class="sc-checkout-secure">
                <i class="fa-solid fa-shield-halved"></i>
                <?php esc_html_e('Secure registration', 'sc_events'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Coupon Registration Modal (for tickets requiring coupon/serial) -->
<div class="modal fade sc-checkout-modal" id="couponRegisterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="sc-checkout-header-inner">
                    <i class="fa-solid fa-key"></i>
                    <h5 class="modal-title"><?php esc_html_e('Register with Coupon', 'sc_events'); ?></h5>
                </div>
                <button type="button" class="sc-modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="coupon-register-form">
                <div class="modal-body">
                    <input type="hidden" id="coupon-reg-event-id" name="event_id">
                    <input type="hidden" id="coupon-reg-ticket-id" name="ticket_id">

                    <!-- Extra Fields (dynamic) -->
                    <div id="coupon-reg-extra-fields" class="sc-checkout-extra-fields"></div>

                    <!-- Coupon / Serial Number Input -->
                    <div class="sc-checkout-coupon-section" style="display: block;">
                        <label class="sc-checkout-field-label">
                            <i class="fa-solid fa-key"></i> <?php esc_html_e('Coupon / Serial Number', 'sc_events'); ?>
                            <span class="required-star">*</span>
                        </label>
                        <div class="sc-checkout-coupon-input">
                            <input type="text" class="sc-input" id="coupon-reg-code" name="coupon_code" placeholder="<?php esc_attr_e('Enter your coupon or serial number', 'sc_events'); ?>" required>
                            <button type="button" class="sc-btn sc-btn-outline sc-btn-sm btn-validate-coupon-reg">
                                <?php esc_html_e('Verify', 'sc_events'); ?>
                            </button>
                        </div>
                        <div id="coupon-reg-result" class="sc-checkout-coupon-result" style="display: none;"></div>
                    </div>

                    <!-- Ticket Info (shown after coupon validated) -->
                    <div id="coupon-reg-ticket-info" class="sc-checkout-summary" style="display: none;">
                        <div class="sc-checkout-summary-row">
                            <span class="sc-checkout-label"><i class="fa-solid fa-ticket"></i> <?php esc_html_e('Ticket', 'sc_events'); ?></span>
                            <span id="coupon-reg-ticket-name" class="sc-checkout-value"></span>
                        </div>
                        <div class="sc-checkout-summary-row">
                            <span class="sc-checkout-label"><?php esc_html_e('Status', 'sc_events'); ?></span>
                            <span class="sc-checkout-value" style="color: var(--sc-success, #22c55e);">
                                <i class="fa-solid fa-circle-check"></i> <?php esc_html_e('Coupon Verified', 'sc_events'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn sc-btn-ghost" data-bs-dismiss="modal">
                        <?php esc_html_e('Cancel', 'sc_events'); ?>
                    </button>
                    <button type="submit" class="sc-btn sc-btn-gold" id="coupon-reg-submit-btn" disabled>
                        <span><?php esc_html_e('Register with Coupon', 'sc_events'); ?></span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </form>
            <div class="sc-checkout-secure">
                <i class="fa-solid fa-shield-halved"></i>
                <?php esc_html_e('Secure registration', 'sc_events'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Event Data (Hidden JSON) -->
<script type="application/json" id="event-extra-fields-data">
<?php echo wp_json_encode($event_extra_fields); ?>
</script>

<script type="application/json" id="event-tickets-data">
<?php echo wp_json_encode($tickets_js); ?>
</script>

<script type="application/json" id="event-data">
<?php echo wp_json_encode(array(
    'event_id' => $event_id,
    'is_registered' => $is_registered
)); ?>
</script>

<!-- Event Single Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/single-event.css">

<!-- Swiper CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<!-- Image Slider Styles -->
<style>
.sc-image-slider-wrapper .swiper { padding-bottom: 40px; }
.sc-image-slider-wrapper .swiper-slide { border-radius: 12px; overflow: hidden; }
.sc-image-slider-wrapper .swiper-slide img { width: 100%; height: 220px; object-fit: cover; cursor: pointer; transition: transform 0.3s; }
.sc-image-slider-wrapper .swiper-slide img:hover { transform: scale(1.03); }
.sc-image-slider-wrapper .swiper-button-next,
.sc-image-slider-wrapper .swiper-button-prev { color: #fff; background: rgba(0,0,0,0.5); width: 36px; height: 36px; border-radius: 50%; }
.sc-image-slider-wrapper .swiper-button-next::after,
.sc-image-slider-wrapper .swiper-button-prev::after { font-size: 16px; }
.sc-image-slider-wrapper .swiper-pagination-bullet-active { background: var(--sc-primary, #3b82f6); }
</style>

<?php /* Fancybox is gone: no page renders a [data-fancybox] link any more, and
         single-event.js checks for it before binding. */ ?>

<!-- Event Single Page JavaScript -->
<script src="<?php echo esc_url($assets_url); ?>js/single-event.js"></script>

<!-- Initialize Image Sliders -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sc-image-slider-wrapper .swiper').forEach(function(el) {
        new Swiper(el, {
            slidesPerView: 2,
            spaceBetween: 16,
            loop: true,
            autoplay: { delay: 3000, disableOnInteraction: false },
            pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
            navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
            breakpoints: { 640: { slidesPerView: 3 }, 1024: { slidesPerView: 4 }, 1280: { slidesPerView: 5 } }
        });
    });
});
</script>
