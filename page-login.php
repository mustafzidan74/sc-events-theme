<?php
/**
 * Template Name: Login Page
 *
 * The form contract is public-scripts.js: #login-form with email, password
 * and remember. Those names are load-bearing — the markup around them is not.
 *
 * @package sc_events
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

$redirect = isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : home_url('/my-account/');

get_template_part('template-parts/public/header', 'public');
?>

<section class="w-auth">
    <?php get_template_part('template-parts/public/auth-aside'); ?>

    <div class="w-auth__card">
        <form class="w-auth__form" id="login-form">
            <nav class="w-auth__modes" aria-label="<?php echo esc_attr(sc_t('frontend.account', 'Account')); ?>">
                <a class="w-auth__mode" aria-current="page" href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php echo esc_html(sc_t('frontend.sign_in', 'Sign in')); ?>
                </a>
                <a class="w-auth__mode" href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?>
                </a>
            </nav>

            <h1 class="w-auth__title"><?php echo esc_html(sc_t('frontend.welcome_back', 'Welcome back')); ?></h1>

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

            <p class="w-auth__foot">
                <?php echo esc_html(sc_t('frontend.no_account', "Don't have an account?")); ?>
                <a href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php echo esc_html(sc_t('frontend.create_account', 'Create account')); ?>
                </a>
            </p>
        </form>
    </div>
</section>

<?php get_template_part('template-parts/public/footer', 'public'); ?>
