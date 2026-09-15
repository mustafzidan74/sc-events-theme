<?php
/**
 * Template Name: Login Page
 *
 * Two ways in: email + password (#login-form, submitted by public-scripts.js — its field names
 * email, password and remember are load-bearing) and a WhatsApp code (#otp-login, wisdom-otp.js,
 * inc/auth/sc-otp-handlers.php).
 *
 * @package sc_events
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$redirect = isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : home_url('/my-account/');
$codes_on = function_exists('sc_otp_available') && sc_otp_available();

get_template_part('template-parts/public/header', 'public');
?>

<section class="w-auth">
    <?php get_template_part('template-parts/public/auth-aside'); ?>

    <div class="w-auth__card">
        <div class="w-auth__form">
            <nav class="w-auth__modes" aria-label="<?php echo esc_attr(sc_t('frontend.account', 'Account')); ?>">
                <a class="w-auth__mode" aria-current="page" href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
                <a class="w-auth__mode" href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?>
                </a>
            </nav>

            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.welcome_back', 'Welcome back')); ?></h1>

            <?php if ($codes_on): ?>
            <div class="w-otp-switch" role="tablist" data-login-modes aria-label="<?php echo esc_attr(sc_t('frontend.sign_in_with', 'Sign in with')); ?>">
                <button type="button" role="tab" data-login-mode="password" aria-selected="true" aria-controls="login-form">
                    <?php echo esc_html(sc_t('frontend.with_password', 'Email & password')); ?>
                </button>
                <button type="button" role="tab" data-login-mode="code" aria-selected="false" aria-controls="otp-login">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.with_whatsapp_code', 'WhatsApp code')); ?>
                </button>
            </div>
            <?php endif; ?>

            <form class="w-auth__form" id="login-form">
                <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">

                <div class="w-field">
                    <label class="w-label" for="email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                    <input class="w-input" type="email" id="email" name="email" autocomplete="email"
                           placeholder="you@clinic.com" required>
                </div>

                <div class="w-field">
                    <label class="w-label" for="password">
                        <span class="w-auth__row">
                            <span><?php echo esc_html(sc_t('frontend.password', 'Password')); ?></span>
                            <a class="w-auth__link" href="<?php echo esc_url(home_url('/forgot-password/')); ?>">
                                <?php echo esc_html(sc_t('frontend.forgot', 'Forgot?')); ?>
                            </a>
                        </span>
                    </label>
                    <span class="w-auth__pw">
                        <input class="w-input" type="password" id="password" name="password"
                               autocomplete="current-password" required>
                        <button class="w-auth__reveal" type="button" data-reveal="password"
                                aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </span>
                </div>

                <label class="w-auth__check">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span><?php echo esc_html(sc_t('frontend.remember_me', 'Keep me signed in')); ?></span>
                </label>

                <button class="w-btn w-btn--lg" type="submit">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </button>
            </form>

            <?php if ($codes_on): ?>
            <div class="w-auth__form" id="otp-login" hidden>
                <p class="w-otp-msg" id="otp-login-msg" role="alert" hidden></p>

                <form class="w-auth__form" id="otp-login-send" novalidate>
                    <p class="w-auth__lede"><?php echo esc_html(sc_t('frontend.code_login_lede', 'We send a 6-digit code to the WhatsApp number on your account. No password needed.')); ?></p>
                    <?php get_template_part('template-parts/public/phone-field', null, array('id' => 'otp-login-phone', 'code_id' => 'otp-login-code')); ?>
                    <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.send_code', 'Send code')); ?></button>
                </form>

                <form class="w-auth__form" id="otp-login-verify" novalidate hidden>
                    <p class="w-otp-sent">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        <span><span id="otp-login-sent"></span> <strong id="otp-login-to"></strong></span>
                    </p>
                    <div class="w-field">
                        <label class="w-label" for="otp-login-input"><?php echo esc_html(sc_t('frontend.code', 'Code')); ?></label>
                        <input class="w-input w-otp-code" id="otp-login-input" name="code" type="text" inputmode="numeric"
                               autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required>
                    </div>
                    <label class="w-auth__check">
                        <input type="checkbox" id="otp-login-remember" value="1" checked>
                        <span><?php echo esc_html(sc_t('frontend.remember_me', 'Keep me signed in')); ?></span>
                    </label>
                    <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?></button>
                    <div class="w-otp-row">
                        <button type="button" class="w-otp-link" id="otp-login-change"><?php echo esc_html(sc_t('frontend.change_number', 'Change number')); ?></button>
                        <button type="button" class="w-otp-link" id="otp-login-resend"><?php echo esc_html(sc_t('frontend.otp_resend', 'Send a new code')); ?></button>
                    </div>
                    <div class="w-otp-accounts" id="otp-login-choose" hidden></div>
                </form>
            </div>
            <?php endif; ?>

            <p class="w-auth__foot">
                <?php echo esc_html(sc_t('frontend.no_account', "Don't have an account?")); ?>
                <a href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?>
                </a>
            </p>
        </div>
    </div>
</section>

<?php
if ($codes_on) {
    get_template_part('template-parts/public/otp-assets');
}
get_template_part('template-parts/public/footer', 'public');
