<?php
/**
 * The fallback listing.
 *
 * Reached only when nothing more specific matches — the events, workshops and
 * speaker archives all have templates of their own.
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');

$heading = is_search()
    ? sprintf(sc_t('frontend.results_for', 'Results for "%s"'), get_search_query())
    : (is_archive() ? get_the_archive_title() : sc_t('frontend.latest', 'Latest'));
?>

<div class="w-doc">
    <header class="w-page-head" style="margin-bottom:0;padding-inline:0">
        <h1><?php echo wp_kses_post($heading); ?></h1>
    </header>

    <?php if (have_posts()): ?>
    <div class="w-list">
        <?php while (have_posts()) : the_post(); ?>
        <a class="w-list__item" href="<?php the_permalink(); ?>">
            <span class="w-list__title"><?php the_title(); ?></span>
            <span class="w-doc__meta"><?php echo esc_html(get_the_date()); ?></span>
            <?php if (has_excerpt()): ?>
                <p class="w-list__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
            <?php endif; ?>
        </a>
        <?php endwhile; ?>
    </div>

    <?php the_posts_pagination([
        'mid_size'  => 1,
        'prev_text' => esc_html(sc_t('frontend.previous', 'Previous')),
        'next_text' => esc_html(sc_t('frontend.next', 'Next')),
    ]); ?>

    <?php else: ?>
    <div class="w-empty">
        <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.nothing_here', 'Nothing here')); ?></span>
        <p><?php echo esc_html(sc_t('frontend.nothing_here_lede', 'Try the programme instead.')); ?></p>
        <a class="w-btn" href="<?php echo esc_url(home_url('/events/')); ?>">
            <?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
