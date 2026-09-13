<?php
/**
 * Dashboard Issued Certificates Page
 * List and manage issued certificates
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

$page_title = sc_t('dashboard_pages.issued_certificates', 'Issued Certificates');
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Translations
$t = array(
    'issued_certificates' => sc_t('dashboard_pages.issued_certificates', 'Issued Certificates'),
    'certificates' => sc_t('nav.certificates', 'Certificates'),
    'issued' => sc_t('dashboard_pages.issued', 'Issued'),
    'templates' => sc_t('dashboard_pages.templates', 'Templates'),
    'issue_certificates' => sc_t('dashboard_pages.issue_certificates', 'Issue Certificates'),
    'total_certificates' => sc_t('dashboard_pages.total_certificates', 'Total Certificates'),
    'active_certificates' => sc_t('dashboard_pages.active_certificates', 'Active Certificates'),
    'revoked_certificates' => sc_t('dashboard_pages.revoked_certificates', 'Revoked Certificates'),
    'all_issued_certificates' => sc_t('dashboard_pages.all_issued_certificates', 'All Issued Certificates'),
    'search_placeholder' => sc_t('dashboard_pages.search_name_number', 'Search name, number...'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'all_status' => sc_t('dashboard_pages.all_status', 'All Status'),
    'revoked' => sc_t('dashboard_pages.revoked', 'Revoked'),
    'certificate_number' => sc_t('dashboard_pages.certificate_number', 'Certificate #'),
    'attendee' => sc_t('dashboard_pages.attendee', 'Attendee'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'issue_date' => sc_t('dashboard_pages.issue_date', 'Issue Date'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
);

// Get all events from custom table
global $wpdb;
$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY title ASC");

// Get counts
$total_certificates = SC_Certificate::count();
$revoked_certificates = SC_Certificate::count(array('status' => 'revoked'));
// Active = every certificate that isn't revoked (issued and downloaded alike).
$issued_certificates = $total_certificates - $revoked_certificates;
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo $t['issued_certificates']; ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><?php echo $t['certificates']; ?></li>
                        <li class="breadcrumb-item active"><?php echo $t['issued']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action d-flex flex-wrap">
                            <a href="<?php echo home_url('/event-manager-dashboard/certificate-templates'); ?>" class="btn btn-secondary mr-2 mb-1">
                                <i class="fa fa-file-text"></i> <?php echo $t['templates']; ?>
                            </a>
                            <a href="<?php echo home_url('/event-manager-dashboard/certificate-issue'); ?>" class="btn btn-primary mb-1">
                                <i class="fa fa-plus"></i> <?php echo $t['issue_certificates']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row clearfix">
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-certificate bg-blue"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['total_certificates']; ?></div>
                        <div class="number" id="stat-total"><?php echo number_format($total_certificates); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-check-circle bg-green"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['active_certificates']; ?></div>
                        <div class="number" id="stat-issued"><?php echo number_format($issued_certificates); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-ban bg-red"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['revoked_certificates']; ?></div>
                        <div class="number" id="stat-revoked"><?php echo number_format($revoked_certificates); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificates Table -->
        <div class="card">
            <div class="header">
                <h2><?php echo $t['all_issued_certificates']; ?></h2>
            </div>
            <div class="body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="certificate-search" placeholder="<?php echo $t['search_placeholder']; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="event-filter">
                            <option value=""><?php echo $t['all_events']; ?></option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="status-filter">
                            <option value=""><?php echo $t['all_status']; ?></option>
                            <option value="issued"><?php echo $t['issued']; ?></option>
                            <option value="revoked"><?php echo $t['revoked']; ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="per-page-select">
                            <option value="20">20 <?php echo sc_t('dashboard_pages.per_page', 'per page'); ?></option>
                            <option value="50" selected>50 <?php echo sc_t('dashboard_pages.per_page', 'per page'); ?></option>
                            <option value="100">100 <?php echo sc_t('dashboard_pages.per_page', 'per page'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-info btn-block" id="bulk-send-emails-btn" disabled>
                            <i class="fa fa-envelope"></i> <?php echo sc_t('dashboard_pages.send_emails', 'Send Emails'); ?>
                        </button>
                    </div>
                </div>

                <div class="table-responsive" id="certificates-table-container">
                    <table class="table table-hover table-custom spacing5" id="certificates-table">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="select-all-certificates">
                                        <label class="custom-control-label" for="select-all-certificates"></label>
                                    </div>
                                </th>
                                <th><?php echo $t['certificate_number']; ?></th>
                                <th><?php echo $t['attendee']; ?></th>
                                <th><?php echo $t['event']; ?></th>
                                <th><?php echo $t['issue_date']; ?></th>
                                <th><?php echo sc_t('dashboard_pages.downloads', 'Downloads'); ?></th>
                                <th><?php echo sc_t('dashboard_pages.email', 'Email'); ?></th>
                                <th><?php echo $t['status']; ?></th>
                                <th><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody id="certificates-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i>
                                    <p class="mt-2"><?php echo $t['loading']; ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="pagination-info-text">Loading...</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav aria-label="Certificates pagination">
                                <ul class="pagination justify-content-end mb-0" id="certificates-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Certificate Details Modal -->
<div class="modal fade" id="certificateDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-certificate"></i> <?php echo esc_html(sc_t('dashboard_pages.certificate_details', 'Certificate Details')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="certificate-details-content">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin" style="font-size: 48px;"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.close', 'Close')); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-ban"></i> <?php echo esc_html(sc_t('dashboard_pages.revoke_certificate', 'Revoke Certificate')); ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="revoke-certificate-id">
                <p><?php echo esc_html(sc_t('dashboard_pages.revoke_confirm_text', 'Are you sure you want to revoke this certificate? The attendee will no longer be able to download or verify it.')); ?></p>
                <div class="form-group">
                    <label for="revoke-reason"><?php echo esc_html(sc_t('dashboard_pages.revoke_reason', 'Reason for Revocation (optional)')); ?></label>
                    <textarea class="form-control" id="revoke-reason" rows="3" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.enter_reason', 'Enter reason...')); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-danger" id="confirm-revoke-btn">
                    <i class="fa fa-ban"></i> <?php echo esc_html(sc_t('dashboard_pages.revoke_certificate', 'Revoke Certificate')); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    // Translations
    var certTranslations = {
        email_sent: '<?php echo esc_js(sc_t("certificates.email_sent", "Email sent successfully!")); ?>',
        failed_send_email: '<?php echo esc_js(sc_t("certificates.failed_send_email", "Failed to send email")); ?>',
        connection_error: '<?php echo esc_js(sc_t("general.connection_error", "Connection error")); ?>',
        send_emails: '<?php echo esc_js(sc_t("certificates.send_emails", "Send Emails?")); ?>',
        send_emails_confirm: '<?php echo esc_js(sc_t("certificates.send_emails_confirm", "Send certificate emails to")); ?>',
        selected_recipients: '<?php echo esc_js(sc_t("certificates.selected_recipients", "selected recipients?")); ?>',
        yes_send: '<?php echo esc_js(sc_t("certificates.yes_send", "Yes, Send Emails")); ?>',
        cancel: '<?php echo esc_js(sc_t("general.cancel", "Cancel")); ?>',
        sending_emails: '<?php echo esc_js(sc_t("certificates.sending_emails", "Sending Emails...")); ?>',
        emails_sent: '<?php echo esc_js(sc_t("certificates.emails_sent", "Emails Sent!")); ?>',
        error: '<?php echo esc_js(sc_t("general.error", "Error")); ?>',
        failed_send_emails: '<?php echo esc_js(sc_t("certificates.failed_send_emails", "Failed to send emails")); ?>',
        revoked: '<?php echo esc_js(sc_t("certificates.revoked", "Revoked!")); ?>',
        certificate_revoked: '<?php echo esc_js(sc_t("certificates.certificate_revoked", "Certificate has been revoked.")); ?>',
        failed_revoke: '<?php echo esc_js(sc_t("certificates.failed_revoke", "Failed to revoke certificate")); ?>',
        reinstate_certificate: '<?php echo esc_js(sc_t("certificates.reinstate_certificate", "Reinstate Certificate?")); ?>',
        reinstate_text: '<?php echo esc_js(sc_t("certificates.reinstate_text", "This will make the certificate valid again.")); ?>',
        yes_reinstate: '<?php echo esc_js(sc_t("certificates.yes_reinstate", "Yes, Reinstate")); ?>',
        reinstated: '<?php echo esc_js(sc_t("certificates.reinstated", "Reinstated!")); ?>',
        certificate_reinstated: '<?php echo esc_js(sc_t("certificates.certificate_reinstated", "Certificate has been reinstated.")); ?>',
        failed_reinstate: '<?php echo esc_js(sc_t("certificates.failed_reinstate", "Failed to reinstate certificate")); ?>',
        default_updated: '<?php echo esc_js(sc_t("certificates.default_updated", "Default updated")); ?>'
    };

    // State
    let currentPage = 1;
    let perPage = 50;
    let searchQuery = '';
    let eventFilter = '';
    let statusFilter = '';
    let isLoading = false;
    const selectedCertificates = new Set();
    let searchTimeout = null;

    // Initialize
    loadCertificates();

    // ===============================
    // Load Certificates via AJAX
    // ===============================
    function loadCertificates() {
        if (isLoading) return;
        isLoading = true;

        $('#certificates-tbody').html('<tr><td colspan="9" class="text-center py-4"><i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i><p class="mt-2 mb-0">Loading certificates...</p></td></tr>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_certificates_paginated',
                nonce: scDashboard.nonce,
                page: currentPage,
                per_page: perPage,
                search: searchQuery,
                event_id: eventFilter,
                status: statusFilter
            },
            success: function(response) {
                isLoading = false;

                if (response.success) {
                    renderCertificates(response.data.certificates);
                    renderPagination(response.data.total, response.data.pages, response.data.current_page);
                    updatePaginationInfo(response.data);
                } else {
                    $('#certificates-tbody').html('<tr><td colspan="9" class="text-center text-danger py-5">Error loading certificates</td></tr>');
                }
            },
            error: function() {
                isLoading = false;
                $('#certificates-tbody').html('<tr><td colspan="9" class="text-center text-danger py-5">Connection error. Please try again.</td></tr>');
            }
        });
    }

    function renderCertificates(certificates) {
        if (!certificates || certificates.length === 0) {
            $('#certificates-tbody').html(`
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="fa fa-certificate" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mt-2">No certificates found.</p>
                        <a href="<?php echo home_url('/event-manager-dashboard/certificate-issue'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> Issue Certificates
                        </a>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        certificates.forEach(function(cert) {
            const isChecked = selectedCertificates.has(String(cert.id)) ? 'checked' : '';

            // Status badge
            // "downloaded" is a valid certificate too; only "revoked" is not.
            let statusBadge = cert.status === 'revoked'
                ? '<span class="badge badge-danger"><?php echo esc_js(sc_t('dashboard_pages.revoked', 'Revoked')); ?></span>'
                : '<span class="badge badge-success"><?php echo esc_js(sc_t('dashboard_pages.issued', 'Issued')); ?></span>';

            // Format date helper - consistent DD MMM YYYY format
            function formatDate(dateStr) {
                if (!dateStr) return '-';
                var d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            }

            // Email status
            let emailBadge = cert.email_sent
                ? '<span class="badge badge-success" title="' + (cert.email_sent_at ? formatDate(cert.email_sent_at) : '<?php echo esc_js(sc_t('certificates.email_sent', 'Email sent')); ?>') + '"><i class="fa fa-check"></i> ' + (cert.email_sent_at ? formatDate(cert.email_sent_at) : '') + '</span>'
                : '<span class="badge badge-secondary" title="<?php echo esc_js(sc_t('certificates.not_sent', 'Not sent')); ?>"><i class="fa fa-times"></i></span>';

            // Format date
            let issuedDate = formatDate(cert.issued_at);

            html += `
                <tr class="${cert.status === 'revoked' ? 'table-danger' : ''}">
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input certificate-checkbox" id="cert-${cert.id}" value="${cert.id}" ${isChecked} ${cert.status === 'revoked' ? 'disabled' : ''}>
                            <label class="custom-control-label" for="cert-${cert.id}"></label>
                        </div>
                    </td>
                    <td>
                        <strong><code>${escapeHtml(cert.certificate_number)}</code></strong>
                        <br><small class="text-muted">${escapeHtml(cert.verification_code)}</small>
                    </td>
                    <td>
                        <strong>${escapeHtml(cert.attendee_name)}</strong>
                    </td>
                    <td>
                        ${cert.event_deleted
                            ? '<span style="color:#e65100;">' + escapeHtml(cert.event_title) + ' <span class="badge badge-danger" style="font-size:10px;vertical-align:middle;"><?php echo esc_js(sc_t('general.deleted', 'Deleted')); ?></span></span>'
                            : '<span class="text-primary">' + escapeHtml(cert.event_title) + '</span>'}
                        <br><small class="text-muted">${escapeHtml(cert.event_date || '')}</small>
                    </td>
                    <td><small>${issuedDate}</small></td>
                    <td><span class="badge badge-info">${cert.download_count || 0}</span></td>
                    <td>${emailBadge}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info view-certificate-btn" data-id="${cert.id}" title="<?php echo esc_js(sc_t('certificates.view_details', 'View Details')); ?>">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${cert.status === 'issued' ? `
                                    <a class="dropdown-item" href="${cert.download_url || '#'}" target="_blank"><i class="fa fa-download mr-2"></i> <?php echo esc_js(sc_t('general.download', 'Download')); ?></a>
                                    <a class="dropdown-item send-email-btn" href="javascript:void(0);" data-id="${cert.id}"><i class="fa fa-envelope mr-2"></i> <?php echo esc_js(sc_t('certificates.send_email', 'Send Email')); ?></a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger revoke-certificate-btn" href="javascript:void(0);" data-id="${cert.id}"><i class="fa fa-ban mr-2"></i> <?php echo esc_js(sc_t('certificates.revoke', 'Revoke')); ?></a>
                                ` : `
                                    <a class="dropdown-item text-warning reinstate-btn" href="javascript:void(0);" data-id="${cert.id}"><i class="fa fa-undo mr-2"></i> <?php echo esc_js(sc_t('certificates.reinstate', 'Reinstate')); ?></a>
                                `}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#certificates-tbody').html(html);
        updateBulkButton();
        syncSelectAllCheckbox();
    }

    function renderPagination(total, pages, current) {
        if (pages <= 1) {
            $('#certificates-pagination').html('');
            return;
        }

        let html = '';

        // Previous
        html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current - 1}">&laquo;</a>
        </li>`;

        // Page numbers
        let startPage = Math.max(1, current - 3);
        let endPage = Math.min(pages, startPage + 6);

        if (endPage - startPage < 6) {
            startPage = Math.max(1, endPage - 6);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        if (endPage < pages) {
            if (endPage < pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pages}">${pages}</a></li>`;
        }

        // Next
        html += `<li class="page-item ${current >= pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current + 1}">&raquo;</a>
        </li>`;

        $('#certificates-pagination').html(html);
    }

    function updatePaginationInfo(data) {
        const start = ((data.current_page - 1) * perPage) + 1;
        const end = Math.min(data.current_page * perPage, data.total);
        $('#pagination-info-text').text('<?php echo esc_js(sc_t('dashboard_pages.showing', 'Showing')); ?> ' + start + ' <?php echo esc_js(sc_t('dashboard_pages.to', 'to')); ?> ' + end + ' <?php echo esc_js(sc_t('dashboard_pages.of', 'of')); ?> ' + data.total + ' <?php echo esc_js(sc_t('dashboard_pages.certificates', 'certificates')); ?>');
    }

    // ===============================
    // Event Handlers
    // ===============================

    // Pagination click
    $(document).on('click', '#certificates-pagination .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== currentPage) {
            currentPage = page;
            loadCertificates();
        }
    });

    // Search
    $('#certificate-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchQuery = $('#certificate-search').val();
            currentPage = 1;
            loadCertificates();
        }, 500);
    });

    // Event filter
    $('#event-filter').on('change', function() {
        eventFilter = $(this).val();
        currentPage = 1;
        loadCertificates();
    });

    // Status filter
    $('#status-filter').on('change', function() {
        statusFilter = $(this).val();
        currentPage = 1;
        loadCertificates();
    });

    // Per page
    $('#per-page-select').on('change', function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        loadCertificates();
    });

    // ===============================
    // Checkbox handling
    // ===============================

    function updateBulkButton() {
        $('#bulk-send-emails-btn').prop('disabled', selectedCertificates.size === 0);
    }

    function syncSelectAllCheckbox() {
        const checkboxes = $('.certificate-checkbox:not(:disabled)');
        if (checkboxes.length === 0) {
            $('#select-all-certificates').prop('checked', false);
            return;
        }
        const allChecked = checkboxes.length === checkboxes.filter(':checked').length;
        $('#select-all-certificates').prop('checked', allChecked);
    }

    $('#select-all-certificates').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.certificate-checkbox:not(:disabled)').each(function() {
            const id = String($(this).val());
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedCertificates.add(id);
            } else {
                selectedCertificates.delete(id);
            }
        });
        updateBulkButton();
    });

    $(document).on('change', '.certificate-checkbox', function() {
        const id = String($(this).val());
        if ($(this).prop('checked')) {
            selectedCertificates.add(id);
        } else {
            selectedCertificates.delete(id);
        }
        updateBulkButton();
        syncSelectAllCheckbox();
    });

    // ===============================
    // Certificate Actions
    // ===============================

    // View certificate details
    $(document).on('click', '.view-certificate-btn', function() {
        const id = $(this).data('id');
        $('#certificate-details-content').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin" style="font-size: 48px;"></i></div>');
        $('#certificateDetailsModal').modal('show');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_certificate',
                nonce: scDashboard.nonce,
                certificate_id: id
            },
            success: function(response) {
                if (response.success) {
                    const cert = response.data.certificate;
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fa fa-certificate"></i> <?php echo esc_js(sc_t('certificates.certificate_info', 'Certificate Information')); ?></h6>
                                <table class="table table-sm">
                                    <tr><th><?php echo esc_js(sc_t('certificates.certificate_number', 'Certificate #')); ?></th><td><code>${escapeHtml(cert.certificate_number)}</code></td></tr>
                                    <tr><th><?php echo esc_js(sc_t('certificates.verification_code', 'Verification Code')); ?></th><td><code>${escapeHtml(cert.verification_code)}</code></td></tr>
                                    <tr><th><?php echo esc_js(sc_t('certificates.status', 'Status')); ?></th><td>${cert.status !== 'revoked' ? '<span class="badge badge-success"><?php echo esc_js(sc_t('dashboard_pages.issued', 'Issued')); ?></span>' : '<span class="badge badge-danger"><?php echo esc_js(sc_t('dashboard_pages.revoked', 'Revoked')); ?></span>'}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('certificates.issued_date', 'Issued Date')); ?></th><td>${cert.issued_at ? new Date(cert.issued_at).toLocaleString() : '-'}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('dashboard_pages.downloads', 'Downloads')); ?></th><td>${cert.download_count || 0}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('certificates.last_download', 'Last Download')); ?></th><td>${cert.last_download ? new Date(cert.last_download).toLocaleString() : '<?php echo esc_js(sc_t('certificates.never', 'Never')); ?>'}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('certificates.email_sent', 'Email Sent')); ?></th><td>${cert.email_sent ? '<span class="badge badge-success"><?php echo esc_js(sc_t('general.yes', 'Yes')); ?></span>' : '<span class="badge badge-secondary"><?php echo esc_js(sc_t('general.no', 'No')); ?></span>'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fa fa-user"></i> <?php echo esc_js(sc_t('certificates.attendee_info', 'Attendee Information')); ?></h6>
                                <table class="table table-sm">
                                    <tr><th><?php echo esc_js(sc_t('attendees.name', 'Name')); ?></th><td>${escapeHtml(cert.attendee_name)}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('events.event', 'Event')); ?></th><td>${escapeHtml(cert.event_title)}</td></tr>
                                    <tr><th><?php echo esc_js(sc_t('events.event_date', 'Event Date')); ?></th><td>${escapeHtml(cert.event_date || '-')}</td></tr>
                                </table>
                                ${cert.status === 'revoked' && cert.revoke_reason ? `
                                    <div class="alert alert-danger">
                                        <strong><?php echo esc_js(sc_t('certificates.revoke_reason', 'Revoke Reason')); ?>:</strong><br>
                                        ${escapeHtml(cert.revoke_reason)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <a href="${cert.verification_url || '#'}" class="btn btn-info" target="_blank">
                                <i class="fa fa-check-circle"></i> <?php echo esc_js(sc_t('certificates.verification_page', 'Verification Page')); ?>
                            </a>
                            ${cert.status === 'issued' ? `
                                <a href="${cert.download_url || '#'}" class="btn btn-success" target="_blank">
                                    <i class="fa fa-download"></i> <?php echo esc_js(sc_t('certificates.download_certificate', 'Download Certificate')); ?>
                                </a>
                            ` : ''}
                        </div>
                    `;
                    $('#certificate-details-content').html(html);
                } else {
                    $('#certificate-details-content').html('<div class="alert alert-danger"><?php echo esc_js(sc_t('certificates.failed_load', 'Failed to load certificate details')); ?></div>');
                }
            }
        });
    });

    // Send email
    $(document).on('click', '.send-email-btn', function() {
        const id = $(this).data('id');
        const $btn = $(this);

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_send_certificate_email',
                nonce: scDashboard.nonce,
                certificate_id: id
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(certTranslations.email_sent);
                    loadCertificates();
                } else {
                    toastr.error(response.data.message || certTranslations.failed_send_email);
                }
                $btn.prop('disabled', false).html('<i class="fa fa-envelope"></i>');
            },
            error: function() {
                toastr.error(certTranslations.connection_error);
                $btn.prop('disabled', false).html('<i class="fa fa-envelope"></i>');
            }
        });
    });

    // Bulk send emails
    $('#bulk-send-emails-btn').on('click', function() {
        if (selectedCertificates.size === 0) return;

        Swal.fire({
            title: certTranslations.send_emails,
            text: certTranslations.send_emails_confirm + ' ' + selectedCertificates.size + ' ' + certTranslations.selected_recipients,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: certTranslations.yes_send,
            cancelButtonText: certTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: certTranslations.sending_emails,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_bulk_send_certificate_emails',
                        nonce: scDashboard.nonce,
                        certificate_ids: Array.from(selectedCertificates)
                    },
                    success: function(response) {
                        if (response.success) {
                            selectedCertificates.clear();
                            loadCertificates();
                            Swal.fire({
                                icon: 'success',
                                title: certTranslations.emails_sent,
                                text: response.data.message,
                                confirmButtonColor: '#28a745'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: certTranslations.error,
                                text: response.data.message || certTranslations.failed_send_emails
                            });
                        }
                    }
                });
            }
        });
    });

    // Revoke certificate
    $(document).on('click', '.revoke-certificate-btn', function() {
        const id = $(this).data('id');
        $('#revoke-certificate-id').val(id);
        $('#revoke-reason').val('');
        $('#revokeModal').modal('show');
    });

    $('#confirm-revoke-btn').on('click', function() {
        const id = $('#revoke-certificate-id').val();
        const reason = $('#revoke-reason').val();

        $('#confirm-revoke-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo esc_js(sc_t('general.processing', 'Processing...')); ?>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_revoke_certificate',
                nonce: scDashboard.nonce,
                certificate_id: id,
                reason: reason
            },
            success: function(response) {
                $('#revokeModal').modal('hide');
                $('#confirm-revoke-btn').prop('disabled', false).html('<i class="fa fa-ban"></i> <?php echo esc_js(sc_t('dashboard_pages.revoke_certificate', 'Revoke Certificate')); ?>');

                if (response.success) {
                    loadCertificates();
                    Swal.fire({
                        icon: 'success',
                        title: certTranslations.revoked,
                        text: certTranslations.certificate_revoked,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: certTranslations.error,
                        text: response.data.message || certTranslations.failed_revoke
                    });
                }
            }
        });
    });

    // Reinstate certificate
    $(document).on('click', '.reinstate-btn', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: certTranslations.reinstate_certificate,
            text: certTranslations.reinstate_text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: certTranslations.yes_reinstate,
            cancelButtonText: certTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_reinstate_certificate',
                        nonce: scDashboard.nonce,
                        certificate_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            loadCertificates();
                            Swal.fire({
                                icon: 'success',
                                title: certTranslations.reinstated,
                                text: certTranslations.certificate_reinstated,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: certTranslations.error,
                                text: response.data.message || certTranslations.failed_reinstate
                            });
                        }
                    }
                });
            }
        });
    });

    // ===============================
    // Helpers
    // ===============================

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
