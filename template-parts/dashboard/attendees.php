<?php
/**
 * Attendees — the first page on the dashboard list pattern (wd-list.js).
 *
 * Rows, tab counts and the CSV export all come from the same filter set in
 * inc/admin-dashboard/attendees-query.php, so what you see is what you export.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list;
$load_wd_list = true;

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

$events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 1000,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

global $wpdb;
$workshops = $wpdb->get_results("SELECT id, event_id, title FROM {$wpdb->prefix}sc_workshops ORDER BY start_date ASC, title ASC");

$dashboard_url = home_url('/event-manager-dashboard/');

// JSON for inline <script>: hex-escape < > & ' " so no value can close the tag.
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$t = array(
    'title'            => sc_t('nav.attendees', 'Attendees'),
    'subtitle'         => sc_t('dashboard_pages.attendees_subtitle', 'Everyone registered for an event or workshop.'),
    'add'              => sc_t('dashboard_pages.add_attendee', 'Add attendee'),
    'import'           => sc_t('dashboard_pages.import', 'Import'),
    'export'           => sc_t('dashboard_pages.export', 'Export'),
    'send_email'       => sc_t('dashboard_pages.send_email', 'Send email'),
    'search'           => sc_t('dashboard_pages.search_attendees_placeholder', 'Search name, email, phone or ticket code'),
    'all_events'       => sc_t('dashboard_pages.all_events', 'All events'),
    'all_workshops'    => sc_t('dashboard_pages.all_workshops', 'Event and workshops'),
    'event_only'       => sc_t('dashboard_pages.event_only', 'Event only (no workshop)'),
    'any_payment'      => sc_t('dashboard_pages.any_payment', 'Any payment'),
    'free'             => sc_t('general.free', 'Free'),
    'coupon'           => sc_t('tickets.discount_code', 'Coupon'),
    'paid'             => sc_t('general.paid', 'Paid'),
    'more_filters'     => sc_t('dashboard_pages.more_filters', 'More filters'),
);
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($t['title']); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        <div class="w-page-head__actions">
            <button type="button" class="btn btn-secondary" id="bulk-email-btn"><i class="fa fa-envelope-o" aria-hidden="true"></i> <?php echo esc_html($t['send_email']); ?></button>
            <button type="button" class="btn btn-secondary" id="import-attendees-btn"><i class="fa fa-upload" aria-hidden="true"></i> <?php echo esc_html($t['import']); ?></button>
            <button type="button" class="btn btn-secondary" id="export-attendees-btn"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html($t['export']); ?></button>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'attendee-add'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="attendees-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>

        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>

            <select class="form-control" data-w-filter="event_id" id="filter-event" aria-label="<?php echo esc_attr($t['all_events']); ?>">
                <option value=""><?php echo esc_html($t['all_events']); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-control" data-w-filter="workshop_id" id="filter-workshop" aria-label="<?php echo esc_attr($t['all_workshops']); ?>" hidden>
                <option value=""><?php echo esc_html($t['all_workshops']); ?></option>
                <option value="none"><?php echo esc_html($t['event_only']); ?></option>
            </select>

            <select class="form-control" data-w-filter="payment_type" aria-label="<?php echo esc_attr($t['any_payment']); ?>">
                <option value=""><?php echo esc_html($t['any_payment']); ?></option>
                <option value="free"><?php echo esc_html($t['free']); ?></option>
                <option value="coupon"><?php echo esc_html($t['coupon']); ?></option>
                <option value="paid"><?php echo esc_html($t['paid']); ?></option>
            </select>

            <!-- Filters set from the "More filters" dialog -->
            <input type="hidden" data-w-filter="status" id="filter-status">
            <input type="hidden" data-w-filter="coupon_code" id="filter-coupon">

            <div class="w-toolbar__end">
                <button type="button" class="btn btn-secondary" id="additional-filters-btn">
                    <i class="fa fa-sliders" aria-hidden="true"></i> <?php echo esc_html($t['more_filters']); ?>
                    <span class="w-btn-badge" id="af-active-count" hidden>0</span>
                </button>
            </div>
        </div>

        <div class="w-chips" data-w-chips hidden></div>
        <div class="w-bulkbar" data-w-bulk hidden></div>

        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table>
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>

</div>
</div>

<!-- View attendee -->
<div class="modal fade" id="viewAttendeeModal" tabindex="-1" role="dialog" aria-labelledby="viewAttendeeTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAttendeeTitle"><?php echo esc_html(sc_t('dashboard_pages.attendee_details', 'Attendee details')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="attendee-details-content"></div>
            <div class="modal-footer">
                <a class="btn btn-secondary" id="view-edit-link" href="#"><i class="fa fa-pencil" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                <button type="button" class="btn btn-primary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Import CSV -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalTitle"><?php echo esc_html(sc_t('dashboard_pages.import_attendees_csv', 'Import attendees from CSV')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="import-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="import-event-id">1. <?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select event')); ?> <span class="text-danger">*</span></label>
                        <select class="form-control" id="import-event-id" name="event_id" required>
                            <option value="">— <?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select event')); ?> —</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="csv-format-info" hidden>
                        <div class="alert alert-secondary">
                            <strong><?php echo esc_html(sc_t('dashboard_pages.required_csv_columns', 'CSV columns')); ?></strong>
                            <div id="csv-columns-list" class="mt-2"></div>
                        </div>
                        <div id="extra-fields-info" hidden>
                            <div class="alert alert-secondary">
                                <strong><?php echo esc_html(sc_t('dashboard_pages.additional_info_columns', 'This event’s extra fields')); ?></strong>
                                <div id="extra-fields-list" class="mt-2"></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mb-3" id="download-template-btn"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.download_csv_template', 'Download CSV template')); ?></button>
                    </div>

                    <div class="form-group" id="file-upload-section" hidden>
                        <label for="csv-file">2. <?php echo esc_html(sc_t('dashboard_pages.upload_csv_file', 'CSV file')); ?> <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv-file" name="csv_file" accept=".csv" required>
                        <small class="form-text"><?php echo esc_html(sc_t('dashboard_pages.upload_csv_help', 'Up to 5 MB.')); ?></small>
                    </div>

                    <div class="form-group" id="create-users-section" hidden>
                        <label>3. <?php echo esc_html(sc_t('dashboard_pages.user_account_options', 'User accounts')); ?></label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="create-users" name="create_users" value="1">
                            <label class="custom-control-label" for="create-users"><?php echo esc_html(sc_t('dashboard_pages.create_wp_accounts', 'Create a site account for each attendee')); ?></label>
                        </div>
                        <small class="form-text"><?php echo esc_html(sc_t('dashboard_pages.create_accounts_help', 'Email becomes the username and the phone number (normalised to 01…) the password. Existing accounts with the same email are updated.')); ?></small>
                    </div>

                    <div id="import-progress" hidden>
                        <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
                        <p class="text-center mt-2 mb-0"><?php echo esc_html(sc_t('dashboard_pages.importing', 'Importing…')); ?></p>
                    </div>
                    <div id="import-results" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="import-submit-btn" disabled><i class="fa fa-upload" aria-hidden="true"></i> <?php echo esc_html($t['import']); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk email -->
<div class="modal fade" id="bulkEmailModal" tabindex="-1" role="dialog" aria-labelledby="bulkEmailTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkEmailTitle"><?php echo esc_html(sc_t('dashboard_pages.send_email_to_attendees', 'Send email to attendees')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="bulk-email-form">
                    <div class="form-group">
                        <label><?php echo esc_html(sc_t('dashboard_pages.send_to', 'Send to')); ?></label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="email-all" name="email-target" class="custom-control-input" value="all" checked>
                            <label class="custom-control-label" for="email-all"><?php echo esc_html(sc_t('dashboard_pages.all_attendees_all_events', 'All attendees of all events')); ?></label>
                        </div>
                        <div class="custom-control custom-radio mt-1">
                            <input type="radio" id="email-event" name="email-target" class="custom-control-input" value="event">
                            <label class="custom-control-label" for="email-event"><?php echo esc_html(sc_t('dashboard_pages.specific_event', 'One event')); ?></label>
                        </div>
                    </div>
                    <div class="form-group" id="email-event-select" hidden>
                        <label for="email-event-id"><?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select event')); ?></label>
                        <select class="form-control" id="email-event-id">
                            <option value="">— <?php echo esc_html(sc_t('dashboard_pages.select_event', 'Select event')); ?> —</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="email-subject"><?php echo esc_html(sc_t('dashboard_pages.email_subject', 'Subject')); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="email-subject" required>
                    </div>
                    <div class="form-group mb-2">
                        <label for="email-message"><?php echo esc_html(sc_t('dashboard_pages.email_message', 'Message')); ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="email-message" rows="8" required></textarea>
                        <small class="form-text"><?php echo esc_html(sc_t('dashboard_pages.available_variables', 'Variables')); ?>: <code>{name}</code> <code>{email}</code> <code>{ticket_id}</code> <code>{event_title}</code> <code>{event_date}</code> <code>{event_location}</code> <code>{qr}</code> <code>{download_link}</code> <code>{my_account}</code></small>
                    </div>
                    <div class="alert alert-warning mb-0" id="email-delivery-note"><?php echo esc_html(sc_t('dashboard_pages.email_batch_note', 'Emails go out one per second; keep this tab open until it finishes.')); ?></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="send-bulk-email-btn"><i class="fa fa-paper-plane" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.send_emails', 'Send emails')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Single email -->
<div class="modal fade" id="singleEmailModal" tabindex="-1" role="dialog" aria-labelledby="singleEmailTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="singleEmailTitle"><?php echo esc_html(sc_t('dashboard_pages.send_email_to', 'Send email to')); ?> <span id="single-email-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="single-email-form">
                    <input type="hidden" id="single-email-attendee-id">
                    <p class="mb-3"><span class="text-muted"><?php echo esc_html(sc_t('dashboard_pages.recipient', 'Recipient')); ?>:</span> <strong id="single-email-recipient" class="w-ltr"></strong></p>
                    <div class="form-group">
                        <label for="single-email-subject"><?php echo esc_html(sc_t('dashboard_pages.email_subject', 'Subject')); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="single-email-subject" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="single-email-message"><?php echo esc_html(sc_t('dashboard_pages.email_message', 'Message')); ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="single-email-message" rows="8" required></textarea>
                        <small class="form-text"><?php echo esc_html(sc_t('dashboard_pages.available_variables', 'Variables')); ?>: <code>{name}</code> <code>{email}</code> <code>{ticket_id}</code> <code>{event_title}</code> <code>{event_date}</code> <code>{event_location}</code> <code>{qr}</code> <code>{download_link}</code> <code>{my_account}</code></small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="send-single-email-btn"><i class="fa fa-paper-plane" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.send_email', 'Send email')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Email progress -->
<div class="modal fade" id="emailProgressModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="emailProgressTitle">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailProgressTitle"><?php echo esc_html(sc_t('dashboard_pages.sending_emails', 'Sending emails')); ?></h5>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="email-progress-text">0 / 0</strong> <span class="text-muted" id="email-current-status"></span></p>
                <div class="progress"><div class="progress-bar" id="email-progress-bar" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>
                <div class="mt-3" id="email-results" hidden>
                    <span class="w-tag w-tag--teal" id="email-success-count" hidden><?php echo esc_html(sc_t('dashboard_pages.sent', 'Sent')); ?>: <span>0</span></span>
                    <span class="w-tag w-tag--red" id="email-failed-count" hidden><?php echo esc_html(sc_t('payments.failed', 'Failed')); ?>: <span>0</span></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="email-progress-close-btn" hidden><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance history -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1" role="dialog" aria-labelledby="attendanceTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceTitle"><?php echo esc_html(sc_t('dashboard_pages.attendance_details', 'Attendance')); ?> · <span id="attendance-attendee-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="attendance-loading" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.loading_attendance', 'Loading attendance…')); ?></div>
                <div id="attendance-no-tracking" class="alert alert-secondary" hidden><?php echo esc_html(sc_t('dashboard_pages.attendance_not_enabled', 'Attendance tracking is off for this event, so only the first check-in is recorded.')); ?></div>
                <div id="attendance-content" hidden>
                    <div class="row mb-3">
                        <div class="col-6 col-md-3"><div class="card mb-2"><div class="card-body py-3"><div class="stat-label"><?php echo esc_html(sc_t('dashboard_pages.total_time', 'Total time')); ?></div><div class="stat-value" id="summary-total-time">00:00</div></div></div></div>
                        <div class="col-6 col-md-3"><div class="card mb-2"><div class="card-body py-3"><div class="stat-label"><?php echo esc_html(sc_t('dashboard_pages.days_attended', 'Days attended')); ?></div><div class="stat-value"><span id="summary-days-attended">0</span> / <span id="summary-total-days">0</span></div></div></div></div>
                        <div class="col-6 col-md-3"><div class="card mb-2"><div class="card-body py-3"><div class="stat-label"><?php echo esc_html(sc_t('dashboard_pages.missing_checkouts', 'Missing check-outs')); ?></div><div class="stat-value" id="summary-missing-checkouts">0</div></div></div></div>
                        <div class="col-6 col-md-3"><div class="card mb-2"><div class="card-body py-3"><div class="stat-label"><?php echo esc_html(sc_t('dashboard_pages.event', 'Event')); ?></div><div class="w-truncate font-weight-bold" id="summary-event-name">-</div></div></div></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr>
                                <th><?php echo esc_html(sc_t('dashboard_pages.day', 'Day')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.sessions', 'In → out')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.total_time', 'Total')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                            </tr></thead>
                            <tbody id="attendance-details-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- More filters: payment status, coupon, and the event's extra fields -->
<div class="modal fade" id="additionalFiltersModal" tabindex="-1" role="dialog" aria-labelledby="additionalFiltersTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="additionalFiltersTitle"><?php echo esc_html($t['more_filters']); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="af-status"><?php echo esc_html(sc_t('dashboard_pages.payment_status', 'Payment status')); ?></label>
                        <select class="form-control" id="af-status">
                            <option value=""><?php echo esc_html(sc_t('general.all', 'Any')); ?></option>
                            <option value="success"><?php echo esc_html(sc_t('dashboard_pages.confirmed', 'Confirmed')); ?></option>
                            <option value="pending"><?php echo esc_html(sc_t('dashboard_pages.pending', 'Pending')); ?></option>
                            <option value="failed"><?php echo esc_html(sc_t('payments.failed', 'Failed')); ?></option>
                            <option value="refunded"><?php echo esc_html(sc_t('payments.refunded', 'Refunded')); ?></option>
                            <option value="cancelled"><?php echo esc_html(sc_t('general.cancelled', 'Cancelled')); ?></option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="af-coupon"><?php echo esc_html(sc_t('dashboard_pages.coupon_code', 'Coupon code contains')); ?></label>
                        <input type="text" class="form-control w-ltr" id="af-coupon" autocomplete="off">
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <label for="af-event-select"><?php echo esc_html(sc_t('dashboard_pages.extra_fields_of', 'Registration answers for')); ?></label>
                    <select class="form-control" id="af-event-select">
                        <option value="">— <?php echo esc_html(sc_t('dashboard_pages.select_event_first', 'Choose an event')); ?> —</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="af-fields-container"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link mr-auto" id="af-clear"><?php echo esc_html(sc_t('dashboard_pages.clear_filters', 'Clear these filters')); ?></button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="af-apply"><?php echo esc_html(sc_t('dashboard_pages.apply', 'Apply')); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var ajaxurl = scDashboard.ajaxurl;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var workshops = <?php echo $js(array_map(function ($w) {
        return array('id' => (int) $w->id, 'event_id' => (int) $w->event_id, 'title' => $w->title);
    }, $workshops)); ?>;
    var eventTitles = <?php echo $js(array_reduce($events, function ($carry, $e) {
        $carry[(int) $e->id] = $e->title;
        return $carry;
    }, array())); ?>;
    var L = <?php echo $js(array(
        'all'            => sc_t('dashboard_pages.all', 'All'),
        'checkedIn'      => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'notCheckedIn'   => sc_t('dashboard_pages.not_checked_in', 'Not checked in'),
        'noCertificate'  => sc_t('dashboard_pages.checked_in_no_certificate', 'Checked in, no certificate'),
        'name'           => sc_t('general.name', 'Name'),
        'ticket'         => sc_t('dashboard_pages.ticket', 'Ticket'),
        'event'          => sc_t('events.event', 'Event'),
        'phone'          => sc_t('general.phone', 'Phone'),
        'checkin'        => sc_t('dashboard_pages.check_in', 'Check-in'),
        'certificate'    => sc_t('dashboard_pages.certificate', 'Certificate'),
        'payment'        => sc_t('payments.payment', 'Payment'),
        'registered'     => sc_t('dashboard_pages.registered', 'Registered'),
        'notYet'         => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'issued'         => sc_t('dashboard_pages.issued', 'Issued'),
        'scans'          => sc_t('scanner.scan_count', 'scans'),
        'free'           => sc_t('general.free', 'Free'),
        'coupon'         => sc_t('tickets.discount_code', 'Coupon'),
        'paid'           => sc_t('general.paid', 'Paid'),
        'deleted'        => sc_t('dashboard_pages.deleted_event', 'Event deleted'),
        'view'           => sc_t('dashboard_pages.view', 'View details'),
        'edit'           => sc_t('dashboard_pages.edit', 'Edit'),
        'doCheckin'      => sc_t('dashboard_pages.check_in', 'Check in'),
        'sendEmail'      => sc_t('dashboard_pages.send_email', 'Send email'),
        'attendance'     => sc_t('dashboard_pages.attendance_history', 'Attendance history'),
        'printTicket'    => sc_t('dashboard_pages.print_ticket', 'Open ticket'),
        'delete'         => sc_t('dashboard_pages.delete', 'Delete'),
        'bulkCheckin'    => sc_t('dashboard_pages.check_in', 'Check in'),
        'bulkDelete'     => sc_t('dashboard_pages.delete', 'Delete'),
        'search'         => sc_t('general.search', 'Search'),
        'workshop'       => sc_t('nav.workshops', 'Workshop'),
        'eventOnly'      => sc_t('dashboard_pages.event_only', 'Event only (no workshop)'),
        'status'         => sc_t('dashboard_pages.payment_status', 'Payment status'),
        'statuses'       => array(
            'success'   => sc_t('dashboard_pages.confirmed', 'Confirmed'),
            'pending'   => sc_t('dashboard_pages.pending', 'Pending'),
            'failed'    => sc_t('payments.failed', 'Failed'),
            'refunded'  => sc_t('payments.refunded', 'Refunded'),
            'cancelled' => sc_t('general.cancelled', 'Cancelled'),
        ),
        'absent'         => sc_t('dashboard_pages.absent', 'Absent'),
        'insideNow'      => sc_t('dashboard_pages.inside_now', 'Inside now'),
        'autoCheckout'   => sc_t('dashboard_pages.auto_checkout', 'Auto check-out'),
        'autoCheckoutTip'=> sc_t('dashboard_pages.auto_checkout_tip', 'No check-out scan; closed at the event’s end time'),
        'complete'       => sc_t('dashboard_pages.complete', 'Complete'),
        'checked'        => sc_t('dashboard_pages.checked', 'Checked'),
        'notChecked'     => sc_t('dashboard_pages.not_checked', 'Not checked'),
        'contains'       => sc_t('dashboard_pages.contains_placeholder', 'Contains…'),
        'notes'          => sc_t('dashboard_pages.notes', 'Notes'),
        'importDone'     => sc_t('dashboard_pages.import_complete', 'Import complete.'),
        'importNew'      => sc_t('dashboard_pages.import_new', 'New'),
        'importUpdated'  => sc_t('dashboard_pages.import_updated', 'Updated'),
        'importFailed'   => sc_t('dashboard_pages.import_failed', 'Failed'),
        'accounts'       => sc_t('dashboard_pages.accounts_created', 'Accounts created'),
        'skipped'        => sc_t('dashboard_pages.skipped', 'skipped'),
        'answers'        => sc_t('dashboard_pages.registration_answers', 'Registration answers'),
        'emptyText'      => sc_t('dashboard_pages.no_attendees_yet', 'No one has registered yet. Add someone by hand or import a CSV.'),
        'confirmCheckin' => sc_t('dashboard_pages.confirm_checkin', 'Mark this attendee as checked in?'),
        'confirmBulkIn'  => sc_t('dashboard_pages.confirm_bulk_checkin', 'Check in %d attendees?'),
        'confirmBulkDel' => sc_t('dashboard_pages.confirm_bulk_delete', 'Delete %d attendees? Their tickets, check-ins and certificates go too. This cannot be undone.'),
        'confirmDelete'  => sc_t('dashboard_pages.confirm_delete_attendee', 'Delete this attendee? Their ticket, check-ins and certificate go too. This cannot be undone.'),
        'confirmExport'  => sc_t('dashboard_pages.confirm_export', 'Export %s attendees matching the current view to CSV?'),
        'checkedInOk'    => sc_t('dashboard_pages.checked_in_ok', 'Checked in.'),
        'deletedOk'      => sc_t('dashboard_pages.deleted_ok', 'Deleted.'),
        'failed'         => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'noTracking'     => sc_t('dashboard_pages.tracking_off', 'Attendance tracking is off for this event'),
    )); ?>;

    function fmt(template, n) { return template.replace('%d', WDList.num(n)).replace('%s', WDList.num(n)); }
    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : '')).toUpperCase();
    }
    function dateOnly(value) {
        if (!value) { return ''; }
        var d = new Date(value.replace(' ', 'T'));
        return isNaN(d) ? value : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function timeOnly(value) {
        if (!value) { return ''; }
        var d = new Date(value.replace(' ', 'T'));
        return isNaN(d) ? value : d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }
    function ajax(data) {
        return $.ajax({ url: ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) });
    }

    /* ----------------------------------------------------------- extra filters */

    var extraFilters = { event_id: 0, filters: [] };

    /* ------------------------------------------------------------------ list */

    var ICON = {
        eye: 'M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        check: 'M20 6 9 17l-5-5',
        mail: 'M3 5h18v14H3zM3 7l9 6 9-6',
        clock: 'M12 7v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
        ticket: 'M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('attendees-list'),
        action: 'sc_get_attendees_paginated',
        filters: ['search', 'event_id', 'workshop_id', 'payment_type', 'status', 'coupon_code'],
        perPage: 25,
        defaultSort: { orderby: 'created_at', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, countKey: 'all' },
            { key: 'in', label: L.checkedIn, params: { ticket_status: 'used' }, countKey: 'checked_in' },
            { key: 'out', label: L.notCheckedIn, params: { ticket_status: 'unused' }, countKey: 'not_checked_in' },
            { key: 'nocert', label: L.noCertificate, params: { ticket_status: 'used', certificate: 'no' }, countKey: 'checked_in_no_certificate' }
        ],
        extraParams: function () {
            return extraFilters.filters.length ? { extra_filters: JSON.stringify(extraFilters.filters) } : {};
        },
        extraFilterCount: function () { return extraFilters.filters.length; },
        emptyText: L.emptyText,
        columns: [
            {
                label: L.name, sort: 'name',
                render: function (a) {
                    return '<div class="w-person"><span class="w-person__avatar" data-tone="' + (a.id % 4) + '" aria-hidden="true">' + esc(initials(a.name)) + '</span>' +
                        '<span class="w-person__text"><span class="w-person__name">' + esc(a.name) + '</span>' +
                        '<span class="w-sub w-ltr">' + esc(a.email) + '</span></span></div>';
                }
            },
            {
                label: L.ticket,
                render: function (a) {
                    return '<span class="w-mono w-ltr">' + esc(a.ticket_id) + '</span><span class="w-sub w-truncate">' + esc(a.ticket_name || '') + '</span>';
                }
            },
            {
                label: L.event,
                render: function (a) {
                    return '<span class="w-truncate">' + esc(a.event_title) + (a.event_deleted ? ' <span class="w-tag w-tag--red">' + esc(L.deleted) + '</span>' : '') + '</span>' +
                        (a.workshop_title ? '<span class="w-sub w-truncate">' + esc(a.workshop_title) + '</span>' : '');
                }
            },
            {
                label: L.phone, className: 'w-col-xl',
                render: function (a) { return a.phone ? '<span class="w-ltr">' + esc(a.phone) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.checkin, sort: 'checked_in_at',
                render: function (a) {
                    if (!a.checked_in) { return '<span class="w-state-dot w-state-dot--off">' + esc(L.notYet) + '</span>'; }
                    var sub = timeOnly(a.checkin_time);
                    if (a.tracking_enabled && a.scan_count) { sub += (sub ? ' · ' : '') + a.scan_count + ' ' + L.scans; }
                    return '<span class="w-state-dot w-state-dot--on">' + esc(L.checkedIn) + '</span>' + (sub ? '<span class="w-sub">' + esc(sub) + '</span>' : '');
                }
            },
            {
                label: L.certificate,
                render: function (a) { return a.has_certificate ? '<span class="w-tag w-tag--teal">' + esc(L.issued) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.payment,
                render: function (a) {
                    var tag = a.payment_type === 'free' ? '<span class="w-tag">' + esc(L.free) + '</span>'
                        : a.payment_type === 'coupon' ? '<span class="w-tag w-tag--gold">' + esc(L.coupon) + '</span>'
                        : '<span class="w-tag w-tag--primary">' + esc(L.paid) + '</span>';
                    var sub = a.coupon_used ? '<span class="w-sub w-mono w-ltr">' + esc(a.coupon_used) + '</span>' : '';
                    if (a.status && a.status !== 'success') { sub = '<span class="w-sub">' + esc(a.status) + '</span>' + sub; }
                    return tag + sub;
                }
            },
            {
                label: L.registered, sort: 'created_at',
                render: function (a) { return '<span class="w-ltr" title="' + esc(a.created_at) + '">' + esc(dateOnly(a.created_at)) + '</span>'; }
            }
        ],
        rowMenu: function (a) {
            return [
                { label: L.view, icon: ICON.eye, onSelect: function () { viewAttendee(a); } },
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'attendee-edit?id=' + a.id },
                { label: L.doCheckin, icon: ICON.check, disabled: a.checked_in, onSelect: function () { checkIn(a); } },
                { label: L.sendEmail, icon: ICON.mail, onSelect: function () { openSingleEmail(a); } },
                { label: L.attendance, icon: ICON.clock, disabled: !a.tracking_enabled && !a.checked_in, onSelect: function () { openAttendance(a); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { deleteAttendee(a); } }
            ];
        },
        bulkActions: [
            { key: 'checkin', label: L.bulkCheckin, icon: ICON.check, run: bulkCheckin },
            { key: 'delete', label: L.bulkDelete, icon: ICON.trash, danger: true, run: bulkDelete }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { clearExtra(); l.setFilters({ event_id: '', workshop_id: '' }); } }); }
            if (f.workshop_id) {
                var w = workshops.filter(function (x) { return String(x.id) === String(f.workshop_id); })[0];
                chips.push({ label: L.workshop, value: f.workshop_id === 'none' ? L.eventOnly : (w ? w.title : '#' + f.workshop_id), clear: function (l) { l.setFilter('workshop_id', ''); } });
            }
            if (f.payment_type) { chips.push({ label: L.payment, value: L[f.payment_type] || f.payment_type, clear: function (l) { l.setFilter('payment_type', ''); } }); }
            if (f.status) { chips.push({ label: L.status, value: L.statuses[f.status] || f.status, clear: function (l) { l.setFilter('status', ''); } }); }
            if (f.coupon_code) { chips.push({ label: L.coupon, value: f.coupon_code, clear: function (l) { l.setFilter('coupon_code', ''); } }); }
            extraFilters.filters.forEach(function (x, i) {
                chips.push({ label: x.label, value: Array.isArray(x.value) ? x.value.join(', ') : x.value, clear: function (l) {
                    extraFilters.filters.splice(i, 1);
                    updateMoreBadge();
                    l.reload();
                } });
            });
            return chips;
        },
        onFiltersChange: function (f) {
            // A workshop from another event no longer applies; drop it before the request goes out.
            if (!syncWorkshopFilter(f.event_id, f.workshop_id)) { delete f.workshop_id; }
            // Extra-field answers belong to one event.
            if (extraFilters.filters.length && String(f.event_id || '') !== String(extraFilters.event_id)) { clearExtra(); }
            updateMoreBadge(f);
        },
        onReset: function () { clearExtra(); }
    });

    /** Fill the workshop filter for the chosen event; false when the current value doesn't belong to it. */
    function syncWorkshopFilter(eventId, current) {
        var $sel = $('#filter-workshop');
        var own = workshops.filter(function (w) { return String(w.event_id) === String(eventId); });
        var valid = !current || (!!eventId && (current === 'none' || own.some(function (w) { return String(w.id) === String(current); })));
        $sel.find('option').slice(2).remove();
        own.forEach(function (w) { $sel.append($('<option>').val(w.id).text(w.title)); });
        $sel.prop('hidden', !eventId || !own.length);
        $sel.val(valid && current ? current : '');
        $sel.toggleClass('is-set', valid && !!current);
        return valid;
    }

    function clearExtra() {
        extraFilters = { event_id: 0, filters: [] };
        updateMoreBadge();
    }

    function updateMoreBadge(f) {
        f = f || list.state().filters;
        var n = extraFilters.filters.length + (f.status ? 1 : 0) + (f.coupon_code ? 1 : 0);
        $('#af-active-count').text(n).prop('hidden', n === 0);
    }

    /* --------------------------------------------------------------- actions */

    function viewAttendee(a) {
        ajax({ action: 'sc_get_attendee', attendee_id: a.id }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message ? res.data.message : L.failed); return; }
            var d = res.data.attendee;
            var extra = d.extra_fields;
            if (typeof extra === 'string') { try { extra = JSON.parse(extra); } catch (e) { extra = {}; } }
            if (Array.isArray(extra)) {
                extra = extra.reduce(function (m, x) { if (x && x.label) { m[x.label] = x.value; } return m; }, {});
            }
            var extraRows = Object.keys(extra || {}).filter(function (k) { return extra[k] !== '' && extra[k] !== null; }).map(function (k) {
                return '<tr><th scope="row">' + esc(k) + '</th><td>' + esc(Array.isArray(extra[k]) ? extra[k].join(', ') : extra[k]) + '</td></tr>';
            }).join('');
            var row = function (label, value) { return '<tr><th scope="row" class="text-muted font-weight-normal" style="width:38%">' + esc(label) + '</th><td>' + value + '</td></tr>'; };

            $('#attendee-details-content').html(
                '<div class="w-person mb-3"><span class="w-person__avatar" data-tone="' + (d.id % 4) + '" style="width:44px;height:44px;font-size:14px">' + esc(initials(d.name)) + '</span>' +
                '<span class="w-person__text"><span class="w-person__name" style="font-size:17px">' + esc(d.name) + '</span><span class="w-sub w-ltr">' + esc(d.email) + '</span></span></div>' +
                '<div class="row"><div class="col-md-6"><table class="table table-sm mb-3"><tbody>' +
                row(L.ticket, '<span class="w-mono w-ltr">' + esc(d.ticket_id) + '</span><span class="w-sub">' + esc(d.ticket_name || '') + '</span>') +
                row(L.event, esc(d.event_title)) +
                row(L.phone, d.phone ? '<span class="w-ltr">' + esc(d.phone) + '</span>' : '—') +
                row(L.registered, esc(d.created_at || '—')) +
                '</tbody></table></div><div class="col-md-6"><table class="table table-sm mb-3"><tbody>' +
                row(L.checkin, d.ticket_status === 'used' ? '<span class="w-state-dot w-state-dot--on">' + esc(L.checkedIn) + '</span><span class="w-sub">' + esc(d.checkin_time || '') + '</span>' : '<span class="w-state-dot w-state-dot--off">' + esc(L.notYet) + '</span>') +
                row(L.payment, esc(d.payment_type) + (d.coupon_used ? ' · <span class="w-mono w-ltr">' + esc(d.coupon_used) + '</span>' : '')) +
                row(L.status, esc(d.status || '')) +
                '</tbody></table></div></div>' +
                (extraRows ? '<h6 class="mb-2">' + esc(L.answers) + '</h6><table class="table table-sm mb-0"><tbody>' + extraRows + '</tbody></table>' : '') +
                (d.notes ? '<h6 class="mt-3 mb-1">' + esc(L.notes) + '</h6><p class="mb-0">' + esc(d.notes) + '</p>' : '')
            );
            $('#view-edit-link').attr('href', dashboardUrl + 'attendee-edit?id=' + d.id);
            $('#viewAttendeeModal').modal('show');
        }).fail(function () { showError(L.failed); });
    }

    function checkIn(a) {
        showConfirm(L.confirmCheckin).then(function (r) {
            if (!r.isConfirmed) { return; }
            ajax({ action: 'sc_checkin_attendee', attendee_id: a.id }).done(function (res) {
                if (res.success) { showSuccess(L.checkedInOk); list.reload(true); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }

    function deleteAttendee(a) {
        showDeleteConfirm(L.confirmDelete).then(function (r) {
            if (!r.isConfirmed) { return; }
            ajax({ action: 'sc_delete_attendee', attendee_id: a.id }).done(function (res) {
                if (res.success) { showSuccess(L.deletedOk); list.reload(); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }

    function bulkCheckin(ids) {
        showConfirm(fmt(L.confirmBulkIn, ids.length)).then(function (r) {
            if (!r.isConfirmed) { return; }
            ajax({ action: 'sc_bulk_checkin_attendees', attendee_ids: ids }).done(function (res) {
                if (res.success) { showSuccess(res.data && res.data.message ? res.data.message : L.checkedInOk); list.reload(); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }

    function bulkDelete(ids) {
        showDeleteConfirm(fmt(L.confirmBulkDel, ids.length)).then(function (r) {
            if (!r.isConfirmed) { return; }
            ajax({ action: 'sc_bulk_delete_attendees', attendee_ids: ids }).done(function (res) {
                if (res.success) { showSuccess(res.data && res.data.message ? res.data.message : L.deletedOk); list.reload(); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }

    /* ---------------------------------------------------------------- export */

    $('#export-attendees-btn').on('click', function () {
        var data = list.data();
        var total = data ? data.total : 0;
        showConfirm(fmt(L.confirmExport, total)).then(function (r) {
            if (!r.isConfirmed) { return; }
            var p = list.params();
            p.action = 'sc_export_attendees_csv';
            p.nonce = scDashboard.nonce;
            window.location.href = ajaxurl + '?' + $.param(p);
        });
    });

    /* ----------------------------------------------------------- more filters */

    var afHtml = {
        choose: '<p class="text-muted mb-0">' + esc(<?php echo $js(sc_t('dashboard_pages.select_event_to_see_fields', 'Choose an event to filter by its registration answers.')); ?>) + '</p>',
        none: '<p class="text-muted mb-0">' + esc(<?php echo $js(sc_t('dashboard_pages.no_extra_fields', 'This event has no extra registration fields.')); ?>) + '</p>',
        error: '<p class="text-danger mb-0">' + esc(<?php echo $js(sc_t('dashboard_pages.error_loading_fields', 'Could not load this event’s fields.')); ?>) + '</p>'
    };

    function parseOptions(options) {
        if (!options) { return []; }
        if (Array.isArray(options)) { return options.map(function (o) { return String(o.value || o).trim(); }).filter(Boolean); }
        return String(options).split(/\r?\n/).map(function (o) { return o.trim(); }).filter(Boolean);
    }

    function renderExtraFields(fields) {
        if (!fields || !fields.length) { $('#af-fields-container').html(afHtml.none); return; }
        var html = '';
        fields.forEach(function (field, idx) {
            var type = field.type || field.field_type || 'text';
            var opts = parseOptions(field.options || field.field_options);
            var multi = type === 'checkbox' && opts.length > 0;
            html += '<div class="form-group af-field' + (multi ? ' af-multi' : '') + '" data-label="' + esc(field.label) + '" data-type="' + esc(type) + '">';
            html += '<label>' + esc(field.label) + '</label>';
            if (type === 'select' || type === 'radio') {
                html += '<select class="form-control af-value"><option value="">' + esc(L.all) + '</option>' + opts.map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + '</option>'; }).join('') + '</select>';
            } else if (multi) {
                html += opts.map(function (o, i) {
                    return '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input af-check" id="af-' + idx + '-' + i + '" value="' + esc(o) + '"><label class="custom-control-label" for="af-' + idx + '-' + i + '">' + esc(o) + '</label></div>';
                }).join('');
            } else if (type === 'checkbox') {
                html += '<select class="form-control af-value"><option value="">' + esc(L.all) + '</option><option value="checked">' + esc(L.checked) + '</option><option value="notchecked">' + esc(L.notChecked) + '</option></select>';
            } else {
                html += '<input type="text" class="form-control af-value" placeholder="' + esc(L.contains) + '">';
            }
            html += '</div>';
        });
        $('#af-fields-container').html(html);
        // Restore values applied earlier for this event.
        if (String(extraFilters.event_id) === String($('#af-event-select').val())) {
            extraFilters.filters.forEach(function (x) {
                var $f = $('#af-fields-container .af-field').filter(function () { return $(this).attr('data-label') === x.label; });
                if (Array.isArray(x.value)) { $f.find('.af-check').each(function () { this.checked = x.value.indexOf(this.value) > -1; }); }
                else { $f.find('.af-value').val(x.value); }
            });
        }
    }

    function loadExtraFields(eventId) {
        if (!eventId) { $('#af-fields-container').html(afHtml.choose); return; }
        $('#af-fields-container').html('<p class="text-muted mb-0"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></p>');
        ajax({ action: 'sc_get_event_extra_fields', event_id: eventId })
            .done(function (res) { if (res && res.success) { renderExtraFields(res.data.fields || []); } else { $('#af-fields-container').html(afHtml.error); } })
            .fail(function () { $('#af-fields-container').html(afHtml.error); });
    }

    $('#additional-filters-btn').on('click', function () {
        var f = list.state().filters;
        $('#af-status').val(f.status || '');
        $('#af-coupon').val(f.coupon_code || '');
        var ev = String(extraFilters.event_id || f.event_id || '');
        $('#af-event-select').val(ev);
        loadExtraFields(ev);
        $('#additionalFiltersModal').modal('show');
    });
    $('#af-event-select').on('change', function () { loadExtraFields(this.value); });

    $('#af-apply').on('click', function () {
        var eventId = $('#af-event-select').val();
        var filters = [];
        $('#af-fields-container .af-field').each(function () {
            var $f = $(this), label = $f.attr('data-label'), type = $f.attr('data-type');
            if ($f.hasClass('af-multi')) {
                var vals = $f.find('.af-check:checked').map(function () { return this.value; }).get();
                if (vals.length) { filters.push({ label: label, type: type, value: vals }); }
            } else {
                var v = $f.find('.af-value').val();
                if (v) { filters.push({ label: label, type: type, value: v }); }
            }
        });
        $('#filter-status').val($('#af-status').val());
        $('#filter-coupon').val($.trim($('#af-coupon').val()));
        $('#additionalFiltersModal').modal('hide');

        extraFilters = filters.length && eventId ? { event_id: eventId, filters: filters } : { event_id: 0, filters: [] };
        var current = list.state().filters;
        if (extraFilters.filters.length && String(current.event_id || '') !== String(eventId)) {
            // Answers belong to one event: switch the event filter to it.
            $('#filter-event').val(eventId);
        }
        // One request with the event, status, coupon and answers together.
        list.applyControls();
        updateMoreBadge();
    });

    $('#af-clear').on('click', function () {
        clearExtra();
        $('#additionalFiltersModal').modal('hide');
        list.setFilters({ status: '', coupon_code: '' });
    });

    /* ---------------------------------------------------------------- import */

    $('#import-attendees-btn').on('click', function () {
        $('#import-form')[0].reset();
        $('#csv-format-info, #file-upload-section, #create-users-section, #import-results, #import-progress').prop('hidden', true);
        $('#import-submit-btn').prop('disabled', true);
        $('#importModal').modal('show');
    });

    $('#import-event-id').on('change', function () {
        var eventId = this.value;
        $('#csv-format-info, #file-upload-section, #create-users-section').prop('hidden', !eventId);
        $('#import-submit-btn').prop('disabled', true);
        if (!eventId) { return; }
        ajax({ action: 'sc_get_event_extra_fields', event_id: eventId }).done(function (res) {
            if (!res.success) { return; }
            var fields = (res.data.fields || []).filter(function (f) { return f.show_attendee_form !== false; });
            var cols = ['Name *', 'Email *', 'Phone', 'Ticket Name', 'Payment Status (success / failed)', 'Ticket Status (used / unused)', 'Payment Type (free / coupon)', 'Coupon Code'];
            $('#csv-columns-list').html(cols.map(function (c) { return '<code class="mr-2">' + esc(c) + '</code>'; }).join(''));
            $('#extra-fields-list').html(fields.map(function (f) { return '<code class="mr-2">' + esc(f.label) + (f.required ? ' *' : '') + '</code>'; }).join(''));
            $('#extra-fields-info').prop('hidden', !fields.length);
            window.importEventExtraFields = fields;
        });
    });

    $('#download-template-btn').on('click', function () {
        var headers = ['Name', 'Email', 'Phone', 'Ticket Name', 'Payment Status', 'Ticket Status', 'Payment Type', 'Coupon Code'];
        var example = ['John Doe', 'john@example.com', '01234567890', 'General', 'success', 'unused', 'free', ''];
        (window.importEventExtraFields || []).forEach(function (f) { headers.push(f.label); example.push(''); });
        var cell = function (v) { v = String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        var csv = 'sep=,\r\n' + headers.map(cell).join(',') + '\r\n' + example.map(cell).join(',') + '\r\n';
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' }));
        link.download = 'attendees_template.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
    });

    $('#csv-file').on('change', function () { $('#import-submit-btn').prop('disabled', !this.files.length); });

    $('#import-form').on('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action', 'sc_import_attendees_csv');
        fd.append('nonce', scDashboard.nonce);
        $('#import-progress').prop('hidden', false);
        $('#import-results').prop('hidden', true);
        $('#import-submit-btn').prop('disabled', true);
        $.ajax({ url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false }).done(function (res) {
            if (res.success) {
                var r = res.data;
                var lines = [L.importNew + ': ' + r.success_count, L.importUpdated + ': ' + (r.updated_count || 0), L.importFailed + ': ' + r.failed_count];
                if (r.users_created !== undefined) { lines.push(L.accounts + ': ' + r.users_created + (r.users_skipped ? ', ' + L.skipped + ': ' + r.users_skipped : '')); }
                $('#import-results').html('<div class="alert alert-success mb-0"><strong>' + esc(L.importDone) + '</strong><br>' + lines.map(esc).join('<br>') +
                    (r.errors && r.errors.length ? '<hr class="my-2">' + r.errors.map(esc).join('<br>') : '') + '</div>').prop('hidden', false);
                list.reload();
            } else {
                $('#import-results').html('<div class="alert alert-danger mb-0">' + esc(res.data && res.data.message ? res.data.message : L.failed) + '</div>').prop('hidden', false);
            }
        }).fail(function () {
            $('#import-results').html('<div class="alert alert-danger mb-0">' + esc(L.failed) + '</div>').prop('hidden', false);
        }).always(function () {
            $('#import-progress').prop('hidden', true);
            $('#import-submit-btn').prop('disabled', false);
        });
    });

    /* ----------------------------------------------------------------- email */

    $('input[name="email-target"]').on('change', function () { $('#email-event-select').prop('hidden', this.value !== 'event'); });

    $('#bulk-email-btn').on('click', function () {
        var ev = list.state().filters.event_id;
        if (ev) { $('#email-event').prop('checked', true); $('#email-event-id').val(ev); $('#email-event-select').prop('hidden', false); }
        $('#bulkEmailModal').modal('show');
    });

    function openSingleEmail(a) {
        $('#single-email-attendee-id').val(a.id);
        $('#single-email-name').text(a.name);
        $('#single-email-recipient').text(a.name + ' <' + a.email + '>');
        $('#singleEmailModal').modal('show');
    }

    function progress(current, total, status) {
        var pct = total ? Math.round(current / total * 100) : 0;
        $('#email-progress-text').text(current + ' / ' + total);
        $('#email-progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
        $('#email-current-status').text(status || '');
    }
    function results(sent, failed) {
        $('#email-results').prop('hidden', false);
        $('#email-success-count').prop('hidden', !sent).find('span').text(sent);
        $('#email-failed-count').prop('hidden', !failed).find('span').text(failed);
    }
    function finished() { $('#email-progress-close-btn').prop('hidden', false); }

    $('#send-single-email-btn').on('click', function () {
        var subject = $.trim($('#single-email-subject').val()), message = $.trim($('#single-email-message').val());
        if (!subject || !message) { showError(<?php echo $js(sc_t('validation.fill_required', 'Enter a subject and a message.')); ?>); return; }
        $('#singleEmailModal').modal('hide');
        $('#emailProgressModal').modal('show');
        progress(0, 1, '');
        ajax({ action: 'sc_send_attendee_email', attendee_id: $('#single-email-attendee-id').val(), subject: subject, message: message })
            .done(function (res) { progress(1, 1, ''); results(res.success ? 1 : 0, res.success ? 0 : 1); if (!res.success) { showError(res.data && res.data.message ? res.data.message : L.failed); } })
            .fail(function () { progress(1, 1, ''); results(0, 1); })
            .always(finished);
    });

    $('#send-bulk-email-btn').on('click', function () {
        var target = $('input[name="email-target"]:checked').val();
        var eventId = $('#email-event-id').val();
        var subject = $.trim($('#email-subject').val()), message = $.trim($('#email-message').val());
        if (!subject || !message || (target === 'event' && !eventId)) { showError(<?php echo $js(sc_t('validation.fill_required', 'Enter a subject and a message, and pick the event.')); ?>); return; }
        $('#bulkEmailModal').modal('hide');
        $('#emailProgressModal').modal('show');
        progress(0, 0, '');
        ajax({ action: 'sc_get_bulk_email_attendees', target: target, event_id: eventId }).done(function (res) {
            var people = res.success ? res.data.attendees : [];
            if (!people.length) { $('#emailProgressModal').modal('hide'); showError(<?php echo $js(sc_t('dashboard_pages.no_recipients', 'No attendees match.')); ?>); return; }
            var i = 0, sent = 0, failed = 0;
            (function next() {
                if (i >= people.length) { progress(people.length, people.length, ''); results(sent, failed); finished(); return; }
                progress(i, people.length, people[i].name);
                ajax({ action: 'sc_send_attendee_email', attendee_id: people[i].id, subject: subject, message: message })
                    .done(function (r) { if (r.success) { sent++; } else { failed++; } })
                    .fail(function () { failed++; })
                    .always(function () { i++; results(sent, failed); setTimeout(next, 1000); });
            })();
        }).fail(function () { $('#emailProgressModal').modal('hide'); showError(L.failed); });
    });

    $('#email-progress-close-btn').on('click', function () {
        $('#emailProgressModal').modal('hide');
        $('#email-results, #email-progress-close-btn').prop('hidden', true);
        $('#bulk-email-form')[0].reset();
        $('#single-email-form')[0].reset();
        $('#email-event-select').prop('hidden', true);
    });

    /* ------------------------------------------------------------ attendance */

    function openAttendance(a) {
        $('#attendance-attendee-name').text(a.name);
        $('#attendance-loading').prop('hidden', false);
        $('#attendance-no-tracking, #attendance-content').prop('hidden', true);
        $('#attendanceDetailsModal').modal('show');

        ajax({ action: 'sc_get_attendance_details', attendee_id: a.id }).done(function (res) {
            $('#attendance-loading').prop('hidden', true);
            if (!res.success) { $('#attendanceDetailsModal').modal('hide'); showError(res.data && res.data.message ? res.data.message : L.failed); return; }
            var d = res.data;
            $('#attendance-no-tracking').prop('hidden', d.event.tracking_enabled);
            $('#attendance-content').prop('hidden', false);
            $('#summary-total-time').text(d.summary.total_duration_formatted);
            $('#summary-days-attended').text(d.summary.days_attended);
            $('#summary-total-days').text(d.summary.total_event_days);
            $('#summary-missing-checkouts').text(d.summary.days_without_checkout);
            $('#summary-event-name').text(d.attendee.event_name);
            $('#attendance-details-body').html(d.daily_attendance.map(function (day) {
                var sessions = day.sessions.length
                    ? day.sessions.map(function (s) { return '<span class="d-block w-ltr">' + esc(s.check_in_time) + ' → ' + esc(s.check_out_time || '—') + ' <span class="text-muted">(' + esc(s.duration_formatted) + ')</span></span>'; }).join('')
                    : '<span class="text-muted">—</span>';
                var status = !day.has_attendance ? '<span class="w-tag">' + esc(L.absent) + '</span>'
                    : day.still_checked_in ? '<span class="w-tag w-tag--teal">' + esc(L.insideNow) + '</span>'
                    : day.missing_checkout ? '<span class="w-tag w-tag--gold" title="' + esc(L.autoCheckoutTip) + '">' + esc(L.autoCheckout) + '</span>'
                    : '<span class="w-tag w-tag--teal">' + esc(L.complete) + '</span>';
                return '<tr><td>' + esc(day.date_formatted) + '</td><td>' + sessions + '</td><td class="w-ltr">' + esc(day.total_duration_formatted) + '</td><td>' + status + '</td></tr>';
            }).join(''));
        }).fail(function () {
            $('#attendanceDetailsModal').modal('hide');
            showError(L.failed);
        });
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
