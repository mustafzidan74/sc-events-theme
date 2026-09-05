<?php
/**
 * Template Name: Forgot Password Page
 * Public Frontend Forgot Password - OTP Email Flow
 * Step 1: Enter email → Step 2: Enter OTP → Step 3: New password
 *
 * @package sc_events
 * @version 6.0.0
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$assets_url = get_template_directory_uri() . '/assets/frontend/';
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_url = $platform_logo_id ? wp_get_attachment_image_url($platform_logo_id, 'full') : '';

// Load header
get_template_part('template-parts/public/header', 'public');
?>

<!-- Page Header -->
<div class="sc-page-header">
    <div class="container">
        <h1><?php esc_html_e('Forgot Password', 'sc_events'); ?></h1>
        <div class="sc-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sc_events'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?php esc_html_e('Forgot Password', 'sc_events'); ?></span>
        </div>
    </div>
</div>

<!-- Forgot Password Section -->
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
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <?php endif; ?>
                        <h2 class="sc-auth-title"><?php esc_html_e('Reset Your Password', 'sc_events'); ?></h2>
                        <p class="sc-auth-subtitle" id="step-subtitle"><?php esc_html_e('Enter your email address to receive a verification code', 'sc_events'); ?></p>
                    </div>

                    <!-- Progress Steps -->
                    <div class="sc-steps-indicator">
                        <div class="sc-step active" id="indicator-1">
                            <div class="sc-step-circle">1</div>
                            <span><?php esc_html_e('Email', 'sc_events'); ?></span>
                        </div>
                        <div class="sc-step-line" id="line-1"></div>
                        <div class="sc-step" id="indicator-2">
                            <div class="sc-step-circle">2</div>
                            <span><?php esc_html_e('Verify', 'sc_events'); ?></span>
                        </div>
                        <div class="sc-step-line" id="line-2"></div>
                        <div class="sc-step" id="indicator-3">
                            <div class="sc-step-circle">3</div>
                            <span><?php esc_html_e('Reset', 'sc_events'); ?></span>
                        </div>
                    </div>

                    <!-- ========== STEP 1: Email ========== -->
                    <form id="email-form" class="sc-forgot-step">
                        <div class="sc-form-group">
                            <label class="sc-form-label" for="email">
                                <i class="fa-solid fa-envelope"></i>
                                <?php esc_html_e('Email Address', 'sc_events'); ?> <span class="sc-required">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter your email', 'sc_events'); ?>" autocomplete="email" required>
                        </div>

                        <button type="submit" class="sc-auth-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                            <?php esc_html_e('Send Verification Code', 'sc_events'); ?>
                        </button>
                    </form>

                    <!-- ========== STEP 2: OTP Verification ========== -->
                    <form id="otp-form" class="sc-forgot-step" style="display: none;">
                        <input type="hidden" id="otp-email" name="otp_email">

                        <div class="sc-otp-sent-notice">
                            <i class="fa-solid fa-mobile-screen-button"></i>
                            <p>
                                <?php esc_html_e('Enter the last 4 digits of the phone number on file', 'sc_events'); ?>
                                <br>
                                <strong id="phone-hint" style="font-family: monospace; letter-spacing: 2px;"></strong>
                            </p>
                        </div>

                        <div class="sc-form-group">
                            <label class="sc-form-label" for="otp-code">
                                <i class="fa-solid fa-shield-halved"></i>
                                <?php esc_html_e('Last 4 digits', 'sc_events'); ?> <span class="sc-required">*</span>
                            </label>
                            <input type="text" id="otp-code" name="otp" class="sc-auth-input sc-otp-input" placeholder="0000" maxlength="4" pattern="[0-9]{4}" autocomplete="off" inputmode="numeric" required>
                            <small class="sc-input-hint"><?php esc_html_e('Enter the last 4 digits of your registered phone number.', 'sc_events'); ?></small>
                        </div>

                        <button type="submit" class="sc-auth-btn">
                            <i class="fa-solid fa-check-circle"></i>
                            <?php esc_html_e('Verify', 'sc_events'); ?>
                        </button>
                    </form>

                    <!-- ========== STEP 3: New Password ========== -->
                    <form id="reset-password-form" class="sc-forgot-step" style="display: none;">
                        <input type="hidden" id="reset-email" name="reset_email">
                        <input type="hidden" id="reset-otp" name="reset_otp">

                        <div class="sc-verify-success">
                            <div class="sc-verify-icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <p class="sc-success-text"><?php esc_html_e('Email Verified!', 'sc_events'); ?></p>
                            <p class="sc-help-text"><?php esc_html_e('Now set your new password', 'sc_events'); ?></p>
                        </div>

                        <div class="sc-form-group">
                            <label class="sc-form-label" for="new-password">
                                <i class="fa-solid fa-lock"></i>
                                <?php esc_html_e('New Password', 'sc_events'); ?> <span class="sc-required">*</span>
                            </label>
                            <div class="sc-password-field">
                                <input type="password" id="new-password" name="new_password" class="sc-auth-input" placeholder="<?php esc_attr_e('Enter new password', 'sc_events'); ?>" autocomplete="new-password" minlength="6" required>
                                <button type="button" class="sc-toggle-password" onclick="togglePassword('new-password')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <small class="sc-input-hint"><?php esc_html_e('At least 6 characters', 'sc_events'); ?></small>
                        </div>

                        <div class="sc-form-group">
                            <label class="sc-form-label" for="confirm-password">
                                <i class="fa-solid fa-lock"></i>
                                <?php esc_html_e('Confirm Password', 'sc_events'); ?> <span class="sc-required">*</span>
                            </label>
                            <div class="sc-password-field">
                                <input type="password" id="confirm-password" name="confirm_password" class="sc-auth-input" placeholder="<?php esc_attr_e('Confirm new password', 'sc_events'); ?>" autocomplete="new-password" required>
                                <button type="button" class="sc-toggle-password" onclick="togglePassword('confirm-password')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="sc-auth-btn">
                            <i class="fa-solid fa-lock"></i>
                            <?php esc_html_e('Reset Password', 'sc_events'); ?>
                        </button>
                    </form>

                    <!-- Back to Login -->
                    <div class="sc-auth-divider">
                        <span></span>
                    </div>
                    <div class="sc-auth-footer">
                        <?php esc_html_e('Remember your password?', 'sc_events'); ?>
                        <a href="<?php echo esc_url(home_url('/login/')); ?>">
                            <?php esc_html_e('Back to Login', 'sc_events'); ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- Forgot Password Page Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/frontend/css/forgot-password-page.css">


<script>
// Toggle Password Visibility
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

jQuery(document).ready(function($) {
    var resendInterval = null;

    // Only allow numbers in OTP field
    $('#otp-code').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // ========== Step Navigation Helpers ==========
    function goToStep(step) {
        $('.sc-forgot-step').slideUp(300);

        // Update indicators
        for (var i = 1; i <= 3; i++) {
            var el = $('#indicator-' + i);
            el.removeClass('active done');
            if (i < step) el.addClass('done');
            if (i === step) el.addClass('active');
        }
        for (var j = 1; j <= 2; j++) {
            var line = $('#line-' + j);
            line.removeClass('active done');
            if (j < step) line.addClass('done');
            if (j === step) line.addClass('active');
        }

        // Update subtitle
        var subtitles = {
            1: '<?php echo esc_js(__('Enter your email address to receive a verification code', 'sc_events')); ?>',
            2: '<?php echo esc_js(__('Check your email and enter the verification code', 'sc_events')); ?>',
            3: '<?php echo esc_js(__('Set your new password', 'sc_events')); ?>'
        };
        $('#step-subtitle').text(subtitles[step]);

        // Show the target form
        setTimeout(function() {
            if (step === 1) $('#email-form').slideDown(300);
            if (step === 2) { $('#otp-form').slideDown(300); $('#otp-code').focus(); }
            if (step === 3) { $('#reset-password-form').slideDown(300); $('#new-password').focus(); }
        }, 350);
    }

    // ========== Resend Timer ==========
    function startResendTimer() {
        var seconds = 60;
        var $btn = $('#resend-otp-btn');
        var $timer = $('#resend-timer');

        $btn.prop('disabled', true);
        $timer.text(seconds);
        $btn.html('<?php echo esc_js(__('Resend', 'sc_events')); ?> (<span id="resend-timer">' + seconds + '</span>s)');

        if (resendInterval) clearInterval(resendInterval);

        resendInterval = setInterval(function() {
            seconds--;
            $btn.find('#resend-timer').text(seconds);
            if (seconds <= 0) {
                clearInterval(resendInterval);
                $btn.prop('disabled', false).html('<?php echo esc_js(__('Resend Code', 'sc_events')); ?>');
            }
        }, 1000);
    }

    // ========== Step 1: Send OTP ==========
    $('#email-form').on('submit', function(e) {
        e.preventDefault();
        sendOTP();
    });

    function sendOTP() {
        var email = $('#email').val().trim();

        if (!email || !isValidEmail(email)) {
            showError('<?php echo esc_js(__('Please enter a valid email address', 'sc_events')); ?>');
            return;
        }

        var $btn = $('#email-form button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Sending...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_send_otp',
                email: email,
                nonce: scPublic.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    // Move to step 2
                    $('#otp-email').val(email);
                    $('#phone-hint').text((response.data && response.data.phone_hint) ? response.data.phone_hint : '');
                    goToStep(2);
                } else {
                    showError(response.data.message || '<?php echo esc_js(__('Could not start verification. Please try again.', 'sc_events')); ?>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                showError('<?php echo esc_js(__('Connection error. Please try again.', 'sc_events')); ?>');
            }
        });
    }

    // ========== Resend OTP ==========
    $('#resend-otp-btn').on('click', function() {
        var email = $('#otp-email').val();
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_send_otp',
                email: email,
                nonce: scPublic.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('<?php echo esc_js(__('New code sent to your email.', 'sc_events')); ?>');
                    startResendTimer();
                } else {
                    showError(response.data.message || '<?php echo esc_js(__('Could not resend code.', 'sc_events')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Resend Code', 'sc_events')); ?>');
                }
            },
            error: function() {
                showError('<?php echo esc_js(__('Connection error.', 'sc_events')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Resend Code', 'sc_events')); ?>');
            }
        });
    });

    // ========== Step 2: Verify OTP ==========
    $('#otp-form').on('submit', function(e) {
        e.preventDefault();

        var email = $('#otp-email').val();
        var otp = $('#otp-code').val().trim();

        if (!otp || otp.length !== 4 || !/^\d{4}$/.test(otp)) {
            showError('<?php echo esc_js(__('Please enter the last 4 digits of your phone', 'sc_events')); ?>');
            return;
        }

        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Verifying...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_verify_otp',
                email: email,
                otp: otp,
                nonce: scPublic.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    // Move to step 3
                    $('#reset-email').val(email);
                    $('#reset-otp').val(otp);
                    if (resendInterval) clearInterval(resendInterval);
                    goToStep(3);
                } else {
                    showError(response.data.message || '<?php echo esc_js(__('Invalid code. Please try again.', 'sc_events')); ?>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                showError('<?php echo esc_js(__('Connection error. Please try again.', 'sc_events')); ?>');
            }
        });
    });

    // ========== Step 3: Reset Password ==========
    $('#reset-password-form').on('submit', function(e) {
        e.preventDefault();

        var email = $('#reset-email').val();
        var otp = $('#reset-otp').val();
        var newPassword = $('#new-password').val();
        var confirmPassword = $('#confirm-password').val();

        if (!newPassword || newPassword.length < 6) {
            showError('<?php echo esc_js(__('Password must be at least 6 characters', 'sc_events')); ?>');
            return;
        }

        if (newPassword !== confirmPassword) {
            showError('<?php echo esc_js(__('Passwords do not match', 'sc_events')); ?>');
            return;
        }

        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Resetting...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_reset_password_with_otp',
                email: email,
                otp: otp,
                new_password: newPassword,
                nonce: scPublic.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    showSuccess(response.data.message || '<?php echo esc_js(__('Password reset successfully!', 'sc_events')); ?>');
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url(home_url('/login/')); ?>';
                    }, 2000);
                } else {
                    showError(response.data.message || '<?php echo esc_js(__('Could not reset password. Please try again.', 'sc_events')); ?>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                showError('<?php echo esc_js(__('Connection error. Please try again.', 'sc_events')); ?>');
            }
        });
    });

    // ========== Helpers ==========
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: '<?php echo esc_js(__('Success', 'sc_events')); ?>', text: message, timer: 3000 });
        } else { alert(message); }
    }

    function showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: '<?php echo esc_js(__('Error', 'sc_events')); ?>', text: message });
        } else { alert(message); }
    }
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
