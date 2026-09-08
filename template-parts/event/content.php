<?php
/**
 * About the event, and the ticket panel.
 *
 * The date, time and venue are already stated in the hero, so this half does
 * not repeat them — it explains what the event is, then sells it.
 *
 * Every button here keeps the class and data attributes the checkout script
 * binds to (btn-buy-ticket, btn-register-free, btn-register-coupon,
 * btn-apply-coupon and #coupon-form-wrapper). Only the surface changed.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event         = $args['event'] ?? null;
$event_id      = $args['event_id'] ?? 0;
$tickets       = $args['tickets'] ?? [];
$has_tickets   = $args['has_tickets'] ?? false;
$is_past       = $args['is_past'] ?? false;
$is_registered = $args['is_registered'] ?? false;

if (!$event) return;

$currency = sc_t('general.currency_symbol', 'EGP');

// Name the gateways that are actually switched on rather than promising
// methods this install cannot take.
$gateway_names = [
    'paymob'     => sc_t('frontend.gateway_paymob', 'Cards & wallets'),
    'stripe'     => sc_t('frontend.gateway_stripe', 'Cards'),
    'kashier'    => sc_t('frontend.gateway_kashier', 'Kashier'),
    'myfatoorah' => sc_t('frontend.gateway_myfatoorah', 'MyFatoorah'),
];
$live_gateways = [];
foreach ($gateway_names as $key => $label) {
    $settings = get_option('sc_gateway_' . $key);
    if (is_array($settings) && !empty($settings['enabled'])) {
        $live_gateways[] = $label;
    }
}
$live_gateways = array_values(array_unique($live_gateways));

$show_buy = $has_tickets && !$is_past && !$is_registered;
?>

<?php if (!empty($event->description)): ?>
<section class="w-ev__section" id="about">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.about_event', 'About this event')); ?></h2>
    <div class="w-prose w-prose--clamped" data-clamp>
        <?php echo wp_kses_post(wpautop($event->description)); ?>
    </div>
    <button class="w-btn w-btn--outline w-btn--sm" type="button" data-clamp-toggle
            data-more="<?php echo esc_attr(sc_t('frontend.read_more', 'Read more')); ?>"
            data-less="<?php echo esc_attr(sc_t('frontend.read_less', 'Read less')); ?>"
            style="align-self:flex-start">
        <?php echo esc_html(sc_t('frontend.read_more', 'Read more')); ?>
    </button>
</section>
<?php endif; ?>

<?php if ($show_buy): ?>
<section class="w-ev__buy" id="tickets">
    <div class="w-ev__section" style="gap:var(--w-space-3)">
        <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.pick_your_ticket', "Pick your ticket. That's it.")); ?></h2>
        <p class="w-ev__lead" style="font-size:1rem">
            <?php echo esc_html(sc_t('frontend.tickets_lede', 'Your e-badge, agenda and certificate all live in one account.')); ?>
        </p>
    </div>

    <?php if ($live_gateways): ?>
    <p class="w-ev__pay">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <span><?php echo esc_html(implode(' · ', $live_gateways)); ?></span>
    </p>
    <?php endif; ?>

    <div class="w-ev__tickets">
        <?php foreach ($tickets as $ticket):
            if (empty($ticket->is_active) || empty($ticket->name)) { continue; }

            $price   = (float) $ticket->price;
            $cap     = (int) $ticket->quantity;
            $sold    = (int) $ticket->sold;
            $left    = $cap > 0 ? max(0, $cap - $sold) : -1;
            $soldout = $cap > 0 && $left === 0;
            $coupons = !empty($ticket->enable_coupons);
            $taken   = $cap > 0 ? min(100, ($sold / $cap) * 100) : 0;
        ?>
        <div class="w-tk<?php echo $soldout ? ' w-tk--gone' : ''; ?>">
            <div class="w-tk__text">
                <span class="w-tk__name"><?php echo esc_html($ticket->name); ?></span>
                <?php if (!empty($ticket->description)): ?>
                    <p class="w-tk__desc"><?php echo esc_html($ticket->description); ?></p>
                <?php endif; ?>
            </div>

            <span class="w-tk__price<?php echo $price == 0 ? ' w-tk__price--free' : ''; ?>">
                <?php echo $price == 0
                    ? esc_html(sc_t('frontend.free', 'Free'))
                    : esc_html(number_format_i18n($price) . ' ' . $currency); ?>
            </span>

            <div class="w-tk__act">
                <?php if ($soldout): ?>
                    <span class="w-btn w-btn--outline" aria-disabled="true">
                        <?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?>
                    </span>
                <?php elseif ($price == 0 && $coupons): ?>
                    <button type="button" class="w-btn btn-register-coupon"
                            data-event-id="<?php echo esc_attr($event_id); ?>"
                            data-ticket-id="<?php echo esc_attr($ticket->id); ?>"
                            data-ticket-name="<?php echo esc_attr($ticket->name); ?>">
                        <?php echo esc_html(sc_t('frontend.register_with_coupon', 'Register with coupon')); ?>
                    </button>
                <?php elseif ($price == 0): ?>
                    <button type="button" class="w-btn btn-register-free"
                            data-event-id="<?php echo esc_attr($event_id); ?>"
                            data-ticket-id="<?php echo esc_attr($ticket->id); ?>"
                            data-ticket-name="<?php echo esc_attr($ticket->name); ?>">
                        <?php echo esc_html(sc_t('frontend.register_free', 'Register free')); ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="w-btn btn-buy-ticket"
                            data-event-id="<?php echo esc_attr($event_id); ?>"
                            data-ticket-id="<?php echo esc_attr($ticket->id); ?>"
                            data-ticket-name="<?php echo esc_attr($ticket->name); ?>"
                            data-ticket-price="<?php echo esc_attr($price); ?>"
                            data-min-qty="<?php echo esc_attr($ticket->min_per_order ?? 1); ?>"
                            data-max-qty="<?php echo esc_attr($ticket->max_per_order ?? 10); ?>">
                        <?php echo esc_html(sc_t('frontend.get_ticket', 'Get ticket')); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($cap > 0 && !$soldout): ?>
            <div class="w-tk__stock">
                <div class="w-tk__bar" role="presentation"><span style="width:<?php echo esc_attr(round($taken)); ?>%"></span></div>
                <span class="w-tk__left"><?php printf(
                    esc_html(sc_t('frontend.d_available', '%s left')),
                    esc_html(number_format_i18n($left))
                ); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="coupon-form-wrapper" class="w-ev__coupon sc-coupon-area" style="display: none;">
        <span class="w-label"><?php echo esc_html(sc_t('frontend.have_a_coupon', 'Have a coupon?')); ?></span>
        <div class="w-ev__coupon-row">
            <input type="text" id="coupon-code" class="w-input sc-input"
                   placeholder="<?php echo esc_attr(sc_t('frontend.enter_code', 'Enter code')); ?>">
            <button class="w-btn w-btn--outline btn-apply-coupon" data-event-id="<?php echo esc_attr($event_id); ?>">
                <?php echo esc_html(sc_t('frontend.apply', 'Apply')); ?>
            </button>
        </div>
    </div>
</section>
<?php elseif ($is_registered): ?>
<section class="w-ev__buy" id="tickets">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered')); ?></h2>
    <p class="w-ev__lead" style="font-size:1rem">
        <?php echo esc_html(sc_t('frontend.registered_lede', 'Your ticket and e-badge are in your account.')); ?>
    </p>
    <a class="w-btn w-btn--lg" href="<?php echo esc_url(home_url('/my-account/')); ?>" style="align-self:flex-start">
        <?php echo esc_html(sc_t('frontend.view_tickets', 'View my ticket')); ?>
    </a>
</section>
<?php endif; ?>
