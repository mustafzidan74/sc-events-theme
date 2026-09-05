<?php
/**
 * Dashboard Header Component - Iconic Template
 *
 * @package sc_events
 * @version 2.1.0
 */

// Prevent page caching on all dashboard pages
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true); // WP Rocket / W3TC / WP Super Cache
}
nocache_headers();
header('X-Accel-Expires: 0');      // Nginx FastCGI cache bypass
header('Surrogate-Control: no-store'); // Varnish/CDN bypass

// Translations
$t = array(
    'please_wait' => sc_t('dashboard_pages.please_wait', 'Please wait...'),
    'language' => sc_t('dashboard_pages.language', 'Language'),
    'my_profile' => sc_t('dashboard_pages.my_profile', 'My Profile'),
    'settings' => sc_t('dashboard_pages.settings', 'Settings'),
    'logout' => sc_t('dashboard_pages.logout', 'Logout'),
);

$current_user = wp_get_current_user();
$assets_url = get_template_directory_uri() . '/assets/admin-dashboard/';
$dashboard_url = home_url('/event-manager-dashboard/');

// Cache busting version - update SC_ASSET_VERSION in functions.php when assets change
$asset_version = defined( 'SC_ASSET_VERSION' ) ? SC_ASSET_VERSION : '3.4.2';
?>

<!doctype html>
<?php
// Determine RTL status
$is_rtl = is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl());
$dir = $is_rtl ? 'rtl' : 'ltr';
$lang = $is_rtl ? 'ar' : 'en';
?>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr($dir); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Event Manager Dashboard">
    <meta name="robots" content="noindex">
    <title><?php echo isset($page_title) ? esc_html($page_title) : 'Dashboard'; ?> - <?php bloginfo('name'); ?></title>
    <link rel="icon" href="<?php echo esc_url($assets_url); ?>images/favicon.ico" type="image/x-icon">

    <!-- VENDOR CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/toastr/toastr.min.css">

    <?php if (isset($load_charts) && $load_charts): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/charts-c3/plugin.css"/>
    <?php endif; ?>

    <?php if (isset($load_flatpickr) && $load_flatpickr): ?>
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/bootstrap-datepicker/bootstrap-datepicker3.css">
    <?php endif; ?>

    <!-- Select2 CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/select2/select2.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/select2/select2-bootstrap.css">

    <!-- SweetAlert2 CSS for Beautiful Alerts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- MAIN Project CSS file -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/main.css">
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>css/color_skins.css">

    <!-- Custom Enhancements CSS - Modern UI -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/custom-enhancements.css?v=' . $asset_version); ?>">

    <!-- Additional Dashboard Improvements CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-improvements.css?v=' . $asset_version); ?>">

    <!-- Dashboard Pages Consolidated Styles -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-pages.css?v=' . $asset_version); ?>">

    <!-- Modern Dashboard Design - Premium UI -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-modern.css?v=' . $asset_version); ?>">

    <!-- Dark Theme Overrides -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-dark-theme.css?v=' . $asset_version); ?>">

    <!-- Premium Design Upgrades -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-premium.css?v=' . $asset_version); ?>">

    <!-- Google Fonts - Inter for modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php if ($is_rtl): ?>
    <!-- Arabic Font - Tajawal for RTL -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url); ?>vendor/bootstrap/css/bootstrap.min.rtl.css">
    <!-- Dashboard RTL CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/dashboard-rtl.css?v=' . $asset_version); ?>">
    <?php endif; ?>

    <?php
    // Load Dynamic Colors CSS
    get_template_part('inc/admin-dashboard/dynamic', 'colors');
    wp_head();
    ?>

    <?php
    // Only load chat notifications if chat module is enabled
    $chat_module_enabled = !function_exists('sc_module_active') || sc_module_active('chat');
    if ($chat_module_enabled):
    ?>
    <!-- Admin Chat Notifications -->
    <script>
        var scAdminChatConfig = {
            ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('sc_chat_nonce')); ?>',
            chatPageUrl: '<?php echo esc_js($dashboard_url . 'chat'); ?>',
            iconUrl: '<?php echo esc_js(get_template_directory_uri() . '/assets/images/chat-icon.png'); ?>',
            chatLabel: '<?php echo esc_js(sc_t('nav.chat', 'Chat Messages')); ?>'
        };
    </script>
    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/admin-chat-notifications.js?v=' . $asset_version); ?>"></script>
    <?php endif; ?>

    <!-- Load jQuery and Bootstrap AFTER wp_head to avoid conflicts -->
    <script src="<?php echo esc_url($assets_url); ?>bundles/libscripts.bundle.js"></script>
    <script src="<?php echo esc_url($assets_url); ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Anti-FOUC: Apply saved theme before paint -->
    <script>
    (function() {
        var saved = null;
        try { saved = localStorage.getItem('sc_dashboard_theme'); } catch(e) {}
        if (!saved) {
            var m = document.cookie.match(/sc_dashboard_theme=([^;]+)/);
            saved = m ? m[1] : null;
        }
        if (saved === 'dark' || saved === 'light') {
            document.documentElement.setAttribute('data-theme', saved);
            try { localStorage.setItem('theme', saved); } catch(e) {} // sync legacy
        }
    })();
    </script>

    <style>
        .page-loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        [data-theme="dark"] .page-loader-wrapper {
            background: #0f172a;
        }
        .page-loader-wrapper.loaded {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        /* CSS-only fallback: force hide after 3s even if JS fails */
        @keyframes loaderFadeOut {
            0%, 80% { opacity: 1; visibility: visible; }
            100% { opacity: 0; visibility: hidden; pointer-events: none; display: none; }
        }
        .page-loader-wrapper {
            animation: loaderFadeOut 2.5s forwards;
        }
        .page-loader-wrapper.loaded {
            display: none !important;
        }
        /* Hide public chat widget in dashboard */
        #sc-chat-container,
        .sc-chat-widget {
            display: none !important;
        }
    </style>
</head>

<?php
// Determine dashboard theme from cookie (default: light)
$dashboard_theme = isset($_COOKIE['sc_dashboard_theme']) ? sanitize_text_field($_COOKIE['sc_dashboard_theme']) : 'light';
if (!in_array($dashboard_theme, array('light', 'dark'))) {
    $dashboard_theme = 'light';
}
?>
<body data-theme="<?php echo esc_attr($dashboard_theme); ?>" class="font-nunito<?php echo $is_rtl ? ' rtl lang-ar' : ' ltr lang-en'; ?>">

<div id="wrapper" class="theme-cyan">

    <!-- Page Loader (removed from DOM after load) -->
    <div class="page-loader-wrapper" id="sc-page-loader" role="status" aria-label="Loading">
        <div class="loader">
            <div class="m-t-30">
                <?php
                $platform_logo_id = get_option('sc_platform_logo');
                if ($platform_logo_id) {
                    $logo = wp_get_attachment_image_src($platform_logo_id, 'full');
                    echo '<img src="' . esc_url($logo[0]) . '" width="48" height="48" alt="' . get_bloginfo('name') . '">';
                } else {
                    echo '<img src="' . get_template_directory_uri() . '/assets/' . 'images/logo.png" width="48" height="48" alt="Iconic">';
                }
                ?>
            </div>
            <p><?php echo $t['please_wait']; ?></p>
        </div>
    </div>
    <script>
    // Look the loader up again on every call. Holding on to the element across
    // a timer breaks when the other path removed it first: parentNode is then
    // null and removeChild throws.
    function scRemovePageLoader() {
        var ldr = document.getElementById('sc-page-loader');
        if (ldr && ldr.parentNode) { ldr.parentNode.removeChild(ldr); }
    }
    // Robust fallback: remove loader from DOM after window load (no jQuery dependency)
    window.addEventListener('load', function() {
        var ldr = document.getElementById('sc-page-loader');
        if (ldr) { ldr.classList.add('loaded'); setTimeout(scRemovePageLoader, 500); }
    });
    // Safety net: force remove after 4s regardless
    setTimeout(scRemovePageLoader, 4000);
    </script>

    <!-- Top navbar div start -->
    <nav class="navbar navbar-fixed-top">
            <div class="navbar-brand">
                <button type="button" class="btn-toggle-offcanvas"><i class="fa fa-bars"></i></button>
                <button type="button" class="btn-toggle-fullwidth"><i class="fa fa-bars"></i></button>
                <a href="<?php echo $dashboard_url; ?>home">
                    <?php
                    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));
                    echo strtoupper(esc_html($platform_name));
                    ?>
                </a>
            </div>

            <div class="navbar-right">
                <!-- Language Switcher -->
                <div class="dropdown language-switcher">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="<?php echo $t['language']; ?>">
                        <i class="fa fa-globe"></i>
                        <span class="lang-label"><?php echo $is_rtl ? 'العربية' : 'English'; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li>
                            <a href="#" class="lang-switch <?php echo !$is_rtl ? 'active' : ''; ?>" data-lang="en">
                                <span class="lang-flag">🇺🇸</span> English
                            </a>
                        </li>
                        <li>
                            <a href="#" class="lang-switch <?php echo $is_rtl ? 'active' : ''; ?>" data-lang="ar">
                                <span class="lang-flag">🇸🇦</span> العربية
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Theme Toggle -->
                <div class="theme-toggle-wrapper">
                    <button type="button" class="btn-theme-toggle" id="dashboardThemeToggle"
                            title="<?php echo esc_attr(sc_t('dashboard.toggle_theme', 'Toggle Dark/Light Mode')); ?>">
                        <i class="fa fa-moon-o dark-icon"></i>
                        <i class="fa fa-sun-o light-icon"></i>
                    </button>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown user-profile">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <img src="<?php echo get_avatar_url($current_user->ID, array('size' => 32)); ?>" alt="<?php echo esc_attr($current_user->display_name); ?>" class="rounded-circle" width="32" height="32">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li class="dropdown-header"><?php echo esc_html($current_user->display_name); ?></li>
                        <li><a href="<?php echo $dashboard_url; ?>settings#account"><i class="fa fa-user"></i> <?php echo $t['my_profile']; ?></a></li>
                        <li><a href="<?php echo $dashboard_url; ?>settings"><i class="fa fa-cog"></i> <?php echo $t['settings']; ?></a></li>
                        <li class="divider"></li>
                        <li><a href="#" class="logout-link"><i class="fa fa-power-off"></i> <?php echo $t['logout']; ?></a></li>
                    </ul>
                </div>
            </div>

    </nav>

    <!-- Global JavaScript Translations -->
    <script>
    window.scTrans = {
        // General
        success: '<?php echo esc_js(sc_t("general.success", "Success")); ?>',
        error: '<?php echo esc_js(sc_t("general.error", "Error")); ?>',
        warning: '<?php echo esc_js(sc_t("general.warning", "Warning")); ?>',
        info: '<?php echo esc_js(sc_t("general.info", "Info")); ?>',
        loading: '<?php echo esc_js(sc_t("general.loading", "Loading...")); ?>',
        processing: '<?php echo esc_js(sc_t("general.processing", "Processing...")); ?>',
        please_wait: '<?php echo esc_js(sc_t("dashboard_pages.please_wait", "Please wait...")); ?>',

        // Confirmations
        confirm_delete: '<?php echo esc_js(sc_t("general.confirm_delete", "Are you sure you want to delete this?")); ?>',
        confirm_action: '<?php echo esc_js(sc_t("general.are_you_sure", "Are you sure?")); ?>',
        cannot_undo: '<?php echo esc_js(sc_t("general.cannot_undo", "This action cannot be undone")); ?>',
        yes_delete: '<?php echo esc_js(sc_t("general.yes", "Yes, delete it")); ?>',
        yes_confirm: '<?php echo esc_js(sc_t("general.confirm", "Yes, confirm")); ?>',
        cancel: '<?php echo esc_js(sc_t("general.cancel", "Cancel")); ?>',

        // Operations
        saved: '<?php echo esc_js(sc_t("general.success", "Saved successfully")); ?>',
        deleted: '<?php echo esc_js(sc_t("general.deleted", "Deleted successfully")); ?>',
        updated: '<?php echo esc_js(sc_t("general.updated", "Updated successfully")); ?>',
        created: '<?php echo esc_js(sc_t("general.created", "Created successfully")); ?>',

        // Errors
        connection_error: '<?php echo esc_js(sc_t("errors.connection_error", "Connection error. Please try again.")); ?>',
        try_again: '<?php echo esc_js(sc_t("general.try_again", "Please try again")); ?>',
        something_wrong: '<?php echo esc_js(sc_t("errors.something_wrong", "Something went wrong")); ?>',

        // Form Validation
        required_field: '<?php echo esc_js(sc_t("validation.required_field", "This field is required")); ?>',
        fill_required: '<?php echo esc_js(sc_t("validation.fill_required", "Please fill in all required fields")); ?>',
        invalid_email: '<?php echo esc_js(sc_t("validation.invalid_email", "Please enter a valid email")); ?>',

        // Events
        select_event: '<?php echo esc_js(sc_t("dashboard_pages.select_event", "Please select an event")); ?>',
        event_created: '<?php echo esc_js(sc_t("events.event_created", "Event created successfully")); ?>',
        event_updated: '<?php echo esc_js(sc_t("events.event_updated", "Event updated successfully")); ?>',
        event_deleted: '<?php echo esc_js(sc_t("events.event_deleted", "Event deleted successfully")); ?>',
        enter_event_title: '<?php echo esc_js(sc_t("events.enter_title", "Please enter an event title")); ?>',
        select_date: '<?php echo esc_js(sc_t("events.select_date", "Please select a date")); ?>',
        select_start_date: '<?php echo esc_js(sc_t("events.select_start_date", "Please select a start date")); ?>',
        add_event_day: '<?php echo esc_js(sc_t("events.add_event_day", "Please add at least one event day")); ?>',
        day_already_added: '<?php echo esc_js(sc_t("events.day_already_added", "This day is already added")); ?>',
        saved_as_draft: '<?php echo esc_js(sc_t("events.saved_as_draft", "Event saved as draft")); ?>',

        // Tickets
        ticket_added: '<?php echo esc_js(sc_t("tickets.ticket_added", "Ticket added successfully")); ?>',
        ticket_updated: '<?php echo esc_js(sc_t("tickets.ticket_updated", "Ticket updated successfully")); ?>',
        ticket_deleted: '<?php echo esc_js(sc_t("tickets.ticket_deleted", "Ticket deleted successfully")); ?>',
        enter_ticket_name: '<?php echo esc_js(sc_t("tickets.enter_name", "Please enter a ticket name")); ?>',
        one_card_required: '<?php echo esc_js(sc_t("tickets.one_card_required", "At least one card is required")); ?>',

        // Attendees
        attendee_added: '<?php echo esc_js(sc_t("attendees.attendee_added", "Attendee added successfully")); ?>',
        attendee_updated: '<?php echo esc_js(sc_t("attendees.attendee_updated", "Attendee updated successfully")); ?>',
        attendee_deleted: '<?php echo esc_js(sc_t("attendees.attendee_deleted", "Attendee deleted successfully")); ?>',

        // Certificates
        certificate_issued: '<?php echo esc_js(sc_t("certificates.certificate_issued", "Certificate issued successfully")); ?>',
        certificates_issued: '<?php echo esc_js(sc_t("certificates.certificates_issued", "Certificates issued successfully")); ?>',
        email_sent: '<?php echo esc_js(sc_t("certificates.email_sent", "Email sent successfully")); ?>',

        // Coupons
        coupon_created: '<?php echo esc_js(sc_t("coupons.coupon_created", "Coupon created successfully")); ?>',
        coupon_updated: '<?php echo esc_js(sc_t("coupons.coupon_updated", "Coupon updated successfully")); ?>',
        coupon_deleted: '<?php echo esc_js(sc_t("coupons.coupon_deleted", "Coupon deleted successfully")); ?>',

        // Scanner
        select_event_first: '<?php echo esc_js(sc_t("scanner.select_event_first", "Please select an event first")); ?>',

        // Dashboard
        stats_refreshed: '<?php echo esc_js(sc_t("dashboard_pages.stats_refreshed", "Statistics refreshed")); ?>',

        // Images
        images_uploaded: '<?php echo esc_js(sc_t("images.images_uploaded", "Images uploaded successfully")); ?>',
        image_uploaded: '<?php echo esc_js(sc_t("images.image_uploaded", "Image uploaded successfully")); ?>',
        upload_error: '<?php echo esc_js(sc_t("images.upload_error", "Error uploading images")); ?>'
    };
    </script>

    <!-- Language Switch Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.lang-switch').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var lang = this.getAttribute('data-lang');

                // Send AJAX to switch language
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        window.location.reload();
                    }
                };
                xhr.send('action=sc_switch_language&lang=' + lang + '&nonce=<?php echo wp_create_nonce('sc_language_switch'); ?>');
            });
        });
    });
    </script>

    <!-- main left sidebar start -->
