<?php
/**
 * Venue.
 *
 * One place, not a gallery of rooms: the halls are listed as chips under the
 * address, because knowing which halls run matters more to someone planning
 * the day than a photo of each room.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event       = $args['event'] ?? null;
$event_halls = $args['event_halls'] ?? [];
$location    = $args['location'] ?? '';
$maps_url    = $args['google_maps_url'] ?? '';

if (!$event) return;

$name = $event->venue_name ?: $location;
if (!$name && !$event_halls) return;

$address = trim(implode(', ', array_filter([
    $event->venue_address ?? '',
    $event->venue_city ?? '',
    $event->venue_country ?? '',
])));

// The address field often just repeats the venue name; saying it twice
// reads as a mistake.
if ($address !== '' && $name !== '' && str_starts_with(mb_strtolower($address), mb_strtolower($name))) {
    $address = trim(mb_substr($address, mb_strlen($name)), " ,");
}

$photo = !empty($event->venue_image) ? sc_img($event->venue_image, 'large', '(max-width: 767px) calc(100vw - 40px), 560px', array('alt' => $name)) : '';
?>

<section class="w-ev__section" id="venue">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.venue', 'Venue')); ?></h2>

    <div class="w-venue<?php echo $photo ? '' : ' w-venue--bare'; ?>">
        <div class="w-venue__say">
            <?php if ($name): ?>
                <h3 class="w-venue__name"><?php echo esc_html($name); ?></h3>
            <?php endif; ?>

            <?php if ($address): ?>
                <p class="w-ev__lead" style="font-size:1rem;margin:0"><?php echo esc_html($address); ?></p>
            <?php endif; ?>

            <?php if ($event_halls): ?>
            <div class="w-venue__halls">
                <?php foreach ($event_halls as $hall): ?>
                <span class="w-venue__hall">
                    <?php echo esc_html($hall->name); ?>
                    <?php if (!empty($hall->capacity)): ?>
                        · <?php printf(esc_html(sc_t('frontend.d_seats', '%s seats')), esc_html(number_format_i18n($hall->capacity))); ?>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($maps_url): ?>
            <a class="w-btn w-btn--outline" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <?php echo esc_html(sc_t('frontend.open_in_maps', 'Open in Maps')); ?>
            </a>
            <?php endif; ?>
        </div>

        <?php if ($photo): ?>
        <div class="w-venue__pic">
            <?php echo $photo; // Built by wp_get_attachment_image(). ?>
        </div>
        <?php endif; ?>
    </div>
</section>
