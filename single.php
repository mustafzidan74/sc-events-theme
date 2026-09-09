<?php
/**
 * Single post.
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');
?>

<?php while (have_posts()) : the_post(); ?>
<article class="w-doc">
    <header class="w-page-head" style="margin-bottom:0;padding-inline:0">
        <h1><?php the_title(); ?></h1>
        <p class="w-doc__meta"><?php echo esc_html(get_the_date()); ?></p>
    </header>

    <?php if (has_post_thumbnail()): ?>
        <div class="w-gallery__shot" style="aspect-ratio:16/9">
            <?php the_post_thumbnail('large', ['loading' => 'lazy', 'decoding' => 'async']); ?>
        </div>
    <?php endif; ?>

    <div class="w-prose">
        <?php
        the_content();
        wp_link_pages(['before' => '<nav class="w-pagination">', 'after' => '</nav>']);
        ?>
    </div>
</article>
<?php endwhile; ?>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
