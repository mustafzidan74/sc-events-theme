<?php
/**
 * Dashboard shell — scripts and the closing wrappers opened by the header.
 *
 * Script order is kept exactly as before the redesign: page code on all 72
 * pages depends on which jQuery copy each plugin attaches to. Untangling that
 * is done page by page, not here.
 *
 * @package sc_events
 */

global $load_charts, $load_flatpickr, $load_wd_list, $load_wd_form;
$admin_assets  = get_template_directory_uri() . '/assets/admin-dashboard/';
$theme_assets  = get_template_directory_uri() . '/assets/';
$asset_version = defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1';
?>
</div><!-- .w-dash-col -->
</div><!-- .w-dash-app -->

<script src="<?php echo esc_url($admin_assets); ?>bundles/libscripts.bundle.js"></script>
<script src="<?php echo esc_url($admin_assets . 'js/dashboard-core.js?v=' . $asset_version); ?>"></script>
<script src="<?php echo esc_url($admin_assets); ?>bundles/vendorscripts.bundle.js"></script>
<?php if (isset($load_flatpickr) && $load_flatpickr): ?>
<script src="<?php echo esc_url($admin_assets); ?>vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
<?php endif; ?>
<script src="<?php echo esc_url($admin_assets); ?>vendor/jquery-sparkline/js/jquery.sparkline.min.js"></script>
<script src="<?php echo esc_url($admin_assets); ?>vendor/toastr/toastr.js"></script>
<script src="<?php echo esc_url($admin_assets); ?>vendor/select2/select2.min.js"></script>
<script src="<?php echo esc_url($admin_assets); ?>vendor/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo esc_url($theme_assets . 'js/shared-utilities.js?v=' . $asset_version); ?>"></script>
<?php if (isset($load_charts) && $load_charts): ?>
<script src="<?php echo esc_url($admin_assets); ?>bundles/c3.bundle.js"></script>
<?php endif; ?>
<script src="<?php echo esc_url($admin_assets); ?>bundles/mainscripts.bundle.js?v=<?php echo esc_attr($asset_version); ?>"></script>

<script>
var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
var scDashboard = {
    ajaxurl: ajaxurl,
    nonce: '<?php echo esc_js(wp_create_nonce('sc_dashboard_nonce')); ?>',
    logoutNonce: '<?php echo esc_js(wp_create_nonce('event_manager_logout')); ?>',
    dashboardUrl: '<?php echo esc_url(home_url('/event-manager-dashboard/')); ?>',
    assetsUrl: '<?php echo esc_url($admin_assets); ?>',
    version: '<?php echo esc_js($asset_version); ?>'
};
</script>
<?php
if (function_exists('sc_print_modules_js_object')) {
    sc_print_modules_js_object();
}
?>
<script src="<?php echo esc_url($admin_assets . 'js/dashboard-init.js?v=' . $asset_version); ?>"></script>
<script src="<?php echo esc_url($theme_assets . 'dashboard/js/wd-shell.js?v=' . $asset_version); ?>"></script>
<?php if (!empty($load_wd_list)): ?>
<script src="<?php echo esc_url($theme_assets . 'dashboard/js/wd-list.js?v=' . $asset_version); ?>"></script>
<?php endif; ?>
<?php if (!empty($load_wd_form)): ?>
<script src="<?php echo esc_url($theme_assets . 'dashboard/js/wd-form.js?v=' . $asset_version); ?>"></script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
