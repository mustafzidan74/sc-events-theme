<?php
/**
 * Dashboard Settings Page
 *
 * @package sc_events
 */

$page_title = sc_t('nav.settings', 'Settings');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'settings' => sc_t('nav.settings', 'Settings'),
    'platform_settings' => sc_t('dashboard_pages.platform_settings', 'Platform Settings'),
    'payment_currency' => sc_t('dashboard_pages.payment_currency', 'Payment & Currency'),
    'seo_settings' => sc_t('dashboard_pages.seo_settings', 'SEO Settings'),
    'content' => sc_t('dashboard_pages.content', 'Content'),
    'account_settings' => sc_t('dashboard_pages.account_settings', 'Account Settings'),
    'platform_information' => sc_t('dashboard_pages.platform_information', 'Platform Information'),
    'configure_platform' => sc_t('dashboard_pages.configure_platform', 'Configure your event platform settings'),
    'platform_name' => sc_t('dashboard_pages.platform_name', 'Platform Name'),
    'platform_description' => sc_t('dashboard_pages.platform_description', 'Platform Description'),
    'platform_logo' => sc_t('dashboard_pages.platform_logo', 'Platform Logo'),
    'save_changes' => sc_t('dashboard_pages.save_changes', 'Save Changes'),
);

$current_user = wp_get_current_user();
$is_admin = current_user_can('administrator');
$is_event_manager = SC_Event_Manager_Dashboard::is_event_manager();

// Get platform settings
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$platform_description = get_option('sc_platform_description', '');
$platform_logo_id = get_option('sc_platform_logo');
$platform_logo_light_id = get_option('sc_platform_logo_light');
$platform_facebook = get_option('sc_platform_facebook', '');
$platform_twitter = get_option('sc_platform_twitter', '');
$platform_instagram = get_option('sc_platform_instagram', '');
$platform_linkedin = get_option('sc_platform_linkedin', '');
$sc_primary_color = get_option('sc_primary_color', '#667eea');
$sc_secondary_color = get_option('sc_secondary_color', '#764ba2');

// Contact Information
$platform_phone = get_option('sc_platform_phone', '');
$platform_email = get_option('sc_platform_email', get_option('admin_email'));
$platform_website = get_option('sc_platform_website', home_url());
$platform_whatsapp = get_option('sc_platform_whatsapp', '');

// Theme default
$default_theme = get_option('sc_default_theme', 'dark');

// Homepage Settings
$homepage_mode = get_option('sc_homepage_mode', 'normal');
$featured_event_id = intval(get_option('sc_featured_event_id', 0));
$published_events = class_exists('SC_Event') ? SC_Event::get_all(array('status' => 'publish', 'orderby' => 'start_date', 'order' => 'DESC', 'limit' => 100)) : array();

// SEO Settings
$site_icon_id = get_option('site_icon');
$site_title = get_option('blogname');
$site_tagline = get_option('blogdescription');
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html(sc_t('nav.settings', 'Settings')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.settings', 'Settings')); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages Container -->
        <div class="row">
            <div class="col-12">
                <div id="settings-alerts"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <!-- Settings Navigation -->
                <div class="card">
                    <div class="list-group list-group-flush">
                        <a href="#platform-settings" class="list-group-item list-group-item-action active" data-toggle="tab">
                            <i class="fa fa-building"></i> <?php echo esc_html(sc_t('settings.platform_settings', 'Platform Settings')); ?>
                        </a>
                        <a href="#payment-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-credit-card"></i> <?php echo esc_html(sc_t('settings.payment_currency', 'Payment & Currency')); ?>
                        </a>
                        <a href="#seo-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-search"></i> <?php echo esc_html(sc_t('settings.seo_settings', 'SEO Settings')); ?>
                        </a>
                        <a href="#content-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-file-text"></i> <?php echo esc_html(sc_t('settings.content_management', 'Content')); ?>
                        </a>
                        <a href="#account-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-user"></i> <?php echo esc_html(sc_t('settings.account_settings', 'Account Settings')); ?>
                        </a>
                        <a href="#security-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-shield"></i> <?php echo esc_html(sc_t('settings.security_settings', 'Security')); ?>
                        </a>
                        <a href="#smtp-settings" class="list-group-item list-group-item-action" data-toggle="tab">
                            <i class="fa fa-envelope-o"></i> Email / SMTP
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="tab-content">

                    <!-- Platform Settings -->
                    <div class="tab-pane fade show active" id="platform-settings">
                        <div class="card">
                            <div class="header">
                                <h2><?php echo esc_html(sc_t('settings.platform_information', 'Platform Information')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.configure_platform', 'Configure your event platform settings')); ?></small>
                            </div>
                            <div class="body">
                                <form id="platform-settings-form" enctype="multipart/form-data">

                                    <div class="form-group">
                                        <label for="platform-logo"><?php echo esc_html(sc_t('settings.platform_logo', 'Platform Logo')); ?></label>
                                        <div class="mb-3">
                                            <?php if ($platform_logo_id):
                                                $logo = wp_get_attachment_image_src($platform_logo_id, 'medium');
                                            ?>
                                            <img id="logo-preview" src="<?php echo esc_url($logo[0]); ?>" alt="Platform Logo" class="img-thumbnail" style="max-width: 200px; display: block;">
                                            <?php else: ?>
                                            <img id="logo-preview" src="" alt="Logo Preview" class="img-thumbnail" style="max-width: 200px; display: none;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control-file" id="platform-logo-input" name="platform_logo" accept="image/*">
                                        <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.logo_dark_hint', 'Logo for dark mode / default (recommended: 200x200px)')); ?></small>
                                    </div>

                                    <div class="form-group">
                                        <label for="platform-logo-light"><i class="fa fa-sun-o"></i> <?php echo esc_html(sc_t('settings.light_mode_logo', 'Light Mode Logo')); ?></label>
                                        <div class="mb-3">
                                            <?php if ($platform_logo_light_id):
                                                $logo_light = wp_get_attachment_image_src($platform_logo_light_id, 'medium');
                                            ?>
                                            <img id="logo-light-preview" src="<?php echo esc_url($logo_light[0]); ?>" alt="Light Mode Logo" class="img-thumbnail" style="max-width: 200px; display: block;">
                                            <?php else: ?>
                                            <img id="logo-light-preview" src="" alt="Light Logo Preview" class="img-thumbnail" style="max-width: 200px; display: none;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control-file" id="platform-logo-light-input" name="platform_logo_light" accept="image/*">
                                        <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.light_logo_hint', 'Optional: separate logo for light mode. If empty, the dark logo will be used.')); ?></small>
                                    </div>

                                    <div class="form-group">
                                        <label for="platform-name"><?php echo esc_html(sc_t('settings.platform_name', 'Platform Name')); ?></label>
                                        <input type="text" class="form-control" id="platform-name" name="platform_name" value="<?php echo esc_attr($platform_name); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="platform-description"><?php echo esc_html(sc_t('settings.platform_description', 'Platform Description')); ?></label>
                                        <textarea class="form-control" id="platform-description" name="platform_description" rows="3"><?php echo esc_textarea($platform_description); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="primary-color">
                                                    <i class="fa fa-paint-brush"></i> <?php echo esc_html(sc_t('settings.primary_color', 'Primary Color')); ?>
                                                </label>
                                                <input type="color" class="form-control" id="primary-color" name="primary_color" value="<?php echo esc_attr($sc_primary_color); ?>" style="height: 50px; cursor: pointer;">
                                                <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.primary_color_hint', 'Main brand color (buttons, links, etc.)')); ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="secondary-color">
                                                    <i class="fa fa-adjust"></i> <?php echo esc_html(sc_t('settings.secondary_color', 'Secondary Color (Hover)')); ?>
                                                </label>
                                                <input type="color" class="form-control" id="secondary-color" name="secondary_color" value="<?php echo esc_attr($sc_secondary_color); ?>" style="height: 50px; cursor: pointer;">
                                                <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.secondary_color_hint', 'Used for hover effects and gradients')); ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i>
                                        <strong><?php echo esc_html(sc_t('settings.preview', 'Preview')); ?>:</strong> <?php echo esc_html(sc_t('settings.color_preview_hint', 'The colors will create a gradient effect throughout the dashboard. Try complementary colors for best results!')); ?>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3"><i class="fa fa-moon"></i> <?php echo esc_html(sc_t('settings.theme_appearance', 'Theme Appearance')); ?></h5>
                                    <div class="form-group">
                                        <label for="default-theme"><?php echo esc_html(sc_t('settings.default_theme', 'Default Theme for Visitors')); ?></label>
                                        <select class="form-control" id="default-theme" name="default_theme">
                                            <option value="dark" <?php selected($default_theme, 'dark'); ?>><?php echo esc_html(sc_t('settings.dark_mode', 'Dark Mode')); ?></option>
                                            <option value="light" <?php selected($default_theme, 'light'); ?>><?php echo esc_html(sc_t('settings.light_mode', 'Light Mode')); ?></option>
                                        </select>
                                        <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.default_theme_hint', 'Sets the initial theme for new visitors. Users can toggle between light and dark mode at any time.')); ?></small>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3"><i class="fa fa-language"></i> <?php echo esc_html(sc_t('settings.site_language', 'Site Language')); ?></h5>
                                    <div class="form-group">
                                        <label for="site-language"><?php echo esc_html(sc_t('settings.site_language_label', 'Website Language')); ?></label>
                                        <select class="form-control" id="site-language" name="site_language">
                                            <option value="en" <?php selected(get_option('sc_site_language', 'en'), 'en'); ?>>English</option>
                                            <option value="ar" <?php selected(get_option('sc_site_language', 'en'), 'ar'); ?>>العربية (Arabic)</option>
                                        </select>
                                        <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.site_language_hint', 'Sets the language for the entire website (frontend). Dashboard language can be changed per-user from the header.')); ?></small>
                                    </div>

                                    <hr>

                                    <div class="p-3 mb-3" style="background: #f0f4ff; border: 1px solid #d0d9f0; border-radius: 8px;">
                                        <h5 class="mb-3"><i class="fa fa-home"></i> <?php echo esc_html(sc_t('settings.homepage_settings', 'Homepage Settings')); ?></h5>

                                        <div class="form-group">
                                            <label for="homepage-mode"><?php echo esc_html(sc_t('settings.homepage_mode', 'Homepage Mode')); ?></label>
                                            <select class="form-control" id="homepage-mode" name="homepage_mode">
                                                <option value="normal" <?php selected($homepage_mode, 'normal'); ?>><?php echo esc_html(sc_t('settings.normal_listing', 'Normal (Events listing)')); ?></option>
                                                <option value="single_event" <?php selected($homepage_mode, 'single_event'); ?>><?php echo esc_html(sc_t('settings.single_event_mode', 'Single Event (Focus on one event)')); ?></option>
                                            </select>
                                            <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.homepage_mode_hint', 'Choose how your homepage displays. "Single Event" shows a specific event as the main page.')); ?></small>
                                        </div>

                                        <div class="form-group mb-0" id="featured-event-group" style="<?php echo $homepage_mode !== 'single_event' ? 'display:none;' : ''; ?>">
                                            <label for="featured-event-id"><i class="fa fa-star"></i> <?php echo esc_html(sc_t('settings.featured_event', 'Featured Event')); ?></label>
                                            <select class="form-control" id="featured-event-id" name="featured_event_id">
                                                <option value="0">-- <?php echo esc_html(sc_t('settings.select_event', 'Select Event')); ?> --</option>
                                                <?php foreach ($published_events as $ev): ?>
                                                <option value="<?php echo esc_attr($ev->id); ?>" <?php selected($featured_event_id, $ev->id); ?>>
                                                    <?php echo esc_html($ev->title); ?>
                                                    <?php if ($ev->start_date): ?> (<?php echo esc_html(date_i18n('d M Y', strtotime($ev->start_date))); ?>)<?php endif; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.featured_event_hint', 'This event will be displayed as your homepage.')); ?></small>
                                        </div>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3"><i class="fa fa-address-book"></i> <?php echo esc_html(sc_t('settings.contact_information', 'Contact Information')); ?></h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="platform-phone"><i class="fa fa-phone"></i> <?php echo esc_html(sc_t('settings.phone_number', 'Phone Number')); ?></label>
                                                <input type="tel" class="form-control" id="platform-phone" name="platform_phone" value="<?php echo esc_attr($platform_phone); ?>" placeholder="+20 123 456 7890">
                                                <small class="form-text text-muted"><?php echo esc_html(sc_t('settings.phone_hint', 'Include country code (e.g., +20 for Egypt)')); ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="platform-email"><i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('settings.email_address', 'Email Address')); ?></label>
                                                <input type="email" class="form-control" id="platform-email" name="platform_email" value="<?php echo esc_attr($platform_email); ?>" placeholder="contact@example.com">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="platform-website"><i class="fa fa-globe"></i> <?php echo esc_html(sc_t('settings.website_url', 'Website URL')); ?></label>
                                                <input type="url" class="form-control" id="platform-website" name="platform_website" value="<?php echo esc_url($platform_website); ?>" placeholder="https://www.example.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="platform-whatsapp"><i class="fa fa-whatsapp"></i> <?php echo esc_html(sc_t('settings.whatsapp_number', 'WhatsApp Number')); ?></label>
                                                <input type="tel" class="form-control" id="platform-whatsapp" name="platform_whatsapp" value="<?php echo esc_attr($platform_whatsapp); ?>" placeholder="+201234567890">
                                                <small class="form-text text-muted"><strong><?php echo esc_html(sc_t('settings.important', 'Important')); ?>:</strong> <?php echo esc_html(sc_t('settings.whatsapp_hint', 'Use international format with country code (e.g., +20 for Egypt). No spaces or dashes.')); ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3"><i class="fa fa-share-alt"></i> <?php echo esc_html(sc_t('settings.social_media', 'Social Media Links')); ?></h5>

                                    <div class="form-group">
                                        <label for="facebook"><i class="fa fa-facebook"></i> <?php echo esc_html(sc_t('settings.facebook', 'Facebook')); ?></label>
                                        <input type="url" class="form-control" id="facebook" name="facebook" value="<?php echo esc_url($platform_facebook); ?>" placeholder="https://facebook.com/yourpage">
                                    </div>

                                    <div class="form-group">
                                        <label for="twitter"><?php echo esc_html(sc_t('settings.twitter', 'Twitter')); ?></label>
                                        <input type="url" class="form-control" id="twitter" name="twitter" value="<?php echo esc_url($platform_twitter); ?>" placeholder="https://twitter.com/yourhandle">
                                    </div>

                                    <div class="form-group">
                                        <label for="instagram"><?php echo esc_html(sc_t('settings.instagram', 'Instagram')); ?></label>
                                        <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo esc_url($platform_instagram); ?>" placeholder="https://instagram.com/yourhandle">
                                    </div>

                                    <div class="form-group">
                                        <label for="linkedin"><?php echo esc_html(sc_t('settings.linkedin', 'LinkedIn')); ?></label>
                                        <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo esc_url($platform_linkedin); ?>" placeholder="https://linkedin.com/company/yourcompany">
                                    </div>


                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.save_changes', 'Save Changes')); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Currency Settings -->
                    <div class="tab-pane fade" id="payment-settings">
                        <!-- Currency Settings Card -->
                        <div class="card mb-4">
                            <div class="header">
                                <h2><i class="fa fa-money"></i> <?php echo esc_html(sc_t('settings.currency_settings', 'Currency Settings')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.currency_settings_hint', 'Configure your platform currency and display format')); ?></small>
                            </div>
                            <div class="body">
                                <form id="currency-settings-form">
                                    <?php
                                    $currencies = sc_get_currencies();
                                    $current_currency = get_option('sc_currency_code', 'EGP');
                                    $currency_position = get_option('sc_currency_position', 'after');
                                    $thousand_sep = get_option('sc_thousand_separator', ',');
                                    $decimal_sep = get_option('sc_decimal_separator', '.');
                                    $decimal_places = get_option('sc_decimal_places', 2);
                                    ?>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="currency-code"><i class="fa fa-globe"></i> <?php echo esc_html(sc_t('settings.currency_code', 'Currency')); ?></label>
                                                <select id="currency-code" name="currency_code" class="form-control">
                                                    <?php foreach ($currencies as $code => $data): ?>
                                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($current_currency, $code); ?>>
                                                            <?php echo esc_html($code . ' - ' . $data['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="currency-position"><i class="fa fa-align-left"></i> <?php echo esc_html(sc_t('settings.symbol_position', 'Symbol Position')); ?></label>
                                                <select id="currency-position" name="currency_position" class="form-control">
                                                    <option value="before" <?php selected($currency_position, 'before'); ?>><?php echo esc_html(sc_t('settings.before_amount', 'Before amount')); ?> ($ 100)</option>
                                                    <option value="before_no_space" <?php selected($currency_position, 'before_no_space'); ?>><?php echo esc_html(sc_t('settings.before_no_space', 'Before, no space')); ?> ($100)</option>
                                                    <option value="after" <?php selected($currency_position, 'after'); ?>><?php echo esc_html(sc_t('settings.after_amount', 'After amount')); ?> (100 EGP)</option>
                                                    <option value="after_no_space" <?php selected($currency_position, 'after_no_space'); ?>><?php echo esc_html(sc_t('settings.after_no_space', 'After, no space')); ?> (100EGP)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="thousand-separator"><?php echo esc_html(sc_t('settings.thousand_separator', 'Thousand Separator')); ?></label>
                                                <select id="thousand-separator" name="thousand_separator" class="form-control">
                                                    <option value="," <?php selected($thousand_sep, ','); ?>><?php echo esc_html(sc_t('settings.comma', 'Comma')); ?> (1,000)</option>
                                                    <option value="." <?php selected($thousand_sep, '.'); ?>><?php echo esc_html(sc_t('settings.dot', 'Dot')); ?> (1.000)</option>
                                                    <option value=" " <?php selected($thousand_sep, ' '); ?>><?php echo esc_html(sc_t('settings.space', 'Space')); ?> (1 000)</option>
                                                    <option value="" <?php selected($thousand_sep, ''); ?>><?php echo esc_html(sc_t('settings.none', 'None')); ?> (1000)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="decimal-separator"><?php echo esc_html(sc_t('settings.decimal_separator', 'Decimal Separator')); ?></label>
                                                <select id="decimal-separator" name="decimal_separator" class="form-control">
                                                    <option value="." <?php selected($decimal_sep, '.'); ?>><?php echo esc_html(sc_t('settings.dot', 'Dot')); ?> (100.00)</option>
                                                    <option value="," <?php selected($decimal_sep, ','); ?>><?php echo esc_html(sc_t('settings.comma', 'Comma')); ?> (100,00)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="decimal-places"><?php echo esc_html(sc_t('settings.decimal_places', 'Decimal Places')); ?></label>
                                                <select id="decimal-places" name="decimal_places" class="form-control">
                                                    <option value="0" <?php selected($decimal_places, 0); ?>>0 (100)</option>
                                                    <option value="1" <?php selected($decimal_places, 1); ?>>1 (100.0)</option>
                                                    <option value="2" <?php selected($decimal_places, 2); ?>>2 (100.00)</option>
                                                    <option value="3" <?php selected($decimal_places, 3); ?>>3 (100.000)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fa fa-eye"></i> <strong><?php echo esc_html(sc_t('settings.preview', 'Preview')); ?>:</strong>
                                        <span id="currency-preview" class="ml-2 font-weight-bold">1,234.56 EGP</span>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.save_currency', 'Save Currency Settings')); ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Payment Gateways Card -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-credit-card"></i> <?php echo esc_html(sc_t('settings.payment_gateways', 'Payment Gateways')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.payment_gateways_hint', 'Configure your payment gateway integrations')); ?></small>
                            </div>
                            <div class="body">
                                <?php
                                // Get saved gateway settings
                                $gateways = array(
                                    'paymob' => array(
                                        'name' => 'Paymob',
                                        'icon' => 'fa-credit-card',
                                        'description' => sc_t('settings.paymob_desc', 'Accept payments via Paymob (Egypt)'),
                                        'fields' => array(
                                            'api_key' => array('label' => sc_t('settings.api_key', 'API Key'), 'type' => 'password'),
                                            'integration_id' => array('label' => sc_t('settings.integration_id', 'Integration ID'), 'type' => 'text'),
                                            'iframe_id' => array('label' => sc_t('settings.iframe_id', 'iFrame ID'), 'type' => 'text'),
                                            'hmac_secret' => array('label' => sc_t('settings.hmac_secret', 'HMAC Secret'), 'type' => 'password'),
                                        )
                                    ),
                                    'stripe' => array(
                                        'name' => 'Stripe',
                                        'icon' => 'fa-cc-stripe',
                                        'description' => sc_t('settings.stripe_desc', 'Accept international card payments'),
                                        'fields' => array(
                                            'publishable_key' => array('label' => sc_t('settings.publishable_key', 'Publishable Key'), 'type' => 'text'),
                                            'secret_key' => array('label' => sc_t('settings.secret_key', 'Secret Key'), 'type' => 'password'),
                                            'webhook_secret' => array('label' => sc_t('settings.webhook_secret', 'Webhook Secret'), 'type' => 'password'),
                                        )
                                    ),
                                    'myfatoorah' => array(
                                        'name' => 'MyFatoorah',
                                        'icon' => 'fa-money',
                                        'description' => sc_t('settings.myfatoorah_desc', 'Accept payments in GCC countries'),
                                        'fields' => array(
                                            'api_key' => array('label' => sc_t('settings.api_key', 'API Key'), 'type' => 'password'),
                                            'country_iso' => array('label' => sc_t('settings.country_iso', 'Country ISO'), 'type' => 'select', 'options' => array(
                                                'KWT' => sc_t('settings.kuwait', 'Kuwait'),
                                                'SAU' => sc_t('settings.saudi_arabia', 'Saudi Arabia'),
                                                'BHR' => sc_t('settings.bahrain', 'Bahrain'),
                                                'ARE' => sc_t('settings.uae', 'UAE'),
                                                'QAT' => sc_t('settings.qatar', 'Qatar'),
                                                'OMN' => sc_t('settings.oman', 'Oman'),
                                                'JOD' => sc_t('settings.jordan', 'Jordan'),
                                                'EGY' => sc_t('settings.egypt', 'Egypt'),
                                            )),
                                        )
                                    ),
                                    'kashier' => array(
                                        'name' => 'Kashier',
                                        'icon' => 'fa-credit-card-alt',
                                        'description' => sc_t('settings.kashier_desc', 'Accept payments via Kashier (Egypt)'),
                                        'fields' => array(
                                            'merchant_id' => array('label' => sc_t('settings.merchant_id', 'Merchant ID'), 'type' => 'text'),
                                            'api_key' => array('label' => sc_t('settings.api_key', 'API Key'), 'type' => 'password'),
                                            'secret_key' => array('label' => sc_t('settings.secret_key', 'Secret Key'), 'type' => 'password'),
                                        )
                                    ),
                                );
                                ?>

                                <div class="accordion" id="gatewaysAccordion">
                                    <?php foreach ($gateways as $gateway_code => $gateway):
                                        $settings = get_option('sc_gateway_' . $gateway_code, array());
                                        $is_enabled = isset($settings['enabled']) && $settings['enabled'];
                                        $is_test_mode = !isset($settings['test_mode']) || $settings['test_mode'];
                                    ?>
                                    <div class="card mb-2">
                                        <div class="card-header p-0" id="heading-<?php echo $gateway_code; ?>">
                                            <div class="d-flex align-items-center justify-content-between p-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="custom-control custom-switch mr-3">
                                                        <input type="checkbox" class="custom-control-input gateway-toggle"
                                                               id="enable-<?php echo $gateway_code; ?>"
                                                               data-gateway="<?php echo $gateway_code; ?>"
                                                               <?php checked($is_enabled); ?>>
                                                        <label class="custom-control-label" for="enable-<?php echo $gateway_code; ?>"></label>
                                                    </div>
                                                    <i class="fa <?php echo $gateway['icon']; ?> fa-lg mr-2 text-primary"></i>
                                                    <div>
                                                        <strong><?php echo $gateway['name']; ?></strong>
                                                        <small class="text-muted d-block"><?php echo $gateway['description']; ?></small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($is_enabled): ?>
                                                        <span class="badge badge-<?php echo $is_test_mode ? 'warning' : 'success'; ?> mr-3">
                                                            <?php echo $is_test_mode ? esc_html(sc_t('settings.test_mode', 'Test Mode')) : esc_html(sc_t('settings.live', 'Live')); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse"
                                                            data-target="#collapse-<?php echo $gateway_code; ?>"
                                                            aria-expanded="false" aria-controls="collapse-<?php echo $gateway_code; ?>">
                                                        <i class="fa fa-cog"></i> <?php echo esc_html(sc_t('settings.configure', 'Configure')); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="collapse-<?php echo $gateway_code; ?>" class="collapse"
                                             aria-labelledby="heading-<?php echo $gateway_code; ?>" data-parent="#gatewaysAccordion">
                                            <div class="card-body border-top">
                                                <form class="gateway-settings-form" data-gateway="<?php echo $gateway_code; ?>">
                                                    <input type="hidden" name="gateway_code" value="<?php echo $gateway_code; ?>">

                                                    <div class="form-group">
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input"
                                                                   id="test-mode-<?php echo $gateway_code; ?>"
                                                                   name="test_mode" <?php checked($is_test_mode); ?>>
                                                            <label class="custom-control-label" for="test-mode-<?php echo $gateway_code; ?>">
                                                                <i class="fa fa-flask"></i> <?php echo esc_html(sc_t('settings.test_mode', 'Test Mode')); ?>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted"><?php echo esc_html(sc_t('settings.test_mode_hint', 'Enable test mode for development. Disable for live payments.')); ?></small>
                                                    </div>

                                                    <hr>

                                                    <?php foreach ($gateway['fields'] as $field_key => $field): ?>
                                                    <div class="form-group">
                                                        <label for="<?php echo $gateway_code; ?>-<?php echo $field_key; ?>">
                                                            <?php echo $field['label']; ?>
                                                        </label>
                                                        <?php if ($field['type'] === 'select' && isset($field['options'])): ?>
                                                            <select class="form-control"
                                                                    id="<?php echo $gateway_code; ?>-<?php echo $field_key; ?>"
                                                                    name="<?php echo $field_key; ?>">
                                                                <?php foreach ($field['options'] as $opt_val => $opt_label): ?>
                                                                    <option value="<?php echo esc_attr($opt_val); ?>"
                                                                            <?php selected(isset($settings[$field_key]) ? $settings[$field_key] : '', $opt_val); ?>>
                                                                        <?php echo esc_html($opt_label); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php else: ?>
                                                            <input type="<?php echo $field['type']; ?>" class="form-control"
                                                                   id="<?php echo $gateway_code; ?>-<?php echo $field_key; ?>"
                                                                   name="<?php echo $field_key; ?>"
                                                                   value="<?php echo $field['type'] === 'password' ? '' : esc_attr(isset($settings[$field_key]) ? $settings[$field_key] : ''); ?>"
                                                                   <?php echo $field['type'] === 'password' ? 'autocomplete="new-password" placeholder="' . esc_attr(!empty($settings[$field_key]) ? __('Saved — leave empty to keep', 'sc_events') : '') . '"' : ''; ?>>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>

                                                    <hr>
                                                    <div class="form-group mb-3">
                                                        <label class="text-muted" style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                            <i class="fa fa-link"></i> <?php echo esc_html(sc_t('settings.callback_webhook_urls', 'Callback / Webhook URLs')); ?>
                                                        </label>
                                                        <div class="input-group input-group-sm mb-1">
                                                            <span class="input-group-text" style="font-size: 11px; min-width: 75px;">Callback</span>
                                                            <input type="text" class="form-control" style="font-size: 12px;"
                                                                   value="<?php echo esc_attr(rest_url('sc-events/v1/payment/callback/' . $gateway_code)); ?>" readonly onclick="this.select()">
                                                        </div>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text" style="font-size: 11px; min-width: 75px;">Webhook</span>
                                                            <input type="text" class="form-control" style="font-size: 12px;"
                                                                   value="<?php echo esc_attr(rest_url('sc-events/v1/payment/webhook/' . $gateway_code)); ?>" readonly onclick="this.select()">
                                                        </div>
                                                        <small class="text-muted"><?php echo esc_html(sc_t('settings.use_urls_in', 'Use these URLs in your')); ?> <?php echo esc_html($gateway['name']); ?> <?php echo esc_html(sc_t('settings.dashboard_settings', 'dashboard settings.')); ?></small>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                                        <button type="button" class="btn btn-outline-secondary test-gateway-btn" data-gateway="<?php echo $gateway_code; ?>">
                                                            <i class="fa fa-plug"></i> <?php echo esc_html(sc_t('settings.test_connection', 'Test Connection')); ?>
                                                        </button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.save_settings', 'Save Settings')); ?>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="alert alert-info mt-4">
                                    <i class="fa fa-info-circle"></i>
                                    <strong><?php echo esc_html(sc_t('settings.note', 'Note')); ?>:</strong> <?php echo esc_html(sc_t('settings.gateway_note', 'You need to configure at least one payment gateway to accept payments. Callback URLs will be automatically configured when you save the settings.')); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEO Settings -->
                    <div class="tab-pane fade" id="seo-settings">
                        <div class="card">
                            <div class="header">
                                <h2><?php echo esc_html(sc_t('settings.seo_settings', 'SEO Settings')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.seo_settings_hint', 'Configure your website SEO and search engine visibility')); ?></small>
                            </div>
                            <div class="body">
                                <form id="seo-settings-form" enctype="multipart/form-data">

                                    <div class="form-group">
                                        <label for="site-icon">
                                            <i class="fa fa-bookmark"></i> <?php echo esc_html(sc_t('settings.site_icon', 'Site Icon (Favicon)')); ?>
                                        </label>
                                        <div class="mb-3">
                                            <?php if ($site_icon_id):
                                                $icon = wp_get_attachment_image_src($site_icon_id, 'thumbnail');
                                            ?>
                                            <img id="icon-preview" src="<?php echo esc_url($icon[0]); ?>" alt="Site Icon" class="img-thumbnail" style="max-width: 100px; display: block;">
                                            <?php else: ?>
                                            <img id="icon-preview" src="" alt="Icon Preview" class="img-thumbnail" style="max-width: 100px; display: none;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control-file" id="site-icon-input" name="site_icon" accept="image/*">
                                        <small class="form-text text-muted">
                                            <i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('settings.site_icon_hint', 'The Site Icon is what you see in browser tabs, bookmark bars, and within the WordPress mobile apps.')); ?>
                                            <br><?php echo esc_html(sc_t('settings.site_icon_size', 'Recommended size: at least 512x512 pixels (square).')); ?>
                                        </small>
                                    </div>

                                    <hr>

                                    <div class="form-group">
                                        <label for="site-title">
                                            <i class="fa fa-text-width"></i> <?php echo esc_html(sc_t('settings.site_title', 'Site Title')); ?>
                                        </label>
                                        <input type="text" class="form-control" id="site-title" name="site_title" value="<?php echo esc_attr($site_title); ?>" required>
                                        <small class="form-text text-muted">
                                            <?php echo esc_html(sc_t('settings.site_title_hint', 'Your site title appears in browser tabs and search results. Keep it short and descriptive.')); ?>
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="site-tagline">
                                            <i class="fa fa-tag"></i> <?php echo esc_html(sc_t('settings.tagline', 'Tagline')); ?>
                                        </label>
                                        <input type="text" class="form-control" id="site-tagline" name="site_tagline" value="<?php echo esc_attr($site_tagline); ?>" placeholder="<?php echo esc_attr(sc_t('settings.tagline_placeholder', 'In a few words, explain what this site is about')); ?>">
                                        <small class="form-text text-muted">
                                            <?php echo esc_html(sc_t('settings.tagline_hint', 'Explain what your site is about in a few words.')); ?>
                                        </small>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fa fa-lightbulb-o"></i>
                                        <strong><?php echo esc_html(sc_t('settings.seo_tip', 'SEO Tip')); ?>:</strong> <?php echo esc_html(sc_t('settings.seo_tip_text', 'These settings help search engines understand your website. Choose a clear, descriptive title and tagline that includes relevant keywords for your events platform.')); ?>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.save_seo', 'Save SEO Settings')); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Content Settings -->
                    <div class="tab-pane fade" id="content-settings">
                        <div class="card">
                            <div class="header">
                                <h2><?php echo esc_html(sc_t('settings.content_management', 'Content Management')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.content_management_hint', 'Manage your website pages and content')); ?></small>
                            </div>
                            <div class="body">
                                <div class="row">
                                    <div class="col-12">
                                        <h4 class="mb-3"><?php echo esc_html(sc_t('settings.important_pages', 'Important Pages')); ?></h4>

                                        <!-- Privacy Policy Page -->
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            <i class="fa fa-shield text-primary"></i> <?php echo esc_html(sc_t('settings.privacy_policy', 'Privacy Policy')); ?>
                                                        </h5>
                                                        <p class="text-muted mb-2"><?php echo esc_html(sc_t('settings.manage_privacy', 'Manage your privacy policy page')); ?></p>
                                                        <?php
                                                        $privacy_page_id = get_option('wp_page_for_privacy_policy');
                                                        if ($privacy_page_id) {
                                                            $privacy_page = get_post($privacy_page_id);
                                                            if ($privacy_page) {
                                                                echo '<small class="text-success"><i class="fa fa-check-circle"></i> ' . esc_html(sc_t('settings.page_exists', 'Page exists')) . '</small>';
                                                            }
                                                        } else {
                                                            echo '<small class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' . esc_html(sc_t('settings.not_configured', 'Not configured')) . '</small>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($privacy_page_id && isset($privacy_page)): ?>
                                                            <a href="<?php echo get_permalink($privacy_page_id); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="fa fa-eye"></i> <?php echo esc_html(sc_t('settings.view_page', 'View Page')); ?>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-primary edit-page-btn" data-page-id="<?php echo $privacy_page_id; ?>" data-page-title="Privacy Policy">
                                                                <i class="fa fa-edit"></i> <?php echo esc_html(sc_t('settings.edit_content', 'Edit Content')); ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-success" id="create-privacy-page">
                                                                <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('settings.create_page', 'Create Page')); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Terms & Conditions (Optional) -->
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="mb-1">
                                                            <i class="fa fa-file-text text-info"></i> <?php echo esc_html(sc_t('settings.terms_conditions', 'Terms & Conditions')); ?>
                                                        </h5>
                                                        <p class="text-muted mb-2"><?php echo esc_html(sc_t('settings.manage_terms', 'Manage your terms and conditions page')); ?></p>
                                                        <?php
                                                        $terms_page = get_page_by_path('terms-and-conditions');
                                                        if ($terms_page) {
                                                            echo '<small class="text-success"><i class="fa fa-check-circle"></i> ' . esc_html(sc_t('settings.page_exists', 'Page exists')) . '</small>';
                                                        } else {
                                                            echo '<small class="text-muted"><i class="fa fa-info-circle"></i> ' . esc_html(sc_t('settings.optional_page', 'Optional page')) . '</small>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($terms_page): ?>
                                                            <a href="<?php echo get_permalink($terms_page->ID); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="fa fa-eye"></i> <?php echo esc_html(sc_t('settings.view_page', 'View Page')); ?>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-primary edit-page-btn" data-page-id="<?php echo $terms_page->ID; ?>" data-page-title="Terms & Conditions">
                                                                <i class="fa fa-edit"></i> <?php echo esc_html(sc_t('settings.edit_content', 'Edit Content')); ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" id="create-terms-page">
                                                                <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('settings.create_page', 'Create Page')); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Help Section -->
                                        <div class="alert alert-info mt-4">
                                            <h5><i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('settings.about_content', 'About Content Pages')); ?></h5>
                                            <ul class="mb-0">
                                                <li><strong><?php echo esc_html(sc_t('settings.privacy_policy', 'Privacy Policy')); ?>:</strong> <?php echo esc_html(sc_t('settings.privacy_desc', 'Required by law in many countries. Explains how you collect and use user data.')); ?></li>
                                                <li><strong><?php echo esc_html(sc_t('settings.terms_conditions', 'Terms & Conditions')); ?>:</strong> <?php echo esc_html(sc_t('settings.terms_desc', 'Optional but recommended. Outlines the rules for using your event platform.')); ?></li>
                                                <li><?php echo esc_html(sc_t('settings.edit_pages_hint', 'You can edit these pages anytime from the WordPress admin panel or directly from this dashboard.')); ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Settings -->
                    <div class="tab-pane fade" id="account-settings">
                        <div class="card">
                            <div class="header">
                                <h2><?php echo esc_html(sc_t('settings.account_information', 'Account Information')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.account_info_hint', 'Update your personal account settings')); ?></small>
                            </div>
                            <div class="body">
                                <form id="account-settings-form">

                                    <div class="form-group">
                                        <label for="first-name"><?php echo esc_html(sc_t('settings.first_name', 'First Name')); ?></label>
                                        <input type="text" class="form-control" id="first-name" name="first_name" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'first_name', true)); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="last-name"><?php echo esc_html(sc_t('settings.last_name', 'Last Name')); ?></label>
                                        <input type="text" class="form-control" id="last-name" name="last_name" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'last_name', true)); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email"><?php echo esc_html(sc_t('settings.email_address', 'Email Address')); ?></label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3"><?php echo esc_html(sc_t('settings.change_password', 'Change Password')); ?></h5>
                                    <p class="text-muted"><?php echo esc_html(sc_t('settings.password_hint', "Leave blank if you don't want to change your password")); ?></p>

                                    <div class="form-group">
                                        <label for="current-password"><?php echo esc_html(sc_t('settings.current_password', 'Current Password')); ?></label>
                                        <input type="password" class="form-control" id="current-password" name="current_password" autocomplete="current-password" placeholder="<?php echo esc_attr(sc_t('settings.enter_current_password', 'Required to change your password')); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="new-password"><?php echo esc_html(sc_t('settings.new_password', 'New Password')); ?></label>
                                        <input type="password" class="form-control" id="new-password" name="new_password" placeholder="<?php echo esc_attr(sc_t('settings.enter_new_password', 'Enter new password')); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm-password"><?php echo esc_html(sc_t('settings.confirm_password', 'Confirm New Password')); ?></label>
                                        <input type="password" class="form-control" id="confirm-password" name="confirm_password" placeholder="<?php echo esc_attr(sc_t('settings.confirm_new_password', 'Confirm new password')); ?>">
                                    </div>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.update_account', 'Update Account')); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security-settings">
                        <?php
                        $user_id = get_current_user_id();
                        $tfa_enabled = get_user_meta($user_id, 'sc_2fa_enabled', true);
                        $tfa_method = get_user_meta($user_id, 'sc_2fa_method', true) ?: 'totp';
                        $tfa_secret = get_user_meta($user_id, 'sc_2fa_secret', true);
                        $backup_codes = get_user_meta($user_id, 'sc_2fa_backup_codes', true);
                        $backup_count = is_array($backup_codes) ? count($backup_codes) : 0;
                        ?>

                        <!-- Two-Factor Authentication Card -->
                        <div class="card mb-4">
                            <div class="header">
                                <h2><i class="fa fa-shield"></i> <?php echo esc_html(sc_t('settings.two_factor_auth', 'Two-Factor Authentication')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.two_factor_hint', 'Add an extra layer of security to your account')); ?></small>
                            </div>
                            <div class="body">
                                <?php if ($tfa_enabled): ?>
                                    <!-- 2FA is enabled -->
                                    <div class="alert alert-success">
                                        <i class="fa fa-check-circle"></i>
                                        <strong><?php echo esc_html(sc_t('settings.two_factor_enabled', 'Two-Factor Authentication is enabled!')); ?></strong>
                                        <br><?php echo esc_html(sc_t('settings.account_protected_with', 'Your account is protected with')); ?> <?php echo $tfa_method === 'totp' ? esc_html(sc_t('settings.authenticator_app', 'Authenticator App')) : esc_html(sc_t('settings.email_verification', 'Email Verification')); ?>.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h5><i class="fa fa-key text-warning"></i> <?php echo esc_html(sc_t('settings.backup_codes', 'Backup Codes')); ?></h5>
                                                    <p class="text-muted"><?php echo esc_html(sc_t('settings.backup_codes_remaining', 'You have')); ?> <strong><?php echo $backup_count; ?></strong> <?php echo esc_html(sc_t('settings.codes_remaining', 'backup codes remaining.')); ?></p>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" id="view-backup-codes">
                                                        <i class="fa fa-eye"></i> <?php echo esc_html(sc_t('settings.view_codes', 'View Codes')); ?>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" id="regenerate-backup-codes">
                                                        <i class="fa fa-refresh"></i> <?php echo esc_html(sc_t('settings.regenerate', 'Regenerate')); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h5><i class="fa fa-cog text-info"></i> <?php echo esc_html(sc_t('settings.current_method', 'Current Method')); ?></h5>
                                                    <p class="text-muted">
                                                        <?php if ($tfa_method === 'totp'): ?>
                                                            <i class="fa fa-mobile"></i> <?php echo esc_html(sc_t('settings.authenticator_app', 'Authenticator App')); ?> (TOTP)
                                                        <?php else: ?>
                                                            <i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('settings.email_verification', 'Email Verification')); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="change-2fa-method">
                                                        <i class="fa fa-exchange"></i> <?php echo esc_html(sc_t('settings.change_method', 'Change Method')); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <button type="button" class="btn btn-danger" id="disable-2fa">
                                        <i class="fa fa-times-circle"></i> <?php echo esc_html(sc_t('settings.disable_2fa', 'Disable Two-Factor Authentication')); ?>
                                    </button>

                                <?php else: ?>
                                    <!-- 2FA is not enabled -->
                                    <div class="alert alert-warning">
                                        <i class="fa fa-exclamation-triangle"></i>
                                        <strong><?php echo esc_html(sc_t('settings.two_factor_not_enabled', 'Two-Factor Authentication is not enabled.')); ?></strong>
                                        <br><?php echo esc_html(sc_t('settings.two_factor_recommend', 'We strongly recommend enabling 2FA to protect your account.')); ?>
                                    </div>

                                    <h5 class="mb-3"><?php echo esc_html(sc_t('settings.auth_method', 'Choose Authentication Method')); ?></h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card border-primary mb-3" id="method-totp-card" style="cursor: pointer;">
                                                <div class="card-body text-center">
                                                    <i class="fa fa-mobile fa-3x text-primary mb-3"></i>
                                                    <h5><?php echo esc_html(sc_t('settings.authenticator_app', 'Authenticator App')); ?></h5>
                                                    <p class="text-muted"><?php echo esc_html(sc_t('settings.authenticator_desc', 'Use Google Authenticator, Authy, or similar apps to generate codes.')); ?></p>
                                                    <span class="badge badge-success"><?php echo esc_html(sc_t('settings.recommended', 'Recommended')); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card mb-3" id="method-email-card" style="cursor: pointer;">
                                                <div class="card-body text-center">
                                                    <i class="fa fa-envelope fa-3x text-info mb-3"></i>
                                                    <h5><?php echo esc_html(sc_t('settings.email_verification', 'Email Verification')); ?></h5>
                                                    <p class="text-muted"><?php echo esc_html(sc_t('settings.email_verify_desc', 'Receive a verification code via email each time you log in.')); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TOTP Setup Section (hidden by default) -->
                                    <div id="totp-setup-section" style="display: none;">
                                        <hr>
                                        <h5><i class="fa fa-mobile"></i> <?php echo esc_html(sc_t('settings.setup_authenticator', 'Setup Authenticator App')); ?></h5>
                                        <ol class="mt-3">
                                            <li><?php echo esc_html(sc_t('settings.step1_download', 'Download an authenticator app (Google Authenticator, Authy, etc.)')); ?></li>
                                            <li><?php echo esc_html(sc_t('settings.step2_scan', 'Scan the QR code below with your app')); ?></li>
                                            <li><?php echo esc_html(sc_t('settings.step3_enter', 'Enter the 6-digit code from the app to verify')); ?></li>
                                        </ol>

                                        <div class="text-center my-4">
                                            <div id="qr-code-container" class="mb-3">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only"><?php echo esc_html(sc_t('general.loading', 'Loading...')); ?></span>
                                                </div>
                                            </div>
                                            <p class="text-muted small">
                                                <?php echo esc_html(sc_t('settings.cant_scan', "Can't scan? Enter this code manually:")); ?> <br>
                                                <code id="manual-secret" class="user-select-all"></code>
                                            </p>
                                        </div>

                                        <div class="form-group">
                                            <label for="totp-verify-code"><?php echo esc_html(sc_t('settings.enter_6digit', 'Enter 6-digit code from app')); ?></label>
                                            <input type="text" class="form-control" id="totp-verify-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="max-width: 200px; font-size: 24px; letter-spacing: 8px; text-align: center;">
                                        </div>

                                        <button type="button" class="btn btn-success" id="verify-totp-setup">
                                            <i class="fa fa-check"></i> <?php echo esc_html(sc_t('settings.verify_enable', 'Verify and Enable')); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="cancel-totp-setup">
                                            <?php echo esc_html(sc_t('settings.cancel', 'Cancel')); ?>
                                        </button>
                                    </div>

                                    <!-- Email Setup Section (hidden by default) -->
                                    <div id="email-setup-section" style="display: none;">
                                        <hr>
                                        <h5><i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('settings.setup_email_verify', 'Setup Email Verification')); ?></h5>
                                        <p><?php echo esc_html(sc_t('settings.email_verify_info', 'A verification code will be sent to your email')); ?> (<strong><?php echo esc_html($current_user->user_email); ?></strong>) <?php echo esc_html(sc_t('settings.each_login', 'each time you log in.')); ?></p>

                                        <div class="form-group">
                                            <label for="email-verify-code"><?php echo esc_html(sc_t('settings.enter_email_code', 'Enter the code sent to your email')); ?></label>
                                            <input type="text" class="form-control" id="email-verify-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="max-width: 200px; font-size: 24px; letter-spacing: 8px; text-align: center;">
                                        </div>

                                        <button type="button" class="btn btn-info" id="send-email-code">
                                            <i class="fa fa-paper-plane"></i> <?php echo esc_html(sc_t('settings.send_code', 'Send Code')); ?>
                                        </button>
                                        <button type="button" class="btn btn-success" id="verify-email-setup" style="display: none;">
                                            <i class="fa fa-check"></i> <?php echo esc_html(sc_t('settings.verify_enable', 'Verify and Enable')); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="cancel-email-setup">
                                            <?php echo esc_html(sc_t('settings.cancel', 'Cancel')); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Login Security Card -->
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-history"></i> <?php echo esc_html(sc_t('settings.login_security', 'Login Security')); ?></h2>
                                <small class="text-muted"><?php echo esc_html(sc_t('settings.monitor_access', 'Monitor your account access')); ?></small>
                            </div>
                            <div class="body">
                                <h5><?php echo esc_html(sc_t('settings.recent_login_activity', 'Recent Login Activity')); ?></h5>
                                <p class="text-muted"><?php echo esc_html(sc_t('settings.recent_login_hint', 'Your recent login sessions are listed below.')); ?></p>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html(sc_t('settings.date', 'Date')); ?></th>
                                                <th><?php echo esc_html(sc_t('settings.ip_address', 'IP Address')); ?></th>
                                                <th><?php echo esc_html(sc_t('settings.browser', 'Browser')); ?></th>
                                                <th><?php echo esc_html(sc_t('settings.status', 'Status')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="login-history">
                                            <?php
                                            // Get recent login activity from activity logs
                                            global $wpdb;
                                            $table = $wpdb->prefix . 'sc_activity_logs';

                                            // Check if table exists
                                            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

                                            if ($table_exists) {
                                                $logins = $wpdb->get_results($wpdb->prepare(
                                                    "SELECT * FROM $table
                                                     WHERE user_id = %d
                                                     AND activity_type = 'security'
                                                     AND action IN ('login_success', 'login_failed')
                                                     ORDER BY created_at DESC
                                                     LIMIT 10",
                                                    $user_id
                                                ));

                                                if ($logins):
                                                    foreach ($logins as $login):
                                                        $status_class = $login->action === 'login_success' ? 'success' : 'danger';
                                                        $status_text = $login->action === 'login_success' ? sc_t('settings.success', 'Success') : sc_t('settings.failed', 'Failed');
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($login->created_at))); ?></td>
                                                <td><code><?php echo esc_html($login->ip_address); ?></code></td>
                                                <td><?php echo esc_html(wp_kses_post(substr($login->user_agent, 0, 50))); ?>...</td>
                                                <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                            </tr>
                                            <?php
                                                    endforeach;
                                                else:
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted"><?php echo esc_html(sc_t('settings.no_login_history', 'No login history available yet.')); ?></td>
                                            </tr>
                                            <?php
                                                endif;
                                            } else {
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted"><?php echo esc_html(sc_t('settings.login_tracking_setup', 'Login history tracking is being set up...')); ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP / Email Settings -->
                    <?php $sc_smtp = function_exists('sc_get_smtp_settings') ? sc_get_smtp_settings() : array('enabled'=>false,'host'=>'smtp.hostinger.com','port'=>465,'secure'=>'ssl','username'=>'','password'=>'','from_email'=>'','from_name'=>get_bloginfo('name')); ?>
                    <div class="tab-pane fade" id="smtp-settings">
                        <div class="card">
                            <div class="header">
                                <h2><i class="fa fa-envelope-o"></i> Email / SMTP Configuration</h2>
                                <small class="text-muted">إعدادات إرسال الإيميلات (Hostinger افتراضياً)</small>
                            </div>
                            <div class="body">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i>
                                    <strong>Hostinger:</strong> Host = <code>smtp.hostinger.com</code>، Port = <code>465</code> (SSL) أو <code>587</code> (TLS).
                                    Username = الإيميل الكامل، Password = باسوورد الإيميل من لوحة Hostinger.
                                </div>

                                <form id="smtp-settings-form">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="smtp-enabled" name="smtp_enabled" <?php checked($sc_smtp['enabled']); ?>>
                                            <label class="custom-control-label" for="smtp-enabled"><strong>تفعيل SMTP</strong></label>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="smtp-host">SMTP Host <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="smtp-host" name="smtp_host" value="<?php echo esc_attr($sc_smtp['host']); ?>" placeholder="smtp.hostinger.com">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="smtp-port">Port <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="smtp-port" name="smtp_port" value="<?php echo esc_attr($sc_smtp['port']); ?>" placeholder="465">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="smtp-secure">Encryption</label>
                                        <select class="form-control" id="smtp-secure" name="smtp_secure">
                                            <option value="ssl" <?php selected($sc_smtp['secure'], 'ssl'); ?>>SSL (port 465)</option>
                                            <option value="tls" <?php selected($sc_smtp['secure'], 'tls'); ?>>TLS (port 587)</option>
                                            <option value="none" <?php selected($sc_smtp['secure'], 'none'); ?>>None</option>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="smtp-username">Username (full email) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="smtp-username" name="smtp_username" value="<?php echo esc_attr($sc_smtp['username']); ?>" placeholder="noreply@yourdomain.com" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="smtp-password">Password <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" id="smtp-password" name="smtp_password" placeholder="<?php echo !empty($sc_smtp['password']) ? '••••••• (محفوظ — اتركه فارغاً للإبقاء)' : 'Email password'; ?>" autocomplete="new-password">
                                                <small class="text-muted">الباسوورد بيتحفظ مشفر</small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="smtp-from-email">From Email</label>
                                                <input type="email" class="form-control" id="smtp-from-email" name="smtp_from_email" value="<?php echo esc_attr($sc_smtp['from_email']); ?>" placeholder="noreply@yourdomain.com">
                                                <small class="text-muted">عادة نفس الـ Username</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="smtp-from-name">From Name</label>
                                                <input type="text" class="form-control" id="smtp-from-name" name="smtp_from_name" value="<?php echo esc_attr($sc_smtp['from_name']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> حفظ إعدادات SMTP
                                    </button>
                                </form>

                                <hr>

                                <h5><i class="fa fa-paper-plane"></i> Send Test Email</h5>
                                <p class="text-muted">جرّب الإعدادات قبل الاعتماد عليها</p>
                                <form id="smtp-test-form" class="form-inline">
                                    <div class="form-group mr-2">
                                        <input type="email" class="form-control" id="smtp-test-email" placeholder="recipient@example.com" required>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-paper-plane"></i> Send Test
                                    </button>
                                </form>
                                <div id="smtp-test-result" class="mt-3"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#smtp-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var orig = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.post(scDashboard.ajaxurl, {
            action: 'sc_save_smtp_settings',
            nonce: scDashboard.nonce,
            smtp_enabled: $('#smtp-enabled').is(':checked') ? 1 : 0,
            smtp_host: $('#smtp-host').val(),
            smtp_port: $('#smtp-port').val(),
            smtp_secure: $('#smtp-secure').val(),
            smtp_username: $('#smtp-username').val(),
            smtp_password: $('#smtp-password').val(),
            smtp_from_email: $('#smtp-from-email').val(),
            smtp_from_name: $('#smtp-from-name').val()
        }).done(function(res) {
            if (res.success) {
                if (typeof toastr !== 'undefined') toastr.success(res.data.message || 'Saved!');
                else alert(res.data.message || 'Saved!');
                $('#smtp-password').val('');
            } else {
                var msg = (res.data && res.data.message) || 'Failed to save';
                if (typeof toastr !== 'undefined') toastr.error(msg); else alert(msg);
            }
        }).fail(function() {
            if (typeof toastr !== 'undefined') toastr.error('Server error'); else alert('Server error');
        }).always(function() {
            $btn.prop('disabled', false).html(orig);
        });
    });

    $('#smtp-test-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var $result = $('#smtp-test-result');
        var orig = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
        $result.html('');

        $.post(scDashboard.ajaxurl, {
            action: 'sc_send_test_email',
            nonce: scDashboard.nonce,
            test_email: $('#smtp-test-email').val()
        }).done(function(res) {
            if (res.success) {
                $result.html('<div class="alert alert-success"><i class="fa fa-check"></i> ' + res.data.message + '</div>');
            } else {
                var msg = (res.data && res.data.message) || 'Failed';
                var html = '<div class="alert alert-danger"><i class="fa fa-times"></i> ' + msg + '</div>';
                if (res.data && res.data.smtp_log) {
                    html += '<details class="mt-2"><summary style="cursor:pointer;color:#666;">Show SMTP conversation log</summary>'
                          + '<pre style="background:#f5f5f5;padding:10px;font-size:11px;direction:ltr;text-align:left;max-height:300px;overflow:auto;">'
                          + $('<div>').text(res.data.smtp_log).html() + '</pre></details>';
                }
                $result.html(html);
            }
        }).fail(function() {
            $result.html('<div class="alert alert-danger">Server error</div>');
        }).always(function() {
            $btn.prop('disabled', false).html(orig);
        });
    });
});
</script>

<!-- Edit Page Modal -->
<div class="modal fade" id="editPageModal" tabindex="-1" role="dialog" aria-labelledby="editPageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPageModalLabel">
                    <i class="fa fa-edit"></i> <?php echo esc_html(sc_t('settings.edit', 'Edit')); ?> <span id="modal-page-title"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('settings.close', 'Close')); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="edit-page-form">
                    <input type="hidden" id="edit-page-id" name="page_id">
                    <div class="form-group">
                        <label for="edit-page-title-input"><i class="fa fa-heading"></i> <?php echo esc_html(sc_t('settings.page_title', 'Page Title')); ?></label>
                        <input type="text" class="form-control" id="edit-page-title-input" name="title" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa fa-file-text"></i> <?php echo esc_html(sc_t('settings.page_content', 'Page Content')); ?></label>
                        <?php
                        // WordPress Editor settings
                        $editor_settings = array(
                            'textarea_name' => 'page_content',
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny' => false,
                            'quicktags' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,alignleft,aligncenter,alignright,|,undo,redo',
                                'toolbar2' => 'forecolor,backcolor,|,hr,|,removeformat,charmap,|,outdent,indent,|,wp_help',
                            ),
                        );
                        wp_editor('', 'edit_page_content_editor', $editor_settings);
                        ?>
                    </div>
                </form>
                <div id="modal-loading" class="text-center py-5" style="display: none;">
                    <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                    <p class="mt-2"><?php echo esc_html(sc_t('settings.loading_content', 'Loading page content...')); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php echo esc_html(sc_t('settings.cancel', 'Cancel')); ?>
                </button>
                <button type="button" class="btn btn-primary" id="save-page-btn">
                    <i class="fa fa-save"></i> <?php echo esc_html(sc_t('settings.save_changes', 'Save Changes')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom Alert Styles with Primary Color */
    .settings-alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        border-left: 4px solid;
        animation: slideDown 0.4s ease-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .settings-alert i {
        font-size: 20px;
    }

    .settings-alert.alert-success {
        background: linear-gradient(135deg, <?php echo esc_attr($sc_primary_color); ?>15 0%, <?php echo esc_attr($sc_primary_color); ?>25 100%);
        color: <?php echo esc_attr($sc_primary_color); ?>;
        border-left-color: <?php echo esc_attr($sc_primary_color); ?>;
    }

    .settings-alert.alert-error {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.15) 100%);
        color: #dc3545;
        border-left-color: #dc3545;
    }

    .settings-alert .close-alert {
        margin-left: auto;
        cursor: pointer;
        font-size: 20px;
        opacity: 0.6;
        transition: opacity 0.3s ease;
    }

    .settings-alert .close-alert:hover {
        opacity: 1;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Loading Spinner */
    .btn-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: translateY(-50%) rotate(360deg); }
    }
</style>

<script>
var settingsTranslations = {
    gateway_updated: '<?php echo esc_js(sc_t("settings.gateway_updated", "Gateway updated")); ?>',
    failed_update_gateway: '<?php echo esc_js(sc_t("settings.failed_update_gateway", "Failed to update gateway")); ?>',
    connection_error: '<?php echo esc_js(sc_t("general.connection_error", "Connection error")); ?>'
};

jQuery(document).ready(function($) {
    // Primary color from PHP
    var primaryColor = '<?php echo esc_js($sc_primary_color); ?>';

    // Function to show alert
    function showAlert(message, type) {
        var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        var alertHtml = `
            <div class="settings-alert alert-${type}">
                <i class="fa ${icon}"></i>
                <span>${message}</span>
                <i class="fa fa-times close-alert"></i>
            </div>
        `;

        $('#settings-alerts').html(alertHtml);

        // Scroll to alert
        $('html, body').animate({
            scrollTop: $('#settings-alerts').offset().top - 100
        }, 500);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('#settings-alerts .settings-alert').fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Close alert manually
    $(document).on('click', '.close-alert', function() {
        $(this).closest('.settings-alert').fadeOut(400, function() {
            $(this).remove();
        });
    });

    // Helper function to get descriptive error message
    function getAjaxErrorMessage(xhr, status, defaultAction) {
        var errorMsg = 'Failed to ' + defaultAction + '. ';
        if (status === 'timeout') {
            errorMsg += 'The request timed out. Please check your connection and try again.';
        } else if (xhr.status === 0) {
            errorMsg += 'Could not connect to server. Please check your internet connection.';
        } else if (xhr.status === 403) {
            errorMsg += 'You do not have permission to perform this action. Please refresh and try again.';
        } else if (xhr.status === 429) {
            errorMsg += 'Too many requests. Please wait a moment and try again.';
        } else if (xhr.status === 500) {
            errorMsg += 'Server error occurred. Please contact support if this persists.';
        } else if (xhr.status === 404) {
            errorMsg += 'The requested resource was not found. Please refresh the page.';
        } else {
            errorMsg += 'Please try again or contact support if the problem persists.';
        }
        return errorMsg;
    }

    // Homepage mode toggle
    $('#homepage-mode').on('change', function() {
        $('#featured-event-group').toggle($(this).val() === 'single_event');
    });

    // Platform Settings Form
    $('#platform-settings-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var formData = new FormData(this);

        formData.append('action', 'sc_update_platform_settings');
        formData.append('nonce', '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>');

        // Show loading state
        submitBtn.addClass('btn-loading').prop('disabled', true);
        $('#settings-alerts').html('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);

                if (response.success) {
                    showAlert(response.data.message || 'Platform settings updated successfully!', 'success');

                    // Reload page after 2 seconds to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(response.data.message || 'Failed to update settings.', 'error');
                }
            },
            error: function(xhr, status) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'save platform settings'), 'error');
            }
        });
    });

    // Account Settings Form
    $('#account-settings-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var newPassword = $('#new-password').val();
        var confirmPassword = $('#confirm-password').val();

        // Password validation
        if (newPassword && newPassword !== confirmPassword) {
            showAlert('Passwords do not match!', 'error');
            return false;
        }

        var formData = form.serialize();
        formData += '&action=sc_update_account_settings';
        formData += '&nonce=<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>';

        // Show loading state
        submitBtn.addClass('btn-loading').prop('disabled', true);
        $('#settings-alerts').html('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);

                if (response.success) {
                    showAlert(response.data.message || 'Account settings updated successfully!', 'success');

                    // Clear password fields
                    $('#current-password, #new-password, #confirm-password').val('');

                    // Reload after 2 seconds if email changed
                    if ($('#email').val() !== '<?php echo esc_js($current_user->user_email); ?>') {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    showAlert(response.data.message || 'Failed to update account settings.', 'error');
                }
            },
            error: function(xhr, status) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'update account settings'), 'error');
            }
        });
    });

    // Logo preview
    $('#platform-logo-input').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-preview').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        }
    });

    // Light logo preview
    $('#platform-logo-light-input').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-light-preview').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        }
    });

    // Site Icon preview
    $('#site-icon-input').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#icon-preview').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        }
    });

    // SEO Settings Form
    $('#seo-settings-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var formData = new FormData(this);

        formData.append('action', 'sc_update_seo_settings');
        formData.append('nonce', '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>');

        // Show loading state
        submitBtn.addClass('btn-loading').prop('disabled', true);
        $('#settings-alerts').html('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);

                if (response.success) {
                    showAlert(response.data.message || 'SEO settings updated successfully!', 'success');

                    // Reload page after 2 seconds to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert(response.data.message || 'Failed to update SEO settings.', 'error');
                }
            },
            error: function(xhr, status) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'update SEO settings'), 'error');
            }
        });
    });

    // Create Privacy Policy Page
    $('#create-privacy-page').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();

        if (!confirm('Are you sure you want to create a Privacy Policy page?')) {
            return;
        }

        btn.html('<i class="fa fa-spinner fa-spin"></i> Creating...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_create_content_page',
                nonce: scDashboard.nonce,
                page_type: 'privacy'
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    btn.html(originalHtml).prop('disabled', false);
                    showAlert(response.data.message || 'Failed to create page.', 'error');
                }
            },
            error: function(xhr, status) {
                btn.html(originalHtml).prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'create Privacy Policy page'), 'error');
            }
        });
    });

    // Create Terms & Conditions Page
    $('#create-terms-page').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();

        if (!confirm('Are you sure you want to create a Terms & Conditions page?')) {
            return;
        }

        btn.html('<i class="fa fa-spinner fa-spin"></i> Creating...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_create_content_page',
                nonce: scDashboard.nonce,
                page_type: 'terms'
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    btn.html(originalHtml).prop('disabled', false);
                    showAlert(response.data.message || 'Failed to create page.', 'error');
                }
            },
            error: function(xhr, status) {
                btn.html(originalHtml).prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'create Terms & Conditions page'), 'error');
            }
        });
    });

    // Edit Page Button - Open Modal
    $(document).on('click', '.edit-page-btn', function() {
        var pageId = $(this).data('page-id');
        var pageTitle = $(this).data('page-title');

        // Set modal title
        $('#modal-page-title').text(pageTitle);

        // Show loading, hide form
        $('#edit-page-form').hide();
        $('#modal-loading').show();

        // Open modal
        $('#editPageModal').modal('show');

        // Fetch page content
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_page_content',
                nonce: scDashboard.nonce,
                page_id: pageId
            },
            success: function(response) {
                $('#modal-loading').hide();
                $('#edit-page-form').show();

                if (response.success) {
                    $('#edit-page-id').val(response.data.page_id);
                    $('#edit-page-title-input').val(response.data.title);

                    // Set content in WordPress editor
                    if (typeof tinymce !== 'undefined' && tinymce.get('edit_page_content_editor')) {
                        tinymce.get('edit_page_content_editor').setContent(response.data.content);
                    }
                    // Also set in textarea (for text mode)
                    $('#edit_page_content_editor').val(response.data.content);
                } else {
                    $('#editPageModal').modal('hide');
                    showAlert(response.data.message || 'Failed to load page content.', 'error');
                }
            },
            error: function(xhr, status) {
                $('#modal-loading').hide();
                $('#editPageModal').modal('hide');
                showAlert(getAjaxErrorMessage(xhr, status, 'load page content'), 'error');
            }
        });
    });

    // Save Page Content
    $('#save-page-btn').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        var pageId = $('#edit-page-id').val();
        var title = $('#edit-page-title-input').val();

        // Get content from WordPress editor
        var content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('edit_page_content_editor')) {
            content = tinymce.get('edit_page_content_editor').getContent();
        } else {
            content = $('#edit_page_content_editor').val();
        }

        if (!title.trim()) {
            showAlert('Page title is required.', 'error');
            return;
        }

        btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_update_page_content',
                nonce: scDashboard.nonce,
                page_id: pageId,
                title: title,
                content: content
            },
            success: function(response) {
                btn.html(originalHtml).prop('disabled', false);

                if (response.success) {
                    $('#editPageModal').modal('hide');
                    showAlert(response.data.message, 'success');
                } else {
                    showAlert(response.data.message || 'Failed to update page.', 'error');
                }
            },
            error: function(xhr, status) {
                btn.html(originalHtml).prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'save page content'), 'error');
            }
        });
    });

    // Reset modal when closed
    $('#editPageModal').on('hidden.bs.modal', function() {
        $('#edit-page-form')[0].reset();
        $('#edit-page-id').val('');

        // Clear WordPress editor content
        if (typeof tinymce !== 'undefined' && tinymce.get('edit_page_content_editor')) {
            tinymce.get('edit_page_content_editor').setContent('');
        }
        $('#edit_page_content_editor').val('');
    });

    // Re-initialize TinyMCE when modal is shown (fix for modal display issues)
    $('#editPageModal').on('shown.bs.modal', function() {
        if (typeof tinymce !== 'undefined' && tinymce.get('edit_page_content_editor')) {
            tinymce.get('edit_page_content_editor').focus();
        }
    });

    // ===================================
    // CURRENCY SETTINGS
    // ===================================

    // Currency preview update
    function updateCurrencyPreview() {
        var code = $('#currency-code').val();
        var position = $('#currency-position').val();
        var thousandSep = $('#thousand-separator').val();
        var decimalSep = $('#decimal-separator').val();
        var decimalPlaces = parseInt($('#decimal-places').val());

        // Get symbol from selected option text
        var currencies = <?php echo json_encode(sc_get_currencies()); ?>;
        var symbol = currencies[code] ? currencies[code].symbol : code;

        // Format sample number
        var num = 1234.56;
        var formatted = num.toFixed(decimalPlaces);
        var parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep || '');
        formatted = parts.join(decimalSep || '.');

        // Apply position
        var preview = '';
        if (position === 'before') {
            preview = symbol + ' ' + formatted;
        } else if (position === 'before_no_space') {
            preview = symbol + formatted;
        } else if (position === 'after_no_space') {
            preview = formatted + symbol;
        } else {
            preview = formatted + ' ' + symbol;
        }

        $('#currency-preview').text(preview);
    }

    // Update preview on any change
    $('#currency-code, #currency-position, #thousand-separator, #decimal-separator, #decimal-places').on('change', updateCurrencyPreview);

    // Initial preview update
    updateCurrencyPreview();

    // Currency Settings Form
    $('#currency-settings-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var formData = form.serialize();

        formData += '&action=sc_update_currency_settings';
        formData += '&nonce=<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>';

        submitBtn.addClass('btn-loading').prop('disabled', true);
        $('#settings-alerts').html('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);

                if (response.success) {
                    showAlert(response.data.message || 'Currency settings updated successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(response.data.message || 'Failed to update currency settings.', 'error');
                }
            },
            error: function(xhr, status) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'update currency settings'), 'error');
            }
        });
    });

    // ===================================
    // PAYMENT GATEWAY SETTINGS
    // ===================================

    // Gateway toggle (enable/disable)
    $('.gateway-toggle').on('change', function() {
        var gateway = $(this).data('gateway');
        var enabled = $(this).is(':checked');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_toggle_payment_gateway',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                gateway: gateway,
                enabled: enabled ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message || settingsTranslations.gateway_updated);
                } else {
                    toastr.error(response.data.message || settingsTranslations.failed_update_gateway);
                }
            },
            error: function() {
                toastr.error(settingsTranslations.connection_error);
            }
        });
    });

    // Gateway settings form
    $('.gateway-settings-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var gateway = form.data('gateway');
        var submitBtn = form.find('button[type="submit"]');
        var formData = form.serialize();

        formData += '&action=sc_save_gateway_settings';
        formData += '&nonce=<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>';

        submitBtn.addClass('btn-loading').prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);

                if (response.success) {
                    showAlert(response.data.message || 'Gateway settings saved!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(response.data.message || 'Failed to save gateway settings.', 'error');
                }
            },
            error: function(xhr, status) {
                submitBtn.removeClass('btn-loading').prop('disabled', false);
                showAlert(getAjaxErrorMessage(xhr, status, 'save gateway settings'), 'error');
            }
        });
    });

    // Test gateway connection
    $('.test-gateway-btn').on('click', function() {
        var btn = $(this);
        var gateway = btn.data('gateway');
        var originalHtml = btn.html();

        btn.html('<i class="fa fa-spinner fa-spin"></i> Testing...').prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_test_gateway_connection',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                gateway: gateway
            },
            success: function(response) {
                btn.html(originalHtml).prop('disabled', false);

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Connection Successful!',
                        text: response.data.message || 'Gateway connection is working.',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Failed',
                        text: response.data.message || 'Could not connect to gateway. Please check your credentials.'
                    });
                }
            },
            error: function() {
                btn.html(originalHtml).prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not reach the server. Please try again.'
                });
            }
        });
    });

    // ===================================
    // TWO-FACTOR AUTHENTICATION
    // ===================================

    var tfaSecret = '';

    // Select TOTP method
    $('#method-totp-card').on('click', function() {
        $(this).addClass('border-primary');
        $('#method-email-card').removeClass('border-primary');
        $('#email-setup-section').hide();
        $('#totp-setup-section').show();

        // Generate new TOTP secret
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_2fa_generate_secret',
                nonce: scDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    tfaSecret = response.data.secret;
                    $('#manual-secret').text(response.data.secret);
                    $('#qr-code-container').html('<img src="' + response.data.qr_url + '" alt="QR Code" style="max-width: 200px;">');
                } else {
                    showAlert(response.data.message || 'Failed to generate secret.', 'error');
                }
            },
            error: function() {
                showAlert('Connection error. Please try again.', 'error');
            }
        });
    });

    // Select Email method
    $('#method-email-card').on('click', function() {
        $(this).addClass('border-primary');
        $('#method-totp-card').removeClass('border-primary');
        $('#totp-setup-section').hide();
        $('#email-setup-section').show();
    });

    // Cancel TOTP setup
    $('#cancel-totp-setup').on('click', function() {
        $('#totp-setup-section').hide();
        $('#method-totp-card').removeClass('border-primary');
        tfaSecret = '';
    });

    // Cancel Email setup
    $('#cancel-email-setup').on('click', function() {
        $('#email-setup-section').hide();
        $('#method-email-card').removeClass('border-primary');
    });

    // Verify TOTP and enable 2FA
    $('#verify-totp-setup').on('click', function() {
        var btn = $(this);
        var code = $('#totp-verify-code').val();

        if (!code || code.length !== 6) {
            showAlert('Please enter a valid 6-digit code.', 'error');
            return;
        }

        btn.html('<i class="fa fa-spinner fa-spin"></i> Verifying...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_2fa_enable',
                nonce: scDashboard.nonce,
                method: 'totp',
                secret: tfaSecret,
                code: code
            },
            success: function(response) {
                btn.html('<i class="fa fa-check"></i> Verify and Enable').prop('disabled', false);

                if (response.success) {
                    showAlert('Two-Factor Authentication enabled successfully!', 'success');

                    // Show backup codes
                    if (response.data.backup_codes) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Save Your Backup Codes!',
                            html: '<p>Store these codes somewhere safe. You can use them if you lose access to your authenticator app.</p>' +
                                  '<div class="text-left bg-light p-3 rounded"><code>' +
                                  response.data.backup_codes.join('<br>') +
                                  '</code></div>',
                            confirmButtonText: 'I\'ve saved my codes',
                            allowOutsideClick: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                } else {
                    showAlert(response.data.message || 'Invalid code. Please try again.', 'error');
                }
            },
            error: function() {
                btn.html('<i class="fa fa-check"></i> Verify and Enable').prop('disabled', false);
                showAlert('Connection error. Please try again.', 'error');
            }
        });
    });

    // Send email verification code
    $('#send-email-code').on('click', function() {
        var btn = $(this);
        btn.html('<i class="fa fa-spinner fa-spin"></i> Sending...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_2fa_send_email_code',
                nonce: scDashboard.nonce
            },
            success: function(response) {
                btn.html('<i class="fa fa-paper-plane"></i> Resend Code').prop('disabled', false);

                if (response.success) {
                    showAlert('Verification code sent to your email!', 'success');
                    $('#verify-email-setup').show();
                } else {
                    showAlert(response.data.message || 'Failed to send code.', 'error');
                }
            },
            error: function() {
                btn.html('<i class="fa fa-paper-plane"></i> Send Code').prop('disabled', false);
                showAlert('Connection error. Please try again.', 'error');
            }
        });
    });

    // Verify Email and enable 2FA
    $('#verify-email-setup').on('click', function() {
        var btn = $(this);
        var code = $('#email-verify-code').val();

        if (!code || code.length !== 6) {
            showAlert('Please enter a valid 6-digit code.', 'error');
            return;
        }

        btn.html('<i class="fa fa-spinner fa-spin"></i> Verifying...').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_2fa_enable',
                nonce: scDashboard.nonce,
                method: 'email',
                code: code
            },
            success: function(response) {
                btn.html('<i class="fa fa-check"></i> Verify and Enable').prop('disabled', false);

                if (response.success) {
                    showAlert('Two-Factor Authentication enabled successfully!', 'success');

                    // Show backup codes
                    if (response.data.backup_codes) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Save Your Backup Codes!',
                            html: '<p>Store these codes somewhere safe. You can use them if you lose access to your email.</p>' +
                                  '<div class="text-left bg-light p-3 rounded"><code>' +
                                  response.data.backup_codes.join('<br>') +
                                  '</code></div>',
                            confirmButtonText: 'I\'ve saved my codes',
                            allowOutsideClick: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                } else {
                    showAlert(response.data.message || 'Invalid code. Please try again.', 'error');
                }
            },
            error: function() {
                btn.html('<i class="fa fa-check"></i> Verify and Enable').prop('disabled', false);
                showAlert('Connection error. Please try again.', 'error');
            }
        });
    });

    // Disable 2FA
    $('#disable-2fa').on('click', function() {
        Swal.fire({
            icon: 'warning',
            title: 'Disable Two-Factor Authentication?',
            text: 'Your account will be less secure without 2FA. Are you sure?',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, disable it',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_2fa_disable',
                        nonce: scDashboard.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showAlert('Two-Factor Authentication disabled.', 'success');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            showAlert(response.data.message || 'Failed to disable 2FA.', 'error');
                        }
                    },
                    error: function() {
                        showAlert('Connection error. Please try again.', 'error');
                    }
                });
            }
        });
    });

    // View backup codes
    $('#view-backup-codes').on('click', function() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_2fa_get_backup_codes',
                nonce: scDashboard.nonce
            },
            success: function(response) {
                if (response.success && response.data.codes) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Your Backup Codes',
                        html: '<p>Use these codes if you lose access to your authenticator.</p>' +
                              '<div class="text-left bg-light p-3 rounded"><code>' +
                              response.data.codes.join('<br>') +
                              '</code></div>',
                        confirmButtonText: '<?php echo esc_js(sc_t('settings.close', 'Close')); ?>'
                    });
                } else {
                    showAlert('Could not retrieve backup codes.', 'error');
                }
            },
            error: function() {
                showAlert('Connection error. Please try again.', 'error');
            }
        });
    });

    // Regenerate backup codes
    $('#regenerate-backup-codes').on('click', function() {
        Swal.fire({
            icon: 'warning',
            title: 'Regenerate Backup Codes?',
            text: 'This will invalidate all your current backup codes.',
            showCancelButton: true,
            confirmButtonText: 'Regenerate',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_2fa_regenerate_backup_codes',
                        nonce: scDashboard.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.codes) {
                            Swal.fire({
                                icon: 'success',
                                title: 'New Backup Codes Generated!',
                                html: '<p>Save these new codes. Your old codes are no longer valid.</p>' +
                                      '<div class="text-left bg-light p-3 rounded"><code>' +
                                      response.data.codes.join('<br>') +
                                      '</code></div>',
                                confirmButtonText: 'I\'ve saved my codes',
                                allowOutsideClick: false
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            showAlert('Failed to regenerate codes.', 'error');
                        }
                    },
                    error: function() {
                        showAlert('Connection error. Please try again.', 'error');
                    }
                });
            }
        });
    });

    // Change 2FA method
    $('#change-2fa-method').on('click', function() {
        Swal.fire({
            icon: 'info',
            title: 'Change Authentication Method',
            text: 'To change your method, you need to disable 2FA first, then enable it again with the new method.',
            confirmButtonText: 'Understood'
        });
    });

});
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
