<?php
/**
 * Public Frontend Footer - Dark & Premium Design
 *
 * @package sc_events
 * @version 2.0.0
 */

// Get platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_description = get_option('sc_platform_description', get_bloginfo('description'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'full') : '';
$platform_logo_light_id = get_option('sc_platform_logo_light');
$platform_logo_light_url = $platform_logo_light_id ? wp_get_attachment_image_url($platform_logo_light_id, 'full') : '';

// Contact info
$platform_phone = get_option('sc_platform_phone', '');
$platform_email = get_option('sc_platform_email', get_option('admin_email'));
$platform_whatsapp = get_option('sc_platform_whatsapp', '');
$platform_address = get_option('sc_platform_address', '');

// Social media
$platform_facebook = get_option('sc_platform_facebook', '');
$platform_twitter = get_option('sc_platform_twitter', '');
$platform_instagram = get_option('sc_platform_instagram', '');
$platform_linkedin = get_option('sc_platform_linkedin', '');

// Assets URL
$assets_url = get_template_directory_uri() . '/assets/frontend/';
?>

<!--===== FOOTER =======-->
<footer class="sc-footer">
    <!-- Gold top border -->
    <div class="sc-footer-border"></div>

    <div class="sc-footer-main">
        <div class="container">
            <div class="row">
                <!-- Column 1: Logo & About -->
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <div class="sc-footer-brand">
                        <?php if ($platform_logo_url): ?>
                            <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" class="sc-footer-logo sc-logo-dark">
                            <?php if ($platform_logo_light_url): ?>
                                <img src="<?php echo esc_url($platform_logo_light_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" class="sc-footer-logo sc-logo-light">
                            <?php endif; ?>
                        <?php else: ?>
                            <h3 class="sc-footer-title"><?php echo esc_html($platform_name); ?></h3>
                        <?php endif; ?>
                        <p class="sc-footer-desc sc-footer-desc-lg"><?php echo esc_html($platform_description); ?></p>
                    </div>
                </div>

                <!-- Column 2: Quick Links -->
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                    <div class="sc-footer-widget">
                        <h5 class="sc-footer-heading"><?php echo esc_html(sc_t('frontend.quick_links', 'Quick Links')); ?></h5>
                        <ul class="sc-footer-links">
                            <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sc_t('frontend.home', 'Home')); ?></a></li>
                            <li><a href="<?php echo esc_url(home_url('/events/')); ?>"><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></a></li>
                            <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html(sc_t('frontend.contact', 'Contact')); ?></a></li>
                            <?php if (is_user_logged_in()): ?>
                            <li><a href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php echo esc_html(sc_t('frontend.my_account', 'My Account')); ?></a></li>
                            <?php else: ?>
                            <li><a href="<?php echo esc_url(home_url('/login/')); ?>"><?php echo esc_html(sc_t('frontend.login', 'Login')); ?></a></li>
                            <li><a href="<?php echo esc_url(home_url('/register/')); ?>"><?php echo esc_html(sc_t('frontend.register', 'Register')); ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Column 3: Contact -->
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="sc-footer-widget">
                        <h5 class="sc-footer-heading"><?php echo esc_html(sc_t('frontend.contact_us', 'Contact Us')); ?></h5>
                        <ul class="sc-footer-contact">
                            <?php if ($platform_phone): ?>
                            <li>
                                <i class="fa-solid fa-phone"></i>
                                <a href="tel:<?php echo esc_attr($platform_phone); ?>"><?php echo esc_html($platform_phone); ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($platform_email): ?>
                            <li>
                                <i class="fa-solid fa-envelope"></i>
                                <a href="mailto:<?php echo esc_attr($platform_email); ?>"><?php echo esc_html($platform_email); ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($platform_address): ?>
                            <li>
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?php echo esc_html($platform_address); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Column 4: Newsletter / Extra -->
                <div class="col-lg-3 col-md-6">
                    <div class="sc-footer-widget">
                        <h5 class="sc-footer-heading"><?php echo esc_html(sc_t('frontend.stay_updated', 'Stay Updated')); ?></h5>
                        <p class="sc-footer-desc"><?php echo esc_html(sc_t('frontend.follow_social_media', 'Follow us on social media for the latest events and updates.')); ?></p>
                        <div class="sc-social-links" style="margin: var(--sc-space-3) 0;">
                            <?php if ($platform_facebook): ?>
                            <a href="<?php echo esc_url($platform_facebook); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-facebook-f"></i></a>
                            <?php endif; ?>
                            <?php if ($platform_instagram): ?>
                            <a href="<?php echo esc_url($platform_instagram); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-instagram"></i></a>
                            <?php endif; ?>
                            <?php if ($platform_linkedin): ?>
                            <a href="<?php echo esc_url($platform_linkedin); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-linkedin-in"></i></a>
                            <?php endif; ?>
                            <?php if ($platform_twitter): ?>
                            <a href="<?php echo esc_url($platform_twitter); ?>" target="_blank" class="sc-social-link"><i class="fa-brands fa-twitter"></i></a>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-btn sc-btn-outline sc-btn-sm">
                            <?php echo esc_html(sc_t('frontend.browse_events', 'Browse Events')); ?> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="sc-footer-bottom">
        <div class="container">
            <div class="sc-footer-bottom-inner">
                <p>&copy; <?php echo date('Y'); ?> All rights reserved for <a href="https://eventsorganize.online/" target="_blank" rel="noopener">eventsorganize</a></p>
            </div>
        </div>
    </div>
</footer>

<style>
.sc-footer-desc-lg { font-size: 1rem; line-height: 1.7; }
.sc-footer-bottom-inner a { color: inherit; text-decoration: underline; }
.sc-footer-bottom-inner a:hover { opacity: 0.8; }
</style>

<?php if ($platform_whatsapp): ?>
<!--===== WHATSAPP BUTTON =======-->
<div class="whatsapp-float">
    <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $platform_whatsapp)); ?>"
       target="_blank"
       rel="noopener noreferrer"
       title="<?php echo esc_attr(sc_t('frontend.chat_on_whatsapp', 'Chat on WhatsApp')); ?>">
        <i class="fa-brands fa-whatsapp"></i>
    </a>
</div>
<?php endif; ?>

<!--===== BACK TO TOP =======-->
<div id="back-to-top" title="Back to top">
    <a href="#" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>
</div>
<style>
#back-to-top {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 1000 !important;
    display: none;
    width: 50px !important;
    height: 50px !important;
}
@media (max-width: 768px) { #back-to-top { right: 16px !important; } }
#back-to-top a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 50px !important;
    height: 50px !important;
    background: var(--sc-primary, #7c1314) !important;
    color: #fff !important;
    border-radius: 50% !important;
    text-decoration: none !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    cursor: pointer;
    position: relative !important;
    overflow: hidden;
}
#back-to-top a:after,
#back-to-top a:before { display: none !important; content: none !important; }
#back-to-top a i { font-size: 18px !important; color: #fff !important; line-height: 1 !important; }
#back-to-top a:hover { background: var(--sc-primary, #7c1314) !important; opacity: 0.9; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('back-to-top');
    if (!btn) return;
    var link = btn.querySelector('a');
    window.addEventListener('scroll', function() {
        btn.style.display = window.scrollY > 500 ? 'block' : 'none';
    });
    if (link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>

<!--===== JS =======-->
<script src="<?php echo esc_url($assets_url); ?>js/eventify/vendor/bootstrap.min.js"></script>
<script src="<?php echo esc_url($assets_url); ?>js/eventify/vendor/fontawesome.js"></script>

<!-- AOS Animation -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<!-- Fancybox -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

<!-- Shared Utilities -->
<script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/shared-utilities.js?v=<?php echo defined('_S_VERSION') ? _S_VERSION : '1.0.0'; ?>"></script>

<!-- Public Config -->
<script>
var scPublic = {
    ajaxurl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('sc_public_nonce')); ?>',
    homeUrl: '<?php echo esc_url(home_url('/')); ?>',
    isLoggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
    i18n: {
        loading: '<?php echo esc_js(sc_t('frontend.loading', 'Loading...')); ?>',
        error: '<?php echo esc_js(sc_t('frontend.error_occurred', 'An error occurred')); ?>',
        success: '<?php echo esc_js(sc_t('frontend.success', 'Success!')); ?>',
        confirm: '<?php echo esc_js(sc_t('frontend.are_you_sure', 'Are you sure?')); ?>',
        yes: '<?php echo esc_js(sc_t('frontend.yes', 'Yes')); ?>',
        no: '<?php echo esc_js(sc_t('frontend.no', 'No')); ?>',
        loginRequired: '<?php echo esc_js(sc_t('frontend.login_required', 'Please login to continue')); ?>',
        networkError: '<?php echo esc_js(sc_t('frontend.network_error', 'Could not connect to server. Please check your internet connection.')); ?>',
        timeoutError: '<?php echo esc_js(sc_t('frontend.timeout_error', 'The request timed out. Please try again.')); ?>',
        serverError: '<?php echo esc_js(sc_t('frontend.server_error', 'Server error occurred. Please try again later.')); ?>',
        permissionError: '<?php echo esc_js(sc_t('frontend.permission_error', 'You do not have permission to perform this action.')); ?>',
        rateLimitError: '<?php echo esc_js(sc_t('frontend.rate_limit_error', 'Too many requests. Please wait a moment.')); ?>',
        registerNow: '<?php echo esc_js(sc_t('frontend.register_now', 'Register Now')); ?>',
        proceedToPayment: '<?php echo esc_js(sc_t('frontend.proceed_to_payment', 'Proceed to Payment')); ?>',
        apply: '<?php echo esc_js(sc_t('frontend.apply', 'Apply')); ?>',
        verify: '<?php echo esc_js(sc_t('frontend.verify', 'Verify')); ?>',
        free: '<?php echo esc_js(sc_t('frontend.free', 'FREE')); ?>',
        enterCoupon: '<?php echo esc_js(sc_t('frontend.enter_coupon', 'Please enter a coupon or serial number')); ?>',
        verifyCouponFirst: '<?php echo esc_js(sc_t('frontend.verify_coupon_first', 'Please verify your coupon first')); ?>',
        registering: '<?php echo esc_js(sc_t('frontend.registering', 'Registering...')); ?>',
        couponNotFull: '<?php echo esc_js(sc_t('frontend.coupon_not_full', 'This coupon does not provide full access. A 100% discount coupon is required.')); ?>'
    },
    currency: <?php echo wp_json_encode(function_exists('sc_get_currency_settings') ? sc_get_currency_settings() : array('symbol' => 'EGP', 'position' => 'after', 'decimal_places' => 0, 'thousand_separator' => ',', 'decimal_separator' => '.')); ?>,
    defaultTheme: '<?php echo esc_js(get_option('sc_default_theme', 'dark')); ?>'
};
</script>

<!-- Public Frontend JS -->
<script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/js/public-scripts.js?v=<?php echo defined('_S_VERSION') ? _S_VERSION : '1.0.0'; ?>"></script>

<!-- Dark Theme Init -->
<script>
(function($) {
    'use strict';

    // Preloader
    $(window).on('load', function() {
        setTimeout(function() {
            $('.preloader').addClass('loaded');
        }, 500);
    });

    // Header scroll behavior
    $(window).on('scroll', function() {
        var header = $('#sc-header');
        if ($(this).scrollTop() > 50) {
            header.addClass('scrolled');
        } else {
            header.removeClass('scrolled');
        }

        // Progress circle
        var scrollTop = $(this).scrollTop();
        var docHeight = $(document).height() - $(window).height();
        var scrollPercent = scrollTop / docHeight;
        var path = $('.progress-circle path')[0];
        if (path) {
            var pathLength = path.getTotalLength();
            path.style.strokeDasharray = pathLength;
            path.style.strokeDashoffset = pathLength - (scrollPercent * pathLength);
        }

        if (scrollTop > 300) {
            $('.progress-wrap').fadeIn(300);
        } else {
            $('.progress-wrap').fadeOut(300);
        }
    });

    // Scroll to top
    $('.progress-wrap').on('click', function() {
        $('html, body').animate({ scrollTop: 0 }, 600);
    });

    // Mobile sidebar
    $('#sc-hamburger').on('click', function() {
        $(this).toggleClass('active');
        $('#sc-mobile-sidebar').addClass('active');
        $('#sc-mobile-overlay').addClass('active');
        $('body').css('overflow', 'hidden');
    });

    function closeMobileSidebar() {
        $('#sc-hamburger').removeClass('active');
        $('#sc-mobile-sidebar').removeClass('active');
        $('#sc-mobile-overlay').removeClass('active');
        $('body').css('overflow', '');
    }

    $('#sc-mobile-close, #sc-mobile-overlay').on('click', closeMobileSidebar);

    // Mobile submenu toggle
    $('.sc-has-submenu').on('click', function(e) {
        e.preventDefault();
        $(this).next('.sub-menu').toggleClass('open');
        $(this).find('i').toggleClass('fa-angle-down fa-angle-up');
    });

    // Theme Toggle
    (function() {
        var defaultTheme = (typeof scPublic !== 'undefined' && scPublic.defaultTheme) ? scPublic.defaultTheme : 'dark';

        function applyTheme(theme) {
            $('body').removeClass('sc-dark-theme sc-light-theme').addClass('sc-' + theme + '-theme');
            $('html').removeClass('sc-dark-theme sc-light-theme').addClass('sc-' + theme + '-theme');
        }

        // Apply saved theme on DOM ready
        var saved = null;
        try { saved = localStorage.getItem('sc_theme'); } catch(e) {}
        if (saved) {
            applyTheme(saved);
        }

        // Toggle click handler (desktop + mobile)
        $(document).on('click', '.sc-theme-toggle', function() {
            var current = $('body').hasClass('sc-light-theme') ? 'light' : 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try { localStorage.setItem('sc_theme', next); } catch(e) {}
        });
    })();

    // AOS Init
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 50
        });
    }

    // Fancybox Init
    if (typeof Fancybox !== 'undefined') {
        Fancybox.bind('[data-fancybox]', {
            Toolbar: {
                display: ['close'],
            },
            contentClick: 'close',
        });
    }

})(jQuery);
</script>

<?php wp_footer(); ?>
</body>
</html>
