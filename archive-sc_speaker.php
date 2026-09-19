<?php
/**
 * Template Name: Speakers Archive
 *
 * Reads from the custom speakers table — none of the 89 speakers has a
 * WordPress post, so the loop is ours rather than WordPress's.
 *
 * @package sc_events
 */

if (!class_exists('SC_Speaker')) {
    get_template_part('404');
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';

// `s` is WordPress's own search variable and would hand the request to the
// search template, so the form submits `speaker_s`.
$search = isset($_GET['speaker_s']) ? sanitize_text_field($_GET['speaker_s']) : '';
// Opens on the event whose turn it is when it has speakers; "All" is ?event=all.
$event_param = sc_listing_event_param();
$event_filter = ctype_digit($event_param) ? (int) $event_param : 0;
$speakers = $event_filter > 0 ? (SC_Speaker::get_by_event($event_filter) ?: []) : [];
if ($event_param === '' && ($turn = sc_turn_event_id())) {
    $speakers = SC_Speaker::get_by_event($turn) ?: [];
    $event_filter = $speakers ? $turn : 0;
}
if (!$event_filter) {
    $speakers = SC_Speaker::get_all(['is_active' => 1, 'limit' => 500]) ?: [];
}

if ($search !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
    $speakers = array_values(array_filter($speakers, static function ($sp) use ($needle) {
        $haystack = strtolower(($sp->name ?? '') . ' ' . ($sp->title ?? '') . ' ' . ($sp->company ?? ''));
        return str_contains($haystack, $needle);
    }));
}

$events_for_filter = class_exists('SC_Event') ? SC_Event::get_all([
    'status'  => ['publish', 'completed'],
    'limit'   => 20,
    'orderby' => 'start_date',
    'order'   => 'DESC',
]) : [];

$base = home_url('/speakers/');

// Twenty-four portraits to a page.
$per_page = 24;
$total = count($speakers);
$max_pages = max(1, (int) ceil($total / $per_page));
$page = min($max_pages, max(1, (int) get_query_var('paged')));
$speakers_page = array_slice($speakers, ($page - 1) * $per_page, $per_page);

get_template_part('template-parts/public/header', 'public');
?>

<header class="w-page-head">
    <h1><?php echo esc_html(sc_t('frontend.speakers', 'Speakers')); ?></h1>
    <p><?php echo esc_html(sc_t('frontend.speakers_lede', 'The faculty teaching across our congresses and workshops.')); ?></p>
</header>

<section class="w-section">
    <div class="w-toolbar">
        <?php if ($events_for_filter): ?>
        <div class="w-filters">
            <a href="<?php echo esc_url(add_query_arg('event', 'all', $base)); ?>" style="text-decoration:none">
                <button type="button" aria-pressed="<?php echo $event_filter ? 'false' : 'true'; ?>">
                    <?php echo esc_html(sc_t('frontend.all', 'All')); ?>
                </button>
            </a>
            <?php foreach (array_slice($events_for_filter, 0, 3) as $ev): ?>
            <a href="<?php echo esc_url(add_query_arg('event', $ev->id, $base)); ?>" style="text-decoration:none">
                <button type="button" aria-pressed="<?php echo (int) $event_filter === (int) $ev->id ? 'true' : 'false'; ?>">
                    <?php echo esc_html(wp_trim_words($ev->title, 3, '')); ?>
                </button>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form class="w-search" method="get" action="<?php echo esc_url($base); ?>" role="search">
            <input type="hidden" name="event" value="<?php echo esc_attr($event_filter ?: 'all'); ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="color:var(--w-text-3)"></i>
            <input type="search" name="speaker_s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo esc_attr(sc_t('frontend.search_speakers', 'Search speakers…')); ?>"
                   aria-label="<?php echo esc_attr(sc_t('frontend.search_speakers', 'Search speakers…')); ?>">
        </form>
    </div>

    <?php if ($speakers): ?>
    <p class="w-count" style="margin:0 0 var(--w-space-5)">
        <?php printf(esc_html(sc_t('frontend.showing_n_speakers', 'Showing %s speakers')), esc_html(number_format_i18n($total))); ?>
    </p>

    <div class="w-speakers">
        <?php foreach ($speakers_page as $sp):
            $photo = '';
            if (!empty($sp->photo)) {
                $photo = sc_img($sp->photo, 'medium_large', '(max-width: 767px) calc(100vw - 40px), 220px', array('alt' => $sp->name), 800);
            }
            $initials = sc_initials($sp->name);
        ?>
        <a class="w-speaker" href="<?php echo esc_url(sc_speaker_permalink($sp)); ?>">
            <?php if ($photo): ?>
                <?php echo $photo; // Built by wp_get_attachment_image(). ?>
            <?php else: ?>
                <span class="w-speaker__initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
            <?php endif; ?>
            <span class="w-speaker__reveal">
                <span class="w-speaker__name"><?php echo esc_html($sp->name); ?></span>
                <?php if (!empty($sp->title)): ?>
                    <span class="w-speaker__topic"><?php echo esc_html($sp->title); ?></span>
                <?php endif; ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($max_pages > 1):
        $w_links = paginate_links([
            'total'     => $max_pages,
            'current'   => $page,
            'type'      => 'array',
            'prev_text' => '‹',
            'next_text' => '›',
        ]);
    ?>
    <nav class="w-pagination" aria-label="<?php esc_attr_e('Pagination', 'sc_events'); ?>">
        <?php foreach ((array) $w_links as $w_link) { echo wp_kses_post($w_link); } ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="w-empty">
        <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.no_speakers_found', 'No speakers found')); ?></span>
        <p><?php echo esc_html(sc_t('frontend.try_another_filter', 'Try another filter or clear your search.')); ?></p>
        <a class="w-btn w-btn--outline" href="<?php echo esc_url(add_query_arg('event', 'all', $base)); ?>"><?php echo esc_html(sc_t('frontend.all', 'All')); ?></a>
    </div>
    <?php endif; ?>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
