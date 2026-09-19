<?php
/**
 * قسم «حمّل التطبيق» — قبل الفوتر في كل الصفحات العامة.
 *
 * بنفس نظام تصميم الموقع (w-section + لوحة غامقة زي حائط الرعاة).
 * جوجل: تحميل مباشر لملف التطبيق من السيرفر (public_html/app/wisdom.apk).
 * آبل: صفحة التطبيق على App Store.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$sc_app_android_url = home_url('/app/wisdom.apk');
$sc_app_ios_url     = 'https://apps.apple.com/app/id6762920225';
?>
<!--===== APP DOWNLOAD =======-->
<section class="w-section w-app" id="app-download">
    <div class="w-section__head">
        <h2 class="w-section__title"><?php echo esc_html(sc_t('frontend.get_the_app', 'Get the app')); ?></h2>
    </div>

    <div class="w-app__panel">
        <div class="w-app__text">
            <h3 class="w-app__title"><?php echo esc_html(sc_t('frontend.get_the_app_title', 'Wisdom in your pocket')); ?></h3>
            <p class="w-app__sub"><?php echo esc_html(sc_t('frontend.get_the_app_desc', 'Your tickets, QR code, schedule and certificates — always with you.')); ?></p>
        </div>

        <div class="w-app__badges">
            <a class="w-app__badge" href="<?php echo esc_url($sc_app_android_url); ?>" download="wisdom.apk" aria-label="Get it on Google Play">
                <span class="w-app__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="30" height="30">
                        <path fill="#00D7FE" d="M3.6 2.3 13.9 12 3.6 21.7c-.3-.2-.5-.6-.5-1V3.3c0-.4.2-.8.5-1z"/>
                        <path fill="#00F076" d="M3.6 2.3c.3-.2.7-.2 1.1 0L17 9.1 13.9 12 3.6 2.3z"/>
                        <path fill="#FF3A44" d="M13.9 12 17 14.9 4.7 21.7c-.4.2-.8.2-1.1 0L13.9 12z"/>
                        <path fill="#FFD500" d="M17 9.1l3.5 2c.7.4.7 1.4 0 1.8l-3.5 2L13.9 12 17 9.1z"/>
                    </svg>
                </span>
                <span class="w-app__label">
                    <small><?php echo esc_html(sc_t('frontend.get_it_on', 'GET IT ON')); ?></small>
                    <strong>Google Play</strong>
                </span>
            </a>

            <a class="w-app__badge" href="<?php echo esc_url($sc_app_ios_url); ?>" target="_blank" rel="noopener" aria-label="Download on the App Store">
                <span class="w-app__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="30" height="30">
                        <path fill="#fff" d="M16.4 12.7c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.7-2-1.6-.2-3.1.9-3.9.9-.8 0-2-.9-3.4-.9-1.7 0-3.3 1-4.2 2.6-1.8 3.1-.5 7.8 1.3 10.3.9 1.2 1.9 2.6 3.2 2.6 1.3-.1 1.8-.8 3.4-.8s2 .8 3.4.8c1.4 0 2.3-1.3 3.1-2.5 1-1.4 1.4-2.8 1.4-2.9-.1 0-2.8-1-2.8-4.2zM13.9 5.1c.7-.9 1.2-2 1-3.2-1 0-2.2.7-3 1.5-.7.8-1.2 2-1.1 3.1 1.2.1 2.3-.6 3.1-1.4z"/>
                    </svg>
                </span>
                <span class="w-app__label">
                    <small><?php echo esc_html(sc_t('frontend.download_on_the', 'Download on the')); ?></small>
                    <strong>App Store</strong>
                </span>
            </a>
        </div>
    </div>
</section>

<style>
/* لوحة غامقة بنفس مقاسات حائط الرعاة (w-sponsors) */
.w-app__panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: clamp(20px, 3vw, 40px);
    border-radius: 28px;
    padding: clamp(26px, 3.5vw, 56px);
    background: var(--w-ink, #141826);
    color: #fff;
    font-family: var(--w-font, Inter, system-ui, sans-serif);
}
.w-app__text { flex: 1 1 320px; min-width: 0; }
.w-app__title {
    margin: 0 0 8px;
    color: #fff;
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.01em;
}
.w-app__sub {
    margin: 0;
    color: var(--w-ink-200, #C9CEDB);
    font-size: 1rem;
    line-height: 1.6;
    max-width: 52ch;
}
.w-app__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    flex: 0 0 auto;
}
.w-app__badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 196px;
    padding: 10px 18px 10px 14px;
    border-radius: var(--w-radius-lg, 12px);
    background: var(--w-ink-950, #0B0E17);
    border: 1px solid var(--w-ink-600, #3B4257);
    color: #fff;
    text-decoration: none;
    transition: transform .2s ease, border-color .2s ease;
}
.w-app__badge:hover,
.w-app__badge:focus-visible {
    color: #fff;
    text-decoration: none;
    transform: translateY(-2px);
    border-color: var(--w-ink-300, #A9AFC0);
    outline: none;
}
.w-app__icon {
    display: inline-flex;
    width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    flex: none;
}
.w-app__label {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
    text-align: start;
}
.w-app__label small {
    font-size: var(--w-caption-size, 0.75rem);
    font-weight: var(--w-caption-weight, 600);
    letter-spacing: .04em;
    color: var(--w-ink-200, #C9CEDB);
    text-transform: uppercase;
}
.w-app__label strong {
    font-size: 1.25rem;
    font-weight: 600;
    letter-spacing: -.01em;
}
@media (max-width: 640px) {
    .w-app__panel { text-align: center; justify-content: center; }
    .w-app__sub { margin-inline: auto; }
    .w-app__badges { width: 100%; justify-content: center; }
    .w-app__badge { flex: 1 1 100%; justify-content: center; }
}
</style>
