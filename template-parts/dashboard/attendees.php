<?php
/**
 * Attendees Management Page
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

$page_title = sc_t('dashboard_pages.attendees_management', 'Attendees Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get all events for filter dropdown from Custom Tables
$events = array();
if (class_exists('SC_Event')) {
    $events = SC_Event::get_all(array(
        'status' => array('publish', 'completed'),
        'limit' => 1000,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="block-header">
                    <h2><?php echo esc_html(sc_t('dashboard_pages.attendees_management', 'Attendees Management')); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html(sc_t('dashboard_pages.attendees_management', 'Attendees Management')); ?></li>
                    </ul>
                </div>

                <div>
                    <a href="<?php echo home_url('/event-manager-dashboard/attendee-add'); ?>" class="btn btn-primary">
                        <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_attendee', 'New Attendee')); ?>
                    </a>
                    <button type="button" class="btn btn-danger" id="bulk-email-btn">
                        <i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('dashboard_pages.send_email', 'Send Email')); ?>
                    </button>
                    <button type="button" class="btn btn-info" id="import-attendees-btn">
                        <i class="fa fa-upload"></i> <?php echo esc_html(sc_t('dashboard_pages.import_attendees', 'Import CSV')); ?>
                    </button>
                    <button type="button" class="btn btn-warning" id="export-attendees-btn">
                        <i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.export_csv', 'Export CSV')); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form id="attendees-filters" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="filter-event" class="mr-2"><?php echo esc_html(sc_t('events.event', 'Event')); ?>:</label>
                            <select class="form-control" id="filter-event" name="event_id">
                                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa fa-spinner fa-spin" id="filter-event-loading" style="display: none; margin-left: 10px;"></i>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-status" class="mr-2"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?>:</label>
                            <select class="form-control" id="filter-status" name="status">
                                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_statuses', 'All Status')); ?></option>
                                <option value="success"><?php echo esc_html(sc_t('dashboard_pages.confirmed', 'Confirmed')); ?></option>
                                <option value="pending"><?php echo esc_html(sc_t('dashboard_pages.pending', 'Pending')); ?></option>
                                <option value="failed"><?php echo esc_html(sc_t('payments.failed', 'Failed')); ?></option>
                                <option value="cancelled"><?php echo esc_html(sc_t('general.cancelled', 'Cancelled')); ?></option>
                            </select>
                            <i class="fa fa-spinner fa-spin" id="filter-status-loading" style="display: none; margin-left: 10px;"></i>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-ticket-status" class="mr-2"><?php echo esc_html(sc_t('tickets.ticket_status', 'Ticket Status')); ?>:</label>
                            <select class="form-control" id="filter-ticket-status" name="ticket_status">
                                <option value=""><?php echo esc_html(sc_t('general.all', 'All')); ?></option>
                                <option value="unused"><?php echo esc_html(sc_t('tickets.valid', 'Unused')); ?></option>
                                <option value="used"><?php echo esc_html(sc_t('tickets.used', 'Used')); ?></option>
                            </select>
                            <i class="fa fa-spinner fa-spin" id="filter-ticket-loading" style="display: none; margin-left: 10px;"></i>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-payment" class="mr-2"><?php echo esc_html(sc_t('payments.payment', 'Payment')); ?>:</label>
                            <select class="form-control" id="filter-payment" name="payment_type">
                                <option value=""><?php echo esc_html(sc_t('general.all', 'All')); ?></option>
                                <option value="paid"><?php echo esc_html(sc_t('general.paid', 'Paid')); ?></option>
                                <option value="free"><?php echo esc_html(sc_t('general.free', 'Free')); ?></option>
                                <option value="coupon"><?php echo esc_html(sc_t('tickets.discount_code', 'Coupon')); ?></option>
                            </select>
                            <i class="fa fa-spinner fa-spin" id="filter-payment-loading" style="display: none; margin-left: 10px;"></i>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-coupon" class="mr-2"><?php echo esc_html(sc_t('tickets.discount_code', 'Coupon')); ?>:</label>
                            <input type="text" class="form-control" id="filter-coupon" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.coupon_code', 'Coupon code...')); ?>" style="width: 150px;">
                        </div>

                        <div class="form-group mb-2">
                            <input type="text" class="form-control" id="filter-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_attendees_placeholder', 'Search by name, email, phone, ticket ID...')); ?>" style="width: 300px;">
                        </div>

                        <div class="form-group mb-2 ml-2">
                            <button type="button" class="btn btn-outline-secondary" id="additional-filters-btn">
                                <i class="fa fa-sliders"></i>
                                <?php echo esc_html(sc_t('dashboard_pages.additional_filters', 'Additional Filters')); ?>
                                <span class="badge badge-primary ml-1" id="af-active-count" style="display:none;">0</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendees List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="attendees-table">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="select-all"></th>
                                    <th><?php echo esc_html(sc_t('tickets.ticket_code', 'Ticket ID')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.name', 'Name')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.email', 'Email')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></th>
                                    <th><?php echo esc_html(sc_t('events.event', 'Event')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.ticket_type', 'Ticket Type')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                    <th><?php echo esc_html(sc_t('tickets.ticket_status', 'Ticket Status')); ?></th>
                                    <th><?php echo esc_html(sc_t('scanner.scan_count', 'Scans')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.check_in_time', 'Last Check-in')); ?></th>
                                    <th><?php echo esc_html(sc_t('sessions.check_out_time', 'Last Check-out')); ?></th>
                                    <th><?php echo esc_html(sc_t('tickets.discount_code', 'Coupon Code')); ?></th>
                                    <th><?php echo esc_html(sc_t('payments.payment', 'Payment')); ?></th>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="15" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading attendees...')); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="row mt-3" id="attendees-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="attendees-showing-info"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="attendees-pagination">
                                    <!-- Pagination buttons will be inserted here -->
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn" disabled>
                            <i class="fa fa-trash"></i> <?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete Selected')); ?>
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="bulk-checkin-btn" disabled>
                            <i class="fa fa-check"></i> <?php echo esc_html(sc_t('dashboard_pages.check_in', 'Check-in Selected')); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- View Attendee Details Modal -->
<div class="modal fade" id="viewAttendeeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-eye"></i> <?php echo esc_html(sc_t('dashboard_pages.attendee_details', 'Attendee Details')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="attendee-details-content">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="print-ticket-btn" style="display: none;">
                    <i class="fa fa-print"></i> <?php echo esc_html(sc_t('dashboard_pages.print_ticket', 'Print Ticket')); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Export CSV Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.export_attendees_csv', 'Export Attendees CSV')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo esc_html(sc_t('dashboard_pages.export_options', 'Export Options')); ?>:</label>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="export-all" name="export-option" class="custom-control-input" value="all" checked>
                        <label class="custom-control-label" for="export-all">
                            <?php echo esc_html(sc_t('dashboard_pages.export_all_events', 'Export All Events')); ?>
                        </label>
                    </div>
                    <div class="custom-control custom-radio mt-2">
                        <input type="radio" id="export-event" name="export-option" class="custom-control-input" value="event">
                        <label class="custom-control-label" for="export-event">
                            <?php echo esc_html(sc_t('dashboard_pages.export_specific_event', 'Export Specific Event')); ?>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="export-event-select" style="display: none;">
                    <label for="export-event-id"><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?>:</label>
                    <select class="form-control" id="export-event-id">
                        <option value=""><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?></option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-warning" id="export-confirm-btn">
                    <i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-upload"></i> <?php echo esc_html(sc_t('dashboard_pages.import_attendees_csv', 'Import Attendees CSV')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="import-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Step 1: Select Event -->
                    <div class="form-group">
                        <label for="import-event-id"><strong>1. <?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?></strong> <span class="text-danger">*</span></label>
                        <select class="form-control" id="import-event-id" name="event_id" required>
                            <option value="">-- <?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?> --</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted"><?php echo esc_html(sc_t('dashboard_pages.choose_event_import', 'Choose the event you want to import attendees for')); ?></small>
                    </div>

                    <!-- Step 2: CSV Format Info (Dynamic based on event) -->
                    <div id="csv-format-info" style="display: none;">
                        <div class="alert alert-success">
                            <strong><i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.required_csv_columns', 'Required CSV Columns')); ?>:</strong>
                            <div id="csv-columns-list" class="mt-2"></div>
                        </div>

                        <!-- Additional Information Fields -->
                        <div id="extra-fields-info" style="display: none;">
                            <div class="alert alert-warning">
                                <strong><i class="fa fa-star"></i> <?php echo esc_html(sc_t('dashboard_pages.additional_info_columns', 'Additional Information Columns')); ?>:</strong>
                                <div id="extra-fields-list" class="mt-2"></div>
                            </div>
                        </div>

                        <!-- CSV Template Download -->
                        <div class="text-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-success" id="download-template-btn">
                                <i class="fa fa-download"></i> <?php echo esc_html(sc_t('dashboard_pages.download_csv_template', 'Download CSV Template')); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Upload CSV File -->
                    <div class="form-group" id="file-upload-section" style="display: none;">
                        <label for="csv-file"><strong>2. <?php echo esc_html(sc_t('dashboard_pages.upload_csv_file', 'Upload CSV File')); ?></strong> <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="csv-file" name="csv_file" accept=".csv" required>
                            <label class="custom-file-label" for="csv-file"><?php echo esc_html(sc_t('dashboard_pages.choose_csv_file', 'Choose CSV file...')); ?></label>
                        </div>
                        <small class="form-text text-muted"><?php echo esc_html(sc_t('dashboard_pages.upload_csv_help', 'Upload your CSV file with attendee data')); ?></small>
                    </div>

                    <!-- Step 4: Create Users Option -->
                    <div class="form-group" id="create-users-section" style="display: none;">
                        <label><strong>3. <?php echo esc_html(sc_t('dashboard_pages.user_account_options', 'User Account Options')); ?></strong></label>
                        <div class="card card-body bg-light">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="create-users" name="create_users" value="1">
                                <label class="custom-control-label" for="create-users">
                                    <i class="fa fa-user-plus text-success"></i> <?php echo esc_html(sc_t('dashboard_pages.create_wp_accounts', 'Create WordPress user accounts for attendees')); ?>
                                </label>
                            </div>
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i> <?php echo esc_html(sc_t('dashboard_pages.when_enabled', 'When enabled')); ?>:
                                <ul class="mb-0 mt-1">
                                    <li><?php echo esc_html(sc_t('dashboard_pages.email_as_username', 'Email will be used as username')); ?></li>
                                    <li><?php echo esc_html(sc_t('dashboard_pages.phone_as_password', 'Phone number will be used as password (normalized to 11 digits starting with 01)')); ?></li>
                                    <li><?php echo esc_html(sc_t('dashboard_pages.phone_normalization', 'Phone formats like +20, 20, 201, 1xxx will be automatically normalized')); ?></li>
                                    <li><?php echo esc_html(sc_t('dashboard_pages.existing_users_updated', 'Existing users with same email will be updated with new data')); ?></li>
                                </ul>
                            </small>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div id="import-progress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center mt-2"><?php echo esc_html(sc_t('dashboard_pages.importing', 'Importing...')); ?></p>
                    </div>

                    <!-- Results -->
                    <div id="import-results" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-info" id="import-submit-btn" disabled>
                        <i class="fa fa-upload"></i> <?php echo esc_html(sc_t('dashboard_pages.import', 'Import')); ?>
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
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('dashboard_pages.send_email_to_attendees', 'Send Email to Attendees')); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bulk-email-form">
                    <div class="form-group">
                        <label><?php echo esc_html(sc_t('dashboard_pages.send_to', 'Send To')); ?>:</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="email-all" name="email-target" class="custom-control-input" value="all" checked>
                            <label class="custom-control-label" for="email-all">
                                <strong><?php echo esc_html(sc_t('dashboard_pages.all_attendees', 'All Attendees')); ?></strong> <span class="text-muted">(<?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?>)</span>
                            </label>
                        </div>
                        <div class="custom-control custom-radio mt-2">
                            <input type="radio" id="email-event" name="email-target" class="custom-control-input" value="event">
                            <label class="custom-control-label" for="email-event">
                                <strong><?php echo esc_html(sc_t('dashboard_pages.specific_event', 'Specific Event')); ?></strong>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="email-event-select" style="display: none;">
                        <label for="email-event-id"><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?>:</label>
                        <select class="form-control" id="email-event-id">
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select Event')); ?></option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="email-subject"><?php echo esc_html(sc_t('dashboard_pages.email_subject', 'Email Subject')); ?>: <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="email-subject" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_email_subject', 'Enter email subject')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email-message"><?php echo esc_html(sc_t('dashboard_pages.email_message', 'Email Message')); ?>: <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="email-message" rows="8" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_message', 'Enter your message...')); ?>" required></textarea>
                        <small class="form-text text-muted">
                            <strong><?php echo esc_html(sc_t('dashboard_pages.available_variables', 'Available Variables')); ?>:</strong><br>
                            <code>{name}</code> - <?php echo esc_html(sc_t('dashboard_pages.attendee_name_var', 'Attendee Name')); ?> |
                            <code>{email}</code> - <?php echo esc_html(sc_t('dashboard_pages.attendee_email_var', 'Attendee Email')); ?> |
                            <code>{ticket_id}</code> - <?php echo esc_html(sc_t('dashboard_pages.ticket_id_var', 'Ticket ID')); ?><br>
                            <code>{event_title}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_title_var', 'Event Title')); ?> |
                            <code>{event_date}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_date_var', 'Event Date')); ?> |
                            <code>{event_location}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_location_var', 'Event Location')); ?><br>
                            <code>{qr}</code> - <?php echo esc_html(sc_t('dashboard_pages.qr_code_var', 'QR Code Image')); ?> |
                            <code>{download_link}</code> - <?php echo esc_html(sc_t('dashboard_pages.download_link_var', 'Ticket Download Button')); ?> |
                            <code>{my_account}</code> - <?php echo esc_html(sc_t('dashboard_pages.my_account_var', 'My Account Button')); ?>
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong><?php echo esc_html(sc_t('dashboard_pages.note', 'Note')); ?>:</strong> <?php echo esc_html(sc_t('dashboard_pages.email_batch_note', 'Emails will be sent in batches to ensure delivery. Please don\'t close this window during sending.')); ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-danger" id="send-bulk-email-btn">
                    <i class="fa fa-paper-plane"></i> <?php echo esc_html(sc_t('dashboard_pages.send_emails', 'Send Emails')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Single Email Modal -->
<div class="modal fade" id="singleEmailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-envelope"></i> <?php echo esc_html(sc_t('dashboard_pages.send_email_to', 'Send Email to')); ?> <span id="single-email-name"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="single-email-form">
                    <input type="hidden" id="single-email-attendee-id">
                    <input type="hidden" id="single-email-attendee-email">

                    <div class="form-group">
                        <label><?php echo esc_html(sc_t('dashboard_pages.recipient', 'Recipient')); ?>:</label>
                        <p class="form-control-plaintext"><strong id="single-email-recipient"></strong></p>
                    </div>

                    <div class="form-group">
                        <label for="single-email-subject"><?php echo esc_html(sc_t('dashboard_pages.email_subject', 'Email Subject')); ?>: <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="single-email-subject" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_email_subject', 'Enter email subject')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="single-email-message"><?php echo esc_html(sc_t('dashboard_pages.email_message', 'Email Message')); ?>: <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="single-email-message" rows="8" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_message', 'Enter your message...')); ?>" required></textarea>
                        <small class="form-text text-muted">
                            <strong><?php echo esc_html(sc_t('dashboard_pages.available_variables', 'Available Variables')); ?>:</strong><br>
                            <code>{name}</code> - <?php echo esc_html(sc_t('dashboard_pages.attendee_name_var', 'Attendee Name')); ?> |
                            <code>{email}</code> - <?php echo esc_html(sc_t('dashboard_pages.attendee_email_var', 'Attendee Email')); ?> |
                            <code>{ticket_id}</code> - <?php echo esc_html(sc_t('dashboard_pages.ticket_id_var', 'Ticket ID')); ?><br>
                            <code>{event_title}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_title_var', 'Event Title')); ?> |
                            <code>{event_date}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_date_var', 'Event Date')); ?> |
                            <code>{event_location}</code> - <?php echo esc_html(sc_t('dashboard_pages.event_location_var', 'Event Location')); ?><br>
                            <code>{qr}</code> - <?php echo esc_html(sc_t('dashboard_pages.qr_code_var', 'QR Code Image')); ?> |
                            <code>{download_link}</code> - <?php echo esc_html(sc_t('dashboard_pages.download_link_var', 'Ticket Download Button')); ?> |
                            <code>{my_account}</code> - <?php echo esc_html(sc_t('dashboard_pages.my_account_var', 'My Account Button')); ?>
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="send-single-email-btn">
                    <i class="fa fa-paper-plane"></i> <?php echo esc_html(sc_t('dashboard_pages.send_email', 'Send Email')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Email Progress Modal -->
<div class="modal fade" id="emailProgressModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-spinner fa-spin"></i> <?php echo esc_html(sc_t('dashboard_pages.sending_emails', 'Sending Emails...')); ?>
                </h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong><?php echo esc_html(sc_t('dashboard_pages.progress', 'Progress')); ?>:</strong> <span id="email-progress-text">0 / 0</span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                         id="email-progress-bar"
                         role="progressbar"
                         style="width: 0%"
                         aria-valuenow="0"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        0%
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted" id="email-current-status"><?php echo esc_html(sc_t('dashboard_pages.preparing_to_send', 'Preparing to send...')); ?></small>
                </div>
                <div class="mt-3" id="email-results" style="display: none;">
                    <div class="alert alert-success mb-2" id="email-success-count" style="display: none;">
                        <i class="fa fa-check-circle"></i> <strong><?php echo esc_html(sc_t('dashboard_pages.sent', 'Sent')); ?>:</strong> <span>0</span>
                    </div>
                    <div class="alert alert-danger mb-0" id="email-failed-count" style="display: none;">
                        <i class="fa fa-times-circle"></i> <strong><?php echo esc_html(sc_t('payments.failed', 'Failed')); ?>:</strong> <span>0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="email-progress-close-btn" style="display: none;"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-clock-o"></i> <?php echo esc_html(sc_t('dashboard_pages.attendance_details', 'Attendance Details')); ?> - <span id="attendance-attendee-name"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Loading State -->
                <div id="attendance-loading" class="text-center py-5">
                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                    <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading_attendance', 'Loading attendance details...')); ?></p>
                </div>

                <!-- No Tracking Message -->
                <div id="attendance-no-tracking" class="text-center py-5" style="display: none;">
                    <i class="fa fa-exclamation-triangle fa-3x text-warning"></i>
                    <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.attendance_not_enabled', 'Attendance tracking is not enabled for this event.')); ?></p>
                </div>

                <!-- Attendance Content -->
                <div id="attendance-content" style="display: none;">
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h3 id="summary-total-time">00:00</h3>
                                    <small><?php echo esc_html(sc_t('dashboard_pages.total_time', 'Total Time')); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3><span id="summary-days-attended">0</span> / <span id="summary-total-days">0</span></h3>
                                    <small><?php echo esc_html(sc_t('dashboard_pages.days_attended', 'Days Attended')); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h3 id="summary-missing-checkouts">0</h3>
                                    <small><?php echo esc_html(sc_t('dashboard_pages.missing_checkouts', 'Missing Check-outs')); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3 id="summary-event-name" style="font-size: 14px; margin: 0;">-</h3>
                                    <small><?php echo esc_html(sc_t('dashboard_pages.event', 'Event')); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Attendance Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="attendance-details-table">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.day', 'Day')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.sessions', 'Sessions')); ?></th>
                                    <th width="120"><?php echo esc_html(sc_t('dashboard_pages.total_time', 'Total Time')); ?></th>
                                    <th width="100"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                </tr>
                            </thead>
                            <tbody id="attendance-details-body">
                                <!-- Data will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Additional Filters Modal -->
<div class="modal fade" id="additionalFiltersModal" tabindex="-1" role="dialog" aria-labelledby="additionalFiltersModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="additionalFiltersModalLabel">
                    <i class="fa fa-sliders"></i> <?php echo esc_html(sc_t('dashboard_pages.additional_filters', 'Additional Filters')); ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="af-event-select" class="font-weight-bold">
                        <?php echo esc_html(sc_t('dashboard_pages.select_event_first', 'Select the event first')); ?>
                    </label>
                    <select class="form-control" id="af-event-select">
                        <option value="">— <?php echo esc_html(sc_t('dashboard_pages.select_event_first', 'Select the event first')); ?> —</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fa fa-spinner fa-spin" id="af-fields-loading" style="display:none; margin-left:8px;"></i>
                </div>
                <hr>
                <div id="af-fields-container">
                    <p class="text-muted text-center py-4">
                        <i class="fa fa-info-circle"></i>
                        <?php echo esc_html(sc_t('dashboard_pages.select_event_to_see_fields', 'Choose an event to see its extra fields.')); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-danger mr-auto" id="af-clear" style="display:none;">
                    <i class="fa fa-times"></i> <?php echo esc_html(sc_t('dashboard_pages.clear_filters', 'Clear Filters')); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="af-apply" disabled>
                    <i class="fa fa-check"></i> <?php echo esc_html(sc_t('dashboard_pages.apply', 'Apply')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* User Search Dropdown Styles */
#existing-user-section {
    position: relative;
    z-index: 100;
}
.user-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 99999;
    background: #fff;
    border: 2px solid #007bff;
    border-radius: 8px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    max-height: 280px;
    overflow-y: auto;
    width: 100%;
    display: none;
    margin-top: 5px;
}
.user-search-item {
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
    background: #fff;
}
.user-search-item:last-child {
    border-bottom: none;
}
.user-search-item:hover,
.user-search-item:focus {
    background: #e7f1ff;
}
.user-search-item strong {
    color: #333;
    display: block;
    margin-bottom: 3px;
}
.user-search-item small {
    color: #666;
}

/* User Selection Section - Fix overflow for dropdown */
div#user-selection-section .card.border-primary {
    overflow: visible !important;
}
div#user-selection-section .card-body {
    overflow: visible !important;
}
#attendeeModal .modal-body {
    overflow: visible;
}
#attendeeModal .modal-content {
    overflow: visible;
}

/* Form fields transition */
#attendee-form-fields {
    transition: opacity 0.2s ease, visibility 0.2s ease;
}

/* Attendance Details Modal Styles */
#attendanceDetailsModal .card {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
#attendanceDetailsModal .card h3 {
    margin-bottom: 5px;
    font-weight: bold;
}
#attendance-details-table .session-item {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 8px 12px;
    margin: 3px 0;
    display: inline-block;
    font-size: 13px;
}
#attendance-details-table .session-item .check-in {
    color: #28a745;
}
#attendance-details-table .session-item .check-out {
    color: #dc3545;
}
#attendance-details-table .session-item .duration {
    color: #6c757d;
    font-weight: bold;
}
#attendance-details-table .auto-checkout {
    background: #fff3cd;
    border: 1px dashed #ffc107;
}
#attendance-details-table .no-attendance {
    color: #999;
    font-style: italic;
}
.btn-attendance {
    background: #17a2b8;
    color: white;
}
.btn-attendance:hover {
    background: #138496;
    color: white;
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';

    let selectedAttendees = [];
    let currentPage = 1;
    let perPage = 20; // Reduced for better performance on slow servers
    let totalPages = 1;
    let searchTimeout;

    // Active additional (extra-field) filters, scoped to one event.
    let activeExtraFilters = { event_id: 0, filters: [] };

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

    // Load attendees with pagination
    function loadAttendees(page = 1) {
        currentPage = page;

        const filters = {
            action: 'sc_get_attendees_paginated',
            nonce: scDashboard.nonce,
            page: page,
            per_page: perPage,
            event_id: $('#filter-event').val(),
            status: $('#filter-status').val(),
            ticket_status: $('#filter-ticket-status').val(),
            payment_type: $('#filter-payment').val(),
            coupon_code: $('#filter-coupon').val(),
            search: $('#filter-search').val()
        };

        // Apply extra-field filters (scoped to their event).
        if (activeExtraFilters.filters.length && activeExtraFilters.event_id) {
            filters.event_id = activeExtraFilters.event_id;
            filters.extra_filters = JSON.stringify(activeExtraFilters.filters);
        }

        let retryCount = 0;
        const maxRetries = 2;

        function doRequest() {
            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: filters,
                timeout: 60000, // 60 second timeout
                beforeSend: function() {
                    const tbody = $('#attendees-table tbody');
                    const retryText = retryCount > 0 ? ` (Retry ${retryCount}/${maxRetries})` : '';
                    tbody.html('<tr><td colspan="15" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">Loading...' + retryText + '</p></td></tr>');
                }
            }).done(function(response) {
                if (response.success) {
                    renderAttendeesTable(response.data.attendees);
                    updatePagination(response.data.total, response.data.pages, response.data.current_page);
                } else {
                    handleError();
                }
            }).fail(function(xhr, status, error) {
                // Retry on 503 or timeout
                if ((xhr.status === 503 || status === 'timeout') && retryCount < maxRetries) {
                    retryCount++;
                    setTimeout(doRequest, 2000); // Wait 2 seconds before retry
                } else {
                    handleError();
                }
            }).always(function() {
                // Hide all loading indicators
                $('#filter-event-loading, #filter-status-loading, #filter-ticket-loading, #filter-payment-loading').hide();
            });
        }

        function handleError() {
            const tbody = $('#attendees-table tbody');
            tbody.html('<tr><td colspan="15" class="text-center py-4 text-danger">Error loading attendees. <button class="btn btn-sm btn-primary ml-2" onclick="loadAttendees(' + page + ')">Try Again</button></td></tr>');
        }

        doRequest();
    }

    function renderAttendeesTable(attendees) {
        const tbody = $('#attendees-table tbody');
        tbody.empty();

        if (!attendees || attendees.length === 0) {
            tbody.html('<tr><td colspan="15" class="text-center py-4">No attendees found.</td></tr>');
            $('#attendees-pagination-controls').hide();
            return;
        }

        attendees.forEach(function(attendee) {
            const statusBadge = getStatusBadge(attendee.status);
            const ticketStatusBadge = getTicketStatusBadge(attendee.ticket_status);
            const paymentBadge = getPaymentBadge(attendee.payment_type);
            const couponDisplay = attendee.coupon_used ? `<code class="text-primary">${escapeHtml(attendee.coupon_used)}</code>` : '<span class="text-muted">-</span>';

            // Attendance tracking columns (only show data if tracking is enabled for the event)
            let scanCountDisplay = '-';
            let lastCheckinDisplay = '-';
            let lastCheckoutDisplay = '-';

            if (attendee.tracking_enabled) {
                scanCountDisplay = attendee.scan_count > 0
                    ? `<span class="badge badge-info">${attendee.scan_count}</span>`
                    : '<span class="badge badge-secondary">0</span>';
                lastCheckinDisplay = attendee.last_checkin || '-';
                lastCheckoutDisplay = attendee.last_checkout || '-';
            }

            const row = `
                <tr>
                    <td><input type="checkbox" class="attendee-checkbox" value="${attendee.id}"></td>
                    <td><code>${escapeHtml(attendee.ticket_id)}</code></td>
                    <td><strong>${escapeHtml(attendee.name)}</strong></td>
                    <td>${escapeHtml(attendee.email)}</td>
                    <td>${escapeHtml(attendee.phone || '-')}</td>
                    <td>${attendee.event_deleted
                        ? '<span style="color:#e65100;">' + escapeHtml(attendee.event_title) + ' <span class="badge badge-danger" style="font-size:10px;vertical-align:middle;">Deleted</span></span>'
                        : escapeHtml(attendee.event_title)}</td>
                    <td>${escapeHtml(attendee.ticket_type || 'General')}</td>
                    <td>${statusBadge}</td>
                    <td>${ticketStatusBadge}</td>
                    <td>${scanCountDisplay}</td>
                    <td>${lastCheckinDisplay}</td>
                    <td>${lastCheckoutDisplay}</td>
                    <td>${couponDisplay}</td>
                    <td>${paymentBadge}</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info view-attendee" data-id="${attendee.id}" title="<?php echo esc_attr(sc_t('dashboard_pages.view', 'View')); ?>">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/attendee-edit'); ?>?id=${attendee.id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <a class="dropdown-item send-email-attendee" href="javascript:void(0);" data-id="${attendee.id}" data-name="${escapeHtml(attendee.name)}" data-email="${escapeHtml(attendee.email)}"><i class="fa fa-envelope mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.send_email', 'Send Email')); ?></a>
                                <a class="dropdown-item checkin-attendee" href="javascript:void(0);" data-id="${attendee.id}" ${attendee.ticket_status === 'used' ? 'style="opacity:0.5;pointer-events:none;"' : ''}><i class="fa fa-check text-success mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.check_in', 'Check-in')); ?></a>
                                <a class="dropdown-item attendance-details" href="javascript:void(0);" data-id="${attendee.id}" data-name="${escapeHtml(attendee.name)}" ${!attendee.tracking_enabled ? 'style="opacity:0.5;pointer-events:none;"' : ''}><i class="fa fa-clock-o mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.attendance_history', 'Attendance History')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-attendee" href="javascript:void(0);" data-id="${attendee.id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
                            </div>
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

        // Show pagination controls
        $('#attendees-pagination-controls').show();

        // Update showing info
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#attendees-showing-info').text('<?php echo esc_js(sc_t('dashboard_pages.showing', 'Showing')); ?> ' + start + '-' + end + ' <?php echo esc_js(sc_t('dashboard_pages.of', 'of')); ?> ' + total);

        // Build pagination buttons
        const paginationHtml = [];

        // Previous button
        paginationHtml.push(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}"><?php echo esc_js(sc_t('dashboard_pages.previous', 'Previous')); ?></a>
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
                <a class="page-link" href="#" data-page="${current + 1}"><?php echo esc_js(sc_t('dashboard_pages.next', 'Next')); ?></a>
            </li>
        `);

        $('#attendees-pagination').html(paginationHtml.join(''));
    }

    // Pagination click handler
    $(document).on('click', '#attendees-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadAttendees(page);
        }
    });

    function getStatusBadge(status) {
        const badges = {
            'success': '<span class="badge badge-success"><?php echo esc_js(sc_t('dashboard_pages.confirmed', 'Confirmed')); ?></span>',
            'pending': '<span class="badge badge-warning"><?php echo esc_js(sc_t('dashboard_pages.pending', 'Pending')); ?></span>',
            'failed': '<span class="badge badge-danger"><?php echo esc_js(sc_t('payments.failed', 'Failed')); ?></span>',
            'refunded': '<span class="badge badge-info"><?php echo esc_js(sc_t('payments.refunded', 'Refunded')); ?></span>',
            'cancelled': '<span class="badge badge-secondary"><?php echo esc_js(sc_t('general.cancelled', 'Cancelled')); ?></span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    function getTicketStatusBadge(status) {
        const badges = {
            'unused': '<span class="badge badge-secondary"><?php echo esc_js(sc_t('tickets.valid', 'Unused')); ?></span>',
            'used': '<span class="badge badge-success"><?php echo esc_js(sc_t('tickets.used', 'Used')); ?></span>'
        };
        return badges[status] || '<span class="badge badge-secondary">-</span>';
    }

    function getPaymentBadge(type) {
        const badges = {
            'paid': '<span class="badge badge-success"><?php echo esc_js(sc_t('general.paid', 'Paid')); ?></span>',
            'free': '<span class="badge badge-info"><?php echo esc_js(sc_t('general.free', 'Free')); ?></span>',
            'coupon': '<span class="badge badge-warning"><?php echo esc_js(sc_t('tickets.discount_code', 'Coupon')); ?></span>'
        };
        return badges[type] || '<span class="badge badge-secondary">-</span>';
    }

    // Filters change with loading indicator
    $('#filter-event').on('change', function() {
        // Extra filters belong to a specific event; a manual event change clears them.
        // (Programmatic .val() updates from "Apply" do not trigger this handler.)
        if (activeExtraFilters.filters.length) {
            clearExtraFilters();
        }
        $('#filter-event-loading').show();
        loadAttendees(1);
    });

    $('#filter-status').on('change', function() {
        $('#filter-status-loading').show();
        loadAttendees(1);
    });

    $('#filter-ticket-status').on('change', function() {
        $('#filter-ticket-loading').show();
        loadAttendees(1);
    });

    $('#filter-payment').on('change', function() {
        $('#filter-payment-loading').show();
        loadAttendees(1);
    });

    // Coupon filter with debounce
    let couponTimeout = null;
    $('#filter-coupon').on('keyup', function() {
        clearTimeout(couponTimeout);
        couponTimeout = setTimeout(function() {
            loadAttendees(1);
        }, 500);
    });

    $('#filter-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadAttendees(1);
        }, 500);
    });

    /* =========================================================
     * Additional (extra-field) filters
     * ========================================================= */
    const afAllLabel          = '<?php echo esc_js(sc_t('general.all', 'All')); ?>';
    const afCheckedLabel      = '<?php echo esc_js(sc_t('dashboard_pages.checked', 'Checked')); ?>';
    const afNotCheckedLabel   = '<?php echo esc_js(sc_t('dashboard_pages.not_checked', 'Not Checked')); ?>';
    const afContainsPlaceholder = '<?php echo esc_js(sc_t('dashboard_pages.contains_placeholder', 'Contains...')); ?>';
    const afChooseEventHtml   = '<p class="text-muted text-center py-4"><i class="fa fa-info-circle"></i> <?php echo esc_js(sc_t('dashboard_pages.select_event_to_see_fields', 'Choose an event to see its extra fields.')); ?></p>';
    const afNoFieldsHtml      = '<p class="text-muted text-center py-3"><i class="fa fa-info-circle"></i> <?php echo esc_js(sc_t('dashboard_pages.no_extra_fields', 'This event has no extra fields.')); ?></p>';
    const afErrorHtml         = '<p class="text-danger text-center py-3"><i class="fa fa-exclamation-triangle"></i> <?php echo esc_js(sc_t('dashboard_pages.error_loading_fields', 'Could not load extra fields.')); ?></p>';

    // Split a field's options (newline string, or array) into a clean list.
    function parseFieldOptions(options) {
        if (!options) return [];
        if (Array.isArray(options)) {
            return options.map(function(o) { return String(o).trim(); }).filter(Boolean);
        }
        return String(options).split(/\r?\n/).map(function(o) { return o.trim(); }).filter(Boolean);
    }

    // Render the extra-field inputs for the chosen event, by type.
    function renderExtraFilterFields(fields) {
        if (!fields || !fields.length) {
            $('#af-fields-container').html(afNoFieldsHtml);
            $('#af-apply').prop('disabled', true);
            return;
        }

        let html = '';
        fields.forEach(function(field, idx) {
            const label = field.label || '';
            const type  = field.type || 'text';
            const opts  = parseFieldOptions(field.options);
            const isCheckboxOptions = (type === 'checkbox' && opts.length > 0);

            html += '<div class="form-group row af-field' + (isCheckboxOptions ? ' af-checkbox-options' : '') + '"'
                 +  ' data-label="' + escapeHtml(label) + '" data-type="' + escapeHtml(type) + '">';
            html += '<label class="col-sm-4 col-form-label">' + escapeHtml(label) + '</label>';
            html += '<div class="col-sm-8">';

            if (type === 'select' || type === 'radio') {
                html += '<select class="form-control af-value"><option value="">' + escapeHtml(afAllLabel) + '</option>';
                opts.forEach(function(o) {
                    html += '<option value="' + escapeHtml(o) + '">' + escapeHtml(o) + '</option>';
                });
                html += '</select>';
            } else if (type === 'checkbox') {
                if (opts.length) {
                    opts.forEach(function(o, i) {
                        const id = 'af-chk-' + idx + '-' + i;
                        html += '<div class="form-check">'
                             +  '<input class="form-check-input af-check" type="checkbox" id="' + id + '" value="' + escapeHtml(o) + '">'
                             +  '<label class="form-check-label" for="' + id + '">' + escapeHtml(o) + '</label>'
                             +  '</div>';
                    });
                } else {
                    html += '<select class="form-control af-value">'
                         +  '<option value="">' + escapeHtml(afAllLabel) + '</option>'
                         +  '<option value="checked">' + escapeHtml(afCheckedLabel) + '</option>'
                         +  '<option value="notchecked">' + escapeHtml(afNotCheckedLabel) + '</option>'
                         +  '</select>';
                }
            } else {
                html += '<input type="text" class="form-control af-value" placeholder="' + escapeHtml(afContainsPlaceholder) + '">';
            }

            html += '</div></div>';
        });

        $('#af-fields-container').html(html);
        $('#af-apply').prop('disabled', false);
    }

    // Re-check / re-fill inputs from previously applied values (same event only).
    function restoreSavedExtraValues() {
        activeExtraFilters.filters.forEach(function(f) {
            const $field = $('#af-fields-container .af-field').filter(function() {
                return $(this).attr('data-label') === f.label;
            });
            if (!$field.length) return;

            if (Array.isArray(f.value)) {
                $field.find('.af-check').each(function() {
                    if (f.value.indexOf($(this).val()) !== -1) {
                        $(this).prop('checked', true);
                    }
                });
            } else {
                $field.find('.af-value').val(f.value);
            }
        });
    }

    // Fetch the event's extra-field definitions and render inputs.
    function loadExtraFilterFields(eventId) {
        if (!eventId) {
            $('#af-fields-container').html(afChooseEventHtml);
            $('#af-apply').prop('disabled', true);
            return;
        }

        $('#af-fields-loading').show();
        $('#af-fields-container').html('');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: { action: 'sc_get_event_extra_fields', nonce: scDashboard.nonce, event_id: eventId }
        }).done(function(resp) {
            if (resp && resp.success) {
                renderExtraFilterFields(resp.data.fields || []);
                if (String(activeExtraFilters.event_id) === String(eventId)) {
                    restoreSavedExtraValues();
                }
            } else {
                $('#af-fields-container').html(afErrorHtml);
                $('#af-apply').prop('disabled', true);
            }
        }).fail(function() {
            $('#af-fields-container').html(afErrorHtml);
            $('#af-apply').prop('disabled', true);
        }).always(function() {
            $('#af-fields-loading').hide();
        });
    }

    function updateExtraFilterBadge() {
        const n = activeExtraFilters.filters.length;
        if (n > 0) {
            $('#af-active-count').text(n).show();
            $('#additional-filters-btn').removeClass('btn-outline-secondary').addClass('btn-secondary');
        } else {
            $('#af-active-count').hide();
            $('#additional-filters-btn').removeClass('btn-secondary').addClass('btn-outline-secondary');
        }
        $('#af-clear').toggle(n > 0);
    }

    function clearExtraFilters() {
        activeExtraFilters = { event_id: 0, filters: [] };
        updateExtraFilterBadge();
    }

    // Open the modal, preselecting the active/current event.
    $('#additional-filters-btn').on('click', function() {
        const preselect = String(activeExtraFilters.event_id || $('#filter-event').val() || '');
        $('#af-event-select').val(preselect);
        $('#additionalFiltersModal').modal('show');
        loadExtraFilterFields(preselect);
    });

    // Event chosen inside the modal -> load its extra fields.
    $('#af-event-select').on('change', function() {
        loadExtraFilterFields($(this).val());
    });

    // Apply the chosen extra filters.
    $('#af-apply').on('click', function() {
        const eventId = $('#af-event-select').val();
        if (!eventId) { return; }

        const filters = [];
        $('#af-fields-container .af-field').each(function() {
            const $f    = $(this);
            const label = $f.attr('data-label');
            const type  = $f.attr('data-type');

            if ($f.hasClass('af-checkbox-options')) {
                const vals = [];
                $f.find('.af-check:checked').each(function() { vals.push($(this).val()); });
                if (vals.length) {
                    filters.push({ label: label, type: type, value: vals });
                }
            } else {
                const val = $f.find('.af-value').val();
                if (val !== '' && val != null) {
                    filters.push({ label: label, type: type, value: val });
                }
            }
        });

        activeExtraFilters = { event_id: eventId, filters: filters };

        // Sync the main event filter (without firing its change handler, which would clear us).
        $('#filter-event').val(eventId);

        updateExtraFilterBadge();
        $('#additionalFiltersModal').modal('hide');
        loadAttendees(1);
    });

    // Clear all extra filters.
    $('#af-clear').on('click', function() {
        clearExtraFilters();
        $('#af-event-select').val('');
        $('#af-fields-container').html(afChooseEventHtml);
        $('#af-apply').prop('disabled', true);
        $('#additionalFiltersModal').modal('hide');
        loadAttendees(1);
    });

    // Note: Create attendee button is now a link to attendee-add page

    // Toggle between existing user and new registration
    $('input[name="user_type"]').on('change', function() {
        const userType = $(this).val();
        if (userType === 'existing') {
            $('#existing-user-section').slideDown();
            initUserSearch();
        } else {
            $('#existing-user-section').slideUp();
            clearUserSelection();
            // Make sure form fields are visible for new registration
            $('#attendee-form-fields').css({'opacity': '1', 'visibility': 'visible', 'pointer-events': 'auto'});
        }
    });

    // Initialize user search with custom autocomplete (no Select2 dependency)
    let userSearchTimeout = null;
    let userSearchResults = [];

    function initUserSearch() {
        // Clear previous search
        $('#user-search').val('');
        $('#user-search-dropdown').hide().empty();
        userSearchResults = [];

        // Show/hide fields below search using opacity (keeps space reserved)
        function hideFieldsBelowSearch() {
            $('#attendee-form-fields').css({
                'opacity': '0',
                'visibility': 'hidden',
                'pointer-events': 'none'
            });
        }

        function showFieldsBelowSearch() {
            $('#attendee-form-fields').css({
                'opacity': '1',
                'visibility': 'visible',
                'pointer-events': 'auto'
            });
        }

        // On focus - hide fields below
        $('#user-search').off('focus').on('focus', function() {
            hideFieldsBelowSearch();
            if ($(this).val() && userSearchResults.length === 0) {
                $(this).trigger('input');
            }
        });

        // Search on input
        $('#user-search').off('input').on('input', function() {
            const searchTerm = $(this).val().trim();

            if (searchTerm.length < 2) {
                $('#user-search-dropdown').hide().empty();
                return;
            }

            clearTimeout(userSearchTimeout);
            userSearchTimeout = setTimeout(function() {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_search_users',
                        nonce: scDashboard.nonce,
                        search: searchTerm
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            userSearchResults = response.data;
                            let html = '';
                            response.data.forEach(function(user, index) {
                                html += `<div class="user-search-item" data-index="${index}">
                                    <strong>${escapeHtml(user.display_name)}</strong><br>
                                    <small class="text-muted">${escapeHtml(user.user_email)}</small>
                                    ${user.phone ? '<br><small class="text-muted"><i class="fa fa-phone"></i> ' + escapeHtml(user.phone) + '</small>' : ''}
                                </div>`;
                            });
                            $('#user-search-dropdown').html(html).show();
                        } else {
                            $('#user-search-dropdown').html('<div class="user-search-item text-muted">No users found</div>').show();
                        }
                    }
                });
            }, 300);
        });

        // Select user from dropdown
        $(document).off('click', '.user-search-item').on('click', '.user-search-item', function() {
            const index = $(this).data('index');
            if (index !== undefined && userSearchResults[index]) {
                const user = userSearchResults[index];
                $('#selected-user-id').val(user.ID);
                $('#user-search').val(user.display_name + ' (' + user.user_email + ')');
                $('#attendee-name').val(user.display_name).prop('readonly', true);
                $('#attendee-email').val(user.user_email).prop('readonly', true);

                // Parse phone - remove country code
                let phone = user.phone || '';
                phone = phone.replace(/^\+?\d{1,3}/, '').replace(/\D/g, '');
                $('#attendee-phone').val(phone).prop('readonly', true);

                $('#selected-user-display').html('<strong>' + escapeHtml(user.display_name) + '</strong> - ' + escapeHtml(user.user_email));
                $('#selected-user-info').slideDown();
                $('#user-search-dropdown').hide().empty();

                // Show fields again after selection
                showFieldsBelowSearch();
            }
        });

        // Hide dropdown on click outside and show fields
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#user-search, #user-search-dropdown').length) {
                $('#user-search-dropdown').hide();
                // Show fields if user clicked outside without selecting
                if ($('#selected-user-id').val() || $('input[name="user_type"]:checked').val() === 'new') {
                    showFieldsBelowSearch();
                } else if (!$('#user-search').is(':focus')) {
                    showFieldsBelowSearch();
                }
            }
        });

        // On blur - show fields if no dropdown visible
        $('#user-search').off('blur').on('blur', function() {
            setTimeout(function() {
                if (!$('#user-search-dropdown').is(':visible')) {
                    showFieldsBelowSearch();
                }
            }, 200);
        });
    }

    // Clear user selection
    function clearUserSelection() {
        $('#selected-user-id').val('');
        $('#user-search').val('');
        $('#attendee-name, #attendee-email, #attendee-phone').val('').prop('readonly', false);
        $('#selected-user-info').hide();
        $('#user-search-dropdown').hide().empty();
        userSearchResults = [];
        $('#attendee-form-fields').show(); // Make sure fields are visible
    }

    // Load tickets when event is selected
    $('#attendee-event').on('change', function() {
        const eventId = $(this).val();
        if (!eventId) {
            $('#attendee-ticket-name').html('<option value="">Select Event First</option>');
            $('#attendee-extra-fields-container').hide();
            return;
        }

        // Load tickets
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_tickets_by_event',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success && response.data.tickets) {
                let options = '<option value="">Select Ticket Name</option>';
                response.data.tickets.forEach(function(ticket) {
                    options += `<option value="${escapeHtml(ticket.name)}" data-price="${ticket.price}" data-slug="${escapeHtml(ticket.slug)}">${escapeHtml(ticket.name)} - ${ticket.price}</option>`;
                });
                $('#attendee-ticket-name').html(options);

                // If editing attendee, select the ticket
                if (window.editingAttendee && window.editingAttendee.ticket_name) {
                    $('#attendee-ticket-name').val(window.editingAttendee.ticket_name).trigger('change');
                    $('#attendee-ticket-slug').val(window.editingAttendee.ticket_slug || '');
                    delete window.editingAttendee; // Clear after use
                }
            }
        });

        // Load extra fields for the event
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_extra_fields',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success && response.data.fields && response.data.fields.length > 0) {
                buildExtraFieldsForm(response.data.fields);
                $('#attendee-extra-fields-container').show();
            } else {
                $('#attendee-extra-fields-container').hide();
            }
        });
    });

    // Auto-populate ticket price when ticket is selected
    $('#attendee-ticket-name').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const price = selectedOption.data('price') || '0';
        const slug = selectedOption.data('slug') || '';

        $('#attendee-ticket-price').val(price);
        $('#attendee-ticket-slug').val(slug);
    });

    // Function to build extra fields form
    function buildExtraFieldsForm(fields, existingData) {
        const container = $('#attendee-extra-fields-list');
        container.empty();

        fields.forEach(function(field) {
            if (!field.show_attendee_form) return;

            // Normalize field_type (handle both 'type' and 'field_type' properties)
            if (!field.field_type && field.type) {
                field.field_type = field.type;
            }

            const fieldValue = existingData && existingData[field.label] ? existingData[field.label] : '';

            let fieldHtml = '<div class="form-group">';
            fieldHtml += '<label for="extra_field_' + field.id + '">' + escapeHtml(field.label);
            if (field.required) {
                fieldHtml += ' <span class="text-danger">*</span>';
            }
            fieldHtml += '</label>';

            // Helper function to get options from field (handles different formats)
            function getFieldOptions(field) {
                // Check field_options first (Eventin format)
                if (field.field_options && Array.isArray(field.field_options)) {
                    return field.field_options.map(function(opt) { return opt.value || opt; });
                }
                // Check options property
                if (field.options) {
                    if (Array.isArray(field.options)) {
                        return field.options;
                    } else if (typeof field.options === 'string' && field.options.length > 0) {
                        // String format (newline or comma separated)
                        return field.options.split(/[\n,]+/).map(function(o) { return o.trim(); }).filter(function(o) { return o.length > 0; });
                    }
                }
                return [];
            }

            const fieldOptions = getFieldOptions(field);

            if (field.field_type === 'select') {
                fieldHtml += '<select class="form-control extra-field" id="extra_field_' + field.id + '" name="extra_fields[' + escapeHtml(field.label) + ']" ' + (field.required ? 'required' : '') + '>';
                fieldHtml += '<option value="">Select...</option>';
                fieldOptions.forEach(function(optValue) {
                    const selected = (fieldValue === optValue) ? 'selected' : '';
                    fieldHtml += '<option value="' + escapeHtml(optValue) + '" ' + selected + '>' + escapeHtml(optValue) + '</option>';
                });
                fieldHtml += '</select>';
            } else if (field.field_type === 'textarea') {
                fieldHtml += '<textarea class="form-control extra-field" id="extra_field_' + field.id + '" name="extra_fields[' + escapeHtml(field.label) + ']" rows="3" ' + (field.required ? 'required' : '') + '>' + escapeHtml(fieldValue) + '</textarea>';
            } else if (field.field_type === 'checkbox') {
                const checkedValues = fieldValue ? fieldValue.split(', ') : [];
                fieldOptions.forEach(function(optValue, idx) {
                    const checked = checkedValues.includes(optValue) ? 'checked' : '';
                    fieldHtml += '<div class="custom-control custom-checkbox">';
                    fieldHtml += '<input class="custom-control-input extra-field-checkbox" type="checkbox" id="extra_field_' + field.id + '_' + idx + '" name="extra_fields[' + escapeHtml(field.label) + '][]" value="' + escapeHtml(optValue) + '" ' + checked + '>';
                    fieldHtml += '<label class="custom-control-label" for="extra_field_' + field.id + '_' + idx + '">' + escapeHtml(optValue) + '</label>';
                    fieldHtml += '</div>';
                });
            } else if (field.field_type === 'radio') {
                fieldOptions.forEach(function(optValue, idx) {
                    const checked = (fieldValue === optValue) ? 'checked' : '';
                    fieldHtml += '<div class="custom-control custom-radio">';
                    fieldHtml += '<input class="custom-control-input extra-field" type="radio" id="extra_field_' + field.id + '_' + idx + '" name="extra_fields[' + escapeHtml(field.label) + ']" value="' + escapeHtml(optValue) + '" ' + checked + ' ' + (field.required ? 'required' : '') + '>';
                    fieldHtml += '<label class="custom-control-label" for="extra_field_' + field.id + '_' + idx + '">' + escapeHtml(optValue) + '</label>';
                    fieldHtml += '</div>';
                });
            } else {
                const inputType = field.field_type || 'text';
                fieldHtml += '<input type="' + inputType + '" class="form-control extra-field" id="extra_field_' + field.id + '" name="extra_fields[' + escapeHtml(field.label) + ']" value="' + escapeHtml(fieldValue) + '" ' + (field.required ? 'required' : '') + '>';
            }

            fieldHtml += '</div>';
            container.append(fieldHtml);
        });
    }

    // Save attendee
    $('#attendee-form').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'sc_save_attendee');
        formData.append('nonce', scDashboard.nonce);

        const btn = $('#save-attendee-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            if (response.success) {
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#attendeeModal').modal('hide');
                    } else {
                        $('#attendeeModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#attendeeModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                loadAttendees();
                showSuccess('Attendee saved successfully!');
            } else {
                showError(response.data.message || 'Error saving attendee');
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Attendee');
        });
    });

    // View attendee
    $(document).on('click', '.view-attendee', function() {
        const attendeeId = $(this).data('id');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_attendee',
                nonce: scDashboard.nonce,
                attendee_id: attendeeId
            }
        }).done(function(response) {
            if (response.success) {
                const a = response.data.attendee;

                // Generate QR code data
                const qrData = `ATTENDEE:${a.id}|TICKET:${escapeHtml(a.ticket_id)}|NAME:${escapeHtml(a.name)}|EMAIL:${escapeHtml(a.email)}|EVENT:${a.event_id}`;

                const html = `
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Ticket ID:</strong> <code>${escapeHtml(a.ticket_id)}</code></p>
                                    <p><strong>Name:</strong> ${escapeHtml(a.name)}</p>
                                    <p><strong>Email:</strong> ${escapeHtml(a.email)}</p>
                                    <p><strong>Phone:</strong> ${escapeHtml(a.phone || '-')}</p>
                                    <p><strong>Event:</strong> ${escapeHtml(a.event_title)}</p>
                                    <p><strong>Ticket Name:</strong> ${escapeHtml(a.ticket_name || 'General')}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> ${getStatusBadge(a.status)}</p>
                                    <p><strong>Ticket Status:</strong> ${getTicketStatusBadge(a.ticket_status)}</p>
                                    <p><strong>Payment Type:</strong> ${getPaymentBadge(a.payment_type)}</p>
                                    <p><strong>Coupon Used:</strong> ${a.coupon_used ? '<span class="badge badge-success">' + escapeHtml(a.coupon_used) + '</span>' : '-'}</p>
                                    <p><strong>Check-in Time:</strong> ${a.checkin_time || '-'}</p>
                                    <p><strong>Created:</strong> ${a.created_at || '-'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                                <h6 class="mb-3">Ticket QR Code</h6>
                                <img id="attendee-qr-image" src="" alt="QR Code" style="max-width: 200px; height: auto; margin: 0 auto; display: block; border: 1px solid #ccc; padding: 5px; background: white;">
                                <p class="mt-2 mb-0" style="font-size: 11px; color: #666;">Scan to verify ticket</p>
                            </div>
                        </div>
                    </div>
                    ${a.notes ? '<div class="mt-3"><strong>Notes:</strong><p>' + escapeHtml(a.notes) + '</p></div>' : ''}
                `;
                $('#attendee-details-content').html(html);

                // Show and setup print ticket button
                const token = a.token || '';
                const printUrl = '<?php echo esc_url(home_url('/ticket-view/')); ?>?attendee_id=' + a.id + '&token=' + token;
                $('#print-ticket-btn').attr('data-print-url', printUrl).show();

                // Generate QR Code using QRCode.toDataURL (same as Eventin Pro)
                setTimeout(function() {
                    if (typeof QRCode !== 'undefined' && QRCode.toDataURL) {
                        const qrImage = document.getElementById('attendee-qr-image');
                        const verifyUrl = scDashboard.dashboardUrl + 'attendees?action=verify&id=' + a.id + '&ticket=' + encodeURIComponent(a.ticket_id);

                        if (qrImage) {
                            QRCode.toDataURL(verifyUrl, function(err, url) {
                                if (!err && url) {
                                    qrImage.src = url;
                                } else {
                                    console.error('QR Code generation error:', err);
                                    $(qrImage).replaceWith('<div style="color: #999; padding: 20px;">Could not generate QR code</div>');
                                }
                            });
                        }
                    } else {
                        console.error('QRCode library not loaded');
                        const qrImage = document.getElementById('attendee-qr-image');
                        if (qrImage) {
                            $(qrImage).replaceWith('<div style="color: #999; padding: 20px;">QR Code library not available</div>');
                        }
                    }
                }, 100);

                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#viewAttendeeModal').modal('show');
                    } else {
                        $('#viewAttendeeModal').addClass('show').css('display', 'block');
                        $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                    }
                } catch (e) {
                    $('#viewAttendeeModal').addClass('show').css('display', 'block');
                    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                }
            }
        });
    });

    // Print ticket button handler
    $(document).on('click', '#print-ticket-btn', function() {
        const printUrl = $(this).attr('data-print-url');
        if (printUrl) {
            window.open(printUrl, '_blank');
        }
    });

    // Note: Edit attendee button is now a link to attendee-edit page

    // Check-in attendee
    $(document).on('click', '.checkin-attendee', function() {
        const attendeeId = $(this).data('id');
        const btn = $(this);

        showConfirm('Mark this attendee as checked-in?').then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_checkin_attendee',
                    nonce: scDashboard.nonce,
                    attendee_id: attendeeId
                }
            }).done(function(response) {
                if (response.success) {
                    loadAttendees();
                    showSuccess('Attendee checked-in successfully!');
                } else {
                    showError(response.data.message || 'Error checking-in attendee');
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Delete attendee
    $(document).on('click', '.delete-attendee', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const attendeeId = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_attendee',
                    nonce: scDashboard.nonce,
                    attendee_id: attendeeId
                }
            }).done(function(response) {
                if (response.success) {
                    loadAttendees();
                } else {
                    showError(response.data.message || 'Error deleting attendee');
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Select all
    $('#select-all').on('change', function() {
        $('.attendee-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });

    $(document).on('change', '.attendee-checkbox', function() {
        updateBulkButtons();
    });

    function updateBulkButtons() {
        const checked = $('.attendee-checkbox:checked').length;
        $('#bulk-delete-btn, #bulk-checkin-btn').prop('disabled', checked === 0);
    }

    // Bulk delete
    $('#bulk-delete-btn').on('click', function() {
        const ids = $('.attendee-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            return;
        }

        showConfirm(`Delete ${ids.length} attendee(s)?`).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_bulk_delete_attendees',
                    nonce: scDashboard.nonce,
                    attendee_ids: ids
                }
            }).done(function(response) {
                if (response.success) {
                    loadAttendees();
                    showSuccess(`${ids.length} attendee(s) deleted successfully!`);
                }
            });
        });
    });

    // Bulk check-in
    $('#bulk-checkin-btn').on('click', function() {
        const ids = $('.attendee-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            return;
        }

        showConfirm(`Check-in ${ids.length} attendee(s)?`).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_bulk_checkin_attendees',
                    nonce: scDashboard.nonce,
                    attendee_ids: ids
                }
            }).done(function(response) {
                if (response.success) {
                    loadAttendees();
                    showSuccess(`${ids.length} attendee(s) checked-in successfully!`);
                }
            });
        });
    });

    // Export CSV - show modal
    $('#export-attendees-btn').on('click', function() {
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#exportModal').modal('show');
            } else {
                $('#exportModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#exportModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Handle export option radio change
    $('input[name="export-option"]').on('change', function() {
        if ($(this).val() === 'event') {
            $('#export-event-select').show();
        } else {
            $('#export-event-select').hide();
        }
    });

    // Export confirm
    $('#export-confirm-btn').on('click', function() {
        const exportOption = $('input[name="export-option"]:checked').val();
        let eventId = '';

        if (exportOption === 'event') {
            eventId = $('#export-event-id').val();
            if (!eventId) {
                showError('Please select an event');
                return;
            }
        }

        const filters = {
            event_id: eventId,
            status: $('#filter-status').val(),
            ticket_status: $('#filter-ticket-status').val(),
            payment_type: $('#filter-payment').val(),
            search: $('#filter-search').val()
        };

        const queryString = $.param(filters);
        window.location.href = scDashboard.ajaxurl + '?action=sc_export_attendees_csv&nonce=' + scDashboard.nonce + '&' + queryString;

        // Close modal
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#exportModal').modal('hide');
            } else {
                $('#exportModal').removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        } catch (e) {
            $('#exportModal').removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    });

    // Import CSV - Open Modal
    $('#import-attendees-btn').on('click', function() {
        // Reset form
        $('#import-form')[0].reset();
        $('#csv-format-info').hide();
        $('#file-upload-section').hide();
        $('#import-results').hide();
        $('#import-submit-btn').prop('disabled', true);

        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#importModal').modal('show');
            } else {
                $('#importModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#importModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Import Event Selection - Load CSV Format
    $('#import-event-id').on('change', function() {
        const eventId = $(this).val();

        if (!eventId) {
            $('#csv-format-info').hide();
            $('#file-upload-section').hide();
            $('#create-users-section').hide();
            $('#import-submit-btn').prop('disabled', true);
            return;
        }

        // Get event extra fields
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_extra_fields',
                nonce: scDashboard.nonce,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success) {
                const fields = response.data.fields || [];

                // Build required columns list
                let columnsList = `
                    <ul class="list-unstyled mb-0" style="column-count: 2;">
                        <li><code>Name</code> <span class="text-danger">*</span></li>
                        <li><code>Email</code> <span class="text-danger">*</span></li>
                        <li><code>Phone</code></li>
                        <li><code>Ticket Name</code></li>
                        <li><code>Status</code> (approved/pending)</li>
                        <li><code>Ticket Status</code> (used/unused)</li>
                        <li><code>Payment Type</code> (free/coupon/woocommerce)</li>
                        <li><code>Coupon Code</code></li>
                    </ul>
                `;
                $('#csv-columns-list').html(columnsList);

                // Build extra fields list if exist
                if (fields.length > 0) {
                    let extraFieldsList = '<ul class="list-unstyled mb-0" style="column-count: 2;">';
                    fields.forEach(function(field) {
                        // Show all extra fields (show_attendee_form defaults to true if not set)
                        if (field.show_attendee_form !== false) {
                            const required = field.required ? ' <span class="text-danger">*</span>' : '';
                            extraFieldsList += `<li><code>${escapeHtml(field.label)}</code>${required}</li>`;
                        }
                    });
                    extraFieldsList += '</ul>';
                    $('#extra-fields-list').html(extraFieldsList);
                    $('#extra-fields-info').show();
                } else {
                    $('#extra-fields-info').hide();
                }

                // Store fields for template generation
                window.importEventExtraFields = fields;

                $('#csv-format-info').show();
                $('#file-upload-section').show();
                $('#create-users-section').show();
            }
        });
    });

    // Download CSV Template
    $('#download-template-btn').on('click', function() {
        const eventId = $('#import-event-id').val();
        if (!eventId) return;

        // Build CSV template
        // Payment Status: success or failed
        let headers = ['Name', 'Email', 'Phone', 'Ticket Name', 'Payment Status', 'Ticket Status', 'Payment Type', 'Coupon Code'];

        // Add extra fields
        if (window.importEventExtraFields) {
            window.importEventExtraFields.forEach(function(field) {
                // Include all extra fields (show_attendee_form defaults to true if not set)
                if (field.show_attendee_form !== false) {
                    headers.push(field.label);
                }
            });
        }

        // Create CSV content with proper escaping for Excel compatibility
        // Wrap headers in quotes and escape any internal quotes
        const escapeCSV = function(value) {
            if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                return '"' + value.replace(/"/g, '""') + '"';
            }
            return value;
        };

        // Tell Excel to use comma as separator (for all locales)
        let csvContent = 'sep=,\r\n';
        csvContent += headers.map(escapeCSV).join(',') + '\r\n';
        // Add example row with sample data (Payment Status: success or failed)
        let exampleRow = ['John Doe', 'john@example.com', '01234567890', 'General', 'success', 'unused', 'free', ''];
        // Add empty values for extra fields
        if (window.importEventExtraFields) {
            window.importEventExtraFields.forEach(function(field) {
                // Include all extra fields (show_attendee_form defaults to true if not set)
                if (field.show_attendee_form !== false) {
                    exampleRow.push('');
                }
            });
        }
        csvContent += exampleRow.map(escapeCSV).join(',') + '\r\n';

        // Add UTF-8 BOM for Excel compatibility
        const BOM = '\uFEFF';
        const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'attendees_template.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // File input label update
    $('#csv-file').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
        $('#import-submit-btn').prop('disabled', false);
    });

    // Import Form Submit
    $('#import-form').on('submit', function(e) {
        e.preventDefault();

        const eventId = $('#import-event-id').val();
        if (!eventId) {
            showError('Please select an event');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'sc_import_attendees_csv');
        formData.append('nonce', scDashboard.nonce);

        $('#import-progress').show();
        $('#import-submit-btn').prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            $('#import-progress').hide();
            if (response.success) {
                const results = response.data;

                // Build attendees info
                let attendeesInfo = `New Attendees: ${results.success_count}`;
                if (results.updated_count > 0) {
                    attendeesInfo += `<br>Updated Attendees: ${results.updated_count}`;
                }
                attendeesInfo += `<br>Failed: ${results.failed_count}`;

                // Build users info
                let usersInfo = '';
                if (results.users_created !== undefined) {
                    usersInfo = `<br><br><strong>Users:</strong><br>New Users: ${results.users_created}`;
                    if (results.users_skipped > 0) {
                        usersInfo += `<br>Skipped (existing): ${results.users_skipped}`;
                    }
                }

                $('#import-results').html(`
                    <div class="alert alert-success">
                        <strong>Import Complete!</strong><br><br>
                        <strong>Attendees:</strong><br>
                        ${attendeesInfo}${usersInfo}
                        ${results.errors.length > 0 ? '<br><br><strong>Errors:</strong><br>' + results.errors.join('<br>') : ''}
                    </div>
                `).show();
                loadAttendees();
            } else {
                $('#import-results').html(`<div class="alert alert-danger">${response.data.message || 'Import failed'}</div>`).show();
            }
        }).always(function() {
            $('#import-submit-btn').prop('disabled', false);
        });
    });

    // ============================================
    // EMAIL FUNCTIONALITY
    // ============================================

    // Show/hide event selector based on email target
    $('input[name="email-target"]').on('change', function() {
        if ($(this).val() === 'event') {
            $('#email-event-select').slideDown();
        } else {
            $('#email-event-select').slideUp();
        }
    });

    // Open bulk email modal
    $('#bulk-email-btn').on('click', function() {
        $('#bulkEmailModal').modal('show');
    });

    // Open single email modal
    $(document).on('click', '.send-email-attendee', function() {
        const attendeeId = $(this).data('id');
        const attendeeName = $(this).data('name');
        const attendeeEmail = $(this).data('email');

        $('#single-email-attendee-id').val(attendeeId);
        $('#single-email-attendee-email').val(attendeeEmail);
        $('#single-email-name').text(attendeeName);
        $('#single-email-recipient').text(attendeeName + ' (' + attendeeEmail + ')');

        $('#singleEmailModal').modal('show');
    });

    // Send bulk emails
    $('#send-bulk-email-btn').on('click', function() {
        const target = $('input[name="email-target"]:checked').val();
        const eventId = $('#email-event-id').val();
        const subject = $('#email-subject').val().trim();
        const message = $('#email-message').val().trim();

        // Validation
        if (!subject) {
            showDashboardAlert('error', 'Please enter email subject');
            return;
        }

        if (!message) {
            showDashboardAlert('error', 'Please enter email message');
            return;
        }

        if (target === 'event' && !eventId) {
            showDashboardAlert('error', 'Please select an event');
            return;
        }

        // Close bulk email modal
        $('#bulkEmailModal').modal('hide');

        // Start sending process
        sendBulkEmails(target, eventId, subject, message);
    });

    // Send single email
    $('#send-single-email-btn').on('click', function() {
        const attendeeId = $('#single-email-attendee-id').val();
        const subject = $('#single-email-subject').val().trim();
        const message = $('#single-email-message').val().trim();

        // Validation
        if (!subject) {
            showDashboardAlert('error', 'Please enter email subject');
            return;
        }

        if (!message) {
            showDashboardAlert('error', 'Please enter email message');
            return;
        }

        // Close single email modal
        $('#singleEmailModal').modal('hide');

        // Show progress modal
        $('#emailProgressModal').modal('show');
        updateEmailProgress(0, 1, 'Sending email...');

        // Send email
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_send_attendee_email',
                nonce: scDashboard.nonce,
                attendee_id: attendeeId,
                subject: subject,
                message: message
            }
        }).done(function(response) {
            if (response.success) {
                updateEmailProgress(1, 1, 'Email sent successfully!');
                showEmailResults(1, 0);
            } else {
                updateEmailProgress(1, 1, 'Failed to send email');
                showEmailResults(0, 1);
                showDashboardAlert('error', response.data.message || 'Failed to send email');
            }
        }).fail(function() {
            updateEmailProgress(1, 1, 'Connection error');
            showEmailResults(0, 1);
            showDashboardAlert('error', 'Connection error. Please try again.');
        });
    });

    // Bulk email sender with queue system
    function sendBulkEmails(target, eventId, subject, message) {
        // Show progress modal
        $('#emailProgressModal').modal('show');
        updateEmailProgress(0, 0, 'Fetching attendees...');

        // Get attendees list
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_bulk_email_attendees',
                nonce: scDashboard.nonce,
                target: target,
                event_id: eventId
            }
        }).done(function(response) {
            if (response.success && response.data.attendees.length > 0) {
                const attendees = response.data.attendees;
                const total = attendees.length;
                let sent = 0;
                let failed = 0;
                let current = 0;

                updateEmailProgress(0, total, `Sending to ${total} attendee(s)...`);

                // Send emails one by one with delay
                function sendNext() {
                    if (current >= total) {
                        // All done
                        updateEmailProgress(total, total, 'All emails processed!');
                        showEmailResults(sent, failed);
                        return;
                    }

                    const attendee = attendees[current];
                    updateEmailProgress(current, total, `Sending to ${attendee.name}...`);

                    $.ajax({
                        url: scDashboard.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sc_send_attendee_email',
                            nonce: scDashboard.nonce,
                            attendee_id: attendee.id,
                            subject: subject,
                            message: message
                        }
                    }).done(function(emailResponse) {
                        if (emailResponse.success) {
                            sent++;
                        } else {
                            failed++;
                        }
                    }).fail(function() {
                        failed++;
                    }).always(function() {
                        current++;
                        updateEmailProgress(current, total, `Sent ${current} of ${total}...`);
                        showEmailResults(sent, failed);

                        // Send next after 1 second delay (to avoid server overload)
                        setTimeout(sendNext, 1000);
                    });
                }

                // Start sending
                sendNext();
            } else {
                $('#emailProgressModal').modal('hide');
                showDashboardAlert('error', 'No attendees found for the selected criteria');
            }
        }).fail(function() {
            $('#emailProgressModal').modal('hide');
            showDashboardAlert('error', 'Failed to fetch attendees. Please try again.');
        });
    }

    // Update email progress
    function updateEmailProgress(current, total, status) {
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;

        $('#email-progress-text').text(current + ' / ' + total);
        $('#email-progress-bar').css('width', percent + '%')
                                .attr('aria-valuenow', percent)
                                .text(percent + '%');
        $('#email-current-status').text(status);
    }

    // Show email results
    function showEmailResults(sent, failed) {
        $('#email-results').show();

        if (sent > 0) {
            $('#email-success-count').show().find('span').text(sent);
        }

        if (failed > 0) {
            $('#email-failed-count').show().find('span').text(failed);
        }

        // Show close button
        $('#email-progress-close-btn').show();
    }

    // Close progress modal
    $('#email-progress-close-btn').on('click', function() {
        // Reset modal
        $('#email-results').hide();
        $('#email-success-count, #email-failed-count').hide().find('span').text('0');
        $('#email-progress-close-btn').hide();

        $('#emailProgressModal').modal('hide');

        // Clear forms
        $('#bulk-email-form')[0].reset();
        $('#single-email-form')[0].reset();
        $('#email-event-select').hide();
    });

    // ============================================
    // END EMAIL FUNCTIONALITY
    // ============================================

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

    // ============================================
    // ATTENDANCE DETAILS FUNCTIONALITY
    // ============================================

    // Open Attendance Details Modal
    $(document).on('click', '.attendance-details', function() {
        const attendeeId = $(this).data('id');
        const attendeeName = $(this).data('name');

        // Set attendee name in modal
        $('#attendance-attendee-name').text(attendeeName);

        // Show loading, hide content
        $('#attendance-loading').show();
        $('#attendance-no-tracking').hide();
        $('#attendance-content').hide();

        // Open modal
        $('#attendanceDetailsModal').modal('show');

        // Fetch attendance details
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_attendance_details',
                nonce: scDashboard.nonce,
                attendee_id: attendeeId
            }
        }).done(function(response) {
            $('#attendance-loading').hide();

            if (response.success) {
                const data = response.data;

                // Check if tracking is enabled
                if (!data.event.tracking_enabled) {
                    $('#attendance-no-tracking').show();
                    return;
                }

                // Show content
                $('#attendance-content').show();

                // Update summary cards
                $('#summary-total-time').text(data.summary.total_duration_formatted);
                $('#summary-days-attended').text(data.summary.days_attended);
                $('#summary-total-days').text(data.summary.total_event_days);
                $('#summary-missing-checkouts').text(data.summary.days_without_checkout);
                $('#summary-event-name').text(data.attendee.event_name);

                // Build daily attendance table
                const tbody = $('#attendance-details-body');
                tbody.empty();

                data.daily_attendance.forEach(function(day) {
                    let sessionsHtml = '';

                    if (day.sessions.length === 0) {
                        sessionsHtml = '<span class="no-attendance">No attendance recorded</span>';
                    } else {
                        day.sessions.forEach(function(session, idx) {
                            const autoClass = session.auto_checkout ? 'auto-checkout' : '';
                            sessionsHtml += `
                                <div class="session-item ${autoClass}">
                                    <span class="check-in"><i class="fa fa-sign-in"></i> ${session.check_in_time}</span>
                                    &nbsp;→&nbsp;
                                    <span class="check-out"><i class="fa fa-sign-out"></i> ${session.check_out_time || '-'}</span>
                                    &nbsp;|&nbsp;
                                    <span class="duration"><i class="fa fa-clock-o"></i> ${session.duration_formatted}</span>
                                </div>
                            `;
                        });
                    }

                    // Status badge
                    let statusBadge = '';
                    if (!day.has_attendance) {
                        statusBadge = '<span class="badge badge-secondary">Absent</span>';
                    } else if (day.missing_checkout) {
                        statusBadge = '<span class="badge badge-warning" title="Auto check-out applied">Auto Check-out</span>';
                    } else {
                        statusBadge = '<span class="badge badge-success">Complete</span>';
                    }

                    const row = `
                        <tr>
                            <td><strong>${day.date_formatted}</strong></td>
                            <td>${sessionsHtml}</td>
                            <td class="text-center"><strong>${day.total_duration_formatted}</strong></td>
                            <td class="text-center">${statusBadge}</td>
                        </tr>
                    `;
                    tbody.append(row);
                });

            } else {
                showDashboardAlert('error', response.data.message || 'Failed to load attendance details');
                $('#attendanceDetailsModal').modal('hide');
            }
        }).fail(function() {
            $('#attendance-loading').hide();
            showDashboardAlert('error', 'Connection error. Please try again.');
            $('#attendanceDetailsModal').modal('hide');
        });
    });

    // ============================================
    // END ATTENDANCE DETAILS FUNCTIONALITY
    // ============================================

    // Initial load
    loadAttendees(1);
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
