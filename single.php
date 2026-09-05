<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package sc_events
 */

get_template_part('template-parts/public/header', 'public');
?>

<?php while ( have_posts() ) : the_post(); ?>
	<!-- Inner Page Header -->
	<div class="inner-page-header">
		<div class="container">
			<div class="row">
				<div class="col-lg-6 m-auto">
					<div class="heading1 text-center">
						<h1><?php the_title(); ?></h1>
						<div class="space20"></div>
						<a href="<?php echo esc_url(home_url('/')); ?>">Home <i class="fa-solid fa-angle-right"></i> <span><?php the_title(); ?></span></a>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endwhile; ?>

<!-- Single Post Content -->
<section class="sc-single-content">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-10">
				<?php while ( have_posts() ) : the_post(); ?>

					<?php if (has_post_thumbnail()) : ?>
						<div class="sc-post-featured-image">
							<?php the_post_thumbnail('full'); ?>
						</div>
					<?php endif; ?>

					<article class="sc-post-article">
						<?php the_content(); ?>

						<?php
						wp_link_pages(
							array(
								'before' => '<div class="page-links">' . esc_html__('Pages:', 'sc_events'),
								'after'  => '</div>',
							)
						);
						?>

						<!-- Post Footer with Tags -->
						<?php $tags = get_the_tags(); ?>
						<?php if ($tags) : ?>
							<div class="sc-post-footer">
								<div class="sc-post-tags">
									<?php foreach ($tags as $tag) : ?>
										<a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="sc-post-tag">
											<i class="fa fa-tag"></i> <?php echo esc_html($tag->name); ?>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					</article>

					<!-- Author Box -->
					<?php
					$author_bio = get_the_author_meta('description');
					if ($author_bio) :
					?>
						<div class="sc-author-box">
							<div class="sc-author-avatar">
								<?php echo get_avatar(get_the_author_meta('ID'), 120); ?>
							</div>
							<div class="sc-author-info">
								<h3><?php echo esc_html(get_the_author()); ?></h3>
								<p class="sc-author-bio"><?php echo esc_html($author_bio); ?></p>
							</div>
						</div>
					<?php endif; ?>

					<!-- Post Navigation -->
					<?php
					$prev_post = get_previous_post();
					$next_post = get_next_post();
					if ($prev_post || $next_post) :
					?>
						<div class="sc-post-navigation">
							<?php if ($prev_post) : ?>
								<a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" class="sc-nav-post prev">
									<?php if (has_post_thumbnail($prev_post->ID)) : ?>
										<div class="sc-nav-thumb">
											<?php echo get_the_post_thumbnail($prev_post->ID, 'thumbnail'); ?>
										</div>
									<?php endif; ?>
									<div class="sc-nav-info">
										<span class="sc-nav-label">
											<i class="fa fa-arrow-left"></i> Previous Post
										</span>
										<span class="sc-nav-title"><?php echo esc_html($prev_post->post_title); ?></span>
									</div>
								</a>
							<?php endif; ?>

							<?php if ($next_post) : ?>
								<a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" class="sc-nav-post next">
									<div class="sc-nav-info">
										<span class="sc-nav-label">
											Next Post <i class="fa fa-arrow-right"></i>
										</span>
										<span class="sc-nav-title"><?php echo esc_html($next_post->post_title); ?></span>
									</div>
									<?php if (has_post_thumbnail($next_post->ID)) : ?>
										<div class="sc-nav-thumb">
											<?php echo get_the_post_thumbnail($next_post->ID, 'thumbnail'); ?>
										</div>
									<?php endif; ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Comments Section -->
					<?php if (comments_open() || get_comments_number()) : ?>
						<div class="sc-comments-area">
							<h3 class="sc-comments-title">
								<i class="fa fa-comments"></i>
								<?php
								$comments_number = get_comments_number();
								if ($comments_number == 0) {
									echo 'No Comments';
								} elseif ($comments_number == 1) {
									echo '1 Comment';
								} else {
									echo $comments_number . ' Comments';
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
