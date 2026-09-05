<?php
/**
 * Dashboard Events Report Page
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

$page_title   = sc_t('dashboard_pages.events_report', 'Events Report');
global $load_charts;
$load_charts  = true; // علشان الهيدر يشحن Chart.js
get_template_part( 'template-parts/dashboard/components/dashboard', 'header' );

// Translations
$t = array(
    'events_report' => sc_t('dashboard_pages.events_report', 'Events Report'),
    'reports' => sc_t('nav.reports', 'Reports'),
    'event_filter' => sc_t('dashboard_pages.event_filter', 'Event Filter'),
    'select_event' => sc_t('dashboard_pages.select_event_report', 'Select Event to View Report'),
    'all_events_overview' => sc_t('dashboard_pages.all_events_overview', 'All Events Overview'),
    'generate_report' => sc_t('dashboard_pages.generate_report', 'Generate Report'),
    'export_pdf' => sc_t('dashboard_pages.export_pdf', 'Export PDF'),
    'export_excel' => sc_t('dashboard_pages.export_excel', 'Export Excel'),
    // Single event
    'event_overview' => sc_t('reports.event_overview', 'Event Overview'),
    'event_date' => sc_t('reports.event_date', 'Event Date'),
    'location' => sc_t('reports.location', 'Location'),
    'event_status' => sc_t('reports.event_status', 'Event Status'),
    'published' => sc_t('reports.published', 'Published'),
    'draft' => sc_t('reports.draft', 'Draft'),
    'total_capacity' => sc_t('reports.total_capacity', 'Total Capacity'),
    'unlimited' => sc_t('reports.unlimited', 'Unlimited'),
    'not_set' => sc_t('reports.not_set', 'Not set'),
    'attendance_statistics' => sc_t('reports.attendance_statistics', 'Attendance Statistics'),
    'tickets_sold' => sc_t('reports.tickets_sold', 'Tickets Sold'),
    'attendance_rate' => sc_t('reports.attendance_rate', 'Attendance Rate'),
    'available_spots' => sc_t('reports.available_spots', 'Available Spots'),
    'capacity_fill' => sc_t('reports.capacity_fill', 'Capacity Fill'),
    'quick_stats' => sc_t('reports.quick_stats', 'Quick Stats'),
    'total_registrations' => sc_t('reports.total_registrations', 'Total Registrations'),
    'confirmed_tickets' => sc_t('reports.confirmed_tickets', 'Confirmed Tickets'),
    'pending_other' => sc_t('reports.pending_other', 'Pending / Other'),
    'cancelled' => sc_t('reports.cancelled', 'Cancelled'),
    'revenue' => sc_t('reports.revenue', 'Revenue'),
    'revenue_note' => sc_t('reports.revenue_note', 'Based on attendee ticket price × quantity'),
    'event_not_found' => sc_t('reports.event_not_found', 'Event not found.'),
    // Overview
    'all_events_summary' => sc_t('reports.all_events_summary', 'All Events Summary'),
    'event_name' => sc_t('reports.event_name', 'Event Name'),
    'capacity' => sc_t('reports.capacity', 'Capacity'),
    'registered' => sc_t('reports.registered', 'Registered'),
    'date' => sc_t('reports.date', 'Date'),
    'no_events_found' => sc_t('reports.no_events_found', 'No events found.'),
    'events_comparison' => sc_t('reports.events_comparison', 'Events Comparison'),
    'tickets_sold_revenue' => sc_t('reports.tickets_sold_revenue', 'Tickets Sold and Revenue per Event'),
    // Email modal
    'email_all_attendees' => sc_t('reports.email_all_attendees', 'Email All Attendees'),
    'email_subject' => sc_t('reports.email_subject', 'Email Subject'),
    'email_subject_placeholder' => sc_t('reports.email_subject_placeholder', 'e.g., Important Update About Your Event'),
    'email_message' => sc_t('reports.email_message', 'Email Message'),
    'email_message_placeholder' => sc_t('reports.email_message_placeholder', 'Enter your message here...'),
    'available_variables' => sc_t('reports.available_variables', 'Available variables'),
    'preview' => sc_t('reports.preview', 'Preview'),
    'email_preview_note' => sc_t('reports.email_preview_note', 'Email will be sent to'),
    'attendees_label' => sc_t('reports.attendees_label', 'attendees'),
    'cancel' => sc_t('reports.cancel', 'Cancel'),
    'send_emails' => sc_t('reports.send_emails', 'Send Emails'),
);

// Load enhanced reports functions
require_once get_template_directory() . '/inc/admin-dashboard/reports-functions.php';

// Global $wpdb
global $wpdb;

/**
 * Helper: Wrapper for new enhanced function (backwards compatibility)
 */
if ( ! function_exists( 'sc_events_collect_event_stats' ) ) {
    function sc_events_collect_event_stats( $event_id ) {
        return sc_get_event_statistics( $event_id );
    }
}

// Get all events from Custom Tables
$all_events = array();
if (class_exists('SC_Event')) {
    $all_events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'),
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}

// Get selected event from query string
$selected_event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;

// متغيرات هنبعتها للـ JS
$js_single_chart_data   = array();
$js_overview_chart_data = array();

?>
<?php get_template_part( 'template-parts/dashboard/components/dashboard', 'sidebar' ); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['events_report']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo esc_url( home_url( '/event-manager-dashboard/home' ) ); ?>">
                                <i class="fa fa-dashboard"></i>
                            </a>
                        </li>
                        <li class="breadcrumb-item active"><?php echo $t['reports']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Filter -->
        <div class="card mb-4">
            <div class="header">
                <h2><?php echo $t['event_filter']; ?></h2>
            </div>
            <div class="body">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label for="event-filter"><?php echo $t['select_event']; ?></label>
                        <select class="form-control" id="event-filter">
                            <option value=""><?php echo $t['all_events_overview']; ?></option>
                            <?php foreach ( $all_events as $event ) : ?>
                                <option value="<?php echo esc_attr( $event->id ); ?>" <?php selected( $selected_event_id, $event->id ); ?>>
                                    <?php echo esc_html( $event->title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-primary" id="generate-report">
                            <i class="fa fa-refresh"></i> <?php echo $t['generate_report']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ( $selected_event_id ) : ?>

            <?php
            // ---------- SINGLE EVENT MODE ----------
            // Get event from Custom Tables
            $event = null;
            if (class_exists('SC_Event')) {
                $event = SC_Event::get($selected_event_id);
            }

            if (!$event) {
                echo '<div class="alert alert-danger">' . esc_html($t['event_not_found']) . '</div>';
                return;
            }

            $event_date       = $event->start_date;
            $event_end_date   = $event->end_date;
            $event_location   = $event->location;

            // Get capacity from tickets
            $event_capacity = 0;
            if (class_exists('SC_Ticket')) {
                $tickets = SC_Ticket::get_by_event($selected_event_id);
                foreach ($tickets as $ticket) {
                    $event_capacity += (int) $ticket->quantity;
                }
            }

            // إحصائيات من Custom Tables
            $stats                   = sc_events_collect_event_stats( $selected_event_id );
            $tickets_sold            = (int) $stats['tickets_sold'];
            $total_revenue           = (float) $stats['revenue'];
            $attendance_percentage   = $event_capacity > 0 ? round( ( $tickets_sold / $event_capacity ) * 100, 2 ) : 0;
            $confirmed_count         = (int) $stats['order_status_counts']['confirmed'];
            $pending_count           = (int) $stats['order_status_counts']['pending'];
            $attendees_rows          = $stats['attendees'];
            $daily_revenue           = $stats['daily_revenue'];
            $ticket_breakdown        = $stats['ticket_breakdown'];

            // تجهيز بيانات الـ charts
            ksort( $daily_revenue );
            $revenue_chart_labels = array_keys( $daily_revenue );
            $revenue_chart_values = array_values( $daily_revenue );

            $ticket_labels = array_keys( $ticket_breakdown );
            $ticket_values = array_values( $ticket_breakdown );

            $js_single_chart_data = array(
                'eventId'        => $selected_event_id,
                'eventName'      => $event->title,
                'capacity'       => $event_capacity,
                'ticketsSold'    => $tickets_sold,
                'revenue'        => $total_revenue,
                'revenueLabels'  => $revenue_chart_labels,
                'revenueData'    => $revenue_chart_values,
                'ticketLabels'   => $ticket_labels,
                'ticketData'     => $ticket_values,
                'attendees'      => $attendees_rows,
                'confirmedCount' => $confirmed_count,
                'pendingCount'   => $pending_count,
            );
            ?>

            <!-- Single Event Report -->
            <div class="row clearfix">
                <div class="col-lg-8">
                    <!-- Event Overview -->
                    <div class="card">
                        <div class="header">
                            <h2><?php echo $t['event_overview']; ?></h2>
                        </div>
                        <div class="body">
                            <h3><?php echo esc_html( $event->title ); ?></h3>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <p><strong><?php echo $t['event_date']; ?>:</strong></p>
                                    <p class="text-muted">
                                        <?php echo $event_date ? esc_html( date( 'F j, Y', strtotime( $event_date ) ) ) : $t['not_set']; ?>
                                        <?php if ( $event_end_date ) : ?>
                                            - <?php echo esc_html( date( 'F j, Y', strtotime( $event_end_date ) ) ); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><?php echo $t['location']; ?>:</strong></p>
                                    <p class="text-muted"><?php echo $event_location ? esc_html( $event_location ) : $t['not_set']; ?></p>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p><strong><?php echo $t['event_status']; ?>:</strong></p>
                                    <p>
                                        <?php
                                        $status_badge = '';
                                        switch ( $event->status ) {
                                            case 'publish':
                                                $status_badge = '<span class="badge badge-success">' . esc_html( $t['published'] ) . '</span>';
                                                break;
                                            case 'draft':
                                                $status_badge = '<span class="badge badge-secondary">' . esc_html( $t['draft'] ) . '</span>';
                                                break;
                                            default:
                                                $status_badge = '<span class="badge badge-warning">' . esc_html( ucfirst( $event->status ) ) . '</span>';
                                        }
                                        echo wp_kses_post( $status_badge );
                                        ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><?php echo $t['total_capacity']; ?>:</strong></p>
                                    <p class="text-muted"><?php echo $event_capacity ? esc_html( $event_capacity ) : $t['unlimited']; ?></p>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mt-4"><?php echo $t['attendance_statistics']; ?></h5>
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h3 class="text-primary"><?php echo esc_html( $tickets_sold ); ?></h3>
                                        <p class="text-muted mb-0"><?php echo $t['tickets_sold']; ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h3 class="text-success"><?php echo esc_html( $attendance_percentage ); ?>%</h3>
                                        <p class="text-muted mb-0"><?php echo $t['attendance_rate']; ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-3 bg-light rounded">
                                        <h3 class="text-info">
                                            <?php
                                            if ( $event_capacity > 0 ) {
                                                echo esc_html( max( $event_capacity - $tickets_sold, 0 ) );
                                            } else {
                                                echo '∞';
                                            }
                                            ?>
                                        </h3>
                                        <p class="text-muted mb-0"><?php echo $t['available_spots']; ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mt-4">
                                <label><?php echo $t['capacity_fill']; ?></label>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-primary"
                                        role="progressbar"
                                        style="width: <?php echo esc_attr( $attendance_percentage ); ?>%"
                                        aria-valuenow="<?php echo esc_attr( $attendance_percentage ); ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?php echo esc_html( $attendance_percentage ); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Sidebar Stats -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="header">
                            <h2><?php echo $t['quick_stats']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa fa-users text-primary mr-2"></i>
                                    <strong><?php echo $t['total_registrations']; ?></strong>
                                </div>
                                <h3 class="ml-4"><?php echo esc_html( $tickets_sold ); ?></h3>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa fa-check-circle text-success mr-2"></i>
                                    <strong><?php echo $t['confirmed_tickets']; ?></strong>
                                </div>
                                <h3 class="ml-4"><?php echo esc_html( $confirmed_count ); ?></h3>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa fa-clock-o text-warning mr-2"></i>
                                    <strong><?php echo $t['pending_other']; ?></strong>
                                </div>
                                <h3 class="ml-4"><?php echo esc_html( $pending_count ); ?></h3>
                            </div>

                            <?php if ( isset( $stats['order_status_counts']['cancelled'] ) && $stats['order_status_counts']['cancelled'] > 0 ) : ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa fa-times-circle text-danger mr-2"></i>
                                    <strong><?php echo $t['cancelled']; ?></strong>
                                </div>
                                <h3 class="ml-4"><?php echo esc_html( $stats['order_status_counts']['cancelled'] ); ?></h3>
                            </div>
                            <?php endif; ?>

                            <hr>

                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa fa-money text-info mr-2"></i>
                                    <strong><?php echo $t['revenue']; ?></strong>
                                </div>
                                <h3 class="ml-4">
                                    <?php echo sc_format_price( $total_revenue, false ); ?>
                                </h3>
                                <small class="text-muted"><?php echo $t['revenue_note']; ?></small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        <?php else : ?>

            <?php
            // ---------- ALL EVENTS OVERVIEW / COMPARE MODE ----------
            $overview_labels   = array();
            $overview_tickets  = array();
            $overview_revenue  = array();
            ?>
            <!-- All Events Overview -->
            <div class="row clearfix">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="header">
                            <h2><?php echo $t['all_events_summary']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="table-responsive">
                                <table class="table table-hover table-custom spacing5">
                                    <thead>
                                        <tr>
                                            <th><i class="fa fa-calendar"></i> <?php echo $t['event_name']; ?></th>
                                            <th><i class="fa fa-users"></i> <?php echo $t['capacity']; ?></th>
                                            <th><i class="fa fa-ticket"></i> <?php echo $t['registered']; ?></th>
                                            <th><i class="fa fa-clock-o"></i> <?php echo $t['date']; ?></th>
                                            <th><i class="fa fa-money"></i> <?php echo $t['revenue']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ( $all_events ) : ?>
                                            <?php foreach ( $all_events as $event ) : ?>
                                                <?php
                                                // Get event data from Custom Tables
                                                $event_date   = $event->start_date;

                                                // Get capacity from tickets
                                                $capacity = 0;
                                                if (class_exists('SC_Ticket')) {
                                                    $event_tickets = SC_Ticket::get_by_event($event->id);
                                                    foreach ($event_tickets as $ticket) {
                                                        $capacity += (int) $ticket->quantity;
                                                    }
                                                }

                                                $event_stats  = sc_events_collect_event_stats( $event->id );
                                                $registered   = isset($event_stats['tickets_sold']) ? (int) $event_stats['tickets_sold'] : 0;
                                                $event_rev    = isset($event_stats['revenue']) ? (float) $event_stats['revenue'] : 0;
                                                $rate         = $capacity > 0 ? round( ( $registered / $capacity ) * 100, 1 ) : 0;

                                                $overview_labels[]  = $event->title;
                                                $overview_tickets[] = $registered;
                                                $overview_revenue[] = $event_rev;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo esc_html( $event->title ); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo $capacity ? esc_html( $capacity ) : '<span class="text-muted">' . $t['unlimited'] . '</span>'; ?>
                                                    </td>
                                                    <td>
                                                        <strong style="font-size: 1.1em;"><?php echo number_format( (int) $registered ); ?></strong>
                                                        <?php if ( $capacity > 0 && $registered > 0 ) {
                                                            $fill_color = $rate >= 80 ? 'danger' : ( $rate >= 50 ? 'warning' : 'success' );
                                                            echo '<br><span class="badge badge-' . esc_attr($fill_color) . '" style="font-size:10px;">' . esc_html($rate) . '%</span>';
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $event_date ? esc_html( date( 'M j, Y', strtotime( $event_date ) ) ) : $t['not_set']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo sc_format_price( $event_rev, false ); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-5">
                                                    <i class="fa fa-calendar-times-o" style="font-size: 48px; opacity: 0.3;"></i>
                                                    <p class="mt-2"><?php echo $t['no_events_found']; ?></p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php
                    // بيانات المقارنة للـ JS
                    $js_overview_chart_data = array(
                        'compareLabels'  => $overview_labels,
                        'compareTickets' => $overview_tickets,
                        'compareRevenue' => $overview_revenue,
                    );
                    ?>

                    <!-- Events Comparison Chart -->
                    <div class="card mt-4">
                        <div class="header">
                            <h2><?php echo $t['events_comparison']; ?></h2>
                            <small class="text-muted"><?php echo $t['tickets_sold_revenue']; ?></small>
                        </div>
                        <div class="body">
                            <div id="sc-events-compare-chart" style="height: 20rem"></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- Email Attendees Modal -->
<div class="modal fade" id="emailAttendeesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-envelope"></i> <?php echo $t['email_all_attendees']; ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="email-attendees-form">
                    <div class="form-group">
                        <label for="email-subject">
                            <i class="fa fa-tag"></i> <?php echo $t['email_subject']; ?> *
                        </label>
                        <input type="text" class="form-control" id="email-subject" name="subject"
                               placeholder="<?php echo esc_attr($t['email_subject_placeholder']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email-message">
                            <i class="fa fa-edit"></i> <?php echo $t['email_message']; ?> *
                        </label>
                        <textarea class="form-control" id="email-message" name="message" rows="8"
                                  placeholder="<?php echo esc_attr($t['email_message_placeholder']); ?>" required></textarea>
                        <small class="form-text text-muted">
                            <?php echo $t['available_variables']; ?>: <code>{name}</code>, <code>{event_name}</code>, <code>{ticket_type}</code>, <code>{event_date}</code>
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong><?php echo $t['preview']; ?>:</strong> <?php echo $t['email_preview_note']; ?> <strong id="attendees-count">0</strong> <?php echo $t['attendees_label']; ?>.
                    </div>

                    <input type="hidden" id="email-event-id" name="event_id" value="<?php echo esc_attr( $selected_event_id ); ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
                </button>
                <button type="button" class="btn btn-success" id="send-email-btn">
                    <i class="fa fa-paper-plane"></i> <?php echo $t['send_emails']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Build JS data object
$js_data = array(
    'mode' => $selected_event_id ? 'single' : 'overview',
);

if ( $selected_event_id && ! empty( $js_single_chart_data ) ) {
    $js_data = array_merge( $js_data, $js_single_chart_data );
} elseif ( ! $selected_event_id && ! empty( $js_overview_chart_data ) ) {
    $js_data = array_merge( $js_data, $js_overview_chart_data );
}
?>

<script>
var scEventReportData = <?php echo wp_json_encode( $js_data ); ?>;
</script>

<script>
var scReportLabelTicketsSold = '<?php echo esc_js(sc_t("reports.tickets_sold", "Tickets Sold")); ?>';
var scReportLabelRevenue = '<?php echo esc_js(sc_t("reports.revenue", "Revenue")); ?>';
jQuery(document).ready(function($) {
    // -------- Filter navigation --------
    $('#generate-report').on('click', function() {
        var eventId = $('#event-filter').val();
        var baseUrl = window.location.pathname;
        if (eventId) {
            window.location.href = baseUrl + '?event_id=' + eventId;
        } else {
            window.location.href = baseUrl;
        }
    });

    // -------- SINGLE EVENT MODE CHARTS & CSV --------
    if (scEventReportData.mode === 'single') {
        // Enhanced Export CSV with all details
        $('#sc-export-csv').on('click', function(e) {
            e.preventDefault();

            if (!scEventReportData.attendees || !scEventReportData.attendees.length) {
                showWarning('No attendees data to export.');
                return;
            }

            // Enhanced CSV headers
            var rows = [[
                'Name',
                'Email',
                'Registration Date',
                'Ticket Type',
                'Status',
                'Order Total',
                'Order ID',
                'Event Name'
            ]];

            scEventReportData.attendees.forEach(function(a) {
                rows.push([
                    a.name || '',
                    a.email || '',
                    a.date || '',
                    a.ticket || '',
                    (a.status || '').toUpperCase(),
                    a.total_formatted || a.total || '0',
                    a.order_id || 'N/A',
                    scEventReportData.eventName || ''
                ]);
            });

            var csv = rows.map(function(row) {
                return row.map(function(value) {
                    var v = String(value).replace(/"/g, '""');
                    return '"' + v + '"';
                }).join(',');
            }).join("\r\n");

            var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            var url  = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            var filename = 'attendees-' + (scEventReportData.eventName || 'event').replace(/[^a-z0-9]/gi, '-').toLowerCase() + '-' + Date.now() + '.csv';
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        // Email Attendees Modal
        $('#sc-email-attendees').on('click', function(e) {
            e.preventDefault();

            if (!scEventReportData.attendees || !scEventReportData.attendees.length) {
                showWarning('No attendees to email.');
                return;
            }

            // Set attendees count
            $('#attendees-count').text(scEventReportData.attendees.length);

            // Show modal - with fallback if Bootstrap not loaded
            try {
                if (typeof $.fn.modal !== 'undefined') {
                    $('#emailAttendeesModal').modal('show');
                } else {
                    // Fallback: show modal manually
                    $('#emailAttendeesModal').addClass('show').css('display', 'block');
                    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                }
            } catch (e) {
                console.error('Error showing modal:', e);
                showError('Error opening email form. Please refresh the page.');
            }
        });

        // Send Email Button
        $('#send-email-btn').on('click', function(e) {
            e.preventDefault();

            var subject = $('#email-subject').val().trim();
            var message = $('#email-message').val().trim();
            var eventId = $('#email-event-id').val();

            if (!subject || !message) {
                showWarning('Please fill in both subject and message.');
                return;
            }

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'sc_email_attendees',
                    nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                    event_id: eventId,
                    subject: subject,
                    message: message
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalText);

                    if (response.success) {
                        showSuccess('Emails sent successfully to ' + response.data.sent_count + ' attendees!');

                        // Hide modal with fallback
                        try {
                            if (typeof $.fn.modal !== 'undefined') {
                                $('#emailAttendeesModal').modal('hide');
                            } else {
                                $('#emailAttendeesModal').removeClass('show').css('display', 'none');
                                $('body').removeClass('modal-open');
                                $('.modal-backdrop').remove();
                            }
                        } catch (e) {
                            $('#emailAttendeesModal').hide();
                            $('.modal-backdrop').remove();
                        }

                        $('#email-attendees-form')[0].reset();
                    } else {
                        showError(response.data.message || 'Failed to send emails.');
                    }
                },
                error: function(xhr, status) {
                    $btn.prop('disabled', false).html(originalText);
                    var errorMsg = 'Failed to send emails. ';
                    if (status === 'timeout') {
                        errorMsg += 'The request timed out. Please try again with fewer recipients.';
                    } else if (xhr.status === 0) {
                        errorMsg += 'Could not connect to server. Please check your internet connection.';
                    } else if (xhr.status === 429) {
                        errorMsg += 'Too many requests. Please wait a moment before sending more emails.';
                    } else if (xhr.status === 500) {
                        errorMsg += 'Server error occurred. Please contact support.';
                    } else {
                        errorMsg += 'Please try again or contact support.';
                    }
                    showError(errorMsg);
                }
            });
        });

        // Modal close buttons handler (fallback)
        $('[data-dismiss="modal"]').on('click', function() {
            var modalId = $(this).closest('.modal').attr('id');
            if (modalId) {
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#' + modalId).modal('hide');
                    } else {
                        $('#' + modalId).removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#' + modalId).hide();
                    $('.modal-backdrop').remove();
                }
            }
        });

    } else if (scEventReportData.mode === 'overview') {

        // -------- OVERVIEW MODE: Events Comparison Chart (C3.js) --------

        if (typeof c3 !== 'undefined' && scEventReportData.compareLabels && scEventReportData.compareLabels.length) {
            // Prepare data columns for C3.js
            var ticketsColumn = [scReportLabelTicketsSold].concat(scEventReportData.compareTickets);
            var revenueColumn = [scReportLabelRevenue].concat(scEventReportData.compareRevenue);

            c3.generate({
                bindto: '#sc-events-compare-chart',
                data: {
                    columns: [
                        ticketsColumn,
                        revenueColumn
                    ],
                    type: 'bar',
                    colors: {
                        [scReportLabelTicketsSold]: '#17a2b8',
                        [scReportLabelRevenue]: '#28a745'
                    }
                },
                axis: {
                    x: {
                        type: 'category',
                        categories: scEventReportData.compareLabels
                    },
                    y: {
                        min: 0,
                        padding: { bottom: 0, top: 5 },
                        tick: {
                            // Only show integer labels — hide fractional ticks C3 generates
                            format: function(d) { return d % 1 === 0 ? d : ''; }
                        }
                    }
                },
                bar: {
                    width: {
                        ratio: 0.6
                    }
                },
                legend: {
                    show: true,
                    position: 'bottom'
                },
                padding: {
                    bottom: 10,
                    top: 10
                }
            });
        } else {
            // Debug: Why chart is not showing
            if (typeof c3 === 'undefined') {
                console.error('C3.js library not loaded!');
            }
            if (!scEventReportData.compareLabels) {
                console.error('No compareLabels data found!');
            }
            if (scEventReportData.compareLabels && !scEventReportData.compareLabels.length) {
                console.warn('compareLabels array is empty!');
            }
        }
    }
});
</script>

<!-- Load C3.js for Charts -->
<script>
// Ensure C3.js is loaded
if (typeof c3 === 'undefined') {
    var script = document.createElement('script');
    script.src = '<?php echo get_template_directory_uri(); ?>/assets/admin-dashboard/bundles/c3.bundle.js';
    script.onload = function() {
        // Reinitialize chart after C3 loads
        if (scEventReportData.mode === 'overview' && scEventReportData.compareLabels && scEventReportData.compareLabels.length) {
            var ticketsColumn = [scReportLabelTicketsSold].concat(scEventReportData.compareTickets);
            var revenueColumn = [scReportLabelRevenue].concat(scEventReportData.compareRevenue);

            c3.generate({
                bindto: '#sc-events-compare-chart',
                data: {
                    columns: [ticketsColumn, revenueColumn],
                    type: 'bar',
                    colors: {
                        [scReportLabelTicketsSold]: '#17a2b8',
                        [scReportLabelRevenue]: '#28a745'
                    }
                },
                axis: {
                    x: {
                        type: 'category',
                        categories: scEventReportData.compareLabels
                    },
                    y: {
                        min: 0,
                        padding: { bottom: 0 },
                        tick: (function() {
                            var allVals = (ticketsColumn || []).slice(1).concat((revenueColumn || []).slice(1));
                            var max = Math.max.apply(null, allVals.map(Number).filter(function(v) { return !isNaN(v); })) || 1;
                            max = Math.ceil(max);
                            if (max <= 10) {
                                var vals = [];
                                for (var i = 0; i <= max; i++) vals.push(i);
                                return { values: vals };
                            }
                            return {
                                format: function(d) { return Math.round(d); },
                                count: Math.min(max + 1, 10)
                            };
                        })()
                    }
                },
                bar: {
                    width: { ratio: 0.6 }
                },
                legend: {
                    show: true,
                    position: 'bottom'
                },
                padding: {
                    bottom: 10,
                    top: 10
                }
            });
        }
    };
    document.head.appendChild(script);
}
</script>

<?php
get_template_part( 'template-parts/dashboard/components/dashboard', 'footer' );
?>
