<?php
/**
 * Customers Management Page
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

// Translations
$page_title = sc_t('dashboard_pages.customers_management', 'Customers Management');
$t = array(
    'customers_management' => sc_t('dashboard_pages.customers_management', 'Customers Management'),
    'customers' => sc_t('dashboard_pages.customers', 'Customers'),
    'export_csv' => sc_t('dashboard_pages.export_csv', 'Export CSV'),
    'total_customers' => sc_t('dashboard_pages.total_customers', 'Total Customers'),
    'active_customers' => sc_t('dashboard_pages.active_customers', 'Active Customers'),
    'registered_this_month' => sc_t('dashboard_pages.registered_this_month', 'Registered This Month'),
    'total_registrations' => sc_t('dashboard_pages.total_registrations', 'Total Registrations'),
    'attended_event' => sc_t('dashboard_pages.attended_event', 'Attended Event'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'registered_from' => sc_t('dashboard_pages.registered_from', 'Registered From'),
    'to' => sc_t('dashboard_pages.to', 'To'),
    'search_placeholder' => sc_t('dashboard_pages.search_name_email_phone', 'Search by name, email, phone...'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'events_attended' => sc_t('dashboard_pages.events_attended', 'Events Attended'),
    'last_event' => sc_t('dashboard_pages.last_event', 'Last Event'),
    'registered' => sc_t('dashboard_pages.registered', 'Registered'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading_customers' => sc_t('dashboard_pages.loading_customers', 'Loading customers...'),
    'delete_selected' => sc_t('dashboard_pages.delete_selected', 'Delete Selected'),
    'email_selected' => sc_t('dashboard_pages.email_selected', 'Email Selected'),
    'customer_details' => sc_t('dashboard_pages.customer_details', 'Customer Details'),
    'loading_customer_details' => sc_t('dashboard_pages.loading_customer_details', 'Loading customer details...'),
    'close' => sc_t('dashboard_pages.close', 'Close'),
    'edit_customer' => sc_t('dashboard_pages.edit_customer', 'Edit Customer'),
    'first_name' => sc_t('dashboard_pages.first_name', 'First Name'),
    'last_name' => sc_t('dashboard_pages.last_name', 'Last Name'),
    'change_password' => sc_t('dashboard_pages.change_password', 'Change Password'),
    'new_password' => sc_t('dashboard_pages.new_password', 'New Password'),
    'leave_blank_current' => sc_t('dashboard_pages.leave_blank_current', 'Leave blank to keep current'),
    'min_characters' => sc_t('dashboard_pages.min_6_characters', 'Minimum 6 characters'),
    'confirm_new_password' => sc_t('dashboard_pages.confirm_new_password', 'Confirm New Password'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'save_changes' => sc_t('dashboard_pages.save_changes', 'Save Changes'),
    'send_email_to_customer' => sc_t('dashboard_pages.send_email_to_customer', 'Send Email to Customer'),
    'recipient' => sc_t('dashboard_pages.recipient', 'Recipient'),
    'subject' => sc_t('dashboard_pages.subject', 'Subject'),
    'message' => sc_t('dashboard_pages.message', 'Message'),
    'available_variables' => sc_t('dashboard_pages.available_variables', 'Available Variables'),
    'customer_name' => sc_t('dashboard_pages.customer_name', 'Customer Name'),
    'customer_email' => sc_t('dashboard_pages.customer_email', 'Customer Email'),
    'send_email' => sc_t('dashboard_pages.send_email', 'Send Email'),
    'send_email_to_selected' => sc_t('dashboard_pages.send_email_to_selected', 'Send Email to Selected Customers'),
    'customers_selected' => sc_t('dashboard_pages.customers_selected', 'customer(s) selected'),
    'send_emails' => sc_t('dashboard_pages.send_emails', 'Send Emails'),
    'view_details' => sc_t('dashboard_pages.view_details', 'View Details'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'delete' => sc_t('dashboard_pages.delete', 'Delete'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'no_customers_found' => sc_t('dashboard_pages.no_customers_found', 'No customers found.'),
    'error_loading_customers' => sc_t('dashboard_pages.error_loading_customers', 'Error loading customers. Please try again.'),
    'showing' => sc_t('dashboard_pages.showing', 'Showing'),
    'of' => sc_t('dashboard_pages.of', 'of'),
    'previous' => sc_t('dashboard_pages.previous', 'Previous'),
    'next' => sc_t('dashboard_pages.next', 'Next'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'ticket_type' => sc_t('dashboard_pages.ticket_type', 'Ticket Type'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'check_in' => sc_t('dashboard_pages.check_in', 'Check-in'),
    'confirmed' => sc_t('dashboard_pages.confirmed', 'Confirmed'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked-in'),
    'not_yet' => sc_t('dashboard_pages.not_yet', 'Not yet'),
    'no_event_registrations' => sc_t('dashboard_pages.no_event_registrations', 'No event registrations found.'),
    'profile_information' => sc_t('dashboard_pages.profile_information', 'Profile Information'),
    'username' => sc_t('dashboard_pages.username', 'Username'),
    'role' => sc_t('dashboard_pages.role', 'Role'),
    'statistics' => sc_t('dashboard_pages.statistics', 'Statistics'),
    'total_events' => sc_t('dashboard_pages.total_events', 'Total Events'),
    'tickets_used' => sc_t('dashboard_pages.tickets_used', 'Tickets Used'),
    'pending_tickets' => sc_t('dashboard_pages.pending_tickets', 'Pending Tickets'),
    'event_registrations' => sc_t('dashboard_pages.event_registrations', 'Event Registrations'),
    'failed_load_customer' => sc_t('dashboard_pages.failed_load_customer', 'Failed to load customer details.'),
    'error_loading_customer' => sc_t('dashboard_pages.error_loading_customer', 'Error loading customer details.'),
    'passwords_not_match' => sc_t('dashboard_pages.passwords_not_match', 'Passwords do not match'),
    'password_min_chars' => sc_t('dashboard_pages.password_min_chars', 'Password must be at least 6 characters'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'customer_updated' => sc_t('dashboard_pages.customer_updated', 'Customer updated successfully!'),
    'error_updating_customer' => sc_t('dashboard_pages.error_updating_customer', 'Error updating customer'),
    'confirm_delete_customer' => sc_t('dashboard_pages.confirm_delete_customer', 'This will delete the customer account. Their event registrations will remain. Are you sure?'),
    'customer_deleted' => sc_t('dashboard_pages.customer_deleted', 'Customer deleted successfully!'),
    'error_deleting_customer' => sc_t('dashboard_pages.error_deleting_customer', 'Error deleting customer'),
    'sending' => sc_t('dashboard_pages.sending', 'Sending...'),
    'email_sent' => sc_t('dashboard_pages.email_sent', 'Email sent successfully!'),
    'error_sending_email' => sc_t('dashboard_pages.error_sending_email', 'Error sending email'),
    'select_customer' => sc_t('dashboard_pages.select_customer', 'Please select at least one customer'),
    'no_customers_selected' => sc_t('dashboard_pages.no_customers_selected', 'No customers selected'),
    'subject_message_required' => sc_t('dashboard_pages.subject_message_required', 'Subject and message are required'),
    'error_sending_emails' => sc_t('dashboard_pages.error_sending_emails', 'Error sending emails. Please try again.'),
    'general' => sc_t('dashboard_pages.general', 'General'),
);
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get all events from custom table
global $wpdb;
$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY title ASC");
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['customers_management']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['customers']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <button type="button" class="btn btn-warning" id="export-customers-btn">
                            <i class="fa fa-download"></i> <?php echo $t['export_csv']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" id="customer-stats">
        <div class="col-lg-3 col-md-6">
            <div class="card info-box-2 hover-zoom-effect">
                <div class="icon bg-primary">
                    <i class="fa fa-users"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['total_customers']; ?></div>
                    <div class="number" id="stat-total">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card info-box-2 hover-zoom-effect">
                <div class="icon bg-success">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['active_customers']; ?></div>
                    <div class="number" id="stat-active">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card info-box-2 hover-zoom-effect">
                <div class="icon bg-info">
                    <i class="fa fa-calendar-check-o"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['registered_this_month']; ?></div>
                    <div class="number" id="stat-month">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card info-box-2 hover-zoom-effect">
                <div class="icon bg-warning">
                    <i class="fa fa-ticket"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['total_registrations']; ?></div>
                    <div class="number" id="stat-registrations">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form id="customers-filters" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="filter-event" class="mr-2"><?php echo $t['attended_event']; ?>:</label>
                            <select class="form-control" id="filter-event" name="event_id">
                                <option value=""><?php echo $t['all_events']; ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-date-from" class="mr-2"><?php echo $t['registered_from']; ?>:</label>
                            <input type="date" class="form-control" id="filter-date-from" name="date_from">
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-date-to" class="mr-2"><?php echo $t['to']; ?>:</label>
                            <input type="date" class="form-control" id="filter-date-to" name="date_to">
                        </div>

                        <div class="form-group mb-2">
                            <input type="text" class="form-control" id="filter-search" placeholder="<?php echo esc_attr($t['search_placeholder']); ?>" style="width: 300px;">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="customers-table">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="select-all"></th>
                                    <th><?php echo $t['name']; ?></th>
                                    <th><?php echo $t['email']; ?></th>
                                    <th><?php echo $t['phone']; ?></th>
                                    <th><?php echo $t['events_attended']; ?></th>
                                    <th><?php echo $t['last_event']; ?></th>
                                    <th><?php echo $t['registered']; ?></th>
                                    <th width="150"><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo $t['loading_customers']; ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="row mt-3" id="customers-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="customers-showing-info"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="customers-pagination">
                                    <!-- Pagination buttons will be inserted here -->
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn" disabled>
                            <i class="fa fa-trash"></i> <?php echo $t['delete_selected']; ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="bulk-email-btn" disabled>
                            <i class="fa fa-envelope"></i> <?php echo $t['email_selected']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- View Customer Details Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-user-circle"></i> <?php echo $t['customer_details']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="customer-details-content">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                    <p class="mt-3"><?php echo $t['loading_customer_details']; ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['close']; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fa fa-edit"></i> <?php echo $t['edit_customer']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="edit-customer-form">
                <div class="modal-body">
                    <input type="hidden" id="edit-customer-id" name="customer_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-first-name"><?php echo $t['first_name']; ?> *</label>
                                <input type="text" class="form-control" id="edit-first-name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-last-name"><?php echo $t['last_name']; ?></label>
                                <input type="text" class="form-control" id="edit-last-name" name="last_name">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-email"><?php echo $t['email']; ?> *</label>
                                <input type="email" class="form-control" id="edit-email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-phone"><?php echo $t['phone']; ?></label>
                                <div class="input-group">
                                    <select class="form-control" id="edit-phone-code" name="phone_code" style="max-width: 100px;">
                                        <option value="+20">+20</option>
                                        <option value="+966">+966</option>
                                        <option value="+971">+971</option>
                                    </select>
                                    <input type="text" class="form-control" id="edit-phone" name="phone" placeholder="XXX XXX XXXX">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="fa fa-key"></i> <?php echo $t['change_password']; ?></h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-new-password"><?php echo $t['new_password']; ?></label>
                                <input type="password" class="form-control" id="edit-new-password" name="new_password" placeholder="<?php echo esc_attr($t['leave_blank_current']); ?>">
                                <small class="form-text text-muted"><?php echo $t['min_characters']; ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-confirm-password"><?php echo $t['confirm_new_password']; ?></label>
                                <input type="password" class="form-control" id="edit-confirm-password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
                    </button>
                    <button type="submit" class="btn btn-warning" id="save-customer-btn">
                        <i class="fa fa-save"></i> <?php echo $t['save_changes']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Email Modal -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-envelope"></i> <?php echo $t['send_email_to_customer']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="send-email-form">
                <div class="modal-body">
                    <input type="hidden" id="email-customer-id" name="customer_id">

                    <div class="form-group">
                        <label><?php echo $t['recipient']; ?>:</label>
                        <p class="form-control-static" id="email-recipient-display"><strong></strong></p>
                    </div>

                    <div class="form-group">
                        <label for="email-subject"><?php echo $t['subject']; ?> *</label>
                        <input type="text" class="form-control" id="email-subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="email-message"><?php echo $t['message']; ?> *</label>
                        <textarea class="form-control" id="email-message" name="message" rows="8" required></textarea>
                        <small class="form-text text-muted">
                            <strong><?php echo $t['available_variables']; ?>:</strong>
                            <code>{name}</code> - <?php echo $t['customer_name']; ?>,
                            <code>{email}</code> - <?php echo $t['customer_email']; ?>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                    <button type="submit" class="btn btn-info" id="send-email-btn">
                        <i class="fa fa-paper-plane"></i> <?php echo $t['send_email']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Email Modal -->
<div class="modal fade" id="bulkEmailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-envelope"></i> <?php echo $t['send_email_to_selected']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="bulk-email-form">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <span id="bulk-email-count">0</span> <?php echo $t['customers_selected']; ?>
                    </div>

                    <div class="form-group">
                        <label for="bulk-email-subject"><?php echo $t['subject']; ?> *</label>
                        <input type="text" class="form-control" id="bulk-email-subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="bulk-email-message"><?php echo $t['message']; ?> *</label>
                        <textarea class="form-control" id="bulk-email-message" name="message" rows="8" required></textarea>
                        <small class="form-text text-muted">
                            <strong><?php echo $t['available_variables']; ?>:</strong>
                            <code>{name}</code> - <?php echo $t['customer_name']; ?>,
                            <code>{email}</code> - <?php echo $t['customer_email']; ?>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                    <button type="submit" class="btn btn-primary" id="send-bulk-email-btn">
                        <i class="fa fa-paper-plane"></i> <?php echo $t['send_emails']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript Translations
var customersTranslations = {
    loading: '<?php echo esc_js($t['loading']); ?>',
    loading_customers: '<?php echo esc_js($t['loading_customers']); ?>',
    no_customers_found: '<?php echo esc_js($t['no_customers_found']); ?>',
    error_loading_customers: '<?php echo esc_js($t['error_loading_customers']); ?>',
    loading_customer_details: '<?php echo esc_js($t['loading_customer_details']); ?>',
    view_details: '<?php echo esc_js($t['view_details']); ?>',
    edit: '<?php echo esc_js($t['edit']); ?>',
    send_email: '<?php echo esc_js($t['send_email']); ?>',
    delete: '<?php echo esc_js($t['delete']); ?>',
    showing: '<?php echo esc_js($t['showing']); ?>',
    of: '<?php echo esc_js($t['of']); ?>',
    previous: '<?php echo esc_js($t['previous']); ?>',
    next: '<?php echo esc_js($t['next']); ?>',
    event: '<?php echo esc_js($t['event']); ?>',
    ticket_type: '<?php echo esc_js($t['ticket_type']); ?>',
    date: '<?php echo esc_js($t['date']); ?>',
    status: '<?php echo esc_js($t['status']); ?>',
    check_in: '<?php echo esc_js($t['check_in']); ?>',
    confirmed: '<?php echo esc_js($t['confirmed']); ?>',
    pending: '<?php echo esc_js($t['pending']); ?>',
    checked_in: '<?php echo esc_js($t['checked_in']); ?>',
    not_yet: '<?php echo esc_js($t['not_yet']); ?>',
    no_event_registrations: '<?php echo esc_js($t['no_event_registrations']); ?>',
    profile_information: '<?php echo esc_js($t['profile_information']); ?>',
    name: '<?php echo esc_js($t['name']); ?>',
    username: '<?php echo esc_js($t['username']); ?>',
    email: '<?php echo esc_js($t['email']); ?>',
    phone: '<?php echo esc_js($t['phone']); ?>',
    registered: '<?php echo esc_js($t['registered']); ?>',
    role: '<?php echo esc_js($t['role']); ?>',
    statistics: '<?php echo esc_js($t['statistics']); ?>',
    total_events: '<?php echo esc_js($t['total_events']); ?>',
    tickets_used: '<?php echo esc_js($t['tickets_used']); ?>',
    pending_tickets: '<?php echo esc_js($t['pending_tickets']); ?>',
    event_registrations: '<?php echo esc_js($t['event_registrations']); ?>',
    failed_load_customer: '<?php echo esc_js($t['failed_load_customer']); ?>',
    error_loading_customer: '<?php echo esc_js($t['error_loading_customer']); ?>',
    passwords_not_match: '<?php echo esc_js($t['passwords_not_match']); ?>',
    password_min_chars: '<?php echo esc_js($t['password_min_chars']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    save_changes: '<?php echo esc_js($t['save_changes']); ?>',
    customer_updated: '<?php echo esc_js($t['customer_updated']); ?>',
    error_updating_customer: '<?php echo esc_js($t['error_updating_customer']); ?>',
    confirm_delete_customer: '<?php echo esc_js($t['confirm_delete_customer']); ?>',
    customer_deleted: '<?php echo esc_js($t['customer_deleted']); ?>',
    error_deleting_customer: '<?php echo esc_js($t['error_deleting_customer']); ?>',
    sending: '<?php echo esc_js($t['sending']); ?>',
    email_sent: '<?php echo esc_js($t['email_sent']); ?>',
    error_sending_email: '<?php echo esc_js($t['error_sending_email']); ?>',
    select_customer: '<?php echo esc_js($t['select_customer']); ?>',
    no_customers_selected: '<?php echo esc_js($t['no_customers_selected']); ?>',
    subject_message_required: '<?php echo esc_js($t['subject_message_required']); ?>',
    error_sending_emails: '<?php echo esc_js($t['error_sending_emails']); ?>',
    general: '<?php echo esc_js($t['general']); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let perPage = 20;
    let totalPages = 1;
    let searchTimeout;

    // Escape HTML helper function
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Parse phone number and set country code and number
    function parseAndSetPhone(phone, codeSelector, phoneSelector) {
        if (!phone) {
            $(codeSelector).val('+20');
            $(phoneSelector).val('');
            return;
        }

        phone = String(phone).trim();
        const countryCodes = ['+971', '+966', '+20'];

        for (let code of countryCodes) {
            if (phone.startsWith(code)) {
                $(codeSelector).val(code);
                $(phoneSelector).val(phone.substring(code.length).trim());
                return;
            }
        }

        // If no country code found, assume +20 and set the full number
        $(codeSelector).val('+20');
        $(phoneSelector).val(phone.replace(/^\+/, ''));
    }

    // Load customers with pagination
    function loadCustomers(page = 1) {
        currentPage = page;

        const filters = {
            action: 'sc_get_customers_paginated',
            nonce: scDashboard.nonce,
            page: page,
            per_page: perPage,
            event_id: $('#filter-event').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: filters,
            beforeSend: function() {
                const tbody = $('#customers-table tbody');
                tbody.html('<tr><td colspan="8" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">Loading...</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderCustomersTable(response.data.customers);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
                updateStats(response.data.stats);
            }
        }).fail(function() {
            const tbody = $('#customers-table tbody');
            tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Error loading customers. Please try again.</td></tr>');
        });
    }

    function renderCustomersTable(customers) {
        const tbody = $('#customers-table tbody');
        tbody.empty();

        if (!customers || customers.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center py-4">No customers found.</td></tr>');
            $('#customers-pagination-controls').hide();
            return;
        }

        customers.forEach(function(customer) {
            const row = `
                <tr>
                    <td><input type="checkbox" class="customer-checkbox" value="${customer.id}"></td>
                    <td>
                        <strong>${escapeHtml(customer.display_name)}</strong>
                    </td>
                    <td>${escapeHtml(customer.email)}</td>
                    <td>${escapeHtml(customer.phone || '-')}</td>
                    <td><span class="badge badge-primary">${customer.events_count}</span></td>
                    <td>${escapeHtml(customer.last_event || '-')}</td>
                    <td>${escapeHtml(customer.registered)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info view-customer" data-id="${customer.id}" title="View Details">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button class="btn btn-warning edit-customer" data-id="${customer.id}" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-primary send-email-customer" data-id="${customer.id}" data-name="${escapeHtml(customer.display_name)}" data-email="${escapeHtml(customer.email)}" title="Send Email">
                                <i class="fa fa-envelope"></i>
                            </button>
                            <button class="btn btn-danger delete-customer" data-id="${customer.id}" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function updatePagination(total, pages, current) {
        totalPages = pages;
        currentPage = current;

        if (pages <= 1) {
            $('#customers-pagination-controls').hide();
            return;
        }

        $('#customers-pagination-controls').show();

        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#customers-showing-info').text(`Showing ${start}-${end} of ${total}`);

        const paginationHtml = [];

        // Previous button
        paginationHtml.push(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">Previous</a>
            </li>
        `);

        // Page numbers
        const maxButtons = 5;
        let startPage = Math.max(1, current - Math.floor(maxButtons / 2));
        let endPage = Math.min(pages, startPage + maxButtons - 1);

        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            paginationHtml.push(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                paginationHtml.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml.push(`
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        if (endPage < pages) {
            if (endPage < pages - 1) {
                paginationHtml.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            paginationHtml.push(`<li class="page-item"><a class="page-link" href="#" data-page="${pages}">${pages}</a></li>`);
        }

        // Next button
        paginationHtml.push(`
            <li class="page-item ${current === pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current + 1}">Next</a>
            </li>
        `);

        $('#customers-pagination').html(paginationHtml.join(''));
    }

    function updateStats(stats) {
        if (stats) {
            $('#stat-total').text(stats.total || 0);
            $('#stat-active').text(stats.active || 0);
            $('#stat-month').text(stats.this_month || 0);
            $('#stat-registrations').text(stats.total_registrations || 0);
        }
    }

    // Pagination click handler
    $(document).on('click', '#customers-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadCustomers(page);
        }
    });

    // Filter changes
    $('#filter-event, #filter-date-from, #filter-date-to').on('change', function() {
        loadCustomers(1);
    });

    $('#filter-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadCustomers(1);
        }, 500);
    });

    // View customer details
    $(document).on('click', '.view-customer', function() {
        const customerId = $(this).data('id');

        $('#customer-details-content').html(`
            <div class="text-center py-5">
                <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                <p class="mt-3">Loading customer details...</p>
            </div>
        `);

        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#viewCustomerModal').modal('show');
            } else {
                $('#viewCustomerModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#viewCustomerModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_customer_details',
                nonce: scDashboard.nonce,
                customer_id: customerId
            }
        }).done(function(response) {
            if (response.success) {
                const c = response.data.customer;
                const events = response.data.events || [];

                let eventsHtml = '';
                if (events.length > 0) {
                    eventsHtml = `
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Ticket Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${events.map(e => `
                                    <tr>
                                        <td>${escapeHtml(e.event_title)}</td>
                                        <td>${escapeHtml(e.ticket_type || 'General')}</td>
                                        <td>${escapeHtml(e.event_date)}</td>
                                        <td>${e.status === 'success' ? '<span class="badge badge-success">Confirmed</span>' : '<span class="badge badge-warning">Pending</span>'}</td>
                                        <td>${e.ticket_status === 'used' ? '<span class="badge badge-success">Checked-in</span>' : '<span class="badge badge-secondary">Not yet</span>'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                } else {
                    eventsHtml = '<p class="text-muted">No event registrations found.</p>';
                }

                const html = `
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fa fa-user"></i> Profile Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Name:</strong> ${escapeHtml(c.display_name)}</p>
                                    <p><strong>Username:</strong> ${escapeHtml(c.username)}</p>
                                    <p><strong>Email:</strong> <a href="mailto:${escapeHtml(c.email)}">${escapeHtml(c.email)}</a></p>
                                    <p><strong>Phone:</strong> ${escapeHtml(c.phone || '-')}</p>
                                    <p><strong>Registered:</strong> ${escapeHtml(c.registered)}</p>
                                    <p><strong>Role:</strong> <span class="badge badge-info">${escapeHtml(c.role)}</span></p>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fa fa-bar-chart"></i> Statistics</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Total Events:</strong> <span class="badge badge-primary">${c.events_count}</span></p>
                                    <p><strong>Tickets Used:</strong> <span class="badge badge-success">${c.tickets_used}</span></p>
                                    <p><strong>Pending Tickets:</strong> <span class="badge badge-warning">${c.tickets_pending}</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fa fa-calendar"></i> Event Registrations (${events.length})</h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    ${eventsHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#customer-details-content').html(html);
            } else {
                $('#customer-details-content').html('<div class="alert alert-danger">Failed to load customer details.</div>');
            }
        }).fail(function() {
            $('#customer-details-content').html('<div class="alert alert-danger">Error loading customer details.</div>');
        });
    });

    // Edit customer
    $(document).on('click', '.edit-customer', function() {
        const customerId = $(this).data('id');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_customer_details',
                nonce: scDashboard.nonce,
                customer_id: customerId
            }
        }).done(function(response) {
            if (response.success) {
                const c = response.data.customer;

                $('#edit-customer-id').val(c.id);
                $('#edit-first-name').val(c.first_name);
                $('#edit-last-name').val(c.last_name);
                $('#edit-email').val(c.email);
                parseAndSetPhone(c.phone, '#edit-phone-code', '#edit-phone');
                $('#edit-new-password').val('');
                $('#edit-confirm-password').val('');

                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#editCustomerModal').modal('show');
                    } else {
                        $('#editCustomerModal').addClass('show').css('display', 'block');
                        $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                    }
                } catch (e) {
                    $('#editCustomerModal').addClass('show').css('display', 'block');
                    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                }
            }
        });
    });

    // Save customer
    $('#edit-customer-form').on('submit', function(e) {
        e.preventDefault();

        const newPassword = $('#edit-new-password').val();
        const confirmPassword = $('#edit-confirm-password').val();

        if (newPassword && newPassword !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }

        if (newPassword && newPassword.length < 6) {
            showError('Password must be at least 6 characters');
            return;
        }

        const formData = $(this).serialize();
        const btn = $('#save-customer-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData + '&action=sc_update_customer&nonce=' + scDashboard.nonce
        }).done(function(response) {
            if (response.success) {
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#editCustomerModal').modal('hide');
                    } else {
                        $('#editCustomerModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#editCustomerModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                loadCustomers(currentPage);
                showSuccess('Customer updated successfully!');
            } else {
                showError(response.data.message || 'Error updating customer');
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
        });
    });

    // Delete customer
    $(document).on('click', '.delete-customer', function() {
        const customerId = $(this).data('id');

        showDeleteConfirm('This will delete the customer account. Their event registrations will remain. Are you sure?').then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_customer',
                    nonce: scDashboard.nonce,
                    customer_id: customerId
                }
            }).done(function(response) {
                if (response.success) {
                    loadCustomers(currentPage);
                    showSuccess('Customer deleted successfully!');
                } else {
                    showError(response.data.message || 'Error deleting customer');
                }
            });
        });
    });

    // Send email
    $(document).on('click', '.send-email-customer', function() {
        const customerId = $(this).data('id');
        const customerName = $(this).data('name');
        const customerEmail = $(this).data('email');

        $('#email-customer-id').val(customerId);
        $('#email-recipient-display strong').text(customerName + ' (' + customerEmail + ')');
        $('#email-subject').val('');
        $('#email-message').val('');

        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#sendEmailModal').modal('show');
            } else {
                $('#sendEmailModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#sendEmailModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Send email form
    $('#send-email-form').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#send-email-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=sc_send_customer_email&nonce=' + scDashboard.nonce
        }).done(function(response) {
            if (response.success) {
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#sendEmailModal').modal('hide');
                    } else {
                        $('#sendEmailModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#sendEmailModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                showSuccess('Email sent successfully!');
            } else {
                showError(response.data.message || 'Error sending email');
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send Email');
        });
    });

    // Select all
    $('#select-all').on('change', function() {
        $('.customer-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });

    $(document).on('change', '.customer-checkbox', function() {
        updateBulkButtons();
    });

    function updateBulkButtons() {
        const checked = $('.customer-checkbox:checked').length;
        $('#bulk-delete-btn, #bulk-email-btn').prop('disabled', checked === 0);
    }

    // Bulk delete
    $('#bulk-delete-btn').on('click', function() {
        const ids = $('.customer-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) return;

        showConfirm(`Delete ${ids.length} customer(s)?`).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_bulk_delete_customers',
                    nonce: scDashboard.nonce,
                    customer_ids: ids
                }
            }).done(function(response) {
                if (response.success) {
                    loadCustomers(currentPage);
                    showSuccess(`${ids.length} customer(s) deleted successfully!`);
                }
            });
        });
    });

    // Bulk email - Open modal
    $('#bulk-email-btn').on('click', function() {
        const ids = $('.customer-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            showError('Please select at least one customer');
            return;
        }

        // Update count in modal
        $('#bulk-email-count').text(ids.length);
        $('#bulk-email-subject').val('');
        $('#bulk-email-message').val('');

        // Show modal
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#bulkEmailModal').modal('show');
            } else {
                $('#bulkEmailModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#bulkEmailModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Bulk email - Submit form
    $('#bulk-email-form').on('submit', function(e) {
        e.preventDefault();

        const ids = $('.customer-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            showError('No customers selected');
            return;
        }

        const subject = $('#bulk-email-subject').val();
        const message = $('#bulk-email-message').val();

        if (!subject || !message) {
            showError('Subject and message are required');
            return;
        }

        const btn = $('#send-bulk-email-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_bulk_email_customers',
                nonce: scDashboard.nonce,
                customer_ids: ids,
                subject: subject,
                message: message
            }
        }).done(function(response) {
            if (response.success) {
                // Close modal
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#bulkEmailModal').modal('hide');
                    } else {
                        $('#bulkEmailModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#bulkEmailModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                showSuccess(response.data.message);
            } else {
                showError(response.data.message || 'Failed to send emails');
            }
        }).fail(function() {
            showError('Error sending emails. Please try again.');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send Emails');
        });
    });

    // Export customers
    $('#export-customers-btn').on('click', function() {
        const filters = {
            event_id: $('#filter-event').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };

        const queryString = $.param(filters);
        window.location.href = scDashboard.ajaxurl + '?action=sc_export_customers_csv&nonce=' + scDashboard.nonce + '&' + queryString;
    });

    // Close modal handler
    $('[data-dismiss="modal"]').on('click', function() {
        const modal = $(this).closest('.modal');
        try {
            if (typeof $.fn.modal !== 'undefined') {
                modal.modal('hide');
            } else {
                modal.removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        } catch (e) {
            modal.removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    });

    // Initial load
    loadCustomers(1);
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
