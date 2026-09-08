<?php
/**
 * The pitch panel shared by the auth pages.
 *
 * Falls back to the plain ink panel when no cover image is available, since
 * the gradient already carries the contrast the copy needs.
 *
 * @package sc_events
 */

$cover = '';
if (class_exists('SC_Event')) {
    $upcoming = SC_Event::get_all([
        'status'        => ['publish'],
        'upcoming_only' => true,
        'limit'         => 1,
        'orderby'       => 'start_date',
        'order'         => 'ASC',
    ]);
    if ($upcoming && !empty($upcoming[0]->featured_image)) {
        $cover = wp_get_attachment_url($upcoming[0]->featured_image);
    }
}
?>
<aside class="w-auth__aside">
    <?php if ($cover): ?>
        <img src="<?php echo esc_url($cover); ?>" alt="" loading="lazy" decoding="async">
    <?php endif; ?>
    <span class="w-auth__kicker"><?php echo esc_html(sc_t('frontend.one_account', 'One account')); ?></span>
    <h2 class="w-auth__pitch">
        <?php echo esc_html(sc_t('frontend.auth_pitch', 'Tickets, e-badge, agenda and certificates — for every Wisdom event.')); ?>
    </h2>
    <p class="w-auth__sub">
        <?php echo esc_html(sc_t('frontend.auth_pitch_sub', 'Your syndicate number and CME record stay with you across congresses.')); ?>
    </p>
</aside>
