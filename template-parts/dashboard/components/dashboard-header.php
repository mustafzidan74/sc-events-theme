<?php
/**
 * Dashboard shell — document head, sidebar and top bar.
 *
 * Every dashboard page includes this first, then the (now empty) sidebar
 * component, then prints `#main-content`, then the footer. The sidebar and top
 * bar are rendered here so a page can't end up with one and not the other; the
 * footer closes the column and app wrappers opened below.
 *
 * Scanner-only accounts get a bare frame: the scanner page draws its own bar.
 *
 * @package sc_events
 */

if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Accel-Expires: 0');
header('Surrogate-Control: no-store');

require_once get_template_directory() . '/inc/admin-dashboard/dashboard-nav.php';

// Pages opt in to chart / date-picker / list-pattern assets by setting these as globals.
global $load_charts, $load_flatpickr, $load_wd_list, $load_wd_form;

$current_user  = wp_get_current_user();
$dashboard_url = home_url('/event-manager-dashboard/');
$admin_assets  = get_template_directory_uri() . '/assets/admin-dashboard/';
$theme_assets  = get_template_directory_uri() . '/assets/';
$asset_version = defined('SC_ASSET_VERSION') ? SC_ASSET_VERSION : '1';

$is_rtl = is_rtl() || (function_exists('sc_is_rtl') && sc_is_rtl());

// Bare frame for scanner-only accounts (they never see the menu).
$w_bare = SC_Event_Manager_Dashboard::is_event_scanner() && !SC_Event_Manager_Dashboard::is_event_manager();

$w_page   = sc_dashboard_current_page();
$w_groups = $w_bare ? array() : sc_dashboard_nav();
list($w_group, $w_link) = sc_dashboard_nav_locate($w_groups, $w_page);
$w_title  = sc_dashboard_page_title($w_page, $w_link);

// Server-side first paint of the theme; wd-shell.js takes over from here.
$w_theme = isset($_COOKIE['sc_dashboard_theme']) ? sanitize_key($_COOKIE['sc_dashboard_theme']) : 'light';
if (!in_array($w_theme, array('light', 'dark', 'system'), true)) {
    $w_theme = 'light';
}
$w_theme_attr = $w_theme === 'system' ? '' : ' data-theme="' . esc_attr($w_theme) . '"';

// Language: the site is pinned via sc_site_language, so the switch stays hidden.
$w_show_lang = !get_option('sc_site_language', '');

$chat_module_enabled = !function_exists('sc_module_active') || sc_module_active('chat');
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>"<?php echo $w_theme_attr; ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo esc_html($w_title); ?> · <?php echo esc_html(get_option('sc_platform_name', get_bloginfo('name'))); ?></title>
    <link rel="icon" href="<?php echo esc_url($admin_assets); ?>images/favicon.ico" type="image/x-icon">

    <script>
    (function () {
        var p = null;
        try { p = localStorage.getItem('sc_dashboard_theme'); } catch (e) {}
        if (p === 'light' || p === 'dark') { document.documentElement.setAttribute('data-theme', p); }
        else if (p === 'system') { document.documentElement.removeAttribute('data-theme'); }
    })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vendor -->
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/bootstrap/css/bootstrap.min.css">
    <?php if ($is_rtl): ?>
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/bootstrap/css/bootstrap.min.rtl.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/toastr/toastr.min.css">
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/select2/select2.css">
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/select2/select2-bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <?php if (isset($load_charts) && $load_charts): ?>
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/charts-c3/plugin.css">
    <?php endif; ?>
    <?php if (isset($load_flatpickr) && $load_flatpickr): ?>
    <link rel="stylesheet" href="<?php echo esc_url($admin_assets); ?>vendor/bootstrap-datepicker/bootstrap-datepicker3.css">
    <?php endif; ?>

    <?php wp_head(); ?>

    <!-- Wisdom design system (after wp_head so it wins over enqueued vendor CSS) -->
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/tokens.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/wd-shell.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/wd-components.css')); ?>">
    <?php if (!empty($load_wd_list)): ?>
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/wd-list.css')); ?>">
    <?php endif; ?>
    <?php if (!empty($load_wd_form)): ?>
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/wd-list.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/wd-form.css')); ?>">
    <?php endif; ?>

    <?php if ($chat_module_enabled && !$w_bare): ?>
    <script>
        var scAdminChatConfig = {
            ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('sc_chat_nonce')); ?>',
            chatPageUrl: '<?php echo esc_js($dashboard_url . 'chat'); ?>',
            iconUrl: '<?php echo esc_js(get_template_directory_uri() . '/assets/images/chat-icon.png'); ?>',
            chatLabel: '<?php echo esc_js(sc_t('nav.chat', 'Chat Messages')); ?>'
        };
    </script>
    <script src="<?php echo esc_url(sc_dashboard_asset('js/admin-chat-notifications.js')); ?>"></script>
    <?php endif; ?>

    <!-- jQuery + Bootstrap for page scripts (load order unchanged from the old shell) -->
    <script src="<?php echo esc_url($admin_assets); ?>bundles/libscripts.bundle.js"></script>
    <script src="<?php echo esc_url($admin_assets); ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body class="w-dash<?php echo $w_bare ? ' w-dash--bare' : ''; ?><?php echo $is_rtl ? ' rtl lang-ar' : ' ltr lang-en'; ?>"<?php echo $w_theme_attr; ?>>
<a class="sr-only sr-only-focusable w-skip" href="#main-content"><?php echo esc_html(sc_t('dashboard.skip_to_content', 'Skip to content')); ?></a>

<div class="w-dash-app">
<?php if (!$w_bare):
    $platform_name = get_option('sc_platform_name', get_bloginfo('name'));
    $logo_id = get_option('sc_platform_logo');
    $logo = $logo_id ? wp_get_attachment_image_src($logo_id, 'medium') : false;

    $initials = function_exists('sc_initials') ? sc_initials($current_user->display_name) : strtoupper(substr($current_user->display_name, 0, 2));
    $role_label = in_array('administrator', (array) $current_user->roles, true)
        ? sc_t('dashboard.role_admin', 'Administrator')
        : sc_t('dashboard.role_manager', 'Event manager');

    $chat_unread = ($chat_module_enabled && function_exists('sc_chat')) ? (int) sc_chat()->get_total_unread_count() : 0;
    ?>
    <aside class="w-side" id="w-side" aria-label="<?php echo esc_attr(sc_t('dashboard.main_menu', 'Main menu')); ?>">
        <div class="w-side__head">
            <a class="w-side__brand" href="<?php echo esc_url($dashboard_url . 'home'); ?>">
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo[0]); ?>" alt="<?php echo esc_attr($platform_name); ?>">
                <?php else: ?>
                    <span class="w-side__brand-name"><?php echo esc_html($platform_name); ?></span>
                <?php endif; ?>
            </a>
            <span class="w-side__chip"><?php echo esc_html(sc_t('dashboard.admin_chip', 'Admin')); ?></span>
            <button type="button" class="w-top__btn w-top__btn--icon w-side__close" aria-label="<?php echo esc_attr(sc_t('general.close', 'Close menu')); ?>">
                <?php echo sc_dashboard_icon('close', 16); ?>
            </button>
        </div>

        <nav class="w-side__nav">
            <?php foreach ($w_groups as $group):
                $has_active = $w_group && $w_group['key'] === $group['key'];

                // A group with a single link is just that link.
                if (count($group['links']) === 1):
                    $link = $group['links'][0];
                    $is_active = in_array($w_page, $link['match'], true);
                    ?>
                    <a class="w-side__link<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($dashboard_url . $link['slug']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                        <?php echo sc_dashboard_icon($group['icon']); ?>
                        <span><?php echo esc_html($link['label']); ?></span>
                    </a>
                    <?php continue;
                endif;

                $items_id = 'w-nav-' . $group['key'];
                ?>
                <div class="w-side__group">
                    <button type="button" class="w-side__toggle<?php echo $has_active ? ' has-active' : ''; ?>" data-group="<?php echo esc_attr($group['key']); ?>" aria-expanded="<?php echo $has_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($items_id); ?>">
                        <?php echo sc_dashboard_icon($group['icon']); ?>
                        <span class="w-side__label"><?php echo esc_html($group['label']); ?></span>
                        <?php if ($group['key'] === 'messages' && $chat_unread > 0 && !$has_active): ?>
                            <span class="w-side__badge"><?php echo (int) $chat_unread; ?></span>
                        <?php endif; ?>
                        <span class="w-side__chev"><?php echo sc_dashboard_icon('chevron', 14); ?></span>
                    </button>
                    <div class="w-side__items" id="<?php echo esc_attr($items_id); ?>"<?php echo $has_active ? '' : ' hidden'; ?>>
                        <?php foreach ($group['links'] as $link):
                            $is_active = in_array($w_page, $link['match'], true);
                            ?>
                            <a class="w-side__link<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($dashboard_url . $link['slug']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                                <span><?php echo esc_html($link['label']); ?></span>
                                <?php if ($link['slug'] === 'chat' && $chat_unread > 0): ?>
                                    <span class="w-side__badge"><?php echo (int) $chat_unread; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="w-side__sep" role="separator"></div>
            <?php if (current_user_can('administrator')): ?>
            <a class="w-side__link" href="<?php echo esc_url(admin_url()); ?>" target="_blank" rel="noopener">
                <?php echo sc_dashboard_icon('external'); ?>
                <span><?php echo esc_html(sc_t('dashboard.wp_admin', 'WP Admin')); ?></span>
            </a>
            <?php endif; ?>
            <a class="w-side__link logout-link" href="#">
                <?php echo sc_dashboard_icon('logout'); ?>
                <span><?php echo esc_html(sc_t('dashboard.logout', 'Log out')); ?></span>
            </a>
        </nav>

        <div class="w-side__foot">
            <span class="w-avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
            <a class="w-side__who" href="<?php echo esc_url($dashboard_url . 'settings#account'); ?>">
                <strong><?php echo esc_html($current_user->display_name); ?></strong>
                <span><?php echo esc_html($role_label); ?></span>
            </a>
            <div class="w-theme" role="group" aria-label="<?php echo esc_attr(sc_t('dashboard.toggle_theme', 'Theme')); ?>">
                <button type="button" class="w-theme__btn" data-w-theme="light" aria-pressed="<?php echo $w_theme === 'light' ? 'true' : 'false'; ?>" title="<?php echo esc_attr(sc_t('dashboard.theme_light', 'Light')); ?>"><?php echo sc_dashboard_icon('sun', 14); ?></button>
                <button type="button" class="w-theme__btn" data-w-theme="dark" aria-pressed="<?php echo $w_theme === 'dark' ? 'true' : 'false'; ?>" title="<?php echo esc_attr(sc_t('dashboard.theme_dark', 'Dark')); ?>"><?php echo sc_dashboard_icon('moon', 14); ?></button>
                <button type="button" class="w-theme__btn" data-w-theme="system" aria-pressed="<?php echo $w_theme === 'system' ? 'true' : 'false'; ?>" title="<?php echo esc_attr(sc_t('dashboard.theme_system', 'System')); ?>"><?php echo sc_dashboard_icon('laptop', 14); ?></button>
            </div>
        </div>
    </aside>
    <div class="w-scrim" id="w-scrim" hidden></div>
<?php endif; ?>

<div class="w-dash-col">
<?php if (!$w_bare):
    // Next featured event, for the countdown chip. One row, cheap.
    $w_event = null;
    $featured_id = (int) get_option('sc_featured_event_id', 0);
    if ($featured_id && class_exists('SC_Event')) {
        $ev = SC_Event::get($featured_id);
        if ($ev && !empty($ev->start_date)) {
            $days = (int) floor((strtotime($ev->start_date) - strtotime(current_time('Y-m-d'))) / DAY_IN_SECONDS);
            if ($days >= 0) {
                $w_event = array('title' => $ev->title, 'days' => $days, 'id' => (int) $ev->id);
            }
        }
    }
    $create_links = sc_dashboard_create_links();
    ?>
    <header class="w-top">
        <button type="button" class="w-top__btn w-top__btn--icon w-top__menu" id="w-drawer-open" aria-controls="w-side" aria-expanded="false" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.toggle_menu', 'Open menu')); ?>">
            <?php echo sc_dashboard_icon('menu', 18); ?>
        </button>

        <ol class="w-crumbs">
            <?php if ($w_group && count($w_group['links']) > 1): ?>
                <li><a href="<?php echo esc_url($dashboard_url . $w_group['links'][0]['slug']); ?>"><?php echo esc_html($w_group['label']); ?></a></li>
            <?php endif; ?>
            <?php if ($w_link && $w_link['slug'] !== $w_page): ?>
                <li><a href="<?php echo esc_url($dashboard_url . $w_link['slug']); ?>"><?php echo esc_html($w_link['label']); ?></a></li>
            <?php endif; ?>
            <li><span aria-current="page"><?php echo esc_html($w_title); ?></span></li>
        </ol>

        <div class="w-top__actions">
            <?php if ($w_event): ?>
            <a class="w-top__btn w-event-chip" href="<?php echo esc_url($dashboard_url . 'event-view?id=' . $w_event['id']); ?>" title="<?php echo esc_attr($w_event['title']); ?>">
                <span class="w-event-chip__dot" aria-hidden="true"></span>
                <span><?php echo esc_html(wp_trim_words($w_event['title'], 4, '…')); ?></span>
                <span class="w-event-chip__days"><?php
                    echo $w_event['days'] === 0
                        ? esc_html(sc_t('dashboard.today', 'today'))
                        : esc_html(sprintf(sc_t('dashboard.in_days', 'in %d days'), $w_event['days']));
                ?></span>
            </a>
            <?php endif; ?>

            <?php if ($w_show_lang): ?>
            <button type="button" class="w-top__btn lang-switch" data-lang="<?php echo $is_rtl ? 'en' : 'ar'; ?>"><?php echo $is_rtl ? 'EN' : 'عربي'; ?></button>
            <?php endif; ?>

            <?php if ($create_links): ?>
            <div class="w-menu">
                <button type="button" class="w-top__btn w-top__btn--primary" data-w-menu aria-haspopup="true" aria-expanded="false" aria-controls="w-create-menu">
                    <?php echo sc_dashboard_icon('plus', 14); ?>
                    <span class="w-top__create-label"><?php echo esc_html(sc_t('dashboard.create', 'Create')); ?></span>
                </button>
                <div class="w-menu__panel" id="w-create-menu" role="menu" hidden>
                    <div class="w-menu__title"><?php echo esc_html(sc_t('dashboard.create_new', 'Create new')); ?></div>
                    <?php foreach ($create_links as $cl): ?>
                        <a class="w-menu__item" role="menuitem" href="<?php echo esc_url($dashboard_url . $cl['slug']); ?>"><?php echo esc_html($cl['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>
<?php endif; ?>

<script>
window.scTrans = {
    success: '<?php echo esc_js(sc_t("general.success", "Success")); ?>',
    error: '<?php echo esc_js(sc_t("general.error", "Error")); ?>',
    warning: '<?php echo esc_js(sc_t("general.warning", "Warning")); ?>',
    info: '<?php echo esc_js(sc_t("general.info", "Info")); ?>',
    loading: '<?php echo esc_js(sc_t("general.loading", "Loading...")); ?>',
    processing: '<?php echo esc_js(sc_t("general.processing", "Processing...")); ?>',
    please_wait: '<?php echo esc_js(sc_t("dashboard_pages.please_wait", "Please wait...")); ?>',
    confirm_delete: '<?php echo esc_js(sc_t("general.confirm_delete", "Are you sure you want to delete this?")); ?>',
    confirm_action: '<?php echo esc_js(sc_t("general.are_you_sure", "Are you sure?")); ?>',
    cannot_undo: '<?php echo esc_js(sc_t("general.cannot_undo", "This action cannot be undone")); ?>',
    yes_delete: '<?php echo esc_js(sc_t("general.yes", "Yes, delete it")); ?>',
    yes_confirm: '<?php echo esc_js(sc_t("general.confirm", "Yes, confirm")); ?>',
    cancel: '<?php echo esc_js(sc_t("general.cancel", "Cancel")); ?>',
    saved: '<?php echo esc_js(sc_t("general.success", "Saved successfully")); ?>',
    deleted: '<?php echo esc_js(sc_t("general.deleted", "Deleted successfully")); ?>',
    updated: '<?php echo esc_js(sc_t("general.updated", "Updated successfully")); ?>',
    created: '<?php echo esc_js(sc_t("general.created", "Created successfully")); ?>',
    connection_error: '<?php echo esc_js(sc_t("errors.connection_error", "Connection error. Please try again.")); ?>',
    try_again: '<?php echo esc_js(sc_t("general.try_again", "Please try again")); ?>',
    something_wrong: '<?php echo esc_js(sc_t("errors.something_wrong", "Something went wrong")); ?>',
    required_field: '<?php echo esc_js(sc_t("validation.required_field", "This field is required")); ?>',
    fill_required: '<?php echo esc_js(sc_t("validation.fill_required", "Please fill in all required fields")); ?>',
    invalid_email: '<?php echo esc_js(sc_t("validation.invalid_email", "Please enter a valid email")); ?>',
    select_event: '<?php echo esc_js(sc_t("dashboard_pages.select_event", "Please select an event")); ?>',
    event_created: '<?php echo esc_js(sc_t("events.event_created", "Event created successfully")); ?>',
    event_updated: '<?php echo esc_js(sc_t("events.event_updated", "Event updated successfully")); ?>',
    event_deleted: '<?php echo esc_js(sc_t("events.event_deleted", "Event deleted successfully")); ?>',
    enter_event_title: '<?php echo esc_js(sc_t("events.enter_title", "Please enter an event title")); ?>',
    select_date: '<?php echo esc_js(sc_t("events.select_date", "Please select a date")); ?>',
    select_start_date: '<?php echo esc_js(sc_t("events.select_start_date", "Please select a start date")); ?>',
    add_event_day: '<?php echo esc_js(sc_t("events.add_event_day", "Please add at least one event day")); ?>',
    day_already_added: '<?php echo esc_js(sc_t("events.day_already_added", "This day is already added")); ?>',
    saved_as_draft: '<?php echo esc_js(sc_t("events.saved_as_draft", "Event saved as draft")); ?>',
    ticket_added: '<?php echo esc_js(sc_t("tickets.ticket_added", "Ticket added successfully")); ?>',
    ticket_updated: '<?php echo esc_js(sc_t("tickets.ticket_updated", "Ticket updated successfully")); ?>',
    ticket_deleted: '<?php echo esc_js(sc_t("tickets.ticket_deleted", "Ticket deleted successfully")); ?>',
    enter_ticket_name: '<?php echo esc_js(sc_t("tickets.enter_name", "Please enter a ticket name")); ?>',
    one_card_required: '<?php echo esc_js(sc_t("tickets.one_card_required", "At least one card is required")); ?>',
    attendee_added: '<?php echo esc_js(sc_t("attendees.attendee_added", "Attendee added successfully")); ?>',
    attendee_updated: '<?php echo esc_js(sc_t("attendees.attendee_updated", "Attendee updated successfully")); ?>',
    attendee_deleted: '<?php echo esc_js(sc_t("attendees.attendee_deleted", "Attendee deleted successfully")); ?>',
    certificate_issued: '<?php echo esc_js(sc_t("certificates.certificate_issued", "Certificate issued successfully")); ?>',
    certificates_issued: '<?php echo esc_js(sc_t("certificates.certificates_issued", "Certificates issued successfully")); ?>',
    email_sent: '<?php echo esc_js(sc_t("certificates.email_sent", "Email sent successfully")); ?>',
    coupon_created: '<?php echo esc_js(sc_t("coupons.coupon_created", "Coupon created successfully")); ?>',
    coupon_updated: '<?php echo esc_js(sc_t("coupons.coupon_updated", "Coupon updated successfully")); ?>',
    coupon_deleted: '<?php echo esc_js(sc_t("coupons.coupon_deleted", "Coupon deleted successfully")); ?>',
    select_event_first: '<?php echo esc_js(sc_t("scanner.select_event_first", "Please select an event first")); ?>',
    stats_refreshed: '<?php echo esc_js(sc_t("dashboard_pages.stats_refreshed", "Statistics refreshed")); ?>',
    images_uploaded: '<?php echo esc_js(sc_t("images.images_uploaded", "Images uploaded successfully")); ?>',
    image_uploaded: '<?php echo esc_js(sc_t("images.image_uploaded", "Image uploaded successfully")); ?>',
    upload_error: '<?php echo esc_js(sc_t("images.upload_error", "Error uploading images")); ?>'
};
</script>
<?php if ($w_show_lang): ?>
<script>
document.addEventListener('click', function (e) {
    var el = e.target.closest('.lang-switch');
    if (!el) { return; }
    e.preventDefault();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo esc_js(admin_url('admin-ajax.php')); ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () { if (xhr.status === 200) { window.location.reload(); } };
    xhr.send('action=sc_switch_language&lang=' + encodeURIComponent(el.getAttribute('data-lang')) + '&nonce=<?php echo esc_js(wp_create_nonce('sc_language_switch')); ?>');
});
</script>
<?php endif; ?>
