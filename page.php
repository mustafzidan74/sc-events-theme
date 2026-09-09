<?php
/**
 * Default page.
 *
 * Privacy, terms and anything else with no template of its own. A narrow
 * measure, because these are read rather than scanned.
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');
?>

<?php while (have_posts()) : the_post(); ?>
<article class="w-doc">
    <header class="w-page-head" style="margin-bottom:0;padding-inline:0">
        <h1><?php the_title(); ?></h1>
        <?php if (get_the_modified_date()): ?>
        <p class="w-doc__meta"><?php printf(
            esc_html(sc_t('frontend.last_updated', 'Last updated %s')),
            esc_html(get_the_modified_date())
        ); ?></p>
        <?php endif; ?>
    </header>

    <div class="w-prose">
        <?php
        the_content();
        wp_link_pages(['before' => '<nav class="w-pagination">', 'after' => '</nav>']);
        ?>
    </div>
</article>
<?php endwhile; ?>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
