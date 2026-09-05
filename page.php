<?php
/**
 * The template for displaying all pages
 *
 * Default page template - Dark & Premium theme.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package sc_events
 * @version 5.1.0
 */

get_template_part('template-parts/public/header', 'public');
?>

<?php while ( have_posts() ) : the_post(); ?>
<!-- Page Header -->
<section class="sc-page-header">
    <div class="container">
        <nav class="sc-breadcrumb" data-aos="fade-up">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php the_title(); ?></span>
        </nav>
        <h1 data-aos="fade-up" data-aos-delay="100"><?php the_title(); ?></h1>
    </div>
</section>
<?php endwhile; ?>

<!-- Page Content -->
<section class="sc-page-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php while ( have_posts() ) : the_post(); ?>

                    <article id="post-<?php the_ID(); ?>" <?php post_class('sc-page-article glass-card-static'); ?>>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="sc-page-featured-image">
                                <?php the_post_thumbnail('full'); ?>
                            </div>
                        <?php endif; ?>

                        <?php the_content(); ?>

                        <?php
                        wp_link_pages(
                            array(
                                'before' => '<div class="page-links">' . esc_html__('Pages:', 'sc_events'),
                                'after'  => '</div>',
                            )
                        );
                        ?>
                    </article>

                    <!-- Comments Section -->
                    <?php if (comments_open() || get_comments_number()) : ?>
                        <div class="sc-comments-area glass-card-static" style="margin-top: 30px; padding: 40px;">
                            <h3 class="sc-comments-title">
                                <i class="fa fa-comments"></i>
                                <?php
                                $comments_number = get_comments_number();
                                if ($comments_number == 0) {
                                    esc_html_e('No Comments', 'sc_events');
                                } elseif ($comments_number == 1) {
                                    esc_html_e('1 Comment', 'sc_events');
                                } else {
                                    printf(esc_html__('%d Comments', 'sc_events'), $comments_number);
                                }
                                ?>
                            </h3>
                            <?php comments_template(); ?>
                        </div>
                    <?php endif; ?>

                <?php endwhile; ?>
            </div>
        </div>
    </div>
</section>

<?php
get_template_part('template-parts/public/footer', 'public');
