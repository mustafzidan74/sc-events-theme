<?php
/**
 * Dashboard Certificate Issue Page
 * Bulk issue certificates to event attendees
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.issue_certificates', 'Issue Certificates'),
    'certificates' => sc_t('dashboard_pages.certificates', 'Certificates'),
    'issue' => sc_t('dashboard_pages.issue', 'Issue'),
    'back_to_certificates' => sc_t('dashboard_pages.back_to_certificates', 'Back to Certificates'),
    'step1_select_event' => sc_t('dashboard_pages.step1_select_event', 'Step 1: Select Event'),
    'event' => sc_t('dashboard_pages.event', 'Event'),
    'select_event' => sc_t('dashboard_pages.select_event', '-- Select an Event --'),
    'event_certificate_stats' => sc_t('dashboard_pages.event_certificate_stats', 'Event Certificate Statistics'),
    'total_attendees' => sc_t('dashboard_pages.total_attendees', 'Total Attendees'),
    'certificates_issued' => sc_t('dashboard_pages.certificates_issued', 'Certificates Issued'),
    'pending' => sc_t('dashboard_pages.pending', 'Pending'),
    'revoked' => sc_t('dashboard_pages.revoked', 'Revoked'),
    'step2_select_template' => sc_t('dashboard_pages.step2_select_template', 'Step 2: Select Template'),
    'certificate_template' => sc_t('dashboard_pages.certificate_template', 'Certificate Template'),
    'no_templates_available' => sc_t('dashboard_pages.no_templates_available', 'No templates available'),
    'create_template_first' => sc_t('dashboard_pages.create_template_first', 'Create a template first'),
    'step3_select_attendees' => sc_t('dashboard_pages.step3_select_attendees', 'Step 3: Select Attendees'),
    'select_all' => sc_t('dashboard_pages.select_all', 'Select All'),
    'select_none' => sc_t('dashboard_pages.select_none', 'Select None'),
    'select_checked_in' => sc_t('dashboard_pages.select_checked_in', 'Select Checked-In Only'),
    'select_pending_only' => sc_t('dashboard_pages.select_pending_only', 'Select Pending Only'),
    'search_attendees' => sc_t('dashboard_pages.search_attendees', 'Search attendees...'),
    'all_attendees' => sc_t('dashboard_pages.all_attendees', 'All Attendees'),
    'without_certificate' => sc_t('dashboard_pages.without_certificate', 'Without Certificate'),
    'with_certificate' => sc_t('dashboard_pages.with_certificate', 'With Certificate'),
    'checked_in_only' => sc_t('dashboard_pages.checked_in_only', 'Checked In Only'),
    'selected' => sc_t('dashboard_pages.selected', 'selected'),
    'attendee' => sc_t('dashboard_pages.attendee', 'Attendee'),
    'ticket' => sc_t('dashboard_pages.ticket', 'Ticket'),
    'checkin' => sc_t('dashboard_pages.checkin', 'Check-In'),
    'certificate' => sc_t('dashboard_pages.certificate', 'Certificate'),
    'select_event_to_load' => sc_t('dashboard_pages.select_event_to_load', 'Select an event to load attendees'),
    'send_notification_emails' => sc_t('dashboard_pages.send_notification_emails', 'Send notification emails'),
    'after_issuing' => sc_t('dashboard_pages.after_issuing', 'after issuing certificates'),
    'issue_certificates_btn' => sc_t('dashboard_pages.issue_certificates_btn', 'Issue Certificates'),
    'template_preview' => sc_t('dashboard_pages.template_preview', 'Template Preview'),
    'select_template_preview' => sc_t('dashboard_pages.select_template_preview', 'Select a template to see preview'),
    'issue_progress' => sc_t('dashboard_pages.issue_progress', 'Issue Progress'),
    'preparing' => sc_t('dashboard_pages.preparing', 'Preparing...'),
    'loading_attendees' => sc_t('dashboard_pages.loading_attendees', 'Loading attendees...'),
    'error_loading_attendees' => sc_t('dashboard_pages.error_loading_attendees', 'Error loading attendees'),
    'no_attendees_found' => sc_t('dashboard_pages.no_attendees_found', 'No attendees found for this event'),
    'no_matching_attendees' => sc_t('dashboard_pages.no_matching_attendees', 'No matching attendees'),
    'yes' => sc_t('dashboard_pages.yes', 'Yes'),
    'no' => sc_t('dashboard_pages.no', 'No'),
    'issued' => sc_t('dashboard_pages.issued', 'Issued'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'preview_failed' => sc_t('dashboard_pages.preview_failed', 'Preview failed'),
    'no_template_warning' => sc_t('dashboard_pages.no_template_warning', 'No Template'),
    'select_template_message' => sc_t('dashboard_pages.select_template_message', 'Please select a certificate template.'),
    'issue_confirm_title' => sc_t('dashboard_pages.issue_confirm_title', 'Issue Certificates?'),
    'issue_confirm_text' => sc_t('dashboard_pages.issue_confirm_text', 'This will create certificates for all selected attendees.'),
    'yes_issue' => sc_t('dashboard_pages.yes_issue', 'Yes, Issue Certificates'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'connection_error' => sc_t('dashboard_pages.connection_error', 'Connection error'),
    'error_issuing' => sc_t('dashboard_pages.error_issuing', 'Error issuing certificates'),
    'sending_emails' => sc_t('dashboard_pages.sending_emails', 'Sending notification emails...'),
    'certs_issued_emails_sent' => sc_t('dashboard_pages.certs_issued_emails_sent', 'Certificates issued and emails sent!'),
    'certs_issued_emails_failed' => sc_t('dashboard_pages.certs_issued_emails_failed', 'Certificates issued but some emails failed.'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
    'default' => sc_t('dashboard_pages.default', 'Default'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get all events for dropdown from custom table
$events = SC_Event::get_all(array(
    'status' => array('publish', 'completed'),
    'orderby' => 'start_date',
    'order' => 'DESC',
    'per_page' => -1
));

// Get all active templates
$templates = SC_Certificate_Template::get_all(array(
    'is_active' => 1,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Get default template
$default_template = SC_Certificate_Template::get_default();
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
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/certificates'); ?>"><?php echo $t['certificates']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['issue']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <a href="<?php echo home_url('/event-manager-dashboard/certificates'); ?>" class="btn btn-secondary">
                                <i class="fa fa-arrow-<?php echo is_rtl() ? 'right' : 'left'; ?>"></i> <?php echo $t['back_to_certificates']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <!-- Left Column - Issue Settings -->
            <div class="col-lg-8 col-md-12">
                <!-- Select Event -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-calendar"></i> <?php echo $t['step1_select_event']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label for="event-select"><?php echo $t['event']; ?> <span class="text-danger">*</span></label>
                            <select class="form-control form-control-lg" id="event-select">
                                <option value=""><?php echo $t['select_event']; ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->id; ?>" data-date="<?php echo esc_attr($event->start_date); ?>">
                                        <?php echo esc_html($event->title); ?>
                                        <?php if ($event->start_date): ?> (<?php echo date('M j, Y', strtotime($event->start_date)); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Event Statistics -->
                <div class="card" id="event-stats-card" style="display: none;">
                    <div class="header">
                        <h2><i class="fa fa-bar-chart"></i> <?php echo $t['event_certificate_stats']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="row text-center" id="event-stats">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded">
                                    <h4 class="mb-0" id="stat-total-attendees">-</h4>
                                    <small class="text-muted"><?php echo $t['total_attendees']; ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-success text-white rounded">
                                    <h4 class="mb-0" id="stat-certificates-issued">-</h4>
                                    <small><?php echo $t['certificates_issued']; ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-warning rounded">
                                    <h4 class="mb-0" id="stat-pending">-</h4>
                                    <small><?php echo $t['pending']; ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-danger text-white rounded">
                                    <h4 class="mb-0" id="stat-revoked">-</h4>
                                    <small><?php echo $t['revoked']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Select Template -->
                <div class="card" id="template-card" style="display: none;">
                    <div class="header">
                        <h2><i class="fa fa-file-text"></i> <?php echo $t['step2_select_template']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="form-group">
                            <label for="template-select"><?php echo $t['certificate_template']; ?> <span class="text-danger">*</span></label>
                            <select class="form-control" id="template-select">
                                <?php if (empty($templates)): ?>
                                    <option value=""><?php echo $t['no_templates_available']; ?></option>
                                <?php else: ?>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template->id; ?>" <?php selected($default_template && $default_template->id == $template->id); ?>>
                                            <?php echo esc_html($template->name); ?>
                                            <?php if ($template->is_default): ?> (<?php echo $t['default']; ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($templates)): ?>
                                <small class="text-danger">
                                    <a href="<?php echo home_url('/event-manager-dashboard/certificate-template-create'); ?>"><?php echo $t['create_template_first']; ?></a>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Select Attendees -->
                <div class="card" id="attendees-card" style="display: none;">
                    <div class="header">
                        <h2><i class="fa fa-users"></i> <?php echo $t['step3_select_attendees']; ?></h2>
                        <ul class="header-dropdown">
                            <li class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li><a href="javascript:void(0);" id="select-all-link"><?php echo $t['select_all']; ?></a></li>
                                    <li><a href="javascript:void(0);" id="select-none-link"><?php echo $t['select_none']; ?></a></li>
                                    <li><a href="javascript:void(0);" id="select-checked-in-link"><?php echo $t['select_checked_in']; ?></a></li>
                                    <li><a href="javascript:void(0);" id="select-pending-link"><?php echo $t['select_pending_only']; ?></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <!-- Quick Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="attendee-search" placeholder="<?php echo esc_attr($t['search_attendees']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-control" id="attendee-filter">
                                    <option value="all"><?php echo $t['all_attendees']; ?></option>
                                    <option value="pending"><?php echo $t['without_certificate']; ?></option>
                                    <option value="issued"><?php echo $t['with_certificate']; ?></option>
                                    <option value="checked-in"><?php echo $t['checked_in_only']; ?></option>
                                </select>
                            </div>
                            <div class="col-md-4 text-<?php echo is_rtl() ? 'left' : 'right'; ?>">
                                <span class="badge badge-primary" id="selected-count">0 <?php echo $t['selected']; ?></span>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm" id="attendees-table">
                                <thead class="thead-light sticky-top">
                                    <tr>
                                        <th width="40">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="select-all-attendees">
                                                <label class="custom-control-label" for="select-all-attendees"></label>
                                            </div>
                                        </th>
                                        <th><?php echo $t['attendee']; ?></th>
                                        <th><?php echo $t['ticket']; ?></th>
                                        <th><?php echo $t['checkin']; ?></th>
                                        <th><?php echo $t['certificate']; ?></th>
                                    </tr>
                                </thead>
                                <tbody id="attendees-tbody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <?php echo $t['select_event_to_load']; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Issue Button -->
                <div class="card" id="issue-card" style="display: none;">
                    <div class="body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="send-emails-checkbox">
                                    <label class="custom-control-label" for="send-emails-checkbox">
                                        <strong><?php echo $t['send_notification_emails']; ?></strong> <?php echo $t['after_issuing']; ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-success btn-lg btn-block" id="issue-certificates-btn" disabled>
                                    <i class="fa fa-certificate"></i> <?php echo $t['issue_certificates_btn']; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Preview -->
            <div class="col-lg-4 col-md-12">
                <!-- Template Preview -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-eye"></i> <?php echo $t['template_preview']; ?></h2>
                    </div>
                    <div class="body" style="background: #f5f5f5; padding: 10px;">
                        <div id="template-preview-container" style="max-width: 100%; overflow: hidden;">
                            <div id="template-preview" style="background: white; transform-origin: top left; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <p class="text-center text-muted py-5"><?php echo $t['select_template_preview']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issue Progress -->
                <div class="card" id="progress-card" style="display: none;">
                    <div class="header">
                        <h2><i class="fa fa-tasks"></i> <?php echo $t['issue_progress']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" id="issue-progress-bar">
                                0%
                            </div>
                        </div>
                        <div id="progress-status" class="text-center">
                            <p class="mb-0"><i class="fa fa-spinner fa-spin"></i> <?php echo $t['preparing']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Translations for JavaScript
var certIssueTranslations = {
    loading_attendees: '<?php echo esc_js($t['loading_attendees']); ?>',
    error_loading_attendees: '<?php echo esc_js($t['error_loading_attendees']); ?>',
    no_attendees_found: '<?php echo esc_js($t['no_attendees_found']); ?>',
    no_matching_attendees: '<?php echo esc_js($t['no_matching_attendees']); ?>',
    yes: '<?php echo esc_js($t['yes']); ?>',
    no: '<?php echo esc_js($t['no']); ?>',
    issued: '<?php echo esc_js($t['issued']); ?>',
    pending: '<?php echo esc_js($t['pending']); ?>',
    selected: '<?php echo esc_js($t['selected']); ?>',
    loading: '<?php echo esc_js($t['loading']); ?>',
    preview_failed: '<?php echo esc_js($t['preview_failed']); ?>',
    no_template_warning: '<?php echo esc_js($t['no_template_warning']); ?>',
    select_template_message: '<?php echo esc_js($t['select_template_message']); ?>',
    issue_confirm_title: '<?php echo esc_js($t['issue_confirm_title']); ?>',
    issue_confirm_text: '<?php echo esc_js($t['issue_confirm_text']); ?>',
    yes_issue: '<?php echo esc_js($t['yes_issue']); ?>',
    cancel: '<?php echo esc_js($t['cancel']); ?>',
    connection_error: '<?php echo esc_js($t['connection_error']); ?>',
    error_issuing: '<?php echo esc_js($t['error_issuing']); ?>',
    sending_emails: '<?php echo esc_js($t['sending_emails']); ?>',
    certs_issued_emails_sent: '<?php echo esc_js($t['certs_issued_emails_sent']); ?>',
    certs_issued_emails_failed: '<?php echo esc_js($t['certs_issued_emails_failed']); ?>',
    issue_to_attendees: '<?php echo esc_js(sc_t('dashboard_pages.issue_to_attendees', 'Issue certificates to')); ?>',
    attendees_text: '<?php echo esc_js(sc_t('dashboard_pages.attendees_text', 'attendees')); ?>'
};

jQuery(function($) {
    // State
    let selectedEvent = null;
    let selectedTemplate = null;
    let attendeesData = [];
    const selectedAttendees = new Set();

    // Event selection
    $('#event-select').on('change', function() {
        selectedEvent = $(this).val();

        if (selectedEvent) {
            loadEventStats();
            loadAttendees();
            $('#template-card, #attendees-card, #issue-card').show();
        } else {
            $('#event-stats-card, #template-card, #attendees-card, #issue-card').hide();
        }
    });

    // Template selection
    $('#template-select').on('change', function() {
        selectedTemplate = $(this).val();
        if (selectedTemplate) {
            loadTemplatePreview();
        }
    });

    // Load event stats
    function loadEventStats() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_event_certificate_stats',
                nonce: scDashboard.nonce,
                event_id: selectedEvent
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data.stats;
                    $('#stat-total-attendees').text(stats.total_attendees || 0);
                    $('#stat-certificates-issued').text(stats.issued || 0);
                    $('#stat-pending').text(stats.pending || 0);
                    $('#stat-revoked').text(stats.revoked || 0);
                    $('#event-stats-card').show();
                }
            }
        });
    }

    // Load attendees for event
    function loadAttendees() {
        $('#attendees-tbody').html('<tr><td colspan="5" class="text-center py-4"><i class="fa fa-spinner fa-spin"></i> ' + certIssueTranslations.loading_attendees + '</td></tr>');
        selectedAttendees.clear();
        updateSelectedCount();

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_attendees_paginated',
                nonce: scDashboard.nonce,
                event_id: selectedEvent,
                per_page: 1000
            },
            success: function(response) {
                if (response.success) {
                    attendeesData = response.data.attendees || [];
                    renderAttendees(attendeesData);
                } else {
                    $('#attendees-tbody').html('<tr><td colspan="5" class="text-center text-danger py-4">' + certIssueTranslations.error_loading_attendees + '</td></tr>');
                }
            }
        });
    }

    function renderAttendees(attendees) {
        if (!attendees || attendees.length === 0) {
            $('#attendees-tbody').html('<tr><td colspan="5" class="text-center text-muted py-4">' + certIssueTranslations.no_attendees_found + '</td></tr>');
            return;
        }

        const filter = $('#attendee-filter').val();
        const search = $('#attendee-search').val().toLowerCase();

        let filtered = attendees.filter(function(a) {
            // Apply filter
            if (filter === 'pending' && a.has_certificate) return false;
            if (filter === 'issued' && !a.has_certificate) return false;
            if (filter === 'checked-in' && !a.checked_in) return false;

            // Apply search
            if (search) {
                const name = (a.name || '').toLowerCase();
                const email = (a.email || '').toLowerCase();
                return name.includes(search) || email.includes(search);
            }

            return true;
        });

        if (filtered.length === 0) {
            $('#attendees-tbody').html('<tr><td colspan="5" class="text-center text-muted py-4">' + certIssueTranslations.no_matching_attendees + '</td></tr>');
            return;
        }

        let html = '';
        filtered.forEach(function(attendee) {
            const isChecked = selectedAttendees.has(String(attendee.id)) ? 'checked' : '';
            const isDisabled = attendee.has_certificate ? 'disabled' : '';
            const rowClass = attendee.has_certificate ? 'table-success' : '';

            // Check-in badge
            let checkinBadge = attendee.checked_in
                ? '<span class="badge badge-success"><i class="fa fa-check"></i> ' + certIssueTranslations.yes + '</span>'
                : '<span class="badge badge-secondary">' + certIssueTranslations.no + '</span>';

            // Certificate badge
            let certBadge = attendee.has_certificate
                ? '<span class="badge badge-success"><i class="fa fa-certificate"></i> ' + certIssueTranslations.issued + '</span>'
                : '<span class="badge badge-warning">' + certIssueTranslations.pending + '</span>';

            html += `
                <tr class="${rowClass}">
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input attendee-checkbox" id="att-${attendee.id}" value="${attendee.id}" ${isChecked} ${isDisabled}>
                            <label class="custom-control-label" for="att-${attendee.id}"></label>
                        </div>
                    </td>
                    <td>
                        <strong>${escapeHtml(attendee.name)}</strong>
                        <br><small class="text-muted">${escapeHtml(attendee.email || '')}</small>
                    </td>
                    <td><small>${escapeHtml(attendee.ticket_name || '-')}</small></td>
                    <td>${checkinBadge}</td>
                    <td>${certBadge}</td>
                </tr>
            `;
        });

        $('#attendees-tbody').html(html);
        syncSelectAllCheckbox();
    }

    // Filter and search handlers
    $('#attendee-filter, #attendee-search').on('change input', function() {
        renderAttendees(attendeesData);
    });

    // Checkbox handling
    function updateSelectedCount() {
        const count = selectedAttendees.size;
        $('#selected-count').text(count + ' ' + certIssueTranslations.selected);
        $('#issue-certificates-btn').prop('disabled', count === 0);
    }

    function syncSelectAllCheckbox() {
        const checkboxes = $('.attendee-checkbox:not(:disabled)');
        if (checkboxes.length === 0) {
            $('#select-all-attendees').prop('checked', false);
            return;
        }
        const allChecked = checkboxes.length === checkboxes.filter(':checked').length;
        $('#select-all-attendees').prop('checked', allChecked);
    }

    $('#select-all-attendees').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.attendee-checkbox:not(:disabled)').each(function() {
            const id = String($(this).val());
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedAttendees.add(id);
            } else {
                selectedAttendees.delete(id);
            }
        });
        updateSelectedCount();
    });

    $(document).on('change', '.attendee-checkbox', function() {
        const id = String($(this).val());
        if ($(this).prop('checked')) {
            selectedAttendees.add(id);
        } else {
            selectedAttendees.delete(id);
        }
        updateSelectedCount();
        syncSelectAllCheckbox();
    });

    // Quick selection links
    $('#select-all-link').on('click', function() {
        $('.attendee-checkbox:not(:disabled)').prop('checked', true).each(function() {
            selectedAttendees.add(String($(this).val()));
        });
        updateSelectedCount();
        syncSelectAllCheckbox();
    });

    $('#select-none-link').on('click', function() {
        $('.attendee-checkbox').prop('checked', false);
        selectedAttendees.clear();
        updateSelectedCount();
        syncSelectAllCheckbox();
    });

    $('#select-checked-in-link').on('click', function() {
        $('.attendee-checkbox').prop('checked', false);
        selectedAttendees.clear();
        attendeesData.forEach(function(a) {
            if (a.checked_in && !a.has_certificate) {
                $('#att-' + a.id).prop('checked', true);
                selectedAttendees.add(String(a.id));
            }
        });
        updateSelectedCount();
        syncSelectAllCheckbox();
    });

    $('#select-pending-link').on('click', function() {
        $('.attendee-checkbox').prop('checked', false);
        selectedAttendees.clear();
        attendeesData.forEach(function(a) {
            if (!a.has_certificate) {
                $('#att-' + a.id).prop('checked', true);
                selectedAttendees.add(String(a.id));
            }
        });
        updateSelectedCount();
        syncSelectAllCheckbox();
    });

    // Load template preview
    function loadTemplatePreview() {
        $('#template-preview').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin"></i> ' + certIssueTranslations.loading + '</div>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_preview_certificate_template',
                nonce: scDashboard.nonce,
                template_id: selectedTemplate
            },
            success: function(response) {
                if (response.success) {
                    $('#template-preview').html(response.data.html);
                    $('#template-preview').css('transform', 'scale(0.3)');
                } else {
                    $('#template-preview').html('<div class="alert alert-danger m-3">' + certIssueTranslations.preview_failed + '</div>');
                }
            }
        });
    }

    // Issue certificates
    $('#issue-certificates-btn').on('click', function() {
        if (selectedAttendees.size === 0) return;

        const templateId = $('#template-select').val();
        if (!templateId) {
            Swal.fire({
                icon: 'warning',
                title: certIssueTranslations.no_template_warning,
                text: certIssueTranslations.select_template_message
            });
            return;
        }

        Swal.fire({
            title: certIssueTranslations.issue_confirm_title,
            html: `<p>${certIssueTranslations.issue_to_attendees} <strong>${selectedAttendees.size}</strong> ${certIssueTranslations.attendees_text}?</p>
                   <p class="text-muted">${certIssueTranslations.issue_confirm_text}</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: certIssueTranslations.yes_issue,
            cancelButtonText: certIssueTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                issueCertificates();
            }
        });
    });

    function issueCertificates() {
        $('#progress-card').show();
        $('#issue-certificates-btn').prop('disabled', true);

        const templateId = $('#template-select').val();
        const sendEmails = $('#send-emails-checkbox').is(':checked');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_bulk_issue_certificates',
                nonce: scDashboard.nonce,
                event_id: selectedEvent,
                template_id: templateId,
                attendee_ids: Array.from(selectedAttendees)
            },
            success: function(response) {
                if (response.success) {
                    $('#issue-progress-bar').css('width', '100%').text('100%');
                    $('#progress-status').html('<p class="text-success mb-0"><i class="fa fa-check"></i> ' + response.data.message + '</p>');

                    // Send emails if requested
                    if (sendEmails && response.data.issued > 0) {
                        sendBulkEmails();
                    } else {
                        // Reload data
                        setTimeout(function() {
                            loadEventStats();
                            loadAttendees();
                            selectedAttendees.clear();
                            updateSelectedCount();
                            $('#progress-card').hide();
                            $('#issue-certificates-btn').prop('disabled', false);
                        }, 2000);
                    }
                } else {
                    $('#progress-status').html('<p class="text-danger mb-0"><i class="fa fa-times"></i> ' + (response.data.message || certIssueTranslations.error_issuing) + '</p>');
                    $('#issue-certificates-btn').prop('disabled', false);
                }
            },
            error: function() {
                $('#progress-status').html('<p class="text-danger mb-0"><i class="fa fa-times"></i> ' + certIssueTranslations.connection_error + '</p>');
                $('#issue-certificates-btn').prop('disabled', false);
            }
        });
    }

    function sendBulkEmails() {
        $('#progress-status').html('<p class="mb-0"><i class="fa fa-envelope"></i> ' + certIssueTranslations.sending_emails + '</p>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_bulk_send_certificate_emails',
                nonce: scDashboard.nonce,
                event_id: selectedEvent,
                unsent_only: true
            },
            success: function(response) {
                if (response.success) {
                    $('#progress-status').html('<p class="text-success mb-0"><i class="fa fa-check"></i> ' + certIssueTranslations.certs_issued_emails_sent + '</p>');
                } else {
                    $('#progress-status').html('<p class="text-warning mb-0"><i class="fa fa-warning"></i> ' + certIssueTranslations.certs_issued_emails_failed + '</p>');
                }

                setTimeout(function() {
                    loadEventStats();
                    loadAttendees();
                    selectedAttendees.clear();
                    updateSelectedCount();
                    $('#progress-card').hide();
                    $('#issue-certificates-btn').prop('disabled', false);
                }, 2000);
            }
        });
    }

    // Helpers
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize template preview if default exists
    if ($('#template-select').val()) {
        selectedTemplate = $('#template-select').val();
        loadTemplatePreview();
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
