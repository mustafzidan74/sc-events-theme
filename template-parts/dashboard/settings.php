<?php
/**
 * Settings — one form per section, each saved on its own.
 * Sections: inc/admin-dashboard/settings-dashboard.php (sc_settings_save, sc_settings_test_email).
 * Payment gateways: sc_toggle_payment_gateway / sc_save_gateway_settings / sc_test_gateway_connection.
 * Legal pages: sc_create_content_page / sc_get_page_content / sc_update_page_content.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_form;
$load_wd_form = true;

$is_admin = current_user_can('manage_options');
$user = wp_get_current_user();
$dashboard_url = home_url('/event-manager-dashboard/');
$opt = function ($name, $default = '') {
    $v = get_option($name, $default);
    // Older saves stored backslashes before quotes.
    return is_string($v) ? wp_unslash($v) : $v;
};
$img = function ($id) {
    return $id ? (string) wp_get_attachment_image_url((int) $id, 'medium') : '';
};
$events = $wpdb->get_results("SELECT id, title, status FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') ORDER BY start_date DESC LIMIT 100");
$currencies = sc_get_currencies();
$currency = sc_get_currency_settings();
$privacy_id = (int) get_option('wp_page_for_privacy_policy');
$privacy = $privacy_id ? get_post($privacy_id) : null;
$terms = get_page_by_path('terms-and-conditions') ?: get_page_by_path('terms');
$smtp_active = function_exists('wp_mail_smtp') || class_exists('WPMailSMTP\\Core');

$gateways = array(
    'paymob'     => array('Paymob', sc_t('settings.paymob_desc', 'Cards and wallets in Egypt'), array('api_key' => array('API key', true), 'integration_id' => array('Integration ID', false), 'iframe_id' => array('iFrame ID', false), 'hmac_secret' => array('HMAC secret', true))),
    'kashier'    => array('Kashier', sc_t('settings.kashier_desc', 'Cards and wallets in Egypt'), array('merchant_id' => array('Merchant ID', false), 'api_key' => array('API key', true), 'secret_key' => array('Secret key', true))),
    'stripe'     => array('Stripe', sc_t('settings.stripe_desc', 'International cards'), array('publishable_key' => array('Publishable key', false), 'secret_key' => array('Secret key', true), 'webhook_secret' => array('Webhook secret', true))),
    'myfatoorah' => array('MyFatoorah', sc_t('settings.myfatoorah_desc', 'Payments in the Gulf'), array('api_key' => array('API key', true))),
);

$nav = array(
    'brand'    => sc_t('settings.brand', 'Brand'),
    'contact'  => sc_t('settings.contact', 'Contact and social'),
    'homepage' => sc_t('settings.homepage', 'Homepage'),
    'currency' => sc_t('settings.currency', 'Currency'),
    'legal'    => sc_t('settings.legal', 'Legal pages'),
);
if ($is_admin) {
    $nav += array(
        'identity' => sc_t('settings.identity', 'Site identity'),
        'language' => sc_t('settings.language', 'Language'),
        'payments' => sc_t('settings.payments', 'Payments'),
        'email'    => sc_t('settings.email', 'Email'),
    );
}
$nav['account'] = sc_t('settings.your_account', 'Your account');

$save_btn = function ($label = '') {
    ?>
    <div class="w-section__foot">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($label ?: sc_t('dashboard_pages.save', 'Save')); ?></button>
    </div>
    <?php
};
$media = function ($name, $id, $label, $contain = true) use ($img) {
    $url = $img($id);
    ?>
    <div class="w-media w-media--square<?php echo $contain ? ' w-media--contain' : ''; ?>" data-w-media="<?php echo esc_attr($label); ?>">
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo $id ? (int) $id : ''; ?>">
        <div class="w-media__preview">
            <img src="<?php echo esc_url($url); ?>" alt=""<?php echo $url ? '' : ' hidden'; ?>>
            <span class="w-media__empty" role="button" tabindex="0"<?php echo $url ? ' hidden' : ''; ?>><?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?></span>
        </div>
        <div class="w-media__actions">
            <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
            <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $url ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
        </div>
    </div>
    <?php
};
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('settings.general_settings', 'Settings')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html($is_admin
                ? sc_t('settings.sub_admin', 'Each section saves on its own. Payments, language and site identity are only shown to administrators.')
                : sc_t('settings.sub_manager', 'Each section saves on its own. Payments, language and site identity are managed by the site administrator.')); ?></p>
        </div>
    </div>

    <div class="w-form-layout w-settings">
        <nav class="w-secnav" data-w-secnav aria-label="<?php echo esc_attr(sc_t('settings.sections', 'Settings sections')); ?>">
            <?php $first = true; foreach ($nav as $key => $label): ?>
                <a href="#<?php echo esc_attr($key); ?>" aria-current="<?php echo $first ? 'true' : 'false'; ?>"><span><?php echo esc_html($label); ?></span></a>
            <?php $first = false; endforeach; ?>
        </nav>

        <div class="w-form-main">

            <!-- Brand -->
            <form class="w-section" id="brand" data-section="brand" novalidate aria-labelledby="brand-t">
                <div class="w-section__head"><h2 id="brand-t"><?php echo esc_html($nav['brand']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.brand_hint', 'Shown across the website, emails and tickets.')); ?></span></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="st-name"><?php echo esc_html(sc_t('settings.platform_name', 'Platform name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="st-name" name="platform_name" value="<?php echo esc_attr($opt('sc_platform_name', get_bloginfo('name'))); ?>" maxlength="100" required>
                    </div>
                    <div class="w-field">
                        <label for="st-desc"><?php echo esc_html(sc_t('settings.platform_description', 'Description')); ?></label>
                        <textarea class="form-control" id="st-desc" name="platform_description" rows="4" dir="auto"><?php echo esc_textarea($opt('sc_platform_description')); ?></textarea>
                        <p class="w-field__help"><?php echo esc_html(sc_t('settings.platform_description_help', 'Used in the website footer and as the homepage description.')); ?></p>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <span class="w-field__label"><?php echo esc_html(sc_t('settings.logo', 'Logo')); ?></span>
                            <?php $media('platform_logo', (int) get_option('sc_platform_logo'), sc_t('settings.logo', 'Logo')); ?>
                            <p class="w-field__help"><?php echo esc_html(sc_t('settings.logo_help', 'For light backgrounds: dashboard, tickets, emails.')); ?></p>
                        </div>
                        <div class="w-field">
                            <span class="w-field__label"><?php echo esc_html(sc_t('settings.logo_light', 'Logo on dark backgrounds')); ?></span>
                            <?php $media('platform_logo_light', (int) get_option('sc_platform_logo_light'), sc_t('settings.logo_light', 'Logo on dark backgrounds')); ?>
                            <p class="w-field__help"><?php echo esc_html(sc_t('settings.logo_light_help', 'Website header and footer.')); ?></p>
                        </div>
                    </div>
                    <div class="w-fields w-fields--3">
                        <div class="w-field">
                            <label for="st-primary"><?php echo esc_html(sc_t('settings.primary_color', 'Main colour')); ?></label>
                            <input type="color" class="form-control w-color" id="st-primary" name="primary_color" value="<?php echo esc_attr(get_option('sc_primary_color', '#667eea') ?: '#667eea'); ?>">
                        </div>
                        <div class="w-field">
                            <label for="st-secondary"><?php echo esc_html(sc_t('settings.secondary_color', 'Second colour')); ?></label>
                            <input type="color" class="form-control w-color" id="st-secondary" name="secondary_color" value="<?php echo esc_attr(get_option('sc_secondary_color', '#764ba2') ?: '#764ba2'); ?>">
                        </div>
                        <div class="w-field">
                            <span class="w-field__label"><?php echo esc_html(sc_t('settings.default_theme', 'Website theme')); ?></span>
                            <div class="w-choice" role="radiogroup">
                                <?php foreach (array('dark' => sc_t('settings.dark', 'Dark'), 'light' => sc_t('settings.light', 'Light')) as $v => $l): ?>
                                    <label class="w-choice__item"><input type="radio" name="default_theme" value="<?php echo esc_attr($v); ?>" <?php checked(get_option('sc_default_theme', 'dark'), $v); ?>><span class="w-choice__box"><span><?php echo esc_html($l); ?></span></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Contact -->
            <form class="w-section" id="contact" data-section="contact" novalidate aria-labelledby="contact-t">
                <div class="w-section__head"><h2 id="contact-t"><?php echo esc_html($nav['contact']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.contact_hint', 'Contact page, footer and the WhatsApp button.')); ?></span></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-fields w-fields--3">
                    <div class="w-field">
                        <label for="st-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?></label>
                        <input type="email" class="form-control w-ltr" id="st-email" name="platform_email" value="<?php echo esc_attr($opt('sc_platform_email', get_option('admin_email'))); ?>">
                        <p class="w-field__help"><?php echo esc_html(sc_t('settings.contact_email_help', 'Contact form messages are sent here.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="st-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                        <input type="tel" class="form-control w-ltr" id="st-phone" name="platform_phone" value="<?php echo esc_attr($opt('sc_platform_phone')); ?>">
                    </div>
                    <div class="w-field">
                        <label for="st-wa">WhatsApp</label>
                        <input type="tel" class="form-control w-ltr" id="st-wa" name="platform_whatsapp" value="<?php echo esc_attr($opt('sc_platform_whatsapp')); ?>" placeholder="+201…">
                    </div>
                </div>
                <div class="w-fields w-fields--2 mt-3">
                    <?php foreach (array('facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X (Twitter)', 'linkedin' => 'LinkedIn') as $net => $label): ?>
                        <div class="w-field">
                            <label for="st-<?php echo esc_attr($net); ?>"><?php echo esc_html($label); ?></label>
                            <input type="url" class="form-control w-ltr" id="st-<?php echo esc_attr($net); ?>" name="platform_<?php echo esc_attr($net); ?>" value="<?php echo esc_attr($opt('sc_platform_' . $net)); ?>" placeholder="https://">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Homepage -->
            <form class="w-section" id="homepage" data-section="homepage" novalidate aria-labelledby="homepage-t">
                <div class="w-section__head"><h2 id="homepage-t"><?php echo esc_html($nav['homepage']); ?></h2></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup">
                        <label class="w-choice__item"><input type="radio" name="homepage_mode" value="normal" <?php checked(get_option('sc_homepage_mode', 'normal') !== 'single_event'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('settings.normal_listing', 'List of events')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('settings.normal_listing_help', 'The homepage shows upcoming events')); ?></span></span></span></label>
                        <label class="w-choice__item"><input type="radio" name="homepage_mode" value="single_event" <?php checked(get_option('sc_homepage_mode', 'normal'), 'single_event'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('settings.single_event', 'One event')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('settings.single_event_help', 'The homepage is the featured event')); ?></span></span></span></label>
                    </div>
                    <div class="w-field">
                        <label for="st-featured"><?php echo esc_html(sc_t('settings.featured_event', 'Featured event')); ?></label>
                        <select class="form-control" id="st-featured" name="featured_event_id">
                            <option value="0"><?php echo esc_html(sc_t('settings.no_featured', 'None')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) get_option('sc_featured_event_id', 0), (int) $ev->id); ?>><?php echo esc_html($ev->title . ($ev->status === 'completed' ? ' (' . sc_t('dashboard_pages.completed', 'completed') . ')' : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="w-field__help"><?php echo esc_html(sc_t('settings.featured_help', 'Also drives the countdown on the dashboard home and the default programme.')); ?></p>
                    </div>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Currency -->
            <form class="w-section" id="currency" data-section="currency" novalidate aria-labelledby="currency-t">
                <div class="w-section__head"><h2 id="currency-t"><?php echo esc_html($nav['currency']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.example', 'Example:')); ?> <strong class="w-ltr" id="cur-preview"></strong></span></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="st-cur"><?php echo esc_html(sc_t('settings.currency', 'Currency')); ?></label>
                        <select class="form-control" id="st-cur" name="currency_code">
                            <?php foreach ($currencies as $code => $c): ?>
                                <option value="<?php echo esc_attr($code); ?>" data-symbol="<?php echo esc_attr($c['symbol']); ?>" <?php selected($currency['code'], $code); ?>><?php echo esc_html($code . ' — ' . $c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="st-pos"><?php echo esc_html(sc_t('settings.currency_position', 'Symbol position')); ?></label>
                        <select class="form-control" id="st-pos" name="currency_position">
                            <?php foreach (array('after' => sc_t('settings.after', 'After the amount'), 'after_no_space' => sc_t('settings.after_no_space', 'After, no space'), 'before' => sc_t('settings.before', 'Before the amount'), 'before_no_space' => sc_t('settings.before_no_space', 'Before, no space')) as $v => $l): ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($currency['position'], $v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="st-thou"><?php echo esc_html(sc_t('settings.thousand_separator', 'Thousands separator')); ?></label>
                        <select class="form-control" id="st-thou" name="thousand_separator">
                            <?php foreach (array(',' => ', (1,000)', '.' => '. (1.000)', ' ' => sc_t('settings.space', 'Space') . ' (1 000)', '' => sc_t('settings.none', 'None') . ' (1000)') as $v => $l): ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($currency['thousand_separator'], $v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="st-dec"><?php echo esc_html(sc_t('settings.decimal_separator', 'Decimal mark')); ?></label>
                            <select class="form-control" id="st-dec" name="decimal_separator">
                                <?php foreach (array('.' => '.', ',' => ',') as $v => $l): ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($currency['decimal_separator'], $v); ?>><?php echo esc_html($l); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-field">
                            <label for="st-places"><?php echo esc_html(sc_t('settings.decimal_places', 'Decimals')); ?></label>
                            <select class="form-control" id="st-places" name="decimal_places">
                                <?php for ($i = 0; $i <= 3; $i++): ?><option value="<?php echo $i; ?>" <?php selected($currency['decimal_places'], $i); ?>><?php echo $i; ?></option><?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Legal pages -->
            <section class="w-section" id="legal" aria-labelledby="legal-t">
                <div class="w-section__head"><h2 id="legal-t"><?php echo esc_html($nav['legal']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.legal_hint', 'Linked from checkout and the website footer.')); ?></span></div>
                <div class="w-legal">
                    <?php foreach (array('privacy' => array(sc_t('settings.privacy_policy', 'Privacy policy'), $privacy), 'terms' => array(sc_t('settings.terms', 'Terms and conditions'), $terms)) as $type => $row): ?>
                        <div class="w-legal__row">
                            <div class="w-stack">
                                <strong><?php echo esc_html($row[0]); ?></strong>
                                <span class="w-sub"><?php echo esc_html($row[1] ? sprintf(sc_t('settings.updated_on', 'Updated %s'), mysql2date('j M Y', $row[1]->post_modified)) : sc_t('settings.not_created', 'Not created yet')); ?></span>
                            </div>
                            <div class="w-aside-actions">
                                <?php if ($row[1]): ?>
                                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url(get_permalink($row[1])); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('dashboard_pages.view', 'View')); ?></a>
                                    <button type="button" class="btn btn-sm btn-secondary" data-legal-edit="<?php echo (int) $row[1]->ID; ?>"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-legal-create="<?php echo esc_attr($type); ?>"><?php echo esc_html(sc_t('settings.create_from_template', 'Create from template')); ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($is_admin): ?>
            <!-- Site identity -->
            <form class="w-section" id="identity" data-section="identity" novalidate aria-labelledby="identity-t">
                <div class="w-section__head"><h2 id="identity-t"><?php echo esc_html($nav['identity']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.identity_hint', 'Browser tabs, search results and bookmarks.')); ?></span></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-speaker-top">
                    <div class="w-field w-speaker-top__photo">
                        <span class="w-field__label"><?php echo esc_html(sc_t('settings.site_icon', 'Site icon')); ?></span>
                        <?php $media('site_icon', (int) get_option('site_icon'), sc_t('settings.site_icon', 'Site icon'), false); ?>
                        <p class="w-field__help"><?php echo esc_html(sc_t('settings.site_icon_help', 'Square, at least 512 × 512 px.')); ?></p>
                    </div>
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="st-title"><?php echo esc_html(sc_t('settings.site_title', 'Site title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="st-title" name="site_title" value="<?php echo esc_attr(get_option('blogname')); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="st-tagline"><?php echo esc_html(sc_t('settings.site_tagline', 'Tagline')); ?></label>
                            <input type="text" class="form-control" id="st-tagline" name="site_tagline" value="<?php echo esc_attr(get_option('blogdescription')); ?>">
                        </div>
                    </div>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Language -->
            <form class="w-section" id="language" data-section="language" novalidate aria-labelledby="language-t">
                <div class="w-section__head"><h2 id="language-t"><?php echo esc_html($nav['language']); ?></h2></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-choice w-choice--stack" role="radiogroup">
                    <?php $site_lang = (string) get_option('sc_site_language', ''); ?>
                    <label class="w-choice__item"><input type="radio" name="site_language" value="en" <?php checked($site_lang, 'en'); ?>><span class="w-choice__box"><span>English<span class="w-choice__sub"><?php echo esc_html(sc_t('settings.lang_en_help', 'Whole site in English; the language switch is hidden')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="site_language" value="ar" <?php checked($site_lang, 'ar'); ?>><span class="w-choice__box"><span>العربية<span class="w-choice__sub"><?php echo esc_html(sc_t('settings.lang_ar_help', 'Whole site in Arabic; the language switch is hidden')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="site_language" value="" <?php checked($site_lang, ''); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('settings.lang_visitor', 'Let each visitor choose')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('settings.lang_visitor_help', 'Shows the language switch')); ?></span></span></span></label>
                </div>
                <?php $save_btn(); ?>
            </form>

            <!-- Payments -->
            <section class="w-section" id="payments" aria-labelledby="payments-t">
                <div class="w-section__head"><h2 id="payments-t"><?php echo esc_html($nav['payments']); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('settings.payments_hint', 'Saved keys are never shown again. Leave a key empty to keep it.')); ?></span></div>
                <div class="w-gateways">
                    <?php foreach ($gateways as $code => $g):
                        $s = get_option('sc_gateway_' . $code, array());
                        $s = is_array($s) ? $s : array();
                        $enabled = !empty($s['enabled']);
                        // The gateway class treats a missing test_mode as live, so show it that way.
                        $test = !empty($s['test_mode']);
                        ?>
                        <form class="w-gateway<?php echo $enabled ? ' is-on' : ''; ?>" data-gateway="<?php echo esc_attr($code); ?>" novalidate autocomplete="off">
                            <input type="hidden" name="gateway_code" value="<?php echo esc_attr($code); ?>">
                            <div class="w-gateway__head">
                                <div class="w-stack">
                                    <strong><?php echo esc_html($g[0]); ?>
                                        <?php if ($enabled): ?><span class="w-tag <?php echo $test ? 'w-tag--gold' : 'w-tag--teal'; ?>"><?php echo esc_html($test ? sc_t('settings.test_mode_tag', 'Test mode') : sc_t('settings.live_tag', 'Live')); ?></span><?php endif; ?>
                                    </strong>
                                    <span class="w-sub"><?php echo esc_html($g[1]); ?></span>
                                </div>
                                <label class="w-switch"><input type="checkbox" data-gateway-toggle <?php checked($enabled); ?> aria-label="<?php echo esc_attr(sprintf(sc_t('settings.enable_gateway', 'Use %s at checkout'), $g[0])); ?>"><span class="w-switch__track" aria-hidden="true"></span></label>
                            </div>
                            <details class="w-gateway__body">
                                <summary><?php echo esc_html(sc_t('settings.gateway_settings', 'Keys and mode')); ?></summary>
                                <div class="w-errors" data-w-errors hidden></div>
                                <div class="w-fields w-fields--2">
                                    <?php foreach ($g[2] as $field => $meta):
                                        $has = !empty($s[$field]); ?>
                                        <div class="w-field">
                                            <label for="gw-<?php echo esc_attr($code . '-' . $field); ?>"><?php echo esc_html($meta[0]); ?></label>
                                            <?php if ($meta[1]): ?>
                                                <input type="password" class="form-control w-ltr" id="gw-<?php echo esc_attr($code . '-' . $field); ?>" name="<?php echo esc_attr($field); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($has ? sc_t('settings.saved_keep', 'Saved — leave empty to keep') : sc_t('settings.not_set', 'Not set')); ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control w-ltr" id="gw-<?php echo esc_attr($code . '-' . $field); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr((string) ($s[$field] ?? '')); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($code === 'myfatoorah'): ?><input type="hidden" name="country_iso" value="<?php echo esc_attr((string) ($s['country_iso'] ?? 'EGY')); ?>"><?php endif; ?>
                                </div>
                                <label class="w-switch mt-3"><input type="checkbox" name="test_mode" value="1" <?php checked($test); ?>><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('settings.test_mode', 'Test mode')); ?></strong><span><?php echo esc_html(sc_t('settings.test_mode_help', 'Use the gateway\'s test keys; no real money moves.')); ?></span></span></label>
                                <div class="w-field mt-3">
                                    <span class="w-field__label"><?php echo esc_html(sc_t('settings.callback_url', 'Callback URL to give the gateway')); ?></span>
                                    <input type="text" class="form-control w-ltr" value="<?php echo esc_attr(rest_url('sc-events/v1/payment/callback/' . $code)); ?>" readonly onclick="this.select()">
                                </div>
                                <div class="w-aside-actions mt-3">
                                    <button type="submit" class="btn btn-sm btn-primary" data-w-save><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                                    <button type="button" class="btn btn-sm btn-secondary" data-gateway-test <?php disabled($code === 'kashier'); ?> title="<?php echo esc_attr($code === 'kashier' ? sc_t('settings.kashier_no_test', 'Kashier has no connection test') : ''); ?>"><?php echo esc_html(sc_t('settings.test_saved', 'Test saved keys')); ?></button>
                                    <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
                                </div>
                            </details>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Email -->
            <section class="w-section" id="email" aria-labelledby="email-t">
                <div class="w-section__head"><h2 id="email-t"><?php echo esc_html($nav['email']); ?></h2></div>
                <p class="mb-3"><?php echo esc_html($smtp_active
                    ? sc_t('settings.email_smtp', 'Emails (tickets, certificates, contact messages) are sent through the WP Mail SMTP plugin. The mail server and password are set there.')
                    : sc_t('settings.email_no_smtp', 'The WP Mail SMTP plugin is not active, so emails use the server\'s default mail and often land in spam.')); ?></p>
                <div class="w-aside-actions">
                    <?php if ($smtp_active): ?>
                        <a class="btn btn-sm btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-smtp')); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('settings.open_smtp', 'Open WP Mail SMTP')); ?></a>
                        <a class="btn btn-sm btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-smtp-tools&tab=debug-events')); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('settings.email_errors', 'Delivery errors')); ?></a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-primary" id="test-email"><?php echo esc_html(sprintf(sc_t('settings.send_test_to', 'Send a test to %s'), $user->user_email)); ?></button>
                </div>
            </section>
            <?php endif; ?>

            <!-- Account -->
            <form class="w-section" id="account" data-section="account" novalidate autocomplete="off" aria-labelledby="account-t">
                <div class="w-section__head"><h2 id="account-t"><?php echo esc_html($nav['account']); ?></h2><span class="w-section__hint w-ltr"><?php echo esc_html($user->user_login); ?></span></div>
                <div class="w-errors" data-w-errors hidden></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--3">
                        <div class="w-field">
                            <label for="ac-first"><?php echo esc_html(sc_t('dashboard_pages.first_name', 'First name')); ?></label>
                            <input type="text" class="form-control" id="ac-first" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" autocomplete="given-name">
                        </div>
                        <div class="w-field">
                            <label for="ac-last"><?php echo esc_html(sc_t('dashboard_pages.last_name', 'Last name')); ?></label>
                            <input type="text" class="form-control" id="ac-last" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" autocomplete="family-name">
                        </div>
                        <div class="w-field">
                            <label for="ac-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?></label>
                            <input type="email" class="form-control w-ltr" id="ac-email" name="email" value="<?php echo esc_attr($user->user_email); ?>" autocomplete="email" required>
                        </div>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="ac-new"><?php echo esc_html(sc_t('dashboard_pages.new_password', 'New password')); ?></label>
                            <input type="password" class="form-control w-ltr" id="ac-new" name="new_password" autocomplete="new-password" minlength="8">
                            <p class="w-field__help"><?php echo esc_html(sc_t('settings.new_password_help', 'Leave empty to keep it. At least 8 characters.')); ?></p>
                        </div>
                        <div class="w-field">
                            <label for="ac-current"><?php echo esc_html(sc_t('settings.current_password', 'Current password')); ?></label>
                            <input type="password" class="form-control w-ltr" id="ac-current" name="current_password" autocomplete="current-password">
                            <p class="w-field__help"><?php echo esc_html(sc_t('settings.current_password_help', 'Needed to change your email or password.')); ?></p>
                        </div>
                    </div>
                </div>
                <?php $save_btn(); ?>
            </form>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('settings.signed_in_as', 'Signed in as')); ?></span>
                <p class="mb-1"><strong><?php echo esc_html($user->display_name); ?></strong></p>
                <p class="w-sub mb-0"><?php echo esc_html($is_admin ? sc_t('settings.role_admin', 'Administrator — every section') : sc_t('settings.role_manager', 'Event manager — payments, language and site identity are for administrators')); ?></p>
            </div>
            <?php if ($is_admin): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('settings.more', 'More')); ?></span>
                <div class="w-aside-actions">
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($dashboard_url . 'module-manager'); ?>"><?php echo esc_html(sc_t('dashboard_pages.module_manager', 'Modules')); ?></a>
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url(admin_url()); ?>"><?php echo esc_html('WP Admin'); ?></a>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

</div>
</div>

<div class="modal fade" id="legalModal" tabindex="-1" role="dialog" aria-labelledby="legal-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="legal-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="legal-title"><?php echo esc_html(sc_t('settings.edit_page', 'Edit page')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="page_id">
                    <div class="w-fields">
                        <div class="w-field"><label class="w-field__label" for="lg-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?></label><input type="text" class="form-control" id="lg-title" name="title" dir="auto"></div>
                        <div class="w-field"><label class="w-field__label" for="lg-content"><?php echo esc_html(sc_t('settings.page_content', 'Content')); ?></label><textarea class="form-control w-legal__editor" id="lg-content" name="content" rows="16" dir="auto"></textarea><p class="w-field__help"><?php echo esc_html(sc_t('settings.page_content_help', 'Basic HTML is kept: headings, paragraphs, lists, links, bold.')); ?></p></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="legal-save"><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var L = <?php echo $js(array(
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'errName'   => sc_t('settings.err_name', 'Enter the platform name.'),
        'errTitle'  => sc_t('settings.err_title', 'Enter the site title.'),
        'errEmail'  => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'errUrl'    => sc_t('dashboard_pages.err_url', 'Enter a full link starting with https://'),
        'errShort'  => sc_t('settings.err_short', 'Use at least 8 characters.'),
        'errCurrent' => sc_t('settings.err_current', 'Enter your current password to change your email or password.'),
        'errFeatured' => sc_t('settings.err_featured', 'Choose the event the homepage shows.'),
        'offAsk'    => sc_t('settings.gateway_off_ask', 'Stop offering %s at checkout?'),
        'onAsk'     => sc_t('settings.gateway_on_ask', 'Offer %s at checkout? Make sure its keys are saved and tested first.'),
        'testing'   => sc_t('settings.testing', 'Testing…'),
        'sending'   => sc_t('settings.sending', 'Sending…'),
    )); ?>;
    var accountEmail = <?php echo $js($user->user_email); ?>;
    var i18n = { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave };
    var post = function (fd) { return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
    var isUrl = function (v) { return !v || /^https?:\/\/\S+$/i.test(v); };

    var validators = {
        brand: function (v) { return $.trim(v.platform_name) ? [] : [{ field: 'platform_name', message: L.errName }]; },
        contact: function (v) {
            var e = [];
            if (v.platform_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.platform_email)) { e.push({ field: 'platform_email', message: L.errEmail }); }
            ['facebook', 'instagram', 'twitter', 'linkedin'].forEach(function (n) { if (!isUrl($.trim(v['platform_' + n]))) { e.push({ field: 'platform_' + n, message: L.errUrl }); } });
            return e;
        },
        homepage: function (v) { return v.homepage_mode === 'single_event' && +v.featured_event_id === 0 ? [{ field: 'featured_event_id', message: L.errFeatured }] : []; },
        identity: function (v) { return $.trim(v.site_title) ? [] : [{ field: 'site_title', message: L.errTitle }]; },
        account: function (v) {
            var e = [];
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email || '')) { e.push({ field: 'email', message: L.errEmail }); }
            if (v.new_password && v.new_password.length < 8) { e.push({ field: 'new_password', message: L.errShort }); }
            if ((v.new_password || (v.email || '').toLowerCase() !== accountEmail.toLowerCase()) && !v.current_password) { e.push({ field: 'current_password', message: L.errCurrent }); }
            return e;
        }
    };

    $('form[data-section]').each(function () {
        var section = this.getAttribute('data-section');
        var formEl = this;
        WDForm.create({
            form: formEl,
            i18n: i18n,
            validate: validators[section] || function () { return []; },
            submit: function (fd) {
                fd.append('action', 'sc_settings_save');
                fd.append('nonce', scDashboard.nonce);
                fd.append('section', section);
                return post(fd);
            },
            onSuccess: function (data, api) {
                api.markClean();
                if (window.toastr) { toastr.success(data.message || L.saved); }
                if (section === 'account') {
                    accountEmail = $('#ac-email').val();
                    $('#ac-new, #ac-current').val('');
                    api.markClean();
                }
            }
        });
    });

    /* Currency preview */
    function currencyPreview() {
        var places = +$('#st-places').val(), dec = $('#st-dec').val(), thou = $('#st-thou').val();
        var symbol = $('#st-cur option:selected').data('symbol') || $('#st-cur').val();
        var parts = (1250.5).toFixed(places).split('.');
        var num = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thou) + (places ? dec + parts[1] : '');
        var pos = $('#st-pos').val();
        $('#cur-preview').text(pos === 'before' ? symbol + ' ' + num : pos === 'before_no_space' ? symbol + num : pos === 'after_no_space' ? num + symbol : num + ' ' + symbol);
    }
    $('#currency').on('change', 'select', currencyPreview);
    currencyPreview();

    /* Payment gateways */
    $('form[data-gateway]').each(function () {
        var formEl = this, code = this.getAttribute('data-gateway');
        WDForm.create({
            form: formEl,
            i18n: i18n,
            validate: function () { return []; },
            submit: function (fd) {
                fd.append('action', 'sc_save_gateway_settings');
                fd.append('nonce', scDashboard.nonce);
                return post(fd);
            },
            onSuccess: function (data, api) {
                $(formEl).find('input[type="password"]').each(function () { if (this.value) { this.value = ''; this.placeholder = <?php echo $js(sc_t('settings.saved_keep', 'Saved — leave empty to keep')); ?>; } });
                api.markClean();
                if (window.toastr) { toastr.success(data.message || L.saved); }
            }
        });
        $(formEl).on('change', '[data-gateway-toggle]', function () {
            var box = this, on = box.checked, name = $(formEl).find('.w-gateway__head strong').first().contents().first().text().trim();
            Swal.fire({ text: (on ? L.onAsk : L.offAsk).replace('%s', name), icon: 'question', showCancelButton: true }).then(function (r) {
                if (!r.isConfirmed) { box.checked = !on; return; }
                $.post(scDashboard.ajaxurl, { action: 'sc_toggle_payment_gateway', nonce: scDashboard.nonce, gateway: code, enabled: on ? 1 : 0 })
                    .done(function (res) {
                        if (!res.success) { box.checked = !on; showError(res.data && res.data.message || L.failed); return; }
                        $(formEl).toggleClass('is-on', on);
                        if (window.toastr) { toastr.success(res.data.message); }
                    })
                    .fail(function () { box.checked = !on; showError(L.failed); });
            });
            // Keep the switch out of the form's unsaved-changes tracking.
            return false;
        });
        $(formEl).on('click', '[data-gateway-test]', function () {
            var btn = $(this).prop('disabled', true);
            var label = btn.text();
            btn.text(L.testing);
            $.post(scDashboard.ajaxurl, { action: 'sc_test_gateway_connection', nonce: scDashboard.nonce, gateway: code })
                .done(function (res) { (res.success ? showSuccess : showError)(res.data && res.data.message || L.failed); })
                .fail(function () { showError(L.failed); })
                .always(function () { btn.prop('disabled', false).text(label); });
        });
    });

    /* Test email */
    $('#test-email').on('click', function () {
        var btn = $(this).prop('disabled', true), label = btn.text();
        btn.text(L.sending);
        $.post(scDashboard.ajaxurl, { action: 'sc_settings_test_email', nonce: scDashboard.nonce })
            .done(function (res) { (res.success ? showSuccess : showError)(res.data && res.data.message || L.failed); })
            .fail(function () { showError(L.failed); })
            .always(function () { btn.prop('disabled', false).text(label); });
    });

    /* Legal pages */
    $('#legal').on('click', '[data-legal-create]', function () {
        var btn = $(this).prop('disabled', true);
        $.post(scDashboard.ajaxurl, { action: 'sc_create_content_page', nonce: scDashboard.nonce, page_type: btn.data('legal-create') })
            .done(function (res) {
                if (!res.success) { btn.prop('disabled', false); showError(res.data && res.data.message || L.failed); return; }
                if (window.toastr) { toastr.success(res.data.message); }
                setTimeout(function () { window.location.hash = 'legal'; window.location.reload(); }, 600);
            })
            .fail(function () { btn.prop('disabled', false); showError(L.failed); });
    });
    $('#legal').on('click', '[data-legal-edit]', function () {
        var id = $(this).data('legal-edit');
        $.post(scDashboard.ajaxurl, { action: 'sc_get_page_content', nonce: scDashboard.nonce, page_id: id }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            var f = document.getElementById('legal-form');
            f.page_id.value = res.data.page_id;
            f.title.value = res.data.title;
            f.content.value = res.data.content;
            $('#legalModal').modal('show');
        }).fail(function () { showError(L.failed); });
    });
    $('#legal-form').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#legal-save').prop('disabled', true);
        var data = $(this).serializeArray().reduce(function (o, x) { o[x.name] = x.value; return o; }, {});
        $.post(scDashboard.ajaxurl, $.extend(data, { action: 'sc_update_page_content', nonce: scDashboard.nonce }))
            .done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                $('#legalModal').modal('hide');
                showSuccess(res.data.message || L.saved);
            })
            .fail(function () { showError(L.failed); })
            .always(function () { btn.prop('disabled', false); });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
