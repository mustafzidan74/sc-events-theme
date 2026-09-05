<?php
/**
 * Workshops Management Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.workshops', 'Workshops');

// Get all events for filter dropdown
$events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 500,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html($page_title); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html($page_title); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/workshop-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_workshop', 'Add Workshop')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" id="workshops-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_workshops', 'Search workshops...')); ?>">
        </div>
        <div class="col-md-4">
            <select class="form-control" id="workshops-event-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                <?php foreach ($events as $ev): ?>
                <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-control" id="workshops-status-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_status', 'All Status')); ?></option>
                <option value="publish"><?php echo esc_html(sc_t('dashboard_pages.publish', 'Published')); ?></option>
                <option value="draft"><?php echo esc_html(sc_t('dashboard_pages.draft', 'Draft')); ?></option>
                <option value="completed"><?php echo esc_html(sc_t('dashboard_pages.completed', 'Completed')); ?></option>
                <option value="cancelled"><?php echo esc_html(sc_t('dashboard_pages.cancelled', 'Cancelled')); ?></option>
            </select>
        </div>
    </div>

    <!-- Workshops List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.event', 'Event')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.date', 'Date')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.sold', 'Sold')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked In')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                    <th width="200"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody id="workshops-tbody">
                                <tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3" id="workshops-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <span id="workshops-showing-info"></span>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="workshops-pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    const perPage = 20;
    let searchTerm = '';
    let eventFilter = '';
    let statusFilter = '';
    let totalPages = 1;

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function statusBadge(status) {
        const map = {
            publish:   '<span class="badge badge-success">Published</span>',
            draft:     '<span class="badge badge-secondary">Draft</span>',
            completed: '<span class="badge badge-info">Completed</span>',
            cancelled: '<span class="badge badge-danger">Cancelled</span>',
            disabled:  '<span class="badge badge-warning">Disabled</span>',
            private:   '<span class="badge badge-dark">Private</span>',
        };
        return map[status] || '<span class="badge badge-light">' + escapeHtml(status) + '</span>';
    }

    function loadWorkshops(page) {
        page = page || 1;
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_workshops_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: perPage,
                search: searchTerm,
                event_id: eventFilter,
                status: statusFilter
            },
            beforeSend: function() {
                $('#workshops-tbody').html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderTable(response.data.workshops);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
            }
        });
    }

    function renderTable(rows) {
        const tbody = $('#workshops-tbody');
        tbody.empty();

        if (!rows || rows.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No workshops found.</td></tr>');
            $('#workshops-pagination-controls').hide();
            return;
        }

        rows.forEach(function(w) {
            const editUrl = '<?php echo home_url('/event-manager-dashboard/workshop-edit'); ?>?id=' + w.id;
            const attUrl = '<?php echo home_url('/event-manager-dashboard/workshop-attendees'); ?>?workshop_id=' + w.id;
            const scanUrl = '<?php echo home_url('/event-manager-dashboard/workshop-scanner'); ?>?workshop_id=' + w.id;

            const row = '<tr>' +
                '<td><strong>' + escapeHtml(w.title) + '</strong></td>' +
                '<td>' + escapeHtml(w.event_title || '-') + '</td>' +
                '<td>' + escapeHtml(w.start_date) + (w.end_date && w.end_date !== w.start_date ? ' → ' + escapeHtml(w.end_date) : '') + '</td>' +
                '<td><span class="badge badge-secondary">' + (w.total_sold || 0) + (w.total_capacity > 0 ? ' / ' + w.total_capacity : '') + '</span></td>' +
                '<td><span class="badge badge-success">' + (w.total_checked_in || 0) + '</span></td>' +
                '<td>' + statusBadge(w.status) + '</td>' +
                '<td>' +
                    '<div class="btn-group btn-group-sm">' +
                        '<a href="' + editUrl + '" class="btn btn-primary" title="Edit"><i class="fa fa-edit"></i></a>' +
                        '<a href="' + attUrl + '" class="btn btn-info" title="Attendees"><i class="fa fa-users"></i></a>' +
                        '<a href="' + scanUrl + '" class="btn btn-success" title="Scanner"><i class="fa fa-qrcode"></i></a>' +
                        '<button type="button" class="btn btn-danger workshop-delete" data-id="' + w.id + '" title="Delete"><i class="fa fa-trash"></i></button>' +
                    '</div>' +
                '</td>' +
                '</tr>';
            tbody.append(row);
        });
    }

    function updatePagination(total, pages, current) {
        totalPages = pages;
        $('#workshops-pagination-controls').toggle(total > 0);
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#workshops-showing-info').text('Showing ' + start + '-' + end + ' of ' + total);

        const html = [];
        html.push('<li class="page-item ' + (current === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current - 1) + '">&laquo;</a></li>');
        for (let i = 1; i <= pages; i++) {
            html.push('<li class="page-item ' + (i === current ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>');
        }
        html.push('<li class="page-item ' + (current === pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current + 1) + '">&raquo;</a></li>');
        $('#workshops-pagination').html(html.join(''));
    }

    $(document).on('click', '#workshops-pagination a.page-link', function(e) {
        e.preventDefault();
        const p = parseInt($(this).data('page'));
        if (p && p !== currentPage && p >= 1 && p <= totalPages) loadWorkshops(p);
    });

    let searchTO;
    $('#workshops-search').on('keyup', function() {
        clearTimeout(searchTO);
        searchTO = setTimeout(function() {
            searchTerm = $('#workshops-search').val();
            loadWorkshops(1);
        }, 400);
    });

    $('#workshops-event-filter, #workshops-status-filter').on('change', function() {
        eventFilter = $('#workshops-event-filter').val();
        statusFilter = $('#workshops-status-filter').val();
        loadWorkshops(1);
    });

    $(document).on('click', '.workshop-delete', function() {
        const id = $(this).data('id');
        const btn = $(this);
        if (!confirm('Delete this workshop? All its tickets, attendees, and check-ins will be deleted.')) return;

        btn.prop('disabled', true);
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: { action: 'sc_delete_workshop', nonce: scDashboard.nonce, id: id }
        }).done(function(resp) {
            if (resp.success) {
                loadWorkshops(currentPage);
            } else {
                alert(resp.data.message || 'Failed to delete workshop');
                btn.prop('disabled', false);
            }
        });
    });

    loadWorkshops();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
