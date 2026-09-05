<?php
/**
 * Halls Management Page
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

$page_title = sc_t('dashboard_pages.halls_management', 'Halls Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html(sc_t('dashboard_pages.halls_management', 'Halls Management')); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.halls', 'Halls')); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/hall-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_hall', 'Add Hall')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" class="form-control" id="hall-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_halls', 'Search by name or location...')); ?>">
            </div>
        </div>
    </div>

    <!-- Halls List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="halls-table">
                            <thead>
                                <tr>
                                    <th width="60"><?php echo esc_html(sc_t('dashboard_pages.image', 'Image')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.name', 'Name')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.location', 'Location')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.schedules_count', 'Schedules')); ?></th>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="pagination-info-text"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="halls-pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var hallsTranslations = {
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    error_loading: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_halls', 'Error loading halls')); ?>',
    failed_load: '<?php echo esc_js(sc_t('dashboard_pages.failed_load_halls', 'Failed to load halls. Please try again.')); ?>',
    no_halls_found: '<?php echo esc_js(sc_t('dashboard_pages.no_halls_found', 'No halls found. Click "Add Hall" to create one.')); ?>',
    schedules_label: '<?php echo esc_js(sc_t('nav.schedules', 'schedules')); ?>',
    showing: '<?php echo esc_js(sc_t('dashboard_pages.showing_to_of', 'Showing {start} to {end} of {total}')); ?>',
    error_deleting: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_hall', 'Error deleting hall')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let currentSearch = '';
    let searchTimeout;

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function getImageHtml(hall) {
        if (hall.image_url) {
            return '<img src="' + escapeHtml(hall.image_url) + '" class="rounded" width="40" height="40" style="object-fit:cover;">';
        }
        var letter = hall.name ? hall.name.charAt(0).toUpperCase() : '?';
        var colors = ['#8B5CF6', '#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#EC4899'];
        var colorIndex = letter.charCodeAt(0) % colors.length;
        return '<div style="width:40px;height:40px;border-radius:8px;background:' + colors[colorIndex] + ';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px;">' + escapeHtml(letter) + '</div>';
    }

    function loadHallsPage(page) {
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_halls_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: 50,
                search: currentSearch
            },
            beforeSend: function() {
                $('#halls-table tbody').html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><p class="mt-3">' + hallsTranslations.loading + '</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderHallsTable(response.data.halls);
                renderPagination(response.data.current_page, response.data.pages);
                updatePaginationInfo(response.data.total, response.data.current_page, 50);
            } else {
                toastr.error(response.data.message || hallsTranslations.error_loading);
            }
        }).fail(function() {
            toastr.error(hallsTranslations.failed_load);
        });
    }

    function renderHallsTable(halls) {
        const tbody = $('#halls-table tbody');
        tbody.empty();

        if (!halls || halls.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4">' + hallsTranslations.no_halls_found + '</td></tr>');
            return;
        }

        halls.forEach(function(hall) {
            const row = `
                <tr>
                    <td>${getImageHtml(hall)}</td>
                    <td><strong>${escapeHtml(hall.name)}</strong></td>
                    <td>${(hall.capacity !== null && hall.capacity !== '') ? hall.capacity : '-'}</td>
                    <td>${escapeHtml(hall.location || '-')}</td>
                    <td><span class="badge badge-info">${hall.schedules_count || 0} ${hallsTranslations.schedules_label}</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/hall-edit'); ?>?id=${hall.id}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/hall-edit'); ?>?id=${hall.id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-hall" href="javascript:void(0);" data-id="${hall.id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function renderPagination(current, total) {
        const pagination = $('#halls-pagination');
        pagination.empty();
        if (total <= 1) return;

        const maxVisible = 7;
        let startPage = Math.max(1, current - Math.floor(maxVisible / 2));
        let endPage = Math.min(total, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

        pagination.append(`<li class="page-item ${current === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}"><i class="fa fa-chevron-left"></i></a></li>`);

        if (startPage > 1) {
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }

        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
        }

        if (endPage < total) {
            if (endPage < total - 1) pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="${total}">${total}</a></li>`);
        }

        pagination.append(`<li class="page-item ${current === total ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}"><i class="fa fa-chevron-right"></i></a></li>`);

        pagination.find('a.page-link').on('click', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== current) loadHallsPage(page);
        });
    }

    function updatePaginationInfo(total, page, perPage) {
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        const text = hallsTranslations.showing.replace('{start}', start).replace('{end}', end).replace('{total}', total);
        $('#pagination-info-text').text(text);
    }

    // Delete hall
    $(document).on('click', '.delete-hall', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) return;

            const hallId = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_hall',
                    nonce: scDashboard.nonce,
                    hall_id: hallId
                }
            }).done(function(response) {
                if (response.success) {
                    loadHallsPage(currentPage);
                } else {
                    toastr.error(response.data.message || hallsTranslations.error_deleting);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Search with debounce
    $('#hall-search').on('input', function() {
        clearTimeout(searchTimeout);
        const val = $(this).val().trim();
        searchTimeout = setTimeout(function() {
            currentSearch = val;
            loadHallsPage(1);
        }, 500);
    });

    // Initial load
    loadHallsPage(1);
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
