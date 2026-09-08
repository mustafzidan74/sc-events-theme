<?php
/**
 * Public Frontend Header - Dark & Premium Design
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

// Social media
$platform_facebook = get_option('sc_platform_facebook', '');
$platform_twitter = get_option('sc_platform_twitter', '');
$platform_instagram = get_option('sc_platform_instagram', '');
$platform_linkedin = get_option('sc_platform_linkedin', '');

// Theme default
$default_theme = get_option('sc_default_theme', 'dark');

// Assets URL
$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Current user
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

// Categories for dropdown (cached with transient)
$categories = function_exists('sc_get_cached_event_categories') ? sc_get_cached_event_categories(10) : get_terms(array(
    'taxonomy' => 'sc_event_category',
    'hide_empty' => false,
    'number' => 10
));
?>
<!DOCTYPE html>
<html <?php echo sc_html_attrs(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo esc_attr($platform_description); ?>">

    <title><?php wp_title('|', true, 'right'); ?><?php echo esc_html($platform_name); ?></title>

    <?php if ($platform_logo_url): ?>
    <link rel="icon" href="<?php echo esc_url($platform_logo_url); ?>" type="image/png">
    <?php endif; ?>

    <!-- Preconnect to CDN domains for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php if (sc_is_rtl()): ?>
    <!-- Arabic Font -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>

    <!-- Redesign: typefaces from the design system (Inter for Latin, IBM Plex
         Sans Arabic for Arabic). Tajawal above stays until the last legacy
         stylesheet is gone. -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Redesign: design tokens. Custom properties only — declares no rules of
         its own, so loading it first is safe on pages not yet rebuilt. -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/tokens.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/eventify/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/eventify/vendor/fontawesome.css">

    <?php if (sc_is_rtl()): ?>
    <!-- Bootstrap RTL Override -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/admin-dashboard/vendor/bootstrap/css/bootstrap.min.rtl.css">
    <?php endif; ?>

    <!-- CSS Variables & Dark Theme -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/variables.css">
    <?php
    // Dynamic colors from dashboard (overrides CSS defaults)
    require_once get_template_directory() . '/inc/public-frontend/frontend-dynamic-colors.php';
    ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/sc-theme.css">

    <!-- Component Styles -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/header-styles.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/auth-pages.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Fancybox CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">

    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">

    <!-- jQuery -->
    <script src="<?php echo esc_url($assets_url); ?>js/eventify/vendor/jquery-3.7.1.min.js"></script>

    <!-- Light Theme Overrides -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/light-theme.css">

    <?php if (sc_is_rtl()): ?>
    <!-- RTL Overrides -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/frontend-rtl.css">
    <?php endif; ?>

    <!-- Prevent FOUC: apply saved theme before paint -->
    <script>
    (function(){
        var saved = null;
        try { saved = localStorage.getItem('sc_theme'); } catch(e) {}
        var theme = saved || '<?php echo esc_js($default_theme); ?>';
        document.documentElement.classList.add('sc-' + theme + '-theme');
    })();
    </script>

    <?php wp_head(); ?>
</head>

<body <?php body_class(array_merge(['sc-' . esc_attr($default_theme) . '-theme'], sc_body_classes())); ?>>
<?php wp_body_open(); ?>

<!--===== GRADIENT ORBS BACKGROUND =======-->
<div class="sc-orbs-container" aria-hidden="true">
    <div class="sc-orb sc-orb-1"></div>
    <div class="sc-orb sc-orb-2"></div>
    <div class="sc-orb sc-orb-3"></div>
</div>

<!--===== PRELOADER =======-->
<div class="preloader">
    <div class="loading-container">
        <div class="loading"></div>
        <div id="loading-icon">
            <?php if ($platform_logo_url): ?>
                <img class="sc-logo-dark" src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
                <?php if ($platform_logo_light_url): ?>
                    <img class="sc-logo-light" src="<?php echo esc_url($platform_logo_light_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!--===== PROGRESS INDICATOR =======-->
<div class="paginacontainer">
    <div class="progress-wrap">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>
</div>

<!--===== HEADER =======-->
<header class="sc-header" id="sc-header">
    <div class="sc-header-inner">
        <div class="container">
            <!-- Logo -->
            <div class="sc-header-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <?php if ($platform_logo_url): ?>
                        <img class="sc-logo-dark" src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
                        <?php if ($platform_logo_light_url): ?>
                            <img class="sc-logo-light" src="<?php echo esc_url($platform_logo_light_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="logo-text"><?php echo esc_html($platform_name); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="sc-nav">
                <ul class="sc-nav-list">
                    <li class="sc-nav-item">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="sc-nav-link"><?php echo esc_html(sc_t('frontend.home', 'Home')); ?></a>
                    </li>
                    <li class="sc-nav-item">
                        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="sc-nav-link"><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></a>
                    </li>
                    <li class="sc-nav-item">
                        <a href="<?php echo esc_url(home_url('/workshops/')); ?>" class="sc-nav-link"><?php echo esc_html(sc_t('frontend.workshops', 'Workshops')); ?></a>
                    </li>
                    <?php if (!is_wp_error($categories) && !empty($categories)): ?>
                    <li class="sc-nav-item">
                        <a href="#" class="sc-nav-link"><?php echo esc_html(sc_t('frontend.categories', 'Categories')); ?> <i class="fa-solid fa-angle-down"></i></a>
                        <ul class="sc-nav-dropdown">
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="sc-nav-item">
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="sc-nav-link"><?php echo esc_html(sc_t('frontend.contact', 'Contact')); ?></a>
                    </li>
                </ul>
            </nav>

            <!-- Header Actions -->
            <div class="sc-header-actions">
                <!-- Theme Toggle -->
                <button class="sc-theme-toggle" id="sc-theme-toggle" title="<?php esc_attr_e('Toggle theme', 'sc_events'); ?>" aria-label="<?php esc_attr_e('Toggle light/dark theme', 'sc_events'); ?>">
                    <i class="fa-solid fa-sun sc-theme-icon-light"></i>
                    <i class="fa-solid fa-moon sc-theme-icon-dark"></i>
                </button>

                <?php if ($is_logged_in): ?>
                    <a href="<?php echo esc_url(home_url('/my-account/#favorites')); ?>" class="sc-header-favorites" id="sc-header-favorites" title="<?php esc_attr_e('My Favorites', 'sc_events'); ?>">
                        <i class="fa-solid fa-heart"></i>
                        <span class="sc-favorites-badge" id="sc-favorites-badge" style="display:none;">0</span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="sc-header-btn">
                        <i class="fa-solid fa-user"></i>
                        <?php echo esc_html(sc_t('frontend.my_account', 'My Account')); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(home_url('/login/')); ?>" class="sc-header-btn">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?php echo esc_html(sc_t('frontend.login', 'Login')); ?>
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger -->
                <div class="sc-hamburger" id="sc-hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
</header>

<!--===== MOBILE SIDEBAR =======-->
<div class="sc-mobile-overlay" id="sc-mobile-overlay"></div>
<div class="sc-mobile-sidebar" id="sc-mobile-sidebar">
    <div class="sc-mobile-sidebar-header">
        <?php if ($platform_logo_url): ?>
            <img class="sc-logo-dark" src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php if ($platform_logo_light_url): ?>
                <img class="sc-logo-light" src="<?php echo esc_url($platform_logo_light_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php endif; ?>
        <?php else: ?>
            <span style="color: var(--sc-text-primary); font-weight: 700;"><?php echo esc_html($platform_name); ?></span>
        <?php endif; ?>
        <div style="display:flex; align-items:center; gap:8px;">
            <button class="sc-theme-toggle sc-theme-toggle-mobile" title="<?php esc_attr_e('Toggle theme', 'sc_events'); ?>" aria-label="<?php esc_attr_e('Toggle light/dark theme', 'sc_events'); ?>">
                <i class="fa-solid fa-sun sc-theme-icon-light"></i>
                <i class="fa-solid fa-moon sc-theme-icon-dark"></i>
            </button>
            <button class="sc-mobile-close" id="sc-mobile-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <nav class="sc-mobile-nav">
        <ul class="sc-mobile-nav-list">
            <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sc_t('frontend.home', 'Home')); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/events/')); ?>"><?php echo esc_html(sc_t('frontend.events', 'Events')); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/workshops/')); ?>"><?php echo esc_html(sc_t('frontend.workshops', 'Workshops')); ?></a></li>
            <?php if (!is_wp_error($categories) && !empty($categories)): ?>
            <li>
                <a href="#" class="sc-has-submenu"><?php echo esc_html(sc_t('frontend.categories', 'Categories')); ?> <i class="fa-solid fa-angle-down"></i></a>
                <ul class="sub-menu">
                    <?php foreach ($categories as $category): ?>
                    <li><a href="<?php echo esc_url(get_term_link($category)); ?>"><?php echo esc_html($category->name); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endif; ?>
            <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html(sc_t('frontend.contact', 'Contact')); ?></a></li>
            <?php if ($is_logged_in): ?>
            <li><a href="<?php echo esc_url(home_url('/my-account/#favorites')); ?>"><i class="fa-solid fa-heart" style="margin-<?php echo sc_is_rtl() ? 'left' : 'right'; ?>:8px;color:#ef4444;"></i><?php echo esc_html(sc_t('frontend.my_favorites', 'My Favorites')); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php echo esc_html(sc_t('frontend.my_account', 'My Account')); ?></a></li>
            <li><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo esc_html(sc_t('frontend.logout', 'Logout')); ?></a></li>
            <?php else: ?>
            <li><a href="<?php echo esc_url(home_url('/login/')); ?>"><?php echo esc_html(sc_t('frontend.login', 'Login')); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/register/')); ?>"><?php echo esc_html(sc_t('frontend.register', 'Register')); ?></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sc-mobile-footer">
        <h6><?php echo esc_html(sc_t('frontend.contact', 'Contact')); ?></h6>
        <?php if ($platform_phone): ?>
        <div class="sc-mobile-contact-item">
            <i class="fa-solid fa-phone"></i>
            <a href="tel:<?php echo esc_attr($platform_phone); ?>"><?php echo esc_html($platform_phone); ?></a>
        </div>
        <?php endif; ?>
        <?php if ($platform_email): ?>
        <div class="sc-mobile-contact-item">
            <i class="fa-solid fa-envelope"></i>
            <a href="mailto:<?php echo esc_attr($platform_email); ?>"><?php echo esc_html($platform_email); ?></a>
        </div>
        <?php endif; ?>

        <div class="sc-mobile-social">
            <?php if ($platform_facebook): ?>
            <a href="<?php echo esc_url($platform_facebook); ?>" target="_blank"><i class="fa-brands fa-facebook-f"></i></a>
            <?php endif; ?>
            <?php if ($platform_instagram): ?>
            <a href="<?php echo esc_url($platform_instagram); ?>" target="_blank"><i class="fa-brands fa-instagram"></i></a>
            <?php endif; ?>
            <?php if ($platform_linkedin): ?>
            <a href="<?php echo esc_url($platform_linkedin); ?>" target="_blank"><i class="fa-brands fa-linkedin-in"></i></a>
            <?php endif; ?>
            <?php if ($platform_twitter): ?>
            <a href="<?php echo esc_url($platform_twitter); ?>" target="_blank"><i class="fa-brands fa-twitter"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!--===== BODY SPACER (for fixed header) =======-->
<div class="sc-body-spacer"></div>
