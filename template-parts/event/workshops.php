<?php
/**
 * The event's hands-on workshops.
 *
 * They were missing from the event page: a congress with six workshops showed
 * them only as ticket names, and an event whose workshop had no ticket of its
 * own showed nothing at all. Each card links to the workshop's own page, where
 * the seat is booked.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event_id = (int) ($args['event_id'] ?? 0);
if (!$event_id) return;

global $wpdb;
$sc_workshops = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_workshops WHERE event_id = %d AND status = 'publish' ORDER BY start_date ASC, start_time ASC",
    $event_id
));

if (!$sc_workshops) return;
?>

<section class="w-ev__section" id="workshops">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.hands_on_workshops', 'Hands-on workshops')); ?></h2>
    <p class="w-ev__lead" style="font-size:1rem">
        <?php echo esc_html(sc_t('frontend.workshops_lede', 'Limited seats, taught at the bench.')); ?>
    </p>

    <div class="w-evws" data-shots>
        <?php foreach ($sc_workshops as $sc_ws):
            $sc_ws_img = !empty($sc_ws->featured_image) ? sc_img($sc_ws->featured_image, 'medium', '72px', array(), 400) : '';
            $sc_cap = (int) $sc_ws->total_capacity;
            $sc_left = $sc_cap > 0 ? max(0, $sc_cap - (int) $sc_ws->total_sold) : -1;
        ?>
        <a class="w-evws__card" href="<?php echo esc_url(home_url('/workshop/' . $sc_ws->slug)); ?>">
            <span class="w-evws__thumb">
                <?php if ($sc_ws_img): ?>
                    <?php echo $sc_ws_img; // Built by wp_get_attachment_image(). ?>
                <?php endif; ?>
            </span>
            <span class="w-evws__body">
                <span class="w-evws__title"><?php echo esc_html($sc_ws->title); ?></span>
                <span class="w-evws__meta">
                    <?php echo esc_html(date_i18n('j M', strtotime($sc_ws->start_date))); ?><?php
                    if (!empty($sc_ws->start_time)) { echo ' · ' . esc_html(date_i18n('g:i A', strtotime($sc_ws->start_time))); }
                    ?>
                </span>
                <span class="w-evws__foot">
                    <?php if ($sc_left > 0): ?>
                        <span class="w-tag w-tag--teal"><?php echo esc_html(sprintf(sc_t('frontend.seats_left', '%s seats left'), number_format_i18n($sc_left))); ?></span>
                    <?php elseif ($sc_left === 0): ?>
                        <span class="w-tag"><?php echo esc_html(sc_t('frontend.sold_out', 'Sold out')); ?></span>
                    <?php endif; ?>
                </span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
