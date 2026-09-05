<?php
/**
 * Template Name: Login Page
 * Public Frontend Login Page - Dark & Premium Design
 *
 * @package sc_events
 * @version 5.0.0
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'medium') : '';
$redirect = isset($_GET['redirect']) ? esc_url($_GET['redirect']) : home_url('/my-account/');

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<div class="sc-page-header">
    <div class="container">
        <h1><?php esc_html_e('Login', 'sc_events'); ?></h1>
        <div class="sc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php esc_html_e('Login', 'sc_events'); ?></span>
        </div>
    </div>
</div>

<!-- Login Section -->
<section class="sc-auth-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7" data-aos="fade-up" data-aos-duration="800">
                <div class="sc-auth-card">

                    <!-- Header -->
                    <div class="sc-auth-header">
                        <?php if ($platform_logo_url): ?>
                        <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" class="sc-auth-logo">
                        <?php else: ?>
                        <div class="sc-auth-icon">
                            <i class="fa-solid fa-right-to-bracket"></i>
                        </div>
                        <?php endif; ?>
                        <h2 class="sc-auth-title"><?php esc_html_e('Welcome Back!', 'sc_events'); ?></h2>
                        <p class="sc-auth-subtitle"><?php esc_html_e('Login to access your account', 'sc_events'); ?></p>
                    </div>

                    <!-- Login Form -->
                    <form id="login-form">
                        <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">

                        <!-- Email -->
                        <div class="sc-form-group">
                            <label class="sc-form-label" for="email">
                                <i class="fa-solid fa-envelope"></i>
                                <?php esc_html_e('Email Address', 'sc_events'); ?>
                            </label>
                            <input type="email" id="email" name="email" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter your email', 'sc_events'); ?>" autocomplete="email" required>
                        </div>

                        <!-- Password -->
                        <div class="sc-form-group">
                            <label class="sc-form-label" for="password">
                                <i class="fa-solid fa-lock"></i>
                                <?php esc_html_e('Password', 'sc_events'); ?>
                            </label>
                            <div class="sc-password-field">
                                <input type="password" id="password" name="password" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter your password', 'sc_events'); ?>" autocomplete="current-password" required>
                                <button type="button" class="sc-toggle-password" onclick="togglePassword('password')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot -->
                        <div class="sc-remember-row">
                            <div class="sc-remember-check">
                                <input type="checkbox" id="remember" name="remember" value="1">
                                <label for="remember"><?php esc_html_e('Remember me', 'sc_events'); ?></label>
                            </div>
                            <a href="<?php echo esc_url(home_url('/forgot-password/')); ?>" class="sc-forgot-link">
                                <?php esc_html_e('Forgot Password?', 'sc_events'); ?>
                            </a>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="sc-auth-btn">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <?php esc_html_e('Login', 'sc_events'); ?>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="sc-auth-divider">
                        <span><?php esc_html_e('or', 'sc_events'); ?></span>
                    </div>

                    <!-- Register Link -->
                    <div class="sc-auth-footer">
                        <?php esc_html_e("Don't have an account?", 'sc_events'); ?>
                        <a href="<?php echo esc_url(home_url('/register/')); ?>">
                            <?php esc_html_e('Register Now', 'sc_events'); ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<script>
function togglePassword(fieldId) {
    var field = document.getElementById(fieldId);
    if (!field) return;
    var toggleBtn = field.parentElement.querySelector('.sc-toggle-password');
    if (!toggleBtn) return;
    var icon = toggleBtn.querySelector('i');
    if (!icon) return;

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<!-- Login form handler is in public-scripts.js -->

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
