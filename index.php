<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');
?>

<!-- Inner Page Header -->
<div class="inner-page-header">
	<div class="container">
		<div class="row">
			<div class="col-lg-6 m-auto">
				<div class="heading1 text-center">
					<h1>Blog</h1>
					<div class="space20"></div>
					<a href="<?php echo esc_url(home_url('/')); ?>">Home <i class="fa-solid fa-angle-right"></i> <span>Blog</span></a>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Index Content -->
<section class="sc-index-content">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-10">
				<?php if (have_posts()) : ?>
					<div class="sc-posts-masonry">
						<?php
						$post_count = 0;
						while (have_posts()) :
							the_post();
							$post_count++;

							// First post is featured
							$is_featured = ($post_count === 1);
							$card_class = $is_featured ? 'sc-post-card-vertical sc-featured-post' : 'sc-post-card-vertical';

							// Get post data
							$categories = get_the_category();
							$author_name = get_the_author();
							$post_date = get_the_date('M j, Y');
							$reading_time = ceil(str_word_count(get_the_content()) / 200);
							?>

							<article class="<?php echo esc_attr($card_class); ?>">
								<?php if (has_post_thumbnail()) : ?>
									<div class="sc-post-card-thumb">
										<a href="<?php the_permalink(); ?>">
											<?php the_post_thumbnail('large'); ?>
										</a>
										<div class="sc-post-card-date">
											<i class="fa fa-calendar"></i> <?php echo esc_html($post_date); ?>
										</div>
									</div>
								<?php endif; ?>

								<div class="sc-post-card-body">
									<?php if (!empty($categories)) : ?>
										<a href="<?php echo esc_url(get_category_link($categories[0]->term_id)); ?>" class="sc-post-card-category">
											<?php echo esc_html($categories[0]->name); ?>
										</a>
									<?php endif; ?>

									<h2 class="sc-post-card-title">
										<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
									</h2>

									<div class="sc-post-card-excerpt">
										<?php
										if ($is_featured) {
											echo wp_trim_words(get_the_excerpt(), 30, '...');
										} else {
											echo wp_trim_words(get_the_excerpt(), 20, '...');
										}
										?>
									</div>

									<div class="sc-post-card-footer">
										<a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>" class="sc-post-author-mini">
											<div class="sc-author-avatar-mini">
												<?php echo get_avatar(get_the_author_meta('ID'), 35); ?>
											</div>
											<span class="sc-author-name"><?php echo esc_html($author_name); ?></span>
										</a>
										<span class="sc-post-read-time">
											<i class="fa fa-clock"></i> <?php echo esc_html($reading_time); ?> min
										</span>
									</div>
								</div>
							</article>

						<?php endwhile; ?>
					</div>

					<!-- Pagination -->
					<?php
					$pagination = paginate_links(array(
						'mid_size' => 2,
						'prev_text' => '<i class="fa fa-chevron-left"></i> Previous',
						'next_text' => 'Next <i class="fa fa-chevron-right"></i>',
						'type' => 'array',
					));

					if ($pagination) :
					?>
						<div class="sc-load-more">
							<nav class="sc-pagination">
								<div class="nav-links">
									<?php foreach ($pagination as $page) : ?>
										<?php echo $page; ?>
									<?php endforeach; ?>
								</div>
							</nav>
						</div>
					<?php endif; ?>

				<?php else : ?>
					<div class="sc-no-posts">
						<i class="fa fa-inbox"></i>
						<h3>No Posts Found</h3>
						<p>Sorry, no posts were found. Please check back later for new content.</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_template_part('template-parts/public/footer', 'public');
