<?php
/**
 * Template Name: Register Page
 * Public Frontend Registration Page - Dark & Premium Design
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

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<div class="sc-page-header">
    <div class="container">
        <h1><?php esc_html_e('Create Account', 'sc_events'); ?></h1>
        <div class="sc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php esc_html_e('Register', 'sc_events'); ?></span>
        </div>
    </div>
</div>

<!-- Register Section -->
<section class="sc-auth-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9" data-aos="fade-up" data-aos-duration="800">
                <div class="sc-auth-card">

                    <!-- Header -->
                    <div class="sc-auth-header">
                        <?php if ($platform_logo_url): ?>
                        <img src="<?php echo esc_url($platform_logo_url); ?>" alt="<?php echo esc_attr($platform_name); ?>" class="sc-auth-logo">
                        <?php else: ?>
                        <div class="sc-auth-icon">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <?php endif; ?>
                        <h2 class="sc-auth-title"><?php esc_html_e('Join Us Today!', 'sc_events'); ?></h2>
                        <p class="sc-auth-subtitle"><?php esc_html_e('Create your account to register for events', 'sc_events'); ?></p>
                    </div>

                    <!-- Register Form -->
                    <form id="register-form">
                        <div class="row">
                            <!-- Full Name -->
                            <div class="col-12">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="name">
                                        <i class="fa-solid fa-user"></i>
                                        <?php esc_html_e('Full Name', 'sc_events'); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" id="name" name="name" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter your full name', 'sc_events'); ?>" autocomplete="name" required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-lg-6 col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="email">
                                        <i class="fa-solid fa-envelope"></i>
                                        <?php esc_html_e('Email Address', 'sc_events'); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <input type="email" id="email" name="email" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter your email', 'sc_events'); ?>" autocomplete="email" required>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="col-lg-6 col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="phone">
                                        <i class="fa-solid fa-phone"></i>
                                        <?php esc_html_e('Phone Number', 'sc_events'); ?>
                                    </label>
                                    <div class="sc-phone-group">
                                        <select id="phone_code" name="phone_code" class="sc-phone-code">
                                            <option value="+20">+20</option>
                                            <option value="+966">+966</option>
                                            <option value="+971">+971</option>
                                        </select>
                                        <input type="tel" id="phone" name="phone" class="sc-auth-input" placeholder="<?php esc_attr_e('XXX XXX XXXX', 'sc_events'); ?>" autocomplete="tel">
                                    </div>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="col-lg-6 col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="password">
                                        <i class="fa-solid fa-lock"></i>
                                        <?php esc_html_e('Password', 'sc_events'); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <div class="sc-password-field">
                                        <input type="password" id="password" name="password" class="sc-auth-input" placeholder="<?php esc_attr_e('Create a password', 'sc_events'); ?>" autocomplete="new-password" required minlength="6">
                                        <button type="button" class="sc-toggle-password" onclick="togglePassword('password')">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="sc-input-hint"><?php esc_html_e('At least 6 characters', 'sc_events'); ?></small>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-lg-6 col-md-6">
                                <div class="sc-form-group">
                                    <label class="sc-form-label" for="confirm_password">
                                        <i class="fa-solid fa-lock"></i>
                                        <?php esc_html_e('Confirm Password', 'sc_events'); ?>
                                        <span class="required">*</span>
                                    </label>
                                    <div class="sc-password-field">
                                        <input type="password" id="confirm_password" name="confirm_password" class="sc-auth-input" placeholder="<?php esc_attr_e('Confirm your password', 'sc_events'); ?>" autocomplete="new-password" required>
                                        <button type="button" class="sc-toggle-password" onclick="togglePassword('confirm_password')">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms -->
                            <div class="col-12">
                                <div class="sc-terms-check">
                                    <input type="checkbox" id="terms" name="terms" required>
                                    <label for="terms">
                                        <?php esc_html_e('I agree to the', 'sc_events'); ?>
                                        <a href="<?php echo esc_url(home_url('/terms-and-conditions/')); ?>" class="sc-terms-link" target="_blank"><?php esc_html_e('Terms & Conditions', 'sc_events'); ?></a>
                                        <?php esc_html_e('and', 'sc_events'); ?>
                                        <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" class="sc-terms-link" target="_blank"><?php esc_html_e('Privacy Policy', 'sc_events'); ?></a>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="col-12">
                                <button type="submit" class="sc-auth-btn">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <?php esc_html_e('Create Account', 'sc_events'); ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="sc-auth-divider">
                        <span><?php esc_html_e('or', 'sc_events'); ?></span>
                    </div>

                    <!-- Login Link -->
                    <div class="sc-auth-footer">
                        <?php esc_html_e('Already have an account?', 'sc_events'); ?>
                        <a href="<?php echo esc_url(home_url('/login/')); ?>">
                            <?php esc_html_e('Login', 'sc_events'); ?>
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

<!-- Register form handler is in public-scripts.js -->

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
