<?php
/**
 * Dashboard Booth Bookings Management Page
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

$page_title = sc_t('booths.bookings', 'Booth Bookings');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'bookings' => sc_t('booths.bookings', 'Bookings'),
    'booths' => sc_t('nav.booths', 'Booths'),
    'add_booking' => sc_t('booths.add_booking', 'Add Booking'),
    'booking_ref' => sc_t('booths.booking_ref', 'Ref'),
    'booth' => sc_t('booths.booth', 'Booth'),
    'company' => sc_t('dashboard_pages.company', 'Company'),
    'period' => sc_t('booths.period', 'Period'),
    'amount' => sc_t('dashboard_pages.amount', 'Amount'),
    'payment' => sc_t('dashboard_pages.payment', 'Payment'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'view' => sc_t('dashboard_pages.view', 'View'),
    'confirm' => sc_t('booths.confirm', 'Confirm'),
    'check_in' => sc_t('dashboard_pages.check_in', 'Check In'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_bookings' => sc_t('booths.no_bookings', 'No bookings found'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'all_statuses' => sc_t('dashboard_pages.all_statuses', 'All Statuses'),
    'all_payments' => sc_t('booths.all_payments', 'All Payments'),
    'search' => sc_t('dashboard_pages.search', 'Search...'),
    'refresh' => sc_t('dashboard_pages.refresh', 'Refresh'),
    'total_bookings' => sc_t('booths.total_bookings', 'Total Bookings'),
    'pending_bookings' => sc_t('booths.pending_bookings', 'Pending'),
    'confirmed_bookings' => sc_t('booths.confirmed_bookings', 'Confirmed'),
    'total_revenue' => sc_t('booths.total_revenue', 'Total Revenue'),
    // Statuses
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'confirmed' => sc_t('booths.confirmed', 'Confirmed'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'completed' => sc_t('dashboard_pages.completed', 'Completed'),
    'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
    // Payment statuses
    'payment_pending' => sc_t('booths.payment_pending', 'Pending'),
    'deposit_paid' => sc_t('booths.deposit_paid', 'Deposit Paid'),
    'fully_paid' => sc_t('booths.fully_paid', 'Fully Paid'),
    'overdue' => sc_t('booths.overdue', 'Overdue'),
);

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get events for dropdown
global $wpdb;
$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY start_date DESC");

// Auto-select if only one event
if (!$event_id && count($events) === 1) {
    $event_id = $events[0]->id;
}

// Get stats
$total_bookings = 0;
$pending_bookings = 0;
$confirmed_bookings = 0;
$total_revenue = 0;

if ($event_id) {
    $total_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booth_bookings WHERE event_id = %d", $event_id)) ?: 0;
    $pending_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booth_bookings WHERE event_id = %d AND status = 'pending'", $event_id)) ?: 0;
    $confirmed_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_booth_bookings WHERE event_id = %d AND status IN ('confirmed', 'active')", $event_id)) ?: 0;
    $total_revenue = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}sc_booth_bookings WHERE event_id = %d AND status != 'cancelled'", $event_id)) ?: 0;
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['bookings']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booths'); ?>"><?php echo $t['booths']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['bookings']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/booth-booking-create' . ($event_id ? '?event_id=' . $event_id : '')); ?>" class="btn btn-primary" <?php echo !$event_id ? 'disabled' : ''; ?>>
                                <i class="fa fa-plus"></i> <?php echo $t['add_booking']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Selector -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="form-group">
                    <label><strong><?php echo $t['select_event']; ?></strong></label>
                    <select id="event-selector" class="form-control">
                        <option value="">-- <?php echo $t['select_event']; ?> --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>" <?php selected($event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row clearfix" id="stats-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-file-text"></i>
                        </div>
                        <h3 class="stat-number" id="stat-total"><?php echo $total_bookings; ?></h3>
                        <p class="stat-label"><?php echo $t['total_bookings']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-clock-o"></i>
                        </div>
                        <h3 class="stat-number" id="stat-pending"><?php echo $pending_bookings; ?></h3>
                        <p class="stat-label"><?php echo $t['pending_bookings']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <h3 class="stat-number" id="stat-confirmed"><?php echo $confirmed_bookings; ?></h3>
                        <p class="stat-label"><?php echo $t['confirmed_bookings']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-money"></i>
                        </div>
                        <h3 class="stat-number" id="stat-revenue"><?php echo number_format($total_revenue); ?></h3>
                        <p class="stat-label"><?php echo $t['total_revenue']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .stat-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.2s; margin-bottom: 20px; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.12); }
        .stat-card .card-body { padding: 20px 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .stat-icon i { font-size: 22px; color: #fff; }
        .stat-number { font-size: 28px; font-weight: 700; margin: 0 0 5px 0; color: #333; }
        .stat-label { font-size: 13px; color: #888; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-primary { background: #3b82f6 !important; }
        .bg-success { background: #22c55e !important; }
        .bg-warning { background: #f59e0b !important; }
        .bg-info { background: #06b6d4 !important; }
        @media (max-width: 575px) {
            .stat-card .card-body { padding: 15px 10px; }
            .stat-icon { width: 40px; height: 40px; }
            .stat-icon i { font-size: 18px; }
            .stat-number { font-size: 20px; }
            .stat-label { font-size: 10px; }
        }
        </style>

        <!-- Bookings Table -->
        <div class="row" id="content-row" style="<?php echo !$event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-calendar-check-o"></i> <?php echo $t['bookings']; ?></h2>
                    </div>
                    <div class="body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="search-bookings" placeholder="<?php echo $t['search']; ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="filter-status">
                                    <option value=""><?php echo $t['all_statuses']; ?></option>
                                    <option value="pending"><?php echo $t['pending']; ?></option>
                                    <option value="confirmed"><?php echo $t['confirmed']; ?></option>
                                    <option value="active"><?php echo $t['active']; ?></option>
                                    <option value="completed"><?php echo $t['completed']; ?></option>
                                    <option value="cancelled"><?php echo $t['cancelled']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="filter-payment">
                                    <option value=""><?php echo $t['all_payments']; ?></option>
                                    <option value="pending"><?php echo $t['payment_pending']; ?></option>
                                    <option value="deposit_paid"><?php echo $t['deposit_paid']; ?></option>
                                    <option value="fully_paid"><?php echo $t['fully_paid']; ?></option>
                                    <option value="overdue"><?php echo $t['overdue']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary btn-block" id="btn-refresh">
                                    <i class="fa fa-refresh"></i> <?php echo $t['refresh']; ?>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="bookings-table">
                                <thead>
                                    <tr>
                                        <th><?php echo $t['booking_ref']; ?></th>
                                        <th><?php echo $t['booth']; ?></th>
                                        <th><?php echo $t['company']; ?></th>
                                        <th><?php echo $t['period']; ?></th>
                                        <th><?php echo $t['amount']; ?></th>
                                        <th><?php echo $t['payment']; ?></th>
                                        <th><?php echo $t['status']; ?></th>
                                        <th style="width: 150px;"><?php echo $t['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody id="bookings-list">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading']; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Event Selected Message -->
        <div class="row" id="no-event-message" style="<?php echo $event_id ? 'display:none' : ''; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="body text-center p-5">
                        <i class="fa fa-calendar fa-4x text-muted mb-3"></i>
                        <h4><?php _e('Please select an event to manage bookings', 'sc_events'); ?></h4>
                        <p class="text-muted"><?php _e('Use the dropdown above to choose an event', 'sc_events'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var bookingsTranslations = <?php echo json_encode($t); ?>;
var currentEventId = <?php echo $event_id ?: 0; ?>;

jQuery(document).ready(function($) {
    // Event selector change
    $('#event-selector').on('change', function() {
        var eventId = $(this).val();
        if (eventId) {
            window.location.href = '<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?>?event_id=' + eventId;
        } else {
            $('#stats-row, #content-row').hide();
            $('#no-event-message').show();
        }
    });

    // Load bookings
    function loadBookings() {
        if (!currentEventId) return;

        var tbody = $('#bookings-list');
        tbody.html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> ' + bookingsTranslations.loading + '</td></tr>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_get_all',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                event_id: currentEventId,
                search: $('#search-bookings').val(),
                status: $('#filter-status').val(),
                payment_status: $('#filter-payment').val()
            },
            success: function(response) {
                if (response.success && response.data.bookings) {
                    var bookings = response.data.bookings;
                    if (bookings.length === 0) {
                        tbody.html('<tr><td colspan="8" class="text-center text-muted">' + bookingsTranslations.no_bookings + '</td></tr>');
                        return;
                    }

                    var html = '';
                    bookings.forEach(function(booking) {
                        var statusBadge = getStatusBadge(booking.status);
                        var paymentBadge = getPaymentBadge(booking.payment_status);

                        html += '<tr>';
                        html += '<td><strong>' + escapeHtml(booking.booking_ref) + '</strong></td>';
                        html += '<td>' + escapeHtml(booking.booth_number || '-') + '</td>';
                        html += '<td>' + escapeHtml(booking.company_name || '-') + '</td>';
                        html += '<td>' + formatDate(booking.start_date) + ' - ' + formatDate(booking.end_date) + '</td>';
                        html += '<td>' + formatCurrency(booking.total_amount) + '</td>';
                        html += '<td>' + paymentBadge + '</td>';
                        html += '<td>' + statusBadge + '</td>';
                        html += '<td>';
                        html += '<div class="btn-group">';
                        html += '<a href="<?php echo home_url('/event-manager-dashboard/booth-booking-edit'); ?>?id=' + booking.id + '" class="btn btn-sm btn-outline-primary" title="' + bookingsTranslations.edit + '"><i class="fa fa-pencil"></i></a>';

                        if (booking.status === 'pending') {
                            html += '<button class="btn btn-sm btn-outline-success btn-confirm" data-id="' + booking.id + '" title="' + bookingsTranslations.confirm + '"><i class="fa fa-check"></i></button>';
                        }
                        if (booking.status === 'confirmed' && booking.payment_status === 'fully_paid') {
                            html += '<button class="btn btn-sm btn-outline-info btn-checkin" data-id="' + booking.id + '" title="' + bookingsTranslations.check_in + '"><i class="fa fa-sign-in"></i></button>';
                        }
                        if (booking.status !== 'cancelled' && booking.status !== 'completed') {
                            html += '<button class="btn btn-sm btn-outline-danger btn-cancel" data-id="' + booking.id + '" title="' + bookingsTranslations.cancel + '"><i class="fa fa-times"></i></button>';
                        }

                        html += '</div>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="8" class="text-center text-danger">Connection error</td></tr>');
            }
        });
    }

    // Status badge helper
    function getStatusBadge(status) {
        var badges = {
            'pending': '<span class="badge badge-warning">Pending</span>',
            'confirmed': '<span class="badge badge-primary">Confirmed</span>',
            'active': '<span class="badge badge-success">Active</span>',
            'completed': '<span class="badge badge-secondary">Completed</span>',
            'cancelled': '<span class="badge badge-danger">Cancelled</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    // Payment badge helper
    function getPaymentBadge(status) {
        var badges = {
            'pending': '<span class="badge badge-warning">Pending</span>',
            'deposit_paid': '<span class="badge badge-info">Deposit</span>',
            'fully_paid': '<span class="badge badge-success">Paid</span>',
            'overdue': '<span class="badge badge-danger">Overdue</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    // Filters
    $('#search-bookings').on('keyup', debounce(loadBookings, 500));
    $('#filter-status, #filter-payment').on('change', loadBookings);
    $('#btn-refresh').on('click', loadBookings);

    // Confirm booking
    $(document).on('click', '.btn-confirm', function() {
        var bookingId = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_confirm',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({icon: 'success', title: 'Booking confirmed', timer: 1500, showConfirmButton: false});
                    loadBookings();
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: response.data.message});
                }
            }
        });
    });

    // Check-in
    $(document).on('click', '.btn-checkin', function() {
        var bookingId = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_check_in',
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({icon: 'success', title: 'Checked in', timer: 1500, showConfirmButton: false});
                    loadBookings();
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: response.data.message});
                }
            }
        });
    });

    // Cancel booking
    $(document).on('click', '.btn-cancel', function() {
        var bookingId = $(this).data('id');
        Swal.fire({
            title: 'Cancel Booking',
            text: 'Are you sure you want to cancel this booking?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, cancel it'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_booth_bookings_cancel',
                        nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                        id: bookingId,
                        reason: 'Cancelled by admin'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({icon: 'success', title: 'Booking cancelled', timer: 1500, showConfirmButton: false});
                            loadBookings();
                        } else {
                            Swal.fire({icon: 'error', title: 'Error', text: response.data.message});
                        }
                    }
                });
            }
        });
    });

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('en-SA', {minimumFractionDigits: 2}) + ' SAR';
    }

    function formatDate(date) {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('en-GB');
    }

    function debounce(func, wait) {
        var timeout;
        return function() {
            clearTimeout(timeout);
            timeout = setTimeout(func, wait);
        };
    }

    // Initial load
    if (currentEventId) {
        loadBookings();
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
