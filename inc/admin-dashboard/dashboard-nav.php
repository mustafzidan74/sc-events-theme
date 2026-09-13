<?php
/**
 * Dashboard navigation model — the single list the sidebar, the top-bar
 * breadcrumb and the Create menu are built from.
 *
 * Every entry is a real routed page. Groups and links follow the module
 * switches (Module Manager) the same way the page router does, so a disabled
 * module disappears from the menu instead of leading to "Module Disabled".
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Line icons (24px grid, stroke) keyed by name.
 */
function sc_dashboard_icon($name, $size = 18) {
    static $paths = array(
        'home'     => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'calendar' => 'M4 5h16v16H4zM4 10h16M8 3v4M16 3v4',
        'users'    => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21a8 8 0 0 1 16 0',
        'scan'     => 'M3 7V4h3M21 7V4h-3M3 17v3h3M21 17v3h-3M7 12h10',
        'handshake'=> 'M3 12l4-4 4 3 3-2 7 5M7 16l2 2M11 16l2 2M3 12l6 6h2l7-7',
        'booth'    => 'M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6',
        'cert'     => 'M12 15a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM8.5 14 7 22l5-3 5 3-1.5-8',
        'coupon'   => 'M20 12V7H4v5a2 2 0 0 1 0 4v1h16v-1a2 2 0 0 1 0-4zM10 9v6',
        'chart'    => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'chat'     => 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
        'chevron'  => 'm9 18 6-6-6-6',
        'menu'     => 'M4 6h16M4 12h16M4 18h16',
        'close'    => 'M18 6 6 18M6 6l12 12',
        'plus'     => 'M12 5v14M5 12h14',
        'sun'      => 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
        'moon'     => 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
        'laptop'   => 'M4 5h16v10H4zM2 19h20',
        'logout'   => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'external' => 'M14 3h7v7M10 14 21 3M18 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5',
        'user'     => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
    );
    if (!isset($paths[$name])) {
        return '';
    }
    return sprintf(
        '<svg class="w-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%2$s"/></svg>',
        (int) $size,
        esc_attr($paths[$name])
    );
}

/**
 * The current dashboard page slug, from the query var or — when rewrite
 * rules are missing — from the URL path.
 */
function sc_dashboard_current_page() {
    $page = get_query_var('dashboard_page');
    if (!$page) {
        $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
        $page = preg_match('#/event-manager-dashboard/([^/]+)/?#', $path, $m) ? $m[1] : 'home';
    }
    return preg_replace('/[^a-z0-9\-]/', '', strtolower($page));
}

/**
 * Navigation groups visible to the current user.
 *
 * Each link: slug, label, and `match` — every page slug that should highlight it.
 *
 * @return array[] groups: key, label, icon, links[]
 */
function sc_dashboard_nav() {
    $on = function ($module) {
        return !function_exists('sc_is_module_enabled') || sc_is_module_enabled($module);
    };
    $link = function ($slug, $label, $match = array()) {
        return array('slug' => $slug, 'label' => $label, 'match' => array_merge(array($slug), $match));
    };

    $groups = array();

    $groups[] = array('key' => 'overview', 'label' => sc_t('dashboard.title', 'Dashboard'), 'icon' => 'home', 'links' => array(
        $link('home', sc_t('dashboard.title', 'Dashboard')),
    ));

    if ($on('events')) {
        $links = array(
            $link('events', sc_t('events.all_events', 'Events'), array('event-create', 'event-edit', 'event-view')),
            $link('workshops', sc_t('nav.workshops', 'Workshops'), array('workshop-create', 'workshop-edit', 'workshop-attendees', 'workshop-scanner')),
        );
        if ($on('sessions')) {
            $links[] = $link('sessions', sc_t('nav.sessions', 'Sessions'), array('session-create', 'session-edit', 'session-attendees'));
        }
        $links[] = $link('schedules', sc_t('nav.schedules', 'Schedules'), array('schedule-create', 'schedule-edit'));
        $links[] = $link('halls', sc_t('nav.halls', 'Halls'), array('hall-create', 'hall-edit'));
        $links[] = $link('categories', sc_t('events.category', 'Categories'));
        $groups[] = array('key' => 'events', 'label' => sc_t('nav.events', 'Events'), 'icon' => 'calendar', 'links' => $links);
    }

    $people = array();
    if ($on('attendees')) {
        $people[] = $link('attendees', sc_t('attendees.all_attendees', 'Attendees'), array('attendee-add', 'attendee-edit'));
        $people[] = $link('customers', sc_t('nav.users', 'Customers'));
    }
    if ($on('events') && $on('speakers')) {
        $people[] = $link('speakers', sc_t('nav.speakers', 'Speakers'), array('speaker-create', 'speaker-edit'));
    }
    if ($on('events')) {
        $people[] = $link('organizers', sc_t('dashboard_pages.organizers', 'Organizers'), array('organizer-create', 'organizer-edit'));
    }
    if ($people) {
        $groups[] = array('key' => 'people', 'label' => sc_t('dashboard.people', 'People'), 'icon' => 'users', 'links' => $people);
    }

    $partners = array();
    if ($on('events') && $on('sponsors')) {
        $partners[] = $link('sponsors', sc_t('nav.sponsors', 'Sponsors'), array('sponsor-create', 'sponsor-edit'));
    }
    if ($on('events') && $on('partners')) {
        $partners[] = $link('partners', sc_t('nav.partners', 'Partners'), array('partner-create', 'partner-edit'));
    }
    if ($on('companies')) {
        $partners[] = $link('company-attendees', sc_t('attendees.company', 'Company / B2B'), array('company-attendee-add', 'company-attendee-edit', 'company-scanner'));
    }
    if ($partners) {
        $groups[] = array('key' => 'partners', 'label' => sc_t('dashboard.partners_group', 'Sponsors & partners'), 'icon' => 'handshake', 'links' => $partners);
    }

    if ($on('attendees')) {
        $groups[] = array('key' => 'onsite', 'label' => sc_t('dashboard.onsite', 'On-site'), 'icon' => 'scan', 'links' => array(
            $link('scanner', sc_t('nav.scanner', 'Scanner')),
            $link('scanners', sc_t('nav.scanner_team', 'Scanner team'), array('scanner-create', 'scanner-edit')),
            $link('badges', sc_t('nav.badges', 'Badges')),
        ));
    }

    if ($on('booths')) {
        $groups[] = array('key' => 'booths', 'label' => sc_t('nav.booths', 'Booths'), 'icon' => 'booth', 'links' => array(
            $link('booths', sc_t('booths.all_booths', 'All booths'), array('booth-create', 'booth-edit')),
            $link('booth-types', sc_t('booths.booth_types', 'Booth types'), array('booth-type-create', 'booth-type-edit')),
            $link('booth-bookings', sc_t('booths.bookings', 'Bookings'), array('booth-booking-create', 'booth-booking-edit')),
            $link('booth-floor-plan', sc_t('booths.floor_plan', 'Floor plan')),
        ));
    }

    if ($on('certificates')) {
        $groups[] = array('key' => 'certificates', 'label' => sc_t('nav.certificates', 'Certificates'), 'icon' => 'cert', 'links' => array(
            $link('certificates', sc_t('dashboard_pages.issued_certificates', 'Issued'), array('certificate-issue', 'certificate-rebuild')),
            $link('certificate-templates', sc_t('certificates.certificate_template', 'Templates'), array('certificate-template-create', 'certificate-template-edit')),
            $link('certificate-visual-builder', sc_t('dashboard_pages.visual_builder', 'Visual builder')),
        ));
    }

    $sales = array();
    if ($on('coupons')) {
        $sales[] = $link('coupons', sc_t('dashboard_pages.coupons_management', 'Coupons'), array('coupon-create', 'coupon-edit'));
        $sales[] = $link('coupon-categories', sc_t('dashboard_pages.coupon_categories', 'Coupon categories'));
    }
    $sales[] = $link('reports', sc_t('nav.reports', 'Reports'));
    $sales[] = $link('analytics', sc_t('nav.analytics', 'Analytics'));
    $groups[] = array('key' => 'sales', 'label' => sc_t('dashboard.sales_reports', 'Sales & reports'), 'icon' => 'chart', 'links' => $sales);

    $messages = array();
    if ($on('chat')) {
        $messages[] = $link('chat', sc_t('nav.chat', 'Chat'));
    }
    $messages[] = $link('support', sc_t('nav.support', 'Support'));
    $messages[] = $link('notifications', sc_t('nav.notifications', 'Push notifications'));
    $groups[] = array('key' => 'messages', 'label' => sc_t('dashboard.messages', 'Messages'), 'icon' => 'chat', 'links' => $messages);

    $system = array($link('settings', sc_t('settings.general_settings', 'Settings')));
    if (current_user_can('administrator')) {
        $system[] = $link('module-manager', sc_t('dashboard_pages.module_manager', 'Module manager'));
    }
    $groups[] = array('key' => 'system', 'label' => sc_t('nav.settings', 'System'), 'icon' => 'settings', 'links' => $system);

    return $groups;
}

/**
 * The group and link for a page slug, or nulls when the page isn't in the menu.
 *
 * @return array{0: array|null, 1: array|null}
 */
function sc_dashboard_nav_locate($groups, $page) {
    foreach ($groups as $group) {
        foreach ($group['links'] as $link) {
            if (in_array($page, $link['match'], true)) {
                return array($group, $link);
            }
        }
    }
    return array(null, null);
}

/**
 * Title for the tab and the last breadcrumb.
 *
 * The header is included with get_template_part(), which can't see a page's
 * local $page_title, so the title comes from the slug: the menu label for a
 * menu page, otherwise the label plus what the sub-page does.
 */
function sc_dashboard_page_title($page, $link) {
    if (!$link) {
        return ucfirst(str_replace('-', ' ', $page));
    }
    if ($page === $link['slug']) {
        return $link['label'];
    }
    $actions = array(
        'create'   => sc_t('dashboard.title_new', 'New'),
        'add'      => sc_t('dashboard.title_new', 'New'),
        'edit'     => sc_t('dashboard.title_edit', 'Edit'),
        'view'     => sc_t('dashboard.title_details', 'Details'),
        'attendees'=> sc_t('nav.attendees', 'Attendees'),
        'scanner'  => sc_t('nav.scanner', 'Scanner'),
        'issue'    => sc_t('dashboard.title_issue', 'Issue'),
        'rebuild'  => sc_t('dashboard.title_rebuild', 'Rebuild'),
        'plan'     => sc_t('booths.floor_plan', 'Floor plan'),
    );
    $last = substr(strrchr($page, '-'), 1);
    return isset($actions[$last]) ? $actions[$last] : ucfirst(str_replace('-', ' ', $page));
}

/**
 * Real "create" pages for the top-bar Create menu, filtered by module.
 */
function sc_dashboard_create_links() {
    $on = function ($module) {
        return !function_exists('sc_is_module_enabled') || sc_is_module_enabled($module);
    };
    $links = array();
    if ($on('events')) {
        $links[] = array('slug' => 'event-create', 'label' => sc_t('dashboard.new_event', 'Event'));
        $links[] = array('slug' => 'workshop-create', 'label' => sc_t('dashboard.new_workshop', 'Workshop'));
    }
    if ($on('attendees')) {
        $links[] = array('slug' => 'attendee-add', 'label' => sc_t('dashboard.new_attendee', 'Attendee'));
    }
    if ($on('events') && $on('speakers')) {
        $links[] = array('slug' => 'speaker-create', 'label' => sc_t('dashboard.new_speaker', 'Speaker'));
    }
    if ($on('coupons')) {
        $links[] = array('slug' => 'coupon-create', 'label' => sc_t('dashboard.new_coupon', 'Coupon'));
    }
    return $links;
}
