<?php
/**
 * Single speaker
 *
 * Reached through the rewrite in inc/public-frontend/speaker-routing.php, since
 * speakers live in the custom table rather than in wp_posts.
 *
 * @package sc_events
 */

$slug = get_query_var('sc_speaker_slug');
$speaker = ($slug && class_exists('SC_Speaker')) ? SC_Speaker::get_by_slug($slug) : null;

if (!$speaker) {
    get_template_part('404');
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';

$photo = '';
if (!empty($speaker->photo)) {
    $photo = sc_image_src($speaker->photo);
}
$initials = sc_initials($speaker->name);

// social_links is JSON in the column but SC_Speaker hands it back already
// decoded, so accept either shape rather than assuming one.
$socials = [];
if (!empty($speaker->social_links)) {
    $decoded = is_array($speaker->social_links)
        ? $speaker->social_links
        : json_decode((string) $speaker->social_links, true);
    if (is_array($decoded)) {
        $socials = array_filter($decoded, 'is_string');
    }
}

// The events this speaker appears at.
$events = [];
if (class_exists('SC_Event')) {
    global $wpdb;
    $p = $wpdb->prefix . 'sc_';
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.* FROM {$p}events e
         INNER JOIN {$p}event_speakers es ON es.event_id = e.id
         WHERE es.speaker_id = %d AND e.status IN ('publish','completed')
         ORDER BY e.start_date DESC",
        $speaker->id
    ));
}

get_template_part('template-parts/public/header', 'public');
?>

<section class="w-section">
    <nav class="w-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'sc_events'); ?>">
        <a href="<?php echo esc_url(home_url('/speakers/')); ?>"><?php echo esc_html(sc_t('frontend.speakers', 'Speakers')); ?></a>
        <span aria-hidden="true">›</span>
        <span><?php echo esc_html($speaker->name); ?></span>
    </nav>

    <div class="w-profile">
        <div class="w-profile__portrait<?php echo $photo ? '' : ' w-profile__portrait--empty'; ?>">
            <?php if ($photo): ?>
                <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($speaker->name); ?>" fetchpriority="high" decoding="async">
            <?php else: ?>
                <span aria-hidden="true"><?php echo esc_html($initials); ?></span>
            <?php endif; ?>
        </div>

        <div class="w-profile__body">
            <h1 class="w-profile__name"><?php echo esc_html($speaker->name); ?></h1>

            <?php if (!empty($speaker->title) || !empty($speaker->company)): ?>
            <p class="w-profile__role">
                <?php echo esc_html(trim(implode(' · ', array_filter([$speaker->title, $speaker->company])))); ?>
            </p>
            <?php endif; ?>

            <?php if (!empty($speaker->bio)): ?>
            <div class="w-prose"><?php echo wp_kses_post(wpautop($speaker->bio)); ?></div>
            <?php endif; ?>

            <?php if ($socials || !empty($speaker->website)): ?>
            <div class="w-profile__links">
                <?php if (!empty($speaker->website)): ?>
                    <a class="w-btn w-btn--outline w-btn--sm" href="<?php echo esc_url($speaker->website); ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                        <?php echo esc_html(sc_t('frontend.website', 'Website')); ?>
                    </a>
                <?php endif; ?>
                <?php foreach ($socials as $network => $url):
                    if (!is_string($url) || $url === '') { continue; }
                ?>
                    <a class="w-btn w-btn--outline w-btn--sm" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-<?php echo esc_attr(sanitize_html_class($network)); ?>" aria-hidden="true"></i>
                        <?php echo esc_html(ucfirst($network)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($events): ?>
<section class="w-section">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.speaking_at', 'Speaking at')); ?></h2>
    </div>

    <div class="w-events">
        <?php foreach ($events as $ev):
            $s = strtotime($ev->start_date);
            $e = !empty($ev->end_date) ? strtotime($ev->end_date) : $s;
            $same = date('Y-m', $s) === date('Y-m', $e);
            $big = $s === $e
                ? date_i18n('j', $s)
                : ($same ? date_i18n('j', $s) . '–' . date_i18n('j', $e)
                         : date_i18n('j M', $s) . ' – ' . date_i18n('j M', $e));
            $small = $same ? date_i18n('M Y', $s) : date_i18n('Y', $e);
            $cover = $ev->featured_image ? wp_get_attachment_url($ev->featured_image) : '';
            $past = $e < current_time('timestamp');
        ?>
        <a class="w-event<?php echo $cover ? '' : ' w-event--empty'; ?>" href="<?php echo esc_url(home_url('/event/' . $ev->slug)); ?>">
            <?php if ($cover): ?>
                <img class="w-event__cover" src="<?php echo esc_url($cover); ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="w-event__body">
                <span class="w-event__badges">
                    <span class="w-event__badge">
                        <?php if (!$past): ?><span class="w-hero__pulse" aria-hidden="true"></span><?php endif; ?>
                        <?php echo esc_html($past ? sc_t('frontend.past', 'Past') : sc_t('frontend.registration_open', 'Registration open')); ?>
                    </span>
                </span>
                <span class="w-event__foot">
                    <span class="w-event__text">
                        <span class="w-event__date"><?php echo esc_html($big); ?> <span><?php echo esc_html($small); ?></span></span>
                        <span class="w-event__title"><?php echo esc_html($ev->title); ?></span>
                    </span>
                </span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
