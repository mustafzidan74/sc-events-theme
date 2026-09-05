<?php
/**
 * Event Halls - Dark & Premium Cards
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

$event_halls = $args['event_halls'] ?? array();

if (empty($event_halls)) return;
?>

<section class="sc-section" id="halls">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.event_halls', 'Event Halls')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.event_halls_desc', 'Explore our event venues and facilities')); ?></p>
        </div>

        <div class="sc-mobile-scroll-container">
        <div class="row g-4 sc-mobile-scroll-row">
            <?php $delay = 0; foreach ($event_halls as $hall):
                $hall_img = !empty($hall->image) ? wp_get_attachment_image_url($hall->image, 'medium_large') : '';
            ?>
            <div class="col-lg-4 col-md-6 sc-mobile-scroll-item" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="glass-card sc-hall-card">
                    <?php if ($hall_img): ?>
                    <div class="sc-hall-image">
                        <img src="<?php echo esc_url($hall_img); ?>" alt="<?php echo esc_attr($hall->name); ?>">
                        <div class="sc-hall-overlay"></div>
                    </div>
                    <?php endif; ?>
                    <h5 style="color: var(--sc-text-primary); margin-bottom: var(--sc-space-3);"><?php echo esc_html($hall->name); ?></h5>
                    <div class="sc-hall-meta">
                        <?php if (!empty($hall->capacity)): ?>
                        <span><i class="fa-solid fa-users"></i> <?php printf(sc_t('frontend.d_seats', '%d seats'), $hall->capacity); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($hall->location)): ?>
                        <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html($hall->location); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($hall->description)): ?>
                    <p style="font-size: var(--sc-text-sm); color: var(--sc-text-muted); margin-top: var(--sc-space-2); margin-bottom: 0;">
                        <?php echo esc_html(wp_trim_words($hall->description, 20)); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php $delay += 100; endforeach; ?>
        </div>
        </div>
    </div>
</section>
