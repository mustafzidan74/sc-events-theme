<?php
/**
 * Dashboard Create Booth Booking Page
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

$page_title = sc_t('booths.add_booking', 'Add Booking');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'booths' => sc_t('nav.booths', 'Booths'),
    'bookings' => sc_t('booths.bookings', 'Bookings'),
    'add_booking' => sc_t('booths.add_booking', 'Add Booking'),
    'save' => sc_t('dashboard_pages.save', 'Save'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'booking_info' => sc_t('booths.booking_info', 'Booking Information'),
    'payment_info' => sc_t('booths.payment_info', 'Payment Information'),
);

// Get event ID and booth ID from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$booth_id = isset($_GET['booth_id']) ? intval($_GET['booth_id']) : 0;

// Get events for dropdown
global $wpdb;
$events = $wpdb->get_results("SELECT id, title, start_date, end_date FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY start_date DESC");

// Auto-select if only one event
if (!$event_id && count($events) === 1) {
    $event_id = $events[0]->id;
}

// Get company attendees (exhibitors)
$companies = $wpdb->get_results("SELECT id, company_name FROM {$wpdb->prefix}sc_company_attendees ORDER BY company_name");

// Get preselected company from URL
$preselect_company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['add_booking']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?>"><?php echo $t['bookings']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['add_booking']; ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-md-12">
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-calendar-plus"></i> <?php echo $t['booking_info']; ?></h2>
                    </div>
                    <div class="body">
                        <form id="booking-form">
                            <!-- Event Selector -->
                            <div class="form-group">
                                <label><strong><?php echo $t['select_event']; ?> *</strong></label>
                                <select id="event_id" name="event_id" class="form-control" required>
                                    <option value="">-- <?php echo $t['select_event']; ?> --</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event->id; ?>"
                                                data-start="<?php echo $event->start_date; ?>"
                                                data-end="<?php echo $event->end_date; ?>"
                                                <?php selected($event_id, $event->id); ?>>
                                            <?php echo esc_html($event->title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Booth *</label>
                                        <select class="form-control" id="booth_id" name="booth_id" required>
                                            <option value="">Select booth first</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Company *</label>
                                        <select class="form-control" id="company_attendee_id" name="company_attendee_id" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo $company->id; ?>" <?php selected($preselect_company_id, $company->id); ?>><?php echo esc_html($company->company_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?php _e('Select from registered company attendees', 'sc_events'); ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>End Date *</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Special Requirements</label>
                                <textarea class="form-control" id="special_requirements" name="special_requirements" rows="3" placeholder="Any special setup or requirements..."></textarea>
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Internal notes..."></textarea>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <div class="col-lg-4 col-md-12">
                <!-- Payment Information -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-money"></i> <?php echo $t['payment_info']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label>Total Amount (SAR) *</label>
                            <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" value="0" min="0">
                            <small class="text-muted">Booth price: <span id="booth-price">-</span></small>
                        </div>

                        <div class="form-group">
                            <label>Deposit Amount (SAR)</label>
                            <input type="number" step="0.01" class="form-control" id="deposit_amount" name="deposit_amount" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label>Payment Status</label>
                            <select class="form-control" id="payment_status" name="payment_status">
                                <option value="pending">Pending</option>
                                <option value="deposit_paid">Deposit Paid</option>
                                <option value="fully_paid">Fully Paid</option>
                            </select>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label>Booking Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                            </select>
                        </div>

                        <hr>

                        <button type="button" class="btn btn-success btn-block btn-lg" id="btn-save">
                            <i class="fa fa-save"></i> <?php echo $t['save']; ?>
                        </button>
                        <a href="<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?><?php echo $event_id ? '?event_id=' . $event_id : ''; ?>" class="btn btn-secondary btn-block">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['cancel']; ?>
                        </a>
                    </div>
                </div>

                <!-- Booth Info Card -->
                <div class="card" id="booth-info-card" style="display:none;">
                    <div class="header">
                        <h2><i class="fa fa-th-large"></i> Booth Details</h2>
                    </div>
                    <div class="body">
                        <div id="booth-info-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("sc_dashboard_nonce"); ?>';
    var booths = [];

    // Load booths when event changes
    $('#event_id').on('change', function() {
        var eventId = $(this).val();
        var $option = $(this).find('option:selected');

        // Set default dates from event
        if ($option.data('start')) {
            $('#start_date').val($option.data('start'));
        }
        if ($option.data('end')) {
            $('#end_date').val($option.data('end'));
        }

        if (eventId) {
            loadAvailableBooths(eventId);
        } else {
            $('#booth_id').html('<option value="">Select booth first</option>');
            $('#booth-info-card').hide();
        }
    });

    // Load booths on page load if event is selected
    var initialEventId = <?php echo $event_id ?: 0; ?>;
    if (initialEventId) {
        loadAvailableBooths(initialEventId);
        var $option = $('#event_id').find('option:selected');
        if ($option.data('start')) {
            $('#start_date').val($option.data('start'));
        }
        if ($option.data('end')) {
            $('#end_date').val($option.data('end'));
        }
    }

    function loadAvailableBooths(eventId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_booths_get_all',
                nonce: nonce,
                event_id: eventId,
                status: 'available'
            },
            success: function(response) {
                if (response.success) {
                    booths = response.data.booths;
                    var html = '<option value="">Select Booth</option>';
                    booths.forEach(function(b) {
                        var selected = (b.id == <?php echo $booth_id; ?>) ? 'selected' : '';
                        html += '<option value="' + b.id + '" ' + selected + '>' + escapeHtml(b.booth_number) + ' - ' + escapeHtml(b.type_name || 'No type') + ' (SAR ' + parseFloat(b.effective_price).toLocaleString() + ')</option>';
                    });
                    $('#booth_id').html(html);

                    <?php if ($booth_id): ?>
                    $('#booth_id').trigger('change');
                    <?php endif; ?>
                }
            }
        });
    }

    // Show booth info when booth is selected
    $('#booth_id').on('change', function() {
        var boothId = $(this).val();
        if (boothId) {
            var booth = booths.find(function(b) { return b.id == boothId; });
            if (booth) {
                var html = '<table class="table table-sm">';
                html += '<tr><th>Number</th><td>' + escapeHtml(booth.booth_number) + '</td></tr>';
                html += '<tr><th>Type</th><td>' + escapeHtml(booth.type_name || 'N/A') + '</td></tr>';
                html += '<tr><th>Size</th><td>' + booth.effective_width + 'm x ' + booth.effective_depth + 'm</td></tr>';
                html += '<tr><th>Price</th><td>SAR ' + parseFloat(booth.effective_price).toLocaleString() + '</td></tr>';
                html += '<tr><th>Floor</th><td>' + (booth.floor_level || 1) + '</td></tr>';
                html += '</table>';
                $('#booth-info-content').html(html);
                $('#booth-info-card').show();

                // Set price
                $('#total_amount').val(booth.effective_price);
                $('#booth-price').text('SAR ' + parseFloat(booth.effective_price).toLocaleString());

                // Calculate deposit (30% default)
                var deposit = booth.effective_price * 0.3;
                $('#deposit_amount').val(deposit.toFixed(2));
            }
        } else {
            $('#booth-info-card').hide();
            $('#booth-price').text('-');
        }
    });

    // Save booking
    $('#btn-save').on('click', function() {
        var $btn = $(this);
        var eventId = $('#event_id').val();
        var boothId = $('#booth_id').val();
        var companyAttendeeId = $('#company_attendee_id').val();

        if (!eventId) {
            toastr.error('Please select an event');
            return;
        }
        if (!boothId) {
            toastr.error('Please select a booth');
            return;
        }
        if (!companyAttendeeId) {
            toastr.error('Please select a company');
            return;
        }

        var totalAmount = parseFloat($('#total_amount').val()) || 0;
        var data = {
            action: 'sc_booth_bookings_create',
            nonce: nonce,
            event_id: eventId,
            booth_id: boothId,
            company_attendee_id: companyAttendeeId,
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            base_price: totalAmount,
            total_amount: totalAmount,
            deposit_required: $('#deposit_amount').val(),
            status: $('#status').val(),
            payment_status: $('#payment_status').val(),
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
                    window.location.href = '<?php echo home_url('/event-manager-dashboard/booth-bookings'); ?>?event_id=' + eventId;
                } else {
                    toastr.error(response.data.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
                }
            },
            error: function() {
                toastr.error('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo esc_js($t['save']); ?>');
            }
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
