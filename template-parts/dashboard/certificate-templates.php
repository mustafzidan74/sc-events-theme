<?php
/**
 * Dashboard Certificate Templates Page
 * List, manage, and create certificate templates
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    'page_title' => sc_t('dashboard_pages.certificate_templates', 'Certificate Templates'),
    'certificates' => sc_t('dashboard_pages.certificates', 'Certificates'),
    'templates' => sc_t('dashboard_pages.templates', 'Templates'),
    'issued_certificates' => sc_t('dashboard_pages.issued_certificates', 'Issued Certificates'),
    'create_template' => sc_t('dashboard_pages.create_template', 'Create Template'),
    'visual_builder' => sc_t('dashboard_pages.visual_builder', 'Visual Builder'),
    'drag_drop_designer' => sc_t('dashboard_pages.drag_drop_designer', 'Drag & drop designer'),
    'classic_editor' => sc_t('dashboard_pages.classic_editor', 'Classic Editor'),
    'html_css_template' => sc_t('dashboard_pages.html_css_template', 'HTML/CSS template'),
    'total_templates' => sc_t('dashboard_pages.total_templates', 'Total Templates'),
    'active_templates' => sc_t('dashboard_pages.active_templates', 'Active Templates'),
    'default_template' => sc_t('dashboard_pages.default_template', 'Default Template'),
    'none' => sc_t('dashboard_pages.none', 'None'),
    'all_templates' => sc_t('dashboard_pages.all_templates', 'All Templates'),
    'search_templates' => sc_t('dashboard_pages.search_templates', 'Search templates...'),
    'all_types' => sc_t('dashboard_pages.all_types', 'All Types'),
    'all_status' => sc_t('dashboard_pages.all_status', 'All Status'),
    'per_page' => sc_t('dashboard_pages.per_page', 'per page'),
    'preview' => sc_t('dashboard_pages.preview', 'Preview'),
    'template_name' => sc_t('dashboard_pages.template_name', 'Template Name'),
    'type' => sc_t('dashboard_pages.type', 'Type'),
    'paper_size' => sc_t('dashboard_pages.paper_size', 'Paper Size'),
    'orientation' => sc_t('dashboard_pages.orientation', 'Orientation'),
    'default' => sc_t('dashboard_pages.default', 'Default'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'loading_templates' => sc_t('dashboard_pages.loading_templates', 'Loading templates...'),
    'template_preview' => sc_t('dashboard_pages.template_preview', 'Template Preview'),
    'close' => sc_t('dashboard_pages.close', 'Close'),
    'no_templates_found' => sc_t('dashboard_pages.no_templates_found', 'No templates found.'),
    'create_first_template' => sc_t('dashboard_pages.create_first_template', 'Create First Template'),
    'error_loading_templates' => sc_t('dashboard_pages.error_loading_templates', 'Error loading templates'),
    'connection_error_retry' => sc_t('dashboard_pages.connection_error_retry', 'Connection error. Please try again.'),
    'duplicate_template' => sc_t('dashboard_pages.duplicate_template', 'Duplicate Template?'),
    'duplicate_confirm' => sc_t('dashboard_pages.duplicate_confirm', 'This will create a copy of the template.'),
    'yes_duplicate' => sc_t('dashboard_pages.yes_duplicate', 'Yes, Duplicate'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'duplicated' => sc_t('dashboard_pages.duplicated', 'Duplicated!'),
    'template_duplicated' => sc_t('dashboard_pages.template_duplicated', 'Template has been duplicated.'),
    'error' => sc_t('dashboard_pages.error', 'Error'),
    'failed_duplicate' => sc_t('dashboard_pages.failed_duplicate', 'Failed to duplicate template.'),
    'default_updated' => sc_t('dashboard_pages.default_updated', 'Default updated'),
    'failed_update_default' => sc_t('dashboard_pages.failed_update_default', 'Failed to update default template.'),
    'delete_template' => sc_t('dashboard_pages.delete_template', 'Delete Template?'),
    'delete_confirm' => sc_t('dashboard_pages.delete_confirm', 'Are you sure you want to delete this template? This cannot be undone.'),
    'yes_delete' => sc_t('dashboard_pages.yes_delete', 'Yes, Delete'),
    'deleted' => sc_t('dashboard_pages.deleted', 'Deleted!'),
    'template_deleted' => sc_t('dashboard_pages.template_deleted', 'Template has been deleted.'),
    'cannot_delete' => sc_t('dashboard_pages.cannot_delete', 'Cannot Delete'),
    'failed_delete' => sc_t('dashboard_pages.failed_delete', 'Failed to delete template.'),
    'failed_load_preview' => sc_t('dashboard_pages.failed_load_preview', 'Failed to load preview'),
    'showing' => sc_t('dashboard_pages.showing', 'Showing'),
    'to' => sc_t('dashboard_pages.to', 'to'),
    'of' => sc_t('dashboard_pages.of', 'of'),
    'used_when_no_template' => sc_t('dashboard_pages.used_when_no_template', 'Used when no template is specified'),
    'visual' => sc_t('dashboard_pages.visual', 'Visual'),
    'classic' => sc_t('dashboard_pages.classic', 'Classic'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'created' => sc_t('dashboard_pages.created', 'Created'),
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get counts
$total_templates = SC_Certificate_Template::count();
$active_templates = SC_Certificate_Template::count(array('is_active' => 1));
$inactive_templates = $total_templates - $active_templates;

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
                        <li class="breadcrumb-item"><?php echo $t['certificates']; ?></li>
                        <li class="breadcrumb-item active"><?php echo $t['templates']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action d-flex flex-wrap">
                            <a href="<?php echo home_url('/event-manager-dashboard/certificates'); ?>" class="btn btn-secondary mr-2 mb-1">
                                <i class="fa fa-list"></i> <?php echo $t['issued_certificates']; ?>
                            </a>
                            <a href="<?php echo home_url('/event-manager-dashboard/certificate-visual-builder'); ?>" class="btn btn-primary mb-1">
                                <i class="fa fa-plus"></i> <?php echo $t['create_template']; ?>
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
                    <div class="icon"><i class="fa fa-file-text bg-blue"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['total_templates']; ?></div>
                        <div class="number" id="stat-total"><?php echo number_format($total_templates); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-check-circle bg-green"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['active_templates']; ?></div>
                        <div class="number" id="stat-active"><?php echo number_format($active_templates); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card info-box-2 hover-zoom-effect">
                    <div class="icon"><i class="fa fa-star bg-orange"></i></div>
                    <div class="content">
                        <div class="text"><?php echo $t['default_template']; ?></div>
                        <div class="number" id="stat-default"><?php echo $default_template ? esc_html($default_template->name) : $t['none']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="card">
            <div class="header">
                <h2><?php echo $t['all_templates']; ?></h2>
            </div>
            <div class="body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="template-search" placeholder="<?php echo esc_attr($t['search_templates']); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="status-filter">
                            <option value=""><?php echo $t['all_status']; ?></option>
                            <option value="active"><?php echo $t['active']; ?></option>
                            <option value="inactive"><?php echo $t['inactive']; ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="per-page-select">
                            <option value="10">10 <?php echo $t['per_page']; ?></option>
                            <option value="20" selected>20 <?php echo $t['per_page']; ?></option>
                            <option value="50">50 <?php echo $t['per_page']; ?></option>
                        </select>
                    </div>
                </div>

                <style>
                .text-purple { color: #9c27b0; }
                .dropdown-item small { display: block; margin-left: 18px; }
                #templates-table .toggle-default-btn {
                    width: auto !important;
                    padding: 10px !important;
                }
                </style>

                <div class="table-responsive" id="templates-table-container">
                    <table class="table table-hover table-custom spacing5" id="templates-table">
                        <thead>
                            <tr>
                                <th width="60"><?php echo $t['preview']; ?></th>
                                <th><?php echo $t['template_name']; ?></th>
                                <th><?php echo $t['paper_size']; ?></th>
                                <th><?php echo $t['orientation']; ?></th>
                                <th><?php echo $t['status']; ?></th>
                                <th><?php echo $t['default']; ?></th>
                                <th><?php echo $t['created']; ?></th>
                                <th><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody id="templates-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i>
                                    <p class="mt-2"><?php echo $t['loading_templates']; ?></p>
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
                            <nav aria-label="Templates pagination">
                                <ul class="pagination justify-content-end mb-0" id="templates-pagination">
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-eye"></i> <?php echo $t['template_preview']; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background: #f5f5f5; padding: 20px; min-height: 400px;">
                <div id="preview-container" style="width: 100%; display: flex; justify-content: center; align-items: flex-start; overflow: auto;">
                    <iframe id="preview-iframe" style="width: 297mm; height: 210mm; transform-origin: top center; transform: scale(0.6); border: 1px solid #ddd; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1);"></iframe>
                </div>
                <div id="preview-loading" class="text-center py-5" style="display: none;">
                    <i class="fa fa-spinner fa-spin" style="font-size: 48px;"></i>
                    <p class="mt-3"><?php echo $t['loading']; ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $t['close']; ?></button>
            </div>
        </div>
    </div>
</div>

<script>
var certTemplatesTranslations = {
    loading_templates: '<?php echo esc_js($t['loading_templates']); ?>',
    error_loading: '<?php echo esc_js($t['error_loading_templates']); ?>',
    connection_error: '<?php echo esc_js($t['connection_error_retry']); ?>',
    no_templates_found: '<?php echo esc_js($t['no_templates_found']); ?>',
    create_first_template: '<?php echo esc_js($t['create_first_template']); ?>',
    duplicate_template: '<?php echo esc_js($t['duplicate_template']); ?>',
    duplicate_confirm: '<?php echo esc_js($t['duplicate_confirm']); ?>',
    yes_duplicate: '<?php echo esc_js($t['yes_duplicate']); ?>',
    cancel: '<?php echo esc_js($t['cancel']); ?>',
    duplicated: '<?php echo esc_js($t['duplicated']); ?>',
    template_duplicated: '<?php echo esc_js($t['template_duplicated']); ?>',
    error: '<?php echo esc_js($t['error']); ?>',
    failed_duplicate: '<?php echo esc_js($t['failed_duplicate']); ?>',
    default_updated: '<?php echo esc_js($t['default_updated']); ?>',
    failed_update_default: '<?php echo esc_js($t['failed_update_default']); ?>',
    delete_template: '<?php echo esc_js($t['delete_template']); ?>',
    delete_confirm: '<?php echo esc_js($t['delete_confirm']); ?>',
    yes_delete: '<?php echo esc_js($t['yes_delete']); ?>',
    deleted: '<?php echo esc_js($t['deleted']); ?>',
    template_deleted: '<?php echo esc_js($t['template_deleted']); ?>',
    cannot_delete: '<?php echo esc_js($t['cannot_delete']); ?>',
    failed_delete: '<?php echo esc_js($t['failed_delete']); ?>',
    failed_load_preview: '<?php echo esc_js($t['failed_load_preview']); ?>',
    showing: '<?php echo esc_js($t['showing']); ?>',
    to: '<?php echo esc_js($t['to']); ?>',
    of: '<?php echo esc_js($t['of']); ?>',
    templates: '<?php echo esc_js($t['templates']); ?>',
    used_when_no_template: '<?php echo esc_js($t['used_when_no_template']); ?>',
    visual: '<?php echo esc_js($t['visual']); ?>',
    classic: '<?php echo esc_js($t['classic']); ?>',
    active: '<?php echo esc_js($t['active']); ?>',
    inactive: '<?php echo esc_js($t['inactive']); ?>',
    default_label: '<?php echo esc_js($t['default']); ?>'
};

jQuery(function($) {
    // State
    let currentPage = 1;
    let perPage = 20;
    let searchQuery = '';
    let statusFilter = '';
    let isLoading = false;
    let searchTimeout = null;

    // Initialize
    loadTemplates();

    // ===============================
    // Load Templates via AJAX
    // ===============================
    function loadTemplates() {
        if (isLoading) return;
        isLoading = true;

        $('#templates-tbody').html('<tr><td colspan="8" class="text-center py-4"><i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i><p class="mt-2 mb-0">' + certTemplatesTranslations.loading_templates + '</p></td></tr>');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_certificate_templates_paginated',
                nonce: scDashboard.nonce,
                page: currentPage,
                per_page: perPage,
                search: searchQuery,
                status: statusFilter
            },
            success: function(response) {
                isLoading = false;

                if (response.success) {
                    renderTemplates(response.data.templates);
                    renderPagination(response.data.total, response.data.pages, response.data.current_page);
                    updatePaginationInfo(response.data);
                } else {
                    $('#templates-tbody').html('<tr><td colspan="8" class="text-center text-danger py-5">' + certTemplatesTranslations.error_loading + '</td></tr>');
                }
            },
            error: function() {
                isLoading = false;
                $('#templates-tbody').html('<tr><td colspan="8" class="text-center text-danger py-5">' + certTemplatesTranslations.connection_error + '</td></tr>');
            }
        });
    }

    function renderTemplates(templates) {
        if (!templates || templates.length === 0) {
            $('#templates-tbody').html(`
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fa fa-certificate" style="font-size: 48px; opacity: 0.3;"></i>
                        <p class="mt-2">${certTemplatesTranslations.no_templates_found}</p>
                        <a href="<?php echo home_url('/event-manager-dashboard/certificate-visual-builder'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> ${certTemplatesTranslations.create_first_template}
                        </a>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        templates.forEach(function(template) {
            // Edit URL - always use visual builder
            let editUrl = '<?php echo home_url('/event-manager-dashboard/certificate-visual-builder'); ?>?template_id=' + template.id;

            // Status badge
            let statusBadge = template.is_active
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-secondary">Inactive</span>';

            // Default toggle button - Use parseInt to handle string "0" vs "1" from database
            let isDefault = parseInt(template.is_default) === 1;
            let defaultToggle = isDefault
                ? '<button class="btn btn-xs btn-warning toggle-default-btn" data-id="' + template.id + '" title="Remove Default"><i class="fa fa-star"></i> Default</button>'
                : '<button class="btn btn-xs btn-outline-secondary toggle-default-btn" data-id="' + template.id + '" title="Set as Default"><i class="fa fa-star-o"></i></button>';

            // Preview thumbnail (use background_image_url which is converted from attachment ID)
            let previewThumb = template.background_image_url
                ? '<img src="' + escapeHtml(template.background_image_url) + '" class="img-thumbnail" style="width: 50px; height: 35px; object-fit: cover; cursor: pointer;" onclick="previewTemplate(' + template.id + ')">'
                : '<div class="bg-light border d-flex align-items-center justify-content-center" style="width: 50px; height: 35px; cursor: pointer;" onclick="previewTemplate(' + template.id + ')"><i class="fa fa-file-text text-muted"></i></div>';

            // Format date
            let createdDate = template.created_at ? new Date(template.created_at).toLocaleDateString() : '-';

            html += `
                <tr>
                    <td>${previewThumb}</td>
                    <td>
                        <strong>${escapeHtml(template.name)}</strong>
                        ${isDefault ? '<br><small class="text-muted">Used when no template is specified</small>' : ''}
                    </td>
                    <td><span class="badge badge-info">${escapeHtml(template.paper_size || 'A4')}</span></td>
                    <td><span class="badge badge-light">${escapeHtml(template.orientation || 'Landscape')}</span></td>
                    <td>${statusBadge}</td>
                    <td>${defaultToggle}</td>
                    <td><small>${createdDate}</small></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info preview-template-btn" data-id="${template.id}" title="Preview">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="${editUrl}"><i class="fa fa-edit mr-2"></i> Edit</a>
                                <a class="dropdown-item duplicate-template-btn" href="javascript:void(0);" data-id="${template.id}"><i class="fa fa-copy mr-2"></i> Duplicate</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-template-btn" href="javascript:void(0);" data-id="${template.id}" ${isDefault ? 'style="opacity:0.5;pointer-events:none;"' : ''}><i class="fa fa-trash mr-2"></i> Delete</a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#templates-tbody').html(html);
    }

    function renderPagination(total, pages, current) {
        if (pages <= 1) {
            $('#templates-pagination').html('');
            return;
        }

        let html = '';

        // Previous
        html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current - 1}">&laquo;</a>
        </li>`;

        // Page numbers
        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(pages, startPage + 4);

        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next
        html += `<li class="page-item ${current >= pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current + 1}">&raquo;</a>
        </li>`;

        $('#templates-pagination').html(html);
    }

    function updatePaginationInfo(data) {
        const start = ((data.current_page - 1) * perPage) + 1;
        const end = Math.min(data.current_page * perPage, data.total);
        $('#pagination-info-text').text(`Showing ${start} to ${end} of ${data.total} templates`);
    }

    // ===============================
    // Event Handlers
    // ===============================

    // Pagination click
    $(document).on('click', '#templates-pagination .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== currentPage) {
            currentPage = page;
            loadTemplates();
        }
    });

    // Search
    $('#template-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchQuery = $('#template-search').val();
            currentPage = 1;
            loadTemplates();
        }, 500);
    });

    // Status filter
    $('#status-filter').on('change', function() {
        statusFilter = $(this).val();
        currentPage = 1;
        loadTemplates();
    });

    // Per page
    $('#per-page-select').on('change', function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        loadTemplates();
    });

    // ===============================
    // Template Actions
    // ===============================

    // Preview template
    $(document).on('click', '.preview-template-btn', function() {
        const id = $(this).data('id');
        previewTemplate(id);
    });

    // Duplicate template
    $(document).on('click', '.duplicate-template-btn', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Duplicate Template?',
            text: 'This will create a copy of the template.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Duplicate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_duplicate_certificate_template',
                        nonce: scDashboard.nonce,
                        template_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            loadTemplates();
                            Swal.fire({
                                icon: 'success',
                                title: 'Duplicated!',
                                text: 'Template has been duplicated.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.data.message || 'Failed to duplicate template.'
                            });
                        }
                    }
                });
            }
        });
    });

    // Toggle default template
    $(document).on('click', '.toggle-default-btn', function() {
        const id = $(this).data('id');
        const btn = $(this);
        const isCurrentlyDefault = btn.hasClass('btn-warning');

        btn.prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_toggle_default_certificate_template',
                nonce: scDashboard.nonce,
                template_id: id
            },
            success: function(response) {
                btn.prop('disabled', false);
                if (response.success) {
                    loadTemplates();
                    toastr.success(response.data.message || certTemplatesTranslations.default_updated);
                } else {
                    toastr.error(response.data.message || certTemplatesTranslations.failed_update_default);
                }
            },
            error: function() {
                btn.prop('disabled', false);
                toastr.error(certTemplatesTranslations.connection_error);
            }
        });
    });

    // Delete template
    $(document).on('click', '.delete-template-btn', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: certTemplatesTranslations.delete_template,
            text: certTemplatesTranslations.delete_confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: certTemplatesTranslations.yes_delete,
            cancelButtonText: certTemplatesTranslations.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_delete_certificate_template',
                        nonce: scDashboard.nonce,
                        template_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            loadTemplates();
                            Swal.fire({
                                icon: 'success',
                                title: certTemplatesTranslations.deleted,
                                text: certTemplatesTranslations.template_deleted,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: certTemplatesTranslations.cannot_delete,
                                text: response.data.message || certTemplatesTranslations.failed_delete
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

    // Global preview function
    window.previewTemplate = function(templateId) {
        const iframe = document.getElementById('preview-iframe');
        $('#preview-container').hide();
        $('#preview-loading').show();
        $('#previewModal').modal('show');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_preview_certificate_template',
                nonce: scDashboard.nonce,
                template_id: templateId
            },
            success: function(response) {
                $('#preview-loading').hide();
                $('#preview-container').show();

                if (response.success) {
                    // Write HTML to iframe for proper rendering of full HTML documents
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(response.data.html);
                    iframeDoc.close();
                } else {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write('<div style="padding:40px;color:red;font-size:18px;">' + (response.data.message || 'Failed to load preview') + '</div>');
                    iframeDoc.close();
                }
            },
            error: function() {
                $('#preview-loading').hide();
                $('#preview-container').show();
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                iframeDoc.open();
                iframeDoc.write('<div style="padding:40px;color:red;font-size:18px;">Connection error</div>');
                iframeDoc.close();
            }
        });
    };
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
