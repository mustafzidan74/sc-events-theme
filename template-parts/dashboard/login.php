<?php
/**
 * Dashboard sign-in — shown for any dashboard URL when nobody is logged in.
 * Posts to event_manager_login (inc/admin-dashboard/auth-ajax-handlers.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in() && (SC_Event_Manager_Dashboard::is_event_manager() || SC_Event_Manager_Dashboard::is_event_scanner())) {
    wp_safe_redirect(home_url(SC_Event_Manager_Dashboard::is_event_manager() ? '/event-manager-dashboard/home' : '/event-manager-dashboard/scanner'));
    exit;
}

require_once get_template_directory() . '/inc/admin-dashboard/dashboard-nav.php'; // sc_dashboard_asset()

$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$logo_id = get_option('sc_platform_logo');
$logo = $logo_id ? wp_get_attachment_image_src($logo_id, 'medium') : false;
$is_rtl = is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl());

$i18n = array(
    'signing_in'   => sc_t('login.signing_in', 'Signing in…'),
    'sign_in'      => sc_t('login.sign_in', 'Sign in'),
    'failed'       => sc_t('login.failed', 'Could not sign in. Please try again.'),
    'offline'      => sc_t('login.offline', 'Could not reach the server. Check your connection and try again.'),
    'show'         => sc_t('login.show_password', 'Show password'),
    'hide'         => sc_t('login.hide_password', 'Hide password'),
    'missing'      => sc_t('login.missing', 'Enter your email or username and your password.'),
);
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo esc_html(sc_t('login.title', 'Sign in') . ' · ' . $platform_name); ?></title>
    <link rel="icon" href="<?php echo esc_url(get_template_directory_uri() . '/assets/admin-dashboard/images/favicon.ico'); ?>" type="image/x-icon">
    <script>
    (function () {
        var p = null;
        try { p = localStorage.getItem('sc_dashboard_theme'); } catch (e) {}
        if (p === 'light' || p === 'dark') { document.documentElement.setAttribute('data-theme', p); }
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/tokens.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/components.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/auth.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/login.css')); ?>">
</head>
<body class="w-login">

<main class="w-login__main">
    <div class="w-login__brand">
        <?php if ($logo): ?>
            <img src="<?php echo esc_url($logo[0]); ?>" alt="<?php echo esc_attr($platform_name); ?>">
        <?php else: ?>
            <span class="w-login__name"><?php echo esc_html($platform_name); ?></span>
        <?php endif; ?>
    </div>

    <div class="w-auth__card w-login__card">
        <form class="w-auth__form" id="w-login-form" method="post" novalidate>
            <div>
                <h1 class="w-login__title"><?php echo esc_html(sc_t('login.heading', 'Sign in to the dashboard')); ?></h1>
                <p class="w-auth__lede"><?php echo esc_html(sc_t('login.lede', 'For event managers and scanner staff.')); ?></p>
            </div>

            <div class="w-login__error" id="w-login-error" role="alert" hidden></div>

            <?php wp_nonce_field('event_manager_login', 'login_nonce'); ?>

            <div class="w-field">
                <label class="w-label" for="user_login"><?php echo esc_html(sc_t('login.user', 'Email or username')); ?></label>
                <input class="w-input" id="user_login" name="user_login" type="text" autocomplete="username" autocapitalize="off" spellcheck="false" dir="ltr" required>
            </div>

            <div class="w-field">
                <label class="w-label" for="user_password">
                    <span class="w-auth__row">
                        <span><?php echo esc_html(sc_t('login.password', 'Password')); ?></span>
                        <a class="w-auth__link" href="<?php echo esc_url(home_url('/forgot-password/')); ?>"><?php echo esc_html(sc_t('login.forgot', 'Forgot password?')); ?></a>
                    </span>
                </label>
                <span class="w-auth__pw">
                    <input class="w-input" id="user_password" name="user_password" type="password" autocomplete="current-password" dir="ltr" required>
                    <button class="w-auth__reveal" type="button" id="w-login-reveal" aria-label="<?php echo esc_attr($i18n['show']); ?>" aria-pressed="false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </div>

            <label class="w-auth__check">
                <input type="checkbox" id="remember" name="remember_me" value="yes">
                <span><?php echo esc_html(sc_t('login.remember', 'Keep me signed in on this device')); ?></span>
            </label>

            <button class="w-btn w-btn--lg" type="submit" id="w-login-submit"><?php echo esc_html($i18n['sign_in']); ?></button>
        </form>
    </div>

    <p class="w-login__foot">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(sprintf(sc_t('login.go_site', '← %s website'), $platform_name)); ?></a>
    </p>
</main>

<script>
(function () {
    var i18n = <?php echo wp_json_encode($i18n); ?>;
    var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var form = document.getElementById('w-login-form');
    var error = document.getElementById('w-login-error');
    var submit = document.getElementById('w-login-submit');
    var user = document.getElementById('user_login');
    var pass = document.getElementById('user_password');
    var reveal = document.getElementById('w-login-reveal');

    function showError(msg) {
        error.textContent = msg;
        error.hidden = false;
    }

    function busy(on) {
        submit.disabled = on;
        submit.textContent = on ? i18n.signing_in : i18n.sign_in;
    }

    reveal.addEventListener('click', function () {
        var show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        reveal.setAttribute('aria-pressed', show ? 'true' : 'false');
        reveal.setAttribute('aria-label', show ? i18n.hide : i18n.show);
        pass.focus();
    });

    user.focus();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        error.hidden = true;
        user.removeAttribute('aria-invalid');
        pass.removeAttribute('aria-invalid');
        if (!user.value.trim() || !pass.value) {
            if (!user.value.trim()) { user.setAttribute('aria-invalid', 'true'); }
            if (!pass.value) { pass.setAttribute('aria-invalid', 'true'); }
            showError(i18n.missing);
            (user.value.trim() ? pass : user).focus();
            return;
        }

        var body = new URLSearchParams();
        body.set('action', 'event_manager_login');
        body.set('user_login', user.value.trim());
        body.set('user_password', pass.value);
        body.set('remember_me', document.getElementById('remember').checked ? 'yes' : 'no');
        body.set('nonce', form.querySelector('[name="login_nonce"]').value);

        busy(true);
        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (res) {
                if (res && res.success && res.data && res.data.redirect) {
                    window.location.assign(res.data.redirect);
                    return;
                }
                busy(false);
                showError((res && res.data && res.data.message) || i18n.failed);
                pass.select();
            })
            .catch(function () {
                busy(false);
                showError(i18n.offline);
            });
    });
})();
</script>
<script>
(function () {
    try {
        if (window.caches) {
            caches.keys().then(function (keys) {
                keys.forEach(function (k) { if (k.indexOf('sc-scanner-') === 0) { caches.delete(k); } });
            });
        }
        if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                regs.forEach(function (r) { if (r.scope.indexOf('/event-manager-dashboard/scanner') !== -1) { r.unregister(); } });
            });
        }
        if (window.indexedDB) {
            var req = indexedDB.open('sc-scanner');
            // Never create the database from here.
            req.onupgradeneeded = function () { req.transaction.abort(); };
            req.onsuccess = function () {
                var db = req.result;
                if (db.objectStoreNames.contains('lists')) {
                    db.transaction('lists', 'readwrite').objectStore('lists').clear();
                }
                db.close();
            };
        }
    } catch (e) {}
})();
</script>
</body>
</html>
