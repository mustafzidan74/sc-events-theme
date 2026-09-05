<?php
/**
 * Coupon Categories Management Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.coupon_categories', 'Coupon Categories');

$t = array(
    'coupon_categories'  => sc_t('dashboard_pages.coupon_categories', 'Coupon Categories'),
    'add_category'       => sc_t('dashboard_pages.add_coupon_category', 'Add Category'),
    'search_categories'  => sc_t('dashboard_pages.search_categories', 'Search categories by name...'),
    'name'               => sc_t('dashboard_pages.name', 'Name'),
    'slug'               => sc_t('dashboard_pages.slug', 'Slug'),
    'description'        => sc_t('dashboard_pages.description', 'Description'),
    'coupons_count'      => sc_t('dashboard_pages.coupons_count', 'Coupons Count'),
    'actions'            => sc_t('dashboard_pages.actions', 'Actions'),
    'loading_categories' => sc_t('dashboard_pages.loading_categories', 'Loading categories...'),
    'category_name'      => sc_t('dashboard_pages.category_name', 'Category Name'),
    'leave_empty_auto'   => sc_t('dashboard_pages.leave_empty_auto', 'Leave empty to auto-generate from name'),
    'category_color'     => sc_t('dashboard_pages.category_color', 'Category Color'),
    'choose_color'       => sc_t('dashboard_pages.choose_color', 'Choose a color for this category'),
    'cancel'             => sc_t('dashboard_pages.cancel', 'Cancel'),
    'save_category'      => sc_t('dashboard_pages.save_category', 'Save Category'),
    'edit_category'      => sc_t('dashboard_pages.edit_category', 'Edit Category'),
    'coupons'            => sc_t('dashboard_pages.coupons', 'coupons'),
    'no_categories'      => sc_t('dashboard_pages.no_coupon_categories', 'No categories found. Click "Add Category" to create one.'),
    'error_loading'      => sc_t('dashboard_pages.error_loading_categories', 'Error loading categories. Please try again.'),
    'error_deleting'     => sc_t('dashboard_pages.error_deleting_category', 'Error deleting category'),
    'saving'             => sc_t('dashboard_pages.saving', 'Saving...'),
    'error_saving'       => sc_t('dashboard_pages.error_saving_category', 'Error saving category'),
    'protected'          => sc_t('dashboard_pages.protected', 'Protected'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['coupon_categories']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/coupons'); ?>"><?php echo esc_html(sc_t('nav.coupons', 'Coupons')); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['coupon_categories']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <button type="button" class="btn btn-primary" id="create-coupon-category-btn">
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
                <input type="text" class="form-control" id="coupon-categories-search" placeholder="<?php echo esc_attr($t['search_categories']); ?>">
            </div>
        </div>
    </div>

    <!-- Categories List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="coupon-categories-table">
                            <thead>
                                <tr>
                                    <th width="40"></th>
                                    <th><?php echo $t['name']; ?></th>
                                    <th><?php echo $t['slug']; ?></th>
                                    <th><?php echo $t['description']; ?></th>
                                    <th><?php echo $t['coupons_count']; ?></th>
                                    <th width="150"><?php echo $t['actions']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo $t['loading_categories']; ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="row mt-3" id="coupon-categories-pagination-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="coupon-categories-showing-info"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="coupon-categories-pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Add/Edit Modal -->
<div class="modal fade" id="couponCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="coupon-category-modal-title">
                    <i class="fa fa-folder"></i> <?php echo $t['add_category']; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="coupon-category-form">
                <div class="modal-body">
                    <input type="hidden" id="coupon-category-id" name="id">

                    <div class="form-group">
                        <label for="coupon-category-name"><?php echo $t['category_name']; ?> *</label>
                        <input type="text" class="form-control" id="coupon-category-name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="coupon-category-slug"><?php echo $t['slug']; ?></label>
                        <input type="text" class="form-control" id="coupon-category-slug" name="slug">
                        <small class="form-text text-muted"><?php echo $t['leave_empty_auto']; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="coupon-category-description"><?php echo $t['description']; ?></label>
                        <textarea class="form-control" id="coupon-category-description" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="coupon-category-color"><?php echo $t['category_color']; ?></label>
                        <div class="d-flex align-items-center">
                            <input type="color" class="form-control" id="coupon-category-color" name="color" value="#7c1314" style="width: 80px; height: 40px; margin-right: 10px;">
                            <input type="text" class="form-control" id="coupon-category-color-text" readonly value="#7C1314" style="width: 100px;">
                        </div>
                        <small class="form-text text-muted"><?php echo $t['choose_color']; ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> <?php echo $t['cancel']; ?>
                    </button>
                    <button type="submit" class="btn btn-primary" id="save-coupon-category-btn">
                        <i class="fa fa-save"></i> <?php echo $t['save_category']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var couponCategoriesTranslations = {
    add_category:    '<?php echo esc_js($t['add_category']); ?>',
    edit_category:   '<?php echo esc_js($t['edit_category']); ?>',
    coupons:         '<?php echo esc_js($t['coupons']); ?>',
    no_categories:   '<?php echo esc_js($t['no_categories']); ?>',
    error_loading:   '<?php echo esc_js($t['error_loading']); ?>',
    error_deleting:  '<?php echo esc_js($t['error_deleting']); ?>',
    saving:          '<?php echo esc_js($t['saving']); ?>',
    save_category:   '<?php echo esc_js($t['save_category']); ?>',
    error_saving:    '<?php echo esc_js($t['error_saving']); ?>',
    protected_label: '<?php echo esc_js($t['protected']); ?>',
    loading:         '<?php echo esc_js(sc_t('dashboard_pages.loading', 'Loading...')); ?>',
    showing:         '<?php echo esc_js(sc_t('dashboard_pages.showing', 'Showing')); ?>',
    of:              '<?php echo esc_js(sc_t('dashboard_pages.of', 'of')); ?>',
    previous:        '<?php echo esc_js(sc_t('dashboard_pages.previous', 'Previous')); ?>',
    next:            '<?php echo esc_js(sc_t('dashboard_pages.next', 'Next')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let perPage = 50;
    let searchTerm = '';
    let totalPages = 1;

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function loadCategories(page) {
        page = page || 1;
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_coupon_categories_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: perPage,
                search: searchTerm
            },
            beforeSend: function() {
                $('#coupon-categories-table tbody').html(
                    '<tr><td colspan="6" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3">' + couponCategoriesTranslations.loading + '</p></td></tr>'
                );
            }
        }).done(function(response) {
            if (response.success) {
                renderTable(response.data.categories);
                updatePagination(response.data.total, response.data.pages, response.data.current_page);
            }
        }).fail(function() {
            $('#coupon-categories-table tbody').html('<tr><td colspan="6" class="text-center py-4 text-danger">' + couponCategoriesTranslations.error_loading + '</td></tr>');
        });
    }

    function renderTable(categories) {
        const tbody = $('#coupon-categories-table tbody');
        tbody.empty();

        if (!categories || categories.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4">' + couponCategoriesTranslations.no_categories + '</td></tr>');
            $('#coupon-categories-pagination-controls').hide();
            return;
        }

        categories.forEach(function(cat) {
            const isProtected = parseInt(cat.is_protected) === 1;
            const colorSwatch = '<span class="d-inline-block" style="width:20px;height:20px;border-radius:50%;background:' + escapeHtml(cat.color || '#7c1314') + ';border:1px solid #ddd;"></span>';
            const protectedBadge = isProtected ? ' <span class="badge badge-warning ml-1">' + couponCategoriesTranslations.protected_label + '</span>' : '';
            const deleteBtn = isProtected
                ? ''
                : '<a class="dropdown-item text-danger delete-coupon-category" href="javascript:void(0);" data-id="' + cat.id + '"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>';

            const row = '<tr>' +
                '<td>' + colorSwatch + '</td>' +
                '<td><strong>' + escapeHtml(cat.name) + '</strong>' + protectedBadge + '</td>' +
                '<td><code>' + escapeHtml(cat.slug) + '</code></td>' +
                '<td>' + escapeHtml(cat.description || '-') + '</td>' +
                '<td><span class="badge badge-info">' + (cat.coupon_count || 0) + ' ' + couponCategoriesTranslations.coupons + '</span></td>' +
                '<td>' +
                    '<div class="btn-group">' +
                        '<button class="btn btn-sm btn-primary edit-coupon-category" data-id="' + cat.id + '" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>"><i class="fa fa-edit"></i></button>' +
                        (isProtected ? '' :
                        '<button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"><span class="sr-only">Toggle</span></button>' +
                        '<div class="dropdown-menu dropdown-menu-right">' +
                            '<a class="dropdown-item edit-coupon-category" href="javascript:void(0);" data-id="' + cat.id + '"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>' +
                            '<div class="dropdown-divider"></div>' +
                            deleteBtn +
                        '</div>') +
                    '</div>' +
                '</td>' +
                '</tr>';
            tbody.append(row);
        });
    }

    function updatePagination(total, pages, current) {
        totalPages = pages;
        currentPage = current;

        $('#coupon-categories-pagination-controls').show();

        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, total);
        $('#coupon-categories-showing-info').text(couponCategoriesTranslations.showing + ' ' + start + '-' + end + ' ' + couponCategoriesTranslations.of + ' ' + total);

        const html = [];
        html.push('<li class="page-item ' + (current === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current - 1) + '">' + couponCategoriesTranslations.previous + '</a></li>');

        const maxBtns = 5;
        let startPage = Math.max(1, current - Math.floor(maxBtns / 2));
        let endPage = Math.min(pages, startPage + maxBtns - 1);
        if (endPage - startPage < maxBtns - 1) {
            startPage = Math.max(1, endPage - maxBtns + 1);
        }

        if (startPage > 1) {
            html.push('<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>');
            if (startPage > 2) html.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
        for (let i = startPage; i <= endPage; i++) {
            html.push('<li class="page-item ' + (i === current ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>');
        }
        if (endPage < pages) {
            if (endPage < pages - 1) html.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
            html.push('<li class="page-item"><a class="page-link" href="#" data-page="' + pages + '">' + pages + '</a></li>');
        }
        html.push('<li class="page-item ' + (current === pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (current + 1) + '">' + couponCategoriesTranslations.next + '</a></li>');

        $('#coupon-categories-pagination').html(html.join(''));
    }

    $(document).on('click', '#coupon-categories-pagination a.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
            loadCategories(page);
        }
    });

    let searchTimeout;
    $('#coupon-categories-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchTerm = $('#coupon-categories-search').val();
            loadCategories(1);
        }, 500);
    });

    $('#create-coupon-category-btn').on('click', function() {
        $('#coupon-category-modal-title').html('<i class="fa fa-folder"></i> ' + couponCategoriesTranslations.add_category);
        $('#coupon-category-form')[0].reset();
        $('#coupon-category-id').val('');
        $('#coupon-category-color').val('#7c1314');
        $('#coupon-category-color-text').val('#7C1314');
        showModal();
    });

    $(document).on('click', '.edit-coupon-category', function() {
        const id = $(this).data('id');
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: { action: 'sc_get_coupon_category', nonce: scDashboard.nonce, id: id }
        }).done(function(response) {
            if (response.success) {
                const c = response.data.category;
                $('#coupon-category-modal-title').html('<i class="fa fa-edit"></i> ' + couponCategoriesTranslations.edit_category);
                $('#coupon-category-id').val(c.id);
                $('#coupon-category-name').val(c.name);
                $('#coupon-category-slug').val(c.slug);
                $('#coupon-category-description').val(c.description || '');
                const color = c.color || '#7c1314';
                $('#coupon-category-color').val(color);
                $('#coupon-category-color-text').val(color.toUpperCase());
                showModal();
            }
        });
    });

    $(document).on('click', '.delete-coupon-category', function() {
        const id = $(this).data('id');
        const btn = $(this);
        const confirmFn = (typeof showDeleteConfirm === 'function') ? showDeleteConfirm() : Promise.resolve({ isConfirmed: confirm('Delete this category? Its coupons will move to General.') });

        Promise.resolve(confirmFn).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true);
                $.ajax({
                    url: scDashboard.ajaxurl,
                    type: 'POST',
                    data: { action: 'sc_delete_coupon_category', nonce: scDashboard.nonce, id: id }
                }).done(function(response) {
                    if (response.success) {
                        loadCategories(currentPage);
                    } else {
                        if (typeof showError === 'function') showError(response.data.message || couponCategoriesTranslations.error_deleting);
                        else alert(response.data.message || couponCategoriesTranslations.error_deleting);
                        btn.prop('disabled', false);
                    }
                });
            }
        });
    });

    $('#coupon-category-form').on('submit', function(e) {
        e.preventDefault();

        const btn = $('#save-coupon-category-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + couponCategoriesTranslations.saving);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_save_coupon_category',
                nonce: scDashboard.nonce,
                id:          $('#coupon-category-id').val(),
                name:        $('#coupon-category-name').val(),
                slug:        $('#coupon-category-slug').val(),
                description: $('#coupon-category-description').val(),
                color:       $('#coupon-category-color').val()
            }
        }).done(function(response) {
            if (response.success) {
                hideModal();
                loadCategories(currentPage);
            } else {
                if (typeof showError === 'function') showError(response.data.message || couponCategoriesTranslations.error_saving);
                else alert(response.data.message || couponCategoriesTranslations.error_saving);
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + couponCategoriesTranslations.save_category);
        });
    });

    $('#coupon-category-name').on('blur', function() {
        if ($('#coupon-category-slug').val() === '') {
            const name = $(this).val();
            const slug = name.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/--+/g, '-').trim();
            $('#coupon-category-slug').val(slug);
        }
    });

    $('#coupon-category-color').on('input change', function() {
        $('#coupon-category-color-text').val($(this).val().toUpperCase());
    });

    function showModal() {
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#couponCategoryModal').modal('show');
            } else {
                $('#couponCategoryModal').addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        } catch (e) {
            $('#couponCategoryModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    }
    function hideModal() {
        try {
            if (typeof $.fn.modal !== 'undefined') {
                $('#couponCategoryModal').modal('hide');
            } else {
                $('#couponCategoryModal').removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        } catch (e) {
            $('#couponCategoryModal').removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    }

    $('[data-dismiss="modal"]').on('click', function() {
        hideModal();
    });

    loadCategories();
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
