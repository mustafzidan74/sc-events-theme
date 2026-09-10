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

    <!-- Redesign: component library. Every class is namespaced `.w-`, so it
         cannot reach an element the legacy stylesheets or Bootstrap own. -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/components.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">

    <!-- Redesign: header and footer shell. -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/header.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">

    <?php // Card rails behave as sliders: arrows, drag, end states. ?>
    <script defer src="<?php echo esc_url($assets_url); ?>js/wisdom-rail.js?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>"></script>

    <?php
    /*
     * Page-specific — the legacy stylesheets load on every page regardless;
     * the redesign ones do not. home.css also carries the event card, filter
     * pills and pagination, which the listings reuse.
     */
    if (is_front_page() || is_page(['events', 'workshops', 'speakers', 'sponsors']) || is_post_type_archive(['sc_event', 'sc_workshop']) || get_query_var('sc_speaker_slug')):
    ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/home.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <?php endif; ?>

    <?php /*
     * utility.css carries the dead ends and the plain-WordPress templates:
     * the 404, contact, and anything falling through to page.php, single.php
     * or index.php (privacy, terms, search, archives).
     */
    if (is_404() || is_page('contact') || is_singular('post') || is_home()
        || is_search() || is_archive() || is_page(['privacy-policy', 'terms-and-conditions'])): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/utility.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <?php endif; ?>

    <?php // The sponsor wall appears on the home page, the sponsors page and
          // each event, so it loads on all three rather than living in any
          // one of their stylesheets.
    if (is_front_page() || is_page('sponsors') || is_singular('sc_event') || get_query_var('sc_event_slug')): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/sponsor-wall.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <?php endif; ?>

    <?php // Events routed from the custom table never become a queried post,
          // so is_singular() cannot see them — the router's query var can.
    if (is_singular(['sc_event', 'sc_workshop']) || get_query_var('sc_event_slug') || get_query_var('sc_workshop')): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/event.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <script defer src="<?php echo esc_url($assets_url); ?>js/wisdom-event.js?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>"></script>
    <?php endif; ?>

    <?php if (is_page('my-account')): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/account.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <script defer src="<?php echo esc_url($assets_url); ?>js/wisdom-account.js?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>"></script>
    <?php endif; ?>

    <?php if (is_page(['login', 'register', 'forgot-password'])): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/wisdom/auth.css?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>">
    <script defer src="<?php echo esc_url($assets_url); ?>js/wisdom-auth.js?v=<?php echo esc_attr(defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1'); ?>"></script>
    <?php endif; ?>

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
<?php
/*
 * Redesign header.
 *
 * The design specifies four items — Events, Workshops, Speakers, Partners —
 * and a single primary call to action. Event categories move under Events
 * rather than becoming a fifth item, which keeps the bar at the specified
 * width and puts them next to what they filter.
 *
 * Speakers and Partners have no template yet; both are rendered only once
 * their destination resolves, so the bar grows on its own as those land
 * instead of shipping links that 404.
 */
// Speakers live in the custom table, so the listing is a page rather than a
// post-type archive; fall back to the archive link if that ever changes.
$w_speakers_page = get_page_by_path('speakers');
$w_speakers_url = $w_speakers_page ? get_permalink($w_speakers_page) : get_post_type_archive_link('sc_speaker');
$w_partners_page = get_page_by_path('partners');
$w_partners_url = $w_partners_page ? get_permalink($w_partners_page) : '';
$w_account_url = home_url($is_logged_in ? '/my-account/' : '/login/');
?>
<header class="w-header" id="sc-header">
    <a class="w-header__brand" href="<?php echo esc_url(home_url('/')); ?>">
        <?php if ($platform_logo_url): ?>
            <img class="w-logo-dark" src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php if ($platform_logo_light_url): ?>
                <img class="w-logo-light" src="<?php echo esc_url($platform_logo_light_url); ?>" alt="<?php echo esc_attr($platform_name); ?>">
            <?php endif; ?>
        <?php else: ?>
            <span class="w-header__wordmark"><?php echo esc_html($platform_name); ?></span>
        <?php endif; ?>
    </a>

    <nav class="w-header__nav" aria-label="<?php esc_attr_e('Primary', 'sc_events'); ?>">
        <?php $w_has_categories = !is_wp_error($categories) && !empty($categories); ?>
        <div class="<?php echo $w_has_categories ? 'w-header__has-menu' : ''; ?>">
            <a href="<?php echo esc_url(home_url('/events/')); ?>" class="w-header__link"<?php echo is_page('events') ? ' aria-current="page"' : ''; ?>>
                <?php echo esc_html(sc_t('frontend.events', 'Events')); ?>
            </a>
            <?php if ($w_has_categories): ?>
            <ul class="w-header__submenu">
                <?php foreach ($categories as $category): ?>
                <li><a href="<?php echo esc_url(get_term_link($category)); ?>"><?php echo esc_html($category->name); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <a href="<?php echo esc_url(home_url('/workshops/')); ?>" class="w-header__link"<?php echo is_page('workshops') ? ' aria-current="page"' : ''; ?>>
            <?php echo esc_html(sc_t('frontend.workshops', 'Workshops')); ?>
        </a>

        <?php if ($w_speakers_url): ?>
        <a href="<?php echo esc_url($w_speakers_url); ?>" class="w-header__link"<?php echo (is_page('speakers') || get_query_var('sc_speaker_slug')) ? ' aria-current="page"' : ''; ?>>
            <?php echo esc_html(sc_t('frontend.speakers', 'Speakers')); ?>
        </a>
        <?php endif; ?>

        <?php if ($w_partners_url): ?>
        <a href="<?php echo esc_url($w_partners_url); ?>" class="w-header__link"<?php echo is_page('partners') ? ' aria-current="page"' : ''; ?>>
            <?php echo esc_html(sc_t('frontend.partners', 'Partners')); ?>
        </a>
        <?php endif; ?>
    </nav>

    <div class="w-header__actions">
        <a href="<?php echo esc_url($w_account_url); ?>" class="w-header__ticket">
            <i class="fa-solid fa-ticket" aria-hidden="true"></i>
            <?php echo esc_html(sc_t('frontend.my_ticket', 'My ticket')); ?>
        </a>

        <?php if ($is_logged_in): ?>
        <a href="<?php echo esc_url(home_url('/my-account/#favorites')); ?>" class="w-header__icon" id="sc-header-favorites" title="<?php esc_attr_e('My Favorites', 'sc_events'); ?>">
            <i class="fa-solid fa-heart" aria-hidden="true"></i>
            <span class="w-header__badge" id="sc-favorites-badge" style="display:none;">0</span>
        </a>
        <?php endif; ?>

        <?php
        /*
         * The theme switcher is held back for now, the same way the language
         * one is: the site runs on whichever theme sc_default_theme names.
         * Set sc_show_theme_toggle to 1 to bring it back — the dark palette is
         * still there and still maintained, it just is not offered.
         */
        if (get_option('sc_show_theme_toggle', '')):
        ?>
        <button class="w-header__icon sc-theme-toggle" id="sc-theme-toggle" title="<?php esc_attr_e('Toggle theme', 'sc_events'); ?>" aria-label="<?php esc_attr_e('Toggle light/dark theme', 'sc_events'); ?>">
            <i class="fa-solid fa-sun sc-theme-icon-light" aria-hidden="true"></i>
            <i class="fa-solid fa-moon sc-theme-icon-dark" aria-hidden="true"></i>
        </button>
        <?php endif; ?>

        <?php
        /*
         * The switcher only appears when the site is not pinned to one
         * language. Setting sc_site_language overrides every other source, so
         * offering a toggle that the next page load would undo would just be
         * confusing. Clear that option and the button comes back.
         */
        if (!get_option('sc_site_language', '')):
            $w_next_lang = sc_get_lang() === 'ar' ? 'en' : 'ar';
        ?>
        <button type="button" class="w-header__icon" id="w-lang-toggle"
                data-lang="<?php echo esc_attr($w_next_lang); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('sc_language_switch')); ?>"
                title="<?php esc_attr_e('Switch language', 'sc_events'); ?>">
            <?php echo esc_html($w_next_lang === 'en' ? 'EN' : 'ع'); ?>
        </button>
        <?php endif; ?>

        <a href="<?php echo esc_url(home_url('/events/')); ?>" class="w-header__cta">
            <?php echo esc_html(sc_t('frontend.get_tickets', 'Get tickets')); ?>
        </a>

        <button class="w-header__burger" id="sc-hamburger" aria-label="<?php esc_attr_e('Open menu', 'sc_events'); ?>">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>

<script>
// Language toggle — same endpoint the dashboard switcher uses.
(function () {
    var btn = document.getElementById('w-lang-toggle');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        btn.disabled = true;
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=sc_switch_language&lang=' + encodeURIComponent(btn.dataset.lang) +
                  '&nonce=' + encodeURIComponent(btn.dataset.nonce)
        }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            location.reload();
        }).catch(function () {
            btn.disabled = false;
        });
    });
})();
</script>

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
            <?php if (get_option('sc_show_theme_toggle', '')): ?>
            <button class="sc-theme-toggle sc-theme-toggle-mobile" title="<?php esc_attr_e('Toggle theme', 'sc_events'); ?>" aria-label="<?php esc_attr_e('Toggle light/dark theme', 'sc_events'); ?>">
                <i class="fa-solid fa-sun sc-theme-icon-light"></i>
                <i class="fa-solid fa-moon sc-theme-icon-dark"></i>
            </button>
            <?php endif; ?>
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

<?php
/*
 * The old header was fixed, so the page needed a spacer to sit below it. The
 * redesigned header is sticky and occupies its own space in the flow, so the
 * spacer would now leave a blank band under it.
 */
?>
