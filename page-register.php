<?php
/**
 * Template Name: Register Page
 *
 * #register-form (name, email, phone_code, phone, password, confirm_password, terms) is submitted
 * by wisdom-otp.js; when WhatsApp codes are on, #register-verify asks for the code sent to the
 * number before the account is created (inc/auth/sc-otp-handlers.php). The name goes over the
 * wire as one string; the two visible boxes feed the hidden field (wisdom-auth.js).
 *
 * @package sc_events
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

get_template_part('template-parts/public/header', 'public');
?>

<section class="w-auth">
    <?php get_template_part('template-parts/public/auth-aside'); ?>

    <div class="w-auth__card">
        <div class="w-auth__form">
        <p class="w-otp-msg" id="register-msg" role="alert" hidden></p>
        <form class="w-auth__form" id="register-form">
            <nav class="w-auth__modes" aria-label="<?php echo esc_attr(sc_t('frontend.account', 'Account')); ?>">
                <a class="w-auth__mode" href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
                <a class="w-auth__mode" aria-current="page" href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?>
                </a>
            </nav>

            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.create_your_account', 'Create your account')); ?></h1>

            <input type="hidden" id="name" name="name" value="">

            <div class="w-auth__pair">
                <div class="w-field">
                    <label class="w-label" for="first_name"><?php echo esc_html(sc_t('frontend.first_name', 'First name')); ?></label>
                    <input class="w-input" type="text" id="first_name" data-name-part
                           autocomplete="given-name" required>
                </div>
                <div class="w-field">
                    <label class="w-label" for="last_name"><?php echo esc_html(sc_t('frontend.last_name', 'Last name')); ?></label>
                    <input class="w-input" type="text" id="last_name" data-name-part
                           autocomplete="family-name" required>
                </div>
            </div>

            <div class="w-field">
                <label class="w-label" for="email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                <input class="w-input" type="email" id="email" name="email" autocomplete="email"
                       placeholder="you@clinic.com" required>
            </div>

            <?php get_template_part('template-parts/public/phone-field', null, array(
                'hint' => sc_t('frontend.whatsapp_number_hint', 'Your ticket, e-badge and sign-in codes are sent here.'),
            )); ?>

            <div class="w-field">
                <label class="w-label" for="password"><?php echo esc_html(sc_t('frontend.password', 'Password')); ?></label>
                <span class="w-auth__pw">
                    <input class="w-input" type="password" id="password" name="password"
                           autocomplete="new-password" minlength="8" required>
                    <button class="w-auth__reveal" type="button" data-reveal="password"
                            aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </span>
                <span class="w-hint"><?php echo esc_html(sc_t('frontend.password_hint_8', 'At least 8 characters.')); ?></span>
            </div>

            <div class="w-field">
                <label class="w-label" for="confirm_password"><?php echo esc_html(sc_t('frontend.confirm_password', 'Confirm password')); ?></label>
                <span class="w-auth__pw">
                    <input class="w-input" type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password" required>
                    <button class="w-auth__reveal" type="button" data-reveal="confirm_password"
                            aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </span>
            </div>

            <label class="w-auth__check">
                <input type="checkbox" id="terms" name="terms" required>
                <span><?php printf(
                    wp_kses(
                        sc_t('frontend.agree_terms', 'I agree to the <a href="%1$s">Terms</a> and <a href="%2$s">Privacy policy</a>.'),
                        ['a' => ['href' => []]]
                    ),
                    esc_url(home_url('/terms/')),
                    esc_url(home_url('/privacy/'))
                ); ?></span>
            </label>

            <button class="w-btn w-btn--lg" type="submit">
                <?php echo esc_html(sc_t('frontend.continue', 'Continue')); ?>
            </button>

            <p class="w-auth__foot">
                <?php echo esc_html(sc_t('frontend.have_account', 'Already have an account?')); ?>
                <a href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
            </p>
        </form>

        <div class="w-auth__form" id="register-verify" hidden>
            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.confirm_number', 'Confirm your number')); ?></h1>
            <p class="w-otp-sent">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                <span><?php echo esc_html(sc_t('frontend.code_sent_to', 'We sent a 6-digit code on WhatsApp to')); ?> <strong id="register-to"></strong></span>
            </p>
            <form class="w-auth__form" id="register-verify-form" novalidate>
                <div class="w-field">
                    <label class="w-label" for="register-code"><?php echo esc_html(sc_t('frontend.code', 'Code')); ?></label>
                    <input class="w-input w-otp-code" id="register-code" type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required>
                </div>
                <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?></button>
                <div class="w-otp-row">
                    <button type="button" class="w-otp-link" id="register-back"><?php echo esc_html(sc_t('frontend.change_number', 'Change number')); ?></button>
                    <button type="button" class="w-otp-link" id="register-resend"><?php echo esc_html(sc_t('frontend.otp_resend', 'Send a new code')); ?></button>
                </div>
            </form>
        </div>
        </div>
    </div>
</section>

<?php
get_template_part('template-parts/public/otp-assets');
get_template_part('template-parts/public/footer', 'public');
