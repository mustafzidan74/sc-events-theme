<?php
/**
 * Event Gallery & Additional Sections - Dark & Premium
 *
 * Section types: image_slider (gallery), about, card, image_grid
 *
 * @package sc_events
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

$gallery_images = $args['gallery_images'] ?? array();
$other_sections = $args['other_sections'] ?? array();

if (empty($gallery_images) && empty($other_sections)) return;

/**
 * Convert FA4 icon class to FA6 format
 * Admin stores: "fa-globe" → needs: "fa-solid fa-globe"
 * Admin stores: "fa-facebook" → needs: "fa-brands fa-facebook"
 */
function sc_fix_icon_class($icon) {
    if (empty($icon)) return '';

    // Already has FA6 prefix
    if (strpos($icon, 'fa-solid ') === 0 || strpos($icon, 'fa-brands ') === 0 || strpos($icon, 'fa-regular ') === 0) {
        return $icon;
    }

    // Already has old FA4 "fa " prefix - strip it
    if (strpos($icon, 'fa ') === 0) {
        $icon = substr($icon, 3);
    }

    // Brand icons that need fa-brands
    $brands = array(
        'fa-facebook', 'fa-twitter', 'fa-instagram', 'fa-linkedin', 'fa-youtube',
        'fa-whatsapp', 'fa-telegram', 'fa-tiktok', 'fa-pinterest', 'fa-snapchat',
        'fa-reddit', 'fa-discord', 'fa-slack', 'fa-skype', 'fa-github',
        'fa-dribbble', 'fa-behance', 'fa-vimeo', 'fa-soundcloud', 'fa-spotify',
        'fa-twitch', 'fa-medium', 'fa-apple', 'fa-android', 'fa-windows', 'fa-linux',
    );

    if (in_array($icon, $brands)) {
        return 'fa-brands ' . $icon;
    }

    // FA4 → FA6 name changes
    $renames = array(
        'fa-clock-o'       => 'fa-clock',
        'fa-lightbulb-o'   => 'fa-lightbulb',
        'fa-money'         => 'fa-money-bill',
        'fa-video-camera'  => 'fa-video',
        'fa-handshake-o'   => 'fa-handshake',
        'fa-cutlery'       => 'fa-utensils',
        'fa-glass'         => 'fa-wine-glass',
        'fa-newspaper-o'   => 'fa-newspaper',
        'fa-line-chart'    => 'fa-chart-line',
        'fa-bar-chart'     => 'fa-chart-bar',
        'fa-pie-chart'     => 'fa-chart-pie',
        'fa-sun-o'         => 'fa-sun',
        'fa-moon-o'        => 'fa-moon',
        'fa-diamond'       => 'fa-gem',
        'fa-hospital-o'    => 'fa-hospital',
        'fa-map-marker'    => 'fa-location-dot',
        'fa-birthday-cake' => 'fa-cake-candles',
        'fa-picture-o'     => 'fa-image',
        'fa-pencil'        => 'fa-pencil',
        'fa-shield'        => 'fa-shield-halved',
        'fa-ticket'        => 'fa-ticket',
    );

    if (isset($renames[$icon])) {
        $icon = $renames[$icon];
    }

    return 'fa-solid ' . $icon;
}
?>

<?php if (!empty($gallery_images)): ?>
<section class="sc-section" id="gallery">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.gallery', 'Gallery')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.moments_captured', 'Moments captured from this event')); ?></p>
        </div>

        <div class="sc-mobile-scroll-container">
        <div class="sc-gallery-grid sc-mobile-scroll-grid" data-aos="fade-up">
            <?php foreach ($gallery_images as $image_id):
                $thumb = wp_get_attachment_image_url($image_id, 'medium_large');
                $full = wp_get_attachment_image_url($image_id, 'full');
                $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                if (!$thumb) continue;
            ?>
            <a href="<?php echo esc_url($full); ?>" data-fancybox="gallery" class="sc-gallery-item" data-caption="<?php echo esc_attr($alt); ?>">
                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy">
                <div class="sc-gallery-overlay">
                    <i class="fa-solid fa-expand"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($other_sections)):
    $section_index = 0;
    foreach ($other_sections as $section):
        $heading      = $section['heading'] ?? $section['title'] ?? '';
        $content      = $section['content'] ?? '';
        $btn_text     = $section['button_text'] ?? $section['btn_text'] ?? '';
        $btn_url      = $section['button_url'] ?? $section['btn_url'] ?? '';
        $main_image   = $section['main_image'] ?? '';
        $images       = $section['images'] ?? array();
        $cards        = $section['cards'] ?? array();
        $section_type = $section['type'] ?? 'content';

        // Skip empty sections
        if (empty($heading) && empty($content) && empty($cards) && empty($main_image) && empty($images)) continue;

        $section_index++;
        $gallery_id = 'section-gallery-' . $section_index;
?>

<?php // ==============================
     // TYPE: ABOUT (text + optional image + button)
     // ============================== ?>
<?php if ($section_type === 'about'): ?>
<section class="sc-section <?php echo $section_index % 2 === 0 ? '' : 'sc-section-alt'; ?>">
    <div class="container">
        <?php if ($heading): ?>
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html($heading); ?></h2>
        </div>
        <?php endif; ?>

        <?php
        // About section images (first one as main banner)
        $about_image_url = '';
        if ($main_image) {
            $about_image_url = $main_image;
        } elseif (!empty($images)) {
            $first_id = reset($images);
            $about_image_url = wp_get_attachment_image_url($first_id, 'large');
        }
        ?>

        <div class="row align-items-center" data-aos="fade-up" style="gap: 0;">
            <div class="<?php echo $about_image_url ? 'col-lg-6' : 'col-lg-12'; ?>" style="margin-bottom: 24px;">
                <?php if ($content): ?>
                <div class="sc-about-section-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
                <?php endif; ?>

                <?php if ($btn_text && $btn_url): ?>
                <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url($btn_url); ?>" class="sc-btn sc-btn-outline">
                        <?php echo esc_html($btn_text); ?> <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($about_image_url): ?>
            <div class="col-lg-6" style="margin-bottom: 24px;">
                <div class="sc-about-section-image" style="margin: 0;">
                    <img src="<?php echo esc_url($about_image_url); ?>" alt="<?php echo esc_attr($heading); ?>" loading="lazy" style="border-radius: 16px; width: 100%; object-fit: cover;">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php // Show remaining images as small gallery if more than 1
        if (count($images) > 1): ?>
        <div class="sc-about-images-row" data-aos="fade-up">
            <?php foreach ($images as $img_id):
                $thumb = wp_get_attachment_image_url($img_id, 'medium');
                $full = wp_get_attachment_image_url($img_id, 'full');
                if (!$thumb) continue;
            ?>
            <a href="<?php echo esc_url($full); ?>" data-fancybox="<?php echo esc_attr($gallery_id); ?>" class="sc-about-thumb">
                <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php // ==============================
     // TYPE: CARD (icon cards grid)
     // ============================== ?>
<?php elseif ($section_type === 'card'): ?>
<section class="sc-section <?php echo $section_index % 2 === 0 ? '' : 'sc-section-alt'; ?>">
    <div class="container">
        <?php if ($heading): ?>
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html($heading); ?></h2>
        </div>
        <?php endif; ?>

        <?php if (!empty($cards)): ?>
        <div class="sc-mobile-scroll-container">
        <div class="sc-cards-grid sc-mobile-scroll-grid" data-aos="fade-up">
            <?php foreach ($cards as $card):
                $card_icon  = sc_fix_icon_class($card['icon'] ?? '');
                $card_title = $card['title'] ?? '';
                $card_desc  = $card['description'] ?? '';
                if (empty($card_title) && empty($card_desc)) continue;
            ?>
            <div class="sc-feature-card glass-card">
                <?php if ($card_icon): ?>
                <div class="sc-feature-card-icon">
                    <i class="<?php echo esc_attr($card_icon); ?>"></i>
                </div>
                <?php endif; ?>
                <?php if ($card_title): ?>
                <h5 class="sc-feature-card-title"><?php echo esc_html($card_title); ?></h5>
                <?php endif; ?>
                <?php if ($card_desc): ?>
                <p class="sc-feature-card-desc"><?php echo wp_kses_post($card_desc); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php // ==============================
     // TYPE: IMAGE GRID (photo grid with fancybox)
     // ============================== ?>
<?php elseif ($section_type === 'image_grid'): ?>
<?php if (!empty($images)): ?>
<section class="sc-section <?php echo $section_index % 2 === 0 ? '' : 'sc-section-alt'; ?>">
    <div class="container">
        <?php if ($heading): ?>
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html($heading); ?></h2>
        </div>
        <?php endif; ?>

        <div class="sc-mobile-scroll-container">
        <div class="sc-image-grid sc-mobile-scroll-grid" data-aos="fade-up">
            <?php foreach ($images as $img_id):
                $thumb = wp_get_attachment_image_url($img_id, 'medium_large');
                $full  = wp_get_attachment_image_url($img_id, 'full');
                $alt   = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                if (!$thumb) continue;
            ?>
            <a href="<?php echo esc_url($full); ?>" data-fancybox="<?php echo esc_attr($gallery_id); ?>" class="sc-image-grid-item" data-caption="<?php echo esc_attr($alt); ?>">
                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy">
                <div class="sc-image-grid-overlay">
                    <i class="fa-solid fa-expand"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php // ==============================
     // TYPE: IMAGE SLIDER (swiper carousel)
     // ============================== ?>
<?php elseif ($section_type === 'image_slider'): ?>
<?php if (!empty($images)): ?>
<section class="sc-section <?php echo $section_index % 2 === 0 ? '' : 'sc-section-alt'; ?>">
    <div class="container">
        <?php if ($heading): ?>
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html($heading); ?></h2>
        </div>
        <?php endif; ?>

        <div class="sc-image-slider-wrapper" data-aos="fade-up">
            <div class="swiper sc-slider-<?php echo $section_index; ?>">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $img_id):
                        $thumb = wp_get_attachment_image_url($img_id, 'medium_large');
                        $full  = wp_get_attachment_image_url($img_id, 'full');
                        $alt   = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                        if (!$thumb) continue;
                    ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url($full); ?>" data-fancybox="<?php echo esc_attr($gallery_id); ?>" data-caption="<?php echo esc_attr($alt); ?>">
                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php // ==============================
     // FALLBACK: generic content section
     // ============================== ?>
<?php else: ?>
<section class="sc-section <?php echo $section_index % 2 === 0 ? '' : 'sc-section-alt'; ?>">
    <div class="container">
        <?php if ($heading): ?>
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html($heading); ?></h2>
        </div>
        <?php endif; ?>

        <?php if ($main_image): ?>
        <div class="sc-about-section-image" data-aos="fade-up">
            <img src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($heading); ?>" loading="lazy">
        </div>
        <?php endif; ?>

        <?php if ($content): ?>
        <div class="row justify-content-center" data-aos="fade-up">
            <div class="col-lg-8">
                <div class="sc-about-section-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($btn_text && $btn_url): ?>
        <div class="text-center" data-aos="fade-up" style="margin-top: var(--sc-space-6);">
            <a href="<?php echo esc_url($btn_url); ?>" class="sc-btn sc-btn-outline">
                <?php echo esc_html($btn_text); ?> <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php
    endforeach;
endif;
?>
