<?php
/**
 * Event Content - Dark & Premium (About + Tickets)
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$event = $args['event'] ?? null;
$event_id = $args['event_id'] ?? 0;
$event_banner = $args['event_banner'] ?? 0;
$tickets = $args['tickets'] ?? array();
$has_tickets = $args['has_tickets'] ?? false;
$is_past = $args['is_past'] ?? false;
$is_registered = $args['is_registered'] ?? false;
$start_date = $args['start_date'] ?? '';
$start_time = $args['start_time'] ?? '';
$end_time = $args['end_time'] ?? '';
$location = $args['location'] ?? '';

if (!$event) return;
?>

<section class="sc-section" id="about">
    <div class="container">
        <?php
        $banner_url = '';
        if ($event_banner) {
            $banner_url = wp_get_attachment_image_url($event_banner, 'large');
        }
        ?>

        <!-- Top row: Banner image + Event Details (side by side) -->
        <?php if ($banner_url || $start_date): ?>
        <div class="row g-4 align-items-stretch" style="margin-bottom: var(--sc-space-8);">
            <?php if ($banner_url): ?>
            <div class="col-lg-7" data-aos="fade-up">
                <div class="sc-event-banner-wrap" style="border-radius: var(--sc-radius-xl); overflow: hidden; height: 100%;">
                    <img src="<?php echo esc_url($banner_url); ?>" alt="<?php echo esc_attr($event->title); ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; min-height: 320px;">
                </div>
            </div>
            <?php endif; ?>

            <div class="<?php echo $banner_url ? 'col-lg-5' : 'col-12'; ?>" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card-static" style="height: 100%;">
                    <h4 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-5);">
                        <i class="fa-solid fa-info-circle sc-text-gold"></i> <?php echo esc_html(sc_t('frontend.event_details', 'Event Details')); ?>
                    </h4>
                    <div class="sc-detail-list">
                        <div class="sc-detail-item">
                            <div class="sc-icon-circle"><i class="fa-regular fa-calendar"></i></div>
                            <div>
                                <span class="sc-detail-label"><?php echo esc_html(sc_t('frontend.date', 'Date')); ?></span>
                                <span class="sc-detail-value"><?php echo esc_html(date_i18n('d M Y', strtotime($start_date))); ?></span>
                            </div>
                        </div>
                        <?php if ($start_time): ?>
                        <div class="sc-detail-item">
                            <div class="sc-icon-circle"><i class="fa-regular fa-clock"></i></div>
                            <div>
                                <span class="sc-detail-label"><?php echo esc_html(sc_t('frontend.time', 'Time')); ?></span>
                                <span class="sc-detail-value">
                                    <?php echo esc_html(date('g:i A', strtotime($start_time)));
                                    if ($end_time) echo ' - ' . esc_html(date('g:i A', strtotime($end_time))); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($location): ?>
                        <div class="sc-detail-item">
                            <div class="sc-icon-circle"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <span class="sc-detail-label"><?php echo esc_html(sc_t('frontend.location', 'Location')); ?></span>
                                <span class="sc-detail-value"><?php echo esc_html($location); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-5">
            <!-- About: full width below -->
            <div class="col-12" data-aos="fade-up">
                <div class="sc-gold-border-left">
                    <h2 style="margin-bottom: var(--sc-space-6);"><?php echo esc_html(sc_t('frontend.about_event', 'About This Event')); ?></h2>
                </div>

                <div class="sc-event-description sc-mobile-truncate" style="color: var(--sc-text-secondary); line-height: var(--sc-leading-relaxed);">
                    <?php echo wp_kses_post($event->description); ?>
                </div>
                <button class="sc-read-more-btn" onclick="this.previousElementSibling.classList.toggle('sc-mobile-truncate');this.textContent=this.previousElementSibling.classList.contains('sc-mobile-truncate')?'<?php echo esc_attr(sc_t('frontend.read_more', 'Read More')); ?>':'<?php echo esc_attr(sc_t('frontend.read_less', 'Read Less')); ?>';">
                    <?php echo esc_html(sc_t('frontend.read_more', 'Read More')); ?>
                </button>
            </div>

            <!-- Tickets: full width below -->
            <div class="col-12" id="tickets" data-aos="fade-up" data-aos-delay="100">
                <?php if ($has_tickets && !$is_past && !$is_registered): ?>
                <div class="sc-tickets-box">
                    <div class="sc-tickets-header">
                        <i class="fa-solid fa-ticket"></i>
                        <h4><?php echo esc_html(sc_t('frontend.get_your_tickets', 'Get Your Tickets')); ?></h4>
                    </div>

                    <?php foreach ($tickets as $ticket):
                        if (!$ticket->is_active) continue;
                        $ticket_name = $ticket->name;
                        $ticket_price = floatval($ticket->price);
                        $ticket_qty = $ticket->quantity > 0 ? $ticket->quantity - $ticket->sold : -1;
                        $ticket_id = $ticket->id;
                        $ticket_description = $ticket->description ?? '';
                        if (empty($ticket_name)) continue;

                        $availability_pct = ($ticket->quantity > 0) ? (($ticket->quantity - $ticket->sold) / $ticket->quantity) * 100 : 100;
                    ?>
                    <div class="sc-ticket-card glass-card">
                        <div class="sc-ticket-top">
                            <h5 class="sc-ticket-name"><?php echo esc_html($ticket_name); ?></h5>
                            <span class="sc-ticket-price <?php echo $ticket_price == 0 ? 'free' : ''; ?>">
                                <?php echo $ticket_price == 0 ? esc_html(sc_t('frontend.free', 'FREE')) : esc_html(number_format($ticket_price)) . ' ' . esc_html(sc_t('general.currency_symbol', 'EGP')); ?>
                            </span>
                        </div>
                        <?php if ($ticket_description): ?>
                        <p class="sc-ticket-desc"><?php echo esc_html($ticket_description); ?></p>
                        <?php endif; ?>

                        <?php if ($ticket->quantity > 0): ?>
                        <div class="sc-ticket-availability">
                            <div class="sc-ticket-bar">
                                <div class="sc-ticket-bar-fill" style="width: <?php echo max(0, 100 - $availability_pct); ?>%;"></div>
                            </div>
                            <span class="sc-ticket-qty">
                                <?php if ($ticket_qty > 0): ?>
                                    <?php printf(sc_t('frontend.d_available', '%d available'), $ticket_qty); ?>
                                <?php else: ?>
                                    <?php echo esc_html(sc_t('frontend.sold_out', 'Sold Out')); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php
                        $enable_coupons = !empty($ticket->enable_coupons);
                        ?>
                        <?php if ($ticket_qty === 0): ?>
                            <span class="sc-btn sc-btn-ghost" style="width: 100%; opacity: 0.5; pointer-events: none; margin-top: var(--sc-space-3);"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold Out')); ?></span>
                        <?php elseif ($ticket_price == 0 && $enable_coupons): ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-register-coupon" style="width: 100%; margin-top: var(--sc-space-3);" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_id); ?>" data-ticket-name="<?php echo esc_attr($ticket_name); ?>">
                                <i class="fa-solid fa-key"></i> <?php echo esc_html(sc_t('frontend.register_with_coupon', 'Register with Coupon')); ?>
                            </button>
                        <?php elseif ($ticket_price == 0): ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-register-free" style="width: 100%; margin-top: var(--sc-space-3);" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_id); ?>" data-ticket-name="<?php echo esc_attr($ticket_name); ?>">
                                <i class="fa-solid fa-check"></i> <?php echo esc_html(sc_t('frontend.register_free', 'Register Free')); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="sc-btn sc-btn-gold btn-buy-ticket" style="width: 100%; margin-top: var(--sc-space-3);" data-event-id="<?php echo esc_attr($event_id); ?>" data-ticket-id="<?php echo esc_attr($ticket_id); ?>" data-ticket-name="<?php echo esc_attr($ticket_name); ?>" data-ticket-price="<?php echo esc_attr($ticket_price); ?>" data-min-qty="<?php echo esc_attr($ticket->min_per_order ?? 1); ?>" data-max-qty="<?php echo esc_attr($ticket->max_per_order ?? 10); ?>">
                                <i class="fa-solid fa-cart-shopping"></i> <?php echo esc_html(sc_t('frontend.buy_now', 'Buy Now')); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Coupon -->
                    <div id="coupon-form-wrapper" class="sc-coupon-area" style="display: none;">
                        <h6 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-2);"><?php echo esc_html(sc_t('frontend.have_a_coupon', 'Have a Coupon?')); ?></h6>
                        <div class="sc-coupon-input">
                            <input type="text" id="coupon-code" class="sc-input" placeholder="<?php echo esc_attr(sc_t('frontend.enter_code', 'Enter code')); ?>">
                            <button class="sc-btn sc-btn-outline sc-btn-sm btn-apply-coupon" data-event-id="<?php echo esc_attr($event_id); ?>">
                                <?php echo esc_html(sc_t('frontend.apply', 'Apply')); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
