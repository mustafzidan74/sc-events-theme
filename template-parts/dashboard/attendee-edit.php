<?php
/**
 * Dashboard - Edit Attendee Page
 * Standalone page for editing attendee details
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.edit_attendee', 'Edit Attendee'),
    'attendees' => sc_t('dashboard_pages.attendees', 'Attendees'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'back' => sc_t('dashboard_pages.back', 'Back'),
    'view_event' => sc_t('dashboard_pages.view_event', 'View Event'),
    'personal_information' => sc_t('dashboard_pages.personal_information', 'Personal Information'),
    'full_name' => sc_t('dashboard_pages.full_name', 'Full Name'),
    'email_address' => sc_t('dashboard_pages.email_address', 'Email Address'),
    'phone_number' => sc_t('dashboard_pages.phone_number', 'Phone Number'),
    'event_ticket' => sc_t('dashboard_pages.event_ticket', 'Event & Ticket'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'unknown_event' => sc_t('dashboard_pages.unknown_event', 'Unknown Event'),
    'ticket' => sc_t('dashboard_pages.ticket', 'Ticket'),
    'ticket_price' => sc_t('dashboard_pages.ticket_price', 'Ticket Price'),
    'payment_type' => sc_t('dashboard_pages.payment_type', 'Payment Type'),
    'free' => sc_t('dashboard_pages.free', 'Free'),
    'coupon' => sc_t('dashboard_pages.coupon', 'Coupon'),
    'paid' => sc_t('dashboard_pages.paid', 'Paid'),
    'payment_status' => sc_t('dashboard_pages.payment_status', 'Payment Status'),
    'success' => sc_t('dashboard_pages.success', 'Success'),
    'failed' => sc_t('dashboard_pages.failed', 'Failed'),
    'coupon_code' => sc_t('dashboard_pages.coupon_code', 'Coupon Code'),
    'enter_coupon_code' => sc_t('dashboard_pages.enter_coupon_code', 'Enter coupon code'),
    'quantity' => sc_t('dashboard_pages.quantity', 'Quantity'),
    'amount_paid' => sc_t('dashboard_pages.amount_paid', 'Amount Paid'),
    'ticket_code' => sc_t('dashboard_pages.ticket_code', 'Ticket Code'),
    'not_generated' => sc_t('dashboard_pages.not_generated', 'Not Generated'),
    'additional_information' => sc_t('dashboard_pages.additional_information', 'Additional Information'),
    'notes' => sc_t('dashboard_pages.notes', 'Notes'),
    'internal_notes' => sc_t('dashboard_pages.internal_notes', 'Internal Notes'),
    'checkin_history' => sc_t('dashboard_pages.checkin_history', 'Check-in History'),
    'no_checkin_history' => sc_t('dashboard_pages.no_checkin_history', 'No check-in history available.'),
    'action' => sc_t('dashboard_pages.action', 'Action'),
    'time' => sc_t('dashboard_pages.time', 'Time'),
    'scanned_by' => sc_t('dashboard_pages.scanned_by', 'Scanned By'),
    'check_in' => sc_t('dashboard_pages.check_in', 'Check In'),
    'check_out' => sc_t('dashboard_pages.check_out', 'Check Out'),
    'update_attendee' => sc_t('dashboard_pages.update_attendee', 'Update Attendee'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
    'registered' => sc_t('dashboard_pages.registered', 'Registered'),
    'updated' => sc_t('dashboard_pages.updated', 'Updated'),
    'checkin_status' => sc_t('dashboard_pages.checkin_status', 'Check-in Status'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'not_checked_in' => sc_t('dashboard_pages.not_checked_in', 'Not Checked In'),
    'undo_checkin' => sc_t('dashboard_pages.undo_checkin', 'Undo Check-in'),
    'manual_checkin' => sc_t('dashboard_pages.manual_checkin', 'Manual Check-in'),
    'qr_code' => sc_t('dashboard_pages.qr_code', 'QR Code'),
    'no_ticket_code' => sc_t('dashboard_pages.no_ticket_code', 'No ticket code generated'),
    'download_qr' => sc_t('dashboard_pages.download_qr', 'Download QR'),
    'communication' => sc_t('dashboard_pages.communication', 'Communication'),
    'resend_confirmation' => sc_t('dashboard_pages.resend_confirmation', 'Resend Confirmation'),
    'resend_eticket' => sc_t('dashboard_pages.resend_eticket', 'Resend E-Ticket'),
    'danger_zone' => sc_t('dashboard_pages.danger_zone', 'Danger Zone'),
    'delete_attendee' => sc_t('dashboard_pages.delete_attendee', 'Delete Attendee'),
    'action_cannot_undone' => sc_t('dashboard_pages.action_cannot_undone', 'This action cannot be undone.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'required_field' => sc_t('dashboard_pages.required_field', '*'),
    // JavaScript translations
    'ticket_code_copied' => sc_t('dashboard_pages.ticket_code_copied', 'Ticket code copied!'),
    'confirm_regenerate_code' => sc_t('dashboard_pages.confirm_regenerate_code', 'Are you sure you want to regenerate the ticket code? The old code will no longer work.'),
    'ticket_code_regenerated' => sc_t('dashboard_pages.ticket_code_regenerated', 'Ticket code regenerated!'),
    'error_regenerating' => sc_t('dashboard_pages.error_regenerating', 'Error regenerating ticket code'),
    'attendee_checked_in' => sc_t('dashboard_pages.attendee_checked_in', 'Attendee checked in!'),
    'error_checking_in' => sc_t('dashboard_pages.error_checking_in', 'Error checking in'),
    'confirm_undo_checkin' => sc_t('dashboard_pages.confirm_undo_checkin', 'Are you sure you want to undo the check-in?'),
    'checkin_undone' => sc_t('dashboard_pages.checkin_undone', 'Check-in undone!'),
    'error_undoing_checkin' => sc_t('dashboard_pages.error_undoing_checkin', 'Error undoing check-in'),
    'sending' => sc_t('dashboard_pages.sending', 'Sending...'),
    'confirmation_sent' => sc_t('dashboard_pages.confirmation_sent', 'Confirmation email sent!'),
    'error_sending_email' => sc_t('dashboard_pages.error_sending_email', 'Error sending email'),
    'eticket_sent' => sc_t('dashboard_pages.eticket_sent', 'E-Ticket sent!'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'attendee_updated' => sc_t('dashboard_pages.attendee_updated', 'Attendee updated successfully!'),
    'error_updating' => sc_t('dashboard_pages.error_updating', 'Error updating attendee'),
    'error_occurred' => sc_t('dashboard_pages.error_occurred', 'An error occurred. Please try again.'),
    'confirm_delete' => sc_t('dashboard_pages.confirm_delete', 'Are you sure you want to delete this attendee?\n\nThis action cannot be undone!'),
    'attendee_deleted' => sc_t('dashboard_pages.attendee_deleted', 'Attendee deleted!'),
    'error_deleting' => sc_t('dashboard_pages.error_deleting', 'Error deleting attendee'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

// Get attendee ID from URL
$attendee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$attendee_id) {
    wp_redirect(home_url('/event-manager-dashboard/attendees'));
    exit;
}

// Get attendee data
$attendee = null;
if (class_exists('SC_Attendee')) {
    $attendee = SC_Attendee::get($attendee_id);
}

if (!$attendee) {
    wp_redirect(home_url('/event-manager-dashboard/attendees'));
    exit;
}

$page_title = $t['page_title'] . ': ' . esc_html($attendee->name);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get event
$event = null;
if (class_exists('SC_Event') && $attendee->event_id) {
    $event = SC_Event::get($attendee->event_id);
}

// Get tickets for the event
$tickets = array();
if (class_exists('SC_Ticket') && $attendee->event_id) {
    $tickets = SC_Ticket::get_by_event($attendee->event_id, array('is_active' => null));
}

// Get check-in history
global $wpdb;
$checkins = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sc_checkins WHERE attendee_id = %d ORDER BY created_at DESC LIMIT 10",
    $attendee_id
));

// Parse extra fields
$extra_fields = array();
if (!empty($attendee->extra_fields)) {
    $extra_fields = is_array($attendee->extra_fields) ? $attendee->extra_fields : json_decode($attendee->extra_fields, true);
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
                        <li class="breadcrumb-item active"><?php echo $t['edit']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <?php if ($event): ?>
                        <a href="<?php echo home_url('/event-manager-dashboard/event-view?id=' . $event->id); ?>" class="btn btn-outline-info ml-2">
                            <i class="fa fa-calendar"></i> <?php echo $t['view_event']; ?>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo home_url('/event-manager-dashboard/attendees'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form id="attendee-edit-form" class="attendee-form">
            <?php wp_nonce_field('sc_attendee_action', 'sc_attendee_nonce'); ?>
            <input type="hidden" name="action" value="sc_save_attendee">
            <input type="hidden" name="attendee_id" value="<?php echo $attendee_id; ?>">

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
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
                                        <input type="text" class="form-control" id="attendee-name" name="name" required value="<?php echo esc_attr($attendee->name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-email"><?php echo $t['email_address']; ?> <span class="text-danger"><?php echo $t['required_field']; ?></span></label>
                                        <input type="email" class="form-control" id="attendee-email" name="email" required value="<?php echo esc_attr($attendee->email); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="attendee-phone"><?php echo $t['phone_number']; ?></label>
                                <input type="tel" class="form-control" id="attendee-phone" name="phone" value="<?php echo esc_attr($attendee->phone ?: ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Event & Ticket -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-ticket"></i> <?php echo $t['event_ticket']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><?php echo $t['event']; ?></label>
                                        <input type="text" class="form-control" value="<?php echo $event ? esc_attr($event->title) : $t['unknown_event']; ?>" readonly>
                                        <input type="hidden" name="event_id" value="<?php echo $attendee->event_id; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-ticket"><?php echo $t['ticket']; ?></label>
                                        <select class="form-control" id="attendee-ticket" name="ticket_id">
                                            <?php foreach ($tickets as $ticket): ?>
                                                <option value="<?php echo $ticket->id; ?>" <?php selected($ticket->id, $attendee->ticket_id); ?>>
                                                    <?php echo esc_html($ticket->name); ?> - <?php echo sc_format_price($ticket->price); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <?php
                            // Get current ticket price
                            $current_ticket_price = '0.00';
                            if ($attendee->ticket_id && !empty($tickets)) {
                                foreach ($tickets as $ticket_item) {
                                    if ($ticket_item->id == $attendee->ticket_id) {
                                        $current_ticket_price = number_format($ticket_item->price, 2);
                                        break;
                                    }
                                }
                            }
                            ?>
                            <div class="form-group">
                                <label for="attendee-ticket-price"><?php echo $t['ticket_price']; ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="attendee-ticket-price" readonly value="<?php echo esc_attr($current_ticket_price); ?>">
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
                                            <option value="free" <?php selected($attendee->payment_type ?? '', 'free'); ?>><?php echo $t['free']; ?></option>
                                            <option value="coupon" <?php selected($attendee->payment_type ?? '', 'coupon'); ?>><?php echo $t['coupon']; ?></option>
                                            <option value="paid" <?php selected($attendee->payment_type ?? '', 'paid'); ?>><?php echo $t['paid']; ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="attendee-payment-status"><?php echo $t['payment_status']; ?></label>
                                        <select class="form-control" id="attendee-payment-status" name="payment_status">
                                            <option value="success" <?php selected($attendee->payment_status, 'success'); ?>><?php echo $t['success']; ?></option>
                                            <option value="failed" <?php selected($attendee->payment_status, 'failed'); ?>><?php echo $t['failed']; ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="attendee-coupon"><?php echo $t['coupon_code']; ?></label>
                                        <input type="text" class="form-control" id="attendee-coupon" name="coupon_code" value="<?php echo esc_attr($attendee->coupon_code ?? ''); ?>" placeholder="<?php echo esc_attr($t['enter_coupon_code']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-quantity"><?php echo $t['quantity']; ?></label>
                                        <input type="number" class="form-control" id="attendee-quantity" name="quantity" min="1" value="<?php echo isset($attendee->quantity) ? $attendee->quantity : 1; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="attendee-amount"><?php echo $t['amount_paid']; ?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="attendee-amount" name="amount_paid" min="0" step="0.01" value="<?php echo $attendee->amount_paid ?: 0; ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text"><?php echo sc_get_currency_symbol(); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ticket Code -->
                            <div class="form-group">
                                <label><?php echo $t['ticket_code']; ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?php echo esc_attr($attendee->ticket_code ?: $t['not_generated']); ?>" readonly id="ticket-code-display">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="copy-ticket-code" title="Copy to clipboard">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="regenerate-ticket-code" title="Regenerate ticket code">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extra Fields -->
                    <?php if (!empty($extra_fields)): ?>
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-list-alt"></i> <?php echo $t['additional_information']; ?></h2>
                        </div>
                        <div class="body">
                            <?php foreach ($extra_fields as $key => $value): ?>
                                <div class="form-group">
                                    <label for="extra-<?php echo esc_attr($key); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></label>
                                    <?php if (strlen($value) > 100): ?>
                                        <textarea class="form-control" id="extra-<?php echo esc_attr($key); ?>" name="extra_fields[<?php echo esc_attr($key); ?>]" rows="2"><?php echo esc_textarea($value); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="extra-<?php echo esc_attr($key); ?>" name="extra_fields[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-sticky-note"></i> <?php echo $t['notes']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="attendee-notes"><?php echo $t['internal_notes']; ?></label>
                                <textarea class="form-control" id="attendee-notes" name="notes" rows="3"><?php echo esc_textarea($attendee->notes ?: ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Check-in History -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-history"></i> <?php echo $t['checkin_history']; ?></h2>
                        </div>
                        <div class="body">
                            <?php if (empty($checkins)): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> <?php echo $t['no_checkin_history']; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th><?php echo $t['action']; ?></th>
                                                <th><?php echo $t['time']; ?></th>
                                                <th><?php echo $t['scanned_by']; ?></th>
                                                <th><?php echo $t['notes']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($checkins as $checkin): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($checkin->action === 'check_in'): ?>
                                                            <span class="badge badge-success"><?php echo $t['check_in']; ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning"><?php echo $t['check_out']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y H:i:s', strtotime($checkin->created_at)); ?></td>
                                                    <td>
                                                        <?php
                                                        if ($checkin->scanned_by) {
                                                            $scanner = get_userdata($checkin->scanned_by);
                                                            echo $scanner ? esc_html($scanner->display_name) : 'User #' . $checkin->scanned_by;
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo esc_html($checkin->notes ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Submit Box -->
                    <div class="card">
                        <div class="header bg-primary">
                            <h2 class="text-white"><i class="fa fa-save"></i> <?php echo $t['update_attendee']; ?></h2>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label for="attendee-status"><?php echo $t['status']; ?></label>
                                <select class="form-control" id="attendee-status" name="status">
                                    <option value="active" <?php selected($attendee->status, 'active'); ?>><?php echo $t['active']; ?></option>
                                    <option value="pending" <?php selected($attendee->status, 'pending'); ?>><?php echo $t['pending']; ?></option>
                                    <option value="cancelled" <?php selected($attendee->status, 'cancelled'); ?>><?php echo $t['cancelled']; ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-calendar"></i> <?php echo $t['registered']; ?>: <?php echo date('M d, Y H:i', strtotime($attendee->created_at)); ?><br>
                                    <i class="fa fa-refresh"></i> <?php echo $t['updated']; ?>: <?php echo date('M d, Y H:i', strtotime($attendee->updated_at)); ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-attendee-btn">
                                    <i class="fa fa-save"></i> <?php echo $t['update_attendee']; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Check-in Status -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-check-circle"></i> <?php echo $t['checkin_status']; ?></h2>
                        </div>
                        <div class="body text-center">
                            <?php if ($attendee->checked_in): ?>
                                <div class="mb-3">
                                    <i class="fa fa-check-circle fa-4x text-success"></i>
                                    <h4 class="text-success mt-2"><?php echo $t['checked_in']; ?></h4>
                                    <?php if ($attendee->checked_in_at): ?>
                                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($attendee->checked_in_at)); ?></small>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-warning btn-block" id="undo-checkin-btn">
                                    <i class="fa fa-undo"></i> <?php echo $t['undo_checkin']; ?>
                                </button>
                            <?php else: ?>
                                <div class="mb-3">
                                    <i class="fa fa-times-circle fa-4x text-muted"></i>
                                    <h4 class="text-muted mt-2"><?php echo $t['not_checked_in']; ?></h4>
                                </div>
                                <button type="button" class="btn btn-success btn-block" id="manual-checkin-btn">
                                    <i class="fa fa-check"></i> <?php echo $t['manual_checkin']; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <?php
                    // Generate secure verification URL for QR code
                    $verification_url = home_url('/ticket-view/?attendee_id=' . $attendee->id . '&ticket_code=' . urlencode($attendee->ticket_code));
                    ?>
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-qrcode"></i> <?php echo $t['qr_code']; ?></h2>
                        </div>
                        <div class="body text-center">
                            <div id="qr-code-container" class="mb-3" data-attendee-id="<?php echo $attendee->id; ?>" data-base-url="<?php echo esc_attr(home_url('/ticket-view/')); ?>">
                                <?php if ($attendee->ticket_code): ?>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($verification_url); ?>" alt="QR Code" class="img-fluid">
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="fa fa-qrcode fa-4x"></i>
                                        <p class="mt-2"><?php echo $t['no_ticket_code']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="#" class="btn btn-outline-primary btn-sm" id="download-qr-btn">
                                <i class="fa fa-download"></i> <?php echo $t['download_qr']; ?>
                            </a>
                        </div>
                    </div>

                    <!-- Communication -->
                    <div class="card">
                        <div class="header">
                            <h2><i class="fa fa-envelope"></i> <?php echo $t['communication']; ?></h2>
                        </div>
                        <div class="body">
                            <button type="button" class="btn btn-outline-primary btn-block mb-2" id="resend-confirmation-btn">
                                <i class="fa fa-paper-plane"></i> <?php echo $t['resend_confirmation']; ?>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-block" id="resend-ticket-btn">
                                <i class="fa fa-ticket"></i> <?php echo $t['resend_eticket']; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="card border-danger">
                        <div class="header bg-danger">
                            <h2 class="text-white"><i class="fa fa-warning"></i> <?php echo $t['danger_zone']; ?></h2>
                        </div>
                        <div class="body">
                            <button type="button" class="btn btn-outline-danger btn-block" id="delete-attendee-btn" data-attendee-id="<?php echo $attendee_id; ?>">
                                <i class="fa fa-trash"></i> <?php echo $t['delete_attendee']; ?>
                            </button>
                            <small class="text-muted d-block mt-2"><?php echo $t['action_cannot_undone']; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var attendeeEditTranslations = {
    ticket_code_copied: '<?php echo esc_js($t['ticket_code_copied']); ?>',
    confirm_regenerate_code: '<?php echo esc_js($t['confirm_regenerate_code']); ?>',
    ticket_code_regenerated: '<?php echo esc_js($t['ticket_code_regenerated']); ?>',
    error_regenerating: '<?php echo esc_js($t['error_regenerating']); ?>',
    attendee_checked_in: '<?php echo esc_js($t['attendee_checked_in']); ?>',
    error_checking_in: '<?php echo esc_js($t['error_checking_in']); ?>',
    confirm_undo_checkin: '<?php echo esc_js($t['confirm_undo_checkin']); ?>',
    checkin_undone: '<?php echo esc_js($t['checkin_undone']); ?>',
    error_undoing_checkin: '<?php echo esc_js($t['error_undoing_checkin']); ?>',
    sending: '<?php echo esc_js($t['sending']); ?>',
    confirmation_sent: '<?php echo esc_js($t['confirmation_sent']); ?>',
    error_sending_email: '<?php echo esc_js($t['error_sending_email']); ?>',
    eticket_sent: '<?php echo esc_js($t['eticket_sent']); ?>',
    resend_confirmation: '<?php echo esc_js($t['resend_confirmation']); ?>',
    resend_eticket: '<?php echo esc_js($t['resend_eticket']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    attendee_updated: '<?php echo esc_js($t['attendee_updated']); ?>',
    error_updating: '<?php echo esc_js($t['error_updating']); ?>',
    error_occurred: '<?php echo esc_js($t['error_occurred']); ?>',
    update_attendee: '<?php echo esc_js($t['update_attendee']); ?>',
    confirm_delete: '<?php echo esc_js($t['confirm_delete']); ?>',
    attendee_deleted: '<?php echo esc_js($t['attendee_deleted']); ?>',
    error_deleting: '<?php echo esc_js($t['error_deleting']); ?>'
};

jQuery(document).ready(function($) {
    // Ticket data for price updates
    var ticketsData = <?php echo json_encode(array_map(function($tk) {
        return array(
            'id' => $tk->id,
            'name' => $tk->name,
            'price' => $tk->price,
            'currency' => sc_get_currency_symbol()
        );
    }, $tickets)); ?>;

    // Update ticket price when ticket selection changes
    $('#attendee-ticket').on('change', function() {
        var ticketId = $(this).val();
        var priceText = '0.00';

        for (var i = 0; i < ticketsData.length; i++) {
            if (ticketsData[i].id == ticketId) {
                priceText = parseFloat(ticketsData[i].price).toFixed(2);
                break;
            }
        }

        $('#attendee-ticket-price').val(priceText);
    });

    // Copy ticket code
    $('#copy-ticket-code').on('click', function() {
        var code = $('#ticket-code-display').val();
        navigator.clipboard.writeText(code).then(function() {
            toastr.success(attendeeEditTranslations.ticket_code_copied);
        });
    });

    // Regenerate ticket code
    $('#regenerate-ticket-code').on('click', function() {
        if (!confirm(attendeeEditTranslations.confirm_regenerate_code)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_regenerate_ticket_code',
                attendee_id: <?php echo $attendee_id; ?>,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#ticket-code-display').val(response.data.ticket_code);
                    // Update QR code with full verification URL
                    var baseUrl = $('#qr-code-container').data('base-url');
                    var attendeeId = $('#qr-code-container').data('attendee-id');
                    var verifyUrl = baseUrl + '?attendee_id=' + attendeeId + '&ticket_code=' + encodeURIComponent(response.data.ticket_code);
                    $('#qr-code-container').html('<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(verifyUrl) + '" alt="QR Code" class="img-fluid">');
                    toastr.success(attendeeEditTranslations.ticket_code_regenerated);
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_regenerating);
                }
            }
        });
    });

    // Manual check-in
    $('#manual-checkin-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_manual_checkin',
                attendee_id: <?php echo $attendee_id; ?>,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.attendee_checked_in);
                    location.reload();
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_checking_in);
                    $btn.prop('disabled', false);
                }
            }
        });
    });

    // Undo check-in
    $('#undo-checkin-btn').on('click', function() {
        if (!confirm(attendeeEditTranslations.confirm_undo_checkin)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_undo_checkin',
                attendee_id: <?php echo $attendee_id; ?>,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.checkin_undone);
                    location.reload();
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_undoing_checkin);
                    $btn.prop('disabled', false);
                }
            }
        });
    });

    // Resend confirmation
    $('#resend-confirmation-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + attendeeEditTranslations.sending);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_resend_confirmation',
                attendee_id: <?php echo $attendee_id; ?>,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.confirmation_sent);
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_sending_email);
                }
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> ' + attendeeEditTranslations.resend_confirmation);
            }
        });
    });

    // Resend ticket
    $('#resend-ticket-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + attendeeEditTranslations.sending);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_resend_ticket',
                attendee_id: <?php echo $attendee_id; ?>,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.eticket_sent);
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_sending_email);
                }
                $btn.prop('disabled', false).html('<i class="fa fa-ticket"></i> ' + attendeeEditTranslations.resend_eticket);
            }
        });
    });

    // Download QR with full verification URL
    $('#download-qr-btn').on('click', function(e) {
        e.preventDefault();
        var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=<?php echo urlencode($verification_url); ?>';
        window.open(qrUrl, '_blank');
    });

    // Form submission
    $('#attendee-edit-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#save-attendee-btn');

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + attendeeEditTranslations.saving);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.attendee_updated);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + attendeeEditTranslations.update_attendee);
                } else {
                    var errorMsg = attendeeEditTranslations.error_updating;
                    if (response.data) {
                        errorMsg = response.data.message || response.data;
                    }
                    toastr.error(errorMsg);
                    $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + attendeeEditTranslations.update_attendee);
                }
            },
            error: function() {
                toastr.error(attendeeEditTranslations.error_occurred);
                $submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + attendeeEditTranslations.update_attendee);
            }
        });
    });

    // Delete attendee
    $('#delete-attendee-btn').on('click', function() {
        var attendeeId = $(this).data('attendee-id');

        if (!confirm(attendeeEditTranslations.confirm_delete)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_delete_attendee',
                attendee_id: attendeeId,
                nonce: '<?php echo wp_create_nonce('sc_attendee_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(attendeeEditTranslations.attendee_deleted);
                    setTimeout(function() {
                        window.location.href = '<?php echo home_url('/event-manager-dashboard/attendees'); ?>';
                    }, 1000);
                } else {
                    toastr.error(response.data || attendeeEditTranslations.error_deleting);
                }
            }
        });
    });
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
.border-danger {
    border: 1px solid #dc3545 !important;
}
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
