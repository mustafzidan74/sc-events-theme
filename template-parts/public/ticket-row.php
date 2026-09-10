<?php
/**
 * One row in a ticket panel.
 *
 * The event page and the workshop page had a copy each — the same fifty lines
 * twice, differing only in a data attribute and the wording of the button.
 * These rows carry the classes and data attributes the checkout script binds
 * to, so two copies meant two chances for the purchase path to drift apart
 * silently. There is one now.
 *
 * Args:
 *   ticket       object  Row from SC_Ticket.
 *   event_id     int     Event the ticket belongs to.
 *   workshop_id  int     Workshop id, or 0 on an event page.
 *   currency     string  Currency symbol.
 *   buy_label    string  Wording for the paid button.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$ticket = $args['ticket'] ?? null;

if (!$ticket || empty($ticket->is_active) || empty($ticket->name)) {
    return;
}

$event_id    = (int) ($args['event_id'] ?? 0);
$workshop_id = (int) ($args['workshop_id'] ?? 0);
$currency    = $args['currency'] ?? sc_t('general.currency_symbol', 'EGP');
$buy_label   = $args['buy_label'] ?? sc_t('frontend.get_ticket', 'Get ticket');

$price   = (float) $ticket->price;
$cap     = (int) $ticket->quantity;
$sold    = (int) $ticket->sold;
$left    = $cap > 0 ? max(0, $cap - $sold) : -1;
$soldout = $cap > 0 && $left === 0;
$coupons = !empty($ticket->enable_coupons);
$taken   = $cap > 0 ? min(100, ($sold / $cap) * 100) : 0;

// Emitted on every button so the checkout script finds what it expects.
$hooks = '';
if ($workshop_id) {
    $hooks .= ' data-workshop-id="' . esc_attr($workshop_id) . '"';
}
$hooks .= ' data-event-id="' . esc_attr($event_id) . '"';
$hooks .= ' data-ticket-id="' . esc_attr($ticket->id) . '"';
$hooks .= ' data-ticket-name="' . esc_attr($ticket->name) . '"';
?>
<div class="w-tk<?php echo $soldout ? ' w-tk--gone' : ''; ?>">
    <div class="w-tk__text">
        <span class="w-tk__name"><?php echo esc_html($ticket->name); ?></span>
        <?php if (!empty($ticket->description)): ?>
            <p class="w-tk__desc"><?php echo esc_html($ticket->description); ?></p>
        <?php endif; ?>
    </div>

    <?php // A price of 0 means the organiser has not set one, so nothing is shown. ?>
    <?php if ($price > 0): ?>
    <span class="w-tk__price">
        <?php echo esc_html(number_format_i18n($price) . ' ' . $currency); ?>
    </span>
    <?php endif; ?>

    <div class="w-tk__act">
        <?php if ($soldout): ?>
            <span class="w-btn w-btn--outline" aria-disabled="true">
                <?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?>
            </span>
        <?php elseif ($price == 0 && $coupons): ?>
            <button type="button" class="w-btn btn-register-coupon"<?php echo $hooks; ?>>
                <?php echo esc_html(sc_t('frontend.register_with_coupon', 'Register with coupon')); ?>
            </button>
        <?php elseif ($price == 0): ?>
            <button type="button" class="w-btn btn-register-free"<?php echo $hooks; ?>>
                <?php echo esc_html(sc_t('frontend.register', 'Register')); ?>
            </button>
        <?php else: ?>
            <button type="button" class="w-btn btn-buy-ticket"<?php echo $hooks; ?>
                    data-ticket-price="<?php echo esc_attr($price); ?>"
                    data-min-qty="<?php echo esc_attr($ticket->min_per_order ?? 1); ?>"
                    data-max-qty="<?php echo esc_attr($ticket->max_per_order ?? 10); ?>">
                <?php echo esc_html($buy_label); ?>
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
