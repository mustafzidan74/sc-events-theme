<?php
/**
 * Event Speakers - Dark & Premium Grid with Full Details
 *
 * @package sc_events
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

$event_speakers = $args['event_speakers'] ?? array();

if (empty($event_speakers)) return;

$use_slider = count($event_speakers) > 6;
?>

<section class="sc-section" id="speakers">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.our_speakers', 'Our Speakers')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.our_speakers_desc', 'Meet the experts sharing their knowledge')); ?></p>
        </div>

        <?php if ($use_slider): ?>
        <div class="sc-speakers-slider-wrapper" data-aos="fade-up">
            <div class="swiper sc-speakers-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($event_speakers as $speaker):
                        $photo_url = $speaker->photo_url ?? '';
                        if (empty($photo_url) && !empty($speaker->photo)) {
                            $photo_url = is_numeric($speaker->photo) ? wp_get_attachment_url($speaker->photo) : $speaker->photo;
                        }
                        $social = $speaker->social_links ?? array();
                        if (is_string($social)) $social = json_decode($social, true) ?: array();
                        $role = $speaker->role ?? '';
                        $bio = $speaker->bio ?? '';
                        $job_title = $speaker->job_title ?? ($speaker->title ?? '');
                        $company = $speaker->company ?? '';
                        $email = $speaker->email ?? '';
                        $phone = $speaker->phone ?? '';
                        $website = $speaker->website ?? '';
                    ?>
                    <div class="swiper-slide">
                        <div class="sc-speaker-card-full glass-card">
                            <div class="sc-speaker-top">
                                <div class="sc-speaker-photo">
                                    <?php if ($photo_url): ?>
                                    <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($speaker->name); ?>">
                                    <?php else: ?>
                                    <div class="sc-speaker-placeholder"><i class="fa-solid fa-user"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="sc-speaker-info">
                                    <h5 class="sc-speaker-name"><?php echo esc_html($speaker->name); ?></h5>
                                    <?php if ($job_title || $company):
                                        $subtitle = $job_title;
                                        if ($company) $subtitle .= ($subtitle ? ' @ ' : '') . $company;
                                    ?>
                                    <span class="sc-speaker-title"><?php echo esc_html($subtitle); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($bio): ?>
                            <div class="sc-speaker-bio"><?php echo wp_kses_post(wp_trim_words($bio, 30, '...')); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($social) && is_array($social)): ?>
                            <div class="sc-speaker-social" style="margin-top: 12px;">
                                <?php foreach ($social as $link):
                                    $icon_class = $link['icon'] ?? '';
                                    $url = $link['url'] ?? '';
                                    if (!$url) continue;
                                    if (strpos($icon_class, 'fa-') === false) {
                                        $icon_class = 'fa-brands fa-' . $icon_class;
                                    }
                                ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" class="sc-social-link"><i class="<?php echo esc_attr($icon_class); ?>"></i></a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
            <div class="swiper-pagination sc-speakers-pagination"></div>
        </div>

        <style>
        .sc-speakers-slider-wrapper { position: relative; padding: 0 40px; }
        .sc-speakers-swiper { padding: 20px 0; overflow: hidden; }
        .sc-speakers-swiper .swiper-slide { height: auto; }
        .sc-speakers-swiper .swiper-slide .sc-speaker-card-full { height: 100%; }
        .sc-speakers-slider-wrapper .swiper-button-next,
        .sc-speakers-slider-wrapper .swiper-button-prev { color: var(--sc-primary, #7c1314); background: rgba(255,255,255,0.95); width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.15); top: calc(50% - 20px); }
        .sc-speakers-slider-wrapper .swiper-button-next::after,
        .sc-speakers-slider-wrapper .swiper-button-prev::after { font-size: 16px; font-weight: 700; }
        .sc-speakers-pagination { position: static !important; text-align: center; margin-top: 24px; }
        .sc-speakers-pagination .swiper-pagination-bullet-active { background: var(--sc-primary, #7c1314); }
        </style>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var spkEl = document.querySelector('.sc-speakers-swiper');
            if (spkEl && typeof Swiper !== 'undefined') {
                new Swiper(spkEl, {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    loop: true,
                    autoplay: { delay: 4000, disableOnInteraction: false },
                    pagination: { el: spkEl.parentElement.querySelector('.sc-speakers-pagination'), clickable: true },
                    navigation: { nextEl: spkEl.parentElement.querySelector('.swiper-button-next'), prevEl: spkEl.parentElement.querySelector('.swiper-button-prev') },
                    breakpoints: {
                        640: { slidesPerView: 2 },
                        992: { slidesPerView: 3 }
                    }
                });
            }
        });
        </script>
        <?php else: ?>
        <div class="sc-mobile-scroll-container">
        <div class="row g-4 sc-mobile-scroll-row">
            <?php $delay = 0; foreach ($event_speakers as $speaker):
                $photo_url = $speaker->photo_url ?? '';
                if (empty($photo_url) && !empty($speaker->photo)) {
                    $photo_url = is_numeric($speaker->photo) ? wp_get_attachment_url($speaker->photo) : $speaker->photo;
                }
                $social = $speaker->social_links ?? array();
                if (is_string($social)) $social = json_decode($social, true) ?: array();
                $role = $speaker->role ?? '';
                $bio = $speaker->bio ?? '';
                $job_title = $speaker->job_title ?? ($speaker->title ?? '');
                $company = $speaker->company ?? '';
                $email = $speaker->email ?? '';
                $phone = $speaker->phone ?? '';
                $website = $speaker->website ?? '';
            ?>
            <div class="col-lg-4 col-md-6 sc-mobile-scroll-item" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="sc-speaker-card-full glass-card">
                    <!-- Top: Photo + Basic Info -->
                    <div class="sc-speaker-top">
                        <div class="sc-speaker-photo">
                            <?php if ($photo_url): ?>
                            <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($speaker->name); ?>">
                            <?php else: ?>
                            <div class="sc-speaker-placeholder"><i class="fa-solid fa-user"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="sc-speaker-info">
                            <h5 class="sc-speaker-name"><?php echo esc_html($speaker->name); ?></h5>
                            <?php if ($job_title || $company): ?>
                            <span class="sc-speaker-title">
                                <?php
                                $subtitle = $job_title;
                                if ($company) $subtitle .= ($subtitle ? ' @ ' : '') . $company;
                                echo esc_html($subtitle);
                                ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($role && $role !== 'speaker'): ?>
                            <div style="margin-top: var(--sc-space-1);">
                                <span class="sc-badge-gold"><?php echo esc_html(ucfirst($role)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bio -->
                    <?php if ($bio): ?>
                    <div class="sc-speaker-bio">
                        <?php echo wp_kses_post(wp_trim_words($bio, 40, '...')); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Contact & Social -->
                    <div class="sc-speaker-footer">
                        <?php if ($email || $phone || $website): ?>
                        <div class="sc-speaker-contact">
                            <?php if ($email): ?>
                            <a href="mailto:<?php echo esc_attr($email); ?>" class="sc-speaker-contact-link" title="<?php echo esc_attr($email); ?>">
                                <i class="fa-solid fa-envelope"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                            <a href="tel:<?php echo esc_attr($phone); ?>" class="sc-speaker-contact-link" title="<?php echo esc_attr($phone); ?>">
                                <i class="fa-solid fa-phone"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($website): ?>
                            <a href="<?php echo esc_url($website); ?>" target="_blank" class="sc-speaker-contact-link" title="<?php echo esc_attr($website); ?>">
                                <i class="fa-solid fa-globe"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($social) && is_array($social)): ?>
                        <div class="sc-speaker-social">
                            <?php foreach ($social as $link):
                                $icon_class = $link['icon'] ?? '';
                                $url = $link['url'] ?? '';
                                if (!$url) continue;
                                if (strpos($icon_class, 'fa-') === false) {
                                    $icon_class = 'fa-brands fa-' . $icon_class;
                                }
                            ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="sc-social-link"><i class="<?php echo esc_attr($icon_class); ?>"></i></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php $delay += 80; endforeach; ?>
        </div>
        </div>
        <?php endif; // end use_slider ?>
    </div>
</section>
