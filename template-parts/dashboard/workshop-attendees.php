<?php
/**
 * Workshop Attendees Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
if (!$workshop_id) {
    wp_redirect(home_url('/event-manager-dashboard/workshops'));
    exit;
}

$workshop = SC_Workshop::get($workshop_id);
if (!$workshop) {
    wp_die('Workshop not found.');
}

$page_title = 'Attendees: ' . esc_html($workshop->title);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-8">
                <h2><?php echo $page_title; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/workshops'); ?>">Workshops</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/workshop-edit?id=' . $workshop_id); ?>"><?php echo esc_html($workshop->title); ?></a></li>
                    <li class="breadcrumb-item active">Attendees</li>
                </ul>
            </div>
            <div class="col-lg-4 text-right">
                <a href="<?php echo home_url('/event-manager-dashboard/workshop-scanner?workshop_id=' . $workshop_id); ?>" class="btn btn-success">
                    <i class="fa fa-qrcode"></i> Open Scanner
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3"><div class="card"><div class="body text-center"><h3><?php echo (int) $workshop->total_sold; ?></h3><p class="text-muted mb-0">Sold</p></div></div></div>
        <div class="col-md-3"><div class="card"><div class="body text-center"><h3><?php echo (int) $workshop->total_checked_in; ?></h3><p class="text-muted mb-0">Checked In</p></div></div></div>
        <div class="col-md-3"><div class="card"><div class="body text-center"><h3><?php echo number_format((float) $workshop->total_revenue, 2); ?></h3><p class="text-muted mb-0">Revenue</p></div></div></div>
        <div class="col-md-3"><div class="card"><div class="body text-center"><h3><?php echo (int) $workshop->total_capacity ?: '∞'; ?></h3><p class="text-muted mb-0">Capacity</p></div></div></div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="att-search" placeholder="Search by name, email, phone, ticket code...">
        </div>
        <div class="col-md-3">
            <select class="form-control" id="att-checked-in">
                <option value="">All</option>
                <option value="used">Checked In</option>
                <option value="not_used">Not Checked In</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-control" id="att-payment-status">
                <option value="">All Payments</option>
                <option value="success">Success</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Ticket Code</th>
                            <th>Payment</th>
                            <th>Checked In</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="att-tbody">
                        <tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div id="att-pagination-wrap" class="row mt-3" style="display:none;">
                <div class="col-md-6"><span id="att-info"></span></div>
                <div class="col-md-6 text-right"><nav><ul class="pagination justify-content-end mb-0" id="att-pagination"></ul></nav></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    const workshopId = <?php echo (int) $workshop_id; ?>;
    let currentPage = 1;
    const perPage = 25;
    let totalPages = 1;
    let searchTerm = '';
    let ticketStatus = '';
    let paymentStatus = '';

    function escapeHtml(t) {
        if (!t) return '';
        const m = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(t).replace(/[&<>"']/g, c => m[c]);
    }

    function load(page) {
        page = page || 1;
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: {
                action: 'sc_get_attendees_paginated',
                nonce: scDashboard.nonce,
                workshop_id: workshopId,
                page: page,
                per_page: perPage,
                search: searchTerm,
                ticket_status: ticketStatus,
                status: paymentStatus
            },
            beforeSend: function() {
                $('#att-tbody').html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></td></tr>');
            }
        }).done(function(resp) {
            if (resp.success) {
                renderTable(resp.data.attendees || resp.data.rows || []);
                renderPagination(resp.data.total || 0, resp.data.pages || 1, resp.data.current_page || page);
            }
        });
    }

    function renderTable(rows) {
        const $tbody = $('#att-tbody');
        $tbody.empty();
        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No attendees yet.</td></tr>');
            $('#att-pagination-wrap').hide();
            return;
        }
        rows.forEach(function(a) {
            const checkedIn = parseInt(a.checked_in) === 1;
            const payment = a.payment_status === 'success'
                ? '<span class="badge badge-success">Success</span>'
                : (a.payment_status === 'pending' ? '<span class="badge badge-warning">Pending</span>' : '<span class="badge badge-danger">' + escapeHtml(a.payment_status || 'unknown') + '</span>');
            const ci = checkedIn ? '<span class="badge badge-success"><i class="fa fa-check"></i> Yes</span>' : '<span class="badge badge-secondary">No</span>';
            const actionBtn = checkedIn
                ? '<button class="btn btn-sm btn-warning undo-checkin-btn" data-id="' + a.id + '"><i class="fa fa-undo"></i> Undo</button>'
                : '<button class="btn btn-sm btn-success manual-checkin-btn" data-id="' + a.id + '"><i class="fa fa-check"></i> Check In</button>';

            const row = '<tr>' +
                '<td><strong>' + escapeHtml(a.name) + '</strong></td>' +
                '<td>' + escapeHtml(a.email || '-') + '</td>' +
                '<td>' + escapeHtml(a.phone || '-') + '</td>' +
                '<td><code>' + escapeHtml(a.ticket_code || '-') + '</code></td>' +
                '<td>' + payment + '</td>' +
                '<td>' + ci + '</td>' +
                '<td>' + actionBtn + '</td>' +
                '</tr>';
            $tbody.append(row);
        });
    }

    function renderPagination(total, pages, current) {
        totalPages = pages;
        $('#att-pagination-wrap').toggle(total > 0);
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#att-info').text('Showing ' + start + '-' + end + ' of ' + total);

        const html = [];
        html.push('<li class="page-item ' + (current === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current - 1) + '">&laquo;</a></li>');
        const startPage = Math.max(1, current - 3);
        const endPage = Math.min(pages, current + 3);
        for (let i = startPage; i <= endPage; i++) {
            html.push('<li class="page-item ' + (i === current ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>');
        }
        html.push('<li class="page-item ' + (current === pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current + 1) + '">&raquo;</a></li>');
        $('#att-pagination').html(html.join(''));
    }

    $(document).on('click', '#att-pagination a.page-link', function(e) {
        e.preventDefault();
        const p = parseInt($(this).data('page'));
        if (p && p !== currentPage && p >= 1 && p <= totalPages) load(p);
    });

    let searchTO;
    $('#att-search').on('keyup', function() {
        clearTimeout(searchTO);
        searchTO = setTimeout(function() {
            searchTerm = $('#att-search').val();
            load(1);
        }, 400);
    });
    $('#att-checked-in').on('change', function() {
        ticketStatus = $(this).val();
        load(1);
    });
    $('#att-payment-status').on('change', function() {
        paymentStatus = $(this).val();
        load(1);
    });

    $(document).on('click', '.manual-checkin-btn', function() {
        const id = $(this).data('id');
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: { action: 'sc_checkin_attendee', nonce: scDashboard.nonce, attendee_id: id }
        }).done(function(resp) {
            if (resp.success) load(currentPage);
            else { alert(resp.data.message || 'Failed'); $btn.prop('disabled', false); }
        });
    });

    $(document).on('click', '.undo-checkin-btn', function() {
        if (!confirm('Undo this check-in?')) return;
        const id = $(this).data('id');
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: { action: 'sc_undo_checkin', nonce: scDashboard.nonce, attendee_id: id }
        }).done(function(resp) {
            if (resp.success) load(currentPage);
            else { alert(resp.data.message || 'Failed'); $btn.prop('disabled', false); }
        });
    });

    load();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
