<?php
/**
 * Event hero.
 *
 * States the date first and large: for a congress the "when" is what people
 * came to check. Everything else on the page is one tile away.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event         = $args['event'] ?? null;
$event_banner  = $args['event_banner'] ?? '';
$event_logo    = $args['event_logo'] ?? '';
$start_date    = $args['start_date'] ?? '';
$end_date      = $args['end_date'] ?? '';
$start_time    = $args['start_time'] ?? '';
$end_time      = $args['end_time'] ?? '';
$location      = $args['location'] ?? '';
$location_type = $args['location_type'] ?? '';
$maps_url      = $args['google_maps_url'] ?? '';
$is_free       = $args['is_free'] ?? true;
$min_price     = $args['min_price'] ?? 0;
$is_past       = $args['is_past'] ?? false;
$is_registered = $args['is_registered'] ?? false;
$has_tickets   = $args['has_tickets'] ?? false;
$social_links  = $args['social_links'] ?? [];
$session_count = (int) ($args['session_count'] ?? 0);
$speaker_count = (int) ($args['speaker_count'] ?? 0);
$cme_hours     = (int) ($args['cme_hours'] ?? 0);

if (!$event) return;

// The hero is full width: WordPress's srcset lets a phone take a phone-sized copy.
$banner_img = sc_img($event_banner ?: $event_logo, 'full', '100vw', array(
    'class'         => 'w-ev__bg',
    'loading'       => false,
    'fetchpriority' => 'high',
));
$banner_url = $banner_img !== '';

$big_date = sc_date_range($start_date, $end_date);

$start_ts  = strtotime($start_date . ' ' . ($start_time ?: '00:00:00'));
$days_left = (int) ceil(($start_ts - current_time('timestamp')) / DAY_IN_SECONDS);

// The venue line reads as one sentence: where, then when in the day.
$where = [];
if ($location_type === 'online') {
    $where[] = sc_t('frontend.online_event', 'Online');
} elseif ($location) {
    $where[] = $location;
}
if ($start_time) {
    $clock = date_i18n('H:i', strtotime($start_time));
    if ($end_time) { $clock .= '–' . date_i18n('H:i', strtotime($end_time)); }
    $where[] = $clock;
}

// 0 means unpriced, not free.
$price_label = $is_free
    ? ''
    : sprintf(
        sc_t('frontend.from_price', 'From %s'),
        sc_currency($min_price)
    );

$tiles = [];
if ($session_count > 0) {
    $tiles[] = [
        'href'  => '#programme',
        'icon'  => 'fa-regular fa-clock',
        'label' => sc_t('frontend.programme', 'Programme'),
        'sub'   => sprintf(sc_t('frontend.n_sessions', '%s sessions'), number_format_i18n($session_count)),
    ];
}
if ($speaker_count > 0) {
    $tiles[] = [
        'href'  => '#speakers',
        'icon'  => 'fa-regular fa-user',
        'label' => sc_t('frontend.speakers', 'Speakers'),
        'sub'   => sprintf(sc_t('frontend.n_faculty', '%s faculty'), number_format_i18n($speaker_count)),
    ];
}
if ($location) {
    $tiles[] = [
        'href'  => '#venue',
        'icon'  => 'fa-solid fa-location-dot',
        'label' => sc_t('frontend.venue', 'Venue'),
        'sub'   => $location,
    ];
}
if ($has_tickets && !$is_past) {
    $tiles[] = [
        'href'  => '#tickets',
        'icon'  => 'fa-solid fa-ticket',
        'label' => sc_t('frontend.tickets', 'Tickets'),
        'sub'   => $price_label ?: sc_t('frontend.book_now', 'Book your place'),
        'buy'   => true,
    ];
}
?>

<div class="w-ev__head">
    <nav class="w-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'sc_events'); ?>">
        <a href="<?php echo esc_url(home_url('/events/')); ?>"><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></a>
        <span aria-hidden="true">/</span>
        <span><?php echo esc_html($event->title); ?></span>
    </nav>

    <section class="w-ev__hero">
        <?php if ($banner_url): ?>
            <?php echo $banner_img; // Built by wp_get_attachment_image(). ?>
        <?php endif; ?>

        <div class="w-ev__badges">
            <?php if ($is_past): ?>
                <span class="w-ev__badge"><?php echo esc_html(sc_t('frontend.event_ended', 'This event has ended')); ?></span>
            <?php elseif ($is_registered): ?>
                <span class="w-ev__badge">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.you_are_registered', 'You are registered')); ?>
                </span>
            <?php elseif ($has_tickets): ?>
                <span class="w-ev__badge">
                    <span class="w-ev__pulse" aria-hidden="true"></span>
                    <?php echo esc_html(sc_t('frontend.registration_open', 'Registration open')); ?>
                </span>
            <?php endif; ?>

            <?php if ($cme_hours > 0): ?>
                <span class="w-ev__badge"><?php printf(
                    esc_html(sc_t('frontend.n_cme_hours', '%s CME hours')),
                    esc_html(number_format_i18n($cme_hours))
                ); ?></span>
            <?php endif; ?>

            <?php if (!$is_past && $days_left > 0): ?>
                <span class="w-ev__badge"><?php printf(
                    esc_html(sc_t('frontend.n_days_left', '%s days left')),
                    esc_html(number_format_i18n($days_left))
                ); ?></span>
            <?php endif; ?>
        </div>

        <div class="w-ev__foot">
            <div class="w-ev__say">
                <span class="w-ev__date"><?php echo esc_html($big_date); ?></span>
                <h1 class="w-ev__title"><?php echo esc_html($event->title); ?></h1>
                <?php if ($where): ?>
                    <p class="w-ev__where"><?php echo esc_html(implode(' · ', $where)); ?></p>
                <?php endif; ?>
            </div>

            <div class="w-ev__acts">
                <?php if ($maps_url && $location_type !== 'online'): ?>
                    <a class="w-ev__act" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer"
                       aria-label="<?php echo esc_attr(sc_t('frontend.open_in_maps', 'Open in Maps')); ?>">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <button class="w-ev__act sc-favorite-btn" type="button"
                        data-event-id="<?php echo esc_attr($event->id); ?>"
                        aria-label="<?php echo esc_attr(sc_t('frontend.add_to_favorites', 'Add to favorites')); ?>">
                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                    <i class="fa-solid fa-heart" aria-hidden="true"></i>
                </button>

                <button class="w-ev__act" type="button" data-share
                        data-title="<?php echo esc_attr($event->title); ?>"
                        aria-label="<?php echo esc_attr(sc_t('frontend.share', 'Share')); ?>">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </section>

    <?php if ($tiles): ?>
    <div class="w-ev__tiles">
        <?php foreach ($tiles as $tile): ?>
        <a class="w-ev__tile<?php echo !empty($tile['buy']) ? ' w-ev__tile--buy' : ''; ?>" href="<?php echo esc_attr($tile['href']); ?>">
            <span class="w-ev__tile-icon" aria-hidden="true"><i class="<?php echo esc_attr($tile['icon']); ?>"></i></span>
            <span class="w-ev__tile-text">
                <span class="w-ev__tile-label"><?php echo esc_html($tile['label']); ?></span>
                <span class="w-ev__tile-sub"><?php echo esc_html($tile['sub']); ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($social_links) && is_array($social_links)): ?>
    <div class="w-ev__socials">
        <?php
        $icon_map = [
            'facebook' => 'fa-brands fa-facebook-f', 'twitter' => 'fa-brands fa-twitter',
            'instagram' => 'fa-brands fa-instagram', 'linkedin' => 'fa-brands fa-linkedin-in',
            'whatsapp' => 'fa-brands fa-whatsapp',   'telegram' => 'fa-brands fa-telegram',
            'youtube' => 'fa-brands fa-youtube',     'tiktok' => 'fa-brands fa-tiktok',
            'globe' => 'fa-solid fa-globe',          'envelope' => 'fa-solid fa-envelope',
            'phone' => 'fa-solid fa-phone',
        ];
        foreach ($social_links as $social):
            $url = $social['url'] ?? '';
            if (!$url) { continue; }
            $key = strtolower($social['icon'] ?? '');
            $fa  = 'fa-solid fa-link';
            foreach ($icon_map as $needle => $class) {
                if (str_contains($key, $needle)) { $fa = $class; break; }
            }
        ?>
        <a class="w-ev__social" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
            <i class="<?php echo esc_attr($fa); ?>" aria-hidden="true"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
