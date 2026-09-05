<?php
/**
 * The template for displaying archive pages
 *
 * @package SC_Events
 */

get_template_part('template-parts/public/header', 'public');

// Get platform colors
$primary_color = get_option('sc_primary_color', '#FF4B36');
$secondary_color = get_option('sc_secondary_color', '#1B1E4A');
?>

<!-- Inner Page Header -->
<div class="inner-page-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 m-auto">
                <div class="heading1 text-center">
                    <?php
                    if (is_category()) {
                        echo '<h1>' . single_cat_title('', false) . '</h1>';
                    } elseif (is_tag()) {
                        echo '<h1>' . single_tag_title('', false) . '</h1>';
                    } elseif (is_author()) {
                        echo '<h1>' . get_the_author() . '</h1>';
                    } elseif (is_date()) {
                        echo '<h1>' . get_the_date('F Y') . '</h1>';
                    } else {
                        echo '<h1>Archives</h1>';
                    }
                    ?>
                    <div class="space20"></div>
                    <a href="<?php echo esc_url(home_url('/')); ?>">Home <i class="fa-solid fa-angle-right"></i>
                        <span>
                            <?php
                            if (is_category()) {
                                echo single_cat_title('', false);
                            } elseif (is_tag()) {
                                echo single_tag_title('', false);
                            } elseif (is_author()) {
                                echo get_the_author();
                            } elseif (is_date()) {
                                echo get_the_date('F Y');
                            } else {
                                echo 'Archives';
                            }
                            ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Archive Content -->
<div class="sc-archive-content">
    <div class="container">
        <div class="row justify-content-center">
            <!-- Main Content -->
            <div class="col-lg-10">
                <?php if (have_posts()) : ?>
                    <div class="sc-posts-grid">
                        <?php while (have_posts()) : the_post(); ?>
                            <article id="post-<?php the_ID(); ?>" <?php post_class('sc-post-card'); ?>>
                                <?php if (has_post_thumbnail()) : ?>
                                    <div class="sc-post-thumbnail">
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_post_thumbnail('medium_large'); ?>
                                        </a>
                                        <div class="sc-post-date">
                                            <span class="day"><?php echo get_the_date('d'); ?></span>
                                            <span class="month"><?php echo get_the_date('M'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="sc-post-content">
                                    <div class="sc-post-meta">
                                        <span class="sc-post-category">
                                            <i class="fa-solid fa-folder"></i>
                                            <?php
                                            $categories = get_the_category();
                                            if (!empty($categories)) {
                                                echo '<a href="' . esc_url(get_category_link($categories[0]->term_id)) . '">' . esc_html($categories[0]->name) . '</a>';
                                            }
                                            ?>
                                        </span>
                                        <span class="sc-post-author">
                                            <i class="fa-solid fa-user"></i>
                                            <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>">
                                                <?php the_author(); ?>
                                            </a>
                                        </span>
                                        <span class="sc-post-comments">
                                            <i class="fa-solid fa-comments"></i>
                                            <?php comments_number('0 Comments', '1 Comment', '% Comments'); ?>
                                        </span>
                                    </div>

                                    <h2 class="sc-post-title">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h2>

                                    <div class="sc-post-excerpt">
                                        <?php the_excerpt(); ?>
                                    </div>

                                    <a href="<?php the_permalink(); ?>" class="sc-read-more">
                                        Read More <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="sc-pagination">
                        <?php
                        the_posts_pagination(array(
                            'mid_size' => 2,
                            'prev_text' => '<i class="fa-solid fa-chevron-left"></i> Previous',
                            'next_text' => 'Next <i class="fa-solid fa-chevron-right"></i>',
                        ));
                        ?>
                    </div>

                <?php else : ?>
                    <div class="sc-no-posts">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>No posts found</h3>
                        <p>Sorry, but nothing matched your criteria. Please try a different search.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
get_template_part('template-parts/public/footer', 'public');
?>
