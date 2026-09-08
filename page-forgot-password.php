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
<section class="w-auth">
    <?php get_template_part('template-parts/public/auth-aside'); ?>

    <div class="w-auth__card">
        <div class="w-auth__form">
            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.reset_password', 'Reset your password')); ?></h1>
            <p class="w-auth__lede" id="step-subtitle">
                <?php echo esc_html(sc_t('frontend.reset_lede', 'Enter the email on your account and we will send a verification code.')); ?>
            </p>

            <div class="w-steps sc-steps-indicator">
                <div class="sc-step active" id="indicator-1">
                    <span class="sc-step-circle">1</span>
                    <span><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></span>
                </div>
                <div class="sc-step-line" id="line-1"></div>
                <div class="sc-step" id="indicator-2">
                    <span class="sc-step-circle">2</span>
                    <span><?php echo esc_html(sc_t('frontend.verify', 'Verify')); ?></span>
                </div>
                <div class="sc-step-line" id="line-2"></div>
                <div class="sc-step" id="indicator-3">
                    <span class="sc-step-circle">3</span>
                    <span><?php echo esc_html(sc_t('frontend.reset', 'Reset')); ?></span>
                </div>
            </div>

            <!-- Step 1 — email -->
            <form id="email-form" class="sc-forgot-step w-auth__form">
                <div class="w-field">
                    <label class="w-label" for="email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                    <input class="w-input" type="email" id="email" name="email" autocomplete="email"
                           placeholder="you@clinic.com" required>
                </div>
                <button class="w-btn w-btn--lg" type="submit">
                    <?php echo esc_html(sc_t('frontend.send_code', 'Send code')); ?>
                </button>
            </form>

            <!-- Step 2 — the last four digits of the phone on file -->
            <form id="otp-form" class="sc-forgot-step w-auth__form" style="display: none;">
                <input type="hidden" id="otp-email" name="otp_email">

                <p class="w-auth__lede">
                    <?php echo esc_html(sc_t('frontend.otp_notice', 'Enter the last 4 digits of the phone number on your account')); ?>
                    <br><strong id="phone-hint" class="w-otp" style="font-size:1rem"></strong>
                </p>

                <div class="w-field">
                    <label class="w-label" for="otp-code"><?php echo esc_html(sc_t('frontend.last_4_digits', 'Last 4 digits')); ?></label>
                    <input class="w-input w-otp" type="text" id="otp-code" name="otp" placeholder="0000"
                           maxlength="4" pattern="[0-9]{4}" inputmode="numeric" autocomplete="off" required>
                </div>

                <button class="w-btn w-btn--lg" type="submit">
                    <?php echo esc_html(sc_t('frontend.verify', 'Verify')); ?>
                </button>
            </form>

            <!-- Step 3 — the new password -->
            <form id="reset-password-form" class="sc-forgot-step w-auth__form" style="display: none;">
                <input type="hidden" id="reset-email" name="reset_email">
                <input type="hidden" id="reset-otp" name="reset_otp">

                <p class="w-auth__verified">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span><?php echo esc_html(sc_t('frontend.verified_set_password', 'Verified — now set your new password.')); ?></span>
                </p>

                <div class="w-field">
                    <label class="w-label" for="new-password"><?php echo esc_html(sc_t('frontend.new_password', 'New password')); ?></label>
                    <span class="w-auth__pw">
                        <input class="w-input" type="password" id="new-password" name="new_password"
                               autocomplete="new-password" minlength="6" required>
                        <button class="w-auth__reveal" type="button" data-reveal="new-password"
                                aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </span>
                    <span class="w-hint"><?php echo esc_html(sc_t('frontend.password_hint', 'At least 6 characters.')); ?></span>
                </div>

                <div class="w-field">
                    <label class="w-label" for="confirm-password"><?php echo esc_html(sc_t('frontend.confirm_password', 'Confirm password')); ?></label>
                    <span class="w-auth__pw">
                        <input class="w-input" type="password" id="confirm-password" name="confirm_password"
                               autocomplete="new-password" required>
                        <button class="w-auth__reveal" type="button" data-reveal="confirm-password"
                                aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </span>
                </div>

                <button class="w-btn w-btn--lg" type="submit">
                    <?php echo esc_html(sc_t('frontend.reset_password_action', 'Reset password')); ?>
                </button>
            </form>

            <p class="w-auth__foot">
                <?php echo esc_html(sc_t('frontend.remember_password', 'Remember your password?')); ?>
                <a href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
            </p>
        </div>
    </div>
</section>

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
