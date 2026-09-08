<?php
/**
 * Organiser.
 *
 * A credit line with a way to reach them, not a second landing page — so it
 * sits low, stays quiet, and the contact links loop rather than repeating the
 * same block per network.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$oc = $args['organizing_company'] ?? null;

if (empty($oc) || empty($oc['name'])) return;

$logo   = !empty($oc['logo']) ? wp_get_attachment_url($oc['logo']) : '';
$social = is_array($oc['social'] ?? null) ? $oc['social'] : [];

$links = [];
if (!empty($oc['website'])) {
    $links[] = ['href' => $oc['website'], 'icon' => 'fa-solid fa-globe',
                'text' => parse_url($oc['website'], PHP_URL_HOST) ?: $oc['website']];
}
if (!empty($oc['phone'])) {
    $links[] = ['href' => 'tel:' . $oc['phone'], 'icon' => 'fa-solid fa-phone', 'text' => $oc['phone']];
}
if (!empty($oc['email'])) {
    $links[] = ['href' => 'mailto:' . $oc['email'], 'icon' => 'fa-solid fa-envelope', 'text' => $oc['email']];
}
if (!empty($oc['whatsapp'])) {
    $links[] = ['href' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $oc['whatsapp']),
                'icon' => 'fa-brands fa-whatsapp', 'text' => 'WhatsApp'];
}

$social_icons = [
    'facebook'  => 'fa-brands fa-facebook-f',
    'twitter'   => 'fa-brands fa-twitter',
    'instagram' => 'fa-brands fa-instagram',
    'linkedin'  => 'fa-brands fa-linkedin-in',
    'youtube'   => 'fa-brands fa-youtube',
];
?>

<section class="w-ev__section" id="organizing-company">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.organizing_company', 'Organised by')); ?></h2>

    <div class="w-venue">
        <div class="w-venue__say">
            <h3 class="w-venue__name"><?php echo esc_html($oc['name']); ?></h3>

            <?php if (!empty($oc['description'])): ?>
                <div class="w-prose"><?php echo wp_kses_post(wpautop($oc['description'])); ?></div>
            <?php endif; ?>

            <?php if ($links): ?>
            <div class="w-venue__halls">
                <?php foreach ($links as $link): ?>
                <a class="w-venue__hall" href="<?php echo esc_url($link['href']); ?>"
                   <?php echo str_starts_with($link['href'], 'http') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                   style="text-decoration:none;gap:8px">
                    <i class="<?php echo esc_attr($link['icon']); ?>" aria-hidden="true"></i>
                    <?php echo esc_html($link['text']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($social): ?>
            <div class="w-ev__socials">
                <?php foreach ($social as $network => $url):
                    if (empty($url) || !isset($social_icons[$network])) { continue; }
                ?>
                <a class="w-ev__social" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                   aria-label="<?php echo esc_attr(ucfirst($network)); ?>">
                    <i class="<?php echo esc_attr($social_icons[$network]); ?>" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($logo): ?>
        <div class="w-venue__pic" style="aspect-ratio:16/9;display:grid;place-items:center;padding:var(--w-space-6);box-sizing:border-box">
            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($oc['name']); ?>"
                 loading="lazy" decoding="async"
                 style="width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain">
        </div>
        <?php endif; ?>
    </div>
</section>
