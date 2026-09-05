<?php
/**
 * Dashboard Footer Component - Iconic Template
 *
 * @package sc_events
 * @version 2.1.0
 */

// Translations
$t_footer = array(
    'toggle_menu' => sc_t('dashboard_pages.toggle_menu', 'Toggle Menu'),
);

global $load_charts;
$assets_url = get_template_directory_uri() . '/assets/admin-dashboard/';

// Cache busting version - update SC_ASSET_VERSION in functions.php when assets change
$asset_version = defined( 'SC_ASSET_VERSION' ) ? SC_ASSET_VERSION : '3.4.2';
?>

        </div>
    </div>

</div>

<!-- Javascript -->
<!-- Core Libraries Bundle (jQuery, Bootstrap core) -->
<script src="<?php echo esc_url($assets_url); ?>bundles/libscripts.bundle.js"></script>

<!-- Dashboard Core Scripts (error handling, DOM fixes) - Load BEFORE vendorscripts -->
<script src="<?php echo esc_url($assets_url . 'js/dashboard-core.js?v=' . $asset_version); ?>"></script>

<script src="<?php echo esc_url($assets_url); ?>bundles/vendorscripts.bundle.js"></script>

<!-- Date Picker (conditional) - Load early to be available for page scripts -->
<?php if (isset($load_flatpickr) && $load_flatpickr): ?>
<script src="<?php echo esc_url($assets_url); ?>vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
<?php endif; ?>

<!-- jQuery Sparkline - Load before mainscripts -->
<script src="<?php echo esc_url($assets_url); ?>vendor/jquery-sparkline/js/jquery.sparkline.min.js"></script>

<!-- Toastr -->
<script src="<?php echo esc_url($assets_url); ?>vendor/toastr/toastr.js"></script>

<!-- Select2 JS -->
<script src="<?php echo esc_url($assets_url); ?>vendor/select2/select2.min.js"></script>

<!-- QRCode.js Library for QR Code Generation -->
<script src="<?php echo esc_url($assets_url); ?>vendor/qrcode.min.js"></script>

<!-- SweetAlert2 JS for Beautiful Alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Shared Utilities (common helper functions) -->
<script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/shared-utilities.js?v=' . $asset_version); ?>"></script>

<!-- Chart.js (conditional) -->
<?php if (isset($load_charts) && $load_charts): ?>
<script src="<?php echo esc_url($assets_url); ?>bundles/c3.bundle.js"></script>
<?php endif; ?>

<!-- Mainscripts Bundle -->
<script src="<?php echo esc_url($assets_url); ?>bundles/mainscripts.bundle.js?v=<?php echo esc_attr($asset_version); ?>"></script>

<!-- Localize script for AJAX - Must be before dashboard-init.js -->
<script>
var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
var scDashboard = {
    ajaxurl: ajaxurl,
    nonce: '<?php echo esc_js(wp_create_nonce('sc_dashboard_nonce')); ?>',
    logoutNonce: '<?php echo esc_js(wp_create_nonce('event_manager_logout')); ?>',
    dashboardUrl: '<?php echo esc_url(home_url('/event-manager-dashboard/')); ?>',
    assetsUrl: '<?php echo esc_url($assets_url); ?>',
    version: '<?php echo esc_js($asset_version); ?>'
};
</script>

<?php
// Output module status for JavaScript
if (function_exists('sc_print_modules_js_object')) {
    sc_print_modules_js_object();
}
?>

<!-- Theme Toggle Script -->
<script>
(function() {
    'use strict';

    function getTheme() {
        try { var s = localStorage.getItem('sc_dashboard_theme'); if (s) return s; } catch(e) {}
        var m = document.cookie.match(/sc_dashboard_theme=([^;]+)/);
        return m ? m[1] : 'light';
    }

    function setTheme(theme) {
        document.body.classList.add('theme-transitioning');
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('sc_dashboard_theme', theme);
            localStorage.setItem('theme', theme); // sync with legacy bundle
        } catch(e) {}
        document.cookie = 'sc_dashboard_theme=' + theme + ';path=/;max-age=31536000;SameSite=Lax';
        updateApexChartsTheme(theme);
        setTimeout(function() {
            document.body.classList.remove('theme-transitioning');
        }, 350);
    }

    function updateApexChartsTheme(theme) {
        if (typeof ApexCharts === 'undefined') return;
        var isDark = theme === 'dark';
        window.Apex = window.Apex || {};
        window.Apex.chart = { foreColor: isDark ? '#94a3b8' : '#64748b' };
        window.Apex.grid = { borderColor: isDark ? '#334155' : '#e2e8f0' };
        window.Apex.tooltip = { theme: isDark ? 'dark' : 'light' };
        try {
            var instances = Apex._chartInstances || [];
            instances.forEach(function(inst) {
                if (inst && inst.chart) {
                    inst.chart.updateOptions({
                        chart: { foreColor: isDark ? '#94a3b8' : '#64748b' },
                        grid: { borderColor: isDark ? '#334155' : '#e2e8f0' },
                        tooltip: { theme: isDark ? 'dark' : 'light' }
                    }, false, false);
                }
            });
        } catch(e) {}
    }

    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('dashboardThemeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var current = document.body.getAttribute('data-theme') || 'light';
                setTheme(current === 'dark' ? 'light' : 'dark');
            });
        }
        var currentTheme = getTheme();
        if (currentTheme === 'dark') {
            updateApexChartsTheme('dark');
        }
    });

    window.scToggleTheme = function() {
        var current = document.body.getAttribute('data-theme') || 'light';
        setTheme(current === 'dark' ? 'light' : 'dark');
    };
    window.scSetTheme = setTheme;
})();
</script>

<!-- Dashboard Initialization Scripts -->
<script src="<?php echo esc_url($assets_url . 'js/dashboard-init.js?v=' . $asset_version); ?>"></script>

<!-- Bootstrap already loaded in header - Verify it's working -->
<script>
jQuery(document).ready(function($) {
    // Check if Bootstrap modal is available
    if (typeof $.fn.modal === 'undefined') {
        console.error('Bootstrap modal not loaded! Check header loading.');
    } else {
    }
});
</script>

<!-- Mobile Sidebar Toggle Button (FAB) -->
<button type="button" class="sidebar-toggle d-lg-none" id="mobileSidebarToggle" aria-label="<?php echo esc_attr($t_footer['toggle_menu']); ?>">
    <i class="fa fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile Responsive JavaScript -->
<script>
jQuery(document).ready(function($) {
    var $sidebar = $('#left-sidebar, .sidebar, #sidebar');
    var $overlay = $('#sidebarOverlay');
    var $toggle = $('#mobileSidebarToggle');
    var $body = $('body');

    // Toggle sidebar on mobile
    $toggle.on('click', function() {
        $sidebar.toggleClass('sidebar--visible');
        $overlay.toggleClass('active');
        $body.toggleClass('sidebar-open');

        // Update icon
        var $icon = $(this).find('i');
        if ($sidebar.hasClass('sidebar--visible')) {
            $icon.removeClass('fa-bars').addClass('fa-times');
        } else {
            $icon.removeClass('fa-times').addClass('fa-bars');
        }
    });

    // Close sidebar when clicking overlay
    $overlay.on('click', function() {
        $sidebar.removeClass('sidebar--visible');
        $overlay.removeClass('active');
        $body.removeClass('sidebar-open');
        $toggle.find('i').removeClass('fa-times').addClass('fa-bars');
    });

    // Close sidebar when clicking a menu link (on mobile)
    $sidebar.find('a').on('click', function() {
        if ($(window).width() < 992) {
            setTimeout(function() {
                $sidebar.removeClass('sidebar--visible');
                $overlay.removeClass('active');
                $body.removeClass('sidebar-open');
                $toggle.find('i').removeClass('fa-times').addClass('fa-bars');
            }, 150);
        }
    });

    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() >= 992) {
            $sidebar.removeClass('sidebar--visible');
            $overlay.removeClass('active');
            $body.removeClass('sidebar-open');
            $toggle.find('i').removeClass('fa-times').addClass('fa-bars');
        }
    });

    // Handle escape key to close sidebar
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $sidebar.hasClass('sidebar--visible')) {
            $sidebar.removeClass('sidebar--visible');
            $overlay.removeClass('active');
            $body.removeClass('sidebar-open');
            $toggle.find('i').removeClass('fa-times').addClass('fa-bars');
        }
    });

    // Swipe gesture support for mobile
    var touchStartX = 0;
    var touchEndX = 0;

    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });

    function handleSwipe() {
        var swipeThreshold = 80;
        var diff = touchEndX - touchStartX;

        // Swipe right from left edge to open sidebar
        if (diff > swipeThreshold && touchStartX < 50 && !$sidebar.hasClass('sidebar--visible')) {
            $sidebar.addClass('sidebar--visible');
            $overlay.addClass('active');
            $body.addClass('sidebar-open');
            $toggle.find('i').removeClass('fa-bars').addClass('fa-times');
        }

        // Swipe left to close sidebar
        if (diff < -swipeThreshold && $sidebar.hasClass('sidebar--visible')) {
            $sidebar.removeClass('sidebar--visible');
            $overlay.removeClass('active');
            $body.removeClass('sidebar-open');
            $toggle.find('i').removeClass('fa-times').addClass('fa-bars');
        }
    }
});
</script>

<?php wp_footer(); ?>

</body>
</html>
