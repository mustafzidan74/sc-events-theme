<?php
/**
 * Company Attendees Management Page
 *
 * @package sc_events
 * @version 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.company_attendees', 'Company Attendees');

// Translations
$t = array(
    'company_attendees' => sc_t('dashboard_pages.company_attendees', 'Company Attendees'),
    'register_company' => sc_t('dashboard_pages.register_company', 'Register Company'),
    'export_csv' => sc_t('dashboard_pages.export_csv', 'Export CSV'),
    'total_companies' => sc_t('dashboard_pages.total_companies', 'Total Companies'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'not_checked_in' => sc_t('dashboard_pages.not_checked_in', 'Not Checked In'),
    'revenue' => sc_t('dashboard_pages.revenue', 'Revenue'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'payment' => sc_t('dashboard_pages.payment', 'Payment'),
    'all' => sc_t('dashboard_pages.all', 'All'),
    'paid' => sc_t('dashboard_pages.paid', 'Paid'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'failed' => sc_t('dashboard_pages.failed', 'Failed'),
    'check_in' => sc_t('dashboard_pages.check_in', 'Check-in'),
    'company' => sc_t('dashboard_pages.company', 'Company'),
    'email_phone' => sc_t('dashboard_pages.email_phone', 'Email / Phone'),
    'booth' => sc_t('dashboard_pages.booth', 'Booth'),
    'sponsorship' => sc_t('dashboard_pages.sponsorship', 'Sponsorship'),
    'registered' => sc_t('dashboard_pages.registered', 'Registered'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading_companies' => sc_t('dashboard_pages.loading_companies', 'Loading companies...'),
    'company_details' => sc_t('dashboard_pages.company_details', 'Company Details'),
    'close' => sc_t('dashboard_pages.close', 'Close'),
    'edit' => sc_t('dashboard_pages.edit', 'Edit'),
    'export_companies' => sc_t('dashboard_pages.export_companies', 'Export Companies'),
    'choose_export' => sc_t('dashboard_pages.choose_export', 'Choose what to export:'),
    'export_all_companies' => sc_t('dashboard_pages.export_all_companies', 'Export All Companies'),
    'export_all_desc' => sc_t('dashboard_pages.export_all_desc', 'Export all company attendees from all events'),
    'export_by_event' => sc_t('dashboard_pages.export_by_event', 'Export by Event'),
    'export_by_event_desc' => sc_t('dashboard_pages.export_by_event_desc', 'Export companies for a specific event'),
    'select_event' => sc_t('dashboard_pages.select_event', 'Select Event'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'search_placeholder' => sc_t('dashboard_pages.search_company_placeholder', 'Search company, contact, email, code...'),
    'showing' => sc_t('dashboard_pages.showing', 'Showing'),
    'of' => sc_t('dashboard_pages.of', 'of'),
    'companies' => sc_t('dashboard_pages.companies', 'companies'),
    'no_companies' => sc_t('dashboard_pages.no_companies_found', 'No companies found'),
    'error_loading' => sc_t('dashboard_pages.error_loading_companies', 'Error loading companies'),
    'confirm_delete' => sc_t('dashboard_pages.confirm_delete_company', 'Are you sure you want to delete this company?'),
    'exporting' => sc_t('dashboard_pages.exporting', 'Exporting...'),
    'select_event_required' => sc_t('dashboard_pages.select_event_required', 'Please select an event'),
    'export_failed' => sc_t('dashboard_pages.export_failed', 'Export failed'),
    'website' => sc_t('dashboard_pages.website', 'Website'),
    'location' => sc_t('dashboard_pages.location', 'Location'),
    'already_checked_in' => sc_t('dashboard_pages.already_checked_in', 'Already Checked In'),
    'error_checking_in' => sc_t('dashboard_pages.error_checking_in', 'Error checking in'),
    'error_deleting_company' => sc_t('dashboard_pages.error_deleting_company', 'Error deleting company'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'phone' => sc_t('dashboard_pages.phone', 'Phone'),
    'booth_number' => sc_t('dashboard_pages.booth_number', 'Booth Number'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get all events for filter dropdown
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
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['company_attendees']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['company_attendees']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/company-attendee-add'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo $t['register_company']; ?>
                        </a>
                        <button type="button" class="btn btn-warning" id="export-companies-btn">
                            <i class="fa fa-download"></i> <?php echo $t['export_csv']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" id="company-stats-row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $t['total_companies']; ?></h5>
                    <h2 id="stat-total">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $t['checked_in']; ?></h5>
                    <h2 id="stat-checked-in">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $t['not_checked_in']; ?></h5>
                    <h2 id="stat-not-checked-in">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $t['revenue']; ?></h5>
                    <h2 id="stat-revenue">0</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form id="company-filters" class="form-inline">
                        <div class="form-group mr-3 mb-2">
                            <label for="filter-event" class="mr-2"><?php echo $t['event']; ?>:</label>
                            <select class="form-control" id="filter-event" name="event_id">
                                <option value=""><?php echo $t['all_events']; ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-payment-status" class="mr-2"><?php echo $t['payment']; ?>:</label>
                            <select class="form-control" id="filter-payment-status" name="payment_status">
                                <option value=""><?php echo $t['all']; ?></option>
                                <option value="success"><?php echo $t['paid']; ?></option>
                                <option value="pending"><?php echo $t['pending']; ?></option>
                                <option value="failed"><?php echo $t['failed']; ?></option>
                            </select>
                        </div>

                        <div class="form-group mr-3 mb-2">
                            <label for="filter-checked-in" class="mr-2"><?php echo $t['check_in']; ?>:</label>
                            <select class="form-control" id="filter-checked-in" name="checked_in">
                                <option value=""><?php echo $t['all']; ?></option>
                                <option value="1"><?php echo $t['checked_in']; ?></option>
                                <option value="0"><?php echo $t['not_checked_in']; ?></option>
                            </select>
                        </div>

                        <div class="form-group mb-2">
                            <input type="text" class="form-control" id="filter-search" placeholder="<?php echo esc_attr($t['search_placeholder']); ?>" style="width: 300px;">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Companies List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="companies-table">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="select-all"></th>
                                    <th><?php echo $t['company']; ?></th>
                                    <th><?php echo $t['email_phone']; ?></th>
                                    <th><?php echo $t['booth']; ?></th>
                                    <th><?php echo $t['sponsorship']; ?></th>
                                    <th><?php echo $t['payment']; ?></th>
                                    <th><?php echo $t['check_in']; ?></th>
                                    <th><?php echo $t['registered']; ?></th>
                                    <th><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody id="companies-tbody">
                                <tr id="loading-row">
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                                        <p class="mt-2 mb-0"><?php echo $t['loading_companies']; ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div id="pagination-info"><?php echo $t['showing']; ?> 0-0 <?php echo $t['of']; ?> 0 <?php echo $t['companies']; ?></div>
                        <nav>
                            <ul class="pagination mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Company Quick View Modal -->
<div class="modal fade" id="company-view-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $t['company_details']; ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="company-view-content">
                <div class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['close']; ?></button>
                <button type="button" class="btn btn-success" id="modal-checkin-btn">
                    <i class="fa fa-check"></i> <?php echo $t['check_in']; ?>
                </button>
                <a href="#" class="btn btn-primary" id="modal-edit-btn">
                    <i class="fa fa-edit"></i> <?php echo $t['edit']; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Export Options Modal -->
<div class="modal fade" id="export-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-download text-warning"></i> <?php echo $t['export_companies']; ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p><?php echo $t['choose_export']; ?></p>
                <div class="form-group">
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="export-all" name="export_option" value="all" class="custom-control-input" checked>
                        <label class="custom-control-label" for="export-all">
                            <strong><?php echo $t['export_all_companies']; ?></strong>
                            <br><small class="text-muted"><?php echo $t['export_all_desc']; ?></small>
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="export-event" name="export_option" value="event" class="custom-control-input">
                        <label class="custom-control-label" for="export-event">
                            <strong><?php echo $t['export_by_event']; ?></strong>
                            <br><small class="text-muted"><?php echo $t['export_by_event_desc']; ?></small>
                        </label>
                    </div>
                </div>
                <div class="form-group" id="export-event-select-container" style="display: none;">
                    <label for="export-event-select"><?php echo $t['select_event']; ?>:</label>
                    <select class="form-control" id="export-event-select">
                        <option value="">-- <?php echo $t['select_event']; ?> --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['cancel']; ?></button>
                <button type="button" class="btn btn-warning" id="confirm-export-btn">
                    <i class="fa fa-download"></i> <?php echo $t['export_csv']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.company-logo-small {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 5px;
    background: #f8f9fa;
}
.company-logo-placeholder {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    border-radius: 5px;
    color: #6c757d;
}
.badge-checked-in {
    background: #28a745;
    color: white;
}
.badge-not-checked {
    background: #dc3545;
    color: white;
}
</style>

<script>
// Company Translations
var companyTranslations = {
    no_companies: '<?php echo esc_js($t['no_companies']); ?>',
    error_loading: '<?php echo esc_js($t['error_loading']); ?>',
    showing: '<?php echo esc_js($t['showing']); ?>',
    of: '<?php echo esc_js($t['of']); ?>',
    companies: '<?php echo esc_js($t['companies']); ?>',
    checked_in: '<?php echo esc_js($t['checked_in']); ?>',
    not_checked_in: '<?php echo esc_js($t['not_checked_in']); ?>',
    paid: '<?php echo esc_js($t['paid']); ?>',
    pending: '<?php echo esc_js($t['pending']); ?>',
    failed: '<?php echo esc_js($t['failed']); ?>',
    confirm_delete: '<?php echo esc_js($t['confirm_delete']); ?>',
    exporting: '<?php echo esc_js($t['exporting']); ?>',
    select_event_required: '<?php echo esc_js($t['select_event_required']); ?>',
    export_failed: '<?php echo esc_js($t['export_failed']); ?>',
    check_in: '<?php echo esc_js($t['check_in']); ?>',
    checking_in: '<?php echo esc_js(sc_t('dashboard_pages.checking_in', 'Checking in...')); ?>',
    already_checked_in: '<?php echo esc_js($t['already_checked_in']); ?>',
    error_checking_in: '<?php echo esc_js($t['error_checking_in']); ?>',
    error_deleting_company: '<?php echo esc_js($t['error_deleting_company']); ?>',
    email: '<?php echo esc_js($t['email']); ?>',
    phone: '<?php echo esc_js($t['phone']); ?>',
    website: '<?php echo esc_js($t['website']); ?>',
    booth_number: '<?php echo esc_js($t['booth_number']); ?>',
    sponsorship: '<?php echo esc_js($t['sponsorship']); ?>',
    location: '<?php echo esc_js($t['location']); ?>'
};

// Define AJAX variables
var sc_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('sc_dashboard_nonce'); ?>',
    dashboard_url: '<?php echo home_url('/event-manager-dashboard/'); ?>'
};

jQuery(document).ready(function($) {
    var currentPage = 1;
    var perPage = 20;
    var currentEventId = '';

    // Load companies on page load
    loadCompanies();

    // Filter change handlers
    $('#filter-event, #filter-payment-status, #filter-checked-in').on('change', function() {
        currentPage = 1;
        loadCompanies();
        if ($('#filter-event').val()) {
            loadStats($('#filter-event').val());
        }
    });

    // Search with debounce
    var searchTimeout;
    $('#filter-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            currentPage = 1;
            loadCompanies();
        }, 300);
    });

    // Load companies function
    function loadCompanies() {
        var eventId = $('#filter-event').val();

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_get_company_attendees',
                nonce: sc_ajax.nonce,
                event_id: eventId,
                payment_status: $('#filter-payment-status').val(),
                checked_in: $('#filter-checked-in').val(),
                search: $('#filter-search').val(),
                page: currentPage,
                per_page: perPage
            },
            beforeSend: function() {
                $('#companies-tbody').html('<tr><td colspan="9" class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i></td></tr>');
            },
            success: function(response) {
                if (response.success) {
                    renderCompanies(response.data.companies);
                    renderPagination(response.data.total, response.data.pages, response.data.current_page);
                    // Update stats from the response
                    if (response.data.stats) {
                        $('#stat-total').text(response.data.stats.total);
                        $('#stat-checked-in').text(response.data.stats.checked_in);
                        $('#stat-not-checked-in').text(response.data.stats.not_checked_in);
                        $('#stat-revenue').text(response.data.stats.revenue);
                    }
                } else {
                    $('#companies-tbody').html('<tr><td colspan="9" class="text-center py-4 text-danger">' + (response.data.message || companyTranslations.error_loading) + '</td></tr>');
                }
            },
            error: function() {
                $('#companies-tbody').html('<tr><td colspan="9" class="text-center py-4 text-danger">' + companyTranslations.error_loading + '</td></tr>');
            }
        });
    }

    // Render companies table
    function renderCompanies(companies) {
        if (!companies || companies.length === 0) {
            $('#companies-tbody').html('<tr><td colspan="9" class="text-center py-4"><i class="fa fa-building fa-3x text-muted mb-3"></i><p class="mb-0">' + companyTranslations.no_companies + '</p></td></tr>');
            return;
        }

        var html = '';
        companies.forEach(function(company) {
            var logoHtml = company.logo_url
                ? '<img src="' + company.logo_url + '" class="company-logo-small">'
                : '<div class="company-logo-placeholder"><i class="fa fa-building"></i></div>';

            var checkedInBadge = company.checked_in
                ? '<span class="badge badge-checked-in"><i class="fa fa-check"></i> ' + companyTranslations.checked_in + '</span><br><small>' + company.checked_in_at + '</small>'
                : '<span class="badge badge-not-checked">' + companyTranslations.not_checked_in + '</span>';

            var paymentBadge = '';
            switch (company.payment_status) {
                case 'success': paymentBadge = '<span class="badge badge-success">' + companyTranslations.paid + '</span>'; break;
                case 'pending': paymentBadge = '<span class="badge badge-warning">' + companyTranslations.pending + '</span>'; break;
                case 'failed': paymentBadge = '<span class="badge badge-danger">' + companyTranslations.failed + '</span>'; break;
                default: paymentBadge = '<span class="badge badge-secondary">' + company.payment_status_label + '</span>';
            }

            html += '<tr data-id="' + company.id + '">';
            html += '<td><input type="checkbox" class="company-checkbox" value="' + company.id + '"></td>';
            html += '<td><div class="d-flex align-items-center">' + logoHtml + '<div class="ml-2"><strong>' + company.company_name + '</strong>';
            if (company.company_name_ar) html += '<br><small class="text-muted">' + company.company_name_ar + '</small>';
            html += '<br><code>' + company.company_code + '</code></div></div></td>';
            html += '<td><a href="mailto:' + company.contact_email + '">' + company.contact_email + '</a>';
            if (company.contact_phone) html += '<br>' + company.contact_phone;
            html += '</td>';
            html += '<td>' + (company.booth_number || '-') + '</td>';
            html += '<td>' + (company.sponsorship_level || '-') + '</td>';
            html += '<td>' + paymentBadge + '<br><small>' + company.amount_paid + '</small></td>';
            html += '<td>' + checkedInBadge + '</td>';
            html += '<td>' + company.created_at + '</td>';
            html += '<td>';
            html += '<div class="btn-group">';
            html += '<button class="btn btn-sm btn-info view-company-btn" data-id="' + company.id + '" title="View"><i class="fa fa-eye"></i></button>';
            html += '<button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="sr-only">Toggle Dropdown</span></button>';
            html += '<div class="dropdown-menu dropdown-menu-right">';
            html += '<a class="dropdown-item" href="' + sc_ajax.dashboard_url + 'company-attendee-edit?id=' + company.id + '"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('general.edit', 'Edit')); ?></a>';
            if (!company.checked_in) {
                html += '<a class="dropdown-item checkin-btn" href="javascript:void(0);" data-id="' + company.id + '"><i class="fa fa-check text-success mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.check_in', 'Check In')); ?></a>';
            }
            html += '<a class="dropdown-item send-company-qr-btn" href="javascript:void(0);" data-id="' + company.id + '"><i class="fa fa-envelope text-primary mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.send_qr', 'Send QR Email')); ?></a>';
            html += '<div class="dropdown-divider"></div>';
            html += '<a class="dropdown-item text-danger delete-company-btn" href="javascript:void(0);" data-id="' + company.id + '"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('general.delete', 'Delete')); ?></a>';
            html += '</div></div>';
            html += '</td>';
            html += '</tr>';
        });

        $('#companies-tbody').html(html);
    }

    // Render pagination
    function renderPagination(total, pages, current) {
        var start = ((current - 1) * perPage) + 1;
        var end = Math.min(current * perPage, total);
        $('#pagination-info').text(companyTranslations.showing + ' ' + start + '-' + end + ' ' + companyTranslations.of + ' ' + total + ' ' + companyTranslations.companies);

        if (pages <= 1) {
            $('#pagination').html('');
            return;
        }

        var html = '';
        html += '<li class="page-item ' + (current === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current - 1) + '">&laquo;</a></li>';

        for (var i = 1; i <= pages; i++) {
            if (i === 1 || i === pages || (i >= current - 2 && i <= current + 2)) {
                html += '<li class="page-item ' + (i === current ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
            } else if (i === current - 3 || i === current + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        html += '<li class="page-item ' + (current === pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current + 1) + '">&raquo;</a></li>';

        $('#pagination').html(html);
    }

    // Pagination click
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page && page > 0) {
            currentPage = page;
            loadCompanies();
        }
    });

    // Load stats
    function loadStats(eventId) {
        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_get_company_stats',
                nonce: sc_ajax.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    $('#stat-total').text(response.data.stats.total);
                    $('#stat-checked-in').text(response.data.stats.checked_in);
                    $('#stat-not-checked-in').text(response.data.stats.not_checked_in);
                    $('#stat-revenue').text(response.data.stats.revenue);
                }
            }
        });
    }

    // View company
    $(document).on('click', '.view-company-btn', function() {
        var id = $(this).data('id');
        $('#company-view-content').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $('#company-view-modal').modal('show');
        $('#modal-edit-btn').attr('href', sc_ajax.dashboard_url + 'company-attendee-edit?id=' + id);
        $('#modal-checkin-btn').data('id', id);

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_get_company_attendee',
                nonce: sc_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var c = response.data.company;
                    var html = '<div class="row">';
                    html += '<div class="col-md-4 text-center">';
                    if (c.logo_url) {
                        html += '<img src="' + c.logo_url + '" style="max-width: 150px; max-height: 150px;">';
                    } else {
                        html += '<div style="width: 150px; height: 150px; background: #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 auto;"><i class="fa fa-building fa-4x text-muted"></i></div>';
                    }
                    html += '<h4 class="mt-3">' + c.company_name + '</h4>';
                    if (c.company_name_ar) html += '<p class="text-muted">' + c.company_name_ar + '</p>';
                    html += '<p><code style="font-size: 14px;">' + c.company_code + '</code></p>';
                    html += '</div>';
                    html += '<div class="col-md-8">';
                    html += '<table class="table table-sm">';
                    html += '<tr><th width="35%">' + companyTranslations.email + '</th><td><a href="mailto:' + c.contact_email + '">' + c.contact_email + '</a></td></tr>';
                    html += '<tr><th>' + companyTranslations.phone + '</th><td>' + (c.contact_phone || '-') + '</td></tr>';
                    html += '<tr><th>' + companyTranslations.website + '</th><td>' + (c.website ? '<a href="' + c.website + '" target="_blank">' + c.website + '</a>' : '-') + '</td></tr>';
                    html += '<tr><th>' + companyTranslations.booth_number + '</th><td><strong>' + (c.booth_number || '-') + '</strong></td></tr>';
                    html += '<tr><th>' + companyTranslations.sponsorship + '</th><td>' + (c.sponsorship_level || '-') + '</td></tr>';
                    html += '<tr><th>' + companyTranslations.location + '</th><td>' + [c.city, c.country].filter(Boolean).join(', ') + '</td></tr>';
                    html += '</table>';
                    html += '</div>';
                    html += '</div>';

                    // QR Code section
                    var qrData = JSON.stringify({type: 'company', code: c.company_code, company: c.company_name, event: c.event_id});
                    var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qrData);
                    html += '<div class="text-center mt-3 pt-3" style="border-top: 1px solid #eee;">';
                    html += '<p class="mb-2"><strong><?php echo esc_js(sc_t('dashboard_pages.qr_code', 'QR Code')); ?></strong></p>';
                    html += '<img src="' + qrUrl + '" alt="QR Code" style="width: 180px; height: 180px; border: 1px solid #ddd; padding: 5px; border-radius: 8px;">';
                    html += '<p class="text-muted small mt-2"><?php echo esc_js(sc_t('dashboard_pages.scan_to_verify', 'Scan to verify')); ?></p>';
                    html += '</div>';

                    $('#company-view-content').html(html);

                    if (c.checked_in) {
                        $('#modal-checkin-btn').prop('disabled', true).html('<i class="fa fa-check"></i> ' + companyTranslations.already_checked_in);
                    } else {
                        $('#modal-checkin-btn').prop('disabled', false).html('<i class="fa fa-check"></i> ' + companyTranslations.check_in);
                    }
                }
            }
        });
    });

    // Check in from modal
    $('#modal-checkin-btn').on('click', function() {
        var id = $(this).data('id');
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + companyTranslations.checking_in);

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_checkin_company',
                nonce: sc_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    btn.html('<i class="fa fa-check"></i> ' + companyTranslations.checked_in);
                    loadCompanies();
                    if ($('#filter-event').val()) {
                        loadStats($('#filter-event').val());
                    }
                } else {
                    btn.prop('disabled', false).html('<i class="fa fa-check"></i> ' + companyTranslations.check_in);
                    alert(response.data.message || companyTranslations.error_checking_in);
                }
            }
        });
    });

    // Check in from table
    $(document).on('click', '.checkin-btn', function() {
        var id = $(this).data('id');
        var btn = $(this);

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_checkin_company',
                nonce: sc_ajax.nonce,
                id: id
            },
            beforeSend: function() {
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            },
            success: function(response) {
                if (response.success) {
                    loadCompanies();
                    if ($('#filter-event').val()) {
                        loadStats($('#filter-event').val());
                    }
                } else {
                    btn.prop('disabled', false).html('<i class="fa fa-check"></i>');
                    alert(response.data.message || companyTranslations.error_checking_in);
                }
            }
        });
    });

    // Send QR Email to company
    $(document).on('click', '.send-company-qr-btn', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var origHtml = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.saving', 'Sending...')); ?>');

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_send_company_ticket',
                nonce: sc_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.data.message);
                } else {
                    toastr.error(response.data.message || '<?php echo esc_js(sc_t('general.error', 'Error')); ?>');
                }
            },
            error: function() {
                toastr.error('<?php echo esc_js(sc_t('general.error', 'Error')); ?>');
            },
            complete: function() {
                $btn.html(origHtml);
            }
        });
    });

    // Delete company
    $(document).on('click', '.delete-company-btn', function() {
        if (!confirm(companyTranslations.confirm_delete)) return;

        var id = $(this).data('id');

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_delete_company_attendee',
                nonce: sc_ajax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadCompanies();
                } else {
                    alert(response.data.message || companyTranslations.error_deleting_company);
                }
            }
        });
    });

    // Export - Show modal
    $('#export-companies-btn').on('click', function() {
        // Pre-select current filter event if one is selected
        var currentEventId = $('#filter-event').val();
        if (currentEventId) {
            $('#export-event').prop('checked', true);
            $('#export-event-select-container').show();
            $('#export-event-select').val(currentEventId);
        } else {
            $('#export-all').prop('checked', true);
            $('#export-event-select-container').hide();
            $('#export-event-select').val('');
        }
        $('#export-modal').modal('show');
    });

    // Toggle event select based on radio selection
    $('input[name="export_option"]').on('change', function() {
        if ($(this).val() === 'event') {
            $('#export-event-select-container').slideDown();
        } else {
            $('#export-event-select-container').slideUp();
            $('#export-event-select').val('');
        }
    });

    // Confirm export button
    $('#confirm-export-btn').on('click', function() {
        var exportOption = $('input[name="export_option"]:checked').val();
        var eventId = '';

        if (exportOption === 'event') {
            eventId = $('#export-event-select').val();
            if (!eventId) {
                alert('Please select an event');
                return;
            }
        }

        var $btn = $(this);
        var originalText = $btn.html();

        $btn.html('<i class="fa fa-spinner fa-spin"></i> Exporting...').prop('disabled', true);

        $.ajax({
            url: sc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sc_export_company_attendees',
                nonce: sc_ajax.nonce,
                event_id: eventId
            },
            success: function(response) {
                $btn.html(originalText).prop('disabled', false);
                if (response.success) {
                    // Convert to CSV and download
                    var csv = response.data.headers.join(',') + '\n';
                    response.data.data.forEach(function(row) {
                        var values = response.data.headers.map(function(h) {
                            var val = row[h] || '';
                            return '"' + String(val).replace(/"/g, '""') + '"';
                        });
                        csv += values.join(',') + '\n';
                    });

                    var blob = new Blob([csv], { type: 'text/csv' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'company-attendees' + (eventId ? '-event-' + eventId : '-all') + '.csv';
                    a.click();

                    $('#export-modal').modal('hide');
                } else {
                    alert(response.data.message || 'Export failed');
                }
            },
            error: function() {
                $btn.html(originalText).prop('disabled', false);
                alert('Export failed. Please try again.');
            }
        });
    });

    // Select all checkbox
    $('#select-all').on('change', function() {
        $('.company-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>

<?php
get_template_part('template-parts/dashboard/components/dashboard', 'footer');
?>
