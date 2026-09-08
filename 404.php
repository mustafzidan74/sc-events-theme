<?php
/**
 * 404 — page not found
 *
 * Most arrivals here followed a link to a past edition, so the page says so
 * plainly and then offers the four things people actually came for rather
 * than a lone "back to home".
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');

$tiles = [
    ['label' => sc_t('frontend.programme', 'Programme'), 'href' => home_url('/events/'),    'icon' => 'fa-regular fa-clock'],
    ['label' => sc_t('frontend.speakers', 'Speakers'),   'href' => home_url('/speakers/'),  'icon' => 'fa-regular fa-user'],
    ['label' => sc_t('frontend.workshops', 'Workshops'), 'href' => home_url('/workshops/'), 'icon' => 'fa-solid fa-bars'],
    ['label' => sc_t('frontend.my_ticket', 'My ticket'), 'href' => home_url('/my-account/'), 'icon' => 'fa-solid fa-ticket'],
];
?>

<section class="w-404">
    <div class="w-404__say">
        <span class="w-404__code" aria-hidden="true">404</span>
        <h1 class="w-404__title"><?php echo esc_html(sc_t('frontend.404_title', 'This page has left the building.')); ?></h1>
        <p class="w-404__lede">
            <?php echo esc_html(sc_t('frontend.404_lede', 'The link may be old — past editions move to the archive after each congress.')); ?>
        </p>
        <div class="w-404__actions">
            <a class="w-btn w-btn--lg" href="<?php echo esc_url(home_url('/events/')); ?>">
                <?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?>
            </a>
            <a class="w-btn w-btn--outline w-btn--lg" href="<?php echo esc_url(home_url('/')); ?>">
                <?php echo esc_html(sc_t('frontend.home', 'Home')); ?>
            </a>
        </div>
    </div>

    <div class="w-404__tiles">
        <?php foreach ($tiles as $tile): ?>
        <a class="w-tile-sm" href="<?php echo esc_url($tile['href']); ?>">
            <span class="w-tile-sm__icon" aria-hidden="true"><i class="<?php echo esc_attr($tile['icon']); ?>"></i></span>
            <span class="w-tile-sm__label"><?php echo esc_html($tile['label']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
