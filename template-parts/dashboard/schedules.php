<?php
/**
 * Schedules Management Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.schedules_management', 'Schedules Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get events for filter dropdown
global $wpdb;
$sc_events_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_results("SELECT id, title FROM $sc_events_table ORDER BY title ASC");
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html(sc_t('dashboard_pages.schedules_management', 'Schedules Management')); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/schedule-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_schedule', 'Add Schedule')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" class="form-control" id="schedule-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_schedules', 'Search by title or speaker...')); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <select class="form-control" id="event-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Schedules List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="schedules-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?></th>
                                    <th><?php echo esc_html(sc_t('nav.events', 'Event')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.date', 'Date')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.time', 'Time')); ?></th>
                                    <th><?php echo esc_html(sc_t('nav.halls', 'Hall')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.speaker', 'Speaker')); ?></th>
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
                            <span id="pagination-info-text"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></span>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="schedules-pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var schedulesT = {
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    no_schedules: '<?php echo esc_js(sc_t('dashboard_pages.no_schedules_found', 'No schedules found. Click "Add Schedule" to create one.')); ?>',
    showing: '<?php echo esc_js(sc_t('dashboard_pages.showing_to_of', 'Showing {start} to {end} of {total}')); ?>',
    error_deleting: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_schedule', 'Error deleting schedule')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let currentSearch = '';
    let currentEventId = '';
    let searchTimeout;

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function loadSchedulesPage(page) {
        currentPage = page;
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_schedules_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: 50,
                search: currentSearch,
                event_id: currentEventId
            },
            beforeSend: function() {
                $('#schedules-table tbody').html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><p class="mt-3">' + schedulesT.loading + '</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderTable(response.data.schedules);
                renderPagination(response.data.current_page, response.data.pages);
                updateInfo(response.data.total, response.data.current_page, 50);
            }
        });
    }

    function renderTable(schedules) {
        const tbody = $('#schedules-table tbody');
        tbody.empty();

        if (!schedules || schedules.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4">' + schedulesT.no_schedules + '</td></tr>');
            return;
        }

        schedules.forEach(function(s) {
            const row = `
                <tr>
                    <td><strong>${escapeHtml(s.title)}</strong></td>
                    <td>${escapeHtml(s.event_title || '-')}</td>
                    <td>${escapeHtml(s.schedule_date)}</td>
                    <td>${escapeHtml(s.start_time)} - ${escapeHtml(s.end_time)}</td>
                    <td>${escapeHtml(s.hall_name || '-')}</td>
                    <td>${escapeHtml(s.speaker_name || '-')}</td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/schedule-edit'); ?>?id=${s.id}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/schedule-edit'); ?>?id=${s.id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-schedule" href="javascript:void(0);" data-id="${s.id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function renderPagination(current, total) {
        const pagination = $('#schedules-pagination');
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
            if (page && page !== current) loadSchedulesPage(page);
        });
    }

    function updateInfo(total, page, perPage) {
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        $('#pagination-info-text').text(schedulesT.showing.replace('{start}', start).replace('{end}', end).replace('{total}', total));
    }

    $(document).on('click', '.delete-schedule', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) return;
            const id = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);
            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: { action: 'sc_delete_schedule', nonce: scDashboard.nonce, schedule_id: id }
            }).done(function(response) {
                if (response.success) {
                    loadSchedulesPage(currentPage);
                } else {
                    toastr.error(response.data.message || schedulesT.error_deleting);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    $('#schedule-search').on('input', function() {
        clearTimeout(searchTimeout);
        const val = $(this).val().trim();
        searchTimeout = setTimeout(function() { currentSearch = val; loadSchedulesPage(1); }, 500);
    });

    $('#event-filter').on('change', function() {
        currentEventId = $(this).val();
        loadSchedulesPage(1);
    });

    loadSchedulesPage(1);
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
