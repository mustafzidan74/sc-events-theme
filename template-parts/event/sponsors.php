<?php
/**
 * Event Sponsors - Dark & Premium Tiered Display
 *
 * @package sc_events
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

$event_sponsors = $args['event_sponsors'] ?? array();

if (empty($event_sponsors)) return;

// Group by tier
$tiers = array();
foreach ($event_sponsors as $sponsor) {
    $tier = $sponsor->tier_override ?? $sponsor->tier ?? 'bronze';
    if (!isset($tiers[$tier])) $tiers[$tier] = array();
    $tiers[$tier][] = $sponsor;
}

$tier_config = array(
    'platinum' => array(
        'label' => sc_t('frontend.platinum_partners', 'Platinum Partners'),
        'icon'  => 'fa-solid fa-gem',
        'color' => '#8B5CF6',
        'glow'  => 'rgba(139,92,246,0.15)',
        'col'   => 'col-lg-4 col-md-6',
        'size'  => 'lg',
    ),
    'gold' => array(
        'label' => sc_t('frontend.gold_partners', 'Gold Partners'),
        'icon'  => 'fa-solid fa-crown',
        'color' => '#F59E0B',
        'glow'  => 'rgba(245,158,11,0.15)',
        'col'   => 'col-lg-3 col-md-4 col-6',
        'size'  => 'md',
    ),
    'silver' => array(
        'label' => sc_t('frontend.silver_partners', 'Silver Partners'),
        'icon'  => 'fa-solid fa-medal',
        'color' => '#94A3B8',
        'glow'  => 'rgba(148,163,184,0.1)',
        'col'   => 'col-lg-3 col-md-4 col-6',
        'size'  => 'sm',
    ),
    'bronze' => array(
        'label' => sc_t('frontend.bronze_partners', 'Bronze Partners'),
        'icon'  => 'fa-solid fa-award',
        'color' => '#CD7F32',
        'glow'  => 'rgba(205,127,50,0.1)',
        'col'   => 'col-lg-2 col-md-3 col-4',
        'size'  => 'sm',
    ),
);

$tier_order = array('platinum', 'gold', 'silver', 'bronze');
?>

<section class="sc-section sc-section-alt" id="sponsors">
    <div class="container">
        <div class="sc-section-header" data-aos="fade-up">
            <h2><?php echo esc_html(sc_t('frontend.our_sponsors', 'Our Sponsors')); ?></h2>
            <p><?php echo esc_html(sc_t('frontend.our_sponsors_desc', 'Thank you to our generous partners and sponsors')); ?></p>
        </div>

        <?php foreach ($tier_order as $tier_key):
            if (empty($tiers[$tier_key])) continue;
            $config = $tier_config[$tier_key];
        ?>
        <div class="sc-sponsor-tier sc-sponsor-tier--<?php echo esc_attr($tier_key); ?>" data-aos="fade-up">
            <div class="sc-sponsor-tier-badge" style="--tier-color: <?php echo $config['color']; ?>; --tier-glow: <?php echo $config['glow']; ?>;">
                <i class="<?php echo esc_attr($config['icon']); ?>"></i>
                <span><?php echo esc_html($config['label']); ?></span>
            </div>

            <div class="sc-mobile-scroll-container">
            <div class="row g-4 justify-content-center sc-mobile-scroll-row sc-mobile-scroll-row--sponsors">
                <?php foreach ($tiers[$tier_key] as $sponsor):
                    $logo_url = $sponsor->logo_url ?? '';
                    if (empty($logo_url) && !empty($sponsor->logo)) {
                        $logo_url = is_numeric($sponsor->logo) ? wp_get_attachment_url($sponsor->logo) : $sponsor->logo;
                    }
                    $website = $sponsor->website ?? '';
                    $tag = $website ? 'a' : 'div';
                    $href = $website ? ' href="' . esc_url($website) . '" target="_blank"' : '';
                ?>
                <div class="<?php echo esc_attr($config['col']); ?> sc-mobile-scroll-item">
                    <<?php echo $tag; ?><?php echo $href; ?> class="sc-sponsor-card sc-sponsor-card--<?php echo esc_attr($config['size']); ?>" title="<?php echo esc_attr($sponsor->name); ?>" style="--tier-color: <?php echo $config['color']; ?>;">
                        <div class="sc-sponsor-card-img">
                            <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($sponsor->name); ?>">
                            <?php else: ?>
                            <span class="sc-sponsor-initials"><?php
                                $words = explode(' ', $sponsor->name);
                                $initials = '';
                                foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                                echo esc_html($initials);
                            ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="sc-sponsor-name"><?php echo esc_html($sponsor->name); ?></span>
                    </<?php echo $tag; ?>>
                </div>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
