<?php
/**
 * Advanced Analytics Dashboard Page
 *
 * Multi-section analytics page with ApexCharts.
 * Sections load independently via separate AJAX calls.
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

$page_title = sc_t('dashboard_pages.advanced_analytics', 'Advanced Analytics');
global $load_charts;
$load_charts = true;
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'analytics'       => sc_t('nav.analytics', 'Analytics'),
    'advanced_analytics' => sc_t('dashboard_pages.advanced_analytics', 'Advanced Analytics'),
    'date_range'      => sc_t('dashboard_pages.date_range', 'Date Range'),
    'today'           => sc_t('general.today', 'Today'),
    'last_7_days'     => sc_t('dashboard_pages.last_7_days', 'Last 7 Days'),
    'last_30_days'    => sc_t('dashboard_pages.last_30_days', 'Last 30 Days'),
    'last_90_days'    => sc_t('dashboard_pages.last_90_days', 'Last 90 Days'),
    'all_time'        => sc_t('dashboard_pages.all_time', 'All Time'),
    'custom'          => sc_t('dashboard_pages.custom', 'Custom'),
    'from'            => sc_t('dashboard_pages.from', 'From'),
    'to'              => sc_t('dashboard_pages.to', 'To'),
    'apply'           => sc_t('dashboard_pages.apply', 'Apply'),
    'export_csv'      => sc_t('dashboard_pages.export_csv', 'Export CSV'),
    'all_events'      => sc_t('dashboard_pages.all_events', 'All Events'),
    'total_revenue'   => sc_t('dashboard_pages.total_revenue', 'Total Revenue'),
    'registrations'   => sc_t('dashboard_pages.registrations', 'Registrations'),
    'checkin_rate'    => sc_t('dashboard_pages.checkin_rate', 'Check-in Rate'),
    'avg_order_value' => sc_t('dashboard_pages.avg_order_value', 'Avg Order Value'),
    'total_refunds'   => sc_t('dashboard_pages.total_refunds', 'Total Refunds'),
    'vs_prev_period'  => sc_t('dashboard_pages.vs_prev_period', 'vs previous period'),
    'revenue_over_time' => sc_t('dashboard_pages.revenue_over_time', 'Revenue Over Time'),
    'revenue_by_method' => sc_t('dashboard_pages.revenue_by_method', 'Revenue by Payment Method'),
    'revenue_by_event'  => sc_t('dashboard_pages.revenue_by_event', 'Revenue by Event'),
    'registration_trends' => sc_t('dashboard_pages.registration_trends', 'Registration Trends'),
    'peak_hours'      => sc_t('dashboard_pages.peak_hours', 'Peak Registration Hours'),
    'peak_days'       => sc_t('dashboard_pages.peak_days', 'Peak Registration Days'),
    'checkin_by_event' => sc_t('dashboard_pages.checkin_by_event', 'Check-in Rate by Event'),
    'checkin_time_dist' => sc_t('dashboard_pages.checkin_time_dist', 'Check-in Time Distribution'),
    'session_attendance' => sc_t('dashboard_pages.session_attendance', 'Session Attendance'),
    'tickets_by_type' => sc_t('dashboard_pages.tickets_by_type', 'Sales by Ticket Type'),
    'coupon_effectiveness' => sc_t('dashboard_pages.coupon_effectiveness', 'Coupon Effectiveness'),
    'event_comparison' => sc_t('dashboard_pages.event_comparison', 'Event Comparison'),
    'select_events_compare' => sc_t('dashboard_pages.select_events_compare', 'Select 2-5 events to compare'),
    'compare'         => sc_t('dashboard_pages.compare', 'Compare'),
    'loading'         => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_data'         => sc_t('dashboard_pages.no_data_available', 'No data available for this period'),
    'coupon_code'     => sc_t('dashboard_pages.coupon_code', 'Coupon Code'),
    'uses'            => sc_t('dashboard_pages.uses', 'Uses'),
    'discount'        => sc_t('dashboard_pages.discount', 'Discount'),
    'revenue'         => sc_t('dashboard_pages.revenue', 'Revenue'),
    'ticket_type'     => sc_t('dashboard_pages.ticket_type', 'Ticket Type'),
    'sold'            => sc_t('dashboard_pages.sold', 'Sold'),
    'avg_price'       => sc_t('dashboard_pages.avg_price', 'Avg Price'),
);

// Get all events for dropdowns
global $wpdb;
$all_events = $wpdb->get_results(
    "SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY title ASC"
);

$currency_symbol = function_exists('sc_get_currency_symbol') ? sc_get_currency_symbol() : 'SAR ';
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html($t['advanced_analytics']); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html($t['analytics']); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="btn-group" id="export-dropdown">
                            <button type="button" class="btn btn-outline-success dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-download"></i> <?php echo esc_html($t['export_csv']); ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item export-csv" data-section="overview" href="#"><i class="fa fa-file-text-o"></i> Overview KPIs</a>
                                <a class="dropdown-item export-csv" data-section="revenue" href="#"><i class="fa fa-money"></i> Revenue Data</a>
                                <a class="dropdown-item export-csv" data-section="registrations" href="#"><i class="fa fa-users"></i> Registration Data</a>
                                <a class="dropdown-item export-csv" data-section="attendance" href="#"><i class="fa fa-check-circle"></i> Attendance Data</a>
                                <a class="dropdown-item export-csv" data-section="tickets" href="#"><i class="fa fa-ticket"></i> Ticket Data</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-4" id="analytics-filters">
            <div class="body">
                <div class="row align-items-end">
                    <div class="col-lg-5 col-md-6 mb-2">
                        <label class="font-weight-bold mb-1"><?php echo esc_html($t['date_range']); ?></label>
                        <div class="btn-group btn-group-sm d-flex" id="date-range-buttons">
                            <button type="button" class="btn btn-primary active" data-range="all"><?php echo esc_html($t['all_time']); ?></button>
                            <button type="button" class="btn btn-outline-primary" data-range="today"><?php echo esc_html($t['today']); ?></button>
                            <button type="button" class="btn btn-outline-primary" data-range="7days"><?php echo esc_html($t['last_7_days']); ?></button>
                            <button type="button" class="btn btn-outline-primary" data-range="30days"><?php echo esc_html($t['last_30_days']); ?></button>
                            <button type="button" class="btn btn-outline-primary" data-range="90days"><?php echo esc_html($t['last_90_days']); ?></button>
                            <button type="button" class="btn btn-outline-primary" data-range="custom"><?php echo esc_html($t['custom']); ?></button>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-2" id="custom-dates" style="display:none;">
                        <div class="row">
                            <div class="col-5">
                                <input type="date" class="form-control form-control-sm" id="date-from">
                            </div>
                            <div class="col-5">
                                <input type="date" class="form-control form-control-sm" id="date-to">
                            </div>
                            <div class="col-2">
                                <button class="btn btn-sm btn-primary" id="apply-custom-dates"><?php echo esc_html($t['apply']); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-2">
                        <label class="font-weight-bold mb-1"><?php echo esc_html(sc_t('dashboard_pages.filter_by_event', 'Filter by Event')); ?></label>
                        <select class="form-control form-control-sm" id="analytics-event-filter">
                            <option value=""><?php echo esc_html($t['all_events']); ?></option>
                            <?php foreach ($all_events as $event): ?>
                                <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row clearfix" id="kpi-cards">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-uppercase text-muted small"><?php echo esc_html($t['total_revenue']); ?></span>
                                <h3 class="mb-0 mt-1" id="kpi-revenue"><i class="fa fa-spinner fa-spin"></i></h3>
                                <small id="kpi-revenue-delta" class="text-muted"><?php echo esc_html($t['loading']); ?></small>
                            </div>
                            <div class="icon-in-bg bg-success text-white rounded"><i class="fa fa-money fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-uppercase text-muted small"><?php echo esc_html($t['registrations']); ?></span>
                                <h3 class="mb-0 mt-1" id="kpi-registrations"><i class="fa fa-spinner fa-spin"></i></h3>
                                <small id="kpi-registrations-delta" class="text-muted"><?php echo esc_html($t['loading']); ?></small>
                            </div>
                            <div class="icon-in-bg bg-primary text-white rounded"><i class="fa fa-users fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-uppercase text-muted small"><?php echo esc_html($t['checkin_rate']); ?></span>
                                <h3 class="mb-0 mt-1" id="kpi-checkin"><i class="fa fa-spinner fa-spin"></i></h3>
                                <small id="kpi-checkin-delta" class="text-muted"><?php echo esc_html($t['loading']); ?></small>
                            </div>
                            <div class="icon-in-bg bg-info text-white rounded"><i class="fa fa-check-circle fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card number-chart">
                    <div class="body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-uppercase text-muted small"><?php echo esc_html($t['avg_order_value']); ?></span>
                                <h3 class="mb-0 mt-1" id="kpi-aov"><i class="fa fa-spinner fa-spin"></i></h3>
                                <small id="kpi-aov-delta" class="text-muted"><?php echo esc_html($t['loading']); ?></small>
                            </div>
                            <div class="icon-in-bg bg-warning text-white rounded"><i class="fa fa-shopping-cart fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Section -->
        <div class="row clearfix">
            <div class="col-lg-8">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-line-chart text-success"></i> <?php echo esc_html($t['revenue_over_time']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-revenue-time" style="height:320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-pie-chart text-info"></i> <?php echo esc_html($t['revenue_by_method']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-revenue-method" style="height:320px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue by Event -->
        <div class="row clearfix">
            <div class="col-lg-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-bar-chart text-primary"></i> <?php echo esc_html($t['revenue_by_event']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-revenue-event" style="height:300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Section -->
        <div class="row clearfix">
            <div class="col-lg-8">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-area-chart text-primary"></i> <?php echo esc_html($t['registration_trends']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-reg-trends" style="height:300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-clock-o text-warning"></i> <?php echo esc_html($t['peak_hours']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-peak-hours" style="height:300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Section -->
        <div class="row clearfix">
            <div class="col-lg-6">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-users text-success"></i> <?php echo esc_html($t['checkin_by_event']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-checkin-event" style="height:300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-clock-o text-info"></i> <?php echo esc_html($t['checkin_time_dist']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-checkin-time" style="height:300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Section -->
        <div class="row clearfix">
            <div class="col-lg-6">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-ticket text-warning"></i> <?php echo esc_html($t['tickets_by_type']); ?></h2>
                    </div>
                    <div class="body">
                        <div id="chart-tickets-type" style="height:300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-tag text-success"></i> <?php echo esc_html($t['coupon_effectiveness']); ?></h2>
                    </div>
                    <div class="body">
                        <div class="table-responsive" id="coupon-table-wrapper">
                            <table class="table table-hover table-sm" id="coupon-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html($t['coupon_code']); ?></th>
                                        <th><?php echo esc_html($t['uses']); ?></th>
                                        <th><?php echo esc_html($t['discount']); ?></th>
                                        <th><?php echo esc_html($t['revenue']); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="4" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Comparison Section -->
        <div class="row clearfix">
            <div class="col-lg-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-exchange text-primary"></i> <?php echo esc_html($t['event_comparison']); ?></h2>
                    </div>
                    <div class="body">
                        <div class="row mb-3">
                            <div class="col-md-9">
                                <select class="form-control" id="comparison-events" multiple>
                                    <?php foreach ($all_events as $event): ?>
                                        <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted"><?php echo esc_html($t['select_events_compare']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-block" id="compare-btn">
                                    <i class="fa fa-exchange"></i> <?php echo esc_html($t['compare']); ?>
                                </button>
                            </div>
                        </div>
                        <div id="comparison-results" style="display:none;">
                            <div id="chart-comparison" style="height:350px;" class="mb-3"></div>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="comparison-table">
                                    <thead id="comparison-thead"></thead>
                                    <tbody id="comparison-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div id="comparison-empty" class="text-center text-muted py-4">
                            <i class="fa fa-exchange fa-3x mb-2" style="opacity:0.3;"></i>
                            <p><?php echo esc_html($t['select_events_compare']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.icon-in-bg {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}
.number-chart .body {
    padding-bottom: 15px;
}
.delta-up { color: #28a745; }
.delta-down { color: #dc3545; }
.delta-neutral { color: #6c757d; }
#date-range-buttons .btn.active {
    font-weight: 600;
}
.apexcharts-canvas {
    margin: 0 auto;
}
#comparison-events {
    min-height: 38px;
}
</style>

<script src="<?php echo get_template_directory_uri(); ?>/assets/admin-dashboard/bundles/apexcharts.bundle.js"></script>

<script>
jQuery(document).ready(function($) {
    'use strict';

    var currency = '<?php echo esc_js($currency_symbol); ?>';
    var charts = {};
    var currentRange = 'all';
    var currentDateFrom = '';
    var currentDateTo = '';
    var currentEventId = '';

    // =================== Helper Functions ===================

    function formatCurrency(val) {
        var n = parseFloat(val) || 0;
        return currency + n.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }

    function formatNumber(val) {
        var n = parseFloat(val) || 0;
        return n.toLocaleString();
    }

    function deltaHtml(delta, suffix) {
        suffix = suffix || '%';
        if (delta > 0) return '<span class="delta-up"><i class="fa fa-arrow-up"></i> +' + delta + suffix + '</span>';
        if (delta < 0) return '<span class="delta-down"><i class="fa fa-arrow-down"></i> ' + delta + suffix + '</span>';
        return '<span class="delta-neutral">0' + suffix + '</span>';
    }

    function getFilterParams() {
        var params = {
            nonce: scDashboard.nonce,
            date_range: currentRange,
            event_id: currentEventId
        };
        if (currentRange === 'custom') {
            params.date_from = currentDateFrom;
            params.date_to = currentDateTo;
        }
        return params;
    }

    function showChartLoading(el) {
        $(el).html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
    }

    function showChartEmpty(el) {
        $(el).html('<div class="text-center text-muted py-5"><i class="fa fa-bar-chart fa-2x mb-2" style="opacity:0.3;"></i><p><?php echo esc_js($t['no_data']); ?></p></div>');
    }

    function destroyChart(name) {
        if (charts[name]) {
            try { charts[name].destroy(); } catch(e) {}
            charts[name] = null;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'};
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // =================== Filter Events ===================

    $('#date-range-buttons .btn').on('click', function() {
        $('#date-range-buttons .btn').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        currentRange = $(this).data('range');

        if (currentRange === 'custom') {
            $('#custom-dates').show();
        } else {
            $('#custom-dates').hide();
            loadAllSections();
        }
    });

    $('#apply-custom-dates').on('click', function() {
        currentDateFrom = $('#date-from').val();
        currentDateTo = $('#date-to').val();
        if (currentDateFrom && currentDateTo) {
            loadAllSections();
        }
    });

    $('#analytics-event-filter').on('change', function() {
        currentEventId = $(this).val();
        loadAllSections();
    });

    // =================== CSV Export ===================

    $(document).on('click', '.export-csv', function(e) {
        e.preventDefault();
        var section = $(this).data('section');
        var params = getFilterParams();
        params.action = 'sc_analytics_export';
        params.section = section;

        var url = scDashboard.ajaxurl + '?' + $.param(params);
        window.open(url, '_blank');
    });

    // =================== Load All Sections ===================

    function loadAllSections() {
        loadOverview();
        loadRevenue();
        loadRegistrations();
        loadAttendance();
        loadTickets();
    }

    // =================== 1. Overview KPIs ===================

    function loadOverview() {
        var params = getFilterParams();
        params.action = 'sc_analytics_overview';

        $('#kpi-revenue, #kpi-registrations, #kpi-checkin, #kpi-aov').html('<i class="fa fa-spinner fa-spin"></i>');

        $.post(scDashboard.ajaxurl, params, function(res) {
            if (!res.success) return;
            var d = res.data;

            $('#kpi-revenue').text(formatCurrency(d.total_revenue.value));
            $('#kpi-revenue-delta').html(deltaHtml(d.total_revenue.delta_pct) + ' <span class="text-muted"><?php echo esc_js($t['vs_prev_period']); ?></span>');

            $('#kpi-registrations').text(formatNumber(d.total_registrations.value));
            $('#kpi-registrations-delta').html(deltaHtml(d.total_registrations.delta_pct) + ' <span class="text-muted"><?php echo esc_js($t['vs_prev_period']); ?></span>');

            $('#kpi-checkin').text(d.checkin_rate.value + '%');
            $('#kpi-checkin-delta').html(deltaHtml(d.checkin_rate.delta_pct) + ' <span class="text-muted"><?php echo esc_js($t['vs_prev_period']); ?></span>');

            $('#kpi-aov').text(formatCurrency(d.avg_order_value.value));
            $('#kpi-aov-delta').html(deltaHtml(d.avg_order_value.delta_pct) + ' <span class="text-muted"><?php echo esc_js($t['vs_prev_period']); ?></span>');
        });
    }

    // =================== 2. Revenue Charts ===================

    function loadRevenue() {
        var params = getFilterParams();
        params.action = 'sc_analytics_revenue';

        showChartLoading('#chart-revenue-time');
        showChartLoading('#chart-revenue-method');
        showChartLoading('#chart-revenue-event');

        $.post(scDashboard.ajaxurl, params, function(res) {
            if (!res.success) return;
            var d = res.data;

            // Revenue over time (area chart)
            destroyChart('revenueTime');
            var labels = d.over_time.map(function(r) { return r.date; });
            var values = d.over_time.map(function(r) { return r.revenue; });

            if (labels.length > 0) {
                $('#chart-revenue-time').empty();
                charts.revenueTime = new ApexCharts(document.querySelector('#chart-revenue-time'), {
                    series: [{ name: '<?php echo esc_js($t['revenue']); ?>', data: values }],
                    chart: { type: 'area', height: 320, toolbar: { show: false } },
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: labels, labels: { show: labels.length <= 31, rotate: -45, style: { fontSize: '10px' } } },
                    yaxis: { min: 0, labels: { formatter: function(v) { var n = parseFloat(v) || 0; return currency + n.toFixed(0); } } },
                    colors: ['#28a745'],
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                    tooltip: { y: { formatter: function(v) { return formatCurrency(v); } } }
                });
                charts.revenueTime.render();
            } else {
                showChartEmpty('#chart-revenue-time');
            }

            // Revenue by payment method (donut)
            destroyChart('revenueMethod');
            if (d.by_payment_method.length > 0) {
                var mLabels = d.by_payment_method.map(function(r) { return r.method; });
                var mValues = d.by_payment_method.map(function(r) { return r.total; });
                $('#chart-revenue-method').empty();
                charts.revenueMethod = new ApexCharts(document.querySelector('#chart-revenue-method'), {
                    series: mValues,
                    chart: { type: 'donut', height: 320 },
                    labels: mLabels,
                    colors: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1'],
                    legend: { position: 'bottom' },
                    tooltip: { y: { formatter: function(v) { return formatCurrency(v); } } }
                });
                charts.revenueMethod.render();
            } else {
                showChartEmpty('#chart-revenue-method');
            }

            // Revenue by event (bar)
            destroyChart('revenueEvent');
            if (d.by_event.length > 0) {
                var eLabels = d.by_event.map(function(r) { return r.title; });
                var eValues = d.by_event.map(function(r) { return r.revenue; });
                $('#chart-revenue-event').empty();
                var maxRevenue = Math.max.apply(null, eValues) || 1;
                charts.revenueEvent = new ApexCharts(document.querySelector('#chart-revenue-event'), {
                    series: [{ name: '<?php echo esc_js($t['revenue']); ?>', data: eValues }],
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true, barHeight: '60%' } },
                    xaxis: { categories: eLabels },
                    yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function(v) { var n = parseFloat(v) || 0; return currency + n.toFixed(0); } } },
                    colors: ['#007bff'],
                    tooltip: { y: { formatter: function(v) { return formatCurrency(v); } } }
                });
                charts.revenueEvent.render();
            } else {
                showChartEmpty('#chart-revenue-event');
            }
        });
    }

    // =================== 3. Registration Charts ===================

    function loadRegistrations() {
        var params = getFilterParams();
        params.action = 'sc_analytics_registrations';

        showChartLoading('#chart-reg-trends');
        showChartLoading('#chart-peak-hours');

        $.post(scDashboard.ajaxurl, params, function(res) {
            if (!res.success) return;
            var d = res.data;

            // Registration trends (line)
            destroyChart('regTrends');
            var labels = d.over_time.map(function(r) { return r.date; });
            var values = d.over_time.map(function(r) { return r.count; });

            if (values.some(function(v) { return v > 0; })) {
                $('#chart-reg-trends').empty();
                charts.regTrends = new ApexCharts(document.querySelector('#chart-reg-trends'), {
                    series: [{ name: '<?php echo esc_js($t['registrations']); ?>', data: values }],
                    chart: { type: 'line', height: 300, toolbar: { show: false } },
                    stroke: { curve: 'smooth', width: 3 },
                    xaxis: { categories: labels, labels: { show: labels.length <= 31, rotate: -45, style: { fontSize: '10px' } } },
                    yaxis: { min: 0, labels: { formatter: function(v) { return Math.round(v); } } },
                    colors: ['#007bff'],
                    markers: { size: labels.length <= 14 ? 4 : 0 }
                });
                charts.regTrends.render();
            } else {
                showChartEmpty('#chart-reg-trends');
            }

            // Peak hours (bar)
            destroyChart('peakHours');
            var hLabels = d.by_hour.map(function(r) { return (r.hour < 10 ? '0' : '') + r.hour + ':00'; });
            var hValues = d.by_hour.map(function(r) { return r.count; });

            if (hValues.some(function(v) { return v > 0; })) {
                $('#chart-peak-hours').empty();
                charts.peakHours = new ApexCharts(document.querySelector('#chart-peak-hours'), {
                    series: [{ name: '<?php echo esc_js($t['registrations']); ?>', data: hValues }],
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    plotOptions: { bar: { columnWidth: '70%' } },
                    xaxis: { categories: hLabels, labels: { rotate: -45, style: { fontSize: '9px' } } },
                    yaxis: { min: 0, labels: { formatter: function(v) { return Math.round(v); } } },
                    colors: ['#ffc107']
                });
                charts.peakHours.render();
            } else {
                showChartEmpty('#chart-peak-hours');
            }
        });
    }

    // =================== 4. Attendance Charts ===================

    function loadAttendance() {
        var params = getFilterParams();
        params.action = 'sc_analytics_attendance';

        showChartLoading('#chart-checkin-event');
        showChartLoading('#chart-checkin-time');

        $.post(scDashboard.ajaxurl, params, function(res) {
            if (!res.success) return;
            var d = res.data;

            // Check-in rate by event (horizontal bar)
            destroyChart('checkinEvent');
            if (d.by_event.length > 0) {
                var labels = d.by_event.map(function(r) { return r.title; });
                var rates = d.by_event.map(function(r) { return r.rate; });

                $('#chart-checkin-event').empty();
                charts.checkinEvent = new ApexCharts(document.querySelector('#chart-checkin-event'), {
                    series: [{ name: '<?php echo esc_js($t['checkin_rate']); ?>', data: rates }],
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true, barHeight: '60%' } },
                    xaxis: { categories: labels, max: 100, labels: { formatter: function(v) { return v + '%'; } } },
                    colors: ['#28a745'],
                    tooltip: { y: { formatter: function(v) { return v + '%'; } } }
                });
                charts.checkinEvent.render();
            } else {
                showChartEmpty('#chart-checkin-event');
            }

            // Check-in time distribution (column)
            destroyChart('checkinTime');
            var tLabels = d.time_distribution.map(function(r) { return (r.hour < 10 ? '0' : '') + r.hour + ':00'; });
            var tValues = d.time_distribution.map(function(r) { return r.count; });

            if (tValues.some(function(v) { return v > 0; })) {
                $('#chart-checkin-time').empty();
                charts.checkinTime = new ApexCharts(document.querySelector('#chart-checkin-time'), {
                    series: [{ name: 'Check-ins', data: tValues }],
                    chart: { type: 'bar', height: 300, toolbar: { show: false } },
                    plotOptions: { bar: { columnWidth: '70%' } },
                    xaxis: { categories: tLabels, labels: { rotate: -45, style: { fontSize: '9px' } } },
                    colors: ['#17a2b8'],
                    yaxis: { labels: { formatter: function(v) { return Math.round(v); } } }
                });
                charts.checkinTime.render();
            } else {
                showChartEmpty('#chart-checkin-time');
            }
        });
    }

    // =================== 5. Tickets Section ===================

    function loadTickets() {
        var params = getFilterParams();
        params.action = 'sc_analytics_tickets';

        showChartLoading('#chart-tickets-type');
        $('#coupon-table tbody').html('<tr><td colspan="4" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i></td></tr>');

        $.post(scDashboard.ajaxurl, params, function(res) {
            if (!res.success) return;
            var d = res.data;

            // Tickets by type (pie)
            destroyChart('ticketsType');
            if (d.by_type.length > 0) {
                var labels = d.by_type.map(function(r) { return r.ticket_name; });
                var values = d.by_type.map(function(r) { return r.sold; });

                $('#chart-tickets-type').empty();
                charts.ticketsType = new ApexCharts(document.querySelector('#chart-tickets-type'), {
                    series: values,
                    chart: { type: 'pie', height: 300 },
                    labels: labels,
                    colors: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'],
                    legend: { position: 'bottom' }
                });
                charts.ticketsType.render();
            } else {
                showChartEmpty('#chart-tickets-type');
            }

            // Coupon effectiveness table
            var tbody = $('#coupon-table tbody');
            tbody.empty();
            if (d.coupon_effectiveness.length > 0) {
                d.coupon_effectiveness.forEach(function(c) {
                    var codeText = (c.code && c.code.trim()) ? c.code.trim() : '—';
                    tbody.append(
                        '<tr>' +
                        '<td><code>' + escapeHtml(codeText) + '</code></td>' +
                        '<td>' + c.uses + '</td>' +
                        '<td>' + formatCurrency(c.total_discount) + '</td>' +
                        '<td>' + formatCurrency(c.revenue_with_coupon) + '</td>' +
                        '</tr>'
                    );
                });
            } else {
                tbody.html('<tr><td colspan="4" class="text-center text-muted py-3"><?php echo esc_js($t['no_data']); ?></td></tr>');
            }
        });
    }

    // =================== 6. Event Comparison ===================

    $('#compare-btn').on('click', function() {
        var selected = $('#comparison-events').val();
        if (!selected || selected.length < 2) {
            toastr.warning('<?php echo esc_js($t['select_events_compare']); ?>');
            return;
        }
        if (selected.length > 5) {
            selected = selected.slice(0, 5);
        }

        var params = getFilterParams();
        params.action = 'sc_analytics_comparison';
        params.event_ids = selected.join(',');

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.post(scDashboard.ajaxurl, params, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-exchange"></i> <?php echo esc_js($t['compare']); ?>');

            if (!res.success || !res.data.length) {
                toastr.error(res.data && res.data.message ? res.data.message : '<?php echo esc_js($t['no_data']); ?>');
                return;
            }

            var data = res.data;
            $('#comparison-empty').hide();
            $('#comparison-results').show();

            // Chart
            destroyChart('comparison');
            var titles = data.map(function(e) { return e.title; });
            var revenues = data.map(function(e) { return e.revenue; });
            var regs = data.map(function(e) { return e.registrations; });
            var rates = data.map(function(e) { return e.checkin_rate; });

            $('#chart-comparison').empty();
            charts.comparison = new ApexCharts(document.querySelector('#chart-comparison'), {
                series: [
                    { name: '<?php echo esc_js($t['revenue']); ?>', data: revenues },
                    { name: '<?php echo esc_js($t['registrations']); ?>', data: regs }
                ],
                chart: { type: 'bar', height: 350, toolbar: { show: false } },
                plotOptions: { bar: { columnWidth: '55%', grouped: true } },
                xaxis: { categories: titles },
                yaxis: [
                    { title: { text: '<?php echo esc_js($t['revenue']); ?>' }, labels: { formatter: function(v) { var n = parseFloat(v) || 0; return currency + n.toFixed(0); } } },
                    { opposite: true, title: { text: '<?php echo esc_js($t['registrations']); ?>' }, labels: { formatter: function(v) { return Math.round(parseFloat(v) || 0); } } }
                ],
                colors: ['#28a745', '#007bff'],
                legend: { position: 'top' }
            });
            charts.comparison.render();

            // Table
            var thead = '<tr><th><?php echo esc_js(sc_t('dashboard_pages.metric', 'Metric')); ?></th>';
            data.forEach(function(e) { thead += '<th>' + escapeHtml(e.title) + '</th>'; });
            thead += '</tr>';
            $('#comparison-thead').html(thead);

            var metrics = [
                { label: '<?php echo esc_js($t['revenue']); ?>', key: 'revenue', fmt: formatCurrency },
                { label: '<?php echo esc_js($t['registrations']); ?>', key: 'registrations', fmt: formatNumber },
                { label: '<?php echo esc_js($t['checkin_rate']); ?>', key: 'checkin_rate', fmt: function(v) { return v + '%'; } },
                { label: '<?php echo esc_js($t['avg_price']); ?>', key: 'avg_ticket_price', fmt: formatCurrency },
                { label: '<?php echo esc_js(sc_t('dashboard_pages.top_ticket', 'Top Ticket')); ?>', key: 'top_ticket', fmt: escapeHtml }
            ];

            var tbody = '';
            metrics.forEach(function(m) {
                tbody += '<tr><td class="font-weight-bold">' + m.label + '</td>';
                data.forEach(function(e) {
                    tbody += '<td>' + m.fmt(e[m.key]) + '</td>';
                });
                tbody += '</tr>';
            });
            $('#comparison-tbody').html(tbody);
        });
    });

    // =================== Initial Load ===================

    if (typeof ApexCharts !== 'undefined') {
        loadAllSections();
    } else {
        // Fallback: show message
        $('#chart-revenue-time, #chart-revenue-method, #chart-revenue-event, #chart-reg-trends, #chart-peak-hours, #chart-checkin-event, #chart-checkin-time, #chart-tickets-type')
            .html('<div class="alert alert-warning text-center">Charts library not loaded. Please refresh the page.</div>');
        // Still load KPIs and tables
        loadOverview();
        loadTickets();
    }
});
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
