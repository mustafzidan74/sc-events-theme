<?php
/**
 * Dashboard Sidebar Component - With Full Localization Support
 *
 * @package sc_events
 * @version 2.0.0
 */

$current_user = wp_get_current_user();
$dashboard_url = home_url('/event-manager-dashboard/');
$assets_url = get_template_directory_uri() . '/assets/';

// Check RTL - use our custom function if available
$is_rtl = function_exists('sc_is_rtl') ? sc_is_rtl() : is_rtl();

// Translation helper
function _sc($key, $en, $ar = '') {
    if (function_exists('sc_t')) {
        $translated = sc_t($key, $en);
        return $translated !== $key ? $translated : $en;
    }
    $is_rtl = function_exists('sc_is_rtl') ? sc_is_rtl() : is_rtl();
    return ($is_rtl && !empty($ar)) ? $ar : $en;
}

// Get current page from query var or URL
$current_page = get_query_var('dashboard_page');
if (empty($current_page)) {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (preg_match('#/event-manager-dashboard/([^/]+)/?#', $request_uri, $matches)) {
        $current_page = $matches[1];
    } else {
        $current_page = 'home';
    }
}

// Check which modules are enabled
$events_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('events');
$speakers_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('speakers');
$attendees_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('attendees');
$certificates_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('certificates');
$booths_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('booths');
$chat_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('chat');
$referrals_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('referrals');
$sponsors_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sponsors');
$partners_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('partners');
$payments_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('payments');
$coupons_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('coupons');
$companies_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('companies');
?>

<div id="left-sidebar" class="sidebar">
    <button type="button" class="btn-toggle-offcanvas"><i class="fa fa-arrow-<?php echo $is_rtl ? 'right' : 'left'; ?>"></i></button>
    <div class="sidebar-scroll">
        <div class="user-account">
            <?php
                $platform_logo_id = get_option('sc_platform_logo');
                if ($platform_logo_id) {
                    $logo = wp_get_attachment_image_src($platform_logo_id, 'full');
                    echo '<img class="rounded-circle user-photo" src="' . esc_url($logo[0]) . '" width="48" height="48" alt="' . get_bloginfo('name') . '">';
                } else {
                    echo '<img class="rounded-circle user-photo" src="' . $assets_url . 'images/logo.png" width="48" height="48" alt="Logo">';
                }
            ?>

            <div class="dropdown">
                <span><?php echo esc_html(sc_t('dashboard.welcome', 'Welcome,')); ?></span>
                <a href="javascript:void(0);" class="dropdown-toggle user-name" data-toggle="dropdown">
                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-<?php echo $is_rtl ? 'left' : 'right'; ?> account">
                    <li><a href="<?php echo $dashboard_url; ?>settings#account"><i class="icon-user"></i><?php echo esc_html(sc_t('dashboard.profile', 'My Profile')); ?></a></li>
                    <li><a href="<?php echo $dashboard_url; ?>settings"><i class="icon-settings"></i><?php echo esc_html(sc_t('nav.settings', 'Settings')); ?></a></li>
                    <li class="divider"></li>
                    <li><a href="#" class="logout-link"><i class="icon-power"></i><?php echo esc_html(sc_t('dashboard.logout', 'Logout')); ?></a></li>
                </ul>
            </div>
            <hr>
            <?php
            global $wpdb;
            // Query custom tables directly (not wp_posts)
            $published_events = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed')"
            );

            $total_attendees = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active'"
            );

            $total_revenue = (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active' AND payment_status = 'success'"
            );

            $currency_symbol = function_exists('sc_get_currency_symbol') ? sc_get_currency_symbol() : 'SAR';
            ?>
            <ul class="row list-unstyled">
                <li class="col-3">
                    <small><?php echo esc_html(sc_t('nav.events', 'Events')); ?></small>
                    <h6><?php echo $published_events; ?></h6>
                </li>
                <li class="col-4">
                    <small><?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?></small>
                    <h6><?php echo $total_attendees ?: 0; ?></h6>
                </li>
                <li class="col-5">
                    <small><?php echo esc_html(sc_t('reports.revenue_report', 'Revenue')); ?></small>
                    <h6>
                        <?php echo number_format($total_revenue, 0); ?>
                        <span><?php echo $currency_symbol; ?></span>
                    </h6>
                </li>
            </ul>
        </div>

        <!-- Main Navigation -->
        <nav id="left-sidebar-nav" class="sidebar-nav">
            <ul id="main-menu" class="metismenu li_animation_delay">
                <!-- Dashboard -->
                <li class="<?php echo ($current_page == 'home') ? 'active' : ''; ?>">
                    <a href="<?php echo $dashboard_url; ?>home">
                        <i class="fa fa-dashboard"></i>
                        <span><?php echo esc_html(sc_t('dashboard.title', 'Dashboard')); ?></span>
                    </a>
                </li>

                <?php if ($events_enabled): ?>
                <!-- Events Section -->
                <li class="<?php echo in_array($current_page, array('events', 'event-create', 'event-edit', 'workshops', 'workshop-create', 'workshop-edit', 'workshop-attendees', 'workshop-scanner', 'categories', 'speakers', 'organizers', 'sponsors', 'sponsor-create', 'sponsor-edit', 'partners', 'partner-create', 'partner-edit', 'halls', 'hall-create', 'hall-edit', 'schedules', 'schedule-create', 'schedule-edit')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-calendar"></i>
                        <span><?php echo esc_html(sc_t('nav.events', 'Events')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('events', 'event-create', 'event-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>events"><i class="fa fa-list"></i> <?php echo esc_html(sc_t('events.all_events', 'All Events')); ?></a>
                        </li>
                        <li class="<?php echo in_array($current_page, array('workshops', 'workshop-create', 'workshop-edit', 'workshop-attendees', 'workshop-scanner')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>workshops"><i class="fa fa-flask"></i> <?php echo esc_html(sc_t('nav.workshops', 'Workshops')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'categories') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>categories"><i class="fa fa-folder"></i> <?php echo esc_html(sc_t('events.category', 'Categories')); ?></a>
                        </li>
                        <?php if ($speakers_enabled): ?>
                        <li class="<?php echo ($current_page == 'speakers') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>speakers"><i class="fa fa-microphone"></i> <?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></a>
                        </li>
                        <?php endif; ?>
                        <li class="<?php echo ($current_page == 'organizers') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>organizers"><i class="fa fa-building"></i> <?php echo esc_html(sc_t('dashboard_pages.organizers', 'Organizers')); ?></a>
                        </li>
                        <?php if ($sponsors_enabled): ?>
                        <li class="<?php echo in_array($current_page, array('sponsors', 'sponsor-create', 'sponsor-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>sponsors"><i class="fa fa-handshake-o"></i> <?php echo esc_html(sc_t('nav.sponsors', 'Sponsors')); ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if ($partners_enabled): ?>
                        <li class="<?php echo in_array($current_page, array('partners', 'partner-create', 'partner-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>partners"><i class="fa fa-users"></i> <?php echo esc_html(sc_t('nav.partners', 'Partners')); ?></a>
                        </li>
                        <?php endif; ?>
                        <li class="<?php echo in_array($current_page, array('halls', 'hall-create', 'hall-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>halls"><i class="fa fa-building"></i> <?php echo esc_html(sc_t('nav.halls', 'Halls')); ?></a>
                        </li>
                        <li class="<?php echo in_array($current_page, array('schedules', 'schedule-create', 'schedule-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>schedules"><i class="fa fa-calendar-check-o"></i> <?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php $sessions_enabled = !function_exists('sc_is_module_enabled') || sc_is_module_enabled('sessions'); ?>
                <?php if ($sessions_enabled): ?>
                <!-- Sessions Section -->
                <li class="<?php echo in_array($current_page, array('sessions', 'session-create', 'session-edit', 'session-attendees')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-th-list"></i>
                        <span><?php echo esc_html(sc_t('nav.sessions', 'Sessions')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('sessions', 'session-edit', 'session-attendees')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>sessions"><i class="fa fa-list"></i> <?php echo esc_html(sc_t('sessions.all_sessions', 'All Sessions')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'session-create') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>session-create"><i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_session', 'Add Session')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($attendees_enabled): ?>
                <!-- Attendees Section -->
                <li class="<?php echo in_array($current_page, array('attendees', 'attendee-add', 'attendee-edit', 'scanner', 'customers', 'badges')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-users"></i>
                        <span><?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('attendees', 'attendee-add', 'attendee-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>attendees"><i class="fa fa-list"></i> <?php echo esc_html(sc_t('attendees.all_attendees', 'All Attendees')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'scanner') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>scanner"><i class="fa fa-qrcode"></i> <?php echo esc_html(sc_t('nav.scanner', 'Attendance Scanner')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'customers') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>customers"><i class="fa fa-user-circle"></i> <?php echo esc_html(sc_t('nav.users', 'Customers')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'badges') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>badges"><i class="fa fa-id-card"></i> <?php echo esc_html(sc_t('nav.badges', 'Badges / Lanyards')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($attendees_enabled): ?>
                <!-- Scanner Team Section -->
                <li class="<?php echo in_array($current_page, array('scanners', 'scanner-create', 'scanner-edit')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-qrcode"></i>
                        <span><?php echo esc_html(sc_t('nav.scanner_team', 'Scanner Team')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('scanners', 'scanner-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>scanners"><i class="fa fa-list"></i> <?php echo esc_html(sc_t('scanners.all_scanners', 'All Scanners')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'scanner-create') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>scanner-create"><i class="fa fa-plus"></i> <?php echo esc_html(sc_t('scanners.add_scanner', 'Add Scanner')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($companies_enabled): ?>
                <!-- Company / B2B Section -->
                <li class="<?php echo in_array($current_page, array('company-attendees', 'company-attendee-add', 'company-attendee-edit', 'company-scanner')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-building-o"></i>
                        <span><?php echo esc_html(sc_t('attendees.company', 'Company / B2B')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('company-attendees', 'company-attendee-add', 'company-attendee-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>company-attendees"><i class="fa fa-briefcase"></i> <?php echo esc_html(sc_t('attendees.company', 'Company Attendees')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'company-scanner') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>company-scanner"><i class="fa fa-qrcode"></i> <?php echo esc_html(sc_t('scanner.title', 'Company Scanner')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($booths_enabled): ?>
                <!-- Booths Section -->
                <li class="<?php echo in_array($current_page, array('booths', 'booth-create', 'booth-edit', 'booth-types', 'booth-type-create', 'booth-type-edit', 'booth-bookings', 'booth-booking-create', 'booth-booking-edit', 'booth-floor-plan')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-th-large"></i>
                        <span><?php echo esc_html(sc_t('nav.booths', 'Booths')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('booths', 'booth-create', 'booth-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>booths"><i class="fa fa-th"></i> <?php echo esc_html(sc_t('booths.all_booths', 'All Booths')); ?></a>
                        </li>
                        <li class="<?php echo in_array($current_page, array('booth-types', 'booth-type-create', 'booth-type-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>booth-types"><i class="fa fa-tags"></i> <?php echo esc_html(sc_t('booths.booth_types', 'Booth Types')); ?></a>
                        </li>
                        <li class="<?php echo in_array($current_page, array('booth-bookings', 'booth-booking-create', 'booth-booking-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>booth-bookings"><i class="fa fa-calendar-check-o"></i> <?php echo esc_html(sc_t('booths.bookings', 'Bookings')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'booth-floor-plan') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>booth-floor-plan"><i class="fa fa-map"></i> <?php echo esc_html(sc_t('booths.floor_plan', 'Floor Plan')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($certificates_enabled): ?>
                <!-- Certificates Section -->
                <li class="<?php echo in_array($current_page, array('certificate-templates', 'certificate-template-create', 'certificate-template-edit', 'certificate-visual-builder', 'certificates', 'certificate-issue')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-certificate"></i>
                        <span><?php echo esc_html(sc_t('nav.certificates', 'Certificates')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo in_array($current_page, array('certificate-templates', 'certificate-template-create', 'certificate-template-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>certificate-templates"><i class="fa fa-file-text"></i> <?php echo esc_html(sc_t('certificates.certificate_template', 'Templates')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'certificate-visual-builder') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>certificate-visual-builder"><i class="fa fa-magic"></i> <?php echo esc_html(sc_t('dashboard_pages.visual_builder', 'Visual Builder')); ?></a>
                        </li>
                        <li class="<?php echo in_array($current_page, array('certificates', 'certificate-issue')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>certificates"><i class="fa fa-list"></i> <?php echo esc_html(sc_t('dashboard_pages.issued_certificates', 'Issued Certificates')); ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Finance Section -->
                <li class="<?php echo in_array($current_page, array('coupons', 'coupon-create', 'coupon-edit', 'coupon-categories', 'reports', 'analytics')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-money"></i>
                        <span><?php echo esc_html(sc_t('nav.payments', 'Finance')); ?></span>
                    </a>
                    <ul>
                        <?php if ($coupons_enabled): ?>
                        <li class="<?php echo in_array($current_page, array('coupons', 'coupon-create', 'coupon-edit')) ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>coupons"><i class="fa fa-ticket"></i> <?php echo esc_html(sc_t('dashboard_pages.coupons_management', 'Discount & Coupons')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'coupon-categories') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>coupon-categories"><i class="fa fa-folder"></i> <?php echo esc_html(sc_t('dashboard_pages.coupon_categories', 'Coupon Categories')); ?></a>
                        </li>
                        <?php endif; ?>
                        <li class="<?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>reports"><i class="fa fa-bar-chart"></i> <?php echo esc_html(sc_t('nav.reports', 'Reports')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'analytics') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>analytics"><i class="fa fa-line-chart"></i> <?php echo esc_html(sc_t('nav.analytics', 'Analytics')); ?></a>
                        </li>
                    </ul>
                </li>

                <?php if ($chat_enabled): ?>
                <!-- Chat -->
                <li class="<?php echo ($current_page == 'chat') ? 'active' : ''; ?>">
                    <a href="<?php echo $dashboard_url; ?>chat">
                        <i class="fa fa-comments"></i>
                        <span><?php echo esc_html(sc_t('nav.chat', 'Chat Messages')); ?></span>
                        <?php
                        $chat_unread = function_exists('sc_chat') ? sc_chat()->get_total_unread_count() : 0;
                        if ($chat_unread > 0): ?>
                            <span class="badge badge-danger float-<?php echo $is_rtl ? 'left' : 'right'; ?>"><?php echo $chat_unread; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Support -->
                <li class="<?php echo ($current_page == 'support') ? 'active' : ''; ?>">
                    <a href="<?php echo $dashboard_url; ?>support">
                        <i class="fa fa-envelope"></i>
                        <span><?php echo esc_html(sc_t('nav.support', 'Support Messages')); ?></span>
                    </a>
                </li>

                <!-- Notifications -->
                <li class="<?php echo ($current_page == 'notifications') ? 'active' : ''; ?>">
                    <a href="<?php echo $dashboard_url; ?>notifications">
                        <i class="fa fa-bell"></i>
                        <span><?php echo esc_html(sc_t('nav.notifications', 'Push Notifications')); ?></span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="<?php echo in_array($current_page, array('settings', 'module-manager')) ? 'active open' : ''; ?>">
                    <a href="#" class="has-arrow">
                        <i class="fa fa-cog"></i>
                        <span><?php echo esc_html(sc_t('nav.settings', 'Settings')); ?></span>
                    </a>
                    <ul>
                        <li class="<?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>settings"><i class="fa fa-sliders"></i> <?php echo esc_html(sc_t('settings.general_settings', 'General Settings')); ?></a>
                        </li>
                        <li class="<?php echo ($current_page == 'module-manager') ? 'active' : ''; ?>">
                            <a href="<?php echo $dashboard_url; ?>module-manager"><i class="fa fa-puzzle-piece"></i> <?php echo esc_html(sc_t('dashboard_pages.module_manager', 'Module Manager')); ?></a>
                        </li>
                    </ul>
                </li>

                <?php if (current_user_can('administrator')): ?>
                <li class="divider"></li>
                <li>
                    <a href="<?php echo admin_url(); ?>" target="_blank">
                        <i class="fa fa-wordpress"></i>
                        <span><?php echo esc_html(sc_t('dashboard.wp_admin', 'WP Admin')); ?></span>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="#" class="logout-link">
                        <i class="fa fa-power-off"></i>
                        <span><?php echo esc_html(sc_t('dashboard.logout', 'Logout')); ?></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>
