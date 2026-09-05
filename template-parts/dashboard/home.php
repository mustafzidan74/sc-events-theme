<?php
/**
 * Dashboard Home Page - Enhanced Version
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('nav.dashboard', 'Dashboard');
global $load_charts;
$load_charts = true;

// Translations
$t = array(
    'dashboard' => sc_t('nav.dashboard', 'Dashboard'),
    'welcome' => sc_t('dashboard_pages.welcome', 'Welcome'),
    'total_events' => sc_t('dashboard_pages.total_events', 'Total Events'),
    'upcoming_events' => sc_t('dashboard_pages.upcoming_events', 'Upcoming Events'),
    'past_events' => sc_t('dashboard_pages.past_events', 'Past Events'),
    'total_attendees' => sc_t('dashboard_pages.total_attendees', 'Total Attendees'),
    'confirmed' => sc_t('dashboard_pages.confirmed', 'Confirmed'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'total_revenue' => sc_t('dashboard_pages.total_revenue', 'Total Revenue'),
    'speakers' => sc_t('nav.speakers', 'Speakers'),
    'organizers' => sc_t('nav.organizers', 'Organizers'),
    'certificates' => sc_t('nav.certificates', 'Certificates'),
    'coupons' => sc_t('nav.coupons', 'Coupons'),
    'quick_actions' => sc_t('dashboard_pages.quick_actions', 'Quick Actions'),
    'create_event' => sc_t('dashboard_pages.create_event', 'Create Event'),
    'add_attendee' => sc_t('dashboard_pages.add_attendee', 'Add Attendee'),
    'view_reports' => sc_t('dashboard_pages.view_reports', 'View Reports'),
    'recent_events' => sc_t('dashboard_pages.recent_events', 'Recent Events'),
    'recent_attendees' => sc_t('dashboard_pages.recent_attendees', 'Recent Attendees'),
    'monthly_stats' => sc_t('dashboard_pages.monthly_stats', 'Monthly Statistics'),
);

// Check which modules are enabled
$certificates_enabled = function_exists('sc_module_active') ? sc_module_active('certificates') : true;
$coupons_enabled = function_exists('sc_module_active') ? sc_module_active('coupons') : true;
$sessions_enabled = function_exists('sc_module_active') ? sc_module_active('sessions') : true;
$venues_enabled = function_exists('sc_module_active') ? sc_module_active('venues') : true;
$chat_enabled = function_exists('sc_module_active') ? sc_module_active('chat') : true;
$staff_enabled = function_exists('sc_module_active') ? sc_module_active('staff') : true;
$booths_enabled = function_exists('sc_module_active') ? sc_module_active('booths') : true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Auto-complete past events (throttled to once per 5 minutes)
global $wpdb;
if (!get_transient('sc_auto_complete_check')) {
    $rows_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}sc_events SET status = 'completed'
         WHERE status = 'publish'
         AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
        date('Y-m-d'), date('Y-m-d')
    ));
    set_transient('sc_auto_complete_check', 1, 300);
    // If events were updated, clear dashboard and analytics caches
    if ($rows_updated > 0) {
        delete_transient('sc_dashboard_home_stats_v2');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sc_analytics_%' OR option_name LIKE '_transient_timeout_sc_analytics_%'");
    }
}

// Get statistics with caching (5 minutes cache)
$cache_key = 'sc_dashboard_home_stats_v2';
$cached_stats = get_transient($cache_key);

if ($cached_stats !== false) {
    extract($cached_stats);
} else {
    // Calculate fresh stats from Custom Tables
    $total_events_count = 0;
    $upcoming_events_count = 0;
    $past_events_count = 0;
    $total_attendees = 0;
    $confirmed_attendees = 0;
    $checked_in_count = 0;
    $total_revenue = 0;
    $total_speakers = 0;
    $total_organizers = 0;
    $total_certificates = 0;
    $total_coupons = 0;

    $today = date('Y-m-d');

    // Get events stats (include completed events in counts)
    $total_events_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed')");
    $upcoming_events_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events WHERE status = 'publish' AND start_date >= %s",
        $today
    ));
    $past_events_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') AND start_date < %s",
        $today
    ));

    // Get attendees stats
    $total_attendees = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active'");
    $confirmed_attendees = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active' AND payment_status = 'success'");
    $checked_in_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active' AND checked_in = 1");

    // Get total revenue
    $total_revenue = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}sc_attendees WHERE status = 'active' AND payment_status = 'success'");

    // Get speakers count
    $total_speakers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_speakers WHERE is_active = 1");

    // Get organizers count
    $total_organizers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_organizers WHERE is_active = 1");

    // Get certificates count
    $total_certificates = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_certificates WHERE status IN ('issued', 'downloaded')");

    // Get coupons count
    $total_coupons = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_coupons WHERE is_active = 1");

    // Monthly stats for chart (last 6 months)
    $monthly_attendees = array();
    $monthly_revenue = array();
    $month_labels = array();

    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_labels[] = date_i18n('M Y', strtotime("-$i months"));

        $monthly_attendees[] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees
             WHERE status = 'active' AND payment_status = 'success'
             AND DATE(created_at) BETWEEN %s AND %s",
            $month_start, $month_end
        ));

        $monthly_revenue[] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}sc_attendees
             WHERE status = 'active' AND payment_status = 'success'
             AND DATE(created_at) BETWEEN %s AND %s",
            $month_start, $month_end
        ));
    }

    // Today's stats
    $today_attendees = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE DATE(created_at) = %s",
        $today
    ));
    $today_revenue = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}sc_attendees
         WHERE payment_status = 'success' AND DATE(created_at) = %s",
        $today
    ));
    $today_checkins = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE DATE(checked_in_at) = %s",
        $today
    ));

    // This week stats
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $week_attendees = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees
         WHERE payment_status = 'success' AND DATE(created_at) BETWEEN %s AND %s",
        $week_start, $week_end
    ));
    $week_revenue = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount_paid), 0) FROM {$wpdb->prefix}sc_attendees
         WHERE payment_status = 'success' AND DATE(created_at) BETWEEN %s AND %s",
        $week_start, $week_end
    ));

    // Cache the stats for 5 minutes
    $stats_to_cache = array(
        'total_events_count' => $total_events_count,
        'upcoming_events_count' => $upcoming_events_count,
        'past_events_count' => $past_events_count,
        'total_attendees' => $total_attendees,
        'confirmed_attendees' => $confirmed_attendees,
        'checked_in_count' => $checked_in_count,
        'total_revenue' => $total_revenue,
        'total_speakers' => $total_speakers,
        'total_organizers' => $total_organizers,
        'total_certificates' => $total_certificates,
        'total_coupons' => $total_coupons,
        'monthly_attendees' => $monthly_attendees,
        'monthly_revenue' => $monthly_revenue,
        'month_labels' => $month_labels,
        'today_attendees' => $today_attendees,
        'today_revenue' => $today_revenue,
        'today_checkins' => $today_checkins,
        'week_attendees' => $week_attendees,
        'week_revenue' => $week_revenue,
    );
    set_transient($cache_key, $stats_to_cache, 300);
    extract($stats_to_cache);
}

$current_user = wp_get_current_user();
// Use dynamic currency from settings
$currency_symbol = sc_get_currency_symbol();

// Get recent events
$recent_events = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY created_at DESC LIMIT 5"
);

// Get upcoming events
$upcoming_events = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_events WHERE status = 'publish' AND start_date >= %s ORDER BY start_date ASC LIMIT 5",
    date('Y-m-d')
));

// Get recent attendees
$recent_attendees = $wpdb->get_results(
    "SELECT a.*, e.title as event_title
     FROM {$wpdb->prefix}sc_attendees a
     LEFT JOIN {$wpdb->prefix}sc_events e ON a.event_id = e.id
     WHERE a.status = 'active'
     ORDER BY a.created_at DESC LIMIT 5"
);

// Get top events by attendance
$top_events = $wpdb->get_results(
    "SELECT e.id, e.title, e.start_date, COUNT(a.id) as attendee_count, SUM(a.amount_paid) as revenue
     FROM {$wpdb->prefix}sc_events e
     LEFT JOIN {$wpdb->prefix}sc_attendees a ON e.id = a.event_id AND a.payment_status = 'success'
     WHERE e.status = 'publish'
     GROUP BY e.id
     ORDER BY attendee_count DESC
     LIMIT 5"
);
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo sc_t('dashboard_pages.event_manager_dashboard', 'Event Manager Dashboard'); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['dashboard']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/event-create'); ?>" class="btn btn-primary">
                                <i class="fa fa-plus"></i> <?php echo sc_t('dashboard_pages.create_new_event', 'Create New Event'); ?>
                            </a>
                            <button type="button" class="btn btn-outline-secondary ml-2" id="refresh-cache-btn" title="<?php echo sc_t('dashboard_pages.refresh_stats', 'Refresh dashboard statistics'); ?>">
                                <i class="fa fa-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Summary -->
        <div class="row clearfix">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="body">
                        <div class="row">
                            <div class="col-md-3 col-6 text-center border-right">
                                <h5 class="mb-0 text-success"><?php echo $today_attendees; ?></h5>
                                <small class="text-muted"><?php echo sc_t('dashboard_pages.today_registrations', "Today's Registrations"); ?></small>
                            </div>
                            <div class="col-md-3 col-6 text-center border-right">
                                <h5 class="mb-0 text-primary"><?php echo $currency_symbol . number_format($today_revenue, 0); ?></h5>
                                <small class="text-muted"><?php echo sc_t('dashboard_pages.today_revenue', "Today's Revenue"); ?></small>
                            </div>
                            <div class="col-md-3 col-6 text-center border-right">
                                <h5 class="mb-0 text-info"><?php echo $today_checkins; ?></h5>
                                <small class="text-muted"><?php echo sc_t('dashboard_pages.today_checkins', "Today's Check-ins"); ?></small>
                            </div>
                            <div class="col-md-3 col-6 text-center">
                                <h5 class="mb-0 text-warning"><?php echo $upcoming_events_count; ?></h5>
                                <small class="text-muted"><?php echo $t['upcoming_events']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Statistics Cards -->
        <div class="row clearfix row-deck">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted"><?php echo $t['total_events']; ?></span>
                                <h3 class="mb-0 mt-2"><?php echo $total_events_count; ?></h3>
                                <small class="text-success"><i class="fa fa-arrow-up"></i> <?php echo $upcoming_events_count; ?> <?php echo sc_t('dashboard_pages.upcoming', 'upcoming'); ?></small>
                            </div>
                            <div class="icon-in-bg bg-primary text-white rounded">
                                <i class="fa fa-calendar fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo home_url('/event-manager-dashboard/events'); ?>" class="card-footer text-center small text-muted">
                        <?php echo sc_t('dashboard_pages.view_all_events', 'View All Events'); ?> <i class="fa fa-angle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted"><?php echo $t['total_attendees']; ?></span>
                                <h3 class="mb-0 mt-2"><?php echo $confirmed_attendees; ?></h3>
                                <small class="text-info"><i class="fa fa-check-circle"></i> <?php echo $checked_in_count; ?> <?php echo $t['checked_in']; ?></small>
                            </div>
                            <div class="icon-in-bg bg-success text-white rounded">
                                <i class="fa fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>" class="card-footer text-center small text-muted">
                        <?php echo sc_t('dashboard_pages.view_all_attendees', 'View All Attendees'); ?> <i class="fa fa-angle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted"><?php echo sc_t('dashboard_pages.total_revenue', 'Total Revenue'); ?></span>
                                <h3 class="mb-0 mt-2"><?php echo $currency_symbol . number_format($total_revenue, 0); ?></h3>
                                <small class="text-success"><i class="fa fa-arrow-up"></i> <?php echo $currency_symbol . number_format($week_revenue, 0); ?> <?php echo sc_t('general.this_week', 'this week'); ?></small>
                            </div>
                            <div class="icon-in-bg bg-warning text-white rounded">
                                <i class="fa fa-money fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo home_url('/event-manager-dashboard/reports'); ?>" class="card-footer text-center small text-muted">
                        <?php echo sc_t('dashboard_pages.view_reports', 'View Reports'); ?> <i class="fa fa-angle-<?php echo sc_is_rtl() ? 'left' : 'right'; ?>"></i>
                    </a>
                </div>
            </div>

            <?php if ($certificates_enabled): ?>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted"><?php echo sc_t('nav.certificates', 'Certificates'); ?></span>
                                <h3 class="mb-0 mt-2"><?php echo $total_certificates; ?></h3>
                                <small class="text-muted"><i class="fa fa-certificate"></i> <?php echo sc_t('dashboard_pages.issued', 'Issued'); ?></small>
                            </div>
                            <div class="icon-in-bg bg-info text-white rounded">
                                <i class="fa fa-certificate fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo home_url('/event-manager-dashboard/certificates'); ?>" class="card-footer text-center small text-muted">
                        <?php echo sc_t('dashboard_pages.view_certificates', 'View Certificates'); ?> <i class="fa fa-angle-<?php echo sc_is_rtl() ? 'left' : 'right'; ?>"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Secondary Stats Row -->
        <div class="row clearfix">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-microphone fa-2x text-primary mb-2"></i>
                        <h4 class="mb-0"><?php echo $total_speakers; ?></h4>
                        <small class="text-muted"><?php echo sc_t('nav.speakers', 'Speakers'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-building fa-2x text-success mb-2"></i>
                        <h4 class="mb-0"><?php echo $total_organizers; ?></h4>
                        <small class="text-muted"><?php echo sc_t('dashboard_pages.organizers', 'Organizers'); ?></small>
                    </div>
                </div>
            </div>
            <?php if ($coupons_enabled): ?>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-ticket fa-2x text-warning mb-2"></i>
                        <h4 class="mb-0"><?php echo $total_coupons; ?></h4>
                        <small class="text-muted"><?php echo sc_t('dashboard_pages.active_coupons', 'Active Coupons'); ?></small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-calendar-check-o fa-2x text-info mb-2"></i>
                        <h4 class="mb-0"><?php echo $upcoming_events_count; ?></h4>
                        <small class="text-muted"><?php echo sc_t('dashboard_pages.upcoming', 'Upcoming'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-history fa-2x text-secondary mb-2"></i>
                        <h4 class="mb-0"><?php echo $past_events_count; ?></h4>
                        <small class="text-muted"><?php echo sc_t('dashboard_pages.past_events', 'Past Events'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card text-center">
                    <div class="body">
                        <i class="fa fa-qrcode fa-2x text-dark mb-2"></i>
                        <h4 class="mb-0"><?php echo $checked_in_count; ?></h4>
                        <small class="text-muted"><?php echo sc_t('dashboard_pages.checkins', 'Check-ins'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row clearfix">
            <div class="col-lg-8 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><?php echo sc_t('dashboard_pages.revenue_attendance_overview', 'Revenue & Attendance Overview'); ?></h2>
                        <small><?php echo sc_t('dashboard_pages.last_6_months', 'Last 6 months performance'); ?></small>
                    </div>
                    <div class="body">
                        <div id="attendance-chart" style="height: 300px"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><?php echo sc_t('dashboard_pages.top_events', 'Top Events'); ?></h2>
                        <small><?php echo sc_t('dashboard_pages.by_attendance', 'By attendance'); ?></small>
                    </div>
                    <div class="body">
                        <?php if (!empty($top_events)): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($top_events as $index => $event): ?>
                                    <li class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="badge badge-<?php echo $index < 3 ? 'primary' : 'secondary'; ?> mr-2"><?php echo $index + 1; ?></span>
                                            <div class="flex-grow-1">
                                                <div class="font-weight-bold text-truncate" style="max-width: 200px;">
                                                    <?php echo esc_html($event->title); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo (int)$event->attendee_count; ?> <?php echo ((int)$event->attendee_count === 1) ? sc_t('dashboard_pages.attendee', 'attendee') : sc_t('dashboard_pages.attendees', 'attendees'); ?> |
                                                    <?php echo $currency_symbol . number_format((float)$event->revenue, 0); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted text-center"><?php echo sc_t('dashboard_pages.no_events_data', 'No events data yet'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <!-- Upcoming Events -->
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-calendar-o text-primary"></i> <?php echo sc_t('dashboard_pages.upcoming_events', 'Upcoming Events'); ?></h2>
                        <ul class="header-dropdown">
                            <li><a href="<?php echo home_url('/event-manager-dashboard/events'); ?>" class="btn btn-sm btn-outline-primary"><?php echo sc_t('dashboard_pages.view_all', 'View All'); ?></a></li>
                        </ul>
                    </div>
                    <div class="body">
                        <?php if (!empty($upcoming_events)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($upcoming_events as $event):
                                            $days_left = floor((strtotime($event->start_date) - time()) / 86400);
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($event->title); ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fa fa-clock-o"></i> <?php echo date('M j, Y', strtotime($event->start_date)); ?>
                                                        <?php if ($event->start_time): ?>
                                                            <?php echo sc_t('dashboard_pages.at', 'at'); ?> <?php echo date('g:i A', strtotime($event->start_time)); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td class="text-right">
                                                    <?php if ($days_left == 0): ?>
                                                        <span class="badge badge-danger"><?php echo sc_t('general.today', 'Today'); ?></span>
                                                    <?php elseif ($days_left == 1): ?>
                                                        <span class="badge badge-warning"><?php echo sc_t('general.tomorrow', 'Tomorrow'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-info"><?php echo $days_left; ?> <?php echo sc_t('dashboard_pages.days', 'days'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right" width="100">
                                                    <a href="<?php echo home_url('/event-manager-dashboard/event-edit?id=' . $event->id); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo home_url('/events/' . $event->slug); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo esc_attr(sc_t('dashboard_pages.view', 'View')); ?>" target="_blank">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fa fa-calendar-o fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?php echo sc_t('dashboard_pages.no_upcoming_events', 'No upcoming events'); ?></p>
                                <a href="<?php echo home_url('/event-manager-dashboard/event-create'); ?>" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus"></i> <?php echo sc_t('dashboard_pages.create_event', 'Create Event'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Attendees -->
            <div class="col-lg-6 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-users text-success"></i> <?php echo sc_t('dashboard_pages.recent_registrations', 'Recent Registrations'); ?></h2>
                        <ul class="header-dropdown">
                            <li><a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>" class="btn btn-sm btn-outline-success"><?php echo sc_t('dashboard_pages.view_all', 'View All'); ?></a></li>
                        </ul>
                    </div>
                    <div class="body">
                        <?php if (!empty($recent_attendees)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($recent_attendees as $attendee): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm bg-primary text-white rounded-circle mr-2" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                                            <?php echo strtoupper(substr($attendee->name, 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo esc_html($attendee->name); ?></strong><br>
                                                            <small class="text-muted"><?php echo esc_html($attendee->event_title); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <?php if ($attendee->payment_status == 'success'): ?>
                                                        <span class="badge badge-success"><?php echo sc_t('dashboard_pages.confirmed', 'Confirmed'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning"><?php echo ucfirst($attendee->payment_status); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right">
                                                    <small class="text-muted"><?php echo sc_time_ago($attendee->created_at); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fa fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><?php echo sc_t('dashboard_pages.no_registrations', 'No registrations yet'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row clearfix">
            <div class="col-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-bolt text-warning"></i> <?php echo sc_t('dashboard_pages.quick_actions', 'Quick Actions'); ?></h2>
                    </div>
                    <div class="body">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/event-create'); ?>" class="btn btn-outline-primary btn-block py-3">
                                    <i class="fa fa-plus-circle fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('dashboard_pages.create_event', 'Create Event'); ?>
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/attendee-add'); ?>" class="btn btn-outline-success btn-block py-3">
                                    <i class="fa fa-user-plus fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('dashboard_pages.add_attendee', 'Add Attendee'); ?>
                                </a>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/scanner'); ?>" class="btn btn-outline-info btn-block py-3">
                                    <i class="fa fa-qrcode fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('nav.scanner', 'Scanner'); ?>
                                </a>
                            </div>
                            <?php if ($coupons_enabled): ?>
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/coupon-create'); ?>" class="btn btn-outline-warning btn-block py-3">
                                    <i class="fa fa-ticket fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('dashboard_pages.create_coupon', 'Create Coupon'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($certificates_enabled): ?>
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/certificate-issue'); ?>" class="btn btn-outline-secondary btn-block py-3">
                                    <i class="fa fa-certificate fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('dashboard_pages.issue_certificates', 'Issue Certificates'); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
                                <a href="<?php echo home_url('/event-manager-dashboard/reports'); ?>" class="btn btn-outline-dark btn-block py-3">
                                    <i class="fa fa-bar-chart fa-2x d-block mb-2"></i>
                                    <?php echo sc_t('nav.reports', 'Reports'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.icon-in-bg {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}
.card-footer {
    background: #f8f9fa;
    border-top: 1px solid #eee;
    padding: 10px 15px;
    display: block;
    text-decoration: none;
}
.card-footer:hover {
    background: #e9ecef;
    text-decoration: none;
}
.number-chart .body {
    padding-bottom: 0;
}
.border-right {
    border-right: 1px solid #dee2e6 !important;
}
@media (max-width: 767px) {
    .border-right {
        border-right: none !important;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Chart data & labels (shared between ApexCharts and C3)
    var monthlyAttendeesData = <?php echo json_encode($monthly_attendees); ?>;
    var monthlyRevenueData = <?php echo json_encode($monthly_revenue); ?>;
    var monthLabels = <?php echo json_encode($month_labels); ?>;
    var chartLabelAttendees = '<?php echo esc_js(sc_t("dashboard_pages.attendees_label", "Attendees")); ?>';
    var chartLabelRevenue = '<?php echo esc_js(sc_t("dashboard_pages.revenue_label", "Revenue")); ?>';

    // ApexCharts for attendance overview
    if (typeof ApexCharts !== 'undefined') {
        var options = {
            series: [{
                name: chartLabelAttendees,
                type: 'column',
                data: monthlyAttendeesData
            }, {
                name: chartLabelRevenue,
                type: 'line',
                data: monthlyRevenueData
            }],
            chart: {
                height: 300,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 3]
            },
            dataLabels: {
                enabled: false
            },
            labels: monthLabels,
            xaxis: {
                type: 'category'
            },
            yaxis: [{
                min: 0,
                forceNiceScale: true,
                title: {
                    text: chartLabelAttendees,
                },
                labels: {
                    formatter: function(val) {
                        return Math.round(val);
                    }
                }
            }, {
                min: 0,
                forceNiceScale: true,
                opposite: true,
                title: {
                    text: chartLabelRevenue
                },
                labels: {
                    formatter: function(val) {
                        return '<?php echo $currency_symbol; ?>' + val.toFixed(0);
                    }
                }
            }],
            colors: ['var(--primary-color)', '#28a745'],
            legend: {
                position: 'top'
            }
        };

        var chart = new ApexCharts(document.querySelector("#attendance-chart"), options);
        chart.render();
    } else if (typeof c3 !== 'undefined') {
        // Fallback to C3
        c3.generate({
            bindto: '#attendance-chart',
            data: {
                columns: [
                    [chartLabelAttendees].concat(monthlyAttendeesData),
                    [chartLabelRevenue].concat(monthlyRevenueData)
                ],
                types: {
                    [chartLabelAttendees]: 'bar',
                    [chartLabelRevenue]: 'line'
                },
                axes: {
                    [chartLabelRevenue]: 'y2'
                },
                colors: {
                    [chartLabelAttendees]: 'var(--primary-color)',
                    [chartLabelRevenue]: '#28a745'
                }
            },
            axis: {
                x: {
                    type: 'category',
                    categories: monthLabels
                },
                y: {
                    min: 0,
                    padding: { bottom: 0 },
                    tick: {
                        format: function(d) { return Math.round(d); }
                    }
                },
                y2: {
                    show: true,
                    min: 0,
                    padding: { bottom: 0 }
                }
            }
        });
    } else {
        $('#attendance-chart').html('<div class="alert alert-warning text-center">Chart library not loaded</div>');
    }

    // Refresh Cache Button Handler
    $('#refresh-cache-btn').on('click', function() {
        var $btn = $(this);
        var $icon = $btn.find('i');

        $icon.addClass('fa-spin');
        $btn.prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_refresh_dashboard_cache',
                nonce: scDashboard.nonce
            },
            success: function(response) {
                $icon.removeClass('fa-spin');
                $btn.prop('disabled', false);

                if (response.success) {
                    toastr.success(scTrans.stats_refreshed);
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    toastr.error(response.data.message || scTrans.something_wrong);
                }
            },
            error: function() {
                $icon.removeClass('fa-spin');
                $btn.prop('disabled', false);
                toastr.error(scTrans.connection_error);
            }
        });
    });
});
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
