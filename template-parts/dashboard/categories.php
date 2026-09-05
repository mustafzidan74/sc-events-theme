<?php
/**
 * Event Categories Management Page
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

$page_title = sc_t('dashboard_pages.event_categories', 'Event Categories');

// Translations
$t = array(
    'event_categories' => sc_t('dashboard_pages.event_categories', 'Event Categories'),
    'add_category' => sc_t('dashboard_pages.add_category', 'Add Category'),
    'search_categories' => sc_t('dashboard_pages.search_categories', 'Search categories by name...'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'slug' => sc_t('dashboard_pages.slug', 'Slug'),
    'description' => sc_t('dashboard_pages.description', 'Description'),
    'events_count' => sc_t('dashboard_pages.events_count', 'Events Count'),
    'actions' => sc_t('dashboard_pages.actions', 'Actions'),
    'loading_categories' => sc_t('dashboard_pages.loading_categories', 'Loading categories...'),
    'category_name' => sc_t('dashboard_pages.category_name', 'Category Name'),
    'leave_empty_auto' => sc_t('dashboard_pages.leave_empty_auto', 'Leave empty to auto-generate from name'),
    'category_color' => sc_t('dashboard_pages.category_color', 'Category Color'),
    'choose_color' => sc_t('dashboard_pages.choose_color', 'Choose a color for this category'),
    'cancel' => sc_t('dashboard_pages.cancel', 'Cancel'),
    'save_category' => sc_t('dashboard_pages.save_category', 'Save Category'),
    'edit_category' => sc_t('dashboard_pages.edit_category', 'Edit Category'),
    'events' => sc_t('dashboard_pages.events', 'events'),
    'no_categories' => sc_t('dashboard_pages.no_categories', 'No categories found. Click "Add Category" to create one.'),
    'error_loading' => sc_t('dashboard_pages.error_loading_categories', 'Error loading categories. Please try again.'),
    'error_deleting' => sc_t('dashboard_pages.error_deleting_category', 'Error deleting category'),
    'saving' => sc_t('dashboard_pages.saving', 'Saving...'),
    'error_saving' => sc_t('dashboard_pages.error_saving_category', 'Error saving category'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['event_categories']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['event_categories']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <button type="button" class="btn btn-primary" id="create-category-btn">
                            <i class="fa fa-plus"></i> <?php echo $t['add_category']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" class="form-control" id="categories-search" placeholder="<?php echo esc_attr($t['search_categories']); ?>">
            </div>
        </div>
    </div>

    <!-- Categories List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="categories-table">
                            <thead>
                                <tr>
                                    <th><?php echo $t['name']; ?></th>
                                    <th><?php echo $t['slug']; ?></th>
                                    <th><?php echo $t['description']; ?></th>
                                    <th><?php echo $t['events_count']; ?></th>
                                    <th width="150"><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo $t['loading_categories']; ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="row mt-3" id="categories-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="categories-showing-info"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="categories-pagination">
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

<!-- Category Add/Edit Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="category-modal-title">
                    <i class="fa fa-folder"></i> <?php echo $t['add_category']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="category-form">
                <div class="modal-body">
                    <input type="hidden" id="category-id" name="category_id">

                    <div class="form-group">
                        <label for="category-name"><?php echo $t['category_name']; ?> *</label>
                        <input type="text" class="form-control" id="category-name" name="category_name" required>
                    </div>

                    <div class="form-group">
                        <label for="category-slug"><?php echo $t['slug']; ?></label>
                        <input type="text" class="form-control" id="category-slug" name="category_slug">
                        <small class="form-text text-muted"><?php echo $t['leave_empty_auto']; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="category-description"><?php echo $t['description']; ?></label>
                        <textarea class="form-control" id="category-description" name="category_description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="category-color"><?php echo $t['category_color']; ?></label>
                        <div class="d-flex align-items-center">
                            <input type="color" class="form-control" id="category-color" name="category_color" value="#52C41A" style="width: 80px; height: 40px; margin-right: 10px;">
                            <input type="text" class="form-control" id="category-color-text" readonly value="#52C41A" style="width: 100px;">
                        </div>
                        <small class="form-text text-muted"><?php echo $t['choose_color']; ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
                    </button>
                    <button type="submit" class="btn btn-primary" id="save-category-btn">
                        <i class="fa fa-save"></i> <?php echo $t['save_category']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Categories Translations
var categoriesTranslations = {
    add_category: '<?php echo esc_js($t['add_category']); ?>',
    edit_category: '<?php echo esc_js($t['edit_category']); ?>',
    events: '<?php echo esc_js($t['events']); ?>',
    no_categories: '<?php echo esc_js($t['no_categories']); ?>',
    error_loading: '<?php echo esc_js($t['error_loading']); ?>',
    error_deleting: '<?php echo esc_js($t['error_deleting']); ?>',
    saving: '<?php echo esc_js($t['saving']); ?>',
    save_category: '<?php echo esc_js($t['save_category']); ?>',
    error_saving: '<?php echo esc_js($t['error_saving']); ?>',
    loading: '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    showing: '<?php echo esc_js(sc_t('dashboard_pages.showing', 'Showing')); ?>',
    of: '<?php echo esc_js(sc_t('dashboard_pages.of', 'of')); ?>',
    previous: '<?php echo esc_js(sc_t('dashboard_pages.previous', 'Previous')); ?>',
    next: '<?php echo esc_js(sc_t('dashboard_pages.next', 'Next')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    // Pagination state
    let currentPage = 1;
    let perPage = 50;
    let searchTerm = '';
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

    // Load categories with AJAX pagination
    function loadCategories(page = 1) {
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_categories_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: perPage,
                search: searchTerm
            },
            beforeSend: function() {
                const tbody = $('#categories-table tbody');
                tbody.html('<tr><td colspan="5" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">' + categoriesTranslations.loading + '</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderCategoriesTable(response.data.categories);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
            }
        }).fail(function() {
            const tbody = $('#categories-table tbody');
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-danger">' + categoriesTranslations.error_loading + '</td></tr>');
        });
    }

    function renderCategoriesTable(categories) {
        const tbody = $('#categories-table tbody');
        tbody.empty();

        if (!categories || categories.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center py-4">' + categoriesTranslations.no_categories + '</td></tr>');
            $('#categories-pagination-controls').hide();
            return;
        }

        categories.forEach(function(category) {
            const row = `
                <tr>
                    <td><strong>${escapeHtml(category.name)}</strong></td>
                    <td><code>${escapeHtml(category.slug)}</code></td>
                    <td>${escapeHtml(category.description || '-')}</td>
                    <td><span class="badge badge-info">${category.count || 0} ${categoriesTranslations.events}</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary edit-category" data-id="${category.term_id}" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item edit-category" href="javascript:void(0);" data-id="${category.term_id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-category" href="javascript:void(0);" data-id="${category.term_id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
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
        $('#categories-pagination-controls').show();

        // Update showing info
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#categories-showing-info').text(`${categoriesTranslations.showing} ${start}-${end} ${categoriesTranslations.of} ${total}`);

        // Build pagination buttons
        const paginationHtml = [];

        // Previous button
        paginationHtml.push(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">${categoriesTranslations.previous}</a>
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
                <a class="page-link" href="#" data-page="${current + 1}">${categoriesTranslations.next}</a>
            </li>
        `);

        $('#categories-pagination').html(paginationHtml.join(''));
    }

    // Pagination click handler
    $(document).on('click', '#categories-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadCategories(page);
        }
    });

    // Search handler with debounce
    let searchTimeout;
    $('#categories-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchTerm = $('#categories-search').val();
            loadCategories(1);
        }, 500);
    });

    // Create category
    $('#create-category-btn').on('click', function() {
        $('#category-modal-title').html('<i class="fa fa-folder"></i> ' + categoriesTranslations.add_category);
        $('#category-form')[0].reset();
        $('#category-id').val('');

        // Show modal with fallback
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#categoryModal').modal('show');
            } else {
                $('#categoryModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#categoryModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    });

    // Edit category
    $(document).on('click', '.edit-category', function() {
        const categoryId = $(this).data('id');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_category',
                nonce: scDashboard.nonce,
                category_id: categoryId
            }
        }).done(function(response) {
            if (response.success) {
                const category = response.data.category;

                $('#category-modal-title').html('<i class="fa fa-edit"></i> ' + categoriesTranslations.edit_category);
                $('#category-id').val(category.term_id);
                $('#category-name').val(category.name);
                $('#category-slug').val(category.slug);
                $('#category-description').val(category.description);

                // Set color (default to #52C41A if not set)
                const color = category.color || '#52C41A';
                $('#category-color').val(color);
                $('#category-color-text').val(color.toUpperCase());

                // Show modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#categoryModal').modal('show');
                    } else {
                        $('#categoryModal').addClass('show').css('display', 'block');
                        $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                    }
                } catch (e) {
                    $('#categoryModal').addClass('show').css('display', 'block');
                    $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
                }
            }
        });
    });

    // Delete category
    $(document).on('click', '.delete-category', function() {
        const categoryId = $(this).data('id');
        const btn = $(this);

        showDeleteConfirm().then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true);

                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sc_delete_category',
                        nonce: scDashboard.nonce,
                        category_id: categoryId
                    }
                }).done(function(response) {
                    if (response.success) {
                        loadCategories();
                    } else {
                        showError(response.data.message || categoriesTranslations.error_deleting);
                        btn.prop('disabled', false);
                    }
                });
            }
        });
    });

    // Save category
    $('#category-form').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#save-category-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + categoriesTranslations.saving);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_save_category',
                nonce: scDashboard.nonce,
                category_id: $('#category-id').val(),
                category_name: $('#category-name').val(),
                category_slug: $('#category-slug').val(),
                category_description: $('#category-description').val(),
                category_color: $('#category-color').val()
            }
        }).done(function(response) {
            if (response.success) {
                // Close modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#categoryModal').modal('hide');
                    } else {
                        $('#categoryModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#categoryModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                loadCategories();
            } else {
                showError(response.data.message || categoriesTranslations.error_saving);
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + categoriesTranslations.save_category);
        });
    });

    // Auto-generate slug from name
    $('#category-name').on('blur', function() {
        if ($('#category-slug').val() === '') {
            const name = $(this).val();
            const slug = name.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/--+/g, '-')
                .trim();
            $('#category-slug').val(slug);
        }
    });

    // Update color text field when color picker changes
    $('#category-color').on('input change', function() {
        $('#category-color-text').val($(this).val().toUpperCase());
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
    loadCategories();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
