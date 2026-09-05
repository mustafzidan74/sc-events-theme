<?php
/**
 * Dashboard Login Template - Modern Bootstrap Design
 *
 * @package sc_events
 */

// Redirect if already logged in
if (is_user_logged_in() && SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_redirect(home_url('/event-manager-dashboard/home'));
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/admin-dashboard/';
$logo_url = get_template_directory_uri() . '/assets/images/logo.png';
$platform_logo_id = get_option('sc_platform_logo');
if ($platform_logo_id) {
    $logo = wp_get_attachment_image_src($platform_logo_id, 'full');
    $logo_url = $logo[0];
}
$platform_name = get_option('sc_platform_name', 'Super Coding Events');
$primary_color = get_option('sc_primary_color', '#667eea');

// Translations
$t = array(
    'event_manager_login' => sc_t('dashboard_pages.event_manager_login', 'Event Manager Login'),
    'event_manager_dashboard' => sc_t('dashboard_pages.event_manager_dashboard', 'Event Manager Dashboard'),
    'welcome_back' => sc_t('dashboard_pages.welcome_back', 'Welcome back!'),
    'login_to_access' => sc_t('dashboard_pages.login_to_access', 'Login to access Event Manager Dashboard'),
    'email_or_username' => sc_t('dashboard_pages.email_or_username', 'Email Address or Username'),
    'password' => sc_t('dashboard_pages.password', 'Password'),
    'enter_password' => sc_t('dashboard_pages.enter_password', 'Enter your password'),
    'remember_me' => sc_t('dashboard_pages.remember_me', 'Remember me'),
    'login' => sc_t('dashboard_pages.login', 'Login'),
    'logging_in' => sc_t('dashboard_pages.logging_in', 'Logging in...'),
    'all_rights_reserved' => sc_t('dashboard_pages.all_rights_reserved', 'All rights reserved.'),
    'failed_to_login' => sc_t('dashboard_pages.failed_to_login', 'Failed to login.'),
    'request_timed_out' => sc_t('dashboard_pages.request_timed_out', 'The request timed out. Please check your connection and try again.'),
    'could_not_connect' => sc_t('dashboard_pages.could_not_connect', 'Could not connect to server. Please check your internet connection.'),
    'too_many_attempts' => sc_t('dashboard_pages.too_many_attempts', 'Too many login attempts. Please wait a few minutes and try again.'),
    'server_error' => sc_t('dashboard_pages.server_error', 'Server error occurred. Please contact support.'),
    'refresh_and_try' => sc_t('dashboard_pages.refresh_and_try', 'Please refresh the page and try again.'),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $t['event_manager_login']; ?> - <?php echo esc_html($platform_name); ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo $assets_url; ?>images/favicon.ico" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?php echo $assets_url; ?>vendor/bootstrap/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo $assets_url; ?>vendor/font-awesome/css/font-awesome.min.css">

    <?php wp_head(); ?>

    <!-- Login Page Styles -->
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/dashboard/css/login.css">
    <style>
        :root { --primary-color: <?php echo esc_attr($primary_color); ?>; --primary-dark: <?php echo esc_attr(adjustBrightness($primary_color, -20)); ?>; }
        body { font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Login Header -->
            <div class="login-header">
                <div class="login-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                </div>
                <h1 class="login-title"><?php echo esc_html($platform_name); ?></h1>
                <p class="login-subtitle"><?php echo $t['event_manager_dashboard']; ?></p>
            </div>

            <!-- Login Body -->
            <div class="login-body">
                <div class="welcome-text">
                    <h4><?php echo $t['welcome_back']; ?></h4>
                    <p><?php echo $t['login_to_access']; ?></p>
                </div>

                <div id="login-messages"></div>

                <form id="event-manager-login-form" method="post" novalidate>
                    <?php wp_nonce_field('event_manager_login', 'login_nonce'); ?>

                    <div class="form-group">
                        <label for="user_login"><?php echo $t['email_or_username']; ?></label>
                        <div class="input-group-modern">
                            <input id="user_login" name="user_login" type="text" required placeholder="john@example.com">
                            <i class="fa fa-user"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user_password"><?php echo $t['password']; ?></label>
                        <div class="input-group-modern">
                            <input id="user_password" name="user_password" type="password" required placeholder="<?php echo $t['enter_password']; ?>">
                            <i class="fa fa-lock"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-checkbox">
                            <input type="checkbox" name="remember_me" id="remember">
                            <label for="remember"><?php echo $t['remember_me']; ?></label>
                        </div>
                    </div>

                    <button class="btn-login" type="submit">
                        <span class="login-text"><?php echo $t['login']; ?></span>
                        <span class="login-spinner" style="display: none;">
                            <i class="fa fa-circle-o-notch fa-spin"></i> <?php echo $t['logging_in']; ?>
                        </span>
                    </button>
                </form>
            </div>

            <!-- Login Footer -->
            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> <?php echo esc_html($platform_name); ?>. <?php echo $t['all_rights_reserved']; ?>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?php echo $assets_url; ?>vendor/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="<?php echo $assets_url; ?>vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
    jQuery(document).ready(function($) {
        $('#event-manager-login-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var submitBtn = form.find('button[type="submit"]');

            // Show loading state
            submitBtn.prop('disabled', true);
            form.find('.login-text').hide();
            form.find('.login-spinner').show();
            $('#login-messages').html('');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'event_manager_login',
                    user_login: $('#user_login').val(),
                    user_password: $('#user_password').val(),
                    remember_me: $('#remember').is(':checked') ? 'yes' : 'no',
                    nonce: $('[name="login_nonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $('#login-messages').html('<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + response.data.message + '</div>');
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 500);
                    } else {
                        $('#login-messages').html('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + response.data.message + '</div>');
                        submitBtn.prop('disabled', false);
                        form.find('.login-text').show();
                        form.find('.login-spinner').hide();
                    }
                },
                error: function(xhr, status) {
                    var errorMsg = '<?php echo esc_js($t['failed_to_login']); ?> ';
                    if (status === 'timeout') {
                        errorMsg += '<?php echo esc_js($t['request_timed_out']); ?>';
                    } else if (xhr.status === 0) {
                        errorMsg += '<?php echo esc_js($t['could_not_connect']); ?>';
                    } else if (xhr.status === 429) {
                        errorMsg += '<?php echo esc_js($t['too_many_attempts']); ?>';
                    } else if (xhr.status === 500) {
                        errorMsg += '<?php echo esc_js($t['server_error']); ?>';
                    } else {
                        errorMsg += '<?php echo esc_js($t['refresh_and_try']); ?>';
                    }
                    $('#login-messages').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' + errorMsg + '</div>');
                    submitBtn.prop('disabled', false);
                    form.find('.login-text').show();
                    form.find('.login-spinner').hide();
                }
            });
        });
    });
    </script>

    <?php wp_footer(); ?>
</body>
</html>

<?php
// Helper function for color adjustment
function adjustBrightness($hexColor, $percent) {
    $hexColor = ltrim($hexColor, '#');
    $rgb = array_map('hexdec', str_split($hexColor, 2));
    foreach ($rgb as &$color) {
        $color = max(0, min(255, $color + ($percent * 255 / 100)));
    }
    return '#' . implode('', array_map(function($val) { return str_pad(dechex($val), 2, '0', STR_PAD_LEFT); }, $rgb));
}
?>
