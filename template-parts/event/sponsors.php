<?php
/**
 * Sponsors for this event.
 *
 * Same wall as the sponsors page — shared styles in wisdom/sponsor-wall.css —
 * so a logo looks the same wherever a visitor meets it.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

$event_sponsors = $args['event_sponsors'] ?? [];

if (empty($event_sponsors)) return;

$tier_order = ['diamond', 'platinum', 'gold', 'silver', 'bronze'];
$tier_names = [
    'diamond'  => sc_t('frontend.tier_diamond', 'Diamond'),
    'platinum' => sc_t('frontend.tier_platinum', 'Platinum'),
    'gold'     => sc_t('frontend.tier_gold', 'Gold'),
    'silver'   => sc_t('frontend.tier_silver', 'Silver'),
    'bronze'   => sc_t('frontend.tier_bronze', 'Bronze'),
];

$by_tier = [];
foreach ($event_sponsors as $sponsor) {
    $tier = strtolower((string) ($sponsor->tier_override ?: ($sponsor->tier ?? 'bronze')));
    $by_tier[$tier][] = $sponsor;
}

// Keep the ladder in order, and drop the tiers nobody is on.
$by_tier = array_filter(array_merge(array_fill_keys($tier_order, []), $by_tier));
$is_top = true;
?>

<section class="w-ev__section" id="sponsors">
    <h2 class="w-ev__h2"><?php echo esc_html(sc_t('frontend.our_sponsors', 'Our sponsors')); ?></h2>

    <div class="w-sponsors">
        <?php foreach ($by_tier as $tier_key => $tier_sponsors): ?>
        <div class="w-tier<?php echo $is_top ? ' w-tier--top' : ''; ?>">
            <div class="w-tier__head">
                <span class="w-tier__badge"><?php echo esc_html($tier_names[$tier_key] ?? ucfirst($tier_key)); ?></span>
            </div>
            <div class="w-tier__logos">
                <?php foreach ($tier_sponsors as $sp):
                    $logo = '';
                    if (!empty($sp->logo)) {
                        $logo = sc_image_src($sp->logo);
                    }
                    $href = $sp->website ?? '';
                    $tag  = $href ? 'a' : 'span';
                ?>
                <<?php echo $tag; ?> class="w-logo"<?php
                    if ($href) { echo ' href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer"'; }
                ?>>
                    <?php if ($logo): ?>
                        <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($sp->name); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <?php echo esc_html($sp->name); ?>
                    <?php endif; ?>
                </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
        </div>
        <?php $is_top = false; ?>
        <?php endforeach; ?>
    </div>
</section>
