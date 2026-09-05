<?php
/**
 * Event Banner - Dark & Premium Immersive Hero
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$event = $args['event'] ?? null;
$event_logo = $args['event_logo'] ?? '';
$event_banner = $args['event_banner'] ?? '';
$start_date = $args['start_date'] ?? '';
$end_date = $args['end_date'] ?? '';
$start_time = $args['start_time'] ?? '';
$end_time = $args['end_time'] ?? '';
$location = $args['location'] ?? '';
$location_type = $args['location_type'] ?? '';
$google_maps_url = $args['google_maps_url'] ?? '';
$is_free = $args['is_free'] ?? true;
$min_price = $args['min_price'] ?? 0;
$max_price = $args['max_price'] ?? 0;
$is_past = $args['is_past'] ?? false;
$is_registered = $args['is_registered'] ?? false;

$social_links = $args['social_links'] ?? array();
$tickets = $args['tickets'] ?? array();
$has_tickets = $args['has_tickets'] ?? false;

if (!$event) return;

$banner_url = $event_banner ? wp_get_attachment_image_url($event_banner, 'full') : '';
$event_datetime = strtotime($start_date . ' ' . ($start_time ?: '00:00:00'));
$current_time = current_time('timestamp');
$show_countdown = ($event_datetime > $current_time);
?>

<section class="sc-event-hero" <?php if ($banner_url): ?>style="background-image: url('<?php echo esc_url($banner_url); ?>');"<?php endif; ?>>
    <div class="sc-event-hero-overlay"></div>

    <div class="container sc-relative">
        <div class="row align-items-end">
            <!-- Left: Logo + Title + Dates + Social -->
            <div class="col-lg-7 mb-4 mb-lg-0">
                <?php if ($event_logo): ?>
                <div class="sc-event-logo" data-aos="fade-right" data-aos-duration="800">
                    <img src="<?php echo esc_url($event_logo); ?>" alt="<?php echo esc_attr($event->title); ?>">
                </div>
                <?php endif; ?>

                <div data-aos="fade-right" data-aos-duration="900">
                    <h1 class="sc-event-hero-title"><?php echo esc_html($event->title); ?></h1>

                    <!-- Date & Location Pills -->
                    <div class="sc-event-pills">
                        <span class="sc-event-pill">
                            <i class="fa-solid fa-calendar-days"></i>
                            <?php
                            echo esc_html(date_i18n('F d, Y', strtotime($start_date)));
                            if ($end_date && $end_date !== $start_date) {
                                echo ' - ' . esc_html(date_i18n('F d, Y', strtotime($end_date)));
                            }
                            ?>
                        </span>
                        <?php if ($start_time): ?>
                        <span class="sc-event-pill">
                            <i class="fa-regular fa-clock"></i>
                            <?php
                            echo esc_html(date('g:i A', strtotime($start_time)));
                            if ($end_time) echo ' - ' . esc_html(date('g:i A', strtotime($end_time)));
                            ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($location || $location_type === 'online'): ?>
                        <?php if (!empty($google_maps_url) && $location_type !== 'online'): ?>
                        <a href="<?php echo esc_url($google_maps_url); ?>" target="_blank" rel="noopener" class="sc-event-pill sc-event-pill-map" style="text-decoration:none; cursor:pointer;" title="<?php echo esc_attr(sc_t('frontend.open_in_maps', 'Open in Google Maps')); ?>">
                            <i class="fa-solid fa-location-dot sc-pill-pin-pulse"></i>
                            <?php echo esc_html($location); ?>
                            <i class="fa-solid fa-up-right-from-square sc-pill-map-arrow"></i>
                        </a>
                        <?php else: ?>
                        <span class="sc-event-pill">
                            <i class="fa-solid fa-location-dot"></i>
                            <?php echo esc_html($location ?: sc_t('frontend.online_event', 'Online Event')); ?>
                        </span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Countdown -->
                    <?php if ($show_countdown): ?>
                    <div class="sc-countdown" data-event-date="<?php echo esc_attr(date('Y-m-d H:i:s', $event_datetime)); ?>" style="margin-top: var(--sc-space-6);">
                        <div class="sc-countdown-item">
                            <span class="sc-countdown-value" id="days">00</span>
                            <span class="sc-countdown-label"><?php echo esc_html(sc_t('frontend.days', 'Days')); ?></span>
                        </div>
                        <div class="sc-countdown-item">
                            <span class="sc-countdown-value" id="hours">00</span>
                            <span class="sc-countdown-label"><?php echo esc_html(sc_t('frontend.hours', 'Hours')); ?></span>
                        </div>
                        <div class="sc-countdown-item">
                            <span class="sc-countdown-value" id="minutes">00</span>
                            <span class="sc-countdown-label"><?php echo esc_html(sc_t('frontend.minutes', 'Minutes')); ?></span>
                        </div>
                        <div class="sc-countdown-item">
                            <span class="sc-countdown-value" id="seconds">00</span>
                            <span class="sc-countdown-label"><?php echo esc_html(sc_t('frontend.seconds', 'Seconds')); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Social Links + Favorite Button -->
                    <div class="sc-social-links sc-event-social-row" style="margin-top: var(--sc-space-5);">
                        <?php if (!empty($social_links) && is_array($social_links)):
                            $icon_map = array(
                                'facebook'  => 'fa-brands fa-facebook-f',
                                'twitter'   => 'fa-brands fa-twitter',
                                'instagram' => 'fa-brands fa-instagram',
                                'linkedin'  => 'fa-brands fa-linkedin-in',
                                'whatsapp'  => 'fa-brands fa-whatsapp',
                                'telegram'  => 'fa-brands fa-telegram',
                                'youtube'   => 'fa-brands fa-youtube',
                                'tiktok'    => 'fa-brands fa-tiktok',
                                'snapchat'  => 'fa-brands fa-snapchat',
                                'pinterest' => 'fa-brands fa-pinterest',
                                'github'    => 'fa-brands fa-github',
                                'globe'     => 'fa-solid fa-globe',
                                'link'      => 'fa-solid fa-link',
                                'envelope'  => 'fa-solid fa-envelope',
                                'phone'     => 'fa-solid fa-phone',
                            );
                            foreach ($social_links as $social):
                                $icon_key = strtolower($social['icon'] ?? '');
                                $url = $social['url'] ?? '';
                                if (empty($url)) continue;
                                $fa_class = 'fa-solid fa-link';
                                foreach ($icon_map as $key => $class) {
                                    if (strpos($icon_key, $key) !== false) {
                                        $fa_class = $class;
                                        break;
                                    }
                                }
                            ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="sc-social-link"><i class="<?php echo esc_attr($fa_class); ?>"></i></a>
                        <?php endforeach; endif; ?>

                        <!-- Favorite Button (inline with social) -->
                        <button class="sc-favorite-btn sc-favorite-btn-inline" data-event-id="<?php echo esc_attr($event->id); ?>" title="<?php echo esc_attr(sc_t('frontend.add_to_favorites', 'Add to favorites')); ?>">
                            <i class="fa-regular fa-heart"></i>
                            <i class="fa-solid fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Quick Info + Status -->
            <div class="col-lg-5" data-aos="fade-left" data-aos-duration="1000">
                <div class="sc-event-quick-info glass-card-static">
                    <?php if ($has_tickets): ?>
                    <!-- Tickets List -->
                    <div class="sc-quick-tickets">
                        <div class="sc-quick-tickets-header">
                            <i class="fa-solid fa-ticket"></i>
                            <span><?php echo esc_html(sc_t('frontend.tickets', 'Tickets')); ?></span>
                        </div>
                        <?php foreach ($tickets as $ticket):
                            $ticket_price = floatval($ticket->price ?? 0);
                            $ticket_qty = intval($ticket->quantity ?? 0);
                            $ticket_sold = intval($ticket->sold ?? 0);
                            $ticket_remaining = $ticket_qty > 0 ? $ticket_qty - $ticket_sold : 0;
                            $is_sold_out = $ticket_qty > 0 && $ticket_remaining <= 0;
                        ?>
                        <div class="sc-quick-ticket-row <?php echo $is_sold_out ? 'sold-out' : ''; ?>">
                            <div class="sc-quick-ticket-info">
                                <strong><?php echo esc_html($ticket->name); ?></strong>
                                <?php if ($ticket_qty > 0): ?>
                                <small><?php echo $is_sold_out ? esc_html(sc_t('frontend.sold_out', 'Sold Out')) : sprintf(esc_html(sc_t('frontend.d_remaining', '%d remaining')), $ticket_remaining); ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="sc-quick-ticket-price">
                                <?php echo $ticket_price > 0 ? esc_html(number_format($ticket_price)) . ' ' . esc_html(sc_t('general.currency_symbol', 'EGP')) : esc_html(sc_t('frontend.free', 'Free')); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <?php if ($is_past): ?>
                    <div class="sc-event-status-alert sc-alert-ended">
                        <i class="fa-solid fa-circle-info"></i>
                        <?php echo esc_html(sc_t('frontend.event_ended', 'This event has ended')); ?>
                    </div>
                    <?php elseif ($is_registered): ?>
                    <div class="sc-event-status-alert sc-alert-registered">
                        <i class="fa-solid fa-circle-check"></i>
                        <?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered!')); ?>
                        <a href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php echo esc_html(sc_t('frontend.view_tickets', 'View Tickets')); ?></a>
                    </div>
                    <?php elseif (!$is_past && $has_tickets): ?>
                    <a href="#tickets" class="sc-btn sc-btn-gold" style="width: 100%; margin-top: var(--sc-space-4);">
                        <?php echo esc_html(sc_t('frontend.register_now', 'Register Now')); ?> <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll indicator -->
    <div class="sc-scroll-indicator">
        <i class="fa-solid fa-chevron-down"></i>
    </div>
</section>

<style>
/* Favorite button inline with social icons */
.sc-event-social-row { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.sc-favorite-btn-inline {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
    color: #fff; cursor: pointer; transition: all 0.25s ease;
    padding: 0; position: relative;
}
.sc-favorite-btn-inline .fa-solid { display: none; }
.sc-favorite-btn-inline.is-favorite { background: #e91e63; border-color: #e91e63; }
.sc-favorite-btn-inline.is-favorite .fa-regular { display: none; }
.sc-favorite-btn-inline.is-favorite .fa-solid { display: inline-block; color: #fff; }
.sc-favorite-btn-inline:hover { transform: scale(1.08); background: rgba(233,30,99,0.2); border-color: #e91e63; color: #e91e63; }
.sc-favorite-btn-inline.is-favorite:hover { background: #c2185b; color: #fff; }

/* Clickable map pill — pin pulse + arrow indicator */
.sc-event-pill-map {
    position: relative;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}
.sc-event-pill-map .sc-pill-pin-pulse {
    color: #ff5252;
    animation: sc-pin-pulse 1.6s ease-in-out infinite;
    transform-origin: center bottom;
}
.sc-event-pill-map .sc-pill-map-arrow {
    margin-inline-start: 8px;
    font-size: 0.78em;
    opacity: 0.75;
    transition: transform 0.25s ease, opacity 0.25s ease;
}
.sc-event-pill-map:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 82, 82, 0.35);
}
.sc-event-pill-map:hover .sc-pill-map-arrow {
    transform: translate(2px, -2px);
    opacity: 1;
}
.sc-event-pill-map:hover .sc-pill-pin-pulse {
    animation-duration: 0.8s;
}

@keyframes sc-pin-pulse {
    0%, 100% {
        transform: scale(1) translateY(0);
    }
    50% {
        transform: scale(1.18) translateY(-3px);
    }
}

/* Subtle ripple ring around the pin */
.sc-event-pill-map .sc-pill-pin-pulse::after {
    content: '';
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(255, 82, 82, 0.4);
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    animation: sc-pin-ripple 1.6s ease-out infinite;
    pointer-events: none;
    z-index: -1;
}

@keyframes sc-pin-ripple {
    0% {
        opacity: 0.6;
        transform: translate(-50%, -50%) scale(0.6);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(2);
    }
}
</style>
