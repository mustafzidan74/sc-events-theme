<?php
/**
 * Organizers Management Page
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

$page_title = sc_t('dashboard_pages.organizers_management', 'Organizers Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Translations
$t = array(
    'organizers_management' => sc_t('dashboard_pages.organizers_management', 'Organizers Management'),
    'organizers' => sc_t('nav.organizers', 'Organizers'),
    'add_organizer' => sc_t('dashboard_pages.add_organizer', 'Add Organizer'),
    'search_organizers' => sc_t('dashboard_pages.search_organizers', 'Search organizers by name or email...'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All Events'),
    'logo' => sc_t('dashboard_pages.logo', 'Logo'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'company_url' => sc_t('dashboard_pages.company_url', 'Company URL'),
    'events' => sc_t('nav.events', 'Events'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading organizers...'),
);
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['organizers_management']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['organizers']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/organizer-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo $t['add_organizer']; ?>
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
                <input type="text" class="form-control" id="organizers-search" placeholder="<?php echo $t['search_organizers']; ?>">
            </div>
        </div>
        <div class="col-md-6">
            <select class="form-control" id="organizers-event-filter">
                <option value=""><?php echo $t['all_events']; ?></option>
                <?php
                // Get all events from custom table
                global $wpdb;
                $events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status = 'publish' ORDER BY title ASC");
                foreach ($events as $event): ?>
                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Organizers List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="organizers-table">
                            <thead>
                                <tr>
                                    <th width="60"><?php echo $t['logo']; ?></th>
                                    <th><?php echo $t['name']; ?></th>
                                    <th><?php echo $t['email']; ?></th>
                                    <th><?php echo $t['company_url']; ?></th>
                                    <th><?php echo $t['events']; ?></th>
                                    <th width="150"><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo $t['loading']; ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="row mt-3" id="organizers-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="organizers-showing-info"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="organizers-pagination">
                                    <!-- Pagination buttons will be inserted here -->
                                </ul>
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

    // Pagination state
    let currentPage = 1;
    let perPage = 50;
    let searchTerm = '';
    let eventFilter = '';
    let totalPages = 1;

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

    // Load organizers with AJAX pagination
    function loadOrganizers(page = 1) {
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_organizers_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: perPage,
                search: searchTerm,
                event_id: eventFilter
            },
            beforeSend: function() {
                const tbody = $('#organizers-table tbody');
                tbody.html('<tr><td colspan="6" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">Loading...</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderOrganizersTable(response.data.organizers);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
            }
        }).fail(function() {
            const tbody = $('#organizers-table tbody');
            tbody.html('<tr><td colspan="6" class="text-center py-4 text-danger">Error loading organizers. Please try again.</td></tr>');
        });
    }

    function renderOrganizersTable(organizers) {
        const tbody = $('#organizers-table tbody');
        tbody.empty();

        if (!organizers || organizers.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4">No organizers found. Click "Add Organizer" to create one.</td></tr>');
            $('#organizers-pagination-controls').hide();
            return;
        }

        organizers.forEach(function(organizer) {
            const logoUrl = organizer.logo_url || scDashboard.themeUrl + '/assets/images/default-org.png';

            const row = `
                <tr>
                    <td><img src="${logoUrl}" class="rounded" width="40" height="40" style="object-fit: cover;"></td>
                    <td><strong>${escapeHtml(organizer.name)}</strong></td>
                    <td>${escapeHtml(organizer.email || '-')}</td>
                    <td>${organizer.website ? '<a href="' + organizer.website + '" target="_blank"><i class="fa fa-external-link"></i></a>' : '-'}</td>
                    <td><span class="badge badge-info">${organizer.events_count || 0} events</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/organizer-edit'); ?>?id=${organizer.ID}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/organizer-edit'); ?>?id=${organizer.ID}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-organizer" href="javascript:void(0);" data-id="${organizer.ID}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
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
        $('#organizers-pagination-controls').show();

        // Update showing info
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#organizers-showing-info').text(`Showing ${start}-${end} of ${total}`);

        // Build pagination buttons
        const paginationHtml = [];

        // Previous button
        paginationHtml.push(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">Previous</a>
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
                <a class="page-link" href="#" data-page="${current + 1}">Next</a>
            </li>
        `);

        $('#organizers-pagination').html(paginationHtml.join(''));
    }

    // Pagination click handler
    $(document).on('click', '#organizers-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadOrganizers(page);
        }
    });

    // Event filter handler
    $('#organizers-event-filter').on('change', function() {
        eventFilter = $(this).val();
        loadOrganizers(1);
    });

    // Search handler with debounce
    let searchTimeout;
    $('#organizers-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchTerm = $('#organizers-search').val();
            loadOrganizers(1);
        }, 500);
    });

    // Note: Create and Edit organizer buttons are now links to organizer-create and organizer-edit pages

    // Delete organizer
    $(document).on('click', '.delete-organizer', function() {
        const organizerId = $(this).data('id');
        const btn = $(this);

        showDeleteConfirm().then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true);

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_delete_organizer',
                        nonce: scDashboard.nonce,
                        organizer_id: organizerId
                    }
                }).done(function(response) {
                    if (response.success) {
                        loadOrganizers();
                    } else {
                        showError(response.data.message || 'Error deleting organizer');
                        btn.prop('disabled', false);
                    }
                });
            }
        });
    });

    // Save organizer
    $('#organizer-form').on('submit', function(e) {
        e.preventDefault();

        // Check if logo is uploaded (for new organizers) or exists (for editing)
        const hasLogo = $('#organizer-has-logo').val() === '1';
        const logoFile = $('#organizer-logo')[0].files[0];

        if (!hasLogo && !logoFile) {
            showError('Please upload a logo image.');
            return false;
        }

        const formData = new FormData(this);
        formData.append('action', 'sc_save_organizer');
        formData.append('nonce', scDashboard.nonce);

        const btn = $('#save-organizer-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            if (response.success) {
                // Close modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#organizerModal').modal('hide');
                    } else {
                        $('#organizerModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#organizerModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                loadOrganizers();
            } else {
                showError(response.data.message || 'Error saving organizer');
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Organizer');
        });
    });

    // Logo preview
    $('#organizer-logo').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#organizer-logo-preview img').attr('src', e.target.result);
                $('#organizer-logo-preview').show();
            };
            reader.readAsDataURL(file);
            $('.custom-file-label').text(file.name);
        }
    });

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

    // Initial load
    loadOrganizers();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
