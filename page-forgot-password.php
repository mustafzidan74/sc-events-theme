<?php
/**
 * Template Name: Forgot Password Page
 *
 * Reset with a code sent on WhatsApp: the account's number or email → the 6-digit code → a new
 * password (then signed in). Script: assets/frontend/js/wisdom-otp.js; handlers:
 * inc/auth/sc-otp-handlers.php. When no WhatsApp number can send codes, the page points to support.
 *
 * @package sc_events
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$reset_on = function_exists('sc_password_reset_available') && sc_password_reset_available();
$platform_whatsapp = preg_replace('/\D/', '', (string) get_option('sc_platform_whatsapp', ''));
if ($platform_whatsapp !== '' && strpos($platform_whatsapp, '0') === 0) {
    $platform_whatsapp = '20' . substr($platform_whatsapp, 1);
}
$help_link = $platform_whatsapp
    ? 'https://wa.me/' . $platform_whatsapp . '?text=' . rawurlencode(sc_t('frontend.reset_paused_wa', 'Hello, I need help signing in to my account.'))
    : home_url('/contact/');

get_template_part('template-parts/public/header', 'public');
?>
<section class="w-auth">
    <?php get_template_part('template-parts/public/auth-aside'); ?>

    <div class="w-auth__card">
        <div class="w-auth__form">
            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.reset_password', 'Reset your password')); ?></h1>

            <?php if (!$reset_on): ?>
            <p class="w-auth__lede"><?php echo esc_html(sc_t('frontend.reset_unavailable', 'Password reset by WhatsApp code is not available right now. Contact us and we will help you sign in.')); ?></p>
            <?php if ($platform_whatsapp): ?>
                <a class="w-btn w-btn--lg" href="<?php echo esc_url($help_link); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('frontend.contact_whatsapp', 'Contact us on WhatsApp')); ?></a>
            <?php endif; ?>
            <a class="w-btn w-btn--lg <?php echo $platform_whatsapp ? 'w-btn--outline' : ''; ?>" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html(sc_t('frontend.contact_page', 'Contact page')); ?></a>
            <?php else: ?>
            <div class="w-auth__form" id="reset-flow">
                <div class="w-steps" aria-hidden="true">
                    <div class="sc-step active" data-reset-step="ask"><span class="sc-step-circle">1</span><span><?php echo esc_html(sc_t('frontend.account', 'Account')); ?></span></div>
                    <div class="sc-step-line"></div>
                    <div class="sc-step" data-reset-step="code"><span class="sc-step-circle">2</span><span><?php echo esc_html(sc_t('frontend.code', 'Code')); ?></span></div>
                    <div class="sc-step-line"></div>
                    <div class="sc-step" data-reset-step="pass"><span class="sc-step-circle">3</span><span><?php echo esc_html(sc_t('frontend.new_password_short', 'New password')); ?></span></div>
                </div>

                <p class="w-otp-msg" id="reset-msg" role="alert" hidden></p>

                <form class="w-auth__form" id="reset-ask" novalidate>
                    <p class="w-auth__lede"><?php echo esc_html(sc_t('frontend.reset_lede_whatsapp', 'We send a 6-digit code to the WhatsApp number on your account.')); ?></p>
                    <div class="w-otp-switch" role="tablist" aria-label="<?php echo esc_attr(sc_t('frontend.find_account_by', 'Find my account by')); ?>">
                        <button type="button" role="tab" data-reset-by="phone" aria-selected="true"><?php echo esc_html(sc_t('frontend.whatsapp_number', 'WhatsApp number')); ?></button>
                        <button type="button" role="tab" data-reset-by="email" aria-selected="false"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></button>
                    </div>
                    <div id="reset-by-phone">
                        <?php get_template_part('template-parts/public/phone-field', null, array('id' => 'reset-phone', 'code_id' => 'reset-phone-code')); ?>
                    </div>
                    <div class="w-field" id="reset-by-email" hidden>
                        <label class="w-label" for="reset-email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                        <input class="w-input" type="email" id="reset-email" autocomplete="email" placeholder="you@clinic.com">
                    </div>
                    <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.send_code', 'Send code')); ?></button>
                </form>

                <form class="w-auth__form" id="reset-code-form" novalidate hidden>
                    <p class="w-otp-sent"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span id="reset-sent"></span></p>
                    <div class="w-field">
                        <label class="w-label" for="reset-code"><?php echo esc_html(sc_t('frontend.code', 'Code')); ?></label>
                        <input class="w-input w-otp-code" id="reset-code" type="text" inputmode="numeric" autocomplete="one-time-code"
                               maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required>
                    </div>
                    <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.verify', 'Verify')); ?></button>
                    <div class="w-otp-row">
                        <button type="button" class="w-otp-link" id="reset-change"><?php echo esc_html(sc_t('frontend.back', 'Back')); ?></button>
                        <button type="button" class="w-otp-link" id="reset-resend"><?php echo esc_html(sc_t('frontend.otp_resend', 'Send a new code')); ?></button>
                    </div>
                    <p class="w-auth__note">
                        <?php echo esc_html(sc_t('frontend.no_code_help', 'No code? Your account may not have a WhatsApp number.')); ?>
                        <a href="<?php echo esc_url($help_link); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('frontend.contact_us', 'Contact us')); ?></a>
                    </p>
                </form>

                <div class="w-auth__form" id="reset-choose-step" hidden>
                    <p class="w-auth__lede"><?php echo esc_html(sc_t('frontend.choose_account', 'This number is on more than one account. Which one?')); ?></p>
                    <div class="w-otp-accounts" id="reset-choose"></div>
                </div>

                <form class="w-auth__form" id="reset-pass-form" novalidate hidden>
                    <p class="w-auth__verified">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span><?php echo esc_html(sc_t('frontend.verified_set_password', 'Verified — now set your new password.')); ?></span>
                    </p>
                    <div class="w-field">
                        <label class="w-label" for="reset-new"><?php echo esc_html(sc_t('frontend.new_password', 'New password')); ?></label>
                        <span class="w-auth__pw">
                            <input class="w-input" type="password" id="reset-new" autocomplete="new-password" minlength="8" required>
                            <button class="w-auth__reveal" type="button" data-reveal="reset-new" aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </span>
                        <span class="w-hint"><?php echo esc_html(sc_t('frontend.password_hint_8', 'At least 8 characters.')); ?></span>
                    </div>
                    <div class="w-field">
                        <label class="w-label" for="reset-confirm"><?php echo esc_html(sc_t('frontend.confirm_password', 'Confirm password')); ?></label>
                        <input class="w-input" type="password" id="reset-confirm" autocomplete="new-password" required>
                    </div>
                    <button class="w-btn w-btn--lg" type="submit"><?php echo esc_html(sc_t('frontend.reset_password_action', 'Reset password')); ?></button>
                </form>
            </div>
            <?php endif; ?>

            <p class="w-auth__foot">
                <?php echo esc_html(sc_t('frontend.remember_password', 'Remember your password?')); ?>
                <a href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
            </p>
        </div>
    </div>
</section>

<?php
if ($reset_on) {
    get_template_part('template-parts/public/otp-assets');
}
get_template_part('template-parts/public/footer', 'public');
