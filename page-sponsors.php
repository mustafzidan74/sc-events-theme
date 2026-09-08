<?php
/**
 * Template Name: Sponsors
 *
 * Two jobs on one page: make the case to a prospective sponsor, and show the
 * ones already on board. Both halves read from the database — the figures are
 * the platform's real numbers, not copy.
 *
 * @package sc_events
 */

$assets_url = get_template_directory_uri() . '/assets/frontend/';

global $wpdb;
$p = $wpdb->prefix . 'sc_';

// The event to pitch against: the next one still to run.
$next_event = null;
if (class_exists('SC_Event')) {
    $upcoming = SC_Event::get_all([
        'status'        => ['publish'],
        'upcoming_only' => true,
        'limit'         => 1,
        'orderby'       => 'start_date',
        'order'         => 'ASC',
    ]);
    $next_event = $upcoming ? $upcoming[0] : null;
}

$total_attendees = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}attendees");
$total_speakers  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}speakers WHERE is_active = 1");
$total_events    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}events WHERE status IN ('publish','completed')");

$sponsors = $wpdb->get_results(
    "SELECT * FROM {$p}sponsors WHERE is_active = 1
     ORDER BY FIELD(tier,'diamond','platinum','gold','silver','bronze'), sort_order ASC"
);
$partners = $wpdb->get_results("SELECT * FROM {$p}partners WHERE is_active = 1 ORDER BY sort_order ASC");

$tier_order = ['diamond', 'platinum', 'gold', 'silver', 'bronze'];
$tier_names = [
    'diamond'  => sc_t('frontend.tier_diamond', 'Diamond'),
    'platinum' => sc_t('frontend.tier_platinum', 'Platinum'),
    'gold'     => sc_t('frontend.tier_gold', 'Gold'),
    'silver'   => sc_t('frontend.tier_silver', 'Silver'),
    'bronze'   => sc_t('frontend.tier_bronze', 'Bronze'),
];

$by_tier = [];
foreach ($sponsors as $sponsor) {
    $by_tier[strtolower((string) $sponsor->tier)][] = $sponsor;
}
$by_tier = array_filter(array_merge(array_fill_keys($tier_order, []), $by_tier));

$facts = array_filter([
    ['n' => $total_attendees, 'l' => sc_t('frontend.attendees', 'Attendees')],
    ['n' => $total_speakers,  'l' => sc_t('frontend.speakers', 'Speakers')],
    ['n' => $total_events,    'l' => sc_t('frontend.events', 'Events')],
], static fn($f) => $f['n'] > 0);

get_template_part('template-parts/public/header', 'public');
?>

<header class="w-page-head">
    <p class="w-eyebrow">
        <?php echo esc_html(sc_t('frontend.sponsors_exhibitors', 'Sponsors & exhibitors')); ?><?php
        if ($next_event) { echo ' · ' . esc_html($next_event->title); }
        ?>
    </p>
    <h1><?php printf(
        esc_html(sc_t('frontend.sponsors_headline', 'Meet %s dentists who buy what they see.')),
        esc_html(number_format_i18n($total_attendees))
    ); ?></h1>
    <p><?php echo esc_html(sc_t('frontend.sponsors_lede', 'Every decision-maker in Egyptian dentistry, in one foyer.')); ?></p>

    <div class="w-hero__actions" style="margin-top:var(--w-space-6)">
        <a class="w-hero__cta" href="<?php echo esc_url(home_url('/contact/')); ?>">
            <?php echo esc_html(sc_t('frontend.book_a_booth', 'Book a booth')); ?>
        </a>
    </div>
</header>

<?php if ($facts): ?>
<section class="w-section">
    <div class="w-stats">
        <?php foreach ($facts as $f): ?>
        <div class="w-stat">
            <span class="w-stat__value"><?php echo esc_html(number_format_i18n($f['n'])); ?></span>
            <span class="w-stat__label"><?php echo esc_html($f['l']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($by_tier): $is_top = true; ?>
<section class="w-section" id="partners">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.our_sponsors', 'Our sponsors')); ?></h2>
    </div>

    <div class="w-sponsors">
        <?php foreach ($by_tier as $tier_key => $tier_sponsors): ?>
        <div class="w-tier<?php echo $is_top ? ' w-tier--top' : ''; ?>">
            <div class="w-tier__head">
                <span class="w-tier__badge"><?php echo esc_html($tier_names[$tier_key] ?? ucfirst($tier_key)); ?></span>
                <?php if ($is_top): ?>
                    <span class="w-tier__note"><?php echo esc_html(sc_t('frontend.title_sponsor', 'Title sponsor')); ?></span>
                <?php endif; ?>
            </div>
            <div class="w-tier__logos">
                <?php foreach ($tier_sponsors as $sp):
                    $logo = '';
                    if (!empty($sp->logo)) {
                        $logo = is_numeric($sp->logo) ? wp_get_attachment_url($sp->logo) : $sp->logo;
                    }
                    $href = !empty($sp->website) ? $sp->website : '';
                    $tag = $href ? 'a' : 'span';
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
<?php endif; ?>

<?php if ($partners): ?>
<section class="w-section">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.partners', 'Partners')); ?></h2>
    </div>
    <div class="w-partners">
        <?php foreach ($partners as $pt):
            $logo = '';
            if (!empty($pt->logo)) {
                $logo = is_numeric($pt->logo) ? wp_get_attachment_url($pt->logo) : $pt->logo;
            }
            $href = !empty($pt->website) ? $pt->website : '';
            $tag = $href ? 'a' : 'span';
        ?>
        <<?php echo $tag; ?> class="w-partner"<?php
            if ($href) { echo ' href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer"'; }
        ?>>
            <?php if ($logo): ?>
                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($pt->name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <?php echo esc_html($pt->name); ?>
            <?php endif; ?>
        </<?php echo $tag; ?>>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
