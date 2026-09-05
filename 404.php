<?php
/**
 * 404 Page - Not Found
 * Dark & Premium Design
 *
 * @package sc_events
 * @version 5.0.0
 */

// Load header
get_template_part('template-parts/public/header', 'public');

// Get platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$assets_url = get_template_directory_uri() . '/assets/frontend/';
?>

<!-- 404 Section -->
<section class="sc-404-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9" data-aos="fade-up" data-aos-duration="800">
                <div class="sc-404-content">

                    <!-- Animated 404 Number -->
                    <div class="sc-404-number">404</div>

                    <!-- Icon -->
                    <div class="sc-404-icon">
                        <i class="fa-solid fa-compass"></i>
                    </div>

                    <h2 class="sc-404-title"><?php esc_html_e('Page Not Found', 'sc_events'); ?></h2>
                    <p class="sc-404-desc"><?php esc_html_e('Sorry, the page you are looking for does not exist or has been moved.', 'sc_events'); ?></p>

                    <!-- Buttons -->
                    <div class="sc-404-buttons">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="sc-btn sc-btn-gold">
                            <i class="fa-solid fa-house"></i>
                            <?php esc_html_e('Back to Home', 'sc_events'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-outline">
                            <i class="fa-solid fa-calendar-days"></i>
                            <?php esc_html_e('Browse Events', 'sc_events'); ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- 404 Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/404-page.css">

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
