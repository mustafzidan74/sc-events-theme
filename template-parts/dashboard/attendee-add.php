<?php
/**
 * Dashboard - Add Attendee Page
 * Standalone page for adding new attendees
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.add_attendee', 'Add Attendee'),
    'attendees' => sc_t('dashboard_pages.attendees', 'Attendees'),
    'add' => sc_t('dashboard_pages.add', 'Add'),
    'back_to_attendees' => sc_t('dashboard_pages.back_to_attendees', 'Back to Attendees'),
    'user_type' => sc_t('dashboard_pages.user_type', 'User Type'),
    'existing_user' => sc_t('dashboard_pages.existing_user', 'Existing User'),
    'select_from_registered' => sc_t('dashboard_pages.select_from_registered', 'Select from registered users'),
    'new_registration' => sc_t('dashboard_pages.new_registration', 'New Registration'),
    'create_new_account' => sc_t('dashboard_pages.create_new_account', 'Create new user account'),
    'search_select_user' => sc_t('dashboard_pages.search_select_user', 'Search & Select User'),
    'type_to_search' => sc_t('dashboard_pages.type_to_search', 'Type to search users...'),
    'search_by_name_email' => sc_t('dashboard_pages.search_by_name_email', 'Search by name or email'),
    'personal_information' => sc_t('dashboard_pages.personal_information', 'Personal Information'),
    'full_name' => sc_t('dashboard_pages.full_name', 'Full Name'),
    'enter_full_name' => sc_t('dashboard_pages.enter_full_name', 'Enter full name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'enter_email' => sc_t('dashboard_pages.enter_email', 'Enter email address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'enter_phone' => sc_t('dashboard_pages.enter_phone', 'Enter phone number'),
    'event_ticket' => sc_t('dashboard_pages.event_ticket', 'Event & Ticket'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'select_event_option' => sc_t('dashboard_pages.select_event_option', '-- Select Event --'),
    'select_ticket' => sc_t('dashboard_pages.select_ticket', 'Select Ticket'),
    'select_event_first' => sc_t('dashboard_pages.select_event_first', '-- Select Event First --'),
    'ticket_price' => sc_t('dashboard_pages.ticket_price', 'Ticket Price'),
    'select_ticket_first' => sc_t('dashboard_pages.select_ticket_first', 'Select ticket first'),
    'payment_type' => sc_t('dashboard_pages.payment_type', 'Payment Type'),
    'free' => sc_t('dashboard_pages.free', 'Free'),
    'coupon' => sc_t('dashboard_pages.coupon', 'Coupon'),
    'paid' => sc_t('dashboard_pages.paid', 'Paid'),
    'payment_status' => sc_t('dashboard_pages.payment_status', 'Payment Status'),
    'success' => sc_t('dashboard_pages.success', 'Success'),
    'failed' => sc_t('dashboard_pages.failed', 'Failed'),
    'coupon_code_optional' => sc_t('dashboard_pages.coupon_code_optional', 'Coupon Code (optional)'),
    'enter_coupon_code' => sc_t('dashboard_pages.enter_coupon_code', 'Enter coupon code'),
    'additional_information' => sc_t('dashboard_pages.additional_information', 'Additional Information'),
    'extra_fields_info' => sc_t('dashboard_pages.extra_fields_info', 'Extra fields will appear here based on the selected event\'s configuration.'),
    'notes' => sc_t('dashboard_pages.notes', 'Notes'),
    'internal_notes' => sc_t('dashboard_pages.internal_notes', 'Internal Notes'),
    'internal_notes_placeholder' => sc_t('dashboard_pages.internal_notes_placeholder', 'Add any notes about this attendee (not visible to attendee)'),
    'save_attendee' => sc_t('dashboard_pages.save_attendee', 'Save Attendee'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
    'checkin_options' => sc_t('dashboard_pages.checkin_options', 'Check-in Options'),
    'mark_as_checked_in' => sc_t('dashboard_pages.mark_as_checked_in', 'Mark as Checked In'),
    'already_checked_in_note' => sc_t('dashboard_pages.already_checked_in_note', 'If checked, the attendee will be marked as already checked in.'),
    'notifications' => sc_t('dashboard_pages.notifications', 'Notifications'),
    'send_confirmation_email' => sc_t('dashboard_pages.send_confirmation_email', 'Send Confirmation Email'),
    'send_confirmation_note' => sc_t('dashboard_pages.send_confirmation_note', 'Send a confirmation email with ticket details to the attendee.'),
    'send_eticket' => sc_t('dashboard_pages.send_eticket', 'Send E-Ticket'),
    'send_eticket_note' => sc_t('dashboard_pages.send_eticket_note', 'Include e-ticket with QR code in the confirmation email.'),
    'quick_options' => sc_t('dashboard_pages.quick_options', 'Quick Options'),
    'add_another_attendee' => sc_t('dashboard_pages.add_another_attendee', 'Add Another Attendee'),
    'add_another_note' => sc_t('dashboard_pages.add_another_note', 'Stay on this page after saving to add more attendees.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // JavaScript translations
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'select_ticket_placeholder' => sc_t('dashboard_pages.select_ticket_placeholder', '-- Select Ticket --'),
    'no_tickets_available' => sc_t('dashboard_pages.no_tickets_available', 'No tickets available'),
    'error_loading_tickets' => sc_t('dashboard_pages.error_loading_tickets', 'Error loading tickets'),
    'no_extra_fields' => sc_t('dashboard_pages.no_extra_fields', 'No extra fields configured for this event.'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'attendee_added' => sc_t('dashboard_pages.attendee_added', 'Attendee added successfully!'),
    'error_adding_attendee' => sc_t('dashboard_pages.error_adding_attendee', 'Error adding attendee'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'select' => sc_t('dashboard_pages.select', '-- Select --'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get event_id from URL if provided
$preselect_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get all events — include 'completed' so attendees can be added retroactively to past events.
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status'  => array('publish', 'completed'),
        'limit'   => 500,
        'orderby' => 'start_date',
        'order'   => 'DESC',
    ));
}

// Get tickets for preselected event
$preselect_tickets = array();
if ($preselect_event_id && class_exists('SC_Ticket')) {
    $preselect_tickets = SC_Ticket::get_by_event($preselect_event_id);
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['page_title']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>"><?php echo $t['attendees']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['add']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back_to_attendees']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="attendee-add-form" class="attendee-form">
            <?php wp_nonce_field('sc_attendee_action', 'sc_attendee_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_attendee">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- User Type Selection -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-user-circle"></i> <?php echo $t['user_type']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="custom-control custom-radio user-type-option" id="existing-user-box">
                                        <input type="radio" class="custom-control-input" id="user-type-existing" name="user_type" value="existing">
                                        <label class="custom-control-label" for="user-type-existing">
                                            <strong><?php echo $t['existing_user']; ?></strong>
                                            <small class="d-block text-muted"><?php echo $t['select_from_registered']; ?></small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-control custom-radio user-type-option" id="new-user-box">
                                        <input type="radio" class="custom-control-input" id="user-type-new" name="user_type" value="new" checked>
                                        <label class="custom-control-label" for="user-type-new">
                                            <strong><?php echo $t['new_registration']; ?></strong>
                                            <small class="d-block text-muted"><?php echo $t['create_new_account']; ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Existing User Selection -->
                            <div id="existing-user-section" class="mt-3" style="display: none;">
                                <div class="form-group">
                                    <label for="existing-user-select"><?php echo $t['search_select_user']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                    <input type="hidden" id="existing-user-select" name="existing_user_id" style="width: 100%;">
                                    <small class="text-muted"><?php echo $t['search_by_name_email']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-user"></i> <?php echo $t['personal_information']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-name"><?php echo $t['full_name']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <input type="text" class="form-control" id="attendee-name" name="name" required placeholder="<?php echo esc_attr($t['enter_full_name']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-email"><?php echo $t['email_address']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <input type="email" class="form-control" id="attendee-email" name="email" required placeholder="<?php echo esc_attr($t['enter_email']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="attendee-phone"><?php echo $t['phone_number']; ?></label>
                                <input type="tel" class="form-control" id="attendee-phone" name="phone" placeholder="<?php echo esc_attr($t['enter_phone']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Event & Ticket Selection -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-ticket"></i> <?php echo $t['event_ticket']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-event"><?php echo $t['select_event']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <select class="form-control" id="attendee-event" name="event_id" required>
                                            <option value=""><?php echo $t['select_event_option']; ?></option>
                                            <?php foreach ($events as $event): ?>
                                                <option value="<?php echo $event->id; ?>" <?php selected($event->id, $preselect_event_id); ?>>
                                                    <?php echo esc_html($event->title); ?> (<?php echo date('M d, Y', strtotime($event->start_date)); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-ticket"><?php echo $t['select_ticket']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <select class="form-control" id="attendee-ticket" name="ticket_id" required>
                                            <option value=""><?php echo $t['select_event_first']; ?></option>
                                            <?php if (!empty($preselect_tickets)): ?>
                                                <?php foreach ($preselect_tickets as $ticket): ?>
                                                    <option value="<?php echo $ticket->id; ?>" data-price="<?php echo $ticket->price; ?>">
                                                        <?php echo esc_html($ticket->name); ?> - <?php echo sc_format_price($ticket->price); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="attendee-ticket-price"><?php echo $t['ticket_price']; ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="attendee-ticket-price" readonly placeholder="<?php echo esc_attr($t['select_ticket_first']); ?>">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?php echo sc_get_currency_symbol(); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="attendee-payment-type"><?php echo $t['payment_type']; ?></label>
                                        <select class="form-control" id="attendee-payment-type" name="payment_type">
                                            <option value="free"><?php echo $t['free']; ?></option>
                                            <option value="coupon"><?php echo $t['coupon']; ?></option>
                                            <option value="paid"><?php echo $t['paid']; ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="attendee-payment-status"><?php echo $t['payment_status']; ?></label>
                                        <select class="form-control" id="attendee-payment-status" name="payment_status">
                                            <option value="success"><?php echo $t['success']; ?></option>
                                            <option value="failed"><?php echo $t['failed']; ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="attendee-coupon"><?php echo $t['coupon_code_optional']; ?></label>
                                        <input type="text" class="form-control" id="attendee-coupon" name="coupon_code" placeholder="<?php echo esc_attr($t['enter_coupon_code']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extra Fields -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-list-alt"></i> <?php echo $t['additional_information']; ?></h2>
                        </div>
                        <div class="body" id="extra-fields-container">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> <?php echo $t['extra_fields_info']; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sticky-note"></i> <?php echo $t['notes']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="attendee-notes"><?php echo $t['internal_notes']; ?></label>
                                <textarea class="form-control" id="attendee-notes" name="notes" rows="3" placeholder="<?php echo esc_attr($t['internal_notes_placeholder']); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Submit Box -->
                    <div class="card">
                        <div class="header bg-success">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['save_attendee']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="attendee-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="attendee-status" name="status">
                                    <option value="active"><?php echo $t['active']; ?></option>
                                    <option value="pending"><?php echo $t['pending']; ?></option>
                                    <option value="cancelled"><?php echo $t['cancelled']; ?></option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-block" id="save-attendee-btn">
                                    <i class="fa fa-user-plus"></i> <?php echo $t['page_title']; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Check-in Options -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-check-circle"></i> <?php echo $t['checkin_options']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="checkbox">
                                <input type="checkbox" id="attendee-checked-in" name="check_in_status" value="1">
                                <label for="attendee-checked-in"><?php echo $t['mark_as_checked_in']; ?></label>
                            </div>
                            <small class="text-muted"><?php echo $t['already_checked_in_note']; ?></small>
                        </div>
                    </div>

                    <!-- Notification Options -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-envelope"></i> <?php echo $t['notifications']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="checkbox">
                                <input type="checkbox" id="send-confirmation" name="send_confirmation" value="1" checked>
                                <label for="send-confirmation"><?php echo $t['send_confirmation_email']; ?></label>
                            </div>
                            <small class="text-muted"><?php echo $t['send_confirmation_note']; ?></small>

                            <hr>

                            <div class="checkbox">
                                <input type="checkbox" id="send-ticket" name="send_ticket" value="1" checked>
                                <label for="send-ticket"><?php echo $t['send_eticket']; ?></label>
                            </div>
                            <small class="text-muted"><?php echo $t['send_eticket_note']; ?></small>
                        </div>
                    </div>

                    <!-- Quick Add Another -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-plus-circle"></i> <?php echo $t['quick_options']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="checkbox">
                                <input type="checkbox" id="add-another" name="add_another" value="1">
                                <label for="add-another"><?php echo $t['add_another_attendee']; ?></label>
                            </div>
                            <small class="text-muted"><?php echo $t['add_another_note']; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Translation variables (before footer) -->
<script>
var attendeeAddTranslations = {
    loading: '<?php echo esc_js($t['loading']); ?>',
    select_event_first: '<?php echo esc_js($t['select_event_first']); ?>',
    select_ticket_placeholder: '<?php echo esc_js($t['select_ticket_placeholder']); ?>',
    no_tickets_available: '<?php echo esc_js($t['no_tickets_available']); ?>',
    error_loading_tickets: '<?php echo esc_js($t['error_loading_tickets']); ?>',
    no_extra_fields: '<?php echo esc_js($t['no_extra_fields']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    attendee_added: '<?php echo esc_js($t['attendee_added']); ?>',
    error_adding_attendee: '<?php echo esc_js($t['error_adding_attendee']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    page_title: '<?php echo esc_js($t['page_title']); ?>',
    type_to_search: '<?php echo esc_js($t['type_to_search']); ?>',
    select: '<?php echo esc_js($t['select']); ?>'
};
</script>

<script>
jQuery(document).ready(function($) {
    // Load tickets when event changes
    $('#attendee-event').on('change', function() {
        var eventId = $(this).val();
        var $ticketSelect = $('#attendee-ticket');

        if (!eventId) {
            $ticketSelect.html('<option value="">' + attendeeAddTranslations.select_event_first + '</option>');
            return;
        }

        $ticketSelect.html('<option value="">' + attendeeAddTranslations.loading + '</option>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_get_tickets_by_event',
                event_id: eventId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data && response.data.tickets) {
                    var html = '<option value="">' + attendeeAddTranslations.select_ticket_placeholder + '</option>';
                    $.each(response.data.tickets, function(index, ticket) {
                        var priceText = ticket.price > 0 ? parseFloat(ticket.price).toFixed(2) + ' <?php echo sc_get_currency_symbol(); ?>' : 'Free';
                        html += '<option value="' + ticket.id + '" data-price="' + ticket.price + '">' + ticket.name + ' - ' + priceText + '</option>';
                    });
                    $ticketSelect.html(html);
                    // Auto-select if only one ticket and update price
                    if (response.data.tickets.length === 1) {
                        $ticketSelect.find('option:last').prop('selected', true);
                        // Trigger change event to update price via the delegated handler
                        $ticketSelect.trigger('change');
                    }
                } else {
                    $ticketSelect.html('<option value="">' + attendeeAddTranslations.no_tickets_available + '</option>');
                }
            },
            error: function() {
                $ticketSelect.html('<option value="">' + attendeeAddTranslations.error_loading_tickets + '</option>');
            }
        });

        // Load extra fields for the event
        loadExtraFields(eventId);
    });

    // Helper to update ticket price display
    function updateTicketPrice() {
        var $selected = $('#attendee-ticket').find(':selected');
        // Use .attr() as primary — .data() cache doesn't work for dynamically created options
        var price = $selected.length ? ($selected.attr('data-price') || $selected.data('price') || 0) : 0;
        price = parseFloat(price) || 0;

        $('#attendee-ticket-price').val(price.toFixed(2)).attr('placeholder', price.toFixed(2));
        if (price > 0) {
            $('#attendee-payment-type').val('paid');
        } else {
            $('#attendee-payment-type').val('free');
        }
    }

    // Update price display and payment type when ticket changes
    $(document).on('change', '#attendee-ticket', function() {
        updateTicketPrice();
    });

    // Load extra fields for event
    function loadExtraFields(eventId) {
        var $container = $('#extra-fields-container');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_get_event_extra_fields',
                event_id: eventId,
                nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>'
            },
            success: function(response) {
                var fields = response.data && response.data.fields ? response.data.fields : response.data;
                if (response.success && fields && fields.length > 0) {
                    var html = '';
                    $.each(fields, function(index, field) {
                        // Use label as key since name might not be set
                        var fieldKey = field.name || field.label;
                        var fieldId = 'extra-' + index;

                        html += '<div class="form-group">';
                        html += '<label for="' + fieldId + '">' + field.label;
                        if (field.required) html += ' <span class="text-danger">*</span>';
                        html += '</label>';

                        if (field.type === 'textarea') {
                            html += '<textarea class="form-control" id="' + fieldId + '" name="extra_fields[' + fieldKey + ']" rows="2"' + (field.required ? ' required' : '') + '></textarea>';
                        } else if (field.type === 'select') {
                            html += '<select class="form-control" id="' + fieldId + '" name="extra_fields[' + fieldKey + ']"' + (field.required ? ' required' : '') + '>';
                            html += '<option value="">' + attendeeAddTranslations.select + '</option>';
                            // Handle options as string (newline separated) or array
                            var options = field.options;
                            if (typeof options === 'string') {
                                options = options.split(/[\n,]+/).map(function(o) { return o.trim(); }).filter(function(o) { return o.length > 0; });
                            }
                            if (Array.isArray(options)) {
                                $.each(options, function(i, opt) {
                                    html += '<option value="' + opt + '">' + opt + '</option>';
                                });
                            }
                            html += '</select>';
                        } else if (field.type === 'checkbox') {
                            html += '<div class="checkbox"><input type="checkbox" id="' + fieldId + '" name="extra_fields[' + fieldKey + ']" value="1"><label for="' + fieldId + '">' + field.label + '</label></div>';
                        } else {
                            html += '<input type="' + (field.type || 'text') + '" class="form-control" id="' + fieldId + '" name="extra_fields[' + fieldKey + ']"' + (field.required ? ' required' : '') + '>';
                        }

                        html += '</div>';
                    });
                    $container.html(html);
                } else {
                    $container.html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> ' + attendeeAddTranslations.no_extra_fields + '</div>');
                }
            }
        });
    }

    // Form submission
    $('#attendee-add-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-attendee-btn');

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + attendeeAddTranslations.saving);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeAddTranslations.attendee_added);

                    if ($('#add-another').is(':checked')) {
                        // Reset form for next entry
                        $form[0].reset();
                        // Keep event selected
                        $('#attendee-event').val($('#attendee-event').val()).trigger('change');
                        $submitBtn.prop('disabled', false).html('<i class="fa fa-user-plus"></i> ' + attendeeAddTranslations.page_title);
                    } else {
                        // Redirect to attendees list
                        setTimeout(function() {
                            var eventId = $('#attendee-event').val();
                            window.location.href = '<?php echo home_url('/event-manager-dashboard/attendees'); ?>' + (eventId ? '?event_id=' + eventId : '');
                        }, 1000);
                    }
                } else {
                    var errorMsg = attendeeAddTranslations.error_adding_attendee;
                    if (response.data) {
                        errorMsg = response.data.message || response.data;
                    }
                    toastr.error(errorMsg);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-user-plus"></i> ' + attendeeAddTranslations.page_title);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                var errorMsg = attendeeAddTranslations.error_occurred;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data.message || xhr.responseJSON.data;
                }
                toastr.error(errorMsg);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-user-plus"></i> ' + attendeeAddTranslations.page_title);
            }
        });
    });

    // Trigger change if event is preselected
    <?php if ($preselect_event_id): ?>
    loadExtraFields(<?php echo $preselect_event_id; ?>);
    // Auto-fill price if only one ticket is preselected
    var $preTicket = $('#attendee-ticket');
    if ($preTicket.find('option[value!=""]').length === 1) {
        $preTicket.find('option[value!=""]').prop('selected', true);
        updateTicketPrice();
    } else if ($preTicket.find(':selected').val()) {
        // If a ticket is already selected (e.g. first option), update price
        updateTicketPrice();
    }
    <?php endif; ?>
});
</script>

<style>
.card .header h2 {
    font-size: 16px;
}
.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
}
/* User Type Selection Styling */
.user-type-option {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px 15px 15px 45px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
}
.user-type-option:hover {
    border-color: #17a2b8;
    background: #f8f9fa;
}
.user-type-option.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.user-type-option .custom-control-label {
    cursor: pointer;
}
.user-type-option .custom-control-label strong {
    font-size: 15px;
    color: #333;
}
.user-type-option .custom-control-label small {
    font-size: 12px;
}
#existing-user-section {
    border-top: 1px dashed #dee2e6;
    padding-top: 15px;
}
/* Readonly field styling */
input[readonly], textarea[readonly] {
    background-color: #f5f5f5 !important;
    cursor: not-allowed;
}
/* Select2 User Search Styling */
#existing-user-section .select2-container {
    width: 100% !important;
}
#existing-user-section .select2-container .select2-choice,
#existing-user-section .select2-container--default .select2-selection--single {
    height: 42px;
    padding: 6px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #fff;
}
#existing-user-section .select2-container .select2-choice .select2-chosen,
#existing-user-section .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px;
    color: #495057;
}
#existing-user-section .select2-container .select2-choice .select2-arrow,
#existing-user-section .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
}
#existing-user-section .select2-dropdown {
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
#existing-user-section .select2-results__option--highlighted,
#existing-user-section .select2-results .select2-highlighted {
    background-color: #28a745;
    color: #fff;
}
#existing-user-section .select2-search input,
#existing-user-section .select2-search__field {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
}
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>

<!-- Page-specific scripts (after footer loads Select2) -->
<script>
var attendeeAddPageReady = function($) {
    // Function to initialize user search Select2
    var userSearchInitialized = false;
    function initUserSearchSelect2() {
        if (userSearchInitialized || typeof $.fn.select2 === 'undefined') {
            return;
        }

        // Initialize Select2 with custom query function
        $('#existing-user-select').select2({
            width: '100%',
            placeholder: attendeeAddTranslations.type_to_search,
            allowClear: true,
            minimumInputLength: 2,
            query: function(query) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'sc_search_users',
                        nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
                        search: query.term
                    },
                    success: function(response) {
                        var results = [];
                        if (response.success && response.data) {
                            var users = Array.isArray(response.data) ? response.data : (response.data.results || []);
                            results = users.map(function(user) {
                                return {
                                    id: user.ID,
                                    text: user.display_name + ' (' + user.user_email + ')',
                                    user: user
                                };
                            });
                        }
                        query.callback({ results: results });
                    },
                    error: function() {
                        query.callback({ results: [] });
                    }
                });
            }
        });

        // Handle selection - works with both Select2 3.x and 4.x
        $('#existing-user-select').on('select2:select select2-selecting change', function(e) {
            var user = null;

            // Select2 4.x
            if (e.params && e.params.data && e.params.data.user) {
                user = e.params.data.user;
            }
            // Select2 3.x - choice object
            else if (e.choice && e.choice.user) {
                user = e.choice.user;
            }
            // Select2 3.x - data from element
            else {
                var data = $(this).select2('data');
                if (data && data.user) {
                    user = data.user;
                }
            }

            if (user) {
                $('#attendee-name').val(user.display_name || '');
                $('#attendee-email').val(user.user_email || '');
                $('#attendee-phone').val(user.phone || '');
                console.log('User data filled:', user);
            }
        });

        $('#existing-user-select').on('select2:clear select2-clearing', function() {
            $('#attendee-name, #attendee-email, #attendee-phone').val('').prop('readonly', false);
        });

        userSearchInitialized = true;
        console.log('Select2 initialized for user search');
    }

    // Toggle between existing user and new registration
    $('input[name="user_type"]').on('change', function() {
        var userType = $(this).val();
        if (userType === 'existing') {
            $('#existing-user-section').slideDown(function() {
                initUserSearchSelect2();
            });
            $('#existing-user-select').prop('required', true);
            $('#attendee-name, #attendee-email').prop('readonly', true);
        } else {
            $('#existing-user-section').slideUp();
            $('#existing-user-select').prop('required', false).val(null).trigger('change');
            $('#attendee-name, #attendee-email, #attendee-phone').val('').prop('readonly', false);
        }
        $('.user-type-option').removeClass('selected');
        $(this).closest('.user-type-option').addClass('selected');
    });

    // Initialize selected state
    $('input[name="user_type"]:checked').closest('.user-type-option').addClass('selected');

    // Click on box to select radio
    $('.user-type-option').on('click', function(e) {
        if (!$(e.target).is('input[type="radio"]')) {
            $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
        }
    });
};

// Run when DOM ready
jQuery(document).ready(function($) {
    attendeeAddPageReady($);
});
</script>
