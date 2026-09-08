<?php
/**
 * Template Name: Register Page
 *
 * The form contract is public-scripts.js: #register-form with name, email,
 * phone, password, confirm_password and terms. The name goes over the wire as
 * one string; the two visible boxes feed the hidden field (wisdom-auth.js).
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

            <div class="w-field">
                <label class="w-label" for="phone"><?php echo esc_html(sc_t('frontend.mobile_for_badge', 'Mobile — for your e-badge')); ?></label>
                <span class="w-auth__tel">
                    <select class="w-select" id="phone_code" name="phone_code"
                            aria-label="<?php echo esc_attr(sc_t('frontend.country_code', 'Country code')); ?>">
                        <option value="+20" selected>+20</option>
                        <option value="+966">+966</option>
                        <option value="+971">+971</option>
                        <option value="+965">+965</option>
                        <option value="+974">+974</option>
                        <option value="+218">+218</option>
                        <option value="+249">+249</option>
                    </select>
                    <input class="w-input" type="tel" id="phone" name="phone" autocomplete="tel"
                           placeholder="100 000 0000">
                </span>
            </div>

            <div class="w-field">
                <label class="w-label" for="password"><?php echo esc_html(sc_t('frontend.password', 'Password')); ?></label>
                <span class="w-auth__pw">
                    <input class="w-input" type="password" id="password" name="password"
                           autocomplete="new-password" minlength="6" required>
                    <button class="w-auth__reveal" type="button" data-reveal="password"
                            aria-label="<?php echo esc_attr(sc_t('frontend.show_password', 'Show password')); ?>">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </span>
                <span class="w-hint"><?php echo esc_html(sc_t('frontend.password_hint', 'At least 6 characters.')); ?></span>
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
    </div>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
