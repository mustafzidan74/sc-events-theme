<?php
/**
 * Dashboard Edit Booth Booking Page
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

// Get booking ID
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$booking_id) {
    wp_redirect(home_url('/event-manager-dashboard/booth-bookings'));
    exit;
}

// Get booking data
global $wpdb;
$booking = $wpdb->get_row($wpdb->prepare(
    "SELECT bb.*, b.booth_number, b.booth_type_id, bt.name as type_name,
            c.company_name, e.title as event_title
     FROM {$wpdb->prefix}sc_booth_bookings bb
     LEFT JOIN {$wpdb->prefix}sc_booths b ON bb.booth_id = b.id
     LEFT JOIN {$wpdb->prefix}sc_booth_types bt ON b.booth_type_id = bt.id
     LEFT JOIN {$wpdb->prefix}sc_company_attendees c ON bb.company_attendee_id = c.id
     LEFT JOIN {$wpdb->prefix}sc_events e ON bb.event_id = e.id
     WHERE bb.id = %d",
    $booking_id
));

if (!$booking) {
    wp_redirect(home_url('/event-manager-dashboard/booth-bookings'));
    exit;
}

$event_id = $booking->event_id;

$page_title = sc_t('booths.edit_booking', 'Edit Booking');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'bookings' => sc_t('booths.bookings', 'Bookings'),
    'edit_booking' => sc_t('booths.edit_booking', 'Edit Booking'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'booking_info' => sc_t('booths.booking_info', 'Booking Information'),
    'payment_info' => sc_t('booths.payment_info', 'Payment Information'),
);

// Get companies
$companies = $wpdb->get_results("SELECT id, company_name FROM {$wpdb->prefix}sc_company_attendees ORDER BY company_name");

// Status colors
$status_colors = array(
    'pending' => 'warning',
    'confirmed' => 'info',
    'active' => 'success',
    'completed' => 'secondary',
    'cancelled' => 'danger'
);

$payment_colors = array(
    'pending' => 'danger',
    'deposit_paid' => 'warning',
    'fully_paid' => 'success',
    'overdue' => 'danger'
);
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['edit_booking']; ?>: <?php echo esc_html($booking->booking_ref); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?>?event_id=<?php echo $event_id; ?>"><?php echo $t['bookings']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['edit_booking']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 text-right">
                    <span class="badge badge-<?php echo $status_colors[$booking->status] ?? 'secondary'; ?>" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo ucfirst($booking->status); ?>
                    </span>
                    <span class="badge badge-<?php echo $payment_colors[$booking->payment_status] ?? 'secondary'; ?>" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo ucfirst(str_replace('_', ' ', $booking->payment_status)); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-calendar"></i> <?php echo $t['booking_info']; ?></h2>
                    </div>
                    <div class="body">
                        <form id="booking-form">
                            <input type="hidden" id="booking_id" value="<?php echo $booking_id; ?>">
                            <input type="hidden" id="event_id" value="<?php echo $event_id; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booking Reference</label>
                                        <input type="text" class="form-control" value="<?php echo esc_attr($booking->booking_ref); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Event</label>
                                        <input type="text" class="form-control" value="<?php echo esc_attr($booking->event_title); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth</label>
                                        <input type="text" class="form-control" value="<?php echo esc_attr($booking->booth_number . ' - ' . ($booking->type_name ?: 'No type')); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Company *</label>
                                        <select class="form-control" id="company_attendee_id" name="company_attendee_id" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo $company->id; ?>" <?php selected($booking->company_attendee_id, $company->id); ?>><?php echo esc_html($company->company_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $booking->start_date; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>End Date *</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $booking->end_date; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Special Requirements</label>
                                <textarea class="form-control" id="special_requirements" name="special_requirements" rows="3"><?php echo esc_textarea($booking->special_requests ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo esc_textarea($booking->notes); ?></textarea>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Timeline -->
                <?php if ($booking->confirmed_at || $booking->checked_in_at || $booking->cancelled_at): ?>
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-history"></i> Timeline</h2>
                    </div>
                    <div class="body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fa fa-calendar-plus text-primary"></i>
                                <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($booking->created_at)); ?>
                            </li>
                            <?php if ($booking->confirmed_at): ?>
                            <li class="mb-2">
                                <i class="fa fa-check-circle text-success"></i>
                                <strong>Confirmed:</strong> <?php echo date('M j, Y g:i A', strtotime($booking->confirmed_at)); ?>
                            </li>
                            <?php endif; ?>
                            <?php if ($booking->checked_in_at): ?>
                            <li class="mb-2">
                                <i class="fa fa-sign-in text-info"></i>
                                <strong>Checked In:</strong> <?php echo date('M j, Y g:i A', strtotime($booking->checked_in_at)); ?>
                            </li>
                            <?php endif; ?>
                            <?php if ($booking->cancelled_at): ?>
                            <li class="mb-2">
                                <i class="fa fa-times-circle text-danger"></i>
                                <strong>Cancelled:</strong> <?php echo date('M j, Y g:i A', strtotime($booking->cancelled_at)); ?>
                                <?php if ($booking->cancellation_reason): ?>
                                    <br><small class="text-muted">Reason: <?php echo esc_html($booking->cancellation_reason); ?></small>
                                <?php endif; ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 col-md-12">
                <!-- Payment Information -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-money"></i> <?php echo $t['payment_info']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label>Total Amount (SAR)</label>
                            <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" value="<?php echo $booking->total_amount; ?>" min="0">
                        </div>

                        <div class="form-group">
                            <label>Deposit Required (SAR)</label>
                            <input type="number" step="0.01" class="form-control" id="deposit_required" name="deposit_required" value="<?php echo $booking->deposit_required; ?>" min="0">
                        </div>

                        <?php
                        $total_paid = floatval($booking->deposit_paid) + floatval($booking->balance_paid);
                        $remaining = floatval($booking->total_amount) - $total_paid;
                        ?>
                        <div class="alert alert-<?php echo ($remaining <= 0) ? 'success' : 'warning'; ?> mb-3">
                            <small>
                                <strong>Deposit Paid:</strong> SAR <?php echo number_format($booking->deposit_paid, 2); ?><br>
                                <strong>Balance Paid:</strong> SAR <?php echo number_format($booking->balance_paid, 2); ?><br>
                                <hr class="my-1">
                                <strong>Total Paid:</strong> SAR <?php echo number_format($total_paid, 2); ?><br>
                                <strong>Remaining:</strong> SAR <?php echo number_format($remaining, 2); ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Payment Status</label>
                            <select class="form-control" id="payment_status" name="payment_status">
                                <option value="pending" <?php selected($booking->payment_status, 'pending'); ?>>Pending</option>
                                <option value="deposit_paid" <?php selected($booking->payment_status, 'deposit_paid'); ?>>Deposit Paid</option>
                                <option value="fully_paid" <?php selected($booking->payment_status, 'fully_paid'); ?>>Fully Paid</option>
                                <option value="overdue" <?php selected($booking->payment_status, 'overdue'); ?>>Overdue</option>
                            </select>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label>Booking Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending</option>
                                <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                <option value="active" <?php selected($booking->status, 'active'); ?>>Active</option>
                                <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                                <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                            </select>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-success btn-block btn-lg" id="btn-save">
                            <i class="fa fa-save"></i> <?php echo $t['save']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?>?event_id=<?php echo $event_id; ?>" class="btn btn-secondary btn-block">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['cancel']; ?>
                        </a>

                        <?php if ($booking->status === 'pending'): ?>
                        <button type="button" class="btn btn-info btn-block" id="btn-confirm">
                            <i class="fa fa-check"></i> Confirm Booking
                        </button>
                        <?php endif; ?>

                        <?php if ($booking->status === 'confirmed' && $booking->payment_status === 'fully_paid'): ?>
                        <button type="button" class="btn btn-primary btn-block" id="btn-checkin">
                            <i class="fa fa-sign-in"></i> Check In
                        </button>
                        <?php endif; ?>

                        <?php if (!in_array($booking->status, ['cancelled', 'completed'])): ?>
                        <button type="button" class="btn btn-danger btn-block" id="btn-cancel">
                            <i class="fa fa-times"></i> Cancel Booking
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Booking Info Card -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-info-circle"></i> Booking Details</h2>
                    </div>
                    <div class="body">
                        <table class="table table-sm">
                            <tr>
                                <th>Booth</th>
                                <td><?php echo esc_html($booking->booth_number); ?></td>
                            </tr>
                            <tr>
                                <th>Type</th>
                                <td><?php echo esc_html($booking->type_name ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Company</th>
                                <td><?php echo esc_html($booking->company_name ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td>
                                    <?php
                                    $start = new DateTime($booking->start_date);
                                    $end = new DateTime($booking->end_date);
                                    $diff = $start->diff($end);
                                    echo $diff->days + 1 . ' day(s)';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var bookingId = <?php echo $booking_id; ?>;
    var eventId = <?php echo $event_id; ?>;

    // Save booking
    $('#btn-save').on('click', function() {
        var $btn = $(this);

        var data = {
            action: 'sc_booth_bookings_update',
            nonce: nonce,
            booking_id: bookingId,
            company_attendee_id: $('#company_attendee_id').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            total_amount: $('#total_amount').val(),
            deposit_required: $('#deposit_required').val(),
            payment_status: $('#payment_status').val(),
            status: $('#status').val(),
            special_requests: $('#special_requirements').val(),
            notes: $('#notes').val()
        };

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                } else {
                    toastr.error(response.data.message);
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
            }
        });
    });

    // Confirm booking
    $('#btn-confirm').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Confirming...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_confirm',
                nonce: nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    location.reload();
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Booking');
                }
            }
        });
    });

    // Check in
    $('#btn-checkin').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Checking in...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_check_in',
                nonce: nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    location.reload();
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-sign-in"></i> Check In');
                }
            }
        });
    });

    // Cancel booking
    $('#btn-cancel').on('click', function() {
        var reason = prompt('Cancellation reason (optional):');
        if (reason === null) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Cancelling...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booth_bookings_cancel',
                nonce: nonce,
                booking_id: bookingId,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                    location.reload();
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-times"></i> Cancel Booking');
                }
            }
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
